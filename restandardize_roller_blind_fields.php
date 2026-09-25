<?php
declare(strict_types=1);

/**
 * Re-standardise the roller "Fascia Sizing" multi-blind cascade after manual
 * edits wiped its stable codes (the form + engine key off codes, not captions).
 * Restores the codes and the canonical gating, KEEPING the current captions:
 *
 *   Fascia Sizing (fascia_sizing)
 *     To Fit / Standard  -> standard
 *     Over size ...      -> oversize
 *     Multi ...          -> multi
 *   Fascia Width (fascia_width, is_width_source)
 *   Number of Blinds? (fascia_blind_count) : 2->n2 .. 5->n5
 *   Blind 1..5 (fascia_blind_width) : 1&2 gated [Multi]; 3..5 [Multi AND count>=N]
 *
 * Idempotent + transactional. Web-runnable: /restandardize_roller_blind_fields.php
 * (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Bev Roller Blinds not found for client {$MASTER}.\n"); }

$hasMatchAll = false; try { $pdo->query('SELECT parent_match_all FROM product_extras LIMIT 1'); $hasMatchAll = true; } catch (Throwable $e) {}
$hasWidthSrc = false; try { $pdo->query('SELECT is_width_source FROM product_extras LIMIT 1'); $hasWidthSrc = true; } catch (Throwable $e) {}

$findExtra = function (string $nameRegex) use ($pdo, $productId, $MASTER): array {
    $q = $pdo->prepare("SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND name REGEXP ? ORDER BY id");
    $q->execute([$productId, $MASTER, $nameRegex]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
};
$choicesOf = function (int $extraId) use ($pdo): array {
    $q = $pdo->prepare("SELECT id, label FROM product_extra_choices WHERE product_extra_id = ? AND active = 1");
    $q->execute([$extraId]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
};
$setExtraCode = function (int $id, string $code) use ($pdo) { $pdo->prepare("UPDATE product_extras SET code = ? WHERE id = ?")->execute([$code, $id]); };
$setChoiceCode = function (int $id, string $code) use ($pdo) { $pdo->prepare("UPDATE product_extra_choices SET code = ? WHERE id = ?")->execute([$code, $id]); };
$setGates = function (int $extraId, array $parentIds, bool $matchAll) use ($pdo, $hasMatchAll): void {
    $parentIds = array_values(array_unique(array_map('intval', $parentIds)));
    $primary = $parentIds[0] ?? null;
    if ($hasMatchAll) $pdo->prepare("UPDATE product_extras SET parent_choice_id = ?, parent_match_all = ? WHERE id = ?")->execute([$primary, $matchAll ? 1 : 0, $extraId]);
    else              $pdo->prepare("UPDATE product_extras SET parent_choice_id = ? WHERE id = ?")->execute([$primary, $extraId]);
    $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?")->execute([$extraId]);
    $ins = $pdo->prepare("INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)");
    foreach ($parentIds as $pid) $ins->execute([$extraId, $pid]);
};

$sizing = $findExtra('^Fascia Sizing$');
if (!$sizing) { exit("No 'Fascia Sizing' extra found.\n"); }
$sizingId = (int) $sizing[0]['id'];

$countRows = $findExtra('Number.*Blind');
if (!$countRows) { exit("No 'Number of Blinds' extra found.\n"); }
$countId = (int) $countRows[0]['id'];

$fw = $findExtra('^Fascia Width$');
$blinds = [];
foreach ($findExtra('^Blind[[:space:]]+[1-5]') as $r) {
    if (preg_match('/^Blind[[:space:]]+([1-5])/', (string) $r['name'], $m)) $blinds[(int) $m[1]] = (int) $r['id'];
}
if (count($blinds) !== 5) { exit('Expected 5 blind fields, found ' . count($blinds) . ".\n"); }

$pdo->beginTransaction();
try {
    // Fascia Sizing + choices.
    $setExtraCode($sizingId, 'fascia_sizing');
    $cMulti = 0;
    foreach ($choicesOf($sizingId) as $c) {
        $l = strtolower((string) $c['label']);
        if (preg_match('/multi/', $l))                       { $setChoiceCode((int) $c['id'], 'multi');    $cMulti = (int) $c['id']; }
        elseif (preg_match('/over ?size|oversize/', $l))     { $setChoiceCode((int) $c['id'], 'oversize'); }
        else                                                 { $setChoiceCode((int) $c['id'], 'standard'); }
    }
    if ($cMulti === 0) { throw new RuntimeException("Couldn't identify the Multi choice on Fascia Sizing."); }

    // Number of Blinds + choices (n2..n5 by numeric label).
    $setExtraCode($countId, 'fascia_blind_count');
    $countChoice = [];
    foreach ($choicesOf($countId) as $c) {
        $n = (int) trim((string) $c['label']);
        if ($n >= 2 && $n <= 5) { $setChoiceCode((int) $c['id'], 'n' . $n); $countChoice[$n] = (int) $c['id']; }
    }
    if (count($countChoice) < 4) { throw new RuntimeException('Missing count choices 2..5.'); }

    // Fascia Width.
    if ($fw) {
        $fwId = (int) $fw[0]['id'];
        $setExtraCode($fwId, 'fascia_width');
        if ($hasWidthSrc) $pdo->prepare("UPDATE product_extras SET is_width_source = 1 WHERE id = ?")->execute([$fwId]);
    }

    // Blind 1..5.
    for ($n = 1; $n <= 5; $n++) {
        $wId = $blinds[$n];
        $pdo->prepare("UPDATE product_extras SET code = 'fascia_blind_width', sort_order = ?, active = 1 WHERE id = ?")->execute([42 + $n, $wId]);
        if ($n <= 2) { $setGates($wId, [$cMulti], false); }
        else {
            $gate = [$cMulti];
            for ($k = $n; $k <= 5; $k++) $gate[] = $countChoice[$k];
            $setGates($wId, $gate, true);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit("\nFAILED: " . $e->getMessage() . " (no changes saved)\n");
}

echo "Restored roller fascia cascade codes + gating (captions kept):\n";
echo "  Fascia Sizing (fascia_sizing) + standard/oversize/multi\n";
echo "  Number of Blinds (fascia_blind_count) + n2..n5\n";
echo "  Fascia Width (fascia_width" . ($hasWidthSrc ? ', is_width_source' : '') . ")\n";
echo "  Blind 1..5 (fascia_blind_width) — all owned by Multi, so they render 1..5.\n";
