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
$quote    = qb_load_quote_or_404($quoteId, $clientId);
qb_require_quote_access($quote, $user, current_user_permissions());

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked (status: ' . $quote['status'] . '). Reopen it to add blinds.'
    );
}

// Parse free-text width / drop using the shared dimension parser.
$unit     = effective_unit($quote['measurement_unit'] ?? null, db(), $clientId);
$widthRaw = (string) ($_POST['width'] ?? '');
$dropRaw  = (string) ($_POST['drop']  ?? '');
$widthMm  = ptp_parse_dimension($widthRaw, $unit);
$dropMm   = ptp_parse_dimension($dropRaw, $unit);

// Roller "Multi blind": several blinds share one fascia. The form sends each
// blind's width in multi_fascia[widths][] (and disables the top Width field,
// which reads "multi blind"). Detect it up front so the placeholder width
// isn't treated as an error and the fan-out below can create one line each.
$multi = (isset($_POST['multi_fascia']) && is_array($_POST['multi_fascia'])) ? $_POST['multi_fascia'] : [];
$multiWidths = [];
$multiDrops  = [];   // parallel to $multiWidths; 0 = use the shared (top) drop
if (!empty($multi['active']) && !empty($multi['widths']) && is_array($multi['widths'])) {
    $dropsRaw = (isset($multi['drops']) && is_array($multi['drops'])) ? array_values($multi['drops']) : [];
    foreach (array_values($multi['widths']) as $idx => $w) {
        $wm = ptp_parse_dimension((string) $w, $unit);
        if ($wm === null || $wm <= 0) continue;
        $dm = ptp_parse_dimension((string) ($dropsRaw[$idx] ?? ''), $unit);
        $multiWidths[] = (int) $wm;
        $multiDrops[]  = ($dm !== null && $dm > 0) ? (int) $dm : 0;   // per-blind drop override
    }
}
$multiActive = count($multiWidths) >= 2;

// Blank width → 0 (per-slat products have none); blank drop → 0 (width-only
// products have none). The engine decides which are required. A non-blank
// unparseable value is still an error — except in multi-blind mode, where the
// top Width field is just the "multi blind" placeholder.
if ($widthMm === null) {
    if (trim($widthRaw) === '' || $multiActive) {
        $widthMm = 0;
    } else {
        qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Could not read width "' . $widthRaw . '".');
    }
}
if ($dropMm === null) {
    if (trim($dropRaw) === '') {
        $dropMm = 0;
    } else {
        qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Could not read drop "' . $dropRaw . '".');
    }
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

        // Per-choice typed values: extras[N][choice_user_values][<choice_id>].
        // Used by per-choice number inputs (migrate_choice_length_input.php).
        // A choice's own value wins over the group-level user_value.
        $choiceUv = (isset($e['choice_user_values']) && is_array($e['choice_user_values']))
            ? $e['choice_user_values'] : [];

        $mkRow = static function (int $eid, int $cid) use ($uvFloat, $choiceUv): array {
            $row = ['extra_id' => $eid, 'choice_id' => $cid];
            $pc  = $choiceUv[$cid] ?? null;
            $pcFloat = ($pc !== null && $pc !== '' && is_numeric($pc) && (float) $pc > 0)
                ? (float) $pc : null;
            $val = $pcFloat ?? $uvFloat;
            if ($val !== null) $row['user_value'] = $val;
            return $row;
        };

        if (isset($e['choice_ids']) && is_array($e['choice_ids'])) {
            foreach ($e['choice_ids'] as $rawCid) {
                $cid = (int) $rawCid;
                if ($cid > 0) $extras[] = $mkRow($eid, $cid);
            }
        } elseif (array_key_exists('choice_id', $e)) {
            // Single-pick dropdown — only counts when a choice was picked.
            $cid = (int) ($e['choice_id'] ?? 0);
            if ($cid > 0) $extras[] = $mkRow($eid, $cid);
        } else {
            // Number-only option: no choice_id submitted at all. Carry the
            // typed value through with no choice (choice_id 0).
            $row = ['extra_id' => $eid, 'choice_id' => 0];
            if ($uvFloat !== null) $row['user_value'] = $uvFloat;
            $extras[] = $row;
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
// Per-line markup/discount override (already converted to MARKUP by the
// client). Cost-viewers only — it's the trade margin. Numeric-or-skip.
$canCosts = ($user['role'] ?? '') === 'admin' || !empty(current_user_permissions()['can_view_costs']);
if ($canCosts) {
    if (isset($_POST['markup_override'])   && is_numeric($_POST['markup_override']))   $input['markup_override']   = (float) $_POST['markup_override'];
    if (isset($_POST['discount_override']) && is_numeric($_POST['discount_override'])) $input['discount_override'] = (float) $_POST['discount_override'];
}

$pdo = db();

$room  = trim((string) ($_POST['room_name'] ?? ''));
$note  = trim((string) ($_POST['notes']     ?? ''));

// Multi-blind fit check: the blinds must fit within the fascia. When an overall
// Fascia width has been typed, the sum of the blind widths must not exceed it.
if ($multiActive) {
    $meta = qb_resolve_fascia_meta($pdo, (int) $input['product_id']);
    $fasciaWidthMm = 0.0;
    if ($meta && $meta['fascia_width_extra'] > 0) {
        foreach ($extras as $row) {
            if ((int) ($row['extra_id'] ?? 0) === $meta['fascia_width_extra'] && (float) ($row['user_value'] ?? 0) > 0) {
                $fasciaWidthMm = (float) $row['user_value']; break;
            }
        }
    }
    $sumW = array_sum($multiWidths);
    if ($fasciaWidthMm > 0 && $sumW > $fasciaWidthMm + 0.5) {
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId . '#add-line', 'error',
            'These blinds won\'t fit: they total ' . qb_fmt_mm((int) $sumW) . ' but the fascia is only '
            . qb_fmt_mm((int) $fasciaWidthMm) . '. Reduce a blind width or widen the fascia.'
        );
    }
}

$pdo->beginTransaction();
try {
    // Price + insert one blind at a given width, tagged into an optional fascia
    // group. Returns [itemId, priced, lineNo]. Shared by the single-blind path
    // and the multi-blind fan-out so they stay identical. cost_price_snapshot +
    // extras_cost_snapshot freeze the per-blind wholesale cost at save-time.
    $insertLine = function (int $lineWidthMm, ?string $fasciaTag, int $dropOverrideMm = 0)
                    use ($pdo, $clientId, $quote, $quoteId, $input, $room, $note): array {
        $lineInput = $input;
        $lineInput['width_mm'] = $lineWidthMm;
        if ($dropOverrideMm > 0) $lineInput['drop_mm'] = $dropOverrideMm;   // per-blind drop override
        $priced = pe_calculate_item($pdo, $clientId, $lineInput, (int) ($quote['account_client_id'] ?? 0));
        if (isset($priced['error'])) throw new RuntimeException($priced['error']);

        $lnSt = $pdo->prepare('SELECT COALESCE(MAX(line_no), 0) + 1 FROM quote_items WHERE quote_id = ?');
        $lnSt->execute([$quoteId]);
        $nextLineNo = (int) $lnSt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO quote_items
              (quote_id, line_no,
               product_id, product_name_snapshot,
               system_id, system_name_snapshot,
               option_id,
               fabric_band_snapshot, fabric_supplier_snapshot, fabric_name_snapshot,
               fabric_colour_snapshot, fabric_code_snapshot,
               room_name, fascia_group,
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
               ?, ?,
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
            $room !== '' ? $room : null, $fasciaTag,
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
        qb_capture_line_wholesale($pdo, $newItemId, $priced);   // Phase 2A wholesale capture

        // One row per applied extra. Try-fallback so this still works pre-
        // migrate_extra_length_input.php (user_value column may be absent).
        if (!empty($priced['extras_applied'])) {
            try {
                $insE = $pdo->prepare(
                    'INSERT INTO quote_item_extras
                       (quote_item_id, product_extra_id, extra_name_snapshot,
                        product_extra_choice_id, choice_label_snapshot,
                        mode, amount_applied, cost_snapshot, user_value)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($priced['extras_applied'] as $ex) {
                    $insE->execute([
                        $newItemId, $ex['extra_id'], $ex['extra_name'],
                        $ex['choice_id'], $ex['choice_label'],
                        $ex['mode'], $ex['amount_applied'],
                        $ex['cost_snapshot'] ?? 0, $ex['user_value'] ?? null,
                    ]);
                    qb_capture_extra_wholesale($pdo, (int) $pdo->lastInsertId(), $ex);
                }
            } catch (Throwable $e) {
                $insE = $pdo->prepare(
                    'INSERT INTO quote_item_extras
                       (quote_item_id, product_extra_id, extra_name_snapshot,
                        product_extra_choice_id, choice_label_snapshot,
                        mode, amount_applied, cost_snapshot)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($priced['extras_applied'] as $ex) {
                    $insE->execute([
                        $newItemId, $ex['extra_id'], $ex['extra_name'],
                        $ex['choice_id'], $ex['choice_label'],
                        $ex['mode'], $ex['amount_applied'], $ex['cost_snapshot'] ?? 0,
                    ]);
                    qb_capture_extra_wholesale($pdo, (int) $pdo->lastInsertId(), $ex);
                }
            }
        }
        return [$newItemId, $priced, $nextLineNo];
    };

    if ($multiActive) {
        // Fan out: one grouped line per blind width, sharing the next free
        // fascia-group letter. qb_reconcile_fascia_groups then carries the
        // fascia once across the group (each blind still cut to its own width).
        $used = [];
        $ug = $pdo->prepare("SELECT DISTINCT fascia_group FROM quote_items WHERE quote_id = ? AND fascia_group IS NOT NULL AND fascia_group <> ''");
        $ug->execute([$quoteId]);
        foreach ($ug->fetchAll(PDO::FETCH_COLUMN) as $t) $used[strtoupper((string) $t)] = true;
        $tag = 'A';
        foreach (range('A', 'Z') as $L) { if (empty($used[$L])) { $tag = (string) $L; break; } }

        foreach ($multiWidths as $k => $w) { $insertLine($w, $tag, $multiDrops[$k] ?? 0); }
        $msg = count($multiWidths) . ' blinds added under one shared fascia (group ' . $tag . ').';
    } else {
        [$newItemId, $priced, $nextLineNo] = $insertLine($widthMm, null);
        $msg = 'Blind ' . $nextLineNo . ' added (' . qb_fmt_money($priced['line_total']) . ').';
        if (!empty($priced['rounded_up'])) {
            $msg .= ' Rounded up to ' . qb_fmt_mm((int) $priced['matrix_width_mm'])
                  . ' × ' . qb_fmt_mm((int) $priced['matrix_drop_mm']) . ' cell.';
        }
    }

    qb_reconcile_fascia_groups($pdo, $quoteId, $clientId, (int) ($quote['account_client_id'] ?? 0));
    qb_recompute_totals($quoteId);
    $pdo->commit();
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
