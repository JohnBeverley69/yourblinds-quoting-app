<?php
declare(strict_types=1);

/**
 * Guide: quote-order-invoice
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /orders/index.php — the unified Quotes + Orders LIST (scope=quotes /
 * scope=orders, faceted by type=retail|trade) — and then follows one row
 * through the Quote actions panel, the Place-order screen, the fulfilment
 * stage on the Factory app's Incoming Orders screen, the invoice email, and
 * Paid. The list itself is taught in full by help/guides/orders-list.php;
 * scenes 1-3 here are only enough of it to pick the row we then follow.
 *
 * Every label, flash and refusal below is taken from the live code:
 * orders/index.php, orders/archive.php, quote-history/bulk_delete.php,
 * quote-builder/edit.php, quote-builder/change_status.php,
 * quote-builder/_helpers.php (qb_allowed_transitions),
 * quote-builder/order_suppliers.php, pdf-generator/send_invoice.php,
 * pdf-generator/pdf.php, factory/incoming-orders.php, admin/users.php,
 * admin/settings.php, _partials/feature_flags.php and
 * _partials/order_stage.php.
 */

return [
        'aud'     => 'admin',
        'section' => 'Orders',
        'title'   => 'Orders, fulfilment & invoicing',
        'eyebrow' => 'Orders',
        'blurb'   => 'One job followed all the way through the order book: accepted, placed with the workshop and the suppliers, made, invoiced — and Paid, which happens on its own.',
        'lede'    => 'The <b>Orders</b> list is your order book: every job the customer has said <b>yes</b> to, newest first. This guide starts
                      there &mdash; just enough of the chips, the columns and the tidy-up buttons to pick a row (the list itself gets a guide of
                      its own, <em>Finding a job: the Orders list</em>) &mdash; and then follows that one row all the way through:
                      <b>accepted</b>, <b>placed</b> with the workshop and your suppliers, <b>made</b>, <b>invoiced</b>, and finally
                      <b>Paid</b>, which happens <em>on its own</em>. Nothing here is a guess: every button and every message you see is the real one.',
        'open'    => '/orders/index.php',
        'css'     => '
          /* ---------- scene switching ---------- */
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scList,
          .gd .stage[data-step="2"] .scList,
          .gd .stage[data-step="3"] .scList{ display:block; }
          .gd .stage[data-step="4"] .scActions{ display:block; }
          .gd .stage[data-step="5"] .scSup{ display:block; }
          .gd .stage[data-step="6"] .scStage{ display:block; }
          .gd .stage[data-step="7"] .scInv{ display:block; }
          .gd .stage[data-step="8"] .scPaid{ display:block; }

          /* ---------- page chrome (the real header) ---------- */
          .gd .pgh{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .pgs{ font-size:.68rem; color:var(--faint); margin:.1rem 0 .45rem; }
          .gd .hrow{ display:flex; align-items:center; gap:.5rem; margin-bottom:.55rem; }
          .gd .seg{ display:inline-flex; background:var(--bg-subtle-2,#f1f4f8); border-radius:8px; padding:2px; }
          .gd .sg{ font-size:.68rem; font-weight:700; color:var(--faint); padding:.2rem .6rem; border-radius:6px; }
          .gd .sg.on{ background:var(--surface); color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.09); }
          .gd .newq{ margin-left:auto; background:var(--accent); color:#fff; border-radius:8px; padding:.28rem .7rem; font-size:.7rem; font-weight:700; }

          /* ---------- filter chips: pill LINKS with counts ---------- */
          .gd .chips{ display:flex; flex-wrap:wrap; align-items:center; gap:.3rem; margin-bottom:.5rem; }
          .gd .fchip{ font-size:.66rem; border-radius:999px; padding:.15rem .55rem; background:var(--bg-subtle-2,#f1f4f8); color:var(--soft); border:1px solid transparent; }
          .gd .fchip.act{ background:var(--accent); color:#fff; }
          .gd .fchip.arch{ margin-left:auto; }

          /* ---------- search form ---------- */
          .gd .sform{ display:flex; align-items:center; gap:.35rem; margin-bottom:.5rem; }
          /* the real control is <input type="search"> — draw it as a text box */
          .gd .sform .box{ flex:1; height:27px; display:flex; align-items:center; padding:0 .5rem; font-size:.68rem;
                           border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; background:var(--surface);
                           color:var(--ink); overflow:hidden; }
          .gd .gbtn{ display:inline-flex; align-items:center; gap:.28rem; border:1px solid var(--line); border-radius:7px; padding:.22rem .6rem; font-size:.68rem; font-weight:600; color:var(--ink); background:var(--surface); white-space:nowrap; }
          .gd .gbtn.pri{ background:var(--accent); color:#fff; border-color:var(--accent); }
          .gd .gbtn.dan{ border-color:var(--err); color:var(--err); }
          .gd .gbtn.off{ opacity:.42; }
          .gd .stage[data-step="3"] .gbtn.off{ opacity:1; }
          .gd .clr{ display:none; }
          .gd .stage[data-step="3"] .clr{ display:inline-flex; }

          /* ---------- bulk bar ---------- */
          .gd .bbar{ display:flex; align-items:center; gap:.4rem; margin-bottom:.4rem; flex-wrap:wrap; }
          .gd .cnt{ font-size:.64rem; color:var(--faint); }
          .gd .cnt .c-two{ display:none; }
          .gd .stage[data-step="3"] .cnt .c-none{ display:none; }
          .gd .stage[data-step="3"] .cnt .c-two{ display:inline; }

          /* ---------- the table ---------- */
          .gd .tbl{ display:none; border:1px solid var(--line); border-radius:9px; overflow:hidden; }
          .gd .stage[data-step="2"] .tbl, .gd .stage[data-step="3"] .tbl{ display:block; }
          .gd .tr{ display:grid; grid-template-columns:1.1rem 5.2rem 4.3rem 3.3rem 3.9rem 3.6rem 3.2rem 4.2rem 3.4rem; gap:.3rem; align-items:center; padding:.3rem .42rem; border-top:1px solid var(--line-2); font-size:.64rem; color:var(--ink); }
          .gd .tr.hd{ border-top:none; background:var(--panel); color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.03em; font-size:.53rem; }
          .gd .qn2{ font-weight:700; color:var(--ink); }
          .gd .sendlink{ display:block; font-size:.53rem; color:var(--accent); margin-top:.1rem; white-space:nowrap; }
          .gd .faint{ color:var(--faint); }
          .gd .num{ font-variant-numeric:tabular-nums; }
          .gd .amb{ color:#92400e; font-weight:700; }
          .gd .grn{ color:#065f46; font-weight:700; }
          .gd .amb.lnk{ border-bottom:1px dashed #92400e; }
          /* status pill drawn NEUTRAL on purpose — the real one is coloured
             inline from the tenant palette (same colours as the calendar). */
          .gd .spill{ display:inline-block; font-size:.52rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; border-radius:999px; padding:.05rem .4rem; background:var(--bg-subtle-2,#eef2f6); color:var(--soft); border:1px dashed var(--line); }
          .gd .nspill{ display:inline-block; font-size:.52rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; border-radius:999px; padding:.04rem .4rem; color:#92400e; background:#fef3c7; border:1px solid #fde68a; }
          /* row tick-boxes: empty until the narration ticks them at step 3. Both
             rows on screen get ticked, and the real JS sets the header box to a
             full check when checked === total (the dash is only for a part-tick). */
          .gd .stage[data-step="3"] .rt{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .ha{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .aside{ display:none; font-size:.64rem; color:var(--faint); margin:.5rem 0 0; line-height:1.5; }
          .gd .stage[data-step="2"] .aside, .gd .stage[data-step="3"] .aside{ display:block; }
          .gd .aside b{ color:var(--ink); }

          /* ---------- quote actions panel ---------- */
          .gd .qbar{ display:flex; align-items:center; gap:.45rem; background:var(--nav); color:#fff; border-radius:8px; padding:.4rem .6rem; font-size:.72rem; margin-bottom:.65rem; }
          .gd .qbar .qn{ font-weight:700; }
          .gd .qbar .qtot{ margin-left:auto; font-weight:700; }
          .gd .qbar .spill{ background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.3); }
          .gd .qah{ font-size:.68rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.45rem; }
          .gd .qacts{ display:flex; gap:.4rem; flex-wrap:wrap; }
          .gd .stage[data-step="4"] .gbtn.hl{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- place-order screen ---------- */
          .gd .supg{ border:1px solid var(--line); border-radius:9px; padding:.45rem .6rem; margin-bottom:.45rem; }
          .gd .supg.mfg{ border-color:#86efac; background:rgba(134,239,172,.14); }
          .gd .suph{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; font-size:.7rem; }
          .gd .supn{ font-weight:700; color:var(--ink); }
          .gd .supe{ color:var(--accent); font-size:.64rem; }
          .gd .supm{ color:var(--faint); font-size:.62rem; }
          .gd .supnote{ font-size:.62rem; color:var(--soft); margin-top:.28rem; line-height:1.5; }
          .gd .sentbadge{ font-size:.58rem; font-weight:700; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:999px; padding:.05rem .45rem; }
          .gd .pick{ display:inline-flex; align-items:center; gap:.3rem; margin-left:auto; font-size:.64rem; color:var(--soft); }
          .gd .supintro{ font-size:.66rem; color:var(--soft); margin:0 0 .5rem; line-height:1.5; }
          .gd .supwarn{ font-size:.62rem; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:.2rem .45rem; margin-top:.28rem; line-height:1.45; }
          /* the real per-supplier line-items table: Product / Fabric-colour / Size / Qty / Room */
          .gd .sit{ margin-top:.35rem; border:1px solid var(--line-2); border-radius:7px; overflow:hidden; }
          .gd .sitr{ display:grid; grid-template-columns:6.4rem 5rem 5.2rem 1.6rem 3.4rem; gap:.3rem; align-items:start;
                     padding:.22rem .4rem; border-top:1px solid var(--line-2); font-size:.58rem; color:var(--ink); }
          .gd .sitr.hd{ border-top:none; background:var(--panel); color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.03em; font-size:.5rem; }
          .gd .sitr .sub{ color:var(--faint); font-size:.53rem; }
          .gd .sitr .opt{ color:var(--accent); font-size:.53rem; }

          /* ---------- fulfilment stage (factory row) ---------- */
          .gd .iorow{ border:1px solid var(--line); border-radius:9px; padding:.5rem .6rem; }
          .gd .ioh{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; font-size:.7rem; }
          .gd .ioref{ font-weight:700; color:var(--ink); }
          .gd .ioq{ font-size:.62rem; color:var(--faint); }
          .gd .stagerow{ display:flex; align-items:center; gap:.3rem; flex-wrap:wrap; margin-top:.45rem; }
          .gd .stg{ font-size:.58rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; border-radius:999px; padding:.1rem .5rem; border:1px solid var(--line); color:var(--faint); background:var(--surface); }
          /* exact stage colours from factory/incoming-orders.php ($stCols) */
          .gd .stg.s1{ color:#5b6b7f; background:#e6ebf1; border-color:#e6ebf1; }
          .gd .stg.s2{ color:#b5730f; background:#f7ecd6; border-color:#f7ecd6; }
          .gd .stg.s3{ color:#1e40af; background:#dbeafe; border-color:#dbeafe; }
          .gd .stg.s4{ color:#0d7a67; background:#d6ece6; border-color:#d6ece6; }
          .gd .prog{ font-size:.6rem; color:var(--accent); border-bottom:1px dashed var(--accent); }

          /* ---------- invoice ---------- */
          .gd .confirm{ border:1px solid var(--line); border-radius:9px; padding:.42rem .6rem; background:var(--panel); font-size:.66rem; color:var(--soft); margin:.45rem 0; }
          .gd .emailcard{ border:1px solid var(--line); border-radius:9px; overflow:hidden; margin-top:.45rem; }
          .gd .ehead{ background:var(--panel); padding:.4rem .6rem; border-bottom:1px solid var(--line); font-size:.66rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); font-size:.62rem; }
          .gd .ebody{ padding:.45rem .6rem; font-size:.64rem; color:var(--soft); line-height:1.55; }
          .gd .ebal{ color:#1e40af; font-weight:700; }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:6px; padding:.15rem .45rem; font-size:.6rem; color:var(--soft); background:var(--surface); margin-top:.25rem; }
          .gd .note{ font-size:.63rem; color:var(--faint); margin-top:.5rem; line-height:1.5; }
          .gd .note b{ color:var(--ink); }
          @media(max-width:620px){
            .gd .tr{ grid-template-columns:1rem 4.4rem 3.6rem 2.8rem 3.2rem 3rem 2.8rem 3.6rem 3rem; font-size:.58rem; }
            .gd .sitr{ grid-template-columns:5.2rem 4rem 4.4rem 1.4rem 2.8rem; font-size:.53rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / orders</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a class="on">Orders</a>
                <div class="navh">Setup</div>
                <a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ============ Scene 1-3: the Orders LIST ============ -->
                <div class="osc scList">
                  <div class="pgh">Orders</div>
                  <div class="pgs">Accepted onward &mdash; orders, invoices and paid jobs.</div>
                  <div class="hrow">
                    <span class="seg"><span class="sg on">List</span><span class="sg">Pipeline</span></span>
                    <span class="newq">+ New quote</span>
                  </div>

                  <div class="chips">
                    <span class="fchip">All (24)</span>
                    <span class="fchip">Accepted (3)</span>
                    <span class="fchip act">Ordered (9)</span>
                    <span class="fchip">Fitted (4)</span>
                    <span class="fchip">Invoiced (6)</span>
                    <span class="fchip">Paid (2)</span>
                    <span class="fchip arch">&#128451; Archived (11)</span>
                  </div>

                  <div class="sform">
                    <span class="box f3"><span class="ph">Search by quote #, customer name, or postcode&hellip;</span><span class="val">Fletcher</span></span>
                    <span class="gbtn">Search</span>
                    <span class="gbtn clr">Clear</span>
                  </div>

                  <div class="bbar">
                    <span class="gbtn off">&#128451; Archive selected</span>
                    <span class="gbtn dan off">Delete selected</span>
                    <span class="cnt"><span class="c-none">(none selected)</span><span class="c-two">(2 of 2 selected)</span></span>
                  </div>

                  <div class="tbl">
                    <div class="tr hd">
                      <span class="tick ha">&check;</span><span>Quote #</span><span>Customer</span><span>Postcode</span><span>Status</span><span>Created</span><span class="num">Total</span><span>Deposit</span><span class="num">Outstanding</span>
                    </div>
                    <div class="tr">
                      <span class="tick rt">&check;</span>
                      <span><span class="qn2">PRE-2026-0042</span><span class="sendlink">&#128230; Send to suppliers</span></span>
                      <span>Emma Fletcher</span><span>BD23 1AB</span>
                      <span><span class="spill">Ordered</span></span>
                      <span class="faint">3 Sep 2026</span>
                      <span class="num">&pound;660.00</span>
                      <span class="amb">&pound;330.00 due</span>
                      <span class="num amb lnk">&pound;660.00</span>
                    </div>
                    <div class="tr">
                      <span class="tick rt">&check;</span>
                      <span><span class="qn2">PRE-2026-0039</span><span class="sendlink">&#128230; Send to suppliers</span></span>
                      <span>Raj Patel</span><span>LS29 8QE</span>
                      <span><span class="spill">Invoiced</span></span>
                      <span class="faint">28 Aug 2026</span>
                      <span class="num">&pound;1,240.00</span>
                      <span class="grn">&check; &pound;250.00 paid</span>
                      <span class="num amb lnk">&pound;990.00</span>
                    </div>
                  </div>
                  <p class="aside">Same screen, second job: the sidebar&rsquo;s <b>Quotes</b> row opens it as the quote pipeline instead &mdash;
                     chips read <b>Quote</b> and <b>Declined</b>, a not-yet-sent draft carries a <span class="nspill">Not sent</span> pill, and the
                     <b>Deposit</b> and <b>Outstanding</b> columns are not there at all.</p>
                </div>

                <!-- ============ Scene 4: Quote actions ============ -->
                <div class="osc scActions">
                  <div class="qbar">
                    <span class="qn">Quote PRE-2026-0042</span>
                    <span class="spill">sent</span>
                    <span class="qtot">Total &pound;660.00</span>
                  </div>
                  <div class="qah">Quote actions</div>
                  <div class="qacts">
                    <span class="gbtn">View PDF</span>
                    <span class="gbtn">Download PDF</span>
                    <span class="gbtn pri hl">&#128230; Save as order</span>
                    <span class="gbtn">Mark as accepted</span>
                    <span class="gbtn">Mark as declined</span>
                    <span class="gbtn">Reopen as draft</span>
                  </div>
                  <div class="okbanner" style="margin-top:.55rem"><span>&check;</span> Order accepted &mdash; place it below (suppliers get emailed their lines).</div>
                  <p class="note">Accepting also seeds the <b>deposit</b> from your default, and drops a placeholder fitting on the calendar:
                     <em>&ldquo;Installation appointment is in the calendar&rsquo;s &lsquo;Pending Fitting&rsquo; tray &mdash; drag it onto the right
                     date and assign a fitter when ready.&rdquo;</em></p>
                </div>

                <!-- ============ Scene 5: place the order ============ -->
                <div class="osc scSup">
                  <div class="pgh">Send order to suppliers</div>
                  <div class="pgs">&larr; Back to order PRE-2026-0042</div>
                  <p class="supintro">Each supplier below gets an email with <b>only their lines</b> and a spec PDF.
                     Tick the ones to send, then <b>Send selected orders</b>.</p>
                  <div class="supg mfg">
                    <div class="suph">
                      <span class="supn">&#127981; Beverley Blinds &mdash; manufacturing</span>
                      <span class="supm">2 lines &middot; auto-routed</span>
                    </div>
                    <div class="supnote">These are your products from the <b>Beverley Blinds</b> catalogue &mdash; they go
                      <b>straight to manufacturing</b> when you place the order. No supplier email needed.</div>
                    <div class="sit">
                      <div class="sitr hd"><span>Product</span><span>Fabric / colour</span><span>Size</span><span>Qty</span><span>Room</span></div>
                      <div class="sitr">
                        <span><b>Bev Roller Blinds</b><br><span class="sub">Standard</span><br><span class="opt">+ Chain: White</span></span>
                        <span>Carnival / Ivory</span><span>1200 &times; 1600 mm</span><span>1</span><span>Lounge</span>
                      </div>
                      <div class="sitr">
                        <span><b>Bev Roller Blinds</b><br><span class="sub">Standard</span></span>
                        <span>Carnival / Ivory</span><span>900 &times; 1600 mm</span><span>1</span><span>Lounge</span>
                      </div>
                    </div>
                  </div>
                  <div class="supg">
                    <div class="suph">
                      <span class="supn">Hunter Douglas</span>
                      <span class="supe">orders@hunterdouglas.co.uk</span>
                      <span class="supm">&middot; acct BEV114</span>
                      <span class="pick"><span class="tick on">&check;</span> Send 2 lines</span>
                    </div>
                    <div class="sit">
                      <div class="sitr hd"><span>Product</span><span>Fabric / colour</span><span>Size</span><span>Qty</span><span>Room</span></div>
                      <div class="sitr">
                        <span><b>PF Venetian</b><br><span class="sub">25mm Aluminium</span></span>
                        <span>Silver Matt</span><span>800 &times; 1100 mm</span><span>1</span><span>Kitchen</span>
                      </div>
                      <div class="sitr">
                        <span><b>PF Venetian</b><br><span class="sub">25mm Aluminium</span></span>
                        <span>Silver Matt</span><span>800 &times; 1100 mm</span><span>1</span><span>Utility</span>
                      </div>
                    </div>
                  </div>
                  <div class="supg">
                    <div class="suph">
                      <span class="supn">Louvolite</span>
                      <span class="supe">trade@louvolite.com</span>
                      <span class="sentbadge">&#9888; Already sent 12 Sep 2026, 09:41</span>
                      <span class="pick"><span class="tick"></span> Re-send 1 line</span>
                    </div>
                    <div class="supnote">Already ordered from <b>Louvolite</b> for this quote &mdash; left unticked so you don&rsquo;t
                      double-order. Tick it only if you really mean to re-send.</div>
                    <div class="sit">
                      <div class="sitr hd"><span>Product</span><span>Fabric / colour</span><span>Size</span><span>Qty</span><span>Room</span></div>
                      <div class="sitr">
                        <span><b>Vogue Vertical</b><br><span class="sub">89mm</span></span>
                        <span>Banlight / Ecru</span><span>2400 &times; 1800 mm</span><span>1</span><span>Dining room</span>
                      </div>
                    </div>
                  </div>
                  <div class="qacts" style="margin-top:.5rem">
                    <span class="gbtn pri">&#128230; Send &amp; place order</span><span class="gbtn">Cancel</span>
                  </div>
                  <p class="note">If <b>every</b> line is something you make yourself you never see this screen &mdash; accepting already placed it:
                     <em>&ldquo;Sent straight to the workshop &mdash; all in-house, no supplier order needed.&rdquo;</em></p>
                </div>

                <!-- ============ Scene 6: fulfilment stage ============ -->
                <div class="osc scStage">
                  <div class="pgh">Incoming Orders</div>
                  <div class="pgs">Placed orders that contain Beverley Blinds lines. Click an order to open its blinds.</div>
                  <div class="iorow">
                    <div class="ioh">
                      <span class="ioref">PRE-2026-0042</span>
                      <span class="ioq">Emma Fletcher &middot; 3 Sep 2026 &middot; 4 blinds</span>
                    </div>
                    <div class="stagerow">
                      <span class="stg s2">In Production</span>
                      <span class="prog">2/4 made</span>
                      <span class="stg s3">Bought-in: ordered</span>
                    </div>
                  </div>
                  <p class="note">One pill, and it is the stage the job is at <em>now</em> &mdash; it changes to <b>Ready</b>, then
                     <b>Dispatched</b>, in place. You never set it; it is worked out for you. <b>Ready</b> is the dispatch gate: every blind made
                     <b>and</b> every bought-in item in. It is <b>not</b> on the Orders list; the list shows Ordered / Fitted / Invoiced / Paid.</p>
                </div>

                <!-- ============ Scene 7: invoice ============ -->
                <div class="osc scInv">
                  <div class="qacts"><span class="gbtn">&#129534; Send invoice</span></div>
                  <div class="confirm">&ldquo;Email this invoice to the customer now? This also marks the job as <b>Invoiced</b>.&rdquo;</div>
                  <div class="errbanner"><span>&#9888;</span><div>No valid customer email on this order &mdash; <b>add one on the customer</b>, then try again.</div></div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Invoice PRE-2026-0042 from Beverley Blinds</div>
                      <div class="frm">to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Hello Emma Fletcher,<br>
                      Please find your invoice (PRE-2026-0042) attached as a PDF.<br>
                      <span class="ebal">Balance due: &pound;330.00.</span> Payment details are on the invoice.<br>
                      You can also view it online here: yourblinds.uk/&hellip;<br>
                      <span class="eatt">&#128206; Invoice_PRE-2026-0042.pdf</span>
                    </div>
                  </div>
                  <div class="okbanner" style="margin-top:.45rem"><span>&check;</span> Invoice emailed to emma.fletcher@gmail.com. Marked as Invoiced.</div>
                </div>

                <!-- ============ Scene 8: paid ============ -->
                <div class="osc scPaid">
                  <div class="chips">
                    <span class="fchip">All (24)</span><span class="fchip">Accepted (3)</span><span class="fchip">Ordered (8)</span>
                    <span class="fchip">Fitted (4)</span><span class="fchip">Invoiced (6)</span><span class="fchip act">Paid (3)</span>
                    <span class="fchip arch">&#128451; Archived (11)</span>
                  </div>
                  <div class="tbl" style="display:block">
                    <div class="tr hd">
                      <span class="tick"></span><span>Quote #</span><span>Customer</span><span>Postcode</span><span>Status</span><span>Created</span><span class="num">Total</span><span>Deposit</span><span class="num">Outstanding</span>
                    </div>
                    <div class="tr">
                      <span class="tick"></span>
                      <span><span class="qn2">PRE-2026-0042</span><span class="sendlink">&#128230; Send to suppliers</span></span>
                      <span>Emma Fletcher</span><span>BD23 1AB</span>
                      <span><span class="spill">Paid</span></span>
                      <span class="faint">3 Sep 2026</span>
                      <span class="num">&pound;660.00</span>
                      <span class="grn">&check; &pound;330.00 paid</span>
                      <span class="num grn">&check; paid</span>
                    </div>
                  </div>
                  <p class="note">There is <b>no</b> &ldquo;Mark as paid&rdquo; button, and never has been. Record the payment and the status follows
                     &mdash; see <em>Payments &amp; accounts</em>.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Your order book &mdash; chips are filters, brackets are counts.</b>
                  <b class="c2"><span class="n">2</span> Column by column &mdash; those status colours are yours, set in Settings.</b>
                  <b class="c3"><span class="n">3</span> Search one box; tick rows to archive or delete.</b>
                  <b class="c4"><span class="n">4</span> Save as order &mdash; accept it and go straight on to place it.</b>
                  <b class="c5"><span class="n">5</span> Yours to the workshop, bought-in to their supplier.</b>
                  <b class="c6"><span class="n">6</span> The fulfilment stage &mdash; worked out for you, on the Factory screen.</b>
                  <b class="c7 err"><span class="n">7</span> Send invoice &mdash; it needs the customer&rsquo;s email.</b>
                  <b class="c8 good"><span class="n">8</span> Paid, on its own, the moment the money covers the total.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>What this screen is.</b> <b>Orders</b> is the list of every job that has been accepted &mdash; the subtitle says so:
             <em>&ldquo;Accepted onward &mdash; orders, invoices and paid jobs.&rdquo;</em> The very same screen doubles as your quote
             pipeline: the sidebar&rsquo;s <b>Quotes</b> row opens it with <em>&ldquo;Quotes still in the pipeline &mdash; drafts, sent, and
             declined.&rdquo;</em> instead. It also comes in two flavours &mdash; under <b>Retail</b> and (where your login has it) under
             <b>Trade</b> &mdash; the title changes to <b>Retail Orders</b> or <b>Trade Orders</b> and the chip counts change with it, because
             each one only counts its own jobs. Top-left there is a two-part <b>List | Pipeline</b> switch (Pipeline is the funnel board, with
             a count and a &pound; value per column and a <b>Window</b> of the last 30 to 365 days); top-right, <b>+ New quote</b>. The list
             shows at most <b>200 rows</b>, newest accepted-or-created first &mdash; past that, filter or search.</p>
          <ul class="steps">
            <li><b>Tick box, then Quote #.</b> The number is the link into the job. Underneath it, on order rows, sits a small
                <b>&#128230; Send to suppliers</b> link straight to the place-order screen.</li>
            <li><b>Customer and Postcode.</b> Exactly what search looks at, along with the quote number.</li>
            <li><b>Status.</b> Accepted, Ordered, Fitted, Invoiced or Paid. The colours are <b>yours</b> &mdash; drawn from the same
                traffic-light palette as the calendar, editable in Settings &mdash; so one job reads the same colour everywhere.</li>
            <li><b>Created</b> and <b>Total.</b> The date the job was raised, and its full value.</li>
            <li><b>Deposit.</b> Green <b>&ldquo;&check; &pound;330.00 paid&rdquo;</b> once it is marked paid, amber
                <b>&ldquo;&pound;330.00 due&rdquo;</b> until then, a dash if there is none. You do not type it: it is seeded the first time the
                job is accepted, from your default deposit (percent &mdash; 50% unless you changed it &mdash; or a flat figure capped at the total).</li>
            <li><b>Outstanding.</b> Total, less payments, less any unpaid-deposit adjustment &mdash; live. Amber and
                <b>underlined</b> means it is a link: click it and Payments opens with that order already chosen
                (<em>&ldquo;Click to take a payment against this order&rdquo;</em>). Blue <b>+&pound;X</b> means overpaid; green
                <b>&check; paid</b> means settled. This column only exists if the <b>Accounts</b> add-on is switched on for your account
                (it is a paid extra, turned on for you by the people who run the system &mdash; there is no switch for it in your own Settings).</li>
          </ul>
          <p><b>Only skimming the list here.</b> Reading a row, the four things that decide which rows you see, and the bulk buttons all get a
             guide to themselves &mdash; <em>Finding a job: the Orders list</em>. This one is about what happens <b>after</b> you have found
             the job.</p>
          <p><b>Finding and tidying.</b> One box searches <b>three</b> things at once &mdash; <em>&ldquo;Search by quote #, customer name, or
             postcode&hellip;&rdquo;</em> &mdash; and it keeps whichever chip you are on, so you can search inside <b>Ordered</b>. A
             <b>Clear</b> button appears once you have typed. The chips carry their counts in brackets, and a chip whose count is
             <b>zero is not drawn at all</b> &mdash; so if you have never had a fitted job, there is simply no Fitted chip; it is not broken.
             Tick some rows and the two buttons wake up and the counter changes from <em>(none selected)</em> to <em>(2 of 2 selected)</em> &mdash;
             and mind that second number: it is how many rows are <b>on screen</b> right now, not how many jobs you own, so it follows the chip
             and the search you are in. Tick every row on screen and the header box fills in completely; tick only some and it shows a dash.
             <b>&#128451; Archive selected</b> just <b>hides</b> finished jobs &mdash; nothing is lost. They move behind the
             <b>&#128451; Archived (n)</b> chip at the far right, where the button becomes <b>Restore selected</b> and the chip becomes
             <b>&larr; Back to active</b>; you get <em>&ldquo;11 jobs archived.&rdquo;</em> or <em>&ldquo;11 jobs restored to active.&rdquo;</em>
             <b>Delete selected</b> is the other thing entirely: it asks <em>&ldquo;Delete the selected quotes? This is permanent &mdash; all
             blinds, items and appointments go too.&rdquo;</em> and it refuses any job with money against it &mdash; <em>&ldquo;2 quotes were kept
             because they have payments recorded against them&hellip; Delete the payments first if you really want to remove these.&rdquo;</em></p>
          <p><b>Turning a quote into an order.</b> Click the quote number and the <b>Quote actions</b> panel is at the top. <b>Accepting</b>
             is what makes a quote an order &mdash; either <b>&check; Customer accepted</b> in the sticky bar or <b>Mark as accepted</b>. If you
             are ready to place it there and then, use the blue <b>&#128230; Save as order</b>: it accepts the job <em>and</em> carries you
             straight to the place-order screen &mdash; <em>&ldquo;Order accepted &mdash; place it below (suppliers get emailed their
             lines).&rdquo;</em> Two things happen quietly on accept: the <b>deposit</b> is worked out, and a placeholder fitting lands in the
             calendar&rsquo;s <b>&ldquo;Pending Fitting&rdquo;</b> tray for you to drag onto the right day. <b>Declining</b> asks
             <em>&ldquo;Mark this quote as declined?&rdquo;</em> and takes that pending fitting back off the calendar.
             On the place-order screen your own products sit in a green <b>&#127981; manufacturing</b> group (no email &mdash; they go to the
             workshop), each bought-in supplier gets its own group with an email and a ticked <b>Send N lines</b> box, and any supplier already
             emailed for this job comes back <b>unticked</b> with <em>&ldquo;&#9888; Already sent&hellip;&rdquo;</em> so you cannot double-order.
             The button reads <b>Send &amp; place order</b>, <b>Send selected orders</b> or just <b>Place order</b> depending on what is on the job.
             Placing it stamps a <b>due date</b>, tells the factory, and auto-sends the bought-in orders. <b>And if every line is something you
             make yourself, you never see that screen</b> &mdash; accepting placed it for you: <em>&ldquo;Sent straight to the workshop &mdash;
             all in-house, no supplier order needed.&rdquo;</em> The buttons you see depend on where the job is, and <b>Reopen as draft</b> is offered
             from nearly everywhere: draft offers sent, accepted or declined; sent offers accepted, declined or back to draft; accepted offers
             ordered, fitted or back to draft; declined offers back to draft; ordered offers fitted, invoiced or back to draft; fitted offers
             invoiced, back to ordered or back to draft; invoiced offers only back to draft; and <b>paid offers nothing at all</b>. Sales moves
             (sent, accepted, declined) need the <b>Create quotes</b> permission and order moves (ordered, fitted, invoiced) need
             <b>Create orders</b> &mdash; both are tick-boxes under <b>Permissions</b> on a person&rsquo;s record under <b>Setup &rsaquo;
             Users</b> &mdash; so a fitter may see none of the order buttons at all. <b>Reopen as draft</b> is the exception: either tick is
             enough.</p>
          <p><b>Where is it?</b> Once an order is placed it also gets a <b>fulfilment stage</b>, worked out for you and never set by hand:
             <b>Confirmed</b> (it has landed) &rarr; <b>In Production</b> (on the floor, or the bought-in orders are out) &rarr; <b>Ready</b>
             &rarr; <b>Dispatched</b>. <b>Ready</b> is the one that matters, because it is the dispatch gate: <b>every</b> in-house blind made
             <b>and every</b> bought-in line received. You will not find it on the Orders list &mdash; open <b>Factory</b> in the sidebar, which
             lands on <b>Incoming Orders</b> (<em>&ldquo;Placed orders that contain &lt;your factory&rsquo;s name&gt; lines. Click an order to
             open its blinds.&rdquo;</em>). Each row there carries <b>one</b> coloured pill &mdash; the stage it is at right now, not the whole
             chain &mdash; beside an <b>N/M made</b> link through to the production floor and, where there is anything bought in, a
             <b>Bought-in: N to order</b> / <b>Bought-in: ordered</b> / <b>Bought-in: received</b> pill. Watch the one pill change; there is no
             four-step strip to read.</p>
          <p><b>Invoicing.</b> The <b>&#129534; Send invoice</b> button only appears once the job is <b>Ordered</b> or later. It asks
             <em>&ldquo;Email this invoice to the customer now? This also marks the job as Invoiced.&rdquo;</em>, then emails the customer a PDF
             named <b>Invoice_&lt;number&gt;.pdf</b> with the subject <b>&ldquo;Invoice &lt;number&gt; from &lt;your company&gt;&rdquo;</b>, the
             <b>balance due</b> stated in the message (or &ldquo;This invoice is fully paid &mdash; thank you.&rdquo;), a link to view it online,
             and moves the job to <b>Invoiced</b>. The document is the same one as the quote, headed <b>Invoice</b>, plus your bank block. If
             any money has been taken already, two more lines appear under the Total &mdash; <b>Paid</b> and <b>Balance due</b>; on a first
             invoice with nothing paid yet <b>neither line is printed</b>, so the Total is the last figure on the page and that is correct,
             not a fault. Send it again and the button has changed to
             <b>&#129534; Resend invoice</b> with a blunter question: <em>&ldquo;This invoice has already been sent. Send it to the customer
             AGAIN?&rdquo;</em></p>
          <div class="oops"><b>When it stops you.</b> <em>&ldquo;No valid customer email on this order &mdash; add one on the customer, then try
             again.&rdquo;</em> &mdash; and you cannot simply type it in where you would expect to: open <b>Customer details</b> on an order and the
             <b>Email</b> box is there but greyed out, because an order is read-only. The way through is <b>Reopen as draft</b> at the top, fill the
             email in, <b>Save details</b>, then <b>Mark as accepted</b> and <b>Mark as ordered</b> to put it back &mdash; and read the
             warning below about the unpaid deposit before you do. <em>&ldquo;You can invoice once the job is
             ordered &mdash; move it to Ordered first.&rdquo;</em> &mdash; the job is only accepted, so place it. And a second send without using
             the Resend button is refused outright: <em>&ldquo;Invoice PRE-2026-0042 has already been sent. Use &lsquo;Resend invoice&rsquo; if you
             really need to send it again.&rdquo;</em> You may also meet <em>&ldquo;You don&rsquo;t have permission to invoice orders.&rdquo;</em>,
             <em>&ldquo;Can&rsquo;t move from accepted to invoiced.&rdquo;</em> or, on the supplier screen,
             <em>&ldquo;No delivery address set &mdash; suppliers won&rsquo;t know where to ship. Add one under Settings &rsaquo; Suppliers
             first.&rdquo;</em></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Paid looks after itself, and an order is read-only.</b> There is no
             &ldquo;Mark as paid&rdquo; button anywhere: a job flips to <b>Paid</b> the moment the deposit plus payments cover the total, and it
             flips back if money is taken out again. So just record the payment. And once a job is an order it cannot be edited &mdash;
             <em>&ldquo;This quote is in ordered state and is read-only. Use Reopen as draft above to edit it.&rdquo;</em> Reopening clears an
             <b>unpaid</b> deposit on purpose, so a percentage deposit re-works itself against the new total; a deposit already marked paid is
             left alone.</div></div>
          <p><b>Two last things.</b> The <b>How to pay &mdash; bank transfer</b> block only prints on the invoice if you have filled the details
             in: <b>Settings</b>, the <b>Quoting</b> tab, right at the bottom under the heading <b>Bank details for customer payments</b>. Leave
             it blank and the block is hidden altogether &mdash; so do that before you invoice anybody. And this is a wide, busy table: if it feels cramped, turn on <b>Compact mode</b> and it tightens
             right up. From here, <em>Payments &amp; accounts</em> covers taking the deposit and the balance, and the <em>Calendar</em> guide
             covers getting that Pending Fitting onto a real day with a real fitter.</p>',
        'script'  => [
            ['0:00', 'The order book: chips and counts.',   'This is your order book. Every job the customer has said yes to lives here, newest first. The chips across the top are just filters, and the number in brackets is how many. If a chip is not there, you simply have not got any of those yet. The same page sits in the sidebar twice, under Retail and under Trade, and each one counts only its own jobs.', 1],
            ['0:18', 'Column by column.',                    'Now the columns. The quote number is the way in. The status colours are your colours, the ones you set in Settings, so a job reads the same colour here, on the calendar and on the pipeline. Deposit is worked out for you the moment you accept, half the total unless you changed it. Outstanding is what is still owed right now, and the amber figure is a link, straight to Payments with that order picked out. Outstanding only shows if the Accounts add-on is switched on for you, and the list stops at two hundred rows. Reading a row in full has a guide of its own, Finding a job: the Orders list.', 2],
            ['0:40', 'Find one, and tidy up.',               'One box searches three things at once: the quote number, the customer name and the postcode. Below it, tick the rows you want and the two buttons wake up. The counter tells you how many of the rows on screen you have picked, so tick both of these two and it reads two of two. Archiving just hides a finished job out of the way. Nothing is lost, and the Archived chip at the end of the row brings them back with Restore selected. Deleting is the other thing entirely. It asks you first, it warns you that all the blinds, items and appointments go too, and it refuses any job with payments recorded against it.', 3],
            ['1:00', 'From quote to order.',                 'Click a quote number and the Quote actions panel is at the top. Accepting is what turns a quote into an order. If you are ready to place it there and then, use Save as order. It accepts the job and carries you straight on to the place-order screen. Two things happen quietly: a deposit is worked out from your default, and a placeholder fitting drops into the calendar, in the Pending Fitting tray, for you to drag onto the right date. The buttons change with the state of the job, and a fitter may not see the order-side buttons at all.', 4],
            ['1:24', 'Placing it: workshop and suppliers.',  'Placing it splits the job in two, and the screen tells you so at the top: each supplier below gets an email with only their lines and a spec PDF. Every group lists those lines underneath it, product by product, with the fabric and colour, the size, the quantity and the room, so you can check before you send. Your own products need no email at all: they go to the workshop. A supplier you have already emailed for this job comes back unticked, so you cannot double-order by accident. And here is the important one: if every line is something you make yourself, you never see this screen. Accepting already placed it, and it tells you so: sent straight to the workshop, all in-house, no supplier order needed.', 5],
            ['1:48', 'Where is it? The fulfilment stage.',   'Once an order is placed it gets a stage of its own, worked out for you. You never set it. Confirmed means it has landed. In Production means it is on the floor, or the bought-in orders are out. Ready is the one that matters: every blind made, and every bought-in item in. Dispatched is out of the door. Note this stage is not on the orders list. The list shows Ordered, Fitted, Invoiced and Paid. The stage lives in the Factory app, on Incoming Orders, and it is one pill, not four: just the stage the job is at now, sitting next to how many blinds are made and a pill for anything bought in.', 6],
            ['2:10', 'Invoice them.',                        'Now invoice them. The button only shows once the job is an order. If it is missing, the job is not placed yet, and it will tell you: you can invoice once the job is ordered. It needs the customer email, and it stops you if there is not one. What the invoice is, is the same document as the quote, headed Invoice, plus your bank details. Those bank details only print if you have filled them in, on the Quoting tab in Settings, under Bank details for customer payments. And if any money has been taken already, a Paid line and a Balance due line appear under the total. On a first invoice with nothing paid yet they are simply not there. Send it twice and it stops you and makes you use Resend invoice.', 7],
            ['2:34', 'Paid, on its own.',                    'And the last one does itself. There is no Mark as paid button and there never was. Paid happens on its own the moment the deposit and the payments cover the total, and it un-happens if money is taken back out. So just record the payment and the status follows. That is the whole arc: accepted, placed, made, invoiced, paid.', 8],
        ],
];
