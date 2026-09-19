<?php
declare(strict_types=1);

/**
 * Guide: products-import-price-tables
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Building price tables',
        'eyebrow' => 'Products',
        'blurb'   => 'The real workflow: paste the supplier grid, set your buying discount and markup right on the table, save, clone the next band, then prove the sell price in Live preview.',
        'lede'    => 'A price table is the <b>supplier&rsquo;s grid</b> &mdash; every width &times; drop and their list price. You paste that in,
                      then set <b>your buying discount and your markup right here</b>, and the system works out the sell price. Set the sizes,
                      paste the prices, <b>enter your discount &amp; markup</b>, save, clone the next band, and <b>prove it in Live preview</b>.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }

          /* ---- price-source banner (all steps) ---- */
          .gd .psbanner{ display:flex; align-items:center; flex-wrap:wrap; gap:.35rem .5rem; border:1px solid #e6b64c; background:rgba(230,182,76,.14); border-radius:9px; padding:.42rem .6rem; font-size:.7rem; color:var(--soft); margin-bottom:.7rem; }
          .gd .psbanner .pill{ background:#e6b64c; color:#4a3600; font-weight:700; border-radius:20px; padding:.08rem .55rem; font-size:.66rem; white-space:nowrap; }
          .gd .psbanner .chg{ margin-left:auto; color:var(--accent); font-weight:600; }

          /* ---- Quick start dialog (steps 1-2) ---- */
          .gd .qsdlg{ display:none; border:1px solid var(--line); border-radius:10px; padding:.7rem .8rem; background:var(--panel); max-width:24rem; }
          .gd .stage[data-step="1"] .qsdlg, .gd .stage[data-step="2"] .qsdlg{ display:block; }
          .gd .qshd{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.55rem; }
          .gd .qsrow{ margin-bottom:.55rem; }
          .gd .qsrow label{ display:block; font-size:.68rem; font-weight:600; color:var(--soft); margin-bottom:.2rem; }
          .gd .ta2{ position:relative; min-height:1.9rem; border:1px solid var(--line); border-radius:7px; background:var(--surface); padding:.34rem .5rem; font-size:.74rem; color:var(--ink); overflow:hidden; }
          .gd .ta2 .def{ color:var(--faint); }
          .gd .stage[data-step="1"] .ta2 .def{ text-decoration:line-through; opacity:.55; }
          .gd .stage[data-step="2"] .ta2 .def{ display:none; }
          .gd .ta2 .rv{ display:none; }
          .gd .stage[data-step="2"] .ta2 .rv{ display:inline-block; clip-path:inset(0 100% 0 0); animation:gdRoll .8s ease forwards; }
          .gd .stage[data-step="2"] .ta2{ box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.35)); }
          .gd .clearchip{ display:inline-block; margin-top:.15rem; font-size:.64rem; color:var(--faint); border:1px solid var(--line); border-radius:20px; padding:.05rem .5rem; }
          .gd .stage[data-step="1"] .clearchip{ box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.35)); color:var(--ink); }
          .gd .buildbtn{ display:inline-flex; margin-top:.2rem; background:var(--nav); color:#fff; border-radius:8px; padding:.4rem .8rem; font-size:.78rem; font-weight:600; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="3"] .buildbtn{ transform:scale(.96); filter:brightness(1.25); }

          /* ---- the grid (steps 3-8) ---- */
          .gd .ptwrap{ display:none; margin-top:.2rem; }
          .gd .stage[data-step="3"] .ptwrap, .gd .stage[data-step="4"] .ptwrap,
          .gd .stage[data-step="5"] .ptwrap, .gd .stage[data-step="6"] .ptwrap,
          .gd .stage[data-step="7"] .ptwrap, .gd .stage[data-step="8"] .ptwrap{ display:block; }
          .gd .ptcap{ font-size:.64rem; color:var(--faint); margin:0 0 .25rem; }
          .gd .ptgrid{ display:grid; grid-template-columns:2.9rem repeat(4,1fr); gap:2px; background:var(--line); border:1px solid var(--line); border-radius:8px; padding:2px; max-width:23rem; }
          .gd .ptc{ background:var(--surface); padding:.26rem; text-align:center; font-size:.7rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .ptc.hd{ background:var(--panel); color:var(--faint); font-weight:700; }
          .gd .ptc .pn{ opacity:0; transition:opacity .45s ease; }
          .gd .stage[data-step="4"] .ptc .pn, .gd .stage[data-step="5"] .ptc .pn,
          .gd .stage[data-step="6"] .ptc .pn, .gd .stage[data-step="7"] .ptc .pn,
          .gd .stage[data-step="8"] .ptc .pn{ opacity:1; }
          /* the list cell we trace through to the sell price */
          .gd .stage[data-step="7"] .ptc.qc{ background:#d1fae5; color:#065f46; font-weight:700; box-shadow:inset 0 0 0 2px #34d399; }

          .gd .savebtn2{ margin-top:.7rem; display:inline-flex; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .85rem; font-size:.82rem; font-weight:600; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="5"] .savebtn2{ transform:scale(.97); filter:brightness(1.2); }
          .gd .ok-saved{ display:none; margin-top:.6rem; }
          .gd .stage[data-step="5"] .ok-saved, .gd .stage[data-step="6"] .ok-saved,
          .gd .stage[data-step="7"] .ok-saved, .gd .stage[data-step="8"] .ok-saved{ display:flex; }

          /* ---- trade-terms form: buying discount + markup (steps 6-8) ---- */
          .gd .tterms{ display:none; margin-top:.7rem; border:1px solid #e6b64c; border-radius:9px; padding:.55rem .7rem; background:rgba(230,182,76,.1); max-width:23rem; }
          .gd .stage[data-step="6"] .tterms, .gd .stage[data-step="7"] .tterms, .gd .stage[data-step="8"] .tterms{ display:block; }
          .gd .stage[data-step="6"] .tterms{ box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.3)); }
          .gd .tthd{ font-size:.68rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.45rem; }
          .gd .ttrow{ display:flex; gap:1rem; }
          .gd .ttf label{ display:block; font-size:.64rem; font-weight:600; color:var(--soft); margin-bottom:.18rem; }
          .gd .boxs{ height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; background:var(--surface); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); min-width:3.4rem; font-variant-numeric:tabular-nums; }
          .gd .savebtn3{ margin-top:.55rem; display:inline-flex; background:var(--nav); color:#fff; border-radius:8px; padding:.38rem .8rem; font-size:.78rem; font-weight:600; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="6"] .savebtn3{ transform:scale(.97); filter:brightness(1.2); }
          .gd .tthelp{ font-size:.64rem; color:var(--faint); margin-top:.4rem; }

          /* ---- live-preview card (step 7) ---- */
          .gd .pvcard{ display:none; margin-top:.7rem; border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:23rem; }
          .gd .stage[data-step="7"] .pvcard{ display:block; }
          .gd .pvhd{ display:flex; align-items:center; gap:.4rem; background:var(--panel); padding:.4rem .65rem; font-size:.72rem; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); }
          .gd .pvbody{ padding:.55rem .65rem; }
          .gd .pvdim{ font-size:.72rem; color:var(--soft); margin-bottom:.4rem; }
          .gd .pvdim b{ color:var(--ink); }
          .gd .pvcalc{ font-size:.72rem; color:var(--soft); margin-bottom:.45rem; font-variant-numeric:tabular-nums; }
          .gd .pvcalc b{ color:var(--ink); }
          .gd .pvcalc .ar{ color:var(--faint); }
          .gd .pvres{ background:#d1fae5; color:#065f46; border-radius:8px; padding:.5rem .65rem; font-size:.74rem; }
          .gd .pvres .big{ font-size:1.05rem; font-weight:700; }

          /* ---- clone panel (step 8) ---- */
          .gd .clonep{ display:none; margin-top:.7rem; border:1px dashed var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); max-width:23rem; }
          .gd .stage[data-step="8"] .clonep{ display:block; }
          .gd .clonep .ct{ font-size:.74rem; color:var(--ink); margin-bottom:.4rem; }
          .gd .clonebtn{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--line); border-radius:7px; padding:.32rem .7rem; font-size:.74rem; font-weight:600; color:var(--ink); box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.3)); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / price-table</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Price table &mdash; 25mm Venetian / Band: Plain</div>

                <!-- price-source banner: this grid is a supplier list -->
                <div class="psbanner"><span class="pill">&#9888; Supplier price list</span> These are the supplier&rsquo;s list prices &mdash; our price = list &minus; buying discount + markup. <span class="chg">Change</span></div>

                <!-- Quick start dialog: sample sizes you clear, then paste your own -->
                <div class="qsdlg">
                  <div class="qshd">Quick start &mdash; default grid</div>
                  <div class="qsrow">
                    <label>Widths (mm)</label>
                    <div class="ta2"><span class="def">500, 600, 700, 800, 900, 1000</span><span class="rv">600, 900, 1200, 1500</span></div>
                    <span class="clearchip">Clear</span>
                  </div>
                  <div class="qsrow">
                    <label>Drops (mm)</label>
                    <div class="ta2"><span class="def">600, 900, 1200, 1500</span><span class="rv">900, 1200, 1500, 1800</span></div>
                    <span class="clearchip">Clear</span>
                  </div>
                  <div class="buildbtn">Build grid</div>
                </div>

                <!-- the grid: supplier LIST prices -->
                <div class="ptwrap">
                  <p class="ptcap">Supplier list prices (&pound;) &mdash; Drop &#92; Width</p>
                  <div class="ptgrid">
                    <span class="ptc hd">mm</span><span class="ptc hd">600</span><span class="ptc hd">900</span><span class="ptc hd">1200</span><span class="ptc hd">1500</span>
                    <span class="ptc hd">900</span><span class="ptc"><span class="pn">22.80</span></span><span class="ptc"><span class="pn">28.50</span></span><span class="ptc"><span class="pn">34.20</span></span><span class="ptc"><span class="pn">40.60</span></span>
                    <span class="ptc hd">1200</span><span class="ptc"><span class="pn">26.40</span></span><span class="ptc"><span class="pn">33.20</span></span><span class="ptc qc"><span class="pn">40.00</span></span><span class="ptc"><span class="pn">46.80</span></span>
                    <span class="ptc hd">1500</span><span class="ptc"><span class="pn">30.90</span></span><span class="ptc"><span class="pn">38.40</span></span><span class="ptc"><span class="pn">45.80</span></span><span class="ptc"><span class="pn">53.70</span></span>
                    <span class="ptc hd">1800</span><span class="ptc"><span class="pn">35.20</span></span><span class="ptc"><span class="pn">43.80</span></span><span class="ptc"><span class="pn">52.10</span></span><span class="ptc"><span class="pn">61.40</span></span>
                  </div>
                  <div class="savebtn2">Save grid</div>
                  <div class="okbanner ok-saved"><span>&check;</span> Saved 16 price cells.</div>
                </div>

                <!-- trade terms: buying discount + our markup -->
                <div class="tterms">
                  <div class="tthd">Supplier terms &mdash; applies to every band on 25mm Venetian</div>
                  <div class="ttrow">
                    <div class="ttf"><label>Buying discount %</label><div class="boxs">25</div></div>
                    <div class="ttf"><label>Our markup %</label><div class="boxs">100</div></div>
                  </div>
                  <div class="savebtn3">Save terms</div>
                  <div class="tthelp">Leave blank or 0 to inherit the default (Settings &rarr; Default margins).</div>
                </div>

                <!-- qualify in Live preview: list -> discount -> markup -> sell -->
                <div class="pvcard">
                  <div class="pvhd">&#128065; Live preview</div>
                  <div class="pvbody">
                    <div class="pvdim">25mm Venetian &middot; Plain &middot; <b>1200</b> &times; <b>1200</b> mm</div>
                    <div class="pvcalc">List <b>&pound;40.00</b> <span class="ar">&minus;25%&rarr;</span> cost &pound;30.00 <span class="ar">+100%&rarr;</span> sell <b>&pound;60.00</b></div>
                    <div class="pvres">Sell price <span class="big">&pound;60.00</span><br>&check; the &pound;40 list cell, less your 25% discount, plus your 100% markup.</div>
                  </div>
                </div>

                <!-- clone the next band; terms already cover it -->
                <div class="clonep">
                  <div class="ct">Next band: <b>Special effects</b> &mdash; same shape, and your terms already apply.</div>
                  <span class="clonebtn">&#9635; Clone Plain</span>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Quick start offers sample sizes &mdash; clear them.</b>
                  <b class="c2"><span class="n">2</span> Paste your real widths &amp; drops from Excel.</b>
                  <b class="c3"><span class="n">3</span> Build grid &mdash; a cell per width &times; drop.</b>
                  <b class="c4"><span class="n">4</span> Paste the supplier&rsquo;s list prices in.</b>
                  <b class="c5"><span class="n">5</span> Save grid &mdash; 16 cells saved.</b>
                  <b class="c6"><span class="n">6</span> Set your buying discount &amp; markup.</b>
                  <b class="c7 good"><span class="n">7</span> List &minus; discount + markup = the sell price.</b>
                  <b class="c8"><span class="n">8</span> Clone the next band &mdash; terms already apply.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A price table is a <b>supplier&rsquo;s grid</b> for one band: every <b>width &times; drop</b> and their <b>list price</b> at that size.
             The strip at the top tells you what the grid holds &mdash; <b>&ldquo;Supplier price list&rdquo;</b> (a bought-in list you mark up) or
             <b>&ldquo;Our price list&rdquo;</b> (your own selling prices). Open a band&rsquo;s table from the system&rsquo;s <b>Price tables</b> page;
             it starts empty with a <b>Quick start</b> grid of sample sizes &mdash; a placeholder, so clear it and put your own in.</p>
          <ul class="steps">
            <li><b>Set your real sizes.</b> In Quick start, <b>Clear</b> the sample widths and drops, <b>paste your own</b> from the supplier&rsquo;s
                sheet (drag across in Excel, <kbd>Ctrl</kbd>+<kbd>C</kbd>, click the box, <kbd>Ctrl</kbd>+<kbd>V</kbd>), then <b>Build grid</b>.</li>
            <li><b>Paste the prices.</b> Select the whole block of list prices in Excel, copy, click the first grid cell and paste &mdash; the range
                spreads across the cells. Then <b>Save grid</b>.</li>
            <li><b>Set your discount &amp; markup &mdash; right here.</b> On a <b>supplier</b> list the table shows a <b>Buying discount %</b> and an
                <b>Our markup %</b> box. Fill them and <b>Save terms</b>: your price = <b>list &minus; discount, then + markup</b>. In the demo a
                &pound;40 list cell, less 25%, is &pound;30 cost; plus 100% markup is a <b>&pound;60 sell price</b>. These terms <b>apply to every
                band on that system</b>, so you set them once.</li>
            <li><b>Clone the next band.</b> The next band is usually the <em>same shape</em> at different prices. Open the empty one, click
                <b>Clone</b> beside a filled band, paste the column that differs, and Save &mdash; your discount/markup already cover it.</li>
            <li><b>Prove it.</b> On the product page click <b>&#128065; Live preview</b>, pick the band and a size, and check the <b>sell price</b>
                traces back to the grid cell through your discount and markup.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin &mdash; your choice of wording.</b> The box is labelled
             <b>&ldquo;Our markup %&rdquo;</b> or <b>&ldquo;Our margin %&rdquo;</b> depending on what you picked in <b>Settings &rarr; Default margins</b>
             (<em>Markup</em> = added on top of cost; <em>Margin</em> = the profit slice of the sell price). The customer price is identical either
             way &mdash; it only changes which number you type.</div></div>
          <p><b>Where these numbers come from (the pecking order).</b> A blank or <b>0</b> discount/markup on the table means <b>&ldquo;use the
             default&rdquo;</b> &mdash; the tenant-wide figures in <b>Settings &rarr; Default margins</b>. Type a value here (or on the product
             <b>Edit &rarr; Pricing per system</b> table, which writes the very same numbers) and it <b>overrides the default</b> for that product
             &amp; system. Setting it back to 0 clears the override and drops back to the default.</p>
          <p><b>&ldquo;Our price list&rdquo; products are simpler:</b> there&rsquo;s <b>no discount/markup box</b> &mdash; the grid <em>is</em> the
             sell price, pushed to trade accounts exactly as typed. (Master-admins also see a small <b>list &rarr; cost &rarr; sell &middot;
             margin%</b> line under each cell.)</p>
          <p><b>Had a price rise?</b> Use <b>Adjust all prices by [ ]%</b> &mdash; &ldquo;Apply&rdquo; multiplies every saved cell (a negative number
             reduces), rounded to 2 dp. And <b>Bulk import (multiple bands)</b> reads one Excel file with every band&rsquo;s grid (each block
             starting with a <code>Band X</code> row in column A; pick the worksheet if there are several).</p>
          <div class="oops"><b>Import says &ldquo;No band sections detected&rdquo;?</b> Each grid in the file needs a <code>Band A</code>
             (or B, C&hellip;) row in <b>column A</b> above it. Add those and re-import. (Spelling is forgiving &mdash; <code>Band A</code>,
             <code>Price Band A</code>, even a <code>Bnad A</code> typo &mdash; but the marker must be there. &pound; signs and commas in prices are stripped automatically.)</div>
          <p><b>Other pricing modes build differently:</b> a <b>width-only</b> product is a simple <b>width &rarr; price</b> list; <b>per-slat</b>
             is a <b>drop &rarr; rate</b> list (with <em>Import width prices</em> / <em>Import per-slat rates</em> buttons); and <b>per m&sup2;</b>
             is a single <b>&pound;/m&sup2; rate</b> per system &amp; band. Same idea &mdash; type, paste, or import; the discount &amp; markup work the same way.</p>
          <p>Whichever way, <b>re-saving or re-importing replaces</b> that table&rsquo;s prices, and it&rsquo;s all one screen &mdash; use your
             browser&rsquo;s <b>Back</b> button to step out.</p>',
        'script'  => [
            ['0:00', 'Supplier list; Quick start sizes.',  'This grid is a supplier price list. Open the band\'s table and it starts empty — Quick start offers some default sizes, but those aren\'t yours, so clear them out.', 1],
            ['0:09', 'Real widths and drops pasted.',      'Now paste your real widths and drops straight from the supplier\'s spreadsheet — a whole row or column at a time.', 2],
            ['0:16', 'Build grid.',                        'Click Build grid, and you get a cell for every width and drop — empty, waiting for prices.', 3],
            ['0:23', 'List prices pasted from Excel.',     'These are the supplier\'s list prices. Copy the whole block in Excel and paste it straight into the grid.', 4],
            ['0:30', 'Saved 16 price cells.',              'Click Save grid — sixteen cells saved. That\'s the supplier\'s list in.', 5],
            ['0:37', 'Buying discount + markup entered.',  'Now the money bit. Because it\'s a supplier list, set your buying discount — twenty-five percent off — and your own markup, a hundred percent. Save terms. These apply to every band on this system.', 6],
            ['0:49', 'List, discount, markup = sell.',     'Live preview proves it. The forty-pound list cell, less your twenty-five percent discount, is thirty pounds cost — plus your hundred percent markup makes a sixty-pound sell price.', 7],
            ['1:00', 'Clone the next band.',               'The next band is the same shape, and your terms already cover it — so just clone it and paste the prices that differ.', 8],
        ],
];
