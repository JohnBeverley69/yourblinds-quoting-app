<?php
declare(strict_types=1);

/**
 * Guide: quote-send-accept
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Sending & accepting',
        'eyebrow' => 'Quotes',
        'blurb'   => 'Send the quote (email, WhatsApp or link), what the customer sees on the public accept page, and what their Yes triggers on your side.',
        'lede'    => 'Once a quote is built, get it to the customer &mdash; <b>email the PDF with a one-click accept link</b>, WhatsApp it, or copy
                      the link. They open it with <b>no login</b>, read it, and <b>Accept</b>. Here&rsquo;s both sides: what you send, what they
                      see, and what their <em>Yes</em> sets off for you.',
        'open'    => '/orders/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scSend, .gd .stage[data-step="2"] .scSend{ display:block; }
          .gd .stage[data-step="3"] .scEmail{ display:block; }
          .gd .stage[data-step="4"] .scPublic, .gd .stage[data-step="5"] .scPublic{ display:block; }
          .gd .stage[data-step="6"] .scYours{ display:block; }

          /* send section */
          .gd .em-e{ display:none; color:var(--faint); }
          .gd .stage[data-step="2"] .em-v{ display:none; }
          .gd .stage[data-step="2"] .em-e{ display:inline; }
          .gd .sbtns{ display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .sbtn{ display:inline-flex; align-items:center; gap:.35rem; border-radius:8px; padding:.42rem .75rem; font-size:.75rem; font-weight:600; }
          .gd .sbtn.email{ background:var(--accent); color:#fff; }
          .gd .sbtn.wa{ background:#25d366; color:#053b1b; }
          .gd .sbtn.copy{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .stage[data-step="2"] .sbtn.email{ background:var(--line); color:var(--faint); }
          .gd .stage[data-step="2"] .sbtn.wa, .gd .stage[data-step="2"] .sbtn.copy{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .plink{ margin-top:.55rem; font-size:.64rem; color:var(--faint); }
          .gd .plink code{ background:var(--panel); border-radius:5px; padding:.05rem .3rem; }
          .gd .sliperr{ display:none; margin-top:.6rem; }
          .gd .stage[data-step="2"] .sliperr{ display:flex; }

          /* email card */
          .gd .emailcard{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:25rem; }
          .gd .ehead{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); font-size:.72rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); }
          .gd .ebody{ padding:.6rem .7rem; font-size:.74rem; color:var(--soft); line-height:1.5; }
          .gd .eaccept{ display:inline-flex; margin:.35rem 0; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.74rem; font-weight:600; }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:6px; padding:.25rem .5rem; font-size:.68rem; color:var(--soft); background:var(--surface); margin-top:.3rem; }

          /* public page */
          .gd .pubpage{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:26rem; }
          .gd .pbrand{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); }
          .gd .pbrand .co{ font-weight:700; color:var(--ink); }
          .gd .pbrand .ad{ font-size:.62rem; color:var(--faint); }
          .gd .pbody{ padding:.6rem .7rem; }
          .gd .pmeta{ font-size:.66rem; color:var(--soft); margin-bottom:.5rem; }
          .gd .pmeta b{ color:var(--ink); }
          .gd .ptbl{ width:100%; border-collapse:collapse; font-size:.66rem; }
          .gd .ptbl th{ text-align:left; font-size:.56rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.25rem .35rem; }
          .gd .ptbl td{ padding:.3rem .35rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ptbl .r{ text-align:right; }
          .gd .ptot{ text-align:right; font-weight:700; color:var(--ink); font-size:.74rem; margin-top:.35rem; }
          .gd .pdep{ margin-top:.5rem; background:#fef9c3; color:#854d0e; border-radius:7px; padding:.4rem .6rem; font-size:.68rem; }
          [data-theme="dark"] .gd .pdep{ background:#3a2f05; color:#fde68a; }
          .gd .acceptbox{ display:none; margin-top:.55rem; border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--surface); }
          .gd .stage[data-step="4"] .acceptbox{ display:block; }
          .gd .acch{ font-weight:700; color:var(--ink); font-size:.76rem; margin-bottom:.2rem; }
          .gd .accsub{ font-size:.66rem; color:var(--faint); margin-bottom:.45rem; }
          .gd .agree{ display:inline-flex; align-items:center; gap:.4rem; font-size:.68rem; color:var(--soft); margin:.4rem 0; }
          .gd .accbtns{ display:flex; gap:.5rem; margin-top:.2rem; }
          .gd .accbtn{ display:inline-flex; background:#16a34a; color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.75rem; font-weight:700; }
          .gd .decbtn{ display:inline-flex; background:var(--surface); border:1px solid var(--line); color:var(--soft); border-radius:7px; padding:.34rem .7rem; font-size:.75rem; }
          .gd .acceptedcard{ display:none; margin-top:.55rem; background:#d1fae5; color:#065f46; border-radius:9px; padding:.6rem .7rem; font-size:.74rem; }
          .gd .stage[data-step="5"] .acceptedcard{ display:block; }
          .gd .acceptedcard b{ color:#065f46; }

          /* your side */
          .gd .bdg{ display:inline-block; font-size:.6rem; font-weight:700; border-radius:20px; padding:.08rem .55rem; background:#dcfce7; color:#166534; }
          .gd .yrow{ display:flex; align-items:center; gap:.5rem; font-size:.78rem; color:var(--ink); margin-bottom:.5rem; }
          .gd .traycard{ border:1px dashed var(--line); border-radius:9px; padding:.5rem .7rem; background:var(--panel); font-size:.72rem; color:var(--soft); margin-bottom:.45rem; }
          .gd .traycard .tt{ font-weight:600; color:var(--ink); }
          .gd .ynote{ font-size:.68rem; color:var(--faint); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Quotes</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: send to customer -->
                <div class="osc scSend">
                  <div class="card-t">Send to customer</div>
                  <p class="ldesc2">Email the PDF and a link the customer can click to accept the quote online.</p>
                  <div class="fld"><label>Recipient email</label><div class="box"><span class="em-v">emma.fletcher@gmail.com</span><span class="em-e">&mdash; no email on file &mdash;</span></div></div>
                  <div class="fld" style="margin-top:.5rem"><label>Message (optional)</label><div class="ta"><span class="ph">Optional &mdash; anything to add above the standard text.</span></div></div>
                  <div class="errbanner sliperr"><span>&#9888;</span><div>Please provide a valid recipient email address. &mdash; no email? <b>Copy the link</b> or <b>WhatsApp</b> it instead.</div></div>
                  <div class="sbtns">
                    <span class="sbtn email">&#128231; Email PDF + accept link</span>
                    <span class="sbtn wa">&#128172; Send via WhatsApp</span>
                    <span class="sbtn copy">&#128279; Copy public link</span>
                  </div>
                  <p class="plink">Public link: <code>yourblinds.uk/quote-history/public.php?token=&hellip;</code></p>
                </div>

                <!-- Scene: the email the customer receives -->
                <div class="osc scEmail">
                  <div class="card-t">What lands in their inbox</div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Your quote PRE-2026-0042 from Beverley Blinds</div>
                      <div class="frm">from Beverley Blinds &middot; to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Hello Emma,<br>
                      Please find your quote (PRE-2026-0042) attached as a PDF.<br><br>
                      You can also view it online and accept it here:<br>
                      <span class="eaccept">View &amp; accept &rarr;</span><br>
                      <span class="eatt">&#128206; PRE-2026-0042.pdf</span><br><br>
                      Kind regards,<br>Beverley Blinds
                    </div>
                  </div>
                </div>

                <!-- Scene: the public quote page (accept box → accepted) -->
                <div class="osc scPublic">
                  <div class="pubpage">
                    <div class="pbrand"><div class="co">Beverley Blinds</div><div class="ad">Kenilworth &middot; 02476 644 684</div></div>
                    <div class="pbody">
                      <div class="pmeta"><b>Quote PRE-2026-0042</b> &middot; Date 31 Aug 2026 &middot; Status: Sent<br>Quote for: Emma Fletcher &mdash; Leamington Spa</div>
                      <table class="ptbl">
                        <thead><tr><th>#</th><th>Description</th><th>Qty</th><th class="r">Total</th></tr></thead>
                        <tbody>
                          <tr><td>1</td><td>Roller Blind &mdash; Sunset White (Living Room)</td><td>1</td><td class="r">&pound;50.00</td></tr>
                          <tr><td>2</td><td>Roller Blind &mdash; Storm Grey (Kitchen)</td><td>1</td><td class="r">&pound;16.00</td></tr>
                        </tbody>
                      </table>
                      <div class="ptot">Total &pound;66.00</div>
                      <div class="pdep">Deposit on acceptance: <b>&pound;33.00</b>. The balance will be due on completion.</div>

                      <div class="acceptbox">
                        <div class="acch">Accept this quote</div>
                        <div class="accsub">Type your full name to confirm acceptance. We&rsquo;ll record it as your digital sign-off and let Beverley Blinds know.</div>
                        <div class="fld"><label>Your full name</label><div class="box"><span>Emma Fletcher</span></div></div>
                        <label class="agree"><span class="tick on">&check;</span> I agree to the Terms &amp; Conditions of Beverley Blinds.</label>
                        <div class="accbtns"><span class="accbtn">Accept quote</span><span class="decbtn">Decline</span></div>
                      </div>

                      <div class="acceptedcard"><b>Quote accepted &check;</b><br>Thanks Emma! This quote was accepted on 31 Aug 2026. Beverley Blinds will be in touch.</div>
                    </div>
                  </div>
                </div>

                <!-- Scene: your side after acceptance -->
                <div class="osc scYours">
                  <div class="card-t">Back on your side</div>
                  <div class="yrow">Quote PRE-2026-0042 <span class="bdg">Accepted</span></div>
                  <div class="traycard"><span class="tt">&#128176; Deposit due on acceptance: &pound;33.00</span> &mdash; seeded at your 50% default, ready to record when it&rsquo;s paid.</div>
                  <div class="traycard"><span class="tt">&#128197; Pending Fitting</span> &mdash; &ldquo;Install: PRE-2026-0042 &mdash; Emma Fletcher&rdquo; is waiting in your calendar tray. Drag it onto a date and assign a fitter.</div>
                  <p class="ynote">No email pings you &mdash; you just see the quote move to <b>Accepted</b>, the deposit worked out, and the fitting waiting.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Send it: email the PDF + a one-click accept link.</b>
                  <b class="c2 err"><span class="n">2</span> No email on file? Copy the link or WhatsApp it.</b>
                  <b class="c3"><span class="n">3</span> They get the quote + a link to view &amp; accept.</b>
                  <b class="c4"><span class="n">4</span> They open it (no login) and Accept &mdash; name + terms.</b>
                  <b class="c5"><span class="n">5</span> &ldquo;Quote accepted&rdquo; &mdash; and they get a thank-you.</b>
                  <b class="c6 good"><span class="n">6</span> Your side: Accepted, deposit set, fitting waiting.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A built quote sits as a <b>draft</b> until you send it. The <b>Send to customer</b> panel (on the quote) gives you three ways to
             get it to them &mdash; and sending flips the quote to <b>Sent</b>.</p>
          <ul class="steps">
            <li><b>Email PDF + accept link</b> &mdash; emails the customer the quote as a <b>PDF</b> plus a link to view it online and accept.
                Needs a <b>valid recipient email</b> (it pre-fills from the customer record); add an optional message above the standard text.
                Subject: <em>&ldquo;Your quote &lt;number&gt; from &lt;you&gt;&rdquo;</em>.</li>
            <li><b>Send via WhatsApp</b> &mdash; opens WhatsApp with the same link and a short message. Only shows when the customer has a
                <b>phone number</b> AND <b>&ldquo;Customer has WhatsApp on this number&rdquo;</b> is ticked (the panel tells you which is missing).</li>
            <li><b>Copy public link</b> &mdash; copies the link to paste anywhere. The link is the customer&rsquo;s way in &mdash; <b>no login</b>,
                the long token is the key.</li>
          </ul>
          <div class="oops"><b>No email on file?</b> The email button needs a valid address, or you&rsquo;ll get
             <em>&ldquo;Please provide a valid recipient email address.&rdquo;</em> Just <b>Copy the link</b> or <b>WhatsApp</b> it instead &mdash;
             both work with no email (opening the link marks the quote Sent on its own). Note the customer then won&rsquo;t get an automatic
             thank-you email on acceptance either, since that also needs their email.</div>
          <p><b>What the customer sees.</b> The link opens a clean, branded page &mdash; your logo, the quote number and date, the blinds
             (description &amp; qty; <b>prices too</b> unless you&rsquo;ve turned line prices off in <em>Settings &rarr; Quoting</em>), the
             <b>Total</b>, the <b>deposit due on acceptance</b>, your <b>bank details</b> for payment, and a <b>Terms &amp; Conditions</b> link.
             Widths and drops are never shown.</p>
          <ul class="steps">
            <li><b>They accept.</b> Under <b>&ldquo;Accept this quote&rdquo;</b> they type their <b>full name</b> (recorded as a digital sign-off,
                with the date and their IP), tick the <b>Terms</b> box if you have terms, and hit <b>Accept quote</b>. Up comes
                <em>&ldquo;Quote accepted &check; &mdash; Thanks &lt;name&gt;! &hellip; will be in touch&rdquo;</em>, and (if they have an email) an
                automatic <b>thank-you email</b> goes out.</li>
            <li><b>Or they decline</b> &mdash; <b>Decline</b> flips it to <b>Declined</b> and quietly removes the pending fitting.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Their Yes does three things on your side &mdash; with no email to you.</b> The
             quote flips to <b>Accepted</b>, a <b>deposit</b> is worked out from your default (e.g. 50%) ready to record, and a
             <b>&ldquo;Pending Fitting&rdquo;</b> appointment (&ldquo;Install: &lt;number&gt; &mdash; &lt;name&gt;&rdquo;) drops into your
             calendar&rsquo;s tray to drag onto a date. You find out by seeing it move, not by a ping &mdash; so keep an eye on the pipeline.
             (You can also mark it accepted yourself for a phone/in-person yes &mdash; same result.)</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Accept links don&rsquo;t expire.</b> A sent quote can be accepted whenever &mdash;
             even weeks later, at the old prices. If a quote is stale, <b>Reopen it as draft</b> and re-price (or start a fresh one) rather than
             leaving an old link live. (The default Terms say &ldquo;valid for 30 days&rdquo;, but nothing enforces it.)</div></div>',
        'script'  => [
            ['0:00', 'Send to customer: three ways.',       'Once the quote is ready, send it. Email the PDF with an accept link the customer can click — or share the same link by WhatsApp, or copy it.', 1],
            ['0:09', 'No email? Copy link or WhatsApp.',     'No email on file? The email button needs one. Copy the public link or send it on WhatsApp instead — the link works for anyone, no login.', 2],
            ['0:18', 'The email they receive.',             'The customer gets an email: their quote as a PDF, and a link to view it online and accept.', 3],
            ['0:26', 'The public page; Accept.',            'That link opens their quote — no login needed. They see the blinds, the total, the deposit due on acceptance, then type their name and Accept.', 4],
            ['0:36', 'Quote accepted; thank-you sent.',     'Up comes "Quote accepted" — and a thank-you email goes to them automatically.', 5],
            ['0:44', 'Your side: accepted, deposit, fitting.', 'Nothing pings you — you just watch it move to Accepted, with the deposit worked out and a Pending Fitting waiting in your calendar to drag onto a date.', 6],
        ],
];
