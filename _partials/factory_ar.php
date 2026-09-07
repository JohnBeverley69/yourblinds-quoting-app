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
