<?php
declare(strict_types=1);

/**
 * Factory bought-in status + auto-send.
 *
 * Tracks, per order, which bought-in suppliers have been ordered / received, and
 * (opt-in) auto-orders them from their suppliers the moment an order is placed.
 * The "ordered"/"received" rollups on quotes.supplier_ordered_at /
 * supplier_received_at only flip once EVERY bought-in supplier on the order is
 * done, so a mixed-supplier order can't read "ordered" while one is still pending.
 */

require_once __DIR__ . '/bought_in.php';
require_once __DIR__ . '/supplier_send.php';

/** Distinct MASTER supplier names for an order's bought-in lines (factory-owned). */
function factory_boughtin_supplier_names(PDO $pdo, int $quoteId, int $factoryId): array
{
    try {
        $st = $pdo->prepare(
            'SELECT DISTINCT mp.supplier_name
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
               ' . bought_in_master_join('p', 'mp') . '
              WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
                AND ' . bought_in_predicate('mp')
        );
        $st->execute([$quoteId, $factoryId]);
        return array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $st->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable $e) { return []; }
}

/** Supplier names already ordered by this factory for the quote (from the log). */
function factory_boughtin_ordered_names(PDO $pdo, int $quoteId, int $factoryId): array
{
    try {
        $st = $pdo->prepare('SELECT DISTINCT supplier_name FROM supplier_orders WHERE quote_id = ? AND ordered_by_factory_id = ?');
        $st->execute([$quoteId, $factoryId]);
        return array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $st->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable $e) { return []; }
}

/** Supplier names marked received (received_at set) for the quote. */
function factory_boughtin_received_names(PDO $pdo, int $quoteId, int $factoryId): array
{
    try {
        $st = $pdo->prepare('SELECT DISTINCT supplier_name FROM supplier_orders WHERE quote_id = ? AND ordered_by_factory_id = ? AND received_at IS NOT NULL');
        $st->execute([$quoteId, $factoryId]);
        return array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $st->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable $e) { return []; }
}

/**
 * Re-derive the order-level rollups from the log:
 *   supplier_ordered_at  set once every bought-in supplier has been ordered;
 *   supplier_received_at set once every bought-in supplier has been received.
 * Cleared back to NULL if a supplier is (re)added and no longer covered.
 */
function factory_boughtin_restamp(PDO $pdo, int $quoteId, int $factoryId): void
{
    $need     = factory_boughtin_supplier_names($pdo, $quoteId, $factoryId);
    if (!$need) return;   // no bought-in lines — nothing to track
    $ordered  = factory_boughtin_ordered_names($pdo, $quoteId, $factoryId);
    $received = factory_boughtin_received_names($pdo, $quoteId, $factoryId);
    $allOrdered  = !array_diff($need, $ordered);
    $allReceived = !array_diff($need, $received);
    try {
        $pdo->prepare('UPDATE quotes SET supplier_ordered_at = ?, supplier_received_at = ? WHERE id = ?')->execute([
            $allOrdered  ? date('Y-m-d H:i:s') : null,
            $allReceived ? date('Y-m-d H:i:s') : null,
            $quoteId,
        ]);
    } catch (Throwable $e) { /* columns absent — ignore */ }
}

/** True if the order has bought-in lines not yet all received (blocks dispatch). */
function factory_boughtin_awaiting(PDO $pdo, int $quoteId, int $factoryId): bool
{
    $need = factory_boughtin_supplier_names($pdo, $quoteId, $factoryId);
    if (!$need) return false;
    $received = factory_boughtin_received_names($pdo, $quoteId, $factoryId);
    return (bool) array_diff($need, $received);
}

/**
 * Auto-order the bought-in lines from their suppliers on placement — only when
 * the FACTORY has auto_send_suppliers ON. Idempotent (skips suppliers already in
 * the log). Best-effort + LOUD on failure: a supplier with no valid email is
 * left un-ordered (the "to order" pill stays), never silently dropped.
 */
function factory_autosend_suppliers(PDO $pdo, int $quoteId): void
{
    if ($quoteId <= 0) return;
    try {
        // Resolve the making factory (dominant product owner), like factory_notify.
        $fs = $pdo->prepare(
            "SELECT COALESCE(NULLIF(p.source_client_id,0), p.client_id) AS fac, COUNT(*) AS n
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id = ? GROUP BY fac ORDER BY n DESC LIMIT 1"
        );
        $fs->execute([$quoteId]);
        $factoryId = (int) ($fs->fetchColumn() ?: 0);
        if ($factoryId <= 0) return;

        // Flag on the factory?
        $on = 0;
        try {
            $s = $pdo->prepare('SELECT auto_send_suppliers FROM client_settings WHERE client_id = ? LIMIT 1');
            $s->execute([$factoryId]);
            $on = (int) ($s->fetchColumn() ?: 0);
        } catch (Throwable $e) { return; }
        if ($on !== 1) return;

        $need = factory_boughtin_supplier_names($pdo, $quoteId, $factoryId);
        if (!$need) return;
        $already = factory_boughtin_ordered_names($pdo, $quoteId, $factoryId);
        $todo = array_diff($need, $already);
        if (!$todo) return;

        $quote = $pdo->query('SELECT quote_number FROM quotes WHERE id = ' . (int) $quoteId . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];

        // Factory buyer identity + delivery + from + supplier emails.
        $client = $pdo->prepare('SELECT company_name, email, phone FROM clients WHERE id = ? LIMIT 1');
        $client->execute([$factoryId]); $client = $client->fetch() ?: [];
        $deliveryAddress = '';
        try { $d = $pdo->prepare('SELECT supplier_delivery_address FROM client_settings WHERE client_id = ? LIMIT 1'); $d->execute([$factoryId]); $deliveryAddress = (string) ($d->fetchColumn() ?: ''); } catch (Throwable $e) {}
        $fromName = (string) ($client['company_name'] ?? ''); $fromEmail = (string) ($client['email'] ?? '');
        try { $fsr = $pdo->prepare('SELECT email_from_name, reply_to_email FROM client_settings WHERE client_id = ? LIMIT 1'); $fsr->execute([$factoryId]); if ($r = $fsr->fetch()) { if (trim((string)($r['email_from_name']??''))!=='') $fromName=trim((string)$r['email_from_name']); if (trim((string)($r['reply_to_email']??''))!=='') $fromEmail=trim((string)$r['reply_to_email']); } } catch (Throwable $e) {}
        $mailOpts = [];
        if ($fromName !== '') { $mailOpts['from_name'] = $fromName; $mailOpts['reply_to_name'] = $fromName; }
        if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { $mailOpts['reply_to_email'] = $fromEmail; }
        $emailByName = []; $accountByName = [];
        try {
            $hasAcc = false; try { $hasAcc = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'account_number'")->fetchColumn() !== false; } catch (Throwable $e) {}
            $e2 = $pdo->prepare('SELECT name, email' . ($hasAcc ? ', account_number' : '') . ' FROM suppliers WHERE client_id = ?');
            $e2->execute([$factoryId]);
            foreach ($e2->fetchAll() as $r) { $nm = trim((string) $r['name']); $emailByName[$nm] = trim((string) ($r['email'] ?? '')); $accountByName[$nm] = $hasAcc ? trim((string) ($r['account_number'] ?? '')) : ''; }
        } catch (Throwable $e) {}

        // Extras formatter (same spec lines as the manual page).
        $fmtExtra = static function (array $ex): string {
            $n = trim((string)($ex['extra_name_snapshot']??'')); $c = trim((string)($ex['choice_label_snapshot']??'')); $o = $n; if ($c!=='') $o .= ($o!==''?': ':'').$c;
            if (isset($ex['user_value']) && $ex['user_value']!==null && (float)$ex['user_value']>0) $o .= ' — '.rtrim(rtrim(number_format((float)$ex['user_value'],2,'.',''),'0'),'.').'mm';
            return $o;
        };

        foreach ($todo as $supplier) {
            $email = $emailByName[$supplier] ?? '';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                error_log("Auto-send: no valid email for supplier '{$supplier}' (quote {$quoteId}) — left to order manually.");
                continue;   // loud (logged) + left on the "to order" pill; never silently dropped
            }
            // The bought-in lines for THIS supplier.
            $li = $pdo->prepare(
                'SELECT qi.id, qi.product_name_snapshot, qi.system_name_snapshot, qi.fabric_name_snapshot,
                        qi.fabric_colour_snapshot, qi.fabric_code_snapshot, qi.fabric_band_snapshot,
                        qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name, qi.notes
                   FROM quote_items qi JOIN products p ON p.id = qi.product_id
                   ' . bought_in_master_join('p', 'mp') . '
                  WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
                    AND ' . bought_in_predicate('mp') . ' AND mp.supplier_name = ?
               ORDER BY qi.line_no, qi.id'
            );
            $li->execute([$quoteId, $factoryId, $supplier]);
            $items = $li->fetchAll(PDO::FETCH_ASSOC);
            if (!$items) continue;
            $ids = array_map(static fn ($x) => (int) $x['id'], $items);
            $exBy = [];
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                try { $ex = $pdo->prepare("SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot, user_value FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id"); $ex->execute($ids); foreach ($ex->fetchAll() as $r) $exBy[(int)$r['quote_item_id']][] = $r; } catch (Throwable $e) {}
            }
            $pdfItems = array_map(static fn ($it) => [
                'product' => $it['product_name_snapshot'], 'system' => $it['system_name_snapshot'], 'fabric' => $it['fabric_name_snapshot'],
                'colour' => $it['fabric_colour_snapshot'], 'code' => $it['fabric_code_snapshot'], 'band' => $it['fabric_band_snapshot'],
                'width_mm' => $it['width_mm'], 'drop_mm' => $it['drop_mm'], 'quantity' => $it['quantity'], 'room' => $it['room_name'], 'notes' => $it['notes'],
                'options' => array_map($fmtExtra, $exBy[(int) $it['id']] ?? []),
            ], $items);
            $ctx = [
                'company_name' => (string)($client['company_name']??''), 'company_email' => (string)($client['email']??''), 'company_phone' => (string)($client['phone']??''),
                'supplier_name' => $supplier, 'account_number' => $accountByName[$supplier] ?? '', 'delivery_address' => $deliveryAddress,
                'quote_number' => (string)($quote['quote_number']??''), 'po_ref' => (string)($quote['quote_number']??''), 'date' => date('j M Y'),
            ];
            supplier_send_group($pdo, $ctx, $pdfItems, $email, $mailOpts, [
                'client_id' => $factoryId, 'quote_id' => $quoteId, 'supplier_name' => $supplier,
                'item_count' => count($items), 'sent_by_user_id' => 0, 'ordered_by_factory_id' => $factoryId,
            ]);
        }
        factory_boughtin_restamp($pdo, $quoteId, $factoryId);
    } catch (Throwable $e) {
        error_log('factory_autosend_suppliers skipped for quote ' . $quoteId . ': ' . $e->getMessage());
    }
}
