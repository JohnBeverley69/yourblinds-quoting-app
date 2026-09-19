<?php
declare(strict_types=1);

/**
 * Guide: products-options
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors three real screens: admin/products/extras.php (the options list +
 * Add option), admin/products/extra.php (the live choices grid + Sub-options)
 * and admin/products/extra-choice-edit.php (the full choice edit page). Every
 * label, default, tooltip and error string in here is quoted from those files —
 * if it is not on the real screen, it is not in the guide.
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Adding options',
        'eyebrow' => 'Products',
        'blurb'   => 'The whole options screen: reading the list, adding an option, the live choices grid, the five ways to price a choice, scoping, sub-options and what the salesperson finally sees.',
        'lede'    => 'Options are the <b>extras</b> your salesperson picks for each blind &mdash; <em>Control type</em>, <em>Bottom weight</em>,
                      <em>Bracket colour</em>. The option itself carries <b>no price</b>; the price lives on its <b>choices</b>. This walks the
                      whole screen: <b>reading the options list</b>, <b>adding</b> an option, the <b>live choices grid</b> (no Save button),
                      the <b>five ways to price</b> a choice, <b>scoping</b> it to systems / bands / fabrics, <b>sub-options</b> that wait their
                      turn &mdash; and what it all looks like in the quote builder.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          /* a statically-filled field (looks like .fld .box, but always shows its value) */
          .gd .boxv{ height:26px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .45rem; font-size:.72rem; color:var(--ink); overflow:hidden; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB{ display:block; }
          .gd .stage[data-step="3"] .scC, .gd .stage[data-step="4"] .scC{ display:block; }
          .gd .stage[data-step="5"] .scD{ display:block; }
          .gd .stage[data-step="6"] .scE{ display:block; }
          .gd .stage[data-step="7"] .scF{ display:block; }
          .gd .stage[data-step="8"] .scG{ display:block; }

          /* the shared f1..f5 fills only reach step 5, so later scenes declare
             their own — same contract: blank placeholder, value rolls in on the
             step the narration reaches, and stays. Nothing is pre-filled. */
          .gd .f6 .ph, .gd .f7 .ph, .gd .f8 .ph{ opacity:1; }
          .gd .stage[data-step="6"] .f6 .ph, .gd .stage[data-step="7"] .f7 .ph, .gd .stage[data-step="8"] .f8 .ph{ opacity:0; }
          .gd .f6 .val, .gd .f7 .val, .gd .f8 .val{ opacity:0; }
          .gd .stage[data-step="6"] .f6 .val, .gd .stage[data-step="7"] .f7 .val, .gd .stage[data-step="8"] .f8 .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="6"] .f6, .gd .stage[data-step="7"] .f7, .gd .stage[data-step="8"] .f8{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          /* late reveals that are not a typed value (a banner, a ticked box) */
          .gd .lr{ opacity:0; transition:opacity .35s; }
          .gd .stage[data-step="4"] .lr4, .gd .stage[data-step="5"] .lr5,
          .gd .stage[data-step="6"] .lr6, .gd .stage[data-step="7"] .lr7{ opacity:1; }
          /* a value that is swapped for another on step 4 (the Bands summary) */
          .gd .b4b{ display:none; }
          .gd .stage[data-step="4"] .b4a{ display:none; }
          .gd .stage[data-step="4"] .b4b{ display:inline; }
          .gd .stage[data-step="4"] .sind.ok{ display:none; }
          /* a tick-box the narration is watched ticking on (step 7) */
          .gd .t7{ transition:background .3s, color .3s; }
          .gd .stage[data-step="7"] .t7{ background:var(--accent); border-color:var(--accent); color:#fff; }
          /* the quote-builder select fills like a .box does */
          .gd .selectbox.f8{ position:relative; }
          .gd .selectbox.f8 .ph{ color:var(--faint); }
          .gd .selectbox.f8 .val{ position:absolute; inset:0; display:flex; align-items:center; padding:.22rem .4rem; color:var(--ink); }

          /* ---- page chrome shared by the scenes ---- */
          .gd .crumb{ font-size:.62rem; color:var(--faint); margin-bottom:.15rem; }
          .gd .subl{ font-size:.66rem; color:var(--accent); margin:.1rem 0 .5rem; }
          .gd .hbtns{ display:flex; gap:.3rem; flex-wrap:wrap; margin-bottom:.5rem; }
          .gd .hbtn{ border:1px solid var(--line); border-radius:7px; padding:.18rem .45rem; font-size:.6rem; color:var(--soft); background:var(--surface); }
          .gd .hbtn.pri{ background:var(--accent); border-color:var(--accent); color:#fff; font-weight:700; }
          .gd .bluep{ background:var(--accent-wash); border:1px solid color-mix(in srgb,var(--accent) 28%,transparent); border-radius:8px; padding:.4rem .55rem; font-size:.64rem; color:var(--ink); line-height:1.45; margin-bottom:.55rem; }
          .gd .exlabel{ font-size:.64rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:0 0 .35rem; }
          .gd .exhelp{ font-size:.63rem; color:var(--faint); margin:.3rem 0 0; line-height:1.45; }
          .gd .chkline{ display:inline-flex; align-items:flex-start; gap:.35rem; font-size:.68rem; color:var(--ink); }
          .gd .chkline small{ color:var(--faint); font-size:.6rem; display:block; }
          .gd .addbtn{ margin-top:.55rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.32rem .7rem; font-size:.72rem; font-weight:700; }
          .gd .ghostbtn{ display:inline-flex; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:7px; padding:.25rem .6rem; font-size:.66rem; font-weight:600; }

          /* ---- scene A: the options list ---- */
          .gd .olist{ display:grid; grid-template-columns:.9rem 1fr 3rem 4.2rem 6.4rem; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .ol{ background:var(--surface); padding:.28rem .35rem; font-size:.66rem; color:var(--ink); }
          .gd .ol.hd{ background:var(--panel); color:var(--faint); font-weight:700; font-size:.58rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .ol.c{ text-align:center; }
          .gd .ol.act{ color:var(--accent); font-size:.58rem; }
          .gd .ol.act .del{ color:var(--err); }
          .gd .rpill{ display:inline-block; background:#1f3b5b; color:#fff; border-radius:999px; padding:.01rem .35rem; font-size:.52rem; font-weight:700; letter-spacing:.05em; margin-left:.25rem; }
          .gd .opill{ display:inline-block; background:var(--panel); color:var(--faint); border-radius:999px; padding:.01rem .35rem; font-size:.52rem; font-weight:700; letter-spacing:.05em; margin-left:.25rem; }
          .gd .pcond{ display:block; color:var(--faint); font-size:.57rem; margin-top:.1rem; }
          .gd .ind{ padding-left:.75rem; position:relative; }
          .gd .ind::before{ content:"\21B3"; position:absolute; left:.05rem; color:var(--faint); }
          .gd .numi{ font-size:.58rem; color:var(--soft); white-space:nowrap; }

          /* ---- scene C/F: the choices grid ---- */
          .gd .sind{ display:inline-flex; align-items:center; gap:.25rem; background:#d1fae5; color:#065f46; border-radius:999px; padding:.05rem .5rem; font-size:.6rem; font-weight:700; margin-left:.4rem; }
          .gd .sind.bad{ background:#fee2e2; color:#991b1b; }
          .gd .cgrid{ display:grid; width:100%; grid-template-columns:.7rem minmax(5.4rem,1.3fr) 3.2rem 2.4rem 2.3rem 1.9rem 1.9rem 2.1rem 2rem 2.4rem 4.2rem; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .cc{ background:var(--surface); padding:.22rem .22rem; font-size:.58rem; color:var(--ink); text-align:center; display:flex; align-items:center; justify-content:center; }
          .gd .cc.l{ text-align:left; justify-content:flex-start; flex-direction:column; align-items:stretch; }
          .gd .cc.hd{ background:var(--panel); color:var(--faint); font-weight:700; font-size:.53rem; text-transform:uppercase; letter-spacing:.02em; flex-direction:column; gap:.05rem; }
          .gd .cc.hd .sa{ color:var(--accent); font-size:.48rem; text-transform:none; letter-spacing:0; font-weight:600; }
          .gd .cc .box{ height:20px; font-size:.58rem; padding:0 .25rem; border-radius:5px; }
          .gd .cc .box .val{ padding:0 .25rem; }
          .gd .msel{ display:inline-flex; align-items:center; justify-content:space-between; gap:.2rem; width:100%; border:1px solid var(--line); border-radius:5px; padding:.1rem .2rem; font-size:.54rem; color:var(--ink); background:var(--surface); }
          .gd .msel::after{ content:"\25BE"; color:var(--faint); font-size:.5rem; }
          .gd .wtb{ display:inline-block; margin-top:.15rem; padding:.02rem .25rem; font-size:.5rem; font-weight:700; color:var(--soft); background:var(--panel); border:1px solid var(--line); border-radius:4px; white-space:nowrap; }
          .gd .cc.act{ color:var(--accent); font-size:.5rem; flex-direction:column; gap:.05rem; align-items:flex-start; }
          .gd .cc.act .del{ color:var(--err); }
          .gd .cc.newr{ color:var(--faint); font-style:italic; font-size:.55rem; }
          .gd .gtip{ font-size:.58rem; color:var(--faint); margin:.35rem 0 0; display:flex; gap:.5rem; align-items:center; justify-content:space-between; flex-wrap:wrap; }

          /* ---- scene D: the choice edit page ---- */
          .gd .fs{ border:1px solid var(--line); border-radius:9px; padding:.45rem .55rem; margin:.4rem 0; background:var(--surface); }
          .gd .fsl{ font-size:.55rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; }
          .gd .frow3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.4rem; }
          .gd .fld label{ font-size:.58rem; }
          .gd .scrollbox{ border:1px solid var(--line); border-radius:7px; padding:.3rem .4rem; background:var(--surface); max-height:4.6rem; overflow:hidden; }
          .gd .bandhd{ font-size:.52rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin:.2rem 0 .1rem; }
          .gd .fchk{ display:inline-flex; align-items:center; gap:.3rem; font-size:.6rem; color:var(--ink); margin:0 .7rem .15rem 0; }
          .gd .filebtn{ display:inline-flex; align-items:center; gap:.35rem; font-size:.6rem; color:var(--ink); }
          .gd .filebtn .cf{ border:1px solid var(--border-strong,#c7ccd4); background:var(--panel); border-radius:5px; padding:.12rem .4rem; font-size:.58rem; }
          .gd .picker{ border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.35rem .45rem; font-size:.58rem; color:var(--ink); max-width:12rem; margin-top:.3rem; }
          .gd .picker .ph2{ font-size:.52rem; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.2rem; }
          .gd .picker .fileline{ display:flex; align-items:center; gap:.3rem; padding:.1rem .2rem; background:var(--accent-wash); border-radius:4px; }
          .gd .picker .pbtns{ display:flex; gap:.3rem; justify-content:flex-end; margin-top:.25rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }

          /* ---- scene F: sub-option cards ---- */
          .gd .subcard{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--surface); }
          .gd .subhead{ display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; margin-bottom:.3rem; }
          .gd .subname{ font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .gatep{ display:inline-block; background:var(--panel); color:#1f3b5b; border-radius:999px; padding:.02rem .4rem; font-size:.55rem; font-weight:700; }
          .gd .subacts{ margin-left:auto; font-size:.55rem; color:var(--soft); }
          .gd .subacts .del{ color:var(--err); }
          .gd .discl{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; margin-top:.4rem; background:var(--panel); }
          .gd .disclhd{ font-size:.66rem; font-weight:700; color:var(--accent); margin-bottom:.35rem; }

          /* ---- scene G: quote-builder preview ---- */
          .gd .pvq{ border:1px solid var(--line); border-radius:10px; overflow:hidden; }
          .gd .pvqhd{ background:var(--panel); padding:.3rem .5rem; font-size:.6rem; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); }
          .gd .pvqbody{ padding:.45rem .5rem; display:flex; flex-direction:column; gap:.4rem; }
          .gd .pvqrow label{ display:block; font-size:.6rem; font-weight:600; color:var(--soft); margin-bottom:.14rem; }
          .gd .pvqrow .req{ color:#b91c1c; }
          .gd .pvqrow .selectbox{ min-width:0; width:100%; font-size:.62rem; padding:.22rem .4rem; }
          .gd .pvqchild{ margin-left:.5rem; padding-left:.45rem; border-left:2px solid var(--line); display:flex; flex-direction:column; gap:.35rem; }
          .gd .pvthumb{ width:2.2rem; height:1.6rem; border:1px solid var(--line); border-radius:4px; background:var(--panel); display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; }
          .gd .pvqsum{ background:#d1fae5; color:#065f46; border-radius:7px; padding:.3rem .5rem; font-size:.64rem; font-weight:700; }
          .gd .tstack{ display:flex; flex-direction:column; gap:.35rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / options</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ===== Scene A: the real options list ===== -->
                <div class="osc scA">
                  <div class="crumb">Products &rsaquo; Roller Blind &rsaquo; Options</div>
                  <div class="card-t" style="margin-bottom:.1rem">Roller Blind &mdash; Options</div>
                  <div class="subl">Edit product &middot; Fabrics &middot; Systems</div>
                  <div class="hbtns">
                    <span class="hbtn">&larr; Back to setup wizard</span>
                    <span class="hbtn">Copy from another product</span>
                    <span class="hbtn pri">Finish &rarr;</span>
                  </div>
                  <div class="bluep"><b>Options</b> are the things your salesperson picks for each blind when building a quote
                    &mdash; e.g. <em>Bottom Weight</em>, <em>Bracket colour</em>, <em>Control side</em>. Add an option below, then click
                    into it to set up its <b>choices</b> (the values the customer can pick from).</div>
                  <p class="exlabel" style="margin-bottom:.15rem">Options (3)</p>
                  <p class="exhelp" style="margin:0 0 .3rem">Drag the <b>&#8942;&#8942;</b> handle to reorder.</p>
                  <div class="olist">
                    <span class="ol hd"></span><span class="ol hd">Name</span><span class="ol hd c">Choices</span><span class="ol hd">Updated</span><span class="ol hd"></span>
                    <span class="ol">&#8942;&#8942;</span>
                    <span class="ol">Control type <span class="rpill">REQUIRED</span></span>
                    <span class="ol c">2</span><span class="ol numi">12 Sep 14:05</span>
                    <span class="ol act">Edit &middot; Duplicate &middot; <span class="del">Delete</span></span>
                    <span class="ol">&#8942;&#8942;</span>
                    <span class="ol ind">Motor type <span class="opill">OPTIONAL</span>
                      <span class="pcond">Appears when <b>Control type = Motorised</b> is selected</span></span>
                    <span class="ol c">3</span><span class="ol numi">12 Sep 14:22</span>
                    <span class="ol act">Edit &middot; Duplicate &middot; <span class="del">Delete</span></span>
                    <span class="ol">&#8942;&#8942;</span>
                    <span class="ol">Fascia width <span class="opill">OPTIONAL</span></span>
                    <span class="ol c numi">&#9998; number</span><span class="ol numi">12 Sep 15:01</span>
                    <span class="ol act">Edit &middot; Duplicate &middot; <span class="del">Delete</span></span>
                  </div>
                  <p class="exhelp">The navy <b>REQUIRED</b> pill = the customer must pick one. An indented <b>&#8627;</b> row only shows
                     in the quote builder once its parent choice is picked. <b>&#9998; number</b> = this option just captures a typed
                     measurement, so it has no choices at all.</p>
                </div>

                <!-- ===== Scene B: Add option — every control on the real form ===== -->
                <div class="osc scB">
                  <p class="exlabel">Add option</p>
                  <p class="exhelp" style="margin:0 0 .45rem">Examples: Control side, Control type, Draw side, Lining, Motor type, Headrail colour.</p>
                  <div class="frow" style="grid-template-columns:4fr 1fr">
                    <div class="fld"><label>Name <span class="req">*</span></label>
                      <div class="box f2"><span class="ph">e.g. Control side</span><span class="val">Control type</span></div></div>
                    <div>
                      <span class="chkline"><span class="tick on">&check;</span> Required</span><br>
                      <span class="chkline" style="margin-top:.25rem"><span class="tick">&check;</span>
                        <span>Allow multiple choices <small>renders as tick-boxes &mdash; salesperson can pick any combination</small></span></span>
                    </div>
                  </div>
                  <p class="exlabel" style="margin:.5rem 0 .2rem">Appears when (optional)</p>
                  <p class="exhelp" style="margin:0">No other choices on this product yet. Add some options + choices first if you want
                     this option to be gated.</p>
                  <div class="fs" style="margin-top:.45rem">
                    <span class="chkline"><span class="tick on">&check;</span>
                      <span>Also show a number input next to this option
                      <small>For things like wand length, cable length, etc. &mdash; the salesperson types a value alongside picking a
                      choice. Recorded on the quote line for supplier docs.</small></span></span>
                    <div class="fld" style="margin:.35rem 0 0 1.1rem"><label>What to call this field</label>
                      <div class="box f2"><span class="ph">e.g. Wand length (mm)</span><span class="val">Length (mm)</span></div></div>
                  </div>
                  <div class="addbtn">Add option</div>
                </div>

                <!-- ===== Scene C: the live choices grid (steps 3 + 4) ===== -->
                <div class="osc scC">
                  <p class="exlabel" style="margin-bottom:.3rem">Control type &mdash; Choices (2)
                    <span class="sind ok">All changes saved</span>
                    <span class="sind bad lr lr4">Must be a number.</span></p>
                  <div class="cgrid">
                    <span class="cc hd"></span><span class="cc hd">Label</span>
                    <span class="cc hd">Available on<span class="sa">Set all</span></span>
                    <span class="cc hd">Bands<span class="sa">Set all</span></span>
                    <span class="cc hd">Flat &pound;<span class="sa">Set all</span></span>
                    <span class="cc hd">%<span class="sa">Set all</span></span>
                    <span class="cc hd">&pound;/m<span class="sa">Set all</span></span>
                    <span class="cc hd">Default</span><span class="cc hd">Active</span><span class="cc hd">Face value</span><span class="cc hd"></span>

                    <span class="cc">&#8942;&#8942;</span>
                    <span class="cc l"><span class="box f3"><span class="ph"></span><span class="val">Cord</span></span></span>
                    <span class="cc"><span class="msel">All systems</span></span>
                    <span class="cc"><span class="msel"><span class="b4a">All bands</span><span class="b4b">A</span></span></span>
                    <span class="cc">0.00</span><span class="cc">0.00</span><span class="cc">0.00</span>
                    <span class="cc"><span class="tick on lr lr4">&check;</span></span>
                    <span class="cc"><span class="tick on">&check;</span></span>
                    <span class="cc"><span class="tick on">&check;</span></span>
                    <span class="cc act">Edit<br>+ Sub-option<br>Duplicate &middot; <span class="del">&times;</span></span>

                    <span class="cc">&#8942;&#8942;</span>
                    <span class="cc l"><span class="box f3"><span class="ph"></span><span class="val">Motorised</span></span>
                      <span class="wtb lr lr4">&#9638; &pound; by width &middot; 3 sizes</span></span>
                    <span class="cc"><span class="msel">All systems</span></span>
                    <span class="cc"><span class="msel">All bands</span></span>
                    <span class="cc"><span class="box f4"><span class="ph">0.00</span><span class="val">120.00</span></span></span>
                    <span class="cc">0.00</span><span class="cc">0.00</span>
                    <span class="cc"><span class="tick">&check;</span></span>
                    <span class="cc"><span class="tick on">&check;</span></span>
                    <span class="cc"><span class="tick on">&check;</span></span>
                    <span class="cc act">Edit<br>+ Sub-option<br>Duplicate &middot; <span class="del">&times;</span></span>

                    <span class="cc">+</span>
                    <span class="cc l newr">Type new label and press Enter&hellip;</span>
                    <span class="cc"><span class="msel">All systems</span></span>
                    <span class="cc"><span class="msel">All bands</span></span>
                    <span class="cc"></span><span class="cc"></span><span class="cc"></span>
                    <span class="cc"></span><span class="cc"></span><span class="cc"></span><span class="cc"></span>
                  </div>
                  <div class="gtip"><span><b>Tip:</b> type a label and press <b>Enter</b> to add one row at a time. For multiple
                    (e.g. <em>Left</em> + <em>Right</em>), use <b>Bulk add</b> &rarr;</span><span class="ghostbtn">+ Bulk add</span></div>
                  <div class="errbanner lr lr4" style="margin-top:.4rem"><span>&#9888;</span>
                    <div><b>Must be a number.</b> The three price cells are number boxes, so letters never get in &mdash; but
                    <em>empty</em> them and the save is refused. The badge itself turns red and carries the reason
                    (&ldquo;Save failed&rdquo; only when the server gives none), the cell rolls back to what it last saved, and after
                    four seconds the badge settles to <b>All changes saved</b> again.</div></div>
                  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.55rem">
                    <span class="hbtn pri">Done &mdash; back to options</span>
                    <span style="font-size:.58rem;color:var(--faint);flex:1;min-width:9rem">Every change is saved automatically as you
                      make it &mdash; you don&rsquo;t have to click anything to save. The badge at the top of the page tells you when
                      something&rsquo;s still in flight.</span>
                  </div>
                </div>

                <!-- ===== Scene D: the full choice edit page ===== -->
                <div class="osc scD">
                  <div class="crumb">Products &rsaquo; Roller Blind &rsaquo; Options &rsaquo; Control type &rsaquo; Motorised</div>
                  <div class="card-t" style="margin-bottom:.15rem">Edit choice: Motorised</div>
                  <p class="exhelp" style="margin:0 0 .4rem">Label, prices, system, default and active toggles are all editable inline on
                     the choices list. This page is for the deeper edits &mdash; width-table pricing and thumbnail image upload.</p>
                  <div class="fld"><label>Label <span class="req">*</span></label><div class="boxv">Motorised</div></div>
                  <div class="fs">
                    <div class="fsl">Ask for a number on this choice</div>
                    <span class="chkline"><span class="tick on">&check;</span> Show a number input when this choice is picked</span>
                    <div class="fld" style="margin:.3rem 0 0 1.1rem"><label>What to call this field</label>
                      <div class="box f5"><span class="ph">e.g. Top offset (mm)</span><span class="val">Number of brackets</span></div></div>
                  </div>
                  <div class="frow3">
                    <div class="fld"><label>Flat (&pound;)</label><div class="boxv">120.00</div></div>
                    <div class="fld"><label>Percent (%)</label><div class="boxv">0.00</div></div>
                    <div class="fld"><label>Per metre (&pound;/m)</label><div class="boxv">0.00</div></div>
                  </div>
                  <div class="fld" style="margin-top:.4rem"><label>Price per unit (&pound;) &mdash; &times; quantity</label>
                    <div class="box f5"><span class="ph">e.g. 2.50</span><span class="val">2.50</span></div>
                    <p class="exhelp">For things sold <b>per unit</b> &mdash; brackets, fixings and the like. The salesperson types
                       <b>how many</b> and the line adds this price &times; that quantity. Setting it adds a <em>Quantity</em> box on the
                       quote automatically (rename it under &ldquo;Ask for a number on this choice&rdquo; above if you&rsquo;d prefer,
                       e.g. &ldquo;Number of brackets&rdquo;).</p></div>
                  <div class="fld" style="margin-top:.4rem"><label>Per-metre length is measured along</label>
                    <div class="selectbox" style="min-width:0;width:100%;font-size:.62rem;padding:.2rem .4rem">Perimeter (2 &times; W + 2 &times; D)</div>
                    <p class="exhelp">Only matters when <b>Per metre (&pound;/m)</b> is set. Width is the usual choice; pick
                       <b>Perimeter</b> for trims that run all the way around the blind (e.g. a magnetic strip) &mdash; charged on
                       2&nbsp;&times;&nbsp;width&nbsp;+&nbsp;2&nbsp;&times;&nbsp;drop.</p></div>
                  <div class="fld" style="margin-top:.4rem"><label>Available on</label>
                    <div class="selectbox" style="min-width:0;width:100%;font-size:.62rem;padding:.2rem .4rem">All systems</div>
                    <p class="exhelp">&ldquo;All systems&rdquo; = appears on every system on this product. Pick a single system to limit
                       it. To price the same choice differently per system, use the <em>Duplicate</em> link on the choices list.</p></div>
                  <div class="fld" style="margin-top:.4rem"><label>Available for bands</label>
                    <div style="display:flex;gap:.5rem;padding-top:.15rem">
                      <span class="fchk"><span class="tick">&check;</span> A</span>
                      <span class="fchk"><span class="tick">&check;</span> B</span>
                      <span class="fchk"><span class="tick">&check;</span> C</span></div>
                    <p class="exhelp">Tick the bands this choice should appear for. Leave them all unticked =
                       &ldquo;appears for every band&rdquo; (the default).</p></div>
                  <div class="fld" style="margin-top:.4rem"><label>Available for specific fabrics</label>
                    <div class="scrollbox">
                      <div class="bandhd">Band A</div>
                      <span class="fchk"><span class="tick">&check;</span> Snow White</span>
                      <span class="fchk"><span class="tick">&check;</span> Cool White</span>
                      <div class="bandhd">Band B</div>
                      <span class="fchk"><span class="tick">&check;</span> Linen Oatmeal</span>
                    </div></div>
                  <div class="fs">
                    <div class="fsl">Thumbnail image (optional)</div>
                    <p class="exhelp" style="margin:0 0 .3rem">Shown to the customer in the quote builder when they pick this choice.
                       JPG, PNG, or GIF, up to 2&nbsp;MB.</p>
                    <span class="filebtn"><span class="cf">Choose File</span>
                      <span class="lr lr5">motorised.jpg</span></span>
                    <div class="picker lr lr5">
                      <div class="ph2">Open</div>
                      <div class="fileline">&#128247; motorised.jpg</div>
                      <div class="pbtns"><span class="hbtn">Cancel</span><span class="hbtn pri">Open</span></div>
                    </div>
                  </div>
                  <div class="tstack" style="margin-top:.35rem">
                    <span class="chkline"><span class="tick">&check;</span> Default <small>pre-selected for the customer</small></span>
                    <span class="chkline"><span class="tick on">&check;</span> Active <small>uncheck to hide from quote builder</small></span>
                  </div>
                  <div style="display:flex;gap:.3rem;margin-top:.4rem"><span class="hbtn pri">Save changes</span><span class="hbtn">Cancel</span></div>
                </div>

                <!-- ===== Scene E: the width-based price table ===== -->
                <div class="osc scE">
                  <div class="fs">
                    <div class="fsl">Width-based price table (optional)</div>
                    <p class="exhelp" style="margin:0 0 .4rem">A fourth pricing mode for cases where the surcharge varies by width.
                       Pricing engine looks up the smallest entry &ge; the customer&rsquo;s width (round-up). <b>Combined</b> with the
                       flat / percent / per-metre fields above.</p>
                    <p class="exlabel" style="margin:.2rem 0 .15rem">Option A &mdash; paste rows</p>
                    <p class="exhelp" style="margin:0 0 .25rem">One row per line: <b>width then price</b>, separated by space, comma, or
                       tab. Width in mm (800) or metres (0.800) &mdash; auto-detected. Empty textarea + no file = clear the table.</p>
                    <div class="ta f6" style="min-height:52px;font-family:ui-monospace,Menlo,Consolas,monospace">
                      <span class="ph">800, 15.00<br>1200, 22.50<br>1600, 30.00</span>
                      <span class="val">800, 15.00<br>1200, 22.50<br>1600, 30.00</span></div>
                    <p class="exlabel" style="margin:.45rem 0 .15rem">Option B &mdash; upload Excel</p>
                    <p class="exhelp" style="margin:0 0 .25rem">Either layout works (auto-detected): <b>vertical</b> &mdash; width in
                       column A, price in column B; or <b>horizontal</b> &mdash; widths across row 1, prices across row 2. If a file is
                       provided, it overrides the textarea above.</p>
                    <span class="filebtn"><span class="cf">Choose File</span> <span style="color:var(--faint)">No file chosen</span></span>
                  </div>
                  <div class="okbanner lr lr6"><span>&check;</span> Choice updated. 3 width-priced rows imported &mdash; check the table below to verify.</div>
                  <div class="errbanner lr lr6" style="margin-top:.35rem"><span>&#9888;</span>
                    <div><b>Width 2400 mm exceeds the largest entry in the width table for &lsquo;Motorised&rsquo;.</b>
                    The last row is your ceiling &mdash; a wider blind stops the quote until you add a row for it.</div></div>
                </div>

                <!-- ===== Scene F: sub-options ===== -->
                <div class="osc scF">
                  <p class="exlabel">Sub-options (1)</p>
                  <div class="subcard">
                    <div class="subhead">
                      <span class="subname">Motor type</span><span class="rpill">REQUIRED</span>
                      <span style="font-size:.55rem;color:var(--faint)">Appears when</span>
                      <span class="gatep">Control type = Motorised</span>
                      <span class="subacts">Edit gates &middot; <span class="del">Delete</span></span>
                    </div>
                    <div class="cgrid" style="grid-template-columns:.7rem minmax(5rem,1.3fr) 3rem 2.3rem 2.2rem 1.8rem 1.8rem 2rem 1.9rem 2.3rem 3.4rem">
                      <span class="cc hd"></span><span class="cc hd">Label</span><span class="cc hd">Available on</span><span class="cc hd">Bands</span>
                      <span class="cc hd">Flat &pound;</span><span class="cc hd">%</span><span class="cc hd">&pound;/m</span>
                      <span class="cc hd">Default</span><span class="cc hd">Active</span><span class="cc hd">Face value</span><span class="cc hd"></span>
                      <span class="cc">&#8942;&#8942;</span><span class="cc l">Tubular</span>
                      <span class="cc"><span class="msel">All systems</span></span><span class="cc"><span class="msel">All bands</span></span>
                      <span class="cc">0.00</span><span class="cc">0.00</span><span class="cc">0.00</span>
                      <span class="cc"><span class="tick on">&check;</span></span><span class="cc"><span class="tick on">&check;</span></span>
                      <span class="cc"><span class="tick on">&check;</span></span>
                      <span class="cc act">Edit<br>+ Sub-option</span>
                      <span class="cc">+</span><span class="cc l newr">Type new label and press Enter&hellip;</span>
                      <span class="cc"><span class="msel">All systems</span></span><span class="cc"><span class="msel">All bands</span></span>
                      <span class="cc"></span><span class="cc"></span><span class="cc"></span>
                      <span class="cc"></span><span class="cc"></span><span class="cc"></span><span class="cc"></span>
                    </div>
                  </div>
                  <div class="discl">
                    <div class="disclhd">+ Add sub-option</div>
                    <div class="fld"><label>Sub-option name <span class="req">*</span></label>
                      <div class="box f7"><span class="ph">e.g. Colour, Length, Bracket type</span><span class="val">Remote channels</span></div></div>
                    <p class="exlabel" style="margin:.4rem 0 .2rem">Appears when <span class="req">*</span></p>
                    <div style="display:flex;flex-direction:column;gap:.2rem">
                      <span class="chkline"><span class="tick">&check;</span> Control type = Cord</span>
                      <span class="chkline"><span class="tick t7">&check;</span> Control type = Motorised</span>
                    </div>
                    <p class="exhelp" style="margin:.2rem 0 .3rem">Tick one or more. The sub-option shows in the quote builder when
                       <b>any</b> ticked choice is selected.</p>
                    <span class="chkline"><span class="tick">&check;</span> Required &mdash; customer must pick a choice from this sub-option</span>
                    <div style="display:flex;gap:.3rem;margin-top:.4rem">
                      <span class="hbtn pri">Save &amp; open choices</span><span class="hbtn">Save &amp; stay here</span></div>
                  </div>
                </div>

                <!-- ===== Scene G: the option settings + what the salesperson sees ===== -->
                <div class="osc scG">
                  <div class="two">
                    <div>
                      <p class="exlabel">Edit option: Control type</p>
                      <div class="tstack">
                        <span class="chkline"><span class="tick on">&check;</span>
                          <span>Required <small>customer must pick a choice</small></span></span>
                        <span class="chkline"><span class="tick">&check;</span>
                          <span>Allow multiple choices <small>renders as tick-boxes instead of a dropdown &mdash; salesperson can pick
                          any combination, each ticked choice contributes to the price</small></span></span>
                        <span class="chkline"><span class="tick">&check;</span>
                          <span>Show above the size fields <small>renders this option (and anything nested under it) before Width / Drop
                          in the quote builder &mdash; e.g. the roller fascia group, so multi-fascia per-blind widths make sense</small></span></span>
                        <span class="chkline"><span class="tick on">&check;</span>
                          <span>Active <small>uncheck to hide from quote builder</small></span></span>
                      </div>
                    </div>
                    <div>
                      <div class="pvq">
                        <div class="pvqhd">&#128065; Quote builder</div>
                        <div class="pvqbody">
                          <div class="pvqrow"><label>Control type <span class="req">*</span></label>
                            <div class="selectbox f8"><span class="ph">&mdash; Select &mdash;</span><span class="val">Motorised</span></div></div>
                          <div class="pvqchild">
                            <div class="pvqrow"><label>Motor type <span class="req">*</span></label><div class="selectbox">Tubular</div></div>
                            <div class="pvqrow"><label>Number of brackets</label><div class="boxv" style="height:22px">5</div></div>
                            <div class="pvqrow"><label>Motorised</label><span class="pvthumb">&#128247;</span></div>
                          </div>
                          <div class="pvqsum">Options: +&pound;132.50</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <p class="exhelp">&pound;120.00 flat, plus five brackets at &pound;2.50 each. Pick <b>Cord</b> instead and none of the
                     follow-on rows show at all.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Reading the options list.</b>
                  <b class="c2"><span class="n">2</span> Add an option &mdash; every box on the form.</b>
                  <b class="c3"><span class="n">3</span> Its choices &mdash; type and press Enter.</b>
                  <b class="c4"><span class="n">4</span> Price it and scope it, right in the grid.</b>
                  <b class="c5"><span class="n">5</span> Open a choice in full &mdash; the Edit link.</b>
                  <b class="c6"><span class="n">6</span> Price it by width.</b>
                  <b class="c7"><span class="n">7</span> A sub-option that waits its turn.</b>
                  <b class="c8 good"><span class="n">8</span> The settings &mdash; and what they see.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Options</b> (the code and a few tooltips call them &ldquo;extras&rdquo;) are the things your salesperson picks for each blind
             when building a quote &mdash; <em>Control type</em>, <em>Bottom Weight</em>, <em>Bracket colour</em>, <em>Control side</em>.
             They are <b>not</b> the product&rsquo;s fabrics (its colours and materials) &mdash; those live on their own page. And an option
             has <b>no price of its own</b>: the price always sits on the <b>choices</b> inside it. Reach them from the product&rsquo;s
             <b>Edit</b> page &rarr; the <b>Options</b> section (or <b>Full manage &raquo;</b>), or from the setup wizard. Three buttons sit in
             the header of that screen: <b>&larr; Back to setup wizard</b>, <b>Copy from another product</b> and <b>Finish &rarr;</b>.</p>

          <p class="prose"><b>1) Reading the list.</b> This is the screen you will spend most of your time on, so learn to read it.</p>
          <ul class="steps">
            <li><b>The pills.</b> A navy <b>REQUIRED</b> pill means the customer must pick one of its choices; a grey <b>OPTIONAL</b> pill
                means they can leave it alone.</li>
            <li><b>The indented rows.</b> A row pushed in behind a <b>&#8627;</b> is gated: underneath the name it says
                <em>&ldquo;Appears when Control type = Motorised is selected&rdquo;</em>. It is a perfectly normal option &mdash; it just waits
                for its trigger before it shows in the quote builder.</li>
            <li><b>The Choices column.</b> A number is how many choices that option holds. <b>&#9998; number</b> in its place means the option
                captures a typed measurement instead &mdash; no pickable choices at all.</li>
            <li><b>Updated</b> tells you when it last changed, and dragging the <b>&#8942;&#8942;</b> handle reorders the list &mdash; that is
                the order the salesperson sees. A small <em>Saving&hellip;</em> note appears while the new order is stored.</li>
            <li><b>Duplicate</b> clones an option <em>with all its choices, pricing and gating</em> &mdash; the quickest way to make a near-identical
                one. <b>Delete</b> asks first, and it is honest about the cost: <code>Delete option Control type? Removes its 2 choices too.</code></li>
          </ul>

          <p class="prose"><b>2) Adding an option.</b> The <b>Add option</b> form sits above the list and has five controls, no more.</p>
          <ul class="steps">
            <li><b>Name *</b> &mdash; name it after what the customer is choosing (<em>Control side</em>, <em>Lining</em>, <em>Headrail colour</em>).
                Up to <b>150 characters</b>; leave it empty and you get <code>Name is required.</code></li>
            <li><b>Required</b> is a plain tick-box and it is <b>already ticked</b>. Leave it ticked if they must choose; untick it for a genuine
                extra they can skip.</li>
            <li><b>Allow multiple choices</b> &mdash; off by default. Tick it and the picker becomes <b>tick-boxes</b> instead of a dropdown, so
                they can have two at once (and <em>every</em> ticked choice adds its price). Leave it off unless that is genuinely possible.</li>
            <li><b>Appears when (optional)</b> &mdash; a scrolling list of tick-boxes, one per choice that already exists on this product, each
                labelled like <em>Control type = Motorised</em>. Tick none for a normal, always-visible option. On a brand-new product it just
                says <em>&ldquo;No other choices on this product yet&hellip;&rdquo;</em> &mdash; that is fine.</li>
            <li><b>Also show a number input next to this option</b> &mdash; tick it and a <b>What to call this field</b> box appears, already
                filled in with <b>Length (mm)</b> and selected so you can type straight over it (max <b>60 characters</b>). Include the unit.</li>
            <li><b>Where you land.</b> Click <b>Add option</b> and a normal option drops you <em>straight into its choices grid</em>. An option
                that is <b>only</b> a number does not &mdash; it needs no choices, so you stay on the list and it tells you why:
                <code>Option &ldquo;Fascia width&rdquo; added &mdash; it captures a typed number, so it needs no choices and is ready to
                use. Open it only if you also want pickable choices alongside the number.</code> That last sentence is the licence to have
                <em>both</em>: a dropdown of choices <b>and</b> a typed number on the same option.</li>
          </ul>

          <p class="prose"><b>3) Its choices.</b> The choices page is a <b>live grid</b> &mdash; there is <b>no Save button</b>.</p>
          <ul class="steps">
            <li><b>Add one.</b> Type a label in the bottom row (<em>&ldquo;Type new label and press Enter&hellip;&rdquo;</em>) and press
                <b>Enter</b>. The row appears above it, ready to price.</li>
            <li><b>Add a list.</b> <b>+ Bulk add</b> takes <b>one label per line</b> &mdash; paste <em>Left</em> then <em>Right</em>, or a
                column of slat sizes. New rows start with no price differences.</li>
            <li><b>Edit anything.</b> Click a cell, type, then <b>Tab</b> or <b>Enter</b> to save it &mdash; <b>Escape</b> cancels. The pill
                beside the heading is your receipt: <b>All changes saved</b>, <b>Saving&hellip;</b> while it is in flight, or a red one that
                <em>tells you what went wrong</em> (it falls back to <b>Save failed</b> only when the server offers no reason) and clears
                itself after four seconds. A refused cell rolls back to the value it last saved, so nothing is left looking saved when it
                is not.</li>
            <li><b>Repeats behave differently on the two add routes.</b> Type a label in the bottom row that is already on this option at
                that system and it <b>asks first</b>:
                <code>A choice called &ldquo;Cord&rdquo; already exists in this option for that system. Add it again anyway?</code> &mdash;
                say yes and you get a second one on purpose. <b>Bulk add never asks</b>: it <b>skips</b> every label that already exists and
                reloads with the rest. If <em>nothing</em> landed it tells you, and only then:
                <code>Nothing added &mdash; 3 labels already existed.</code></li>
            <li><b>Duplicate</b> on a row clones that choice &mdash; the intended way to have <em>the same label on a different system at a
                different price</em>. Drag <b>&#8942;&#8942;</b> to reorder, and <b>&times;</b> deletes.</li>
            <li><b>The blue button at the bottom is not a Save button.</b> <b>Done &mdash; back to options</b> only walks you back to the
                options list; the page says so itself underneath &mdash; <em>&ldquo;Every change is saved automatically as you make it &mdash;
                you don&rsquo;t have to click anything to save. The badge at the top of the page tells you when something&rsquo;s still in
                flight.&rdquo;</em> It is there for the reflex to look for a Save; clicking it saves nothing extra, and leaving without it
                loses nothing.</li>
          </ul>

          <p class="prose"><b>4) The five ways to price a choice.</b> They all <b>add together</b>, and blank everywhere means the choice is
             <b>free</b>. Three live in the grid; two live on the choice&rsquo;s <b>Edit</b> page &mdash; which is exactly the bit people
             cannot find. <em>A word on the count:</em> the width-table panel on the Edit page introduces itself as
             <b>&ldquo;A fourth pricing mode&rdquo;</b>, and that is not a contradiction &mdash; it is counting the three grid columns plus
             itself, and leaving <b>Price per unit</b> out because that one is a multiplier on a typed quantity rather than a rate on the
             blind. Counted as things you can fill in, there are five.</p>
          <ul class="steps">
            <li><b>Flat &pound;</b> (grid) &mdash; a straight surcharge, the same at every size.</li>
            <li><b>%</b> (grid) &mdash; worked out on the <b>base blind price</b>, not on the running total and not on the other options.</li>
            <li><b>&pound;/m</b> (grid) &mdash; charged by length. <em>Which</em> length is set on the Edit page under
                <b>Per-metre length is measured along</b>: <b>Width</b>, <b>Drop</b>, <b>Width + Drop</b> or
                <b>Perimeter (2 &times; W + 2 &times; D)</b>. Width is the usual one; perimeter is for a trim that runs all the way round.</li>
            <li><b>Price per unit (&pound;) &mdash; &times; quantity</b> (Edit page) &mdash; for brackets, fixings and the like. The salesperson
                types how many and the line adds price &times; quantity. Setting it <b>adds a Quantity box to the quote automatically</b>
                &mdash; but <em>Quantity</em> is only the name it falls back to when you have left the label blank. Fill in
                <b>What to call this field</b> under &ldquo;Ask for a number on this choice&rdquo; and the quote builder shows <em>your</em>
                wording instead, word for word &mdash; e.g. &ldquo;Number of brackets&rdquo;. Name it, or your salesperson gets a box
                labelled <em>Quantity</em> with nothing to say what of.</li>
            <li><b>Width-based price table</b> (Edit page) &mdash; when the surcharge changes with width. <b>Option A</b>: paste rows, one per
                line, <b>width then price</b>, separated by a space, a comma or a tab, in mm (<code>800</code>) or metres (<code>0.800</code>).
                <b>Option B</b>: upload the supplier&rsquo;s Excel &mdash; vertical or horizontal, auto-detected; a file beats the textarea.
                The engine <b>rounds up</b> to the first row at least as wide as the blind, so your last row is the ceiling. Emptying the box
                and saving <b>clears</b> the table. A choice priced this way wears a <b>&#9638; &pound; by width &middot; N sizes</b> badge on
                the grid &mdash; without it you would look at three blank price cells and assume the choice was free.</li>
          </ul>

          <p class="prose"><b>5) Scoping a choice</b> &mdash; three levels, coarse to fine. Leave them all alone and the choice shows everywhere.</p>
          <ul class="steps">
            <li><b>Available on</b> (systems) &mdash; in the grid it is a little dropdown reading <b>All systems</b>; on the Edit page it is a
                proper select, <em>All systems</em> or <em>&lt;System&gt; only</em>.</li>
            <li><b>Bands</b> &mdash; tick the fabric tiers it is offered on. Leave every band unticked and it means <em>appears for every band</em>.</li>
            <li><b>Available for specific fabrics</b> &mdash; a scrolling list of tick-boxes grouped under <b>BAND A</b>, <b>BAND B</b> and so
                on. Only needed <b>when a band cannot say it</b>: the screen&rsquo;s own example is a 38&nbsp;mm slat offered on <em>Snow</em> and
                <em>Cool White</em>, where Snow shares its band with colours that do not have it.</li>
            <li><b>Set all.</b> Every one of <b>Available on</b>, <b>Bands</b>, <b>Flat &pound;</b>, <b>%</b> and <b>&pound;/m</b> has a small blue
                <b>Set all</b> in its column heading: tick or type one value, hit <b>Apply to all</b>, and it lands on every row. On an option
                with fifteen choices that is one click instead of fifteen.</li>
            <li><b>Default</b> is the choice that comes pre-picked &mdash; and it is <b>one default per system</b>. Ticking it clears the old
                default only within the <em>same</em> system, so an option spread across systems can legitimately show several ticks.
                <b>Active</b> unticked hides a choice from the quote builder without deleting it.</li>
          </ul>

          <p class="prose"><b>6) Face value</b> &mdash; the last tick-box in the grid, and it is <b>ticked by default</b>. Leave it that way:
             it means the price you typed is the price charged. Untick it only for a <b>supplier list add-on</b> &mdash; then, on a
             supplier-priced product, that surcharge is run through the buying discount and markup along with the base instead of being added
             at face value.</p>

          <p class="prose"><b>7) Sub-options &mdash; an option that waits its turn.</b> <em>Motor type</em> should only appear once
             <em>Control type = Motorised</em> is picked. There are two routes to exactly the same thing, so use whichever you are nearer to:</p>
          <ul class="steps">
            <li><b>Way 1 &mdash; &ldquo;Appears when&rdquo;.</b> On the Add option form (or Edit option), tick one or more parent choices. Done.</li>
            <li><b>Way 2 &mdash; from the choice itself.</b> <b>+ Sub-option</b> on a choice row, or the <b>+ Add sub-option</b> panel at the
                bottom of the choices page. Same gating, with the ticks filled in for you.</li>
            <li><b>Any-of, not all-of.</b> Tick several parents and it shows when <b>any</b> of them is picked &mdash; one <em>Colour</em>
                sub-option can serve both <em>Chained</em> and <em>Chainless</em>, so you never duplicate a choice list. Tick none and it is
                always visible. Tick nothing on the sub-option panel and it refuses:
                <code>Pick at least one parent choice to gate the sub-option.</code></li>
            <li><b>Two save buttons.</b> <b>Save &amp; open choices</b> takes you straight in to fill the new sub-option; <b>Save &amp; stay
                here</b> leaves you on this page to add another.</li>
            <li><b>Nest as deep as the product needs.</b> A sub-option is a full option: its own choices, its own prices, its own sub-options.
                Each one appears as a card with a <b>Required</b> pill, its <b>Appears when</b> gate pills, an <b>Edit gates</b> link and
                <b>its whole choices grid inline</b> underneath.</li>
            <li><b>Deleting one is not free.</b> The confirm says so plainly:
                <code>Delete sub-option Motor type? Removes its choices too. Cannot be undone.</code></li>
          </ul>

          <p class="prose"><b>8) Copying a set-up.</b> Setting up a second, similar product? <b>Copy from another product</b> in the page header
             is by far the fastest way. Pick the source, tick the options you want (or <b>Select all</b>), and it brings across the
             <b>choices, the band scoping and the width-table pricing</b>. Gated sub-options are pulled in with their parent automatically,
             systems are matched <b>by name</b> (anything it cannot match becomes &ldquo;all systems&rdquo;), and an option already on the target
             with the same name is skipped. It tells you exactly what it did:
             <code>Copied 4 options (17 choices) from &ldquo;Roller Blind&rdquo;. Pulled in 2 linked sub-options automatically. Skipped 1 already
             on this product (same name).</code></p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Two things worth knowing.</b>
             <b>Renaming: use the full Edit pages, not the grid cell.</b> The factory&rsquo;s build rules find their values by <em>name</em>,
             with no proper link behind them, so a rename that is not carried into those rules silently stops them firing and the blind sizes
             wrongly &mdash; or blank &mdash; on the real ticket. Rename on <b>Edit option</b> or on a choice&rsquo;s <b>Edit</b> page and the
             rules are updated for you as part of <b>Save changes</b>. Rename by clicking the <b>Label</b> cell in the grid and it
             <b>does not cascade</b> &mdash; the new label saves, the build rules keep looking for the old one. Quick edits in the grid are
             perfect for prices and ticks; for a <em>name</em> on a product the factory builds, take the extra click and use Edit.
             <b>A follow-on option not showing?</b> Check its <b>Appears when</b> parents are <b>Active</b> choices that actually get picked,
             and check the option&rsquo;s own <b>Active</b> box on <em>Edit option</em>.</div></div>

          <div class="oops"><b>What it says when something is wrong:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>A price cell left <b>empty</b> in the grid &rarr; <code>Must be a number.</code> right in the red badge. (You cannot
                   easily type a <em>word</em> there &mdash; the three grid price cells are number boxes &mdash; but the Edit page&rsquo;s
                   own fields give the longer versions:
                   <code>Flat surcharge must be a number.</code>, <code>Percent surcharge must be a number.</code>,
                   <code>Per-metre surcharge must be a number.</code> or <code>Price per unit must be a number.</code>).</li>
               <li>Name or label left empty &rarr; <code>Name is required.</code> / <code>Label is required.</code> /
                   <code>Sub-option name is required.</code> &mdash; and too long &rarr;
                   <code>Name is too long (max 150 chars).</code> / <code>Label too long (150 max).</code> /
                   <code>Number-input label is too long (max 60 chars).</code></li>
               <li>A sub-option with nothing ticked &rarr; <code>Pick at least one parent choice to gate the sub-option.</code></li>
               <li>A repeated label &rarr; <code>A choice called &ldquo;Cord&rdquo; already exists in this option for that system. Add it again anyway?</code></li>
               <li>A blind wider than your width table &rarr; <code>Width 2400 mm exceeds the largest entry in the width table for &lsquo;Motorised&rsquo;.</code></li>
               <li>The wrong sort of picture &rarr; <code>Thumbnail must be a JPG, PNG, or GIF image.</code> or
                   <code>Thumbnail too large (2 MB max).</code>; a big spreadsheet &rarr; <code>File too large (5 MB max).</code></li>
               <li>Left the page open too long &rarr; <code>CSRF token invalid &mdash; reload the page.</code> Reload and do it again.</li>
             </ul></div>

          <p>All of it lands in the <b>quote builder</b>: each option is a dropdown (or tick-boxes when <em>Allow multiple choices</em> is on),
             a required one carries a red <b>*</b>, the dropdown shows <em>&mdash; Select &mdash;</em> until something is picked unless a choice
             is flagged <b>Default</b>, the chosen choice&rsquo;s <b>thumbnail</b> and any <b>number box</b> appear beside it, sub-options slide
             in when their trigger is chosen, and every surcharge lands on the line. <b>Show above the size fields</b> on the option pushes it
             (and anything nested under it) above <b>Width</b> and <b>Drop</b> &mdash; that is how the roller fascia group works. When you are
             done, use <b>&#128065; Live preview</b> on the product page to walk the whole cascade before a salesperson meets it.</p>',
        'script'  => [
            ['0:00', 'The options list: pills, indent, number.',
             'Options are the extras your salesperson picks for each blind — control type, a bottom weight, a bracket colour. They are not the fabrics, and the option itself carries no price: the price lives on the choices inside it. Read the list like this. A navy Required pill means the customer must pick one. A row pushed in behind an arrow only shows once another choice is picked — this one says, appears when Control type is Motorised. And where the count would be, a little pencil and the word number means that option just captures a typed measurement, so it has no choices at all.', 1],
            ['0:24', 'Add option — every box on the form.',
             'To add one, name it after what the customer is choosing. Control type. Up to a hundred and fifty characters, and it will not save without a name. Required is already ticked, so leave it. Allow multiple choices turns the dropdown into tick-boxes — only tick that if they could genuinely have two at once. Appears when is empty on a new product, and that is fine. And if you want a typed measurement alongside, tick the number box: the field name fills itself in as Length in millimetres, ready for you to type over. Click Add option and a normal option takes you straight into its choices.', 2],
            ['0:52', 'Type a label, press Enter.',
             'Here are the choices — the values the customer picks from. Type in the bottom row and press Enter. Cord. Then Motorised. There is no save button anywhere on this page: Tab or Enter saves the cell, Escape cancels, and the green badge at the top says, all changes saved. There is a blue Done button at the very bottom, but read the line beside it — it only walks you back to the options list, it saves nothing, because everything is already saved. For a whole list at once use Bulk add — one label per line. Bulk add never asks about repeats: it quietly skips any label already on the option and only speaks up if nothing at all got added. Type a repeat in the bottom row instead and that one does ask you first.', 3],
            ['1:14', 'Price it and scope it in the grid.',
             'Now price it. Blank means free. Flat pounds is a straight surcharge — a hundred and twenty pounds on Motorised. The percent column is worked out on the base blind price, not on the other options, and pounds per metre charges by length. Available on limits the choice to one system, and Bands to certain fabric tiers — leave both alone and it shows everywhere. Leave Face value ticked: that means the price you type is the price charged. Default is the one that comes pre-picked, and it is one default per system. And every one of those columns has a Set all in its heading, so you can apply a value down the whole list in one go. Those three price cells are number boxes, so letters never get into them — but clear one out completely and the save is refused: the badge turns red and tells you why, must be a number, the cell rolls back to what it last held, and four seconds later it settles to all changes saved again.', 4],
            ['1:50', 'Open a choice in full — the Edit link.',
             'Some things do not fit in a grid. To reach them, click Edit on the row. Edit — the first of the little links beside the choice. Everything from the grid is here too, plus three things that are only here. A price per unit — two pounds fifty a bracket — which automatically puts a number box on the quote. That box is called Quantity if you leave it at that, but name it yourself, just above, under, ask for a number on this choice. Number of brackets. Whatever you type there is exactly what your salesperson reads. The length your per-metre charge runs along: width, drop, width plus drop, or perimeter for a trim that goes all the way round. And a picture, for choices where the words alone will not do — Left, Right, Centre Left. Choose the file: a JPEG, PNG or GIF, up to two megabytes. Bands and fabrics are here too — only reach for fabrics when a band cannot say it.', 5],
            ['2:24', 'Price it by width.',
             'The last pricing mode is further down the same page: a price table by width. Type one row per line — width, then price — separated by a space, a comma or a tab, in millimetres or in metres, it works it out. Or upload the supplier\'s spreadsheet instead. It rounds up to the first row at least as wide as the blind, so your last row is your ceiling: a wider blind stops the quote and says so. It adds to the flat, percent and per-metre figures, it does not replace them — and emptying the box and saving clears the table. You stay on this page afterwards, so you can check the rows landed.', 6],
            ['2:52', 'A sub-option that waits its turn.',
             'A sub-option is an option that waits its turn. Motor type only appears once Motorised is picked. There are two routes to the same thing: tick the parents under Appears when on the option itself, or use the plus Sub-option link on the choice row, which fills those ticks in for you. Tick several parents and it shows when any one of them is picked; tick none and it is always visible. Then choose: save and open its choices, or save and stay here to add another. A sub-option is a full option, so it can have its own choices, its own prices, and its own sub-options. But do read the warning when you delete one — it takes its choices with it, and it cannot be undone.', 7],
            ['3:24', 'The settings, and what they see.',
             'Four settings live on the option itself. Required puts the red star on. Allow multiple choices turns the dropdown into tick-boxes, and every ticked choice adds its price. Show above the size fields pushes the option, and anything nested under it, above width and drop, which is how the roller fascia group works. And Active is how you retire an option without deleting it. And here is the result: pick Motorised, and Motor type, the number of brackets box and the picture all slide in — a hundred and thirty-two pounds fifty, the flat charge plus five brackets. Pick Cord, and none of it shows. Use Live preview on the product page to walk the whole thing through before a salesperson ever meets it.', 8],
        ],
];
