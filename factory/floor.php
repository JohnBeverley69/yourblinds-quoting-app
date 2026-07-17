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

// Blinds move when someone scans at a bench, so this screen goes stale on its
// own. See the poll at the bottom.
$pollVersion = fx_poll_version($pdo, 'floor', $MASTER);

$factoryTitle = 'Production Floor';
$factoryNav   = 'floor';
$factoryWide  = true;   // the stage strip needs the whole monitor, not 1200px
require __DIR__ . '/../_partials/factory_head.php';
require __DIR__ . '/../_partials/blind_styles.php';
$RT = '/factory/floor.php' . ($showMade ? '?made=1' : '');
?>

<div class="io-news" id="fl-news" hidden style="position:sticky;top:56px;z-index:15;display:flex;align-items:center;gap:.9rem;background:#166534;color:#fff;padding:.7rem 1.1rem;border-radius:10px;margin:0 0 1rem;font-size:.95rem;font-weight:600;box-shadow:0 2px 10px rgba(0,0,0,.15)">
    <span>The floor has moved &mdash; someone's scanned since this loaded.</span>
    <button type="button" id="fl-news-btn" style="font:inherit;font-weight:700;cursor:pointer;border:none;border-radius:8px;padding:.35rem .9rem;background:#fff;color:#166534">Refresh</button>
</div>

<div class="fl-head">
    <h1 class="fl-h1">Production Floor</h1>
    <?php if ($ready): ?>
        <span class="fl-stat"><b><?= (int) $liveTot ?></b> in production<?php if ($showMade): ?> &middot; <b><?= (int) $madeTot ?></b> made<?php endif; ?></span>
    <?php endif; ?>
</div>
<p class="fl-sub">Every blind in production, one row each. <strong>Click a stage</strong> to move that blind to it &mdash; green is done, orange is where it is now.</p>

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
    <?php foreach ($rows as $r):
        $jobId   = (int) $r['id'];
        $qty     = max(1, (int) $r['quantity']);
        $unit    = (int) $r['unit_no'];
        $done    = $r['status'] === 'complete';
        $working = $r['status'] === 'in_progress';
        // Blind Matrix-style ref: order-line-(unit/qty).
        $ref = (string) $r['quote_number'] . '-' . (int) $r['line_no'] . '-(' . $unit . '/' . $qty . ')';

        // A vertical has two streams (headrail + fabric) that run alongside each
        // other; roller and pleated have one. Each keeps its own position.
        $byStream   = bj_route_by_stream($pdo, (int) $r['product_id']);
        $myStreams  = $streamsBy[$jobId] ?? [];
        [$doneCount, $total] = bj_progress($byStream, $myStreams);
        $pct = $total > 0 ? (int) round($doneCount / $total * 100) : 0;
        // Which processes this blind still needs, for the filter. A vertical with
        // its fabric finished but headrail outstanding should show under
        // "Vertical — Headrail" and not under "Vertical — Fabric".
        $atStations = [];
        foreach ($myStreams as $sname => $sr) {
            if ($sr['status'] !== 'done') $atStations[] = (int) $r['product_id'] . '|' . $sname;
        }

        $fab = trim((string) $r['fabric_name_snapshot']);
        $col = trim((string) $r['fabric_colour_snapshot']);
        $sys = trim((string) $r['system_name_snapshot']);
        $searchKey = strtolower(trim($ref . ' ' . $r['product_name_snapshot'] . ' ' . $sys . ' ' . $fab . ' ' . $col . ' ' . $r['room_name'] . ' ' . $r['tenant']));
    ?>
        <tr class="<?= $done ? 'is-made' : '' ?>" data-search="<?= e($searchKey) ?>" data-station="<?= e(implode(',', $atStations)) ?>" data-made="<?= $done ? 1 : 0 ?>">
            <td>
                <a class="fl-ref" href="/factory/worksheet-print.php?order=<?= (int) $r['quote_id'] ?>" target="_blank" rel="noopener"><?= e($ref) ?></a>
                <span class="fl-tenant"><?= e((string) $r['tenant']) ?></span>
            </td>
            <td>
                <div class="fl-prog" title="<?= $doneCount ?> of <?= $total ?> stages done">
                    <div class="fl-prog-track"><div class="fl-prog-fill<?= $pct >= 100 ? ' full' : '' ?>" style="width:<?= $pct ?>%"></div></div>
                    <span class="fl-prog-pct"><?= $total > 0 ? $pct . '%' : '—' ?></span>
                </div>
            </td>
            <td>
                <span class="fl-blind"><?= e((string) $r['product_name_snapshot']) ?><?php if ($sys !== ''): ?> <span><?= e($sys) ?></span><?php endif; ?></span>
                <?php if ($fab !== '' || $col !== ''): ?><span class="fl-fab"><?= e(trim($fab . ($col !== '' ? ' / ' . $col : ''))) ?></span><?php endif; ?>
            </td>
            <td class="fl-size"><?= (int) $r['width_mm'] ?> &times; <?= (int) $r['drop_mm'] ?></td>
            <td><?= e((string) $r['room_name']) ?></td>
            <?php [$dueCls, $dueTxt] = $dueTag($r['due_date'] ?? null, $done); ?>
            <td class="fl-date fl-due <?= $dueCls ?>" title="Ordered <?= e($fmtDate($r['created_at'] ?? null)) ?>"><?= e($dueTxt) ?></td>
            <td>
                <?php if ($total === 0): ?>
                    <span class="pill out">no route</span> &mdash; set one on <a href="/factory/routes.php">Routes</a>
                <?php else: $multi = count($byStream) > 1; ?>
                    <?php foreach ($byStream as $stream => $list):
                        $sr  = $myStreams[$stream] ?? null;
                        if (!$sr) continue;
                        $sDone = $sr['status'] === 'done' || $sr['route_step_id'] === null;
                        $sWork = $sr['status'] === 'in_progress';
                        $idx = null;
                        foreach ($list as $i => $s) {
                            if ((int) $s['id'] === (int) $sr['route_step_id']) { $idx = $i; break; }
                        }
                        $sDoneCount = $sDone ? count($list) : ($idx ?? 0);
                    ?>
                        <div class="fl-streamline">
                            <?php if ($multi): ?><span class="fl-streamname"><?= e((string) $stream) ?></span><?php endif; ?>
                            <form method="post" action="/factory/blind-action.php" class="fl-strip">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_stage">
                                <input type="hidden" name="stream_id" value="<?= (int) $sr['id'] ?>">
                                <input type="hidden" name="return_to" value="<?= e($RT) ?>">
                                <?php foreach ($list as $i => $s):
                                    $cls = 'stg';
                                    if ($i < $sDoneCount)             $cls .= ' done';
                                    elseif (!$sDone && $i === $idx)   $cls .= $sWork ? ' working' : ' current';
                                    $tip = (string) ($s['label'] ?? '');
                                ?>
                                    <button type="submit" name="step_id" value="<?= (int) $s['id'] ?>" class="<?= $cls ?>" title="<?= e($tip) ?>"><?= e((string) ($s['label'] ?? '')) ?></button>
                                <?php endforeach; ?>
                                <button type="submit" name="step_id" value="done" class="stg made<?= $sDone ? ' done' : '' ?>" title="<?= $multi ? e($stream . ' finished') : 'Finished — off the floor' ?>"><?= $multi ? 'Done' : 'Made' ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<script>
// Blinds move when a bench scans one, so this screen goes stale on its own.
// OFFER a refresh rather than taking one: every stage chip here is a button
// that moves a blind, and a page that reloads under a hand lands the click on
// the wrong one.
(function () {
    var mine = <?= json_encode($pollVersion) ?>;
    var news = document.getElementById('fl-news');
    var btn  = document.getElementById('fl-news-btn');
    if (!news || !btn) return;
    btn.addEventListener('click', function () { location.reload(); });
    setInterval(function () {
        if (document.hidden) return;
        fetch('/factory/poll.php?what=floor', { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.v && j.v !== 'x' && j.v !== mine) news.hidden = false; })
            .catch(function () {});
    }, 20000);
})();

(function () {
    var search  = document.getElementById('fl-search');
    var station = document.getElementById('fl-station');
    var made    = document.getElementById('fl-made');
    var shown   = document.getElementById('fl-shown');
    var rows    = [].slice.call(document.querySelectorAll('.fl-tbl tbody tr'));
    if (!rows.length) return;

    function apply() {
        var q = (search.value || '').trim().toLowerCase();
        var s = station.value;
        var n = 0;
        rows.forEach(function (tr) {
            // A blind needs two processes at once (headrail + fabric), so
            // data-station is a list of "<product>|<stream>".
            var at = (tr.dataset.station || '').split(',');
            var ok = (!q || (tr.dataset.search || '').indexOf(q) !== -1)
                  && (!s || at.indexOf(s) !== -1);
            tr.style.display = ok ? '' : 'none';
            if (ok) n++;
        });
        shown.textContent = n + (n === 1 ? ' blind' : ' blinds');
    }
    search.addEventListener('input', apply);
    station.addEventListener('change', apply);
    // "Show made" is a server round-trip — completed blinds aren't loaded otherwise.
    made.addEventListener('change', function () {
        window.location = '/factory/floor.php' + (made.checked ? '?made=1' : '');
    });
    apply();
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
