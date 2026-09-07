<?php
declare(strict_types=1);

/**
 * READ-ONLY diagnostic: find duplicate-label option choices. With ?product_id=N
 * it details one product (per-choice pricing/scoping/gate/refs, flags empty
 * strays). With no product_id it SCANS every factory-owned master product and
 * lists which have duplicate-label choices, so you can spot the same mess on
 * other products. No writes. Super-admin only.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$factory = function_exists('current_factory_id') ? (int) current_factory_id()
         : (function_exists('factory_client_id') ? (int) factory_client_id() : 3);

$count = static function (string $sql, array $args) use ($pdo): int {
    try { $s = $pdo->prepare($sql); $s->execute($args); return (int) $s->fetchColumn(); }
    catch (Throwable $e) { return -1; }
};

/** [label => [choiceRows]] duplicate groups for one extra. */
$dupGroups = static function (int $eid) use ($pdo): array {
    $cs = $pdo->prepare('SELECT id, label, active FROM product_extra_choices WHERE product_extra_id = ? ORDER BY label, id');
    $cs->execute([$eid]);
    $byLabel = [];
    foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) $byLabel[trim((string) $c['label'])][] = $c;
    return array_filter($byLabel, static fn ($g) => count($g) > 1);
};

// ── Whole-catalogue scan (no product_id) ────────────────────────────────────
if (($_GET['product_id'] ?? '') === '') {
    $ps = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? ORDER BY sort_order, name');
    $ps->execute([$factory]);
    $products = $ps->fetchAll(PDO::FETCH_ASSOC);
    echo "Duplicate-label choice scan — factory #$factory master products (" . count($products) . ")\n";
    echo str_repeat('=', 70) . "\n";
    $dirty = 0;
    foreach ($products as $p) {
        $pid = (int) $p['id'];
        $exs = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? ORDER BY id');
        $exs->execute([$pid]);
        $hits = [];
        foreach ($exs->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            foreach ($dupGroups((int) $ex['id']) as $label => $g) {
                $hits[] = $ex['name'] . ': "' . $label . '" ×' . count($g);
            }
        }
        if ($hits) {
            $dirty++;
            echo "\n#$pid  {$p['name']}\n";
            foreach ($hits as $h) echo "   - $h\n";
        }
    }
    echo "\n" . str_repeat('=', 70) . "\n";
    echo $dirty === 0
        ? "Clean — no duplicate-label choices on any factory product.\n"
        : "$dirty product(s) have duplicate-label choices. Detail one with ?product_id=N; fix with /migrate_dedup_vertical_choices.php?product_id=N then push.\n";
    exit;
}

// ── Single-product detail ───────────────────────────────────────────────────
$pid = (int) $_GET['product_id'];
$exs = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? ORDER BY sort_order, id');
$exs->execute([$pid]);
echo "Product #$pid — duplicate-label choices (per extra)\n";
echo "cols: choice_id · active · #priceRows · #bands · #fabricOpts · #parentGate · #quoteUses · #disc · #promo\n";
echo str_repeat('=', 78) . "\n";
$groups = 0; $strays = 0;
foreach ($exs->fetchAll(PDO::FETCH_ASSOC) as $ex) {
    $eid = (int) $ex['id'];
    $dl  = $dupGroups($eid);
    if (!$dl) continue;
    echo "\n[extra #$eid] {$ex['name']}\n";
    foreach ($dl as $label => $group) {
        $groups++;
        echo "  \"$label\" ×" . count($group) . "\n";
        foreach ($group as $c) {
            $cid = (int) $c['id'];
            $pr = $count('SELECT COUNT(*) FROM extra_choice_price_rows WHERE product_extra_choice_id = ?', [$cid]);
            $bd = $count('SELECT COUNT(*) FROM product_extra_choice_bands WHERE choice_id = ?', [$cid]);
            $fo = $count('SELECT COUNT(*) FROM product_extra_choice_options WHERE choice_id = ?', [$cid]);
            $pg = $count('SELECT COUNT(*) FROM product_extra_parent_choices WHERE product_extra_choice_id = ?', [$cid]);
            $qu = $count('SELECT COUNT(*) FROM quote_item_extras WHERE choice_id = ?', [$cid]);
            $di = $count('SELECT COUNT(*) FROM trade_discounts WHERE choice_id = ?', [$cid]);
            $po = $count('SELECT COUNT(*) FROM trade_promotions WHERE choice_id = ?', [$cid]);
            $stray = ($pr <= 0 && $bd <= 0 && $fo <= 0 && $pg <= 0 && $qu <= 0 && $di <= 0 && $po <= 0);
            if ($stray) $strays++;
            echo sprintf("     #%-6d act=%d  pr=%-3d bd=%-2d fab=%-3d gate=%-2d quote=%-3d disc=%-2d promo=%-2d  %s\n",
                $cid, (int) $c['active'], $pr, $bd, $fo, $pg, $qu, $di, $po, $stray ? '<- STRAY (empty)' : '');
        }
    }
}
echo "\n" . str_repeat('=', 78) . "\n";
echo "Duplicate-label groups: $groups · empty-stray choices: $strays\n";
