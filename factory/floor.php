<?php
declare(strict_types=1);

/**
 * Factory · Production Floor (Phase B).
 *
 * One row per physical blind, with its whole route drawn as a chevron strip:
 * green behind it, coloured where it is, grey ahead. Click any stage to move
 * the blind there — the strip is the control, not just a picture.
 *
 * Laid out as a dense table rather than kanban columns so it stays scannable
 * and searchable at a few hundred blinds (the columns drowned).
 *
 * Blinds land here when an order is moved to "in production" on Incoming
 * Orders. Someone working one process scans at /factory/scan.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/due_dates.php';
require __DIR__ . '/../_partials/factory_poll.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();
$ready  = bj_tables_ready($pdo);
$hasDue = dd_ready($pdo);   // due dates are a later migration — degrade quietly

$showMade = isset($_GET['made']);   // completed blinds are hidden by default

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$rows      = [];
$stations  = [];
$liveTot   = 0;
$madeTot   = 0;

if ($ready) {
    // Filter by PROCESS, not bench. The same saw serves verticals, rollers and
    // pleateds, so "Safety Saw" was never a useful thing to filter by — what
    // anyone actually wants is "show me the vertical headrails".
    $stations = $pdo->query(
        "SELECT DISTINCT CONCAT(rs.product_id,'|',COALESCE(NULLIF(rs.stream,''),'main')) AS id,
                CASE WHEN COALESCE(NULLIF(rs.stream,''),'main') = 'main' THEN p.name
                     ELSE CONCAT(p.name,' — ',rs.stream) END AS name
           FROM product_route_steps rs JOIN products p ON p.id = rs.product_id
          WHERE p.client_id = {$MASTER} AND rs.active = 1
          ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $where = $showMade ? '' : "WHERE bj.status IN ('queued','in_progress')";
    // Soonest-due first, undated last — the order a workshop actually works to.
    $dueSel   = $hasDue ? 'q.due_date' : 'NULL AS due_date';
    $dueOrder = $hasDue ? 'q.due_date IS NULL, q.due_date,' : '';
    $st = $pdo->query(
        "SELECT bj.id, bj.quote_id, bj.quote_item_id, bj.unit_no, bj.product_id, bj.status,
                q.quote_number, q.created_at, $dueSel, c.company_name AS tenant,
                qi.line_no, qi.product_name_snapshot, qi.system_name_snapshot,
                qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
                qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name
           FROM factory_blind_jobs bj
           JOIN quotes q       ON q.id = bj.quote_id
           JOIN clients c      ON c.id = q.client_id
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           $where
          ORDER BY $dueOrder q.created_at, q.id, qi.line_no, bj.unit_no
          LIMIT 500"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($st as $r) {
        if ($r['status'] === 'complete') $madeTot++; else $liveTot++;
        $rows[] = $r;
    }
    // Each blind's position in every one of its streams, in one query.
    $streamsBy = bj_streams_for($pdo, array_map(static fn ($r) => (int) $r['id'], $rows));
}

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('j M y'); }
    catch (Throwable $e) { return (string) $ts; }
};

// How a due date reads on an unfinished blind: [class, text].
$today  = new DateTimeImmutable('today');
$dueTag = static function (?string $due, bool $done) use ($today, $fmtDate): array {
    if (!$due) return ['', '—'];
    try { $d = new DateTimeImmutable($due); } catch (Throwable $e) { return ['', (string) $due]; }
    $label = $fmtDate($due);
    if ($done) return ['', $label];                       // made — lateness is moot
    $days = (int) $today->diff($d)->format('%r%a');
    if ($days < 0)  return ['late',  $label . ' · ' . abs($days) . 'd late'];
    if ($days === 0) return ['today', $label . ' · today'];
    if ($days <= 2) return ['soon',  $label];
    return ['', $label];
};

// The floor is a live wallboard — scans out on the shop floor move blinds, and
// this screen swaps the changed rows in on its own (see the script at the foot).
$pollVersion = fx_poll_version($pdo, 'floor', $MASTER);
$RT = '/factory/floor.php' . ($showMade ? '?made=1' : '');

// Live-update fragment: just the rows + counts + version, so the board can
// refresh in place without a reload. Must run before any page chrome is echoed.
if (isset($_GET['rows'])) {
    ob_start();
    if ($ready && $rows) require __DIR__ . '/../_partials/floor_rows.php';
    $rowsHtml = ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['v' => $pollVersion, 'live' => $liveTot, 'made' => $madeTot, 'html' => $rowsHtml]);
    exit;
}

$factoryTitle = 'Production Floor';
$factoryNav   = 'floor';
$factoryWide  = true;   // the stage strip needs the whole monitor, not 1200px
require __DIR__ . '/../_partials/factory_head.php';
require __DIR__ . '/../_partials/blind_styles.php';
?>

<style>
    .fl-live { display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; color:#64748b; }
    .fl-live-dot { width:.55rem; height:.55rem; border-radius:50%; background:#94a3b8; transition:background .2s, box-shadow .2s; }
    .fl-live-dot.on { background:#16a34a; box-shadow:0 0 0 4px rgba(22,163,74,.2); }
</style>
<div class="fl-head">
    <h1 class="fl-h1">Production Floor</h1>
    <?php if ($ready): ?>
        <span class="fl-stat"><b id="fl-live-stat"><?= (int) $liveTot ?></b> in production<?php if ($showMade): ?> &middot; <b><?= (int) $madeTot ?></b> made<?php endif; ?></span>
        <span class="fl-live" title="This board updates itself as blinds are scanned — no need to refresh."><span class="fl-live-dot" id="fl-live-dot"></span> Live</span>
    <?php endif; ?>
</div>
<p class="fl-sub">Every blind in production, one row each &mdash; the board updates itself as scans come in. <strong>Click a stage</strong> to move that blind to it &mdash; green is done, orange is where it is now.</p>

<?php if ($flashOk !== ''): ?><div class="fl-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="fl-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$ready): ?>
    <div class="fl-flash err">Floor tracking isn't set up yet &mdash; run <code>/migrate_factory_blind_jobs.php</code>, then move an order to <em>in production</em> on Incoming Orders.</div>
<?php elseif (!$rows): ?>
    <div class="fl-empty">Nothing on the floor<?= $showMade ? '' : ' right now' ?>. Open <a href="/factory/incoming-orders.php">Incoming Orders</a> and press <strong>Start production</strong> on an order &mdash; its blinds will appear here.
        <?php if (!$showMade): ?><br><a href="/factory/floor.php?made=1">Show made blinds too</a><?php endif; ?>
    </div>
<?php else: ?>

<div class="fl-bar">
    <input type="search" id="fl-search" placeholder="Search job ref, blind, fabric, room, customer&hellip;" autocomplete="off">
    <select id="fl-station">
        <option value="">All processes</option>
        <?php foreach ($stations as $s): ?>
            <?php // id is "<product>|<stream>", not a number — don't cast it. ?>
            <option value="<?= e((string) $s['id']) ?>"><?= e((string) $s['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label><input type="checkbox" id="fl-made" <?= $showMade ? 'checked' : '' ?>> Show made</label>
    <span class="fl-stat" id="fl-shown"></span>
</div>

<div class="fl-tw">
<table class="fl-tbl">
    <thead>
        <tr>
            <th>Job ref</th>
            <th>Progress</th>
            <th>Blind</th>
            <th>Size</th>
            <th>Room</th>
            <th>Due</th>
            <th>Stages &mdash; click to move</th>
        </tr>
    </thead>
    <tbody>
    <?php require __DIR__ . '/../_partials/floor_rows.php'; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<script>
(function () {
    var search  = document.getElementById('fl-search');
    var station = document.getElementById('fl-station');
    var made    = document.getElementById('fl-made');
    var shown   = document.getElementById('fl-shown');
    var tbody   = document.querySelector('.fl-tbl tbody');

    // Search / process filter. Re-reads the rows each pass so it keeps working
    // after the live update swaps fresh ones in.
    function apply() {
        if (!tbody || !search) return;
        var q = (search.value || '').trim().toLowerCase();
        var s = station.value;
        var n = 0;
        [].slice.call(tbody.querySelectorAll('tr')).forEach(function (tr) {
            // A blind needs two processes at once (headrail + fabric), so
            // data-station is a list of "<product>|<stream>".
            var at = (tr.dataset.station || '').split(',');
            var ok = (!q || (tr.dataset.search || '').indexOf(q) !== -1)
                  && (!s || at.indexOf(s) !== -1);
            tr.style.display = ok ? '' : 'none';
            if (ok) n++;
        });
        if (shown) shown.textContent = n + (n === 1 ? ' blind' : ' blinds');
    }
    if (search)  search.addEventListener('input', apply);
    if (station) station.addEventListener('change', apply);
    // "Show made" is a server round-trip — completed blinds aren't loaded otherwise.
    if (made) made.addEventListener('change', function () {
        window.location = '/factory/floor.php' + (made.checked ? '?made=1' : '');
    });
    apply();

    // ---- Live wallboard --------------------------------------------------
    // Scans out on the shop floor move blinds, so instead of nagging for a
    // refresh, fetch just the changed rows and swap them in — no reload, no
    // scroll jump, filters preserved. Hold off while a hand is on the table so
    // the rows never re-shuffle under a click.
    if (!tbody) return;
    var mine = <?= json_encode($pollVersion) ?>;
    var stat = document.getElementById('fl-live-stat');
    var dot  = document.getElementById('fl-live-dot');
    var hovering = false, pending = null;
    tbody.addEventListener('mouseenter', function () { hovering = true; });
    tbody.addEventListener('mouseleave', function () { hovering = false; if (pending) { swap(pending); pending = null; } });

    function swap(j) {
        if (!j.html) { location.reload(); return; }   // floor emptied — show the empty state
        tbody.innerHTML = j.html;
        mine = j.v;
        if (stat) stat.textContent = j.live;
        apply();
        if (dot) { dot.classList.add('on'); setTimeout(function () { dot.classList.remove('on'); }, 700); }
    }
    setInterval(function () {
        if (document.hidden) return;
        // Cheap version check first; only pull the (heavier) fresh rows if it moved.
        fetch('/factory/poll.php?what=floor', { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (pv) {
                if (!pv || !pv.v || pv.v === 'x' || pv.v === mine) return;
                return fetch('/factory/floor.php?rows=1' + (made && made.checked ? '&made=1' : ''), { cache: 'no-store' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) { if (j && j.v) { if (hovering) pending = j; else swap(j); } });
            })
            .catch(function () {});
    }, 8000);
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
