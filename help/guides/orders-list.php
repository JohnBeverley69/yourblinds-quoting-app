<?php
declare(strict_types=1);

/**
 * Guide: orders-list
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /orders/index.php — the single list page that four sidebar links
 * land on. Teaches the four axes that decide which rows you see (scope,
 * retail/trade type, status chip, search), how to read a row, and the two
 * bulk actions (archive / delete) with the payments guard.
 *
 * Every string, colour and control here is taken from orders/index.php,
 * orders/archive.php and quote-history/bulk_delete.php. The search box is
 * the only genuinely typed field, so it uses a guide-local fill rule that
 * holds through steps 5–8 (the shared f1..f5 engine stops at step 5).
 */

return [
        'aud'     => 'admin',
        'section' => 'Orders',
        'title'   => 'Finding a job: the Orders list',
        'eyebrow' => 'Orders',
        'blurb'   => 'One list, four ways to look at it: Quotes or Orders, Retail or Trade, the status chips and the search box — plus ticking rows to archive or delete.',
        'lede'    => 'Every quote and every order lives in the <b>same list</b>. Which rows you see depends
                      on four things: whether you came in through <b>Quotes</b> or <b>Orders</b>, whether you
                      came in through <b>Retail</b> or <b>Trade</b>, which <b>status chip</b> is lit, and what
                      is in the <b>search box</b>. Once you can read those four, you can find any job in
                      seconds.',
        'open'    => '/orders/index.php?scope=orders&type=retail',
        'css'     => '
          /* ---------- sidebar drawn in the real "two worlds" shape ---------- */
          .gd .navh{ font-size:.54rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin:.6rem 0 .1rem; padding:0 .5rem; }
          .gd .nRO, .gd .nRQ, .gd .nTO{ background:transparent; color:var(--nav-ink); }
          .gd .app:has(.stage[data-step="1"]) .nRO,
          .gd .app:has(.stage[data-step="4"]) .nRO,
          .gd .app:has(.stage[data-step="5"]) .nRO,
          .gd .app:has(.stage[data-step="6"]) .nRO,
          .gd .app:has(.stage[data-step="7"]) .nRO,
          .gd .app:has(.stage[data-step="8"]) .nRO,
          .gd .app:has(.stage[data-step="2"]) .nRQ,
          .gd .app:has(.stage[data-step="3"]) .nTO{ background:rgba(91,155,255,.16) !important; color:#fff !important; }
          .gd .app:has(.stage[data-step="1"]) .nRO,
          .gd .app:has(.stage[data-step="2"]) .nRQ,
          .gd .app:has(.stage[data-step="3"]) .nTO{ box-shadow:0 0 0 2px #5b9bff; }

          /* ---------- page header ---------- */
          .gd .olt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .ols{ font-size:.7rem; color:var(--faint); margin:.12rem 0 .45rem; }
          .gd .ringtrade{ border-radius:5px; padding:0 .12rem; }
          .gd .stage[data-step="3"] .ringtrade{ box-shadow:0 0 0 2px var(--accent); background:var(--accent-wash); }
          .gd .hdrow{ display:flex; align-items:flex-start; gap:.6rem; flex-wrap:wrap; }
          .gd .hdrow .hleft{ flex:1; min-width:11rem; }
          .gd .tray{ display:inline-flex; background:var(--bg-subtle-2,#f1f3f6); border-radius:8px; padding:.12rem; }
          .gd .tray span{ padding:.2rem .7rem; border-radius:6px; font-size:.72rem; font-weight:600; color:var(--faint); }
          .gd .tray span.on{ background:var(--surface); color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.08); }
          .gd .newq{ background:var(--accent); color:#fff; border-radius:8px; padding:.32rem .7rem; font-size:.75rem; font-weight:700; white-space:nowrap; }
          .gd .stage[data-step="1"] .tray, .gd .stage[data-step="1"] .newq{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- filter chips: rounded pill LINKS with a count ---------- */
          .gd .chips{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin:.55rem 0 .5rem; }
          .gd .chip{ display:inline-block; padding:.16rem .55rem; font-size:.7rem; border-radius:999px;
                     background:var(--bg-subtle-2,#f1f3f6); color:var(--soft); border:1px solid transparent; white-space:nowrap; }
          .gd .chip.on{ background:var(--accent); color:#fff; }
          .gd .chip.right{ margin-left:auto; }
          .gd .cbar{ display:none; }
          .gd .stage[data-step="2"] .cbarQ{ display:flex; }
          .gd .stage[data-step="3"] .cbarT{ display:flex; }
          .gd .stage[data-step="0"] .cbarO, .gd .stage[data-step="1"] .cbarO,
          .gd .stage[data-step="4"] .cbarO, .gd .stage[data-step="5"] .cbarO,
          .gd .stage[data-step="6"] .cbarO, .gd .stage[data-step="7"] .cbarO{ display:flex; }
          /* Which chip is lit. The real page always gives "All" class="active" while
             no status filter is set (orders/index.php, the All chip link), so All is
             lit at rest too — the poster must not black the whole bar out.
             Ordered takes over only once step 4 clicks it. */
          .gd .stage[data-step="4"] .cAll{ background:var(--bg-subtle-2,#f1f3f6); color:var(--soft); }
          .gd .stage[data-step="4"] .cOrd{ background:var(--accent); color:#fff; box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---------- search row ---------- */
          .gd .srch{ display:flex; gap:.4rem; align-items:center; margin-bottom:.5rem; }
          .gd .srch .box{ flex:1; height:28px; font-size:.72rem; }
          .gd .srch .box .ph{ font-size:.7rem; }
          .gd .sbtn{ border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); color:var(--soft);
                     border-radius:7px; padding:.24rem .6rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .clr{ visibility:hidden; }
          .gd .stage[data-step="5"] .clr, .gd .stage[data-step="6"] .clr,
          .gd .stage[data-step="7"] .clr{ visibility:visible; }
          /* the ONE typed field — held open past step 5, which fN cannot do */
          .gd .stage[data-step="5"] .qsrch .ph, .gd .stage[data-step="6"] .qsrch .ph,
          .gd .stage[data-step="7"] .qsrch .ph, .gd .stage[data-step="8"] .qsrch .ph{ opacity:0; }
          .gd .stage[data-step="5"] .qsrch .val, .gd .stage[data-step="6"] .qsrch .val,
          .gd .stage[data-step="7"] .qsrch .val, .gd .stage[data-step="8"] .qsrch .val{ opacity:1; }
          .gd .stage[data-step="5"] .qsrch{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="5"] .qsrch .val{ animation:gdRoll .8s ease-out both; }

          /* ---------- bulk bar: grey until something is ticked ---------- */
          .gd .bulkbar{ display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; margin:0 0 .45rem; }
          .gd .bbtn{ border-radius:7px; padding:.22rem .6rem; font-size:.72rem; font-weight:600;
                     border:1px solid var(--line); background:var(--bg-subtle-2,#f1f3f6); color:var(--faint); white-space:nowrap; }
          .gd .bcount{ font-size:.68rem; color:var(--faint); }
          .gd .bcOn{ display:none; }
          .gd .stage[data-step="7"] .bcOff{ display:none; }
          .gd .stage[data-step="7"] .bcOn{ display:inline; }
          .gd .stage[data-step="7"] .bArch{ border-color:var(--border-strong,#c7ccd4); background:var(--surface); color:var(--soft); }
          .gd .stage[data-step="7"] .bDel{ border-color:var(--err); background:var(--err); color:#fff; }
          /* the ticks themselves, and the header box going part-way (indeterminate) */
          .gd .stage[data-step="7"] .tkA, .gd .stage[data-step="7"] .tkD,
          .gd .stage[data-step="7"] .tickAll{ background:var(--accent); color:#fff; }

          /* ---------- the table ---------- */
          .gd .otbl{ width:100%; border-collapse:collapse; font-size:.68rem; }
          .gd .otbl th{ text-align:left; font-size:.53rem; text-transform:uppercase; letter-spacing:.04em;
                        color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.26rem .3rem; white-space:nowrap; }
          .gd .otbl td{ padding:.3rem .3rem; border-bottom:1px solid var(--line-2); color:var(--ink); vertical-align:top; }
          .gd .otbl .r{ text-align:right; }
          .gd .otbl .c{ text-align:center; }
          .gd .qlink{ font-weight:700; color:var(--ink); }
          .gd .supp{ display:block; margin-top:.14rem; font-size:.58rem; color:var(--accent); white-space:nowrap; }
          .gd .spill{ display:inline-block; padding:.02rem .42rem; font-size:.55rem; font-weight:700;
                      text-transform:uppercase; letter-spacing:.05em; border-radius:999px; color:#fff; }
          .gd .nsent{ display:inline-block; margin-left:.2rem; font-size:.53rem; font-weight:700; text-transform:uppercase;
                      letter-spacing:.03em; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:999px; padding:.02rem .36rem; }
          .gd .dpaid{ color:#065f46; font-weight:700; }
          .gd .ddue{ color:#92400e; font-weight:700; }
          .gd .owed{ color:#92400e; font-weight:700; border-bottom:1px dashed #92400e; }
          .gd .allpaid{ color:#065f46; font-weight:700; }
          .gd .dash{ color:var(--faint); }
          .gd .crt{ color:var(--faint); white-space:nowrap; }
          /* order-only columns vanish on the quotes side, exactly as they really do */
          .gd .stage[data-step="2"] .ordcol{ display:none; }
          /* which body of rows is on screen */
          .gd .tb{ display:none; }
          .gd .stage[data-step="2"] .tbQ{ display:table-row-group; }
          .gd .stage[data-step="3"] .tbT{ display:table-row-group; }
          .gd .stage[data-step="0"] .tbR, .gd .stage[data-step="1"] .tbR,
          .gd .stage[data-step="4"] .tbR, .gd .stage[data-step="5"] .tbR,
          .gd .stage[data-step="6"] .tbR, .gd .stage[data-step="7"] .tbR{ display:table-row-group; }
          /* the chip narrows the rows; the search narrows them again */
          .gd .stage[data-step="4"] .rowB, .gd .stage[data-step="4"] .rowC{ display:none; }
          .gd .stage[data-step="5"] .rowB, .gd .stage[data-step="5"] .rowC, .gd .stage[data-step="5"] .rowD,
          .gd .stage[data-step="6"] .rowB, .gd .stage[data-step="6"] .rowC, .gd .stage[data-step="6"] .rowD{ display:none; }
          .gd .stage[data-step="6"] .rowA{ box-shadow:inset 0 0 0 2px var(--accent); }

          /* ---------- the "reading a row" key (step 6) ---------- */
          .gd .rowkey{ display:none; margin-top:.5rem; border:1px solid var(--line); border-radius:9px;
                       background:var(--panel); padding:.5rem .65rem; font-size:.66rem; color:var(--soft); line-height:1.55; }
          .gd .stage[data-step="6"] .rowkey{ display:block; }
          .gd .rowkey b{ color:var(--ink); }
          .gd .rowkey div + div{ margin-top:.18rem; }

          /* ---------- step 8: the confirm, the red flash, the archive drawer ---------- */
          .gd .scMain{ display:none; }
          .gd .stage:not([data-step="8"]) .scMain{ display:block; }
          .gd .sc8{ display:none; }
          .gd .stage[data-step="8"] .sc8{ display:block; }
          .gd .cfm{ border:1px solid var(--border-strong,#c7ccd4); border-radius:10px; background:var(--surface);
                    box-shadow:0 10px 26px -14px rgba(20,30,45,.45); max-width:24rem; overflow:hidden; }
          .gd .cfm .cfmh{ background:var(--panel); border-bottom:1px solid var(--line); padding:.32rem .65rem;
                          font-size:.6rem; color:var(--faint); letter-spacing:.04em; text-transform:uppercase; font-weight:700; }
          .gd .cfm .cfmb{ padding:.6rem .65rem; font-size:.74rem; color:var(--ink); line-height:1.5; }
          .gd .cfm .cfmf{ display:flex; gap:.4rem; justify-content:flex-end; padding:0 .65rem .6rem; }
          .gd .cfm .cbt{ border-radius:6px; padding:.2rem .7rem; font-size:.72rem; font-weight:600;
                         border:1px solid var(--border-strong,#c7ccd4); color:var(--soft); background:var(--surface); }
          .gd .cfm .cbt.ok{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .sc8 .errbanner{ margin-top:.6rem; }
          .gd .sc8 .drawer{ margin-top:.6rem; }
          .gd .sc8 .dnote{ font-size:.66rem; color:var(--faint); margin-top:.35rem; line-height:1.55; }
          .gd .sc8 .dnote b{ color:var(--ink); }
          @media(max-width:620px){ .gd .otbl{ font-size:.62rem; } .gd .hdrow{ flex-direction:column; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk/orders</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a>Pipeline</a><a>Factory</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a class="nRQ">Quotes</a><a class="nRO">Orders</a>
                <div class="navh">Trade</div>
                <a>Quotes</a><a class="nTO">Orders</a>
              </div>

              <div class="stage" id="gdStage" data-step="0">

                <!-- page header: the title IS the answer to "which view am I in?" -->
                <div class="hdrow">
                  <div class="hleft">
                    <div class="olt"><span class="ringtrade">Retail</span> Orders</div>
                    <div class="ols">Accepted onward &mdash; orders, invoices and paid jobs.</div>
                    <div class="tray"><span class="on">List</span><span>Pipeline</span></div>
                  </div>
                  <div class="newq">+ New quote</div>
                </div>

                <!-- steps 0-7: the real list -->
                <div class="scMain">

                  <!-- chips: one bar per view, only one on screen at a time -->
                  <div class="chips cbar cbarO">
                    <span class="chip on cAll">All (53)</span>
                    <span class="chip">Accepted (4)</span>
                    <span class="chip cOrd">Ordered (7)</span>
                    <span class="chip">Fitted (2)</span>
                    <span class="chip">Invoiced (9)</span>
                    <span class="chip">Paid (31)</span>
                  </div>
                  <div class="chips cbar cbarQ">
                    <span class="chip on">All (15)</span>
                    <span class="chip">Quote (12)</span>
                    <span class="chip">Declined (3)</span>
                  </div>
                  <div class="chips cbar cbarT">
                    <span class="chip on">All (18)</span>
                    <span class="chip">Accepted (2)</span>
                    <span class="chip">Ordered (6)</span>
                    <span class="chip">Invoiced (4)</span>
                    <span class="chip">Paid (6)</span>
                  </div>

                  <!-- search: input + Search button + Clear link (Clear only once there is text) -->
                  <div class="srch">
                    <div class="box qsrch"><span class="ph">Search by quote #, customer name, or postcode...</span><span class="val">PRE-2026-0042</span></div>
                    <span class="sbtn">Search</span>
                    <span class="sbtn clr">Clear</span>
                  </div>

                  <!-- bulk bar: both buttons start disabled-grey -->
                  <div class="bulkbar">
                    <span class="bbtn bArch">&#128451; Archive selected</span>
                    <span class="bbtn bDel">Delete selected</span>
                    <span class="bcount"><span class="bcOff">(none selected)</span><span class="bcOn">(2 of 4 selected)</span></span>
                  </div>

                  <div class="table-wrap">
                    <table class="otbl">
                      <thead>
                        <tr>
                          <th class="c"><span class="tick tickAll">&ndash;</span></th>
                          <th>Quote #</th><th>Customer</th><th>Postcode</th><th>Status</th><th>Created</th>
                          <th class="r">Total</th>
                          <th class="ordcol">Deposit</th>
                          <th class="r ordcol">Outstanding</th>
                        </tr>
                      </thead>

                      <!-- RETAIL ORDERS -->
                      <tbody class="tb tbR">
                        <tr class="rowA">
                          <td class="c"><span class="tick tkA">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0042</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Emma Fletcher</td><td>LS17 8AB</td>
                          <td><span class="spill" style="background:#0891b2">Ordered</span></td>
                          <td class="crt">14 Sep 2026</td>
                          <td class="r">&pound;486.00</td>
                          <td class="ordcol"><span class="dpaid">&check; &pound;150.00 paid</span></td>
                          <td class="r ordcol"><span class="owed">&pound;336.00</span></td>
                        </tr>
                        <tr class="rowD">
                          <td class="c"><span class="tick tkD">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0047</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Neil Braithwaite</td><td>LS28 7HG</td>
                          <td><span class="spill" style="background:#0891b2">Ordered</span></td>
                          <td class="crt">12 Sep 2026</td>
                          <td class="r">&pound;929.00</td>
                          <td class="ordcol"><span class="ddue">&pound;250.00 due</span></td>
                          <td class="r ordcol"><span class="owed">&pound;679.00</span></td>
                        </tr>
                        <tr class="rowB">
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0051</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Raj Patel</td><td>WF1 2QD</td>
                          <td><span class="spill" style="background:#ea580c">Invoiced</span></td>
                          <td class="crt">9 Sep 2026</td>
                          <td class="r">&pound;1,240.00</td>
                          <td class="ordcol"><span class="ddue">&pound;300.00 due</span></td>
                          <td class="r ordcol"><span class="owed">&pound;940.00</span></td>
                        </tr>
                        <tr class="rowC">
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0038</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Susan Wrigley</td><td>HG2 0LT</td>
                          <td><span class="spill" style="background:#475569">Paid</span></td>
                          <td class="crt">2 Sep 2026</td>
                          <td class="r">&pound;312.00</td>
                          <td class="ordcol"><span class="dpaid">&check; &pound;120.00 paid</span></td>
                          <td class="r ordcol"><span class="allpaid">&check; paid</span></td>
                        </tr>
                      </tbody>

                      <!-- RETAIL QUOTES (no Deposit / Outstanding columns) -->
                      <tbody class="tb tbQ">
                        <tr>
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0061</span></td>
                          <td>Dan Yeadon</td><td>LS6 3NN</td>
                          <td><span class="spill" style="background:#f59e0b">Quote</span><span class="nsent">Not sent</span></td>
                          <td class="crt">17 Sep 2026</td>
                          <td class="r">&pound;402.00</td>
                        </tr>
                        <tr>
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0059</span></td>
                          <td>Alison Kerr</td><td>BD18 4LW</td>
                          <td><span class="spill" style="background:#f59e0b">Quote</span></td>
                          <td class="crt">16 Sep 2026</td>
                          <td class="r">&pound;755.00</td>
                        </tr>
                        <tr>
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0044</span></td>
                          <td>Mark Hoyle</td><td>LS25 1PP</td>
                          <td><span class="spill" style="background:#dc2626">Declined</span></td>
                          <td class="crt">12 Sep 2026</td>
                          <td class="r">&pound;188.00</td>
                        </tr>
                      </tbody>

                      <!-- TRADE ORDERS -->
                      <tbody class="tb tbT">
                        <tr>
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0112</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Kirkstall Interiors</td><td>LS5 3BY</td>
                          <td><span class="spill" style="background:#0891b2">Ordered</span></td>
                          <td class="crt">16 Sep 2026</td>
                          <td class="r">&pound;2,410.00</td>
                          <td class="ordcol"><span class="dpaid">&check; &pound;900.00 paid</span></td>
                          <td class="r ordcol"><span class="owed">&pound;1,510.00</span></td>
                        </tr>
                        <tr>
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0108</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Harrogate Shutters</td><td>HG1 5QT</td>
                          <td><span class="spill" style="background:#ea580c">Invoiced</span></td>
                          <td class="crt">11 Sep 2026</td>
                          <td class="r">&pound;1,065.00</td>
                          <td class="ordcol"><span class="dash">&mdash;</span></td>
                          <td class="r ordcol"><span class="allpaid">&check; paid</span></td>
                        </tr>
                        <tr>
                          <td class="c"><span class="tick">&check;</span></td>
                          <td><span class="qlink">PRE-2026-0097</span><span class="supp">&#128230; Send to suppliers</span></td>
                          <td>Vale Blinds Ltd</td><td>YO23 1AA</td>
                          <td><span class="spill" style="background:#475569">Paid</span></td>
                          <td class="crt">4 Sep 2026</td>
                          <td class="r">&pound;640.00</td>
                          <td class="ordcol"><span class="dpaid">&check; &pound;200.00 paid</span></td>
                          <td class="r ordcol"><span class="allpaid">&check; paid</span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- step 6 only: what each cell in that row is telling you -->
                  <div class="rowkey">
                    <div><b>Quote #</b> &mdash; click it to open the job.</div>
                    <div><b>&#128230; Send to suppliers</b> &mdash; the small link underneath: sends this order to its suppliers without opening it.</div>
                    <div><b>Status</b> &mdash; your own colour from Settings &rarr; Status colours.</div>
                    <div><b>Deposit</b> &mdash; &ldquo;&check; &pound;150.00 paid&rdquo; or &ldquo;&pound;150.00 due&rdquo;, and &ldquo;&mdash;&rdquo; when no deposit was set.</div>
                    <div><b>Outstanding</b> &mdash; still owed. The dashed underline means it is a link: it opens Payments with this order already picked.</div>
                  </div>
                </div>

                <!-- step 8: the delete confirm, the red flash, and the archive drawer -->
                <div class="sc8">
                  <div class="cfm">
                    <div class="cfmh">yourblinds.uk says</div>
                    <div class="cfmb">Delete the selected quotes? This is permanent &mdash; all blinds, items and appointments go too.</div>
                    <div class="cfmf"><span class="cbt">Cancel</span><span class="cbt ok">OK</span></div>
                  </div>
                  <div class="errbanner"><b>!</b><span>1 quote deleted. 1 quote was kept because it has payment(s) recorded against it: PRE-2026-0042. Delete the payments first if you really want to remove these.</span></div>
                  <div class="drawer">
                    <div class="chips">
                      <span class="chip on">All (51)</span>
                      <span class="chip">Accepted (4)</span>
                      <span class="chip">Ordered (6)</span>
                      <span class="chip">Fitted (2)</span>
                      <span class="chip">Invoiced (9)</span>
                      <span class="chip">Paid (30)</span>
                      <span class="chip right">&#128451; Archived (6)</span>
                    </div>
                    <div class="chips">
                      <span class="chip right on">&larr; Back to active</span>
                    </div>
                    <div class="dnote">The <b>&#128451; Archived (6)</b> chip sits on the far right of the same bar. Click it and it
                      becomes <b>&larr; Back to active</b> &mdash; that is how you get out again.</div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Retail &rarr; Orders. The title tells you where you are.</b>
                  <b class="c2"><span class="n">2</span> Quotes and Orders are the same page, different statuses.</b>
                  <b class="c3"><span class="n">3</span> Retail is your customers. Trade is your accounts.</b>
                  <b class="c4"><span class="n">4</span> A chip with nothing in it isn&rsquo;t shown at all.</b>
                  <b class="c5"><span class="n">5</span> Quote number, customer name, or postcode &mdash; any part of it.</b>
                  <b class="c6"><span class="n">6</span> Click the amount owed &mdash; it takes you straight to Payments.</b>
                  <b class="c7"><span class="n">7</span> Archive hides. Delete destroys. They are not the same button.</b>
                  <b class="c8 good"><span class="n">8</span> Archived jobs are never gone &mdash; they&rsquo;re behind the Archived chip.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>One page wearing four hats.</b> There is only <em>one</em> list of jobs in YourBlinds, and the
             menu on the left points at it from four different doors: <b>Retail &rarr; Quotes</b>,
             <b>Retail &rarr; Orders</b>, <b>Trade &rarr; Quotes</b> and <b>Trade &rarr; Orders</b>. They all open
             the same screen. What changes is <b>which rows it lets through</b>, and that comes down to four
             things, in this order:</p>
          <ul class="steps">
            <li><b>Scope &mdash; Quotes or Orders.</b> A job is a <em>quote</em> until the customer accepts it,
                and an <em>order</em> from then on. The <b>Quotes</b> door shows the not-yet-accepted end
                (draft, sent, declined) and is headed &ldquo;<em>Quotes still in the pipeline &mdash; drafts,
                sent, and declined.</em>&rdquo; The <b>Orders</b> door shows everything from acceptance onward
                (accepted, ordered, fitted, invoiced, paid) and is headed &ldquo;<em>Accepted onward &mdash;
                orders, invoices and paid jobs.</em>&rdquo; Read the line under the title and you always know
                which side you are on.</li>
            <li><b>Retail or Trade.</b> <b>Retail</b> is your own end customers. <b>Trade</b> is the accounts you
                supply as a business. The heading picks up the word &mdash; <b>Retail Orders</b>,
                <b>Trade Quotes</b> &mdash; and that prefix is the only thing on screen telling you a filter is on,
                so get in the habit of reading it. (Only a super-admin sees the Trade section at all.)
                Which side a job lands on is decided <em>once</em>, when the job is created, and nothing
                afterwards moves it: <b>there is no control on this list, and none inside the job, that flips a
                job from retail to trade or back</b>. So do not go hunting for one &mdash; if a job is on the
                wrong side, it was raised on the wrong side, and the only cure is to raise it again on the side
                you wanted and archive or delete the one you did not. For a super-admin, which side the
                sidebar&rsquo;s big <b>+ New</b> button opens on is set in
                <b>Settings &rarr; Quoting</b> under <b>Default sale type</b> &mdash; and that fieldset is itself
                super-admin only, so an ordinary admin will not find it on their Settings page.</li>
            <li><b>The status chip.</b> The row of little rounded chips under the heading. One chip per stage,
                with its count in brackets.</li>
            <li><b>The search box.</b> Narrows whatever the first three have already left on screen.</li>
          </ul>

          <p><b>Reading the chips.</b> The first chip is always <b>All (53)</b> &mdash; every job in this view.
             After it, on the orders side: <b>Accepted (4)</b>, <b>Ordered (7)</b>, <b>Fitted (2)</b>,
             <b>Invoiced (9)</b>, <b>Paid (31)</b>. On the quotes side there are just two:
             <b>Quote (12)</b> (drafts and sent quotes together &mdash; a draft is only a quote you haven&rsquo;t
             sent yet) and <b>Declined (3)</b>. The number in brackets already takes account of which side you
             are on, so the chips on the Trade page count trade jobs only. Click a chip to keep just those rows;
             click <b>All</b> to come back. <b>A chip whose count is zero is not drawn at all</b> &mdash; if you
             cannot find a <b>Declined</b> chip, it is because nothing has been declined. Nothing is broken and
             nothing is missing.</p>

          <p><b>Searching.</b> The box reads <em>&ldquo;Search by quote #, customer name, or postcode...&rdquo;</em>
             and it does exactly those three. Any part of any of them will do &mdash; type
             <code>Fletcher</code>, or <code>LS17</code>, or <code>0042</code>. Press <b>Search</b>. A
             <b>Clear</b> button appears next to it once there is something in the box; that is how you get the
             whole list back. The important thing to remember: <b>search only looks inside the view you are
             already in</b>. If a job will not come up, the search is not at fault &mdash; you are probably
             standing on the wrong side of one of the other three filters. Click <b>All</b>, and check whether
             the heading says Retail when you wanted Trade, or Quotes when you wanted Orders.</p>

          <p><b>Reading a row.</b> Left to right:</p>
          <ul class="steps">
            <li><b>The tick box</b> &mdash; for the bulk actions, below.</li>
            <li><b>Quote #</b> &mdash; in bold. Click it and the job opens.</li>
            <li><b>&#128230; Send to suppliers</b> &mdash; a small link tucked underneath the quote number, on
                order rows only. It sends that order straight to its suppliers without you opening the job first.
                Hover it and it says &ldquo;<em>Send this order to its suppliers</em>&rdquo;. It is only there if
                you are allowed to create orders.</li>
            <li><b>Customer</b> and <b>Postcode</b> &mdash; as typed on the job.</li>
            <li><b>Status</b> &mdash; a coloured capitals pill. The colours are <em>yours</em>, from
                <a href="/help/guide.php?g=settings-colours"><b>Settings &rarr; Status colours</b></a>, so a job is
                the same colour here, on the calendar and on the dashboard. On the quotes side a draft carries a
                second small amber badge, <b>Not sent</b>, meaning
                &ldquo;<em>This quote hasn&rsquo;t been sent to the customer yet</em>&rdquo;.</li>
            <li><b>Created</b> &mdash; the date the job was started, as <code>14 Sep 2026</code>. Do not expect
                this column to count neatly down the page, because <b>the list is not sorted by it</b>. Rows come
                newest-first on <b>the date the job was accepted</b>, falling back to Created for anything not
                accepted yet. So on the quotes side the two are the same thing and Created does run in order; on
                the orders side a job written in July but accepted this morning sits at the very top, above one
                written last week. That is the sort working, not a fault.</li>
            <li><b>Total</b> &mdash; the job&rsquo;s value.</li>
            <li><b>Deposit</b> &mdash; orders side only. Either a green <b>&check; &pound;150.00 paid</b>, an amber
                <b>&pound;150.00 due</b>, or a plain <b>&mdash;</b> when no deposit was ever set on that job.</li>
            <li><b>Outstanding</b> &mdash; orders side only, <em>and</em> only when the paid <b>Accounts</b>
                add-on is switched on for your account. That is why a list on one account has this column and a
                list on another does not. When money is owed it is an amber figure with a dashed underline, which
                means it is a <b>link</b>: click it and Payments opens with that order already chosen
                (&ldquo;<em>Click to take a payment against this order</em>&rdquo;). When it is settled it reads a
                green <b>&check; paid</b>, and if you have taken too much it shows a blue <b>+&pound;20.00</b>
                marked <em>Overpaid</em>.</li>
          </ul>

          <p><b>Ticking rows: the two bulk actions.</b> Tick the box at the left of any row, or the box in the
             header to take everything on the page at once (that one is labelled
             &ldquo;<em>Select all visible quotes</em>&rdquo;, and it shows a dash rather than a tick when you
             have only some of them). The counter beside the buttons keeps up: <b>(none selected)</b> becomes
             <b>(3 of 12 selected)</b>. Until something is ticked, <b>both buttons are greyed out and will not
             respond</b> &mdash; that is normal, not a fault.</p>
          <ul class="steps">
            <li><b>&#128451; Archive selected</b> &mdash; the safe one. It moves old jobs out of the everyday list
                without destroying anything, and the green bar afterwards reads <b>&ldquo;3 jobs archived.&rdquo;</b>
                They reappear behind the <b>&#128451; Archived (6)</b> chip on the far right of the chip bar,
                which turns into <b>&larr; Back to active</b> while you are in there. Tick them there and the
                button now reads <b>Restore selected</b>: <b>&ldquo;1 job restored to active.&rdquo;</b>
                <em>(Two different reasons the chip may not be there, and only one of them is a problem.
                <b>No Archived chip but the Archive button is present</b> &mdash; perfectly normal: the chip is
                only drawn once there is at least one archived job, so on an account that has never archived
                anything there is nothing to show. Archive something and it appears. <b>No Archive button
                either</b> &mdash; that is the other one: archiving has not been switched on for your database
                yet, and it needs turning on before any of this works.)</em></li>
            <li><b>Delete selected</b> &mdash; the red one. Read the next box before you touch it.</li>
          </ul>

          <div class="oops"><b>Delete is not archive.</b> <b>Delete selected</b> removes the job and everything on
             it &mdash; blinds, items and appointments. The browser asks once:
             <em>&ldquo;Delete the selected quotes? This is permanent &mdash; all blinds, items and appointments go
             too.&rdquo;</em> and that is your only chance to back out. One thing surprises people: a job that has
             a <b>payment</b> recorded against it is <b>refused</b>. The others still go, that one stays, and the
             message names it in a <em>red</em> bar even though part of it worked:
             <em>&ldquo;1 quote deleted. 1 quote was kept because it has payment(s) recorded against it:
             PRE-2026-0042. Delete the payments first if you really want to remove these.&rdquo;</em> That guard is
             deliberate &mdash; a payment with no order behind it would be a hole in your books. If you truly want
             it gone, clear its payments in <b>Payments</b> first. Far better: <b>archive it instead</b>.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Three filter traps, all the same lesson: the
             filters do not stack the way you expect.</b>
             <b>One</b> &mdash; clicking any chip <em>wipes whatever you had typed in the search box</em>. The chips
             only carry the side you are on and the status, nothing else. Search first, then read; do not search and
             then start clicking chips.
             <b>Two</b> &mdash; searching while you are inside the <b>Archived</b> drawer quietly puts you back in
             the active list, with no message. Come out of Archived first, or search and then go back in.
             <b>Three</b> &mdash; the <b>Pipeline</b> button in the little grey tray at the top does <em>not</em>
             carry your Retail/Trade choice, or Quotes/Orders. Click it from <b>Trade Orders</b> and you land on the
             whole pipeline, every job on it. Your filter has not broken; Pipeline simply does not have one.</div></div>

          <p><b>What this list will not do.</b> It shows you jobs; it does not move them along. You change a
             job&rsquo;s stage inside the job itself, and the workshop&rsquo;s own progress lives in
             <b>Factory</b>, not here &mdash; so do not expect a row to change colour because the bench has
             finished it. One piece of housekeeping worth knowing: the list stops at <b>two hundred rows</b> in
             whichever view you are in, and it does not tell you when it has stopped there &mdash; there are no
             page buttons. Those two hundred are the <b>two hundred most recently accepted</b> jobs (and for
             anything not yet accepted, the most recently created), which is also the order they sit in on the
             page &mdash; not the Created order. So if an older job will not appear no matter how you filter,
             don&rsquo;t scroll for it: <b>search for it by name, postcode or quote number</b>.</p>

          <p><b>Who sees what.</b> The <b>+ New quote</b> button at the top right is only there if you are allowed
             to create quotes. Someone who only fits &mdash; a fitter without the see-everything permission &mdash;
             sees only the jobs they are actually booked on, both in the rows and in the chip counts, and if they
             have none the page simply says
             &ldquo;<em>Quotes you&rsquo;re assigned to fit will appear here.</em>&rdquo;. Two other empty messages
             you may meet: &ldquo;<em>Nothing matches your filter.</em>&rdquo; with a <b>Clear filters</b> link
             (a filter or a search is on and nothing survived it), and &ldquo;<em>No quotes yet.</em>&rdquo; with
             <b>Start a new quote &rarr;</b> on a brand-new account.</p>

          <p><b>Where to go next.</b> Open a row and you are in
             <a href="/help/guide.php?g=quote-build"><b>Building a quote</b></a>. Getting it to the customer and
             back is <a href="/help/guide.php?g=quote-send-accept"><b>Sending &amp; accepting</b></a>. Turning it
             into an order, a supplier order and an invoice is
             <a href="/help/guide.php?g=quote-order-invoice"><b>Ordering &amp; invoicing</b></a>. And the colours in
             the Status column are set in
             <a href="/help/guide.php?g=settings-colours"><b>Settings &rarr; Status colours</b></a>.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Retail → Orders; title, subtitle, List/Pipeline tray, + New quote.',
                     'This is the Orders list, and the very first thing to do is read the title. It says Retail Orders. That tells you two things at once: you are looking at your own retail customers, not your trade accounts, and you are looking at the order end of the job, not the quote end. Underneath it says "Accepted onward — orders, invoices and paid jobs." Top right is the "plus New quote" button. And that little grey tray with List and Pipeline swaps you between this list and the same jobs shown as a board — just be aware that Pipeline does not keep your Retail or Trade filter, so it will show you everything.', 1],
            ['0:26', 'Sidebar highlight moves to Quotes; title and chips swap; order columns vanish.',
                     'Now watch what happens when I click Quotes instead. It is the same page. A job is a quote until the customer accepts it, and an order from then on — so the Quotes door lets through the drafts, the sent quotes and the declined ones, and the Orders door lets through everything from accepted onwards. Notice the chips have changed to just Quote and Declined, and the Deposit and Outstanding columns have gone, because neither of those means anything until a job is an order. You will also see a little amber "Not sent" badge on a draft — that is a quote you have written but not yet sent out.', 2],
            ['0:54', 'Sidebar highlight jumps to Trade → Orders; title becomes Trade Orders.',
                     'The other pair of doors is Retail and Trade. Retail is your own end customers. Trade is the accounts you supply as a business. Same page again — only the heading changes, to Trade Orders. And the Trade section is only in the menu at all if you are a super-admin. Now, the thing worth knowing about these two. A job is stamped retail or trade at the moment it is created, and nothing after that moves it: there is no switch on this list, and there is none inside the job either. So if a job is sitting on the wrong list, it was raised on the wrong side — and the only cure is to raise it again on the side you wanted, then archive or delete the one you did not. If you are the super-admin, which side the big plus-New button in the sidebar starts on is set in Settings, under Quoting, as Default sale type — and that setting is itself super-admin only, so most people will never see it.', 3],
            ['1:18', 'All (53) lit, then Ordered (7) lit; the table narrows.',
                     'Now the chips. Each one is a stage, with the number of jobs sitting at that stage in brackets, and that count already knows which side you are on. Click Ordered and you keep only the ordered jobs. Click All and you get the lot back. Two things to know. First: a chip with nothing in it is not shown at all. If you cannot see a Declined chip, it is because nothing has been declined — nothing is broken. Second, and this one catches people: clicking a chip clears anything you have typed in the search box.', 4],
            ['1:46', 'PRE-2026-0042 types into the search box; one row left; Clear appears.',
                     'The search box. Type a quote number, a customer\'s name, or a postcode — and any part of any of the three will do. Press Search. A Clear button appears beside it, and that puts the whole list back. Here is the honest warning, and it is the one thing that will have you saying a job has vanished. Search only looks inside the view you are already in. So if you cannot find something, widen it: click All first, and check the heading — you may be on the Trade side hunting for a retail job.', 5],
            ['2:12', 'The row is ringed; each cell is named underneath.',
                     'Let us read a row properly. The quote number in bold is a link — click it and the job opens. That tiny link underneath, "Send to suppliers", sends that order off to its suppliers without you having to open it at all. Then the customer, the postcode, and the status pill. Those pill colours are your own, from Settings, Status colours, so a job is the same colour here as it is on your calendar. Deposit tells you whether the money up front has landed. And Outstanding is what is still owed — see the dashed underline? That means it is a link. Click the amount and it takes you straight to Payments with that order already picked out for you. One aside: Outstanding only appears at all if the Accounts add-on is switched on for your account. And one about the Created column before we move on — it is a date, but it is not what the list is sorted by. Rows come down the page newest-accepted first, so on the orders side you will see Created dates that look out of order. Nothing is wrong; a job accepted this morning goes to the top however long ago it was written.', 6],
            ['2:48', 'Two ticks go on; counter updates; both buttons colour up.',
                     'Down the left of every row is a tick box, and there is one in the header that takes the whole page at once. Tick a couple and watch the two buttons above the table: until something is ticked they are grey and they will not do anything, which is normal. Now they wake up, and the counter tells you how many you have got. Archive is the safe one — it just moves old jobs out of the way, you get a green bar saying "3 jobs archived", and you can always bring them back with Restore selected. Then there is the red one. And I want to be blunt about this: Delete is not archive.', 7],
            ['3:16', 'The confirm box, the red kept-rows message, then the Archived chip.',
                     'Delete asks you once — "Delete the selected quotes? This is permanent — all blinds, items and appointments go too." That is your last chance; everything on the job goes with it. Now here is the part that surprises people. A job that has a payment recorded against it will not delete. The others go, that one stays, and the message names it: "one quote was kept because it has payment or payments recorded against it." It comes up in a red bar even though part of it worked. That guard is there on purpose — a payment with no order behind it would leave a hole in your books. And to finish on a happier note: archived jobs are never gone. They are sitting behind the Archived chip on the right-hand end of the chip bar, with the count right there on it, and one click brings you back to active. And if you cannot see that chip yet, it is not broken — it only turns up once there is something archived to look at. Last housekeeping fact: the list stops at two hundred rows, the two hundred most recently accepted, and it does not tell you when it has stopped there — so if an old one will not appear, search for it by name or number rather than scrolling.', 8],
        ],
];
