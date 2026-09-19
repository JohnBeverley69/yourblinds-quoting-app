<?php
declare(strict_types=1);

/**
 * Guide: products-wizard
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /admin/products/wizard.php end to end — all four steps, every field
 * on each, the three escape hatches on Fabrics, and the status board on step 4
 * (which is NOT a price grid, whatever the old version of this guide said).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Setting up a product (the setup wizard)',
        'eyebrow' => 'Products',
        'blurb'   => 'The whole four-step wizard — Name, Systems, Fabrics, Price tables — every field, every shortcut, and the band trap that stops a product pricing.',
        'lede'    => 'A <b>product</b> is one type of blind &mdash; <em>Roller Blind</em>, <em>Vertical</em>, <em>Roman</em>,
                      <em>Metal Venetian</em>. The setup wizard builds one in four steps &mdash; <b>Name</b>, <b>Systems</b>,
                      <b>Fabrics</b>, <b>Price tables</b> &mdash; and remembers exactly where you got to, so you can stop
                      half-way and come back tomorrow. It never traps you either: a <b>&ldquo;Skip wizard &rarr;&rdquo;</b> link sits
                      on every step. But do it in the order it asks, and the product actually <b>prices</b> at the end.',
        'open'    => '/admin/products/wizard.php',
        'css'     => '
          /* ── scene swapping (same pattern as the options guide) ── */
          .gd .wsc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB{ display:block; }
          .gd .stage[data-step="3"] .scC{ display:block; }
          .gd .stage[data-step="4"] .scD{ display:block; }
          .gd .stage[data-step="5"] .scE{ display:block; }
          .gd .stage[data-step="6"] .scF{ display:block; }
          .gd .stage[data-step="7"] .scG{ display:block; }
          .gd .stage[data-step="8"] .scH{ display:block; }

          /* ── the real wiz-stepper: 4 numbered bubbles, done ones green + tick ── */
          .gd .wstepper{ display:flex; gap:0; margin:0 0 1rem; padding:0; list-style:none; }
          .gd .wstep{ flex:1; text-align:center; position:relative; font-size:.58rem; text-transform:uppercase;
                      letter-spacing:.05em; font-weight:700; color:var(--faint); }
          .gd .wstep::after{ content:""; position:absolute; top:.7rem; right:-50%; width:100%; height:2px; background:var(--line); z-index:0; }
          .gd .wstep:last-child::after{ display:none; }
          .gd .bub{ position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center;
                    width:1.5rem; height:1.5rem; border-radius:50%; background:var(--line); color:var(--soft);
                    font-weight:700; font-size:.72rem; }
          .gd .bub::after{ content:""; position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                    color:#fff; font-size:.8rem; }
          .gd .wlbl{ display:block; margin-top:.22rem; }
          /* current bubble — navy with the pale-blue ring */
          .gd .stage[data-step="0"] .s1 .bub, .gd .stage[data-step="1"] .s1 .bub, .gd .stage[data-step="2"] .s1 .bub,
          .gd .stage[data-step="3"] .s2 .bub,
          .gd .stage[data-step="4"] .s3 .bub, .gd .stage[data-step="5"] .s3 .bub,
          .gd .stage[data-step="6"] .s4 .bub, .gd .stage[data-step="7"] .s4 .bub{ background:#1f3b5b; color:#fff; box-shadow:0 0 0 3px #dbeafe; }
          /* done bubble — green, and the number is replaced by a tick */
          .gd .stage[data-step="3"] .s1 .bub, .gd .stage[data-step="4"] .s1 .bub, .gd .stage[data-step="5"] .s1 .bub,
          .gd .stage[data-step="6"] .s1 .bub, .gd .stage[data-step="7"] .s1 .bub, .gd .stage[data-step="8"] .s1 .bub,
          .gd .stage[data-step="4"] .s2 .bub, .gd .stage[data-step="5"] .s2 .bub, .gd .stage[data-step="6"] .s2 .bub,
          .gd .stage[data-step="7"] .s2 .bub, .gd .stage[data-step="8"] .s2 .bub,
          .gd .stage[data-step="6"] .s3 .bub, .gd .stage[data-step="7"] .s3 .bub, .gd .stage[data-step="8"] .s3 .bub,
          .gd .stage[data-step="8"] .s4 .bub{ background:#10b981; color:transparent; box-shadow:none; }
          .gd .stage[data-step="3"] .s1 .bub::after, .gd .stage[data-step="4"] .s1 .bub::after, .gd .stage[data-step="5"] .s1 .bub::after,
          .gd .stage[data-step="6"] .s1 .bub::after, .gd .stage[data-step="7"] .s1 .bub::after, .gd .stage[data-step="8"] .s1 .bub::after,
          .gd .stage[data-step="4"] .s2 .bub::after, .gd .stage[data-step="5"] .s2 .bub::after, .gd .stage[data-step="6"] .s2 .bub::after,
          .gd .stage[data-step="7"] .s2 .bub::after, .gd .stage[data-step="8"] .s2 .bub::after,
          .gd .stage[data-step="6"] .s3 .bub::after, .gd .stage[data-step="7"] .s3 .bub::after, .gd .stage[data-step="8"] .s3 .bub::after,
          .gd .stage[data-step="8"] .s4 .bub::after{ content:"\2713"; }

          /* ── card furniture ── */
          .gd .wh2{ font-weight:700; font-size:.92rem; color:var(--ink); margin:0 0 .2rem; }
          .gd .wlede{ color:var(--soft); font-size:.76rem; line-height:1.5; margin:0 0 .7rem; }
          .gd .whelper{ background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:.45rem .6rem;
                        font-size:.72rem; color:#1e40af; line-height:1.5; margin:.6rem 0; }
          .gd .whelper b{ color:#1e3a8a; }
          .gd .whelper.split{ display:flex; align-items:center; justify-content:space-between; gap:.7rem; flex-wrap:wrap; }
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel);
                     display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          /* the material word is GENUINELY pre-filled on the real form — show it
             as a real value sitting there, not as a faint placeholder */
          .gd .box .ph.deflt{ color:var(--ink); }
          .gd .stage[data-step="1"] .hl1{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .wnote{ font-size:.68rem; color:var(--faint); margin:.25rem 0 0; }

          /* ── checkboxes: bold lead line + small grey sub-line (the real control) ── */
          .gd .wchk{ display:flex; align-items:flex-start; gap:.45rem; font-size:.76rem; color:var(--ink); margin:.5rem 0; }
          .gd .wchk small{ display:block; color:var(--faint); font-size:.68rem; line-height:1.45; margin-top:.12rem; }
          .gd .wfade{ opacity:.45; margin-top:.6rem; }

          /* ── buttons + footer ── */
          .gd .wbtn{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:8px;
                     padding:.35rem .75rem; font-size:.76rem; font-weight:700; }
          .gd .wbtn.sec{ background:var(--surface); color:var(--accent-ink); border:1px solid var(--border-strong,#c7ccd4); }
          .gd .wbtn.dis{ background:var(--accent); opacity:.5; }
          .gd .wfoot{ display:flex; align-items:center; justify-content:space-between; gap:.5rem;
                      border-top:1px solid var(--line-2); margin-top:.8rem; padding-top:.6rem; }
          .gd .wback{ font-size:.72rem; color:var(--faint); }
          .gd .whint{ font-size:.68rem; color:var(--faint); }
          .gd .wbtnrow{ display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; margin-top:.45rem; }

          /* ── lists (systems, fabrics, price tables, missing combos) ── */
          .gd .wlist{ background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.15rem .55rem; margin:.5rem 0; }
          .gd .wrow{ display:flex; align-items:center; gap:.4rem; font-size:.76rem; color:var(--ink);
                     padding:.28rem 0; border-bottom:1px solid var(--line-2); }
          .gd .wrow:last-child{ border-bottom:none; }
          .gd .wrow .ok{ color:var(--good); font-weight:700; }
          .gd .wrow .rm{ margin-left:auto; color:var(--err); font-size:.7rem; text-decoration:underline; }
          .gd .wempty{ color:var(--faint); font-style:italic; font-size:.76rem; padding:.35rem 0; }
          .gd .wtag{ color:var(--faint); font-size:.68rem; font-style:italic; }
          .gd .wtag.sysonly{ color:#7c3aed; }
          .gd .wcount{ display:flex; justify-content:space-between; align-items:center; font-size:.7rem;
                       color:var(--faint); margin:.6rem 0 .1rem; }
          .gd .wcount .rm{ color:var(--err); text-decoration:underline; }

          /* ── band box + its datalist suggestions ── */
          .gd .wdl{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; background:var(--surface);
                    margin-top:.15rem; font-size:.72rem; overflow:hidden; max-width:8rem; }
          .gd .wdl span{ display:block; padding:.16rem .45rem; color:var(--soft); border-bottom:1px solid var(--line-2); }
          .gd .wdl span:last-child{ border-bottom:none; }
          .gd .wdl span.on{ background:var(--accent-wash); color:var(--accent-ink); font-weight:700; }
          .gd .wta{ min-height:4.6rem; line-height:1.4; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.7rem; }
          .gd .wta .val{ line-height:1.4; }
          .gd .wgrid3{ display:grid; grid-template-columns:5rem 8rem 1fr; gap:.45rem; align-items:start; }
          @media(max-width:620px){ .gd .wgrid3{ grid-template-columns:1fr; } .gd .whelper.split{ display:block; } }

          /* ── step-4 status tiles ── */
          .gd .wtile{ border-radius:10px; padding:.7rem .85rem; text-align:center; margin-bottom:.6rem;
                      background:linear-gradient(135deg,#fef3c7 0%,#fffbeb 100%); border:1px solid #fcd34d; }
          .gd .wtile .icon{ font-size:1.4rem; line-height:1; }
          .gd .wtile h3{ margin:.25rem 0 .2rem; font-size:.86rem; color:#78350f; }
          .gd .wtile p{ margin:0; font-size:.72rem; color:#92400e; line-height:1.5; }
          .gd .wtile.done{ background:linear-gradient(135deg,#d1fae5 0%,#ecfdf5 100%); border-color:#a7f3d0; }
          .gd .wtile.done h3{ color:#065f46; } .gd .wtile.done p{ color:#047857; }
          .gd .wcard{ border:1px solid var(--line); border-radius:10px; padding:.6rem .75rem; margin-bottom:.6rem; }
          .gd .wcard.blue{ background:#eff6ff; border-color:#bfdbfe; }
          .gd .wcard.blue .wh2, .gd .wcard.blue .wlede{ color:#1e40af; }
          .gd .wcard.amber{ background:#fffbeb; border-color:#fcd34d; }
          .gd .wcard.amber .wh2{ color:#78350f; } .gd .wcard.amber .wlede{ color:#92400e; }
          .gd .wemptypill{ background:#fef3c7; color:#92400e; font-size:.56rem; font-weight:700; text-transform:uppercase;
                           letter-spacing:.05em; padding:.08rem .38rem; border-radius:999px; }
          .gd .wcells{ color:var(--faint); font-size:.68rem; }
          .gd .wrow .wbtn{ margin-left:auto; padding:.2rem .5rem; font-size:.68rem; }
          .gd .wrow .wbtn + .rm{ margin-left:.4rem; }

          /* ── the downstream consequence panel ── */
          .gd .wqb{ border:1px solid var(--line); border-radius:9px; overflow:hidden; margin-top:.6rem; }
          .gd .wqbhd{ background:var(--panel); padding:.3rem .55rem; font-size:.64rem; font-weight:700;
                      color:var(--faint); border-bottom:1px solid var(--line); text-transform:uppercase; letter-spacing:.04em; }
          .gd .wqbbody{ padding:.5rem .55rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / admin / products / wizard.php</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Setup wizard</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Set up a product <span style="font-weight:400;color:var(--faint);font-size:.74rem">&middot; Guided 4-step setup. <span style="text-decoration:underline">Skip wizard &rarr;</span></span></div>
                <ol class="wstepper">
                  <li class="wstep s1"><span class="bub">1</span><span class="wlbl">Name</span></li>
                  <li class="wstep s2"><span class="bub">2</span><span class="wlbl">Systems</span></li>
                  <li class="wstep s3"><span class="bub">3</span><span class="wlbl">Fabrics</span></li>
                  <li class="wstep s4"><span class="bub">4</span><span class="wlbl">Price tables</span></li>
                </ol>

                <!-- Scene A (steps 0,1): step 1 — name + material word -->
                <div class="wsc scA">
                  <div class="wh2">What kind of blind are we adding?</div>
                  <p class="wlede">A <b>product</b> is one type of blind &mdash; e.g. <em>Roller Blind</em>, <em>Vertical</em>,
                     <em>Roman</em>, <em>Metal Venetian</em>. Each product gets its own systems, fabrics, options and price tables.</p>
                  <div class="fld"><label>Product name <span class="req">*</span></label>
                    <div class="box f1"><span class="ph">e.g. Roller Blind</span><span class="val">Roller Blind</span></div></div>
                  <div class="fld" style="margin-top:.55rem"><label>What do you call the material this product is made of?</label>
                    <div class="box hl1"><span class="ph deflt">Fabric</span></div>
                    <p class="wnote">Already filled in for you &mdash; overtype it for a venetian.</p></div>
                  <div class="whelper">For rollers / romans use <b>Fabric</b>. For metal venetians, try <b>Colour</b>.
                     For wood venetians, <b>Finish</b>. (You can change this later.)</div>
                  <div class="wfade">
                    <div class="wchk"><span class="tick">&check;</span><span>This product has <b>no fabrics</b> (e.g. headrail only, track, spares).</span></div>
                    <div class="wchk"><span class="tick">&check;</span><span>Priced by <b>width only</b> (no drop) &mdash; e.g. a headrail or track.</span></div>
                    <div class="wchk"><span class="tick">&check;</span><span>Priced <b>per slat</b> (by drop) &mdash; e.g. vertical fabric only.</span></div>
                    <div class="wchk"><span class="tick">&check;</span><span>Priced <b>per square metre</b> &mdash; e.g. shutters.</span></div>
                  </div>
                </div>

                <!-- Scene B (step 2): the four tickboxes, full size -->
                <div class="wsc scB">
                  <div class="wh2">The four tickboxes &mdash; leave them alone for an ordinary blind</div>
                  <div class="wchk"><span class="tick">&check;</span><span>This product has <b>no fabrics</b> (e.g. headrail only, track, spares).
                    <small>We&rsquo;ll skip the fabric step and price it on system &times; size alone.</small></span></div>
                  <div class="wchk"><span class="tick">&check;</span><span>Priced by <b>width only</b> (no drop) &mdash; e.g. a headrail or track.
                    <small>The Drop field is hidden at quote time and each price table is a single width &rarr; price list.</small></span></div>
                  <div class="wchk"><span class="tick">&check;</span><span>Priced <b>per slat</b> (by drop) &mdash; e.g. vertical fabric only.
                    <small>Price table is a drop &rarr; price-per-slat list; the line price is that rate &times; number of slats. Leave the boxes above unticked.</small></span></div>
                  <div class="wchk"><span class="tick">&check;</span><span>Priced <b>per square metre</b> &mdash; e.g. shutters.
                    <small>A single &pound;/m&sup2; rate &times; area (width &times; height), with an optional minimum area (set on the product edit page). Leave the boxes above unticked.</small></span></div>
                  <div class="wfoot"><span class="wback"></span><span class="wbtn">Create product &rarr;</span></div>
                </div>

                <!-- Scene C (step 3): systems -->
                <div class="wsc scC">
                  <div class="wh2">What systems does <em>Roller Blind</em> come in?</div>
                  <p class="wlede">A <b>system</b> is the operating mechanism &mdash; e.g. <em>Standard</em>, <em>Motorised</em>,
                     <em>Battery</em>, <em>Pelmet</em>. Each system can have its own price table. Most products need just one or two.</p>
                  <div class="okbanner"><b>&check;</b> Added 2 systems. Skipped 1 (already on this product).</div>
                  <div class="wlist">
                    <div class="wrow"><span class="ok">&check;</span> <b>Standard</b><span class="rm">Remove</span></div>
                    <div class="wrow"><span class="ok">&check;</span> <b>Motorised</b><span class="rm">Remove</span></div>
                  </div>
                  <div class="fld"><label>One system per line (paste from Excel or type)</label>
                    <div class="ta wta f3"><span class="ph">Standard<br>Motorised<br>FW 35mm String<br>FW 50mm Tape<br>&hellip;</span><span class="val">Standard<br>Motorised<br>Standard</span></div></div>
                  <div class="wbtnrow"><span class="wbtn sec">&plus; Add</span><span class="whint">One line = one system. Single name = single add.</span></div>
                  <div class="whelper"><b>What&rsquo;s a system?</b> The operating mechanism or physical variant &mdash; e.g.
                     <em>Standard</em>, <em>Motorised</em>, or size variants like <em>35mm String</em>, <em>50mm Tape</em>.
                     Each system gets its own price table.</div>
                  <div class="wfoot"><span class="wback">&larr; Back</span><span class="wbtn">Continue &rarr;</span></div>
                </div>

                <!-- Scene D (step 4): fabrics — band first, then the paste box -->
                <div class="wsc scD">
                  <div class="wh2">What fabrics do you sell?</div>
                  <p class="wlede">Add at least one fabric with a <b>band code</b>. The band groups fabrics that share the
                     same price table &mdash; your &ldquo;cheap range&rdquo; and &ldquo;premium range&rdquo; are usually different
                     bands. Common codes: <code>A</code>/<code>B</code>/<code>C</code> from a supplier price list, or words like
                     <code>Standard</code>/<code>Special</code>.</p>
                  <p class="wlede">By default a fabric is <b>universal</b> &mdash; available with every system. If a colour only
                     applies to a specific system, pick that system in the dropdown.</p>
                  <div class="okbanner"><b>&check;</b> Added 41 to Band A (all systems).</div>
                  <div class="wcount"><span>42 fabrics added</span><span class="rm">Delete all</span></div>
                  <div class="wlist">
                    <div class="wrow"><span class="ok">&check;</span> <b>Anthracite</b> <span class="wtag sysonly">&mdash; Band A &middot; Motorised only</span><span class="rm">Remove</span></div>
                    <div class="wrow"><span class="ok">&check;</span> <b>Plain Cream</b> <span class="wtag">&mdash; Band A &middot; all systems</span><span class="rm">Remove</span></div>
                    <div class="wrow"><span class="ok">&check;</span> <b>Plain White</b> <span class="wtag">&mdash; Band A &middot; all systems</span><span class="rm">Remove</span></div>
                  </div>
                  <div class="wgrid3">
                    <div class="fld"><label>Band <span class="req">*</span></label>
                      <div class="box f4"><span class="ph">A</span><span class="val">A</span></div>
                      <div class="wdl"><span class="on">A</span><span>B</span></div></div>
                    <div class="fld"><label>Available on</label><div class="selectbox" style="min-width:0">All systems (universal)</div></div>
                    <div class="fld"><label>Fabrics (one per line or comma-separated &mdash; paste from Excel or type)</label>
                      <div class="ta wta f4"><span class="ph">Plain White<br>Plain Cream<br>Plain Black<br>Silver<br>&hellip;</span><span class="val">Plain White<br>Plain Cream<br>Plain Black<br>Silver<br>&hellip;</span></div></div>
                  </div>
                  <div class="wbtnrow"><span class="wbtn sec">&plus; Add</span><span class="whint">One line <b>or comma</b> = one fabric.
                     Or use <code>[System Name]</code> headers in the textarea to add to many systems in one go.</span></div>
                  <div class="wfoot"><span class="wback">&larr; Back</span><span class="wbtn">Continue &rarr;</span></div>
                </div>

                <!-- Scene E (step 5): the three escape hatches + the [System] paste slip -->
                <div class="wsc scE">
                  <div class="whelper split"><span><b>Same fabrics as another product?</b> Copy the whole range across (bands and all)
                     rather than re-entering it.</span><span class="wbtn sec">Copy from another product &rarr;</span></div>
                  <div class="whelper split"><span><b>Rather set up pricing first?</b> Do the price tables now &mdash; bulk-import your
                     grid &mdash; then come back here: the band box will auto-suggest the bands you imported.</span><span class="wbtn sec">Price tables first &rarr;</span></div>
                  <div class="whelper split"><span><b>No fabrics for this product?</b> For a headrail-only line, track, or spares you can
                     skip this step and price on system &times; size alone.</span><span class="wbtn sec">No fabrics &mdash; skip &rarr;</span></div>
                  <div class="fld" style="margin-top:.5rem"><label>Fabrics (one per line or comma-separated &mdash; paste from Excel or type)</label>
                    <div class="ta wta f5" style="min-height:6.4rem"><span class="ph">&hellip;</span><span class="val">[Standard Roller]<br>Linen Oyster<br>Linen Slate<br>[Motorized]<br>Blackout Ivory<br>Blackout Graphite</span></div></div>
                  <div class="errbanner" style="margin-top:.45rem"><b>&#9888;</b> Nothing added. Check the paste &mdash; colours need a
                     [System Name] header above them, or pick a system in the dropdown.</div>
                  <p class="wnote">Neither header is a system on this product &mdash; step 2 named them <code>Standard</code> and
                     <code>Motorised</code>, so every colour under them was skipped. Retype the two headers to match and paste again:</p>
                  <div class="okbanner" style="margin-top:.4rem"><b>&check;</b> Added 12 to Band B across 2 systems. Skipped 2 (likely duplicates).</div>
                  <div class="whelper split" style="margin-top:.55rem"><span><b>Already have these fabrics?</b> Bring them in instead of typing:</span>
                    <span><span class="wbtn sec">&#128218; Import from Fabric Library</span> <span class="wbtn sec">&#128196; Import from spreadsheet</span></span></div>
                </div>

                <!-- Scene F (step 6): step 4 — the status board, not a grid -->
                <div class="wsc scF">
                  <div class="wtile"><div class="icon">&#128203;</div><h3>One thing left &mdash; price tables</h3>
                    <p>Create the price tables below &mdash; one per band &times; system &mdash; then fill in the prices.</p></div>
                  <div class="wcard blue"><div class="wh2">Got a price spreadsheet? Import it</div>
                    <p class="wlede">Upload your width &times; drop price grid for a system (all its bands in one file) and we&rsquo;ll
                       create and fill its tables in one go &mdash; no need to create the empty tables first. Do each system in turn.</p>
                    <div class="wbtnrow"><span class="wbtn">Import Standard &rarr;</span><span class="wbtn">Import Motorised &rarr;</span></div></div>
                  <div class="wcard amber"><div class="wh2">4 price tables need setting up</div>
                    <p class="wlede">Each fabric band on each system needs its own price grid. We&rsquo;ve spotted these
                       <b>(system &plus; band) combinations</b> without one. Clicking <em>Create</em> below makes an empty price
                       table for each &mdash; you&rsquo;ll fill in the actual width &times; drop prices afterwards.</p>
                    <div class="wrow" style="border-bottom:none;padding:.15rem 0"><b>Standard</b> <span style="color:#92400e">&plus; Band A</span></div>
                    <div class="wrow" style="border-bottom:none;padding:.15rem 0"><b>Standard</b> <span style="color:#92400e">&plus; Band B</span></div>
                    <div class="wrow" style="border-bottom:none;padding:.15rem 0"><b>Motorised</b> <span style="color:#92400e">&plus; Band A</span></div>
                    <div class="wrow" style="border-bottom:none;padding:.15rem 0"><b>Motorised</b> <span style="color:#92400e">&plus; Band B</span></div>
                    <div class="wbtnrow"><span class="wbtn">Create all 4 empty price tables</span></div>
                    <p class="whint" style="margin-top:.35rem">One click &mdash; the empty grids appear instantly. Leave any out that don&rsquo;t apply to what you sell.</p></div>
                </div>

                <!-- Scene G (step 7): fill them in + the band trap -->
                <div class="wsc scG">
                  <div class="wh2">Price tables</div>
                  <p class="wlede">One price grid per system &plus; band. Empty tables won&rsquo;t generate a price at quote time &mdash;
                     fill them in now, or come back via the product edit page later.</p>
                  <div class="wlist">
                    <div class="wrow"><span class="ok">&check;</span> <b>Standard</b> <span class="wcells">&mdash; Band A &middot; 48 cells</span><span class="wbtn sec">Edit</span><span class="rm">Remove</span></div>
                    <div class="wrow"><span class="ok">&check;</span> <b>Standard</b> <span class="wcells">&mdash; Band B &middot; 48 cells</span><span class="wbtn sec">Edit</span><span class="rm">Remove</span></div>
                    <div class="wrow"><span class="wemptypill">Empty</span> <b>Motorised</b> <span class="wcells">&mdash; Band A</span><span class="wbtn">Fill in</span><span class="rm">Remove</span></div>
                    <div class="wrow"><span class="wemptypill">Empty</span> <b>Motorised</b> <span class="wcells">&mdash; Band B</span><span class="wbtn">Fill in</span><span class="rm">Remove</span></div>
                  </div>
                  <div class="wqb"><div class="wqbhd">&#128065; Quote builder &mdash; band mismatch</div>
                    <div class="wqbbody"><div class="errbanner"><b>&#9888;</b> No price table for Roller Blind band Plain on system &lsquo;Standard&rsquo;.</div></div></div>
                </div>

                <!-- Scene H (step 8): done + resume -->
                <div class="wsc scH">
                  <div class="wtile done"><div class="icon">&#127881;</div><h3>All set &mdash; Roller Blind is ready to quote</h3>
                    <p>Product, 2 systems, 54 fabrics, and all 4 price tables filled in.</p></div>
                  <div class="wfoot" style="border-top:none;margin-top:0"><span class="wback">&larr; Back to fabrics</span><span class="wbtn">Open product edit page &rarr;</span></div>
                  <div class="wqb" style="margin-top:.7rem"><div class="wqbhd">&#128260; Next time you open the wizard &mdash; step 1, before you name anything</div>
                    <div class="wqbbody">
                      <div class="wcard amber" style="margin-bottom:0"><div class="wh2">Continue an in-progress product</div>
                        <p class="wlede">These aren&rsquo;t finished yet &mdash; resume and the wizard picks up at the next thing each one needs.
                           Or start a brand-new product below.</p>
                        <div class="wrow"><b>Vertical Blind</b> <span style="color:#92400e;font-size:.7rem">&mdash; needs fabric &plus; price table</span><span class="wbtn sec">Resume &rarr;</span></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Name it &mdash; and say what the material is called.</b>
                  <b class="c2"><span class="n">2</span> The four tickboxes that change everything.</b>
                  <b class="c3"><span class="n">3</span> Systems &mdash; one line each, paste the lot.</b>
                  <b class="c4"><span class="n">4</span> Fabrics &mdash; the band comes first.</b>
                  <b class="c5"><span class="n">5</span> Two shortcuts, one slip &mdash; and the fix.</b>
                  <b class="c6"><span class="n">6</span> Price tables: a checklist, not a grid.</b>
                  <b class="c7 err"><span class="n">7</span> Fill them in &mdash; and the band trap.</b>
                  <b class="c8 good"><span class="n">8</span> Done &mdash; and what the wizard leaves you.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where it lives.</b> Products &rarr; <b>&#10024; Setup wizard</b> (the secondary button at the top). On a brand-new
             account with no products at all, the empty Products page offers a big <b>&#10024; Start the setup wizard</b> instead,
             with <em>Skip the wizard &mdash; just add a product</em> underneath. Nothing here is a cage: every step carries a
             <b>Skip wizard &rarr;</b> link that drops you on the standard &ldquo;add product&rdquo; form (before the product exists) or on
             the product&rsquo;s edit page (after). The wizard only suggests an order &mdash; it never blocks you.</p>

          <p><b>The stepper</b> across the top &mdash; <b>1 Name &middot; 2 Systems &middot; 3 Fabrics &middot; 4 Price tables</b> &mdash; is
             a progress bar and a set of links. A finished step turns <b>green and its number becomes a &check;</b>. Bubbles are
             clickable <b>forwards and back</b> the moment their prerequisites are met, so going back to fix the name never strands
             you. Step 4 only ticks when <b>every</b> price table has cells in it <em>and</em> no combination is missing &mdash; so
             it is an honest tick, not a &ldquo;you got to the end&rdquo; tick. And every action reloads the page properly, so
             <b>refreshing never re-submits</b> anything.</p>

          <p class="prose"><b>Step 1 &mdash; Name.</b> Six controls, and only the first is compulsory.</p>
          <ul class="steps">
            <li><b>Product name *</b> &mdash; required, up to 150 characters, placeholder <code>e.g. Roller Blind</code>. This is what
                your salesperson picks in the quote builder, so use the name they say out loud.</li>
            <li><b>&ldquo;What do you call the material this product is made of?&rdquo;</b> &mdash; up to 40 characters, and it arrives
                <b>already filled in with &ldquo;Fabric&rdquo;</b>. Overtype it if that is the wrong word. The screen&rsquo;s own blue note says:
                <em>For rollers / romans use Fabric. For metal venetians, try Colour. For wood venetians, Finish. (You can change this
                later.)</em> Whatever you type becomes the word the rest of the app uses for this product &mdash; including the heading on
                the Fabrics step, which reads <em>&ldquo;What colours do you sell?&rdquo;</em> if you typed Colour.</li>
            <li><b>This product has no fabrics</b> (headrail only, track, spares) &mdash; skips the fabric step entirely and prices on
                <b>system &times; size</b> alone.</li>
            <li><b>Priced by width only</b> (no drop) &mdash; the <b>Drop field disappears at quote time</b> and each price table becomes a
                single width &rarr; price list. For a headrail or a track.</li>
            <li><b>Priced per slat</b> (by drop) &mdash; the table is a drop &rarr; price-per-slat list and the line price is that rate
                &times; the number of slats. For vertical fabric sold on its own.</li>
            <li><b>Priced per square metre</b> &mdash; one &pound;/m&sup2; rate &times; the area, with an optional minimum area you set later on
                the edit page. For shutters. The screen tells you the last three are exclusive: <em>&ldquo;Leave the boxes above
                unticked.&rdquo;</em> For an ordinary blind, leave <b>all four</b> alone.</li>
          </ul>
          <p>Two things happen quietly when you press <b>Create product &rarr;</b>. If the material word contains
             &ldquo;colour&rdquo; (e.g. <em>Slat Colour</em>) the separate <b>Colour</b> sub-field is switched off, so you don&rsquo;t end up with
             two colour boxes. And every product made here starts on the <b>supplier price-list</b> pricing model &mdash; the one your
             trade-account discounts are worked out from. See <em>Pricing: source, markup &amp; mode</em>.</p>
          <div class="oops"><b>If it won&rsquo;t save:</b> <code>Product name is required.</code> &middot;
             <code>Product name too long (max 150).</code> &middot; <code>Option label too long (max 40).</code></div>

          <p class="prose"><b>Step 2 &mdash; Systems.</b> A <b>system</b> is the operating mechanism or the physical variant &mdash;
             <em>Standard</em>, <em>Motorised</em>, <em>Battery</em>, <em>Pelmet</em>, or a size like <em>35mm String</em> / <em>50mm Tape</em>.
             Most products need one or two.</p>
          <ul class="steps">
            <li>It is a <b>paste box, not a one-at-a-time field</b>: a six-row textarea labelled <em>One system per line (paste from Excel
                or type)</em>. One line = one system. Press <b>&plus; Add</b>.</li>
            <li>Paste the same name twice and it tells you what it skipped: <code>Added 2 systems. Skipped 1 (already on this product).</code>
                Before you add anything the list reads <code>No systems yet &mdash; add at least one below.</code> An empty box is
                refused with <code>No system names &mdash; paste at least one name into the box.</code></li>
            <li>Each system in the list has a red <b>Remove</b> that warns you first: <em>Remove the &ldquo;Standard&rdquo; system? Any price
                tables it has go too.</em></li>
            <li><b>Continue &rarr;</b> is greyed out until there is at least one system &mdash; price tables hang off systems, so with none
                there is nothing to price against. Push past it anyway and the server says
                <code>Add at least one system before continuing.</code></li>
          </ul>

          <p class="prose"><b>Step 3 &mdash; Fabrics</b> (or Colours, or Finishes &mdash; the heading uses your word).</p>
          <ul class="steps">
            <li><b>Band first.</b> A band is the <b>price group</b>: every fabric in Band A shares one price grid, so your standard range
                and your premium range are different bands. <code>A</code>/<code>B</code>/<code>C</code> off a supplier list is fine, so are
                words like <code>Standard</code>/<code>Special</code>. Type <em>Band A</em> if you like &mdash; a leading &ldquo;Band &rdquo; is
                stripped for you. The box <b>suggests bands you have already used</b> on this product, so you pick instead of retyping.</li>
            <li><b>Available on</b> only appears once the product has <b>two or more systems</b>: <em>All systems (universal)</em> or
                <em>&lt;System&gt; only</em>. Leave it universal unless a colour genuinely comes on one system alone.</li>
            <li><b>The paste box</b> takes one name per line <b>or</b> comma-separated &mdash; whichever your list is in. Or use
                <b>[System Name] headers</b> to load several systems in one go:<br>
                <code>[Standard]</code> / <code>Linen Oyster</code> / <code>Linen Slate</code> / <code>[Motorised]</code> /
                <code>Blackout Ivory</code>. The header has to be the system&rsquo;s name as you typed it on step 2. If some colours
                went in but a header didn&rsquo;t match anything, the success line names the offender on the end:
                <code>Unknown system names: "Motorized".</code> If <em>nothing</em> matched, nothing is added at all &mdash; see the
                red message below.</li>
            <li><b>Three ways out of typing</b>, each its own blue card: <b>Copy from another product &rarr;</b> (the whole range, bands and
                all), <b>Price tables first &rarr;</b> (import the grid, then come back and pick the bands it created), and
                <b>No fabrics &mdash; skip &rarr;</b> for a headrail, track or spares &mdash; it asks <em>Mark this as a no-fabric product and
                skip straight to price tables?</em> and then jumps you to step 4. Plus <b>&#128218; Import from Fabric Library</b> and
                <b>&#128196; Import from spreadsheet</b>, both of which bring you straight back here.</li>
            <li>Above the list sits the count &mdash; <em>42 fabrics added</em> &mdash; with a red <b>Delete all</b> guarded by
                <em>Delete ALL 42 fabrics on this product? Cannot be undone.</em> Each row shows its band, a colour if one was set
                elsewhere (the wizard doesn&rsquo;t ask for one), and either a purple <em>&lt;System&gt; only</em> or a faint
                <em>all systems</em> &mdash; that last pair only once the product has two or more systems.</li>
          </ul>
          <div class="oops"><b>If it won&rsquo;t add:</b> <code>Band code is required.</code> &middot;
             <code>Band code too long (max 60).</code> &middot; <code>No names to add &mdash; paste at least one name into the box.</code> &middot;
             <code>Nothing added. Check the paste &mdash; colours need a [System Name] header above them, or pick a system in the dropdown.</code>
             (that last one is the mistyped-header case &mdash; every colour sat under a header the product doesn&rsquo;t have)
             &middot; <code>Add at least one fabric before continuing.</code></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The band trap &mdash; the one thing that catches everybody.</b> The band on
             the <b>fabric</b> and the band on the <b>price table</b> must be the same word. Put your fabrics on &ldquo;Plain&rdquo; but import a
             table for &ldquo;Special effects&rdquo; and the quote builder says, word for word:
             <code>No price table for Roller Blind band Plain on system &lsquo;Standard&rsquo;.</code> The two are matched as plain text, so
             make them <b>identical</b> &mdash; same spelling, same spacing, same capitals. Two ways to never hit it: pick from the band box&rsquo;s suggestion list, or take the
             <b>Price tables first &rarr;</b> route so the bands exist before you name any fabric.</div></div>

          <p class="prose"><b>Step 4 &mdash; Price tables. There is no price grid on this screen.</b> It is a <b>checklist</b> of every
             system-and-band combination the product needs.</p>
          <ul class="steps">
            <li><b>The tile at the top</b> tells you where you stand &mdash; amber <b>&ldquo;One thing left &mdash; price tables&rdquo;</b> with
                the exact reason (<code>Create the price tables below &mdash; one per band &times; system&hellip;</code>, then
                <code>2 of 4 filled in&hellip;</code>, or <code>No bands yet &mdash; that&rsquo;s fine&hellip;</code>, or
                <code>Your fabrics don&rsquo;t have band codes yet&hellip;</code>, or <code>This product has no systems yet&hellip;</code>), and a
                button straight to whatever is missing. Green <b>&#127881; All set</b> when it is done.</li>
            <li><b>The quick road: import.</b> A blue card offers <b>Import &lt;System&gt; &rarr;</b> for each system that still needs prices
                (or <b>Import width prices &rarr;</b> / <b>Import rates &rarr;</b> if you ticked width-only or per-slat on step 1). The importer
                <b>creates and fills</b> the tables in one go. The card disappears once nothing needs importing.</li>
            <li><b>Typing them yourself?</b> The amber card lists the missing <em>&lt;System&gt; &plus; Band A</em> rows and
                <b>Create all 4 empty price tables</b> makes the lot: <em>One click &mdash; the empty grids appear instantly. Leave any out
                that don&rsquo;t apply to what you sell.</em> (With only one combination missing the button reads
                <b>Create the empty price table</b> instead.)</li>
            <li><b>The list below</b> is every table you have: a green &check; with <em>&middot; 48 cells</em>, or an amber <b>Empty</b> pill.
                <b>Empty means no price at all at quote time.</b> <b>Fill in</b> opens the grid to type or paste prices; <b>Edit</b> reopens a
                filled one; <b>Remove</b> warns <em>Its 48 price cells will be wiped too.</em></li>
            <li><b>Deletions stick.</b> Stub creation is always something you click &mdash; a table you delete because you don&rsquo;t sell that
                combination will <b>not</b> quietly reappear next time you open step 4.</li>
          </ul>

          <p><b>Finishing, and coming back.</b> The footer has <b>&larr; Back to fabrics</b> and <b>Open product edit page &rarr;</b> (which
             reads <b>Finish later &mdash; open product &rarr;</b> while anything is outstanding). You never have to do it in one sitting:
             close the tab, and next time you open the wizard from the Products page &mdash; <b>step 1, before you have named anything
             new</b> &mdash; an amber <b>&ldquo;Continue an in-progress product&rdquo;</b> card sits above the name box listing what is
             unfinished &mdash; <em>Vertical Blind &mdash; needs fabric &plus; price table</em> &mdash; with a
             <b>Resume &rarr;</b> button that drops you at the exact step that product needs next. (It is only on that opening screen:
             once you are inside a product the card is gone.)</p>

          <p><b>What the wizard deliberately leaves out.</b> There is no options step, no supplier, no discount and no markup &mdash; those all
             live on the <b>product edit page</b>, and <b>Open product edit page &rarr;</b> is the door to them. Carry on with
             <em>Adding options</em>, <em>Pricing: source, markup &amp; mode</em>, <em>Building price tables</em>,
             <em>Adding fabrics &mdash; paste, Excel or library</em> and <em>Combining products into one</em>.</p>',
        'script'  => [
            ['0:00', 'Step 1 — name + the material word.',        'The wizard builds a product in four steps. A product is one type of blind — a Roller, a Vertical, a Roman, a Metal Venetian. Give it the name your salesperson will pick in the quote builder. Underneath, the material word is already filled in as Fabric. Overtype it for a venetian — Colour for metal, Finish for wood — and that becomes the word the rest of the app uses for this product.', 1],
            ['0:20', 'The four tickboxes, full size.',            'Now the four tickboxes, and for an ordinary blind you leave all four alone. No fabrics is for a headrail, a track or spares — it skips the fabric step and prices on system and size alone. Width only hides the Drop box at quote time. Per slat gives you a drop-to-rate table, times the number of slats. Per square metre is for shutters. The last three are one or the other, never together. Then press Create product, and step one ticks green.', 2],
            ['0:45', 'Step 2 — systems, pasted one per line.',    'A system is the operating mechanism, or the physical variant — Standard, Motorised, or a size like thirty-five millimetre String. Most products need one or two. This is a paste box, not a one-at-a-time field: one line per system, straight out of Excel. Paste the same name twice and it tells you — added two systems, skipped one, already on this product. Continue stays greyed out until there is at least one, because price tables hang off systems.', 3],
            ['1:10', 'Step 3 — band first, then the names.',      'Fabrics next, and the band comes first. A band is the price group: every fabric in Band A shares one price grid, so your standard range and your premium range are different bands. A, B and C off a supplier list is fine, so are words like Standard and Special. Type Band A if you like — it keeps just the A. The box suggests bands you have used before, so you pick instead of retyping. Then the names, one per line or separated by commas.', 4],
            ['1:35', 'Three escape hatches, and the paste slip.', 'Three ways out of typing. If another product already has this exact range, copy it across, bands and all. If your colours only come on certain systems, put the system name in square brackets above each batch and paste the whole lot in one go. The catch: the name in the brackets has to be the system name you typed on step two. Get it wrong — Standard Roller instead of Standard, Motorized with a zed instead of Motorised — and every colour underneath is skipped, so nothing is added and it says so in red. Correct the two headers, paste again, and twelve go in across two systems. And if this is a headrail or spares with no fabric at all, the no fabrics skip button takes you straight to prices. There is also a price tables first shortcut, which imports the grid so the band box can suggest the bands back to you.', 5],
            ['2:05', 'Step 4 — a checklist, not a grid.',         'Now the important bit. Step four does not show you a price grid. It is a checklist of every system and band combination the product needs. If you have the supplier spreadsheet, import it one system at a time and it creates and fills the tables in one go — that is the quick road. If you are typing them yourself, one click makes all the empty grids. Leave out any combination you do not actually sell — and once you delete one, it stays deleted.', 6],
            ['2:28', 'Fill them in — and the band trap.',         'Empty tables produce no price at all; that is what the Empty pill is telling you. Fill in opens the grid where you type or paste the prices. Then the one thing that catches everybody: the band on the fabric and the band on the price table must be the same word. Put your fabrics on Plain but import a table for Special effects, and the quote builder says, word for word, no price table for Roller Blind band Plain on system Standard. The two words are compared as they are typed, so make them identical — spelling, spacing and capitals alike.', 7],
            ['2:52', 'All set — and what is left to do.',         'The green tile counts back exactly what you built. Step four only ticks when every table is filled and nothing is missing, so the stepper is an honest progress bar. And you never have to do it in one sitting — come back and the amber card at the top lists what is unfinished and what each one still needs. Last thing: the wizard leaves out options, the supplier, your discount and your markup. Those live on the product edit page, and the finish button is the door to them.', 8],
        ],
];
