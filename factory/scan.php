<?php
declare(strict_types=1);

/**
 * Factory · Workstation scan.
 *
 * A workstation is a PROCESS, not a bench. John: "the user is not a person its
 * simply 'Vertical Head Rail' ... The benches are just noise as the product has
 * processes rather than benches."
 *
 * So the login covers one or more (product, stream) pairs — "Vertical Head Rail"
 * = the vertical's Headrail stream, profile cut through to assembly, wherever
 * the saw happens to be that day. Scan a blind's label and the stream this
 * workstation owns moves on one stage.
 *
 * The blind's QR identifies the BLIND (line + unit), so it already tells us the
 * product and its streams. The login only has to say which process it does.
 *
 * Ambiguity: if a login covers BOTH of a vertical's streams, a scan can't know
 * which you just finished — so it offers a button per stream. That's the only
 * case that needs a tap; everywhere else is scan-and-go.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/qr.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();
$user   = current_user();
$userId = (int) ($user['user_id'] ?? 0);

$mine = bj_workstation_streams($pdo, $userId);

// Product names for the master products we might mention.
$pname = [];
foreach ($pdo->query("SELECT id, name FROM products WHERE client_id = {$MASTER}")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $pname[(int) $p['id']] = (string) $p['name'];
}
$processName = static function (int $pid, string $stream) use ($pname): string {
    $n = $pname[$pid] ?? ('product ' . $pid);
    return $stream === 'main' ? $n : $n . ' — ' . $stream;
};

/** Advance one stream and describe what happened. */
$advance = function (int $streamId) use ($pdo, $userId): array {
    $row = bj_stream_get($pdo, $streamId);
    if (!$row) return ['ok' => false, 'title' => 'Gone', 'detail' => 'That job no longer exists.'];
    bj_stream_advance($pdo, $streamId, $userId ?: null);
    $after = bj_stream_get($pdo, $streamId);

    $q = $pdo->prepare(
        'SELECT q.quote_number, qi.line_no, qi.quantity, qi.product_name_snapshot,
                qi.width_mm, qi.drop_mm, qi.room_name, bj.unit_no
           FROM factory_blind_jobs bj
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN quotes q       ON q.id = bj.quote_id
          WHERE bj.id = ? LIMIT 1'
    );
    $q->execute([(int) $row['blind_job_id']]);
    $j = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    $ref = ($j['quote_number'] ?? '?') . '-' . (int) ($j['line_no'] ?? 0)
         . '-(' . (int) ($j['unit_no'] ?? 1) . '/' . max(1, (int) ($j['quantity'] ?? 1)) . ')';
    $what = trim((string) ($j['product_name_snapshot'] ?? '') . '  '
          . (int) ($j['width_mm'] ?? 0) . '×' . (int) ($j['drop_mm'] ?? 0)
          . (trim((string) ($j['room_name'] ?? '')) !== '' ? '  ' . $j['room_name'] : ''));

    $next = 'finished — nothing left on this side';
    if ($after && $after['route_step_id'] !== null) {
        $s = $pdo->prepare('SELECT label FROM product_route_steps WHERE id = ?');
        $s->execute([(int) $after['route_step_id']]);
        $next = 'next: ' . ((string) $s->fetchColumn() ?: '?');
    }
    return ['ok' => true, 'title' => $ref, 'detail' => $what . '  ·  ' . $next];
};

/**
 * A scanned code -> either done, an error, or a choice of streams.
 * @return array{ok:bool,title:string,detail:string,choices?:array}
 */
$handleScan = function (string $scanned) use ($pdo, $mine, $processName, $advance): array {
    $parsed = qr_parse_code($scanned);
    if ($parsed === null) {
        return ['ok' => false, 'title' => 'Not a blind label', 'detail' => 'Scanned: ' . mb_substr(trim($scanned), 0, 40)];
    }
    [$itemId, $unitNo] = $parsed;

    $q = $pdo->prepare(
        'SELECT bj.id, bj.status, bj.product_id, qi.product_name_snapshot, qi.width_mm, qi.drop_mm,
                qi.quantity, qi.room_name, qi.line_no, q.quote_number
           FROM factory_blind_jobs bj
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN quotes q       ON q.id = bj.quote_id
          WHERE bj.quote_item_id = ? AND bj.unit_no = ? LIMIT 1'
    );
    $q->execute([$itemId, $unitNo]);
    $job = $q->fetch(PDO::FETCH_ASSOC);
    if (!$job) return ['ok' => false, 'title' => 'Not on the floor', 'detail' => 'That blind hasn\'t been released to production yet.'];

    $ref = (string) $job['quote_number'] . '-' . (int) $job['line_no']
         . '-(' . $unitNo . '/' . max(1, (int) $job['quantity']) . ')';
    $what = trim((string) $job['product_name_snapshot'] . '  '
          . (int) $job['width_mm'] . '×' . (int) $job['drop_mm']
          . (trim((string) $job['room_name']) !== '' ? '  ' . $job['room_name'] : ''));

    if ($job['status'] === 'complete') return ['ok' => false, 'title' => 'Already made', 'detail' => $ref . ' — ' . $what];

    $pid     = (int) $job['product_id'];
    $streams = bj_streams_for($pdo, [(int) $job['id']])[(int) $job['id']] ?? [];

    // Which of this blind's live streams does this workstation actually do?
    $can = [];
    foreach ($streams as $name => $sr) {
        if ($sr['status'] === 'done') continue;
        if (!bj_covers($mine, $pid, (string) $name)) continue;
        $can[$name] = $sr;
    }

    if (!$can) {
        // Not our work — say whose it is, rather than just "no".
        $bits = [];
        foreach ($streams as $name => $sr) {
            $bits[] = $name . ': ' . ($sr['status'] === 'done' ? 'done' : 'still to do');
        }
        return ['ok' => false, 'title' => 'Not this workstation\'s job',
                'detail' => $ref . ' — ' . $what . ($bits ? '  ·  ' . implode('   ', $bits) : '')
                          . '  ·  this login does: ' . implode(', ', array_map(
                                static fn ($w) => $processName((int) $w['product_id'], (string) $w['stream']), $mine))];
    }

    if (count($can) > 1) {
        // Covers both sides of the same blind — we can't know which was finished.
        $choices = [];
        foreach ($can as $name => $sr) {
            $s = $pdo->prepare('SELECT label FROM product_route_steps WHERE id = ?');
            $s->execute([(int) $sr['route_step_id']]);
            $choices[] = ['stream_id' => (int) $sr['id'], 'stream' => (string) $name, 'stage' => (string) ($s->fetchColumn() ?: '?')];
        }
        return ['ok' => false, 'title' => 'Which one?', 'detail' => $ref . ' — ' . $what, 'choices' => $choices];
    }

    return $advance((int) reset($can)['id']);
};

// POST -> handle -> redirect, so a refresh can't replay a scan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['_action'] ?? '') === 'pick' && (int) ($_POST['stream_id'] ?? 0) > 0) {
            $_SESSION['scan_result'] = $advance((int) $_POST['stream_id']);
        } else {
            $code = (string) ($_POST['code'] ?? '');
            if (trim($code) !== '') $_SESSION['scan_result'] = $handleScan($code);
        }
    } catch (Throwable $e) {
        $_SESSION['scan_result'] = ['ok' => false, 'title' => 'Error', 'detail' => $e->getMessage()];
    }
    header('Location: /factory/scan.php');
    exit;
}

$result = $_SESSION['scan_result'] ?? null;
unset($_SESSION['scan_result']);

// This workstation's work: every live stream of every blind whose process we do.
$queue = [];
if ($mine && bj_tables_ready($pdo)) {
    $ors = [];
    $args = [];
    foreach ($mine as $w) { $ors[] = '(bj.product_id = ? AND bs.stream = ?)'; $args[] = (int) $w['product_id']; $args[] = (string) $w['stream']; }
    $sql =
        "SELECT bs.id AS stream_id, bs.stream, bs.status, q.quote_number, qi.line_no, bj.unit_no, qi.quantity,
                qi.product_name_snapshot, qi.width_mm, qi.drop_mm, qi.room_name, q.due_date,
                rs.label AS stage
           FROM factory_blind_streams bs
           JOIN factory_blind_jobs bj ON bj.id = bs.blind_job_id
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN quotes q       ON q.id = bj.quote_id
           LEFT JOIN product_route_steps rs ON rs.id = bs.route_step_id
          WHERE bs.status IN ('queued','in_progress') AND (" . implode(' OR ', $ors) . ")
          ORDER BY q.due_date IS NULL, q.due_date, q.created_at, qi.line_no, bj.unit_no
          LIMIT 60";
    try { $st = $pdo->prepare($sql); $st->execute($args); $queue = $st->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $queue = []; }
}

$fmtDate = static function (?string $d): string {
    if (!$d) return '';
    try { return (new DateTimeImmutable($d))->format('j M'); } catch (Throwable $e) { return (string) $d; }
};

$factoryTitle = 'Scan';
$factoryNav   = 'scan';
$factoryWide  = true;
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .sc-head { display:flex; align-items:baseline; gap:1rem; flex-wrap:wrap; margin:0 0 1rem; }
  .sc-bench { font-size:2rem; font-weight:800; letter-spacing:-.02em; margin:0; }
  .sc-sub { color:var(--text-muted,#667); margin:0; }
  .sc-banner { border-radius:14px; padding:1.4rem 1.6rem; margin:0 0 1.2rem; }
  .sc-banner.ok  { background:#16a34a; color:#fff; }
  .sc-banner.bad { background:#b91c1c; color:#fff; }
  .sc-banner.ask { background:#b45309; color:#fff; }
  .sc-banner .t { font-size:2.4rem; font-weight:800; line-height:1.1; }
  .sc-banner .d { font-size:1.15rem; opacity:.95; margin-top:.35rem; }
  .sc-pick { display:flex; gap:.6rem; margin-top:.9rem; flex-wrap:wrap; }
  .sc-pick button { font:inherit; font-size:1.15rem; font-weight:700; cursor:pointer; border:none; border-radius:10px;
      padding:.7rem 1.2rem; background:#fff; color:#7c2d12; }
  .sc-box { display:flex; align-items:center; gap:.8rem; margin:0 0 1.4rem; }
  .sc-box input { font:inherit; font-size:1.6rem; font-weight:700; letter-spacing:.08em;
      padding:.6rem .9rem; border:3px solid #166534; border-radius:12px; width:16rem; background:var(--bg-card,#fff); color:inherit; }
  .sc-box .hint { color:var(--text-muted,#667); font-size:.95rem; }
  .sc-q { width:100%; border-collapse:collapse; background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; overflow:hidden; }
  .sc-q th { text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); padding:.5rem .7rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); }
  .sc-q td { padding:.5rem .7rem; border-bottom:1px solid var(--border,#eef1f5); font-size:1rem; }
  .sc-q tr:last-child td { border-bottom:none; }
  .sc-ref { font-weight:700; font-variant-numeric:tabular-nums; }
  .sc-stage { background:#eef2ff; color:#3730a3; border-radius:6px; padding:.05rem .45rem; font-size:.85rem; }
  .sc-empty { background:var(--bg-subtle,#f8fafc); border:1px dashed var(--border,#e5e7eb); border-radius:12px; padding:2rem; text-align:center; color:var(--text-faint,#94a3b8); }
  .sc-warn { background:#fef3c7; border:1px solid #fde68a; color:#92400e; border-radius:12px; padding:.9rem 1.1rem; margin:0 0 1.2rem; font-size:1rem; line-height:1.5; }
  .sc-warn strong { display:block; font-size:1.15rem; margin-bottom:.2rem; }
  .sc-warn.caught { background:#b91c1c; border-color:#b91c1c; color:#fff; }
</style>

<?php if (!$mine): ?>
    <!-- No processes assigned: this login cannot accept a scan. Say so, and catch
         a scan landing here rather than swallowing it. -->
    <div style="max-width:44rem">
        <div class="sc-warn" id="sc-warn">
            <strong>Don't scan yet — this login has no processes.</strong>
            A workstation is a process, not a person: “Vertical Head Rail”, “Roller”. Until this login
            is told what it does, a scan has nothing to mark done.
        </div>
        <h1 style="font-size:1.4rem;margin:0 0 .4rem">Assign this login's processes</h1>
        <p style="color:var(--text-muted,#667);margin:.3rem 0">
            <a href="/admin/users.php">Admin &rarr; Users</a> &rarr; this account &rarr; tick the processes it
            covers. Tick as many as you like &mdash; staff move where they're needed, and more than one
            login can cover the same process.
        </p>
    </div>
<?php else: ?>

    <div class="sc-head">
        <h1 class="sc-bench"><?= e(implode(' · ', array_map(static fn ($w) => $processName((int) $w['product_id'], (string) $w['stream']), $mine))) ?></h1>
        <p class="sc-sub"><?= count($queue) ?> waiting</p>
    </div>

    <?php if ($result !== null): ?>
        <div class="sc-banner <?= !empty($result['choices']) ? 'ask' : ($result['ok'] ? 'ok' : 'bad') ?>">
            <div class="t"><?= !empty($result['choices']) ? '' : ($result['ok'] ? '✓ ' : '✕ ') ?><?= e((string) $result['title']) ?></div>
            <div class="d"><?= e((string) $result['detail']) ?></div>
            <?php if (!empty($result['choices'])): ?>
                <form method="post" class="sc-pick">
                    <?= csrf_field() ?><input type="hidden" name="_action" value="pick">
                    <?php foreach ($result['choices'] as $c): ?>
                        <button name="stream_id" value="<?= (int) $c['stream_id'] ?>">
                            <?= e((string) $c['stream']) ?> &mdash; <?= e((string) $c['stage']) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="sc-box" id="sc-form">
        <?= csrf_field() ?>
        <input type="text" name="code" id="sc-code" autocomplete="off" autofocus placeholder="scan a label">
        <span class="hint">Scan a blind to move it on one stage.</span>
    </form>

    <?php if (!$queue): ?>
        <div class="sc-empty">Nothing waiting.</div>
    <?php else: ?>
        <table class="sc-q">
            <thead><tr><th>Job ref</th><th>Blind</th><th>Size</th><th>Room</th><th>Doing</th><th>Due</th></tr></thead>
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
                    <td><span class="sc-stage"><?= e((string) ($r['stage'] ?? '')) ?></span></td>
                    <td><?= e($fmtDate($r['due_date'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php endif; ?>

<script>
(function () {
    // No processes assigned: catch a scan and say why it did nothing, rather
    // than letting a wedge scanner type into the void.
    var warn = document.getElementById('sc-warn');
    if (warn) {
        var buf = '', last = 0;
        document.addEventListener('keydown', function (e) {
            var now = Date.now();
            if (now - last > 120) buf = '';
            last = now;
            if (e.key && e.key.length === 1) { buf += e.key; return; }
            if (e.key === 'Enter' && buf.length >= 4) {
                var code = buf; buf = '';
                warn.className = 'sc-warn caught';
                warn.innerHTML = '<strong>That scan did nothing — ' + code.replace(/[<>&]/g, '') + '</strong>'
                    + 'This login has no processes assigned, so there\'s nothing for it to mark done.';
            }
        });
        return;
    }

    var box = document.getElementById('sc-code'), form = document.getElementById('sc-form');
    if (!box || !form) return;

    // The scanner is a keyboard: if focus wanders, the scan types into nothing.
    setInterval(function () { if (document.activeElement !== box) box.focus(); }, 700);
    document.addEventListener('click', function (e) { if (!e.target.closest('.sc-pick')) box.focus(); });

    var sent = false;
    function go() {
        if (sent) return;
        if (!/^\d{8,9}$/.test(box.value.trim())) return;   // only a real code
        sent = true;
        form.submit();
    }

    // DON'T depend on the scanner's Enter. A terminator is a setting, and a
    // setting is something that can be off, or knocked off while someone's
    // hunting through the manual for something else — and when it is, the code
    // lands in the box, nothing happens, and it looks like the system is
    // broken. A wedge scanner types far faster than a human, so a complete code
    // followed by a moment's silence IS the end of a scan. Submit on that, and
    // the terminator setting stops mattering.
    var idle = null;
    box.addEventListener('input', function () {
        clearTimeout(idle);
        idle = setTimeout(go, 120);          // ~10x slower than the scanner, ~2x faster than typing
    });

    // Enter still works, whether the scanner sends one or two. LF & CR sends it
    // TWICE — submitting on the raw event would fire the form twice and advance
    // the blind two stages, so the guard above matters either way.
    form.addEventListener('submit', function (e) {
        if (sent || box.value.trim() === '') { e.preventDefault(); return; }
        sent = true;
    });
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
