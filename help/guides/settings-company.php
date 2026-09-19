<?php
declare(strict_types=1);

/**
 * Guide: settings-company
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the "Company details" fieldset on the Company tab of
 * /admin/settings.php — all ten fields, in the real row order, plus the
 * letterhead they build and the one real gotcha (you cannot blank the
 * company name; the handler falls back to the session value).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company details',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'All ten boxes on the Company tab, in order — and the letterhead they build on every quote, invoice and legal page.',
        'lede'    => 'These ten boxes are your <b>letterhead</b>. Your name, your contact details,
                      your VAT number and your address get printed at the top-left of every quote,
                      order and invoice PDF you send &mdash; and all of it bar the VAT line on the
                      web page your customer opens on their phone. Fill them in once, properly, and
                      everything that leaves the app looks like it came from <b>you</b>. Here&rsquo;s
                      every box, in the order you meet them, and where each one ends up.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* --- sidebar drawn in the real "two worlds" shape (Work / Retail / Setup) --- */
          .gd .navh{ font-size:.56rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin:.7rem 0 .15rem; padding:0 .5rem; }
          .gd .navh .chev{ font-size:.6rem; margin-left:.15rem; }
          /* Who-you-are block. In the real sidebar this sits at the TOP, directly
             under the YourBlinds brand (_partials/sidebar.php:325 .app-sidebar-user,
             app.css:244 gives it a border-BOTTOM): your full name on its own line,
             then "company name · role" under it. Not a footer. */
          .gd .navuser{ margin:.45rem 0 .25rem; padding:0 .5rem .5rem; border-bottom:1px solid rgba(255,255,255,.08); }
          .gd .navuser .nuname{ font-size:.66rem; font-weight:700; color:#fff; line-height:1.3; }
          .gd .navuser .numeta{ font-size:.6rem; color:#8fa3b3; margin-top:.1rem; line-height:1.3; }
          .gd .navuser .nfnew{ display:none; }
          .gd .app:has(.stage[data-step="7"]) .navuser .nfold,
          .gd .app:has(.stage[data-step="8"]) .navuser .nfold{ display:none; }
          .gd .app:has(.stage[data-step="7"]) .navuser .nfnew,
          .gd .app:has(.stage[data-step="8"]) .navuser .nfnew{ display:inline; color:#fff; font-weight:700; }
          .gd .app:has(.stage[data-step="7"]) .navuser{ box-shadow:0 0 0 2px #5b9bff; border-radius:7px; }
          /* step 1 rings the route in: Setup, then Settings */
          .gd .app:has(.stage[data-step="1"]) .navsetup,
          .gd .app:has(.stage[data-step="1"]) .side a.on{ box-shadow:0 0 0 2px #5b9bff; border-radius:7px; }

          /* --- page chrome: heading, flash banner, the seven tabs --- */
          .gd .pgh{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .pgs{ font-size:.7rem; color:var(--faint); margin:.1rem 0 .6rem; }
          .gd .okwrap{ visibility:hidden; margin-bottom:.6rem; }
          .gd .stage[data-step="7"] .okwrap{ visibility:visible; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.15rem; border-bottom:1px solid var(--line); margin-bottom:.9rem; }
          .gd .tab{ font-size:.68rem; color:var(--faint); padding:.3rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; }
          .gd .tab.on{ color:var(--ink); font-weight:700; border-bottom-color:var(--accent); }
          .gd .stage[data-step="1"] .tab.on{ background:var(--accent-wash); border-radius:5px 5px 0 0; }

          /* --- the form, in the real row structure --- */
          .gd .frow + .frow{ margin-top:.7rem; }
          .gd .fld.full{ margin-top:.7rem; }
          .gd .frow3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.7rem .8rem; margin-top:.7rem; }
          .gd .vhint{ font-size:.62rem; color:var(--faint); margin-top:.22rem; line-height:1.4; max-width:30rem; }

          /* The shared fill engine only runs f1..f5 and only holds to step 5.
             Top it up so the form stays filled through steps 6 and 7, and add a
             local f6 for the Town / County / Postcode row. */
          .gd .stage[data-step="6"] .f2 .ph, .gd .stage[data-step="7"] .f2 .ph,
          .gd .stage[data-step="6"] .f3 .ph, .gd .stage[data-step="7"] .f3 .ph,
          .gd .stage[data-step="6"] .f4 .ph, .gd .stage[data-step="7"] .f4 .ph,
          .gd .stage[data-step="6"] .f5 .ph, .gd .stage[data-step="7"] .f5 .ph,
          .gd .stage[data-step="6"] .f6 .ph, .gd .stage[data-step="7"] .f6 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .f2 .val, .gd .stage[data-step="7"] .f2 .val,
          .gd .stage[data-step="6"] .f3 .val, .gd .stage[data-step="7"] .f3 .val,
          .gd .stage[data-step="6"] .f4 .val, .gd .stage[data-step="7"] .f4 .val,
          .gd .stage[data-step="6"] .f5 .val, .gd .stage[data-step="7"] .f5 .val,
          .gd .stage[data-step="6"] .f6 .val, .gd .stage[data-step="7"] .f6 .val{ opacity:1; }
          .gd .stage[data-step="6"] .f6{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="6"] .f6 .val{ animation:gdRoll .8s ease-out both; }
          .gd .stage[data-step="7"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="7"] .toast{ opacity:1; transform:none; }

          /* --- scene swap: the form, then the letterhead payoff --- */
          .gd .csc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA,
          .gd .stage[data-step="2"] .scA, .gd .stage[data-step="3"] .scA,
          .gd .stage[data-step="4"] .scA, .gd .stage[data-step="5"] .scA,
          .gd .stage[data-step="6"] .scA, .gd .stage[data-step="7"] .scA{ display:block; }
          .gd .stage[data-step="8"] .scB{ display:block; }

          .gd .lhead{ border:1px solid var(--line); border-radius:10px; background:var(--panel); padding:.9rem 1rem; max-width:19rem; }
          .gd .lhlogo{ width:3.4rem; height:1.7rem; border:1px dashed var(--line); border-radius:5px; display:flex; align-items:center; justify-content:center; font-size:.52rem; color:var(--faint); margin-bottom:.45rem; }
          .gd .lhname{ font-weight:800; font-size:.95rem; color:var(--ink); margin-bottom:.25rem; }
          .gd .lhline{ font-size:.74rem; color:var(--soft); line-height:1.55; }
          .gd .lhline .mk{ background:var(--accent-wash); border-radius:4px; padding:0 .2rem; }
          /* VAT No. is on the PDF letterhead (pdf-generator/pdf.php) but NOT on the
             public quote page (quote-history/public.php stops at the email), so the
             mock tags that one line rather than pretending both carry it. */
          .gd .lhline .pdfonly{ font-size:.56rem; letter-spacing:.06em; text-transform:uppercase;
                                color:var(--faint); border:1px solid var(--line); border-radius:4px;
                                padding:0 .22rem; margin-left:.3rem; white-space:nowrap; }
          .gd .lhnote{ font-size:.68rem; color:var(--faint); margin-top:.6rem; max-width:24rem; line-height:1.5; }
          .gd .lhnote b{ color:var(--ink); }
          @media(max-width:620px){ .gd .frow3{ grid-template-columns:1fr; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / admin / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navuser">
                  <div class="nuname">Jo Taylor</div>
                  <div class="numeta"><span class="nfold">Sample Blinds</span><span class="nfnew">Demo Blinds Ltd</span> &middot; admin</div>
                </div>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh navsetup">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Company details saved.</div>

                <!-- Scene A: the real Company tab, filled box by box -->
                <div class="csc scA">
                  <div class="pgh">Settings</div>
                  <div class="pgs">Company details and per-quote defaults.</div>
                  <div class="okwrap"><div class="okbanner"><b>&check;</b> Company details saved.</div></div>
                  <div class="tabs">
                    <span class="tab on">Company</span><span class="tab">Quoting</span><span class="tab">Legal</span><span class="tab">Status colours</span><span class="tab">Suppliers</span><span class="tab">Accounting</span><span class="tab">Back up data</span>
                  </div>
                  <div class="card-t">Company details</div>

                  <div class="frow">
                    <div class="fld"><label>Company name <span class="req">*</span></label>
                      <div class="box f2"><span class="ph"></span><span class="val">Demo Blinds Ltd</span></div></div>
                    <div class="fld"><label>Contact name</label>
                      <div class="box f2"><span class="ph"></span><span class="val">Alex Sample</span></div></div>
                  </div>

                  <div class="frow">
                    <div class="fld"><label>Email</label>
                      <div class="box f3"><span class="ph"></span><span class="val">hello@demoblinds.example</span></div></div>
                    <div class="fld"><label>Phone</label>
                      <div class="box f3"><span class="ph"></span><span class="val">01234 567890</span></div></div>
                  </div>

                  <div class="fld full"><label>VAT number</label>
                    <div class="box f4"><span class="ph">e.g. GB123456789</span><span class="val">GB123456789</span></div>
                    <div class="vhint">Leave blank if your business isn&rsquo;t VAT-registered.
                      When set, it appears below your contact details on every quote PDF.</div></div>

                  <div class="fld full"><label>Address line 1</label>
                    <div class="box f5"><span class="ph"></span><span class="val">Unit 4, Sample Way</span></div></div>

                  <div class="fld full"><label>Address line 2</label>
                    <div class="box f5"><span class="ph"></span><span class="val">Sample Business Park</span></div></div>

                  <div class="frow3">
                    <div class="fld"><label>Town</label>
                      <div class="box f6"><span class="ph"></span><span class="val">Leeds</span></div></div>
                    <div class="fld"><label>County</label>
                      <div class="box f6"><span class="ph"></span><span class="val">West Yorkshire</span></div></div>
                    <div class="fld"><label>Postcode</label>
                      <div class="box f6"><span class="ph"></span><span class="val">LS1 1AA</span></div></div>
                  </div>

                  <div class="save">Save company details</div>
                </div>

                <!-- Scene B: the payoff — the letterhead those ten boxes build -->
                <div class="csc scB">
                  <div class="card-t">Top-left of your quote, invoice and receipt PDF</div>
                  <div class="lhead">
                    <div class="lhlogo">LOGO</div>
                    <div class="lhname">Demo Blinds Ltd</div>
                    <div class="lhline">
                      Unit 4, Sample Way<br>
                      Sample Business Park<br>
                      <span class="mk">Leeds LS1 1AA</span><br>
                      West Yorkshire<br>
                      01234 567890<br>
                      hello@demoblinds.example<br>
                      VAT No. GB123456789 <span class="pdfonly">PDF only</span>
                    </div>
                  </div>
                  <div class="lhnote"><b>Town and postcode share a line</b>, with the county underneath &mdash;
                    the app arranges that for you. Any box you left empty is simply dropped, so there are no gaps.
                    The web page your customer opens on their phone carries this very same block, in this very same
                    order, with <b>one difference: no VAT line</b>. That page stops at your email address &mdash;
                    <b>VAT No. prints on the PDF only</b>.</div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Setup &rarr; Settings &mdash; you land on the Company tab.</b>
                  <b class="c2"><span class="n">2</span> Company name, and the contact name.</b>
                  <b class="c3"><span class="n">3</span> Email and phone &mdash; your printed contact details.</b>
                  <b class="c4"><span class="n">4</span> VAT number &mdash; or leave it blank.</b>
                  <b class="c5"><span class="n">5</span> Address line one, then line two underneath.</b>
                  <b class="c6"><span class="n">6</span> Town, county, postcode.</b>
                  <b class="c7 good"><span class="n">7</span> Saved &mdash; and your name updates top-left.</b>
                  <b class="c8 good"><span class="n">8</span> And that&rsquo;s where every box ends up.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> The menu down the left-hand side comes in sections &mdash; <b>Work</b>,
             <b>Retail</b>, <b>Trade</b>, <b>Setup</b> and <b>Platform</b> &mdash; and <b>Setup</b> starts
             <em>closed</em>, so from the dashboard you won&rsquo;t see Settings at all until you click it.
             Click the word <b>Setup</b> to open the section, then click <b>Settings</b>. The page is headed
             <b>Settings</b>, with &ldquo;Company details and per-quote defaults.&rdquo; underneath, and a row
             of seven tabs: <b>Company</b> &middot; Quoting &middot; Legal &middot; Status colours &middot;
             Suppliers &middot; Accounting &middot; Back up data. <b>Company</b> is already picked for you.
             The first panel on it is <b>Company details</b> &mdash; ten plain typing boxes, nothing to tick,
             nothing to choose from a list.</p>

          <p><b>Work down the form.</b> They&rsquo;re in this order on screen:</p>
          <ul class="steps">
            <li><b>Company name</b> &mdash; the one with the little red <span class="req">*</span>. This is the
                name that heads every quote, order and invoice; it sits at the very top of your public terms
                and privacy pages, above the document&rsquo;s own heading; and it&rsquo;s the second half of
                those pages&rsquo; browser-tab title. Type it exactly as you want customers to read it.
                Up to 150 characters.</li>
            <li><b>Contact name</b> &mdash; the real person a customer asks for when they ring. Also up to 150.</li>
            <li><b>Email</b> &mdash; your <em>printed</em> email address: it goes on the paperwork and into the
                sign-off of the purchase orders you send suppliers. It is <b>not</b> the address your quotes are
                sent <em>from</em>. That lives on the <b>Quoting</b> tab, under <b>Email &ldquo;from&rdquo; name</b>
                and <b>Reply-to email</b>. Filling this box in won&rsquo;t change who your <em>quote</em> emails
                appear to come from. One exception worth knowing: your <b>purchase orders to suppliers</b> fall
                back to this address for their reply-to if you&rsquo;ve left <b>Reply-to email</b> on the Quoting
                tab empty &mdash; so a supplier hitting Reply lands here.</li>
            <li><b>Phone</b> &mdash; the number printed alongside the email. Up to 50 characters, and because the
                app marks it as a telephone box, a phone or tablet pops up the number keypad for it rather than
                the full keyboard.</li>
            <li><b>VAT number</b> &mdash; the app puts the advice right under the box:
                &ldquo;<em>Leave blank if your business isn&rsquo;t VAT-registered. When set, it appears below your
                contact details on every quote PDF.</em>&rdquo; It&rsquo;s the only box on this form with a faint
                example in it (<code>e.g. GB123456789</code>). Fill it in and it prints as <b>VAT No.</b> under your
                phone and email &mdash; on the quote, invoice and receipt PDFs, and on trade delivery notes, invoices,
                credit notes and statements as well. It is the one letterhead line that does <b>not</b> appear on the
                public quote page your customer opens in a browser &mdash; that page stops at your email address.
                Leave the box empty and the line simply isn&rsquo;t there anywhere. <em>(If you&rsquo;ve switched
                <b>Compact mode</b> on in the sidebar, that little grey advice line is hidden to save room &mdash;
                the box itself works exactly the same.)</em></li>
            <li><b>Address line 1</b> &mdash; full width, on its own row. Unit or building first. Up to 150.</li>
            <li><b>Address line 2</b> &mdash; its own row underneath, not beside it. Estate, or street. If you
                don&rsquo;t need it, leave it empty &mdash; empty boxes are dropped when the address is printed,
                so you won&rsquo;t get a blank gap. Never type &ldquo;N/A&rdquo; or a dash into a box you don&rsquo;t use.</li>
            <li><b>Town</b> &mdash; first of the last three, which sit side by side. Up to 100.</li>
            <li><b>County</b> &mdash; up to 100.</li>
            <li><b>Postcode</b> &mdash; a short box, 20 characters, which is plenty for any UK postcode.</li>
          </ul>

          <p><b>Then save.</b> Click <b>Save company details</b>. The page reloads and a green bar appears across
             the top, above the tabs, reading <b>&ldquo;Company details saved.&rdquo;</b> Glance at the
             <b>top-left corner</b> of the menu as well &mdash; just under the big <b>YourBlinds</b> name there&rsquo;s
             a small block with <em>your own name</em> on the first line and, on the line beneath it, your company
             name and your role, like &ldquo;Demo Blinds Ltd &middot; admin&rdquo;. That company name changes the
             moment you save. That&rsquo;s your proof it went in.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>You can&rsquo;t empty the company name out.</b>
             Clear that box, hit Save, and the old name quietly comes back &mdash; with the same green
             &ldquo;Company details saved.&rdquo; message. The app would sooner keep the name it&rsquo;s got than let
             a quote out with no name on it. So if you&rsquo;re changing it, <b>type the new name over the top</b>
             rather than clearing it first. Every other box <em>can</em> be emptied and saved blank.</div></div>

          <p><b>Where these details show up.</b> Ten boxes, a lot of paperwork:</p>
          <ul class="steps">
            <li><b>Your quote, invoice and receipt PDFs</b> &mdash; the top-left letterhead: your logo, then the
                company name, then the address, then phone, email and <b>VAT No.</b></li>
            <li><b>The public quote page</b> your customer opens from their email, on their phone &mdash; the
                same block, in the same order, <em>except</em> that it ends at your email address. The
                <b>VAT No.</b> line is not on it. If you want a customer to see your VAT number, send them
                the PDF.</li>
            <li><b>Your public terms and privacy pages</b> &mdash; your company name sits at the top of the page
                in bold, with the document&rsquo;s own heading underneath it (<b>Terms &amp; Conditions</b>,
                <b>Terms &amp; Conditions (Trade)</b> or <b>Privacy Policy</b>), and it makes up the second half
                of the browser-tab title, as in &ldquo;Privacy Policy &middot; Demo Blinds Ltd&rdquo;.</li>
            <li><b>Trade paperwork</b> &mdash; delivery notes, invoices, credit notes and statements all carry the
                identical letterhead.</li>
            <li><b>Purchase-order emails to your suppliers</b> &mdash; your company name heads the order and the
                sign-off uses your email and phone.</li>
            <li><b>Your terms and your accept email</b> &mdash; these use placeholders
                <code>{{company_name}}</code>, <code>{{company_address}}</code>, <code>{{company_email}}</code> and
                <code>{{company_phone}}</code>, which are swapped for what you typed here. Both the retail and the
                trade terms end with the line <em>&ldquo;Contact: {{company_name}}, {{company_address}} &mdash;
                {{company_email}} &mdash; {{company_phone}}.&rdquo;</em> &mdash; so a half-filled address turns into a
                visibly broken sentence on a page your customer can read. The <b>Legal</b> tab previews it with your
                real details, which is the quickest way to check yours reads properly.</li>
          </ul>

          <p>You&rsquo;ll set this up once and barely touch it again &mdash; come back if you move premises or
             register for VAT. While you&rsquo;re on this tab, two more short jobs sit just below this panel:
             <a href="/help/guide.php?g=settings-logo"><b>your company logo</b></a> and the
             <a href="/help/guide.php?g=settings-dashboard"><b>dashboard&rsquo;s joke of the day</b></a>.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Setup opens; Settings; Company tab.',        'In the menu down the left, click Setup to open it, then click Settings. You land on Company — that\'s the first of the seven tabs along the top, and it\'s the one you want. Everything on this tab is about your business, not about any one job.', 1],
            ['0:14', 'Company name and contact name type in.',     'Your company name goes in first. This is the name that heads every quote, every invoice, and the page your customer opens online. It\'s got a little red star beside it, so type it in properly — exactly as you want customers to read it. Next to it, the contact name: the real person a customer asks for when they ring.', 2],
            ['0:30', 'Email and phone type in.',                   'Now your email and your phone number. These two get printed on the paperwork — they\'re how the customer gets hold of you. One thing worth knowing: this isn\'t the address your quotes are sent from. That\'s set over on the Quoting tab, under the email "from" name and the reply-to. Here you\'re filling in what appears on the page.', 3],
            ['0:47', 'VAT number types over the example.',         'VAT number — and the app tells you itself: leave blank if your business isn\'t VAT-registered; when set, it appears below your contact details on every quote PDF. If you are registered, put it in. It prints as V-A-T number under your phone and email, and it carries onto your trade delivery notes, invoices and statements too. If you\'re not registered, leave it empty and that line simply doesn\'t appear.', 4],
            ['1:06', 'Address line 1, then line 2 underneath.',    'Now your address. Line one, and then line two on its own row underneath — unit or building first, estate or street second. If you don\'t need line two, leave it empty. Blanks are dropped, so you won\'t get a gap on the printed page.', 5],
            ['1:20', 'Town, county and postcode fill together.',   'And the last three, side by side — town, county, postcode. Quick tip: on the printed letterhead the town and the postcode end up together on one line, with the county underneath. That\'s the app doing it, not you — just put each one in its own box.', 6],
            ['1:34', 'Save; green bar; sidebar name updates.',     'Hit Save company details. The page reloads and a green bar across the top says "Company details saved." Now look top-left, just under the big YourBlinds name — there\'s your own name, and on the line under it your company name and your role. That company name has changed. That\'s how you know it took.', 7],
            ['1:46', 'The finished letterhead.',                   'And that\'s where every one of those boxes ends up — the top-left corner of your quote, invoice and receipt PDF. The page your customer opens on their phone shows the very same block in the very same order, with one difference: it stops at your email address, so the V-A-T number line isn\'t on it. One last thing worth knowing: you can\'t empty the company name out. Clear it, save, and the old name quietly comes back. The app would rather keep the name it\'s got than send out a quote with no name on it. So to change it, just type the new one over the top.', 8],
        ],
];
