<?php
declare(strict_types=1);

/**
 * Guide: settings-colours
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors Settings -> "Status colours" (admin/settings.php, _action=status_colours).
 * The 3 groups, 13 labels and 13 default hex values below are taken straight
 * from job_status_groups() / job_status_labels() / job_status_defaults() in
 * _partials/job_status_colours.php — keep them in step with that file.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Status colours',
        'eyebrow' => 'Settings · Status colours',
        'blurb'   => 'Your traffic-light colours — one colour per job stage, shown on the calendar, your orders list and the Pipeline.',
        'lede'    => 'Every job in YourBlinds wears a colour for the stage it has reached, and this is where you choose those
                      colours. Pick them once and the same colour follows the job everywhere &mdash; on the <b>calendar</b>, in your
                      <b>orders list</b> and across the <b>Pipeline</b> &mdash; changing itself as the job moves along. There are
                      <b>thirteen stages in three groups</b>, and you never have to recolour a job by hand.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* ---- the tab strip across the top of Settings ---- */
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.22rem; border-bottom:1px solid var(--line); margin-bottom:.75rem; padding-bottom:.3rem; }
          .gd .tb{ font-size:.67rem; color:var(--faint); padding:.2rem .42rem; border-radius:6px 6px 0 0; white-space:nowrap; }
          .gd .tb.on{ background:var(--accent-wash); color:var(--accent-ink); font-weight:700; box-shadow:inset 0 -2px 0 var(--accent); }
          /* after saving, the page comes back on the Company tab — the real redirect has no #colours */
          .gd .stage[data-step="8"] .tb-col{ background:none; color:var(--faint); font-weight:400; box-shadow:none; }
          .gd .stage[data-step="8"] .tb-com{ background:var(--accent-wash); color:var(--accent-ink); font-weight:700; box-shadow:inset 0 -2px 0 var(--accent); }

          /* ---- the real grey intro paragraph ---- */
          .gd .scintro{ font-size:.68rem; color:var(--faint); line-height:1.5; margin:0 0 .7rem; max-width:44rem; }

          /* ---- the group / card layout (mirrors the real flex row of bordered cards) ---- */
          .gd .scgrp{ margin-bottom:.55rem; border:2px solid transparent; border-radius:10px; padding:.15rem .3rem; transition:border-color .25s, background .25s; }
          .gd .scgrp-h{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.3rem 0 .35rem; }
          .gd .scards{ display:flex; flex-wrap:wrap; gap:.45rem; }
          .gd .scard{ display:flex; align-items:center; gap:.45rem; border:1px solid var(--line); border-radius:8px; padding:.32rem .42rem; background:var(--surface); transition:box-shadow .2s, border-color .2s; }
          /* the real control: a native <input type="color">, 2.25rem square, no border, pointer cursor */
          .gd .swatch{ width:36px; height:36px; border-radius:5px; border:none; flex:none; cursor:pointer; }
          .gd .pillc{ display:inline-block; padding:.06rem .5rem; border-radius:999px; font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#fff; white-space:nowrap; }
          .gd .pillc.dark{ color:#1f2937; }

          /* group rings, one per narrated step */
          .gd .stage[data-step="1"] .g-quote,
          .gd .stage[data-step="2"] .g-appt,
          .gd .stage[data-step="3"] .g-flag{ border-color:var(--accent); background:var(--accent-wash); }
          /* the two confusable cards, picked out on step 2 */
          .gd .stage[data-step="2"] .card-ab, .gd .stage[data-step="2"] .card-fb,
          .gd .stage[data-step="3"] .card-iss,
          .gd .stage[data-step="4"] .card-acc{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }

          /* Accepted turns violet from step 5 on, and stays */
          .gd .sw-acc, .gd .pc-acc{ background:#16a34a; }
          .gd .stage[data-step="5"] .sw-acc, .gd .stage[data-step="6"] .sw-acc,
          .gd .stage[data-step="7"] .sw-acc, .gd .stage[data-step="8"] .sw-acc,
          .gd .stage[data-step="5"] .pc-acc, .gd .stage[data-step="6"] .pc-acc,
          .gd .stage[data-step="7"] .pc-acc, .gd .stage[data-step="8"] .pc-acc{ background:#9333ea; }

          /* ---- your computer\'s own colour dialog (NOT part of YourBlinds) ---- */
          .gd .picker{ display:none; position:absolute; left:7.5rem; top:5.6rem; z-index:8; width:15.5rem;
                       background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); border-radius:9px;
                       box-shadow:0 18px 38px -12px rgba(20,30,45,.45); overflow:hidden; }
          .gd .stage[data-step="4"] .picker{ display:block; }
          .gd .pk-bar{ display:flex; align-items:center; justify-content:space-between; background:var(--panel); border-bottom:1px solid var(--line); padding:.28rem .5rem; font-size:.66rem; font-weight:700; color:var(--soft); }
          .gd .pk-bar span{ color:var(--faint); font-weight:400; }
          .gd .pk-body{ display:flex; gap:.5rem; padding:.5rem; }
          .gd .pk-sq{ width:6.6rem; height:4.6rem; border-radius:4px; flex:none;
                      background:linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, #9333ea); }
          .gd .pk-side{ flex:1; display:flex; flex-direction:column; gap:.3rem; }
          .gd .pk-hue{ height:.55rem; border-radius:999px; background:linear-gradient(to right,#f00,#ff0,#0f0,#0ff,#00f,#f0f,#f00); }
          .gd .pk-eye{ font-size:.6rem; color:var(--faint); }
          .gd .pk-hexrow{ display:flex; align-items:center; gap:.3rem; }
          .gd .pk-hexlbl{ font-size:.58rem; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; }
          .gd .pk-hex{ flex:1; border:1px solid var(--border-strong,#c7ccd4); border-radius:4px; padding:.12rem .3rem; font-size:.64rem;
                       font-family:ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--ink); background:var(--surface); }
          .gd .pk-btns{ display:flex; justify-content:flex-end; gap:.35rem; padding:.1rem .5rem .5rem; }
          .gd .pk-btn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:.14rem .55rem; font-size:.64rem; color:var(--soft); background:var(--panel); }
          .gd .pk-btn.go{ background:var(--accent); border-color:var(--accent); color:#fff; font-weight:700; }

          /* ---- black-or-white writing, shown on step 5 ---- */
          .gd .lumen{ display:none; align-items:center; flex-wrap:wrap; gap:.4rem; margin-top:.5rem; font-size:.66rem; color:var(--faint); }
          .gd .stage[data-step="5"] .lumen{ display:flex; }

          /* ---- scene swap: the form steps aside for the calendar / Pipeline scenes ---- */
          .gd .stage[data-step="6"] .formscene, .gd .stage[data-step="7"] .formscene{ display:none; }
          .gd .calscene, .gd .pipescene{ display:none; }
          .gd .stage[data-step="6"] .calscene, .gd .stage[data-step="7"] .pipescene{ display:block; }

          /* calendar strip */
          .gd .callegend{ display:flex; flex-wrap:wrap; gap:.3rem .7rem; border:1px solid var(--line); border-radius:8px; background:var(--panel); padding:.4rem .55rem; font-size:.6rem; color:var(--soft); }
          .gd .callegend span{ display:inline-flex; align-items:center; gap:.25rem; white-space:nowrap; }
          .gd .callegend i{ width:.6rem; height:.6rem; border-radius:3px; display:inline-block; }
          .gd .calrow{ display:flex; flex-wrap:wrap; gap:.45rem; margin-top:.6rem; }
          .gd .calchip{ border-radius:7px; padding:.4rem .55rem; font-size:.7rem; font-weight:600; min-width:9.5rem; }
          .gd .calchip small{ display:block; font-size:.58rem; font-weight:400; opacity:.85; }
          .gd .ch-acc{ background:#9333ea; color:#fff; }
          .gd .ch-fit{ background:#6366f1; color:#fff; outline:2px solid #111827; outline-offset:-2px; }
          .gd .ch-iss{ background:#0891b2; color:#fff; outline:2px solid #e11d48; outline-offset:-2px; }
          .gd .issbtn{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid #e11d48; color:#e11d48; border-radius:999px; padding:.1rem .5rem; font-size:.62rem; font-weight:700; margin-top:.55rem; }
          .gd .stage[data-step="3"] .issalone{ display:block; }
          .gd .issalone{ display:none; margin-top:.75rem; }

          /* orders rows + Pipeline board */
          .gd .ordrow{ display:flex; align-items:center; gap:.5rem; border-bottom:1px solid var(--line-2); padding:.3rem .1rem; font-size:.7rem; color:var(--soft); }
          .gd .ordrow b{ color:var(--ink); font-weight:600; min-width:7rem; }
          .gd .notsent{ font-size:.55rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:999px; padding:.04rem .38rem; }
          .gd .pipecols{ display:flex; gap:.3rem; margin-top:.8rem; overflow:hidden; }
          .gd .pipecol{ flex:1; min-width:0; border:1px solid var(--line); border-radius:7px; background:var(--panel); overflow:hidden; }
          .gd .pipecol .ph2{ font-size:.55rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#fff; padding:.2rem .25rem; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .pipecol .pb{ height:1.5rem; }
          .gd .pipenote{ font-size:.63rem; color:var(--faint); margin-top:.45rem; }

          /* ---- save + banner ---- */
          .gd .stage[data-step="8"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="8"] .toast{ opacity:1; transform:none; }

          /* ---- the defaults table in the written steps ---- */
          .gd .deft{ display:grid; grid-template-columns:repeat(auto-fill,minmax(12.5rem,1fr)); gap:.25rem .8rem; margin:.5rem 0 0; font-size:.82rem; color:var(--soft); }
          .gd .deft span{ display:flex; align-items:center; gap:.4rem; }
          .gd .deft i{ width:.8rem; height:.8rem; border-radius:3px; display:inline-block; flex:none; }
          .gd .deft code{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
          @media(max-width:620px){ .gd .pipecols{ flex-wrap:wrap; } .gd .pipecol{ flex:0 0 31%; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Status colours saved.</div>

                <div class="tabs">
                  <span class="tb tb-com">Company</span><span class="tb">Quoting</span><span class="tb">Legal</span>
                  <span class="tb on tb-col">Status colours</span><span class="tb">Suppliers</span>
                  <span class="tb">Accounting</span><span class="tb">Back up data</span>
                </div>

                <div class="formscene">
                  <div class="card-t">Status colours</div>
                  <p class="scintro">Your &ldquo;traffic-light&rdquo; colours. A job shows the same colour everywhere it appears &mdash; on the
                     <b>calendar</b> and in your <b>orders list</b> &mdash; and the calendar updates itself as the job moves from stage to
                     stage. Pick a colour for each stage below; the sample pill updates as you go.</p>

                  <div class="scgrp g-quote">
                    <div class="scgrp-h">Quote stages</div>
                    <div class="scards">
                      <div class="scard"><span class="swatch" style="background:#7c3aed"></span><span class="pillc" style="background:#7c3aed">Quote drafted</span></div>
                      <div class="scard"><span class="swatch" style="background:#f59e0b"></span><span class="pillc dark" style="background:#f59e0b">Quote sent</span></div>
                      <div class="scard card-acc"><span class="swatch sw-acc"></span><span class="pillc pc-acc">Accepted</span></div>
                      <div class="scard"><span class="swatch" style="background:#dc2626"></span><span class="pillc" style="background:#dc2626">Declined</span></div>
                      <div class="scard"><span class="swatch" style="background:#0891b2"></span><span class="pillc" style="background:#0891b2">Ordered</span></div>
                    </div>
                  </div>

                  <div class="scgrp g-appt">
                    <div class="scgrp-h">Appointments &amp; job</div>
                    <div class="scards">
                      <div class="scard card-ab"><span class="swatch" style="background:#2563eb"></span><span class="pillc" style="background:#2563eb">Appointment booked</span></div>
                      <div class="scard card-fb"><span class="swatch" style="background:#6366f1"></span><span class="pillc" style="background:#6366f1">Fitting booked</span></div>
                      <div class="scard"><span class="swatch" style="background:#0d9488"></span><span class="pillc" style="background:#0d9488">Fitted</span></div>
                      <div class="scard"><span class="swatch" style="background:#ea580c"></span><span class="pillc" style="background:#ea580c">Invoiced</span></div>
                      <div class="scard"><span class="swatch" style="background:#475569"></span><span class="pillc" style="background:#475569">Paid</span></div>
                      <div class="scard"><span class="swatch" style="background:#b91c1c"></span><span class="pillc" style="background:#b91c1c">Cancelled</span></div>
                      <div class="scard"><span class="swatch" style="background:#9ca3af"></span><span class="pillc dark" style="background:#9ca3af">No-show</span></div>
                    </div>
                  </div>

                  <div class="scgrp g-flag">
                    <div class="scgrp-h">Flags</div>
                    <div class="scards">
                      <div class="scard card-iss"><span class="swatch" style="background:#e11d48"></span><span class="pillc" style="background:#e11d48">Issue</span></div>
                    </div>
                    <div class="issalone">
                      <div class="calrow" style="margin-top:.25rem">
                        <div class="calchip ch-iss">09:30 &middot; Mr Dodds<small>Ordered &mdash; flagged</small></div>
                      </div>
                      <span class="issbtn">&#9888;&#65039; Issues (2)</span>
                    </div>
                  </div>

                  <div class="lumen">
                    Writing picks itself:
                    <span class="pillc dark" style="background:#fde68a">Pale &rarr; black writing</span>
                    <span class="pillc" style="background:#1e3a8a">Dark &rarr; white writing</span>
                  </div>

                  <div class="save">Save status colours</div>
                </div>

                <div class="picker">
                  <div class="pk-bar">Colour <span>&times;</span></div>
                  <div class="pk-body">
                    <div class="pk-sq"></div>
                    <div class="pk-side">
                      <div class="pk-hue"></div>
                      <div class="pk-eye">&#9673; Eyedropper</div>
                      <div class="pk-hexrow"><span class="pk-hexlbl">Hex</span><span class="pk-hex">#9333EA</span></div>
                    </div>
                  </div>
                  <div class="pk-btns"><span class="pk-btn">Cancel</span><span class="pk-btn go">OK</span></div>
                </div>

                <div class="calscene">
                  <div class="card-t">Calendar &mdash; September</div>
                  <div class="callegend">
                    <span><i style="background:#7c3aed"></i> Quote drafted</span>
                    <span><i style="background:#f59e0b"></i> Quote sent</span>
                    <span><i style="background:#9333ea"></i> Accepted</span>
                    <span><i style="background:#dc2626"></i> Declined</span>
                    <span><i style="background:#0891b2"></i> Ordered</span>
                    <span><i style="background:#2563eb"></i> Appointment booked</span>
                    <span><i style="background:#6366f1"></i> Fitting booked</span>
                    <span><i style="background:#0d9488"></i> Fitted</span>
                    <span><i style="background:#ea580c"></i> Invoiced</span>
                    <span><i style="background:#475569"></i> Paid</span>
                    <span><i style="background:#b91c1c"></i> Cancelled</span>
                    <span><i style="background:#9ca3af"></i> No-show</span>
                    <span><i style="background:transparent;outline:2px solid #111827;outline-offset:-2px"></i> = Fitting</span>
                    <span style="color:#e11d48;font-weight:700"><i style="background:transparent;outline:2px solid #e11d48;outline-offset:-2px"></i> &#9888;&#65039; Issues (2)</span>
                  </div>
                  <div class="calrow">
                    <div class="calchip ch-acc">10:00 &middot; Mrs Patel<small>Accepted</small></div>
                    <div class="calchip ch-acc">13:15 &middot; Mr Okafor<small>Accepted</small></div>
                    <div class="calchip ch-fit">15:00 &middot; Mrs Hale<small>Fitting booked</small></div>
                  </div>
                  <p class="pipenote">Fittings carry a dark outline; measures don&rsquo;t.</p>
                </div>

                <div class="pipescene">
                  <div class="card-t">Orders &amp; Pipeline</div>
                  <div class="ordrow"><b>#1042 Patel</b><span class="pillc" style="background:#9333ea">Accepted</span></div>
                  <div class="ordrow"><b>#1043 Nunn</b><span class="pillc dark" style="background:#f59e0b">Quote</span><span class="notsent">Not sent</span></div>
                  <div class="ordrow"><b>#1044 Hale</b><span class="pillc" style="background:#0891b2">Ordered</span></div>
                  <div class="pipecols">
                    <div class="pipecol"><div class="ph2" style="background:#f59e0b;color:#1f2937">Quote</div><div class="pb"></div></div>
                    <div class="pipecol"><div class="ph2" style="background:#dc2626">Declined</div><div class="pb"></div></div>
                    <div class="pipecol"><div class="ph2" style="background:#9333ea">Accepted</div><div class="pb"></div></div>
                    <div class="pipecol"><div class="ph2" style="background:#0891b2">Ordered</div><div class="pb"></div></div>
                    <div class="pipecol"><div class="ph2" style="background:#0d9488">Fitted</div><div class="pb"></div></div>
                    <div class="pipecol"><div class="ph2" style="background:#ea580c">Invoiced</div><div class="pb"></div></div>
                    <div class="pipecol"><div class="ph2" style="background:#475569">Paid</div><div class="pb"></div></div>
                  </div>
                  <p class="pipenote">The <b>Quote</b> column holds drafted <i>and</i> sent quotes, and uses the <b>Quote sent</b> colour.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Settings &rarr; Status colours &mdash; thirteen stages, three groups.</b>
                  <b class="c2"><span class="n">2</span> Appointment booked = the measure. Fitting booked = the install.</b>
                  <b class="c3"><span class="n">3</span> Issue isn&rsquo;t a stage &mdash; it&rsquo;s a warning ring.</b>
                  <b class="c4"><span class="n">4</span> Click the colour square &mdash; your computer&rsquo;s colour box opens.</b>
                  <b class="c5"><span class="n">5</span> The pill updates, and picks black or white writing itself.</b>
                  <b class="c6"><span class="n">6</span> The calendar and its key follow along.</b>
                  <b class="c7"><span class="n">7</span> So do your orders list and the Pipeline.</b>
                  <b class="c8 good"><span class="n">8</span> Saved &mdash; but you land back on Company.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar and click the <b>Status colours</b> tab &mdash; it is the <b>fourth</b> one along,
             after Company, Quoting and Legal. You will know you are in the right place by the grey line at the top:
             &ldquo;<em>Your &lsquo;traffic-light&rsquo; colours. A job shows the same colour everywhere it appears&hellip;</em>&rdquo;
             You choose these colours <b>once, for the whole company</b> &mdash; everyone who logs in sees your colours. Your
             <b>customer never sees them</b>; they are for you and your team.</p>

          <p class="prose"><b>The three groups &mdash; all thirteen stages</b></p>
          <ul class="steps">
            <li><b>Quote stages</b> &mdash; the life of a quote: <b>Quote drafted</b>, <b>Quote sent</b>, <b>Accepted</b>,
                <b>Declined</b>, <b>Ordered</b>.</li>
            <li><b>Appointments &amp; job</b> &mdash; the visits and the work that follows: <b>Appointment booked</b>,
                <b>Fitting booked</b>, <b>Fitted</b>, <b>Invoiced</b>, <b>Paid</b>, <b>Cancelled</b>, <b>No-show</b>.</li>
            <li><b>Flags</b> &mdash; just one: <b>Issue</b>.</li>
          </ul>

          <p class="prose"><b>The two that catch people out.</b> <b>Appointment booked</b> is the <b>measure</b> visit &mdash; you have
             been out to measure, or you are about to, and there is no quote yet. <b>Fitting booked</b> is the <b>install</b> visit,
             once the quote has been accepted or ordered. They are two different days, for two different jobs, so give them two
             clearly different colours.</p>
          <p class="prose">You never set either of them by hand. A calendar entry has no colour of its own &mdash; YourBlinds works it
             out for you: a <b>measure</b> visit borrows the stage of the quote it is attached to (no quote yet = Appointment booked,
             then drafted, sent, accepted, declined, ordered as the quote moves on); a <b>fitting</b> visit reads an accepted or
             ordered quote as <b>Fitting booked</b>, then follows the job through <b>Fitted</b>, <b>Invoiced</b> and <b>Paid</b>. And
             if you mark the appointment <b>Cancelled</b> or a <b>No-show</b>, that always wins, whatever the quote says.</p>

          <p class="prose"><b>Issue is not a stage &mdash; it is a warning.</b> Flagging a job does not repaint its card. It draws a
             <b>ring</b> round the card in your Issue colour with a <span class="req">&#9888;</span> mark, <b>on top of</b> whatever
             stage colour the job already has, and it colours the <b>&ldquo;&#9888;&#65039; Issues&rdquo;</b> button in the calendar&rsquo;s
             key &mdash; the button that filters the month down to flagged jobs only. Pick something loud that nothing else uses.</p>

          <p class="prose"><b>Changing a colour</b></p>
          <ul class="steps">
            <li><b>Click the little colour square</b> on the left of the card &mdash; the square one, not the pill. It is a proper
                control, noticeably bigger than the pill beside it.</li>
            <li><b>Your computer&rsquo;s own colour box opens.</b> This part is <b>not</b> YourBlinds, so it looks different on
                Windows, on a Mac and in different browsers. Every version of it lets you drag on a colour square or type a colour
                code such as <code>#9333ea</code>. Then choose <b>OK</b>.</li>
            <li><b>The sample pill changes straight away</b> so you can see what you have done. Work through as many stages as you
                like &mdash; <b>nothing is saved</b> until you press the button.</li>
            <li><b>Black or white writing sorts itself out.</b> YourBlinds measures how light the colour you picked is; anything
                bright gets <b>near-black</b> writing, anything dark gets <b>white</b>. That is why a pale yellow pill shows black
                letters and a navy one shows white. You never have to think about legibility.</li>
            <li><b>Press &ldquo;Save status colours&rdquo;</b> &mdash; the one button at the bottom of the form, and the only button
                on the whole tab.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>You land back on the Company tab.</b> You will see a green
             <b>&ldquo;Status colours saved.&rdquo;</b> banner at the top of the page &mdash; but the page reopens on the <b>Company</b>
             tab, not this one. Nothing has been lost. Click <b>Status colours</b> again if you want another look.</div></div>

          <p class="prose"><b>Where to check your work.</b> Open the <b>calendar</b> month view. The little key along the top is built
             from this very list, so it is the quickest place to see all your colours together. Fittings carry a dark outline there so
             you can tell a fitting from a measure at a glance. The same colours turn up as the status pills in your <b>orders list</b>,
             on the <b>Pipeline</b> board, and on the <b>Today&rsquo;s run</b> sheet.</p>

          <p class="prose"><b>One thing to know about the Pipeline.</b> The board has seven columns &mdash; <b>Quote</b>, <b>Declined</b>,
             <b>Accepted</b>, <b>Ordered</b>, <b>Fitted</b>, <b>Invoiced</b>, <b>Paid</b>. The <b>Quote</b> column holds drafted
             <em>and</em> sent quotes together, and it is drawn in your <b>Quote sent</b> colour. So changing <b>Quote drafted</b>
             will not change that column &mdash; nor the &ldquo;Quote&rdquo; pill in the quotes view of your orders list, which merges
             them the same way.</p>

          <p class="prose"><b>Putting a colour back.</b> There is no reset button, so if you want a standard colour back you type its
             code in again. YourBlinds only remembers the ones you actually <b>changed</b>, so anything you set back to its code goes
             back to being a standard colour. Here they all are:</p>
          <div class="deft">
            <span><i style="background:#7c3aed"></i> Quote drafted <code>#7c3aed</code></span>
            <span><i style="background:#f59e0b"></i> Quote sent <code>#f59e0b</code></span>
            <span><i style="background:#16a34a"></i> Accepted <code>#16a34a</code></span>
            <span><i style="background:#dc2626"></i> Declined <code>#dc2626</code></span>
            <span><i style="background:#0891b2"></i> Ordered <code>#0891b2</code></span>
            <span><i style="background:#2563eb"></i> Appointment booked <code>#2563eb</code></span>
            <span><i style="background:#6366f1"></i> Fitting booked <code>#6366f1</code></span>
            <span><i style="background:#0d9488"></i> Fitted <code>#0d9488</code></span>
            <span><i style="background:#ea580c"></i> Invoiced <code>#ea580c</code></span>
            <span><i style="background:#475569"></i> Paid <code>#475569</code></span>
            <span><i style="background:#b91c1c"></i> Cancelled <code>#b91c1c</code></span>
            <span><i style="background:#9ca3af"></i> No-show <code>#9ca3af</code></span>
            <span><i style="background:#e11d48"></i> Issue <code>#e11d48</code></span>
          </div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Two bits of friendly advice.</b> First, don&rsquo;t give two stages
             the <b>same colour</b> &mdash; nothing stops you doing it, and it quietly ruins the whole point of the traffic lights.
             Second, <b>go easy on very pale shades</b>: on a busy month view the cards are small, and a near-white card just reads as
             an empty square.</div></div>

          <p class="prose"><b>Where these colours don&rsquo;t reach.</b> Nothing your customer sees uses them &mdash; not a quote, not an
             invoice. Neither does the <b>factory floor</b> or your <b>production areas</b>, and nor do the <b>fulfilment</b> or
             <b>invoicing</b> badges; those all have their own look. Two smaller ones worth knowing: the amber <b>&ldquo;Not sent&rdquo;</b>
             mark beside a quote that hasn&rsquo;t gone out to the customer yet is fixed and isn&rsquo;t on this list; and
             <b>Appointment booked</b>, <b>Cancelled</b>, <b>No-show</b> and <b>Issue</b> only ever show on the <b>calendar</b>, never in
             the orders list &mdash; so don&rsquo;t go hunting for a recoloured No-show among your orders.</p>

          <div class="oops"><b>Nothing here can go wrong.</b> A colour box can only hold a real colour, so there is nothing to type
             incorrectly and nothing to validate. The only message you could ever see instead of the green one is a database
             complaint &mdash; <code>Could not save colours: &hellip; &mdash; have you run migrate_job_status_colours.php?</code> &mdash;
             which means the upgrade step hasn&rsquo;t been run yet. Show that one to whoever looks after your setup.</div>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'The Status colours tab; Quote stages ringed.',        'This is Settings, Status colours — the fourth tab along. Thirteen stages, in three groups. Quote stages is the life of a quote: drafted, sent, accepted, declined, ordered.', 1],
            ['0:13', 'Appointments & job ringed; the two visits picked out.', 'Appointments and job covers the visits and the work. Watch these two — Appointment booked is the measure visit, before there is a quote. Fitting booked is the install visit. They are different days, so give them different colours.', 2],
            ['0:28', 'Flags ringed; a flagged card shows the Issue ring.',   'Flags has just one — Issue. That is not a stage, it is a warning ring drawn round a job that has gone wrong, on top of whatever colour it already has. It also colours the Issues button on the calendar.', 3],
            ['0:42', 'Accepted ringed; the computer\'s colour box opens.',   'To change one, click its little colour square. Your computer\'s own colour box opens — it will look a bit different on every machine. Type a colour code or pick one, and choose OK.', 4],
            ['0:55', 'The Accepted swatch and pill turn violet.',           'The sample pill changes straight away so you can see what you have done. Notice the writing stays readable — YourBlinds works out whether black or white letters show up better, so pale colours get black writing and dark ones get white. You never have to think about it.', 5],
            ['1:10', 'Calendar: key plus cards in the new colour.',         'Now look at the calendar. Every job in that stage has turned violet by itself, and the little key along the top has changed with it. Fittings carry a dark outline, so you can tell a fitting from a measure at a glance.', 6],
            ['1:24', 'Orders list pills and the Pipeline columns.',         'It is the same in your orders list and on the Pipeline board. One thing to know — the Quote column holds drafted and sent quotes together, and it uses the Quote sent colour, so changing Quote drafted will not change that column.', 7],
            ['1:38', 'Save pressed; green banner, Company tab selected.',   'Press Save status colours. You will see Status colours saved in green — but the page comes back on the Company tab, so click Status colours again if you want another look. Nothing has been lost.', 8],
        ],
];
