<?php
declare(strict_types=1);

/**
 * TEMP: clean up the dummy customers the load test created on Beverley (client 3).
 * Each dummy order auto-created a customer named 'LOADTEST-…'.
 *
 *   (no param)   REPORT only — counts + a sample of any NON-loadtest names.
 *   ?do=loadtest DELETE customers named 'LOADTEST%' on client 3 (safe — their
 *                quotes are already gone).
 *   ?do=all      DELETE every customer on client 3 (unlinks quotes first). Use
 *                only if you truly want a clean slate.
 *
 * Super-admin. Delete this file when done.
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
const C = 3;   // Beverley tenant
$do = (string) ($_GET['do'] ?? '');

$total = (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE client_id = ' . C)->fetchColumn();
$lt    = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE client_id = " . C . " AND name LIKE 'LOADTEST%'")->fetchColumn();
$other = $total - $lt;

echo "Customers on client " . C . ": {$total} total — {$lt} LOADTEST dummies, {$other} other.\n\n";

if ($do === '') {
    if ($other > 0) {
        echo "The {$other} NON-loadtest customer name(s) — check nothing real is here:\n";
        $s = $pdo->query("SELECT name FROM customers WHERE client_id = " . C . " AND (name NOT LIKE 'LOADTEST%' OR name IS NULL) ORDER BY name LIMIT 40");
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $n) echo '  - ' . ($n === null ? '(no name)' : $n) . "\n";
        if ($other > 40) echo "  … and " . ($other - 40) . " more.\n";
    }
    echo "\nNothing deleted (report only).\n";
    echo "Run  ?do=loadtest  to remove just the {$lt} dummies, or  ?do=all  to remove all {$total}.\n";
    exit;
}

if ($do === 'loadtest') {
    $st = $pdo->prepare("DELETE FROM customers WHERE client_id = " . C . " AND name LIKE 'LOADTEST%'");
    $st->execute();
    echo "Deleted " . $st->rowCount() . " LOADTEST customer(s).\n";
} elseif ($do === 'all') {
    // Unlink quotes/appointments first so the FK can't block the delete; the
    // quote keeps its own end_customer_name snapshot regardless.
    try { $pdo->exec('UPDATE quotes SET customer_id = NULL WHERE client_id = ' . C); } catch (Throwable $e) {}
    try { $pdo->exec('UPDATE appointments SET customer_id = NULL WHERE customer_id IN (SELECT id FROM customers WHERE client_id = ' . C . ')'); } catch (Throwable $e) {}
    $n = (int) $pdo->exec('DELETE FROM customers WHERE client_id = ' . C);
    echo "Deleted {$n} customer(s) (ALL on client " . C . ").\n";
} else {
    echo "Unknown ?do — use 'loadtest' or 'all'.\n";
}
echo "\nDone.\n";
