<?php
declare(strict_types=1);
/**
 * TEMPORARY diagnostic — verify the "supplier trade discount wins" engine change.
 * Read-only except a self-cleaned temp client_discounts row. Delete after use.
 * /_disc_check.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/pricing_engine.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Find a (system, fabric, width, drop) that resolves for a client+product, then price it.
function priceIt(PDO $pdo, int $clientId, int $productId): array {
    $sysRows = $pdo->prepare('SELECT id FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1');
    $sysRows->execute([$productId, $clientId]);
    $systems = array_map('intval', array_column($sysRows->fetchAll(PDO::FETCH_ASSOC), 'id')) ?: [0];
    $fabRows = $pdo->prepare('SELECT id FROM product_options WHERE product_id = ? AND client_id = ? AND active = 1 LIMIT 25');
    $fabRows->execute([$productId, $clientId]);
    $fabrics = array_map('intval', array_column($fabRows->fetchAll(PDO::FETCH_ASSOC), 'id'));
    foreach ($systems as $sys) {
        foreach ($fabrics as $fab) {
            $r = pe_calculate_item($pdo, $clientId, [
                'product_id' => $productId, 'system_id' => $sys, 'option_id' => $fab,
                'width_mm' => 1000, 'drop_mm' => 1200, 'quantity' => 1, 'round_up' => true, 'extras' => [],
            ]);
            if (!isset($r['error'])) { $r['_sys'] = $sys; $r['_fab'] = $fab; return $r; }
        }
    }
    return ['error' => 'no resolvable fabric/size'];
}
function show(string $label, array $r): void {
    if (isset($r['error'])) { echo "$label: ERROR ${r['error']}\n\n"; return; }
    printf("%s:\n  trade_price=%.2f  trade_disc=%.2f%%  base_after_trade=%.2f  client_discount=%.2f%%  sell=%.2f\n\n",
        $label, $r['trade_price_per_blind'], $r['trade_discount_percent'], $r['base_price'], $r['discount_percent'], $r['sell_price']);
}

// Beverley Roller (own product, factory-owned) — trade discount should still apply.
show('Beverley(3) Bev Roller Blinds', priceIt($pdo, 3, 5));

// Annette(53) — find her Roller product (supplier) that has a trade discount.
$aq = $pdo->prepare("SELECT DISTINCT td.product_id, p.name FROM trade_discounts td JOIN products p ON p.id = td.product_id
                      WHERE td.client_id = 53 AND td.active = 1 AND (td.extra_id IS NULL) ORDER BY p.name");
$aq->execute();
$annetteProducts = $aq->fetchAll(PDO::FETCH_ASSOC);
echo "Annette trade_discounts on: " . implode(', ', array_map(fn($r) => $r['name'] . '(#' . $r['product_id'] . ')', $annetteProducts)) . "\n\n";

foreach ($annetteProducts as $ap) {
    $pid = (int) $ap['product_id'];
    show("Annette(53) {$ap['name']} — trade only", priceIt($pdo, 53, $pid));

    // Temporarily add a 20% client_discounts buying discount, re-price (expect it SUPPRESSED), then remove.
    $sys = (int) ($pdo->query("SELECT id FROM product_systems WHERE product_id = $pid AND client_id = 53 AND active = 1 LIMIT 1")->fetchColumn() ?: 0);
    $pdo->prepare('INSERT INTO client_discounts (client_id, product_id, system_id, discount_percent) VALUES (53, ?, ?, 20)
                   ON DUPLICATE KEY UPDATE discount_percent = 20')->execute([$pid, $sys ?: null]);
    show("Annette(53) {$ap['name']} — trade + 20% client (expect client suppressed → discount 0)", priceIt($pdo, 53, $pid));
    // Clean up the temp row.
    if ($sys) $pdo->prepare('DELETE FROM client_discounts WHERE client_id = 53 AND product_id = ? AND system_id = ?')->execute([$pid, $sys]);
    else $pdo->prepare('DELETE FROM client_discounts WHERE client_id = 53 AND product_id = ? AND system_id IS NULL')->execute([$pid]);
    echo "(temp client_discount removed)\n\n";
}
echo "done.\n";
