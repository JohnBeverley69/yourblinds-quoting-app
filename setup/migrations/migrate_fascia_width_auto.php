<?php
declare(strict_types=1);

/**
 * Migration: tell a worked-out shared-fascia width from a typed one.
 *
 * Adds quote_item_extras.is_auto (TINYINT, default 0). qb_reconcile_fascia_groups
 * sets it on the carrier's "Fascia width" row when it fills in the group's total
 * itself (sum of the blind widths + joins, nothing typed). Next time a blind in the
 * group is edited or deleted the total is worked out again instead of being read
 * back as if somebody had typed it — before this, the fascia stayed at the old
 * width (wrong price, wrong cut), and a blind left on its own kept the group's width.
 *
 * Existing rows stay 0 (= typed), exactly as they behaved before.
 * Additive + idempotent. Web-runnable: /setup/migrations/migrate_fascia_width_auto.php (super-admin).
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$col = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = 'quote_item_extras'
        AND column_name = 'is_auto'"
)->fetchColumn();
if ((int) $col === 0) {
    $pdo->exec("ALTER TABLE quote_item_extras ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER user_value");
    echo "Added quote_item_extras.is_auto.\n";
} else {
    echo "quote_item_extras.is_auto already present — skipped.\n";
}

echo "\nDone. Shared fascia widths the system works out are now recalculated when a blind in the group changes.\n";
