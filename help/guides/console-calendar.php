<?php
declare(strict_types=1);

/**
 * Guide: console-calendar — "The office calendar" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Audience 'factory': Factory Console users and the super-admin only.
 *
 * Mirrors:
 *   - _partials/office_calendar.php — one shared, internal list; the kinds
 *     (Note / Reminder / Callback) and their chip colours, the form fields
 *     (What, Date, Time, Account, Order number, Notes) and messages, "due" =
 *     open reminders/callbacks dated today or earlier + remakes due that
 *     haven't gone out, an account's open callbacks;
 *   - factory/calendar.php — Due now, + Add to the calendar, the week with
 *     ‹ Previous / This week / Next ›, the + per day, ticking, remakes due;
 *   - factory/calendar-entry.php — Save, ✓ Mark done / Mark not done, Delete;
 *   - _partials/sidebar.php — "Calendar (n due)"; factory/dashboard.php — the
 *     Calendar tile;
 *   - master-admin/account.php — "Callbacks & reminders" + "+ Add callback".
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line; start times come from where a phrase falls in
 * the voice-over ($at, ~13.6 characters a second), data-len from its length.
 */

$S = [
    ['1', 'One shared office calendar', 'The Calendar, in the Work menu, is the office\'s own calendar. It is one shared list for the whole office, not one each, so anyone can see what is coming up, and pick it up. It is strictly internal. Trade accounts never see it.', 1],
    ['2', 'Notes, reminders and callbacks', 'There are three kinds of entry. A Note is for a day only, like the van going in for its service. A Reminder is a job to do, and it stays on the list until someone ticks it. A Callback is a reminder to ring a trade account back. It is tied to that account, and it shows on their page too.', 2],
    ['3', 'Adding an entry', 'To add something, click Add to the calendar. Choose Note, Reminder or Callback. Under What, say what it is, in a few words. For example, order hangers. Pick the date, and a time if it matters. Then click Add, and it goes onto the calendar, in that day\'s box.', 3],
    ['4', 'Accounts and orders', 'You can tie an entry to an account, and to an order number. For a callback, the account is needed, so everyone knows who to ring. If you type an order number, and the calendar finds that order, it becomes a link straight to it. If not, the number is simply kept as text. There is a Notes box too, for anything else.', 4],
    ['5', 'Due now', 'At the top is Due now. It lists every reminder and callback dated today, and anything from earlier days that has not been ticked yet. Older ones are shown in red, with the day they were from. Nothing is lost. A job simply carries forward, day after day, until someone ticks it done.', 5],
    ['6', 'Ticking it done', 'When a job is done, tick its box. You can tick it in Due now, or in the week below. A ticked entry is crossed through, and it drops off Due now. Ticked one by mistake? Tick it again to undo it. Notes have no box, because there is nothing to finish.', 6],
    ['7', 'The week view', 'Below that is the week, Monday to Sunday, with today outlined. Use Previous, This week and Next to move about. Each day has a little plus in its corner. Click it to add something on that day. The form opens with the date already filled in, ready for a reminder.', 7],
    ['8', 'Remakes appear by themselves', 'Remakes with a due date appear on the calendar by themselves, in purple, on the day they are due. You do not add them, and you do not tick them. They come from the remake itself, and they go once the remake has been dispatched. Until then, an overdue remake also shows under Due now.', 8],
    ['9', 'Changing or deleting an entry', 'To change an entry, click its title. You can change any part of it, and click Save. A reminder or a callback also has a Mark done button, or Mark not done once it is ticked. To remove it altogether, click Delete, and confirm. That takes it off the calendar for everyone.', 9],
    ['10', 'The menu and the Dashboard', 'You do not have to open the calendar to know what is waiting. The menu says Calendar, and in brackets, how many are due. The Calendar tile on the Dashboard shows the same number, in red. Both count reminders and callbacks due now, and remakes due, including anything carried forward.', 10],
    ['11', 'Callbacks on an account\'s page', 'Each trade account\'s page has a Callbacks and reminders box. It lists every open callback and reminder for that account, whatever its date, and marks any that are overdue. Click Add callback, and the calendar opens with a callback already set up for that account.', 11],
];
$len = static fn (int $n): string => (string) round(mb_strlen($S[$n - 1][2]) / 13.6, 1);
$at = static function (int $n, string $phrase, float $add = 0.0) use ($S): string {
    $p = mb_strpos($S[$n - 1][2], $phrase);
    if ($p === false) { error_log('console-calendar guide: "' . $phrase . '" not in line ' . $n); $p = 0; }
    return (string) round($p / 13.6 + $add, 1);
};
$d = static fn (int $n, string $phrase, float $add = 0.0): string => '--d:' . $at($n, $phrase, $add) . 's';

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The kind chips, coloured as oc_kinds() / oc_chip() colour them.
$KIND = ['note' => ['Note', '#475569', '#e2e8f0'], 'reminder' => ['Reminder', '#92400e', '#fef3c7'],
         'callback' => ['Callback', '#1e40af', '#dbeafe'], 'remake' => ['Remake', '#ffffff', '#7c3aed']];
$chip = static fn (string $k, string $cls = '', string $style = ''): string =>
    '<span class="kc ' . $cls . '" style="color:' . $KIND[$k][1] . ';background:' . $KIND[$k][2] . ';' . $style . '">' . $KIND[$k][0] . '</span>';
$box = static fn (string $cls = '', string $style = '', bool $on = false): string =>
    '<span class="bx ' . ($on ? 'on ' : '') . $cls . '" style="' . $style . '">' . ($on ? '&#10003;' : '') . '</span>';
$ph = static fn (string $t): string => '<span class="gph">' . $t . '</span>';

/** A labelled control. */
$f = static fn (string $label, string $inner, string $kind = '', string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib ' . $kind . '">' . $inner . '</span></div>';
$opt = ' <small>(optional)</small>';

/** The radio row of kinds; $sel = key, or '' with $anim = [key => style] to move it. */
$kinds = static function (string $sel = 'reminder', array $anim = []) : string {
    $h = '<div class="kr">';
    foreach (['note' => 'Note', 'reminder' => 'Reminder', 'callback' => 'Callback'] as $k => $l) {
        $dot = isset($anim[$k]) ? (is_string($anim[$k]) ? $anim[$k] : '<b class="dot ' . $anim[$k][0] . '" style="' . $anim[$k][1] . '"></b>') : ($k === $sel ? '<b class="dot"></b>' : '');
        $h .= '<span><i>' . $dot . '</i>' . $l . '</span>';
    }
    return $h . '</div>';
};

/** One Due-now item. */
$due = static fn (string $kind, string $title, string $meta, string $cls = '', string $style = '', string $boxHtml = ''): string =>
    '<div class="dn ' . $cls . '" style="' . $style . '">' . ($boxHtml !== '' ? $boxHtml : ($kind === 'remake' ? '<span class="bxs"></span>' : $box()))
    . '<div><div class="t">' . $chip($kind) . ' <span class="tl">' . $title . '</span></div><div class="m">' . $meta . '</div></div></div>';

// The week (Mon 5 – Sun 11 Oct 2026; today = Fri 9 Oct).
$DAYS = ['Mon 5 Oct', 'Tue 6 Oct', 'Wed 7 Oct', 'Thu 8 Oct', 'Fri 9 Oct', 'Sat 10 Oct', 'Sun 11 Oct'];
/** $cells = [dayIndex => html]; $plus = [dayIndex => [cls, style]] */
$week = static function (array $cells = [], array $plus = []) use ($DAYS): string {
    $h = '<div class="wk">';
    foreach ($DAYS as $i => $dn) {
        [$pc, $ps] = $plus[$i] ?? ['', ''];
        $h .= '<div class="dy' . ($i === 4 ? ' today' : '') . '"><div class="dd"><span>' . $dn . '</span><b class="' . $pc . '" style="' . $ps . '">+</b></div>' . ($cells[$i] ?? '') . '</div>';
    }
    return $h . '</div>';
};
/** A week entry. */
$we = static fn (string $kind, string $title, string $acct = '', string $cls = '', string $style = '', string $boxHtml = ''): string =>
    '<div class="we ' . $kind . ' ' . $cls . '" style="' . $style . '">' . ($kind === 'note' ? '' : ($boxHtml !== '' ? $boxHtml : $box()))
    . '<div class="x">' . $chip($kind) . ' <span class="tl">' . $title . '</span>' . ($acct !== '' ? '<span class="a">' . $acct . '</span>' : '') . '</div></div>';
$wrm = static fn (string $num, string $acct, string $cls = '', string $style = ''): string =>
    '<div class="we remake ' . $cls . '" style="' . $style . '"><div class="x"><span class="tl"><b>Remake due</b> ' . $num . '</span><span class="a">' . $acct . '</span></div></div>';

$weekNav = '<div class="wn"><b>Week of 5 October 2026</b><span class="btns sm">&lsaquo; Previous</span><span class="btns sm">This week</span><span class="btns sm">Next &rsaquo;</span></div>';
$calHead = '<div class="pt">Calendar</div><div class="phint">The office&rsquo;s shared calendar: notes, reminders and callbacks. Anything not ticked carries forward until it&rsquo;s done. Remake due dates appear here by themselves. Only the office sees this.</div>';
$dueH = '<h5>Due now <small>(today and anything carried forward)</small></h5>';

return [
        'aud'     => 'factory',
        'section' => 'Factory Console',
        'title'   => 'The office calendar',
        'eyebrow' => 'Factory Console',
        'v'       => 2,
        'blurb'   => 'The office\'s shared, internal calendar: notes, reminders and callbacks, Due now (carried forward until ticked), the week view, remake due dates, editing, the menu count, and callbacks on an account\'s page.',
        'lede'    => 'The <b>Calendar</b> in the console is the factory office&rsquo;s <b>own shared list</b> &mdash; one for the whole office,
                      not one each, and <b>strictly internal</b>: trade accounts never see it. It holds <b>notes</b> for a day,
                      <b>reminders</b> that stay until someone ticks them, and <b>callbacks</b> to ring an account back, and it shows
                      <b>remake due dates</b> by itself. This guide goes through it <b>slowly</b>, one idea per chapter. To get there:
                      <b>Work &rarr; Calendar</b>.',
        'open'    => '/factory/calendar.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:400px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .phint{ font-size:.64rem; color:var(--faint); margin:.15rem 0 .55rem; max-width:34rem; line-height:1.45; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.32rem .75rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .btns.sm, .gd .btnp.sm{ padding:.18rem .5rem; font-size:.62rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .lnk{ color:var(--accent); font-weight:700; text-decoration:underline; border-radius:4px; }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.4rem .6rem; font-size:.7rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; }
          .gd .ebn{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.4rem .6rem; font-size:.7rem; font-weight:700; color:var(--err); margin:0 0 .5rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }
          .gd .three{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.6rem; align-items:start; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); display:flex; gap:.4rem; align-items:center; }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .stk{ display:grid; } .gd .stk > *{ grid-area:1/1; align-self:start; }

          /* kind chips + tick boxes */
          .gd .kc{ display:inline-block; font-size:.54rem; font-weight:700; border-radius:999px; padding:0 .4rem; white-space:nowrap; vertical-align:1px; }
          .gd .bx{ width:15px; height:15px; border:2px solid var(--border-strong,#9aa3af); border-radius:5px; background:var(--surface); flex:none;
                   display:grid; place-items:center; color:#fff; font-size:.56rem; font-weight:900; position:relative; }
          .gd .bx.on{ background:#16a34a; border-color:#16a34a; }
          .gd .bx .ck{ position:absolute; inset:-2px; border-radius:5px; background:#16a34a; color:#fff; display:grid; place-items:center; }
          .gd .bxs{ width:15px; flex:none; }

          /* due now */
          .gd .pnl{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); min-width:0; }
          .gd .pnl h5{ margin:0 0 .4rem; font-size:.76rem; color:var(--ink); }
          .gd .pnl h5 small{ font-weight:400; color:var(--faint); font-size:.6rem; }
          .gd .dn{ display:grid; grid-template-columns:auto 1fr; gap:.45rem; align-items:start; margin-bottom:.35rem; font-size:.66rem; }
          .gd .dn .t .tl{ font-weight:700; color:var(--ink); }
          .gd .dn .m{ color:var(--faint); font-size:.58rem; }
          .gd .late{ color:#b91c1c; font-weight:700; }

          /* week */
          .gd .wn{ display:flex; gap:.35rem; align-items:center; flex-wrap:wrap; margin:.2rem 0 .45rem; font-size:.7rem; }
          .gd .wn b{ margin-right:.3rem; color:var(--ink); }
          .gd .wk{ display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:.3rem; }
          .gd .dy{ border:1px solid var(--line); border-radius:8px; padding:.3rem; min-height:5.6rem; background:var(--surface); display:flex; flex-direction:column; gap:.25rem; min-width:0; }
          .gd .dy.today{ border-color:var(--accent); box-shadow:0 0 0 1px var(--accent); }
          .gd .dd{ display:flex; justify-content:space-between; font-size:.5rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; }
          .gd .dd b{ color:var(--accent); font-size:.8rem; line-height:.8; border-radius:4px; padding:0 .1rem; }
          .gd .we{ display:grid; grid-template-columns:auto minmax(0,1fr); gap:.2rem; align-items:start; background:var(--panel); border-radius:6px; padding:.2rem; font-size:.54rem; color:var(--ink); overflow-wrap:anywhere; }
          .gd .we.note, .gd .we.remake{ grid-template-columns:minmax(0,1fr); }
          .gd .we.remake{ background:rgba(124,58,237,.12); }
          .gd .we .bx{ width:12px; height:12px; border-width:1.5px; border-radius:4px; font-size:.5rem; }
          .gd .we .a{ display:block; color:var(--faint); }
          .gd .we .kc{ font-size:.46rem; }
          .gd .gd-play .strike{ animation:ocStrike .4s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .strike{ text-decoration:line-through; color:var(--faint); }
          @keyframes ocStrike{ to{ text-decoration:line-through; color:var(--faint); } }

          /* form */
          .gd .frm{ display:flex; flex-direction:column; gap:.4rem; max-width:24rem; }
          .gd .g2{ display:grid; grid-template-columns:1fr 1fr; gap:.45rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.18rem; min-width:0; position:relative; }
          .gd .fl{ font-size:.62rem; font-weight:700; color:var(--soft); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .fl small{ font-weight:400; color:var(--faint); }
          .gd .ib{ display:flex; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.68rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.sel{ padding-right:1.2rem; } .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.35rem; color:var(--faint); font-size:.64rem; }
          .gd .ib.ta{ min-height:38px; align-items:flex-start; padding-top:.3rem; }
          .gd .gph{ color:var(--faint); }
          .gd .kr{ display:flex; gap:.8rem; flex-wrap:wrap; font-size:.68rem; font-weight:600; color:var(--ink); }
          .gd .kr span{ display:flex; gap:.3rem; align-items:center; }
          .gd .kr i{ width:13px; height:13px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:50%; position:relative; background:var(--surface); }
          .gd .kr .dot{ position:absolute; inset:2px; border-radius:50%; background:var(--accent); }
          .gd .addbtn{ align-self:flex-start; }

          /* menu + tile + account page */
          .gd .cmnu{ background:#1f2937; border-radius:10px; padding:.55rem .55rem; color:#cbd5e1; width:12rem; }
          .gd .cmnu .nh{ font-size:.52rem; letter-spacing:.12em; text-transform:uppercase; color:#8aa0b2; font-weight:700; margin:.1rem 0 .05rem .25rem; }
          .gd .cmnu .it{ display:block; font-size:.68rem; padding:.12rem .35rem; border-radius:6px; color:#e2e8f0; }
          .gd .ftile{ background:var(--surface); border:1px solid var(--line); border-top:3px solid #b91c1c; border-radius:10px; padding:.5rem .6rem; width:12rem; }
          .gd .ftile .fl2{ font-size:.54rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .ftile .fv{ font-size:1.4rem; font-weight:800; color:var(--ink); line-height:1.15; }
          .gd .ftile .fs{ font-size:.58rem; color:var(--faint); }
          .gd .acs h4{ display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap; margin:0 0 .4rem; font-size:.82rem; color:var(--ink); }
          .gd .acs ul{ margin:0; padding-left:1rem; font-size:.66rem; color:var(--ink); display:flex; flex-direction:column; gap:.25rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:600px; }
            .gd .two, .gd .three{ grid-template-columns:1fr; }
            .gd .wk{ grid-template-columns:1fr; } .gd .dy{ min-height:0; }
            .gd .wk.keep{ grid-template-columns:repeat(7,minmax(0,1fr)); } .gd .wk.keep .dy{ min-height:3.5rem; }
            .gd .g2{ grid-template-columns:1fr; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / factory / calendar</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>FACTORY CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Orders</a><a>Remakes</a><a class="on">Calendar (3 due)</a>
                <div class="navh">Production</div>
                <a>Incoming orders</a><a>Floor</a><a>Dispatch</a>
                <div class="navh">Accounts</div>
                <a>Invoices</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $calHead . '
                  <div class="two">
                    <div class="pnl">' . $dueH
                      . $due('reminder', 'Order hangers', '<span class="late">from Wed 7 Oct</span>')
                      . $due('callback', '14:00 Ring about the fabric issue', 'Today &middot; ABC Blinds &middot; <span class="lnk">ABC-2026-0041</span>')
                      . $due('remake', '<span class="lnk">Remake ABC-2026-0038-R1 due</span>', 'Due today &middot; ABC Blinds &middot; Fabric fault') . '</div>
                    <div class="pnl"><span class="btnp">+ Add to the calendar</span></div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; eleven short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — one shared calendar -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">One shared office calendar</div>
                  <div class="a-rise" style="' . $d(1, 'The Calendar') . '">' . $calHead . '</div>
                  <div class="three" style="margin-top:.4rem">
                    <div class="card a-rise" style="' . $d(1, 'one shared list') . '"><h4>The whole office</h4>One list, not one each</div>
                    <div class="card a-rise" style="' . $d(1, 'so anyone can') . '"><h4>Anyone</h4>sees what is coming up &mdash; and picks it up</div>
                    <div class="card a-rise" style="' . $d(1, 'strictly internal') . ';border-color:var(--err)"><h4>Trade accounts</h4><span class="chip bad a-pop" style="' . $d(1, 'never see it') . '">&#10007; never see it</span></div>
                  </div>
                </div>

                <!-- 2 — kinds -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Notes, reminders and callbacks</div>
                  <div class="three" style="margin-top:.5rem">
                    <div class="card a-rise" style="' . $d(2, 'A Note') . '"><h4>' . $chip('note') . '</h4>For a day only.
                      <div class="we note" style="margin-top:.4rem"><div class="x">' . $chip('note') . ' <span class="tl">Van in for its service</span></div></div></div>
                    <div class="card a-rise" style="' . $d(2, 'A Reminder') . '"><h4>' . $chip('reminder') . '</h4>A job to do &mdash; stays until someone ticks it.
                      <div class="we reminder" style="margin-top:.4rem">' . $box() . '<div class="x">' . $chip('reminder') . ' <span class="tl">Order hangers</span></div></div></div>
                    <div class="card a-rise" style="' . $d(2, 'A Callback') . '"><h4>' . $chip('callback') . '</h4>Ring a trade account back &mdash; tied to that account.
                      <div class="we callback" style="margin-top:.4rem">' . $box() . '<div class="x">' . $chip('callback') . ' <span class="tl">Ring about the fabric issue</span><span class="a">ABC Blinds</span></div></div>
                      <span class="chip a-pop" style="' . $d(2, 'shows on their page') . ';margin-top:.4rem">also on their page</span></div>
                  </div>
                </div>

                <!-- 3 — adding -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">Adding an entry</div>
                  <span class="btnp a-press a-ring" style="' . $d(3, 'click Add to') . ';margin:.3rem 0 .6rem">+ Add to the calendar</span>
                  <div class="frm a-rise" style="' . $d(3, 'click Add to', 1) . '">
                    ' . $kinds('', ['reminder' => '<b class="dot a-out" style="' . $d(3, 'Choose Note') . '"></b><b class="dot a-fade" style="' . $d(3, 'Under What') . '"></b>', 'note' => ['a-mid', $d(3, 'Choose Note') . ';--d2:' . $at(3, 'Reminder or')], 'callback' => ['a-mid', $d(3, 'Callback.') . ';--d2:' . $at(3, 'Under What')]]) . '
                    ' . $f('What', '<span class="swap"><span class="gph a-out" style="' . $d(3, 'order hangers') . '">e.g. Order hangers &middot; Saw blade on number two saw &middot; Ring about the fabric issue</span><span class="a-fade" style="' . $d(3, 'order hangers') . '"><span class="a-type" style="' . $d(3, 'order hangers', .2) . ';--ts:13;--tt:.9s">Order hangers</span></span></span>', '', 'a-ring', $d(3, 'Under What')) . '
                    <div class="g2">' . $f('Date', '<span class="swap"><span class="a-out" style="' . $d(3, 'Pick the date', .8) . '">09/10/2026</span><span class="a-fade" style="' . $d(3, 'Pick the date', .8) . '">12/10/2026</span></span>', '', 'a-ring', $d(3, 'Pick the date'))
                      . $f('Time' . $opt, '<span class="swap"><span class="gph a-out" style="' . $d(3, 'a time if') . '">--:--</span><span class="a-fade" style="' . $d(3, 'a time if') . '">09:30</span></span>') . '</div>
                    <span class="btnp a-press addbtn" style="' . $d(3, 'Then click Add') . '">Add</span>
                  </div>
                  <div class="bnr a-pop" style="' . $d(3, 'it goes onto') . ';margin-top:.6rem;max-width:24rem">Added to the calendar.</div>
                </div>

                <!-- 4 — accounts and orders -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">Accounts and orders</div>
                  <div class="frm" style="margin-top:.4rem;max-width:30rem">
                    ' . $kinds('callback') . '
                    <div class="g2">
                      ' . $f('Account <small>(needed for a callback)</small>', '<span class="swap"><span class="a-out" style="' . $d(4, 'so everyone knows') . '">&mdash;</span><span class="a-fade" style="' . $d(4, 'so everyone knows') . '">ABC Blinds</span></span>', 'sel', 'a-ring', $d(4, 'tie an entry')) . '
                      ' . $f('Order number' . $opt, '<span class="swap"><span class="gph a-out" style="' . $d(4, 'type an order') . '">e.g. ABC-2026-0004</span><span class="a-fade" style="' . $d(4, 'type an order') . '"><span class="a-type" style="' . $d(4, 'type an order', .2) . ';--ts:13;--tt:.9s">ABC-2026-0041</span></span></span>', '', 'a-ring', $d(4, 'and to an order')) . '
                    </div>
                    ' . $f('Notes' . $opt, '<span class="a-type" style="' . $d(4, 'Notes box') . ';--ts:30;--tt:1.4s">Wants to know about the delay</span>', 'ta', 'a-ring', $d(4, 'Notes box')) . '
                  </div>
                  <div class="ebn a-mid" style="' . $d(4, 'For a callback') . ';--d2:' . $at(4, 'so everyone knows') . 's;margin-top:.6rem;max-width:30rem">Choose which account to call back.</div>
                  <div class="two" style="max-width:30rem">
                    <div class="card a-rise" style="' . $d(4, 'it becomes a link') . '">Order: <span class="lnk">ABC-2026-0041</span></div>
                    <div class="card a-rise" style="' . $d(4, 'If not') . '">No order found with that number for this account &mdash; it&rsquo;s kept as text.</div>
                  </div>
                </div>

                <!-- 5 — due now -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">Due now</div>
                  <div class="pnl a-rise" style="' . $d(5, 'At the top') . ';max-width:30rem">' . $dueH
                    . $due('callback', '14:00 Ring about the fabric issue', 'Today &middot; ABC Blinds &middot; <span class="lnk">ABC-2026-0041</span>', 'a-fly', $d(5, 'dated today'))
                    . $due('reminder', 'Saw blade on number two saw', 'Today', 'a-fly', $d(5, 'dated today', .5))
                    . $due('reminder', 'Order hangers', '<span class="late a-ring" style="' . $d(5, 'shown in red') . '">from Wed 7 Oct</span>', 'a-fly', $d(5, 'anything from earlier'))
                    . $due('callback', 'Chase signed proof', '<span class="late">from Mon 5 Oct</span> &middot; Coastal Shutters', 'a-fly', $d(5, 'anything from earlier', .5)) . '</div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="' . $d(5, 'Nothing is lost') . '">Nothing is lost</span>
                    <span class="chip a-pop" style="' . $d(5, 'carries forward') . '">Carries forward &mdash; day after day &mdash; until ticked</span>
                  </div>
                  <span class="chip a-pop" style="' . $d(5, 'At the top', 1.5) . ';margin-top:.5rem">Nothing due? <b>Nothing due. Nice.</b></span>
                </div>

                <!-- 6 — ticking -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">Ticking it done</div>
                  <div class="two" style="margin-top:.4rem">
                    <div class="pnl">' . $dueH
                      . $due('callback', '14:00 Ring about the fabric issue', 'Today &middot; ABC Blinds', '', '')
                      . $due('reminder', 'Order hangers', '<span class="late">from Wed 7 Oct</span>', 'a-out', $d(6, 'drops off', .3),
                             '<span class="bx a-ring" style="' . $d(6, 'tick its box') . '"><span class="ck a-pop" style="' . $d(6, 'tick its box', .6) . '">&#10003;</span></span>') . '</div>
                    <div>
                      <div class="lg" style="font-size:.62rem;font-weight:700;color:var(--soft);margin-bottom:.3rem">In the week</div>
                      ' . $we('reminder', '<span class="strike" style="' . $d(6, 'crossed through') . '">Order hangers</span>', '', '', '',
                              '<span class="bx"><span class="ck a-pop" style="' . $d(6, 'or in the week') . '">&#10003;</span></span>') . '
                      <div style="height:.4rem"></div>
                      ' . $we('note', 'Van in for its service') . '
                      <span class="chip a-pop" style="' . $d(6, 'Notes have no box') . ';margin-top:.4rem">Notes: no box</span>
                    </div>
                  </div>
                  <span class="chip a-pop" style="' . $d(6, 'Tick it again') . ';margin-top:.7rem">Ticked by mistake? Tick it again to undo</span>
                </div>

                <!-- 7 — the week -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">The week view</div>
                  <div class="wn"><b>Week of 5 October 2026</b>
                    <span class="btns sm a-ring" style="' . $d(7, 'Use Previous') . '">&lsaquo; Previous</span>
                    <span class="btns sm a-ring" style="' . $d(7, 'This week and') . '">This week</span>
                    <span class="btns sm a-ring" style="' . $d(7, 'and Next') . '">Next &rsaquo;</span></div>
                  <div class="a-rise" style="' . $d(7, 'Below that') . '">' . $week([
                        0 => $we('note', 'Van in for its service'),
                        2 => $we('reminder', 'Order hangers'),
                        4 => $we('callback', '14:00 Ring about the fabric issue', 'ABC Blinds &middot; ABC-2026-0041'),
                    ], [6 => ['a-ring', $d(7, 'Each day has')], 5 => ['a-ring', $d(7, 'Click it to add')]]) . '</div>
                  <div class="frm a-rise" style="' . $d(7, 'The form opens') . ';margin-top:.6rem">
                    ' . $kinds('reminder') . '
                    <div class="g2">' . $f('Date', '<b>10/10/2026</b>', '', 'a-ring', $d(7, 'date already')) . $f('Time' . $opt, $ph('--:--')) . '</div>
                  </div>
                  <div class="a-move" style="--fx:50%;--fy:95%;--tx:82%;--ty:3.7rem;' . $d(7, 'Click it to add', -1.2) . ';--md:1.2s">' . $ptr . '</div>
                </div>

                <!-- 8 — remakes -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">Remakes appear by themselves</div>
                  ' . $weekNav . '
                  ' . $week([
                        2 => $we('reminder', 'Order hangers'),
                        4 => $wrm('ABC-2026-0038-R1', 'ABC Blinds', 'a-pop', $d(8, 'in purple')),
                        6 => $wrm('CST-2026-0004-R1', 'Coastal Shutters', 'a-pop', $d(8, 'on the day they')),
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(8, 'You do not add') . '">No box to tick &mdash; it comes from the remake</span>
                    <span class="chip ok a-pop" style="' . $d(8, 'they go once') . '">Gone once it&rsquo;s dispatched</span>
                  </div>
                  <div class="pnl a-rise" style="' . $d(8, 'Until then') . ';margin-top:.6rem;max-width:30rem">' . $dueH
                    . $due('remake', '<span class="lnk">Remake HUG-2026-0006-R1 due</span>', '<span class="late">due Tue 6 Oct</span> &middot; Hughes Interiors &middot; Measuring error') . '</div>
                </div>

                <!-- 9 — edit / delete -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">Changing or deleting an entry</div>
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin:.3rem 0 .4rem"><div class="pt">Reminder</div><span class="btns sm">Back to the calendar</span></div>
                  <div style="display:flex;gap:.5rem;align-items:center;font-size:.68rem;color:var(--soft);margin-bottom:.5rem">
                    <span class="stk"><span class="a-out" style="' . $d(9, 'Mark done button', .8) . '">Not done yet</span><span class="a-fade" style="' . $d(9, 'Mark done button', .8) . '">&#10003; Done 9 Oct 2026, 10:42</span></span>
                    <span class="stk"><span class="btnp sm a-out" style="' . $d(9, 'Mark done button', .8) . '"><span class="a-press" style="' . $d(9, 'Mark done button') . '">&#10003; Mark done</span></span><span class="btns sm a-fade" style="' . $d(9, 'or Mark not done') . '">Mark not done</span></span>
                  </div>
                  <div class="frm a-rise" style="' . $d(9, 'click its title') . '">
                    ' . $kinds('reminder') . '
                    ' . $f('What', '<span class="swap"><span class="a-out" style="' . $d(9, 'change any part') . '">Order hangers</span><span class="a-fade" style="' . $d(9, 'change any part') . '">Order hangers &mdash; 500 white</span></span>', '', 'a-ring', $d(9, 'change any part')) . '
                    <span class="btnp a-press addbtn" style="' . $d(9, 'click Save') . '">Save</span>
                  </div>
                  <div style="margin-top:.5rem"><span class="btns sm a-press" style="' . $d(9, 'click Delete') . '">Delete</span></div>
                  <div class="card a-mid" style="' . $d(9, 'and confirm') . ';--d2:' . $at(9, 'That takes it', 1.5) . ';position:absolute;left:20%;top:45%;z-index:5;box-shadow:var(--gd-shadow);color:var(--ink);font-weight:600">Delete this from the calendar?
                    <div style="display:flex;gap:.4rem;justify-content:flex-end;margin-top:.4rem"><span class="btns sm">Cancel</span><span class="btnp sm a-press" style="' . $d(9, 'and confirm', .8) . '">OK</span></div></div>
                  <div class="bnr a-pop" style="' . $d(9, 'That takes it', 1.5) . ';margin-top:.5rem;max-width:24rem">Deleted &ldquo;Order hangers &mdash; 500 white&rdquo;.</div>
                </div>

                <!-- 10 — menu + dashboard -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="sct a-fade" style="--d:.2s">The menu and the Dashboard</div>
                  <div class="two" style="margin-top:.5rem;max-width:28rem">
                    <div class="cmnu a-rise" style="' . $d(10, 'The menu says') . '"><div class="nh">Work</div><span class="it">Dashboard</span><span class="it">Orders</span><span class="it">Remakes</span>
                      <span class="it a-ring" style="' . $d(10, 'how many are due') . '">Calendar <b class="a-pop" style="' . $d(10, 'in brackets') . '">(3 due)</b></span></div>
                    <div class="ftile a-rise" style="' . $d(10, 'The Calendar tile') . '"><div class="fl2">Calendar</div><div class="fv a-pop" style="' . $d(10, 'same number') . '">3</div>
                      <div class="fs"><b style="color:#b91c1c">due now</b> &middot; reminders, callbacks, remakes</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(10, 'Both count') . '">reminders + callbacks due now</span>
                    <span class="chip a-pop" style="' . $d(10, 'and remakes due') . '">+ remakes due</span>
                    <span class="chip a-pop" style="' . $d(10, 'including anything') . '">+ anything carried forward</span>
                  </div>
                </div>

                <!-- 11 — account page -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="sct a-fade" style="--d:.2s">Callbacks on an account&rsquo;s page</div>
                  <p class="scs a-fade" style="--d:.6s">The trade account&rsquo;s page &mdash; ABC Blinds</p>
                  <div class="card acs a-rise" style="' . $d(11, 'has a Callbacks') . ';max-width:30rem">
                    <h4>Callbacks &amp; reminders <span class="btns sm a-ring" style="' . $d(11, 'Click Add callback') . '">+ Add callback</span></h4>
                    <ul>
                      <li class="a-fly" style="' . $d(11, 'It lists every') . '"><b>Fri 9 Oct 14:00</b> &middot; <span class="lnk">Ring about the fabric issue</span> &middot; ABC-2026-0041</li>
                      <li class="a-fly" style="' . $d(11, 'whatever its date') . '"><b>Thu 15 Oct</b> &middot; <span class="lnk">Check the new pattern books arrived</span></li>
                      <li class="a-fly" style="' . $d(11, 'marks any') . '"><b>Mon 5 Oct</b> &middot; <span class="lnk">Chase signed proof</span> <span class="late a-pop" style="' . $d(11, 'overdue') . '">overdue</span></li>
                    </ul>
                  </div>
                  <div class="frm a-rise" style="' . $d(11, 'the calendar opens') . ';margin-top:.7rem;max-width:30rem">
                    ' . $kinds('callback') . '
                    ' . $f('Account <small>(needed for a callback)</small>', 'ABC Blinds', 'sel', 'a-ring', $d(11, 'already set up')) . '
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>What it is.</b> <b>Work &rarr; Calendar</b> is <em>the office&rsquo;s shared calendar: notes, reminders and callbacks.
             Anything not ticked carries forward until it&rsquo;s done. Remake due dates appear here by themselves. Only the office sees
             this.</em> It is one list for the whole office, not one per person, and trade accounts never see it.</p>

          <ul class="steps">
            <li><b>Note</b> &mdash; for a day only. No tick box.</li>
            <li><b>Reminder</b> &mdash; a job to do. It stays (and shows as due) until someone ticks it.</li>
            <li><b>Callback</b> &mdash; a reminder to ring a trade account back. It needs an account, and it also shows on that account&rsquo;s
                page.</li>
          </ul>

          <p><b>Adding one.</b> Click <b>+ Add to the calendar</b>, then:</p>
          <ul class="steps">
            <li>Choose <b>Note</b>, <b>Reminder</b> or <b>Callback</b> (Reminder is picked to start with).</li>
            <li><b>What</b> &mdash; e.g. <em>Order hangers</em>. Leave it empty and it says
                <code>Say what it is &mdash; e.g. &ldquo;Order hangers&rdquo;.</code></li>
            <li><b>Date</b> (today to start with) and <b>Time (optional)</b>.</li>
            <li><b>Account (needed for a callback)</b> &mdash; a callback without one says <code>Choose which account to call back.</code></li>
            <li><b>Order number (optional)</b> &mdash; if exactly one order has that number (for that account, when one is chosen) it becomes a
                link to the order; if not, it is kept as text (<em>No order found with that number &hellip; &mdash; it&rsquo;s kept as text.</em>).</li>
            <li><b>Notes (optional)</b>, then <b>Add</b> &rarr; <code>Added to the calendar.</code> The calendar jumps to that day&rsquo;s week.</li>
          </ul>

          <p><b>Due now</b> <em>(today and anything carried forward)</em> lists every open reminder and callback dated today or earlier
             &mdash; older ones in red, <em>from Wed 7 Oct</em> &mdash; plus any remake due today or earlier that has not gone out. Empty, it
             says <em>Nothing due. Nice.</em></p>

          <ul class="steps">
            <li><b>Tick</b> the box beside a reminder or callback (in Due now or in the week) to mark it done: it is crossed through in the week
                and leaves Due now. Tick it again to undo.</li>
            <li><b>The week</b> runs Monday to Sunday (<b>Week of &hellip;</b>), today outlined, with <b>&lsaquo; Previous</b>,
                <b>This week</b> and <b>Next &rsaquo;</b>. The <b>+</b> in a day&rsquo;s corner opens the add form on that date, set to Reminder.</li>
            <li><b>Remakes</b> with a due date appear on their day in purple (<b>Remake due &hellip;</b>) by themselves &mdash; read-only,
                straight from the remake &mdash; until the remake is dispatched. Click one to open the remake order.</li>
            <li><b>Click a title</b> to open the entry: change anything and <b>Save</b> (<code>Saved.</code>); <b>&#10003; Mark done</b> /
                <b>Mark not done</b> for reminders and callbacks; <b>Delete</b> asks <em>Delete this from the calendar?</em> and removes it for
                everyone. <b>Back to the calendar</b> returns to that week.</li>
          </ul>

          <p><b>Without opening it.</b> The menu reads <b>Calendar (3 due)</b> and the Dashboard&rsquo;s <b>Calendar</b> tile shows the same
             number, <b>due now</b> in red (or <em>Nothing due</em>). Both count open reminders and callbacks dated today or earlier, plus remakes
             due today or earlier that have not gone out.</p>

          <p><b>On a trade account&rsquo;s page</b>, <b>Callbacks &amp; reminders</b> lists every open callback and reminder tied to that account,
             whatever its date, marking any <b>overdue</b> (or <em>Nothing open for this account.</em>). <b>+ Add callback</b> opens the calendar
             with a callback for that account ready to fill in.</p>',
        'script'  => $S,
];
