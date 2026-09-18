<?php
declare(strict_types=1);

/**
 * TEMP: undo the load test. Deletes every dummy order the harness placed
 * (quotes whose end_customer_name starts 'LOADTEST') plus the loadtest1..N
 * accounts. Web-runnable by a super-admin: /cleanup_loadtest.php
 *
 * Pass ?accounts=keep to delete only the dummy orders and leave the logins
 * (handy between runs). Delete this file + seed_loadtest_accounts.php when done.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const LT_TENANT = 3;
$keepAccounts = (($_GET['accounts'] ?? '') === 'keep');

echo "Cleaning up load-test data…\n\n";

// Dummy orders are tagged by the harness with a LOADTEST customer name.
$q = $pdo->prepare("SELECT id FROM quotes WHERE client_id = ? AND end_customer_name LIKE 'LOADTEST%'");
$q->execute([LT_TENANT]);
$quoteIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));

echo "Found " . count($quoteIds) . " dummy order(s).\n";

$deletedQuotes = 0;
if ($quoteIds) {
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));

    // Floor rows first (no cascade from quotes to these).
    $tryExec = static function (string $sql, array $args) use ($pdo): void {
        try { $pdo->prepare($sql)->execute($args); } catch (Throwable $e) { /* table absent — skip */ }
    };
    $tryExec("DELETE s FROM factory_blind_streams s JOIN factory_blind_jobs j ON j.id = s.blind_job_id WHERE j.quote_id IN ($ph)", $quoteIds);
    $tryExec("DELETE FROM factory_blind_jobs WHERE quote_id IN ($ph)", $quoteIds);
    $tryExec("DELETE FROM factory_jobs WHERE quote_id IN ($ph)", $quoteIds);
    $tryExec("DELETE FROM supplier_orders WHERE quote_id IN ($ph)", $quoteIds);
    $tryExec("DELETE FROM appointments WHERE quote_id IN ($ph)", $quoteIds);
    // Any AR artefacts a dispatch may have created.
    $tryExec("DELETE FROM factory_ar_delivery_notes WHERE source_quote_id IN ($ph)", $quoteIds);
    $tryExec("DELETE FROM factory_ar_invoices WHERE source_quote_id IN ($ph)", $quoteIds);

    // Quotes last — cascades quote_items + quote_item_extras.
    $del = $pdo->prepare("DELETE FROM quotes WHERE id IN ($ph)");
    $del->execute($quoteIds);
    $deletedQuotes = $del->rowCount();
}
echo "Deleted {$deletedQuotes} order(s) and their line items / floor jobs.\n";

$deletedUsers = 0;
if (!$keepAccounts) {
    $du = $pdo->prepare("DELETE FROM client_users WHERE client_id = ? AND username LIKE 'loadtest%'");
    $du->execute([LT_TENANT]);
    $deletedUsers = $du->rowCount();
    echo "Deleted {$deletedUsers} loadtest account(s).\n";
} else {
    echo "Kept loadtest accounts (?accounts=keep).\n";
}

echo "\nCleanup complete.\n";
