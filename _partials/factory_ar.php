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

require_once __DIR__ . '/delivery_charges.php';

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
 * The account's DELIVERY address as a newline block, for delivery notes. When the
 * account has set a separate delivery address (client_settings.supplier_delivery_address
 * — "where suppliers ship to") that wins; otherwise the main address. The setting is
 * free text, so it can hold real newlines, CRLFs or a literal "\n" typed/imported as
 * two characters — all normalised to one line per address line, blanks dropped. The
 * company name is put on top unless the text already starts with it.
 */
function ar_account_delivery_block(PDO $pdo, array $acc): string
{
    $raw = '';
    $accId = (int) ($acc['id'] ?? 0);
    if ($accId > 0) {
        try {
            $s = $pdo->prepare('SELECT supplier_delivery_address FROM client_settings WHERE client_id = ? LIMIT 1');
            $s->execute([$accId]);
            $raw = (string) ($s->fetchColumn() ?: '');
        } catch (Throwable $e) { $raw = ''; }   // column absent pre-migration → main address
    }
    $raw = str_replace(['\\r\\n', '\\n', '\\r', "\r\n", "\r"], "\n", $raw);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), static fn ($s) => $s !== ''));
    if (!$lines) return ar_account_address_block($acc);

    $company = trim((string) ($acc['company_name'] ?? ''));
    if ($company !== '' && stripos($lines[0], $company) !== 0) array_unshift($lines, $company);
    return implode("\n", $lines);
}

/**
 * The factory's VAT rate for its trade invoices — client_settings.vat_percent of the
 * factory itself (0 when it isn't VAT registered), defaulting to 20 when there is no
 * settings row / column yet.
 */
function ar_factory_vat_percent(PDO $pdo, int $factory): float
{
    try {
        $s = $pdo->prepare('SELECT vat_percent FROM client_settings WHERE client_id = ? LIMIT 1');
        $s->execute([$factory]);
        $v = $s->fetchColumn();
        if ($v !== false && $v !== null && $v !== '' && is_numeric($v)) {
            return round(max(0.0, min(100.0, (float) $v)), 2);
        }
    } catch (Throwable $e) { /* fall through to the default */ }
    return 20.00;
}

/**
 * A balance worded for people: "£120.00" when owed, "£52.18 in credit" when the
 * account has paid/been credited more than it owes, "£0.00" when square. Plain
 * text (escape at output). Every A/R screen uses it so none shows "£-52.18".
 */
function ar_balance_label(float $amount): string
{
    $amount = round($amount, 2);
    if ($amount < -0.004) return '£' . number_format(-$amount, 2) . ' in credit';
    return '£' . number_format(max(0.0, $amount), 2);
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
 * wholesale_total (Σ line_total — the account's sell price), and dn_count (delivery notes
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
    // wholesale_total is now SUM(line_total) (the account's actual sell price,
    // incl. options + account discount), so the old options-trade_amount subquery
    // and its placeholder are gone. Placeholders bind in SQL text order:
    // [1] account<>, [optional account/from/to], [last] main ownership.
    $args = [];

    $dnReady = ar_table_ready($pdo, 'factory_ar_delivery_notes');
    $dnSel   = $dnReady
        ? "(SELECT COUNT(*) FROM factory_ar_delivery_notes dn WHERE dn.source_quote_id = q.id AND dn.status <> 'cancelled')"
        : '0';

    $where = "q.status IN ($in) AND {$acct} <> ?";
    $args[] = $factoryId;   // [2] exclude the factory's own (retail) quotes
    if ($accountId) { $where .= " AND {$acct} = ?"; $args[] = $accountId; }
    if ($from !== null && $from !== '') { $where .= ' AND q.created_at >= ?';                       $args[] = $from; }
    if ($to   !== null && $to   !== '') { $where .= ' AND q.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $args[] = $to; }

    // wholesale_total = what the INVOICE will bill, on the same basis as
    // ar_invoice_lines_from_order(): a PORTAL order (the account's own quote,
    // client_id <> factory) is billed at trade — (trade_price_per_blind − their
    // buying discount + options' trade_amount) × qty + the flat line charge —
    // because its line_total is the account's own RETAIL price (≈2× wholesale).
    // A Beverley-raised order bills line_total, reconciled below (in PHP) to the
    // order's stored net so an agreed price_override is honoured.
    // The CASE carries no placeholders, so the bind order above is unchanged.
    $optJoin =
        "LEFT JOIN (SELECT e.quote_item_id, SUM(e.trade_amount) AS opt_trade
                      FROM quote_item_extras e
                      JOIN quote_items qi2 ON qi2.id = e.quote_item_id
                      JOIN quotes q2       ON q2.id = qi2.quote_id
                     WHERE q2.status IN ($in)
                  GROUP BY e.quote_item_id) ex ON ex.quote_item_id = qi.id";
    $billExpr =
        "CASE WHEN q.client_id <> {$factoryId}
              THEN ROUND(COALESCE(qi.trade_price_per_blind, qi.sell_price) - COALESCE(ROUND(qi.trade_discount_amount, 2), 0)
                         + COALESCE(ex.opt_trade, 0), 2) * GREATEST(1, qi.quantity)
                   + (qi.line_total - ROUND(qi.sell_price, 2) * GREATEST(1, qi.quantity))
              ELSE qi.line_total END";

    $sqlFor = static function (bool $trade) use ($acct, $dnSel, $where, $optJoin, $billExpr): string {
        return
        "SELECT q.id, q.quote_number, q.status, q.created_at, q.client_id AS quote_client_id,
                {$acct} AS account_id, c.company_name AS account_name,
                COUNT(qi.id)                  AS bev_lines,
                COALESCE(SUM(qi.quantity), 0) AS bev_qty,
                COALESCE(SUM(" . ($trade ? $billExpr : 'qi.line_total') . "), 0) AS wholesale_total,
                " . ($trade ? "q.subtotal AS order_subtotal,
                (SELECT COALESCE(SUM(a.line_total),0) FROM quote_items a WHERE a.quote_id = q.id) AS order_lines_total," : '') . "
                $dnSel AS dn_count
           FROM quotes q
           JOIN clients c      ON c.id = {$acct}
           JOIN quote_items qi ON qi.quote_id = q.id
           JOIN products p     ON p.id = qi.product_id
           " . ($trade ? $optJoin : '') . "
          WHERE $where AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
       GROUP BY q.id, q.quote_number, q.status, q.created_at, q.client_id, {$acct}, c.company_name" . ($trade ? ', q.subtotal' : '') . "
       ORDER BY q.created_at DESC, q.id DESC
          LIMIT 500";
    };
    $args[] = $factoryId;   // [last] main-filter ownership

    try {
        $st = $pdo->prepare($sqlFor(true));
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Pre-capture schema (no trade columns / subtotal) — fall back to line_total.
        try {
            $st = $pdo->prepare($sqlFor(false));
            $st->execute($args);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
            return [];
        }
    }

    // Beverley-raised orders: reconcile to the order's stored net, pro-rated by our
    // share — exactly ar_invoice_lines_from_order()'s adjust line.
    foreach ($rows as &$r) {
        $r['wholesale_total'] = round((float) $r['wholesale_total'], 2);
        if ((int) ($r['quote_client_id'] ?? 0) === $factoryId
            && isset($r['order_subtotal']) && $r['order_subtotal'] !== null
            && (float) ($r['order_lines_total'] ?? 0) > 0.005) {
            $share = (float) $r['wholesale_total'] / (float) $r['order_lines_total'];
            $r['wholesale_total'] = round((float) $r['order_subtotal'] * $share, 2);
        }
    }
    unset($r);
    return $rows;
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
                qi.notes, qi.base_price, qi.sell_price, qi.line_total,
                qi.trade_price_per_blind,
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
    // user_value = the typed number on a measurement option (Fit Height, a wand
    // length) — without it the note printed "+ Fit Height" with no height. Widen
    // the SELECT progressively so a pre-migration schema still gets its options.
    foreach ([
        'quote_item_id, extra_name_snapshot, choice_label_snapshot, user_value, amount_applied, trade_amount',
        'quote_item_id, extra_name_snapshot, choice_label_snapshot, amount_applied, trade_amount',
    ] as $exCols) {
        try {
            $ex = $pdo->prepare(
                "SELECT $exCols
                   FROM quote_item_extras
                  WHERE quote_item_id IN ($ph)
               ORDER BY id"
            );
            $ex->execute($ids);
            foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $exBy[(int) $e['quote_item_id']][] = $e;
            }
            break;
        } catch (Throwable $e) { $exBy = []; /* column / table absent — try narrower, else leave empty */ }
    }

    foreach ($lines as &$ln) {
        $opts = [];
        foreach ($exBy[(int) $ln['id']] ?? [] as $e) {
            $name  = trim((string) $e['extra_name_snapshot']);
            $label = trim((string) $e['choice_label_snapshot']);
            $uv    = $e['user_value'] ?? null;
            $val   = (is_numeric($uv) && (float) $uv > 0)
                ? rtrim(rtrim(number_format((float) $uv, 2, '.', ''), '0'), '.') : '';
            if ($val !== '') $label = $label !== '' ? ($label . ' (' . $val . ')') : $val;
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

    // WHICH BASIS DO WE BILL THIS ACCOUNT ON?
    //
    // An order BEVERLEY raised FOR an account is priced with the account in mind
    // ($forAccountId > 0 in the engine): sell_price is already what that account
    // pays, and trade_price_per_blind / trade_discount_amount are the sell before
    // and after their account discount. Billing sell_price is right.
    //
    // An order the account placed through THEIR OWN PORTAL is priced for THEIR
    // customer. pricing_engine.php:1462 assigns only $sellPrice on that path, so
    // sell_price is the tenant's RETAIL price — their own markup included — while
    // trade_price_per_blind (set back at :1272) is still Beverley's trade price to
    // them, and trade_discount_* their buying discount. Billing sell_price there
    // charged a trade account its own retail price, close to double what it owed.
    // Confirmed on a live document 2026-09-20: list £57.20, buying discount 15%,
    // and the invoice billing £97.24.
    //
    // So: portal order → bill the trade price (discounted base + options at their
    // trade amount). Beverley-raised order → bill sell, as before.
    $isPortal = true;
    try {
        $c = $pdo->prepare('SELECT client_id FROM quotes WHERE id = ? LIMIT 1');
        $c->execute([$quoteId]);
        $isPortal = ((int) $c->fetchColumn()) !== $factoryId;
    } catch (Throwable $e) {
        $isPortal = false;   // can't tell — keep the old behaviour rather than guess
    }

    foreach ($src as $ln) {
        $qty    = max(1, (int) $ln['quantity']);
        $optNet = 0.0;
        foreach ($ln['extras_rows'] ?? [] as $ex) {
            $ta      = $ex['trade_amount'];
            $applied = (float) ($ex['amount_applied'] ?? 0);
            if ($ta === null) { if ($applied > 0) $uncaptured = true; continue; }
            $optNet += (float) $ta;
        }
        // Bill the account our SELL price — the same figure the quote shows.
        // sell_price is per blind, incl. options and any per-account discount;
        // trade_price_per_blind is the sell BEFORE their account discount and
        // trade_discount_amount the £ off (both sell-basis, set by the engine).
        // ($optNet is still summed above only to flag uncaptured options.)
        //
        // line_net comes from the STORED line_total, not sell_price × qty. The
        // engine builds it as `round($sellPrice * $quantity + $lineCharge, 2)`
        // (pricing_engine.php:1470) — products.line_charge is a flat £ added
        // ONCE per line, after the × quantity step. Recomputing here silently
        // dropped it and under-billed the account on every product that carries
        // one (e.g. Arena "Louvres Only" £6.98 per set).
        //
        // unit_net stays the per-blind sell price, so unit × qty can differ from
        // line_net by that flat charge. That is exactly what the customer's own
        // quote shows, so the two documents agree.
        // The flat per-line charge, recovered from what the engine stored rather
        // than re-reading products.line_charge: line_total = sell × qty + charge.
        // It is pass-through (never marked up), so it bills the same either way.
        $lineCharge = round((float) $ln['line_total'] - round((float) $ln['sell_price'], 2) * $qty, 2);

        if ($isPortal) {
            $tradeUnit = $ln['trade_price_per_blind'];
            if ($tradeUnit === null) {
                // Pre-capture line: we have no trade price, and billing the retail
                // one would overcharge. Flag it so ar_create_invoice refuses and
                // asks for the line to be re-saved, rather than inventing a figure.
                $uncaptured = true;
                $tradeUnit  = (float) $ln['sell_price'];
            }
            $discAmt  = round((float) ($ln['trade_discount_amount'] ?? 0), 2);
            $unitNet  = round(((float) $tradeUnit - $discAmt) + $optNet, 2);
            $lineNet  = round($unitNet * $qty + $lineCharge, 2);
            // List = trade before their buying discount, plus options (options are
            // never discounted), so List − Discount = Net reads correctly on the PDF.
            $listUnit = round((float) $tradeUnit + $optNet, 2);
        } else {
            $unitNet  = round((float) $ln['sell_price'], 2);
            $lineNet  = round((float) $ln['line_total'], 2);
            $listUnit = round((float) ($ln['trade_price_per_blind'] ?? $ln['sell_price']), 2);
        }

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

    // ---- Reconcile the invoice to the ORDER's real net -----------------------
    // Summing the billed lines is not the order's net. Two things live above the
    // line level and were both being dropped:
    //
    //   * quotes.price_override — the agreed NET price ("I'll do it for £X").
    //     qb_recompute_totals() assigns it straight to quotes.subtotal and adds
    //     VAT on top (_helpers.php), and the customer's quote shows the gap as a
    //     single "Discount" line. The invoice ignored it entirely and billed the
    //     full list, so an agreed price was never honoured.
    //     (NB migrate_quote_price_override.php's header calls it inc-VAT; the
    //     code is the authority and treats it as net.)
    //   * the Wally tax (WT charge) — folded into the stored subtotal, never a
    //     line of its own, so a line-by-line rebuild loses it.
    //
    // So: take the order's stored net as the truth and add ONE adjust line for the
    // difference, mirroring what the quote already showed. Pro-rated by the billed
    // share, because a quote can hold lines this factory does not own and we must
    // only ever bill our own portion of an order-level figure.
    //
    // ONLY on the sell basis. quotes.subtotal is the tenant's own RETAIL total on
    // a portal order, so reconciling a trade-priced invoice to it would add an
    // "Adjustment" dragging the bill straight back up to retail — undoing the
    // whole point. A portal invoice is built from trade components and needs no
    // order-level reconcile; the tenant's own price_override and internal
    // surcharge are theirs, not Beverley's to bill.
    if ($lines && !$isPortal) {
        try {
            $qs = $pdo->prepare('SELECT subtotal FROM quotes WHERE id = ? LIMIT 1');
            $qs->execute([$quoteId]);
            $orderNet = $qs->fetchColumn();

            $as = $pdo->prepare('SELECT COALESCE(SUM(line_total),0) FROM quote_items WHERE quote_id = ?');
            $as->execute([$quoteId]);
            $allLinesNet = (float) $as->fetchColumn();

            $billedNet = 0.0;
            foreach ($lines as $l) $billedNet += (float) $l['line_net'];

            if ($orderNet !== false && $orderNet !== null && $allLinesNet > 0.005) {
                $share  = $billedNet / $allLinesNet;          // our slice of the order
                $target = round(((float) $orderNet) * $share, 2);
                $adjust = round($target - round($billedNet, 2), 2);

                if (abs($adjust) >= 0.01) {
                    $lines[] = [
                        'source_quote_id'      => $quoteId,
                        'source_quote_item_id' => null,
                        'line_type'            => 'adjust',
                        'description'          => $adjust < 0 ? 'Discount — agreed price' : 'Adjustment',
                        'width_mm'             => null,
                        'drop_mm'              => null,
                        'quantity'             => 1,
                        'unit_net'             => $adjust,
                        'line_net'             => $adjust,
                        'list_trade_unit'      => null,
                        'discount_percent'     => null,
                        'discount_amount'      => null,
                        'sort_order'           => $so++,
                    ];
                }
            }
        } catch (Throwable $e) {
            // quotes.subtotal or quote_items.line_total absent on an un-migrated
            // database — bill the lines as they stand rather than failing to invoice.
            error_log('ar_invoice_lines_from_order: order-net reconcile skipped — ' . $e->getMessage());
        }
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
function ar_create_delivery_note(PDO $pdo, int $factory, int $quoteId, int $accountId, int $userId, bool $dispatched = false, ?string $method = null): array
{
    // Delivery method for THIS note: the tray's choice, else the account's default.
    if ($method === null || !isset(dc_methods()[$method])) $method = dc_account_terms($pdo, $accountId)['method'];
    $lines = ar_order_lines_for_doc($pdo, $factory, $quoteId);
    if (!$lines) throw new RuntimeException('This order has no Beverley-owned lines to deliver.');

    $ac = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $ac->execute([$accountId]);
    $acc  = $ac->fetch(PDO::FETCH_ASSOC) ?: [];
    // Ship-to: the account's own delivery address when it has one set, else main.
    $addr = ar_account_delivery_block($pdo, $acc);

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

        // Join today's delivery for this account + method, and re-work its charge.
        if (dc_ready($pdo)) {
            $today = date('Y-m-d');
            $pdo->prepare('UPDATE factory_ar_delivery_notes SET delivery_method = ?, delivery_date = ? WHERE id = ?')
                ->execute([$method, $today, $dnId]);
            dc_recalc_delivery($pdo, $factory, $accountId, $today, $method);
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
 * order, with a gap-free INV number, per-document VAT at the factory's own rate
 * (client_settings.vat_percent, default 20), due +30 days. It is always created
 * 'raised'. $send then EMAILS it to the account (ar_send_invoice) once committed,
 * and only a successful email marks it 'sent' — so the one-step / auto-invoice
 * flows can never claim an invoice went out when it didn't. When called inside a
 * caller's transaction the email is NOT attempted (it would go out before the
 * caller commits); 'send_pending' => true tells the caller to call
 * ar_send_invoice() after its own commit.
 * Returns ['id','number','total','sent'=>bool,'send_message'=>string,'send_pending'=>bool].
 * Throws RuntimeException on a business problem (no owned lines; a priced option
 * with no captured wholesale price — an uncaptured pre-2A order) or PDOException.
 * The caller MUST have guarded against double-invoicing (see
 * factory_ar_invoice_orders) before calling.
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
    $vatPct = ar_factory_vat_percent($pdo, $factory);

    $subtotal = 0.0;
    foreach ($built['lines'] as $l) $subtotal += (float) $l['line_net'];
    $subtotal = round($subtotal, 2);
    $vat      = round($subtotal * $vatPct / 100, 2);
    $total    = round($subtotal + $vat, 2);
    $status   = 'raised';   // 'sent' only once the email has actually gone

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
                    $factory, $accountId, $num, $status, null,
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

        // The delivery charge fixed at dispatch rides on this order's invoice
        // when its note is the one carrying it (see dc_recalc_delivery).
        $carr = dc_order_carriage($pdo, $factory, $quoteId);
        if ($carr['amount'] > 0) {
            $insL->execute([
                $invId, null, null, 'carriage', $carr['description'],
                null, null, 1, $carr['amount'], $carr['amount'], null, null, null, count($built['lines']) + 1,
            ]);
            ar_invoice_recalc_totals($pdo, $invId);
            $t = $pdo->prepare('SELECT total FROM factory_ar_invoices WHERE id = ?');
            $t->execute([$invId]);
            $total = round((float) $t->fetchColumn(), 2);
        }

        if ($ownTxn) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $res = ['id' => $invId, 'number' => $num, 'total' => $total,
            'sent' => false, 'send_message' => '', 'send_pending' => false];
    if ($send) {
        if ($ownTxn) {
            $s = ar_send_invoice($pdo, $factory, $invId);
            $res['sent'] = $s['ok'];
            $res['send_message'] = $s['message'];
        } else {
            $res['send_pending'] = true;
        }
    }
    return $res;
}

/* ── Invoice / credit-note PDFs + emailing ─────────────────────────────────── */

/** The factory's bank/payment block for invoice footers ('' when none set). */
function ar_factory_bank_block(PDO $pdo, int $factory): string
{
    try {
        $bs = $pdo->prepare('SELECT bank_account_name, bank_sort_code, bank_account_number, payment_instructions FROM client_settings WHERE client_id = ? LIMIT 1');
        $bs->execute([$factory]);
        if ($b = $bs->fetch(PDO::FETCH_ASSOC)) {
            $bankLines = array_values(array_filter([
                ($b['bank_account_name'] ?? '') !== '' ? 'Account: ' . $b['bank_account_name'] : '',
                ($b['bank_sort_code'] ?? '') !== '' ? 'Sort code: ' . $b['bank_sort_code'] : '',
                ($b['bank_account_number'] ?? '') !== '' ? 'Account no: ' . $b['bank_account_number'] : '',
                (string) ($b['payment_instructions'] ?? ''),
            ], static fn ($s) => trim((string) $s) !== ''));
            return implode("\n", $bankLines);
        }
    } catch (Throwable $e) { /* no bank details */ }
    return '';
}

/** The factory's clients row (letterhead). */
function ar_factory_row(PDO $pdo, int $factory): array
{
    try {
        $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $fs->execute([$factory]);
        return $fs->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];   // letterhead degrades
    }
}

/**
 * Render one invoice to PDF bytes — the same document invoice-pdf.php streams and
 * ar_send_invoice() attaches. Returns ['pdf'=>bytes,'inv'=>row,'filename'=>…] or
 * null (not found for this factory / PDF engine unavailable).
 */
function ar_invoice_pdf(PDO $pdo, int $factory, int $invId): ?array
{
    require_once __DIR__ . '/../pdf-generator/ar_pdf.php';
    $st = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $st->execute([$invId, $factory]);
    $inv = $st->fetch(PDO::FETCH_ASSOC);
    if (!$inv) return null;

    $lines = [];
    try {
        $ls = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
        $ls->execute([$invId]);
        $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* none */ }

    $orderRef = '';
    try {
        $os = $pdo->prepare(
            'SELECT q.quote_number FROM factory_ar_invoice_orders io
               JOIN quotes q ON q.id = io.quote_id WHERE io.invoice_id = ? ORDER BY io.id'
        );
        $os->execute([$invId]);
        $orderRef = implode(', ', array_filter(array_column($os->fetchAll(PDO::FETCH_ASSOC), 'quote_number')));
    } catch (Throwable $e) { /* optional */ }

    $ctx = [
        'factory'     => ar_factory_row($pdo, $factory),
        'doc_title'   => 'INVOICE',
        'doc_number'  => (string) $inv['inv_number'],
        'issue_date'  => $inv['issue_date'] ? date('j F Y', strtotime((string) $inv['issue_date'])) : date('j F Y', strtotime((string) $inv['created_at'])),
        'due_date'    => $inv['due_date'] ? date('j F Y', strtotime((string) $inv['due_date'])) : '',
        'bill_to'     => (string) ($inv['bill_to_snapshot'] ?? ''),
        'order_ref'   => $orderRef,
        'vat_percent' => (float) $inv['vat_percent'],
        'notes'       => (string) ($inv['notes'] ?? ''),
        'bank'        => ar_factory_bank_block($pdo, $factory),
        'watermark'   => $inv['status'] === 'void' ? 'VOID' : '',
    ];
    $totals = ['subtotal' => (float) $inv['subtotal'], 'vat' => (float) $inv['vat'], 'total' => (float) $inv['total']];

    $pdf = ar_render_invoice($ctx, $lines, $totals);
    if ($pdf === null) return null;
    return ['pdf' => $pdf, 'inv' => $inv,
            'filename' => preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $inv['inv_number']) . '.pdf'];
}

/** Render one credit note to PDF bytes. Same shape as ar_invoice_pdf() ('cn' row). */
function ar_credit_note_pdf(PDO $pdo, int $factory, int $cnId): ?array
{
    require_once __DIR__ . '/../pdf-generator/ar_pdf.php';
    $st = $pdo->prepare('SELECT * FROM factory_ar_credit_notes WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $st->execute([$cnId, $factory]);
    $cn = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cn) return null;

    $lines = [];
    try {
        $ls = $pdo->prepare('SELECT * FROM factory_ar_credit_note_lines WHERE credit_note_id = ? ORDER BY sort_order, id');
        $ls->execute([$cnId]);
        $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* none */ }

    $againstRef = '';
    if (!empty($cn['against_invoice_id'])) {
        try {
            $q = $pdo->prepare('SELECT inv_number FROM factory_ar_invoices WHERE id = ? LIMIT 1');
            $q->execute([(int) $cn['against_invoice_id']]);
            $againstRef = (string) ($q->fetchColumn() ?: '');
        } catch (Throwable $e) { /* optional */ }
    }

    $notes = trim((string) ($cn['reason'] ?? ''));
    if ($againstRef !== '') $notes = ($notes !== '' ? $notes . ' · ' : '') . 'Credit against ' . $againstRef;
    if ((string) ($cn['settle_mode'] ?? 'credit') === 'refund') {
        $notes = ($notes !== '' ? $notes . ' · ' : '') . 'Settled by refund to you (not left as credit on your account).';
    }

    $ctx = [
        'factory'     => ar_factory_row($pdo, $factory),
        'doc_title'   => 'CREDIT NOTE',
        'doc_number'  => (string) $cn['cn_number'],
        'issue_date'  => $cn['issue_date'] ? date('j F Y', strtotime((string) $cn['issue_date'])) : date('j F Y', strtotime((string) $cn['created_at'])),
        'due_date'    => '',
        'bill_to'     => (string) ($cn['bill_to_snapshot'] ?? ''),
        'order_ref'   => $againstRef,
        'vat_percent' => (float) $cn['vat_percent'],
        'notes'       => $notes,
        'bank'        => '',
        'watermark'   => $cn['status'] === 'void' ? 'VOID' : '',
    ];
    $totals = ['subtotal' => (float) $cn['subtotal'], 'vat' => (float) $cn['vat'], 'total' => (float) $cn['total']];

    $pdf = ar_render_invoice($ctx, $lines, $totals);
    if ($pdf === null) return null;
    return ['pdf' => $pdf, 'cn' => $cn,
            'filename' => preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $cn['cn_number']) . '.pdf'];
}

/**
 * Shared guard + send for emailing an A/R document to its trade account: the
 * account's clients.email must be a valid address, and emails must not be paused
 * (mailer_send() reports "success" while paused, which would mark a document sent
 * that never left). Returns ['ok'=>bool,'message'=>string,'email'=>string].
 */
function ar_email_account_document(PDO $pdo, int $factory, int $accountId, string $subject, string $body,
                                   string $pdf, string $filename): array
{
    require_once __DIR__ . '/../mailer.php';
    $ac = $pdo->prepare('SELECT company_name, email FROM clients WHERE id = ? LIMIT 1');
    $ac->execute([$accountId]);
    $acc   = $ac->fetch(PDO::FETCH_ASSOC) ?: [];
    $email = trim((string) ($acc['email'] ?? ''));
    $name  = trim((string) ($acc['company_name'] ?? '')) ?: ('account ' . $accountId);

    if ($email === '') {
        return ['ok' => false, 'email' => '', 'message' => $name . ' has no email address on file — add one to the account, then send again.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'email' => $email, 'message' => $name . "'s email address (" . $email . ') is not valid — fix it on the account, then send again.'];
    }
    if (function_exists('app_setting_on') && app_setting_on('email_paused')) {
        return ['ok' => false, 'email' => $email, 'message' => 'Emails are paused (testing mode) — nothing was sent. Turn the pause off in Master admin to send for real.'];
    }
    $ok = mailer_send($email, $subject, $body, ['content' => $pdf, 'filename' => $filename, 'mime' => 'application/pdf']);
    return $ok
        ? ['ok' => true,  'email' => $email, 'message' => 'Emailed to ' . $email . '.']
        : ['ok' => false, 'email' => $email, 'message' => 'The email to ' . $email . ' could not be sent (mail server error) — try again shortly.'];
}

/**
 * EMAIL an invoice PDF to its trade account and, only if the email went, mark it
 * sent (status raised/draft → 'sent'; a part-paid/paid invoice keeps its status
 * and just gets sent_at stamped the first time). A re-send of an already-sent
 * invoice just emails it again. Void invoices are refused.
 * Returns ['ok'=>bool,'message'=>string].
 */
function ar_send_invoice(PDO $pdo, int $factory, int $invId): array
{
    try {
        $doc = ar_invoice_pdf($pdo, $factory, $invId);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not build the invoice PDF: ' . $e->getMessage()];
    }
    if ($doc === null) return ['ok' => false, 'message' => 'Invoice not found, or the PDF engine is unavailable.'];
    $inv = $doc['inv'];
    if ((string) $inv['status'] === 'void') return ['ok' => false, 'message' => 'Invoice ' . $inv['inv_number'] . ' is void — it cannot be sent.'];

    $fac     = ar_factory_row($pdo, $factory);
    $facName = trim((string) ($fac['company_name'] ?? ''));
    $due     = $inv['due_date'] ? date('j M Y', strtotime((string) $inv['due_date'])) : '';
    $subject = 'Invoice ' . $inv['inv_number'] . ($facName !== '' ? ' from ' . $facName : '');
    $body  = "Hello,\n\nPlease find invoice " . $inv['inv_number'] . " attached.\n";
    $body .= 'Amount: £' . number_format((float) $inv['total'], 2) . ($due !== '' ? ', due ' . $due : '') . ".\n";
    $body .= "\nIf you have any questions about this invoice please reply to this email.\n\nKind regards,\n" . ($facName !== '' ? $facName : 'Accounts');

    $r = ar_email_account_document($pdo, $factory, (int) $inv['account_client_id'], $subject, $body, $doc['pdf'], $doc['filename']);
    if (!$r['ok']) return ['ok' => false, 'message' => 'Invoice ' . $inv['inv_number'] . ' not sent: ' . $r['message']];

    try {
        $pdo->prepare(
            "UPDATE factory_ar_invoices
                SET status  = CASE WHEN status IN ('draft','raised') THEN 'sent' ELSE status END,
                    sent_at = COALESCE(sent_at, NOW())
              WHERE id = ? AND factory_client_id = ? AND status <> 'void'"
        )->execute([$invId, $factory]);
    } catch (Throwable $e) {
        error_log('ar_send_invoice: emailed ' . $inv['inv_number'] . ' but could not mark it sent — ' . $e->getMessage());
    }
    return ['ok' => true, 'message' => 'Invoice ' . $inv['inv_number'] . ' ' . $r['message']];
}

/** EMAIL a credit note PDF to its trade account (no status change — CNs have none for it). */
function ar_send_credit_note(PDO $pdo, int $factory, int $cnId): array
{
    try {
        $doc = ar_credit_note_pdf($pdo, $factory, $cnId);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not build the credit note PDF: ' . $e->getMessage()];
    }
    if ($doc === null) return ['ok' => false, 'message' => 'Credit note not found, or the PDF engine is unavailable.'];
    $cn = $doc['cn'];
    if ((string) $cn['status'] === 'void') return ['ok' => false, 'message' => 'Credit note ' . $cn['cn_number'] . ' is void — it cannot be sent.'];

    $fac     = ar_factory_row($pdo, $factory);
    $facName = trim((string) ($fac['company_name'] ?? ''));
    $refund  = (string) ($cn['settle_mode'] ?? 'credit') === 'refund';
    $subject = 'Credit note ' . $cn['cn_number'] . ($facName !== '' ? ' from ' . $facName : '');
    $body  = "Hello,\n\nPlease find credit note " . $cn['cn_number'] . " attached, for £" . number_format((float) $cn['total'], 2) . ".\n";
    $body .= $refund ? "This credit is being refunded to you.\n" : "This credit has been applied to your account.\n";
    $body .= "\nIf you have any questions please reply to this email.\n\nKind regards,\n" . ($facName !== '' ? $facName : 'Accounts');

    $r = ar_email_account_document($pdo, $factory, (int) $cn['account_client_id'], $subject, $body, $doc['pdf'], $doc['filename']);
    return $r['ok']
        ? ['ok' => true,  'message' => 'Credit note ' . $cn['cn_number'] . ' ' . $r['message']]
        : ['ok' => false, 'message' => 'Credit note ' . $cn['cn_number'] . ' not sent: ' . $r['message']];
}

/* ── Invoice editing (draft / raised-not-sent), void, reissue ───────────────── */

/** Recompute an invoice's subtotal / VAT / total from its lines at its own VAT rate. */
function ar_invoice_recalc_totals(PDO $pdo, int $invId): void
{
    $s = $pdo->prepare('SELECT COALESCE(SUM(line_net),0) FROM factory_ar_invoice_lines WHERE invoice_id = ?');
    $s->execute([$invId]);
    $sub = round((float) $s->fetchColumn(), 2);
    $v = $pdo->prepare('SELECT vat_percent FROM factory_ar_invoices WHERE id = ? LIMIT 1');
    $v->execute([$invId]);
    $pct = (float) $v->fetchColumn();
    $vat = round($sub * $pct / 100, 2);
    $pdo->prepare('UPDATE factory_ar_invoices SET subtotal = ?, vat = ?, total = ? WHERE id = ?')
        ->execute([$sub, $vat, round($sub + $vat, 2), $invId]);
}

/**
 * Can this invoice's lines still be edited? Only while it hasn't gone to the
 * customer and nothing hangs off it: status draft/raised, never sent, nothing paid,
 * no live credit note. Returns '' when editable, else the reason it isn't.
 */
function ar_invoice_edit_block_reason(PDO $pdo, array $inv): string
{
    $st = (string) $inv['status'];
    if ($st === 'void') return 'This invoice is void.';
    if (!in_array($st, ['draft', 'raised'], true) || !empty($inv['sent_at'])) {
        return 'This invoice has been sent — correct it with a credit note, or void and reissue it.';
    }
    if ((float) ($inv['amount_paid'] ?? 0) > 0.004) return 'A payment is allocated to this invoice.';
    if (ar_table_ready($pdo, 'factory_ar_credit_notes')) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM factory_ar_credit_notes WHERE against_invoice_id = ? AND status <> 'void'");
        $c->execute([(int) $inv['id']]);
        if ((int) $c->fetchColumn() > 0) return 'A credit note has been raised against this invoice.';
    }
    return '';
}

/**
 * Add a carriage or adjustment line (net, ex VAT; an adjustment may be negative)
 * to an editable invoice and recompute its totals. Throws RuntimeException when
 * the invoice can't be edited or the input is bad.
 */
function ar_invoice_add_line(PDO $pdo, int $factory, int $invId, string $type, string $description, float $amount): void
{
    if (!in_array($type, ['carriage', 'adjust'], true)) throw new RuntimeException('Only carriage or adjustment lines can be added.');
    $amount = round($amount, 2);
    if (abs($amount) < 0.005) throw new RuntimeException('Enter an amount.');
    if ($type === 'carriage' && $amount < 0) throw new RuntimeException('Carriage can\'t be negative — use an adjustment for a discount.');
    $description = trim($description);
    if ($description === '') $description = $type === 'carriage' ? 'Carriage' : ($amount < 0 ? 'Discount' : 'Adjustment');
    $description = mb_substr($description, 0, 255);

    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $g = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1 FOR UPDATE');
        $g->execute([$invId, $factory]);
        $inv = $g->fetch(PDO::FETCH_ASSOC);
        if (!$inv) throw new RuntimeException('Invoice not found.');
        if (($why = ar_invoice_edit_block_reason($pdo, $inv)) !== '') throw new RuntimeException($why);

        $so = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) + 1 FROM factory_ar_invoice_lines WHERE invoice_id = ?');
        $so->execute([$invId]);

        // source_quote_id / source_quote_item_id stay NULL: that is what marks a
        // line as MANUALLY added (the automatic "agreed price" adjust line built
        // from the order carries source_quote_id), so only these can be removed.
        $pdo->prepare(
            "INSERT INTO factory_ar_invoice_lines
               (invoice_id, source_quote_id, source_quote_item_id, line_type, description,
                width_mm, drop_mm, quantity, unit_net, line_net, list_trade_unit, discount_percent, discount_amount, sort_order)
             VALUES (?, NULL, NULL, ?, ?, NULL, NULL, 1, ?, ?, NULL, NULL, NULL, ?)"
        )->execute([$invId, $type, $description, $amount, $amount, (int) $so->fetchColumn()]);

        ar_invoice_recalc_totals($pdo, $invId);
        if ($ownTxn) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Remove a MANUALLY added line (carriage, or an adjust line with no order link —
 * never a blind line or the automatic "agreed price" reconcile) from an editable
 * invoice and recompute totals.
 */
function ar_invoice_remove_line(PDO $pdo, int $factory, int $invId, int $lineId): void
{
    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $g = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1 FOR UPDATE');
        $g->execute([$invId, $factory]);
        $inv = $g->fetch(PDO::FETCH_ASSOC);
        if (!$inv) throw new RuntimeException('Invoice not found.');
        if (($why = ar_invoice_edit_block_reason($pdo, $inv)) !== '') throw new RuntimeException($why);

        $l = $pdo->prepare('SELECT line_type, source_quote_id, source_quote_item_id FROM factory_ar_invoice_lines WHERE id = ? AND invoice_id = ? LIMIT 1');
        $l->execute([$lineId, $invId]);
        $line = $l->fetch(PDO::FETCH_ASSOC);
        if (!$line) throw new RuntimeException('Line not found.');
        if (!in_array((string) $line['line_type'], ['carriage', 'adjust'], true)
            || $line['source_quote_item_id'] !== null || $line['source_quote_id'] !== null) {
            throw new RuntimeException('Only manually added carriage and adjustment lines can be removed.');
        }
        $pdo->prepare('DELETE FROM factory_ar_invoice_lines WHERE id = ? AND invoice_id = ?')->execute([$lineId, $invId]);
        ar_invoice_recalc_totals($pdo, $invId);
        if ($ownTxn) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * VOID an invoice (keeps its number, gap-free). Also:
 *   - voids the credit notes raised against it (a credit against a void invoice is
 *     a credit the account never earned), and
 *   - RELEASES its payment allocations: the rows are deleted and amount_paid zeroed,
 *     so money paid against it becomes unallocated credit on the account (it used
 *     to stay stuck on the void invoice, understating on-account money). It can then
 *     be applied to the reissue with ar_apply_account_credit().
 * Returns ['released'=>float,'cn_voided'=>int]. Own-transaction-aware.
 */
function ar_void_invoice(PDO $pdo, int $factory, int $invId, string $reason = ''): array
{
    $reason = trim($reason) !== '' ? trim($reason) : 'Voided';
    $out = ['released' => 0.0, 'cn_voided' => 0];
    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $g = $pdo->prepare("SELECT id FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? AND status <> 'void' LIMIT 1 FOR UPDATE");
        $g->execute([$invId, $factory]);
        if (!$g->fetchColumn()) { if ($ownTxn) $pdo->commit(); return $out; }

        if (ar_payments_ready($pdo)) {
            $s = $pdo->prepare(
                "SELECT COALESCE(SUM(a.amount),0) FROM factory_ar_payment_allocations a
                   JOIN factory_ar_payments p ON p.id = a.payment_id
                  WHERE a.invoice_id = ? AND p.voided_at IS NULL"
            );
            $s->execute([$invId]);
            $out['released'] = round((float) $s->fetchColumn(), 2);
            $pdo->prepare('DELETE FROM factory_ar_payment_allocations WHERE invoice_id = ?')->execute([$invId]);
        }
        if ($out['released'] > 0.004) {
            $reason .= ' (£' . number_format($out['released'], 2) . ' paid released to account credit)';
        }
        $pdo->prepare("UPDATE factory_ar_invoices SET status = 'void', voided_at = NOW(), void_reason = ?, amount_paid = 0 WHERE id = ? AND factory_client_id = ?")
            ->execute([mb_substr($reason, 0, 255), $invId, $factory]);

        if (ar_table_ready($pdo, 'factory_ar_credit_notes')) {
            $cnv = $pdo->prepare(
                "UPDATE factory_ar_credit_notes SET status = 'void', voided_at = NOW()
                  WHERE against_invoice_id = ? AND factory_client_id = ? AND status <> 'void'"
            );
            $cnv->execute([$invId, $factory]);
            $out['cn_voided'] = $cnv->rowCount();
        }
        if ($ownTxn) $pdo->commit();
        return $out;
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/* ── Credit notes (full / partial, credit / refund) ─────────────────────────── */

/**
 * Raise a credit note against an invoice.
 *   $lines = [['source_invoice_line_id'=>?int,'description'=>str,'quantity'=>int,
 *              'unit_net'=>float,'line_net'=>float,'width_mm'=>?,'drop_mm'=>?], …]
 *            (net, ex VAT, positive). Empty = FULL credit of what's left.
 *   $settleMode 'credit' (sits on the account) | 'refund' (recorded as refunded to
 *   the customer — no money is moved by the system).
 * VAT is at the INVOICE's rate. A credit that takes the invoice to fully credited
 * uses the invoice's remaining VAT exactly, so the pennies always reconcile. The
 * running credited total can never exceed the invoice. Recomputes the invoice's
 * status (fully credited → settled). Returns ['id','number','total'].
 */
function ar_create_credit_note(PDO $pdo, int $factory, int $invId, array $lines, string $reason,
                               string $settleMode, int $userId): array
{
    if (!ar_table_ready($pdo, 'factory_ar_credit_notes')) throw new RuntimeException('Run /migrate_ar_credit_notes.php first.');
    $settleMode = $settleMode === 'refund' ? 'refund' : 'credit';

    $iv = $pdo->prepare('SELECT * FROM factory_ar_invoices WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $iv->execute([$invId, $factory]);
    $inv = $iv->fetch(PDO::FETCH_ASSOC);
    if (!$inv) throw new RuntimeException('Invoice not found.');
    if ($inv['status'] === 'void') throw new RuntimeException('That invoice is void — nothing to credit.');

    $already = $pdo->prepare(
        "SELECT COALESCE(SUM(subtotal),0) AS sub, COALESCE(SUM(vat),0) AS vat, COALESCE(SUM(total),0) AS tot
           FROM factory_ar_credit_notes WHERE against_invoice_id = ? AND factory_client_id = ? AND status <> 'void'"
    );
    $already->execute([$invId, $factory]);
    $cr = $already->fetch(PDO::FETCH_ASSOC) ?: ['sub' => 0, 'vat' => 0, 'tot' => 0];
    $leftSub = round((float) $inv['subtotal'] - (float) $cr['sub'], 2);
    $leftVat = round((float) $inv['vat'] - (float) $cr['vat'], 2);
    $leftTot = round((float) $inv['total'] - (float) $cr['tot'], 2);
    if ($leftTot <= 0.005) {
        throw new RuntimeException((float) $cr['tot'] > 0.005
            ? 'That invoice is already credited in full (£' . number_format((float) $cr['tot'], 2) . ') — void the existing credit note first if it needs redoing.'
            : 'That invoice has nothing left to credit.');
    }

    $full = !$lines;
    if ($full) {
        $il = $pdo->prepare('SELECT * FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
        $il->execute([$invId]);
        $invLines = $il->fetchAll(PDO::FETCH_ASSOC);
        if (!$invLines) throw new RuntimeException('That invoice has no lines to credit.');
        if ((float) $cr['tot'] > 0.005) {
            // Already part-credited: credit the remainder as one line.
            $lines = [['source_invoice_line_id' => null, 'description' => 'Balance of ' . $inv['inv_number'],
                       'quantity' => 1, 'unit_net' => $leftSub, 'line_net' => $leftSub]];
        } else {
            foreach ($invLines as $l) {
                $lines[] = ['source_invoice_line_id' => (int) $l['id'], 'description' => (string) $l['description'],
                            'width_mm' => $l['width_mm'], 'drop_mm' => $l['drop_mm'], 'quantity' => (int) $l['quantity'],
                            'unit_net' => (float) $l['unit_net'], 'line_net' => (float) $l['line_net']];
            }
        }
    }

    $sub = 0.0;
    foreach ($lines as $l) $sub += round((float) $l['line_net'], 2);
    $sub = round($sub, 2);
    if ($sub <= 0.004) throw new RuntimeException('Nothing to credit — enter an amount.');
    if ($sub > $leftSub + 0.005) {
        throw new RuntimeException('That credits £' . number_format($sub, 2) . ' net, but only £' . number_format(max(0, $leftSub), 2) . ' net is left to credit on ' . $inv['inv_number'] . '.');
    }
    $vatPct = (float) $inv['vat_percent'];
    if ($full || abs($sub - $leftSub) < 0.005) {
        $sub = $leftSub; $vat = $leftVat;        // closes the invoice exactly
    } else {
        $vat = round($sub * $vatPct / 100, 2);
        if ($vat > $leftVat) $vat = $leftVat;
    }
    $total = round($sub + $vat, 2);

    $cnQuoteId = 0;
    try {
        $qo = $pdo->prepare('SELECT quote_id FROM factory_ar_invoice_orders WHERE invoice_id = ? LIMIT 1');
        $qo->execute([$invId]);
        $cnQuoteId = (int) $qo->fetchColumn();
    } catch (Throwable $e) { $cnQuoteId = 0; }

    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $cnId = 0; $num = '';
        for ($try = 1; $try <= 3; $try++) {
            $num = $cnQuoteId > 0
                ? ar_order_doc_number($pdo, $factory, $cnQuoteId, 'CN', 'factory_ar_credit_notes', 'cn_number')
                : ar_next_number($pdo, $factory, 'CN', 'factory_ar_credit_notes', 'cn_number');
            try {
                $pdo->prepare(
                    "INSERT INTO factory_ar_credit_notes
                       (factory_client_id, account_client_id, cn_number, against_invoice_id, status, issue_date,
                        reason, vat_percent, subtotal, vat, total, settle_mode, bill_to_snapshot, created_by)
                     VALUES (?, ?, ?, ?, 'issued', CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $factory, (int) $inv['account_client_id'], $num, $invId,
                    trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
                    $vatPct, $sub, $vat, $total, $settleMode, $inv['bill_to_snapshot'], $userId ?: null,
                ]);
                $cnId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $try < 3) continue;
                throw $e;
            }
        }

        $insL = $pdo->prepare(
            "INSERT INTO factory_ar_credit_note_lines
               (credit_note_id, source_invoice_line_id, description, width_mm, drop_mm, quantity, unit_net, line_net, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $so = 0;
        foreach ($lines as $l) {
            $insL->execute([
                $cnId, $l['source_invoice_line_id'] ?? null, mb_substr((string) $l['description'], 0, 255),
                $l['width_mm'] ?? null, $l['drop_mm'] ?? null, max(1, (int) ($l['quantity'] ?? 1)),
                round((float) $l['unit_net'], 2), round((float) $l['line_net'], 2), $so++,
            ]);
        }
        ar_recompute_invoice_paid($pdo, $invId);   // fully credited → settled
        if ($ownTxn) $pdo->commit();
        return ['id' => $cnId, 'number' => $num, 'total' => $total];
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
    $z = ['invoiced' => 0.0, 'credited' => 0.0, 'paid' => 0.0, 'refunded' => 0.0, 'outstanding' => 0.0];
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
        $z['refunded'] = ar_refunded_total($pdo, $factory, $accountId);
    }
    // A credit note settled by REFUND is money handed back, so it does not stay as
    // credit on the account: it credits the account and the refund charges it back.
    $z['outstanding'] = round($z['invoiced'] - $z['paid'] - $z['credited'] + $z['refunded'], 2);
    return $z;
}

/**
 * Σ non-void credit notes settled by REFUND (settle_mode = 'refund') for an
 * account, optionally only those issued on/before $asAt. The refund itself is only
 * RECORDED (no money moves) — it just stops that credit sitting on the account.
 */
function ar_refunded_total(PDO $pdo, int $factory, int $accountId, string $asAt = ''): float
{
    if (!ar_table_ready($pdo, 'factory_ar_credit_notes')) return 0.0;
    try {
        $sql = "SELECT COALESCE(SUM(total),0) FROM factory_ar_credit_notes
                 WHERE factory_client_id = ? AND account_client_id = ? AND status <> 'void' AND settle_mode = 'refund'";
        $p = [$factory, $accountId];
        if ($asAt !== '') { $sql .= ' AND COALESCE(issue_date, DATE(created_at)) <= ?'; $p[] = $asAt; }
        $s = $pdo->prepare($sql);
        $s->execute($p);
        return round((float) $s->fetchColumn(), 2);
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * Money received but not allocated to any LIVE invoice, per payment (oldest first):
 * [['id','pay_number','payment_date','amount','allocated','unallocated'], …] — only
 * payments with something left. Allocations still sitting on a VOID invoice (from
 * before voiding released them) count as unallocated.
 */
function ar_unallocated_payments(PDO $pdo, int $factory, int $accountId): array
{
    if (!ar_payments_ready($pdo)) return [];
    $st = $pdo->prepare(
        "SELECT p.id, p.pay_number, p.payment_date, p.amount,
                COALESCE((SELECT SUM(a.amount) FROM factory_ar_payment_allocations a
                            JOIN factory_ar_invoices i ON i.id = a.invoice_id
                           WHERE a.payment_id = p.id AND i.status <> 'void'), 0) AS allocated
           FROM factory_ar_payments p
          WHERE p.factory_client_id = ? AND p.account_client_id = ? AND p.voided_at IS NULL
          ORDER BY p.payment_date, p.id"
    );
    $st->execute([$factory, $accountId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $left = round((float) $r['amount'] - (float) $r['allocated'], 2);
        if ($left > 0.004) { $r['unallocated'] = $left; $out[] = $r; }
    }
    return $out;
}

/** Σ unallocated payment money for an account (see ar_unallocated_payments). */
function ar_unallocated_total(PDO $pdo, int $factory, int $accountId): float
{
    $t = 0.0;
    foreach (ar_unallocated_payments($pdo, $factory, $accountId) as $p) $t += (float) $p['unallocated'];
    return round($t, 2);
}

/**
 * APPLY CREDIT: allocate the account's unallocated payment money (oldest payment
 * first) to its open invoices (oldest first, or only $onlyInvoiceId), creating
 * ordinary allocation rows against the existing payments — no new money, no
 * schema change. Also tidies any allocation still stranded on a VOID invoice
 * (pre-fix data) so that money is free to apply. Standalone credit notes already
 * reduce the balance but can't be allocated to an invoice (no link column).
 * Returns ['applied'=>float,'invoices'=>[inv_number,…]]. Own-transaction-aware.
 */
function ar_apply_account_credit(PDO $pdo, int $factory, int $accountId, int $onlyInvoiceId = 0): array
{
    $res = ['applied' => 0.0, 'invoices' => []];
    if (!ar_payments_ready($pdo)) return $res;
    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        // Release allocations stuck on void invoices for this account's payments.
        $pdo->prepare(
            "DELETE a FROM factory_ar_payment_allocations a
               JOIN factory_ar_payments p ON p.id = a.payment_id
               JOIN factory_ar_invoices i ON i.id = a.invoice_id
              WHERE p.factory_client_id = ? AND p.account_client_id = ? AND i.status = 'void'"
        )->execute([$factory, $accountId]);

        $pays = ar_unallocated_payments($pdo, $factory, $accountId);
        $invs = ar_open_invoices($pdo, $factory, $accountId);
        if ($onlyInvoiceId > 0) $invs = array_values(array_filter($invs, static fn ($i) => (int) $i['id'] === $onlyInvoiceId));

        $ins = $pdo->prepare('INSERT INTO factory_ar_payment_allocations (payment_id, invoice_id, amount) VALUES (?, ?, ?)');
        $pi = 0;
        foreach ($invs as $inv) {
            $need = round((float) $inv['balance'], 2);
            while ($need > 0.004 && $pi < count($pays)) {
                $give = round(min($need, (float) $pays[$pi]['unallocated']), 2);
                if ($give > 0.004) {
                    $ins->execute([(int) $pays[$pi]['id'], (int) $inv['id'], $give]);
                    $pays[$pi]['unallocated'] = round((float) $pays[$pi]['unallocated'] - $give, 2);
                    $need = round($need - $give, 2);
                    $res['applied'] = round($res['applied'] + $give, 2);
                    $res['invoices'][(string) $inv['inv_number']] = true;
                }
                if ((float) $pays[$pi]['unallocated'] <= 0.004) $pi++;
            }
            if (isset($res['invoices'][(string) $inv['inv_number']])) ar_recompute_invoice_paid($pdo, (int) $inv['id']);
            if ($pi >= count($pays)) break;
        }
        $res['invoices'] = array_keys($res['invoices']);
        if ($ownTxn) $pdo->commit();
        return $res;
    } catch (Throwable $e) {
        if ($ownTxn && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Payments recorded for an account, newest first (with allocated total). */
function ar_account_payments(PDO $pdo, int $factory, int $accountId): array
{
    if (!ar_table_ready($pdo, 'factory_ar_payments')) return [];
    $st = $pdo->prepare(
        "SELECT p.*, COALESCE((SELECT SUM(a.amount) FROM factory_ar_payment_allocations a
                                  JOIN factory_ar_invoices i ON i.id = a.invoice_id
                                WHERE a.payment_id = p.id AND i.status <> 'void'), 0) AS allocated
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
    $iv = $pdo->prepare("SELECT total, sent_at, status, amount_paid FROM factory_ar_invoices WHERE id = ? LIMIT 1");
    $iv->execute([$invoiceId]);
    $row = $iv->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string) $row['status'] === 'void') return;   // never touch a void invoice

    // Paid = live allocations. Pre-payments-migration there are none to read, so
    // keep the cached figure (a credit note can still settle the invoice).
    $paid = round((float) $row['amount_paid'], 2);
    if (ar_payments_ready($pdo)) {
        $ps = $pdo->prepare(
            "SELECT COALESCE(SUM(a.amount),0)
               FROM factory_ar_payment_allocations a
               JOIN factory_ar_payments p ON p.id = a.payment_id
              WHERE a.invoice_id = ? AND p.voided_at IS NULL"
        );
        $ps->execute([$invoiceId]);
        $paid = round((float) $ps->fetchColumn(), 2);
    }

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
        // A credit note settled by refund: the money went back to the customer, so
        // the ledger shows the refund as a charge that cancels the credit.
        try {
            $s = $pdo->prepare("SELECT cn_number ref, COALESCE(issue_date, DATE(created_at)) d, total amt
                                  FROM factory_ar_credit_notes
                                 WHERE factory_client_id = ? AND account_client_id = ? AND status <> 'void'
                                   AND settle_mode = 'refund'");
            $s->execute([$factory, $accountId]);
            $add('Refund paid', $s->fetchAll(PDO::FETCH_ASSOC), 1);
        } catch (Throwable $e) { /* settle_mode absent — no refunds */ }
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

    $overSettled = 0.0;   // paid + credited beyond an invoice's total (e.g. paid, then credited)
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $total = round((float) $r['total'], 2);
        $settled = round((float) $r['paid'] + (float) $r['credited'], 2);   // BM "Paid" = payments + credits
        $out     = round($total - $settled, 2);
        if ($out < -0.004) $overSettled = round($overSettled - $out, 2);
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

    // Money the account has paid or been credited that is not attached to any
    // invoice — an unallocated payment, or a standalone credit note
    // (against_invoice_id IS NULL). The open-item rows above can't show it, so
    // without this the statement chased a customer for money they had already
    // sent. ar_account_ar_summary() already nets it off on screen, so the
    // emailed statement was contradicting the account page.
    //
    // Also on account: anything paid/credited BEYOND an invoice's total (it drops
    // out of the open items above, so without this an account that was paid and
    // then credited read as square instead of in credit), less credit notes settled
    // by REFUND (that money went back to the customer). Allocations still sitting
    // on a VOID invoice count as unallocated — voiding releases them now, but older
    // voids left them stranded and the money vanished from every total.
    // on_account is SIGNED: negative only if refunds exceed the credits.
    $onAccount = $overSettled;
    if ($hasPay) {
        try {
            $q = $pdo->prepare(
                "SELECT COALESCE(SUM(p.amount),0)
                      - COALESCE((SELECT SUM(a.amount)
                                    FROM factory_ar_payment_allocations a
                                    JOIN factory_ar_payments p2 ON p2.id = a.payment_id
                                    JOIN factory_ar_invoices i2 ON i2.id = a.invoice_id
                                   WHERE p2.factory_client_id = p.factory_client_id
                                     AND p2.account_client_id = p.account_client_id
                                     AND p2.voided_at IS NULL AND i2.status <> 'void'
                                     AND p2.payment_date <= ?), 0)
                   FROM factory_ar_payments p
                  WHERE p.factory_client_id = ? AND p.account_client_id = ?
                    AND p.voided_at IS NULL AND p.payment_date <= ?"
            );
            $q->execute([$toD, $factory, $accountId, $toD]);
            $onAccount += round((float) $q->fetchColumn(), 2);
        } catch (Throwable $e) { /* leave at 0 rather than misstate */ }
    }
    if ($hasCN) $onAccount -= ar_refunded_total($pdo, $factory, $accountId, $toD);
    if ($hasCN) {
        try {
            $q = $pdo->prepare(
                "SELECT COALESCE(SUM(total),0) FROM factory_ar_credit_notes
                  WHERE factory_client_id = ? AND account_client_id = ?
                    AND against_invoice_id IS NULL AND status <> 'void'
                    AND COALESCE(issue_date, DATE(created_at)) <= ?"
            );
            $q->execute([$factory, $accountId, $toD]);
            $onAccount += round((float) $q->fetchColumn(), 2);
        } catch (Throwable $e) { /* leave as is */ }
    }

    $res['on_account']        = round($onAccount, 2);
    $res['total_outstanding'] = round($res['total_outstanding'] - $res['on_account'], 2);

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
    $z = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'd90plus' => 0.0,
          'gross' => 0.0, 'on_account' => 0.0, 'total' => 0.0];
    $asD  = ($asAt !== '' && strtotime($asAt)) ? date('Y-m-d', strtotime($asAt)) : date('Y-m-d');
    $asTs = strtotime($asD . ' 23:59:59');
    $si   = ar_statement_invoices($pdo, $factory, $accountId, $asD);
    foreach ($si['rows'] as $r) {
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
    // Buckets age the open invoices (gross). Money on account (unallocated payments,
    // standalone / excess credits, less refunds) isn't tied to a due date, so it is
    // shown as its own figure and netted into the TOTAL — which therefore equals the
    // ledger (invoices − credits − payments) and the statement's total outstanding.
    $z['gross']      = round($z['current'] + $z['d30'] + $z['d60'] + $z['d90'] + $z['d90plus'], 2);
    $z['on_account'] = round((float) ($si['on_account'] ?? 0), 2);
    $z['total']      = round($z['gross'] - $z['on_account'], 2);
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
            'on_account'        => $inv['on_account'] ?? 0.0,
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
 * when the rule's product is NULL — and commission = turnover × %. Turnover is NET
 * of credit notes issued in the period, and an account's product-specific rate
 * replaces (never adds to) its All-products rate for that product. Products matched
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
                             WHERE $cond AND il.line_type <> 'carriage'");   // carriage isn't a sale
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

        // Net CREDITS out: a credit note issued in the period (non-void, against a
        // non-void invoice or standalone) reduces turnover — commission is paid on
        // what the account actually ends up paying for, not on credited sales.
        // Product-level: CN lines that trace back to an invoice line → its product.
        if (ar_table_ready($pdo, 'factory_ar_credit_notes')) {
            $cc     = "cn.factory_client_id = ? AND cn.account_client_id = ? AND cn.status <> 'void'
                       AND (cn.against_invoice_id IS NULL OR EXISTS (SELECT 1 FROM factory_ar_invoices vi WHERE vi.id = cn.against_invoice_id AND vi.status <> 'void'))";
            $cp     = [$factory, $accountId];
            if ($fromD !== null) { $cc .= " AND COALESCE(cn.issue_date, DATE(cn.created_at)) >= ?"; $cp[] = $fromD; }
            $cc .= " AND COALESCE(cn.issue_date, DATE(cn.created_at)) <= ?"; $cp[] = $to;
            try {
                $ct = $pdo->prepare("SELECT COALESCE(SUM(cn.subtotal),0) FROM factory_ar_credit_notes cn WHERE $cc");
                $ct->execute($cp);
                $total = round($total - (float) $ct->fetchColumn(), 2);

                $cb = $pdo->prepare("SELECT COALESCE(p.source_product_id, p.id) mpid, COALESCE(SUM(cl.line_net),0) net
                                       FROM factory_ar_credit_notes cn
                                       JOIN factory_ar_credit_note_lines cl ON cl.credit_note_id = cn.id
                                       JOIN factory_ar_invoice_lines il ON il.id = cl.source_invoice_line_id
                                       JOIN quote_items qi ON qi.id = il.source_quote_item_id
                                       JOIN products p ON p.id = qi.product_id
                                      WHERE $cc GROUP BY mpid");
                $cb->execute($cp);
                foreach ($cb->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $k = (int) $r['mpid'];
                    $byP[$k] = round(($byP[$k] ?? 0.0) - (float) $r['net'], 2);
                }
            } catch (Throwable $e) { /* credit tables unreadable — gross turnover */ }
        }
        return $accCache[$accountId] = ['total' => $total, 'byP' => $byP];
    };

    $masterOf = function (int $pid) use ($pdo): array {
        $s = $pdo->prepare("SELECT COALESCE(source_product_id, id) mid, name FROM products WHERE id = ? LIMIT 1");
        $s->execute([$pid]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ? [(int) $r['mid'], (string) $r['name']] : [0, ''];
    };

    // A product-specific rate OVERRIDES the account's "All products" rate for that
    // product (it used to stack on top, paying commission twice on the same sale).
    // So the All-products rule only earns on turnover NOT covered by a product rule
    // of this consultant for the same account.
    $specific = [];   // [accountId][masterProductId] = true
    foreach ($rules as $rule) {
        if ($rule['product_id'] !== null) {
            [$mid] = $masterOf((int) $rule['product_id']);
            if ($mid > 0) $specific[(int) $rule['client_id']][$mid] = true;
        }
    }

    foreach ($rules as $rule) {
        $acc = $loadAcc((int) $rule['client_id']);
        $pct = (float) $rule['commission_percent'];
        if ($rule['product_id'] === null) {
            $turnover = $acc['total'];
            foreach (array_keys($specific[(int) $rule['client_id']] ?? []) as $mid) {
                $turnover -= (float) ($acc['byP'][$mid] ?? 0.0);
            }
            $turnover = round($turnover, 2);
            $pname    = !empty($specific[(int) $rule['client_id']]) ? 'All other products' : 'All products';
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
