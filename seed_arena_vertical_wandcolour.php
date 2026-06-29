<?php
declare(strict_types=1);

/**
 * Arena Vertical — Wand Colour per headrail type (multi-condition gate).
 *
 * Wand Colour depends on BOTH Control Type = Wand AND the Headrail Type, and
 * the colour set differs per headrail (verified from Arena's configurator):
 *   Standard       → Anthracite, Black, Brushed Silver, White
 *   Senses Slim    → Anthracite, Black, Brushed Silver, Cream, Grey, White
 *   Slimline Vogue → Black, Brown, Grey, White
 *
 * The single combined "Wand Colour" option is replaced with one per type,
 * each gated on [Control Type = Wand, Headrail Type = <type>] with
 * parent_match_all = 1 (AND). So each shows ONLY when the control is a wand
 * and that headrail is picked — needs migrate_parent_match_all.php first.
 *
 * SAFE BY DEFAULT — DRY RUN unless ?apply=1. Idempotent (removes any existing
 * "Wand Colour" options, recreates the three). Super-admin only.
 *
 *   Preview : /seed_arena_vertical_wandcolour.php
 *   Apply   : /seed_arena_vertical_wandcolour.php?apply=1
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

// Require the AND-gating column.
try { $pdo->query('SELECT parent_match_all FROM product_extras LIMIT 1'); }
catch (Throwable $e) { echo "Run /migrate_parent_match_all.php first (parent_match_all column missing).\n"; exit; }

$WAND = [
    'Standard'       => ['Anthracite', 'Black', 'Brushed Silver', 'White'],
    'Senses Slim'    => ['Anthracite', 'Black', 'Brushed Silver', 'Cream', 'Grey', 'White'],
    'Slimline Vogue' => ['Black', 'Brown', 'Grey', 'White'],
];

echo ($apply ? "APPLY" : "DRY RUN (preview only)") . " — Wand Colour per headrail type on:\n";
echo "  #{$productId}  \"{$product['name']}\"\n";
echo str_repeat('=', 64) . "\n\n";

/** Resolve a choice id by (option name, choice label). */
$resolve = function (string $optName, string $choiceLabel) use ($pdo, $productId, $clientId): ?int {
    $st = $pdo->prepare(
        'SELECT c.id FROM product_extra_choices c
           JOIN product_extras e ON e.id = c.product_extra_id
          WHERE e.product_id = ? AND e.client_id = ?
            AND LOWER(e.name) = LOWER(?) AND LOWER(c.label) = LOWER(?) LIMIT 1'
    );
    $st->execute([$productId, $clientId, $optName, $choiceLabel]);
    $id = (int) ($st->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
};

$wandChoiceId = $resolve('Control Type', 'Wand');
if ($wandChoiceId === null) { echo "Could not find Control Type = Wand.\n"; exit; }
$typeChoiceId = [];
foreach (array_keys($WAND) as $t) {
    $cid = $resolve('Headrail Type', $t);
    if ($cid === null) { echo "Could not find Headrail Type = {$t}.\n"; exit; }
    $typeChoiceId[$t] = $cid;
}

if ($apply) $pdo->beginTransaction();
try {
    // Remove any existing "Wand Colour" options (the old single one + reruns).
    $find = $pdo->prepare("SELECT id FROM product_extras WHERE product_id = ? AND client_id = ? AND LOWER(name) = 'wand colour'");
    $find->execute([$productId, $clientId]);
    $oldIds = array_map('intval', $find->fetchAll(PDO::FETCH_COLUMN));
    if ($oldIds) {
        echo "  REMOVE existing 'Wand Colour' option(s) id " . implode(',', $oldIds) . " — children cascade.\n";
        if ($apply) {
            $ph = implode(',', array_fill(0, count($oldIds), '?'));
            $pdo->prepare("DELETE FROM product_extras WHERE id IN ($ph)")->execute($oldIds);
        }
    }

    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM product_extras WHERE product_id = ? AND client_id = ?');
    $sortStmt->execute([$productId, $clientId]);
    $nextSort = (int) $sortStmt->fetchColumn();

    $insSub = $pdo->prepare(
        'INSERT INTO product_extras
           (client_id, product_id, parent_choice_id, name, is_required, sort_order, active, parent_match_all)
         VALUES (?, ?, ?, ?, 0, ?, 1, 1)'
    );
    $insJunc   = $pdo->prepare('INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)');
    $insChoice = $pdo->prepare(
        'INSERT INTO product_extra_choices
           (product_extra_id, system_id, label, image_path, price_delta, price_percent, price_per_metre, is_default, sort_order, active)
         VALUES (?, NULL, ?, NULL, 0, 0, 0, ?, ?, 1)'
    );

    foreach ($WAND as $type => $colours) {
        echo "  ADD   Wand Colour ⟶ Control = Wand AND Headrail = {$type}: " . implode(' · ', $colours) . "\n";
        if ($apply) {
            // parent_choice_id = the Wand choice (legacy owner); junction holds
            // BOTH gating choices; parent_match_all = 1 makes it an AND.
            $insSub->execute([$clientId, $productId, $wandChoiceId, 'Wand Colour', $nextSort++]);
            $subId = (int) $pdo->lastInsertId();
            $insJunc->execute([$subId, $wandChoiceId]);
            $insJunc->execute([$subId, $typeChoiceId[$type]]);
            $cs = 0;
            foreach ($colours as $i => $col) { $insChoice->execute([$subId, $col, $i === 0 ? 1 : 0, $cs++]); }
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
