<?php
declare(strict_types=1);

/**
 * Guide: instaprice-quick — "InstaPrice — a price in thirty seconds" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors /instaprice/index.php (the signed-in quick-price screen: Product /
 * System, Band / Fabric search, options, Measurement unit, Dimensions &
 * quantity, the "Using …" echo, the price panel and its editable rates, the
 * Trade discount row, the cost eye beside Sell price (customer view, from
 * _partials/cost_reveal.php), Reset and "Turn into full quote →"), /instaprice/to-quote.php
 * (the conversion and its errors) and where it lands on /quote-builder/edit.php
 * (the amber "no customer yet" bar and the open customer form). Every label,
 * button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

/** A labelled control. $box is the inside of the control; $kind 'sel' adds a dropdown chevron. */
$f = static fn (string $label, string $box, string $kind = '', string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib ' . $kind . '">' . $box . '</span></div>';
$ph = static fn (string $t): string => '<span class="gph">' . $t . '</span>';

// The cost eye (copied from _partials/cost_reveal.php: an unlabelled eye icon beside Sell price).
$eye = '<span class="eye"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></span>';
/** The eye being tapped: ring at $r, presses at $p1 (and $p2 if given). One animation class per element, so nested. */
$eyeTap = static fn (string $r, string $p1, string $p2 = ''): string =>
    '<span class="eyew a-ring" style="--d:' . $r . 's"><span class="eyew a-press" style="--d:' . $p1 . 's">'
    . ($p2 !== '' ? '<span class="eyew a-press" style="--d:' . $p2 . 's">' . $eye . '</span>' : $eye) . '</span></span>';

/** The price panel (as a cost-viewer sees it with the eye tapped). $rows overrides individual values; each is inner HTML.
 *  'eye' => false for someone without cost access (no eye, no Mark up row). */
$panel = static function (array $v = []) use ($eye): string {
    $d = $v + [
        'trade' => '', 'price' => '&pound;45.00', 'disc' => '10.00', 'dprice' => '&pound;41.00',
        'mark' => '100.00', 'sell' => '&pound;77.00', 'each' => '', 'cls' => '', 'style' => '', 'markrow' => true, 'eye' => true,
    ];
    return '<div class="pp ' . $d['cls'] . '" style="' . $d['style'] . '">'
        . ($d['trade'] !== '' ? '<div class="pr trade">' . $d['trade'] . '</div>' : '')
        . '<div class="pr"><span class="l">Price</span><span class="v">' . $d['price'] . '</span></div>'
        . '<div class="pr ed"><span class="l">Discount %</span><span class="pct">' . $d['disc'] . '</span></div>'
        . '<div class="pr"><span class="l">Discounted price</span><span class="v">' . $d['dprice'] . '</span></div>'
        . ($d['markrow'] ? '<div class="pr ed"><span class="l">Mark up %</span><span class="pct">' . $d['mark'] . '</span></div>' : '')
        . '<div class="pr sell"><span class="l">Sell price' . ($d['eye'] ? ' ' . $eye : '') . '</span><span class="v">' . $d['sell'] . '</span></div>'
        . '<div class="tot">' . $d['each'] . '</div></div>';
};

// The form's top two rows, picked (used as a still in several scenes).
$topPicked = '
  <div class="g2">' . $f('Product', 'Roller Blind', 'sel') . $f('System', 'Bev Roller', 'sel') . '</div>
  <div class="g2">' . $f('Band', 'All bands', 'sel') . $f('Fabric', 'Sunset / Ivory') . '</div>';

$dims = static fn (string $w = '1200', string $d = '1400', string $q = '', string $echo = 'Using 1200 &times; 1400 mm'): string => '
  <div class="g2">' . '<div class="fg"><span class="fl">Measurement unit</span><span class="ib sel">Millimetres (mm)</span></div><div></div></div>
  <div class="fg"><span class="fl">Dimensions (mm) &amp; quantity</span>
    <div class="dims"><div class="fg"><span class="cap">Width</span><span class="ib">' . $w . '</span></div>
      <div class="fg"><span class="cap">Drop</span><span class="ib">' . $d . '</span></div>
      <div class="fg q"><span class="cap">Qty</span><span class="ib">' . ($q === '' ? '<span class="gph">Qty</span>' : $q) . '</span></div></div>
    <div class="echo">' . $echo . '</div></div>';

$btns = '<div class="fact"><span class="btnp">Turn into full quote &rarr;</span><span class="btns">Reset</span></div>';

return [
        'aud'     => 'all',
        'section' => 'Quotes',
        'title'   => 'InstaPrice — a price in thirty seconds',
        'eyebrow' => 'InstaPrice',
        'v'       => 2,
        'blurb'   => 'The whole quick-price flow: product, fabric, options, size — then the breakdown, the rates you can play with, quantities, when it won\'t price, and turning it into a real quote.',
        'lede'    => 'InstaPrice is the tool for <b>&ldquo;how much is that, roughly?&rdquo;</b> &mdash; on the phone, or standing in
                      somebody&rsquo;s front room. <b>No customer, no saving</b>: pick a product, size it, read the price. It uses the
                      <b>same pricing as the quote builder</b>, so the figure you read out is the real figure. And if they say yes,
                      <b>one button</b> turns it into a proper quote. Open it from the <b>&#9889; InstaPrice</b> button near the top of the menu.',
        'open'    => '/instaprice/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.68rem; color:var(--soft); margin:.1rem 0 .7rem; }

          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; white-space:nowrap; }
          .gd .btnp.off{ background:var(--panel); color:var(--faint); border:1px solid var(--line); }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .fact{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }

          /* form */
          .gd .frm{ display:flex; flex-direction:column; gap:.5rem; max-width:30rem; }
          .gd .g2{ display:grid; grid-template-columns:1fr 1fr; gap:.55rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.2rem; min-width:0; position:relative; }
          .gd .fl{ font-size:.66rem; font-weight:700; color:var(--soft); }
          .gd .cap{ font-size:.6rem; font-weight:600; color:var(--faint); }
          .gd .ib{ display:flex; align-items:center; min-height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .5rem; font-size:.74rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.sel{ padding-right:1.4rem; }
          .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.45rem; color:var(--faint); font-size:.7rem; }
          .gd .ib.dis{ background:var(--panel); }
          .gd .gph{ color:var(--faint); }
          .gd .rq{ color:#b91c1c; font-style:normal; }
          .gd .dims{ display:flex; gap:.5rem; } .gd .dims > .fg{ flex:1 1 0; } .gd .dims > .fg.q{ flex:0 0 4.5rem; }
          .gd .echo{ font-size:.68rem; color:var(--faint); margin-top:.3rem; }
          .gd .drop{ position:absolute; left:0; right:0; top:100%; z-index:5; margin-top:3px; background:var(--surface); border:1px solid var(--line);
                     border-radius:8px; box-shadow:var(--gd-shadow); font-size:.7rem; overflow:hidden; }
          .gd .drop div{ padding:.3rem .55rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .drop div:first-child{ border-top:0; }
          .gd .drop small{ display:block; color:var(--faint); font-size:.6rem; }
          .gd .drop .hit{ background:var(--accent-wash); }
          .gd .drop .og{ background:var(--panel); color:var(--faint); font-weight:800; font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.7rem; color:var(--ink); margin-right:.7rem; }
          .gd .ck > i{ width:14px; height:14px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:3px; display:grid; place-items:center;
                       font-style:normal; font-size:.7rem; font-weight:900; color:var(--accent); background:var(--surface); }
          .gd .ind{ margin-left:1rem; padding-left:.6rem; border-left:2px solid var(--line); }

          /* price panel */
          .gd .pp{ border:1px solid var(--line); border-radius:12px; background:var(--surface); padding:.55rem .8rem; font-size:.74rem; max-width:22rem; }
          .gd .pp.idle{ color:var(--faint); font-style:italic; }
          .gd .pp.err{ border-color:var(--err); color:var(--err); }
          .gd .pr{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding:.25rem 0; border-top:1px solid var(--line); }
          .gd .pr:first-child{ border-top:0; }
          .gd .pr .l{ color:var(--soft); } .gd .pr .v{ font-weight:600; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .pr.ed .l{ color:#b45309; font-weight:700; }
          :root[data-theme="dark"] .gd .pr.ed .l{ color:#fbbf24; }
          .gd .pct{ display:inline-flex; justify-content:flex-end; min-width:4.6rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    padding:.15rem .4rem; background:var(--surface); font-variant-numeric:tabular-nums; }
          .gd .pr.sell{ border-top:2px solid var(--border-strong,#c7ccd4); margin-top:.25rem; padding-top:.4rem; }
          .gd .pr.sell .l{ font-weight:800; color:var(--ink); font-size:.86rem; }
          .gd .pr.sell .v{ font-weight:800; font-size:1.15rem; color:var(--accent); }
          .gd .pr.trade .l, .gd .pr.trade .v{ color:#065f46; }
          :root[data-theme="dark"] .gd .pr.trade .l, :root[data-theme="dark"] .gd .pr.trade .v{ color:#34d399; }
          .gd .eye{ display:inline-flex; width:14px; height:14px; vertical-align:middle; margin:0 .15rem; color:var(--ink); opacity:.45; }
          .gd .eye svg{ width:100%; height:100%; }
          .gd .eyew{ display:inline-flex; vertical-align:middle; border-radius:5px; }
          .gd .ppstack{ display:grid; max-width:22rem; } .gd .ppstack > *{ grid-area:1/1; align-self:start; }
          .gd .tot{ font-size:.66rem; color:var(--faint); text-align:right; min-height:.4rem; }

          .gd .side2{ display:grid; grid-template-columns:1.1fr 1fr; gap:1rem; align-items:start; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.7rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .mnu{ border:1px solid var(--line); border-radius:9px; padding:.45rem; background:#1f2937; width:10rem; }
          .gd .mnu .nw{ display:block; text-align:center; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem; font-size:.72rem; font-weight:800; margin-bottom:.35rem; }
          .gd .mnu .ip{ display:block; text-align:center; background:#f59e0b; color:#1f2937; border-radius:7px; padding:.3rem; font-size:.72rem; font-weight:800; }
          .gd .mnu small{ display:block; color:#94a3b8; font-size:.55rem; text-transform:uppercase; letter-spacing:.06em; margin:.45rem 0 .1rem .2rem; }
          .gd .mnu span.it{ display:block; color:#cbd5e1; font-size:.66rem; padding:.12rem .25rem; }
          .gd .hdrrow{ display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; }
          .gd .nope{ display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.6rem; }
          .gd .nope span{ border:1px dashed var(--line); border-radius:999px; padding:.2rem .6rem; font-size:.68rem; color:var(--faint); text-decoration:line-through; }

          /* quote-builder landing */
          .gd .qsb{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; background:#1f2937; color:#fff; border-radius:9px; padding:.45rem .7rem; font-size:.74rem; font-weight:700; margin-bottom:.55rem; }
          .gd .qsb .pill{ background:#e5e7eb; color:#374151; border-radius:999px; padding:.05rem .5rem; font-size:.6rem; text-transform:lowercase; }
          .gd .qsb .tt{ margin-left:auto; }
          .gd .ncb{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; background:#fef3c7; border:1px solid #f59e0b; color:#78350f;
                    border-radius:9px; padding:.45rem .65rem; font-size:.72rem; margin-bottom:.55rem; }
          .gd .ncb .go{ margin-left:auto; background:#92600a; color:#fff; border-radius:6px; padding:.15rem .5rem; font-weight:700; font-size:.68rem; }
          .gd .cust{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); }
          .gd .cust .sm{ font-size:.74rem; font-weight:700; color:var(--ink); margin-bottom:.45rem; }
          .gd .cust .sm small{ font-weight:400; color:var(--faint); }
          .gd .ovr{ border:1px solid var(--line); border-radius:8px; padding:.4rem .6rem; font-size:.7rem; color:var(--soft); margin-top:.6rem; max-width:30rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:450px; }
            .gd .side2, .gd .two{ grid-template-columns:1fr; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / instaprice</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="pt">InstaPrice</div><div class="psub">Quick price &mdash; no customer details needed.</div>
                  <div class="side2">
                    <div class="frm">' . $topPicked . $dims('1200', '1400', '') . '</div>
                    <div>' . $panel() . $btns . '</div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is -->
                <div class="sc" data-scene="1" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">A real price, in thirty seconds</div>
                  <div class="hdrrow">
                    <div class="mnu a-fly" style="--d:8.5s"><span class="nw">+ New</span><span class="ip a-ring" style="--d:10s">&#9889; InstaPrice</span>
                      <small>Work</small><span class="it">Dashboard</span><span class="it">Calendar</span></div>
                    <div style="flex:1;min-width:14rem">
                      <div class="chip a-pop" style="--d:1.5s">&#128222; &ldquo;Roughly how much would that be?&rdquo;</div>
                      <div class="pt a-fade" style="--d:12s;margin-top:.8rem">InstaPrice</div>
                      <div class="psub a-fade" style="--d:12.3s">Quick price &mdash; no customer details needed.</div>
                      <div class="two" style="margin-top:.5rem">
                        <div class="card a-rise" style="--d:14.6s"><h4>InstaPrice</h4>&pound;77.00</div>
                        <div class="card a-rise" style="--d:15.5s"><h4>Quote builder</h4>&pound;77.00</div>
                      </div>
                      <span class="chip a-pop" style="--d:18.5s;margin-top:.6rem">Same pricing &mdash; the real figure, not a guess</span>
                    </div>
                  </div>
                </div>

                <!-- 2 — nothing kept -->
                <div class="sc" data-scene="2" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Nothing is kept</div>
                  <div class="nope">
                    <span class="a-pop" style="--d:2s">Customer</span><span class="a-pop" style="--d:2.6s">Room names</span>
                    <span class="a-pop" style="--d:3.2s">Notes</span><span class="a-pop" style="--d:5.5s">Saving</span>
                  </div>
                  <div class="side2" style="margin-top:.8rem">
                    <div class="frm">
                      <div class="g2">' . $f('Product', '<span class="swap"><span class="a-out" style="--d:14s">Roller Blind</span><span class="gph a-fade" style="--d:14.1s">Choose product&hellip;</span></span>', 'sel') . $f('System', '<span class="swap"><span class="a-out" style="--d:14s">Bev Roller</span><span class="gph a-fade" style="--d:14.1s">Choose product first</span></span>', 'sel') . '</div>
                      <div class="g2">' . $f('Band', 'All bands', 'sel') . $f('Fabric', '<span class="swap"><span class="a-out" style="--d:14s">Sunset / Ivory</span><span class="gph a-fade" style="--d:14.1s">Choose product first</span></span>') . '</div>
                      <div class="fact"><span class="btns a-press a-ring" style="--d:13.2s">Reset</span></div>
                    </div>
                    <div>
                      <div style="display:grid"><div class="a-out" style="--d:14s;grid-area:1/1">' . $panel() . '</div>
                        <div class="pp idle a-fade" style="--d:14.2s;grid-area:1/1;align-self:start">Choose a product and enter a size to see the price.</div></div>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:16.8s;margin-top:.7rem">Sell price is before VAT &mdash; VAT joins on a real quote</span>
                </div>

                <!-- 3 — product then system -->
                <div class="sc" data-scene="3" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Product first, then System</div>
                  <div class="frm">
                    <div class="g2">
                      <div class="fg"><span class="fl">Product</span><span class="ib sel a-ring" style="--d:0.5s"><span class="swap"><span class="gph a-out" style="--d:7.7s">Choose product&hellip;</span><span class="a-fade" style="--d:7.8s">Roller Blind</span></span></span>
                        <div class="drop a-mid" style="--d:2.5s;--d2:7.6s"><div class="og">Rollers</div><div class="hit">Roller Blind</div><div class="og">Verticals</div><div>Vertical Blind</div><div class="og">Other</div><div>Headrail</div></div></div>
                      <div class="fg"><span class="fl">System</span><span class="ib sel a-ring" style="--d:9.5s"><span class="swap"><span class="gph a-out" style="--d:9.2s">Choose product first</span><span class="a-fade" style="--d:9.3s">Bev Roller</span></span></span></div>
                    </div>
                  </div>
                  <div style="margin-top:7.5rem;display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:11.5s">Lands on the product&rsquo;s usual system</span>
                    <span class="chip a-pop" style="--d:16.5s">Price &amp; options can differ per system</span></div>
                  <div class="a-move" style="--fx:70%;--fy:90%;--tx:20%;--ty:4.6rem;--d:4.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 4 — band then fabric -->
                <div class="sc" data-scene="4" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Band, then Fabric</div>
                  <div class="frm">
                    <div class="g2">' . $f('Product', 'Roller Blind', 'sel') . $f('System', 'Bev Roller', 'sel') . '</div>
                    <div class="g2">
                      ' . $f('Band', 'All bands', 'sel', 'a-ring', '--d:1.5s') . '
                      <div class="fg"><span class="fl"><span class="swap"><span class="a-out" style="--d:20.5s">Fabric</span><span class="a-fade" style="--d:20.6s">Colour</span></span></span>
                        <span class="ib a-ring" style="--d:8.8s"><span class="swap"><span class="gph a-out" style="--d:11.1s">Type to search fabrics&hellip;</span><span class="a-mid" style="--d:11.2s;--d2:18.1s"><span class="a-type" style="--d:11.2s;--ts:3;--tt:.5s">Sun</span></span><span class="a-fade" style="--d:18.2s">Sunset / Ivory</span></span></span>
                        <div class="drop a-mid" style="--d:12.5s;--d2:18s"><div class="hit">Sunset / Ivory<small>Louvolite &middot; Code SW-104</small></div><div>Sunset / Charcoal<small>Louvolite &middot; Code SW-109</small></div><div>Sunbury / White<small>Decora &middot; Code SB-01</small></div></div></div>
                    </div>
                  </div>
                  <div style="margin-top:6.8rem;display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:4s">All bands = search everything</span>
                    <span class="chip a-pop" style="--d:20.8s">Labels follow the product: Fabric, Colour, Slat&hellip;</span></div>
                </div>

                <!-- 5 — order matters -->
                <div class="sc" data-scene="5" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Work down the screen, in order</div>
                  <div class="frm">
                    <div class="g2">' . $f('Product', 'Roller Blind', 'sel') . '<div class="fg"><span class="fl">System</span><span class="ib sel a-ring" style="--d:2.5s"><span class="swap"><span class="a-out" style="--d:4s">Bev Roller</span><span class="a-fade" style="--d:4s">Bev Roller Plus</span></span></span></div></div>
                    <div class="g2">' . $f('Band', 'All bands', 'sel') . '<div class="fg"><span class="fl">Fabric</span><span class="ib"><span class="swap"><span class="a-out" style="--d:6.8s">Sunset / Ivory</span><span class="gph a-fade" style="--d:6.9s">Type to search fabrics&hellip;</span></span></span></div></div>
                  </div>
                  <span class="chip a-pop" style="--d:10.1s;margin-top:.7rem">Fabric cleared &mdash; so it is never priced on the wrong system</span>
                  <div class="frm a-rise" style="--d:14.1s;margin-top:.9rem">
                    <div class="g2">' . $f('Product', 'Headrail', 'sel') . $f('System', 'Vogue', 'sel') . '</div>
                    <div class="g2" style="opacity:.35"><div class="fg"><span class="fl" style="text-decoration:line-through">Band</span></div><div class="fg"><span class="fl" style="text-decoration:line-through">Fabric</span></div></div>
                  </div>
                  <span class="chip a-pop" style="--d:18s;margin-top:.5rem">No fabric? The row disappears.</span>
                </div>

                <!-- 6 — options -->
                <div class="sc" data-scene="6" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Options &mdash; only what fits</div>
                  <div class="frm">
                    <div class="g2">
                      ' . $f('Control Side <em class="rq a-ring" style="--d:9.4s">*</em>', 'Left', 'sel', 'a-rise', '--d:2s') . '
                      ' . $f('Bottom Bar', 'Chained', 'sel', 'a-rise', '--d:2.6s') . '
                    </div>
                    <div class="fg a-rise" style="--d:12.1s"><span class="fl">Extras</span>
                      <div><span class="ck"><i><span class="a-pop" style="--d:13s">&#10003;</span></i>Child safety cleat</span><span class="ck"><i></i>Spring assist</span></div></div>
                    ' . $f('Fascia Options', 'LL Cassette', 'sel', 'a-rise', '--d:15.5s') . '
                    <div class="ind a-fly" style="--d:17s">' . $f('Fascia Sizing', 'Standard fascia', 'sel') . '</div>
                  </div>
                  <span class="chip a-pop" style="--d:19.3s;margin-top:.7rem">Expected one is missing? Check the fabric.</span>
                </div>

                <!-- 7 — size -->
                <div class="sc" data-scene="7" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">The size, and the line that checks it</div>
                  <div class="frm">
                    <div class="g2"><div class="fg"><span class="fl">Measurement unit</span><span class="ib sel a-ring" style="--d:1.5s">Millimetres (mm)</span></div><div></div></div>
                    <div class="fg"><span class="fl">Dimensions (mm) &amp; quantity</span>
                      <div class="dims">
                        <div class="fg"><span class="cap">Width</span><span class="ib a-ring" style="--d:8.7s"><span class="swap"><span class="a-mid" style="--d:9.5s;--d2:13.5s"><span class="a-type" style="--d:9.5s;--ts:4;--tt:.5s">1.2m</span></span><span class="a-fade" style="--d:13.5s">1.2m</span></span></span></div>
                        <div class="fg"><span class="cap">Drop</span><span class="ib"><span class="a-type" style="--d:11.8s;--ts:4;--tt:.5s">1400</span></span></div>
                        <div class="fg q"><span class="cap">Qty</span><span class="ib"><span class="gph">Qty</span></span></div>
                      </div>
                      <div class="echo a-ring" style="--d:18.6s"><span class="a-fade" style="--d:14.5s">Using 1200 &times; 1400 mm</span></div></div>
                  </div>
                  <div style="margin-top:.8rem;display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:5s">1400 &rarr; read in mm</span>
                    <span class="chip a-pop" style="--d:11s">1.2m &rarr; the unit you type wins</span>
                    <span class="chip a-pop" style="--d:22s">Spot a stray nought here</span></div>
                </div>

                <!-- 8 — costs stay hidden -->
                <div class="sc" data-scene="8" data-len="28">
                  <div class="sct a-fade" style="--d:.2s">Costs stay hidden &mdash; until you tap the eye</div>
                  <div class="side2">
                    <div class="frm">' . $dims('1200', '<span class="a-type" style="--d:4.6s;--ts:4;--tt:.5s">1400</span>', '', '<span class="a-fade" style="--d:5.3s">Using 1200 &times; 1400 mm</span>') . '</div>
                    <div class="ppstack">
                      <div class="pp idle a-out" style="--d:5.8s">Still need: drop.</div>
                      <div class="pp a-fade" style="--d:5.9s">
                        <div class="pr sell" style="border-top:0;margin-top:0;padding-top:.25rem"><span class="l">Sell price ' . $eyeTap('14', '21', '23.9') . '</span><span class="v a-ring" style="--d:8.4s">&pound;77.00</span></div>
                      </div>
                      <div class="a-mid" style="--d:21.3s;--d2:24.2s">' . $panel() . '</div>
                    </div>
                  </div>
                  <div style="margin-top:.8rem;display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:10.5s">&#128274; At first: just the Sell price</span>
                    <span class="chip a-pop" style="--d:15.8s">The customer may be watching the screen</span>
                    <span class="chip a-pop" style="--d:21.6s">Tap = show &middot; tap again = hide</span>
                    <span class="chip a-pop" style="--d:25.8s">Every page starts hidden</span></div>
                </div>

                <!-- 9 — breakdown -->
                <div class="sc" data-scene="9" data-len="28">
                  <div class="sct a-fade" style="--d:.2s">The price, as a sum you can follow</div>
                  <div class="side2">
                    <div class="pp">
                      <div class="pr a-ring" style="--d:2.6s"><span class="l">Price</span><span class="v">&pound;45.00</span></div>
                      <div class="pr ed a-ring" style="--d:6.5s"><span class="l">Discount %</span><span class="pct">10.00</span></div>
                      <div class="pr a-ring" style="--d:8.8s"><span class="l">Discounted price</span><span class="v">&pound;41.00</span></div>
                      <div class="pr ed a-ring" style="--d:10.1s"><span class="l">Mark up %</span><span class="pct">100.00</span></div>
                      <div class="pr sell"><span class="l">Sell price ' . $eye . '</span><span class="v a-ring" style="--d:12s">&pound;77.00</span></div>
                    </div>
                    <div>
                      <span class="chip a-pop" style="--d:3.6s">This blind, this size, with its options</span>
                      <div style="margin-top:.5rem"><span class="chip a-pop" style="--d:15.3s">Discount + mark up: the blind only</span></div>
                      <div style="margin-top:.5rem"><span class="chip a-pop" style="--d:18.5s">Options at their own price</span></div>
                      <div class="a-rise" style="--d:22.1s;margin-top:.7rem"><div class="fl" style="margin-bottom:.3rem">Without cost access &mdash; no eye</div>' . $panel(['markrow' => false, 'eye' => false]) . '</div>
                    </div>
                  </div>
                </div>

                <!-- 10 — haggling -->
                <div class="sc" data-scene="10" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">The amber rates are yours to play with</div>
                  <div class="side2">
                    <div class="pp">
                      <div class="pr"><span class="l">Price</span><span class="v">&pound;45.00</span></div>
                      <div class="pr ed"><span class="l">Discount %</span><span class="pct a-ring" style="--d:7.6s"><span class="swap"><span class="a-out" style="--d:8.6s">10.00</span><span class="a-type" style="--d:8.7s;--ts:5;--tt:.6s">15.00</span></span></span></div>
                      <div class="pr"><span class="l">Discounted price</span><span class="v"><span class="swap"><span class="a-out" style="--d:9.4s">&pound;41.00</span><span class="a-fade" style="--d:9.4s">&pound;39.00</span></span></span></div>
                      <div class="pr ed"><span class="l">Mark up %</span><span class="pct">100.00</span></div>
                      <div class="pr sell"><span class="l">Sell price ' . $eye . '</span><span class="v"><span class="swap"><span class="a-out" style="--d:9.7s">&pound;77.00</span><span class="a-fade" style="--d:9.7s">&pound;73.00</span></span></span></div>
                    </div>
                    <div>
                      <span class="chip a-pop" style="--d:3.2s">Starts on this product&rsquo;s saved rates</span>
                      <div style="margin-top:.5rem"><span class="chip a-pop" style="--d:11.8s">&#129309; Five percent more costs you &pound;4.00</span></div>
                      <div style="margin-top:.5rem"><span class="chip a-pop" style="--d:19.3s">One-off &mdash; never saved back</span></div>
                      <div style="margin-top:.5rem"><span class="chip a-pop" style="--d:21s">Change product &rarr; back to the usual rates</span></div>
                    </div>
                  </div>
                </div>

                <!-- 11 — quantity -->
                <div class="sc" data-scene="11" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">More than one</div>
                  <div class="side2">
                    <div class="frm"><div class="dims">
                      <div class="fg"><span class="cap">Width</span><span class="ib">1200</span></div>
                      <div class="fg"><span class="cap">Drop</span><span class="ib">1400</span></div>
                      <div class="fg q"><span class="cap">Qty</span><span class="ib a-ring" style="--d:1s"><span class="swap"><span class="gph a-out" style="--d:2.4s">Qty</span><span class="a-type" style="--d:2.5s;--ts:1;--tt:.2s">2</span></span></span></div></div>
                      <span class="chip a-pop" style="--d:4s;align-self:flex-start">Empty Qty counts as one</span></div>
                    <div class="pp">
                      <div class="pr"><span class="l">Price</span><span class="v">&pound;45.00</span></div>
                      <div class="pr ed"><span class="l">Discount %</span><span class="pct">10.00</span></div>
                      <div class="pr"><span class="l">Discounted price</span><span class="v">&pound;41.00</span></div>
                      <div class="pr ed"><span class="l">Mark up %</span><span class="pct">100.00</span></div>
                      <div class="pr sell"><span class="l">Sell price ' . $eye . '</span><span class="v"><span class="swap"><span class="a-out" style="--d:7s">&pound;77.00</span><span class="a-fade a-ring" style="--d:7s">&pound;154.00</span></span></span></div>
                      <div class="tot"><span class="a-fade" style="--d:11s">2 &times; &pound;77.00 each</span></div>
                    </div>
                  </div>
                </div>

                <!-- 12 — who sees what -->
                <div class="sc" data-scene="12" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Two lines depend on who you are</div>
                  <div class="two">
                    <div class="a-rise" style="--d:2.4s"><div class="fl" style="margin-bottom:.3rem">Allowed to see costs &mdash; eye tapped</div>' . $panel(['trade' => '<span class="l a-ring" style="--d:8.7s">Trade discount</span><span class="v">12.50% (&minus;&pound;5.71)</span>']) . '</div>
                    <div class="a-rise" style="--d:21.2s"><div class="fl" style="margin-bottom:.3rem">Without cost access &mdash; no eye</div>' . $panel(['markrow' => false, 'eye' => false]) . '</div>
                  </div>
                  <span class="chip a-pop" style="--d:12s;margin-top:.7rem">Green line = your buying discount, already inside the Price</span>
                </div>

                <!-- 13 — won\'t price -->
                <div class="sc" data-scene="13" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">When it won&rsquo;t price</div>
                  <div class="side2">
                    <div class="frm"><div class="dims">
                      <div class="fg"><span class="cap">Width</span><span class="ib a-ring" style="--d:15.8s">3200</span></div>
                      <div class="fg"><span class="cap">Drop</span><span class="ib">1400</span></div>
                      <div class="fg q"><span class="cap">Qty</span><span class="ib"><span class="gph">Qty</span></span></div></div>
                      <div class="echo">Using 3200 &times; 1400 mm</div></div>
                    <div>
                      <div class="pp err a-pop" style="--d:5.4s">No price for this size &mdash; 3200 &times; 1400 mm is outside this price table.</div>
                      <div class="fact"><span class="btnp off a-ring" style="--d:12.4s">Turn into full quote &rarr;</span><span class="btns">Reset</span></div>
                    </div>
                  </div>
                  <div style="margin-top:.8rem;display:flex;gap:.4rem;flex-wrap:wrap">
                    <span class="chip a-pop" style="--d:16.2s">1 &middot; Check the size and the unit</span>
                    <span class="chip a-pop" style="--d:18.8s">2 &middot; Still right? Extend the table under Products</span></div>
                </div>

                <!-- 14 — turn into full quote -->
                <div class="sc" data-scene="14" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Turn into full quote</div>
                  <div class="side2">
                    <div>' . $panel() . '<div class="fact"><span class="btnp a-press a-ring" style="--d:2.5s">Turn into full quote &rarr;</span><span class="btns">Reset</span></div></div>
                    <div>
                      <div class="card a-drop" style="--d:5.3s"><h4>&#128221; A real draft quote &mdash; BEV-2026-0042</h4>made straight away, with its own number</div>
                      <div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-top:.5rem">
                        <span class="chip a-pop" style="--d:10.5s">Product</span><span class="chip a-pop" style="--d:11s">Fabric</span><span class="chip a-pop" style="--d:11.5s">Options</span>
                        <span class="chip a-pop" style="--d:12s">Size</span><span class="chip a-pop" style="--d:12.5s">Qty</span></div>
                      <span class="chip bad a-pop" style="--d:15.4s;margin-top:.5rem">Press it to keep a price &mdash; not to see what happens</span>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:19.3s;margin-top:.8rem">No button? You need the <b>Create quotes</b> permission</span>
                  <div class="a-move" style="--fx:60%;--fy:95%;--tx:18%;--ty:13.6rem;--d:.6s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 15 — where you land -->
                <div class="sc" data-scene="15" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Where you land</div>
                  <div class="qsb a-rise" style="--d:.8s">Quote BEV-2026-0042 <span class="pill">draft</span><span class="tt">Total &pound;92.40</span></div>
                  <div class="ncb a-drop" style="--d:2.3s">&#9888; <span>This quote has <b>no customer yet</b> &mdash; add their details.</span><span class="go">Add customer &darr;</span></div>
                  <div class="cust a-rise" style="--d:4.5s">
                    <div class="sm">Customer details <small>&mdash; click to add the customer&rsquo;s contact info</small></div>
                    <div class="g2">' . $f('Customer name <em class="rq">*</em>', '<span class="a-type" style="--d:8s;--ts:13;--tt:1s">Emma Fletcher</span>', '', 'a-ring', '--d:7s') . $f('Email', '<span class="gph">Email</span>') . '</div>
                  </div>
                  <div class="ovr a-rise" style="--d:17.5s">' . $eyeTap('19.6', '20.4') . ' <span class="a-fade" style="--d:21s">&#9656; Adjust price for this blind</span> &nbsp;<span class="chip a-pop" style="--d:22s">Discount % (this blind)</span></div>
                  <span class="chip bad a-pop" style="--d:10.4s;margin-top:.6rem">Rates you typed over don&rsquo;t come across</span>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>InstaPrice</b> is for the moment somebody asks <em>&ldquo;go on then, roughly what would that cost?&rdquo;</em> Open it from the
             coloured <b>&#9889; InstaPrice</b> button near the top of the left-hand menu, just under <b>+ New</b> &mdash; everybody sees it. The page
             is headed <b>InstaPrice</b>, <em>&ldquo;Quick price &mdash; no customer details needed.&rdquo;</em> It runs the <b>same pricing as the quote
             builder</b>, so it is not a guess.</p>
          <p><b>What it deliberately hasn&rsquo;t got.</b> No customer, no rooms, no notes, no VAT line, and <b>no saving</b> &mdash; nothing you type
             here is recorded anywhere, and leaving the page loses it. The big <b>Sell price</b> is the <b>ex-VAT</b> selling figure; VAT only joins in
             once it is a real quote. <b>Reset</b> empties the lot (<em>&ldquo;Choose a product and enter a size to see the price.&rdquo;</em>).</p>

          <ul class="steps">
            <li><b>Product, then System.</b> The <b>Product</b> dropdown (<em>Choose product&hellip;</em>) groups your products under their
                <b>category</b> headings (anything without one sits under <b>Other</b>). The <b>System</b> box starts greyed out reading
                <b>&ldquo;Choose product first&rdquo;</b>; pick a product and it lands on that product&rsquo;s <b>default system</b>. Change it only if this
                job needs a different one &mdash; the price, the bands and the options can all differ per system.</li>
            <li><b>Band, then Fabric.</b> <b>Band</b> narrows the search: leave it on <b>&ldquo;All bands&rdquo;</b> to search the lot. <b>Fabric</b> is a
                <b>search box, not a dropdown</b> &mdash; type two or three letters and matches drop down with the supplier and code underneath
                (<em>Louvolite &middot; Code SW-104</em>); click one. Nothing matching says <b>&ldquo;No matching fabrics.&rdquo;</b>; a search that fails says
                <b>&ldquo;Could not search.&rdquo;</b> Clicking back into a picked fabric shows the whole list again, to switch colour. These two labels follow
                the product &mdash; the fabric one may read <b>Colour</b>, <b>Slat</b> or <b>Finish</b>, and the band one can be renamed too.</li>
            <li><b>Work down in order.</b> Changing the <b>System</b> or the <b>Band</b> after picking a fabric clears the fabric, so it can never be
                priced on the wrong system. Products with no fabric (a headrail, a track) hide the <b>Band and Fabric row entirely</b>.</li>
            <li><b>Options.</b> Only what <em>this</em> product, <em>this</em> system and <em>this</em> fabric can have. Sensible ones are <b>already
                chosen</b>; with no default the dropdown starts on <b>&ldquo;&mdash; Select &mdash;&rdquo;</b>. A red <b>*</b> means you must answer it.
                Some options are <b>tick-boxes</b>, some a <b>number box</b>, and some open a second option <b>indented underneath</b> once you pick the
                parent. If an option you expected isn&rsquo;t there, it is usually the <b>fabric</b>. Pure measurement boxes are left out here: they
                don&rsquo;t move the price.</li>
            <li><b>Unit, size and quantity.</b> <b>Measurement unit</b> starts on what your company works in (Settings &rarr; Measurements):
                <b>Millimetres (mm)</b>, <b>Centimetres (cm)</b>, <b>Metres (m)</b> or <b>Inches (in)</b>. Under <b>Dimensions (mm) &amp; quantity</b>
                are <b>Width</b>, <b>Drop</b> and <b>Qty</b>. A bare number is read in the chosen unit, but a unit typed on the end wins &mdash;
                <code>60&quot;</code>, <code>1.5m</code>, <code>150cm</code>. The grey line underneath shows what it is really using &mdash;
                <b>&ldquo;Using 1200 &times; 1400 mm&rdquo;</b>. <b>Qty</b> left empty counts as one.</li>
            <li><b>Read the breakdown.</b> Until everything is in, the panel says what is outstanding &mdash; <b>&ldquo;Still need: product, fabric,
                width, drop.&rdquo;</b>, shrinking as you go. Then it is a sum, top to bottom: <b>Price</b> (this blind at this size with its options)
                &rarr; <b>Discount %</b> &rarr; <b>Discounted price</b> &rarr; <b>Mark up %</b> &rarr; <b>Sell price</b>. The discount and mark-up apply to
                the blind; the options are added at their own price. With a quantity over one, the Sell price is the total and a grey line reads
                <b>&ldquo;2 &times; &pound;77.00 each&rdquo;</b>. If you are allowed to see costs, most of that sum <b>starts hidden</b> (see
                <b>Costs stay hidden</b> below).</li>
            <li><b>Turn it into a quote.</b> <b>Turn into full quote &rarr;</b> lifts the spec into the quote builder (details below).</li>
          </ul>

          <p><b>How the form changes shape</b> &mdash; nothing is broken, it is following the product: <b>width-only</b> products have no Drop box
             (<b>&ldquo;Using 1524 mm wide&rdquo;</b>); <b>per-slat</b> products have no Width box (<b>&ldquo;Using 900 mm drop (per slat)&rdquo;</b>);
             <b>per square metre</b> products show the area (<b>&ldquo;Area: 2.40 m&sup2;&rdquo;</b>, or <b>&ldquo;(billed at min 3.00 m&sup2;)&rdquo;</b> under
             the minimum).</p>

          <p><b>The amber rates are yours to play with.</b> <b>Discount %</b> and <b>Mark up %</b> arrive filled in from that <b>product and
             system&rsquo;s own saved rates</b> (Mark up falls back to your default in <b>Settings</b>; Discount with no saved rate starts at
             <b>0.00</b>). Type over either and the figures below <b>move as you type</b>. It is a <b>one-off</b>: never written back to the product,
             and it <b>springs back</b> the moment you change product or system. If your company works in <b>margin</b>, the second box reads
             <b>Margin %</b> instead.</p>

          <div class="heads"><span class="hi">&#128065;</span><div><b>Costs stay hidden.</b> Prices are often worked out with the customer looking
             at the screen. So if you are an admin or allowed to see costs, every time the page opens the panel shows <b>only the Sell price</b>, with a
             small, unlabelled <b>eye</b> icon beside it. <b>Price</b>, <b>Discount %</b>, <b>Discounted price</b>, <b>Mark up %</b> and the green
             <b>Trade discount</b> line stay hidden until you tap the eye; tap it again to hide them. It is never remembered &mdash; the next page
             starts hidden again. People without cost access have no eye: they see Price, Discount %, Discounted price and Sell price straight away.</div></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Who sees what.</b> The <b>Mark up %</b> row and the green <b>Trade discount</b> line
             are only shown to admins and people allowed to see costs (once the eye is tapped). The green line &mdash; e.g. <b>12.50% (&minus;&pound;5.71)</b> &mdash; is
             <b>your</b> standing buying discount from your supplier; it is <b>already inside the Price</b> and there for information only. The
             customer&rsquo;s discount is the amber <b>Discount %</b>.</div></div>

          <div class="oops"><b>What the panel says, and what to do about it:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>Still need: fabric, drop.</code> &mdash; nothing wrong; keep filling in.</li>
               <li><code>No price for this size &mdash; 3200 &times; 1400 mm is outside this price table.</code> &mdash; the band&rsquo;s grid stops short of that
                   size. Check the size and the unit; if they are right, the table needs extending under <b>Products</b>.</li>
               <li><code>No price table for &hellip;</code> &mdash; that band has <b>no grid at all</b> on that system yet.</li>
               <li><code>Could not get a price &mdash; try again.</code> &mdash; the connection blinked. Change something and it re-prices.</li>
             </ul>
             While the panel is red, <b>Turn into full quote</b> goes grey. Fix the figure and the price comes straight back.</div>

          <p><b>Roller blinds sharing one fascia.</b> Pick the multi-blind fascia choice on a roller and the <b>Width</b> box locks to a grey
             <b>&ldquo;multi blind&rdquo;</b>; a table headed <b>&ldquo;Blinds in this fascia&rdquo;</b> appears (<b>Blind &middot; Width &middot; Drop
             &middot; Price</b>, drop left blank for <b>&ldquo;same&rdquo;</b>) with a fit-check underneath, and the panel reads
             <b>&ldquo;3 blinds &middot; &pound;312.00 total&rdquo;</b>. On conversion each blind becomes its own line, grouped, with the fascia charged once.</p>

          <p><b>What &ldquo;Turn into full quote&rdquo; really does.</b> It <b>creates a genuine draft quote</b> straight away: the next quote number
             (<code>BEV-2026-0042</code>), your current VAT rate, the line re-priced with all its options, and the unit you were working in. It sends the
             product, system, fabric, options, size, quantity and unit &mdash; <b>not</b> any rate you typed over: the line is priced at that product and
             system&rsquo;s <b>standard</b> rates. Press it to <b>keep</b> a price; if you clicked it by mistake, delete the draft from your quotes list. If the
             server cannot manage it you come back to InstaPrice with a red bar &mdash; <code>Could not price that: &hellip;</code>,
             <code>Could not read the size &mdash; go back and try again.</code> or <code>Could not start the quote &mdash; please try again.</code> &mdash;
             and nothing is created.</p>

          <p><b>Where you land.</b> The quote builder opens with the bar <b>Quote BEV-2026-0042</b>, a <b>draft</b> pill and the total (VAT included, so
             higher than the ex-VAT Sell price). An amber bar says <b>&ldquo;This quote has no customer yet &mdash; add their details.&rdquo;</b> with
             <b>Add customer &darr;</b>, and the <b>Customer details</b> form is already open with <b>Customer name *</b> empty. Fill it in (or pick them in
             <b>Linked customer</b>) and press <b>Save details</b>. Until you do, the quotes list shows the placeholder name
             <b>&ldquo;Quick price (add customer)&rdquo;</b>. To put a haggled rate back, open the blind and use <b>Adjust price for this blind</b>
             (<b>Discount % (this blind)</b>, <b>Markup % (this blind)</b> &mdash; for admins and people allowed to see costs, and hidden there too until
             you tap the eye in the price line).</p>

          <p><b>No &ldquo;Turn into full quote&rdquo; button?</b> It is only shown to admins and users with the <b>Create quotes</b> permission
             (<b>Setup &rarr; Users</b>). Everybody else can price all day but cannot keep it.</p>',
        'script'  => [
            ['1', 'A real price, in thirty seconds', 'InstaPrice is for the moment someone asks, roughly how much would that be? On the phone, or standing in their front room. Open it from the InstaPrice button near the top of the menu, just under New. It uses the very same pricing as the quote builder. So the figure you read out is the real figure, not a guess.', 1],
            ['2', 'Nothing is kept',                 'InstaPrice keeps nothing. There is no customer, no room names and no notes, and nothing you type is saved anywhere. Leave the page, and it is gone. That is the point: it is quick. Reset empties every box, ready for the next one. And the price it shows is before VAT. VAT only joins in on a real quote.', 2],
            ['3', 'Product first, then System',      'Start with the Product box. Your products are grouped under their categories, so scroll to the right heading, and pick one. The System box next to it then wakes up, on that product\'s usual system. Only change it if this job needs a different one, because the price and the options can differ between systems.', 3],
            ['4', 'Band, then Fabric',               'Next, the fabric. Band is a price group. Leave it on All bands to search everything, or pick a band to narrow the list. The Fabric box is a search box. Type two or three letters, and the matches drop down, with the supplier and code underneath. Click the one you want. On some products, these boxes have other names, like Colour, or Slat.', 4],
            ['5', 'Work down in order',              'Work down the screen in order. If you change the system or the band after picking a fabric, the fabric is cleared, and you pick it again. That stops a fabric being priced on the wrong system. And on a product with no fabric at all, such as a headrail, the band and fabric boxes simply disappear.', 5],
            ['6', 'Options, only what fits',         'Now the options. You only see what this product, this system and this fabric can have, and sensible choices are already picked. A red star means you must answer it. Some options are tick boxes, some are a number to type, and some open a second option underneath. If one you expected is missing, it is usually the fabric.', 6],
            ['7', 'The size',                        'Then the size. The Measurement unit starts on whatever your company works in, so a plain number is read in that unit. But you can type the unit on the end, like one point two m, and that wins. Under the boxes, a grey line shows what it is really using. Using twelve hundred by fourteen hundred millimetres. Check it, to catch a stray nought.', 7],
            ['8', 'Costs stay hidden',               'Until everything is filled in, the panel says what is missing. Fill in the last box, and the price appears. If you are allowed to see costs, you see only the big Sell price at first, with a small eye beside it. The rest stays hidden, because the customer may be looking at the screen. Tap the eye, and the whole sum appears. Tap it again to hide it. Each new page starts hidden again.', 8],
            ['9', 'The price, as a sum',             'Here is the sum, from the top down. Price is this blind at this size, with its options. Then Discount percent, and the Discounted price. Then the Mark up, and the Sell price at the bottom, in big letters. The discount and the mark up apply to the blind, and the options are added at their own price. Without cost access there is no eye and no Mark up, and the rest shows straight away.', 9],
            ['10', 'The amber rates',                'The two amber rates are yours to play with. They start on the rates saved for this product and system. Type over them, and the sum below changes as you type. That is handy when someone is haggling, and you want to know what another five percent really costs you. It is a one-off. Nothing is saved back, and it resets when you change the product.', 10],
            ['11', 'More than one',                  'If they want more than one, type it in the Qty box. Leave it empty, and it counts as one. The big Sell price becomes the total for all of them. And a small grey line underneath shows the price of each one. Two times seventy seven pounds, each.', 11],
            ['12', 'Who sees what',                  'Two lines depend on who you are. If you are allowed to see costs, the eye also shows the Mark up box, and sometimes a green Trade discount line at the top. That green line is your own buying discount from your supplier. It is already inside the Price, so it is just for your information. People without cost access never see either of them, and have no eye.', 12],
            ['13', 'When it won\'t price',            'If a size is bigger than the price table goes, InstaPrice will not guess. The panel turns red, and tells you why: no price for this size, because it is outside the price table. The quote button goes grey until it is fixed. So check the size, and the unit, first. If they are right, the price table needs extending, under Products.', 13],
            ['14', 'Turn into full quote',           'When they say yes, click Turn into full quote. Be clear what that does. It makes a real draft quote, straight away, with its own quote number. It copies the product, fabric, options, size and quantity across. So press it to keep a price, not to see what happens. If you have no such button, you need the Create quotes permission.', 14],
            ['15', 'Where you land',                 'You land in the quote builder. An amber bar says the quote has no customer yet, and the customer form is open and empty, ready to fill in. One thing does not come across: any rate you typed over while haggling. The line uses the usual rates. To change one blind, open it, tap the small eye, and use Adjust price for this blind.', 15],
        ],
];
