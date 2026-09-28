<?php
declare(strict_types=1);

/**
 * Guide: quote-offline
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Working with no signal on a tablet (built 2026-09-26/28, PRs #802–#827):
 *   1-2  sidebar footer → "Work offline" → Set up for offline → status line
 *        (offline/engine.js render(): "✓ Works offline — prices from …",
 *        "14 products and their fabrics saved.", Update now / Turn off)
 *   3    no signal: the saved copy of a quote (sw.js → offline_guard.php note
 *        "No signal — this is the copy saved on this tablet at …") and the orange
 *        bar ("No signal — anything you save is kept on this tablet.")
 *   4    Add blind priced on the tablet ("tablet price — checked when sent")
 *   5    Save → "Kept on this tablet, not on the quote yet (N)" with
 *        Put back in the form / Delete
 *   6    + New with no signal → the on-tablet new quote ("New quote · ON THIS
 *        TABLET · Ref …")
 *   7    Email PDF + accept link with no signal → the confirm (option B)
 *   8    Signal back: sent, numbered, flagged price, held email (Send now / Don't send)
 *
 * Every wording below is copied from the code; keep it in step if they change.
 */

return [
        'aud'     => 'all',
        'section' => 'Quotes',
        'title'   => 'Working offline on a tablet',
        'eyebrow' => 'Quotes · No signal',
        'blurb'   => 'Set a tablet up once on WiFi, then measure, price and start quotes with no signal. Everything is kept on the tablet and goes up — checked by the server — when the signal is back.',
        'lede'    => 'Out on a job with <b>no signal</b>? A tablet you have <b>set up for offline</b> keeps working: it opens the
                      quotes you have looked at, prices every blind <b>on the tablet itself</b> (using the very same pricing
                      the server uses), starts <b>brand-new quotes</b>, and can even <b>queue the email</b> to the customer.
                      Nothing is lost &mdash; it all waits on the tablet and <b>sends itself</b> when the signal comes back,
                      where the server checks every price again. You set it up <b>once, on WiFi</b>, from the menu.',
        'open'    => '/orders/index.php?scope=quotes&type=retail',
        'css'     => '
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scSet,
          .gd .stage[data-step="2"] .scSet{ display:block; }
          .gd .stage[data-step="3"] .scQuote,
          .gd .stage[data-step="4"] .scQuote,
          .gd .stage[data-step="5"] .scQuote{ display:block; }
          .gd .stage[data-step="6"] .scNew{ display:block; }
          .gd .stage[data-step="7"] .scSend{ display:block; }
          .gd .stage[data-step="8"] .scBack{ display:block; }
          .gd .stage{ position:relative; min-height:21rem; }
          /* room under the scenes for the orange "No signal" bar, so it never covers the buttons */
          .gd .scQuote, .gd .scNew, .gd .scSend{ padding-bottom:2.4rem; }

          /* 1-2: the sidebar footer and its panel */
          .gd .foot{ max-width:17rem; background:var(--nav); color:#dfe7ef; border-radius:10px; padding:.6rem .7rem; font-size:.68rem; }
          .gd .foot .fl{ color:#9fb3c4; font-size:.62rem; margin-bottom:.4rem; }
          .gd .tbtn{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid rgba(255,255,255,.25); border-radius:999px; padding:.18rem .55rem; font-size:.58rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; margin:.15rem .2rem .15rem 0; color:#fff; }
          .gd .tbtn.hit{ box-shadow:0 0 0 3px var(--accent-wash); border-color:var(--accent); }
          .gd .epanel{ margin-top:.4rem; background:rgba(255,255,255,.08); border-radius:8px; padding:.45rem .55rem; font-size:.66rem; line-height:1.5; }
          .gd .epanel .eb{ display:inline-flex; background:#fff; color:#1d2330; border:1px solid #cbd5e1; border-radius:6px; padding:.1rem .45rem; font-size:.62rem; margin:.3rem .25rem 0 0; }
          .gd .epanel .eb.hit{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .s1, .gd .s2, .gd .t1, .gd .t2{ display:none; }
          .gd .stage[data-step="1"] .t1, .gd .stage[data-step="2"] .t2{ display:inline; }
          .gd .stage[data-step="1"] .s1, .gd .stage[data-step="2"] .s2{ display:block; }
          .gd .snote{ font-size:.7rem; color:var(--soft); margin:.6rem 0 0; max-width:30rem; line-height:1.55; }
          .gd .snote b{ color:var(--ink); }

          /* 3-5: a saved quote with no signal */
          .gd .savednote{ background:#fff8e6; border:1px solid #e0b252; color:#4a3700; border-radius:8px; padding:.4rem .6rem; font-size:.66rem; margin-bottom:.55rem; }
          :root[data-theme="dark"] .gd .savednote{ background:#3a3020; color:#f3e2b8; border-color:#7a6230; }
          .gd .qbar{ display:flex; justify-content:space-between; background:#1f3b5b; color:#fff; border-radius:7px; padding:.35rem .6rem; font-size:.68rem; font-weight:700; margin-bottom:.55rem; }
          .gd .grid3{ display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem; }
          .gd .mini{ font-size:.55rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; margin-bottom:.12rem; }
          .gd .vbox{ height:24px; border:1px solid var(--line); border-radius:6px; background:var(--panel); display:flex; align-items:center; padding:0 .4rem; font-size:.66rem; color:var(--ink); overflow:hidden; white-space:nowrap; }
          .gd .vbox.sel::after{ content:"\25BE"; margin-left:auto; color:var(--faint); font-size:.55rem; }
          .gd .preview{ margin-top:.5rem; border-radius:7px; padding:.35rem .55rem; font-size:.68rem; background:var(--panel); color:var(--soft); border:1px solid var(--line-2); }
          .gd .preview.ok{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
          .gd .preview em{ font-style:italic; }
          .gd .p3, .gd .p4, .gd .p5{ display:none; }
          .gd .stage[data-step="3"] .p3, .gd .stage[data-step="4"] .p4, .gd .stage[data-step="5"] .p5{ display:block; }
          .gd .kept{ border:1px solid #e0b252; background:#fff8e6; color:#4a3700; border-radius:8px; padding:.4rem .55rem; font-size:.64rem; margin-bottom:.5rem; display:none; }
          .gd .stage[data-step="5"] .kept{ display:block; }
          :root[data-theme="dark"] .gd .kept{ background:#3a3020; color:#f3e2b8; border-color:#7a6230; }
          .gd .kept .kr{ display:flex; gap:.3rem; align-items:center; flex-wrap:wrap; margin-top:.25rem; }
          .gd .kept .kr span{ flex:1 1 12rem; }
          .gd .kb{ display:inline-flex; border:1px solid #c99a2e; background:#fff; color:#4a3700; border-radius:5px; padding:.05rem .4rem; font-size:.58rem; }
          .gd .btnrow{ display:flex; gap:.4rem; margin-top:.5rem; }
          .gd .btnp{ display:inline-flex; background:var(--nav); color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.66rem; font-weight:700; }
          .gd .btns{ display:inline-flex; border:1px solid var(--line); color:var(--soft); border-radius:7px; padding:.28rem .7rem; font-size:.66rem; }
          .gd .stage[data-step="5"] .btns{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .netbar{ position:absolute; left:50%; bottom:2.9rem; transform:translateX(-50%); background:#8a4b00; color:#fff; border-radius:999px; padding:.3rem .8rem; font-size:.62rem; font-weight:700; white-space:nowrap; box-shadow:0 4px 14px rgba(0,0,0,.18); display:none; }
          .gd .stage[data-step="3"] .netbar, .gd .stage[data-step="4"] .netbar, .gd .stage[data-step="5"] .netbar,
          .gd .stage[data-step="6"] .netbar, .gd .stage[data-step="7"] .netbar{ display:block; }

          /* 6: new quote on the tablet */
          .gd .newhead .qbar .tag{ background:rgba(255,255,255,.18); border-radius:999px; padding:0 .45rem; font-size:.55rem; letter-spacing:.05em; text-transform:uppercase; margin-left:.35rem; }
          .gd .pgt{ font-size:.95rem; font-weight:800; color:var(--ink); margin:.1rem 0 .1rem; }
          .gd .pgs{ font-size:.66rem; color:var(--faint); margin:0 0 .55rem; max-width:32rem; line-height:1.5; }

          /* 7: the email confirm */
          .gd .dlg{ max-width:25rem; border:1px solid var(--line); border-radius:10px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.6rem .75rem; font-size:.7rem; color:var(--ink); line-height:1.5; margin-top:.6rem; }
          .gd .dlg .dt{ font-size:.55rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.2rem; }
          .gd .dlg .dbtns{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.5rem; }
          .gd .dlg .db{ border:1px solid var(--line); border-radius:6px; padding:.12rem .55rem; font-size:.64rem; color:var(--soft); }
          .gd .dlg .db.ok{ background:var(--nav); color:#fff; border-color:var(--nav); font-weight:700; }
          .gd .sbtn{ display:inline-flex; align-items:center; gap:.35rem; border-radius:8px; font-weight:700; background:rgba(220,38,38,.5); color:#000; font-size:.78rem; padding:.45rem .8rem; box-shadow:0 0 0 3px var(--accent-wash); }

          /* 8: signal back */
          .gd .greenbar{ display:inline-block; background:#2b2f36; color:#fff; border-radius:999px; padding:.28rem .8rem; font-size:.62rem; font-weight:700; margin-bottom:.55rem; }
          .gd .ltbl{ width:100%; border-collapse:collapse; font-size:.64rem; }
          .gd .ltbl th{ text-align:left; font-size:.52rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.22rem .3rem; }
          .gd .ltbl td{ padding:.28rem .3rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
          .gd .ltbl .r{ text-align:right; color:var(--ink); }
          .gd .flagbox{ margin-top:.55rem; border:1px solid #e0b252; background:#fff8e6; color:#4a3700; border-radius:8px; padding:.4rem .55rem; font-size:.64rem; line-height:1.5; }
          :root[data-theme="dark"] .gd .flagbox{ background:#3a3020; color:#f3e2b8; border-color:#7a6230; }
          .gd .flagbox .kb{ margin:.25rem .25rem 0 0; }
          @media(max-width:620px){ .gd .grid3{ grid-template-columns:1fr 1fr; } .gd .netbar{ white-space:normal; width:80%; text-align:center; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk &mdash; on the tablet</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a class="on">Quotes</a><a>Orders</a><a>Customers</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ===== 1-2: set it up from the menu ===== -->
                <div class="osc scSet">
                  <div class="card-t">The bottom of the menu</div>
                  <div class="foot">
                    <div class="fl">Help &amp; guide &middot; Change password &middot; Sign out &rarr;</div>
                    <span class="tbtn">&#127769; Dark mode</span><span class="tbtn">&#8597; Roomy &middot; go compact</span>
                    <span class="tbtn hit"><span class="t1">&#128246; Work offline: set up</span><span class="t2">&#128246; Offline ready</span></span>
                    <div class="epanel">
                      <div class="s1">Saving the product lists&hellip; 9 of 14<br><span class="eb hit">Set up for offline</span></div>
                      <div class="s2">&check; Works offline &mdash; prices from Mon 28 Sept. 14 products and their fabrics saved.<br>
                        <span class="eb">Update now</span><span class="eb">Turn off</span></div>
                    </div>
                  </div>
                  <p class="snote s1">One-off download of about <b>20&nbsp;MB</b> &mdash; do it <b>on WiFi</b>. It saves the pricing,
                     your price list, and every product with all its fabrics.</p>
                  <p class="snote s2">The button now reads <b>Offline ready</b>. It keeps itself up to date whenever the tablet has
                     signal; <b>Update now</b> does it straight away.</p>
                </div>

                <!-- ===== 3-5: a saved quote with no signal ===== -->
                <div class="osc scQuote">
                  <div class="savednote">No signal &mdash; this is the copy saved on this tablet at 10:42. Anything you change is kept here and sent when the signal is back.</div>
                  <div class="qbar"><span>Quote BEV-2026-0042</span><span>Total &pound;144.28</span></div>
                  <div class="kept"><b>Kept on this tablet, not on the quote yet (2)</b>
                    <div class="kr"><span>Lounge &mdash; Bev Roller Blinds &mdash; 1200 &times; 1500 mm &middot; &pound;59.23 (tablet price) &middot; waiting to send</span><span class="kb">Put back in the form</span><span class="kb">Delete</span></div>
                    <div class="kr"><span>Kitchen &mdash; Bev Roller Blinds &mdash; 900 &times; 1200 mm &middot; &pound;40.31 (tablet price) &middot; waiting to send</span><span class="kb">Put back in the form</span><span class="kb">Delete</span></div>
                  </div>
                  <div class="card-t" style="margin-bottom:.35rem">Add blind</div>
                  <div class="grid3">
                    <div><div class="mini">Product</div><div class="vbox sel">Bev Roller Blinds</div></div>
                    <div><div class="mini">System</div><div class="vbox sel">No Frills Rollers</div></div>
                    <div><div class="mini">Fabric</div><div class="vbox">ACACIA BO / CARAWAY</div></div>
                    <div><div class="mini">Room name</div><div class="vbox"><span class="p4">Lounge</span></div></div>
                    <div><div class="mini">Width (mm)</div><div class="vbox"><span class="p4">120cm</span></div></div>
                    <div><div class="mini">Drop (mm)</div><div class="vbox"><span class="p4">1500</span></div></div>
                  </div>
                  <div class="preview p3">Getting prices ready on this tablet&hellip;</div>
                  <div class="preview ok p4"><b>&pound;59.23</b> per blind &middot; base &pound;59.23 &middot; <em>tablet price &mdash; checked when sent</em></div>
                  <div class="preview p5">&check; Kept on this tablet &mdash; it&rsquo;ll be added when the signal is back (the server checks the price then).</div>
                  <div class="btnrow"><span class="btnp">Save</span><span class="btns">Save and add another blind</span></div>
                </div>

                <!-- ===== 6: a brand-new quote with no signal ===== -->
                <div class="osc scNew newhead">
                  <div class="qbar"><span>New quote <span class="tag">on this tablet</span></span><span>Ref WTTF65</span></div>
                  <div class="pgt">New quote</div>
                  <div class="pgs">Started with no signal. It gets its quote number when the signal is back &mdash; everything here is kept on this tablet until then.</div>
                  <div class="grid3">
                    <div><div class="mini">Customer name</div><div class="vbox">Emma Fletcher</div></div>
                    <div><div class="mini">Postcode</div><div class="vbox">CV32 5PQ</div></div>
                    <div><div class="mini">Their reference</div><div class="vbox">&nbsp;</div></div>
                  </div>
                  <div class="card-t" style="margin:.6rem 0 .3rem">Add blind</div>
                  <div class="preview ok"><b>&pound;40.31</b> per blind &middot; base &pound;40.31 &middot; <em>tablet price &mdash; checked when sent</em></div>
                </div>

                <!-- ===== 7: email with no signal ===== -->
                <div class="osc scSend">
                  <div class="card-t">Send to customer</div>
                  <span class="sbtn">&#128231; Email PDF + accept link</span>
                  <div class="dlg"><div class="dt">yourblinds.uk says</div>
                    No signal. Send this quote to emma.fletcher@gmail.com automatically when the signal is back?<br><br>
                    It goes once all its blinds have been sent. If the server prices any blind differently from the tablet, it waits for you to check the quote first.
                    <div class="dbtns"><span class="db">Cancel</span><span class="db ok">OK</span></div></div>
                </div>

                <!-- ===== 8: signal back ===== -->
                <div class="osc scBack">
                  <span class="greenbar">Signal back &mdash; sending 4 saved changes&hellip;</span>
                  <div class="qbar"><span>Quote BEV-2026-0043</span><span>Total &pound;119.45</span></div>
                  <table class="ltbl">
                    <thead><tr><th>#</th><th>Description</th><th>Size</th><th class="r">Total</th></tr></thead>
                    <tbody>
                      <tr><td>1</td><td>Lounge &mdash; Bev Roller Blinds</td><td>1200 &times; 1500 mm</td><td class="r">&pound;59.23</td></tr>
                      <tr><td>2</td><td>Kitchen &mdash; Bev Roller Blinds</td><td>900 &times; 1200 mm</td><td class="r">&pound;40.31</td></tr>
                    </tbody>
                  </table>
                  <div class="flagbox"><b>If a price came out different:</b> &ldquo;Priced differently when sent from the tablet &mdash; the quote uses the server&rsquo;s price&rdquo;, with both prices and <span class="kb">OK, checked</span><br>
                    and the queued email is held: &ldquo;held: a price changed when the blinds were sent&rdquo; <span class="kb">Send now</span><span class="kb">Don&rsquo;t send</span></div>
                </div>

                <div class="netbar">No signal &mdash; anything you save is kept on this tablet (2 saved changes waiting).</div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Menu &rarr; Work offline &rarr; Set up for offline (on WiFi).</b>
                  <b class="c2 good"><span class="n">2</span> Offline ready &mdash; prices and every product saved.</b>
                  <b class="c3"><span class="n">3</span> No signal: the quote opens from the tablet.</b>
                  <b class="c4 good"><span class="n">4</span> Blinds price on the tablet &mdash; checked when sent.</b>
                  <b class="c5"><span class="n">5</span> Save: kept on the tablet, ready for the next window.</b>
                  <b class="c6"><span class="n">6</span> + New with no signal: a quote on the tablet.</b>
                  <b class="c7"><span class="n">7</span> The email can wait for the signal too.</b>
                  <b class="c8 good"><span class="n">8</span> Signal back: numbered, added, re-checked.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Tablets only.</b> Offline working is meant for the tablet a salesperson takes out &mdash; the
             <b>Work offline</b> button only appears on touch screens. It runs on very cheap tablets (it has been tested on a
             &pound;55 one), and it is easiest to use with the tablet <b>held upright</b>: the on-screen keyboard takes far less
             of the screen that way.</p>

          <p><b>Set it up once, on WiFi.</b></p>
          <ul class="steps">
            <li>Open the menu (the <b>&#9881;</b> button, top right, on a tablet) and scroll to the bottom. Under
                <b>Dark mode</b> and <b>Compact mode</b> you&rsquo;ll see <b>&#128246; Work offline: set up</b>. Tap it.</li>
            <li>Tap <b>Set up for offline</b>. It downloads about <b>20&nbsp;MB</b> once &mdash; the pricing, your price list,
                and every product with <b>all</b> of its fabrics &mdash; counting &ldquo;<em>Saving the product lists&hellip;
                9 of 14</em>&rdquo; as it goes, then &ldquo;<em>&check; Ready</em>&rdquo;.</li>
            <li>The button now reads <b>Offline ready</b>. Tap it any time to see the status:
                &ldquo;<em>&check; Works offline &mdash; prices from Mon 28 Sept. 14 products and their fabrics saved.</em>&rdquo;
                The same line sits just above <b>Product</b> in <b>Add blind</b> on every quote.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Open the quotes you&rsquo;ll need while you still have
             signal.</b> The tablet saves a copy of <b>every quote you open with signal</b> (plus your quote list and a blank
             new-quote screen), and keeps them for <b>14 days</b>. A quote you have never opened on that tablet can&rsquo;t be
             opened with no signal &mdash; you&rsquo;ll get a &ldquo;<em>No signal</em>&rdquo; page listing the ones it
             <em>does</em> have.</div></div>

          <p><b>With no signal.</b> An orange bar appears at the bottom: &ldquo;<em>No signal &mdash; anything you save is kept on
             this tablet.</em>&rdquo; A quote opened now shows a yellow note at the top &mdash; &ldquo;<em>No signal &mdash; this is
             the copy saved on this tablet at 10:42</em>&rdquo; &mdash; because it is the copy from the last time you opened it.</p>
          <ul class="steps">
            <li><b>Add blinds as normal.</b> Product, system, band and fabric all work (from the tablet&rsquo;s copy). The price
                is worked out <b>on the tablet</b> and marked <b>&ldquo;<em>tablet price &mdash; checked when sent</em>&rdquo;</b>.
                The first price after the signal drops can take a few seconds while it gets ready &mdash; it says
                &ldquo;<em>Getting prices ready on this tablet&hellip;</em>&rdquo; &mdash; after that every price is instant.</li>
            <li><b>Save</b> (or <b>Save and add another blind</b>). The blind goes into a yellow list above the form,
                &ldquo;<em>Kept on this tablet, not on the quote yet</em>&rdquo;, with its tablet price. The form keeps the
                product, fabric and options and clears the room and sizes, ready for the next window. Each kept blind has
                <b>Put back in the form</b> (to change it) and <b>Delete</b>.</li>
            <li><b>Customer details</b> you change are kept the same way.</li>
            <li><b>A new quote.</b> Tap <b>+ New</b>, type the customer&rsquo;s name and press <b>Create quote</b>. With no signal
                it opens a <b>New quote &mdash; on this tablet</b> with a short reference (e.g. <em>Ref WTTF65</em>). It gets its
                real quote number when the signal is back. Add its blinds exactly as above.</li>
            <li><b>Emailing the quote.</b> Press <b>&#128231; Email PDF + accept link</b> and it asks:
                &ldquo;<em>No signal. Send this quote to &hellip; automatically when the signal is back?</em>&rdquo; Say
                <b>OK</b> and the email waits on the tablet.</li>
          </ul>

          <p><b>When the signal is back</b> it all goes up on its own &mdash; you don&rsquo;t press anything. The bar says
             &ldquo;<em>Signal back &mdash; sending 3 saved changes&hellip;</em>&rdquo;. New quotes are created first (and get their
             number), then their details and blinds, then any email. The server <b>prices every blind again</b> as it adds it, and
             the quote uses the server&rsquo;s price.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>If a price comes out different, you&rsquo;re told &mdash; never
             silently.</b> The quote shows &ldquo;<em>Priced differently when sent from the tablet &mdash; the quote uses the
             server&rsquo;s price</em>&rdquo; with both figures, until you press <b>OK, checked</b>. And a queued email for that quote
             is <b>held</b> rather than sent &mdash; &ldquo;<em>held: a price changed when the blinds were sent</em>&rdquo; &mdash;
             with <b>Send now</b> and <b>Don&rsquo;t send</b>, so a customer never gets a price you haven&rsquo;t seen.</div></div>

          <p><b>What needs signal.</b> Anything the server has to do right there and then: <b>View PDF</b> /
             <b>Download PDF</b>, changing a quote&rsquo;s status, deleting, deposits and so on. With no signal those buttons say
             &ldquo;<em>No signal &mdash; this needs signal. Nothing has been changed</em>&rdquo; and leave you where you are.</p>

          <div class="oops"><b>If something doesn&rsquo;t look right.</b>
             <b>&ldquo;Not saved on this tablet&rdquo;</b> in the System or Fabric box &mdash; that product&rsquo;s lists aren&rsquo;t on
             the tablet yet: with signal, open the menu, tap <b>Offline ready</b> &rarr; <b>Update now</b> and let it count to the end.
             <b>&ldquo;No signal &mdash; Update now needs WiFi or a signal&rdquo;</b> &mdash; exactly that: turn flight mode off,
             reload the page, try again. <b>A quote looks out of date, or a new button isn&rsquo;t there</b> &mdash; the tablet is showing
             the copy it saved last time: open it once <b>with signal</b> to refresh it. <b>Signed out while you were away?</b> The bar
             says &ldquo;<em>Signed out &mdash; sign in again to send &hellip;</em>&rdquo;; sign in and it carries on.</div>

          <p><b>Keeping prices current.</b> Whenever the tablet has signal it quietly checks for new prices (at most every half hour)
             and fetches them. The copy is dated: if it is from an earlier day the status turns amber &mdash;
             &ldquo;<em>&#9888; Offline prices are from Mon 28 Sept &mdash; update them when you have signal.</em>&rdquo; The simplest habit:
             open the app on WiFi before you go out.</p>

          <p><b>Shared tablets and privacy.</b> Everything kept on a tablet belongs to the person signed in &mdash; if someone else
             signs in on the same tablet, they never see or send your saved work. Saved quote pages include customer names and
             addresses, which is why only tablets you <b>deliberately set up</b> keep them, only for <b>14 days</b>, and why
             <b>Turn off</b> (menu &rarr; Offline ready &rarr; Turn off) wipes the lot &mdash; price list, saved pages and the engine.
             Blinds still <em>waiting to send</em> are not wiped: that&rsquo;s unsent work.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Menu → Work offline → Set up.',            'On the tablet, open the menu and go to the very bottom. Under dark mode you\'ll find Work offline, set up. Tap it, then Set up for offline. Do this on WiFi — it\'s a one-off download of about twenty megabytes, and it saves your prices and every product with all its fabrics.', 1],
            ['0:18', 'Offline ready.',                           'When it\'s done, the button says Offline ready, and the status tells you the date of your prices and how many products are saved. It keeps itself up to date whenever the tablet has signal.', 2],
            ['0:32', 'No signal: the saved copy.',               'Now say you\'re in a customer\'s house with no signal. An orange bar at the bottom tells you, and the quote opens from the copy saved on the tablet — there\'s a yellow note at the top with the time it was saved. That\'s why it pays to open your quotes before you go out.', 3],
            ['0:50', 'The tablet price.',                        'Add a blind exactly as normal. The price is worked out on the tablet itself, using the same pricing as the server, and it\'s marked tablet price, checked when sent. The very first price can take a few seconds while it gets ready; after that it\'s instant.', 4],
            ['1:08', 'Kept on this tablet.',                     'Press Save, and the blind is kept on the tablet, in a yellow list above the form, with its price. The form keeps the product and fabric and clears the sizes, ready for the next window.', 5],
            ['1:22', 'A new quote on the tablet.',               'You can start a brand new quote with no signal, too. Press New, type the customer\'s name and press Create quote. It opens a new quote on the tablet with a short reference, and it gets its real quote number when the signal comes back.', 6],
            ['1:40', 'Queue the email.',                         'Even the email can wait. Press Email PDF plus accept link, and it asks whether to send it automatically when the signal is back. Say OK.', 7],
            ['1:52', 'Signal back.',                             'And when the signal comes back, you don\'t have to do a thing. The new quote is created and numbered, its blinds are added, and the server checks every price. If a price comes out different, you\'re told, and the customer\'s email is held until you\'ve checked it and pressed Send now.', 8],
        ],
];
