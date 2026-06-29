<?php
declare(strict_types=1);

/**
 * Arena Vertical — headrail refinement (raised by John):
 *
 *   1. HEADRAIL COLOUR must depend on HEADRAIL TYPE. The first options seed
 *      created one flat "Headrail Colour" (8 colours). Replace it with one
 *      gated sub-option per Headrail Type, each showing only that type's
 *      colours:
 *        Senses Slim    → Anthracite, Black, Cream, Silver, White
 *        Slimline Vogue → Anthracite, Black, Brown, Brushed Silver, Champagne, White
 *        Standard       → (set $HEADRAIL_COLOURS['Standard'] below)
 *
 *   2. SENSES / VOGUE HEADRAIL SURCHARGE. Arena adds a WIDTH-BASED surcharge
 *      when the headrail is Senses or Vogue (Standard = none). Same table for
 *      89mm/127mm/Elements and every band: £5.06 @ 50cm … £12.46 @ 400cm.
 *      Modelled as a per-choice width table (extra_choice_price_rows) on the
 *      "Senses Slim" and "Slimline Vogue" Headrail Type choices — the pricing
 *      engine rounds width up to the smallest matching row and adds it.
 *      NB Arena applies NO discount to this surcharge (it's net) — review the
 *      markup treatment in your pricing settings if that matters.
 *
 * SAFE BY DEFAULT — DRY RUN unless ?apply=1. Idempotent: the flat colour
 * option is removed, per-type ones are created only if missing, and the
 * surcharge rows are replaced (delete + reinsert) each run.
 *
 *   Preview : /seed_arena_vertical_headrail_fix.php
 *   Apply   : /seed_arena_vertical_headrail_fix.php?apply=1
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
if ($productId <= 0) {
    $f = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Arena Vertical' LIMIT 1");
    $f->execute([$clientId]);
    $productId = (int) ($f->fetchColumn() ?: 0);
}
$pStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND client_id = ? LIMIT 1');
$pStmt->execute([$productId, $clientId]);
$product = $pStmt->fetch();
if (!$product) { echo "Arena Vertical product not found (pass ?product_id=N).\n"; exit; }

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
// Colours per Headrail Type (first = default). Standard TBC — fill before apply.
$HEADRAIL_COLOURS = [
    'Standard'       => ['Anthracite', 'Black', 'Brushed Silver', 'White'],
    'Senses Slim'    => ['Anthracite', 'Black', 'Cream', 'Silver', 'White'],
    'Slimline Vogue' => ['Anthracite', 'Black', 'Brown', 'Brushed Silver', 'Champagne', 'White'],
];

// Senses/Vogue headrail surcharge — width_mm => £ (Arena trade, no discount).
$SURCHARGE = [
    500 => 5.06, 700 => 5.28, 900 => 5.62, 1100 => 6.08, 1300 => 6.35,
    1500 => 6.85, 1700 => 7.35, 1900 => 7.68, 2100 => 7.92, 2300 => 8.44,
    2500 => 8.92, 2700 => 9.13, 2900 => 9.63, 3100 => 10.11, 3300 => 10.80,
    3500 => 11.27, 3700 => 11.57, 3900 => 11.81, 4000 => 12.46,
];
$SURCHARGE_TYPES = ['Senses Slim', 'Slimline Vogue'];

echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — Arena Vertical headrail fix on:\n";
echo "  #{$productId}  \"{$product['name']}\"\n";
echo str_repeat('=', 64) . "\n\n";

// Resolve the Headrail Type extra and its choice ids (by label).
$htExtra = $pdo->prepare(
    "SELECT id FROM product_extras
      WHERE product_id = ? AND client_id = ? AND LOWER(name) = 'headrail type'
        AND parent_choice_id IS NULL LIMIT 1"
);
$htExtra->execute([$productId, $clientId]);
$htExtraId = (int) ($htExtra->fetchColumn() ?: 0);
if ($htExtraId === 0) { echo "Could not find the top-level 'Headrail Type' option.\n"; exit; }

$htChoices = $pdo->prepare('SELECT id, label FROM product_extra_choices WHERE product_extra_id = ?');
$htChoices->execute([$htExtraId]);
$choiceIdByLabel = [];
foreach ($htChoices->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $choiceIdByLabel[(string) $r['label']] = (int) $r['id'];
}

$missing = [];
foreach (array_keys($HEADRAIL_COLOURS) as $t) { if (!isset($choiceIdByLabel[$t])) $missing[] = $t; }
if ($missing) { echo "Headrail Type choices not found: " . implode(', ', $missing) . "\n"; exit; }

if ($apply) $pdo->beginTransaction();
try {
    // --- 1. Remove the flat (ungated) "Headrail Colour" option --------------
    $findFlat = $pdo->prepare(
        "SELECT id FROM product_extras
          WHERE product_id = ? AND client_id = ? AND LOWER(name) = 'headrail colour'
            AND parent_choice_id IS NULL"
    );
    $findFlat->execute([$productId, $clientId]);
    $flatIds = array_map('intval', $findFlat->fetchAll(PDO::FETCH_COLUMN));
    if ($flatIds) {
        echo "  REMOVE flat 'Headrail Colour' (id " . implode(',', $flatIds) . ") — choices cascade.\n";
        if ($apply) {
            $ph = implode(',', array_fill(0, count($flatIds), '?'));
            $pdo->prepare("DELETE FROM product_extras WHERE id IN ($ph)")->execute($flatIds);
        }
    } else {
        echo "  (no flat 'Headrail Colour' to remove)\n";
    }

    // --- 2. Per-type gated "Headrail Colour" sub-options --------------------
    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM product_extras WHERE product_id = ? AND client_id = ?');
    $sortStmt->execute([$productId, $clientId]);
    $nextSort = (int) $sortStmt->fetchColumn();

    $insSub = $pdo->prepare(
        'INSERT INTO product_extras (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
         VALUES (?, ?, ?, ?, 0, ?, 1)'
    );
    $insJunc = $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)');
    $insChoice = $pdo->prepare(
        'INSERT INTO product_extra_choices
           (product_extra_id, system_id, label, image_path, price_delta, price_percent, price_per_metre, is_default, sort_order, active)
         VALUES (?, NULL, ?, NULL, 0, 0, 0, ?, ?, 1)'
    );
    // Already-gated "Headrail Colour" sub-options (idempotency).
    $haveSub = $pdo->prepare(
        "SELECT pc.product_extra_choice_id
           FROM product_extras e
           JOIN product_extra_parent_choices pc ON pc.product_extra_id = e.id
          WHERE e.product_id = ? AND e.client_id = ? AND LOWER(e.name) = 'headrail colour'"
    );
    $haveSub->execute([$productId, $clientId]);
    $gatedAlready = array_map('intval', $haveSub->fetchAll(PDO::FETCH_COLUMN));

    foreach ($HEADRAIL_COLOURS as $type => $colours) {
        $parentChoiceId = $choiceIdByLabel[$type];
        if (!$colours) { echo "  SKIP  Headrail Colour ($type) — no colour list set yet.\n"; continue; }
        if (in_array($parentChoiceId, $gatedAlready, true)) { echo "  SKIP  Headrail Colour ($type) — already gated.\n"; continue; }

        echo "  ADD   Headrail Colour ($type) ⟶ only when Headrail Type = $type: " . implode(' · ', $colours) . "\n";
        if ($apply) {
            $insSub->execute([$clientId, $productId, $parentChoiceId, 'Headrail Colour', $nextSort++]);
            $subId = (int) $pdo->lastInsertId();
            $insJunc->execute([$subId, $parentChoiceId]);
            $cs = 0;
            foreach ($colours as $i => $col) { $insChoice->execute([$subId, $col, $i === 0 ? 1 : 0, $cs++]); }
        }
    }

    // --- 3. Senses/Vogue width-table surcharge ------------------------------
    $delRows = $pdo->prepare('DELETE FROM extra_choice_price_rows WHERE product_extra_choice_id = ?');
    $insRow  = $pdo->prepare('INSERT INTO extra_choice_price_rows (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?)');
    foreach ($SURCHARGE_TYPES as $type) {
        $cid = $choiceIdByLabel[$type];
        echo "  PRICE Headrail Type = $type → width surcharge £" . number_format(reset($SURCHARGE), 2)
           . "–£" . number_format(end($SURCHARGE), 2) . " over " . count($SURCHARGE) . " widths.\n";
        if ($apply) {
            $delRows->execute([$cid]);
            foreach ($SURCHARGE as $w => $p) { $insRow->execute([$cid, $w, $p]); }
        }
    }

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    echo "\nFAILED: " . $e->getMessage() . "\n(no changes saved)\n";
    exit(1);
}

echo "\n" . str_repeat('-', 64) . "\n";
echo ($apply ? "Applied." : "Preview only — add &apply=1 to make these changes.") . "\n";
if (!$HEADRAIL_COLOURS['Standard']) {
    echo "NOTE: Standard headrail colours not set yet — its sub-option was skipped.\n";
}
