<?php
declare(strict_types=1);

/**
 * Guide: products-start-defaults — "Setting what the order page starts on" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * The settings that decide what the quote builder and InstaPrice fill in
 * before anyone types, spread over four product screens:
 *   - band ORDER: admin/products/price-tables.php (drag, price_tables.sort_order, per system)
 *   - "Start on the first band instead of All bands": admin/products/edit.php,
 *     under Band label (products.band_start_first)
 *   - default fabric: admin/products/options.php "Make default"
 *     (product_options.is_default, one per band; option-set-default.php),
 *     with same-name-and-colour inheritance in quote-builder/api/product-data.php
 *   - option order + default choice + "Show above the size fields":
 *     admin/products/extras.php, extra.php (_partials/choices_grid.php), extra-edit.php
 * Behaviour copied from quote-builder/edit.php populateBands()/applyDefaultFabric()
 * and the same pair in instaprice/index.php. Push: master-admin/push-updates.php.
 * NB the product page's Live preview drawer does not apply the tick or the
 * default fabric, so the last chapter checks in InstaPrice instead.
 *
 * Worked example: Bev Vertical Fabrics Only (systems Chainless, Chained;
 * bands AAA, AA, A, B, C, D, E).
 */

// ── The voice-over, by step ───────────────────────────────────────────
$S = [
    1  => ['1',  'What the order page starts on',
        'When you add a blind, the order page fills some boxes in for you. The system, the band, the fabric, and the options. That saves clicks on every single blind. A few settings decide what it starts on, and they live on different pages of the product. We will set them all on one product, Bev Vertical Fabrics Only.'],
    2  => ['2',  'The product\'s own page',
        'Open Products, and click Bev Vertical Fabrics Only. Its page has four tiles. Systems, Fabrics, Price tables and Options. Everything in this guide starts here. One quick thing on Systems. The system marked Default is the one the System box starts on. Here that is Chainless. With no default, the box says Choose system.'],
    3  => ['3',  'Putting the bands in order',
        'First, the order of the bands. Click the Price tables tile, then open a system, say Chainless. Each band has its own price table, one per row. Drag the handle on the left to move a band up or down. It says Saving, then Saved. Put the band you sell most at the top. Here that is Triple A, then Double A, A, B, C, D and E.'],
    4  => ['4',  'Each system has its own list',
        'Each system keeps its own order. So once Chainless is right, go back, open Chained, and drag its bands the same way. The Band box on the order page lists the bands in exactly this order, under All bands at the top. That is true in the quote builder, and in InstaPrice as well.'],
    5  => ['5',  'Start on the first band',
        'Next, the tick that is easy to forget. Back on the product\'s page, find the Band label box. Underneath it is a tick box. Start on the first band instead of All bands. Tick it, and press Save changes at the bottom of the page. The first band means the top one in that system\'s Price tables order. So the order you dragged really matters.'],
    6  => ['6',  'Ticked or not',
        'Here is the difference. Unticked, the Band box starts on All bands. The fabric search then shows every fabric in every band, which is a long list to scroll. Ticked, it starts on Triple A, and only Triple A fabrics are shown. A system with only one band picks it for you either way, tick or no tick.'],
    7  => ['7',  'A default fabric',
        'Now the fabric. Click the Fabrics tile. Find the fabric you want to start on, and click Make default at the end of its row. A star Default pill appears by its name, and the message says the order page fills it in to start with. Changed your mind? The same link now says Remove default.'],
    8  => ['8',  'One for each band',
        'You get one default for each band. Make another fabric in the same band the default, and the old one is cleared. So on a vertical, you can pick a starting fabric for Triple A, another for Double A, and so on down to E. A band you leave without one just starts with an empty fabric box.'],
    9  => ['9',  'When it fills in',
        'The default fills in at three moments. When the product is picked. When the system is changed. And when the band is changed. Pick band B, and B\'s default drops straight in. If a band has no default, the fabric list opens instead, showing only that band\'s fabrics, ready to choose from.'],
    10 => ['10', 'Bands that share fabrics',
        'Some products carry the same fabric in more than one band. A wood venetian, say, where each colour comes on String and on Tape. A band with no default of its own borrows one. It takes the fabric with the same name and colour. So making Elegant White the default on String covers Tape as well.'],
    11 => ['11', 'Putting the options in order',
        'Now the options. Click the Options tile. They are listed in the order the order page shows them. Drag the handle to move one up or down, just like the bands. Put the ones that matter most first. Inside each option, the choices have a handle too, so you can order the dropdown as well.'],
    12 => ['12', 'An option\'s default choice',
        'Click an option\'s name to see its choices. Tick Default on the choice that should come already picked. Here, Hem to Hem for Size. There is one default for each system, so ticking another clears the first. No default at all? The box starts on Select, and the salesperson has to choose.'],
    13 => ['13', 'Above the size fields',
        'Some options make more sense before the width and drop. On the Options list, click Edit beside the option. Tick Show above the size fields, and press Save changes. That option, and anything under it, moves above Width and Drop in the quote builder. InstaPrice already puts its options above the size.'],
    14 => ['14', 'Other companies\' copies',
        'A word about other companies. ABC Blinds, for example, has its own copy of Bev Vertical Fabrics Only. Your changes only reach their copy after a catalogue push, from Platform, Push updates. The push carries the band order, the tick, the default fabrics and the option defaults across with everything else.'],
    15 => ['15', 'Check it in InstaPrice',
        'Finally, check it before a salesperson does. Open InstaPrice, and pick Bev Vertical Fabrics Only. Watch the boxes fill in. Chainless, Triple A, your default fabric, and the options in your order, each on its default. If something starts in the wrong place, go back to the page that sets it.'],
];

/** "Ns" — when the words $p are spoken in line $n (13.6 characters a second). */
$at = static function (int $n, string $p, float $plus = 0.0) use ($S): string {
    $i = mb_strpos($S[$n][2], $p);
    if ($i === false) { $GLOBALS['gd_at_miss'][] = "$n: $p"; $i = 0; }
    return round($i / 13.6 + $plus, 1) . 's';
};
$len = static fn (int $n): string => (string) max(8, (int) round(mb_strlen($S[$n][2]) / 13.6));

$BANDS = ['AAA', 'AA', 'A', 'B', 'C', 'D', 'E'];

/** A price-tables row (price-tables.php): handle, Band pill, Name, Cells, Open. */
$ptRow = static fn (string $b, int $cells): string =>
    '<div class="pr"><span class="dg">&#8942;&#8942;</span><span><span class="bp">Band ' . $b . '</span></span>'
  . '<span>' . $b . '</span><span></span><span class="num">' . $cells . '</span><span class="upd">2 days ago</span><span class="lnk">Open <span class="del">Delete</span></span></div>';
$ptHead = '<div class="pr hd"><span></span><span>Band</span><span>Name</span><span>Notes</span><span class="num">Cells</span><span>Updated</span><span></span></div>';
$CELLS = ['AAA' => 120, 'AA' => 120, 'A' => 120, 'B' => 120, 'C' => 120, 'D' => 120, 'E' => 120];

/** A select box as on the order page (one visible value + chevron). */
$sel = static fn (string $v, string $cls = '', string $st = ''): string =>
    '<span class="sb ' . $cls . '" style="' . $st . '">' . $v . '</span>';

/** The order page's first two rows: Product · System · Band / Fabric. */
$qrow = static function (string $sys, string $band, string $fab, array $ex = []) use ($sel): string {
    $f = static fn (string $lab, string $box, string $id = ''): string =>
        '<div class="fld"' . ($id !== '' ? ' data-k="' . $id . '"' : '') . '><span class="lab">' . $lab . '</span>' . $box . '</div>';
    $ip = !empty($ex['ip']);   // InstaPrice: no required stars
    return '<div class="qf">'
         . $f($ip ? 'Product' : 'Product <i>*</i>', $sel('Bev Vertical Fabrics Only', '', 'font-size:.62rem'))
         . $f('System', $ex['sys'] ?? $sel($sys))
         . $f('Band', $ex['band'] ?? $sel($band))
         . '</div><div class="qf q2">'
         . $f($ip ? 'Fabric' : 'Fabric <i>*</i>', $ex['fab'] ?? ('<span class="inp">' . $fab . '</span>'))
         . '</div>';
};

$side = '
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a class="on">Products</a><a>Users</a><a>Settings</a><a>Trade terms</a>
              </div>';

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Setting what the order page starts on',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'The band order, the "Start on the first band" tick, default fabrics, and option order and defaults — the settings that decide what the quote builder and InstaPrice fill in first.',
        'lede'    => 'When you add a blind, the order page fills some boxes in for you: the <b>system</b>, the <b>band</b>, the <b>fabric</b> and the
                      <b>options</b>. What it starts on is set on the product, across four of its pages &mdash; <b>Price tables</b>, the product&rsquo;s
                      own page, <b>Fabrics</b> and <b>Options</b>. This guide sets them all on one example, <b>Bev Vertical Fabrics Only</b>
                      (systems Chainless and Chained, bands AAA to E). To get there: <b>Products</b> &rarr; the product.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .app{ grid-template-columns:132px minmax(0,1fr); }
          .gd .sc{ position:relative; min-height:400px; }
          .gd .ttl{ font-size:.95rem; font-weight:800; color:var(--ink); margin:0 0 .45rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .sct{ font-weight:800; font-size:.86rem; color:var(--ink); margin:0 0 .3rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .6rem; line-height:1.45; max-width:34rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.22rem .6rem; font-size:.66rem; font-weight:700; color:var(--ink); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.35rem; margin:.5rem 0; }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .15rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.68rem; font-weight:700; white-space:nowrap; }
          .gd .btnr{ background:#b91c1c; }
          .gd .alr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:6px; padding:.32rem .55rem; font-size:.66rem; font-weight:600;
                    color:var(--ink); margin:0 0 .45rem; max-width:34rem; box-sizing:border-box; line-height:1.4; }
          .gd .swb{ display:grid; align-items:start; } .gd .swb > *{ grid-area:1/1; }
          .gd .sw{ display:inline-grid; } .gd .sw > *{ grid-area:1/1; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; max-width:36rem; }
          .gd .cap{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; font-weight:800; color:var(--soft); margin:0 0 .3rem; }
          .gd .ok{ color:var(--good); font-weight:700; }

          /* product page: setting-up tiles + form bits (admin/products/edit.php) */
          .gd .setup{ background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:.55rem .65rem; max-width:34rem; }
          .gd .setup h4{ margin:0 0 .4rem; font-size:.74rem; color:#0c4a6e; }
          .gd .tiles{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.4rem; }
          .gd .tile{ background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:.4rem .45rem; font-size:.56rem; color:#334155; line-height:1.3; }
          .gd .tile b{ display:flex; align-items:center; gap:.3rem; font-size:.68rem; color:#0f172a; margin-bottom:.1rem; }
          .gd .tile b i{ font-style:normal; width:.95rem; height:.95rem; border-radius:999px; background:#16a34a; color:#fff; font-size:.55rem;
                         display:inline-flex; align-items:center; justify-content:center; }
          .gd .fl{ display:block; font-size:.66rem; font-weight:700; color:var(--ink); margin:.4rem 0 .2rem; }
          .gd .inp{ display:flex; align-items:center; min-height:24px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:0 .5rem;
                    font-size:.7rem; background:var(--surface); color:var(--ink); box-sizing:border-box; }
          .gd .ph{ color:var(--faint); }
          .gd .uh{ display:block; font-size:.6rem; color:var(--faint); line-height:1.4; margin:.25rem 0 0; }
          .gd .cbr{ display:flex; gap:.45rem; align-items:flex-start; font-size:.7rem; color:var(--ink); margin:.4rem 0 .1rem; line-height:1.35; }
          .gd .cbr small{ display:block; color:var(--faint); font-size:.58rem; }
          .gd .cbr .tick{ flex:0 0 auto; }
          .gd .frm{ border:1px solid var(--line); border-radius:10px; padding:.5rem .7rem .6rem; max-width:30rem; background:var(--surface); }

          /* systems / price tables / options lists */
          .gd .pt{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:30rem; background:var(--surface); }
          .gd .pr{ display:grid; grid-template-columns:1.1rem 4.4rem .7fr .6fr 2.4rem 4.2rem 4.4rem; align-items:center; gap:.3rem; padding:.24rem .5rem;
                   border-top:1px solid var(--line-2); font-size:.66rem; color:var(--ink); }
          .gd .pr.hd{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .dg{ color:var(--faint); letter-spacing:-.1em; }
          .gd .num{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .lnk{ color:var(--accent); font-size:.6rem; white-space:nowrap; }
          .gd .lnk .del{ color:#b91c1c; margin-left:.3rem; }
          .gd .upd{ color:var(--faint); font-size:.58rem; white-space:nowrap; }
          .gd .bp{ display:inline-block; background:#1f3b5b; color:#fff; border-radius:999px; padding:.04rem .45rem; font-size:.56rem; font-weight:700; white-space:nowrap; }
          .gd .bp.def{ background:#b45309; }
          .gd .pill{ display:inline-block; font-size:.52rem; font-weight:700; border-radius:999px; padding:.02rem .4rem; margin-left:.3rem; vertical-align:middle;
                     text-transform:uppercase; letter-spacing:.04em; background:var(--accent-wash); color:var(--accent-ink); }
          .gd .pill.rq{ background:#1f3b5b; color:#fff; }
          .gd .stat{ font-size:.6rem; font-weight:700; }
          .gd .ol .pr{ grid-template-columns:1.1rem 1fr 3.2rem 4.2rem 7.4rem; }
          .gd .mini .pr{ grid-template-columns:1.1rem 4.4rem 1fr 2.4rem; }
          .gd .mini .pr > :nth-child(4), .gd .mini .pr > :nth-child(6), .gd .mini .pr > :nth-child(7){ display:none; }
          .gd .fb .pr{ grid-template-columns:.9rem 4.1rem 1fr 3.4rem 3.2rem 2.4rem 8.4rem; }
          .gd .tick.cb{ width:11px; height:11px; border-radius:3px; font-size:.45rem; }
          .gd .fb .ra{ display:flex; gap:.45rem; justify-content:flex-end; font-size:.58rem; color:var(--accent); white-space:nowrap; }
          .gd .fb .ra .del{ color:#b91c1c; }
          .gd .cg .pr{ grid-template-columns:1.1rem 1fr 3rem 3rem; }
          .gd .cg .tick{ width:14px; height:14px; font-size:.52rem; margin:0 auto; }

          /* the order page (quote-builder/edit.php, instaprice/index.php) */
          .gd .qp{ border:1px solid var(--line); border-radius:12px; padding:.55rem .7rem; background:var(--panel); max-width:30rem; }
          .gd .qf{ display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:.45rem; }
          .gd .qf.q2{ grid-template-columns:2.5fr 1fr; margin-top:.4rem; }
          .gd .fld .sw{ display:grid; }
          .gd .qf.qo{ grid-template-columns:1fr 1fr; margin-top:.4rem; }
          .gd .qf.qs{ grid-template-columns:1fr 1fr 1fr; margin-top:.4rem; }
          .gd .fld{ min-width:0; position:relative; }
          .gd .lab{ display:block; font-size:.58rem; font-weight:700; color:var(--soft); margin:0 0 .15rem; white-space:nowrap; }
          .gd .lab i{ color:#b91c1c; font-style:normal; }
          .gd .sb{ display:flex; align-items:center; justify-content:space-between; gap:.3rem; min-height:24px; border:1px solid var(--border-strong,#c7ccd4);
                   border-radius:6px; padding:0 .45rem; font-size:.68rem; background:var(--surface); color:var(--ink); box-sizing:border-box; white-space:nowrap; overflow:hidden; }
          .gd .sb::after{ content:"\25BE"; color:var(--faint); font-size:.58rem; }
          .gd .dd{ position:absolute; left:0; right:0; top:100%; z-index:3; background:var(--surface); border:1px solid var(--line); border-radius:6px;
                   box-shadow:var(--gd-shadow); font-size:.64rem; padding:.15rem 0; }
          .gd .dd span{ display:block; padding:.1rem .5rem; color:var(--ink); }
          .gd .dd span.on{ background:var(--accent); color:#fff; }
          .gd .res{ border:1px solid var(--line); border-radius:6px; background:var(--surface); margin-top:.2rem; font-size:.62rem; box-shadow:var(--gd-shadow); }
          .gd .res span{ display:flex; justify-content:space-between; gap:.4rem; padding:.12rem .45rem; border-top:1px solid var(--line-2); color:var(--ink); }
          .gd .res span:first-child{ border-top:0; }
          .gd .res em{ font-style:normal; color:var(--faint); font-size:.56rem; }
          .gd .zone{ border:1px dashed var(--accent); border-radius:8px; padding:.3rem .4rem; background:var(--accent-wash); }

          /* push updates (master-admin/push-updates.php) */
          .gd .tr{ display:flex; align-items:center; gap:.45rem; font-size:.7rem; color:var(--ink); padding:.28rem .5rem; border:1px solid var(--line-2); border-radius:7px; margin:.2rem 0; background:var(--surface); max-width:26rem; }
          .gd .tr small{ color:var(--faint); font-size:.6rem; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; }
            .gd .side{ display:none; }
            .gd .sc{ min-height:470px; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .tiles{ grid-template-columns:1fr 1fr; }
            .gd .pt:not(.ol):not(.fb):not(.cg) .pr{ grid-template-columns:1.1rem 4.4rem 1fr 2.4rem 4.4rem; }
            .gd .pt:not(.ol):not(.fb):not(.cg) .pr > :nth-child(4), .gd .pt:not(.ol):not(.fb):not(.cg) .pr > :nth-child(6){ display:none; }
            .gd .ol .pr{ grid-template-columns:1.1rem 1fr 3rem 7rem; }
            .gd .ol .pr > :nth-child(4){ display:none; }
            .gd .qf{ grid-template-columns:1fr 1fr; }
            .gd .qf > .fld:first-child{ grid-column:1 / -1; }
            .gd .qf.q2, .gd .qf.qo{ grid-template-columns:1fr; }
            .gd .fb .pr{ grid-template-columns:4.1rem 1fr 7.4rem; }
            .gd .fb .pr > :nth-child(1), .gd .fb .pr > :nth-child(4), .gd .fb .pr > :nth-child(5), .gd .fb .pr > :nth-child(6){ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / bev vertical fabrics only</span></div>
            <div class="app">' . $side . '
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="ttl">What the order page starts on</div>
                  <p class="scs">Pick <b>Bev Vertical Fabrics Only</b> and the boxes fill themselves in &mdash; because of four settings on the product.</p>
                  <div class="qp">' . $qrow('Chainless', 'AAA', 'Cleo / Beige') . '
                    <div class="qf qo"><div class="fld"><span class="lab">Size <i>*</i></span>' . $sel('Hem to Hem') . '</div>
                      <div class="fld"><span class="lab">Fabric Finish <i>*</i></span>' . $sel('Welded') . '</div></div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters.</p>
                </div>

                <!-- 1 — what it starts on -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="ttl a-fade" style="--d:.2s">Add a blind</div>
                  <div class="qp a-rise" style="--d:.4s">' . $qrow('Chainless', 'AAA', 'Cleo / Beige', [
                      'sys'  => '<span class="sw"><span class="sb a-out" style="--d:' . $at(1, 'The system') . '">&mdash; Choose system &mdash;</span><span class="sb a-fade" style="--d:' . $at(1, 'The system') . '">Chainless</span></span>',
                      'band' => '<span class="sw"><span class="sb a-out" style="--d:' . $at(1, 'the band') . '">All bands</span><span class="sb a-fade" style="--d:' . $at(1, 'the band') . '">AAA</span></span>',
                      'fab'  => '<span class="sw" style="display:grid"><span class="inp ph a-out" style="--d:' . $at(1, 'the fabric') . '">Type to search fabrics (or click for recent)</span><span class="inp a-fade" style="--d:' . $at(1, 'the fabric') . '">Cleo / Beige</span></span>',
                    ]) . '
                    <div class="qf qo a-fade" style="--d:' . $at(1, 'and the options') . '"><div class="fld"><span class="lab">Size <i>*</i></span>' . $sel('Hem to Hem') . '</div>
                      <div class="fld"><span class="lab">Fabric Finish <i>*</i></span>' . $sel('Welded') . '</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(1, 'That saves clicks') . '">&#10003; fewer clicks on every blind</span>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(1, 'they live on different') . '">Price tables</span>
                    <span class="chip a-pop" style="--d:' . $at(1, 'they live on different', .4) . '">Product page</span>
                    <span class="chip a-pop" style="--d:' . $at(1, 'they live on different', .8) . '">Fabrics</span>
                    <span class="chip a-pop" style="--d:' . $at(1, 'they live on different', 1.2) . '">Options</span>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(1, 'Bev Vertical') . ';border-color:var(--accent)">Example: Bev Vertical Fabrics Only</span></div>
                </div>

                <!-- 2 — the product page -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="ttl a-fade" style="--d:' . $at(2, 'click Bev') . '">Edit Bev Vertical Fabrics Only</div>
                  <div class="setup a-rise" style="--d:' . $at(2, 'Its page has') . '">
                    <h4>Setting this product up</h4>
                    <div class="tiles">
                      <div class="tile a-ring" style="--d:' . $at(2, 'Systems, Fabrics') . '"><b><i>&#10003;</i>Systems</b>2 items &mdash; click to manage</div>
                      <div class="tile a-ring" style="--d:' . $at(2, 'Fabrics, Price') . '"><b><i>&#10003;</i>Fabrics</b>1308 items &mdash; click to manage</div>
                      <div class="tile a-ring" style="--d:' . $at(2, 'Price tables and') . '"><b><i>&#10003;</i>Price tables</b>14 items &mdash; click to manage</div>
                      <div class="tile a-ring" style="--d:' . $at(2, 'and Options') . '"><b><i>&#10003;</i>Options</b>4 items &mdash; click to manage</div>
                    </div>
                  </div>
                  <div class="a-rise" style="--d:' . $at(2, 'One quick thing') . ';margin-top:.7rem">
                    <div class="cap">Bev Vertical Fabrics Only &mdash; Systems</div>
                    <div class="pt ol">
                      <div class="pr hd"><span></span><span>Name</span><span class="num">Price tables</span><span>Updated</span><span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Chainless</b><span class="pill a-ring" style="--d:' . $at(2, 'The system marked') . '">Default</span></span><span class="num">7</span><span class="upd">2 days ago</span><span class="lnk">Rename <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Chained</b></span><span class="num">7</span><span class="upd">2 days ago</span><span class="lnk">Rename <u>Set default</u> <span class="del">Delete</span></span></div>
                    </div>
                  </div>
                  <div class="two" style="margin-top:.7rem;max-width:30rem">
                    <div class="fld a-pop" style="--d:' . $at(2, 'Here that is') . '"><span class="lab">System</span>' . $sel('Chainless') . '</div>
                    <div class="fld a-pop" style="--d:' . $at(2, 'With no default') . '"><span class="lab">System (no default set)</span>' . $sel('&mdash; Choose system &mdash;') . '</div>
                  </div>
                </div>

                <!-- 3 — band order -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="ttl a-fade" style="--d:' . $at(3, 'open a system') . '">Bev Vertical Fabrics Only &mdash; Chainless</div>
                  <div class="sct a-fade" style="--d:' . $at(3, 'Each band has') . '">Price tables (7)</div>
                  <p class="scs a-fade" style="--d:' . $at(3, 'Drag the handle') . ';margin:0 0 .35rem">Drag the <b class="a-ring" style="--d:' . $at(3, 'Drag the handle', .5) . ';border-radius:4px;padding:0 .15rem">&#8942;&#8942;</b> handle to reorder bands.
                    <span class="sw"><span class="stat a-mid" style="--d:' . $at(3, 'It says Saving') . ';--d2:' . $at(3, 'then Saved') . '">Saving&hellip;</span><span class="stat ok a-fade" style="--d:' . $at(3, 'then Saved') . '">Saved</span></span></p>
                  <div class="swb">
                    <div class="pt a-out" style="--d:' . $at(3, 'It says Saving') . '">' . $ptHead
                      . implode('', array_map(static fn ($b) => $ptRow($b, $CELLS[$b]), ['AAA', 'E', 'D', 'C', 'B', 'A', 'AA'])) . '</div>
                    <div class="pt a-fade" style="--d:' . $at(3, 'It says Saving') . '">' . $ptHead
                      . implode('', array_map(static fn ($b) => $ptRow($b, $CELLS[$b]), $BANDS)) . '</div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(3, 'Put the band you sell') . '">&#9650; the band you sell most goes at the top</span></div>
                </div>

                <!-- 4 — each system -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="two">
                    <div class="a-rise" style="--d:.3s"><div class="cap">Chainless</div><div class="pt mini">' . $ptHead
                      . implode('', array_map(static fn ($b) => $ptRow($b, $CELLS[$b]), $BANDS)) . '</div><p class="scs ok" style="margin:.3rem 0 0">&#10003; done</p></div>
                    <div class="a-rise" style="--d:' . $at(4, 'open Chained') . '"><div class="cap">Chained</div><div class="swb">
                      <div class="pt mini a-out" style="--d:' . $at(4, 'drag its bands') . '">' . $ptHead
                        . implode('', array_map(static fn ($b) => $ptRow($b, $CELLS[$b]), ['D', 'C', 'B', 'A', 'AA', 'AAA', 'E'])) . '</div>
                      <div class="pt mini a-fade" style="--d:' . $at(4, 'drag its bands') . '">' . $ptHead
                        . implode('', array_map(static fn ($b) => $ptRow($b, $CELLS[$b]), $BANDS)) . '</div></div></div>
                  </div>
                  <div class="two" style="margin-top:.7rem">
                    <div class="fld a-rise" style="--d:' . $at(4, 'The Band box') . '"><span class="lab">Band &middot; quote builder</span>' . $sel('All bands') . '
                      <div class="dd" style="position:static;margin-top:.15rem"><span class="a-ring" style="--d:' . $at(4, 'under All bands') . '">All bands</span>'
                      . implode('', array_map(static fn ($b) => '<span>' . $b . '</span>', $BANDS)) . '</div></div>
                    <div class="fld a-rise" style="--d:' . $at(4, 'and in InstaPrice') . '"><span class="lab">Band &middot; InstaPrice</span>' . $sel('All bands') . '
                      <div class="dd" style="position:static;margin-top:.15rem"><span>All bands</span>'
                      . implode('', array_map(static fn ($b) => '<span>' . $b . '</span>', $BANDS)) . '</div></div>
                  </div>
                </div>

                <!-- 5 — the tick -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="ttl a-fade" style="--d:.2s">Edit Bev Vertical Fabrics Only</div>
                  <div class="frm a-rise" style="--d:.5s">
                    <span class="fl a-ring" style="--d:' . $at(5, 'find the Band label') . ';border-radius:4px;display:inline-block">Band label</span>
                    <span class="inp"><span class="ph">Band</span></span>
                    <small class="uh">What the <b>band</b> step is called in the quote builder &mdash; the price-table tier picked after System. Leave blank for
                      the default <em>Band</em>. For a wood venetian, e.g. <em>Tape / String</em>.</small>
                    <div class="cbr a-fade" style="--d:' . $at(5, 'Underneath it') . '">
                      <span class="a-ring" style="--d:' . $at(5, 'Tick it') . ';border-radius:5px;display:inline-flex"><span class="tick a-sel" style="--d:' . $at(5, 'Tick it', .6) . '">&#10003;</span></span>
                      <span>Start on the first band instead of <em>All bands</em></span></div>
                    <small class="uh a-fade" style="--d:' . $at(5, 'Underneath it') . '">The first band is the top one in each system&rsquo;s Price tables order &mdash; drag that list to change it.</small>
                  </div>
                  <div style="margin-top:.6rem"><span class="btnp a-press" style="--d:' . $at(5, 'press Save changes') . '">Save changes</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(5, 'The first band means') . '">first band = top of Price tables: <b>AAA</b></span>
                    <span class="chip a-pop" style="--d:' . $at(5, 'So the order you dragged') . ';border-color:var(--accent)">the order you dragged matters</span></div>
                </div>

                <!-- 6 — ticked or not -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="two">
                    <div class="a-rise" style="--d:.3s">
                      <div class="cap"><span class="tick" style="width:12px;height:12px;vertical-align:middle">&#10003;</span> Unticked</div>
                      <div class="fld"><span class="lab">Band</span>' . $sel('All bands', 'a-ring', '--d:' . $at(6, 'starts on All bands')) . '</div>
                      <div class="fld a-fade" style="--d:' . $at(6, 'The fabric search') . ';margin-top:.35rem"><span class="lab">Fabric <i>*</i></span><span class="inp ph">Type to search fabrics (or click for recent)</span>
                        <div class="res"><span>Amaris / Beige <em>AAA</em></span><span>Ada / White <em>AA</em></span><span>Aria / Stone <em>A</em></span><span>Barclay / Pure <em>B</em></span>
                          <span>Arona / Stone <em>C</em></span><span>Alara / Slate <em>D</em></span><span>COLLINA / MOONLIGHT MINK <em>E</em></span><span><em>&hellip; 1300 more</em></span></div></div>
                    </div>
                    <div class="a-rise" style="--d:' . $at(6, 'Ticked, it starts') . '">
                      <div class="cap"><span class="tick on" style="width:12px;height:12px;vertical-align:middle">&#10003;</span> Ticked</div>
                      <div class="fld"><span class="lab">Band</span>' . $sel('AAA', 'a-ring', '--d:' . $at(6, 'starts on Triple A')) . '</div>
                      <div class="fld a-fade" style="--d:' . $at(6, 'only Triple A fabrics') . ';margin-top:.35rem"><span class="lab">Fabric <i>*</i></span><span class="inp ph">Type to search fabrics (or click for recent)</span>
                        <div class="res"><span>Amaris / Beige <em>AAA</em></span><span>Cameo / White <em>AAA</em></span><span>Cleo / Beige <em>AAA</em></span><span>Verona / Cream <em>AAA</em></span></div></div>
                    </div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(6, 'A system with only one') . '">one band in the system? It is picked for you, tick or no tick</span></div>
                </div>

                <!-- 7 — make default -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="ttl a-fade" style="--d:' . $at(7, 'Click the Fabrics') . '">Bev Vertical Fabrics Only &mdash; Fabrics</div>
                  <div class="alr a-drop" style="--d:' . $at(7, 'and the message') . '">Cameo is now the default &mdash; the order page fills it in to start with.</div>
                  <div class="pt fb a-rise" style="--d:' . $at(7, 'Click the Fabrics', 1) . '">
                    <div class="pr hd"><span><span class="tick cb">&#10003;</span></span><span>Band</span><span>Fabric</span><span>Colour</span><span>Supplier</span><span>Code</span><span></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band AAA</span></span><span>Amaris</span><span>Beige</span><span></span><span></span><span class="ra"><u>Edit</u><u>Make default</u><u class="del">Delete</u></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band AAA</span></span><span>Cameo <span class="bp def a-pop" style="--d:' . $at(7, 'A star Default') . '">&#9733; Default</span></span><span>White</span><span></span><span></span>
                      <span class="ra"><u>Edit</u><span class="sw"><u class="a-out a-ring" style="--d:' . $at(7, 'click Make default') . '">Make default</u><u class="a-fade" style="--d:' . $at(7, 'Remove default') . '">Remove default</u></span><u class="del">Delete</u></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band AAA</span></span><span>Cleo</span><span>Beige</span><span></span><span></span><span class="ra"><u>Edit</u><u>Make default</u><u class="del">Delete</u></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band AA</span></span><span>Polaris</span><span>Cream</span><span></span><span></span><span class="ra"><u>Edit</u><u>Make default</u><u class="del">Delete</u></span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(7, 'Changed your mind') . '">&#8617; same link: Remove default</span></div>
                </div>

                <!-- 8 — one per band -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">One default for each band</div>
                  <div class="pt fb a-rise" style="--d:.5s">
                    <div class="pr hd"><span><span class="tick cb">&#10003;</span></span><span>Band</span><span>Fabric</span><span>Colour</span><span>Supplier</span><span>Code</span><span></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band AAA</span></span><span>Cameo <span class="sw"><span class="bp def a-out" style="--d:' . $at(8, 'the old one is cleared') . '">&#9733; Default</span></span></span><span>White</span><span></span><span></span><span class="ra"><span class="sw"><u class="a-out" style="--d:' . $at(8, 'the old one is cleared') . '">Remove default</u><u class="a-fade" style="--d:' . $at(8, 'the old one is cleared') . '">Make default</u></span></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band AAA</span></span><span>Cleo <span class="bp def a-pop" style="--d:' . $at(8, 'Make another fabric') . '">&#9733; Default</span></span><span>Beige</span><span></span><span></span><span class="ra"><span class="sw"><u class="a-out a-ring" style="--d:' . $at(8, 'Make another fabric') . '">Make default</u><u class="a-fade" style="--d:' . $at(8, 'Make another fabric') . '">Remove default</u></span></span></div>
                  </div>
                  <div class="chips" style="margin-top:.8rem">'
                    . implode('', array_map(static fn ($b, $f, $i) => '<span class="chip a-pop" style="--d:' . $at(8, 'So on a vertical', $i * .9) . '"><span class="bp">' . $b . '</span> ' . $f . '</span>',
                        ['AAA', 'AA', 'A', 'B', 'C', 'D'], ['&#9733; Cleo / Beige', '&#9733; Polaris / Cream', '&#9733; Fleur / Linen', '&#9733; Barclay / Pure', '&#9733; Arona / Stone', '&#9733; Alara / Slate'], range(0, 5))) . '
                    <span class="chip a-pop" style="--d:' . $at(8, 'A band you leave') . ';color:var(--faint)"><span class="bp">E</span> no default &rarr; empty box</span>
                  </div>
                </div>

                <!-- 9 — when it fills in -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(9, 'When the product') . '">1 &middot; product picked</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'When the system') . '">2 &middot; system changed</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'when the band is') . '">3 &middot; band changed</span>
                  </div>
                  <div class="qp a-rise" style="--d:.3s">' . $qrow('Chainless', 'AAA', 'Cleo / Beige', [
                      'sys'  => '<span class="sw"><span class="sb a-out" style="--d:' . $at(9, 'When the system') . '">Chainless</span><span class="sb a-fade" style="--d:' . $at(9, 'When the system') . '">Chained</span></span>',
                      'band' => '<span class="sw"><span class="sb a-out" style="--d:' . $at(9, 'Pick band B') . '">AAA</span><span class="sb a-mid" style="--d:' . $at(9, 'Pick band B') . ';--d2:' . $at(9, 'If a band has no') . '">B</span><span class="sb a-fade" style="--d:' . $at(9, 'If a band has no') . '">E</span></span>',
                      'fab'  => '<span class="sw" style="display:grid"><span class="inp a-out" style="--d:' . $at(9, 'Pick band B') . '">Cleo / Beige</span>'
                              . '<span class="inp a-mid" style="--d:' . $at(9, 'B\'s default drops') . ';--d2:' . $at(9, 'If a band has no') . '"><span class="a-ring" style="--d:' . $at(9, 'B\'s default drops') . '">Barclay / Pure</span></span>'
                              . '<span class="a-fade" style="--d:' . $at(9, 'If a band has no') . '"><span class="inp ph">Type to search fabrics (or click for recent)</span>'
                              . '<span class="res" style="display:block"><span>COLLINA / MOONLIGHT MINK <em>E</em></span><span>CRUSH / NORDIC MIST <em>E</em></span><span>HAMPTON / SEA BREEZE <em>E</em></span></span></span></span>',
                    ]) . '</div>
                </div>

                <!-- 10 — bands that share fabrics -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="ttl a-fade" style="--d:' . $at(10, 'A wood venetian') . '">e.g. a wood venetian &mdash; Fabrics</div>
                  <div class="pt fb a-rise" style="--d:' . $at(10, 'A wood venetian', .4) . '">
                    <div class="pr hd"><span><span class="tick cb">&#10003;</span></span><span>Band</span><span>Slat</span><span>Colour</span><span>Supplier</span><span>Code</span><span></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band String</span></span><span>Elegant <span class="bp def a-pop" style="--d:' . $at(10, 'So making Elegant') . '">&#9733; Default</span></span><span>White</span><span></span><span></span><span class="ra"><u>Edit</u><u>Remove default</u></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band String</span></span><span>Elegant</span><span>Oak</span><span></span><span></span><span class="ra"><u>Edit</u><u>Make default</u></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp a-ring" style="--d:' . $at(10, 'A band with no default') . '">Band Tape</span></span><span>Elegant</span><span><span class="a-ring" style="--d:' . $at(10, 'same name and colour') . '">White</span></span><span></span><span></span><span class="ra"><u>Edit</u><u>Make default</u></span></div>
                    <div class="pr"><span><span class="tick cb">&#10003;</span></span><span><span class="bp">Band Tape</span></span><span>Elegant</span><span>Oak</span><span></span><span></span><span class="ra"><u>Edit</u><u>Make default</u></span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(10, 'covers Tape') . ';border-color:var(--accent)">Tape has no default &rarr; borrows <b>Elegant / White</b></span></div>
                </div>

                <!-- 11 — option order -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="ttl a-fade" style="--d:' . $at(11, 'Click the Options') . '">Bev Vertical Fabrics Only &mdash; Options</div>
                  <div class="sct a-fade" style="--d:' . $at(11, 'They are listed') . '">Options (4)</div>
                  <p class="scs a-fade" style="--d:' . $at(11, 'Drag the handle') . ';margin:0 0 .35rem">Drag the <b class="a-ring" style="--d:' . $at(11, 'Drag the handle', .5) . ';border-radius:4px;padding:0 .15rem">&#8942;&#8942;</b> handle to reorder.
                    <span class="sw"><span class="stat a-mid" style="--d:' . $at(11, 'Drag the handle', 1) . ';--d2:' . $at(11, 'Put the ones') . '">Saving&hellip;</span><span class="stat ok a-fade" style="--d:' . $at(11, 'Put the ones') . '">Saved</span></span></p>
                  <div class="swb">
                    <div class="pt ol a-out" style="--d:' . $at(11, 'Drag the handle', 1) . '"><div class="pr hd"><span></span><span>Name</span><span class="num">Choices</span><span>Updated</span><span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Colour</b><span class="pill rq">Required</span></span><span class="num">2</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Size</b><span class="pill rq">Required</span></span><span class="num">3</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Fabric Finish</b><span class="pill rq">Required</span></span><span class="num">2</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Bottom Weight Option</b><span class="pill rq">Required</span></span><span class="num">2</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div></div>
                    <div class="pt ol a-fade" style="--d:' . $at(11, 'Drag the handle', 1) . '"><div class="pr hd"><span></span><span>Name</span><span class="num">Choices</span><span>Updated</span><span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Size</b><span class="pill rq">Required</span></span><span class="num">3</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Fabric Finish</b><span class="pill rq">Required</span></span><span class="num">2</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Bottom Weight Option</b><span class="pill rq">Required</span></span><span class="num">2</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div>
                      <div class="pr"><span class="dg">&#8942;&#8942;</span><span><b>Colour</b><span class="pill rq">Required</span></span><span class="num">2</span><span class="upd">2 days ago</span><span class="lnk">Edit Duplicate <span class="del">Delete</span></span></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(11, 'Inside each option') . '">choices have a &#8942;&#8942; handle too &rarr; the dropdown&rsquo;s order</span></div>
                </div>

                <!-- 12 — default choice -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="ttl a-fade" style="--d:.2s">Size &mdash; Choices</div>
                  <div class="pt cg a-rise" style="--d:.5s">
                    <div class="pr hd"><span></span><span>Label</span><span style="text-align:center">Default</span><span style="text-align:center">Active</span></div>
                    <div class="pr"><span class="dg">&#8942;&#8942;</span><span>Hem to Hem</span><span class="a-ring" style="--d:' . $at(12, 'Tick Default') . ';border-radius:5px;justify-self:center;display:inline-flex"><span class="tick a-sel" style="--d:' . $at(12, 'Here, Hem') . '">&#10003;</span></span><span><span class="tick on">&#10003;</span></span></div>
                    <div class="pr"><span class="dg">&#8942;&#8942;</span><span>Recess</span><span><span class="tick">&#10003;</span></span><span><span class="tick on">&#10003;</span></span></div>
                    <div class="pr"><span class="dg">&#8942;&#8942;</span><span>Blind Size</span><span><span class="tick">&#10003;</span></span><span><span class="tick on">&#10003;</span></span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(12, 'There is one default') . '">one default per system &mdash; ticking another clears the first</span></div>
                  <div class="two" style="margin-top:.4rem;max-width:30rem">
                    <div class="fld a-rise" style="--d:' . $at(12, 'Here, Hem', 1.2) . '"><span class="lab">Size <i>*</i> &middot; with a default</span>' . $sel('Hem to Hem') . '</div>
                    <div class="fld a-rise" style="--d:' . $at(12, 'No default at all') . '"><span class="lab">Size <i>*</i> &middot; no default</span>' . $sel('&mdash; Select &mdash;', 'a-ring', '--d:' . $at(12, 'The box starts on Select')) . '</div>
                  </div>
                </div>

                <!-- 13 — above the size fields -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="two" style="grid-template-columns:1.1fr 1fr;max-width:38rem">
                    <div class="a-rise" style="--d:' . $at(13, 'click Edit') . '">
                      <div class="sct">Edit option: Size</div>
                      <div class="frm">
                        <div class="cbr"><span class="tick on">&#10003;</span><span>Required</span></div>
                        <div class="cbr"><span class="tick">&#10003;</span><span>Allow multiple choices</span></div>
                        <div class="cbr"><span class="a-ring" style="--d:' . $at(13, 'Tick Show above') . ';border-radius:5px;display:inline-flex"><span class="tick a-sel" style="--d:' . $at(13, 'Tick Show above', 1) . '">&#10003;</span></span>
                          <span>Show above the size fields<small>renders this option (and anything nested under it) before Width / Drop in the quote builder</small></span></div>
                        <div class="cbr"><span class="tick">&#10003;</span><span>Splits the blind into equal panels</span></div>
                        <div class="cbr"><span class="tick">&#10003;</span><span>Can be joined when longer than its width table</span></div>
                        <div class="cbr"><span class="tick on">&#10003;</span><span>Active</span></div>
                        <div style="margin-top:.45rem"><span class="btnp a-press" style="--d:' . $at(13, 'press Save changes') . '">Save changes</span></div>
                      </div>
                    </div>
                    <div class="a-rise" style="--d:' . $at(13, 'moves above Width') . '">
                      <div class="sct">Quote builder</div>
                      <div class="qp" style="padding:.45rem .55rem">
                        <div class="fld zone a-pop" style="--d:' . $at(13, 'moves above Width', .6) . '"><span class="lab">Size <i>*</i></span>' . $sel('Hem to Hem') . '</div>
                        <div class="qf qs"><div class="fld"><span class="lab">Width (mm) <i>*</i></span><span class="inp">1200</span></div><div class="fld"><span class="lab">Drop (mm) <i>*</i></span><span class="inp">1500</span></div><div class="fld"><span class="lab">Quantity</span><span class="inp">1</span></div></div>
                        <div class="qf qo"><div class="fld"><span class="lab">Fabric Finish <i>*</i></span>' . $sel('Welded') . '</div><div class="fld"><span class="lab">Colour <i>*</i></span>' . $sel('White') . '</div></div>
                      </div>
                      <p class="scs a-fade" style="--d:' . $at(13, 'InstaPrice already') . ';margin-top:.4rem">InstaPrice shows all its options above the size anyway.</p>
                    </div>
                  </div>
                </div>

                <!-- 14 — push -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(14, 'has its own copy') . '">Beverley Blinds &middot; Bev Vertical Fabrics Only</span>
                    <span class="arrow a-fade" style="--d:' . $at(14, 'after a catalogue push') . '">&rarr;</span>
                    <span class="chip a-pop" style="--d:' . $at(14, 'ABC Blinds') . '">ABC Blinds &middot; their copy</span>
                  </div>
                  <div class="ttl a-fade" style="--d:' . $at(14, 'from Platform') . '">Push catalogue updates</div>
                  <div class="a-rise" style="--d:' . $at(14, 'from Platform', .4) . '">
                    <div class="cap">1. Suppliers to push</div>
                    <div class="tr"><span class="tick a-sel" style="--d:' . $at(14, 'Push updates', 1) . '">&#10003;</span><b>Beverley Blinds Trade</b> <small>(Bev) &middot; 15 products</small></div>
                    <div class="cap" style="margin-top:.5rem">2. Tenants to push to</div>
                    <div class="tr"><span class="tick a-sel" style="--d:' . $at(14, 'Push updates', 2) . '">&#10003;</span>ABC Blinds</div>
                    <div style="margin-top:.5rem"><span class="btnp btnr a-press" style="--d:' . $at(14, 'The push carries') . '">Push to selected tenants &raquo;</span></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(14, 'the band order') . '">band order</span>
                    <span class="chip a-pop" style="--d:' . $at(14, 'the tick') . '">the tick</span>
                    <span class="chip a-pop" style="--d:' . $at(14, 'the default fabrics') . '">default fabrics</span>
                    <span class="chip a-pop" style="--d:' . $at(14, 'the option defaults') . '">option order &amp; defaults</span>
                  </div>
                </div>

                <!-- 15 — check in InstaPrice -->
                <div class="sc" data-scene="15" data-len="' . $len(15) . '">
                  <div class="ttl a-fade" style="--d:' . $at(15, 'Open InstaPrice') . '">InstaPrice</div>
                  <div class="qp a-rise" style="--d:' . $at(15, 'Open InstaPrice', .5) . '">' . $qrow('Chainless', 'AAA', 'Cleo / Beige', [
                      'ip'   => true,
                      'sys'  => '<span class="sw"><span class="sb a-out" style="--d:' . $at(15, 'Chainless,') . '">&mdash; Choose system &mdash;</span><span class="sb a-fade" style="--d:' . $at(15, 'Chainless,') . '">Chainless</span></span>',
                      'band' => '<span class="sw"><span class="sb a-out" style="--d:' . $at(15, 'Triple A') . '">All bands</span><span class="sb a-fade" style="--d:' . $at(15, 'Triple A') . '">AAA</span></span>',
                      'fab'  => '<span class="sw" style="display:grid"><span class="inp ph a-out" style="--d:' . $at(15, 'your default fabric') . '">Type to search fabrics&hellip;</span><span class="inp a-fade" style="--d:' . $at(15, 'your default fabric') . '">Cleo / Beige</span></span>',
                    ]) . '
                    <div class="qf qo a-fade" style="--d:' . $at(15, 'and the options') . '"><div class="fld"><span class="lab">Size <i>*</i></span>' . $sel('Hem to Hem') . '</div>
                      <div class="fld"><span class="lab">Fabric Finish <i>*</i></span>' . $sel('Welded') . '</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(15, 'If something starts') . '">System &rarr; Systems &middot; Band &rarr; Price tables + the tick</span>
                    <span class="chip a-pop" style="--d:' . $at(15, 'If something starts', 1) . '">Fabric &rarr; Fabrics &middot; Options &rarr; Options</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p class="prose">Four settings on a product decide what the order page &mdash; the quote builder&rsquo;s <b>Add blind</b> form and
             <b>InstaPrice</b> &mdash; fills in before anyone types. All four are reached from the product&rsquo;s own page:
             <b>Products</b> &rarr; the product (<em>Edit Bev Vertical Fabrics Only</em>), with its <b>Setting this product up</b> tiles
             <b>Systems</b>, <b>Fabrics</b>, <b>Price tables</b> and <b>Options</b>.</p>

          <p class="prose"><b>0) The system it starts on</b> &mdash; <b>Systems</b> tile. The system with the <b>Default</b> pill is the one the
             <b>System</b> box starts on; <b>Set default</b> on another row moves it. With no default the box reads <em>&mdash; Choose system &mdash;</em>.
             Drag <b>&#8942;&#8942;</b> to change the order of the systems in that box.</p>

          <p class="prose"><b>1) Band order</b> &mdash; <b>Price tables</b> tile, then a system (<em>Bev Vertical Fabrics Only &mdash; Chainless</em>).</p>
          <ul class="steps">
            <li>Under <b>Price tables (7)</b>: <em>Drag the &#8942;&#8942; handle to reorder bands.</em> It shows <em>Saving&hellip;</em>, then
                <em>Saved</em> (or <em>Save failed</em>).</li>
            <li>The <b>Band</b> box lists the bands in this order, under <em>All bands</em> &mdash; in the quote builder and in InstaPrice.</li>
            <li><b>Each system has its own order.</b> On Bev Vertical Fabrics Only, set Chainless <em>and</em> Chained (AAA, AA, A, B, C, D, E).</li>
          </ul>

          <p class="prose"><b>2) Start on the first band</b> &mdash; on the product&rsquo;s own page, under the <b>Band label</b> box.</p>
          <ul class="steps">
            <li>Tick <b>Start on the first band instead of <em>All bands</em></b> and press <b>Save changes</b> at the bottom of the page.
                (<em>The first band is the top one in each system&rsquo;s Price tables order &mdash; drag that list to change it.</em>)</li>
            <li><b>Ticked:</b> the Band box starts on the top band (AAA here), and the fabric search shows only that band&rsquo;s fabrics.</li>
            <li><b>Unticked:</b> it starts on <em>All bands</em>, and the fabric search covers every band.</li>
            <li>A system with <b>only one band</b> selects it for you either way.</li>
            <li>If you change the band yourself, your choice is kept when the list refreshes.</li>
          </ul>

          <p class="prose"><b>3) Default fabric</b> &mdash; <b>Fabrics</b> tile (<em>Bev Vertical Fabrics Only &mdash; Fabrics</em>).</p>
          <ul class="steps">
            <li>Click <b>Make default</b> at the end of the fabric&rsquo;s row. It gets a <b>&#9733; Default</b> pill and the page says
                <code>Cameo is now the default &mdash; the order page fills it in to start with.</code> The link becomes <b>Remove default</b>
                (<code>Cameo is no longer the default.</code>).</li>
            <li><b>One default per band.</b> Making another fabric in the same band the default clears the old one. Give each band its own,
                or leave a band without one &mdash; its fabric box simply starts empty.</li>
            <li>It fills in when the <b>product is picked</b>, when the <b>system is changed</b> and when the <b>band is changed</b> (only if no fabric is
                chosen yet). Change the band to one with no default and the fabric list opens, showing that band&rsquo;s fabrics.</li>
            <li><b>Bands that share fabrics:</b> a band with no default of its own borrows the fabric with the <b>same name and colour</b> as a default in
                another band &mdash; so <em>Elegant / White</em> made default on String also covers Tape on a wood venetian.</li>
          </ul>

          <p class="prose"><b>4) Options: order and defaults</b> &mdash; <b>Options</b> tile (<em>Bev Vertical Fabrics Only &mdash; Options</em>).</p>
          <ul class="steps">
            <li><b>Order:</b> <em>Drag the &#8942;&#8942; handle to reorder.</em> The order page shows the options in this order. Click an option&rsquo;s
                name to reach its choices (<em>Size &mdash; Choices</em>), where <b>&#8942;&#8942;</b> orders the dropdown itself.</li>
            <li><b>Default choice:</b> tick <b>Default</b> on the choice that should come already picked (<em>Default = pre-selected for the customer</em>).
                One per system &mdash; ticking another clears the first. No default and the box starts on <em>&mdash; Select &mdash;</em>.</li>
            <li><b>Show above the size fields:</b> <b>Edit</b> beside the option on the Options list &rarr; tick <b>Show above the size fields</b> &rarr;
                <b>Save changes</b>. That option, and anything nested under it, sits above <b>Width</b> / <b>Drop</b> in the quote builder.
                InstaPrice already shows its options above the size, so it makes no difference there.</li>
          </ul>

          <p class="prose"><b>Other companies&rsquo; copies.</b> A company such as ABC Blinds has its own copy of Bev Vertical Fabrics Only. These settings only
             reach it after a catalogue push &mdash; <b>Platform &rarr; Push updates</b>, tick the supplier and the company, then
             <b>Push to selected tenants &raquo;</b>. The push copies the band order, the tick, the default fabrics, the default system and the option order
             and defaults with everything else.</p>

          <div class="oops"><b>Check it in InstaPrice or the quote builder.</b> Pick the product and watch the boxes fill in. The <b>Live preview</b> button
             on the product&rsquo;s page does <em>not</em> use the <em>Start on the first band</em> tick or the default fabric, so it always starts on
             <em>All bands</em> with an empty fabric box &mdash; don&rsquo;t judge these settings by it.</div>',
        'script'  => array_map(static fn ($k, $l) => [$l[0], $l[1], $l[2], $k], array_keys($S), $S),
];
