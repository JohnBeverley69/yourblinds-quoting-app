<?php
declare(strict_types=1);

/**
 * Factory · mark an order's bought-in items as RECEIVED from the supplier.
 * Stamps received_at on this factory's supplier_orders rows for the quote and
 * re-derives the order rollup. Gates dispatch (see set-status.php).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/../_partials/factory_boughtin.php';

requireFactory();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
csrf_check();

$factoryId = current_factory_id();
$quoteId   = (int) ($_POST['quote_id'] ?? 0);
$back      = '/factory/incoming-orders.php';
$pdo       = db();

// Authorise: this factory owns a line on the order.
$own = $pdo->prepare(
    'SELECT 1 FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ? LIMIT 1'
);
$own->execute([$quoteId, $factoryId]);
if (!$own->fetchColumn()) { http_response_code(403); exit('Not your order.'); }

$undo = !empty($_POST['undo']);
try {
    if ($undo) {
        $pdo->prepare('UPDATE supplier_orders SET received_at = NULL WHERE quote_id = ? AND ordered_by_factory_id = ?')
            ->execute([$quoteId, $factoryId]);
        $msg = 'Bought-in items marked not received.';
    } else {
        $pdo->prepare('UPDATE supplier_orders SET received_at = COALESCE(received_at, NOW()) WHERE quote_id = ? AND ordered_by_factory_id = ?')
            ->execute([$quoteId, $factoryId]);
        $msg = 'Bought-in items marked received.';
    }
    factory_boughtin_restamp($pdo, $quoteId, $factoryId);
    // Phase 0: bought-in received can complete the Ready gate — roll it up.
    require_once __DIR__ . '/../_partials/order_stage.php';
    recompute_order_stage($pdo, $quoteId);
} catch (Throwable $e) {
    qb_flash_redirect($back, 'error', 'Could not update: ' . $e->getMessage());
}
qb_flash_redirect($back, 'success', $msg);
