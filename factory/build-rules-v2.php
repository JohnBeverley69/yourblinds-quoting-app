<?php
declare(strict_types=1);

/**
 * Factory · Build Rules (v2 preview) — the "Cuts / Calcs / Charts" redesign.
 *
 * A READ-ONLY preview of the simplified build-rules screen, so we can judge the
 * shape before wiring it to storage + edit/save. Populated with Bev Vertical
 * Blinds' real authoritative numbers (from the seed_vertical_* logic), arranged
 * as:
 *   Cuts   — measurement − allowance (plain-number tables, no formulas)
 *   Calcs  — the handful of genuine formulas (vanes, metres, chain, cord)
 *   Charts — the one supplier lookup (Vogue trucks, Louvolite)
 * plus the working values (truck spacing/count/size) collapsed out of the way.
 *
 * The live "cut tester" computes the two cuts client-side from the same
 * $headrail table rendered above, to show the numbers are real. The full calc
 * engine (trucks/vanes/metres) stays in the existing engine; this is a look-and-
 * feel preview only. Lives beside build-rules.php; nothing here writes.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

// Headrail cut allowance: Width − N. Rows are [system, control, wand, recess, exact];
// wand '' = "any wand type / not applicable". First match wins, top to bottom —
// exactly the authoritative seed_vertical_hcut.php numbers.
$headrail = [
    ['Slimline', 'Cord', '',       30, 20],
    ['Slimline', 'Wand', 'Stack',  12,  2],
    ['Slimline', 'Wand', 'Centre', 20, 10],
    ['Vogue',    'Cord', '',       33, 23],
    ['Vogue',    'Wand', 'Stack',  22, 12],
    ['Vogue',    'Wand', 'Centre', 32, 22],
    ['Nova',     'Cord', '',       25, 15],
    ['Nova',     'Wand', '',       15,  5],
];
// Fabric drop cut: Drop − N.
$fabricDrop = ['Recess' => 55, 'Exact' => 45];

$factoryTitle = 'Build rules v2';
$factoryNav   = 'buildv2';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
  .brv2{ --ink:#1f2a37; --soft:#55616d; --faint:#8592a0; --line:#e4e9ef; --line-2:#eef2f6;
         --surface:#fff; --panel:#f6f8fb; --accent:#0284c7; --accent-ink:#075985; --accent-wash:#e0f2fe;
         --num:#b45309; --keep:#16794c; --keep-wash:#e4f3ec; }
  .brv2{ color:var(--ink); }
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
  .brv2 .badge{ font-size:.66rem; font-weight:600; padding:.08rem .5rem; border-radius:5px; letter-spacing:.02em; text-transform:none; }
  .brv2 .badge.prints{ background:var(--keep-wash); color:var(--keep); }
  .brv2 .badge.hidden{ background:#eef2f6; color:var(--faint); }

  .brv2 .cut{ background:var(--surface); border:1px solid var(--line); border-radius:11px; padding:1rem 1.1rem; margin-bottom:.8rem; }
  .brv2 .cut-top{ display:flex; align-items:baseline; gap:.6rem; flex-wrap:wrap; }
  .brv2 .cut-name{ font-weight:700; font-size:1.05rem; }
  .brv2 .cut-def{ font-family:ui-monospace,"SFMono-Regular",Menlo,monospace; font-size:.85rem; color:var(--soft); }
  .brv2 .cut-def .m{ color:var(--accent); font-weight:600; } .brv2 .cut-def .n{ color:var(--num); font-weight:600; }
  .brv2 .cut-note{ font-size:.85rem; color:var(--soft); margin:.25rem 0 0; }
  .brv2 table{ width:100%; border-collapse:collapse; margin-top:.7rem; font-size:.9rem; }
  .brv2 th{ text-align:left; font-size:.68rem; letter-spacing:.05em; text-transform:uppercase; color:var(--faint);
      font-weight:700; padding:.35rem .6rem; border-bottom:1px solid var(--line); }
  .brv2 td{ padding:.34rem .6rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
  .brv2 tr:last-child td{ border-bottom:none; }
  .brv2 td.num{ font-family:ui-monospace,Menlo,monospace; color:var(--num); font-weight:600; text-align:right;
      font-variant-numeric:tabular-nums; }
  .brv2 th.r{ text-align:right; }
  .brv2 .scroll{ overflow-x:auto; }

  .brv2 .calc{ display:flex; gap:.9rem; align-items:baseline; padding:.5rem 0; border-bottom:1px solid var(--line-2); }
  .brv2 .calc:last-child{ border-bottom:none; }
  .brv2 .calc .cn{ font-weight:700; font-size:.95rem; min-width:8rem; flex-shrink:0; }
  .brv2 .calc .cf{ font-family:ui-monospace,Menlo,monospace; font-size:.83rem; color:var(--soft); }
  .brv2 .calc .cf .m{ color:var(--accent); } .brv2 .calc .cf .n{ color:var(--num); }

  .brv2 .chartcard{ background:var(--surface); border:1px solid var(--line); border-radius:11px; padding:1rem 1.1rem;
      display:flex; gap:.9rem; align-items:flex-start; }
  .brv2 .chartcard .ic{ font-size:1.3rem; line-height:1; }
  .brv2 .chartcard .cn{ font-weight:700; }
  .brv2 .chartcard p{ margin:.2rem 0 0; font-size:.86rem; color:var(--soft); }
  .brv2 details.working{ background:var(--panel); border:1px dashed var(--line); border-radius:11px; padding:.6rem 1rem; }
  .brv2 details.working summary{ cursor:pointer; font-size:.9rem; color:var(--soft); font-weight:600; }
  .brv2 details.working p{ font-size:.86rem; color:var(--soft); margin:.6rem 0 0; }

  /* Tester */
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
  .brv2 .out .o{ display:flex; justify-content:space-between; align-items:baseline; }
  .brv2 .out .ol{ font-size:.88rem; color:var(--soft); }
  .brv2 .out .ov{ font-family:ui-monospace,Menlo,monospace; font-weight:700; font-size:1.15rem;
      color:var(--keep); font-variant-numeric:tabular-nums; }
  .brv2 .out .ov small{ font-weight:500; color:var(--faint); font-size:.7rem; }

  /* Dark mode, in case app.css flips --bg */
  @media (prefers-color-scheme:dark){
    .brv2:not([data-lit]){ --ink:#e8eef3; --soft:#a3b0bc; --faint:#6e7d89; --line:#2b343d; --line-2:#232b33;
        --surface:#1a2128; --panel:#161d23; --accent:#38bdf8; --accent-ink:#7dd3fc; --accent-wash:#0c2b3a;
        --num:#e0a256; --keep:#4cba81; --keep-wash:#16302a; }
  }
</style>

<div class="brv2">
  <span class="preview-flag">● Preview — read-only look &amp; feel</span>
  <h1>Build rules <span style="color:var(--accent)">v2</span></h1>
  <p class="sub">The <em>same numbers</em> as the current Build Rules screen — arranged so you can actually read them: <b>Cuts</b>, <b>Calcs</b>, and <b>Charts</b>, with the internal plumbing tucked away. Nothing here saves yet; if the shape's right, editing comes next.</p>

  <div class="topline">
    <label style="font-size:.8rem;color:var(--soft);font-weight:600">Product</label>
    <select>
      <option selected>Bev Vertical Blinds</option>
      <option disabled>Bev Roller Blinds — next</option>
      <option disabled>Bev PF Pleated — next</option>
      <option disabled>Bev PF Roller — next</option>
    </select>
  </div>

  <div class="layout">
    <div>
      <!-- CUTS -->
      <div class="grouplabel"><span>Cuts &nbsp;·&nbsp; measurement − allowance</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>

      <div class="cut">
        <div class="cut-top">
          <span class="cut-name">Headrail cut</span>
          <span class="cut-def">= <span class="m">Width</span> − <span class="n">allowance</span></span>
        </div>
        <p class="cut-note">Depends on the system, the control, and — for wands — whether it stacks or draws from the centre. Cord is the same whichever way it draws.</p>
        <div class="scroll">
        <table>
          <thead><tr><th>System</th><th>Control</th><th>Wand type</th><th class="r">Recess</th><th class="r">Exact</th></tr></thead>
          <tbody>
            <?php foreach ($headrail as [$sys,$ctl,$wand,$rec,$exa]): ?>
            <tr>
              <td><?= e($sys) ?></td><td><?= e($ctl) ?></td><td><?= $wand === '' ? '—' : e($wand) ?></td>
              <td class="num"><?= $rec ?></td><td class="num"><?= $exa ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

      <div class="cut">
        <div class="cut-top">
          <span class="cut-name">Fabric drop</span>
          <span class="cut-def">= <span class="m">Drop</span> − <span class="n">allowance</span></span>
        </div>
        <div class="scroll">
        <table>
          <thead><tr><th>Fit</th><th class="r">Take off</th></tr></thead>
          <tbody>
            <?php foreach ($fabricDrop as $fit => $n): ?>
            <tr><td><?= e($fit) ?></td><td class="num"><?= $n ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

      <!-- CALCS -->
      <div class="grouplabel"><span>Calcs &nbsp;·&nbsp; the real sums</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>
      <div class="cut">
        <div class="calc"><span class="cn">Vanes</span><span class="cf">number of trucks <span class="n">+ 1</span></span></div>
        <div class="calc"><span class="cn">Fabric metres</span><span class="cf">round up&nbsp; (<span class="m">Drop</span> + 95) × Vanes ÷ 1000</span></div>
        <div class="calc"><span class="cn">Tilt chain</span><span class="cf">fitted height set? (height − 1500) × 2&nbsp; :&nbsp; <span class="m">Drop</span> × 1.5</span></div>
        <div class="calc"><span class="cn">Draw cord</span><span class="cf">corded? chain + 2 × <span class="m">Width</span>&nbsp; :&nbsp; 2 × <span class="m">Width</span></span></div>
      </div>

      <!-- CHART -->
      <div class="grouplabel"><span>Charts &nbsp;·&nbsp; supplier lookup</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>
      <div class="chartcard">
        <span class="ic">📊</span>
        <div>
          <div class="cn">Vogue trucks — Louvolite chart</div>
          <p>Look up the width, get the number and size of trucks in one go. Straight from Louvolite's sizing table — rarely touched. (Replaces the old <em>Trucks</em> and <em>Truck&nbsp;Size</em> tables, which were the same chart looked up twice.)</p>
        </div>
      </div>

      <!-- WORKING -->
      <div class="grouplabel"><span>Working values</span><span class="badge hidden">the floor never sees these</span><span class="ln"></span></div>
      <details class="working">
        <summary>Show the internal plumbing (truck spacing, count, size)</summary>
        <p><b>Truck spacing</b> = 77mm · <b>Truck count</b> and <b>Truck size</b> come from the width (Vogue → the chart above; others → width ÷ spacing, rounded). These feed Vanes and the metres calc — they don't print, so they stay out of your way.</p>
      </details>
    </div>

    <!-- LIVE TESTER -->
    <div class="tester" id="tester">
      <h3>Try a size</h3>
      <p class="hint">Live — computes the two cuts from the table on the left.</p>
      <div class="row2">
        <div class="field"><label>Width (mm)</label><input id="t_w" type="number" value="1200" inputmode="numeric"></div>
        <div class="field"><label>Drop (mm)</label><input id="t_d" type="number" value="1500" inputmode="numeric"></div>
      </div>
      <div class="field"><label>System</label>
        <select id="t_sys"><option>Slimline</option><option>Vogue</option><option>Nova</option></select>
      </div>
      <div class="row2">
        <div class="field"><label>Control</label>
          <select id="t_ctl"><option>Wand</option><option>Cord</option></select>
        </div>
        <div class="field"><label>Wand type</label>
          <select id="t_wand"><option>Stack</option><option>Centre</option></select>
        </div>
      </div>
      <div class="field"><label>Fit</label>
        <select id="t_fit"><option>Recess</option><option>Exact</option></select>
      </div>
      <div class="out">
        <div class="o"><span class="ol">Headrail cut</span><span class="ov" id="o_head">—</span></div>
        <div class="o"><span class="ol">Fabric drop</span><span class="ov" id="o_drop">—</span></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Same authoritative table as rendered above (server → client, one source).
  var HEADRAIL = <?= json_encode($headrail) ?>;
  var DROP = <?= json_encode($fabricDrop) ?>;

  function lookupHeadrail(sys, ctl, wand){
    for (var i=0;i<HEADRAIL.length;i++){
      var r = HEADRAIL[i]; // [system, control, wand, recess, exact]
      if (r[0]!==sys) continue;
      if (r[1]!==ctl) continue;
      if (r[2]!=='' && r[2]!==wand) continue;
      return { recess:r[3], exact:r[4] };
    }
    return null;
  }
  function fmt(v){ return (v===null||isNaN(v)) ? '—' : v; }

  function recompute(){
    var w = parseInt(document.getElementById('t_w').value,10);
    var d = parseInt(document.getElementById('t_d').value,10);
    var sys = document.getElementById('t_sys').value;
    var ctl = document.getElementById('t_ctl').value;
    var wand = document.getElementById('t_wand').value;
    var fit = document.getElementById('t_fit').value;

    // Wand type only applies to wands; disable it visually for cord.
    document.getElementById('t_wand').disabled = (ctl !== 'Wand');

    var a = lookupHeadrail(sys, ctl, wand);
    var headEl = document.getElementById('o_head');
    var dropEl = document.getElementById('o_drop');

    if (a && !isNaN(w)){
      var take = (fit==='Exact') ? a.exact : a.recess;
      headEl.innerHTML = (w - take) + ' <small>= '+w+' − '+take+'</small>';
    } else { headEl.textContent = '—'; }

    var dtake = DROP[fit];
    if (!isNaN(d) && dtake!==undefined){
      dropEl.innerHTML = (d - dtake) + ' <small>= '+d+' − '+dtake+'</small>';
    } else { dropEl.textContent = '—'; }
  }

  ['t_w','t_d','t_sys','t_ctl','t_wand','t_fit'].forEach(function(id){
    var el = document.getElementById(id);
    el.addEventListener('input', recompute);
    el.addEventListener('change', recompute);
  });
  recompute();
})();
</script>
<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
