<?php
declare(strict_types=1);

/**
 * Guide: quote-payments
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Payments & accounts',
        'eyebrow' => 'Quotes',
        'blurb'   => 'Record the deposit, take the balance in the Payments panel, watch it flip to Paid on its own, and the customer gets a receipt.',
        'lede'    => 'The money side, on the order: <b>record the deposit</b> (suggested from your default), take the <b>balance</b> in the
                      <b>Payments</b> panel, and once it&rsquo;s all in the order <b>flips itself to Paid</b> and emails the customer a
                      <b>receipt</b> &mdash; no button pressed.',
        'open'    => '/orders/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scDep{ display:block; }
          .gd .stage[data-step="2"] .scPay, .gd .stage[data-step="3"] .scPay{ display:block; }
          .gd .stage[data-step="4"] .scPaid{ display:block; }
          .gd .stage[data-step="5"] .scRec{ display:block; }
          .gd .stage[data-step="6"] .scOver{ display:block; }

          .gd .ph3{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.5rem; }
          .gd .bnr{ border-radius:8px; padding:.45rem .65rem; font-size:.74rem; }
          .gd .bnr.out{ background:#fef3c7; color:#92400e; } .gd .bnr.out b{ color:#92400e; }
          .gd .bnr.over{ background:#dbeafe; color:#1e40af; } .gd .bnr.over b{ color:#1e40af; }
          .gd .bnr b.faint{ font-weight:400; opacity:.7; }
          .gd .sugg{ font-size:.68rem; color:var(--faint); margin-top:.4rem; }
          .gd .sugg a{ color:var(--accent); font-weight:600; }
          .gd .mbtn{ display:inline-flex; border-radius:8px; padding:.38rem .75rem; font-size:.76rem; font-weight:600; }
          .gd .mbtn.pri{ background:var(--accent); color:#fff; }
          .gd .mbtn.gh{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .stage[data-step="3"] .recwrap{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .recwrap{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); margin-top:.55rem; max-width:24rem; }
          .gd .recwrap .rt{ font-weight:700; color:var(--ink); font-size:.76rem; margin-bottom:.35rem; }
          .gd .ptbl{ width:100%; border-collapse:collapse; font-size:.68rem; margin-top:.45rem; }
          .gd .ptbl th{ text-align:left; font-size:.56rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.25rem .35rem; }
          .gd .ptbl td{ padding:.3rem .35rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ptbl .r{ text-align:right; }
          .gd .prow2{ display:none; }
          .gd .stage[data-step="3"] .prow2{ display:table-row; }

          /* receipt email */
          .gd .emailcard{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:24rem; }
          .gd .ehead{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); font-size:.72rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); }
          .gd .ebody{ padding:.6rem .7rem; font-size:.73rem; color:var(--soft); line-height:1.5; }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:6px; padding:.25rem .5rem; font-size:.68rem; color:var(--soft); background:var(--surface); margin-top:.3rem; }

          .gd .paidpill{ display:inline-block; font-size:.6rem; font-weight:700; border-radius:20px; padding:.08rem .55rem; background:#a7f3d0; color:#065f46; }
          .gd .note{ font-size:.68rem; color:var(--faint); margin-top:.55rem; }
          .gd .note b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / order</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Quotes</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: deposit -->
                <div class="osc scDep">
                  <div class="ph3">Deposit</div>
                  <div class="okbanner"><span>&check;</span> Deposit paid &pound;33.00 on 3 Aug 2026.</div>
                  <div class="frow" style="margin-top:.55rem">
                    <div class="fld"><label>Amend &pound;</label><div class="box"><span>33.00</span></div></div>
                    <div style="display:flex;align-items:flex-end;gap:.4rem"><span class="mbtn pri">Save</span><span class="mbtn gh">Mark unpaid</span></div>
                  </div>
                  <p class="sugg">Suggested 50%: <a>&pound;33.00</a> &mdash; seeded from your default when the quote was accepted.</p>
                </div>

                <!-- Scene: payments panel -->
                <div class="osc scPay">
                  <div class="ph3">Payments</div>
                  <div class="bnr out"><b>Outstanding: &pound;33.00</b> <b class="faint">of &pound;66.00</b> &mdash; the &pound;33 deposit is already counted.</div>
                  <table class="ptbl">
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="r">Amount</th></tr></thead>
                    <tbody>
                      <tr class="prow2"><td>4 Aug 2026</td><td>Bank transfer</td><td>Ref 8841</td><td class="r">&pound;33.00</td></tr>
                    </tbody>
                  </table>
                  <div class="recwrap">
                    <div class="rt">&#128176; Record a new payment</div>
                    <div class="frow">
                      <div class="fld"><label>Amount &pound;</label><div class="box"><span>33.00</span></div></div>
                      <div class="fld"><label>Method</label><div class="selectbox">Bank transfer</div></div>
                    </div>
                    <div class="fld" style="margin-top:.45rem"><label>Reference (optional)</label><div class="box"><span class="ph">e.g. cheque #, Stripe id&hellip;</span></div></div>
                    <div style="margin-top:.5rem"><span class="mbtn pri">&check; Record payment</span></div>
                  </div>
                </div>

                <!-- Scene: paid -->
                <div class="osc scPaid">
                  <div class="ph3">Payments <span class="paidpill">Paid</span></div>
                  <div class="bnr" style="background:#d1fae5;color:#065f46"><b>&check; Fully paid (&pound;66.00)</b></div>
                  <p class="note">Deposit + balance now cover the total, so the order <b>flipped itself to Paid</b> &mdash; no button. Pull money back below the total and it steps <b>back</b> on its own too.</p>
                </div>

                <!-- Scene: receipt -->
                <div class="osc scRec">
                  <div class="card-t">&hellip; and they get a receipt</div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Receipt PRE-2026-0042 &mdash; paid in full &middot; Beverley Blinds</div>
                      <div class="frm">to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Hello Emma,<br>
                      Thank you &mdash; your payment for PRE-2026-0042 has been received in full, so your account is now settled. Your receipt is attached.<br>
                      <span class="eatt">&#128206; Receipt_PRE-2026-0042.pdf</span><br><br>
                      Kind regards,<br>Beverley Blinds
                    </div>
                  </div>
                  <p class="note">Sent <b>automatically</b> on full payment &mdash; and only <b>ever once</b>.</p>
                </div>

                <!-- Scene: overpaid slip -->
                <div class="osc scOver">
                  <div class="ph3">Payments</div>
                  <div class="bnr over"><b>&#9888; Overpaid by &pound;5.00</b></div>
                  <p class="note">Recorded too much? Delete the extra payment (the <b>&times;</b> beside it, &ldquo;Delete this payment?&rdquo;) and the order <b>un-flips</b> from Paid on its own. The deposit itself is only changed on the <b>Deposit</b> panel, never here.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Record the deposit (suggested from your default).</b>
                  <b class="c2"><span class="n">2</span> Payments panel &mdash; what&rsquo;s still outstanding.</b>
                  <b class="c3"><span class="n">3</span> Take the balance &mdash; prefilled for you.</b>
                  <b class="c4"><span class="n">4</span> Paid &mdash; flips on its own when it&rsquo;s all in.</b>
                  <b class="c5 good"><span class="n">5</span> They get a receipt, automatically.</b>
                  <b class="c6"><span class="n">6</span> Overpaid? Delete the extra &mdash; it un-flips.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The money for an order lives on the order itself &mdash; a <b>Deposit</b> panel (always there once a quote is accepted) and, with the
             <b>Accounts</b> add-on, a <b>Payments</b> panel. Open the order from <b>Order history</b>; the sticky bar even has a
             <b>&#128176; Take payment</b> shortcut.</p>
          <ul class="steps">
            <li><b>Record the deposit.</b> When the quote is accepted, a deposit is <b>seeded from your default</b> (e.g. 50%). In the Deposit
                panel, the amount is <b>suggested</b> (<em>&ldquo;Suggested 50%: &pound;33.00&rdquo;</em> &mdash; click it to fill), and
                <b>Record deposit paid</b> logs it as <em>&ldquo;&check; Deposit paid &pound;X on &lt;date&gt;&rdquo;</em>. You can <b>Amend</b>
                the figure or <b>Mark unpaid</b> later.</li>
            <li><b>Take the balance</b> (Accounts add-on). The <b>Payments</b> panel shows what&rsquo;s left &mdash;
                <em>&ldquo;Outstanding: &pound;33.00 of &pound;66.00&rdquo;</em>, with the deposit already counted. <b>Record a new payment</b>
                pre-fills the <b>outstanding amount</b>; set the <b>date</b>, <b>method</b> (Cash / Card / Bank transfer / Cheque / &hellip;) and an
                optional <b>reference</b>, then <b>Record payment</b>. Each one lists in the table with a <b>&times;</b> to remove it.</li>
            <li><b>It settles itself.</b> The moment deposit + payments <b>cover the total</b>, the order <b>flips to Paid on its own</b> &mdash;
                there&rsquo;s no &ldquo;mark paid&rdquo; step &mdash; and shows <em>&ldquo;&check; Fully paid&rdquo;</em>. Pull money back below the
                total (delete a payment) and it steps <b>back</b> automatically.</li>
            <li><b>The customer gets a receipt.</b> On full payment they&rsquo;re emailed a <b>receipt</b> (subject
                <em>&ldquo;Receipt &lt;number&gt; &mdash; paid in full&rdquo;</em>, the order PDF headed <b>Receipt</b>) &mdash; automatically,
                and <b>only ever once</b>.</li>
          </ul>
          <div class="oops"><b>Overpaid, or paid too early?</b> Record more than the balance and you&rsquo;ll see <em>&ldquo;Overpaid by
             &pound;X&rdquo;</em> and the record-payment box disappears &mdash; delete the extra payment (the <b>&times;</b>, &ldquo;Delete this
             payment?&rdquo;) and it un-flips. And a <b>deposit can only be recorded once the quote is accepted</b> &mdash; before that you&rsquo;ll
             get <em>&ldquo;A deposit can be recorded once the quote has been accepted.&rdquo;</em> Change the deposit only on the <b>Deposit</b>
             panel &mdash; from the Payments list it says <em>&ldquo;The deposit is managed on the order &mdash; change it from the order&rsquo;s
             deposit panel.&rdquo;</em></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The figures match everywhere.</b> Received = <b>deposit + payments</b>, balance =
             <b>total &minus; received</b> &mdash; the same maths powers the <b>invoice</b>, the <b>money line on the calendar</b>, and this panel,
             so nothing ever disagrees. <b>No Accounts add-on?</b> You still get the Deposit panel, and an order can still settle to Paid from the
             deposit alone &mdash; you just don&rsquo;t get the Payments ledger (your supplier turns Accounts on). There&rsquo;s also an
             <b>Accounts</b> list page for a tenant-wide view of every payment.</div></div>',
        'script'  => [
            ['0:00', 'Record the deposit.',                'Once it is accepted, record the deposit — the amount is already suggested from your default, fifty percent here. Record deposit paid, and it is logged.', 1],
            ['0:09', 'Payments panel; what is outstanding.', 'With the Accounts add-on you also get a Payments panel — it shows what is outstanding, thirty-three of sixty-six, with the deposit already counted.', 2],
            ['0:18', 'Take the balance.',                   'Take the balance: the amount is pre-filled to what is left, pick a method, add a reference, and Record payment.', 3],
            ['0:26', 'Paid, on its own.',                  'That covers the total, so the order flips itself to Paid — no button. Fully paid, sixty-six pounds.', 4],
            ['0:33', 'Receipt, automatically.',            'And the customer gets a receipt, emailed automatically — paid in full, PDF attached. It only ever goes once.', 5],
            ['0:41', 'Overpaid? Delete the extra.',        'Recorded too much by mistake? You will see overpaid — just delete the extra payment and it un-flips. The figures match your invoice and calendar everywhere.', 6],
        ],
];
