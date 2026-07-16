<?php
declare(strict_types=1);

/**
 * Factory · Routes & Stations.
 *
 * The editable production model: the machines/benches (factory_stations) and,
 * per product, the ordered run of stages it passes through (product_route_steps).
 * Change either at any time — add a station, rename it, reorder a product's
 * stages, drop a product to Outsourced. Phase B tracks each blind's live stage
 * against these routes.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/due_dates.php';
requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

$ready = true;
try { $pdo->query('SELECT 1 FROM factory_stations LIMIT 0'); $pdo->query('SELECT 1 FROM product_route_steps LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

/** Reassign 0..n order to a list of ids on the given table/column. */
$renumber = static function (PDO $pdo, string $table, string $col, array $ids): void {
    $u = $pdo->prepare("UPDATE `$table` SET `$col` = ? WHERE id = ?");
    foreach (array_values($ids) as $i => $id) $u->execute([$i, (int) $id]);
};
$move = static function (array $ids, int $id, string $dir): array {
    $p = array_search($id, $ids, true);
    if ($p === false) return $ids;
    $q = $dir === 'up' ? $p - 1 : $p + 1;
    if ($q < 0 || $q >= count($ids)) return $ids;
    [$ids[$p], $ids[$q]] = [$ids[$q], $ids[$p]];
    return $ids;
};

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'add_station') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name !== '') {
                $mx = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),-1)+1 FROM factory_stations WHERE client_id = {$MASTER}")->fetchColumn();
                $pdo->prepare('INSERT INTO factory_stations (client_id, name, sort_order, is_outsourced, active) VALUES (?, ?, ?, ?, 1)')
                    ->execute([$MASTER, $name, $mx, !empty($_POST['is_outsourced']) ? 1 : 0]);
                $_SESSION['flash_success'] = 'Station added.';
            }
        } elseif ($action === 'rename_station') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name !== '') $pdo->prepare('UPDATE factory_stations SET name = ? WHERE id = ? AND client_id = ?')->execute([$name, (int) $_POST['id'], $MASTER]);
        } elseif ($action === 'toggle_station') {
            $pdo->prepare('UPDATE factory_stations SET active = 1 - active WHERE id = ? AND client_id = ?')->execute([(int) $_POST['id'], $MASTER]);
        } elseif ($action === 'move_station') {
            $ids = array_map('intval', $pdo->query("SELECT id FROM factory_stations WHERE client_id = {$MASTER} ORDER BY sort_order, id")->fetchAll(PDO::FETCH_COLUMN));
            $renumber($pdo, 'factory_stations', 'sort_order', $move($ids, (int) $_POST['id'], (string) $_POST['dir']));
        } elseif ($action === 'delete_station') {
            $sid = (int) $_POST['id'];
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM product_route_steps WHERE station_id = ?'); $cnt->execute([$sid]);
            if ((int) $cnt->fetchColumn() > 0) {
                $_SESSION['flash_error'] = 'That station is used in a route — remove it from the routes first, or just deactivate it.';
            } else {
                $pdo->prepare('DELETE FROM factory_stations WHERE id = ? AND client_id = ?')->execute([$sid, $MASTER]);
                $_SESSION['flash_success'] = 'Station deleted.';
            }
        } elseif ($action === 'add_step' && $productId > 0) {
            $stationId = (int) ($_POST['station_id'] ?? 0);
            $label = trim((string) ($_POST['label'] ?? ''));
            if ($stationId > 0) {
                $m = $pdo->prepare('SELECT COALESCE(MAX(seq),-1)+1 FROM product_route_steps WHERE product_id = ?'); $m->execute([$productId]);
                $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, active) VALUES (?, ?, ?, ?, 1)')
                    ->execute([$productId, (int) $m->fetchColumn(), $stationId, $label !== '' ? $label : null]);
            }
        } elseif ($action === 'remove_step') {
            $pdo->prepare('DELETE FROM product_route_steps WHERE id = ? AND product_id = ?')->execute([(int) $_POST['step_id'], $productId]);
        } elseif ($action === 'set_lead' && $productId > 0) {
            dd_set_lead_days($pdo, $productId, (int) ($_POST['lead_days'] ?? 0));
            $_SESSION['flash_success'] = 'Production time saved — it applies to orders placed from now on. Orders already placed keep the date they were promised.';
        } elseif ($action === 'move_step' && $productId > 0) {
            $s = $pdo->prepare('SELECT id FROM product_route_steps WHERE product_id = ? ORDER BY seq, id'); $s->execute([$productId]);
            $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
            $renumber($pdo, 'product_route_steps', 'seq', $move($ids, (int) $_POST['step_id'], (string) $_POST['dir']));
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /factory/routes.php' . ($productId > 0 ? '?product_id=' . $productId : ''));
    exit;
}

// ---- Load for display ------------------------------------------------------
$stations = $ready ? $pdo->query("SELECT * FROM factory_stations WHERE client_id = {$MASTER} ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC) : [];
$activeStations = array_values(array_filter($stations, static fn ($s) => (int) $s['active'] === 1));

$products = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND name LIKE 'Bev%' ORDER BY name");
$products->execute([$MASTER]);
$products = $products->fetchAll(PDO::FETCH_ASSOC);
if ($productId === 0 && $products) $productId = (int) $products[0]['id'];

$steps = [];
if ($ready && $productId > 0) {
    $st = $pdo->prepare(
        'SELECT rs.id, rs.label, s.name AS station, s.is_outsourced
           FROM product_route_steps rs JOIN factory_stations s ON s.id = rs.station_id
          WHERE rs.product_id = ? ORDER BY rs.seq, rs.id'
    );
    $st->execute([$productId]);
    $steps = $st->fetchAll(PDO::FETCH_ASSOC);
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$factoryTitle = 'Routes';
$factoryNav   = 'routes';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .rt-wrap { display:grid; grid-template-columns:320px 1fr; gap:1.4rem; align-items:start; max-width:1100px; }
  @media (max-width:820px){ .rt-wrap{ grid-template-columns:1fr; } }
  .rt-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .rt-card h2 { font-size:1rem; margin:0 0 .9rem; }
  .rt-sub { color:var(--text-muted,#667); font-size:.85rem; margin:.2rem 0 1rem; }
  .rt-flash { padding:.6rem 1rem; border-radius:10px; margin:0 0 1rem; font-size:.9rem; }
  .rt-flash.ok{ background:#dcfce7; color:#166534; } .rt-flash.err{ background:#fee2e2; color:#991b1b; }
  input[type=text], select { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:.4rem .55rem; background:var(--bg-input,#fff); color:inherit; }
  .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:.4rem .8rem; background:#166534; color:#fff; }
  .btn.ghost { background:#eef2f6; color:#334155; } .btn.mini{ padding:.2rem .5rem; font-size:.8rem; }
  .st-row, .step-row { display:flex; align-items:center; gap:.5rem; padding:.4rem 0; border-bottom:1px solid var(--border,#eef); }
  .st-row:last-child, .step-row:last-child { border-bottom:none; }
  .st-name { flex:1; }
  .pill { font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:2px 7px; border-radius:999px; }
  .pill.out{ background:#fef3c7; color:#92400e; } .pill.off{ background:#e5e7eb; color:#6b7280; }
  .seq { width:1.6rem; height:1.6rem; border-radius:50%; background:#0f766e; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; flex:0 0 auto; }
  .mv { color:#64748b; text-decoration:none; padding:0 .25rem; font-weight:700; }
  .inline { display:inline; }
  .add-row { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.9rem; }
  .lead-row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin:0 0 1.1rem; padding:.6rem .75rem; background:var(--bg-subtle,#f8fafc); border:1px solid var(--border,#e5e7eb); border-radius:10px; }
  .lead-row label { font-weight:600; font-size:.9rem; }
</style>

<h1 style="font-size:1.5rem;margin:0 0 .3rem;">Routes &amp; Stations</h1>
<p class="rt-sub">The machines &amp; benches on the floor, and the run of stages each product passes through. Edit any of it, any time.</p>

<?php if ($flashOk !== ''): ?><div class="rt-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="rt-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$ready): ?>
  <div class="rt-flash err">The routing tables aren't there yet — run <code>/migrate_factory_routes.php</code> then <code>/seed_factory_routes.php</code>.</div>
<?php else: ?>
<div class="rt-wrap">

  <!-- Stations -->
  <div class="rt-card">
    <h2>Stations</h2>
    <?php foreach ($stations as $i => $s): $off = (int) $s['active'] !== 1; ?>
      <div class="st-row" style="<?= $off ? 'opacity:.55' : '' ?>">
        <form method="post" class="inline" style="display:flex;gap:.3rem;flex:1">
          <?= csrf_field() ?><input type="hidden" name="_action" value="rename_station"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <input type="text" name="name" value="<?= e((string) $s['name']) ?>" class="st-name" onchange="this.form.submit()">
        </form>
        <?php if ((int) $s['is_outsourced'] === 1): ?><span class="pill out">buy-in</span><?php endif; ?>
        <?php if ($off): ?><span class="pill off">off</span><?php endif; ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="move_station"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="mv" name="dir" value="up" title="up">▲</button><button class="mv" name="dir" value="down" title="down">▼</button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="toggle_station"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn ghost mini"><?= $off ? 'On' : 'Off' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete this station?')"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_station"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn ghost mini" title="delete">✕</button></form>
      </div>
    <?php endforeach; ?>
    <form method="post" class="add-row">
      <?= csrf_field() ?><input type="hidden" name="_action" value="add_station">
      <input type="text" name="name" placeholder="New station name" style="flex:1;min-width:8rem" required>
      <label style="display:inline-flex;align-items:center;gap:.3rem;font-size:.85rem;color:var(--text-muted,#667)"><input type="checkbox" name="is_outsourced" value="1"> buy-in</label>
      <button class="btn">Add</button>
    </form>
  </div>

  <!-- Routes -->
  <div class="rt-card">
    <h2>Product route</h2>
    <form method="get" action="/factory/routes.php" style="margin:0 0 1rem">
      <select name="product_id" onchange="this.form.submit()">
        <?php foreach ($products as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $productId ? 'selected' : '' ?>><?= e((string) $p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if (dd_ready($pdo) && $productId > 0): ?>
      <form method="post" class="lead-row">
        <?= csrf_field() ?><input type="hidden" name="_action" value="set_lead"><input type="hidden" name="product_id" value="<?= $productId ?>">
        <label for="lead_days">Production time</label>
        <input type="number" id="lead_days" name="lead_days" value="<?= (int) dd_lead_days($pdo, $productId) ?>" min="0" max="365" step="1" style="width:4.5rem">
        <span class="rt-sub" style="margin:0">working days</span>
        <button class="btn mini">Save</button>
        <p class="rt-sub" style="margin:.35rem 0 0;flex-basis:100%">Turn this up when the workshop is busy. It sets the due date on orders placed <strong>from now on</strong> — orders already placed keep the date they were promised.</p>
      </form>
    <?php endif; ?>

    <?php if (!$steps): ?>
      <p class="rt-sub">No route set yet — add the first stage below (or leave empty for a buy-in product).</p>
    <?php endif; ?>
    <?php foreach ($steps as $i => $st): ?>
      <div class="step-row">
        <span class="seq"><?= $i + 1 ?></span>
        <span style="flex:1"><strong><?= e((string) $st['station']) ?></strong><?php if ($st['label']): ?> <span style="color:var(--text-muted,#667);font-size:.85rem;">· <?= e((string) $st['label']) ?></span><?php endif; ?><?php if ((int) $st['is_outsourced'] === 1): ?> <span class="pill out">buy-in</span><?php endif; ?></span>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="move_step"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>"><button class="mv" name="dir" value="up">▲</button><button class="mv" name="dir" value="down">▼</button></form>
        <form method="post" class="inline" onsubmit="return confirm('Remove this stage?')"><?= csrf_field() ?><input type="hidden" name="_action" value="remove_step"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>"><button class="btn ghost mini">✕</button></form>
      </div>
    <?php endforeach; ?>

    <form method="post" class="add-row">
      <?= csrf_field() ?><input type="hidden" name="_action" value="add_step"><input type="hidden" name="product_id" value="<?= $productId ?>">
      <select name="station_id" required>
        <option value="">Add a stage…</option>
        <?php foreach ($activeStations as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="label" placeholder="operation (optional, e.g. tube cut)" style="flex:1;min-width:9rem">
      <button class="btn">Add stage</button>
    </form>
  </div>

</div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
