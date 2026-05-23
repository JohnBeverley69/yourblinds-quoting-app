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

// Selected extras: each entry has extra_id plus either:
//   - choice_id (scalar)  — single-pick option (the historical shape)
//   - choice_ids[] (array) — multi-pick option, one record fanned out
//                            per ticked checkbox
// extras[N][user_value] is optional (length-input extras only). For
// multi-pick options, the same user_value applies to every fanned-out
// record (the spec value belongs to the extra, not each choice).
$extras = [];
if (isset($_POST['extras']) && is_array($_POST['extras'])) {
    foreach ($_POST['extras'] as $e) {
        if (!is_array($e)) continue;
        $eid = (int) ($e['extra_id'] ?? 0);
        if ($eid <= 0) continue;

        $uv  = $e['user_value'] ?? null;
        $uvFloat = ($uv !== null && $uv !== '' && is_numeric($uv) && (float) $uv > 0)
            ? (float) $uv : null;

        $mkRow = static function (int $eid, int $cid) use ($uvFloat): array {
            $row = ['extra_id' => $eid, 'choice_id' => $cid];
            if ($uvFloat !== null) $row['user_value'] = $uvFloat;
            return $row;
        };

        if (isset($e['choice_ids']) && is_array($e['choice_ids'])) {
            foreach ($e['choice_ids'] as $rawCid) {
                $cid = (int) $rawCid;
                if ($cid > 0) $extras[] = $mkRow($eid, $cid);
            }
        } else {
            $cid = (int) ($e['choice_id'] ?? 0);
            if ($cid > 0) $extras[] = $mkRow($eid, $cid);
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

    // cost_price_snapshot + extras_cost_snapshot freeze the per-blind
    // wholesale cost at save-time, so historic gross-profit numbers
    // stay correct if the admin edits products/fabrics later. NULL on
    // any underlying cost_price column → 0 here; tenants see no
    // profit erosion until they fill cost data in.
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
           base_price, cost_price_snapshot, extras_cost_snapshot,
           extras_total, subtotal_per_blind,
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
        $priced['base_price'], $priced['cost_price_per_blind'] ?? 0, $priced['extras_cost_total'] ?? 0,
        $priced['extras_total'], $priced['subtotal_per_blind'],
        $priced['markup_percent'], $priced['discount_percent'],
        $priced['sell_price'], $priced['line_total'],
        $note !== '' ? $note : null,
    ]);
    $newItemId = (int) $pdo->lastInsertId();

    // Insert one row per applied extra. cost_snapshot freezes the
    // wholesale cost. user_value snapshots the user-typed length / spec
    // (NULL when the extra doesn't have a length_input_label, or when
    // nothing was typed). Try-fallback so this still works pre-
    // migrate_extra_length_input.php.
    if (!empty($priced['extras_applied'])) {
        try {
            $insE = $pdo->prepare(
                'INSERT INTO quote_item_extras
                   (quote_item_id,
                    product_extra_id, extra_name_snapshot,
                    product_extra_choice_id, choice_label_snapshot,
                    mode, amount_applied, cost_snapshot, user_value)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($priced['extras_applied'] as $ex) {
                $insE->execute([
                    $newItemId,
                    $ex['extra_id'], $ex['extra_name'],
                    $ex['choice_id'], $ex['choice_label'],
                    $ex['mode'], $ex['amount_applied'],
                    $ex['cost_snapshot'] ?? 0,
                    $ex['user_value']    ?? null,
                ]);
            }
        } catch (Throwable $e) {
            $insE = $pdo->prepare(
                'INSERT INTO quote_item_extras
                   (quote_item_id,
                    product_extra_id, extra_name_snapshot,
                    product_extra_choice_id, choice_label_snapshot,
                    mode, amount_applied, cost_snapshot)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($priced['extras_applied'] as $ex) {
                $insE->execute([
                    $newItemId,
                    $ex['extra_id'], $ex['extra_name'],
                    $ex['choice_id'], $ex['choice_label'],
                    $ex['mode'], $ex['amount_applied'],
                    $ex['cost_snapshot'] ?? 0,
                ]);
            }
        }
    }

    qb_recompute_totals($quoteId);
    $pdo->commit();

    $msg = 'Blind ' . $nextLineNo . ' added (' . qb_fmt_money($priced['line_total']) . ').';
    if (!empty($priced['rounded_up'])) {
        $msg .= ' Rounded up to ' . qb_fmt_mm((int) $priced['matrix_width_mm'])
              . ' × ' . qb_fmt_mm((int) $priced['matrix_drop_mm']) . ' cell.';
    }
    // The form has two submit buttons:
    //   "Add blind"          → next_action=more  → land back on Add-line
    //   "Add blind & finish" → next_action=stop  → land at top of editor
    // so the trade user can either keep adding or pop up to the items
    // table for a final review.
    $nextAction = (string) ($_POST['next_action'] ?? 'more');
    $anchor     = $nextAction === 'stop' ? '' : '#add-line';
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId . $anchor, 'success', $msg);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId . '#add-line', 'error', 'Could not add blind: ' . $e->getMessage());
}
