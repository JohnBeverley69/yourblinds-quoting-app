<?php
declare(strict_types=1);

/**
 * Guide: calendar-booking
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers /calendar/index.php (the rolling six-week board, the Everyone /
 * Just me toggle, the Pending Fitting tray, drag-to-schedule, the per-card
 * note and issue buttons) and /calendar/new.php in full — all three
 * fieldsets — plus the AM/PM windows and finishing a job on
 * /calendar/view.php.
 *
 * Scenes are keyed on data-step, and because the shared f1..f5 fill rules
 * only cover steps 1-5 this guide declares its own fill classes (.fA* for
 * the customer form at step 5, .fB* for the appointment box at steps 6-7)
 * so nothing is ever pre-filled on the poster.
 */

return [
        'aud'     => 'admin',
        'section' => 'Calendar',
        'title'   => 'Calendar & booking',
        'eyebrow' => 'Calendar',
        'blurb'   => 'The diary: a rolling six weeks from a Monday, jobs coloured by stage, the Pending Fitting tray you drag onto a date, the full booking form, AM/PM windows and finishing a job off.',
        'lede'    => 'The <b>Calendar</b> is your diary. It shows a <b>rolling six weeks starting on a Monday</b> &mdash; not a
                      calendar month &mdash; and every job on it is a card, <b>coloured by the stage it has reached</b>. Accepted
                      quotes drop a <b>fitting</b> into the <b>Pending Fitting</b> tray for you to <b>drag onto a date</b>; a
                      <b>measure visit</b> you book yourself on a form that also creates the customer; and marking the install
                      <b>Completed</b> moves the order on to <b>Fitted</b> without you touching it twice.',
        'open'    => '/calendar/index.php',
        'css'     => '
          /* ---- scene switching ------------------------------------------------ */
          .gd .osc{ display:none; }
          /* step 0 = the poster: the empty board, nothing filled in anywhere */
          .gd .stage[data-step="0"] .scBoard,
          .gd .stage[data-step="1"] .scBoard, .gd .stage[data-step="2"] .scBoard{ display:block; }
          .gd .stage[data-step="3"] .scTray{ display:block; }
          .gd .stage[data-step="4"] .scDrop{ display:block; }
          .gd .stage[data-step="5"] .scWho{ display:block; }
          .gd .stage[data-step="6"] .scWhen, .gd .stage[data-step="7"] .scWhen{ display:block; }
          .gd .stage[data-step="8"] .scDone{ display:block; }

          /* ---- local progressive fill (the shared f1..f5 stop at step 5) ------- */
          .gd .stage[data-step="5"] .fA .ph{ opacity:0; }
          .gd .stage[data-step="5"] .fA .val{ opacity:1; animation:gdRoll .6s ease-out both; }
          .gd .stage[data-step="5"] .dA2 .val{ animation-delay:.3s; }
          .gd .stage[data-step="5"] .dA3 .val{ animation-delay:.6s; }
          .gd .stage[data-step="5"] .dA4 .val{ animation-delay:.9s; }
          .gd .stage[data-step="5"] .dA5 .val{ animation-delay:1.2s; }
          .gd .stage[data-step="6"] .fB .ph, .gd .stage[data-step="7"] .fB .ph{ opacity:0; }
          .gd .stage[data-step="6"] .fB .val, .gd .stage[data-step="7"] .fB .val{ opacity:1; }
          .gd .stage[data-step="6"] .fB .val{ animation:gdRoll .6s ease-out both; }
          .gd .stage[data-step="6"] .dB2 .val{ animation-delay:.35s; }
          .gd .stage[data-step="6"] .dB3 .val{ animation-delay:.7s; }
          /* the ticks and the chosen radio land as the narration reaches them */
          .gd .stage[data-step="5"] .tkA{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="6"] .rpm .dot, .gd .stage[data-step="7"] .rpm .dot{ border-color:var(--accent); }
          .gd .stage[data-step="6"] .rpm .dot::after, .gd .stage[data-step="7"] .rpm .dot::after{
            content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .stage[data-step="6"] .aopt.pm, .gd .stage[data-step="7"] .aopt.pm{
            border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---- board: header, subtitle, toggle, actions ------------------------ */
          .gd .pgh{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .pgs{ font-size:.7rem; color:var(--faint); margin:.1rem 0 .35rem; }
          .gd .pgs a{ color:var(--accent); font-weight:700; text-decoration:none; }
          .gd .hdrow{ display:flex; align-items:flex-start; gap:.6rem; flex-wrap:wrap; }
          .gd .hdrow .acts{ margin-left:auto; display:flex; gap:.3rem; flex-wrap:wrap; }
          .gd .cbtn{ display:inline-flex; border-radius:7px; padding:.26rem .55rem; font-size:.66rem; font-weight:700; }
          .gd .cbtn.pri{ background:var(--accent); color:#fff; }
          .gd .cbtn.sec{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .cbtn.ok{ background:#15803d; color:#fff; }
          .gd .vtog{ display:inline-flex; border:1px solid var(--line); border-radius:999px; overflow:hidden; font-size:.64rem; }
          .gd .vtog span{ padding:.18rem .62rem; color:var(--soft); }
          .gd .vtog span.on{ background:var(--accent); color:#fff; font-weight:700; }
          .gd .stage[data-step="2"] .vtog{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="2"] .issp{ box-shadow:0 0 0 3px var(--accent-wash); border-radius:999px; }

          /* ---- board: toolbar + legend ---------------------------------------- */
          .gd .ctool{ display:flex; align-items:center; gap:.5rem; margin:.55rem 0 .4rem; flex-wrap:wrap; }
          .gd .cnav{ display:flex; align-items:center; gap:.3rem; }
          .gd .cnb{ border:1px solid var(--line); border-radius:6px; padding:.1rem .38rem; font-size:.72rem; color:var(--soft); }
          .gd .cml{ font-size:.72rem; font-weight:700; color:var(--ink); text-align:center; line-height:1.15; }
          .gd .cml small{ display:block; font-size:.56rem; font-weight:500; color:var(--faint); }
          .gd .cleg{ display:flex; flex-wrap:wrap; gap:.15rem .5rem; font-size:.55rem; color:var(--soft); margin-left:auto; max-width:26rem; }
          .gd .cleg span{ display:inline-flex; align-items:center; gap:.2rem; }
          .gd .cleg i{ width:8px; height:8px; border-radius:2px; display:inline-block; }

          /* ---- board: the 6-week grid ----------------------------------------- */
          .gd .cwk{ display:grid; grid-template-columns:repeat(7,1fr); gap:2px; font-size:.5rem; color:var(--faint); text-transform:uppercase; letter-spacing:.06em; margin-bottom:2px; }
          .gd .mcal{ display:grid; grid-template-columns:repeat(7,1fr); gap:2px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .mc{ background:var(--surface); min-height:2.5rem; padding:.12rem .18rem; position:relative; }
          .gd .dn{ font-size:.53rem; color:var(--faint); font-variant-numeric:tabular-nums; }
          .gd .mc.out{ background:var(--panel); }
          .gd .mc.tod{ background:var(--accent-wash); border-left:3px solid var(--nav); }
          .gd .appt{ display:block; border-radius:4px; padding:.06rem .22rem; font-size:.5rem; color:#fff; margin-top:.1rem; line-height:1.3; overflow:hidden; }
          .gd .appt .aw{ display:block; font-weight:700; opacity:.9; }
          .gd .appt.fit{ box-shadow:inset 0 0 0 2px rgba(0,0,0,.6); }
          .gd .appt.iss{ box-shadow:inset 0 0 0 2px #e11d48; }
          .gd .appt .gl{ font-size:.5rem; opacity:.9; margin-right:.12rem; }

          /* ---- pending tray ---------------------------------------------------- */
          .gd .ptray{ border:1px solid #f0c674; background:rgba(240,198,116,.14); border-radius:9px; padding:.5rem .65rem; margin-bottom:.55rem; }
          .gd .ptray .ph2{ font-weight:700; color:var(--ink); font-size:.76rem; display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
          .gd .ptray .pcount{ background:#b45309; color:#fff; border-radius:999px; font-size:.58rem; padding:.02rem .34rem; }
          .gd .ptray .phint{ font-size:.62rem; color:var(--faint); font-weight:400; }
          .gd .pcard{ display:inline-block; background:var(--surface); border:1px solid var(--line); border-radius:7px; padding:.32rem .52rem; font-size:.68rem; color:var(--ink); margin-top:.35rem; }
          .gd .pcard .pm{ display:block; color:var(--faint); font-size:.6rem; }
          .gd .stage[data-step="3"] .ptray{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .emptyq{ font-size:.66rem; color:var(--soft); border:1px dashed var(--line); border-radius:8px; padding:.4rem .6rem; margin-top:.5rem; }

          /* ---- drop + time popup ---------------------------------------------- */
          .gd .dragc{ display:inline-block; background:var(--surface); border:1px solid var(--accent); border-radius:7px; padding:.3rem .5rem; font-size:.66rem; color:var(--ink); transform:rotate(-2deg); box-shadow:0 8px 18px -10px rgba(20,30,45,.5); }
          .gd .tpop{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); max-width:14rem; margin-top:.5rem; }
          .gd .tpop .tt{ font-weight:700; color:var(--ink); font-size:.74rem; margin-bottom:.4rem; }
          .gd .tin{ display:block; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.28rem .5rem; font-size:.8rem; color:var(--ink); background:var(--surface); font-variant-numeric:tabular-nums; }
          .gd .tbtns{ display:flex; gap:.4rem; margin-top:.5rem; }
          .gd .tbtn{ display:inline-flex; border-radius:6px; padding:.26rem .6rem; font-size:.7rem; font-weight:600; }
          .gd .tbtn.go{ background:var(--accent); color:#fff; }
          .gd .tbtn.no{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }

          /* ---- the booking form ------------------------------------------------ */
          .gd .fset{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem .3rem; margin-bottom:.5rem; }
          .gd .fleg{ font-size:.56rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--soft); margin-bottom:.35rem; }
          .gd .frow3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.45rem .5rem; }
          .gd .frow4{ display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:.45rem .5rem; }
          .gd .frow + .frow, .gd .frow3 + .frow3, .gd .fld + .fld, .gd .frow + .fld, .gd .fld + .frow3{ margin-top:.42rem; }
          .gd .lkrow{ display:grid; grid-template-columns:1fr auto; gap:.45rem; align-items:end; }
          .gd .lbox{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; background:var(--surface); padding:.15rem; }
          .gd .lbox b{ display:block; font-size:.62rem; font-weight:400; color:var(--soft); padding:.1rem .3rem; border-radius:4px; }
          .gd .lbox b.pick{ background:var(--accent); color:#fff; font-weight:600; }
          .gd .ckrow{ display:inline-flex; align-items:center; gap:.4rem; font-size:.68rem; color:var(--ink); margin:.4rem 0 .1rem; }
          .gd .bblk{ border-left:2px solid var(--line); padding-left:.5rem; margin-top:.35rem; }
          .gd .fhint{ font-size:.6rem; color:var(--faint); line-height:1.45; margin:.3rem 0 0; }
          /* a number input with its spinner, so Duration (mins) reads as typable */
          .gd .numbox{ position:relative; padding-right:1.1rem; }
          .gd .numbox .spin{ position:absolute; right:.32rem; top:50%; transform:translateY(-50%); display:flex; flex-direction:column; line-height:.62; font-size:.5rem; color:var(--faint); }
          .gd .numbox .spin i{ font-style:normal; display:block; }
          /* a dropped-open list: the closed control plus the real options beneath it */
          .gd .optlist{ border:1px solid var(--border-strong,#c7ccd4); border-top:0; border-radius:0 0 7px 7px; background:var(--surface); padding:.12rem; box-shadow:0 8px 18px -12px rgba(20,30,45,.45); }
          .gd .optlist b{ display:block; font-size:.66rem; font-weight:400; color:var(--soft); padding:.12rem .32rem; border-radius:4px; }
          .gd .optlist b.pick{ background:var(--accent); color:#fff; font-weight:600; }
          .gd .facts{ display:flex; gap:.4rem; margin-top:.5rem; }

          /* ---- AM/PM radio cards (drawn as the real radios) -------------------- */
          .gd .ampm{ display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .aopt{ flex:1 1 9rem; display:flex; align-items:center; gap:.4rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:9px; padding:.42rem .55rem; font-size:.68rem; background:var(--surface); }
          .gd .aopt .an{ font-weight:600; color:var(--ink); }
          .gd .aopt .ar{ font-weight:400; color:var(--faint); }
          .gd .aopt .ac{ margin-left:auto; font-size:.6rem; color:var(--faint); font-variant-numeric:tabular-nums; }
          .gd .aopt.full{ opacity:.55; }
          .gd .aopt.full .ac{ color:#b45309; font-weight:700; }
          .gd .aopt .radio{ margin-right:0; }
          .gd .aopt.full .radio .dot{ background:var(--panel); }

          /* ---- errors + the slots-off alternative ----------------------------- */
          .gd .errwrap{ display:none; margin-bottom:.45rem; }
          .gd .stage[data-step="7"] .errwrap{ display:block; }
          .gd .altbox{ display:none; border:1px dashed var(--line); border-radius:9px; padding:.5rem .6rem; margin-top:.5rem; }
          .gd .stage[data-step="7"] .altbox{ display:block; }
          .gd .altbox .at{ font-size:.6rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
          .gd .slotwrap{ display:block; }
          .gd .stage[data-step="7"] .slotwrap{ opacity:.45; }

          /* ---- the appointment page ------------------------------------------- */
          .gd .dlr{ display:grid; grid-template-columns:6.5rem 1fr; gap:.28rem .6rem; font-size:.72rem; color:var(--ink); align-items:center; }
          .gd .dlr dt{ color:var(--faint); font-size:.62rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .dlr dd{ margin:0; }
          .gd .hrule{ border:0; border-top:1px solid var(--line); margin:.6rem 0; }
          .gd .selrow{ display:flex; align-items:center; gap:.45rem; font-size:.72rem; color:var(--ink); flex-wrap:wrap; }
          .gd .spill{ display:inline-flex; border-radius:999px; padding:.1rem .5rem; font-size:.6rem; font-weight:700; background:var(--good-wash); color:var(--good); }
          .gd .dz{ border:1px solid var(--err); border-radius:9px; padding:.45rem .6rem; margin-top:.55rem; font-size:.64rem; color:var(--soft); }
          .gd .dz b{ color:var(--err); }

          .gd .note{ font-size:.66rem; color:var(--faint); margin-top:.5rem; line-height:1.5; }
          .gd .note b{ color:var(--ink); }
          .gd .navh{ font-size:.56rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c; font-weight:700; margin:.7rem 0 .15rem; padding:0 .5rem; }
          @media(max-width:620px){ .gd .frow3, .gd .frow4{ grid-template-columns:1fr; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / calendar</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a class="on">Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup</div>
                <a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- ===== Scene: the board (steps 1-2) ===== -->
                <div class="osc scBoard">
                  <div class="hdrow">
                    <div>
                      <div class="pgh">Calendar</div>
                      <div class="pgs">Appointments for Bev Blinds. &middot; <a>&#128198; Week</a> &middot; <a>&#128197; Day</a></div>
                      <span class="vtog"><span class="on">Everyone</span><span>Just me</span></span>
                    </div>
                    <div class="acts">
                      <span class="cbtn ok">Today&rsquo;s run &rarr;</span>
                      <span class="cbtn sec">+ New quote</span>
                      <span class="cbtn pri">+ Book Appointment</span>
                    </div>
                  </div>

                  <div class="ctool">
                    <div class="cnav">
                      <span class="cnb">&lsaquo;</span>
                      <span class="cml">August 2026<small>Mon 3 Aug &mdash; Sun 13 Sep</small></span>
                      <span class="cnb">&rsaquo;</span>
                      <span class="cnb">Today</span>
                    </div>
                    <div class="cleg">
                      <span><i style="background:#7c3aed"></i> Quote drafted</span>
                      <span><i style="background:#f59e0b"></i> Quote sent</span>
                      <span><i style="background:#16a34a"></i> Accepted</span>
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
                      <span class="issp"><i style="background:transparent;outline:2px solid #e11d48;outline-offset:-2px"></i> &#9888;&#65039; Issues (2)</span>
                    </div>
                  </div>

                  <div class="cwk"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                  <div class="mcal">
                    <span class="mc"><span class="dn">10/08</span></span>
                    <span class="mc"><span class="dn">11/08</span></span>
                    <span class="mc tod"><span class="dn">12/08</span><span class="appt" style="background:#2563eb"><span class="aw">Morning</span>ABC-2026-0031 &mdash; Angela Reed <span class="gl">&#9888;&#65039;</span><span class="gl">&#128221;</span></span></span>
                    <span class="mc"><span class="dn">13/08</span></span>
                    <span class="mc"><span class="dn">14/08</span></span>
                    <span class="mc"><span class="dn">15/08</span><span class="appt fit" style="background:#6366f1"><span class="aw">9:00am</span>Install: ABC-2026-0042 &mdash; Emma Fletcher <span class="gl">&rarr;</span></span></span>
                    <span class="mc"><span class="dn">16/08</span></span>
                    <span class="mc"><span class="dn">17/08</span></span>
                    <span class="mc"><span class="dn">18/08</span></span>
                    <span class="mc"><span class="dn">19/08</span><span class="appt iss" style="background:#16a34a"><span class="aw">Afternoon</span>ABC-2026-0055 &mdash; Tom Shah</span></span>
                    <span class="mc"><span class="dn">20/08</span></span>
                    <span class="mc"><span class="dn">21/08</span></span>
                    <span class="mc"><span class="dn">22/08</span></span>
                    <span class="mc"><span class="dn">23/08</span></span>
                    <span class="mc out"><span class="dn">01/09</span></span>
                    <span class="mc out"><span class="dn">02/09</span></span>
                    <span class="mc out"><span class="dn">03/09</span></span>
                    <span class="mc out"><span class="dn">04/09</span></span>
                    <span class="mc out"><span class="dn">05/09</span></span>
                    <span class="mc out"><span class="dn">06/09</span></span>
                    <span class="mc out"><span class="dn">07/09</span></span>
                  </div>
                  <p class="note">Cells are labelled <b>DD/MM</b>. Today is tinted; days outside the month named above are shaded, but they still work.</p>
                </div>

                <!-- ===== Scene: the Pending Fitting tray (step 3) ===== -->
                <div class="osc scTray">
                  <div class="ptray">
                    <div class="ph2">Pending Fitting <span class="pcount">1</span>
                      <span class="phint">Drag a card onto a date to schedule it.</span></div>
                    <span class="pcard">Install: ABC-2026-0042 &mdash; Emma Fletcher
                      <span class="pm">Leamington Spa &middot; CV32 5PJ</span></span>
                  </div>
                  <div class="emptyq">When there is nothing waiting it reads:
                    &ldquo;<b>Nothing pending. Accepted quotes land here until you place them on a date.</b>&rdquo;</div>
                  <p class="note">The <b>Week</b> and <b>Day</b> views have no tray &mdash; they show a nudge instead:
                    &ldquo;<b>1 fitting pending &mdash; place it on the Month calendar to schedule.</b>&rdquo;</p>
                </div>

                <!-- ===== Scene: drag onto a day + the time popup (step 4) ===== -->
                <div class="osc scDrop">
                  <div class="cwk"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                  <div class="mcal">
                    <span class="mc"><span class="dn">18/08</span></span>
                    <span class="mc"><span class="dn">19/08</span></span>
                    <span class="mc"><span class="dn">20/08</span></span>
                    <span class="mc" style="box-shadow:inset 0 0 0 2px var(--accent)"><span class="dn">21/08</span></span>
                    <span class="mc"><span class="dn">22/08</span></span>
                    <span class="mc"><span class="dn">23/08</span></span>
                    <span class="mc"><span class="dn">24/08</span></span>
                  </div>
                  <span class="dragc" style="margin-top:.45rem">Install: ABC-2026-0042 &mdash; Emma Fletcher</span>
                  <div class="tpop">
                    <div class="tt">Set fitting time</div>
                    <span class="tin">09:00</span>
                    <div class="tbtns"><span class="tbtn no">Cancel</span><span class="tbtn go">Schedule</span></div>
                  </div>
                  <p class="note">Dropping an <b>already-dated</b> job on another day changes the <b>date only</b> &mdash;
                    time, length, fitter and status stay exactly as they were.</p>
                </div>

                <!-- ===== Scene: Book appointment, who it is for (step 5) ===== -->
                <div class="osc scWho">
                  <div class="pgh">Book appointment</div>
                  <div class="pgs"><a>&larr; Back to calendar</a></div>

                  <div class="fset">
                    <div class="fleg">Customer</div>
                    <div class="fld"><label>Name <span class="req">*</span></label>
                      <div class="box fA"><span class="ph"></span><span class="val">Angela Reed</span></div></div>
                    <div class="frow3">
                      <div class="fld"><label>Email</label>
                        <div class="box fA dA2"><span class="ph"></span><span class="val">angela@example.co.uk</span></div></div>
                      <div class="fld"><label>Phone (landline)</label>
                        <div class="box fA dA2"><span class="ph"></span><span class="val">01926 555114</span></div></div>
                      <div class="fld"><label>Mobile</label>
                        <div class="box fA dA3"><span class="ph"></span><span class="val">07700 900114</span></div>
                        <span class="ckrow"><span class="tick tkA">&check;</span> Mobile is on WhatsApp</span></div>
                    </div>
                  </div>

                  <div class="fset">
                    <div class="fleg">Installation address</div>
                    <div class="lkrow">
                      <div class="fld"><label>Find by postcode</label>
                        <div class="box fA dA3"><span class="ph">e.g. BS1 4ST</span><span class="val">CV32 5PJ</span></div></div>
                      <span class="cbtn sec" style="padding:.34rem .6rem">Find address</span>
                    </div>
                    <div class="fld" style="margin-top:.42rem"><label>Pick an address</label>
                      <div class="lbox"><b>12 Clarendon Street, Leamington Spa</b><b class="pick">14 Clarendon Street, Leamington Spa</b><b>16 Clarendon Street, Leamington Spa</b></div></div>
                    <div class="fld"><label>Address line 1</label>
                      <div class="box fA dA4"><span class="ph"></span><span class="val">14 Clarendon Street</span></div></div>
                    <div class="fld"><label>Address line 2</label>
                      <div class="box fA dA4"><span class="ph"></span><span class="val"></span></div></div>
                    <div class="frow3">
                      <div class="fld"><label>Town</label>
                        <div class="box fA dA4"><span class="ph"></span><span class="val">Leamington Spa</span></div></div>
                      <div class="fld"><label>County</label>
                        <div class="box fA dA4"><span class="ph"></span><span class="val">Warwickshire</span></div></div>
                      <div class="fld"><label>Postcode</label>
                        <div class="box fA dA4"><span class="ph"></span><span class="val">CV32 5PJ</span></div></div>
                    </div>
                    <span class="ckrow"><span class="tick tkA">&check;</span> Different billing address?</span>
                    <div class="bblk">
                      <div class="fld"><label>Billing address line 1</label>
                        <div class="box fA dA5"><span class="ph"></span><span class="val">Reed &amp; Co, Unit 9</span></div></div>
                      <div class="fld"><label>Billing address line 2</label>
                        <div class="box fA dA5"><span class="ph"></span><span class="val"></span></div></div>
                      <div class="frow3">
                        <div class="fld"><label>Town</label>
                          <div class="box fA dA5"><span class="ph"></span><span class="val">Warwick</span></div></div>
                        <div class="fld"><label>County</label>
                          <div class="box fA dA5"><span class="ph"></span><span class="val">Warwickshire</span></div></div>
                        <div class="fld"><label>Postcode</label>
                          <div class="box fA dA5"><span class="ph"></span><span class="val">CV34 4AB</span></div></div>
                      </div>
                    </div>
                  </div>
                  <p class="note">The <b>Find by postcode</b> row only appears if your account has the lookup add-on.
                    Without it you simply type the address in.</p>
                </div>

                <!-- ===== Scene: the Appointment box (steps 6-7) ===== -->
                <div class="osc scWhen">
                  <div class="errwrap">
                    <div class="errbanner"><b>&#9888;</b> <span>Morning (9am&ndash;1pm) is fully booked on 22 Aug 2026. Please choose the other window or another day.</span></div>
                  </div>

                  <div class="fset">
                    <div class="fleg">Appointment</div>
                    <div class="slotwrap">
                      <div class="frow">
                        <div class="fld"><label>Date <span class="req">*</span></label>
                          <div class="box fB"><span class="ph">dd/mm/yyyy</span><span class="val">22/08/2026</span></div></div>
                        <div class="fld"><label>Assigned to</label>
                          <div class="selectbox" style="min-width:0;width:100%">Sam Yates</div>
                          <div class="optlist"><b>&mdash; Unassigned &mdash;</b><b class="pick">Sam Yates</b><b>Priya Nair</b></div></div>
                      </div>
                      <div class="fld" style="margin-top:.45rem"><label>Time slot <span class="req">*</span></label>
                        <div class="ampm">
                          <div class="aopt full">
                            <span class="radio"><span class="dot"></span></span>
                            <span class="an">Morning <span class="ar">(9am&ndash;1pm)</span></span>
                            <span class="ac">Full</span>
                          </div>
                          <div class="aopt pm">
                            <span class="radio rpm"><span class="dot"></span></span>
                            <span class="an">Afternoon <span class="ar">(1pm&ndash;5pm)</span></span>
                            <span class="ac">3 of 4 left</span>
                          </div>
                        </div>
                        <p class="fhint">The customer is given this window, never an exact time. Each window holds a set number of
                          quote visits per day (change the times and limits in Settings &rarr; Calendar).</p>
                        <span class="ckrow"><span class="tick on">&check;</span> Email the customer their appointment window (needs an email above)</span>
                      </div>
                    </div>

                    <div class="altbox">
                      <div class="at">With Morning / afternoon slots switched OFF, the same box looks like this</div>
                      <div class="frow4">
                        <div class="fld"><label>Date <span class="req">*</span></label><div class="box">22/08/2026</div></div>
                        <div class="fld"><label>Time <span class="req">*</span></label>
                          <div class="box">09:00</div>
                          <div class="optlist"><b>08:30</b><b class="pick">09:00</b><b>09:30</b><b>10:00</b></div></div>
                        <div class="fld"><label>Duration (mins)</label>
                          <div class="box numbox">60<span class="spin"><i>&#9652;</i><i>&#9662;</i></span></div></div>
                        <div class="fld"><label>Assigned to</label><div class="selectbox" style="min-width:0;width:100%">Sam Yates</div></div>
                      </div>
                      <p class="fhint"><b>Time</b> is a box you type into &mdash; it shows
                        <code>HH:MM (e.g. 09:30)</code> until you do &mdash; with the half-hour list from
                        <b>08:00</b> to <b>18:00</b> dropping down under it, filtering as you type. Type anything else and it
                        tells you &ldquo;<em>No common slot &mdash; type any HH:MM you like.</em>&rdquo;
                        <b>Duration (mins)</b> is a number box with up/down arrows, stepping in fives.</p>
                    </div>

                    <div class="fld" style="margin-top:.45rem"><label>Notes</label>
                      <div class="ta fB dB3"><span class="ph"></span><span class="val">Side gate, dog in the garden.</span></div></div>
                    <div class="facts"><span class="cbtn pri" style="padding:.34rem .7rem">Book appointment</span><span class="cbtn sec" style="padding:.34rem .7rem">Cancel</span></div>
                  </div>
                </div>

                <!-- ===== Scene: finishing the job (step 8) ===== -->
                <div class="osc scDone">
                  <div class="hdrow">
                    <div>
                      <div class="pgh">Install: ABC-2026-0042 &mdash; Emma Fletcher</div>
                      <div class="pgs"><a>&larr; Back to calendar</a></div>
                    </div>
                    <div class="acts">
                      <span class="cbtn ok">Google Maps &rarr;</span>
                      <span class="cbtn ok">Waze &rarr;</span>
                      <span class="cbtn pri">Open order &rarr;</span>
                      <span class="cbtn sec">Edit</span>
                    </div>
                  </div>
                  <div class="okbanner" style="margin:.35rem 0 .55rem"><b>&check;</b>
                    Status updated to completed. Linked quote ABC-2026-0042 advanced to &ldquo;fitted&rdquo;.</div>
                  <div class="card-t" style="margin-bottom:.45rem">When &amp; status <span class="spill">Completed</span></div>
                  <dl class="dlr">
                    <dt>Date</dt><dd>Friday, 21 August 2026</dd>
                    <dt>Time</dt><dd>9:00am &ndash; 10:00am <span style="color:var(--faint)">(60 mins)</span></dd>
                    <dt>Assigned to</dt><dd><span class="selrow"><span class="selectbox" style="min-width:8rem">Dave Cole (fitter)</span><span class="cbtn sec" style="padding:.24rem .55rem">Save</span></span></dd>
                  </dl>
                  <hr class="hrule">
                  <div class="selrow"><span>Update status:</span>
                    <span class="selectbox" style="min-width:8rem">Completed</span>
                    <span class="cbtn sec" style="padding:.24rem .55rem">Save status</span></div>
                  <div class="dz"><b>Danger zone</b> &mdash; Deleting this appointment is permanent. The customer record and any
                    linked quotes will be kept; only the calendar entry is removed.</div>
                  <p class="note">Back on the board, every card carries a <b>&#128221;</b> note button and a
                    <b>&#9888;&#65039;</b> issue button &mdash; and a <b>&rarr;</b> straight to the order when a quote is linked.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> The board &mdash; six weeks from a Monday, jobs coloured by stage.</b>
                  <b class="c2"><span class="n">2</span> Everyone or Just me &mdash; and the Issues filter.</b>
                  <b class="c3"><span class="n">3</span> Accepted quotes wait in the Pending Fitting tray.</b>
                  <b class="c4"><span class="n">4</span> Drag onto a day &rarr; Set fitting time &rarr; Schedule.</b>
                  <b class="c5"><span class="n">5</span> Book Appointment &mdash; who it is for, and where.</b>
                  <b class="c6"><span class="n">6</span> Pick the Morning or Afternoon window.</b>
                  <b class="c7 err"><span class="n">7</span> Window gone? And what you see with slots off.</b>
                  <b class="c8 good"><span class="n">8</span> Mark it Completed &rarr; the order goes Fitted.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> In the menu down the left-hand side, <b>Calendar</b> sits under <b>Work</b>, right below
             Dashboard. The page is headed <b>Calendar</b>, and underneath it says who you are looking at &mdash;
             &ldquo;<em>Appointments for &lt;your company&gt;.</em>&rdquo; &mdash; followed by two plain links,
             <b>&#128198; Week</b> and <b>&#128197; Day</b>. What you land on is a <b>rolling six weeks that always starts on a
             Monday</b>. It is <em>not</em> a calendar month, and that catches people out: the <b>&lsaquo;</b> and
             <b>&rsaquo;</b> arrows move you <b>one week</b> at a time, the middle of the toolbar names the month most of the
             screen belongs to (<em>August 2026</em>) with the real span underneath (<em>Mon 3 Aug &mdash; Sun 13 Sep</em>), and
             a <b>Today</b> button only appears once you have wandered off this week. Days that fall outside the named month are
             shaded but perfectly usable, and every cell is labelled <b>DD/MM</b> &mdash; so look for <b>21/08</b>, not a bare 21.
             Top right sit the page&rsquo;s buttons: <b>+ Book Appointment</b> always, <b>+ New quote</b> if your login is allowed
             to create quotes, and a green <b>Today&rsquo;s run &rarr;</b> <em>only</em> if the maps add-on is switched on for
             your account &mdash; it opens a <b>Today&rsquo;s run</b> page that plots the day&rsquo;s visits as a driving route
             on a map. No maps add-on, no button, and nothing is broken.</p>

          <ul class="steps">
            <li><b>Read the board.</b> Each job is a card showing either a time (<em>9:00am</em>) or, if it was booked into a
                half-day window, the word <b>Morning</b> or <b>Afternoon</b> &mdash; then the job reference and the title, like
                <b>&ldquo;ABC-2026-0031 &mdash; Angela Reed&rdquo;</b>. The reference is only put in front when the title doesn&rsquo;t
                already contain it &mdash; a fitting the system created for you is already titled
                <b>&ldquo;Install: ABC-2026-0042 &mdash; Emma Fletcher&rdquo;</b>, so it isn&rsquo;t repeated. The colour is the stage the job has reached,
                straight from your own <em>Settings &rarr; Status colours</em>: <b>Quote drafted, Quote sent, Accepted,
                Declined, Ordered, Appointment booked, Fitting booked, Fitted, Invoiced, Paid, Cancelled, No-show</b> &mdash;
                the legend across the toolbar lists them all. <b>Fittings carry a dark outline</b>; measures do not. Click a card
                to open the appointment; click the small <b>&rarr;</b> on it to jump straight to the order instead. The board also
                refreshes itself quietly in the background, so a change a colleague makes appears without you reloading.</li>

            <li><b>Everyone, or just you.</b> Under the subtitle is a rounded toggle with two halves, <b>Everyone</b> and
                <b>Just me</b>. Everyone is the whole team&rsquo;s diary; Just me shows only the jobs assigned to you. If a
                login is not allowed to see other people&rsquo;s work, <b>that toggle is not there at all</b> and the subtitle
                reads &ldquo;<em>Your appointments.</em>&rdquo; &mdash; that is the permission doing its job, not a fault. A fitter
                can also be set to <b>see fittings only</b>, so their calendar looks half empty on purpose. And if Just me turns up
                blank you get told so plainly: &ldquo;<em>No appointments assigned to you in this 6-week window.</em>&rdquo;</li>

            <li><b>The two small buttons on every card.</b> <b>&#128221;</b> opens <b>Appointment note</b> &mdash; &ldquo;<em>A
                short reminder for the day &mdash; e.g. &ldquo;tap gently, baby asleep&rdquo;.</em>&rdquo; &mdash; up to 280
                characters, with <b>Save</b>, <b>Remove</b> and <b>Cancel</b>. <b>&#9888;&#65039;</b> opens <b>Flag an issue</b>
                &mdash; &ldquo;<em>What&rsquo;s the problem? &mdash; e.g. &ldquo;wrong colour delivered&rdquo;, &ldquo;no
                access&rdquo;, &ldquo;remake needed&rdquo;.</em>&rdquo; &mdash; and <b>Flag as issue</b> rings that card in red.
                The <b>&#9888;&#65039; Issues (N)</b> pill at the end of the legend counts them, and clicking it filters the
                board down to just the flagged jobs. Click it again to come back.</li>

            <li><b>Fittings arrive on their own, in the tray.</b> When a customer accepts a quote the app writes the install for
                you and parks it, <em>with no date on it</em>, in the yellow <b>Pending Fitting</b> panel above the grid:
                &ldquo;<em>Drag a card onto a date to schedule it.</em>&rdquo; Nothing pings you, so glance at this tray every
                morning. Empty, it reads &ldquo;<em>Nothing pending. Accepted quotes land here until you place them on a
                date.</em>&rdquo; The Week and Day views cannot show the tray, so they nag instead:
                &ldquo;<em>1 fitting pending &mdash; place it on the Month calendar to schedule.</em>&rdquo;</li>

            <li><b>Drag it onto the day you agreed.</b> Pick the card up, drop it on the date, and a small <b>Set fitting time</b>
                box appears with a time box already showing <b>09:00</b>; change it if you need to, then press <b>Schedule</b>
                (<b>Enter</b> does the same, <b>Esc</b> leaves it in the tray). Moving a job that is <em>already</em>
                dated is the same drag, and it changes the <b>date only</b> &mdash; the time, the length, the fitter and the
                status all stay put. Drag a card back onto the tray to take it off the diary again. Anything more than a new
                date &mdash; a different time or duration &mdash; is the <b>Edit</b> button on the appointment, then <b>Save
                changes</b>. And the fitter is <em>not</em> chosen in that little popup: open the card and use <b>Assigned to</b>
                then <b>Save</b>.</li>

            <li><b>Book a measure visit: who it is for.</b> <b>+ Book Appointment</b> (top right) is for the visit you make
                <em>before</em> there is an order. The form has three boxed sections. <b>Customer</b> asks for <b>Name</b> &mdash;
                the only thing it insists on &mdash; then <b>Email</b>, <b>Phone (landline)</b> and <b>Mobile</b>, with a tick
                under the mobile for <b>&ldquo;Mobile is on WhatsApp&rdquo;</b>. <b>Installation address</b> is where the blinds
                actually go: if your account has the lookup add-on you get <b>Find by postcode</b> (it shows
                <code>e.g. BS1 4ST</code>), a <b>Find address</b> button and a <b>Pick an address</b> list; otherwise type
                <b>Address line 1</b>, <b>Address line 2</b>, <b>Town</b>, <b>County</b> and <b>Postcode</b> yourself. Only tick
                <b>&ldquo;Different billing address?&rdquo;</b> when the bill genuinely goes elsewhere &mdash; five more boxes
                open underneath for it.</li>

            <li><b>Then when &mdash; the window.</b> In the <b>Appointment</b> section, with <b>Morning / afternoon booking
                slots</b> switched on, you set the <b>Date</b>, pick who it is <b>Assigned to</b>, and choose a <b>Time slot</b>:
                two cards each with a <b>radio button</b>, <b>Morning</b> and <b>Afternoon</b>, showing your own times and how
                many places are left (<em>&ldquo;3 of 4 left&rdquo;</em>). The app says it under them:
                &ldquo;<em>The customer is given this window, never an exact time.</em>&rdquo; That is the point &mdash; a job
                that overruns does not make you late for the next one. <b>&ldquo;Email the customer their appointment window
                (needs an email above)&rdquo;</b> is <b>ticked already</b>, so unless you untick it they get it in writing, with
                the line &ldquo;<em>We&rsquo;ll aim to be with you within that window rather than at a fixed time.</em>&rdquo;
                Add any <b>Notes</b>, press <b>Book appointment</b>, and the calendar returns with
                &ldquo;<em>Appointment booked for Angela Reed on 22 Aug 2026, Morning (9am&ndash;1pm).</em>&rdquo;</li>

            <li><b>Finish the job off.</b> When the install is done, click the card and, in the <b>When &amp; status</b> section,
                set <b>Update status:</b> to <b>Completed</b> and press <b>Save status</b> (the choices are <b>Booked</b>,
                <b>Completed</b>, <b>Cancelled</b> and <b>No-show</b>). That one change carries the order itself on to
                <b>Fitted</b>: &ldquo;<em>Status updated to completed. Linked quote ABC-2026-0042 advanced to
                &ldquo;fitted&rdquo;.</em>&rdquo; If the visit never happened, use <b>Cancelled</b> or <b>No-show</b> rather than
                the <b>Danger zone</b> at the foot of that page &mdash; &ldquo;<em>Deleting this appointment is permanent. The
                customer record and any linked quotes will be kept; only the calendar entry is removed.</em>&rdquo;</li>
          </ul>

          <div class="oops"><b>Two different &ldquo;full&rdquo; messages &mdash; and only one of them lets you off.</b>
             Two people can grab the last place at the same moment, so the app checks again when you save. On the booking form
             you get <em>&ldquo;Morning (9am&ndash;1pm) is fully booked on 22 Aug 2026. Please choose the other window or another
             day.&rdquo;</em> &mdash; pick the window still showing places, or change the date and the counts refresh on the spot.
             If instead you <b>drag</b> a windowed visit onto a full day, it is a hard stop: <em>&ldquo;Morning (9am&ndash;1pm) is
             full on 22 Aug 2026. Move it to another day or window.&rdquo;</em> and the card snaps back &mdash; there is no
             &ldquo;do it anyway&rdquo;. A full window is also drawn <b>faded</b>, its count turns <b>amber and bold</b> and its
             radio button is <b>switched off</b>, so clicking it does nothing at all. <b>What counts towards a window:</b> only
             window-booked quote visits. Cancelled and no-show bookings give their place back, and <b>fittings never count</b>.
             Other things it will stop you on: <em>&ldquo;Customer name is required.&rdquo;</em>,
             <em>&ldquo;Please enter a valid email address.&rdquo;</em>, <em>&ldquo;Please choose Morning or Afternoon.&rdquo;</em>,
             <em>&ldquo;Please choose a valid appointment date.&rdquo;</em> and, with slots off,
             <em>&ldquo;Please choose a valid appointment time.&rdquo;</em> or
             <em>&ldquo;Duration must be between 5 and 1440 minutes.&rdquo;</em></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Booking a measure visit creates a brand-new customer.</b>
             Saving that form writes a <b>new customer record</b>, with the installation address saved as their address. So if
             the person is already on the system, start from their customer record instead &mdash; otherwise you end up with two
             of them. <b>And the money line:</b> ticking <b>&ldquo;&#128183; Show order value + balance on the calendar&rdquo;</b>
             in <em>Settings &rarr; Calendar</em> prints the value, what has been received and the balance on every card &mdash;
             and the app warns you there that it <b>ignores each person&rsquo;s &ldquo;Can view costs&rdquo; setting</b>. Anyone
             who can open the calendar will see the figures.</div></div>

          <p><b>If you have not switched the windows on</b>, the Appointment section looks different: a four-across row of
             <b>Date</b>, an exact <b>Time</b> &mdash; a box you type into, showing <code>HH:MM (e.g. 09:30)</code> until you do,
             with the half-hour list from <b>08:00</b> to <b>18:00</b> dropping down under it and filtering as you type
             (&ldquo;<em>No common slot &mdash; type any HH:MM you like.</em>&rdquo; if what you typed is not on it) &mdash; a
             <b>Duration (mins)</b> number box starting at <b>60</b> and stepping in fives (anything from 5 to 1440), and
             <b>Assigned to</b>. In that mode the app
             also watches for clashes: <em>&ldquo;Dave Cole is already booked 09:00&ndash;10:00 (Angela Reed) that day &mdash;
             they can&rsquo;t be in two places at once. Pick another time, assignee, or day.&rdquo;</em> &mdash; with a
             <b>Book anyway</b> button if you really do mean it. The <b>Assigned to</b> list is picked for the job in hand: a
             measure offers your <b>sales</b> people, a fitting offers your <b>fitters</b> (and everyone, if nobody holds the
             role). Whichever mode you are in, the very first line of that list is <b>&ldquo;&mdash; Unassigned &mdash;&rdquo;</b>.
             A new booking preselects <em>you</em> if you do measures, or your only salesperson if you have just the one &mdash;
             and if neither applies (several sales people, and you are not one of them) it opens on
             <b>&mdash; Unassigned &mdash;</b>, so remember to set it before you save.
             You change the times, the length of each window and the <b>Bookings / day</b> limit &mdash; Morning and Afternoon
             have <b>separate</b> limits &mdash; in <a href="/help/guide.php?g=settings-calendar"><b>Settings &rarr;
             Calendar</b></a>.</p>

          <p>One last thing worth knowing: if you mark a job <b>Completed</b> and then change it to <b>Cancelled</b> or
             <b>No-show</b>, the appointment page notices the order is still sitting at Fitted and says so &mdash;
             <em>&ldquo;Status mismatch: this appointment is cancelled but its linked quote ABC-2026-0042 is still marked as
             fitted.&rdquo;</em> &mdash; with a <b>Rewind quote &raquo;</b> button. Use it only if the install really did not
             happen (<em>&ldquo;Quote ABC-2026-0042 rewound from &ldquo;fitted&rdquo; back to &ldquo;ordered&rdquo;.&rdquo;</em>);
             if the work was done and this is only paperwork, leave it be. Once the order has moved on you cannot rewind anyway:
             <em>&ldquo;Could not rewind &mdash; quote may already have moved past &ldquo;fitted&rdquo; (invoiced / paid).&rdquo;</em></p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'The board: six weeks from a Monday.', 'This is your diary. Every job on it is a card, coloured by the stage it has reached — the same colours you picked in Settings. Fittings carry a dark outline, so you can tell an install from a measure at a glance. And look carefully: this is not a calendar month. It is six weeks starting on a Monday, so the little arrows step you a week at a time, and the Today button brings you back. Each square is labelled day and month, like twenty-one oh eight.', 1],
            ['0:24', 'Everyone or Just me; the Issues filter.', 'Under the heading there are two halves to a little toggle. Everyone shows the whole team. Just me shows only your own jobs. If somebody is not allowed to see other people work, that toggle is not there at all and they only ever see their own — that is not a fault. A fitter can also be set to see fittings only, so their calendar looks emptier on purpose. And over on the right of the legend, the Issues pill: click it and the board shows only the jobs someone has flagged as a problem.', 2],
            ['0:47', 'The Pending Fitting tray.', 'When a customer accepts a quote, the app writes the install for you and parks it here, in the Pending Fitting tray, with no date on it. Nothing pings you, so have a look at this tray each morning. When there is nothing waiting it simply says: nothing pending, accepted quotes land here until you place them on a date. The Week and Day views cannot hold the tray, so they nudge you instead — one fitting pending, place it on the Month calendar to schedule.', 3],
            ['1:10', 'Drag onto a day; set the time.', 'Drag the card onto the day you have agreed, and a little box asks you to set the fitting time. It starts at nine. Press Schedule, and it is on the diary. Moving a job that is already booked to another day is the same drag, and it changes the date only — the time, the length, the fitter and the status all stay exactly as they were. Drag a card back onto the tray to take it off the diary again. One thing: the fitter is not chosen here. Open the card and use Assigned to, then Save.', 4],
            ['1:36', 'Book Appointment: who, and where.', 'Book Appointment, top right, is for the visit you make before there is an order — the measure. The name is the only thing it insists on; everything else is there so whoever goes out has what they need. Tick Mobile is on WhatsApp if that is how they like to be reached. The installation address is where the blinds go — type the postcode, press Find address, and pick the right line if you have the lookup. Only tick different billing address when the bill really does go somewhere else. And do please note: saving this makes a brand new customer record with this address on it, so if they are already on the system, start from the customer instead of typing them in twice.', 5],
            ['2:10', 'Pick the Morning or Afternoon window.', 'Now the Appointment box. If you have turned on Morning and Afternoon booking slots, the visit goes into a half day window instead of an exact time. The customer is told afternoon, never ten past two, so a job that overruns does not make you late. Each window holds a set number of visits a day, and the counter tells you how many are left. Morning here says Full, and its button will not take a click at all. So pick Afternoon — or change the date, and the counts refresh on the spot. Leave the email box ticked and they get their window in writing.', 6],
            ['2:38', 'When it will not save; and slots off.', 'Two people can grab the same last place at once, so the app checks again when you save. If you see that it is fully booked, somebody beat you to it — choose the other window, or another day. The same thing happens if you drag a windowed visit onto a full day, except there is no way round it: the card simply jumps back. And if you leave the slots switch off, this box looks different — an exact time, typed or picked from the half hour list, and a duration in minutes, sixty to start with. You change the window times and the daily limits in Settings, then Calendar, where Morning and Afternoon each have their own.', 7],
            ['3:08', 'Mark it Completed; the order goes Fitted.', 'When the install is done, open the card and set the status to Completed. That one change moves the order itself on to Fitted, so you are not doing the same job twice. If the visit did not happen, use Cancelled or No show rather than deleting it — delete is permanent, and the diary entry is gone for good. And finally, those two small buttons on every card: the notepad for a reminder for the day, like tap gently, baby asleep; and the warning triangle to flag a problem, which rings the card in red and can be filtered to from the legend.', 8],
        ],
];
