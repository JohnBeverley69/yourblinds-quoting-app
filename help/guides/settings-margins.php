<?php
declare(strict_types=1);

/**
 * Guide: settings-margins
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors the "Default margins" section of /admin/settings.php (Quoting tab):
 * the pricing_basis radios, the two number boxes, their blue basis-hints, the
 * grey ui-hint helper lines and the single "Save margins" button. Every label,
 * hint and flash below is copied from that screen.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Default margins',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Set your profit once, here, instead of on every product — plus markup vs margin, with a live calculator.',
        'lede'    => 'Set your profit <b>once</b> and the pricing engine uses it everywhere, so you don&rsquo;t have to
                      type a margin onto every product and every option. There&rsquo;s one choice to make first &mdash;
                      do you want to type your profit as <b>markup</b> or as <b>margin</b>? Both give the customer the
                      same price; they&rsquo;re just two ways of writing the same money. Here&rsquo;s the whole screen,
                      step by step, with a calculator to play with.',
        'open'    => '/admin/settings.php#quoting',
        'css'     => '
          /* The settings tab strip, so the guide shows WHERE the panel lives. */
          .gd .tabrow{ display:flex; gap:.3rem; border-bottom:1px solid var(--line); margin-bottom:.85rem; flex-wrap:wrap; }
          .gd .tb{ font-size:.72rem; color:var(--soft); padding:.28rem .5rem; border-radius:7px 7px 0 0; }
          .gd .tb.on{ color:var(--ink); font-weight:700; box-shadow:inset 0 -2px 0 var(--accent); }
          .gd .stage[data-step="1"] .tb.on{ background:var(--accent-wash); }
          /* the real screen\'s grey ui-hint lines */
          .gd .ghint{ font-size:.64rem; color:var(--faint); line-height:1.5; margin:.3rem 0 .75rem; }
          .gd .fld .ghint{ margin:.3rem 0 0; }
          .gd .mlabel{ font-size:.78rem; color:var(--soft); font-weight:600; margin-bottom:.45rem; }
          .gd .mradios{ padding:.25rem .3rem; border-radius:8px; }
          .gd .stage[data-step="2"] .mradios, .gd .stage[data-step="5"] .mradios{ background:var(--accent-wash); box-shadow:0 0 0 3px var(--accent-wash); }
          /* Markup is selected by default (class "on" in the mock, exactly as the
             tenant default). From step 5 the narration CLICKS Margin %, so the
             selected dot moves across — and only then. */
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .rmk.on{ color:var(--soft); font-weight:400; }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .rmk.on .dot{ border-color:var(--border-strong,#c7ccd4); }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .rmk.on .dot::after{ display:none; }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .rmg{ color:var(--ink); font-weight:600; }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .rmg .dot{ border-color:var(--accent); }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .rmg .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          /* the live label word swap the real JS performs */
          .gd .wmg{ display:none; }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .wmk{ display:none; }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .wmg{ display:inline; }
          /* the blue span.basis-hint under each box (empty when the rate is 0) */
          .gd .mrates{ margin-bottom:.2rem; align-items:start; }
          .gd .mhint{ position:relative; min-height:1rem; font-size:.66rem; color:var(--accent); margin-top:.22rem; }
          .gd .mhint span{ position:absolute; left:0; top:0; white-space:nowrap; opacity:0; }
          /* Box A: typed at step 3 (markup), repainted at 5 (margin), clamped at 6. */
          .gd .stage:is([data-step="3"],[data-step="4"]) .fa .box .ph{ opacity:0; }
          .gd .stage:is([data-step="3"],[data-step="4"]) .fa .vmk, .gd .stage:is([data-step="3"],[data-step="4"]) .fa .hmk{ opacity:1; }
          .gd .stage[data-step="3"] .fa .vmk{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="3"] .fa .box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage:is([data-step="5"],[data-step="7"],[data-step="8"]) .fa .box .ph{ opacity:0; }
          .gd .stage:is([data-step="5"],[data-step="7"],[data-step="8"]) .fa .vmg, .gd .stage:is([data-step="5"],[data-step="7"],[data-step="8"]) .fa .hmg{ opacity:1; }
          .gd .stage[data-step="5"] .fa .vmg{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="6"] .fa .box .ph{ opacity:0; }
          .gd .stage[data-step="6"] .fa .box{ border-color:#f59e0b; box-shadow:0 0 0 3px color-mix(in srgb,#f59e0b 24%,transparent); }
          .gd .stage[data-step="6"] .v95, .gd .stage[data-step="6"] .h95{ animation:gdOut 3.4s ease-in-out forwards; }
          .gd .stage[data-step="6"] .v90, .gd .stage[data-step="6"] .h90{ animation:gdIn 3.4s ease-in-out forwards; }
          @keyframes gdOut{ 0%,40%{ opacity:1; } 52%,100%{ opacity:0; } }
          @keyframes gdIn{ 0%,40%{ opacity:0; } 52%,100%{ opacity:1; } }
          .gd .clampnote{ display:none; font-size:.64rem; line-height:1.45; color:#b45309; margin-top:.3rem; }
          :root[data-theme="dark"] .gd .clampnote{ color:#fbbf24; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .clampnote{ color:#fbbf24; } }
          .gd .stage[data-step="6"] .clampnote{ display:block; }
          /* Box B: typed at step 4 (markup), repainted with the basis from step 5 on. */
          .gd .stage[data-step="4"] .fb .box .ph{ opacity:0; }
          .gd .stage[data-step="4"] .fb .vmk, .gd .stage[data-step="4"] .fb .hmk{ opacity:1; }
          .gd .stage[data-step="4"] .fb .vmk{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="4"] .fb .box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .fb .box .ph{ opacity:0; }
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .fb .vmg,
          .gd .stage:is([data-step="5"],[data-step="6"],[data-step="7"],[data-step="8"]) .fb .hmg{ opacity:1; }
          .gd .stage[data-step="5"] .fb .vmg{ animation:gdRoll .8s ease-out both; }
          /* save + saved flash */
          .gd .stage[data-step="7"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="7"] .toast, .gd .stage[data-step="8"] .toast{ opacity:1; transform:none; }
          /* step 8: the form settles back and the "where it lands" strip appears */
          .gd .mform{ transition:opacity .3s; }
          .gd .stage[data-step="8"] .mform{ opacity:.42; }
          .gd .dstrip{ display:none; margin-top:.5rem; border:1px solid var(--line); border-radius:10px; background:var(--panel); padding:.55rem .75rem; font-size:.72rem; color:var(--soft); line-height:1.7; }
          .gd .stage[data-step="8"] .dstrip{ display:block; }
          .gd .dstrip b{ color:var(--ink); } .gd .dstrip i{ font-style:normal; color:var(--good); margin-right:.4rem; font-weight:700; }
          @media (prefers-reduced-motion:reduce){ .gd .v95, .gd .v90, .gd .h95, .gd .h90{ animation:none !important; } .gd .stage[data-step="6"] .v95, .gd .stage[data-step="6"] .h95{ opacity:0; } .gd .stage[data-step="6"] .v90, .gd .stage[data-step="6"] .h90{ opacity:1; } }
          /* interactive pop-out */
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
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1rem; }
          .gd .chip{ font:inherit; font-size:.72rem; line-height:1.3; cursor:pointer; text-align:left; border:1px solid var(--line); background:var(--panel); color:var(--soft); border-radius:9px; padding:.35rem .55rem; }
          .gd .chip:hover{ border-color:var(--accent); color:var(--accent-ink); }
          .gd .chip b{ display:block; color:var(--ink); font-size:.82rem; }
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
          /* "steep but perfectly legal" band (75%–90.9% margin): amber, not error red.
             Red is reserved for the genuine 999% cap, so a saveable rate never looks broken. */
          .gd .calc-card.steep{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .calc-card.steep .cc-sell{ color:#b45309; }
          :root[data-theme="dark"] .gd .calc-card.steep .cc-sell{ color:#fbbf24; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .calc-card.steep .cc-sell{ color:#fbbf24; } }
          .gd .calc-bars{ margin:.9rem 0 .3rem; display:flex; flex-direction:column; gap:.4rem; }
          .gd .bar{ height:12px; background:var(--line-2); border-radius:6px; overflow:hidden; }
          .gd .bar span{ display:block; height:100%; border-radius:6px; transition:width .15s; width:0; }
          .gd .bar-mk span{ background:var(--soft); }
          .gd .bar-mg span{ background:var(--accent); }
          .gd .calc-store{ margin-top:.8rem; border:1px dashed var(--line); border-radius:10px; padding:.55rem .7rem; font-size:.84rem; color:var(--soft); }
          .gd .calc-store b{ color:var(--accent); }
          .gd .calc-note{ font-size:.9rem; color:var(--ink); margin-top:.7rem; line-height:1.5; }
          .gd .calc-eq{ font-size:.88rem; color:var(--soft); margin-top:.5rem; }
          .gd .calc-eq .boom{ display:block; margin-top:.4rem; color:var(--err); font-weight:600; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Default margins saved.</div>
                <div class="tabrow">
                  <span class="tb">Company</span><span class="tb on">Quoting</span><span class="tb">Legal</span>
                  <span class="tb">Status colours</span><span class="tb">Suppliers</span>
                  <span class="tb">Accounting</span><span class="tb">Back up data</span>
                </div>
                <div class="mform">
                  <div class="card-t">Default margins</div>
                  <div class="ghint">Set your usual margin once and the engine applies it everywhere &mdash; no need to
                       set markup on every product or option choice. You can still override at the product / option level
                       when needed.</div>
                  <div class="mlabel">Enter your margins as</div>
                  <div class="mradios">
                    <span class="radio rmk on"><span class="dot"></span> Markup&nbsp;%</span>
                    <span class="radio rmg"><span class="dot"></span> Margin&nbsp;%</span>
                  </div>
                  <div class="ghint"><b>Markup</b> is added on top of your cost (cost&nbsp;+&nbsp;50%&nbsp;=&nbsp;sell).
                       <b>Margin</b> is the profit slice of the sell price (50%&nbsp;margin&nbsp;=&nbsp;cost is half the
                       sell). The customer price is identical either way &mdash; this only sets which number you type.
                       You can switch any time without re-pricing anything.</div>
                  <div class="frow mrates">
                    <div class="fld fa">
                      <label>Default price-table <span class="wmk">markup</span><span class="wmg">margin</span> %</label>
                      <div class="box">
                        <span class="ph">0.00</span>
                        <span class="val vmk">100</span>
                        <span class="val vmg">50.00</span>
                        <span class="val v95">95</span>
                        <span class="val v90">90.90</span>
                      </div>
                      <div class="mhint">
                        <span class="hmk">&asymp; 50.00% margin</span>
                        <span class="hmg">&asymp; 100.00% markup (what the engine uses)</span>
                        <span class="h95">&asymp; 1900.00% markup (what the engine uses)</span>
                        <span class="h90">&asymp; 998.90% markup (what the engine uses)</span>
                      </div>
                      <div class="clampnote">&#9888; 95% margin needs 1900% markup &mdash; capped at 999%, so it comes
                           back as 90.90.</div>
                      <div class="ghint">Applied to every (product, system) that doesn&rsquo;t have an explicit value set
                           on the product edit page.</div>
                    </div>
                    <div class="fld fb">
                      <label>Default options &amp; extras <span class="wmk">markup</span><span class="wmg">margin</span> %</label>
                      <div class="box">
                        <span class="ph">0.00</span>
                        <span class="val vmk">100</span>
                        <span class="val vmg">50.00</span>
                      </div>
                      <div class="mhint">
                        <span class="hmk">&asymp; 50.00% margin</span>
                        <span class="hmg">&asymp; 100.00% markup (what the engine uses)</span>
                      </div>
                      <div class="ghint">Uniform uplift on every option choice&rsquo;s price &mdash; fixed-&pound;,
                           per-metre, and width-table modes all included. Only applies to new choices you add after this
                           feature went live; existing choices stay at their entered prices.</div>
                    </div>
                  </div>
                  <div class="save">Save margins</div>
                </div>
                <div class="dstrip">
                  <div><i>&check;</i><b>Products</b> &mdash; every product &amp; system with no rate of its own</div>
                  <div><i>&check;</i><b>Options &amp; extras</b> &mdash; every new choice you add</div>
                  <div><i>&check;</i><b>Quotes</b> &mdash; retail and trade accounts alike</div>
                </div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Settings &rarr; Quoting &rarr; Default margins.</b>
                  <b class="c2"><span class="n">2</span> Markup or margin? Markup is already chosen.</b>
                  <b class="c3"><span class="n">3</span> Your usual rate &mdash; the blue line shows the other reading.</b>
                  <b class="c4"><span class="n">4</span> Same again for options &amp; extras.</b>
                  <b class="c5"><span class="n">5</span> Click Margin&nbsp;% &mdash; same money, written differently.</b>
                  <b class="c6 err"><span class="n">6</span> Past about 90.9% margin it quietly caps.</b>
                  <b class="c7 good"><span class="n">7</span> Save margins &mdash; &ldquo;Default margins saved.&rdquo;</b>
                  <b class="c8"><span class="n">8</span> In force everywhere &mdash; retail and trade.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar, then click the <b>Quoting</b> tab along the top.
             <b>Default margins</b> is the first panel on it. One thing to expect: if you arrive here by clicking the
             &ldquo;change on Settings&rdquo; link on a product page, the app puts you on whichever tab you had open
             last &mdash; usually <b>Company</b>. Nothing has gone wrong. Just click <b>Quoting</b>.</p>
          <p>This screen is where you set your profit <b>once</b>. In its own words: &ldquo;Set your usual margin once
             and the engine applies it everywhere &mdash; no need to set markup on every product or option choice. You
             can still override at the product / option level when needed.&rdquo; So these two boxes are your
             <b>fallback</b>: the rate used everywhere you haven&rsquo;t typed a different one.</p>
          <ul class="steps">
            <li><b>Enter your margins as</b> &mdash; two round radio buttons, <b>Markup&nbsp;%</b> and
                <b>Margin&nbsp;%</b>. Markup is the one that&rsquo;s already ticked, and that&rsquo;s where everybody
                starts. <b>Markup</b> is added on top of what you paid: cost &pound;100 + 50% = <b>&pound;150</b>.
                <b>Margin</b> is the profit slice of the price you charge: a 50% margin means your cost is half the
                sell, so &pound;100 cost = <b>&pound;200</b>. Don&rsquo;t panic about picking wrong &mdash; the screen
                itself says &ldquo;The customer price is identical either way &mdash; this only sets which number you
                type. You can switch any time without re-pricing anything.&rdquo;</li>
            <li><b>Default price-table markup&nbsp;%</b> (the word changes to &ldquo;margin&rdquo; if you tick Margin) &mdash;
                your everyday rate on the blind itself. It is &ldquo;Applied to every (product, system) that
                doesn&rsquo;t have an explicit value set on the product edit page.&rdquo; It takes pennies as well as
                whole numbers (steps of 0.01), won&rsquo;t go below 0, and &mdash; like the options box next to it
                &mdash; carries a ceiling of <b>999</b>. That is the same 999 the clamp further down turns on. The form
                is marked <code>novalidate</code>, so your browser won&rsquo;t stop you typing 1500 into it; it&rsquo;s
                the save itself that quietly trims anything above 999 back down to 999.</li>
            <li><b>Default options &amp; extras markup&nbsp;%</b> &mdash; the same job for everything you bolt on:
                &ldquo;Uniform uplift on every option choice&rsquo;s price &mdash; fixed-&pound;, per-metre, and
                width-table modes all included.&rdquo; Read the rest of that line carefully, because it surprises
                people: it &ldquo;Only applies to new choices you add after this feature went live; existing choices
                stay at their entered prices.&rdquo; Your existing options keep the prices you typed into them.</li>
            <li><b>The blue line under each box</b> is doing you a favour. On markup it reads
                &ldquo;&asymp; 50.00% margin&rdquo;; on margin it reads &ldquo;&asymp; 100.00% markup (what the engine
                uses)&rdquo;. That last bit is the whole secret of this screen: behind the scenes the engine only ever
                works in <b>markup</b>. Whatever you type is converted on the way in and converted back on the way out
                &mdash; which is exactly why flipping between the two is safe, and why a rate of 0 shows no blue line
                at all.</li>
            <li><b>Save margins</b> &mdash; the one and only button here. The page reloads and tells you
                <b>&ldquo;Default margins saved.&rdquo;</b> Don&rsquo;t be thrown if it drops you back onto
                <b>Company</b> with that green message sitting at the top: saving sends you to the plain
                <code>/admin/settings.php</code> address with no tab named in it, so you land on whichever tab the app
                remembers &mdash; the very same quirk as above. Click <b>Quoting</b> and your new figures are sitting
                there, saved.</li>
            <li><b>If you see a red banner instead of the form</b>, this database hasn&rsquo;t had its one-off
                upgrade: &ldquo;The default-margins columns aren&rsquo;t on this database yet &mdash; run
                <code>/migrate_default_margins.php</code> &hellip; (super-admin) to enable this section.&rdquo; The same
                goes for a save that comes back &ldquo;Could not save margins &mdash; has migrate_default_margins.php
                been run?&rdquo; Neither is your fault or your job &mdash; that&rsquo;s one for whoever runs the
                platform.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Zero does not mean &ldquo;no profit&rdquo;:</b>
             over on a product&rsquo;s <b>Pricing per system</b> panel, 0 means <b>inherit</b>. Its own hint says
             &ldquo;Set markup to 0 to inherit the tenant default (100.00% &mdash; change on Settings)&rdquo;, and
             saving 0 there deletes the product&rsquo;s override altogether. So if you genuinely want one product sold
             at cost, you can&rsquo;t do it by typing 0 on that product &mdash; set the <b>Settings</b> default to 0
             instead and give every other product its own rate.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>There&rsquo;s a ceiling, and it doesn&rsquo;t warn
             you:</b> margin runs away near the top &mdash; 80% margin is five times your cost, 90% is ten times. The
             engine stores markup and refuses anything over 999%, so a margin of about <b>90.9%</b> is as high as it
             goes. Type 95, press Save, and the box comes back reading <b>90.90</b>. No error message, just a different
             number to the one you typed. If a figure you saved looks odd, that&rsquo;s why.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Supplier price lists ignore the options rate on
             purpose:</b> on a product priced from a supplier&rsquo;s own list, the <b>Default options &amp; extras</b>
             rate is deliberately skipped. Their surcharges (taped +20%, no valance &minus;&pound;1.50 and so on) are
             part of what you buy, so they already travel through your buying discount and your price-table rate.
             Marking them up here as well would charge for the same profit twice.</div></div>
          <div class="oops"><b>Screen looks bare?</b> If all you can see is two number boxes with no grey explanation
             under them, you have <b>Compact mode</b> turned on &mdash; it hides every hint line on every screen,
             including the four on this one. The blue &ldquo;&asymp;&rdquo; line stays put. Turn Compact off and the
             wording comes back.</div>
          <p><b>Where these two numbers turn up:</b> on a product&rsquo;s <b>Pricing per system</b> panel, whose column
             is headed with whichever word you chose here; on the price-table screen as <b>Our markup&nbsp;%</b> next to
             <b>Buying discount&nbsp;%</b>; in <b>Adjust price for this blind</b> while you&rsquo;re building a quote;
             in <b>InstaPrice</b>; and on the Dashboard&rsquo;s <b>Gross profit</b> panel &mdash; &ldquo;Equivalent to
             your markup &amp; discount turned into pounds.&rdquo; They set the floor under <b>trade</b> prices too: a
             trade-account quote is priced from the same grid as a retail one, and the account&rsquo;s own discount
             comes off the final sell on the <b>base blind only</b> &mdash; options are charged at the price they&rsquo;re
             set at.</p>
          <p><b>Still not sure which one you want?</b> Have a play. This shows both readings side by side, tells you
             what the engine will store, and shows you where the ceiling bites.</p>
          <button type="button" class="calc-open" id="mmOpen">&#128200; Try it: markup vs margin, side by side</button>
          <div class="calc-modal" id="mmModal">
            <div class="calc-box" role="dialog" aria-modal="true" aria-label="Markup versus margin calculator">
              <button type="button" class="calc-x" id="mmClose" aria-label="Close">&times;</button>
              <h3>Markup vs margin &mdash; see the difference</h3>
              <div class="calc-row">
                <label>Your cost &pound; <input id="mmCost" type="number" value="100" min="0" step="1"></label>
                <label>The % you enter: <b id="mmPctlbl">50%</b> <input id="mmPct" type="range" min="0" max="99" step="0.1" value="50"></label>
              </div>
              <div class="chips" id="mmChips">
                <button type="button" class="chip" data-p="50"><b>50%</b>margin = 100% markup &middot; double it</button>
                <button type="button" class="chip" data-p="66.7"><b>66.7%</b>margin &asymp; 200% markup &middot; three times it</button>
                <button type="button" class="chip" data-p="80"><b>80%</b>margin = 400% markup &middot; five times it</button>
              </div>
              <div class="calc-cards">
                <div class="calc-card"><div class="cc-h">As markup</div><div class="cc-sell" id="mmMkSell">&pound;150.00</div><div class="cc-sub">profit <span id="mmMkProfit">&pound;50.00</span></div></div>
                <div class="calc-card cc-margin" id="mmMgCard"><div class="cc-h">As margin</div><div class="cc-sell" id="mmMgSell">&pound;200.00</div><div class="cc-sub">profit <span id="mmMgProfit">&pound;100.00</span></div><div class="cc-cap" id="mmCap">capped at 999% markup &mdash; saves back as 90.90% margin</div></div>
              </div>
              <div class="calc-bars"><div class="bar bar-mk"><span id="mmBarMk"></span></div><div class="bar bar-mg"><span id="mmBarMg"></span></div></div>
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
    // Red = the genuine 999% cap ONLY. 80% margin is a legal rate that saves
    // fine (400% markup), so the steep-but-legal band gets the amber treatment
    // that matches the ⚠ line in .calc-eq instead of looking like an error.
    mgCard.classList.toggle('danger', capped);
    mgCard.classList.toggle('steep', !capped && p >= 75);
    q('mmCap').style.display = capped ? 'block' : 'none';
    // What the engine actually stores — worded exactly as the blue line on the screen.
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
      var b = e.target.closest('.chip'); if (!b) return;
      pct.value = b.getAttribute('data-p'); calc();
    });
  }
  cost.addEventListener('input', calc); pct.addEventListener('input', calc);
})();
JS,
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Settings, Quoting tab, Default margins panel.',
                     'Settings, then the Quoting tab. Default margins is the first panel. This is where you set your profit once, so you don\'t have to set it on every product and every option.', 1],
            ['0:11', 'The two radio buttons light up; Markup is ticked.',
                     'First, how do you want to type your profit? Markup, or margin. Markup is already chosen — that\'s what everyone starts on. Markup is what you add on top of what you paid: a hundred pounds cost, plus fifty percent, is a hundred and fifty.', 2],
            ['0:24', 'First box types 100; blue hint reads approximately 50 percent margin.',
                     'Type your usual rate into Default price-table markup. A hundred percent here doubles the price-table figure. The blue line underneath tells you what that is as a margin — fifty percent. This one is used on every product and system where you haven\'t typed a rate of your own on the product page.', 3],
            ['0:40', 'Second box types 100; its blue hint appears.',
                     'The second box, Default options and extras, does the same job for the tick-boxes and add-ons — flat pound charges, per-metre charges, width tables, all of them. One warning from the screen itself: it only lifts choices you add from now on. Anything already on your products keeps the price you typed.', 4],
            ['0:56', 'Margin % is clicked; labels and both values repaint.',
                     'Now watch. Click Margin percent. The labels change, and so do the numbers — a hundred becomes fifty. Nothing has been re-priced. A hundred percent markup and a fifty percent margin are the same money; you\'re just writing it the other way round. Underneath it says roughly a hundred percent markup, what the engine uses — because behind the scenes it always stores markup.', 5],
            ['1:14', 'First box flashes 95, settles at 90.90 with an amber note.',
                     'One thing to know before you push margin up. Margin runs away near the top — eighty percent margin is five times your cost, ninety is ten. And there\'s a hard ceiling: type ninety-five and it saves as the highest markup allowed, then comes back as ninety point nine. It won\'t warn you, it\'ll just be a different number to the one you typed.', 6],
            ['1:31', 'Save margins is pressed; green "Default margins saved." appears.',
                     'Happy? Save margins. The page reloads and says Default margins saved. If it puts you back on the Company tab, nothing has gone wrong — that\'s the same tab-memory quirk as before. Click Quoting and your new figures are there.', 7],
            ['1:39', 'The form settles back; the "where it lands" strip appears.',
                     'That\'s it — and it\'s now working everywhere. Every product with no rate of its own uses it. Every new option choice uses it. Every quote uses it, retail and trade alike.', 8],
        ],
];
