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

requireFactory();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /factory/incoming-orders.php'); exit; }
csrf_check();

$pdo    = db();
$MASTER = factory_client_id();
$qid    = (int) ($_POST['quote_id'] ?? 0);

$backEdit = '/factory/edit-order.php?order=' . $qid;
$fail = static function (string $msg) use ($backEdit) { $_SESSION['flash_error'] = $msg; header('Location: ' . $backEdit); exit; };

// Order must exist and carry Beverley lines.
$ord = $pdo->prepare('SELECT id, client_id FROM quotes WHERE id = ? LIMIT 1');
$ord->execute([$qid]);
$order = $ord->fetch(PDO::FETCH_ASSOC);
if (!$order) { $fail('Order not found.'); }
$clientId = (int) $order['client_id'];

// The order's own Beverley item ids (the only rows we may write to).
$vi = $pdo->prepare(
    'SELECT qi.id FROM quote_items qi JOIN products p ON p.id = qi.product_id
      WHERE qi.quote_id = ? AND p.source_client_id = ?'
);
$vi->execute([$qid, $MASTER]);
$validItems = array_map('intval', $vi->fetchAll(PDO::FETCH_COLUMN));
$validItemSet = array_flip($validItems);

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
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM quote_item_extras WHERE quote_item_id IN (SELECT id FROM quote_items WHERE quote_id = ?)')->execute([$qid]);
        $pdo->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$qid]);
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
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM quote_item_extras WHERE quote_item_id = ?')->execute([$iid]);
        $pdo->prepare('DELETE FROM quote_items WHERE id = ?')->execute([$iid]);
        // Renumber the remaining Beverley lines.
        $rs = $pdo->prepare('SELECT qi.id FROM quote_items qi JOIN products p ON p.id = qi.product_id
                              WHERE qi.quote_id = ? AND p.source_client_id = ? ORDER BY qi.line_no, qi.id');
        $rs->execute([$qid, $MASTER]);
        $n = 0;
        $upd = $pdo->prepare('UPDATE quote_items SET line_no = ? WHERE id = ?');
        foreach ($rs->fetchAll(PDO::FETCH_COLUMN) as $rid) { $upd->execute([++$n, (int) $rid]); }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $fail('Could not delete blind: ' . $e->getMessage()); }
    $_SESSION['flash_success'] = 'Blind deleted.';
    header('Location: ' . $backEdit); exit;
}

// ---- Add a blind (clone the last) ------------------------------------------
if (isset($_POST['add_item'])) {
    if (!$validItems) { $fail('Nothing to copy from.'); }
    try {
        $lastId = (int) end($validItems);
        // Highest line among Beverley lines.
        $mx = $pdo->prepare('SELECT COALESCE(MAX(line_no),0) FROM quote_items qi JOIN products p ON p.id = qi.product_id WHERE qi.quote_id = ? AND p.source_client_id = ?');
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
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $fail('Could not add blind: ' . $e->getMessage()); }
    $_SESSION['flash_success'] = 'Blind added — edit it below.';
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

    // Item product ids — a picked fabric must belong to the item's own product.
    $itemProduct = [];
    if ($validItems) {
        $ph2 = implode(',', array_fill(0, count($validItems), '?'));
        $ips = $pdo->prepare("SELECT id, product_id FROM quote_items WHERE id IN ($ph2)");
        $ips->execute($validItems);
        foreach ($ips->fetchAll(PDO::FETCH_ASSOC) as $r) $itemProduct[(int) $r['id']] = (int) $r['product_id'];
    }

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

        // Fabric: the picker posts the chosen product_options id; re-snapshot from it.
        $optId = (int) ($_POST['opt_fabric'][$iid] ?? 0);
        if ($optId > 0 && isset($itemProduct[$iid])) {
            $fabLookup->execute([$optId, $clientId, $itemProduct[$iid]]);
            $fab = $fabLookup->fetch(PDO::FETCH_ASSOC);
            if ($fab) {
                $updFabric->execute([$optId, $fab['band_code'], $fab['supplier_name'], $fab['name'], $fab['colour'], $fab['code'], $iid]);
            }
        }
    }

    // Options: opt[<extra_id>] = chosen label; uval[<extra_id>] = length number.
    // Only extras that belong to this order's Beverley items may be touched.
    if ($validItems) {
        $ph = implode(',', array_fill(0, count($validItems), '?'));
        $er = $pdo->prepare("SELECT id, product_extra_id FROM quote_item_extras WHERE quote_item_id IN ($ph)");
        $er->execute($validItems);
        $extraProduct = [];   // extra_row_id => product_extra_id
        foreach ($er->fetchAll(PDO::FETCH_ASSOC) as $r) $extraProduct[(int) $r['id']] = (int) $r['product_extra_id'];

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
        }
        foreach ((array) ($_POST['uval'] ?? []) as $exId => $val) {
            $exId = (int) $exId;
            if (!isset($extraProduct[$exId])) continue;
            $val = trim((string) $val);
            $updVal->execute([$val === '' ? null : (float) $val, $exId]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $fail('Could not save: ' . $e->getMessage());
}

$_SESSION['flash_success'] = 'Order saved.';
header('Location: ' . $backEdit);
exit;
