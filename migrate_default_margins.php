<?php
declare(strict_types=1);

/**
 * Migration: tenant-wide default margins for price tables + options.
 *
 * Adds two columns to client_settings so a tenant can set their
 * margin once instead of touching every product × system × choice:
 *
 *   default_price_table_markup_pct
 *     → fallback used by pe_markup_for_system() when no
 *       client_markups row exists for the (product, system).
 *       Existing explicit rows still override.
 *
 *   default_options_markup_pct
 *     → applied as an uplift on each option choice's contribution
 *       (price_delta / price_percent / price_per_metre / width_table)
 *       inside pe_apply_extra(). Per-choice override available via
 *       product_extra_choices.markup_pct_override (added below).
 *
 * SAFETY: existing options have their customer-facing prices
 * already baked in (a tenant who set price_delta = £10 meant
 * "charge the customer £10"). Adding a default markup retroactively
 * would inflate every existing price. So we backfill
 * markup_pct_override = 0 on every existing choice — they keep
 * their flat prices regardless of what default the tenant later
 * sets. New choices created after this migration get the default
 * applied (override stays NULL = "use default"), so the feature
 * only affects new data going forward.
 *
 * Idempotent. Run via /migrate_default_margins.php (super-admin).
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

function dm_col_exists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = ?
            AND COLUMN_NAME  = ? LIMIT 1'
    );
    $st->execute([$table, $col]);
    return $st->fetchColumn() !== false;
}

$ops = [];

// ── client_settings.default_price_table_markup_pct ────────────────────
if (!dm_col_exists($pdo, 'client_settings', 'default_price_table_markup_pct')) {
    $pdo->exec(
        "ALTER TABLE client_settings
         ADD COLUMN default_price_table_markup_pct DECIMAL(5,2) NOT NULL DEFAULT 0"
    );
    $ops[] = 'Added client_settings.default_price_table_markup_pct';
} else {
    $ops[] = 'client_settings.default_price_table_markup_pct already present';
}

// ── client_settings.default_options_markup_pct ────────────────────────
if (!dm_col_exists($pdo, 'client_settings', 'default_options_markup_pct')) {
    $pdo->exec(
        "ALTER TABLE client_settings
         ADD COLUMN default_options_markup_pct DECIMAL(5,2) NOT NULL DEFAULT 0"
    );
    $ops[] = 'Added client_settings.default_options_markup_pct';
} else {
    $ops[] = 'client_settings.default_options_markup_pct already present';
}

// ── product_extra_choices.markup_pct_override ─────────────────────────
//
// NULL    = use the tenant default
// numeric = override (0 = "no markup on this choice")
if (!dm_col_exists($pdo, 'product_extra_choices', 'markup_pct_override')) {
    $pdo->exec(
        "ALTER TABLE product_extra_choices
         ADD COLUMN markup_pct_override DECIMAL(5,2) NULL DEFAULT NULL"
    );
    $ops[] = 'Added product_extra_choices.markup_pct_override (nullable)';

    // SAFETY backfill — existing choices have customer-facing prices
    // already baked in, so we explicitly set override=0 on all of
    // them. The default markup ONLY applies to choices created after
    // this migration runs (their override stays NULL).
    $affected = $pdo->exec(
        "UPDATE product_extra_choices
            SET markup_pct_override = 0
          WHERE markup_pct_override IS NULL"
    );
    $ops[] = "Backfilled markup_pct_override = 0 on $affected existing choices "
           . '(prevents retro-uplift)';
} else {
    $ops[] = 'product_extra_choices.markup_pct_override already present';
}

echo "MIGRATION OK\n============\n\n";
foreach ($ops as $line) echo '- ' . $line . "\n";
echo "\nAll done.\n";
