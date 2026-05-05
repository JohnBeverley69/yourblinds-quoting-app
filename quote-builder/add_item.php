<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../pricing-engine/engine.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /quote-history/index.php');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);

$quote = qb_load_quote_or_404($quoteId, $clientId);

$productId = (int)    ($_POST['product_id'] ?? 0);
$supplier  = trim((string) ($_POST['supplier'] ?? ''));
$fabric    = trim((string) ($_POST['fabric']   ?? ''));
$colour    = trim((string) ($_POST['colour']   ?? ''));
$width     = (float)  ($_POST['width']    ?? 0);
$drop      = (float)  ($_POST['drop']     ?? 0);
$quantity  = max(1, (int) ($_POST['quantity'] ?? 1));
$roomName  = trim((string) ($_POST['room_name']      ?? ''));
$opType    = trim((string) ($_POST['operation_type'] ?? ''));
$fitType   = trim((string) ($_POST['fitting_type']   ?? ''));
$itemNotes = trim((string) ($_POST['notes']          ?? ''));
$roundUp   = !empty($_POST['round_up']);

if ($productId <= 0 || $supplier === '' || $fabric === '' || $colour === ''
    || $width <= 0 || $drop <= 0
) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Please fill all required fields (product, supplier, fabric, colour, size).'
    );
}

$prodStmt = db()->prepare(
    'SELECT id, name FROM products WHERE id = ? AND client_id = ? AND active = 1 LIMIT 1'
);
$prodStmt->execute([$productId, $clientId]);
$product = $prodStmt->fetch();
if (!$product) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Product not found.');
}

$priced = pricing_quote_line(
    $clientId,
    $productId,
    $supplier,
    $fabric,
    $colour,
    $width,
    $drop,
    $quantity,
    $roundUp
);
if (isset($priced['error'])) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', $priced['error']);
}

// Use the rounded matrix size for the description if rounding occurred
$descWidth = (float) $priced['matrix_width'];
$descDrop  = (float) $priced['matrix_drop'];

$description = qb_build_description(
    (string) $product['name'],
    $fabric,
    $colour,
    (string) $priced['band_code'],
    $descWidth,
    $descDrop
);

// Next line_no for this quote
$lnStmt = db()->prepare('SELECT COALESCE(MAX(line_no), 0) + 1 FROM quote_items WHERE quote_id = ?');
$lnStmt->execute([$quoteId]);
$nextLineNo = (int) $lnStmt->fetchColumn();

$ins = db()->prepare(
    'INSERT INTO quote_items
      (quote_id, product_id, line_no, room_name, description_text,
       width, drop_value, unit, operation_type, fitting_type,
       quantity, base_cost, discount_percent, markup_percent, sell_price, line_total,
       price_table_id, price_table_row_id, vertical_fabric_id, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, "m", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([
    $quoteId,
    $productId,
    $nextLineNo,
    $roomName !== '' ? $roomName : null,
    $description,
    $descWidth,
    $descDrop,
    $opType  !== '' ? $opType  : null,
    $fitType !== '' ? $fitType : null,
    $quantity,
    $priced['base_cost'],
    $priced['discount_percent'],
    $priced['markup_percent'],
    $priced['sell_price'],
    $priced['line_total'],
    $priced['price_table_id'],
    $priced['price_table_row_id'],
    $priced['vertical_fabric_id'],
    $itemNotes !== '' ? $itemNotes : null,
]);

qb_recompute_totals($quoteId);

$msg = 'Line ' . $nextLineNo . ' added (£' . number_format((float) $priced['line_total'], 2) . ').';
if (!empty($priced['rounded_up'])) {
    $msg .= ' Rounded up to ' . qb_fmt_size($descWidth) . 'm × ' . qb_fmt_size($descDrop) . 'm cell.';
}
qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', $msg);
