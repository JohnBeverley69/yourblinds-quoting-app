<?php
declare(strict_types=1);

/**
 * Guide: settings-measurements — "Measurement units" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the "Measurements" section on the Quoting tab of
 * /admin/settings.php (hint, "Default measurement unit" select with
 * Millimetres (mm) / Centimetres (cm) / Metres (m) / Inches (in), "Save unit",
 * "Default measurement unit saved."), the quote builder's "Measurement" select
 * (mm / cm / m / inch → quote-builder/set_unit.php) and "Width (…)" /
 * "Drop (…)" labels, InstaPrice's "Measurement unit" select and its "Using … mm"
 * read-back, the parser in _partials/price_table_parser.php (a typed unit wins;
 * a bare number is read in the quote's unit; words → "Could not read width"),
 * _partials/units.php (always stored in mm; display precision per unit), the
 * customer PDF / online quote (always "W × D mm") and the width-only / per-slat
 * price lists (admin/products/price-table.php, follow the company unit).
 *
 * v2: one SCENE per script line (data-scene = the line's step); data-len is
 * worked out from the line itself (characters ÷ 13.6).
 */

$script = [
    ['1', 'Sizes are stored in millimetres', 'Behind the scenes, every size in the system is stored in millimetres. This one setting only decides what your team types in, and reads on the screen. It never changes the sizes underneath. That is why it is safe to change at any time, even with hundreds of quotes already on the system.', 1],
    ['2', 'Finding it',                      'Go to Setup, then Settings, and click the Quoting tab. Look for the section called Measurements. It sits between Default margins and Quote defaults. There is just one dropdown in it, Default measurement unit, with its own Save unit button underneath.', 2],
    ['3', 'Pick your unit',                  'Open the dropdown, and there are four choices: millimetres, centimetres, metres, or inches. Pick the one your fitters actually call out on site. Most people stay on millimetres. Then press Save unit. A green bar says, Default measurement unit saved.', 3],
    ['4', 'What changes on a quote',         'Now open a quote. The Width and Drop boxes carry your unit in their labels, so in inches they show the inch mark. Each unit is shown to its own sensible precision. Fifteen hundred millimetres reads as one hundred and fifty centimetres, one point five metres, or fifty nine point oh six inches.', 4],
    ['5', 'One job in a different unit',     'If one customer works in a different unit, there is no need to touch Settings. Beside the size boxes on the quote is a dropdown called Measurement. Change it, and the page reloads with every blind on that quote shown in the new unit. It sticks to that quote from then on.', 5],
    ['6', 'One blind in a different unit',   'For one odd blind, you do not even need the dropdown. Type the unit straight into the box, like sixty in, or one point five m. A typed unit always wins, just for that box. InstaPrice even reads it back to you in millimetres underneath, so you can spot a slip.', 6],
    ['7', 'A number with no unit',           'Here is the one to watch. A number with no unit is read in the quote\'s unit. On an inches quote, fifteen hundred on its own means fifteen hundred inches. And words are not allowed: type two metres in letters, and you get, could not read width. Type two m instead.', 7],
    ['8', 'What the customer sees',          'Your customers never see this setting. The quote PDF, and the quote page they open online, always show sizes in millimetres. So does the factory paperwork. The workshop cuts in millimetres, so its paperwork stays in millimetres, whatever you choose here.', 8],
    ['9', 'Price lists follow it too',       'One last place. Price lists that are priced by width only, or per slat, follow your company unit as well. Their column heading and the example in each empty box change to match. Nothing you have already saved moves. It is only shown a different way.', 9],
];
$L = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr  = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$type = static fn (string $txt, float $d, int $steps = 6, float $tt = .5): string =>
    '<span class="a-type" style="--d:' . $d . 's;--ts:' . $steps . ';--tt:' . $tt . 's">' . $txt . '</span>';
$hint = '<p class="hintg">The unit your team enters and sees blind sizes in. Sizes are always stored the same way under the hood, so you can
    change this any time. On a quote you can still override the unit for one job, and you can always type a unit directly
    (e.g. <code>60in</code>, <code>1.5m</code>) for a one-off.</p>';
$tabs = static fn (string $ring = '') => '<div class="tabs"><span class="tab">Company</span><span class="tab on ' . $ring . '">Quoting</span><span class="tab">Legal</span>'
      . '<span class="tab">Status colours</span><span class="tab">Suppliers</span><span class="tab">Accounting</span><span class="tab">Back up data</span></div>';
$section = static function (string $sel = 'Millimetres (mm)', string $saveCls = '', string $saveSt = '', string $cls = '', string $st = '', string $more = '') use ($hint): string {
    return '<div class="secbox ' . $cls . '" style="' . $st . '"><div class="sech">Measurements</div>' . $hint
         . '<span class="lab">Default measurement unit</span><div class="sel">' . $sel . '</div>' . $more
         . '<div class="fact"><span class="btnp ' . $saveCls . '" style="' . $saveSt . '">Save unit</span></div></div>';
};

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Measurement units',
        'eyebrow' => 'Settings · Quoting',
        'v'       => 2,
        'blurb'   => 'Pick the unit your team measures in — mm, cm, m or inches — and see everywhere it shows up.',
        'lede'    => 'Every size in YourBlinds is stored in <b>millimetres</b>. This one setting decides what your team <b>types in and
                      reads</b> on screen &mdash; millimetres, centimetres, metres or inches &mdash; without moving a single saved size.
                      This guide shows where it takes effect, how to use a different unit for one job or one blind, and the one thing
                      to watch. To get there: <b>Setup</b> &rarr; <b>Settings</b> &rarr; the <b>Quoting</b> tab &rarr;
                      <b>Measurements</b>.',
        'open'    => '/admin/settings.php#quoting',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          .gd .ph1{ font-size:.98rem; font-weight:800; color:var(--ink); }
          .gd .ph2{ font-size:.66rem; color:var(--faint); margin:.05rem 0 .55rem; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin-bottom:.65rem; }
          .gd .tab{ font-size:.64rem; font-weight:600; color:var(--faint); padding:.28rem .45rem; border:1px solid transparent; border-bottom:none;
                    border-radius:6px 6px 0 0; margin-bottom:-1px; white-space:nowrap; }
          .gd .tab.on{ color:var(--accent); background:var(--surface); border-color:var(--line); border-bottom-color:var(--surface); }
          .gd .secbox{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:29rem; }
          .gd .secbox + .secbox{ margin-top:.45rem; }
          .gd .secbox.dim{ opacity:.55; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .hintg{ font-size:.6rem; color:var(--faint); line-height:1.45; margin:0 0 .4rem; }
          .gd .hintg code{ font-size:.95em; }
          .gd .lab{ display:block; font-size:.62rem; font-weight:600; color:var(--soft); margin-bottom:.12rem; white-space:nowrap; }
          .gd .sel{ position:relative; display:grid; align-items:center; min-width:11rem; max-width:13rem; height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                    padding:0 1.5rem 0 .5rem; font-size:.72rem; color:var(--ink); background:var(--surface); }
          .gd .sel::after{ content:"\25BE"; position:absolute; right:.5rem; top:.3rem; color:var(--faint); font-size:.66rem; }
          .gd .sel > span{ grid-area:1/1; }
          .gd .sel.sm{ min-width:4.5rem; max-width:6rem; }
          .gd .inp{ position:relative; display:grid; align-items:center; height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    background:var(--surface); padding:0 .45rem; font-size:.72rem; color:var(--ink); overflow:hidden; white-space:nowrap; font-variant-numeric:tabular-nums; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp .ph{ color:var(--faint); }
          .gd .inp.bad{ border-color:var(--err); box-shadow:0 0 0 3px var(--err-wash); }
          .gd .fact{ margin-top:.5rem; position:relative; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .flash{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); color:var(--good); font-weight:700;
                      font-size:.72rem; border-radius:8px; padding:.4rem .65rem; margin-bottom:.55rem; max-width:29rem; }
          .gd .flash.err{ background:var(--err-wash); border-color:color-mix(in srgb,var(--err) 35%,transparent); color:var(--err); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.good{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.6rem; }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
          .gd .stack{ display:inline-grid; } .gd .stack > *{ grid-area:1/1; }

          /* dropdown list */
          .gd .ddwrap{ position:relative; display:inline-block; }
          .gd .ddlist{ position:absolute; z-index:5; left:0; top:calc(100% + 2px); min-width:11rem; background:var(--surface); border:1px solid var(--line);
                       border-radius:7px; box-shadow:var(--gd-shadow); padding:.2rem; font-size:.72rem; color:var(--ink); }
          .gd .ddlist div{ padding:.25rem .45rem; border-radius:5px; }
          .gd .ddlist .hl{ background:var(--accent); color:#fff; }

          /* the stored size */
          .gd .vault{ display:flex; flex-direction:column; align-items:center; gap:.2rem; margin:1rem auto .8rem; border:2px solid var(--ink); border-radius:12px;
                      padding:.6rem 1rem; width:max-content; background:var(--panel); }
          .gd .vault small{ font-size:.58rem; font-weight:800; letter-spacing:.08em; color:var(--faint); }
          .gd .vault b{ font-size:1.3rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .shows{ display:flex; justify-content:center; flex-wrap:wrap; gap:.6rem; }
          .gd .disp{ border:1px solid var(--line); border-radius:10px; padding:.4rem .7rem; background:var(--surface); text-align:center; min-width:5rem; }
          .gd .disp small{ display:block; font-size:.56rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; }
          .gd .disp b{ font-size:.95rem; color:var(--accent); font-variant-numeric:tabular-nums; }

          /* quote builder bits */
          .gd .qb{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); max-width:31rem; }
          .gd .qb .lg{ font-size:.66rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
          .gd .row3{ display:grid; grid-template-columns:1fr 1fr 6.5rem; gap:.5rem; align-items:end; }
          .gd .row3.w{ grid-template-columns:1fr 1fr 9.5rem; }
          .gd .line{ display:flex; justify-content:space-between; gap:.5rem; font-size:.7rem; color:var(--ink); padding:.3rem 0; border-top:1px solid var(--line-2); }
          .gd .line:first-of-type{ border-top:0; }
          .gd .line .sz{ display:inline-grid; font-variant-numeric:tabular-nums; color:var(--soft); } .gd .line .sz > span{ grid-area:1/1; }
          .gd .readback{ font-size:.64rem; color:var(--faint); margin-top:.3rem; }
          .gd .paper{ background:#fff; color:#374151; border:1px solid #e5e7eb; border-radius:6px; padding:.5rem .6rem; font-size:.64rem; line-height:1.5; box-shadow:var(--gd-shadow); }
          .gd .paper b{ color:#111827; }
          .gd .three{ display:grid; grid-template-columns:repeat(3,1fr); gap:.6rem; }
          .gd .ptag{ font-size:.56rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--faint); margin-bottom:.25rem; }
          .gd .mm{ background:#fef3c7; border-radius:3px; padding:0 .15rem; }
          .gd .tbl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:20rem; font-size:.7rem; }
          .gd .tbl div{ display:grid; grid-template-columns:1fr 1fr 1.6rem; gap:.4rem; align-items:center; padding:.28rem .5rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .tbl div:first-child{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.64rem; }
          .gd .tbl .h{ display:inline-grid; } .gd .tbl .h > span{ grid-area:1/1; }
          .gd .tbl .x{ color:var(--faint); text-align:center; }

          @media (max-width:640px){
            .gd .three{ grid-template-columns:1fr; }
            .gd .row3, .gd .row3.w{ grid-template-columns:1fr 1fr; }
            .gd .sc{ min-height:440px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / quoting</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="ph1">Settings</div><div class="ph2">Company details and per-quote defaults.</div>' . $tabs() . $section() . '
                  <p class="scs" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; nine short chapters.</p>
                </div>

                <!-- 1 — stored in mm -->
                <div class="sc" data-scene="1" data-len="' . $L(1) . '">
                  <div class="sct a-fade" style="--d:.2s">One size, shown four ways</div>
                  <div class="vault a-pop" style="--d:1.5s"><small>STORED</small><b>1500 mm</b></div>
                  <div class="shows">
                    <div class="disp a-rise" style="--d:6s"><small>Millimetres</small><b>1500 mm</b></div>
                    <div class="disp a-rise" style="--d:6.6s"><small>Centimetres</small><b>150 cm</b></div>
                    <div class="disp a-rise" style="--d:7.2s"><small>Metres</small><b>1.5 m</b></div>
                    <div class="disp a-rise" style="--d:7.8s"><small>Inches</small><b>59.06&quot;</b></div>
                  </div>
                  <div class="chips" style="justify-content:center"><span class="chip a-pop" style="--d:11s">only what you type and read</span>
                    <span class="chip good a-pop" style="--d:15.5s">&#10003; safe to change any time</span></div>
                </div>

                <!-- 2 — finding it -->
                <div class="sc" data-scene="2" data-len="' . $L(2) . '">
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:.6s">Setup</span><span class="arrow a-fade" style="--d:1.2s">&rarr;</span>
                    <span class="chip a-pop" style="--d:1.8s">Settings</span><span class="arrow a-fade" style="--d:2.4s">&rarr;</span>
                    <span class="chip a-pop" style="--d:3s;border-color:var(--accent);color:var(--accent)">Quoting</span>
                  </div>
                  <div class="a-rise" style="--d:3s">' . $tabs('a-ring" style="--d:3.5s') . '</div>
                  <div class="secbox dim a-rise" style="--d:5.5s"><div class="sech" style="margin:0">Default margins</div></div>
                  ' . $section('Millimetres (mm)', '', '', 'a-rise', '--d:6.5s') . '
                  <div class="secbox dim a-rise" style="--d:9.5s"><div class="sech" style="margin:0">Quote defaults</div></div>
                </div>

                <!-- 3 — pick and save -->
                <div class="sc" data-scene="3" data-len="' . $L(3) . '">
                  <div class="flash a-pop" style="--d:17.5s">Default measurement unit saved.</div>
                  <div class="secbox"><div class="sech">Measurements</div>
                    <span class="lab">Default measurement unit</span>
                    <div class="ddwrap"><div class="sel a-ring" style="--d:1s"><span class="a-out" style="--d:10s">Millimetres (mm)</span><span class="a-fade" style="--d:10s">Inches (in)</span></div>
                      <div class="ddlist a-mid" style="--d:1.8s;--d2:10s">
                        <div class="a-sel" style="--d:3s">Millimetres (mm)</div><div class="a-fade" style="--d:3.5s">Centimetres (cm)</div>
                        <div class="a-fade" style="--d:4s">Metres (m)</div><div class="a-sel" style="--d:8.5s">Inches (in)</div></div></div>
                    <div class="fact"><span class="btnp a-press a-ring" style="--d:15s">Save unit</span></div></div>
                  <div class="chips"><span class="chip a-pop" style="--d:7s">the unit your fitters call out</span>
                    <span class="chip a-pop" style="--d:11.5s">most people stay on mm</span></div>
                </div>

                <!-- 4 — on a quote -->
                <div class="sc" data-scene="4" data-len="' . $L(4) . '">
                  <div class="qb a-rise" style="--d:.8s"><div class="lg">Add a blind</div>
                    <div class="row3">
                      <div><span class="lab a-ring" style="--d:4s;border-radius:4px">Width (&quot;) <span class="req">*</span></span><div class="inp"><span class="ph">Width in &quot;</span></div></div>
                      <div><span class="lab a-ring" style="--d:4.5s;border-radius:4px">Drop (&quot;) <span class="req">*</span></span><div class="inp"><span class="ph">Drop in &quot;</span></div></div>
                      <div><span class="lab">Measurement</span><div class="sel sm">inch</div></div>
                    </div></div>
                  <div class="vault a-pop" style="--d:10s;margin-top:1rem"><small>STORED</small><b>1500 mm</b></div>
                  <div class="shows">
                    <div class="disp a-rise" style="--d:14s"><small>cm &middot; 1 decimal</small><b>150 cm</b></div>
                    <div class="disp a-rise" style="--d:17s"><small>m &middot; up to 3</small><b>1.5 m</b></div>
                    <div class="disp a-rise" style="--d:19s"><small>inches &middot; 2</small><b>59.06&quot;</b></div>
                  </div>
                </div>

                <!-- 5 — one job, another unit -->
                <div class="sc" data-scene="5" data-len="' . $L(5) . '">
                  <div class="qb"><div class="lg">Quote Q-1042 &middot; Mrs Patel</div>
                    <div class="row3">
                      <div><span class="lab"><span class="stack"><span class="a-out" style="--d:13s">Width (mm)</span><span class="a-fade" style="--d:13s">Width (&quot;)</span></span></span><div class="inp"><span class="ph">&nbsp;</span></div></div>
                      <div><span class="lab"><span class="stack"><span class="a-out" style="--d:13s">Drop (mm)</span><span class="a-fade" style="--d:13s">Drop (&quot;)</span></span></span><div class="inp"><span class="ph">&nbsp;</span></div></div>
                      <div><span class="lab a-ring" style="--d:7s;border-radius:4px">Measurement</span><div class="sel sm"><span class="a-out" style="--d:11s">mm</span><span class="a-fade" style="--d:11s">inch</span></div></div>
                    </div>
                    <div style="margin-top:.5rem">
                      <div class="line"><span>Roller blind &middot; Lounge</span><span class="sz"><span class="a-out" style="--d:13.5s">1500 &times; 1200 mm</span><span class="a-fade" style="--d:13.8s">59.06&quot; &times; 47.24&quot;</span></span></div>
                      <div class="line"><span>Roller blind &middot; Kitchen</span><span class="sz"><span class="a-out" style="--d:13.7s">900 &times; 1000 mm</span><span class="a-fade" style="--d:14s">35.43&quot; &times; 39.37&quot;</span></span></div>
                    </div></div>
                  <div class="chips"><span class="chip a-pop" style="--d:2.5s">no need to touch Settings</span>
                    <span class="chip a-pop" style="--d:12s">&#8635; page reloads</span>
                    <span class="chip good a-pop" style="--d:18s">sticks to this quote</span></div>
                </div>

                <!-- 6 — one blind, typed unit -->
                <div class="sc" data-scene="6" data-len="' . $L(6) . '">
                  <div class="qb a-rise" style="--d:.5s"><div class="lg">InstaPrice</div>
                    <div class="row3 w">
                      <div><span class="lab">Width</span><div class="inp">' . $type('1.5m', 7.5, 4, .4) . '</div></div>
                      <div><span class="lab">Drop</span><div class="inp">' . $type('60in', 6, 4, .4) . '</div></div>
                      <div><span class="lab">Measurement unit</span><div class="sel" style="min-width:0;max-width:none;white-space:nowrap">Millimetres (mm)</div></div>
                    </div>
                    <div class="readback a-fade" style="--d:15.5s">Using 1500 &times; 1524 mm</div></div>
                  <div class="chips"><span class="chip a-pop" style="--d:10s">a typed unit wins &mdash; just for that box</span>
                    <span class="chip good a-pop" style="--d:17s">read back in mm &mdash; spot a slip</span></div>
                </div>

                <!-- 7 — bare numbers and words -->
                <div class="sc" data-scene="7" data-len="' . $L(7) . '">
                  <div class="qb"><div class="lg">An inches quote</div>
                    <div class="row3">
                      <div><span class="lab">Width (&quot;) <span class="req">*</span></span><div class="inp bad a-ring" style="--d:7s">' . $type('1500', 5, 4, .4) . '</div></div>
                      <div style="align-self:center"><span class="chip bad a-pop" style="--d:7.5s">= 1500 inches (38,100 mm!)</span></div>
                    </div></div>
                  <div class="flash err a-fly" style="--d:14s;margin-top:.7rem">Could not read width &quot;two metres&quot;.</div>
                  <div class="qb a-rise" style="--d:18s;max-width:18rem"><span class="lab">Width (&quot;) <span class="req">*</span></span><div class="inp">' . $type('2m', 19, 2, .3) . '</div></div>
                  <div class="chips"><span class="chip good a-pop" style="--d:19.5s">put the unit on it</span></div>
                </div>

                <!-- 8 — customer paperwork stays mm -->
                <div class="sc" data-scene="8" data-len="' . $L(8) . '">
                  <div class="three">
                    <div class="a-rise" style="--d:3s"><div class="ptag">Quote PDF</div><div class="paper"><b>Roller blind</b><br>Lounge<br><span class="mm">1500 &times; 1200 mm</span></div></div>
                    <div class="a-rise" style="--d:6s"><div class="ptag">Online quote page</div><div class="paper"><b>Roller blind</b><br>Lounge<br><span class="mm">1500 &times; 1200 mm</span></div></div>
                    <div class="a-rise" style="--d:9.5s"><div class="ptag">Factory paperwork</div><div class="paper"><b>W</b> 1500 &nbsp;<b>D</b> 1200<br><span class="mm">mm</span></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:1s">customers never see this setting</span>
                    <span class="chip good a-pop" style="--d:13s">&#9986; the workshop cuts in mm</span></div>
                </div>

                <!-- 9 — width-only / per-slat price lists -->
                <div class="sc" data-scene="9" data-len="' . $L(9) . '">
                  <div class="sct a-fade" style="--d:.2s">A width-only price list</div>
                  <div class="tbl a-rise" style="--d:3s">
                    <div><span class="h a-ring" style="--d:9s;border-radius:4px"><span class="a-out" style="--d:10s">Width (mm)</span><span class="a-fade" style="--d:10s">Width (&quot;)</span></span><span>Price (&pound;)</span><span></span></div>
                    <div><span class="stack"><span class="a-out" style="--d:10.5s">600</span><span class="a-fade" style="--d:10.5s">23.62</span></span><span>18.40</span><span class="x">&times;</span></div>
                    <div><span class="stack"><span class="a-out" style="--d:10.7s">900</span><span class="a-fade" style="--d:10.7s">35.43</span></span><span>22.10</span><span class="x">&times;</span></div>
                    <div><span class="inp" style="height:22px"><span class="ph stack"><span class="a-out" style="--d:12s">e.g. 800</span><span class="a-fade" style="--d:12s">e.g. 31.5</span></span></span><span class="inp" style="height:22px"></span><span></span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:5s">width only</span><span class="chip a-pop" style="--d:6s">per slat</span>
                    <span class="chip good a-pop" style="--d:15s">nothing saved moves</span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>Everything in YourBlinds is stored in <b>millimetres</b> &mdash; every quote line, every price table, every factory
             ticket. This one setting decides what your team <em>types into</em> and <em>reads off</em> the screen. It changes the
             labels and the numbers you see, never the sizes underneath, so it is safe to change with any number of quotes already
             on the system.</p>

          <ul class="steps">
            <li><b>Find it.</b> <b>Setup &rarr; Settings</b> &rarr; the <b>Quoting</b> tab &rarr; the <b>Measurements</b> section,
                between <b>Default margins</b> and <b>Quote defaults</b>. (Settings remembers the tab you were last on.)</li>
            <li><b>Pick the unit.</b> <b>Default measurement unit</b> is a dropdown with four choices: <b>Millimetres (mm)</b>,
                <b>Centimetres (cm)</b>, <b>Metres (m)</b>, <b>Inches (in)</b>. Choose the one your fitters call out on site. Most UK
                blind shops stay on <b>mm</b>.</li>
            <li><b>Press <em>Save unit</em>.</b> It saves this section only. The page reloads with a green <b>&ldquo;Default
                measurement unit saved.&rdquo;</b></li>
            <li><b>Check a quote.</b> In the add-a-blind row the <b>Width</b> and <b>Drop</b> labels carry your unit &mdash;
                <b>Width (mm)</b>, <b>Width (cm)</b>, <b>Width (m)</b>, or <b>Width (&quot;)</b> for inches &mdash; and the empty box says
                <b>&ldquo;Width in &hellip;&rdquo;</b> to match.</li>
          </ul>

          <p><b>How the numbers are shown.</b> Each unit has its own precision: 1500&nbsp;mm reads <b>1500 mm</b>, <b>150 cm</b>,
             <b>1.5 m</b> or <b>59.06&quot;</b> &mdash; whole millimetres, one decimal for centimetres, up to three for metres, two for
             inches, with trailing zeros trimmed.</p>

          <p><b>One job in a different unit.</b> Beside the size boxes on the quote is a dropdown headed <b>Measurement</b>
             (<b>mm</b>, <b>cm</b>, <b>m</b>, <b>inch</b>). Change it and the page reloads with every line on that quote shown in the new
             unit; it sticks to that quote from then on. On a locked quote you&rsquo;ll get <b>&ldquo;Quote is locked &mdash; reopen it to
             change the measurement unit.&rdquo;</b> <b>InstaPrice</b> has the same switch, called <b>Measurement unit</b>; it starts
             on the company default.</p>

          <p><b>One blind in a different unit.</b> Type the unit straight into the size box &mdash; <code>60in</code>,
             <code>60&quot;</code>, <code>1.5m</code>, <code>61.5cm</code>, <code>1500mm</code> &mdash; and that wins over every setting,
             for that box alone. In InstaPrice a grey line under the size boxes reads back what will be used, in millimetres:
             <b>&ldquo;Using 1500 &times; 1524 mm&rdquo;</b> (or <b>&ldquo;Using 1500 mm wide&rdquo;</b> / <b>&ldquo;Using 1000 mm drop
             (per slat)&rdquo;</b>).</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>A number with no unit is read in the quote&rsquo;s unit.</b> On an
             inches quote, <b>1500</b> in Width means <em>1500 inches</em> &mdash; 38,100&nbsp;mm &mdash; and nothing stops you, because
             it&rsquo;s a valid number. Glance at the size on the line you just added.</div></div>

          <div class="oops"><b>&ldquo;Could not read width &quot;two metres&quot;.&rdquo;</b> Size boxes take <em>numbers</em> (with an
             optional unit), not words; the line isn&rsquo;t added (the drop has its own <b>&ldquo;Could not read drop &hellip;&rdquo;</b>).
             Retype it as <code>2m</code> or <code>2000mm</code> &mdash; with the unit on, because on an inches quote a bare
             <code>2000</code> means 2000 inches.</div>

          <p><b>Where it never shows.</b> The <b>customer&rsquo;s quote PDF</b> and the <b>online quote page</b> print sizes as
             <b>1500 &times; 1200 mm</b> whatever you choose, and the factory paperwork stays in millimetres too &mdash; the workshop
             cuts in millimetres. Whether sizes are printed for the customer at all is a separate setting on the same tab:
             <b>Sizes on the customer quote</b> &rarr; <b>Show the size of each blind</b>.</p>

          <p><b>Price lists follow the company unit.</b> On a <b>width-only</b> price list the heading reads e.g. <b>Width
             (&quot;)</b> next to <b>Price (&pound;)</b>; on a <b>per-slat</b> one, <b>Drop (&quot;)</b>. The empty boxes prompt
             <b>&ldquo;e.g. 31.5&rdquo;</b> for inches, <b>&ldquo;e.g. 0.8&rdquo;</b> for metres, <b>&ldquo;e.g. 80&rdquo;</b> for
             centimetres, <b>&ldquo;e.g. 800&rdquo;</b> for millimetres. Change the company unit and they re-label and re-display;
             not one saved price or width moves. The big width &times; drop grid keeps its <b>Drop \\ Width (mm)</b> corner.</p>

          <p><b>If saving fails</b> with <b>&ldquo;Could not save measurement unit &mdash; has migrate_measurement_unit.php been
             run?&rdquo;</b>, a one-off database update is missing &mdash; send that to support; until then the system works in
             millimetres.</p>',
        'script'  => $script,
];
