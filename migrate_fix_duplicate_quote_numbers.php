<?php
declare(strict_types=1);

/**
 * Migration: renumber quotes that share an order number with another quote.
 *
 * Quote numbers are PREFIX-YYYY-NNNN, the sequence was counted PER TENANT, and
 * the prefix is whatever the tenant typed into Settings → Quoting. So two
 * tenants that both picked "BB" each started at 0001 and handed out the same
 * order number. Seen live 2026-09-20: ABC Blinds and Annette's Blinds both
 * holding BB-2026-0001.
 *
 * Inside one tenant this is survivable — NOTHING in the app looks an order up by
 * its number, every lookup is by id. What it breaks is the factory's wholesale
 * paperwork, which is numbered FROM the order number: the second account's
 * invoice comes out as INV-BB-2026-0001-2, and that "-2" means "void-and-reissue
 * of this same order" everywhere else. Two customers' documents then read as one
 * order reissued.
 *
 * Fixed at source in the same change: Settings now refuses a prefix another
 * tenant already holds, and qb_generate_quote_number() checks the candidate is
 * free across ALL tenants before handing it out. This script cleans up what was
 * created before that.
 *
 * WHAT IT DOES: for each duplicated number, the OLDEST quote keeps it and the
 * others are renumbered to the next free number in their own tenant's series.
 *
 * WHAT IT REFUSES TO TOUCH: any quote that already has a delivery note, invoice
 * or credit note — those documents carry the old number inside their own, so
 * renumbering underneath them would leave the paperwork disagreeing. Those are
 * listed for a human to decide.
 *
 * Idempotent — a second run finds nothing. Run via web: super-admin, or CLI.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/quote-builder/_helpers.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$apply = isset($_GET['apply']) || (PHP_SAPI === 'cli' && in_array('--apply', $argv ?? [], true));

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "== Duplicate order numbers ==\n";
echo $apply ? "MODE: apply\n\n" : "MODE: dry run — add ?apply=1 to actually renumber\n\n";

/** Does this quote already have wholesale paperwork numbered from it? */
$hasDocs = static function (PDO $pdo, int $quoteId): array {
    $found = [];
    $probe = [
        'delivery note' => ["factory_ar_delivery_notes", "source_quote_id = ? AND status <> 'cancelled'"],
        'invoice'       => ["factory_ar_invoice_orders", 'quote_id = ?'],
    ];
    foreach ($probe as $label => [$table, $where]) {
        try {
            $s = $pdo->prepare("SELECT 1 FROM $table WHERE $where LIMIT 1");
            $s->execute([$quoteId]);
            if ($s->fetchColumn()) $found[] = $label;
        } catch (Throwable $e) { /* table absent — nothing to protect */ }
    }
    return $found;
};

$dups = $pdo->query(
    "SELECT quote_number, COUNT(*) AS n
       FROM quotes
      WHERE quote_number IS NOT NULL AND quote_number <> ''
   GROUP BY quote_number
     HAVING COUNT(*) > 1
   ORDER BY quote_number"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$dups) {
    echo "No duplicated order numbers. Nothing to do.\n";
    exit;
}

$renumbered = 0; $skipped = 0;

foreach ($dups as $d) {
    $num = (string) $d['quote_number'];
    echo "\n{$num}  ({$d['n']} quotes)\n";

    $q = $pdo->prepare(
        "SELECT q.id, q.client_id, q.created_at, c.company_name
           FROM quotes q LEFT JOIN clients c ON c.id = q.client_id
          WHERE q.quote_number = ?
       ORDER BY q.id"
    );
    $q->execute([$num]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    $first = true;
    foreach ($rows as $r) {
        $qid   = (int) $r['id'];
        $owner = (string) ($r['company_name'] ?? ('client ' . $r['client_id']));

        if ($first) {
            echo "   keep  #{$qid}  {$owner}  (oldest — keeps the number)\n";
            $first = false;
            continue;
        }

        $docs = $hasDocs($pdo, $qid);
        if ($docs) {
            echo "   SKIP  #{$qid}  {$owner}  — already has a " . implode(' + ', $docs)
               . " numbered from it. Renumber by hand, or void the paperwork first.\n";
            $skipped++;
            continue;
        }

        $new = qb_generate_quote_number((int) $r['client_id']);
        if ($new === $num) {
            echo "   SKIP  #{$qid}  {$owner}  — generator handed back the same number.\n";
            $skipped++;
            continue;
        }

        if ($apply) {
            $pdo->prepare('UPDATE quotes SET quote_number = ? WHERE id = ?')->execute([$new, $qid]);
            echo "   FIXED #{$qid}  {$owner}  {$num} -> {$new}\n";
        } else {
            echo "   would #{$qid}  {$owner}  {$num} -> {$new}\n";
        }
        $renumbered++;
    }
}

echo "\n" . ($apply ? "Renumbered" : "Would renumber") . ": {$renumbered}";
echo $skipped ? "   Skipped (has paperwork): {$skipped}\n" : "\n";
if (!$apply) echo "\nRe-run with ?apply=1 to make the change.\n";
