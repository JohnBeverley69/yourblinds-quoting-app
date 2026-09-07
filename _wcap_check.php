<?php
declare(strict_types=1);
/**
 * TEMPORARY diagnostic (Phase 2A verification) — read-only. Dumps the wholesale
 * capture columns for a quote's lines/extras so we can confirm they populate.
 * Delete after use. Usage: /_wcap_check.php?quote_id=767  (defaults to latest quote)
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$qid = (int) ($_GET['quote_id'] ?? 0);
if ($qid <= 0) {
    $qid = (int) $pdo->query('SELECT id FROM quotes ORDER BY id DESC LIMIT 1')->fetchColumn();
}
echo "Quote id: $qid\n\n";

$items = $pdo->prepare(
    'SELECT id, product_name_snapshot, quantity, base_price,
            trade_price_per_blind, trade_discount_percent, trade_discount_amount
       FROM quote_items WHERE quote_id = ? ORDER BY line_no'
);
$items->execute([$qid]);
foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
    printf("LINE #%d  %s  x%d\n", $it['id'], $it['product_name_snapshot'], $it['quantity']);
    printf("   base_price=%s  trade_price_per_blind=%s  trade_disc%%=%s  trade_disc£=%s\n",
        var_export($it['base_price'], true),
        var_export($it['trade_price_per_blind'], true),
        var_export($it['trade_discount_percent'], true),
        var_export($it['trade_discount_amount'], true));
    $ex = $pdo->prepare(
        'SELECT extra_name_snapshot, choice_label_snapshot, amount_applied,
                trade_amount, promo_discount_percent, promo_discount_amount
           FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id'
    );
    $ex->execute([(int) $it['id']]);
    foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $e) {
        printf("   EXTRA %s / %s : amount_applied(retail)=%s  trade_amount(wholesale)=%s  promo%%=%s  promo£=%s\n",
            $e['extra_name_snapshot'], $e['choice_label_snapshot'],
            var_export($e['amount_applied'], true),
            var_export($e['trade_amount'], true),
            var_export($e['promo_discount_percent'], true),
            var_export($e['promo_discount_amount'], true));
    }
    echo "\n";
}
echo "done.\n";
