<?php
declare(strict_types=1);

/**
 * Guide: accounts-to-package
 *
 * "Get your figures into Xero, QuickBooks or Sage" — the CSV route that works
 * today for every package (the live QuickBooks link is settings-accounting).
 *
 * Scenes by data-step:
 *   .scPay  (0, 1)  Payments page header + date quick-picks
 *   .scCsv  (2, 5)  what the invoices / payments CSV look like
 *   .scXero (3, 4)  Xero's import screen, then the drafts
 *   .scBank (6)     matching a bank line to the invoice
 *   .scPick (7)     which route for which package
 *
 * Sources: accounts/index.php (buttons, note, filter, quick-picks),
 * accounts/export.php (columns, defaults 200 / "20% (VAT on Income)" /
 * "No VAT", 14-day due date, net unit price, negative "Discount — agreed
 * price" line), and the vendors' own help, checked 26 Sep 2026:
 *   Xero  central.xero.com/0/article/Import-customer-invoices-GL (+ Find & Match)
 *   QBO   quickbooks.intuit.com/learn-support/en-uk … import-multiple-invoices
 *   Sage  gb-kb.sage.com solution 222001000100915 (now PURCHASE invoices only)
 *   FreeAgent support … Import-a-client-s-invoices (Practice Partners only)
 * None of the four imports customer PAYMENTS from a file — payments are matched
 * to invoices from the bank feed. Re-check these vendor steps if they change.
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Get your figures into Xero, QuickBooks or Sage',
        'eyebrow' => 'Accounts · Exports',
        'blurb'   => 'Download your sales as a spreadsheet from the Payments page and bring them into Xero, QuickBooks Online, Sage or FreeAgent — step by step, with the traps for each package.',
        'lede'    => 'Until the automatic links are finished, this is how your sales get into your accounts package: the <b>Payments</b>
                      page gives you two spreadsheet files (CSV), your package <b>imports the invoices</b>, and the <b>payments are
                      matched from your bank feed</b>. It works for <b>Xero</b> best, <b>QuickBooks Online</b> with a couple of
                      tweaks, and for <b>Sage</b> and <b>FreeAgent</b> through your bookkeeper. Do it once a week or once a month
                      &mdash; whatever suits your bookkeeping.',
        'open'    => '/accounts/index.php',
        'css'     => '
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scPay, .gd .stage[data-step="1"] .scPay{ display:block; }
          .gd .stage[data-step="2"] .scCsv, .gd .stage[data-step="5"] .scCsv{ display:block; }
          .gd .stage[data-step="3"] .scXero, .gd .stage[data-step="4"] .scXero{ display:block; }
          .gd .stage[data-step="6"] .scBank{ display:block; }
          .gd .stage[data-step="7"] .scPick{ display:block; }

          /* ---- Payments header ---- */
          .gd .hdrow{ display:flex; justify-content:space-between; gap:.6rem; flex-wrap:wrap; align-items:flex-start; }
          .gd .subt{ font-size:.7rem; color:var(--soft); margin:0; }
          .gd .hbtns{ display:flex; gap:.35rem; flex-wrap:wrap; }
          .gd .mb{ display:inline-flex; border-radius:8px; padding:.32rem .6rem; font-size:.7rem; font-weight:600; border:1px solid var(--line); background:var(--surface); color:var(--ink); transition:box-shadow .2s; }
          .gd .mb.pri{ background:var(--nav); color:#fff; border-color:var(--nav); }
          .gd .note{ font-size:.64rem; color:var(--faint); margin:.5rem 0 .7rem; line-height:1.45; max-width:34rem; }
          .gd .note b{ color:var(--soft); }
          .gd .filt{ border:1px solid var(--line); border-radius:9px; padding:.45rem .6rem; font-size:.68rem; color:var(--soft); }
          .gd .filt .ft{ font-weight:700; font-size:.62rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); margin-bottom:.35rem; }
          .gd .frow{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; }
          .gd .fd{ border:1px solid var(--line); border-radius:6px; padding:.2rem .4rem; background:var(--panel); }
          .gd .stage[data-step="1"] .lastm{ background:var(--nav); color:#fff; border-color:var(--nav); }
          .gd .stage[data-step="1"] .expinv{ box-shadow:0 0 0 3px var(--accent-wash), 0 0 0 5px var(--accent); }
          .gd .stage[data-step="0"] .fdv, .gd .fdv2{ display:none; }
          .gd .stage[data-step="1"] .fdv2{ display:inline; } .gd .stage[data-step="1"] .fdv{ display:none; }

          /* ---- CSV preview ---- */
          .gd .csv{ border:1px solid var(--line); border-radius:9px; overflow:hidden; }
          .gd .csvh{ background:var(--panel); border-bottom:1px solid var(--line); padding:.3rem .55rem; font-size:.64rem; font-weight:700; color:var(--soft); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          .gd .csvb{ padding:.4rem .55rem; font-size:.58rem; line-height:1.75; color:var(--ink); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; overflow-x:auto; white-space:nowrap; }
          .gd .csvb .hd{ color:var(--faint); }
          .gd .csvb .neg{ background:color-mix(in srgb,#f59e0b 22%,transparent); border-radius:3px; }
          .gd .cinv, .gd .cpay{ display:none; }
          .gd .stage[data-step="2"] .cinv{ display:block; }
          .gd .stage[data-step="5"] .cpay{ display:block; }
          .gd .ctip{ font-size:.64rem; color:var(--soft); margin:.45rem 0 0; line-height:1.45; }

          /* ---- third-party screens, drawn plainly ---- */
          .gd .ext{ border:1px solid var(--line); border-radius:12px; background:var(--surface); padding:.65rem .8rem; max-width:31rem; }
          .gd .ext .who{ font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; margin-bottom:.35rem; }
          .gd .ext h4{ margin:0 0 .5rem; font-size:.86rem; color:var(--ink); }
          .gd .ext .crumb{ font-size:.66rem; color:var(--soft); margin-bottom:.45rem; }
          .gd .ext .file{ border:1px dashed var(--line); border-radius:7px; padding:.35rem .5rem; font-size:.68rem; color:var(--ink); margin-bottom:.45rem; }
          .gd .ext .rad{ font-size:.68rem; color:var(--soft); margin:.2rem 0; display:flex; gap:.35rem; align-items:center; }
          .gd .ext .rad .o{ width:.7rem; height:.7rem; border-radius:50%; border:2px solid var(--line); }
          .gd .ext .rad.on .o{ border-color:#13b5ea; background:#13b5ea; }
          .gd .ext .rad.on{ color:var(--ink); font-weight:600; }
          .gd .ext .xb{ display:inline-flex; border-radius:7px; padding:.3rem .65rem; font-size:.7rem; font-weight:700; background:#13b5ea; color:#fff; margin-top:.35rem; }
          .gd .ext .row{ display:flex; justify-content:space-between; gap:.5rem; font-size:.66rem; border-bottom:1px solid var(--line); padding:.28rem 0; color:var(--ink); }
          .gd .ext .row span:last-child{ color:var(--soft); }
          .gd .x3, .gd .x4{ display:none; }
          .gd .stage[data-step="3"] .x3{ display:block; }
          .gd .stage[data-step="4"] .x4{ display:block; }
          .gd .tag{ font-size:.58rem; border-radius:10px; padding:.05rem .4rem; background:var(--panel); color:var(--soft); }

          /* ---- bank matching ---- */
          .gd .match{ display:grid; grid-template-columns:1fr auto 1fr; gap:.5rem; align-items:center; }
          .gd .mcell{ border:1px solid var(--line); border-radius:9px; padding:.45rem .55rem; font-size:.66rem; color:var(--ink); line-height:1.5; background:var(--surface); }
          .gd .mcell .ml{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .mcell b{ font-variant-numeric:tabular-nums; }
          .gd .marrow{ color:var(--good); font-weight:700; font-size:.9rem; }
          @media(max-width:560px){ .gd .match{ grid-template-columns:1fr; } .gd .marrow{ text-align:center; } }

          /* ---- which package ---- */
          .gd .picks{ display:grid; grid-template-columns:1fr 1fr; gap:.45rem; }
          @media(max-width:560px){ .gd .picks{ grid-template-columns:1fr; } }
          .gd .pk{ border:1px solid var(--line); border-radius:9px; padding:.45rem .55rem; font-size:.66rem; color:var(--soft); line-height:1.45; background:var(--surface); }
          .gd .pk b{ color:var(--ink); display:block; font-size:.74rem; margin-bottom:.1rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / payments</span></div>
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

                <!-- ============ Payments page ============ -->
                <div class="osc scPay">
                  <div class="hdrow">
                    <div><div class="card-t" style="margin-bottom:.1rem">Payments</div>
                      <p class="subt">Payments received against your orders.</p></div>
                    <div class="hbtns">
                      <span class="mb expinv">Export invoices (CSV)</span>
                      <span class="mb">Export payments (CSV)</span>
                      <span class="mb pri">+ Record payment</span>
                    </div>
                  </div>
                  <p class="note">CSV for <b>Xero / QuickBooks / Sage</b>. Respects the date filter below. Invoices export the line items
                     (net of VAT) so the package recomputes tax; they default to account code <b>200 (Sales)</b> and <b>20% VAT</b> &mdash;
                     remap on import if your chart of accounts differs.</p>
                  <div class="filt">
                    <div class="ft">Filter the list</div>
                    <div class="frow">
                      <span class="fd">Customer, quote #, reference...</span>
                      <span>From</span><span class="fd"><span class="fdv">dd/mm/yyyy</span><span class="fdv2">01/08/2026</span></span>
                      <span>To</span><span class="fd"><span class="fdv">dd/mm/yyyy</span><span class="fdv2">31/08/2026</span></span>
                      <span class="mb">This month</span><span class="mb lastm">Last month</span>
                    </div>
                  </div>
                </div>

                <!-- ============ CSV preview ============ -->
                <div class="osc scCsv">
                  <div class="csv cinv">
                    <div class="csvh">beverley-blinds-invoices-2026-09-01.csv</div>
                    <div class="csvb">
                      <span class="hd">ContactName,EmailAddress,InvoiceNumber,InvoiceDate,DueDate,Description,Quantity,UnitAmount,AccountCode,TaxType</span><br>
                      Emma Fletcher,emma@&hellip;,BRI-2026-0042,04/08/2026,18/08/2026,Roller &mdash; Cassette / Ada Blue (Kitchen),1,412.50,200,20% (VAT on Income)<br>
                      Emma Fletcher,emma@&hellip;,BRI-2026-0042,04/08/2026,18/08/2026,Vertical &mdash; Slimline / Cairo White (Lounge),2,310.42,200,20% (VAT on Income)<br>
                      <span class="neg">Emma Fletcher,emma@&hellip;,BRI-2026-0042,04/08/2026,18/08/2026,Discount &mdash; agreed price,1,-40.00,200,20% (VAT on Income)</span>
                    </div>
                  </div>
                  <p class="ctip cinv">One row per blind. Rows with the same <b>InvoiceNumber</b> make one invoice. The highlighted line is a
                     <b>minus</b> line &mdash; only there when you agreed a different price. QuickBooks won&rsquo;t accept minus lines (see below).</p>
                  <div class="csv cpay">
                    <div class="csvh">beverley-blinds-payments-2026-09-01.csv</div>
                    <div class="csvb">
                      <span class="hd">Date,InvoiceNumber,Customer,Amount,Method,Reference,Type</span><br>
                      05/08/2026,BRI-2026-0042,Emma Fletcher,372.00,Bank transfer,BRI-2026-0042,Deposit<br>
                      29/08/2026,BRI-2026-0042,Emma Fletcher,868.00,Bank transfer,BRI-2026-0042,Payment
                    </div>
                  </div>
                  <p class="ctip cpay">The payments file is your <b>checklist</b>: no package imports payments from a file &mdash; you tick each
                     one off against the money in your bank feed.</p>
                </div>

                <!-- ============ Xero ============ -->
                <div class="osc scXero">
                  <div class="ext x3">
                    <div class="who">In Xero</div>
                    <h4>Import invoices</h4>
                    <div class="crumb">Sales &rsaquo; Invoices &rsaquo; Import</div>
                    <div class="file">&#128196; beverley-blinds-invoices-2026-09-01.csv</div>
                    <div class="rad"><span class="o"></span>Update contact addresses from the file? &mdash; No</div>
                    <div class="rad on"><span class="o"></span>Unit prices are <b>&nbsp;tax exclusive</b></div>
                    <div class="rad"><span class="o"></span>Unit prices are tax inclusive</div>
                    <span class="xb">Import</span>
                  </div>
                  <div class="ext x4">
                    <div class="who">In Xero</div>
                    <h4>Invoices &rsaquo; Draft</h4>
                    <div class="row"><span>BRI-2026-0042 &middot; Emma Fletcher</span><span>&pound;1,240.00 <span class="tag">Draft</span></span></div>
                    <div class="row"><span>BRI-2026-0043 &middot; D. Patel</span><span>&pound;596.40 <span class="tag">Draft</span></span></div>
                    <span class="xb">Approve</span>
                  </div>
                </div>

                <!-- ============ Bank matching ============ -->
                <div class="osc scBank">
                  <div class="card-t">In your accounts package &mdash; the bank feed</div>
                  <div class="match">
                    <div class="mcell"><div class="ml">Bank line</div>29 Aug &middot; FLETCHER E<br>Ref <b>BRI-2026-0042</b><br><b>+ &pound;868.00</b></div>
                    <div class="marrow">&#8644; match</div>
                    <div class="mcell"><div class="ml">Invoice</div>BRI-2026-0042 &middot; Emma Fletcher<br>Owing <b>&pound;868.00</b></div>
                  </div>
                  <p class="ctip">The quote number is printed on your bank-details box as the <b>payment reference</b>, so it&rsquo;s right there on the bank line.</p>
                </div>

                <!-- ============ Which package ============ -->
                <div class="osc scPick">
                  <div class="card-t">Which route for your package?</div>
                  <div class="picks">
                    <div class="pk"><b>Xero</b>Import the invoices CSV as it is. Match payments from the bank feed.</div>
                    <div class="pk"><b>QuickBooks Online</b>Import with column matching (no minus lines) &mdash; or, if you only record paid sales, wait for the direct link.</div>
                    <div class="pk"><b>Sage Accounting</b>Enter paid sales from the payments file, or hand both files to your bookkeeper.</div>
                    <div class="pk"><b>FreeAgent</b>Only your accountant can import invoices &mdash; send them both files.</div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c0"><span class="n">0</span> Payments page &mdash; the two Export buttons, top right.</b>
                  <b class="c1"><span class="n">1</span> Pick the period first (Last month), then Export invoices.</b>
                  <b class="c2"><span class="n">2</span> One row per blind; one invoice per quote number.</b>
                  <b class="c3"><span class="n">3</span> Xero: Sales &rsaquo; Invoices &rsaquo; Import &mdash; &ldquo;tax exclusive&rdquo;.</b>
                  <b class="c4 good"><span class="n">4</span> They arrive as drafts &mdash; check, then Approve.</b>
                  <b class="c5"><span class="n">5</span> Export payments &mdash; your matching checklist.</b>
                  <b class="c6 good"><span class="n">6</span> Match each bank line to its invoice by the quote number.</b>
                  <b class="c7"><span class="n">7</span> Other packages &mdash; which route to take.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>First, the two files.</b> Open <b>Payments</b> in the left-hand menu (under <b>Retail</b>). If it isn&rsquo;t there, the
             <b>Accounts</b> add-on isn&rsquo;t on your plan &mdash; ask us through <b>? Help</b>. The export buttons are only shown to
             admins.</p>
          <ul class="steps">
            <li><b>Pick the period.</b> In <b>Filter the list</b>, press <b>Last month</b> (or type your own <b>From</b> and <b>To</b>
                dates). The exports follow this filter, so you only get that period. Do it the same way each time &mdash; say, on the 1st
                of every month &mdash; so nothing is missed or sent twice.</li>
            <li><b>Export invoices (CSV)</b> downloads your sales: <b>one row per blind</b>, and every row with the same quote number is
                one invoice. Prices are <b>before VAT</b> &mdash; your package adds the VAT. Each row already has account code
                <code>200</code> (Sales) and <code>20% (VAT on Income)</code>, or <code>No VAT</code> if the job had none. The due date is
                14 days after the order.</li>
            <li><b>Export payments (CSV)</b> downloads the money received: date, quote number, customer, amount, how it was paid, the
                reference, and whether it was a <b>Deposit</b> or a later <b>Payment</b>.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The minus line.</b> If you agreed a different price from the list
             price (&ldquo;I&rsquo;ll do it for &pound;1,200&rdquo;), the file adds a line called <b>&ldquo;Discount &mdash; agreed
             price&rdquo;</b> with a <b>minus</b> amount, so the invoice total matches what the customer was actually charged. Xero is happy
             with that. <b>QuickBooks is not</b> &mdash; see its section.</div></div>

          <p><b>Xero &mdash; the easiest.</b> The invoices file is laid out the way Xero expects.</p>
          <ul class="steps">
            <li>In Xero go to <b>Sales &rarr; Invoices</b> and press <b>Import</b>. The first time, press <b>Download template file</b> and
                check its column headings match ours &mdash; if Xero&rsquo;s have a star in front (like <code>*ContactName</code>), just
                copy our rows underneath its headings. <b>Don&rsquo;t delete columns or rename the headings.</b></li>
            <li>Press <b>Browse</b> and pick the invoices file.</li>
            <li>When asked whether prices are tax exclusive or inclusive, choose <b>Tax exclusive</b>. Our prices are <b>before VAT</b> &mdash;
                getting this wrong puts every total out by the VAT.</li>
            <li>Press <b>Import</b>. If Xero lists problems, press <b>Go Back</b>, fix them and import again; otherwise <b>Complete Import</b>.</li>
            <li>The invoices arrive as <b>drafts</b>. Check a couple against YourBlinds, then <b>Approve</b> them.</li>
          </ul>
          <p>Things Xero is fussy about: the <b>customer name</b> must match an existing Xero contact <em>exactly</em> or it makes a second
             one; <code>200</code> and <code>20% (VAT on Income)</code> must exist in <em>your</em> Xero (they do in a standard UK setup);
             no more than <b>500 rows</b> per file (split a big month in two); and an invoice number already in Xero is simply skipped
             &mdash; so importing the same month twice is harmless.</p>
          <p><b>Payments in Xero:</b> don&rsquo;t import them. When the money shows in your bank feed, go to <b>Reconcile</b>, use
             <b>Find &amp; Match</b> on the bank line, tick the invoice (the quote number is the reference) and press <b>Reconcile</b>.
             A deposit and a balance are two bank lines against the same invoice &mdash; Xero handles that. On the VAT Cash Accounting
             Scheme, Xero only counts the VAT once the payment is matched, which is exactly what you want.</p>

          <p><b>QuickBooks Online &mdash; works, with two tweaks.</b></p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>If you only put PAID sales into QuickBooks</b> (cash accounting, the way
             many bookkeepers work), <b>don&rsquo;t import invoices</b> &mdash; it would create lots of &ldquo;owed&rdquo; invoices you
             don&rsquo;t want. Instead use the <b>payments file</b> as your list and record each paid sale in QuickBooks, or wait for the
             direct link on <b>Settings &rarr; Accounting</b>, which will send paid sales across by itself.</div></div>
          <ul class="steps">
            <li><b>Tidy the file first:</b> open it in Excel and <b>delete any &ldquo;Discount &mdash; agreed price&rdquo; rows</b> (QuickBooks
                refuses minus lines), then change that invoice&rsquo;s other lines so they add up to the agreed price. Save it as
                <b>CSV</b>.</li>
            <li>In QuickBooks click the <b>&#9881; Settings</b> cog &rarr; <b>Import data</b> &rarr; <b>Invoices</b>. Tick the box to add new
                customers if some aren&rsquo;t in QuickBooks yet. <b>Browse</b> for the file and press <b>Next</b>.</li>
            <li><b>Match the columns:</b> ContactName &rarr; Customer, InvoiceNumber &rarr; Invoice no., InvoiceDate &rarr; Invoice date,
                DueDate &rarr; Due date, Description &rarr; description, Quantity &rarr; quantity, UnitAmount &rarr; the rate/amount, TaxType
                &rarr; tax code. Set <b>AccountCode</b> and <b>EmailAddress</b> to <b>Not applicable</b>. If QuickBooks insists on a line
                <em>amount</em> rather than a rate, add a column in Excel that is Quantity &times; UnitAmount and use that.</li>
            <li>Choose date format <b>D/M/YYYY</b> and VAT <b>Exclusive</b>, then match <code>20% (VAT on Income)</code> to
                <code>20.0% S</code> and <code>No VAT</code> to <code>No VAT</code>.</li>
            <li>Check the summary and press <b>Start import</b>. Limits: <b>100 invoices / 1,000 rows</b> per file. If some fail, note the
                reason shown and fix just those.</li>
            <li>With no product column, QuickBooks uses its general <b>&ldquo;sales&rdquo;</b> item. Fine for most people; if you want a
                &ldquo;Blinds&rdquo; item, add a column in Excel filled with <code>Blinds</code> and map it to Product/Service.</li>
          </ul>
          <p><b>Payments in QuickBooks:</b> match the bank line to the open invoice under <b>Banking</b> (or <b>+ New &rarr; Receive
             payment</b> by hand). There&rsquo;s no payments import.</p>

          <p><b>Sage Accounting.</b> Sage&rsquo;s UK help changed in July 2026: its spreadsheet import now covers <b>purchase</b> invoices,
             and the sales version may no longer be offered. Two easy routes:</p>
          <ul class="steps">
            <li><b>Paid sales by hand from the payments file</b> &mdash; in Sage go to <b>Sales &rarr; Quick entries</b>, and for each paid
                job enter Date, Customer, Reference (the quote number), Ledger account (your sales account), Net, VAT rate
                <b>Standard</b>, then match it to the money in your bank feed with <b>Match</b>.</li>
            <li><b>Hand both files to your bookkeeper</b> &mdash; they&rsquo;ll know your Sage setup. If your Sage does still show
                <b>Sales &rarr; Quick entries &rarr; Import</b>, it wants <b>one row per invoice</b> and the customer&rsquo;s Sage
                <b>account reference</b>, not their name &mdash; so the file needs reshaping first.</li>
          </ul>

          <p><b>FreeAgent.</b> FreeAgent only lets <b>accountants and bookkeepers</b> (on its Practice Partner scheme) import invoices. Send
             them both files. If you do your own FreeAgent, explain each bank receipt as an <b>Invoice Receipt</b> against the invoice, or
             ask your accountant about entering your sales from the payments file.</p>

          <p><b>Good habits whichever package you use.</b></p>
          <ul class="steps">
            <li><b>Same period every time</b> &mdash; use the <b>Last month</b> button on the 1st and you&rsquo;ll never double up or miss one.</li>
            <li><b>Keep the files</b> in a folder by month, so your bookkeeper can see what went in.</li>
            <li><b>Check the totals:</b> the invoices file&rsquo;s total (plus VAT) should match YourBlinds&rsquo; figures for the same month.</li>
            <li><b>Account code 200 isn&rsquo;t right for you?</b> Change it in Excel (e.g. Sage uses 4000-style codes) or let your package
                remap it on import.</li>
          </ul>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Payments page; Export invoices and Export payments top right.',  'Getting your sales into your accounts package starts on the Payments page. Top right there are two buttons: Export invoices, and Export payments. They download spreadsheet files your package can read.', 0],
            ['0:13', 'Last month pressed; dates fill; Export invoices highlighted.',    'Pick the period first — press Last month, or type your own dates — because both files follow that filter. Then press Export invoices.', 1],
            ['0:24', 'The invoices CSV: one row per blind; a minus discount line.',      'The invoices file has one row per blind, and every row with the same quote number makes one invoice. Prices are before VAT. If you agreed a special price, there\'s a minus line called Discount so the total matches what you charged.', 2],
            ['0:38', 'Xero import screen: tax exclusive selected.',                     'In Xero, go to Sales, Invoices, Import. Choose the file, and when it asks, say the prices are tax exclusive. Then press Import.', 3],
            ['0:48', 'Invoices arrive as drafts; Approve.',                             'The invoices arrive as drafts. Check one or two against YourBlinds, then approve them.', 4],
            ['0:56', 'The payments CSV.',                                               'Now the payments file. No accounts package imports payments from a file — this is your checklist for matching the money.', 5],
            ['1:04', 'Bank line matched to the invoice by the quote number.',          'When the money shows in your bank feed, match it to the invoice. The quote number is the customer\'s payment reference, so it\'s easy to spot.', 6],
            ['1:13', 'Which route: Xero, QuickBooks, Sage, FreeAgent.',                'QuickBooks can import the invoices too, once you take out any minus lines and match the columns — or, if you only record paid sales, wait for the direct link. For Sage and FreeAgent, the simplest route is your bookkeeper, with both files.', 7],
        ],
];
