<?php
declare(strict_types=1);

/**
 * Factory · Save order (write handler for /factory/edit-order.php).
 *
 * One form, several submit buttons: `save` (apply all field edits), `add_item`
 * (clone the last blind), `del_item=<id>` (remove a blind), `del_order` (remove
 * the whole order). Only ever touches the order's own Beverley lines + extras.
 * The worksheet reads the order live, so edits flow through with no re-sync.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../quote-builder/_helpers.php';      // qb_reprice_stored_line / qb_recompute_totals
require_once __DIR__ . '/../_partials/pricing_engine.php';    // pe_calculate_item (same engine as the quote builder)
require_once __DIR__ . '/../_partials/order_stage.php';       // os_line_edit_lock / recompute_order_stage (+ blind_jobs)

requireFactory();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /factory/incoming-orders.php'); exit; }
csrf_check();

$pdo    = db();
$MASTER = current_factory_id();
$qid    = (int) ($_POST['quote_id'] ?? 0);

$backEdit = '/factory/edit-order.php?order=' . $qid;
$fail = static function (string $msg) use ($backEdit) { $_SESSION['flash_error'] = $msg; header('Location: ' . $backEdit); exit; };

// ---- Exactly one action, please -------------------------------------------
// A browser sends the name of the ONE submit button that was pressed. Anything
// posting more than one of these did not come from a person pressing a button:
// it built the request from the form's fields, which include every button, and
// del_order is checked first. That is precisely how a whole order was deleted
// by something that only meant to set a customer reference.
//
// This costs a real submit nothing — it can never carry two — and it turns that
// accident into a refusal instead of a deletion.
$posted = array_values(array_filter(
    ['save', 'add_item', 'del_item', 'del_order'],
    static fn (string $k): bool => isset($_POST[$k])
));
if (count($posted) > 1) {
    $fail('That request asked for ' . implode(' and ', $posted) . ' at once, so nothing was done. '
        . 'A form sends one button at a time — this looks like an automated post rather than a click.');
}

// Order must exist and carry Beverley lines.
$ord = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$ord->execute([$qid]);
$order = $ord->fetch(PDO::FETCH_ASSOC);
if (!$order) { $fail('Order not found.'); }
$clientId  = (int) $order['client_id'];
$accountId = (int) ($order['account_client_id'] ?? 0);   // factory-raised account order → price with the account's discount

// The order's own Beverley item ids (the only rows we may write to).
$vi = $pdo->prepare(
    'SELECT qi.id FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?'
);
$vi->execute([$qid, $MASTER]);
$validItems = array_map('intval', $vi->fetchAll(PDO::FETCH_COLUMN));
$validItemSet = array_flip($validItems);

// Enforce the second half of the contract above. The quotes lookup only proves
// the order EXISTS — it is not scoped to this factory, so without this an id
// typed into quote_id reaches another tenant's order. del_item checked itself,
// but del_order and the header/due-date save below did not: they took $qid raw.
// One guard here covers every branch. Mirrors the ownership test the sibling
// handlers already do (boughtin-received.php, set-status.php, blind-action.php).
if (!$validItems) { $fail("That order isn't yours to make."); }

// Only a PLACED order is the factory's. A tenant's draft / sent / accepted quote
// carries factory lines too, but it's still theirs to change — not ours.
if (!in_array((string) ($order['status'] ?? ''), os_placed_statuses(), true)) {
    $fail("That order hasn't been placed yet, so it can't be edited from the factory.");
}

// Dispatched / invoiced orders: the lines are what was delivered and billed, so
// they're locked (references can still be corrected). Checked per branch below.
$lineLock = os_line_edit_lock($pdo, $qid);
$lockMsg  = $lineLock !== ''
    ? "The blinds on this order can't be changed — {$lineLock}. Raise a credit note or a new order instead."
    : '';

/**
 * After any line change: re-derive the floor jobs (added lines/units reach the
 * floor, removed ones leave it) and the fulfilment stage. Best-effort.
 */
$afterLineChange = static function () use ($pdo, $qid, $MASTER): void {
    bj_resync_order($pdo, $qid, $MASTER);
    recompute_order_stage($pdo, $qid);
};

// Generic clone helpers (mirror the dummy-order seed).
$freshTokens = static function (array $row): array {
    foreach ($row as $k => $v) {
        if ($v !== null && stripos((string) $k, 'token') !== false) $row[$k] = bin2hex(random_bytes(32));
    }
    return $row;
};
$insertRow = static function (PDO $pdo, string $table, array $row): int {
    unset($row['id']);
    $cols = array_keys($row);
    $sql  = 'INSERT INTO `' . $table . '` (' . implode(',', array_map(static fn ($c) => "`$c`", $cols)) . ') VALUES ('
          . implode(',', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
    return (int) $pdo->lastInsertId();
};

// ---- Delete whole order ----------------------------------------------------
if (isset($_POST['del_order'])) {
    // Never delete an order that has paperwork or money behind it: a dispatched
    // order was deleted in the go-live test and left a delivery note that could
    // never be invoiced. Those need a credit note / cancellation, not a delete.
    foreach ([
        'SELECT 1 FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND status <> \'cancelled\' LIMIT 1'
            => 'it has a delivery note',
        'SELECT 1 FROM factory_ar_invoice_orders WHERE quote_id = ? LIMIT 1' => 'it has been invoiced',
        'SELECT 1 FROM payments WHERE quote_id = ? LIMIT 1'                  => 'payments are recorded against it',
    ] as $sql => $why) {
        try {
            $chk = $pdo->prepare($sql);
            $chk->execute([$qid]);
            $hit = (bool) $chk->fetchColumn();
        } catch (Throwable $e) { $hit = false; /* table not migrated on this install */ }
        if ($hit) { $fail('This order can\'t be deleted — ' . $why . '.'); }
    }
    try {
        $pdo->beginTransaction();
        // The factory's own job rows for the order go with it (they were left orphaned).
        foreach (['factory_blind_jobs', 'factory_jobs'] as $t) {
            try { $pdo->prepare("DELETE FROM `$t` WHERE quote_id = ?")->execute([$qid]); } catch (Throwable $e) { /* not migrated */ }
        }
        $pdo->prepare('DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id = ?)')->execute([$qid]);
        $pdo->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$qid]);
        // Remove the order's calendar appointments (e.g. the pending fitting) so
        // deleting the order doesn't leave phantom fittings on the account's calendar.
        $pdo->prepare('DELETE FROM appointments WHERE quote_id = ?')->execute([$qid]);
        $pdo->prepare('DELETE FROM quotes WHERE id = ?')->execute([$qid]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $fail('Could not delete order: ' . $e->getMessage()); }
    $_SESSION['flash_success'] = 'Order deleted.';
    header('Location: /factory/incoming-orders.php'); exit;
}

// ---- Delete one blind ------------------------------------------------------
if (isset($_POST['del_item'])) {
    $iid = (int) $_POST['del_item'];
    if (!isset($validItemSet[$iid])) { $fail('That blind is not part of this order.'); }
    if ($lockMsg !== '') { $fail($lockMsg); }
    // A bought-in blind already ordered from its supplier: the delete still goes
    // ahead, but the supplier has to be told (warned after the save).
    $delWarn = '';
    require_once __DIR__ . '/../_partials/factory_boughtin.php';
    $pidSt = $pdo->prepare('SELECT product_id, line_no FROM quote_items WHERE id = ?');
    $pidSt->execute([$iid]);
    $delRow = $pidSt->fetch(PDO::FETCH_ASSOC) ?: ['product_id' => 0, 'line_no' => 0];
    $delSup = bought_in_supplier_for_product($pdo, (int) $delRow['product_id']);
    if ($delSup !== '' && in_array(strtolower($delSup), array_map('strtolower', factory_boughtin_ordered_names($pdo, $qid, $MASTER)), true)) {
        $delWarn = 'Heads up: blind ' . (int) $delRow['line_no'] . ' was already ordered from ' . $delSup . ' — cancel it with them.';
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM quote_item_extras WHERE quote_item_id = ?')->execute([$iid]);
        $pdo->prepare('DELETE FROM quote_items WHERE id = ?')->execute([$iid]);
        // Renumber the remaining Beverley lines.
        $rs = $pdo->prepare('SELECT qi.id FROM quote_items qi JOIN products p ON p.id = qi.product_id
                              WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ? ORDER BY qi.line_no, qi.id');
        $rs->execute([$qid, $MASTER]);
        $n = 0;
        $upd = $pdo->prepare('UPDATE quote_items SET line_no = ? WHERE id = ?');
        foreach ($rs->fetchAll(PDO::FETCH_COLUMN) as $rid) { $upd->execute([++$n, (int) $rid]); }
        // The order's money must follow its lines (the totals were left stale).
        qb_reconcile_fascia_groups($pdo, $qid, $clientId, $accountId);
        qb_recompute_totals($qid);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $fail('Could not delete blind: ' . $e->getMessage()); }
    $afterLineChange();
    $_SESSION['flash_success'] = 'Blind deleted — order total updated.';
    if ($delWarn !== '') $_SESSION['flash_error'] = $delWarn;
    header('Location: ' . $backEdit); exit;
}

// ---- Add a blind (clone the last) ------------------------------------------
if (isset($_POST['add_item'])) {
    if (!$validItems) { $fail('Nothing to copy from.'); }
    if ($lockMsg !== '') { $fail($lockMsg); }
    try {
        $lastId = (int) end($validItems);
        // Highest line among Beverley lines.
        $mx = $pdo->prepare('SELECT COALESCE(MAX(line_no),0) FROM quote_items qi JOIN products p ON p.id = qi.product_id WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?');
        $mx->execute([$qid, $MASTER]);
        $nextLine = (int) $mx->fetchColumn() + 1;
        $srcRow = $pdo->query('SELECT * FROM quote_items WHERE id = ' . $lastId)->fetch(PDO::FETCH_ASSOC);
        $srcRow['line_no'] = $nextLine;
        $srcRow = $freshTokens($srcRow);
        $pdo->beginTransaction();
        $newId = $insertRow($pdo, 'quote_items', $srcRow);
        foreach ($pdo->query('SELECT * FROM quote_item_extras WHERE quote_item_id = ' . $lastId)->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            $ex['quote_item_id'] = $newId;
            $insertRow($pdo, 'quote_item_extras', $freshTokens($ex));
        }
        // Price the new line through the engine and bring the order totals up to
        // date (they were left stale). If the engine can't price it, the copy
        // keeps the source blind's price — which is what it is a copy of.
        qb_reprice_stored_line($pdo, $newId, $clientId, $accountId);
        qb_reconcile_fascia_groups($pdo, $qid, $clientId, $accountId);
        qb_recompute_totals($qid);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $fail('Could not add blind: ' . $e->getMessage()); }
    $afterLineChange();
    $_SESSION['flash_success'] = 'Blind added — edit it below. Order total updated.';
    header('Location: ' . $backEdit); exit;
}

// ---- Save all field edits --------------------------------------------------
try {
    $pdo->beginTransaction();

    // Order-level references.
    $pdo->prepare('UPDATE quotes SET customer_reference = ?, additional_reference = ? WHERE id = ?')
        ->execute([
            mb_substr(trim((string) ($_POST['customer_reference'] ?? '')), 0, 120),
            mb_substr(trim((string) ($_POST['additional_reference'] ?? '')), 0, 120),
            $qid,
        ]);

    // Due date: an explicit human override of the date stamped at placement.
    // Only touched when the field was actually rendered on the form.
    if (array_key_exists('due_date', $_POST)) {
        require_once __DIR__ . '/../_partials/due_dates.php';
        if (dd_ready($pdo)) dd_set_due($pdo, $qid, (string) $_POST['due_date']);
    }

    // Dispatched / invoiced: keep the reference / due-date fix, refuse the lines.
    if ($lockMsg !== '') {
        $pdo->commit();
        $_SESSION['flash_success'] = 'References saved.';
        $fail($lockMsg . ' Blind changes were not saved.');
    }

    // Item product ids — a picked fabric must belong to the item's own product —
    // plus each line's price-driving inputs as they stand BEFORE this save, so
    // only lines that actually changed are re-priced (an untouched line keeps the
    // price it was sold at).
    $itemProduct = [];
    $before      = [];   // item id => [w, d, qty, system_id, option_id]
    if ($validItems) {
        $ph2 = implode(',', array_fill(0, count($validItems), '?'));
        $ips = $pdo->prepare("SELECT id, product_id, width_mm, drop_mm, quantity, system_id, option_id FROM quote_items WHERE id IN ($ph2)");
        $ips->execute($validItems);
        foreach ($ips->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itemProduct[(int) $r['id']] = (int) $r['product_id'];
            $before[(int) $r['id']] = [(int) $r['width_mm'], (int) $r['drop_mm'], (int) $r['quantity'], (int) $r['system_id'], (int) $r['option_id']];
        }
    }
    $dirty = [];   // item id => true when a price-driving input changed

    $sysName   = $pdo->prepare('SELECT name FROM product_systems WHERE id = ? LIMIT 1');
    $updItem   = $pdo->prepare('UPDATE quote_items SET width_mm = ?, drop_mm = ?, quantity = ?, room_name = ?, notes = ?, system_id = ?, system_name_snapshot = ? WHERE id = ?');
    $fabLookup = $pdo->prepare('SELECT band_code, supplier_name, name, colour, code FROM product_options WHERE id = ? AND client_id = ? AND product_id = ? LIMIT 1');
    $updFabric = $pdo->prepare('UPDATE quote_items SET option_id = ?, fabric_band_snapshot = ?, fabric_supplier_snapshot = ?, fabric_name_snapshot = ?, fabric_colour_snapshot = ?, fabric_code_snapshot = ? WHERE id = ?');

    foreach ($validItems as $iid) {
        $w   = max(1, (int) ($_POST['w'][$iid] ?? 0));
        $d   = max(1, (int) ($_POST['d'][$iid] ?? 0));
        $qty = max(1, (int) ($_POST['qty'][$iid] ?? 1));
        $room = mb_substr(trim((string) ($_POST['room'][$iid] ?? '')), 0, 80);
        $note = mb_substr(trim((string) ($_POST['notes'][$iid] ?? '')), 0, 255);

        // System: dropdown posts the system id; text fallback posts a name.
        $sid = isset($_POST['sys'][$iid]) ? (int) $_POST['sys'][$iid] : 0;
        if ($sid > 0) {
            $sysName->execute([$sid]);
            $sname = (string) ($sysName->fetchColumn() ?: '');
        } else {
            $sname = mb_substr(trim((string) ($_POST['sysname'][$iid] ?? '')), 0, 120);
            $sid   = 0;
        }

        $updItem->execute([$w, $d, $qty, $room, $note, $sid > 0 ? $sid : null, $sname, $iid]);
        $b = $before[$iid] ?? null;
        if ($b === null || $b[0] !== $w || $b[1] !== $d || $b[2] !== $qty || $b[3] !== $sid) $dirty[$iid] = true;

        // Fabric: the picker posts the chosen product_options id; re-snapshot from it.
        $optId = (int) ($_POST['opt_fabric'][$iid] ?? 0);
        if ($optId > 0 && isset($itemProduct[$iid])) {
            $fabLookup->execute([$optId, $clientId, $itemProduct[$iid]]);
            $fab = $fabLookup->fetch(PDO::FETCH_ASSOC);
            if ($fab) {
                $updFabric->execute([$optId, $fab['band_code'], $fab['supplier_name'], $fab['name'], $fab['colour'], $fab['code'], $iid]);
                if ($b === null || $b[4] !== $optId) $dirty[$iid] = true;
            }
        }
    }

    // Options: opt[<extra_id>] = chosen label; uval[<extra_id>] = length number.
    // Only extras that belong to this order's Beverley items may be touched.
    if ($validItems) {
        $ph = implode(',', array_fill(0, count($validItems), '?'));
        $er = $pdo->prepare("SELECT id, quote_item_id, product_extra_id, choice_label_snapshot, user_value FROM quote_item_extras WHERE quote_item_id IN ($ph)");
        $er->execute($validItems);
        $extraProduct = [];   // extra_row_id => product_extra_id
        $extraItem    = [];   // extra_row_id => quote_item_id
        $extraBefore  = [];   // extra_row_id => [label, value] before this save
        foreach ($er->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $extraProduct[(int) $r['id']] = (int) $r['product_extra_id'];
            $extraItem[(int) $r['id']]    = (int) $r['quote_item_id'];
            $extraBefore[(int) $r['id']]  = [
                trim((string) ($r['choice_label_snapshot'] ?? '')),
                $r['user_value'] === null ? null : (float) $r['user_value'],
            ];
        }

        $choiceId = $pdo->prepare('SELECT id FROM product_extra_choices WHERE product_extra_id = ? AND label = ? LIMIT 1');
        $updLabel = $pdo->prepare('UPDATE quote_item_extras SET choice_label_snapshot = ?, product_extra_choice_id = ? WHERE id = ?');
        $updVal   = $pdo->prepare('UPDATE quote_item_extras SET user_value = ? WHERE id = ?');

        foreach ((array) ($_POST['opt'] ?? []) as $exId => $label) {
            $exId = (int) $exId;
            if (!isset($extraProduct[$exId])) continue;
            $label = mb_substr(trim((string) $label), 0, 190);
            $choiceId->execute([$extraProduct[$exId], $label]);
            $cid = (int) ($choiceId->fetchColumn() ?: 0);
            $updLabel->execute([$label, $cid > 0 ? $cid : null, $exId]);
            if (($extraBefore[$exId][0] ?? '') !== $label) $dirty[$extraItem[$exId]] = true;
        }
        foreach ((array) ($_POST['uval'] ?? []) as $exId => $val) {
            $exId = (int) $exId;
            if (!isset($extraProduct[$exId])) continue;
            $val = trim((string) $val);
            $newVal = $val === '' ? null : (float) $val;
            $updVal->execute([$newVal, $exId]);
            $oldVal = $extraBefore[$exId][1] ?? null;
            if ($oldVal !== $newVal && !($oldVal !== null && $newVal !== null && abs($oldVal - $newVal) < 0.0001)) {
                $dirty[$extraItem[$exId]] = true;
            }
        }
    }

    // Re-price every changed line through the quote builder's engine (with the
    // order's trade account, so the account discount still applies), then the
    // order totals. Before this, a qty 1→2 left line_total / the order total at
    // the old figure and the invoice worked out a negative line charge.
    $lineNo = $pdo->prepare('SELECT line_no FROM quote_items WHERE id = ?');
    foreach (array_keys($dirty) as $iid) {
        $err = qb_reprice_stored_line($pdo, (int) $iid, $clientId, $accountId);
        if ($err !== null) {
            $lineNo->execute([(int) $iid]);
            throw new RuntimeException('blind ' . (int) $lineNo->fetchColumn() . " couldn't be re-priced ({$err}). Nothing was changed");
        }
    }
    qb_reconcile_fascia_groups($pdo, $qid, $clientId, $accountId);
    qb_recompute_totals($qid);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $fail('Could not save: ' . $e->getMessage());
}

// Warn (don't block) when a changed line is bought-in and its supplier has
// already been sent the order: the supplier still has the OLD spec.
$warn = [];
if ($dirty) {
    require_once __DIR__ . '/../_partials/factory_boughtin.php';
    $orderedSup = array_map('strtolower', factory_boughtin_ordered_names($pdo, $qid, $MASTER));
    if ($orderedSup) {
        $li = $pdo->prepare('SELECT line_no, product_id FROM quote_items WHERE id = ?');
        foreach (array_keys($dirty) as $iid) {
            $li->execute([(int) $iid]);
            $row = $li->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $sup = bought_in_supplier_for_product($pdo, (int) $row['product_id']);
            if ($sup !== '' && in_array(strtolower($sup), $orderedSup, true)) {
                $warn[] = 'blind ' . (int) $row['line_no'] . ' (' . $sup . ')';
            }
        }
    }
}

if ($dirty) $afterLineChange();

$_SESSION['flash_success'] = 'Order saved.' . ($dirty ? ' ' . count($dirty) . ' blind' . (count($dirty) === 1 ? '' : 's') . ' re-priced — order total updated.' : '');
if ($warn) {
    $_SESSION['flash_error'] = 'Heads up: ' . implode(', ', $warn) . ' was already ordered from the supplier. '
        . 'The supplier still has the old details — contact them with the change.';
}
header('Location: ' . $backEdit);
exit;
