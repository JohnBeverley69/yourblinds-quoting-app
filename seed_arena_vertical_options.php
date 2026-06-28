<?php
declare(strict_types=1);

/**
 * Seed the Arena VERTICAL guided-journey options onto the "Arena Vertical"
 * product (Phase 2). Harvested from Arena's live configurator (VET fieldRows).
 *
 * Fidelity = "smart-gate the clean cases" (agreed with John):
 *   - All ~14 spec options added as extras (dropdowns / radios).
 *   - The clean single-parent gates are modelled as sub-options:
 *       Wand Colour + Wand Length  → only when Control Type = Wand
 *       Stabilising Chains Colour  → only when Stabilising Chain = Yes
 *     (gating uses product_extras.parent_choice_id + the
 *      product_extra_parent_choices junction, the same as the UI's
 *      "+ Sub-option" feature.)
 *   - The multi-condition rules (A AND B AND C) are left always-visible —
 *     the app gates on a single parent dimension, and these are no-cost
 *     spec choices anyway.
 *
 * All choices start NON-PRICED (£0 / 0%). Arena's vertical price is band ×
 * size (already seeded); if any option carries a surcharge, set it later in
 * the Options UI. Choices apply to ALL systems (system_id NULL).
 *
 * SAFE BY DEFAULT — DRY RUN unless ?apply=1. Idempotent: an option whose
 * name already exists on the product is skipped (so re-running won't
 * duplicate or overwrite your edits).
 *
 *   Preview : /seed_arena_vertical_options.php
 *   Apply   : /seed_arena_vertical_options.php?apply=1
 *   (defaults to the "Arena Vertical" product; or pass ?product_id=N)
 *
 * Super-admin only.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

ini_set('display_errors', '1');
error_reporting(E_ALL);

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$apply     = (($_GET['apply'] ?? '') === '1');
$productId = (int) ($_GET['product_id'] ?? 0);

// Default to the "Arena Vertical" product in this tenant.
if ($productId <= 0) {
    $f = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Arena Vertical' LIMIT 1");
    $f->execute([$clientId]);
    $productId = (int) ($f->fetchColumn() ?: 0);
}
$pStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ? LIMIT 1');
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();
if (!$product) {
    echo "Arena Vertical product not found for your account (pass ?product_id=N).\n";
    exit;
}

// ---------------------------------------------------------------------------
// The Vertical option set. Each: name, required?, optional gate, and choices
// ([label, isDefault]). Gated options are created as sub-options of the named
// parent choice. Order top-level options BEFORE the sub-options that depend
// on them (the script also re-resolves parents from the DB, so re-runs work).
// ---------------------------------------------------------------------------
$OPTIONS = [
    ['name' => 'Headrail Type', 'required' => true, 'choices' => [
        ['Standard', true], ['Senses Slim', false], ['Slimline Vogue', false],
    ]],
    ['name' => 'Headrail Colour', 'required' => false, 'choices' => [
        ['White', true], ['Anthracite', false], ['Black', false], ['Brushed Silver', false],
        ['Brown', false], ['Champagne', false], ['Cream', false], ['Silver', false],
    ]],
    ['name' => 'Control Type', 'required' => true, 'choices' => [
        ['Cord', true], ['Wand', false],
    ]],
    ['name' => 'Control Side', 'required' => true, 'choices' => [
        ['Left', true], ['Right', false], ['Both', false],
    ]],
    ['name' => 'Bunch / Stack Side', 'required' => false, 'choices' => [
        ['Left', true], ['Right', false], ['Split', false],
    ]],
    ['name' => 'Brackets (Fixing)', 'required' => false, 'choices' => [
        ['3.5" Face Fix', false], ['3.5" Shallow Fix', false], ['5" Face Fix', false],
        ['5" Shallow Fix', false], ['Both Top and Face Fix', false], ['Face', false], ['Top', false],
        ['Universal Extension Brackets', false], ['80mm Extension Top Fix', false],
        ['130mm Extension Top Fix', false], ['160mm Extension Top Fix', false],
        ['135mm-185mm Adjustable Face Fix', false], ['160mm-210mm Adjustable Face Fix', false],
        ['Shallow Face Fix', false], ['Shallow Face Fix Extension', false],
    ]],
    ['name' => 'Chain Colour', 'required' => false, 'choices' => [
        ['Chrome Metal', true], ['White Plastic', false], ['Grey Plastic', false],
        ['Black Plastic', false], ['Anthracite Plastic', false], ['Beige Plastic', false],
        ['Antique Metal', false], ['Black Metal', false], ['Gold Metal', false],
    ]],
    ['name' => 'End Cap Colour', 'required' => false, 'choices' => [
        ['Chrome', true], ['White', false], ['Black Chrome', false], ['Anthracite', false], ['Cream', false],
    ]],
    ['name' => 'Extra Coverage', 'required' => false, 'choices' => [
        ['No', true], ['Yes', false],
    ]],
    ['name' => 'Stabilising Weight Type', 'required' => false, 'choices' => [
        ['Chained Weights', true], ['Chainless Weights', false],
    ]],
    ['name' => 'Stabilising Chain', 'required' => false, 'choices' => [
        ['No', true], ['Yes', false],
    ]],

    // --- Sub-options (gated) ----------------------------------------------
    ['name' => 'Wand Length', 'required' => false,
     'gate' => ['parent' => 'Control Type', 'choice' => 'Wand'], 'choices' => [
        ['50cm', false], ['100cm', true], ['150cm', false], ['200cm', false],
    ]],
    ['name' => 'Wand Colour', 'required' => false,
     'gate' => ['parent' => 'Control Type', 'choice' => 'Wand'], 'choices' => [
        ['Anthracite', true], ['Black', false], ['Brushed Silver', false], ['White', false],
        ['Brown', false], ['Grey', false], ['Cream', false],
    ]],
    ['name' => 'Stabilising Chains Colour', 'required' => false,
     'gate' => ['parent' => 'Stabilising Chain', 'choice' => 'Yes'], 'choices' => [
        ['Black', true], ['White', false], ['Beige', false],
    ]],
];

echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — seed Arena Vertical options onto:\n";
echo "  #{$productId}  \"{$product['name']}\"\n";
echo str_repeat('=', 64) . "\n\n";

// Existing option names on this product (skip = idempotent).
$exStmt = $pdo->prepare('SELECT name FROM product_extras WHERE product_id = ? AND client_id = ?');
$exStmt->execute([$productId, $clientId]);
$existing = [];
foreach ($exStmt->fetchAll(PDO::FETCH_COLUMN) as $n) {
    $existing[mb_strtolower(trim((string) $n))] = true;
}

$sortStmt = $pdo->prepare(
    'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_extras WHERE product_id = ? AND client_id = ?'
);
$sortStmt->execute([$productId, $clientId]);
$nextSort = (int) $sortStmt->fetchColumn();

/** Resolve a parent choice id by (option name, choice label) from the DB. */
$resolveChoiceId = function (string $optName, string $choiceLabel) use ($pdo, $productId, $clientId): ?int {
    $st = $pdo->prepare(
        'SELECT c.id
           FROM product_extra_choices c
           JOIN product_extras e ON e.id = c.product_extra_id
          WHERE e.product_id = ? AND e.client_id = ?
            AND LOWER(e.name) = LOWER(?) AND LOWER(c.label) = LOWER(?)
          LIMIT 1'
    );
    $st->execute([$productId, $clientId, $optName, $choiceLabel]);
    $id = (int) ($st->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
};

$createdOpts = 0; $createdChoices = 0; $skipped = 0; $gatedOk = 0;

if ($apply) $pdo->beginTransaction();
try {
    $insExtraTop = $pdo->prepare(
        'INSERT INTO product_extras
           (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
         VALUES (?, ?, NULL, ?, ?, ?, 1)'
    );
    $insExtraSub = $pdo->prepare(
        'INSERT INTO product_extras
           (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    $insJunction = $pdo->prepare(
        'INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)'
    );
    $insChoice = $pdo->prepare(
        'INSERT INTO product_extra_choices
           (product_extra_id, system_id, label, image_path,
            price_delta, price_percent, price_per_metre, is_default, sort_order, active)
         VALUES (?, NULL, ?, NULL, 0, 0, 0, ?, ?, 1)'
    );

    // Two passes so any gated option resolves a parent created in pass 1.
    foreach ([false, true] as $gatedPass) {
        foreach ($OPTIONS as $opt) {
            $isGated = isset($opt['gate']);
            if ($isGated !== $gatedPass) continue;

            $key = mb_strtolower(trim($opt['name']));
            if (isset($existing[$key])) {
                echo "  SKIP  {$opt['name']} (already exists)\n";
                $skipped++;
                continue;
            }

            $labels  = array_map(static fn ($c) => $c[0] . ($c[1] ? '*' : ''), $opt['choices']);
            $reqTag  = $opt['required'] ? 'required' : 'optional';
            $gateTag = $isGated ? "  ⟶ only when {$opt['gate']['parent']} = {$opt['gate']['choice']}" : '';
            echo "  ADD   {$opt['name']} ($reqTag): " . implode(' · ', $labels) . "{$gateTag}\n";

            $parentChoiceId = null;
            if ($isGated) {
                $parentChoiceId = $resolveChoiceId($opt['gate']['parent'], $opt['gate']['choice']);
                if ($parentChoiceId === null && $apply) {
                    throw new RuntimeException(
                        "Cannot gate \"{$opt['name']}\" — parent choice "
                        . "\"{$opt['gate']['parent']} = {$opt['gate']['choice']}\" not found."
                    );
                }
            }

            if ($apply) {
                if ($isGated) {
                    $insExtraSub->execute([$clientId, $productId, $parentChoiceId, $opt['name'], $opt['required'] ? 1 : 0, $nextSort++]);
                    $extraId = (int) $pdo->lastInsertId();
                    $insJunction->execute([$extraId, $parentChoiceId]);   // multi-parent table the quote builder reads
                    $gatedOk++;
                } else {
                    $insExtraTop->execute([$clientId, $productId, $opt['name'], $opt['required'] ? 1 : 0, $nextSort++]);
                    $extraId = (int) $pdo->lastInsertId();
                }
                $cSort = 0;
                foreach ($opt['choices'] as $c) {
                    $insChoice->execute([$extraId, $c[0], $c[1] ? 1 : 0, $cSort++]);
                    $createdChoices++;
                }
            } else {
                $createdChoices += count($opt['choices']);
                if ($isGated) $gatedOk++;
            }
            $createdOpts++;
            $existing[$key] = true;   // so a duplicate name later in the list is caught
        }
    }

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED: " . $e->getMessage() . "\n(no changes saved)\n";
    exit(1);
}

echo "\n" . str_repeat('-', 64) . "\n";
echo ($apply ? "Created" : "Would create") . ": $createdOpts option(s), $createdChoices choice(s), "
   . "$gatedOk gated sub-option(s). $skipped already existed.\n";

if (!$apply) {
    echo "\nThis was a PREVIEW — nothing changed. Add &apply=1 to create them.\n";
} else {
    echo "\nDone. All choices were created NON-PRICED (£0 / 0%).\n";
    echo "Verify in the Options UI whether any Arena option carries a surcharge\n";
    echo "(likely candidates: Extra Coverage = Yes, Stabilising Chain = Yes).\n";
}
