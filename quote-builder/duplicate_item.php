<?php
declare(strict_types=1);

/**
 * Clone a quote_items row + its extras, then drop the user into edit
 * mode for the new item with the cursor near the size fields. Saves
 * a lot of retyping when a quote has several blinds with the same
 * fabric/system/options but different dimensions ("the same blackout
 * roller for every bedroom, just different widths").
 *
 * The clone copies every snapshot field as-is — the price stays the
 * same as the source until the user changes the size and saves, at
 * which point /quote-builder/update_item.php re-prices via the engine.
 *
 * The new line_no = MAX + 1 so the clone appears at the bottom of the
 * list. The flash message carries the new line_no for orientation.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

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
$itemId   = (int) ($_POST['item_id']  ?? 0);
$quote    = qb_load_quote_or_404($quoteId, $clientId);
qb_require_quote_access($quote, $user, current_user_permissions());

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked. Reopen it to duplicate blinds.'
    );
}

$pdo = db();
$pdo->beginTransaction();
try {
    // Source row — ownership check via quote_id.
    $st = $pdo->prepare(
        'SELECT * FROM quote_items WHERE id = ? AND quote_id = ? LIMIT 1'
    );
    $st->execute([$itemId, $quoteId]);
    $src = $st->fetch();
    if (!$src) {
        throw new RuntimeException('Source blind not found.');
    }

    // Next line_no — appended to the bottom.
    $lnSt = $pdo->prepare(
        'SELECT COALESCE(MAX(line_no), 0) + 1 FROM quote_items WHERE quote_id = ?'
    );
    $lnSt->execute([$quoteId]);
    $nextLineNo = (int) $lnSt->fetchColumn();

    // Clone the row. Every column copies straight across except id and
    // line_no — the user will tweak size + re-save through the normal
    // update_item flow which re-prices via the engine.
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
           (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $quoteId, $nextLineNo,
        $src['product_id'], $src['product_name_snapshot'],
        $src['system_id'],  $src['system_name_snapshot'],
        $src['option_id'],
        $src['fabric_band_snapshot'], $src['fabric_supplier_snapshot'], $src['fabric_name_snapshot'],
        $src['fabric_colour_snapshot'], $src['fabric_code_snapshot'],
        $src['room_name'],
        $src['width_mm'], $src['drop_mm'], $src['width_matrix_mm'], $src['drop_matrix_mm'],
        $src['quantity'],
        $src['price_table_id'], $src['price_table_row_id'],
        $src['base_price'], $src['extras_total'], $src['subtotal_per_blind'],
        $src['markup_percent'], $src['discount_percent'],
        $src['sell_price'], $src['line_total'],
        $src['notes'],
    ]);
    $newItemId = (int) $pdo->lastInsertId();

    // Copy the source's selected extras so the clone keeps the same
    // option choices. mode + amount_applied carry through verbatim
    // because we're not re-pricing yet.
    $exSt = $pdo->prepare(
        'SELECT product_extra_id, extra_name_snapshot,
                product_extra_choice_id, choice_label_snapshot,
                mode, amount_applied
           FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id'
    );
    $exSt->execute([$itemId]);
    $insE = $pdo->prepare(
        'INSERT INTO quote_item_extras
           (quote_item_id,
            product_extra_id, extra_name_snapshot,
            product_extra_choice_id, choice_label_snapshot,
            mode, amount_applied)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($exSt->fetchAll(PDO::FETCH_ASSOC) as $ex) {
        $insE->execute([
            $newItemId,
            $ex['product_extra_id'], $ex['extra_name_snapshot'],
            $ex['product_extra_choice_id'], $ex['choice_label_snapshot'],
            $ex['mode'], $ex['amount_applied'],
        ]);
    }

    qb_recompute_totals($quoteId);
    $pdo->commit();

    // Land in edit mode for the new clone, jumped to the form anchor
    // so the user can immediately tweak the size and Save changes.
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId
            . '&edit_item=' . $newItemId . '#add-line',
        'success',
        'Blind ' . (int) $src['line_no'] . ' duplicated as #' . $nextLineNo
            . '. Adjust the size (or anything else) and save.'
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Could not duplicate: ' . $e->getMessage()
    );
}
