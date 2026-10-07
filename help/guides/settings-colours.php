<?php
declare(strict_types=1);

/**
 * Guide: settings-colours — "Status colours" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors Settings → Status colours (admin/settings.php, POST
 * _action=status_colours): thirteen stages in three groups from
 * _partials/job_status_colours.php (job_status_groups / job_status_labels /
 * job_status_defaults), one native colour picker + sample pill per stage,
 * pill text black-or-white by luma (job_status_text_colour), "Save status
 * colours" → "Status colours saved." Used by the calendar (cards, key, the
 * Issue ring + Issues filter), the orders list and the Pipeline board.
 *
 * v2: one scene per script line; data-len is worked out from the line's own
 * length (characters ÷ 13.6), so editing a line keeps its scene in step.
 */

$vo = [
    1 => ['Your traffic-light colours',
          'Status colours is a tab in Settings. It sets your traffic-light colours. Every job wears a colour for the stage it has reached. The same colour follows the job everywhere, on the calendar and in your orders list. And it changes by itself as the job moves on, so you never colour a job by hand.'],
    2 => ['Thirteen stages, three groups',
          'There are thirteen stages, in three groups. Quote stages is the life of a quote: drafted, sent, accepted, declined and ordered. Appointments and job covers the visits and the work, from appointment booked right through to paid. And Flags has just one, called Issue.'],
    3 => ['Two visits, two colours',
          'Two of these are easy to mix up. Appointment booked is the measure visit, before there is a quote. Fitting booked is the install visit, after the quote is accepted. They are different days, for different jobs. So give them clearly different colours, and you can tell them apart at a glance.'],
    4 => ['Issue is a warning',
          'Issue is not a stage. It is a warning. Flag a job with an issue, and a ring in your Issue colour is drawn round its card on the calendar, on top of the stage colour it already has. So pick something loud for Issue, that nothing else uses.'],
    5 => ['Change a colour',
          'To change a colour, click the little colour square on its card. Your computer\'s own colour picker opens. It looks a little different on every computer, and that is normal. Pick a colour, or type a colour code, and choose OK.'],
    6 => ['The sample pill',
          'The sample pill beside it changes straight away, so you can see what you have done. The writing on it stays readable, too. Pale colours get dark writing, and dark colours get white writing. You never have to think about it.'],
    7 => ['Save status colours',
          'Nothing is saved until you press the button. Change as many colours as you like, then click Save status colours, at the bottom. A green bar says, Status colours saved. The page reloads, and brings you back to the Status colours tab.'],
    8 => ['See it on the calendar',
          'Now look at your calendar. Every job at that stage has changed colour by itself, and so has the little key along the top. Your orders list uses the same colours for its status pills. Your customers never see these colours. They are just for you and your team.'],
    9 => ['Two tips',
          'Two tips. Do not give two stages the same colour. Nothing stops you, but it spoils the whole point of the traffic lights. And go easy on very pale colours. On a busy calendar, a pale card can look like an empty space. There is no reset button, so note a colour\'s code before you change it.'],
];
$len = static fn (int $n): string => (string) round(mb_strlen($vo[$n][1]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The real stages, labels, groups and default colours (job_status_colours.php).
$C = [
    'draft' => ['Quote drafted', '#7c3aed'], 'sent' => ['Quote sent', '#f59e0b'], 'accepted' => ['Accepted', '#16a34a'],
    'declined' => ['Declined', '#dc2626'], 'ordered' => ['Ordered', '#0891b2'],
    'appointment_booked' => ['Appointment booked', '#2563eb'], 'booked' => ['Fitting booked', '#6366f1'], 'fitted' => ['Fitted', '#0d9488'],
    'invoiced' => ['Invoiced', '#ea580c'], 'paid' => ['Paid', '#475569'], 'cancelled' => ['Cancelled', '#b91c1c'], 'no_show' => ['No-show', '#9ca3af'],
    'issue' => ['Issue', '#e11d48'],
];
$G = [
    'Quote stages'       => ['draft', 'sent', 'accepted', 'declined', 'ordered'],
    'Appointments & job' => ['appointment_booked', 'booked', 'fitted', 'invoiced', 'paid', 'cancelled', 'no_show'],
    'Flags'              => ['issue'],
];
$txt = static function (string $hex): string {                 // same rule as job_status_text_colour()
    $h = ltrim($hex, '#');
    $l = 0.299 * hexdec(substr($h, 0, 2)) + 0.587 * hexdec(substr($h, 2, 2)) + 0.114 * hexdec(substr($h, 4, 2));
    return $l > 150 ? '#1f2937' : '#ffffff';
};
$pill = static fn (string $label, string $hex, string $cls = '', string $style = ''): string =>
    '<span class="pl ' . $cls . '" style="background:' . $hex . ';color:' . $txt($hex) . ';' . $style . '">' . $label . '</span>';
$card = static fn (string $key, string $cls = '', string $style = ''): string =>
    '<span class="cc ' . $cls . '" style="' . $style . '"><i class="sw" style="background:' . $C[$key][1] . '"></i>' . $pill($C[$key][0], $C[$key][1]) . '</span>';

// All three groups. $anim($key, $i, $group) → [class, style] for each card.
$groups = static function (?callable $anim = null, ?callable $head = null) use ($G, $card): string {
    $h = '';
    foreach ($G as $name => $keys) {
        [$hc, $hs] = $head ? $head($name) : ['', ''];
        $h .= '<div class="gh ' . $hc . '" style="' . $hs . '">' . htmlspecialchars($name) . '</div><div class="gr">';
        foreach ($keys as $i => $k) {
            [$c, $s] = $anim ? $anim($k, $i, $name) : ['', ''];
            $h .= $card($k, $c, $s);
        }
        $h .= '</div>';
    }
    return $h;
};

$tabs = static function (string $on, string $ring = ''): string {
    $h = '<div class="tabs">';
    foreach (['Company', 'Quoting', 'Legal', 'Status colours', 'Suppliers', 'Accounting', 'Back up data'] as $t) {
        $cls = 'tab' . ($t === $on ? ' on' : '');
        $h  .= $t === $on && $ring !== ''
            ? '<span class="' . $cls . ' a-ring" style="--d:' . $ring . '">' . $t . '</span>'
            : '<span class="' . $cls . '">' . $t . '</span>';
    }
    return $h . '</div>';
};

$script = [];
foreach ($vo as $n => [$cap, $line]) $script[] = [(string) $n, $cap, $line, $n];

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Status colours',
        'eyebrow' => 'Settings · Status colours',
        'v'       => 2,
        'blurb'   => 'Your traffic-light colours — one colour per job stage, shown on the calendar, your orders list and the Pipeline.',
        'lede'    => 'Every job in YourBlinds wears a colour for the stage it has reached, and this is where you choose those
                      colours. Pick them once and the same colour follows the job everywhere &mdash; on the <b>calendar</b>, in your
                      <b>orders list</b> and across the <b>Pipeline</b> &mdash; changing by itself as the job moves along. There are
                      <b>thirteen stages in three groups</b>. To get there: <b>Settings</b> &rarr; the <b>Status colours</b> tab.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; max-width:34rem; line-height:1.45; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin:0 0 .7rem; }
          .gd .tab{ font-size:.66rem; font-weight:600; color:var(--soft); padding:.28rem .45rem; border-radius:6px 6px 0 0; }
          .gd .tab.on{ color:var(--accent); box-shadow:inset 0 -2px 0 var(--accent); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:6px; padding:.2rem .6rem; font-size:.7rem; font-weight:600; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .row{ display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; } .gd .mt{ margin-top:.7rem; }
          .gd .stk{ display:inline-grid; } .gd .stk > *{ grid-area:1/1; }

          /* the colour cards */
          .gd .gh{ font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.35rem 0 .3rem; border-radius:4px; display:inline-block; }
          .gd .gr{ display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.35rem; }
          .gd .cc{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:8px; padding:.25rem .35rem; background:var(--surface); position:relative; }
          .gd .sw{ display:inline-block; width:1.35rem; height:1.35rem; border-radius:4px; box-shadow:inset 0 0 0 1px rgba(0,0,0,.08); }
          .gd .pl{ display:inline-block; padding:.05rem .45rem; font-size:.56rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; border-radius:999px; white-space:nowrap; }
          .gd .big .sw{ width:2rem; height:2rem; } .gd .big .pl{ font-size:.66rem; padding:.12rem .6rem; }

          /* mini calendar */
          .gd .cal{ display:grid; grid-template-columns:repeat(5,1fr); gap:3px; max-width:30rem; background:var(--line); border:1px solid var(--line); border-radius:8px; padding:3px; }
          .gd .cal > div{ background:var(--surface); min-height:3.6rem; padding:.2rem .25rem; font-size:.56rem; color:var(--faint); border-radius:4px; }
          .gd .ca{ display:block; border-radius:4px; padding:.12rem .25rem; margin-top:.2rem; font-size:.56rem; font-weight:700; white-space:nowrap; overflow:hidden; position:relative; }
          .gd .ca.fit{ box-shadow:inset 0 0 0 1.5px rgba(15,23,42,.55); }
          .gd .ca.iss{ outline:2px solid #e11d48; outline-offset:1px; }
          .gd .key{ display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; margin:0 0 .4rem; }
          .gd .key .pl{ font-size:.5rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; max-width:34rem; }
          .gd .vis{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); font-size:.7rem; color:var(--soft); }
          .gd .vis h4{ margin:.35rem 0 .2rem; font-size:.78rem; color:var(--ink); }

          /* the computer\'s colour box */
          .gd .osp{ position:absolute; z-index:5; left:12rem; top:5.6rem; width:12.5rem; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px;
                    box-shadow:0 14px 30px -12px rgba(15,23,42,.45); padding:.45rem; font-size:.62rem; color:#1f2937; }
          .gd .osp .grad{ height:5rem; border-radius:4px; background:linear-gradient(to top,#000,transparent),linear-gradient(to right,#fff,#9333ea); position:relative; }
          .gd .osp .dot{ position:absolute; width:10px; height:10px; border:2px solid #fff; border-radius:50%; box-shadow:0 0 0 1px rgba(0,0,0,.4); }
          .gd .osp .hue{ height:.55rem; border-radius:3px; margin:.35rem 0; background:linear-gradient(to right,red,#ff0,lime,cyan,blue,#f0f,red); }
          .gd .osp .hx{ display:flex; gap:.3rem; align-items:center; }
          .gd .osp .hx span.f{ flex:1; border:1px solid #cbd5e1; background:#fff; border-radius:4px; padding:.12rem .3rem; font-family:ui-monospace,Menlo,Consolas,monospace; }
          .gd .osp .ok{ background:#2563eb; color:#fff; border-radius:4px; padding:.12rem .55rem; font-weight:700; }

          /* orders list */
          .gd .ol{ border:1px solid var(--line); border-radius:9px; overflow:hidden; max-width:30rem; font-size:.66rem; }
          .gd .ol div{ display:flex; justify-content:space-between; align-items:center; padding:.28rem .5rem; border-top:1px solid var(--line); color:var(--ink); background:var(--surface); }
          .gd .ol div:first-child{ border-top:0; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .osp{ left:auto; right:0; } .gd .nophone{ display:none; }
            .gd .sc{ min-height:430px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / status colours</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a><a>Billing</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $tabs('Status colours') . '
                  ' . $groups() . '
                  <p class="scs" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; nine short chapters.</p>
                </div>

                <!-- 1 — traffic lights -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  ' . $tabs('Status colours', '2s') . '
                  <div class="a-move" style="--fx:70%;--fy:80%;--tx:10.6rem;--ty:.9rem;--d:.5s;--md:1.6s">' . $ptr . '</div>
                  <p class="scs a-fade" style="--d:3s">Your &ldquo;traffic-light&rdquo; colours. A job shows the same colour everywhere it appears &mdash; on the
                     <b>calendar</b> and in your <b>orders list</b> &mdash; and the calendar updates itself as the job moves from stage to stage.</p>
                  <div class="row" style="align-items:flex-start;gap:1rem">
                    <div class="a-rise" style="--d:7s"><div class="gh">Calendar</div>
                      <div class="cal" style="grid-template-columns:repeat(2,6.5rem)"><div>Tue 14<span class="ca stk" style="display:grid">
                          <span class="a-out" style="--d:17s;background:#f59e0b;color:#1f2937;border-radius:4px;padding:0 .2rem">10:00 Hall</span>
                          <span class="a-fade" style="--d:17s;background:#16a34a;color:#fff;border-radius:4px;padding:0 .2rem">10:00 Hall</span></span></div>
                        <div>Wed 15<span class="ca" style="background:#0891b2;color:#fff">14:00 Patel</span></div></div></div>
                    <div class="a-rise" style="--d:11s"><div class="gh">Orders list</div>
                      <div class="ol" style="width:13rem"><div><span>Mrs Hall</span><span class="stk">
                          <span class="a-out" style="--d:17s">' . $pill('Quote sent', '#f59e0b') . '</span><span class="a-fade" style="--d:17s">' . $pill('Accepted', '#16a34a') . '</span></span></div>
                        <div><span>Mr Patel</span>' . $pill('Ordered', '#0891b2') . '</div></div></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:12.5s">Same colour everywhere</span>
                    <span class="chip a-pop" style="--d:17.5s;border-color:var(--good)">Quote accepted &rarr; the colour changes by itself</span>
                  </div>
                </div>

                <!-- 2 — the three groups -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Thirteen stages, three groups</div>
                  ' . $groups(
                        static function ($k, $i, $g) {
                            $start = ['Quote stages' => 4, 'Appointments & job' => 11.5, 'Flags' => 16.5][$g];
                            return ['a-fly', '--d:' . ($start + $i * .55) . 's'];
                        },
                        static fn ($g) => ['a-ring', '--d:' . ['Quote stages' => 3, 'Appointments & job' => 10.5, 'Flags' => 16][$g] . 's']
                    ) . '
                </div>

                <!-- 3 — two visits -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">Two visits &mdash; two colours</div>
                  <div class="two" style="margin-top:.5rem">
                    <div class="vis a-rise" style="--d:3s">' . $card('appointment_booked', 'big') . '<h4>&#128207; The measure visit</h4>Before there is a quote.</div>
                    <div class="vis a-rise" style="--d:8.5s">' . $card('booked', 'big') . '<h4>&#128295; The install visit</h4>After the quote is accepted.</div>
                  </div>
                  <div class="cal a-rise mt" style="--d:13s">
                    <div>Mon 13<span class="ca" style="background:#2563eb;color:#fff">09:00 Measure &middot; Lee</span></div>
                    <div>Tue 14</div>
                    <div>Wed 15<span class="ca fit" style="background:#6366f1;color:#fff">10:00 Fit &middot; Hall</span></div>
                    <div>Thu 16<span class="ca" style="background:#2563eb;color:#fff">13:30 Measure &middot; Ross</span></div>
                    <div>Fri 17<span class="ca fit" style="background:#6366f1;color:#fff">09:00 Fit &middot; Patel</span></div>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:17s">Different days, different jobs &mdash; tell them apart at a glance</span></div>
                </div>

                <!-- 4 — issue -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">Issue is a warning, not a stage</div>
                  <div class="row" style="margin:.4rem 0 .7rem">' . $card('issue', 'big a-ring', '--d:14s') . '</div>
                  <div class="cal a-rise" style="--d:2s;grid-template-columns:repeat(3,1fr);max-width:22rem">
                    <div>Tue 14<span class="ca" style="background:#16a34a;color:#fff">10:00 Hall</span></div>
                    <div>Wed 15<span class="ca stk" style="display:grid">
                        <span class="a-out" style="--d:6s;background:#6366f1;color:#fff;border-radius:4px;padding:0 .2rem">14:00 Fit &middot; Patel</span>
                        <span class="iss a-pop" style="--d:6s;background:#6366f1;color:#fff;border-radius:4px;padding:0 .2rem;outline:2px solid #e11d48;outline-offset:1px">&#9888; 14:00 Fit &middot; Patel</span></span></div>
                    <div>Thu 16<span class="ca" style="background:#0d9488;color:#fff">09:00 Ross</span></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:8s">A ring in your Issue colour &mdash; on top of the stage colour</span>
                  </div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:15s">Pick something loud that nothing else uses</span></div>
                </div>

                <!-- 5 — change a colour -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">Click the little colour square</div>
                  <div class="gh">Quote stages</div>
                  <div class="gr">' . $card('draft') . $card('sent') . $card('accepted', 'a-ring', '--d:2.5s') . '</div>
                  <div class="a-move nophone" style="--fx:80%;--fy:95%;--tx:16.9rem;--ty:3.7rem;--d:.8s;--md:1.6s">' . $ptr . '</div>
                  <div class="osp a-pop" style="--d:3.5s">
                    <div class="grad"><span class="dot" style="right:12%;top:18%"></span></div>
                    <div class="hue"></div>
                    <div class="hx"><span class="f"><span class="a-type" style="--d:11s;--ts:7;--tt:.8s">#9333ea</span></span><span class="ok a-press" style="--d:15s">OK</span></div>
                  </div>
                  <div class="row" style="margin-top:9.6rem"><span class="chip a-pop" style="--d:6s">Your computer&rsquo;s own colour picker &mdash; it looks different on every computer</span></div>
                </div>

                <!-- 6 — the sample pill -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">The sample pill changes straight away</div>
                  <div class="row" style="margin:.5rem 0 .8rem">
                    <span class="cc big a-ring" style="--d:1.5s"><span class="stk"><i class="sw a-out" style="--d:2s;background:#16a34a"></i><i class="sw a-fade" style="--d:2s;background:#9333ea"></i></span>
                      <span class="stk"><span class="a-out" style="--d:2.5s">' . $pill('Accepted', '#16a34a') . '</span><span class="a-fade" style="--d:2.5s">' . $pill('Accepted', '#9333ea') . '</span></span></span>
                  </div>
                  <div class="row" style="align-items:flex-start;gap:1rem">
                    <div class="a-rise" style="--d:9s"><div class="gh" style="display:block">Pale colour</div><span class="cc big"><i class="sw" style="background:#fde68a"></i>' . $pill('Accepted', '#fde68a') . '</span>
                      <div class="scs" style="margin-top:.3rem">&rarr; dark writing</div></div>
                    <div class="a-rise" style="--d:12s"><div class="gh" style="display:block">Dark colour</div><span class="cc big"><i class="sw" style="background:#1e3a8a"></i>' . $pill('Accepted', '#1e3a8a') . '</span>
                      <div class="scs" style="margin-top:.3rem">&rarr; white writing</div></div>
                  </div>
                  <div class="row"><span class="chip a-pop" style="--d:15s">Always readable &mdash; worked out for you</span></div>
                </div>

                <!-- 7 — save -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="bnr a-drop" style="--d:10.5s">&#10003; Status colours saved.</div>
                  ' . $tabs('Status colours', '14s') . '
                  <div class="gh">Quote stages</div>
                  <div class="gr">' . $card('draft') . $card('sent') . '<span class="cc"><i class="sw" style="background:#9333ea"></i>' . $pill('Accepted', '#9333ea') . '</span>' . $card('declined') . '</div>
                  <div class="mt"><span class="btnp a-press" style="--d:8.5s">Save status colours</span>
                    <span class="chip a-pop" style="--d:2s;margin-left:.4rem">Nothing is saved until you press it</span></div>
                  <div class="a-move" style="--fx:80%;--fy:95%;--tx:6.5rem;--ty:9.6rem;--d:6.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 8 — on the calendar -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">See it on the calendar</div>
                  <div class="key a-fade" style="--d:7s">' . $pill('Quote sent', '#f59e0b') . '<span class="stk"><span class="a-out" style="--d:8s">' . $pill('Accepted', '#16a34a') . '</span><span class="a-fade" style="--d:8s">' . $pill('Accepted', '#9333ea') . '</span></span>'
                    . $pill('Ordered', '#0891b2') . $pill('Fitting booked', '#6366f1') . $pill('Paid', '#475569') . '</div>
                  <div class="cal a-rise" style="--d:1s">
                    <div>Mon 13<span class="ca stk" style="display:grid"><span class="a-out" style="--d:3.5s;background:#16a34a;color:#fff;border-radius:4px;padding:0 .2rem">09:00 Lee</span><span class="a-fade" style="--d:3.5s;background:#9333ea;color:#fff;border-radius:4px;padding:0 .2rem">09:00 Lee</span></span></div>
                    <div>Tue 14<span class="ca" style="background:#f59e0b;color:#1f2937">11:00 Ross</span></div>
                    <div>Wed 15<span class="ca stk" style="display:grid"><span class="a-out" style="--d:4.5s;background:#16a34a;color:#fff;border-radius:4px;padding:0 .2rem">14:00 Hall</span><span class="a-fade" style="--d:4.5s;background:#9333ea;color:#fff;border-radius:4px;padding:0 .2rem">14:00 Hall</span></span></div>
                    <div>Thu 16<span class="ca fit" style="background:#6366f1;color:#fff">10:00 Patel</span></div>
                    <div>Fri 17<span class="ca" style="background:#0891b2;color:#fff">13:00 Khan</span></div>
                  </div>
                  <div class="ol a-rise mt" style="--d:11s">
                    <div><span>Mrs Hall &middot; BRI-2026-0042</span>' . $pill('Accepted', '#9333ea') . '</div>
                    <div><span>Mr Khan &middot; BRI-2026-0039</span>' . $pill('Ordered', '#0891b2') . '</div>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:15s">&#128274; Customers never see these colours</span></div>
                </div>

                <!-- 9 — tips -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">Two tips</div>
                  <div class="two" style="margin-top:.5rem">
                    <div class="vis a-rise" style="--d:1.5s"><h4 style="margin-top:0">&#10007; Two stages, one colour</h4>
                      <div class="row">' . $pill('Accepted', '#0891b2') . $pill('Ordered', '#0891b2') . '</div>
                      <div style="margin-top:.35rem">Which is which?</div></div>
                    <div class="vis a-rise" style="--d:9s"><h4 style="margin-top:0">&#10007; Very pale</h4>
                      <div class="cal" style="grid-template-columns:repeat(2,1fr)"><div>Tue 14<span class="ca" style="background:#f8fafc;color:#94a3b8">10:00 Hall</span></div><div>Wed 15</div></div>
                      <div style="margin-top:.35rem">Looks like an empty day.</div></div>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:16s">No reset button &mdash; note the code first</span>
                    <span class="chip a-pop" style="--d:18s">Accepted <code>#16a34a</code></span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Settings</b> (in the <b>Setup</b> group of the sidebar) &rarr; the <b>Status colours</b> tab, fourth
             along. The line at the top reads: <em>&ldquo;Your &lsquo;traffic-light&rsquo; colours. A job shows the same colour everywhere it
             appears &mdash; on the calendar and in your orders list &mdash; and the calendar updates itself as the job moves from stage to stage.
             Pick a colour for each stage below; the sample pill updates as you go.&rdquo;</em> The colours are for the whole company, and your
             <b>customers never see them</b>.</p>

          <ul class="steps">
            <li><b>Quote stages</b> &mdash; <b>Quote drafted</b>, <b>Quote sent</b>, <b>Accepted</b>, <b>Declined</b>, <b>Ordered</b>.</li>
            <li><b>Appointments &amp; job</b> &mdash; <b>Appointment booked</b>, <b>Fitting booked</b>, <b>Fitted</b>, <b>Invoiced</b>,
                <b>Paid</b>, <b>Cancelled</b>, <b>No-show</b>.</li>
            <li><b>Flags</b> &mdash; <b>Issue</b>.</li>
          </ul>

          <p class="prose"><b>Appointment booked</b> is the <b>measure</b> visit (no quote yet); <b>Fitting booked</b> is the <b>install</b> visit,
             once the quote is accepted or ordered. A calendar entry takes its colour from the stage of the job it belongs to, so you never set
             it by hand &mdash; and marking an appointment <b>Cancelled</b> or a <b>No-show</b> always wins.</p>
          <p class="prose"><b>Issue is a warning, not a stage.</b> Flagging a job draws a <b>ring</b> in your Issue colour round its calendar card,
             on top of its stage colour, and colours the calendar&rsquo;s <b>Issues</b> filter button. Pick a loud colour nothing else uses.</p>

          <ul class="steps">
            <li><b>Click the little colour square</b> on a stage&rsquo;s card (the square, not the pill).</li>
            <li><b>Your computer&rsquo;s own colour picker opens</b> &mdash; not part of YourBlinds, so it looks different on Windows, Mac and
                different browsers. Pick a colour or type a code such as <code>#9333ea</code>, then <b>OK</b>.</li>
            <li><b>The sample pill changes straight away.</b> The writing on it picks itself: light colours get dark writing, dark colours get
                white.</li>
            <li><b>Save status colours</b> &mdash; nothing is saved until you press it. You&rsquo;ll see <b>&ldquo;Status colours saved.&rdquo;</b>;
                the page reloads and reopens on the <b>Status colours</b> tab (the last tab is remembered in this browser).</li>
          </ul>

          <p><b>Where to check.</b> The <b>calendar</b> &mdash; the cards and the little key along the top are built from this list (fittings carry a
             dark outline so you can tell them from measures). The same colours are the status pills in your <b>orders list</b> and the columns of the
             <b>Pipeline</b>. On the Pipeline, the <b>Quote</b> column holds drafted and sent quotes together in your <b>Quote sent</b> colour.</p>

          <p class="prose"><b>Putting a colour back.</b> There is no reset button &mdash; type the standard code back in. The standard colours:
             Quote drafted <code>#7c3aed</code>, Quote sent <code>#f59e0b</code>, Accepted <code>#16a34a</code>, Declined <code>#dc2626</code>,
             Ordered <code>#0891b2</code>, Appointment booked <code>#2563eb</code>, Fitting booked <code>#6366f1</code>, Fitted <code>#0d9488</code>,
             Invoiced <code>#ea580c</code>, Paid <code>#475569</code>, Cancelled <code>#b91c1c</code>, No-show <code>#9ca3af</code>,
             Issue <code>#e11d48</code>.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Two tips.</b> Don&rsquo;t give two stages the <b>same colour</b> &mdash; nothing
             stops you, but it spoils the traffic lights. And go easy on <b>very pale</b> shades: on a busy calendar a near-white card reads as an
             empty day.</div></div>

          <div class="oops"><b>Nothing to type wrong.</b> A colour box can only hold a real colour. The only other message you might see is
             <code>Could not save colours: &hellip; &mdash; have you run migrate_job_status_colours.php?</code> &mdash; show it to whoever looks after
             your setup.</div>',
        'script'  => $script,
];
