<?php
declare(strict_types=1);

/**
 * Guide: quote-build
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Building a quote',
        'eyebrow' => 'Quotes',
        'blurb'   => 'The quote builder end to end: add a blind (product → system → fabric → size → options), watch the live price, read the totals, and send it.',
        'lede'    => 'The <b>quote builder</b> is where a sale is built &mdash; one blind at a time. Pick the <b>product</b>, <b>fabric</b> and
                      <b>size</b>, watch the <b>live price</b>, add any <b>options</b>, and it lands on the quote with running <b>totals</b>.
                      Then <b>send it</b> and move it down the line. Here&rsquo;s the whole thing, including the mistake everyone hits once.',
        'open'    => '/quote-builder/new.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scStart{ display:block; }
          .gd .stage[data-step="2"] .scAdd, .gd .stage[data-step="3"] .scAdd, .gd .stage[data-step="4"] .scAdd,
          .gd .stage[data-step="5"] .scAdd, .gd .stage[data-step="6"] .scAdd{ display:block; }
          .gd .stage[data-step="7"] .scList{ display:block; }
          .gd .stage[data-step="8"] .scSend{ display:block; }

          /* sticky quote bar (all steps) */
          .gd .qbar{ display:flex; align-items:center; gap:.5rem; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .65rem; font-size:.74rem; margin-bottom:.7rem; }
          .gd .qbar .qn{ font-weight:700; }
          .gd .qpill{ font-size:.58rem; font-weight:700; border-radius:20px; padding:.06rem .5rem; background:rgba(255,255,255,.22); text-transform:capitalize; }
          .gd .qpill.acc{ display:none; background:#16a34a; }
          .gd .stage[data-step="8"] .qpill.draft{ display:none; }
          .gd .stage[data-step="8"] .qpill.acc{ display:inline; }
          .gd .qbar .qtot{ margin-left:auto; font-weight:700; }
          .gd .qtot .t1{ display:none; }
          .gd .stage[data-step="7"] .qtot .t0, .gd .stage[data-step="8"] .qtot .t0{ display:none; }
          .gd .stage[data-step="7"] .qtot .t1, .gd .stage[data-step="8"] .qtot .t1{ display:inline; }

          /* system slip swap */
          .gd .sys-cas{ display:none; }
          .gd .stage[data-step="4"] .sys-std{ display:none; }
          .gd .stage[data-step="4"] .sys-cas{ display:inline; }
          .gd .stage[data-step="4"] .sysbox{ box-shadow:0 0 0 2px #ef4444; }

          /* live-preview box */
          .gd .prev{ margin-top:.7rem; border-radius:8px; padding:.5rem .65rem; font-size:.73rem; max-width:24rem; }
          .gd .prev.idle{ background:var(--panel); color:var(--faint); font-style:italic; }
          .gd .prev.err{ background:#fee2e2; color:#991b1b; }
          .gd .prev.ok{ background:#d1fae5; color:#065f46; }
          .gd .prev.ok b{ color:#065f46; }
          .gd .pv{ display:none; }
          .gd .stage[data-step="2"] .pv-i1{ display:block; }
          .gd .stage[data-step="3"] .pv-i2{ display:block; }
          .gd .stage[data-step="4"] .pv-err{ display:block; }
          .gd .stage[data-step="5"] .pv-ok1{ display:block; }
          .gd .stage[data-step="6"] .pv-ok2{ display:block; }

          /* options (step 6) */
          .gd .optwrap{ display:none; margin-top:.6rem; }
          .gd .stage[data-step="6"] .optwrap{ display:block; }
          .gd .optrow{ display:flex; align-items:center; gap:.5rem; font-size:.74rem; }
          .gd .optrow label{ min-width:5.5rem; color:var(--faint); font-size:.64rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .plus{ color:#065f46; font-weight:600; }

          /* save bar */
          .gd .savebar{ margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .qbtn{ display:inline-flex; align-items:center; gap:.3rem; border-radius:8px; padding:.4rem .8rem; font-size:.76rem; font-weight:600; }
          .gd .qsave{ background:var(--line); color:var(--faint); }
          .gd .stage[data-step="6"] .qsave{ background:var(--accent); color:#fff; }
          .gd .qbtn.ghost{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }

          /* blinds list + totals */
          .gd .btl{ width:100%; border-collapse:collapse; font-size:.7rem; }
          .gd .btl th{ text-align:left; font-size:.58rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .4rem; }
          .gd .btl td{ padding:.4rem .4rem; border-bottom:1px solid var(--line); color:var(--ink); vertical-align:top; }
          .gd .btl td b{ color:var(--ink); }
          .gd .tot{ margin:.7rem 0 0 auto; max-width:20rem; font-size:.74rem; }
          .gd .tot .r{ display:flex; justify-content:space-between; padding:.2rem 0; color:var(--soft); }
          .gd .tot .r.wt{ color:#7c3aed; }
          .gd .tot .r.grand{ font-weight:700; color:var(--ink); border-top:1px solid var(--line); margin-top:.2rem; padding-top:.32rem; }
          .gd .tot .r.dep{ color:var(--faint); font-style:italic; }

          /* send actions */
          .gd .actlist{ display:flex; flex-direction:column; gap:.42rem; max-width:20rem; }
          .gd .actbtn{ display:inline-flex; align-items:center; gap:.45rem; border:1px solid var(--line); border-radius:8px; padding:.42rem .65rem; font-size:.74rem; color:var(--ink); background:var(--surface); }
          .gd .actbtn.acc{ background:#16a34a; color:#fff; border-color:#16a34a; }
          .gd .flow{ font-size:.7rem; color:var(--faint); margin-top:.6rem; }
          .gd .flow b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote-builder</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Quotes</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- persistent quote bar -->
                <div class="qbar">
                  <span class="qn">Quote PRE-2026-0042</span>
                  <span class="qpill draft">Draft</span><span class="qpill acc">Accepted</span>
                  <span class="qtot">Total <span class="t0">&pound;0.00</span><span class="t1">&pound;66.00</span></span>
                </div>

                <!-- Scene: start -->
                <div class="osc scStart">
                  <div class="card-t">New quote</div>
                  <div class="fld"><label>Existing customer</label><div class="selectbox">Emma Fletcher &mdash; Leamington Spa &middot; CV32 5PJ</div></div>
                  <p class="ldesc2" style="margin-top:.5rem">Their details fill in below. New customer? Just type the name.</p>
                  <div class="savebar"><span class="qbtn qsave" style="background:var(--accent);color:#fff">Create quote</span></div>
                </div>

                <!-- Scene: add a blind (the cascade) -->
                <div class="osc scAdd">
                  <div class="card-t">Add a blind</div>
                  <div class="frow">
                    <div class="fld"><label>Product</label><div class="selectbox">Roller Blind</div></div>
                    <div class="fld"><label>System</label><div class="selectbox sysbox"><span class="sys-std">Standard</span><span class="sys-cas">Cassette</span></div></div>
                  </div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld"><label>Band</label><div class="selectbox">All bands</div></div>
                    <div class="fld"><label>Fabric</label><div class="box f3"><span class="ph">Type to search fabrics&hellip;</span><span class="val">Sunset White &middot; Band A</span></div></div>
                  </div>
                  <div class="cols3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem;margin-top:.5rem">
                    <div class="fld"><label>Width (mm)</label><div class="box f5"><span class="ph">&nbsp;</span><span class="val">1200</span></div></div>
                    <div class="fld"><label>Drop (mm)</label><div class="box f5"><span class="ph">&nbsp;</span><span class="val">1500</span></div></div>
                    <div class="fld"><label>Qty</label><div class="boxv" style="height:30px;display:flex;align-items:center;padding:0 .5rem;border:1px solid var(--line);border-radius:7px;background:var(--panel);font-size:.8rem">1</div></div>
                  </div>

                  <!-- options (step 6) -->
                  <div class="optwrap">
                    <div class="optrow"><label>Bottom weight</label><span class="selectbox" style="min-width:8rem">Chained</span><span class="plus">+&pound;5.00</span></div>
                  </div>

                  <!-- live preview -->
                  <div class="prev idle pv pv-i1">Still need: fabric, width, drop.</div>
                  <div class="prev idle pv pv-i2">Still need: width, drop.</div>
                  <div class="prev err pv pv-err">&#9888; No price table for Roller Blind band A on system &lsquo;Cassette&rsquo;.</div>
                  <div class="prev ok pv pv-ok1"><b>&pound;45.00</b> per blind &middot; base &pound;45.00</div>
                  <div class="prev ok pv pv-ok2"><b>&pound;50.00</b> per blind &middot; base &pound;45.00 &middot; + extras &pound;5.00</div>

                  <div class="savebar">
                    <span class="qbtn qsave">Save</span>
                    <span class="qbtn ghost">Save and add another blind</span>
                  </div>
                </div>

                <!-- Scene: blinds list + totals -->
                <div class="osc scList">
                  <div class="card-t">Blinds (1)</div>
                  <table class="btl">
                    <thead><tr><th>#</th><th>Description</th><th>Size</th><th>Qty</th><th>Total</th></tr></thead>
                    <tbody>
                      <tr><td>1</td><td><b>Living Room</b><br>Roller Blind &mdash; Standard<br>Band A &mdash; Sunset White<br><span style="color:var(--soft)">+ Bottom weight: Chained (&pound;5.00)</span></td><td>1200 &times; 1500</td><td>1</td><td>&pound;50.00</td></tr>
                    </tbody>
                  </table>
                  <div class="tot">
                    <div class="r wt"><span>WT (internal &mdash; never shown to the customer)</span><span>&pound;5.00</span></div>
                    <div class="r"><span>Subtotal</span><span>&pound;55.00</span></div>
                    <div class="r"><span>VAT (20.00%)</span><span>&pound;11.00</span></div>
                    <div class="r grand"><span>Total</span><span>&pound;66.00</span></div>
                    <div class="r dep"><span>Deposit due on acceptance</span><span>&pound;33.00</span></div>
                  </div>
                </div>

                <!-- Scene: send + status -->
                <div class="osc scSend">
                  <div class="card-t">Send &amp; status</div>
                  <div class="actlist">
                    <span class="actbtn acc">&check; Customer accepted</span>
                    <span class="actbtn">&#128231; Email PDF + accept link</span>
                    <span class="actbtn">&#128172; Send via WhatsApp</span>
                    <span class="actbtn">&#128279; Copy public link</span>
                  </div>
                  <p class="flow">Status moves along: <b>draft &rarr; sent &rarr; accepted &rarr; ordered &rarr; fitted &rarr; invoiced &rarr; paid</b>. Only a <b>draft</b> can be edited.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Start from New quote &rarr; pick the customer &rarr; Create.</b>
                  <b class="c2"><span class="n">2</span> Choose the product; its system fills in.</b>
                  <b class="c3"><span class="n">3</span> Pick the fabric &mdash; search, or filter by band.</b>
                  <b class="c4 err"><span class="n">4</span> No prices for that band + system? Save is blocked.</b>
                  <b class="c5"><span class="n">5</span> Priced system + size &rarr; the price goes green.</b>
                  <b class="c6"><span class="n">6</span> Add options, then Save the blind.</b>
                  <b class="c7"><span class="n">7</span> Totals: subtotal, VAT, WT (internal), deposit.</b>
                  <b class="c8 good"><span class="n">8</span> Send it, and mark it accepted.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>quote builder</b> is the heart of the app. Start it from <b>+ New quote</b>, pick the customer (their address fills in),
             and <b>Create quote</b> &mdash; you land in the builder on a fresh <b>draft</b>. Now add blinds one at a time.</p>
          <ul class="steps">
            <li><b>The cascade.</b> Pick a <b>Product</b> and its <b>System</b> fills in (a variant &mdash; Standard, Motorised&hellip;). Pick the
                <b>Fabric</b> &mdash; type to search by name, colour or code, or narrow it with the <b>Band</b> filter first. Give it a
                <b>Room</b> name, then the <b>Width</b> and <b>Drop</b> (type 1500, or 150cm, or 60in &mdash; the <b>unit</b> selector re-shows
                every size), a <b>Qty</b>, and any <b>Options</b>.</li>
            <li><b>Watch the live price.</b> As you fill it in, the price box updates: grey (<em>&ldquo;Still need: &hellip;&rdquo;</em>) until it
                has enough, then <b>green</b> with the breakdown &mdash; <em>&pound;X per blind &middot; base &middot; + extras</em> (cost-viewers
                also see the markup &amp; discount). <b>Save stays disabled until the price is valid</b>, so you can&rsquo;t save a broken line.</li>
            <li><b>Save.</b> <b>Save</b> drops the blind onto the quote (right-hand list); <b>Save and add another blind</b> keeps the form open
                for the next one. Each blind can carry its own <b>per-blind price tweak</b> (a discount or markup override, cost-viewers only) &mdash;
                it only affects that one line.</li>
          </ul>
          <div class="oops"><b>&ldquo;No price table for &hellip; band A on system &lsquo;Cassette&rsquo;.&rdquo;</b> The single most common slip:
             the fabric&rsquo;s <b>band has no price list on the system you picked</b>. The Band filter is just a filter &mdash; it doesn&rsquo;t
             guarantee a price. <b>Fix:</b> switch to a <b>System</b> that&rsquo;s priced for that band (the Band list re-scopes to it), or pick a
             band that has a price table, then reselect the fabric. Save unlocks once the price turns green.</div>
          <div class="oops"><b>&ldquo;Size 2400 &times; 3000 mm exceeds the largest cell in this price table.&rdquo;</b> The size is bigger than the
             grid goes. <b>Fix:</b> double-check the <b>measurement unit</b> (a value typed as mm while the quote is in cm reads ten times too
             big!), and confirm the price list actually covers that size.</div>
          <ul class="steps">
            <li><b>The list &amp; totals.</b> Each blind shows its room, product/system, fabric, size, qty and line total, with <b>Edit</b>,
                <b>Dup</b> (duplicate &mdash; handy for the same blind in another size) and <b>&times;</b> (remove). Totals stack up on the right:
                <b>Subtotal</b>, <b>VAT</b>, <b>Total</b>, and the <b>Deposit due on acceptance</b>. The purple <b>WT</b> line is your internal
                <b>Wally tax</b> &mdash; a hassle surcharge folded into the price; the customer <b>never</b> sees it as a line.</li>
            <li><b>Send it.</b> <b>Email PDF + accept link</b> sends the customer a PDF with a one-click accept button; or <b>Send via WhatsApp</b>
                (when their number is flagged for it) or <b>Copy public link</b>. You can also <b>View / Download PDF</b> any time.</li>
            <li><b>Move it along.</b> When they say yes, hit <b>&check; Customer accepted</b> (or Declined). Status runs
                <b>draft &rarr; sent &rarr; accepted &rarr; ordered &rarr; fitted &rarr; invoiced &rarr; paid</b>. Accepting seeds the <b>deposit</b>
                and drops a <b>Pending Fitting</b> into the calendar; <b>Send to suppliers</b> and <b>Send invoice</b> live in the Quote actions panel.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>There&rsquo;s no big &ldquo;Save quote&rdquo; button &mdash; every panel saves
             itself.</b> Adding a blind, editing the customer, setting the WT or deposit each save on the spot, and the totals recompute. And
             <b>only a draft is editable</b> &mdash; once it&rsquo;s sent/accepted it&rsquo;s locked (<em>&ldquo;Quote is locked&hellip; Reopen it
             to add blinds&rdquo;</em>); use <b>Reopen as draft</b> if you need to change it.</div></div>',
        'script'  => [
            ['0:00', 'New quote; pick the customer.',      'A quote starts from New quote. Pick the customer — their address fills in — and Create quote. You land in the builder.', 1],
            ['0:08', 'Product chosen; system fills in.',   'Now build it a blind at a time. Choose the product, and its system fills in — here, a standard roller.', 2],
            ['0:15', 'Fabric picked.',                     'Pick the fabric — type to search, or filter by band first. This one is Sunset White, band A.', 3],
            ['0:22', 'Red: no price for band + system.',   'Keep an eye on the live price. If that band has no prices on the system you picked, it says so — no price table for band A on Cassette — and won\'t let you save.', 4],
            ['0:32', 'Fixed system + size; price green.',  'Switch to a system that is priced — Standard — then type the width and drop. The price turns green: forty-five pounds a blind.', 5],
            ['0:41', 'Option added; Save.',                'Add any options — a chained bottom weight adds five pounds — then Save. That is one blind on the quote.', 6],
            ['0:49', 'Blinds list and totals.',           'It lands on the right with the running totals: subtotal, VAT, grand total. WT is your internal Wally-tax line, never shown to the customer — and it works out the deposit due.', 7],
            ['1:00', 'Send it; mark accepted.',            'Then send it — email the PDF with an accept link, or WhatsApp. When they say yes, mark it accepted, and it moves along: sent, accepted, ordered.', 8],
        ],
];
