<?php
declare(strict_types=1);

/**
 * Guide: settings-calendar — "Calendar options" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the "Calendar" section on the Company tab of /admin/settings.php —
 * three separate forms, each with its own Save:
 *   - calendar_money: "💷 Show order value + balance on the calendar" (money
 *     line from _partials/calendar_money.php; shown only to user_can_see_money());
 *   - map_provider: "🧭 Navigation app" radios Google Maps / Waze
 *     (_partials/maps.php — the embedded run map stays Google);
 *   - ampm_slots: "🕘 Booking time slots" + a LIST of time slots (Name / From /
 *     To / Bookings / day, "✕ Remove", "+ Add a time slot", max AMPM_MAX_WINDOWS
 *     = 6, new rows default to 2 a day, defaults Morning 09:00–13:00 and
 *     Afternoon 13:00–17:00 at 4 a day), with the handler's exact messages.
 * The booking side is calendar/new.php ("+ Book Appointment" → "Book
 * appointment"): exact Time + Duration (mins) when slots are off; "Time slot"
 * cards with "N of M left" / "Full" and the "Email the customer their
 * appointment time slot" tick when on.
 *
 * v2: one SCENE per script line (data-scene = the line's step); data-len is
 * worked out from the line itself (characters ÷ 13.6).
 */

$script = [
    ['1',  'Three settings in one box',   'The Calendar section is the last one on the Company tab. It looks like one box, but it holds three separate settings: money on the calendar, your navigation app, and booking time slots. Each one has its own Save button. Change one, and save that one. Saving one never touches the other two.', 1],
    ['2',  'Money on the calendar',       'First, Show order value plus balance on the calendar. Tick it, and every job linked to a quote shows its money on the month, week and day calendars: the order value, what has been paid, and the balance still owed. Paid means the deposit, plus any payments you have logged, so it keeps itself up to date.', 2],
    ['3',  'Paid, and who sees it',       'Once a job is settled, it shows a tick, PAID, and a balance of nothing. And do not worry about your fitters. Only people allowed to see money see these figures: admins, and anyone with Can see money ticked on the Users page. Everyone else sees the calendar without them. Press Save, and it says, Calendar will show order value plus balance.', 3],
    ['4',  'Navigation app',              'Second, your Navigation app. There are two round buttons: Google Maps, which is the default, or Waze. When someone taps an address on My Schedule or the day calendar, it opens in the app you pick here. So choose Waze if your fitters like it for live traffic. Press Save, and it says, Address links will now open in Waze.', 4],
    ['5',  'One small exception',         'One small exception. The little route map drawn inside Today\'s run is always a Google map, because Waze cannot be shown inside another page. But every address you tap, on My Schedule or the day calendar, still opens in the app you chose.', 5],
    ['6',  'An exact time, or a time slot',  'Third, and the biggest: Booking time slots. This changes how you book a quote visit, to measure up. Left unticked, you book an exact time, with a duration, like ten past eleven for an hour. Tick it, and you offer a time slot instead, such as Morning or Afternoon. The customer is given the time slot, never an exact hour. Fittings are not affected.', 6],
    ['7',  'Your time slots',                'Underneath the tick is one row for each window. Out of the box there are two: Morning, nine till one, and Afternoon, one till five, each holding four bookings a day. Every row has a Name, which is what the customer sees, a From time, a To time, and Bookings per day. Change them to suit your own hours.', 7],
    ['8',  'Bookings per day',            'Bookings per day is your limit for that time slot, anything from one to ninety nine. Each time slot has its own limit, so six mornings and three afternoons is perfectly fine. Once a time slot is full on a given day, it cannot be booked any more. So you never promise more visits than you can do.', 8],
    ['9',  'Add or remove a time slot',      'Need an evening? Click Add a time slot. A new empty row appears, ready for a name and times. Say, Evening, six till eight. You can have up to six windows. To take one away, click Remove on its row. Bookings already in it keep their label. You must keep at least one. Then press Save.', 9],
    ['10', 'Saving, and what it checks',  'When you save, the time slots are put in time order, and a green bar says, Booking time slots saved. If something does not add up, you get a red bar starting, Time slots not saved, and it tells you why. Every slot needs a name, and each one needs a From time before its To time.', 10],
    ['11', 'Booking with time slots',        'Now book a quote visit, from Book Appointment on the calendar. Instead of a time, you get Time slot, with one choice for each time slot, its hours, and how many are left that day. A full time slot says Full, and cannot be picked. Below is a tick to email the customer their appointment window. Pick one, and press Book appointment.', 11],
];
$L = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr  = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$type = static fn (string $txt, float $d, int $steps = 6, float $tt = .5): string =>
    '<span class="a-type" style="--d:' . $d . 's;--ts:' . $steps . ';--tt:' . $tt . 's">' . $txt . '</span>';
$tk   = '<span class="tick on">&#10003;</span>';
$tk0  = '<span class="tick"></span>';
$save = static fn (string $cls = '', string $st = '', string $more = '') => '<div class="fact"><span class="btnp ' . $cls . '" style="' . $st . '">Save</span>' . $more . '</div>';

// One time slot row. Each value may be HTML (for animations).
$row = static function (string $name, string $from, string $to, string $cap, string $cls = '', string $st = '', string $rm = '') {
    return '<div class="wrow ' . $cls . '" style="' . $st . '">'
         . '<div class="wf w-n"><span class="lab">Name</span><div class="inp">' . $name . '</div></div>'
         . '<div class="wf w-t"><span class="lab">From</span><div class="inp tm">' . $from . '</div></div>'
         . '<div class="wf w-t"><span class="lab">To</span><div class="inp tm">' . $to . '</div></div>'
         . '<div class="wf w-c"><span class="lab">Bookings / day</span><div class="inp">' . $cap . '</div></div>'
         . '<span class="btns sm rm ' . $rm . '">&#10005; Remove</span></div>';
};
$slotHint = '<p class="hintg">When booking a <b>quote (measure) visit</b>, offer a time slot such as <b>Morning</b>, <b>Afternoon</b> or <b>Evening</b>
    instead of an exact time &mdash; so the customer is given a time slot, never an exact hour. Name each slot, set its <b>times</b> and how many
    bookings it holds <b>per day</b>; once a slot is full it can&rsquo;t be booked. Add or remove slots to suit you (up to 6). Fittings are unaffected.</p>';
$moneyHint = '<p class="hintg">On the month, week and day calendars, each job linked to a quote shows its order value, amount received (deposit + payments)
    and outstanding balance &mdash; with a PAID badge once it&rsquo;s settled.</p>';
$whoHint = '<p class="hintg">Only people allowed to see money see the figures &mdash; admins, plus anyone with <b>&ldquo;Can see money&rdquo;</b> ticked on the
    <span class="lnk">Users</span> page. Everyone else (e.g. fitters) sees the calendar without them.</p>';
$mapHint = '<p class="hintg">When you tap an address on My Schedule or the day calendar, it opens in the app you choose here. Google Maps is the default;
    pick Waze if your fitters prefer it for live traffic and routing.</p>';
$radio = static fn (string $label, string $fill = '') => '<span class="rad"><span class="rd">' . $fill . '</span>' . $label . '</span>';
$job = static fn (string $money, string $st = '') => '<div class="job" style="' . $st . '"><b>10:00 Mrs Patel</b><span>Fitting &middot; LA9 4QT</span>' . $money . '</div>';
$owe = '<div class="mny">&pound;540.00 &middot; paid &pound;200.00 &middot; <b>bal &pound;340.00</b></div>';
$paid = '<div class="mny ok">&#10003; PAID &pound;540.00 &middot; bal &pound;0.00</div>';

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Calendar options',
        'eyebrow' => 'Settings · Company',
        'v'       => 2,
        'blurb'   => 'Money on jobs, your map app, and booking time slots — Morning, Afternoon, Evening — with your own times and limits.',
        'lede'    => 'The <b>Calendar</b> section at the bottom of the <b>Company</b> tab holds three separate settings: whether jobs on
                      the calendar show their <b>money</b>, which <b>navigation app</b> an address opens in, and whether quote visits
                      are booked at an <b>exact time</b> or in <b>time slots</b> (such as Morning, Afternoon and Evening, with
                      your own hours and a limit per day). Each has its own <b>Save</b>. To get there: <b>Setup</b> &rarr;
                      <b>Settings</b> &rarr; <b>Company</b>, then scroll to the bottom.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          .gd .secbox{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:31rem; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .subf + .subf{ margin-top:.55rem; padding-top:.55rem; border-top:1px solid var(--line); }
          .gd .ckl{ display:flex; align-items:center; gap:.45rem; font-size:.74rem; font-weight:600; color:var(--ink); }
          .gd .ckl .tick{ flex:0 0 auto; }
          .gd .flab{ display:block; font-size:.74rem; font-weight:600; color:var(--ink); margin-bottom:.3rem; }
          .gd .hintg{ font-size:.6rem; color:var(--faint); line-height:1.45; margin:.3rem 0 0; }
          .gd .hintg b{ color:var(--soft); }
          .gd .lnk{ color:var(--accent); text-decoration:underline; }
          .gd .lab{ display:block; font-size:.58rem; font-weight:600; color:var(--soft); margin-bottom:.12rem; white-space:nowrap; }
          .gd .inp{ position:relative; display:grid; align-items:center; height:24px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    background:var(--surface); padding:0 .4rem; font-size:.7rem; color:var(--ink); overflow:hidden; white-space:nowrap; font-variant-numeric:tabular-nums; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp.tm::after{ content:"\25F7"; position:absolute; right:.35rem; top:.2rem; color:var(--faint); font-size:.7rem; }
          .gd .inp.dd::after{ content:"\25BE"; position:absolute; right:.4rem; top:.25rem; color:var(--faint); font-size:.62rem; }
          .gd .fact{ margin-top:.45rem; position:relative; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .btns.sm{ font-size:.62rem; padding:.2rem .5rem; }
          .gd .flash{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); color:var(--good); font-weight:700;
                      font-size:.72rem; border-radius:8px; padding:.4rem .65rem; margin-bottom:.5rem; max-width:31rem; }
          .gd .flash.err{ background:var(--err-wash); border-color:color-mix(in srgb,var(--err) 35%,transparent); color:var(--err); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.good{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
          .gd .split{ display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); gap:.9rem; align-items:start; }
          .gd .side2{ display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; min-width:0; }
          .gd .stack{ display:grid; } .gd .stack > *{ grid-area:1/1; }
          .gd .swap{ display:inline-grid; } .gd .swap > *{ grid-area:1/1; }

          /* radios, drawn like the real ones */
          .gd .rad{ display:inline-flex; align-items:center; gap:.4rem; font-size:.74rem; color:var(--ink); margin-right:1.2rem; }
          .gd .rd{ width:14px; height:14px; border-radius:50%; border:1.5px solid var(--border-strong,#9aa3af); display:inline-grid; place-items:center; box-sizing:border-box; background:var(--surface); }
          .gd .rd i{ grid-area:1/1; width:6px; height:6px; border-radius:50%; background:var(--accent); display:block; }

          /* time slot rows */
          .gd .wrow{ display:flex; flex-wrap:wrap; align-items:flex-end; gap:.35rem .55rem; margin-top:.45rem; }
          .gd .wf{ min-width:0; } .gd .w-n{ width:6.2rem; } .gd .w-t{ width:4.6rem; } .gd .w-c{ width:5.4rem; }
          .gd .rm{ margin-bottom:.1rem; }

          /* calendar job card */
          .gd .cal{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:17rem; background:var(--surface); }
          .gd .cal .ch{ background:var(--panel); font-size:.62rem; font-weight:700; color:var(--soft); padding:.3rem .5rem; border-bottom:1px solid var(--line); }
          .gd .job{ margin:.45rem; border-left:3px solid #2563eb; background:#eff6ff; border-radius:6px; padding:.35rem .5rem; font-size:.64rem; color:#1f2937; }
          :root[data-theme="dark"] .gd .job{ background:rgba(37,99,235,.15); color:var(--ink); }
          .gd .job b{ display:block; font-size:.7rem; } .gd .job span{ color:#6b7280; }
          .gd .mny{ margin-top:.2rem; font-size:.64rem; color:#4b5563; } .gd .mny b{ color:#111827; }
          :root[data-theme="dark"] .gd .mny, :root[data-theme="dark"] .gd .mny b{ color:var(--ink); }
          .gd .mny.ok{ color:#047857; font-weight:700; }
          .gd .who{ display:flex; align-items:center; gap:.45rem; font-size:.68rem; color:var(--ink); }
          .gd .who i{ font-style:normal; font-size:1rem; }

          /* map + address */
          .gd .map{ position:relative; height:7.5rem; border-radius:10px; border:1px solid var(--line); overflow:hidden;
                    background:linear-gradient(135deg,#e5efe0 0%,#e9f1f7 50%,#efe9dc 100%); max-width:17rem; }
          .gd .map svg{ position:absolute; inset:0; width:100%; height:100%; }
          .gd .map .gtag{ position:absolute; left:.4rem; bottom:.3rem; font-size:.6rem; font-weight:800; color:#4285f4; background:#fff; border-radius:4px; padding:0 .3rem; }
          .gd .addr{ display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; color:var(--accent); text-decoration:underline; }

          /* booking screen */
          .gd .bk{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); max-width:31rem; }
          .gd .bk .lg{ font-size:.66rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
          .gd .bkrow{ display:grid; grid-template-columns:1fr 1fr; gap:.45rem; }
          .gd .bkrow4{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; }
          .gd .opts{ display:flex; flex-direction:column; gap:.3rem; margin-top:.15rem; }
          .gd .opt{ display:flex; align-items:center; gap:.45rem; border:1px solid var(--line); border-radius:8px; padding:.3rem .5rem; font-size:.7rem; color:var(--ink); background:var(--surface); }
          .gd .opt .rg{ color:var(--faint); font-size:.64rem; }
          .gd .opt .cnt{ margin-left:auto; font-size:.62rem; font-weight:700; color:var(--good); }
          .gd .opt.full{ opacity:.55; background:var(--panel); } .gd .opt.full .cnt{ color:var(--err); }
          .gd .opt.pick{ border-color:var(--accent); box-shadow:0 0 0 2px var(--accent-wash); }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; }
          .gd .ttl{ font-size:.72rem; font-weight:800; color:var(--ink); margin-bottom:.35rem; }
          .gd .dots{ display:flex; gap:.25rem; margin-top:.3rem; }
          .gd .dots i{ width:.75rem; height:.75rem; border-radius:50%; border:1.5px solid var(--accent); display:block; }
          .gd .dots i.f{ background:var(--accent); }
          .gd .lane{ display:flex; align-items:center; gap:.6rem; font-size:.7rem; color:var(--ink); margin-top:.35rem; }
          .gd .lane b{ min-width:6.5rem; }

          @media (max-width:640px){
            .gd .split, .gd .two{ grid-template-columns:1fr; }
            .gd .sc{ min-height:440px; }
            .gd .bkrow4{ grid-template-columns:1fr 1fr; }
            .gd .w-n{ width:5.4rem; } .gd .w-t{ width:4.3rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / company</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="secbox"><div class="sech">Calendar</div>
                    <div class="subf"><label class="ckl">' . $tk . ' &#128183; Show order value + balance on the calendar</label>' . $save() . '</div>
                    <div class="subf"><span class="flab">&#129517; Navigation app</span>' . $radio('Google Maps', '<i></i>') . $radio('Waze') . $save() . '</div>
                    <div class="subf"><label class="ckl">' . $tk . ' &#128344; Booking time slots</label>
                      ' . $row('Morning', '09:00', '13:00', '4') . $row('Afternoon', '13:00', '17:00', '4') . '
                      <div class="fact"><span class="btns sm">+ Add a time slot</span></div>' . $save() . '</div>
                  </div>
                  <p class="scs" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; eleven short chapters.</p>
                </div>

                <!-- 1 — three settings -->
                <div class="sc" data-scene="1" data-len="' . $L(1) . '">
                  <div class="secbox a-rise" style="--d:1s"><div class="sech">Calendar</div>
                    <div class="subf a-fly" style="--d:5s"><label class="ckl">' . $tk0 . ' &#128183; Show order value + balance on the calendar</label>' . $save('a-ring', '--d:12.5s') . '</div>
                    <div class="subf a-fly" style="--d:7s"><span class="flab">&#129517; Navigation app</span>' . $radio('Google Maps', '<i></i>') . $radio('Waze') . $save('a-ring', '--d:13.2s') . '</div>
                    <div class="subf a-fly" style="--d:9s"><label class="ckl">' . $tk0 . ' &#128344; Booking time slots</label>' . $save('a-ring', '--d:13.9s') . '</div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:12.5s">Each has its own Save</span>
                    <span class="chip good a-pop" style="--d:18s">Saving one never touches the others</span></div>
                </div>

                <!-- 2 — money -->
                <div class="sc" data-scene="2" data-len="' . $L(2) . '">
                  <div class="split">
                    <div class="secbox"><div class="sech">Calendar</div>
                      <label class="ckl"><span class="tick a-sel" style="--d:4s">&#10003;</span> &#128183; Show order value + balance on the calendar</label>
                      <div class="a-fade" style="--d:5s">' . $moneyHint . '</div>' . $save() . '</div>
                    <div class="side2">
                      <div class="cal a-rise" style="--d:6s"><div class="ch">Thu 24 Sep</div>
                        <div class="job"><b>10:00 Mrs Patel</b><span>Fitting &middot; LA9 4QT</span>
                          <div class="mny"><span class="a-fade" style="--d:10.5s">&pound;540.00</span> <span class="a-fade" style="--d:12.5s">&middot; paid <span class="a-ring" style="--d:16.5s;border-radius:4px">&pound;200.00</span></span>
                            <span class="a-fade" style="--d:13.8s">&middot; <b>bal &pound;340.00</b></span></div></div></div>
                      <div class="chips" style="margin-top:0"><span class="chip a-pop" style="--d:7s">Month</span><span class="chip a-pop" style="--d:7.5s">Week</span><span class="chip a-pop" style="--d:8s">Day</span></div>
                      <span class="chip good a-pop" style="--d:17.5s">paid = deposit + payments logged</span>
                    </div>
                  </div>
                </div>

                <!-- 3 — paid, and who sees it -->
                <div class="sc" data-scene="3" data-len="' . $L(3) . '">
                  <div class="flash a-pop" style="--d:22.5s">Calendar will show order value + balance.</div>
                  <div class="split">
                    <div class="side2">
                      <div class="cal"><div class="ch">Thu 24 Sep</div>
                        <div class="job"><b>10:00 Mrs Patel</b><span>Fitting &middot; LA9 4QT</span>
                          <div class="stack"><div class="a-out" style="--d:1.5s">' . $owe . '</div><div class="a-fade" style="--d:1.8s">' . $paid . '</div></div></div></div>
                      <div class="secbox a-rise" style="--d:19s;max-width:17rem"><label class="ckl">' . $tk . ' &#128183; Show order value + balance on the calendar</label>' . $save('a-press', '--d:21.5s') . '</div>
                    </div>
                    <div class="side2">
                      <div class="who a-fly" style="--d:9s"><i>&#128081;</i> Admins &mdash; <b>see the figures</b></div>
                      <div class="who a-fly" style="--d:11s"><i>&#9989;</i> &ldquo;Can see money&rdquo; ticked &mdash; <b>see them</b></div>
                      <div class="who a-fly" style="--d:15s"><i>&#128295;</i> Everyone else, e.g. fitters &mdash; <b>no figures</b></div>
                      <div class="a-fade" style="--d:12s">' . $whoHint . '</div>
                    </div>
                  </div>
                </div>

                <!-- 4 — navigation app -->
                <div class="sc" data-scene="4" data-len="' . $L(4) . '">
                  <div class="flash a-pop" style="--d:21.5s">Address links will now open in Waze.</div>
                  <div class="split">
                    <div class="secbox"><span class="flab">&#129517; Navigation app</span>
                      <span class="a-ring" style="--d:4s;border-radius:6px;display:inline-block">' . $radio('Google Maps', '<i class="a-out" style="--d:15.5s"></i>') . '</span>'
                      . $radio('Waze', '<i class="a-pop" style="--d:15.8s"></i>') . '
                      <div class="a-fade" style="--d:8s">' . $mapHint . '</div>' . $save('a-press a-ring', '--d:19s') . '</div>
                    <div class="side2">
                      <div class="a-rise" style="--d:9.5s"><span class="lab">My Schedule</span><span class="addr a-ring" style="--d:11s">&#128205; 4 Mill Lane, Kendal LA9 4QT</span></div>
                      <span class="chip a-pop" style="--d:12.5s">&rarr; opens in the app you pick</span>
                      <span class="chip good a-pop" style="--d:16s">&#128663; Waze: live traffic</span>
                    </div>
                  </div>
                </div>

                <!-- 5 — the run map exception -->
                <div class="sc" data-scene="5" data-len="' . $L(5) . '">
                  <div class="sct a-fade" style="--d:.2s">Today&rsquo;s run</div>
                  <div class="split">
                    <div class="map a-rise" style="--d:2s">
                      <svg viewBox="0 0 200 90" preserveAspectRatio="none" aria-hidden="true"><path class="a-draw" style="--d:3.5s" pathLength="100" d="M15 70 C 50 20, 80 80, 110 40 S 170 30, 185 15" fill="none" stroke="#4285f4" stroke-width="3"/>
                        <circle cx="15" cy="70" r="4" fill="#ea4335"/><circle cx="110" cy="40" r="4" fill="#ea4335"/><circle cx="185" cy="15" r="4" fill="#ea4335"/></svg>
                      <span class="gtag">Google</span></div>
                    <div class="side2">
                      <span class="chip a-pop" style="--d:6s">The route map inside the page &rarr; always Google</span>
                      <span class="chip a-pop" style="--d:9s">Waze can&rsquo;t be shown inside a page</span>
                      <div class="a-rise" style="--d:12s"><span class="addr">&#128205; 4 Mill Lane, Kendal</span></div>
                      <span class="chip good a-pop" style="--d:14s">Tapped addresses &rarr; the app you chose</span>
                    </div>
                  </div>
                </div>

                <!-- 6 — exact time or a time slot -->
                <div class="sc" data-scene="6" data-len="' . $L(6) . '">
                  <div class="secbox a-rise" style="--d:.5s"><label class="ckl"><span class="tick a-sel" style="--d:13.5s">&#10003;</span> &#128344; Booking time slots</label></div>
                  <div class="two" style="margin-top:.7rem">
                    <div class="bk a-rise" style="--d:6s"><div class="ttl">Unticked &mdash; an exact time</div>
                      <div class="bkrow"><div><span class="lab">Time <span class="req">*</span></span><div class="inp">' . $type('11:10', 9, 5, .5) . '</div></div>
                        <div><span class="lab">Duration (mins)</span><div class="inp">' . $type('60', 10.5, 2, .3) . '</div></div></div></div>
                    <div class="bk a-rise" style="--d:14s"><div class="ttl">Ticked &mdash; a time slot</div><span class="lab">Time slot <span class="req">*</span></span>
                      <div class="opts"><div class="opt a-fly" style="--d:15s"><span class="rd"></span>Morning <span class="rg">(9am&ndash;1pm)</span></div>
                        <div class="opt a-fly" style="--d:15.6s"><span class="rd"></span>Afternoon <span class="rg">(1pm&ndash;5pm)</span></div></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:19.5s">The customer gets a time slot, never an exact hour</span>
                    <span class="chip good a-pop" style="--d:23.5s">Fittings unaffected</span></div>
                </div>

                <!-- 7 — your time slots -->
                <div class="sc" data-scene="7" data-len="' . $L(7) . '">
                  <div class="secbox"><label class="ckl">' . $tk . ' &#128344; Booking time slots</label>
                    ' . $row('<span class="a-ring" style="--d:13s">Morning</span>', '<span class="stack"><span class="a-out" style="--d:20s">09:00</span><span class="a-fade" style="--d:20.2s">08:00</span></span>',
                             '<span class="stack"><span class="a-out" style="--d:20.8s">13:00</span><span class="a-fade" style="--d:21s">12:30</span></span>', '4', 'a-fly', '--d:4s') . '
                    ' . $row('Afternoon', '13:00', '17:00', '4', 'a-fly', '--d:7s') . '
                    <div class="fact"><span class="btns sm">+ Add a time slot</span></div>' . $save() . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:12.5s">Name = what the customer sees</span>
                    <span class="chip a-pop" style="--d:15.5s">From &middot; To</span>
                    <span class="chip a-pop" style="--d:17.5s">Bookings / day</span>
                    <span class="chip good a-pop" style="--d:20.5s">Your own hours</span>
                  </div>
                </div>

                <!-- 8 — bookings per day -->
                <div class="sc" data-scene="8" data-len="' . $L(8) . '">
                  <div class="secbox"><label class="ckl">' . $tk . ' &#128344; Booking time slots</label>
                    ' . $row('Morning', '08:00', '12:30', '<span class="stack"><span class="a-out" style="--d:6s">4</span><span class="a-fade" style="--d:6.3s">6</span></span>', 'a-ring', '--d:5.5s;border-radius:8px') . '
                    ' . $row('Afternoon', '13:00', '17:00', '<span class="stack"><span class="a-out" style="--d:9s">4</span><span class="a-fade" style="--d:9.3s">3</span></span>', 'a-ring', '--d:8.5s;border-radius:8px') . '
                  </div>
                  <div class="bk a-rise" style="--d:13s;margin-top:.6rem;max-width:22rem"><div class="ttl">Thu 24 Sep</div>
                    <div class="lane"><b>Morning</b><span class="dots"><i class="f"></i><i class="f"></i><i class="f"></i><i class="f"></i><i class="f"></i><i class="a-pop f" style="--d:15s"></i></span><span class="chip bad a-pop" style="--d:16s;padding:.1rem .45rem">Full</span></div>
                    <div class="lane"><b>Afternoon</b><span class="dots"><i class="f"></i><i></i><i></i></span><span style="color:var(--good);font-weight:700;font-size:.64rem">2 of 3 left</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:3s">1 to 99</span><span class="chip good a-pop" style="--d:19s">Never promise more visits than you can do</span></div>
                </div>

                <!-- 9 — add / remove -->
                <div class="sc" data-scene="9" data-len="' . $L(9) . '">
                  <div class="secbox"><label class="ckl">' . $tk . ' &#128344; Booking time slots</label>
                    ' . $row('Morning', '08:00', '12:30', '6') . $row('Afternoon', '13:00', '17:00', '3', '', '', 'a-ring" style="--d:12.5s') . '
                    <div class="a-drop" style="--d:3.5s">' . $row($type('Evening', 5.5, 7, .5), $type('18:00', 7, 5, .4), $type('20:00', 8, 5, .4), '2') . '</div>
                    <div class="fact"><span class="btns sm a-press a-ring" style="--d:2s">+ Add a time slot</span></div>' . $save('a-press', '--d:20s') . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:10s">Up to 6 time slots</span>
                    <span class="chip a-pop" style="--d:15s">Removed? Its bookings keep their label</span>
                    <span class="chip bad a-pop" style="--d:18s">Keep at least one</span>
                  </div>
                </div>

                <!-- 10 — saving -->
                <div class="sc" data-scene="10" data-len="' . $L(10) . '">
                  <div class="flash a-pop" style="--d:5s">Booking time slots saved.</div>
                  <div class="secbox a-rise" style="--d:.5s;max-width:22rem"><span class="lab" style="margin-bottom:.3rem">In time order:</span>
                    <div class="lane a-fly" style="--d:2s"><b>Morning</b>8am&ndash;12:30pm</div>
                    <div class="lane a-fly" style="--d:2.4s"><b>Afternoon</b>1pm&ndash;5pm</div>
                    <div class="lane a-fly" style="--d:2.8s"><b>Evening</b>6pm&ndash;8pm</div></div>
                  <div class="flash err a-fly" style="--d:9s;margin-top:.7rem">Time slots not saved: &hellip;</div>
                  <div class="flash err a-fly" style="--d:14.5s">Time slots not saved: Every time slot needs a name.</div>
                  <div class="flash err a-fly" style="--d:17s">Time slots not saved: &ldquo;Evening&rdquo; needs a From time before its To time.</div>
                </div>

                <!-- 11 — booking with time slots -->
                <div class="sc" data-scene="11" data-len="' . $L(11) . '">
                  <div class="flash a-pop" style="--d:24.5s">Appointment booked for Mrs Patel on 24 Sep 2026, Morning (8am&ndash;12:30pm).</div>
                  <div class="bk a-rise" style="--d:1s"><div class="lg">Appointment</div>
                    <div class="bkrow"><div><span class="lab">Date <span class="req">*</span></span><div class="inp">24/09/2026</div></div>
                      <div><span class="lab">Assigned to</span><div class="inp dd">Sarah Jones</div></div></div>
                    <span class="lab" style="margin-top:.45rem">Time slot <span class="req">*</span></span>
                    <div class="opts">
                      <div class="opt a-fly" style="--d:6s"><span class="rd"><i class="a-pop" style="--d:21s"></i></span>Morning <span class="rg">(8am&ndash;12:30pm)</span><span class="cnt">4 of 6 left</span></div>
                      <div class="opt full a-fly" style="--d:6.6s"><span class="rd"></span>Afternoon <span class="rg">(1pm&ndash;5pm)</span><span class="cnt a-ring" style="--d:13s">Full</span></div>
                      <div class="opt a-fly" style="--d:7.2s"><span class="rd"></span>Evening <span class="rg">(6pm&ndash;8pm)</span><span class="cnt">2 of 2 left</span></div>
                    </div>
                    <p class="hintg">The customer is given this time slot, never an exact time. Each time slot holds a set number of quote visits per day (change the times and limits in Settings &rarr; Calendar).</p>
                    <label class="ckl a-fade" style="--d:17s;margin-top:.4rem;font-weight:400;font-size:.68rem">' . $tk . ' Email the customer their appointment time slot (needs an email above)</label>
                    <div class="fact"><span class="btnp a-press a-ring" style="--d:23s">Book appointment</span></div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where to find it.</b> <b>Setup &rarr; Settings</b>, on the <b>Company</b> tab. Scroll right to the bottom, past
             <i>Company details</i>, <i>Company logo</i> and <i>Dashboard</i>: the last section is <b>Calendar</b>. It looks like one
             box, but it is <b>three separate forms</b>, each ending in its <b>own Save</b>. Change one, save that one &mdash; saving
             one never touches the other two.</p>

          <p><b>&#128183; Show order value + balance on the calendar.</b> One tick box. Unticked, your calendar just shows the
             appointments. Ticked, every job that is linked to a quote carries a money line on the <b>month, week and day</b>
             calendars. A job still owing reads <code>&pound;540.00 &middot; paid &pound;200.00 &middot; bal &pound;340.00</code>; once
             settled it reads <code>&check; PAID &pound;540.00 &middot; bal &pound;0.00</code>. &ldquo;Paid&rdquo; is the <b>deposit plus
             every payment logged against that quote</b>, so it keeps itself up to date. Save gives you <b>&ldquo;Calendar will show
             order value + balance.&rdquo;</b>; untick and save for <b>&ldquo;Calendar money figures are now hidden.&rdquo;</b></p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Who sees the figures.</b> Only people allowed to see money:
             <b>admins</b>, plus anyone with <b>&ldquo;Can see money&rdquo;</b> ticked on the <b>Users</b> page. Everyone else &mdash;
             fitters, for instance &mdash; sees the same calendar without the money line.</div></div>

          <p><b>&#129517; Navigation app.</b> Two radio buttons: <b>Google Maps</b> (the default) and <b>Waze</b>. Whichever you pick
             is what opens when someone <b>taps an address</b> on <b>My Schedule</b> or the <b>day calendar</b> &mdash; pick Waze if
             your fitters want its live traffic and routing. Save says <b>&ldquo;Address links will now open in Waze.&rdquo;</b> or
             <b>&ldquo;Address links will now open in Google Maps.&rdquo;</b> One exception: the <b>route map drawn inside Today&rsquo;s
             run</b> is always Google, because Waze can&rsquo;t be embedded in a page. The tappable addresses still go where you
             chose.</p>

          <p><b>&#128344; Booking time slots</b> &mdash; how a <b>quote (measure) visit</b> is booked.</p>
          <ul class="steps">
            <li><b>Unticked</b> (the default): <b>Book appointment</b> asks for an exact <b>Time</b> and a <b>Duration (mins)</b>.</li>
            <li><b>Ticked</b>: you offer a <b>time slot</b> instead &mdash; the customer is given the time slot, never an exact hour.
                <b>Fittings are unaffected</b>.</li>
            <li><b>One row per time slot</b>, each with <b>Name</b> (what the customer sees), <b>From</b> and <b>To</b> (time boxes &mdash;
                use the little clock or type <code>08:00</code>) and <b>Bookings / day</b> (<b>1 to 99</b>). Out of the box:
                <b>Morning 09:00&ndash;13:00</b> and <b>Afternoon 13:00&ndash;17:00</b>, <b>4 a day</b> each.</li>
            <li><b>Every limit is separate</b> &mdash; six mornings and three afternoons is fine. Once a time slot is full for a day it
                can&rsquo;t be booked.</li>
            <li><b>+ Add a time slot</b> adds an empty row (its Bookings / day starts at 2) &mdash; e.g. <i>Evening</i>, 18:00 to 20:00.
                Up to <b>six</b>; the button stops working at six. <b>&#10005; Remove</b> takes a row away (not the last one); bookings
                already in a removed time slot keep their label.</li>
            <li><b>Save</b> puts the time slots in time order and says <b>&ldquo;Booking time slots saved.&rdquo;</b> (or <b>&ldquo;Booking
                time slots are off.&rdquo;</b> when unticked).</li>
          </ul>
          <div class="oops"><b>&ldquo;Time slots not saved: &hellip;&rdquo;</b> Nothing was changed; fix the one thing it names and
             Save again: <b>&ldquo;Every time slot needs a name.&rdquo;</b>, <b>&ldquo;&ldquo;Evening&rdquo; needs a From time before its
             To time.&rdquo;</b>, <b>&ldquo;Keep at least one time slot.&rdquo;</b> or <b>&ldquo;At most 6 time slots.&rdquo;</b></div>

          <p><b>Booking with windows.</b> On the calendar, <b>+ Book Appointment</b> opens <b>Book appointment</b>. Under
             <b>Appointment</b> you set the <b>Date</b> and <b>Assigned to</b>, and the time picker is replaced by <b>Time slot
             <span class="req">*</span></b>: one choice per time slot, with its hours and a live count for that day &mdash;
             <code>4 of 6 left</code>. Change the date and the counts follow. A time slot with no room left reads <code>Full</code> and
             can&rsquo;t be picked. Underneath is a tick, already on: <b>&ldquo;Email the customer their appointment time slot (needs an
             email above)&rdquo;</b>. Book it and you get, for example, <b>&ldquo;Appointment booked for Mrs Patel on 24 Sep 2026,
             Morning (8am&ndash;12:30pm).&rdquo;</b></p>
          <div class="oops"><b>Two refusals on the booking screen.</b> No time slot chosen: <b>&ldquo;Please choose a time slot.&rdquo;</b>
             The time slot filled up while you were typing: <b>&ldquo;Afternoon (1pm&ndash;5pm) is fully booked on 24 Sep 2026. Please choose
             another time slot or another day.&rdquo;</b> Neither loses your typing.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Worth knowing.</b>
             <br>&bull; <b>Set your time slots before you start booking.</b> A time slot&rsquo;s wording comes from <i>today&rsquo;s</i>
             settings, so if you change Morning&rsquo;s hours, bookings already in Morning describe themselves with the new hours.
             <br>&bull; If a save fails with <b>&ldquo;Could not save: &hellip; &mdash; have you run &hellip;?&rdquo;</b>, a database update
             hasn&rsquo;t been run yet &mdash; nothing you can fix here; tell whoever looks after the system.
             <br>&bull; With <b>Compact mode</b> on, the grey explanations under each setting are hidden.</div></div>',
        'script'  => $script,
];
