<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../_partials/pricing_engine.php';
require __DIR__ . '/../_partials/price_table_parser.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked (status: ' . $quote['status'] . '). Reopen it to add blinds.'
    );
}

// Parse free-text width / drop using the shared dimension parser.
$widthRaw = (string) ($_POST['width'] ?? '');
$dropRaw  = (string) ($_POST['drop']  ?? '');
$widthMm  = ptp_parse_dimension($widthRaw);
$dropMm   = ptp_parse_dimension($dropRaw);

if ($widthMm === null) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Could not read width "' . $widthRaw . '".');
}
if ($dropMm === null) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Could not read drop "' . $dropRaw . '".');
}

// Selected extras: extras[N][extra_id], extras[N][choice_id].
$extras = [];
if (isset($_POST['extras']) && is_array($_POST['extras'])) {
    foreach ($_POST['extras'] as $e) {
        if (!is_array($e)) continue;
        $eid = (int) ($e['extra_id']  ?? 0);
        $cid = (int) ($e['choice_id'] ?? 0);
        if ($eid > 0 && $cid > 0) {
            $extras[] = ['extra_id' => $eid, 'choice_id' => $cid];
        }
    }
}

$input = [
    'product_id' => (int) ($_POST['product_id'] ?? 0),
    'system_id'  => (int) ($_POST['system_id']  ?? 0),
    'option_id'  => (int) ($_POST['option_id']  ?? 0),
    'width_mm'   => $widthMm,
    'drop_mm'    => $dropMm,
    'quantity'   => max(1, (int) ($_POST['quantity'] ?? 1)),
    'extras'     => $extras,
    'round_up'   => !empty($_POST['round_up']),
];

$pdo = db();
$pdo->beginTransaction();
try {
    $priced = pe_calculate_item($pdo, $clientId, $input);
    if (isset($priced['error'])) {
        $pdo->rollBack();
        qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', $priced['error']);
    }

    // Next line_no in this quote.
    $lnSt = $pdo->prepare(
        'SELECT COALESCE(MAX(line_no), 0) + 1 FROM quote_items WHERE quote_id = ?'
    );
    $lnSt->execute([$quoteId]);
    $nextLineNo = (int) $lnSt->fetchColumn();

    $room  = trim((string) ($_POST['room_name'] ?? ''));
    $note  = trim((string) ($_POST['notes']     ?? ''));

    $ins = $pdo->prepare(
        'INSERT INTO quote_items
          (quote_id, line_no,
           product_id, product_name_snapshot,
           system_id, system_name_snapshot,
           option_id,
           fabric_band_snapshot, fabric_supplier_snapshot, fabric_name_snapshot,
           fabric_colour_snapshot, fabric_code_snapshot,
           room_name,
           width_mm, drop_mm, width_matrix_mm, drop_matrix_mm,
           quantity,
           price_table_id, price_table_row_id,
           base_price, extras_total, subtotal_per_blind,
           markup_percent, discount_percent,
           sell_price, line_total,
           notes)
         VALUES
          (?, ?,
           ?, ?,
           ?, ?,
           ?,
           ?, ?, ?,
           ?, ?,
           ?,
           ?, ?, ?, ?,
           ?,
           ?, ?,
           ?, ?, ?,
           ?, ?,
           ?, ?,
           ?)'
    );
    $ins->execute([
        $quoteId, $nextLineNo,
        $priced['product_id'], $priced['product_name'],
        $priced['system_id'],  $priced['system_name'],
        $priced['option_id'],
        $priced['fabric_band'], $priced['fabric_supplier'], $priced['fabric_name'],
        $priced['fabric_colour'], $priced['fabric_code'],
        $room !== '' ? $room : null,
        $priced['width_mm'], $priced['drop_mm'],
        $priced['matrix_width_mm'], $priced['matrix_drop_mm'],
        $priced['quantity'],
        $priced['price_table_id'], $priced['price_table_row_id'],
        $priced['base_price'], $priced['extras_total'], $priced['subtotal_per_blind'],
        $priced['markup_percent'], $priced['discount_percent'],
        $priced['sell_price'], $priced['line_total'],
        $note !== '' ? $note : null,
    ]);
    $newItemId = (int) $pdo->lastInsertId();

    // Insert one row per applied extra.
    if (!empty($priced['extras_applied'])) {
        $insE = $pdo->prepare(
            'INSERT INTO quote_item_extras
               (quote_item_id,
                product_extra_id, extra_name_snapshot,
                product_extra_choice_id, choice_label_snapshot,
                mode, amount_applied)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($priced['extras_applied'] as $ex) {
            $insE->execute([
                $newItemId,
                $ex['extra_id'], $ex['extra_name'],
                $ex['choice_id'], $ex['choice_label'],
                $ex['mode'], $ex['amount_applied'],
            ]);
        }
    }

    qb_recompute_totals($quoteId);
    $pdo->commit();

    $msg = 'Blind ' . $nextLineNo . ' added (' . qb_fmt_money($priced['line_total']) . ').';
    if (!empty($priced['rounded_up'])) {
        $msg .= ' Rounded up to ' . qb_fmt_mm((int) $priced['matrix_width_mm'])
              . ' × ' . qb_fmt_mm((int) $priced['matrix_drop_mm']) . ' cell.';
    }
    // Land back on the Add-line section so the user can keep adding blinds
    // without scrolling past the items table each time.
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId . '#add-line', 'success', $msg);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId . '#add-line', 'error', 'Could not add blind: ' . $e->getMessage());
}
