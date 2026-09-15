<?php
declare(strict_types=1);

/**
 * Re-standardise the roller multi-blind "Blind N" width fields after manual
 * edits left them inconsistent: gives all five the stable code `fascia_blind_width`
 * (the width-capture keys off it), restores the canonical gating so they render
 * as ordered siblings under Fascia Sizing (all owned by the Multi choice), and
 * sets a clean sort order. KEEPS the current captions (your renames).
 *
 * Canonical gating (from seed_roller_fascia_modes.php):
 *   Blind 1 & 2  -> [Multi]                       (match_all OFF)
 *   Blind 3..5   -> [Multi, count>=N ...]         (match_all ON)
 *
 * Idempotent + transactional. Web-runnable: /restandardize_roller_blind_fields.php
 * (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$prod = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
$prod->execute([$MASTER]);
$productId = (int) $prod->fetchColumn();
if ($productId === 0) { exit("Bev Roller Blinds not found for client {$MASTER}.\n"); }

$hasMatchAll = false; try { $pdo->query('SELECT parent_match_all FROM product_extras LIMIT 1'); $hasMatchAll = true; } catch (Throwable $e) {}

// Resolve the Multi choice (code 'multi' on Fascia Sizing) and the count choices.
$mq = $pdo->prepare(
    "SELECT c.id FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
      WHERE e.product_id = ? AND e.client_id = ? AND e.code = 'fascia_sizing' AND c.code = 'multi' LIMIT 1"
);
$mq->execute([$productId, $MASTER]);
$cMulti = (int) $mq->fetchColumn();
if ($cMulti === 0) { exit("Could not find the 'Multi blind' choice (fascia_sizing/multi). Run /seed_roller_fascia_modes.php.\n"); }

$countChoice = [];  // n => choice id
$cq = $pdo->prepare(
    "SELECT c.code, c.id FROM product_extra_choices c
       JOIN product_extras e ON e.id = c.product_extra_id
      WHERE e.product_id = ? AND e.client_id = ? AND e.code = 'fascia_blind_count'"
);
$cq->execute([$productId, $MASTER]);
foreach ($cq->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (preg_match('/^n(\d+)$/', (string) $r['code'], $m)) $countChoice[(int) $m[1]] = (int) $r['id'];
}
if (count($countChoice) < 4) { exit("Could not find the count choices (n2..n5). Run /seed_roller_fascia_modes.php.\n"); }

// Find the current five blind fields by name (Blind 1 .. Blind 5, any suffix).
$bq = $pdo->prepare("SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND name REGEXP '^Blind[[:space:]]+[1-5]'");
$bq->execute([$productId, $MASTER]);
$blinds = [];  // n => extra id
foreach ($bq->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (preg_match('/^Blind[[:space:]]+([1-5])/', (string) $r['name'], $m)) $blinds[(int) $m[1]] = (int) $r['id'];
}
if (count($blinds) !== 5) { exit('Expected 5 blind fields, found ' . count($blinds) . " — resolve manually.\n"); }

$setGates = function (int $extraId, array $parentIds, bool $matchAll) use ($pdo, $hasMatchAll): void {
    $parentIds = array_values(array_unique(array_map('intval', $parentIds)));
    $primary = $parentIds[0] ?? null;
    if ($hasMatchAll) {
        $pdo->prepare("UPDATE product_extras SET parent_choice_id = ?, parent_match_all = ? WHERE id = ?")->execute([$primary, $matchAll ? 1 : 0, $extraId]);
    } else {
        $pdo->prepare("UPDATE product_extras SET parent_choice_id = ? WHERE id = ?")->execute([$primary, $extraId]);
    }
    $pdo->prepare("DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?")->execute([$extraId]);
    $ins = $pdo->prepare("INSERT INTO product_extra_parent_choices (product_extra_id, product_extra_choice_id) VALUES (?, ?)");
    foreach ($parentIds as $pid) $ins->execute([$extraId, $pid]);
};

$pdo->beginTransaction();
try {
    for ($n = 1; $n <= 5; $n++) {
        $wId = $blinds[$n];
        $pdo->prepare("UPDATE product_extras SET code = 'fascia_blind_width', sort_order = ?, active = 1 WHERE id = ?")
            ->execute([42 + $n, $wId]);
        if ($n <= 2) {
            $setGates($wId, [$cMulti], false);
        } else {
            $gate = [$cMulti];
            for ($k = $n; $k <= 5; $k++) $gate[] = $countChoice[$k];
            $setGates($wId, $gate, true);
        }
        echo "Blind {$n} (#{$wId}): code fascia_blind_width, gated " . ($n <= 2 ? "on Multi.\n" : "Multi AND count>={$n}.\n");
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    exit("\nFAILED: " . $e->getMessage() . " (no changes saved)\n");
}

echo "\nDone. The five blind fields now share code fascia_blind_width, are owned by the\n";
echo "Multi choice (so they render in order 1..5), and keep their current captions.\n";
