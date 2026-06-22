<?php
declare(strict_types=1);

/**
 * Migration: per-tenant pricing basis (markup vs margin).
 *
 * Adds one column to client_settings:
 *
 *   pricing_basis  VARCHAR(10) NOT NULL DEFAULT 'markup'   -- 'markup' | 'margin'
 *
 * This does NOT change the pricing engine. The engine always works in
 * markup. The basis only changes how a tenant ENTERS and SEES that
 * number: a 'margin' tenant types a margin %, the UI converts it to the
 * equivalent markup before storing, and converts back for display. Every
 * stored value and every price stays markup, so nothing in the live
 * pricing math is affected. Default 'markup' = current behaviour for
 * every existing tenant.
 *
 * Idempotent. Run via web: /migrate_pricing_basis.php (super-admin).
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

$ops = [];
set_exception_handler(function (Throwable $e) use (&$ops) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Migration FAILED: " . $e->getMessage() . "\n\n";
    echo "Steps completed before failure:\n";
    foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
    exit(1);
});

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$table, $col]);
    return (bool) $st->fetchColumn();
};

echo "Migrating: pricing basis (markup vs margin)…\n\n";

if (!$colExists('client_settings', 'pricing_basis')) {
    $pdo->exec(
        "ALTER TABLE client_settings
           ADD COLUMN pricing_basis VARCHAR(10) NOT NULL DEFAULT 'markup'"
    );
    $ops[] = "Added client_settings.pricing_basis (default 'markup').";
} else {
    $ops[] = 'client_settings.pricing_basis already exists — skipped.';
}

echo "Migration complete.\n\n";
echo "Steps:\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nTenants can now choose Markup or Margin on Settings → Quoting → Default margins.\n";
echo "Default is Markup, so every existing tenant is unchanged.\n";
