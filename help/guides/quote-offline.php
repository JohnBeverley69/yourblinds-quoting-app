<?php
declare(strict_types=1);

/**
 * Guide: quote-offline — "Working offline on a tablet" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Working with no signal on a tablet:
 *   - menu (⚙) → "Work offline: set up" → Set up for offline → status line
 *     (offline/engine.js render(): "Saving the product lists… 9 of 14",
 *     "✓ Ready — … prices kept on this tablet.", "✓ Works offline — prices from …",
 *     "14 products and their fabrics saved.", Update now / Turn off),
 *   - the saved copies of pages (sw.js, 14 days; its "No signal" page),
 *   - no signal: the orange bar and the "copy saved on this tablet" note
 *     (_partials/offline_guard.php),
 *   - Add blind priced on the tablet ("tablet price — checked when sent"),
 *     Save → "Kept on this tablet, not on the quote yet (N)" with Put back in
 *     the form / Delete (quote-builder/edit.php),
 *   - + New with no signal → "New quote · on this tablet · Ref …",
 *   - Email PDF + accept link with no signal → the confirm,
 *   - signal back: sent, numbered, "Priced differently…" + OK, checked, a
 *     held email (Send now / Don't send), and what still needs signal.
 * Every wording is copied from that code.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$f = static fn (string $label, string $box, string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib">' . $box . '</span></div>';

// The tablet frame: a narrow upright screen.
$tab = static fn (string $inner, string $cls = '', string $style = ''): string =>
    '<div class="tab ' . $cls . '" style="' . $style . '"><div class="cam"></div><div class="scr">' . $inner . '</div></div>';

// The orange no-signal bar at the bottom of the screen.
$offBar = static fn (string $txt = 'No signal &mdash; anything you save is kept on this tablet.', string $cls = '', string $style = ''): string =>
    '<div class="obar ' . $cls . '" style="' . $style . '">' . $txt . '</div>';

return [
        'aud'     => 'all',
        'section' => 'Quotes',
        'title'   => 'Working offline on a tablet',
        'eyebrow' => 'Quotes · No signal',
        'v'       => 2,
        'blurb'   => 'Set a tablet up once on WiFi, then measure, price and start quotes with no signal. Everything is kept on the tablet and goes up — checked by the server — when the signal is back.',
        'lede'    => 'Out on a job with <b>no signal</b>? A tablet you have <b>set up for offline</b> keeps working: it opens the
                      quotes you have looked at, prices every blind <b>on the tablet itself</b> (using the very same pricing the server
                      uses), starts <b>brand-new quotes</b>, and can even <b>queue the email</b> to the customer. Nothing is lost &mdash; it
                      all waits on the tablet and <b>sends itself</b> when the signal comes back, where the server checks every price
                      again. You set it up <b>once, on WiFi</b>, from the menu. This guide goes slowly, one idea per chapter.',
        'open'    => '/orders/index.php?scope=quotes&type=retail',
        'css'     => '
          .gd .sc{ position:relative; min-height:400px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.26rem .6rem; font-size:.68rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.ok{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .chips{ display:flex; flex-direction:column; align-items:flex-start; gap:.45rem; }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .stack{ display:grid; } .gd .stack > *{ grid-area:1/1; align-self:start; }
          .gd .lay{ display:grid; grid-template-columns:minmax(0,17rem) 1fr; gap:1.1rem; align-items:start; }

          /* the tablet */
          .gd .tab{ position:relative; border:10px solid #111827; border-top-width:16px; border-bottom-width:16px; border-radius:22px;
                    background:var(--surface); box-shadow:var(--gd-shadow); width:100%; max-width:17rem; min-height:20rem; overflow:hidden; }
          .gd .tab .cam{ position:absolute; top:-11px; left:50%; width:6px; height:6px; margin-left:-3px; border-radius:50%; background:#374151; }
          .gd .scr{ padding:.5rem .55rem 2.2rem; font-size:.66rem; color:var(--ink); min-height:20rem; position:relative; }
          .gd .scr .h{ font-weight:800; font-size:.78rem; margin-bottom:.35rem; }
          .gd .obar{ position:absolute; left:0; right:0; bottom:0; background:#ea580c; color:#fff; font-size:.6rem; font-weight:700; padding:.35rem .5rem; line-height:1.35; }
          .gd .obar.ok{ background:#16a34a; } .gd .obar.warn{ background:#b45309; }
          .gd .note{ background:#fef9c3; border:1px solid #fde047; color:#713f12; border-radius:7px; padding:.35rem .45rem; font-size:.6rem; line-height:1.4; margin-bottom:.4rem; }
          .gd .note b{ display:block; }
          .gd .note .row{ display:flex; gap:.3rem; flex-wrap:wrap; margin-top:.3rem; }
          .gd .note .btns{ font-size:.56rem; padding:.12rem .4rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.15rem; min-width:0; margin-bottom:.3rem; }
          .gd .fl{ font-size:.58rem; font-weight:700; color:var(--soft); }
          .gd .ib{ display:flex; align-items:center; min-height:22px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                   background:var(--surface); padding:0 .4rem; font-size:.64rem; color:var(--ink); overflow:hidden; white-space:nowrap; }
          .gd .g2{ display:grid; grid-template-columns:1fr 1fr; gap:.35rem; }
          .gd .gph{ color:var(--faint); }
          .gd .prv{ border-radius:7px; padding:.3rem .45rem; font-size:.62rem; border:1px solid var(--line); background:var(--panel); color:var(--faint); font-style:italic; margin:.2rem 0 .35rem; }
          .gd .prv.ok{ background:var(--good-wash); color:var(--ink); font-style:normal; }
          .gd .qsb{ display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; background:#1f2937; color:#fff; border-radius:7px; padding:.3rem .45rem; font-size:.62rem; font-weight:700; margin-bottom:.35rem; }
          .gd .qsb .pill{ background:#e5e7eb; color:#374151; border-radius:999px; padding:.02rem .4rem; font-size:.54rem; }
          .gd .qsb .tt{ margin-left:auto; }
          .gd .menu{ background:#1f2937; color:#e5e7eb; border-radius:8px; padding:.4rem; font-size:.62rem; }
          .gd .menu span{ display:block; padding:.25rem .35rem; border-radius:5px; }
          .gd .menu .on{ background:#334155; color:#fff; font-weight:700; }
          .gd .panel{ background:#0f172a; color:#e2e8f0; border-radius:7px; padding:.4rem .45rem; font-size:.6rem; line-height:1.45; margin-top:.3rem; }
          .gd .panel .btns{ font-size:.56rem; padding:.12rem .4rem; margin:.3rem .2rem 0 0; }
          .gd .gear{ position:absolute; right:.45rem; top:.4rem; width:1.5rem; height:1.5rem; border-radius:50%; background:#1f2937; color:#fff; display:grid; place-items:center; font-size:.78rem; }
          .gd .bar2{ height:6px; border-radius:999px; background:var(--panel); overflow:hidden; margin:.35rem 0; }
          .gd .bar2 i{ display:block; height:100%; background:var(--accent); width:100%; }
          .gd .lst{ border:1px solid var(--line); border-radius:7px; overflow:hidden; }
          .gd .lst div{ padding:.25rem .4rem; border-top:1px solid var(--line); font-size:.6rem; }
          .gd .lst div:first-child{ border-top:0; }
          .gd .lst .x{ color:var(--faint); text-decoration:line-through; }
          .gd .dlg{ position:absolute; z-index:5; left:.4rem; right:.4rem; top:30%; background:var(--surface); border:1px solid var(--line); border-radius:10px;
                    box-shadow:var(--gd-shadow); padding:.5rem .55rem; font-size:.6rem; line-height:1.45; }
          .gd .dlg .act{ display:flex; justify-content:flex-end; gap:.3rem; margin-top:.4rem; }
          .gd .cloud{ font-size:1.6rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:640px; }
            .gd .lay{ grid-template-columns:1fr; }
            .gd .tab{ max-width:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / on a tablet, no signal</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a class="on">Quotes</a><a>Orders</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="lay">
                    ' . $tab('<div class="h">Add blind</div>' . $f('Product', 'Roller Blind') . $f('Fabric', 'Sunset / Ivory')
                        . '<div class="prv ok"><b>&pound;77.00</b> per blind &middot; <i>tablet price &mdash; checked when sent</i></div>' . $offBar()) . '
                    <div class="chips"><span class="chip">&#128246; Set up once, on WiFi</span><span class="chip">&#128208; Measure and price with no signal</span>
                      <span class="chip ok">&#9729; It all goes up when the signal is back</span></div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; thirteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is -->
                <div class="sc" data-scene="1" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">No signal? Keep working</div>
                  <div class="lay">
                    ' . $tab('<div class="h">Quote BEV-2026-0042</div>'
                        . '<div class="prv ok a-pop" style="--d:9s"><b>&pound;77.00</b> per blind</div>'
                        . '<div class="note a-pop" style="--d:12.2s">Kept on this tablet, not on the quote yet (1)</div>'
                        . $offBar('No signal &mdash; anything you save is kept on this tablet.', 'a-fly', '--d:2.5s')) . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:5.4s">&#128203; Opens quotes you&rsquo;ve looked at</span>
                      <span class="chip a-pop" style="--d:8.9s">&#163; Prices every blind on the tablet</span>
                      <span class="chip a-pop" style="--d:12.2s">&#128190; Keeps what you save</span>
                      <span class="chip ok a-pop" style="--d:14.4s">&#9729; Sends it all when the signal is back</span>
                      <span class="chip a-pop" style="--d:18.8s">&#128241; Tablets only &mdash; easiest held upright</span>
                    </div>
                  </div>
                </div>

                <!-- 2 — set it up -->
                <div class="sc" data-scene="2" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Set it up once, on WiFi</div>
                  <div class="lay">
                    ' . $tab('<span class="gear a-ring" style="--d:2s">&#9881;</span><div class="h">Menu</div>'
                        . '<div class="menu a-rise" style="--d:3s"><span>Help &amp; guide</span><span>&#9790; Dark mode</span><span>&#8597; Compact mode</span>'
                        . '<span class="on a-ring" style="--d:5.5s">&#128246; Work offline: set up</span></div>'
                        . '<div class="panel a-rise" style="--d:6.5s"><span class="stack"><span class="a-out" style="--d:9.2s">Prices with no signal: not set up on this tablet.</span>'
                        . '<span class="a-mid" style="--d:9.3s;--d2:18.9s">Saving the product lists&hellip; <span class="swap"><span class="a-out" style="--d:12.5s">3</span><span class="a-mid" style="--d:12.5s;--d2:15.5s">9</span><span class="a-fade" style="--d:15.5s">14</span></span> of 14</span>'
                        . '<span class="a-fade" style="--d:19s">&#10003; Ready &mdash; 48,210 prices kept on this tablet.</span></span>'
                        . '<br><span class="btns a-press a-ring" style="--d:8.7s">Set up for offline</span></div>'
                        . '<div class="bar2 a-fade" style="--d:9.3s"><i class="a-wide" style="--d:9.5s"></i></div>') . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:3s">&#9881; Menu &rarr; scroll to the bottom</span>
                      <span class="chip a-pop" style="--d:10.8s">&#11015; About 20 MB, once</span>
                      <span class="chip a-pop" style="--d:14.3s">Prices + every product + all its fabrics</span>
                      <span class="chip ok a-pop" style="--d:20s">Do it on WiFi</span></div>
                  </div>
                </div>

                <!-- 3 — offline ready -->
                <div class="sc" data-scene="3" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Offline ready</div>
                  <div class="lay">
                    ' . $tab('<div class="menu"><span>&#9790; Dark mode</span><span>&#8597; Compact mode</span><span class="on a-ring" style="--d:0.5s">&#128246; Offline ready</span></div>'
                        . '<div class="panel a-rise" style="--d:2.6s">&#10003; Works offline &mdash; prices from Tue 7 Oct. 14 products and their fabrics saved.'
                        . '<br><span class="btns">Update now</span><span class="btns">Turn off</span></div>'
                        . '<div class="panel a-rise" style="--d:13.7s;background:#78350f">&#9888; Offline prices are from Mon 6 Oct &mdash; update them when you have signal.</div>') . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:4.5s">The date of your prices</span>
                      <span class="chip a-pop" style="--d:7s">How many products are saved</span>
                      <span class="chip a-pop" style="--d:8.7s">&#128260; Checks for new prices with signal</span>
                      <span class="chip bad a-pop" style="--d:14.5s">From an earlier day? It turns amber</span>
                      <span class="chip ok a-pop" style="--d:19.5s">Habit: open the app on WiFi before you go</span></div>
                  </div>
                </div>

                <!-- 4 — open quotes before you go -->
                <div class="sc" data-scene="4" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Open the quotes you&rsquo;ll need, before you go</div>
                  <div class="lay">
                    ' . $tab('<div class="h">No signal</div><div style="margin-bottom:.35rem">This page isn&rsquo;t saved on the tablet. These are:</div>'
                        . '<div class="lst"><div class="a-fly" style="--d:13.6s">Quote BEV-2026-0042 &mdash; Emma Fletcher <small style="color:var(--faint)">saved Tue 18:42</small></div><div class="a-fly" style="--d:14s">Quote BEV-2026-0039 &mdash; Tom Hughes <small style="color:var(--faint)">saved Tue 18:40</small></div><div class="a-fly" style="--d:14.4s">Quotes <small style="color:var(--faint)">saved Tue 18:39</small></div></div>'
                        . '<div style="margin-top:.4rem;color:var(--accent)">Start a new quote &middot; Go back</div>', 'a-rise', '--d:13.3s') . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:1s">&#128190; Every quote you open with signal is saved</span>
                      <span class="chip a-pop" style="--d:5s">&#9201; Kept for 14 days</span>
                      <span class="chip bad a-pop" style="--d:7.6s">Never opened on this tablet? It can&rsquo;t open with no signal</span>
                      <span class="chip ok a-pop" style="--d:17.5s">So open tomorrow&rsquo;s quotes tonight, on WiFi</span></div>
                  </div>
                </div>

                <!-- 5 — no signal -->
                <div class="sc" data-scene="5" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">In the house, with no signal</div>
                  <div class="lay">
                    ' . $tab('<div class="note a-drop" style="--d:6.6s">No signal &mdash; this is the copy saved on this tablet at 18:42. Anything you change is kept here and sent when the signal is back.</div>'
                        . '<div class="qsb">Quote BEV-2026-0042 <span class="pill">draft</span><span class="tt">Total &pound;520.00</span></div>'
                        . '<div class="h">Add blind</div>' . $f('Product', '<span class="gph">Choose product...</span>')
                        . $offBar('No signal &mdash; anything you save is kept on this tablet.', 'a-fly', '--d:3.8s')) . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:4.5s">&#128992; The orange bar = no signal</span>
                      <span class="chip a-pop" style="--d:8.5s">The yellow note says when it was saved</span>
                      <span class="chip a-pop" style="--d:14.3s">It&rsquo;s the copy from last time you opened it</span></div>
                  </div>
                </div>

                <!-- 6 — the tablet price -->
                <div class="sc" data-scene="6" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">The tablet price</div>
                  <div class="lay">
                    ' . $tab('<div class="h">Add blind</div>'
                        . '<div class="g2">' . $f('Product', 'Roller Blind') . $f('System', 'Bev Roller') . '</div>'
                        . $f('Fabric', 'Sunset / Ivory') . '<div class="g2">' . $f('Width (mm)', '<span class="a-type" style="--d:2s;--ts:4;--tt:.4s">1500</span>') . $f('Drop (mm)', '<span class="a-type" style="--d:3s;--ts:4;--tt:.4s">1600</span>') . '</div>'
                        . '<div class="stack"><div class="prv a-mid" style="--d:3.4s;--d2:6.4s">Getting prices ready on this tablet&hellip;</div>'
                        . '<div class="prv ok a-pop" style="--d:6.5s"><b>&pound;77.00</b> per blind &middot; <i class="a-ring" style="--d:8.5s">tablet price &mdash; checked when sent</i></div></div>'
                        . $offBar()) . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:2.3s">Worked out on the tablet itself</span>
                      <span class="chip a-pop" style="--d:4.5s">The same pricing the server uses</span>
                      <span class="chip a-pop" style="--d:11.9s">&#9203; First one: a few seconds</span>
                      <span class="chip ok a-pop" style="--d:18.2s">After that, instant</span></div>
                  </div>
                </div>

                <!-- 7 — kept on this tablet -->
                <div class="sc" data-scene="7" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Save &mdash; kept on this tablet</div>
                  <div class="lay">
                    ' . $tab('<div class="note a-drop" style="--d:2.5s"><b>Kept on this tablet, not on the quote yet (1)</b>Living Room &mdash; Roller Blind &mdash; 1500 &times; 1600 mm &middot; &pound;77.00 (tablet price) &middot; waiting to send'
                        . '<div class="row"><span class="btns a-ring" style="--d:15.5s">Put back in the form</span><span class="btns">Delete</span></div></div>'
                        . $f('Fabric', 'Sunset / Ivory') . '<div class="g2">' . $f('Room name', '<span class="swap"><span class="a-out" style="--d:7.7s">Living Room</span><span class="gph a-fade" style="--d:7.8s">Type or pick</span></span>') . $f('Width (mm)', '<span class="a-out" style="--d:7.7s">1500</span>') . '</div>'
                        . '<div class="prv">&#10003; Kept on this tablet &mdash; it&rsquo;ll be added when the signal is back (the server checks the price then).</div>'
                        . '<span class="btnp a-press" style="--d:1s">Save</span>' . $offBar()) . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:4.5s">A yellow list, with its tablet price</span>
                      <span class="chip a-pop" style="--d:8s">Product and fabric stay</span>
                      <span class="chip a-pop" style="--d:10s">Room and sizes clear &mdash; ready for the next window</span>
                      <span class="chip a-pop" style="--d:15.1s">Change it? Put back in the form</span></div>
                  </div>
                </div>

                <!-- 8 — a new quote -->
                <div class="sc" data-scene="8" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">A brand-new quote, with no signal</div>
                  <div class="lay">
                    <div class="stack">
                      ' . $tab('<div class="h">New quote</div>' . $f('Customer name *', '<span class="a-type" style="--d:3.5s;--ts:13;--tt:.9s">Aisha Khan</span>')
                          . '<span class="btnp a-press" style="--d:5.8s">Create quote</span>' . $offBar(), 'a-out', '--d:7.4s') . '
                      ' . $tab('<div class="qsb a-ring" style="--d:8.5s">New quote <span class="pill">on this tablet</span><span class="tt">Ref WTTF65</span></div>'
                          . '<div class="h">New quote</div><div style="color:var(--soft);margin-bottom:.4rem">Started with no signal. It gets its quote number when the signal is back &mdash; everything here is kept on this tablet until then.</div>'
                          . '<div class="h">Add blind</div>' . $f('Product', '<span class="gph">Choose product...</span>') . $offBar(), 'a-fade', '--d:7.5s') . '
                    </div>
                    <div class="chips">
                      <span class="chip a-pop" style="--d:1s">+ New &rarr; type the name &rarr; Create quote</span>
                      <span class="chip a-pop" style="--d:9.5s">A short reference for now</span>
                      <span class="chip ok a-pop" style="--d:13.1s">Its real quote number arrives with the signal</span>
                      <span class="chip a-pop" style="--d:17.4s">Add blinds exactly as before</span></div>
                  </div>
                </div>

                <!-- 9 — queue the email -->
                <div class="sc" data-scene="9" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Even the email can wait</div>
                  <div class="lay">
                    ' . $tab('<div class="h">Send to customer</div>' . $f('Recipient email', 'aisha.k@outlook.com')
                        . '<span class="btnp a-press" style="--d:1.8s;background:rgba(220,38,38,.5);color:#000">&#128231; Email PDF + accept link</span>'
                        . '<div class="dlg a-mid" style="--d:3s;--d2:10.2s">No signal. Send this quote to aisha.k@outlook.com automatically when the signal is back?<br><br>It goes once all its blinds have been sent. If the server prices any blind differently from the tablet, it waits for you to check the quote first.'
                        . '<div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:9.8s">OK</span></div></div><div class="dlg a-pop" style="--d:10.4s">&#10003; Kept on this tablet &mdash; the email goes when the signal is back.<div class="act"><span class="btnp">OK</span></div></div>' . $offBar()) . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:9.5s">Say OK &mdash; it waits on the tablet</span>
                      <span class="chip a-pop" style="--d:12.1s">Goes after the blinds</span>
                      <span class="chip bad a-pop" style="--d:15.1s">Price changed? It waits for you</span></div>
                  </div>
                </div>

                <!-- 10 — signal back -->
                <div class="sc" data-scene="10" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">The signal comes back</div>
                  <div class="lay">
                    ' . $tab('<div class="qsb">Quote <span class="swap"><span class="a-out" style="--d:8.5s">Ref WTTF65</span><span class="a-fade" style="--d:8.5s">BEV-2026-0044</span></span><span class="pill">draft</span></div>'
                        . '<div class="a-fade" style="--d:11.7s">' . $f('Customer name', 'Aisha Khan') . '</div><div class="h" style="margin-top:.3rem">Blinds (1)</div><div class="lst"><div class="a-fly" style="--d:15.6s">1 &middot; <b>Living Room</b> &mdash; Roller Blind &middot; &pound;77.00</div></div>'
                        . '<div class="stack" style="position:absolute;left:0;right:0;bottom:0"><div class="obar a-out" style="--d:1s;position:static">No signal &mdash; anything you save is kept on this tablet (3 saved changes waiting).</div>'
                        . '<div class="obar ok a-mid" style="--d:1.1s;--d2:20.3s;position:static">Signal back &mdash; sending 3 saved changes&hellip;</div></div>') . '
                    <div class="chips">
                      <span class="chip ok a-pop" style="--d:2s">&#128588; You don&rsquo;t press anything</span>
                      <span class="chip a-pop" style="--d:7.8s">New quotes first &mdash; they get their number</span>
                      <span class="chip a-pop" style="--d:11.7s">Then details, then blinds, then the email</span>
                      <span class="chip a-pop" style="--d:15.4s">The server prices every blind again</span></div>
                  </div>
                </div>

                <!-- 11 — priced differently -->
                <div class="sc" data-scene="11" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">If a price comes out different</div>
                  <div class="lay">
                    ' . $tab('<div class="note a-drop" style="--d:3.4s"><b>Priced differently when sent from the tablet &mdash; the quote uses the server&rsquo;s price:</b>'
                        . 'Living Room &mdash; Roller Blind &mdash; 1500 &times; 1600 mm: &pound;77.00 on the tablet &rarr; &pound;79.50 now.'
                        . '<div class="row"><span class="btns a-ring" style="--d:9.5s">OK, checked</span></div></div>'
                        . '<div class="note a-drop" style="--d:10.6s"><b>Kept on this tablet, not on the quote yet (1)</b>Email to aisha.k@outlook.com &middot; held: a price changed when the blinds were sent &mdash; check the quote, then Send now'
                        . '<div class="row"><span class="btns a-ring" style="--d:14.1s">Send now</span><span class="btns">Don&rsquo;t send</span></div></div>') . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:5s">Both figures, side by side</span>
                      <span class="chip a-pop" style="--d:7.5s">The quote uses the server&rsquo;s price</span>
                      <span class="chip bad a-pop" style="--d:11.5s">The email is held, not sent</span>
                      <span class="chip ok a-pop" style="--d:16.9s">A customer never gets a price you haven&rsquo;t seen</span></div>
                  </div>
                </div>

                <!-- 12 — what needs signal -->
                <div class="sc" data-scene="12" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">What still needs signal</div>
                  <div class="lay">
                    ' . $tab('<div class="qsb">Quote BEV-2026-0042 <span class="pill">draft</span></div>'
                        . '<div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-bottom:.4rem"><span class="btns a-press" style="--d:5.2s">View PDF</span><span class="btns">Download PDF</span><span class="btns a-press" style="--d:9.8s">Mark as sent</span></div>'
                        . '<div class="dlg a-mid" style="--d:5.6s;--d2:9.8s;top:42%">No signal &mdash; the PDF is made by the server, so it needs signal. Try again when you&rsquo;re back in signal.<div class="act"><span class="btnp">OK</span></div></div>'
                        . '<div class="dlg a-pop" style="--d:10s;top:42%">No signal &mdash; this needs signal. Nothing has been changed; try again when you&rsquo;re back in signal.<div class="act"><span class="btnp">OK</span></div></div>'
                        . $offBar()) . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:5.4s">&#128196; PDFs</span>
                      <span class="chip a-pop" style="--d:7.5s">Changing the status, deleting, deposits</span>
                      <span class="chip ok a-pop" style="--d:10.5s">Nothing changes &mdash; you stay where you are</span>
                      <span class="chip a-pop" style="--d:14.5s">&ldquo;Not saved on this tablet&rdquo;? With signal: Update now</span></div>
                  </div>
                </div>

                <!-- 13 — shared tablets, turning off -->
                <div class="sc" data-scene="13" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Shared tablets, and turning it off</div>
                  <div class="lay">
                    ' . $tab('<div class="menu"><span class="on">&#128246; Offline ready</span></div>'
                        . '<div class="panel">&#10003; Works offline &mdash; prices from Tue 7 Oct. 14 products and their fabrics saved.<br><span class="btns">Update now</span><span class="btns a-press a-ring" style="--d:10s">Turn off</span></div>'
                        . '<div class="dlg a-pop" style="--d:11s;top:55%">Stop keeping prices on this tablet? You can set it up again any time with signal.<div class="act"><span class="btns">Cancel</span><span class="btnp">OK</span></div></div>') . '
                    <div class="chips">
                      <span class="chip a-pop" style="--d:1s">&#128100; Your saved work is yours alone</span>
                      <span class="chip a-pop" style="--d:7.6s">Saved pages hold names and addresses</span>
                      <span class="chip a-pop" style="--d:12s">Turn off wipes the prices and saved pages</span>
                      <span class="chip ok a-pop" style="--d:16.2s">Blinds still waiting to send are kept</span></div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Tablets only.</b> Offline working is for the tablet a salesperson takes out &mdash; the <b>Work offline</b> button only appears on
             touch screens (or on a device already set up). It runs on very cheap tablets, and it is easiest held <b>upright</b>: the on-screen keyboard
             takes far less of the screen that way.</p>

          <p><b>Set it up once, on WiFi.</b></p>
          <ul class="steps">
            <li>Open the menu (the <b>&#9881;</b> button on a tablet) and scroll to the bottom. Under <b>Dark mode</b> and <b>Compact mode</b> you&rsquo;ll see
                <b>&#128246; Work offline: set up</b>. Tap it &mdash; it says <em>&ldquo;Prices with no signal: not set up on this tablet.&rdquo;</em></li>
            <li>Tap <b>Set up for offline</b>. It downloads about <b>20&nbsp;MB</b> once &mdash; the pricing, your price list, and every product with
                <b>all</b> of its fabrics &mdash; counting <em>&ldquo;Saving the product lists&hellip; 9 of 14&rdquo;</em> as it goes, then
                <em>&ldquo;&#10003; Ready &mdash; &hellip; prices kept on this tablet.&rdquo;</em></li>
            <li>The button now reads <b>Offline ready</b>. Tap it any time to see the status &mdash; <em>&ldquo;&#10003; Works offline &mdash; prices from Tue
                7 Oct. 14 products and their fabrics saved.&rdquo;</em> &mdash; with <b>Update now</b> and <b>Turn off</b>. The same status sits just above
                <b>Product</b> in <b>Add blind</b> on every quote.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Open the quotes you&rsquo;ll need while you still have signal.</b> The tablet saves a copy
             of <b>every quote you open with signal</b> (plus your quote list and a blank new-quote screen), and keeps them for <b>14 days</b>. A quote you
             have never opened on that tablet can&rsquo;t be opened with no signal &mdash; you get a <b>No signal</b> page (<em>&ldquo;This page isn&rsquo;t saved
             on the tablet. These are:&rdquo;</em>) listing the ones it <em>does</em> have, with <b>Start a new quote</b> and <b>Go back</b>.</div></div>

          <p><b>With no signal.</b> An orange bar appears at the bottom: <em>&ldquo;No signal &mdash; anything you save is kept on this tablet.&rdquo;</em> A
             quote opened now shows a yellow note at the top &mdash; <em>&ldquo;No signal &mdash; this is the copy saved on this tablet at 18:42. Anything you
             change is kept here and sent when the signal is back.&rdquo;</em> &mdash; because it is the copy from the last time you opened it.</p>
          <ul class="steps">
            <li><b>Add blinds as normal.</b> Product, system, band and fabric all work from the tablet&rsquo;s copy. The price is worked out <b>on the
                tablet</b> and marked <em>&ldquo;tablet price &mdash; checked when sent&rdquo;</em>. The first price after the signal drops can take a few
                seconds &mdash; <em>&ldquo;Getting prices ready on this tablet&hellip;&rdquo;</em> &mdash; after that every price is instant.</li>
            <li><b>Save</b> (or <b>Save and add another blind</b>): <em>&ldquo;&#10003; Kept on this tablet &mdash; it&rsquo;ll be added when the signal is back
                (the server checks the price then).&rdquo;</em> The blind goes into a yellow list above the form, <em>&ldquo;Kept on this tablet, not on the quote
                yet (1)&rdquo;</em>, with its tablet price. The form keeps the product, fabric and options and clears the room, sizes and notes, ready for the
                next window. Each kept blind has <b>Put back in the form</b> (to change it) and <b>Delete</b>.</li>
            <li><b>Customer details</b> you change are kept the same way.</li>
            <li><b>A new quote.</b> Tap <b>+ New</b>, type the customer&rsquo;s name and press <b>Create quote</b>. With no signal it opens <b>New quote</b>
                <b>on this tablet</b> with a short reference (e.g. <em>Ref WTTF65</em>) &mdash; <em>&ldquo;Started with no signal. It gets its quote number when
                the signal is back &mdash; everything here is kept on this tablet until then.&rdquo;</em> Add its blinds exactly as above.</li>
            <li><b>Emailing the quote.</b> Press <b>&#128231; Email PDF + accept link</b> and it asks: <em>&ldquo;No signal. Send this quote to &hellip;
                automatically when the signal is back? It goes once all its blinds have been sent. If the server prices any blind differently from the
                tablet, it waits for you to check the quote first.&rdquo;</em> Say <b>OK</b> and the email waits on the tablet &mdash; <em>&ldquo;&#10003; Kept on this tablet &mdash; the email goes when the signal is back.&rdquo;</em></li>
          </ul>

          <p><b>When the signal is back</b> it all goes up on its own &mdash; you don&rsquo;t press anything. The bar says <em>&ldquo;Signal back &mdash; sending 3
             saved changes&hellip;&rdquo;</em>. New quotes are created first (and get their number), then their details and blinds, then any email. The server
             <b>prices every blind again</b> as it adds it, and the quote uses the server&rsquo;s price.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>If a price comes out different, you&rsquo;re told &mdash; never silently.</b> The quote
             shows <em>&ldquo;Priced differently when sent from the tablet &mdash; the quote uses the server&rsquo;s price:&rdquo;</em> with both figures, until you
             press <b>OK, checked</b>. A queued email for that quote is <b>held</b> &mdash; <em>&ldquo;held: a price changed when the blinds were sent &mdash; check
             the quote, then Send now&rdquo;</em> &mdash; with <b>Send now</b> and <b>Don&rsquo;t send</b>, so a customer never gets a price you haven&rsquo;t
             seen.</div></div>

          <p><b>What needs signal.</b> Anything the server has to do there and then: <b>View PDF</b> / <b>Download PDF</b> (<em>&ldquo;No signal &mdash; the
             PDF is made by the server, so it needs signal.&rdquo;</em>), changing a quote&rsquo;s status, deleting, deposits and so on (<em>&ldquo;No signal &mdash;
             this needs signal. Nothing has been changed; try again when you&rsquo;re back in signal.&rdquo;</em>).</p>

          <div class="oops"><b>If something doesn&rsquo;t look right.</b>
             <b>&ldquo;Not saved on this tablet&rdquo;</b> in the System or Fabric box &mdash; that product&rsquo;s lists aren&rsquo;t on the tablet yet: with signal,
             tap <b>Work offline</b> &rarr; <b>Update now</b> and let it count to the end. <b>&ldquo;No signal &mdash; Update now needs WiFi or a signal. Turn
             flight mode off, reload the page, then try again.&rdquo;</b> &mdash; exactly that. <b>A quote looks out of date</b> &mdash; the tablet is showing the
             copy it saved last time: open it once <b>with signal</b> to refresh it. <b>Signed out while you were away?</b> The bar says <em>&ldquo;Signed out
             &mdash; sign in again to send &hellip;&rdquo;</em>; sign in and it carries on.</div>

          <p><b>Keeping prices current.</b> Whenever the tablet has signal it checks for new prices and fetches them. If the copy is from an earlier day the
             status turns amber &mdash; <em>&ldquo;&#9888; Offline prices are from Mon 6 Oct &mdash; update them when you have signal.&rdquo;</em> The simplest
             habit: open the app on WiFi before you go out.</p>

          <p><b>Shared tablets and privacy.</b> Everything kept on a tablet belongs to the person signed in &mdash; someone else signing in on the same tablet
             never sees or sends your saved work. Saved quote pages include customer names and addresses, which is why only tablets you <b>deliberately set
             up</b> keep them, only for <b>14 days</b>, and why <b>Turn off</b> (<em>&ldquo;Stop keeping prices on this tablet? You can set it up again any time
             with signal.&rdquo;</em>) wipes the price list and saved pages. Blinds still <em>waiting to send</em> are not wiped &mdash; that&rsquo;s unsent
             work.</p>',
        'script'  => [
            ['1', 'No signal? Keep working',         'Out on a job, with no signal? A tablet set up for offline keeps working. It opens the quotes you have already looked at. It prices every blind, on the tablet itself. It keeps everything you save. And when the signal comes back, it sends it all up for you. This is for tablets, and it is easiest with the tablet held upright.', 1],
            ['2', 'Set it up once, on WiFi',         'You set it up once, on WiFi. Open the menu, and scroll to the very bottom. Under Dark mode, tap Work offline, set up. Then tap Set up for offline. It downloads about twenty megabytes, just once. That is your prices, and every product with all of its fabrics. It counts as it goes, and then says Ready.', 2],
            ['3', 'Offline ready',                   'The button now says Offline ready. Tap it, and it tells you the date of your prices, and how many products are saved. Whenever the tablet has signal, it checks for new prices by itself. If your prices are from an earlier day, the line turns amber, to remind you. Best habit: open the app on WiFi before you go out.', 3],
            ['4', 'Open your quotes before you go',  'The tablet saves a copy of every quote you open while you have signal, and keeps it for fourteen days. A quote you have never opened on that tablet cannot be opened with no signal. You get a No signal page, listing the ones it does have. So open tomorrow\'s quotes tonight, on WiFi.', 4],
            ['5', 'In the house, with no signal',    'Now you are in a customer\'s house, with no signal. An orange bar at the bottom tells you. Open the quote, and a yellow note at the top says it is the copy saved on this tablet, and at what time. That is because it is the copy from the last time you opened it.', 5],
            ['6', 'The tablet price',                'Add a blind exactly as normal. The price is worked out on the tablet itself, with the same pricing the server uses. It is marked tablet price, checked when sent. The very first price can take a few seconds, while the tablet gets ready. It says so. After that, every price is instant.', 6],
            ['7', 'Kept on this tablet',             'Press Save, and the blind is kept on the tablet, in a yellow list above the form, with its tablet price. The form keeps the product and the fabric, and clears the room and sizes, ready for the next window. To change a kept blind, press Put back in the form.', 7],
            ['8', 'A brand-new quote',               'You can start a new quote with no signal, too. Press New, type the customer\'s name, and press Create quote. It opens a new quote, on this tablet, with a short reference for now. Its real quote number arrives when the signal comes back. Add the blinds exactly as before.', 8],
            ['9', 'Even the email can wait',         'Even the email can wait. Press Email PDF plus accept link, and it asks whether to send it automatically when the signal is back. Say OK, and it waits on the tablet. It goes once the blinds have been sent. And if any price changed, it waits for you.', 9],
            ['10', 'The signal comes back',          'When the signal comes back, you do not have to do a thing. The bar says it is sending your saved changes. New quotes are created first, and get their number. Then the details, then the blinds, then the email. As each blind goes up, the server prices it again, and the quote uses the server\'s price.', 10],
            ['11', 'If a price comes out different', 'If a price comes out different, you are told. The quote shows both figures, the tablet price and the server price, until you press OK, checked. And any email for that quote is held, not sent. Check the quote, then press Send now. So a customer never gets a price you have not seen.', 11],
            ['12', 'What still needs signal',        'A few things need signal, because the server does them there and then. Making a PDF, changing a quote\'s status, deleting, and deposits. With no signal, those buttons tell you, and nothing changes. And if a box says not saved on this tablet, tap Update now, next time you have signal.', 12],
            ['13', 'Shared tablets, and turning off', 'Your saved work belongs to you. If someone else signs in on the same tablet, they never see or send it. Saved pages hold customer names and addresses, so Turn off, in the same menu, wipes the prices and the saved pages. Blinds still waiting to send are kept, because that is unsent work.', 13],
        ],
];
