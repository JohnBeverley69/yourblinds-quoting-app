<?php
declare(strict_types=1);

/**
 * Guide: products-import-price-tables
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors /admin/products/price-table.php — the 2-D grid editor, its
 * price-source strip and trade-terms form, the empty-state clone tile, the
 * "Start your grid" dialog, reshaping, the uplift bar and the Advanced
 * XLSX block. Every label, button and message below is taken from that file.
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Building price tables',
        'eyebrow' => 'Products',
        'blurb'   => 'One band, end to end: read the price-source strip, clone or build the grid, paste the prices, save, set your buying discount and markup, and read the proof printed under every cell.',
        'lede'    => 'A price table is <b>one band&rsquo;s grid</b> &mdash; every <b>width &times; drop</b> and the price at that size.
                      The strip across the top tells you <b>which kind of number you are typing</b>: <b>&ldquo;Supplier price list&rdquo;</b>
                      (their list, which we discount and mark up) or <b>&ldquo;Our price list&rdquo;</b> (your own selling price, pushed to
                      trade accounts untouched). Getting that the wrong way round moves every trade account&rsquo;s price without a word,
                      so read it first. One thing to know before you start: a product priced <b>by width only</b>, <b>per slat</b> or
                      <b>per m&sup2;</b> opens a <b>different and much smaller editor</b> &mdash; per m&sup2; is a single box &mdash; so
                      do not panic if no grid appears. To get here: <b>Products</b> &rarr; the product &rarr; <b>Price tables</b> &rarr;
                      the system&rsquo;s list &rarr; <b>Open</b> on the band&rsquo;s row.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          /* ---------- always on screen: subtitle + price-source strip ---------- */
          .gd .ptsub{ font-size:.68rem; color:var(--soft); margin:-.6rem 0 .6rem; }
          .gd .ptsub .lk{ color:var(--accent); font-weight:600; }

          .gd .psbar{ border:1px solid #f59e0b; background:rgba(245,158,11,.08); border-radius:10px;
                      padding:.45rem .65rem; margin-bottom:.6rem; display:flex; gap:.45rem;
                      align-items:baseline; flex-wrap:wrap; }
          .gd .psbar > b{ font-size:.74rem; color:var(--ink); }
          .gd .pshint{ font-size:.66rem; color:var(--faint); }
          .gd .pschg{ margin-left:auto; color:var(--accent); font-size:.66rem; font-weight:600; }
          .gd .stage[data-step="1"] .psbar{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- trade-terms form, inside the strip (step 6) ---------- */
          .gd .ttform{ display:none; flex-basis:100%; margin-top:.5rem; padding-top:.5rem;
                       border-top:1px solid var(--line); align-items:flex-end; gap:.6rem; flex-wrap:wrap; }
          .gd .stage[data-step="6"] .ttform{ display:flex; }
          .gd .ttf label{ display:block; font-size:.58rem; font-weight:700; color:var(--faint);
                          text-transform:uppercase; letter-spacing:.05em; margin-bottom:.18rem; }
          .gd .ttbox{ height:26px; width:5.2rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                      background:var(--surface); display:flex; align-items:center; padding:0 .45rem;
                      font-size:.76rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .ttbox::after{ content:"\\2195"; margin-left:auto; color:var(--faint); font-size:.6rem; }
          .gd .ttgreen{ width:5.2rem; padding:.1rem 0; font-size:.76rem; font-weight:700; color:#065f46; }
          .gd .ttgreen small{ display:block; font-size:.54rem; font-weight:400; color:var(--faint); }
          .gd .tthelp{ font-size:.6rem; color:var(--faint); flex-basis:100%; margin-top:.15rem; }
          .gd .stage[data-step="6"] .b6a .ph, .gd .stage[data-step="6"] .b6b .ph{ opacity:0; }
          .gd .stage[data-step="6"] .b6a .val, .gd .stage[data-step="6"] .b6b .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="6"] .b6a, .gd .stage[data-step="6"] .b6b{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- shared button shapes, drawn like the real ones ---------- */
          .gd .btnpri{ display:inline-flex; align-items:center; justify-content:center; gap:.3rem;
                       background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem;
                       font-size:.76rem; font-weight:700; }
          .gd .btnsec{ display:inline-flex; align-items:center; justify-content:center; gap:.3rem;
                       background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                       border-radius:7px; padding:.3rem .7rem; font-size:.74rem; font-weight:600; }
          .gd .lnk{ color:var(--accent); font-size:.68rem; text-decoration:underline; }

          /* ---------- empty-state tile (step 2) ---------- */
          .gd .qtile{ display:none; border:1px dashed var(--line); border-radius:10px; padding:.7rem .8rem;
                      background:var(--panel); text-align:center; }
          .gd .stage[data-step="2"] .qtile{ display:block; }
          .gd .qth{ font-size:.86rem; font-weight:700; color:var(--ink); margin-bottom:.3rem; }
          .gd .qtp{ font-size:.66rem; color:var(--soft); margin:0 0 .55rem; }
          .gd .clonebtn{ display:block; width:100%; max-width:24rem; margin:0 auto .3rem;
                         background:var(--accent); color:#fff; border-radius:7px; padding:.4rem .7rem;
                         font-size:.76rem; font-weight:700; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .clonebtn .faint{ opacity:.75; font-weight:400; font-size:.68rem; }
          .gd .clonebtn2{ display:block; width:100%; max-width:24rem; margin:0 auto .3rem;
                          background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                          border-radius:7px; padding:.36rem .7rem; font-size:.74rem; font-weight:600; }
          .gd .qtfoot{ margin-top:.6rem; padding-top:.5rem; border-top:1px solid var(--line);
                       font-size:.64rem; color:var(--faint); }

          /* ---------- "Start your grid" dialog (step 3) ---------- */
          .gd .qsdlg{ display:none; border:1px solid var(--line); border-radius:10px; padding:.65rem .75rem;
                      background:var(--surface); box-shadow:var(--gd-shadow); max-width:26rem; }
          .gd .stage[data-step="3"] .qsdlg{ display:block; }
          .gd .qshd{ font-size:.86rem; font-weight:700; color:var(--ink); margin-bottom:.25rem; }
          .gd .qsp{ font-size:.62rem; color:var(--faint); margin:0 0 .5rem; }
          .gd .qslab{ display:flex; justify-content:space-between; align-items:baseline; margin:.4rem 0 .18rem; }
          .gd .qslab span{ font-size:.64rem; font-weight:700; color:var(--soft); }
          .gd .qsdlg .ta{ min-height:1.9rem; font-size:.72rem; font-family:ui-monospace,Menlo,Consolas,monospace; }
          .gd .qsact{ display:flex; gap:.4rem; justify-content:flex-end; margin-top:.55rem; }
          .gd .stage[data-step="3"] .t3 .ph{ opacity:0; }
          .gd .stage[data-step="3"] .t3 .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="3"] .t3{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- the grid (steps 4,5,7,8) ---------- */
          .gd .gwrap{ display:none; }
          .gd .stage[data-step="4"] .gwrap, .gd .stage[data-step="5"] .gwrap,
          .gd .stage[data-step="7"] .gwrap, .gd .stage[data-step="8"] .gwrap{ display:block; }
          .gd .ghelp{ font-size:.6rem; color:var(--faint); margin:0 0 .3rem; }
          .gd .ptgrid{ display:grid; grid-template-columns:4.4rem repeat(4,1fr) 2.7rem; gap:1px;
                       background:var(--line); border:1px solid var(--line); border-radius:8px; padding:1px;
                       max-width:27rem; }
          .gd .ptc{ background:var(--surface); padding:.2rem .15rem; text-align:center; font-size:.68rem;
                    color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .ptc.hd{ background:var(--panel); font-weight:700; color:var(--faint); font-size:.6rem;
                       display:flex; align-items:center; justify-content:center; gap:.15rem; }
          .gd .ptc.corner{ font-size:.55rem; letter-spacing:0; line-height:1.15; text-align:center; }
          .gd .axbtn{ color:var(--accent); font-weight:700; font-size:.66rem; text-decoration:underline; }
          .gd .axrm{ color:var(--faint); font-size:.6rem; border:1px solid var(--line); border-radius:4px;
                     padding:0 .15rem; line-height:1.1; }
          .gd .ptc.adder{ background:var(--panel); }
          .gd .addbtn2{ color:var(--accent); font-size:.58rem; font-weight:700; border:1px dashed var(--line);
                        border-radius:5px; padding:.05rem .2rem; }
          .gd .ptc .pn{ opacity:0; transition:opacity .5s ease; }
          .gd .stage[data-step="4"] .ptc .pn, .gd .stage[data-step="5"] .ptc .pn,
          .gd .stage[data-step="7"] .ptc .pn, .gd .stage[data-step="8"] .ptc .pn{ opacity:1; }
          .gd .cderiv{ display:none; font-size:.5rem; color:var(--faint); line-height:1.25; margin-top:.1rem; }
          .gd .stage[data-step="7"] .cderiv{ display:block; }
          .gd .stage[data-step="7"] .ptc.qc{ background:#d1fae5; box-shadow:inset 0 0 0 2px #34d399; }
          .gd .stage[data-step="7"] .ptc.qc .cderiv{ color:#065f46; font-weight:700; }
          .gd .stage[data-step="8"] .ptc.rn .axbtn{ background:var(--accent); color:#fff; border-radius:4px;
                                                    padding:0 .2rem; text-decoration:none; }

          /* toolbar under the grid */
          .gd .gtool{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin-top:.5rem; }
          .gd .gmuted{ font-size:.58rem; color:var(--faint); }

          /* saved banner + Next band button (step 5) */
          .gd .okbar{ display:none; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:.5rem; }
          .gd .stage[data-step="5"] .okbar{ display:flex; }
          .gd .okbar .nextb{ margin-left:auto; background:var(--accent); color:#fff; border-radius:6px;
                             padding:.2rem .5rem; font-size:.66rem; font-weight:700; }

          /* uplift bar + advanced (step 8) */
          .gd .upbar{ display:none; align-items:center; gap:.35rem; flex-wrap:wrap; margin-bottom:.45rem;
                      padding:.35rem .5rem; background:var(--panel); border:1px solid var(--line); border-radius:8px; }
          .gd .stage[data-step="8"] .upbar{ display:flex; }
          .gd .upbar label{ font-size:.64rem; font-weight:700; color:var(--soft); }
          .gd .numbox2{ width:3.2rem; height:22px; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px;
                        background:var(--surface); display:inline-flex; align-items:center; padding:0 .3rem;
                        font-size:.68rem; color:var(--ink); }
          .gd .advsum{ display:none; margin-top:.5rem; border:1px solid var(--line); border-radius:8px;
                       padding:.3rem .55rem; font-size:.66rem; font-weight:600; color:var(--soft); background:var(--panel); }
          .gd .stage[data-step="8"] .advsum{ display:block; }
          .gd .renamepop{ display:none; margin-top:.4rem; border:1px solid var(--line); border-radius:8px;
                          padding:.35rem .55rem; background:var(--surface); box-shadow:var(--gd-shadow);
                          font-size:.64rem; color:var(--soft); max-width:22rem; }
          .gd .stage[data-step="8"] .renamepop{ display:block; }
          .gd .renamepop b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / price-table</span></div>
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

                <div class="card-t">25mm Venetian / Standard &mdash; Band A</div>
                <p class="ptsub"><span class="lk">&larr; All Standard price tables</span> &middot; <span class="lk">Edit band / name / notes</span></p>

                <!-- price-source strip: on screen at every step -->
                <div class="psbar">
                  <b>Supplier price list</b>
                  <span class="pshint">These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.</span>
                  <span class="pschg">Change</span>

                  <!-- the two numbers that turn their list into your price -->
                  <div class="ttform">
                    <div class="ttf">
                      <label>Buying discount %</label>
                      <div class="box ttbox b6a"><span class="ph">0</span><span class="val">25</span></div>
                    </div>
                    <div class="ttf">
                      <label>Our markup %</label>
                      <div class="box ttbox b6b"><span class="ph">0</span><span class="val">100</span></div>
                    </div>
                    <span class="btnsec">Save terms</span>
                    <span class="tthelp">Applies to <b>every band</b> on Standard. Leave blank or 0 to inherit the default.</span>
                  </div>
                </div>

                <!-- saved banner, carrying the next empty band -->
                <div class="okbar">
                  <span class="okbanner"><span>&check;</span> Saved 16 price cells.</span>
                  <span class="nextb">Next: Standard &mdash; Band B &rarr;</span>
                </div>

                <!-- empty-state tile -->
                <div class="qtile">
                  <div class="qth">This price table is empty</div>
                  <p class="qtp">Other bands on this product are filled in. Pick one to clone &mdash; same widths, drops and prices
                     &mdash; then tweak the cells that differ. We&rsquo;ve sorted them so the closest match (same band code) is first.</p>
                  <span class="clonebtn">Clone <b>Standard &mdash; Band B</b> <span class="faint">(16 cells)</span></span>
                  <span class="clonebtn2">Clone <b>Slimline &mdash; Band A</b> <span class="faint">(16 cells)</span></span>
                  <div class="qtfoot">Or don&rsquo;t clone &mdash; <span class="lnk">start with the same shape but blank prices</span>
                     &mdash; or <span class="lnk">build from scratch</span>.</div>
                </div>

                <!-- Start your grid dialog -->
                <div class="qsdlg">
                  <div class="qshd">Start your grid</div>
                  <p class="qsp">Type or paste the widths and drops your supplier sells in &mdash; a <b>row or a column</b> from Excel,
                     commas, tabs and new lines all work. It reads <b>decimals and metres</b> too, so <b>0.8 becomes 800&nbsp;mm</b>.
                     Defaults are filled in for you &mdash; hit <b>Clear</b> on a box first if you want only your own sizes.</p>
                  <div class="qslab"><span>Widths (mm)</span><span class="lnk">Clear</span></div>
                  <div class="ta t3"><span class="ph">800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000</span><span class="val">800, 1200, 1600, 2000</span></div>
                  <div class="qslab"><span>Drops (mm)</span><span class="lnk">Clear</span></div>
                  <div class="ta t3"><span class="ph">800, 1200, 1600, 2000, 2400, 2800, 3200, 3600, 4000</span><span class="val">800, 1200, 1600, 2000</span></div>
                  <div class="qsact"><span class="btnsec">Cancel</span><span class="btnpri">Build grid</span></div>
                </div>

                <!-- the grid -->
                <div class="gwrap">
                  <div class="upbar">
                    <label>Adjust all prices by</label>
                    <span class="numbox2">5</span><span class="gmuted">%</span>
                    <span class="btnsec">Apply</span>
                    <span class="gmuted">Multiplies every cell (use a negative number to reduce). Rounded to 2 dp.</span>
                  </div>
                  <p class="ghelp">Click any cell, type the new price, hit <b>Tab</b> to move on. Leave a cell blank to skip that
                     width &times; drop combo (no price quoted).</p>
                  <div class="ptgrid">
                    <span class="ptc hd corner">Drop \\ Width (mm)</span>
                    <span class="ptc hd"><span class="axbtn">800</span><span class="axrm">&times;</span></span>
                    <span class="ptc hd"><span class="axbtn">1200</span><span class="axrm">&times;</span></span>
                    <span class="ptc hd rn"><span class="axbtn">1600</span><span class="axrm">&times;</span></span>
                    <span class="ptc hd"><span class="axbtn">2000</span><span class="axrm">&times;</span></span>
                    <span class="ptc hd adder"><span class="addbtn2">+ Width</span></span>

                    <span class="ptc hd"><span class="axbtn">800</span><span class="axrm">&times;</span></span>
                    <span class="ptc"><span class="pn">22.80</span><span class="cderiv">&pound;17.10 &rarr; &pound;34.20 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">28.50</span><span class="cderiv">&pound;21.38 &rarr; &pound;42.75 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">34.20</span><span class="cderiv">&pound;25.65 &rarr; &pound;51.30 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">40.60</span><span class="cderiv">&pound;30.45 &rarr; &pound;60.90 &middot; 50%</span></span>
                    <span class="ptc"></span>

                    <span class="ptc hd"><span class="axbtn">1200</span><span class="axrm">&times;</span></span>
                    <span class="ptc"><span class="pn">26.40</span><span class="cderiv">&pound;19.80 &rarr; &pound;39.60 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">33.20</span><span class="cderiv">&pound;24.90 &rarr; &pound;49.80 &middot; 50%</span></span>
                    <span class="ptc qc"><span class="pn">40.00</span><span class="cderiv">&pound;30.00 &rarr; &pound;60.00 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">46.80</span><span class="cderiv">&pound;35.10 &rarr; &pound;70.20 &middot; 50%</span></span>
                    <span class="ptc"></span>

                    <span class="ptc hd"><span class="axbtn">1600</span><span class="axrm">&times;</span></span>
                    <span class="ptc"><span class="pn">30.90</span><span class="cderiv">&pound;23.18 &rarr; &pound;46.35 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">38.40</span><span class="cderiv">&pound;28.80 &rarr; &pound;57.60 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">45.80</span><span class="cderiv">&pound;34.35 &rarr; &pound;68.70 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">53.70</span><span class="cderiv">&pound;40.28 &rarr; &pound;80.55 &middot; 50%</span></span>
                    <span class="ptc"></span>

                    <span class="ptc hd"><span class="axbtn">2000</span><span class="axrm">&times;</span></span>
                    <span class="ptc"><span class="pn">35.20</span><span class="cderiv">&pound;26.40 &rarr; &pound;52.80 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">43.80</span><span class="cderiv">&pound;32.85 &rarr; &pound;65.70 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">52.10</span><span class="cderiv">&pound;39.08 &rarr; &pound;78.15 &middot; 50%</span></span>
                    <span class="ptc"><span class="pn">61.40</span><span class="cderiv">&pound;46.05 &rarr; &pound;92.10 &middot; 50%</span></span>
                    <span class="ptc"></span>

                    <span class="ptc hd adder"><span class="addbtn2">+ Drop</span></span>
                    <span class="ptc"></span><span class="ptc"></span><span class="ptc"></span><span class="ptc"></span><span class="ptc"></span>
                  </div>

                  <div class="gtool">
                    <span class="btnpri">Save grid</span>
                    <span class="btnsec">Edit sizes</span>
                    <span class="btnsec">Cancel</span>
                    <span class="gmuted">Saving replaces every cell in this table with what&rsquo;s on screen.</span>
                  </div>

                  <div class="renamepop">Rename width 1600mm to (new mm value): <b>1800</b>
                     &mdash; prices in this column keep their values.</div>
                  <div class="advsum">&#9656; Advanced &mdash; XLSX import / export</div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Read the strip &mdash; whose prices are these?</b>
                  <b class="c2"><span class="n">2</span> Another band done? Clone it &mdash; same shape, same prices.</b>
                  <b class="c3"><span class="n">3</span> Start your grid &mdash; Clear, then paste your own sizes.</b>
                  <b class="c4"><span class="n">4</span> Paste the block &mdash; it spreads right and down.</b>
                  <b class="c5 good"><span class="n">5</span> Save grid replaces every cell &mdash; then Next band.</b>
                  <b class="c6"><span class="n">6</span> Buying discount + markup &mdash; every band on this system.</b>
                  <b class="c7 good"><span class="n">7</span> The proof is printed under every cell.</b>
                  <b class="c8"><span class="n">8</span> Uplift it, resize it, or take it to Excel.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Products</b> &rarr; click the product &rarr; the <b>Price tables</b> tile (or the <b>Price tables</b>
             section further down) &rarr; that opens the system&rsquo;s list of bands &rarr; click <b>Open</b> on the band you want.
             The page title tells you exactly where you landed: <b>&ldquo;25mm Venetian / Standard &mdash; Band A&rdquo;</b>, with
             <b>&larr; All Standard price tables</b> and <b>Edit band / name / notes</b> underneath it. (If you came from the product
             setup wizard, that first link reads <b>&larr; Back to setup wizard</b> instead.)</p>

          <ul class="steps">
            <li><b>Read the strip at the top &mdash; before you type anything.</b> It is a plain bold label with a faint line of
                explanation and a <b>Change</b> link on the right. <b>&ldquo;Supplier price list&rdquo;</b> means
                <em>&ldquo;These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.&rdquo;</em>
                <b>&ldquo;Our price list&rdquo;</b> means <em>&ldquo;These are our selling prices. Pushed to trade accounts exactly as
                they are.&rdquo;</em> If it is the wrong one, click <b>Change</b> &mdash; it takes you to the product&rsquo;s pricing
                settings. Nothing else on this page matters until that is right.</li>
            <li><b>Empty table? Clone before you build.</b> A new table shows a tile headed <b>&ldquo;This price table is empty&rdquo;</b>.
                If any other band on this product is already priced, you get a <b>full-width button per band</b> &mdash;
                <b>Clone Standard &mdash; Band B (16 cells)</b> &mdash; closest match first. Click it and you get
                <code>Copied 16 cells &mdash; tweak the prices that differ, then Save.</code> Underneath: <em>Or don&rsquo;t clone &mdash;
                start with the same shape but blank prices &mdash; or build from scratch.</em> If <b>nothing</b> is priced yet you get
                <b>Quick start &mdash; default grid</b> (widths 800&ndash;4000mm, drops 800&ndash;4000mm in 400mm steps) and
                <b>Or build from scratch &mdash; start blank</b> instead.</li>
            <li><b>&ldquo;Start your grid&rdquo;.</b> Both build routes open the same dialog: <b>Widths (mm)</b> and <b>Drops (mm)</b>,
                each with a small underlined <b>Clear</b> beside its label. They arrive pre-filled as a <em>suggestion</em> &mdash; hit
                <b>Clear</b> and paste your own from the supplier&rsquo;s sheet. A row or a column both work; commas, tabs and new lines
                all work; and it reads metres, so <b>0.8 becomes 800&nbsp;mm</b>. Then <b>Build grid</b>. Leave a box empty and it says
                <code>Add at least one width and one drop.</code></li>
            <li><b>Paste the prices.</b> The grid appears with <b>Drop \\ Width (mm)</b> in the corner. Click the top-left cell, copy the
                whole block in Excel, and paste &mdash; it spreads <b>right and down from wherever you clicked</b>, stripping
                <b>&pound;</b>, <b>$</b>, <b>&euro;</b> and commas as it goes. Or click any cell, type, and <b>Tab</b> on to the next.
                Two things that catch people out: <b>anything past the edge of the grid is silently thrown away</b> (paste a 9-wide block
                into a 4-wide grid and five columns per row vanish), so build the grid the right size first; and
                <b>a cell left blank means no price is quoted at that size</b> &mdash; that is the answer to &ldquo;why is my quote
                refusing this width?&rdquo;</li>
            <li><b>Save grid.</b> The muted note beside the button is the whole story: <em>Saving replaces every cell in this table with
                what&rsquo;s on screen.</em> It does not merge. You get <code>Saved 16 price cells.</code> If something would not read as a
                number you get <code>Saved 14 price cells. 2 had problems (see below).</code> and the offenders listed
                (<code>Non-numeric value at 1200&times;1500: abc</code>, <code>Negative price at 1200&times;1500: -5</code>). Best of all,
                the green bar carries a button to the <b>next empty band</b> &mdash; <b>Next: Standard &mdash; Band B &rarr;</b> &mdash;
                so a product&rsquo;s tables feel like a conveyor belt rather than a maze.</li>
            <li><b>Now the money bit &mdash; buying discount and markup.</b> On a <b>supplier</b> table the strip at the top carries two
                number boxes: <b>Buying discount %</b> and <b>Our markup %</b>, with a <b>Save terms</b> button beside them. Twenty-five
                off their list, a hundred percent on top. You get <code>Trade terms saved for this system.</code> The helper line is the
                important part: <em>Applies to <b>every band</b> on Standard. Leave blank or 0 to inherit the default.</em> Set it once
                for the system, not once per band.</li>
            <li><b>Check the sums without leaving the page.</b> On a supplier table there is a small grey line under <b>every</b> cell:
                <code>&pound;30.00 &rarr; &pound;60.00 &middot; 50%</code>. Hover it and it spells the lot out &mdash; <em>list &pound;40.00
                less 25% buying discount = your cost &middot; plus 100% markup = your price &middot; your margin.</em> That is your
                proof, printed in front of you. (<b>&#128065; Live preview</b> on the <em>product</em> page will show the same figure
                inside a real quote if you want a second look &mdash; but it is a different screen, not this one.)</li>
            <li><b>A price rise is one box.</b> Once a table has prices in it, a bar appears above the grid:
                <b>Adjust all prices by [ ] %</b> and <b>Apply</b>, with <em>Multiplies every cell (use a negative number to reduce).
                Rounded to 2 dp.</em> It asks first: <code>Adjust every saved price in this table by the entered %? Save any unsaved cell
                edits first.</code> Take that literally &mdash; it moves the <b>saved</b> cells, so anything you have only just typed is
                skipped. Result: <code>Adjusted 16 prices by +5%.</code> Slips: <code>Enter a percentage, e.g. 5 or -2.5.</code>,
                <code>Enter a non-zero percentage.</code>, <code>Percentage must be greater than -100.</code></li>
          </ul>

          <p><b>Reshaping a grid you already built.</b> Every width and every drop number in the headers <b>is a button</b>.</p>
          <ul class="steps">
            <li><b>Click the number to rename it.</b> You get <code>Rename width 1600mm to (new mm value):</code> &mdash; type the new
                figure and the column is relabelled with its <b>prices kept</b>: <code>Width 1600mm renamed to 1800mm (4 cells
                affected).</code> Type rubbish and it says <code>Need a positive number (mm).</code> Pick a number already on the table and
                it refuses: <code>Can&rsquo;t rename to 1200mm &mdash; that value already exists on this table. Remove it first or pick a
                different number.</code></li>
            <li><b>The &times; beside it drops that column or row.</b> <code>Remove the 1600mm width column? Any prices in it will be lost
                on next save.</code> (and the matching <code>Remove the 1600mm drop row?</code>). Nothing is lost until you
                <b>Save grid</b>.</li>
            <li><b>+ Width / + Drop extends it.</b> Each opens a paste box &mdash; a row or a column, decimals and metres fine, duplicates
                and non-numbers ignored. Paste a list you already have and it tells you: <code>All 4 values already existed in the
                grid.</code></li>
            <li><b>Edit sizes does the lot at once.</b> The <b>Edit sizes</b> button beside <b>Save grid</b> opens
                <b>Bulk-edit widths and drops</b> with both lists pre-filled (each shows <em>currently N</em> and has its own
                <b>Clear</b>). Three rules, stated on the dialog: <b>changed values</b> rename that column/row, <em>prices kept</em>;
                <b>deleted lines</b> take that column/row away, <em>prices too</em>; <b>added lines</b> appear as a new empty
                column/row. Result: <code>Sizes updated: 2 renamed, 1 removed (with their prices), 1 new (placeholder price 0 &mdash;
                edit on the grid).</code> Change nothing and it simply says <code>No changes.</code></li>
          </ul>

          <p><b>Table details</b> &mdash; the collapsed panel behind <b>Edit band / name / notes</b>. <b>Band *</b> (required, 60
             characters), <b>Name (optional)</b> (150, e.g. <em>2026 Slim Line Band A</em>) and <b>Notes (optional)</b> (255, anything to
             remember about this sheet). The note beside <b>Save</b> is reassuring and true: <em>Changing the band code keeps all existing
             prices intact.</em> But it goes further than this table &mdash; renaming a band <b>cascades onto the fabrics carrying that
             code</b>: <code>Saved. Renamed band on 3 fabrics (&ldquo;URBAN&rdquo; &rarr; &ldquo;Urban&rdquo;).</code> Rename it to a band
             that already exists on the system and it stops you: <code>A price table for that band already exists on this system. Pick a
             different band code.</code> (Also <code>Band code is required.</code> and <code>Band code too long (max 60 chars).</code>)</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin &mdash; and what arrives pre-filled.</b> The second box
             reads <b>&ldquo;Our markup %&rdquo;</b> or <b>&ldquo;Our margin %&rdquo;</b> depending on what you chose in
             <b>Settings &rarr; Default margins</b> (<em>Markup</em> = added on top of cost; <em>Margin</em> = the profit slice of the
             selling price). The customer price is identical either way &mdash; it only changes which number you type. And note the
             difference between the two boxes: the <b>markup</b> box usually arrives with a figure already in it, because it falls back to
             your tenant-wide default; the <b>buying discount</b> has no such fallback, so a blank one really is <b>0%</b>. Typing a value
             here overrides the default for this product and system; clearing it back to 0 drops you to the default again. Slips:
             <code>Discount and markup must be numbers.</code>, <code>Discount must be 0 or more and less than 100.</code>,
             <code>markup cannot be negative.</code></div></div>

          <p><b>Trade accounts change this box.</b> Where the factory owns the product and your supplier has set a trade discount against
             your account, the <b>Buying discount %</b> input is <b>replaced</b> by plain green read-only text &mdash; <b>25%</b>, captioned
             <b>from your supplier</b>. You cannot edit it here and there is no hidden box to hunt for: that discount is your
             supplier&rsquo;s to set. <b>Our markup %</b> stays yours to change.</p>

          <p><b>&ldquo;Our price list&rdquo; tables are simpler.</b> There is <b>no terms form at all</b> &mdash; the grid <em>is</em> the
             selling price, pushed to trade accounts exactly as typed. (Master admins get two extras on those: an
             <b>Import cost (this band)</b> button up in the page header, and the same small grey <b>cost &middot; margin</b> line under
             each cell, read from the stored cost.)</p>

          <p><b>Advanced &mdash; XLSX import / export</b> is a collapsed panel at the bottom with three blocks.
             <b>Download as XLSX</b> &rarr; <b>Download template (.xlsx)</b> gives you this table pre-populated, for backup or for a big
             edit in Excel. <b>Upload &mdash; rigid template</b> &rarr; <b>Upload &amp; replace</b> takes that file back:
             <em>widths across row 1, drops down column A, prices in the cells</em>, and it <b>replaces every cell</b>. <b>Upload &mdash;
             supplier file (flexible parser)</b> &rarr; <b>Import &amp; replace</b> takes a raw supplier sheet, finds the block matching
             <b>this table&rsquo;s band</b> and falls back to the first one it finds &mdash; it will tell you which:
             <code>Picked Band A out of 4 bands in the file.</code> and, when it had to guess,
             <code>(No &ldquo;Band D&rdquo; header in the file &mdash; used the first band instead.)</code></p>

          <div class="oops"><b>When an upload will not go in:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>Could not detect any band data in the file. For multi-band files, use the bulk-import page instead. For a
                   single-band file, the parser still expects a &ldquo;Band X&rdquo; header row.</code> &rarr; put a <code>Band A</code>
                   marker in <b>column A</b> above the block and try again.</li>
               <li><code>No width values detected in row 1 (B onwards).</code> &rarr; that is the <b>rigid</b> template talking: widths
                   must sit across the very first row, starting in column B.</li>
               <li><code>No valid price cells found in the uploaded file.</code> / <code>The matched band had no price cells.</code>
                   &rarr; the shape was read but every cell was blank or unreadable.</li>
               <li><code>Please choose a file to upload.</code>, <code>File too large (5 MB max).</code> (rigid) or
                   <code>File too large (10 MB max).</code> (flexible), <code>Could not read the file: &hellip;</code></li>
             </ul></div>

          <p><b>Other pricing shapes open a different editor.</b> A <b>width-only</b> product gives you a simple two-column list &mdash;
             <b>Width</b> / <b>Price (&pound;)</b> &mdash; with an <b>Import width prices</b> button (<code>Saved 9 width prices.</code>).
             A <b>per-slat</b> product gives you the same list keyed on <b>Drop</b> / <b>Price per slat (&pound;)</b> with <b>Import
             per-slat rates</b>; the line price is that rate &times; the number of slats, and a quoted drop rounds up to the next listed
             drop. A <b>per m&sup2;</b> product is a <b>single box</b> &mdash; <b>Rate (&pound; per m&sup2;)</b>, then <b>Save rate</b>
             (<code>Saved &pound;/m&sup2; rate.</code>, or <code>Enter a &pound;/m&sup2; rate (a non-negative number).</code>); the line
             price is that rate &times; the area, with the product&rsquo;s minimum billable area applied. Same idea throughout &mdash;
             type, paste or import &mdash; and the buying discount and markup work exactly the same way.</p>

          <p><b>When you are finished.</b> Take the green bar&rsquo;s <b>Next: &hellip; &rarr;</b> button to the next empty band and keep
             going; if you arrived from the product setup wizard, that button reads <b>&larr; Back to setup wizard</b> instead. And the
             floating <b>Fix next &rarr;</b> pill at the bottom of the screen walks you to the next thing in the catalogue that is not
             ready to quote &mdash; follow it until it stops appearing.</p>',
        'script'  => [
            ['0:00', 'The price-source strip.',        'You are on one band of one system: twenty-five millimetre Venetian, Standard, Band A. Before you type a single number, read the strip at the top. It says Supplier price list, so these are the supplier\'s own figures, and the system takes your buying discount off and adds your markup on. If it said Our price list instead, the numbers would be your selling prices, sent to your trade accounts exactly as typed. Getting that the wrong way round moves every trade price without a word, so if it is wrong, click Change.', 1],
            ['0:23', 'The empty tile — clone first.',  'The table starts empty. If another band on this product is already priced, you do not build anything: you clone it. Click Clone Standard, Band B, and you get the same widths, the same drops and the same prices, with a message saying copied sixteen cells, tweak the prices that differ, then Save. If nothing is priced yet, you get a Quick start button instead, which lays out a standard grid for you.', 2],
            ['0:42', 'Start your grid.',               'Building from scratch opens this dialog, Start your grid. The two boxes come pre-filled as a suggestion, so click Clear on each and paste your own sizes straight from the supplier\'s sheet. A row or a column, commas, tabs or new lines all work, and it reads metres, so nought point eight becomes eight hundred millimetres. Then Build grid. Leave a box empty and it tells you to add at least one width and one drop.', 3],
            ['1:02', 'Paste the prices in.',           'Now the grid. Click the top left cell and paste the whole block from Excel: it spreads right and down from wherever you clicked, and it strips pound signs and commas for you. Two things catch people out. Anything past the edge of the grid is thrown away without a word, so build the grid the right size first. And a cell left blank means no price is quoted at that size at all.', 4],
            ['1:22', 'Save grid, then the next band.', 'Save grid. Saving replaces every cell in this table with what is on screen; it does not merge. You get, saved sixteen price cells. If something would not read as a number, you get the count that did save, and the ones that did not, listed underneath. And the green bar hands you the next empty band as a button, so you can work straight down the product without hunting for it.', 5],
            ['1:41', 'Buying discount and markup.',    'Now the money. Because this is a supplier list, the strip carries two boxes. Buying discount, twenty-five percent off their list. And Our markup, a hundred percent on top. Save terms, and it says trade terms saved for this system. These cover every band on Standard, so you set them once. The markup box may already show a figure, inherited from your settings, and if your settings are set to margin rather than markup, the label reads Our margin instead. And if that discount box is green, read only, and captioned from your supplier, it was set by your supplier and you cannot change it here.', 6],
            ['2:07', 'The proof under every cell.',    'You do not have to go anywhere to check the sums. The page prints them under every cell. The forty pound list cell shows thirty pounds, arrow, sixty pounds, fifty percent. List, less your twenty-five percent discount, is thirty pounds cost. Plus your hundred percent markup is a sixty pound price. And half of that is your margin. Hover it and it spells the whole thing out.', 7],
            ['2:26', 'Uplift, resize, or use Excel.',  'Living with it. A price rise is one box: adjust all prices by five percent, Apply, and every saved cell moves. A minus number brings them down. Note the word saved: anything you have only just typed is skipped, so save first. Sizes change under Edit sizes. Change a number and the column is renamed with its prices kept. Delete a line and the column goes, prices and all. Add a line and you get an empty one. And Advanced is where the spreadsheet lives, if you would rather work in Excel.', 8],
        ],
];
