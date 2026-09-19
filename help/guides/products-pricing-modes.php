<?php
declare(strict_types=1);

/**
 * Guide: products-pricing-modes
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Pricing: source, markup & mode',
        'eyebrow' => 'Products',
        'blurb'   => 'The product Edit page pricing block: own vs supplier list, your markup & buying discount per system, and how the base price is looked up.',
        'lede'    => 'The <b>Pricing</b> block on a product&rsquo;s <b>Edit</b> page has three parts: <b>where the prices come from</b>
                      (our own list or a supplier&rsquo;s), your <b>markup &amp; buying discount per system</b>, and the <b>pricing mode</b>
                      (width &times; drop, width-only, per slat, or per m&sup2;). Here&rsquo;s the whole block.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB{ display:block; }
          .gd .stage[data-step="3"] .scC, .gd .stage[data-step="4"] .scC{ display:block; }
          .gd .fsleg{ font-size:.66rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; }
          .gd .fshelp{ font-size:.68rem; color:var(--faint); margin:.5rem 0 0; }
          .gd .fshelp a, .gd .srcnote b{ color:var(--accent); }
          .gd .srcrow{ display:flex; gap:.5rem; align-items:flex-start; margin-bottom:.5rem; }
          .gd .srcrow .radio{ margin:0; }
          .gd .srctxt{ font-size:.74rem; color:var(--soft); }
          .gd .srctxt b{ color:var(--ink); }
          /* per-system markup/discount table */
          .gd .ptbl{ width:100%; max-width:23rem; border-collapse:collapse; }
          .gd .ptbl th{ text-align:left; font-size:.6rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .45rem; }
          .gd .ptbl td{ padding:.4rem .45rem; border-bottom:1px solid var(--line); color:var(--ink); font-size:.74rem; vertical-align:top; }
          .gd .boxs{ height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); display:inline-flex; align-items:center; padding:0 .45rem; font-size:.76rem; color:var(--ink); min-width:3rem; font-variant-numeric:tabular-nums; }
          .gd .boxs.empty{ color:var(--faint); }
          .gd .tag{ font-size:.58rem; margin-top:.14rem; line-height:1.1; }
          .gd .tag.def{ color:var(--faint); }
          .gd .tag.ovr{ color:#9333ea; font-weight:700; }
          /* modes */
          .gd .modes{ display:flex; flex-direction:column; gap:.5rem; }
          .gd .chkrow{ display:flex; align-items:flex-start; gap:.5rem; font-size:.78rem; color:var(--soft); }
          .gd .chkrow .tick{ margin-top:1px; flex:none; }
          .gd .stage[data-step="3"] .t-slat, .gd .stage[data-step="4"] .t-slat{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .stage[data-step="4"] .t-sqm{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .m-slat, .gd .stage[data-step="4"] .m-slat, .gd .stage[data-step="4"] .m-sqm{ color:var(--ink); font-weight:600; }
          .gd .minarea{ display:none; margin-top:.7rem; max-width:15rem; }
          .gd .stage[data-step="4"] .minarea{ display:block; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / edit</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Pricing</div>

                <!-- Scene A: pricing source -->
                <div class="osc scA">
                  <p class="fsleg">Pricing source</p>
                  <div class="srcrow">
                    <span class="radio"><span class="dot"></span></span>
                    <span class="srctxt"><b>Our price list</b> &mdash; we make it. Trade accounts get these prices exactly as they are.</span>
                  </div>
                  <div class="srcrow">
                    <span class="radio on"><span class="dot"></span></span>
                    <span class="srctxt"><b>Supplier price list</b> &mdash; we buy it in. Trade accounts get it <b>less the discount plus the margin</b> set below.</span>
                  </div>
                </div>

                <!-- Scene B: markup + discount per system -->
                <div class="osc scB">
                  <p class="fsleg">Pricing per system</p>
                  <table class="ptbl">
                    <thead><tr><th>System</th><th>Markup %</th><th>Discount %</th></tr></thead>
                    <tbody>
                      <tr><td>Standard</td><td><span class="boxs empty">&nbsp;</span><div class="tag def">using default (50%)</div></td><td><span class="boxs">0.00</span></td></tr>
                      <tr><td>Motorised</td><td><span class="boxs">100</span><div class="tag ovr">override</div></td><td><span class="boxs">25</span></td></tr>
                    </tbody>
                  </table>
                  <p class="fshelp">Your markup is applied on top of the price-table base; discount comes off after that. Set markup to 0 to inherit the tenant default (<b>50%</b> &mdash; change on Settings).</p>
                </div>

                <!-- Scene C: pricing mode -->
                <div class="osc scC">
                  <p class="fsleg">Pricing mode</p>
                  <p class="ldesc2">Most products price on a width &times; drop grid &mdash; tick a box only if this one is different.</p>
                  <div class="modes">
                    <label class="chkrow"><span class="tick">&check;</span> This product has no fabrics (headrail only, track, spares)</label>
                    <label class="chkrow"><span class="tick">&check;</span> Priced by width only (no drop) &mdash; e.g. a headrail or track</label>
                    <label class="chkrow m-slat"><span class="tick t-slat">&check;</span> Priced per slat (by drop) &mdash; e.g. vertical fabric only</label>
                    <label class="chkrow m-sqm"><span class="tick t-sqm">&check;</span> Priced per square metre &mdash; e.g. shutters</label>
                  </div>
                  <div class="minarea"><label>Minimum billable area (m&sup2;)</label><div class="boxs" style="min-width:8rem">0.5</div></div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Pricing source &mdash; our list, or a supplier&rsquo;s?</b>
                  <b class="c2"><span class="n">2</span> Markup &amp; buying discount, per system.</b>
                  <b class="c3"><span class="n">3</span> Pricing mode &mdash; how the base price is looked up.</b>
                  <b class="c4 good"><span class="n">4</span> Per m&sup2;? Add a minimum billable area.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Pricing</b> block on a product&rsquo;s <b>Edit</b> page is three settings stacked together. Get them right here and every
             quote for this product prices correctly.</p>
          <ul class="steps">
            <li><b>Pricing source</b> &mdash; what the numbers in the price tables actually <em>are</em>.
                <b>Our price list</b> = our own selling prices; trade accounts get them <em>exactly as they are</em>.
                <b>Supplier price list</b> = a bought-in trade list; trade accounts (and your quotes) get it <b>less your buying discount, plus
                your markup</b>. This is the same choice shown in the banner on the price-table screen.</li>
            <li><b>Pricing per system</b> &mdash; your <b>Markup %</b> and <b>Discount %</b>, tuned per system (Standard / Motorised are usually
                different). Your markup goes <b>on top of the price-table base; the discount comes off after that</b>. Leave a box <b>blank / 0</b>
                to <b>inherit the tenant default</b> (a &ldquo;using default (X%)&rdquo; tag shows); type a number and it becomes an
                <b>override</b> (purple tag). These are the <em>same numbers</em> you can edit straight on the price table
                (see <em>Building price tables</em>).</li>
            <li><b>Pricing mode</b> &mdash; how the base price is looked up. The default is a <b>width &times; drop grid</b> (tick nothing).
                The others:
                <ul>
                  <li><b>No fabrics</b> &mdash; headrail, track or spares; priced on system &times; size, no fabric picker.</li>
                  <li><b>Width only</b> &mdash; priced on width, no drop; each table is a width &rarr; price list.</li>
                  <li><b>Per slat</b> &mdash; priced by the <b>drop</b> &times; the <b>number of slats</b> (vertical fabric replacement).</li>
                  <li><b>Per square metre</b> &mdash; a single <b>&pound;/m&sup2; rate</b> &times; area (width &times; height), e.g. shutters, with an optional <b>minimum billable area</b>.</li>
                </ul>
            </li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin?</b> Whether the column says <b>Markup %</b> or
             <b>Margin %</b> is set once in <b>Settings &rarr; Default margins</b> &mdash; the customer price is identical either way, it only
             changes which number you type. The tenant default there is what an empty box inherits.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Pick one mode, and pick the right one.</b> Width-only, per-slat and per-m&sup2;
             <b>can&rsquo;t be combined</b> &mdash; they price in different shapes, and the mode decides what the quote builder asks for and what
             your price tables look like. Switching it later means re-importing your prices, so get it right <em>before</em> you build the tables.</div></div>',
        'script'  => [
            ['0:00', 'Pricing source: supplier selected.',    'The pricing block starts with the source. Is this our own price list — which trade accounts get exactly as-is — or a supplier list we buy in? Pick supplier, and your discount and margin get applied on top.', 1],
            ['0:11', 'Markup & discount per system.',         'Then your markup and buying discount, per system. Leave a system blank to inherit your Settings default, or type a value to override it — like a hundred percent markup, twenty-five off, on motorised.', 2],
            ['0:22', 'Pricing mode; per slat ticked.',        'Next, the pricing mode — how the base price is looked up. Most products use a width-by-drop grid, so you tick nothing. Vertical fabric prices per slat.', 3],
            ['0:31', 'Per m² ticked; minimum area appears.',  'And shutters price per square metre — width times height — with an optional minimum billable area.', 4],
        ],
];
