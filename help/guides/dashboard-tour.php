<?php
declare(strict_types=1);

/**
 * Guide: dashboard-tour — "Reading your dashboard" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Covers the DASHBOARD SCREEN itself (/dashboard/index.php): the subtitle
 * line, + New quote, the Joke of the day strip, the period bar and custom
 * From/To range, the View: salesperson filter, Upcoming jobs (incl. the
 * "Earlier today, not closed" group), the four KPI tiles, Sales team /
 * Their numbers + Revenue share, What's selling, Gross profit and Recent wins
 * — and who sees what (user_can_see_money = "Can see money", the
 * dash_view_* panel ticks, View costs). The window is measured on
 * quotes.created_at; Revenue (won) = quotes.total (VAT + WT charge
 * included); Gross profit = net sell − price-table cost basis, less any
 * agreed-price discount. Every label and message is copied from that file.
 *
 * v2: one SCENE per script line (data-scene = the line's step).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$head = static fn (string $sub, string $subCls = '', string $subStyle = ''): string =>
    '<div class="dhead"><div><div class="pgh">Dashboard</div><div class="pgs ' . $subCls . '" style="' . $subStyle . '">' . $sub . '</div></div>'
  . '<span class="newq">+ New quote</span></div>';

// Period buttons; $on = which is active; $sel = [label => start time] to animate selection.
$periods = static function (string $on = 'This month', array $sel = []): string {
    $h = '<div class="pgrp">';
    foreach (['This month', 'Last 30 days', 'This quarter', 'This year', 'All time'] as $p) {
        if (isset($sel[$p]))  $h .= '<span class="pbtn a-sel" style="--d:' . $sel[$p] . 's">' . $p . '</span>';
        elseif ($p === $on)   $h .= '<span class="pbtn on">' . $p . '</span>';
        else                  $h .= '<span class="pbtn">' . $p . '</span>';
    }
    return $h . '</div>';
};
$range = static fn (string $from = '07/09/2026', string $to = '07/10/2026', string $apply = ''): string =>
    '<div class="crange"><span class="dlbl">From</span><span class="dbox">' . $from . '</span><span class="dlbl">To</span><span class="dbox">' . $to . '</span>'
  . '<span class="applybtn ' . $apply . '">Apply</span></div>';

$viewSel = static fn (string $inner): string =>
    '<div class="pfilter"><span class="dlbl">View:</span><span class="selectbox vsb">' . $inner . '</span></div>';

$sw = static fn (string $old, string $new, float $d): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $d . 's">' . $old . '</span><span class="a-fade" style="--d:' . ($d + .1) . 's">' . $new . '</span></span>';

$up = static fn (string $day, string $time, string $name, string $pc, string $fitter, string $q, string $cls = '', string $style = ''): string =>
    '<div class="uprow ' . $cls . '" style="' . $style . '"><div><div class="update' . ($day === 'Today' ? ' today' : '') . '">' . $day . '</div><div class="uptime">' . $time . '</div></div>'
  . '<div><div class="upname">' . $name . '</div><div class="upplace">' . $pc . '</div></div>'
  . '<div class="upfit">' . $fitter . '</div><div class="upq">' . $q . '</div></div>';

$kpis = static fn (array $anim = []): string =>
    '<div class="kgrid">'
  . '<div class="ktile rev ' . ($anim[0] ?? '') . '"><div class="klbl">Revenue (won)</div><div class="kval">&pound;16,290.00</div><div class="ksub">12 jobs accepted</div></div>'
  . '<div class="ktile ' . ($anim[1] ?? '') . '"><div class="klbl">Average order value</div><div class="kval">&pound;1,357.50</div><div class="ksub">across won quotes</div></div>'
  . '<div class="ktile rate ' . ($anim[2] ?? '') . '"><div class="klbl">Close rate</div><div class="kval">60.0%</div><div class="ksub">12 of 20 decided</div></div>'
  . '<div class="ktile ' . ($anim[3] ?? '') . '"><div class="klbl">Jobs in period</div><div class="kval">12</div><div class="ksub">accepted &amp; beyond</div></div>'
  . '</div>';

$donut = static fn (array $segs, int $size = 80, string $cls = '', string $style = ''): string =>
    '<svg class="' . $cls . '" style="' . $style . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 76 76" aria-hidden="true"><g transform="rotate(-90 38 38)" fill="none" stroke-width="15">'
  . implode('', array_map(static fn ($s) => '<circle cx="38" cy="38" r="30" stroke="' . $s[0] . '" stroke-dasharray="' . $s[1] . ' 188.5" stroke-dashoffset="-' . $s[2] . '"></circle>', $segs))
  . '</g></svg>';
$teamDonut = [['#1f3b5b', 97.5, 0], ['#15803d', 59.2, 97.5], ['#f59e0b', 31.8, 156.7]];
$mixDonut  = [['#1f3b5b', 86.7, 0], ['#15803d', 59.9, 86.7], ['#f59e0b', 41.9, 146.6]];

return [
        'aud'     => 'admin',
        'section' => 'Dashboard',
        'title'   => 'Reading your dashboard',
        'eyebrow' => 'Dashboard',
        'v'       => 2,
        'blurb'   => 'Every panel on the Dashboard, what it is counting, and the two numbers people always read wrong.',
        'lede'    => 'The Dashboard is your <b>scoreboard</b>, not a to-do list. Apart from <b>Upcoming jobs</b>, everything on it answers
                      one question &mdash; <em>how did we do in this window of time?</em> &mdash; and you choose the window with the
                      buttons across the top. The bit that catches everybody out: the window counts a job by the day the <b>quote was
                      started</b>, not the day the customer said yes. This guide takes it one panel at a time. To get there: <b>Work</b>
                      &rarr; <b>Dashboard</b> in the sidebar.',
        'open'    => '/dashboard/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .3rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .6rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }

          /* header */
          .gd .dhead{ display:flex; align-items:flex-start; justify-content:space-between; gap:.8rem; margin-bottom:.5rem; }
          .gd .pgh{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .pgs{ font-size:.72rem; color:var(--faint); margin:.1rem 0 0; border-radius:5px; }
          .gd .pgs b{ color:var(--soft); }
          .gd .newq{ background:var(--accent); color:#fff; border-radius:8px; padding:.3rem .65rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
          .gd .jotd{ display:flex; align-items:center; gap:.5rem; background:linear-gradient(90deg,#fffbeb,#fef9c3); border:1px solid #fde68a; border-radius:10px; padding:.4rem .55rem; margin-bottom:.5rem; }
          :root[data-theme="dark"] .gd .jotd{ background:rgba(250,204,21,.08); border-color:rgba(250,204,21,.3); }
          .gd .jlbl{ font-size:.56rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:#b45309; }
          .gd .jtxt{ font-size:.7rem; color:var(--ink); }
          .gd .jbtn{ font-size:.64rem; color:#b45309; padding:.1rem .3rem; border-radius:6px; white-space:nowrap; }

          /* period bar */
          .gd .pbar{ display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; margin-bottom:.5rem; }
          .gd .pgrp{ display:inline-flex; flex-wrap:wrap; gap:.3rem; }
          .gd .pbtn{ display:inline-flex; align-items:center; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; padding:.24rem .5rem;
                     font-size:.68rem; color:var(--soft); background:var(--surface); white-space:nowrap; }
          .gd .pbtn.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .crange{ display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .35rem; background:var(--panel); border:1px solid var(--line); border-radius:8px; flex-wrap:wrap; }
          .gd .dlbl{ font-size:.58rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
          .gd .dbox{ display:inline-grid; align-items:center; height:22px; min-width:5.4rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                     background:var(--surface); padding:0 .35rem; font-size:.66rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .dbox > span{ grid-area:1/1; }
          .gd .applybtn{ background:var(--accent); color:#fff; border-radius:6px; padding:.18rem .5rem; font-size:.64rem; font-weight:700; }
          .gd .pfilter{ display:inline-flex; align-items:center; gap:.4rem; padding:.22rem .45rem; background:var(--panel); border:1px solid var(--line); border-radius:8px; margin-bottom:.5rem; }
          .gd .vsb{ min-width:9rem; padding:.2rem .45rem; font-size:.7rem; }

          /* panels */
          .gd .pnl{ border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.5rem .65rem; margin-bottom:.5rem; max-width:34rem; }
          .gd .pnl h4{ margin:0; font-size:.8rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.62rem; color:var(--faint); margin:.1rem 0 .4rem; }
          .gd .pfoot{ font-size:.64rem; color:var(--accent); font-weight:600; margin-top:.3rem; }
          .gd .ghead{ font-size:.56rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:var(--soft); margin:.3rem 0 .1rem; }
          .gd .uprow{ display:grid; grid-template-columns:3.8rem 1fr 4.6rem 7.8rem; gap:.4rem; align-items:center; padding:.26rem .2rem; border-bottom:1px solid var(--line-2); font-size:.66rem; }
          .gd .update{ font-weight:700; color:var(--ink); font-size:.66rem; } .gd .update.today{ color:#ef4444; }
          .gd .uptime{ color:var(--faint); font-size:.6rem; }
          .gd .upname{ font-weight:600; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .upplace{ color:var(--faint); font-size:.6rem; }
          .gd .upfit{ color:var(--soft); font-size:.64rem; } .gd .upfit i{ color:var(--faint); }
          .gd .upq{ display:flex; gap:.3rem; align-items:center; font-size:.6rem; color:var(--ink); }
          .gd .upq code{ white-space:nowrap; font-size:.56rem; color:var(--faint); background:none; padding:0; }

          .gd .kgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; max-width:34rem; margin-bottom:.4rem; }
          .gd .ktile{ border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.45rem .5rem; }
          .gd .klbl{ font-size:.54rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .kval{ font-size:1rem; font-weight:800; color:var(--ink); line-height:1.2; margin-top:.1rem; font-variant-numeric:tabular-nums; }
          .gd .ktile.rev .kval{ color:#16a34a; } .gd .ktile.rate .kval{ color:#d97706; }
          .gd .ksub{ font-size:.56rem; color:var(--faint); margin-top:.1rem; }

          .gd .lbflex{ display:flex; gap:.7rem; align-items:center; flex-wrap:wrap; }
          .gd .lbwrap{ flex:1 1 18rem; min-width:0; }
          .gd table.lbt{ width:100%; border-collapse:collapse; font-size:.64rem; }
          .gd table.lbt th, .gd table.lbt td{ text-align:left; padding:.22rem .25rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
          .gd table.lbt th{ font-size:.52rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); background:var(--panel); }
          .gd table.lbt .num{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd table.lbt td.who{ color:var(--ink); font-weight:600; white-space:nowrap; }
          .gd .phead{ font-size:.54rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.2rem; }
          .gd .colhi{ background:var(--accent-wash) !important; }

          .gd .mixrow{ display:grid; grid-template-columns:1fr 3.4rem 4.2rem; gap:.35rem; align-items:center; padding:.24rem 0; border-bottom:1px solid var(--line-2); }
          .gd .mixname{ font-size:.66rem; font-weight:600; color:var(--ink); }
          .gd .mixbar{ position:relative; height:5px; background:var(--panel); border-radius:999px; margin-top:.15rem; overflow:hidden; }
          .gd .mixbar span{ position:absolute; left:0; top:0; bottom:0; border-radius:999px; background:var(--accent); }
          .gd .mixnum{ text-align:right; font-size:.62rem; color:var(--soft); font-variant-numeric:tabular-nums; }

          .gd .mgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:.4rem; }
          .gd .mval{ font-size:.86rem; font-weight:700; color:#16a34a; font-variant-numeric:tabular-nums; }
          .gd .mval.cog{ color:#92400e; }

          .gd .rrow{ display:grid; grid-template-columns:1fr 5rem 4.6rem 4.2rem; gap:.35rem; align-items:center; padding:.24rem .1rem; border-bottom:1px solid var(--line-2); font-size:.64rem; color:var(--soft); }
          .gd .rrow a{ color:var(--accent); font-weight:700; }
          .gd .rdate{ color:var(--faint); font-size:.6rem; }
          .gd .rrev{ text-align:right; color:#16a34a; font-weight:700; font-variant-numeric:tabular-nums; }

          /* 4 — the timeline */
          .gd .tl{ position:relative; max-width:32rem; height:5.2rem; margin:.6rem 0 .4rem; }
          .gd .tl .axis{ position:absolute; left:0; right:0; top:2.6rem; height:2px; background:var(--line); }
          .gd .tl .mon{ position:absolute; top:2.95rem; font-size:.62rem; font-weight:700; color:var(--soft); }
          .gd .tl .band{ position:absolute; top:2.2rem; height:.9rem; border-radius:4px; }
          .gd .tl .pin{ position:absolute; top:.4rem; transform:translateX(-50%); font-size:.6rem; font-weight:700; text-align:center; white-space:nowrap; }
          .gd .tl .pin i{ display:block; width:2px; height:1.4rem; margin:.15rem auto 0; background:currentColor; }

          /* 15 — who sees what */
          .gd .who3{ display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; max-width:34rem; }
          .gd .wcard{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); font-size:.64rem; color:var(--soft); line-height:1.45; }
          .gd .wcard h5{ margin:0 0 .3rem; font-size:.74rem; color:var(--ink); }

          @media (max-width:640px){
            .gd .sc{ min-height:470px; }
            .gd .kgrid, .gd .mgrid{ grid-template-columns:1fr 1fr; }
            .gd .uprow{ grid-template-columns:3.4rem 1fr 4.4rem; } .gd .upq{ display:none; }
            .gd .rrow{ grid-template-columns:1fr 4.2rem; } .gd .rrow .ru, .gd .rrow .rdate{ display:none; }
            .gd .who3{ grid-template-columns:1fr; }
            .gd table.lbt .hidem{ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / dashboard</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a class="on">Dashboard</a><a>Calendar</a><a>Pipeline</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head('Sales at a glance &mdash; this month.') . '
                  <div class="pbar">' . $periods() . $range() . '</div>
                  ' . $viewSel('All sales team') . '
                  ' . $kpis() . '
                  <div class="pnl"><h4>Upcoming jobs</h4><div class="psub">Next 3 appointments on the calendar &mdash; soonest first.</div>
                    ' . $up('Today', '2:30pm', 'Mrs Halliwell', 'TA1 3QS', 'Dave Perry', 'accepted <code>DEM-2026-0042</code>') . '</div>
                  <p class="scs" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — a scoreboard; read the line -->
                <div class="sc" data-scene="1" data-len="22">
                  <div class="a-fade" style="--d:.2s">' . $head('Sales at a glance &mdash; this month.', 'a-ring', '--d:12s') . '</div>
                  <div class="a-rise" style="--d:3s">' . $kpis() . '</div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:4s">&#127942; A scoreboard</span>
                    <span class="chip bad a-pop" style="--d:6s">not a to-do list</span>
                    <span class="chip a-pop" style="--d:13s">&#128064; Read the grey line first &mdash; it says what&rsquo;s being counted</span>
                  </div>
                </div>

                <!-- 2 — new quote + joke -->
                <div class="sc" data-scene="2" data-len="26">
                  ' . $head('Sales at a glance &mdash; this month.') . '
                  <div class="chips" style="margin:-.2rem 0 .6rem;justify-content:flex-end"><span class="chip a-pop" style="--d:8.5s">Admins, or anyone with <b>Create quotes</b></span></div>
                  <div class="jotd a-rise" style="--d:11.5s">
                    <span style="font-size:1.1rem">&#128516;</span>
                    <div style="flex:1"><div class="jlbl">Joke of the day</div><div class="jtxt">Why was the blind so good at its job? It always knew when to draw the line.</div></div>
                    <span class="jbtn a-ring" style="--d:19s">&#128257; Another</span><span class="jbtn a-ring" style="--d:21s">&#10005;</span>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:21.5s">&#10005; hides it until tomorrow</span>
                    <span class="chip ok a-pop" style="--d:24s">The real off switch: Settings &rarr; Dashboard section</span>
                  </div>
                  <div class="a-move" style="--fx:30%;--fy:80%;--tx:90%;--ty:.8rem;--d:1.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 3 — the period buttons -->
                <div class="sc" data-scene="3" data-len="20">
                  <div class="a-fade" style="--d:.2s">' . $head('Sales at a glance &mdash; ' . $sw('this month', 'this quarter', 13) . '.') . '</div>
                  <div class="pbar a-rise" style="--d:1s"><div class="pgrp"><span class="sw"><span class="pbtn on a-out" style="--d:12.5s">This month</span><span class="pbtn a-fade" style="--d:12.5s">This month</span></span><span class="pbtn">Last 30 days</span><span class="pbtn a-sel" style="--d:12.5s">This quarter</span><span class="pbtn">This year</span><span class="pbtn">All time</span></div></div>
                  <div class="a-fade" style="--d:2s">' . $kpis() . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:6s">Opens on <b>This month</b></span>
                    <span class="chip ok a-pop" style="--d:14s">Every panel re-counts &mdash; except Upcoming jobs</span>
                  </div>
                </div>

                <!-- 4 — the catch: quote STARTED -->
                <div class="sc" data-scene="4" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">The catch: it counts the day the quote was <u>started</u></div>
                  <div class="tl">
                    <div class="axis a-wide" style="--d:1s"></div>
                    <div class="band a-fade" style="--d:2s;left:0;width:50%;background:var(--accent-wash)"></div>
                    <span class="mon a-fade" style="--d:2s;left:18%">September</span>
                    <span class="mon a-fade" style="--d:2.3s;left:68%">October</span>
                    <span class="pin a-pop" style="--d:10.5s;left:44%;color:var(--accent)">Quote started<br>28 Sep<i></i></span>
                    <span class="pin a-pop" style="--d:13s;left:60%;color:var(--good)">Customer said yes<br>4 Oct<i></i></span>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:18s">&rarr; counted in <b>September</b></span>
                    <span class="chip bad a-pop" style="--d:22s">&ldquo;A job I won is missing!&rdquo;</span>
                    <span class="chip a-pop" style="--d:24.5s">Widen the window, or press <b>All time</b></span>
                  </div>
                </div>

                <!-- 5 — From / To -->
                <div class="sc" data-scene="5" data-len="26">
                  <div class="a-fade" style="--d:.2s">' . $head('Sales at a glance &mdash; ' . $sw('this month', 'beginning &rarr; 30 Sep 2026', 21) . '.', 'a-ring', '--d:21.5s') . '</div>
                  <div class="pbar">' . $periods() . '
                    <div class="crange a-ring" style="--d:2s"><span class="dlbl">From</span><span class="dbox">' . $sw('07/09/2026', '<span style="color:var(--faint)">dd/mm/yyyy</span>', 18) . '</span>
                      <span class="dlbl">To</span><span class="dbox">' . $sw('07/10/2026', '30/09/2026', 13) . '</span><span class="applybtn a-press" style="--d:20s">Apply</span></div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:6s">Pre-filled: the last thirty days</span>
                    <span class="chip bad a-pop" style="--d:9.5s">Neither box goes past today</span>
                    <span class="chip ok a-pop" style="--d:14s">The To day is included</span>
                    <span class="chip a-pop" style="--d:18.5s">Leave one empty &rarr; that end stays open</span>
                  </div>
                </div>

                <!-- 6 — View: one salesperson -->
                <div class="sc" data-scene="6" data-len="28">
                  <div class="a-fade" style="--d:.2s">' . $head('Sales at a glance &mdash; this month' . '<span class="a-fade" style="--d:15s"> for <b>Jane Weller</b></span>.') . '</div>
                  ' . $viewSel($sw('All sales team', 'Jane Weller', 9.5)) . '
                  <div class="a-move" style="--fx:70%;--fy:90%;--tx:22%;--ty:4.2rem;--d:7s;--md:1.6s">' . $ptr . '</div>
                  <div class="pnl a-rise" style="--d:1s"><h4>' . $sw('Sales team', 'Their numbers', 18) . '</h4>
                    <div class="psub">Pipeline, close rate, and revenue ' . $sw('per salesperson.', 'for this salesperson.', 18) . '</div>
                    <div class="lbflex"><div class="lbwrap"><table class="lbt"><tr><th>Person</th><th class="num">Pipeline</th><th class="num">Decided</th><th class="num">Won</th><th class="num">Close rate</th><th class="num">Revenue</th></tr>
                      <tr><td class="who">Jane Weller</td><td class="num">14</td><td class="num">9</td><td class="num">6</td><td class="num">66.7%</td><td class="num">&pound;8,420.00</td></tr></table></div>
                      <div class="a-out" style="--d:21s"><div class="phead">Revenue share</div>' . $donut($teamDonut, 64) . '</div></div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5s">Only people who have raised a quote</span>
                    <span class="chip ok a-pop" style="--d:25.5s">Pick <b>All sales team</b> to come back out</span>
                  </div>
                </div>

                <!-- 7 — upcoming jobs -->
                <div class="sc" data-scene="7" data-len="30">
                  <div class="pnl a-rise" style="--d:.3s"><h4>Upcoming jobs</h4><div class="psub">Next 3 appointments on the calendar &mdash; soonest first.</div>
                    ' . $up('Today', '2:30pm', 'Mrs Halliwell', 'TA1 3QS', 'Dave Perry', 'accepted <code>DEM-2026-0042</code>', 'a-fly', '--d:7s') . '
                    ' . $up('Tomorrow', '9:00am', 'Bishops Lydeard job', 'TA4 3BW', '<i class="a-ring" style="--d:20s">Unassigned</i>', '', 'a-fly', '--d:7.6s') . '
                    ' . $up('Fri 9 Oct', '11:30am', 'Miller', 'BS40 5RL', 'Dave Perry', 'ordered <code>DEM-2026-0038</code>', 'a-fly', '--d:8.2s') . '
                    <div class="pfoot">Open calendar &rarr;</div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3s">Ignores the period and the person</span>
                    <span class="chip a-pop" style="--d:9.5s">Booked jobs, up to eight</span>
                    <span class="chip bad a-pop" style="--d:12s">Today in red</span>
                    <span class="chip a-pop" style="--d:20.5s">Unassigned = put somebody on it</span>
                    <span class="chip ok a-pop" style="--d:28s">Click a row to open it</span>
                  </div>
                </div>

                <!-- 8 — earlier today, not closed -->
                <div class="sc" data-scene="8" data-len="18">
                  <div class="pnl a-rise" style="--d:.3s"><h4>Upcoming jobs</h4><div class="psub">Next 2 appointments on the calendar &mdash; soonest first.</div>
                    <div class="ghead a-fade" style="--d:4s">Earlier today, not closed (1)</div>
                    ' . $up('Today', '9:00am', 'Patel', 'TA2 7PL', 'Dave Perry', 'accepted <code>DEM-2026-0040</code>', 'a-fly', '--d:4.5s') . '
                    <div class="ghead a-fade" style="--d:9s">Still to come</div>
                    ' . $up('Today', '2:30pm', 'Mrs Halliwell', 'TA1 3QS', 'Dave Perry', 'accepted <code>DEM-2026-0042</code>') . '
                    ' . $up('Tomorrow', '9:00am', 'Bishops Lydeard job', 'TA4 3BW', '<i>Unassigned</i>', '') . '
                    <div class="pfoot">Open calendar &rarr;</div></div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:13s">Close it off on the calendar &mdash; then it drops away</span></div>
                </div>

                <!-- 9 — revenue (won) -->
                <div class="sc" data-scene="9" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Revenue (won)</div>
                  ' . $kpis(['a-ring', '', '', '']) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4s">accepted</span><span class="chip a-pop" style="--d:4.6s">ordered</span>
                    <span class="chip a-pop" style="--d:5.2s">invoiced</span><span class="chip a-pop" style="--d:5.8s">paid</span>
                    <span class="chip ok a-pop" style="--d:10s">The customer&rsquo;s full figure &mdash; <b>VAT included</b></span>
                    <span class="chip a-pop" style="--d:14.5s">and any Wally tax (WT charge)</span>
                    <span class="chip bad a-pop" style="--d:19s">So it will never match Gross profit</span>
                  </div>
                </div>

                <!-- 10 — AOV, close rate, jobs -->
                <div class="sc" data-scene="10" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Average order value, Close rate, Jobs in period</div>
                  ' . $kpis(['', 'a-ring', '', '']) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:1.5s">Average = revenue &divide; won jobs</span>
                  </div>
                  <div class="a-rise" style="--d:4.5s;max-width:34rem;margin-top:.6rem"><div class="ktile rate a-ring" style="--d:5.5s;display:inline-block;min-width:11rem"><div class="klbl">Close rate</div><div class="kval">60.0%</div><div class="ksub">12 of 20 decided</div></div></div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:8s">Won &divide; <b>decided</b> (yes or no)</span>
                    <span class="chip a-pop" style="--d:12s">Still at &ldquo;sent&rdquo;? Left out &mdash; it may yet land</span>
                    <span class="chip a-pop" style="--d:18s">Nothing decided &rarr; &mdash; &ldquo;no decided quotes yet&rdquo;</span>
                    <span class="chip a-pop" style="--d:21.5s">Jobs in period = the won count, written large</span>
                  </div>
                </div>

                <!-- 11 — sales team -->
                <div class="sc" data-scene="11" data-len="28">
                  <div class="pnl a-rise" style="--d:.3s"><h4>Sales team</h4><div class="psub">Pipeline, close rate, and revenue per salesperson.</div>
                    <div class="lbflex"><div class="lbwrap"><table class="lbt">
                      <tr><th>Person</th><th class="num a-ring" style="--d:11s">Pipeline</th><th class="num a-ring" style="--d:14s">Decided</th><th class="num a-ring" style="--d:17.5s">Won</th><th class="num">Close rate</th><th class="num hidem">Revenue</th></tr>
                      <tr class="a-fly" style="--d:2s"><td class="who">&#129351; Jane Weller</td><td class="num">14</td><td class="num">9</td><td class="num">6</td><td class="num">66.7%</td><td class="num hidem">&pound;8,420.00</td></tr>
                      <tr class="a-fly" style="--d:2.5s"><td class="who">&#129352; Dave Perry</td><td class="num">9</td><td class="num">7</td><td class="num">4</td><td class="num">57.1%</td><td class="num hidem">&pound;5,110.00</td></tr>
                      <tr class="a-fly" style="--d:3s"><td class="who">&#129353; Sam Okafor</td><td class="num">6</td><td class="num">4</td><td class="num">2</td><td class="num">50.0%</td><td class="num hidem">&pound;2,760.00</td></tr></table></div>
                      <div><div class="phead">Revenue share</div>' . $donut($teamDonut, 70, 'a-pop', '--d:25.5s') . '</div></div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:11.5s">Pipeline = got as far as sending</span>
                    <span class="chip a-pop" style="--d:14.5s">Decided = answered</span>
                    <span class="chip a-pop" style="--d:18s">Won = turned into work</span>
                    <span class="chip ok a-pop" style="--d:21.5s">Big pipeline, small decided = quotes still in the air</span>
                  </div>
                </div>

                <!-- 12 — what is selling -->
                <div class="sc" data-scene="12" data-len="18">
                  <div class="pnl a-rise" style="--d:.3s"><h4>What&rsquo;s selling</h4><div class="psub">Top products by revenue in this period.</div>
                    <div class="lbflex">' . $donut($mixDonut, 80, 'a-pop', '--d:3s') . '
                      <div class="lbwrap">
                        <div class="mixrow"><div><div class="mixname">Vertical Blinds</div><div class="mixbar"><span class="a-wide" style="--d:5s;width:46%"></span></div></div><div class="mixnum">18 units</div><div class="mixnum">&pound;6,240.00</div></div>
                        <div class="mixrow"><div><div class="mixname">Roller Blinds</div><div class="mixbar"><span class="a-wide" style="--d:5.6s;width:31.8%"></span></div></div><div class="mixnum">11 units</div><div class="mixnum">&pound;4,310.00</div></div>
                        <div class="mixrow"><div><div class="mixname">Wooden Venetian</div><div class="mixbar"><span class="a-wide" style="--d:6.2s;width:22.2%"></span></div></div><div class="mixnum">4 units</div><div class="mixnum">&pound;3,020.00</div></div>
                      </div></div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:2s">Top eight &middot; won jobs only</span>
                    <span class="chip ok a-pop" style="--d:13s">&#128230; Look here before you order stock</span>
                  </div>
                </div>

                <!-- 13 — gross profit -->
                <div class="sc" data-scene="13" data-len="29">
                  <div class="pnl a-rise" style="--d:.3s"><h4>Gross profit</h4>
                    <div class="psub">Sell price minus the price-table cost basis (material + extras). Equivalent to your markup &amp; discount turned into pounds.</div>
                    <div class="mgrid">
                      <div class="a-pop" style="--d:11s"><div class="klbl">Total profit</div><div class="mval">&pound;6,390.00</div></div>
                      <div class="a-pop" style="--d:12s"><div class="klbl">Margin %</div><div class="mval">47.1%</div></div>
                      <div class="a-pop" style="--d:13s"><div class="klbl">Per job (avg)</div><div class="mval">&pound;532.50</div></div>
                      <div class="a-pop" style="--d:14s"><div class="klbl">Cost of goods</div><div class="mval cog">&pound;7,180.00</div></div>
                    </div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:18s">Net &mdash; no VAT, no Wally tax</span>
                    <span class="chip bad a-pop" style="--d:21s">Don&rsquo;t subtract it from Revenue</span>
                    <span class="chip a-pop" style="--d:24s">Sold at an agreed price? That discount comes off too</span>
                    <span class="chip ok a-pop" style="--d:27s">&#128274; Needs View costs</span>
                  </div>
                </div>

                <!-- 14 — recent wins -->
                <div class="sc" data-scene="14" data-len="17">
                  <div class="pnl a-rise" style="--d:.3s"><h4>Recent wins</h4><div class="psub">Latest 10 jobs accepted in this period.</div>
                    <div class="rrow a-fly" style="--d:3s"><div><a class="a-ring" style="--d:13s">DEM-2026-0042</a> &mdash; Mrs Halliwell</div><div class="ru">Jane Weller</div><div class="rdate">6 Oct 2026</div><div class="rrev">&pound;1,284.00</div></div>
                    <div class="rrow a-fly" style="--d:3.5s"><div><a>DEM-2026-0038</a> &mdash; Miller</div><div class="ru">Dave Perry</div><div class="rdate">29 Sep 2026</div><div class="rrev">&pound;2,140.00</div></div>
                    <div class="rrow a-fly" style="--d:4s"><div><a>DEM-2026-0031</a> &mdash; Bishops Lydeard Surgery</div><div class="ru">Jane Weller</div><div class="rdate">18 Sep 2026</div><div class="rrev">&pound;3,960.00</div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:6s">Who sold it &middot; when accepted &middot; the money</span>
                    <span class="chip ok a-pop" style="--d:13.5s">Click the quote number to open the job</span></div>
                </div>

                <!-- 15 — who sees what -->
                <div class="sc" data-scene="15" data-len="29">
                  <div class="sct a-fade" style="--d:.2s">Who sees what</div>
                  <div class="who3">
                    <div class="wcard a-rise" style="--d:2s"><h5>Admin</h5>Every panel, always.</div>
                    <div class="wcard a-rise" style="--d:5.5s"><h5>Everyone else</h5>Needs <b>Can see money</b> first. Then the panels ticked on their user page: Sales-team leaderboard, Product mix, Gross profit, Recent wins.</div>
                    <div class="wcard a-rise" style="--d:24s"><h5>No Can see money</h5>No Dashboard in the menu &mdash; they land on the <b>Calendar</b>.</div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:20.5s">Gross profit also needs <b>View costs</b></span>
                    <span class="chip ok a-pop" style="--d:27s">Half the Dashboard missing? Check Setup &rarr; Users &rarr; Edit</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>What this screen is.</b> The <b>Dashboard</b> is a scoreboard &mdash; the only screen in the app that does not ask you to
             <em>do</em> anything. The title says <b>Dashboard</b>, and the grey line underneath always tells you what is being counted &mdash;
             <em>&ldquo;Sales at a glance &mdash; this month.&rdquo;</em> Read that line first, every time. Top right, if you are an admin or
             have <b>Create quotes</b>, there is a solid <b>+ New quote</b> button. Above the numbers you may get an amber
             <b>Joke of the day</b> strip: <b>&#128257; Another</b> fetches a different one, and the <b>&#10005;</b> only hides it
             <em>until tomorrow</em>. The real off switch is the tick-box <em>&ldquo;Show a &lsquo;Joke of the day&rsquo; on the
             dashboard&rdquo;</em> in the <b>Dashboard</b> section of <a href="/help/guide.php?g=settings-dashboard"><b>Setup &rarr;
             Settings</b></a>.</p>

          <p><b>The three controls at the top.</b></p>
          <ul class="steps">
            <li><b>The period buttons</b> &mdash; <b>This month</b>, <b>Last 30 days</b>, <b>This quarter</b>, <b>This year</b>,
                <b>All time</b>. The one you are on is filled in. The page opens on <b>This month</b>. Every panel except <b>Upcoming jobs</b>
                is re-counted when you click one.</li>
            <li><b>From / To and Apply</b> &mdash; for anything the buttons don&rsquo;t cover. Both come <b>pre-filled with the last thirty
                days</b>. <b>Neither box lets you pick a day after today</b> &mdash; this screen only looks backwards (future dates belong on the
                <b>Calendar</b>). The <b>To</b> date is <b>included</b>. Leave one side empty and that end stays open; the grey line then says it
                in words: <em>&ldquo;beginning &rarr; 30 Sep 2026&rdquo;</em>, <em>&ldquo;1 Sep 2026 &rarr; today&rdquo;</em>, or
                <em>&ldquo;beginning &rarr; today&rdquo;</em> (the same as All time). If neither date makes sense you get a red bar,
                <b>&ldquo;Bad date format &mdash; use the date pickers.&rdquo;</b> &mdash; in practice only when the web address has been
                mangled.</li>
            <li><b>View:</b> &mdash; a dropdown starting at <b>All sales team</b>. Pick a name and the whole page narrows to that person,
                straight away. It only lists people who have <em>raised a quote</em> in your account (and isn&rsquo;t shown at all until
                someone has). The grey line gains <em>&ldquo;for Jane Weller&rdquo;</em>; <b>Sales team</b> becomes <b>Their numbers</b>
                (<em>&ldquo;&hellip;for this salesperson.&rdquo;</em>); and the <b>Revenue share</b> ring disappears. Changing the period keeps
                your person, and changing the person keeps your period.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The window counts from when the quote was STARTED &mdash; not when it was
             won.</b> A quote begun on 28 September and accepted on 4 October belongs to <b>September</b> here. If a job you know you won is
             missing, widen the window or click <b>All time</b>.</div></div>

          <p><b>The panels, top to bottom.</b></p>
          <ul class="steps">
            <li><b>Upcoming jobs</b> &mdash; the only panel looking <em>forwards</em>; it ignores the period and the View box. It lists the next
                appointments still <b>booked</b> on the calendar, soonest first, up to eight, and the sub-line counts what it found:
                <em>&ldquo;Next 3 appointments on the calendar &mdash; soonest first.&rdquo;</em> Each row opens that appointment and shows the
                day (<b>Today</b> in red, <b>Tomorrow</b>, then a date) and time; the customer over the postcode you are driving to; the fitter
                (or an italic <b>Unassigned</b>); and, if it is tied to a quote, that quote&rsquo;s status and number. <b>Open calendar
                &rarr;</b> at the foot. Today&rsquo;s jobs whose time has passed but are still booked sit in their own group,
                <b>Earlier today, not closed (1)</b>, above <b>Still to come</b>, so they can&rsquo;t quietly vanish before someone closes them
                off. With nothing booked: <em>&ldquo;Nothing booked yet &mdash; head to the calendar to add one.&rdquo;</em> and
                <em>&ldquo;No upcoming jobs booked.&rdquo;</em> Without <b>View all customer jobs</b>, you only see your own bookings and the
                fitter column is left blank.</li>
            <li><b>The four tiles.</b> <b>Revenue (won)</b>, in green, is the money from every job past accepted (accepted, ordered, invoiced
                or paid) &mdash; the <b>customer&rsquo;s full figure, VAT included</b>, with any Wally tax (WT charge) inside it; sub-line
                <em>&ldquo;12 jobs accepted&rdquo;</em>. <b>Average order value</b> is that money shared across those jobs (<em>&ldquo;across
                won quotes&rdquo;</em>). <b>Close rate</b>, in amber, is won out of <em>decided</em> &mdash; quotes still at <em>sent</em>
                are left out, as they might yet land (<em>&ldquo;12 of 20 decided&rdquo;</em>; with none decided it shows <b>&mdash;</b> and
                <em>&ldquo;no decided quotes yet&rdquo;</em>). <b>Jobs in period</b> is the same won count, written large (<em>&ldquo;accepted
                &amp; beyond&rdquo;</em>).</li>
            <li><b>Sales team</b> &mdash; everyone who raised a quote in the window, richest first, with &#129351; &#129352; &#129353; on the
                top three. <b>Pipeline</b> = got as far as sending; <b>Decided</b> = the customer has answered; <b>Won</b> = turned into work;
                <b>Close rate</b> = Won out of Decided; <b>Revenue</b> = their share of the money. Beside it, <b>Revenue share</b> draws the
                same revenue as a ring. Empty window: <em>&ldquo;No quotes raised in this period.&rdquo;</em></li>
            <li><b>What&rsquo;s selling</b> &mdash; <em>&ldquo;Top products by revenue in this period.&rdquo;</em> Your top <b>eight</b>
                products from won jobs, as a ring and a list with a share bar, units and money. Renamed or deleted products fall back to the name
                on the quote, or <b>(unknown)</b>. Empty: <em>&ldquo;No products sold in this period.&rdquo;</em></li>
            <li><b>Gross profit</b> &mdash; <em>&ldquo;Sell price minus the price-table cost basis (material + extras). Equivalent to your
                markup &amp; discount turned into pounds.&rdquo;</em> (it says <em>margin</em> if that is how your pricing is set up). Four
                cells: <b>Total profit</b>, <b>Margin %</b>, <b>Per job (avg)</b>, <b>Cost of goods</b> in brown. It works from the
                <b>net</b> sell price, with no VAT and no Wally tax, and if a job was sold at an agreed price the discount is taken off too.</li>
            <li><b>Recent wins</b> &mdash; <em>&ldquo;Latest 10 jobs accepted in this period.&rdquo;</em> Quote number, customer, who sold it,
                the date accepted and the money. Click the quote number to open it. Empty: <em>&ldquo;No jobs accepted in this period
                yet.&rdquo;</em></li>
          </ul>

          <div class="oops"><b>&ldquo;These numbers don&rsquo;t add up&rdquo; &mdash; the usual ones:</b>
            <ul style="margin:.4rem 0 0;padding-left:1.15rem">
              <li><b>Gross profit will never match Revenue (won).</b> Revenue includes VAT and the Wally tax; Gross profit is net of both.
                  Don&rsquo;t subtract one from the other.</li>
              <li><b>A job you won is missing.</b> The window goes by the day the quote was <b>started</b>. Widen it or click <b>All time</b>.</li>
              <li><b>A panel has vanished.</b> That is permission, not data &mdash; see below.</li>
              <li><b>The Revenue share ring has vanished.</b> You are filtered to one person in <b>View:</b>. Set it back to <b>All sales team</b>.</li>
            </ul>
          </div>

          <p><b>Who sees what.</b> An <b>admin</b> always sees everything. Everyone else needs <b>Can see money</b> (on their page under
             <b>Setup &rarr; Users &rarr; Edit</b>) before they get a Dashboard at all &mdash; without it the Dashboard is missing from their menu
             and they land on the <b>Calendar</b>. With it, they get the four tiles and <b>Upcoming jobs</b>, plus whichever panels are ticked
             in that page&rsquo;s <b>Dashboard</b> box: <b>Sales-team leaderboard</b>, <b>Product mix</b>, <b>Gross profit</b> and
             <b>Recent wins</b>. <b>Gross profit</b> also needs <b>View costs</b>. See <a href="/help/guide.php?g=users-add"><b>Adding users
             &amp; permissions</b></a>.</p>',
        'script'  => [
            ['1',  'A scoreboard',                    'The Dashboard is the first screen most people open. It helps to know what it is. It is a scoreboard, not a to do list. It tells you how the selling is going. The grey line under the title always says exactly what is being counted. Sales at a glance, this month. Read that line first, every time.', 1],
            ['2',  'New quote, and the joke',         'Top right is the New quote button, so starting a job is always one click away. You see it if you are an admin, or have the create quotes tick. Above the numbers there may be an amber strip, the joke of the day. Another fetches a different one. The cross only hides it until tomorrow. The real off switch is a tick box in Settings, in the Dashboard section.', 2],
            ['3',  'Pick the window',                 'Now the important row. Every number below, apart from upcoming jobs, is measured over the window you pick here. This month, last thirty days, this quarter, this year, or all time. The page opens on this month. Click another, and the grey line and every panel change to match.', 3],
            ['4',  'The catch',                       'Here is the one thing that catches everybody out, so it is worth hearing twice. The window counts a job by the day the quote was started. Not the day the customer said yes. So a quote you began on the twenty eighth of September, and won in October, still counts in September. If a job you know you won seems to be missing, widen the window, or press all time.', 4],
            ['5',  'Your own dates',                  'For any other stretch, use the From and To boxes, then press Apply. They come filled in with the last thirty days. Neither box will go past today, because this screen only looks backwards. The To date is included, the whole day. Leave one box empty, and that end stays open. The grey line then says it in words, like beginning, to the thirtieth of September.', 5],
            ['6',  'One salesperson',                 'The View box narrows the whole page to one salesperson. It only lists people who have raised a quote, and it changes the moment you pick a name. Watch what changes. The grey line adds, for Jane Weller. The team panel becomes Their numbers. And the revenue share ring disappears, because one person\'s share of themselves is always all of it. Pick all sales team to come back out.', 6],
            ['7',  'Upcoming jobs',                   'Upcoming jobs is the exception. It ignores the window, and it ignores the person. It shows the next jobs booked on the calendar, soonest first, up to eight of them. Today is in red, so your eye lands on it. Under each customer is the postcode you are driving to, then the fitter. Unassigned is your cue to put somebody on it. If the job is tied to a quote, you see its status and number. Click any row to open it.', 7],
            ['8',  'Earlier today, not closed',       'One more thing about that panel. A job from earlier today that is still marked as booked does not just disappear. It sits in its own group at the top, earlier today, not closed. That is your reminder to go and close it off on the calendar.', 8],
            ['9',  'Revenue (won)',                   'Now the four big numbers. Revenue, won, is the money from every job that got past accepted. That means accepted, ordered, invoiced or paid. It is the customer\'s full figure, VAT included, and any Wally tax you added is inside it too. So it will never match the gross profit figure further down.', 9],
            ['10', 'Average, close rate, jobs',       'Average order value is that money shared across the won jobs. Close rate is how many you won, out of the ones the customer has actually decided on. Quotes still sitting at sent are left out on purpose, because they might still land. With nothing decided, it just shows a dash. And jobs in period is the won count again, written large.', 10],
            ['11', 'Sales team',                      'Next, who is selling it. Everyone who raised a quote in the window, richest first, with medals for the top three. Pipeline is everything they sent out. Decided is the ones the customer has answered. Won is the ones that turned into work. So a big pipeline with a small decided is not a poor salesperson. It is quotes still in the air. The ring shows each person\'s share of the money.', 11],
            ['12', 'What\'s selling',                 'What\'s selling ranks your top eight products by the money taken, from won jobs only. You get a ring, and a list. A bar shows each product\'s share, with the number of units and the money beside it. This is the panel to look at before you order stock.', 12],
            ['13', 'Gross profit',                    'Gross profit takes each blind\'s sell price, and subtracts what your price tables say the blind and its extras cost you. You get total profit, margin percent, profit per job, and cost of goods. It is worked out net, with no VAT and no Wally tax, so do not subtract it from revenue. If a job was sold at an agreed price, that discount comes off too. You need the view costs permission to see it.', 13],
            ['14', 'Recent wins',                     'Recent wins lists the last ten jobs accepted in the window, newest first. Each row shows the quote number and the customer, who sold it, the day it was accepted, and the money in green. Click the quote number, and the job opens.', 14],
            ['15', 'Who sees what',                   'Finally, who sees what. An admin sees every panel. Everybody else needs can see money first. Then your admin ticks which panels they get, on their user page: the sales team leaderboard, product mix, gross profit and recent wins. Gross profit also needs view costs. Without can see money, there is no dashboard at all, and they land on the calendar. Half the dashboard missing? Check that page first.', 15],
        ],
];
