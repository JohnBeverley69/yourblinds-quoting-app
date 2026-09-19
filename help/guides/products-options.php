<?php
declare(strict_types=1);

/**
 * Guide: products-options
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Adding options',
        'eyebrow' => 'Products',
        'blurb'   => 'The full options walk-through — simple options, choice pricing and scoping, and submenu (sub-)options two ways, through to the quote builder.',
        'lede'    => 'Options are the <b>extras</b> your salesperson picks for each blind &mdash; <em>Control type</em>, <em>Bottom weight</em>,
                      <em>Motor type</em>, <em>Bracket colour</em>. This covers the lot: <b>simple options</b> and their priced <b>choices</b>,
                      how to <b>scope</b> a choice to systems / bands / fabrics, and <b>submenu options</b> &mdash; the two ways to make one
                      option appear only when another is picked, and how to <b>nest</b> them &mdash; through to what the salesperson finally sees.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.8rem; margin:0 0 .75rem; }
          /* a statically-filled field (looks like .fld .box, but always shows its value) */
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .orow{ display:flex; gap:1.3rem; margin-top:.5rem; }
          .gd .chkline{ display:inline-flex; align-items:center; gap:.4rem; font-size:.76rem; color:var(--ink); }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB, .gd .stage[data-step="3"] .scB{ display:block; }
          .gd .stage[data-step="4"] .scC{ display:block; }
          .gd .stage[data-step="5"] .scD{ display:block; }
          .gd .stage[data-step="6"] .scE{ display:block; }
          .gd .stage[data-step="7"] .scF{ display:block; }
          .gd .stage[data-step="8"] .scG{ display:block; }

          /* scene C — choice scoping + image */
          .gd .scoperow{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin:.28rem 0; font-size:.74rem; }
          .gd .scopelbl{ width:5.2rem; color:var(--faint); font-weight:700; font-size:.62rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .chip{ border:1px solid var(--line); border-radius:999px; padding:.08rem .5rem; color:var(--soft); background:var(--panel); }
          .gd .chip.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .muted{ color:var(--faint); font-size:.7rem; }
          .gd .thumb{ width:1.4rem; height:1.4rem; border:1px solid var(--line); border-radius:5px; display:inline-flex; align-items:center; justify-content:center; background:var(--panel); }

          /* scene E — nested sub-options tree */
          .gd .tree{ border:1px solid var(--line); border-radius:9px; padding:.5rem .65rem; background:var(--panel); max-width:24rem; font-size:.76rem; }
          .gd .tnode{ padding:.14rem 0; color:var(--ink); }
          .gd .tkids{ margin-left:.9rem; border-left:2px solid var(--line); padding-left:.6rem; }
          .gd .tmuted{ color:var(--faint); font-size:.66rem; }
          .gd .tsub, .gd .tsub2{ color:var(--soft); }
          .gd .tbadge{ font-size:.58rem; background:var(--accent-wash,#eef2ff); color:var(--accent); border-radius:999px; padding:.02rem .38rem; margin-left:.2rem; font-weight:700; }

          /* add-option form */
          .gd .exlabel{ font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:0 0 .45rem; }
          .gd .exhelp{ font-size:.68rem; color:var(--faint); margin:.3rem 0 0; }
          .gd .addbtn{ margin-top:.8rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.4rem .85rem; font-size:.8rem; font-weight:700; }

          /* choices grid */
          .gd .ogrid{ display:grid; grid-template-columns:1fr 3.4rem 2.4rem 2.4rem 2.4rem; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; max-width:23rem; }
          .gd .oc{ background:var(--surface); padding:.28rem .3rem; font-size:.68rem; color:var(--ink); text-align:center; }
          .gd .oc.l{ text-align:left; }
          .gd .oc.hd{ background:var(--panel); color:var(--faint); font-weight:700; font-size:.62rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .oc.newrow{ color:var(--faint); font-style:italic; }
          .gd .oflat{ opacity:0; transition:opacity .4s; }
          .gd .stage[data-step="3"] .oflat{ opacity:1; }
          .gd .stage[data-step="3"] .oc.flatcell{ box-shadow:inset 0 0 0 2px var(--accent); }
          .gd .otick{ display:inline-block; width:.85rem; height:.85rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:3px; position:relative; vertical-align:middle; }
          .gd .otick.on{ background:var(--accent); border-color:var(--accent); }
          .gd .otick.on::after{ content:"\2713"; color:#fff; font-size:.6rem; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; }
          .gd .gridcols{ font-size:.66rem; color:var(--faint); margin-top:.4rem; }

          /* appears-when list */
          .gd .awlist{ border:1px solid var(--line); border-radius:8px; padding:.4rem .55rem; background:var(--panel); max-width:20rem; }
          .gd .awrow{ display:flex; align-items:center; gap:.45rem; font-size:.74rem; color:var(--soft); padding:.16rem 0; }
          .gd .awrow.on{ color:var(--ink); font-weight:600; }

          /* number-input fieldset */
          .gd .nfield{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); max-width:22rem; }
          .gd .nlegend{ font-size:.7rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.4rem; }

          /* quote-builder preview */
          .gd .pvq{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:22rem; }
          .gd .pvqhd{ background:var(--panel); padding:.4rem .65rem; font-size:.7rem; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); }
          .gd .pvqbody{ padding:.55rem .65rem; display:flex; flex-direction:column; gap:.5rem; }
          .gd .pvqrow label{ display:block; font-size:.68rem; font-weight:600; color:var(--soft); margin-bottom:.18rem; }
          .gd .pvqrow .req{ color:#b91c1c; }
          .gd .pvqchild{ margin-left:.7rem; padding-left:.55rem; border-left:2px solid var(--line); }
          .gd .pvqsum{ margin-top:.2rem; background:#d1fae5; color:#065f46; border-radius:7px; padding:.4rem .6rem; font-size:.74rem; font-weight:600; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / options</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Options &mdash; Roller Blind</div>

                <!-- Scene A: add a simple option -->
                <div class="osc scA">
                  <p class="exlabel">Add an option</p>
                  <div class="fld"><label>Name</label><div class="box f1"><span class="ph">e.g. Control type</span><span class="val">Control type</span></div></div>
                  <div class="orow">
                    <span class="chkline"><span class="tick on">&check;</span> Required</span>
                    <span class="chkline"><span class="tick">&check;</span> Allow multiple choices</span>
                  </div>
                  <p class="exhelp"><b>A simple option</b> &mdash; one plain pick. <b>Required</b> = must choose one. <b>Allow multiple</b> = tick-boxes (any combination). Examples: Control type, Control side, Lining, Bracket colour, Bottom weight.</p>
                  <div class="addbtn">Add option</div>
                </div>

                <!-- Scene B: choices grid + pricing -->
                <div class="osc scB">
                  <p class="exlabel">Choices for &ldquo;Control type&rdquo;</p>
                  <div class="ogrid">
                    <span class="oc hd l">Label</span><span class="oc hd">Flat &pound;</span><span class="oc hd">%</span><span class="oc hd">Default</span><span class="oc hd">Active</span>
                    <span class="oc l">Cord</span><span class="oc"></span><span class="oc"></span><span class="oc"><span class="otick on"></span></span><span class="oc"><span class="otick on"></span></span>
                    <span class="oc l">Motorised</span><span class="oc flatcell"><span class="oflat">120.00</span></span><span class="oc"></span><span class="oc"><span class="otick"></span></span><span class="oc"><span class="otick on"></span></span>
                    <span class="oc l newrow">Type new label and press Enter&hellip;</span><span class="oc"></span><span class="oc"></span><span class="oc"></span><span class="oc"></span>
                  </div>
                  <p class="gridcols">Prices <b>stack</b>: Flat &pound; + % + <b>&pound;/m</b> (Width / Drop / W+D / Perimeter) + <b>per-unit &times; Qty</b>. Plus columns for <b>Available on</b> (systems), <b>Bands</b> and a per-choice <b>width table</b>. Blank = free.</p>
                </div>

                <!-- Scene C: scope + picture a choice (full edit) -->
                <div class="osc scC">
                  <p class="exlabel">Choice &mdash; Motorised &middot; full edit (&hellip;)</p>
                  <div class="scoperow"><span class="scopelbl">Available on</span><span class="chip on">Motorised</span><span class="chip">Standard</span><span class="chip">Battery</span></div>
                  <div class="scoperow"><span class="scopelbl">Bands</span><span class="chip">A</span><span class="chip">B</span><span class="muted">blank = all bands</span></div>
                  <div class="scoperow"><span class="scopelbl">Fabrics</span><span class="muted">Snow, Cool White&hellip; &mdash; or blank for every fabric</span></div>
                  <div class="scoperow"><span class="scopelbl">Image</span><span class="thumb">&#128247;</span><span class="muted">thumbnail the customer sees</span></div>
                  <p class="exhelp">Fine-tune a choice: limit it to certain <b>systems</b>, <b>bands</b> or even specific <b>fabrics</b>, and give it a picture. Leave scoping blank = shows everywhere.</p>
                </div>

                <!-- Scene D: submenu way 1 — Appears when -->
                <div class="osc scD">
                  <p class="exlabel">Submenu &mdash; way 1: &ldquo;Appears when&rdquo;</p>
                  <div class="fld"><label>New option name</label><div class="boxv">Motor type</div></div>
                  <p class="exlabel" style="margin:.6rem 0 .3rem">Appears when (optional)</p>
                  <div class="awlist">
                    <div class="awrow"><span class="otick"></span> Control type = Cord</div>
                    <div class="awrow on"><span class="otick on"></span> Control type = Motorised</div>
                  </div>
                  <p class="exhelp">Gate a <b>whole option</b> to one or more parent choices &mdash; it shows when <b>any</b> ticked choice is picked. Tick none = always visible.</p>
                </div>

                <!-- Scene E: submenu way 2 — nested Sub-options -->
                <div class="osc scE">
                  <p class="exlabel">Submenu &mdash; way 2: nested &ldquo;Sub-options&rdquo;</p>
                  <div class="tree">
                    <div class="tnode">Control type <span class="tmuted">option</span></div>
                    <div class="tkids">
                      <div class="tnode">Motorised <span class="tmuted">choice</span></div>
                      <div class="tkids">
                        <div class="tnode tsub">&#8627; Motor type <span class="tbadge">sub-option</span></div>
                        <div class="tnode tsub">&#8627; Remote channels <span class="tbadge">sub-option</span></div>
                        <div class="tkids"><div class="tnode tsub2">&#8627; Wall bracket <span class="tbadge">sub-of-sub</span></div></div>
                      </div>
                    </div>
                  </div>
                  <p class="exhelp">Right inside the option, the <b>Sub-options</b> panel adds a follow-on option gated to a single choice &mdash; and a sub-option can have its <em>own</em> choices and sub-options, so you can <b>nest as deep</b> as the product needs.</p>
                </div>

                <!-- Scene F: number input on a choice -->
                <div class="osc scF">
                  <p class="exlabel">Choice &mdash; Motorised</p>
                  <div class="nfield">
                    <div class="nlegend">Ask for a number on this choice</div>
                    <div class="chkline" style="margin-bottom:.5rem"><span class="tick on">&check;</span> Show a number input when this choice is picked</div>
                    <div class="fld"><label>What to call this field</label><div class="boxv">Cable length (mm)</div></div>
                    <p class="exhelp">Also available on the option itself. The value is recorded on the line for the supplier docs &mdash; it doesn&rsquo;t change the price.</p>
                  </div>
                </div>

                <!-- Scene G: quote-builder preview -->
                <div class="osc scG">
                  <p class="exlabel">What the salesperson sees</p>
                  <div class="pvq">
                    <div class="pvqhd">&#128065; Quote builder</div>
                    <div class="pvqbody">
                      <div class="pvqrow"><label>Control type <span class="req">*</span></label><div class="selectbox">Motorised</div></div>
                      <div class="pvqchild">
                        <div class="pvqrow"><label>Motor type</label><div class="selectbox">Tubular</div></div>
                        <div class="pvqrow"><label>Cable length (mm)</label><div class="boxv">3000</div></div>
                      </div>
                      <div class="pvqsum">Options: +&pound;120.00</div>
                    </div>
                  </div>
                  <p class="exhelp">Pick <b>Cord</b> and none of this shows; pick <b>Motorised</b> and the sub-options slide in. Required = red <b>*</b>; multi-choice = tick-boxes; thumbnails and measurement boxes show too.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Simple option &mdash; name it, mark it required.</b>
                  <b class="c2"><span class="n">2</span> Add its choices &mdash; set the default.</b>
                  <b class="c3"><span class="n">3</span> Price a choice, right in the grid.</b>
                  <b class="c4"><span class="n">4</span> Scope &amp; picture a choice.</b>
                  <b class="c5"><span class="n">5</span> Submenu, way 1 &mdash; &ldquo;Appears when&rdquo;.</b>
                  <b class="c6"><span class="n">6</span> Submenu, way 2 &mdash; nested Sub-options.</b>
                  <b class="c7"><span class="n">7</span> Capture a measurement alongside.</b>
                  <b class="c8 good"><span class="n">8</span> That&rsquo;s what the salesperson sees.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Options</b> (the code and some tooltips call them &ldquo;extras&rdquo;) are the add-ons a salesperson picks per blind &mdash;
             <em>Control type</em>, <em>Bottom weight</em>, <em>Motor type</em>, <em>Bracket colour</em>. They&rsquo;re separate from a
             product&rsquo;s <b>fabrics</b> (its colour/material choices). Manage them on the product&rsquo;s <b>Edit</b> page &mdash; the
             <b>Options</b> section &mdash; or open <b>Full manage &raquo;</b>. An option has <b>no price of its own</b>; the price always
             lives on its <b>choices</b>.</p>

          <p class="prose"><b>1) Simple options</b> &mdash; a single, flat pick.</p>
          <ul class="steps">
            <li><b>Add the option.</b> Give it a <b>Name</b> (e.g. <em>Control type</em>). <b>Required</b> means the customer must pick one
                (on by default). <b>Allow multiple choices</b> turns the picker into <b>tick-boxes</b> so they can pick any combination
                (e.g. two trims at once).</li>
            <li><b>Add its choices.</b> The choices editor is a <b>live grid</b> &mdash; type a label in the bottom row and press <b>Enter</b>,
                or use <b>+ Bulk add</b> (one label per line, e.g. <em>Cord</em> then <em>Motorised</em>). No Save button: each cell saves as you
                Tab or click away. Tick <b>Default</b> for the one that&rsquo;s pre-selected, and <b>Active</b> to show/hide it.</li>
            <li><b>Price a choice.</b> Each choice can <b>stack</b> up to four price modes, all combined: <b>Flat &pound;</b>, <b>Percent %</b>,
                <b>Per metre &pound;/m</b> (measured along Width / Drop / Width+Drop / Perimeter), and <b>Price per unit</b> (&times; a Quantity
                box &mdash; for brackets and fixings). A choice can also have its own <b>width-based price table</b> (the &ldquo;&hellip;&rdquo;
                link on the row). Leave them all blank for a <b>free</b> choice.</li>
            <li><b>Scope &amp; picture a choice.</b> Open a choice in full (the <b>&ldquo;&hellip;&rdquo;</b> link) to limit it to certain
                <b>systems</b>, <b>bands</b>, or even specific <b>fabrics</b> &mdash; and add a <b>thumbnail image</b> the customer sees when they
                pick it. Leave the scoping blank and the choice shows <b>everywhere</b>.</li>
          </ul>

          <p class="prose"><b>2) Submenu options</b> (&ldquo;sub-options&rdquo;) &mdash; an option that only appears after a particular choice
             is picked, e.g. <em>Motor type</em> only once <em>Control type = Motorised</em>. There are <b>two ways</b> to build them &mdash;
             they do the same thing under the bonnet, so use whichever reads more naturally:</p>
          <ul class="steps">
            <li><b>Way 1 &mdash; &ldquo;Appears when&rdquo; on the option.</b> When you add (or edit) an option, tick one or more parent
                <b>choices</b> under <b>Appears when</b>. The whole option shows in the quote builder when <b>any</b> ticked choice is selected.
                Tick <b>none</b> = always visible. Ticking several parents is how one follow-on option serves more than one trigger.</li>
            <li><b>Way 2 &mdash; the &ldquo;Sub-options&rdquo; panel inside an option.</b> Open an option and scroll to <b>Sub-options</b>: add a
                follow-on option right there, gated to the choice(s) you tick. It&rsquo;s the same gating, created in context.</li>
            <li><b>Nest as deep as you need.</b> A sub-option is itself a full option &mdash; it can have its <b>own choices, prices, and its own
                sub-options</b>. So <em>Control type &rarr; Motorised &rarr; Motor type &rarr; Remote channels &rarr; Wall bracket</em> is
                perfectly valid; each level only appears once the one above it is chosen.</li>
            <li><b>Remove one safely.</b> Deleting a sub-option only removes that follow-on option and its gating &mdash; the parent option and
                its choices are untouched.</li>
          </ul>

          <p class="prose"><b>3) Capture a measurement</b> alongside a pick.</p>
          <ul class="steps">
            <li>Tick <b>&ldquo;Also show a number input&rdquo;</b> (on the option) or <b>&ldquo;Ask for a number on this choice&rdquo;</b> and name
                it (e.g. <em>Cable length (mm)</em>, <em>Top offset (mm)</em>). The salesperson types a value; it&rsquo;s recorded on the line
                for the supplier docs but <b>doesn&rsquo;t change the price</b>.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Simple vs submenu, at a glance.</b> A <b>simple option</b> is always visible (unless
             you scope its choices). A <b>submenu option</b> is a normal option that you&rsquo;ve <b>gated</b> to a parent choice &mdash; nothing
             else about it is special. If a follow-on option isn&rsquo;t showing in the quote builder, check its <b>Appears when</b> parents are
             <b>active</b> choices that actually get picked.</div></div>

          <div class="oops"><b>Common trips &mdash; and what it says:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>Typed text in a price cell &rarr; <code>Must be a number.</code> (also <code>Flat surcharge must be a number.</code> on the full edit page).</li>
               <li>Built a sub-option but ticked no parent &rarr; <code>Pick at least one parent choice to gate the sub-option.</code></li>
               <li>Left the name empty &rarr; <code>Name is required.</code> / <code>Sub-option name is required.</code></li>
             </ul></div>

          <p>Everything flows straight to the <b>quote builder</b>: each option shows as a dropdown (or tick-boxes if multiple), required
             ones get a red <b>*</b>, the picked choice&rsquo;s <b>thumbnail</b> and any <b>measurement box</b> appear, <b>sub-options slide in</b>
             when their trigger is chosen (and their sub-options after that), and the surcharges land on the line. Use <b>&#128065; Live preview</b>
             on the product page to check the whole cascade before it goes live.</p>',
        'script'  => [
            ['0:00', 'Simple option: Control type, required.',       'Options are the extras your salesperson picks — control type, a motor, a bottom weight. Start with a simple one: give it a name, like Control type, and mark it required so they must choose.', 1],
            ['0:11', 'Choices Cord + Motorised; Cord default.',      'Then add its choices — the values to pick from. Type Cord, then Motorised, one per line, and tick the one that should be pre-selected as the default. There is no save button — each cell saves as you tab away.', 2],
            ['0:24', 'Flat £120 on Motorised.',                     'Each choice can carry its own price, right in the grid. Put a flat surcharge on Motorised — a hundred and twenty pounds. You can stack a percentage, a price per metre, or a per-unit charge on top.', 3],
            ['0:36', 'Scope a choice to systems, bands, fabrics.',   'Open a choice in full to fine-tune it: limit it to certain systems, bands, or even specific fabrics, and add a thumbnail the customer sees. Leave it blank and the choice shows everywhere.', 4],
            ['0:49', 'Submenu one: Motor type appears when Motorised.', 'Now the submenus. The first way: add a second option, Motor type, and set Appears when Control type is Motorised. The whole option only shows once Motorised is picked.', 5],
            ['1:02', 'Submenu two: nested Sub-options.',            'The second way lives right inside the option — the Sub-options panel gates a follow-on option to a single choice. And a sub-option can have its own choices and sub-options, so you can nest as deep as the product needs.', 6],
            ['1:16', 'Number box: Cable length (mm).',              'Need a measurement? Turn on a number box — Cable length in millimetres — and the salesperson types it alongside. It is recorded on the line, but does not change the price.', 7],
            ['1:28', 'Quote builder shows it all.',                 'And here is what they see: pick Motorised, and Motor type, the cable-length box and any sub-options slide in, with the surcharge added. Pick Cord, and none of it shows.', 8],
        ],
];
