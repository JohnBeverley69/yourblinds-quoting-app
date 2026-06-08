<?php
declare(strict_types=1);

/**
 * Seed the standard SHUTTER option set onto a product.
 *
 * Plantation shutters have a big, fairly standard set of configuration
 * options (style, louvre size, frame, tilt, etc.). Hand-entering them all
 * is tedious, so this creates the lot in one go — every choice with a £0 /
 * 0% surcharge, i.e. NON-PRICED. Once they exist you tweak them in the
 * Options UI: e.g. set louvre "89mm" to +10%, "Hidden tilt" to +£X, etc.
 *
 * Surcharges supported per choice (set later): flat £ (price_delta),
 * percent of base (price_percent), per-metre (price_per_metre). Choices
 * here apply to ALL systems (system_id NULL).
 *
 * SAFE BY DEFAULT — runs as a DRY RUN and only reports what it WOULD
 * create. Add ?apply=1 to actually create. Idempotent: an option whose
 * name already exists on the product is skipped (so re-running is safe and
 * won't duplicate or overwrite your edits).
 *
 *   Preview : /seed_shutter_options.php?product_id=123
 *   Apply   : /seed_shutter_options.php?product_id=123&apply=1
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

$productId = (int) ($_GET['product_id'] ?? 0);
$apply     = (($_GET['apply'] ?? '') === '1');

if ($productId <= 0) {
    echo "Pass ?product_id=<id> (find it in the product edit page URL).\n";
    exit;
}

// Verify the product belongs to this tenant.
$pStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ? LIMIT 1');
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();
if (!$product) {
    echo "Product #$productId not found for your account.\n";
    exit;
}

// ---------------------------------------------------------------------------
// The standard shutter option set. Each option: name, required?, and its
// choices (the first marked * is the default). All surcharges start at 0 —
// edit them later in the Options UI. Trim / rename freely afterwards.
// ---------------------------------------------------------------------------
$OPTIONS = [
    ['name' => 'Style', 'required' => true, 'choices' => [
        ['Full Height', true], ['Tier-on-Tier', false], ['Café Style', false], ['Solid Panel', false],
    ]],
    ['name' => 'Louvre Size', 'required' => true, 'choices' => [
        ['47mm', false], ['63mm', false], ['76mm', true], ['89mm', false],
        // ↑ likely a % uplift for wider louvres — set price_percent later.
    ]],
    ['name' => 'Colour / Finish', 'required' => true, 'choices' => [
        ['White', true], ['Custom Painted (RAL)', false], ['Wood Stain', false],
    ]],
    ['name' => 'Frame Style', 'required' => true, 'choices' => [
        ['L-Frame', true], ['Z-Frame', false], ['Bullnose', false], ['Face-Fix', false], ['No Frame', false],
    ]],
    ['name' => 'Mounting', 'required' => true, 'choices' => [
        ['Inside Recess', true], ['Edge of Recess', false], ['Face Fix', false],
    ]],
    ['name' => 'Tilt Control', 'required' => true, 'choices' => [
        ['Standard Tilt Rod', true], ['Hidden Tilt Rod', false], ['No Tilt', false], ['Split Tilt', false],
    ]],
    ['name' => 'Mid-Rail', 'required' => false, 'choices' => [
        ['None', true], ['Add Mid-Rail', false],
    ]],
    ['name' => 'Hinge Colour', 'required' => false, 'choices' => [
        ['Match Panel', true], ['White', false], ['Silver', false], ['Brass', false], ['Antique', false],
    ]],
    ['name' => 'Special Shape', 'required' => false, 'choices' => [
        ['Square / Standard', true], ['Arch', false], ['Eyebrow', false],
        ['Circle / Oval', false], ['Slant / Rake Top', false], ['Triangle', false],
    ]],
];

echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — seed shutter options onto:\n";
echo "  #{$productId}  \"{$product['name']}\"\n";
echo str_repeat('=', 64) . "\n\n";

// Existing option names on this product (skip = idempotent).
$exStmt = $pdo->prepare(
    'SELECT name FROM product_extras WHERE product_id = ? AND client_id = ?'
);
$exStmt->execute([$productId, $clientId]);
$existing = [];
foreach ($exStmt->fetchAll(PDO::FETCH_COLUMN) as $n) {
    $existing[mb_strtolower(trim((string) $n))] = true;
}

// Next sort_order for new options (append after any existing).
$sortStmt = $pdo->prepare(
    'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_extras WHERE product_id = ? AND client_id = ?'
);
$sortStmt->execute([$productId, $clientId]);
$nextSort = (int) $sortStmt->fetchColumn();

$createdOpts = 0;
$createdChoices = 0;
$skipped = 0;

if ($apply) $pdo->beginTransaction();
try {
    $insExtra = $pdo->prepare(
        'INSERT INTO product_extras
           (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
         VALUES (?, ?, NULL, ?, ?, ?, 1)'
    );
    $insChoice = $pdo->prepare(
        'INSERT INTO product_extra_choices
           (product_extra_id, system_id, label, image_path,
            price_delta, price_percent, price_per_metre, is_default, sort_order, active)
         VALUES (?, NULL, ?, NULL, 0, 0, 0, ?, ?, 1)'
    );

    foreach ($OPTIONS as $opt) {
        $key = mb_strtolower(trim($opt['name']));
        if (isset($existing[$key])) {
            echo "  SKIP  {$opt['name']} (already exists)\n";
            $skipped++;
            continue;
        }

        $reqTag = $opt['required'] ? 'required' : 'optional';
        $labels = array_map(static fn ($c) => $c[0] . ($c[1] ? '*' : ''), $opt['choices']);
        echo "  ADD   {$opt['name']} ($reqTag): " . implode(' · ', $labels) . "\n";

        if ($apply) {
            $insExtra->execute([
                $clientId, $productId, $opt['name'], $opt['required'] ? 1 : 0, $nextSort++,
            ]);
            $extraId = (int) $pdo->lastInsertId();
            $cSort = 0;
            foreach ($opt['choices'] as $c) {
                $insChoice->execute([$extraId, $c[0], $c[1] ? 1 : 0, $cSort++]);
                $createdChoices++;
            }
        } else {
            $createdChoices += count($opt['choices']);
        }
        $createdOpts++;
    }

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED: " . $e->getMessage() . "\n(no changes saved)\n";
    exit(1);
}

echo "\n" . str_repeat('-', 64) . "\n";
echo ($apply ? "Created" : "Would create") . ": $createdOpts option(s), $createdChoices choice(s). "
   . "$skipped already existed.\n";

if (!$apply) {
    echo "\nThis was a PREVIEW — nothing changed.\n";
    echo "Re-run with &apply=1 on the URL to create them.\n";
} else {
    echo "\nDone. Open the product's Options page to set any surcharges\n";
    echo "(e.g. louvre 89mm → +10%, Hidden Tilt Rod → +£X) and prune anything\n";
    echo "you don't offer. All choices were created NON-PRICED (£0 / 0%).\n";
    echo "\nNOTE: quantity-based add-ons (cut-outs, T-posts priced per item)\n";
    echo "aren't included — they need a per-item surcharge type we haven't built yet.\n";
}
