<?php
declare(strict_types=1);

/**
 * READ-ONLY diagnostic: find duplicate-label option choices on a product and
 * report, for each, whether it's a genuine empty STRAY (safe to delete) or a real
 * choice carrying its own pricing / scoping / references (must keep or merge).
 * No writes. Super-admin only. Usage: /_dup_choices_check.php?product_id=8
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pid = (int) ($_GET['product_id'] ?? 8);

$count = static function (string $sql, array $args) use ($pdo): int {
    try { $s = $pdo->prepare($sql); $s->execute($args); return (int) $s->fetchColumn(); }
    catch (Throwable $e) { return -1; }   // -1 = table/column absent
};

$exs = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ? ORDER BY sort_order, id');
$exs->execute([$pid]);
$extras = $exs->fetchAll(PDO::FETCH_ASSOC);

echo "Product #$pid — duplicate-label choices (per extra)\n";
echo "cols: choice_id · active · #priceRows · #bands · #fabricOpts · #parentGate · #quoteUses · #disc · #promo\n";
echo str_repeat('=', 78) . "\n";

$totalDupGroups = 0; $totalStrayCandidates = 0;

foreach ($extras as $ex) {
    $eid = (int) $ex['id'];
    $cs = $pdo->prepare('SELECT id, label, active FROM product_extra_choices WHERE product_extra_id = ? ORDER BY label, id');
    $cs->execute([$eid]);
    $choices = $cs->fetchAll(PDO::FETCH_ASSOC);

    // Group by trimmed label.
    $byLabel = [];
    foreach ($choices as $c) { $byLabel[trim((string) $c['label'])][] = $c; }
    $dupLabels = array_filter($byLabel, static fn ($g) => count($g) > 1);
    if (!$dupLabels) continue;

    echo "\n[extra #$eid] {$ex['name']}\n";
    foreach ($dupLabels as $label => $group) {
        $totalDupGroups++;
        echo "  \"$label\" ×" . count($group) . "\n";
        foreach ($group as $c) {
            $cid = (int) $c['id'];
            $pr  = $count('SELECT COUNT(*) FROM extra_choice_price_rows WHERE product_extra_choice_id = ?', [$cid]);
            $bd  = $count('SELECT COUNT(*) FROM product_extra_choice_bands WHERE choice_id = ?', [$cid]);
            $fo  = $count('SELECT COUNT(*) FROM product_extra_choice_options WHERE choice_id = ?', [$cid]);
            $pg  = $count('SELECT COUNT(*) FROM product_extra_parent_choices WHERE product_extra_choice_id = ?', [$cid]);
            $qu  = $count('SELECT COUNT(*) FROM quote_item_extras WHERE choice_id = ?', [$cid]);
            if ($qu < 0) $qu = $count('SELECT COUNT(*) FROM quote_item_extras WHERE product_extra_choice_id = ?', [$cid]);
            $di  = $count('SELECT COUNT(*) FROM trade_discounts WHERE choice_id = ?', [$cid]);
            $po  = $count('SELECT COUNT(*) FROM trade_promotions WHERE choice_id = ?', [$cid]);
            $stray = ($pr <= 0 && $bd <= 0 && $fo <= 0 && $pg <= 0 && $qu <= 0 && $di <= 0 && $po <= 0);
            if ($stray) $totalStrayCandidates++;
            echo sprintf("     #%-6d act=%d  pr=%-3d bd=%-2d fab=%-3d gate=%-2d quote=%-3d disc=%-2d promo=%-2d  %s\n",
                $cid, (int) $c['active'], $pr, $bd, $fo, $pg, $qu, $di, $po,
                $stray ? '<- STRAY (empty; safe to delete)' : '');
        }
    }
}

echo "\n" . str_repeat('=', 78) . "\n";
echo "Duplicate-label groups: $totalDupGroups · empty-stray choices: $totalStrayCandidates\n";
echo "(-1 in a column = that table/column doesn't exist here.)\n";
echo "A STRAY has no pricing, no scoping, no gate, no quote use, no discount/promo — safe to delete.\n";
echo "A non-stray duplicate carries its own data (likely per-system) — keep or merge deliberately.\n";
