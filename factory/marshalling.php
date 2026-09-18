<?php
declare(strict_types=1);

/**
 * Factory · Marshalling (Phase D).
 *
 * The collection/dispatch bench. Finished blinds are scanned in here — from every
 * production area — and an order can only be dispatched once every one of its
 * blinds has arrived. That's the convergence point: all the areas' parts come
 * together before anything ships.
 *
 * A dispatch is still gated on Ready (every blind made, every bought-in item in);
 * marshalling adds the physical "and it's all actually here" check on top.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/order_stage.php';
require __DIR__ . '/../_partials/due_dates.php';
require __DIR__ . '/../_partials/factory_kv.php';
require __DIR__ . '/../_partials/qr.php';

requireFactory();

$pdo    = db();
$MASTER = current_factory_id();
$user   = current_user();
$userId = (int) ($user['user_id'] ?? 0);
$ready  = bj_tables_ready($pdo);
$hasMarshal = $ready && bj_marshal_ready($pdo);
$hasDue = dd_ready($pdo);
$isSuper = function_exists('is_super_admin') && is_super_admin();

// IDOR guard for a blind job: does it belong to a factory-owned order line?
$ownJob = static function (PDO $pdo, int $jobId, int $factory): bool {
    if ($jobId <= 0) return false;
    $s = $pdo->prepare(
        'SELECT 1 FROM factory_blind_jobs bj JOIN products p ON p.id = bj.product_id
          WHERE bj.id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ? LIMIT 1'
    );
    $s->execute([$jobId, $factory]);
    return (bool) $s->fetchColumn();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'marshal_scan') {
            $parsed = qr_parse_code((string) ($_POST['code'] ?? ''));
            if ($parsed === null) {
                $_SESSION['flash_error'] = 'That didn\'t read as a blind label.';
            } else {
                [$itemId, $unitNo] = $parsed;
                $res = bj_mark_marshalled($pdo, (int) $itemId, (int) $unitNo, $userId ?: null);
                if ($res['ok']) $_SESSION['flash_success'] = ($res['already'] ?? false ? 'Already collected: ' : 'Collected: ') . $res['title'] . ' — ' . $res['detail'];
                else            $_SESSION['flash_error']   = $res['title'] . ' — ' . $res['detail'];
            }
        } elseif ($action === 'mark' || $action === 'unmark') {
            $jobId = (int) ($_POST['job_id'] ?? 0);
            if ($ownJob($pdo, $jobId, $MASTER)) {
                if ($action === 'unmark') {
                    bj_unmarshal($pdo, $jobId, $userId ?: null);
                } else {
                    // Mark in by job id — must be made first.
                    $j = $pdo->prepare('SELECT quote_item_id, unit_no FROM factory_blind_jobs WHERE id = ?');
                    $j->execute([$jobId]);
                    $row = $j->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $res = bj_mark_marshalled($pdo, (int) $row['quote_item_id'], (int) $row['unit_no'], $userId ?: null);
                        if (!$res['ok']) $_SESSION['flash_error'] = $res['title'] . ' — ' . $res['detail'];
                    }
                }
            }
        } elseif ($action === 'mark_order') {
            // Collect every made blind on one order in a click (manual convenience).
            $quoteId = (int) ($_POST['quote_id'] ?? 0);
            $js = $pdo->prepare(
                "SELECT bj.quote_item_id, bj.unit_no
                   FROM factory_blind_jobs bj JOIN products p ON p.id = bj.product_id
                  WHERE bj.quote_id = ? AND bj.status = 'complete' AND bj.marshalled_at IS NULL
                    AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?"
            );
            $js->execute([$quoteId, $MASTER]);
            $n = 0;
            foreach ($js->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $res = bj_mark_marshalled($pdo, (int) $r['quote_item_id'], (int) $r['unit_no'], $userId ?: null);
                if ($res['ok'] && empty($res['already'])) $n++;
            }
            $_SESSION['flash_success'] = $n > 0 ? "Collected {$n} blind" . ($n === 1 ? '' : 's') . '.' : 'Nothing new to collect on that order.';
        } elseif ($action === 'gen_key' && $isSuper) {
            fx_kv_set($pdo, 'marshal_scan_key', bin2hex(random_bytes(12)));
            $_SESSION['flash_success'] = 'Marshalling scan key generated.';
        } elseif ($action === 'clear_key' && $isSuper) {
            fx_kv_set($pdo, 'marshal_scan_key', '');
            $_SESSION['flash_success'] = 'Marshalling scan key removed.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update marshalling: ' . $e->getMessage();
    }
    header('Location: /factory/marshalling.php');
    exit;
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Area names for the per-area convergence chips.
$areaNames = [];
try {
    $a = $pdo->prepare('SELECT id, name FROM production_areas WHERE client_id = ? ORDER BY sort_order, id');
    $a->execute([$MASTER]);
    foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $r) $areaNames[(int) $r['id']] = (string) $r['name'];
} catch (Throwable $e) { $areaNames = []; }
$hasAreaCol = false;
try { $pdo->query('SELECT area_id FROM factory_blind_jobs LIMIT 0'); $hasAreaCol = true; }
catch (Throwable $e) { $hasAreaCol = false; }

// Orders with blinds on the floor that aren't dispatched — what's converging.
$orders = [];
$blindsByOrder = [];
if ($hasMarshal) {
    $dueSel  = $hasDue ? 'q.due_date' : 'NULL AS due_date';
    $areaSel = $hasAreaCol ? 'bj.area_id' : 'NULL AS area_id';
    $os = $pdo->query(
        "SELECT bj.quote_id, q.quote_number, q.created_at, $dueSel, c.company_name AS tenant,
                COALESCE(fj.status,'') AS fj_status
           FROM factory_blind_jobs bj
           JOIN quotes q   ON q.id = bj.quote_id
           JOIN clients c  ON c.id = q.client_id
           JOIN products p ON p.id = bj.product_id
           LEFT JOIN factory_jobs fj ON fj.quote_id = bj.quote_id
          WHERE COALESCE(NULLIF(p.source_client_id,0), p.client_id) = {$MASTER}
            AND COALESCE(fj.status,'') <> 'dispatched'
          GROUP BY bj.quote_id
          ORDER BY " . ($hasDue ? 'q.due_date IS NULL, q.due_date,' : '') . " q.created_at, q.id
          LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    $orders = $os;

    $qids = array_map(static fn ($r) => (int) $r['quote_id'], $orders);
    if ($qids) {
        $ph = implode(',', array_fill(0, count($qids), '?'));
        $bs = $pdo->prepare(
            "SELECT bj.id, bj.quote_id, bj.unit_no, bj.status, bj.marshalled_at, $areaSel,
                    qi.line_no, qi.product_name_snapshot, qi.width_mm, qi.drop_mm, qi.quantity
               FROM factory_blind_jobs bj
               JOIN quote_items qi ON qi.id = bj.quote_item_id
              WHERE bj.quote_id IN ($ph)
              ORDER BY " . ($hasAreaCol ? 'bj.area_id IS NULL, bj.area_id,' : '') . " qi.line_no, bj.unit_no"
        );
        $bs->execute($qids);
        foreach ($bs->fetchAll(PDO::FETCH_ASSOC) as $b) $blindsByOrder[(int) $b['quote_id']][] = $b;
    }
}

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('j M y'); } catch (Throwable $e) { return (string) $ts; }
};
$areaLabel = static function (int $aid) use ($areaNames): string {
    return $aid === 0 ? 'Unassigned' : ($areaNames[$aid] ?? ('Area ' . $aid));
};

$marshalKey = $hasMarshal ? fx_marshal_key($pdo) : '';

$factoryTitle = 'Marshalling';
$factoryNav   = 'marshal';
$factoryWide  = true;
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .ms-h { font-size:1.5rem; font-weight:700; margin:0 0 .2rem; }
  .ms-sub { color:var(--text-muted,#667); margin:0 0 1.1rem; font-size:.92rem; }
  .ms-flash { border-radius:10px; padding:.6rem .9rem; margin:0 0 1rem; font-size:.9rem; }
  .ms-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
  .ms-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
  .ms-scan { display:flex; align-items:center; gap:.8rem; margin:0 0 1.2rem; flex-wrap:wrap; }
  .ms-scan input { font:inherit; font-size:1.4rem; font-weight:700; letter-spacing:.06em; padding:.55rem .8rem;
      border:3px solid #155e75; border-radius:12px; width:15rem; background:var(--bg-card,#fff); color:inherit; }
  .ms-scan .hint { color:var(--text-muted,#667); font-size:.9rem; }
  .ms-empty { background:var(--bg-subtle,#f8fafc); border:1px dashed var(--border,#e5e7eb); border-radius:12px; padding:2rem; text-align:center; color:var(--text-faint,#94a3b8); }
  .ms-order { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); margin:0 0 1rem; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .ms-ohead { display:flex; align-items:center; gap:.9rem; padding:.7rem .95rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); flex-wrap:wrap; }
  .ms-ono { font-weight:800; font-variant-numeric:tabular-nums; }
  .ms-oten { color:var(--text-muted,#667); font-size:.9rem; }
  .ms-odue { color:var(--text-muted,#667); font-size:.85rem; }
  .ms-conv { display:inline-flex; align-items:center; gap:.4rem; font-size:.85rem; color:var(--text-muted,#667); }
  .ms-track { width:8rem; height:.5rem; border-radius:999px; background:#e5e7eb; overflow:hidden; }
  .ms-fill { height:100%; background:#155e75; } .ms-fill.full { background:#16a34a; }
  .ms-areas { display:flex; gap:.3rem; flex-wrap:wrap; }
  .ms-chip { font-size:.72rem; font-weight:700; padding:.1rem .5rem; border-radius:999px; background:#e5e7eb; color:#475569; white-space:nowrap; }
  .ms-chip.part { background:#cffafe; color:#155e75; } .ms-chip.done { background:#dcfce7; color:#166534; }
  .ms-actions { margin-left:auto; display:flex; gap:.5rem; align-items:center; }
  .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:.45rem .85rem; }
  .btn.go { background:#166534; color:#fff; } .btn.go[disabled] { background:#cbd5e1; color:#eef2f6; cursor:not-allowed; }
  .btn.ghost { background:#eef2f6; color:#334155; } .btn.mini { padding:.25rem .55rem; font-size:.8rem; }
  .ms-gate { font-size:.8rem; color:#92600a; }
  table.ms-b { width:100%; border-collapse:collapse; }
  .ms-b th { text-align:left; font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); font-weight:700; padding:.4rem .95rem; border-bottom:1px solid var(--border,#eef1f5); }
  .ms-b td { padding:.4rem .95rem; border-bottom:1px solid var(--border,#eef1f5); font-size:.9rem; vertical-align:middle; }
  .ms-b tr:last-child td { border-bottom:none; }
  .ms-ref { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .ms-st { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:.1rem .45rem; border-radius:999px; }
  .ms-st.made { background:#dcfce7; color:#166534; } .ms-st.work { background:#fef3c7; color:#92600a; } .ms-st.queue { background:#e5e7eb; color:#475569; }
  .ms-in { color:#155e75; font-weight:700; } .ms-out { color:var(--text-faint,#94a3b8); }
  .ms-keycard { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); padding:.9rem 1.1rem; margin:1.2rem 0 0; }
  .ms-keycard h2 { font-size:1rem; margin:0 0 .3rem; }
  .ms-url { width:100%; max-width:40rem; font-family:ui-monospace,Consolas,monospace; font-size:.8rem; padding:.35rem .5rem; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; background:var(--bg-subtle,#f8fafc); color:inherit; }
  .inline { display:inline; }
</style>

<h1 class="ms-h">Marshalling</h1>
<p class="ms-sub">The collection bench. Scan every finished blind in as it arrives from each area — an order can only be dispatched once all of it is here <em>and</em> it's Ready.</p>

<?php if ($flashOk !== ''): ?><div class="ms-flash ok" role="status"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="ms-flash err" role="alert"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$hasMarshal): ?>
    <div class="ms-flash err">Marshalling isn't set up yet — run <code>/migrate_marshalling.php</code>.</div>
<?php else: ?>

<form method="post" class="ms-scan" id="ms-form" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="_action" value="marshal_scan">
    <input type="text" name="code" id="ms-code" autofocus placeholder="scan a finished blind">
    <span class="hint">Scan a blind's label to collect it into marshalling.</span>
</form>

<?php if (!$orders): ?>
    <div class="ms-empty">Nothing in production right now. Blinds appear here to be collected once an order is started on <a href="/factory/incoming-orders.php">Incoming Orders</a>.</div>
<?php else:
    foreach ($orders as $o):
        $qid    = (int) $o['quote_id'];
        $blinds = $blindsByOrder[$qid] ?? [];
        $total  = count($blinds);
        $made   = 0; $coll = 0;
        $areaTally = [];   // area_id => [total, coll]
        foreach ($blinds as $b) {
            $isMade = $b['status'] === 'complete';
            $isColl = !empty($b['marshalled_at']);
            if ($isMade) $made++;
            if ($isColl) $coll++;
            $aid = (int) ($b['area_id'] ?? 0);
            $areaTally[$aid]['total'] = ($areaTally[$aid]['total'] ?? 0) + 1;
            $areaTally[$aid]['coll']  = ($areaTally[$aid]['coll'] ?? 0) + ($isColl ? 1 : 0);
        }
        $allColl  = $total > 0 && $coll === $total;
        $isReady  = os_is_ready($pdo, $qid, $MASTER);
        $canDispatch = $allColl && $isReady;
        $pct = $total > 0 ? (int) round($coll / $total * 100) : 0;
        $gate = !$allColl ? 'waiting on ' . ($total - $coll) . ' blind' . (($total - $coll) === 1 ? '' : 's') . ' to collect'
              : (!$isReady ? 'not Ready yet (bought-in outstanding?)' : '');
?>
    <div class="ms-order">
        <div class="ms-ohead">
            <span class="ms-ono"><?= e((string) $o['quote_number']) ?></span>
            <span class="ms-oten"><?= e((string) $o['tenant']) ?></span>
            <?php if ($hasDue && !empty($o['due_date'])): ?><span class="ms-odue">Due <?= e($fmtDate($o['due_date'])) ?></span><?php endif; ?>
            <span class="ms-conv" title="<?= $coll ?> of <?= $total ?> collected">
                <span class="ms-track"><span class="ms-fill<?= $pct >= 100 ? ' full' : '' ?>" style="width:<?= $pct ?>%"></span></span>
                <b><?= $coll ?></b>/<?= $total ?> collected
            </span>
            <span class="ms-areas">
                <?php foreach ($areaTally as $aid => $t):
                    $c = $t['coll'] >= $t['total'] ? 'done' : ($t['coll'] > 0 ? 'part' : '');
                ?>
                    <span class="ms-chip <?= $c ?>"><?= e($areaLabel((int) $aid)) ?> <?= (int) $t['coll'] ?>/<?= (int) $t['total'] ?></span>
                <?php endforeach; ?>
            </span>
            <span class="ms-actions">
                <a class="btn ghost mini" href="/factory/order-areas.php?order=<?= $qid ?>">Whole order &rarr;</a>
                <?php if ($made > $coll): ?>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="mark_order"><input type="hidden" name="quote_id" value="<?= $qid ?>"><button class="btn ghost mini" title="Collect every made blind on this order">Collect all made</button></form>
                <?php endif; ?>
                <form method="post" action="/factory/set-status.php" class="inline" onsubmit="return confirm('Dispatch <?= e((string) $o['quote_number']) ?>? All blinds are collected and the order is Ready.')">
                    <?= csrf_field() ?><input type="hidden" name="status" value="dispatched"><input type="hidden" name="quote_id" value="<?= $qid ?>">
                    <button class="btn go" <?= $canDispatch ? '' : 'disabled' ?> title="<?= $canDispatch ? 'Dispatch this order' : e($gate) ?>">Dispatch</button>
                </form>
            </span>
        </div>
        <?php if ($gate !== ''): ?><div style="padding:.35rem .95rem 0"><span class="ms-gate">⛔ <?= e($gate) ?></span></div><?php endif; ?>
        <table class="ms-b">
            <thead><tr><th>Blind</th><th>Area</th><th>Size</th><th>Made</th><th>Collected</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($blinds as $b):
                $qty = max(1, (int) $b['quantity']);
                $ref = (string) $o['quote_number'] . '-' . (int) $b['line_no'] . '-(' . (int) $b['unit_no'] . '/' . $qty . ')';
                $isMade = $b['status'] === 'complete';
                $isColl = !empty($b['marshalled_at']);
                $stCls = $isMade ? 'made' : ($b['status'] === 'in_progress' ? 'work' : 'queue');
                $stTxt = $isMade ? 'Made' : ($b['status'] === 'in_progress' ? 'In progress' : 'Queued');
            ?>
                <tr>
                    <td><span class="ms-ref"><?= e($ref) ?></span> <?= e((string) $b['product_name_snapshot']) ?></td>
                    <td><?= e($areaLabel((int) ($b['area_id'] ?? 0))) ?></td>
                    <td style="white-space:nowrap"><?= (int) $b['width_mm'] ?> &times; <?= (int) $b['drop_mm'] ?></td>
                    <td><span class="ms-st <?= $stCls ?>"><?= e($stTxt) ?></span></td>
                    <td><?= $isColl ? '<span class="ms-in">✓ collected</span>' : '<span class="ms-out">—</span>' ?></td>
                    <td style="text-align:right">
                        <?php if ($isColl): ?>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="unmark"><input type="hidden" name="job_id" value="<?= (int) $b['id'] ?>"><button class="btn ghost mini" title="Undo — put it back as not collected">Undo</button></form>
                        <?php elseif ($isMade): ?>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="mark"><input type="hidden" name="job_id" value="<?= (int) $b['id'] ?>"><button class="btn ghost mini">Mark in</button></form>
                        <?php else: ?>
                            <span class="ms-out" style="font-size:.8rem">in production</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; endif; ?>

<?php if ($isSuper): ?>
    <div class="ms-keycard">
        <h2>Marshalling bench scanner</h2>
        <p class="ms-sub" style="margin:.2rem 0 .7rem">The collection bench's WiFi scanner uses this key. It <em>collects</em> whatever it scans (rather than advancing production). Put <code>{CODE}</code> where the scanner sends the barcode.</p>
        <?php $scanBase = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yourblinds.uk') . '/factory/scan-in.php'; ?>
        <?php if ($marshalKey === ''): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="gen_key"><button class="btn go mini">Generate key</button></form>
        <?php else: ?>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <input type="text" class="ms-url" readonly onclick="this.select()" value="<?= e($scanBase . '?key=' . $marshalKey . '&c={CODE}') ?>">
                <form method="post" class="inline" onsubmit="return confirm('Generate a new key? The old one stops working immediately.')"><?= csrf_field() ?><input type="hidden" name="_action" value="gen_key"><button class="btn ghost mini">Regenerate</button></form>
                <form method="post" class="inline" onsubmit="return confirm('Remove this key? The marshalling scanner will stop working.')"><?= csrf_field() ?><input type="hidden" name="_action" value="clear_key"><button class="btn ghost mini">✕</button></form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Scan-and-go: a wedge scanner fills the box and a moment's silence submits it,
// so the collector never has to touch the keyboard. Enter still works.
(function () {
    var form = document.getElementById('ms-form'), box = document.getElementById('ms-code');
    if (!form || !box) return;
    setInterval(function () { if (document.activeElement !== box && !/^(INPUT|BUTTON)$/.test(document.activeElement.tagName)) box.focus(); }, 900);
    var idle = null;
    box.addEventListener('input', function () {
        clearTimeout(idle);
        if (/^\d{8,9}$/.test(box.value.trim())) idle = setTimeout(function () { form.submit(); }, 150);
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
