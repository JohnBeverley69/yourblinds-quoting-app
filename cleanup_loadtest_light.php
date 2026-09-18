<?php
declare(strict_types=1);

/**
 * TEMP: undo the light ceiling test. Deletes the LOADTEST-tagged orders placed
 * on the Beverley tenant (client_id=3) and the loadtest1..N logins. Light —
 * these orders use Beverley's own products (no per-tenant catalogue to cascade),
 * so this is quick. Super-admin: /cleanup_loadtest_light.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
const LT_TENANT = 3;

$tryExec = static function (string $sql, array $args) use ($pdo): void {
    try { $pdo->prepare($sql)->execute($args); } catch (Throwable $e) { /* table absent — skip */ }
};

// Dummy orders on Beverley, tagged LOADTEST by the harness.
$q = $pdo->prepare("SELECT id FROM quotes WHERE client_id = ? AND end_customer_name LIKE 'LOADTEST%'");
$q->execute([LT_TENANT]);
$ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
echo "Found " . count($ids) . " LOADTEST order(s) on client " . LT_TENANT . ".\n";

$deleted = 0;
if ($ids) {
    // Delete in chunks so a big run's worth of orders can't lock the box.
    foreach (array_chunk($ids, 200) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $tryExec("DELETE s FROM factory_blind_streams s JOIN factory_blind_jobs j ON j.id=s.blind_job_id WHERE j.quote_id IN ($ph)", $chunk);
        $tryExec("DELETE FROM factory_blind_jobs WHERE quote_id IN ($ph)", $chunk);
        $tryExec("DELETE FROM factory_jobs WHERE quote_id IN ($ph)", $chunk);
        $del = $pdo->prepare("DELETE FROM quotes WHERE id IN ($ph)");   // cascades items/extras
        $del->execute($chunk);
        $deleted += $del->rowCount();
    }
}
echo "Deleted {$deleted} order(s) (+ their line items / floor jobs).\n";

$du = $pdo->prepare("DELETE FROM client_users WHERE client_id = ? AND username LIKE 'loadtest%'");
$du->execute([LT_TENANT]);
echo "Deleted " . $du->rowCount() . " loadtest login(s).\n\nCleanup complete.\n";
