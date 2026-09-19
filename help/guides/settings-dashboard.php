<?php
declare(strict_types=1);

/**
 * Guide: settings-dashboard
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Covers the one Dashboard switch on Settings > Company (the Joke of the day)
 * AND the screen it decorates: /dashboard/index.php — its period bar, its
 * salesperson filter, every panel on it, and the fact that WHO sees which
 * panel is set per person on /admin/users_edit.php, not here.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your dashboard',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'The one Dashboard switch in Settings, the whole dashboard it sits on, and where you say who sees which panel.',
        'lede'    => 'The <b>Company</b> tab holds exactly one Dashboard setting &mdash; the
                      &ldquo;Joke of the day&rdquo; tickbox. Everything else about your dashboard is
                      steered from the dashboard itself, and <b>who</b> sees each panel is set
                      per person over on <b>Users</b>. Here&rsquo;s the switch, the screen it
                      decorates, and what all the numbers on it actually mean.',
        'open'    => '/admin/settings.php',
        'css'     => '
          /* --- the real seven-tab strip at the top of Settings --- */
          .gd .tabstrip{ display:flex; flex-wrap:wrap; gap:.15rem; border-bottom:1px solid var(--line); margin-bottom:.8rem; }
          .gd .tb{ font-size:.72rem; font-weight:600; color:var(--faint); padding:.3rem .5rem; border-bottom:2px solid transparent; white-space:nowrap; }
          .gd .tb.on{ color:var(--accent-ink); border-bottom-color:var(--accent); }
          /* --- the section list you scroll down the Company panel --- */
          .gd .seclist{ margin-bottom:.7rem; }
          .gd .secrow{ font-size:.74rem; color:var(--faint); padding:.16rem .4rem; border-radius:6px; }
          .gd .secrow.cur{ color:var(--ink); font-weight:700; }
          .gd .stage[data-step="1"] .secrow.cur{ box-shadow:0 0 0 2px var(--accent); background:var(--accent-wash); }
          /* --- the Dashboard section card itself --- */
          .gd .sect{ border:1px solid var(--line); border-radius:10px; padding:.7rem .85rem; background:var(--panel); }
          .gd .stage[data-step="2"] .sect{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .chkrow{ display:inline-flex; align-items:center; gap:.5rem; font-weight:600; font-size:.84rem; color:var(--ink); }
          .gd .ghint{ display:block; color:var(--faint); font-size:.72rem; line-height:1.45; margin-top:.4rem; max-width:44ch; }
          /* two shipped .tick controls, swapped — never restyle .tick itself */
          .gd .tk-on{ display:none; }
          .gd .stage[data-step="2"] .tk-on, .gd .stage[data-step="3"] .tk-on, .gd .stage[data-step="4"] .tk-on{ display:inline-flex; }
          .gd .stage[data-step="2"] .tk-off, .gd .stage[data-step="3"] .tk-off, .gd .stage[data-step="4"] .tk-off{ display:none; }
          .gd .stage[data-step="3"] .save, .gd .stage[data-step="5"] .save{ transform:scale(.96); filter:brightness(1.25); }
          /* --- flash banners across the top of the real page --- */
          .gd .bn{ display:none; margin-bottom:.7rem; }
          .gd .stage[data-step="3"] .bn-on, .gd .stage[data-step="4"] .bn-on{ display:flex; }
          .gd .stage[data-step="5"] .bn-off{ display:flex; }
          .gd .errlead{ display:none; font-size:.68rem; color:var(--faint); margin:.6rem 0 .25rem; }
          .gd .stage[data-step="5"] .errlead{ display:block; }
          .gd .stage[data-step="5"] .bn-err{ display:flex; margin-bottom:0; }
          /* --- the dashboard preview strip below the setting --- */
          .gd .prev{ display:none; margin-top:.8rem; border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.65rem .8rem; }
          .gd .prev-h{ font-size:.63rem; text-transform:uppercase; letter-spacing:.07em; color:var(--faint); font-weight:700; margin-bottom:.5rem; }
          .gd .stage[data-step="4"] .pv-joke, .gd .stage[data-step="6"] .pv-ctl,
          .gd .stage[data-step="7"] .pv-pan, .gd .stage[data-step="8"] .pv-use{ display:block; }
          /* the real amber Joke of the day card */
          .gd .jcard{ display:flex; align-items:center; gap:.6rem; background:linear-gradient(90deg,#fffbeb,#fef9c3); border:1px solid #fde68a; border-radius:12px; padding:.5rem .7rem; }
          .gd .jemoji{ font-size:1.2rem; line-height:1; }
          .gd .jlabel{ font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:#b45309; }
          .gd .jtext{ font-size:.82rem; color:#1c2733; margin-top:.05rem; }
          .gd .jbody{ flex:1 1 auto; min-width:0; }
          .gd .jbtn{ font-size:.7rem; color:#b45309; border-radius:8px; padding:.18rem .4rem; white-space:nowrap; }
          /* the period bar: real text links, real date boxes, real Apply button */
          .gd .pbar{ display:flex; flex-wrap:wrap; align-items:center; gap:.2rem .7rem; }
          .gd .pl{ font-size:.76rem; color:var(--accent); }
          .gd .pl.on{ color:var(--ink); font-weight:700; }
          .gd .drange{ display:flex; align-items:center; gap:.3rem; font-size:.7rem; color:var(--faint); margin-left:auto; }
          .gd .dbox{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.18rem .35rem; font-size:.7rem; color:var(--ink); background:var(--surface); }
          .gd .applybtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.18rem .5rem; font-size:.7rem; font-weight:600; color:var(--ink); background:var(--panel); }
          .gd .vrow{ display:flex; align-items:center; gap:.45rem; margin-top:.6rem; font-size:.74rem; color:var(--soft); }
          /* the panel stack */
          .gd .pn{ border:1px solid var(--line-2); border-radius:8px; padding:.4rem .55rem; margin-bottom:.35rem; }
          .gd .pn b{ font-size:.78rem; color:var(--ink); }
          .gd .pnsub{ display:block; font-size:.66rem; color:var(--faint); margin-top:.1rem; line-height:1.4; }
          .gd .kgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:.3rem; margin-bottom:.35rem; }
          .gd .ktile{ border:1px solid var(--line-2); border-radius:8px; padding:.35rem .4rem; }
          .gd .klab{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .ksub{ font-size:.58rem; color:var(--faint); }
          .gd .kval{ font-size:.8rem; font-weight:700; color:var(--ink); }
          /* the Users-page Dashboard fieldset */
          .gd .ufield{ border:1px solid var(--line); border-radius:10px; padding:.55rem .7rem; }
          .gd .uleg{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; color:var(--accent-ink); margin-bottom:.3rem; }
          .gd .unote{ font-size:.66rem; color:var(--faint); line-height:1.45; margin-bottom:.45rem; }
          .gd .ulist{ display:flex; flex-wrap:wrap; gap:.5rem .9rem; }
          .gd .urow{ display:inline-flex; align-items:center; gap:.35rem; font-size:.74rem; color:var(--ink); }
          @media(max-width:620px){ .gd .kgrid{ grid-template-columns:repeat(2,1fr); } .gd .drange{ margin-left:0; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="okbanner bn bn-on"><b>&check;</b> Joke of the day is on.</div>
                <div class="okbanner bn bn-off"><b>&check;</b> Joke of the day is off.</div>
                <div class="tabstrip">
                  <span class="tb on">Company</span><span class="tb">Quoting</span><span class="tb">Legal</span>
                  <span class="tb">Status colours</span><span class="tb">Suppliers</span>
                  <span class="tb">Accounting</span><span class="tb">Back up data</span>
                </div>
                <div class="seclist">
                  <div class="secrow">Company details</div>
                  <div class="secrow">Company logo</div>
                  <div class="secrow cur">Dashboard</div>
                  <div class="secrow">Calendar</div>
                </div>
                <div class="sect">
                  <div class="card-t">Dashboard</div>
                  <label class="chkrow">
                    <span class="tk tk-off"><span class="tick">&check;</span></span>
                    <span class="tk tk-on"><span class="tick on">&check;</span></span>
                    &#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard
                  </label>
                  <span class="ghint">A little light relief for your team on the dashboard (staff only
                    &mdash; never shown to customers). Dismissible per day. Untick to switch it off.</span>
                  <div class="save">Save</div>
                </div>
                <div class="errlead">If that column hasn&rsquo;t been added to your account yet, you&rsquo;d see this instead:</div>
                <div class="errbanner bn bn-err"><b>&#9888;</b> Could not save: SQLSTATE[42S22] Unknown column
                  &lsquo;feature_joke_of_day&rsquo; &mdash; have you run migrate_joke_toggle.php?</div>

                <div class="prev pv-joke">
                  <div class="prev-h">Your dashboard</div>
                  <div class="jcard">
                    <span class="jemoji">&#128516;</span>
                    <div class="jbody">
                      <div class="jlabel">Joke of the day</div>
                      <div class="jtext">Why was the blind so good at its job? It always knew when to draw the line.</div>
                    </div>
                    <span class="jbtn">&#128257; Another</span><span class="jbtn">&#10005;</span>
                  </div>
                </div>

                <div class="prev pv-ctl">
                  <div class="prev-h">Your dashboard</div>
                  <div class="pbar">
                    <span class="pl on">This month</span><span class="pl">Last 30 days</span>
                    <span class="pl">This quarter</span><span class="pl">This year</span><span class="pl">All time</span>
                    <span class="drange">From <span class="dbox">01/09/2026</span> To <span class="dbox">19/09/2026</span>
                      <span class="applybtn">Apply</span></span>
                  </div>
                  <div class="vrow">View: <span class="selectbox">All sales team</span></div>
                </div>

                <div class="prev pv-pan">
                  <div class="prev-h">Your dashboard</div>
                  <div class="pn"><b>Upcoming jobs</b><span class="pnsub">Next 8 appointments on the calendar
                    &mdash; soonest first. Open calendar &rarr;</span></div>
                  <div class="kgrid">
                    <div class="ktile"><div class="klab">Revenue (won)</div><div class="kval">&pound;18,420.00</div><div class="ksub">14 jobs accepted</div></div>
                    <div class="ktile"><div class="klab">Average order value</div><div class="kval">&pound;1,315.71</div><div class="ksub">across won quotes</div></div>
                    <div class="ktile"><div class="klab">Close rate</div><div class="kval">63.6%</div><div class="ksub">14 of 22 decided</div></div>
                    <div class="ktile"><div class="klab">Jobs in period</div><div class="kval">14</div><div class="ksub">accepted &amp; beyond</div></div>
                  </div>
                  <div class="pn"><b>Sales team</b><span class="pnsub">Person &middot; Pipeline &middot; Decided &middot; Won
                    &middot; Close rate &middot; Revenue, plus a Revenue share donut.</span></div>
                  <div class="pn"><b>What&rsquo;s selling</b><span class="pnsub">Top products by revenue in this period.</span></div>
                  <div class="pn"><b>Gross profit</b><span class="pnsub">Total profit &middot; Margin % &middot; Per job (avg)
                    &middot; Cost of goods.</span></div>
                  <div class="pn"><b>Recent wins</b><span class="pnsub">Latest 10 jobs accepted in this period.</span></div>
                </div>

                <div class="prev pv-use">
                  <div class="prev-h">Users &rarr; edit a person</div>
                  <div class="ufield">
                    <div class="uleg">Dashboard</div>
                    <div class="unote">Which Dashboard panels this user can see. Admins always see everything;
                      these checkboxes only apply to non-admin users. Tick none to hide the Dashboard menu entry
                      entirely for this user. <b>Gross profit</b> also requires the <i>View costs</i> permission above.</div>
                    <div class="ulist">
                      <span class="urow"><span class="tick on">&check;</span> Revenue &amp; KPIs</span>
                      <span class="urow"><span class="tick on">&check;</span> Sales-team leaderboard</span>
                      <span class="urow"><span class="tick on">&check;</span> Product mix</span>
                      <span class="urow"><span class="tick">&check;</span> Gross profit</span>
                      <span class="urow"><span class="tick on">&check;</span> Recent wins</span>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Company tab &mdash; third block down is <b>Dashboard</b>.</b>
                  <b class="c2"><span class="n">2</span> One tickbox, and it&rsquo;s already ticked for you.</b>
                  <b class="c3 good"><span class="n">3</span> This block has its own <b>Save</b> &mdash; not the one higher up.</b>
                  <b class="c4"><span class="n">4</span> There it is, top of the dashboard &mdash; &#128257; Another, &#10005; hides it.</b>
                  <b class="c5 err"><span class="n">5</span> Untick, Save &mdash; and the red bar you might see instead.</b>
                  <b class="c6"><span class="n">6</span> The rest you steer from the dashboard itself.</b>
                  <b class="c7"><span class="n">7</span> Every panel, in the order they appear.</b>
                  <b class="c8"><span class="n">8</span> Who sees what lives on <b>Users</b>, not here.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar and you land on the <b>Company</b> tab (the first of
             seven: Company, Quoting, Legal, Status colours, Suppliers, Accounting, Back up data). Scroll
             down past <b>Company details</b> and <b>Company logo</b> and the third block is
             <b>Dashboard</b>, just above <b>Calendar</b>.</p>
          <ul class="steps">
            <li><b>&#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard</b> &mdash; one tickbox,
                and it is <b>already ticked</b> from the day your account is set up. You don&rsquo;t have to
                do anything to switch it on. The note printed under it reads: &ldquo;A little light relief
                for your team on the dashboard (staff only &mdash; never shown to customers). Dismissible
                per day. Untick to switch it off.&rdquo; If you use <b>Compact mode</b> that note is hidden
                on screen &mdash; so there it is in full.</li>
            <li><b>Save</b> &mdash; this little block has its <b>own</b> Save button, labelled just
                <b>Save</b>. The big <b>Save company details</b> button higher up belongs to a different
                form and won&rsquo;t save this. Press the right one and a green bar comes back across the
                top of the page: <b>Joke of the day is on.</b></li>
          </ul>
          <p><b>What it looks like.</b> At the very top of the dashboard, a soft amber card: the
             &#128516; face, the words <b>JOKE OF THE DAY</b>, the joke, and two buttons on the right.
             <b>&#128257; Another</b> shuffles up a different one for a laugh &mdash; it isn&rsquo;t saved,
             so a refresh brings the day&rsquo;s joke back. <b>&#10005;</b> is &ldquo;hide until
             tomorrow&rdquo;. Everybody in your team gets the same joke all day, and no customer ever sees
             it &mdash; not on a quote, not on the accept page, not on an invoice.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The cross is per browser, not per
             person.</b> Hiding it hides it on the machine you&rsquo;re sat at. It&rsquo;ll be back on the
             tablet in the van, in a different browser, or after somebody clears their site data.</div></div>
          <p><b>Turning it off.</b> Untick the box, press <b>Save</b>, and the green bar says <b>Joke of
             the day is off.</b> The card stops appearing for everyone straight away.</p>
          <div class="oops"><b>Could not save: &hellip; &mdash; have you run migrate_joke_toggle.php?</b>
             If you ever get that red bar, nothing is broken and nothing else is affected &mdash; the
             database column simply hasn&rsquo;t been added to your account yet. Tell us and we&rsquo;ll
             sort it.</div>
          <p><b>The screen it decorates.</b> That is the only dashboard setting in Settings; the rest of
             the dashboard you steer from the dashboard itself. Along the top is a row of plain links:
             <b>This month</b> (where it opens), <b>Last 30 days</b>, <b>This quarter</b>, <b>This
             year</b>, <b>All time</b> &mdash; plus <b>From</b> and <b>To</b> date pickers (neither will
             go past today) and an <b>Apply</b> button for your own range. Type a date in by hand and get
             it wrong and it says <b>Bad date format &mdash; use the date pickers.</b> Underneath,
             <b>View:</b> narrows the whole screen to one salesperson; it starts on <b>All sales team</b>
             and changes as soon as you pick a name. It only lists people who have actually raised a
             quote, so a brand-new starter simply isn&rsquo;t in the list yet &mdash; that&rsquo;s not a
             fault.</p>
          <p><b>The panels, in page order.</b></p>
          <ul class="steps">
            <li><b>Upcoming jobs</b> &mdash; your next eight bookings from now on, soonest first: Today /
                Tomorrow / Fri 3 Oct and the time, the customer (or &ldquo;No customer&rdquo;), the
                postcode, the fitter (or <i>Unassigned</i>), a status pill and the quote number, with
                <b>Open calendar &rarr;</b> at the foot. Nothing booked? &ldquo;Nothing booked yet &mdash;
                head to the calendar to add one.&rdquo;</li>
            <li><b>Revenue (won) / Average order value / Close rate / Jobs in period</b> &mdash; four
                tiles: money won and how many jobs, the average across won quotes, your close rate
                (&ldquo;14 of 22 decided&rdquo;, or a dash and &ldquo;no decided quotes yet&rdquo;), and
                the job count &ldquo;accepted &amp; beyond&rdquo;.</li>
            <li><b>Sales team</b> &mdash; Person, Pipeline, Decided, Won, Close rate, Revenue, top three
                picked out, with a <b>Revenue share</b> donut beside it. Filter to one person and the
                heading becomes <b>Their numbers</b> and the donut drops away. Empty: &ldquo;No quotes
                raised in this period.&rdquo;</li>
            <li><b>What&rsquo;s selling</b> &mdash; top products by revenue, units and money. Empty:
                &ldquo;No products sold in this period.&rdquo;</li>
            <li><b>Gross profit</b> &mdash; sell price minus the price-table cost basis (material and
                extras), shown as Total profit, Margin %, Per job (avg) and Cost of goods.</li>
            <li><b>Recent wins</b> &mdash; the latest ten jobs accepted in the period. Empty: &ldquo;No
                jobs accepted in this period yet.&rdquo;</li>
          </ul>
          <p><b>What the words mean</b>, so you read it right on day one. <b>Won</b> means the quote is
             accepted, ordered, invoiced or paid. The close rate only counts <b>decided</b> quotes &mdash;
             won or declined &mdash; so a quote still sitting at &ldquo;sent&rdquo; isn&rsquo;t held
             against anybody. And every period runs on the day the quote was <b>started</b>, not the day
             it was accepted, so a big January quote won in March still counts as January work. The
             figures cover retail and trade together, so they won&rsquo;t tie up with a Retail-only order
             list in the sidebar.</p>
          <p><b>&ldquo;Why can&rsquo;t my fitter see the profit?&rdquo;</b> Because that isn&rsquo;t set
             here. Go to <b>Users</b>, edit the person, and there&rsquo;s a <b>Dashboard</b> box with five
             tickboxes: <b>Revenue &amp; KPIs</b>, <b>Sales-team leaderboard</b>, <b>Product mix</b>,
             <b>Gross profit</b>, <b>Recent wins</b>. Gross profit also needs the <b>View costs</b>
             permission above it. Admins always see the lot. Tick none at all and that person gets no
             Dashboard link and lands on the Calendar instead &mdash; usually exactly right for a fitter.
             And until your business has raised its first quote, nobody gets a Dashboard link at all.</p>
          <p>Nothing in this block touches a quote, a price or a customer &mdash; it is your own team&rsquo;s
             screen, start to finish.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Company tab; Dashboard section ringed.',      'Settings opens on the Company tab. Scroll past your company details and your logo, and you\'ll find a little block called Dashboard. It has one switch in it.', 1],
            ['0:10', 'The tickbox, already ticked, hint below.',    'The box is "Show a Joke of the day on the dashboard", and it\'s ticked from the day you start. It\'s staff only — a customer never sees it, not on a quote, not on the accept page.', 2],
            ['0:21', 'Save pressed; green bar across the top.',     'This block has its own Save button. Press it and a green bar comes back across the top saying "Joke of the day is on." Press the big Save company details button higher up and nothing in this block moves.', 3],
            ['0:33', 'Amber joke card with its two buttons.',       'Open the Dashboard and there it is, at the top. Everyone in your team gets the same joke all day. "Another" shuffles one up for a laugh, and the cross hides it until tomorrow. That cross only hides it on the machine you\'re sat at — it\'ll be back on the tablet in the van.', 4],
            ['0:47', 'Untick, Save; "off" bar, plus the red one.',  'Not your sort of thing? Untick it, press Save, and it says "Joke of the day is off." If you ever see a red bar mentioning migrate joke toggle, nothing is broken — just tell us, that column hasn\'t been added to your account yet.', 5],
            ['1:00', 'Period bar, date range, View: dropdown.',     'That\'s the only dashboard switch in Settings. The rest of the screen you steer from the screen itself: it opens on This month, and you can jump to thirty days, the quarter, the year or all time, or pick your own dates and press Apply. The View dropdown narrows everything to one salesperson — it only lists people who\'ve actually written a quote.', 6],
            ['1:16', 'The panel stack, in page order.',             'Upcoming jobs is your next eight bookings. Then the money: revenue won, average order value, close rate, jobs in the period. Then how each of your team is doing, what\'s selling, your gross profit, and your last ten wins. "Won" means accepted, ordered, invoiced or paid — and the dates count from the day the quote was started, not the day it was accepted.', 7],
            ['1:33', 'Users page: the Dashboard tickboxes.',        'One last thing, and it\'s the question everyone asks: why can\'t my fitter see the profit? Because that isn\'t set here. Open Users, edit the person, and there\'s a Dashboard box with five tickboxes. Gross profit also needs "Can view costs". Tick none at all and they don\'t get a Dashboard link — they go straight to the Calendar, which is usually exactly what you want for a fitter.', 8],
        ],
];
