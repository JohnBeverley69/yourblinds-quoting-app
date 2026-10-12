<?php
declare(strict_types=1);

/**
 * Guide: bm-swap-over-balances — "Swap-over: Blind Matrix balances" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * SUPER-ADMIN ONLY (the page is requireSuperAdmin). Reached from Factory
 * Console → Accounts → QuickBooks → "Swap-over: Blind Matrix balances →".
 *
 * Sources: master-admin/bm-opening-balances.php — title + subtitle,
 * "← QuickBooks", the test-mode box, "1. Upload the Blind Matrix statements"
 * (+ the From/To hint), Choose file + "Read it", "Showing: … (read …)", flashes
 * "Read …. Nothing has been saved — check the accounts below." / "Updated.
 * Still nothing saved." / "Imported N invoices (£…). N already in YourBlinds
 * were skipped." / the refusals; "2. Check it" + period line + the eight chips,
 * "N rows to look at", the Green/Amber/Red hint, table columns, pills Same
 * name / Check this one / Picked by hand / Not found / Left out, "— choose —",
 * "Leave out (bad debt / don't bring in)", "Show invoices" and its columns,
 * "Update matches" + "Only changes this preview — nothing is saved.";
 * "3. Import" (switched-off text; Type IMPORT + Import, disabled until every
 * account is matched), "Clear this preview". _partials/bm_opening_balances.php
 * (what an imported invoice is), _partials/qbo_push.php ("Nothing to send: not
 * against any invoice dated from …").
 *
 * KEEP TRUE: importing only works when platform_config BM_IMPORT_ON = '1',
 * which is set for the swap-over only, with John.
 *
 * PUBLIC REPO: the accounts and figures shown are made up.
 *
 * v2: one SCENE per script line (data-scene = the line's step).
 */

$head = '<div class="hd"><div><div class="h1">Swap-over: Blind Matrix balances</div>'
      . '<p class="sub">Bring every unpaid Blind Matrix invoice into YourBlinds, so balances, statements and the Bank screen carry straight on from the swap-over.</p></div>'
      . '<span class="btns">&larr; QuickBooks</span></div>';

$test = '<div class="card test"><b>Test mode &mdash; nothing is saved.</b> <span>Upload a statement to check how it reads and which accounts it matches. The file is read and thrown away; no invoices are created. Importing is switched on only for the swap-over itself.</span></div>';

$upload = static fn (string $file, string $btn = '<span class="btnp">Read it</span>'): string =>
    '<div class="card"><b>1. Upload the Blind Matrix statements</b>'
  . '<p class="muted">In Blind Matrix: Statements, all trade accounts, <b>From</b> a date well before the oldest unpaid invoice (a few years back), <b>To</b> today, export to Excel. It lists every unpaid and part-paid invoice.</p>'
  . '<div class="frow"><span class="btns sm">Choose File</span><span class="fn">' . $file . '</span>' . $btn . '</div></div>';

$chipRow = static fn (array $c): string =>
    '<div class="bchips">'
  . '<span class="bc ' . ($c['w0'] ?? '') . '"><b>' . $c[0] . '</b> accounts</span>'
  . '<span class="bc"><b>' . $c[1] . '</b> unpaid invoices</span>'
  . '<span class="bc"><b>&pound;' . $c[2] . '</b> owed</span>'
  . '<span class="bc"><b>' . $c[3] . '</b> part-paid (brought in for the balance)</span>'
  . '<span class="bc ' . ($c[4] !== '0' ? 'warn' : '') . '"><b>' . $c[4] . '</b> accounts not matched</span>'
  . '<span class="bc ' . ($c[5] !== '0' ? 'warn' : '') . '"><b>' . $c[5] . '</b> already in YourBlinds (would be skipped)</span>'
  . '<span class="bc"><b>' . $c[6] . '</b> accounts left out (&pound;' . $c[7] . ')</span>'
  . '<span class="bc"><b>&pound;' . $c[8] . '</b> would come into YourBlinds</span>'
  . '</div>';

$pill = static fn (string $cls, string $t): string => '<span class="pill ' . $cls . '">' . $t . '</span>';

$row = static fn (string $name, string $sub, string $pill, string $sel, string $n, string $owed): string =>
    '<tr><td><b>' . $name . '</b><div class="muted s">' . $sub . '</div><div class="muted s lk">&#9656; Show invoices</div></td>'
  . '<td>' . $pill . '<br><span class="selectbox sb">' . $sel . '</span></td><td class="num">' . $n . '</td><td class="num">&pound;' . $owed . '</td></tr>';

$thead = '<tr><th>Blind Matrix account</th><th>YourBlinds trade account</th><th class="num">Invoices</th><th class="num">Owed</th></tr>';

$rows = [
    'none'   => $row('Starline Blinds', 'oldest 3 Mar 2025', $pill('none', 'Not found'), '&mdash; choose &mdash;', '2', '207.46'),
    'likely' => $row('Hilltop Blinds', 'oldest 1 Jul 2026 &middot; 1 part-paid', $pill('likely', 'Check this one'), 'Hilltop Blinds Ltd', '14', '3,912.40'),
    'exact'  => $row('Brightside Blinds', 'oldest 2 Sep 2026', $pill('exact', 'Same name'), 'Brightside Blinds', '31', '6,204.18'),
];

$swap = static fn (string $old, string $new, float $d): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $d . 's">' . $old . '</span><span class="a-fade" style="--d:' . ($d + .1) . 's">' . $new . '</span></span>';

$c0 = ['58', '641', '142,385.20', '2', '1', '0', '0', '0.00', '142,385.20'];

return [
        'aud'     => 'super',
        'section' => 'Factory Console',
        'title'   => 'Swap-over: Blind Matrix balances',
        'eyebrow' => 'Factory Console · QuickBooks',
        'v'       => 2,
        'blurb'   => 'On the swap-over day, bring every unpaid Blind Matrix invoice into YourBlinds from Blind Matrix\'s statements export, so balances, statements and the Bank screen carry on. Test mode until then.',
        'lede'    => 'When Beverley Blinds moves from Blind Matrix to YourBlinds, customers still owe money on <b>Blind Matrix invoices</b>.
                      This page reads Blind Matrix&rsquo;s <b>Statements</b> export and brings every unpaid invoice into YourBlinds, so
                      balances, statements and the <b>Bank</b> screen carry straight on. Until the swap-over it is in <b>test mode</b>: you
                      can upload and check as often as you like, and <b>nothing is saved</b>. Find it on <b>Factory Console</b> &rarr;
                      <b>Accounts</b> &rarr; <b>QuickBooks</b> &rarr; <b>Swap-over: Blind Matrix balances &rarr;</b>. Super-admins only.',
        'open'    => '/master-admin/bm-opening-balances.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:390px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .hd{ display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; flex-wrap:wrap; }
          .gd .h1{ font-size:.98rem; font-weight:800; color:var(--ink); }
          .gd .sub{ font-size:.64rem; color:var(--soft); margin:.1rem 0 .55rem; max-width:30rem; line-height:1.4; }
          .gd .card{ border:1px solid var(--border-strong,#c7ccd4); border-radius:10px; padding:.55rem .7rem; background:var(--surface); max-width:36rem; margin-bottom:.5rem; font-size:.68rem; color:var(--ink); }
          .gd .card.test{ border-left:4px solid #f59e0b; }
          .gd .card.test span{ color:var(--soft); }
          .gd .muted{ color:var(--faint); font-size:.62rem; margin:.25rem 0 .45rem; line-height:1.4; }
          .gd .muted b{ color:var(--soft); }
          .gd .muted.s{ margin:.1rem 0 0; font-size:.56rem; }
          .gd .lk{ cursor:pointer; }
          .gd .frow{ display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
          .gd .fn{ font-size:.64rem; color:var(--soft); }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.68rem; font-weight:700; white-space:nowrap; }
          .gd .btnp.off{ opacity:.45; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.26rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .btns.sm{ font-size:.6rem; padding:.18rem .45rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.24rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.5rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.38rem .6rem; font-size:.66rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; max-width:36rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          .gd .bchips{ display:flex; gap:.3rem; flex-wrap:wrap; margin:.2rem 0 .5rem; max-width:36rem; }
          .gd .bc{ border:1px solid var(--line); border-radius:999px; padding:.12rem .5rem; font-size:.58rem; color:var(--soft); background:var(--surface); }
          .gd .bc b{ color:var(--ink); } .gd .bc.warn{ border-color:#f59e0b; }
          .gd .tbl{ width:100%; max-width:36rem; border-collapse:collapse; font-size:.62rem; color:var(--ink); }
          .gd .tbl th{ text-align:left; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); padding:.2rem .3rem; font-size:.58rem; }
          .gd .tbl td{ border-bottom:1px solid var(--line); padding:.3rem .3rem; vertical-align:top; }
          .gd .num{ text-align:right; white-space:nowrap; }
          .gd .sb{ min-width:0; width:100%; max-width:12rem; box-sizing:border-box; font-size:.6rem; padding:.16rem .4rem; margin-top:.2rem; }
          .gd .pill{ display:inline-block; padding:.02rem .45rem; border-radius:999px; font-size:.56rem; font-weight:700; white-space:nowrap; }
          .gd .pill.exact{ background:#dcfce7; color:#166534; }
          .gd .pill.likely, .gd .pill.manual{ background:#fef3c7; color:#92400e; }
          .gd .pill.none{ background:#fee2e2; color:#991b1b; }
          .gd .pill.left{ background:var(--panel); color:var(--soft); }
          .gd .ib{ display:inline-flex; align-items:center; height:24px; width:7.5rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface);
                   padding:0 .4rem; font-size:.64rem; color:var(--ink); }
          .gd .inv{ font-size:.56rem; border-collapse:collapse; margin-top:.3rem; }
          .gd .inv th, .gd .inv td{ padding:.1rem .35rem; border-bottom:1px solid var(--line); }
          .gd .ddl{ position:absolute; z-index:5; border:1px solid var(--line); border-radius:8px; background:var(--surface); box-shadow:var(--gd-shadow);
                    font-size:.6rem; min-width:14rem; overflow:hidden; }
          .gd .ddl div{ padding:.25rem .55rem; color:var(--ink); }
          .gd .ddl div.hi{ background:var(--accent-wash); }
          .gd .steps3{ display:grid; gap:.35rem; max-width:34rem; }
          .gd .steps3 div{ display:flex; gap:.55rem; align-items:center; border:1px solid var(--line); border-radius:9px; padding:.38rem .6rem; background:var(--surface); font-size:.7rem; color:var(--ink); }
          .gd .steps3 i{ font-style:normal; font-weight:800; color:var(--accent); width:1.1rem; text-align:center; }
          .gd .bm{ border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:22rem; padding:.6rem .8rem; box-shadow:var(--gd-shadow); font-size:.68rem; color:var(--ink); }
          .gd .bm .who{ font-size:.56rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; }
          .gd .bm .r{ display:flex; justify-content:space-between; gap:.6rem; border-bottom:1px solid var(--line); padding:.28rem 0; }
          .gd .bm .r b{ color:var(--soft); font-weight:600; }

          @media (max-width:640px){
            .gd .sc{ min-height:500px; }
            .gd .tbl th:nth-child(3), .gd .tbl td:nth-child(3){ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / master-admin / bm-opening-balances</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>FACTORY CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Orders</a>
                <div class="navh">Accounts</div>
                <a>Trade accounts</a><a>Invoices</a><a>Bank</a><a class="on">QuickBooks</a><a>Statements</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head . $test . $upload('No file chosen') . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is for -->
                <div class="sc" data-scene="1" data-len="21">
                  <div class="a-fade" style="--d:.2s">' . $head . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5s">Unpaid Blind Matrix invoices</span>
                    <span class="chip ok a-pop" style="--d:9.5s">&rarr; brought into YourBlinds</span>
                    <span class="chip ok a-pop" style="--d:11.5s">Balances carry on</span>
                    <span class="chip ok a-pop" style="--d:12.5s">Statements carry on</span>
                    <span class="chip ok a-pop" style="--d:13.5s">The Bank screen carries on</span>
                    <span class="chip a-pop" style="--d:16.5s">QuickBooks &rarr; Swap-over: Blind Matrix balances &rarr;</span>
                  </div>
                </div>

                <!-- 2 — test mode -->
                <div class="sc" data-scene="2" data-len="17">
                  <div class="a-rise" style="--d:.5s">' . $test . '</div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:6.5s">Practise as often as you like</span>
                    <span class="chip ok a-pop" style="--d:11s">The file is thrown away</span>
                    <span class="chip bad a-pop" style="--d:13.5s">Importing: swap-over day only</span>
                  </div>
                </div>

                <!-- 3 — the Blind Matrix export -->
                <div class="sc" data-scene="3" data-len="17.5">
                  <div class="sct a-fade" style="--d:.2s">In Blind Matrix: the Statements export</div>
                  <div class="bm a-rise" style="--d:1s">
                    <div class="who">Blind Matrix &middot; not YourBlinds</div>
                    <div class="r"><b>Report</b><span class="a-fade" style="--d:3s">Statements</span></div>
                    <div class="r"><b>Accounts</b><span class="a-fade" style="--d:5s">All trade accounts</span></div>
                    <div class="r"><b>From</b><span class="a-type" style="--d:6.5s;--ts:10;--tt:.8s">01-10-2020</span></div>
                    <div class="r"><b>To</b><span class="a-type" style="--d:13.5s;--ts:10;--tt:.8s">31-12-2026</span></div>
                    <div class="r" style="border:0"><b>Export</b><span class="a-fade" style="--d:16s">Excel</span></div>
                  </div>
                  <div class="chips"><span class="chip bad a-pop" style="--d:10.5s">From = a few years back, or old unpaid ones are missed</span></div>
                </div>

                <!-- 4 — upload -->
                <div class="sc" data-scene="4" data-len="13.5">
                  <div class="bnr a-drop" style="--d:4.5s">Read Statements for Trade_Contract.xlsx. Nothing has been saved &mdash; check the accounts below.</div>
                  ' . $upload($swap('No file chosen', 'Statements for Trade_Contract.xlsx', 2), '<span class="btnp a-press" style="--d:3.5s">Read it</span>') . '
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:8.5s">Read, then thrown away</span>
                    <span class="chip a-pop" style="--d:11.5s">Read it again any time</span>
                  </div>
                </div>

                <!-- 5 — the counts -->
                <div class="sc" data-scene="5" data-len="18">
                  <div class="card"><b>2. Check it</b>
                    <p class="muted">Statement period 1 Oct 2020 to 31 Dec 2026.</p>
                    <div class="a-rise" style="--d:1s">' . $chipRow($c0) . '</div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4.5s">How many, and how much</span>
                    <span class="chip a-pop" style="--d:9s">Part-paid: just the balance</span>
                    <span class="chip bad a-pop" style="--d:12s">Amber outline = look at it</span>
                    <span class="chip ok a-pop" style="--d:15s">Last chip = what would come in</span>
                  </div>
                </div>

                <!-- 6 — colours -->
                <div class="sc" data-scene="6" data-len="19">
                  <p class="muted a-fade" style="--d:.3s"><b>Green</b> = same name as a YourBlinds account. <b>Amber</b> = a close name or picked by hand &mdash; check it.
                    <b>Red</b> = no YourBlinds account found: pick it, or create the trade account first and read the file again.</p>
                  <table class="tbl a-rise" style="--d:1s">' . $thead . $rows['none'] . $rows['likely'] . $rows['exact'] . '</table>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:7s">Green: same name</span>
                    <span class="chip a-pop" style="--d:9.5s">Amber: check it</span>
                    <span class="chip bad a-pop" style="--d:13s">Red: pick one, or make the account first</span>
                  </div>
                </div>

                <!-- 7 — show invoices -->
                <div class="sc" data-scene="7" data-len="13">
                  <table class="tbl">' . $thead . '
                    <tr><td><b>Brightside Blinds</b><div class="muted s">oldest 2 Sep 2026</div><div class="muted s lk a-ring" style="--d:1s">&#9662; Show invoices</div>
                      <table class="inv a-fade" style="--d:2.5s"><tr><th>Date</th><th>No.</th><th>Order</th><th>Their ref</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Owed</th></tr>
                        <tr><td>02/09/2026</td><td class="a-ring" style="--d:5.5s">60127</td><td>ON012345</td><td>Smith</td><td class="num">412.80</td><td class="num">0.00</td><td class="num">412.80</td></tr>
                        <tr><td>05/09/2026</td><td>60188</td><td>ON012391</td><td>Patel</td><td class="num">96.00</td><td class="num">50.00</td><td class="num a-ring" style="--d:10s">46.00</td></tr></table></td>
                      <td>' . $pill('exact', 'Same name') . '<br><span class="selectbox sb">Brightside Blinds</span></td><td class="num">31</td><td class="num">&pound;6,204.18</td></tr>
                  </table>
                </div>

                <!-- 8 — leave out bad debts -->
                <div class="sc" data-scene="8" data-len="15.5">
                  <div class="bnr a-drop" style="--d:10s">Updated. Still nothing saved.</div>
                  <table class="tbl">' . $thead . '
                    <tr><td><b>Starline Blinds</b><div class="muted s">oldest 3 Mar 2025</div></td>
                      <td>' . $swap($pill('none', 'Not found'), $pill('left', 'Left out'), 10.2) . '<br><span class="selectbox sb">' . $swap('&mdash; choose &mdash;', 'Leave out (bad debt / don&rsquo;t bring in)', 7.4) . '</span></td>
                      <td class="num">2</td><td class="num">&pound;207.46</td></tr>
                  </table>
                  <div class="ddl a-mid" style="--d:4.5s;--d2:7.3s;left:38%;top:5.6rem"><div>&mdash; choose &mdash;</div><div class="hi">Leave out (bad debt / don&rsquo;t bring in)</div><div>Oakwood Blinds And Curtains</div></div>
                  <div style="margin-top:.6rem"><span class="btns a-press" style="--d:9s">Update matches</span> <span class="muted">Only changes this preview &mdash; nothing is saved.</span></div>
                  <div class="chips" style="margin-top:2.5rem"><span class="chip a-pop" style="--d:1.5s">A bad debt you won&rsquo;t chase</span>
                    <span class="chip ok a-pop" style="--d:13s">Left out = not brought in</span></div>
                </div>

                <!-- 9 — on the day -->
                <div class="sc" data-scene="9" data-len="20.5">
                  <div class="sct a-fade" style="--d:.2s">On the swap-over day</div>
                  <div class="steps3">
                    <div class="a-fly" style="--d:2s"><i>1</i>Blind Matrix bank and accounts up to date</div>
                    <div class="a-fly" style="--d:7s"><i>2</i>A fresh Statements export &rarr; Read it</div>
                    <div class="a-fly" style="--d:10s"><i>3</i>Check every account; Leave out the bad debts</div>
                    <div class="a-fly" style="--d:12.5s"><i>4</i>Importing switched on (with John)</div>
                  </div>
                  <div class="card a-rise" style="--d:13s;margin-top:.5rem"><b>3. Import</b>
                    <p class="muted">Creates 639 brought-forward invoices (&pound;142,177.74, less any already in YourBlinds; left-out accounts are not brought in), all in one go &mdash; if anything fails, nothing is saved.</p>
                    <div class="frow"><span class="ib"><span class="a-type" style="--d:14.5s;--ts:6;--tt:.6s">IMPORT</span></span><span class="btnp a-press" style="--d:16s">Import</span></div></div>
                </div>

                <!-- 10 — what you get -->
                <div class="sc" data-scene="10" data-len="20">
                  <div class="bnr a-drop" style="--d:.5s">Imported 639 invoices (&pound;142,177.74). 0 already in YourBlinds were skipped.</div>
                  <div class="card a-rise" style="--d:2.5s"><b>Brought forward from Blind Matrix</b>
                    <p class="muted" style="margin-bottom:0">One invoice for each unpaid Blind Matrix invoice: its <b>Blind Matrix number</b>, date and their reference, for the <b>amount still owed</b>.</p></div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:10.5s">Bank screen: their payment quoting 60127 &rarr; that invoice</span>
                    <span class="chip a-pop" style="--d:13s">Statements show them</span>
                    <span class="chip bad a-pop" style="--d:15s">Not sent to QuickBooks &mdash; dated before the send-from date</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <div class="heads"><span class="hi">&#9888;</span><div><b>Test mode until the swap-over.</b> The orange box says
             <em>&ldquo;Test mode &mdash; nothing is saved.&rdquo;</em> You can upload and check as often as you like; no invoices are
             created and the file is thrown away straight after it&rsquo;s read. Importing is switched on only for the swap-over itself
             (with John).</div></div>

          <p><b>What it&rsquo;s for.</b> At the swap-over, customers still owe money on Blind Matrix invoices. This page brings every
             unpaid one into YourBlinds, so balances, statements and the <b>Bank</b> screen carry straight on. Find it on
             <b>Factory Console &rarr; Accounts &rarr; QuickBooks</b>, in the card <b>Swap-over from Blind Matrix</b>.</p>

          <p><b>1. Export from Blind Matrix.</b> Statements, <b>all trade accounts</b>, <b>From</b> a date well before the oldest unpaid
             invoice (a few years back), <b>To</b> today, export to <b>Excel</b>. It lists every unpaid and part-paid invoice. If the From
             date is too recent, older unpaid invoices are missed.</p>

          <p><b>2. Upload it.</b> Under <b>1. Upload the Blind Matrix statements</b>, choose the file and press <b>Read it</b>. You&rsquo;ll
             see <em>&ldquo;Read <i>file</i>. Nothing has been saved &mdash; check the accounts below.&rdquo;</em> (Wrong file? &ldquo;No unpaid
             invoices found in that file &mdash; is it the Statements export?&rdquo;)</p>

          <p><b>3. Check it.</b> Under <b>2. Check it</b>:</p>
          <ul class="steps">
            <li>The statement period, then counts: <b>accounts</b>, <b>unpaid invoices</b>, <b>owed</b>, <b>part-paid (brought in for the
                balance)</b>, <b>accounts not matched</b>, <b>already in YourBlinds (would be skipped)</b>, <b>accounts left out</b>, and
                <b>would come into YourBlinds</b>. Counts that need a look have an amber outline. <b>N rows to look at</b> (if shown) lists
                lines the file couldn&rsquo;t read cleanly.</li>
            <li>Each Blind Matrix account is matched to a YourBlinds trade account: <b>Same name</b> (green), <b>Check this one</b> or
                <b>Picked by hand</b> (amber &mdash; check it), <b>Not found</b> (red &mdash; pick it, or create the trade account first and
                read the file again).</li>
            <li><b>Show invoices</b> lists each one: Date, No. (the Blind Matrix number), Order, Their ref, Amount, Paid, Owed.</li>
            <li><b>Bad debts</b> you won&rsquo;t chase: choose <b>Leave out (bad debt / don&rsquo;t bring in)</b>.</li>
            <li>Press <b>Update matches</b> (&ldquo;Only changes this preview &mdash; nothing is saved.&rdquo;). <b>Clear this preview</b>
                starts again.</li>
          </ul>

          <p><b>4. On the swap-over day.</b> Get the bank and accounts up to date in Blind Matrix, export a fresh statement, read it and
             check it. With importing switched on, <b>3. Import</b> says how many brought-forward invoices it will create. Type
             <code>IMPORT</code> in the box and press <b>Import</b>. It&rsquo;s all in one go: if anything fails, nothing is saved. The
             button stays greyed out until every account is matched or left out. You&rsquo;ll see <em>&ldquo;Imported N invoices
             (&pound;&hellip;). N already in YourBlinds were skipped.&rdquo;</em> Reading the same file twice never doubles up &mdash;
             invoice numbers already in YourBlinds are skipped.</p>

          <p><b>What you get.</b> One invoice per unpaid Blind Matrix invoice, keeping its Blind Matrix number, date and the
             customer&rsquo;s reference, for the <b>amount still owed</b>, marked <b>Brought forward from Blind Matrix</b>. VAT is taken
             as a sixth of the amount (20%). On the <b>Bank</b> screen, a payment from a known payer quoting the Blind Matrix number
             matches that invoice.</p>

          <p><b>QuickBooks.</b> These invoices are dated before the QuickBooks <b>Send invoices dated from</b> date, so payments
             against them are <b>not sent</b> to QuickBooks (the QuickBooks page shows them as &ldquo;nothing to send&rdquo;). Enter those
             in QuickBooks the usual way.</p>',
        'script'  => [
            ['1',  'What this page is for',  'When we swap over from Blind Matrix, customers will still owe money on Blind Matrix invoices. This page brings every unpaid one into YourBlinds. So balances, statements and the Bank screen carry straight on. You find it on the QuickBooks page, under Swap-over from Blind Matrix.', 1],
            ['2',  'Test mode',              'Until the swap-over, the page is in test mode. The orange box says so. You can upload and check as often as you like, and nothing is saved. The file is read, then thrown away. Importing is only switched on for the swap-over itself.', 2],
            ['3',  'The Blind Matrix export', 'Start in Blind Matrix. Run Statements, for all trade accounts. Set From to a date well before the oldest unpaid invoice, a few years back. If it is too recent, older unpaid invoices are missed. Set To as today, and export it to Excel.', 3],
            ['4',  'Read it',                'Back here, choose the file, and press Read it. A green bar says it has been read, and that nothing has been saved. The file itself is not kept. You can read a new one at any time.', 4],
            ['5',  'The counts',             'Under Check it, a row of counts. How many accounts, how many unpaid invoices, and how much is owed. Part paid ones come in for just the balance. Anything that needs a look has an amber outline. The last count is what would come into YourBlinds.', 5],
            ['6',  'Green, amber, red',      'Each Blind Matrix account is matched to a YourBlinds trade account. Green means the same name. Amber means a close name, or one you picked, so check it. Red means none was found. Pick the right one, or make the trade account first, and read the file again.', 6],
            ['7',  'Show invoices',          'Click Show invoices to see each one: the date, the Blind Matrix invoice number, the order, their reference, and what is still owed. A part paid invoice shows how much was paid.', 7],
            ['8',  'Leave out bad debts',    'If an account is a bad debt you will not chase, open its dropdown, and choose Leave out. Then press Update matches. That only changes the preview. Nothing is saved. A left out account is not brought in at all.', 8],
            ['9',  'On the swap-over day',   'On the day, get the bank and accounts up to date in Blind Matrix first. Then export a fresh statement, read it, and check every account. Once importing is switched on, type the word import in the box, and press Import. It is all in one go. If anything fails, nothing is saved.', 9],
            ['10', 'What you get',           'Each unpaid Blind Matrix invoice becomes a brought forward invoice, with its Blind Matrix number, for the amount still owed. On the Bank screen, a payment quoting that number matches it. These are not sent to QuickBooks, because they are dated before the send from date.', 10],
        ],
];
