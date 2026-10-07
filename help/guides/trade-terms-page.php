<?php
declare(strict_types=1);

/**
 * Guide: trade-terms-page — "Your trade terms" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors admin/trade-terms.php — a READ-ONLY page under Setup → Trade terms:
 * "The buying discounts your account gets from <supplier>.", the "Your
 * standing discounts" table (Discount / Product / Applies to — "All systems ·
 * All bands", "<system> · Band X", "Option: <group> → <choice>"), the "Where
 * you'll see it" note, "Current promotions" (only when one is running; Until =
 * a date or "ongoing"; the larger of promotion and standing discount wins),
 * and the empty states. Where it shows elsewhere: instaprice/index.php (green
 * "Trade discount" row), quote-builder/edit.php cost breakdown ("trade
 * discount N%", View costs only), admin/products/edit.php Pricing per system
 * (Discount % becomes green "from your supplier").
 *
 * v2: one scene per script line; data-len is worked out from the line's own
 * length (characters ÷ 13.6), so editing a line keeps its scene in step.
 */

$vo = [
    1  => ['Where it lives',
           'Trade terms is in the left-hand menu, under Setup. It shows the buying discounts your supplier gives your account, and the heading names your supplier. There is nothing to fill in, and no Save button. Your supplier sets these on their side. This page simply lets you see the deal you are on.'],
    2  => ['Two different discounts',
           'First, the most important idea. There are two different discounts. The trade discount on this page comes off what you pay your supplier, so it lowers your costs. Your own discount comes off what your customer pays you, and you set that yourself. A bigger trade discount does not lower your customer\'s price. It widens your margin.'],
    3  => ['Your standing discounts',
           'The first section is called Your standing discounts. It is a table with three columns. Discount is the percentage off. Product is which of your products it is on. And Applies to says exactly where it counts. That last column is worth reading slowly.'],
    4  => ['All systems, all bands',
           'All systems, all bands, means every version of that product. Every system, and every price band. So in this example, fifty percent comes off every roller blind you price, whatever the system, and whatever the fabric.'],
    5  => ['Narrower rows',
           'Some rows are narrower. A system and a band, such as Vogue, Band A, means that one system, in that one band, and nothing else. A row that starts with the word Option is a discount on a part, not on the blind. Here, a fascia. It comes off what that option costs you.'],
    6  => ['Already applied',
           'You never apply any of these. They are already working. When you price a discounted product, the trade price has already had the percentage taken off. Your own markup, and any discount you give your customer, then go on top. There is no switch to turn on, and nothing to tick.'],
    7  => ['Watch it in InstaPrice',
           'You can watch it happen. Price a discounted product in InstaPrice, and a green line appears at the top of the price card, called Trade discount. It shows the percentage, and how much it took off. The price underneath is already the lower one. The quote builder shows the same in its cost breakdown, for people allowed to view costs.'],
    8  => ['A box you cannot type in',
           'This also explains a box you cannot type in. On a product with a trade discount, the Discount percent in its pricing is no longer a box. It shows the figure in green, with, from your supplier, underneath. That is on purpose. There is one buying discount, theirs, so the two can never double up.'],
    9  => ['Current promotions',
           'Underneath, you may see Current promotions. These are time-limited offers from your supplier. The Until column shows the end date, or the word ongoing. Where a promotion and a standing discount both apply, you get the larger one. They are never added together. If nothing is running, this section does not appear at all.'],
    10 => ['Standard terms',
           'If the page says you are on standard terms, nothing is wrong. It means no special discount is set for your account, so you buy at the standard trade price. If you think a figure is wrong, ring your supplier, because only they can change it. It is worth a look after any price change, to check the new deal has landed.'],
];
$len = static fn (int $n): string => (string) round(mb_strlen($vo[$n][1]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$rows = [
    ['50%', 'Bev Roller Blinds',   'All systems &middot; All bands'],
    ['15%', 'Bev Roller Blinds',   'Option: Fascia Options &rarr; LL 70mm Cassette'],
    ['35%', 'Bev Vertical Blinds', 'Vogue &middot; Band A'],
];
/** The standing-discounts table. $cell($i, $col) → [class, style] for a cell's inner span. */
$tbl = static function (?callable $rowAnim = null, ?callable $cell = null) use ($rows): string {
    $h = '<div class="tt"><div class="tth"><span class="r">Discount</span><span>Product</span><span>Applies to</span></div>';
    foreach ($rows as $i => [$p, $prod, $ap]) {
        [$rc, $rs] = $rowAnim ? $rowAnim($i) : ['', ''];
        $c = static fn (int $col) => $cell ? $cell($i, $col) : ['', ''];
        [$c0, $s0] = $c(0); [$c1, $s1] = $c(1); [$c2, $s2] = $c(2);
        $h .= '<div class="ttr ' . $rc . '" style="' . $rs . '">'
            . '<span class="r"><span class="pct ' . $c0 . '" style="' . $s0 . '">' . $p . '</span></span>'
            . '<span><span class="' . $c1 . '" style="' . $s1 . '">' . $prod . '</span></span>'
            . '<span><span class="ap ' . $c2 . '" style="' . $s2 . '">' . $ap . '</span></span></div>';
    }
    return $h . '</div>';
};

$head = '<div class="ph1">Trade terms</div><div class="ph2">The buying discounts your account gets from Beverley Blinds.</div>';

$script = [];
foreach ($vo as $n => [$cap, $line]) $script[] = [(string) $n, $cap, $line, $n];

return [
        'aud'     => 'admin',
        'section' => 'Setup',
        'title'   => 'Your trade terms',
        // The page is a Setup-menu item in its own right (sidebar.php: Setup →
        // Products / Users / Settings / Trade terms / Billing), NOT a Settings
        // tab — and "Trade" is a different, super-admin-only nav section, so
        // filing it under that heading sent people to the wrong place.
        'eyebrow' => 'Setup · Trade terms',
        'v'       => 2,
        'blurb'   => 'The deal you buy on: the discounts your supplier gives you, where they come off, and why they are not the discount you give your own customer.',
        'lede'    => 'This page is a <b>statement of the deal you buy on</b>. It lists the discounts your supplier takes off
                      <b>what you pay them</b> &mdash; per product, and sometimes only on one system, one band or one option.
                      It is <b>read-only</b>: your supplier sets these on their side, so there is nothing to fill in and no Save
                      button. Every line on it is already working on every price you see. To get there: <b>Setup</b> &rarr;
                      <b>Trade terms</b> in the left-hand menu.',
        'open'    => '/admin/trade-terms.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; max-width:34rem; line-height:1.45; }
          .gd .ph1{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .ph2{ font-size:.72rem; color:var(--soft); margin:.1rem 0 .8rem; }
          .gd .sh{ font-size:.86rem; font-weight:800; color:var(--ink); margin:0 0 .3rem; }
          .gd .lead{ font-size:.66rem; color:var(--faint); line-height:1.45; margin:0 0 .5rem; max-width:34rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .chip.good{ border-color:var(--good); }
          .gd .row{ display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; } .gd .mt{ margin-top:.7rem; }
          .gd .arrow{ color:var(--faint); font-weight:800; }

          /* the table */
          .gd .tt{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:34rem; font-size:.7rem; background:var(--surface); }
          .gd .tth, .gd .ttr{ display:grid; grid-template-columns:4.2rem 1fr 1.5fr; gap:.5rem; padding:.32rem .6rem; align-items:center; }
          .gd .tth{ background:var(--panel); font-size:.6rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.04em; }
          .gd .ttr{ border-top:1px solid var(--line); color:var(--ink); }
          .gd .tt .r{ text-align:right; }
          .gd .pct{ font-weight:800; color:#065f46; font-variant-numeric:tabular-nums; border-radius:4px; }
          :root[data-theme="dark"] .gd .pct{ color:#34d399; }
          .gd .ap{ border-radius:4px; }
          .gd .ttr.dim{ opacity:.35; }
          .gd .note{ font-size:.66rem; color:var(--faint); margin:.6rem 0 0; max-width:34rem; line-height:1.45; }

          /* 2 — two discounts */
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; max-width:36rem; }
          .gd .kind{ border:1px solid var(--line); border-radius:10px; padding:.55rem .7rem; background:var(--surface); font-size:.7rem; color:var(--soft); }
          .gd .kind h4{ margin:0 0 .4rem; font-size:.8rem; color:var(--ink); }
          .gd .flow{ display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; font-weight:700; color:var(--ink); }
          .gd .flow span{ border-radius:6px; padding:.12rem .4rem; background:var(--panel); }
          .gd .flow .m{ background:var(--err-wash); color:var(--err); }
          .gd .flow .p{ background:var(--good-wash); color:var(--good); }

          /* 6 — the sum */
          .gd .chain{ display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; margin:.8rem 0; }
          .gd .chain .v{ display:flex; flex-direction:column; align-items:center; border:1px solid var(--line); border-radius:10px; padding:.4rem .65rem; background:var(--surface); min-width:4.8rem; }
          .gd .chain .v b{ font-size:1rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .chain .v small{ font-size:.58rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .chain .op{ font-size:.7rem; font-weight:800; border-radius:999px; padding:.15rem .5rem; }
          .gd .chain .op.m{ background:var(--err-wash); color:var(--err); } .gd .chain .op.p{ background:var(--good-wash); color:var(--good); }

          /* 7 — InstaPrice card */
          .gd .ipc{ border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.5rem .7rem; max-width:20rem; font-size:.72rem; }
          .gd .ipc .ttl{ font-weight:800; color:var(--ink); margin-bottom:.3rem; }
          .gd .ipr{ display:flex; justify-content:space-between; padding:.2rem 0; border-top:1px dashed var(--line); color:var(--soft); }
          .gd .ipr b{ color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .ipr.tr, .gd .ipr.tr b{ color:#065f46; font-weight:700; }
          :root[data-theme="dark"] .gd .ipr.tr, :root[data-theme="dark"] .gd .ipr.tr b{ color:#34d399; }
          .gd .bx{ display:inline-block; min-width:2.6rem; text-align:right; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:0 .3rem; background:var(--surface); }
          .gd .stk{ display:inline-grid; } .gd .stk > *{ grid-area:1/1; }
          .gd .qbline{ font-size:.66rem; color:var(--soft); border:1px solid var(--line); border-radius:8px; padding:.35rem .55rem; background:var(--panel); max-width:30rem; }

          /* 8 — pricing per system */
          .gd .pps{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:28rem; font-size:.7rem; background:var(--surface); }
          .gd .pps .h, .gd .pps .b{ display:grid; grid-template-columns:1.3fr 1fr 1fr; gap:.4rem; padding:.35rem .6rem; align-items:center; }
          .gd .pps .h{ background:var(--panel); font-size:.6rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.04em; }
          .gd .pps .in{ display:inline-flex; width:4rem; height:24px; align-items:center; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:0 .35rem; background:var(--surface); }
          .gd .green{ color:#065f46; font-weight:800; } .gd .green small{ display:block; color:var(--faint); font-weight:400; font-size:.58rem; }
          :root[data-theme="dark"] .gd .green{ color:#34d399; }

          /* 10 — empty state */
          .gd .empty{ font-size:.74rem; color:var(--faint); border:1px dashed var(--line); border-radius:9px; padding:.6rem .75rem; max-width:34rem; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .tth, .gd .ttr{ grid-template-columns:3rem 1fr 1.3fr; } .gd .nophone{ display:none; }
            .gd .sc{ min-height:430px; }
          }',
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
                <a>Products</a><a>Users</a><a>Settings</a><a class="on">Trade terms</a><a>Billing</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head . '
                  <div class="sh">Your standing discounts</div>
                  ' . $tbl() . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters.</p>
                </div>

                <!-- 1 — where it lives -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="a-fade" style="--d:2.5s">' . $head . '</div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:8s">&#128065; Read-only</span>
                    <span class="chip a-pop" style="--d:9.5s">Nothing to fill in &middot; no Save button</span>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:13.5s">Your supplier sets these on their side</span>
                    <span class="chip a-pop" style="--d:17s">The deal you are on</span>
                  </div>
                  <div class="a-move nophone" style="--fx:60%;--fy:85%;--tx:-6.5rem;--ty:20.3rem;--d:.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 2 — two discounts -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Two different discounts</div>
                  <div class="two" style="margin-top:.5rem">
                    <div class="kind a-rise" style="--d:4s"><h4>Trade discount &mdash; this page</h4>
                      <div class="flow"><span>Supplier&rsquo;s trade price</span><span class="m a-pop" style="--d:7s">&minus; trade discount</span><span class="a-pop" style="--d:9s">= your cost &darr;</span></div>
                      <p style="margin:.4rem 0 0">Set by your supplier.</p></div>
                    <div class="kind a-rise" style="--d:11s"><h4>Your discount &mdash; to your customer</h4>
                      <div class="flow"><span>Your selling price</span><span class="m a-pop" style="--d:14s">&minus; your discount</span><span class="a-pop" style="--d:15.5s">= customer pays</span></div>
                      <p style="margin:.4rem 0 0">Set by you, under Products or on the quote.</p></div>
                  </div>
                  <div class="row mt"><span class="chip good a-pop" style="--d:20s">Bigger trade discount &rarr; wider margin, same customer price</span></div>
                </div>

                <!-- 3 — the table -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sh a-fade" style="--d:.5s">Your standing discounts</div>
                  <p class="lead a-fade" style="--d:1.5s">These come off the <b>trade price</b> you pay Beverley Blinds &mdash; applied automatically on every quote you build, so
                     your costs are already reduced. (They&rsquo;re not a discount to your own customers; that&rsquo;s set separately under Products.)</p>
                  ' . $tbl(
                        static fn ($i) => ['a-fly', '--d:' . (4 + $i * .5) . 's'],
                        static fn ($i, $col) => $i === 0 ? ['a-ring', '--d:' . [7, 9.5, 13][$col] . 's'] : ['', '']
                    ) . '
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:7s">Discount &mdash; how much off</span>
                    <span class="chip a-pop" style="--d:9.5s">Product &mdash; which one</span>
                    <span class="chip a-pop" style="--d:13s">Applies to &mdash; exactly where it counts</span>
                  </div>
                </div>

                <!-- 4 — all systems, all bands -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  ' . $tbl(
                        static fn ($i) => [$i === 0 ? '' : 'dim', ''],
                        static fn ($i, $col) => $i === 0 && $col === 2 ? ['a-ring', '--d:1s'] : ['', '']
                    ) . '
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:4s">Every system</span>
                    <span class="chip a-pop" style="--d:6s">Every price band</span>
                  </div>
                  <div class="row mt"><span class="chip good a-pop" style="--d:9s">50% off every roller blind you price &mdash; whatever the fabric</span></div>
                </div>

                <!-- 5 — narrower rows -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  ' . $tbl(
                        static fn ($i) => [$i === 0 ? 'dim' : '', ''],
                        static fn ($i, $col) => $col === 2 && $i > 0 ? ['a-ring', '--d:' . ($i === 2 ? 2.5 : 10) . 's'] : ['', '']
                    ) . '
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:5s">Vogue &middot; Band A &rarr; only that system, only that band</span>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:12s">Option: &hellip; &rarr; a discount on a part, not the blind</span>
                    <span class="chip a-pop" style="--d:17.5s">Off what that option costs you</span>
                  </div>
                </div>

                <!-- 6 — already applied -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">Already applied &mdash; nothing to switch on</div>
                  <div class="chain">
                    <div class="v a-pop" style="--d:5s"><small>Trade price</small><b>&pound;40.00</b></div>
                    <span class="op m a-pop" style="--d:7.5s">&minus; 50% trade discount</span>
                    <div class="v a-pop" style="--d:9s"><small>Your cost</small><b>&pound;20.00</b></div>
                    <span class="op p a-pop" style="--d:12s">+ your markup</span>
                    <span class="op m a-pop" style="--d:14s">&minus; your customer discount</span>
                    <div class="v a-pop" style="--d:15.5s"><small>Sell price</small><b>&hellip;</b></div>
                  </div>
                  <p class="note a-fade" style="--d:17s"><b>Where you&rsquo;ll see it:</b> when you price a discounted product, the <em>base / trade price</em> is already reduced by
                    the percentage above. Your own markup and any discount you give your customer are then applied on top.</p>
                </div>

                <!-- 7 — InstaPrice -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">Watch it in InstaPrice</div>
                  <div class="ipc a-rise" style="--d:2s">
                    <div class="ttl">Bev Roller Blinds &middot; 1200 &times; 1600</div>
                    <div class="ipr tr a-fly" style="--d:7s"><span>Trade discount</span><b>50.00% (&minus;&pound;10.60)</b></div>
                    <div class="ipr"><span>Price</span><b class="stk"><span class="a-out" style="--d:13s">&pound;21.20</span><span class="a-fade" style="--d:13s">&pound;10.60</span></b></div>
                    <div class="ipr"><span>Discount %</span><span class="bx">0</span></div>
                    <div class="ipr"><span>Discounted price</span><b>&pound;10.60</b></div>
                    <div class="ipr"><span>Mark up %</span><span class="bx">100</span></div>
                    <div class="ipr"><span>Sell price</span><b>&pound;21.20</b></div>
                  </div>
                  <div class="qbline a-rise mt" style="--d:17s">Quote builder &middot; cost breakdown: &hellip; <b>trade discount 50%</b> &hellip;</div>
                  <div class="row mt"><span class="chip a-pop" style="--d:21s">&#128100; Only for users with <b>View costs</b> ticked</span></div>
                </div>

                <!-- 8 — Discount % read-only -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">Products &rarr; Edit &rarr; Pricing per system</div>
                  <div class="pps a-rise" style="--d:2s">
                    <div class="h"><span>System</span><span>Markup %</span><span>Discount %</span></div>
                    <div class="b"><span>Standard</span><span class="in">100</span><span class="green a-ring" style="--d:8s;border-radius:5px">50.00%<small>from your supplier</small></span></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:10s">Not a box &mdash; a green figure</span>
                    <span class="chip good a-pop" style="--d:16s">One buying discount, theirs &mdash; never doubled up</span>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:18.5s">Markup % is still yours to change</span></div>
                </div>

                <!-- 9 — promotions -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sh a-fade" style="--d:.5s">Current promotions</div>
                  <p class="lead a-fade" style="--d:1.5s">Time-limited offers running now. Where a promotion and a standing discount both apply, you get the larger.</p>
                  <div class="tt a-rise" style="--d:3s">
                    <div class="tth" style="grid-template-columns:4.2rem 1fr 1.2fr 5rem"><span class="r">Discount</span><span>Product</span><span>Applies to</span><span>Until</span></div>
                    <div class="ttr" style="grid-template-columns:4.2rem 1fr 1.2fr 5rem"><span class="r"><span class="pct">60%</span></span><span>Bev Roller Blinds<br><small style="color:var(--faint)">Autumn offer</small></span><span>All systems &middot; All bands</span><span class="a-ring" style="--d:8s;border-radius:4px;color:var(--faint)">31 Oct 2026</span></div>
                    <div class="ttr" style="grid-template-columns:4.2rem 1fr 1.2fr 5rem"><span class="r"><span class="pct">10%</span></span><span>Bev Vertical Blinds</span><span>All systems &middot; All bands</span><span class="a-ring" style="--d:9.5s;border-radius:4px;color:var(--faint)">ongoing</span></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:12s">Standing 50% + promotion 60%</span><span class="arrow a-fade" style="--d:13s">&rarr;</span>
                    <span class="chip good a-pop" style="--d:13.5s">you get 60% &mdash; the larger</span>
                    <span class="chip warn a-pop" style="--d:17s">Never added together</span>
                  </div>
                </div>

                <!-- 10 — standard terms -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  ' . $head . '
                  <div class="sh">Your standing discounts</div>
                  <div class="empty a-rise" style="--d:1.5s">You&rsquo;re on standard terms &mdash; no special discounts set for your account.</div>
                  <div class="row mt">
                    <span class="chip good a-pop" style="--d:4.5s">Nothing is wrong</span>
                    <span class="chip a-pop" style="--d:7s">You buy at the standard trade price</span>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:12s">&#128222; A figure looks wrong? Ring your supplier</span>
                    <span class="chip a-pop" style="--d:18s">Check it after any price change</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Trade terms</b> is under <b>Setup</b> in the left-hand menu. The heading reads <em>&ldquo;The buying discounts
             your account gets from &hellip;&rdquo;</em> with your supplier&rsquo;s name (if it just says <b>your supplier</b>, the name couldn&rsquo;t
             be looked up &mdash; nothing is broken). The page is <b>read-only</b>: no boxes, no ticks, no <b>Save</b> button. Your supplier sets these
             on their side; if a figure looks wrong, talk to them.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Two different discounts.</b> The <b>trade discount</b> on this page comes off
             <b>what you pay your supplier</b> &mdash; it lowers <em>your cost</em>. <b>Your own discount</b> comes off <b>what your customer pays
             you</b> &mdash; you set that under <b>Products</b> or on the quote. A bigger trade discount doesn&rsquo;t lower your customer&rsquo;s price
             by itself: it widens your margin, until you choose to pass some on.</div></div>

          <p><b>Your standing discounts</b> &mdash; <em>&ldquo;These come off the trade price you pay &hellip; &mdash; applied automatically on every
             quote you build, so your costs are already reduced.&rdquo;</em> Three columns:</p>
          <ul class="steps">
            <li><b>Discount</b> &mdash; the percentage, e.g. <code>50%</code> (trailing zeros trimmed; the product screen shows the same deal as
                <code>50.00%</code>).</li>
            <li><b>Product</b> &mdash; which of your products it is on. Only products that come from your supplier can carry one.</li>
            <li><b>Applies to</b> &mdash; the scope. <code>All systems &middot; All bands</code> = every version of that product.
                <code>Vogue &middot; Band A</code> = that one system, in that one band, only. <code>Option: Fascia Options &rarr; LL 70mm
                Cassette</code> = a discount on a <b>part</b>, not the blind: it comes off what that option costs you (without the choice, e.g.
                <code>Option: Fascia Options</code>, it covers every choice in the group).</li>
            <li><b>Nothing to do.</b> Every row is already live on every price you build &mdash; no switch, no tick.</li>
          </ul>

          <p><b>Where you&rsquo;ll see it.</b> <em>&ldquo;When you price a discounted product, the base / trade price is already reduced by the
             percentage above. Your own markup and any discount you give your customer are then applied on top.&rdquo;</em> In <b>InstaPrice</b> a
             green <b>Trade discount</b> row appears at the top of the price card (e.g. <code>50.00% (&minus;&pound;10.60)</code>) and the
             <b>Price</b> under it is already reduced; the rest of the card &mdash; <b>Discount %</b>, <b>Discounted price</b>, <b>Mark up %</b> (or
             <b>Margin %</b>) and <b>Sell price</b> &mdash; works as normal. In the <b>quote builder</b> the line&rsquo;s cost breakdown shows
             <code>trade discount 50%</code>. Both are cost figures, so only users with <b>View costs</b> ticked (<b>Users</b> &rarr; edit the user)
             see them.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Why can&rsquo;t I type in Discount %?</b> On a product your supplier discounts,
             <b>Discount %</b> under <b>Products &rarr; Edit &rarr; Pricing per system</b> (and <b>Buying discount %</b> on its price table) becomes a
             read-only green figure with <b>from your supplier</b> underneath &mdash; one buying discount, theirs, so the two can never stack. The
             <b>Markup %</b> beside it (or <b>Margin %</b>, if you work in margin) is still yours.</div></div>

          <p><b>Current promotions</b> &mdash; only shown when something is running. <em>&ldquo;Time-limited offers running now. Where a promotion
             and a standing discount both apply, you get the larger.&rdquo;</em> An extra <b>Until</b> column shows a date (e.g. <code>31 Oct
             2026</code>) or <code>ongoing</code>, soonest-ending first. The two are never added together.</p>

          <div class="oops"><b>&ldquo;You&rsquo;re on standard terms &mdash; no special discounts set for your account.&rdquo;</b> Nothing is broken
             &mdash; no special deal is set, so you buy at the standard trade price. A blue <b>&ldquo;Your trade terms aren&rsquo;t set up
             yet.&rdquo;</b> means the feature isn&rsquo;t switched on for your system yet. Either way, talk to your supplier.</div>

          <p class="prose"><b>Not your own trade accounts.</b> If you sell to other businesses, the discount you give <em>them</em> is held per
             account under <b>Trade &rarr; Trade accounts</b> (a super-admin section). This page is only about what <b>you</b> buy at. Worth a look
             after any price change, to check the new deal has landed.</p>',
        'script'  => $script,
];
