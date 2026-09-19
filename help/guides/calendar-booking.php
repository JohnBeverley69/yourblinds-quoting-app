<?php
declare(strict_types=1);

/**
 * Guide: calendar-booking
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Calendar',
        'title'   => 'Calendar & booking',
        'eyebrow' => 'Calendar',
        'blurb'   => 'The diary: jobs colour-coded by stage, the Pending Fitting tray you drag onto a date, AM/PM measure windows, and completing a fitting.',
        'lede'    => 'The <b>Calendar</b> is your diary &mdash; every job a card, <b>colour-coded by its stage</b>. Accepted quotes drop a
                      <b>fitting</b> into the <b>Pending</b> tray to <b>drag onto a date</b>; <b>measure visits</b> you book yourself (into a
                      <b>Morning / Afternoon window</b>, if you&rsquo;ve turned those on, or an exact time); and completing a fitting nudges the
                      order on to <b>Fitted</b>.',
        'open'    => '/calendar/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scBoard, .gd .stage[data-step="2"] .scBoard{ display:block; }
          .gd .stage[data-step="3"] .scSched, .gd .stage[data-step="4"] .scSched{ display:block; }
          .gd .stage[data-step="5"] .scMeasure{ display:block; }
          .gd .stage[data-step="6"] .scDone{ display:block; }

          /* board header */
          .gd .calhd{ display:flex; align-items:center; gap:.5rem; margin-bottom:.55rem; }
          .gd .calhd .mo{ font-weight:700; color:var(--ink); font-size:.9rem; }
          .gd .vsw{ display:inline-flex; border:1px solid var(--line); border-radius:7px; overflow:hidden; font-size:.66rem; }
          .gd .vsw span{ padding:.16rem .5rem; color:var(--soft); }
          .gd .vsw span.on{ background:var(--accent); color:#fff; }
          .gd .bookbtn{ margin-left:auto; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.32rem .65rem; font-size:.72rem; font-weight:700; }

          /* pending tray */
          .gd .ptray{ border:1px solid #f0c674; background:rgba(240,198,116,.14); border-radius:9px; padding:.5rem .65rem; margin-bottom:.6rem; }
          .gd .ptray .ph2{ font-weight:700; color:var(--ink); font-size:.76rem; }
          .gd .ptray .phint{ font-size:.64rem; color:var(--faint); margin:.1rem 0 .4rem; }
          .gd .pcard{ display:inline-block; background:var(--surface); border:1px solid var(--line); border-radius:7px; padding:.35rem .55rem; font-size:.7rem; color:var(--ink); }
          .gd .pcard .pm{ color:var(--faint); font-size:.62rem; }
          .gd .stage[data-step="2"] .pcard{ box-shadow:0 0 0 3px var(--accent-wash); transform:translateX(3px) rotate(-1deg); }

          /* mini month grid */
          .gd .mcal{ display:grid; grid-template-columns:repeat(7,1fr); gap:2px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .mc{ background:var(--surface); min-height:2.6rem; padding:.15rem .2rem; font-size:.56rem; color:var(--faint); position:relative; }
          .gd .appt{ display:block; border-radius:4px; padding:.08rem .25rem; font-size:.56rem; color:#fff; margin-top:.12rem; white-space:nowrap; overflow:hidden; }
          .gd .appt.fit{ box-shadow:inset 0 0 0 2px rgba(0,0,0,.55); }

          /* set-time popup + fitter */
          .gd .tpop{ display:none; border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); max-width:15rem; }
          .gd .stage[data-step="3"] .tpop{ display:block; }
          .gd .tpop .tt{ font-weight:700; color:var(--ink); font-size:.74rem; margin-bottom:.4rem; }
          .gd .tin{ display:inline-flex; align-items:center; border:1px solid var(--line); border-radius:6px; padding:.25rem .5rem; font-size:.78rem; color:var(--ink); background:var(--surface); }
          .gd .tbtns{ display:flex; gap:.4rem; margin-top:.5rem; }
          .gd .tbtn{ display:inline-flex; border-radius:6px; padding:.28rem .6rem; font-size:.72rem; font-weight:600; }
          .gd .tbtn.go{ background:var(--nav); color:#fff; }
          .gd .tbtn.no{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .fitrow{ display:none; margin-top:.65rem; }
          .gd .stage[data-step="4"] .fitrow{ display:block; }

          /* measure / AM-PM */
          .gd .ampm{ display:flex; gap:.55rem; }
          .gd .ao{ flex:1; border:1px solid var(--line); border-radius:9px; padding:.5rem .6rem; font-size:.72rem; }
          .gd .ao .an{ font-weight:700; color:var(--ink); }
          .gd .ao .ar{ color:var(--faint); font-size:.64rem; }
          .gd .ao .ac{ font-size:.64rem; margin-top:.25rem; font-weight:600; }
          .gd .ao.full{ opacity:.55; }
          .gd .ao.full .ac{ color:#b91c1c; }
          .gd .ao.pick{ box-shadow:0 0 0 3px var(--accent-wash); border-color:var(--accent); }
          .gd .ao.pick .ac{ color:#166534; }

          /* done / status */
          .gd .selrow{ display:flex; align-items:center; gap:.5rem; font-size:.76rem; color:var(--ink); }

          .gd .note{ font-size:.68rem; color:var(--faint); margin-top:.55rem; }
          .gd .note b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / calendar</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a class="on">Calendar</a><a>Customers</a><a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: the board -->
                <div class="osc scBoard">
                  <div class="calhd">
                    <span class="mo">August 2026</span>
                    <span class="vsw"><span class="on">Month</span><span>Week</span><span>Day</span></span>
                    <span class="bookbtn">+ Book appointment</span>
                  </div>
                  <div class="ptray">
                    <div class="ph2">Pending Fitting <span style="color:var(--faint);font-weight:400">(1)</span></div>
                    <div class="phint">Drag a card onto a date to schedule it.</div>
                    <span class="pcard">Install: PRE-2026-0042 &mdash; Emma Fletcher<br><span class="pm">Leamington Spa &middot; CV32 5PJ</span></span>
                  </div>
                  <div class="mcal">
                    <span class="mc">11</span><span class="mc">12<span class="appt" style="background:#16a34a">Measure &mdash; Reed</span></span><span class="mc">13</span><span class="mc">14</span><span class="mc">15<span class="appt fit" style="background:#0d9488">Fit &mdash; Cole</span></span><span class="mc">16</span><span class="mc">17</span>
                    <span class="mc">18</span><span class="mc">19<span class="appt" style="background:#2563eb">Measure &mdash; Shah</span></span><span class="mc">20</span><span class="mc">21</span><span class="mc">22</span><span class="mc">23</span><span class="mc">24</span>
                  </div>
                  <p class="note">Card colour = the job&rsquo;s <b>stage</b> (your Status colours). <b>Fittings</b> carry a dark outline; measures don&rsquo;t.</p>
                </div>

                <!-- Scene: schedule from the tray -->
                <div class="osc scSched">
                  <p class="ldesc2">Dropped &ldquo;Install: PRE-2026-0042&rdquo; onto <b>Thu 21 Aug</b>&hellip;</p>
                  <div class="tpop">
                    <div class="tt">Set fitting time</div>
                    <span class="tin">&#128337; 09:00</span>
                    <div class="tbtns"><span class="tbtn no">Cancel</span><span class="tbtn go">Schedule</span></div>
                  </div>
                  <div class="fitrow">
                    <div class="fld"><label>Assigned to</label><div class="selectbox">Dave (Fitter)</div></div>
                    <p class="note">Left on <b>&mdash; Unassigned &mdash;</b>? It shows in the Day view&rsquo;s <b>&#9888; Unassigned</b> tray &mdash; open it and pick a fitter.</p>
                  </div>
                </div>

                <!-- Scene: measure visit + AM/PM -->
                <div class="osc scMeasure">
                  <div class="card-t">Book appointment &mdash; measure visit</div>
                  <div class="frow">
                    <div class="fld"><label>Name</label><div class="box"><span>Angela Reed</span></div></div>
                    <div class="fld"><label>Date</label><div class="box"><span>Fri 22 Aug 2026</span></div></div>
                  </div>
                  <div class="fld" style="margin-top:.55rem"><label>Time slot</label>
                    <div class="ampm">
                      <div class="ao full"><span class="an">Morning</span> <span class="ar">(9am&ndash;1pm)</span><div class="ac">Full</div></div>
                      <div class="ao pick"><span class="an">Afternoon</span> <span class="ar">(1pm&ndash;5pm)</span><div class="ac">3 of 4 left</div></div>
                    </div>
                  </div>
                  <p class="note">With <b>Morning / Afternoon slots</b> turned on (Settings &rarr; Calendar), the customer gets a <b>window</b>, never an exact time &mdash; here Morning&rsquo;s full, so pick <b>Afternoon</b> (or another day). With slots <b>off</b>, you set an exact <b>time &amp; duration</b> instead.</p>
                </div>

                <!-- Scene: complete the fitting -->
                <div class="osc scDone">
                  <div class="card-t">Install: PRE-2026-0042 &mdash; Emma Fletcher</div>
                  <div class="selrow" style="margin:.3rem 0 .6rem"><span>Update status:</span><div class="selectbox" style="min-width:9rem">Completed</div><span class="tbtn go">Save status</span></div>
                  <div class="okbanner"><span>&check;</span> Status updated to completed. Linked quote PRE-2026-0042 advanced to &ldquo;fitted&rdquo;.</div>
                  <p class="note">Completing the fitting moves the order to <b>Fitted</b> for you. (Cancelled it by mistake? A prompt lets you rewind the quote.)</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Your calendar &mdash; jobs colour-coded by stage.</b>
                  <b class="c2"><span class="n">2</span> Accepted quotes wait in the Pending Fitting tray.</b>
                  <b class="c3"><span class="n">3</span> Drag onto a day &rarr; set the fitting time.</b>
                  <b class="c4"><span class="n">4</span> Assign a fitter so it&rsquo;s not left loose.</b>
                  <b class="c5"><span class="n">5</span> Measure visits get a Morning / Afternoon window.</b>
                  <b class="c6 good"><span class="n">6</span> Mark it Completed &rarr; the order goes Fitted.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Calendar</b> (in the sidebar) is your diary. It opens on the <b>Month</b> board &mdash; a rolling six-week grid &mdash; with
             <b>Week</b> and <b>Day</b> views a click away. Every appointment is a card <b>coloured by the job&rsquo;s stage</b> (the very
             colours you set in <em>Settings &rarr; Status colours</em>); <b>fittings</b> carry a <b>dark outline</b>, measures don&rsquo;t.</p>
          <ul class="steps">
            <li><b>Fittings arrive in the Pending tray.</b> When a quote is accepted, a fitting card &mdash; <b>&ldquo;Install: &lt;number&gt; &mdash;
                &lt;name&gt;&rdquo;</b> &mdash; drops into the yellow <b>Pending Fitting</b> panel at the top of the Month view. It isn&rsquo;t on a
                date yet, and nothing pings you &mdash; so keep an eye here.</li>
            <li><b>Drag it onto a day.</b> Drag the card onto the target date; a little <b>&ldquo;Set fitting time&rdquo;</b> box pops up (defaults
                to 09:00) &mdash; set it and hit <b>Schedule</b>. Now it&rsquo;s on the calendar. (Drag it back to the tray to un-schedule.)</li>
            <li><b>Assign a fitter.</b> Open the appointment and pick a fitter under <b>Assigned to</b> (it only auto-assigns when you have exactly
                one fitter). Left <b>&mdash; Unassigned &mdash;</b>, it sits in the <b>Day</b> view&rsquo;s <b>&#9888; Unassigned</b> column as
                &ldquo;needs a fitter assigned&rdquo;.</li>
            <li><b>Book a measure visit.</b> Click <b>+ Book appointment</b> for a survey/measure. If you&rsquo;ve switched on
                <b>Morning / Afternoon slots</b> (in <b>Settings &rarr; Calendar</b>), the customer gets a <b>window</b> &mdash; never an exact
                time &mdash; and each shows how many are left (<em>&ldquo;3 of 4 left&rdquo;</em>); you can email them their window. <b>With slots
                off</b>, you pick an exact <b>time</b> and <b>duration</b> instead. <em>(Fittings never use windows &mdash; always a real time.)</em></li>
            <li><b>Complete it.</b> Open the fitting and set <b>Update status &rarr; Completed</b>. That <b>advances the linked order to Fitted</b>
                automatically &mdash; &ldquo;<em>Linked quote &lt;ref&gt; advanced to &lsquo;fitted&rsquo;</em>&rdquo;. (Cancel/no-show it while the
                quote&rsquo;s still Fitted and a banner offers to rewind it.)</li>
          </ul>
          <div class="oops"><b>Window full?</b> If Morning (or Afternoon) has hit its limit it shows <b>Full</b> and greys out; saving into it
             (or dragging a measure onto that full day) gives <em>&ldquo;&hellip; is fully booked on &lt;date&gt;. Please choose the other window
             or another day.&rdquo;</em> <b>Fix:</b> pick the window still showing <em>&ldquo;N left&rdquo;</em>, or change the date &mdash; the
             counts update live. Adjust each window&rsquo;s <b>times and daily limit</b> in <b>Settings &rarr; Calendar</b>.</div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Don&rsquo;t leave a fitting stranded in the tray.</b> A pending fitting never
             shows on the grid until you place it, and Week/Day nag with &ldquo;<em>N fittings pending</em>&rdquo;. Drag it onto a day as soon as
             the install&rsquo;s arranged. Also: turning on <b>&ldquo;Show order value + balance&rdquo;</b> (Settings &rarr; Calendar) prints the
             money on each card &mdash; handy for the fitter, but it shows to <b>everyone</b> who can open the calendar.</div></div>
          <p><b>Double-booked a fitter?</b> Assigning a clashing time warns <em>&ldquo;&hellip; is already booked &hellip; they can&rsquo;t be in
             two places at once&rdquo;</em> and lets you <b>Book anyway</b> or pick another time/person. And deleting an appointment is permanent &mdash;
             to just record it didn&rsquo;t happen, set the status to <b>Cancelled</b> or <b>No-show</b> instead.</p>',
        'script'  => [
            ['0:00', 'The calendar board.',                'Your calendar is the diary. Every job is a card, colour-coded by its stage — the same status colours you set up — and you flip between Month, Week and Day.', 1],
            ['0:10', 'Pending Fitting tray.',              'When a quote is accepted, its fitting lands here in the Pending tray — Install, the order number, the customer — waiting for you to place it.', 2],
            ['0:19', 'Drag onto a day; set the time.',     'Drag the card onto a day and set the fitting time. That schedules it onto the calendar.', 3],
            ['0:26', 'Assign a fitter.',                   'Open it and pick a fitter, so it is not left sitting unassigned in the Day view.', 4],
            ['0:33', 'Measure visit; AM/PM window.',       'Booking a measure visit is a bit different. If you\'ve turned on Morning and Afternoon slots in Settings, you give the customer a window, not an exact time — and if one is full, pick the other, or another day. With slots off, you just set an exact time.', 5],
            ['0:44', 'Mark it Completed → Fitted.',        'When the install is done, mark the appointment Completed — and the linked order moves itself on to Fitted.', 6],
        ],
];
