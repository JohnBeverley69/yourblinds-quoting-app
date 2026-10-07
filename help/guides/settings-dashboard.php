<?php
declare(strict_types=1);

/**
 * Guide: settings-dashboard — "Your dashboard" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the "Dashboard" section on the Company tab of /admin/settings.php
 * (the joke tickbox, its hint, its own Save and the 'joke' handler's flash
 * strings), the dashboard itself (dashboard/index.php: the Joke of the day
 * card with "🔁 Another" and "✕" (hide until tomorrow, localStorage = per
 * browser), the period bar, "View:" / "All sales team" (only people who have
 * raised a quote), the panels in page order) and the per-user panel ticks on
 * admin/users_edit.php (Permissions: View costs, Can see money; Dashboard
 * fieldset: Sales-team leaderboard, Product mix, Gross profit, Recent wins).
 * Everyone without "Can see money" (non-admins) is sent to the Calendar.
 *
 * v2: one SCENE per script line (data-scene = the line's step); data-len is
 * worked out from the line itself (characters ÷ 13.6).
 */

$script = [
    ['1', 'One switch in Settings',  'Most of your dashboard is steered from the dashboard itself. In Settings, there is just one switch for it, and it is a bit of fun. Go to Setup, then Settings, and stay on the Company tab. Scroll down past your company details and your logo, and you will find a small section called Dashboard.', 1],
    ['2', 'Joke of the day',         'It has one tick box: Show a Joke of the day on the dashboard. It is already ticked when your account is set up, so you do not have to do anything to switch it on. The note underneath says it is a little light relief for your team. It is staff only, and never shown to customers.', 2],
    ['3', 'Its own Save button',     'This little section has its own Save button. Change the tick, and press that Save. Not the Save company details button higher up, which belongs to a different form. The page reloads, and a green bar says, Joke of the day is on. Or, if you untick it, Joke of the day is off.', 3],
    ['4', 'What your team sees',     'Here is what your team sees. At the top of the dashboard, a soft yellow card: a smiley face, the words Joke of the day, and the joke. Everyone in your team gets the same joke all day. Press Another, and it shuffles up a different one, just for a laugh.', 4],
    ['5', 'Hiding it for the day',   'The little cross hides the joke until tomorrow. But it only hides it on the computer you are using. Open the dashboard on the tablet in the van, or in another browser, and it is back. To switch it off for everyone, untick the box in Settings, and press Save.', 5],
    ['6', 'Choosing the period',     'Now the rest of the dashboard, which you steer from the screen itself. Along the top are This month, Last thirty days, This quarter, This year, and All time. Or pick your own From and To dates, and press Apply. Every money figure on the page follows the period you choose.', 6],
    ['7', 'One person at a time',    'Underneath is View. It starts on All sales team. Pick a name, and the whole page narrows to that one person\'s numbers. It only lists people who have actually raised a quote, so a brand new starter will not appear until they have written one.', 7],
    ['8', 'The panels',              'Then come the panels. Upcoming jobs shows your next eight bookings from the calendar. Then the money: revenue won, average order value, close rate, and jobs in the period. Then your sales team, what is selling, your gross profit, and your most recent wins. The money panels all follow the period, and the person, you have picked.', 8],
    ['9', 'Who sees which panels',   'Who sees which panels is not set here. It is on the Users page. Edit a person, and tick the panels they may see. Every panel shows money, so they also need Can see money ticked, and Gross profit also needs View costs. Admins always see everything. Anyone who cannot see money goes straight to the calendar instead.', 9],
];
$L = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr  = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$hint = '<p class="hintg">A little light relief for your team on the dashboard (staff only &mdash; never shown to customers). Dismissible per day. Untick to switch it off.</p>';
// The Dashboard section. $tick = the checkbox markup; $save = Save button classes/style; $more = extra inside .fact.
$dashSec = static function (string $tick = '<span class="tick on">&#10003;</span>', string $saveCls = '', string $saveSt = '', string $more = '', string $cls = '', string $st = '') use ($hint): string {
    return '<div class="secbox ' . $cls . '" style="' . $st . '"><div class="sech">Dashboard</div>'
         . '<label class="ckl">' . $tick . ' &#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard</label>' . $hint
         . '<div class="fact"><span class="btnp ' . $saveCls . '" style="' . $saveSt . '">Save</span>' . $more . '</div></div>';
};
$joke = 'Why did the blind go to school? It wanted to be a little brighter.';
$joke2 = 'I&rsquo;d tell you a roller blind joke, but it always comes back round.';
$card = static fn (string $text, string $extra = '') => '<div class="jotd"><span class="jem">&#128516;</span><div class="jb"><div class="jl">Joke of the day</div><div class="jt">' . $text . '</div></div>'
    . '<div class="ja"><span class="jbtn">&#128257; Another</span><span class="jbtn">&#10005;</span></div>' . $extra . '</div>';
$cb = static fn (string $label, string $tick = '') => '<label class="ckl sm">' . ($tick !== '' ? $tick : '<span class="tick"></span>') . ' ' . $label . '</label>';

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your dashboard',
        'eyebrow' => 'Settings · Company',
        'v'       => 2,
        'blurb'   => 'The one Dashboard switch in Settings, the whole dashboard it sits on, and where you say who sees which panel.',
        'lede'    => 'Settings has exactly <b>one</b> switch for the dashboard &mdash; the <b>Joke of the day</b> &mdash; on the
                      <b>Company</b> tab. This guide covers that switch, then walks round the dashboard itself (which you steer
                      from the dashboard, not from Settings), and finishes with the question everyone asks: <b>who sees which
                      panel</b>. To get there: <b>Setup</b> &rarr; <b>Settings</b> &rarr; <b>Company</b>, then scroll to
                      <b>Dashboard</b>.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* the settings page */
          .gd .ph1{ font-size:.98rem; font-weight:800; color:var(--ink); }
          .gd .ph2{ font-size:.66rem; color:var(--faint); margin:.05rem 0 .55rem; }
          .gd .secbox{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:27rem; }
          .gd .secbox + .secbox{ margin-top:.5rem; }
          .gd .secbox.dim{ opacity:.55; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .ckl{ display:flex; align-items:center; gap:.45rem; font-size:.74rem; font-weight:600; color:var(--ink); }
          .gd .ckl.sm{ font-size:.68rem; font-weight:400; }
          .gd .hintg{ font-size:.6rem; color:var(--faint); line-height:1.45; margin:.3rem 0 0; }
          .gd .fact{ margin-top:.5rem; position:relative; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; }
          .gd .flash{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); color:var(--good); font-weight:700;
                      font-size:.72rem; border-radius:8px; padding:.4rem .65rem; margin-bottom:.55rem; max-width:27rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.good{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.7rem; }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
          .gd .split{ display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:.9rem; align-items:start; }
          .gd .side2{ display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; min-width:0; }
          .gd .swap{ display:inline-grid; } .gd .swap > *{ grid-area:1/1; }
          .gd .stack{ display:grid; } .gd .stack > *{ grid-area:1/1; }

          /* the dashboard */
          .gd .dh1{ font-size:.98rem; font-weight:800; color:var(--ink); }
          .gd .dh2{ font-size:.66rem; color:var(--soft); margin:.05rem 0 .5rem; }
          .gd .jotd{ position:relative; display:flex; align-items:center; gap:.6rem; background:linear-gradient(90deg,#fffbeb,#fef9c3); border:1px solid #fde68a;
                     border-radius:12px; padding:.5rem .7rem; max-width:31rem; }
          :root[data-theme="dark"] .gd .jotd{ background:rgba(250,204,21,.08); border-color:rgba(250,204,21,.3); }
          .gd .jem{ font-size:1.3rem; line-height:1; }
          .gd .jb{ flex:1 1 auto; min-width:0; }
          .gd .jl{ font-size:.58rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:#b45309; }
          :root[data-theme="dark"] .gd .jl{ color:#fbbf24; }
          .gd .jt{ font-size:.76rem; color:var(--ink); display:grid; } .gd .jt > *{ grid-area:1/1; }
          .gd .ja{ display:flex; gap:.2rem; flex:0 0 auto; position:relative; }
          .gd .jbtn{ font-size:.66rem; font-weight:600; color:#b45309; border-radius:6px; padding:.2rem .4rem; white-space:nowrap; }
          .gd .pbar{ display:flex; flex-wrap:wrap; align-items:center; gap:.3rem; margin-bottom:.5rem; }
          .gd .pl{ font-size:.66rem; font-weight:600; color:var(--soft); border:1px solid var(--line); border-radius:7px; padding:.22rem .5rem; background:var(--surface); }
          .gd .pl.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .dbox{ display:inline-flex; align-items:center; height:22px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:0 .4rem;
                     font-size:.64rem; color:var(--ink); background:var(--surface); }
          .gd .lbl{ font-size:.62rem; font-weight:600; color:var(--soft); }
          .gd .sel{ display:inline-grid; align-items:center; min-width:9rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.22rem 1.4rem .22rem .5rem;
                    font-size:.68rem; color:var(--ink); background:var(--surface); position:relative; }
          .gd .sel::after{ content:"\25BE"; position:absolute; right:.45rem; top:.2rem; color:var(--faint); font-size:.62rem; }
          .gd .sel > span{ grid-area:1/1; }
          .gd .kpis{ display:grid; grid-template-columns:repeat(4,1fr); gap:.45rem; margin:.5rem 0; }
          .gd .kpi{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--surface); min-width:0; }
          .gd .kpi small{ display:block; font-size:.54rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .kpi b{ display:grid; font-size:.86rem; color:var(--ink); font-variant-numeric:tabular-nums; } .gd .kpi b > span{ grid-area:1/1; }
          .gd .kpi i{ display:block; font-style:normal; font-size:.56rem; color:var(--soft); }
          .gd .panel{ border:1px solid var(--line); border-radius:9px; padding:.4rem .55rem; background:var(--surface); font-size:.66rem; color:var(--soft); }
          .gd .panel h4{ margin:0 0 .25rem; font-size:.74rem; color:var(--ink); display:grid; } .gd .panel h4 > span{ grid-area:1/1; }
          .gd .panels{ display:grid; grid-template-columns:repeat(2,1fr); gap:.45rem; }
          .gd .uprow{ display:flex; gap:.45rem; align-items:center; padding:.15rem 0; border-top:1px solid var(--line-2); }
          .gd .uprow:first-of-type{ border-top:0; }
          .gd .uprow b{ color:var(--ink); min-width:4.6rem; }
          .gd .pill{ margin-left:auto; font-size:.56rem; font-weight:700; background:var(--accent-wash); color:var(--accent-ink); border-radius:999px; padding:.05rem .4rem; }
          .gd .dev{ display:flex; flex-direction:column; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface);
                    font-size:.66rem; color:var(--soft); text-align:center; }
          .gd .dev .ico{ font-size:1.4rem; }
          .gd .dev b{ color:var(--ink); }
          .gd .ghost{ border:1px dashed var(--line); border-radius:12px; padding:.75rem .7rem; font-size:.7rem; color:var(--faint); max-width:31rem; align-self:center; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; max-width:24rem; }

          /* users page */
          .gd .fs{ border:1px solid #e5e7eb; border-radius:10px; padding:.5rem .65rem; margin-top:.5rem; }
          .gd .fs .lg{ font-size:.6rem; font-weight:600; color:#1f3b5b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; }
          :root[data-theme="dark"] .gd .fs .lg{ color:var(--soft); }
          .gd .cks{ display:flex; flex-wrap:wrap; gap:.35rem .9rem; }
          .gd .tick{ flex:0 0 auto; }

          @media (max-width:640px){
            .gd .split{ grid-template-columns:1fr; }
            .gd .sc{ min-height:430px; }
            .gd .kpis{ grid-template-columns:repeat(2,1fr); }
            .gd .panels{ grid-template-columns:1fr; }
            .gd .jotd{ flex-wrap:wrap; }
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
                  <div class="ph1">Settings</div><div class="ph2">Company details and per-quote defaults.</div>
                  ' . $dashSec() . '
                  <div style="margin-top:.8rem">' . $card($joke) . '</div>
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; nine short chapters.</p>
                </div>

                <!-- 1 — where the switch is -->
                <div class="sc" data-scene="1" data-len="' . $L(1) . '">
                  <div class="chips" style="margin:0 0 .6rem"><span class="chip a-pop" style="--d:3s">One switch &mdash; and it&rsquo;s a bit of fun</span></div>
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:9.5s">Setup</span><span class="arrow a-fade" style="--d:10.3s">&rarr;</span>
                    <span class="chip a-pop" style="--d:11s">Settings</span><span class="arrow a-fade" style="--d:11.8s">&rarr;</span>
                    <span class="chip a-pop" style="--d:12.5s;border-color:var(--accent);color:var(--accent)">Company</span>
                  </div>
                  <div class="secbox dim a-rise" style="--d:15s"><div class="sech" style="margin:0">Company details</div></div>
                  <div class="secbox dim a-rise" style="--d:16.5s"><div class="sech" style="margin:0">Company logo</div></div>
                  <div class="a-rise" style="--d:18.5s;margin-top:.5rem">' . $dashSec('<span class="tick on">&#10003;</span>', '', '', '', 'a-ring', '--d:19.5s') . '</div>
                  <div class="secbox dim a-rise" style="--d:19s"><div class="sech" style="margin:0">Calendar</div></div>
                </div>

                <!-- 2 — the tick box -->
                <div class="sc" data-scene="2" data-len="' . $L(2) . '">
                  <div class="secbox a-rise" style="--d:.5s"><div class="sech">Dashboard</div>
                    <label class="ckl"><span class="tick on a-ring" style="--d:6s">&#10003;</span> <span class="a-type" style="--d:1.5s;--ts:40;--tt:2.2s">&#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard</span></label>
                    <div class="a-fade" style="--d:13s">' . $hint . '</div>
                    <div class="fact"><span class="btnp">Save</span></div></div>
                  <div class="chips">
                    <span class="chip good a-pop" style="--d:7s">&#10003; ticked from day one</span>
                    <span class="chip a-pop" style="--d:16.5s">&#128101; staff only</span>
                    <span class="chip bad a-pop" style="--d:18.5s">never shown to customers</span>
                  </div>
                </div>

                <!-- 3 — its own Save -->
                <div class="sc" data-scene="3" data-len="' . $L(3) . '">
                  <div class="stack">
                    <div class="flash a-mid" style="--d:13.5s;--d2:18s">Joke of the day is on.</div>
                    <div class="flash a-fade" style="--d:18s">Joke of the day is off.</div>
                  </div>
                  <div class="secbox dim a-rise" style="--d:5.5s"><div class="sech">Company details</div>
                    <div class="fact"><span class="btns">Save company details</span> <span class="chip bad a-pop" style="--d:7s">&#10007; different form</span></div></div>
                  ' . $dashSec('<span class="stack"><span class="tick"></span><span class="tick on a-out" style="--d:17s">&#10003;</span></span>', 'a-press a-ring', '--d:3s',
                               '<div class="a-move" style="--fx:16rem;--fy:-4rem;--tx:1.2rem;--ty:.6rem;--d:.8s;--md:1.8s">' . $ptr . '</div>') . '
                  <div class="chips"><span class="chip good a-pop" style="--d:2.5s">This section&rsquo;s own Save</span></div>
                </div>

                <!-- 4 — what the team sees -->
                <div class="sc" data-scene="4" data-len="' . $L(4) . '">
                  <div class="dh1 a-fade" style="--d:.5s">Dashboard</div><div class="dh2 a-fade" style="--d:.8s">Sales at a glance &mdash; This month.</div>
                  <div class="a-drop" style="--d:3s">
                    <div class="jotd"><span class="jem a-pop" style="--d:5s">&#128516;</span><div class="jb"><div class="jl a-fade" style="--d:6s">Joke of the day</div>
                      <div class="jt"><span class="a-out" style="--d:16s">' . $joke . '</span><span class="a-fade" style="--d:16.3s">' . $joke2 . '</span></div></div>
                      <div class="ja"><span class="jbtn a-ring" style="--d:14.5s">&#128257; Another</span><span class="jbtn">&#10005;</span></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:10s">&#128101; Same joke for the whole team, all day</span>
                    <span class="chip a-pop" style="--d:17s">Another = a different one, just for a laugh</span></div>
                </div>

                <!-- 5 — hide for the day -->
                <div class="sc" data-scene="5" data-len="' . $L(5) . '">
                  <div class="stack">
                    <div class="a-out" style="--d:4s"><div class="jotd"><span class="jem">&#128516;</span><div class="jb"><div class="jl">Joke of the day</div><div class="jt">' . $joke . '</div></div>
                      <div class="ja"><span class="jbtn">&#128257; Another</span><span class="jbtn a-ring" style="--d:1s">&#10005;</span></div></div></div>
                    <div class="ghost a-fade" style="--d:4.3s">&#10005; Hidden until tomorrow &mdash; on this computer</div>
                  </div>
                  <div class="two" style="margin-top:.8rem">
                    <div class="dev a-rise" style="--d:6s"><span class="ico">&#128187;</span><b>This computer</b>hidden until tomorrow</div>
                    <div class="dev a-rise" style="--d:10s"><span class="ico">&#128241;</span><b>Tablet in the van</b><span class="chip a-pop" style="--d:12.5s;font-size:.62rem">&#128516; still showing</span></div>
                  </div>
                  <div class="a-rise" style="--d:15s;margin-top:.8rem">' . $dashSec('<span class="stack"><span class="tick"></span><span class="tick on a-out" style="--d:16.5s">&#10003;</span></span>', 'a-press', '--d:18s', '', '', 'max-width:24rem') . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:18.5s">Off for everyone</span></div>
                </div>

                <!-- 6 — the period -->
                <div class="sc" data-scene="6" data-len="' . $L(6) . '">
                  <div class="dh1">Dashboard</div><div class="dh2">Sales at a glance &mdash; <span class="swap"><span class="a-out" style="--d:15s">This month</span><span class="a-fade" style="--d:15s">1 Jul 2026 &ndash; 30 Sep 2026</span></span>.</div>
                  <div class="pbar">
                    <span class="a-pop" style="--d:5s"><span class="stack"><span class="pl on a-out" style="--d:14s">This month</span><span class="pl a-fade" style="--d:14s">This month</span></span></span><span class="pl a-pop" style="--d:5.8s">Last 30 days</span><span class="pl a-pop" style="--d:6.6s">This quarter</span>
                    <span class="pl a-pop" style="--d:7.3s">This year</span><span class="pl a-pop" style="--d:8s">All time</span>
                  </div>
                  <div class="pbar a-rise" style="--d:9.5s">
                    <span class="lbl">From</span><span class="dbox a-ring" style="--d:10.5s">01/07/2026</span>
                    <span class="lbl">To</span><span class="dbox a-ring" style="--d:11.5s">30/09/2026</span>
                    <span class="stack"><span class="btns">Apply</span><span class="pl on a-fade" style="--d:13.8s;padding:.28rem .65rem">Apply</span></span>
                  </div>
                  <div class="kpis">
                    <div class="kpi"><small>Revenue (won)</small><b><span class="a-out" style="--d:15.5s">&pound;8,420.00</span><span class="a-fade" style="--d:15.5s">&pound;24,960.00</span></b></div>
                    <div class="kpi"><small>Average order value</small><b><span class="a-out" style="--d:15.8s">&pound;701.67</span><span class="a-fade" style="--d:15.8s">&pound;693.33</span></b></div>
                    <div class="kpi"><small>Close rate</small><b><span class="a-out" style="--d:16.1s">63%</span><span class="a-fade" style="--d:16.1s">58%</span></b></div>
                    <div class="kpi"><small>Jobs in period</small><b><span class="a-out" style="--d:16.4s">12</span><span class="a-fade" style="--d:16.4s">36</span></b></div>
                  </div>
                  <span class="chip good a-pop" style="--d:17s">Every money figure follows the period</span>
                </div>

                <!-- 7 — View: one person -->
                <div class="sc" data-scene="7" data-len="' . $L(7) . '">
                  <div class="dh1">Dashboard</div><div class="dh2">Sales at a glance &mdash; This month<span class="a-fade" style="--d:8s"> for <b>Sarah Jones</b></span>.</div>
                  <div class="pbar"><span class="lbl">View:</span>
                    <span class="sel a-ring" style="--d:1.5s"><span class="a-out" style="--d:7s">All sales team</span><span class="a-fade" style="--d:7s">Sarah Jones</span></span></div>
                  <div class="split">
                    <div class="panel a-rise" style="--d:3s"><h4><span class="a-out" style="--d:8.5s">Sales team</span><span class="a-fade" style="--d:8.5s">Their numbers</span></h4>
                      <div class="uprow"><b>Sarah Jones</b> 9 won &middot; &pound;6,310</div>
                      <div class="uprow a-out" style="--d:8.5s"><b>Mark Patel</b> 3 won &middot; &pound;2,110</div></div>
                    <div class="side2">
                      <span class="chip a-pop" style="--d:13s">Only people who have raised a quote</span>
                      <span class="chip a-pop" style="--d:16s">New starter? Not listed until their first quote</span>
                    </div>
                  </div>
                </div>

                <!-- 8 — the panels -->
                <div class="sc" data-scene="8" data-len="' . $L(8) . '">
                  <div class="panel a-rise" style="--d:1.5s"><h4>Upcoming jobs</h4>
                    <div class="uprow"><b>Today 10:00</b> Mrs Patel &middot; LA9 4QT <span class="pill">Booked</span></div>
                    <div class="uprow"><b>Tomorrow 14:30</b> J Reed &middot; LA8 9AB <span class="pill">Booked</span></div></div>
                  <div class="kpis">
                    <div class="kpi a-pop" style="--d:7s"><small>Revenue (won)</small><b>&pound;8,420.00</b><i>12 jobs accepted</i></div>
                    <div class="kpi a-pop" style="--d:8.3s"><small>Average order value</small><b>&pound;701.67</b><i>across won quotes</i></div>
                    <div class="kpi a-pop" style="--d:9.6s"><small>Close rate</small><b>63%</b><i>12 of 19 decided</i></div>
                    <div class="kpi a-pop" style="--d:10.8s"><small>Jobs in period</small><b>12</b><i>accepted &amp; beyond</i></div>
                  </div>
                  <div class="panels">
                    <div class="panel a-fly" style="--d:12.5s"><h4>Sales team</h4>Person &middot; Pipeline &middot; Won &middot; Revenue</div>
                    <div class="panel a-fly" style="--d:14s"><h4>What&rsquo;s selling</h4>Top products by revenue</div>
                    <div class="panel a-fly" style="--d:15.5s"><h4>Gross profit</h4>Total profit &middot; Margin %</div>
                    <div class="panel a-fly" style="--d:17s"><h4>Recent wins</h4>The latest jobs accepted</div>
                  </div>
                  <div class="chips"><span class="chip good a-pop" style="--d:19.5s">Money panels follow the period &amp; person you picked</span></div>
                </div>

                <!-- 9 — who sees what -->
                <div class="sc" data-scene="9" data-len="' . $L(9) . '">
                  <div class="crumbs"><span class="chip a-pop" style="--d:2.5s">Users</span><span class="arrow a-fade" style="--d:3.2s">&rarr;</span><span class="chip a-pop" style="--d:4s">Edit a person</span></div>
                  <div class="secbox a-rise" style="--d:4.5s;max-width:31rem">
                    <div class="lbl" style="margin-bottom:.25rem">Permissions</div>
                    <div class="cks">' . $cb('Create quotes', '<span class="tick on">&#10003;</span>') . $cb('Create orders') . $cb('View all customer jobs')
                      . $cb('View costs', '<span class="tick a-sel" style="--d:16.5s">&#10003;</span>') . $cb('Can see money', '<span class="a-ring" style="--d:12.5s;border-radius:5px;display:inline-flex"><span class="tick a-sel" style="--d:12.5s">&#10003;</span></span>') . $cb('Fittings only') . '</div>
                    <div class="fs"><div class="lg">Dashboard</div>
                      <div class="cks">' . $cb('Sales-team leaderboard', '<span class="tick a-sel" style="--d:8s">&#10003;</span>') . $cb('Product mix', '<span class="tick a-sel" style="--d:8.6s">&#10003;</span>')
                        . $cb('Gross profit', '<span class="tick a-sel" style="--d:15.5s">&#10003;</span>') . $cb('Recent wins', '<span class="tick a-sel" style="--d:9.2s">&#10003;</span>') . '</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip good a-pop" style="--d:19s">Admins always see everything</span>
                    <span class="chip a-pop" style="--d:21s">Can&rsquo;t see money &rarr; straight to the Calendar</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where the switch is.</b> <b>Setup &rarr; Settings</b>, on the <b>Company</b> tab (the first of seven: Company,
             Quoting, Legal, Status colours, Suppliers, Accounting, Back up data). Scroll past <b>Company details</b> and
             <b>Company logo</b> and the third section is <b>Dashboard</b>, just above <b>Calendar</b>.</p>
          <ul class="steps">
            <li><b>&#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard</b> &mdash; one tick box, <b>already ticked</b>
                from the day your account is set up. The note under it reads: &ldquo;A little light relief for your team on the
                dashboard (staff only &mdash; never shown to customers). Dismissible per day. Untick to switch it off.&rdquo;
                (With <b>Compact mode</b> on, that note is hidden.)</li>
            <li><b>Save</b> &mdash; this section has its <b>own</b> Save button, labelled just <b>Save</b>. The <b>Save company
                details</b> button higher up belongs to a different form and won&rsquo;t save this. Press the right one and a
                green bar says <b>Joke of the day is on.</b> &mdash; or <b>Joke of the day is off.</b> if you unticked it.</li>
          </ul>
          <p><b>What it looks like.</b> Near the top of the dashboard, a soft yellow card: the &#128516; face, the words
             <b>JOKE OF THE DAY</b>, the joke, and two buttons on the right. <b>&#128257; Another</b> shuffles up a different
             one for a laugh &mdash; it isn&rsquo;t saved, so a refresh brings the day&rsquo;s joke back. <b>&#10005;</b> hides it
             until tomorrow. Everybody in your team gets the same joke all day, and no customer ever sees it.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The cross is per browser, not per person.</b> It hides the
             card on the machine you&rsquo;re using. It&rsquo;ll be back on the tablet in the van, in a different browser, or
             after the browser&rsquo;s site data is cleared. To switch it off for everyone, untick the box in Settings and
             press <b>Save</b>.</div></div>
          <div class="oops"><b>Could not save: &hellip; &mdash; have you run migrate_joke_toggle.php?</b> If you ever get that
             red bar, nothing is broken &mdash; the database column hasn&rsquo;t been added to your account yet. Tell us and
             we&rsquo;ll sort it.</div>

          <p><b>The rest of the dashboard</b> you steer from the dashboard itself.</p>
          <ul class="steps">
            <li><b>The period.</b> Along the top: <b>This month</b> (where it opens), <b>Last 30 days</b>, <b>This quarter</b>,
                <b>This year</b>, <b>All time</b> &mdash; plus <b>From</b> and <b>To</b> date pickers (neither goes past today)
                and <b>Apply</b> for your own range. Every money panel follows it; <b>Upcoming jobs</b> always looks ahead from now.</li>
            <li><b>View:</b> narrows the whole page to one salesperson. It starts on <b>All sales team</b> and changes as soon
                as you pick a name. It only lists people who have actually raised a quote, so a brand-new starter isn&rsquo;t
                there yet &mdash; that&rsquo;s not a fault.</li>
            <li><b>Upcoming jobs</b> &mdash; your next eight bookings from now on, soonest first, with <b>Open calendar
                &rarr;</b> at the foot. Today&rsquo;s jobs that have started but haven&rsquo;t been closed off are listed
                separately as <b>Earlier today, not closed</b>.</li>
            <li><b>Revenue (won)</b>, <b>Average order value</b>, <b>Close rate</b> (&ldquo;12 of 19 decided&rdquo;) and
                <b>Jobs in period</b> (&ldquo;accepted &amp; beyond&rdquo;).</li>
            <li><b>Sales team</b> &mdash; each person&rsquo;s pipeline, decided, won, close rate and revenue, with a
                <b>Revenue share</b> donut. Filter to one person and the heading becomes <b>Their numbers</b>.</li>
            <li><b>What&rsquo;s selling</b>, <b>Gross profit</b> and <b>Recent wins</b> (the latest jobs accepted in the
                period).</li>
          </ul>

          <p><b>&ldquo;Why can&rsquo;t my fitter see the dashboard?&rdquo;</b> Because who sees what isn&rsquo;t set here. Go to
             <b>Users</b> and edit the person:</p>
          <ul class="steps">
            <li>Under <b>Permissions</b>, <b>Can see money</b> is the key. Every dashboard panel shows money, so without it a
                person has no Dashboard link and lands on the <b>Calendar</b> instead &mdash; usually right for a fitter.
                <b>Can see money</b> on its own already shows the Revenue panel.</li>
            <li>The <b>Dashboard</b> box below has four tick boxes &mdash; <b>Sales-team leaderboard</b>, <b>Product mix</b>,
                <b>Gross profit</b>, <b>Recent wins</b> &mdash; for the other panels. <b>Gross profit</b> also needs
                <b>View costs</b> ticked under Permissions.</li>
            <li><b>Admins</b> always see everything; these boxes only apply to everyone else.</li>
          </ul>
          <p>Nothing in this guide touches a quote, a price or a customer &mdash; the dashboard is your own team&rsquo;s screen.</p>',
        'script'  => $script,
];
