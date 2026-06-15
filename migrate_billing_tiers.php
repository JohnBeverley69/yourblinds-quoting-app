<?php
declare(strict_types=1);

/**
 * Migration: move billing to the tier ladder (Bronze/Silver/Gold/Platinum).
 *
 *   1. Seeds plan_pricing rows for silver (£20), gold (£40), platinum (£60).
 *      Only inserts when missing — never overwrites a price you've since
 *      edited on /master-admin/pricing.php.
 *   2. Adds tenant_subscriptions.commitment_end_at (DATE NULL) — the date a
 *      minimum-term plan (Platinum's 12-month contract) can first be cancelled.
 *
 * The old à-la-carte plan_pricing rows (maps/postcode_lookup/accounts) are left
 * in place but no longer offered — the app only shows plans defined in
 * billing_plans.php. Deactivate their PayPal plans in your PayPal dashboard.
 *
 * Idempotent. Run via /migrate_billing_tiers.php (super-admin) then delete.
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

set_exception_handler(function (Throwable $e) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "MIGRATION FAILED\n================\n\n";
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'In:    ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
});

// 1) Seed tier prices (insert-if-missing, preserving any edited price). ---------
$seed = $pdo->prepare(
    'INSERT INTO plan_pricing (plan_code, price_gbp_monthly)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE plan_code = plan_code'   // no-op: keep existing price
);
foreach (['silver' => 20, 'gold' => 40, 'platinum' => 60] as $code => $price) {
    $seed->execute([$code, $price]);
    echo "plan_pricing[$code]: " . ($seed->rowCount() > 0 ? "seeded at £$price" : 'already present (kept)') . "\n";
}

// 2) commitment_end_at on tenant_subscriptions. --------------------------------
$col = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$col->execute(['tenant_subscriptions', 'commitment_end_at']);
if ($col->fetchColumn() === false) {
    $pdo->exec('ALTER TABLE tenant_subscriptions ADD COLUMN commitment_end_at DATE NULL');
    echo "tenant_subscriptions.commitment_end_at: added\n";
} else {
    echo "tenant_subscriptions.commitment_end_at: already present (skipped)\n";
}

echo "\n";
echo "Done. Now on /master-admin/pricing.php click 'Create on PayPal' for\n";
echo "Silver, Gold and Platinum to create the sandbox plans, then they're\n";
echo "subscribable on the tenant Billing page.\n";
echo "\n";
echo "Delete this file from the server once you're happy.\n";
