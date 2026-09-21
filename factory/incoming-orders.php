<?php
declare(strict_types=1);

/**
 * Factory · Incoming Orders.
 *
 * The factory back-office queue: placed orders from every trade customer that
 * contain Beverley Blinds Trade lines (products.source_client_id = the factory
 * account), showing only those lines — a tenant's own products never appear.
 * Replaces re-keying from Blind Matrix's Online Submission inbox.
 *
 * Read-only for now. Mark-as-received / production status / work sheets follow.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/factory_poll.php';

requireFactory();

$pdo    = db();
$MASTER = current_factory_id();

$PLACED   = ['ordered', 'fitted', 'invoiced', 'paid'];
$inPlaced = "'" . implode("','", $PLACED) . "'";

$orders    = [];
$linesBy   = [];
$loadError = null;

// The factory status (received / into production) lives in factory_jobs. Probe
// so the queue still renders if migrate_factory_jobs.php hasn't run yet — then
// every order simply reads as "new".
$hasFactoryJobs = false;
try { $pdo->query('SELECT 1 FROM factory_jobs LIMIT 0'); $hasFactoryJobs = true; }
catch (Throwable $e) { /* table not migrated yet */ }
$hasStages = false;
if ($hasFactoryJobs) {
    try { $pdo->query('SELECT status_at FROM factory_jobs LIMIT 0'); $hasStages = true; }
    catch (Throwable $e) { /* stage columns not migrated yet */ }
}

$statusAtSel = $hasStages ? 'fj.status_at' : 'NULL';
$fjSelect = $hasFactoryJobs
    ? ", fj.status AS factory_status, fj.received_at, $statusAtSel AS status_at"
    : ', NULL AS factory_status, NULL AS received_at, NULL AS status_at';
$fjJoin  = $hasFactoryJobs ? 'LEFT JOIN factory_jobs fj ON fj.quote_id = q.id' : '';
$fjGroup = $hasFactoryJobs
    ? ($hasStages ? ', fj.status, fj.received_at, fj.status_at' : ', fj.status, fj.received_at')
    : '';
// New first, then received -> in production -> made -> dispatched.
$fjOrder = $hasFactoryJobs
    ? "CASE COALESCE(fj.status,'new')
            WHEN 'new' THEN 0 WHEN 'received' THEN 1 WHEN 'in_production' THEN 2
            WHEN 'made' THEN 3 WHEN 'dispatched' THEN 4 ELSE 5 END, "
    : '';

require_once __DIR__ . '/../_partials/bought_in.php';
$mpJoin  = bought_in_master_join('p', 'mp');
$inHouse = bought_in_inhouse_predicate('mp');   // true = made here (goes to floor)
try {
    $oStmt = $pdo->prepare(
        "SELECT q.id, q.client_id, c.company_name AS tenant,
                q.account_client_id,
                ac.company_name AS account_company, ac.contact_name AS account_contact,
                q.quote_number, q.status, q.created_at,
                q.customer_reference, q.additional_reference,
                q.end_customer_name, q.supplier_ordered_at, q.supplier_received_at,
                COUNT(qi.id)                  AS bev_lines,
                COALESCE(SUM(CASE WHEN $inHouse THEN qi.quantity ELSE 0 END), 0)     AS bev_qty,
                COALESCE(SUM(CASE WHEN NOT ($inHouse) THEN qi.quantity ELSE 0 END), 0) AS boughtin_qty,
                COALESCE(SUM(qi.quantity), 0) AS order_qty
                $fjSelect
           FROM quotes q
           JOIN clients c       ON c.id = q.client_id
           LEFT JOIN clients ac ON ac.id = q.account_client_id
           JOIN quote_items qi  ON qi.quote_id = q.id
           JOIN products p      ON p.id = qi.product_id
           $mpJoin
           $fjJoin
          WHERE q.status IN ($inPlaced)
            AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
       GROUP BY q.id, q.client_id, c.company_name, q.account_client_id,
                ac.company_name, ac.contact_name, q.quote_number, q.status,
                q.created_at, q.customer_reference, q.additional_reference,
                q.end_customer_name, q.supplier_ordered_at, q.supplier_received_at $fjGroup
       ORDER BY {$fjOrder}q.created_at DESC
          LIMIT 300"
    );
    $oStmt->execute([$MASTER]);
    $orders = $oStmt->fetchAll(PDO::FETCH_ASSOC);

    $ids = array_map(static fn ($o) => (int) $o['id'], $orders);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $lStmt = $pdo->prepare(
            "SELECT qi.quote_id, qi.line_no,
                    qi.product_name_snapshot, qi.system_name_snapshot,
                    qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
                    qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name
               FROM quote_items qi
               JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id IN ($ph)
                AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
           ORDER BY qi.quote_id, qi.line_no, qi.id"
        );
        $lStmt->execute([...$ids, $MASTER]);
        foreach ($lStmt->fetchAll(PDO::FETCH_ASSOC) as $ln) {
            $linesBy[(int) $ln['quote_id']][] = $ln;
        }
    }
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

// Floor progress per order (blinds made / total) once released — Phase B.
$floorProg = [];
$waitingBy = [];
if (bj_tables_ready($pdo) && !empty($ids)) {
    try { $floorProg = bj_order_progress($pdo, $ids); } catch (Throwable $e) { $floorProg = []; }
    // "Waiting on …" — which parts (streams) are still outstanding per order.
    try { $waitingBy = bj_order_waiting($pdo, $ids); } catch (Throwable $e) { $waitingBy = []; }
}

// Phase 0: the single derived fulfilment stage, shown next to the old pills so
// it can be checked against them. Guarded (column added by migration).
require_once __DIR__ . '/../_partials/order_stage.php';
$stageBy = [];
if (!empty($ids)) {
    try {
        $sph = implode(',', array_fill(0, count($ids), '?'));
        $sSt = $pdo->prepare("SELECT id, fulfilment_stage FROM quotes WHERE id IN ($sph)");
        $sSt->execute($ids);
        foreach ($sSt->fetchAll(PDO::FETCH_ASSOC) as $r) $stageBy[(int) $r['id']] = $r['fulfilment_stage'];
    } catch (Throwable $e) { $stageBy = []; }
}

$newCount = 0;
foreach ($orders as $o) {
    if (empty($o['factory_status'])) $newCount++;   // no factory_jobs row = new
}

// One-shot flash from a status action.
$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Production stages: pill [label, text colour, bg], the next action label, and
// the previous stage (for a one-step rewind).
$STAGE_META = [
    'new'           => ['New',           '#b91c1c', '#fee2e2'],   // not yet received — stands out
    'received'      => ['Received',     '#1e40af', '#dbeafe'],
    'in_production' => ['In production', '#92600a', '#fef3c7'],
    'made'          => ['Made',          '#166534', '#dcfce7'],
    'dispatched'    => ['Dispatched',    '#334155', '#e2e8f0'],
];
$STAGE_NEXT = [
    'new'           => ['received',      'Mark as received'],
    'received'      => ['in_production', 'Start production'],
    'in_production' => ['made',          'Mark made'],
    'made'          => ['dispatched',    'Dispatch'],
];
$STAGE_PREV = [
    'received'      => 'new',
    'in_production' => 'received',
    'made'          => 'in_production',
    'dispatched'    => 'made',
];

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('j M Y'); }
    catch (Throwable $e) { return (string) $ts; }
};

// What the page is looking at right now, so it can spot an order arriving while
// it sits open on a screen.
$pollVersion = fx_poll_version($pdo, 'incoming', $MASTER);

$factoryTitle = 'Incoming Orders';
$factoryNav   = 'incoming';
// Six columns plus a row of buttons: this is a wide table, not prose. Capped at
// reading width it crushed the status pills into the buttons and pushed the
// expand chevron off the edge. Same treatment as Floor and Scan log.
$factoryWide  = true;
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .io-head-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin: 0 0 0.4rem; }
    .io-h1 { font-size: 1.6rem; font-weight: 700; margin: 0; letter-spacing: -0.01em; }
    .io-badge { background: #dcfce7; color: #166534; font-size: 0.8125rem; font-weight: 600; padding: 0.1rem 0.6rem; border-radius: 999px; }
    .io-search { margin-left: auto; font: inherit; padding: 0.45rem 0.75rem; border: 1px solid var(--border, #e5e7eb); border-radius: 8px; min-width: 16rem; background: var(--bg-card, #fff); color: inherit; }
    .io-sub { color: var(--text-muted, #667); margin: 0 0 0.55rem; }
    /* Deliberately an OFFER, not an auto-reload: this page has "Start production"
       on it, and a page that reloads under someone's hand lands the click on the
       wrong order. */
    .io-news { position: sticky; top: 56px; z-index: 15; display: flex; align-items: center; gap: 0.9rem;
        background: #166534; color: #fff; padding: 0.7rem 1.1rem; border-radius: 10px; margin: 0 0 1rem;
        font-size: 0.95rem; font-weight: 600; box-shadow: 0 2px 10px rgba(0,0,0,0.15); }
    /* The banner is shown only by JS (news.hidden = false). Without this, the
       display:flex above overrides the [hidden] attribute and it's always on. */
    .io-news[hidden] { display: none; }
    .io-news button { font: inherit; font-weight: 700; cursor: pointer; border: none; border-radius: 8px;
        padding: 0.35rem 0.9rem; background: #fff; color: #166534; }
    .io-flash { padding: 0.7rem 1rem; border-radius: 10px; margin: 0 0 0.7rem; font-size: 0.9375rem; }
    .io-flash.ok  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .io-flash.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .io-empty { background: var(--bg-subtle, #f8fafc); border: 1px dashed var(--border, #e5e7eb); border-radius: 12px; padding: 1.75rem; color: var(--text-faint, #94a3b8); text-align: center; }

    /* Compact one-row-per-order list; click a row to open its blinds. */
    .io-list { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; overflow: hidden; background: var(--bg-card, #fff); box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    /* Each row is its own grid, so only FIXED columns line up between rows. The
       flexible column has to be the last one before the buttons: when it was the
       customer, a row with fewer buttons handed its spare width to the customer
       and pushed that row's date and count out of line with every other row. */
    /* Every column can give ground, so the row narrows with the window instead
       of spilling out of it. They all shrink by the same amount on every row,
       so the columns stay lined up whatever is in them. */
    .io-cols { display: grid; grid-template-columns:
        minmax(7rem, 8.5rem) minmax(7rem, 16rem) minmax(5.5rem, 7rem) minmax(4.5rem, 5.5rem)
        minmax(8rem, 1fr) minmax(0, max-content);
        gap: 0.5rem 1rem; align-items: center; }
    .io-list-head { padding: 0.55rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-faint, #94a3b8); font-weight: 600; border-bottom: 1px solid var(--border, #e5e7eb); background: var(--bg-subtle, #f8fafc); }
    .io-list-head span:last-child { text-align: right; }
    .io-item { border-bottom: 1px solid var(--border, #e5e7eb); }
    /* A not-yet-received order: a red left rail + a faint tint so it's obvious
       at a glance which orders are new and need actioning. */
    .io-item.is-new { border-left: 4px solid #dc2626; background: #fef4f4; }
    [data-theme="dark"] .io-item.is-new { background: rgba(220,38,38,0.10); }
    .io-item:last-child { border-bottom: none; }
    .io-summary { padding: 0.4rem 1rem; cursor: pointer; }
    .io-summary:hover { background: var(--bg-subtle, #f8fafc); }
    .io-summary:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; }
    .io-summary .ref { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .io-summary .cust { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .io-summary .date, .io-summary .cnt { color: var(--text-muted, #667); font-size: 0.875rem; white-space: nowrap; }
    /* Several pills can land here at once (stage, floor progress, bought-in).
       Lay them out properly instead of letting them run into the buttons. */
    .io-summary .stat { min-width: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.4rem; }
    .io-summary .io-actions { display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap; }
    .io-chev { color: var(--text-faint, #94a3b8); transition: transform 0.15s ease; display: inline-block; }
    .io-item.open .io-chev { transform: rotate(90deg); }
    .io-item.done .io-summary .ref, .io-item.done .io-summary .cust { opacity: 0.6; }
    .io-status { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.15rem 0.55rem; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
    .io-status.ordered { background: #dcfce7; color: #166534; }
    .io-stage { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.2rem 0.6rem; border-radius: 999px; white-space: nowrap; }
    .io-prog { display: inline-block; margin-left: 0.4rem; font-size: 0.6875rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 999px; background: #fef3c7; color: #92600a; text-decoration: none; white-space: nowrap; }
    .io-prog.all { background: #dcfce7; color: #166534; }
    .io-prog:hover { text-decoration: underline; }
    /* "Waiting on …" — the per-component backlog (headrails, fabrics) still to
       make. Understated so it reads as detail beside the made pill, not another
       loud status. */
    .io-waiting { font-size: 0.6875rem; font-weight: 600; color: var(--text-muted, #667); white-space: nowrap; }
    .io-waiting b { font-weight: 700; color: var(--text-secondary, #475569); }
    .io-btn { font: inherit; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; border-radius: 8px; padding: 0.35rem 0.8rem; }
    .io-btn.advance { background: #1f2a37; color: #fff; }
    .io-btn.advance:hover { background: #111a24; }
    .io-btn.worksheet { background: #2563eb; color: #fff; text-decoration: none; }
    .io-btn.worksheet:hover { background: #1d4ed8; }
    .io-btn.edit { background: var(--bg-subtle, #f1f5f9); color: #334155; text-decoration: none; border: 1px solid var(--border, #e5e7eb); }
    .io-btn.edit:hover { background: #e2e8f0; }
    .io-undo { background: none; border: none; color: var(--text-muted, #667); font-size: 0.75rem; text-decoration: underline; cursor: pointer; padding: 0; }

    .io-detail { padding: 0.2rem 1rem 0.9rem; }
    .io-detail .io-refs { color: var(--text-muted, #667); font-size: 0.85rem; margin: 0 0 0.5rem; }
    .io-lines { width: 100%; border-collapse: collapse; }
    .io-lines th { text-align: left; font-size: 0.7rem; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-faint, #94a3b8); font-weight: 600; padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--border, #e5e7eb); }
    .io-lines td { padding: 0.3rem 0.6rem; border-bottom: 1px solid var(--border, #e5e7eb); font-size: 0.9rem; vertical-align: top; }
    .io-lines tr:last-child td { border-bottom: none; }
    .io-lines .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .io-lines .prod { font-weight: 600; }
    /* Laptop width: the date is the first thing worth losing — you can still see
       it by opening the row — and dropping it keeps everything else readable
       rather than squeezing all six columns. */
    @media (max-width: 1040px) {
        .io-cols { grid-template-columns:
            minmax(7rem, 8.5rem) minmax(6rem, 14rem) minmax(4.5rem, 5.5rem)
            minmax(7rem, 1fr) minmax(0, max-content); }
        .io-summary .date, .io-list-head span:nth-child(3) { display: none; }
    }
    @media (max-width: 720px) {
        .io-cols { grid-template-columns: 1fr auto; }
        .io-summary .date, .io-summary .stat, .io-list-head span:nth-child(3), .io-list-head span:nth-child(5) { display: none; }
    }
</style>

<div class="io-news" id="io-news" hidden>
    <span id="io-news-text">New orders have come in.</span>
    <button type="button" id="io-news-btn">Refresh</button>
</div>

<div class="io-head-row">
    <h1 class="io-h1">Incoming Orders</h1>
    <?php if ($newCount > 0): ?><span class="io-badge"><?= (int) $newCount ?> new</span><?php endif; ?>
    <input type="search" id="io-search" class="io-search" placeholder="Search order no, customer, ref&hellip;" autocomplete="off">
</div>
<p class="io-sub ui-hint">Placed orders that contain <?= e($factoryName) ?> lines. Click an order to open its blinds.</p>

<?php if ($flashOk !== ''): ?><div class="io-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="io-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if ($loadError !== null): ?>
    <div class="io-empty">Couldn't load orders: <?= e($loadError) ?></div>
<?php elseif (!$orders): ?>
    <div class="io-empty">No incoming orders yet. Placed orders containing <?= e($factoryName) ?> lines will appear here — nothing to re-key.</div>
<?php else: ?>
    <div class="io-list">
        <div class="io-list-head io-cols">
            <span>Order</span><span>Customer</span><span>Date</span><span>Blinds</span><span>Status</span><span></span>
        </div>
        <?php foreach ($orders as $o):
            $qid      = (int) $o['id'];
            $lines    = $linesBy[$qid] ?? [];
            $ref      = (string) ($o['quote_number'] ?? ('#' . $qid));
            $tenant   = (string) ($o['tenant'] ?? 'Unknown account');
            // For a trade order (raised FOR an account) the real customer is the
            // linked account, not the factory that owns the quote. For a one-off
            // (no account) fall back to the end-customer name so the row isn't
            // just labelled "Beverley Blinds Trade" (the owning tenant).
            $accCompany = trim((string) ($o['account_company'] ?? ''));
            $accContact = trim((string) ($o['account_contact'] ?? ''));
            $endCustName = trim((string) ($o['end_customer_name'] ?? ''));
            // Who we're MAKING FOR: the trade account, else the tenant whose
            // order it is. A tenant's own customer used to win over the tenant,
            // so ABC Blinds' order for Dale Podmore was filed under Dale
            // Podmore — but the blinds go to ABC and so does the invoice.
            // The exception is the factory's own retail, where the tenant is us
            // and "Beverley Blinds Trade" tells the bench nothing.
            $ownRetail  = $accCompany === '' && (int) ($o['client_id'] ?? 0) === $MASTER;
            $custLabel  = $accCompany !== ''
                ? $accCompany
                : (($ownRetail && $endCustName !== '') ? $endCustName : $tenant);
            // Their customer is deliberately NOT on this row: we don't deal with
            // them, and the order number plus the customer reference are what
            // tie the job back to the client's paperwork. It's still in the
            // expanded detail below, and still searchable.
            $status   = (string) ($o['status'] ?? '');
            $custRef  = trim((string) ($o['customer_reference'] ?? ''));
            $addRef   = trim((string) ($o['additional_reference'] ?? ''));
            $endCust  = trim((string) ($o['end_customer_name'] ?? ''));
            $stage    = (string) ($o['factory_status'] ?? '');
            if ($stage === '') $stage = 'new';
            $stageAt   = $o['status_at'] ?? $o['received_at'] ?? null;
            $stagePill = $STAGE_META[$stage] ?? null;
            $next      = $STAGE_NEXT[$stage] ?? null;
            $prev      = $STAGE_PREV[$stage] ?? null;
            $prog      = $floorProg[$qid] ?? null;   // ['total'=>, 'done'=>] once on the floor
            // Bought-in (ordered from a supplier, not made here): its own track.
            $boughtinQty = (int) ($o['boughtin_qty'] ?? 0);
            $supOrdered  = $o['supplier_ordered_at']  ?? null;
            $supReceived = $o['supplier_received_at'] ?? null;
            $searchKey = strtolower(trim($ref . ' ' . $custLabel . ' ' . $accContact . ' ' . $custRef . ' ' . $addRef . ' ' . $endCust));
        ?>
            <div class="io-item<?= ($stageBy[$qid] ?? '') === 'dispatched' ? ' done' : '' ?><?= ($stageBy[$qid] ?? '') === 'confirmed' ? ' is-new' : '' ?>" data-search="<?= e($searchKey) ?>">
                <div class="io-summary io-cols" role="button" tabindex="0" aria-expanded="false">
                    <span class="ref"><?= e($ref) ?></span>
                    <span class="cust"><?= e($custLabel) ?><?php if ($accContact !== ''): ?> <span style="color:var(--text-faint,#6b7280);font-weight:400">· <?= e($accContact) ?></span><?php endif; ?></span>
                    <span class="date"><?= e($fmtDate($o['created_at'] ?? null)) ?></span>
                    <?php
                        // The count is the whole order, not just what we make.
                        // An order that's entirely bought in used to read
                        // "0 blinds" next to five blinds' worth of work.
                        // The bought-in split is already on the status badge, so
                        // this column stays a single figure and the row stays
                        // in its columns.
                        $orderQty = (int) ($o['order_qty'] ?? ((int) $o['bev_qty'] + $boughtinQty));
                    ?>
                    <span class="cnt"><?= $orderQty ?> blind<?= $orderQty === 1 ? '' : 's' ?></span>
                    <span class="stat">
                        <?php
                            // Phase 3: the single fulfilment stage IS the status now (Confirmed /
                            // In Production / Ready / Dispatched), coloured by stage. The old raw
                            // factory-jobs pill has been retired now the roll-up is proven.
                            $fs = $stageBy[$qid] ?? null;
                            $stCols = [
                                'confirmed'     => ['#5b6b7f', '#e6ebf1'],
                                'in_production' => ['#b5730f', '#f7ecd6'],
                                'ready'         => ['#245ea3', '#dde8f6'],
                                'dispatched'    => ['#0d7a67', '#d6ece6'],
                            ];
                            $sc = $stCols[$fs] ?? ['#5b6b7f', '#eef1f4'];
                        ?>
                        <?php if ($fs !== null): ?>
                            <span class="io-stage" style="color:<?= $sc[0] ?>;background:<?= $sc[1] ?>;font-weight:700"><?= e(os_stage_label($fs)) ?></span>
                        <?php else: ?>
                            <span class="io-status <?= $status === 'ordered' ? 'ordered' : '' ?>"><?= e($status !== '' ? $status : 'new') ?></span>
                        <?php endif; ?>
                        <?php if ($prog !== null && $prog['total'] > 0): ?>
                            <a class="io-prog<?= $prog['done'] >= $prog['total'] ? ' all' : '' ?>" href="/factory/floor.php" title="On the production floor"><?= (int) $prog['done'] ?>/<?= (int) $prog['total'] ?> made</a>
                        <?php endif; ?>
                        <?php
                            // "Waiting on …" — the outstanding parts by bench/stream, so
                            // the queue shows WHY an order isn't finished at a glance.
                            $waiting = $waitingBy[$qid] ?? [];
                            $parts = [];
                            foreach ($waiting as $streamName => $cnt) {
                                $lbl = strtolower(trim((string) $streamName));
                                if ($cnt !== 1 && $lbl !== '' && substr($lbl, -1) !== 's') $lbl .= 's';
                                $parts[] = e((int) $cnt . ' ' . $lbl);   // escaped, then wrapped in <b> below
                            }
                        ?>
                        <?php if ($parts): ?>
                            <span class="io-waiting" title="Parts still to make on the floor">Waiting on <b><?= implode('</b> · <b>', $parts) ?></b></span>
                        <?php endif; ?>
                        <?php if ($boughtinQty > 0): ?>
                            <?php if ($supReceived): ?>
                                <span class="io-stage" style="color:#166534;background:#dcfce7" title="Bought-in items received">Bought-in: received</span>
                            <?php elseif ($supOrdered): ?>
                                <span class="io-stage" style="color:#1e40af;background:#dbeafe" title="Ordered from supplier <?= e(date('j M Y', strtotime((string) $supOrdered))) ?>">Bought-in: ordered</span>
                            <?php else: ?>
                                <span class="io-stage" style="color:#b91c1c;background:#fee2e2" title="<?= (int) $boughtinQty ?> bought-in item(s) to order from the supplier">Bought-in: <?= (int) $boughtinQty ?> to order</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                    <span class="io-actions">
                        <a class="io-btn edit" href="/factory/edit-order.php?order=<?= $qid ?>">Edit</a>
                        <a class="io-btn worksheet" href="/factory/worksheet-print.php?order=<?= $qid ?>" target="_blank" rel="noopener">Worksheet</a>
                        <?php if ($boughtinQty > 0 && !$supReceived): ?>
                            <a class="io-btn" href="/factory/order-suppliers.php?id=<?= $qid ?>" title="Order the bought-in items from their supplier"><?= $supOrdered ? '📦 Bought-in' : '📦 Order bought-in' ?></a>
                        <?php endif; ?>
                        <?php if ($boughtinQty > 0 && $supOrdered && !$supReceived): ?>
                            <form method="post" action="/factory/boughtin-received.php" style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                <button type="submit" class="io-btn" title="Mark the bought-in items as received from the supplier">✓ Received</button>
                            </form>
                        <?php endif; ?>
                        <?php
                            // Phase 1: dispatch is gated on Ready (all made AND all bought-in received).
                            // Phase 3: an all-bought-in order (no in-house blinds) has nothing to make,
                            // so skip the production steps — it goes received -> dispatch once the
                            // bought-in items arrive.
                            $dispatchReady = ($stageBy[$qid] ?? '') === 'ready';
                            $noProduction  = ((int) ($o['bev_qty'] ?? 0) === 0);
                        ?>
                        <?php if ($noProduction): ?>
                            <?php if ($next !== null && $next[0] === 'received'): ?>
                                <form method="post" action="/factory/set-status.php" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                    <button type="submit" name="status" value="received" class="io-btn advance">Mark as received</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($dispatchReady): ?>
                                <form method="post" action="/factory/set-status.php" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                    <button type="submit" name="status" value="dispatched" class="io-btn advance">Dispatch</button>
                                </form>
                            <?php endif; ?>
                        <?php elseif ($next !== null && ($next[0] !== 'dispatched' || $dispatchReady)): ?>
                            <form method="post" action="/factory/set-status.php" style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                <button type="submit" name="status" value="<?= e($next[0]) ?>" class="io-btn advance"><?= e($next[1]) ?></button>
                            </form>
                        <?php elseif ($next !== null && $next[0] === 'dispatched'): ?>
                            <span class="io-btn advance" style="opacity:.45;cursor:not-allowed" title="Not ready to dispatch yet — every blind must be made and every bought-in item received first">Dispatch</span>
                        <?php endif; ?>
                        <span class="io-chev" aria-hidden="true">&#9654;</span>
                    </span>
                </div>
                <div class="io-detail" hidden>
                    <?php if ($custRef !== '' || $addRef !== '' || $endCust !== '' || $stageAt): ?>
                        <p class="io-refs">
                            <?= $orderQty ?> unit<?= $orderQty === 1 ? '' : 's' ?>
                            <?php if ($custRef !== ''): ?> &middot; Ref: <strong><?= e($custRef) ?></strong><?php endif; ?>
                            <?php if ($addRef !== ''): ?> &middot; <?= e($addRef) ?><?php endif; ?>
                            <?php if ($endCust !== ''): ?> &middot; <?= e($endCust) ?><?php endif; ?>
                            <?php if ($stagePill !== null && $stageAt): ?> &middot; <?= e($stagePill[0]) ?> <?= e($fmtDate($stageAt)) ?><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($lines): ?>
                    <table class="io-lines">
                        <thead>
                            <tr><th>#</th><th>Product</th><th>Fabric / Colour</th><th class="num">W&times;D (mm)</th><th class="num">Qty</th><th>Room</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $ln):
                                $fab = trim((string) ($ln['fabric_name_snapshot'] ?? ''));
                                $col = trim((string) ($ln['fabric_colour_snapshot'] ?? ''));
                                $sys = trim((string) ($ln['system_name_snapshot'] ?? ''));
                            ?>
                                <tr>
                                    <td class="num"><?= (int) $ln['line_no'] ?></td>
                                    <td class="prod">
                                        <?= e((string) ($ln['product_name_snapshot'] ?? '')) ?>
                                        <?php if ($sys !== ''): ?><br><span style="font-weight:400;font-size:0.8125rem;color:var(--text-muted,#667)"><?= e($sys) ?></span><?php endif; ?>
                                    </td>
                                    <td><?= e(trim($fab . ($col !== '' ? ' / ' . $col : ''))) ?></td>
                                    <td class="num"><?= (int) $ln['width_mm'] ?> &times; <?= (int) $ln['drop_mm'] ?></td>
                                    <td class="num"><?= (int) $ln['quantity'] ?></td>
                                    <td><?= e((string) ($ln['room_name'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php if ($prev !== null): ?>
                        <form method="post" action="/factory/set-status.php" style="margin:0.6rem 0 0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="quote_id" value="<?= $qid ?>">
                            <button type="submit" name="status" value="<?= e($prev) ?>" class="io-undo">&larr; step status back</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    var list = document.querySelector('.io-list');
    if (list) {
        function toggle(sum) {
            var item = sum.closest('.io-item'); if (!item) return;
            var open = item.classList.toggle('open');
            var detail = item.querySelector('.io-detail');
            if (detail) detail.hidden = !open;
            sum.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        list.addEventListener('click', function (e) {
            if (e.target.closest('.io-actions')) return;   // buttons/links keep their own behaviour
            var sum = e.target.closest('.io-summary');
            if (sum) toggle(sum);
        });
        list.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('io-summary')) { e.preventDefault(); toggle(e.target); }
        });
    }
    // This screen sits open all day, so an order can land and nobody know. Poll
    // a cheap version string and OFFER a refresh — never take one, because the
    // buttons on this page start production, and a page that reloads itself
    // under a hand puts that click on the wrong order.
    (function () {
        var mine = <?= json_encode($pollVersion) ?>;
        var news = document.getElementById('io-news');
        var text = document.getElementById('io-news-text');
        var btn  = document.getElementById('io-news-btn');
        if (!news || !btn) return;
        var baseTitle = document.title;
        btn.addEventListener('click', function () { location.reload(); });

        // Track when the user last touched the page. A reload that lands while
        // someone is clicking "Start production" would put that click on the
        // wrong row — so we only auto-refresh when they've been idle for a few
        // seconds. While they're actively working we fall back to the OFFER
        // banner and let them refresh when they're ready.
        var lastTouch = 0;
        ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(function (ev) {
            document.addEventListener(ev, function () { lastTouch = Date.now(); }, true);
        });
        var IDLE_MS = 4000;

        // Poll a cheap version string. When it differs from what we loaded with,
        // a new order (or a status move) has landed. If the operator is idle we
        // just refresh so the new order appears on its own, colour-coded; if
        // they're mid-click we show the OFFER banner instead. Works in a hidden
        // background tab too (badges the count into the tab title).
        function check() {
            fetch('/factory/poll.php?what=incoming', { cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (!j || !j.v || j.v === 'x' || j.v === mine) return;
                    var a = (mine.match(/^i(\d+)/) || [])[1], b = (j.v.match(/^i(\d+)/) || [])[1];
                    var n = (a !== undefined && b !== undefined) ? (parseInt(b, 10) - parseInt(a, 10)) : 0;

                    // Safe to auto-refresh: nobody's touched the page recently, or
                    // it's a hidden background tab. New orders then just appear.
                    if (document.hidden || (Date.now() - lastTouch) > IDLE_MS) {
                        location.reload();
                        return;
                    }

                    // Busy — offer, don't force.
                    text.textContent = n > 0
                        ? (n === 1 ? '1 new order has come in.' : n + ' new orders have come in.')
                        : 'Orders have changed.';
                    news.hidden = false;
                    document.title = (n > 0 ? '(' + n + ') ' : '● ') + baseTitle;
                })
                .catch(function () { /* offline / blip — say nothing */ });
        }
        setInterval(check, 20000);
    })();

    var search = document.getElementById('io-search');
    if (search) search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        document.querySelectorAll('.io-item').forEach(function (it) {
            it.style.display = (!q || (it.dataset.search || '').indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
