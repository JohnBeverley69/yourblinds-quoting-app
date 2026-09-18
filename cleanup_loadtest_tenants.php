<?php
declare(strict_types=1);

/**
 * TEMP: tear down the load-test TENANTS created by seed_loadtest_tenants.php —
 * their pushed catalogues, logins, orders and floor jobs. Matches ONLY clients
 * whose company_name starts 'LoadTest Tenant ' (never the factory, never a real
 * client). Resumable + batched (cascade-deleting a pushed catalogue is heavy):
 *   /cleanup_loadtest_tenants.php?batch=5
 * Reload until it says all gone. Delete this file + the seed when finished.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
$autochain = (($_GET['auto'] ?? '') === '1');
header('Content-Type: ' . ($autochain ? 'text/html' : 'text/plain') . '; charset=utf-8');
@set_time_limit(600);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$factory = function_exists('factory_client_id') ? factory_client_id() : 3;
$batch   = max(1, min(20, (int) ($_GET['batch'] ?? 5)));

// The load-test tenants only — belt and braces: name prefix, not the factory,
// not a factory account.
$all = $pdo->prepare(
    "SELECT id FROM clients
      WHERE company_name LIKE 'LoadTest Tenant %' AND id <> ? AND COALESCE(is_factory,0) = 0
      ORDER BY id LIMIT ?"
);
$all->bindValue(1, $factory, PDO::PARAM_INT);
$all->bindValue(2, $batch, PDO::PARAM_INT);
$all->execute();
$tids = array_map('intval', $all->fetchAll(PDO::FETCH_COLUMN));

$remaining = (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE company_name LIKE 'LoadTest Tenant %' AND id <> " . (int) $factory . " AND COALESCE(is_factory,0)=0")->fetchColumn();

echo "Tearing down load-test tenants — {$remaining} remain, removing up to {$batch} this pass.\n\n";

if (!$tids) { echo "Nothing to do.\n"; exit; }

$tryExec = static function (string $sql, array $args) use ($pdo): void {
    try { $pdo->prepare($sql)->execute($args); } catch (Throwable $e) { /* table absent — skip */ }
};

$done = 0;
foreach ($tids as $tid) {
    // Rows with no FK to clients/quotes — must go before the cascade delete.
    $tryExec("DELETE s FROM factory_blind_streams s JOIN factory_blind_jobs j ON j.id=s.blind_job_id JOIN quotes q ON q.id=j.quote_id WHERE q.client_id=?", [$tid]);
    $tryExec("DELETE j FROM factory_blind_jobs j JOIN quotes q ON q.id=j.quote_id WHERE q.client_id=?", [$tid]);
    $tryExec("DELETE fj FROM factory_jobs fj JOIN quotes q ON q.id=fj.quote_id WHERE q.client_id=?", [$tid]);
    $tryExec("DELETE FROM supplier_orders WHERE client_id=?", [$tid]);
    $tryExec("DELETE FROM trade_discounts WHERE client_id=?", [$tid]);
    $tryExec("DELETE FROM trade_commissions WHERE client_id=?", [$tid]);
    $tryExec("DELETE FROM factory_ar_delivery_notes WHERE account_client_id=?", [$tid]);
    $tryExec("DELETE FROM factory_ar_invoices WHERE account_client_id=?", [$tid]);
    $tryExec("DELETE FROM factory_ar_payments WHERE account_client_id=?", [$tid]);

    // The big cascade: products (+options/systems/extras/choices/price_tables/rows),
    // quotes (+items/extras), client_users, client_settings, customers, appointments.
    $pdo->prepare('DELETE FROM clients WHERE id = ? AND company_name LIKE ? AND COALESCE(is_factory,0)=0')
        ->execute([$tid, 'LoadTest Tenant %']);
    $done++;
    echo "  removed tenant client {$tid}\n";
}

$left = (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE company_name LIKE 'LoadTest Tenant %' AND id <> " . (int) $factory . " AND COALESCE(is_factory,0)=0")->fetchColumn();
echo "\nRemoved {$done} this pass. {$left} load-test tenant(s) remain.\n";
if ($left > 0) {
    echo "\nNOT DONE — reload to remove the next {$batch}.\n";
    if ($autochain) {
        // Pause a few seconds between passes so a fragile box gets breathing room.
        echo '<meta http-equiv="refresh" content="4;url=/cleanup_loadtest_tenants.php?batch=' . $batch . '&auto=1&_=' . time() . '">';
    }
} else {
    echo "\nALL GONE.\n";
}
