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
require_once __DIR__ . '/order_tracks.php';

/** Stage keys shown in the console, in display order => [label, text, background]. */
function fc_stage_meta(): array
{
    return [
        'notplaced'     => ['Quotes',         '#475569', '#e2e8f0'],
        'new'           => ['New',            '#b91c1c', '#fee2e2'],
        'in_production' => ['In production',  '#b5730f', '#f7ecd6'],
        'awaiting_supplier' => ['Awaiting supplier', '#7c3aed', '#ede9fe'],
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
 * An order's status is two separate things, shown side by side (John, 2026-10-11):
 *
 *   MAKING — where the blinds are: fc_stage_meta() above, the same words as
 *            Incoming orders (New / In production / Awaiting supplier / Ready / Dispatched).
 *   MONEY  — where the bill is, from the factory's own invoices:
 *            Not invoiced → Needs invoicing (dispatched, no invoice yet) →
 *            Invoiced · awaiting payment / Part paid / Overdue → Paid.
 *
 * Before this the one Stage pill only followed making, so an order that was
 * invoiced and an order that was invoiced AND paid both just said "Dispatched".
 */
function fc_money_meta(): array
{
    return [
        'none'            => ['—',                        '#94a3b8', 'transparent'],
        'not_invoiced'    => ['Not invoiced',             '#64748b', '#f1f5f9'],
        'needs_invoicing' => ['Needs invoicing',          '#b91c1c', '#fee2e2'],
        'awaiting'        => ['Invoiced · awaiting payment', '#245ea3', '#dde8f6'],
        'part_paid'       => ['Part paid',                '#b5730f', '#f7ecd6'],
        'overdue'         => ['Overdue',                  '#ffffff', '#b91c1c'],
        'paid'            => ['Paid',                     '#ffffff', '#0d7a67'],
    ];
}

function fc_money_pill(string $key): string
{
    $m = fc_money_meta()[$key] ?? fc_money_meta()['none'];
    return '<span class="fc-pill" style="color:' . $m[1] . ';background:' . $m[2] . '">'
         . htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * The list's filter chips, in their groups => [key => label]. "Open" is anything
 * still needing making OR money; "Done" is dispatched AND paid.
 */
function fc_filter_groups(): array
{
    $g = [
        ''          => ['open' => 'Open'],
        'To make'   => ['new' => 'New', 'in_production' => 'In production', 'awaiting_supplier' => 'Awaiting supplier', 'ready' => 'Ready to go'],
        'To bill'   => ['needs_invoicing' => 'Needs invoicing', 'awaiting' => 'Awaiting payment', 'overdue' => 'Overdue'],
        'Finished'  => ['done' => 'Done'],
        'Not placed'=> ['notplaced' => 'Quotes'],
        ' '         => ['all' => 'All'],
    ];
    if (!fc_money_visible()) unset($g['To bill']);
    return $g;
}

/**
 * Is the MONEY side shown? It belongs to the accounts pages, which are the main
 * factory's only (#1057), so the same gate applies: factory_accounts_user().
 * A white-label factory sees Making only, and "Done" means dispatched. John,
 * 2026-10-12: factory changes are per client, with no crossover with Beverley.
 */
function fc_money_visible(): bool
{
    return !function_exists('factory_accounts_user') || factory_accounts_user();
}

/** Does an fc_orders() row belong under filter $key? */
function fc_filter_match(array $r, string $key): bool
{
    switch ($key) {
        case 'all':      return true;
        case 'open':     return $r['stage'] !== 'notplaced' && !$r['done'];
        case 'done':     return $r['done'];
        case 'awaiting': return in_array($r['money'], ['awaiting', 'part_paid'], true);
        case 'needs_invoicing':
        case 'overdue':  return $r['money'] === $key;
        case 'dispatched': return $r['stage'] === 'dispatched';   // old links
        case 'confirmed':  return $r['stage'] === 'new';          // old links: Received is gone, those are New now
        default:         return $r['stage'] === $key;
    }
}

/**
 * Live (non-void) factory invoices for each order, oldest first:
 *   [quote_id => [[id, number, status, total, outstanding, due, overdue], …]]
 * An order can have several (INV-BEV-2026-0001-2) and one invoice can cover
 * several orders; outstanding is the invoice's own balance, net of payments AND
 * credit notes, the same sum as ar_open_invoices().
 */
function fc_order_invoices(PDO $pdo, int $factoryId, array $quoteIds): array
{
    $quoteIds = array_values(array_unique(array_filter(array_map('intval', $quoteIds))));
    if (!$quoteIds || !ar_table_ready($pdo, 'factory_ar_invoice_orders') || !ar_table_ready($pdo, 'factory_ar_invoices')) return [];
    $ph   = implode(',', array_fill(0, count($quoteIds), '?'));
    // Last payment against the invoice (the Pipeline drops Paid cards after 7 days).
    $paidAt = ar_table_ready($pdo, 'factory_ar_payment_allocations') && ar_table_ready($pdo, 'factory_ar_payments')
        ? "(SELECT MAX(p.payment_date) FROM factory_ar_payment_allocations a JOIN factory_ar_payments p ON p.id = a.payment_id
             WHERE a.invoice_id = i.id AND p.voided_at IS NULL)"
        : 'NULL';
    $cred = ar_table_ready($pdo, 'factory_ar_credit_notes')
        ? "COALESCE((SELECT SUM(cn.total) FROM factory_ar_credit_notes cn
                      WHERE cn.against_invoice_id = i.id AND cn.status <> 'void'), 0)"
        : '0';
    try {
        $st = $pdo->prepare(
            "SELECT io.quote_id, i.id, i.inv_number, i.status, i.issue_date, i.due_date, i.total,
                    i.total - i.amount_paid - $cred AS outstanding, $paidAt AS paid_at
               FROM factory_ar_invoice_orders io
               JOIN factory_ar_invoices i ON i.id = io.invoice_id
              WHERE io.quote_id IN ($ph) AND i.factory_client_id = ? AND i.status <> 'void'
           ORDER BY i.issue_date, i.id"
        );
        $st->execute([...$quoteIds, $factoryId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];   // invoices not migrated — no money shown
    }
    $today = date('Y-m-d');
    $out = [];
    foreach ($rows as $r) {
        // A paid invoice owes nothing, whatever the sum says (rounding).
        $owed = (string) $r['status'] === 'paid' ? 0.0 : max(0.0, round((float) $r['outstanding'], 2));
        // Same fallback as ar_aging(): no due date = 30 days from issue.
        $due = (string) ($r['due_date'] ?? '');
        if ($due === '' && (string) ($r['issue_date'] ?? '') !== '') $due = date('Y-m-d', strtotime($r['issue_date'] . ' +30 days'));
        $out[(int) $r['quote_id']][] = [
            'id'          => (int) $r['id'],
            'number'      => (string) $r['inv_number'],
            'status'      => (string) $r['status'],
            'total'       => (float) $r['total'],
            'outstanding' => $owed,
            'paid_some'   => $owed < round((float) $r['total'], 2) - 0.004,
            'due'         => $due,
            'overdue'     => $owed > 0.004 && $due !== '' && $due < $today,
            'paid_at'     => (string) ($r['paid_at'] ?? ''),
        ];
    }
    return $out;
}

/** fc_money_meta() key for an fc_orders() row (needs stage, status, invoices, billed_here). */
function fc_money_state(array $r): string
{
    if ($r['stage'] === 'notplaced' || !fc_money_visible()) return 'none';
    $sent = $r['stage'] === 'dispatched';
    if (!$r['billed_here']) {
        // Our own one-off sale: billed from the quote builder.
        if ((string) $r['status'] === 'paid')     return 'paid';
        if ((string) $r['status'] === 'invoiced') return 'awaiting';
        return $sent ? 'needs_invoicing' : 'not_invoiced';
    }
    $inv = $r['invoices'];
    if (!$inv) return $sent ? 'needs_invoicing' : 'not_invoiced';
    $owed = 0.0; $late = false; $some = false;
    foreach ($inv as $i) {
        $owed += $i['outstanding'];
        $late  = $late || $i['overdue'];
        $some  = $some || $i['paid_some'];
    }
    if ($owed <= 0.004) return 'paid';
    if ($late)          return 'overdue';
    return $some ? 'part_paid' : 'awaiting';
}

/**
 * The Pipeline opened on one order (its search finds the order and opens its
 * card), at a column. The Pipeline is the ONE screen where an order is moved
 * on (John, 2026-10-12), so every making step's Next link lands there.
 */
function fc_pipeline_url(array $r, string $col = ''): string
{
    $qn = trim((string) ($r['quote_number'] ?? ''));
    return '/factory/pipeline.php' . ($qn !== '' ? '?q=' . rawurlencode($qn) : '') . ($col !== '' ? '#col-' . $col : '');
}

/**
 * The one thing to do next on an order, for its row: [label, url|'' , urgent].
 * Money that's late comes first; otherwise making until it's dispatched, then
 * money. Making steps link to the order's Pipeline card, where the buttons are.
 * Accounts links only for someone allowed on the accounts pages (#1057).
 */
function fc_next_action(array $r): array
{
    $acct  = fc_money_visible();
    $accId = (int) ($r['account_key'] ?? 0);
    $acUrl = $acct && $accId > 0 ? '/master-admin/account.php?id=' . $accId : '';
    if ($r['stage'] === 'notplaced') return ['Place the order', '/quote-builder/edit.php?id=' . (int) $r['id'], false];
    if ($r['money'] === 'overdue')   return ['Chase payment', $acUrl, true];
    switch ($r['stage']) {
        // Process order takes the order in, puts it on the floor and emails its
        // suppliers — the Pipeline card's button.
        case 'new':           return ['Process order',    fc_pipeline_url($r, 'new'), false];
        case 'in_production':
        case 'awaiting_supplier': return fc_making_next($r['tracks'] ?? null, $r);
        case 'ready':         return ['Dispatch', fc_pipeline_url($r, 'ready'), false];
    }
    switch ($r['money']) {
        case 'needs_invoicing':
            if (!$r['billed_here']) return ['Raise invoice', '/quote-builder/edit.php?id=' . (int) $r['id'], true];
            return ['Raise invoice', $acct ? '/master-admin/wholesale.php' . ($accId > 0 ? '?account=' . $accId : '') : '', true];
        case 'awaiting':  return ['Wait for payment', $acUrl, false];
        case 'part_paid': return ['Wait for the rest', $acUrl, false];
    }
    return ['Done', '', false];
}

/**
 * Next for an order in production, from ot_order_tracks(): a supplier PO that
 * didn't go → "Couldn't order from X", an item added after processing → "New
 * item to order from X" (both first: they're the office's job, whatever the
 * floor is doing); still being made → "Being made"; ordered and not received →
 * "Waiting on X". Same wording as Incoming orders; the supplier steps link to
 * the order ($r) on the Pipeline, where its Retry / Received buttons are.
 */
function fc_making_next(?array $t, array $r = []): array
{
    $pl = fc_pipeline_url($r, 'awaiting_supplier');
    foreach ($t['buy'] ?? [] as $sup => $b) {
        if ($b['state'] !== 'to_order') continue;
        if (($b['problem'] ?? '') === 'failed') return ["Couldn't order from " . $sup, $pl, true];
        if (($b['problem'] ?? '') === 'new_item') return ['New item to order from ' . $sup, $pl, true];
        return ['Order from ' . $sup, $pl, true];
    }
    if (!$t || $t['make']['made'] < $t['make']['units']) return ['Being made', '', false];
    foreach ($t['buy'] as $sup => $b) {
        if ($b['state'] === 'ordered') return ['Waiting on ' . $sup, $pl, false];
    }
    return ['Being made', '', false];
}

/**
 * In production but nothing left to make, only a bought-in item still to come
 * (e.g. 20/20 verticals made, Forest Wood with Home Creations): call it
 * "Awaiting supplier", the same name as the Incoming orders tab.
 * fulfilment_stage itself stays 'in_production'; this is display only.
 *
 * There is no Received stage on the console any more (John, 2026-10-12: Process
 * order makes it redundant). fc_making_stage()'s internal 'confirmed' becomes
 * New when the order still needs Process (ot_needs_process), else whatever its
 * parts say: being made, or waiting on a supplier (e.g. an all-bought-in order
 * whose purchase order couldn't go).
 */
function fc_supplier_stage(string $stage, ?array $t, string $fj = ''): string
{
    if (!in_array($stage, ['confirmed', 'in_production'], true)) return $stage;
    if (!$t) return $stage === 'confirmed' ? 'new' : $stage;
    if (ot_needs_process($fj, $t)) return 'new';
    if ($t['make']['made'] < $t['make']['units']) return 'in_production';
    foreach ($t['buy'] as $b) if ($b['state'] !== 'received') return 'awaiting_supplier';
    return 'in_production';
}

/** fc_stage_meta() key from a quote's status, fulfilment_stage and factory_jobs.status. */
function fc_making_stage(string $status, string $fs, string $fj): string
{
    if (!in_array($status, os_placed_statuses(), true)) return 'notplaced';
    if (in_array($fs, ['dispatched', 'ready', 'in_production'], true)) return $fs;
    if ($fj === 'dispatched') return 'dispatched';
    if ($fj === 'in_production' || $fj === 'made') return 'in_production';
    if ($fj === '' || $fj === 'new') return 'new';
    return 'confirmed';
}

/**
 * Making + Money for ONE order, for the order's own page — the same rules as
 * the Orders list row. Returns ['stage','money','invoices','next','done'].
 */
function fc_order_status(PDO $pdo, int $factoryId, array $quote): array
{
    $id = (int) ($quote['id'] ?? 0);
    $fj = '';
    try {
        $st = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? LIMIT 1');
        $st->execute([$id]);
        $fj = (string) ($st->fetchColumn() ?: '');
    } catch (Throwable $e) { /* no floor jobs table */ }
    $own = (int) ($quote['client_id'] ?? 0) === $factoryId;
    $r = [
        'id'          => $id,
        'quote_number' => (string) ($quote['quote_number'] ?? ''),
        'status'      => (string) ($quote['status'] ?? ''),
        'stage'       => fc_making_stage((string) ($quote['status'] ?? ''), (string) ($quote['fulfilment_stage'] ?? ''), $fj),
        'invoices'    => fc_money_visible() ? (fc_order_invoices($pdo, $factoryId, [$id])[$id] ?? []) : [],
        'tracks'      => ot_order_tracks($pdo, [$id], $factoryId)[$id] ?? null,
        'account_key' => (int) ($quote['account_client_id'] ?? 0) > 0 ? (int) $quote['account_client_id'] : ($own ? 0 : (int) ($quote['client_id'] ?? 0)),
    ];
    $r['stage'] = fc_supplier_stage($r['stage'], $r['tracks'], $fj);
    $r['billed_here'] = !($own && $r['account_key'] === 0);
    $r['money'] = fc_money_state($r);
    $r['done']  = $r['stage'] === 'dispatched' && in_array($r['money'], ['paid', 'none'], true);
    $r['next']  = fc_next_action($r);
    return $r;
}

/**
 * Every order the factory office should see, newest first (max 400). Each row adds:
 *   stage       — a fc_stage_meta() key
 *   account     — who it's for (trade account, else the ordering business, else "One-off")
 *   blinds      — blinds on it that the factory makes or supplies
 *   value       — net value to the factory where known (what the invoice bills), else null
 *   invoice     — the factory invoice number(s) once invoiced, space-separated, else ''
 *   invoices    — fc_order_invoices() entries for it
 *   money       — a fc_money_meta() key
 *   done        — dispatched AND paid (nothing left to do)
 *   next        — fc_next_action(): [label, url, urgent]
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
    $hasAcct    = $has('SELECT account_client_id FROM quotes LIMIT 0');
    // The factory's own UNPLACED quotes used to be narrowed to trade ones
    // ($tradeOwn: sale_type = 'trade' OR an account), which contradicted the
    // placed branch below — that has no sale-type restriction, so a PLACED
    // retail order of the factory's own does appear, and line 157 even labels it
    // "One-off sale". Only the draft was hidden.
    //
    // So a factory office user who used "+ New" → Retail landed in the quote
    // builder on a real quote and could then never find it again: absent from
    // Console Orders at every stage, uncounted on the dashboard, and
    // /orders/index.php and /orders/pipeline.php both redirect to the Console.
    // Checked live on BEV-2026-0002 before the fix — invisible on all five.
    // $hasSale went with $tradeOwn; sale_type is no longer read here.


    // The 400-row cap below is ordered so it can only ever drop FINISHED
    // orders. It used to be a plain created_at DESC cap, and both consumers
    // count the truncated set in PHP rather than asking the database:
    // factory/dashboard.php loops the rows into the New / In
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
    // NB a dispatched order can still be open on the MONEY side (not invoiced or
    // not paid); those sit with the dispatched ones, newest first, so the cap
    // drops the oldest of them first. Fine at today's volumes; if it ever bites,
    // sort unpaid dispatched orders ahead of paid ones here.
    // The tenant's archive flag is deliberately NOT applied to this query.
    // archived_at is set from the ACCOUNT's own Order history
    // (orders/archive.php) with no status restriction, so they can archive a
    // live placed order — their staff reasonably treat archiving as "finished
    // with on our side".
    //
    // No other factory consumer looks at it: not factory/incoming-orders.php,
    // not factory/floor.php, not master-admin/dispatch.php, not
    // ar_placed_orders(). Only this one did, so an archived order disappeared
    // from Console Orders and the dashboard while its blinds were still on the
    // floor, still in Incoming orders and still in the invoicing list — made
    // and shipped without ever showing on the screen the office works from.
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
                 OR (q.client_id = ? AND q.status IN ('draft','sent','accepted') ))
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

    // The order's factory invoices (void ones don't count): numbers for the row
    // and the search, what's still owed for the MONEY column.
    $invBy = fc_money_visible() ? fc_order_invoices($pdo, $factoryId, array_map(static fn ($r) => (int) $r['id'], $rows)) : [];

    require_once __DIR__ . '/remakes.php';
    $remakeOf = rm_remake_orders($pdo, array_map(static fn ($r) => (int) $r['id'], $rows));

    // Make + buy-in progress for orders still being made, so Next can say what
    // an order is really waiting on ("Waiting on Home Creations"), not just
    // "Being made" once the floor has finished. Same helper as Incoming orders.
    $tracks = ot_order_tracks($pdo, array_map(static fn ($r) => (int) $r['id'], array_filter($rows, static fn ($r) => (string) ($r['fulfilment_stage'] ?? '') !== 'dispatched')), $factoryId);

    foreach ($rows as &$r) {
        $id  = (int) $r['id'];
        $r['remake_of'] = $remakeOf[$id] ?? '';
        $r['tracks']    = $tracks[$id] ?? null;
        $own = (int) $r['client_id'] === $factoryId;
        $stage = fc_making_stage((string) $r['status'], (string) ($r['fulfilment_stage'] ?? ''), (string) ($r['factory_status'] ?? ''));
        $stage = fc_supplier_stage($stage, $r['tracks'], (string) ($r['factory_status'] ?? ''));
        $r['stage']   = $stage;
        $r['account'] = trim((string) ($r['account_name'] ?? '')) !== ''
            ? (string) $r['account_name']
            : ($own ? 'One-off sale' : (string) $r['tenant_name']);
        $r['account_key'] = (int) ($r['account_client_id'] ?? 0) > 0
            ? (int) $r['account_client_id']
            : ($own ? 0 : (int) $r['client_id']);
        $r['value']   = $valueBy[$id] ?? ($own ? (float) $r['subtotal'] : null);
        $r['invoices'] = $invBy[$id] ?? [];
        $r['invoice']  = implode(' ', array_column($r['invoices'], 'number'));
        // Our own one-off (retail) sale isn't billed through the factory's
        // invoices (ar_placed_orders() leaves it out), so its money is read from
        // the quote's own status instead.
        $r['billed_here'] = !($own && (int) $r['account_key'] === 0);
        $r['money'] = fc_money_state($r);
        $r['done']  = $stage === 'dispatched' && in_array($r['money'], ['paid', 'none'], true);   // 'none' = money not shown here
        $r['next']  = fc_next_action($r);
        $r['open_url'] = ($own || $stage === 'notplaced')
            ? '/quote-builder/edit.php?id=' . $id
            : '/factory/edit-order.php?order=' . $id;
    }
    unset($r);
    return $rows;
}
