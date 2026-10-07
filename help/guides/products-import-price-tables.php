<?php
declare(strict_types=1);

/**
 * Guide: products-import-price-tables — "Building price tables" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script (and optionally js).
 *
 * Mirrors /admin/products/price-table.php — the 2-D grid editor, its
 * price-source strip and trade-terms form, the empty-state clone tile, the
 * "Start your grid" dialog, saving, the uplift bar, the Undo strip
 * (_partials/price_table_undo.php), reshaping and the other pricing shapes.
 * Every label, button and message is taken from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

// ── Demo data: one band's grid (widths across, drops down) ────────────
$W = [800, 1200, 1600, 2000];
$D = [800, 1200, 1600, 2000];
$P = [                                  // [drop][width index]
    800  => [22.80, 28.50, 34.20, 40.60],
    1200 => [26.40, 33.20, 40.00, 46.80],
    1600 => [30.90, 38.40, 45.80, 53.70],
    2000 => [35.20, 43.80, 52.10, 61.40],
];
$money = static fn (float $v): string => number_format($v, 2);

/**
 * A price grid. $cell($drop, $wi, $ri, $price) returns [extraClass, style,
 * innerHtml] for each price square; $head($w, $wi) / $side($d, $ri) the
 * same for the width headers / drop labels.
 */
$grid = static function (callable $cell, ?callable $head = null, ?callable $side = null, string $cls = '') use ($W, $D, $P): string {
    $h  = '<div class="pg ' . $cls . '"><span class="pc hd corner">Drop \\ Width (mm)</span>';
    foreach ($W as $wi => $w) {
        [$c, $s, $in] = $head ? $head($w, $wi) : ['', '', (string) $w];
        $h .= '<span class="pc hd ' . $c . '" style="' . $s . '">' . $in . '</span>';
    }
    foreach ($D as $ri => $d) {
        [$c, $s, $in] = $side ? $side($d, $ri) : ['', '', (string) $d];
        $h .= '<span class="pc hd ' . $c . '" style="' . $s . '">' . $in . '</span>';
        foreach ($W as $wi => $w) {
            [$c, $s, $in] = $cell($d, $wi, $ri, $P[$d][$wi]);
            $h .= '<span class="pc ' . $c . '" style="' . $s . '">' . $in . '</span>';
        }
    }
    return $h . '</div>';
};
$plain = static fn ($d, $wi, $ri, $p) => ['', '', number_format($p, 2)];

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// Scene 1 — find a price: column 1200 lights, then row 1600; they meet at 38.40.
$g1 = $grid(
    static function ($d, $wi, $ri, $p) use ($money) {
        $c = [];
        if ($wi === 1 || $d === 1600) $c[] = 'hl';
        if ($wi === 1 && $d === 1600) { $c[] = 'hit'; $c[] = 'a-ring'; }
        $lit = $wi === 1 ? 8.5 : 12;                      // column first, then the row
        return [implode(' ', $c) . ' a-fade', '--d:' . (3 + $ri * .35 + $wi * .1) . 's;--d2:' . $lit . 's', $money($p)];
    },
    static fn ($w, $wi) => [$wi === 1 ? 'a-sel' : 'a-fly', '--d:' . ($wi === 1 ? 8.5 : .6 + $wi * .3) . 's', (string) $w],   // (one animation per element: a-sel wins over a-fly)
    static fn ($d, $ri) => [$d === 1600 ? 'a-sel' : 'a-fly', '--d:' . ($d === 1600 ? 12 : 1.8 + $ri * .3) . 's', (string) $d]
);

// Scene 4 — cloned: every square pops in, row by row.
$g4 = $grid(static fn ($d, $wi, $ri, $p) => ['a-pop', '--d:' . (9 + $ri * .45 + $wi * .12) . 's', number_format($p, 2)]);

// Scene 6 — pasted: prices pour right and down from the top-left square.
$g6 = $grid(static fn ($d, $wi, $ri, $p) => [($ri === 0 && $wi === 0 ? 'tl' : ''), '',
    '<span class="a-pop" style="--d:' . (13.5 + ($ri + $wi) * .35) . 's">' . number_format($p, 2) . '</span>']);

// Scene 7 — the edge: a fifth pasted column falls off; one square left empty.
$g7 = $grid(static fn ($d, $wi, $ri, $p) => $d === 2000 && $wi === 3
    ? ['blank a-ring', '--d:17s', '<span class="nope a-pop" style="--d:17s">no price</span>']
    : ['', '', number_format($p, 2)]);

// Scenes 12/13 — a price rise and its undo: the figures swap.
$up  = static fn (float $p): float => round($p * 1.05, 2);
$g12 = $grid(static fn ($d, $wi, $ri, $p) => ['swap', '',
    '<span class="a-out" style="--d:' . (13 + $ri * .3 + $wi * .1) . 's">' . number_format($p, 2) . '</span>'
  . '<span class="a-fade" style="--d:' . (13 + $ri * .3 + $wi * .1) . 's">' . number_format($up($p), 2) . '</span>']);
$g13 = $grid(static fn ($d, $wi, $ri, $p) => ['swap', '',
    '<span class="a-out" style="--d:' . (15 + $ri * .3 + $wi * .1) . 's">' . number_format($up($p), 2) . '</span>'
  . '<span class="a-fade" style="--d:' . (15 + $ri * .3 + $wi * .1) . 's">' . number_format($p, 2) . '</span>']);

// Scene 14 — reshape: 1600 renamed to 1800, its prices stay.
$g14 = $grid($plain,
    static fn ($w, $wi) => $wi === 2
        ? ['axis a-ring', '--d:4s', '<span class="a-out" style="--d:10s">1600</span><span class="a-fade" style="--d:10s">1800</span><i class="x">&times;</i>']
        : ['axis', '', $w . '<i class="x">&times;</i>']);

$sheet = implode('', array_map(static fn ($r) => implode('', array_map(static fn ($v) => '<span>&pound;' . number_format($v, 2) . '</span>', $r)), $P));

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Building price tables',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'What a price table is, how to clone or build one, paste in your supplier\'s prices, set your buying discount and markup, check the sums, put prices up — and undo a mistake.',
        'lede'    => 'A price table is <b>one band&rsquo;s grid of prices</b>: the widths your supplier sells run across the top,
                      the drops run down the side, and the price for any blind sits where its width and drop meet. This guide
                      walks through one band from empty to finished &mdash; <b>slowly</b>, one idea per chapter. Watch it all the
                      way through once, then use <b>Jump to a chapter</b> to go back over any part. To get to a table:
                      <b>Products</b> &rarr; the product &rarr; <b>Price tables</b> &rarr; <b>Open</b> on the band.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* the price grid */
          .gd .pg{ display:grid; grid-template-columns:4.6rem repeat(4,1fr); gap:1px; background:var(--line);
                   border:1px solid var(--line); border-radius:8px; padding:1px; max-width:26rem; position:relative; }
          .gd .pc{ background:var(--surface); padding:.32rem .2rem; text-align:center; font-size:.74rem; color:var(--ink);
                   font-variant-numeric:tabular-nums; position:relative; min-height:1.6rem; }
          .gd .pc.hd{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.66rem; }
          .gd .pc.corner{ font-size:.56rem; line-height:1.15; }
          .gd .pc.hit{ font-weight:800; outline:2px solid var(--accent); outline-offset:-2px; }
          .gd .gd-play .hl{ animation:hpHl .5s ease calc(var(--d2,0s) * var(--k,1)) forwards, gdFade .6s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .hl{ background:var(--accent-wash); }
          @keyframes hpHl{ to{ background:var(--accent-wash); } }
          .gd .pc.swap, .gd .pc.axis{ display:grid; place-items:center; }
          .gd .pc.swap > span, .gd .pc.axis > span{ grid-area:1/1; }
          .gd .pc.axis .x{ position:absolute; right:3px; top:2px; font-style:normal; color:var(--faint); font-size:.6rem; }
          .gd .pc.blank{ background:var(--err-wash); }
          .gd .pc .nope{ font-size:.58rem; font-weight:700; color:var(--err); }
          .gd .pc.tl{ outline:2px solid var(--accent); outline-offset:-2px; }

          /* little helpers */
          .gd .tst{ position:absolute; right:0; top:0; background:var(--good-wash); color:var(--good); font-weight:700; font-size:.72rem;
                    border:1px solid color-mix(in srgb,var(--good) 35%,transparent); border-radius:8px; padding:.4rem .65rem; max-width:17rem; z-index:4; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; }
          .gd .lnk{ color:var(--accent); font-weight:600; text-decoration:underline; border-radius:4px; }
          .gd .ttl{ font-size:.86rem; font-weight:800; color:var(--ink); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.74rem; font-weight:700; color:var(--ink); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .box2{ display:inline-flex; align-items:center; min-width:4.5rem; height:28px; border:1px solid var(--border-strong,#c7ccd4);
                     border-radius:6px; background:var(--surface); padding:0 .5rem; font-size:.8rem; font-variant-numeric:tabular-nums; }

          /* 2 — the band list */
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.9rem; }
          .gd .blist{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:24rem; }
          .gd .blist div{ display:flex; align-items:center; justify-content:space-between; padding:.4rem .6rem; font-size:.74rem; border-top:1px solid var(--line); }
          .gd .blist div:first-child{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.64rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .ptitle{ margin-top:1rem; font-size:.95rem; font-weight:800; color:var(--ink); }

          /* 3 — whose prices */
          .gd .psbar{ border:1px solid #f59e0b; background:rgba(245,158,11,.08); border-radius:10px; padding:.5rem .7rem;
                      display:flex; gap:.5rem; align-items:baseline; flex-wrap:wrap; margin-bottom:.9rem; }
          .gd .psbar > b{ font-size:.8rem; color:var(--ink); }
          .gd .pshint{ font-size:.68rem; color:var(--faint); }
          .gd .pschg{ margin-left:auto; color:var(--accent); font-size:.7rem; font-weight:700; border-radius:5px; padding:0 .2rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
          .gd .kind{ border:1px solid var(--line); border-radius:10px; padding:.65rem .75rem; background:var(--surface); }
          .gd .kind h4{ margin:0 0 .35rem; font-size:.82rem; color:var(--ink); }
          .gd .kind p{ margin:.4rem 0 0; font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .sum{ display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; font-size:.72rem; font-weight:700; }
          .gd .sum span{ border-radius:6px; padding:.15rem .4rem; background:var(--panel); }
          .gd .sum .m{ background:var(--err-wash); color:var(--err); } .gd .sum .p{ background:var(--good-wash); color:var(--good); }

          /* 4 — empty tile */
          .gd .qtile{ border:1px dashed var(--line); border-radius:10px; padding:.7rem .8rem; background:var(--panel); text-align:center; max-width:26rem; margin-bottom:.8rem; }
          .gd .qtile .ttl{ margin-bottom:.3rem; }
          .gd .qtile p{ font-size:.66rem; color:var(--soft); margin:0 0 .55rem; }
          .gd .clone{ display:block; background:var(--accent); color:#fff; border-radius:7px; padding:.4rem .7rem; font-size:.74rem; font-weight:700; margin:0 auto .3rem; max-width:22rem; }
          .gd .clone2{ display:block; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:7px; padding:.36rem .7rem; font-size:.72rem; font-weight:600; margin:0 auto; max-width:22rem; }

          /* 5 — start your grid */
          .gd .dlg{ border:1px solid var(--line); border-radius:12px; padding:.75rem .85rem; background:var(--surface); box-shadow:var(--gd-shadow); max-width:27rem; }
          .gd .dlg p{ font-size:.66rem; color:var(--faint); margin:.2rem 0 .55rem; }
          .gd .dl{ display:flex; justify-content:space-between; font-size:.66rem; font-weight:700; color:var(--soft); margin:.45rem 0 .2rem; }
          .gd .ta2{ position:relative; display:grid; border:1px solid var(--line); border-radius:7px; background:var(--panel); padding:.4rem .55rem;
                    font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.74rem; min-height:1.9rem; align-items:center; }
          .gd .ta2 > span{ grid-area:1/1; }
          .gd .ta2 .sugg{ color:var(--faint); }
          .gd .dlgact{ display:flex; gap:.4rem; justify-content:flex-end; margin-top:.6rem; }
          .gd .conv{ font-size:.66rem; font-weight:700; color:var(--good); margin-top:.3rem; }

          /* 6 — Excel sheet + grid */
          .gd .side2{ display:grid; grid-template-columns:1fr 1.25fr; gap:1rem; align-items:start; }
          .gd .xl{ border:1px solid #1d6f42; border-radius:8px; overflow:hidden; font-size:.66rem; background:#fff; color:#1f2937; position:relative; }
          .gd .xl .xh{ background:#1d6f42; color:#fff; font-weight:700; padding:.3rem .5rem; font-size:.66rem; }
          .gd .xl .xg{ display:grid; grid-template-columns:repeat(4,1fr); }
          .gd .xl .xg span{ border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:.25rem .2rem; text-align:right; font-variant-numeric:tabular-nums; }
          .gd .xl .sel{ position:absolute; left:0; right:0; top:1.55rem; bottom:0; border:2px dashed #1d6f42; background:rgba(29,111,66,.08); }
          .gd .kbd{ display:inline-block; background:#0f172a; color:#fff; border-radius:6px; padding:.2rem .45rem; font-size:.68rem; font-weight:800;
                    font-family:ui-monospace,Menlo,monospace; box-shadow:0 3px 0 #334155; }

          /* 7 — over the edge */
          .gd .edgewrap{ position:relative; display:inline-block; margin-top:2rem; }
          .gd .spill{ position:absolute; top:1px; bottom:1px; left:calc(100% + 4px); width:4.2rem; display:flex; flex-direction:column; gap:1px;
                      border:2px dashed var(--err); border-radius:6px; padding:1px; }
          .gd .spill span{ flex:1; display:grid; place-items:center; font-size:.7rem; color:var(--err); background:var(--err-wash); font-variant-numeric:tabular-nums; }
          .gd .gd-play .fall{ animation:hpFall 1.2s ease-in calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .fall{ opacity:0; }
          @keyframes hpFall{ to{ transform:translateY(60px) rotate(8deg); opacity:0; } }
          .gd .tag7a{ left:0; top:1.6rem; } .gd .tag7b{ left:0; bottom:2.2rem; }

          /* 9 — terms */
          .gd .terms{ display:flex; align-items:flex-end; gap:.8rem; flex-wrap:wrap; flex-basis:100%; border-top:1px solid var(--line); padding-top:.55rem; margin-top:.4rem; }
          .gd .terms label{ display:block; font-size:.6rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.2rem; }

          /* 10 — the sum, step by step */
          .gd .chain{ display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; margin:1.2rem 0 1.1rem; }
          .gd .chain .v{ display:flex; flex-direction:column; align-items:center; border:1px solid var(--line); border-radius:10px; padding:.45rem .7rem;
                         background:var(--surface); min-width:5rem; }
          .gd .chain .v b{ font-size:1.05rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .chain .v small{ font-size:.6rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .chain .op{ font-size:.72rem; font-weight:800; border-radius:999px; padding:.15rem .5rem; }
          .gd .chain .op.m{ background:var(--err-wash); color:var(--err); } .gd .chain .op.p{ background:var(--good-wash); color:var(--good); }
          .gd .chain .v.out{ border-color:var(--good); } .gd .chain .v.out b{ color:var(--good); }
          .gd .bigcell{ display:inline-flex; flex-direction:column; align-items:center; border:2px solid var(--accent); border-radius:9px; padding:.4rem .9rem; }
          .gd .bigcell b{ font-size:1.1rem; } .gd .bigcell small{ font-size:.66rem; color:var(--faint); }

          /* 11 — three things */
          .gd .three{ display:grid; grid-template-columns:repeat(3,1fr); gap:.7rem; }
          .gd .note{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); font-size:.7rem; color:var(--soft); line-height:1.45; }
          .gd .note h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .green{ color:#065f46; font-weight:800; font-size:.86rem; } .gd .green small{ display:block; color:var(--faint); font-weight:400; font-size:.62rem; }
          :root[data-theme="dark"] .gd .green{ color:#34d399; }

          /* 12/13 — uplift + undo */
          .gd .upbar{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; padding:.4rem .55rem; background:var(--panel); border:1px solid var(--line);
                      border-radius:8px; margin-bottom:.6rem; font-size:.7rem; font-weight:700; color:var(--soft); }
          .gd .undobar{ display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; background:var(--panel); border:1px solid var(--line); border-radius:8px;
                        padding:.45rem .6rem; margin-bottom:.6rem; font-size:.72rem; color:var(--soft); }
          .gd .undobar .btns{ margin-left:auto; }
          .gd .confirm{ position:absolute; z-index:5; left:12%; top:32%; max-width:20rem; background:var(--surface); border:1px solid var(--line);
                        border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.72rem; color:var(--ink); }
          .gd .confirm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }

          /* 14 — rename */
          .gd .renpop{ display:inline-flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-top:.7rem; border:1px solid var(--line); border-radius:10px;
                       padding:.5rem .65rem; background:var(--surface); box-shadow:var(--gd-shadow); font-size:.72rem; color:var(--soft); }
          .gd .tools{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }

          /* 15 — other editors */
          .gd .mini{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.7rem; }
          .gd .mini h4{ margin:0 0 .4rem; font-size:.76rem; color:var(--ink); }
          .gd .mini .row2{ display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:6px; overflow:hidden; }
          .gd .mini .row2 span{ background:var(--surface); padding:.2rem .3rem; font-variant-numeric:tabular-nums; }
          .gd .mini .row2 span.h{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.62rem; }
          .gd .adv{ margin-top:.8rem; border:1px solid var(--line); border-radius:9px; padding:.5rem .65rem; background:var(--panel); font-size:.72rem;
                    font-weight:700; color:var(--soft); display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }

          @media (max-width:640px){
            .gd .two, .gd .three, .gd .side2{ grid-template-columns:1fr; }
            .gd .sc{ min-height:430px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / price table</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a class="on">Products</a><a>Users</a><a>Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="sct">Building a price table</div>
                  <p class="scs">One band&rsquo;s grid: widths across the top, drops down the side.</p>
                  ' . $grid($plain) . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what a price table is -->
                <div class="sc" data-scene="1" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">What is a price table?</div>
                  <p class="scs a-fade" style="--d:.4s">Widths across the top &middot; drops down the side &middot; the price where they meet.</p>
                  ' . $g1 . '
                  <div style="margin-top:.9rem;display:flex;gap:.5rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:15s;border-color:var(--accent)">1200 wide &times; 1600 drop = <b>&pound;38.40</b></span>
                    <span class="chip a-pop" style="--d:21s">&#127991; Every band of fabric has its own table</span>
                  </div>
                </div>

                <!-- 2 — getting there -->
                <div class="sc" data-scene="2" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Getting there</div>
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:1.5s">Products</span><span class="arrow a-fade" style="--d:2.4s">&rarr;</span>
                    <span class="chip a-pop" style="--d:3.2s">25mm Venetian</span><span class="arrow a-fade" style="--d:4.2s">&rarr;</span>
                    <span class="chip a-pop" style="--d:5s">Price tables</span><span class="arrow a-fade" style="--d:6s">&rarr;</span>
                    <span class="chip a-pop" style="--d:9.5s">Open</span>
                  </div>
                  <div class="blist a-rise" style="--d:6.5s">
                    <div><span>Standard &mdash; bands</span><span>&nbsp;</span></div>
                    <div><span>Band A &middot; 16 cells</span><span class="btnp a-ring" style="--d:9.5s">Open</span></div>
                    <div><span>Band B &middot; empty</span><span class="btns">Open</span></div>
                    <div><span>Band C &middot; empty</span><span class="btns">Open</span></div>
                  </div>
                  <div class="ptitle"><span class="a-type" style="--d:13s;--tt:1.6s;--ts:30">25mm Venetian / Standard &mdash; Band A</span></div>
                  <p class="scs a-fade" style="--d:15s">The title always says which product, which system and which band.</p>
                </div>

                <!-- 3 — whose prices -->
                <div class="sc" data-scene="3" data-len="31">
                  <div class="sct a-fade" style="--d:.2s">Whose prices are these?</div>
                  <div class="psbar a-rise" style="--d:1.5s">
                    <b>Supplier price list</b>
                    <span class="pshint">These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.</span>
                    <span class="pschg a-ring" style="--d:25s">Change</span>
                  </div>
                  <div class="two">
                    <div class="kind a-rise" style="--d:6s">
                      <h4>&ldquo;Supplier price list&rdquo;</h4>
                      <div class="sum"><span>Their list</span><span class="m a-pop" style="--d:10s">&minus; your buying discount</span><span class="p a-pop" style="--d:12s">+ your markup</span></div>
                      <p>You type the supplier&rsquo;s figures. The system works out your selling price.</p>
                    </div>
                    <div class="kind a-rise" style="--d:15s">
                      <h4>&ldquo;Our price list&rdquo;</h4>
                      <div class="sum"><span>Your selling price</span><span class="p a-pop" style="--d:18s">used exactly as typed</span></div>
                      <p>You type your own prices. Nothing is added or taken off.</p>
                    </div>
                  </div>
                  <p class="scs a-fade" style="--d:25s;margin-top:.8rem">&#9888; Wrong one? Click <b>Change</b> before anything else.</p>
                </div>

                <!-- 4 — clone -->
                <div class="sc" data-scene="4" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">An empty table &mdash; clone one that&rsquo;s done</div>
                  <div class="qtile a-rise" style="--d:.8s">
                    <div class="ttl">This price table is empty</div>
                    <p>Other bands on this product are filled in. Pick one to clone &mdash; same widths, drops and prices &mdash; then tweak the cells that differ.</p>
                    <span class="clone a-press a-ring" style="--d:7.6s">Clone <b>Standard &mdash; Band B</b> (16 cells)</span>
                    <span class="clone2">Clone <b>Slimline &mdash; Band A</b> (16 cells)</span>
                  </div>
                  <div class="a-move" style="--fx:80%;--fy:95%;--tx:52%;--ty:7.3rem;--d:5.5s;--md:1.8s">' . $ptr . '</div>
                  ' . $g4 . '
                  <span class="tst a-pop" style="--d:12s">Copied 16 cells &mdash; tweak the prices that differ, then Save.</span>
                </div>

                <!-- 5 — start your grid -->
                <div class="sc" data-scene="5" data-len="31">
                  <div class="sct a-fade" style="--d:.2s">Nothing to clone? Start your grid</div>
                  <div class="dlg a-rise" style="--d:1.5s">
                    <div class="ttl">Start your grid</div>
                    <p>Type or paste the widths and drops your supplier sells in &mdash; a row or a column from Excel, commas, tabs and new lines all work. It reads decimals and metres too.</p>
                    <div class="dl"><span>Widths (mm)</span><span class="lnk a-ring" style="--d:8s">Clear</span></div>
                    <div class="ta2">
                      <span class="sugg a-out" style="--d:9s">800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000</span>
                      <span class="a-mid" style="--d:12s;--d2:19s"><span class="a-type" style="--d:12s;--ts:16">0.8, 1.2, 1.6, 2.0</span></span>
                      <span class="a-fade" style="--d:19s">800, 1200, 1600, 2000</span>
                    </div>
                    <div class="conv a-fade" style="--d:19.5s">&#10003; metres understood &mdash; 0.8 becomes 800&nbsp;mm</div>
                    <div class="dl"><span>Drops (mm)</span><span class="lnk">Clear</span></div>
                    <div class="ta2"><span class="sugg a-out" style="--d:22s">800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000</span><span class="a-fade" style="--d:22.5s">800, 1200, 1600, 2000</span></div>
                    <div class="dlgact"><span class="btns">Cancel</span><span class="btnp a-press a-ring" style="--d:27s">Build grid</span></div>
                  </div>
                </div>

                <!-- 6 — paste -->
                <div class="sc" data-scene="6" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Paste the prices in</div>
                  <div class="side2">
                    <div>
                      <div class="xl a-rise" style="--d:3s">
                        <div class="xh">Supplier price list.xlsx</div>
                        <div class="xg">' . $sheet . '</div>
                        <div class="sel a-wide" style="--d:6s"></div>
                      </div>
                      <p class="scs" style="margin-top:.5rem"><span class="kbd a-pop" style="--d:8s">Ctrl + C</span> <span class="a-fade" style="--d:8.3s">copy the block</span></p>
                    </div>
                    <div>
                      ' . $g6 . '
                      <p class="scs" style="margin-top:.5rem"><span class="kbd a-pop" style="--d:12.5s">Ctrl + V</span> <span class="a-fade" style="--d:12.8s">into the top-left square</span></p>
                      <span class="chip a-pop" style="--d:19s">&pound; signs and commas are cleaned off</span>
                    </div>
                  </div>
                  <div class="a-move" style="--fx:20%;--fy:90%;--tx:52%;--ty:3.4rem;--d:9.5s;--md:2s">' . $ptr . '</div>
                </div>

                <!-- 7 — two catches -->
                <div class="sc" data-scene="7" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">Two things that catch people out</div>
                  <div><span class="chip a-pop" style="--d:5s;border-color:var(--err);color:var(--err)">&#10007; Past the edge of the grid &mdash; thrown away</span></div>
                  <div class="edgewrap a-rise" style="--d:1s;margin-top:.8rem">
                    ' . $g7 . '
                    <div class="spill fall" style="--d:8.5s"><span>&nbsp;</span><span>48.10</span><span>55.90</span><span>63.20</span><span>71.00</span></div>
                  </div>
                  <p class="scs a-fade" style="--d:11s;margin-top:.7rem;max-width:26rem">Sheet wider than the grid? <b>Add the extra widths first</b>, then paste.</p>
                  <span class="chip a-pop" style="--d:18s;border-color:var(--err);color:var(--err)">An empty square = no price at that size</span>
                </div>

                <!-- 8 — save -->
                <div class="sc" data-scene="8" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Save the grid</div>
                  <div class="bnr a-rise" style="--d:5s">&#10003; Saved 16 price cells.
                    <span class="btnp a-ring" style="--d:12s;margin-left:auto">Next: Standard &mdash; Band B &rarr;</span></div>
                  ' . $grid($plain) . '
                  <div class="tools"><span class="btnp a-press a-ring" style="--d:2.5s">Save grid</span><span class="btns">Edit sizes</span><span class="btns">Cancel</span></div>
                  <p class="scs a-fade" style="--d:7s;margin-top:.5rem">Saving replaces every cell in this table with what&rsquo;s on screen.</p>
                </div>

                <!-- 9 — discount + markup -->
                <div class="sc" data-scene="9" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Your buying discount and your markup</div>
                  <div class="psbar">
                    <b>Supplier price list</b>
                    <span class="pshint">Our price = list &minus; buying discount + margin.</span>
                    <div class="terms a-rise" style="--d:3s">
                      <div><label>Buying discount %</label><span class="box2 a-ring" style="--d:7s"><span class="a-type" style="--d:8s;--ts:2;--tt:.4s">25</span></span></div>
                      <div><label>Our markup %</label><span class="box2 a-ring" style="--d:12s"><span class="a-type" style="--d:13s;--ts:3;--tt:.5s">100</span></span></div>
                      <span class="btns a-press" style="--d:16s">Save terms</span>
                    </div>
                  </div>
                  <div class="bnr a-pop" style="--d:17s">&#10003; Trade terms saved for this system.</div>
                  <span class="chip a-pop" style="--d:20s">Covers <b>every band</b> on Standard &mdash; set it once</span>
                </div>

                <!-- 10 — the sum -->
                <div class="sc" data-scene="10" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">Check the sums without leaving the page</div>
                  <div class="chain">
                    <div class="v a-pop" style="--d:3s"><small>List</small><b>&pound;40.00</b></div>
                    <span class="op m a-pop" style="--d:6s">&minus; 25%</span>
                    <div class="v a-pop" style="--d:8s"><small>Your cost</small><b>&pound;30.00</b></div>
                    <span class="op p a-pop" style="--d:11s">+ 100%</span>
                    <div class="v out a-pop" style="--d:13s"><small>Your price</small><b>&pound;60.00</b></div>
                    <span class="chip a-pop" style="--d:16s">50% margin</span>
                  </div>
                  <div class="bigcell a-rise" style="--d:19s"><b>40.00</b><small>&pound;30.00 &rarr; &pound;60.00 &middot; 50%</small></div>
                  <p class="scs a-fade" style="--d:20s;margin-top:.6rem">That little line sits under every square. Hover it for the full sum.</p>
                </div>

                <!-- 11 — three things -->
                <div class="sc" data-scene="11" data-len="29">
                  <div class="sct a-fade" style="--d:.2s">Three things you might notice</div>
                  <div class="three">
                    <div class="note a-rise" style="--d:2s"><h4>Markup or margin</h4>
                      <span class="chip">Our markup %</span> <span class="arrow">&#8644;</span> <span class="chip">Our margin %</span>
                      <p style="margin:.4rem 0 0">Which one you see follows <b>Settings &rarr; Default margins</b>.</p></div>
                    <div class="note a-rise" style="--d:9s"><h4>A figure already there</h4>
                      <span class="box2" style="color:var(--faint)">100</span>
                      <p style="margin:.4rem 0 0">That&rsquo;s your default from Settings. Type here to override it for this system.</p></div>
                    <div class="note a-rise" style="--d:18s"><h4>Green and read-only</h4>
                      <span class="green">25%<small>from your supplier</small></span>
                      <p style="margin:.4rem 0 0">Your supplier set this for your account &mdash; it&rsquo;s theirs to change.</p></div>
                  </div>
                </div>

                <!-- 12 — price rise -->
                <div class="sc" data-scene="12" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">A price rise in one go</div>
                  <div class="upbar a-rise" style="--d:3s">Adjust all prices by
                    <span class="box2 a-ring" style="--d:6s;min-width:3rem"><span class="a-type" style="--d:7s;--ts:1;--tt:.3s">5</span></span> %
                    <span class="btns a-press" style="--d:11s">Apply</span>
                    <span style="font-weight:400">Multiplies every cell (use a negative number to reduce).</span></div>
                  ' . $g12 . '
                  <div class="bnr a-pop" style="--d:15s;margin-top:.6rem">&#10003; Adjusted 16 prices by +5%. Wrong number? Press Undo just below.</div>
                </div>

                <!-- 13 — undo -->
                <div class="sc" data-scene="13" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Made a mistake? Undo it</div>
                  <div class="undobar a-rise" style="--d:3s">Last change: <b>Adjust by +5%</b> &middot; 7 Oct 10:42
                    <span class="btns a-press a-ring" style="--d:9s">&#8630; Undo Adjust by +5%</span></div>
                  ' . $g13 . '
                  <div class="confirm a-mid" style="--d:10s;--d2:14.5s">Undo &ldquo;Adjust by +5%&rdquo;? Every price it changed goes back to exactly what it was before (7 Oct 10:42).
                    <div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:13s">OK</span></div></div>
                  <span class="chip a-pop" style="--d:19s;margin-top:.8rem">Edited again since? Then it won&rsquo;t undo &mdash; it never overwrites newer work.</span>
                </div>

                <!-- 14 — sizes -->
                <div class="sc" data-scene="14" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">Changing the sizes</div>
                  ' . $g14 . '
                  <div class="renpop a-pop" style="--d:5s">Rename width 1600mm to (new mm value): <span class="box2"><span class="a-type" style="--d:6.5s;--ts:4;--tt:.6s">1800</span></span> <span class="btnp a-press" style="--d:8.5s">OK</span></div>
                  <div class="tools">
                    <span class="chip a-pop" style="--d:13s">&times; removes a row or column</span>
                    <span class="chip a-pop" style="--d:16s">+ Width</span><span class="chip a-pop" style="--d:16.5s">+ Drop</span>
                    <span class="btns a-ring" style="--d:19s">Edit sizes</span>
                  </div>
                  <p class="scs a-fade" style="--d:23s;margin-top:.6rem">Nothing is lost until you click <b>Save grid</b>.</p>
                </div>

                <!-- 15 — other price lists -->
                <div class="sc" data-scene="15" data-len="30">
                  <div class="sct a-fade" style="--d:.2s">Not every product uses a grid</div>
                  <div class="three">
                    <div class="mini a-rise" style="--d:3s"><h4>By width only</h4>
                      <div class="row2"><span class="h">Width</span><span class="h">Price (&pound;)</span><span>600</span><span>18.40</span><span>900</span><span>22.10</span><span>1200</span><span>26.80</span></div></div>
                    <div class="mini a-rise" style="--d:8s"><h4>Per slat</h4>
                      <div class="row2"><span class="h">Drop</span><span class="h">Price per slat</span><span>1200</span><span>1.85</span><span>1800</span><span>2.40</span><span>2400</span><span>3.10</span></div></div>
                    <div class="mini a-rise" style="--d:13s"><h4>Per m&sup2;</h4>
                      <div style="font-size:.64rem;color:var(--faint);font-weight:700;margin-bottom:.2rem">RATE (&pound; PER M&sup2;)</div>
                      <span class="box2">42.00</span> <span class="btnp" style="margin-top:.4rem">Save rate</span></div>
                  </div>
                  <div class="adv a-rise" style="--d:19s">&#9656; Advanced &mdash; XLSX import / export
                    <span class="chip a-pop" style="--d:22s">&#11015; Download template (.xlsx)</span>
                    <span class="chip a-pop" style="--d:24s">&#11014; Upload &amp; replace</span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Products</b> &rarr; click the product &rarr; the <b>Price tables</b> tile (or the <b>Price tables</b>
             section further down) &rarr; that opens the system&rsquo;s list of bands &rarr; click <b>Open</b> on the band you want.
             The page title tells you exactly where you landed: <b>&ldquo;25mm Venetian / Standard &mdash; Band A&rdquo;</b>, with
             <b>&larr; All Standard price tables</b> and <b>Edit band / name / notes</b> underneath it. (If you came from the product
             setup wizard, that first link reads <b>&larr; Back to setup wizard</b> instead.)</p>

          <ul class="steps">
            <li><b>Read the strip at the top &mdash; before you type anything.</b> <b>&ldquo;Supplier price list&rdquo;</b> means
                <em>&ldquo;These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.&rdquo;</em>
                <b>&ldquo;Our price list&rdquo;</b> means <em>&ldquo;These are our selling prices. Pushed to trade accounts exactly as
                they are.&rdquo;</em> If it is the wrong one, click <b>Change</b> &mdash; it takes you to the product&rsquo;s pricing
                settings. Nothing else on this page matters until that is right.</li>
            <li><b>Empty table? Clone before you build.</b> A new table shows <b>&ldquo;This price table is empty&rdquo;</b>. If any other
                band on this product is priced, there is a <b>Clone</b> button per band, closest match first:
                <code>Copied 16 cells &mdash; tweak the prices that differ, then Save.</code> If nothing is priced yet you get
                <b>Quick start &mdash; default grid</b> and <b>Or build from scratch &mdash; start blank</b> instead.</li>
            <li><b>&ldquo;Start your grid&rdquo;.</b> <b>Widths (mm)</b> and <b>Drops (mm)</b>, each with a <b>Clear</b> link. They arrive
                pre-filled as a suggestion &mdash; <b>Clear</b>, then paste your own. A row or a column, commas, tabs or new lines all work,
                and metres are understood (<b>0.8 becomes 800&nbsp;mm</b>). Then <b>Build grid</b>. Leave a box empty and it says
                <code>Add at least one width and one drop.</code></li>
            <li><b>Paste the prices.</b> Click the top-left square, paste the block from Excel &mdash; it spreads <b>right and down</b>,
                stripping <b>&pound;</b>, <b>$</b>, <b>&euro;</b> and commas. Or click a square, type, and <b>Tab</b> on.
                <b>Anything past the edge is thrown away</b>, so build the grid the right size first; and <b>a blank square means no price
                at that size</b> &mdash; the answer to &ldquo;why won&rsquo;t my quote take this width?&rdquo;</li>
            <li><b>Save grid.</b> <em>Saving replaces every cell in this table with what&rsquo;s on screen.</em> You get
                <code>Saved 16 price cells.</code> (or <code>Saved 14 price cells. 2 had problems (see below).</code> with the offenders
                listed), and a <b>Next: Standard &mdash; Band B &rarr;</b> button to the next empty band.</li>
            <li><b>Buying discount and markup.</b> On a supplier table the strip has <b>Buying discount %</b> and <b>Our markup %</b>
                and <b>Save terms</b> &rarr; <code>Trade terms saved for this system.</code> They apply to <b>every band</b> on the system.
                Leave blank or 0 to inherit the default.</li>
            <li><b>Check the sums.</b> Under every square on a supplier table: <code>&pound;30.00 &rarr; &pound;60.00 &middot; 50%</code>
                &mdash; your cost, your price, your margin. Hover it to see the whole sum spelt out.</li>
            <li><b>A price rise is one box.</b> <b>Adjust all prices by [ ] %</b> &rarr; <b>Apply</b>. It moves <b>saved</b> prices only,
                so save first. <code>Adjusted 16 prices by +5%. Wrong number? Press Undo just below.</code></li>
            <li><b>Undo.</b> After any change to a table &mdash; Save grid, an upload or import, Adjust all prices, a clone, renaming or
                editing sizes &mdash; a strip shows <b>Last change: &hellip;</b> with an <b>&#8630; Undo</b> button. It asks first, then puts
                every price it changed back exactly as it was. If the prices have been edited again since, it says it
                <em>can&rsquo;t be undone, the prices have been edited since</em> &mdash; it never overwrites newer work.</li>
          </ul>

          <p><b>Reshaping a grid.</b> Every width and drop number in the headers <b>is a button</b>.</p>
          <ul class="steps">
            <li><b>Click a number to rename it</b> &mdash; <code>Rename width 1600mm to (new mm value):</code> &mdash; and the prices stay:
                <code>Width 1600mm renamed to 1800mm (4 cells affected).</code> It refuses a size the table already has.</li>
            <li><b>The &times; removes that column or row</b> (<code>Remove the 1600mm width column? Any prices in it will be lost on next
                save.</code>). Nothing is lost until you <b>Save grid</b>.</li>
            <li><b>+ Width / + Drop</b> add new ones; <b>Edit sizes</b> changes them all at once (changed = renamed, deleted = removed with
                prices, added = new empty row or column).</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin, and figures already filled in.</b> The second box
             reads <b>&ldquo;Our markup %&rdquo;</b> or <b>&ldquo;Our margin %&rdquo;</b> depending on <b>Settings &rarr; Default margins</b>.
             The customer price is the same either way. The markup box often shows your default from Settings; the buying discount has no
             default, so blank really is 0%. Where your <b>supplier</b> has set a trade discount on your account it shows as green read-only
             text, <b>25% from your supplier</b> &mdash; theirs to change, not yours.</div></div>

          <p><b>&ldquo;Our price list&rdquo; tables</b> have no terms boxes &mdash; the grid <em>is</em> the selling price.</p>

          <p><b>Other kinds of price list.</b> <b>By width only</b>: a <b>Width</b> / <b>Price (&pound;)</b> list. <b>Per slat</b>: <b>Drop</b> /
             <b>Price per slat (&pound;)</b> (the line price is that rate &times; the number of slats). <b>Per m&sup2;</b>: one box,
             <b>Rate (&pound; per m&sup2;)</b> &rarr; <b>Save rate</b>, with the product&rsquo;s minimum area applied.</p>

          <p><b>Advanced &mdash; XLSX import / export</b>, at the bottom: <b>Download template (.xlsx)</b> gives you this table to edit in
             Excel; <b>Upload &amp; replace</b> puts it back (widths across row 1, drops down column A); <b>Import &amp; replace</b> reads a
             raw supplier sheet and finds this band in it (<code>Picked Band A out of 4 bands in the file.</code>). Both replace every cell
             &mdash; and both can be undone.</p>

          <div class="oops"><b>When an upload won&rsquo;t go in:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>Could not detect any band data in the file.</code> &rarr; put a <code>Band A</code> marker in <b>column A</b> above the block.</li>
               <li><code>No width values detected in row 1 (B onwards).</code> &rarr; the template wants widths across the very first row, from column B.</li>
               <li><code>No valid price cells found in the uploaded file.</code> &rarr; the shape was read but every cell was blank or unreadable.</li>
             </ul></div>

          <p><b>When you&rsquo;re finished,</b> take <b>Next: &hellip; &rarr;</b> to the next empty band, and follow the floating
             <b>Fix next &rarr;</b> pill until it stops appearing &mdash; then every product is ready to quote.</p>',
        'script'  => [
            ['1', 'What a price table is',             'A price table is simply a grid. The widths your supplier sells run across the top, and the drops run down the side. To price a blind, you find its width along the top, and its drop down the side. The price is in the square where the two meet. So a blind twelve hundred wide and sixteen hundred deep is thirty eight pounds forty. Every band of fabric has a table of its own.', 1],
            ['2', 'Getting there',                     'To find a table, go to Products, and click the product. Open its Price tables, and you will see one row for each band. Click Open on the band you want. The title at the top always tells you exactly where you are: which product, which system, and which band.', 2],
            ['3', 'Whose prices are these?',           'Before you type a single number, read the strip across the top. It tells you what kind of prices belong in this table. Supplier price list means these are your supplier\'s own list prices. The system takes your buying discount off, and adds your markup on. Our price list means these are your own selling prices, used exactly as you type them. If it shows the wrong one, click Change before you do anything else.', 3],
            ['4', 'Clone a band that is done',         'A new table starts empty. If another band on this product already has prices, there is no need to build anything. Just clone it. Click Clone, and you get the same widths, the same drops and the same prices, all in one go. Then you only change the squares that are different.', 4],
            ['5', 'Nothing to clone? Start your grid', 'If nothing is priced yet, choose to build from scratch, and the Start your grid box opens. The widths and drops arrive filled in, as a suggestion. Click Clear, and paste in your own sizes from the supplier\'s sheet. Across or down, with commas or on new lines, it does not mind. It even understands metres, so nought point eight becomes eight hundred millimetres. Then click Build grid.', 5],
            ['6', 'Paste the prices in',               'Now an empty grid appears, in the shape you asked for. Open your supplier\'s price sheet, select the block of prices, and copy it. Come back here, click the top left square, and paste. The prices pour in, to the right and downwards, from the square you clicked. Pound signs and commas are cleaned off for you.', 6],
            ['7', 'Two things that catch people out',  'Two things catch people out. First, anything that falls past the edge of the grid is thrown away, without a warning. So if the supplier\'s sheet is wider than your grid, add the extra widths first, and then paste. Second, a square left empty means there is no price at that size. If a quote will not accept a size, look for an empty square.', 7],
            ['8', 'Save the grid',                     'When you are happy, click Save grid. Saving replaces every square in this table with what is on the screen. You will see how many prices were saved, and a button that takes you straight on to the next empty band.', 8],
            ['9', 'Buying discount and markup',        'Now the money. On a supplier price list, the strip at the top has two boxes. Buying discount is what your supplier takes off their list for you. Say, twenty five percent. Our markup is what you add on top. Say, a hundred percent. Click Save terms. These two figures cover every band on this system, so you only set them once.', 9],
            ['10', 'Check the sums',                   'You can check the sums without leaving the page. Take a square with a list price of forty pounds. Take off your twenty five percent, and it costs you thirty pounds. Add your hundred percent markup, and you sell it for sixty pounds. That is a fifty percent margin. A small line under every square shows exactly this. Hover over it to see the whole sum.', 10],
            ['11', 'Three things you might notice',    'Three things you might notice. If your settings work in margin rather than markup, the second box says Our margin instead. The markup box may already show a figure. That is your default from Settings, and typing here overrides it for this system. And if the discount shows as green text, saying from your supplier, your supplier set it on your account, so it is theirs to change.', 11],
            ['12', 'A price rise in one go',           'When your supplier puts their prices up, there is no need to retype the grid. In the Adjust all prices box, type the rise. Say, five percent. Then click Apply, and every saved price goes up by five percent. A minus number brings them down. It only changes saved prices, so save first.', 12],
            ['13', 'Made a mistake? Undo it',          'Made a mistake? Every change to a price table can be undone. A strip shows you the last change, with an Undo button beside it. Click Undo, say OK, and every price it touched goes back exactly as it was. If the prices have been changed again since, it will not undo, so it can never overwrite newer work.', 13],
            ['14', 'Changing the sizes',               'Sizes can change too. Every width and drop along the edge of the grid is a button. Click one to rename it, and its prices stay where they are. The little cross removes that row or column. Plus width and plus drop add new ones. Or use Edit sizes to change them all at once. Nothing is lost until you click Save grid.', 14],
            ['15', 'Not every product uses a grid',    'Finally, not every product uses a grid. A product priced by width only has a simple list of widths and prices. One priced per slat has a list of drops, with a price per slat. One priced per square metre has just one box: the rate. And if you would rather work in Excel, the Advanced section at the bottom lets you download the table, change it, and upload it again.', 15],
        ],
];
