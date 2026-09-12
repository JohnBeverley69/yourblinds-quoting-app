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

/**
 * Order-aligned document number: PREFIX-<order number> (e.g. INV-ABC-2026-0001),
 * so the invoice / delivery note / credit note carry the ORDER's own number and a
 * trade account can reconcile them against the order at a glance instead of three
 * separate running series. A second document of the same type for the same order
 * (a void-and-reissue, or a further credit note) gets a "-2", "-3" … suffix so the
 * number stays unique. $table/$col are internal constants (never user input); the
 * caller still retries on a UNIQUE collision (a race just bumps the suffix).
 */
function ar_order_doc_number(PDO $pdo, int $factoryId, int $quoteId, string $prefix, string $table, string $col): string
{
    $q = $pdo->prepare('SELECT quote_number FROM quotes WHERE id = ? LIMIT 1');
    $q->execute([$quoteId]);
    $qn = trim((string) ($q->fetchColumn() ?: ''));
    if ($qn === '') $qn = (string) $quoteId;                 // fallback: bare id
    $base = $prefix . '-' . $qn;

    $exists = static function (string $num) use ($pdo, $factoryId, $table, $col): bool {
        $s = $pdo->prepare("SELECT 1 FROM $table WHERE factory_client_id = ? AND $col = ? LIMIT 1");
        $s->execute([$factoryId, $num]);
        return (bool) $s->fetchColumn();
    };
    if (!$exists($base)) return $base;
    for ($i = 2; ; $i++) {
        $cand = $base . '-' . $i;
        if (!$exists($cand)) return $cand;
    }
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
 * SQL expression resolving a quote's TRADE ACCOUNT, for wholesale A/R discovery.
 *
 * Two ways an order reaches a trade account:
 *   - the account's OWN portal quote     → client_id = the account
 *   - a Beverley "New order" quote FOR    → client_id = factory, and the account
 *     the account (no-portal one-offs)      is tagged in account_client_id
 * So the account is account_client_id when set, else client_id. This one
 * expression handles all three quote shapes with NO factory id inline —
 *   portal account quote  → account_client_id NULL → client_id (= account)  [in]
 *   Beverley RETAIL quote → account_client_id NULL → client_id (= factory)  [out, `<> factory`]
 *   Beverley → account    → account_client_id set  → the account            [in]
 * Falls back to plain client_id on a DB that predates the column, so callers
 * that add it are byte-identical pre-migration. The expression carries no
 * placeholders, so it drops into existing SQL without disturbing bind order.
 *
 * @param string $prefix column prefix incl. dot — 'q.' (default) or '' for an unaliased `quotes`.
 */
function ar_account_expr(PDO $pdo, string $prefix = 'q.'): string
{
    static $has = null;
    if ($has === null) {
        try { $pdo->query('SELECT account_client_id FROM quotes LIMIT 0'); $has = true; }
        catch (Throwable $e) { $has = false; }
    }
    return $has
        ? "COALESCE(NULLIF({$prefix}account_client_id, 0), {$prefix}client_id)"
        : "{$prefix}client_id";
}

/**
 * Placed trade-account orders that contain ≥1 factory-owned line, newest first.
 * Each row: quote id/number/status/date, account id + name, bev_lines, bev_qty,
 * wholesale_total (base×qty + Σ option trade_amount), and dn_count (delivery notes
 * already raised for this order). $accountId / $from / $to are optional filters.
 *
 * "Account" is resolved via ar_account_expr(), so Beverley-raised orders FOR an
 * account (client_id = factory, account tagged in account_client_id) are included
 * and grouped under the account — not just the account's own portal quotes.
 */
function ar_placed_orders(PDO $pdo, int $factoryId, ?int $accountId = null, ?string $from = null, ?string $to = null): array
{
    $in     = "'" . implode("','", ar_placed_statuses()) . "'";
    $acct   = ar_account_expr($pdo, 'q.');                                   // effective trade account
    $ownSub = 'COALESCE(NULLIF(p2.source_client_id,0), p2.client_id) = ?';   // options subquery (p2)
    // Placeholders bind in SQL text order: [1] subquery ownership, [2] account<>,
    // [optional account/from/to], [last] main ownership. ($acct carries no
    // placeholders, so interpolating it doesn't shift this order.)
    $args = [$factoryId];   // [1] options-subquery ownership

    $dnReady = ar_table_ready($pdo, 'factory_ar_delivery_notes');
    $dnSel   = $dnReady
        ? "(SELECT COUNT(*) FROM factory_ar_delivery_notes dn WHERE dn.source_quote_id = q.id AND dn.status <> 'cancelled')"
        : '0';

    $where = "q.status IN ($in) AND {$acct} <> ?";
    $args[] = $factoryId;   // [2] exclude the factory's own (retail) quotes
    if ($accountId) { $where .= " AND {$acct} = ?"; $args[] = $accountId; }
    if ($from !== null && $from !== '') { $where .= ' AND q.created_at >= ?';                       $args[] = $from; }
    if ($to   !== null && $to   !== '') { $where .= ' AND q.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $args[] = $to; }

    $sql =
        "SELECT q.id, q.quote_number, q.status, q.created_at,
                {$acct} AS account_id, c.company_name AS account_name,
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
           JOIN clients c      ON c.id = {$acct}
           JOIN quote_items qi ON qi.quote_id = q.id
           JOIN products p     ON p.id = qi.product_id
          WHERE $where AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
       GROUP BY q.id, q.quote_number, q.status, q.created_at, {$acct}, c.company_name
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
    $in   = "'" . implode("','", ar_placed_statuses()) . "'";
    $acct = ar_account_expr($pdo, 'q.');
    try {
        $st = $pdo->prepare(
            "SELECT DISTINCT {$acct} AS id, c.company_name AS name
               FROM quotes q
               JOIN clients c      ON c.id = {$acct}
               JOIN quote_items qi ON qi.quote_id = q.id
               JOIN products p     ON p.id = qi.product_id
              WHERE q.status IN ($in) AND {$acct} <> ?
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
            $num = ar_order_doc_number($pdo, $factory, $quoteId, 'DN', 'factory_ar_delivery_notes', 'dn_number');
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
            $num = ar_order_doc_number($pdo, $factory, $quoteId, 'INV', 'factory_ar_invoices', 'inv_number');
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

/* ── Statement run (open-item statements + aged debtors) ─────────────────── */

/**
 * OPEN-ITEM view of an account's unpaid invoices as at a date — the BM statement
 * table. One row per non-void invoice with issue_date <= $asAt that STILL has an
 * amount outstanding (paid/credited AS AT that date, so a past-dated run is
 * historically correct). Columns mirror BM: date, invoice no, order ref, the
 * account's own customer reference, amount, paid, outstanding.
 *   Returns ['rows'=>[{issue_date,due_date,inv_number,order_ref,customer_ref,total,paid,outstanding}],
 *            'total_outstanding'=>float].
 */
function ar_statement_invoices(PDO $pdo, int $factory, int $accountId, string $asAt = ''): array
{
    $res = ['rows' => [], 'total_outstanding' => 0.0];
    if (!ar_table_ready($pdo, 'factory_ar_invoices')) return $res;
    $toD = ($asAt !== '' && strtotime($asAt)) ? date('Y-m-d', strtotime($asAt)) : date('Y-m-d');

    $hasPay = ar_payments_ready($pdo);
    $hasCN  = ar_table_ready($pdo, 'factory_ar_credit_notes');
    // customer_reference may not exist pre-migrate_order_references — probe once.
    $hasCustRef = false;
    try { $pdo->query('SELECT customer_reference FROM quotes LIMIT 0'); $hasCustRef = true; }
    catch (Throwable $e) { $hasCustRef = false; }

    // Paid/credited AS AT $asAt (bounded by payment/credit date) so a back-dated
    // statement doesn't count money that arrived later.
    $paidSub = $hasPay
        ? "COALESCE((SELECT SUM(a.amount) FROM factory_ar_payment_allocations a
                       JOIN factory_ar_payments p ON p.id = a.payment_id
                      WHERE a.invoice_id = i.id AND p.voided_at IS NULL AND p.payment_date <= ?),0)"
        : '0';
    $credSub = $hasCN
        ? "COALESCE((SELECT SUM(cn.total) FROM factory_ar_credit_notes cn
                      WHERE cn.against_invoice_id = i.id AND cn.status <> 'void'
                        AND COALESCE(cn.issue_date, DATE(cn.created_at)) <= ?),0)"
        : '0';
    $refSub  = "(SELECT q.quote_number FROM factory_ar_invoice_orders io
                   JOIN quotes q ON q.id = io.quote_id WHERE io.invoice_id = i.id ORDER BY io.id LIMIT 1)";
    $custSub = $hasCustRef
        ? "(SELECT q.customer_reference FROM factory_ar_invoice_orders io
              JOIN quotes q ON q.id = io.quote_id WHERE io.invoice_id = i.id ORDER BY io.id LIMIT 1)"
        : 'NULL';

    // Placeholder order follows SQL text: SELECT subqueries first (paid, cred),
    // then the WHERE (factory, account, asAt).
    $params = [];
    if ($hasPay) $params[] = $toD;
    if ($hasCN)  $params[] = $toD;
    $params[] = $factory; $params[] = $accountId; $params[] = $toD;

    $sql = "SELECT i.id, i.inv_number, i.issue_date, i.due_date, i.total,
                   $paidSub AS paid, $credSub AS credited,
                   $refSub AS order_ref, $custSub AS customer_ref
              FROM factory_ar_invoices i
             WHERE i.factory_client_id = ? AND i.account_client_id = ? AND i.status <> 'void'
               AND COALESCE(i.issue_date, DATE(i.created_at)) <= ?
             ORDER BY (i.issue_date IS NULL), i.issue_date, i.id";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
    } catch (Throwable $e) {
        return $res;
    }

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $total = round((float) $r['total'], 2);
        $settled = round((float) $r['paid'] + (float) $r['credited'], 2);   // BM "Paid" = payments + credits
        $out     = round($total - $settled, 2);
        if ($out <= 0.004) continue;                                         // open-item: only what's owed
        $res['rows'][] = [
            'issue_date'   => (string) ($r['issue_date'] ?? ''),
            'due_date'     => (string) ($r['due_date'] ?? ''),
            'inv_number'   => (string) $r['inv_number'],
            'order_ref'    => (string) ($r['order_ref'] ?? ''),
            'customer_ref' => (string) ($r['customer_ref'] ?? ''),
            'total'        => $total,
            'paid'         => $settled,
            'outstanding'  => $out,
        ];
        $res['total_outstanding'] = round($res['total_outstanding'] + $out, 2);
    }
    return $res;
}

/**
 * Aged-debtor buckets for an account as at a date — DAYS OVERDUE against each open
 * invoice's due date (Current = not yet due). Buckets: current, 1-30, 31-60, 61-90,
 * 90+. Same open-item set as ar_statement_invoices(), so the statement and the
 * debtors report always reconcile. A missing due_date falls back to issue_date+30.
 *   Returns ['current','d30','d60','d90','d90plus','total'].
 */
function ar_aging(PDO $pdo, int $factory, int $accountId, string $asAt = ''): array
{
    $z = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90plus' => 0.0, 'total' => 0.0];
    $asD  = ($asAt !== '' && strtotime($asAt)) ? date('Y-m-d', strtotime($asAt)) : date('Y-m-d');
    $asTs = strtotime($asD . ' 23:59:59');
    foreach (ar_statement_invoices($pdo, $factory, $accountId, $asD)['rows'] as $r) {
        if ((string) $r['due_date'] !== '' && strtotime((string) $r['due_date'])) {
            $dueTs = strtotime((string) $r['due_date']);
        } elseif ((string) $r['issue_date'] !== '' && strtotime((string) $r['issue_date'])) {
            $dueTs = strtotime((string) $r['issue_date'] . ' +30 days');
        } else {
            $dueTs = $asTs;                                   // undateable → treat as current
        }
        $late = (int) floor(($asTs - $dueTs) / 86400);
        $amt  = (float) $r['outstanding'];
        if     ($late <= 0)  $z['current'] += $amt;
        elseif ($late <= 30) $z['d30']     += $amt;
        elseif ($late <= 60) $z['d60']     += $amt;
        elseif ($late <= 90) $z['d90']     += $amt;
        else                 $z['d90plus'] += $amt;
    }
    foreach (['current','d30','d60','d90','d90plus'] as $k) $z[$k] = round($z[$k], 2);
    $z['total'] = round($z['current'] + $z['d30'] + $z['d60'] + $z['d90'] + $z['d90plus'], 2);
    return $z;
}

/**
 * Every trade account that has an outstanding balance as at a date, biggest debtor
 * first — drives the statement run (who gets a statement) and the aged-debtors
 * report. Accounts with nothing owed are dropped.
 *   Returns [{account_id, name, email, aging}] where aging is ar_aging()'s shape.
 */
function ar_statement_accounts(PDO $pdo, int $factory, string $asAt = ''): array
{
    if (!ar_table_ready($pdo, 'factory_ar_invoices')) return [];
    $st = $pdo->prepare(
        "SELECT DISTINCT account_client_id FROM factory_ar_invoices
          WHERE factory_client_id = ? AND status <> 'void' AND account_client_id <> ?"
    );
    $st->execute([$factory, $factory]);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $out = [];
    $nm  = $pdo->prepare('SELECT company_name, email FROM clients WHERE id = ? LIMIT 1');
    foreach ($ids as $accId) {
        $aging = ar_aging($pdo, $factory, $accId, $asAt);
        if ($aging['total'] <= 0.004) continue;
        $nm->execute([$accId]);
        $c = $nm->fetch(PDO::FETCH_ASSOC) ?: [];
        $out[] = [
            'account_id' => $accId,
            'name'       => (string) ($c['company_name'] ?? ('Account ' . $accId)),
            'email'      => (string) ($c['email'] ?? ''),
            'aging'      => $aging,
        ];
    }
    usort($out, static fn ($a, $b) => $b['aging']['total'] <=> $a['aging']['total']);
    return $out;
}

/**
 * Assemble the render context + data for ONE account's open-item statement, so the
 * single PDF, the combined run PDF and the screen all build it identically.
 *   Returns ['ctx'=>[...], 'data'=>['invoices'=>[...],'total_outstanding'=>,'aging'=>[...]]]
 *   or null if the account isn't a valid trade account.
 */
function ar_statement_bundle(PDO $pdo, int $factory, int $accountId, string $asAt = ''): ?array
{
    if ($accountId <= 0 || $accountId === $factory) return null;
    $ac = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $ac->execute([$accountId]);
    $acc = $ac->fetch(PDO::FETCH_ASSOC);
    if (!$acc) return null;

    $fac = [];
    try {
        $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $fs->execute([$factory]);
        $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { /* letterhead degrades */ }

    $billTo = ar_account_address_block($acc);
    if (($acc['vat_number'] ?? '') !== '') $billTo .= "\nVAT No. " . $acc['vat_number'];

    $inv   = ar_statement_invoices($pdo, $factory, $accountId, $asAt);
    $aging = ar_aging($pdo, $factory, $accountId, $asAt);
    $toD   = ($asAt !== '' && strtotime($asAt)) ? date('Y-m-d', strtotime($asAt)) : date('Y-m-d');

    return [
        'ctx' => [
            'factory'        => $fac,
            'account_name'   => (string) ($acc['company_name'] ?? ''),
            'acc_ref'        => (string) ($acc['company_name'] ?? ('A' . $accountId)),   // Stage 1: name as ref
            'bill_to'        => $billTo,
            'statement_date' => $toD,
            'to'             => $toD,
        ],
        'data' => [
            'invoices'          => $inv['rows'],
            'total_outstanding' => $inv['total_outstanding'],
            'aging'             => $aging,
        ],
    ];
}

/* ── Statement run Stage 2: bulk-email send log ─────────────────────────── */

/** The statement-email send log table exists? */
function ar_statement_email_ready(PDO $pdo): bool
{
    return ar_table_ready($pdo, 'factory_ar_statement_emails');
}

/**
 * Latest send status per account for a given run date — [account_id => status]
 * ('sent'|'failed'|'skipped'). Drives the "already emailed" indicator and the
 * double-send guard.
 */
function ar_statement_emailed_map(PDO $pdo, int $factory, string $periodTo): array
{
    if (!ar_statement_email_ready($pdo)) return [];
    $d = ($periodTo !== '' && strtotime($periodTo)) ? date('Y-m-d', strtotime($periodTo)) : date('Y-m-d');
    $st = $pdo->prepare(
        "SELECT e.account_client_id, e.status
           FROM factory_ar_statement_emails e
           JOIN (SELECT account_client_id, MAX(id) AS mid
                   FROM factory_ar_statement_emails
                  WHERE factory_client_id = ? AND period_to = ? GROUP BY account_client_id) last
             ON last.mid = e.id"
    );
    $st->execute([$factory, $d]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['account_client_id']] = (string) $r['status'];
    return $out;
}

/** Record one statement-email outcome (audit + double-send guard). */
function ar_log_statement_email(PDO $pdo, int $factory, int $accountId, string $periodTo,
                                string $toEmail, float $closing, string $status, int $userId): void
{
    if (!ar_statement_email_ready($pdo)) return;
    $d = ($periodTo !== '' && strtotime($periodTo)) ? date('Y-m-d', strtotime($periodTo)) : date('Y-m-d');
    try {
        $pdo->prepare(
            "INSERT INTO factory_ar_statement_emails
               (factory_client_id, account_client_id, period_to, to_email, closing, status, sent_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$factory, $accountId, $d, $toEmail !== '' ? $toEmail : null,
                    round($closing, 2), $status, $userId ?: null]);
    } catch (Throwable $e) { /* logging must never break a run */ }
}

/**
 * Commission statement for a consultant over a period (2E). For each of the
 * consultant's active trade_commissions rows, turnover = the account's INVOICED
 * net (issue_date in period, non-void invoices) for that product — or ALL products
 * when the rule's product is NULL — and commission = turnover × %. Products matched
 * by MASTER id on both sides (the rule's product may be a mirrored copy).
 *   $from '' = from the beginning; $to '' = today.
 * Returns ['from','to','rows'=>[{account_id,account_name,product_name,turnover,percent,commission}],'total'].
 */
function ar_commission_statement(PDO $pdo, int $factory, int $consultantId, string $from = '', string $to = ''): array
{
    $out = ['from' => null, 'to' => date('Y-m-d'), 'rows' => [], 'total' => 0.0];
    if (!ar_table_ready($pdo, 'trade_commissions') || !ar_table_ready($pdo, 'factory_ar_invoices')) return $out;

    $to    = ($to !== '' && strtotime($to)) ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
    $fromD = ($from !== '' && strtotime($from)) ? date('Y-m-d', strtotime($from)) : null;
    $out['from'] = $fromD; $out['to'] = $to;

    $rc = $pdo->prepare(
        "SELECT tc.client_id, tc.product_id, tc.commission_percent, c.company_name
           FROM trade_commissions tc JOIN clients c ON c.id = tc.client_id
          WHERE tc.consultant_id = ? AND tc.active = 1
       ORDER BY c.company_name, (tc.product_id IS NULL) DESC, tc.product_id"
    );
    $rc->execute([$consultantId]);
    $rules = $rc->fetchAll(PDO::FETCH_ASSOC);
    if (!$rules) return $out;

    // Per-account invoiced turnover for the period: total + by master product id.
    $accCache = [];
    $loadAcc = function (int $accountId) use ($pdo, $factory, $fromD, $to, &$accCache): array {
        if (isset($accCache[$accountId])) return $accCache[$accountId];
        $cond   = "i.factory_client_id = ? AND i.account_client_id = ? AND i.status <> 'void'";
        $params = [$factory, $accountId];
        if ($fromD !== null) { $cond .= " AND COALESCE(i.issue_date, DATE(i.created_at)) >= ?"; $params[] = $fromD; }
        $cond .= " AND COALESCE(i.issue_date, DATE(i.created_at)) <= ?"; $params[] = $to;

        $t = $pdo->prepare("SELECT COALESCE(SUM(il.line_net),0)
                              FROM factory_ar_invoices i JOIN factory_ar_invoice_lines il ON il.invoice_id = i.id
                             WHERE $cond");
        $t->execute($params);
        $total = round((float) $t->fetchColumn(), 2);

        $b = $pdo->prepare("SELECT COALESCE(p.source_product_id, p.id) mpid, COALESCE(SUM(il.line_net),0) net
                              FROM factory_ar_invoices i
                              JOIN factory_ar_invoice_lines il ON il.invoice_id = i.id
                              JOIN quote_items qi ON qi.id = il.source_quote_item_id
                              JOIN products p ON p.id = qi.product_id
                             WHERE $cond GROUP BY mpid");
        $b->execute($params);
        $byP = [];
        foreach ($b->fetchAll(PDO::FETCH_ASSOC) as $r) $byP[(int) $r['mpid']] = round((float) $r['net'], 2);
        return $accCache[$accountId] = ['total' => $total, 'byP' => $byP];
    };

    $masterOf = function (int $pid) use ($pdo): array {
        $s = $pdo->prepare("SELECT COALESCE(source_product_id, id) mid, name FROM products WHERE id = ? LIMIT 1");
        $s->execute([$pid]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ? [(int) $r['mid'], (string) $r['name']] : [0, ''];
    };

    foreach ($rules as $rule) {
        $acc = $loadAcc((int) $rule['client_id']);
        $pct = (float) $rule['commission_percent'];
        if ($rule['product_id'] === null) {
            $turnover = $acc['total'];
            $pname    = 'All products';
        } else {
            [$mid, $pname] = $masterOf((int) $rule['product_id']);
            $turnover      = $acc['byP'][$mid] ?? 0.0;
        }
        $comm = round($turnover * $pct / 100, 2);
        $out['rows'][] = [
            'account_id'   => (int) $rule['client_id'],
            'account_name' => (string) $rule['company_name'],
            'product_name' => $pname,
            'turnover'     => round($turnover, 2),
            'percent'      => $pct,
            'commission'   => $comm,
        ];
        $out['total'] = round($out['total'] + $comm, 2);
    }
    return $out;
}
