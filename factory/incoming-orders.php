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

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

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

try {
    $oStmt = $pdo->prepare(
        "SELECT q.id, q.client_id, c.company_name AS tenant,
                q.quote_number, q.status, q.created_at,
                q.customer_reference, q.additional_reference,
                q.end_customer_name,
                COUNT(qi.id)                  AS bev_lines,
                COALESCE(SUM(qi.quantity), 0) AS bev_qty
                $fjSelect
           FROM quotes q
           JOIN clients c      ON c.id = q.client_id
           JOIN quote_items qi ON qi.quote_id = q.id
           JOIN products p     ON p.id = qi.product_id
           $fjJoin
          WHERE q.status IN ($inPlaced)
            AND p.source_client_id = ?
       GROUP BY q.id, q.client_id, c.company_name, q.quote_number, q.status,
                q.created_at, q.customer_reference, q.additional_reference,
                q.end_customer_name $fjGroup
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
                AND p.source_client_id = ?
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

$factoryTitle = 'Incoming Orders';
$factoryNav   = 'incoming';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .io-head-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin: 0 0 0.4rem; }
    .io-h1 { font-size: 1.6rem; font-weight: 700; margin: 0; letter-spacing: -0.01em; }
    .io-badge { background: #dcfce7; color: #166534; font-size: 0.8125rem; font-weight: 600; padding: 0.1rem 0.6rem; border-radius: 999px; }
    .io-search { margin-left: auto; font: inherit; padding: 0.45rem 0.75rem; border: 1px solid var(--border, #e5e7eb); border-radius: 8px; min-width: 16rem; background: var(--bg-card, #fff); color: inherit; }
    .io-sub { color: var(--text-muted, #667); margin: 0 0 1.1rem; }
    .io-flash { padding: 0.7rem 1rem; border-radius: 10px; margin: 0 0 1.2rem; font-size: 0.9375rem; }
    .io-flash.ok  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .io-flash.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .io-empty { background: var(--bg-subtle, #f8fafc); border: 1px dashed var(--border, #e5e7eb); border-radius: 12px; padding: 1.75rem; color: var(--text-faint, #94a3b8); text-align: center; }

    /* Compact one-row-per-order list; click a row to open its blinds. */
    .io-list { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; overflow: hidden; background: var(--bg-card, #fff); box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .io-cols { display: grid; grid-template-columns: 8.5rem minmax(7rem, 1fr) 7rem 5.5rem minmax(7rem, auto) minmax(max-content, auto); gap: 0.5rem 1rem; align-items: center; }
    .io-list-head { padding: 0.55rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-faint, #94a3b8); font-weight: 600; border-bottom: 1px solid var(--border, #e5e7eb); background: var(--bg-subtle, #f8fafc); }
    .io-list-head span:last-child { text-align: right; }
    .io-item { border-bottom: 1px solid var(--border, #e5e7eb); }
    .io-item:last-child { border-bottom: none; }
    .io-summary { padding: 0.55rem 1rem; cursor: pointer; }
    .io-summary:hover { background: var(--bg-subtle, #f8fafc); }
    .io-summary:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; }
    .io-summary .ref { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .io-summary .cust { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .io-summary .date, .io-summary .cnt { color: var(--text-muted, #667); font-size: 0.875rem; white-space: nowrap; }
    .io-summary .stat { min-width: 0; }
    .io-summary .io-actions { display: flex; align-items: center; gap: 0.5rem; justify-content: flex-end; }
    .io-chev { color: var(--text-faint, #94a3b8); transition: transform 0.15s ease; display: inline-block; }
    .io-item.open .io-chev { transform: rotate(90deg); }
    .io-item.done .io-summary .ref, .io-item.done .io-summary .cust { opacity: 0.6; }
    .io-status { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.15rem 0.55rem; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
    .io-status.ordered { background: #dcfce7; color: #166534; }
    .io-stage { font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.2rem 0.6rem; border-radius: 999px; white-space: nowrap; }
    .io-btn { font: inherit; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; border-radius: 8px; padding: 0.35rem 0.8rem; }
    .io-btn.advance { background: #1f2a37; color: #fff; }
    .io-btn.advance:hover { background: #111a24; }
    .io-btn.worksheet { background: #2563eb; color: #fff; text-decoration: none; }
    .io-btn.worksheet:hover { background: #1d4ed8; }
    .io-undo { background: none; border: none; color: var(--text-muted, #667); font-size: 0.75rem; text-decoration: underline; cursor: pointer; padding: 0; }

    .io-detail { padding: 0.2rem 1rem 0.9rem; }
    .io-detail .io-refs { color: var(--text-muted, #667); font-size: 0.85rem; margin: 0 0 0.5rem; }
    .io-lines { width: 100%; border-collapse: collapse; }
    .io-lines th { text-align: left; font-size: 0.7rem; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-faint, #94a3b8); font-weight: 600; padding: 0.4rem 0.6rem; border-bottom: 1px solid var(--border, #e5e7eb); }
    .io-lines td { padding: 0.45rem 0.6rem; border-bottom: 1px solid var(--border, #e5e7eb); font-size: 0.9rem; vertical-align: top; }
    .io-lines tr:last-child td { border-bottom: none; }
    .io-lines .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .io-lines .prod { font-weight: 600; }
    @media (max-width: 720px) {
        .io-cols { grid-template-columns: 1fr auto; }
        .io-summary .date, .io-summary .stat, .io-list-head span:nth-child(3), .io-list-head span:nth-child(5) { display: none; }
    }
</style>

<div class="io-head-row">
    <h1 class="io-h1">Incoming Orders</h1>
    <?php if ($newCount > 0): ?><span class="io-badge"><?= (int) $newCount ?> new</span><?php endif; ?>
    <input type="search" id="io-search" class="io-search" placeholder="Search order no, customer, ref&hellip;" autocomplete="off">
</div>
<p class="io-sub">Placed orders from every trade customer that contain Beverley Blinds Trade lines. Click an order to open its blinds.</p>

<?php if ($flashOk !== ''): ?><div class="io-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="io-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if ($loadError !== null): ?>
    <div class="io-empty">Couldn't load orders: <?= e($loadError) ?></div>
<?php elseif (!$orders): ?>
    <div class="io-empty">No incoming orders yet. Placed orders containing Beverley lines will appear here — nothing to re-key from Blind Matrix.</div>
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
            $searchKey = strtolower(trim($ref . ' ' . $tenant . ' ' . $custRef . ' ' . $addRef . ' ' . $endCust));
        ?>
            <div class="io-item<?= $stage === 'dispatched' ? ' done' : '' ?>" data-search="<?= e($searchKey) ?>">
                <div class="io-summary io-cols" role="button" tabindex="0" aria-expanded="false">
                    <span class="ref"><?= e($ref) ?></span>
                    <span class="cust"><?= e($tenant) ?></span>
                    <span class="date"><?= e($fmtDate($o['created_at'] ?? null)) ?></span>
                    <span class="cnt"><?= (int) $o['bev_lines'] ?> blind<?= (int) $o['bev_lines'] === 1 ? '' : 's' ?></span>
                    <span class="stat">
                        <?php if ($stagePill !== null): ?>
                            <span class="io-stage" style="color:<?= e($stagePill[1]) ?>;background:<?= e($stagePill[2]) ?>"><?= e($stagePill[0]) ?></span>
                        <?php else: ?>
                            <span class="io-status <?= $status === 'ordered' ? 'ordered' : '' ?>"><?= e($status !== '' ? $status : 'new') ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="io-actions">
                        <a class="io-btn worksheet" href="/factory/worksheet-print.php?order=<?= $qid ?>" target="_blank" rel="noopener">Worksheet</a>
                        <?php if ($next !== null): ?>
                            <form method="post" action="/factory/set-status.php" style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="quote_id" value="<?= $qid ?>">
                                <button type="submit" name="status" value="<?= e($next[0]) ?>" class="io-btn advance"><?= e($next[1]) ?></button>
                            </form>
                        <?php endif; ?>
                        <span class="io-chev" aria-hidden="true">&#9654;</span>
                    </span>
                </div>
                <div class="io-detail" hidden>
                    <?php if ($custRef !== '' || $addRef !== '' || $endCust !== '' || $stageAt): ?>
                        <p class="io-refs">
                            <?= (int) $o['bev_qty'] ?> unit<?= (int) $o['bev_qty'] === 1 ? '' : 's' ?>
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
