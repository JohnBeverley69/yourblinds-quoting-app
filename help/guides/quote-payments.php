<?php
declare(strict_types=1);

/**
 * Guide: quote-payments
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers all three real surfaces: the Deposit + Payments panels on
 * /quote-builder/edit.php, the Deposit / Outstanding columns on
 * /orders/index.php, and the Retail -> Payments page (/accounts/index.php).
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Payments & accounts',
        'eyebrow' => 'Quotes',
        'blurb'   => 'Deposits, balances and receipts: record what was paid, follow the Outstanding link, and watch the order settle itself — plus the Payments page your bookkeeper wants.',
        'lede'    => 'Money lives in three places, and they all agree with each other: the <b>Deposit</b> panel on the order, the
                      <b>Payments</b> panel underneath it, and the <b>Retail &rarr; Payments</b> page. This walks the whole job &mdash;
                      record the deposit, follow the amber <b>Outstanding</b> link straight into a pre-filled payment, watch the order
                      <b>settle itself to paid</b> and email a <b>receipt</b>, put a mistake right, and hand a <b>CSV</b> to the accountant.',
        'open'    => '/orders/index.php',
        'css'     => '
          /* a statically-filled field (looks like .fld .box, but always shows its value) */
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .ghost{ display:inline-flex; align-items:center; border:1px solid var(--line); border-radius:8px; padding:.34rem .7rem; font-size:.78rem; font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .btnrow{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .btnrow .save{ margin-top:0; }
          .gd .rowline{ display:flex; align-items:flex-end; gap:.5rem; flex-wrap:wrap; }
          .gd .rowline .save{ margin-top:0; }

          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scDep, .gd .stage[data-step="1"] .scDep{ display:block; }
          .gd .stage[data-step="2"] .scPaid{ display:block; }
          .gd .stage[data-step="3"] .scList{ display:block; }
          .gd .stage[data-step="4"] .scAcct, .gd .stage[data-step="5"] .scAcct{ display:block; }
          .gd .stage[data-step="6"] .scSet{ display:block; }
          .gd .stage[data-step="7"] .scOver{ display:block; }
          .gd .stage[data-step="8"] .scSum{ display:block; }

          /* the mock sidebar follows the live grouped nav: Orders for the order
             scenes, Payments for the Accounts scenes */
          .gd .side .grp{ display:block; font-size:.54rem; letter-spacing:.14em; text-transform:uppercase; color:#6a7d8c; margin:.55rem 0 .15rem; padding-left:.5rem; font-weight:700; }
          .gd .app:has(.stage[data-step="4"]) .nvOrd,
          .gd .app:has(.stage[data-step="5"]) .nvOrd,
          .gd .app:has(.stage[data-step="6"]) .nvOrd,
          .gd .app:has(.stage[data-step="7"]) .nvOrd,
          .gd .app:has(.stage[data-step="8"]) .nvOrd{ background:transparent; color:var(--nav-ink); }
          .gd .app:has(.stage[data-step="4"]) .nvPay,
          .gd .app:has(.stage[data-step="5"]) .nvPay,
          .gd .app:has(.stage[data-step="6"]) .nvPay,
          .gd .app:has(.stage[data-step="7"]) .nvPay,
          .gd .app:has(.stage[data-step="8"]) .nvPay{ background:rgba(91,155,255,.16); color:#fff; }

          .gd .ph3{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.4rem; }
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .5rem; }
          .gd .note{ font-size:.68rem; color:var(--faint); margin-top:.5rem; line-height:1.5; }
          .gd .note b{ color:var(--ink); }
          .gd .sugg{ font-size:.72rem; color:var(--faint); padding-bottom:.42rem; }
          .gd .sugg a{ color:var(--accent); font-weight:600; }

          /* orders list */
          .gd .otbl{ width:100%; border-collapse:collapse; font-size:.66rem; }
          .gd .otbl th{ text-align:left; font-size:.54rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.26rem .3rem; white-space:nowrap; }
          .gd .otbl td{ padding:.34rem .3rem; border-bottom:1px solid var(--line); color:var(--ink); white-space:nowrap; }
          .gd .otbl .r{ text-align:right; }
          .gd .otbl .c{ text-align:center; width:1.1rem; }
          .gd .otbl .tick{ width:12px; height:12px; border-radius:3px; font-size:.5rem; }
          .gd .otbl .qn{ color:var(--accent); font-weight:600; }
          .gd .otbl .subl{ display:block; margin-top:.12rem; font-size:.56rem; color:var(--accent); white-space:nowrap; }
          .gd .spill{ display:inline-block; font-size:.56rem; font-weight:700; border-radius:20px; padding:.06rem .45rem; background:#dbeafe; color:#1e40af; }
          .gd .depg{ color:#065f46; font-weight:600; }
          .gd .depa{ color:#92400e; font-weight:600; }
          .gd .outlink{ color:#92400e; font-weight:700; border-bottom:1px dashed #92400e; }
          .gd .tipbub{ display:inline-block; margin-top:.4rem; background:#1f2a37; color:#fff; font-size:.62rem; border-radius:5px; padding:.18rem .45rem; }

          /* a <details> disclosure, as used on the Payments page */
          .gd .disc{ display:inline-flex; align-items:center; gap:.4rem; border:1px solid var(--line); border-radius:8px; background:var(--panel); padding:.3rem .6rem; font-size:.76rem; font-weight:700; color:var(--ink); }
          .gd .caret{ width:0; height:0; border-left:5px solid var(--faint); border-top:4px solid transparent; border-bottom:4px solid transparent; }
          .gd .caret.open{ border-left:4px solid transparent; border-right:4px solid transparent; border-top:5px solid var(--faint); border-bottom:none; }
          .gd .npbox{ border:1px solid var(--line); border-top:none; border-radius:0 0 9px 9px; padding:.5rem .6rem .6rem; background:var(--surface); }
          .gd .pref{ border-color:var(--accent) !important; background:var(--accent-wash) !important; }
          .gd .preflbl{ font-size:.62rem; color:var(--accent); font-weight:700; }
          .gd .frow3{ display:grid; grid-template-columns:1fr 1fr; gap:.4rem .6rem; margin-bottom:.4rem; }

          /* payments table + history card */
          .gd .ptbl{ width:100%; border-collapse:collapse; font-size:.66rem; margin-top:.4rem; }
          .gd .ptbl th{ text-align:left; font-size:.54rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.24rem .3rem; }
          .gd .ptbl td{ padding:.28rem .3rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ptbl .r{ text-align:right; }
          .gd .ptbl tr.deprow td{ background:#d1fae5; }
          .gd .mpill{ display:inline-block; border:1px solid var(--line); border-radius:999px; padding:.02rem .4rem; font-size:.6rem; color:var(--soft); background:var(--surface); }
          .gd .minibtn{ display:inline-block; border:1px solid var(--line); border-radius:5px; padding:.06rem .35rem; font-size:.6rem; font-weight:600; color:var(--soft); background:var(--surface); margin-right:.2rem; }
          .gd .xbtn{ display:inline-block; border-radius:5px; padding:.06rem .35rem; font-size:.6rem; font-weight:700; color:#fff; background:var(--err); }
          .gd .lockr{ color:var(--faint); font-style:italic; font-size:.6rem; }
          .gd .pgsum{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; font-size:.68rem; color:var(--ink); border:1px solid var(--line); border-radius:8px; background:var(--panel); padding:.3rem .5rem; }
          .gd .pgsum .cust{ font-weight:700; }
          .gd .pgsum .qlink{ color:var(--accent); font-weight:600; }
          .gd .pgsum .bit{ color:var(--soft); }
          .gd .pgsum .ovr{ color:#1e40af; font-weight:700; }
          .gd .pgsum .pd{ color:#065f46; font-weight:700; }
          .gd .modal{ margin-top:.5rem; border:1px solid var(--line); border-radius:9px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.5rem .6rem; font-size:.7rem; color:var(--ink); max-width:26rem; }
          .gd .modal .mb{ display:flex; gap:.4rem; margin-top:.4rem; }

          /* receipt email */
          .gd .emailcard{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:26rem; margin-top:.5rem; }
          .gd .ehead{ background:var(--panel); padding:.4rem .6rem; border-bottom:1px solid var(--line); font-size:.68rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); }
          .gd .ebody{ padding:.45rem .6rem; font-size:.68rem; color:var(--soft); line-height:1.5; }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:6px; padding:.18rem .42rem; font-size:.64rem; color:var(--soft); background:var(--surface); margin-top:.25rem; }

          /* summary cards + filter box + exports */
          .gd .scards{ display:grid; grid-template-columns:repeat(3,1fr); gap:.45rem; }
          .gd .scard{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--panel); }
          .gd .scard .lbl{ font-size:.56rem; text-transform:uppercase; letter-spacing:.04em; font-weight:700; color:var(--faint); }
          .gd .scard .val{ font-size:.9rem; font-weight:700; color:var(--ink); margin-top:.1rem; }
          .gd .scard.am .val{ color:#92400e; }
          .gd .scard.nv .val{ color:#1e40af; }
          .gd .scard.gr .val{ color:#065f46; }
          .gd .fbox{ border:1px dashed var(--line); border-radius:9px; padding:.45rem .55rem; margin-top:.5rem; }
          .gd .fbox .ft{ font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; font-weight:700; color:var(--faint); margin-bottom:.35rem; }
          .gd .frow4{ display:flex; align-items:flex-end; gap:.4rem; flex-wrap:wrap; }
          .gd .lnkbtn{ display:inline-flex; align-items:center; border:1px solid var(--line); border-radius:7px; padding:.28rem .55rem; font-size:.7rem; font-weight:600; color:var(--accent); background:var(--surface); }
          .gd .lnkbtn.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .exprow{ display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:.45rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / money</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a>
                <span class="grp">Retail</span>
                <a>Customers</a><a>Quotes</a><a class="on nvOrd">Orders</a><a class="nvPay">Payments</a>
                <span class="grp">Setup</span>
                <a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene 1: the deposit, not yet paid (what a first-timer meets) -->
                <div class="osc scDep">
                  <div class="card-t">PRE-2026-0042 &middot; Emma Fletcher &middot; total &pound;66.00</div>
                  <div class="ph3">Deposit</div>
                  <p class="ldesc2">Enter the deposit the customer has paid.</p>
                  <div class="rowline">
                    <div class="fld" style="width:8rem"><label>Deposit paid &pound;</label>
                      <div class="box f1"><span class="ph"></span><span class="val">33.00</span></div></div>
                    <span class="save">Record deposit paid</span>
                    <span class="sugg">Suggested 50%: <a>&pound;33.00</a></span>
                  </div>
                  <p class="note">Before the quote is accepted this same panel reads <b>Deposit due on acceptance &pound;</b> with a
                     <b>Save deposit</b> button instead &mdash; that one only <em>sets</em> the figure, it doesn&rsquo;t say it&rsquo;s been paid.</p>
                </div>

                <!-- Scene 2: deposit recorded — amend / mark unpaid -->
                <div class="osc scPaid">
                  <div class="ph3">Deposit</div>
                  <div class="okbanner"><b>&check;</b> Deposit paid &pound;33.00 on 3 Aug 2026</div>
                  <div class="rowline" style="margin-top:.55rem">
                    <div class="fld" style="width:7rem"><label>Amend &pound;</label><div class="boxv">33.00</div></div>
                    <span class="ghost">Save</span>
                    <span class="ghost">Mark unpaid</span>
                  </div>
                  <p class="note">Deposits are changed <b>here and only here</b>. You will see this same &pound;33.00 again on the
                     <b>Payments</b> page, but there it sits in green with no buttons and the words <b>managed on the order</b>.</p>
                </div>

                <!-- Scene 3: the Orders list — Deposit + Outstanding columns -->
                <div class="osc scList">
                  <div class="card-t">Orders <span class="ldesc2" style="display:inline">&mdash; accepted onward</span></div>
                  <table class="otbl">
                    <thead><tr><th class="c"><span class="tick"></span></th>
                      <th>Quote #</th><th>Customer</th><th>Postcode</th><th>Status</th><th>Created</th>
                      <th class="r">Total</th><th>Deposit</th><th class="r">Outstanding</th></tr></thead>
                    <tbody>
                      <tr>
                        <td class="c"><span class="tick"></span></td>
                        <td><span class="qn">PRE-2026-0042</span>
                          <span class="subl">&#128230; Send to suppliers</span></td>
                        <td>Emma Fletcher</td><td>LS27 8QP</td>
                        <td><span class="spill">ordered</span></td><td>2 Aug 2026</td>
                        <td class="r">&pound;66.00</td>
                        <td><span class="depg">&check; &pound;33.00 paid</span></td>
                        <td class="r"><span class="outlink">&pound;33.00</span></td>
                      </tr>
                      <tr>
                        <td class="c"><span class="tick"></span></td>
                        <td><span class="qn">PRE-2026-0039</span>
                          <span class="subl">&#128230; Send to suppliers</span></td>
                        <td>Raj Patel</td><td>LS11 6EE</td>
                        <td><span class="spill">ordered</span></td><td>28 Jul 2026</td>
                        <td class="r">&pound;412.00</td>
                        <td><span class="depa">&pound;206.00 due</span></td>
                        <td class="r"><span class="outlink">&pound;412.00</span></td>
                      </tr>
                    </tbody>
                  </table>
                  <span class="tipbub">Click to take a payment against this order</span>
                </div>

                <!-- Scene 4 + 5: the Payments page, + Record payment panel -->
                <div class="osc scAcct">
                  <div class="card-t">Payments <span class="ldesc2" style="display:inline">&mdash; payments received against your orders.</span></div>
                  <div class="disc"><span class="caret open"></span> + Record payment</div>
                  <div class="npbox">
                    <div class="fld"><label>Order (optional)</label>
                      <div class="selectbox pref" style="min-width:16rem">PRE-2026-0042 &mdash; Emma Fletcher (&pound;33.00 outstanding)</div></div>
                    <div class="frow3">
                      <div class="fld"><label>Amount &pound;</label><div class="boxv pref">33.00</div></div>
                      <div class="fld"><label>Received on</label><div class="boxv pref">2026-08-04</div></div>
                    </div>
                    <div class="frow3">
                      <div class="fld"><label>Method</label><div class="selectbox pref" style="min-width:0">Bank transfer</div></div>
                      <div class="fld"><label>Received from (optional)</label>
                        <div class="box f5"><span class="ph">Who paid &mdash; e.g. customer name</span><span class="val">Emma Fletcher</span></div></div>
                    </div>
                    <div class="fld"><label>Reference (optional)</label>
                      <div class="box f5"><span class="ph">e.g. cheque #, Stripe id...</span><span class="val">Ref 8841</span></div></div>
                    <div class="btnrow"><span class="save">Save payment</span><span class="ghost">Cancel</span></div>
                  </div>
                  <p class="note"><span class="preflbl">Blue</span> = filled in for you by the app, because you arrived from the
                     <b>Outstanding</b> link. Everything is still yours to change. That <b>Amount</b> is the <b>remaining
                     balance</b> &mdash; the deposit has already been taken off it, so never add the deposit on top.</p>
                </div>

                <!-- Scene 6: it settles itself + the receipt. Still the PAYMENTS
                     page — saving from here returns you here (return_to), so what
                     you actually see is the flash plus that order row flipped. -->
                <div class="osc scSet">
                  <div class="ph3">Payments</div>
                  <div class="okbanner"><b>&check;</b> Payment recorded: &pound;33.00.</div>
                  <div class="pgsum" style="margin-top:.45rem">
                    <span class="caret"></span><span class="cust">Emma Fletcher</span>
                    <span class="qlink">PRE-2026-0042</span>
                    <span class="bit">Total <b>&pound;66.00</b></span>
                    <span class="bit">Paid <b>&pound;66.00</b> (2)</span>
                    <span class="pd">&check; Fully paid</span>
                  </div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Receipt PRE-2026-0042 &mdash; paid in full &middot; Beverley Blinds</div>
                      <div class="frm">to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Thank you &mdash; your payment for PRE-2026-0042 has been received in full, so your account is now settled.
                      Your receipt is attached.
                      <br><span class="eatt">&#128206; Receipt_PRE-2026-0042.pdf</span>
                    </div>
                  </div>
                  <p class="note">You stay on the <b>Payments</b> page &mdash; that is where you saved from. Open the order itself and
                     its own Payments panel now reads the green <b>&check; Fully paid (&pound;66.00)</b>, and the
                     <b>&#128183; Record a new payment</b> card has gone &mdash; there is nothing left to take.
                     The factory is unaffected: the job carries on through Confirmed, In Production, Ready and Dispatched.</p>
                </div>

                <!-- Scene 7: the slip and the fix -->
                <div class="osc scOver">
                  <div class="ph3">Payment history</div>
                  <div class="pgsum" style="margin-top:.45rem">
                    <span class="caret open"></span><span class="cust">Emma Fletcher</span>
                    <span class="qlink">PRE-2026-0042</span>
                    <span class="bit">Total <b>&pound;66.00</b></span>
                    <span class="bit">Paid <b>&pound;71.00</b> (2)</span>
                    <span class="ovr">Overpaid &pound;5.00</span>
                  </div>
                  <table class="ptbl">
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="r">Amount</th><th></th></tr></thead>
                    <tbody>
                      <tr class="deprow"><td>3 Aug 2026</td><td><span class="mpill">Deposit</span></td><td>Deposit</td>
                        <td class="r">&pound;33.00</td><td><span class="lockr">managed on the order</span></td></tr>
                      <tr><td>4 Aug 2026</td><td><span class="mpill">Bank transfer</span></td><td>Ref 8841</td>
                        <td class="r">&pound;38.00</td><td><span class="minibtn">Edit</span><span class="xbtn">&times;</span></td></tr>
                    </tbody>
                  </table>
                  <div class="modal">Delete this payment? (Won\'t undo the bank entry &mdash; adjust on your bank reconciliation if needed.)
                    <div class="mb"><span class="ghost">Cancel</span><span class="save">Yes, continue</span></div></div>
                </div>

                <!-- Scene 8: the whole picture + the exports -->
                <div class="osc scSum">
                  <div class="exprow">
                    <span class="ghost">Export invoices (CSV)</span>
                    <span class="ghost">Export payments (CSV)</span>
                    <span class="save" style="margin-top:0">+ Record payment</span>
                  </div>
                  <div class="scards">
                    <div class="scard am"><div class="lbl">Outstanding</div><div class="val">&pound;1,240.00</div></div>
                    <div class="scard nv"><div class="lbl">Received this month</div><div class="val">&pound;3,480.00</div></div>
                    <div class="scard gr"><div class="lbl">All-time received</div><div class="val">&pound;27,905.00</div></div>
                  </div>
                  <div class="fbox">
                    <div class="ft">Filter the list</div>
                    <div class="frow4">
                      <div class="fld" style="flex:1 1 9rem"><div class="boxv" style="color:var(--faint)">Customer, quote #, reference...</div></div>
                      <div class="fld" style="width:6rem"><label>From</label><div class="boxv">2026-08-01</div></div>
                      <div class="fld" style="width:6rem"><label>To</label><div class="boxv">2026-08-31</div></div>
                      <span class="lnkbtn on">This month</span><span class="lnkbtn">Last month</span>
                      <div class="selectbox" style="min-width:8rem">All methods</div>
                      <span class="ghost">Apply filter</span>
                    </div>
                  </div>
                  <p class="note">The exports respect these dates &mdash; set the window first, then export.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Record the deposit the customer actually paid.</b>
                  <b class="c2"><span class="n">2</span> It&rsquo;s logged &mdash; and it can be undone.</b>
                  <b class="c3"><span class="n">3</span> The Orders list shows what&rsquo;s still owed.</b>
                  <b class="c4"><span class="n">4</span> Payments opens with the work half done.</b>
                  <b class="c5"><span class="n">5</span> Fill in the last two boxes and save.</b>
                  <b class="c6 good"><span class="n">6</span> It settles itself, and they get a receipt.</b>
                  <b class="c7"><span class="n">7</span> Put a mistake right &mdash; Edit or remove.</b>
                  <b class="c8"><span class="n">8</span> The whole picture, and the accountant&rsquo;s CSV.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>There are <b>two panels and one page</b>. On the order itself (<b>Quote builder</b> &rarr; the order) you get a <b>Deposit</b>
             panel and, if your account has the <b>Accounts</b> add-on, a <b>Payments</b> panel below it &mdash; the sticky bar at the top
             even has a <b>&#128183; Take payment</b> link that jumps straight down to it. Then there&rsquo;s the whole-business view:
             <b>Retail &rarr; Payments</b> in the left-hand menu, titled <b>Payments</b> with the subtitle
             <em>&ldquo;Payments received against your orders.&rdquo;</em> Everything below happens in one of those three spots.</p>

          <ul class="steps">
            <li><b>The deposit, before the customer accepts.</b> While it is still a quote the Deposit panel reads
                <em>&ldquo;The deposit due when the customer accepts. Leave it as your default, or type an override for this quote.&rdquo;</em>
                &mdash; a box labelled <b>Deposit due on acceptance &pound;</b> and a <b>Save deposit</b> button. That only <b>sets the
                figure</b>; it does not say anyone has paid. Type a one-off amount here and a small <b>Override set</b> flag appears so you
                can see at a glance that this quote isn&rsquo;t using your default. The default itself comes from
                <b>Settings &rarr; Quoting &rarr; Default deposit</b>, which is a pair of <b>radio buttons</b>: <b>Percentage of total</b>
                (50 to begin with) or <b>Flat amount</b> in pounds. Whichever you pick decides the wording on the order &mdash; either
                <em>&ldquo;Suggested 50%:&rdquo;</em> or plain <em>&ldquo;Suggested deposit&rdquo;</em> &mdash; so don&rsquo;t be thrown if
                yours shows no percentage.</li>

            <li><b>The deposit, once it&rsquo;s an order.</b> The panel changes to <em>&ldquo;Enter the deposit the customer has paid.&rdquo;</em>
                with a box labelled <b>Deposit paid &pound;</b> and a <b>Record deposit paid</b> button. The suggested figure sits beside it as
                a blue link &mdash; click it to drop it in, or type over it. <b>Type what was really handed over</b>, not what you hoped for:
                this figure is what the customer&rsquo;s balance is worked out from. Once saved you get a green line,
                <em>&ldquo;&check; Deposit paid &pound;33.00 on 3 Aug 2026&rdquo;</em>, and the panel turns into two small controls: <b>Amend
                &pound;</b> with a <b>Save</b> button for a wrong figure, and <b>Mark unpaid</b> to take the whole thing back off again.
                Nothing here is a one-way door.</li>

            <li><b>Finding what&rsquo;s still owed.</b> Open <b>Retail &rarr; Orders</b> and two money columns do the watching for you.
                <b>Deposit</b> shows <em>&ldquo;&check; &pound;33.00 paid&rdquo;</em> in green, or <em>&ldquo;&pound;33.00 due&rdquo;</em> in
                amber if a figure is set but no money has come in, or a grey dash if there&rsquo;s no deposit at all.
                <b>Outstanding</b> shows what is left on the whole job: an <b>amber figure with a dashed underline</b>, and it is a
                <b>link</b> &mdash; its tooltip says <em>&ldquo;Click to take a payment against this order&rdquo;</em>. Click it and you land
                on the Payments page with the panel already open, the order already chosen and the amount already filled in. That is the
                quickest route, and it is the one that can&rsquo;t go wrong. A fully-settled order shows a green <em>&ldquo;&check;
                paid&rdquo;</em> instead; if too much has gone in you get a blue <em>&ldquo;+&pound;5.00&rdquo;</em> marked
                <b>Overpaid</b>.</li>

            <li><b>Taking the balance.</b> Two ways, same result. On the order, the <b>Payments</b> panel shows an amber
                <em>&ldquo;Outstanding: &pound;33.00 of &pound;66.00&rdquo;</em> and a card headed <b>&#128183; Record a new payment</b> with
                the hint <em>&ldquo;Outstanding amount pre-filled. Adjust if it&rsquo;s a part-payment, then click Record payment.&rdquo;</em>
                It has four boxes &mdash; <b>Amount &pound;</b>, <b>Date received</b>, <b>Method</b> (Cash, Card, Bank transfer, Cheque,
                PayPal, Stripe, GoCardless, Other &mdash; Bank transfer is pre-picked) and <b>Reference (optional)</b> &mdash; then
                <b>&check; Record payment</b>. On the Payments page it is the same form inside a <b>+ Record payment</b> drop-down panel
                (click the row to open it), with two extra boxes &mdash; <b>Order (optional)</b> and <b>Received from (optional)</b>
                &mdash; and one small difference of wording: the date box there is labelled <b>Received on</b>, not <b>Date received</b>.
                Same box, same job. Its button is <b>Save payment</b>, with a <b>Cancel</b> beside it. Fill
                <b>Reference</b> with whatever will let you find the money on the bank statement &mdash; a cheque number, a Stripe id, a
                bank reference.</li>

            <li><b>Part-payments, and money with no job behind it.</b> Paid only some of it? Type the smaller figure. Part-payments stack
                up and the balance simply comes down; record as many as you like. And if money arrives that isn&rsquo;t against any order,
                leave the <b>Order (optional)</b> picker on its first entry, <b>&mdash; Standalone payment (no order linked) &mdash;</b>,
                and put the sender in <b>Received from (optional)</b> (placeholder <em>&ldquo;Who paid &mdash; e.g. customer name&rdquo;</em>).
                Standalone money gets its own card in the history, labelled <b>Standalone</b>. One thing to watch: the order picker only
                lists orders that <b>still owe something</b>, so a settled order won&rsquo;t be in the list.</li>

            <li><b>It settles itself.</b> The moment the deposit plus the payments <b>cover the total</b>, the order marks itself
                <b>paid</b> &mdash; there is no &ldquo;mark as paid&rdquo; button to hunt for. The banner turns green,
                <em>&ldquo;&check; Fully paid (&pound;66.00)&rdquo;</em>, and the record-a-payment card <b>disappears</b>, because there is
                nothing left to take. The customer is emailed a receipt at the same moment: subject <em>&ldquo;Receipt PRE-2026-0042 &mdash;
                paid in full &middot; Beverley Blinds&rdquo;</em>, with the PDF headed <b>Receipt</b> attached as
                <b>Receipt_PRE-2026-0042.pdf</b>. It goes <b>once and once only</b> &mdash; the app stamps the order the moment it sends, so
                it can never repeat. Three things have to be true: the setting <b>Settings &rarr; Paid-in-full receipt &rarr;
                &ldquo;Email a receipt when an order is paid in full&rdquo;</b> is ticked (it is, by default), the customer has a valid email
                address on file, and the order really is paid in full. If there&rsquo;s no email address nothing is sent and nothing warns
                you, so check the customer record if you were expecting one. Take money back off &mdash; delete a payment, or
                <b>Mark unpaid</b> the deposit &mdash; and the order steps <b>back to the status it held before it went paid</b> (usually
                <em>ordered</em> or <em>invoiced</em>). That is why <em>ordered</em> reappears and not something new.</li>

            <li><b>Reading the Payments page.</b> Three cards across the top answer the three questions you actually have:
                <b>Outstanding</b> (everything still owed across every accepted-or-beyond order, not just what&rsquo;s on screen),
                <b>Received this month</b>, and <b>All-time received</b>. Below them, <b>Payment history</b> is grouped into
                <b>collapsible cards &mdash; one per order</b>, so three part-payments on one job roll into a single row rather than
                cluttering the list. The closed row reads: the customer&rsquo;s name, the quote number as a link,
                <em>&ldquo;Total &pound;66.00&rdquo;</em>, <em>&ldquo;Paid &pound;66.00 (2)&rdquo;</em> (the number in brackets is how many
                payments) and then one of <em>&ldquo;&check; Fully paid&rdquo;</em>, <em>&ldquo;Owed &pound;33.00&rdquo;</em> or
                <em>&ldquo;Overpaid &pound;5.00&rdquo;</em>. <b>Click the row to open it</b> and you see each payment: Date, Method (as a
                little pill), Reference, Amount, and two small buttons &mdash; <b>Edit</b>, which re-opens the panel at the top with that
                payment loaded (the heading changes to <b>&#9998; Edit payment</b> and the button to <b>Update payment</b>, and you can
                even move it to a different order), and a red <b>&times;</b> to remove it. The <b>deposit row is the exception</b>: it shows
                in green with no buttons, just the words <em>&ldquo;managed on the order&rdquo;</em>, because a deposit is only ever changed
                on the order&rsquo;s own Deposit panel. <b>Filter the list</b> sits above the history: a search box
                (<em>&ldquo;Customer, quote #, reference...&rdquo;</em>), <b>From</b> and <b>To</b> dates, one-click <b>This month</b> /
                <b>Last month</b>, a method picker starting at <b>All methods</b>, then <b>Apply filter</b> &mdash; and <b>Clear</b> to drop
                it all again.</li>

            <li><b>Handing it to the accountant.</b> Two buttons at the top right of the Payments page, for admins:
                <b>Export invoices (CSV)</b> (<em>&ldquo;One row per order line &mdash; import as sales invoices&rdquo;</em>) and
                <b>Export payments (CSV)</b> (<em>&ldquo;Payments received &mdash; for your bookkeeper / accounting software&rdquo;</em>).
                Both are plain CSV for <b>Xero, QuickBooks or Sage</b>, and both <b>respect the date filter</b>, so set the From/To window
                first. The invoice export sends the <b>line items net of VAT</b> so the accounts package works the tax out itself; it
                defaults to <b>account code 200 (Sales)</b> and <b>20% VAT</b>, which you remap on import if your chart of accounts is set
                up differently.</li>
          </ul>

          <div class="oops"><b>What it says when it won&rsquo;t play &mdash; and what to do:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>A deposit can be recorded once the quote has been accepted.</code> &mdash; you tried to record a deposit as
                   <em>paid</em> on a quote that isn&rsquo;t an order yet. Set the figure with <b>Save deposit</b> now, and record it as
                   paid after they accept.</li>
               <li><code>Enter the deposit amount the customer paid (more than &pound;0).</code> and
                   <code>Set a deposit amount first &mdash; a &pound;0 deposit can&rsquo;t be marked paid.</code> &mdash; a zero deposit is
                   not a payment. If they paid nothing up front, record nothing.</li>
               <li><code>Deposit must be a non-negative number.</code> &mdash; letters, or a minus sign, in the deposit box.</li>
               <li><code>Amount must be a non-zero number.</code> / <code>Received date is required (YYYY-MM-DD).</code> &mdash; the two
                   required boxes on a payment. The date box defaults to today, so this usually means it was cleared.</li>
               <li><code>The deposit is managed on the order &mdash; change it from the order&rsquo;s deposit panel.</code> &mdash; a
                   back-stop rather than something you can walk into: the deposit row on the Payments page deliberately carries no
                   <b>Edit</b> and no <b>&times;</b>, so there is no button there to press. You would only meet this if something tried
                   to change the deposit from the Payments page anyway &mdash; a page left open from before the deposit was recorded,
                   say. Either way the answer is the same: open the order and use <b>Amend &pound;</b> or <b>Mark unpaid</b>.</li>
               <li><code>This quote can no longer be edited.</code> &mdash; the quote is locked (cancelled or archived).</li>
               <li>Removing a payment always asks first, in the app&rsquo;s own little dialog with <b>Cancel</b> and a red
                   <b>Yes, continue</b>. The wording depends on where you pressed the red <b>&times;</b>. On the <b>Payments</b> page it is
                   the long version: <em>&ldquo;Delete this payment? (Won\'t undo the bank entry &mdash; adjust on your bank
                   reconciliation if needed.)&rdquo;</em> In the Payments table on the <b>order</b> it is just
                   <em>&ldquo;Delete this payment?&rdquo;</em> Both do exactly the same thing, and the long version is worth reading either
                   way &mdash; deleting here only tidies <b>your</b> record; the money is still in the bank and your bookkeeper will still
                   see it.</li>
             </ul></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Four things worth knowing.</b>
             <b>(1) The figures agree everywhere</b> because one sum drives them all: <em>received = deposit + payments</em>,
             <em>balance = total &minus; received</em>. That is the same maths on the order, on the Orders list, on the Payments page and
             on the invoice &mdash; and the deposit is <b>never counted twice</b>, which is why it is
             hidden from the Payments table on the order but shows (locked) on the Payments page.
             <b>(2) No Accounts add-on?</b> Then there is no <b>Retail &rarr; Payments</b> row in the menu at all, no Payments panel on the
             order, and going to the page directly gives a blocked screen headed <em>&ldquo;Accounts module not enabled&rdquo;</em> saying
             <em>&ldquo;The Accounts add-on isn&rsquo;t enabled for your account. Contact your supplier to enable it.&rdquo;</em> The
             <b>Deposit</b> panel still works, and an order can still settle itself to <b>paid</b> on the deposit alone.
             <b>(3) Staff see less.</b> A user without the <b>View all customer jobs</b> tick box (<b>Setup &rarr; Users</b>, on that
             user&rsquo;s own record) only sees payments on orders they
             are assigned to, and cannot touch standalone payments at all &mdash; they get <em>&ldquo;You don&rsquo;t have permission to
             record payments for that order.&rdquo;</em> or <em>&ldquo;You don&rsquo;t have permission to delete that payment.&rdquo;</em>
             <b>(4) Paying doesn&rsquo;t stop the making.</b> An order going <b>paid</b> is still a placed order &mdash; the factory side
             carries on through Confirmed, In Production, Ready and Dispatched exactly as before. Money and making are two separate tracks.
             And this page is <b>your own customers&rsquo; money</b>: the invoices and statements a factory raises against its <b>trade
             accounts</b> live under <b>Trade &rarr; Invoices</b> and <b>Trade &rarr; Statements</b>, and are super-admin only &mdash; you
             won&rsquo;t find them here.</div></div>',
        'script'  => [
            ['0:00', 'Record the deposit that was actually paid.',
             'The deposit is the money taken up front, and it lives on the order. Once the customer has accepted, this panel says: enter the deposit the customer has paid. The figure beside Suggested is your own default from Settings — click it to drop it in, or type what they really handed over. Type what was actually paid, not what you hoped for. And if nothing was paid, record nothing: a zero deposit is not a payment, and the app will say so.', 1],
            ['0:22', 'Logged — and it can be undone.',
             'A green line confirms it, with the date. Got the figure wrong? Change it in Amend and press Save. Recorded it by mistake altogether? Mark unpaid takes it straight back off — the money, the running totals, everything. And note where deposits live: only here, on the order. You will see the deposit again on the Payments page, but there it is locked, and marked: managed on the order.', 2],
            ['0:42', 'The Orders list shows what is owed.',
             'On your Orders list, two money columns do the watching for you. Deposit shows either a green tick with the amount paid, or the amount still due in amber. Outstanding shows what is left on the whole job. That amber figure is a link — click it and the app carries you to the Payments page with the order already chosen and the amount already filled in. That is the quickest way to take money, and it is also the way that cannot go wrong.', 3],
            ['1:04', 'Payments opens with the work half done.',
             'Here is that page, and everything highlighted in blue was filled in for you. The order and the amount, because you came in from the Outstanding link — plus today’s date and Bank transfer as the method, which are simply this form’s defaults. Look hard at that amount. It is the balance, with the deposit already taken off, not the whole total — so there is nothing left for the deposit to be added to. Typing the deposit in again on top of it is the commonest mistake on this page.', 4],
            ['1:25', 'Fill in the last two boxes and save.',
             'Received from is who the money came from — most useful when there is no order attached to it. Reference is whatever lets you find it again on the bank statement: a cheque number, a Stripe id, a bank reference. Both are optional, and both save you an argument later. Then, Save payment. If you were only handed part of it, just type the smaller figure — part payments are perfectly fine, and they stack up.', 5],
            ['1:47', 'It settles itself, and they get a receipt.',
             'Saving keeps you here on the Payments page — Payment recorded, thirty three pounds — and the order’s row has already flipped to Fully paid. The deposit plus the payment now cover the total, so the order marks itself paid. There is no mark as paid button to hunt for. Open the order itself and its Payments panel is green too, and the record a payment box has gone, because there is nothing left to take. And the customer is emailed a receipt automatically — the order, headed Receipt — once, and only once, as long as they have an email address on file. You can switch that off in Settings, under Paid in full receipt. Paying does not disturb the factory: the job carries on through Confirmed, In Production, Ready and Dispatched, exactly as before.', 6],
            ['2:15', 'Put a mistake right.',
             'Typed too much, and you get a blue Overpaid line. Nothing is stuck. Edit reopens the box above with that payment loaded — the heading changes to Edit payment, and the button to Update payment — so you can correct the figure, the date, the method, even move it to a different order. The red cross removes it altogether, and the order steps back out of paid, to whatever it was before. The deposit row is the exception: it shows here in green, but it has no buttons, because a deposit is only ever changed on the order itself.', 7],
            ['2:40', 'The whole picture, and the accountant’s CSV.',
             'Three cards at the top answer the three questions you actually have: what is still owed across every order, what came in this month, and what has come in ever. Below that, filter by customer, quote number or reference, or by date — This month and Last month are one click. And when your bookkeeper asks, set the dates and use the two export buttons: a spreadsheet file for Xero, QuickBooks or Sage. The invoice export sends the line items net of VAT so the package works the tax out, and it assumes account code two hundred, Sales, at twenty percent — remap it on import if your books are set up differently.', 8],
        ],
];
