<?php
declare(strict_types=1);

/**
 * Guide: products-import-fabrics — "Adding fabrics — paste, Excel or library" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors admin/products/options.php (the Fabrics page: header buttons, the
 * "Bulk add … — paste a list" box with band chips, "Add one at a time", the
 * filter + bulk bar and the list), options-import.php (Import from Excel:
 * template, upload, the result messages), options-from-library.php,
 * options-copy.php and option-set-band.php. The cross-product workbook import
 * (options-bulk-import.php) is mentioned in the written steps only. Every
 * label, button and message is copied from those files.
 *
 * v2: one SCENE per script line. Beat times are worked out from where the
 * matching words fall in the voice-over ($at); data-len = line length ÷ 13.6.
 */

$S = [
    1  => ['1',  'Fabrics and bands',
        'A fabric is what the customer picks for the blind. The material, the colour, the slat. Every fabric belongs to a band. A band is a group of fabrics that cost the same, so they share one price table, instead of needing one each. The band is just a name you choose, like A, or Blackout. What matters is that it matches a price table.'],
    2  => ['2',  'The Fabrics page',
        'Fabrics live on one page. Open the product, and click the Fabrics tile. The page may not say Fabric. That word comes from the product\'s own settings, so a venetian\'s page might say Colours. Same page, same buttons. Across the top are the ways in, and they all add to the same list. Use whichever suits you, or mix them.'],
    3  => ['3',  'Paste a list: the band',
        'The quickest way is the paste box, which is already open. Start with the band. If the product has bands already, they show as buttons above the box. Click one to fill it in, so you never mistype it. They come from the bands you already use, so the spelling always matches. Typed Band A by habit? That is fine. It keeps just the A.'],
    4  => ['4',  'Paste a list: the names',
        'If the product has systems, there is a System box too. Leave it on all systems, or pick one, so a colour that only comes on one system stays off the others. That way, nobody can quote a colour that cannot be made. Then paste the names into the big box, one per line, or with commas between them. Press Add all.'],
    5  => ['5',  'What comes back',
        'A green message says how many went in, and to which band. Any that were already on the product are skipped, and it tells you how many, so nothing is ever doubled up. Your list fills up underneath, each fabric with its band beside it. Pasted the wrong band? Do not worry. There is a quick fix, coming up later.'],
    6  => ['6',  'One at a time',
        'Need a supplier, a colour or a code on a single fabric? Open Add one at a time, just below the paste box. It has boxes for the band, the name, the colour, the supplier and the code, and an Active tick. Leave Active ticked, unless you are parking a fabric you do not sell at the moment. It is slower than pasting, so keep it for the odd one.'],
    7  => ['7',  'Excel: the template',
        'Got a supplier spreadsheet? Click Import from Excel. Step one is to download the blank template. It has five columns. Band and name must be filled in. Colour, supplier and code are optional. Each row becomes one fabric, and the columns are already labelled for you. Delete the grey note on row three, paste your data in, and save.'],
    8  => ['8',  'Excel: upload it',
        'Step three, choose your file, and press Upload and import. Two things to know. It only reads the sheet that was showing when the file was saved, so do one tab at a time. And a file with no heading row still works. Column A is read as the band, B as the name, then colour, supplier and code.'],
    9  => ['9',  'When rows bounce',
        'If some rows have a problem, the good ones still go in. A red box lists the others by row number. Row seven, missing name, for example. Fix those rows, and upload the same file again. The rows already in are skipped as duplicates, so nothing doubles up. Then Continue product setup takes you on.'],
    10 => ['10', 'From the fabric library',
        'If the range is in the Fabric Library, click Add from fabric library, and choose the manufacturer. Every fabric is ticked, with a suggested band in a small box. Change any band right there, and untick what you do not sell. The manufacturer\'s name is filled in as the supplier. Then press Add ticked fabrics.'],
    11 => ['11', 'Copy from another product',
        'Selling the same range on two products? Click Copy from another product. Pick the product, choose which of its bands to bring, and copy. Anything already there is skipped, so it is safe to run twice. Fabrics tied to a system go to the system with the same name. This is how a fabric only product can share a blind\'s whole range in one go.'],
    12 => ['12', 'Fixing mistakes in bulk',
        'Something went in wrong? There is no need to start again. Type in the filter box to find the rows. Tick one, then hold Shift and tick another, to take everything in between. Then use Set band on selected, Set supplier on selected, or Delete selected. A whole range in the wrong band takes seconds to put right.'],
    13 => ['13', 'Bands must match a price table',
        'One last thing. A fabric only gets a price if its band matches a price table on that product. The band buttons show bands from your fabrics and your price tables together. So if you see one you do not recognise, that is the one to check, and Set band on selected puts it right. When you are done, press Next, price tables.'],
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

/** The fabrics list. $rows: [band, name, supplier, code, tickHtml|null, rowClass, rowStyle]. */
$flist = static function (array $rows, string $nameHead = 'Fabric'): string {
    $h = '<div class="fls fl6"><div class="hd"><span></span><span>Band</span><span>' . $nameHead . '</span><span>Supplier</span><span>Code</span><span></span></div>';
    foreach ($rows as $r) {
        $h .= '<div class="' . ($r[5] ?? '') . '" style="' . ($r[6] ?? '') . '"><span>' . ($r[4] ?? '<span class="tick"></span>') . '</span><span><span class="bp">Band ' . $r[0] . '</span></span>'
            . '<span>' . $r[1] . '</span><span class="mu">' . $r[2] . '</span><span class="mu">' . $r[3] . '</span><span class="ra"><u>Edit</u> <b>Delete</b></span></div>';
    }
    return $h . '</div>';
};

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Adding fabrics — paste, Excel or library',
        'eyebrow' => 'Products',
        'v'       => 2,
        // NB: index.php escapes the blurb, so keep it plain text (no entities).
        'blurb'   => 'Get a product’s fabrics in — paste a list, import a spreadsheet, pull a range from the library or copy another product — and fix the rows that go in wrong.',
        'lede'    => 'A <b>fabric</b> is what the customer picks for the blind &mdash; the material, the colour, the slat. Every fabric
                      belongs to a <b>band</b>, and the band is what links it to a price table. There are several ways to get fabrics in,
                      all from one page, and most need no spreadsheet at all. Watch it through once, then use <b>Jump to a chapter</b>
                      for the route you need. To get there: <b>Products</b> &rarr; the product &rarr; the <b>Fabrics</b> tile.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .app{ grid-template-columns:132px minmax(0,1fr); }
          .gd .sc{ position:relative; min-height:395px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .ttl{ font-size:.95rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.22rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.35rem; margin:.55rem 0; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.68rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                     border-radius:7px; padding:.24rem .6rem; font-size:.64rem; font-weight:600; white-space:nowrap; }
          .gd .btnn{ display:inline-flex; align-items:center; background:#1f3b5b; color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.68rem; font-weight:700; white-space:nowrap; }
          .gd .bb{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; }
          .gd .sw{ display:inline-grid; } .gd .sw > *{ grid-area:1/1; }
          .gd .swb{ display:grid; align-items:start; } .gd .swb > *{ grid-area:1/1; }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .15rem; }
          .gd .alr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:6px; padding:.32rem .55rem; font-size:.66rem; font-weight:600;
                    color:var(--ink); margin:0 0 .45rem; max-width:33rem; box-sizing:border-box; line-height:1.45; }
          .gd .alr.err{ background:var(--err-wash); border-left-color:var(--err); font-weight:500; }
          .gd .alr ul{ margin:.15rem 0 0; padding-left:1rem; }
          .gd .fl{ display:block; font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.45rem 0 .2rem; }
          .gd .inp{ display:grid; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:0 .5rem;
                    font-size:.74rem; background:var(--surface); color:var(--ink); box-sizing:border-box; position:relative; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .ph{ color:var(--faint); }
          .gd .tx{ display:block; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.3rem .5rem; font-family:ui-monospace,Menlo,Consolas,monospace;
                   font-size:.68rem; min-height:3.6rem; background:var(--surface); color:var(--ink); line-height:1.5; }
          .gd .selb{ display:inline-flex; align-items:center; justify-content:space-between; gap:.5rem; min-width:8rem; border:1px solid var(--border-strong,#c7ccd4);
                     border-radius:6px; padding:.2rem .45rem; font-size:.68rem; background:var(--surface); color:var(--ink); box-sizing:border-box; }
          .gd .selb::after{ content:"\25BE"; color:var(--faint); font-size:.58rem; }
          .gd .card{ border:1px solid var(--line); border-radius:12px; padding:.6rem .75rem; background:var(--surface); max-width:33rem; }
          .gd .card h4{ margin:0 0 .3rem; font-size:.78rem; color:var(--ink); }
          .gd .sm{ font-size:.62rem; color:var(--soft); margin:0 0 .4rem; line-height:1.45; }
          .gd .frow{ display:grid; grid-template-columns:7rem 1fr; gap:.6rem; }
          .gd .tick{ width:15px; height:15px; font-size:.55rem; }
          .gd .cbr{ display:flex; gap:.4rem; align-items:center; font-size:.68rem; color:var(--ink); }
          .gd .gd-tag{ font-size:.66rem; }

          /* band chips (click-to-fill) */
          .gd .bchips{ display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; margin:0 0 .35rem; }
          .gd .bchips small{ font-size:.58rem; color:var(--faint); font-weight:700; }
          .gd .bc{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); border-radius:999px; padding:.04rem .5rem; font-size:.62rem; font-weight:700; color:var(--ink); background:var(--surface); }
          .gd .bp{ display:inline-block; background:#1f3b5b; color:#fff; border-radius:999px; padding:.02rem .45rem; font-size:.56rem; font-weight:700; white-space:nowrap; }
          .gd .sum{ font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .35rem; }

          /* the fabrics list */
          .gd .fls{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:33rem; font-size:.66rem; background:var(--surface); }
          .gd .fls > div{ display:grid; grid-template-columns:1.2rem 4.4rem minmax(5rem,1fr) 5rem 3.4rem 5.2rem; gap:.3rem; align-items:center; padding:.28rem .5rem;
                          border-top:1px solid var(--line-2); color:var(--ink); }
          .gd .fls > div.hd{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; }
          .gd .fls .mu{ color:var(--soft); }
          .gd .fls .ra{ font-size:.58rem; display:flex; gap:.4rem; justify-content:flex-end; }
          .gd .fls .ra u{ color:var(--accent); } .gd .fls .ra b{ color:var(--err); font-weight:600; text-decoration:underline; }
          .gd .bulk{ display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; font-size:.6rem; color:var(--faint); margin:.35rem 0; max-width:33rem; }
          .gd .bulk .bi{ display:inline-flex; width:3rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:.08rem .3rem; font-size:.62rem; color:var(--ink); background:var(--surface); text-transform:uppercase; }
          .gd .srch{ display:flex; align-items:center; gap:.45rem; max-width:33rem; }
          .gd .srch .inp{ flex:1; }

          /* 1 — band diagram */
          .gd .bandmap{ display:grid; grid-template-columns:1fr auto 1fr; gap:.5rem .7rem; align-items:center; max-width:32rem; }
          .gd .bbox{ border:1px solid var(--line); border-radius:10px; padding:.4rem .55rem; background:var(--surface); }
          .gd .bbox h5{ margin:0 0 .25rem; font-size:.7rem; color:var(--ink); }
          .gd .f{ display:inline-block; font-size:.6rem; border:1px solid var(--line); border-radius:999px; padding:.06rem .4rem; margin:0 .2rem .2rem 0; color:var(--soft); background:var(--panel); }
          .gd .ptab{ border:1px solid var(--line); border-radius:8px; padding:.35rem .5rem; background:var(--panel); font-size:.66rem; color:var(--ink); }
          .gd .mgrid{ display:inline-grid; grid-template-columns:repeat(4,.6rem); gap:2px; margin-left:.3rem; vertical-align:middle; }
          .gd .mgrid i{ display:block; height:.4rem; background:color-mix(in srgb,var(--accent) 30%,transparent); border-radius:1px; }

          /* 2 — tiles */
          .gd .tiles{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; max-width:33rem; }
          .gd .tile{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--surface); font-size:.64rem; color:var(--soft); }
          .gd .tile b{ display:block; color:var(--ink); font-size:.72rem; }
          .gd .tile i{ display:block; font-style:normal; font-size:1rem; font-weight:800; color:var(--accent); }

          /* 7–8 — excel */
          .gd .xl{ border:1px solid #1d6f42; border-radius:8px; overflow:hidden; font-size:.6rem; background:#fff; color:#1f2937; max-width:24rem; }
          .gd .xl .xh{ background:#1d6f42; color:#fff; font-weight:700; padding:.25rem .5rem; }
          .gd .xl .xr{ display:grid; grid-template-columns:2.6rem 1.3fr 1fr 1fr .8fr; }
          .gd .xl .xr span{ border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:.15rem .3rem; white-space:nowrap; overflow:hidden; }
          .gd .xl .xr.h span{ background:#1f3b5b; color:#fff; font-weight:700; }
          .gd .xl .note{ padding:.2rem .3rem; color:#6b7280; font-style:italic; border-bottom:1px solid #e5e7eb; font-size:.56rem; }
          .gd .xl .tabs{ display:flex; gap:2px; background:#f3f4f6; padding:2px 4px; }
          .gd .xl .tabs span{ padding:.08rem .45rem; background:#e5e7eb; border-radius:0 0 4px 4px; font-size:.56rem; }
          .gd .xl .tabs span.on{ background:#fff; color:#1d6f42; font-weight:700; }
          .gd .step{ font-size:.8rem; font-weight:800; color:var(--ink); margin:.45rem 0 .25rem; }
          .gd .tip{ background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.35rem .55rem; font-size:.62rem; color:var(--soft); max-width:33rem; line-height:1.45; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; }
            .gd .side{ display:none; }
            .gd .sc{ min-height:470px; }
            .gd .tiles{ grid-template-columns:repeat(2,1fr); }
            .gd .bandmap{ grid-template-columns:1fr; }
            .gd .bandmap .arrow{ display:none; }
            .gd .fls.fl6 > div{ grid-template-columns:1.1rem 3.9rem minmax(4rem,1fr) 3.6rem; }
            .gd .fls.fl6 > div > span:nth-child(5), .gd .fls.fl6 > div > span:nth-child(6){ display:none; }
            .gd .frow{ grid-template-columns:1fr; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / fabrics</span></div>
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
                  <div class="ttl">Roller Blind &mdash; Fabrics</div>
                  <div class="bb" style="margin-bottom:.6rem"><span class="btns">&larr; Back to setup wizard</span><span class="btns">Add from fabric library</span>
                    <span class="btns">Copy from another product</span><span class="btns">Import from Excel</span><span class="btnp">Next: price tables &rarr;</span></div>
                  ' . $flist([['A', 'Cream', 'Louvolite', 'LV101'], ['A', 'Stone', 'Louvolite', 'LV102'], ['B', 'Blackout Ivory', 'Louvolite', 'LV201']]) . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; thirteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — fabrics and bands -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">Fabrics belong to bands &mdash; bands have price tables</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(1, 'The material') . '">the material</span><span class="chip a-pop" style="--d:' . $at(1, 'the colour') . '">the colour</span><span class="chip a-pop" style="--d:' . $at(1, 'the slat') . '">the slat</span>
                  </div>
                  <div class="bandmap">
                    <div class="bbox a-rise" style="--d:' . $at(1, 'Every fabric belongs') . '"><h5>Band <span class="a-type" style="--d:' . $at(1, 'like A') . ';--ts:1;--tt:.2s">A</span></h5>
                      <span class="f">Cream</span><span class="f">Stone</span><span class="f">Linen Oyster</span><span class="f">Polaris White</span></div>
                    <span class="arrow a-fade" style="--d:' . $at(1, 'so they share') . '">&rarr;</span>
                    <div class="ptab a-pop" style="--d:' . $at(1, 'so they share') . '">Price table &middot; Band A<span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></div>
                    <div class="bbox a-rise" style="--d:' . $at(1, 'cost the same') . '"><h5>Band <span class="a-type" style="--d:' . $at(1, 'or Blackout') . ';--ts:8;--tt:.6s">Blackout</span></h5>
                      <span class="f">Blackout Ivory</span><span class="f">Blackout Grey</span></div>
                    <span class="arrow a-fade" style="--d:' . $at(1, 'instead of needing') . '">&rarr;</span>
                    <div class="ptab a-pop" style="--d:' . $at(1, 'instead of needing') . '">Price table &middot; Band Blackout<span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(1, 'What matters') . ';border-color:var(--good);color:var(--good)">The band name must match a price table</span></div>
                </div>

                <!-- 2 — the page -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="tiles a-rise" style="--d:' . $at(2, 'Open the product') . '">
                    <div class="tile"><b>Systems</b><i>2</i>click to manage</div>
                    <div class="tile a-ring" style="--d:' . $at(2, 'click the Fabrics') . '"><b>Fabrics</b><i>0</i>click to manage</div>
                    <div class="tile"><b>Price tables</b><i>0</i>click to manage</div>
                    <div class="tile"><b>Options</b><i>3</i>click to manage</div>
                  </div>
                  <div class="ttl" style="margin-top:.7rem"><span class="sw"><span class="a-mid" style="--d:' . $at(2, 'click the Fabrics', 1) . ';--d2:' . $at(2, 'might say Colours') . '">Roller Blind &mdash; Fabrics</span>
                    <span class="a-fade" style="--d:' . $at(2, 'might say Colours') . '">Metal Venetian &mdash; <span class="a-ring" style="--d:' . $at(2, 'might say Colours') . ';border-radius:4px">Colours</span></span></span></div>
                  <div class="bb">
                    <span class="btns a-pop" style="--d:' . $at(2, 'Across the top') . '">&larr; Back to setup wizard</span>
                    <span class="btns a-pop" style="--d:' . $at(2, 'are the ways in') . '">Add from fabric library</span>
                    <span class="btns a-pop" style="--d:' . $at(2, 'are the ways in', .4) . '">Copy from another product</span>
                    <span class="btns a-pop" style="--d:' . $at(2, 'are the ways in', .8) . '">Import from Excel</span>
                    <span class="btnp a-pop" style="--d:' . $at(2, 'are the ways in', 1.2) . '">Next: price tables &rarr;</span>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(2, 'Same page') . '">Same page, same buttons &mdash; just your word</span>
                    <span class="chip a-pop" style="--d:' . $at(2, 'they all add') . ';border-color:var(--good);color:var(--good)">They all add to the same list</span>
                    <span class="chip a-pop" style="--d:' . $at(2, 'Use whichever') . '">Use one, or mix them</span></div>
                  <div class="a-move" style="--fx:60%;--fy:16rem;--tx:33%;--ty:1.6rem;--d:' . $at(2, 'click the Fabrics', -1.4) . ';--md:1.3s"><span class="a-mid" style="display:block;--d:0s;--d2:' . $at(2, 'The page may not') . '">' . $ptr . '</span></div>
                </div>

                <!-- 3 — paste a list: the band -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="card a-rise" style="--d:.3s">
                    <div class="sum">&#9662; Bulk add fabrics &mdash; paste a list</div>
                    <p class="sm">One name per line <b>or comma-separated</b> &mdash; paste either way. They all go in under the same band (and optionally one system). Duplicates are skipped silently.</p>
                    <div class="bchips a-fade" style="--d:' . $at(3, 'they show as buttons') . '"><small>Bands:</small>
                      <span class="bc a-ring" style="--d:' . $at(3, 'Click one') . '">A</span><span class="bc">B</span><span class="bc">Blackout</span></div>
                    <div style="max-width:9rem"><span class="fl">Band <span class="req">*</span></span>
                      <span class="inp a-ring" style="--d:' . $at(3, 'Start with the band') . '"><span class="ph a-out" style="--d:' . $at(3, 'Click one', .8) . '">A</span>
                        <span class="a-mid" style="--d:' . $at(3, 'Click one', .8) . ';--d2:' . $at(3, 'Typed Band A') . '">A</span>
                        <span class="a-mid" style="--d:' . $at(3, 'Typed Band A') . ';--d2:' . $at(3, 'It keeps just', .6) . '"><span class="a-type" style="--d:' . $at(3, 'Typed Band A') . ';--ts:6;--tt:.6s">Band A</span></span>
                        <span class="a-fade" style="--d:' . $at(3, 'It keeps just', .6) . '">A</span></span></div>
                  </div>
                  <span class="chip a-pop" style="--d:' . $at(3, 'It keeps just') . ';margin-top:.6rem">&ldquo;Band A&rdquo; <span class="arrow">&rarr;</span> stored as A</span>
                  <div class="a-move" style="--fx:70%;--fy:15rem;--tx:13%;--ty:6.7rem;--d:' . $at(3, 'Click one', -1.2) . ';--md:1.2s"><span class="a-mid" style="display:block;--d:0s;--d2:' . $at(3, 'Typed Band A', -.3) . '">' . $ptr . '</span></div>
                </div>

                <!-- 4 — paste a list: the names -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="card">
                    <div class="sum">&#9662; Bulk add fabrics &mdash; paste a list</div>
                    <div class="frow">
                      <div><span class="fl" style="margin-top:0">Band <span class="req">*</span></span><span class="inp">A</span></div>
                      <div class="a-rise" style="--d:' . $at(4, 'there is a System box') . '"><span class="fl" style="margin-top:0">System (optional)</span>
                        <span class="selb a-ring" style="--d:' . $at(4, 'Leave it on all') . ';width:100%"><span class="sw"><span class="a-out" style="--d:' . $at(4, 'or pick one', .6) . '">All systems on this product</span><span class="a-fade" style="--d:' . $at(4, 'or pick one', .6) . '">Standard</span></span></span></div>
                    </div>
                    <span class="fl">Fabric names &mdash; one per line or comma-separated <span class="req">*</span></span>
                    <div class="tx a-ring" style="--d:' . $at(4, 'paste the names') . '">
                      <div><span class="a-type" style="--d:' . $at(4, 'one per line') . ';--ts:5;--tt:.4s">Cream</span></div>
                      <div><span class="a-type" style="--d:' . $at(4, 'one per line', .6) . ';--ts:5;--tt:.4s">Stone</span></div>
                      <div><span class="a-type" style="--d:' . $at(4, 'with commas') . ';--ts:27;--tt:1.4s">Linen Oyster, Polaris White</span></div></div>
                    <div style="margin-top:.45rem"><span class="btnp a-press a-ring" style="--d:' . $at(4, 'Press Add all') . '">Add all</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(4, 'stays off the others') . '">One system only = stays off the others</span>
                    <span class="chip a-pop" style="--d:' . $at(4, 'nobody can quote') . '">No quoting a colour that can&rsquo;t be made</span></div>
                </div>

                <!-- 5 — what comes back -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="alr a-pop" style="--d:.4s">Added 4 to Band A (one system only). <span class="a-fade" style="--d:' . $at(5, 'Any that were') . '">Skipped 2 (likely duplicates).</span></div>
                  <div class="ttl a-fade" style="--d:' . $at(5, 'Your list') . '">Fabrics (6)</div>
                  <div class="a-rise" style="--d:' . $at(5, 'Your list') . '">' . $flist([
                        ['A', 'Cream', '', ''], ['A', 'Linen Oyster', '', ''], ['A', 'Polaris White', '', ''], ['A', 'Stone', '', ''],
                        ['B', 'Blackout Grey', 'Louvolite', 'LV202'], ['B', 'Blackout Ivory', 'Louvolite', 'LV201'],
                    ]) . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(5, 'nothing is ever doubled') . ';border-color:var(--good);color:var(--good)">Duplicates skipped &mdash; never doubled</span>
                    <span class="chip a-pop" style="--d:' . $at(5, 'with its band beside') . '">Each fabric shows its <span class="bp" style="margin-left:.25rem">Band A</span></span>
                    <span class="chip a-pop" style="--d:' . $at(5, 'quick fix') . '">Wrong band? Quick fix in chapter 12</span></div>
                </div>

                <!-- 6 — one at a time -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="card" style="opacity:.55;margin-bottom:.5rem;padding:.4rem .75rem"><div class="sum" style="margin:0">&#9662; Bulk add fabrics &mdash; paste a list</div></div>
                  <div class="card a-rise" style="--d:' . $at(6, 'Open Add one') . '">
                    <div class="sum">&#9662; Add one at a time <span style="font-weight:400;color:var(--faint)">&mdash; for setting supplier / colour / code on a single fabric</span></div>
                    <p class="sm"><b>Bulk add</b> above is quicker for most cases. Use this only when you need to set a supplier, colour, or code on an individual fabric.</p>
                    <div style="display:grid;grid-template-columns:3.5rem 1.4fr 1fr 1fr .8fr;gap:.4rem">
                      <div class="a-fade" style="--d:' . $at(6, 'the band, the name') . '"><span class="fl">Band <span class="req">*</span></span><span class="inp">B</span></div>
                      <div class="a-fade" style="--d:' . $at(6, 'the name, the colour') . '"><span class="fl">Fabric name <span class="req">*</span></span><span class="inp"><span><span class="a-type" style="--d:' . $at(6, 'the name, the colour') . ';--ts:13;--tt:.8s">Blackout Sand</span></span></span></div>
                      <div class="a-fade" style="--d:' . $at(6, 'the colour, the') . '"><span class="fl">Colour</span><span class="inp"><span><span class="a-type" style="--d:' . $at(6, 'the colour, the') . ';--ts:4;--tt:.4s">Sand</span></span></span></div>
                      <div class="a-fade" style="--d:' . $at(6, 'the supplier and') . '"><span class="fl">Supplier</span><span class="inp"><span><span class="a-type" style="--d:' . $at(6, 'the supplier and') . ';--ts:9;--tt:.6s">Louvolite</span></span></span></div>
                      <div class="a-fade" style="--d:' . $at(6, 'and the code') . '"><span class="fl">Code</span><span class="inp"><span><span class="a-type" style="--d:' . $at(6, 'and the code') . ';--ts:5;--tt:.4s">LV203</span></span></span></div>
                    </div>
                    <div class="cbr a-fade" style="--d:' . $at(6, 'an Active tick') . ';margin:.5rem 0"><span class="a-ring" style="--d:' . $at(6, 'Leave Active') . ';border-radius:5px;display:inline-flex">' . $tk(true) . '</span> Active</div>
                    <span class="btnp">Add fabric</span>
                  </div>
                  <span class="chip a-pop" style="--d:' . $at(6, 'unless you are parking') . ';margin-top:.5rem">Unticked = stays in the list with a grey <b>&nbsp;Inactive&nbsp;</b> pill</span>
                  <span class="chip a-pop" style="--d:' . $at(6, 'keep it for the odd') . ';margin-top:.5rem">Slower &mdash; keep it for the odd one</span>
                </div>

                <!-- 7 — excel: the template -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="ttl">Import fabrics &mdash; Roller Blind</div>
                  <div class="step">1. Download the template</div>
                  <div class="tip a-fade" style="--d:' . $at(7, 'It has five') . '">Columns: <code>Band*</code>, <code>Fabric name*</code>, <code>Colour</code>, <code>Supplier</code>, <code>Code</code>. Asterisks = required. Each row becomes one fabric.</div>
                  <div style="margin:.4rem 0"><span class="btnn a-press a-ring" style="--d:' . $at(7, 'download the blank') . '">Download blank template (.xlsx)</span></div>
                  <div class="xl a-rise" style="--d:' . $at(7, 'It has five') . '">
                    <div class="xh">Roller Blind - Fabric template.xlsx</div>
                    <div class="xr h"><span class="a-ring" style="--d:' . $at(7, 'Band and name') . '">Band*</span><span class="a-ring" style="--d:' . $at(7, 'Band and name') . '">Fabric name*</span><span>Colour</span><span>Supplier</span><span>Code</span></div>
                    <div class="swb">
                      <div class="a-out" style="--d:' . $at(7, 'Delete the grey', 1) . '"><div class="xr"><span>&nbsp;</span><span></span><span></span><span></span><span></span></div>
                        <div class="note a-ring" style="--d:' . $at(7, 'Delete the grey') . '">* = required. Bands like A, B, C, AA, AAA &mdash; case is normalised. Duplicate (band + name + colour) rows are skipped on import.</div></div>
                      <div class="a-fade" style="--d:' . $at(7, 'paste your data') . '">
                        <div class="xr"><span>A</span><span>Cream</span><span>Cream</span><span>Decora</span><span>D101</span></div>
                        <div class="xr"><span>A</span><span>Stone</span><span>Stone</span><span>Decora</span><span>D102</span></div>
                        <div class="xr"><span>B</span><span>Blackout Ivory</span><span>Ivory</span><span>Decora</span><span>D201</span></div></div>
                    </div>
                  </div>
                  <div class="step a-fade" style="--d:' . $at(7, 'and save') . '">2. Fill it in &mdash; then save</div>
                </div>

                <!-- 8 — excel: upload -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="step" style="margin-top:0">3. Upload</div>
                  <span class="fl">Filled template (.xlsx)</span>
                  <div class="bb"><span class="btns a-press" style="--d:' . $at(8, 'choose your file') . '">Choose File</span><span class="a-type" style="--d:' . $at(8, 'choose your file', .6) . ';--ts:20;--tt:.6s;font-size:.66rem">Decora rollers.xlsx</span></div>
                  <div class="bb" style="margin-top:.5rem"><span class="btnp a-press a-ring" style="--d:' . $at(8, 'press Upload') . '">Upload &amp; import</span><span class="btns">Cancel</span></div>
                  <div class="xl a-rise" style="--d:' . $at(8, 'It only reads') . ';margin-top:.7rem">
                    <div class="xr"><span>A</span><span>Cream</span><span>Cream</span><span>Decora</span><span>D101</span></div>
                    <div class="xr"><span>A</span><span>Stone</span><span>Stone</span><span>Decora</span><span>D102</span></div>
                    <div class="tabs"><span class="on a-ring" style="--d:' . $at(8, 'that was showing') . '">Rollers</span><span>Romans</span><span>Verticals</span><span>Venetians</span></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(8, 'that was showing') . ';border-color:#f59e0b">Only the sheet showing when saved &mdash; one tab at a time</span>
                    <span class="chip a-pop" style="--d:' . $at(8, 'no heading row') . '">No headings? A = Band &middot; B = Name &middot; C = Colour &middot; D = Supplier &middot; E = Code</span>
                  </div>
                </div>

                <!-- 9 — when rows bounce -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="alr a-pop" style="--d:' . $at(9, 'the good ones') . '">Imported <b>38</b> fabrics (header row detected). <span class="a-fade" style="--d:' . $at(9, 'skipped as duplicates') . '">Skipped 3 duplicates.</span></div>
                  <div class="alr err a-pop" style="--d:' . $at(9, 'A red box') . '"><b>Some rows had problems:</b><ul>
                    <li class="a-ring" style="--d:' . $at(9, 'Row seven') . ';border-radius:4px">Row 7: missing name</li>
                    <li>Row 9: band code was just &lsquo;Band&rsquo; with nothing after it</li></ul></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(9, 'Fix those rows') . '">Fix rows 7 and 9 &rarr; upload the same file again</span>
                    <span class="chip a-pop" style="--d:' . $at(9, 'skipped as duplicates') . ';border-color:var(--good);color:var(--good)">Rows already in are skipped</span>
                  </div>
                  <div class="bb"><span class="btnp a-ring" style="--d:' . $at(9, 'Then Continue') . '">Continue product setup &rarr;</span><span class="btns">View imported fabrics</span></div>
                </div>

                <!-- 10 — from the fabric library -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="swb">
                    <div class="a-mid" style="--d:0s;--d2:' . $at(10, 'Every fabric is ticked', -.3) . '">
                      <div class="ttl">Roller Blind &mdash; add fabrics from library</div>
                      <div class="card"><h4>Pick a fabric manufacturer</h4>
                        <div class="fls" style="font-size:.66rem"><div class="hd" style="grid-template-columns:1fr 4rem 5rem"><span>Manufacturer</span><span style="text-align:right">Fabrics</span><span></span></div>
                          <div style="grid-template-columns:1fr 4rem 5rem"><b>Decora</b><span style="text-align:right">212</span><span style="text-align:right"><span class="btns">Choose &rarr;</span></span></div>
                          <div style="grid-template-columns:1fr 4rem 5rem"><b>Louvolite</b><span style="text-align:right">348</span><span style="text-align:right"><span class="btns a-press a-ring" style="--d:' . $at(10, 'choose the manufacturer') . '">Choose &rarr;</span></span></div></div></div>
                    </div>
                    <div class="a-fade" style="--d:' . $at(10, 'Every fabric is ticked', -.3) . '">
                      <div class="bb" style="justify-content:space-between;max-width:33rem;margin-bottom:.45rem"><span><b style="font-size:.8rem">Louvolite</b><br><span class="sm">348 fabrics in the library</span></span>
                        <span><span class="fl" style="margin-top:0">Apply to system</span><span class="selb">All systems</span></span>
                        <span class="btnp a-press a-ring" style="--d:' . $at(10, 'Then press Add') . '">Add ticked fabrics</span></div>
                      <p class="sm">All ticked by default. The <b>band</b> is pre-filled from the library&rsquo;s suggested band &mdash; edit any to suit your pricing before adding.</p>
                      <div class="fls"><div class="hd" style="grid-template-columns:1.2rem 1fr 4rem 3.6rem 3rem 4rem"><span>' . $tk(true) . '</span><span>Fabric</span><span>Colour</span><span>Code</span><span>Band</span><span>Type</span></div>
                        <div style="grid-template-columns:1.2rem 1fr 4rem 3.6rem 3rem 4rem">' . $tk(true) . '<b>Carnival</b><span>White</span><span class="mu">CA01</span><span class="inp" style="min-height:20px;font-size:.6rem;padding:0 .3rem">A</span><span class="mu">Roller</span></div>
                        <div style="grid-template-columns:1.2rem 1fr 4rem 3.6rem 3rem 4rem">' . $tk(true) . '<b>Carnival</b><span>Ivory</span><span class="mu">CA02</span><span class="inp a-ring" style="--d:' . $at(10, 'Change any band') . ';min-height:20px;font-size:.6rem;padding:0 .3rem"><span class="a-out" style="--d:' . $at(10, 'right there') . '">A</span><span class="a-fade" style="--d:' . $at(10, 'right there') . '">B</span></span><span class="mu">Roller</span></div>
                        <div style="grid-template-columns:1.2rem 1fr 4rem 3.6rem 3rem 4rem"><span class="sw">' . $tk(true, 'a-out', '--d:' . $at(10, 'untick what')) . $tk(false, 'a-fade', '--d:' . $at(10, 'untick what')) . '</span><b>Carnival</b><span>Lime</span><span class="mu">CA07</span><span class="inp" style="min-height:20px;font-size:.6rem;padding:0 .3rem">A</span><span class="mu">Roller</span></div></div>
                      <div class="alr a-pop" style="--d:' . $at(10, 'Then press Add', 1) . ';margin-top:.45rem">Added 2 fabrics from &ldquo;Louvolite&rdquo;.</div>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:' . $at(10, 'filled in as the supplier') . ';margin-top:.4rem">Supplier = Louvolite, filled in for you</span>
                </div>

                <!-- 11 — copy from another product -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="ttl">Copy fabrics into Vertical Fabric Only</div>
                  <div class="card a-rise" style="--d:' . $at(11, 'Pick the product') . '"><h4>1. Copy from</h4><span class="fl">Source product</span>
                    <span class="selb" style="min-width:13rem"><span class="sw"><span class="a-out" style="--d:' . $at(11, 'Pick the product', 1) . '">&mdash; Choose a product &mdash;</span><span class="a-fade" style="--d:' . $at(11, 'Pick the product', 1) . '">Vertical Blind (86)</span></span></span></div>
                  <div class="card a-rise" style="--d:' . $at(11, 'choose which') . ';margin-top:.5rem"><h4>2. Bands in Vertical Blind</h4>
                    <div class="cbr">' . $tk(true) . '<span style="color:var(--soft)">Select all bands</span></div>
                    <div class="cbr" style="margin-top:.25rem">' . $tk(true) . '<span><b>Band A</b> <span style="color:var(--faint)">&middot; 40 fabrics</span></span></div>
                    <div class="cbr" style="margin-top:.25rem">' . $tk(true) . '<span><b>Band B</b> <span style="color:var(--faint)">&middot; 46 fabrics</span></span></div>
                    <div style="margin-top:.45rem"><span class="btnp a-press" style="--d:' . $at(11, 'and copy') . '">Copy selected into Vertical Fabric Only &rarr;</span></div>
                    <p class="sm a-fade" style="--d:' . $at(11, 'safe to run twice') . ';margin:.4rem 0 0">Existing fabrics (same band + name + colour) are skipped, so it&rsquo;s safe to run this more than once.</p></div>
                  <div class="alr a-pop" style="--d:' . $at(11, 'and copy', .8) . ';margin-top:.5rem">Copied 86 fabrics from &ldquo;Vertical Blind&rdquo;.</div>
                  <span class="chip a-pop" style="--d:' . $at(11, 'Fabrics tied') . '">System-only fabrics &rarr; the same-named system</span>
                </div>

                <!-- 12 — fixing mistakes in bulk -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="alr a-pop" style="--d:' . $at(12, 'takes seconds', -1) . '">3 fabrics set to band B.</div>
                  <div class="srch"><span class="inp a-ring" style="--d:' . $at(12, 'Type in the filter') . '"><span class="ph a-out" style="--d:' . $at(12, 'to find the rows', -.5) . '">Filter (e.g. polaris cream)&hellip;</span><span><span class="a-type" style="--d:' . $at(12, 'to find the rows', -.5) . ';--ts:7;--tt:.6s">polaris</span></span></span>
                    <span class="sm" style="margin:0"><span class="sw"><span class="a-out" style="--d:' . $at(12, 'to find the rows') . '">9 fabrics</span><span class="a-fade" style="--d:' . $at(12, 'to find the rows') . '">Showing 3 of 9</span></span></span><span class="a-fade" style="color:var(--accent);font-size:.62rem;--d:' . $at(12, 'to find the rows') . '">Clear</span></div>
                  <div class="bulk">
                    <span class="btns" style="font-size:.58rem">Delete selected</span> &middot;
                    <span class="bi a-ring" style="--d:' . $at(12, 'Then use Set band') . '"><span class="a-type" style="--d:' . $at(12, 'Then use Set band', .5) . ';--ts:1;--tt:.2s">B</span></span>
                    <span class="btns a-press" style="--d:' . $at(12, 'Set supplier', -.4) . ';font-size:.58rem">Set band on selected</span> &middot;
                    <span class="bi" style="width:4rem;text-transform:none;color:var(--faint)">supplier</span><span class="btns" style="font-size:.58rem">Set supplier on selected</span>
                    <span><span class="sw"><span class="a-out" style="--d:' . $at(12, 'Tick one') . '">No rows selected</span><span class="a-mid" style="--d:' . $at(12, 'Tick one') . ';--d2:' . $at(12, 'and tick another', .2) . '">1 row selected</span><span class="a-fade" style="--d:' . $at(12, 'and tick another', .2) . '">3 rows selected</span></span></span>
                  </div>
                  <div class="chips" style="margin:.1rem 0 .4rem"><span class="chip a-pop" style="--d:' . $at(12, 'hold Shift') . '"><b>Shift</b>-click = everything in between</span></div>
                  <div class="swb">
                    <div class="a-out" style="--d:' . $at(12, 'to find the rows') . '">' . $flist([
                        ['A', 'Cream', 'Louvolite', 'LV101'], ['A', 'Polaris Cream', 'Louvolite', 'PO11'], ['A', 'Polaris Grey', 'Louvolite', 'PO12'],
                        ['A', 'Polaris White', 'Louvolite', 'PO10'], ['A', 'Stone', 'Louvolite', 'LV102'],
                    ]) . '</div>
                    <div class="a-fade" style="--d:' . $at(12, 'to find the rows') . '">' . $flist([
                        ['<span class="sw"><span class="a-out" style="--d:' . $at(12, 'takes seconds', -1) . '">A</span><span class="a-fade" style="--d:' . $at(12, 'takes seconds', -1) . '">B</span></span>', 'Polaris Cream', 'Louvolite', 'PO11', '<span class="tick a-sel" style="--d:' . $at(12, 'Tick one') . '">&#10003;</span>'],
                        ['<span class="sw"><span class="a-out" style="--d:' . $at(12, 'takes seconds', -1) . '">A</span><span class="a-fade" style="--d:' . $at(12, 'takes seconds', -1) . '">B</span></span>', 'Polaris Grey', 'Louvolite', 'PO12', '<span class="tick a-sel" style="--d:' . $at(12, 'and tick another', .2) . '">&#10003;</span>'],
                        ['<span class="sw"><span class="a-out" style="--d:' . $at(12, 'takes seconds', -1) . '">A</span><span class="a-fade" style="--d:' . $at(12, 'takes seconds', -1) . '">B</span></span>', 'Polaris White', 'Louvolite', 'PO10', '<span class="tick a-sel" style="--d:' . $at(12, 'and tick another') . '">&#10003;</span>'],
                    ]) . '</div>
                  </div>
                </div>

                <!-- 13 — bands must match -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="sct a-fade" style="--d:.2s">A fabric prices only if its band has a price table</div>
                  <div class="bandmap" style="grid-template-columns:1fr 1fr">
                    <div class="bbox a-rise" style="--d:' . $at(13, 'its band matches') . '"><h5>Fabric bands</h5><span class="bp">Band A</span> <span class="bp">Band B</span> <span class="bp a-ring" style="--d:' . $at(13, 'you do not recognise') . '">Band Blackot</span></div>
                    <div class="bbox a-rise" style="--d:' . $at(13, 'a price table on that') . '"><h5>Price tables</h5><span class="ptab" style="display:inline-block;padding:.1rem .4rem">Band A</span> <span class="ptab" style="display:inline-block;padding:.1rem .4rem">Band B</span> <span class="ptab" style="display:inline-block;padding:.1rem .4rem">Band Blackout</span></div>
                  </div>
                  <div class="bchips a-fade" style="--d:' . $at(13, 'The band buttons') . ';margin-top:.7rem"><small>Bands:</small><span class="bc">A</span><span class="bc">B</span><span class="bc">Blackout</span>
                    <span class="bc a-ring" style="--d:' . $at(13, 'you do not recognise') . ';border-color:var(--err);color:var(--err)">Blackot</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(13, 'that is the one') . ';border-color:var(--err);color:var(--err)">&ldquo;Blackot&rdquo; fabrics have no price table &rarr; no price</span></div>
                  <span class="btnp a-ring" style="--d:' . $at(13, 'press Next') . '">Next: price tables &rarr;</span>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>A product&rsquo;s <b>fabrics</b> are what the customer picks &mdash; in the page&rsquo;s own words, <em>&ldquo;the actual material /
             colour / slat type they want&rdquo;</em>. They are not its <b>options</b> (see <em>Adding options</em>). They live on one page:
             <b>Products &rarr; the product &rarr; the Fabrics tile</b> (or <b>Full manage &raquo;</b> on the edit page&rsquo;s Fabrics section).
             The header has <b>&larr; Back to setup wizard</b>, <b>Add from fabric library</b>, <b>Copy from another product</b>,
             <b>Import from Excel</b> and <b>Next: price tables &rarr;</b>.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>It may not say &ldquo;Fabric&rdquo; on your screen.</b> The word is the
             product&rsquo;s own label (set when the product was made, and on <b>Edit product</b>) &mdash; e.g. <em>Colour</em> for metal venetians,
             <em>Finish</em> for wood. Type <em>Colour</em> and the page reads <em>Colours</em>, the paste box <em>Colour names</em>, the template
             <em>Colour name*</em>. Same page, same buttons.</div></div>

          <p class="prose"><b>The band.</b> A band groups fabrics that cost the same per blind size, so they share <em>one</em> price table.
             It is free text up to 60 characters &mdash; <em>A</em>, <em>AA</em>, <em>Plain</em>, <em>Blackout</em> are all fine. A leading
             &ldquo;Band &rdquo; is removed when you type one (<em>Band AA</em> &rarr; <em>AA</em>) in the paste box, <b>Add one at a time</b>,
             the Excel import and <b>Set band on selected</b>; the library pull&rsquo;s per-row box takes exactly what you type, so put just the
             code there. The Excel import, the library pull and <b>Set band on selected</b> store bands in <b>capitals</b>; band matching
             ignores case.</p>

          <p class="prose"><b>1) Bulk add fabrics &mdash; paste a list</b> (open by default).</p>
          <ul class="steps">
            <li><b>Bands:</b> &mdash; a row of buttons, one per band already on this product (from its fabrics <em>and</em> its price tables).
                Click one to fill the box. The <b>Band *</b> box also suggests them as you type.</li>
            <li><b>System (optional)</b> &mdash; appears when the product has systems: <em>All systems on this product</em> or one system.</li>
            <li><b>&hellip; names &mdash; one per line or comma-separated *</b> &mdash; paste, then <b>Add all</b>:
                <code>Added 14 to Band A.</code> (<code>(one system only)</code> when a system was picked), plus
                <code>Skipped 2 (likely duplicates).</code> Errors: <code>Band code is required.</code>,
                <code>No names &mdash; paste at least one name into the box.</code></li>
          </ul>

          <p class="prose"><b>2) Add one at a time &mdash; for setting supplier / colour / code on a single fabric</b> (folded; click to open).
             <b>Band *</b>, <b>Fabric name *</b>, <b>Colour</b> (hidden when the product&rsquo;s word already means colour, or the
             <em>Show separate &ldquo;Colour&rdquo; column</em> tick is off), <b>Supplier</b>, <b>Code</b>, <b>Active</b> (ticked) and
             <b>Add fabric</b>. An unticked one stays in the list with a grey <b>Inactive</b> pill. Errors:
             <code>Band code is required (e.g. A, B, C).</code>, <code>Fabric name is required.</code>,
             <code>A fabric with that name + colour already exists for this product.</code></p>

          <p class="prose"><b>3) Import from Excel</b> &mdash; <em>Import fabrics &mdash; &lt;product&gt;</em>, three numbered sections.</p>
          <ul class="steps">
            <li><b>1. Download the template</b> &mdash; <b>Download blank template (.xlsx)</b>: columns <b>Band*</b>, <b>Fabric name*</b>,
                <b>Colour</b>, <b>Supplier</b>, <b>Code</b>. Each row becomes one fabric. Row 3 holds a grey note
                (<em>* = required. Bands like A, B, C, AA, AAA &mdash; case is normalised&hellip;</em>) &mdash; delete it or paste over it, or it
                is read as a row with no name.</li>
            <li><b>2. Fill it in</b> &mdash; leave Supplier / Colour / Code blank if you don&rsquo;t have them. <b>Headerless files also work</b>:
                with no recognisable header the importer reads A = Band, B = Name, C = Colour, D = Supplier, E = Code.</li>
            <li><b>3. Upload</b> &mdash; <b>Filled template (.xlsx)</b> takes .xlsx, .xlsm, .xls, .csv or .ods up to 5 MB, then
                <b>Upload &amp; import</b> (or <b>Cancel</b>).</li>
            <li><b>It reads only the active sheet</b> &mdash; the tab showing when the file was last saved. Split a multi-tab workbook, or paste
                each tab into the paste box.</li>
            <li><b>Results:</b> green <code>Imported 38 fabrics (header row detected).</code> (or <code>(no headers &mdash; used positional
                A=Band B=Name C=Colour D=Supplier E=Code)</code>), <code>Skipped 3 duplicates.</code>, <code>Ignored 2 blank rows.</code>; red
                <b>Some rows had problems:</b> <code>Row 7: missing name</code>, <code>Row 3: missing band</code>,
                <code>Row 9: band code was just &lsquo;Band&rsquo; with nothing after it</code> (first 25, then
                <code>&hellip; and 4 more</code>). Good rows always go in; fix the listed rows and upload again &mdash; rows already in are
                skipped. Then <b>Continue product setup &rarr;</b> or <b>View imported fabrics</b>.</li>
            <li>Other messages: <code>Please choose a file to upload.</code>, <code>File too large (5 MB max).</code>,
                <code>Could not read the file: &hellip;</code></li>
          </ul>

          <p class="prose"><b>4) Add from fabric library</b> &mdash; <b>Pick a fabric manufacturer</b> (with a count each) &rarr;
             <b>Choose &rarr;</b>. Every fabric is listed <b>ticked</b> with <b>Fabric</b>, <b>Colour</b>, <b>Code</b>, <b>Band</b> (an editable
             box, pre-filled from the library&rsquo;s suggested band) and <b>Type</b>. Untick what you don&rsquo;t sell, set <b>Apply to system</b>
             if needed, and press <b>Add ticked fabrics</b>. The manufacturer becomes the <b>Supplier</b>:
             <code>Added 12 fabrics from &ldquo;Louvolite&rdquo;. Skipped 3 already on this product. 2 had no band &mdash; set their band so they
             price correctly.</code> If it says <code>The Fabric Library isn&rsquo;t set up yet.</code> or
             <code>No manufacturers in the library yet.</code>, use another route.</p>

          <p class="prose"><b>5) Copy from another product</b> &mdash; <b>1. Copy from</b> &rarr; <b>Source product</b>; <b>2. Bands in &hellip;</b>
             (all ticked, <b>Select all bands</b>) &rarr; <b>Copy selected into &hellip; &rarr;</b>. Existing fabrics (same band + name + colour)
             are skipped, so it is safe to run twice; system-scoped fabrics are matched to this product&rsquo;s same-named system:
             <code>Copied 86 fabrics from &ldquo;Vertical Blind&rdquo;. Skipped 4 already present (same band + name + colour).</code></p>

          <p class="prose"><b>6) Mending the list</b> &mdash; under <b>Fabrics (N)</b>:</p>
          <ul class="steps">
            <li>The <b>filter box</b> (<em>Filter (e.g. polaris cream)&hellip;</em>) narrows the list as you type; every word must appear. It
                matches name, colour, band, code, system and group &mdash; <b>not</b> supplier. <b>Clear</b> appears once you type.</li>
            <li>Tick rows (tick one, then <b>Shift</b>-click another to take everything between), then <b>Delete selected</b>
                (<em>Delete 3 selected rows? This cannot be undone.</em>), <b>Set band on selected</b> (<code>3 fabrics set to band B.</code>) or
                <b>Set supplier on selected</b>.</li>
            <li>Columns: tick, <b>Band</b> (navy pill), <b>System</b> (when the product has systems), the fabric name, <b>Colour</b>,
                <b>Supplier</b>, <b>Code</b>, <b>Group</b> (filled by the library pull) and <b>Edit</b> / <b>Delete</b> per row.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The band must match a price table.</b> A fabric whose band has no price table
             on that product shows no price in the quote builder. The <b>Bands:</b> buttons come from fabrics and price tables together, so a band
             you don&rsquo;t recognise there is the one to fix. When you&rsquo;re done: <b>Next: price tables &rarr;</b>.</div></div>

          <p><b>Many products in one workbook</b> (one sheet per product) is handled centrally: <b>Platform &rarr; Catalogue &rarr; Fabric
             Library</b> &rarr; <b>Bulk import fabrics across products &rarr;</b>. If you can&rsquo;t see it, ask whoever looks after the
             library &mdash; everything on this page does the same job one product at a time.</p>',
        'script'  => array_map(static fn ($k, $l) => [$l[0], $l[1], $l[2], $k], array_keys($S), $S),
];
