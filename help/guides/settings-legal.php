<?php
declare(strict_types=1);

/**
 * Guide: settings-legal
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors /admin/settings.php -> tab "Legal": FOUR stacked textareas
 * (terms_conditions, trade_terms_conditions, privacy_policy,
 * accept_email_body), each with a live preview, and one Save button.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Terms, trade terms & privacy',
        'eyebrow' => 'Settings · Legal',
        'blurb'   => 'The four documents behind every quote — retail terms, trade terms, privacy, and the thank-you email.',
        'lede'    => 'Four boxes on one tab, and <b>all four come ready-written</b> &mdash; you are editing wording,
                      not starting from nothing. Your quotes and invoices no longer print pages of small print: they
                      print <b>one line with a link</b> to a public page, so a word changed here changes every quote
                      already out there. The second box is the <b>trade</b> one &mdash; used whenever a quote belongs
                      to a trade account.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* ---- settings tab strip (how a first-timer finds this screen) ---- */
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.2rem; border-bottom:1px solid var(--line); padding-bottom:.35rem; margin-bottom:.75rem; }
          .gd .tb{ font-size:.66rem; color:var(--faint); padding:.18rem .42rem; border-radius:6px; white-space:nowrap; }
          .gd .tb.legal{ background:var(--accent-wash); color:var(--accent-ink); font-weight:700; }
          .gd .stage[data-step="1"] .tabs{ box-shadow:0 0 0 3px var(--accent-wash); border-radius:8px; }
          /* the Legal tab stays lit after Save — settings.php remembers the open tab */
          .gd .stage[data-step="7"] .tb.legal{ box-shadow:0 0 0 2px var(--accent-wash); }

          /* ---- the form (hidden on the last step, which shows the output) ---- */
          .gd .stage[data-step="8"] .lform{ display:none; }
          .gd .lintro{ font-size:.68rem; color:var(--soft); line-height:1.5; margin:0 0 .35rem; }
          .gd .lintro b{ color:var(--ink); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.22rem; margin:0 0 .7rem; padding:.18rem; border-radius:8px; }
          .gd .chips code{ font-size:.62rem; background:var(--bg-subtle-2,#f3f4f6); color:var(--soft); border-radius:4px; padding:.04rem .3rem; }
          .gd .stage[data-step="2"] .chips{ box-shadow:0 0 0 3px var(--accent-wash); }

          .gd .lgrp{ margin-bottom:.65rem; }
          .gd .llab{ display:block; font-size:.68rem; font-weight:700; color:var(--ink); margin-bottom:.2rem; }
          .gd .llab i{ font-style:normal; font-weight:400; color:var(--faint); border-radius:4px; padding:0 .2rem; }
          .gd .stage[data-step="3"] .llab.retail i,
          .gd .stage[data-step="4"] .llab.trade i{ color:var(--accent-ink); background:var(--accent-wash); }
          .gd .lgrp .ta{ min-height:3.3rem; font-size:.68rem; line-height:1.45; overflow:hidden; }
          .gd .lgrp .ta .ph, .gd .lgrp .ta .val{ font-size:.68rem; line-height:1.45; }

          /* boxes 2-4 fill on their own step and stay (f2..f5 stop at step 5) */
          .gd .tb2 .val, .gd .tb3 .val, .gd .tb4 .val{ opacity:0; }
          .gd .stage[data-step="4"] .tb2 .val, .gd .stage[data-step="5"] .tb2 .val,
          .gd .stage[data-step="6"] .tb2 .val, .gd .stage[data-step="7"] .tb2 .val,
          .gd .stage[data-step="5"] .tb3 .val, .gd .stage[data-step="6"] .tb3 .val,
          .gd .stage[data-step="7"] .tb3 .val,
          .gd .stage[data-step="6"] .tb4 .val, .gd .stage[data-step="7"] .tb4 .val{ opacity:1; }
          .gd .stage[data-step="4"] .tb2 .ph, .gd .stage[data-step="5"] .tb2 .ph,
          .gd .stage[data-step="6"] .tb2 .ph, .gd .stage[data-step="7"] .tb2 .ph,
          .gd .stage[data-step="5"] .tb3 .ph, .gd .stage[data-step="6"] .tb3 .ph,
          .gd .stage[data-step="7"] .tb3 .ph,
          .gd .stage[data-step="6"] .tb4 .ph, .gd .stage[data-step="7"] .tb4 .ph{ opacity:0; }
          .gd .stage[data-step="4"] .tb2, .gd .stage[data-step="5"] .tb3, .gd .stage[data-step="6"] .tb4{
            border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="4"] .tb2 .val, .gd .stage[data-step="5"] .tb3 .val,
          .gd .stage[data-step="6"] .tb4 .val{ animation:gdRoll .8s ease-out both; }

          /* ---- the always-there preview panel under every box ---- */
          .gd .lpvh{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.35rem 0 .18rem; }
          .gd .lpvh span{ text-transform:none; letter-spacing:normal; font-weight:400; }
          .gd .lpv{ border:1px solid var(--line); background:var(--panel); border-radius:6px; padding:.38rem .5rem;
            font-size:.66rem; line-height:1.5; color:var(--soft); min-height:1.5rem; }
          .gd .lpv b{ color:var(--ink); }
          .gd .lpv .pvt{ opacity:0; transition:opacity .35s; }
          .gd .stage[data-step="2"] .pv1 .pvt, .gd .stage[data-step="3"] .pv1 .pvt,
          .gd .stage[data-step="4"] .pv1 .pvt, .gd .stage[data-step="5"] .pv1 .pvt,
          .gd .stage[data-step="6"] .pv1 .pvt, .gd .stage[data-step="7"] .pv1 .pvt,
          .gd .stage[data-step="4"] .pv2 .pvt, .gd .stage[data-step="5"] .pv2 .pvt,
          .gd .stage[data-step="6"] .pv2 .pvt, .gd .stage[data-step="7"] .pv2 .pvt,
          .gd .stage[data-step="5"] .pv3 .pvt, .gd .stage[data-step="6"] .pv3 .pvt,
          .gd .stage[data-step="7"] .pv3 .pvt,
          .gd .stage[data-step="6"] .pv4 .pvt, .gd .stage[data-step="7"] .pv4 .pvt{ opacity:1; }

          /* ---- the trade-account side note (step 4) ---- */
          .gd .acctnote{ display:none; margin:.3rem 0 .55rem; border:1px dashed var(--line); border-radius:8px;
            padding:.35rem .55rem; font-size:.66rem; color:var(--soft); background:var(--panel); }
          .gd .acctnote b{ color:var(--ink); }
          .gd .stage[data-step="4"] .acctnote{ display:block; }

          /* ---- save + flash (the real banner prints ABOVE the tab strip) ---- */
          .gd .stage[data-step="7"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .lsaved{ display:none; margin:0 0 .55rem; }
          .gd .stage[data-step="7"] .lsaved{ display:flex; }

          /* ---- step 8: where the wording actually appears ---- */
          .gd .outp{ display:none; }
          .gd .stage[data-step="8"] .outp{ display:block; }
          .gd .pdoc{ border:1px solid var(--line); border-radius:8px; padding:.5rem .65rem; background:var(--surface);
            font-size:.66rem; line-height:1.55; color:var(--soft); }
          .gd .pdoc .ph2{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.3rem; }
          .gd .pdoc .plink{ color:var(--accent); word-break:break-all; }
          .gd .pubpage{ margin-top:.6rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .pubbar{ background:var(--panel); border-bottom:1px solid var(--line); padding:.28rem .55rem;
            font-size:.6rem; color:var(--faint); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; word-break:break-all; }
          .gd .pubbody{ padding:.5rem .65rem; font-size:.66rem; line-height:1.55; color:var(--soft); }
          .gd .pubco{ display:block; font-size:.85rem; font-weight:800; color:var(--ink); margin-bottom:.15rem; }
          .gd .pubt{ display:block; font-size:.62rem; text-transform:uppercase; letter-spacing:.05em;
            font-weight:600; color:var(--faint); margin:0 0 .35rem; }
          .gd .pubfoot{ margin-top:.45rem; padding-top:.3rem; border-top:1px solid var(--line); font-size:.58rem; color:var(--faint); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="okbanner lsaved"><span>&check;</span> Terms, Privacy Policy and acceptance email saved.</div>

                <div class="tabs">
                  <span class="tb company">Company</span><span class="tb">Quoting</span>
                  <span class="tb legal">Legal</span><span class="tb">Status colours</span>
                  <span class="tb">Suppliers</span><span class="tb">Accounting</span><span class="tb">Back up data</span>
                </div>

                <div class="lform">
                  <div class="card-t">Terms &amp; Conditions &amp; Privacy Policy</div>
                  <p class="lintro">These print, personalised, at the bottom of your quote PDF and the customer-facing
                     quote. A suggested template is pre-filled below &mdash; edit it to suit your business, then Save.
                     <b>Leave a box empty to show nothing.</b> This is a starting point, not legal advice &mdash;
                     have it reviewed before relying on it.</p>
                  <div class="chips">
                    <code>{{company_name}}</code><code>{{company_address}}</code><code>{{company_email}}</code><code>{{company_phone}}</code><code>{{customer_name}}</code><code>{{quote_number}}</code><code>{{date}}</code>
                  </div>

                  <div class="lgrp">
                    <span class="llab retail">Terms &amp; Conditions <i>(retail &mdash; used on retail quotes)</i></span>
                    <div class="ta f1"><span class="ph">Terms &amp; Conditions&hellip;</span><span class="val">TERMS &amp; CONDITIONS OF SALE &mdash; {{company_name}}<br>These terms apply to your order with {{company_name}}. &ldquo;You&rdquo; means {{customer_name}}, the customer named on quotation {{quote_number}} dated {{date}}&hellip;</span></div>
                    <div class="lpvh">Preview <span>&mdash; with example customer &amp; quote</span></div>
                    <div class="lpv pv1"><span class="pvt">TERMS &amp; CONDITIONS OF SALE &mdash; <b>Demo Blinds Ltd</b><br>These terms apply to your order with <b>Demo Blinds Ltd</b>. &ldquo;You&rdquo; means <b>Jane Smith</b>, the customer named on quotation <b>BEV-2026-0042</b>&hellip;</span></div>
                  </div>

                  <div class="lgrp">
                    <span class="llab trade">Terms &amp; Conditions <i>(trade &mdash; used on quotes raised for a trade account)</i></span>
                    <div class="acctnote">Quote <b>BEV-2026-0042</b> &middot; Account: <b>Bright Interiors Ltd</b> &mdash; so this quote gets the <b>trade</b> wording.</div>
                    <div class="ta tb2"><span class="ph">Terms &amp; Conditions (trade)&hellip;</span><span class="val">TERMS &amp; CONDITIONS OF SALE (TRADE) &mdash; {{company_name}}<br>4. PAYMENT &mdash; within 20 days of the end of the month of the invoice date&hellip;<br>5. DIRECTOR&rsquo;S PERSONAL GUARANTEE&hellip;</span></div>
                    <div class="lpvh">Preview <span>&mdash; with example customer &amp; quote</span></div>
                    <div class="lpv pv2"><span class="pvt">TERMS &amp; CONDITIONS OF SALE (TRADE) &mdash; <b>Demo Blinds Ltd</b><br>5. DIRECTOR&rsquo;S PERSONAL GUARANTEE &mdash; each director personally guarantees payment of all sums owed to <b>Demo Blinds Ltd</b>&hellip;</span></div>
                  </div>

                  <div class="lgrp">
                    <span class="llab">Privacy Policy</span>
                    <div class="ta tb3"><span class="ph">Privacy Policy&hellip;</span><span class="val">PRIVACY POLICY &mdash; {{company_name}}<br>11. HOW TO COMPLAIN &mdash; you may complain to the Information Commissioner&rsquo;s Office (ICO), Wycliffe House, Wilmslow SK9 5AF&hellip;</span></div>
                    <div class="lpvh">Preview <span>&mdash; with example customer &amp; quote</span></div>
                    <div class="lpv pv3"><span class="pvt">PRIVACY POLICY &mdash; <b>Demo Blinds Ltd</b><br>13. CONTACT US<br><b>Demo Blinds Ltd</b>, Unit 4, Sample Way, Leeds, LS1 1AA &mdash; hello@demoblinds.example &mdash; 01234 567890.</span></div>
                  </div>

                  <div class="lgrp">
                    <span class="llab">Thank-you email <i>(sent when a customer accepts a quote)</i></span>
                    <div class="ta tb4"><span class="ph">Thank-you email&hellip;</span><span class="val">Hello {{customer_name}},<br>Thank you for accepting your quote {{quote_number}}&hellip;<br>You can view your quote any time here: {{quote_link}}</span></div>
                    <div class="lpvh">Preview <span>&mdash; what the customer receives</span></div>
                    <div class="lpv pv4"><span class="pvt">Hello <b>Jane Smith</b>,<br>Thank you for accepting your quote <b>BEV-2026-0042</b>&hellip;<br>You can view your quote any time here: <b>https://your-site/quote-history/public.php?token=abc123</b></span></div>
                  </div>

                  <div class="save">Save terms, privacy &amp; email</div>
                </div>

                <div class="outp">
                  <div class="pdoc">
                    <div class="ph2">Quote PDF &mdash; foot of the page</div>
                    This quotation is subject to our Terms &amp; Conditions of sale:
                    <span class="plink">https://yourblinds.uk/legal/view.php?c=3&amp;doc=retail</span>.<br>
                    Privacy Policy: <span class="plink">https://yourblinds.uk/legal/view.php?c=3&amp;doc=privacy</span>.
                  </div>
                  <div class="pubpage">
                    <div class="pubbar">yourblinds.uk/legal/view.php?c=3&amp;doc=retail</div>
                    <div class="pubbody">
                      <span class="pubco">Demo Blinds Ltd</span>
                      <span class="pubt">Terms &amp; Conditions</span>
                      TERMS &amp; CONDITIONS OF SALE &mdash; Demo Blinds Ltd<br>
                      1. OUR QUOTATION &mdash; Quotations are valid for 30 days&hellip;
                      <div class="pubfoot">Provided via YourBlinds</div>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Settings &rarr; Legal &mdash; four boxes, all pre-filled.</b>
                  <b class="c2"><span class="n">2</span> Seven placeholders; a preview under every box.</b>
                  <b class="c3"><span class="n">3</span> Box one: your <em>retail</em> terms.</b>
                  <b class="c4"><span class="n">4</span> Box two: <em>trade</em> &mdash; used when the quote has an account.</b>
                  <b class="c5"><span class="n">5</span> Box three: your privacy policy.</b>
                  <b class="c6"><span class="n">6</span> Box four: the thank-you email, with the quote link.</b>
                  <b class="c7 good"><span class="n">7</span> Saved &mdash; green banner at the top, still on Legal.</b>
                  <b class="c8"><span class="n">8</span> Quotes print a link; the page is public.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> and click the <b>Legal</b> tab &mdash; third along the row: Company, Quoting,
             <b>Legal</b>, Status colours, Suppliers, Accounting, Back up data. One section, <b>four boxes</b>, one Save
             button. The good news first: <b>every box already has a full, ready-written document in it</b>, so your
             quotes are covered from day one even if you never open this page. A box you have never saved shows the
             standard wording; the moment you save, what you typed replaces it for good.</p>
          <ul class="steps">
            <li><b>The placeholders, and the preview.</b> Anything in double curly brackets fills itself in on each
                quote. Seven are offered for the legal boxes: <code>{{company_name}}</code>,
                <code>{{company_address}}</code>, <code>{{company_email}}</code>, <code>{{company_phone}}</code>,
                <code>{{customer_name}}</code>, <code>{{quote_number}}</code> and <code>{{date}}</code>. The first four
                come straight from the <b>Company</b> tab, so <b>fill that in first</b> &mdash; all three documents end
                with your contact details, and they would otherwise publish half empty. The two terms documents close
                with a prefixed line, &ldquo;Contact: {{company_name}}, {{company_address}} &mdash; {{company_email}}
                &mdash; {{company_phone}}.&rdquo;; the privacy policy carries the same details without the
                &ldquo;Contact:&rdquo; prefix, under its own heading <b>13. CONTACT US</b>. Under every box sits a grey
                <b>Preview</b> that redraws as you type, using your real company details and a fixed example: customer
                <b>Jane Smith</b>, quote <b>BEV-2026-0042</b>. (In <b>Compact mode</b> the placeholder chips are hidden
                along with the other hints &mdash; the list above is the same one.)</li>
            <li><b>Box 1 &mdash; Terms &amp; Conditions (retail &mdash; used on retail quotes).</b> The ones an ordinary
                household customer gets. Read it through and change what is not how you work &mdash; deposits, lead
                times and guarantee length are what people usually alter. As supplied it covers: 30-day quotation
                validity; survey and measurements, including the clause about an opening altered <em>after</em>
                measuring; a 50% deposit with the balance on completion; made-to-measure goods being exempt from the
                Consumer Contracts Regulations 2013 cancellation rights, with a 48-hour amendment window; colour and
                batch variation; lead times; access; child safety to BS EN 13120; motorised products; condensation and
                damp; a 12-month guarantee; fit-only and customer-supplied goods; your statutory rights under the
                Consumer Rights Act 2015; complaints; data protection; and the law of England and Wales.</li>
            <li><b>Box 2 &mdash; Terms &amp; Conditions (trade &mdash; used on quotes raised for a trade account).</b>
                It looks identical but it is a different document. There is <b>nothing to switch on</b>: if the quote
                was raised against a <b>trade account</b>, the trade wording goes out; everything else gets box 1.
                Being business-to-business, the consumer protections deliberately do not apply. Its notable clauses:
                a contract formed only when you accept an <b>order</b> (a quotation is not an offer); the price may move
                on cost increases; payment within <b>20 days of the end of the month</b> of the invoice date, with
                interest under the Late Payment of Commercial Debts (Interest) Act 1998 at 8% over base; clause 5,
                the <b>director&rsquo;s personal guarantee</b>; retention of title until paid in full; and 14 days to
                report damage or shortage.</li>
            <li><b>Box 3 &mdash; Privacy Policy.</b> What you do with a customer&rsquo;s details. Written to UK data
                protection law, it lists what you collect, why, who it is shared with, that financial records are kept
                about 6 years, where it is held, the customer&rsquo;s rights, and how to complain &mdash; naming the
                Information Commissioner&rsquo;s Office with its address and phone number.</li>
            <li><b>Box 4 &mdash; Thank-you email</b> (sent when a customer accepts a quote). Different placeholders here,
                only four: <code>{{customer_name}}</code>, <code>{{company_name}}</code>, <code>{{quote_number}}</code>
                and <code>{{quote_link}}</code>. <b>{{quote_link}}</b> is the useful one &mdash; it drops in the web
                address of their own quote so they can look at it again any time. The subject line is fixed
                (&ldquo;Thank you for accepting quote BEV-2026-0042&rdquo;), it only goes out when the quote carries a
                valid customer email address, and sending never holds up the acceptance itself.
                <b>Leave empty to send no thank-you email.</b></li>
            <li><b>Save terms, privacy &amp; email.</b> One button, at the foot, saves all four together. The page
                reloads and a green <b>&ldquo;Terms, Privacy Policy and acceptance email saved.&rdquo;</b> appears
                <b>above the row of tabs</b>, under the &ldquo;Settings&rdquo; heading &mdash; that banner is your
                receipt. You stay on <b>Legal</b>: the screen remembers the tab you had open and reopens on it, so the
                boxes and their previews are right there to read back.</li>
          </ul>
          <div class="oops"><b>Careful with an empty box.</b> Clearing the <b>retail</b> box does more than drop the
             small print: the <b>&ldquo;I agree to the Terms &amp; Conditions&rdquo;</b> tick-box disappears from the
             online quote, so customers accept with nothing agreed, and the terms link vanishes from the PDF. And there
             is <b>no undo and no Reset button</b> &mdash; the standard wording only shows in a box that has
             <em>never</em> been saved. If you might want it back, copy it out somewhere before you clear the box.</div>
          <p><b>Two things that catch people out.</b> The trade box is written to the database by a separate step from
             the other three, and on an older database that step can fail silently &mdash; the green banner still
             appears. So after your <b>first</b> trade edit, reload <b>Settings</b> &mdash; you come back on
             <b>Legal</b> &mdash; and check the trade box really kept it. And if you see <b>&ldquo;Could not save: &hellip; &mdash; have you run
             migrate_terms_conditions.php?&rdquo;</b>, nothing saved at all: that one is for whoever set the system up.</p>
          <p><b>Not the same as &ldquo;Trade terms&rdquo; in the menu.</b> The <b>Trade terms</b> page in the left-hand
             nav is about the buying <em>discounts</em> an account gets. The trade <em>document</em> lives here, on
             this tab.</p>
          <p><b>Where the wording actually appears.</b> Quotes and invoices no longer print the documents in full &mdash;
             they print one line with a link: <em>&ldquo;This quotation is subject to our Terms &amp; Conditions of sale:
             https://yourblinds.uk/legal/view.php?c=3&amp;doc=retail.&rdquo;</em> and <em>&ldquo;Privacy Policy:
             https://yourblinds.uk/legal/view.php?c=3&amp;doc=privacy.&rdquo;</em> The <code>doc=</code> bit switches
             between <code>retail</code>, <code>trade</code> and <code>privacy</code>; on an invoice it
             reads &ldquo;This invoice is subject to&hellip;&rdquo;, and on a receipt &ldquo;This receipt is
             subject to&hellip;&rdquo;. A link only prints when that
             document has something in it. Because the link is read live, <b>change a word today and every quote already
             sitting in someone&rsquo;s inbox shows the new wording next time they open it</b>. That page needs
             <b>no login</b> and is printable &mdash; anyone with the link can read it, so keep internal notes out of
             these boxes. It is headed with your company name, then the document&rsquo;s own title in small capitals
             &mdash; <b>Terms &amp; Conditions</b>, <b>Terms &amp; Conditions (Trade)</b> or <b>Privacy Policy</b>
             &mdash; then your wording, and is footed &ldquo;Provided via YourBlinds&rdquo;.</p>
          <p><b>One honest limitation.</b> The <em>online</em> quote page &mdash; the tick-box beside
             &ldquo;I agree to the Terms &amp; Conditions of &lt;your company&gt;.&rdquo; and the link at the foot
             &mdash; always shows the <b>retail</b> wording, even for a trade account. The trade wording is what a trade
             account&rsquo;s quote and invoice <b>PDF</b> link to. If a customer rings up stuck on acceptance, the two
             messages they will be reading are <b>&ldquo;Please tick the box to agree to the Terms &amp;
             Conditions.&rdquo;</b> and <b>&ldquo;Please confirm you have read and understood the Terms &amp; Conditions
             before accepting.&rdquo;</b> (with <b>&ldquo;Please type your full name to confirm acceptance.&rdquo;</b>
             and <b>&ldquo;This quote is no longer awaiting your response.&rdquo;</b> close behind).</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Not legal advice.</b> All four documents are a
             starting point, written for a typical blinds business. Have your terms, your trade terms and your privacy
             policy checked by a solicitor before you rely on them &mdash; especially the <b>director&rsquo;s personal
             guarantee</b> in the trade document, which normally needs signing separately.</div></div>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Legal tab lit; four labels; retail box fills.',
                     'This is Settings, the Legal tab — third along. Four boxes, and the good news first: every one of them is already filled in with a ready-written document, so your quotes are covered from day one even if you never touch this page. You are editing wording, not creating it from nothing.', 1],
            ['0:16', 'Token chips highlight; first preview fills.',
                     'The bits in double curly brackets fill themselves in. Seven of them for the legal documents — your company name, address, email and phone, the customer\'s name, the quote number and today\'s date. Underneath each box is a preview that redraws as you type, using your real company details and an example customer, Jane Smith, on quote BEV-2026-0042.', 2],
            ['0:33', 'The "(retail)" qualifier highlights.',
                     'The first box is your retail terms — the ones an ordinary household customer gets. Read it through and change anything that is not how you work. Deposits, lead times, guarantee length: those are the ones people usually alter.', 3],
            ['0:45', 'Trade box fills; account shown on the quote.',
                     'The second box looks identical, but it is a different document altogether — your trade terms, used whenever a quote is raised for a trade account. There is nothing to switch on: if the quote has an account on it, the trade wording is the one that goes out.', 4],
            ['0:58', 'Privacy box fills; ICO line in the preview.',
                     'Third box, your privacy policy — what you do with a customer\'s details. It is written to UK data protection law, and it already names the Information Commissioner\'s Office and how to complain.', 5],
            ['1:09', 'Email box fills; preview shows the quote link.',
                     'The last box is the email that goes out the moment a customer accepts online. Four placeholders here, not seven — and the important one is quote link, which drops in the web address of their own quote so they can look at it again any time.', 6],
            ['1:21', 'Save pressed; green banner above the tabs; still on Legal.',
                     'One button at the bottom saves all four together — Save terms, privacy and email. The page reloads, a green banner appears above the row of tabs saying Terms, Privacy Policy and acceptance email saved, and you are still on the Legal tab: the screen remembers which tab you had open, so you can read the previews back straight away.', 7],
            ['1:33', 'Quote footer link, then the public page.',
                     'And this is the point of it. Your quotes and invoices no longer print pages of terms — they print one line with a link. The customer clicks it and reads the current version on a plain web page with your company name at the top. Change a word here, and every quote already sitting in someone\'s inbox shows the new wording next time they look.', 8],
        ],
];
