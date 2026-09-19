<?php
declare(strict_types=1);

/**
 * Guide: products-combine
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Combining products into one',
        'eyebrow' => 'Products',
        'blurb'   => 'Fold 15/25/35mm into one product, each size a system — and get the master right first time.',
        'lede'    => 'A separate product for every slat size clutters the Product list and means three lots of upkeep &mdash; three sets of
                      fabrics, three sets of price tables, three things to remember. <b>Combine</b> folds them into <b>one</b> product where
                      the size is a <b>system</b>, carrying the fabrics, price tables and markups across with it. The ones folded in are
                      switched <b>off</b>, not deleted. There is <b>no un-combine</b>, so it pays to read this page first.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .7rem; line-height:1.45; }
          .gd .crumb{ font-size:.64rem; color:var(--faint); margin:0 0 .3rem; letter-spacing:.02em; }

          /* ---- four screens in one stage ---- */
          .gd .scr{ display:none; }
          .gd .stage[data-step="0"] .scr-list, .gd .stage[data-step="1"] .scr-list,
          .gd .stage[data-step="2"] .scr-list{ display:block; }
          .gd .stage[data-step="3"] .scr-form, .gd .stage[data-step="4"] .scr-form,
          .gd .stage[data-step="5"] .scr-form, .gd .stage[data-step="6"] .scr-form{ display:block; }
          .gd .stage[data-step="7"] .scr-after{ display:block; }
          .gd .stage[data-step="8"] .scr-back{ display:block; }

          /* ---- screen 1: the Products list ---- */
          .gd .ghead{ display:flex; align-items:center; gap:.35rem; font-size:.72rem; font-weight:700; color:var(--ink); margin:0 0 .3rem; }
          .gd .ghead .cart{ color:var(--faint); font-size:.6rem; }
          .gd .ghead .gcount{ background:var(--panel); border:1px solid var(--line); border-radius:999px; padding:.01rem .4rem; font-size:.58rem; color:var(--soft); font-weight:700; }
          .gd .ptab{ border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.68rem; }
          .gd .pth, .gd .ptr{ display:grid; grid-template-columns:1rem .75rem 1fr 4.5rem 2rem 2.1rem 3.2rem 2.1rem 4.4rem 2.9rem 4.9rem; gap:.26rem; align-items:center; padding:.3rem .45rem; }
          .gd .pth{ background:var(--panel); font-size:.5rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; }
          .gd .ptr{ border-top:1px solid var(--line); color:var(--ink); position:relative; }
          .gd .ptab .tick{ width:14px; height:14px; font-size:.56rem; }
          .gd .drag{ color:var(--faint); font-size:.68rem; letter-spacing:-.08em; }
          .gd .st{ justify-self:start; background:#d1fae5; color:#065f46; border-radius:999px; padding:.04rem .4rem; font-size:.56rem; font-weight:700; white-space:nowrap; }
          .gd .st.off{ background:var(--line); color:var(--soft); }
          .gd .n2{ text-align:right; font-variant-numeric:tabular-nums; color:var(--soft); }
          .gd .ptab .selectbox{ min-width:0; width:100%; font-size:.56rem; padding:.1rem .28rem; gap:.2rem; border-radius:5px; }
          .gd .ptab .selectbox::after{ font-size:.5rem; }
          .gd .upd{ color:var(--faint); font-size:.58rem; white-space:nowrap; }
          .gd .racts{ display:flex; gap:.28rem; font-size:.55rem; color:var(--accent); white-space:nowrap; }
          .gd .mstag{ display:none; position:absolute; right:.45rem; top:-.5rem; background:var(--accent); color:#fff; font-size:.54rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; border-radius:999px; padding:.03rem .45rem; }
          .gd .stage[data-step="2"] .ptr.r1 .mstag{ display:inline-block; }
          .gd .stage[data-step="2"] .ptr.r1{ box-shadow:inset 0 0 0 2px var(--accent); border-radius:6px; }
          .gd .stage[data-step="2"] .ptr.ven .tick{ background:var(--accent); color:#fff; }

          /* ---- the bulk bar under the tables ---- */
          .gd .bbar{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-top:.55rem; font-size:.72rem; }
          .gd .bcount{ color:var(--faint); }
          .gd .bsel{ display:none; }
          .gd .stage[data-step="2"] .bnone{ display:none; }
          .gd .stage[data-step="2"] .bsel{ display:inline; color:var(--ink); font-weight:700; }
          .gd .bbtn{ border:1px solid var(--line); border-radius:7px; padding:.24rem .6rem; background:var(--panel); color:var(--faint); font-weight:600; }
          .gd .stage[data-step="2"] .bbtn.cmb{ color:var(--ink); background:var(--surface); border-color:var(--border-strong,#c7ccd4); box-shadow:0 0 0 3px var(--accent-wash); }
          /* the real bulk Delete is btn btn-danger &mdash; red, not neutral */
          .gd .bbtn.danger{ border-color:var(--err); background:var(--err-wash); color:var(--err); }
          .gd .bclear{ display:none; color:var(--accent); text-decoration:underline; }
          .gd .stage[data-step="2"] .bclear{ display:inline; }

          /* ---- screen 2: the Combine page ---- */
          .gd .bluep{ border:1px solid #bae6fd; background:#f0f9ff; border-radius:9px; padding:.5rem .7rem; margin:.15rem 0 .7rem; font-size:.7rem; line-height:1.5; color:#0c4a6e; }
          .gd .stage[data-step="3"] .bluep{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .errb{ display:none; margin:0 0 .65rem; }
          .gd .stage[data-step="6"] .errb{ display:flex; }

          /* master-name field: filled by hand at step 4 (f1 would fill on the list screen) */
          .gd .stage[data-step="4"] .mname .ph, .gd .stage[data-step="5"] .mname .ph,
          .gd .stage[data-step="6"] .mname .ph{ opacity:0; }
          .gd .stage[data-step="4"] .mname .val, .gd .stage[data-step="5"] .mname .val,
          .gd .stage[data-step="6"] .mname .val{ opacity:1; }
          .gd .stage[data-step="4"] .mname{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="4"] .mname .val{ animation:gdRoll .8s ease-out both; }

          .gd .ctable{ margin-top:.75rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.76rem; }
          .gd .ct-head, .gd .ct-row{ display:grid; grid-template-columns:1.45fr 1.25fr 3rem; gap:.5rem; padding:.34rem .55rem; align-items:center; }
          .gd .ct-head{ background:var(--panel); font-size:.56rem; text-transform:uppercase; letter-spacing:.045em; color:var(--faint); font-weight:700; }
          .gd .ct-head .fabh, .gd .ct-row .fabn{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .ct-row{ border-top:1px solid var(--line); color:var(--ink); }
          .gd .masterpill{ display:inline-block; margin-left:.35rem; padding:.03rem .45rem; border-radius:999px; background:#0a58ca; color:#fff; font-size:.55rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
          .gd .boxv{ height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; background:var(--surface); display:flex; align-items:center; padding:0 .45rem; font-size:.76rem; color:var(--ink); overflow:hidden; }
          .gd .stage[data-step="5"] .ct-row .boxv{ box-shadow:0 0 0 3px var(--accent-wash); border-color:var(--accent); }
          .gd .stage[data-step="5"] .ct-row .fabn{ color:var(--ink); font-weight:700; }
          .gd .acts{ display:flex; align-items:center; gap:.6rem; }
          .gd .ghost{ border:1px solid var(--line); border-radius:8px; padding:.36rem .75rem; font-size:.78rem; font-weight:600; color:var(--accent); background:transparent; }

          /* ---- screen 3: the master&rsquo;s Edit page (collapsible cat-sections) ---- */
          .gd .catsec{ border:1px solid var(--line); border-radius:9px; background:var(--surface); margin:.55rem 0; overflow:hidden; }
          .gd .catsum{ display:flex; align-items:center; gap:.4rem; background:var(--panel); padding:.34rem .6rem; font-size:.78rem; font-weight:700; color:var(--ink); }
          .gd .catsum .cart{ color:var(--faint); font-size:.6rem; }
          .gd .catsum .count{ color:var(--faint); font-weight:600; }
          .gd .catsum .acts2{ margin-left:auto; display:flex; gap:.5rem; color:var(--accent); font-size:.6rem; font-weight:600; }
          .gd .catbody{ padding:.2rem .6rem .5rem; }
          .gd .irow{ display:grid; grid-template-columns:.8rem 1fr 5.6rem 2.6rem; gap:.4rem; align-items:center; padding:.28rem 0; border-top:1px solid var(--line); font-size:.74rem; color:var(--ink); }
          .gd .irow:first-child{ border-top:0; }
          .gd .irow .lnk{ color:var(--accent); }
          .gd .irow .ra{ text-align:right; color:var(--accent); font-size:.66rem; }
          .gd .pdef{ display:inline-block; margin-left:.3rem; padding:.02rem .42rem; border-radius:999px; background:var(--accent); color:#fff; font-size:.54rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .ptbl{ display:inline-block; padding:.02rem .42rem; border-radius:999px; border:1px solid var(--line); background:var(--panel); color:var(--soft); font-size:.58rem; font-weight:600; white-space:nowrap; }
          .gd .qadd{ display:flex; gap:.45rem; align-items:flex-end; margin-top:.5rem; }
          .gd .qadd .fld{ flex:1 1 auto; }
          .gd .qadd .box{ height:26px; font-size:.72rem; }
          .gd .qbtn{ border:1px solid var(--line); border-radius:7px; padding:.22rem .6rem; background:var(--panel); color:var(--soft); font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .chok{ display:flex; align-items:center; gap:.4rem; background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; border-radius:8px; padding:.3rem .6rem; font-size:.7rem; margin:.55rem 0; }
          .gd .chok b{ color:#065f46; }
          .gd .stage[data-step="7"] .catsec.sys{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---- screen 4: back on the Products list ---- */
          .gd .stage[data-step="8"] .ptr.off{ box-shadow:inset 0 0 0 2px var(--accent-wash); border-radius:6px; }
          .gd .stage[data-step="8"] .ptr.mst{ box-shadow:inset 0 0 0 2px var(--accent); border-radius:6px; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ============ screen 1: the Products list ============ -->
                <div class="scr scr-list">
                  <div class="card-t">Products</div>
                  <p class="ghead"><span class="cart">&#9654;</span> Ungrouped <span class="gcount">4</span></p>
                  <div class="ptab">
                    <div class="pth">
                      <span class="tick">&check;</span><span></span><span>Name</span><span>Status</span>
                      <span class="n2">Systems</span><span class="n2">Fabrics</span><span class="n2">Price tables</span>
                      <span class="n2">Options</span><span>Group</span><span>Updated</span><span></span>
                    </div>
                    <div class="ptr ven r1"><span class="mstag">master</span>
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>15mm Venetian</span>
                      <span class="st">&check; Ready</span><span class="n2">1</span><span class="n2">42</span><span class="n2">1</span>
                      <span class="n2">6</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">2 days ago</span>
                      <span class="racts"><span>Deactivate</span><span>Duplicate</span><span>Delete</span></span></div>
                    <div class="ptr ven">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>25mm Venetian</span>
                      <span class="st">&check; Ready</span><span class="n2">1</span><span class="n2">38</span><span class="n2">1</span>
                      <span class="n2">6</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">2 days ago</span>
                      <span class="racts"><span>Deactivate</span><span>Duplicate</span><span>Delete</span></span></div>
                    <div class="ptr ven">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>35mm Venetian</span>
                      <span class="st">&check; Ready</span><span class="n2">1</span><span class="n2">40</span><span class="n2">1</span>
                      <span class="n2">5</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">2 days ago</span>
                      <span class="racts"><span>Deactivate</span><span>Duplicate</span><span>Delete</span></span></div>
                    <div class="ptr">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>Roller Blind</span>
                      <span class="st">&check; Ready</span><span class="n2">2</span><span class="n2">96</span><span class="n2">2</span>
                      <span class="n2">9</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">1 wk ago</span>
                      <span class="racts"><span>Deactivate</span><span>Duplicate</span><span>Delete</span></span></div>
                  </div>
                  <div class="bbar">
                    <span class="bcount"><span class="bnone">(none selected)</span><span class="bsel">3 selected</span></span>
                    <span class="selectbox" style="min-width:9.5rem;font-size:.72rem;padding:.24rem .5rem">Move selected to&hellip;</span>
                    <span class="bbtn cmb" title="Make these the systems (e.g. slat sizes) of one product">Combine into product&hellip;</span>
                    <span class="bbtn danger">Delete selected</span>
                    <span class="bclear">Clear</span>
                  </div>
                  <p class="ldesc2" style="margin-top:.55rem">Greyed out until <b>two or more</b> rows are ticked. The
                     <b>topmost ticked row</b> becomes the master &mdash; drag <b>&#8942;&#8942;</b> to reorder.</p>
                </div>

                <!-- ============ screen 2: the Combine page ============ -->
                <div class="scr scr-form">
                  <p class="crumb">Products / Combine</p>
                  <div class="card-t">Combine into one product</div>
                  <p class="ldesc2">Each product below becomes a <b>system</b> of a single master product. Their fabrics,
                     price tables and settings move across.</p>

                  <div class="errbanner errb"><span>&#9888;</span><div><b>These products are priced differently
                     (e.g. per-slat vs width&times;drop), so they can&rsquo;t be systems of one product.</b></div></div>

                  <div class="bluep">The <b>first</b> product is reused as the master (it keeps its group and settings).
                     The others fold in as systems and are then deactivated &mdash; their data isn&rsquo;t lost, it moves onto
                     the master. You can delete the empty husks afterwards.</div>

                  <div class="fld"><label>Master product name <span class="req">*</span></label>
                    <div class="box mname"><span class="ph">e.g. Metal Venetian</span><span class="val">Metal Venetian</span></div></div>

                  <div class="ctable">
                    <div class="ct-head"><span>Product</span><span>Becomes system</span><span class="fabh">Fabrics</span></div>
                    <div class="ct-row"><span>15mm Venetian <span class="masterpill">Master</span></span>
                      <span><span class="boxv">15mm</span></span><span class="fabn">42</span></div>
                    <div class="ct-row"><span>25mm Venetian</span>
                      <span><span class="boxv">25mm</span></span><span class="fabn">38</span></div>
                    <div class="ct-row"><span>35mm Venetian</span>
                      <span><span class="boxv">35mm</span></span><span class="fabn">40</span></div>
                  </div>

                  <div class="acts"><span class="save">Combine into one product</span><span class="ghost">Cancel</span></div>
                </div>

                <!-- ============ screen 3: the master&rsquo;s Edit page ============ -->
                <div class="scr scr-after">
                  <p class="crumb">Products / Metal Venetian</p>
                  <div class="card-t">Edit Metal Venetian</div>
                  <div class="okbanner"><span>&check;</span><div>Combined into &quot;Metal Venetian&quot; with 3 systems.
                     25mm Venetian, 35mm Venetian are now empty and deactivated &mdash; delete once you&rsquo;ve checked the result.</div></div>
                  <div class="chok"><span>&check;</span><b>Catalogue health:</b> All checks pass &mdash; this product is
                     ready to quote.</div>

                  <div class="catsec sys"><div class="catsum"><span class="cart">&#9660;</span>Systems
                     <span class="count">(3)</span><span class="acts2"><span>Full manage &raquo;</span></span></div>
                    <div class="catbody">
                      <div class="irow"><span class="drag">&#8942;&#8942;</span>
                        <span><span class="lnk">15mm</span><span class="pdef">Default</span></span>
                        <span><span class="ptbl">1 price table</span></span><span class="ra">Open</span></div>
                      <div class="irow"><span class="drag">&#8942;&#8942;</span>
                        <span><span class="lnk">25mm</span></span>
                        <span><span class="ptbl">1 price table</span></span><span class="ra">Open</span></div>
                      <div class="irow"><span class="drag">&#8942;&#8942;</span>
                        <span><span class="lnk">35mm</span></span>
                        <span><span class="ptbl">1 price table</span></span><span class="ra">Open</span></div>
                      <div class="qadd">
                        <div class="fld"><label>Add a new system</label>
                          <div class="box"><span class="ph">e.g. Slim Line / Motorised / 25mm slat</span></div></div>
                        <span class="qbtn">+ Add system</span>
                      </div>
                    </div>
                  </div>

                  <div class="catsec"><div class="catsum"><span class="cart">&#9654;</span>Fabrics
                     <span class="count">(120)</span><span class="acts2"><span>Copy from product &raquo;</span>
                     <span>Import &raquo;</span><span>Full manage &raquo;</span></span></div></div>
                  <p class="ldesc2" style="margin-top:.55rem">Every catalogue section starts <b>shut</b> &mdash; click its
                     <b>&#9654;</b> to open it. <b>Systems</b> is shown open here.</p>
                </div>

                <!-- ============ screen 4: back on the Products list ============ -->
                <div class="scr scr-back">
                  <div class="card-t">Products</div>
                  <p class="ghead"><span class="cart">&#9654;</span> Ungrouped <span class="gcount">4</span></p>
                  <div class="ptab">
                    <div class="pth">
                      <span class="tick">&check;</span><span></span><span>Name</span><span>Status</span>
                      <span class="n2">Systems</span><span class="n2">Fabrics</span><span class="n2">Price tables</span>
                      <span class="n2">Options</span><span>Group</span><span>Updated</span><span></span>
                    </div>
                    <div class="ptr mst">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>Metal Venetian</span>
                      <span class="st">&check; Ready</span><span class="n2">3</span><span class="n2">120</span><span class="n2">3</span>
                      <span class="n2">9</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">just now</span>
                      <span class="racts"><span>Deactivate</span><span>Duplicate</span><span>Delete</span></span></div>
                    <div class="ptr off">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>25mm Venetian</span>
                      <span class="st off">Inactive</span><span class="n2">1</span><span class="n2">0</span><span class="n2">0</span>
                      <span class="n2">0</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">just now</span>
                      <span class="racts"><span>Activate</span><span>Duplicate</span><span>Delete</span></span></div>
                    <div class="ptr off">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>35mm Venetian</span>
                      <span class="st off">Inactive</span><span class="n2">1</span><span class="n2">0</span><span class="n2">0</span>
                      <span class="n2">0</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">just now</span>
                      <span class="racts"><span>Activate</span><span>Duplicate</span><span>Delete</span></span></div>
                    <div class="ptr">
                      <span class="tick">&check;</span><span class="drag">&#8942;&#8942;</span><span>Roller Blind</span>
                      <span class="st">&check; Ready</span><span class="n2">2</span><span class="n2">96</span><span class="n2">2</span>
                      <span class="n2">9</span><span class="selectbox">&mdash; Ungrouped &mdash;</span><span class="upd">1 wk ago</span>
                      <span class="racts"><span>Deactivate</span><span>Duplicate</span><span>Delete</span></span></div>
                  </div>
                  <p class="ldesc2" style="margin-top:.55rem">The husks keep their row and their own empty system, but
                     <b>Fabrics</b>, <b>Price tables</b> and <b>Options</b> all read <b>0</b> &mdash; that lot moved onto the
                     master. Their first action link now reads <b>Activate</b>.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Three near-identical products &mdash; one job each.</b>
                  <b class="c2"><span class="n">2</span> Tick them &mdash; the <em>topmost</em> one is the master.</b>
                  <b class="c3"><span class="n">3</span> Read the blue panel before you go on.</b>
                  <b class="c4"><span class="n">4</span> Name the master &mdash; the only empty box.</b>
                  <b class="c5"><span class="n">5</span> Check each system name and the fabric counts.</b>
                  <b class="c6 err"><span class="n">6</span> The refusal you&rsquo;re most likely to meet.</b>
                  <b class="c7 good"><span class="n">7</span> The master&rsquo;s Edit page &mdash; Systems (3), 15mm Default.</b>
                  <b class="c8"><span class="n">8</span> Back on the list &mdash; the two husks are Inactive.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Suppliers hand you a family of blinds as <b>separate products</b> &mdash; <em>15mm Venetian</em>, <em>25mm Venetian</em>,
             <em>35mm Venetian</em>. Each one carries its own fabrics, its own price tables and its own settings, so every price rise
             means doing the same job three times, and whoever is quoting has to know which of the three lookalikes to pick.</p>
          <p><b>Combine</b> folds them into <b>one</b> product in which each size is a <b>system</b>. The payoff is at the other end:
             instead of three near-identical entries in the quote builder&rsquo;s <b>Product</b> picker, there is <b>one</b> product and a
             <b>System</b> dropdown &mdash; Product &rarr; System &rarr; Band &rarr; Fabric &mdash; and InstaPrice gains the same
             <b>System</b> select.</p>
          <ul class="steps">
            <li><b>Open the Products list</b> and <b>tick</b> two or more products with the box on the left of each row. The count under
                the tables changes from <em>(none selected)</em> to <em>3 selected</em>, a <b>Clear</b> link appears, and
                <b>&ldquo;Combine into product&hellip;&rdquo;</b> stays <b>greyed out until at least two</b> are ticked.</li>
            <li><b>Get the master to the top first.</b> The master is <b>not</b> the one you clicked first &mdash; it is the ticked row
                that sits <b>highest on the page</b>. Rows are ordered by their own sort order, then name; with groups switched on, each
                group&rsquo;s table comes in group order and <b>Ungrouped is last</b>. So if the one you want as master is lower down,
                drag it up by its <b>&#8942;&#8942;</b> handle before you tick anything (the new order is saved as you drop it), or use
                <b>Move selected to&hellip;</b> to file them all into one group. Selection is page-wide, but <b>Select all</b> and
                shift-click ranges only work inside <b>one</b> table.</li>
            <li><b>Press &ldquo;Combine into product&hellip;&rdquo;.</b> That button is the <b>only</b> way in &mdash; the Combine page is
                posted to, never browsed to. Typing or bookmarking the address bounces you straight back with
                <code>Pick at least two products to combine.</code></li>
            <li><b>Read the blue panel.</b> It tells you which case you are in: the first product is <b>reused</b> as the master and keeps
                its group and settings, the rest fold in and are then switched off. If the first one is already a master the wording
                changes to say the others are <b>added to it</b> as extra systems.</li>
            <li><b>Type the Master product name</b> (e.g. <em>Metal Venetian</em>) &mdash; 1&ndash;150 characters. This <b>renames the
                first product</b>; it keeps its <b>id</b>, its group and its settings, so anything already pointing at it still works.</li>
            <li><b>Check each &ldquo;Becomes system&rdquo; box.</b> Every row has one and every one is <b>required</b>. The app has already
                guessed the name from the product name &mdash; it takes the first <em>NNmm</em> it finds and strips the space
                (<em>15mm Venetian</em> &rarr; <b>15mm</b>). If a name has <b>no mm in it</b>, the box is pre-filled with the
                <b>whole product name</b> (<em>Vertical Blind</em>), which you almost certainly want to shorten. Glance at the
                <b>Fabrics</b> column too &mdash; that is how many colours will move for each product, so it is your check that you ticked
                the right three.</li>
            <li><b>Press &ldquo;Combine into one product&rdquo;</b> (or <b>Cancel</b> to back out, changing nothing). You land on the
                <b>master&rsquo;s Edit page</b> with the green message and the green <b>Catalogue health</b> strip. Scroll to the
                catalogue sections and click <b>Systems</b> open &mdash; those sections start <b>shut</b> &mdash; and its heading now
                reads <b>Systems (3)</b>, listing 15mm (<b>Default</b>), 25mm and 35mm, each with its own <em>1 price table</em>
                pill. The <b>Fabrics</b> section below it now reads <b>(120)</b>.</li>
            <li><b>Go back to the Products list</b> to see the shape of it: <em>Metal Venetian</em> reading <b>3</b> systems,
                <b>120</b> fabrics and <b>3</b> price tables, with <em>25mm Venetian</em> and <em>35mm Venetian</em> still listed but
                wearing the grey <b>Inactive</b> pill and their Fabrics, Price tables and Options columns emptied.</li>
          </ul>

          <p class="prose"><b>What actually moves across</b></p>
          <ul class="steps">
            <li><b>Price tables</b> &mdash; re-pointed to the master and to that size&rsquo;s new system.</li>
            <li><b>Fabrics</b> &mdash; moved to the master and <b>scoped to their own system</b>, so the quote builder only offers the
                25mm colours under 25mm. The <b>Fabrics</b> page gains a <b>System</b> column reading the system name or <em>All systems</em>.</li>
            <li><b>Markup % and Discount %</b> &mdash; the product&rsquo;s per-system rows travel with the system.</li>
            <li><b>Options/extras</b> &mdash; merged onto the master <b>by name</b> (see the warning below).</li>
            <li><b>The source product</b> &mdash; set to <b>Inactive</b>. The base&rsquo;s own size becomes the <b>default</b> system, which
                is what the quote builder pre-selects.</li>
          </ul>
          <p>It is <b>one database transaction</b>: all of it or none of it. If anything goes wrong you see
             <code>Could not combine: &hellip;</code> and <b>nothing has changed</b>.</p>

          <div class="oops"><b>&ldquo;These products are priced differently (e.g. per-slat vs width&times;drop), so they can&rsquo;t be
             systems of one product.&rdquo;</b> Every product must be set up the <b>same way</b> on its own <b>Edit</b> page. Four
             tick-boxes are compared, not just the pricing shape &mdash; <em>No fabric to choose (headrail only, track, spares).</em>,
             <em>Sized by width only &mdash; no drop (e.g. a headrail cut to length).</em>, <em>Priced per slat (by drop) &mdash; e.g.
             vertical fabric only.</em> and <em>Priced per square metre &mdash; e.g. shutters.</em> So a no-fabric headrail cannot be
             folded in with fabric products even when both are width &times; drop. Untick the odd one out and combine the rest, or go and
             fix that product first. You may also see <code>Give the combined product a valid name (1&ndash;150 chars).</code> or
             <code>Each new system needs a name (1&ndash;150 chars).</code> &mdash; every system box is required, so clearing one to tidy
             it up blocks the save.</div>

          <div class="oops"><b>&ldquo;&quot;50mm Venetian&quot; already has more than one system &mdash; only the first (the master)
             may. Add single-size products to it.&rdquo;</b> This is the one that catches people adding a <b>new size to an existing
             master</b>, and it is almost always a sorting accident: <em>50mm Venetian</em> sorts <b>above</b> <em>Metal Venetian</em>, so
             the new single size ended up topmost and the app treated <b>it</b> as the base. The fix is not to re-tick in a different
             order &mdash; it is to get the <b>master to the top</b>: drag <em>Metal Venetian</em> above it with the
             <b>&#8942;&#8942;</b> handle, or put them in the same group, then tick and combine again.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Check your extras afterwards.</b> Extras are merged by <b>name</b>.
             If the master already has an extra with the <b>same name</b>, the incoming copy <b>and all of its choices are deleted</b>,
             not merged &mdash; different choices or prices under that name are lost. And an extra that exists only on the folded size
             arrives as a <b>product-wide</b> extra, so it will offer itself on <b>every</b> system until you scope it. Open the
             master&rsquo;s options and give it a look.</div></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>There is no un-combine.</b> The folded-in products are switched to
             <b>Inactive</b>, and the quote builder only offers <b>active</b> products, so they vanish from quoting immediately &mdash;
             there is <b>no hurry</b> to delete them. Leave them until you are happy the master is right; when you do delete, the confirm
             says <code>Delete 25mm Venetian? This removes all options, extras, and price tables linked to it. Cannot be undone.</code>
             (in bulk: <code>Delete 2 selected products? This removes all options, extras and price tables linked to them. Cannot be
             undone.</code>). Existing <b>quotes and orders are unaffected</b> &mdash; each line keeps the product and system name it was
             saved with, so renaming the master never rewrites history.</div></div>

          <p><b>Adding another size later.</b> Tick the existing master <b>and</b> the new single-size product, make sure the
             <b>master is the higher of the two</b> in the list, and combine again. The blue panel changes to
             <em>&ldquo;Metal Venetian is already a master, so the others are added to it as extra systems&hellip;&rdquo;</em>, the
             master&rsquo;s name box is pre-filled, its own row shows <em>keeps its 3 existing systems</em> instead of a box, and the new
             size is appended as an extra system. Afterwards check <b>Systems</b> (Rename, drag to reorder, <b>Set default</b> if a
             different size should be pre-selected) and give the new system its <b>price table</b>.</p>',
        'script'  => [
            ['0:00', 'Products list &mdash; nothing ticked yet.',
             'Here is why you are on this page. Fifteen, twenty-five and thirty-five millimetre Venetians sitting as three separate products means three sets of fabrics, three sets of price tables, and three near-identical entries for whoever is quoting. Combining them makes one product in which the size is a system. Nothing is thrown away.', 1],
            ['0:12', 'Three rows tick; the button wakes up.',
             'Tick the ones that belong together. The Combine into product button stays greyed out until at least two are ticked, and the count tells you where you are. Now the rule that catches everybody out: the master is not the one you clicked first. It is the one nearest the top of the list. If the one you want as master is lower down, drag it up by its handle before you tick anything. And if they sit in different groups, the earlier group wins, so file them together first.', 2],
            ['0:24', 'The Combine page arrives; the blue panel.',
             'Press the button and the Combine page opens. Read the blue panel once. The first product is reused as the master and keeps its group and its settings. The others fold in as systems and are then switched off. Their data is not deleted, it moves. If the first one is already a master the wording changes to say the others are added to it as extra systems.', 3],
            ['0:38', 'Metal Venetian types into the name box.',
             'This is the only box you type from scratch. Master product name. It renames the first product, so give the family its proper name, up to a hundred and fifty characters. Leave it empty and it will tell you: give the combined product a valid name, one to a hundred and fifty characters.', 4],
            ['0:52', 'System names highlight; fabrics 42, 38, 40.',
             'The system names are already filled in for you. The app takes the first millimetre size it finds in the product name and uses that. If a name has no millimetres in it at all, the box is filled with the whole product name, so shorten it before you go on. Each one is required. And the Fabrics column is how many colours will move for each product, so use it to check you have ticked the right three.', 5],
            ['1:08', 'The red refusal banner.',
             'Here is the refusal you are most likely to meet. These products are priced differently, so they cannot be systems of one product. Every product has to be set up the same way on its edit page: the same answer to sized by width only, priced per slat, priced per square metre, and no fabric to choose. Untick the odd one out and combine the rest, or go and fix that product first. And if you are adding a new size to an existing master and it says that product already has more than one system, the master was simply not at the top. Drag it above and try again.', 6],
            ['1:24', 'Press it; land on Edit Metal Venetian.',
             'Press Combine into one product and it happens in one go: all of it, or none of it. Price tables re-point to their new system. Each size brings its fabrics across scoped to that size, so the quote builder only offers twenty-five millimetre colours under twenty-five millimetre. The markup and discount rows come with them. And you land on the master\'s edit page with the green message and catalogue health all clear. Click the Systems section open — those sections start shut — and it reads Systems, three: fifteen, twenty-five and thirty-five millimetre, the first one marked Default, each carrying its own price table.', 7],
            ['1:38', 'Back on the list; two inactive husks.',
             'Now go back to the Products list and see what you have. One row, Metal Venetian, three systems, a hundred and twenty fabrics, three price tables. The two folded-in products are still listed, but marked Inactive with their counts emptied, and the quote builder only offers active products, so they have already gone from quoting. Check your extras as well: an extra sharing its name with one already on the master is dropped rather than merged. Leave the husks be until you are happy, because deleting is final, and there is no un-combine.', 8],
        ],
];
