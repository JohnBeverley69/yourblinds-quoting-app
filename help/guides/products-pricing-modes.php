<?php
declare(strict_types=1);

/**
 * Guide: products-pricing-modes — "Pricing: source, markup & mode" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the pricing block near the bottom of /admin/products/edit.php, in
 * the REAL form order: the pricing tick-boxes (no fabric / width only /
 * per slat / per m² + Minimum billable area), the "Pricing source" fieldset,
 * the "Pricing per system" fieldset, then Active + Save changes. Also the
 * per-choice "Face value" tick (_partials/choices_grid.php), the pricing
 * engine's precedence (_partials/pricing_engine.php) and the matching
 * Buying discount % / Our markup % pair on price-table.php. Every label,
 * hint and message is taken from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

// ── The voice-over (built first so each scene's data-len comes from it) ──
$script = [
    ['1',  'Where pricing lives',              "Every price this product will ever quote is decided by three settings. They sit near the bottom of the product's Edit page, one under the other. First comes a stack of tick-boxes. They say how the blind is measured and priced. Then Pricing source, which says whose prices are in your tables. Then Pricing per system, where your markup and your discount live.", 1],
    ['2',  'Most blinds need no ticks',        "Start with the tick-boxes. Most blinds need none of them. Leave every box empty, and the blind prices the normal way. It finds its width along the top of a grid, and its drop down the side, and takes the price where the two meet. Only tick a box if this product is genuinely different, like a headrail on its own, or a shutter.", 2],
    ['3',  'No fabric to choose',              "The first box says, No fabric to choose. This one is about what the customer picks. Tick it for a headrail, a track or spares, where there is nothing to choose. The quote builder and InstaPrice then hide the band and fabric pickers. You keep one price table per system, with no bands. The blind is still sized by width and drop.", 3],
    ['4',  'Sized by width only',              "The next box is Sized by width only. This one is about how the blind is measured. Tick it when the price depends on width alone, like a headrail cut to length. The Drop field disappears from the quote, and each price table becomes a simple list of widths and prices. After you save, an Import width prices link appears in the Systems section.", 4],
    ['5',  'Priced per slat',                  "Next is Priced per slat, for something like vertical fabric only. Each price table becomes a list of drops, with the price of one slat at each drop. When you quote it, there is no width. You enter the drop, and the Quantity box becomes Number of slats. So the price is the slat rate, times the number of slats. After saving, an Import rates link appears.", 5],
    ['6',  'Per square metre',                 "The last pricing box is Priced per square metre, for shutters. Each system and band has one rate, in pounds per square metre. The quote multiplies it by the width times the height, so both are needed. Underneath sits Minimum billable area. Put nought point five in it, and even a tiny shutter is charged as half a square metre. Leave it blank for no minimum.", 6],
    ['7',  'Only tick one of these',           "Here is a trap. Width only, per slat and per square metre do not mix. Tick two by mistake, and nothing warns you. The pricing simply uses one of them, in this order: width only first, then per slat, then per square metre. The box you meant is quietly ignored. If prices look odd, check that only one is ticked. No fabric to choose is different. It goes with any of them.", 7],
    ['8',  'Pricing source: our price list',   "Below the boxes is Pricing source. It says what the numbers in your price tables really are. Choose Our price list when you make the blind yourself. The grid is already your selling price, so nothing is added and nothing is taken off. Trade accounts get those prices exactly as they are.", 8],
    ['9',  'Or a supplier price list',         "Choose Supplier price list when you buy the blind in. Now the grid holds your supplier's standard trade list. Your price is that list, less your buying discount, plus your markup. Say the list price is one hundred pounds. Twenty five percent off makes seventy five. A hundred percent markup makes one hundred and fifty pounds. That is the price you quote.", 9],
    ['10', 'Options follow the same rule',     "Options have a say too. Normally an option is charged at face value, exactly the price you typed. But on a supplier price list, some surcharges are the supplier's own, like a taped finish. Untick Face value on that choice, and it goes through the same sum as the blind. Less your buying discount, plus your markup.", 10],
    ['11', 'Markup, system by system',         "Now Pricing per system. Each system gets its own row, because standard and motorised are rarely priced alike. Leave the Markup box empty, and it says using default, fifty percent. That figure comes from Default margins, in Settings. Type a number, say a hundred for Motorised, and a purple tag says override. Clear it to go back to the default.", 11],
    ['12', 'Discount has no default',          "The Discount box works differently, and this catches people out. There is no default behind it. Blank, or nought, means no discount at all. Sometimes the box is replaced by a green figure, with from your supplier underneath. Your supplier has given you that trade discount on their product. It is the one the pricing uses, so you cannot type over it.", 12],
    ['13', 'Markup or margin',                 "Two more things. If your Settings say you work in margin, this column says Margin instead of Markup. The customer pays the same either way. It only changes which number you type. And on a supplier list, the same two figures also sit on each price table, as Buying discount and Our markup. Change them in one place, and the other changes too.", 13],
    ['14', 'Save changes',                     "Finally, click Save changes. If something is wrong, like a minus number, the page comes back with a red message: Markup percent must be a non-negative number. Fix it, and save again. You will see Product updated, and land back on your products list. The new prices apply to quotes from now on. Quotes already saved keep their prices.", 14],
];
$len = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The four pricing tick-boxes, as on the real form. $on = which ones show
// ticked (finished state); $anim = [index => '--d:..s'] to tick one in.
$labels = [
    'nf' => 'No fabric to choose (headrail only, track, spares).',
    'wo' => 'Sized by width only &mdash; no drop (e.g. a headrail cut to length).',
    'ps' => 'Priced per slat (by drop) &mdash; e.g. vertical fabric only.',
    'sq' => 'Priced per square metre &mdash; e.g. shutters.',
];
$boxes = static function (array $tickAt = [], array $hints = [], string $dim = '') use ($labels): string {
    $h = '<div class="ckstack">';
    foreach ($labels as $k => $lbl) {
        $faded = ($dim !== '' && !isset($tickAt[$k]) && !isset($hints[$k])) ? ' dim' : '';
        $tick  = isset($tickAt[$k])
            ? '<span class="tk"><span class="tkon a-pop" style="--d:' . $tickAt[$k] . 's">&check;</span></span>'
            : '<span class="tk"></span>';
        $h .= '<div class="ck' . $faded . '">' . $tick . '<div><b>' . $lbl . '</b>'
            . (isset($hints[$k]) ? '<small>' . $hints[$k] . '</small>' : '') . '</div></div>';
    }
    return $h . '</div>';
};

// A tiny width × drop grid for the "normal" way.
$miniGrid = static function (bool $animate): string {
    $W = [600, 1200, 1800]; $D = [1000, 1500, 2000];
    $P = [[24.10, 31.60, 39.20], [27.90, 36.40, 45.10], [31.70, 41.30, 51.00]];
    $h = '<div class="mg"><span class="mc hd">&nbsp;</span>';
    foreach ($W as $i => $w) $h .= '<span class="mc hd' . ($i === 1 && $animate ? ' a-sel' : '') . '" style="--d:11s">' . $w . '</span>';
    foreach ($D as $r => $d) {
        $h .= '<span class="mc hd' . ($r === 1 && $animate ? ' a-sel' : '') . '" style="--d:13s">' . $d . '</span>';
        foreach ($W as $i => $w) {
            $hit = $animate && $i === 1 && $r === 1;
            $h .= '<span class="mc' . ($hit ? ' hit a-ring' : '') . '" style="--d:15s">' . number_format($P[$r][$i], 2) . '</span>';
        }
    }
    return $h . '</div>';
};

$L1 = $len(1); $L2 = $len(2); $L3 = $len(3); $L4 = $len(4); $L5 = $len(5); $L6 = $len(6); $L7 = $len(7);
$L8 = $len(8); $L9 = $len(9); $L10 = $len(10); $L11 = $len(11); $L12 = $len(12); $L13 = $len(13); $L14 = $len(14);

$bxPoster = $boxes();
$bx2 = $boxes([], [], '');
$bx3 = $boxes(['nf' => 3.5], ['nf' => 'This is about <em>what the customer picks</em>&hellip; the quote builder and InstaPrice hide the band/fabric pickers and you use one price table per system (no bands).'], 'dim');
$bx4 = $boxes(['wo' => 3.5], ['wo' => 'This is about <em>how it&rsquo;s sized</em>&hellip; the Drop field is hidden and each price table is a single width &rarr; price list.'], 'dim');
$bx5 = $boxes(['ps' => 2.5], ['ps' => 'Each price table is a <em>drop &rarr; price-per-slat</em> list&hellip; enter the drop + number of slats (the quantity) &mdash; no width.'], 'dim');
$bx6 = $boxes(['sq' => 2.5], [], 'dim');
$gridPlain = $miniGrid(false);
$gridLive  = $miniGrid(true);

$demo = <<<HTML
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / edit</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a class="on">Products</a><a>Users</a><a>Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="sct">Pricing: source, markup &amp; mode</div>
                  <p class="scs">Three settings near the bottom of a product&rsquo;s Edit page decide every price it quotes.</p>
                  <div class="blk"><span class="bn">1</span><div><b>The tick-boxes</b><small>How the blind is measured and priced</small></div></div>
                  <div class="blk"><span class="bn">2</span><div><b>Pricing source</b><small>Whose prices are in your tables</small></div></div>
                  <div class="blk"><span class="bn">3</span><div><b>Pricing per system</b><small>Your markup and your discount</small></div></div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fourteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — where it lives -->
                <div class="sc" data-scene="1" data-len="{$L1}">
                  <div class="sct a-fade" style="--d:.2s">Where pricing lives</div>
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:5.5s">Products</span><span class="arrow a-fade" style="--d:6.2s">&rarr;</span>
                    <span class="chip a-pop" style="--d:6.8s">Roller Blind</span><span class="arrow a-fade" style="--d:7.5s">&rarr;</span>
                    <span class="chip a-pop" style="--d:8.2s">Edit</span><span class="arrow a-fade" style="--d:9s">&darr; near the bottom</span>
                  </div>
                  <div class="blk a-rise" style="--d:11s"><span class="bn">1</span><div><b>Tick-boxes</b><small>How it is measured and priced</small></div></div>
                  <div class="blk a-rise" style="--d:16.5s"><span class="bn">2</span><div><b class="lg">Pricing source</b><small>Whose prices are in your tables</small></div></div>
                  <div class="blk a-rise" style="--d:21s"><span class="bn">3</span><div><b class="lg">Pricing per system</b><small>Your markup and discount</small></div></div>
                  <div class="actrow a-fade" style="--d:25s"><span class="tk"><span class="tkon">&check;</span></span> Active <span class="faint">uncheck to hide from quote builder</span> <span class="btnp">Save changes</span></div>
                  <span class="tag3 a-pop" style="--d:1.5s">3 settings &rarr; every price</span>
                </div>

                <!-- 2 — no ticks = width × drop -->
                <div class="sc" data-scene="2" data-len="{$L2}">
                  <div class="sct a-fade" style="--d:.2s">Most blinds need no ticks</div>
                  <div class="a-rise" style="--d:1.5s"><div class="ringbox a-ring" style="--d:5s">{$bx2}</div></div>
                  <div class="row2c">
                    <div class="a-rise" style="--d:8s">{$gridLive}</div>
                    <div class="colx">
                      <span class="chip a-pop" style="--d:16s;border-color:var(--accent)">1200 wide &times; 1500 drop = <b>&pound;36.40</b></span>
                      <span class="chip a-pop" style="--d:21s;margin-top:.5rem">Tick only if this product is <b>different</b></span>
                    </div>
                  </div>
                </div>

                <!-- 3 — no fabric to choose -->
                <div class="sc" data-scene="3" data-len="{$L3}">
                  <div class="sct a-fade" style="--d:.2s">No fabric to choose</div>
                  {$bx3}
                  <div class="qb a-rise" style="--d:9s">
                    <div class="qbh">Quote builder</div>
                    <div class="qrow">
                      <div class="qf"><label>System</label><span class="qbox">Standard</span></div>
                      <div class="qf gone" style="--d:12.5s"><label>Band</label><span class="qbox">A</span></div>
                      <div class="qf gone" style="--d:12.8s"><label>Fabric</label><span class="qbox">Polaris</span></div>
                      <div class="qf"><label>Width</label><span class="qbox">1200</span></div>
                      <div class="qf"><label>Drop</label><span class="qbox">1500</span></div>
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:17s">One price table per system &mdash; no bands</span>
                    <span class="chip a-pop" style="--d:21.5s">Still width &times; drop</span>
                  </div>
                </div>

                <!-- 4 — width only -->
                <div class="sc" data-scene="4" data-len="{$L4}">
                  <div class="sct a-fade" style="--d:.2s">Sized by width only</div>
                  {$bx4}
                  <div class="row2c">
                    <div class="qb a-rise" style="--d:12s">
                      <div class="qbh">Quote builder</div>
                      <div class="qrow">
                        <div class="qf"><label>Width</label><span class="qbox">2400</span></div>
                        <div class="qf gone" style="--d:14s"><label>Drop</label><span class="qbox">1500</span></div>
                      </div>
                    </div>
                    <div class="lst a-rise" style="--d:17s"><span class="h">Width</span><span class="h">Price (&pound;)</span><span>1800</span><span>14.20</span><span>2400</span><span>17.60</span><span>3000</span><span>21.10</span></div>
                  </div>
                  <div class="sysbar a-fade" style="--d:22s">Systems <span class="faint">(2)</span><span class="a-pop" style="--d:24s;margin-left:auto"><span class="lnk a-ring" style="--d:24.6s">Import width prices &raquo;</span></span><span class="lnk">Full manage &raquo;</span></div>
                </div>

                <!-- 5 — per slat -->
                <div class="sc" data-scene="5" data-len="{$L5}">
                  <div class="sct a-fade" style="--d:.2s">Priced per slat</div>
                  {$bx5}
                  <div class="row2c">
                    <div class="lst a-rise" style="--d:6s"><span class="h">Drop</span><span class="h">Price per slat (&pound;)</span><span>1500</span><span>1.85</span><span>2000</span><span>2.40</span><span>2500</span><span>3.10</span></div>
                    <div class="qb a-rise" style="--d:10s">
                      <div class="qbh">Quote builder</div>
                      <div class="qrow">
                        <div class="qf gone" style="--d:12s"><label>Width</label><span class="qbox">&nbsp;</span></div>
                        <div class="qf"><label>Drop</label><span class="qbox">2000</span></div>
                        <div class="qf"><label class="swapl"><span class="a-out" style="--d:15.5s">Quantity</span><span class="a-fade" style="--d:15.5s">Number of slats</span></label><span class="qbox a-ring" style="--d:15.5s">12</span></div>
                      </div>
                      <div class="sumline a-pop" style="--d:20s">&pound;2.40 &times; 12 slats = <b>&pound;28.80</b></div>
                    </div>
                  </div>
                  <div class="sysbar a-fade" style="--d:24s">Systems <span class="faint">(1)</span><span class="a-pop" style="--d:24.6s;margin-left:auto"><span class="lnk a-ring" style="--d:25.2s">Import rates &raquo;</span></span><span class="lnk">Full manage &raquo;</span></div>
                </div>

                <!-- 6 — per m² + minimum area -->
                <div class="sc" data-scene="6" data-len="{$L6}">
                  <div class="sct a-fade" style="--d:.2s">Per square metre</div>
                  {$bx6}
                  <div class="row2c">
                    <div>
                      <div class="chain sm">
                        <div class="v a-pop" style="--d:5s"><small>Rate</small><b>&pound;42.00</b><small>per m&sup2;</small></div>
                        <span class="op a-pop" style="--d:8.5s">&times;</span>
                        <div class="v a-pop" style="--d:9.5s"><small>Area</small><b>1.44 m&sup2;</b><small>1200 &times; 1200</small></div>
                        <span class="op a-pop" style="--d:12s">=</span>
                        <div class="v out a-pop" style="--d:12.5s"><small>Price</small><b>&pound;60.48</b></div>
                      </div>
                    </div>
                    <div class="fldm a-rise" style="--d:15s">
                      <label>Minimum billable area (m&sup2;)</label>
                      <span class="inp a-ring" style="--d:17s"><span class="ph a-out" style="--d:18.5s">e.g. 0.5 (blank = none)</span><span class="tv"><span class="a-type" style="--d:18.5s;--ts:3;--tt:.5s">0.5</span></span></span>
                      <small>The area is billed at no less than this. Blank or 0 = no minimum.</small>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:21s">600 &times; 400 = 0.24 m&sup2; &rarr; billed as <b>0.5 m&sup2;</b></span>
                </div>

                <!-- 7 — only one -->
                <div class="sc" data-scene="7" data-len="{$L7}">
                  <div class="sct a-fade" style="--d:.2s">Only tick one of these</div>
                  <div class="ckstack">
                    <div class="ck"><span class="tk"></span><div><b>No fabric to choose (headrail only, track, spares).</b></div><span class="okchip a-pop" style="--d:25s">mixes with any</span></div>
                    <div class="ck"><span class="tk"><span class="tkon a-pop" style="--d:4s">&check;</span></span><div><b>Sized by width only &mdash; no drop</b></div><span class="rank a-pop" style="--d:13s">1st</span></div>
                    <div class="ck"><span class="tk"><span class="tkon a-pop" style="--d:4.6s">&check;</span></span><div><b>Priced per slat (by drop)</b></div><span class="rank a-pop" style="--d:14.5s">2nd</span><span class="ign a-stamp" style="--d:17.5s">ignored</span></div>
                    <div class="ck"><span class="tk"></span><div><b>Priced per square metre</b></div><span class="rank a-pop" style="--d:16s">3rd</span></div>
                  </div>
                  <div class="errline a-pop" style="--d:6.5s">&#9888; Two ticked &mdash; and no warning</div>
                </div>

                <!-- 8 — our price list -->
                <div class="sc" data-scene="8" data-len="{$L8}">
                  <div class="sct a-fade" style="--d:.2s">Pricing source</div>
                  <div class="fs a-rise" style="--d:1s">
                    <div class="lg">Pricing source</div>
                    <p class="fsh">What the numbers in this product&rsquo;s price tables actually are. This decides whether a trade account receives them as they stand, or with your buying discount and margin applied.</p>
                    <div class="rd"><span class="dot a-sel" style="--d:8s"></span><div><b>Our price list</b> &mdash; we make it. <span class="faint">The grid is our selling price (its cost sits in the cost grid)&hellip; Trade accounts get these prices <b>exactly as they are</b>.</span></div></div>
                    <div class="rd"><span class="dot"></span><div><b>Supplier price list</b> &mdash; we buy it in.</div></div>
                  </div>
                  <div class="flow">
                    <span class="cellc a-pop" style="--d:11s">Grid <b>&pound;60.00</b></span>
                    <span class="arrow a-fade" style="--d:13s">&rarr; nothing added or taken off &rarr;</span>
                    <span class="cellc good a-pop" style="--d:17s">Trade account <b>&pound;60.00</b></span>
                  </div>
                </div>

                <!-- 9 — supplier price list -->
                <div class="sc" data-scene="9" data-len="{$L9}">
                  <div class="sct a-fade" style="--d:.2s">Or a supplier price list</div>
                  <div class="fs">
                    <div class="lg">Pricing source</div>
                    <div class="rd"><span class="dot dotoff"><i class="a-out" style="--d:1.5s"></i></span><div><b>Our price list</b> &mdash; we make it.</div></div>
                    <div class="rd"><span class="dot a-sel" style="--d:1.5s"></span><div><b>Supplier price list</b> &mdash; we buy it in. <span class="faint">The grid is the supplier&rsquo;s standard trade list. Trade accounts get it <b>less the discount plus the margin</b> set below.</span></div></div>
                  </div>
                  <div class="chain">
                    <div class="v a-pop" style="--d:14s"><small>List</small><b>&pound;100.00</b></div>
                    <span class="op m a-pop" style="--d:17s">&minus; 25%</span>
                    <div class="v a-pop" style="--d:18.5s"><small>You pay</small><b>&pound;75.00</b></div>
                    <span class="op p a-pop" style="--d:21s">+ 100%</span>
                    <div class="v out a-pop" style="--d:23s"><small>You quote</small><b>&pound;150.00</b></div>
                  </div>
                </div>

                <!-- 10 — options: face value -->
                <div class="sc" data-scene="10" data-len="{$L10}">
                  <div class="sct a-fade" style="--d:.2s">Options follow the same rule</div>
                  <div class="opt a-rise" style="--d:1s">
                    <div class="oh"><span>Choice</span><span>Price</span><span>Face&nbsp;value</span></div>
                    <div class="or"><span>Taped finish</span><span>+&pound;5.00</span><span class="fvc"><span class="tk"><span class="tkon a-out" style="--d:14.5s">&check;</span></span></span></div>
                    <div class="or"><span>Chrome bottom bar</span><span>+&pound;8.00</span><span class="fvc"><span class="tk"><span class="tkon">&check;</span></span></span></div>
                  </div>
                  <div class="a-move" style="--fx:70%;--fy:92%;--tx:21rem;--ty:4.1rem;--d:12s;--md:2s">{$ptr}</div>
                  <div class="two">
                    <div class="note a-rise" style="--d:4s"><h4>Face value ticked (normal)</h4><div class="sum"><span>+&pound;5.00</span><span class="p">charged as typed</span></div></div>
                    <div class="note a-rise" style="--d:16s"><h4>Unticked &mdash; a supplier add-on</h4><div class="sum"><span>&pound;5.00</span><span class="m">&minus; 25%</span><span class="p">+ 100%</span><span class="p"><b>= &pound;7.50</b></span></div></div>
                  </div>
                  <p class="scs a-fade" style="--d:19s;margin-top:.6rem">Only matters on a <b>Supplier price list</b> product.</p>
                </div>

                <!-- 11 — markup per system -->
                <div class="sc" data-scene="11" data-len="{$L11}">
                  <div class="sct a-fade" style="--d:.2s">Pricing per system</div>
                  <div class="lg a-fade" style="--d:.6s">Pricing per system</div>
                  <table class="ptbl a-rise" style="--d:1.2s">
                    <thead><tr><th>System</th><th>Markup %</th><th>Discount %</th></tr></thead>
                    <tbody>
                      <tr class="a-fly" style="--d:3s"><td>Standard</td><td><span class="inp ring2 a-ring" style="--d:9s"><span class="ph">50.00</span></span><div class="tg def">using default (50.00%)</div></td><td><span class="inp">0.00</span></td></tr>
                      <tr class="a-fly" style="--d:4s"><td>Motorised</td><td><span class="inp a-ring" style="--d:16s"><span class="ph a-out" style="--d:17s">50.00</span><span class="tv"><span class="a-type" style="--d:17s;--ts:3;--tt:.5s">100</span></span></span>
                        <div class="tg swapt"><span class="def a-out" style="--d:20.5s">using default (50.00%)</span><span class="ovr a-fade" style="--d:20.5s">override</span></div></td><td><span class="inp">0.00</span></td></tr>
                    </tbody>
                  </table>
                  <span class="chip a-pop" style="--d:13.5s;margin-top:.7rem">Default from <b>Settings &rarr; Default margins</b></span>
                </div>

                <!-- 12 — discount -->
                <div class="sc" data-scene="12" data-len="{$L12}">
                  <div class="sct a-fade" style="--d:.2s">Discount has no default</div>
                  <table class="ptbl">
                    <thead><tr><th>System</th><th>Markup %</th><th>Discount %</th></tr></thead>
                    <tbody>
                      <tr><td>Standard</td><td><span class="inp"><span class="ph">50.00</span></span><div class="tg def">using default (50.00%)</div></td><td><span class="inp a-ring" style="--d:8s">0.00</span><div class="tg nodisc a-pop" style="--d:9.5s">= no discount</div></td></tr>
                      <tr><td>Motorised</td><td><span class="inp">100.00</span><div class="tg ovr">override</div></td><td><span class="inp">0.00</span></td></tr>
                      <tr class="a-fly" style="--d:12s"><td>Motorised &mdash; Somfy</td><td><span class="inp"><span class="ph">50.00</span></span><div class="tg def">using default (50.00%)</div></td><td class="supc a-ring" style="--d:14s"><span class="fromsup">25.00%</span><div class="tg def">from your supplier</div></td></tr>
                    </tbody>
                  </table>
                  <span class="chip a-pop" style="--d:21s;margin-top:.7rem">Your supplier&rsquo;s discount wins &mdash; it can&rsquo;t be typed over</span>
                </div>

                <!-- 13 — markup or margin + the price-table pair -->
                <div class="sc" data-scene="13" data-len="{$L13}">
                  <div class="sct a-fade" style="--d:.2s">Markup or margin</div>
                  <div class="row2c">
                    <div class="note a-rise" style="--d:2s"><h4>Settings &rarr; Default margins</h4>
                      <div style="margin:.3rem 0 .4rem">Enter your margins as<br><span class="radio"><span class="dot dotoff"><i class="a-out" style="--d:4.5s"></i></span>Markup&nbsp;%</span><span class="radio"><span class="dot a-sel" style="--d:4.5s"></span>Margin&nbsp;%</span></div>
                      <div class="colh"><span class="a-out" style="--d:6s">Markup %</span><span class="a-fade" style="--d:6s">Margin %</span></div>
                      <p style="margin:.4rem 0 0">The customer price is identical either way.</p></div>
                    <div class="note a-rise" style="--d:14s"><h4>On a supplier list&rsquo;s price table</h4>
                      <div class="terms"><div><label>Buying discount %</label><span class="inp">25</span></div><div><label>Our markup %</label><span class="inp">100</span></div><span class="btns">Save terms</span></div></div>
                  </div>
                  <div class="same a-pop" style="--d:21.5s">&#8644; Same two numbers &mdash; change one, both change</div>
                </div>

                <!-- 14 — save -->
                <div class="sc" data-scene="14" data-len="{$L14}">
                  <div class="sct a-fade" style="--d:.2s">Save changes</div>
                  <div class="row2c">
                    <div>
                      <table class="ptbl"><thead><tr><th>System</th><th>Markup %</th></tr></thead>
                        <tbody><tr><td>Standard</td><td><span class="inp swapv"><span class="a-out" style="--d:13s">-5</span><span class="a-fade" style="--d:13s">5</span></span></td></tr></tbody></table>
                      <div style="margin-top:.6rem"><span class="a-press" style="--d:14.5s;display:inline-block"><span class="btnp a-press" style="--d:3s">Save changes</span></span></div>
                    </div>
                    <div>
                      <div class="bstack"><div class="errbanner a-mid" style="--d:6s;--d2:14.5s"><span>&#9888;</span><div><b>Markup % must be a non-negative number.</b></div></div>
                      <div class="okbanner a-pop" style="--d:15.5s"><span>&#10003;</span><div>Product updated.</div></div></div>
                      <div class="plist a-rise" style="--d:17s"><div class="ph2">Products</div><div>Roller Blind <span class="faint">&middot; active</span></div><div>Vertical Blind</div></div>
                    </div>
                  </div>
                  <span class="chip a-pop" style="--d:21.5s;margin-top:.7rem">Saved quotes keep their prices</span>
                </div>

              </div>
            </div>
          </div>
HTML;

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Pricing: source, markup & mode',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'The tick-boxes that decide how a blind is measured and priced, whose prices are in your tables, and your markup and buying discount per system.',
        'lede'    => 'Near the bottom of a product&rsquo;s <b>Edit</b> page sit three settings that decide <b>every price</b> it quotes:
                      a stack of <b>tick-boxes</b> (how the blind is measured and priced), <b>Pricing source</b> (whose prices are
                      in your price tables) and <b>Pricing per system</b> (your markup and your discount). This guide takes them
                      <b>slowly, one idea per chapter</b>, in the order you meet them on the page. To get there: <b>Products</b>
                      &rarr; click the product &rarr; scroll down past the name and description.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .6rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .faint{ color:var(--faint); font-weight:400; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chips{ display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; font-size:.74rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.8rem; }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; }
          .gd .lnk{ color:var(--accent); font-weight:600; font-size:.72rem; border-radius:4px; }
          .gd .lg{ font-size:.62rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin:0 0 .35rem; }
          .gd b.lg{ font-size:.66rem; }

          /* 0/1 — the three blocks */
          .gd .blk{ display:flex; align-items:center; gap:.6rem; border:1px solid var(--line); border-radius:10px; padding:.5rem .7rem;
                    background:var(--surface); margin:0 0 .45rem; max-width:24rem; }
          .gd .blk b{ display:block; font-size:.8rem; color:var(--ink); }
          .gd .blk small{ display:block; font-size:.66rem; color:var(--faint); }
          .gd .bn{ flex:none; display:inline-grid; place-items:center; width:1.4rem; height:1.4rem; border-radius:50%; background:var(--accent); color:#fff; font-size:.7rem; font-weight:800; }
          .gd .actrow{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; margin-top:.6rem; font-size:.74rem; color:var(--ink); }
          .gd .tag3{ position:absolute; right:0; top:0; background:var(--accent-wash); color:var(--accent-ink); font-weight:800; font-size:.72rem; border-radius:8px; padding:.35rem .6rem; }

          /* the tick-box stack */
          .gd .ckstack{ display:flex; flex-direction:column; gap:.4rem; max-width:31rem; }
          .gd .ck{ display:flex; align-items:flex-start; gap:.5rem; }
          .gd .ck b{ display:block; font-size:.72rem; color:var(--ink); line-height:1.3; }
          .gd .ck small{ display:block; font-size:.62rem; color:var(--faint); line-height:1.4; margin-top:.1rem; }
          .gd .ck.dim{ opacity:.45; }
          .gd .tk{ position:relative; flex:none; width:15px; height:15px; margin-top:1px; border-radius:4px; border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); }
          .gd .tkon{ position:absolute; inset:-1px; border-radius:4px; background:var(--accent); color:#fff; font-size:.6rem; display:grid; place-items:center; }
          .gd .ringbox{ border-radius:10px; padding:.4rem .5rem; border:1px dashed var(--line); max-width:31rem; }
          .gd .row2c{ display:grid; grid-template-columns:auto 1fr; gap:1rem; align-items:start; margin-top:.8rem; }
          .gd .row2c > .colx{ display:flex; flex-direction:column; align-items:flex-start; }
          .gd .row2c > .lst{ justify-self:start; }

          /* mini width x drop grid */
          .gd .mg{ display:grid; grid-template-columns:repeat(4,3.3rem); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:7px; overflow:hidden; }
          .gd .mc{ background:var(--surface); text-align:center; font-size:.68rem; padding:.28rem .1rem; font-variant-numeric:tabular-nums; color:var(--ink); }
          .gd .mc.hd{ background:var(--panel); color:var(--soft); font-weight:700; font-size:.62rem; }
          .gd .mc.hit{ font-weight:800; outline:2px solid var(--accent); outline-offset:-2px; }

          /* a mini quote builder */
          .gd .qb{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--panel); margin-top:.8rem; max-width:27rem; }
          .gd .row2c .qb{ margin-top:0; }
          .gd .qbh{ font-size:.6rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
          .gd .qrow{ display:flex; gap:.4rem; flex-wrap:wrap; }
          .gd .qf label{ display:block; font-size:.58rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.15rem; }
          .gd .qbox{ display:inline-flex; align-items:center; min-width:3.6rem; height:24px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:0 .4rem; font-size:.72rem; color:var(--ink); }
          .gd .gd-play .qf.gone{ animation:pmGone .6s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .qf.gone{ opacity:.18; filter:grayscale(1); text-decoration:line-through; }
          @keyframes pmGone{ to{ opacity:.18; filter:grayscale(1); } }
          .gd .swapl{ display:grid !important; } .gd .swapl > span{ grid-area:1/1; }
          .gd .sumline{ margin-top:.45rem; font-size:.72rem; color:var(--ink); }
          .gd .lst{ display:grid; grid-template-columns:auto auto; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:7px; overflow:hidden; font-size:.7rem; }
          .gd .lst span{ background:var(--surface); padding:.22rem .55rem; font-variant-numeric:tabular-nums; color:var(--ink); }
          .gd .lst .h{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.62rem; }
          .gd .sysbar{ display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-top:.8rem; border:1px solid var(--line); border-radius:9px;
                       padding:.4rem .65rem; background:var(--panel); font-size:.76rem; font-weight:700; color:var(--ink); max-width:27rem; }

          /* per m2 */
          .gd .chain{ display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; margin:1rem 0; }
          .gd .chain.sm{ margin:0; }
          .gd .chain .v{ display:flex; flex-direction:column; align-items:center; border:1px solid var(--line); border-radius:10px; padding:.4rem .65rem; background:var(--surface); min-width:4.6rem; }
          .gd .chain .v b{ font-size:.98rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .chain .v small{ font-size:.56rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .chain .op{ font-size:.74rem; font-weight:800; border-radius:999px; padding:.15rem .5rem; color:var(--soft); }
          .gd .chain .op.m{ background:var(--err-wash); color:var(--err); } .gd .chain .op.p{ background:var(--good-wash); color:var(--good); }
          .gd .chain .v.out{ border-color:var(--good); } .gd .chain .v.out b{ color:var(--good); }
          .gd .fldm{ max-width:15rem; }
          .gd .fldm label{ display:block; font-size:.66rem; font-weight:700; color:var(--soft); margin-bottom:.2rem; }
          .gd .fldm small{ display:block; font-size:.6rem; color:var(--faint); margin-top:.25rem; line-height:1.4; }
          .gd .inp{ position:relative; display:inline-grid; align-items:center; min-width:4.4rem; height:26px; border:1px solid var(--border-strong,#c7ccd4);
                    border-radius:6px; background:var(--surface); padding:0 .45rem; font-size:.74rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp .ph{ color:var(--faint); }
          .gd .fldm .inp{ width:100%; box-sizing:border-box; }
          .gd .swapv > span{ grid-area:1/1; }

          /* 7 — only one */
          .gd .rank{ margin-left:auto; flex:none; font-size:.66rem; font-weight:800; border-radius:999px; padding:.1rem .45rem; background:var(--accent-wash); color:var(--accent-ink); }
          .gd .ign{ flex:none; font-size:.66rem; font-weight:900; color:var(--err); border:2px solid var(--err); border-radius:5px; padding:0 .3rem; text-transform:uppercase; letter-spacing:.06em; }
          .gd .okchip{ margin-left:auto; flex:none; font-size:.64rem; font-weight:700; border-radius:999px; padding:.1rem .45rem; background:var(--good-wash); color:var(--good); }
          .gd .sc[data-scene="7"] .ck{ align-items:center; border:1px solid var(--line); border-radius:8px; padding:.35rem .5rem; background:var(--surface); }
          .gd .errline{ display:inline-block; margin-top:.8rem; font-size:.74rem; font-weight:700; color:var(--err); background:var(--err-wash); border-radius:8px; padding:.35rem .6rem; }

          /* 8/9 — pricing source */
          .gd .fs{ border:1px solid var(--line); border-radius:10px; padding:.6rem .75rem; background:var(--surface); max-width:31rem; }
          .gd .fsh{ font-size:.64rem; color:var(--faint); margin:0 0 .5rem; line-height:1.45; }
          .gd .rd{ display:flex; gap:.5rem; align-items:flex-start; margin:.35rem 0; font-size:.72rem; color:var(--ink); line-height:1.45; }
          .gd .rd .faint{ font-size:.66rem; }
          .gd .dot{ position:relative; flex:none; width:14px; height:14px; margin-top:2px; border-radius:50%; border:1.5px solid var(--border-strong,#c7ccd4); box-sizing:border-box; background:var(--surface); }
          .gd .dot.a-sel{ background:var(--surface); }
          .gd .gd-done .dot.a-sel{ background:var(--surface) !important; border-color:var(--accent) !important; box-shadow:inset 0 0 0 3px var(--surface), inset 0 0 0 7px var(--accent); }
          .gd .gd-play .dot.a-sel{ animation:pmDot .35s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          @keyframes pmDot{ to{ border-color:var(--accent); box-shadow:inset 0 0 0 3px var(--surface), inset 0 0 0 7px var(--accent); } }
          .gd .dotoff i{ position:absolute; inset:2.5px; border-radius:50%; background:var(--accent); }
          .gd .radio{ font-size:.7rem; margin-right:.8rem; }
          .gd .flow{ display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; margin-top:1rem; }
          .gd .cellc{ border:1px solid var(--line); border-radius:9px; padding:.4rem .7rem; font-size:.74rem; color:var(--soft); background:var(--surface); }
          .gd .cellc b{ color:var(--ink); font-size:.9rem; margin-left:.2rem; }
          .gd .cellc.good{ border-color:var(--good); } .gd .cellc.good b{ color:var(--good); }

          /* 10 — face value */
          .gd .opt{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:24rem; font-size:.72rem; margin-bottom:.8rem; }
          .gd .opt > div{ display:grid; grid-template-columns:1fr 5rem 5rem; align-items:center; padding:.35rem .6rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .opt .oh{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.6rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .fvc{ display:flex; justify-content:center; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; max-width:31rem; }
          .gd .note{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .note h4{ margin:0 0 .35rem; font-size:.74rem; color:var(--ink); }
          .gd .sum{ display:flex; align-items:center; gap:.25rem; flex-wrap:wrap; font-size:.68rem; font-weight:700; }
          .gd .sum span{ border-radius:6px; padding:.12rem .35rem; background:var(--panel); color:var(--ink); }
          .gd .sum .m{ background:var(--err-wash); color:var(--err); } .gd .sum .p{ background:var(--good-wash); color:var(--good); }

          /* 11/12 — per-system table */
          .gd .ptbl{ border-collapse:collapse; min-width:19rem; max-width:31rem; }
          .gd .ptbl th{ text-align:left; font-size:.58rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .45rem; }
          .gd .ptbl td{ padding:.4rem .45rem; border-bottom:1px solid var(--line); color:var(--ink); font-size:.72rem; vertical-align:top; }
          .gd .tg{ font-size:.58rem; margin-top:.15rem; line-height:1.2; }
          .gd .tg.def, .gd .tg .def{ color:var(--faint); }
          .gd .tg.ovr, .gd .tg .ovr{ color:#9333ea; font-weight:700; }
          .gd .tg.nodisc{ color:var(--err); font-weight:700; }
          .gd .swapt{ display:grid; } .gd .swapt > span{ grid-area:1/1; }
          .gd .fromsup{ color:#065f46; font-weight:700; font-size:.76rem; }
          .gd .supc{ border-radius:6px; }
          :root[data-theme="dark"] .gd .fromsup{ color:#34d399; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .fromsup{ color:#34d399; } }

          /* 13 */
          .gd .colh{ display:inline-grid; font-size:.66rem; font-weight:800; color:var(--ink); border:1px solid var(--line); border-radius:6px; padding:.15rem .45rem; background:var(--panel); }
          .gd .colh > span{ grid-area:1/1; }
          .gd .terms{ display:flex; align-items:flex-end; gap:.5rem; flex-wrap:wrap; }
          .gd .terms label{ display:block; font-size:.56rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.15rem; }
          .gd .terms .inp{ min-width:3.4rem; }
          .gd .same{ display:inline-block; margin-top:.8rem; font-size:.74rem; font-weight:800; color:var(--accent-ink); background:var(--accent-wash); border-radius:8px; padding:.35rem .65rem; }
          .gd .sc[data-scene="13"] .row2c{ grid-template-columns:1fr 1fr; }

          /* 14 */
          .gd .plist{ margin-top:.6rem; border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.72rem; }
          .gd .plist div{ padding:.3rem .6rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .plist .ph2{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.62rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .sc[data-scene="14"] .row2c{ grid-template-columns:auto 1fr; }
          .gd .bstack{ display:grid; } .gd .bstack > div{ grid-area:1/1; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; } .gd .stage{ min-width:0; padding:.9rem .8rem; }
            .gd .sc{ min-height:440px; }
            .gd .row2c, .gd .two, .gd .sc[data-scene="13"] .row2c, .gd .sc[data-scene="14"] .row2c{ grid-template-columns:1fr; }
            .gd .tag3{ position:static; display:inline-block; margin-bottom:.5rem; }
            .gd .ptbl{ min-width:0; width:100%; }
            .gd .sc[data-scene="10"] .a-move{ display:none; }
          }',
        'demo'    => $demo,
        'body'    => '
          <p><b>Getting here.</b> <b>Products</b> &rarr; click the product &rarr; scroll down the Edit page. Three settings sit one under
             the other, and between them they decide <em>every</em> price this product quotes. Work down the page in the order it is
             printed: the <b>tick-boxes</b>, then <b>Pricing source</b>, then <b>Pricing per system</b>, then <b>Active</b> and
             <b>Save changes</b>. There is one grid, and retail and trade both price from it.</p>

          <p class="prose"><b>1) The pricing tick-boxes.</b> Leave them all empty and the blind prices the normal way: its width along
             the top of a grid, its drop down the side, and the price where they meet. Tick one only if this product is genuinely different.
             Each box has a bold label and a grey explanation under it.</p>
          <ul class="steps">
            <li><b>&ldquo;No fabric to choose (headrail only, track, spares).&rdquo;</b> (the word follows the product&rsquo;s own name for its
                fabrics.) This is about <em>what the customer picks</em>. The quote builder and InstaPrice hide the band and fabric pickers, and
                you use <b>one price table per system (no bands)</b>. It is still sized width &times; drop unless you also tick width only.</li>
            <li><b>&ldquo;Sized by width only &mdash; no drop (e.g. a headrail cut to length).&rdquo;</b> This is about <em>how it is sized</em>.
                The Drop field is hidden and each price table is a single <b>width &rarr; price</b> list. Once saved, an
                <b>Import width prices &raquo;</b> link appears in the <b>Systems</b> section header.</li>
            <li><b>&ldquo;Priced per slat (by drop) &mdash; e.g. vertical fabric only.&rdquo;</b> Each price table is a <b>drop &rarr;
                price-per-slat</b> list. At quote time there is no width: you enter the drop, and the <b>Quantity</b> box becomes
                <b>Number of slats</b>. Line price = slat rate &times; number of slats. Once saved, an <b>Import rates &raquo;</b> link appears.</li>
            <li><b>&ldquo;Priced per square metre &mdash; e.g. shutters.&rdquo;</b> One <b>&pound;/m&sup2; rate</b> per system and band,
                multiplied by the area (width &times; height). Both are required at quote time. Set the rate on the price-tables page.</li>
            <li><b>Minimum billable area (m&sup2;)</b> sits under the per-m&sup2; box (placeholder <code>e.g. 0.5 (blank = none)</code>):
                <em>&ldquo;The area is billed at no less than this. Blank or 0 = no minimum. Only applies to per-m&sup2; products.&rdquo;</em></li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Only tick one of width only, per slat and per square metre.</b> Nothing
             stops you ticking two, and nothing warns you &mdash; the pricing simply uses one, in this order: <b>width only &rarr; per slat
             &rarr; per square metre</b>, and quietly ignores the other. <b>No fabric to choose</b> answers a different question, so it
             combines with any of them. Settle the mode <em>before</em> you build price tables: change it afterwards and the tables
             will be the wrong shape.</div></div>

          <div class="oops"><b>If the tables don&rsquo;t match the mode</b>, the quote tells you, for example:
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>No &pound;/m&sup2; rate set for Shutters in this price list.</code></li>
               <li><code>No per-slat rate for drop 2400 mm. Try the next available drop.</code></li>
               <li><code>No exact price for width 1800 mm. Try the next available width.</code></li>
             </ul></div>

          <p class="prose"><b>2) Pricing source</b> &mdash; <em>&ldquo;What the numbers in this product&rsquo;s price tables actually are.
             This decides whether a trade account receives them as they stand, or with your buying discount and margin applied.&rdquo;</em></p>
          <ul class="steps">
            <li><b>Our price list</b> &mdash; we make it. The grid is our selling price (its cost sits in the cost grid). Trade accounts get
                these prices <b>exactly as they are</b>.</li>
            <li><b>Supplier price list</b> &mdash; we buy it in. The grid is the supplier&rsquo;s standard trade list. Trade accounts get it
                <b>less the discount plus the margin</b> set below. As a sum: list &times; (1 &minus; discount) &times; (1 + markup) &mdash;
                &pound;100 at 25% off and 100% markup = &pound;100 &times; 0.75 &times; 2 = <b>&pound;150</b>.</li>
            <li><b>Options.</b> Every option choice has a <b>Face value</b> tick, on by default: <em>&ldquo;the price you set is what&rsquo;s
                charged.&rdquo;</em> Untick it for a supplier list add-on (a taped finish, say) &mdash; then, on a supplier-priced product, it
                is run through the buying discount and markup along with the blind. On <b>Our price list</b> products options are always
                charged at face value.</li>
          </ul>
          <p class="prose">The same choice shows as a strip at the top of every price table on this product, with a <b>Change</b> link back
             here: <code>These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.</code></p>

          <p class="prose"><b>3) Pricing per system</b> &mdash; markup and discount tuned per system, because standard, premium and motorised
             are usually priced differently. <em>&ldquo;Your markup is applied on top of the price-table base; discount comes off after
             that.&rdquo;</em></p>
          <ul class="steps">
            <li><b>With systems</b> you get a table: <b>System</b>, <b>Markup %</b>, <b>Discount %</b>. With no systems you get two boxes
                and the line <em>&ldquo;No systems on this product yet &mdash; values below apply to every quote.&rdquo;</em></li>
            <li><b>Markup inherits.</b> Empty (or 0) shows <b>using default (50.00%)</b> &mdash; your figure from <b>Settings &rarr; Default
                margins</b> (<b>Default price-table markup %</b>). Type a number and the tag turns into a purple <b>override</b>. Clear it,
                or set 0, to go back to the default.</li>
            <li><b>Discount does not inherit.</b> Blank or 0 means <b>no discount</b> &mdash; there is no default behind it.</li>
            <li><b>&ldquo;from your supplier&rdquo;.</b> Where your supplier has given you a trade discount on their product, the Discount
                cell is a read-only green figure with <b>from your supplier</b> under it. That is the discount the pricing uses.</li>
            <li><b>The same numbers on the price table.</b> On a supplier-list product, each price table has <b>Buying discount %</b> and
                <b>Our markup %</b> with <b>Save terms</b> (<em>Applies to every band on Standard</em>). They are the very same figures, so a
                change in one place shows in the other.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin?</b> Whether the column says <b>Markup %</b> or
             <b>Margin %</b> follows <b>Settings &rarr; Default margins &rarr; Enter your margins as</b>. The customer price is identical either
             way &mdash; it only changes which number you type. For one awkward job, don&rsquo;t touch the product: use
             <b>Adjust price for this blind</b> in the quote builder, which changes that one line only.</div></div>

          <p class="prose"><b>Saving.</b> Below the pricing sits <b>Active</b> (<em>uncheck to hide from quote builder</em>), then
             <b>Save changes</b>. A bad figure brings the page back with a red message &mdash; <code>Markup % must be a non-negative
             number.</code>, <code>Discount % must be a non-negative number.</code> or <code>A markup or discount % is too large for the
             column. Maximum is 999999.99.</code> Fix it and save again: you get <b>Product updated.</b> and land on the <b>products
             list</b>, not back on the product. Changes apply to quotes from now on; quotes already saved keep their prices.</p>',
        'script'  => $script,
];
