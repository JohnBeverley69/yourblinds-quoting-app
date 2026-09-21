<?php
declare(strict_types=1);

/**
 * Guide: accounts-money
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /accounts/index.php — the tenant-wide payments ledger (sidebar
 * Retail -> Payments). The sister guide quote-payments covers the money
 * panels ON one order; this one is the whole business at once.
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'The Payments page (Accounts)',
        'eyebrow' => 'Accounts',
        'blurb'   => 'Every payment your business has taken, in one place: what is still owed, what came in this month, and the CSV your bookkeeper wants.',
        'lede'    => 'One page for all the money: the <b>Outstanding</b>, <b>Received this month</b> and <b>All-time received</b> figures at the
                      top, every payment underneath grouped by the order it belongs to, and a <b>+ Record payment</b> box for money that arrives
                      in the post or the bank. Find it in the sidebar under <b>Retail &rarr; Payments</b>. It needs the paid <b>Accounts</b>
                      add-on.',
        'open'    => '/accounts/index.php',
        'css'     => '
          /* scenes — one per beat, switched by the stage step */
          .gd .asc{ display:none; }
          .gd .stage[data-step="0"] .scTop, .gd .stage[data-step="1"] .scTop, .gd .stage[data-step="2"] .scTop{ display:block; }
          .gd .stage[data-step="2"] .scHist, .gd .stage[data-step="3"] .scHist{ display:block; }
          .gd .stage[data-step="4"] .scForm, .gd .stage[data-step="5"] .scForm{ display:block; }
          .gd .stage[data-step="6"] .scDone{ display:block; }
          .gd .stage[data-step="7"] .scOops{ display:block; }
          .gd .stage[data-step="8"] .scCsv{ display:block; }

          /* page header row */
          .gd .hdrow{ display:flex; align-items:flex-start; gap:.6rem; flex-wrap:wrap; justify-content:space-between; }
          .gd .subt{ margin:0; font-size:.72rem; color:var(--faint); }
          .gd .hbtns{ display:flex; gap:.35rem; flex-wrap:wrap; }
          .gd .mbtn{ display:inline-flex; align-items:center; border-radius:8px; padding:.3rem .6rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .mbtn.pri{ background:var(--accent); color:#fff; }
          .gd .mbtn.gh{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          /* the shared confirm dialog styles its confirm button RED, not blue */
          .gd .mbtn.dng{ background:var(--err); color:#fff; }
          .gd .mbtn.grey{ background:var(--line); color:var(--soft); }
          .gd .csvnote{ font-size:.66rem; color:var(--faint); margin:.45rem 0 0; line-height:1.45; }
          .gd .csvnote b{ color:var(--soft); }

          /* the three read-only summary cards (amber / navy / green, as on the page) */
          .gd .scards{ display:grid; grid-template-columns:repeat(3,1fr); gap:.45rem; margin:.7rem 0 .6rem; }
          .gd .scard{ border:1px solid var(--line); border-radius:9px; background:var(--surface); padding:.45rem .55rem; min-height:3rem; }
          .gd .scard .sl{ font-size:.55rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .scard .sv{ font-size:1rem; font-weight:700; margin-top:.15rem; opacity:0; transition:opacity .25s; }
          .gd .scard.out .sv{ color:#b45309; }
          .gd .scard.mon .sv{ color:#1f3b5b; }
          .gd .scard.all .sv{ color:#065f46; }
          .gd .stage[data-step="1"] .scard .sv, .gd .stage[data-step="2"] .scard .sv{ opacity:1; }
          .gd .stage[data-step="1"] .scard .sv{ animation:gdRoll .8s ease-out both; }
          .gd .scards.done .sv{ opacity:1; }

          /* the collapsed / open "Record payment" disclosure */
          .gd .disc{ border:1px solid var(--line); border-radius:9px; background:var(--surface); padding:.42rem .6rem; font-size:.78rem; font-weight:600; color:var(--accent); }
          .gd .disc::before{ content:"\25B8 "; color:var(--faint); }
          .gd .disc.open{ border-bottom-left-radius:0; border-bottom-right-radius:0; border-bottom:none; }
          .gd .disc.open::before{ content:"\25BE "; color:var(--accent); }

          /* the record / edit form itself */
          .gd .npwrap{ border:1px solid var(--line); border-top:none; border-radius:0 0 9px 9px; background:var(--surface); padding:.55rem .6rem .6rem; }
          .gd .npg{ display:flex; flex-wrap:wrap; gap:.45rem .6rem; align-items:flex-end; }
          .gd .npg .fld{ flex:0 0 8.4rem; }
          .gd .npg .fwide{ flex:1 1 100%; }
          .gd .fwide .selectbox{ width:100%; font-size:.72rem; min-width:0; }
          .gd .npa{ display:flex; gap:.4rem; margin-top:.6rem; }
          .gd .depnote{ margin:.3rem 0 0; font-size:.66rem; color:#92400e; line-height:1.45; display:none; }
          .gd .stage[data-step="4"] .depnote, .gd .stage[data-step="5"] .depnote{ display:block; }

          /* field fills — this guides typing happens at steps 4 and 5, so the
             shared f1..f5 classes (hard-wired to steps 1..5) are no use here */
          .gd .selectbox .sph{ color:var(--faint); }
          .gd .selectbox .sval{ display:none; color:var(--ink); }
          .gd .stage[data-step="4"] .vOrder .sph, .gd .stage[data-step="5"] .vOrder .sph{ display:none; }
          .gd .stage[data-step="4"] .vOrder .sval, .gd .stage[data-step="5"] .vOrder .sval{ display:inline; }
          .gd .stage[data-step="4"] .vOrder .sval{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="4"] .vOrder,
          .gd .stage[data-step="4"] .vAmt{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="4"] .vAmt .ph, .gd .stage[data-step="5"] .vAmt .ph{ opacity:0; }
          .gd .stage[data-step="4"] .vAmt .val, .gd .stage[data-step="5"] .vAmt .val{ opacity:1; }
          .gd .stage[data-step="4"] .vAmt .val{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="5"] .vDate,
          .gd .stage[data-step="5"] .vMeth,
          .gd .stage[data-step="5"] .vPayer,
          .gd .stage[data-step="5"] .vRef{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .vDate .dnew{ display:none; }
          .gd .stage[data-step="5"] .vDate .dflt{ display:none; }
          .gd .stage[data-step="5"] .vDate .dnew{ display:inline; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="5"] .vPayer .ph, .gd .stage[data-step="5"] .vRef .ph{ opacity:0; }
          .gd .stage[data-step="5"] .vPayer .val, .gd .stage[data-step="5"] .vRef .val{ opacity:1; animation:gdRoll .8s ease-out both; }

          /* payment history — one collapsible card per order */
          .gd .secttl{ font-size:.8rem; font-weight:700; color:var(--ink); margin:.2rem 0 .45rem; }
          .gd .pgc{ border:1px solid var(--line); border-radius:8px; background:var(--surface); margin-bottom:.3rem; overflow:hidden; }
          .gd .pgc.paid{ background:var(--good-wash); border-color:color-mix(in srgb,var(--good) 35%,transparent); }
          .gd .pgsum{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; padding:.35rem .55rem; font-size:.74rem; }
          .gd .cart{ color:var(--faint); font-weight:700; font-size:.72rem; }
          .gd .cart::before{ content:"\25B8"; }
          .gd .pgcust{ font-weight:700; color:var(--ink); }
          .gd .qlink{ font-size:.66rem; font-weight:700; color:var(--accent); background:var(--panel); border-radius:4px; padding:.05rem .35rem; }
          .gd .stand{ font-size:.66rem; color:var(--faint); font-style:italic; }
          .gd .pgm{ margin-left:auto; display:flex; gap:.7rem; flex-wrap:wrap; color:var(--soft); font-size:.7rem; }
          .gd .pgm b{ color:var(--ink); }
          .gd .pgm .owed, .gd .pgm .owed b{ color:#92400e; }
          .gd .pgm .okp{ color:#065f46; font-weight:700; }
          .gd .pgm .ovr, .gd .pgm .ovr b{ color:#1e40af; }
          .gd .pgm i{ font-style:normal; color:var(--faint); font-size:.64rem; }
          .gd .pgdet{ display:none; border-top:1px solid var(--line); background:var(--panel); padding:.25rem .55rem .4rem; }
          .gd .stage[data-step="3"] .pgOpen .pgdet{ display:block; }
          .gd .stage[data-step="3"] .pgOpen{ border-color:var(--accent); }
          .gd .stage[data-step="3"] .pgOpen .cart{ color:var(--accent); }
          .gd .stage[data-step="3"] .pgOpen .cart::before{ content:"\25BE"; }

          .gd .atbl{ width:100%; border-collapse:collapse; font-size:.66rem; }
          .gd .atbl th{ text-align:left; font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.22rem .3rem; }
          .gd .atbl td{ padding:.26rem .3rem; border-bottom:1px solid var(--line); color:var(--ink); vertical-align:middle; }
          .gd .atbl tr:last-child td{ border-bottom:none; }
          .gd .atbl .r{ text-align:right; }
          .gd .atbl tr.dep td{ background:var(--good-wash); }
          .gd .mng{ color:var(--faint); font-style:italic; font-size:.6rem; }
          .gd .xbtn{ display:inline-flex; align-items:center; border-radius:6px; padding:.06rem .32rem; font-size:.64rem; font-weight:700; background:var(--err); color:#fff; }
          .gd .ebtn{ display:inline-flex; align-items:center; border-radius:6px; padding:.06rem .34rem; font-size:.62rem; font-weight:600; background:var(--surface); border:1px solid var(--line); color:var(--soft); margin-right:.2rem; }

          /* confirm modal */
          .gd .cmod{ border:1px solid var(--line); border-radius:10px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.55rem .7rem; max-width:23rem; margin-top:.5rem; }
          .gd .cmod .cq{ font-size:.72rem; color:var(--ink); line-height:1.5; }
          .gd .cmod .cbtns{ display:flex; gap:.4rem; margin-top:.5rem; justify-content:flex-end; }

          /* filter box + CSV preview */
          .gd .filtbox{ border:1px dashed var(--border-strong,#c7ccd4); border-radius:10px; padding:.45rem .6rem .55rem; }
          .gd .filtttl{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--soft); font-weight:700; margin-bottom:.4rem; }
          .gd .filtrow{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:flex-end; }
          .gd .fbox{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:.26rem .45rem; font-size:.68rem; color:var(--faint); }
          .gd .fbox.v{ color:var(--ink); }
          .gd .fbox.wide{ flex:1 1 10rem; }
          .gd .dlab{ display:flex; flex-direction:column; gap:.1rem; font-size:.56rem; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; font-weight:700; }
          .gd .filtrow .selectbox{ min-width:7.5rem; font-size:.7rem; padding:.24rem .45rem; }
          .gd .csv{ border:1px solid var(--line); border-radius:9px; overflow:hidden; margin-top:.5rem; }
          .gd .csvh{ background:var(--panel); border-bottom:1px solid var(--line); padding:.3rem .55rem; font-size:.64rem; font-weight:700; color:var(--soft); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          .gd .csvb{ padding:.4rem .55rem; font-size:.6rem; line-height:1.7; color:var(--ink); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; overflow:hidden; }
          .gd .csvb .hd{ color:var(--faint); }

          .gd .note{ font-size:.66rem; color:var(--faint); margin-top:.5rem; line-height:1.5; }
          .gd .note b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / accounts</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a><a class="on">Payments</a>
                <div class="navh">Setup</div>
                <a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: the top of the page -->
                <div class="asc scTop">
                  <div class="hdrow">
                    <div>
                      <div class="card-t" style="margin-bottom:.1rem">Payments</div>
                      <p class="subt">Payments received against your orders.</p>
                    </div>
                    <div class="hbtns">
                      <span class="mbtn gh">Export invoices (CSV)</span>
                      <span class="mbtn gh">Export payments (CSV)</span>
                      <span class="mbtn pri">+ Record payment</span>
                    </div>
                  </div>
                  <div class="scards">
                    <div class="scard out"><div class="sl">Outstanding</div><div class="sv">&pound;1,284.00</div></div>
                    <div class="scard mon"><div class="sl">Received this month</div><div class="sv">&pound;2,460.00</div></div>
                    <div class="scard all"><div class="sl">All-time received</div><div class="sv">&pound;18,930.00</div></div>
                  </div>
                  <div class="disc">+ Record payment</div>
                  <p class="csvnote">CSV for <b>Xero / QuickBooks / Sage</b>. Respects the date filter below. Invoices export the line items (net of VAT) so the package recomputes tax; they default to account code <b>200 (Sales)</b> and <b>20% VAT</b> &mdash; remap on import if your chart of accounts differs.</p>
                </div>

                <!-- Scene: payment history, grouped -->
                <div class="asc scHist">
                  <div class="secttl">Payment history</div>
                  <div class="pgc pgOpen">
                    <div class="pgsum">
                      <span class="cart"></span>
                      <span class="pgcust">Emma Fletcher</span>
                      <span class="qlink">PRE-2026-0042</span>
                      <span class="pgm">
                        <span>Total <b>&pound;66.00</b></span>
                        <span>Paid <b>&pound;33.00</b> <i>(1)</i></span>
                        <span class="owed">Owed <b>&pound;33.00</b></span>
                      </span>
                    </div>
                    <div class="pgdet">
                      <table class="atbl">
                        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="r">Amount</th><th></th></tr></thead>
                        <tbody>
                          <tr class="dep">
                            <td>3 Aug 2026</td><td><span class="pill">DEPOSIT</span></td><td>Deposit</td>
                            <td class="r">&pound;33.00</td><td><span class="mng">managed on the order</span></td>
                          </tr>
                          <tr>
                            <td>4 Aug 2026</td><td><span class="pill">BANK TRANSFER</span></td><td>Ref 8841</td>
                            <td class="r">&pound;33.00</td><td><span class="ebtn">Edit</span><span class="xbtn">&times;</span></td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                  <div class="pgc paid">
                    <div class="pgsum">
                      <span class="cart"></span>
                      <span class="pgcust">Tom Hargreaves</span>
                      <span class="qlink">PRE-2026-0039</span>
                      <span class="pgm">
                        <span>Total <b>&pound;410.00</b></span>
                        <span>Paid <b>&pound;410.00</b> <i>(3)</i></span>
                        <span class="okp">&check; Fully paid</span>
                      </span>
                    </div>
                  </div>
                  <div class="pgc">
                    <div class="pgsum">
                      <span class="cart"></span>
                      <span class="pgcust">J. Whitaker</span>
                      <span class="stand">Standalone</span>
                      <span class="pgm"><span>Paid <b>&pound;120.00</b> <i>(1)</i></span></span>
                    </div>
                  </div>
                </div>

                <!-- Scene: the record-payment form, open -->
                <div class="asc scForm">
                  <div class="disc open">+ Record payment</div>
                  <div class="npwrap">
                    <div class="npg">
                      <div class="fld fwide">
                        <label>Order (optional)</label>
                        <span class="selectbox vOrder">
                          <span class="sph">&mdash; Standalone payment (no order linked) &mdash;</span>
                          <span class="sval">PRE-2026-0042 &mdash; Emma Fletcher (&pound;33.00 outstanding)</span>
                        </span>
                        <p class="depnote">&check; Deposit of &pound;33.00 is already recorded on this order &mdash; the amount above is the remaining balance, so don&rsquo;t re-enter the deposit.</p>
                      </div>
                      <div class="fld" style="flex:0 0 5.6rem">
                        <label>Amount &pound;</label>
                        <div class="box vAmt"><span class="ph">0.00</span><span class="val">33.00</span></div>
                      </div>
                      <div class="fld">
                        <label>Received on</label>
                        <div class="box vDate"><span class="dflt">19/09/2026</span><span class="dnew">04/08/2026</span></div>
                      </div>
                      <div class="fld">
                        <label>Method</label>
                        <span class="selectbox vMeth" style="min-width:8rem;font-size:.72rem">Bank transfer</span>
                      </div>
                      <div class="fld" style="flex:1 1 10rem">
                        <label>Received from (optional)</label>
                        <div class="box vPayer"><span class="ph">Who paid &mdash; e.g. customer name</span><span class="val">Emma Fletcher</span></div>
                      </div>
                      <div class="fld" style="flex:1 1 10rem">
                        <label>Reference (optional)</label>
                        <div class="box vRef"><span class="ph">e.g. cheque #, Stripe id...</span><span class="val">Ref 8841</span></div>
                      </div>
                    </div>
                    <div class="npa"><span class="mbtn pri">Save payment</span><span class="mbtn gh">Cancel</span></div>
                  </div>
                </div>

                <!-- Scene: saved, and it settles itself -->
                <div class="asc scDone">
                  <div class="okbanner"><b>&check;</b> Payment recorded: &pound;33.00.</div>
                  <div class="scards done" style="grid-template-columns:1fr 1fr">
                    <div class="scard out"><div class="sl">Outstanding</div><div class="sv">&pound;1,251.00</div></div>
                    <div class="scard all"><div class="sl">All-time received</div><div class="sv">&pound;18,963.00</div></div>
                  </div>
                  <div class="pgc paid">
                    <div class="pgsum">
                      <span class="cart"></span>
                      <span class="pgcust">Emma Fletcher</span>
                      <span class="qlink">PRE-2026-0042</span>
                      <span class="pgm">
                        <span>Total <b>&pound;66.00</b></span>
                        <span>Paid <b>&pound;66.00</b> <i>(2)</i></span>
                        <span class="okp">&check; Fully paid</span>
                      </span>
                    </div>
                  </div>
                  <p class="note">Outstanding drops from &pound;1,284.00 to &pound;1,251.00, the card turns <b>green</b>, and the order itself
                     <b>flips to Paid on its own</b>. The paid-in-full receipt then emails itself &mdash; once only, and only if the order carries a
                     valid customer email and the <b>Paid-in-full receipt</b> setting is still ticked.</p>
                </div>

                <!-- Scene: the slip, the edit and the delete -->
                <div class="asc scOops">
                  <div class="errbanner"><b>&#9888;</b> <span>Amount must be a non-zero number.</span></div>
                  <div class="disc open" style="margin-top:.5rem">&#9998; Edit payment</div>
                  <div class="npwrap">
                    <div class="npg">
                      <div class="fld" style="flex:0 0 5.6rem"><label>Amount &pound;</label><div class="box"><span class="ph">0.00</span></div></div>
                      <div class="fld"><label>Received on</label><div class="box"><span>04/08/2026</span></div></div>
                      <div class="fld"><label>Method</label><span class="selectbox" style="min-width:8rem;font-size:.72rem">Bank transfer</span></div>
                    </div>
                    <div class="npa"><span class="mbtn pri">Update payment</span><span class="mbtn gh">Cancel</span></div>
                  </div>
                  <table class="atbl" style="margin-top:.5rem">
                    <tbody><tr>
                      <td>4 Aug 2026</td><td><span class="pill">BANK TRANSFER</span></td><td>Ref 8841</td>
                      <td class="r">&pound;33.00</td><td><span class="ebtn">Edit</span><span class="xbtn">&times;</span></td>
                    </tr></tbody>
                  </table>
                  <div class="cmod">
                    <div class="cq">Delete this payment? (Won&rsquo;t undo the bank entry &mdash; adjust on your bank reconciliation if needed.)</div>
                    <div class="cbtns"><span class="mbtn grey">Cancel</span><span class="mbtn dng">Yes, continue</span></div>
                  </div>
                </div>

                <!-- Scene: filter + the bookkeeper CSV -->
                <div class="asc scCsv">
                  <div class="filtbox">
                    <div class="filtttl">Filter the list</div>
                    <div class="filtrow">
                      <span class="fbox wide">Customer, quote #, reference...</span>
                      <span class="dlab">From<span class="fbox v">01/08/2026</span></span>
                      <span class="dlab">To<span class="fbox v">31/08/2026</span></span>
                      <span class="mbtn pri">This month</span>
                      <span class="mbtn gh">Last month</span>
                      <span class="selectbox">All methods</span>
                      <span class="mbtn gh">Apply filter</span>
                      <span class="mbtn gh">Clear</span>
                    </div>
                  </div>
                  <div class="hbtns" style="margin-top:.55rem">
                    <span class="mbtn gh">Export invoices (CSV)</span>
                    <span class="mbtn gh">Export payments (CSV)</span>
                  </div>
                  <div class="csv">
                    <div class="csvh">Beverley-Blinds-payments-2026-09-19.csv</div>
                    <div class="csvb">
                      <span class="hd">Date,InvoiceNumber,Customer,Amount,Method,Reference,Type</span><br>
                      04/08/2026,PRE-2026-0042,Emma Fletcher,33.00,Bank transfer,Ref 8841,Payment
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Three figures: owed, this month, ever.</b>
                  <b class="c2"><span class="n">2</span> One card per order, not one row per payment.</b>
                  <b class="c3"><span class="n">3</span> The deposit sits here too &mdash; but it is changed on the order.</b>
                  <b class="c4"><span class="n">4</span> Pick the order &mdash; the amount fills itself in.</b>
                  <b class="c5"><span class="n">5</span> Date, how it came, who from, and a reference.</b>
                  <b class="c6 good"><span class="n">6</span> Fully paid &mdash; and the order flips itself.</b>
                  <b class="c7 err"><span class="n">7</span> Wrong figure? Edit it. Wrong payment? Delete it.</b>
                  <b class="c8"><span class="n">8</span> Filter it, then hand it to your bookkeeper.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>There are two money screens and it helps to know which is which. The <b>Payments</b> panel <em>on an order</em> is <b>one job&rsquo;s
             money</b> &mdash; that is the <em>Payments &amp; accounts</em> guide. <b>This</b> page is <b>the whole business&rsquo;s money</b>:
             every payment you have ever taken, what is still owed across all your orders, and the CSV files your bookkeeper or your accounts
             package wants. You will find it in the left-hand menu under <b>Retail &rarr; Payments</b> &mdash; nothing in the app is actually
             labelled &ldquo;Accounts&rdquo;, that is just the name of the add-on. The page is headed <b>Payments</b>, with
             <em>&ldquo;Payments received against your orders.&rdquo;</em> underneath.</p>
          <p>It is a <b>paid add-on</b>. If it is not switched on for you the menu link simply is not there, and typing the address yourself gets
             you <b>&ldquo;Accounts module not enabled&rdquo;</b> and <em>&ldquo;The Accounts add-on isn&rsquo;t enabled for your account. Contact
             your supplier to enable it.&rdquo;</em> Nothing is broken &mdash; it just needs turning on.</p>
          <ul class="steps">
            <li><b>Read the three figures at the top.</b> <b>Outstanding</b> (amber) is everything still owed to you, added up across every order
                that has reached <em>accepted</em> or beyond &mdash; accepted, ordered, fitted, invoiced, paid. <b>Received this month</b> (navy)
                is money that came in since the <b>1st of this month</b> &mdash; a calendar month to date, <em>not</em> the last thirty days, so on
                the 2nd of the month it is supposed to look tiny. <b>All-time received</b> (green) is every payment ever recorded. Two traps worth
                knowing: <b>Outstanding ignores the date boxes</b> further down the page &mdash; filtering the list below does not change it &mdash;
                and it is a <b>net</b> sum, so an order somebody has overpaid quietly pulls the headline figure down a little.</li>
            <li><b>Read the history.</b> Under <b>Payment history</b> you get <b>one card per order</b>, not one row per payment &mdash; an order
                paid in three instalments is still a single line. Newest money first, the <b>300 most recent</b> payments. The closed row reads
                left to right: the customer&rsquo;s name, then either the <b>quote number</b> (click it and you go straight to that order) or an
                italic <b>Standalone</b>, then on the right <b>Total</b>, <b>Paid &pound;X (n)</b> &mdash; the <em>(n)</em> is how many separate
                payments went into it &mdash; and finally one of <b>&check; Fully paid</b> in green (the whole card turns green),
                <b>Owed &pound;X</b> in amber, or <b>Overpaid &pound;X</b> in blue. Total and Owed only appear when the card belongs to an order;
                a standalone card has no job to compare against.</li>
            <li><b>Open a card.</b> Click the row (the little <b>&#9656;</b> flips to <b>&#9662;</b>) and you get the individual payments:
                <b>Date</b>, <b>Method</b> as a small uppercase pill, <b>Reference</b>, <b>Amount</b>, and on the end an <b>Edit</b> button and a
                red <b>&times;</b>. The <b>deposit</b> is in there too, shaded green, with its method pill reading <b>DEPOSIT</b> and its reference
                simply &ldquo;Deposit&rdquo;. It is deliberately read-only here &mdash; where Edit and &times; would be it says
                <em>&ldquo;managed on the order&rdquo;</em>, because the deposit is changed on the order&rsquo;s own <b>Deposit</b> panel. One
                oddity to remember: the <b>Method</b> filter can never find a deposit, because &ldquo;Deposit&rdquo; is not one of the eight methods
                in that list.</li>
            <li><b>Record a payment.</b> The blue <b>+ Record payment</b> button, top right, opens the box (it sits closed until you ask for it).
                Six things, in this order (five on an older database &mdash; see the note on <b>Received from</b> below). <b>Order (optional)</b>
                &mdash; a dropdown starting
                <em>&ldquo;&mdash; Standalone payment (no order linked) &mdash;&rdquo;</em>, then your orders that still have something owing,
                newest accepted first, each written like <em>&ldquo;PRE-2026-0042 &mdash; Emma Fletcher (&pound;33.00 outstanding)&rdquo;</em>
                (up to 200 of them). Pick one and the <b>Amount</b> fills itself with the balance &mdash; but <b>only if Amount is still empty
                (or nought)</b>. It will never overwrite a figure you have already typed, so if this is a part-payment the safe order is
                <em>order first, then amount</em>; pick the order after typing and the box simply keeps your number. If
                a deposit has already been taken you get an amber line: <em>&ldquo;&check; Deposit of &pound;33.00 is already recorded on this
                order &mdash; the amount above is the remaining balance, so don&rsquo;t re-enter the deposit.&rdquo;</em> Then
                <b>Amount &pound;</b>, <b>Received on</b> (starts on today &mdash; change it to the day the money actually landed, because every
                figure and every filter on this page counts by that date), <b>Method</b> (Cash, Card, <b>Bank transfer</b>, Cheque, PayPal, Stripe,
                GoCardless, Other &mdash; Bank transfer is the one already chosen), <b>Received from (optional)</b> for who sent it
                (placeholder <em>&ldquo;Who paid &mdash; e.g. customer name&rdquo;</em>), and
                <b>Reference (optional)</b> for a cheque number or a Stripe id. <b>Save payment</b> finishes it.
                <b>If &ldquo;Received from&rdquo; is not on your form, you have not lost it</b> &mdash; that one field only appears once the
                database has the column for it, so on an installation that has not had that update yet the box goes straight from <b>Method</b>
                to <b>Reference</b>. Everything else here works exactly the same; put the sender in <b>Reference</b> until it turns up.
                There is a shortcut in, too: on
                the <b>Orders</b> page the amber <b>Outstanding</b> figure is a link &mdash; <em>&ldquo;Click to take a payment against this
                order&rdquo;</em> &mdash; and it lands you here with the box already open, the order already chosen and the amount already typed.</li>
            <li><b>Watch it settle itself.</b> You get a green <em>&ldquo;Payment recorded: &pound;33.00.&rdquo;</em> and the card redraws. The
                moment the money covers the order total, the order <b>flips to Paid on its own</b> &mdash; there is no button to press &mdash; and
                the customer is emailed their <b>paid-in-full receipt</b> &mdash; a copy of the order headed <b>Receipt</b>, attached to an email
                subject-lined <em>&ldquo;Receipt PRE-2026-0042 &mdash; paid in full &middot; Beverley Blinds&rdquo;</em> (your own company name is
                tacked on the end like that). That email is <b>not</b> unconditional, so do not
                promise it to a customer without checking two things. First, <b>the customer must have a proper email address on the order</b>; no
                valid address, no receipt, and nothing tells you it was skipped. Second, it obeys the <b>Paid-in-full receipt</b> tick in
                <b>Setup &rarr; Settings</b> &mdash; <em>&ldquo;Email a receipt when an order is paid in full&rdquo;</em> &mdash; which is
                <b>on unless somebody has turned it off</b>. When it does go, it goes <b>once and once only</b>: the send is stamped on the order,
                so no amount of further fiddling can send a second one. The <em>status</em> change works in reverse as well: delete a payment and
                the order steps <b>back</b> out of Paid to whatever it was before (the receipt, of course, cannot be un-sent).</li>
            <li><b>Filter, then export.</b> The dashed <b>Filter the list</b> box searches the <b>customer name, quote number and reference</b>;
                <b>From</b> and <b>To</b> bracket the day the money came in; <b>This month</b> and <b>Last month</b> are one-click date windows that
                keep your search and turn solid blue while they are in effect; the <b>All methods</b> dropdown narrows by how it was paid.
                <b>Apply filter</b> to run it, <b>Clear</b> to drop the lot. Then the two exports (admin only).
                <b>Export payments (CSV)</b> gives you <code>&lt;your-company&gt;-payments-&lt;date&gt;.csv</code> with the columns Date,
                InvoiceNumber, Customer, Amount, Method, Reference and <b>Type</b> (Deposit or Payment). <b>Export invoices (CSV)</b> gives you
                <code>&lt;your-company&gt;-invoices-&lt;date&gt;.csv</code> &mdash; one row per order line, priced <b>net of VAT</b> so the
                accounts package works the tax out itself, with the due date set <b>14 days</b> after the invoice date. The accounting codes are
                on the <b>invoices</b> file only: every line carries <b>AccountCode 200 (Sales)</b> and a <b>TaxType</b> taken from the order
                &mdash; <code>20% (VAT on Income)</code> where the order charges VAT, <code>No VAT</code> where it does not. Remap them on import
                if your own chart of accounts is different. The <b>payments</b> file has no code columns at all &mdash; it is the seven listed
                above and nothing more. Important: the exports
                follow <b>only the From and To dates</b> &mdash; not the search box and not the method dropdown.</li>
          </ul>
          <div class="oops"><b>When it will not save.</b> Two things stop it, and it says so in plain words at the top of the page:
             <em>&ldquo;Amount must be a non-zero number.&rdquo;</em> (blank, nought, or something that is not a number) and
             <em>&ldquo;Received date is required (YYYY-MM-DD).&rdquo;</em> Be warned: <b>the box does not keep what you typed</b>. It stays
             <em>open</em> for you, but blank &mdash; <b>Amount</b> empty, <b>Received on</b> back on today, <b>Method</b> back on
             <b>Bank transfer</b> and the order back on <b>Standalone</b> &mdash; so read the red line, then put the lot in again.
             Got the figure wrong? <b>Edit</b> on the row refills the very same box (the heading changes to <b>&#9998; Edit payment</b> and the
             button to <b>Update payment</b>), and an edit can even move a payment onto a <b>different order</b>, or off orders altogether to
             Standalone &mdash; both orders are then re-checked for you. Wrong payment entirely? The red <b>&times;</b> asks first:
             <em>&ldquo;Delete this payment? (Won&rsquo;t undo the bank entry &mdash; adjust on your bank reconciliation if needed.)&rdquo;</em>
             with <b>Cancel</b> and <b>Yes, continue</b>. The deposit refuses both, with <em>&ldquo;The deposit is managed on the order &mdash;
             change it from the order&rsquo;s deposit panel.&rdquo;</em> And if an order is <b>missing from the dropdown</b>, do not hunt for it:
             the list only holds orders with money still owing, so it has almost certainly been paid off already.</div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Four things this page touches.</b>
             <b>(a) Not everyone sees the same figures.</b> A member of staff without the <b>View all customer jobs</b> tick (set per person in
             <b>Setup &rarr; Users</b>) sees only
             payments on orders they are booked onto, and their three cards are added up from just those &mdash; so their totals will be smaller
             than yours, and that is correct, not a fault. Standalone payments are back-office only: they cannot see or record one at all.
             <b>(b) The figures can never disagree.</b> This page, the order&rsquo;s own Payments panel, the invoice and the money line on the
             calendar all run the same sum &mdash; received is deposit plus payments, balance is total minus received.
             <b>(c) This is retail money.</b> It is the <b>Retail</b> section of the menu, and it counts your own end customers&rsquo; payments.
             Trade account paperwork does not live here &mdash; it sits under <b>Trade &rarr; Invoices</b> and <b>Trade &rarr; Statements</b>
             &mdash; but do not go hunting for those two: the whole <b>Trade</b> block is <b>super-admin only</b>, so unless you are the account
             that runs the platform it will not be in your menu at all. If trade paperwork is what you are after, ask whoever runs the platform.
             <b>(d) CSV or the live link, not both.</b> If you have connected <b>QuickBooks Online</b> in <b>Settings &rarr; Accounting</b>, sales
             are sent across automatically once they are paid &mdash; these CSV files are the <em>manual</em> alternative for everyone else. Use
             one or the other, or your bookkeeper will enter everything twice.</div></div>',
        'script'  => [
            ['0:00', 'The three figures at the top.',
             'This is the Payments page, in the left-hand menu under Retail, then Payments. Three figures across the top tell you where you stand. Outstanding, in amber, is everything still owed to you across every order that has been accepted or gone further. Received this month, in navy, is money that has come in since the first of the month, so early in the month it is meant to look small. All-time received, in green, is every penny ever recorded. Two things to remember: Outstanding takes no notice of the date boxes further down the page, and it is a net figure, so an order someone has overpaid pulls it down a little.', 1],
            ['0:12', 'Every payment, grouped by order.',
             'Underneath is Payment history, and it gives you one card for each order, not one line for each payment. So an order settled in three instalments is still a single tidy card. Read one from left to right: the customer, then the quote number, which is a link straight to that order, then the money. Total, then Paid, with the little number in brackets telling you how many payments made it up, and then the verdict. Green with a tick means fully paid, and the whole card goes green. Amber Owed is what is left to come. Blue Overpaid means too much has gone on. A card that says Standalone instead of a quote number is money with no job attached, named after whoever sent it.', 2],
            ['0:24', 'Open a card for the detail.',
             'Click a card and it opens out into the payments themselves: the date, how it was paid, the reference and the amount. The deposit is in this list too, shaded green, with its method showing as DEPOSIT and its reference simply saying Deposit. It is in here so the totals agree with the order, but you cannot change it from this page. Where the buttons would be it says, managed on the order, and that is exactly where you change it, on the deposit panel of the order itself. One quirk: the method filter will never find a deposit, because Deposit is not one of the eight methods in that list.', 3],
            ['0:35', 'Record a payment, and pick the order.',
             'Money arrives in the bank or the post, so let us record it. The blue plus Record payment button at the top right opens the box. First, the order. The dropdown only lists orders that still have something owing, newest first, each one showing the customer and what is outstanding, so if an order is missing it has almost certainly been paid off already. Choose one and the amount fills itself in with the balance, ready for you. It only does that while the amount box is still empty, mind, so it will never wipe out a figure you have already put in. If it is a part-payment, the easy order is to pick the order first and then type over the amount. And read the amber line when it appears. It tells you the deposit is already recorded and the amount shown is the remaining balance, so please do not enter the deposit a second time.', 4],
            ['0:47', 'Date, method, who from, reference.',
             'Now the rest. Received on already shows today, so change it to the day the money actually landed, because every figure and every filter on this page counts by that date. Method offers cash, card, bank transfer, cheque, PayPal, Stripe, GoCardless or other, and it starts on bank transfer. Received from is for money with no order attached, where there is no customer name to show. And reference is your cheque number or your Stripe reference, and it is one of the things the search box looks in, so it is worth filling. Then Save payment.', 5],
            ['0:59', 'It settles itself.',
             'Payment recorded, thirty-three pounds. Outstanding drops, the card turns green, and the order flips itself to Paid, with no button pressed by anybody. The customer is then emailed their paid-in-full receipt, once and only once. Two things have to be true for that email, though, so do not promise it without checking. The order needs a proper email address on it, because there is nowhere else to send it, and the paid-in-full receipt setting over in Settings has to still be ticked, which it is unless somebody has turned it off. The same thing works from the Orders page: that amber outstanding figure beside an order is a link, and it brings you here with the order already chosen and the amount already typed in for you.', 6],
            ['1:09', 'Fixing a slip.',
             'Two things will stop a payment saving, and it tells you in plain words. Amount must be a non-zero number. And, received date is required. Do be warned that the box does not hang on to what you typed. It stays open for you, but empty: the amount gone, the date back on today, the method back on bank transfer and the order back on standalone. So read the red line, then put it all in again. Got the figure wrong? Edit on the row refills that very same box, the heading changes to Edit payment and the button to Update payment, and you can even move the payment onto a different order. Wrong payment altogether? The little red cross asks first, and warns you it will not undo the bank entry, so adjust that on your bank reconciliation. Take money back out and a Paid order steps back out of Paid on its own. The deposit refuses both, and tells you it is managed on the order.', 7],
            ['1:20', 'Filter it, and the bookkeeper CSV.',
             'Last, finding things and handing them over. The search box looks at the customer name, the quote number and the reference. The two date boxes bracket the day the money came in, and This month and Last month are one-click shortcuts that keep whatever you have searched for. Then the two files. Payments gives your bookkeeper the money received. Invoices gives them the sales themselves, with every line shown before VAT so Xero, QuickBooks or Sage can work the tax out for itself. Both follow the dates you set, but not the search box and not the method. The accounting codes are only on the invoices file: every line goes out on account code two hundred, sales, with the VAT type taken from the order, which your bookkeeper may want to remap. The payments file carries no codes at all, just the seven columns you can see. And if the live QuickBooks link in Settings, Accounting, is already switched on, these files are the manual alternative. Use one or the other, never both.', 8],
        ],
];
