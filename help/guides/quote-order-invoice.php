<?php
declare(strict_types=1);

/**
 * Guide: quote-order-invoice
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Ordering & invoicing',
        'eyebrow' => 'Quotes',
        'blurb'   => 'From the Quote actions panel: place the order (your products → manufacturing, bought-in → supplier), send the invoice, and follow it to Paid.',
        'lede'    => 'Once a quote is <b>accepted</b>, the rest runs from the <b>Quote actions</b> panel: <b>place the order</b>, <b>send the
                      invoice</b>, and watch the status march <b>Ordered &rarr; Fitted &rarr; Invoiced &rarr; Paid</b> &mdash; the last two
                      largely on their own.',
        'open'    => '/orders/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scPanel{ display:block; }
          .gd .stage[data-step="2"] .scOrder{ display:block; }
          .gd .stage[data-step="3"] .scOrdered{ display:block; }
          .gd .stage[data-step="4"] .scInvoice, .gd .stage[data-step="5"] .scInvoice{ display:block; }
          .gd .stage[data-step="6"] .scPaid{ display:block; }

          /* quote bar + status pill */
          .gd .qbar{ display:flex; align-items:center; gap:.5rem; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .65rem; font-size:.74rem; margin-bottom:.7rem; }
          .gd .qbar .qn{ font-weight:700; }
          .gd .qpill{ display:none; font-size:.58rem; font-weight:700; border-radius:20px; padding:.06rem .55rem; text-transform:capitalize; }
          .gd .stage[data-step="1"] .p-acc, .gd .stage[data-step="2"] .p-acc{ display:inline; }
          .gd .stage[data-step="3"] .p-ord, .gd .stage[data-step="4"] .p-ord{ display:inline; }
          .gd .stage[data-step="5"] .p-inv{ display:inline; }
          .gd .stage[data-step="6"] .p-paid{ display:inline; }
          .gd .p-acc{ background:#dcfce7; color:#166534; }
          .gd .p-ord{ background:#fde68a; color:#92400e; }
          .gd .p-inv{ background:#dbeafe; color:#1e40af; }
          .gd .p-paid{ background:#a7f3d0; color:#065f46; }
          .gd .qbar .qtot{ margin-left:auto; font-weight:700; }

          /* action buttons */
          .gd .qah{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.5rem; }
          .gd .qacts{ display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .ab{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:8px; padding:.4rem .7rem; font-size:.74rem; font-weight:600; color:var(--ink); background:var(--surface); }
          .gd .ab.pri{ background:var(--accent); color:#fff; border-color:var(--accent); }
          .gd .stage[data-step="1"] .ab.b-order{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* manufacturing / order split */
          .gd .mfgbox{ border:1px solid #86efac; background:rgba(134,239,172,.14); border-radius:9px; padding:.55rem .7rem; max-width:23rem; }
          .gd .mfgbox .t{ font-weight:700; color:var(--ink); font-size:.76rem; }
          .gd .mfgbox .s{ font-size:.68rem; color:var(--soft); margin:.2rem 0 .5rem; }
          .gd .placebtn{ display:inline-flex; background:var(--nav); color:#fff; border-radius:8px; padding:.4rem .8rem; font-size:.76rem; font-weight:600; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .buynote{ font-size:.66rem; color:var(--faint); margin-top:.45rem; }

          /* invoice email + confirm */
          .gd .confirm{ display:none; border:1px solid var(--line); border-radius:9px; padding:.5rem .7rem; background:var(--panel); font-size:.72rem; color:var(--soft); max-width:23rem; margin-bottom:.55rem; }
          .gd .stage[data-step="4"] .confirm{ display:block; }
          .gd .sliperr2{ display:none; margin-bottom:.55rem; }
          .gd .stage[data-step="4"] .sliperr2{ display:flex; }
          .gd .emailcard{ display:none; border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:24rem; }
          .gd .stage[data-step="5"] .emailcard{ display:block; }
          .gd .ehead{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); font-size:.72rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); }
          .gd .ebody{ padding:.6rem .7rem; font-size:.73rem; color:var(--soft); line-height:1.5; }
          .gd .ebal{ color:#1e40af; font-weight:600; }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:6px; padding:.25rem .5rem; font-size:.68rem; color:var(--soft); background:var(--surface); margin-top:.3rem; }
          .gd .invok{ display:none; margin-top:.55rem; }
          .gd .stage[data-step="5"] .invok{ display:flex; }

          .gd .arc{ font-size:.72rem; color:var(--faint); margin-top:.6rem; }
          .gd .arc b{ color:var(--ink); }
          .gd .note{ font-size:.68rem; color:var(--faint); margin-top:.5rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / order</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Quotes</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <div class="qbar">
                  <span class="qn">Order PRE-2026-0042</span>
                  <span class="qpill p-acc">Accepted</span><span class="qpill p-ord">Ordered</span><span class="qpill p-inv">Invoiced</span><span class="qpill p-paid">Paid</span>
                  <span class="qtot">Total &pound;66.00</span>
                </div>

                <!-- Scene: quote actions panel -->
                <div class="osc scPanel">
                  <div class="qah">Quote actions</div>
                  <div class="qacts">
                    <span class="ab">&#128196; View PDF</span>
                    <span class="ab">&#11015; Download PDF</span>
                    <span class="ab pri b-order">Mark as ordered</span>
                    <span class="ab">&#128230; Send to suppliers</span>
                  </div>
                  <p class="note">Everything for this order lives here &mdash; place it, invoice it, or nudge the status along.</p>
                </div>

                <!-- Scene: place the order (manufacturing split) -->
                <div class="osc scOrder">
                  <div class="qah">Send to suppliers</div>
                  <div class="mfgbox">
                    <div class="t">&#127981; Beverley Blinds Trade &mdash; manufacturing</div>
                    <div class="s">Your catalogue products &mdash; they go straight to their manufacturing. No supplier email needed.</div>
                    <span class="placebtn">Place order</span>
                  </div>
                  <p class="buynote">Any <b>bought-in</b> lines are grouped by their supplier and emailed a spec PDF instead. Placing the order moves it to <b>Ordered</b>.</p>
                </div>

                <!-- Scene: ordered -->
                <div class="osc scOrdered">
                  <div class="okbanner"><span>&check;</span> Status: ordered. Due 15 Sep 2026.</div>
                  <p class="note" style="margin-top:.6rem">A <b>due date</b> is stamped from your production times, and your <b>Pending Fitting</b> is already in the calendar &mdash; drag it onto the day and assign a fitter.</p>
                </div>

                <!-- Scene: invoice (slip → sent) -->
                <div class="osc scInvoice">
                  <div class="qah">&#129534; Send invoice</div>
                  <div class="confirm">&ldquo;Email this invoice to the customer now? This also marks the job as <b>Invoiced</b>.&rdquo;</div>
                  <div class="errbanner sliperr2"><span>&#9888;</span><div>No valid customer email on this order &mdash; <b>add one on the customer</b>, then try again.</div></div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Invoice PRE-2026-0042 from Beverley Blinds</div>
                      <div class="frm">to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Hello Emma,<br>
                      Please find your invoice (PRE-2026-0042) attached as a PDF.<br><br>
                      <span class="ebal">Balance due: &pound;33.00.</span> Payment details are on the invoice.<br>
                      <span class="eatt">&#128206; Invoice_PRE-2026-0042.pdf</span><br><br>
                      Kind regards,<br>Beverley Blinds
                    </div>
                  </div>
                  <div class="okbanner invok"><span>&check;</span> Invoice emailed to emma.fletcher@gmail.com. Marked as Invoiced.</div>
                </div>

                <!-- Scene: paid -->
                <div class="osc scPaid">
                  <div class="okbanner"><span>&check;</span> Status: paid.</div>
                  <p class="arc">The whole arc: <b>Accepted &rarr; Ordered &rarr; Fitted &rarr; Invoiced &rarr; Paid</b>. <b>Fitted</b> ticks over when you complete the fitting in the calendar; <b>Paid</b> when the balance is settled &mdash; see <em>Payments &amp; accounts</em>.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Quote actions &mdash; order &amp; invoice, all here.</b>
                  <b class="c2"><span class="n">2</span> Place the order &mdash; your products go to manufacturing.</b>
                  <b class="c3"><span class="n">3</span> Ordered &mdash; due date set, fitting waiting.</b>
                  <b class="c4 err"><span class="n">4</span> Send invoice &mdash; needs the customer&rsquo;s email.</b>
                  <b class="c5"><span class="n">5</span> Invoice emailed &rarr; marked Invoiced.</b>
                  <b class="c6 good"><span class="n">6</span> Paid &mdash; ticks over when the balance&rsquo;s settled.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Everything after acceptance happens on the <b>Quote actions</b> panel at the top of the order (open it from <b>Order history</b>).
             It&rsquo;s your control desk: <b>View / Download PDF</b>, the <b>status buttons</b>, <b>Send to suppliers</b> and <b>Send invoice</b>.</p>
          <ul class="steps">
            <li><b>Place the order.</b> Either hit <b>Mark as ordered</b>, or open <b>&#128230; Send to suppliers</b>. There your
                <b>own catalogue products</b> show as <b>&ldquo;made by &lt;factory&gt; &mdash; Place order&rdquo;</b> (they route straight to their
                manufacturing, no supplier email), while anything <b>bought-in</b> is grouped by supplier and emailed a spec PDF. Either way it
                moves to <b>Ordered</b>, stamps a <b>due date</b> from your production times, and your <b>Pending Fitting</b> is waiting in the calendar.</li>
            <li><b>Fit it.</b> When you complete the fitting appointment in the calendar the job moves to <b>Fitted</b> on its own (you can also
                set it by hand).</li>
            <li><b>Invoice the customer.</b> Once the job is <b>ordered or later</b>, the <b>&#129534; Send invoice</b> button appears. It asks
                <em>&ldquo;Email this invoice to the customer now? This also marks the job as Invoiced.&rdquo;</em>, then emails them the
                <b>invoice PDF</b> and flips the status to <b>Invoiced</b>.</li>
          </ul>
          <p><b>What the invoice is.</b> It&rsquo;s the same document as the quote, headed <b>&ldquo;Invoice&rdquo;</b> &mdash; with two extra
             money lines the quote doesn&rsquo;t have: <b>Paid</b> (the deposit / any payments) and <b>Balance due</b>, plus your
             <b>bank details</b> and <em>&ldquo;Please use &lt;number&gt; as your payment reference&rdquo;</em>. The email states the
             <b>balance due</b> up front. (There&rsquo;s no separate invoice number &mdash; it uses the order number, e.g. <em>PRE-2026-0042</em>.)</p>
          <div class="oops"><b>&ldquo;No valid customer email on this order&rdquo;?</b> Send invoice needs the customer&rsquo;s email &mdash;
             <b>add one on the customer</b> (the Customer details section on the order, or their record) and try again. And if the
             <b>&#129534; Send invoice</b> button isn&rsquo;t showing at all, the job isn&rsquo;t ordered yet &mdash;
             <em>&ldquo;You can invoice once the job is ordered&rdquo;</em>, so <b>Mark as ordered</b> first.</div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The last two steps mostly happen on their own.</b> <b>Fitted</b> is set
             when you complete the fitting in the calendar; <b>Paid</b> flips automatically the moment recorded payments (deposit + balance)
             cover the total &mdash; and un-flips if money is later pulled back. So you rarely press <b>Mark as paid</b> yourself; you just record
             the payment (see <em>Payments &amp; accounts</em>) and the status follows.</div></div>
          <p><b>No bank details on the invoice?</b> The &ldquo;How to pay&rdquo; block only appears if your <b>account name / number</b> are set
             in <b>Settings &rarr; Bank details</b> &mdash; fill those in so customers know where to pay. And remember an ordered/invoiced order is
             <b>read-only</b> (only a draft can be edited); use <b>Reopen as draft</b> if you must change a line.</p>',
        'script'  => [
            ['0:00', 'Quote actions panel.',               'Once a quote is accepted, the rest runs from the Quote actions panel — view the PDF, place the order, send the invoice, move the status.', 1],
            ['0:09', 'Place the order → manufacturing.',    'Place the order with Send to suppliers. Your Beverley products show as made-to-order — hit Place order and they go straight to manufacturing. Anything bought-in emails its supplier.', 2],
            ['0:19', 'Ordered; due date; fitting waiting.', 'That moves it to Ordered, stamps a due date, and your Pending Fitting is already waiting in the calendar to drag onto a day.', 3],
            ['0:28', 'Send invoice; needs an email.',       'When it is made and fitted, invoice them. Send invoice — but it needs a valid customer email, or it stops you.', 4],
            ['0:36', 'Invoice emailed; marked Invoiced.',   'Add the email, and it sends the invoice PDF — with the deposit paid and balance due — and marks the job Invoiced.', 5],
            ['0:45', 'Paid, on its own.',                   'Last step, Paid — and that one ticks over on its own the moment their balance is settled. That is the job, start to finish.', 6],
        ],
];
