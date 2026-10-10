<?php
declare(strict_types=1);

/**
 * Clear ORPHANED remakes — rows whose orders no longer exist.
 *
 * A remake points at two orders and has a foreign key to neither: the original
 * (source_quote_id) and, once approved, the remake order it became
 * (remake_quote_id). Delete both orders and the remake row survives, pointing at
 * nothing: rm_list() shows it as "(order deleted)" with "Blind (no longer on the
 * order)", and if it is sitting at 'requested' it also keeps the sidebar badge
 * and the Waiting-for-approval tab counting it for ever.
 *
 * That is what the first force deletes left behind (PR #1030): fod_delete_order()
 * resets a remake to 'requested' when its order goes, which is right for an
 * ordinary delete but wrong for a force delete. The force delete now purges them
 * as it goes — this clears the ones from before that fix, and is safe to keep
 * around for any that appear another way.
 *
 * It only ever touches remakes where BOTH orders are gone, so it cannot remove
 * one that still has work behind it. Says what it would do before doing it.
 *
 * Super-admin, web-runnable: /setup/tools/clear_orphan_remakes.php
 *   (add ?apply=1 to actually delete — without it, it only reports)
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once dirname(__DIR__, 2) . '/_partials/factory_order_delete.php';   // fod_delete_remakes
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$apply = (string) ($_GET['apply'] ?? ($argv[1] ?? '')) === '1';

try {
    $pdo->query('SELECT 1 FROM factory_remakes LIMIT 0');
} catch (Throwable $e) {
    echo "factory_remakes doesn't exist here — nothing to do.\n";
    exit;
}

// Both orders gone (or never set). LEFT JOINs, so a NULL remake_quote_id counts
// as "no remake order" rather than dropping the row.
$st = $pdo->query(
    "SELECT r.id, r.status, r.reason_label, r.source_quote_id, r.remake_quote_id,
            c.company_name AS account_name, r.created_at
       FROM factory_remakes r
       LEFT JOIN quotes q  ON q.id  = r.source_quote_id
       LEFT JOIN quotes rq ON rq.id = r.remake_quote_id
       LEFT JOIN clients c ON c.id  = r.account_client_id
      WHERE q.id IS NULL AND rq.id IS NULL
   ORDER BY r.id"
);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No orphaned remakes — nothing to clear.\n";
    exit;
}

printf("%d orphaned remake%s (both orders gone):\n\n", count($rows), count($rows) === 1 ? '' : 's');
foreach ($rows as $r) {
    printf("  #%-4d %-10s %-28s %s · source order #%s, remake order #%s\n",
        $r['id'], $r['status'], (string) ($r['reason_label'] ?? ''),
        (string) ($r['account_name'] ?? 'unknown account'),
        (string) ($r['source_quote_id'] ?? '—'), (string) ($r['remake_quote_id'] ?? '—'));
}

if (!$apply) {
    echo "\nNothing deleted. Re-run with &apply=1 to remove these.\n";
    exit;
}

$ids = array_map(static fn ($r) => (int) $r['id'], $rows);
$pdo->beginTransaction();
try {
    $n = fod_delete_remakes($pdo, $ids);
    $pdo->commit();
    printf("\nRemoved %d remake%s, their ticked blinds and any fault photos.\n", $n, $n === 1 ? '' : 's');
    echo "The Remakes tabs and the sidebar badge will be right on the next page load.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\nFailed, nothing changed: " . $e->getMessage() . "\n";
    exit(1);
}
