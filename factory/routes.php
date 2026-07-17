<?php
declare(strict_types=1);

/**
 * Factory · Routes.
 *
 * Per product: the streams it has, and the ordered stages in each.
 *
 * There are no benches here. John: "The benches are just noise as the product
 * has processes rather than benches." He's right — the same saw serves
 * verticals, rollers and pleateds, so naming it told us nothing useful and made
 * a "Safety Saw" queue that was nobody's job. What matters is the process: the
 * vertical's Headrail runs profile cut -> assembly, wherever the saw is today.
 *
 * Stages sharing a stream run in order. Different streams run alongside each
 * other and imply nothing about one another — that's what stops a finished
 * fabric marking an untouched headrail as done. 'main' = a single line.
 *
 * Production time per product lives here too: it sets the due date on orders
 * placed from now on, and never touches an order already taken.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/due_dates.php';
requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

$ready = true;
try { $pdo->query('SELECT 1 FROM product_route_steps LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

$hasStreams = false;
if ($ready) {
    try { $pdo->query('SELECT stream FROM product_route_steps LIMIT 0'); $hasStreams = true; }
    catch (Throwable $e) { /* not migrated */ }
}

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

/** Reassign 0..n order to a list of ids. */
$renumber = static function (PDO $pdo, array $ids): void {
    $u = $pdo->prepare('UPDATE product_route_steps SET seq = ? WHERE id = ?');
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
        if ($action === 'add_step' && $productId > 0) {
            $label  = trim((string) ($_POST['label'] ?? ''));
            $stream = trim((string) ($_POST['stream'] ?? '')) ?: 'main';
            if ($label !== '') {
                $m = $pdo->prepare('SELECT COALESCE(MAX(seq),-1)+1 FROM product_route_steps WHERE product_id = ?');
                $m->execute([$productId]);
                $seqNext = (int) $m->fetchColumn();
                if ($hasStreams) {
                    $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, stream, active) VALUES (?, ?, NULL, ?, ?, 1)')
                        ->execute([$productId, $seqNext, $label, $stream]);
                } else {
                    $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, active) VALUES (?, ?, NULL, ?, 1)')
                        ->execute([$productId, $seqNext, $label]);
                }
            } else {
                $_SESSION['flash_error'] = 'Give the stage a name — it\'s what the workshop reads.';
            }
        } elseif ($action === 'update_step' && $productId > 0) {
            $label  = trim((string) ($_POST['label'] ?? ''));
            $stream = trim((string) ($_POST['stream'] ?? '')) ?: 'main';
            if ($hasStreams) {
                $pdo->prepare('UPDATE product_route_steps SET label = ?, stream = ? WHERE id = ? AND product_id = ?')
                    ->execute([$label !== '' ? $label : null, $stream, (int) $_POST['step_id'], $productId]);
            } else {
                $pdo->prepare('UPDATE product_route_steps SET label = ? WHERE id = ? AND product_id = ?')
                    ->execute([$label !== '' ? $label : null, (int) $_POST['step_id'], $productId]);
            }
        } elseif ($action === 'remove_step') {
            $pdo->prepare('DELETE FROM product_route_steps WHERE id = ? AND product_id = ?')
                ->execute([(int) $_POST['step_id'], $productId]);
        } elseif ($action === 'move_step' && $productId > 0) {
            $s = $pdo->prepare('SELECT id FROM product_route_steps WHERE product_id = ? ORDER BY seq, id');
            $s->execute([$productId]);
            $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
            $renumber($pdo, $move($ids, (int) $_POST['step_id'], (string) $_POST['dir']));
        } elseif ($action === 'set_lead' && $productId > 0) {
            dd_set_lead_days($pdo, $productId, (int) ($_POST['lead_days'] ?? 0));
            $_SESSION['flash_success'] = 'Production time saved — it applies to orders placed from now on. Orders already placed keep the date they were promised.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /factory/routes.php' . ($productId > 0 ? '?product_id=' . $productId : ''));
    exit;
}

// ---- Load for display ------------------------------------------------------
$products = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND name LIKE 'Bev%' ORDER BY name");
$products->execute([$MASTER]);
$products = $products->fetchAll(PDO::FETCH_ASSOC);
if ($productId === 0 && $products) $productId = (int) $products[0]['id'];

$steps = [];
if ($ready && $productId > 0) {
    $streamSel = $hasStreams ? "COALESCE(NULLIF(rs.stream,''),'main') AS stream" : "'main' AS stream";
    $st = $pdo->prepare("SELECT rs.id, rs.label, $streamSel FROM product_route_steps rs
                          WHERE rs.product_id = ? AND rs.active = 1 ORDER BY rs.seq, rs.id");
    $st->execute([$productId]);
    $steps = $st->fetchAll(PDO::FETCH_ASSOC);
}

// Group into streams for display — the order within a stream is what matters.
$byStream = [];
foreach ($steps as $i => $s) { $s['_pos'] = $i; $byStream[(string) $s['stream']][] = $s; }

// Streams already used on any product, to offer as suggestions.
$knownStreams = [];
if ($hasStreams) {
    try {
        $knownStreams = $pdo->query("SELECT DISTINCT COALESCE(NULLIF(stream,''),'main') AS s FROM product_route_steps WHERE active = 1 ORDER BY s")
            ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { $knownStreams = ['main']; }
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$factoryTitle = 'Routes';
$factoryNav   = 'routes';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .rt-wrap { max-width:900px; }
  .rt-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .rt-card h2 { font-size:1rem; margin:0 0 .9rem; }
  .rt-sub { color:var(--text-muted,#667); font-size:.85rem; margin:.2rem 0 1rem; }
  .rt-flash { padding:.6rem 1rem; border-radius:10px; margin:0 0 1rem; font-size:.9rem; }
  .rt-flash.ok{ background:#dcfce7; color:#166534; } .rt-flash.err{ background:#fee2e2; color:#991b1b; }
  input[type=text], input[type=number], select { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:.4rem .55rem; background:var(--bg-input,#fff); color:inherit; }
  .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:.4rem .8rem; background:#166534; color:#fff; }
  .btn.ghost { background:#eef2f6; color:#334155; } .btn.mini{ padding:.2rem .5rem; font-size:.8rem; }
  .stream-box { border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:.7rem .9rem; margin:0 0 .9rem; background:var(--bg-subtle,#f8fafc); }
  .stream-name { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#0f766e; margin:0 0 .5rem; }
  .step-row { display:flex; align-items:center; gap:.5rem; padding:.35rem 0; border-bottom:1px solid var(--border,#eef); }
  .step-row:last-child { border-bottom:none; }
  .seq { width:1.5rem; height:1.5rem; border-radius:50%; background:#0f766e; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; flex:0 0 auto; }
  .mv { color:#64748b; text-decoration:none; padding:0 .25rem; font-weight:700; background:none; border:none; cursor:pointer; }
  .inline { display:inline; }
  .add-row { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.9rem; align-items:center; }
  .lead-row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin:0 0 1.1rem; padding:.6rem .75rem; background:var(--bg-subtle,#f8fafc); border:1px solid var(--border,#e5e7eb); border-radius:10px; }
  .lead-row label { font-weight:600; font-size:.9rem; }
</style>

<h1 style="font-size:1.5rem;margin:0 0 .3rem;">Routes</h1>
<p class="rt-sub">What each product goes through. Stages in the same <strong>stream</strong> run in order; different streams run alongside each other and mean nothing to one another &mdash; that's how a vertical's headrail and fabric stay separate. <strong>main</strong> = a single line.</p>

<?php if ($flashOk !== ''): ?><div class="rt-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="rt-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$ready): ?>
  <div class="rt-flash err">The routing tables aren't there yet — run <code>/migrate_factory_routes.php</code>.</div>
<?php else: ?>
<div class="rt-wrap">
  <div class="rt-card">
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
      <p class="rt-sub">No route yet — add the first stage below.</p>
    <?php endif; ?>

    <?php foreach ($byStream as $stream => $list): ?>
      <div class="stream-box">
        <p class="stream-name"><?= e((string) $stream) ?><?= count($byStream) > 1 ? '' : ' — single line' ?></p>
        <?php foreach ($list as $i => $st): ?>
          <div class="step-row">
            <span class="seq"><?= $i + 1 ?></span>
            <form method="post" class="inline" style="flex:1;display:flex;gap:.3rem">
              <?= csrf_field() ?><input type="hidden" name="_action" value="update_step"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>">
              <input type="text" name="label" value="<?= e((string) ($st['label'] ?? '')) ?>" placeholder="what happens here" onchange="this.form.submit()" style="flex:1" title="This is what the workshop reads on the floor.">
              <?php if ($hasStreams): ?>
                <input type="text" name="stream" value="<?= e((string) $st['stream']) ?>" list="streams" placeholder="main" onchange="this.form.submit()" style="flex:0 0 7rem" title="Stages sharing a stream run in order. Different streams run alongside each other.">
              <?php endif; ?>
            </form>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="move_step"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>"><button class="mv" name="dir" value="up">▲</button><button class="mv" name="dir" value="down">▼</button></form>
            <form method="post" class="inline" onsubmit="return confirm('Remove this stage?')"><?= csrf_field() ?><input type="hidden" name="_action" value="remove_step"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="step_id" value="<?= (int) $st['id'] ?>"><button class="btn ghost mini">✕</button></form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <datalist id="streams"><?php foreach ($knownStreams as $s): ?><option value="<?= e((string) $s) ?>"><?php endforeach; ?></datalist>

    <form method="post" class="add-row">
      <?= csrf_field() ?><input type="hidden" name="_action" value="add_step"><input type="hidden" name="product_id" value="<?= $productId ?>">
      <input type="text" name="label" placeholder="stage, e.g. tube cut" style="flex:1;min-width:10rem" required>
      <?php if ($hasStreams): ?>
        <input type="text" name="stream" list="streams" placeholder="stream (main)" style="flex:0 0 8rem">
      <?php endif; ?>
      <button class="btn">Add stage</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
