<?php
declare(strict_types=1);

/**
 * TEMP load-test helper: stand up trade-account TENANTS and push Beverley's
 * catalogue into each (the push itself is under test). Each tenant is a normal
 * clients row (is_factory=0) + client_settings + one verified admin login, then
 * push_catalogue_to_client() copies Beverley's 'Bev%' catalogue (stamping
 * source_client_id=3) so the tenant's orders route to Beverley Incoming Orders.
 *
 *   company:  LoadTest Tenant NN        login:  lttenantNN / LoadTest2026!
 *
 * HEAVY: each push copies ~6k fabrics + thousands of price cells (~a few seconds
 * each). Resumable + batched so no single request runs too long:
 *   /seed_loadtest_tenants.php?target=50&batch=5
 * Re-hit it (reload) until it says all done. Idempotent (skips existing tenants;
 * the push itself is idempotent). Undo with /cleanup_loadtest_tenants.php.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/catalogue_push.php';
requireSuperAdmin();
$autochain = (($_GET['auto'] ?? '') === '1');
header('Content-Type: ' . ($autochain ? 'text/html' : 'text/plain') . '; charset=utf-8');
@set_time_limit(600);
@ini_set('memory_limit', '512M');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$factory  = function_exists('factory_client_id') ? factory_client_id() : 3;
$target   = max(1, min(200, (int) ($_GET['target'] ?? 50)));
$batch    = max(1, min(20,  (int) ($_GET['batch']  ?? 5)));
const LT_PASSWORD = 'LoadTest2026!';

$hash = password_hash(LT_PASSWORD, PASSWORD_DEFAULT);
$pad  = static fn (int $i): string => str_pad((string) $i, 2, '0', STR_PAD_LEFT);

$findClient = $pdo->prepare('SELECT id FROM clients WHERE company_name = ? LIMIT 1');

echo "Load-test tenants — target {$target}, up to {$batch} this pass. Factory=source {$factory}.\n\n";

$processed = 0; $created = 0; $pushed = 0; $existing = 0;
for ($i = 1; $i <= $target && $processed < $batch; $i++) {
    $company = 'LoadTest Tenant ' . $pad($i);
    $findClient->execute([$company]);
    $tid = (int) $findClient->fetchColumn();
    if ($tid > 0) { $existing++; continue; }   // already stood up — skip (resumable)

    $processed++;
    $t0 = microtime(true);

    // Tenant + settings + verified admin login (mirrors master-admin/new-client.php
    // + trade-account.php user_add, which sets email_verified_at so login works).
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO clients (company_name, active) VALUES (?, 1)')->execute([$company]);
    $tid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO client_settings (client_id) VALUES (?)')->execute([$tid]);
    $pdo->prepare(
        "INSERT INTO client_users
            (client_id, username, email, full_name, password_hash, role, active, is_super_admin, email_verified_at)
         VALUES (?, ?, ?, ?, ?, 'admin', 1, 0, NOW())"
    )->execute([$tid, 'lttenant' . $i, 'lttenant' . $pad($i) . '@loadtest.invalid', $company, $hash]);
    $pdo->commit();
    $created++;

    // The push manages its own per-product transactions — never wrap it.
    $res = push_catalogue_to_client($pdo, (int) $factory, $tid, 'Bev');
    $pushed++;
    $dt = number_format(microtime(true) - $t0, 1);
    $prod = (int) ($res['products'] ?? $res['products_pushed'] ?? 0);
    echo "  #{$i}  {$company}  (client {$tid})  pushed" . ($prod ? " {$prod} products" : '') . "  {$dt}s\n";
}

// How many exist now, for the resume prompt.
$have = (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE company_name LIKE 'LoadTest Tenant %'")->fetchColumn();

echo "\nThis pass: created {$created}, pushed {$pushed} (skipped {$existing} already there).\n";
echo "Total load-test tenants now: {$have} of {$target}.\n";
if ($have < $target) {
    echo "\nNOT DONE — reload this page to push the next {$batch}.\n";
    if ($autochain) {
        // Auto-continue: reload to process the next batch without manual driving.
        $qs = 'target=' . $target . '&batch=' . $batch . '&auto=1&_=' . time();
        echo '<meta http-equiv="refresh" content="2;url=/seed_loadtest_tenants.php?' . $qs . '">';
    }
} else {
    echo "\nALL DONE. Logins: lttenant1..lttenant{$target}  password: " . LT_PASSWORD . "\n";
    echo "Each tenant can now place orders that route to Beverley Incoming Orders.\n";
}
