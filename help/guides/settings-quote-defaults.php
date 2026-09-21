<?php
declare(strict_types=1);

/**
 * Guide: settings-quote-defaults
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the THIRD form on Settings → Quoting ("Quote defaults",
 * admin/settings.php 1362–1689, POST _action=quote at 238–410). The form is
 * fifteen blocks tall, so the demo uses four scenes (scA..scD) swapped by
 * data-step — every real field, in the real order, in its real fieldset.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Quote defaults',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Quote numbers, VAT, deposits, what the customer sees, the automatic emails and the Wally tax (WT charge).',
        'lede'    => 'The biggest form on the <b>Quoting</b> tab, and the one that quietly shapes every quote you send:
                      the <b>letters in front of your quote numbers</b>, your <b>VAT rate</b>, the <b>deposit</b> that lands
                      on a quote when it is accepted, <b>what the customer does and doesn&rsquo;t see</b>, the emails that go
                      out on their own, and the <b>Wally tax (WT charge)</b>. We&rsquo;ll walk the whole thing, top to bottom.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* ── local scaffolding (not in guide.php): fieldsets, number boxes,
               checkbox rows and the little static value box ───────────────── */
          .gd .fset{ border:1px solid var(--line); border-radius:9px; padding:.5rem .7rem .55rem; margin-top:.6rem; }
          .gd .fset-lg{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.4rem; }
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .numbox{ display:inline-flex; align-items:center; justify-content:center; min-width:2.7rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.15rem .4rem; font-size:.78rem; color:var(--ink); background:var(--surface); }
          .gd .unit{ color:var(--faint); font-size:.8rem; }
          .gd .chkrow{ display:flex; align-items:flex-start; gap:.5rem; font-size:.78rem; color:var(--ink); margin:.1rem 0; }
          .gd .muted{ color:var(--faint); }
          .gd .fhint{ font-size:.66rem; color:var(--faint); margin:.25rem 0 0; line-height:1.45; }
          .gd .fhint2{ font-size:.68rem; color:var(--faint); margin:.6rem 0 0; }
          .gd .frow + .frow, .gd .qfield{ margin-top:.7rem; }
          .gd .deprow{ display:flex; align-items:center; gap:.35rem 1.1rem; flex-wrap:wrap; }
          .gd .deprow .radio{ margin-right:0; gap:.4rem; }
          .gd .qfield label{ text-transform:none; letter-spacing:0; font-size:.66rem; }
          .gd .wide{ max-width:19rem; margin-top:.3rem; }
          .gd .chklab{ font-size:.78rem; color:var(--ink); }
          .gd .salerow{ font-size:.8rem; color:var(--ink); display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; }
          .gd .salerow .selectbox{ min-width:8rem; }

          /* ── scenes: the form is 15 blocks tall, so we show it in four
               passes, in the real screen order ─────────────────────────────── */
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA,
          .gd .stage[data-step="2"] .scA, .gd .stage[data-step="3"] .scA{ display:block; }
          .gd .stage[data-step="4"] .scB, .gd .stage[data-step="5"] .scB{ display:block; }
          .gd .stage[data-step="6"] .scC, .gd .stage[data-step="7"] .scC{ display:block; }
          .gd .stage[data-step="8"] .scD{ display:block; }

          /* scene A — step 2 lights the VAT box (it ships already reading 20),
             step 3 selects the percentage radio and lights its number box */
          .gd .stage[data-step="2"] .vatbox{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .rperc{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="3"] .rperc .dot{ border-color:var(--accent); }
          .gd .stage[data-step="3"] .rperc .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .stage[data-step="3"] .depbox{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }

          /* scene B — the three customer-facing boxes tick at step 4; the WT
             inset (the builder row + its flash) appears at step 5 */
          .gd .stage[data-step="4"] .tPrice, .gd .stage[data-step="5"] .tPrice,
          .gd .stage[data-step="4"] .tSize,  .gd .stage[data-step="5"] .tSize,
          .gd .stage[data-step="4"] .tWt,    .gd .stage[data-step="5"] .tWt{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="5"] .wtset{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .wtin{ display:none; margin-top:.5rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .stage[data-step="5"] .wtin{ display:block; }
          .gd .wtq{ background:var(--panel); padding:.25rem .55rem; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); border-bottom:1px solid var(--line); }
          .gd .wtrow{ display:flex; align-items:center; gap:.4rem; padding:.4rem .55rem; font-size:.78rem; flex-wrap:wrap; }
          .gd .wtlbl{ color:#6d28d9; font-weight:700; }
          .gd .wtnote{ color:#6d28d9; font-weight:400; font-size:.66rem; }
          .gd .setbtn{ background:var(--nav); color:#fff; border-radius:6px; padding:.15rem .55rem; font-size:.7rem; font-weight:600; }
          .gd .wtin .okbanner{ border-radius:0; border-left-width:3px; font-size:.7rem; }

          /* scene C — step 6 fills the alert address; step 7 is the slip: a
             malformed address is thrown away silently, so it fades back out
             and the placeholder returns while the green banner still shows */
          .gd .stage[data-step="6"] .g6 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .g6 .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .box .bad{ position:absolute; inset:0; display:flex; align-items:center; padding:0 .5rem; color:var(--ink); opacity:0; white-space:nowrap; }
          @keyframes gdFadeBack{ 0%,55%{ opacity:1; } 100%{ opacity:0; } }
          .gd .stage[data-step="7"] .bad{ animation:gdFadeBack 7s ease-in both; }
          .gd .stage[data-step="7"] .g6{ border-color:#f59e0b; box-shadow:0 0 0 3px color-mix(in srgb,#f59e0b 22%,transparent); }
          .gd .caps b.c7{ color:#b45309; }

          /* scene D — the last three fields fill at step 8 (past the shared
             f1..f5 engine, so this guide carries its own rule) */
          .gd .stage[data-step="8"] .g8 .ph{ opacity:0; }
          .gd .stage[data-step="8"] .g8 .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="8"] .save{ transform:scale(.96); filter:brightness(1.25); }

          /* the real feedback: a green banner across the TOP of the page after
             the redirect — not a floating toast by the button */
          .gd .alertbar{ display:none; background:var(--good-wash); color:var(--good); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); border-left:3px solid var(--good); border-radius:8px; padding:.35rem .7rem; font-size:.78rem; font-weight:700; margin-bottom:.6rem; }
          .gd .stage[data-step="7"] .alertbar, .gd .stage[data-step="8"] .alertbar{ display:block; }
          @media(max-width:620px){ .gd .deprow{ gap:.5rem; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings &middot; quoting</span></div>
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
                <div class="alertbar">Quote settings saved.</div>
                <div class="card-t">Quote defaults</div>

                <!-- Scene A: prefix, VAT, default deposit, pricing-basis note -->
                <div class="osc scA">
                  <div class="frow">
                    <div class="fld"><label>Quote prefix</label>
                      <div class="box f1"><span class="ph">e.g. BRI</span><span class="val">BRI</span></div></div>
                    <div class="fld"><label>VAT %</label>
                      <div class="boxv vatbox">20</div></div>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">Default deposit</div>
                    <p class="fhint" style="margin:0 0 .45rem">Seeds the deposit figure on every quote the moment it moves into Accepted.
                       Overrideable per quote. Pick whichever mode matches how you actually take deposits.</p>
                    <div class="deprow">
                      <span class="radio rperc"><span class="dot"></span> Percentage of total
                        <span class="numbox depbox">50</span><span class="unit">%</span></span>
                      <span class="radio rflat"><span class="dot"></span> Flat amount
                        <span class="unit">&pound;</span><span class="numbox">0.00</span></span>
                    </div>
                  </div>
                  <p class="fhint2">Markup and discount are set per product (<b>Products</b> &rarr; Edit &rarr; Pricing overrides).</p>
                </div>

                <!-- Scene B: what the customer sees + the WT charge -->
                <div class="osc scB">
                  <div class="fset">
                    <div class="fset-lg">Prices on the customer quote</div>
                    <div class="chkrow"><span class="tick tPrice">&check;</span> Show the price of each blind</div>
                    <p class="fhint">Ticked: the quote PDF and the customer&rsquo;s online quote list a unit price and line total for
                       every blind. Unticked: those per-blind prices are hidden and the customer only sees the quote total.</p>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">Sizes on the customer quote</div>
                    <div class="chkrow"><span class="tick tSize">&check;</span> Show the size of each blind</div>
                    <p class="fhint">Ticked: the quote shows each blind&rsquo;s size (width &times; drop) &mdash; right for trade orders.
                       Unticked: sizes are hidden (retail style), leaving just the description.</p>
                  </div>
                  <div class="fset wtset">
                    <div class="fset-lg">WT charge &mdash; the &ldquo;Wally tax&rdquo; (internal)</div>
                    <div class="chkrow"><span class="tick tWt">&check;</span> Enable the Wally tax <span class="muted">(WT charge)</span></div>
                    <div class="wtin">
                      <div class="wtq">What it adds to the quote builder</div>
                      <div class="wtrow">
                        <span class="wtlbl">WT <span class="wtnote">(internal &mdash; never shown to the customer)</span></span>
                        <span class="unit">&pound;</span><span class="numbox">45.00</span><span class="setbtn">Set</span>
                      </div>
                      <div class="okbanner">WT set to &pound;45.00 (internal &mdash; not shown to the customer).</div>
                    </div>
                  </div>
                </div>

                <!-- Scene C: the automatic bits -->
                <div class="osc scC">
                  <div class="fset">
                    <div class="fset-lg">Default sale type <span class="pill">factory login only</span></div>
                    <div class="salerow">When you click <b>New</b>, start as <span class="selectbox">Trade</span></div>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">New order alerts</div>
                    <div class="chklab">Email me when a customer accepts a quote online</div>
                    <div class="box wide g6">
                      <span class="ph">orders@yourbusiness.co.uk</span>
                      <span class="val">orders@demoblinds.example</span>
                      <span class="bad">orders@demoblinds</span>
                    </div>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">Factory &mdash; new order received <span class="pill">factory login only</span></div>
                    <div class="chklab">Email the factory when a trade order lands in the queue</div>
                    <div class="box wide"><span class="ph">factory@yourbusiness.co.uk</span></div>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">Auto-order bought-in items <span class="pill">factory login only</span></div>
                    <div class="chkrow"><span class="tick">&check;</span> Automatically order bought-in blinds from their supplier when an
                      order is placed <span class="muted">&mdash; off by default</span></div>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">Auto-place in-house orders</div>
                    <div class="chkrow"><span class="tick on">&check;</span> Send in-house orders straight to the workshop when accepted</div>
                  </div>
                  <div class="fset">
                    <div class="fset-lg">Paid-in-full receipt</div>
                    <div class="chkrow"><span class="tick on">&check;</span> Email a receipt when an order is paid in full</div>
                  </div>
                </div>

                <!-- Scene D: how your emails are signed, the footer, Save -->
                <div class="osc scD">
                  <div class="frow">
                    <div class="fld"><label>Email &ldquo;from&rdquo; name</label>
                      <div class="box g8"><span class="ph">&nbsp;</span><span class="val">Demo Blinds</span></div></div>
                    <div class="fld"><label>Reply-to email</label>
                      <div class="box g8"><span class="ph">&nbsp;</span><span class="val">hello@demoblinds.example</span></div></div>
                  </div>
                  <div class="fld qfield"><label>Quote footer (printed at the bottom of the PDF)</label>
                    <div class="ta g8"><span class="ph">&nbsp;</span><span class="val">Thank you for your custom &mdash; 5-year guarantee.</span></div></div>
                  <div class="save">Save quote defaults</div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> The letters in front of your quote numbers.</b>
                  <b class="c2"><span class="n">2</span> VAT &mdash; unlocks once your VAT number is set; remembered per quote.</b>
                  <b class="c3"><span class="n">3</span> The deposit that lands when a quote is accepted.</b>
                  <b class="c4"><span class="n">4</span> What the customer sees: prices, sizes&hellip;</b>
                  <b class="c5"><span class="n">5</span> &hellip;and the Wally tax (WT charge) &mdash; internal only.</b>
                  <b class="c6"><span class="n">6</span> The boxes that do things for you.</b>
                  <b class="c7"><span class="n">7</span> &#9888; Mistype an address and it is quietly thrown away.</b>
                  <b class="c8 good"><span class="n">8</span> Sign your emails, add a footer, save.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Quoting</b> tab is not one form &mdash; it is <b>four</b>, stacked down the page: <b>Default margins</b>,
             <b>Measurements</b>, <b>Quote defaults</b> (the one this guide is about) and <b>Bank details for customer payments</b>.
             Each has <b>its own save button</b>. So <b>Save quote defaults</b> saves <em>this</em> form and nothing else &mdash; if you also
             changed a margin or the measurement unit higher up, go back and press their buttons too. Nothing is lost noisily; it simply
             isn&rsquo;t saved.</p>

          <p>Now work down the form:</p>
          <ul class="steps">
            <li><b>Quote prefix</b> &mdash; the few letters in front of every quote number, up to twenty characters. It is stored
                <b>in capitals</b> whatever you type, so <code>bri</code> saves as <code>BRI</code>. The number is built as
                <b>PREFIX-YEAR-0001</b> &mdash; your first quote of this year is <code>BRI-2026-0001</code>, then <code>0002</code>, and it
                starts again at <code>0001</code> each January. Leave it <b>blank</b> and the system uses the first three letters of your
                company name instead (and <code>QTE</code> if even that is empty), so it is worth setting properly.</li>
            <li><b>VAT %</b> &mdash; you can only charge VAT once you are <b>VAT-registered</b>, so this box is
                <b>locked until you have entered your VAT number</b> on the <b>Company</b> tab. With no VAT number it sits
                <b>greyed out at 0</b>, with a note pointing you to the Company tab, and the server refuses to save any rate
                above zero. Put your VAT number in first and the box unlocks &mdash; it then reads your rate (<b>20</b> for
                most), which you change only if yours is different. At <b>0</b> the quote hides the <b>Subtotal</b> and
                <b>VAT</b> lines completely &mdash; the customer just sees a <b>Total</b>. The box accepts up to 99; the
                server allows 0&ndash;100 and trims anything outside that. It also decides what your accounting export says:
                above zero it exports as <code>20% (VAT on Income)</code>, at zero as <code>No VAT</code>.</li>
            <li><b>Default deposit</b> &mdash; two radio buttons, each with its own number box: <b>Percentage of total</b> (50 to start with)
                or <b>Flat amount</b> in pounds. Both boxes stay editable whichever radio is picked; the radio only decides which figure is
                used. The figure is <b>seeded onto a quote the moment it moves into Accepted</b>, and only if that quote&rsquo;s deposit is
                still blank &mdash; anything you typed by hand is never overwritten. A flat amount is <b>capped at the order total</b>, so a
                &pound;100 flat deposit on an &pound;80 order asks for &pound;80. You will see the figure before acceptance as
                <b>&ldquo;Deposit due on acceptance&rdquo;</b> on the quote, as the <b>&ldquo;Suggested 50%&rdquo;</b> button on the Deposit
                panel, and on the customer&rsquo;s own online quote.</li>
            <li><b>The grey line underneath</b> &mdash; <em>&ldquo;Markup and discount are set per product (Products &rarr; Edit &rarr;
                Pricing overrides)&rdquo;</em>. Not a setting, just a signpost: this screen does not hold your margins.</li>
            <li><b>Prices on the customer quote</b> &mdash; <b>Show the price of each blind</b>. Ticked, the PDF and the online quote list a
                unit price and line total for every blind; unticked, the customer sees the quote total only. One extra for trade: with prices
                showing on a quote raised for one of your <b>trade accounts</b>, the PDF gains a whole extra <b>Discount</b> column between
                Unit and Total.</li>
            <li><b>Sizes on the customer quote</b> &mdash; <b>Show the size of each blind</b>. Ticked shows <b>width &times; drop</b> on every
                line, which is what a trade customer wants to check before they order; unticked hides them for the tidier retail look,
                leaving just the description. Both of these start <b>ticked</b>.</li>
            <li><b>WT charge &mdash; the &ldquo;Wally tax&rdquo; (internal)</b> &mdash; off unless you tick it. A discretionary charge you can
                quietly add to a job that is going to be more trouble than it is worth (an awkward customer, a fiddly fit &mdash; you know the
                ones). Ticking it puts a small <b>WT</b> box on the quote builder, labelled
                <b>&ldquo;WT (internal &mdash; never shown to the customer)&rdquo;</b>: type a figure, press <b>Set</b>, and it confirms
                <em>&ldquo;WT set to &pound;45.00 (internal &mdash; not shown to the customer).&rdquo;</em> Clear the box and press Set again
                and you get <em>&ldquo;WT cleared.&rdquo;</em> Up to &pound;99,999.99. It is added <b>before VAT</b>, and if
                &ldquo;Show the price of each blind&rdquo; is on it is <b>spread proportionally across the blind prices</b> (the odd penny
                landing on the last line) so the figures still add up; otherwise it simply lifts the total. Your customer <b>never</b> sees
                the letters WT, the words Wally tax, or a separate line for it &mdash; not on the quote, the PDF, or the invoice. Try to set
                one without ticking this box first and the builder refuses:
                <code>WT is not enabled &mdash; turn it on in Settings &rarr; Quoting first.</code></li>
            <li><b>Default sale type</b> <span class="muted">(factory login only)</span> &mdash; a dropdown: <b>When you click New, start as
                Trade</b> or <b>Retail</b>. It only decides which way the <b>New</b> screen opens; you can flip it there for a one-off.</li>
            <li><b>New order alerts</b> &mdash; an email address. When a customer clicks <b>Accept</b> on a quote link you sent them, this
                address gets a message, subject <em>&ldquo;New order &mdash; BRI-2026-0001 accepted (Mrs Hall)&rdquo;</em>, opening
                <em>&ldquo;Good news &mdash; a new order has come in.&rdquo;</em> It fires on <b>online</b> acceptance only &mdash; marking a
                quote accepted yourself in the builder sends nothing. Leave it blank to turn it off.</li>
            <li><b>Factory &mdash; new order received</b> <span class="muted">(factory login only)</span> &mdash; where the workshop is told a
                trade order has landed in the queue. Leave blank to use the address above.</li>
            <li><b>Auto-order bought-in items</b> <span class="muted">(factory login only)</span> &mdash; <b>off by default, and leave it off
                until you are sure</b>. On, any bought-in line (a PF Venetian from Hunter Douglas, say) is emailed to its supplier the moment
                the order is placed. That is a <b>real purchase order</b> going out on its own. A supplier with no email on file is left for
                you to order by hand rather than failing silently.</li>
            <li><b>Auto-place in-house orders</b> &mdash; <b>already ticked</b>. When a quote is accepted and <b>every</b> blind on it is one
                you make yourself, it skips the separate <b>Place order</b> step, goes straight to the factory queue with a due date stamped
                on it, and tells you <em>&ldquo;Sent straight to the workshop &mdash; all in-house, no supplier order needed.&rdquo;</em> Any
                quote with a bought-in line is unaffected &mdash; those still say <em>&ldquo;Order accepted &mdash; place it below (suppliers
                get emailed their lines).&rdquo;</em> and wait for you.</li>
            <li><b>Paid-in-full receipt</b> &mdash; <b>already ticked</b>. When a payment settles an order&rsquo;s balance to zero, the
                customer is emailed a thank-you receipt (the order, headed &ldquo;Receipt&rdquo;, showing paid in full) &mdash; subject
                <em>&ldquo;Receipt BRI-2026-0001 &mdash; paid in full &middot; Demo Blinds&rdquo;</em>. Two things stop it happening quietly:
                it goes <b>once per order, ever</b>, and only if that customer has a valid <b>email address on file</b>.</li>
            <li><b>Email &ldquo;from&rdquo; name</b> and <b>Reply-to email</b> &mdash; the name your emails are signed with and where replies
                land. These are <b>not just for quotes</b>: your <b>purchase orders to suppliers</b> and your <b>appointment confirmations</b>
                go out under the same name and reply-to, so a wrong address here means a supplier&rsquo;s reply goes nowhere.</li>
            <li><b>Quote footer (printed at the bottom of the PDF)</b> &mdash; a line or two of your own: a thank-you, a lead time, a
                guarantee. It prints at the foot of the quote PDF <b>and</b> on the customer&rsquo;s online quote page. Leave it empty and
                the block simply doesn&rsquo;t print.</li>
          </ul>

          <p>Then press <b>Save quote defaults</b>. The page reloads and a green bar appears across the <b>top</b> reading
             <b>&ldquo;Quote settings saved.&rdquo;</b></p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>A mistyped email address is thrown away silently.</b> If what you put in
             <b>New order alerts</b> (or the factory address) is neither blank nor a proper email, that one setting is skipped &mdash; no
             error, no red message, and you still get the green &ldquo;Quote settings saved.&rdquo; The old address simply reappears when the
             page reloads. After saving, <b>glance back at the box</b> and check your address is still sitting in it.</div></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>VAT is remembered per quote.</b> Each quote keeps the rate it was
             <b>created</b> with. Change 20 to 5 here today and every quote you have already raised still says 20 &mdash; only new ones pick
             the new rate up. That is deliberate (an old quote must still add up), but it surprises people, so if a rate really has changed,
             expect to re-create anything still outstanding.</div></div>

          <div class="oops"><b>The one error you might actually see:</b>
             <code>Settings saved, but the deposit-mode columns are missing. Run migrate_deposit_flat_mode.php to unlock the flat-amount
             option.</code> &mdash; everything else saved, but this copy of the database predates the flat-amount deposit, so only the
             percentage was kept. If something bigger goes wrong you get <code>Could not save settings: </code> followed by the reason.
             Either way, ring whoever looks after your setup rather than retyping it all.</div>

          <p><b>What this screen touches.</b> The <b>quote builder</b> (the quote number, the VAT line, &ldquo;Deposit due on
             acceptance&rdquo;, the WT box), the <b>quote PDF</b> and the <b>customer&rsquo;s online quote</b> (prices, sizes, the footer),
             the <b>factory queue</b> and the fulfilment stage behind it (auto-place in-house), your <b>supplier and appointment emails</b>
             (the from-name and reply-to), and your <b>accounting export</b> (the VAT rate). One form, a long reach &mdash; which is why it is
             worth ten minutes now.</p>

          <p class="muted">One last thing: every grey explanation under these boxes is a hint, and <b>Compact mode</b> hides the lot to fit
             more on screen. If your screen looks barer than this guide, that is why &mdash; the settings themselves are all still there.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Scene A. Quote prefix types in: BRI.',
                'Your quote numbers need a few letters in front of them. Type up to twenty characters — it is stored in capitals whatever you type, so b-r-i becomes B-R-I. Your first quote of this year becomes B-R-I, twenty twenty-six, oh-oh-oh-one, then oh-oh-oh-two, and so on. Leave it blank and the system takes the first three letters of your company name instead, so it is worth setting properly.', 1],
            ['0:20', 'VAT % box highlighted — needs a VAT number first.',
                'VAT sits next to it — but you can only charge VAT once you are VAT registered, so this box stays locked, greyed out at nought, until you have put your VAT number on the Company tab. Do that and it unlocks, reading your rate, twenty for most; change it only if yours is different. Set it to nought and the quote shows just a total, with no subtotal or VAT line at all. And here is the important bit: each quote remembers the rate it was created with, so changing this today will not touch a single quote you have already raised. Only the new ones.', 2],
            ['0:40', 'Percentage radio selected; its box reads 50.',
                'Next, the default deposit. A percentage of the total, or a flat pound amount — pick the one that matches how you actually take money. Fifty per cent of a four hundred pound order seeds two hundred pounds the moment that quote is accepted, and only if the deposit is still blank, so anything you typed yourself is safe. A flat amount is capped at the order total, so a hundred pound deposit on an eighty pound order asks for eighty. You will see it beforehand as deposit due on acceptance. And that grey line underneath is just a signpost: your markup and discount are set per product, not here.', 3],
            ['1:10', 'Scene B. Prices, sizes and WT all ticked.',
                'Now three boxes that decide what your customer actually reads. Show the price of each blind: ticked, every blind gets its own unit price and line total; unticked, they see one total and nothing else. Show the size of each blind: ticked shows width by drop, which is what a trade customer wants to check; unticked hides them, which is the tidier retail look. And if the quote is for one of your trade accounts with prices showing, a discount column appears on it too.', 4],
            ['1:33', 'WT fieldset lit; the builder row and its flash show.',
                'And this one is the Wally tax, the W-T charge. A quiet extra you can add to a job that is going to be more trouble than it is worth. Ticking it puts a small W-T box on the quote builder, marked internal, never shown to the customer, where you type a figure and press Set. It confirms: W-T set to forty-five pounds, internal, not shown to the customer. It goes in before VAT, and if you are showing per-blind prices it is spread across them so the sums still add up. Your customer never sees the letters W-T, never sees the words Wally tax, and never sees a separate line for it — not on the quote, the P-D-F, or the invoice. Try to set one before ticking this box and the builder simply refuses.', 5],
            ['2:10', 'Scene C. Sale type Trade; alert address fills; two ticks on.',
                'Then the boxes that do things for you. Default sale type decides whether the New button opens on trade or retail. New order alerts emails you the moment a customer clicks accept on a quote link — leave it blank to switch it off. Auto-place in-house orders is on already: when a quote is accepted and every blind on it is one you make yourself, it skips the place order step and goes straight to the workshop queue with a due date stamped on it. Anything bought in from a supplier still waits for you to place it by hand. And paid-in-full receipt, also on, emails the customer a thank-you receipt the moment their balance hits zero — once per order, and only if you have an email address for them.', 6],
            ['2:45', 'The slip: a half-typed address saves, then vanishes.',
                'One thing to watch. If you mistype either of those email addresses, nothing tells you. You get the green quote settings saved message like normal, but the address is thrown away and the old one comes back when the page reloads. So after saving, glance at the box and check your address is still sitting in it.', 7],
            ['3:05', 'Scene D. From-name, reply-to and footer fill; Save; green bar.',
                'Last three. The from name and reply-to are how your emails are signed and where replies land — and these are used on more than quotes: your purchase orders to suppliers and your appointment confirmations go out under the same name, so do get them right. The footer prints at the bottom of every quote P-D-F and on the customer\'s online quote; leave it empty and nothing prints. Then save quote defaults, and the green bar across the top reads: quote settings saved.', 8],
        ],
];
