<?php
declare(strict_types=1);

/**
 * TEMP stress test: no-portal wholesale flow for N dummy trade accounts.
 *
 * Proves PR #621 end-to-end at scale: create N dummy no-portal accounts, raise a
 * Beverley "New order" quote FOR each (via the real no_create_account_quote so
 * account_client_id is tagged exactly as the UI does), give it 1–3 factory-owned
 * lines (cloned from a real captured line so every column/constraint is valid and
 * wholesale-price capture is present), place it, and — optionally — raise its
 * delivery note + invoice through the real ar_create_* helpers.
 *
 *   /_wholesale_stresstest.php?action=seed[&n=10][&docs=1]   seed (docs=1 also raises DN+INV)
 *   /_wholesale_stresstest.php?action=clean                  remove everything it made
 *
 * Dummy accounts are named "ZZ Wholesale Test NN" so clean-up can target them and
 * they sort to the bottom of any list. Super-admin only. Temp file — git rm after.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/quote-builder/_helpers.php';
require_once __DIR__ . '/master-admin/_helpers_new_order.php';
require_once __DIR__ . '/_partials/factory_ar.php';

requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$factory = function_exists('factory_client_id') ? factory_client_id() : 3;
$user    = current_user();
$userId  = (int) ($user['user_id'] ?? 0);
$action  = (string) ($_GET['action'] ?? '');
$NAME_PREFIX = 'ZZ Wholesale Test ';

/* ─────────────────────────── CLEAN ─────────────────────────────────────── */
if ($action === 'clean') {
    $ids = $pdo->prepare('SELECT id FROM clients WHERE company_name LIKE ?');
    $ids->execute([$NAME_PREFIX . '%']);
    $accIds = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
    if (!$accIds) { echo "Nothing to clean — no ZZ Wholesale Test accounts found.\n"; exit; }
    $ph = implode(',', array_fill(0, count($accIds), '?'));

    // Their quotes (owned by the factory, tagged to these accounts).
    $qs = $pdo->prepare("SELECT id FROM quotes WHERE account_client_id IN ($ph)");
    $qs->execute($accIds);
    $qIds = array_map('intval', $qs->fetchAll(PDO::FETCH_COLUMN));

    $pdo->beginTransaction();
    try {
        if ($qIds) {
            $qph = implode(',', array_fill(0, count($qIds), '?'));
            // A/R docs: payments/allocations → credit notes → invoices → delivery notes.
            $pdo->prepare("DELETE pa FROM factory_ar_payment_allocations pa JOIN factory_ar_payments p ON p.id = pa.payment_id WHERE p.account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE FROM factory_ar_payments WHERE account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE cl FROM factory_ar_credit_note_lines cl JOIN factory_ar_credit_notes cn ON cn.id = cl.credit_note_id WHERE cn.account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE FROM factory_ar_credit_notes WHERE account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE io FROM factory_ar_invoice_orders io JOIN factory_ar_invoices i ON i.id = io.invoice_id WHERE i.account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE il FROM factory_ar_invoice_lines il JOIN factory_ar_invoices i ON i.id = il.invoice_id WHERE i.account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE FROM factory_ar_invoices WHERE account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE dl FROM factory_ar_delivery_note_lines dl JOIN factory_ar_delivery_notes dn ON dn.id = dl.delivery_note_id WHERE dn.account_client_id IN ($ph)")->execute($accIds);
            $pdo->prepare("DELETE FROM factory_ar_delivery_notes WHERE account_client_id IN ($ph)")->execute($accIds);
            // The quotes themselves.
            $pdo->prepare("DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id IN ($qph))")->execute($qIds);
            $pdo->prepare("DELETE FROM quote_items WHERE quote_id IN ($qph)")->execute($qIds);
            $pdo->prepare("DELETE FROM appointments WHERE quote_id IN ($qph)")->execute($qIds);
            $pdo->prepare("DELETE FROM quotes WHERE id IN ($qph)")->execute($qIds);
        }
        // Any logins + the accounts.
        $pdo->prepare("DELETE FROM client_users WHERE client_id IN ($ph)")->execute($accIds);
        $pdo->prepare("DELETE FROM clients WHERE id IN ($ph)")->execute($accIds);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "CLEAN FAILED: " . $e->getMessage() . "\n";
        exit;
    }
    echo "Cleaned " . count($accIds) . " dummy accounts and " . count($qIds) . " orders (+ their A/R docs).\n";
    exit;
}

/* ─────────────────────────── SEED ──────────────────────────────────────── */
if ($action !== 'seed') {
    echo "Wholesale stress test.\n\n";
    echo "  ?action=seed         create 10 dummy accounts + placed orders\n";
    echo "  ?action=seed&docs=1  ...and raise a DN + invoice for each\n";
    echo "  ?action=seed&n=5     ...choose how many accounts\n";
    echo "  ?action=clean        remove everything this made\n";
    exit;
}

$n     = max(1, min(50, (int) ($_GET['n'] ?? 10)));
$docs  = (string) ($_GET['docs'] ?? '') === '1';

// Template line: any real factory-owned quote_items row (for a valid full column
// set). The clone never copies extras and overrides the wholesale-capture cols
// itself, so it lands as a clean, fully-captured, extra-free invoice line
// regardless of the template's own capture/extras state.
$tplSt = $pdo->prepare(
    "SELECT qi.* FROM quote_items qi
       JOIN products p ON p.id = qi.product_id
      WHERE COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
      ORDER BY qi.id DESC LIMIT 1"
);
$tplSt->execute([$factory]);
$tpl = $tplSt->fetch(PDO::FETCH_ASSOC);
if (!$tpl) { echo "No captured factory-owned template line found — raise/capture one real order first.\n"; exit; }
$tplCols = array_keys($tpl);
echo "Template line: quote_item #{$tpl['id']} — " . ($tpl['product_name_snapshot'] ?? '?') . "\n\n";

$already = 0;
$made    = [];
for ($i = 1; $i <= $n; $i++) {
    $label = $NAME_PREFIX . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    // Idempotent: skip an account that already exists.
    $ex = $pdo->prepare('SELECT id FROM clients WHERE company_name = ? LIMIT 1');
    $ex->execute([$label]);
    if ($ex->fetchColumn()) { $already++; continue; }

    try {
        // (No outer transaction — no_create_account_quote() and
        // qb_recompute_totals() manage their own; wrapping them would nest.)
        // 1) the dummy no-portal account
        $pdo->prepare(
            "INSERT INTO clients (company_name, contact_name, email, phone, address1, town, county, postcode, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
        )->execute([
            $label, 'Test Contact ' . $i, 'test' . $i . '@example.invalid', '01234 000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            $i . ' Test Street', 'Testville', 'Testshire', 'TE' . $i . ' 1ST',
        ]);
        $accId = (int) $pdo->lastInsertId();

        // 2) the Beverley "New order" quote FOR that account (real helper → tags account_client_id)
        $q   = no_create_account_quote($pdo, $factory, $accId, $userId);
        $qid = (int) $q['id'];

        // 3) 1–3 factory-owned lines, cloned from the template with varied figures
        $nLines = (($i % 3) + 1);                 // 1, 2, 3, 1, 2, 3…
        for ($l = 1; $l <= $nLines; $l++) {
            $qty  = (($i + $l) % 5) + 1;           // 1..5
            $base = round(18 + (($i * 3 + $l * 7) % 30) + 0.29, 2);   // ~£18–48
            $overrides = [
                'quote_id'             => $qid,
                'line_no'              => $l,
                'quantity'             => $qty,
                'base_price'           => $base,
                'subtotal_per_blind'   => $base,
                'sell_price'           => $base,
                'line_total'           => round($base * $qty, 2),
                'extras_total'         => 0,
                'markup_percent'       => 0,
                'discount_percent'     => 0,
                'trade_price_per_blind'=> $base,
                'trade_discount_percent' => 0,
                'trade_discount_amount'  => 0,
                'room_name'            => 'Room ' . $l,
            ];
            $cols = []; $vals = [];
            foreach ($tplCols as $c) {
                if ($c === 'id') continue;
                $cols[] = "`$c`";
                $vals[] = array_key_exists($c, $overrides) ? $overrides[$c] : $tpl[$c];
            }
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare('INSERT INTO quote_items (' . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
        }

        // 4) recompute totals + place it (status ordered — skips floor release,
        //    which is irrelevant to the A/R flow under test)
        qb_recompute_totals($qid);
        $pdo->prepare("UPDATE quotes SET status = 'ordered' WHERE id = ?")->execute([$qid]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "  ! {$label}: FAILED — " . $e->getMessage() . "\n";
        continue;
    }

    // 5) optionally raise DN + invoice through the real helpers (own txns)
    $docNote = '';
    if ($docs) {
        try {
            $dn  = ar_create_delivery_note($pdo, $factory, $qid, $accId, $userId, true);
            $inv = ar_create_invoice($pdo, $factory, $qid, $accId, $userId, true);
            $docNote = " → {$dn['number']} + {$inv['number']} (£" . number_format($inv['total'], 2) . ')';
        } catch (Throwable $e) {
            $docNote = ' → DOC FAILED: ' . $e->getMessage();
        }
    }

    $qnum = (string) ($q['number'] ?? $qid);
    $made[] = "  + {$label} (id {$accId}) → order {$qnum}, {$nLines} line(s){$docNote}";
}

echo implode("\n", $made) . "\n\n";
echo "Seeded " . count($made) . " new account(s)"
   . ($already ? " ({$already} already existed, skipped)" : '')
   . ($docs ? ", each with a dispatched DN + sent invoice." : ", each placed as an order.") . "\n";
echo "Open /master-admin/wholesale.php to see them. Run ?action=clean to remove.\n";
