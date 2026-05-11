<?php
declare(strict_types=1);

/**
 * Migration: consolidate markup to a single source of truth.
 *
 * Before:
 *   - client_settings.default_markup_percent (tenant-wide default)
 *   - client_markups (per-product override; 0 / no row = use the default)
 *   Engine resolved markup as: per-product override > settings default > 0.
 *
 * After:
 *   - client_markups is THE markup, per (client, product). No fall-back.
 *   - Settings page no longer exposes a global default — it was just a
 *     duplicate knob that confused people.
 *
 * Migration steps:
 *   1. For every (client_id, product_id) that has NO client_markups row,
 *      INSERT one carrying that client's current default_markup_percent.
 *      Existing per-product overrides are left alone. End state: every
 *      product has an explicit markup, pricing is unchanged.
 *
 * The default_markup_percent column is intentionally left in place for
 * back-compat / easy revert. A future migration can drop it once we're
 * confident nothing reads it.
 *
 * Idempotent — re-runnable. Subsequent runs insert nothing (every
 * product already has a row).
 *
 * Run via web: /migrate_consolidate_markup.php   (super-admin login)
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

// Backfill: for each (client, product) without a markup row, insert
// one carrying the client's current default. Products belonging to
// clients with default = 0 still get a row (with 0), so the engine's
// post-migration "no fall-back" behaviour is consistent across all
// tenants.
$copied = $pdo->exec("
    INSERT INTO client_markups (client_id, product_id, markup_percent)
    SELECT p.client_id,
           p.id,
           COALESCE(cs.default_markup_percent, 0)
      FROM products p
      LEFT JOIN client_settings cs ON cs.client_id = p.client_id
      LEFT JOIN client_markups  cm ON cm.client_id = p.client_id
                                  AND cm.product_id = p.id
     WHERE cm.product_id IS NULL
");

echo "Migration complete.\n";
echo "  - Inserted $copied client_markups row(s) (one per product that\n";
echo "    didn't already have an explicit markup, carrying that\n";
echo "    tenant's previous default).\n";
echo "\n";
echo "After this + the code update, the Settings page no longer shows\n";
echo "the 'Default markup %' field. Each product carries its own markup,\n";
echo "set on the product Edit page (and on the New Product form).\n";
echo "\n";
echo "When you're confident, you can delete this file from the server.\n";
