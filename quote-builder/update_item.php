<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../_partials/pricing_engine.php';
require __DIR__ . '/../_partials/price_table_parser.php';
require __DIR__ . '/../_partials/units.php';

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

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked (status: ' . $quote['status'] . '). Reopen it to edit blinds.'
    );
}

// Ownership check — the item must belong to this quote.
$ownerSt = db()->prepare(
    'SELECT id, line_no FROM quote_items WHERE id = ? AND quote_id = ? LIMIT 1'
);
$ownerSt->execute([$itemId, $quoteId]);
$existing = $ownerSt->fetch();
if (!$existing) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'That blind no longer exists on this quote.'
    );
}

// Parse free-text width / drop using the shared dimension parser. Same as
// add_item.php — the form sends raw input strings.
$unit     = effective_unit($quote['measurement_unit'] ?? null, db(), $clientId);
$widthRaw = (string) ($_POST['width'] ?? '');
$dropRaw  = (string) ($_POST['drop']  ?? '');
$widthMm  = ptp_parse_dimension($widthRaw, $unit);
$dropMm   = ptp_parse_dimension($dropRaw, $unit);

// Blank width → 0 (per-slat) / blank drop → 0 (width-only); the engine
// decides which are required. Non-blank unparseable values are still errors.
if ($widthMm === null) {
    if (trim($widthRaw) === '') {
        $widthMm = 0;
    } else {
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId . '&edit_item=' . $itemId . '#add-line',
            'error',
            'Could not read width "' . $widthRaw . '".'
        );
    }
}
if ($dropMm === null) {
    if (trim($dropRaw) === '') {
        $dropMm = 0;
    } else {
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId . '&edit_item=' . $itemId . '#add-line',
            'error',
            'Could not read drop "' . $dropRaw . '".'
        );
    }
}

// Same parsing as add_item.php — handles both single-pick
// (extras[N][choice_id]) and multi-pick (extras[N][choice_ids][])
// options. See add_item.php for the full comment.
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
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId . '&edit_item=' . $itemId . '#add-line',
            'error',
            $priced['error']
        );
    }

    $room = trim((string) ($_POST['room_name'] ?? ''));
    $note = trim((string) ($_POST['notes']     ?? ''));

    // Re-snapshot every catalogue field — the user may have changed product
    // / system / fabric, so the previous snapshots are stale.
    // cost_price_snapshot + extras_cost_snapshot get re-frozen on every
    // edit, same as the price snapshots, so they always reflect the
    // CURRENT product cost at the moment of last edit (not at original
    // creation). That's the right behaviour: if you fix a wrong line
    // item, the cost numbers re-snapshot to match.
    $upd = $pdo->prepare(
        'UPDATE quote_items
            SET product_id               = ?,
                product_name_snapshot    = ?,
                system_id                = ?,
                system_name_snapshot     = ?,
                option_id                = ?,
                fabric_band_snapshot     = ?,
                fabric_supplier_snapshot = ?,
                fabric_name_snapshot     = ?,
                fabric_colour_snapshot   = ?,
                fabric_code_snapshot     = ?,
                room_name                = ?,
                width_mm                 = ?,
                drop_mm                  = ?,
                width_matrix_mm          = ?,
                drop_matrix_mm           = ?,
                quantity                 = ?,
                price_table_id           = ?,
                price_table_row_id       = ?,
                base_price               = ?,
                cost_price_snapshot      = ?,
                extras_cost_snapshot     = ?,
                extras_total             = ?,
                subtotal_per_blind       = ?,
                markup_percent           = ?,
                discount_percent         = ?,
                sell_price               = ?,
                line_total               = ?,
                notes                    = ?
          WHERE id = ? AND quote_id = ?'
    );
    $upd->execute([
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
        $itemId, $quoteId,
    ]);

    // Replace extras: easier than diff'ing — drop them all and reinsert.
    $pdo->prepare('DELETE FROM quote_item_extras WHERE quote_item_id = ?')
        ->execute([$itemId]);

    // Same try-fallback as add_item.php — user_value column may not
    // exist pre-migration.
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
                    $itemId,
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
                    $itemId,
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

    $msg = 'Blind ' . (int) $existing['line_no']
         . ' updated (' . qb_fmt_money($priced['line_total']) . ').';
    if (!empty($priced['rounded_up'])) {
        $msg .= ' Rounded up to ' . qb_fmt_mm((int) $priced['matrix_width_mm'])
              . ' × ' . qb_fmt_mm((int) $priced['matrix_drop_mm']) . ' cell.';
    }
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'success', $msg);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId . '&edit_item=' . $itemId . '#add-line',
        'error',
        'Could not save blind: ' . $e->getMessage()
    );
}
