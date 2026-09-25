<?php
declare(strict_types=1);

/**
 * Seed: "Split" tilt bar option for Bev PF Shutter.
 *
 * Adds a third Tilt Bar choice — "Split" (flat +£2.00) — and a child option
 * "Split Tilt Position" shown only when Split is picked: Centre (default) or
 * Offset, where Offset reveals a manual "from bottom (mm)" box. Purely data
 * (choices + a parent-gated sub-option + a per-choice number input); nothing
 * hardcoded. Additive + idempotent (find-or-create).
 *
 * Web-runnable: /seed_pf_shutter_split_tilt.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev PF Shutter' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Could not find product 'Bev PF Shutter' for client {$MASTER}.\n"); }

$hasLen = false; try { $pdo->query('SELECT length_input_label FROM product_extra_choices LIMIT 1'); $hasLen = true; } catch (Throwable $e) {}
$hasExtraLen = false; try { $pdo->query('SELECT length_input_label FROM product_extras LIMIT 1'); $hasExtraLen = true; } catch (Throwable $e) {}

// Find the Tilt Bar option group.
$tb = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Tilt Bar' LIMIT 1");
$tb->execute([$productId, $MASTER]);
$tiltId = (int) $tb->fetchColumn();
if ($tiltId === 0) { exit("Missing 'Tilt Bar' option on product {$productId}.\n"); }

$pdo->beginTransaction();
try {
    // Find-or-create a choice on a given extra; sets price_delta, default, sort,
    // and (when the column exists) an optional per-choice number-input label.
    $upsertChoice = function (int $extraId, string $label, float $delta, bool $default, int $sort, ?string $lenLabel = null)
                     use ($pdo, $hasLen): int {
        $q = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = ? LIMIT 1");
        $q->execute([$extraId, $label]);
        $id = (int) $q->fetchColumn();
        if ($id > 0) {
            $sql = "UPDATE product_extra_choices SET price_delta = ?, is_default = ?, sort_order = ?, active = 1"
                 . ($hasLen ? ", length_input_label = ?" : "") . " WHERE id = ?";
            $args = $hasLen ? [$delta, $default ? 1 : 0, $sort, $lenLabel, $id]
                            : [$delta, $default ? 1 : 0, $sort, $id];
            $pdo->prepare($sql)->execute($args);
            return $id;
        }
        if ($hasLen) {
            $pdo->prepare("INSERT INTO product_extra_choices (product_extra_id, system_id, label, image_path, price_delta, price_percent, price_per_metre, is_default, sort_order, active, length_input_label) VALUES (?,NULL,?,NULL,?,0,0,?,?,1,?)")
                ->execute([$extraId, $label, $delta, $default ? 1 : 0, $sort, $lenLabel]);
        } else {
            $pdo->prepare("INSERT INTO product_extra_choices (product_extra_id, system_id, label, image_path, price_delta, price_percent, price_per_metre, is_default, sort_order, active) VALUES (?,NULL,?,NULL,?,0,0,?,?,1)")
                ->execute([$extraId, $label, $delta, $default ? 1 : 0, $sort]);
        }
        return (int) $pdo->lastInsertId();
    };

    // 1. Tilt Bar → "Split" (flat +£2.00). Existing Left/Right untouched.
    $splitId = $upsertChoice($tiltId, 'Split', 2.00, false, 2);
    echo "Tilt Bar 'Split' choice (#{$splitId}) = +£2.00.\n";

    // 2. Child option "Split Tilt Position", gated on Split.
    $sp = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND name = 'Split Tilt Position' LIMIT 1");
    $sp->execute([$productId, $MASTER]);
    $posId = (int) $sp->fetchColumn();
    if ($posId === 0) {
        $pdo->prepare("INSERT INTO product_extras (client_id, product_id, parent_choice_id, name, is_required, sort_order, active) VALUES (?,?,?,?,1,?,1)")
            ->execute([$MASTER, $productId, $splitId, 'Split Tilt Position', 3]);
        $posId = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE product_extras SET parent_choice_id = ?, is_required = 1, sort_order = 3, active = 1 WHERE id = ?")
            ->execute([$splitId, $posId]);
    }
    // Gate via the junction table (source of truth for parent gating).
    $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?")->execute([$posId]);
    $pdo->prepare("INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)")->execute([$posId, $splitId]);

    // 3. Its choices: Centre (default), Offset (+ manual "from bottom (mm)" box).
    $upsertChoice($posId, 'Centre', 0.0, true,  0);
    $upsertChoice($posId, 'Offset', 0.0, false, 1, 'From bottom (mm)');
    echo "Split Tilt Position (#{$posId}): Centre (default) / Offset (+ mm box), gated on Split.\n";

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit("\nFAILED: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n(no changes saved)\n");
}

echo "\nDone — Split tilt bar (+£2.00) with Centre/Offset on product {$productId}.\n";
