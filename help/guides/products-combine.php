<?php
declare(strict_types=1);

/**
 * Guide: products-combine — "Combining products into one" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors admin/products/combine.php (the confirm form, its blue panel, the
 * Master pill, "Becomes system" boxes, the checks and the flash message) and
 * the Products list bulk bar in admin/products/index.php ("Combine into
 * product…", selection order = page order). The master's Systems section is
 * from admin/products/edit.php. Every label and message is copied from those.
 *
 * v2: one SCENE per script line. Beat times are worked out from where the
 * matching words fall in the voice-over ($at); data-len = line length ÷ 13.6.
 */

$S = [
    1  => ['1',  'Why combine',
        'Suppliers often give you a family of blinds as separate products. Fifteen, twenty five and thirty five millimetre venetians, say. That means three lots of fabrics, three lots of price tables, and three look-alikes to keep up to date. Whoever is quoting has to pick the right one, every time. Combining folds them into one product, where each size becomes a system.'],
    2  => ['2',  'What your salesperson sees',
        'Here is the payoff. Instead of three similar products in the quote builder, there is just one. The salesperson picks Metal Venetian, and then the size, from its System list. When the supplier puts prices up, you deal with one product, not three. One product to look after, and nothing to mix up.'],
    3  => ['3',  'Tick the products',
        'Go to the Products list, and tick the box beside each product you want to combine. Only tick blinds from the same family. The bar above the list counts how many you have ticked. Combine into product stays greyed out until at least two are ticked. Then it lights up, ready to press.'],
    4  => ['4',  'The master is the top one',
        'Now the rule that catches people out. The master is not the one you ticked first. It is the ticked product nearest the top of the list. So before you tick anything, drag the one you want as master up above the others, by its handle. If they sit in different groups, put them in one group first. The master keeps its own settings and its group.'],
    5  => ['5',  'The Combine page',
        'Press Combine into product, and the Combine page opens. Nothing has changed yet. Read the blue panel once. The first product is used as the master. The others fold in as systems, and are then switched off. Their fabrics and prices are not lost. They move onto the master. Cancel takes you back, with nothing touched.'],
    6  => ['6',  'Name the master',
        'Type the master product name at the top. Metal Venetian, for example. This renames the first product, so give the whole family its proper name. It is the name your salesperson will pick from the list, so use the words they would say. It can be up to a hundred and fifty characters, and it cannot be left empty.'],
    7  => ['7',  'Check the system names',
        'Each row has a Becomes system box, filled in for you. It takes the size from the product\'s name, so fifteen millimetre Venetian becomes fifteen millimetre. With no size in the name, it uses the whole name, so shorten it. The Fabrics column shows how many will move, so you can check you ticked the right products.'],
    8  => ['8',  'Combine them',
        'Press Combine into one product. It all happens in one go, or not at all. The price tables move to their new systems. Each size brings its fabrics, kept to that size, so twenty five millimetre colours only show under twenty five millimetre. Markups and discounts move with them too. You land on the master\'s edit page, with a green message.'],
    9  => ['9',  'The products left behind',
        'Back on the Products list, the master now shows three systems. The products you folded in are still there, but empty, and marked Inactive. Their fabric and price table counts drop to nought, because those have moved. Inactive products are not offered on quotes, so there is no rush to delete them. Leave them until you are happy, because there is no undo for a combine.'],
    10 => ['10', 'When it says no',
        'Two messages you may see. These products are priced differently. Every product must be set up the same way, for example all width and drop, or all per slat. Untick the odd one out, or fix it on its edit page. And, already has more than one system. That means an existing master was not at the top. Drag it to the top, and try again.'],
    11 => ['11', 'Options, and adding sizes later',
        'Check the options afterwards. They are matched by name, and if the master already has one with the same name, the incoming one is dropped, choices and all. So give the master\'s options a look. To add a new size later, tick the master and the new product, with the master on top, and combine again. It is added as one more system.'],
];

/** "Ns" — when the words $p are spoken in line $n (13.6 characters a second). */
$at = static function (int $n, string $p, float $plus = 0.0) use ($S): string {
    $i = mb_strpos($S[$n][2], $p);
    if ($i === false) { $GLOBALS['gd_at_miss'][] = "$n: $p"; $i = 0; }
    return round($i / 13.6 + $plus, 1) . 's';
};
$len = static fn (int $n): string => (string) max(8, (int) round(mb_strlen($S[$n][2]) / 13.6));

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$tk  = static fn (bool $on, string $cls = '', string $st = ''): string => '<span class="tick ' . ($on ? 'on ' : '') . $cls . '" style="' . $st . '">&#10003;</span>';

/**
 * A Products-list table. $rows: [name, status html, systems, fabrics, tables, extra cell-1 html (tick), row class].
 */
$plist = static function (array $rows): string {
    $h = '<div class="pl"><div class="hd"><span></span><span></span><span>Name</span><span>Status</span><span>Systems</span><span>Fabrics</span><span>Price tables</span></div>';
    foreach ($rows as $r) {
        $h .= '<div class="' . ($r[6] ?? '') . '"><span>' . ($r[5] ?? '<span class="tick"></span>') . '</span><span class="dg">&#8942;&#8942;</span><span class="nm">' . $r[0] . '</span>'
            . '<span>' . $r[1] . '</span><span class="n">' . $r[2] . '</span><span class="n">' . $r[3] . '</span><span class="n">' . $r[4] . '</span></div>';
    }
    return $h . '</div>';
};
$ready = '<span class="st ok">&#10003; Ready</span>';
$inact = '<span class="st off">Inactive</span>';

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Combining products into one',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'Fold 15/25/35mm into one product, each size a system — why, how to pick the master, what moves across, and what to check afterwards.',
        'lede'    => 'A separate product for every size means three lots of fabrics, three lots of price tables and three look-alikes in the quote
                      builder. <b>Combine</b> folds them into <b>one</b> product where each size is a <b>system</b>, carrying the fabrics, price
                      tables and markups across. The ones folded in are switched <b>off</b>, not deleted &mdash; and there is <b>no un-combine</b>,
                      so watch this through first. To start: <b>Products</b> &rarr; tick the products &rarr; <b>Combine into product&hellip;</b>',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .app{ grid-template-columns:132px minmax(0,1fr); }
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .ttl{ font-size:.95rem; font-weight:800; color:var(--ink); margin:0 0 .3rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.22rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.35rem; margin:.55rem 0; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.68rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                     border-radius:7px; padding:.24rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .dis{ opacity:.45; }
          .gd .sw{ display:inline-grid; } .gd .sw > *{ grid-area:1/1; }
          .gd .swb{ display:grid; align-items:start; } .gd .swb > *{ grid-area:1/1; }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .alr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:6px; padding:.32rem .55rem; font-size:.66rem; font-weight:600;
                    color:var(--ink); margin:0 0 .45rem; max-width:32rem; box-sizing:border-box; line-height:1.4; }
          .gd .alr.err{ background:var(--err-wash); border-left-color:var(--err); }
          .gd .blue{ background:color-mix(in srgb,#3b82f6 9%,var(--surface)); border:1px solid color-mix(in srgb,#3b82f6 32%,transparent); border-radius:10px;
                     padding:.5rem .7rem; font-size:.68rem; color:var(--ink); line-height:1.5; max-width:32rem; margin:.4rem 0 .6rem; }
          .gd .fl{ display:block; font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.45rem 0 .2rem; }
          .gd .inp{ display:grid; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:0 .5rem;
                    font-size:.76rem; background:var(--surface); color:var(--ink); max-width:24rem; box-sizing:border-box; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp.sm{ min-height:22px; font-size:.68rem; max-width:7.5rem; }
          .gd .ph{ color:var(--faint); }
          .gd .tick{ width:15px; height:15px; font-size:.55rem; }
          .gd .mpill{ display:inline-block; padding:.04rem .45rem; font-size:.52rem; font-weight:700; color:#fff; background:#0a58ca; border-radius:999px;
                      margin-left:.35rem; text-transform:uppercase; letter-spacing:.05em; vertical-align:middle; }

          /* products list */
          .gd .pl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:32rem; font-size:.68rem; background:var(--surface); }
          .gd .pl > div{ display:grid; grid-template-columns:1.3rem .9rem minmax(6rem,1fr) 4.4rem 3rem 3rem 3.6rem; gap:.25rem; align-items:center;
                         padding:.32rem .5rem; border-top:1px solid var(--line-2); color:var(--ink); }
          .gd .pl > div.hd{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; }
          .gd .pl .dg{ color:var(--faint); letter-spacing:-.1em; }
          .gd .pl .nm{ font-weight:700; color:var(--accent); }
          .gd .pl .n{ text-align:right; color:var(--accent); font-variant-numeric:tabular-nums; }
          .gd .pl .off{ color:var(--faint); }
          .gd .pl > div.gone .nm, .gd .pl > div.gone .n{ color:var(--faint); }
          .gd .st{ display:inline-block; padding:.06rem .45rem; border-radius:999px; font-size:.56rem; font-weight:700; white-space:nowrap; }
          .gd .st.ok{ background:#d1fae5; color:#065f46; } .gd .st.off{ background:var(--line); color:var(--soft); }
          .gd .bulk{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin:0 0 .5rem; font-size:.66rem; color:var(--faint); }

          /* 1 — three products → one */
          .gd .fam{ display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .pc{ border:1px solid var(--line); border-radius:10px; padding:.4rem .55rem; background:var(--surface); font-size:.7rem; color:var(--ink); min-width:7.5rem; }
          .gd .pc b{ display:block; margin-bottom:.2rem; }
          .gd .pc small{ display:block; color:var(--faint); font-size:.58rem; }
          .gd .master{ border:2px solid var(--accent); border-radius:12px; padding:.5rem .7rem; background:var(--surface); max-width:24rem; margin-top:.7rem; }
          .gd .master b{ font-size:.86rem; color:var(--ink); }
          .gd .sysl{ display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.35rem; }

          /* 2 — quote builder */
          .gd .qb{ border:1px solid var(--line); border-radius:12px; padding:.5rem .7rem; background:var(--panel); max-width:16rem; }
          .gd .qb .qr{ display:grid; grid-template-columns:4.6rem 1fr; gap:.4rem; align-items:start; margin:.3rem 0; font-size:.66rem; color:var(--soft); }
          .gd .qb .qr b{ color:var(--ink); padding-top:.15rem; }
          .gd .dd{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); font-size:.66rem; color:var(--ink); overflow:hidden; }
          .gd .dd div{ padding:.14rem .4rem; } .gd .dd div.hi{ background:var(--accent-wash); font-weight:700; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:1rem; max-width:34rem; }

          /* combine page */
          .gd .ct{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:32rem; font-size:.68rem; background:var(--surface); }
          .gd .ct > div{ display:grid; grid-template-columns:minmax(7rem,1fr) 8rem 3.4rem; gap:.4rem; align-items:center; padding:.32rem .55rem; border-top:1px solid var(--line-2); color:var(--ink); }
          .gd .ct > div.hd{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; }
          .gd .ct .n{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .sysbox{ border:1px solid var(--line); border-radius:10px; max-width:24rem; background:var(--surface); }
          .gd .sysbox > div{ display:flex; align-items:center; gap:.45rem; padding:.3rem .6rem; border-top:1px solid var(--line-2); font-size:.7rem; color:var(--ink); }
          .gd .sysbox > div:first-child{ border-top:0; font-weight:800; background:var(--panel); }
          .gd .pill2{ display:inline-block; border-radius:999px; padding:.02rem .45rem; font-size:.56rem; font-weight:700; background:var(--accent-wash); color:var(--accent-ink); }
          .gd .pill2.def{ background:#dbeafe; color:#1e40af; }
          .gd .x{ color:var(--err); font-weight:800; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; }
            .gd .side{ display:none; }
            .gd .sc{ min-height:450px; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .pl > div{ grid-template-columns:1.2rem .8rem minmax(5rem,1fr) 3.9rem 2.2rem 2.4rem 2.8rem; font-size:.6rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / combine</span></div>
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
                  <div class="sct">Combine into one product</div>
                  <p class="scs">Each product below becomes a <b>system</b> of a single master product. Their fabrics, price tables and settings move across.</p>
                  ' . $plist([
                        ['15mm Venetian', $ready, 1, 42, 1],
                        ['25mm Venetian', $ready, 1, 38, 1],
                        ['35mm Venetian', $ready, 1, 40, 1],
                    ]) . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; eleven short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — why combine -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">Three products for one family</div>
                  <div class="fam">
                    <div class="pc a-drop" style="--d:' . $at(1, 'Fifteen') . '"><b>15mm Venetian</b><small class="a-fade" style="--d:' . $at(1, 'three lots of fabrics') . '">42 fabrics</small><small class="a-fade" style="--d:' . $at(1, 'three lots of price') . '">1 price table</small></div>
                    <div class="pc a-drop" style="--d:' . $at(1, 'twenty five') . '"><b>25mm Venetian</b><small class="a-fade" style="--d:' . $at(1, 'three lots of fabrics', .3) . '">38 fabrics</small><small class="a-fade" style="--d:' . $at(1, 'three lots of price', .3) . '">1 price table</small></div>
                    <div class="pc a-drop" style="--d:' . $at(1, 'thirty five') . '"><b>35mm Venetian</b><small class="a-fade" style="--d:' . $at(1, 'three lots of fabrics', .6) . '">40 fabrics</small><small class="a-fade" style="--d:' . $at(1, 'three lots of price', .6) . '">1 price table</small></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(1, 'three look-alikes') . ';border-color:var(--err);color:var(--err)">3&times; the upkeep</span></div>
                  <div class="a-fade" style="font-size:1.1rem;color:var(--faint);margin:.1rem 0 0 3rem;--d:' . $at(1, 'Combining folds') . '">&darr;</div>
                  <div class="master a-rise" style="--d:' . $at(1, 'Combining folds') . '"><b>Metal Venetian</b> <span style="font-size:.6rem;color:var(--faint)">&middot; one product &middot; 120 fabrics &middot; 3 price tables</span>
                    <div class="sysl"><span class="pill2 a-pop" style="--d:' . $at(1, 'each size becomes') . '">System: 15mm</span><span class="pill2 a-pop" style="--d:' . $at(1, 'each size becomes', .4) . '">System: 25mm</span><span class="pill2 a-pop" style="--d:' . $at(1, 'each size becomes', .8) . '">System: 35mm</span></div></div>
                </div>

                <!-- 2 — what the salesperson sees -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">In the quote builder</div>
                  <div class="two">
                    <div class="a-rise" style="--d:' . $at(2, 'Instead of three') . '"><p class="scs" style="margin:0 0 .3rem">Before</p>
                      <div class="qb"><div class="qr"><b>Product</b><div class="dd"><div>15mm Venetian</div><div>25mm Venetian</div><div>35mm Venetian</div></div></div></div></div>
                    <div class="a-rise" style="--d:' . $at(2, 'there is just one') . '"><p class="scs" style="margin:0 0 .3rem">After</p>
                      <div class="qb"><div class="qr"><b>Product</b><div class="dd"><div class="hi a-sel" style="--d:' . $at(2, 'picks Metal') . '">Metal Venetian</div></div></div>
                        <div class="qr a-fade" style="--d:' . $at(2, 'and then the size') . '"><b>System</b><div class="dd"><div>15mm</div><div class="hi a-sel" style="--d:' . $at(2, 'from its System list') . '">25mm</div><div>35mm</div></div></div></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(2, 'When the supplier') . '">Price rise &rarr; one product, not three</span>
                    <span class="chip a-pop" style="--d:' . $at(2, 'One product to look') . ';border-color:var(--good);color:var(--good)">One product to look after</span></div>
                </div>

                <!-- 3 — tick the products -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="ttl">Products</div>
                  <div class="bulk">
                    <span class="sw"><span class="a-out" style="--d:' . $at(3, 'tick the box', 1) . '">(none selected)</span><span class="a-mid" style="--d:' . $at(3, 'tick the box', 1) . ';--d2:' . $at(3, 'you want to combine') . '">1 selected</span>
                      <span class="a-mid" style="--d:' . $at(3, 'you want to combine') . ';--d2:' . $at(3, 'The bar above') . '">2 selected</span><span class="a-fade a-ring" style="--d:' . $at(3, 'The bar above') . ';border-radius:4px">3 selected</span></span>
                    <span class="btns dis">Move selected to&hellip;</span>
                    <span class="sw"><span class="btns dis a-out" style="--d:' . $at(3, 'Then it lights')  . '">Combine into product&hellip;</span><span class="a-fade" style="--d:' . $at(3, 'Then it lights') . '"><span class="btns a-ring" style="--d:' . $at(3, 'ready to press') . '">Combine into product&hellip;</span></span></span>
                    <span class="btns dis" style="color:var(--err)">Delete selected</span>
                    <span class="a-fade" style="--d:' . $at(3, 'tick the box', 1) . ';color:var(--accent);text-decoration:underline">Clear</span>
                  </div>
                  ' . $plist([
                        ['15mm Venetian', $ready, 1, 42, 1, '<span class="tick a-sel" style="--d:' . $at(3, 'tick the box', 1) . '">&#10003;</span>'],
                        ['25mm Venetian', $ready, 1, 38, 1, '<span class="tick a-sel" style="--d:' . $at(3, 'you want to combine') . '">&#10003;</span>'],
                        ['35mm Venetian', $ready, 1, 40, 1, '<span class="tick a-sel" style="--d:' . $at(3, 'The bar above') . '">&#10003;</span>'],
                        ['Roller Blind', $ready, 2, 64, 4],
                    ]) . '
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(3, 'same family') . '">Same family only</span>
                    <span class="chip a-pop" style="--d:' . $at(3, 'stays greyed out') . '">Greyed out until two or more are ticked</span></div>
                </div>

                <!-- 4 — the master is the top one -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">The master = the ticked product nearest the top</div>
                  <div class="swb">
                    <div class="a-out" style="--d:' . $at(4, 'by its handle', 1) . '">' . $plist([
                        ['25mm Venetian', $ready, 1, 38, 1, $tk(true)],
                        ['<span class="a-ring" style="--d:' . $at(4, 'drag the one') . ';border-radius:4px">15mm Venetian</span>', $ready, 1, 42, 1, $tk(true)],
                        ['35mm Venetian', $ready, 1, 40, 1, $tk(true)],
                    ]) . '</div>
                    <div class="a-fade" style="--d:' . $at(4, 'by its handle', 1) . '">' . $plist([
                        ['15mm Venetian <span class="mpill a-pop" style="--d:' . $at(4, 'The master keeps') . '">Master</span>', $ready, 1, 42, 1, $tk(true)],
                        ['25mm Venetian', $ready, 1, 38, 1, $tk(true)],
                        ['35mm Venetian', $ready, 1, 40, 1, $tk(true)],
                    ]) . '</div>
                  </div>
                  <div class="a-move" style="--fx:6%;--fy:5.1rem;--tx:6%;--ty:3.1rem;--d:' . $at(4, 'drag the one', .6) . ';--md:2s">' . $ptr . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(4, 'not the one you ticked') . ';border-color:var(--err);color:var(--err)">&#10007; not the one you ticked first</span>
                    <span class="chip a-pop" style="--d:' . $at(4, 'nearest the top') . ';border-color:var(--good);color:var(--good)">&#10003; the one nearest the top</span>
                    <span class="chip a-pop" style="--d:' . $at(4, 'one group first') . '">Different groups? Move them into one first</span>
                    <span class="chip a-pop" style="--d:' . $at(4, 'The master keeps') . '">Keeps its settings and its group</span>
                  </div>
                </div>

                <!-- 5 — the combine page -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <span class="btns a-press a-ring" style="--d:.3s">Combine into product&hellip;</span>
                  <div class="a-rise" style="--d:' . $at(5, 'the Combine page opens') . ';margin-top:.6rem">
                    <div class="ttl">Combine into one product</div>
                    <p class="scs" style="margin:0">Each product below becomes a <b>system</b> of a single master product. Their fabrics, price tables and settings move across.</p>
                  </div>
                  <div class="blue a-rise a-ring" style="--d:' . $at(5, 'Read the blue') . '">The <b>first</b> product is reused as the master (it keeps its group and settings). The others fold in as systems and are then
                    deactivated &mdash; their data isn&rsquo;t lost, it moves onto the master. You can delete the empty husks afterwards.</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(5, 'The first product') . '">1st = master</span><span class="arrow a-fade" style="--d:' . $at(5, 'The others') . '">+</span>
                    <span class="chip a-pop" style="--d:' . $at(5, 'The others') . '">others &rarr; systems</span><span class="arrow a-fade" style="--d:' . $at(5, 'switched off') . '">&rarr;</span>
                    <span class="chip a-pop" style="--d:' . $at(5, 'switched off') . '">then switched off</span>
                    <span class="chip a-pop" style="--d:' . $at(5, 'They move onto') . ';border-color:var(--good);color:var(--good)">fabrics + prices move to the master</span>
                  </div>
                  <div style="display:flex;gap:.4rem;align-items:center"><span class="btnp">Combine into one product</span><span class="btns a-ring" style="--d:' . $at(5, 'Cancel takes') . '">Cancel</span>
                    <span class="scs a-fade" style="--d:' . $at(5, 'Cancel takes') . ';margin:0">&larr; nothing touched</span></div>
                </div>

                <!-- 6 — name the master -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="ttl">Combine into one product</div>
                  <span class="fl">Master product name <span class="req">*</span></span>
                  <span class="inp a-ring" style="--d:.5s"><span class="ph a-out" style="--d:' . $at(6, 'Metal Venetian,') . '">e.g. Metal Venetian</span><span><span class="a-type" style="--d:' . $at(6, 'Metal Venetian,') . ';--ts:14;--tt:1s">Metal Venetian</span></span></span>
                  <div class="chips" style="margin-top:.8rem">
                    <span class="chip a-pop" style="--d:' . $at(6, 'This renames') . '">15mm Venetian <span class="arrow">&rarr;</span> Metal Venetian</span>
                  </div>
                  <div class="qb a-rise" style="--d:' . $at(6, 'It is the name') . ';margin-top:.4rem"><div class="qr"><b>Product</b><div class="dd"><div class="hi">Metal Venetian</div></div></div></div>
                </div>

                <!-- 7 — check the system names -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <span class="fl" style="margin-top:0">Master product name <span class="req">*</span></span><span class="inp">Metal Venetian</span>
                  <div class="ct" style="margin-top:.6rem">
                    <div class="hd"><span>Product</span><span>Becomes system</span><span class="n">Fabrics</span></div>
                    <div><span>15mm Venetian <span class="mpill">Master</span></span><span class="inp sm a-ring" style="--d:' . $at(7, 'so fifteen') . '">15mm</span><span class="n a-ring" style="--d:' . $at(7, 'The Fabrics column') . ';border-radius:4px">42</span></div>
                    <div><span>25mm Venetian</span><span class="inp sm a-ring" style="--d:' . $at(7, 'Each row has') . '">25mm</span><span class="n">38</span></div>
                    <div><span>35mm Venetian</span><span class="inp sm">35mm</span><span class="n">40</span></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(7, 'so fifteen') . '">&ldquo;15mm Venetian&rdquo; <span class="arrow">&rarr;</span> 15mm</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'With no size') . ';border-color:#f59e0b">&ldquo;Venetian Blind&rdquo; <span class="arrow">&rarr;</span> Venetian Blind &mdash; shorten it</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'The Fabrics column') . '">42 + 38 + 40 fabrics will move</span>
                  </div>
                  <div style="display:flex;gap:.4rem"><span class="btnp">Combine into one product</span><span class="btns">Cancel</span></div>
                </div>

                <!-- 8 — combine them -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="swb">
                    <div class="a-out" style="--d:' . $at(8, 'You land on', -.2) . '">
                      <div style="display:flex;gap:.4rem"><span class="btnp a-press a-ring" style="--d:.4s">Combine into one product</span><span class="btns">Cancel</span></div>
                      <div class="chips" style="flex-direction:column;align-items:flex-start">
                        <span class="chip a-pop" style="--d:' . $at(8, 'in one go') . '">All in one go &mdash; or not at all</span>
                        <span class="chip a-pop" style="--d:' . $at(8, 'The price tables move') . '">Price tables &rarr; their new systems</span>
                        <span class="chip a-pop" style="--d:' . $at(8, 'Each size brings') . '">Fabrics &rarr; kept to their own size</span>
                        <span class="chip a-pop" style="--d:' . $at(8, 'Markups and discounts') . '">Markups and discounts &rarr; their system</span>
                      </div>
                    </div>
                    <div class="a-fade" style="--d:' . $at(8, 'You land on') . '">
                      <div class="ttl">Edit Metal Venetian</div>
                      <div class="alr">Combined into &ldquo;Metal Venetian&rdquo; with 3 systems. 25mm Venetian, 35mm Venetian are now empty and deactivated &mdash; delete once you&rsquo;ve checked the result.</div>
                      <div class="sysbox"><div>Systems (3)</div>
                        <div><span class="dg" style="color:var(--faint)">&#8942;&#8942;</span><b style="color:var(--accent)">15mm</b><span class="pill2 def">Default</span><span class="pill2" style="margin-left:auto">1 price table</span></div>
                        <div><span class="dg" style="color:var(--faint)">&#8942;&#8942;</span><b style="color:var(--accent)">25mm</b><span class="pill2" style="margin-left:auto">1 price table</span></div>
                        <div><span class="dg" style="color:var(--faint)">&#8942;&#8942;</span><b style="color:var(--accent)">35mm</b><span class="pill2" style="margin-left:auto">1 price table</span></div></div>
                    </div>
                  </div>
                </div>

                <!-- 9 — the products left behind -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="ttl">Products</div>
                  ' . $plist([
                        ['Metal Venetian', $ready, '<span class="a-ring" style="--d:' . $at(9, 'shows three') . ';border-radius:4px;padding:0 .2rem">3</span>', 120, 3],
                        ['25mm Venetian', '<span class="a-ring" style="--d:' . $at(9, 'marked Inactive') . ';border-radius:999px;display:inline-block">' . $inact . '</span>', 1, 0, 0, null, 'gone'],
                        ['35mm Venetian', $inact, 1, 0, 0, null, 'gone'],
                        ['Roller Blind', $ready, 2, 64, 4],
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(9, 'not offered on quotes') . '">Inactive = not offered on quotes</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'no rush') . '">No rush to delete them</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'no undo') . ';border-color:var(--err);color:var(--err)">&#9888; There is no un-combine</span>
                  </div>
                </div>

                <!-- 10 — when it says no -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="ttl">Combine into one product</div>
                  <div class="alr err a-pop" style="--d:' . $at(10, 'These products') . '">These products are priced differently (e.g. per-slat vs width&times;drop), so they can&rsquo;t be systems of one product.</div>
                  <div class="chips" style="margin-top:0"><span class="chip a-pop" style="--d:' . $at(10, 'Every product must') . '">All width &times; drop &middot; or all per slat &middot; or all per m&sup2;</span>
                    <span class="chip a-pop" style="--d:' . $at(10, 'Untick the odd') . '">Untick the odd one out &mdash; or fix it on its Edit page</span></div>
                  <div class="alr err a-pop" style="--d:' . $at(10, 'And, already') . ';margin-top:.6rem">&ldquo;Metal Venetian&rdquo; already has more than one system &mdash; only the first (the master) may. Add single-size products to it.</div>
                  <div class="swb" style="margin-top:.3rem">
                    <div class="a-out" style="--d:' . $at(10, 'Drag it to the top', 1) . '">' . $plist([
                        ['50mm Venetian', $ready, 1, 30, 1, $tk(true)],
                        ['<span class="a-ring" style="--d:' . $at(10, 'was not at the top') . ';border-radius:4px">Metal Venetian</span>', $ready, 3, 120, 3, $tk(true)],
                    ]) . '</div>
                    <div class="a-fade" style="--d:' . $at(10, 'Drag it to the top', 1) . '">' . $plist([
                        ['Metal Venetian <span class="mpill">Master</span>', $ready, 3, 120, 3, $tk(true)],
                        ['50mm Venetian', $ready, 1, 30, 1, $tk(true)],
                    ]) . '</div>
                  </div>
                </div>

                <!-- 11 — options, and adding sizes later -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="sct a-fade" style="--d:.2s">Options are matched by name</div>
                  <div class="two" style="grid-template-columns:auto auto 1fr;align-items:center;gap:.5rem;max-width:30rem">
                    <div class="pc a-rise" style="--d:' . $at(11, 'They are matched') . '"><b>25mm Venetian</b><small>Control type &middot; 3 choices</small></div>
                    <span class="arrow a-fade" style="--d:' . $at(11, 'if the master') . '">&rarr;</span>
                    <div class="pc a-rise" style="--d:' . $at(11, 'if the master') . '"><b>Metal Venetian</b><small>Control type &middot; 2 choices</small>
                      <small class="x a-pop" style="--d:' . $at(11, 'is dropped') . '">incoming &ldquo;Control type&rdquo; dropped, choices and all</small></div>
                  </div>
                  <div class="a-rise" style="--d:' . $at(11, 'To add a new size') . ';margin-top:.8rem">
                    <p class="scs" style="margin:0 0 .3rem">Adding a size later &mdash; master on top:</p>
                    <div class="blue" style="margin-top:0"><b>Metal Venetian</b> is already a master, so the others are <b>added to it</b> as extra systems. Their data moves across and
                      they&rsquo;re then deactivated &mdash; nothing is lost.</div>
                    <div class="ct">
                      <div class="hd"><span>Product</span><span>Becomes system</span><span class="n">Fabrics</span></div>
                      <div><span>Metal Venetian <span class="mpill">Master</span></span><span style="color:var(--faint)">keeps its 3 existing systems</span><span class="n">120</span></div>
                      <div><span>50mm Venetian</span><span class="inp sm a-ring" style="--d:' . $at(11, 'one more system') . '">50mm</span><span class="n">30</span></div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>Suppliers often hand you a family of blinds as <b>separate products</b> &mdash; <em>15mm Venetian</em>, <em>25mm Venetian</em>,
             <em>35mm Venetian</em>. Each has its own fabrics, price tables and settings, so every price rise is done three times, and whoever
             quotes has to pick between look-alikes. <b>Combine</b> folds them into <b>one</b> product in which each size is a <b>system</b>:
             the quote builder then shows one product with a <b>System</b> list.</p>
          <ul class="steps">
            <li><b>Tick the products</b> on the Products list (the box at the left of each row). The bar above reads <em>(none selected)</em>,
                then <em>3 selected</em>, with a <b>Clear</b> link. <b>Combine into product&hellip;</b> stays greyed out until at least two are
                ticked.</li>
            <li><b>Put the master at the top first.</b> The master is <b>not</b> the one you clicked first &mdash; it is the ticked row that sits
                <b>highest on the page</b>. Drag it up by its <b>&#8942;&#8942;</b> handle before you tick (the order saves as you drop), or file
                them into the same group with <b>Move selected to&hellip;</b>; with groups, each group&rsquo;s table comes in group order.</li>
            <li><b>Press &ldquo;Combine into product&hellip;&rdquo;.</b> That button is the only way in &mdash; opening the page directly sends you
                back with <code>Pick at least two products to combine.</code></li>
            <li><b>Read the blue panel.</b> <em>The first product is reused as the master (it keeps its group and settings). The others fold in as
                systems and are then deactivated &mdash; their data isn&rsquo;t lost, it moves onto the master.</em> The master&rsquo;s row wears a
                blue <b>MASTER</b> pill.</li>
            <li><b>Master product name *</b> (placeholder <em>e.g. Metal Venetian</em>, 1&ndash;150 characters). This <b>renames the first
                product</b>; it keeps its id, group and settings, so anything pointing at it still works.</li>
            <li><b>Check each &ldquo;Becomes system&rdquo; box</b> &mdash; all required, pre-filled from the first <em>NNmm</em> in the product
                name (<em>15mm Venetian</em> &rarr; <b>15mm</b>). With no mm in the name it is the <b>whole product name</b> &mdash; shorten it.
                The <b>Fabrics</b> column is how many fabrics will move: a check that you ticked the right ones.</li>
            <li><b>Press &ldquo;Combine into one product&rdquo;</b> (or <b>Cancel</b>). You land on the master&rsquo;s edit page:
                <code>Combined into &ldquo;Metal Venetian&rdquo; with 3 systems. 25mm Venetian, 35mm Venetian are now empty and deactivated
                &mdash; delete once you&rsquo;ve checked the result.</code> Open its <b>Systems</b> section: <b>Systems (3)</b> &mdash; 15mm
                (<b>Default</b>), 25mm, 35mm, each with its price tables.</li>
          </ul>

          <p class="prose"><b>What moves across</b> &mdash; all in <b>one transaction</b> (all of it or none; a failure shows
             <code>Could not combine: &hellip;</code> and changes nothing):</p>
          <ul class="steps">
            <li><b>Price tables</b> &mdash; re-pointed to the master and that size&rsquo;s new system.</li>
            <li><b>Fabrics</b> &mdash; moved to the master and <b>scoped to their own system</b>, so 25mm colours only show under 25mm.</li>
            <li><b>Markups and discounts</b> &mdash; the per-system rows travel with the system.</li>
            <li><b>Options</b> &mdash; merged onto the master <b>by name</b> (see below).</li>
            <li><b>The folded-in products</b> &mdash; set to <b>Inactive</b>, left as empty husks. The master&rsquo;s own size becomes the
                <b>Default</b> system.</li>
          </ul>

          <div class="oops"><b>&ldquo;These products are priced differently (e.g. per-slat vs width&times;drop), so they can&rsquo;t be systems of one
             product.&rdquo;</b> Every product must be set up the same way on its <b>Edit</b> page &mdash; the no-fabric, width-only, per-slat and
             per-square-metre ticks are all compared. Untick the odd one out, or fix it first. Also possible:
             <code>Give the combined product a valid name (1&ndash;150 chars).</code> and
             <code>Each new system needs a name (1&ndash;150 chars).</code></div>

          <div class="oops"><b>&ldquo;&quot;Metal Venetian&quot; already has more than one system &mdash; only the first (the master) may. Add single-size
             products to it.&rdquo;</b> Only the top product may already be a master. This almost always means the existing master was lower in the
             list than the new size &mdash; drag the master to the top and combine again.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Check the options afterwards.</b> Options are merged by <b>name</b>: if the
             master already has an option with the same name, the incoming copy <b>and all its choices are deleted</b>, not merged. An option
             that only the folded-in size had arrives on the master and, if its choices were system-specific, they are moved to the new
             system. Open the master&rsquo;s options and give them a look.</div></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>There is no un-combine.</b> The folded-in products are switched to
             <b>Inactive</b> (grey pill; their Fabrics, Price tables and Options counts emptied) and are no longer offered on quotes, so there is no hurry to delete them. When you do,
             the confirm says <code>Delete 25mm Venetian? This removes all options, extras, and price tables linked to it. Cannot be
             undone.</code> Existing quotes and orders keep the names they were saved with.</div></div>

          <p><b>Adding another size later.</b> Tick the master <b>and</b> the new single-size product, with the <b>master higher</b> in the list,
             and combine again. The blue panel reads <em>&ldquo;Metal Venetian is already a master, so the others are added to it as extra
             systems&hellip;&rdquo;</em>, the name box is pre-filled, the master&rsquo;s row shows <em>keeps its 3 existing systems</em>, and the
             new size is added as one more system: <code>Added 1 system to &ldquo;Metal Venetian&rdquo;. &hellip;</code> Afterwards check
             <b>Systems</b> (rename, reorder, <b>Default</b>) and that the new system has its price tables.</p>',
        'script'  => array_map(static fn ($k, $l) => [$l[0], $l[1], $l[2], $k], array_keys($S), $S),
];
