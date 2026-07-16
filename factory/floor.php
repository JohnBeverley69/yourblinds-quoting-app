<?php
declare(strict_types=1);

/**
 * Factory · Production Floor (Phase B).
 *
 * The live board: every active station is a column holding the blinds sitting
 * there right now, plus an "unrouted" catch-all and a recently-completed strip.
 * Blinds land here when an order is moved to "in production" on Incoming Orders,
 * then get Started / Done'd along their route. This is the master's watch-the-
 * floor view; a maker who only cares about one bench can open that column.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();
$ready  = bj_tables_ready($pdo);

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$stations   = [];
$byStation  = [];   // station_id => [rows]
$unrouted   = [];   // on the floor but product has no route
$completed  = [];   // recent completions
$activeTot  = 0;

if ($ready) {
    // Active stations, in floor order.
    $stations = $pdo->query(
        "SELECT id, name, is_outsourced FROM factory_stations
          WHERE client_id = {$MASTER} AND active = 1 ORDER BY sort_order, id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $sel =
        "SELECT bj.id, bj.quote_id, bj.station_id, bj.status, bj.seq, bj.completed_at,
                q.quote_number, c.company_name AS tenant,
                qi.product_name_snapshot, qi.system_name_snapshot,
                qi.fabric_name_snapshot, qi.fabric_colour_snapshot,
                qi.width_mm, qi.drop_mm, qi.quantity, qi.room_name,
                rs.label AS step_label
           FROM factory_blind_jobs bj
           JOIN quotes q       ON q.id = bj.quote_id
           JOIN clients c      ON c.id = q.client_id
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           LEFT JOIN product_route_steps rs ON rs.id = bj.route_step_id";

    // On the floor now.
    $live = $pdo->query(
        $sel . " WHERE bj.status IN ('queued','in_progress')
                 ORDER BY bj.seq, q.created_at, bj.id"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($live as $row) {
        $activeTot++;
        $sid = $row['station_id'] !== null ? (int) $row['station_id'] : 0;
        if ($sid === 0) { $unrouted[] = $row; } else { $byStation[$sid][] = $row; }
    }

    // Recently finished (last 20).
    $completed = $pdo->query(
        $sel . " WHERE bj.status = 'complete' ORDER BY bj.completed_at DESC, bj.id DESC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
}

$factoryTitle = 'Production Floor';
$factoryNav   = 'floor';
require __DIR__ . '/../_partials/factory_head.php';
require __DIR__ . '/../_partials/blind_styles.php';
require __DIR__ . '/../_partials/blind_card.php';
$RT = '/factory/floor.php';
?>

<div class="fl-head">
    <h1 class="fl-h1">Production Floor</h1>
    <?php if ($ready): ?>
        <span class="fl-stat"><b><?= (int) $activeTot ?></b> on the floor<?php if ($completed): ?> &middot; <b><?= count($completed) ?></b> recently made<?php endif; ?></span>
    <?php endif; ?>
</div>
<p class="fl-sub">Every blind that's in production, sitting at the stage it's on. Move it along with <strong>Done &rarr;</strong> as each stage finishes.</p>

<?php if ($flashOk !== ''): ?><div class="fl-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="fl-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$ready): ?>
    <div class="fl-flash err">Floor tracking isn't set up yet — run <code>/migrate_factory_blind_jobs.php</code>, then move an order to <em>in production</em> on Incoming Orders.</div>
<?php elseif ($activeTot === 0 && !$completed): ?>
    <div class="fl-empty">Nothing on the floor yet. Open <a href="/factory/incoming-orders.php">Incoming Orders</a> and press <strong>Start production</strong> on an order — its blinds will appear here.</div>
<?php else: ?>
    <div class="fl-board">
        <?php foreach ($stations as $s):
            $sid  = (int) $s['id'];
            $rows = $byStation[$sid] ?? [];
            $out  = (int) $s['is_outsourced'] === 1;
        ?>
            <div class="fl-col<?= $out ? ' out' : '' ?>">
                <div class="fl-col-head">
                    <a href="/factory/station.php?station_id=<?= $sid ?>"><h2><?= e((string) $s['name']) ?></h2></a>
                    <?php if ($out): ?><span class="pill out">buy-in</span><?php endif; ?>
                    <span class="fl-count"><?= count($rows) ?></span>
                </div>
                <div class="fl-col-body">
                    <?php if (!$rows): ?>
                        <div class="fl-col-empty">—</div>
                    <?php else: foreach ($rows as $row) bj_render_card($row, $RT); endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($unrouted): ?>
            <div class="fl-col out">
                <div class="fl-col-head">
                    <h2>No route</h2>
                    <span class="fl-count"><?= count($unrouted) ?></span>
                </div>
                <div class="fl-col-body">
                    <?php foreach ($unrouted as $row) bj_render_card($row, $RT); ?>
                    <p class="fl-col-empty" style="text-align:left">These products have no route — set one on <a href="/factory/routes.php">Routes</a>, then they'll flow.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($completed): ?>
            <div class="fl-col done">
                <div class="fl-col-head">
                    <h2>Recently made</h2>
                    <span class="fl-count"><?= count($completed) ?></span>
                </div>
                <div class="fl-col-body">
                    <?php foreach ($completed as $row): ?>
                        <div class="bcard" style="opacity:.85">
                            <div class="bcard-top">
                                <span class="bcard-ref"><?= e((string) ($row['quote_number'] ?? '')) ?></span>
                                <?php if ((int) $row['quantity'] > 1): ?><span class="bcard-qty">&times;<?= (int) $row['quantity'] ?></span><?php endif; ?>
                                <span class="bcard-live" style="margin-left:auto;color:#166534;background:#dcfce7">made</span>
                            </div>
                            <div class="bcard-prod"><?= e((string) ($row['product_name_snapshot'] ?? '')) ?></div>
                            <div class="bcard-meta"><span class="bcard-size"><?= (int) $row['width_mm'] ?> &times; <?= (int) $row['drop_mm'] ?></span><?php if (trim((string) $row['room_name']) !== ''): ?><span><?= e((string) $row['room_name']) ?></span><?php endif; ?></div>
                            <div class="bcard-actions">
                                <form method="post" action="/factory/blind-action.php" class="bc-inline">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="back">
                                    <input type="hidden" name="job_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="return_to" value="<?= e($RT) ?>">
                                    <button class="bc-btn ghost">&larr; Reopen</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
