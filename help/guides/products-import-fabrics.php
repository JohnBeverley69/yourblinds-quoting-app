<?php
declare(strict_types=1);

/**
 * Guide: products-import-fabrics
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers all four tenant routes into a product's fabrics, which all live on
 * /admin/products/options.php: the paste box, Import from Excel, the Fabric
 * Library pull and Copy from another product.
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Adding fabrics — paste, Excel or library',
        'eyebrow' => 'Products',
        // NB: index.php escapes the blurb, so keep it plain text (no entities).
        'blurb'   => 'Three ways to get a product’s fabrics in — paste a list, import a spreadsheet, or pull a range from the library — and how to fix the rows that bounce.',
        'lede'    => 'A <b>fabric</b> is what the customer picks for the blind &mdash; the material, the colour, the slat.
                      There are <b>four ways</b> to get them in, all from one page, and <b>three of them need no spreadsheet at all</b>:
                      <b>paste a list</b>, <b>import from Excel</b>, <b>pull a range from the fabric library</b>, or
                      <b>copy from another product</b>. Every fabric belongs to a <b>band</b> &mdash; and the band is just a name
                      you choose, because that is what the price table is keyed on.',
        'open'    => '/admin/products/options.php',
        'css'     => '
          /* three stacked panels, shown by step range */
          .gd .pn{ display:none; }
          .gd .stage[data-step="0"] .pA, .gd .stage[data-step="1"] .pA,
          .gd .stage[data-step="2"] .pA, .gd .stage[data-step="3"] .pA,
          .gd .stage[data-step="4"] .pA{ display:block; }
          .gd .stage[data-step="5"] .pB, .gd .stage[data-step="6"] .pB,
          .gd .stage[data-step="7"] .pB{ display:block; }
          .gd .stage[data-step="8"] .pC{ display:block; }

          /* real header button row */
          .gd .hdrbtns{ display:flex; flex-wrap:wrap; gap:.28rem; margin:0 0 .55rem; }
          .gd .hbtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.16rem .45rem; font-size:.62rem; font-weight:600; color:var(--soft); background:var(--surface); white-space:nowrap; }
          .gd .hbtn.pri{ background:var(--accent); border-color:var(--accent); color:#fff; }

          /* the blue explainer panel that heads the real page */
          .gd .bluenote{ background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:.4rem .6rem; font-size:.64rem; color:#0c4a6e; line-height:1.5; margin:0 0 .55rem; }
          .gd .bluenote b{ color:#0c4a6e; }

          .gd .summ{ font-weight:600; font-size:.78rem; color:var(--ink); margin:0 0 .2rem; }
          .gd .subtle{ font-size:.64rem; color:var(--faint); margin:0 0 .45rem; line-height:1.45; }

          /* dashed band-chips strip — real <button> chips, click to fill */
          .gd .chips{ display:none; flex-wrap:wrap; align-items:center; gap:.3rem; padding:.3rem .5rem; background:var(--panel); border:1px dashed var(--border-strong,#cbd5e1); border-radius:8px; margin:0 0 .5rem; }
          .gd .stage[data-step="2"] .chips, .gd .stage[data-step="3"] .chips{ display:flex; }
          .gd .chips i{ font-style:normal; font-size:.64rem; font-weight:600; color:var(--faint); margin-right:.15rem; }
          .gd .chip{ font-size:.64rem; font-weight:600; padding:.06rem .5rem; color:var(--ink); background:var(--surface); border:1px solid var(--line); border-radius:999px; }
          .gd .stage[data-step="2"] .chip.pick{ background:#1f3b5b; border-color:#1f3b5b; color:#fff; }

          .gd .ta.big{ min-height:72px; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          .gd .prim{ display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.3rem .7rem; font-size:.73rem; font-weight:700; }
          .gd .sec{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; padding:.3rem .7rem; font-size:.73rem; font-weight:600; color:var(--soft); }
          .gd .btnrow{ display:flex; gap:.4rem; align-items:center; margin-top:.55rem; }
          .gd .stage[data-step="3"] .addall, .gd .stage[data-step="6"] .upbtn{ transform:scale(.96); filter:brightness(1.15); }

          /* scene swap inside panel A: the add form, then the resulting list */
          .gd .addblk{ display:none; }
          .gd .stage[data-step="0"] .addblk, .gd .stage[data-step="1"] .addblk,
          .gd .stage[data-step="2"] .addblk, .gd .stage[data-step="3"] .addblk{ display:block; }
          .gd .listblk{ display:none; }
          .gd .stage[data-step="4"] .listblk{ display:block; }

          /* the fabrics list: filter bar, bulk bar, navy Band pills */
          .gd .srchbar{ display:flex; align-items:center; gap:.4rem; margin:.45rem 0 .4rem; }
          .gd .srchin{ flex:1; max-width:13rem; height:24px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); display:flex; align-items:center; padding:0 .4rem; font-size:.66rem; color:var(--faint); }
          .gd .cnt{ font-size:.64rem; color:var(--faint); }
          .gd .lnk{ font-size:.64rem; color:var(--accent); }
          .gd .bulkbar{ display:flex; flex-wrap:wrap; align-items:center; gap:.3rem; padding:.3rem .45rem; background:var(--panel); border:1px solid var(--line); border-radius:8px; font-size:.62rem; color:var(--faint); }
          .gd .sbtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.1rem .4rem; background:var(--surface); color:var(--soft); font-size:.62rem; font-weight:600; }
          .gd .sin{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.08rem .35rem; background:var(--surface); color:var(--faint); font-size:.62rem; }
          .gd .ftbl{ width:100%; border-collapse:collapse; font-size:.66rem; margin-top:.35rem; }
          .gd .ftbl th{ text-align:left; font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.2rem .25rem; }
          .gd .ftbl td{ padding:.22rem .25rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
          .gd .ftbl td b{ color:var(--ink); font-weight:600; }
          .gd .bandpill{ display:inline-block; padding:.05rem .45rem; font-weight:700; font-size:.6rem; color:#fff; background:#1f3b5b; border-radius:6px; white-space:nowrap; }
          .gd .allsys, .gd .dash{ color:var(--faint); }
          /* the real trailing actions column: an Edit link + a red Delete button */
          .gd .ract{ font-size:.62rem; color:var(--accent); }
          .gd .ract.del{ color:#b91c1c; margin-left:.35rem; }

          /* the second, collapsed <details> under the paste box */
          .gd .collapsed{ margin-top:.65rem; padding-top:.5rem; border-top:1px solid var(--line); font-size:.74rem; font-weight:600; color:var(--ink); }
          .gd .collapsed em{ font-style:normal; color:var(--faint); font-size:.9em; }
          .gd .collapsed i{ font-style:normal; color:var(--faint); margin-right:.2rem; }
          .gd .okflash{ display:none; margin:0 0 .5rem; }
          .gd .stage[data-step="4"] .okflash{ display:flex; }

          /* panel B — the three numbered sections with their grey tip-boxes */
          .gd .secnum{ font-weight:700; font-size:.74rem; color:var(--ink); margin:.55rem 0 .28rem; }
          .gd .tipbox{ background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.35rem .55rem; font-size:.63rem; color:var(--soft); line-height:1.5; margin:0 0 .4rem; }
          .gd .tipbox code{ background:var(--surface); border:1px solid var(--line); border-radius:4px; padding:0 .2rem; font-size:.6rem; }
          .gd .tipbox b{ color:var(--ink); }
          .gd .filebox{ display:inline-flex; align-items:center; gap:.45rem; }
          .gd .choosebtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.16rem .5rem; background:var(--panel); color:var(--ink); font-size:.68rem; white-space:nowrap; }
          .gd .fnbox{ min-width:11rem; height:26px; font-size:.7rem; }

          /* mock file-picker dialog — a file input is chosen, never typed */
          .gd .picker{ display:none; position:absolute; left:50%; top:34%; transform:translateX(-50%); width:15rem; border:1px solid var(--line); border-radius:9px; background:var(--surface); box-shadow:0 14px 34px -12px rgba(20,30,45,.45); padding:.4rem .5rem; z-index:4; }
          .gd .stage[data-step="6"] .picker{ display:block; }
          .gd .picker .pt{ font-weight:700; font-size:.64rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .picker .pr{ padding:.12rem .3rem; border-radius:4px; font-size:.64rem; color:var(--soft); }
          .gd .picker .pr.on{ background:var(--accent-wash); color:var(--accent-ink); font-weight:600; }
          .gd .picker .pb{ text-align:right; margin-top:.3rem; }

          .gd .banners{ display:none; }
          .gd .stage[data-step="7"] .banners{ display:block; }
          .gd .banners .okbanner{ margin-bottom:.4rem; align-items:flex-start; }
          .gd .banners .errbanner ul{ margin:.25rem 0 0; padding-left:1rem; }
          .gd .banners .errbanner li{ padding:.04rem 0; }
          .gd .onward{ display:none; gap:.4rem; margin-top:.5rem; }
          .gd .stage[data-step="7"] .onward{ display:flex; }

          /* panel C — the Fabric Library, stage 2 */
          .gd .libbar{ display:flex; align-items:flex-end; gap:.6rem; flex-wrap:wrap; margin:0 0 .45rem; }
          .gd .libname{ font-weight:700; font-size:.82rem; color:var(--ink); }
          .gd .libcnt{ font-size:.64rem; color:var(--faint); }
          .gd .aplbl{ display:block; font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.15rem; }
          .gd .libtbl{ width:100%; border-collapse:collapse; font-size:.66rem; margin-top:.3rem; }
          .gd .libtbl th{ text-align:left; font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.2rem .25rem; }
          .gd .libtbl td{ padding:.22rem .25rem; border-bottom:1px solid var(--line-2); color:var(--soft); vertical-align:middle; }
          .gd .libtbl td b{ color:var(--ink); font-weight:600; }
          .gd .bandin{ height:1.4rem; width:2.8rem; padding:0; justify-content:center; font-size:.66rem; text-transform:uppercase; }
          .gd .bandin .val{ padding:0; justify-content:center; }

          /* two extra fill steps beyond the shared f1..f5 engine:
             f6 = the chosen file name (steps 6-7), f8 = the edited band (step 8) */
          .gd .stage[data-step="6"] .f6 .ph, .gd .stage[data-step="7"] .f6 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .f6 .val, .gd .stage[data-step="7"] .f6 .val{ opacity:1; }
          .gd .stage[data-step="8"] .f8 .ph{ opacity:0; }
          .gd .stage[data-step="8"] .f8 .val{ opacity:1; }
          .gd .stage[data-step="6"] .f6, .gd .stage[data-step="8"] .f8{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="6"] .f6 .val, .gd .stage[data-step="8"] .f8 .val{ animation: gdRoll .8s ease-out both; }
          @media(max-width:620px){ .gd .hbtn{ font-size:.58rem; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / fabrics</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Panel A: the Fabrics hub (steps 0-4) -->
                <div class="pn pA">
                  <div class="card-t">Roller Blind &mdash; Fabrics</div>
                  <div class="hdrbtns">
                    <span class="hbtn">&larr; Back to setup wizard</span>
                    <span class="hbtn">Add from fabric library</span>
                    <span class="hbtn">Copy from another product</span>
                    <span class="hbtn">Import from Excel</span>
                    <span class="hbtn pri">Next: price tables &rarr;</span>
                  </div>

                  <div class="addblk">
                    <div class="bluenote"><b>Fabrics</b> are what the customer picks for the blind &mdash; the actual
                      material / colour / slat type they want. Each one belongs to a <b>band</b> &mdash; a letter code
                      (A, B, C&hellip;) you assign by supplier price tier.<br>
                      <b>About bands:</b> a band groups fabrics that all cost the same per blind size, so they share
                      <em>one</em> price table instead of needing one each.</div>

                    <p class="summ">Bulk add fabrics &mdash; paste a list</p>
                    <p class="subtle">One name per line <b>or comma-separated</b> &mdash; paste either way. They all go in
                      under the same band (and optionally one system). Duplicates are skipped silently.</p>

                    <div class="chips"><i>Bands:</i>
                      <span class="chip">A</span><span class="chip">B</span><span class="chip pick">URBAN</span></div>

                    <div class="frow">
                      <div class="fld"><label>Band <span class="req">*</span></label>
                        <div class="box f2"><span class="ph">A</span><span class="val">URBAN</span></div></div>
                      <div class="fld"><label>System (optional)</label>
                        <div class="selectbox">All systems on this product</div></div>
                    </div>

                    <div class="fld" style="margin-top:.55rem">
                      <label>Fabric names &mdash; one per line or comma-separated <span class="req">*</span></label>
                      <div class="ta big f3"><span class="ph">Cream<br>Stone<br>Black<br>Polaris White</span><span class="val">Cream, Stone, Black<br>Polaris White</span></div>
                    </div>

                    <div class="btnrow"><span class="prim addall">Add all</span></div>

                    <div class="collapsed"><i>&#9656;</i>Add one at a time
                      <em>&mdash; for setting supplier / colour / code on a single fabric</em></div>
                  </div>

                  <div class="listblk">
                    <div class="okbanner okflash"><span>&check;</span> Added 14 to Band URBAN. Skipped 2 (likely duplicates).</div>
                    <p class="summ">Fabrics (14)</p>
                    <div class="srchbar">
                      <span class="srchin">Filter (e.g. polaris cream)&hellip;</span>
                      <span class="cnt">14 fabrics</span><span class="lnk">Clear</span>
                    </div>
                    <div class="bulkbar">
                      <span class="sbtn">Delete selected</span>&middot;
                      <span class="sin">band</span><span class="sbtn">Set band on selected</span>&middot;
                      <span class="sin">supplier</span><span class="sbtn">Set supplier on selected</span>
                      <span>No rows selected</span>
                      <span>&middot; Tip: tick one, then <b>Shift</b>-click another to select everything between.</span>
                    </div>
                    <table class="ftbl">
                      <tr><th style="width:1rem"><span class="tick">&check;</span></th><th>Band</th><th>System</th><th>Fabric</th><th>Colour</th><th>Supplier</th><th>Code</th><th>Group</th><th></th></tr>
                      <tr><td><span class="tick">&check;</span></td><td><span class="bandpill">Band URBAN</span></td><td class="allsys">All systems</td><td><b>Cream</b></td><td></td><td></td><td></td><td class="dash">&mdash;</td><td><span class="ract">Edit</span><span class="ract del">Delete</span></td></tr>
                      <tr><td><span class="tick">&check;</span></td><td><span class="bandpill">Band URBAN</span></td><td class="allsys">All systems</td><td><b>Stone</b></td><td></td><td></td><td></td><td class="dash">&mdash;</td><td><span class="ract">Edit</span><span class="ract del">Delete</span></td></tr>
                      <tr><td><span class="tick">&check;</span></td><td><span class="bandpill">Band URBAN</span></td><td class="allsys">All systems</td><td><b>Polaris White</b></td><td></td><td></td><td></td><td class="dash">&mdash;</td><td><span class="ract">Edit</span><span class="ract del">Delete</span></td></tr>
                    </table>
                  </div>
                </div>

                <!-- Panel B: Import from Excel (steps 5-7) -->
                <div class="pn pB">
                  <div class="card-t">Import fabrics &mdash; Roller Blind</div>

                  <div class="banners">
                    <div class="okbanner"><span>&check;</span><div>Imported <b>46</b> fabrics (no headers &mdash; used positional
                      A=Band B=Name C=Colour D=Supplier E=Code). Skipped 3 duplicates. Ignored 2 blank rows.</div></div>
                    <div class="errbanner"><span>&#9888;</span><div><b>Some rows had problems:</b>
                      <ul>
                        <li>Row 3: missing band</li>
                        <li>Row 7: missing name</li>
                        <li>Row 9: band code was just &lsquo;Band&rsquo; with nothing after it</li>
                        <li>&hellip; and 4 more</li>
                      </ul></div></div>
                    <div class="onward"><span class="prim">Continue product setup &rarr;</span><span class="sec">View imported fabrics</span></div>
                  </div>

                  <p class="secnum">1. Download the template</p>
                  <div class="tipbox">Columns: <code>Band*</code>, <code>Fabric name*</code>, <code>Colour</code>,
                    <code>Supplier</code>, <code>Code</code>. Asterisks = required. Each row becomes one fabric.</div>
                  <div class="btnrow"><span class="prim">Download blank template (.xlsx)</span></div>

                  <p class="secnum">2. Fill it in</p>
                  <div class="tipbox">Open the file in Excel, paste your data into the columns, save. You can leave
                    Supplier / Colour / Code blank if you don&rsquo;t have them. Bands like <code>A</code>, <code>AA</code>,
                    <code>AAA</code> are normalised to uppercase on import.<br>
                    <b>Headerless files also work:</b> if row 1 has no recognisable header, the importer falls back to
                    positional columns &mdash; A&nbsp;=&nbsp;Band, B&nbsp;=&nbsp;Name, C&nbsp;=&nbsp;Colour,
                    D&nbsp;=&nbsp;Supplier, E&nbsp;=&nbsp;Code.</div>

                  <p class="secnum">3. Upload</p>
                  <div class="fld"><label>Filled template (.xlsx)</label>
                    <div class="filebox"><span class="choosebtn">Choose File</span>
                      <span class="box fnbox f6"><span class="ph">No file chosen</span><span class="val">Decora roller range.xlsx</span></span></div></div>
                  <div class="btnrow"><span class="prim upbtn">Upload &amp; import</span><span class="sec">Cancel</span></div>

                  <div class="picker">
                    <p class="pt">Open</p>
                    <div class="pr">Louvolite 2026 price list.xlsx</div>
                    <div class="pr on">Decora roller range.xlsx</div>
                    <div class="pr">Vertical vanes.csv</div>
                    <div class="pb"><span class="sec" style="font-size:.62rem;padding:.12rem .5rem">Open</span></div>
                  </div>
                </div>

                <!-- Panel C: Fabric Library, stage 2 (step 8) -->
                <div class="pn pC">
                  <div class="card-t">Roller Blind &mdash; add fabrics from library</div>
                  <div class="libbar">
                    <div><div class="libname">Louvolite</div><div class="libcnt">212 fabrics in the library</div></div>
                    <div style="margin-left:auto"><span class="aplbl">Apply to system</span>
                      <span class="selectbox" style="min-width:9rem">All systems</span></div>
                    <span class="prim">Add ticked fabrics</span>
                  </div>
                  <p class="subtle">All ticked by default. The <b>band</b> is pre-filled from the library&rsquo;s suggested
                    band &mdash; edit any to suit your pricing before adding.</p>
                  <table class="libtbl">
                    <tr><th style="width:1rem"><span class="tick on">&check;</span></th><th>Fabric</th><th>Colour</th><th>Code</th><th>Band</th><th>Type</th></tr>
                    <tr><td><span class="tick on">&check;</span></td><td><b>Bella</b></td><td>Chalk</td><td>BL-101</td><td><span class="box bandin"><span class="ph">A</span><span class="val">A</span></span></td><td>Roller</td></tr>
                    <tr><td><span class="tick on">&check;</span></td><td><b>Bella</b></td><td>Stone</td><td>BL-104</td><td><span class="box bandin"><span class="ph">A</span><span class="val">A</span></span></td><td>Roller</td></tr>
                    <tr><td><span class="tick on">&check;</span></td><td><b>Banlight Duo FR</b></td><td>Charcoal</td><td>BD-220</td><td><span class="box bandin f8"><span class="ph">A</span><span class="val">B</span></span></td><td>Roller</td></tr>
                  </table>
                  <div class="okbanner" style="margin-top:.5rem"><span>&check;</span><div>Added 12 fabrics from
                    &ldquo;Louvolite&rdquo;. Skipped 3 already on this product. 2 had no band &mdash; set their band so they
                    price correctly.</div></div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Where the fabrics live &mdash; four ways in.</b>
                  <b class="c2"><span class="n">2</span> The band comes first &mdash; you name it.</b>
                  <b class="c3"><span class="n">3</span> Paste the names in, then Add all.</b>
                  <b class="c4 good"><span class="n">4</span> What comes back &mdash; and how to mend it.</b>
                  <b class="c5"><span class="n">5</span> The spreadsheet route &mdash; get the template.</b>
                  <b class="c6"><span class="n">6</span> Choose the file, upload it.</b>
                  <b class="c7 err"><span class="n">7</span> Good rows in; bad rows named.</b>
                  <b class="c8 good"><span class="n">8</span> The library &mdash; no file at all.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A product&rsquo;s <b>fabrics</b> are what the customer picks for the blind &mdash; in the page&rsquo;s own words,
             &ldquo;the actual material / colour / slat type they want&rdquo;. They are <em>not</em> the same as its
             <b>options</b> (controls, cassettes, bottom bars) &mdash; those are covered in <em>Adding options</em>.
             They all live on one page: <b>Products &rarr; the product &rarr; Fabrics</b>.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>It may not say &ldquo;Fabric&rdquo; on your screen.</b>
             That word is the product&rsquo;s own <b>Option label</b>, a free-text box on <b>Edit product</b> (up to 40
             characters). The screen&rsquo;s own advice there is: <em>Fabric</em> for rollers and romans, <em>Colour</em> for
             metal venetians, <em>Finish</em> for wood venetians &mdash; but it will take any word you like, so plenty of
             products read <em>Slat type</em>. Whatever you put, the whole page follows suit: type <em>Slat type</em> and you
             get <em>Import slat types</em>, <em>Slat type name*</em> in the template, <em>No slat types yet</em> on an empty
             product. Same page, same buttons, different word. Everything below works exactly the same.</div></div>

          <p class="prose"><b>Four ways in.</b> They all write to the same list, so mix and match freely.</p>
          <ul class="steps">
            <li><b>&ldquo;Bulk add fabrics &mdash; paste a list&rdquo;</b> &mdash; already open on the Fabrics page, and the
                fastest route for most jobs. Type a band, paste the names, press <b>Add all</b>. No file, no template.</li>
            <li><b>Import from Excel</b> &mdash; a header button. Best when a supplier has sent you a spreadsheet, or when you
                want Colour, Supplier and Code filled in as well as the name.</li>
            <li><b>Add from fabric library</b> &mdash; a header button. If the range is already in the library, pick the
                manufacturer, untick what you don&rsquo;t sell, and add. The manufacturer&rsquo;s name is written into
                <b>Supplier</b> for you.</li>
            <li><b>Copy from another product</b> &mdash; a header button, and the fastest of all for a sibling product. Choose
                the source product, tick which <b>bands</b> to bring across (all ticked by default), and go. Anything already
                there (same band + name + colour) is skipped, so <b>it is safe to run twice</b>, and a fabric tied to a system
                is re-pointed to the target&rsquo;s same-<em>named</em> system. This is how a &ldquo;vertical blind fabric
                only&rdquo; line inherits the vertical&rsquo;s whole range in one click.</li>
          </ul>

          <p class="prose"><b>And a fifth, for one row at a time.</b> Directly under the paste box sits a second, <b>folded-up</b>
             section headed <b>Add one at a time &mdash; for setting supplier / colour / code on a single fabric</b>. Click the
             heading to open it. It tells you plainly why it is folded away: <em>&ldquo;Bulk add above is quicker for most cases.
             Use this only when you need to set a supplier, colour, or code on an individual fabric.&rdquo;</em> It has the same
             band chips and the same autocomplete, and six controls in all: <b>Band&nbsp;*</b>, <b>Fabric name&nbsp;*</b>
             (placeholder <em>e.g. Cream Slats</em>), <b>Colour</b>, <b>Supplier</b>, <b>Code</b>, an <b>Active</b> tick that is
             on to start with, and an <b>Add fabric</b> button. Leave Active ticked unless you are parking a fabric you don&rsquo;t
             sell at the moment &mdash; an unticked one still sits in the list, with a grey <b>Inactive</b> pill beside its name.
             The refusals are short and say what is wrong: <code>Band code is required (e.g. A, B, C).</code>,
             <code>Band code is too long (max 60 chars).</code>, <code>Fabric name is required.</code> and
             <code>Fabric name is too long (max 150 chars).</code> If the Colour box is missing here &mdash; and from the Colour
             column in the list below &mdash; that is the tick on <b>Edit product</b> called
             <b>&ldquo;Show separate &lsquo;Colour&rsquo; column on fabric forms.&rdquo;</b>, which you untick on products where
             the fabric name <em>is</em> the colour.</p>

          <p class="prose"><b>The band is yours to name.</b> A <b>band</b> groups fabrics that all cost the same per blind size,
             so they share <em>one</em> price table instead of needing one each. The box is <b>free text up to 60 characters</b>
             &mdash; <em>A</em>, <em>B</em> and <em>AAA</em> are fine, but so are <em>Plain</em>, <em>Blackout</em> and
             <em>Special effects</em>. Name it for what it is.</p>
          <ul class="steps">
            <li><b>The chips strip</b> above the box lists every band already used on this product &mdash; <b>click one to fill
                the box</b>. It is drawn from your <b>fabrics and your price tables together</b>, so a band sitting in one list
                but not the other is visible at a glance.</li>
            <li><b>It remembers the last band you used</b>, so adding five bands in a row is five short edits, not five retypes.
                There is a matching autocomplete list on the Band boxes too.</li>
            <li><b>Type <code>Band AA</code> and it quietly stores <code>AA</code></b> &mdash; a leading &ldquo;Band&nbsp;&rdquo;
                is thrown away, so you never end up with &ldquo;Band Band AA&rdquo; in the pill. That tidy-up happens on the
                four places you <em>type</em> a band: the <b>paste a list</b> box, the <b>Add one at a time</b> form, the
                <b>Excel import</b>, and <b>Set band on selected</b>. The one place it does <em>not</em> happen is the little
                per-row <b>Band</b> box on the <b>fabric library</b> screen &mdash; type &ldquo;Band B&rdquo; there and you get
                a band literally called <em>BAND B</em>, which will not match a price table called <em>B</em>. Put just the
                code in that box.</li>
            <li><b>Case:</b> bands you <b>type</b> keep the case you typed; bands that arrive by <b>Excel import or library
                pull</b> come in as capitals. Either way they match, because band matching ignores case &mdash; but don&rsquo;t
                be surprised to see <em>Urban</em> and <em>URBAN</em> side by side.</li>
            <li><b>Ordering:</b> all-A bands come first and the <b>longest run of A&rsquo;s counts as the most premium</b>
                (AAAA, then AAA, then AA, then A), and everything else follows alphabetically.</li>
          </ul>

          <p class="prose"><b>One system, or all of them.</b> Next to the Band box is <b>System (optional)</b>, and it defaults to
             <b>All systems on this product</b>. Leave it alone and the fabrics show on every system; pick one and they only show
             on that one. That is how a roller-only colour stays off your verticals. The list below then carries a <b>System</b>
             column reading either the system&rsquo;s name or a grey <em>All systems</em>. The library pull has the same control,
             labelled <b>APPLY TO SYSTEM</b>.</p>
          <p class="prose">The same paste box appears in the <b>setup wizard</b>, where it can do one extra trick: put a
             <code>[System Name]</code> line above each group of names and one paste feeds several systems at once. Get that
             wrong and it says: <code>Nothing added. Check the paste &mdash; colours need a [System Name] header above them, or
             pick a system in the dropdown.</code></p>

          <p class="prose"><b>The spreadsheet, properly.</b> <b>Import from Excel</b> is three numbered steps on one page.</p>
          <ul class="steps">
            <li><b>1. Download the template.</b> The blue <b>Download blank template (.xlsx)</b> button gives you a file named
                after your product with five columns: <b>Band*</b>, <b>Fabric name*</b>, Colour, Supplier, Code. Only the two
                starred ones are required. <b>Each row becomes one fabric.</b> Row 3 of the file reminds you:
                <code>* = required. Bands like A, B, C, AA, AAA &mdash; case is normalised. Duplicate (band + name + colour)
                rows are skipped on import.</code></li>
            <li><b>2. Fill it in.</b> Paste your data in and save. Leave Supplier, Colour and Code blank if you haven&rsquo;t
                got them.</li>
            <li><b>3. Upload.</b> It accepts <b>.xlsx, .xlsm, .xls, .csv and .ods</b>, up to <b>5 MB</b>. Then
                <b>Upload &amp; import</b>, or <b>Cancel</b> to go back.</li>
            <li><b>No headings? Still fine.</b> If row 1 has no recognisable heading the importer reads by position instead
                &mdash; <b>A = Band, B = Name, C = Colour, D = Supplier, E = Code</b> &mdash; and treats row 1 as data. Plenty of
                supplier files arrive exactly like that, and they import as they are.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>It only reads one sheet &mdash; the active one.</b> That is
             whichever tab was showing when the file was last saved. A supplier workbook with twelve tabs will import
             <b>one tab</b> and look like it worked perfectly. If your file has several tabs, save each one as its own file and
             import them one at a time &mdash; or open each tab and use the <b>paste a list</b> box instead.</div></div>

          <div class="oops"><b>&ldquo;Some rows had problems:&rdquo;</b> the good rows <b>still go in</b> &mdash; nothing is
             rolled back &mdash; and you are told exactly which ones did not, by row number:
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>Row 3: missing band</code></li>
               <li><code>Row 7: missing name</code></li>
               <li><code>Row 9: band code was just &lsquo;Band&rsquo; with nothing after it</code></li>
               <li>and, when there are lots, <code>&hellip; and 4 more</code> under the first twenty-five.</li>
             </ul>
             Read the <b>green</b> line as well as the red one: it always says which way it read your file &mdash;
             <code>(header row detected)</code> or <code>(no headers &mdash; used positional A=Band B=Name C=Colour D=Supplier
             E=Code)</code> &mdash; plus <code>Skipped 3 duplicates.</code> and <code>Ignored 2 blank rows.</code> Fix the named
             rows, upload the file again, and the rows that already went in skip themselves. Other things it may say:
             <code>Please choose a file to upload.</code>, <code>File too large (5 MB max).</code> and
             <code>Could not read the file:</code> followed by the reason.</div>

          <p class="prose"><b>Pulling a range from the library.</b> <b>Add from fabric library</b> lists the manufacturers with a
             count of how many fabrics each has and a <b>Choose &rarr;</b> button. Pick one and you get the whole range,
             <b>all ticked by default</b>, with <b>Fabric, Colour, Code, Band and Type</b>. The <b>Band</b> is a little box on
             every row, pre-filled from the library&rsquo;s suggested band and <b>fully editable</b> &mdash; change any of them
             right there before you add, because the band is what decides the price. Untick anything you don&rsquo;t sell, set
             <b>APPLY TO SYSTEM</b> if needed, and press <b>Add ticked fabrics</b>. You get back, for example:
             <code>Added 12 fabrics from &ldquo;Louvolite&rdquo;. Skipped 3 already on this product. 2 had no band &mdash; set
             their band so they price correctly.</code> If the page says <code>The Fabric Library isn&rsquo;t set up yet.</code>
             or <code>No manufacturers in the library yet.</code>, there is nothing to pull from &mdash; use one of the other
             three routes.</p>

          <p class="prose"><b>Mending an import without re-doing it.</b> Just above the list, under the
             <b>Fabrics&nbsp;(14)</b> heading, there is a <b>filter box</b> &mdash; <em>Filter (e.g. polaris cream)&hellip;</em>
             &mdash; with a live count beside it and a <b>Clear</b> button that only appears once you have typed something. Every
             word you type has to appear in the row, so &ldquo;polaris cream&rdquo; narrows straight to it. It matches name,
             colour, band, code, system and group; it deliberately does <b>not</b> match supplier. Tick the rows you want (tick
             one, then <b>Shift</b>-click another to take everything between) and use <b>Set band on selected</b>, <b>Set
             supplier on selected</b> or <b>Delete selected</b>. A whole range imported under the wrong band is a
             thirty-second fix, not a re-import.</p>

          <p class="prose"><b>The list&rsquo;s own columns.</b> Left to right: a <b>tick box</b>, <b>Band</b> (the navy pill),
             <b>System</b>, the fabric name under whatever your option label is, <b>Colour</b>, <b>Supplier</b>, <b>Code</b>,
             <b>Group</b>, and a last, unheaded column holding <b>Edit</b> and a red <b>Delete</b> for that single row. Two of
             those columns come and go: <b>System</b> only appears once the product has systems, and <b>Group</b> only on
             databases that have the fabric-group column. <b>Group</b> is filled for you by the <b>fabric library</b> pull
             &mdash; it is the library&rsquo;s own grouping of the range &mdash; and shows a grey <b>&mdash;</b> on fabrics you
             pasted or imported yourself. Use <b>Edit</b> for a one-row correction (a misspelt name, a missing code) and the
             bulk bar above for anything that touches more than one.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The band must match a price table.</b> A fabric whose band
             has no matching price table simply <b>shows no price</b> in the quote builder. The chips strip is drawn from both
             lists at once, so if you see a band there that you don&rsquo;t recognise, it is a price table with no fabrics or a
             fabric with no price table &mdash; and that is the one to fix.</div></div>

          <p class="prose"><b>What happens next.</b> On the Products list each product carries a status pill. Before it is
             finished it reads <b>Needs system + fabric + price table</b>, and it drops each part as you satisfy it until it
             turns into a green <b>&#10003; Ready</b>. <b>Systems come first</b> in that order, because price tables belong to a
             system. Clicking the pill jumps you straight to whichever piece is missing. The catalogue health check on the
             product&rsquo;s own page words it plainly: <code>No fabrics added. Salespeople won&rsquo;t be able to pick a fabric
             for this product.</code> One exception &mdash; a product ticked <b>&ldquo;No fabric to choose (headrail only,
             track, spares)&rdquo;</b> never needs fabrics at all and is never asked for them. After an import, the green
             <b>Continue product setup &rarr;</b> button takes you on to <b>price tables</b>; <b>View imported fabrics</b> takes
             you back to the list.</p>

          <p class="prose"><b>One last thing, so you don&rsquo;t go hunting.</b> A single supplier workbook with <em>one sheet
             per product</em> &mdash; many products in one go &mdash; is handled centrally, under <b>Platform &rarr; Catalogue &rarr; Fabric
             Library</b> as <b>Bulk import fabrics across products &rarr;</b>. It used to sit on the Products page and it no
             longer does. If you can&rsquo;t find it, it isn&rsquo;t missing &mdash; it just isn&rsquo;t yours; ask whoever
             looks after the library. Everything on this page does the same job one product at a time.</p>',
        'script'  => [
            ['0:00', 'Fabrics page; five header buttons.',
             'This is where a product\'s fabrics live. A fabric is simply what the customer picks — the material, the colour, the slat. There are four ways to get them in, and they all land in the same list, so you never have to use a spreadsheet if you would rather not. One thing to know: on some products this page says Slat type, or Colour, instead of Fabric. That is because you choose the word yourself on Edit product. Same page either way.', 1],

            ['0:22', 'Band chips; Band field fills with A.',
             'The band comes first. Every fabric belongs to a band, and the band is just a name you choose — up to sixty characters, so Plain, Blackout or Special effects is every bit as valid as A. A band is a group of fabrics that all cost the same, so they can share one price table instead of needing one each. Click a chip to reuse a band you have already got, and the box remembers the last band you used. And if you type Band A A, it quietly stores just A A.', 2],

            ['0:45', 'System select; paste names; Add all.',
             'Now paste the names in. One per line, or separated by commas, or both — it copes with either. Leave System on All systems and the fabrics go on every system for this product. Pick one, and they only show on that one. That is how a roller-only colour stays off your verticals. Then press Add all. And if you only ever want one fabric, with a supplier and a code on it, there is a folded-up section just underneath — Add one at a time. Click its heading to open it.', 3],

            ['1:02', 'Green flash; list with Band URBAN pills.',
             'And here is what comes back. Added fourteen to Band URBAN, skipped two likely duplicates — duplicates are skipped quietly, never shown as a failure. Underneath is your list, with each fabric in its navy Band URBAN pill. If something went in wrong, don\'t re-do it. Use the filter box to find the rows, tick one and shift-click another to take everything between, then Set band on selected, Set supplier on selected, or Delete selected. A wrong band is a thirty-second fix.', 4],

            ['1:26', 'Import page; three numbered sections.',
             'Now the spreadsheet route. Import from Excel is three numbered steps. First, download the template. The five columns are Band star, Fabric name star, Colour, Supplier and Code — only the two starred ones are required, and each row becomes one fabric. The file arrives named after your product with the headings already in place.', 5],

            ['1:44', 'Choose File; Decora roller range.xlsx; upload.',
             'Then choose your file and upload it. It takes X L S X, X L S, X L S M, C S V or O D S, up to five megabytes. Two things worth knowing before you press it. First, it reads only the sheet that was showing when the file was saved — so a supplier workbook with twelve tabs will import one tab and look like it worked. Split it, or paste each tab in instead. Second, a supplier file with no heading row still works perfectly: it assumes A is Band, B is Name, C is Colour, D is Supplier and E is Code.', 6],

            ['2:10', 'Green banner + Some rows had problems.',
             'When rows bounce, you get both messages. The good rows always go in — nothing is rolled back — and you are told exactly which ones did not, by row number. Row three, missing band. Row seven, missing name. Row nine, band code was just the word Band with nothing after it. Read the green line as well as the red one: it tells you whether your headings were understood, or whether it guessed by column. Fix those rows, upload again, and the ones already in skip themselves. Then Continue product setup takes you on to price tables.', 7],

            ['2:36', 'Library table; band edited A to B; added.',
             'And the fourth way needs no file at all. If the range is already in the library, pick the maker, untick anything you do not sell, and the supplier name fills itself in for you. Every row carries a suggested band — change any of them right there in the little box before you add, because the band is what decides the price. Put just the code in that box, not the word Band in front of it: unlike the paste box, this one takes exactly what you type, in capitals. Press Add ticked fabrics, and if some came in with no band the message tells you how many, and they will not price until you give them one.', 8],
        ],
];
