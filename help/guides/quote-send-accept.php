<?php
declare(strict_types=1);

/**
 * Guide: quote-send-accept
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers BOTH sides of getting a quote out and getting a yes back:
 *   - the "Send to customer" panel on /quote-builder/edit.php (recipient
 *     email, optional message, the three buttons, the four WhatsApp states,
 *     the real success + failure flashes),
 *   - the plain-text email from /pdf-generator/email_pdf.php,
 *   - the whole public page /quote-history/public.php?token=… — header,
 *     items table with sizes and extras, Subtotal / VAT / Total, notes,
 *     deposit card, "How to pay", the accept card and its terms tick,
 *   - what /quote-history/accept.php sets off: pending fitting, thank-you
 *     email, new-order alert, and the in-house auto-place straight to
 *     'ordered',
 *   - and where to watch it afterwards on /orders/index.php.
 */

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Sending & accepting',
        'eyebrow' => 'Quotes',
        'blurb'   => 'The Send to customer panel, the plain-text email, the whole public accept page, and the four things a customer\'s Yes sets off on your side.',
        'lede'    => 'You send a quote from <b>inside the quote itself</b> &mdash; scroll down the quote screen to the
                      <b>Send to customer</b> panel. From there you can <b>email the PDF with an accept link</b>, share the
                      same link on <b>WhatsApp</b>, or just <b>copy the link</b> and paste it into a text. The customer opens
                      it with <b>no login</b>, reads it, types their name and <b>accepts</b>. Afterwards you watch it on
                      <b>Retail &rarr; Quotes</b> (or <b>Trade &rarr; Quotes</b>). Here&rsquo;s both sides, in full &mdash; what
                      you press, what they see, and what their <em>Yes</em> sets off for you.
                      <em>(The button below opens your Retail Quotes list &mdash; click a quote on it, then scroll down to
                      <b>Send to customer</b>. There is no send panel on the list itself.)</em>',
        'open'    => '/orders/index.php?scope=quotes&type=retail',
        'css'     => '
          /* --- shared bits --- */
          .gd .side a{ font-size:.72rem; padding:.22rem .5rem; }
          .gd .navh{ font-size:.54rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin:.55rem 0 .12rem; padding:0 .5rem; }
          .gd .navh .chev{ font-size:.58rem; margin-left:.15rem; }
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .6rem; line-height:1.5; max-width:34rem; }
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scSend,
          .gd .stage[data-step="2"] .scSend,
          .gd .stage[data-step="3"] .scSend{ display:block; }
          .gd .stage[data-step="4"] .scEmail{ display:block; }
          .gd .stage[data-step="5"] .scPublic,
          .gd .stage[data-step="6"] .scPublic,
          .gd .stage[data-step="7"] .scPublic{ display:block; }
          .gd .stage[data-step="8"] .scYours{ display:block; }

          /* --- 1-3: the Send to customer panel --- */
          .gd .stage[data-step="3"] .em-v{ display:none; }
          .gd .stage[data-step="3"] .emailfld{ border-color:var(--err) !important; box-shadow:0 0 0 3px var(--err-wash); }
          .gd .sbtns{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-top:.75rem; }
          .gd .sbtn{ display:inline-flex; align-items:center; gap:.35rem; border-radius:8px; font-weight:700; transition:transform .12s, filter .12s; }
          /* the real shapes: a big red-wash submit, a green link-button, a small grey secondary */
          .gd .sbtn.email{ background:rgba(220,38,38,.5); color:#000; font-size:.82rem; padding:.5rem .9rem; }
          .gd .sbtn.wa{ background:rgba(37,211,102,.5); color:#000; font-size:.82rem; padding:.5rem .9rem; }
          .gd .sbtn.copy{ background:var(--surface); border:1px solid var(--line); color:var(--soft); font-size:.72rem; font-weight:600; padding:.34rem .6rem; }
          .gd .stage[data-step="2"] .sbtn.email{ transform:scale(.96); filter:brightness(1.15); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .sbtn.email{ opacity:.45; }
          .gd .stage[data-step="3"] .sbtn.wa,
          .gd .stage[data-step="3"] .sbtn.copy{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .sbtn.copy .cflash{ display:none; font-style:normal; }
          .gd .stage[data-step="3"] .sbtn.copy .clbl{ display:none; }
          .gd .stage[data-step="3"] .sbtn.copy .cflash{ display:inline; color:var(--good); font-weight:700; }
          .gd .plink{ margin-top:.6rem; font-size:.63rem; color:var(--faint); word-break:break-all; }
          .gd .plink code{ background:var(--panel); border-radius:5px; padding:.05rem .3rem; }
          .gd .okwrap, .gd .slip3{ display:none; margin-top:.75rem; }
          .gd .stage[data-step="2"] .okwrap{ display:block; }
          .gd .stage[data-step="3"] .slip3{ display:block; }
          .gd .slip3 .heads{ margin-top:.55rem; font-size:.76rem; }

          /* --- 4: the plain-text email --- */
          .gd .emailcard{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:27rem; }
          .gd .ehead{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); font-size:.72rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); font-size:.65rem; }
          .gd .ebody{ padding:.65rem .7rem; font-size:.73rem; color:var(--soft); line-height:1.6; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          .gd .ebody .yours{ background:var(--accent-wash); border-radius:4px; padding:0 .2rem; color:var(--ink); }
          .gd .ebody .elink{ color:var(--accent); word-break:break-all; }
          .gd .efoot{ border-top:1px solid var(--line); padding:.45rem .7rem; background:var(--panel); }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:6px; padding:.22rem .5rem; font-size:.67rem; color:var(--soft); background:var(--surface); }
          .gd .enote{ font-size:.7rem; color:var(--faint); margin:.6rem 0 0; max-width:27rem; line-height:1.5; }
          .gd .enote b{ color:var(--ink); }

          /* --- 5-7: the public page --- */
          .gd .pubpage{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:28rem; }
          .gd .pbrand{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; gap:.6rem; }
          .gd .pbrand .co{ font-weight:700; color:var(--ink); font-size:.8rem; }
          .gd .pbrand .ad{ font-size:.6rem; color:var(--faint); line-height:1.45; }
          .gd .pbrand .plogo{ width:2.6rem; height:1.2rem; border:1px dashed var(--line); border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:.46rem; color:var(--faint); margin-bottom:.25rem; }
          .gd .pbrand .qm{ text-align:right; font-size:.6rem; color:var(--soft); white-space:nowrap; }
          .gd .pbrand .qm b{ display:block; color:var(--ink); font-size:.76rem; }
          .gd .stv-acc{ display:none; }
          .gd .stage[data-step="7"] .stv-sent{ display:none; }
          .gd .stage[data-step="7"] .stv-acc{ display:inline; }
          .gd .pbody{ padding:.6rem .7rem; }
          .gd .psec{ display:none; }
          .gd .stage[data-step="5"] .pTop{ display:block; }
          .gd .stage[data-step="6"] .pAccept{ display:block; }
          .gd .stage[data-step="7"] .pDone{ display:block; }
          .gd .pfor{ border:1px solid var(--line-2); border-radius:7px; background:var(--panel); padding:.35rem .5rem; margin-bottom:.5rem; }
          .gd .pfor .lbl{ font-size:.55rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .pfor .nm{ font-weight:700; color:var(--ink); font-size:.72rem; }
          .gd .pfor .ad{ font-size:.62rem; color:var(--soft); line-height:1.4; }
          .gd .ptbl{ width:100%; border-collapse:collapse; font-size:.64rem; }
          .gd .ptbl th{ text-align:left; font-size:.53rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.25rem .3rem; }
          .gd .ptbl td{ padding:.32rem .3rem; border-bottom:1px solid var(--line-2); color:var(--soft); vertical-align:top; }
          .gd .ptbl .r{ text-align:right; white-space:nowrap; color:var(--ink); }
          .gd .ptbl .room{ font-weight:700; color:var(--ink); }
          .gd .ptbl .size{ color:var(--ink); }
          .gd .ptbl .exs2{ color:var(--faint); }
          .gd .pfoot{ margin-top:.3rem; font-size:.66rem; color:var(--soft); }
          .gd .pfoot .fr{ display:flex; justify-content:flex-end; gap:.9rem; padding:.1rem 0; }
          .gd .pfoot .fr b{ color:var(--ink); }
          .gd .pfoot .fr.grand{ border-top:1px solid var(--line); margin-top:.15rem; padding-top:.25rem; font-size:.78rem; font-weight:700; color:var(--ink); }
          .gd .pnotes{ margin-top:.5rem; background:#fffbeb; border:1px solid #fde68a; border-radius:7px; padding:.35rem .55rem; font-size:.64rem; color:#78350f; }
          .gd .pnotes b{ color:#78350f; display:block; }
          :root[data-theme="dark"] .gd .pnotes{ background:#3a2f05; border-color:#5b4708; color:#fde68a; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .pnotes{ background:#3a2f05; border-color:#5b4708; color:#fde68a; } }
          .gd .pdep{ margin-top:.5rem; background:#fef9c3; color:#854d0e; border-radius:7px; padding:.4rem .6rem; font-size:.66rem; }
          .gd .pdep b{ color:#854d0e; }
          :root[data-theme="dark"] .gd .pdep, :root[data-theme="dark"] .gd .pdep b{ background:#3a2f05; color:#fde68a; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .pdep, :root:not([data-theme="light"]) .gd .pdep b{ background:#3a2f05; color:#fde68a; } }
          .gd .pbank{ margin-top:.5rem; border:1px solid var(--line); border-radius:7px; background:var(--panel); padding:.4rem .6rem; font-size:.64rem; color:var(--soft); line-height:1.6; }
          .gd .pbank .bh{ display:block; font-weight:700; color:var(--ink); margin-bottom:.15rem; font-size:.68rem; }
          .gd .pbank b{ color:var(--ink); }

          /* accept card */
          .gd .acard{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--surface); }
          .gd .acch{ font-weight:700; color:var(--ink); font-size:.78rem; margin-bottom:.2rem; }
          .gd .accsub{ font-size:.65rem; color:var(--faint); margin-bottom:.45rem; line-height:1.5; }
          .gd .agree{ display:flex; align-items:flex-start; gap:.4rem; font-size:.67rem; color:var(--soft); margin:.5rem 0 .2rem; line-height:1.5; }
          .gd .agree .tick{ flex:0 0 auto; margin-top:.05rem; }
          .gd .agree .lk{ color:var(--accent); text-decoration:underline; }
          .gd .accbtns{ display:flex; gap:.5rem; margin-top:.5rem; }
          .gd .accbtn{ display:inline-flex; background:#16a34a; color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .decbtn{ display:inline-flex; background:var(--surface); border:1px solid var(--err); color:var(--err); border-radius:7px; padding:.34rem .7rem; font-size:.74rem; font-weight:600; }
          .gd .tcerr{ color:var(--err); font-size:.66rem; font-weight:700; margin-top:.4rem; opacity:0; }
          .gd .stage[data-step="6"] .tcerr{ animation:gdFadeIn .3s ease-out 1.4s both; }
          .gd .stage[data-step="6"] .tctick{ animation:gdTickOn .25s ease-out 6s both; }
          @keyframes gdFadeIn{ from{ opacity:0; } to{ opacity:1; } }
          @keyframes gdTickOn{ from{ background:var(--surface); color:transparent; } to{ background:var(--accent); color:#fff; } }
          @media (prefers-reduced-motion:reduce){
            .gd .stage[data-step="6"] .tcerr{ animation:none; opacity:1; }
            .gd .stage[data-step="6"] .tctick{ animation:none; background:var(--accent); color:#fff; }
          }
          .gd .confbox{ margin-top:.55rem; border:1px solid var(--line); border-radius:8px; background:var(--panel); padding:.4rem .55rem; max-width:19rem; box-shadow:var(--gd-shadow); }
          .gd .confbox .ct{ font-size:.55rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .confbox .cq{ font-size:.68rem; color:var(--ink); margin:.15rem 0 .3rem; }
          .gd .confbox .cb{ display:inline-flex; font-size:.62rem; border-radius:6px; padding:.14rem .5rem; margin-right:.3rem; }
          .gd .confbox .cb.no{ border:1px solid var(--line); color:var(--soft); }
          .gd .confbox .cb.yes{ background:var(--err); color:#fff; font-weight:700; }

          /* accepted + thank-you */
          .gd .acceptedcard{ margin-top:.5rem; background:#d1fae5; color:#065f46; border-radius:9px; padding:.55rem .7rem; font-size:.7rem; line-height:1.5; }
          .gd .acceptedcard b{ color:#065f46; display:block; font-size:.8rem; margin-bottom:.1rem; }
          .gd .tycard{ margin-top:.6rem; border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:24rem; }
          .gd .tycard .th{ background:var(--panel); border-bottom:1px solid var(--line); padding:.35rem .6rem; font-size:.66rem; font-weight:700; color:var(--ink); }
          .gd .tycard .tb{ padding:.45rem .6rem; font-size:.66rem; color:var(--soft); line-height:1.6; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }

          /* --- 8: back on your side --- */
          .gd .pgh{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .pgs{ font-size:.68rem; color:var(--faint); margin:.1rem 0 .55rem; }
          .gd .chips{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin-bottom:.5rem; }
          .gd .chip{ border:1px solid var(--line); border-radius:999px; padding:.14rem .55rem; font-size:.63rem; color:var(--soft); }
          .gd .chip.on{ background:var(--nav); border-color:var(--nav); color:#fff; font-weight:700; }
          .gd .chip.arch{ margin-left:auto; color:var(--faint); border-style:dashed; }
          .gd .srch{ height:26px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.66rem; color:var(--faint); margin-bottom:.5rem; max-width:20rem; }
          .gd .otbl{ width:100%; border-collapse:collapse; font-size:.64rem; }
          .gd .otbl th{ text-align:left; font-size:.52rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.22rem .3rem; }
          .gd .otbl td{ padding:.3rem .3rem; border-bottom:1px solid var(--line-2); color:var(--soft); white-space:nowrap; }
          .gd .otbl td b{ color:var(--ink); }
          .gd .spill{ display:inline-block; font-size:.58rem; font-weight:700; border-radius:999px; padding:.08rem .5rem; background:#dbeafe; color:#1e40af; text-transform:capitalize; }
          .gd .dep-due{ color:#92400e; font-weight:700; }
          .gd .notsent{ display:inline-block; font-size:.54rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:999px; padding:.02rem .4rem; margin-left:.2rem; }
          .gd .ycards{ display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .ycard{ flex:1 1 13rem; border:1px solid var(--line); border-radius:9px; background:var(--panel); padding:.45rem .6rem; font-size:.64rem; color:var(--soft); line-height:1.55; }
          .gd .ycard .yt{ display:block; font-weight:700; color:var(--ink); font-size:.68rem; margin-bottom:.15rem; }
          .gd .ycard.tray{ border-style:dashed; }
          .gd .ynote{ font-size:.64rem; color:var(--faint); margin:.6rem 0 0; line-height:1.55; }
          .gd .ynote b{ color:var(--ink); }
          @media(max-width:620px){ .gd .ycards{ flex-direction:column; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote-builder / edit</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a>Pipeline</a><a>Factory</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a class="on">Quotes</a><a>Orders</a><a>Payments</a>
                <div class="navh">Trade</div>
                <a>Trade accounts</a><a>Quotes</a><a>Orders</a><a>Invoices</a><a>Statements</a><a>Commissions</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <div class="navh">Platform <span class="chev">&#9662;</span></div>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ===== Scenes 1-3: the Send to customer panel ===== -->
                <div class="osc scSend">
                  <div class="card-t">Send to customer</div>
                  <p class="ldesc2">Email the PDF and a link the customer can click to accept the quote online.
                     Or share the same link via WhatsApp.</p>

                  <div class="fld"><label>Recipient email</label>
                    <div class="boxv emailfld"><span class="em-v">emma.fletcher@gmail.com</span></div></div>

                  <div class="fld" style="margin-top:.55rem"><label>Message (optional)</label>
                    <div class="ta f2" style="min-height:32px">
                      <span class="ph">Optional &mdash; anything to add above the standard text.</span>
                      <span class="val">Hi Emma &mdash; prices held to the end of the month.</span>
                    </div></div>

                  <div class="sbtns">
                    <span class="sbtn email">&#128231; Email PDF + accept link</span>
                    <span class="sbtn wa">&#128172; Send via WhatsApp</span>
                    <span class="sbtn copy"><span class="clbl">&#128279; Copy public link</span><em class="cflash">&check; Link copied!</em></span>
                  </div>
                  <p class="plink">Public link: <code>https://yourblinds.uk/quote-history/public.php?token=&hellip;</code></p>

                  <div class="okwrap">
                    <div class="okbanner"><b>&check;</b> Quote PDF emailed to emma.fletcher@gmail.com.</div>
                  </div>

                  <div class="slip3">
                    <div class="errbanner"><span>&#9888;</span><div><b>Please provide a valid recipient email address.</b>
                      &mdash; the box is empty, so the red button sends you straight back here.</div></div>
                    <div class="heads"><span class="hi">&#9888;</span><div><b>Could not send the email. Check SMTP credentials
                      in .env and the PHP error log.</b> &mdash; the quote is fine; your email settings aren&rsquo;t.</div></div>
                  </div>
                </div>

                <!-- ===== Scene 4: the plain-text email ===== -->
                <div class="osc scEmail">
                  <div class="card-t">What lands in their inbox</div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Your quote PRE-2026-0042 from Beverley Blinds</div>
                      <div class="frm">from Beverley Blinds &middot; to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Hello Emma,<br><br>
                      Please find your quote (PRE-2026-0042) attached as a PDF.<br><br>
                      <span class="yours">Hi Emma &mdash; prices held to the end of the month.</span><br><br>
                      You can also view it online and accept it here:<br>
                      <span class="elink">https://yourblinds.uk/quote-history/public.php?token=8f3c&hellip;</span><br><br>
                      If you have any questions please reply to this email.<br><br>
                      Kind regards,<br>Beverley Blinds
                    </div>
                    <div class="efoot"><span class="eatt">&#128206; PRE-2026-0042.pdf</span></div>
                  </div>
                  <p class="enote">Plain text &mdash; <b>there is no button to look for.</b> The link on its own line
                     <em>is</em> the link. And the last line tells them to reply to the email, so their reply lands with you.</p>
                </div>

                <!-- ===== Scenes 5-7: the public page ===== -->
                <div class="osc scPublic">
                  <div class="pubpage">
                    <div class="pbrand">
                      <div>
                        <div class="plogo">LOGO</div>
                        <div class="co">Beverley Blinds</div>
                        <div class="ad">Unit 4, Sample Way<br>Kenilworth CV8 1AA<br>Warwickshire<br>02476 644 684<br>hello@beverleyblinds.example</div>
                      </div>
                      <div class="qm"><b>Quote PRE-2026-0042</b>
                        Date 31 August 2026<br>
                        Status <span class="stv-sent">Sent</span><span class="stv-acc">Accepted</span></div>
                    </div>
                    <div class="pbody">

                      <!-- step 5: the whole quote -->
                      <div class="psec pTop">
                        <div class="pfor"><div class="lbl">Quote for</div>
                          <div class="nm">Emma Fletcher</div>
                          <div class="ad">14 Willow Drive<br>Leamington Spa CV32 5PQ<br>Warwickshire</div></div>
                        <table class="ptbl">
                          <thead><tr><th style="width:1.4rem">#</th><th>Description</th><th style="width:1.6rem">Qty</th><th class="r" style="width:3rem">Unit</th><th class="r" style="width:3.4rem">Total</th></tr></thead>
                          <tbody>
                            <tr><td>1</td>
                              <td><span class="room">Living Room</span><br>Roller Blind &mdash; Bev Roller Blinds<br>
                                  Louvolite / Sunset / White<br><span class="size">1200 &times; 1800 mm</span><br>
                                  <span class="exs2">+ Chain colour: White</span></td>
                              <td>1</td><td class="r">&pound;45.00</td><td class="r">&pound;45.00</td></tr>
                            <tr><td>2</td>
                              <td><span class="room">Kitchen</span><br>Roller Blind &mdash; Bev Roller Blinds<br>
                                  Louvolite / Storm / Grey<br><span class="size">900 &times; 1200 mm</span><br>
                                  <span class="exs2">+ Chain colour: White<br>+ Chain length: Extended &mdash; 1500mm</span></td>
                              <td>1</td><td class="r">&pound;10.00</td><td class="r">&pound;10.00</td></tr>
                          </tbody>
                        </table>
                        <div class="pfoot">
                          <div class="fr">Subtotal <b>&pound;55.00</b></div>
                          <div class="fr">VAT (20%) <b>&pound;11.00</b></div>
                          <div class="fr grand">Total &pound;66.00</div>
                        </div>
                        <div class="pnotes"><b>Notes</b>Fitting to be arranged for the week beginning 14 September.</div>
                        <div class="pdep"><b>Deposit on acceptance:</b> &pound;33.00. The balance will be due on completion.</div>
                        <div class="pbank"><span class="bh">How to pay &mdash; bank transfer</span>
                          Account name: <b>Beverley Blinds Ltd</b><br>
                          Sort code: <b>04-00-72</b><br>
                          Account number: <b>12345678</b><br>
                          Payment clears in one working day.<br>
                          Please use <b>PRE-2026-0042</b> as your payment reference.</div>
                      </div>

                      <!-- step 6: the accept card -->
                      <div class="psec pAccept">
                        <div class="acard">
                          <div class="acch">Accept this quote</div>
                          <div class="accsub">Type your full name to confirm acceptance. We&rsquo;ll record it as your
                            digital sign-off and let Beverley Blinds know.</div>
                          <div class="fld"><label>Your full name</label><div class="boxv">Emma Fletcher</div></div>
                          <label class="agree"><span class="tick tctick">&check;</span>
                            <span>I agree to the <span class="lk">Terms &amp; Conditions</span> of Beverley Blinds.</span></label>
                          <div class="tcerr">Please tick the box to agree to the Terms &amp; Conditions.</div>
                          <div class="accbtns"><span class="accbtn">Accept quote</span><span class="decbtn">Decline</span></div>
                        </div>
                        <div class="confbox"><div class="ct">If they press Decline</div>
                          <div class="cq">Decline this quote? Your supplier will be notified.</div>
                          <span class="cb no">Cancel</span><span class="cb yes">OK</span></div>
                      </div>

                      <!-- step 7: accepted -->
                      <div class="psec pDone">
                        <div class="okbanner"><b>&check;</b> Quote accepted. Thanks!</div>
                        <div class="acceptedcard"><b>Quote accepted &check;</b>
                          Thanks Emma Fletcher! This quote was accepted on 31 August 2026. Beverley Blinds will be in touch.</div>
                        <div class="tycard">
                          <div class="th">Thank you for accepting quote PRE-2026-0042</div>
                          <div class="tb">Hello Emma,<br><br>Thank you for accepting your quote PRE-2026-0042 &mdash;
                            we really appreciate your business and are delighted to have you as a customer.&hellip;</div>
                        </div>
                      </div>

                    </div>
                  </div>
                </div>

                <!-- ===== Scene 8: back on your side ===== -->
                <div class="osc scYours">
                  <div class="pgh">Orders</div>
                  <div class="pgs">Accepted onward &mdash; orders, invoices and paid jobs.</div>
                  <div class="chips">
                    <span class="chip on">All (12)</span><span class="chip">Accepted (3)</span><span class="chip">Ordered (5)</span>
                    <span class="chip arch">&#128451; Archived (2)</span>
                  </div>
                  <div class="srch">Search by quote #, customer name, or postcode&hellip;</div>
                  <table class="otbl">
                    <thead><tr><th>Quote #</th><th>Customer</th><th>Postcode</th><th>Status</th><th>Created</th><th>Total</th><th>Deposit</th></tr></thead>
                    <tbody>
                      <tr><td><b>PRE-2026-0042</b></td><td>Emma Fletcher</td><td>CV32 5PQ</td>
                          <td><span class="spill">Ordered</span></td><td>31 Aug 2026</td><td><b>&pound;66.00</b></td>
                          <td><span class="dep-due">&pound;33.00 due</span></td></tr>
                    </tbody>
                  </table>
                  <div class="ycards">
                    <div class="ycard"><span class="yt">&#128231; New order &mdash; PRE-2026-0042 accepted (Emma Fletcher)</span>
                      Good news &mdash; a new order has come in.<br>Order total: &pound;66.00.</div>
                    <div class="ycard tray"><span class="yt">&#128197; Pending Fitting</span>
                      Install: PRE-2026-0042 &mdash; Emma Fletcher &mdash; waiting in the calendar tray to drag onto a date.</div>
                    <div class="ycard"><span class="yt">&#127981; Straight to the workshop</span>
                      Every blind is one you make, so it skipped <em>Accepted</em> and went to <b>Ordered</b>.</div>
                  </div>
                  <p class="ynote">On <b>Retail &rarr; Quotes</b> a quote you haven&rsquo;t sent yet shows as
                    <span class="spill">Quote</span><span class="notsent">Not sent</span> &mdash; and that amber badge
                    disappearing is how you see it has gone out.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Send to customer &mdash; email already filled in.</b>
                  <b class="c2 good"><span class="n">2</span> Your message, then the red button &mdash; emailed.</b>
                  <b class="c3 err"><span class="n">3</span> No email? WhatsApp it, or copy the link.</b>
                  <b class="c4"><span class="n">4</span> A plain email, the PDF, and the bare link.</b>
                  <b class="c5"><span class="n">5</span> Their page: rooms, sizes, extras, VAT, bank details.</b>
                  <b class="c6"><span class="n">6</span> Name&rsquo;s already there &mdash; they tick, then Accept.</b>
                  <b class="c7 good"><span class="n">7</span> Quote accepted &mdash; and a thank-you goes out.</b>
                  <b class="c8 good"><span class="n">8</span> Your side: alert email, deposit, fitting waiting.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where you send from.</b> Not from the Quotes list &mdash; from <em>inside</em> the quote. Open the quote and
             scroll down past the blinds to the panel headed <b>Send to customer</b>. The list is where you <em>watch</em> it
             afterwards. There are only two boxes and three buttons here, so it looks simpler than it is.</p>

          <ul class="steps">
            <li><b>Recipient email</b> &mdash; already filled in from the customer&rsquo;s record. Glance at it and make sure
                it&rsquo;s the address they actually use. You can type over it &mdash; changing it here doesn&rsquo;t change
                their customer record.</li>
            <li><b>Message (optional)</b> &mdash; one line to start with, and it grows as you type. The faint grey wording in
                it says &ldquo;<em>Optional &mdash; anything to add above the standard text.</em>&rdquo; Whatever you put here
                is dropped into the email <b>underneath</b> the &ldquo;attached as a PDF&rdquo; line, in your own words.
                Leave it empty and the email simply doesn&rsquo;t have that paragraph.</li>
            <li><b>&#128231; Email PDF + accept link</b> &mdash; the big red button. It builds the quote PDF, attaches it,
                and emails it with a link the customer can click to accept online. The page comes back with a green bar:
                <b>&ldquo;Quote PDF emailed to emma.fletcher@gmail.com.&rdquo;</b> That green bar is your only confirmation,
                so read it before you close the page. Sending also flips a <b>draft</b> quote to <b>sent</b> and stamps the date.</li>
            <li><b>&#128172; Send via WhatsApp</b> &mdash; the green button. Opens WhatsApp with the same link and a short
                message. <b>It isn&rsquo;t always there</b> &mdash; if you can&rsquo;t see a green button, WhatsApp isn&rsquo;t
                switched on for this customer (see below).</li>
            <li><b>&#128279; Copy public link</b> &mdash; the small grey button. Copies the link so you can paste it into a
                text, another email, anything. It flashes <b>&ldquo;&check; Link copied!&rdquo;</b> for about two seconds and
                then goes back to normal. If your browser blocks copying, the whole link is printed in grey underneath
                (<b>Public link:</b> &hellip;) so you can select it by hand.</li>
          </ul>

          <div class="oops"><b>Three things that can go wrong when you press the red button.</b>
             <b>&ldquo;Please provide a valid recipient email address.&rdquo;</b> &mdash; the box is empty or the address is
             mistyped. The form doesn&rsquo;t stop you before you press; it lets you press, then comes back with that red bar.
             <b>&ldquo;Could not send the email. Check SMTP credentials in .env and the PHP error log.&rdquo;</b> &mdash; this
             is the one a real business hits. Nothing is wrong with your quote; the app&rsquo;s email settings need looking at.
             Copy the link and send it another way in the meantime. And <b>&ldquo;Could not render the quote PDF.&rdquo;</b> or
             <b>&ldquo;PDF generator not installed&hellip;&rdquo;</b> &mdash; both mean the PDF, not the quote. In every case
             nothing has been sent and the quote hasn&rsquo;t moved.</div>

          <p><b>The WhatsApp button, and why it&rsquo;s sometimes missing.</b> WhatsApp uses the customer&rsquo;s
             <b>mobile</b> number (it falls back to the landline if there&rsquo;s no mobile), and the button only appears once
             you&rsquo;ve also ticked <b>&ldquo;Mobile is on WhatsApp&rdquo;</b> in the customer details higher up the same
             page. The grey line at the top of the panel always tells you which bit is missing, in its own words:</p>
          <ul class="steps">
            <li>&ldquo;<em>Or share the same link via WhatsApp.</em>&rdquo; &mdash; all good, the green button is there.</li>
            <li>&ldquo;<em>Add a phone number to the customer details above to enable WhatsApp sharing.</em>&rdquo; &mdash; you
                have no number at all for them.</li>
            <li>&ldquo;<em>Tick &ldquo;Customer has WhatsApp on this number&rdquo; above to enable WhatsApp sharing.</em>&rdquo;
                &mdash; you have the number, you just haven&rsquo;t confirmed it&rsquo;s a WhatsApp one.</li>
            <li>&ldquo;<em>Add a mobile number to the trade account to enable WhatsApp sharing.</em>&rdquo; &mdash; this is a
                <b>trade-account</b> order, so it uses the account&rsquo;s own mobile number. A trade account needs <b>no tick</b>
                &mdash; the mobile on the account is enough.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>A copied link sends the quote all by itself.</b> The very
             first time anybody opens the public link, a quote still sitting in <b>draft</b> is quietly flipped to <b>sent</b>
             and the date stamped &mdash; because you&rsquo;ve plainly given the link out. So don&rsquo;t paste the link
             anywhere until you&rsquo;re happy with the quote.</div></div>

          <p><b>What lands in their inbox.</b> A plain-text email &mdash; no colours, no buttons. Subject:
             <em>&ldquo;Your quote PRE-2026-0042 from Beverley Blinds&rdquo;</em>. Then &ldquo;Hello Emma,&rdquo;, &ldquo;Please
             find your quote (PRE-2026-0042) attached as a PDF.&rdquo;, your optional message, &ldquo;You can also view it online
             and accept it here:&rdquo; followed by <b>the link on its own line</b>, then
             <em>&ldquo;If you have any questions please reply to this email.&rdquo;</em> and your company name. The attachment
             is named after the quote number. Worth knowing both ends: there is <b>no fancy accept button</b> in the email for a
             customer to hunt for, and a reply comes straight back to you.</p>

          <p><b>What they see when they click.</b> No login, no password &mdash; the long code in the link is the key. Top to
             bottom the page carries: your <b>logo</b>, company name, address, phone and email; the <b>quote number</b>, the
             <b>date</b> and the <b>status</b>; a <b>&ldquo;Quote for&rdquo;</b> panel with their name and address; then the
             items table &mdash; <b>#, Description, Qty, Unit, Total</b> &mdash; with, for every blind, the <b>room name</b> in
             bold, the product and system, the <b>fabric supplier / fabric / colour</b>, the <b>size</b>, and each <b>extra</b>
             listed underneath as &ldquo;+ Chain colour: White&rdquo;. Under the table: <b>Subtotal</b> and <b>VAT (20%)</b>
             (only if you charge VAT), then the <b>Total</b>. Then your <b>Notes</b> if the quote has any, the <b>deposit</b>
             card, and a <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> box with your <b>account name</b>, <b>sort
             code</b>, <b>account number</b>, your own payment wording, and &ldquo;<em>Please use PRE-2026-0042 as your payment
             reference.</em>&rdquo; On a phone the little <b>#</b> column is dropped so the description gets the room.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Sizes are shown by default now.</b> Two tick-boxes in
             <b>Settings &rarr; Quoting</b> control this &mdash; <b>&ldquo;Prices on the customer quote&rdquo;</b> and
             <b>&ldquo;Sizes on the customer quote&rdquo;</b> &mdash; and <b>both start ticked</b>. So unless you have turned
             it off, your customer can read the millimetres of every blind (&ldquo;1200 &times; 1800 mm&rdquo;) as well as the
             price of each one. Untick sizes for a retail job if you&rsquo;d rather they didn&rsquo;t.
             The <b>Wally tax (WT charge)</b> is different: it is spread quietly across the line prices and <b>never</b> appears
             as its own line, or by name, anywhere the customer can see. If you&rsquo;ve agreed a round price for the job, the
             difference shows as a plain <b>&ldquo;Discount&rdquo;</b> row (or &ldquo;Price adjustment&rdquo; if it went up) so
             the sums still add up in front of them.</div></div>

          <ul class="steps">
            <li><b>They accept.</b> At the bottom, <b>&ldquo;Accept this quote&rdquo;</b>. Their <b>full name is already in the
                box</b> &mdash; they can change it. If you have Terms &amp; Conditions (everyone does by default) there&rsquo;s
                an <b>unticked</b> box: &ldquo;I agree to the <b>Terms &amp; Conditions</b> of &lt;your company&gt;.&rdquo;
                Forgetting that tick is the commonest reason an accept bounces &mdash; the page stops them with
                <em>&ldquo;Please tick the box to agree to the Terms &amp; Conditions.&rdquo;</em> The blue
                <b>Terms &amp; Conditions</b> words open your terms and privacy policy on <b>their own tab</b>, headed with your
                company name and with <b>&ldquo;&larr; Back to your quote&rdquo;</b> at the top, so nobody loses the quote
                reading them. A centred link at the very bottom of the quote does the same &mdash; labelled
                <b>Terms &amp; Conditions &amp; Privacy Policy</b>, or just one of the two, depending on what you&rsquo;ve
                published.</li>
            <li><b>Or they decline.</b> The white <b>Decline</b> button asks first &mdash;
                <em>&ldquo;Decline this quote? Your supplier will be notified.&rdquo;</em> If they say OK the quote goes to
                <b>Declined</b> and the pending fitting is quietly taken back off your calendar.</li>
            <li><b>The sign-off.</b> On a yes the app stores the <b>name they typed</b>, the <b>date</b> and their
                <b>IP address</b>, and shows them <b>&ldquo;Quote accepted &check;&rdquo;</b> with their name on it.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Their Yes sets off four things at once.</b>
             <b>One:</b> you get an email &mdash; <em>&ldquo;New order &mdash; PRE-2026-0042 accepted (Emma Fletcher)&rdquo;</em>
             with the order total and a link &mdash; <b>as long as you have put an address in</b>
             <b>Settings &rarr; New order alerts &rarr; &ldquo;Email me when a customer accepts a quote online&rdquo;</b>.
             Leave that blank and no alert is sent. It only fires for acceptances made <em>online</em>, not for ones you record
             yourself. <b>Two:</b> the <b>deposit</b> is worked out from your default and shows as due.
             <b>Three:</b> an appointment called <b>&ldquo;Install: &lt;quote number&gt; &mdash; &lt;customer&gt;&rdquo;</b>
             drops into your calendar&rsquo;s <b>Pending Fitting</b> tray with no date on it, ready to drag onto the right day.
             <b>Four, and the surprising one:</b> if <b>every</b> blind on the job is one you make yourself, with no outside
             supplier, the quote <b>skips Accepted altogether and goes straight to Ordered</b> &mdash; due dates stamped and the
             workshop emailed. So the quote you expected to read &ldquo;Accepted&rdquo; may well read
             <b>&ldquo;Ordered&rdquo;</b> by the time you look. That&rsquo;s the app being helpful, not a fault.</div></div>

          <p><b>The thank-you email is yours to change.</b> If the customer has an email address, a thank-you goes out the
             moment they accept &mdash; subject <em>&ldquo;Thank you for accepting quote &lt;number&gt;&rdquo;</em>, starting
             &ldquo;Hello Emma, Thank you for accepting your quote &hellip;&rdquo;. It is a template you can edit in
             <b>Settings</b>, under <b>&ldquo;Thank-you email (sent when a customer accepts a quote)&rdquo;</b>, using
             <code>{{customer_name}}</code>, <code>{{company_name}}</code>, <code>{{quote_number}}</code> and
             <code>{{quote_link}}</code>. <b>&ldquo;Leave empty to send no thank-you email.&rdquo;</b></p>

          <p><b>Where to watch it.</b> <b>Retail &rarr; Quotes</b> (or <b>Trade &rarr; Quotes</b> for an account job) lists
             everything still in the pipeline &mdash; &ldquo;<em>Quotes still in the pipeline &mdash; drafts, sent, and
             declined.</em>&rdquo; Drafts and sent quotes share <b>one pill reading &ldquo;Quote&rdquo;</b>; a draft carries an
             extra amber <b>Not sent</b> badge (hover it and it says &ldquo;<em>This quote hasn&rsquo;t been sent to the
             customer yet</em>&rdquo;). <b>That badge disappearing is how you see the quote has gone out.</b> Once accepted it
             moves over to <b>Orders</b> &mdash; &ldquo;<em>Accepted onward &mdash; orders, invoices and paid jobs.</em>&rdquo;
             &mdash; where the pill reads Accepted or Ordered and a <b>Deposit</b> column shows
             &ldquo;&pound;33.00 due&rdquo; in amber or &ldquo;&check; &pound;33.00 paid&rdquo; in green.</p>

          <p><b>Saying yes on their behalf.</b> Plenty of customers say yes on the phone or at the door. You don&rsquo;t have
             to make them use the link. In the bar at the very top of the quote there are two one-click buttons:
             <b>&ldquo;&check; Customer accepted&rdquo;</b> and <b>&ldquo;&#10005; Customer declined&rdquo;</b> (the decline one
             asks &ldquo;<em>Mark this quote as declined?</em>&rdquo; first). Or, in <b>Quote actions</b> just below,
             <b>&ldquo;&#128230; Save as order&rdquo;</b> accepts it <em>and</em> places it in one go. Same result &mdash;
             except the New-order alert email only fires for an acceptance made online.</p>

          <p><b>If they come back to the link later.</b> Nothing breaks. Once the job has moved on they see
             <b>&ldquo;Quote in progress &mdash; This quote has moved on to ordered.&rdquo;</b> instead of the accept box, and
             if they refresh the page straight after accepting they get
             <b>&ldquo;This quote is no longer awaiting your response.&rdquo;</b> &mdash; which is simply the app refusing to
             accept the same quote twice.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Accept links never expire.</b> A sent quote can still be
             accepted weeks later, <b>at the old prices</b>. The standard terms say a quotation is valid for 30 days, but
             nothing in the app enforces that. So if a quote has gone stale, use <b>Reopen as draft</b> and re-price it (or
             start a fresh one) rather than leaving an old link live for somebody to click.</div></div>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'The Send to customer panel.',            'Scroll down the quote and you\'ll find Send to customer. The email address is already filled in from the customer\'s record — check it\'s the one they actually use. Underneath is a message box you can leave alone, and three ways to get the quote to them.', 1],
            ['0:15', 'Message typed; red button; green bar.',  'Anything you type in the message box goes into the email above the standard wording. Then press the red Email PDF plus accept link button. It sends the quote as a PDF attachment and a link they can click to accept, and the green bar comes back saying: Quote PDF emailed to emma dot fletcher at gmail dot com. Sending is also what turns a draft into a sent quote.', 2],
            ['0:34', 'The two slips; WhatsApp and Copy.',      'Two things can trip you up. No email address, and the red button won\'t go — you\'ll get: please provide a valid recipient email address. And if the email won\'t send at all, you\'ll see a message about SMTP credentials — that\'s your settings, not your quote. Either way, use Send via WhatsApp, or Copy public link and paste it into a text. Both send the very same link, and the link needs no password.', 3],
            ['0:56', 'The plain-text email they receive.',     'This is what arrives. A plain email, the quote attached as a PDF, your message in the middle, and the link on its own line. There\'s no fancy button to look for — the link is the link. And it ends by asking them to reply to the email, so if they do, the reply comes back to you.', 4],
            ['1:12', 'The public page, top to bottom.',        'The link opens this. Your logo, your address, the quote number and the date. Every blind with its room, its fabric and colour, its size, any extras, and the price. Then the subtotal, the V-A-T, and the total. The deposit they\'ll owe when they say yes. And your bank details, with the quote number as the payment reference.', 5],
            ['1:32', 'The Accept box and the terms tick.',     'Down at the bottom, Accept this quote. Their name is already in the box. They tick to agree to your terms — and if they forget, the page stops them and says: please tick the box to agree to the Terms and Conditions. The Terms and Conditions words open your terms on their own page, so they can read them without losing the quote. Then, Accept quote. If they press Decline instead, it asks them to confirm first.', 6],
            ['1:56', 'Accepted; the thank-you email.',         'That\'s it done. They see Quote accepted, with their name and the date. And if you have their email address, a thank-you goes out to them straight away. You can change the wording of that email yourself in Settings, or empty the box to stop it being sent at all.', 7],
            ['2:12', 'Your side: alert, deposit, fitting.',    'And on your side, three things happen at once. You get an email — new order, quote accepted — as long as you\'ve put an address into New order alerts in Settings. The deposit is worked out from your default and shown as due. And an install lands in your calendar\'s Pending Fitting tray, ready to drag onto a date. One last thing: if every blind on the job is one you make yourself, it skips Accepted altogether and drops straight into the workshop as an order.', 8],
        ],
];
