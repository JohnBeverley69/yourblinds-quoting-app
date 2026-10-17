<?php
declare(strict_types=1);

/**
 * READ-ONLY: what each line of a placed order will bill the account, with the
 * figures the price comes from. Answers "is this order's value right?" without
 * raising an invoice (John, 2026-10-12: ABC-2026-0001, 20 vertical slats +
 * 1 Forest Wood, showing £65.77 net on the Dispatch tray).
 *
 * Super-admin, web-runnable: /setup/tools/order_bill_check.php?order=ABC-2026-0001
 * Writes nothing.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once dirname(__DIR__, 2) . '/_partials/factory_ar.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo     = db();
$num     = PHP_SAPI === 'cli' ? (string) ($argv[1] ?? '') : trim((string) ($_GET['order'] ?? ''));
$factory = ar_factory_id();
$money   = static fn ($n) => $n === null ? '—' : '£' . number_format((float) $n, 2);

$st = $pdo->prepare('SELECT id, quote_number, client_id, status, subtotal FROM quotes WHERE quote_number = ? LIMIT 1');
$st->execute([$num]);
$q = $st->fetch(PDO::FETCH_ASSOC);
if (!$q) { echo "No order '$num'. Use ?order=ABC-2026-0001\n"; exit; }
$portal = (int) $q['client_id'] !== $factory;
echo $q['quote_number'] . ' [status: ' . $q['status'] . '] — ' . ($portal ? "placed on the account's portal (billed at TRADE)" : 'raised by the factory (billed at SELL)') . "\n";
echo 'Order subtotal on the quote (the account\'s own price): ' . $money($q['subtotal']) . "\n\n";

$items = $pdo->prepare(
    'SELECT qi.id, qi.product_name_snapshot, qi.quantity, qi.width_mm, qi.drop_mm, qi.base_price, qi.sell_price,
            qi.line_total, qi.trade_price_per_blind, qi.trade_discount_percent, qi.trade_discount_amount,
            COALESCE(NULLIF(p.source_client_id,0), p.client_id) AS owner
       FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? ORDER BY qi.line_no, qi.id'
);
$items->execute([(int) $q['id']]);
$ex = $pdo->prepare('SELECT extra_name_snapshot, choice_label_snapshot, amount_applied, trade_amount FROM quote_item_extras WHERE quote_item_id = ?');
foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $i) {
    echo '• ' . $i['product_name_snapshot'] . ' × ' . $i['quantity'] . ' (' . (int) $i['width_mm'] . ' × ' . (int) $i['drop_mm'] . ')'
       . ((int) $i['owner'] === $factory ? '' : '   [NOT a factory line — not billed by us]') . "\n";
    echo '    account\'s price: sell ' . $money($i['sell_price']) . ' each, line ' . $money($i['line_total']) . "\n";
    echo '    our trade price:  ' . $money($i['trade_price_per_blind']) . ' each, buying discount '
       . ($i['trade_discount_percent'] !== null ? rtrim(rtrim(number_format((float) $i['trade_discount_percent'], 2), '0'), '.') . '%' : '—')
       . ' (' . $money($i['trade_discount_amount']) . ")\n";
    $ex->execute([(int) $i['id']]);
    foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $e) {
        if ((float) $e['amount_applied'] == 0.0 && (float) ($e['trade_amount'] ?? 0) == 0.0) continue;
        echo '      + ' . trim($e['extra_name_snapshot'] . ': ' . $e['choice_label_snapshot']) . ' — account ' . $money($e['amount_applied']) . ', trade ' . $money($e['trade_amount']) . "\n";
    }
}

echo "\nWhat the invoice will bill (ar_invoice_lines_from_order, nothing saved):\n";
$total = 0.0;
$bill = ar_invoice_lines_from_order($pdo, $factory, (int) $q['id']);
if (!empty($bill['uncaptured'])) echo "  NB: some options have no trade figure captured (they bill as £0).
";
foreach ($bill['lines'] as $l) {
    $total += (float) $l['line_net'];
    echo '  ' . str_pad($money($l['line_net']), 10, ' ', STR_PAD_LEFT) . '  ' . $l['quantity'] . ' × ' . $money($l['unit_net']) . '  ' . mb_substr((string) $l['description'], 0, 90) . "\n";
}
echo '  ' . str_pad($money($total), 10, ' ', STR_PAD_LEFT) . "  TOTAL NET\n";
