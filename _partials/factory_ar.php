<?php
declare(strict_types=1);

/**
 * Wholesale A/R shared helpers (Phase 2). The read layer for Beverley (the
 * factory) billing its trade accounts: which placed orders contain factory-owned
 * lines, the wholesale value of those lines, and document numbering. Every query
 * applies the same ownership rule as the factory queue:
 *     COALESCE(NULLIF(products.source_client_id,0), products.client_id) = factory
 * and only "placed" orders (ordered/fitted/invoiced/paid). A trade account is any
 * client that ISN'T the factory itself.
 */

/** The factory (Beverley) client id. */
function ar_factory_id(): int
{
    if (function_exists('current_factory_id')) return (int) current_factory_id();
    if (function_exists('factory_client_id'))  return (int) factory_client_id();
    return 3;
}

/** Order statuses that count as "placed" (an order the account owes for). */
function ar_placed_statuses(): array
{
    return ['ordered', 'fitted', 'invoiced', 'paid'];
}

/** Does an A/R table exist yet? (so pages render pre-migration). */
function ar_table_ready(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $s->execute([$table]);
        return $cache[$table] = ($s->fetchColumn() !== false);
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

/**
 * Next gap-free document number for a factory + prefix, per calendar year:
 * PREFIX-YYYY-####. $table/$col are internal constants (never user input). The
 * caller must hold a UNIQUE (factory_client_id, number) index + retry on collision.
 */
function ar_next_number(PDO $pdo, int $factoryId, string $prefix, string $table, string $col): string
{
    $year = date('Y');
    $st = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX($col, '-', -1) AS UNSIGNED))
           FROM $table WHERE factory_client_id = ? AND $col LIKE ?"
    );
    $st->execute([$factoryId, $prefix . '-' . $year . '-%']);
    $next = ((int) ($st->fetchColumn() ?? 0)) + 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

/** Snapshot of the account's ship-to address as a newline block. */
function ar_account_address_block(array $acc): string
{
    $lines = array_values(array_filter([
        (string) ($acc['company_name'] ?? ''),
        (string) ($acc['address1'] ?? ''),
        (string) ($acc['address2'] ?? ''),
        trim(((string) ($acc['town'] ?? '')) . ' ' . ((string) ($acc['postcode'] ?? ''))),
        (string) ($acc['county'] ?? ''),
    ], static fn ($s) => trim((string) $s) !== ''));
    return implode("\n", $lines);
}

/**
 * Placed trade-account orders that contain ≥1 factory-owned line, newest first.
 * Each row: quote id/number/status/date, account id + name, bev_lines, bev_qty,
 * wholesale_total (base×qty + Σ option trade_amount), and dn_count (delivery notes
 * already raised for this order). $accountId / $from / $to are optional filters.
 */
function ar_placed_orders(PDO $pdo, int $factoryId, ?int $accountId = null, ?string $from = null, ?string $to = null): array
{
    $in     = "'" . implode("','", ar_placed_statuses()) . "'";
    $ownSub = 'COALESCE(NULLIF(p2.source_client_id,0), p2.client_id) = ?';   // options subquery (p2)
    // Placeholders bind in SQL text order: [1] subquery ownership, [2] client<>,
    // [optional account/from/to], [last] main ownership.
    $args = [$factoryId];   // [1] options-subquery ownership

    $dnReady = ar_table_ready($pdo, 'factory_ar_delivery_notes');
    $dnSel   = $dnReady
        ? "(SELECT COUNT(*) FROM factory_ar_delivery_notes dn WHERE dn.source_quote_id = q.id AND dn.status <> 'cancelled')"
        : '0';

    $where = "q.status IN ($in) AND q.client_id <> ?";
    $args[] = $factoryId;   // [2] exclude the factory's own quotes
    if ($accountId) { $where .= ' AND q.client_id = ?'; $args[] = $accountId; }
    if ($from !== null && $from !== '') { $where .= ' AND q.created_at >= ?';                       $args[] = $from; }
    if ($to   !== null && $to   !== '') { $where .= ' AND q.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $args[] = $to; }

    $sql =
        "SELECT q.id, q.quote_number, q.status, q.created_at,
                q.client_id AS account_id, c.company_name AS account_name,
                COUNT(qi.id)                  AS bev_lines,
                COALESCE(SUM(qi.quantity), 0) AS bev_qty,
                COALESCE(SUM(qi.base_price * qi.quantity), 0)
                  + COALESCE((SELECT SUM(qie.trade_amount)
                                FROM quote_item_extras qie
                                JOIN quote_items qi2 ON qi2.id = qie.quote_item_id
                                JOIN products p2     ON p2.id = qi2.product_id
                               WHERE qi2.quote_id = q.id AND $ownSub), 0) AS wholesale_total,
                $dnSel AS dn_count
           FROM quotes q
           JOIN clients c      ON c.id = q.client_id
           JOIN quote_items qi ON qi.quote_id = q.id
           JOIN products p     ON p.id = qi.product_id
          WHERE $where AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
       GROUP BY q.id, q.quote_number, q.status, q.created_at, q.client_id, c.company_name
       ORDER BY q.created_at DESC, q.id DESC
          LIMIT 500";
    $args[] = $factoryId;   // [last] main-filter ownership

    try {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * The factory-owned lines of one placed order, assembled for a document snapshot
 * (delivery note / invoice). Each row carries the display snapshot fields, the
 * wholesale figures (base_price + trade breakdown, options' trade_amount), and an
 * `options` array of spec labels. Read live from quote_items/quote_item_extras at
 * raise time; the caller copies these into the document's own line tables.
 */
function ar_order_lines_for_doc(PDO $pdo, int $factoryId, int $quoteId): array
{
    $own = 'COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?';
    $st = $pdo->prepare(
        "SELECT qi.id, qi.line_no, qi.product_name_snapshot, qi.system_name_snapshot,
                qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.fabric_code_snapshot,
                qi.fabric_band_snapshot, qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name,
                qi.notes, qi.base_price, qi.trade_price_per_blind,
                qi.trade_discount_percent, qi.trade_discount_amount
           FROM quote_items qi
           JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND $own
       ORDER BY qi.line_no, qi.id"
    );
    $st->execute([$quoteId, $factoryId]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$lines) return [];

    // Options per line — labels for the spec list + wholesale trade_amount.
    $ids = array_map(static fn ($l) => (int) $l['id'], $lines);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $exBy = [];
    try {
        $ex = $pdo->prepare(
            "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot,
                    amount_applied, trade_amount
               FROM quote_item_extras
              WHERE quote_item_id IN ($ph)
           ORDER BY id"
        );
        $ex->execute($ids);
        foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $exBy[(int) $e['quote_item_id']][] = $e;
        }
    } catch (Throwable $e) { /* extras absent — leave empty */ }

    foreach ($lines as &$ln) {
        $opts = [];
        foreach ($exBy[(int) $ln['id']] ?? [] as $e) {
            $name  = trim((string) $e['extra_name_snapshot']);
            $label = trim((string) $e['choice_label_snapshot']);
            if ($name === '' && $label === '') continue;
            $opts[] = $label !== '' ? ($name . ': ' . $label) : $name;
        }
        $ln['options']    = $opts;
        $ln['extras_rows'] = $exBy[(int) $ln['id']] ?? [];   // for pricing (invoices)
    }
    unset($ln);
    return $lines;
}

/** Trade accounts (non-factory clients) that have ≥1 placed factory-owned order. */
function ar_account_options(PDO $pdo, int $factoryId): array
{
    $in = "'" . implode("','", ar_placed_statuses()) . "'";
    try {
        $st = $pdo->prepare(
            "SELECT DISTINCT q.client_id AS id, c.company_name AS name
               FROM quotes q
               JOIN clients c      ON c.id = q.client_id
               JOIN quote_items qi ON qi.quote_id = q.id
               JOIN products p     ON p.id = qi.product_id
              WHERE q.status IN ($in) AND q.client_id <> ?
                AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
           ORDER BY c.company_name"
        );
        $st->execute([$factoryId, $factoryId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Build snapshot invoice lines from a placed order's factory-owned lines — one line
 * per blind, net wholesale: unit_net = base_price + Σ option trade_amount. Returns
 * ['lines' => [...], 'uncaptured' => bool]. `uncaptured` is true when a PRICED option
 * has no captured trade_amount (a pre-2A order) — the caller should refuse to invoice
 * so retail never leaks in. Each line carries the trade→discount→net display fields.
 */
function ar_invoice_lines_from_order(PDO $pdo, int $factoryId, int $quoteId): array
{
    $src   = ar_order_lines_for_doc($pdo, $factoryId, $quoteId);
    $lines = []; $uncaptured = false; $so = 0;
    foreach ($src as $ln) {
        $qty    = max(1, (int) $ln['quantity']);
        $optNet = 0.0;
        foreach ($ln['extras_rows'] ?? [] as $ex) {
            $ta      = $ex['trade_amount'];
            $applied = (float) ($ex['amount_applied'] ?? 0);
            if ($ta === null) { if ($applied > 0) $uncaptured = true; continue; }
            $optNet += (float) $ta;
        }
        $unitNet  = round((float) $ln['base_price'] + $optNet, 2);
        $lineNet  = round($unitNet * $qty, 2);
        $listUnit = round((float) ($ln['trade_price_per_blind'] ?? $ln['base_price']) + $optNet, 2);

        $desc = trim((string) $ln['product_name_snapshot']);
        if (($ln['system_name_snapshot'] ?? '') !== '') $desc .= ' — ' . $ln['system_name_snapshot'];
        $fab = trim(implode(' / ', array_filter([
            (string) $ln['fabric_name_snapshot'], (string) $ln['fabric_colour_snapshot'],
        ], static fn ($s) => trim($s) !== '')));
        if ($fab !== '') $desc .= ', ' . $fab;
        if (!empty($ln['options'])) $desc .= ' (' . implode(', ', $ln['options']) . ')';

        $lines[] = [
            'source_quote_id'      => $quoteId,
            'source_quote_item_id' => (int) $ln['id'],
            'line_type'            => 'blind',
            'description'          => $desc,
            'width_mm'             => $ln['width_mm'],
            'drop_mm'              => $ln['drop_mm'],
            'quantity'             => $qty,
            'unit_net'             => $unitNet,
            'line_net'             => $lineNet,
            'list_trade_unit'      => $listUnit,
            'discount_percent'     => $ln['trade_discount_percent'],
            'discount_amount'      => $ln['trade_discount_amount'],
            'sort_order'           => $so++,
        ];
    }
    return ['lines' => $lines, 'uncaptured' => $uncaptured];
}

/**
 * Create a delivery note (header + snapshot lines) for a validated placed order,
 * with a gap-free DN number. $dispatched stamps it dispatched at creation (the
 * one-step "print & invoice" flow); otherwise it's left 'draft'. Returns
 * ['id'=>int,'number'=>string]. Throws RuntimeException (no owned lines) or
 * PDOException. Runs in the caller's transaction if one is open, else its own.
 * The caller owns order validation and user-facing messaging.
 */
function ar_create_delivery_note(PDO $pdo, int $factory, int $quoteId, int $accountId, int $userId, bool $dispatched = false): array
{
    $lines = ar_order_lines_for_doc($pdo, $factory, $quoteId);
    if (!$lines) throw new RuntimeException('This order has no Beverley-owned lines to deliver.');

    $ac = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $ac->execute([$accountId]);
    $acc  = $ac->fetch(PDO::FETCH_ASSOC) ?: [];
    $addr = ar_account_address_block($acc);

    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $dnId = 0; $num = '';
        for ($try = 1; $try <= 3; $try++) {
            $num = ar_next_number($pdo, $factory, 'DN', 'factory_ar_delivery_notes', 'dn_number');
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO factory_ar_delivery_notes
                       (factory_client_id, account_client_id, dn_number, source_quote_id, status, dispatched_at, delivery_address, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->execute([
                    $factory, $accountId, $num, $quoteId,
                    $dispatched ? 'dispatched' : 'draft',
                    $dispatched ? date('Y-m-d H:i:s') : null,
                    $addr !== '' ? $addr : null,
                    $userId ?: null,
                ]);
                $dnId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $try < 3) continue;   // number taken — retry
                throw $e;
            }
        }

        $insL = $pdo->prepare(
            "INSERT INTO factory_ar_delivery_note_lines
               (delivery_note_id, source_quote_item_id, product_name, system_name, fabric,
                band_code, width_mm, drop_mm, quantity, room, options_snapshot, line_notes, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $so = 0;
        foreach ($lines as $ln) {
            $fabric = trim(implode(' / ', array_filter([
                (string) $ln['fabric_name_snapshot'],
                (string) $ln['fabric_colour_snapshot'],
                (string) $ln['fabric_code_snapshot'],
            ], static fn ($s) => trim($s) !== '')));
            $opts = implode("\n", $ln['options'] ?? []);
            $insL->execute([
                $dnId, (int) $ln['id'],
                $ln['product_name_snapshot'] ?: null, $ln['system_name_snapshot'] ?: null,
                $fabric !== '' ? $fabric : null, $ln['fabric_band_snapshot'] ?: null,
                $ln['width_mm'], $ln['drop_mm'], (int) $ln['quantity'],
                $ln['room_name'] ?: null, $opts !== '' ? $opts : null, $ln['notes'] ?: null, $so++,
            ]);
        }

        if ($ownTxn) $pdo->commit();
        return ['id' => $dnId, 'number' => $num];
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Create an invoice (header + snapshot lines + order link) for a validated placed
 * order, with a gap-free INV number, per-document VAT @20%, due +30 days. $send
 * stamps it 'sent' at creation (the one-step flow auto-sends); otherwise 'raised'.
 * Returns ['id'=>int,'number'=>string,'total'=>float]. Throws RuntimeException on
 * a business problem (no owned lines; a priced option with no captured wholesale
 * price — an uncaptured pre-2A order) or PDOException. Runs in the caller's
 * transaction if open, else its own. The caller MUST have guarded against
 * double-invoicing (see factory_ar_invoice_orders) before calling.
 */
function ar_create_invoice(PDO $pdo, int $factory, int $quoteId, int $accountId, int $userId, bool $send = false): array
{
    $built = ar_invoice_lines_from_order($pdo, $factory, $quoteId);
    if (!$built['lines'])     throw new RuntimeException('This order has no Beverley-owned lines to invoice.');
    if ($built['uncaptured']) throw new RuntimeException('This order predates wholesale-price capture — re-save its lines in the quote before invoicing (a priced option has no wholesale price).');

    $ac = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $ac->execute([$accountId]);
    $acc    = $ac->fetch(PDO::FETCH_ASSOC) ?: [];
    $billTo = ar_account_address_block($acc);
    if (($acc['vat_number'] ?? '') !== '') $billTo .= "\nVAT No. " . $acc['vat_number'];
    $vatPct = 20.00;

    $subtotal = 0.0;
    foreach ($built['lines'] as $l) $subtotal += (float) $l['line_net'];
    $subtotal = round($subtotal, 2);
    $vat      = round($subtotal * $vatPct / 100, 2);
    $total    = round($subtotal + $vat, 2);
    $status   = $send ? 'sent' : 'raised';

    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $invId = 0; $num = '';
        for ($try = 1; $try <= 3; $try++) {
            $num = ar_next_number($pdo, $factory, 'INV', 'factory_ar_invoices', 'inv_number');
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO factory_ar_invoices
                       (factory_client_id, account_client_id, inv_number, status, issue_date, due_date, sent_at,
                        vat_percent, subtotal, vat, total, bill_to_snapshot, created_by)
                     VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->execute([
                    $factory, $accountId, $num, $status, $send ? date('Y-m-d H:i:s') : null,
                    $vatPct, $subtotal, $vat, $total, $billTo !== '' ? $billTo : null, $userId ?: null,
                ]);
                $invId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $try < 3) continue;
                throw $e;
            }
        }

        $insL = $pdo->prepare(
            "INSERT INTO factory_ar_invoice_lines
               (invoice_id, source_quote_id, source_quote_item_id, line_type, description,
                width_mm, drop_mm, quantity, unit_net, line_net, list_trade_unit, discount_percent, discount_amount, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($built['lines'] as $l) {
            $insL->execute([
                $invId, $l['source_quote_id'], $l['source_quote_item_id'], $l['line_type'], $l['description'],
                $l['width_mm'], $l['drop_mm'], $l['quantity'], $l['unit_net'], $l['line_net'],
                $l['list_trade_unit'], $l['discount_percent'], $l['discount_amount'], $l['sort_order'],
            ]);
        }
        $pdo->prepare('INSERT INTO factory_ar_invoice_orders (invoice_id, quote_id) VALUES (?, ?)')->execute([$invId, $quoteId]);

        if ($ownTxn) $pdo->commit();
        return ['id' => $invId, 'number' => $num, 'total' => $total];
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** A non-void invoice number covering this order, or '' if not yet invoiced. */
function ar_order_invoice_number(PDO $pdo, int $quoteId): string
{
    try {
        $st = $pdo->prepare(
            "SELECT i.inv_number FROM factory_ar_invoice_orders io
               JOIN factory_ar_invoices i ON i.id = io.invoice_id
              WHERE io.quote_id = ? AND i.status <> 'void' LIMIT 1"
        );
        $st->execute([$quoteId]);
        return (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return '';
    }
}

/* ── Phase 2D: payments received + allocations ──────────────────────────── */

/** Payments feature ready? (both tables migrated) */
function ar_payments_ready(PDO $pdo): bool
{
    return ar_table_ready($pdo, 'factory_ar_payments')
        && ar_table_ready($pdo, 'factory_ar_payment_allocations');
}

/**
 * Open (unsettled) invoices for an account, oldest first — for payment allocation.
 * balance = total − amount_paid − credits-against-it (non-void). Only balance > 0.
 */
function ar_open_invoices(PDO $pdo, int $factory, int $accountId): array
{
    if (!ar_table_ready($pdo, 'factory_ar_invoices')) return [];
    $st = $pdo->prepare(
        "SELECT i.id, i.inv_number, i.issue_date, i.due_date, i.total, i.amount_paid,
                COALESCE((SELECT SUM(cn.total) FROM factory_ar_credit_notes cn
                           WHERE cn.against_invoice_id = i.id AND cn.status <> 'void'), 0) AS credited
           FROM factory_ar_invoices i
          WHERE i.factory_client_id = ? AND i.account_client_id = ? AND i.status <> 'void'
          ORDER BY (i.issue_date IS NULL), i.issue_date, i.id"
    );
    $st->execute([$factory, $accountId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bal = round((float) $r['total'] - (float) $r['amount_paid'] - (float) $r['credited'], 2);
        if ($bal > 0.004) { $r['balance'] = $bal; $out[] = $r; }
    }
    return $out;
}

/**
 * Account A/R summary (all non-void): invoiced, credited, paid, outstanding.
 * outstanding = invoiced − paid − credited. Credit notes (any, incl. standalone
 * account credits) reduce what the account owes.
 */
function ar_account_balance(PDO $pdo, int $factory, int $accountId): array
{
    $z = ['invoiced' => 0.0, 'credited' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0];
    if (!ar_table_ready($pdo, 'factory_ar_invoices')) return $z;
    $i = $pdo->prepare(
        "SELECT COALESCE(SUM(total),0) AS invoiced, COALESCE(SUM(amount_paid),0) AS paid
           FROM factory_ar_invoices
          WHERE factory_client_id = ? AND account_client_id = ? AND status <> 'void'"
    );
    $i->execute([$factory, $accountId]);
    $row = $i->fetch(PDO::FETCH_ASSOC) ?: [];
    $z['invoiced'] = round((float) ($row['invoiced'] ?? 0), 2);
    $z['paid']     = round((float) ($row['paid'] ?? 0), 2);   // fallback: allocated, pre-payments-table
    // Prefer TOTAL money received (payments), so unallocated payment sitting as
    // credit on account correctly reduces the outstanding — not just allocations.
    if (ar_payments_ready($pdo)) {
        $p = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM factory_ar_payments
                             WHERE factory_client_id = ? AND account_client_id = ? AND voided_at IS NULL");
        $p->execute([$factory, $accountId]);
        $z['paid'] = round((float) $p->fetchColumn(), 2);
    }
    if (ar_table_ready($pdo, 'factory_ar_credit_notes')) {
        $c = $pdo->prepare(
            "SELECT COALESCE(SUM(total),0) FROM factory_ar_credit_notes
              WHERE factory_client_id = ? AND account_client_id = ? AND status <> 'void'"
        );
        $c->execute([$factory, $accountId]);
        $z['credited'] = round((float) $c->fetchColumn(), 2);
    }
    $z['outstanding'] = round($z['invoiced'] - $z['paid'] - $z['credited'], 2);
    return $z;
}

/** Payments recorded for an account, newest first (with allocated total). */
function ar_account_payments(PDO $pdo, int $factory, int $accountId): array
{
    if (!ar_table_ready($pdo, 'factory_ar_payments')) return [];
    $st = $pdo->prepare(
        "SELECT p.*, COALESCE((SELECT SUM(a.amount) FROM factory_ar_payment_allocations a
                                WHERE a.payment_id = p.id), 0) AS allocated
           FROM factory_ar_payments p
          WHERE p.factory_client_id = ? AND p.account_client_id = ?
          ORDER BY p.payment_date DESC, p.id DESC"
    );
    $st->execute([$factory, $accountId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Recompute an invoice's amount_paid cache + status from its (non-void) allocations. */
function ar_recompute_invoice_paid(PDO $pdo, int $invoiceId): void
{
    $ps = $pdo->prepare(
        "SELECT COALESCE(SUM(a.amount),0)
           FROM factory_ar_payment_allocations a
           JOIN factory_ar_payments p ON p.id = a.payment_id
          WHERE a.invoice_id = ? AND p.voided_at IS NULL"
    );
    $ps->execute([$invoiceId]);
    $paid = round((float) $ps->fetchColumn(), 2);

    $iv = $pdo->prepare("SELECT total, sent_at, status FROM factory_ar_invoices WHERE id = ? LIMIT 1");
    $iv->execute([$invoiceId]);
    $row = $iv->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string) $row['status'] === 'void') return;   // never touch a void invoice

    $cred = 0.0;
    if (ar_table_ready($pdo, 'factory_ar_credit_notes')) {
        $c = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM factory_ar_credit_notes WHERE against_invoice_id = ? AND status <> 'void'");
        $c->execute([$invoiceId]);
        $cred = round((float) $c->fetchColumn(), 2);
    }
    $netDue = round((float) $row['total'] - $cred, 2);
    if ($netDue <= 0.004 || $paid >= $netDue - 0.004) {
        $status = 'paid';
    } elseif ($paid > 0.004) {
        $status = 'part_paid';
    } else {
        $status = !empty($row['sent_at']) ? 'sent' : 'raised';
    }
    $pdo->prepare("UPDATE factory_ar_invoices SET amount_paid = ?, status = ? WHERE id = ?")
        ->execute([$paid, $status, $invoiceId]);
}

/**
 * Record a payment from an account and allocate it across invoices.
 *   $allocations = [invoiceId => amount, …]  (only >0 entries applied)
 * Returns ['id','number','allocated','amount']. Own-transaction-aware. Each
 * allocation is checked to belong to a non-void invoice for this factory+account.
 */
function ar_create_payment(PDO $pdo, int $factory, int $accountId, string $date, string $method,
                           float $amount, string $reference, string $notes, array $allocations, int $userId): array
{
    $amount = round(max(0.0, $amount), 2);
    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $payId = 0; $num = '';
        for ($try = 1; $try <= 3; $try++) {
            $num = ar_next_number($pdo, $factory, 'PAY', 'factory_ar_payments', 'pay_number');
            try {
                $pdo->prepare(
                    "INSERT INTO factory_ar_payments
                       (factory_client_id, account_client_id, pay_number, payment_date, method, amount, reference, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $factory, $accountId, $num, $date, $method, $amount,
                    $reference !== '' ? $reference : null, $notes !== '' ? $notes : null, $userId ?: null,
                ]);
                $payId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $try < 3) continue;
                throw $e;
            }
        }
        $insA = $pdo->prepare("INSERT INTO factory_ar_payment_allocations (payment_id, invoice_id, amount) VALUES (?, ?, ?)");
        $chk  = $pdo->prepare("SELECT 1 FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? AND account_client_id = ? AND status <> 'void' LIMIT 1");
        $allocated = 0.0; $touched = [];
        foreach ($allocations as $invId => $amt) {
            $invId = (int) $invId; $amt = round((float) $amt, 2);
            if ($invId <= 0 || $amt <= 0.004) continue;
            $chk->execute([$invId, $factory, $accountId]);
            if (!$chk->fetchColumn()) continue;
            $insA->execute([$payId, $invId, $amt]);
            $allocated += $amt; $touched[$invId] = true;
        }
        foreach (array_keys($touched) as $invId) ar_recompute_invoice_paid($pdo, $invId);
        if ($ownTxn) $pdo->commit();
        return ['id' => $payId, 'number' => $num, 'allocated' => round($allocated, 2), 'amount' => $amount];
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Void a payment (keeps the row for audit) and recompute the invoices it touched. */
function ar_void_payment(PDO $pdo, int $factory, int $payId, string $reason = ''): void
{
    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $g = $pdo->prepare("SELECT id FROM factory_ar_payments WHERE id = ? AND factory_client_id = ? AND voided_at IS NULL LIMIT 1");
        $g->execute([$payId, $factory]);
        if (!$g->fetchColumn()) { if ($ownTxn) $pdo->commit(); return; }
        $inv = $pdo->prepare("SELECT DISTINCT invoice_id FROM factory_ar_payment_allocations WHERE payment_id = ?");
        $inv->execute([$payId]);
        $ids = array_map(static fn ($r) => (int) $r['invoice_id'], $inv->fetchAll(PDO::FETCH_ASSOC));
        $pdo->prepare("UPDATE factory_ar_payments SET voided_at = NOW(), void_reason = ? WHERE id = ?")
            ->execute([$reason !== '' ? substr($reason, 0, 255) : null, $payId]);
        foreach ($ids as $invId) ar_recompute_invoice_paid($pdo, $invId);
        if ($ownTxn) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Account statement for a period. Ledger of non-void invoices (charge), credit
 * notes (credit) and payments (credit) by document date, with a running balance.
 *   $from '' = from the beginning (opening 0);  $to '' = today.
 * Returns ['from','to','opening','rows'=>[{date,type,ref,charge,credit,balance}],'closing'].
 * With $from='' and $to=today, 'closing' equals ar_account_balance()['outstanding'].
 */
function ar_statement(PDO $pdo, int $factory, int $accountId, string $from = '', string $to = ''): array
{
    $to    = ($to !== '' && strtotime($to)) ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
    $fromD = ($from !== '' && strtotime($from)) ? date('Y-m-d', strtotime($from)) : null;

    $tx = [];   // ['date','type','ref','amount'] — amount signed (+charge, −credit)
    $add = static function (string $type, array $rows, int $sign) use (&$tx) {
        foreach ($rows as $r) {
            $tx[] = ['date' => (string) $r['d'], 'type' => $type, 'ref' => (string) $r['ref'],
                     'amount' => $sign * round((float) $r['amt'], 2)];
        }
    };
    if (ar_table_ready($pdo, 'factory_ar_invoices')) {
        $s = $pdo->prepare("SELECT inv_number ref, COALESCE(issue_date, DATE(created_at)) d, total amt
                              FROM factory_ar_invoices
                             WHERE factory_client_id = ? AND account_client_id = ? AND status <> 'void'");
        $s->execute([$factory, $accountId]);
        $add('Invoice', $s->fetchAll(PDO::FETCH_ASSOC), 1);
    }
    if (ar_table_ready($pdo, 'factory_ar_credit_notes')) {
        $s = $pdo->prepare("SELECT cn_number ref, COALESCE(issue_date, DATE(created_at)) d, total amt
                              FROM factory_ar_credit_notes
                             WHERE factory_client_id = ? AND account_client_id = ? AND status <> 'void'");
        $s->execute([$factory, $accountId]);
        $add('Credit note', $s->fetchAll(PDO::FETCH_ASSOC), -1);
    }
    if (ar_table_ready($pdo, 'factory_ar_payments')) {
        $s = $pdo->prepare("SELECT pay_number ref, payment_date d, amount amt
                              FROM factory_ar_payments
                             WHERE factory_client_id = ? AND account_client_id = ? AND voided_at IS NULL");
        $s->execute([$factory, $accountId]);
        $add('Payment', $s->fetchAll(PDO::FETCH_ASSOC), -1);
    }

    // Date ascending; within a day, charges (positive) before credits (negative).
    usort($tx, static function ($a, $b) {
        $c = strcmp((string) $a['date'], (string) $b['date']);
        return $c !== 0 ? $c : ($b['amount'] <=> $a['amount']);
    });

    $opening = 0.0;
    foreach ($tx as $t) {
        if ($fromD !== null && (string) $t['date'] < $fromD) $opening = round($opening + $t['amount'], 2);
    }

    $rows = []; $running = $opening;
    foreach ($tx as $t) {
        $d = (string) $t['date'];
        if ($fromD !== null && $d < $fromD) continue;   // folded into opening
        if ($d > $to) continue;                          // outside the period
        $running = round($running + $t['amount'], 2);
        $rows[] = [
            'date'    => $d,
            'type'    => $t['type'],
            'ref'     => $t['ref'],
            'charge'  => $t['amount'] > 0 ? $t['amount'] : 0.0,
            'credit'  => $t['amount'] < 0 ? -$t['amount'] : 0.0,
            'balance' => $running,
        ];
    }

    return ['from' => $fromD, 'to' => $to, 'opening' => round($opening, 2),
            'rows' => $rows, 'closing' => round($running, 2)];
}
