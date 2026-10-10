<?php
declare(strict_types=1);

/**
 * Factory Console — the trade-only factory office's own home (stage 1).
 *
 * Why this exists: an order a trade account places (e.g. ABC on their portal) is
 * stored ONCE, as the account's own quote (client_id = ABC). Nothing on it says
 * "factory" except its lines: the products belong to the factory. The factory's
 * own screens (Incoming orders, Dispatch, Invoices) find orders that way, but the
 * retail-shaped screens (Dashboard, Pipeline, Orders list) filter client_id = the
 * factory, so they could never see an account's order. The console reads orders
 * the factory way, in one place:
 *
 *   - every PLACED order carrying at least one factory-owned line (any account),
 *   - plus the factory's own trade quotes not placed yet (keyed in via "+ New").
 *
 * Read-only: nothing here writes. Requires auth/middleware.php + factory_ar.php.
 */

require_once __DIR__ . '/factory_ar.php';
require_once __DIR__ . '/order_stage.php';

/** Stage keys shown in the console, in display order => [label, text, background]. */
function fc_stage_meta(): array
{
    return [
        'notplaced'     => ['Not placed',     '#475569', '#e2e8f0'],
        'new'           => ['New',            '#b91c1c', '#fee2e2'],
        'confirmed'     => ['Received',       '#5b6b7f', '#e6ebf1'],
        'in_production' => ['In production',  '#b5730f', '#f7ecd6'],
        'ready'         => ['Ready',          '#245ea3', '#dde8f6'],
        'dispatched'    => ['Dispatched',     '#0d7a67', '#d6ece6'],
    ];
}

function fc_stage_pill(string $key): string
{
    $m = fc_stage_meta()[$key] ?? ['—', '#475569', '#e2e8f0'];
    return '<span class="fc-pill" style="color:' . $m[1] . ';background:' . $m[2] . '">'
         . htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Every order the factory office should see, newest first (max 400). Each row adds:
 *   stage       — a fc_stage_meta() key
 *   account     — who it's for (trade account, else the ordering business, else "One-off")
 *   blinds      — blinds on it that the factory makes or supplies
 *   value       — net value to the factory where known (what the invoice bills), else null
 *   invoice     — the factory invoice number once invoiced, else ''
 *   open_url    — where "open" goes (quote builder for our own, factory edit for an account's)
 */
function fc_orders(PDO $pdo, int $factoryId): array
{
    $placed = os_placed_statuses();
    $inPlaced = "'" . implode("','", $placed) . "'";
    $owner = 'COALESCE(NULLIF(p.source_client_id,0), p.client_id)';

    $has = static function (string $sql) use ($pdo): bool {
        try { $pdo->query($sql); return true; } catch (Throwable $e) { return false; }
    };
    $hasFj      = $has('SELECT 1 FROM factory_jobs LIMIT 0');
    $hasStage   = $has('SELECT fulfilment_stage FROM quotes LIMIT 0');
    $hasArchive = $has('SELECT archived_at FROM quotes LIMIT 0');
    $hasAcct    = $has('SELECT account_client_id FROM quotes LIMIT 0');
    $hasSale    = $has('SELECT sale_type FROM quotes LIMIT 0');

    $tradeOwn = $hasSale
        ? ($hasAcct ? "(q.sale_type = 'trade' OR q.account_client_id IS NOT NULL)" : "q.sale_type = 'trade'")
        : ($hasAcct ? 'q.account_client_id IS NOT NULL' : '0');

    // The 400-row cap below is ordered so it can only ever drop FINISHED
    // orders. It used to be a plain created_at DESC cap, and both consumers
    // count the truncated set in PHP rather than asking the database:
    // factory/dashboard.php loops the rows into the New / Received / In
    // production / Ready tiles, and factory/orders.php builds its stage chips
    // the same way. So past 400 orders the rows discarded were the OLDEST —
    // exactly where a job stuck in production for three months sits. The tile
    // under-counted it and it showed on no Console screen at all. Dropping the
    // oldest dispatched orders instead is the harmless direction.
    //
    // fulfilment_stage is the only stage signal available in SQL at this point;
    // the fuller stage value is derived in PHP afterwards from the factory-job
    // rows, which are fetched by id once this query has run. With more than 400
    // orders still open the counts would narrow again — raise the cap then.
    $sql = "SELECT q.id, q.client_id, q.quote_number, q.status, q.created_at,
                   q.customer_reference, q.end_customer_name, q.subtotal,
                   " . ($hasAcct ? 'q.account_client_id' : 'NULL') . " AS account_client_id,
                   " . ($hasStage ? 'q.fulfilment_stage' : 'NULL') . " AS fulfilment_stage,
                   " . ($hasFj ? 'fj.status' : 'NULL') . " AS factory_status,
                   c.company_name AS tenant_name,
                   " . ($hasAcct ? 'ac.company_name' : 'NULL') . " AS account_name,
                   (SELECT COALESCE(SUM(qi.quantity), 0)
                      FROM quote_items qi JOIN products p ON p.id = qi.product_id
                     WHERE qi.quote_id = q.id AND $owner = ?) AS blinds
              FROM quotes q
              JOIN clients c ON c.id = q.client_id
              " . ($hasAcct ? 'LEFT JOIN clients ac ON ac.id = q.account_client_id' : '') . "
              " . ($hasFj ? 'LEFT JOIN factory_jobs fj ON fj.quote_id = q.id' : '') . "
             WHERE ((q.status IN ($inPlaced)
                     AND EXISTS (SELECT 1 FROM quote_items qi JOIN products p ON p.id = qi.product_id
                                  WHERE qi.quote_id = q.id AND $owner = ?))
                 OR (q.client_id = ? AND q.status IN ('draft','sent','accepted') AND $tradeOwn))
               " . ($hasArchive ? 'AND q.archived_at IS NULL' : '') . "
          ORDER BY " . ($hasStage ? "CASE WHEN COALESCE(q.fulfilment_stage, '') = 'dispatched' THEN 1 ELSE 0 END, " : '') . "q.created_at DESC, q.id DESC
             LIMIT 400";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([$factoryId, $factoryId, $factoryId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    if (!$rows) return [];

    // What each placed order bills — the same figure Invoices uses.
    $valueBy = [];
    foreach (ar_placed_orders($pdo, $factoryId) as $r) $valueBy[(int) $r['id']] = (float) $r['wholesale_total'];

    // Factory invoice numbers (void ones don't count).
    $invBy = [];
    if (ar_table_ready($pdo, 'factory_ar_invoice_orders') && ar_table_ready($pdo, 'factory_ar_invoices')) {
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $pdo->prepare(
                "SELECT io.quote_id, i.inv_number FROM factory_ar_invoice_orders io
                   JOIN factory_ar_invoices i ON i.id = io.invoice_id
                  WHERE io.quote_id IN ($ph) AND i.factory_client_id = ? AND i.status <> 'void'"
            );
            $st->execute([...$ids, $factoryId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $invBy[(int) $r['quote_id']] = (string) $r['inv_number'];
        } catch (Throwable $e) { /* invoices not migrated — no badge */ }
    }

    require_once __DIR__ . '/remakes.php';
    $remakeOf = rm_remake_orders($pdo, array_map(static fn ($r) => (int) $r['id'], $rows));

    foreach ($rows as &$r) {
        $id  = (int) $r['id'];
        $r['remake_of'] = $remakeOf[$id] ?? '';
        $own = (int) $r['client_id'] === $factoryId;
        if (!in_array((string) $r['status'], $placed, true)) {
            $stage = 'notplaced';
        } else {
            $fs = (string) ($r['fulfilment_stage'] ?? '');
            $fj = (string) ($r['factory_status'] ?? '');
            if (in_array($fs, ['dispatched', 'ready', 'in_production'], true)) $stage = $fs;
            elseif ($fj === 'dispatched')    $stage = 'dispatched';
            elseif ($fj === 'in_production' || $fj === 'made') $stage = 'in_production';
            elseif ($fj === '' || $fj === 'new') $stage = 'new';
            else $stage = 'confirmed';
        }
        $r['stage']   = $stage;
        $r['account'] = trim((string) ($r['account_name'] ?? '')) !== ''
            ? (string) $r['account_name']
            : ($own ? 'One-off sale' : (string) $r['tenant_name']);
        $r['account_key'] = (int) ($r['account_client_id'] ?? 0) > 0
            ? (int) $r['account_client_id']
            : ($own ? 0 : (int) $r['client_id']);
        $r['value']   = $valueBy[$id] ?? ($own ? (float) $r['subtotal'] : null);
        $r['invoice'] = $invBy[$id] ?? '';
        $r['open_url'] = ($own || $stage === 'notplaced')
            ? '/quote-builder/edit.php?id=' . $id
            : '/factory/edit-order.php?order=' . $id;
    }
    unset($r);
    return $rows;
}
