<?php
declare(strict_types=1);

/**
 * Floor table rows — one <tr> per blind. Shared by the full Floor page and the
 * live-update fragment (floor.php?rows=1), so the wallboard can quietly swap in
 * fresh rows without a reload or scroll jump.
 *
 * Expects in scope: $rows, $streamsBy, $pdo, $dueTag, $fmtDate, $RT.
 */
foreach ($rows as $r):
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
