<?php
declare(strict_types=1);

/**
 * Guide: settings-calendar
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Calendar options',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Three choices: show money on jobs, your map app, and morning/afternoon slots.',
        'lede'    => 'The <b>Calendar</b> section has three quick choices — whether jobs show their money,
                      which map app your address links open in, and whether measure visits are booked as
                      morning or afternoon windows. Each saves on its own.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .opt{ border:1px solid var(--line); border-radius:10px; padding:.6rem .75rem; margin-bottom:.55rem; transition:box-shadow .2s, border-color .2s; }
          .gd .opt-h{ display:flex; align-items:center; gap:.5rem; font-weight:600; font-size:.82rem; color:var(--ink); }
          .gd .opt small{ display:block; color:var(--faint); font-size:.72rem; margin-top:.2rem; }
          .gd .tick{ width:17px; height:17px; border-radius:5px; border:1px solid var(--border-strong,#d1d5db); background:var(--surface); color:transparent; display:inline-flex; align-items:center; justify-content:center; font-size:.66rem; transition:background .2s,color .2s; }
          .gd .stage[data-step="1"] .t-money, .gd .stage[data-step="2"] .t-money, .gd .stage[data-step="3"] .t-money, .gd .stage[data-step="4"] .t-money{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .t-slots, .gd .stage[data-step="4"] .t-slots{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="1"] .opt-money, .gd .stage[data-step="2"] .opt-nav, .gd .stage[data-step="3"] .opt-slots{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .jobchip{ margin-top:.5rem; border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px; padding:.35rem .55rem; font-size:.74rem; background:var(--surface); display:inline-block; }
          .gd .jobchip .money{ display:none; color:var(--soft); margin-top:.2rem; }
          .gd .jobchip .paid{ display:none; margin-left:.35rem; color:var(--good); font-weight:700; }
          .gd .stage[data-step="1"] .jobchip .money, .gd .stage[data-step="2"] .jobchip .money, .gd .stage[data-step="3"] .jobchip .money, .gd .stage[data-step="4"] .jobchip .money{ display:block; }
          .gd .stage[data-step="1"] .jobchip .paid, .gd .stage[data-step="2"] .jobchip .paid, .gd .stage[data-step="3"] .jobchip .paid, .gd .stage[data-step="4"] .jobchip .paid{ display:inline; }
          .gd .pill{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:999px; padding:.18rem .55rem; font-size:.74rem; margin-right:.35rem; color:var(--soft); }
          .gd .pill.sel{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); }
          .gd .stage[data-step="0"] .rg, .gd .stage[data-step="1"] .rg, .gd .stage[data-step="2"] .rw, .gd .stage[data-step="3"] .rw, .gd .stage[data-step="4"] .rw{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="0"] .rg .dot, .gd .stage[data-step="1"] .rg .dot, .gd .stage[data-step="2"] .rw .dot, .gd .stage[data-step="3"] .rw .dot, .gd .stage[data-step="4"] .rw .dot{ border-color:var(--accent); }
          .gd .stage[data-step="0"] .rg .dot::after, .gd .stage[data-step="1"] .rg .dot::after, .gd .stage[data-step="2"] .rw .dot::after, .gd .stage[data-step="3"] .rw .dot::after, .gd .stage[data-step="4"] .rw .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .opens{ display:block; margin-top:.4rem; font-size:.74rem; color:var(--soft); }
          .gd .nn-waze{ display:none; }
          .gd .stage[data-step="2"] .nn-google, .gd .stage[data-step="3"] .nn-google, .gd .stage[data-step="4"] .nn-google{ display:none; }
          .gd .stage[data-step="2"] .nn-waze, .gd .stage[data-step="3"] .nn-waze, .gd .stage[data-step="4"] .nn-waze{ display:inline; }
          .gd .wrows{ display:none; flex-direction:column; gap:.4rem; margin-top:.55rem; }
          .gd .stage[data-step="3"] .wrows, .gd .stage[data-step="4"] .wrows{ display:flex; }
          .gd .wrow{ display:flex; align-items:center; gap:.35rem; font-size:.74rem; color:var(--soft); flex-wrap:wrap; }
          .gd .wlab{ min-width:4.3rem; font-weight:600; color:var(--ink); }
          .gd .tfield, .gd .capfield{ border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:.08rem .4rem; background:var(--surface); color:var(--ink); font-size:.72rem; }
          .gd .capfield{ min-width:1.9rem; text-align:center; }
          .gd .stage[data-step="4"] .toast{ opacity:1; transform:none; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Saved</div>
                <div class="card-t">Calendar</div>
                <div class="opt opt-money">
                  <div class="opt-h"><span class="tick t-money">&check;</span> &#128183; Show order value + balance on the calendar</div>
                  <div class="jobchip"><b>10:00 &middot; Mrs Patel &middot; Fit</b>
                    <span class="money">Order &pound;540 &middot; Paid &pound;540 &middot; Balance &pound;0 <span class="paid">PAID</span></span></div>
                </div>
                <div class="opt opt-nav">
                  <div class="opt-h">&#129517; Navigation app</div>
                  <div style="margin-top:.5rem"><span class="radio rg"><span class="dot"></span> Google Maps</span><span class="radio rw"><span class="dot"></span> Waze</span></div>
                  <span class="opens">Tap an address &rarr; opens in <b class="nn-google">Google Maps</b><b class="nn-waze">Waze</b></span>
                </div>
                <div class="opt opt-slots">
                  <div class="opt-h"><span class="tick t-slots">&check;</span> &#128344; Morning / afternoon booking slots</div>
                  <small>A window, not an exact time &mdash; set your own hours and limits for each.</small>
                  <div class="wrows">
                    <div class="wrow"><span class="wlab">Morning</span> From <span class="tfield">08:00</span> To <span class="tfield">12:30</span> &middot; <span class="capfield">5</span> / day</div>
                    <div class="wrow"><span class="wlab">Afternoon</span> From <span class="tfield">12:30</span> To <span class="tfield">17:00</span> &middot; <span class="capfield">3</span> / day</div>
                  </div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Three quick calendar choices.</b>
                  <b class="c1"><span class="n">2</span> Show the money on jobs &mdash; or keep it off.</b>
                  <b class="c2"><span class="n">3</span> The map app your address links open in.</b>
                  <b class="c3"><span class="n">4</span> Windows for measures &mdash; your own hours &amp; limits.</b>
                  <b class="c4 good"><span class="n">5</span> Each saves on its own &mdash; done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Still on the <b>Company</b> tab, scroll to <b>Calendar</b>. Three separate choices, each with its own <b>Save</b>:</p>
          <ul class="steps">
            <li><b>&#128183; Show order value + balance on the calendar</b> &mdash; each job linked to a quote shows its
                order value, what&rsquo;s been paid (deposit + payments) and the balance left, with a <b>PAID</b> badge once
                it&rsquo;s settled.</li>
            <li><b>&#129517; Navigation app</b> &mdash; when you tap an address on your schedule or the day calendar, it opens
                in the app you pick here: <b>Google Maps</b> (the default) or <b>Waze</b> if your fitters prefer it for live traffic.</li>
            <li><b>&#128344; Morning / afternoon booking slots</b> &mdash; for <b>measure (quote) visits</b>, offer a
                <b>Morning</b> or <b>Afternoon</b> window instead of an exact time, so you promise a window, not a specific hour.
                Set <b>your own times</b> for each window and <b>how many bookings each holds per day</b> (the two can differ &mdash;
                say 5 mornings but 3 afternoons). Once a window is full it can&rsquo;t be booked. Fittings aren&rsquo;t affected.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Heads up about the money:</b> this shows the figures to
             <b>everyone who can open the calendar</b> &mdash; including people whose logins normally hide costs, like fitters.
             It ignores the per-person <b>&ldquo;Can view costs&rdquo;</b> option on the <b>Users</b> page, just for the calendar.
             Leave it off if anyone who sees the calendar shouldn&rsquo;t see the figures.</div></div>
          <p>Each of the three has its <b>own Save button</b> &mdash; change one and save it; they don&rsquo;t depend on each other.</p>',
        'script'  => [
            ['0:00', 'Calendar section.',                 'Your calendar has three little choices. Here they are.', 0],
            ['0:06', 'Money on; job shows figures.',       'Show each job\'s value, what\'s paid, and what\'s left. But careful — everyone who can open the calendar sees it, even fitters. Leave it off if that\'s not what you want.', 1],
            ['0:14', 'Waze selected.',                     'Next, the map app your address links open in. Google Maps, or Waze if your fitters prefer it.', 2],
            ['0:22', 'Slots on; windows appear.',          'And morning or afternoon windows for measure visits, so you give a window, not an exact time. Set your own hours, and how many fit in each.', 3],
            ['0:31', 'Saved.',                             'Each choice saves on its own. That\'s your calendar sorted.', 4],
        ],
];
