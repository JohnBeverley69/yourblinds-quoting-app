<?php
declare(strict_types=1);

/**
 * Guide: calendar-booking — "Calendar & booking" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors /calendar/index.php (the rolling six-week board, Everyone / Just
 * me, the legend + Issues filter, the Pending Fitting tray, drag-to-schedule
 * with the "Set fitting time" popup, the note + issue modals),
 * /calendar/week.php and /calendar/day.php, /calendar/new.php (all three
 * fieldsets, exact-time and time-slot modes), /calendar/reschedule.php
 * (date-only drags, clash + full-slot messages), /calendar/view.php (When &
 * status, completing a fitting) and /calendar/edit.php. Every label, button
 * and message is copied from those files. It is the CALENDAR, never a diary,
 * and there is no phone-calendar sync — each person uses the app itself.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

// ── Demo data: the board starts Monday 5 Oct 2026; today is Wed 7 Oct ────
$dayLbl = static fn (int $i): string => date('d/m', mktime(0, 0, 0, 10, 5 + $i, 2026));
$calToday = 2;               // 07/10
$calNov   = 27;              // index of 01/11 — shaded from here on

/** One card on the board. $x = extra classes (fit / iss / lt / a-*). */
$ap = static fn (string $time, string $title, string $bg, string $x = '', string $style = ''): string =>
    '<span class="ap ' . $x . '" style="background:' . $bg . ';' . $style . '"><b class="t">' . $time . '</b>' . $title . '</span>';

/**
 * The board grid. $rows weeks from 05/10; $cards[idx] = html inside that day;
 * $attr(idx) → [extra class, style] for the day square; $lbl(idx) → the
 * day-label html (defaults to DD/MM).
 */
$grid = static function (int $rows, array $cards = [], ?callable $attr = null, ?callable $lbl = null, string $cls = '', bool $today = true) use ($dayLbl, $calToday, $calNov): string {
    $h = '<div class="wdh"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>'
       . '<div class="cg ' . $cls . '">';
    for ($i = 0; $i < $rows * 7; $i++) {
        [$c, $s] = $attr ? $attr($i) : ['', ''];
        if ($today && $i === $calToday) $c .= ' tod';
        if ($i >= $calNov)    $c .= ' out';
        $h .= '<span class="cc ' . $c . '" style="' . $s . '"><span class="dn">' . ($lbl ? $lbl($i) : $dayLbl($i)) . '</span>'
            . ($cards[$i] ?? '') . '</span>';
    }
    return $h . '</div>';
};

// colours = the default traffic-light palette (_partials/job_status_colours.php)
$BLUE = '#2563eb'; $AMBER = '#f59e0b'; $GREEN = '#16a34a'; $INDIGO = '#6366f1'; $TEAL = '#0d9488'; $CYAN = '#0891b2';

// The everyday board's cards.
$base = [
    0  => $ap('9:00am', 'Install: ABC-2026-0029 &mdash; Joe Bloggs', $TEAL, 'fit'),
    2  => $ap('Morning', 'Angela Reed', $BLUE),
    3  => $ap('9:00am', 'Install: ABC-2026-0038 &mdash; Ray Patel', $INDIGO, 'fit'),
    4  => $ap('Afternoon', 'ABC-2026-0041 &mdash; Tom Shah', $AMBER, 'lt'),
    9  => $ap('Morning', 'ABC-2026-0044 &mdash; Kim Ward', $GREEN),
];
$dropIn = static function (array $cards, array $times): array {
    foreach ($cards as $i => $html) {
        $cards[$i] = '<span class="w a-drop" style="--d:' . ($times[$i] ?? 1) . 's">' . $html . '</span>';
    }
    return $cards;
};

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// Page header used on several scenes.
$hdr = static fn (string $sub = 'Appointments for Hart Interiors.', string $tog = '<span class="on">Everyone</span><span>Just me</span>', string $x = ''): string =>
    '<div class="chd ' . $x . '"><div><div class="ph1">Calendar</div>'
  . '<div class="psub">' . $sub . ' &middot; <span class="lk">&#128198; Week</span> &middot; <span class="lk">&#128197; Day</span></div>'
  . ($tog !== '' ? '<span class="vtog">' . $tog . '</span>' : '') . '</div>'
  . '<div class="acts"><span class="cbtn sec">+ New quote</span><span class="cbtn pri">+ Book Appointment</span></div></div>';

// Scene 2 — six weeks: rows fly in; the day labels move on a week and back.
$g2 = $grid(6, [], static fn ($i) => ['a-fade', '--d:' . (1 + intdiv($i, 7) * .5) . 's'],
    static fn ($i) => '<span class="sw"><span class="a-out" style="--d:9.8s">' . $dayLbl($i) . '</span>'
        . '<span class="a-mid" style="--d:9.8s;--d2:14.4s">' . $dayLbl($i + 7) . '</span>'
        . '<span class="a-fade" style="--d:14.4s">' . $dayLbl($i) . '</span></span>', '', false);

// Scene 4 — Everyone → Just me: other people's cards fade away.
$g4cards = $base;
foreach ([0, 3, 4] as $i) $g4cards[$i] = '<span class="w a-out" style="--d:8s">' . $base[$i] . '</span>';

// Scene 7 — pending: an accepted quote's fitting arrives.
$g8 = $grid(2, $base);

// Scene 9 — drag a booked fitting from Thu 08/10 to Tue 13/10.
$g10cards = $base;
$g10cards[3]  = '<span class="w a-out" style="--d:3.2s">' . $base[3] . '</span>';
$g10cards[8]  = '<span class="w a-pop" style="--d:3.6s">' . $ap('9:00am', 'Install: ABC-2026-0038 &mdash; Ray Patel', $INDIGO, 'fit') . '</span>';
$g10 = $grid(2, $g10cards, static fn ($i) => $i === 8 ? ['a-ring', '--d:2.6s'] : ['', '']);

// Scene 8 — drop the pending fitting on Fri 16/10.
$g9cards = $base;
$g9cards[11] = '<span class="w a-pop" style="--d:15.6s">' . $ap('10:30am', 'Install: ABC-2026-0042 &mdash; Emma Fletcher', $INDIGO, 'fit') . '</span>';
$g9 = $grid(2, $g9cards, static fn ($i) => $i === 11 ? ['a-ring', '--d:4.4s'] : ['', '']);

// Scene 15 — the flagged card and the Issues filter.
$g16cards = $base;
$g16cards[3] = '<span class="stk" style="display:grid;justify-items:stretch;grid-template-columns:minmax(0,1fr)"><span class="w a-out" style="--d:14s">' . $ap('9:00am', 'Install: ABC-2026-0038 &mdash; Ray Patel', $INDIGO, 'fit') . '</span>'
             . '<span class="w a-fade" style="--d:14s">' . $ap('9:00am &#9888;&#65039;', 'Install: ABC-2026-0038 &mdash; Ray Patel', $INDIGO, 'iss2') . '</span></span>';
foreach ([0, 2, 4, 9] as $i) $g16cards[$i] = '<span class="w a-out" style="--d:21s">' . $g16cards[$i] . '</span>';

return [
        'aud'     => 'admin',
        'section' => 'Calendar',
        'title'   => 'Calendar & booking',
        'eyebrow' => 'Calendar',
        'v'       => 2,
        'blurb'   => 'The calendar: six weeks from a Monday, cards coloured by stage, Everyone or Just me, the Pending Fitting tray you drag onto a date, booking a visit at an exact time or in a time slot, notes, issues and finishing a job.',
        'lede'    => 'The <b>Calendar</b> is where every visit your team makes is kept &mdash; the <b>measures</b> you book so you can
                      quote, and the <b>fittings</b> once a job is sold. It shows a <b>rolling six weeks from a Monday</b>, every visit a
                      card coloured by how far the job has got. Fittings for accepted quotes wait in the <b>Pending Fitting</b> tray
                      until you drag them onto a date, and each person sees their own jobs on their own phone or tablet, in the app.
                      This guide goes <b>slowly</b>, one idea per chapter. To get there: <b>Calendar</b>, under <b>Work</b> in the menu.',
        'open'    => '/calendar/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .w{ display:block; }
          .gd .row{ display:flex; gap:.45rem; flex-wrap:wrap; align-items:center; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; }
          .gd .btnd{ display:inline-flex; background:var(--err); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; }
          .gd .btng{ display:inline-flex; background:#15803d; color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }
          .gd .gd-tag{ font-size:.66rem; }

          /* page header */
          .gd .chd{ display:flex; gap:.6rem; align-items:flex-start; flex-wrap:wrap; margin-bottom:.5rem; }
          .gd .chd .acts{ margin-left:auto; display:flex; gap:.3rem; flex-wrap:wrap; }
          .gd .ph1{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.66rem; color:var(--faint); margin:.1rem 0 .35rem; }
          .gd .lk{ color:var(--accent); font-weight:700; }
          .gd .cbtn{ display:inline-flex; border-radius:7px; padding:.26rem .55rem; font-size:.64rem; font-weight:700; white-space:nowrap; }
          .gd .cbtn.pri{ background:var(--accent); color:#fff; } .gd .cbtn.sec{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .vtog{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); border-radius:999px; overflow:hidden; font-size:.64rem; background:var(--surface); }
          .gd .vtog span{ padding:.2rem .65rem; color:var(--soft); font-weight:600; }
          .gd .vtog span.on{ background:var(--accent); color:#fff; }
          .gd .vtog span + span{ border-left:1px solid var(--border-strong,#c7ccd4); }
          .gd .stk{ display:inline-grid; justify-items:start; } .gd .stk > *{ grid-area:1/1; }

          /* toolbar + legend */
          .gd .ctool{ display:flex; align-items:center; gap:.5rem; margin:.2rem 0 .4rem; flex-wrap:wrap; }
          .gd .cnav{ display:flex; align-items:center; gap:.3rem; }
          .gd .cnb{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.08rem .42rem; font-size:.72rem; color:var(--soft); background:var(--surface); }
          .gd .cml{ font-size:.74rem; font-weight:800; color:var(--ink); text-align:center; line-height:1.15; min-width:7.5rem; }
          .gd .cml small{ display:block; font-size:.56rem; font-weight:500; color:var(--faint); }
          .gd .leg{ display:flex; flex-wrap:wrap; gap:.15rem .5rem; font-size:.56rem; color:var(--soft); }
          .gd .leg span{ display:inline-flex; align-items:center; gap:.2rem; }
          .gd .leg i{ width:8px; height:8px; border-radius:2px; display:inline-block; }
          .gd .issp{ border:1px solid var(--line); border-radius:999px; padding:.05rem .4rem; }

          /* the board */
          .gd .wdh{ display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:1px; font-size:.5rem; color:var(--faint); text-transform:uppercase; letter-spacing:.06em; margin-bottom:2px; }
          .gd .cg{ display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
          .gd .cc{ background:var(--surface); min-height:2.75rem; padding:.12rem .18rem; position:relative; min-width:0; }
          .gd .cg.sm .cc{ min-height:1.55rem; }
          .gd .cc .dn{ font-size:.52rem; color:var(--faint); font-variant-numeric:tabular-nums; }
          .gd .cc.out{ background:var(--panel); }
          .gd .cc.tod{ background:var(--accent-wash); box-shadow:inset 3px 0 0 var(--nav); }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          .gd .ap{ display:block; border-radius:4px; padding:.06rem .22rem; font-size:.5rem; line-height:1.3; color:#fff; margin-top:.12rem;
                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .ap .t{ display:block; font-weight:700; opacity:.92; }
          .gd i.ap{ font-style:normal; }
          .gd .cg.ph .cc{ min-height:1.9rem; padding:.08rem; } .gd .cg.ph .dn{ font-size:.42rem; } .gd .cg.ph .ap{ font-size:.4rem; padding:.04rem .1rem; }
          .gd .ap.lt{ color:#111827; }
          .gd .ap.fit{ box-shadow:inset 0 0 0 2px rgba(17,24,39,.75); }
          .gd .ap.iss2{ box-shadow:inset 0 0 0 2px #e11d48; }

          /* 3 — the big card */
          .gd .bigap{ display:inline-flex; align-items:center; gap:.5rem; border-radius:9px; padding:.55rem .8rem; font-size:.86rem; color:#111827; background:#f59e0b; max-width:100%; }
          .gd .bigap.fit{ background:#6366f1; color:#fff; box-shadow:inset 0 0 0 3px rgba(17,24,39,.8); }
          .gd .bigap .t{ font-weight:800; }
          .gd .bigap .ib{ display:inline-grid; place-items:center; width:1.4rem; height:1.4rem; border-radius:5px; background:rgba(255,255,255,.55); font-size:.72rem; }
          .gd .legbig{ display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.8rem; }
          .gd .legbig span{ display:inline-flex; align-items:center; gap:.3rem; font-size:.66rem; font-weight:700; border:1px solid var(--line); border-radius:999px; padding:.15rem .5rem; background:var(--surface); color:var(--ink); }
          .gd .legbig i{ width:10px; height:10px; border-radius:3px; display:inline-block; }

          /* 5/6 — week, day, phone */
          .gd .vsw{ display:inline-flex; gap:.25rem; margin:.25rem 0 .45rem; }
          .gd .vsw span{ font-size:.62rem; border:1px solid var(--line); border-radius:6px; padding:.12rem .45rem; color:var(--accent); background:var(--surface); }
          .gd .vsw span.on{ background:var(--accent); color:#fff; border-color:var(--accent); }
          .gd .mini{ border:1px solid var(--line); border-radius:10px; padding:.55rem .6rem; background:var(--surface); }
          .gd .mini h4{ margin:0; font-size:.8rem; color:var(--ink); }
          .gd .mini small{ display:block; font-size:.58rem; color:var(--faint); }
          .gd .wk{ display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:2px; }
          .gd .wk > span{ background:var(--panel); border-radius:4px; min-height:3.2rem; padding:.1rem; font-size:.48rem; color:var(--faint); min-width:0; }
          .gd .dycols{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:3px; }
          .gd .dycols > div{ background:var(--panel); border-radius:5px; padding:.2rem; min-height:5.6rem; min-width:0; }
          .gd .dycols b.nm{ display:block; font-size:.58rem; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .dycard{ display:block; border-radius:5px; color:#fff; font-size:.5rem; padding:.2rem .25rem; margin-top:.25rem; line-height:1.35; }
          .gd .dycard u{ text-decoration:underline; display:block; }
          .gd .nudge{ background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.45); border-radius:8px; padding:.35rem .55rem; font-size:.64rem; color:var(--ink); margin-top:.55rem; }
          .gd .phone{ width:12.5rem; border:6px solid #0f172a; border-radius:22px; padding:.55rem .5rem .8rem; background:var(--surface); box-shadow:var(--gd-shadow); }
          .gd .phone .ntch{ width:3.5rem; height:.35rem; border-radius:9px; background:#0f172a; margin:-.2rem auto .45rem; }
          .gd .scard{ border:1px solid var(--line); border-left:4px solid #6366f1; border-radius:8px; padding:.35rem .45rem; font-size:.62rem; color:var(--ink); margin-top:.4rem; }
          .gd .scard b{ display:block; font-size:.66rem; }
          .gd .scard .lk2{ color:var(--accent); font-weight:700; display:block; margin-top:.15rem; }
          .gd .nosync{ position:relative; display:inline-flex; align-items:center; gap:.35rem; border:1px dashed var(--line); border-radius:10px; padding:.4rem .6rem; font-size:.68rem; color:var(--soft); }
          .gd .nosync s{ color:var(--faint); }
          .gd .phwrap{ display:flex; gap:1.2rem; align-items:flex-start; flex-wrap:wrap; }
          .gd .phnotes{ flex:1 1 14rem; display:flex; flex-direction:column; gap:.55rem; align-items:flex-start; }

          /* 7/8 — the tray, the drop, the time popup */
          .gd .tray{ background:rgba(245,158,11,.10); border:1px solid rgba(245,158,11,.45); border-radius:10px; padding:.5rem .65rem; margin-bottom:.55rem; min-height:3.9rem; }
          .gd .tray h5{ display:inline; margin:0; font-size:.78rem; color:var(--ink); }
          .gd .tray .cnt{ display:inline-grid; place-items:center; background:#b45309; color:#fff; border-radius:999px; font-size:.58rem; min-width:1.1rem; padding:0 .3rem; margin:0 .3rem; }
          .gd .tray .hnt{ font-size:.62rem; color:var(--faint); }
          .gd .pcard{ display:inline-flex; flex-direction:column; background:var(--surface); border:1px solid rgba(245,158,11,.55); border-radius:8px; padding:.3rem .5rem; font-size:.66rem; color:var(--ink); margin-top:.35rem; font-weight:600; }
          .gd .pcard small{ color:var(--faint); font-size:.58rem; font-weight:400; }
          .gd .pempty{ font-size:.64rem; color:var(--faint); font-style:italic; display:block; margin-top:.35rem; }
          .gd .accq{ display:inline-flex; align-items:center; gap:.4rem; border:1px solid var(--line); border-radius:9px; padding:.35rem .55rem; background:var(--surface); font-size:.68rem; color:var(--ink); }
          .gd .tpop{ position:absolute; z-index:5; border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); box-shadow:var(--gd-shadow); width:11rem; }
          .gd .tpop .tt{ font-weight:800; color:var(--ink); font-size:.74rem; margin-bottom:.35rem; }
          .gd .tin{ display:flex; align-items:center; justify-content:space-between; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.22rem .45rem; font-size:.8rem; color:var(--ink); background:var(--surface); font-variant-numeric:tabular-nums; }
          .gd .tin i{ font-style:normal; color:var(--faint); font-size:.7rem; }
          .gd .tpop .act{ display:flex; gap:.35rem; justify-content:flex-end; margin-top:.5rem; }
          .gd .ghost{ display:block; width:9.5rem; background:var(--surface); border:1px solid var(--accent); border-radius:7px; padding:.25rem .45rem; font-size:.58rem; color:var(--ink);
                      transform:rotate(-3deg); box-shadow:0 8px 18px -10px rgba(20,30,45,.55); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .ghost + .gd-ptr{ margin:-.4rem 0 0 4.2rem; }
          .gd .cdlg{ position:absolute; z-index:5; left:8%; right:8%; top:5.2rem; max-width:24rem; border:1px solid var(--line); border-radius:12px; background:var(--surface);
                     box-shadow:var(--gd-shadow); padding:.65rem .75rem; font-size:.7rem; color:var(--ink); line-height:1.45; }
          .gd .cdlg .act{ display:flex; gap:.35rem; justify-content:flex-end; margin-top:.5rem; }

          /* 10–15 — the booking form */
          .gd .fs{ border:1px solid var(--line); border-radius:10px; padding:.5rem .65rem .55rem; margin-bottom:.5rem; background:var(--surface); }
          .gd .lg{ font-size:.58rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--soft); margin-bottom:.35rem; }
          .gd .fl{ display:block; font-size:.6rem; color:var(--faint); font-weight:700; margin:.35rem 0 .15rem; }
          .gd .fl .rq{ color:var(--err); }
          .gd .ib2{ display:flex; align-items:center; min-height:1.65rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:0 .45rem;
                    font-size:.72rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib2 .phd{ color:var(--faint); }
          .gd .ib2.sel::after{ content:"\25BE"; margin-left:auto; color:var(--faint); font-size:.62rem; }
          .gd .ib2.num::after{ content:"\21D5"; margin-left:auto; color:var(--faint); font-size:.62rem; }
          .gd .dl{ border:1px solid var(--border-strong,#c7ccd4); border-top:0; border-radius:0 0 7px 7px; background:var(--surface); padding:.12rem; box-shadow:0 8px 18px -12px rgba(20,30,45,.45); }
          .gd .dl.flo{ position:absolute; z-index:4; }
          .gd .dl b{ display:block; font-size:.62rem; font-weight:400; color:var(--soft); padding:.12rem .32rem; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .dl b.pk{ background:var(--accent); color:#fff; font-weight:600; }
          .gd .fh{ font-size:.6rem; color:var(--faint); margin:.25rem 0 0; line-height:1.4; }
          .gd .g3{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:0 .45rem; }
          .gd .g4{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:0 .45rem; }
          .gd .g2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 .45rem; }
          .gd .lkr{ display:grid; grid-template-columns:1fr auto; gap:.4rem; align-items:end; }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.66rem; color:var(--ink); margin-top:.4rem; }
          .gd .tk{ width:15px; height:15px; border-radius:4px; border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); display:inline-grid; place-items:center;
                   font-size:.6rem; color:transparent; }
          .gd .tk.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .warnbox{ background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.5); border-radius:8px; padding:.45rem .6rem; font-size:.66rem; color:var(--ink); margin-bottom:.5rem; line-height:1.45; }
          .gd .warnbox .btnw{ display:inline-flex; margin-top:.35rem; background:#d97706; color:#fff; border-radius:6px; padding:.22rem .6rem; font-size:.64rem; font-weight:700; }
          .gd .errb{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.4rem .6rem; font-size:.66rem; color:var(--ink); margin-bottom:.5rem; }
          .gd .okb{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.4rem .6rem; font-size:.68rem; color:var(--ink); font-weight:600; margin-bottom:.5rem; }
          .gd .twins{ display:flex; gap:.4rem; flex-wrap:wrap; }
          .gd .twin{ border:1px solid var(--line); border-radius:8px; padding:.3rem .5rem; font-size:.64rem; background:var(--surface); color:var(--ink); }
          .gd .slots{ display:flex; gap:.4rem; flex-wrap:wrap; }
          .gd .slot{ flex:1 1 8.5rem; display:flex; align-items:center; gap:.35rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:9px; padding:.38rem .5rem; font-size:.64rem; background:var(--surface); color:var(--ink); min-width:0; }
          .gd .slot .rg{ color:var(--faint); }
          .gd .slot .lf{ margin-left:auto; font-size:.58rem; color:var(--faint); white-space:nowrap; }
          .gd .slot.full > *{ opacity:.55; } .gd .slot.full .lf{ color:#b45309; font-weight:800; opacity:1; }
          .gd .slotstk{ flex:1 1 8.5rem; display:grid; min-width:0; } .gd .slotstk > .slot{ grid-area:1/1; }
          .gd .rd{ width:13px; height:13px; flex:0 0 13px; border-radius:50%; border:1.5px solid var(--border-strong,#c7ccd4); display:inline-block; position:relative; box-sizing:border-box; background:var(--surface); }
          .gd .rd.on{ border-color:var(--accent); }
          .gd .rd.on::after{ content:""; position:absolute; inset:2px; border-radius:50%; background:var(--accent); }
          .gd .setrow{ display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr auto; gap:.35rem; align-items:end; margin-top:.3rem; }
          .gd .setrow .ib2{ font-size:.66rem; min-height:1.5rem; }
          .gd .setrow .rm{ font-size:.58rem; border:1px solid var(--line); border-radius:6px; padding:.18rem .35rem; color:var(--soft); white-space:nowrap; }

          /* 15 — note / issue modals */
          .gd .mdl{ border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.6rem .7rem; }
          .gd .mdl h4{ margin:0 0 .2rem; font-size:.8rem; color:var(--ink); }
          .gd .mdl p{ margin:0 0 .4rem; font-size:.62rem; color:var(--faint); }
          .gd .txa{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; min-height:2.6rem; padding:.3rem .45rem; font-size:.7rem; color:var(--ink); background:var(--surface); }
          .gd .mdl .act{ display:flex; gap:.3rem; flex-wrap:wrap; margin-top:.45rem; }

          /* 16 — the appointment page */
          .gd .dlr{ display:grid; grid-template-columns:6rem 1fr; gap:.3rem .6rem; font-size:.7rem; color:var(--ink); align-items:center; margin:.4rem 0; }
          .gd .dlr dt{ color:var(--faint); font-size:.6rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .dlr dd{ margin:0; }
          .gd .stp{ display:inline-grid; } .gd .stp > span{ grid-area:1/1; }
          .gd .spl{ display:inline-flex; border-radius:999px; padding:.08rem .5rem; font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
          .gd .secth{ display:flex; align-items:center; gap:.5rem; font-size:.82rem; font-weight:800; color:var(--ink); }
          .gd .dz{ border:1px solid var(--err); border-radius:9px; padding:.4rem .55rem; margin-top:.55rem; font-size:.62rem; color:var(--soft); }
          .gd .dz b{ color:var(--err); }

          @media (max-width:640px){
            .gd .sc{ min-height:440px; }
            .gd .two, .gd .g3, .gd .g4{ grid-template-columns:1fr; }
            .gd .chd .acts{ margin-left:0; }
            .gd .setrow{ grid-template-columns:1.2fr 1fr 1fr .9fr; } .gd .setrow .rm{ display:none; }
            .gd .dycols{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .gd .dycols > div:nth-child(3){ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / calendar</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a class="on">Calendar</a><a>Pipeline</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $hdr() . '
                  <div class="tray"><h5>Pending Fitting</h5><span class="cnt">1</span><span class="hnt">Drag a card onto a date to schedule it.</span><br>
                    <span class="pcard">Install: ABC-2026-0042 &mdash; Emma Fletcher<small>Leamington Spa &middot; CV32 5PJ</small></span></div>
                  ' . $grid(2, $base) . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is -->
                <div class="sc" data-scene="1" data-len="25">
                  <div class="a-rise" style="--d:.4s">' . $hdr() . '</div>
                  <div class="a-fade" style="--d:2s">' . $grid(2, $dropIn($base, [0 => 9.6, 2 => 5.4, 3 => 9.2, 4 => 6, 9 => 6.6])) . '</div>
                  <div class="row" style="margin-top:.7rem">
                    <span class="chip a-pop" style="--d:6.2s"><i style="width:9px;height:9px;border-radius:2px;background:#2563eb;display:inline-block"></i> Measures &mdash; visits so you can quote</span>
                    <span class="chip a-pop" style="--d:9.8s"><i style="width:9px;height:9px;border-radius:2px;background:#6366f1;outline:2px solid #111827;outline-offset:-2px;display:inline-block"></i> Fittings &mdash; jobs already sold</span>
                    <span class="chip a-pop" style="--d:11.8s">Menu: <b>Work</b> &rarr; <b>Calendar</b></span>
                    <span class="chip ok a-pop" style="--d:19.8s">&#128101; One calendar for the whole team</span>
                  </div>
                </div>

                <!-- 2 — six weeks from a Monday -->
                <div class="sc" data-scene="2" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">Six weeks, always from a Monday &mdash; not a month</div>
                  <div class="ctool">
                    <div class="cnav a-rise" style="--d:.6s">
                      <span class="cnb a-press" style="--d:9.6s">&lsaquo;</span>
                      <span class="cml stk"><span class="a-out" style="--d:9.8s">October 2026<small>Mon 5 Oct &mdash; Sun 15 Nov</small></span>
                        <span class="a-mid" style="--d:9.8s;--d2:14.4s">November 2026<small>Mon 12 Oct &mdash; Sun 22 Nov</small></span>
                        <span class="a-fade" style="--d:14.4s">October 2026<small>Mon 5 Oct &mdash; Sun 15 Nov</small></span></span>
                      <span class="cnb a-press" style="--d:9.4s">&rsaquo;</span>
                      <span class="cnb a-mid" style="--d:10.4s;--d2:14.4s">Today</span>
                    </div>
                    <span class="chip a-pop" style="--d:4.2s">6 weeks &middot; Monday to Sunday</span>
                  </div>
                  ' . $g2 . '
                  <div class="row" style="margin-top:.55rem">
                    <span class="chip a-pop" style="--d:16.4s">07/10 = 7 October &mdash; day / month</span>
                    <span class="chip a-pop" style="--d:22.8s">&#9638; Shaded = next month &mdash; still bookable</span>
                  </div>
                  <div class="a-move" style="--fx:60%;--fy:16rem;--tx:9.5rem;--ty:2.4rem;--d:7.6s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 3 — reading a card -->
                <div class="sc" data-scene="3" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Reading a card</div>
                  <div style="margin:1.4rem 0 .4rem">
                    <span class="bigap a-rise" style="--d:.8s">
                      <span class="t a-pop" style="--d:1.4s">Afternoon</span>
                      <span class="a-type" style="--d:5.4s;--ts:30;--tt:1.4s">ABC-2026-0041 &mdash; Tom Shah</span>
                      <span class="ib">&#9888;&#65039;</span><span class="ib">&#128221;</span><span class="ib a-ring" style="--d:22.6s">&rarr;</span>
                    </span>
                  </div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:2.2s">time, or the slot name</span>
                    <span class="chip a-pop" style="--d:6.4s">job number &mdash; customer</span>
                  </div>
                  <div class="legbig">
                    <span class="a-pop" style="--d:9s"><i style="background:#2563eb"></i>Appointment booked</span>
                    <span class="a-pop" style="--d:10s"><i style="background:#f59e0b"></i>Quote sent</span>
                    <span class="a-pop" style="--d:10.8s"><i style="background:#16a34a"></i>Accepted</span>
                    <span class="a-pop" style="--d:11.4s"><i style="background:#0891b2"></i>Ordered</span>
                    <span class="a-pop" style="--d:12s"><i style="background:#6366f1"></i>Fitting booked</span>
                    <span class="a-pop" style="--d:12.6s"><i style="background:#0d9488"></i>Fitted</span>
                    <span class="a-pop" style="--d:13.2s"><i style="background:#ea580c"></i>Invoiced</span>
                    <span class="a-pop" style="--d:13.8s"><i style="background:#475569"></i>Paid</span>
                    <span class="a-pop" style="--d:14.8s;border-color:var(--accent)">your colours &mdash; Settings &rarr; Status colours</span>
                  </div>
                  <div style="margin-top:.9rem" class="row">
                    <span class="bigap fit a-drop" style="--d:17.4s"><span class="t">9:00am</span> Install: ABC-2026-0038 &mdash; Ray Patel</span>
                    <span class="chip a-pop" style="--d:18.6s">dark outline = a fitting</span>
                  </div>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:20.6s">click the card &rarr; the appointment</span>
                    <span class="chip a-pop" style="--d:23s;border-color:var(--accent)">click &rarr; &rarr; the order</span>
                  </div>
                </div>

                <!-- 4 — everyone / just me -->
                <div class="sc" data-scene="4" data-len="24">
                  <div class="chd"><div><div class="ph1">Calendar</div>
                    <div class="psub stk"><span class="a-out" style="--d:6.8s">Appointments for Hart Interiors. &middot; <span class="lk">&#128198; Week</span> &middot; <span class="lk">&#128197; Day</span></span>
                      <span class="a-fade" style="--d:6.8s">Filtered to Sam Yates&rsquo;s appointments only. &middot; <span class="lk">&#128198; Week</span> &middot; <span class="lk">&#128197; Day</span></span></div>
                    <span class="stk a-ring" style="--d:1.2s;border-radius:999px"><span class="vtog a-out" style="--d:6.6s"><span class="on">Everyone</span><span>Just me</span></span>
                      <span class="vtog a-fade" style="--d:6.6s"><span>Everyone</span><span class="on">Just me</span></span></span></div>
                    <div class="acts"><span class="cbtn sec">+ New quote</span><span class="cbtn pri">+ Book Appointment</span></div></div>
                  ' . $grid(2, $g4cards, null, null, 'sm') . '
                  <div class="mini a-rise" style="--d:10.4s;margin-top:.7rem;max-width:22rem">
                    <small>Signed in without &ldquo;see everyone&rsquo;s jobs&rdquo;:</small>
                    <div class="ph1" style="font-size:.86rem">Calendar</div>
                    <div class="psub">Your appointments. &middot; <span class="lk">&#128198; Week</span> &middot; <span class="lk">&#128197; Day</span></div>
                    <span class="chip a-pop" style="--d:14.6s">no Everyone / Just me switch</span>
                  </div>
                  <span class="chip ok a-pop" style="--d:20s;margin-top:.6rem">&#10003; The permission working &mdash; not a fault</span>
                  <div class="a-move" style="--fx:70%;--fy:14rem;--tx:3.6rem;--ty:3.4rem;--d:4.6s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 5 — week and day views -->
                <div class="sc" data-scene="5" data-len="27">
                  <div class="psub a-fade" style="--d:.4s;font-size:.74rem">Appointments for Hart Interiors. &middot; <span class="lk a-ring" style="--d:2.6s">&#128198; Week</span> &middot; <span class="lk a-ring" style="--d:5.4s">&#128197; Day</span></div>
                  <div class="two">
                    <div class="mini a-rise" style="--d:3s">
                      <h4>Week view</h4><small>7-day grid. Card colour shows the job stage &mdash; see the key below.</small>
                      <div class="vsw a-pop" style="--d:15.2s"><span>Month</span><span class="on">Week</span><span>Day</span></div>
                      <div class="wk">
                        <span>Mon 5<i class="ap fit" style="background:#0d9488">9:00am</i></span><span>Tue 6</span>
                        <span>Wed 7<i class="ap" style="background:#2563eb">Morning</i></span>
                        <span>Thu 8<i class="ap fit" style="background:#6366f1">9:00am</i></span>
                        <span>Fri 9<i class="ap lt" style="background:#f59e0b">Afternoon</i></span><span>Sat 10</span><span>Sun 11</span>
                      </div>
                    </div>
                    <div class="mini a-rise" style="--d:6s">
                      <h4>Day view</h4><small>Who&rsquo;s doing what today, with full address + tap-to-call.</small>
                      <div class="vsw a-pop" style="--d:15.8s"><span>Month</span><span>Week</span><span class="on">Day</span></div>
                      <div class="dycols">
                        <div><b class="nm">Sam Yates &middot; 1 job</b><span class="dycard a-pop" style="--d:8.6s;background:#2563eb">Morning &middot; Angela Reed<u>14 Clarendon St</u><u class="a-ring" style="--d:12.4s">&#128222; 07700 900114</u></span></div>
                        <div><b class="nm">Dave Cole</b></div>
                        <div><b class="nm">Priya Nair</b></div>
                      </div>
                    </div>
                  </div>
                  <div class="nudge a-rise" style="--d:20.2s">1 fitting pending &mdash; place it on the <b>Month calendar</b> to schedule.</div>
                </div>

                <!-- 6 — on each person\'s phone -->
                <div class="sc" data-scene="6" data-len="28">
                  <div class="sct a-fade" style="--d:.2s">Everyone sees their own jobs, in the app</div>
                  <div class="phwrap">
                    <div class="phone a-rise" style="--d:1.2s">
                      <div class="ntch"></div>
                      <div class="ph1" style="font-size:.8rem">Calendar</div>
                      <div class="psub">Your appointments.</div>
                      ' . $grid(1, [3 => '<span class="w a-ring" style="--d:9.2s">' . $ap('9:00am', 'Install: ABC-2026-0038 &mdash; Ray Patel', $INDIGO, 'fit') . '</span>',
                                    4 => '<span class="w a-pop" style="--d:24.2s">' . $ap('2:00pm', 'Install: ABC-2026-0039 &mdash; Kim Ward', $INDIGO, 'fit') . '</span>'], null, null, 'ph') . '
                      <div class="scard a-fly" style="--d:12.6s"><b>Install: ABC-2026-0038 &mdash; Ray Patel</b>Thursday, 8 October 2026 &middot; 9:00am<br>3 Mill Lane, Kenilworth
                        <span class="lk2 a-ring" style="--d:15.6s">Phone &middot; 07700 900222</span>
                        <span class="btng" style="margin-top:.3rem;font-size:.58rem;padding:.18rem .45rem">Google Maps &rarr;</span></div>
                    </div>
                    <div class="phnotes">
                      <span class="chip a-pop" style="--d:3.4s">&#128241; Their own phone or tablet &mdash; their own login</span>
                      <span class="chip a-pop" style="--d:13.6s">Address, phone, notes &mdash; all on the job</span>
                      <span class="nosync a-pop" style="--d:18.8s">&#128197; <s>Link to the phone&rsquo;s calendar</s> &mdash; not needed</span>
                      <span class="chip ok a-pop" style="--d:23.2s">&#8635; Keeps itself up to date</span>
                    </div>
                  </div>
                </div>

                <!-- 7 — the pending tray -->
                <div class="sc" data-scene="7" data-len="26">
                  <div class="row" style="margin-bottom:.55rem">
                    <span class="accq a-rise" style="--d:.4s">&#10003; Customer accepted &middot; <b>ABC-2026-0042</b> Emma Fletcher</span>
                    <span class="chip a-pop" style="--d:2.6s">&rarr; the fitting is written for you</span>
                  </div>
                  <div class="tray a-ring" style="--d:7s"><h5>Pending Fitting</h5><span class="cnt stk"><span class="a-out" style="--d:5.4s">0</span><span class="a-fade" style="--d:5.4s">1</span></span><span class="hnt">Drag a card onto a date to schedule it.</span><br>
                    <span class="pcard a-drop" style="--d:5.2s">Install: ABC-2026-0042 &mdash; Emma Fletcher<small>Leamington Spa &middot; CV32 5PJ</small></span></div>
                  ' . $g8 . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:12.2s">&#9749; No alert &mdash; check the tray each morning</span>
                  </div>
                  <div class="tray a-rise" style="--d:18.6s;margin-top:.6rem;min-height:0"><h5>Pending Fitting</h5><span class="cnt">0</span><span class="hnt">Drag a card onto a date to schedule it.</span>
                    <span class="pempty">Nothing pending. Accepted quotes land here until you place them on a date.</span></div>
                </div>

                <!-- 8 — drag onto a day -->
                <div class="sc" data-scene="8" data-len="25">
                  <div class="tray"><h5>Pending Fitting</h5><span class="cnt stk"><span class="a-out" style="--d:15.6s">1</span><span class="a-fade" style="--d:15.6s">0</span></span><span class="hnt">Drag a card onto a date to schedule it.</span><br>
                    <span class="stk"><span class="pcard a-out" style="--d:1.6s">Install: ABC-2026-0042 &mdash; Emma Fletcher<small>Leamington Spa &middot; CV32 5PJ</small></span>
                      <span class="pempty a-fade" style="--d:16s">Nothing pending. Accepted quotes land here until you place them on a date.</span></span></div>
                  ' . $g9 . '
                  <div class="a-move" style="--fx:2%;--fy:2.6rem;--tx:55%;--ty:11.2rem;--d:1.6s;--md:2.8s"><span class="ghost a-out" style="--d:6.4s">Install: ABC-2026-0042 &mdash; Emma Fletcher</span>' . $ptr . '</div>
                  <div class="tpop a-mid" style="--d:6.8s;--d2:15.2s;left:34%;top:13.2rem">
                    <div class="tt">Set fitting time</div>
                    <div class="tin"><span class="stk"><span class="a-out" style="--d:12.4s">09:00</span><span class="a-fade" style="--d:12.4s">10:30</span></span><i>&#128339;</i></div>
                    <div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:14.2s">Schedule</span></div>
                  </div>
                  <span class="chip a-pop" style="--d:20.6s;margin-top:.6rem">Cancel &rarr; it stays in the tray</span>
                </div>

                <!-- 9 — moving a booked job -->
                <div class="sc" data-scene="9" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Moving a job that is already on the calendar</div>
                  ' . $g10 . '
                  <div class="a-move" style="--fx:43%;--fy:4rem;--tx:14%;--ty:7.2rem;--d:.8s;--md:2.2s"><span class="ghost a-out" style="--d:3.3s">Install: ABC-2026-0038 &mdash; Ray Patel</span>' . $ptr . '</div>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:4.2s">Date changes &mdash; time, length, person, status stay</span>
                  </div>
                  <div class="cdlg a-mid" style="--d:11.6s;--d2:18s">Dave Cole is already booked 09:00&ndash;10:00 (Angela Reed) that day &mdash; they can&rsquo;t be in two places at once. Pick another time, assignee, or day.<br><br>Book anyway?
                    <div class="act"><span class="btns">Cancel</span><span class="btnp">OK</span></div></div>
                  <div class="row" style="margin-top:.5rem">
                    <span class="chip a-pop" style="--d:18.8s">Drag back onto the tray &rarr; off the calendar</span>
                    <span class="chip a-pop" style="--d:23.2s">New time? Open it &rarr; <b>Edit</b></span>
                  </div>
                </div>

                <!-- 10 — booking a visit yourself -->
                <div class="sc" data-scene="10" data-len="24">
                  <div class="row" style="margin-bottom:.5rem">
                    <span class="cbtn pri a-ring" style="--d:6.6s">+ Book Appointment</span>
                    <span class="chip a-pop" style="--d:9.8s">or the <b style="color:var(--accent)">+</b> in any day &rarr; starts on that date</span>
                  </div>
                  <div class="a-rise" style="--d:12.6s">
                    <div class="ph1">Book appointment</div><div class="psub"><span class="lk">&larr; Back to calendar</span></div>
                    <div class="fs">
                      <div class="lg">Customer</div>
                      <span class="fl">Existing customer</span>
                      <div class="ib2 a-ring" style="--d:15.4s"><span class="stk"><span class="phd a-out" style="--d:16.2s">Search by name, postcode, phone or email&hellip;</span><span class="a-type" style="--d:16.2s;--ts:4;--tt:.6s">Reed</span></span></div>
                      <div class="dl flo a-mid" style="--d:17.2s;--d2:20.6s;width:80%"><b class="pk">Angela Reed &mdash; Leamington Spa CV32 5PJ &mdash; 07700 900114 &mdash; angela@example.co.uk</b><b>Pat Reed &mdash; Warwick CV34 4AB</b></div>
                      <p class="fh stk"><span class="a-out" style="--d:20.6s">Leave blank to add a new customer.</span><span class="a-fade" style="--d:20.6s">Booking for this existing customer &mdash; any changes below also update their record.</span></p>
                      <span class="fl">Name <span class="rq">*</span></span>
                      <div class="ib2"><span class="a-fade" style="--d:20.8s">Angela Reed</span></div>
                      <div class="g3"><div><span class="fl">Email</span><div class="ib2"><span class="a-fade" style="--d:21.1s">angela@example.co.uk</span></div></div>
                        <div><span class="fl">Phone (landline)</span><div class="ib2"><span class="a-fade" style="--d:21.4s">01926 555114</span></div></div>
                        <div><span class="fl">Mobile</span><div class="ib2"><span class="a-fade" style="--d:21.7s">07700 900114</span></div></div></div>
                    </div>
                  </div>
                </div>

                <!-- 11 — a new customer, and the address -->
                <div class="sc" data-scene="11" data-len="26">
                  <div class="fs">
                    <div class="lg">Customer</div>
                    <span class="fl">Existing customer</span><div class="ib2"><span class="phd">Search by name, postcode, phone or email&hellip;</span></div>
                    <p class="fh">Leave blank to add a new customer.</p>
                    <span class="fl">Name <span class="rq">*</span></span>
                    <div class="ib2 a-ring" style="--d:4.4s"><span class="a-type" style="--d:1.6s;--ts:12;--tt:.9s">Emma Fletcher</span></div>
                    <span class="ck"><span class="tk">&check;</span> Mobile is on WhatsApp</span>
                  </div>
                  <div class="row a-rise" style="--d:8s;margin-bottom:.5rem">
                    <span class="chip">Saving = a new customer record</span>
                    <span class="twins"><span class="twin">Emma Fletcher</span><span class="twin a-pop" style="--d:11.6s;border-color:var(--err);color:var(--err)">Emma Fletcher &#10007;</span></span>
                    <span class="chip bad a-pop" style="--d:12.4s">Search first &mdash; no doubles</span>
                  </div>
                  <div class="fs a-rise" style="--d:15s">
                    <div class="lg">Installation address</div>
                    <div class="lkr"><div><span class="fl">Find by postcode</span><div class="ib2"><span class="stk"><span class="phd a-out" style="--d:20.2s">Find by postcode (e.g. BS1 4ST)</span><span class="a-type" style="--d:20.2s;--ts:8;--tt:.7s">CV32 5PJ</span></span></div></div>
                      <span class="btns a-press" style="--d:22s">Find address</span></div>
                    <span class="fl">Address line 1</span><div class="ib2"><span class="a-fade" style="--d:23.2s">14 Clarendon Street</span></div>
                    <span class="ck"><span class="tk"></span> Different billing address?</span>
                  </div>
                </div>

                <!-- 12 — date, time and who -->
                <div class="sc" data-scene="12" data-len="25">
                  <div class="warnbox a-fade" style="--d:17.4s">&#9888;&#65039; Sam Yates is already booked 10:00&ndash;11:00 (Tom Shah) that day &mdash; they can&rsquo;t be in two places at once. Pick another time, assignee, or day.
                    <br><span class="btnw a-ring" style="--d:22.4s">Book anyway</span></div>
                  <div class="fs a-rise" style="--d:.4s">
                    <div class="lg">Appointment</div>
                    <div class="g4">
                      <div><span class="fl">Date <span class="rq">*</span></span><div class="ib2 a-ring" style="--d:2.6s">08/10/2026</div></div>
                      <div><span class="fl">Time <span class="rq">*</span></span><div class="ib2 a-ring" style="--d:3.6s"><span class="stk"><span class="a-out" style="--d:9.8s">09:00</span><span class="a-fade" style="--d:9.8s">10:00</span></span></div>
                        <div class="dl flo a-mid" style="--d:6.6s;--d2:12s;width:7rem"><b>08:00</b><b>08:30</b><b>09:00</b><b>09:30</b><b class="pk">10:00</b><b>&hellip; 18:00</b></div></div>
                      <div><span class="fl">Duration (mins)</span><div class="ib2 num a-ring" style="--d:13.6s">60</div></div>
                      <div><span class="fl">Assigned to</span><div class="ib2 sel a-ring" style="--d:5.2s">Sam Yates</div></div>
                    </div>
                    <p class="fh a-fade" style="--d:8.6s">Type into the time box &mdash; it shows <b>HH:MM (e.g. 09:30)</b> until you do. Not on the list? &ldquo;No common slot &mdash; type any HH:MM you like.&rdquo;</p>
                    <span class="fl">Notes</span><div class="ib2" style="min-height:2rem">Side gate, dog in the garden.</div>
                  </div>
                  <div class="row"><span class="btnp">Book appointment</span><span class="btns">Cancel</span></div>
                </div>

                <!-- 13 — or offer a time slot -->
                <div class="sc" data-scene="13" data-len="26">
                  <div class="fs a-rise" style="--d:2s">
                    <div class="lg">Settings &rarr; Calendar</div>
                    <span class="ck" style="margin-top:0;font-weight:700"><span class="tk a-sel" style="--d:4.4s">&check;</span> &#128344; Booking time slots</span>
                    <div class="setrow" style="font-size:.58rem;color:var(--faint);font-weight:700"><span>Name</span><span>From</span><span>To</span><span>Bookings / day</span><span></span></div>
                    <div class="setrow a-fly" style="--d:6.6s"><span class="ib2">Morning</span><span class="ib2">09:00</span><span class="ib2">13:00</span><span class="ib2 num">4</span><span class="rm">&#10005; Remove</span></div>
                    <div class="setrow a-fly" style="--d:7.4s"><span class="ib2">Afternoon</span><span class="ib2">13:00</span><span class="ib2">17:00</span><span class="ib2 num">4</span><span class="rm">&#10005; Remove</span></div>
                    <div class="setrow a-fly" style="--d:9.2s"><span class="ib2"><span class="a-type" style="--d:9.6s;--ts:7;--tt:.6s">Evening</span></span><span class="ib2">18:00</span><span class="ib2">20:00</span><span class="ib2 num">2</span><span class="rm">&#10005; Remove</span></div>
                    <span class="btns a-press" style="--d:8.8s;margin-top:.4rem">+ Add a time slot</span>
                  </div>
                  <div class="fs a-rise" style="--d:12.6s">
                    <div class="lg">Appointment &mdash; Time slot <span class="rq">*</span></div>
                    <div class="slots">
                      <span class="slot"><span class="rd"></span><span>Morning <span class="rg">(9am&ndash;1pm)</span></span><span class="lf">4 of 4 left</span></span>
                      <span class="slot a-ring" style="--d:15.6s"><span class="rd on"></span><span>Afternoon <span class="rg">(1pm&ndash;5pm)</span></span><span class="lf">4 of 4 left</span></span>
                      <span class="slot"><span class="rd"></span><span>Evening <span class="rg">(6pm&ndash;8pm)</span></span><span class="lf">2 of 2 left</span></span>
                    </div>
                    <p class="fh">The customer is given this window, never an exact time.</p>
                    <span class="ck a-ring" style="--d:21.6s"><span class="tk on">&check;</span> Email the customer their appointment window (needs an email above)</span>
                  </div>
                </div>

                <!-- 14 — when a slot is full -->
                <div class="sc" data-scene="14" data-len="25">
                  <div class="errb a-fade" style="--d:17.8s">Afternoon (1pm&ndash;5pm) is fully booked on 9 Oct 2026. Please choose another window or another day.</div>
                  <div class="fs">
                    <div class="lg">Appointment</div>
                    <div class="g2"><div><span class="fl">Date <span class="rq">*</span></span><div class="ib2 a-ring" style="--d:12.4s"><span class="stk"><span class="a-out" style="--d:13.6s">08/10/2026</span><span class="a-fade" style="--d:13.6s">09/10/2026</span></span></div></div>
                      <div><span class="fl">Assigned to</span><div class="ib2 sel">Sam Yates</div></div></div>
                    <span class="fl">Time slot <span class="rq">*</span></span>
                    <div class="slots">
                      <span class="slotstk"><span class="slot full a-out" style="--d:14s"><span class="rd"></span><span>Morning <span class="rg">(9am&ndash;1pm)</span></span><span class="lf">Full</span></span>
                        <span class="slot a-fade" style="--d:14s"><span class="rd"></span><span>Morning <span class="rg">(9am&ndash;1pm)</span></span><span class="lf">2 of 4 left</span></span></span>
                      <span class="slot a-ring" style="--d:3.6s"><span class="rd on"></span><span>Afternoon <span class="rg">(1pm&ndash;5pm)</span></span><span class="lf stk"><span class="a-out" style="--d:14s">3 of 4 left</span><span class="a-fade" style="--d:14s">1 of 4 left</span></span></span>
                      <span class="slot"><span class="rd"></span><span>Evening <span class="rg">(6pm&ndash;8pm)</span></span><span class="lf">2 of 2 left</span></span>
                    </div>
                  </div>
                  <div class="row">
                    <span class="chip bad a-pop" style="--d:8.6s">Full = greyed out, can&rsquo;t be picked</span>
                    <span class="chip a-pop" style="--d:15s">New date &rarr; counts refresh</span>
                    <span class="chip a-pop" style="--d:20s">Checked again when you save</span>
                    <span class="chip ok a-pop" style="--d:23.4s">Fittings never use up a slot</span>
                  </div>
                  <div class="a-move" style="--fx:70%;--fy:18rem;--tx:3%;--ty:9.5rem;--d:8.2s;--md:1.4s">' . $ptr . '</div>
                </div>

                <!-- 15 — notes and problems -->
                <div class="sc" data-scene="15" data-len="25">
                  <div class="ctool" style="justify-content:flex-end">
                    <div class="leg"><span><i style="background:#6366f1"></i> Fitting booked</span><span><i style="background:transparent;outline:2px solid #111827;outline-offset:-2px"></i> = Fitting</span>
                      <span class="issp a-ring" style="--d:16.6s"><i style="background:transparent;outline:2px solid #e11d48;outline-offset:-2px"></i> &#9888;&#65039; Issues <span class="stk" style="display:inline-grid"><span class="a-out" style="--d:14s">&nbsp;</span><span class="a-fade" style="--d:14s">(1)</span></span></span></div>
                  </div>
                  <div class="two">
                    <div class="mdl a-rise" style="--d:2.4s">
                      <h4>Appointment note</h4>
                      <p>A short reminder for the day &mdash; e.g. &ldquo;tap gently, baby asleep&rdquo;.</p>
                      <div class="txa"><span class="a-type" style="--d:4.4s;--ts:24;--tt:1.4s">Ring the side door bell</span></div>
                      <div class="act"><span class="btnp a-press" style="--d:6.8s">Save</span><span class="btns">Remove</span><span class="btns">Cancel</span></div>
                    </div>
                    <div class="mdl a-rise" style="--d:8.4s">
                      <h4>&#9888;&#65039; Flag an issue</h4>
                      <p>What&rsquo;s the problem? &mdash; e.g. &ldquo;wrong colour delivered&rdquo;, &ldquo;no access&rdquo;, &ldquo;remake needed&rdquo;.</p>
                      <div class="txa"><span class="a-type" style="--d:10s;--ts:22;--tt:1.3s">wrong colour delivered</span></div>
                      <div class="act"><span class="btnp a-press" style="--d:12.6s">Flag as issue</span><span class="btns">Clear issue</span><span class="btns">Cancel</span></div>
                    </div>
                  </div>
                  <div style="margin-top:.6rem">' . $grid(2, $g16cards, null, null, 'sm') . '</div>
                  <span class="chip a-pop" style="--d:21s;margin-top:.5rem">Issues on &rarr; only the flagged jobs</span>
                </div>

                <!-- 16 — finishing a job -->
                <div class="sc" data-scene="16" data-len="25">
                  <div class="chd"><div><div class="ph1">Install: ABC-2026-0042 &mdash; Emma Fletcher</div><div class="psub"><span class="lk">&larr; Back to calendar</span></div></div>
                    <div class="acts"><span class="btng">Google Maps &rarr;</span><span class="btng">Waze &rarr;</span><span class="btnp">Open order &rarr;</span><span class="btns">Edit</span></div></div>
                  <div class="okb a-pop" style="--d:6.6s">Status updated to Completed. Linked quote ABC-2026-0042 advanced to &ldquo;fitted&rdquo;.</div>
                  <div class="secth">When &amp; status <span class="stp"><span class="spl a-out" style="--d:6.4s;background:#e0e7ff;color:#3730a3">Booked</span><span class="spl a-fade" style="--d:6.4s;background:var(--good-wash);color:var(--good)">Completed</span></span></div>
                  <dl class="dlr"><dt>Date</dt><dd>Friday, 16 October 2026</dd><dt>Time</dt><dd>10:30am &ndash; 11:30am <span style="color:var(--faint)">(60 mins)</span></dd>
                    <dt>Assigned to</dt><dd><span class="ib2 sel" style="display:inline-flex;min-width:9rem">Dave Cole (fitter)</span> <span class="btns">Save</span></dd></dl>
                  <div class="row" style="font-size:.7rem;color:var(--ink)">Update status:
                    <span style="position:relative;display:inline-block"><span class="ib2 sel a-ring" style="--d:1.6s;display:inline-flex;min-width:7.5rem"><span class="stk"><span class="a-out" style="--d:3.6s">Booked</span><span class="a-fade" style="--d:3.6s">Completed</span></span></span>
                      <span class="dl flo a-mid" style="--d:2.4s;--d2:4s;display:block;width:7.5rem;top:100%;left:0"><b>Booked</b><b class="pk">Completed</b><b>Cancelled</b><b>No-show</b></span></span>
                    <span class="btns a-press" style="--d:5s">Save status</span>
                    <span class="chip ok a-pop" style="--d:8.4s">Order: Ordered &rarr; <b>Fitted</b></span>
                  </div>
                  <div class="row" style="margin-top:.5rem"><span class="chip a-pop" style="--d:13s">Didn&rsquo;t happen? <b>Cancelled</b> or <b>No-show</b></span></div>
                  <div class="dz a-rise" style="--d:17.6s"><b>Danger zone</b> &mdash; Deleting this appointment is permanent. The customer record and any linked quotes will be kept; only the calendar entry is removed.
                    <div style="margin-top:.35rem"><span class="btnd a-ring" style="--d:20.6s">Delete appointment</span></div></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> <b>Calendar</b> sits under <b>Work</b> in the menu on the left. The page is headed <b>Calendar</b>,
             and the line under it says whose calendar you are looking at &mdash; <em>&ldquo;Appointments for &lt;your company&gt;.&rdquo;</em>
             &mdash; followed by two links, <b>&#128198; Week</b> and <b>&#128197; Day</b>. Top right: <b>+ Book Appointment</b> always,
             <b>+ New quote</b> if your login may create quotes, and a green <b>Today&rsquo;s run &rarr;</b> only if the maps add-on is on for
             your account (it plots the day&rsquo;s visits as a driving route).</p>

          <ul class="steps">
            <li><b>Six weeks from a Monday &mdash; not a month.</b> The board always shows six weeks starting on a Monday. <b>&lsaquo;</b>
                and <b>&rsaquo;</b> move <b>one week</b> at a time; the middle names the month most of the screen belongs to
                (<em>October 2026</em>) with the real span under it (<em>Mon 5 Oct &mdash; Sun 15 Nov</em>), and <b>Today</b> appears once you
                have moved off this week. Each square is labelled <b>DD/MM</b> (<b>07/10</b> = 7 October); today is tinted, and days outside the
                named month are shaded but work normally.</li>
            <li><b>Reading a card.</b> Each card shows the time (<em>9:00am</em>) or, for a visit booked into a time slot, the slot&rsquo;s name
                (<em>Morning</em>); then the job reference and the title, e.g. <b>&ldquo;ABC-2026-0041 &mdash; Tom Shah&rdquo;</b> (the reference
                is only added when the title doesn&rsquo;t already contain it, so a fitting reads
                <b>&ldquo;Install: ABC-2026-0042 &mdash; Emma Fletcher&rdquo;</b>). The colour is the stage the job has reached, from your own
                <b>Settings &rarr; Status colours</b>: <b>Quote drafted, Quote sent, Accepted, Declined, Ordered, Appointment booked, Fitting booked,
                Fitted, Invoiced, Paid, Cancelled, No-show</b> &mdash; the key across the toolbar lists them. <b>Fittings carry a dark outline</b>;
                measures don&rsquo;t. Click a card to open the appointment; click its small <b>&rarr;</b> (&ldquo;Open order&rdquo;) to go straight to
                the order. The board refreshes itself in the background, so a colleague&rsquo;s change appears without reloading.</li>
            <li><b>Everyone or Just me.</b> Under the subtitle, <b>Everyone</b> shows the whole team; <b>Just me</b> shows only jobs assigned to
                you (<em>&ldquo;Filtered to &lt;your name&gt;&rsquo;s appointments only.&rdquo;</em>). A login that may not see other people&rsquo;s jobs
                has no switch at all and reads <em>&ldquo;Your appointments.&rdquo;</em> &mdash; the permission doing its job. If Just me finds
                nothing: <em>&ldquo;No appointments assigned to you in this 6-week window.&rdquo;</em></li>
            <li><b>Week and Day.</b> <b>Week view</b> is a 7-day grid; <b>Day view</b> (<em>&ldquo;Who&rsquo;s doing what today, with full address +
                tap-to-call.&rdquo;</em>) gives each bookable person a column, with the address and a tap-to-call number. Both have <b>Month</b>,
                <b>Week</b> and <b>Day</b> buttons and a date picker. Clicking an empty slot in Day view starts a booking with that time and person
                already filled in. Neither shows the Pending Fitting tray; they say <em>&ldquo;1 fitting pending &mdash; place it on the Month
                calendar to schedule.&rdquo;</em> instead.</li>
            <li><b>Each person, their own phone.</b> Everyone signs in to YourBlinds on their own phone or tablet and sees their jobs in the app
                &mdash; the calendar, the Day view with tap-to-call, and each appointment with its address, notes and (when the job has an
                address) <b>Google Maps &rarr;</b> / <b>Waze &rarr;</b> buttons. There is no link to the phone&rsquo;s own calendar app to set
                up; the YourBlinds calendar is the one place.</li>
          </ul>

          <p><b>Fittings: the Pending Fitting tray.</b></p>
          <ul class="steps">
            <li><b>Where they come from.</b> When a retail quote is accepted &mdash; by the customer online, or by you pressing <b>Mark as
                accepted</b> / <b>&#10003; Customer accepted</b> / <b>&#128230; Save as order</b> &mdash; the app writes the install for you,
                titled <b>&ldquo;Install: &lt;job&gt; &mdash; &lt;customer&gt;&rdquo;</b>, and parks it with no date in the <b>Pending Fitting</b>
                tray above the board: <em>&ldquo;Drag a card onto a date to schedule it.&rdquo;</em> If you have exactly one fitter it is already
                assigned to them. Trade jobs never get a fitting. Nothing alerts you &mdash; check the tray each morning. Empty, it reads
                <em>&ldquo;Nothing pending. Accepted quotes land here until you place them on a date.&rdquo;</em> Declining the quote takes a pending
                fitting back off.</li>
            <li><b>Drag it onto the day.</b> Drop the card on a date and <b>Set fitting time</b> pops up with <b>09:00</b> in the box; change it
                and press <b>Schedule</b> (<b>Enter</b> does the same). <b>Cancel</b> or <b>Esc</b> leaves it in the tray.</li>
            <li><b>Moving a booked job</b> is the same drag and changes the <b>date only</b> &mdash; time, length, person and status stay as
                they were. If the person is already booked then, the browser asks: <em>&ldquo;&hellip; they can&rsquo;t be in two places at once.
                Pick another time, assignee, or day. Book anyway?&rdquo;</em> A visit booked into a time slot can&rsquo;t be dragged onto a day
                where that slot is full &mdash; <em>&ldquo;Morning (9am&ndash;1pm) is full on 22 Oct 2026. Move it to another day or
                window.&rdquo;</em> and it snaps back. Drag a card back onto the tray to take it off the calendar. Anything more than a new date
                is <b>Edit</b> on the appointment, then <b>Save changes</b>.</li>
          </ul>

          <p><b>Booking a visit yourself</b> (usually the measure) &mdash; <b>+ Book Appointment</b>, or the <b>+</b> in any day square to
             start on that date. The <b>Book appointment</b> form has three sections:</p>
          <ul class="steps">
            <li><b>Customer.</b> <b>Existing customer</b> searches people already on the system (<em>&ldquo;Search by name, postcode, phone or
                email&hellip;&rdquo;</em>); picking one fills the form &mdash; <em>&ldquo;Booking for this existing customer &mdash; any changes
                below also update their record.&rdquo;</em> Otherwise <em>&ldquo;Leave blank to add a new customer.&rdquo;</em> and fill in
                <b>Name</b> (the only required field), <b>Email</b>, <b>Phone (landline)</b>, <b>Mobile</b> and <b>Mobile is on WhatsApp</b>.
                Saving a new name <b>creates a new customer record</b> &mdash; search first, or you&rsquo;ll have the same person twice.</li>
            <li><b>Installation address</b> &mdash; where the blinds go. With the postcode add-on: <b>Find by postcode</b>, <b>Find address</b>,
                then <b>Pick an address</b>. Otherwise type <b>Address line 1</b>, <b>Address line 2</b>, <b>Town</b>, <b>County</b>,
                <b>Postcode</b>. Tick <b>Different billing address?</b> only if the bill goes elsewhere.</li>
            <li><b>Appointment &mdash; exact time.</b> <b>Date</b>, <b>Time</b> (type it &mdash; it shows <code>HH:MM (e.g. 09:30)</code> &mdash;
                or pick from the half hours <b>08:00</b> to <b>18:00</b>; anything else gets <em>&ldquo;No common slot &mdash; type any HH:MM you
                like.&rdquo;</em>), <b>Duration (mins)</b> (starts at 60, 5 to 1440), <b>Assigned to</b> (your sales people, starting with
                <b>&mdash; Unassigned &mdash;</b>; it preselects you if you do measures, or your only salesperson), then <b>Notes</b> and
                <b>Book appointment</b>. A clash shows <em>&ldquo;Sam Yates is already booked 10:00&ndash;11:00 (Tom Shah) that day &mdash;
                they can&rsquo;t be in two places at once. Pick another time, assignee, or day.&rdquo;</em> with a <b>Book anyway</b> button.</li>
            <li><b>Appointment &mdash; time slots.</b> With <b>&#128344; Booking time slots</b> ticked in <b>Settings &rarr; Calendar</b> (each slot
                has a <b>Name</b>, <b>From</b>, <b>To</b> and <b>Bookings / day</b>; <b>+ Add a time slot</b> up to six), measure visits use a
                <b>Time slot</b> instead: one radio card per slot, e.g. <b>Afternoon (1pm&ndash;5pm) &middot; 3 of 4 left</b>. <em>&ldquo;The
                customer is given this window, never an exact time.&rdquo;</em> A full slot reads <b>Full</b>, is greyed out and can&rsquo;t be
                picked; changing the date refreshes the counts. <b>Email the customer their appointment window (needs an email above)</b> is
                ticked to start with. Saving re-checks the space: <em>&ldquo;Afternoon (1pm&ndash;5pm) is fully booked on 8 Oct 2026. Please choose
                another window or another day.&rdquo;</em> Cancelled and no-show visits give their place back; fittings never count.</li>
          </ul>
          <p>Booked, you&rsquo;re back on the calendar with <em>&ldquo;Appointment booked for Angela Reed on 8 Oct 2026, Afternoon
             (1pm&ndash;5pm).&rdquo;</em> Open a measure appointment and <b>Start quote</b> begins the quote from it.</p>

          <p><b>Notes and issues.</b> Every card has <b>&#128221;</b> &mdash; <b>Appointment note</b>, <em>&ldquo;A short reminder for the day
             &mdash; e.g. &ldquo;tap gently, baby asleep&rdquo;.&rdquo;</em>, up to 280 characters, <b>Save</b> / <b>Remove</b> / <b>Cancel</b>
             &mdash; and <b>&#9888;&#65039;</b> &mdash; <b>Flag an issue</b>, <b>Flag as issue</b> / <b>Clear issue</b> / <b>Cancel</b>. A flagged
             card gets a red ring, and <b>&#9888;&#65039; Issues (N)</b> at the end of the key filters the board to flagged jobs (click again to
             show all). The same flag is on the <b>Edit</b> form: <b>&#9888;&#65039; Flag this job as having an issue</b>.</p>

          <p><b>Finishing a job.</b> Open the card. In <b>When &amp; status</b>, <b>Assigned to</b> + <b>Save</b> changes who goes, and
             <b>Update status:</b> (<b>Booked</b>, <b>Completed</b>, <b>Cancelled</b>, <b>No-show</b>) + <b>Save status</b> records what
             happened. Completing a <b>fitting</b> moves the order on to Fitted: <em>&ldquo;Status updated to Completed. Linked quote
             ABC-2026-0042 advanced to &ldquo;fitted&rdquo;.&rdquo;</em></p>

          <div class="oops"><b>Delete is permanent.</b> <em>&ldquo;Deleting this appointment is permanent. The customer record and any linked
             quotes will be kept; only the calendar entry is removed.&rdquo;</em> If the visit just didn&rsquo;t happen, use <b>Cancelled</b> or
             <b>No-show</b>. If a completed fitting is later set to Cancelled or No-show, the page shows <b>Status mismatch:</b> with a
             <b>Rewind quote &raquo;</b> button &mdash; use it only if the install really didn&rsquo;t happen (<em>&ldquo;Quote ABC-2026-0042
             rewound from &ldquo;fitted&rdquo; back to &ldquo;ordered&rdquo;.&rdquo;</em>).</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Money on the calendar.</b> Ticking <b>&#128183; Show order value + balance
             on the calendar</b> in <b>Settings &rarr; Calendar</b> adds each linked job&rsquo;s value, amount received and balance to its card.
             Only people allowed to see money see the figures &mdash; admins, plus anyone with <b>&ldquo;Can see money&rdquo;</b> ticked on the
             <b>Users</b> page.</div></div>',
        'script'  => [
            ['1', 'What the calendar is',            'This is the calendar. It is where every visit your team makes is kept: the measures you book so you can quote, and the fittings once a job is sold. You will find it in the menu on the left, under Work. Each visit is a small card, sitting on the day it happens. The whole team works from this one calendar, so everybody sees the same picture.', 1],
            ['2', 'Six weeks from a Monday',         'Look closely, because this is not a calendar month. It shows six weeks, always starting on a Monday. The arrows either side step you back or forward one week at a time, and the Today button brings you home again. Every square is labelled with the day and the month, so there is no doubt which date it is. Days from the next month are shaded, but they work just the same.', 2],
            ['3', 'Reading a card',                  'Each card starts with the time, or the name of a time slot, such as Afternoon. Then comes the job number and the customer. The colour shows how far the job has got, from booked all the way to paid, in your own colours from Settings. Fittings carry a dark outline. Click a card to open the appointment, or click its little arrow to go straight to the order.', 3],
            ['4', 'Everyone, or just me',            'Under the heading is a small switch with two halves. Everyone shows the whole team\'s work. Just me shows only the jobs assigned to you. If someone is not allowed to see other people\'s jobs, the switch is not there at all, and the heading simply says, your appointments. That is the permission doing its job. It is not a fault.', 4],
            ['5', 'Week and Day views',              'Beside the heading are two more ways to look. Week shows seven days side by side. Day gives every member of your team a column of their own, with the full address, and a phone number you can tap to call. Week and Day both have Month, Week and Day buttons, to hop between them. Fittings waiting for a date only show on the month view, so the others give you a reminder.', 5],
            ['6', 'On each person\'s phone',         'Each person in your team signs in on their own phone or tablet, and sees their jobs right there in the app. A fitter opens the calendar, and sees the jobs assigned to them. Tap a job, and the address, the notes and the customer\'s number are all there. There is nothing to link to the phone\'s own calendar. This is the one calendar, and while it is open, it keeps itself up to date.', 6],
            ['7', 'The Pending Fitting tray',        'When a customer accepts a quote, the app writes the fitting for you. It has no date yet, so it waits here, in the Pending Fitting tray above the calendar. Nothing pings you when one arrives, so make a habit of glancing at this tray each morning. When the tray is empty it says so: nothing pending. Accepted quotes land here until you place them on a date.', 7],
            ['8', 'Drag it onto a day',              'To book the fitting, pick the card up, and drop it on the day you agreed with the customer. A small box pops up, asking for the fitting time. It starts at nine o\'clock. Change it if you need to, then press Schedule. The card is now on the calendar, and it has left the tray. Press Cancel instead, and it simply stays waiting in the tray.', 8],
            ['9', 'Moving a booked job',             'Moving a job that is already booked is the same drag. It changes the date only. The time, the length, the person and the status all stay exactly as they were. If that person is already booked at that time, it warns you, and asks whether to book anyway. Drag a card back onto the tray to take it off the calendar. For a new time, open the card and press Edit.', 9],
            ['10', 'Booking a visit yourself',       'Now, booking a visit yourself. Usually that is the measure, before there is any quote. Press Book Appointment at the top, or click the little plus in any day, to start on that date. First comes the customer. If they are already on the system, search for them by name, postcode, phone or email, and their details fill in for you.', 10],
            ['11', 'A new customer, and the address', 'Someone new? Leave the search empty, and type their name. The name is the only thing you must fill in. Saving creates a brand new customer, so always search first, or you end up with the same person twice. Then the installation address, which is where the blinds will go. If you have the postcode finder, type the postcode, and press Find address.', 11],
            ['12', 'Date, time and who',             'Then the appointment itself: the date, the time, how long it will take, and who is going. The time box offers every half hour from eight until six, but you can type any time you like. The length starts at sixty minutes. If the person you pick is already busy then, it tells you they cannot be in two places at once, and offers to book anyway.', 12],
            ['13', 'Or offer a time slot',           'Many firms would rather not promise an exact time. Switch on Booking time slots in Settings, under Calendar, and name your own slots, such as Morning, Afternoon or Evening. Now the customer is given a slot, never an exact hour, so a job that overruns never makes you late for the next one. Leave the email box ticked, and they are sent their slot in writing.', 13],
            ['14', 'When a slot is full',            'Each slot holds a set number of visits a day, and it tells you how many are left, such as three of four left. A full slot is greyed out, and cannot be picked. Choose another slot, or change the date, and the counts update straight away. If two people grab the last place at once, the app checks again when you save. Fittings never use up a slot.', 14],
            ['15', 'Notes and problems',             'Every card has two small buttons. The notepad adds a short reminder for the day, such as, tap gently, baby asleep. The warning sign flags a problem, such as the wrong colour delivered. A flagged card gets a red ring, and the Issues button at the end of the key counts them. Click it, and the calendar shows only the jobs with a problem.', 15],
            ['16', 'Finishing a job',                'When the fitting is done, open its card, set the status to Completed, and press Save status. That one change moves the order itself on to Fitted, so you never do it twice. If the visit did not happen, choose Cancelled or No-show instead. Only delete an appointment if it was a mistake. Deleting is permanent, and the entry is gone for good.', 16],
        ],
];
