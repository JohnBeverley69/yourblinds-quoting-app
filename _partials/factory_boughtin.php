<?php
declare(strict_types=1);

/**
 * Factory bought-in status + supplier purchase orders.
 *
 * Tracks, per order, which bought-in suppliers have been ordered / received, and
 * sends the purchase orders: Process order (factory/process-order.php), its
 * retry and the Order bought-in page all go through
 * factory_po_send(), so the PO can't differ between them.
 * The "ordered"/"received" rollups on quotes.supplier_ordered_at /
 * supplier_received_at only flip once EVERY bought-in supplier on the order is
 * done, so a mixed-supplier order can't read "ordered" while one is still pending.
 */

require_once __DIR__ . '/bought_in.php';
require_once __DIR__ . '/supplier_send.php';
require_once __DIR__ . '/extra_value.php';

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

/**
 * Supplier names whose goods are all in: every PO sent to them for the quote is
 * marked received. A follow-up PO (an item added later) that hasn't arrived
 * keeps the supplier out, the same rule ot_order_tracks() shows.
 */
function factory_boughtin_received_names(PDO $pdo, int $quoteId, int $factoryId): array
{
    try {
        $st = $pdo->prepare(
            'SELECT TRIM(supplier_name) FROM supplier_orders WHERE quote_id = ? AND ordered_by_factory_id = ?
           GROUP BY TRIM(supplier_name) HAVING SUM(received_at IS NULL) = 0'
        );
        $st->execute([$quoteId, $factoryId]);
        return array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $st->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Throwable $e) { return []; }
}

/**
 * Re-derive the order-level rollups from the log:
 *   supplier_ordered_at  set once every bought-in line is on a PO;
 *   supplier_received_at set once, on top of that, every supplier is received.
 * Cleared back to NULL if a line or supplier is added and no longer covered.
 */
function factory_boughtin_restamp(PDO $pdo, int $quoteId, int $factoryId): void
{
    $need     = factory_boughtin_supplier_names($pdo, $quoteId, $factoryId);
    if (!$need) return;   // no bought-in lines — nothing to track
    $allOrdered  = !factory_boughtin_outstanding($pdo, $quoteId, $factoryId);
    $received    = factory_boughtin_received_names($pdo, $quoteId, $factoryId);
    $allReceived = $allOrdered && !array_diff($need, $received);
    try {
        $pdo->prepare('UPDATE quotes SET supplier_ordered_at = ?, supplier_received_at = ? WHERE id = ?')->execute([
            $allOrdered  ? date('Y-m-d H:i:s') : null,
            $allReceived ? date('Y-m-d H:i:s') : null,
            $quoteId,
        ]);
    } catch (Throwable $e) { /* columns absent — ignore */ }
}

/**
 * True if the order has bought-in lines not yet all received (blocks dispatch),
 * including an item added after its supplier's PO went.
 */
function factory_boughtin_awaiting(PDO $pdo, int $quoteId, int $factoryId): bool
{
    $need = factory_boughtin_supplier_names($pdo, $quoteId, $factoryId);
    if (!$need) return false;
    if (factory_boughtin_outstanding($pdo, $quoteId, $factoryId)) return true;
    $received = factory_boughtin_received_names($pdo, $quoteId, $factoryId);
    return (bool) array_diff($need, $received);
}

/**
 * The bought-in lines on an order that still need a purchase order, per MASTER
 * supplier: every line for a supplier never ordered from, else only the lines
 * added since that supplier's last PO went (a blind added after the order was
 * processed must not be assumed done — John, 2026-10-12). Each line carries the
 * spec fields the PO needs and its formatted extras ('options').
 *
 * @return array<string, array{lines: array<int, array>, addition: bool}>
 */
function factory_boughtin_outstanding(PDO $pdo, int $quoteId, int $factoryId, ?string $onlySupplier = null): array
{
    try {
        $st = $pdo->prepare(
            'SELECT qi.id, qi.line_no, qi.created_at, qi.product_name_snapshot, qi.system_name_snapshot,
                    qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.fabric_code_snapshot,
                    qi.fabric_band_snapshot, qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name, qi.notes,
                    TRIM(mp.supplier_name) AS supplier
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
               ' . bought_in_master_join('p', 'mp') . '
              WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
                AND ' . bought_in_predicate('mp') . '
           ORDER BY qi.line_no, qi.id'
        );
        $st->execute([$quoteId, $factoryId]);
        $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }

    // When each supplier's last PO went (this factory's sends only).
    $lastSent = [];
    try {
        $ls = $pdo->prepare('SELECT TRIM(supplier_name) AS s, MAX(sent_at) AS t FROM supplier_orders WHERE quote_id = ? AND ordered_by_factory_id = ? GROUP BY TRIM(supplier_name)');
        $ls->execute([$quoteId, $factoryId]);
        foreach ($ls->fetchAll(PDO::FETCH_ASSOC) as $r) $lastSent[(string) $r['s']] = (string) $r['t'];
    } catch (Throwable $e) {}

    $out = [];
    foreach ($lines as $ln) {
        $sup = (string) $ln['supplier'];
        if ($sup === '' || ($onlySupplier !== null && $sup !== $onlySupplier)) continue;
        $sent = $lastSent[$sup] ?? null;
        if ($sent !== null && (string) $ln['created_at'] <= $sent) continue;   // already on a PO
        $out[$sup]['lines'][]  = $ln;
        $out[$sup]['addition'] = $sent !== null;
    }
    if (!$out) return [];

    $ids = [];
    foreach ($out as $g) foreach ($g['lines'] as $ln) $ids[] = (int) $ln['id'];
    $exBy = factory_po_extras($pdo, $ids);
    foreach ($out as &$g) {
        foreach ($g['lines'] as &$ln) $ln['options'] = $exBy[(int) $ln['id']] ?? [];
        unset($ln);
    }
    unset($g);
    ksort($out);
    return $out;
}

/** Formatted extras ("Control: Left — 1200mm") per quote_item id. */
function factory_po_extras(PDO $pdo, array $itemIds): array
{
    $itemIds = array_values(array_filter(array_map('intval', $itemIds)));
    if (!$itemIds) return [];
    $ph = implode(',', array_fill(0, count($itemIds), '?'));
    try {
        $st = $pdo->prepare("SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot, user_value, mode FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id");
        $st->execute($itemIds);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        try {   // user_value not migrated
            $st = $pdo->prepare("SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id");
            $st->execute($itemIds);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) { return []; }
    }
    $out = [];
    foreach ($rows as $ex) if (!extra_is_blank_choice($ex)) $out[(int) $ex['quote_item_id']][] = factory_po_extra_text($ex);
    return $out;
}

function factory_po_extra_text(array $ex): string
{
    $n = trim((string) ($ex['extra_name_snapshot'] ?? '')); $c = trim((string) ($ex['choice_label_snapshot'] ?? ''));
    $o = $n; if ($c !== '') $o .= ($o !== '' ? ': ' : '') . $c;
    return $o . extra_value_suffix($ex);   // "× 4" for a quantity, "— 50mm" for a size
}

/**
 * Who the factory is when it sends a PO: its own name/email/phone, delivery
 * address, From/Reply-To, and its suppliers' order emails + account numbers.
 * Always the ACTING factory's records — a white-label factory orders from its
 * own suppliers under its own name.
 */
function factory_po_sender(PDO $pdo, int $factoryId): array
{
    $client = [];
    try { $c = $pdo->prepare('SELECT company_name, email, phone FROM clients WHERE id = ? LIMIT 1'); $c->execute([$factoryId]); $client = $c->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
    $delivery = '';
    try { $d = $pdo->prepare('SELECT supplier_delivery_address FROM client_settings WHERE client_id = ? LIMIT 1'); $d->execute([$factoryId]); $delivery = (string) ($d->fetchColumn() ?: ''); } catch (Throwable $e) {}
    $fromName = (string) ($client['company_name'] ?? ''); $fromEmail = (string) ($client['email'] ?? '');
    try {
        $f = $pdo->prepare('SELECT email_from_name, reply_to_email FROM client_settings WHERE client_id = ? LIMIT 1');
        $f->execute([$factoryId]);
        if ($r = $f->fetch(PDO::FETCH_ASSOC)) {
            if (trim((string) ($r['email_from_name'] ?? '')) !== '') $fromName  = trim((string) $r['email_from_name']);
            if (trim((string) ($r['reply_to_email']  ?? '')) !== '') $fromEmail = trim((string) $r['reply_to_email']);
        }
    } catch (Throwable $e) {}
    $mailOpts = [];
    if ($fromName !== '') { $mailOpts['from_name'] = $fromName; $mailOpts['reply_to_name'] = $fromName; }
    if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) $mailOpts['reply_to_email'] = $fromEmail;

    $emails = []; $accounts = [];
    try {
        $hasAcc = false; try { $hasAcc = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'account_number'")->fetchColumn() !== false; } catch (Throwable $e) {}
        $s = $pdo->prepare('SELECT name, email' . ($hasAcc ? ', account_number' : '') . ' FROM suppliers WHERE client_id = ?');
        $s->execute([$factoryId]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $nm = trim((string) $r['name']);
            $emails[$nm]   = trim((string) ($r['email'] ?? ''));
            $accounts[$nm] = $hasAcc ? trim((string) ($r['account_number'] ?? '')) : '';
        }
    } catch (Throwable $e) {}

    return ['factory_id' => $factoryId, 'client' => $client, 'delivery' => $delivery, 'mail_opts' => $mailOpts,
            'emails' => $emails, 'accounts' => $accounts];
}

/** A supplier's order email if it's a valid address, else ''. */
function factory_po_email(array $sender, string $supplier): string
{
    $e = (string) ($sender['emails'][$supplier] ?? '');
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false ? $e : '';
}

/**
 * Email ONE supplier its purchase order for $lines on this order and log it.
 * $addition marks a follow-up PO for items added after the first one went.
 *
 * @return array{ok: bool, reason: string, email: string}
 */
function factory_po_send(PDO $pdo, array $sender, int $quoteId, string $quoteNumber, string $supplier, array $lines, int $userId, bool $addition = false): array
{
    $email = factory_po_email($sender, $supplier);
    if ($email === '') {
        $raw = (string) ($sender['emails'][$supplier] ?? '');
        return ['ok' => false, 'email' => $raw,
                'reason' => $raw === '' ? 'No order email is set for ' . $supplier . ' (Settings › Suppliers).'
                                        : 'The order email for ' . $supplier . ' isn\'t a valid address (Settings › Suppliers).'];
    }
    if (!$lines) return ['ok' => false, 'email' => $email, 'reason' => 'Nothing to order from ' . $supplier . '.'];

    $client = $sender['client'];
    $pdfItems = array_map(static fn ($it) => [
        'product' => $it['product_name_snapshot'], 'system' => $it['system_name_snapshot'], 'fabric' => $it['fabric_name_snapshot'],
        'colour' => $it['fabric_colour_snapshot'], 'code' => $it['fabric_code_snapshot'], 'band' => $it['fabric_band_snapshot'],
        'width_mm' => $it['width_mm'], 'drop_mm' => $it['drop_mm'], 'quantity' => $it['quantity'], 'room' => $it['room_name'], 'notes' => $it['notes'],
        'options' => $it['options'] ?? [],
    ], $lines);
    $ctx = [
        'company_name' => (string) ($client['company_name'] ?? ''), 'company_email' => (string) ($client['email'] ?? ''),
        'company_phone' => (string) ($client['phone'] ?? ''), 'supplier_name' => $supplier,
        'account_number' => (string) ($sender['accounts'][$supplier] ?? ''), 'delivery_address' => (string) $sender['delivery'],
        'quote_number' => $quoteNumber, 'po_ref' => $quoteNumber . ($addition ? ' (additional items)' : ''), 'date' => date('j M Y'),
        'addition' => $addition,
    ];
    $ok = supplier_send_group($pdo, $ctx, $pdfItems, $email, $sender['mail_opts'], [
        'client_id' => (int) $sender['factory_id'], 'quote_id' => $quoteId, 'supplier_name' => $supplier,
        'item_count' => count($lines), 'sent_by_user_id' => $userId, 'ordered_by_factory_id' => (int) $sender['factory_id'],
    ]);
    return ['ok' => $ok, 'email' => $email, 'reason' => $ok ? '' : 'The email to ' . $email . ' didn\'t send. Check the address, then retry.'];
}

/**
 * Send every outstanding PO on an order (or just one supplier's), then roll the
 * order up. Returns per supplier ['ok', 'reason', 'email', 'items', 'addition'].
 * A failure is never silent: it's returned here, and the order shows
 * "Couldn't order from …" (ot_order_tracks) until it goes.
 */
function factory_send_outstanding(PDO $pdo, int $quoteId, int $factoryId, int $userId, ?string $onlySupplier = null): array
{
    $todo = factory_boughtin_outstanding($pdo, $quoteId, $factoryId, $onlySupplier);
    if (!$todo) return [];
    $qn = '';
    try { $q = $pdo->prepare('SELECT quote_number FROM quotes WHERE id = ? LIMIT 1'); $q->execute([$quoteId]); $qn = (string) ($q->fetchColumn() ?: ''); } catch (Throwable $e) {}
    $sender = factory_po_sender($pdo, $factoryId);
    $res = [];
    foreach ($todo as $sup => $g) {
        $r = factory_po_send($pdo, $sender, $quoteId, $qn, (string) $sup, $g['lines'], $userId, $g['addition']);
        if (!$r['ok']) error_log("Supplier PO not sent: quote {$quoteId}, '{$sup}': {$r['reason']}");
        $res[(string) $sup] = $r + [
            'items'    => array_sum(array_map(static fn ($l) => max(1, (int) $l['quantity']), $g['lines'])),
            'addition' => $g['addition'],
        ];
    }
    factory_boughtin_restamp($pdo, $quoteId, $factoryId);
    return $res;
}

// (factory_autosend_suppliers — emailing POs the moment an order was placed,
// behind the auto_send_suppliers switch — was retired 2026-10-17 at John's
// request: Process order is the ONLY way supplier orders go out. The
// client_settings.auto_send_suppliers column is left in place, unused.)
