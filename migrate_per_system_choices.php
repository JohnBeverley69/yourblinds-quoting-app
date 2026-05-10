<?php
declare(strict_types=1);

/**
 * Migration: collapse choice scope to per-row system_id.
 *
 * The model is moving from "one choice → multiple systems via junction →
 * single price" to "one row per (choice × system) with its own price".
 *
 * That matches the trade tooling our users are already familiar with
 * (each colour appears as one row per system in the legacy grid) and
 * crucially lets pricing genuinely vary per system — e.g. Black headrail
 * is £3/m on Vogue but £0 on Nova; the old single-price-per-choice model
 * couldn't express that without contortions.
 *
 * Per-choice migration logic:
 *   - 0 junction rows               → leave system_id NULL (= every system)
 *   - junction == every system on the product
 *                                   → set system_id NULL (collapse to "all")
 *   - 1 junction row                → set system_id directly
 *   - 2+ junction rows (subset)     → keep this row pointing at the FIRST
 *                                     junction system, then INSERT N-1
 *                                     clones (label, prices, image_path,
 *                                     sort_order, is_default, active) one
 *                                     per remaining system. Each clone
 *                                     also gets its own copy of any
 *                                     extra_choice_price_rows (width
 *                                     tables) so editing one clone's
 *                                     price doesn't affect siblings.
 *
 * Option-level scope (product_extra_systems) is intentionally NOT
 * migrated forward — the new model has no option-level system concept.
 * An option appears whenever any of its choices is available for the
 * chosen system. Any option that was option-scoped to a subset of
 * systems will become available on every system after this runs; the
 * per-choice scopes still control which choices appear inside it.
 *
 * For idempotency the migration empties the junction rows it has
 * processed. Re-runs see an empty junction and become no-ops. The
 * junction tables themselves are LEFT IN PLACE so old code (if any
 * is still running between this migration and the code-switch upload)
 * won't crash; it'll just see empty scope = "available everywhere"
 * and over-show, which is a permissive failure rather than a hard one.
 *
 * Run via web: /migrate_per_system_choices.php   (super-admin login)
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

$ops      = [];
$updated  = 0;
$cloned   = 0;
$widthCp  = 0;

// Pull every choice that still has junction rows to process. The clones
// we create won't have junction rows themselves, so this naturally only
// picks up the originals on a fresh run.
$choices = $pdo->query(
    'SELECT c.id, c.product_extra_id, c.label, c.image_path,
            c.price_delta, c.price_percent, c.price_per_metre,
            c.is_default, c.sort_order, c.active,
            e.product_id
       FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
      WHERE EXISTS (SELECT 1 FROM product_extra_choice_systems
                     WHERE product_extra_choice_id = c.id)
      ORDER BY c.id'
)->fetchAll(PDO::FETCH_ASSOC);

$junctionSt = $pdo->prepare(
    'SELECT product_system_id FROM product_extra_choice_systems
      WHERE product_extra_choice_id = ?
      ORDER BY product_system_id'
);

$totalSystemsSt = $pdo->prepare(
    'SELECT COUNT(*) FROM product_systems WHERE product_id = ?'
);

$updateSystemSt = $pdo->prepare(
    'UPDATE product_extra_choices SET system_id = ? WHERE id = ?'
);

$cloneChoiceSt = $pdo->prepare(
    'INSERT INTO product_extra_choices
       (product_extra_id, system_id, label, image_path,
        price_delta, price_percent, price_per_metre,
        is_default, sort_order, active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$widthRowsSt = $pdo->prepare(
    'SELECT width_mm, price FROM extra_choice_price_rows
      WHERE product_extra_choice_id = ?'
);

$cloneWidthRowSt = $pdo->prepare(
    'INSERT INTO extra_choice_price_rows
       (product_extra_choice_id, width_mm, price)
     VALUES (?, ?, ?)'
);

$clearJunctionSt = $pdo->prepare(
    'DELETE FROM product_extra_choice_systems WHERE product_extra_choice_id = ?'
);

foreach ($choices as $c) {
    $junctionSt->execute([(int) $c['id']]);
    $systemIds = array_map('intval', $junctionSt->fetchAll(PDO::FETCH_COLUMN));

    $totalSystemsSt->execute([(int) $c['product_id']]);
    $totalSystems = (int) $totalSystemsSt->fetchColumn();

    if ($totalSystems > 0 && count($systemIds) === $totalSystems) {
        // Junction covers every system → "all systems" → NULL.
        $updateSystemSt->execute([null, (int) $c['id']]);
        $updated++;
    } elseif (count($systemIds) === 1) {
        // Single system → set directly.
        $updateSystemSt->execute([$systemIds[0], (int) $c['id']]);
        $updated++;
    } else {
        // Subset (2+ but not all) → original keeps the first system,
        // we clone one new row per remaining system.
        $first = array_shift($systemIds);
        $updateSystemSt->execute([$first, (int) $c['id']]);
        $updated++;

        // Pull this choice's width table once so each clone gets its
        // own copy.
        $widthRowsSt->execute([(int) $c['id']]);
        $widthRows = $widthRowsSt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($systemIds as $sid) {
            $cloneChoiceSt->execute([
                (int) $c['product_extra_id'],
                $sid,
                (string) $c['label'],
                $c['image_path'],
                $c['price_delta'],
                $c['price_percent'],
                $c['price_per_metre'],
                (int) $c['is_default'],
                (int) $c['sort_order'],
                (int) $c['active'],
            ]);
            $cloneId = (int) $pdo->lastInsertId();
            $cloned++;

            foreach ($widthRows as $w) {
                $cloneWidthRowSt->execute([$cloneId, (int) $w['width_mm'], $w['price']]);
                $widthCp++;
            }
        }
    }

    // Empty the junction for this choice so re-runs are no-ops and old
    // code (briefly running between migration + code upload) sees empty
    // scope rather than stale subset data.
    $clearJunctionSt->execute([(int) $c['id']]);
}

$ops[] = "Updated $updated existing choice(s) — set system_id from junction.";
$ops[] = "Cloned $cloned new choice row(s) — one per extra junction system.";
$ops[] = "Copied $widthCp width-table row(s) onto cloned choices.";

// Option-level scope is gone entirely — empty the table.
$optionScopeCleared = $pdo->exec('DELETE FROM product_extra_systems');
$ops[] = "Cleared $optionScopeCleared row(s) from product_extra_systems "
       . "(option-level scope removed in the new model).";

// Sanity: how many choices already meant "available everywhere" and
// needed no work?
$noopCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM product_extra_choices c
      WHERE NOT EXISTS (SELECT 1 FROM product_extra_choice_systems
                         WHERE product_extra_choice_id = c.id)
        AND c.system_id IS NULL'
)->fetchColumn();
$ops[] = "Left $noopCount choice(s) untouched — already meant 'available everywhere'.";

echo "Migration complete:\n";
foreach ($ops as $op) {
    echo '  - ' . $op . "\n";
}
echo "\nThe junction tables (product_extra_choice_systems, product_extra_systems)\n";
echo "are intentionally left in place so any pre-upload PHP can still query\n";
echo "them safely (they're just empty now). Drop them later if you want — no\n";
echo "rush.\n";
echo "\nNext step: upload the new PHP files (admin/products/*, quote-builder/*,\n";
echo "_partials/pricing_engine.php, _partials/seed_client_from_template.php).\n";
echo "After upload, the choice scope is read directly from\n";
echo "product_extra_choices.system_id — no junction lookups, no option-level\n";
echo "scope to confuse anyone.\n";
echo "\nWhen you're done, you can delete this file from the server.\n";
