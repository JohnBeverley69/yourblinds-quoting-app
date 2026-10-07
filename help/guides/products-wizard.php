<?php
declare(strict_types=1);

/**
 * Guide: products-wizard — "Setting up a product (the setup wizard)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors /admin/products/wizard.php end to end — the stepper, step 1 (Name +
 * the material word + the four tick boxes), step 2 (Systems paste box), step 3
 * (band + names, the shortcut cards) and step 4 (a CHECKLIST of price tables,
 * not a grid), plus the "Continue an in-progress product" card. The Setup
 * wizard / + New product buttons come from admin/products/index.php. Every
 * label, button and message is copied from those files.
 *
 * v2: one SCENE per script line. Beat times are worked out from where the
 * matching words fall in the voice-over ($at), so the picture keeps step
 * with the narration; data-len is the line's length ÷ 13.6.
 */

// ── The voice-over, by step (built first so scene timings can read it) ──
$S = [
    1  => ['1',  'What the wizard does',
        'A product is one type of blind that you sell. A roller blind, a vertical, a roman, a metal venetian. The setup wizard builds one with you, in four steps. First its name, then its systems, then its fabrics, and last its price tables. Do them in that order, and the product will price at the end. The bubbles across the top show how far you have got.'],
    2  => ['2',  'Finding the wizard',
        'To start, go to Products. There are two buttons at the top. New product is the quick form, for when you know your way around. Setup wizard is the guided way, and it is the one to use while you are learning. Every step of the wizard has a Skip wizard link as well, so you can always step out. The wizard suggests an order. It never locks you in.'],
    3  => ['3',  'Step one: the name',
        'Step one asks, what kind of blind are we adding? Type the product name. This is the name your salesperson picks from a list when they build a quote, so use the words they would say out loud. Roller Blind is perfect. It is the only box on this page that you have to fill in.'],
    4  => ['4',  'What the material is called',
        'The next box asks what you call the material the product is made of. It already says Fabric, which is right for rollers and romans. For a metal venetian, type Colour instead. For a wood venetian, try Finish. Why does it matter? Because that word is used all through this product. On step three, the page will ask, what colours do you sell?'],
    5  => ['5',  'Four boxes to leave alone',
        'Below that are four tick boxes. For an ordinary blind, leave all four of them empty. They are for unusual products. No fabrics is for a headrail, a track or spares. Width only hides the drop when you quote. Per slat prices vertical fabric by the slat. And per square metre is for shutters. Not sure? Leave them alone. They can be changed later, on the product\'s edit page.'],
    6  => ['6',  'Create the product',
        'Now press Create product. The first bubble turns green, with a tick, and you move on to step two. From now on, the wizard remembers where you got to. Close the page half way through, and you can come back tomorrow and carry on. The finished bubbles stay clickable too, so you can always go back and fix something.'],
    7  => ['7',  'Step two: systems',
        'Step two is systems. A system is the way the blind works, or a version of it. Standard and Motorised are systems. So are sizes, like thirty five millimetre string. Each system has its own price tables, so you need at least one. Most products need just one or two. If yours only comes one way, call it Standard.'],
    8  => ['8',  'Paste the systems in',
        'Type one system per line, or paste a column straight from Excel. Press Add, and each line becomes a system, with a green tick in the list. Paste a name that is already there, and it is skipped, and the message says so. Continue stays greyed out until there is at least one system. Then it lights up, and takes you on.'],
    9  => ['9',  'Step three: bands',
        'Step three is your fabrics, and each one needs a band. A band is a price group. Every fabric in a band shares one price grid. So your plain range might be band A, and your blackouts band B. Letters from a supplier\'s price list are fine, and so are words like Standard. Once you have used a band, the box offers it back to you, so you pick it instead of typing it again.'],
    10 => ['10', 'Paste the names in',
        'Then paste the names into the big box. One per line, or with commas between them, whichever your list is in. Press Add, and they all go into that band together. The list shows each one with its band. If the product has two or more systems, a box called Available on appears as well. Leave it on all systems, unless a colour really only comes on one. To fill several systems in one go, type a system\'s name in square brackets on a line of its own, and the colours under it go on that system.'],
    11 => ['11', 'Ways to save typing',
        'There are quicker ways in, too. If another product already sells the same range, Copy from another product brings the whole lot across, bands and all. Import from Fabric Library pulls in a maker\'s range, and Import from spreadsheet reads an Excel file. Both bring you straight back here. And if this product has no fabric at all, the skip button takes you straight on to price tables.'],
    12 => ['12', 'Step four is a checklist',
        'Step four is price tables, and there is no price grid on this screen. It is a checklist. A product needs one table for each band, on each system. The quickest road is to import. If you have the supplier\'s price spreadsheet, click Import beside a system. It makes and fills every table for that system in one go. Then do the next system.'],
    13 => ['13', 'Or make the empty tables',
        'Typing the prices yourself? The yellow card lists every system and band that still needs a table. One click on Create all makes the empty grids. Each one says Empty, and empty means no price at all when you quote. Click Fill in to type or paste its prices. If you do not sell a combination, remove it, and it will not come back.'],
    14 => ['14', 'The bands must match',
        'Here is the one thing that catches everybody. The band on a fabric and the band on its price table must be the same word. Put your fabrics in band Plain, but make the table for band Special, and the quote cannot find a price. It says, no price table for that band. The easy way to avoid it is to pick bands from the suggestions, instead of typing them fresh each time.'],
    15 => ['15', 'All set, and coming back',
        'When every table is filled in, the tile turns green, and says the product is ready to quote. Options and the pricing settings live on the product\'s edit page, and the button at the bottom takes you there. You do not have to do it all in one go, either. Open the wizard again later, and a yellow card lists anything you have not finished, with a Resume button that takes you to the right step.'],
];

/** "Ns" — when the words $p are spoken in line $n (13.6 characters a second). */
$at = static function (int $n, string $p, float $plus = 0.0) use ($S): string {
    $i = mb_strpos($S[$n][2], $p);
    if ($i === false) { $GLOBALS['gd_at_miss'][] = "$n: $p"; $i = 0; }
    return round($i / 13.6 + $plus, 1) . 's';
};
/** data-len for line $n. */
$len = static fn (int $n): string => (string) max(8, (int) round(mb_strlen($S[$n][2]) / 13.6));

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

/**
 * The wizard's stepper. $done = finished steps (green ✓), $cur = the current one.
 * $tickAt[n] = a time when step n's tick pops on; $curAt[n] = when it becomes current;
 * $popAt[n] = when the whole bubble pops in (scene 1).
 */
$stp = static function (int $cur, array $done = [], array $tickAt = [], array $curAt = [], array $popAt = []): string {
    $lbl = [1 => 'Name', 2 => 'Systems', 3 => 'Fabrics', 4 => 'Price tables'];
    $h = '<div class="stp">';
    foreach ($lbl as $n => $l) {
        $cls = in_array($n, $done, true) ? 'done' : ($n === $cur ? 'cur' : '');
        $in  = '<span class="n">' . (in_array($n, $done, true) ? '&check;' : $n) . '</span>';
        if (isset($curAt[$n]))  $in .= '<span class="n cu a-pop" style="--d:' . $curAt[$n] . '">' . $n . '</span>';
        if (isset($tickAt[$n])) $in .= '<span class="n ok a-pop" style="--d:' . $tickAt[$n] . '">&check;</span>';
        $pop = isset($popAt[$n]) ? ' a-pop" style="--d:' . $popAt[$n] : '';
        $h .= '<div class="' . $cls . $pop . '"><span class="nb">' . $in . '</span><small>' . $l . '</small></div>';
    }
    return $h . '</div>';
};

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Setting up a product (the setup wizard)',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'The four-step wizard — Name, Systems, Fabrics, Price tables — what each step asks for and why, the shortcuts, and the band trap that stops a product pricing.',
        'lede'    => 'A <b>product</b> is one type of blind &mdash; <em>Roller Blind</em>, <em>Vertical</em>, <em>Roman</em>,
                      <em>Metal Venetian</em>. The setup wizard builds one with you in four steps &mdash; <b>Name</b>, <b>Systems</b>,
                      <b>Fabrics</b>, <b>Price tables</b> &mdash; and remembers where you got to, so you can stop half-way and
                      come back. Watch it through once, slowly, then use <b>Jump to a chapter</b> for any part you want again.
                      To get there: <b>Products</b> &rarr; <b>&#10024; Setup wizard</b>.',
        'open'    => '/admin/products/wizard.php',
        'css'     => '
          .gd .app{ grid-template-columns:132px minmax(0,1fr); }
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* the wizard stepper — numbered bubbles, done = green tick */
          .gd .stp{ display:flex; max-width:28rem; margin:0 0 .75rem; }
          .gd .stp > div{ flex:1; text-align:center; position:relative; }
          .gd .stp > div::after{ content:""; position:absolute; top:.8rem; left:calc(50% + 1rem); right:calc(-50% + 1rem); height:2px; background:var(--line); }
          .gd .stp > div:last-child::after{ display:none; }
          .gd .stp .nb{ display:inline-grid; width:1.6rem; height:1.6rem; }
          .gd .stp .nb > span{ grid-area:1/1; display:grid; place-items:center; border-radius:999px; font-size:.72rem; font-weight:800;
                                background:var(--line); color:var(--faint); }
          .gd .stp .cur .n, .gd .stp .n.cu{ background:#1f3b5b; color:#fff; box-shadow:0 0 0 3px color-mix(in srgb,#3b82f6 30%,transparent); }
          .gd .stp .done .n, .gd .stp .n.ok{ background:#10b981; color:#fff; box-shadow:none; }
          .gd .stp small{ display:block; margin-top:.2rem; font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; font-weight:700; color:var(--faint); }
          .gd .stp .cur small{ color:var(--ink); } .gd .stp .done small{ color:#059669; }

          /* the wizard card and its controls */
          .gd .wc{ border:1px solid var(--line); border-radius:12px; padding:.7rem .85rem; background:var(--surface); max-width:31rem; }
          .gd .wc h3{ margin:0 0 .2rem; font-size:.88rem; color:var(--ink); }
          .gd .wc .ld{ font-size:.68rem; color:var(--soft); margin:0 0 .5rem; line-height:1.45; }
          .gd .fl{ display:block; font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.45rem 0 .2rem; }
          .gd .inp{ display:grid; align-items:center; min-height:27px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:0 .5rem;
                    font-size:.78rem; background:var(--surface); color:var(--ink); position:relative; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp .ph, .gd .tx .ph{ color:var(--faint); }
          .gd .tx{ display:grid; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.3rem .5rem; font-family:ui-monospace,Menlo,Consolas,monospace;
                   font-size:.7rem; min-height:3.4rem; background:var(--surface); color:var(--ink); line-height:1.5; align-content:start; }
          .gd .tx > div{ grid-area:1/1; }
          .gd .hlp{ background:color-mix(in srgb,#3b82f6 9%,var(--surface)); border:1px solid color-mix(in srgb,#3b82f6 32%,transparent); border-radius:8px;
                    padding:.4rem .6rem; font-size:.66rem; color:var(--ink); line-height:1.45; margin:.45rem 0; }
          .gd .hlp b{ color:var(--accent-ink); }
          .gd .hlp.row{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; }
          .gd .cbr{ display:flex; gap:.45rem; align-items:flex-start; font-size:.7rem; color:var(--ink); margin:.35rem 0; line-height:1.35; }
          .gd .cbr small{ display:block; color:var(--faint); font-size:.6rem; }
          .gd .cbr .tick{ flex:0 0 auto; margin-top:.05rem; }
          .gd .wl{ border:1px solid var(--line); background:var(--panel); border-radius:8px; padding:.15rem .6rem; margin:.3rem 0 .4rem; }
          .gd .wl > div{ display:flex; align-items:center; gap:.4rem; font-size:.72rem; padding:.26rem 0; border-bottom:1px solid var(--line-2); color:var(--ink); }
          .gd .wl > div:last-child{ border-bottom:0; }
          .gd .wl .ck{ color:#10b981; font-weight:800; }
          .gd .wl .bd{ color:var(--faint); font-size:.64rem; }
          .gd .wl .rm{ margin-left:auto; color:#dc2626; text-decoration:underline; font-size:.62rem; }
          .gd .wl .emp{ color:var(--faint); font-style:italic; }
          .gd .alr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:6px; padding:.32rem .55rem; font-size:.68rem; font-weight:600; color:var(--ink); margin:0 0 .45rem; max-width:31rem; box-sizing:border-box; }
          .gd .alr.err{ background:var(--err-wash); border-left-color:var(--err); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                     border-radius:7px; padding:.26rem .6rem; font-size:.68rem; font-weight:600; white-space:nowrap; }
          .gd .dis{ opacity:.45; }
          .gd .sw{ display:inline-grid; } .gd .sw > *{ grid-area:1/1; }
          .gd .acts{ display:flex; justify-content:space-between; align-items:center; gap:.5rem; border-top:1px solid var(--line-2); margin-top:.55rem; padding-top:.5rem; font-size:.68rem; color:var(--faint); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.24rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin:.2rem 0 .7rem; }
          .gd .lnk{ color:var(--faint); text-decoration:underline; font-size:.68rem; border-radius:4px; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; max-width:31rem; }

          /* 2 — the Products page header */
          .gd .phd{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; border:1px solid var(--line); border-radius:10px;
                    padding:.55rem .7rem; max-width:31rem; background:var(--panel); }
          .gd .phd b{ font-size:.95rem; color:var(--ink); }
          .gd .phd .bb{ display:flex; gap:.4rem; flex-wrap:wrap; }
          .gd .wzh{ margin-top:1rem; max-width:31rem; }
          .gd .wzh b{ font-size:.95rem; color:var(--ink); display:block; }
          .gd .wzh span{ font-size:.68rem; color:var(--soft); }

          /* 3 — mini quote picker */
          .gd .qpick{ display:inline-flex; flex-direction:column; gap:.2rem; margin-top:.8rem; border:1px dashed var(--line); border-radius:9px; padding:.45rem .6rem; background:var(--panel); }
          .gd .qpick small{ font-size:.56rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
          .gd .selb{ display:inline-flex; align-items:center; justify-content:space-between; gap:.8rem; min-width:9rem; border:1px solid var(--border-strong,#c7ccd4);
                     border-radius:6px; padding:.2rem .45rem; font-size:.72rem; background:var(--surface); color:var(--ink); }
          .gd .selb::after{ content:"\25BE"; color:var(--faint); font-size:.6rem; }

          /* 7/9 — what a system / band is */
          .gd .sysmap{ display:grid; grid-template-columns:auto auto 1fr; gap:.35rem .5rem; align-items:center; max-width:24rem; margin:.4rem 0 .6rem; }
          .gd .arrow{ color:var(--faint); font-weight:800; }
          .gd .mgrid{ display:inline-grid; grid-template-columns:repeat(4,.7rem); gap:2px; padding:3px; border:1px solid var(--line); border-radius:4px; background:var(--panel); vertical-align:middle; }
          .gd .mgrid i{ display:block; height:.45rem; background:color-mix(in srgb,var(--accent) 30%,transparent); border-radius:1px; }
          .gd .band{ border:1px solid var(--line); border-radius:10px; padding:.45rem .55rem; background:var(--surface); }
          .gd .band h4{ margin:0 0 .3rem; font-size:.74rem; color:var(--ink); display:flex; align-items:center; justify-content:space-between; gap:.4rem; }
          .gd .band .f{ display:inline-block; font-size:.62rem; border:1px solid var(--line); border-radius:999px; padding:.08rem .45rem; margin:0 .2rem .2rem 0; color:var(--soft); background:var(--panel); }
          .gd .dl{ position:absolute; left:.3rem; top:100%; margin-top:2px; border:1px solid var(--line); border-radius:6px; background:var(--surface); box-shadow:var(--gd-shadow);
                   font-size:.7rem; z-index:3; min-width:5rem; }
          .gd .dl div{ padding:.18rem .5rem; } .gd .dl div:first-child{ background:var(--accent-wash); }
          .gd .frm{ display:grid; grid-template-columns:5rem 1fr; gap:.5rem; align-items:start; }
          .gd .frm.f3{ grid-template-columns:4rem 8.5rem 1fr; }

          /* 12–15 — step 4 tiles and cards */
          .gd .tile{ border-radius:12px; padding:.55rem .8rem; text-align:center; max-width:31rem; margin-bottom:.55rem; }
          .gd .tile .ic{ font-size:1.25rem; line-height:1; }
          .gd .tile h4{ margin:.2rem 0; font-size:.84rem; color:var(--ink); }
          .gd .tile p{ margin:0; font-size:.66rem; color:var(--soft); line-height:1.45; }
          .gd .am{ background:color-mix(in srgb,#f59e0b 13%,var(--surface)); border:1px solid color-mix(in srgb,#f59e0b 45%,transparent); }
          .gd .gr{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 40%,transparent); }
          .gd .bl{ background:color-mix(in srgb,#3b82f6 9%,var(--surface)); border:1px solid color-mix(in srgb,#3b82f6 32%,transparent); }
          .gd .card{ border-radius:12px; padding:.55rem .75rem; max-width:31rem; margin-bottom:.55rem; }
          .gd .card h4{ margin:0 0 .25rem; font-size:.78rem; color:var(--ink); }
          .gd .card p{ margin:0 0 .4rem; font-size:.64rem; color:var(--soft); line-height:1.45; }
          .gd .card .bb{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; }
          .gd .pe{ background:#fef3c7; color:#92400e; font-size:.54rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; padding:.1rem .4rem; border-radius:999px; }
          .gd .combo{ display:grid; grid-template-columns:repeat(2,auto); gap:.3rem; justify-content:start; margin:.2rem 0 .5rem; }
          .gd .mis{ border:1px solid var(--line); border-radius:12px; padding:.5rem .65rem; background:var(--surface); }
          .gd .mis h4{ margin:0 0 .3rem; font-size:.72rem; color:var(--ink); }
          .gd .mis .row{ font-size:.68rem; color:var(--soft); }
          .gd .vs{ display:grid; grid-template-columns:1fr auto 1fr; gap:.6rem; align-items:center; max-width:31rem; margin:.6rem 0 .8rem; }
          .gd .xmark{ font-size:1.6rem; font-weight:900; color:var(--err); text-align:center; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; }
            .gd .side{ display:none; }
            .gd .sc{ min-height:440px; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .frm.f3{ grid-template-columns:4rem 1fr; }
            .gd .frm.f3 > div:last-child{ grid-column:1 / -1; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / setup wizard</span></div>
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
                  <div class="sct">Set up a product</div>
                  <p class="scs">Guided 4-step setup.</p>
                  ' . $stp(1) . '
                  <div class="wc">
                    <h3>What kind of blind are we adding?</h3>
                    <p class="ld">A <b>product</b> is one type of blind &mdash; e.g. <em>Roller Blind</em>, <em>Vertical</em>, <em>Roman</em>,
                       <em>Metal Venetian</em>. Each product gets its own systems, fabrics, options and price tables.</p>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what the wizard does -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">One product = one type of blind</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:' . $at(1, 'A roller') . '">Roller Blind</span>
                    <span class="chip a-pop" style="--d:' . $at(1, 'a vertical') . '">Vertical</span>
                    <span class="chip a-pop" style="--d:' . $at(1, 'a roman') . '">Roman</span>
                    <span class="chip a-pop" style="--d:' . $at(1, 'a metal') . '">Metal Venetian</span>
                  </div>
                  <p class="scs a-fade" style="--d:' . $at(1, 'The setup wizard') . '">The setup wizard &mdash; four steps:</p>
                  <div class="a-ring" style="--d:' . $at(1, 'The bubbles') . ';border-radius:10px;max-width:28rem">
                  ' . $stp(0, [], [], [], [1 => $at(1, 'First its name'), 2 => $at(1, 'then its systems'), 3 => $at(1, 'then its fabrics'), 4 => $at(1, 'last its price')]) . '
                  </div>
                  <div class="chips" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:' . $at(1, 'Do them in that order') . ';border-color:var(--good);color:var(--good)">&#10003; In that order &rarr; it prices at the end</span>
                  </div>
                </div>

                <!-- 2 — finding it -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Products &rarr; Setup wizard</div>
                  <div class="phd a-rise" style="--d:' . $at(2, 'go to Products') . '">
                    <b>Products</b>
                    <span class="bb">
                      <span class="a-press" style="--d:' . $at(2, 'Every step', -.6) . ';display:inline-flex"><span class="btns a-ring" style="--d:' . $at(2, 'Setup wizard is') . '">&#10024; Setup wizard</span></span>
                      <span class="btnp a-ring" style="--d:' . $at(2, 'New product is') . '">+ New product</span>
                    </span>
                  </div>
                  <div class="chips" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:' . $at(2, 'New product is') . '">+ New product = the quick form</span>
                    <span class="chip a-pop" style="--d:' . $at(2, 'Setup wizard is') . ';border-color:var(--accent)">&#10024; Setup wizard = the guided way</span>
                  </div>
                  <div class="a-move" style="--fx:12%;--fy:14rem;--tx:50%;--ty:2.2rem;--d:' . $at(2, 'the one to use', -1.5) . ';--md:1.6s">' . $ptr . '</div>
                  <div class="wzh a-rise" style="--d:' . $at(2, 'Every step') . '">
                    <b>Set up a product</b>
                    <span>Guided 4-step setup. <span class="lnk a-ring" style="--d:' . $at(2, 'Skip wizard link') . '">Skip wizard &rarr; standard &ldquo;add product&rdquo; form</span></span>
                  </div>
                  <p class="scs a-fade" style="--d:' . $at(2, 'The wizard suggests') . ';margin-top:.7rem">It suggests an order &mdash; it never locks you in.</p>
                </div>

                <!-- 3 — the name -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  ' . $stp(1) . '
                  <div class="wc a-rise" style="--d:.3s">
                    <h3>What kind of blind are we adding?</h3>
                    <span class="fl">Product name <span class="req">*</span></span>
                    <span class="inp a-ring" style="--d:' . $at(3, 'Type the product name') . '"><span class="ph a-out" style="--d:' . $at(3, 'Roller Blind is') . '">e.g. Roller Blind</span>
                      <span><span class="a-type" style="--d:' . $at(3, 'Roller Blind is') . ';--ts:12;--tt:.9s">Roller Blind</span></span></span>
                  </div>
                  <div class="qpick a-rise" style="--d:' . $at(3, 'This is the name') . '">
                    <small>In the quote builder &middot; Product</small>
                    <span class="selb"><span class="sw"><span class="a-out" style="--d:' . $at(3, 'Roller Blind is') . '">&mdash; Select &mdash;</span><span class="a-fade" style="--d:' . $at(3, 'Roller Blind is', 1) . '">Roller Blind</span></span></span>
                  </div>
                  <span class="gd-tag a-pop" style="--d:' . $at(3, 'It is the only box') . ';left:13.5rem;top:12.4rem">The only box you must fill in</span>
                </div>

                <!-- 4 — the material word -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  ' . $stp(1) . '
                  <div class="wc">
                    <span class="fl" style="margin-top:0">Product name <span class="req">*</span></span>
                    <span class="inp">Metal Venetian</span>
                    <span class="fl a-fade" style="--d:.5s">What do you call the material this product is made of?</span>
                    <span class="inp a-ring" style="--d:.8s"><span class="a-out" style="--d:' . $at(4, 'type Colour') . '">Fabric</span>
                      <span><span class="a-type" style="--d:' . $at(4, 'type Colour', .4) . ';--ts:6;--tt:.6s">Colour</span></span></span>
                    <div class="hlp a-rise" style="--d:' . $at(4, 'right for rollers') . '">For rollers / romans use <b>Fabric</b>. For metal venetians, try <b>Colour</b>.
                      For wood venetians, <b>Finish</b>. (You can change this later.)</div>
                  </div>
                  <div class="wc a-drop" style="--d:' . $at(4, 'On step three') . ';margin-top:.6rem;background:var(--panel)">
                    <p class="scs" style="margin:0 0 .2rem">Step 3 will read:</p>
                    <h3 style="margin:0">What colours do you sell?</h3>
                  </div>
                </div>

                <!-- 5 — the four tick boxes -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">Four tick boxes &mdash; for unusual products</div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(5, 'leave all four') . ';border-color:var(--good);color:var(--good)">Ordinary blind? Leave all four empty</span></div>
                  <div class="wc">
                    <div class="cbr a-fly" style="--d:' . $at(5, 'No fabrics is') . '"><span class="tick"></span><span>This product has <b>no fabrics</b> (e.g. headrail only, track, spares).
                      <small>We&rsquo;ll skip the fabric step and price it on system &times; size alone.</small></span></div>
                    <div class="cbr a-fly" style="--d:' . $at(5, 'Width only') . '"><span class="tick"></span><span>Priced by <b>width only</b> (no drop) &mdash; e.g. a headrail or track.
                      <small>The Drop field is hidden at quote time and each price table is a single width &rarr; price list.</small></span></div>
                    <div class="cbr a-fly" style="--d:' . $at(5, 'Per slat') . '"><span class="tick"></span><span>Priced <b>per slat</b> (by drop) &mdash; e.g. vertical fabric only.
                      <small>Price table is a drop &rarr; price-per-slat list; the line price is that rate &times; number of slats.</small></span></div>
                    <div class="cbr a-fly" style="--d:' . $at(5, 'per square metre') . '"><span class="tick"></span><span>Priced <b>per square metre</b> &mdash; e.g. shutters.
                      <small>A single &pound;/m&sup2; rate &times; area, with an optional minimum area.</small></span></div>
                  </div>
                  <span class="gd-tag a-pop" style="--d:' . $at(5, 'They can be changed') . ';right:0;top:1.6rem">Can be changed later on the edit page</span>
                </div>

                <!-- 6 — create -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  ' . $stp(1, [], [1 => $at(6, 'The first bubble', .6)], [2 => $at(6, 'move on to step two')]) . '
                  <div class="wc">
                    <span class="fl" style="margin-top:0">Product name <span class="req">*</span></span>
                    <span class="inp">Roller Blind</span>
                    <span class="fl">What do you call the material this product is made of?</span>
                    <span class="inp">Fabric</span>
                    <div class="acts"><span></span><span class="btnp a-press a-ring" style="--d:' . $at(6, 'press Create') . '">Create product &rarr;</span></div>
                  </div>
                  <div class="a-move" style="--fx:30%;--fy:16rem;--tx:68%;--ty:10.9rem;--d:.3s;--md:1.4s">' . $ptr . '</div>
                  <div class="chips" style="margin-top:.7rem">
                    <span class="chip a-pop" style="--d:' . $at(6, 'remembers') . '">&#128190; It remembers where you got to</span>
                    <span class="chip a-pop" style="--d:' . $at(6, 'come back tomorrow') . '">Close the page &rarr; carry on tomorrow</span>
                    <span class="chip a-pop" style="--d:' . $at(6, 'finished bubbles') . '">&#10003; bubbles stay clickable</span>
                  </div>
                </div>

                <!-- 7 — what a system is -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  ' . $stp(2, [1]) . '
                  <div class="wc">
                    <h3>What systems does <em>Roller Blind</em> come in?</h3>
                    <p class="ld">A <b>system</b> is the operating mechanism &mdash; e.g. <em>Standard</em>, <em>Motorised</em>, <em>Battery</em>,
                       <em>Pelmet</em>. Each system can have its own price table. Most products need just one or two.</p>
                    <div class="sysmap">
                      <span class="chip a-pop" style="--d:' . $at(7, 'Standard and') . '">Standard</span><span class="arrow a-fade" style="--d:' . $at(7, 'Each system') . '">&rarr;</span>
                        <span class="a-fade" style="--d:' . $at(7, 'Each system') . '"><span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span> <small style="font-size:.6rem;color:var(--faint)">its own price tables</small></span>
                      <span class="chip a-pop" style="--d:' . $at(7, 'Motorised are') . '">Motorised</span><span class="arrow a-fade" style="--d:' . $at(7, 'Each system', .5) . '">&rarr;</span>
                        <span class="a-fade" style="--d:' . $at(7, 'Each system', .5) . '"><span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></span>
                      <span class="chip a-pop" style="--d:' . $at(7, 'thirty five') . '">35mm String</span><span class="arrow a-fade" style="--d:' . $at(7, 'Each system', 1) . '">&rarr;</span>
                        <span class="a-fade" style="--d:' . $at(7, 'Each system', 1) . '"><span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></span>
                    </div>
                  </div>
                  <span class="gd-tag a-pop" style="--d:' . $at(7, 'If yours only') . ';left:1rem;top:17rem">Only one kind? Call it <b>Standard</b></span>
                </div>

                <!-- 8 — paste the systems -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  ' . $stp(2, [1]) . '
                  <div class="wc">
                    <div class="alr a-pop" style="--d:' . $at(8, 'Press Add', 1) . '"><span class="sw"><span class="a-mid" style="--d:' . $at(8, 'Press Add', 1) . ';--d2:' . $at(8, 'Paste a name') . '">Added 2 systems.</span><span class="a-fade" style="--d:' . $at(8, 'Paste a name', .3) . '">Added 0 systems. Skipped 1 (already on this product).</span></span></div>
                    <div class="wl"><div class="sw" style="display:grid">
                      <span class="emp a-out" style="--d:' . $at(8, 'Press Add', .8) . '">No systems yet &mdash; add at least one below.</span>
                      <span class="a-fade" style="--d:' . $at(8, 'Press Add', .8) . ';display:flex;gap:.4rem;align-items:center"><span class="ck">&check;</span><b>Standard</b><span class="rm">Remove</span></span></div>
                      <div class="a-fade" style="--d:' . $at(8, 'Press Add', 1.1) . '"><span class="ck">&check;</span> <b>Motorised</b><span class="rm">Remove</span></div>
                    </div>
                    <span class="fl">One system per line (paste from Excel or type)</span>
                    <div class="tx a-ring" style="--d:.6s"><div>
                      <div><span class="a-type" style="--d:' . $at(8, 'Type one') . ';--ts:8;--tt:.7s">Standard</span></div>
                      <div><span class="a-type" style="--d:' . $at(8, 'paste a column') . ';--ts:9;--tt:.7s">Motorised</span></div></div></div>
                    <div style="display:flex;gap:.5rem;align-items:center;margin-top:.4rem"><span class="btns a-press" style="--d:' . $at(8, 'Press Add') . '">+ Add</span>
                      <span style="font-size:.62rem;color:var(--faint)">One line = one system. Single name = single add.</span></div>
                    <div class="acts"><span>&larr; Back</span>
                      <span class="sw"><span class="btnp dis a-out" style="--d:' . $at(8, 'Then it lights') . '">Continue &rarr;</span><span class="btnp a-fade" style="--d:' . $at(8, 'Then it lights') . '"><span class="a-ring" style="--d:' . $at(8, 'Then it lights', .5) . ';border-radius:7px">Continue &rarr;</span></span></span></div>
                  </div>
                </div>

                <!-- 9 — bands -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  ' . $stp(3, [1, 2]) . '
                  <div class="sct a-fade" style="--d:.3s">What fabrics do you sell?</div>
                  <div class="two" style="margin:.4rem 0 .7rem">
                    <div class="band a-rise" style="--d:' . $at(9, 'plain range') . '"><h4>Band A <span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></h4>
                      <span class="f">Linen Oyster</span><span class="f">Linen Slate</span><span class="f">Cream</span><span class="f">Stone</span></div>
                    <div class="band a-rise" style="--d:' . $at(9, 'blackouts') . '"><h4>Band B <span class="mgrid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></h4>
                      <span class="f">Blackout Ivory</span><span class="f">Blackout Grey</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:' . $at(9, 'Every fabric in a band') . '">One band &rarr; one price grid</span></div>
                  <div style="max-width:9rem;position:relative">
                    <span class="fl">Band <span class="req">*</span></span>
                    <span class="inp a-ring" style="--d:' . $at(9, 'offers it back') . '"><span class="ph a-out" style="--d:' . $at(9, 'offers it back', .5) . '">A</span><span><span class="a-type" style="--d:' . $at(9, 'pick it instead', -.5) . ';--ts:1;--tt:.2s">A</span></span>
                      <span class="dl a-mid" style="--d:' . $at(9, 'offers it back', .5) . ';--d2:' . $at(9, 'pick it instead', -.5) . '"><div>A</div><div>B</div></span></span>
                  </div>
                </div>

                <!-- 10 — paste the names -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  ' . $stp(3, [1, 2]) . '
                  <div class="wc">
                    <div class="alr a-pop" style="--d:' . $at(10, 'Press Add', 1) . '">Added 4 to Band A (all systems).</div>
                    <div class="wl">
                      <div class="a-fade" style="--d:' . $at(10, 'The list shows') . '"><span class="ck">&check;</span><b>Cream</b><span class="bd">&mdash; Band A &middot; <em>all systems</em></span></div>
                      <div class="a-fade" style="--d:' . $at(10, 'The list shows', .3) . '"><span class="ck">&check;</span><b>Linen Oyster</b><span class="bd">&mdash; Band A &middot; <em>all systems</em></span></div>
                      <div class="a-fade" style="--d:' . $at(10, 'The list shows', .6) . '"><span class="ck">&check;</span><b>Linen Slate</b><span class="bd">&mdash; Band A &middot; <em>all systems</em></span></div>
                    </div>
                    <div class="frm f3">
                      <div><span class="fl">Band <span class="req">*</span></span><span class="inp">A</span></div>
                      <div class="a-pop" style="--d:' . $at(10, 'Available on') . '"><span class="fl">Available on</span>
                        <span class="selb a-ring" style="--d:' . $at(10, 'Leave it on all') . ';min-width:0;width:100%;box-sizing:border-box;font-size:.62rem;padding:.3rem .4rem">All systems (universal)</span></div>
                      <div><span class="fl">Fabrics (one per line or comma-separated)</span>
                        <div class="tx a-ring" style="--d:' . $at(10, 'paste the names') . '"><div>
                          <div><span class="a-type" style="--d:' . $at(10, 'One per line') . ';--ts:12;--tt:.8s">Linen Oyster</span></div>
                          <div><span class="a-type" style="--d:' . $at(10, 'or with commas') . ';--ts:19;--tt:1s">Linen Slate, Cream,</span></div>
                          <div><span class="a-type" style="--d:' . $at(10, 'whichever') . ';--ts:5;--tt:.5s">Stone</span></div></div></div>
                        <div style="margin-top:.35rem"><span class="btns a-press" style="--d:' . $at(10, 'Press Add') . '">+ Add</span></div></div>
                    </div>
                  </div>
                </div>

                <!-- 11 — shortcuts -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  ' . $stp(3, [1, 2]) . '
                  <div class="hlp row a-rise" style="--d:' . $at(11, 'If another product') . ';max-width:31rem"><span><b>Same fabrics as another product?</b> Copy the whole range across (bands and all).</span>
                    <span class="btns a-ring" style="--d:' . $at(11, 'Copy from another product') . '">Copy from another product &rarr;</span></div>
                  <div class="hlp a-rise" style="--d:' . $at(11, 'Import from Fabric') . ';max-width:31rem"><b>Already have these fabrics?</b> Bring them in instead of typing:
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin:.35rem 0 .25rem">
                      <span class="btns a-ring" style="--d:' . $at(11, 'Import from Fabric') . '">&#128218; Import from Fabric Library</span>
                      <span class="btns a-ring" style="--d:' . $at(11, 'Import from spreadsheet') . '">&#128196; Import from spreadsheet</span></div>
                    <span class="a-fade" style="--d:' . $at(11, 'Both bring you') . ';color:var(--soft)">You&rsquo;ll come straight back here to carry on.</span></div>
                  <div class="hlp row a-rise" style="--d:' . $at(11, 'no fabric at all') . ';max-width:31rem"><span><b>No fabrics for this product?</b> For a headrail-only line, track, or spares.</span>
                    <span class="btns a-ring" style="--d:' . $at(11, 'the skip button') . '">No fabrics &mdash; skip &rarr;</span></div>
                </div>

                <!-- 12 — step 4 is a checklist -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  ' . $stp(4, [1, 2, 3]) . '
                  <div class="tile am a-rise" style="--d:' . $at(12, 'It is a checklist') . '"><div class="ic">&#128203;</div><h4>One thing left &mdash; price tables</h4>
                    <p>Create the price tables below &mdash; one per band &times; system &mdash; then fill in the prices.</p></div>
                  <div class="combo">
                    <span class="chip a-pop" style="--d:' . $at(12, 'one table for each') . '">Standard + Band A</span><span class="chip a-pop" style="--d:' . $at(12, 'one table for each', .4) . '">Standard + Band B</span>
                    <span class="chip a-pop" style="--d:' . $at(12, 'on each system') . '">Motorised + Band A</span><span class="chip a-pop" style="--d:' . $at(12, 'on each system', .4) . '">Motorised + Band B</span>
                  </div>
                  <div class="card bl a-rise" style="--d:' . $at(12, 'The quickest road') . '"><h4>Got a price spreadsheet? Import it</h4>
                    <p>Upload your width &times; drop price grid for a system (all its bands in one file) and we&rsquo;ll create and fill its tables in one go. Do each system in turn.</p>
                    <div class="bb"><span class="btnp a-press a-ring" style="--d:' . $at(12, 'click Import') . '">Import Standard &rarr;</span><span class="btnp a-ring" style="--d:' . $at(12, 'Then do the next') . '">Import Motorised &rarr;</span></div></div>
                </div>

                <!-- 13 — or create the empty tables -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  ' . $stp(4, [1, 2, 3]) . '
                  <div class="alr a-pop" style="--d:' . $at(13, 'makes the empty') . '">4 price tables created.</div>
                  <div class="sw" style="display:grid;align-items:start">
                  <div class="card am a-mid" style="--d:' . $at(13, 'The yellow card') . ';--d2:' . $at(13, 'makes the empty') . '"><h4>4 price tables need setting up</h4>
                    <p>Each fabric band on each system needs its own price grid. Clicking <em>Create</em> below makes an empty price table for each.</p>
                    <div class="combo"><span class="chip">Standard + Band A</span><span class="chip">Standard + Band B</span><span class="chip">Motorised + Band A</span><span class="chip">Motorised + Band B</span></div>
                    <span class="btnp a-press a-ring" style="--d:' . $at(13, 'One click') . '">Create all 4 empty price tables</span></div>
                  <div class="wc a-rise" style="--d:' . $at(13, 'makes the empty', .3) . '">
                    <h3>Price tables</h3>
                    <div class="wl">
                      <div><span class="sw"><span class="pe a-out" style="--d:' . $at(13, 'type or paste', 1) . '">Empty</span><span class="ck a-pop" style="--d:' . $at(13, 'type or paste', 1) . '">&check;</span></span><b>Standard</b><span class="bd">&mdash; Band A <span class="a-fade" style="--d:' . $at(13, 'type or paste', 1) . '">&middot; 48 cells</span></span>
                        <span class="sw" style="margin-left:auto"><span class="a-out" style="--d:' . $at(13, 'type or paste', 1) . '"><span class="btnp a-ring" style="--d:' . $at(13, 'Click Fill in') . ';font-size:.6rem">Fill in</span></span><span class="btns a-fade" style="--d:' . $at(13, 'type or paste', 1) . ';font-size:.6rem">Edit</span></span><span class="rm" style="margin-left:.3rem">Remove</span></div>
                      <div><span class="pe a-ring" style="--d:' . $at(13, 'Each one says') . '">Empty</span><b>Standard</b><span class="bd">&mdash; Band B</span><span class="btnp" style="margin-left:auto;font-size:.6rem">Fill in</span><span class="rm" style="margin-left:.3rem">Remove</span></div>
                      <div><span class="pe">Empty</span><b>Motorised</b><span class="bd">&mdash; Band A</span><span class="btnp" style="margin-left:auto;font-size:.6rem">Fill in</span><span class="rm" style="margin-left:.3rem">Remove</span></div>
                      <div><span class="pe">Empty</span><b>Motorised</b><span class="bd">&mdash; Band B</span><span class="btnp" style="margin-left:auto;font-size:.6rem">Fill in</span><span class="rm a-ring" style="--d:' . $at(13, 'remove it') . ';margin-left:.3rem">Remove</span></div>
                    </div>
                  </div>
                  </div>
                  <div class="chips" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:' . $at(13, 'empty means') . ';border-color:#f59e0b">Empty = no price when you quote</span>
                    <span class="chip a-pop" style="--d:' . $at(13, 'it will not come back') . '">Removed? It stays removed</span>
                  </div>
                </div>

                <!-- 14 — the band trap -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="sct a-fade" style="--d:.2s">The band on the fabric = the band on the table</div>
                  <div class="vs">
                    <div class="mis a-rise" style="--d:' . $at(14, 'Put your fabrics') . '"><h4>Fabric</h4><div class="row"><b style="color:var(--ink)">Linen Oyster</b> &mdash; Band <b style="color:var(--ink)">Plain</b></div></div>
                    <div class="xmark a-stamp" style="--d:' . $at(14, 'cannot find') . '">&ne;</div>
                    <div class="mis a-rise" style="--d:' . $at(14, 'make the table') . '"><h4>Price table</h4><div class="row"><b style="color:var(--ink)">Standard</b> &mdash; Band <b style="color:var(--ink)">Special</b></div></div>
                  </div>
                  <div class="alr err a-pop" style="--d:' . $at(14, 'It says') . ';max-width:31rem">No price table for Roller Blind band Plain on system &lsquo;Standard&rsquo;.</div>
                  <span class="fl a-fade" style="--d:' . $at(14, 'The easy way') . ';margin-top:.8rem">Band <span class="req">*</span></span>
                  <div style="display:flex;gap:.7rem;align-items:center;flex-wrap:wrap">
                    <span class="inp a-fade" style="--d:' . $at(14, 'The easy way') . ';width:8rem"><span><span class="a-type" style="--d:' . $at(14, 'instead of typing', .5) . ';--ts:5;--tt:.4s">Plain</span></span>
                      <span class="dl a-mid" style="--d:' . $at(14, 'pick bands') . ';--d2:' . $at(14, 'instead of typing', .5) . '"><div>Plain</div><div>Blackout</div></span></span>
                    <span class="chip a-pop" style="--d:' . $at(14, 'pick bands') . ';border-color:var(--good);color:var(--good)">&#10003; Pick from the suggestions &mdash; same word every time</span>
                  </div>
                </div>

                <!-- 15 — all set, and coming back -->
                <div class="sc" data-scene="15" data-len="' . $len(15) . '">
                  <div class="sw" style="display:grid;align-items:start">
                    <div class="a-mid" style="--d:.1s;--d2:' . $at(15, 'You do not have to') . '">
                      ' . $stp(4, [1, 2, 3]) . '
                      <div class="tile gr a-rise" style="--d:.6s"><div class="ic">&#127881;</div><h4>All set &mdash; Roller Blind is ready to quote</h4>
                        <p>Product, 2 systems, 6 fabrics, and all 4 price tables filled in.</p></div>
                      <div class="acts" style="max-width:31rem"><span>&larr; Back to fabrics</span><span class="btnp a-ring" style="--d:' . $at(15, 'the button at the bottom') . '">Open product edit page &rarr;</span></div>
                    </div>
                    <div class="a-fade" style="--d:' . $at(15, 'Open the wizard again') . '">
                      <div class="sct">Set up a product</div>
                      <p class="scs">Next time you open the wizard from Products:</p>
                      <div class="card am" style="text-align:left"><h4>Continue an in-progress product</h4>
                        <p>These aren&rsquo;t finished yet &mdash; resume and the wizard picks up at the next thing each one needs.</p>
                        <div class="bb" style="justify-content:space-between"><span style="font-size:.68rem;color:var(--ink)"><b>Vertical Blind</b> <span style="color:#b45309">&mdash; needs fabric + price table</span></span>
                          <span class="btns a-ring" style="--d:' . $at(15, 'Resume button') . '">Resume &rarr;</span></div></div>
                      <div class="wc" style="opacity:.55"><h3>What kind of blind are we adding?</h3><span class="fl">Product name <span class="req">*</span></span><span class="inp"><span class="ph">e.g. Roller Blind</span></span></div>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where it lives.</b> <b>Products</b> &rarr; <b>&#10024; Setup wizard</b> (the secondary button at the top, beside
             <b>+ New product</b>). On a brand-new account with no products yet, the Products page offers a big
             <b>&#10024; Start the setup wizard</b> instead, with <em>Skip the wizard &mdash; just add a product</em> underneath. Every step
             carries a <b>Skip wizard</b> link &mdash; <em>Skip wizard &rarr; standard &ldquo;add product&rdquo; form</em> before the product
             exists, <em>Skip wizard &rarr; jump to the edit page</em> after. The wizard suggests an order; it never blocks you.</p>

          <p><b>The stepper</b> across the top &mdash; <b>1 Name &middot; 2 Systems &middot; 3 Fabrics &middot; 4 Price tables</b> &mdash; is a
             progress bar and a set of links. A finished step turns <b>green and its number becomes a &check;</b>, and any step whose
             earlier steps are done is clickable, forwards or back. (The step you are on always shows its number.) The <b>Price tables</b>
             bubble only earns its &check; when <b>every</b> price table has prices in it <em>and</em> no system + band combination is
             missing &mdash; an honest finish line, not &ldquo;you got to the end&rdquo;. Every action reloads the page, so <b>refreshing never re-submits</b>
             anything.</p>

          <p class="prose"><b>Step 1 &mdash; Name</b> (<em>&ldquo;What kind of blind are we adding?&rdquo;</em>). Only the first box is compulsory.</p>
          <ul class="steps">
            <li><b>Product name *</b> &mdash; up to 150 characters, placeholder <code>e.g. Roller Blind</code>. It is what your salesperson
                picks in the quote builder, so use the name they say out loud.</li>
            <li><b>What do you call the material this product is made of?</b> &mdash; up to 40 characters, <b>already filled in with
                &ldquo;Fabric&rdquo;</b>. The blue note says: <em>For rollers / romans use Fabric. For metal venetians, try Colour. For wood
                venetians, Finish. (You can change this later.)</em> The word you choose is used across this product &mdash; type
                <em>Colour</em> and step 3 asks <em>&ldquo;What colours do you sell?&rdquo;</em></li>
            <li><b>This product has no fabrics</b> (e.g. headrail only, track, spares) &mdash; skips the fabric step; it is priced on
                <b>system &times; size</b> alone.</li>
            <li><b>Priced by width only</b> (no drop) &mdash; the <b>Drop field is hidden at quote time</b> and each price table is a single
                width &rarr; price list. For a headrail or track.</li>
            <li><b>Priced per slat</b> (by drop) &mdash; the price table is a drop &rarr; price-per-slat list; the line price is that rate
                &times; the number of slats. For vertical fabric only.</li>
            <li><b>Priced per square metre</b> &mdash; a single &pound;/m&sup2; rate &times; area, with an optional minimum area set on the
                product edit page. For shutters. The last two say <em>&ldquo;Leave the boxes above unticked.&rdquo;</em> For an ordinary blind,
                leave <b>all four</b> empty. All four can be changed later on the product&rsquo;s edit page.</li>
            <li>Press <b>Create product &rarr;</b>. If your material word contains &ldquo;colour&rdquo; (e.g. <em>Slat Colour</em>) the
                separate <b>Colour</b> sub-field is switched off for you, so you don&rsquo;t get two colour boxes. Every product made here starts
                on the <b>supplier price list</b> pricing model.</li>
          </ul>
          <div class="oops"><b>If it won&rsquo;t save:</b> <code>Product name is required.</code> &middot;
             <code>Product name too long (max 150).</code> &middot; <code>Option label too long (max 40).</code></div>

          <p class="prose"><b>Step 2 &mdash; Systems</b> (<em>&ldquo;What systems does &hellip; come in?&rdquo;</em>). A <b>system</b> is the
             operating mechanism or physical variant &mdash; <em>Standard</em>, <em>Motorised</em>, <em>Battery</em>, <em>Pelmet</em>, or a size
             like <em>35mm String</em> / <em>50mm Tape</em>. Each system gets its own price table. Most products need one or two.</p>
          <ul class="steps">
            <li>Under <b>Add systems</b>, the box is labelled <em>One system per line (paste from Excel or type)</em>. One line = one system;
                a single name is a single add. Press <b>+ Add</b>.</li>
            <li>Messages: <code>Added 2 systems.</code> &mdash; and for a name already there,
                <code>Skipped 1 (already on this product).</code> Before you add anything the list reads
                <code>No systems yet &mdash; add at least one below.</code> An empty box gives
                <code>No system names &mdash; paste at least one name into the box.</code></li>
            <li>Each system has a red <b>Remove</b>, which asks first: <em>Remove the &ldquo;Standard&rdquo; system? Any price tables it has go
                too.</em></li>
            <li><b>Continue &rarr;</b> stays greyed out until there is at least one system, because price tables hang off systems.
                (<code>Add at least one system before continuing.</code>) <b>&larr; Back</b> returns to step 1.</li>
          </ul>

          <p class="prose"><b>Step 3 &mdash; Fabrics</b> (or Colours, or Finishes &mdash; the heading uses your word:
             <em>&ldquo;What fabrics do you sell?&rdquo;</em>).</p>
          <ul class="steps">
            <li><b>Band * first.</b> A band groups fabrics that share the same price table &mdash; your cheap range and premium range are
                usually different bands. <code>A</code>/<code>B</code>/<code>C</code> from a supplier list is fine, so are words like
                <code>Standard</code>/<code>Special</code>. Typing <em>Band A</em> stores just <em>A</em>. The box suggests bands already used
                on this product (from its fabrics <em>and</em> its price tables), so you pick rather than retype.</li>
            <li><b>Available on</b> appears only when the product has <b>two or more systems</b>: <em>All systems (universal)</em> or
                <em>&lt;System&gt; only</em>. Leave it universal unless a colour really comes on one system only.
                With two or more systems you can also fill several in one paste: put a <code>[System Name]</code> header on its own line
                and the colours below it go on that system, until the next header &mdash; the message then reads
                <code>Added 8 to Band A across 2 systems.</code> A header that doesn&rsquo;t match a system is listed under
                <code>Unknown system names</code> and its colours are skipped.</li>
            <li><b>The names box</b> &mdash; <em>Fabrics (one per line or comma-separated &mdash; paste from Excel or type)</em>. Press
                <b>+ Add</b>: <code>Added 4 to Band A (all systems).</code> (or <code>(one system only).</code>), with
                <code>Skipped 2 (likely duplicates).</code> when some were already there.</li>
            <li><b>The list</b> shows a count (<em>6 fabrics added</em>), each fabric with its band, and &mdash; once there are two or more
                systems &mdash; a purple <em>&lt;System&gt; only</em> or a faint <em>all systems</em>. Each row has <b>Remove</b>; a red
                <b>Delete all</b> asks <em>Delete ALL 6 fabrics on this product? Cannot be undone.</em></li>
            <li><b>Shortcuts</b>, each its own blue card: <b>Copy from another product &rarr;</b> (the whole range, bands and all);
                <b>Price tables first &rarr;</b> (import the grid on step 4, then come back &mdash; the band box will suggest the bands you
                imported); <b>No fabrics &mdash; skip &rarr;</b> for a headrail, track or spares, which asks
                <em>Mark this as a no-fabric product and skip straight to price tables?</em>; and, under <em>Already have these fabrics?</em>,
                <b>&#128218; Import from Fabric Library</b> and <b>&#128196; Import from spreadsheet</b> &mdash; both bring you straight back.</li>
            <li><b>Continue &rarr;</b> is greyed out until there is at least one fabric.</li>
          </ul>
          <div class="oops"><b>If it won&rsquo;t add:</b> <code>Band code is required.</code> &middot;
             <code>Band code too long (max 60).</code> &middot; <code>No names to add &mdash; paste at least one name into the box.</code>
             &middot; <code>Add at least one fabric before continuing.</code></div>

          <p class="prose"><b>Step 4 &mdash; Price tables. There is no price grid on this screen</b> &mdash; it is a checklist of every
             system-and-band combination the product needs.</p>
          <ul class="steps">
            <li><b>The tile at the top</b> says where you stand &mdash; amber <b>&ldquo;One thing left &mdash; price tables&rdquo;</b> with the
                reason (<code>Create the price tables below &mdash; one per band &times; system &mdash; then fill in the prices.</code>,
                <code>2 of 4 filled in. Click Fill in on each&hellip;</code>, <code>No bands yet &mdash; that&rsquo;s fine&hellip;</code>, or
                <code>Your fabrics don&rsquo;t have band codes yet&hellip;</code>). Green <b>&#127881; All set &mdash; &hellip; is ready to quote</b>
                when it is done.</li>
            <li><b>The quick road: import.</b> The blue card <b>Got a price spreadsheet? Import it</b> has an <b>Import &lt;System&gt; &rarr;</b>
                button for each system that still needs prices. The importer creates and fills that system&rsquo;s tables in one go. (A
                width-only product shows <b>Import width prices &rarr;</b>; a per-slat one <b>Import rates &rarr;</b>.) The card disappears
                once nothing needs importing.</li>
            <li><b>Typing them yourself?</b> The amber card <b>4 price tables need setting up</b> lists the missing
                <em>&lt;System&gt; + Band A</em> rows; <b>Create all 4 empty price tables</b> makes them
                (<code>4 price tables created.</code>) &mdash; <em>One click &mdash; the empty grids appear instantly. Leave any out that
                don&rsquo;t apply to what you sell.</em> With one missing it reads <b>Create the empty price table</b>.</li>
            <li><b>The Price tables list</b>: a green &check; with <em>&middot; 48 cells</em>, or an amber <b>Empty</b> pill. <b>Empty tables
                won&rsquo;t generate a price at quote time.</b> <b>Fill in</b> opens the grid to type or paste prices; <b>Edit</b> reopens a
                filled one; <b>Remove</b> warns <em>Its 48 price cells will be wiped too.</em></li>
            <li><b>Deletions stick.</b> Empty tables are only ever made when you click &mdash; one you remove will not reappear by itself.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The band trap.</b> The band on the <b>fabric</b> and the band on the
             <b>price table</b> must be the same word. Fabrics on &ldquo;Plain&rdquo; with a table for &ldquo;Special&rdquo; give, in the quote
             builder: <code>No price table for Roller Blind band Plain on system &lsquo;Standard&rsquo;.</code> Pick bands from the
             suggestion list, or take <b>Price tables first &rarr;</b> so the bands exist before you name any fabric.</div></div>

          <p><b>Finishing, and coming back.</b> The footer has <b>&larr; Back to fabrics</b> and <b>Open product edit page &rarr;</b> (it reads
             <b>Finish later &mdash; open product &rarr;</b> while anything is outstanding). Close the tab whenever you like: next time you open
             the wizard from the Products page, an amber <b>&ldquo;Continue an in-progress product&rdquo;</b> card above the name box lists
             what is unfinished &mdash; <em>Vertical Blind &mdash; needs fabric + price table</em> &mdash; with <b>Resume &rarr;</b>, which drops
             you at the step that product needs next.</p>

          <p><b>What the wizard leaves out.</b> Options and the pricing settings (<b>Pricing source</b>, <b>Pricing per system</b>) live on the
             <b>product edit page</b>. Carry on with <em>Adding options</em>, <em>Building price tables</em>,
             <em>Adding fabrics &mdash; paste, Excel or library</em> and <em>Combining products into one</em>.</p>',
        'script'  => array_map(static fn ($n, $l) => [$l[0], $l[1], $l[2], $n], array_keys($S), $S),
];
