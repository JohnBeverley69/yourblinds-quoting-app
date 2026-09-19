<?php
declare(strict_types=1);

/**
 * Guide: settings-bank
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Two scenes: .scForm (steps 0-5, the Quoting tab's last section) and
 * .scDoc (steps 6-8, the customer's quote + the trade wording).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Bank details for payments',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Four boxes at the bottom of the Quoting tab that put a "How to pay — bank transfer" block on every quote, invoice, receipt and trade statement — with the quote number as the reference.',
        'lede'    => 'Right at the bottom of the <b>Quoting</b> tab there are <b>four boxes</b>. Fill them in once and a
                      <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> block prints on the customer&rsquo;s quote, on the invoice you
                      email, on the receipt that goes out when a job&rsquo;s paid &mdash; and on trade invoices, credit notes and statements
                      too. The <b>quote number</b> is added as the payment reference for you, every time. Leave all four blank and the block
                      simply never appears.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scForm, .gd .stage[data-step="1"] .scForm, .gd .stage[data-step="2"] .scForm,
          .gd .stage[data-step="3"] .scForm, .gd .stage[data-step="4"] .scForm, .gd .stage[data-step="5"] .scForm{ display:block; }
          .gd .stage[data-step="6"] .scDoc, .gd .stage[data-step="7"] .scDoc, .gd .stage[data-step="8"] .scDoc{ display:block; }

          /* ---- settings tab strip: Quoting is the second of seven ---- */
          .gd .tabstrip{ display:flex; flex-wrap:wrap; gap:.1rem .25rem; border-bottom:1px solid var(--line); margin-bottom:.6rem; }
          .gd .tabx{ font-size:.68rem; color:var(--faint); padding:.28rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; }
          .gd .tabx.on{ color:var(--accent); font-weight:700; border-bottom-color:var(--accent); }

          /* ---- the three sections you scroll past to get here ---- */
          .gd .above{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; border:1px dashed var(--line); border-radius:8px;
                      padding:.3rem .55rem; margin-bottom:.6rem; font-size:.64rem; color:var(--faint); }
          .gd .above i{ font-style:normal; opacity:.8; }
          .gd .above .dn{ margin-left:auto; color:var(--accent); font-weight:700; }

          /* ---- the grey ui-hint under the heading (hidden in Compact mode) ---- */
          .gd .bhint{ font-size:.68rem; color:var(--faint); line-height:1.45; margin:-.55rem 0 .75rem; }

          /* ---- real layout: full / cols-2 / full ---- */
          .gd .rowfull{ margin-bottom:.7rem; }
          .gd .row2{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem .8rem; margin-bottom:.7rem; }
          @media(max-width:620px){ .gd .row2{ grid-template-columns:1fr; } }

          /* ---- save feedback lives at the TOP of the page ---- */
          .gd .okb{ display:none; margin-bottom:.65rem; }
          .gd .stage[data-step="5"] .okb{ display:flex; }
          .gd .alt{ display:none; margin-top:.7rem; }
          .gd .stage[data-step="5"] .alt{ display:block; opacity:.62; }
          .gd .alt .altl{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.25rem; }

          /* ---- scene B: the customer document ---- */
          .gd .qmini{ border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.65rem .8rem; max-width:24rem; }
          .gd .qmh{ font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); margin-bottom:.45rem; }
          .gd .totr{ display:flex; justify-content:space-between; font-size:.74rem; color:var(--soft); padding:.14rem 0; font-variant-numeric:tabular-nums; }
          .gd .totr.tot{ border-top:1px solid var(--line); margin-top:.18rem; padding-top:.3rem; font-weight:700; color:var(--ink); font-size:.84rem; }
          .gd .depc{ margin-top:.5rem; border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px; background:var(--panel);
                     padding:.4rem .6rem; font-size:.72rem; color:var(--soft); line-height:1.45; }
          .gd .depc b{ color:var(--ink); }
          .gd .pay{ margin-top:.55rem; border:1px solid var(--line); border-radius:8px; background:var(--panel); padding:.5rem .7rem;
                    font-size:.74rem; color:var(--ink); line-height:1.6; transition:opacity .3s; }
          .gd .pay .ph2{ display:block; font-weight:700; margin-bottom:.22rem; }
          .gd .pay .lb{ color:var(--soft); }
          .gd .pay .note{ color:var(--faint); }
          .gd .pay .refl{ display:block; margin-top:.12rem; padding:.1rem .3rem; border-radius:6px; color:var(--faint); transition:background .3s, color .3s; }
          .gd .stage[data-step="7"] .pay .refl{ background:var(--accent-wash); color:var(--accent-ink); font-weight:600; }
          .gd .tcl{ margin-top:.4rem; font-size:.66rem; color:var(--faint); }
          .gd .stage[data-step="8"] .totals, .gd .stage[data-step="8"] .depc, .gd .stage[data-step="8"] .tcl{ display:none; }
          .gd .stage[data-step="8"] .pay{ opacity:.45; }

          /* ---- the same four boxes, trade wording ---- */
          .gd .trade{ display:none; margin-top:.55rem; }
          .gd .stage[data-step="8"] .trade{ display:block; }
          .gd .tradetag{ display:inline-block; font-size:.6rem; font-weight:700; color:var(--accent-ink); background:var(--accent-wash);
                         border-radius:20px; padding:.1rem .5rem; margin-bottom:.3rem; }
          .gd .doclist{ display:flex; flex-wrap:wrap; gap:.28rem; margin-top:.5rem; }
          .gd .docl{ font-size:.62rem; border:1px solid var(--line); border-radius:20px; padding:.1rem .5rem; color:var(--soft); background:var(--surface); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ============ SCENE A: the Quoting tab, scrolled to the bottom ============ -->
                <div class="osc scForm">
                  <div class="tabstrip">
                    <span class="tabx">Company</span><span class="tabx on">Quoting</span><span class="tabx">Legal</span>
                    <span class="tabx">Status colours</span><span class="tabx">Suppliers</span><span class="tabx">Accounting</span>
                    <span class="tabx">Back up data</span>
                  </div>

                  <div class="okbanner okb"><span>&check;</span> Bank / payment details saved.</div>

                  <div class="above">
                    <i>Default margins</i><i>&middot;</i><i>Measurements</i><i>&middot;</i><i>Quote defaults</i>
                    <span class="dn">&darr; scroll to the bottom</span>
                  </div>

                  <div class="card-t">Bank details for customer payments</div>
                  <p class="bhint">Printed on the customer&rsquo;s quote / invoice so they can pay by bank transfer.
                     Leave blank to hide the &ldquo;How to pay&rdquo; block entirely.</p>

                  <div class="rowfull">
                    <div class="fld"><label>Account name</label>
                      <div class="box f1"><span class="ph">e.g. Beverley Blinds Ltd</span><span class="val">Beverley Blinds Ltd</span></div></div>
                  </div>
                  <div class="row2">
                    <div class="fld"><label>Sort code</label>
                      <div class="box f2"><span class="ph">00-00-00</span><span class="val">20-00-00</span></div></div>
                    <div class="fld"><label>Account number</label>
                      <div class="box f3"><span class="ph">12345678</span><span class="val">12345678</span></div></div>
                  </div>
                  <div class="rowfull">
                    <div class="fld"><label>Payment note (optional)</label>
                      <div class="box f4"><span class="ph">e.g. Please use your quote number as the reference</span><span class="val">BACS only please &mdash; we don&rsquo;t accept cheques.</span></div></div>
                  </div>

                  <div class="save">Save bank details</div>

                  <div class="alt">
                    <div class="altl">Or, if the database hasn&rsquo;t been set up yet</div>
                    <div class="errbanner"><span>&#9888;</span><span>Could not save bank details &mdash; run /migrate_bank_details.php first.</span></div>
                  </div>
                </div>

                <!-- ============ SCENE B: what the customer gets ============ -->
                <div class="osc scDoc">
                  <div class="qmini">
                    <div class="qmh">Quote BRI-1042 &middot; what the customer sees</div>
                    <div class="totals">
                      <div class="totr"><span>Subtotal</span><span>&pound;1,033.33</span></div>
                      <div class="totr"><span>VAT 20%</span><span>&pound;206.67</span></div>
                      <div class="totr tot"><span>Total</span><span>&pound;1,240.00</span></div>
                    </div>
                    <div class="depc"><b>Deposit on acceptance:</b> &pound;372.00. The balance will be due on completion.</div>

                    <div class="pay">
                      <span class="ph2">How to pay &mdash; bank transfer</span>
                      <span class="lb">Account name:</span> <b>Beverley Blinds Ltd</b><br>
                      <span class="lb">Sort code:</span> <b>20-00-00</b><br>
                      <span class="lb">Account number:</span> <b>12345678</b><br>
                      <span class="note">BACS only please &mdash; we don&rsquo;t accept cheques.</span>
                      <span class="refl">Please use <b>BRI-1042</b> as your payment reference.</span>
                    </div>
                    <div class="tcl">Terms &amp; Conditions &middot; Privacy Policy</div>

                    <div class="trade">
                      <span class="tradetag">Trade invoice &middot; credit note &middot; statement</span>
                      <div class="pay" style="opacity:1;margin-top:0">
                        <span class="ph2">Payment</span>
                        <span class="lb">Account:</span> <b>Beverley Blinds Ltd</b><br>
                        <span class="lb">Sort code:</span> <b>20-00-00</b><br>
                        <span class="lb">Account no:</span> <b>12345678</b><br>
                        <span class="note">BACS only please &mdash; we don&rsquo;t accept cheques.</span>
                      </div>
                      <div class="doclist">
                        <span class="docl">Quote PDF</span><span class="docl">Online quote</span><span class="docl">Invoice PDF</span>
                        <span class="docl">Receipt PDF</span><span class="docl">Trade invoice</span><span class="docl">Credit note</span>
                        <span class="docl">Statement</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Quoting tab, bottom section &mdash; the account name.</b>
                  <b class="c2"><span class="n">2</span> Sort code, in the small box on the left.</b>
                  <b class="c3"><span class="n">3</span> Account number beside it &mdash; nothing is checked.</b>
                  <b class="c4"><span class="n">4</span> Payment note &mdash; one line, and optional.</b>
                  <b class="c5 good"><span class="n">5</span> Saved &mdash; the green bar is at the top.</b>
                  <b class="c6"><span class="n">6</span> It prints under the deposit line.</b>
                  <b class="c7 good"><span class="n">7</span> The quote number is added as the reference.</b>
                  <b class="c8"><span class="n">8</span> Same four boxes on every other document.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Bank details live in <b>Settings</b> on the <b>Quoting</b> tab &mdash; the <b>second</b> of the seven tabs across the top.
             Quoting is a long tab: <b>Default margins</b> first, then <b>Measurements</b>, then <b>Quote defaults</b>, and right at the
             <b>very bottom</b> the bit we want, <b>Bank details for customer payments</b>. Scroll all the way down. It is a little form of
             its own with its own <b>Save bank details</b> button, so saving it doesn&rsquo;t touch your margins, measurements or quote
             defaults &mdash; and saving those doesn&rsquo;t touch this.</p>
          <p>Under the heading there&rsquo;s a grey line of explanation: <em>&ldquo;Printed on the customer&rsquo;s quote / invoice so they
             can pay by bank transfer. Leave blank to hide the &lsquo;How to pay&rsquo; block entirely.&rdquo;</em> If you have
             <b>Compact mode</b> switched on, that grey line is hidden and you&rsquo;ll see just the heading and four empty boxes.
             That&rsquo;s normal &mdash; nothing is broken.</p>
          <p>There are <b>four boxes</b>, and every one of them is a plain <b>one-line</b> text box:</p>
          <ul class="steps">
            <li><b>Account name</b> &mdash; full width, at the top. Up to 120 characters; the empty box reads
                <code>e.g. Beverley Blinds Ltd</code>. Type it <em>exactly</em> as your bank holds it.</li>
            <li><b>Sort code</b> &mdash; the small box on the <b>left</b> of the next row. Up to 12 characters; the empty box reads
                <code>00-00-00</code>. Dashes or no dashes &mdash; it prints exactly as you type it.</li>
            <li><b>Account number</b> &mdash; beside it on the <b>right</b>. Up to 40 characters; the empty box reads <code>12345678</code>.</li>
            <li><b>Payment note (optional)</b> &mdash; full width, underneath. It&rsquo;s wide, so it looks like a paragraph box, but it
                <b>isn&rsquo;t</b>: one line, up to 500 characters. The empty box reads
                <code>e.g. Please use your quote number as the reference</code>.</li>
            <li><b>Save bank details.</b> The confirmation, <b>&ldquo;Bank / payment details saved.&rdquo;</b>, appears as a green bar at the
                <b>top of the page</b> &mdash; while the button you pressed is at the bottom. <b>Scroll up to see it.</b> You come back to the
                Quoting tab, so you haven&rsquo;t lost your place.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>What actually switches the block on.</b> The &ldquo;How to pay&rdquo; box
             appears only if the <b>Account name</b> <em>or</em> the <b>Account number</b> has something in it. A sort code on its own, or a
             payment note on its own, prints <b>nothing at all</b> &mdash; and that catches people out. Each line inside the box is separate,
             so if you skip the sort code you simply get one line fewer. Leave <b>all four</b> blank and the box is switched off completely:
             that is the proper way to turn it off if you&rsquo;d rather customers rang you about paying.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Don&rsquo;t write the reference yourself.</b> The line
             <b>&ldquo;Please use BRI-1042 as your payment reference.&rdquo;</b> is added automatically at the bottom of the box, using that
             document&rsquo;s own quote number. If you also type &ldquo;please use your quote number as the reference&rdquo; into the
             <b>Payment note</b>, the customer reads the same instruction twice. Save the note for something they actually need &mdash;
             <em>bank transfer only</em>, or when you expect to be paid.</div></div>
          <p><b>Where these details turn up.</b> Every document reads them fresh each time it&rsquo;s produced, so you set them once and
             everything follows:</p>
          <ul class="steps">
            <li><b>The quote</b> &mdash; both the PDF and the online quote link, in a box headed
                <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> sitting directly under the deposit line
                (<em>&ldquo;Deposit on acceptance: &pound;372.00. The balance will be due on completion.&rdquo;</em>) and above the
                Terms &amp; Conditions link.</li>
            <li><b>The invoice</b> you email &mdash; same box, same place. The email itself only says
                <b>&ldquo;Balance due: &pound;120.00. Payment details are on the invoice.&rdquo;</b>, which is only true if these boxes are filled in.</li>
            <li><b>The receipt</b> that goes out on its own when a job is paid in full.</li>
            <li><b>Trade accounts</b> &mdash; the trade <b>invoice</b>, <b>credit note</b> and account <b>statement</b> print the same four
                boxes under a heading of just <b>Payment</b>, worded a little differently:
                <b>Account:</b> / <b>Sort code:</b> / <b>Account no:</b> &mdash; then your note. Same boxes, different paperwork.</li>
          </ul>
          <p><b>If something&rsquo;s wrong.</b> Three things go wrong here, and all three are quick:</p>
          <ul class="steps">
            <li><b>A red bar saying &ldquo;Could not save bank details &mdash; run /migrate_bank_details.php first.&rdquo;</b> The four
                columns aren&rsquo;t in the database yet. It&rsquo;s a one-off job for whoever looks after the system &mdash; they open
                <code>/migrate_bank_details.php</code> once, and it&rsquo;s safe to run again if anyone&rsquo;s unsure. Nothing you typed
                has gone anywhere else.</li>
            <li><b>No box on the customer&rsquo;s quote.</b> You&rsquo;ve filled in only the sort code, or only the note. Go back and add the
                account name or the account number.</li>
            <li><b>Wrong digits.</b> There is <b>no checking of any kind</b> here &mdash; no format, nothing required, no warning. A mistyped
                account number saves perfectly happily and then goes out on every document until somebody notices. Copy the numbers off a
                bank statement, read them back once, and send yourself a test quote. If you do find a mistake, fix it here and re-send: even
                an old quote printed again picks up the corrected details.</li>
          </ul>
          <p><b>One thing this is not.</b> Over on the <b>Accounting</b> tab you&rsquo;ll be asked to
             &ldquo;map your sales item, VAT code and <b>bank account</b>&rdquo;. That is a <em>QuickBooks</em> ledger account &mdash; which
             pot paid sales land in inside your accounts package. It is a completely different setting and it changes nothing about what
             prints on the customer&rsquo;s quote.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Quoting tab, bottom section; Account name types in.', 'Bank details live in Settings, on the Quoting tab — right at the bottom, under Default margins, Measurements and Quote defaults. Scroll down to "Bank details for customer payments". First box: the account name, exactly as your bank has it.', 1],
            ['0:12', 'Sort code fills — 20-00-00.',                          'Sort code next, in the little box on the left. Six digits — with or without the dashes, it prints exactly as you type it.', 2],
            ['0:19', 'Account number fills — 12345678.',                     'Then the account number beside it. Nothing here is checked for you, so copy it straight off a statement and read it back once. A typo saves perfectly happily, and then goes out on every quote.', 3],
            ['0:29', 'Payment note fills — one line.',                       'The payment note is one line, and it\'s optional. Don\'t waste it on "use your quote number" — the app already adds that line by itself. Use it for something the customer actually needs, like bank transfer only, or your terms on payment.', 4],
            ['0:41', 'Save pressed; green banner at the top.',               'Press Save bank details. The green bar — "Bank / payment details saved" — appears at the top of the page, so look up, not down. If instead you get a red one saying it needs the migration run first, that\'s a one-off job for whoever looks after the system; nothing you\'ve typed is lost.', 5],
            ['0:56', 'Scene swaps to the customer\'s quote.',                'Now look at what the customer gets. Underneath the totals and the deposit line, there\'s a box headed "How to pay — bank transfer", with your account name, sort code and account number, and your note in grey.', 6],
            ['1:08', 'Reference line highlighted.',                          'And the last line is put there for you, every time: please use this quote number as your payment reference. That\'s how the money that lands in your bank matches the job on the screen, so never change it by hand.', 7],
            ['1:19', 'Trade "Payment" wording; document list.',              'Fill these four boxes in once and they follow everything — the quote, the invoice you email, the receipt that goes out when a job\'s paid in full, and on a trade account the invoice, credit note and statement too. Fill nothing in, and the whole box simply never appears.', 8],
        ],
];
