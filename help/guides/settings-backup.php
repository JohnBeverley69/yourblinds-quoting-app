<?php
declare(strict_types=1);

/**
 * Guide: settings-backup
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors the real screen: admin/settings.php (tab 7, data-panel="backup")
 * and the download it fires, admin/export.php. Every label, string, column
 * heading and filename below is taken from those two files.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Back up your data',
        'eyebrow' => 'Settings · Back up data',
        'blurb'   => 'Download your quotes and orders — two Excel sheets or a printable PDF. A full Excel (no dates) is the one that counts, and the one that unlocks “changes since last backup”.',
        'lede'    => 'The last tab in Settings hands you a copy of your <b>quotes and orders</b> &mdash; with their line items,
                      totals and payments &mdash; to keep on your own computer. Nothing to fill in, nothing to save: every
                      button here just gives you a file. Take a <b>full Excel with no dates</b> and the page starts tracking
                      what has changed since, so next time you can grab just the new bits.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* ---- the seven Settings tabs (this screen is the seventh) ---- */
          .gd .sttabs{ display:flex; flex-wrap:wrap; gap:.15rem; border-bottom:1px solid var(--line); margin:0 0 .85rem; }
          .gd .sttab{ font-size:.67rem; font-weight:600; color:var(--faint); padding:.3rem .48rem; border:1px solid transparent; border-bottom:none; border-radius:7px 7px 0 0; margin-bottom:-1px; white-space:nowrap; }
          .gd .sttab.on{ color:var(--accent-ink); background:var(--surface); border-color:var(--line); border-bottom-color:var(--surface); }
          .gd .stage[data-step="1"] .sttab.on{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="1"] .card-t{ color:var(--accent-ink); }

          /* ---- the two grey explainer paragraphs (class ui-hint on the real page) ---- */
          .gd .bkhint{ color:var(--soft); font-size:.71rem; line-height:1.55; margin:0 0 .85rem; max-width:34rem; }
          .gd .bkhint.last{ color:var(--faint); margin:.85rem 0 0; }
          .gd .bkhint b{ color:var(--ink); }

          /* ---- From / To + the three preset buttons ---- */
          .gd .bkrange{ display:flex; align-items:flex-end; gap:.5rem .85rem; flex-wrap:wrap; margin:0 0 .75rem; }
          .gd .bkf label{ text-transform:none; letter-spacing:0; font-size:.68rem; color:var(--soft); }
          .gd .dfill{ width:8.6rem; }
          .gd .dfill::after{ content:"\1F4C5"; font-size:.68rem; margin-left:auto; position:relative; z-index:2; opacity:.65; }
          .gd .stage[data-step="3"] .dfill .ph{ opacity:0; }
          .gd .stage[data-step="3"] .dfill .val{ opacity:1; animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="3"] .dfill{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .psets{ display:flex; gap:.35rem; flex-wrap:wrap; border-radius:9px; }
          .gd .pbtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.26rem .55rem; font-size:.71rem; font-weight:600; color:var(--soft); background:var(--surface); transition:transform .12s, filter .12s; }
          .gd .stage[data-step="2"] .p-all{ transform:scale(.94); filter:brightness(.9); }
          .gd .stage[data-step="3"] .psets{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .emptynote{ display:none; align-self:center; font-size:.64rem; font-style:italic; color:var(--faint); border:1px dashed var(--line); border-radius:20px; padding:.1rem .5rem; }
          .gd .stage[data-step="2"] .emptynote, .gd .stage[data-step="4"] .emptynote{ display:inline-block; }

          /* ---- the two download buttons ---- */
          .gd .bkbtns{ display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .dl{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:8px; padding:.4rem .75rem; font-size:.78rem; font-weight:700; color:var(--ink); background:var(--surface); transition:transform .12s, filter .12s; }
          .gd .dl.pri{ background:var(--accent); color:#fff; border-color:transparent; }
          .gd .stage[data-step="4"] .dl.pri{ transform:scale(.96); filter:brightness(1.15); }
          .gd .stage[data-step="6"] .dl.sec{ transform:scale(.96); border-color:var(--accent); color:var(--accent-ink); }

          /* ---- the browser\'s OWN download bar: chrome, not part of the app ---- */
          .gd .dlbar{ display:none; align-items:center; gap:.45rem; flex-wrap:wrap; margin:.85rem -1.2rem 0; padding:.42rem 1.2rem; border-top:1px dashed var(--line); background:var(--panel); }
          .gd .stage[data-step="4"] .dlbar, .gd .stage[data-step="5"] .dlbar, .gd .stage[data-step="6"] .dlbar,
          .gd .stage[data-step="7"] .dlbar, .gd .stage[data-step="8"] .dlbar{ display:flex; }
          .gd .dlbar .chr{ font-size:.55rem; letter-spacing:.11em; text-transform:uppercase; font-weight:700; color:var(--faint); }
          .gd .fchip{ display:inline-flex; align-items:center; gap:.28rem; border:1px solid var(--line); border-radius:6px; background:var(--surface); padding:.16rem .45rem; font-size:.65rem; color:var(--ink); }
          .gd .fchip.pdfc{ display:none; }
          .gd .stage[data-step="6"] .fchip.pdfc, .gd .stage[data-step="7"] .fchip.pdfc, .gd .stage[data-step="8"] .fchip.pdfc{ display:inline-flex; }

          /* ---- what is inside the Excel: the two sheets and their headings ---- */
          .gd .sheets{ display:none; margin-top:.75rem; border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:33rem; }
          .gd .stage[data-step="4"] .sheets, .gd .stage[data-step="5"] .sheets{ display:block; }
          .gd .stage[data-step="5"] .sheets{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .shtabs{ display:flex; gap:.2rem; padding:.3rem .35rem 0; background:var(--panel); border-bottom:1px solid var(--line); }
          .gd .shtab{ font-size:.65rem; font-weight:700; color:var(--faint); padding:.2rem .5rem; border-radius:6px 6px 0 0; }
          .gd .shtab.on{ background:var(--surface); color:var(--ink); border:1px solid var(--line); border-bottom-color:var(--surface); margin-bottom:-1px; }
          .gd .shbody{ padding:.5rem .6rem; }
          .gd .shlab{ font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:var(--faint); font-weight:700; }
          .gd .hdrow{ display:flex; flex-wrap:wrap; gap:.18rem; margin:.25rem 0 .6rem; opacity:.25; transition:opacity .35s; }
          .gd .shbody > .hdrow:last-child{ margin-bottom:0; }
          .gd .stage[data-step="5"] .hdrow{ opacity:1; }
          .gd .hcell{ border:1px solid var(--line); background:var(--panel); border-radius:4px; padding:.1rem .34rem; font-size:.6rem; font-weight:600; color:var(--ink); white-space:nowrap; }

          /* ---- what the PDF looks like ---- */
          .gd .pdfp{ display:none; margin-top:.75rem; }
          .gd .stage[data-step="6"] .pdfp{ display:block; }
          .gd .pdflab{ font-size:.58rem; text-transform:uppercase; letter-spacing:.09em; color:var(--faint); font-weight:700; margin-bottom:.22rem; }
          .gd .pdfsheet{ border:1px solid var(--line); border-radius:8px; background:var(--surface); padding:.5rem .6rem; max-width:33rem; box-shadow:var(--gd-shadow); }
          .gd .pdfsheet .t{ font-size:.75rem; font-weight:700; color:var(--ink); }
          .gd .pdfsheet .sub{ font-size:.6rem; color:var(--faint); margin:.1rem 0 .4rem; }
          .gd .pdfhead,
          .gd .pdfrow{ display:grid; grid-template-columns:3.9rem minmax(0,1fr) 3.3rem 3.4rem 3.9rem 3rem 2.9rem 3.2rem; gap:.14rem; }
          .gd .pdfhead span{ background:#1f3b5b; color:#fff; font-size:.57rem; border-radius:3px; padding:.12rem .34rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .pdfrow{ padding:.16rem 0; border-bottom:1px solid var(--line-2); }
          .gd .pdfrow span{ font-size:.57rem; color:var(--ink); padding:0 .34rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .pdfhead .num, .gd .pdfrow .num{ text-align:right; }
          .gd .pdfmore{ font-size:.58rem; font-style:italic; color:var(--faint); padding:.26rem .34rem 0; }

          /* ---- the bordered box: before a backup, and after one ---- */
          .gd .bkbox{ margin-top:.9rem; border:1px solid var(--line); border-radius:8px; padding:.55rem .7rem; max-width:33rem; }
          .gd .bkbox .pre{ margin:0; font-size:.71rem; color:var(--faint); line-height:1.5; }
          .gd .bkbox .post{ display:none; }
          .gd .bkbox .post p{ margin:0 0 .45rem; font-size:.71rem; color:var(--soft); line-height:1.5; }
          .gd .bkbox .post p b{ color:var(--ink); }
          .gd .stage[data-step="7"] .bkbox .pre, .gd .stage[data-step="8"] .bkbox .pre{ display:none; }
          .gd .stage[data-step="7"] .bkbox .post, .gd .stage[data-step="8"] .bkbox .post{ display:block; }
          .gd .stage[data-step="7"] .bkbox{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .sincebtn{ display:inline-block; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.22rem .55rem; font-size:.7rem; font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .stage[data-step="8"] .sincebtn{ border-color:var(--accent); color:var(--accent-ink); box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---- closing advisory in the mock (step 8) ---- */
          .gd .bkwarn{ display:none; gap:.5rem; align-items:flex-start; background:color-mix(in srgb,#f59e0b 12%,transparent); border-left:3px solid #f59e0b; border-radius:8px; padding:.45rem .6rem; font-size:.7rem; line-height:1.5; color:var(--ink); margin-top:.75rem; max-width:33rem; }
          .gd .stage[data-step="8"] .bkwarn{ display:flex; }
          .gd .bkwarn b{ color:#b45309; }
          :root[data-theme="dark"] .gd .bkwarn b{ color:#fbbf24; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .bkwarn b{ color:#fbbf24; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <div class="sttabs">
                  <span class="sttab">Company</span><span class="sttab">Quoting</span><span class="sttab">Legal</span>
                  <span class="sttab">Status colours</span><span class="sttab">Suppliers</span><span class="sttab">Accounting</span>
                  <span class="sttab on">Back up data</span>
                </div>

                <div class="card-t">Back up your data</div>
                <p class="bkhint">Download a copy of <b>your quotes and orders</b> (with their line items, totals and
                   payments) to keep on your own computer. Useful as a regular off-site backup or to work with your
                   figures in a spreadsheet.</p>

                <div class="bkrange">
                  <div class="fld bkf"><label>From</label>
                    <div class="box dfill"><span class="ph">dd/mm/yyyy</span><span class="val">01/09/2026</span></div></div>
                  <div class="fld bkf"><label>To</label>
                    <div class="box dfill"><span class="ph">dd/mm/yyyy</span><span class="val">30/09/2026</span></div></div>
                  <div class="psets">
                    <span class="pbtn p-all">All time</span><span class="pbtn p-30">Last 30 days</span><span class="pbtn p-yr">This year</span>
                  </div>
                  <span class="emptynote">&larr; both boxes empty</span>
                </div>

                <div class="bkbtns">
                  <span class="dl pri">&#11015; Download Excel (.xlsx)</span>
                  <span class="dl sec">&#11015; Download PDF summary</span>
                </div>

                <div class="sheets">
                  <div class="shtabs"><span class="shtab on">Quotes &amp; Orders</span><span class="shtab">Line items</span></div>
                  <div class="shbody">
                    <div class="shlab">Sheet 1 &mdash; Quotes &amp; Orders</div>
                    <div class="hdrow">
                      <span class="hcell">Quote #</span><span class="hcell">Customer</span><span class="hcell">Postcode</span>
                      <span class="hcell">Status</span><span class="hcell">Created</span><span class="hcell">Accepted</span>
                      <span class="hcell">Total &pound;</span><span class="hcell">Deposit paid &pound;</span>
                      <span class="hcell">Received &pound;</span><span class="hcell">Outstanding &pound;</span>
                    </div>
                    <div class="shlab">Sheet 2 &mdash; Line items</div>
                    <div class="hdrow">
                      <span class="hcell">Quote #</span><span class="hcell">Customer</span><span class="hcell">Line</span>
                      <span class="hcell">Room</span><span class="hcell">Product</span><span class="hcell">System</span>
                      <span class="hcell">Fabric</span><span class="hcell">Colour</span><span class="hcell">Width (mm)</span>
                      <span class="hcell">Drop (mm)</span><span class="hcell">Qty</span><span class="hcell">Line total &pound;</span>
                    </div>
                  </div>
                </div>

                <div class="pdfp">
                  <div class="pdflab">The PDF &mdash; A4 landscape</div>
                  <div class="pdfsheet">
                    <div class="t">Demo Blinds Ltd &mdash; Quotes &amp; Orders</div>
                    <div class="sub">42 records &middot; All data &middot; generated 19 Sep 2026</div>
                    <div class="pdfhead">
                      <span>Quote #</span><span>Customer</span><span>Postcode</span><span>Status</span>
                      <span>Date</span><span class="num">Total</span><span class="num">Received</span><span class="num">Outstanding</span>
                    </div>
                    <div class="pdfrow">
                      <span>BEV-2026-0042</span><span>Jane Smith</span><span>LS1 4AB</span><span>Accepted</span>
                      <span>12 Sep 2026</span><span class="num">&pound;1,240.00</span><span class="num">&pound;620.00</span><span class="num">&pound;620.00</span>
                    </div>
                    <div class="pdfrow">
                      <span>BEV-2026-0041</span><span>M Okafor</span><span>HG1 2QT</span><span>Ordered</span>
                      <span>9 Sep 2026</span><span class="num">&pound;486.00</span><span class="num">&pound;486.00</span><span class="num">&pound;0.00</span>
                    </div>
                    <div class="pdfmore">&hellip; and 40 more rows, one per job.</div>
                  </div>
                </div>

                <div class="bkbox">
                  <p class="pre">Once you take a full Excel backup (the button above, with no dates set), a
                     &ldquo;Changes since last backup&rdquo; option appears here so you can grab only what&rsquo;s new
                     or changed next time.</p>
                  <div class="post">
                    <p>Last full backup: <b>19 Sep 2026, 9:14am</b>. Get just the quotes and orders created or changed since then:</p>
                    <span class="sincebtn">&#11015; Changes since last backup (Excel)</span>
                  </div>
                </div>

                <div class="bkwarn"><span class="hi">&#9888;</span><div><b>Goes by when a job was last touched</b> &mdash;
                   not its order date &mdash; and it ignores the From and To boxes. Quotes and orders only: no customers,
                   products, price tables or invoices, and nothing to load back in.</div></div>

                <p class="bkhint last">The <b>Excel</b> file has two sheets &mdash; a Quotes &amp; Orders summary and a full
                   Line items list &mdash; and is the one to keep as your backup (you can open, sort and filter it). The
                   <b>PDF</b> is a printable one-look summary. Dates filter by <b>order date</b> (accepted, or created if not
                   yet accepted). A full Excel backup (no dates) is what the &ldquo;since last backup&rdquo; option counts from.</p>

                <div class="dlbar">
                  <span class="chr">Browser downloads</span>
                  <span class="fchip">&#128200; Demo-Blinds-Ltd-orders-2026-09-19.xlsx</span>
                  <span class="fchip pdfc">&#128196; Demo-Blinds-Ltd-orders-2026-09-19.pdf</span>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Settings, seventh tab &mdash; and there&rsquo;s no Save here.</b>
                  <b class="c2"><span class="n">2</span> Leave both dates empty &mdash; that&rsquo;s the full backup.</b>
                  <b class="c3"><span class="n">3</span> Or pick a From and a To for a slice.</b>
                  <b class="c4"><span class="n">4</span> Dates empty again &mdash; Download Excel.</b>
                  <b class="c5"><span class="n">5</span> Two sheets: the jobs, and every line.</b>
                  <b class="c6"><span class="n">6</span> The PDF is the printable one &mdash; not your backup.</b>
                  <b class="c7 good"><span class="n">7</span> The grey box turns into a date and a button.</b>
                  <b class="c8"><span class="n">8</span> Know what it holds &mdash; then get it off the machine.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar. There are <b>seven tabs</b> along the top &mdash; Company, Quoting,
             Legal, Status colours, Suppliers, Accounting and <b>Back up data</b> &mdash; and this one is the last.
             (Settings remembers the tab you were on, so once you have been here you land back here next time.)
             It is an <b>admin-only</b> page.</p>
          <p><b>There is no Save button on this tab, and nothing to fill in.</b> Nothing you do here is stored.
             Every button simply hands you a file to keep: your <b>quotes and orders</b>, with their line items,
             totals and payments, downloaded onto your own computer.</p>
          <ul class="steps">
            <li><b>For a proper backup, leave both dates empty.</b> Or press <b>All time</b>, which empties them for
                you &mdash; that is all that button does. Empty dates means everything you have ever quoted, and that
                is the only kind the system counts as a <b>full backup</b>.</li>
            <li><b>Want a slice instead?</b> Put a date in <b>From</b> and <b>To</b> (they are ordinary date boxes with
                the little calendar), or let <b>Last 30 days</b> or <b>This year</b> fill them in for you.
                <b>Last 30 days</b> puts today minus thirty days in From and today in To; <b>This year</b> puts the 1st
                of January in From and today in To. Neither button stays looking &ldquo;pressed&rdquo; &mdash; look at
                the boxes to see what it did.</li>
            <li><b>What the dates mean.</b> They go by the <b>order date</b>: the day the job was accepted, or the day
                it was created if it has not been accepted yet. The <b>To</b> date is <b>included</b> &mdash; the whole
                of that day, right up to midnight &mdash; so a 30 September &ldquo;To&rdquo; does include the 30th.</li>
            <li><b>&#11015; Download Excel (.xlsx)</b> is the one to keep. It lands in your <b>Downloads</b> folder,
                named after your company and today&rsquo;s date &mdash; <code>&lt;your company&gt;-orders-&lt;date&gt;.xlsx</code>,
                so <b>Demo Blinds Ltd</b> gets <code>Demo-Blinds-Ltd-orders-2026-09-19.xlsx</code>. It appears in your
                <b>browser&rsquo;s</b> download bar, not anywhere in the app &mdash; nothing is kept on the server.</li>
            <li><b>&#11015; Download PDF summary</b> gives you a printable list instead: A4 landscape, headed
                <b>&ldquo;Demo Blinds Ltd &mdash; Quotes &amp; Orders&rdquo;</b> with a line underneath like
                <b>&ldquo;42 records &middot; All data &middot; generated 19 Sep 2026&rdquo;</b>, then a row per order
                across eight columns: Quote #, Customer, Postcode, Status, Date, Total, Received, Outstanding. If there
                is nothing to list it prints one line: <b>&ldquo;No quotes or orders yet.&rdquo;</b> Good for filing or
                handing over &mdash; but it is <b>not your backup</b>: you cannot sort it or work in it.</li>
            <li><b>Then look at the bordered box.</b> Before your first full Excel it just explains itself in grey.
                After one, it turns into <b>&ldquo;Last full backup: 19 Sep 2026, 9:14am&rdquo;</b> &mdash; a date
                <em>and</em> a time &mdash; with a <b>&#11015; Changes since last backup (Excel)</b> button beside it.</li>
          </ul>
          <p><b>What is actually inside the Excel.</b> Two sheets, and it is worth knowing the headings before you open it:</p>
          <ul class="steps">
            <li><b>Sheet 1 &mdash; &ldquo;Quotes &amp; Orders&rdquo;</b>, a row per job:
                <b>Quote #, Customer, Postcode, Status, Created, Accepted, Total &pound;, Deposit paid &pound;,
                Received &pound;, Outstanding &pound;</b>. The last two are worked out from the payments and the paid
                deposit, so this sheet doubles as your <b>who-still-owes-me</b> list &mdash; sort by Outstanding.</li>
            <li><b>Sheet 2 &mdash; &ldquo;Line items&rdquo;</b>, a row per blind:
                <b>Quote #, Customer, Line, Room, Product, System, Fabric, Colour, Width (mm), Drop (mm), Qty,
                Line total &pound;</b>. The product, system, fabric and colour names are the ones <em>as they were when
                the job was quoted</em>, so an old order still reads correctly after you rename something.</li>
          </ul>
          <p>On a <b>trade order with no customer record</b>, the Customer and Postcode columns fall back to the
             <b>end customer&rsquo;s</b> name and postcode as typed on the order. If those were left blank as well,
             the cell simply comes out <b>empty</b> &mdash; nothing is invented to fill it, so a blank Customer or
             Postcode in the file means nobody filled the end-customer details in on the job itself.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Only a full Excel counts as a backup.</b>
             Excel with <b>both dates empty</b> &rarr; <b>counts</b>, and moves the &ldquo;last full backup&rdquo; date
             on. Excel with a From or a To in (including anything <b>Last 30 days</b> or <b>This year</b> typed in for
             you) &rarr; <b>does not count</b>, and the line does not move. The <b>PDF</b> &rarr; <b>never</b> counts.
             That is deliberate: a part export must not be allowed to move the marker, or the next &ldquo;changes
             since&rdquo; file would quietly skip everything the part export left out. <b>All time</b> is safe, because
             it clears both boxes.</div></div>
          <div class="oops"><b>A badly typed date is ignored without a word.</b> If what is in a date box is not a proper
             date, the export simply drops that end of the range &mdash; no red banner, no message, you just quietly get
             <em>more</em> than you asked for. So if a file comes back much bigger than expected, go back and look at the
             two boxes.</div>
          <p><b>The catch in &ldquo;Changes since last backup&rdquo;.</b> That button works on a different clock from the
             From/To boxes. From/To go by the <b>order date</b>; this one goes by <b>when the job was last touched</b>
             &mdash; created, accepted or edited. So an order from March that you tweaked yesterday <em>is</em> in it.
             It also <b>ignores the From and To boxes completely</b>. It is there so that after one full Excel you can
             keep topping up with small files instead of pulling the lot every time.</p>
          <p><b>What this file is not.</b> It is your <b>quotes and orders only</b>. It does <em>not</em> contain your
             customer list, your products, your price tables, your fabrics, your invoices, the payments ledger detail,
             your uploaded logo and images, your settings, or anything from the factory floor. And there is <b>no
             &ldquo;restore&rdquo;</b> &mdash; you cannot load this file back in. It is a readable copy for your own
             records and your spreadsheet, not a system rebuild. (The full database backup-and-restore tool is a separate,
             super-admin-only thing, and the host backs the server up nightly regardless.)</p>
          <p><b>Then get it off this computer.</b> The file is plain and unencrypted, and it is streamed straight to your
             browser &mdash; no copy is kept on the server. So treat it like paperwork: onto a memory stick, into a cloud
             drive, or emailed to yourself. A backup sitting on the machine that breaks is not a backup. Do it
             <b>once a week</b>, and always before anything big.</p>
          <p><b>Two things that catch people out.</b> The neighbouring <b>Accounting</b> tab (the QuickBooks link) is
             <em>not</em> a backup &mdash; it pushes paid sales into your accounts package and nothing else, so it does
             not replace this. And if you are running in <b>Compact mode</b>, the two grey explanation paragraphs on this
             tab are hidden, so the real screen looks barer than the one in this guide &mdash; just the date boxes and the
             buttons. The buttons still do exactly the same thing.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Seventh tab highlighted; no Save.',      'Settings, last tab along — Back up data. There\'s no Save button on this page and nothing to fill in. Every button here just hands you a file to keep. It\'s your quotes and orders, with their lines, totals and payments, downloaded onto your own computer.', 1],
            ['0:16', 'All time pressed; both boxes empty.',    'For a proper backup, leave both dates empty — or press All time, which empties them for you. Empty dates means everything you\'ve ever quoted, and that\'s the one the system counts as your full backup.', 2],
            ['0:29', 'From and To typed in.',                  'If you only want a slice — for your accountant, say — put a From and a To in. Last thirty days and This year fill both boxes for you. The dates go by the order\'s date: the day it was accepted, or the day it was created if it hasn\'t been accepted yet. And the To date is included, whole day.', 3],
            ['0:46', 'Dates cleared; Excel downloads.',        'Now watch the boxes go empty again, because that\'s what makes this one the full backup. Download Excel is the one to keep. It goes to your Downloads folder, named after your company and today\'s date.', 4],
            ['0:59', 'Two sheets and their headings.',         'Two sheets. Quotes and Orders is a line per job: quote number, customer, postcode, status, created, accepted, total, deposit paid, received and outstanding — so it doubles as a who-still-owes-me list. Line items is a row per blind: room, product, system, fabric, colour, width, drop, quantity and line total.', 5],
            ['1:18', 'The PDF summary.',                       'Download PDF summary is a printed list instead — landscape, one line per order, with the totals. Good for filing or handing over. It isn\'t your backup, though: you can\'t sort it or work in it, and taking a PDF doesn\'t count as having backed up.', 6],
            ['1:33', 'Grey box becomes date + button.',        'Now look at the grey box. Take one full Excel with no dates and it turns into this: the date and time of your last full backup, and a button for just what\'s changed since. Only the full Excel does that. A dated one doesn\'t, and the PDF never does — so if you press Last thirty days and download, this line won\'t move.', 7],
            ['1:52', 'The catch, and what it isn\'t.',         'One thing to know. That button goes by when a job was last touched, not its order date — so an order from March you tweaked yesterday is in there. It ignores the From and To boxes as well. And this file is your quotes and orders, nothing else: not your customer list, your products, your price tables or your invoices, and it isn\'t something you can load back in. It\'s a readable copy — so once it\'s downloaded, get it off the computer. A memory stick, a cloud drive, an email to yourself. A backup sat on the machine that breaks isn\'t a backup.', 8],
        ],
];
