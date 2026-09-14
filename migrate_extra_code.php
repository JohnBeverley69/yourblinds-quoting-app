<?php
declare(strict_types=1);

/**
 * Migration: stable machine keys on option groups + choices.
 *
 * Adds product_extras.code and product_extra_choices.code — short, stable
 * identifiers the front-end / engine can key off WITHOUT depending on the
 * human caption, so the display name (label) stays freely renameable by the
 * tenant. Used by the roller "Fascia Sizing" multi-blind flow (Standard /
 * Over size / Multi blind) so its captions can be edited without breaking the
 * form logic. Nullable + additive — every existing option keeps code = NULL
 * and behaves exactly as before.
 *
 * Additive + idempotent. Web-runnable: /migrate_extra_code.php (super-admin).
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

$addCol = static function (string $table, string $col, string $ddl) use ($pdo): void {
    $n = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
    );
    $n->execute([$table, $col]);
    if ((int) $n->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
        echo "Added {$table}.{$col}.\n";
    } else {
        echo "{$table}.{$col} already present — skipped.\n";
    }
};

$addCol('product_extras',        'code', 'code VARCHAR(40) NULL AFTER name');
$addCol('product_extra_choices', 'code', 'code VARCHAR(40) NULL AFTER label');

echo "\nDone. Stable machine keys available; captions stay editable.\n";
