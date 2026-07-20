<?php
declare(strict_types=1);

/**
 * Migration: remember which master choice a pushed choice came from.
 *
 * The catalogue push identifies a tenant's copy of a choice by its LABEL. That
 * works right up until the label changes, and then it quietly does the wrong
 * thing twice over:
 *
 *   Rename "Not Required" -> "No Scallop" on the master, push, and the tenant
 *   gets a NEW "No Scallop" while the old "Not Required" stays. The push never
 *   deletes — deliberately, so it can't destroy a tenant's own additions — so
 *   the tenant ends up with both and no way to tell which one is ours.
 *
 * A label is not an identity. Give the copy a real one:
 *
 *   product_extra_choices.source_choice_id  INT UNSIGNED NULL
 *
 * NULL means "the tenant made this themselves" — those are never touched by a
 * push. Set means "this is our copy of master choice N", which lets the push
 * rename it in place, and remove it when the master original is deleted.
 *
 * Products already carry source_product_id for exactly this reason; this is the
 * same idea one level down.
 *
 * No backfill: existing rows stay NULL and keep matching by label, which is
 * current behaviour. The next push stamps each row it matches, so identities
 * fill in on their own. A row already orphaned by a past rename stays NULL and
 * is left alone — those need clearing by hand once.
 *
 * Run via web: /migrate_choice_source_id.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$colExists('product_extra_choices', 'source_choice_id')) {
    $pdo->exec(
        "ALTER TABLE product_extra_choices
            ADD COLUMN source_choice_id INT UNSIGNED NULL,
            ADD KEY idx_source_choice (source_choice_id)"
    );
    echo "  Added product_extra_choices.source_choice_id.\n";
} else {
    echo "  product_extra_choices.source_choice_id already exists — skipped.\n";
}

echo "\nDone. NULL = the tenant's own choice, never touched by a push.\n";
echo "The next push stamps the rows it matches, so identities fill in as you go.\n";
