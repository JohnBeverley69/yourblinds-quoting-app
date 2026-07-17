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

// A scan must NOT reload the page. The workshop scans back to back, and a full
// reload leaves ~half a second where the page is being torn down — a scan in
// that window types into a dying document and is silently lost. So the browser
// posts this in the background and we hand back JSON; the page never goes away
// and the box is ready for the next trigger pull immediately.
//
// The plain (non-ajax) path is kept for no-JS and for the stream-choice buttons:
// POST -> redirect -> GET, so a refresh can't replay a scan.
$isAjax = !empty($_POST['_ajax']);
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['_action'] ?? '') === 'pick' && (int) ($_POST['stream_id'] ?? 0) > 0) {
            $result = $advance((int) $_POST['stream_id']);
        } else {
            $code = (string) ($_POST['code'] ?? '');
            if (trim($code) !== '') $result = $handleScan($code);
        }
    } catch (Throwable $e) {
        $result = ['ok' => false, 'title' => 'Error', 'detail' => $e->getMessage()];
    }
    if (!$isAjax) {
        if ($result !== null) $_SESSION['scan_result'] = $result;
        header('Location: /factory/scan.php');
        exit;
    }
}

if ($result === null) {
    $result = $_SESSION['scan_result'] ?? null;
    unset($_SESSION['scan_result']);
}

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

/** One queue row as the page (and the JSON) present it. */
$rowOf = static function (array $r) use ($fmtDate): array {
    $qty = max(1, (int) $r['quantity']);
    return [
        'ref'   => (string) $r['quote_number'] . '-' . (int) $r['line_no'] . '-(' . (int) $r['unit_no'] . '/' . $qty . ')',
        'blind' => (string) $r['product_name_snapshot'],
        'size'  => (int) $r['width_mm'] . ' × ' . (int) $r['drop_mm'],
        'room'  => (string) $r['room_name'],
        'stage' => (string) ($r['stage'] ?? ''),
        'due'   => $fmtDate($r['due_date'] ?? null),
    ];
};

// Background scan: hand back the outcome and the refreshed queue, and let the
// page update itself in place. No reload, so no window in which a scan is lost.
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['result' => $result, 'rows' => array_map($rowOf, $queue)]);
    exit;
}

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
  /* Amber, sticky under the top bar, impossible to miss — it means every scan
     is currently being lost. */
  .sc-focus { position:sticky; top:56px; z-index:16; background:#b45309; color:#fff; border-radius:12px;
      padding:.9rem 1.2rem; margin:0 0 1rem; font-size:1.05rem; line-height:1.45; box-shadow:0 2px 12px rgba(0,0,0,.2); cursor:pointer; }
  .sc-focus strong { display:block; font-size:1.25rem; }
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
        <p class="sc-sub" id="sc-count"><?= count($queue) ?> waiting</p>
    </div>

    <div class="sc-banner <?= !empty($result['choices']) ? 'ask' : (($result && $result['ok']) ? 'ok' : 'bad') ?>"
         id="sc-banner" <?= $result === null ? 'hidden' : '' ?>>
        <?php if ($result !== null): ?>
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
        <?php endif; ?>
    </div>

    <!-- Shown whenever the browser window isn't the active window. A wedge
         scanner types into whatever window Windows has in front, so if this one
         isn't it, every scan lands somewhere else and vanishes — and the page
         looks like it's just ignoring you. Better to say so, loudly. -->
    <div class="sc-focus" id="sc-focus" hidden>
        <strong>This window isn't listening.</strong>
        Click anywhere on this page before scanning &mdash; your scans are going to another window.
    </div>

    <form method="post" class="sc-box" id="sc-form">
        <?= csrf_field() ?>
        <input type="text" name="code" id="sc-code" autocomplete="off" autofocus placeholder="scan a label">
        <span class="hint">Scan a blind to move it on one stage.</span>
    </form>

    <div class="sc-empty" id="sc-qempty" <?= $queue ? 'hidden' : '' ?>>Nothing waiting.</div>
    <table class="sc-q" id="sc-qwrap" <?= $queue ? '' : 'hidden' ?>>
        <thead><tr><th>Job ref</th><th>Blind</th><th>Size</th><th>Room</th><th>Doing</th><th>Due</th></tr></thead>
        <tbody id="sc-qbody">
        <?php foreach ($queue as $r): $row = $rowOf($r); ?>
            <tr>
                <td class="sc-ref"><?= e($row['ref']) ?></td>
                <td><?= e($row['blind']) ?></td>
                <td><?= e($row['size']) ?></td>
                <td><?= e($row['room']) ?></td>
                <td><span class="sc-stage"><?= e($row['stage']) ?></span></td>
                <td><?= e($row['due']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

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
    var TOKEN = <?= json_encode(csrf_token()) ?>;

    // The scanner is a keyboard: if focus wanders, the scan types into nothing.
    setInterval(function () { if (document.activeElement !== box) box.focus(); }, 700);
    document.addEventListener('click', function (e) { if (!e.target.closest('.sc-pick')) box.focus(); });

    // A wedge scanner types into whatever WINDOW Windows has in front. If that
    // isn't this one, the box having focus counts for nothing — the scan lands
    // in another app and is gone. Warn plainly while the window is unfocused,
    // because otherwise the page just looks like it's ignoring the scanner.
    var focusWarn = document.getElementById('sc-focus');
    function reflectWindowFocus() {
        var listening = document.hasFocus();
        if (focusWarn) focusWarn.hidden = listening;
        box.style.opacity = listening ? '' : '0.4';
        if (listening) box.focus();
    }
    window.addEventListener('focus', reflectWindowFocus);
    window.addEventListener('blur',  reflectWindowFocus);
    if (focusWarn) focusWarn.addEventListener('click', function () { window.focus(); box.focus(); reflectWindowFocus(); });
    reflectWindowFocus();

    var banner = document.getElementById('sc-banner');
    var qbody  = document.getElementById('sc-qbody');
    var qwrap  = document.getElementById('sc-qwrap');
    var qempty = document.getElementById('sc-qempty');
    var count  = document.getElementById('sc-count');
    var esc    = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };

    function paint(r, rows) {
        if (banner && r) {
            var ask = r.choices && r.choices.length;
            banner.className = 'sc-banner ' + (ask ? 'ask' : (r.ok ? 'ok' : 'bad'));
            var h = '<div class="t">' + (ask ? '' : (r.ok ? '✓ ' : '✕ ')) + esc(r.title) + '</div>'
                  + '<div class="d">' + esc(r.detail) + '</div>';
            if (ask) {
                h += '<form method="post" class="sc-pick">'
                   + '<input type="hidden" name="_csrf" value="' + esc(TOKEN) + '">'
                   + '<input type="hidden" name="_action" value="pick">';
                r.choices.forEach(function (c) {
                    h += '<button name="stream_id" value="' + esc(c.stream_id) + '">' + esc(c.stream) + ' — ' + esc(c.stage) + '</button>';
                });
                h += '</form>';
            }
            banner.innerHTML = h;
            banner.hidden = false;
        }
        if (qbody && rows) {
            qbody.innerHTML = rows.map(function (r) {
                return '<tr><td class="sc-ref">' + esc(r.ref) + '</td><td>' + esc(r.blind) + '</td><td>' + esc(r.size)
                     + '</td><td>' + esc(r.room) + '</td><td><span class="sc-stage">' + esc(r.stage)
                     + '</span></td><td>' + esc(r.due) + '</td></tr>';
            }).join('');
            if (qwrap)  qwrap.hidden  = rows.length === 0;
            if (qempty) qempty.hidden = rows.length !== 0;
            if (count)  count.textContent = rows.length + ' waiting';
        }
    }

    var sent = false;
    function go() {
        if (sent) return;                      // one in flight at a time
        var code = box.value.trim();
        if (!/^\d{8,9}$/.test(code)) return;   // only a real code
        sent = true;

        // Clear the box BEFORE sending, not after. Someone can pull the trigger
        // again while this is in the air — if the box still held the old code,
        // the new scan would land on the end of it, make nonsense, and then get
        // wiped when the reply cleared the box. Clearing now means a scan
        // arriving mid-flight lands in an empty box and is waiting for us.
        box.value = '';

        var body = new URLSearchParams({ _csrf: TOKEN, code: code, _ajax: '1' });
        var done = function (r, rows) {
            sent = false;
            box.focus();
            paint(r, rows);
            // Anything that arrived while we were waiting? Deal with it now.
            if (/^\d{8,9}$/.test(box.value.trim())) go();
        };
        fetch('/factory/scan.php', { method: 'POST', body: body, headers: {'Content-Type':'application/x-www-form-urlencoded'} })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { done(j ? j.result : null, j ? j.rows : null); })
            .catch(function () {
                // Never swallow a scan silently — if the post failed, say so, or
                // the workshop scans on believing it landed. The code is named
                // so it can be found again.
                done({ ok: false, title: 'Scan not saved', detail: code + ' didn\'t reach the server — scan it again.' }, null);
            });
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

    // Enter still works, whether the scanner sends one or two — but it goes
    // through the same background post, never a page reload. LF & CR sends
    // Enter TWICE; the second lands while the first is in flight and is dropped
    // by the sent guard, so a blind can't advance two stages off one pull.
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(idle);
        go();
    });
})();
</script>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
