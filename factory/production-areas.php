<?php
declare(strict_types=1);

/**
 * Factory · Production areas.
 *
 * The workshop's production areas (e.g. Vertical / Roller / Pleated), and which
 * product is made in which area. When an order lands, its blinds split to their
 * area automatically off the product. Areas are fully editable — add, rename,
 * reorder, remove — and each product is assigned to exactly one area.
 *
 * Foundation only (Phase A): this sets up the data. Per-area floor/scan views and
 * the marshalling/dispatch flow build on top of it.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';   // bj_streams_ordered — a product's routes
requireFactory();

$pdo    = db();
$MASTER = current_factory_id();

require_once __DIR__ . '/../_partials/factory_kv.php';   // fx_kv_get / fx_kv_set

$scanHost = (string) ($_SERVER['HTTP_HOST'] ?? 'yourblinds.uk');

/**
 * The path+query a bench scanner calls. ONE definition — the URL box, the AT
 * provisioning string and the QR of it are all built from this, so what you
 * scan can never drift from what you copy.
 */
$scanPathFor = static function (array $ar): string {
    $key   = (string) ($ar['scan_key'] ?? '');
    $sname = trim((string) ($ar['scanner_name'] ?? ''));
    return '/factory/scan-in.php?key=' . $key
         . ($sname !== '' ? '&s=' . rawurlencode($sname) : '')
         . '&c={CODE}';
};
$scanUrlFor = static function (array $ar) use ($scanPathFor, $scanHost): string {
    return 'https://' . $scanHost . $scanPathFor($ar);
};

/**
 * The scanner's provisioning string — what its WiFi-configuration QR carries.
 * Two AT commands, semicolon-separated:
 *
 *   AT+URL=1,<host>,<path+query>;AT+RAP=<ssid>,<password>
 *
 * AT+URL takes three COMMA-separated arguments: the protocol selector (1 =
 * https), the host, and the path with its leading slash. AT+RAP carries the
 * network the scanner joins, SSID first.
 *
 * This used to join them with dots — "AT+URL=1.host./path" — and the scanner
 * refused every code built that way while giving no clue why. Settled by
 * decoding the vendor tool's own QR for the same bench and diffing: 129 bytes
 * each, identical but for those two separators. So if this ever has to change
 * again, don't reason about it — generate one in the vendor's app and compare.
 * The Setup QR popup shows our string for exactly that purpose.
 *
 * Commas therefore separate BOTH commands, which is why set_wifi refuses an
 * SSID or password containing one. Host and path are safe: the host comes from
 * the request, and the path's only free text is the scanner name, which
 * rawurlencode turns into %2C.
 *
 * The literal {CODE} stays in: the scanner substitutes the barcode it reads.
 */
$scanAtFor = static function (array $ar, string $ssid, string $pass) use ($scanPathFor, $scanHost): string {
    return 'AT+URL=1,' . $scanHost . ',' . $scanPathFor($ar)
         . ';AT+RAP=' . $ssid . ',' . $pass;
};

// ---- ?qr=<area id> — that area's scanner URL as a QR code -------------------
// Rendered here rather than by a third-party generator: the URL carries the
// area's scan KEY, and pasting a secret into someone else's website to get a
// picture back is the one thing you never want in a setup flow. We already
// have the encoder (_partials/qr.php over the vendored _lib/qrcode), so this
// costs nothing and keeps the app self-contained.
//
// Served on demand instead of inlined because a ~106-character scanner URL is a
// 53x53 symbol — about 40kB of SVG — which would be added to the page once per
// bench. As its own response the browser caches it and the page stays light.
// Behind the same requireFactory() guard as the rest of the page.
if (isset($_GET['qr'])) {
    require_once __DIR__ . '/../_partials/qr.php';
    $row = null;
    try {
        $st = $pdo->prepare('SELECT * FROM production_areas WHERE id = ? AND client_id = ? LIMIT 1');
        $st->execute([(int) $_GET['qr'], $MASTER]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { $row = null; }

    $qrSsid = (string) fx_kv_get($pdo, 'wifi_ssid', '');
    $qrPass = (string) fx_kv_get($pdo, 'wifi_password', '');

    if (!$row || trim((string) ($row['scan_key'] ?? '')) === '' || trim($qrSsid) === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Need both a scan key for that area and the workshop WiFi set.\n");
    }

    header('Content-Type: image/svg+xml; charset=utf-8');
    // It encodes the scan key AND the WiFi password — keep it out of shared
    // caches, proxies and browser history.
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');
    // ECC M, not the label default of Q: this is read off a screen rather than
    // a greasy workshop label, and the lower level keeps the symbol smaller, so
    // each module stays fatter at the same physical size.
    echo qr_svg($scanAtFor($row, $qrSsid, $qrPass), 44.0, 'M');
    exit;
}

$ready = true;
try { $pdo->query('SELECT 1 FROM production_areas LIMIT 0'); }
catch (Throwable $e) { $ready = false; }

/** Reassign 0..n order to a list of area ids. */
$renumber = static function (PDO $pdo, array $ids): void {
    $u = $pdo->prepare('UPDATE production_areas SET sort_order = ? WHERE id = ?');
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
/** Confirm an area belongs to this factory before mutating it (IDOR guard). */
$ownArea = static function (PDO $pdo, int $areaId, int $factory): bool {
    if ($areaId <= 0) return false;
    $s = $pdo->prepare('SELECT 1 FROM production_areas WHERE id = ? AND client_id = ?');
    $s->execute([$areaId, $factory]);
    return (bool) $s->fetchColumn();
};

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'add_area') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $_SESSION['flash_error'] = 'Give the area a name.';
            } else {
                $m = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1)+1 FROM production_areas WHERE client_id = ?');
                $m->execute([$MASTER]);
                $pdo->prepare('INSERT INTO production_areas (client_id, name, sort_order, active) VALUES (?, ?, ?, 1)')
                    ->execute([$MASTER, $name, (int) $m->fetchColumn()]);
                $_SESSION['flash_success'] = 'Area added.';
            }
        } elseif ($action === 'rename_area') {
            $id = (int) ($_POST['area_id'] ?? 0); $name = trim((string) ($_POST['name'] ?? ''));
            if ($ownArea($pdo, $id, $MASTER) && $name !== '') {
                $pdo->prepare('UPDATE production_areas SET name = ? WHERE id = ? AND client_id = ?')->execute([$name, $id, $MASTER]);
            }
        } elseif ($action === 'move_area') {
            $s = $pdo->prepare('SELECT id FROM production_areas WHERE client_id = ? ORDER BY sort_order, id');
            $s->execute([$MASTER]);
            $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
            $renumber($pdo, $move($ids, (int) ($_POST['area_id'] ?? 0), (string) ($_POST['dir'] ?? '')));
        } elseif ($action === 'remove_area') {
            $id = (int) ($_POST['area_id'] ?? 0);
            if ($ownArea($pdo, $id, $MASTER)) {
                $pdo->prepare('DELETE FROM product_area_map WHERE area_id = ?')->execute([$id]);
                try { $pdo->prepare('DELETE FROM user_production_areas WHERE area_id = ?')->execute([$id]); } catch (Throwable $e) {}
                $pdo->prepare('DELETE FROM production_areas WHERE id = ? AND client_id = ?')->execute([$id, $MASTER]);
                $_SESSION['flash_success'] = 'Area removed.';
            }
        } elseif ($action === 'set_wifi') {
            // The network every bench scanner joins. Factory-wide, so it lives in
            // factory_kv alongside the scan secret rather than per area.
            $ssid = trim((string) ($_POST['wifi_ssid'] ?? ''));
            $pass = (string) ($_POST['wifi_password'] ?? '');
            // AT+RAP=<ssid>,<password> is comma-separated and the command list is
            // semicolon-separated, so either character would split the string and
            // silently mis-provision the scanner. Refuse rather than mangle.
            if (strpbrk($ssid, ',;') !== false || strpbrk($pass, ',;') !== false) {
                $_SESSION['flash_error'] = 'WiFi name and password cannot contain a comma or semicolon — the scanner\'s setup command uses both as separators.';
            } else {
                fx_kv_set($pdo, 'wifi_ssid', $ssid);
                // Blank password box = leave the stored one alone, so saving a
                // corrected SSID doesn't wipe the password you can't see.
                if ($pass !== '') fx_kv_set($pdo, 'wifi_password', $pass);
                $_SESSION['flash_success'] = 'Workshop WiFi saved — the setup QR codes now carry it.';
            }
        } elseif ($action === 'gen_scan_key') {
            $id = (int) ($_POST['area_id'] ?? 0);
            if ($ownArea($pdo, $id, $MASTER)) {
                $newKey = bin2hex(random_bytes(12));   // 24 hex chars
                $pdo->prepare('UPDATE production_areas SET scan_key = ? WHERE id = ? AND client_id = ?')->execute([$newKey, $id, $MASTER]);
                $_SESSION['flash_success'] = 'Scan key generated — point that area\'s scanner at the URL shown.';
            }
        } elseif ($action === 'clear_scan_key') {
            $id = (int) ($_POST['area_id'] ?? 0);
            if ($ownArea($pdo, $id, $MASTER)) {
                $pdo->prepare('UPDATE production_areas SET scan_key = NULL WHERE id = ? AND client_id = ?')->execute([$id, $MASTER]);
                $_SESSION['flash_success'] = 'Scan key removed — that area\'s scanner will stop working until you generate a new one.';
            }
        } elseif ($action === 'set_scanner_name') {
            // Name the scanner/bench for an area — baked into its scan-in URL as &s=
            // so the scan log shows which bench a scan came from.
            $id = (int) ($_POST['area_id'] ?? 0);
            $nm = trim((string) ($_POST['scanner_name'] ?? ''));
            if ($ownArea($pdo, $id, $MASTER)) {
                $pdo->prepare('UPDATE production_areas SET scanner_name = ? WHERE id = ? AND client_id = ?')
                    ->execute([$nm !== '' ? mb_substr($nm, 0, 60) : null, $id, $MASTER]);
                $_SESSION['flash_success'] = 'Scanner name saved.';
            }
        } elseif ($action === 'set_product_area') {
            // Assign one product ROUTE (stream) to an area, or clear it. A single-route
            // product uses stream 'main'; a vertical has 'Headrail' and 'Fabric', each
            // assignable to a different area. Product must be factory-owned.
            $pid    = (int) ($_POST['product_id'] ?? 0);
            $aid    = (int) ($_POST['area_id'] ?? 0);
            $stream = trim((string) ($_POST['stream'] ?? 'main'));
            if ($stream === '') $stream = 'main';
            if ($pid > 0 && factory_owns_product($pdo, $pid, $MASTER)) {
                if ($aid === 0) {
                    $pdo->prepare('DELETE FROM product_area_map WHERE product_id = ? AND stream = ?')->execute([$pid, $stream]);
                } elseif ($ownArea($pdo, $aid, $MASTER)) {
                    // One area per (product, stream): replace any existing mapping.
                    $pdo->prepare('INSERT INTO product_area_map (area_id, product_id, stream) VALUES (?, ?, ?)
                                   ON DUPLICATE KEY UPDATE area_id = VALUES(area_id)')->execute([$aid, $pid, $stream]);
                }
                // Re-stamp this route's blinds already on the floor so the change
                // takes effect at once (release also stamps, but existing orders
                // won't re-release on their own). Guarded on the Phase E column.
                try {
                    $pdo->prepare(
                        "UPDATE factory_blind_streams s
                           JOIN factory_blind_jobs j ON j.id = s.blind_job_id
                            SET s.area_id = ?
                          WHERE j.product_id = ? AND COALESCE(NULLIF(s.stream,''),'main') = ?"
                    )->execute([$aid > 0 ? $aid : null, $pid, $stream]);
                } catch (Throwable $e) { /* factory_blind_streams.area_id not migrated yet */ }
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /factory/production-areas.php');
    exit;
}

// ---- Load for display ------------------------------------------------------
// The scan_key column arrives with Phase B, scanner_name + per-stream map with
// Phase E — degrade gracefully if not migrated.
$hasScanKey = $hasScannerName = $hasStream = false;
if ($ready) {
    try { $pdo->query('SELECT scan_key FROM production_areas LIMIT 0'); $hasScanKey = true; } catch (Throwable $e) {}
    try { $pdo->query('SELECT scanner_name FROM production_areas LIMIT 0'); $hasScannerName = true; } catch (Throwable $e) {}
    try { $pdo->query('SELECT stream FROM product_area_map LIMIT 0'); $hasStream = true; } catch (Throwable $e) {}
}

$areas = [];
$productArea = [];   // "product_id:stream" => area_id
if ($ready) {
    $cols = 'id, name' . ($hasScanKey ? ', scan_key' : '') . ($hasScannerName ? ', scanner_name' : '');
    $a = $pdo->prepare("SELECT $cols FROM production_areas WHERE client_id = ? ORDER BY sort_order, id");
    $a->execute([$MASTER]);
    $areas = $a->fetchAll(PDO::FETCH_ASSOC);
    $mapCols = $hasStream ? 'product_id, stream, area_id' : "product_id, 'main' AS stream, area_id";
    foreach ($pdo->query("SELECT $mapCols FROM product_area_map")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $productArea[(int) $r['product_id'] . ':' . (string) $r['stream']] = (int) $r['area_id'];
    }
}
// Factory's own (master) products — the things it makes.
$products = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? ORDER BY name");
$products->execute([$MASTER]);
$products = $products->fetchAll(PDO::FETCH_ASSOC);

// Each product's routes (streams). A single-route product → ['main'] (shown as one
// row); a vertical → ['Headrail','Fabric'] (a row each, assignable separately).
$productStreams = [];
foreach ($products as $p) {
    $pid = (int) $p['id'];
    $productStreams[$pid] = bj_streams_ordered($pdo, $pid) ?: ['main'];
}

// Per-area count of distinct PRODUCTS (not per-stream rows) for the summary.
$areaCounts = [];
$seenPA = [];
foreach ($productArea as $key => $aid) {
    $pidStr = explode(':', $key, 2)[0];
    $tag = $aid . ':' . $pidStr;
    if (isset($seenPA[$tag])) continue;
    $seenPA[$tag] = true;
    $areaCounts[$aid] = ($areaCounts[$aid] ?? 0) + 1;
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$factoryTitle = 'Production areas';
$factoryNav   = 'areas';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .pa-wrap { max-width:900px; }
  .pa-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04); margin:0 0 1.1rem; }
  .pa-card h2 { font-size:1rem; margin:0 0 .3rem; }
  .pa-sub { color:var(--text-muted,#667); font-size:.85rem; margin:.2rem 0 1rem; line-height:1.5; }
  .pa-flash { padding:.6rem 1rem; border-radius:10px; margin:0 0 1rem; font-size:.9rem; }
  .pa-flash.ok{ background:#dcfce7; color:#166534; } .pa-flash.err{ background:#fee2e2; color:#991b1b; }
  input[type=text], select { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:.4rem .55rem; background:var(--bg-input,#fff); color:inherit; }
  .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:.4rem .8rem; background:#166534; color:#fff; }
  .btn.ghost { background:#eef2f6; color:#334155; } .btn.mini{ padding:.2rem .5rem; font-size:.8rem; }
  /* wrap + min-width:0 so a long scan URL folds onto the next line instead of
     setting the row's width and pushing the page off the side of the screen. */
  .area-row { display:flex; align-items:center; gap:.5rem; padding:.4rem 0; border-bottom:1px solid var(--border,#eef); flex-wrap:wrap; }
  .area-row > * { min-width:0; }
  .area-row:last-child { border-bottom:none; }
  .seq { width:1.6rem; height:1.6rem; border-radius:50%; background:#0f766e; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; flex:0 0 auto; }
  .count { color:var(--text-muted,#667); font-size:.8rem; }
  .mv { color:#64748b; padding:0 .25rem; font-weight:700; background:none; border:none; cursor:pointer; }
  .inline { display:inline; }
  .add-row { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.9rem; align-items:center; }
  table.prods { width:100%; border-collapse:collapse; }
  table.prods td { padding:.4rem .3rem; border-bottom:1px solid var(--border,#eef); }
  table.prods td.pname { font-weight:600; }
  .scan-row { display:flex; align-items:center; gap:.5rem; padding:.45rem 0; border-bottom:1px solid var(--border,#eef); flex-wrap:wrap; }
  .scan-row:last-child { border-bottom:none; }
  .scan-name { font-weight:600; min-width:9rem; }
  .scan-url { flex:1 1 16rem; min-width:0; font-family:ui-monospace,Consolas,monospace; font-size:.8rem; padding:.35rem .5rem; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; background:var(--bg-subtle,#f8fafc); color:inherit; }
  .scan-none { color:var(--text-muted,#667); font-size:.85rem; }
  .wifi-bar { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin:0 0 .7rem;
              padding:.55rem .65rem; border:1px solid var(--border,#eef); border-radius:10px;
              background:var(--bg-subtle,#f8fafc); }
  .wifi-lab { font-weight:600; font-size:.85rem; margin-right:.2rem; }
  .wifi-bar input { font:inherit; font-size:.82rem; padding:.3rem .45rem; border-radius:7px;
                    border:1px solid var(--border-strong,#cbd5e1); background:var(--bg-card,#fff); color:inherit; }
  /* QR of the scanner URL. <details> so it costs nothing until asked for, and
     needs no JavaScript. The marker is suppressed so <summary> can wear .btn. */
  .scan-qr { position:relative; }
  .scan-qr > summary { list-style:none; cursor:pointer; }
  .scan-qr > summary::-webkit-details-marker { display:none; }
  .scan-qr[open] > summary { outline:2px solid var(--link,#175cd3); outline-offset:1px; }
  .scan-qr-pop { position:absolute; z-index:20; right:0; margin-top:.4rem; width:13rem;
                 display:flex; flex-direction:column; gap:.5rem; align-items:center;
                 padding:.6rem; border:1px solid var(--border-strong,#cbd5e1); border-radius:10px;
                 background:var(--bg-card,#fff); box-shadow:0 8px 24px rgba(0,0,0,.14); }
  .scan-qr-pop img { width:180px; height:180px; background:#fff; border-radius:4px; }
  .scan-qr-note { font-size:.72rem; line-height:1.35; color:var(--text-muted,#667); text-align:left; }
  @media (max-width:640px) { .scan-qr-pop { position:static; width:auto; }
                             .scan-qr-pop img { width:150px; height:150px; } }
  @media print { .scan-qr-pop { position:static; box-shadow:none; } }
</style>

<h1 style="font-size:1.5rem;margin:0 0 .3rem;">Production areas</h1>
<p class="pa-sub">Your workshop's areas — each a bench with its own scanner — and which product route is made in which. When an order comes in, its blinds split to their area automatically. A vertical's headrail and fabric routes can live in separate areas.</p>

<?php if ($flashOk !== ''): ?><div class="pa-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="pa-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$ready): ?>
  <div class="pa-flash err">The production-areas tables aren't there yet — run <code>/migrate_production_areas.php</code>.</div>
<?php else: ?>
<div class="pa-wrap">
  <div class="pa-card">
    <h2>Areas</h2>
    <p class="pa-sub" style="margin:.2rem 0 .8rem">Add the areas your workshop is split into — e.g. Vertical Blinds, Roller Blinds, Pleated Blinds.</p>
    <?php if (!$areas): ?><p class="pa-sub">No areas yet — add the first below.</p><?php endif; ?>
    <?php foreach ($areas as $i => $ar): $aid = (int) $ar['id']; ?>
      <div class="area-row">
        <span class="seq"><?= $i + 1 ?></span>
        <form method="post" class="inline" style="flex:1;display:flex;gap:.3rem">
          <?= csrf_field() ?><input type="hidden" name="_action" value="rename_area"><input type="hidden" name="area_id" value="<?= $aid ?>">
          <input type="text" name="name" value="<?= e((string) $ar['name']) ?>" onchange="this.form.submit()" style="flex:1">
        </form>
        <span class="count"><?= (int) ($areaCounts[$aid] ?? 0) ?> product<?= ((int) ($areaCounts[$aid] ?? 0)) === 1 ? '' : 's' ?></span>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="move_area"><input type="hidden" name="area_id" value="<?= $aid ?>"><button class="mv" name="dir" value="up">▲</button><button class="mv" name="dir" value="down">▼</button></form>
        <form method="post" class="inline" onsubmit="return confirm('Remove this area? Its products become unassigned.')"><?= csrf_field() ?><input type="hidden" name="_action" value="remove_area"><input type="hidden" name="area_id" value="<?= $aid ?>"><button class="btn ghost mini">✕</button></form>
      </div>
    <?php endforeach; ?>
    <form method="post" class="add-row">
      <?= csrf_field() ?><input type="hidden" name="_action" value="add_area">
      <input type="text" name="name" placeholder="area name, e.g. Roller Blinds" style="flex:1;min-width:12rem" required>
      <button class="btn">Add area</button>
    </form>
  </div>

  <div class="pa-card">
    <h2>What's made where</h2>
    <p class="pa-sub" style="margin:.2rem 0 .8rem">Set the area for each product's route. Most products have one route, so one area. A vertical blind has two — its <em>Headrail</em> and <em>Fabric</em> labels — and each can go to a different area, so each area's scanner only accepts its own label.</p>
    <?php
      // One area <select> for a (product, stream) → its own tiny form.
      $areaSelect = function (int $pid, string $stream, int $cur) use ($areas): string {
          $h  = '<form method="post" class="inline">' . csrf_field()
              . '<input type="hidden" name="_action" value="set_product_area">'
              . '<input type="hidden" name="product_id" value="' . $pid . '">'
              . '<input type="hidden" name="stream" value="' . e($stream) . '">'
              . '<select name="area_id" onchange="this.form.submit()">'
              . '<option value="0"' . ($cur === 0 ? ' selected' : '') . '>— unassigned —</option>';
          foreach ($areas as $ar) {
              $id = (int) $ar['id'];
              $h .= '<option value="' . $id . '"' . ($cur === $id ? ' selected' : '') . '>' . e((string) $ar['name']) . '</option>';
          }
          return $h . '</select></form>';
      };
    ?>
    <?php if (!$areas): ?>
      <p class="pa-sub">Add an area first, then assign products to it.</p>
    <?php else: ?>
      <table class="prods">
        <?php foreach ($products as $p): $pid = (int) $p['id']; $streams = $productStreams[$pid]; ?>
          <?php if (count($streams) === 1): $st = (string) $streams[0]; $cur = (int) ($productArea["$pid:$st"] ?? 0); ?>
            <tr>
              <td class="pname"><?= e((string) $p['name']) ?></td>
              <td style="text-align:right"><?= $areaSelect($pid, $st, $cur) ?></td>
            </tr>
          <?php else: ?>
            <tr><td class="pname" colspan="2" style="padding-top:.7rem;border-bottom:none"><?= e((string) $p['name']) ?> <span class="count">· <?= count($streams) ?> routes</span></td></tr>
            <?php foreach ($streams as $st): $st = (string) $st; $cur = (int) ($productArea["$pid:$st"] ?? 0); ?>
              <tr>
                <td style="padding-left:1.4rem;color:var(--text-muted,#667)">↳ <?= e(ucfirst($st)) ?></td>
                <td style="text-align:right"><?= $areaSelect($pid, $st, $cur) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <div class="pa-card">
    <h2>Area scanners</h2>
    <p class="pa-sub" style="margin:.2rem 0 .8rem">Each area is a bench with its own WiFi scanner. Name the scanner and generate its key, then point the scanner at the URL below (put <code>{CODE}</code> where it sends the barcode). A key only finishes the routes in <em>its</em> area — so the fabric bench's scanner rejects a headrail label, and vice versa. The scanner name shows against every scan in the log.</p>
    <?php if (!$hasScanKey): ?>
      <div class="pa-flash err">Scan keys need the Phase B migration — run <code>/migrate_production_areas_phase_b.php</code>.</div>
    <?php elseif (!$areas): ?>
      <p class="pa-sub">Add an area first, then generate its scanner key here.</p>
    <?php else: ?>
      <?php
        $wifiSsid = (string) fx_kv_get($pdo, 'wifi_ssid', '');
        $wifiPass = (string) fx_kv_get($pdo, 'wifi_password', '');
      ?>
      <form method="post" class="wifi-bar">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="set_wifi">
        <span class="wifi-lab">Workshop WiFi</span>
        <input type="text" name="wifi_ssid" value="<?= e($wifiSsid) ?>"
               placeholder="network name (SSID)" maxlength="60" style="width:11rem" autocomplete="off">
        <input type="password" name="wifi_password" value=""
               placeholder="<?= $wifiPass !== '' ? 'password saved — leave blank to keep' : 'password' ?>"
               maxlength="120" style="width:13rem" autocomplete="new-password">
        <button class="btn mini">Save WiFi</button>
        <span class="pa-sub" style="flex-basis:100%;margin:.15rem 0 0;font-size:.75rem">
          The scanners join this network, so it goes in every setup QR below.
          <?php if (trim($wifiSsid) === ''): ?>
            <strong>Set it before generating a QR</strong> — without it the scanner can't get online.
          <?php endif; ?>
          No commas or semicolons: the scanner's setup command uses both as separators.
        </span>
      </form>
      <?php foreach ($areas as $ar):
              $aid   = (int) $ar['id'];
              $key   = (string) ($ar['scan_key'] ?? '');
              $sname = trim((string) ($ar['scanner_name'] ?? ''));
              $url   = $scanUrlFor($ar);
      ?>
        <div class="scan-row">
          <div class="scan-name"><?= e((string) $ar['name']) ?></div>
          <?php if ($hasScannerName): ?>
            <form method="post" class="inline" style="display:flex;gap:.3rem;align-items:center">
              <?= csrf_field() ?><input type="hidden" name="_action" value="set_scanner_name"><input type="hidden" name="area_id" value="<?= $aid ?>">
              <input type="text" name="scanner_name" value="<?= e($sname) ?>" placeholder="scanner name, e.g. Headrail bench" onchange="this.form.submit()" style="width:12rem" maxlength="60">
            </form>
          <?php endif; ?>
          <?php if ($key === ''): ?>
            <span class="scan-none">No key yet</span>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="_action" value="gen_scan_key"><input type="hidden" name="area_id" value="<?= $aid ?>"><button class="btn mini">Generate key</button></form>
          <?php else: ?>
            <input type="text" class="scan-url" readonly onclick="this.select()" value="<?= e($url) ?>">
            <details class="scan-qr">
              <summary class="btn ghost mini" title="Show this bench's scanner setup code">Setup QR</summary>
              <div class="scan-qr-pop">
                <?php if (trim($wifiSsid) === ''): ?>
                  <div class="scan-qr-note">Set the workshop WiFi above first — the setup code carries the network as well as this URL.</div>
                <?php else: ?>
                  <img src="?qr=<?= $aid ?>" alt="Scanner setup code for <?= e((string) $ar['name']) ?>" width="180" height="180">
                  <div class="scan-qr-note">
                    Put the scanner in WiFi-config mode and read this. It carries
                    the network <strong><?= e($wifiSsid) ?></strong> and this
                    bench's URL in one code, so there's nothing to type.
                    <br><br>
                    <strong>Press the trigger twice</strong> to commit it — one
                    press looks like it worked but doesn't save.
                    <br><br>
                    It contains this area's key and the WiFi password, so don't
                    leave it on screen or print it where it'll be left lying about.
                    <br><br>
                    <?php /* The exact bytes the QR carries. Without this, a
                             scanner that refuses the code gives you nothing to
                             compare against the vendor tool's own output, and
                             the only way to read our string is to decode the
                             image. Same secrets as the QR beside it, behind the
                             same click. */ ?>
                    <details>
                      <summary style="cursor:pointer">Show the exact text this code contains</summary>
                      <input class="scan-url" readonly onclick="this.select()" style="margin-top:.35rem;width:100%"
                             value="<?= e($scanAtFor($ar, $wifiSsid, $wifiPass)) ?>">
                      <span style="display:block;margin-top:.3rem">
                        Paste this beside what the scanner's own app produces for the
                        same bench — one character of difference is the whole fault.
                      </span>
                    </details>
                  </div>
                <?php endif; ?>
              </div>
            </details>
            <form method="post" class="inline" onsubmit="return confirm('Generate a new key? The old one stops working immediately — you\'ll need to re-point that area\'s scanner.')"><?= csrf_field() ?><input type="hidden" name="_action" value="gen_scan_key"><input type="hidden" name="area_id" value="<?= $aid ?>"><button class="btn ghost mini">Regenerate</button></form>
            <form method="post" class="inline" onsubmit="return confirm('Remove this key? That area\'s scanner will stop working.')"><?= csrf_field() ?><input type="hidden" name="_action" value="clear_scan_key"><input type="hidden" name="area_id" value="<?= $aid ?>"><button class="btn ghost mini">✕</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
