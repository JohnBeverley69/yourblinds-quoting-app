<?php
declare(strict_types=1);

/**
 * Seed: "Draw Options" group (the corded draws) for Bev Vertical Blinds.
 *
 * The product had Control Options = Corded / Wand and a Wand Options group, but NO
 * Draw Options group — so picking Corded gave no draw to choose, and the build rules'
 * corded-centre branch (BESTFIT("vogue_split_cord",…) keyed on Draw = "C/L"/"C/R")
 * could never match. This adds the six corded draws with the EXACT labels the rules
 * expect — all uniform, no spaces around the slash — gated to Control = Corded, for
 * every system that offers Corded.
 *
 * Built by cloning the existing Wand Options group + a Wand Options choice, so every
 * column, client scope and default is filled the same way the admin would. Idempotent:
 * the group and each choice are only created if missing, and parent links are de-duped.
 *
 * Run via web: /seed_vertical_draw_options.php (super-admin).
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

$MASTER = function_exists('factory_client_id') ? (int) factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Vertical Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Product 'Bev Vertical Blinds' not found for client {$MASTER}.\n"); }

// Groups we need: a template (Wand Options), the parent (Control Options), and the
// target (Draw Options — may already exist).
$ex = $pdo->prepare(
    "SELECT id, name FROM product_extras
      WHERE product_id = ? AND client_id = ?
        AND name IN ('Wand Options','Control Options','Draw Options')"
);
$ex->execute([$productId, $MASTER]);
$extra = [];
foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extra[(string) $r['name']] = (int) $r['id']; }
if (empty($extra['Wand Options']))    { exit("No 'Wand Options' group to model from.\n"); }
if (empty($extra['Control Options'])) { exit("No 'Control Options' group found.\n"); }

// Every "Corded" choice under Control Options (one per system).
$cc = $pdo->prepare("SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = 'Corded'");
$cc->execute([$extra['Control Options']]);
$cordedIds = array_map('intval', $cc->fetchAll(PDO::FETCH_COLUMN));
if (!$cordedIds) { exit("No 'Corded' choices found under Control Options.\n"); }

// Clone a row (all columns except id), applying overrides that actually exist on the
// table, and blanking source_id so this is treated as a master-origin row.
$copyRow = static function (string $table, int $templateId, array $ov) use ($pdo): int {
    $row = $pdo->query("SELECT * FROM `$table` WHERE id = " . (int) $templateId)->fetch(PDO::FETCH_ASSOC);
    if (!$row) { throw new RuntimeException("template {$table}#{$templateId} not found"); }
    unset($row['id']);
    if (array_key_exists('source_id', $row)) { $row['source_id'] = null; }
    foreach ($ov as $k => $v) { if (array_key_exists($k, $row)) { $row[$k] = $v; } }
    $cols = array_keys($row);
    $sql  = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
    $pdo->prepare($sql)->execute(array_values($row));
    return (int) $pdo->lastInsertId();
};

$pdo->beginTransaction();

// 1) The Draw Options group (clone Wand Options; re-point its parent to Corded).
$drawExtraId = $extra['Draw Options'] ?? 0;
if ($drawExtraId === 0) {
    $maxSort = (int) $pdo->query(
        "SELECT COALESCE(MAX(sort_order),0) FROM product_extras WHERE product_id = {$productId} AND client_id = {$MASTER}"
    )->fetchColumn();
    $drawExtraId = $copyRow('product_extras', $extra['Wand Options'], [
        'name'             => 'Draw Options',
        'sort_order'       => $maxSort + 1,
        'active'           => 1,
        'is_required'      => 0,
        'parent_choice_id' => $cordedIds[0],   // legacy single-parent gate → Corded
    ]);
    echo "Created 'Draw Options' group (id {$drawExtraId}).\n";
} else {
    echo "'Draw Options' group already exists (id {$drawExtraId}) — filling in choices/links.\n";
}

// 2) The six corded draws — uniform labels, no spaces around the slash.
$tplChoice = (int) $pdo->query(
    "SELECT id FROM product_extra_choices WHERE product_extra_id = {$extra['Wand Options']} ORDER BY sort_order, id LIMIT 1"
)->fetchColumn();
if ($tplChoice === 0) { throw new RuntimeException('No Wand Options choice to model from.'); }

$have = $pdo->prepare("SELECT label FROM product_extra_choices WHERE product_extra_id = ?");
$have->execute([$drawExtraId]);
$existing = array_flip(array_map('strval', $have->fetchAll(PDO::FETCH_COLUMN)));

$labels = ['R/R', 'L/L', 'C/L', 'C/R', 'L Ctrl / Right Stack', 'R Ctrl / Left Stack'];
$seq = 0; $added = 0;
foreach ($labels as $lab) {
    $seq++;
    if (isset($existing[$lab])) { echo "  choice '{$lab}' already there — skip\n"; continue; }
    $copyRow('product_extra_choices', $tplChoice, [
        'product_extra_id' => $drawExtraId,
        'label'            => $lab,
        'sort_order'       => $seq,
        'system_id'        => null,             // all systems that reach here (Corded)
        'is_default'       => $added === 0 ? 1 : 0,
        'active'           => 1,
        'price_delta'      => 0,
        'price_percent'    => 0,
        'price_per_metre'  => 0,
        'image_path'       => null,
    ]);
    echo "  added '{$lab}'\n";
    $added++;
}

// 3) Gate the group to every Corded choice (so it shows when Control = Corded).
$existLink = $pdo->prepare("SELECT 1 FROM product_extra_parent_choices WHERE product_extra_id = ? AND product_extra_choice_id = ?");
$addLink   = $pdo->prepare("INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)");
$linked = 0;
foreach ($cordedIds as $cid) {
    $existLink->execute([$drawExtraId, $cid]);
    if ($existLink->fetchColumn()) { continue; }
    $addLink->execute([$drawExtraId, $cid]);
    $linked++;
}

$pdo->commit();

echo "Gated to {$linked} new 'Corded' link(s) (Corded choices total: " . count($cordedIds) . ").\n";
echo "\nDone. 'Draw Options' now appears when Control = Corded, with the six corded draws.\n";
echo "Push the catalogue afterwards so tenants get it too.\n";
