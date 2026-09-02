<?php
declare(strict_types=1);

/**
 * Migration: per-tenant factory flag (white-label manufacturing).
 *
 *   clients.is_factory  TINYINT(1) NOT NULL DEFAULT 0
 *
 * The factory back-office was hardwired to ONE account — Beverley's master
 * ("Your Blinds", client #3, env FACTORY_CLIENT_ID) — via factory_client_id().
 * That constant is the only thing that made an account "a factory". To let other
 * trade firms run their OWN factory (their own queue, worksheets, build rules,
 * scanner), a factory has to be a per-account property, not a global constant.
 *
 * This flag is that property. An account with is_factory = 1 IS a factory:
 *   - its factory-role users can reach the factory back-office (requireFactory);
 *   - order lines for products it OWNS route to ITS queue —
 *
 *       a line belongs to factory F  iff  COALESCE(NULLIF(source_client_id,0), client_id) = F
 *
 *     i.e. products it pushed to tenants (source_client_id = F, the Beverley
 *     model) AND its own native products (client_id = F, no source), while a
 *     non-factory tenant's own products route nowhere.
 *
 * Additive + idempotent. Beverley (factory_client_id) is flagged here as the one
 * canonical factory; further factories are flagged from the master-admin
 * Factories screen (onboarding), not by editing this migration.
 *
 * Run via web: /migrate_client_is_factory.php (super-admin).
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

echo "Migrating: clients.is_factory (per-tenant factory flag)…\n\n";

if (!$colExists('clients', 'is_factory')) {
    $pdo->exec("ALTER TABLE clients ADD COLUMN is_factory TINYINT(1) NOT NULL DEFAULT 0");
    $ops[] = 'Added clients.is_factory.';
} else {
    $ops[] = 'clients.is_factory already exists — skipped.';
}

// Flag the one canonical factory (Beverley's master account). This is definitional
// — that account has always BEEN the factory; the flag just makes it data.
$factoryId = function_exists('factory_client_id') ? (int) factory_client_id() : 3;
$upd = $pdo->prepare("UPDATE clients SET is_factory = 1 WHERE id = ? AND is_factory <> 1");
$upd->execute([$factoryId]);
$ops[] = $upd->rowCount() > 0
    ? "Flagged client #{$factoryId} (Beverley) as a factory."
    : "Client #{$factoryId} (Beverley) already flagged (or missing) — no change.";

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nNext: flag further factories (e.g. Very Nice Blinds) from master-admin → Factories.\n";
