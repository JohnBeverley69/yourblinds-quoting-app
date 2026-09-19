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
 * the supplier has set for this account. The real screen has no form controls
 * at all, so the demo draws tables only: no .fld/.box, no radios, no Save.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your trade terms',
        'eyebrow' => 'Trade',
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
          .gd .stage[data-step="1"] .scPage,
          .gd .stage[data-step="2"] .scPage,
          .gd .stage[data-step="3"] .scPage,
          .gd .stage[data-step="4"] .scPage,
          .gd .stage[data-step="5"] .scPage{ display:block; }
          .gd .stage[data-step="6"] .scIp{ display:block; }
          .gd .stage[data-step="7"] .scEdit{ display:block; }
          .gd .stage[data-step="8"] .scPromo{ display:block; }

          .gd .rv{ opacity:0; visibility:hidden; transition:opacity .28s; }
          .gd .stage[data-step="1"] .r1, .gd .stage[data-step="2"] .r1, .gd .stage[data-step="3"] .r1,
          .gd .stage[data-step="4"] .r1, .gd .stage[data-step="5"] .r1,
          .gd .stage[data-step="2"] .r2, .gd .stage[data-step="3"] .r2, .gd .stage[data-step="4"] .r2, .gd .stage[data-step="5"] .r2,
          .gd .stage[data-step="3"] .r3, .gd .stage[data-step="4"] .r3, .gd .stage[data-step="5"] .r3,
          .gd .stage[data-step="4"] .r4, .gd .stage[data-step="5"] .r4,
          .gd .stage[data-step="5"] .r5{ opacity:1; visibility:visible; }
          /* the row being talked about gets a calm outline */
          .gd .stage[data-step="3"] .ttr.r3, .gd .stage[data-step="4"] .ttr.r4{ box-shadow:inset 0 0 0 2px var(--accent); }

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

          /* InstaPrice price panel (step 6) */
          .gd .ipp{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:19rem; }
          .gd .ipp .iph{ background:var(--panel); padding:.35rem .6rem; font-size:.66rem; font-weight:700;
                         color:var(--faint); border-bottom:1px solid var(--line); text-transform:uppercase; letter-spacing:.04em; }
          .gd .iprow{ display:flex; justify-content:space-between; gap:.8rem; padding:.28rem .6rem; font-size:.74rem; color:var(--ink); }
          .gd .iprow .lbl{ color:var(--soft); }
          .gd .iprow.trade .lbl, .gd .iprow.trade .val{ color:#065f46; font-weight:700; }
          .gd .iprow.sell{ border-top:1px solid var(--line-2); font-weight:700; }

          /* product-edit fragment (step 7) — the greyed "from your supplier" cell */
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
                <a>Dashboard</a><a>Customers</a><a>Products</a><a>Users</a><a>Settings</a><a class="on">Trade terms</a><a>Billing</a>
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
                    <div class="ttr hd rv r3"><span class="ttc pct">Discount</span><span class="ttc">Product</span><span class="ttc">Applies to</span></div>
                    <div class="ttr rv r3"><span class="ttc pct">50%</span><span class="ttc">Bev Vertical Blinds</span><span class="ttc">All systems &middot; All bands</span></div>
                    <div class="ttr rv r4"><span class="ttc pct">35%</span><span class="ttc">Bev Roller Blinds</span><span class="ttc">Vogue &middot; Band A</span></div>
                    <div class="ttr rv r4"><span class="ttc pct">15%</span><span class="ttc">Bev Roller Blinds</span><span class="ttc">Option: Chain type &rarr; Metal chain</span></div>
                  </div>

                  <p class="ttnote rv r5"><b>Where you&rsquo;ll see it:</b> when you price a discounted product, the
                     <em>base / trade price</em> is already reduced by the percentage above. Your own markup and any discount
                     you give your customer are then applied on top.</p>
                </div>

                <!-- Where the money shows up: InstaPrice -->
                <div class="ttsc scIp">
                  <div class="card-t">InstaPrice &mdash; Bev Vertical Blinds</div>
                  <div class="ipp">
                    <div class="iph">Price</div>
                    <div class="iprow trade"><span class="lbl">Trade discount</span><span class="val">50.00% (&minus;&pound;10.60)</span></div>
                    <div class="iprow"><span class="lbl">Price</span><span class="val">&pound;10.61</span></div>
                    <div class="iprow"><span class="lbl">Discount %</span><span class="val">0.00</span></div>
                    <div class="iprow sell"><span class="lbl">Sell price</span><span class="val">&pound;21.22</span></div>
                  </div>
                  <p class="exhelp">The green line is a <b>cost</b> figure. The quote builder shows the same thing as
                     <b>trade discount 50%</b> in the line&rsquo;s cost breakdown. Staff without <b>View costs</b> see the
                     price, never the breakdown.</p>
                </div>

                <!-- Why Discount % is greyed out on the product -->
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
                     stack. The same happens to <b>Buying discount %</b> on the price table.</p>
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
                  <b class="c4"><span class="n">4</span> Narrower scopes &mdash; a band, then an option.</b>
                  <b class="c5"><span class="n">5</span> Nothing to switch on &mdash; it is already applied.</b>
                  <b class="c6 good"><span class="n">6</span> InstaPrice shows it in green.</b>
                  <b class="c7"><span class="n">7</span> That is why Discount % is greyed out.</b>
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
                And <code>Option: Chain type &rarr; Metal chain</code> is not the blind at all &mdash; it is a discount on a
                <b>part</b>: it comes off what that option costs you, before your options markup is added.</li>
            <li><b>Nothing to do.</b> Every row is already live on every price you build. There is no switch to turn one on,
                no tick to apply it, and no order in which they have to be used.</li>
          </ul>

          <p><b>Where you&rsquo;ll see it.</b> The page says it plainly: <em>when you price a discounted product, the base /
             trade price is already reduced by the percentage above. Your own markup and any discount you give your customer
             are then applied on top.</em> In practice there are two places you can watch it happen. In <b>InstaPrice</b>, a
             green row labelled <b>Trade discount</b> appears above the price, reading something like
             <code>50.00% (&minus;&pound;10.60)</code> &mdash; and the <b>Price</b> underneath is already the reduced one. In the
             <b>quote builder</b>, the line&rsquo;s cost breakdown picks up <code>trade discount 50%</code>. Both of those are
             <b>cost</b> figures, so a staff member whose user record does not have the <b>View costs</b> tick
             (<b>Users</b> &rarr; edit the user) sees the price but never the breakdown. That is deliberate, not a fault.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Why is Discount % greyed out?</b> On a product your
             supplier gives you a trade discount on, the <b>Discount %</b> under <b>Products &rarr; Edit &rarr; &ldquo;Pricing
             per system&rdquo;</b> &mdash; and the <b>Buying discount %</b> on that product&rsquo;s price table &mdash; stop
             being boxes you can type in. They become a read-only green figure with <b>from your supplier</b> underneath.
             That is on purpose: one buying discount, theirs, so the two can never stack and double-discount you into a loss.
             The <b>Markup %</b> beside it is still entirely yours to edit.</div></div>

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
             you give <em>them</em> is a different thing again: it is set per account under <b>Trade &rarr; Trade accounts</b>,
             and it comes off the base blind only &mdash; never off the options. This page is only ever about what <b>you</b>
             buy at.</p>

          <p>Worth a look after a price rise, or any time your supplier tells you your terms have changed &mdash; it is the
             quickest way to prove the new deal has actually landed in your prices, rather than taking it on trust and finding
             out on a quote.</p>',
        'script'  => [
            ['0:00', 'Setup → Trade terms.',                       'Trade terms lives under Setup, in the left-hand menu. Open it and the heading tells you what it is: the buying discounts your account gets from your supplier. If it says your supplier rather than a company name, that is just a name we could not look up — nothing is wrong.', 1],
            ['0:14', 'The lead explained.',                        'Read that first line carefully, because it is the bit people get wrong. These discounts come off the trade price you pay your supplier. They make your own costs lower. They are not a discount to your customer — that one you set yourself, under Products.', 2],
            ['0:28', 'First row: 50%, all systems, all bands.',    'Here is the table. Three columns. How much off, which product it is off, and what it applies to. This first line says fifty percent off Bev Vertical Blinds, all systems, all bands — so every vertical you price, whatever the fabric.', 3],
            ['0:42', 'Narrower rows: a band, then an option.',     'The next two are narrower. Thirty-five percent, but only on the Vogue system in Band A. And the last one starts with the word Option — that is a discount on a part, not the blind: fifteen percent off the metal chain, wherever you add it.', 4],
            ['0:56', 'Where you will see it.',                     'Now the important part. You do not apply any of this. It is already applied. When you price one of these products the base trade price has already had the percentage taken off it, and your own markup, and any discount you give your customer, go on top of that.', 5],
            ['1:10', 'InstaPrice — green Trade discount row.',     'You can watch it happening. Price a discounted product in InstaPrice and there is a green line, Trade discount, fifty percent, ten pounds sixty off — and the price underneath is already the lower one. The quote builder shows the same thing in its cost breakdown. Only people allowed to view costs see either.', 6],
            ['1:26', 'Greyed Discount % — from your supplier.',    'This also explains a box you cannot type in. On a product your supplier gives you a discount on, the Discount percent is greyed out and simply reads, from your supplier. That is deliberate — one buying discount, theirs, so the two can never double up. Your markup box is still yours to change.', 7],
            ['1:42', 'Promotions, and the empty states.',          'Underneath, if anything is running, you will see Current promotions, with an end date or the word ongoing. Where a promotion and a standing discount both cover the same blind you get the larger of the two, never both added together. And if the page says you are on standard terms, that is fine — it just means no special deal is set. Ring your supplier if you think that is wrong.', 8],
        ],
];
