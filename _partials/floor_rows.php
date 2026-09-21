<?php
declare(strict_types=1);

/**
 * Floor table rows — one <tr> per blind. Shared by the full Floor page and the
 * live-update fragment (floor.php?rows=1), so the wallboard can quietly swap in
 * fresh rows without a reload or scroll jump.
 *
 * Expects in scope: $rows, $streamsBy, $pdo, $dueTag, $fmtDate, $RT.
 * Optional (Phase C/E): $orderAreas, $orderTotals, $areaNames — order summary.
 */
$orderAreas  = $orderAreas  ?? [];
$orderTotals = $orderTotals ?? [];
$areaNames   = $areaNames   ?? [];

// Rows arrive grouped by order (the query orders by due, then q.id, line, unit),
// so a header can simply be emitted whenever the order changes. Everything that
// describes the ORDER rather than the blind lives on that header now: it used to
// be repeated on every single row, which on a 16-blind order meant four
// identical lines printed sixteen times and one order filling two screens.
$flLastOrder = null;
foreach ($rows as $r):
    $qidRow = (int) $r['quote_id'];
    if ($qidRow !== $flLastOrder):
        $flLastOrder = $qidRow;
        $ot       = $orderTotals[$qidRow] ?? null;
        $ordTotal = $ot ? (int) $ot['total'] : 0;
        $ordDone  = $ot ? (int) $ot['done']  : 0;
        $ordPct   = $ordTotal > 0 ? (int) round($ordDone / $ordTotal * 100) : 0;
        $oa       = $orderAreas[$qidRow] ?? [];
        [$oDueCls, $oDueTxt] = $dueTag($r['due_date'] ?? null, $ordTotal > 0 && $ordDone >= $ordTotal);
?>
    <tr class="fl-ohead" data-ohead="1" data-order="<?= $qidRow ?>">
        <td colspan="7"><div class="fl-ohead-inner">
            <button type="button" class="fl-otog" aria-expanded="false"
                    title="Show or hide this order's blinds">
                <span class="fl-ocar" aria-hidden="true">&#9656;</span>
                <span class="fl-onum"><?= e((string) $r['quote_number']) ?></span>
            </button>
            <span class="fl-otenant"><?= e((string) $r['tenant']) ?></span>
            <span class="fl-oprog" title="<?= $ordDone ?> of <?= $ordTotal ?> blinds made">
                <span class="fl-prog-track"><span class="fl-prog-fill<?= $ordPct >= 100 ? ' full' : '' ?>" style="width:<?= $ordPct ?>%"></span></span>
                <span class="fl-oprog-txt"><?= $ordDone ?>/<?= $ordTotal ?> made</span>
            </span>
            <?php if ($ordTotal > 0): ?>
                <?php if ($ordDone >= $ordTotal): ?>
                    <a class="fl-ord ready" href="/factory/order-areas.php?order=<?= $qidRow ?>" title="Every blind on this order is made — it can be dispatched">&#10003; ready to dispatch</a>
                <?php else: ?>
                    <a class="fl-ord wait" href="/factory/order-areas.php?order=<?= $qidRow ?>" title="This order can't dispatch until all its blinds are made"><?= $ordTotal - $ordDone ?> still to make</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (count($oa) > 1): ?>
                <a class="fl-others" href="/factory/order-areas.php?order=<?= $qidRow ?>" title="See the whole order across every area">
                    <?php foreach ($oa as $aid => $ag):
                        $nm  = $aid === 0 ? 'Unassigned' : ($areaNames[$aid] ?? ('Area ' . $aid));
                        $cls = $ag['done'] >= $ag['total'] ? 'done' : ($ag['done'] > 0 ? 'part' : '');
                    ?>
                        <span class="fl-oa <?= $cls ?>"><?= e($nm) ?> <?= (int) $ag['done'] ?>/<?= (int) $ag['total'] ?></span>
                    <?php endforeach; ?>
                </a>
            <?php endif; ?>
            <span class="fl-odue fl-due <?= $oDueCls ?>"><?= e($oDueTxt) ?></span>
            <span class="fl-oshown"></span>
        </div></td>
    </tr>
<?php endif; ?>
<?php
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

    // The areas this blind belongs to — one per stream (Phase E), so a split
    // vertical filters onto BOTH its headrail and its fabric bench. Falls back to
    // the blind-level area, then 0 (unassigned), if streams carry no area yet.
    $rowAreas = [];
    foreach ($myStreams as $sr) {
        if (isset($sr['area_id']) && $sr['area_id'] !== null) $rowAreas[(int) $sr['area_id']] = true;
    }
    if (!$rowAreas && (int) ($r['area_id'] ?? 0) > 0) $rowAreas[(int) $r['area_id']] = true;
    $dataArea = $rowAreas ? implode(',', array_keys($rowAreas)) : '0';

    $fab = trim((string) $r['fabric_name_snapshot']);
    $col = trim((string) $r['fabric_colour_snapshot']);
    $sys = trim((string) $r['system_name_snapshot']);
    $searchKey = strtolower(trim($ref . ' ' . $r['product_name_snapshot'] . ' ' . $sys . ' ' . $fab . ' ' . $col . ' ' . $r['room_name'] . ' ' . $r['tenant']));
?>
    <?php /* Tenant, order convergence and the area chips used to be repeated here
             on every blind. They describe the ORDER, so they live on the header
             above and this row carries only what is true of this one blind. */ ?>
    <tr class="fl-job <?= $done ? 'is-made' : '' ?>" data-order="<?= $qidRow ?>" data-search="<?= e($searchKey) ?>" data-station="<?= e(implode(',', $atStations)) ?>" data-area="<?= e($dataArea) ?>" data-made="<?= $done ? 1 : 0 ?>">
        <td>
            <a class="fl-ref" href="/factory/worksheet-print.php?order=<?= (int) $r['quote_id'] ?>" target="_blank" rel="noopener"><?= e($ref) ?></a>
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
