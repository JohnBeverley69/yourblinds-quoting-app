<?php
declare(strict_types=1);

/**
 * Factory · Build Rules (v2) — the "Cuts / Calcs / Charts" redesign.
 *
 * Reads the LIVE build_variables for a product and re-presents them so they can
 * actually be read:
 *   Cuts   — variables whose every row is "Width − N" / "Drop − N": shown as a
 *            plain options→take-off table, no formula text.
 *   Calcs  — the genuine formulas (vanes, metres, chain, cord).
 *   Charts — the Vogue/Louvolite best-fit lookup (lives inside the truck plumbing).
 *   Plumbing — intermediate values that never print (spacing, trucks, size).
 *
 * Same data the worksheet reads, so nothing here forks the compute path. This
 * pass is READ-ONLY (proving it shows the real numbers); editing/save is the
 * next step. The live "cut tester" evaluates the real cut rows client-side.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$pdo    = db();
$MASTER = function_exists('factory_client_id') ? (int) factory_client_id() : 0;

// Products that have build variables (master "Bev …" catalogue), for the picker.
$products = [];
try {
    $ps = $pdo->prepare(
        "SELECT DISTINCT p.id, p.name
           FROM products p JOIN build_variables v ON v.product_id = p.id
          WHERE p.client_id = ? AND p.name LIKE 'Bev%'
          ORDER BY p.name"
    );
    $ps->execute([$MASTER]);
    $products = $ps->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* handled in view */ }

// Default to Vertical Blinds if present, else first product.
$productId = (int) ($_GET['product_id'] ?? 0);
if ($productId === 0) {
    foreach ($products as $p) { if ($p['name'] === 'Bev Vertical Blinds') { $productId = (int) $p['id']; break; } }
    if ($productId === 0 && $products) $productId = (int) $products[0]['id'];
}
$productName = '';
foreach ($products as $p) { if ((int) $p['id'] === $productId) $productName = (string) $p['name']; }

/** Load this product's build variables in evaluation order. */
$vars = [];
if ($productId > 0) {
    try {
        $vs = $pdo->prepare(
            'SELECT name, columns_json, rows_json, seq
               FROM build_variables WHERE product_id = ? ORDER BY seq, id'
        );
        $vs->execute([$productId]);
        $vars = $vs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* table missing / not migrated */ }
}

// Friendly names for the cryptic variable codes.
$FRIENDLY = [
    'H_Cut' => 'Headrail cut', 'Hem_To_Hem' => 'Fabric drop', 'Vanes' => 'Vanes',
    'Mtrs' => 'Fabric metres', 'CH_L' => 'Tilt chain', 'C_L' => 'Draw cord',
    'Trucks' => 'Trucks', 'Truck_Size' => 'Truck size', 'Truck_Spec' => 'Truck spec',
    'Spacing' => 'Truck spacing', 'Truck_Spacing' => 'Truck spacing',
];
// Variables that are internal plumbing (never printed on a ticket).
$PLUMBING = ['Spacing', 'Truck_Spacing', 'Trucks', 'Truck_Size', 'Truck_Spec'];
// A little plain-English gloss for the known calcs (fallbacks to raw rows).
$CALC_GLOSS = [
    'Vanes' => 'number of trucks + 1',
    'Mtrs'  => 'round up  (Drop + 95) × Vanes ÷ 1000',
];

/** Parse a result expression as a cut: "Width − N" / "Drop + N" / bare "Width". */
$parseCut = static function (string $result): ?array {
    $r = trim($result);
    if (!preg_match('/^(Width|Drop|W_C|D_C)\s*(?:([+\-])\s*([0-9]+(?:\.[0-9]+)?))?$/i', $r, $m)) {
        return null;
    }
    $base = strtoupper($m[1][0]) === 'W' || stripos($m[1], 'Width') === 0 ? 'Width' : 'Drop';
    if (strcasecmp($m[1], 'W_C') === 0) $base = 'Width';
    if (strcasecmp($m[1], 'D_C') === 0) $base = 'Drop';
    $sign = ($m[2] ?? '') === '-' ? -1 : 1;
    $n    = isset($m[3]) && $m[3] !== '' ? (float) $m[3] : 0.0;
    return ['base' => $base, 'sign' => $sign, 'n' => $n];
};

// Sort each variable into a bucket and pre-compute a tidy shape.
$cuts = []; $calcs = []; $charts = []; $plumbing = [];
foreach ($vars as $v) {
    $name    = (string) $v['name'];
    $cols    = json_decode((string) $v['columns_json'], true) ?: [];
    $rows    = json_decode((string) $v['rows_json'], true) ?: [];
    $labels  = array_map(static fn ($c) => (string) ($c['label'] ?? ($c['ref'] ?? '')), $cols);
    $friendly = $FRIENDLY[$name] ?? $name;

    $rawResults = array_map(static fn ($r) => (string) ($r['result'] ?? ''), $rows);
    $hasBestfit = false;
    foreach ($rawResults as $rr) { if (stripos($rr, 'BESTFIT') !== false) { $hasBestfit = true; break; } }

    // CUT: has rows, and every row parses as the same single base.
    $cutRows = []; $base = null; $isCut = !empty($rows);
    foreach ($rows as $r) {
        $p = $parseCut((string) ($r['result'] ?? ''));
        if ($p === null) { $isCut = false; break; }
        if ($base === null) $base = $p['base'];
        if ($p['base'] !== $base) { $isCut = false; break; }
        $cutRows[] = ['cells' => array_map('strval', (array) ($r['cells'] ?? [])), 'sign' => $p['sign'], 'n' => $p['n']];
    }

    if ($isCut && $base !== null) {
        // Hide columns that are blank in every row.
        $active = [];
        foreach ($labels as $i => $lab) {
            foreach ($cutRows as $cr) { if (($cr['cells'][$i] ?? '') !== '') { $active[] = $i; break; } }
        }
        $cuts[] = ['name' => $name, 'friendly' => $friendly, 'base' => $base,
                   'labels' => $labels, 'active' => $active, 'rows' => $cutRows];
    } elseif (in_array($name, $PLUMBING, true)) {
        $plumbing[] = ['name' => $name, 'friendly' => $friendly, 'results' => $rawResults, 'bestfit' => $hasBestfit];
    } else {
        $calcs[] = ['name' => $name, 'friendly' => $friendly, 'gloss' => $CALC_GLOSS[$name] ?? '',
                    'rows' => $rows, 'labels' => $labels];
    }
}
// A single conceptual "chart" card if any plumbing uses best-fit.
$usesChart = false; foreach ($plumbing as $pl) { if ($pl['bestfit']) { $usesChart = true; break; } }

$factoryTitle = 'Build rules v2';
$factoryNav   = 'buildv2';
require __DIR__ . '/../_partials/factory_head.php';

$e2 = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<style>
  .brv2{ --ink:#1f2a37; --soft:#55616d; --faint:#8592a0; --line:#e4e9ef; --line-2:#eef2f6;
         --surface:#fff; --panel:#f6f8fb; --accent:#0284c7; --accent-ink:#075985; --accent-wash:#e0f2fe;
         --num:#b45309; --keep:#16794c; --keep-wash:#e4f3ec; color:var(--ink); }
  .brv2 h1{ font-size:1.6rem; letter-spacing:-.02em; margin:0 0 .3rem; }
  .brv2 .sub{ color:var(--soft); margin:0 0 .2rem; max-width:64ch; }
  .brv2 .preview-flag{ display:inline-flex; align-items:center; gap:.4rem; background:var(--accent-wash);
      color:var(--accent-ink); font-size:.78rem; font-weight:600; padding:.2rem .6rem; border-radius:999px;
      border:1px solid #bae6fd; margin-bottom:.9rem; }
  .brv2 .topline{ display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:1.1rem 0 1.6rem; }
  .brv2 select{ font:inherit; padding:.45rem .7rem; border:1px solid var(--line); border-radius:8px;
      background:var(--surface); color:var(--ink); font-weight:600; }
  .brv2 .layout{ display:grid; grid-template-columns:1fr 320px; gap:1.4rem; align-items:start; }
  @media(max-width:900px){ .brv2 .layout{ grid-template-columns:1fr; } }

  .brv2 .grouplabel{ display:flex; align-items:center; gap:.6rem; margin:1.6rem 0 .8rem; font-size:.72rem;
      letter-spacing:.13em; text-transform:uppercase; color:var(--faint); font-weight:600; }
  .brv2 .grouplabel:first-of-type{ margin-top:0; }
  .brv2 .grouplabel .ln{ flex:1; height:1px; background:var(--line); }
  .brv2 .badge{ font-size:.66rem; font-weight:600; padding:.08rem .5rem; border-radius:5px; text-transform:none; }
  .brv2 .badge.prints{ background:var(--keep-wash); color:var(--keep); }
  .brv2 .badge.hidden{ background:#eef2f6; color:var(--faint); }

  .brv2 .cut{ background:var(--surface); border:1px solid var(--line); border-radius:11px; padding:1rem 1.1rem; margin-bottom:.8rem; }
  .brv2 .cut-top{ display:flex; align-items:baseline; gap:.6rem; flex-wrap:wrap; }
  .brv2 .cut-name{ font-weight:700; font-size:1.05rem; }
  .brv2 .cut-def{ font-family:ui-monospace,Menlo,monospace; font-size:.85rem; color:var(--soft); }
  .brv2 .cut-def .m{ color:var(--accent); font-weight:600; } .brv2 .cut-def .n{ color:var(--num); font-weight:600; }
  .brv2 .code-name{ font-family:ui-monospace,Menlo,monospace; font-size:.72rem; color:var(--faint); }
  .brv2 table{ width:100%; border-collapse:collapse; margin-top:.7rem; font-size:.9rem; }
  .brv2 th{ text-align:left; font-size:.68rem; letter-spacing:.05em; text-transform:uppercase; color:var(--faint);
      font-weight:700; padding:.35rem .6rem; border-bottom:1px solid var(--line); }
  .brv2 td{ padding:.34rem .6rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
  .brv2 tr:last-child td{ border-bottom:none; }
  .brv2 td.num{ font-family:ui-monospace,Menlo,monospace; color:var(--num); font-weight:600; text-align:right;
      font-variant-numeric:tabular-nums; }
  .brv2 th.r{ text-align:right; }
  .brv2 .scroll{ overflow-x:auto; }

  .brv2 .calc{ display:flex; gap:.9rem; align-items:baseline; padding:.5rem 0; border-bottom:1px solid var(--line-2); flex-wrap:wrap; }
  .brv2 .calc:last-child{ border-bottom:none; }
  .brv2 .calc .cn{ font-weight:700; font-size:.95rem; min-width:8rem; flex-shrink:0; }
  .brv2 .calc .cf{ font-family:ui-monospace,Menlo,monospace; font-size:.83rem; color:var(--soft); }

  .brv2 .chartcard{ background:var(--surface); border:1px solid var(--line); border-radius:11px; padding:1rem 1.1rem;
      display:flex; gap:.9rem; align-items:flex-start; }
  .brv2 .chartcard .ic{ font-size:1.3rem; line-height:1; }
  .brv2 .chartcard .cn{ font-weight:700; }
  .brv2 .chartcard p{ margin:.2rem 0 0; font-size:.86rem; color:var(--soft); }
  .brv2 details.working{ background:var(--panel); border:1px dashed var(--line); border-radius:11px; padding:.6rem 1rem; }
  .brv2 details.working summary{ cursor:pointer; font-size:.9rem; color:var(--soft); font-weight:600; }
  .brv2 details.working .pl{ font-size:.85rem; color:var(--soft); margin:.6rem 0 0; }
  .brv2 details.working code{ font-family:ui-monospace,Menlo,monospace; font-size:.8rem; }

  .brv2 .tester{ position:sticky; top:72px; background:var(--surface); border:1px solid var(--line);
      border-radius:12px; padding:1.1rem 1.15rem; box-shadow:0 8px 24px -18px rgba(20,30,40,.35); }
  .brv2 .tester h3{ margin:0 0 .2rem; font-size:1rem; }
  .brv2 .tester .hint{ font-size:.8rem; color:var(--soft); margin:0 0 .9rem; }
  .brv2 .field{ margin-bottom:.7rem; }
  .brv2 .field label{ display:block; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em;
      color:var(--faint); font-weight:700; margin-bottom:.2rem; }
  .brv2 .field input, .brv2 .field select{ width:100%; padding:.4rem .55rem; border:1px solid var(--line);
      border-radius:7px; font:inherit; background:var(--surface); color:var(--ink); }
  .brv2 .row2{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
  .brv2 .out{ margin-top:1rem; border-top:1px solid var(--line); padding-top:.9rem; display:grid; gap:.55rem; }
  .brv2 .out .o{ display:flex; justify-content:space-between; align-items:baseline; gap:.6rem; }
  .brv2 .out .ol{ font-size:.88rem; color:var(--soft); }
  .brv2 .out .ov{ font-family:ui-monospace,Menlo,monospace; font-weight:700; font-size:1.1rem;
      color:var(--keep); font-variant-numeric:tabular-nums; text-align:right; }
  .brv2 .out .ov small{ font-weight:500; color:var(--faint); font-size:.68rem; }
  .brv2 .empty{ background:var(--panel); border:1px dashed var(--line); border-radius:11px; padding:1rem 1.1rem; color:var(--soft); }

  @media (prefers-color-scheme:dark){
    .brv2:not([data-lit]){ --ink:#e8eef3; --soft:#a3b0bc; --faint:#6e7d89; --line:#2b343d; --line-2:#232b33;
        --surface:#1a2128; --panel:#161d23; --accent:#38bdf8; --accent-ink:#7dd3fc; --accent-wash:#0c2b3a;
        --num:#e0a256; --keep:#4cba81; --keep-wash:#16302a; }
  }
</style>

<div class="brv2">
  <span class="preview-flag">● Live data · read-only preview</span>
  <h1>Build rules <span style="color:var(--accent)">v2</span></h1>
  <p class="sub">Your <em>actual</em> stored build rules — the same numbers the worksheet uses — arranged as <b>Cuts</b>, <b>Calcs</b> and <b>Charts</b>, with the internal plumbing tucked away. Reading live; editing is the next step.</p>

  <div class="topline">
    <label style="font-size:.8rem;color:var(--soft);font-weight:600">Product</label>
    <form method="get" style="margin:0">
      <select name="product_id" onchange="this.form.submit()">
        <?php foreach ($products as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $productId ? 'selected' : '' ?>><?= $e2($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (!$vars): ?>
    <div class="empty">No build variables found for this product<?= $productName ? ' (' . $e2($productName) . ')' : '' ?>. Pick another product, or this one hasn't been set up yet.</div>
  <?php else: ?>
  <div class="layout">
    <div>
      <!-- CUTS -->
      <?php if ($cuts): ?>
      <div class="grouplabel"><span>Cuts &nbsp;·&nbsp; measurement − allowance</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>
      <?php foreach ($cuts as $c): ?>
        <div class="cut">
          <div class="cut-top">
            <span class="cut-name"><?= $e2($c['friendly']) ?></span>
            <span class="cut-def">= <span class="m"><?= $e2($c['base']) ?></span> − <span class="n">take-off</span></span>
            <span class="code-name">(<?= $e2($c['name']) ?>)</span>
          </div>
          <div class="scroll">
          <table>
            <thead><tr>
              <?php foreach ($c['active'] as $i): ?><th><?= $e2($c['labels'][$i] ?? '') ?></th><?php endforeach; ?>
              <th class="r">Take off (mm)</th>
            </tr></thead>
            <tbody>
              <?php foreach ($c['rows'] as $cr): $take = $cr['sign'] < 0 ? $cr['n'] : -$cr['n']; ?>
              <tr>
                <?php foreach ($c['active'] as $i): $cell = $cr['cells'][$i] ?? ''; ?>
                  <td><?= $cell === '' ? '<span style="opacity:.5">— any —</span>' : $e2($cell) ?></td>
                <?php endforeach; ?>
                <td class="num"><?= $e2(rtrim(rtrim(number_format($take, 2, '.', ''), '0'), '.')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <!-- CALCS -->
      <?php if ($calcs): ?>
      <div class="grouplabel"><span>Calcs &nbsp;·&nbsp; the real sums</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>
      <div class="cut">
        <?php foreach ($calcs as $cc): ?>
          <div class="calc">
            <span class="cn"><?= $e2($cc['friendly']) ?> <span class="code-name">(<?= $e2($cc['name']) ?>)</span></span>
            <span class="cf">
              <?php if ($cc['gloss'] !== ''): ?>
                <?= $e2($cc['gloss']) ?>
              <?php else:
                $parts = [];
                foreach ($cc['rows'] as $r) { $parts[] = (string) ($r['result'] ?? ''); }
                $parts = array_values(array_unique(array_filter($parts)));
                echo $e2(implode('   ·   ', array_slice($parts, 0, 4)));
              ?>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- CHART -->
      <?php if ($usesChart): ?>
      <div class="grouplabel"><span>Charts &nbsp;·&nbsp; supplier lookup</span><span class="badge prints">feeds the ticket</span><span class="ln"></span></div>
      <div class="chartcard">
        <span class="ic">📊</span>
        <div>
          <div class="cn">Vogue trucks — Louvolite chart</div>
          <p>Look up the width, get the number and size of trucks. Straight from Louvolite's sizing table — rarely touched. (The <em>Trucks</em> and <em>Truck size</em> plumbing below both read this one chart.)</p>
        </div>
      </div>
      <?php endif; ?>

      <!-- PLUMBING -->
      <?php if ($plumbing): ?>
      <div class="grouplabel"><span>Working values</span><span class="badge hidden">the floor never sees these</span><span class="ln"></span></div>
      <details class="working">
        <summary>Show the internal plumbing (<?= (int) count($plumbing) ?> values)</summary>
        <?php foreach ($plumbing as $pl): ?>
          <div class="pl"><b><?= $e2($pl['friendly']) ?></b> <span class="code-name">(<?= $e2($pl['name']) ?>)</span> — <?php
            $u = array_values(array_unique(array_filter($pl['results'])));
            echo $pl['bestfit'] ? 'from the Vogue chart, else <code>width ÷ spacing</code>' : $e2(implode(' · ', array_slice($u, 0, 3)));
          ?></div>
        <?php endforeach; ?>
      </details>
      <?php endif; ?>
    </div>

    <!-- LIVE TESTER (cuts only) -->
    <div class="tester" id="tester">
      <h3>Try a size</h3>
      <p class="hint">Live — runs your real cut rows for the values you pick.</p>
      <div class="row2">
        <div class="field"><label>Width (mm)</label><input id="t_w" type="number" value="1200" inputmode="numeric"></div>
        <div class="field"><label>Drop (mm)</label><input id="t_d" type="number" value="1500" inputmode="numeric"></div>
      </div>
      <div id="t_axes"></div>
      <div class="out" id="t_out"></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  // Real cut definitions from the server: [{name, friendly, base, cols:[labels], rows:[{keys:[...], sign, n}]}]
  var CUTS = <?= json_encode(array_map(static function ($c) {
      $cols = [];
      foreach ($c['active'] as $i) $cols[] = $c['labels'][$i] ?? '';
      $rows = [];
      foreach ($c['rows'] as $cr) {
          $keys = [];
          foreach ($c['active'] as $i) $keys[] = $cr['cells'][$i] ?? '';
          $rows[] = ['keys' => $keys, 'sign' => $cr['sign'], 'n' => $cr['n']];
      }
      return ['name' => $c['name'], 'friendly' => $c['friendly'], 'base' => $c['base'], 'cols' => $cols, 'rows' => $rows];
  }, $cuts), JSON_UNESCAPED_UNICODE) ?>;

  if (!CUTS.length) { return; }

  // Build option axes = union of each column's distinct non-blank values across all cuts.
  var axes = {}; // label -> Set
  CUTS.forEach(function(c){
    c.cols.forEach(function(lab, ci){
      if (!axes[lab]) axes[lab] = {};
      c.rows.forEach(function(r){ var v = r.keys[ci]; if (v) axes[lab][v] = true; });
    });
  });
  var axesEl = document.getElementById('t_axes');
  var pickers = {};
  Object.keys(axes).forEach(function(lab){
    var vals = Object.keys(axes[lab]);
    if (!vals.length) return;
    var wrap = document.createElement('div'); wrap.className = 'field';
    var l = document.createElement('label'); l.textContent = lab; wrap.appendChild(l);
    var sel = document.createElement('select');
    vals.forEach(function(v){ var o = document.createElement('option'); o.textContent = v; sel.appendChild(o); });
    wrap.appendChild(sel); axesEl.appendChild(wrap);
    pickers[lab] = sel; sel.addEventListener('change', recompute);
  });

  var outEl = document.getElementById('t_out');
  function evalCut(c, w, d){
    var sel = {};
    c.cols.forEach(function(lab){ sel[lab] = pickers[lab] ? pickers[lab].value : ''; });
    // First matching row wins; blank key = wildcard.
    for (var i=0;i<c.rows.length;i++){
      var r = c.rows[i], ok = true;
      for (var j=0;j<c.cols.length;j++){
        var k = r.keys[j];
        if (k !== '' && k.toLowerCase() !== String(sel[c.cols[j]]).toLowerCase()) { ok = false; break; }
      }
      if (ok){
        var baseVal = (c.base === 'Drop') ? d : w;
        if (isNaN(baseVal)) return null;
        var val = baseVal + r.sign * r.n;
        return { val: val, base: baseVal, sign: r.sign, n: r.n };
      }
    }
    return null;
  }
  function recompute(){
    var w = parseInt(document.getElementById('t_w').value,10);
    var d = parseInt(document.getElementById('t_d').value,10);
    outEl.innerHTML = '';
    CUTS.forEach(function(c){
      var res = evalCut(c, w, d);
      var row = document.createElement('div'); row.className = 'o';
      var lab = document.createElement('span'); lab.className = 'ol'; lab.textContent = c.friendly;
      var v = document.createElement('span'); v.className = 'ov';
      if (res){
        var op = res.sign < 0 ? '−' : '+';
        v.innerHTML = Math.round(res.val) + ' <small>= ' + res.base + ' ' + op + ' ' + res.n + '</small>';
      } else { v.textContent = '—'; }
      row.appendChild(lab); row.appendChild(v); outEl.appendChild(row);
    });
  }
  document.getElementById('t_w').addEventListener('input', recompute);
  document.getElementById('t_d').addEventListener('input', recompute);
  recompute();
})();
</script>
<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
