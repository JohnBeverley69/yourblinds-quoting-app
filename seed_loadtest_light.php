<?php
declare(strict_types=1);

/**
 * TEMP (ceiling test): create N lightweight logins loadtest1..N on the Beverley
 * tenant (client_id=3) so a ramped throughput test can log in as each and place
 * Beverley orders. NO catalogue push — these use Beverley's own catalogue, so
 * there's zero product-row bloat and cleanup is trivial.
 *
 *   loadtest1 .. loadtestN   password: LoadTest2026!
 *
 * Idempotent, super-admin: /seed_loadtest_light.php?count=60
 * Undo: /cleanup_loadtest_light.php   (delete this + cleanup when done)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const LT_TENANT = 3;
const LT_PW     = 'LoadTest2026!';
$count = max(1, min(200, (int) ($_GET['count'] ?? 60)));

$hash = password_hash(LT_PW, PASSWORD_DEFAULT);
$exists = $pdo->prepare('SELECT id FROM client_users WHERE username = ? LIMIT 1');
$ins = $pdo->prepare(
    "INSERT INTO client_users
        (client_id, username, email, full_name, password_hash, role, active, is_super_admin,
         email_verified_at, can_create_quotes, can_create_orders, can_view_costs)
     VALUES (?, ?, ?, ?, ?, 'admin', 1, 0, NOW(), 1, 1, 1)"
);

$made = 0; $skip = 0;
for ($i = 1; $i <= $count; $i++) {
    $u = 'loadtest' . $i;
    $exists->execute([$u]);
    if ($exists->fetchColumn()) { $skip++; continue; }
    $ins->execute([LT_TENANT, $u, $u . '@loadtest.invalid', 'Load Test ' . $i, $hash]);
    $made++;
}

echo "Light load-test logins on client_id=" . LT_TENANT . ": created {$made}, skipped {$skip}.\n";
echo "Logins: loadtest1 .. loadtest{$count}   password: " . LT_PW . "\n";
echo "No catalogue push — they order from Beverley's own catalogue. Cleanup: /cleanup_loadtest_light.php\n";
