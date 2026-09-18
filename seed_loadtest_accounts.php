<?php
declare(strict_types=1);

/**
 * TEMP load-test helper: create 50 dummy logins (loadtest1..loadtest50) on the
 * Beverley factory tenant (client_id = 3) so a stress test can log in as each and
 * place orders that route through to Beverley's Incoming Orders.
 *
 *   username: loadtest1 .. loadtest50   password: LoadTest2026!
 *   role: sales, can_create_quotes + can_create_orders, email pre-verified.
 *
 * Idempotent (skips a username that already exists). Web-runnable by a super-admin:
 *   /seed_loadtest_accounts.php
 * Undo with /cleanup_loadtest.php (deletes these accounts + their dummy orders).
 *
 * DELETE THIS FILE (and cleanup) once stress testing is done.
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

const LT_TENANT   = 3;                 // Beverley Blinds Trade tenant
const LT_COUNT    = 50;
const LT_PASSWORD = 'LoadTest2026!';

echo "Seeding load-test accounts on client_id=" . LT_TENANT . "…\n\n";

$hash = password_hash(LT_PASSWORD, PASSWORD_DEFAULT);

$exists = $pdo->prepare('SELECT id FROM client_users WHERE username = ? LIMIT 1');
$ins = $pdo->prepare(
    "INSERT INTO client_users
        (client_id, username, email, full_name, password_hash, role, active, is_super_admin,
         email_verified_at, can_create_quotes, can_create_orders, can_view_costs)
     VALUES
        (?, ?, ?, ?, ?, 'sales', 1, 0, NOW(), 1, 1, 1)"
);

$made = 0; $skipped = 0;
for ($i = 1; $i <= LT_COUNT; $i++) {
    $u = 'loadtest' . $i;
    $exists->execute([$u]);
    if ($exists->fetchColumn()) { $skipped++; continue; }
    $ins->execute([LT_TENANT, $u, $u . '@loadtest.invalid', 'Load Test ' . $i, $hash]);
    $made++;
}

echo "Done. Created {$made}, skipped {$skipped} (already existed).\n\n";
echo "Logins: loadtest1 .. loadtest" . LT_COUNT . "   password: " . LT_PASSWORD . "\n";
echo "Tenant: client_id=" . LT_TENANT . " (Beverley) — orders they place route to Incoming Orders.\n";
echo "\nWhen finished: run /cleanup_loadtest.php, then delete both temp files.\n";
