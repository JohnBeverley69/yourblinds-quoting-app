<?php
declare(strict_types=1);

/**
 * Factory · Station queue (Phase B).
 *
 * One station's worklist — the roomy view for a maker standing at a bench who
 * only cares about what's waiting for them. Same Start / Done / step-back
 * actions as the floor board, just focused on a single station.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();
$ready  = bj_tables_ready($pdo);

$stationId = (int) ($_GET['station_id'] ?? 0);

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$station = null;
$rows    = [];
if ($ready && $stationId > 0) {
    $s = $pdo->prepare("SELECT id, name, is_outsourced FROM factory_stations WHERE id = ? AND client_id = ?");
    $s->execute([$stationId, $MASTER]);
    $station = $s->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($station) {
        $q = $pdo->prepare(
            "SELECT bj.id, bj.quote_id, bj.status, bj.seq, bj.unit_no,
                    q.quote_number, c.company_name AS tenant,
                    qi.product_name_snapshot, qi.system_name_snapshot,
                    qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
                    qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name,
                    rs.label AS step_label
               FROM factory_blind_jobs bj
               JOIN quotes q       ON q.id = bj.quote_id
               JOIN clients c      ON c.id = q.client_id
               JOIN quote_items qi ON qi.id = bj.quote_item_id
               LEFT JOIN product_route_steps rs ON rs.id = bj.route_step_id
              WHERE bj.station_id = ? AND bj.status IN ('queued','in_progress')
              ORDER BY bj.status DESC, q.created_at, bj.quote_item_id, bj.unit_no, bj.id"
        );
        $q->execute([$stationId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

$factoryTitle = $station ? (string) $station['name'] : 'Station';
$factoryNav   = 'floor';
$factoryWide  = true;   // bench view: more cards across a big screen, fewer scrolls
require __DIR__ . '/../_partials/factory_head.php';
require __DIR__ . '/../_partials/blind_styles.php';
require __DIR__ . '/../_partials/blind_card.php';
$RT = '/factory/station.php?station_id=' . $stationId;
?>

<div class="fl-head">
    <a href="/factory/floor.php" style="text-decoration:none;color:var(--text-muted,#667);font-size:.9rem">&larr; Floor</a>
    <h1 class="fl-h1"><?= e($station ? (string) $station['name'] : 'Station') ?></h1>
    <?php if ($station): ?>
        <?php if ((int) $station['is_outsourced'] === 1): ?><span class="pill out">buy-in</span><?php endif; ?>
        <span class="fl-stat"><b><?= count($rows) ?></b> waiting</span>
    <?php endif; ?>
</div>

<?php if ($flashOk !== ''): ?><div class="fl-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="fl-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$ready): ?>
    <div class="fl-flash err">Floor tracking isn't set up yet — run <code>/migrate_factory_blind_jobs.php</code>.</div>
<?php elseif (!$station): ?>
    <div class="fl-empty">Station not found. <a href="/factory/floor.php">Back to the floor</a>.</div>
<?php elseif (!$rows): ?>
    <div class="fl-empty">Nothing waiting at this station right now.</div>
<?php else: ?>
    <div class="fl-queue">
        <?php foreach ($rows as $row) bj_render_card($row, $RT); ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
