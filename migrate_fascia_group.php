<?php
declare(strict_types=1);

/**
 * Migration: auto-grouping of roller blinds under one shared fascia.
 *
 * Adds quote_items.fascia_group — a small tag (A / B / …). Blinds on a quote
 * sharing the same non-empty tag are treated as ONE fascia: the reconcile pass
 * (qb_reconcile_fascia_groups) makes one "carrier" line carry the fascia at the
 * group's total width and sets the others to "No Fascia", so the fascia is
 * priced + cut once while each blind is still made to its own width.
 *
 * Also seeds the editable roller_fascia_join allowance (per-gap mm added when
 * totalling the shared fascia width; default 0) so it can be tuned like the
 * other roller allowances — no hardcoded number.
 *
 * Additive + idempotent. Web-runnable: /migrate_fascia_group.php (super-admin).
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

// 1. quote_items.fascia_group — additive, nullable. Guard on column existence.
$col = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE()
        AND table_name = 'quote_items'
        AND column_name = 'fascia_group'"
)->fetchColumn();
if ((int) $col === 0) {
    $pdo->exec("ALTER TABLE quote_items ADD COLUMN fascia_group VARCHAR(8) NULL AFTER room_name");
    echo "Added quote_items.fascia_group.\n";
} else {
    echo "quote_items.fascia_group already present — skipped.\n";
}

// 2. Editable join allowance (per gap, mm). Default 0 = blinds butt together.
$ins = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES ('roller_fascia_join', 'gap', 'Shared fascia · join per gap (mm)', 0, 0)
     ON DUPLICATE KEY UPDATE keys_display = VALUES(keys_display)"
);
$ins->execute();
echo "Ensured roller_fascia_join / gap allowance (default 0).\n";

echo "\nDone. Tag blinds with the same Fascia group letter to share one fascia.\n";
