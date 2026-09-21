<?php
declare(strict_types=1);

/**
 * Guide: trade-terms-page
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /admin/trade-terms.php — a READ-ONLY report of the buying discounts
 * the supplier has set for this account. That screen has no form controls at
 * all, so its scenes draw tables only: no .fld/.box, no radios, no Save. The
 * two "where you see it" scenes show OTHER screens (InstaPrice's price card and
 * Products → Edit), so those do carry the real controls those screens have.
 */

return [
        'aud'     => 'admin',
        'section' => 'Setup',
        'title'   => 'Your trade terms',
        // The page is a Setup-menu item in its own right (sidebar.php: Setup →
        // Products / Users / Settings / Trade terms / Billing), NOT a Settings
        // tab — and "Trade" is a different, super-admin-only nav section, so
        // filing it under that heading sent people to the wrong place.
        'eyebrow' => 'Setup · Trade terms',
        'blurb'   => 'The deal you buy on: the discounts your supplier gives you, where they come off, and why they are not the discount you give your own customer.',
        'lede'    => 'This page is a <b>statement of the deal you buy on</b>. It lists the discounts your supplier takes off
                      <b>what you pay them</b> &mdash; per product, and sometimes only on one system, one band or one option.
                      It is <b>read-only</b>: your supplier sets these on their side, so there is nothing to fill in and no Save
                      button. Every line on it is already working on every price you see.',
        'open'    => '/admin/trade-terms.php',
        'css'     => '
          /* This screen has NO form controls — tables and text only. Scenes are
             switched by data-step; inside the page scene the parts fade in one
             at a time (visibility, so nothing jumps about). */
          .gd .ttsc{ display:none; }
          .gd .stage[data-step="0"] .scPage,
          .gd .stage[data-step="1"] .scPage,
          .gd .stage[data-step="2"] .scPage,
          .gd .stage[data-step="3"] .scPage,
          .gd .stage[data-step="4"] .scPage,
          .gd .stage[data-step="5"] .scPage{ display:block; }
          .gd .stage[data-step="6"] .scIp{ display:block; }
          .gd .stage[data-step="7"] .scEdit{ display:block; }
          .gd .stage[data-step="8"] .scPromo{ display:block; }

          .gd .rv{ opacity:0; visibility:hidden; transition:opacity .28s; }
          /* Step 0 is the poster: the real screen with nothing filled in yet —
             its heading and sub-heading only, exactly as the page paints before
             any row is read out. (Same contract as products-options / orders-
             pipeline, whose first scene also shows at step 0.) */
          .gd .stage[data-step="0"] .r1,
          .gd .stage[data-step="1"] .r1, .gd .stage[data-step="2"] .r1, .gd .stage[data-step="3"] .r1,
          .gd .stage[data-step="4"] .r1, .gd .stage[data-step="5"] .r1,
          .gd .stage[data-step="2"] .r2, .gd .stage[data-step="3"] .r2, .gd .stage[data-step="4"] .r2, .gd .stage[data-step="5"] .r2,
          .gd .stage[data-step="3"] .r3, .gd .stage[data-step="4"] .r3, .gd .stage[data-step="5"] .r3,
          .gd .stage[data-step="4"] .r4, .gd .stage[data-step="5"] .r4,
          .gd .stage[data-step="5"] .r5{ opacity:1; visibility:visible; }
          /* the row being talked about gets a calm outline (never the header) */
          .gd .stage[data-step="3"] .ttr.r3:not(.hd),
          .gd .stage[data-step="4"] .ttr.r4{ box-shadow:inset 0 0 0 2px var(--accent); }

          /* page chrome */
          .gd .ttsub{ font-size:.72rem; color:var(--faint); margin:-.7rem 0 .7rem; }
          .gd .tth2{ font-size:.78rem; font-weight:700; color:var(--ink); margin:.1rem 0 .3rem; }
          .gd .ttlead{ font-size:.7rem; color:var(--faint); margin:0 0 .55rem; max-width:34rem; line-height:1.45; }
          .gd .ttlead b{ color:var(--ink); }
          .gd .ttnote{ font-size:.68rem; color:var(--faint); margin:.55rem 0 0; max-width:34rem; line-height:1.45; }
          .gd .ttnote b{ color:var(--ink); }

          /* the two real tables — a header row and data rows, nothing clickable */
          .gd .ttg{ border:1px solid var(--line); border-radius:8px; overflow:hidden; max-width:31rem; }
          .gd .ttr{ display:grid; grid-template-columns:4.4rem 1fr 1.35fr; border-top:1px solid var(--line-2); }
          .gd .ttg.four .ttr{ grid-template-columns:3.9rem 1fr 1.2fr 5rem; }
          .gd .ttr:first-child{ border-top:none; }
          .gd .ttc{ background:var(--surface); padding:.3rem .45rem; font-size:.72rem; color:var(--ink); }
          .gd .ttr.hd .ttc{ background:var(--panel); color:var(--faint); font-weight:700; font-size:.6rem;
                            text-transform:uppercase; letter-spacing:.04em; }
          .gd .ttc.pct{ text-align:right; font-weight:800; color:#065f46; font-variant-numeric:tabular-nums; }
          .gd .ttr.hd .ttc.pct{ color:var(--faint); font-weight:700; }
          .gd .ttc .sub{ display:block; font-size:.62rem; color:var(--faint); }
          .gd .ttc.until{ font-size:.66rem; color:var(--faint); }

          /* InstaPrice price panel (step 6) — mirrors the real #ip-price card
             (instaprice/index.php renderPricePanel): a plain bordered card with
             NO heading bar, six rows, and the two EDITABLE rows drawn as real
             number inputs (amber label + box), not as static text. */
          .gd .ipp{ border:1px solid var(--line); border-radius:10px; padding:.45rem .6rem; max-width:19rem; background:var(--surface); }
          .gd .iprow{ display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.28rem 0; font-size:.74rem; color:var(--ink); }
          .gd .iprow + .iprow{ border-top:1px solid var(--line-2); }
          .gd .iprow .lbl{ color:var(--soft); }
          .gd .iprow .val{ font-variant-numeric:tabular-nums; font-weight:600; }
          .gd .iprow.trade .lbl, .gd .iprow.trade .val{ color:#065f46; font-weight:700; }
          .gd .iprow.edit .lbl{ color:#b45309; font-weight:700; }
          /* .numbox is not global — a number input, same as the real control */
          .gd .numbox{ display:inline-flex; justify-content:flex-end; min-width:4.6rem;
                       border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface);
                       padding:.16rem .4rem; font-size:.74rem; font-variant-numeric:tabular-nums; color:var(--ink); }
          .gd .iprow.sell{ border-top:2px solid var(--border-strong,#c7ccd4); margin-top:.2rem; padding-top:.4rem; }
          .gd .iprow.sell .lbl{ font-weight:700; color:var(--ink); font-size:.82rem; }
          .gd .iprow.sell .val{ font-weight:800; font-size:1rem; color:var(--accent-ink); }

          /* product-edit fragment (step 7) — the read-only green "from your supplier" cell */
          .gd .pedit{ display:flex; gap:1.6rem; align-items:flex-start; border:1px solid var(--line);
                      border-radius:9px; padding:.6rem .7rem; background:var(--panel); max-width:22rem; }
          .gd .pelbl{ font-size:.62rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint);
                      font-weight:700; margin-bottom:.25rem; }
          .gd .rofig{ font-weight:700; color:#065f46; font-size:.82rem; font-variant-numeric:tabular-nums; }
          .gd .rocap{ font-size:.62rem; color:var(--faint); margin-top:.1rem; }
          .gd .mkbox{ height:26px; width:5rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                      background:var(--surface); display:flex; align-items:center; padding:0 .45rem; font-size:.78rem; color:var(--ink); }
          .gd .exhelp{ font-size:.68rem; color:var(--faint); margin:.5rem 0 0; max-width:30rem; line-height:1.45; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / admin / trade-terms</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a>Settings</a><a class="on">Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- The page itself: header → lead → table → note -->
                <div class="ttsc scPage">
                  <div class="card-t rv r1">Trade terms</div>
                  <p class="ttsub rv r1">The buying discounts your account gets from Beverley Blinds.</p>

                  <div class="tth2 rv r2">Your standing discounts</div>
                  <p class="ttlead rv r2">These come off the <b>trade price</b> you pay Beverley Blinds &mdash; applied
                     automatically on every quote you build, so your costs are already reduced. (They&rsquo;re not a discount
                     to your own customers; that&rsquo;s set separately under Products.)</p>

                  <div class="ttg">
                    <!-- Row order matches the real query: ORDER BY p.name,
                         td.discount_percent DESC (admin/trade-terms.php). -->
                    <div class="ttr hd rv r3"><span class="ttc pct">Discount</span><span class="ttc">Product</span><span class="ttc">Applies to</span></div>
                    <div class="ttr rv r3"><span class="ttc pct">50%</span><span class="ttc">Bev Roller Blinds</span><span class="ttc">All systems &middot; All bands</span></div>
                    <div class="ttr rv r4"><span class="ttc pct">15%</span><span class="ttc">Bev Roller Blinds</span><span class="ttc">Option: Fascia Options &rarr; LL 70mm Cassette</span></div>
                    <div class="ttr rv r4"><span class="ttc pct">35%</span><span class="ttc">Bev Vertical Blinds</span><span class="ttc">Vogue &middot; Band A</span></div>
                  </div>

                  <p class="ttnote rv r5"><b>Where you&rsquo;ll see it:</b> when you price a discounted product, the
                     <em>base / trade price</em> is already reduced by the percentage above. Your own markup and any discount
                     you give your customer are then applied on top.</p>
                </div>

                <!-- Where the money shows up: InstaPrice -->
                <div class="ttsc scIp">
                  <div class="card-t">InstaPrice &mdash; Bev Roller Blinds</div>
                  <div class="ipp">
                    <div class="iprow trade"><span class="lbl">Trade discount</span><span class="val">50.00% (&minus;&pound;10.60)</span></div>
                    <div class="iprow"><span class="lbl">Price</span><span class="val">&pound;10.61</span></div>
                    <div class="iprow edit"><span class="lbl">Discount %</span><span class="numbox">0.00</span></div>
                    <div class="iprow"><span class="lbl">Discounted price</span><span class="val">&pound;10.61</span></div>
                    <div class="iprow edit"><span class="lbl">Mark up %</span><span class="numbox">100.00</span></div>
                    <div class="iprow sell"><span class="lbl">Sell price</span><span class="val">&pound;21.22</span></div>
                  </div>
                  <p class="exhelp">The green line is a <b>cost</b> figure. The quote builder shows the same thing as
                     <b>trade discount 50%</b> in the line&rsquo;s cost breakdown. Staff without <b>View costs</b> see the
                     price, never the breakdown. The two amber rows are the only boxes you can type in &mdash; and
                     <b>Mark up %</b> reads <b>Margin %</b> if your business works in margin.</p>
                </div>

                <!-- Why Discount % is not a box on the product -->
                <div class="ttsc scEdit">
                  <div class="card-t">Products &rarr; Edit &mdash; Pricing per system</div>
                  <div class="pedit">
                    <div>
                      <div class="pelbl">Discount %</div>
                      <div class="rofig">50.00%</div>
                      <div class="rocap">from your supplier</div>
                    </div>
                    <div>
                      <div class="pelbl">Markup %</div>
                      <div class="mkbox">100</div>
                      <div class="rocap">yours to change</div>
                    </div>
                  </div>
                  <p class="exhelp">No box, no typing: the buying discount is your supplier&rsquo;s, so the two can never
                     stack. The same happens to <b>Buying discount %</b> on the price table. (That second heading follows
                     your pricing basis, so on a margin-based business it reads <b>Margin %</b>.)</p>
                </div>

                <!-- Current promotions (only rendered when something is running) -->
                <div class="ttsc scPromo">
                  <div class="tth2">Current promotions</div>
                  <p class="ttlead">Time-limited offers running now. Where a promotion and a standing discount both apply,
                     you get the larger.</p>
                  <div class="ttg four">
                    <div class="ttr hd"><span class="ttc pct">Discount</span><span class="ttc">Product</span><span class="ttc">Applies to</span><span class="ttc">Until</span></div>
                    <div class="ttr"><span class="ttc pct">20%</span><span class="ttc">Bev Roller Blinds<span class="sub">Autumn roller offer</span></span><span class="ttc">All systems &middot; All bands</span><span class="ttc until">31 Oct 2026</span></div>
                    <div class="ttr"><span class="ttc pct">10%</span><span class="ttc">Bev Vertical Blinds<span class="sub">Trade loyalty deal</span></span><span class="ttc">All systems &middot; All bands</span><span class="ttc until">ongoing</span></div>
                  </div>
                  <p class="exhelp">Nothing running? The whole <b>Current promotions</b> heading is simply not on the page.
                     That is normal, not a fault.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Setup &rarr; Trade terms &mdash; who you buy from.</b>
                  <b class="c2"><span class="n">2</span> It comes off what <em>you</em> pay, not your customer.</b>
                  <b class="c3"><span class="n">3</span> Discount &middot; Product &middot; Applies to.</b>
                  <b class="c4"><span class="n">4</span> Narrower scopes &mdash; an option, then a system and band.</b>
                  <b class="c5"><span class="n">5</span> Nothing to switch on &mdash; it is already applied.</b>
                  <b class="c6 good"><span class="n">6</span> InstaPrice shows it in green.</b>
                  <b class="c7"><span class="n">7</span> That is why Discount % is not a box.</b>
                  <b class="c8"><span class="n">8</span> Promotions &mdash; you get the larger, never both.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Trade terms</b> lives under <b>Setup</b> in the left-hand menu. It is a <b>read-only statement of the deal
             you buy on</b> &mdash; the discounts your supplier has agreed for your account. There is not one box, tick or
             dropdown on it, and no <b>Save</b> button, because there is nothing for you to save. Your supplier sets these
             on their own trade-account screen; if you think a figure is wrong, the person to ring is them, not us. The
             heading reads <em>&ldquo;The buying discounts your account gets from &hellip;&rdquo;</em> with their company
             name &mdash; and if it just says <b>your supplier</b>, that is only a name we could not look up. Nothing is broken.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The one thing people get wrong.</b> There are
             <b>two completely different discounts</b> in this system and they pull in opposite directions.
             <b>(a)</b> The <b>trade discount</b> on this page is money off <b>what you pay your supplier</b> &mdash; it lowers
             <em>your cost</em>. <b>(b)</b> <b>Your own discount</b> is money off <b>what your customer pays you</b> &mdash; you
             set that yourself, under <b>Products</b> or on the quote. A bigger trade discount does <b>not</b> make your
             customer&rsquo;s price drop on its own: it widens your margin, until you choose to pass some of it on.</div></div>

          <p class="prose"><b>Reading a row.</b> The table has three columns.</p>
          <ul class="steps">
            <li><b>Discount</b> &mdash; the percentage, e.g. <code>50%</code>. Trailing zeros are trimmed here, so the same
                deal reads <code>50%</code> on this page and <code>50.00%</code> on the product edit screen. It is the same
                number, printed two ways.</li>
            <li><b>Product</b> &mdash; which of your products it is on. Only products that come <b>from your supplier</b> can
                carry one. Anything you source elsewhere, or make yourself, will never appear here however good your terms are.</li>
            <li><b>Applies to</b> &mdash; the <b>scope</b>, and this is the bit worth slowing down for.
                <code>All systems &middot; All bands</code> means every version of that product &mdash; every system, every
                price band. <code>Vogue &middot; Band A</code> means that one system, in that one band, and nothing else.
                And a line that starts with the word <b>Option</b> &mdash; <code>Option: Fascia Options &rarr; LL 70mm
                Cassette</code> &mdash; is not the blind at all: it is a discount on a <b>part</b>, written
                <em>Option: the option group &rarr; the one choice</em>. It comes off what that option costs you,
                before your options markup is added. Leave the choice off and the row reads just
                <code>Option: Fascia Options</code>, meaning every choice in that group.</li>
            <li><b>Nothing to do.</b> Every row is already live on every price you build. There is no switch to turn one on,
                no tick to apply it, and no order in which they have to be used.</li>
          </ul>

          <p><b>Where you&rsquo;ll see it.</b> The page says it plainly: <em>when you price a discounted product, the base /
             trade price is already reduced by the percentage above. Your own markup and any discount you give your customer
             are then applied on top.</em> In practice there are two places you can watch it happen. In <b>InstaPrice</b>, a
             green row labelled <b>Trade discount</b> appears at the top of the price card, reading something like
             <code>50.00% (&minus;&pound;10.60)</code> &mdash; and the <b>Price</b> underneath is already the reduced one. The
             rest of that card is unchanged: <b>Discount&nbsp;%</b>, <b>Discounted price</b>, <b>Mark up&nbsp;%</b> (or
             <b>Margin&nbsp;%</b>) and <b>Sell price</b>, with the two percentage boxes still yours to type in. In the
             <b>quote builder</b>, the line&rsquo;s cost breakdown picks up <code>trade discount 50%</code>. Both of those are
             <b>cost</b> figures, so a staff member whose user record does not have the <b>View costs</b> tick
             (<b>Users</b> &rarr; edit the user) sees the price but never the breakdown. That is deliberate, not a fault.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Why can I not type in Discount %?</b> On a product your
             supplier gives you a trade discount on, the <b>Discount %</b> under <b>Products &rarr; Edit &rarr; &ldquo;Pricing
             per system&rdquo;</b> &mdash; and the <b>Buying discount %</b> on that product&rsquo;s price table &mdash; stop
             being boxes you can type in. They become a read-only green figure with <b>from your supplier</b> underneath.
             That is on purpose: one buying discount, theirs, so the two can never stack and double-discount you into a loss.
             The <b>Markup %</b> beside it is still entirely yours to edit &mdash; and if your business is set to work in
             margin rather than markup, that same heading reads <b>Margin %</b> instead. Same box, same money, different
             way of saying it.</div></div>

          <p><b>Current promotions.</b> Underneath the standing discounts you may find a second section, <b>Current
             promotions</b> &mdash; time-limited offers your supplier is running. It only appears when something is actually
             running, so <b>no heading means nothing is running today</b>: do not go hunting for it. It has a fourth column,
             <b>Until</b>, showing either a date like <code>31 Oct 2026</code> or the word <code>ongoing</code> for an offer
             with no end date, soonest-ending first. And then the rule that catches people out, in the page&rsquo;s own words:
             where a promotion and a standing discount both apply, <b>you get the larger</b>. The best one wins &mdash; the two
             are never added together.</p>

          <div class="oops"><b>Page says &ldquo;You&rsquo;re on standard terms &mdash; no special discounts set for your
             account.&rdquo;?</b> Nothing is broken. It means your supplier has not set a special deal for you, so you buy at
             the standard trade price like everybody else. You may also see <b>&ldquo;Your trade terms aren&rsquo;t set up
             yet.&rdquo;</b> in a blue strip at the top &mdash; that one means the feature has not been switched on for your
             system yet. In both cases the person to talk to is your supplier; there is nothing on this screen to change.</div>

          <p><b>Don&rsquo;t confuse it with your own trade accounts.</b> If you sell to other businesses yourself, the discount
             you give <em>them</em> is a different thing again: it is held per account on the <b>Trade &rarr; Trade accounts</b>
             screen, and it comes off the base blind only &mdash; never off the options. That whole <b>Trade</b> section of the
             menu is super-admin only, so as an ordinary admin you will not see it at all: if you need a trade account&rsquo;s
             discount changed, ask whoever runs the wholesale side. This page is only ever about what <b>you</b> buy at.</p>

          <p>Worth a look after a price rise, or any time your supplier tells you your terms have changed &mdash; it is the
             quickest way to prove the new deal has actually landed in your prices, rather than taking it on trust and finding
             out on a quote.</p>',
        'script'  => [
            ['0:00', 'Setup → Trade terms.',                       'Trade terms lives under Setup, in the left-hand menu. Open it and the heading tells you what it is: the buying discounts your account gets from your supplier. If it says your supplier rather than a company name, that is just a name we could not look up — nothing is wrong.', 1],
            ['0:14', 'The lead explained.',                        'Read that first line carefully, because it is the bit people get wrong. These discounts come off the trade price you pay your supplier. They make your own costs lower. They are not a discount to your customer — that one you set yourself, under Products.', 2],
            ['0:28', 'First row: 50%, all systems, all bands.',    'Here is the table. Three columns. How much off, which product it is off, and what it applies to. This first line says fifty percent off Bev Roller Blinds, all systems, all bands — so every roller you price, whatever the fabric.', 3],
            ['0:42', 'Narrower rows: an option, then a band.',     'The next two are narrower. One starts with the word Option — that is a discount on a part, not on the blind: fifteen percent off the L L seventy millimetre cassette, wherever you add it to a roller. And the last one is thirty-five percent, but only on Bev Vertical Blinds, on the Vogue system, in Band A. Nothing else.', 4],
            ['0:56', 'Where you will see it.',                     'Now the important part. You do not apply any of this. It is already applied. When you price one of these products the base trade price has already had the percentage taken off it, and your own markup, and any discount you give your customer, go on top of that.', 5],
            ['1:10', 'InstaPrice — green Trade discount row.',     'You can watch it happening. Price a discounted product in InstaPrice and there is a green line at the top of the price card, Trade discount, fifty percent, ten pounds sixty off — and the Price underneath is already the lower one. Below it the card carries on as normal: Discount percent, Discounted price, Mark up percent, Sell price, with those two percentage boxes still yours to type in. If your business works in margin, that second box is labelled Margin percent instead. The quote builder shows the same thing in its cost breakdown. Only people allowed to view costs see either.', 6],
            ['1:26', 'Discount % — from your supplier.',    'This also explains a box you cannot type in. On a product your supplier gives you a discount on, the Discount percent is no longer a box at all — it is a green figure with, from your supplier, underneath it. That is deliberate: one buying discount, theirs, so the two can never double up. The Markup box beside it is still yours to change, and it reads Margin if that is how your business works.', 7],
            ['1:42', 'Promotions, and the empty states.',          'Underneath, if anything is running, you will see Current promotions, with an end date or the word ongoing. Where a promotion and a standing discount both cover the same blind you get the larger of the two, never both added together. And if the page says you are on standard terms, that is fine — it just means no special deal is set. Ring your supplier if you think that is wrong.', 8],
        ],
];
