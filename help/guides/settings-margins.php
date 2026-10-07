<?php
declare(strict_types=1);

/**
 * Guide: settings-margins — "Default margins" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script, js.
 *
 * Mirrors the "Default margins" section on the Quoting tab of
 * /admin/settings.php: the intro hint, "Enter your margins as" radios
 * (Markup % / Margin %) and their hint, "Default price-table markup %" and
 * "Default options & extras markup %" (label word follows the basis), the blue
 * "≈ …% margin" / "≈ …% markup (what the engine uses)" lines, "Save margins"
 * and "Default margins saved.". The engine only stores markup; the save
 * handler clamps it to 0–999 (so the highest margin is ≈ 90.90%). Settings
 * remembers its tab (localStorage), so a save lands back on Quoting.
 * Product side: admin/products/edit.php "Pricing per system" (0 = inherit).
 * Supplier-list products skip the options default (pricing_engine.php).
 *
 * v2: one SCENE per script line (data-scene = the line's step); data-len is
 * worked out from the line itself (characters ÷ 13.6). The 'js' key keeps the
 * markup-vs-margin calculator that sits in the written steps.
 */

$script = [
    ['1',  'Set your profit once',          'Default margins is where you set your profit once, instead of on every product. Go to Setup, then Settings, and click the Quoting tab. Default margins is the first panel on it. The two numbers here are your fallback. They are used everywhere you have not typed a different figure of your own.', 1],
    ['2',  'Markup or margin?',             'First, choose how you like to think about profit. Enter your margins as: Markup, or Margin. Markup is added on top of your cost. A hundred pounds cost, plus fifty percent markup, sells for a hundred and fifty. Margin is the profit\'s share of the selling price. A fifty percent margin means your cost is half the price, so it sells for two hundred.', 2],
    ['3',  'The price is the same either way','Do not worry about picking the wrong one. The customer price is exactly the same either way. This only sets which number you type. A hundred percent markup and a fifty percent margin are the same money, written two ways. You can switch at any time, without re-pricing anything.', 3],
    ['4',  'Your everyday rate',            'Now the first box: Default price-table markup. This is your everyday rate on the blind itself, the price that comes from the price table. It is used on every product and system that does not have its own figure set on the product page. Type, say, one hundred, and the price-table cost is doubled.', 4],
    ['5',  'The blue line',                 'Under each box, a blue line does the sum for you. On markup, it shows the same rate as a margin. So a hundred percent markup reads, about fifty percent margin. Behind the scenes, the system always works in markup, and simply converts what you type. That is why switching between the two is safe.', 5],
    ['6',  'Options and extras',            'The second box, Default options and extras, does the same job for the add-ons: fixed pound charges, per metre charges, and width tables. The screen adds a note. It only applies to option choices added after this feature went live. Older choices keep the prices they were given.', 6],
    ['7',  'Switching to margin',           'Watch what happens when you click Margin. The labels change from markup to margin, and the numbers change too. One hundred becomes fifty. Nothing has been re-priced. It is the same money, written the other way round. And the blue line now shows the markup, which is what the system uses.', 7],
    ['8',  'The ceiling',                   'One thing to know before you push margin up high. Margin climbs fast near the top. Eighty percent margin is five times your cost. And there is a ceiling: the system stores at most nine hundred and ninety nine percent markup. So the highest margin is about ninety point nine. Type more, and it quietly saves the highest it can.', 8],
    ['9',  'Save margins',                  'When you are happy, click Save margins. The page reloads, still on the Quoting tab, and a green bar says, Default margins saved. From now on, every product and option without a figure of its own uses these rates, on retail and trade quotes alike.', 9],
    ['10', 'One product different',         'Need one product to be different? Set its own rate on that product\'s page, under Pricing per system, and it wins over this default. But note: on the product page, nought does not mean no profit. Typing nought there means, use the default from Settings.', 10],
];
$L = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr  = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$type = static fn (string $txt, float $d, int $steps = 6, float $tt = .5): string =>
    '<span class="a-type" style="--d:' . $d . 's;--ts:' . $steps . ';--tt:' . $tt . 's">' . $txt . '</span>';
$intro = '<p class="hintg">Set your usual margin once and the engine applies it everywhere &mdash; no need to set markup on every product
    or option choice. You can still override at the product / option level when needed.</p>';
$basisHint = '<p class="hintg"><b>Markup</b> is added on top of your cost (cost&nbsp;+&nbsp;50%&nbsp;=&nbsp;sell). <b>Margin</b> is the profit slice
    of the sell price (50%&nbsp;margin&nbsp;=&nbsp;cost is half the sell). The customer price is identical either way &mdash; this only sets
    which number you type. You can switch any time without re-pricing anything.</p>';
$ptHint  = '<p class="hintg">Applied to every (product, system) that doesn&rsquo;t have an explicit value set on the product edit page.</p>';
$optHint = '<p class="hintg">Uniform uplift on every option choice&rsquo;s price &mdash; fixed-&pound;, per-metre, and width-table modes all included.
    Only applies to new choices you add after this feature went live; existing choices stay at their entered prices.</p>';
$rad = static fn (string $label, string $fill = '') => '<span class="rad"><span class="rd">' . $fill . '</span>' . $label . '</span>';
$radios = static fn (string $mk = '<i></i>', string $mg = '') => '<span class="flab">Enter your margins as</span>' . $rad('Markup&nbsp;%', $mk) . $rad('Margin&nbsp;%', $mg);
$tabs = static fn (string $ring = '') => '<div class="tabs"><span class="tab">Company</span><span class="tab on ' . $ring . '">Quoting</span><span class="tab">Legal</span>'
      . '<span class="tab">Status colours</span><span class="tab">Suppliers</span><span class="tab">Accounting</span><span class="tab">Back up data</span></div>';
// The two boxes. Each arg is [label word HTML, value HTML, blue line HTML, extra class/style for the box].
$boxes = static function (array $pt, array $opt, bool $hints = true) use ($ptHint, $optHint): string {
    $one = static fn (string $prefix, array $b, string $hint) => '<div class="fg"><span class="lab">' . $prefix . $b[0] . ' %</span>'
        . '<div class="inp ' . ($b[3] ?? '') . '">' . $b[1] . '</div><span class="blue">' . $b[2] . '</span>' . $hint . '</div>';
    return '<div class="r2">' . $one('Default price-table ', $pt, $hints ? $ptHint : '') . $one('Default options &amp; extras ', $opt, $hints ? $optHint : '') . '</div>';
};
$panel = static function (string $inner, string $cls = '', string $st = '') {
    return '<div class="secbox ' . $cls . '" style="' . $st . '"><div class="sech">Default margins</div>' . $inner . '</div>';
};
$saveBtn = static fn (string $cls = '', string $st = '') => '<div class="fact"><span class="btnp ' . $cls . '" style="' . $st . '">Save margins</span></div>';

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Default margins',
        'eyebrow' => 'Settings · Quoting',
        'v'       => 2,
        'blurb'   => 'Set your profit once, here, instead of on every product — plus markup vs margin, with a live calculator.',
        'lede'    => 'Two numbers that set your <b>profit everywhere</b>: one for the blind itself, one for options and extras. They
                      are the fallback the system uses wherever you haven&rsquo;t typed a figure of your own. This guide also clears up
                      <b>markup versus margin</b> &mdash; slowly, one idea at a time &mdash; and there&rsquo;s a calculator to play with
                      at the end. To get there: <b>Setup</b> &rarr; <b>Settings</b> &rarr; the <b>Quoting</b> tab.',
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
          .gd .secbox{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:31rem; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .flab{ display:block; font-size:.66rem; font-weight:600; color:var(--ink); margin:.4rem 0 .25rem; }
          .gd .hintg{ font-size:.58rem; color:var(--faint); line-height:1.45; margin:.25rem 0 0; }
          .gd .hintg b{ color:var(--soft); }
          .gd .r2{ display:grid; grid-template-columns:1fr 1fr; gap:.4rem .7rem; margin-top:.55rem; }
          .gd .fg{ min-width:0; }
          .gd .lab{ display:block; font-size:.62rem; font-weight:600; color:var(--soft); margin-bottom:.12rem; }
          .gd .stack > *{ grid-area:1/1; }
          .gd .stack{ display:inline-grid; }
          .gd .inp{ position:relative; display:grid; align-items:center; height:24px; max-width:9rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    background:var(--surface); padding:0 .45rem; font-size:.72rem; color:var(--ink); overflow:hidden; white-space:nowrap; font-variant-numeric:tabular-nums; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp.focus{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .blue{ display:grid; font-size:.62rem; color:#2563eb; margin-top:.15rem; min-height:.9rem; }
          :root[data-theme="dark"] .gd .blue{ color:#60a5fa; }
          .gd .blue > span{ grid-area:1/1; }
          .gd .rad{ display:inline-flex; align-items:center; gap:.4rem; font-size:.72rem; color:var(--ink); margin-right:1.2rem; }
          .gd .rd{ width:14px; height:14px; border-radius:50%; border:1.5px solid var(--border-strong,#9aa3af); display:inline-grid; place-items:center; box-sizing:border-box; background:var(--surface); }
          .gd .rd i{ grid-area:1/1; width:6px; height:6px; border-radius:50%; background:var(--accent); display:block; }
          .gd .fact{ margin-top:.5rem; position:relative; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .flash{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); color:var(--good); font-weight:700;
                      font-size:.72rem; border-radius:8px; padding:.4rem .65rem; margin-bottom:.55rem; max-width:31rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.good{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.6rem; }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }

          /* sums */
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin-top:.7rem; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); }
          .gd .card h4{ margin:0 0 .45rem; font-size:.78rem; color:var(--ink); }
          .gd .card p{ margin:.45rem 0 0; font-size:.64rem; color:var(--soft); line-height:1.45; }
          .gd .chain{ display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; }
          .gd .v{ display:inline-flex; flex-direction:column; align-items:center; border:1px solid var(--line); border-radius:8px; padding:.25rem .5rem; background:var(--panel); }
          .gd .v small{ font-size:.52rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .v b{ font-size:.86rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .v.out{ border-color:var(--good); } .gd .v.out b{ color:var(--good); }
          .gd .op{ font-size:.68rem; font-weight:800; border-radius:999px; padding:.1rem .45rem; background:var(--good-wash); color:var(--good); }
          .gd .split{ display:flex; height:1.3rem; border-radius:6px; overflow:hidden; margin-top:.45rem; font-size:.6rem; font-weight:700; color:#fff; max-width:15rem; }
          .gd .split span{ display:grid; place-items:center; }
          .gd .split .c{ background:#64748b; } .gd .split .p{ background:var(--good); }
          .gd .eq{ display:flex; align-items:center; justify-content:center; flex-wrap:wrap; gap:.6rem; margin:1rem 0 .6rem; }
          .gd .big{ border:2px solid var(--accent); border-radius:12px; padding:.55rem .9rem; text-align:center; background:var(--surface); }
          .gd .big b{ display:block; font-size:1.2rem; color:var(--ink); } .gd .big small{ font-size:.62rem; color:var(--faint); font-weight:700; }
          .gd .eqs{ font-size:1.4rem; font-weight:800; color:var(--accent); }
          .gd .ladder{ display:flex; flex-direction:column; gap:.35rem; margin-top:.6rem; max-width:27rem; }
          .gd .rung{ display:flex; align-items:center; gap:.5rem; font-size:.66rem; color:var(--soft); }
          .gd .rung b{ width:5.5rem; color:var(--ink); flex:0 0 auto; }
          .gd .rung .barx{ height:.7rem; border-radius:4px; background:var(--accent); }
          .gd .rung .barx.hot{ background:#f59e0b; } .gd .rung .barx.max{ background:var(--err); }
          .gd .tbl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:24rem; font-size:.68rem; }
          .gd .tbl div{ display:grid; grid-template-columns:1fr 6rem 6rem; gap:.4rem; align-items:center; padding:.3rem .5rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .tbl div:first-child{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.62rem; }

          /* the calculator in the written steps */
          .gd .calc-open{ margin-top:.7rem; display:inline-flex; align-items:center; gap:.45rem; cursor:pointer; font:inherit; font-weight:700; font-size:.92rem; border:none; border-radius:9px; padding:.55rem 1rem; background:var(--accent); color:#fff; }
          .gd .calc-open:hover{ background:var(--accent-ink); }
          .gd .calc-modal{ display:none; position:fixed; inset:0; z-index:50; background:rgba(10,15,22,.55); align-items:center; justify-content:center; padding:1rem; }
          .gd .calc-modal.open{ display:flex; }
          .gd .calc-box{ position:relative; width:min(560px,100%); max-height:90vh; overflow:auto; background:var(--surface); border:1px solid var(--line); border-radius:16px; box-shadow:var(--gd-shadow); padding:1.3rem 1.4rem; color:var(--ink); }
          .gd .calc-box h3{ margin:0 0 1rem; font-size:1.15rem; }
          .gd .calc-x{ position:absolute; top:.55rem; right:.7rem; border:none; background:none; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--faint); }
          .gd .calc-row{ display:flex; flex-direction:column; gap:.7rem; margin-bottom:.8rem; }
          .gd .calc-row label{ font-size:.85rem; color:var(--soft); font-weight:600; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .calc-row input[type=number]{ width:6rem; font:inherit; padding:.3rem .5rem; border:1px solid var(--line); border-radius:7px; background:var(--panel); color:var(--ink); }
          .gd .calc-row input[type=range]{ flex:1; min-width:11rem; accent-color:var(--accent); }
          .gd .mmchips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1rem; }
          .gd .mmchip{ font:inherit; font-size:.72rem; line-height:1.3; cursor:pointer; text-align:left; border:1px solid var(--line); background:var(--panel); color:var(--soft); border-radius:9px; padding:.35rem .55rem; }
          .gd .mmchip:hover{ border-color:var(--accent); color:var(--accent-ink); }
          .gd .mmchip b{ display:block; color:var(--ink); font-size:.82rem; }
          .gd .calc-cards{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; }
          .gd .calc-card{ border:1px solid var(--line); border-radius:12px; padding:.8rem .9rem; background:var(--panel); text-align:center; }
          .gd .cc-h{ font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:var(--faint); }
          .gd .cc-sell{ font-size:1.5rem; font-weight:800; margin:.2rem 0; letter-spacing:-.02em; }
          .gd .cc-sub{ font-size:.78rem; color:var(--soft); }
          .gd .calc-card.cc-margin .cc-sell{ color:var(--accent-ink); }
          .gd .calc-card.danger{ border-color:var(--err); background:var(--err-wash); }
          .gd .calc-card.danger .cc-sell{ color:var(--err); }
          .gd .cc-cap{ display:none; font-size:.68rem; line-height:1.4; color:var(--err); font-weight:600; margin-top:.3rem; }
          .gd .calc-card.danger .cc-cap{ display:block; }
          .gd .calc-card.steep{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .calc-card.steep .cc-sell{ color:#b45309; }
          :root[data-theme="dark"] .gd .calc-card.steep .cc-sell{ color:#fbbf24; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .calc-card.steep .cc-sell{ color:#fbbf24; } }
          .gd .calc-bars{ margin:.9rem 0 .3rem; display:flex; flex-direction:column; gap:.4rem; }
          .gd .mmbar{ height:12px; background:var(--line-2); border-radius:6px; overflow:hidden; }
          .gd .mmbar span{ display:block; height:100%; border-radius:6px; transition:width .15s; width:0; }
          .gd .bar-mk span{ background:var(--soft); }
          .gd .bar-mg span{ background:var(--accent); }
          .gd .calc-store{ margin-top:.8rem; border:1px dashed var(--line); border-radius:10px; padding:.55rem .7rem; font-size:.84rem; color:var(--soft); }
          .gd .calc-store b{ color:var(--accent); }
          .gd .calc-note{ font-size:.9rem; color:var(--ink); margin-top:.7rem; line-height:1.5; }
          .gd .calc-eq{ font-size:.88rem; color:var(--soft); margin-top:.5rem; }
          .gd .calc-eq .boom{ display:block; margin-top:.4rem; color:var(--err); font-weight:600; }

          @media (max-width:640px){
            .gd .two, .gd .r2{ grid-template-columns:1fr; }
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
                  <div class="ph1">Settings</div><div class="ph2">Company details and per-quote defaults.</div>' . $tabs() . '
                  ' . $panel($intro . $radios() . $boxes(['markup', '100.00', '&asymp; 50.00% margin'], ['markup', '100.00', '&asymp; 50.00% margin'], false) . $saveBtn()) . '
                  <p class="scs" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters.</p>
                </div>

                <!-- 1 — set it once -->
                <div class="sc" data-scene="1" data-len="' . $L(1) . '">
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:4.5s">Setup</span><span class="arrow a-fade" style="--d:5.2s">&rarr;</span>
                    <span class="chip a-pop" style="--d:6s">Settings</span><span class="arrow a-fade" style="--d:6.8s">&rarr;</span>
                    <span class="chip a-pop" style="--d:7.6s;border-color:var(--accent);color:var(--accent)">Quoting</span>
                  </div>
                  <div class="a-rise" style="--d:7.5s">' . $tabs('a-ring" style="--d:8s') . '</div>
                  ' . $panel($intro . $radios() . $boxes(['markup', '100.00', ''], ['markup', '100.00', ''], false), 'a-rise', '--d:10s') . '
                  <div class="chips"><span class="chip good a-pop" style="--d:15.5s">Your fallback &mdash; used wherever you haven&rsquo;t typed your own</span></div>
                </div>

                <!-- 2 — markup or margin -->
                <div class="sc" data-scene="2" data-len="' . $L(2) . '">
                  <div class="secbox a-rise" style="--d:.5s">' . $radios('<i class="a-pop" style="--d:4.5s"></i>', '') . '</div>
                  <div class="two">
                    <div class="card a-rise" style="--d:6.5s"><h4>Markup &mdash; on top of your cost</h4>
                      <div class="chain"><span class="v a-pop" style="--d:9s"><small>Cost</small><b>&pound;100</b></span><span class="op a-pop" style="--d:10.5s">+ 50%</span>
                        <span class="v out a-pop" style="--d:12s"><small>Sell</small><b>&pound;150</b></span></div></div>
                    <div class="card a-rise" style="--d:14s"><h4>Margin &mdash; profit&rsquo;s share of the price</h4>
                      <div class="chain"><span class="v a-pop" style="--d:18.5s"><small>Cost</small><b>&pound;100</b></span><span class="op a-pop" style="--d:19.5s">50% margin</span>
                        <span class="v out a-pop" style="--d:22s"><small>Sell</small><b>&pound;200</b></span></div>
                      <div class="split a-wide" style="--d:20.5s"><span class="c" style="width:50%">cost &pound;100</span><span class="p" style="width:50%">profit &pound;100</span></div></div>
                  </div>
                </div>

                <!-- 3 — same price either way -->
                <div class="sc" data-scene="3" data-len="' . $L(3) . '">
                  <div class="sct a-fade" style="--d:.2s">Same money, written two ways</div>
                  <div class="eq">
                    <div class="big a-pop" style="--d:9.5s"><small>MARKUP</small><b>100%</b></div>
                    <span class="eqs a-pop" style="--d:11.5s">=</span>
                    <div class="big a-pop" style="--d:12.5s"><small>MARGIN</small><b>50%</b></div>
                    <span class="arrow a-fade" style="--d:14s">&rarr;</span>
                    <div class="big a-pop" style="--d:14.5s;border-color:var(--good)"><small>CUSTOMER PAYS</small><b>&pound;200</b></div>
                  </div>
                  <div class="a-fade" style="--d:3s">' . $basisHint . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:6s">only sets which number you type</span>
                    <span class="chip good a-pop" style="--d:17.5s">switch any time &mdash; nothing re-priced</span></div>
                </div>

                <!-- 4 — the price-table box -->
                <div class="sc" data-scene="4" data-len="' . $L(4) . '">
                  ' . $panel($radios() . $boxes(['markup', '<span class="stack"><span class="a-out" style="--d:18s">0.00</span>' . $type('100', 18.3, 3, .4) . '</span>', '', 'focus a-ring" style="--d:2s'],
                                                 ['markup', '100.00', '']), '', '') . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5s">the blind itself &mdash; from the price table</span>
                    <span class="chip a-pop" style="--d:12s">every product &amp; system without its own figure</span>
                  </div>
                  <div class="chain" style="margin-top:.7rem"><span class="v a-pop" style="--d:19s"><small>Price-table cost</small><b>&pound;30.00</b></span>
                    <span class="op a-pop" style="--d:19.7s">+ 100%</span><span class="v out a-pop" style="--d:20.4s"><small>Sell</small><b>&pound;60.00</b></span></div>
                </div>

                <!-- 5 — the blue line -->
                <div class="sc" data-scene="5" data-len="' . $L(5) . '">
                  ' . $panel($radios() . $boxes(['markup', '100.00', '<span class="a-pop" style="--d:4s">&asymp; 50.00% margin</span>', 'focus'],
                                                 ['markup', '100.00', '<span class="a-pop" style="--d:5s">&asymp; 50.00% margin</span>'], false)) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:8s">markup 100% &harr; margin 50%</span>
                    <span class="chip a-pop" style="--d:13s">&#9881; the system always works in markup</span>
                    <span class="chip good a-pop" style="--d:19s">so switching is safe</span>
                  </div>
                </div>

                <!-- 6 — options & extras -->
                <div class="sc" data-scene="6" data-len="' . $L(6) . '">
                  ' . $panel($radios() . $boxes(['markup', '100.00', '&asymp; 50.00% margin'],
                                                 ['markup', '<span class="stack"><span class="a-out" style="--d:2.5s">0.00</span>' . $type('100', 2.8, 3, .4) . '</span>', '<span class="a-pop" style="--d:3.5s">&asymp; 50.00% margin</span>', 'focus a-ring" style="--d:1s'], false)
                             . '<div class="a-ring" style="--d:12s;border-radius:6px;margin-top:.3rem">' . $optHint . '</div>') . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:6.5s">fixed &pound;</span><span class="chip a-pop" style="--d:7.5s">per metre</span><span class="chip a-pop" style="--d:8.5s">width tables</span>
                    <span class="chip bad a-pop" style="--d:16s">older choices keep their prices</span>
                  </div>
                </div>

                <!-- 7 — switch to margin -->
                <div class="sc" data-scene="7" data-len="' . $L(7) . '">
                  ' . $panel($radios('<i class="a-out" style="--d:2.5s"></i>', '<i class="a-pop" style="--d:2.6s"></i>')
                     . $boxes(['<span class="stack"><span class="a-out" style="--d:4.5s">markup</span><span class="a-fade" style="--d:4.6s">margin</span></span>',
                               '<span class="stack"><span class="a-out" style="--d:7.5s">100.00</span><span class="a-fade" style="--d:7.6s">50.00</span></span>',
                               '<span class="a-out" style="--d:7.5s">&asymp; 50.00% margin</span><span class="a-fade" style="--d:18s">&asymp; 100.00% markup (what the engine uses)</span>', 'a-ring" style="--d:7.5s'],
                              ['<span class="stack"><span class="a-out" style="--d:4.5s">markup</span><span class="a-fade" style="--d:4.6s">margin</span></span>',
                               '<span class="stack"><span class="a-out" style="--d:8s">100.00</span><span class="a-fade" style="--d:8.1s">50.00</span></span>',
                               '<span class="a-out" style="--d:8s">&asymp; 50.00% margin</span><span class="a-fade" style="--d:18.4s">&asymp; 100.00% markup (what the engine uses)</span>'], false)) . '
                  <div class="chips"><span class="chip good a-pop" style="--d:10.5s">Nothing re-priced &mdash; same money</span></div>
                  <div class="a-move" style="--fx:70%;--fy:80%;--tx:6.8rem;--ty:4.3rem;--d:.4s;--md:1.8s">' . $ptr . '</div>
                </div>

                <!-- 8 — the ceiling -->
                <div class="sc" data-scene="8" data-len="' . $L(8) . '">
                  <div class="sct a-fade" style="--d:.2s">Margin climbs fast near the top</div>
                  <div class="ladder">
                    <div class="rung a-fly" style="--d:4s"><b>50% margin</b><span class="barx a-wide" style="--d:4.3s;width:2.4rem"></span>2 &times; your cost</div>
                    <div class="rung a-fly" style="--d:6.5s"><b>80% margin</b><span class="barx hot a-wide" style="--d:6.8s;width:6rem"></span>5 &times; your cost</div>
                    <div class="rung a-fly" style="--d:13s"><b>90.9% margin</b><span class="barx max a-wide" style="--d:13.3s;width:10rem"></span>&asymp; 11 &times; &mdash; the most it stores</div>
                  </div>
                  ' . $panel('<div class="fg" style="margin-top:.2rem"><span class="lab">Default price-table margin %</span>'
                     . '<div class="inp focus"><span class="stack"><span class="a-mid" style="--d:18.5s;--d2:21.5s">95</span><span class="a-fade" style="--d:21.6s">90.90</span></span></div>'
                     . '<span class="blue"><span class="a-fade" style="--d:21.8s">&asymp; 998.90% markup (what the engine uses)</span></span></div>', 'a-rise', '--d:17s;margin-top:.8rem;max-width:20rem') . '
                  <div class="chips"><span class="chip a-pop" style="--d:10s">ceiling: 999% markup</span>
                    <span class="chip bad a-pop" style="--d:22s">type 95 &rarr; saves as 90.90, without a warning</span></div>
                </div>

                <!-- 9 — save -->
                <div class="sc" data-scene="9" data-len="' . $L(9) . '">
                  <div class="flash a-pop" style="--d:4.5s">Default margins saved.</div>
                  ' . $tabs('a-ring" style="--d:5.5s') . '
                  ' . $panel($radios() . $boxes(['markup', '100.00', '&asymp; 50.00% margin'], ['markup', '100.00', '&asymp; 50.00% margin'], false)
                     . '<div class="fact"><span class="btnp a-press a-ring" style="--d:2s">Save margins</span><div class="a-move" style="--fx:14rem;--fy:-5rem;--tx:3rem;--ty:.7rem;--d:.3s;--md:1.6s">' . $ptr . '</div></div>') . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:12s">every product</span><span class="chip a-pop" style="--d:12.8s">every option</span>
                    <span class="chip good a-pop" style="--d:15s">retail &amp; trade quotes</span>
                  </div>
                </div>

                <!-- 10 — one product different -->
                <div class="sc" data-scene="10" data-len="' . $L(10) . '">
                  <div class="crumbs"><span class="chip a-pop" style="--d:2s">Products</span><span class="arrow a-fade" style="--d:2.6s">&rarr;</span>
                    <span class="chip a-pop" style="--d:3.2s">25mm Venetian</span><span class="arrow a-fade" style="--d:3.8s">&rarr;</span><span class="chip a-pop" style="--d:4.4s">Pricing per system</span></div>
                  <div class="tbl a-rise" style="--d:5s">
                    <div><span>System</span><span>Markup %</span><span>Discount %</span></div>
                    <div><span>Standard</span><span class="inp">0</span><span class="inp">0</span></div>
                    <div><span>Motorised</span><span class="inp a-ring" style="--d:8s"><span class="stack"><span class="a-out" style="--d:8.5s">0</span>' . $type('150', 8.8, 3, .4) . '</span></span><span class="inp">0</span></div>
                  </div>
                  <p class="hintg a-fade" style="--d:13s;max-width:24rem"><b>Set markup to 0 to inherit the tenant default (100.00% &mdash; change on Settings).</b></p>
                  <div class="chips"><span class="chip good a-pop" style="--d:10s">its own rate wins</span>
                    <span class="chip a-pop" style="--d:16s">0 here = &ldquo;use the Settings default&rdquo;</span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> <b>Setup &rarr; Settings</b>, then click the <b>Quoting</b> tab. <b>Default margins</b> is the
             first panel on it. (Settings remembers the tab you used last, so next time it may open straight on Quoting.) In the
             screen&rsquo;s own words: &ldquo;Set your usual margin once and the engine applies it everywhere &mdash; no need to set
             markup on every product or option choice. You can still override at the product / option level when needed.&rdquo;
             These two boxes are your <b>fallback</b>: the rate used everywhere you haven&rsquo;t typed a different one.</p>
          <ul class="steps">
            <li><b>Enter your margins as</b> &mdash; two radio buttons, <b>Markup&nbsp;%</b> (where everyone starts) and
                <b>Margin&nbsp;%</b>. <b>Markup</b> is added on top of your cost: &pound;100 + 50% = <b>&pound;150</b>.
                <b>Margin</b> is the profit&rsquo;s slice of the selling price: a 50% margin means cost is half the sell, so
                &pound;100 cost = <b>&pound;200</b>. The screen says &ldquo;The customer price is identical either way &mdash; this
                only sets which number you type. You can switch any time without re-pricing anything.&rdquo;</li>
            <li><b>Default price-table markup&nbsp;%</b> (the word changes to &ldquo;margin&rdquo; if you pick Margin) &mdash; your
                everyday rate on the blind itself: &ldquo;Applied to every (product, system) that doesn&rsquo;t have an explicit
                value set on the product edit page.&rdquo; Pennies are fine (steps of 0.01); it can&rsquo;t go below 0.</li>
            <li><b>Default options &amp; extras markup&nbsp;%</b> &mdash; the same for add-ons: &ldquo;Uniform uplift on every
                option choice&rsquo;s price &mdash; fixed-&pound;, per-metre, and width-table modes all included. Only applies to new
                choices you add after this feature went live; existing choices stay at their entered prices.&rdquo;</li>
            <li><b>The blue line under each box</b> does the sum: on markup it reads &ldquo;&asymp; 50.00% margin&rdquo;; on margin
                &ldquo;&asymp; 100.00% markup (what the engine uses)&rdquo;. Behind the scenes the system only ever stores
                <b>markup</b> &mdash; what you type is converted on the way in and back on the way out, which is why flipping
                between the two is safe. A rate of 0 shows no blue line.</li>
            <li><b>Save margins</b> &mdash; the page reloads (still on the Quoting tab) with <b>&ldquo;Default margins
                saved.&rdquo;</b></li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>There&rsquo;s a ceiling, and it doesn&rsquo;t warn you.</b>
             Margin runs away near the top &mdash; 80% margin is five times your cost, 90% is ten times. The system stores at most
             <b>999% markup</b>, so about <b>90.9% margin</b> is as high as it goes. Type 95 and save, and the box comes back reading
             <b>90.90</b>.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Zero on a product means &ldquo;use the default&rdquo;.</b> On a
             product&rsquo;s <b>Pricing per system</b> panel the hint reads &ldquo;Set markup to 0 to inherit the tenant default
             (100.00% &mdash; change on Settings)&rdquo;. Any other figure there wins over this page for that system.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Supplier price lists skip the options rate on purpose.</b> On a
             product priced from a supplier&rsquo;s own list, their option surcharges already travel through your buying discount
             and your price-table rate, so <b>Default options &amp; extras</b> isn&rsquo;t added on top &mdash; that would charge
             the same profit twice.</div></div>
          <div class="oops"><b>Red banner instead of the form?</b> &ldquo;The default-margins columns aren&rsquo;t on this database
             yet&hellip;&rdquo;, or a save that says &ldquo;Could not save margins &mdash; has migrate_default_margins.php been
             run?&rdquo; &mdash; a one-off database update is missing. Not your job: tell whoever runs the platform. And if the grey
             explanations are missing, <b>Compact mode</b> is on; the blue line stays.</div>
          <p><b>Where the two numbers turn up:</b> a product&rsquo;s <b>Pricing per system</b> panel (its column is headed with the word
             you chose here); the price-table screen as <b>Our markup&nbsp;%</b> / <b>Our margin&nbsp;%</b>; and every retail and
             trade quote priced from your grids.</p>
          <p><b>Still not sure which one you want?</b> Have a play &mdash; both readings side by side, what the system stores, and
             where the ceiling bites.</p>
          <button type="button" class="calc-open" id="mmOpen">&#128200; Try it: markup vs margin, side by side</button>
          <div class="calc-modal" id="mmModal">
            <div class="calc-box" role="dialog" aria-modal="true" aria-label="Markup versus margin calculator">
              <button type="button" class="calc-x" id="mmClose" aria-label="Close">&times;</button>
              <h3>Markup vs margin &mdash; see the difference</h3>
              <div class="calc-row">
                <label>Your cost &pound; <input id="mmCost" type="number" value="100" min="0" step="1"></label>
                <label>The % you enter: <b id="mmPctlbl">50%</b> <input id="mmPct" type="range" min="0" max="99" step="0.1" value="50"></label>
              </div>
              <div class="mmchips" id="mmChips">
                <button type="button" class="mmchip" data-p="50"><b>50%</b>margin = 100% markup &middot; double it</button>
                <button type="button" class="mmchip" data-p="66.7"><b>66.7%</b>margin &asymp; 200% markup &middot; three times it</button>
                <button type="button" class="mmchip" data-p="80"><b>80%</b>margin = 400% markup &middot; five times it</button>
              </div>
              <div class="calc-cards">
                <div class="calc-card"><div class="cc-h">As markup</div><div class="cc-sell" id="mmMkSell">&pound;150.00</div><div class="cc-sub">profit <span id="mmMkProfit">&pound;50.00</span></div></div>
                <div class="calc-card cc-margin" id="mmMgCard"><div class="cc-h">As margin</div><div class="cc-sell" id="mmMgSell">&pound;200.00</div><div class="cc-sub">profit <span id="mmMgProfit">&pound;100.00</span></div><div class="cc-cap" id="mmCap">capped at 999% markup &mdash; saves back as 90.90% margin</div></div>
              </div>
              <div class="calc-bars"><div class="mmbar bar-mk"><span id="mmBarMk"></span></div><div class="mmbar bar-mg"><span id="mmBarMg"></span></div></div>
              <div class="calc-store" id="mmStore"></div>
              <div class="calc-note" id="mmNote"></div>
              <div class="calc-eq" id="mmEq"></div>
            </div>
          </div>',
        'js'      => <<<'JS'
(function(){
  var open = document.getElementById('mmOpen'), modal = document.getElementById('mmModal');
  if (!open || !modal) return;
  var q = function(id){ return document.getElementById(id); };
  var cost=q('mmCost'), pct=q('mmPct'), pctl=q('mmPctlbl'),
      mkSell=q('mmMkSell'), mkPro=q('mmMkProfit'), mgSell=q('mmMgSell'), mgPro=q('mmMgProfit'),
      mgCard=q('mmMgCard'), barMk=q('mmBarMk'), barMg=q('mmBarMg'),
      store=q('mmStore'), note=q('mmNote'), eq=q('mmEq'), chips=q('mmChips');
  function money(n){ if(!isFinite(n)) return 'off the chart'; return '£' + n.toLocaleString('en-GB',{minimumFractionDigits:2, maximumFractionDigits:2}); }
  // Mirrors _partials/pricing_basis.php: a typed MARGIN becomes the MARKUP the engine stores.
  function m2k(m){ m = Math.min(Math.max(m, 0), 99.99); return m <= 0 ? 0 : m * 100 / (100 - m); }
  function k2m(k){ k = Math.max(k, 0);                  return k <= 0 ? 0 : k * 100 / (100 + k); }
  function r2(n){ return (Math.round(n * 100) / 100).toFixed(2); }
  function calc(){
    var c = Math.max(0, parseFloat(cost.value)||0), p = parseFloat(pct.value)||0;
    pctl.textContent = r2(p).replace(/\.00$/, '') + '%';
    var mk = c*(1 + p/100), mkP = mk - c;
    var mg = (p>=100) ? Infinity : c/(1 - p/100), mgP = (mg===Infinity) ? Infinity : mg - c;
    mkSell.textContent = money(mk); mkPro.textContent = money(mkP);
    mgSell.textContent = money(mg); mgPro.textContent = money(mgP);
    var ref = Math.max(mk, isFinite(mg)?mg:mk, c) || 1;
    barMk.style.width = (mk/ref*100) + '%';
    barMg.style.width = ((isFinite(mg)?mg/ref:1)*100) + '%';
    // The save handler does max(0, min(999, markup)) — so a margin over ~90.90 is clamped.
    var asMarkup = m2k(p), capped = asMarkup > 999;
    mgCard.classList.toggle('danger', capped);
    mgCard.classList.toggle('steep', !capped && p >= 75);
    q('mmCap').style.display = capped ? 'block' : 'none';
    if (p <= 0){
      store.innerHTML = 'What the engine stores: <b>nothing at all</b> — a rate of 0 leaves the blue line empty.';
    } else if (capped){
      store.innerHTML = 'What the engine stores: type <b>' + r2(p) + '%</b> as a margin and it needs <b>' +
                        r2(asMarkup) + '%</b> markup — over the 999% limit, so it saves as <b>999%</b> and the box ' +
                        'comes back reading <b>' + r2(k2m(999)) + '</b>.';
    } else {
      store.innerHTML = 'What the engine stores: type <b>' + r2(p) + '%</b> as a margin and the screen shows ' +
                        '<b>≈ ' + r2(asMarkup) + '% markup (what the engine uses)</b>.';
    }
    note.innerHTML = 'Type <b>' + r2(p).replace(/\.00$/, '') + '</b> and mean <b>markup</b> → you charge <b>' + money(mk) +
                     '</b>. Mean <b>margin</b> → you charge <b>' + money(mg) + '</b>. Same number, very different price.';
    var txt = 'A <b>' + r2(p).replace(/\.00$/, '') + '% margin</b> is the same money as a <b>' +
              (p>=100 ? '∞' : r2(asMarkup)) + '% markup</b> — the customer pays the same either way.';
    if (isFinite(mg) && c>0 && p>=75){
      txt += '<span class="boom">⚠ At ' + r2(p).replace(/\.00$/, '') + '% margin the sell price is ' + (mg/c).toFixed(1) +
             '× your cost — margins run away as you near 100%.</span>';
    }
    eq.innerHTML = txt;
  }
  function show(){ modal.classList.add('open'); calc(); }
  function hide(){ modal.classList.remove('open'); }
  open.addEventListener('click', show);
  q('mmClose').addEventListener('click', hide);
  modal.addEventListener('click', function(e){ if (e.target === modal) hide(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hide(); });
  if (chips){
    chips.addEventListener('click', function(e){
      var b = e.target.closest('.mmchip'); if (!b) return;
      pct.value = b.getAttribute('data-p'); calc();
    });
  }
  cost.addEventListener('input', calc); pct.addEventListener('input', calc);
})();
JS,
        'script'  => $script,
];
