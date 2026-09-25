<?php
declare(strict_types=1);

/**
 * Guide: settings-calendar
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the three forms of the Calendar section on the Company tab of
 * /admin/settings.php: calendar_money, map_provider and ampm_slots (the
 * configurable morning/afternoon windows — own times + own capacity each).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Calendar options',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Money on jobs, your map app, and morning/afternoon booking windows with your own times and limits.',
        'lede'    => 'The last section on the <b>Company</b> tab. Three separate little settings that change how your
                      diary behaves &mdash; whether each job shows its <b>money</b>, which <b>map app</b> your address
                      links open in, and whether measure visits are booked as a <b>morning or afternoon window</b> with
                      your own hours and your own daily limit. Each one has its <b>own Save button</b>.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* the three forms, drawn the way the real section is: one under the
             other, each divided by a rule, each ending in its own Save. */
          .gd .cform{ padding:.15rem 0 .1rem; }
          .gd .cform + .cform{ margin-top:.8rem; padding-top:.8rem; border-top:1px solid var(--line); }
          .gd .chk{ display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:var(--ink); }
          .gd .chk b{ font-weight:600; }
          .gd .blab{ font-weight:600; font-size:.82rem; color:var(--ink); margin-bottom:.45rem; }
          .gd .uihint{ font-size:.71rem; color:var(--faint); line-height:1.45; margin:.4rem 0 0; }
          .gd .amber{ font-size:.71rem; line-height:1.45; color:var(--soft); margin:.4rem 0 0;
                      padding:.4rem .55rem; border-left:3px solid #f59e0b; background:rgba(245,158,11,.10);
                      border-radius:6px; transition:box-shadow .2s; }
          .gd .amber b{ color:var(--ink); }
          .gd .amber u{ color:var(--accent); text-decoration:none; }
          .gd .radios{ margin:.1rem 0 0; }
          /* the calendar cards, with the money line the real views render */
          .gd .calcards{ display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.55rem; }
          .gd .calcard{ border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px;
                        padding:.35rem .55rem; font-size:.72rem; background:var(--surface); }
          .gd .calcard b{ display:block; color:var(--ink); font-weight:600; }
          .gd .calmoney{ display:none; color:var(--soft); margin-top:.15rem; font-weight:600; }
          .gd .calmoney.paid{ color:var(--good); }
          /* the two window rows — three real form controls each, as on screen */
          .gd .wrows{ margin-top:.6rem; border-radius:9px; padding:.35rem .4rem; transition:box-shadow .2s, background .2s; }
          .gd .wrow{ display:flex; align-items:flex-end; gap:.5rem .8rem; flex-wrap:wrap; padding:.25rem 0; }
          .gd .wlab{ min-width:4.6rem; font-weight:600; font-size:.8rem; color:var(--ink); padding-bottom:.4rem; }
          .gd .wrow .fld label{ text-transform:none; font-size:.66rem; letter-spacing:0; color:var(--soft); }
          .gd .timebox{ width:6.1rem; }
          .gd .timebox .clk{ position:absolute; right:.4rem; top:50%; transform:translateY(-50%); z-index:2;
                             font-size:.72rem; color:var(--faint); }
          /* .numbox is not global (it lives in settings-quote-defaults) — copied
             here and given the same ph/val fill scaffolding as a .box. */
          .gd .numbox{ position:relative; display:flex; align-items:center; height:30px; width:6.4rem;
                       border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:0 .5rem;
                       background:var(--surface); font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .numbox .ph{ color:var(--faint); transition:opacity .15s; }
          .gd .numbox .val{ position:absolute; inset:0; display:flex; align-items:center; padding:0 .5rem;
                            color:var(--ink); opacity:0; white-space:nowrap; overflow:hidden; }
          .gd .numbox .spin{ position:absolute; right:.35rem; top:50%; transform:translateY(-50%); z-index:2;
                             display:flex; flex-direction:column; line-height:.72; font-size:.46rem; color:var(--faint); }
          .gd .save + .save{ margin-left:0; }

          /* ── step 2 onwards: the money tick is on and the cards show figures ── */
          .gd .stage:not([data-step="0"]):not([data-step="1"]) .t-money{ background:var(--accent); color:#fff; }
          .gd .stage:not([data-step="0"]):not([data-step="1"]) .calmoney{ display:block; }
          /* ── step 4 onwards: Waze takes the dot, Google loses it ── */
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]) .rg{ color:var(--soft); font-weight:400; }
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]) .rg .dot{ border-color:var(--border-strong,#c7ccd4); }
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]) .rg .dot::after{ display:none; }
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]) .rw{ color:var(--ink); font-weight:600; }
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]) .rw .dot{ border-color:var(--accent); }
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]) .rw .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          /* ── step 5 onwards: the slots tick is on ── */
          .gd .stage:not([data-step="0"]):not([data-step="1"]):not([data-step="2"]):not([data-step="3"]):not([data-step="4"]) .t-slots{ background:var(--accent); color:#fff; }

          /* per-step spotlights */
          .gd .stage[data-step="1"] .card-t{ color:var(--accent-ink); }
          .gd .stage[data-step="2"] .cf-money, .gd .stage[data-step="3"] .cf-money{ border-radius:9px; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="4"] .cf-nav{ border-radius:9px; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .amber{ box-shadow:0 0 0 3px rgba(245,158,11,.28); }
          .gd .stage[data-step="5"] .wrows{ background:var(--accent-wash); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .sv1, .gd .stage[data-step="4"] .sv2, .gd .stage[data-step="8"] .sv3{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="3"] .tst-money{ opacity:1; transform:none; }
          .gd .stage[data-step="4"] .tst-nav{ opacity:1; transform:none; }

          /* ── f4 / f5 retimed ──────────────────────────────────────────────
             guide.php fills f4 at steps 4-5 and f5 at step 5, but the morning
             row is typed at step 6 and the afternoon at step 7 here, and both
             must stay put through step 8. Same specificity, declared later, so
             these win. */
          .gd .stage[data-step="4"] .f4 .ph, .gd .stage[data-step="5"] .f4 .ph, .gd .stage[data-step="5"] .f5 .ph{ opacity:1; }
          .gd .stage[data-step="4"] .f4 .val, .gd .stage[data-step="5"] .f4 .val, .gd .stage[data-step="5"] .f5 .val{ opacity:0; animation:none; }
          .gd .stage[data-step="4"] .f4, .gd .stage[data-step="5"] .f5{ border-color:var(--border-strong,#c7ccd4) !important; box-shadow:none; }
          .gd .stage[data-step="6"] .f4 .ph, .gd .stage[data-step="7"] .f4 .ph, .gd .stage[data-step="8"] .f4 .ph,
          .gd .stage[data-step="7"] .f5 .ph, .gd .stage[data-step="8"] .f5 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .f4 .val, .gd .stage[data-step="7"] .f4 .val, .gd .stage[data-step="8"] .f4 .val,
          .gd .stage[data-step="7"] .f5 .val, .gd .stage[data-step="8"] .f5 .val{ opacity:1; }
          .gd .stage[data-step="6"] .f4, .gd .stage[data-step="7"] .f5{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="6"] .f4 .val, .gd .stage[data-step="7"] .f5 .val{ animation:gdRoll .8s ease-out both; }

          /* ── step 8: what the booking screen looks like once it is on ── */
          .gd .bookmock{ display:none; }
          .gd .stage[data-step="8"] .bookmock{ display:block; position:absolute; inset:0 0 3rem 0; z-index:3;
                                               background:var(--surface); padding:1.1rem 1.2rem; overflow:hidden; }
          /* the real booking screen is an h1 "Book appointment" with the fields inside a
             bordered fieldset whose legend reads "Appointment" (uppercase, letter-spaced)
             — calendar/new.php page-title + .form-fieldset legend. */
          .gd .fset{ position:relative; border:1px solid var(--line); border-radius:10px;
                     padding:.6rem .7rem .25rem; margin-top:.35rem; }
          .gd .fset .lgnd{ position:absolute; top:-.5rem; left:.7rem; padding:0 .4rem; background:var(--surface);
                           font-size:.64rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em;
                           color:var(--ink); }
          .gd .ampmopt{ display:flex; align-items:center; justify-content:space-between; gap:.7rem;
                        border:1px solid var(--border-strong,#c7ccd4); border-radius:9px; padding:.45rem .6rem;
                        margin-bottom:.4rem; background:var(--surface); }
          .gd .ampmopt.is-sel{ border-color:var(--accent); background:var(--accent-wash); }
          .gd .ampmopt.is-full{ opacity:.55; background:var(--panel); }
          .gd .ampmopt .rng{ color:var(--soft); font-weight:400; }
          /* the real count is plain muted grey; only a full window goes amber (#b45309)
             — calendar/new.php .ampm-count / .ampm-opt.is-full .ampm-count. */
          .gd .ampmopt .cnt{ font-size:.72rem; font-weight:400; color:var(--soft); white-space:nowrap;
                             font-variant-numeric:tabular-nums; }
          .gd .ampmopt.is-full .cnt{ color:#b45309; font-weight:600; }
          @media(max-width:620px){ .gd .wrow{ gap:.4rem .5rem; } .gd .timebox, .gd .numbox{ width:5.3rem; } }',
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
                <div class="toast tst-money">&check; Calendar will show order value + balance.</div>
                <div class="toast tst-nav">&check; Address links will now open in Waze.</div>
                <div class="card-t">Calendar</div>

                <div class="cform cf-money">
                  <div class="chk"><span class="tick t-money">&check;</span> <b>&#128183; Show order value + balance on the calendar</b></div>
                  <div class="uihint">On the month, week and day calendars, each job linked to a quote shows its order
                    value, amount received (deposit + payments) and outstanding balance &mdash; with a PAID badge once
                    it&rsquo;s settled.</div>
                  <div class="amber"><b>&#9888; Heads up:</b> this shows the figures to <b>everyone who can open the
                    calendar</b> &mdash; including staff whose logins normally hide costs, like fitters. It ignores each
                    person&rsquo;s <b>&ldquo;Can view costs&rdquo;</b> setting on the <u>Users</u> page, just for the
                    calendar. Leave it unticked if anyone who sees the calendar shouldn&rsquo;t see the money.</div>
                  <div class="calcards">
                    <div class="calcard"><b>10:00 &middot; Mrs Patel &middot; Fit</b>
                      <span class="calmoney">&pound;540.00 &middot; paid &pound;200.00 &middot; bal &pound;340.00</span></div>
                    <div class="calcard"><b>14:00 &middot; Mr Okoro &middot; Fit</b>
                      <span class="calmoney paid">&check; PAID &pound;540.00 &middot; bal &pound;0.00</span></div>
                  </div>
                  <div class="save sv1">Save</div>
                </div>

                <div class="cform cf-nav">
                  <div class="blab">&#129517; Navigation app</div>
                  <div class="radios"><span class="radio rg on"><span class="dot"></span> Google Maps</span><span class="radio rw"><span class="dot"></span> Waze</span></div>
                  <div class="uihint">When you tap an address on My Schedule or the day calendar, it opens in the app you
                    choose here. Google Maps is the default; pick Waze if your fitters prefer it for live traffic and routing.</div>
                  <div class="save sv2">Save</div>
                </div>

                <div class="cform cf-slots">
                  <div class="chk"><span class="tick t-slots">&check;</span> <b>&#128344; Morning / afternoon booking slots</b></div>
                  <div class="uihint">When booking a <b>quote (measure) visit</b>, offer a <b>Morning</b> or <b>Afternoon</b>
                    window instead of an exact time &mdash; so the customer is given a window, never an exact hour. Set each
                    window&rsquo;s <b>times</b> and how many bookings it holds <b>per day</b> below; once a window is full it
                    can&rsquo;t be booked. Fittings are unaffected.</div>
                  <div class="wrows">
                    <div class="wrow">
                      <span class="wlab">Morning</span>
                      <div class="fld"><label>From</label>
                        <div class="box timebox f4"><span class="ph">09:00</span><span class="val">08:00</span><span class="clk">&#128339;</span></div></div>
                      <div class="fld"><label>To</label>
                        <div class="box timebox f4"><span class="ph">13:00</span><span class="val">12:30</span><span class="clk">&#128339;</span></div></div>
                      <div class="fld"><label>Bookings / day</label>
                        <div class="numbox f4"><span class="ph">4</span><span class="val">6</span><span class="spin">&#9650;&#9660;</span></div></div>
                    </div>
                    <div class="wrow">
                      <span class="wlab">Afternoon</span>
                      <div class="fld"><label>From</label>
                        <div class="box timebox f5"><span class="ph">13:00</span><span class="val">12:30</span><span class="clk">&#128339;</span></div></div>
                      <div class="fld"><label>To</label>
                        <div class="box timebox f5"><span class="ph">17:00</span><span class="val">17:00</span><span class="clk">&#128339;</span></div></div>
                      <div class="fld"><label>Bookings / day</label>
                        <div class="numbox f5"><span class="ph">4</span><span class="val">3</span><span class="spin">&#9650;&#9660;</span></div></div>
                    </div>
                  </div>
                  <div class="save sv3">Save</div>
                </div>

                <div class="bookmock">
                  <div class="okbanner"><b>&check;</b> Morning/afternoon booking slots saved.</div>
                  <div class="card-t" style="margin-top:.75rem;margin-bottom:.5rem">Book appointment</div>
                  <div class="fset">
                    <span class="lgnd">Appointment</span>
                    <div class="frow" style="margin-bottom:.7rem">
                      <div class="fld"><label>Date <span class="req">*</span></label><div class="box">24/09/2026</div></div>
                      <div class="fld"><label>Assigned to</label><span class="selectbox" style="min-width:0;width:100%">Dave (fitter)</span></div>
                    </div>
                    <div class="blab">Time slot <span class="req">*</span></div>
                    <div class="ampmopt is-sel"><span class="radio on"><span class="dot"></span> Morning <span class="rng">(8am&ndash;12:30pm)</span></span><span class="cnt">6 of 6 left</span></div>
                    <div class="ampmopt is-full"><span class="radio"><span class="dot"></span> Afternoon <span class="rng">(12:30pm&ndash;5pm)</span></span><span class="cnt">Full</span></div>
                    <div class="uihint">The customer is given this window, never an exact time. Each window holds a set number
                      of quote visits per day (change the times and limits in Settings &rarr; Calendar).</div>
                    <div class="chk" style="margin-top:.55rem"><span class="tick on">&check;</span> Email the customer their
                      appointment window (needs an email above)</div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Company tab, right at the bottom: <b>Calendar</b>.</b>
                  <b class="c2"><span class="n">2</span> Tick the money on &mdash; every job shows its figures.</b>
                  <b class="c3"><span class="n">3</span> Read the amber warning first, then Save.</b>
                  <b class="c4"><span class="n">4</span> Navigation app: Google Maps or Waze. Save.</b>
                  <b class="c5"><span class="n">5</span> Turn on morning / afternoon windows.</b>
                  <b class="c6"><span class="n">6</span> Set the morning &mdash; From, To, Bookings / day.</b>
                  <b class="c7"><span class="n">7</span> The afternoon is its own window, its own limit.</b>
                  <b class="c8 good"><span class="n">8</span> Saved &mdash; and this is what booking looks like now.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where to find it.</b> Open <b>Settings</b> from the sidebar. You land on the <b>Company</b> tab &mdash;
             scroll right to the bottom, past <i>Company details</i>, <i>Company logo</i> and <i>Dashboard</i>, and the last
             section is <b>Calendar</b>. It looks like one box, but it is really <b>three little forms stacked up</b>, each
             separated by a faint line and each ending in its <b>own Save button</b>. Change one, save that one. They do not
             depend on each other, and saving one never touches the other two.</p>

          <p><b>&#128183; Show order value + balance on the calendar.</b> One tick box. Leave it unticked and your diary just
             shows the appointments. Tick it and every job that is linked to a quote carries a money line on the
             <b>month, week and day</b> calendars. A job still owing money reads
             <code>&pound;540.00 &middot; paid &pound;200.00 &middot; bal &pound;340.00</code>, with the balance in bold. Once it is
             settled the same job reads <code>&check; PAID &pound;540.00 &middot; bal &pound;0.00</code>. The
             &ldquo;<b>paid</b>&rdquo; figure is the <b>deposit plus every payment logged against that quote</b>, so it moves on
             its own as you take money &mdash; you never type it here. Save gives you
             <b>&ldquo;Calendar will show order value + balance.&rdquo;</b>; untick and save and you get
             <b>&ldquo;Calendar money figures are now hidden.&rdquo;</b></p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Heads up:</b> this shows the figures to
             <b>everyone who can open the calendar</b> &mdash; including staff whose logins normally hide costs, like fitters.
             It ignores each person&rsquo;s <b>&ldquo;Can view costs&rdquo;</b> setting on the <b>Users</b> page, just for the
             calendar. Leave it unticked if anyone who sees the calendar shouldn&rsquo;t see the money.</div></div>

          <p><b>&#129517; Navigation app.</b> Two plain radio buttons side by side: <b>Google Maps</b> and <b>Waze</b>. Google
             Maps is what you start with. Whichever you pick is what opens when someone <b>taps an address</b> on
             <b>My Schedule</b> or on the <b>day calendar</b> &mdash; so pick Waze if your fitters want live traffic and
             routing. Save says <b>&ldquo;Address links will now open in Waze.&rdquo;</b> or
             <b>&ldquo;Address links will now open in Google Maps.&rdquo;</b> One honest exception: the little <b>route map drawn
             inside Today&rsquo;s run</b> always stays Google, because Waze cannot be embedded in a page. The tappable address
             links still go where you chose.</p>

          <p><b>&#128344; Booking time slots.</b> This one changes how you <b>book a measure (quote) visit</b>.
             Instead of promising ten past eleven, you promise a <b>morning</b>, an <b>afternoon</b> or an <b>evening</b> &mdash; the customer is
             given the window, never an exact hour. <b>Fittings are unaffected</b>: they keep the ordinary time picker.</p>
          <ul class="steps">
            <li><b>The tick box</b> turns the feature on. Underneath it is one row per time slot &mdash; out of the box,
                <b>Morning</b> and <b>Afternoon</b>.</li>
            <li><b>+ Add a time slot</b> adds another row (up to six) &mdash; name it <i>Evening</i>, say, 18:00 to 20:00.
                <b>&#10005; Remove</b> takes one away; bookings already in it are kept. Gaps between slots are fine.</li>
            <li><b>Each row has four boxes:</b> <b>Name</b> (what the customer sees), <b>From</b> and <b>To</b> (proper time boxes &mdash; use the little clock or
                just type <code>08:00</code>) and <b>Bookings / day</b> (a number box, <b>1 to 99</b>).</li>
            <li><b>The defaults are</b> Morning <b>09:00 to 13:00</b> and Afternoon <b>13:00 to 17:00</b>, with <b>4 bookings
                a day</b> in each. Change them to your own hours.</li>
            <li><b>Every limit is independent</b> &mdash; six mornings, three afternoons and two evenings is perfectly fine.</li>
            <li><b>Save</b> gives you <b>&ldquo;Booking time slots saved.&rdquo;</b>, or
                <b>&ldquo;Booking time slots are off.&rdquo;</b> when you untick it. A slot with no name, or a To time before
                its From, is refused with a message saying which one.</li>
          </ul>
          <p>Now go to <b>Calendar &rarr; Book appointment</b> for a quote visit and the time picker is replaced by
             <b>Time slot <span class="req">*</span></b> and one choice per slot, each showing your hours and a <b>live count</b> of
             what is left that day &mdash; <code>6 of 6 left</code>. Change the date and the counts follow, without reloading
             the page. A window with no room left reads <code>Full</code>, goes grey and cannot be picked. Under the choices
             sits a tick: <b>&ldquo;Email the customer their appointment window (needs an email above)&rdquo;</b>, already
             ticked for you. Pick a window and the booking confirms with
             <b>&ldquo;Appointment booked for Mrs Patel on 24 Sep 2026, Morning (8am&ndash;12:30pm).&rdquo;</b></p>
          <div class="oops"><b>Two refusals you may meet.</b> Save the booking without choosing and it says
             <b>&ldquo;Please choose a time slot.&rdquo;</b> Try to squeeze one into a window that is already full and
             it says <b>&ldquo;Afternoon (12:30pm&ndash;5pm) is fully booked on 24 Sep 2026. Please choose another window or
             another day.&rdquo;</b> Neither loses your typing &mdash; fix the one thing and save again.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Worth knowing before you start:</b>
             <br>&bull; <b>Set your windows before you start booking.</b> Appointments already in the diary keep the time they
             were booked at, but the wording of a window is drawn from <i>today&rsquo;s</i> settings &mdash; so an old
             nine-o&rsquo;clock morning will start describing itself as &ldquo;Morning (8am&ndash;12:30pm)&rdquo;.
             <br>&bull; <b>A slot&rsquo;s times must make sense</b> &mdash; a From at or after its To is refused and nothing is
             saved, so fix that row and Save again.
             <br>&bull; If saving fails you will see <b>&ldquo;Could not save: &hellip; &mdash; have you run
             migrate_ampm_windows_list.php?&rdquo;</b> (the money and map forms have their own versions,
             <b>migrate_calendar_money.php</b> and <b>migrate_map_provider.php</b>). That means the database update has not been
             run yet &mdash; nothing you can fix from this screen, so tell whoever looks after the system.
             <br>&bull; If you have <b>Compact mode</b> switched on, the little grey explanations under each tick are hidden, so
             the section looks barer than it does here. The amber warning stays put.</div></div>

          <p>And again, because it is the one thing people trip over: <b>each of the three has its own Save</b>. Ticked the
             money but nothing changed? You probably pressed the Save belonging to a different form. Once the slots feature is
             on, open <b>Calendar &rarr; Book appointment</b> and book a quote visit to see your windows in action.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Calendar heading highlights.',
                'Settings, Company tab, and scroll right to the bottom — past your company details, your logo and the dashboard. The last section is Calendar. It looks like one box, but it is really three little settings sitting together, and each one has its own Save button. Change one, save that one. They do not depend on each other.', 1],
            ['0:16', 'Money tick on; cards show figures.',
                'First one. Tick "Show order value + balance on the calendar" and every job that is linked to a quote shows its money on the month, week and day calendars. This one still owes: five hundred and forty pounds, paid two hundred, balance three hundred and forty. This one is settled, so it reads PAID, balance nothing. "Paid" means the deposit plus any payments you have logged on the quote — you never type it here.', 2],
            ['0:36', 'Amber warning glows; Save pressed.',
                'Before you tick it, read the amber note underneath. This shows the money to everyone who can open the calendar, fitters included. It ignores each person\'s "Can view costs" setting on the Users page, just for the calendar. If anyone who sees your diary should not see your figures, leave it unticked. Press Save and you get "Calendar will show order value plus balance." Untick and save, and it says the money figures are now hidden.', 3],
            ['0:57', 'Waze radio takes the dot; Save pressed.',
                'Second one. Navigation app — two plain radio buttons, Google Maps or Waze. Google Maps is what you start with. Whichever you pick is what opens when somebody taps an address on My Schedule or on the day calendar, so pick Waze if your fitters want live traffic. Save says "Address links will now open in Waze." One honest exception: the little route map drawn inside Today&rsquo;s run always stays Google, because Waze cannot be put inside a page.', 4],
            ['1:16', 'Slots tick on; the two rows light up.',
                'Third one, and this is the big one. It changes how you book a measure visit. Instead of promising ten past eleven, you promise a morning or an afternoon. Fittings are not touched — they still get a proper time. Underneath you get two rows, Morning and Afternoon, and each row has three boxes: From, To, and Bookings per day.', 5],
            ['1:32', 'Morning row types in: 08:00, 12:30, 6.',
                'Set the morning. Out of the box it is nine till one with room for four. Type your own — say eight o\'clock to half twelve, and six jobs. These are real time boxes, so use the little clock or just type oh eight colon oh oh.', 6],
            ['1:47', 'Afternoon row types in: 12:30, 17:00, 3.',
                'Now the afternoon. It is its own window with its own limit, so six mornings and three afternoons is perfectly fine. Anything from one to ninety-nine in each. One gotcha: if you type something the box cannot read as a time, it quietly puts the standard time back rather than telling you off — so have a glance at the boxes after you save.', 7],
            ['2:04', 'Saved; the booking screen appears.',
                'Press Save, and it says "Morning slash afternoon booking slots saved." Now book a quote visit, and instead of a time picker you get two choices with the hours you just set and a live count of what is left that day. Change the date and the counts follow. A full window goes grey and cannot be picked — push it and you are told the afternoon is fully booked on that day, choose the other window or another day. Pick one, and the booking confirms: appointment booked for Mrs Patel on the twenty-fourth of September, morning, eight till half twelve.', 8],
        ],
];
