<?php
declare(strict_types=1);

/**
 * Guide: dashboard-tour
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the DASHBOARD SCREEN itself (/dashboard/index.php) — the period bar,
 * the custom date range, the salesperson filter, and all six panels: Upcoming
 * jobs, the four KPI tiles, Sales team, What's selling, Gross profit and
 * Recent wins. Plus the three things people always read wrong: the window is
 * measured on quotes.created_at (not accepted_at), Revenue (won) is
 * VAT-inclusive and carries the Wally tax (WT charge) while Gross profit is
 * net of both, and the five per-panel permissions on /admin/users_edit.php.
 *
 * (settings-dashboard covers the Joke of the day SETTING; this covers the
 * screen the joke sits on.)
 */

return [
        'aud'     => 'admin',
        'section' => 'Dashboard',
        'title'   => 'Reading your dashboard',
        'eyebrow' => 'Dashboard',
        'blurb'   => 'Every panel on the Dashboard, what it is counting, and the two numbers people always read wrong.',
        'lede'    => 'The Dashboard is your <b>scoreboard</b>, not a to-do list. Apart from
                      <b>Upcoming jobs</b>, every single thing on it is answering one question &mdash;
                      <em>how did we do in this window of time?</em> &mdash; and you choose that window
                      with the row of buttons across the top. Here is the bit that catches everybody
                      out, so it is worth reading twice: the window counts a job by the day the
                      <b>quote was started</b>, not the day the customer said yes. Work through this
                      once and the numbers will stop arguing with you.',
        'open'    => '/dashboard/index.php',
        'css'     => '
          /* ---------- the spotlight: the narrated panel lifts, the rest stays readable ----------
             A hard dim (opacity .24 + desaturate) made this tall page look dead and
             "washed out" — and because the first narration line runs ~50s, it sat like
             that long enough to look broken. Keep the page readable (gentle .62) and put
             a clear ring on the ACTIVE panel so the focus is obvious and every step is
             visibly a MOVE, not a static grey screen. */
          .gd .zone{ transition:opacity .3s, box-shadow .3s; border-radius:10px; }
          .gd .stage:not([data-step="0"]) .zone{ opacity:.62; }
          .gd .stage[data-step="1"] .z1, .gd .stage[data-step="2"] .z2,
          .gd .stage[data-step="3"] .z3, .gd .stage[data-step="4"] .z4,
          .gd .stage[data-step="5"] .z5, .gd .stage[data-step="6"] .z6,
          .gd .stage[data-step="7"] .z7, .gd .stage[data-step="8"] .z8{
              opacity:1; box-shadow:0 0 0 2px var(--accent), 0 10px 26px -12px rgba(37,99,235,.5);
          }

          /* ---------- sidebar group heading (the real nav is grouped Work / Retail / …) ---------- */
          .gd .navh{ font-size:.56rem; letter-spacing:.12em; text-transform:uppercase; color:#6a7d8c;
                     font-weight:700; margin:.7rem 0 .15rem; padding:0 .5rem; }

          /* ---------- page header ---------- */
          .gd .dhead{ display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; }
          .gd .pgh{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .pgs{ font-size:.7rem; color:var(--faint); margin:.1rem 0 .55rem; }
          .gd .pgs b{ color:var(--soft); }
          .gd .subfor{ display:none; }
          .gd .stage[data-step="4"] .subfor{ display:inline; }
          /* the real "+ New quote" is a SOLID primary button, not a ghost link */
          .gd .newq{ background:var(--brand,var(--accent)); color:#fff; border-radius:8px; padding:.28rem .6rem;
                     font-size:.7rem; font-weight:700; white-space:nowrap; }

          /* ---------- joke of the day (amber strip) ---------- */
          .gd .jotd{ display:flex; align-items:center; gap:.5rem; background:linear-gradient(90deg,#fffbeb,#fef9c3);
                     border:1px solid #fde68a; border-radius:9px; padding:.35rem .5rem; margin-bottom:.6rem; }
          :root[data-theme="dark"] .gd .jotd{ background:rgba(250,204,21,.08); border-color:rgba(250,204,21,.3); }
          .gd .jemo{ font-size:1rem; line-height:1; }
          .gd .jlbl{ font-size:.54rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:#b45309; }
          :root[data-theme="dark"] .gd .jlbl{ color:#fbbf24; }
          .gd .jtxt{ font-size:.68rem; color:var(--ink); }
          .gd .jbtn{ font-size:.62rem; color:#b45309; border:1px solid transparent; border-radius:7px; padding:.1rem .3rem; }
          :root[data-theme="dark"] .gd .jbtn{ color:#fbbf24; }

          /* ---------- period bar: RECTANGULAR anchor-buttons, 8px radius (not .pill lozenges) ---------- */
          .gd .pbar{ display:flex; flex-wrap:wrap; gap:.3rem; align-items:center; margin-bottom:.55rem; }
          .gd .pgrp{ display:inline-flex; flex-wrap:wrap; gap:.3rem; align-items:center; }
          .gd .pbtn{ display:inline-flex; align-items:center; border:1px solid var(--border-strong,#c7ccd4);
                     border-radius:8px; padding:.22rem .5rem; font-size:.68rem; font-weight:500;
                     color:var(--soft); background:var(--surface); white-space:nowrap; }
          .gd .pbtn.on{ background:var(--brand,var(--accent)); border-color:var(--brand,var(--accent)); color:#fff; }
          /* the custom range sits in its own bordered inline strip, labels to the LEFT */
          .gd .crange{ display:inline-flex; align-items:center; gap:.3rem; padding:.18rem .35rem;
                       background:var(--panel); border:1px solid var(--line); border-radius:8px; }
          .gd .dlbl{ font-size:.56rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
          .gd .dbox{ height:22px; width:5.6rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                     background:var(--surface); display:flex; align-items:center; padding:0 .35rem;
                     font-size:.66rem; color:var(--ink); overflow:hidden; }
          .gd .dbox .val{ padding:0 .35rem; font-size:.66rem; }
          .gd .applybtn{ background:var(--brand,var(--accent)); color:#fff; border-radius:6px;
                         padding:.16rem .45rem; font-size:.64rem; font-weight:700; }

          /* ---------- person filter: a real select, label "View:" to its left, own row ---------- */
          .gd .pfilter{ display:inline-flex; align-items:center; gap:.4rem; padding:.22rem .45rem;
                        background:var(--panel); border:1px solid var(--line); border-radius:8px; margin-bottom:.6rem; }
          .gd .pfilter .selectbox{ min-width:8.6rem; padding:.18rem .4rem; font-size:.7rem; }
          .gd .seljane{ display:none; }
          .gd .stage[data-step="4"] .selall{ display:none; }
          .gd .stage[data-step="4"] .seljane{ display:inline; }

          /* ---------- shared panel shell ---------- */
          .gd .pnl{ border:1px solid var(--line); border-radius:10px; background:var(--surface);
                    padding:.5rem .6rem; margin-bottom:.55rem; }
          .gd .pnl h4{ margin:0; font-size:.78rem; font-weight:700; color:var(--ink); }
          .gd .psub{ font-size:.6rem; color:var(--faint); margin:.1rem 0 .4rem; }
          .gd .pfoot{ font-size:.62rem; color:var(--accent); font-weight:600; margin-top:.3rem; }

          /* ---------- upcoming jobs: four-column whole-row links ---------- */
          .gd .uprow{ display:grid; grid-template-columns:3.5rem 1fr 4.6rem 4.8rem; gap:.4rem; align-items:center;
                      padding:.24rem .2rem; border-bottom:1px solid var(--line-2); font-size:.66rem; }
          .gd .uprow:last-of-type{ border-bottom:0; }
          .gd .update{ font-weight:700; color:var(--ink); font-size:.64rem; }
          .gd .update.today{ color:#ef4444; }
          .gd .uptime{ color:var(--faint); font-size:.58rem; }
          .gd .upname{ font-weight:600; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .upplace{ color:var(--faint); font-size:.58rem; }
          .gd .upfit{ color:var(--soft); font-size:.62rem; }
          .gd .upfit i{ color:var(--faint); }
          .gd .upq{ display:flex; align-items:center; gap:.25rem; }
          /* the real dashboard has no coloured lozenge here: .up-quote .status-pill only sets
             size/padding and app.css carries no base .status-pill rule, so the status word
             renders as plain small text beside the quote number. Drawn that way on purpose. */
          .gd .spill{ font-size:.52rem; padding:.04rem .2rem; color:var(--ink); }
          .gd .upqn{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; color:var(--faint); font-size:.56rem; }

          /* ---------- KPI tiles: four bordered cards, big value, grey sub-line ---------- */
          .gd .kgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; margin-bottom:.55rem; }
          .gd .ktile{ border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.4rem .5rem; }
          .gd .klbl{ font-size:.53rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .kval{ font-size:.98rem; font-weight:800; color:var(--ink); line-height:1.15; margin-top:.1rem; }
          .gd .ktile.rev .kval{ color:#16a34a; }
          .gd .ktile.rate .kval{ color:#d97706; }
          .gd .ksub{ font-size:.55rem; color:var(--faint); margin-top:.1rem; }

          /* ---------- leaderboard: a genuine table, right-aligned numbers, medals ---------- */
          .gd .lbflex{ display:flex; gap:.7rem; align-items:flex-start; flex-wrap:wrap; }
          .gd .lbwrap{ flex:1 1 17rem; min-width:0; }
          .gd table.lbt{ width:100%; border-collapse:collapse; font-size:.64rem; }
          .gd table.lbt th, .gd table.lbt td{ text-align:left; padding:.2rem .25rem;
                          border-bottom:1px solid var(--line-2); color:var(--soft); }
          .gd table.lbt th{ font-size:.5rem; text-transform:uppercase; letter-spacing:.05em;
                          color:var(--faint); font-weight:700; background:var(--panel); }
          .gd table.lbt td.num, .gd table.lbt th.num{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd table.lbt td.who{ color:var(--ink); font-weight:600; }
          .gd .sharewrap{ flex:0 0 auto; }
          .gd .phead{ font-size:.5rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint);
                      font-weight:700; margin-bottom:.2rem; }
          .gd .stage[data-step="4"] .sharewrap{ display:none; }
          /* Filtered to one person, the panel re-titles itself AND re-words its sub-line
             ("Their numbers" / "…for this salesperson.") — so the mock must swap both. */
          .gd .tmjane, .gd .tsjane{ display:none; }
          .gd .stage[data-step="4"] .tmall, .gd .stage[data-step="4"] .tsall{ display:none; }
          .gd .stage[data-step="4"] .tmjane, .gd .stage[data-step="4"] .tsjane{ display:inline; }

          /* ---------- the red bar you get when neither date parses ---------- */
          /* Sits where the real one does: under the page header, above the period bar.
             Deliberately outside any .zone so the spotlight never dims it. */
          .gd .errdemo{ display:none; font-size:.66rem; padding:.35rem .5rem; margin-bottom:.5rem; }
          .gd .stage[data-step="3"] .errdemo{ display:flex; }

          /* ---------- donut + legend (the real charts are SVG donuts with a legend) ---------- */
          .gd .pwrap{ display:flex; gap:.5rem; align-items:center; }
          .gd .pleg{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:.14rem; font-size:.58rem; }
          .gd .pleg li{ display:grid; grid-template-columns:8px 1fr auto auto; gap:.3rem; align-items:center; }
          .gd .psw{ width:8px; height:8px; border-radius:2px; display:inline-block; }
          .gd .plbl{ color:var(--ink); white-space:nowrap; }
          .gd .pval{ color:var(--soft); font-variant-numeric:tabular-nums; }
          .gd .ppct{ color:var(--faint); font-variant-numeric:tabular-nums; }

          /* ---------- product mix rows, each with its proportional bar ---------- */
          .gd .mixrow{ display:grid; grid-template-columns:1fr 3.4rem 4rem; gap:.35rem; align-items:center;
                       padding:.22rem 0; border-bottom:1px solid var(--line-2); }
          .gd .mixrow:last-child{ border-bottom:0; }
          .gd .mixname{ font-size:.64rem; font-weight:600; color:var(--ink); }
          .gd .mixbar{ position:relative; height:5px; background:var(--panel); border-radius:999px; margin-top:.14rem; }
          .gd .mixbar span{ position:absolute; left:0; top:0; bottom:0; border-radius:999px;
                            background:linear-gradient(90deg,var(--brand,var(--accent)),var(--accent)); }
          .gd .mixnum{ text-align:right; font-size:.6rem; color:var(--soft); font-variant-numeric:tabular-nums; }

          /* ---------- gross profit cells ---------- */
          .gd .mgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; }
          .gd .mval{ font-size:.82rem; font-weight:700; color:#16a34a; font-variant-numeric:tabular-nums; margin-top:.05rem; }
          .gd .mval.cog{ color:#92400e; }

          /* ---------- recent wins rows ---------- */
          .gd .rrow{ display:grid; grid-template-columns:1fr 5.2rem 4.2rem 4rem; gap:.35rem; align-items:center;
                     padding:.2rem .1rem; border-bottom:1px solid var(--line-2); font-size:.63rem; color:var(--soft); }
          .gd .rrow:last-child{ border-bottom:0; }
          .gd .rrow a{ color:var(--accent); font-weight:700; }
          .gd .rdate{ color:var(--faint); font-size:.58rem; }
          .gd .rrev{ text-align:right; color:#16a34a; font-weight:700; font-variant-numeric:tabular-nums; }

          /* The shared fill engine only holds f3 visible up to step 5. This guide
             runs to step 8, so top the From / To dates up for steps 6, 7 and 8 —
             otherwise they blank out halfway through the walkthrough. */
          .gd .stage[data-step="6"] .f3 .ph, .gd .stage[data-step="7"] .f3 .ph,
          .gd .stage[data-step="8"] .f3 .ph{ opacity:0; }
          .gd .stage[data-step="6"] .f3 .val, .gd .stage[data-step="7"] .f3 .val,
          .gd .stage[data-step="8"] .f3 .val{ opacity:1; }
          .gd .stage[data-step="3"] .applybtn{ box-shadow:0 0 0 3px var(--accent-wash); }

          @media(max-width:620px){
            .gd .kgrid, .gd .mgrid{ grid-template-columns:1fr 1fr; }
            .gd .uprow{ grid-template-columns:3.2rem 1fr; }
            .gd .upfit, .gd .upq{ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / dashboard</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a class="on">Dashboard</a><a>Calendar</a><a>Pipeline</a><a>Factory</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 1. header + joke strip -->
                <div class="zone z1">
                  <div class="dhead">
                    <div>
                      <div class="pgh">Dashboard</div>
                      <div class="pgs">Sales at a glance &mdash; this month<span class="subfor"> for <b>Jane Weller</b></span>.</div>
                    </div>
                    <div class="newq">+ New quote</div>
                  </div>
                  <div class="jotd">
                    <span class="jemo">&#128516;</span>
                    <div>
                      <div class="jlbl">Joke of the day</div>
                      <div class="jtxt">Why was the blind so good at its job? It always knew when to draw the line.</div>
                    </div>
                    <div style="margin-left:auto;display:flex;gap:.2rem">
                      <span class="jbtn">&#128257; Another</span><span class="jbtn">&#10005;</span>
                    </div>
                  </div>
                </div>

                <!-- the flash_error bar, exactly where the real one renders -->
                <div class="errbanner errdemo">
                  <span>&#9888;</span><div><b>Bad date format &mdash; use the date pickers.</b></div>
                </div>

                <!-- 2. period buttons + 3. custom range -->
                <div class="pbar">
                  <span class="zone z2 pgrp">
                    <span class="pbtn on">This month</span><span class="pbtn">Last 30 days</span><span class="pbtn">This quarter</span><span class="pbtn">This year</span><span class="pbtn">All time</span>
                  </span>
                  <span class="zone z3 crange">
                    <span class="dlbl">From</span>
                    <span class="box dbox f3"><span class="ph">dd/mm/yyyy</span><span class="val">31/08/2026</span></span>
                    <span class="dlbl">To</span>
                    <span class="box dbox f3"><span class="ph">dd/mm/yyyy</span><span class="val">30/09/2026</span></span>
                    <span class="applybtn">Apply</span>
                  </span>
                </div>

                <!-- 4. person filter -->
                <div class="zone z4 pfilter">
                  <span class="dlbl">View:</span>
                  <span class="selectbox"><span class="selall">All sales team</span><span class="seljane">Jane Weller</span></span>
                </div>

                <!-- 5. upcoming jobs -->
                <div class="zone z5 pnl">
                  <h4>Upcoming jobs</h4>
                  <div class="psub">Next 3 appointments on the calendar &mdash; soonest first.</div>
                  <div class="uprow">
                    <div><div class="update today">Today</div><div class="uptime">2:30pm</div></div>
                    <div><div class="upname">Mrs Halliwell</div><div class="upplace">TA1 3QS</div></div>
                    <div class="upfit">Dave Perry</div>
                    <div class="upq"><span class="spill">accepted</span><span class="upqn">PRE-2026-0042</span></div>
                  </div>
                  <div class="uprow">
                    <div><div class="update">Tomorrow</div><div class="uptime">9:00am</div></div>
                    <div><div class="upname">Bishops Lydeard job</div><div class="upplace">TA4 3BW</div></div>
                    <div class="upfit"><i>Unassigned</i></div>
                    <div class="upq"></div>
                  </div>
                  <div class="uprow">
                    <div><div class="update">Fri 2 Oct</div><div class="uptime">11:30am</div></div>
                    <div><div class="upname">Miller</div><div class="upplace">BS40 5RL</div></div>
                    <div class="upfit">Dave Perry</div>
                    <div class="upq"><span class="spill">ordered</span><span class="upqn">PRE-2026-0038</span></div>
                  </div>
                  <div class="pfoot">Open calendar &rarr;</div>
                </div>

                <!-- 6. KPI tiles -->
                <div class="zone z6 kgrid">
                  <div class="ktile rev"><div class="klbl">Revenue (won)</div><div class="kval">&pound;16,290.00</div><div class="ksub">12 jobs accepted</div></div>
                  <div class="ktile"><div class="klbl">Average order value</div><div class="kval">&pound;1,357.50</div><div class="ksub">across won quotes</div></div>
                  <div class="ktile rate"><div class="klbl">Close rate</div><div class="kval">60.0%</div><div class="ksub">12 of 20 decided</div></div>
                  <div class="ktile"><div class="klbl">Jobs in period</div><div class="kval">12</div><div class="ksub">accepted &amp; beyond</div></div>
                </div>

                <!-- 7. sales team + revenue share donut -->
                <div class="zone z7 pnl">
                  <h4><span class="tmall">Sales team</span><span class="tmjane">Their numbers</span></h4>
                  <div class="psub"><span class="tsall">Pipeline, close rate, and revenue per salesperson.</span><span class="tsjane">Pipeline, close rate, and revenue for this salesperson.</span></div>
                  <div class="lbflex">
                    <div class="lbwrap">
                      <table class="lbt">
                        <thead><tr><th>Person</th><th class="num">Pipeline</th><th class="num">Decided</th><th class="num">Won</th><th class="num">Close rate</th><th class="num">Revenue</th></tr></thead>
                        <tbody>
                          <tr><td class="who">&#129351; Jane Weller</td><td class="num">14</td><td class="num">9</td><td class="num">6</td><td class="num">66.7%</td><td class="num">&pound;8,420.00</td></tr>
                          <tr><td class="who">&#129352; Dave Perry</td><td class="num">9</td><td class="num">7</td><td class="num">4</td><td class="num">57.1%</td><td class="num">&pound;5,110.00</td></tr>
                          <tr><td class="who">&#129353; Sam Okafor</td><td class="num">6</td><td class="num">4</td><td class="num">2</td><td class="num">50.0%</td><td class="num">&pound;2,760.00</td></tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="sharewrap">
                      <div class="phead">Revenue share</div>
                      <div class="pwrap">
                        <svg width="76" height="76" viewBox="0 0 76 76" role="img" aria-label="Revenue share donut">
                          <g transform="rotate(-90 38 38)" fill="none" stroke-width="15">
                            <circle cx="38" cy="38" r="30" stroke="#1f3b5b" stroke-dasharray="97.5 91.0"></circle>
                            <circle cx="38" cy="38" r="30" stroke="#15803d" stroke-dasharray="59.2 129.3" stroke-dashoffset="-97.5"></circle>
                            <circle cx="38" cy="38" r="30" stroke="#f59e0b" stroke-dasharray="31.8 156.7" stroke-dashoffset="-156.7"></circle>
                          </g>
                        </svg>
                        <ul class="pleg">
                          <li><span class="psw" style="background:#1f3b5b"></span><span class="plbl">Jane Weller</span><span class="pval">&pound;8,420.00</span><span class="ppct">51.7%</span></li>
                          <li><span class="psw" style="background:#15803d"></span><span class="plbl">Dave Perry</span><span class="pval">&pound;5,110.00</span><span class="ppct">31.4%</span></li>
                          <li><span class="psw" style="background:#f59e0b"></span><span class="plbl">Sam Okafor</span><span class="pval">&pound;2,760.00</span><span class="ppct">16.9%</span></li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 8. what is selling + gross profit + recent wins -->
                <div class="zone z8">
                  <div class="pnl">
                    <h4>What&rsquo;s selling</h4>
                    <div class="psub">Top products by revenue in this period.</div>
                    <div class="lbflex">
                      <div class="pwrap">
                        <svg width="82" height="82" viewBox="0 0 76 76" role="img" aria-label="Product mix donut">
                          <g transform="rotate(-90 38 38)" fill="none" stroke-width="15">
                            <circle cx="38" cy="38" r="30" stroke="#1f3b5b" stroke-dasharray="86.7 101.8"></circle>
                            <circle cx="38" cy="38" r="30" stroke="#15803d" stroke-dasharray="59.9 128.6" stroke-dashoffset="-86.7"></circle>
                            <circle cx="38" cy="38" r="30" stroke="#f59e0b" stroke-dasharray="41.9 146.6" stroke-dashoffset="-146.6"></circle>
                          </g>
                        </svg>
                      </div>
                      <div class="lbwrap">
                        <div class="mixrow">
                          <div><div class="mixname">Vertical Blinds</div><div class="mixbar"><span style="width:46.0%"></span></div></div>
                          <div class="mixnum">18 units</div><div class="mixnum">&pound;6,240.00</div>
                        </div>
                        <div class="mixrow">
                          <div><div class="mixname">Roller Blinds</div><div class="mixbar"><span style="width:31.8%"></span></div></div>
                          <div class="mixnum">11 units</div><div class="mixnum">&pound;4,310.00</div>
                        </div>
                        <div class="mixrow">
                          <div><div class="mixname">Wooden Venetian</div><div class="mixbar"><span style="width:22.2%"></span></div></div>
                          <div class="mixnum">4 units</div><div class="mixnum">&pound;3,020.00</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="pnl">
                    <h4>Gross profit</h4>
                    <div class="psub">Sell price minus the price-table cost basis (material + extras).
                      Equivalent to your markup &amp; discount turned into pounds.</div>
                    <div class="mgrid">
                      <div><div class="klbl">Total profit</div><div class="mval">&pound;6,390.00</div></div>
                      <div><div class="klbl">Margin %</div><div class="mval">47.1%</div></div>
                      <div><div class="klbl">Per job (avg)</div><div class="mval">&pound;532.50</div></div>
                      <div><div class="klbl">Cost of goods</div><div class="mval cog">&pound;7,180.00</div></div>
                    </div>
                  </div>

                  <div class="pnl">
                    <h4>Recent wins</h4>
                    <div class="psub">Latest 10 jobs accepted in this period.</div>
                    <div class="rrow"><div><a>PRE-2026-0042</a> &mdash; Mrs Halliwell</div><div>Jane Weller</div><div class="rdate">29 Sep 2026</div><div class="rrev">&pound;1,284.00</div></div>
                    <div class="rrow"><div><a>PRE-2026-0038</a> &mdash; Miller</div><div>Dave Perry</div><div class="rdate">26 Sep 2026</div><div class="rrev">&pound;2,140.00</div></div>
                    <div class="rrow"><div><a>PRE-2026-0031</a> &mdash; Bishops Lydeard Surgery</div><div>Jane Weller</div><div class="rdate">18 Sep 2026</div><div class="rrev">&pound;3,960.00</div></div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> A scoreboard &mdash; and the line that says what you are looking at.</b>
                  <b class="c2"><span class="n">2</span> The time window. It counts from when the quote was <em>started</em>.</b>
                  <b class="c3"><span class="n">3</span> Your own dates &mdash; From, To, Apply. Neither box goes past today.</b>
                  <b class="c4"><span class="n">4</span> View: one salesperson&rsquo;s numbers.</b>
                  <b class="c5"><span class="n">5</span> Upcoming jobs &mdash; the panel that ignores all that.</b>
                  <b class="c6"><span class="n">6</span> The four big numbers.</b>
                  <b class="c7"><span class="n">7</span> Who is selling it.</b>
                  <b class="c8"><span class="n">8</span> What sold, what it made, and what just landed.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>What this screen is.</b> The <b>Dashboard</b> is the first thing most people open, and it is
             the only screen in the app that does not ask you to <em>do</em> anything. It is a scoreboard.
             The title says <b>Dashboard</b>, and the grey line underneath always tells you exactly what is
             being counted &mdash; <em>&ldquo;Sales at a glance &mdash; this month.&rdquo;</em> Read that line
             first, every time. Top right, <em>if you are an admin or you have been given</em>
             <b>Create quotes</b>, there is a solid <b>+ New quote</b> button, so you are never more than one
             click from starting a job &mdash; somebody who is not allowed to raise quotes simply does not get
             that button. Above the numbers you may get an amber <b>Joke of the day</b> strip:
             <b>&#128257; Another</b> fetches a different one, and the <b>&#10005;</b> only hides it
             <em>until tomorrow</em> &mdash; it is not an off switch. The real off switch is the tick-box
             <em>&ldquo;&#128516; Show a &lsquo;Joke of the day&rsquo; on the dashboard&rdquo;</em>, in the
             <b>Dashboard</b> section of
             <a href="/help/guide.php?g=settings-dashboard"><b>Setup &rarr; Settings</b></a>.</p>

          <p><b>The three controls at the top.</b> That is the whole of the input on this page:</p>
          <ul class="steps">
            <li><b>The period buttons</b> &mdash; five rectangular buttons in a row: <b>This month</b>,
                <b>Last 30 days</b>, <b>This quarter</b>, <b>This year</b>, <b>All time</b>. The one you are on
                is filled in solid. The page opens on <b>This month</b>. Every panel below except
                <b>Upcoming jobs</b> is re-counted the moment you click one.</li>
            <li><b>From / To and Apply</b> &mdash; two date pickers sitting in their own little bordered strip,
                for anything the five buttons do not cover: a show week, one supplier&rsquo;s month, last
                year&rsquo;s same fortnight. Both come <b>pre-filled with the last thirty days</b> &mdash;
                From is thirty days ago, To is today &mdash; so you are never staring at an empty box.
                <b>Neither box will let you pick a day later than today.</b> The Dashboard only ever looks
                backwards, so both pickers are capped at today&rsquo;s date and the calendar simply greys out
                anything beyond it; if you are hunting for a future date, you are on the wrong screen (that is
                the <b>Calendar</b>). The <b>To</b> date is <b>included</b> &mdash; pick the 30th and
                you get all of the 30th. Leave one side empty and that end stays open, and the line under the
                title says so in words rather than dates: clear the From box and it reads
                <em>&ldquo;beginning &rarr; 30 Sep 2026&rdquo;</em>; clear the To box and it reads
                <em>&ldquo;1 Sep 2026 &rarr; today&rdquo;</em>; clear both and it reads
                <em>&ldquo;beginning &rarr; today&rdquo;</em>, which is the same as <b>All time</b>.
                Press <b>Apply</b> to set it. If <em>neither</em> date makes sense to the app you get a red bar
                across the top: <b>&ldquo;Bad date format &mdash; use the date pickers.&rdquo;</b> &mdash; which
                in practice only happens if something has mangled the web address, because the pickers
                themselves cannot produce a bad date.</li>
            <li><b>View:</b> &mdash; a dropdown on its own row underneath, starting at <b>All sales team</b>. Pick a
                name and the whole page narrows to that one person; it applies straight away, no button to press.
                The list only holds people who have <em>actually raised a quote</em> in your account, so it stays
                short. The title line gains <em>&ldquo;for Jane Weller&rdquo;</em> so nobody mistakes one
                person&rsquo;s figures for the firm&rsquo;s; the team panel re-titles itself from
                <b>Sales team</b> to <b>Their numbers</b> and its sub-line changes from
                <em>&ldquo;&hellip;per salesperson.&rdquo;</em> to <em>&ldquo;&hellip;for this
                salesperson.&rdquo;</em>; and the <b>Revenue share</b> ring disappears (one person&rsquo;s share
                of themselves is always a hundred per cent). Pick <b>All sales team</b> to come back out.
                Changing the period keeps your person, and changing the person keeps your period. If nobody in
                your account has raised a quote yet, the whole <b>View:</b> row is not drawn at all.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The window counts from when the quote was
             STARTED &mdash; not when it was won.</b> This is far and away the biggest cause of
             &ldquo;the numbers are wrong&rdquo;. A quote you began on the 28th of September and the customer
             accepted on the 4th of October belongs to <b>September</b> on this screen, because that is the day
             it was raised. So if a job you know you won is nowhere to be seen, you are almost certainly looking
             at the wrong window: widen it, or click <b>All time</b>, and it will be there.</div></div>

          <p><b>Now the panels, top to bottom.</b></p>
          <ul class="steps">
            <li><b>Upcoming jobs</b> &mdash; the odd one out, and the only panel that is looking <em>forwards</em>.
                It ignores the period buttons and it ignores the View dropdown. It shows the next appointments
                <b>booked</b> on the calendar, soonest first, <b>up to eight of them</b> &mdash; and the sub-line
                counts what it actually found, so it reads <em>&ldquo;Next <b>3</b> appointments on the calendar
                &mdash; soonest first.&rdquo;</em> when there are three in the book and <em>&ldquo;Next
                <b>8</b>&hellip;&rdquo;</em> once you are busy. (With exactly one it says
                <em>&ldquo;appointment&rdquo;</em>, singular.) Only <em>booked</em> jobs count &mdash; completed,
                cancelled and no-shows are not &ldquo;upcoming&rdquo; by any useful definition, so they never
                appear. Each row is a link straight to that appointment and carries four things: the day
                (<b>Today</b> is in red, then <b>Tomorrow</b>, then a date like <em>Fri 2 Oct</em>) over the
                time; the customer&rsquo;s name over the <b>postcode you are driving to</b>; the fitter; and, if
                the appointment is tied to a quote, that quote&rsquo;s status word (plain small text &mdash;
                there is no coloured badge on this panel) and its number.
                <b>Open calendar &rarr;</b> at the foot takes you to the full diary. With nothing in the book the
                sub-line reads <em>&ldquo;Nothing booked yet &mdash; head to the calendar to add one.&rdquo;</em>
                and the panel says <em>&ldquo;No upcoming jobs booked.&rdquo;</em>
                <br><b>The fitter column depends on who you are.</b> If you have <b>View all customer jobs</b>
                (and every admin does) you see every booking, with the fitter&rsquo;s name, or an italic
                <b>Unassigned</b> where nobody is on it &mdash; which is your cue to go and put somebody on it.
                If you do <em>not</em> have that permission, you only ever see your own bookings, and the fitter
                column is left <b>blank</b> rather than saying &ldquo;Unassigned&rdquo; &mdash; every row is
                yours, so there is nothing to tell you.</li>
            <li><b>The four KPI tiles</b> &mdash; <b>Revenue (won)</b> in green is the money from every job that
                got past accepted (accepted, ordered, invoiced or paid), and it is the <b>customer&rsquo;s full
                figure &mdash; VAT included</b>, carrying any Wally tax (WT charge) you added. Underneath it,
                <em>&ldquo;12 jobs accepted&rdquo;</em>. <b>Average order value</b> is that same money shared
                across those jobs, sub-lined <em>&ldquo;across won quotes&rdquo;</em>. <b>Close rate</b>, in
                amber, is how many you won out of the ones the customer has actually <em>decided</em> on &mdash;
                quotes still sitting at <em>sent</em> are deliberately left out, because they might yet land, so
                nobody is punished for a job still in the air. With nothing decided it shows <b>&mdash;</b> and
                <em>&ldquo;no decided quotes yet&rdquo;</em>. <b>Jobs in period</b> is, honestly, the same count
                that is already printed under Revenue, written large; the sub-line is
                <em>&ldquo;accepted &amp; beyond&rdquo;</em>.</li>
            <li><b>Sales team</b> &mdash; a table of everyone who raised a quote in the window, richest first,
                with &#129351; &#129352; &#129353; on the top three. <b>Pipeline</b> is everything they got as far
                as sending out. <b>Decided</b> is the ones the customer has answered, yes or no. <b>Won</b> is the
                ones that turned into work. <b>Close rate</b> is Won out of Decided (a dash if nothing is decided
                yet), and <b>Revenue</b> is their share of the money. A big Pipeline with a small Decided is not a
                poor salesperson &mdash; it is a lot of quotes still waiting on an answer. Beside the table,
                <b>Revenue share</b> draws the same revenue as a ring. Empty window:
                <em>&ldquo;No quotes raised in this period.&rdquo;</em>, and an empty ring is a grey disc marked
                <em>no data</em>.</li>
            <li><b>What&rsquo;s selling</b> &mdash; your top <b>eight</b> products by money taken, won jobs only,
                as a ring and a list. Each row has the product name, a thin bar showing its share of the total,
                the unit count and the money. This is the panel to look at before you place a stock order. If a
                product has since been renamed or deleted, the row falls back to the name that was on the quote
                at the time, or <b>(unknown)</b>. Empty window:
                <em>&ldquo;No products sold in this period.&rdquo;</em></li>
            <li><b>Gross profit</b> &mdash; the only panel that needs <b>two</b> permissions: the <b>Gross
                profit</b> tick on the user&rsquo;s own page <em>and</em> <b>View costs</b>. Miss either one and
                the panel is not there at all. An empty window does <b>not</b> hide it &mdash; it still draws,
                showing <b>&pound;0.00</b>, a dash, a dash and <b>&pound;0.00</b>. Its sub-line says exactly what it
                does: <em>&ldquo;Sell price minus the price-table cost basis (material + extras). Equivalent to
                your markup &amp; discount turned into pounds.&rdquo;</em> &mdash; and it says <em>margin</em>
                instead of <em>markup</em> if that is how your pricing is set up. Four cells: <b>Total profit</b>,
                <b>Margin %</b>, <b>Per job (avg)</b>, and <b>Cost of goods</b> in brown. If a job was sold at an
                agreed price rather than the list price, the discount you gave is taken off here too, so the
                profit is what you really made.</li>
            <li><b>Recent wins</b> &mdash; <em>&ldquo;Latest 10 jobs accepted in this period.&rdquo;</em> Quote
                number, customer, who sold it, the date it was accepted and the money in green. Click the quote
                number to open the job. Missing names fall back to <b>(no customer)</b> and <b>(unknown)</b>.
                Empty window: <em>&ldquo;No jobs accepted in this period yet.&rdquo;</em></li>
          </ul>

          <div class="oops"><b>&ldquo;These numbers do not add up&rdquo; &mdash; the usual five:</b>
            <ul style="margin:.4rem 0 0;padding-left:1.15rem">
              <li><b>Gross profit will never match Revenue (won).</b> Revenue is what the customer pays &mdash;
                  <b>VAT included</b>, and with the Wally tax (WT charge) inside it. Gross profit works from the
                  <b>net</b> sell price of each blind and does <b>not</b> include the Wally tax at all. They are
                  counting two different things, so do not subtract one from the other.</li>
              <li><b>A job you won is missing.</b> The window is measured on the day the <b>quote was started</b>.
                  Widen the period or click <b>All time</b>.</li>
              <li><b>The Gross profit panel has vanished.</b> That is always permission, never the data &mdash;
                  an empty window leaves the panel showing zeros. You need <b>both</b> the <b>Gross profit</b>
                  tick in the user&rsquo;s <b>Dashboard</b> box <b>and</b> the <b>View costs</b> permission above
                  it; one without the other hides it.</li>
              <li><b>The Revenue share ring has vanished.</b> You are filtered to one person in the
                  <b>View:</b> box. Set it back to <b>All sales team</b>.</li>
              <li><b>There is no Dashboard link in the menu at all.</b> That user has none of the five Dashboard
                  panels ticked, so the app hides the link and sends them to the Calendar instead.</li>
            </ul>
          </div>

          <p><b>Who sees what.</b> An <b>admin</b> always sees all five panels. For everyone else, the panels are
             switched on one at a time on that person&rsquo;s own page &mdash; <b>Setup &rarr; Users</b>, edit the
             user, and look for the box headed <b>Dashboard</b>. It says it plainly: <em>&ldquo;Which Dashboard
             panels this user can see. Admins always see everything; these checkboxes only apply to non-admin
             users. Tick none to hide the Dashboard menu entry entirely for this user. Gross profit also requires
             the View costs permission above.&rdquo;</em> The five tick-boxes are <b>Revenue &amp; KPIs</b>,
             <b>Sales-team leaderboard</b>, <b>Product mix</b>, <b>Gross profit</b> and <b>Recent wins</b>.
             <b>Upcoming jobs</b> is not on that list &mdash; anybody who gets as far as the Dashboard sees it.
             This is why a salesperson and their boss can be looking at the same screen and seeing quite different
             pages; if somebody says half the dashboard is missing, that box is the first place to look. There is
             a whole guide on it: <a href="/help/guide.php?g=users-add"><b>adding a user</b></a>.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Header and the joke strip light up.',
             'This is the first screen most people open, and it helps to know what it is: a scoreboard, not a job list. It tells you how the selling is going. The grey line under the title always tells you exactly what you are looking at — "Sales at a glance, this month." Read that line first, every single time. Top right — as long as you are an admin, or you have been given the "create quotes" tick — there is a "new quote" button, so you never have to go looking for it. Somebody who is not allowed to raise quotes just will not have it. And the amber strip is the joke of the day — the cross hides it until tomorrow, it is not an off switch. The real off switch is a tick-box in Settings, in the section headed Dashboard.', 1],
            ['0:24', 'The five period buttons; This month is filled.',
             'Now the important row. Every number below, apart from the upcoming jobs list, is measured over the window you pick here — this month, last thirty days, this quarter, this year, or all time. It opens on this month. And here is the one thing that catches everybody out, so listen carefully. The window counts a job by the day the quote was STARTED — not the day the customer said yes. So a quote you began in September and won in October still lands in September. If a job you know you have won seems to be missing, widen the window, or click all time, and it will be there.', 2],
            ['0:52', 'From and To dates are picked; Apply. The red bar shows what a bad date looks like.',
             'For anything else — a show week, one supplier\'s month — use the two date pickers and press Apply. They come ready filled with the last thirty days: "from" is thirty days back, "to" is today, so you are never staring at an empty box. Notice that neither box will go past today — this screen only ever looks backwards, so the calendar greys out tomorrow onwards. If you want a future date you want the Calendar, not the Dashboard. The "to" date is included, so picking the thirtieth means all of the thirtieth. Leave one side empty and that end stays open; the line under the title then reads "beginning, arrow, today". And if neither date makes sense to the app, you get the red bar you can see now — "Bad date format — use the date pickers." In practice that only ever turns up if the web address itself has been mangled, because the pickers cannot produce a bad date.', 3],
            ['1:12', 'View: switches to Jane Weller; the page re-labels.',
             'The View box narrows the whole page down to one salesperson. It only lists people who have actually raised a quote here, so it stays nice and short, and it applies the moment you pick a name. Watch what changes: the title line gains "for Jane Weller", so nobody mistakes one person\'s figures for the firm\'s. The team panel stops saying "Sales team" and re-titles itself "Their numbers", with its sub-line changing from "per salesperson" to "for this salesperson". And the revenue share ring disappears, because one person\'s share of themselves is always a hundred per cent. Pick "all sales team" to come back out. Changing the period keeps your person, and changing the person keeps your period.', 4],
            ['1:38', 'Upcoming jobs panel lights up.',
             'Upcoming jobs is the exception to everything I have just said. It ignores the period, and it ignores the person. It shows the next jobs booked on the calendar, soonest first, up to eight of them — and the little grey line counts whatever it found, so with three in the book it says "next three appointments", not "next eight". Only jobs still marked as booked appear; anything completed, cancelled or marked a no-show drops off. Today is in red so your eye lands on it. Under the customer\'s name is the postcode you are driving to, then the fitter — or the word "unassigned", which is your cue to go and put somebody on it. If the appointment is tied to a quote you also get its status and its number. Click any row to open the appointment, or "open calendar" at the bottom for the full diary. With nothing in the book it reads "Nothing booked yet — head to the calendar to add one." And a fitter who has not been given "view all customer jobs" only sees their own bookings here, with that fitter column left blank rather than saying unassigned — every row is theirs anyway.', 5],
            ['2:10', 'The four KPI tiles light up.',
             'The four big numbers. "Revenue, won" is the money from jobs that got past accepted — accepted, ordered, invoiced or paid — and it is the customer\'s full figure, VAT included, carrying any Wally tax you added. "Average order value" is that money shared across those jobs. "Close rate" is how many you won out of the ones the customer has actually decided on; quotes still sitting at "sent" are left out on purpose, because they might still land, so nobody is punished for a job that is still in the air. With nothing decided it simply reads a dash, and "no decided quotes yet". "Jobs in period" is the same count you can already see under Revenue, written large. And an honest warning before we go on: because the Revenue tile includes VAT and the Wally tax, do not try to take the gross profit figure away from it. They are counting different things.', 6],
            ['2:46', 'Sales team table and the Revenue share ring.',
             'Who is selling it. Every person who raised a quote in the window, richest first, with a gold, silver and bronze medal on the top three. Pipeline is everything they got as far as sending out. Decided is the ones the customer has answered. Won is the ones that turned into work. Close rate is won out of decided — so a big pipeline with a small decided is not a poor salesperson, it just means a lot of quotes still in the air. Revenue is their share of the money, and the ring beside the table is that same revenue drawn as a share of the firm\'s. An empty window reads "No quotes raised in this period."', 7],
            ['3:12', 'What\'s selling, Gross profit and Recent wins.',
             'And the last three. "What\'s selling" ranks your top eight products by money taken, with a bar showing each one\'s share and the unit count beside it — that is the panel that tells you which range to reorder. "Gross profit" only appears for people who have both the gross profit tick and the view costs permission — if it is missing for somebody, that is always the reason, because an empty window still draws the panel with zeros in it. It takes each blind\'s sell price and subtracts what the price table says that blind and its extras cost you: total profit, margin percent, per job, and cost of goods. It is net — no VAT, and no Wally tax — which is why it will never match the Revenue tile. If you work in margin rather than markup, the wording follows your setting. Last, "recent wins" is the last ten jobs accepted in the window; click a quote number to open the job. And to finish, who sees what: an admin sees all five panels. For everybody else, your admin ticks them one at a time on the user\'s own page — revenue and KPIs, sales team leaderboard, product mix, gross profit, and recent wins — and gross profit also needs the "view costs" permission. Tick none at all, and that person has no dashboard; they land on the calendar instead.', 8],
        ],
];
