<?php
declare(strict_types=1);

/**
 * Guide: products-pricing-modes
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the whole pricing block on /admin/products/edit.php, drawn in the
 * REAL form order: the mode tick-boxes, then Pricing source, then Pricing
 * per system.
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Pricing: source, markup & mode',
        'eyebrow' => 'Products',
        'blurb'   => 'Four tick-boxes that decide how this blind is measured and priced, then where the price list came from, then your markup and buying discount per system.',
        'lede'    => 'Near the bottom of a product&rsquo;s <b>Edit</b> page, three things sit one under the other. First a stack of
                      <b>tick-boxes</b> &mdash; how this blind is measured and priced. Then <b>Pricing source</b> &mdash; whether the
                      numbers in your price tables are our own selling prices or a supplier&rsquo;s trade list. Then
                      <b>Pricing per system</b> &mdash; your markup and your buying discount. Below them sit the <b>Active</b> box and
                      the <b>Save changes</b> button. Here is the whole run, in the order you meet it.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA, .gd .stage[data-step="2"] .scA,
          .gd .stage[data-step="3"] .scA, .gd .stage[data-step="4"] .scA{ display:block; }
          .gd .stage[data-step="5"] .scB, .gd .stage[data-step="6"] .scB{ display:block; }
          .gd .stage[data-step="7"] .scC, .gd .stage[data-step="8"] .scC{ display:block; }

          /* fieldset chrome, matching the real uppercase legend + grey intro hint */
          .gd .fsleg{ font-size:.66rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; }
          .gd .fshelp{ font-size:.68rem; color:var(--faint); margin:.5rem 0 0; line-height:1.45; }
          .gd .fshelp a, .gd .fshelp b{ color:var(--accent); }

          /* the checkbox stack — bold label + a grey multi-line hint under it */
          .gd .modes{ display:flex; flex-direction:column; gap:.55rem; }
          .gd .chkrow{ display:flex; align-items:flex-start; gap:.5rem; }
          .gd .chkrow .tick{ margin-top:2px; flex:none; }
          .gd .chklbl{ font-size:.76rem; font-weight:700; color:var(--ink); display:block; line-height:1.3; }
          .gd .chkhint{ font-size:.66rem; color:var(--faint); display:block; margin-top:.12rem; line-height:1.4; }
          .gd .offscope{ opacity:.5; }
          .gd .aside{ font-size:.6rem; color:var(--faint); font-style:italic; font-weight:400; }
          .gd .stage[data-step="2"] .t-wo, .gd .stage[data-step="3"] .t-slat, .gd .stage[data-step="4"] .t-sqm{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .minarea{ margin-top:.7rem; max-width:14rem; }

          /* Pricing source rows — dot on the left, real copy wrapping beside it */
          .gd .srcrow{ display:flex; gap:.5rem; align-items:flex-start; margin-bottom:.6rem; }
          .gd .srcrow .radio{ margin:0; }
          .gd .srctxt{ font-size:.72rem; color:var(--faint); line-height:1.45; }
          .gd .srctxt b{ color:var(--ink); font-size:.76rem; }
          .gd .stage[data-step="5"] .r-own, .gd .stage[data-step="6"] .r-sup{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="5"] .r-own .dot, .gd .stage[data-step="6"] .r-sup .dot{ border-color:var(--accent); }
          .gd .stage[data-step="5"] .r-own .dot::after,
          .gd .stage[data-step="6"] .r-sup .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }

          /* per-system markup / discount table */
          .gd .ptbl{ width:100%; max-width:24rem; border-collapse:collapse; }
          .gd .ptbl th{ text-align:left; font-size:.6rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .45rem; }
          .gd .ptbl td{ padding:.4rem .45rem; border-bottom:1px solid var(--line); color:var(--ink); font-size:.74rem; vertical-align:top; }
          .gd .ptbl .box{ width:4.4rem; height:26px; font-size:.75rem; font-variant-numeric:tabular-nums; }
          .gd .tag{ font-size:.58rem; margin-top:.16rem; line-height:1.15; }
          .gd .tag.def{ color:var(--faint); }
          .gd .tag.ovr{ color:#9333ea; font-weight:700; }
          .gd .fromsup{ color:#065f46; font-weight:700; font-size:.78rem; }
          :root[data-theme="dark"] .gd .fromsup{ color:#34d399; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .fromsup{ color:#34d399; } }

          /* the Active toggle + Save row that close the form */
          .gd .actrow{ display:flex; align-items:center; gap:.45rem; margin-top:.8rem; font-size:.76rem; color:var(--ink); }
          .gd .actrow .aht{ color:var(--faint); font-size:.66rem; }
          .gd .fixblk{ display:none; margin-top:.7rem; }
          .gd .stage[data-step="8"] .fixblk{ display:flex; flex-direction:column; gap:.45rem; }
          .gd .stage[data-step="8"] .save{ filter:brightness(1.12); }

          /* own progressive fills — the shared f1..f5 map to steps 1..5, and these
             fields fill at steps 4, 7 and 8 instead. Same .box/.ph/.val parts. */
          .gd .stage[data-step="4"] .fa .ph{ opacity:0; }
          .gd .stage[data-step="4"] .fa .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="4"] .fa{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="7"] .fb .ph, .gd .stage[data-step="8"] .fb .ph,
          .gd .stage[data-step="7"] .fc .ph, .gd .stage[data-step="8"] .fc .ph{ opacity:0; }
          .gd .stage[data-step="7"] .fb .val, .gd .stage[data-step="8"] .fb .val,
          .gd .stage[data-step="7"] .fc .val, .gd .stage[data-step="8"] .fc .val{ opacity:1; }
          .gd .stage[data-step="7"] .fb .val, .gd .stage[data-step="7"] .fc .val{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="7"] .fb, .gd .stage[data-step="7"] .fc{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="7"] .supcell{ box-shadow:inset 0 0 0 2px var(--accent); border-radius:6px; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / edit</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Edit product &mdash; Roller Blind</div>

                <!-- Scene A: the tick-box stack, in real DOM order -->
                <div class="osc scA">
                  <div class="modes">
                    <label class="chkrow">
                      <span class="tick">&check;</span>
                      <span><span class="chklbl">No fabric to choose (headrail only, track, spares).</span>
                        <span class="chkhint">This is about <em>what the customer picks</em>. The quote builder and InstaPrice hide the band and fabric pickers, and you use one price table per system.</span></span>
                    </label>
                    <label class="chkrow">
                      <span class="tick t-wo">&check;</span>
                      <span><span class="chklbl">Sized by width only &mdash; no drop (e.g. a headrail cut to length).</span>
                        <span class="chkhint">This is about <em>how it is sized</em>. The Drop field is hidden and each price table is a single width &rarr; price list.</span></span>
                    </label>
                    <label class="chkrow">
                      <span class="tick t-slat">&check;</span>
                      <span><span class="chklbl">Priced per slat (by drop) &mdash; e.g. vertical fabric only.</span>
                        <span class="chkhint">Each price table is a <em>drop &rarr; price-per-slat</em> list. At quote time you enter the drop and the number of slats &mdash; no width.</span></span>
                    </label>
                    <label class="chkrow">
                      <span class="tick t-sqm">&check;</span>
                      <span><span class="chklbl">Priced per square metre &mdash; e.g. shutters.</span>
                        <span class="chkhint">A single &pound;/m&sup2; rate per system and band, multiplied by the area (width &times; height). Both width and height are required at quote time.</span></span>
                    </label>
                  </div>
                  <div class="minarea fld">
                    <label>Minimum billable area (m&sup2;)</label>
                    <div class="box fa"><span class="ph">e.g. 0.5 (blank = none)</span><span class="val">0.5</span></div>
                    <span class="chkhint">The area is billed at no less than this. Blank or 0 = no minimum.</span>
                  </div>
                  <label class="chkrow offscope" style="margin-top:.7rem">
                    <span class="tick">&check;</span>
                    <span><span class="chklbl">Show separate &ldquo;Colour&rdquo; column on fabric forms. <span class="aside">(not a pricing setting)</span></span>
                      <span class="chkhint">Sits in the same run of boxes, but it only changes the fabric form &mdash; nothing to do with price.</span></span>
                  </label>
                </div>

                <!-- Scene B: Pricing source -->
                <div class="osc scB">
                  <p class="fsleg">Pricing source</p>
                  <p class="ldesc2">What the numbers in this product&rsquo;s price tables actually are. This decides whether a trade account receives them as they stand, or with your buying discount and margin applied.</p>
                  <div class="srcrow">
                    <span class="radio r-own"><span class="dot"></span></span>
                    <span class="srctxt"><b>Our price list</b> &mdash; we make it. The grid is our selling price (its cost sits in the cost grid). Selling is cost plus a percentage, labour and overhead, so it isn&rsquo;t a straight percentage of cost. Trade accounts get these prices <b>exactly as they are</b>.</span>
                  </div>
                  <div class="srcrow">
                    <span class="radio r-sup"><span class="dot"></span></span>
                    <span class="srctxt"><b>Supplier price list</b> &mdash; we buy it in. The grid is the supplier&rsquo;s standard trade list. Trade accounts get it <b>less the discount plus the margin</b> set below.</span>
                  </div>
                </div>

                <!-- Scene C: Pricing per system, then Active + Save -->
                <div class="osc scC">
                  <p class="fsleg">Pricing per system</p>
                  <p class="ldesc2">Markup and discount can be tuned per system. Your markup is applied on top of the price-table base; discount comes off after that. <b>Set markup to 0 to inherit the tenant default (50.00% &mdash; change on Settings).</b></p>
                  <table class="ptbl">
                    <thead><tr><th>System</th><th>Markup %</th><th>Discount %</th></tr></thead>
                    <tbody>
                      <tr>
                        <td>Standard</td>
                        <td><div class="box"><span class="ph">50.00</span></div><div class="tag def">using default (50.00%)</div></td>
                        <td><div class="box"><span class="ph">0.00</span></div></td>
                      </tr>
                      <tr>
                        <td>Motorised</td>
                        <td><div class="box fb"><span class="ph">50.00</span><span class="val">100</span></div><div class="tag ovr">override</div></td>
                        <td><div class="box fc"><span class="ph">0.00</span><span class="val">25</span></div></td>
                      </tr>
                      <tr>
                        <td>Motorised &mdash; Somfy</td>
                        <td><div class="box"><span class="ph">50.00</span></div><div class="tag def">using default (50.00%)</div></td>
                        <td class="supcell"><span class="fromsup">25.00%</span><div class="tag def">from your supplier</div></td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="actrow"><span class="tick on">&check;</span> Active <span class="aht">uncheck to hide from quote builder</span></div>
                  <div class="save">Save changes</div>
                  <div class="fixblk">
                    <div class="errbanner"><span>&#9888;</span><div>Typed <b>-5</b> in Markup %: <b>Markup % must be a non-negative number.</b></div></div>
                    <div class="okbanner"><span>&#10003;</span><div><b>Product updated.</b> &mdash; and you land back on the products list.</div></div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Tick nothing and it prices on a width &times; drop grid.</b>
                  <b class="c2"><span class="n">2</span> Sized by width only &mdash; the Drop field goes away.</b>
                  <b class="c3"><span class="n">3</span> Priced per slat &mdash; drop &times; the number of slats.</b>
                  <b class="c4"><span class="n">4</span> Per square metre, with a minimum billable area.</b>
                  <b class="c5"><span class="n">5</span> Pricing source &mdash; our own selling prices&hellip;</b>
                  <b class="c6"><span class="n">6</span> &hellip;or a supplier list, less discount, plus margin.</b>
                  <b class="c7"><span class="n">7</span> Markup and discount, tuned per system.</b>
                  <b class="c8 good"><span class="n">8</span> Fix the slip, save, and you are done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Three settings sit one under the other near the bottom of a product&rsquo;s <b>Edit</b> page, and between them they decide
             <em>every</em> price this product ever quotes. Nothing here is a retail-versus-trade setting &mdash; there is one grid and
             everybody prices from it. Work down the page in the order it is printed: the <b>tick-boxes</b> first, then
             <b>Pricing source</b>, then <b>Pricing per system</b>.</p>

          <p class="prose"><b>1) The four tick-boxes</b> &mdash; leave every one of them alone and the blind prices the normal way, off a
             grid with widths down one side and drops across the other. Tick one only if this product is genuinely different. Each box has a
             bold label and a grey explanation under it, so the stack is taller than you expect &mdash; scroll slowly.</p>
          <ul class="steps">
            <li><b>&ldquo;No fabric to choose (headrail only, track, spares).&rdquo;</b> This one is about <em>what the customer picks</em>.
                Tick it and the quote builder and InstaPrice hide the band and fabric pickers, and you keep <b>one price table per system,
                no bands</b>. It is still sized width &times; drop unless you also tick width only.</li>
            <li><b>&ldquo;Sized by width only &mdash; no drop (e.g. a headrail cut to length).&rdquo;</b> About <em>how it is sized</em>. The
                <b>Drop</b> field disappears at quote time and each price table becomes a single <b>width &rarr; price</b> list. Save, and an
                <b>Import width prices &raquo;</b> link appears in the <b>Systems</b> section header.</li>
            <li><b>&ldquo;Priced per slat (by drop) &mdash; e.g. vertical fabric only.&rdquo;</b> Each table is a <b>drop &rarr;
                price-per-slat</b> list. At quote time there is no Width, and the Quantity box is relabelled <b>Number of slats</b>. Save, and
                an <b>Import rates &raquo;</b> link appears in the Systems section.</li>
            <li><b>&ldquo;Priced per square metre &mdash; e.g. shutters.&rdquo;</b> One <b>&pound;/m&sup2; rate</b> per system and band,
                times width &times; height. Both are required, and the quote shows you the area it used, e.g. <b>1.44 m&sup2;</b>.</li>
          </ul>
          <p class="prose">The first box and the other three answer different questions, so the first one combines with any of them. The
             other three do not combine with each other.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Tick two by accident and nothing warns you.</b> The form is happy to let
             you, but the pricing engine picks just one, silently, in this order: <b>width only &rarr; per slat &rarr; per square metre</b>.
             The box you actually meant is quietly ignored. If prices look wrong, come back here and check only one is ticked.</div></div>

          <p class="prose"><b>Minimum billable area (m&sup2;)</b> sits just under the per-m&sup2; box and is <em>always</em> on screen, ticked
             or not &mdash; it is simply ignored unless per-m&sup2; is on. Type the smallest area you will charge for (the placeholder reads
             <code>e.g. 0.5 (blank = none)</code>). Blank or 0 means no minimum.</p>

          <div class="oops"><b>If the tables do not match the mode</b>, the quote builder tells you so in plain words:
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>No &pound;/m&sup2; rate set for Shutters in this price list.</code></li>
               <li><code>No per-slat rate for drop 2400 mm. Try the next available drop.</code></li>
               <li><code>No exact price for width 1800 mm. Try the next available width.</code></li>
             </ul>
             That is the <em>mode and the tables disagreeing</em>, not a missing price. Change the mode after the tables are built and you will
             be re-importing them, so settle the mode first.</div>

          <p class="prose"><b>2) Pricing source</b> &mdash; two plain radio buttons that say what the numbers in your price tables
             <em>are</em>.</p>
          <ul class="steps">
            <li><b>Our price list</b> &mdash; we make it. The grid is already our <b>selling</b> price, so a trade account gets it
                <b>exactly as it is</b>, and the catalogue push sends it through untouched.</li>
            <li><b>Supplier price list</b> &mdash; we buy it in. The grid is the supplier&rsquo;s standard trade list, so our price is that
                list <b>less our buying discount, plus our margin</b>. It is one sum:
                <code>sell = base &times; (1 &minus; discount/100) &times; (1 + markup/100)</code>. A &pound;100 base at 25% off and 100% markup
                is &pound;100 &times; 0.75 &times; 2 = <b>&pound;150</b>.</li>
            <li><b>It moves your option prices too.</b> On a supplier list the extras are the supplier&rsquo;s own surcharges, so they go
                through that same sum and your tenant-wide options markup is deliberately skipped. On our own list, options are charged at
                <b>face value</b>. Flip this radio and option prices move as well as blind prices.</li>
          </ul>
          <p class="prose">The same choice is repeated in a banner at the top of every price table on this product, with a <b>Change</b> link
             back to here &mdash; <code>These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.</code></p>

          <p class="prose"><b>3) Pricing per system</b> &mdash; the same two numbers, tuned per system, because premium, motorised and
             standard are rarely priced alike.</p>
          <ul class="steps">
            <li><b>With systems</b> you get a small table: <b>System</b>, <b>Markup %</b>, <b>Discount %</b>, one row each. Without systems you
                get a simple two-field row instead, plus the line <em>&ldquo;No systems on this product yet &mdash; values below apply to
                every quote. Add systems on the Systems page to split them out.&rdquo;</em></li>
            <li><b>Markup inherits.</b> Leave the box empty (or 0) and the row shows <b>using default (50.00%)</b> &mdash; the tenant default
                set on <b>Settings</b>. Hover it and it says <em>&ldquo;Inheriting tenant default &mdash; leave empty / 0 to keep using
                it.&rdquo;</em> Type a number and the tag turns into a purple <b>override</b>: <em>&ldquo;Override of the tenant default. Clear
                or set 0 to revert.&rdquo;</em></li>
            <li><b>Discount does not inherit.</b> This catches people out. A blank or 0 in <b>Discount %</b> means exactly that &mdash;
                <b>no discount</b>. There is no tenant default sitting behind it.</li>
            <li><b>&ldquo;from your supplier&rdquo; &mdash; why can I not type in that box?</b> When the maker has given you a trade discount
                on their own product, the Discount cell is not a box at all: it is a read-only green percentage with <b>from your supplier</b>
                under it. That discount is the one the pricing uses, so yours is set aside.</li>
            <li><b>The same two numbers live in a second place.</b> On a supplier product&rsquo;s price-table screen there is a
                <b>Buying discount %</b> / <b>Our markup %</b> pair with a <b>Save terms</b> button, noting
                <code>Applies to every band on Standard. Leave blank or 0 to inherit the default.</code> It writes the very same rows, so a
                number changed there shows up changed here.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin?</b> Whether the column says <b>Markup %</b> or
             <b>Margin %</b> is set once in <b>Settings &rarr; Default margins</b>. The customer&rsquo;s price is identical either way &mdash;
             it only changes which number you type in the box.</div></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>One awkward job? Do not touch the product rate.</b> In the quote builder,
             open <b>&ldquo;Adjust price for this blind&rdquo;</b> and use <b>Discount % (this blind)</b> or <b>Markup % (this blind)</b> &mdash;
             both placeholder <em>product default</em>, and both change that <em>one line only</em>. A Wally tax (WT charge) style uplift for a
             difficult fit belongs there, not on the product.</div></div>

          <p class="prose"><b>What happens when you save.</b> Press <b>Save changes</b>. Type something silly and the form does not stop you
             &mdash; it comes back with a red banner reading <code>Markup % must be a non-negative number.</code> (or
             <code>Discount % must be a non-negative number.</code>, or
             <code>A markup or discount % is too large for the column. Maximum is 999999.99.</code>). Correct it, save again, and you get
             <b>Product updated.</b> and land back on the <b>products list</b> &mdash; not on the product &mdash; so open it again if you have
             more to do. The change applies to every <em>future</em> quote on this product; quotes already saved keep the prices they were
             saved with.</p>',
        'script'  => [
            ['0:00', 'Tick-box stack, all five clear.',              'Scroll down the product Edit page and you come to a little stack of tick-boxes. Leave every one of them alone and the blind prices the normal way — a grid of widths down one side and drops across the other. Only tick something here if this product is genuinely different. The last box, about a colour column, is not a pricing setting at all, so ignore it for now.', 1],
            ['0:18', 'Sized by width only ticks on.',                'The second box reads: sized by width only, no drop — for example a headrail cut to length. Tick that and the Drop field disappears when someone quotes it, and each price table becomes a simple list of width against price. Save the product and an Import width prices link appears up in the Systems section, ready for that list.', 2],
            ['0:36', 'Width only clears; per slat ticks on.',        'Untick that, and take the next one: priced per slat, by drop — vertical fabric only, for instance. Now each table is a list of drop against the price for one slat. At quote time there is no width at all, and the Quantity box is renamed Number of slats. Save, and an Import rates link appears instead.', 3],
            ['0:54', 'Per slat clears; per square metre ticks on; 0.5 rolls into minimum area.', 'And the last one: priced per square metre — shutters. One pound-per-square-metre rate for each system and band, multiplied by width times height, and the quote shows you the area it used, like one point four four square metres. The minimum billable area box underneath is always on screen, ticked or not — put nought point five in it and nothing bills under half a metre. Blank, or nought, means no minimum. Now a warning: tick two of these boxes by accident and nothing warns you. Width only wins, then per slat, then per square metre, and the box you actually meant is quietly ignored.', 4],
            ['1:22', 'Pricing source; Our price list selected.',     'Under the tick-boxes comes Pricing source — two plain radio buttons. The first says: our price list, we make it. That means the grid is already our selling price, so a trade account gets it exactly as it stands, and the catalogue push sends it straight through, untouched.', 5],
            ['1:38', 'Dot moves to Supplier price list.',            'The second says: supplier price list, we buy it in. Now the grid is the supplier own trade list, so our price is that list less our buying discount, plus our margin — one sum: the base, times one minus the discount, times one plus the markup. And here is something you would never guess: on a supplier list the options are the supplier own surcharges, so they go through that same sum, where on our own list options are charged at face value. Flipping this radio moves option prices as well as blind prices.', 6],
            ['2:00', 'Markup 100 and discount 25 roll into Motorised; supplier row highlighted.', 'Last comes Pricing per system, where those two numbers are tuned per system. Leave a markup box empty and it says using default, fifty percent — that is the tenant default you set on Settings. Type a number, say a hundred on motorised, and the tag turns purple and says override. Now the bit that catches people out: a blank or nought discount is not inherited from anywhere. It simply means no discount. And look at the third row — when the maker has given you a trade discount on their own product, the discount is fixed and shown in green with from your supplier under it. You cannot type in that box, because their discount is the one the pricing uses.', 7],
            ['2:30', 'Minus five rejected, then corrected and saved.', 'One last thing. Type minus five in a markup box and the page does not stop you — it comes back and says: markup percent must be a non-negative number. Put a sensible number in, press Save changes, and you get Product updated. Mind you, saving drops you back on the products list rather than leaving you here, so if you want to keep working on the same product, just open it again.', 8],
        ],
];
