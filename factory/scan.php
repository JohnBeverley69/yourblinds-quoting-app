<?php
declare(strict_types=1);

/**
 * Factory · Bench scan.
 *
 * The point of the whole exercise: a PC at each bench, logged in as that bench,
 * with a wedge scanner. Scan a blind's label -> that bench's stage is done ->
 * it moves to the next stage on its route. No mouse, no keyboard, no hunting a
 * row on a screen with a 2.7m blind under your arm.
 *
 * Which bench this is comes from the LOGIN (client_users.factory_station_id),
 * not from the code — that's what lets us reject a blind scanned at the wrong
 * bench instead of silently moving it. The super-admin can pick a bench with
 * ?station_id= to test without a station login.
 *
 * The result banner is deliberately enormous: with a wireless scanner you may be
 * several metres from the screen, and the scanner's own beep only means "I read
 * something", not "the system accepted it".
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/qr.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

// The bench: from the login first, else an explicit pick (super-admin testing).
$stationId = current_user_station_id();
$picked    = (int) ($_GET['station_id'] ?? 0);
if ($stationId === null && $picked > 0 && is_super_admin()) $stationId = $picked;

$station = null;
if ($stationId !== null) {
    $s = $pdo->prepare('SELECT id, name FROM factory_stations WHERE id = ? AND client_id = ? LIMIT 1');
    $s->execute([$stationId, $MASTER]);
    $station = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$station) $stationId = null;
}

/**
 * Take one scanned code and try to move that blind on at THIS bench.
 * @return array{ok:bool,title:string,detail:string}
 */
$handleScan = function (string $scanned) use ($pdo, $stationId, $station): array {
    $parsed = qr_parse_code($scanned);
    if ($parsed === null) {
        return ['ok' => false, 'title' => 'Not a blind label',
                'detail' => 'Scanned: ' . mb_substr(trim($scanned), 0, 40)];
    }
    [$itemId, $unitNo] = $parsed;

    $q = $pdo->prepare(
        'SELECT bj.id, bj.status, qi.product_name_snapshot, qi.width_mm, qi.drop_mm,
                qi.quantity, qi.room_name, qi.line_no, q.quote_number
           FROM factory_blind_jobs bj
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN quotes q       ON q.id = bj.quote_id
          WHERE bj.quote_item_id = ? AND bj.unit_no = ? LIMIT 1'
    );
    $q->execute([$itemId, $unitNo]);
    $job = $q->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        return ['ok' => false, 'title' => 'Not on the floor',
                'detail' => 'That blind hasn\'t been released to production yet.'];
    }

    $ref = (string) $job['quote_number'] . '-' . (int) $job['line_no']
         . '-(' . $unitNo . '/' . max(1, (int) $job['quantity']) . ')';
    $what = trim((string) $job['product_name_snapshot'] . '  '
          . (int) $job['width_mm'] . '×' . (int) $job['drop_mm']
          . ((string) $job['room_name'] !== '' ? '  ' . $job['room_name'] : ''));

    if ($job['status'] === 'complete') {
        return ['ok' => false, 'title' => 'Already made', 'detail' => $ref . ' — ' . $what];
    }

    // Which of this blind's streams is sitting at THIS bench?
    $streams = bj_streams_for($pdo, [(int) $job['id']])[(int) $job['id']] ?? [];
    $mine = null;
    foreach ($streams as $sr) {
        if ((int) ($sr['station_id'] ?? 0) === (int) $stationId
            && in_array($sr['status'], ['queued', 'in_progress'], true)) { $mine = $sr; break; }
    }

    if (!$mine) {
        // Say where it actually is — "wrong bench" is useless without "it's at the saw".
        $where = [];
        foreach ($streams as $name => $sr) {
            if ($sr['status'] === 'done') { $where[] = $name . ': done'; continue; }
            $st = $pdo->prepare('SELECT s.name FROM factory_stations s WHERE s.id = ?');
            $st->execute([(int) $sr['station_id']]);
            $where[] = $name . ': ' . ((string) $st->fetchColumn() ?: 'no route');
        }
        return ['ok' => false, 'title' => 'Not at this bench',
                'detail' => $ref . ' — ' . $what . ($where ? '  ·  ' . implode('   ', $where) : '')];
    }

    bj_stream_advance($pdo, (int) $mine['id'], (int) (current_user()['user_id'] ?? 0) ?: null);

    // Where has it gone?
    $after = bj_stream_get($pdo, (int) $mine['id']);
    $next  = 'finished';
    if ($after && $after['station_id'] !== null) {
        $st = $pdo->prepare('SELECT name FROM factory_stations WHERE id = ?');
        $st->execute([(int) $after['station_id']]);
        $next = 'next: ' . ((string) $st->fetchColumn() ?: '?');
    }
    return ['ok' => true, 'title' => $ref, 'detail' => $what . '  ·  ' . $next];
};

// POST -> handle -> redirect, so a refresh can't replay a scan.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stationId !== null) {
    csrf_check();
    $code = (string) ($_POST['code'] ?? '');
    if (trim($code) !== '') {
        try { $_SESSION['scan_result'] = $handleScan($code); }
        catch (Throwable $e) { $_SESSION['scan_result'] = ['ok' => false, 'title' => 'Error', 'detail' => $e->getMessage()]; }
    }
    header('Location: /factory/scan.php' . ($picked > 0 ? '?station_id=' . $picked : ''));
    exit;
}

$result = $_SESSION['scan_result'] ?? null;
unset($_SESSION['scan_result']);

// This bench's queue, so there's something to look at between scans.
$queue = [];
if ($stationId !== null && bj_tables_ready($pdo)) {
    $q = $pdo->prepare(
        "SELECT bs.stream, q.quote_number, qi.line_no, bs.blind_job_id, bj.unit_no, qi.quantity,
                qi.product_name_snapshot, qi.width_mm, qi.drop_mm, qi.room_name, rs.label AS step_label
           FROM factory_blind_streams bs
           JOIN factory_blind_jobs bj ON bj.id = bs.blind_job_id
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN quotes q       ON q.id = bj.quote_id
           LEFT JOIN product_route_steps rs ON rs.id = bs.route_step_id
          WHERE bs.station_id = ? AND bs.status IN ('queued','in_progress')
          ORDER BY q.due_date IS NULL, q.due_date, q.created_at, qi.line_no, bj.unit_no
          LIMIT 40"
    );
    try { $q->execute([$stationId]); $queue = $q->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $queue = []; }
}

$stations = is_super_admin() && $stationId === null
    ? $pdo->query("SELECT id, name FROM factory_stations WHERE client_id = {$MASTER} AND active = 1 ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$factoryTitle = $station ? 'Scan · ' . $station['name'] : 'Scan';
$factoryNav   = 'scan';
$factoryWide  = true;
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .sc-head { display:flex; align-items:baseline; gap:1rem; flex-wrap:wrap; margin:0 0 1rem; }
  .sc-bench { font-size:2rem; font-weight:800; letter-spacing:-.02em; margin:0; }
  .sc-sub { color:var(--text-muted,#667); margin:0; }
  /* Readable from across a workshop — with a wireless scanner you're not at the screen. */
  .sc-banner { border-radius:14px; padding:1.4rem 1.6rem; margin:0 0 1.2rem; }
  .sc-banner.ok  { background:#16a34a; color:#fff; }
  .sc-banner.bad { background:#b91c1c; color:#fff; }
  .sc-banner .t { font-size:2.4rem; font-weight:800; line-height:1.1; }
  .sc-banner .d { font-size:1.15rem; opacity:.95; margin-top:.35rem; }
  .sc-box { display:flex; align-items:center; gap:.8rem; margin:0 0 1.4rem; }
  .sc-box input { font:inherit; font-size:1.6rem; font-weight:700; letter-spacing:.08em;
      padding:.6rem .9rem; border:3px solid #166534; border-radius:12px; width:16rem; background:var(--bg-card,#fff); color:inherit; }
  .sc-box .hint { color:var(--text-muted,#667); font-size:.95rem; }
  .sc-q { width:100%; border-collapse:collapse; background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; overflow:hidden; }
  .sc-q th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); padding:.5rem .7rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); }
  .sc-q td { padding:.5rem .7rem; border-bottom:1px solid var(--border,#eef1f5); font-size:1rem; }
  .sc-q tr:last-child td { border-bottom:none; }
  .sc-ref { font-weight:700; font-variant-numeric:tabular-nums; }
  .sc-empty { background:var(--bg-subtle,#f8fafc); border:1px dashed var(--border,#e5e7eb); border-radius:12px; padding:2rem; text-align:center; color:var(--text-faint,#94a3b8); }

  .sc-nobench { max-width:44rem; }
  .sc-nobench h1 { font-size:1.4rem; margin:0 0 .4rem; }
  .sc-nobench p { color:var(--text-muted,#667); margin:.35rem 0; line-height:1.5; }
  .sc-warn { background:#fef3c7; border:1px solid #fde68a; color:#92400e; border-radius:12px; padding:.9rem 1.1rem; margin:0 0 1.2rem; font-size:1rem; line-height:1.5; }
  .sc-warn strong { display:block; font-size:1.15rem; margin-bottom:.2rem; }
  .sc-warn.caught { background:#b91c1c; border-color:#b91c1c; color:#fff; }
  .sc-warn.caught strong { font-size:1.4rem; }
  .sc-benches { display:flex; flex-wrap:wrap; gap:.5rem; margin:.2rem 0 0; }
  .sc-benches a { display:inline-block; padding:.55rem 1rem; background:#1f2a37; color:#fff; border-radius:10px;
      text-decoration:none; font-weight:600; font-size:1rem; }
  .sc-benches a:hover { background:#111a24; }
</style>

<?php if ($stationId === null): ?>
    <!-- This page CANNOT take a scan: without a bench there's nothing to mark
         done. Say so loudly and catch a scan that lands here, rather than
         swallowing it silently and leaving someone thinking the system's broken. -->
    <div class="sc-nobench">
        <div class="sc-warn" id="sc-warn">
            <strong>Don't scan yet — pick your bench below first.</strong>
            This page can't take a scan: until it knows which bench you're at, it can't tell
            what to mark done.
        </div>

        <h1>Which bench is this?</h1>
        <p>A bench login <em>is</em> a station — the PC stays signed in as “Safety Saw” and
           whoever's stood at it uses it. Set the <strong>Bench</strong> on the account in
           <a href="/admin/users.php">Admin &rarr; Users</a> and it'll come straight here, and this
           screen will never appear again.</p>
        <?php if ($stations): ?>
            <p style="margin:.2rem 0 .6rem">Meanwhile, pick one — this PC will remember it:</p>
            <div class="sc-benches">
                <?php foreach ($stations as $s): ?>
                    <a href="/factory/scan.php?station_id=<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>

    <div class="sc-head">
        <h1 class="sc-bench"><?= e((string) $station['name']) ?></h1>
        <p class="sc-sub"><?= count($queue) ?> waiting<?php if ($picked > 0): ?>
            &middot; not a bench login &mdash; <a href="#" id="sc-change">change bench</a><?php endif; ?></p>
    </div>

    <?php if ($result !== null): ?>
        <div class="sc-banner <?= $result['ok'] ? 'ok' : 'bad' ?>">
            <div class="t"><?= $result['ok'] ? '✓ ' : '✕ ' ?><?= e((string) $result['title']) ?></div>
            <div class="d"><?= e((string) $result['detail']) ?></div>
        </div>
    <?php endif; ?>

    <form method="post" class="sc-box" id="sc-form">
        <?= csrf_field() ?>
        <input type="text" name="code" id="sc-code" autocomplete="off" autofocus placeholder="scan a label">
        <span class="hint">Scan a blind's label to mark <strong><?= e((string) $station['name']) ?></strong> done. It'll move to the next stage on its route.</span>
    </form>

    <?php if (!$queue): ?>
        <div class="sc-empty">Nothing waiting at this bench.</div>
    <?php else: ?>
        <table class="sc-q">
            <thead><tr><th>Job ref</th><th>Blind</th><th>Size</th><th>Room</th><th>Doing</th></tr></thead>
            <tbody>
            <?php foreach ($queue as $r):
                $qty = max(1, (int) $r['quantity']);
                $ref = (string) $r['quote_number'] . '-' . (int) $r['line_no'] . '-(' . (int) $r['unit_no'] . '/' . $qty . ')';
            ?>
                <tr>
                    <td class="sc-ref"><?= e($ref) ?></td>
                    <td><?= e((string) $r['product_name_snapshot']) ?></td>
                    <td><?= (int) $r['width_mm'] ?> &times; <?= (int) $r['drop_mm'] ?></td>
                    <td><?= e((string) $r['room_name']) ?></td>
                    <td><?= e((string) ($r['step_label'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>

<script>
(function () {
    // ---- The bench picker ------------------------------------------------
    // Nothing here can accept a scan, so catch one landing on this page and say
    // why rather than swallowing it. A wedge scanner is just a keyboard: without
    // this, the characters go nowhere and it looks like the system is broken.
    var warn = document.getElementById('sc-warn');
    if (warn) {
        var buf = '', last = 0;
        document.addEventListener('keydown', function (e) {
            var now = Date.now();
            if (now - last > 120) buf = '';        // a human typing, not a scanner burst
            last = now;
            if (e.key && e.key.length === 1) { buf += e.key; return; }
            if (e.key === 'Enter' && buf.length >= 4) {
                var code = buf; buf = '';
                warn.className = 'sc-warn caught';
                warn.innerHTML = '<strong>That scan did nothing — ' + code.replace(/[<>&]/g, '') + '</strong>'
                    + 'This page isn\'t a bench, so there\'s nothing to mark done. Pick your bench below, '
                    + 'then scan again.';
                warn.scrollIntoView({ block: 'center' });
            }
        });
        // Remember the bench per PC, so this screen is a one-off even without a
        // bench login. "change bench" on the scan page clears it.
        document.querySelectorAll('.sc-benches a').forEach(function (a) {
            a.addEventListener('click', function () {
                try { localStorage.setItem('factoryBench', a.href.match(/station_id=(\d+)/)[1]); } catch (e) {}
            });
        });
        try {
            var remembered = localStorage.getItem('factoryBench');
            if (remembered && !/no_auto/.test(location.search)) location.replace('/factory/scan.php?station_id=' + remembered);
        } catch (e) {}
        return;
    }

    // ---- The bench itself -------------------------------------------------
    var change = document.getElementById('sc-change');
    if (change) change.addEventListener('click', function (e) {
        e.preventDefault();
        try { localStorage.removeItem('factoryBench'); } catch (err) {}
        location.href = '/factory/scan.php?no_auto=1';
    });

    var box = document.getElementById('sc-code'), form = document.getElementById('sc-form');
    if (!box || !form) return;

    // The scanner is a keyboard: if focus wanders, the scan is typed into nothing.
    setInterval(function () { if (document.activeElement !== box) box.focus(); }, 700);
    document.addEventListener('click', function () { box.focus(); });

    // The D5100's LF & CR terminator sends Enter TWICE. Submitting on the raw
    // Enter would fire the form twice and advance the blind two stages. So
    // submit only when there's actually something in the box, and lock straight
    // away so the second Enter lands on a spent form.
    var sent = false;
    form.addEventListener('submit', function (e) {
        if (sent || box.value.trim() === '') { e.preventDefault(); return; }
        sent = true;
    });
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
