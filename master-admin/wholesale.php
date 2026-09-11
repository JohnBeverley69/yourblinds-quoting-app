<?php
declare(strict_types=1);

/**
 * Master Admin · Wholesale (Layer B A/R hub).
 *
 * Beverley billing its trade accounts: placed orders that contain Beverley-owned
 * lines, and the documents raised against them. Phase 2B adds delivery notes;
 * invoices / credit notes / payments / statements follow.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../_partials/app_settings.php';

requireSuperAdmin();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();

$dnReady  = ar_table_ready($pdo, 'factory_ar_delivery_notes');
$invReady = ar_table_ready($pdo, 'factory_ar_invoices');
$cnReady  = ar_table_ready($pdo, 'factory_ar_credit_notes');

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'dn_raise') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$dnReady) throw new RuntimeException('Run /migrate_ar_delivery_notes.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, client_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND client_id <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');

            $dn = ar_create_delivery_note($pdo, $factory, $qid, (int) $order['client_id'], (int) ($user['user_id'] ?? 0), false);
            $_SESSION['flash_success'] = 'Delivery note ' . $dn['number'] . ' created (draft). View/print it below, then mark it dispatched.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise delivery note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'dn_dispatch' || $action === 'dn_cancel') {
        $dnId = (int) ($_POST['dn_id'] ?? 0);
        try {
            if ($action === 'dn_dispatch') {
                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'dispatched', dispatched_at = NOW() WHERE id = ? AND factory_client_id = ? AND status = 'draft'")
                    ->execute([$dnId, $factory]);
                $_SESSION['flash_success'] = 'Delivery note marked dispatched.';
            } else {
                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'cancelled' WHERE id = ? AND factory_client_id = ? AND status <> 'cancelled'")
                    ->execute([$dnId, $factory]);
                $_SESSION['flash_success'] = 'Delivery note cancelled.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update delivery note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'inv_raise') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$invReady) throw new RuntimeException('Run /migrate_ar_invoices.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, client_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND client_id <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');

            // Guard: don't double-invoice an order (a non-void invoice already covers it).
            if ($existingInv = ar_order_invoice_number($pdo, $qid)) {
                throw new RuntimeException('Order already invoiced on ' . $existingInv . ' (void it first to re-invoice).');
            }

            $inv = ar_create_invoice($pdo, $factory, $qid, (int) $order['client_id'], (int) ($user['user_id'] ?? 0), false);
            $_SESSION['flash_success'] = 'Invoice ' . $inv['number'] . ' raised (£' . number_format($inv['total'], 2) . '). View/send it below.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    // One-step commit: dispatch the delivery note AND create + send the invoice in a
    // single action. Idempotent per order — a reprint or a duplicate delivery note
    // never re-invoices (the non-void-invoice guard below fires once). Used by the
    // "Print DN & invoice" button when auto-invoice mode is on.
    if ($action === 'deliver_invoice') {
        $qid = (int) ($_POST['quote_id'] ?? 0);
        try {
            if (!$dnReady)  throw new RuntimeException('Run /migrate_ar_delivery_notes.php first.');
            if (!$invReady) throw new RuntimeException('Run /migrate_ar_invoices.php first.');

            $q = $pdo->prepare(
                "SELECT id, quote_number, client_id FROM quotes
                  WHERE id = ? AND status IN ('ordered','fitted','invoiced','paid') AND client_id <> ? LIMIT 1"
            );
            $q->execute([$qid, $factory]);
            $order = $q->fetch(PDO::FETCH_ASSOC);
            if (!$order) throw new RuntimeException('Order not found, not placed, or not a trade-account order.');
            $accId = (int) $order['client_id'];

            $pdo->beginTransaction();

            // 1) Ensure a dispatched delivery note exists (reuse the newest live one).
            $ex = $pdo->prepare(
                "SELECT id, dn_number, status FROM factory_ar_delivery_notes
                  WHERE source_quote_id = ? AND factory_client_id = ? AND status <> 'cancelled'
               ORDER BY id DESC LIMIT 1"
            );
            $ex->execute([$qid, $factory]);
            $dnRow = $ex->fetch(PDO::FETCH_ASSOC);
            if ($dnRow) {
                $dnNum = (string) $dnRow['dn_number'];
                if ($dnRow['status'] === 'draft') {
                    $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'dispatched', dispatched_at = NOW() WHERE id = ? AND factory_client_id = ?")
                        ->execute([(int) $dnRow['id'], $factory]);
                }
            } else {
                $dn    = ar_create_delivery_note($pdo, $factory, $qid, $accId, (int) ($user['user_id'] ?? 0), true);
                $dnNum = $dn['number'];
            }

            // 2) Create + send the invoice — once. If already invoiced (non-void), skip
            //    silently: this is the reprint / duplicate case the user called out.
            $invNum = ar_order_invoice_number($pdo, $qid);
            if ($invNum === '') {
                $inv    = ar_create_invoice($pdo, $factory, $qid, $accId, (int) ($user['user_id'] ?? 0), true);
                $invNum = $inv['number'];
                $msg    = 'Delivery note ' . $dnNum . ' dispatched · invoice ' . $invNum . ' created & sent (£' . number_format($inv['total'], 2) . ').';
            } else {
                $msg = 'Delivery note ' . $dnNum . ' dispatched. Order was already invoiced on ' . $invNum . ' — no second invoice raised.';
            }

            $pdo->commit();
            $_SESSION['flash_success'] = $msg;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not print & invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    // Flip the delivery-note flow mode (one-step auto-invoice vs two-step manual).
    if ($action === 'wh_mode') {
        $mode = (string) ($_POST['mode'] ?? '');
        $ok = app_setting_set('wholesale_dn_auto_invoice', $mode === 'two_step' ? '0' : '1');
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
            ? ($mode === 'two_step'
                ? 'Switched to two-step: raise a delivery note, then invoice separately.'
                : 'Switched to one-step: printing a delivery note creates & sends the invoice automatically.')
            : "Couldn't save the setting — run /migrate_app_settings.php (super-admin) and try again.";
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'inv_send' || $action === 'inv_void') {
        $invId = (int) ($_POST['inv_id'] ?? 0);
        try {
            if ($action === 'inv_send') {
                $pdo->prepare("UPDATE factory_ar_invoices SET status = 'sent', sent_at = NOW() WHERE id = ? AND factory_client_id = ? AND status IN ('raised')")
                    ->execute([$invId, $factory]);
                $_SESSION['flash_success'] = 'Invoice marked sent.';
            } else {
                $reason = trim((string) ($_POST['void_reason'] ?? '')) ?: 'Voided';
                // Never mutate a sent invoice's figures — void keeps its number (gap-free).
                $pdo->prepare("UPDATE factory_ar_invoices SET status = 'void', voided_at = NOW(), void_reason = ? WHERE id = ? AND factory_client_id = ? AND status <> 'void'")
                    ->execute([$reason, $invId, $factory]);
                $_SESSION['flash_success'] = 'Invoice voided (its number is kept). Raise a fresh one if needed.';
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not update invoice: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_raise') {
        $invId = (int) ($_POST['inv_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;
        try {
            if (!$cnReady) throw new RuntimeException('Run /migrate_ar_credit_notes.php first.');

            $iv = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1');
            $iv->execute([$invId, $factory]);
            $inv = $iv->fetch(PDO::FETCH_ASSOC);
            if (!$inv) throw new RuntimeException('Invoice not found.');
            if ($inv['status'] === 'void') throw new RuntimeException('That invoice is void — nothing to credit.');

            $il = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
            $il->execute([$invId]);
            $invLines = $il->fetchAll(PDO::FETCH_ASSOC);
            if (!$invLines) throw new RuntimeException('That invoice has no lines to credit.');

            // The credit note carries the ORDER's number (CN-<order>) so it lines up
            // with its invoice/DN for the account. Fall back to the CN sequence if the
            // invoice has no order link.
            $cnQuoteId = 0;
            try {
                $qo = $pdo->prepare('SELECT quote_id FROM factory_ar_invoice_orders WHERE invoice_id = ? LIMIT 1');
                $qo->execute([$invId]);
                $cnQuoteId = (int) $qo->fetchColumn();
            } catch (Throwable $e) { $cnQuoteId = 0; }

            $pdo->beginTransaction();

            $cnId = 0; $num = '';
            for ($try = 1; $try <= 3; $try++) {
                $num = $cnQuoteId > 0
                    ? ar_order_doc_number($pdo, $factory, $cnQuoteId, 'CN', 'factory_ar_credit_notes', 'cn_number')
                    : ar_next_number($pdo, $factory, 'CN', 'factory_ar_credit_notes', 'cn_number');
                try {
                    $ins = $pdo->prepare(
                        "INSERT INTO factory_ar_credit_notes
                           (factory_client_id, account_client_id, cn_number, against_invoice_id, status, issue_date,
                            reason, vat_percent, subtotal, vat, total, settle_mode, bill_to_snapshot, created_by)
                         VALUES (?, ?, ?, ?, 'issued', CURDATE(), ?, ?, ?, ?, ?, 'credit', ?, ?)"
                    );
                    $ins->execute([
                        $factory, (int) $inv['account_client_id'], $num, $invId, $reason,
                        (float) $inv['vat_percent'], (float) $inv['subtotal'], (float) $inv['vat'], (float) $inv['total'],
                        $inv['bill_to_snapshot'], (int) ($user['user_id'] ?? 0) ?: null,
                    ]);
                    $cnId = (int) $pdo->lastInsertId();
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000' && $try < 3) continue;
                    throw $e;
                }
            }

            $insL = $pdo->prepare(
                "INSERT INTO factory_ar_credit_note_lines
                   (credit_note_id, source_invoice_line_id, description, width_mm, drop_mm, quantity, unit_net, line_net, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $so = 0;
            foreach ($invLines as $l) {
                $insL->execute([
                    $cnId, (int) $l['id'], $l['description'], $l['width_mm'], $l['drop_mm'],
                    (int) $l['quantity'], (float) $l['unit_net'], (float) $l['line_net'], $so++,
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = 'Credit note ' . $num . ' raised against ' . $inv['inv_number'] . ' (£' . number_format((float) $inv['total'], 2) . ').';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not raise credit note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    if ($action === 'cn_void') {
        $cnId = (int) ($_POST['cn_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE factory_ar_credit_notes SET status = 'void', voided_at = NOW() WHERE id = ? AND factory_client_id = ? AND status <> 'void'")
                ->execute([$cnId, $factory]);
            $_SESSION['flash_success'] = 'Credit note voided.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not void credit note: ' . $e->getMessage();
        }
        header('Location: /master-admin/wholesale.php'); exit;
    }

    header('Location: /master-admin/wholesale.php'); exit;
}

// ── Load ─────────────────────────────────────────────────────────────────────
$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fAccount = (int) ($_GET['account'] ?? 0) ?: null;
$fFrom    = trim((string) ($_GET['from'] ?? ''));
$fTo      = trim((string) ($_GET['to'] ?? ''));

$accounts = ar_account_options($pdo, $factory);
$orders   = ar_placed_orders($pdo, $factory, $fAccount, $fFrom !== '' ? $fFrom : null, $fTo !== '' ? $fTo : null);

$notes = [];
if ($dnReady) {
    try {
        $ns = $pdo->prepare(
            "SELECT dn.*, c.company_name AS account_name, q.quote_number AS order_number
               FROM factory_ar_delivery_notes dn
               JOIN clients c  ON c.id = dn.account_client_id
          LEFT JOIN quotes q   ON q.id = dn.source_quote_id
              WHERE dn.factory_client_id = ?
           ORDER BY dn.id DESC LIMIT 100"
        );
        $ns->execute([$factory]);
        $notes = $ns->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$invoices = [];
if ($invReady) {
    try {
        $is = $pdo->prepare(
            "SELECT i.*, c.company_name AS account_name,
                    io.quote_id AS order_quote_id, q.quote_number AS order_number
               FROM factory_ar_invoices i
               JOIN clients c ON c.id = i.account_client_id
          LEFT JOIN factory_ar_invoice_orders io ON io.invoice_id = i.id
          LEFT JOIN quotes q ON q.id = io.quote_id
              WHERE i.factory_client_id = ?
           ORDER BY i.id DESC LIMIT 100"
        );
        $is->execute([$factory]);
        $invoices = $is->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$creditNotes = [];
if ($cnReady) {
    try {
        $cs = $pdo->prepare(
            "SELECT cn.*, c.company_name AS account_name, i.inv_number AS against_number
               FROM factory_ar_credit_notes cn
               JOIN clients c ON c.id = cn.account_client_id
          LEFT JOIN factory_ar_invoices i ON i.id = cn.against_invoice_id
              WHERE cn.factory_client_id = ?
           ORDER BY cn.id DESC LIMIT 100"
        );
        $cs->execute([$factory]);
        $creditNotes = $cs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}

$money = static fn ($n) => '&pound;' . number_format((float) $n, 2);
$fmtD  = static function (?string $dt): string {
    if (!$dt) return '&mdash;';
    $ts = strtotime($dt);
    return $ts ? date('j M Y', $ts) : '&mdash;';
};
$dnPill = static function (string $s): array {
    return $s === 'dispatched' ? ['Dispatched', '#065f46', '#d1fae5']
        : ($s === 'cancelled'  ? ['Cancelled', '#6b7280', '#e5e7eb']
        : ['Draft', '#92400e', '#fef3c7']);
};
$invPill = static function (string $s): array {
    return $s === 'paid'      ? ['Paid', '#065f46', '#d1fae5']
        : ($s === 'part_paid' ? ['Part paid', '#1e40af', '#dbeafe']
        : ($s === 'void'      ? ['Void', '#6b7280', '#e5e7eb']
        : ($s === 'sent'      ? ['Sent', '#1e40af', '#dbeafe']
        : ['Raised', '#92400e', '#fef3c7'])));
};

// ── Unify: index every document by the order it belongs to ───────────────────
$dnByOrder = [];
foreach ($notes as $dn) {
    $oid = (int) ($dn['source_quote_id'] ?? 0);
    if ($oid) $dnByOrder[$oid][] = $dn;
}
$invByOrder = [];
foreach ($invoices as $inv) {
    $oid = (int) ($inv['order_quote_id'] ?? 0);
    if ($oid) $invByOrder[$oid][] = $inv;
}
$cnByInvoice = [];
foreach ($creditNotes as $cn) {
    $iid = (int) ($cn['against_invoice_id'] ?? 0);
    if ($iid) $cnByInvoice[$iid][] = $cn;
}

// One-step (auto-invoice on DN print) is the default; two-step is the manual fallback.
$autoInvoice = app_setting_get('wholesale_dn_auto_invoice', '1') === '1';

/**
 * The live (non-void) invoice covering an order, or null. Void invoices remain
 * visible in the expander but don't set the order's stage.
 */
$liveInvoiceFor = static function (int $qid) use ($invByOrder) {
    foreach ($invByOrder[$qid] ?? [] as $iv) if ($iv['status'] !== 'void') return $iv;
    return null;
};

/**
 * Derive an order's lifecycle stage from the documents raised against it.
 * Returns [key, label, textColour, bgColour] — drives the status pill + row tint.
 */
$orderStage = static function (int $qid) use ($dnByOrder, $liveInvoiceFor, $cnByInvoice): array {
    if ($iv = $liveInvoiceFor($qid)) {
        foreach ($cnByInvoice[(int) $iv['id']] ?? [] as $cn) {
            if ($cn['status'] !== 'void') return ['credited', 'Credited', '#6b21a8', '#f3e8ff'];
        }
        if ($iv['status'] === 'paid') return ['paid',     'Paid',     '#065f46', '#d1fae5'];
        if ($iv['status'] === 'sent') return ['invoiced', 'Invoiced', '#1e40af', '#dbeafe'];
        return ['inv_raised', 'Invoiced (draft)', '#92400e', '#fef3c7'];
    }
    $delivered = false; $draft = false;
    foreach ($dnByOrder[$qid] ?? [] as $dn) {
        if ($dn['status'] === 'dispatched') $delivered = true;
        elseif ($dn['status'] === 'draft')  $draft = true;
    }
    if ($delivered) return ['delivered', 'Delivered', '#0f766e', '#ccfbf1'];
    if ($draft)     return ['dn_draft',  'DN draft',  '#92400e', '#fef3c7'];
    return ['ordered', 'Ordered', '#3730a3', '#e0e7ff'];
};

$activeNav = 'wholesale';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wholesale &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .wh-filter { display:flex; gap:0.625rem; align-items:flex-end; flex-wrap:wrap; margin:0 0 0.75rem; }
        .wh-filter label { font-size:0.75rem; color:var(--text-faint); display:block; margin-bottom:0.15rem; }
        .wh-filter select, .wh-filter input { padding:0.4rem 0.55rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); }
        .wh-pill { display:inline-block; padding:0.05rem 0.5rem; font-size:0.7rem; font-weight:700; border-radius:999px; white-space:nowrap; }
        .wh-money { font-variant-numeric:tabular-nums; text-align:right; }
        .wh-muted { color:var(--text-faint); font-size:0.8125rem; }

        /* Mode toggle */
        .wh-mode { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin:0 0 1rem; padding:0.6rem 0.8rem;
                   background:var(--bg-subtle,#f8fafc); border:1px solid var(--border); border-radius:10px; font-size:0.85rem; }
        .wh-seg { display:inline-flex; border:1px solid var(--border-strong); border-radius:999px; overflow:hidden; }
        .wh-seg button { border:0; background:transparent; padding:0.3rem 0.85rem; font:inherit; font-size:0.8rem; cursor:pointer; color:var(--text-faint); }
        .wh-seg button.on { background:var(--accent,#2563eb); color:#fff; font-weight:700; }

        /* Dense order grid (BM-style: one row per order) */
        .wh-orders { width:100%; border-collapse:collapse; font-size:0.83rem; }
        .wh-orders thead th { text-align:left; font-size:0.68rem; letter-spacing:0.04em; text-transform:uppercase;
                              color:var(--text-faint); font-weight:700; padding:0.4rem 0.55rem; border-bottom:2px solid var(--border-strong); white-space:nowrap; }
        .wh-orders tbody td { padding:0.4rem 0.55rem; border-bottom:1px solid var(--border); vertical-align:middle; }
        .wh-orders .wh-row > td { border-left:3px solid transparent; }
        .wh-orders .wh-row:hover > td { background:var(--bg-hover,#f1f5f9); }
        .wh-orders .wh-num { font-weight:700; white-space:nowrap; }
        .wh-orders .wh-caret { background:none; border:0; cursor:pointer; color:var(--text-faint); font-size:0.9rem; line-height:1; padding:0.1rem 0.25rem; transition:transform .12s; }
        .wh-orders .wh-caret[aria-expanded="true"] { transform:rotate(90deg); }
        .wh-filters input, .wh-filters select { width:100%; box-sizing:border-box; padding:0.28rem 0.4rem; font:inherit; font-size:0.78rem;
                              border:1px solid var(--border); border-radius:6px; background:var(--bg-input); }
        .wh-filters td { padding:0.3rem 0.4rem 0.55rem; border-bottom:2px solid var(--border-strong); }
        .wh-orders tr[hidden] { display:none; }
        .wh-detail > td { background:var(--bg-subtle,#f8fafc); padding:0.7rem 1rem 0.9rem 1.6rem; border-bottom:1px solid var(--border); }
        .wh-doc { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; padding:0.28rem 0; font-size:0.82rem; }
        .wh-doc + .wh-doc { border-top:1px dashed var(--border); }
        .wh-doc .wh-dnum { font-weight:700; min-width:8.5rem; }
        .wh-act { background:none; border:0; padding:0; font:inherit; font-size:0.82rem; cursor:pointer; color:var(--link); text-decoration:underline; }
        .wh-act.danger { color:#b91c1c; }
        .wh-link { color:var(--link); font-size:0.82rem; text-decoration:underline; }
        .wh-primary { display:inline-flex; margin:0; }
        .wh-none { text-align:center; color:var(--text-faint); padding:1.2rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Wholesale</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; billing your trade accounts for the orders they place &mdash; delivery notes, invoices and statements.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>
        <?php if (!$dnReady): ?>
            <div class="alert alert-error" role="alert">
                The wholesale tables aren't set up yet — run
                <a href="/migrate_ar_delivery_notes.php"><code>/migrate_ar_delivery_notes.php</code></a> (super-admin), then reload.
            </div>
        <?php endif; ?>

        <!-- Delivery-note flow mode -->
        <form method="post" action="/master-admin/wholesale.php" class="wh-mode">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="wh_mode">
            <strong style="font-weight:700">Delivery-note flow:</strong>
            <span class="wh-seg">
                <button type="submit" name="mode" value="one_step" class="<?= $autoInvoice ? 'on' : '' ?>">One-step · print &amp; invoice</button>
                <button type="submit" name="mode" value="two_step" class="<?= $autoInvoice ? '' : 'on' ?>">Two-step · manual</button>
            </span>
            <span class="wh-muted" style="font-size:0.8rem">
                <?= $autoInvoice
                    ? 'Printing a delivery note dispatches it and creates &amp; sends the invoice automatically (once per order).'
                    : 'Raise a delivery note, then raise and send the invoice yourself.' ?>
            </span>
        </form>

        <!-- One order, one row: its whole lifecycle -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Orders</h2>
            <p class="wh-muted" style="margin:0 0 0.75rem;max-width:78ch">
                Every trade-account order that contains your products, with the delivery note, invoice and any credit note
                raised against it. Open a row (&#9656;) for the documents and their actions.
            </p>

            <form method="get" action="/master-admin/wholesale.php" class="wh-filter">
                <div>
                    <label>Load account</label>
                    <select name="account">
                        <option value="">All accounts</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= $fAccount === (int) $a['id'] ? 'selected' : '' ?>><?= e((string) $a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>From</label><input type="date" name="from" value="<?= e($fFrom) ?>"></div>
                <div><label>To</label><input type="date" name="to" value="<?= e($fTo) ?>"></div>
                <button type="submit" class="btn btn-secondary btn-sm">Load</button>
                <?php if ($fAccount || $fFrom !== '' || $fTo !== ''): ?>
                    <a href="/master-admin/wholesale.php" class="wh-muted" style="margin-left:0.25rem">clear</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="wh-orders">
                    <thead>
                        <tr>
                            <th style="width:1.4rem"></th>
                            <th>Order</th><th>Account</th><th>Placed</th>
                            <th class="wh-money">Qty</th><th class="wh-money">Wholesale</th>
                            <th>Status</th><th style="text-align:right">Action</th>
                        </tr>
                        <tr class="wh-filters">
                            <td></td>
                            <td><input id="f-order" type="text" placeholder="Filter…" oninput="whFilter()"></td>
                            <td><input id="f-account" type="text" placeholder="Filter…" oninput="whFilter()"></td>
                            <td></td><td></td><td></td>
                            <td>
                                <select id="f-status" onchange="whFilter()">
                                    <option value="">All</option>
                                    <option value="ordered">Ordered</option>
                                    <option value="dn_draft">DN draft</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="inv_raised">Invoiced (draft)</option>
                                    <option value="invoiced">Invoiced</option>
                                    <option value="paid">Paid</option>
                                    <option value="credited">Credited</option>
                                </select>
                            </td>
                            <td></td>
                        </tr>
                    </thead>
                    <tbody id="wh-body">
                        <?php if (!$orders): ?>
                            <tr><td colspan="8" class="wh-none">No placed trade-account orders<?= $fAccount || $fFrom !== '' || $fTo !== '' ? ' for this filter' : '' ?>.</td></tr>
                        <?php else: foreach ($orders as $o):
                            $qid   = (int) $o['id'];
                            $ordNo = (string) ($o['quote_number'] ?: ('#' . $qid));
                            [$sKey, $sLbl, $sFg, $sBg] = $orderStage($qid);

                            $dns = $dnByOrder[$qid] ?? [];
                            $ivs = $invByOrder[$qid] ?? [];
                            $cns = [];
                            foreach ($ivs as $iv) foreach ($cnByInvoice[(int) $iv['id']] ?? [] as $c) $cns[] = $c;

                            $liveInv = $liveInvoiceFor($qid);
                            $liveDn  = null;
                            foreach ($dns as $d) { if ($d['status'] !== 'cancelled') { $liveDn = $d; break; } }
                            $dnDispatched = false;
                            foreach ($dns as $d) { if ($d['status'] === 'dispatched') { $dnDispatched = true; break; } }
                            $dnDraftLive = ($liveDn && $liveDn['status'] === 'draft');
                            $hasDocs = $dns || $ivs;
                        ?>
                            <tr class="wh-row" data-order="<?= e(strtolower($ordNo)) ?>" data-account="<?= e(strtolower((string) $o['account_name'])) ?>" data-status="<?= e($sKey) ?>">
                                <td style="border-left-color:<?= $sFg ?>">
                                    <button type="button" class="wh-caret" aria-expanded="false" aria-label="Show documents" onclick="whToggle(this)">&#9656;</button>
                                </td>
                                <td class="wh-num"><?= e($ordNo) ?></td>
                                <td><?= e((string) $o['account_name']) ?></td>
                                <td style="white-space:nowrap"><?= $fmtD($o['created_at']) ?></td>
                                <td class="wh-money"><?= (int) $o['bev_qty'] ?></td>
                                <td class="wh-money"><?= $money($o['wholesale_total']) ?></td>
                                <td><span class="wh-pill" style="background:<?= $sBg ?>;color:<?= $sFg ?>"><?= e($sLbl) ?></span></td>
                                <td style="text-align:right;white-space:nowrap">
                                    <?php if ($autoInvoice): ?>
                                        <?php if (!$liveInv): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="deliver_invoice">
                                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" <?= ($dnReady && $invReady) ? '' : 'disabled' ?>>Print DN &amp; invoice</button>
                                            </form>
                                        <?php else: ?>
                                            <a class="wh-link" href="/master-admin/invoice-pdf.php?id=<?= (int) $liveInv['id'] ?>" target="_blank">Invoice PDF</a>
                                        <?php endif; ?>
                                    <?php else: /* two-step */ ?>
                                        <?php if (!$liveInv && !$liveDn): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="dn_raise">
                                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" <?= $dnReady ? '' : 'disabled' ?>>Raise delivery note</button>
                                            </form>
                                        <?php elseif (!$liveInv && $dnDraftLive): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="dn_dispatch">
                                                <input type="hidden" name="dn_id" value="<?= (int) $liveDn['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Mark dispatched</button>
                                            </form>
                                        <?php elseif (!$liveInv && $dnDispatched): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="inv_raise">
                                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" <?= $invReady ? '' : 'disabled' ?>>Raise invoice</button>
                                            </form>
                                        <?php elseif ($liveInv && $liveInv['status'] === 'raised'): ?>
                                            <form method="post" action="/master-admin/wholesale.php" class="wh-primary">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="inv_send">
                                                <input type="hidden" name="inv_id" value="<?= (int) $liveInv['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Mark sent</button>
                                            </form>
                                        <?php elseif ($liveInv): ?>
                                            <a class="wh-link" href="/master-admin/invoice-pdf.php?id=<?= (int) $liveInv['id'] ?>" target="_blank">Invoice PDF</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="wh-detail" hidden>
                                <td colspan="8">
                                    <?php if (!$hasDocs): ?>
                                        <span class="wh-muted">No documents raised yet.</span>
                                    <?php else: ?>
                                        <?php foreach ($dns as $d): [$dl, $df, $db] = $dnPill((string) $d['status']); ?>
                                            <div class="wh-doc">
                                                <span class="wh-dnum"><?= e((string) $d['dn_number']) ?></span>
                                                <span class="wh-pill" style="background:<?= $db ?>;color:<?= $df ?>"><?= e($dl) ?></span>
                                                <span class="wh-muted"><?= $d['status'] === 'dispatched' ? $fmtD($d['dispatched_at']) : $fmtD($d['created_at']) ?></span>
                                                <a class="wh-link" href="/master-admin/delivery-note-pdf.php?id=<?= (int) $d['id'] ?>" target="_blank">View / print</a>
                                                <?php if ($d['status'] === 'draft'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="dn_dispatch">
                                                        <input type="hidden" name="dn_id" value="<?= (int) $d['id'] ?>">
                                                        <button type="submit" class="wh-act">Mark dispatched</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($d['status'] !== 'cancelled'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Cancel delivery note <?= e((string) $d['dn_number']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="dn_cancel">
                                                        <input type="hidden" name="dn_id" value="<?= (int) $d['id'] ?>">
                                                        <button type="submit" class="wh-act danger">Cancel</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php foreach ($ivs as $iv): [$il, $if, $ib] = $invPill((string) $iv['status']); ?>
                                            <div class="wh-doc">
                                                <span class="wh-dnum"><?= e((string) $iv['inv_number']) ?></span>
                                                <span class="wh-pill" style="background:<?= $ib ?>;color:<?= $if ?>"><?= e($il) ?></span>
                                                <span class="wh-money" style="min-width:5rem"><?= $money($iv['total']) ?></span>
                                                <a class="wh-link" href="/master-admin/invoice-pdf.php?id=<?= (int) $iv['id'] ?>" target="_blank">View / print</a>
                                                <?php if ($iv['status'] === 'raised'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="inv_send">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act">Mark sent</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($iv['status'] !== 'void' && $iv['status'] !== 'paid'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Void invoice <?= e((string) $iv['inv_number']) ?>? Its number is kept; raise a fresh invoice to replace it.">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="inv_void">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act danger">Void</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($cnReady && $iv['status'] !== 'void'): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Raise a full credit note for <?= e((string) $iv['inv_number']) ?> (£<?= e(number_format((float) $iv['total'], 2)) ?>)?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="cn_raise">
                                                        <input type="hidden" name="inv_id" value="<?= (int) $iv['id'] ?>">
                                                        <button type="submit" class="wh-act">Credit note</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php foreach ($cns as $c): $cvoid = $c['status'] === 'void'; ?>
                                            <div class="wh-doc">
                                                <span class="wh-dnum"><?= e((string) $c['cn_number']) ?></span>
                                                <span class="wh-pill" style="background:<?= $cvoid ? '#e5e7eb' : '#f3e8ff' ?>;color:<?= $cvoid ? '#6b7280' : '#6b21a8' ?>"><?= $cvoid ? 'Void' : 'Credit' ?></span>
                                                <span class="wh-money" style="min-width:5rem">&minus;<?= $money($c['total']) ?></span>
                                                <a class="wh-link" href="/master-admin/credit-note-pdf.php?id=<?= (int) $c['id'] ?>" target="_blank">View / print</a>
                                                <?php if (!$cvoid): ?>
                                                    <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Void credit note <?= e((string) $c['cn_number']) ?>?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="_action" value="cn_void">
                                                        <input type="hidden" name="cn_id" value="<?= (int) $c['id'] ?>">
                                                        <button type="submit" class="wh-act danger">Void</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if ($dnReady): ?>
                                            <div class="wh-doc" style="border-top:1px dashed var(--border)">
                                                <form method="post" action="/master-admin/wholesale.php" style="display:inline;margin:0" data-confirm="Raise another delivery note for order <?= e($ordNo) ?>? This does not create a second invoice.">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="_action" value="dn_raise">
                                                    <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                                    <button type="submit" class="wh-act">+ Duplicate delivery note</button>
                                                </form>
                                                <span class="wh-muted">(reprints don't re-invoice)</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
<script>
function whToggle(btn){
    var row = btn.closest('tr');
    var detail = row.nextElementSibling;
    if (!detail || !detail.classList.contains('wh-detail')) return;
    var open = detail.hidden;            // currently hidden → open it
    detail.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
}
function whFilter(){
    var o = (document.getElementById('f-order').value || '').toLowerCase();
    var a = (document.getElementById('f-account').value || '').toLowerCase();
    var s = document.getElementById('f-status').value || '';
    document.querySelectorAll('#wh-body .wh-row').forEach(function(row){
        var show = (!o || (row.dataset.order || '').indexOf(o) >= 0)
                && (!a || (row.dataset.account || '').indexOf(a) >= 0)
                && (!s || row.dataset.status === s);
        row.hidden = !show;
        var detail = row.nextElementSibling;
        if (detail && detail.classList.contains('wh-detail') && !show) {
            detail.hidden = true;
            var caret = row.querySelector('.wh-caret');
            if (caret) caret.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>
</body>
</html>
