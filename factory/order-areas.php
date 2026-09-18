<?php
declare(strict_types=1);

/**
 * Factory · Order across areas (Phase C).
 *
 * The whole of one order, grouped by production area — so a bench that only makes
 * its own part can still see the rest of the order coming together, and the office
 * can see at a glance whether every area is ready to converge for dispatch.
 *
 * Read-only. The viewer's own (home) area is highlighted; every other area is
 * shown for awareness. Scoped + IDOR-guarded to the acting factory's own blinds.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/due_dates.php';

requireFactory();

$pdo    = db();
$MASTER = current_factory_id();
$order  = (int) ($_GET['order'] ?? 0);

$hasDue = dd_ready($pdo);
$hasArea = false;
try { $pdo->query('SELECT area_id FROM factory_blind_jobs LIMIT 0'); $hasArea = true; }
catch (Throwable $e) { $hasArea = false; }

// This login's home area, to highlight "yours" among the rest.
$homeArea = 0;
try {
    $u = $pdo->prepare('SELECT area_id FROM user_production_areas WHERE user_id = ? ORDER BY area_id LIMIT 1');
    $u->execute([(int) (current_user()['user_id'] ?? 0)]);
    $homeArea = (int) $u->fetchColumn();
} catch (Throwable $e) { $homeArea = 0; }

// Area names for this factory.
$areaNames = [];
try {
    $a = $pdo->prepare('SELECT id, name FROM production_areas WHERE client_id = ? ORDER BY sort_order, id');
    $a->execute([$MASTER]);
    foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $r) $areaNames[(int) $r['id']] = (string) $r['name'];
} catch (Throwable $e) { $areaNames = []; }

// The order's in-house blinds, scoped to THIS factory (owning factory of the
// product). No rows = not this factory's order → nothing to show (IDOR guard).
$rows = [];
$meta = null;
if ($order > 0) {
    $dueSel = $hasDue ? 'q.due_date' : 'NULL AS due_date';
    $areaSel = $hasArea ? 'bj.area_id' : 'NULL AS area_id';
    $st = $pdo->prepare(
        "SELECT bj.id, bj.unit_no, bj.product_id, bj.status, $areaSel,
                q.quote_number, q.created_at, $dueSel, c.company_name AS tenant,
                qi.line_no, qi.product_name_snapshot, qi.system_name_snapshot,
                qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
                qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name
           FROM factory_blind_jobs bj
           JOIN quotes q       ON q.id = bj.quote_id
           JOIN clients c      ON c.id = q.client_id
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN products p     ON p.id = bj.product_id
          WHERE bj.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
          ORDER BY " . ($hasArea ? 'bj.area_id IS NULL, bj.area_id,' : '') . " qi.line_no, bj.unit_no"
    );
    $st->execute([$order, $MASTER]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) $meta = $rows[0];
}

// Group by area, computing each blind's progress (same maths as the floor).
$byArea      = [];   // area_id => [rows]
$areaRoll    = [];   // area_id => ['total'=>, 'made'=>]
$grandTotal  = 0;
$grandMade   = 0;
if ($rows) {
    $streamsBy = bj_streams_for($pdo, array_map(static fn ($r) => (int) $r['id'], $rows));
    foreach ($rows as &$r) {
        $aid = (int) ($r['area_id'] ?? 0);
        $byStream = bj_route_by_stream($pdo, (int) $r['product_id']);
        [$dc, $tot] = bj_progress($byStream, $streamsBy[(int) $r['id']] ?? []);
        $r['_pct']  = $tot > 0 ? (int) round($dc / $tot * 100) : 0;
        $r['_made'] = $r['status'] === 'complete';
        $byArea[$aid][] = $r;
        $areaRoll[$aid]['total'] = ($areaRoll[$aid]['total'] ?? 0) + 1;
        $areaRoll[$aid]['made']  = ($areaRoll[$aid]['made'] ?? 0) + ($r['_made'] ? 1 : 0);
        $grandTotal++; if ($r['_made']) $grandMade++;
    }
    unset($r);
}

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('j M y'); } catch (Throwable $e) { return (string) $ts; }
};
$areaLabel = static function (int $aid) use ($areaNames): string {
    return $aid === 0 ? 'Unassigned' : ($areaNames[$aid] ?? ('Area ' . $aid));
};

$factoryTitle = 'Order across areas';
$factoryNav   = 'floor';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .oa-h { font-size:1.5rem; font-weight:700; margin:0 0 .2rem; }
  .oa-sub { color:var(--text-muted,#667); margin:0 0 1rem; font-size:.92rem; }
  .oa-back { font-size:.85rem; }
  .oa-top { display:flex; flex-wrap:wrap; gap:1rem 2rem; align-items:baseline; margin:0 0 1.2rem; }
  .oa-top .big { font-size:1.15rem; font-weight:800; }
  .oa-roll { display:inline-flex; align-items:center; gap:.5rem; font-size:.9rem; color:var(--text-muted,#667); }
  .oa-track { width:9rem; height:.55rem; border-radius:999px; background:#e5e7eb; overflow:hidden; }
  .oa-fill { height:100%; background:#0f766e; }
  .oa-fill.full { background:#16a34a; }
  .oa-ready { font-weight:700; padding:.15rem .6rem; border-radius:999px; font-size:.8rem; }
  .oa-ready.yes { background:#dcfce7; color:#166534; } .oa-ready.no { background:#fef3c7; color:#92600a; }
  .oa-area { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); margin:0 0 1rem; overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .oa-area.mine { border-color:#0f766e; box-shadow:0 0 0 1px #0f766e; }
  .oa-ahead { display:flex; align-items:center; gap:.7rem; padding:.6rem .9rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); }
  .oa-ahead .nm { font-weight:700; font-size:1rem; }
  .oa-ahead .mine-tag { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; background:#0f766e; color:#fff; padding:.1rem .45rem; border-radius:999px; }
  .oa-ahead .cnt { margin-left:auto; font-size:.85rem; color:var(--text-muted,#667); }
  table.oa-t { width:100%; border-collapse:collapse; }
  .oa-t th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); font-weight:700; padding:.4rem .9rem; border-bottom:1px solid var(--border,#eef1f5); }
  .oa-t td { padding:.45rem .9rem; border-bottom:1px solid var(--border,#eef1f5); font-size:.9rem; vertical-align:middle; }
  .oa-t tr:last-child td { border-bottom:none; }
  .oa-ref { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .oa-mini { display:inline-flex; align-items:center; gap:.4rem; }
  .oa-mini .t { width:5rem; height:.45rem; border-radius:999px; background:#e5e7eb; overflow:hidden; }
  .oa-mini .f { height:100%; background:#0f766e; } .oa-mini .f.full { background:#16a34a; }
  .oa-st { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:.1rem .45rem; border-radius:999px; }
  .oa-st.made { background:#dcfce7; color:#166534; } .oa-st.work { background:#fef3c7; color:#92600a; } .oa-st.queue { background:#e5e7eb; color:#475569; }
  .oa-empty { background:var(--bg-subtle,#f8fafc); border:1px dashed var(--border,#e5e7eb); border-radius:12px; padding:2rem; text-align:center; color:var(--text-faint,#94a3b8); }
</style>

<p class="oa-back"><a href="/factory/floor.php">&larr; Back to the floor</a></p>

<?php if (!$meta): ?>
    <h1 class="oa-h">Order across areas</h1>
    <div class="oa-empty">That order isn't on your floor, or has no blinds in production.</div>
<?php else:
    $pct = $grandTotal > 0 ? (int) round($grandMade / $grandTotal * 100) : 0;
    $allMade = $grandTotal > 0 && $grandMade === $grandTotal;
?>
    <h1 class="oa-h">Order <?= e((string) $meta['quote_number']) ?></h1>
    <p class="oa-sub"><?= e((string) $meta['tenant']) ?> &middot; every blind on this order, grouped by the area that makes it.</p>

    <div class="oa-top">
        <span class="oa-roll">
            <span class="oa-track"><span class="oa-fill<?= $pct >= 100 ? ' full' : '' ?>" style="width:<?= $pct ?>%"></span></span>
            <b><?= $grandMade ?></b>&nbsp;of&nbsp;<b><?= $grandTotal ?></b> blinds made &middot; <?= count($byArea) ?> area<?= count($byArea) === 1 ? '' : 's' ?>
        </span>
        <?php if ($hasDue && !empty($meta['due_date'])): ?><span class="big">Due <?= e($fmtDate($meta['due_date'])) ?></span><?php endif; ?>
        <span class="oa-ready <?= $allMade ? 'yes' : 'no' ?>"><?= $allMade ? 'All areas done — ready to converge' : 'Still in production' ?></span>
    </div>

    <?php foreach ($byArea as $aid => $list):
        $roll = $areaRoll[$aid] ?? ['total' => 0, 'made' => 0];
        $mine = $aid !== 0 && $aid === $homeArea;
    ?>
        <div class="oa-area<?= $mine ? ' mine' : '' ?>">
            <div class="oa-ahead">
                <span class="nm"><?= e($areaLabel((int) $aid)) ?></span>
                <?php if ($mine): ?><span class="mine-tag">Your area</span><?php endif; ?>
                <span class="cnt"><?= (int) $roll['made'] ?> of <?= (int) $roll['total'] ?> made</span>
            </div>
            <table class="oa-t">
                <thead><tr><th>Job ref</th><th>Blind</th><th>Size</th><th>Room</th><th>Progress</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r):
                    $qty = max(1, (int) $r['quantity']);
                    $ref = (string) $r['quote_number'] . '-' . (int) $r['line_no'] . '-(' . (int) $r['unit_no'] . '/' . $qty . ')';
                    $fab = trim((string) $r['fabric_name_snapshot']);
                    $col = trim((string) $r['fabric_colour_snapshot']);
                    $sys = trim((string) $r['system_name_snapshot']);
                    $stCls = $r['_made'] ? 'made' : ($r['status'] === 'in_progress' ? 'work' : 'queue');
                    $stTxt = $r['_made'] ? 'Made' : ($r['status'] === 'in_progress' ? 'In progress' : 'Queued');
                ?>
                    <tr>
                        <td class="oa-ref"><a class="fl-ref" href="/factory/worksheet-print.php?order=<?= (int) $order ?>" target="_blank" rel="noopener"><?= e($ref) ?></a></td>
                        <td><?= e((string) $r['product_name_snapshot']) ?><?php if ($sys !== ''): ?> <span style="color:var(--text-muted,#667)"><?= e($sys) ?></span><?php endif; ?>
                            <?php if ($fab !== '' || $col !== ''): ?><br><span style="color:var(--text-muted,#667);font-size:.82rem"><?= e(trim($fab . ($col !== '' ? ' / ' . $col : ''))) ?></span><?php endif; ?></td>
                        <td style="white-space:nowrap"><?= (int) $r['width_mm'] ?> &times; <?= (int) $r['drop_mm'] ?></td>
                        <td><?= e((string) $r['room_name']) ?></td>
                        <td><span class="oa-mini"><span class="t"><span class="f<?= $r['_pct'] >= 100 ? ' full' : '' ?>" style="width:<?= (int) $r['_pct'] ?>%"></span></span><?= (int) $r['_pct'] ?>%</span></td>
                        <td><span class="oa-st <?= $stCls ?>"><?= e($stTxt) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
