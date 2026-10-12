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
 * Each order has two tracks (_partials/order_tracks.php): lines we MAKE, which
 * go to the floor, and lines we BUY IN from a supplier, which are ordered then
 * received. The tabs are the next jobs (New to accept · To make · To order ·
 * Awaiting supplier · Ready to dispatch), and an order sits in every tab where it
 * still has work. Each row has one main button for that tab; the rest are under
 * "⋯". Route A of the factory order-flow rethink (John, 2026-10-12).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/factory_poll.php';
require __DIR__ . '/../_partials/order_tracks.php';

requireFactory();

// Every row action bar the Worksheet link goes to an office-only screen:
// edit-order, order-suppliers, boughtin-received and set-status are all
// requireFactoryOffice(). Rendering them to a floor login offered buttons that
// 403 on click — #852 gated the destinations and left the buttons. Worksheet
// and the expand chevron stay: the floor is meant to read and print tickets.
$ioOffice = !function_exists('factory_user_is_office') || factory_user_is_office();

// Floor logins see the next-job tabs and the MAKE / BUY chips only when the
// factory switches it on (Factory → Settings, client_settings.floor_sees_lanes,
// default off — John, 2026-10-12). Read-only either way: no action buttons.
$showLanes = $ioOffice;
if (!$showLanes) {
    try {
        $fl = db()->prepare('SELECT floor_sees_lanes FROM client_settings WHERE client_id = ? LIMIT 1');
        $fl->execute([current_factory_id()]);
        $showLanes = (int) $fl->fetchColumn() === 1;
    } catch (Throwable $e) { /* column not migrated yet — keep them hidden */ }
}

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
            "SELECT qi.id, qi.quote_id, qi.line_no,
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

// Possible duplicates: another placed order from the SAME customer (tenant, or
// trade account on a factory order) carrying the same customer reference.
// Flagged on the row so the office can catch an order keyed in twice.
// A remake deliberately carries its original's customer reference —
// rm_create_remake_order()'s $skip list doesn't drop customer_reference, and
// shouldn't: the office needs to see which job it belongs to. But that makes
// every remake look like the same order keyed in twice, so each approved
// remake flagged its own original and was flagged right back. Neither side is
// a duplicate, and a warning that fires on every remake is one the office
// learns to ignore — which is the real cost, because then it misses a genuine
// double entry.
$dupBy = [];
if (!empty($ids)) {
    try {
        $dph = implode(',', array_fill(0, count($ids), '?'));
        // Matched on the EFFECTIVE trade account, not on client_id and
        // account_client_id separately. The same account's order is stored in
        // two different shapes depending on who keyed it in: a portal order is
        // client_id = the account with account_client_id NULL, while one the
        // office keys in is client_id = the factory with account_client_id = the
        // account. The old join required b.client_id = a.client_id, so those two
        // never matched — and "they ordered through the portal and also rang it
        // in" is precisely the double entry this badge was added for (#917). It
        // only ever caught duplicates keyed in the same way.
        //
        // COALESCE(NULLIF(account_client_id,0), client_id) is the expression the
        // rest of the factory code uses for this — ar_account_expr(),
        // _partials/factory_ar.php:186. Inlined rather than requiring
        // factory_ar.php, which this page does not otherwise pull in.
        $acctExpr = static fn (string $p): string
            => "COALESCE(NULLIF({$p}account_client_id, 0), {$p}client_id)";
        $dSt = $pdo->prepare(
            "SELECT a.id, b.quote_number
               FROM quotes a
               JOIN quotes b ON " . $acctExpr('b.') . " = " . $acctExpr('a.') . "
                            AND b.id <> a.id
                            AND b.status IN ($inPlaced)
                            AND b.remake_of_quote_id IS NULL
                            AND LOWER(TRIM(b.customer_reference)) = LOWER(TRIM(a.customer_reference))
              WHERE a.id IN ($dph) AND TRIM(COALESCE(a.customer_reference, '')) <> ''
                AND a.remake_of_quote_id IS NULL
              ORDER BY b.created_at"
        );
        $dSt->execute($ids);
        foreach ($dSt->fetchAll(PDO::FETCH_ASSOC) as $r) $dupBy[(int) $r['id']][] = (string) $r['quote_number'];
    } catch (Throwable $e) { $dupBy = []; }
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

// Per-factory order colour-coding — tint each row by its state (configurable in
// the factory Settings tab).
require_once __DIR__ . '/../_partials/factory_order_colours.php';
$ocolours = foc_colours($pdo, $MASTER);

// Remake orders (Factory Console stage 3) — badge + their own row colour.
require_once __DIR__ . '/../_partials/remakes.php';
$remakeOf = !empty($ids) ? rm_remake_orders($pdo, $ids) : [];

$newCount = 0;
foreach ($orders as $o) {
    if (empty($o['factory_status'])) $newCount++;   // no factory_jobs row = new
}

// ── The two tracks + the tabs ───────────────────────────────────────────────
// Each order's MAKE progress and BUY-IN suppliers, then the tabs (lanes) it
// belongs in: one per job still to do. An order with a roller on the floor and a
// venetian still to order from Hunter Douglas is in both To make and To order.
$tracks = !empty($ids) ? ot_order_tracks($pdo, $ids, $MASTER) : [];
$LANES = [
    'new'        => 'New to accept',
    'make'       => 'To make',
    'order'      => 'To order from suppliers',
    'awaiting'   => 'Awaiting supplier',
    'ready'      => 'Ready to dispatch',
    'open'       => 'All open',
    'dispatched' => 'Dispatched',
];
$lane = (string) ($_GET['lane'] ?? 'open');
if (!isset($LANES[$lane]) || !$showLanes) $lane = 'open';
$emptyTrack = ['make' => ['units' => 0, 'made' => 0, 'released' => false], 'buy' => [],
               'total_units' => 0, 'ready_units' => 0, 'waiting_on' => [], 'lines' => []];

$laneCount = array_fill_keys(array_keys($LANES), 0);
$lanesOf   = [];
foreach ($orders as $o) {
    $qid = (int) $o['id'];
    $t   = $tracks[$qid] ?? $emptyTrack;
    $fj  = (string) ($o['factory_status'] ?? '');
    $fs  = (string) ($stageBy[$qid] ?? '');
    if ($fs === 'dispatched' || $fj === 'dispatched') {
        $ls = ['dispatched'];
    } else {
        $ls = ['open'];
        if ($fj === '') $ls[] = 'new';
        if ($fj !== '' && $t['make']['made'] < $t['make']['units']) $ls[] = 'make';
        foreach ($t['buy'] as $b) {
            if ($b['state'] === 'to_order') $ls[] = 'order';
            if ($b['state'] === 'ordered')  $ls[] = 'awaiting';
        }
        if ($fs === 'ready') $ls[] = 'ready';
    }
    $lanesOf[$qid] = array_values(array_unique($ls));
    foreach ($lanesOf[$qid] as $l) $laneCount[$l]++;
}
$orders = array_values(array_filter($orders, static fn ($o) => in_array($lane, $lanesOf[(int) $o['id']] ?? [], true)));

/**
 * The jobs this order has outstanding, in the order the office should do them:
 * accept it, order its bought-in items (suppliers take days), start production,
 * book the supplier's goods in, dispatch. The first one that fits the tab is the
 * row's main button; the rest go under "⋯".
 */
// One rule for both screens: ot_next_jobs() in _partials/order_tracks.php (the
// Factory Pipeline cards offer the same buttons).
$jobsFor = static fn (array $o, array $t, string $fs): array => ot_next_jobs((string) ($o['factory_status'] ?? ''), $t, $fs);

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
    .io-dup { display: inline-block; margin-left: 0.4rem; font-size: 0.6875rem; font-weight: 700; white-space: nowrap;
              padding: 0.1rem 0.45rem; border-radius: 999px; background: #fef3c7; color: #92400e; }
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

    /* Tabs = the next jobs. An order appears in every tab it has work in. */
    .io-lanes { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0 0 0.8rem; }
    .io-lanes a { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.75rem; border-radius: 999px;
        border: 1px solid var(--border, #e5e7eb); background: var(--bg-card, #fff); color: inherit; text-decoration: none;
        font-size: 0.875rem; font-weight: 600; white-space: nowrap; }
    .io-lanes a .n { font-variant-numeric: tabular-nums; color: var(--text-muted, #667); font-weight: 700; }
    .io-lanes a.active { background: #1f2a37; border-color: #1f2a37; color: #fff; }
    .io-lanes a.active .n { color: #cbd5e1; }
    .io-lanes .sep { width: 1px; background: var(--border, #e5e7eb); margin: 0.2rem 0.2rem; }

    /* The two tracks: MAKE (to the floor) and one chip per BUY-IN supplier. */
    .io-track { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.6875rem; font-weight: 700;
        padding: 0.15rem 0.5rem; border-radius: 6px; white-space: nowrap; text-decoration: none; border: 1px solid transparent; }
    .io-track .k { letter-spacing: 0.05em; opacity: 0.8; }
    .io-track.t-todo { background: #fee2e2; color: #991b1b; }
    .io-track.t-wait { background: #dbeafe; color: #1e40af; }
    .io-track.t-prog { background: #fef3c7; color: #92600a; }
    .io-track.t-done { background: #dcfce7; color: #166534; }
    .io-track.t-idle { background: var(--bg-subtle, #f1f5f9); color: #475569; border-color: var(--border, #e5e7eb); }
    .io-track .warn { color: #b91c1c; font-weight: 800; }
    .io-ready { flex-basis: 100%; font-size: 0.75rem; color: var(--text-muted, #667); }
    .io-ready b { color: var(--text-secondary, #334155); }

    /* "⋯" — everything that isn't the row's main job. */
    .io-more { position: relative; }
    .io-more > summary { list-style: none; cursor: pointer; font-weight: 800; letter-spacing: 0.1em; padding: 0.3rem 0.6rem;
        border-radius: 8px; border: 1px solid var(--border, #e5e7eb); background: var(--bg-subtle, #f1f5f9); color: #334155; }
    .io-more > summary::-webkit-details-marker { display: none; }
    .io-more[open] > summary { background: #e2e8f0; }
    .io-menu { position: absolute; right: 0; top: calc(100% + 4px); z-index: 20; min-width: 13rem; display: flex; flex-direction: column;
        background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); padding: 0.3rem; }
    .io-menu a, .io-menu button { font: inherit; font-size: 0.875rem; text-align: left; color: inherit; text-decoration: none; background: none;
        border: 0; border-radius: 6px; padding: 0.45rem 0.6rem; cursor: pointer; width: 100%; }
    .io-menu a:hover, .io-menu button:hover { background: var(--bg-subtle, #f1f5f9); }
    .io-menu form { margin: 0; }
    .io-menu hr { border: 0; border-top: 1px solid var(--border, #e5e7eb); margin: 0.25rem 0; }
    .io-btn.floor { background: var(--bg-subtle, #f1f5f9); color: #334155; text-decoration: none; border: 1px solid var(--border, #e5e7eb); }
    .io-lines .track { font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
    .io-actions form { margin: 0; }
    /* The "⋯" menu drops out of its row, so the list can't clip its overflow;
       the rounded corners come from the first and last rows instead. */
    .io-list { overflow: visible; }
    .io-list-head { border-radius: 12px 12px 0 0; }
    .io-item:last-child { border-radius: 0 0 12px 12px; }

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

<?php if ($showLanes): ?>
<nav class="io-lanes" aria-label="Show orders by their next job">
    <?php foreach ($LANES as $k => $label):
        $href = '/factory/incoming-orders.php' . ($k === 'open' ? '' : '?lane=' . $k); ?>
        <?php if ($k === 'open'): ?><span class="sep" aria-hidden="true"></span><?php endif; ?>
        <a href="<?= e($href) ?>" class="<?= $lane === $k ? 'active' : '' ?>"<?= $lane === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="n"><?= (int) $laneCount[$k] ?></span></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if ($loadError !== null): ?>
    <div class="io-empty">Couldn't load orders: <?= e($loadError) ?></div>
<?php elseif (!$orders && $lane !== 'open'): ?>
    <div class="io-empty">Nothing in <strong><?= e($LANES[$lane]) ?></strong> right now.</div>
<?php elseif (!$orders && $laneCount['dispatched'] > 0): ?>
    <div class="io-empty">Nothing open right now — every order has been dispatched. See <a href="/factory/incoming-orders.php?lane=dispatched">Dispatched</a>.</div>
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
            <?php
                // Whole-row colour by state (New / In production / Ready / Dispatched
                // / Invoiced / Paid), from the factory's configurable palette.
                $ostate    = foc_state_for_order($stageBy[$qid] ?? null, $status);
                if (isset($remakeOf[$qid]) && !in_array($ostate, ['dispatched', 'invoiced', 'paid'], true)) $ostate = 'remake';
                $orowStyle = isset($ocolours[$ostate]) ? foc_row_style($ocolours[$ostate]['color']) : '';
            ?>
            <div class="io-item<?= ($stageBy[$qid] ?? '') === 'dispatched' ? ' done' : '' ?>" data-search="<?= e($searchKey) ?>" style="<?= e($orowStyle) ?>">
                <div class="io-summary io-cols" role="button" tabindex="0" aria-expanded="false">
                    <span class="ref"><?= e($ref) ?></span>
                    <span class="cust"><?= e($custLabel) ?><?php if ($accContact !== ''): ?> <span style="color:var(--text-faint,#6b7280);font-weight:400">· <?= e($accContact) ?></span><?php endif; ?><?php if (!empty($dupBy[$qid])): ?>
                        <span class="io-dup" title="Same customer reference (<?= e($custRef) ?>) as <?= e(implode(', ', $dupBy[$qid])) ?> — check it isn't the same order twice">&#9888; same ref as <?= e(implode(', ', $dupBy[$qid])) ?></span><?php endif; ?></span>
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
                        <?php if (isset($remakeOf[$qid])): ?><?= rm_badge($remakeOf[$qid]) ?><?php endif; ?>
                        <?php
                            // One set of words on this page: New (not accepted yet) /
                            // Received (accepted) / In production / Ready / Dispatched.
                            // os_stage_label() says "Confirmed" for Received, but it also
                            // labels the trade customer's own pill, so it's mapped here.
                            $fs = $stageBy[$qid] ?? null;
                            $t  = $tracks[$qid] ?? $emptyTrack;
                            $fjNow = (string) ($o['factory_status'] ?? '');
                            $stCols = [
                                'new'           => ['#b91c1c', '#fee2e2'],
                                'confirmed'     => ['#1e40af', '#dbeafe'],
                                'in_production' => ['#b5730f', '#f7ecd6'],
                                'ready'         => ['#245ea3', '#dde8f6'],
                                'dispatched'    => ['#0d7a67', '#d6ece6'],
                            ];
                            $stKey = ($fjNow === '' && $fs !== 'dispatched') ? 'new' : (string) $fs;
                            $stLabel = [
                                'new' => 'New', 'confirmed' => 'Received', 'in_production' => 'In production',
                                'ready' => 'Ready', 'dispatched' => 'Dispatched',
                            ][$stKey] ?? ($status !== '' ? ucfirst($status) : 'New');
                            $sc = $stCols[$stKey] ?? ['#5b6b7f', '#eef1f4'];
                        ?>
                        <span class="io-stage" style="color:<?= $sc[0] ?>;background:<?= $sc[1] ?>;font-weight:700"><?= e($stLabel) ?></span>
                        <?php
                            // MAKE track — the blinds we make, linked to the floor.
                            $mk = $t['make'];
                            if ($showLanes && $mk['units'] > 0):
                                $mkCls = $mk['made'] >= $mk['units'] ? 't-done' : ($mk['released'] ? 't-prog' : 't-idle');
                                $mkTxt = $mk['released'] || $mk['made'] > 0
                                    ? $mk['made'] . '/' . $mk['units'] . ' made'
                                    : $mk['units'] . ' to make';
                        ?>
                            <a class="io-track <?= $mkCls ?>" href="/factory/order-areas.php?order=<?= $qid ?>" title="Blinds we make here — open this order on the floor"><span class="k">MAKE</span> <?= e($mkTxt) ?></a>
                        <?php endif; ?>
                        <?php
                            // BUY-IN track — one chip per supplier.
                            foreach ($showLanes ? $t['buy'] : [] as $supName => $b):
                                $bCls = $b['state'] === 'received' ? 't-done' : ($b['state'] === 'ordered' ? 't-wait' : 't-todo');
                                $bTxt = $b['state'] === 'received' ? 'received'
                                      : ($b['state'] === 'ordered' ? 'ordered ' . date('j M', strtotime((string) $b['sent_at'])) : $b['units'] . ' to order');
                                $noMail = $b['state'] === 'to_order' && !$b['has_email'];
                        ?>
                            <span class="io-track <?= $bCls ?>" title="<?= (int) $b['units'] ?> bought in from <?= e($supName) ?><?= $noMail ? ' — no order email set for this supplier (Settings › Suppliers), so it can\'t be sent automatically' : '' ?>"><span class="k">BUY</span> <?= e($supName) ?> · <?= e($bTxt) ?><?php if ($noMail): ?> <span class="warn">· no email set</span><?php endif; ?></span>
                        <?php endforeach; ?>
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
                            <span class="io-waiting" title="Parts still to make on the floor">Floor: <b><?= implode('</b> · <b>', $parts) ?></b></span>
                        <?php endif; ?>
                        <?php if ($showLanes && $fs !== 'dispatched' && $t['total_units'] > 0): ?>
                            <span class="io-ready">
                                <b><?= (int) $t['ready_units'] ?> of <?= (int) $t['total_units'] ?> ready</b><?php if ($t['waiting_on']): ?> · waiting on <?= e(implode(' · ', $t['waiting_on'])) ?><?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="io-actions">
                        <?php if (!$ioOffice): /* floor logins: read and print tickets only, as before */ ?>
                            <a class="io-btn worksheet" href="/factory/worksheet-print.php?order=<?= $qid ?>" target="_blank" rel="noopener">Worksheet</a>
                        <?php else:
                            // Every job this order still has, in doing order. The main
                            // button is the first one belonging to this tab (in All open,
                            // simply the first); the others go under "⋯".
                            $jobs = $jobsFor($o, $t, (string) $fs);
                            $main = null;
                            foreach ($jobs as $i => $j) {
                                if ($lane === 'open' || $j['lane'] === $lane) { $main = $j; unset($jobs[$i]); break; }
                            }
                            $backQ = '&back=' . rawurlencode($lane);
                            // Render one job as a button (main) or a menu item.
                            $jobHtml = static function (array $j, int $qid, string $lane, string $cls, string $backQ): string {
                                $h  = csrf_field() . '<input type="hidden" name="quote_id" value="' . $qid . '">'
                                    . '<input type="hidden" name="back" value="' . e($lane) . '">';
                                $sup = (string) ($j['supplier'] ?? '');
                                switch ($j['kind']) {
                                    case 'accept':
                                        // Accept = take it on AND put its blinds on the floor
                                        // (set-status.php, accept=1). All bought-in: just accepted.
                                        return '<form method="post" action="/factory/set-status.php">' . $h
                                             . '<input type="hidden" name="accept" value="1">'
                                             . '<button type="submit" name="status" value="received" class="' . $cls . '" title="Take the order on and put its blinds on the floor">Accept</button></form>';
                                    case 'start':
                                        return '<form method="post" action="/factory/set-status.php">' . $h
                                             . '<button type="submit" name="status" value="in_production" class="' . $cls . '" title="Put the blinds we make on the floor">Start production</button></form>';
                                    case 'order':
                                        return '<a class="' . $cls . '" href="/factory/order-suppliers.php?id=' . $qid . $backQ . '" title="Send the bought-in lines to ' . e($sup) . '">Order from ' . e($sup) . '</a>';
                                    case 'receive':
                                        return '<form method="post" action="/factory/boughtin-received.php">' . $h
                                             . '<input type="hidden" name="supplier_name" value="' . e($sup) . '">'
                                             . '<button type="submit" class="' . $cls . '" title="The goods from ' . e($sup) . ' have arrived">Received from ' . e($sup) . '</button></form>';
                                    case 'dispatch':
                                        return '<form method="post" action="/factory/set-status.php">' . $h
                                             . '<button type="submit" name="status" value="dispatched" class="' . $cls . '" title="Everything is made and received">Dispatch</button></form>';
                                    case 'floor':
                                        return '<a class="' . ($cls === 'io-btn advance' ? 'io-btn floor' : $cls) . '" href="/factory/order-areas.php?order=' . $qid . '" title="The floor is making these — nothing to click until they\'re done">View on floor</a>';
                                }
                                return '';
                            };
                            // "Mark made" overrides the floor: say so and finish its blinds
                            // (force_made) rather than letting the order read made while the
                            // floor says 0 of 2.
                            $floorOpen = ($fjNow === 'in_production' && $prog !== null && $prog['total'] > 0 && $prog['done'] < $prog['total'])
                                ? (int) $prog['total'] - (int) $prog['done'] : 0;
                        ?>
                            <?php if ($main !== null): ?><?= $jobHtml($main, $qid, $lane, 'io-btn advance', $backQ) ?><?php endif; ?>
                            <details class="io-more">
                                <summary aria-label="More actions for <?= e($ref) ?>" title="More actions">&middot;&middot;&middot;</summary>
                                <div class="io-menu">
                                    <?php foreach ($jobs as $j): ?><?= $jobHtml($j, $qid, $lane, '', $backQ) ?><?php endforeach; ?>
                                    <?php if ($jobs): ?><hr><?php endif; ?>
                                    <a href="/factory/worksheet-print.php?order=<?= $qid ?>" target="_blank" rel="noopener">Worksheet</a>
                                    <a href="/factory/edit-order.php?order=<?= $qid ?>">Edit order</a>
                                    <?php if ($t['make']['released']): ?><a href="/factory/order-areas.php?order=<?= $qid ?>">View on floor</a><?php endif; ?>
                                    <?php if ($fjNow === 'in_production'): ?>
                                        <form method="post" action="/factory/set-status.php"<?php if ($floorOpen > 0): ?> onsubmit="return confirm(<?= e(json_encode('The floor still has ' . $floorOpen . ' of ' . (int) $prog['total'] . ' blind' . ((int) $prog['total'] === 1 ? '' : 's') . ' to finish. Mark the order made anyway? The remaining blinds will be marked complete on the floor.')) ?>)"<?php endif; ?>>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                            <input type="hidden" name="back" value="<?= e($lane) ?>">
                                            <?php if ($floorOpen > 0): ?><input type="hidden" name="force_made" value="1"><?php endif; ?>
                                            <button type="submit" name="status" value="made">Mark made</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; /* $ioOffice */ ?>
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
                            <tr><th>#</th><th>Product</th><th>Fabric / Colour</th><th class="num">W&times;D (mm)</th><th class="num">Qty</th><th>Room</th><th>Track</th><th>Status</th></tr>
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
                                    <?php $lt = $t['lines'][(int) $ln['id']] ?? null; ?>
                                    <td class="track"><?= $lt === null ? '' : ($lt['track'] === 'make' ? 'MAKE' : 'BUY · ' . e($lt['supplier'])) ?></td>
                                    <td><?= $lt === null ? '' : e($lt['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php if ($prev !== null && $ioOffice): ?>
                        <form method="post" action="/factory/set-status.php" style="margin:0.6rem 0 0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="quote_id" value="<?= $qid ?>">
                            <input type="hidden" name="back" value="<?= e($lane) ?>">
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
        // One "⋯" menu open at a time; a click anywhere else closes it.
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.io-more[open]').forEach(function (d) {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
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
