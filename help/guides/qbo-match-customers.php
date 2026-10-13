<?php
declare(strict_types=1);

/**
 * Guide: qbo-match-customers — "Match customers (QuickBooks)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * For the factory's accounts person (office login): sidebar Accounts →
 * "QuickBooks customers"; super-admins reach the same page from the QuickBooks
 * page ("Match customers →"). The page is requireFactoryAccounts() (main
 * factory only).
 *
 * Sources: master-admin/quickbooks-customers.php — title "Match customers" +
 * subtitle, "← QuickBooks" (super only), "Connected to … · N customers in
 * QuickBooks", chips "N linked" / "N suggested — check and save" / "N no match
 * found", the Blue/Amber hint paragraph, "Showing the N that need a decision."
 * / "Show all N accounts" / "Show only those needing a decision", table
 * columns Trade account (YourBlinds) / Status / QuickBooks customer, pills
 * Linked / Same name / Check this one / Not found in QuickBooks / No match
 * found, "— not linked —", "(inactive)", the two amber notes, "Create in
 * QuickBooks" + its confirm, "Save matches" + "Saves every row as shown
 * above.", flashes "Saved: N linked." / "Nothing changed." / "Created "…" in
 * QuickBooks and linked it." / the "already has … link to that one" refusal,
 * "Every trade account is linked. 🎉"; _partials/qbo_push.php held message.
 *
 * v2: one SCENE per script line (data-scene = the line's step).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$head = '<div class="h1">Match customers</div>'
      . '<p class="sub">Pair each trade account with its customer that&rsquo;s already in QuickBooks. Done once; after that, paid invoices always go to the right customer and no duplicates are made.</p>';

$conn = '<p class="cn">Connected to <b>Beverley Blinds Ltd</b> &middot; 312 customers in QuickBooks</p>';

$chips = static fn (string $a, string $b, string $c): string =>
    '<div class="sum"><span class="qc"><b>' . $a . '</b> linked</span><span class="qc"><b>' . $b . '</b> suggested &mdash; check and save</span><span class="qc"><b>' . $c . '</b> no match found</span></div>';

$pill = static fn (string $cls, string $t): string => '<span class="pill ' . $cls . '">' . $t . '</span>';

// One table row. $sel = the dropdown's HTML; $btn = the right-hand cell.
$row = static fn (string $name, string $ref, string $status, string $sel, string $btn = '', string $cls = '', string $note = ''): string =>
    '<tr class="' . $cls . '"><td><b>' . $name . '</b>' . ($ref !== '' ? '<span class="ref"> &middot; ' . $ref . '</span>' : '') . '</td>'
  . '<td>' . $status . '</td><td><span class="selectbox sb">' . $sel . '</span>' . $note . '</td><td>' . $btn . '</td></tr>';

$thead = '<tr><th>Trade account (YourBlinds)</th><th>Status</th><th>QuickBooks customer</th><th></th></tr>';
$create = '<span class="btns sm">Create in QuickBooks</span>';
$nl = '<span class="ph">&mdash; not linked &mdash;</span>';

$swap = static fn (string $old, string $new, float $d): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $d . 's">' . $old . '</span><span class="a-fade" style="--d:' . ($d + .1) . 's">' . $new . '</span></span>';

// The usual "to do" table, used by several scenes.
$todo = static fn (array $over = []): string =>
    '<table class="tbl">' . $thead
  . ($over['likely'] ?? $row('KMS Blinds', 'KMS01', $pill('likely', 'Check this one'), 'KMS Blinds &amp; Shutters', $create, 'likely'))
  . ($over['exact']  ?? $row('Oakwood Blinds And Curtains', 'OAK01', $pill('exact', 'Same name'), 'Oakwood Blinds And Curtains'))
  . ($over['exact2'] ?? $row('P &amp; J Blinds Ltd', 'PJB01', $pill('exact', 'Same name'), 'P &amp; J Blinds Ltd'))
  . ($over['none']   ?? $row('Shadeline Interiors', '', $pill('none', 'No match found'), $nl, $create))
  . '</table>';

$save = '<div class="stk"><span class="btnp">Save matches</span> <span class="ref">Saves every row as shown above.</span></div>';

return [
        'aud'     => 'console',
        'section' => 'Factory Console',
        'title'   => 'Match customers (QuickBooks)',
        'eyebrow' => 'Factory Console · QuickBooks customers',
        'v'       => 2,
        'blurb'   => 'Pair each trade account with the customer already in QuickBooks, once, so paid invoices always land on the right customer and nothing is duplicated.',
        'lede'    => 'Before YourBlinds can send a paid invoice to QuickBooks, it has to know <b>which QuickBooks customer</b> each trade
                      account is. This page pairs them up, <b>once</b>. YourBlinds suggests the match; you check it and press
                      <b>Save matches</b>. After that, paid invoices always go to the right customer and <b>no duplicates</b> are made.
                      Find it in the menu under <b>Accounts</b> &rarr; <b>QuickBooks customers</b> (super-admins: <b>QuickBooks</b> &rarr;
                      <b>Match customers &rarr;</b>).',
        'open'    => '/master-admin/quickbooks-customers.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .h1{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .sub{ font-size:.66rem; color:var(--soft); margin:.1rem 0 .55rem; max-width:33rem; line-height:1.4; }
          .gd .cn{ font-size:.68rem; color:var(--soft); margin:0 0 .35rem; } .gd .cn b{ color:var(--ink); }
          .gd .sum{ display:flex; gap:.35rem; flex-wrap:wrap; margin:0 0 .5rem; }
          .gd .qc{ border:1px solid var(--line); border-radius:999px; padding:.15rem .55rem; font-size:.62rem; color:var(--soft); background:var(--surface); }
          .gd .qc b{ color:var(--ink); }
          .gd .hint{ font-size:.62rem; color:var(--soft); line-height:1.45; max-width:33rem; margin:0 0 .45rem; }
          .gd .hint b{ color:var(--ink); }
          .gd .show{ font-size:.62rem; color:var(--faint); margin:0 0 .4rem; }
          .gd .show a{ color:var(--accent); font-weight:600; margin-left:.4rem; }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .btns.sm{ font-size:.58rem; padding:.18rem .45rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.24rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.55rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.38rem .6rem; font-size:.68rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; max-width:33rem; }
          .gd .bnr.err{ background:var(--err-wash,#fee2e2); border-left-color:var(--err); font-weight:600; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          .gd .ph{ color:var(--faint); }
          .gd .ref{ color:var(--faint); font-size:.58rem; font-weight:400; }

          .gd .tbl{ width:100%; max-width:36rem; border-collapse:collapse; font-size:.62rem; color:var(--ink); }
          .gd .tbl th{ text-align:left; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); padding:.2rem .3rem; font-size:.58rem; }
          .gd .tbl td{ border-bottom:1px solid var(--line); padding:.3rem .3rem; vertical-align:middle; }
          .gd .tbl tr.likely td{ background:color-mix(in srgb, #f59e0b 9%, transparent); }
          .gd .sb{ min-width:0; width:100%; max-width:12rem; box-sizing:border-box; font-size:.6rem; padding:.18rem .4rem; }
          .gd .pill{ display:inline-block; padding:.02rem .45rem; border-radius:999px; font-size:.56rem; font-weight:700; white-space:nowrap; }
          .gd .pill.linked{ background:#dcfce7; color:#166534; }
          .gd .pill.exact{ background:#dbeafe; color:#1e40af; }
          .gd .pill.likely{ background:#fef3c7; color:#92400e; }
          .gd .pill.none{ background:var(--panel); color:var(--soft); }
          .gd .pill.missing{ background:#fee2e2; color:#991b1b; }
          .gd .note{ font-size:.55rem; color:#92400e; margin-top:.15rem; }
          .gd .stk{ border-top:1px solid var(--line); padding-top:.45rem; margin-top:.45rem; max-width:36rem; }

          .gd .ddl{ position:absolute; z-index:5; border:1px solid var(--line); border-radius:8px; background:var(--surface); box-shadow:var(--gd-shadow);
                    font-size:.62rem; min-width:13rem; overflow:hidden; }
          .gd .ddl div{ padding:.25rem .55rem; color:var(--ink); }
          .gd .ddl div.hi{ background:var(--accent-wash); }
          .gd .confirm{ position:absolute; z-index:5; left:6%; top:32%; max-width:22rem; background:var(--surface); border:1px solid var(--line);
                        border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.7rem; color:var(--ink); line-height:1.45; }
          .gd .confirm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }

          .gd .flow{ display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; margin:.6rem 0 .3rem; }
          .gd .node{ border:1px solid var(--line); border-radius:10px; padding:.45rem .6rem; background:var(--surface); font-size:.7rem; font-weight:700; color:var(--ink); text-align:center; }
          .gd .node small{ display:block; font-weight:500; color:var(--faint); font-size:.58rem; }
          .gd .arr{ font-size:1rem; color:var(--faint); }

          @media (max-width:640px){
            .gd .sc{ min-height:480px; }
            .gd .tbl th:nth-child(4), .gd .tbl td:nth-child(4){ display:none; }
            .gd .confirm{ left:0; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / master-admin / quickbooks-customers</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>FACTORY CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Orders</a>
                <div class="navh">Accounts</div>
                <a>Invoices</a><a>Bank</a><a class="on">QuickBooks customers</a><a>Statements</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head . $conn . $chips('40', '3', '1') . '
                  ' . $todo() . $save . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is for -->
                <div class="sc" data-scene="1" data-len="22">
                  <div class="a-fade" style="--d:.2s">' . $head . '</div>
                  <div class="flow">
                    <div class="node a-pop" style="--d:5s">Trade account<small>in YourBlinds</small></div>
                    <span class="arr a-fade" style="--d:9.5s">&harr;</span>
                    <div class="node a-pop" style="--d:10s">Customer<small>already in QuickBooks</small></div>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:11s">Done once</span>
                    <span class="chip ok a-pop" style="--d:14s">Right customer every time</span>
                    <span class="chip ok a-pop" style="--d:16.5s">No duplicates</span>
                    <span class="chip a-pop" style="--d:18s">Menu: Accounts &rarr; QuickBooks customers</span>
                  </div>
                </div>

                <!-- 2 — the top of the page -->
                <div class="sc" data-scene="2" data-len="20">
                  <div class="a-fade" style="--d:.2s">' . $conn . '</div>
                  <div class="a-rise" style="--d:2s">' . $chips('<span class="a-ring" style="--d:3.3s">40</span>', '<span class="a-ring" style="--d:7.3s">3</span>', '<span class="a-ring" style="--d:11s">1</span>') . '</div>
                  <p class="hint a-fade" style="--d:12s">Each row shows the QuickBooks customer we think it is. <b>Blue</b> = same name. <b>Amber</b> = a close name &mdash; please
                    check it. Change any that are wrong (or choose <i>&mdash; not linked &mdash;</i>), then press <b>Save matches</b>.
                    Nothing is linked until you save. Only use <b>Create in QuickBooks</b> for an account that really isn&rsquo;t in QuickBooks yet.</p>
                </div>

                <!-- 3 — only what needs a decision -->
                <div class="sc" data-scene="3" data-len="17">
                  <p class="show a-fade" style="--d:.5s">Showing the 4 that need a decision.<a class="a-ring" style="--d:12.5s">Show all 44 accounts</a></p>
                  <div class="a-rise" style="--d:2.5s">' . $todo() . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:8s">Linked ones are tucked away</span>
                    <span class="chip a-pop" style="--d:13s">Show all &rarr; check or change an old one</span>
                  </div>
                </div>

                <!-- 4 — reading a row -->
                <div class="sc" data-scene="4" data-len="18">
                  <table class="tbl">' . $thead . '
                    <tr><td><b class="a-ring" style="--d:3s">Oakwood Blinds And Curtains</b><span class="ref a-ring" style="--d:4.5s"> &middot; OAK01</span></td>
                      <td><span class="pill exact a-ring" style="--d:7s">Same name</span></td>
                      <td><span class="selectbox sb a-ring" style="--d:9.5s">Oakwood Blinds And Curtains</span></td><td></td></tr>
                  </table>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3.5s">Our trade account</span>
                    <span class="chip a-pop" style="--d:5s">Its account ref</span>
                    <span class="chip a-pop" style="--d:7.5s">How sure we are</span>
                    <span class="chip a-pop" style="--d:10s">The QuickBooks customer we picked</span>
                  </div>
                </div>

                <!-- 5 — blue -->
                <div class="sc" data-scene="5" data-len="10.5">
                  <div class="sct a-fade" style="--d:.2s">Blue &mdash; Same name</div>
                  <table class="tbl a-rise" style="--d:1s">' . $thead
                    . $row('Oakwood Blinds And Curtains', 'OAK01', '<span class="pill exact a-ring" style="--d:2s">Same name</span>', 'Oakwood Blinds And Curtains')
                    . $row('P &amp; J Blinds Ltd', 'PJB01', '<span class="pill exact a-ring" style="--d:2.5s">Same name</span>', 'P &amp; J Blinds Ltd') . '</table>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:6.5s">Nearly always right</span>
                    <span class="chip a-pop" style="--d:8s">Still glance at it</span>
                  </div>
                </div>

                <!-- 6 — amber -->
                <div class="sc" data-scene="6" data-len="15.5">
                  <div class="sct a-fade" style="--d:.2s">Amber &mdash; Check this one</div>
                  <table class="tbl a-rise" style="--d:1s">' . $thead
                    . $row('KMS Blinds', 'KMS01', '<span class="pill likely a-ring" style="--d:2.5s">Check this one</span>',
                           $swap('KMS Blinds &amp; Shutters', 'KMS Blinds', 14), $create, 'likely') . '</table>
                  <div class="ddl a-mid" style="--d:11s;--d2:13.9s;left:45%;top:4.8rem">
                    <div>KMS Blinds &amp; Shutters</div><div class="hi">KMS Blinds</div><div>KMS Interiors</div></div>
                  <div class="chips" style="margin-top:4.6rem">
                    <span class="chip a-pop" style="--d:4.5s">A close name &mdash; not the same</span>
                    <span class="chip bad a-pop" style="--d:10s">Wrong? Pick the right one</span>
                  </div>
                </div>

                <!-- 7 — no match found -->
                <div class="sc" data-scene="7" data-len="17.5">
                  <div class="sct a-fade" style="--d:.2s">No match found</div>
                  <table class="tbl a-rise" style="--d:1s">' . $thead
                    . $row('Shadeline Interiors', '', '<span class="pill none">No match found</span>', $swap($nl, 'Shadeline Ltd', 12.5), $create) . '</table>
                  <div class="ddl a-mid" style="--d:9s;--d2:12.4s;left:45%;top:4.8rem">
                    <div>' . $nl . '</div><div>Shade &amp; Co</div><div class="hi">Shadeline Ltd</div></div>
                  <div class="chips" style="margin-top:4.6rem">
                    <span class="chip a-pop" style="--d:8s">Often there under a different name</span>
                    <span class="chip a-pop" style="--d:13.5s">Not sure? Leave it &mdash; not linked &mdash;</span>
                  </div>
                </div>

                <!-- 8 — save -->
                <div class="sc" data-scene="8" data-len="17">
                  <div class="bnr a-drop" style="--d:7.7s">Saved: 4 linked.</div>
                  <div class="a-mid" style="--d:.3s;--d2:7.6s">' . $todo() . '
                    <div class="stk"><span class="btnp a-press" style="--d:5.5s">Save matches</span> <span class="ref">Saves every row as shown above.</span></div></div>
                  <div class="a-fade" style="--d:8s;position:absolute;left:0;top:2.4rem;width:100%">
                    ' . $chips('44', '0', '0') . '
                    <p class="show">Showing all 44 accounts.</p>
                    <table class="tbl">' . $thead
                      . $row('Oakwood Blinds And Curtains', 'OAK01', $pill('linked', 'Linked'), 'Oakwood Blinds And Curtains')
                      . $row('KMS Blinds', 'KMS01', $pill('linked', 'Linked'), 'KMS Blinds') . '</table>
                  </div>
                  <div class="chips" style="position:absolute;left:0;top:14rem"><span class="chip ok a-pop" style="--d:2s">Nothing is linked until you save</span>
                    <span class="chip a-pop" style="--d:13.5s">Change your mind? Pick &mdash; not linked &mdash; and save</span></div>
                </div>

                <!-- 9 — create in QuickBooks -->
                <div class="sc" data-scene="9" data-len="21">
                  <div class="bnr a-drop" style="--d:12.5s">Created &ldquo;Northgate Blinds&rdquo; in QuickBooks and linked it.</div>
                  <div class="chips" style="margin:0 0 .5rem"><span class="chip bad a-pop" style="--d:2s">Only for a genuinely new account</span></div>
                  <table class="tbl">' . $thead
                    . $row('Northgate Blinds', 'NOR01', '<span class="pill none">No match found</span>', $nl, '<span class="btns sm a-press" style="--d:6.5s">Create in QuickBooks</span>') . '</table>
                  <div class="confirm a-mid" style="--d:7.5s;--d2:12s">Create a NEW customer &ldquo;Northgate Blinds&rdquo; in QuickBooks?<br><br>
                    Only do this if it really isn&rsquo;t in QuickBooks already. Otherwise pick it from the list instead.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:11s">OK</span></div></div>
                </div>

                <!-- 10 — warnings and held payments -->
                <div class="sc" data-scene="10" data-len="19.5">
                  <table class="tbl a-rise" style="--d:.5s">' . $thead
                    . $row('Brightside Blinds', 'BRI01', '<span class="pill missing a-ring" style="--d:3.5s">Not found in QuickBooks</span>', 'Brightside Blinds (no longer in QuickBooks)', '', '',
                           '<div class="note">This customer was deleted or merged in QuickBooks &mdash; pick the right one.</div>')
                    . $row('Just Shades', 'JUS02', $pill('linked', 'Linked'), 'Just Shades Leeds', '', '',
                           '<div class="note a-fade" style="--d:10.5s">Another trade account is linked to this same QuickBooks customer.</div>') . '</table>
                  <div class="chips">
                    <span class="chip bad a-pop" style="--d:14.5s">Not matched = payment held</span>
                    <span class="chip ok a-pop" style="--d:17s">Match it &rarr; it goes on the next run</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Why it matters.</b> When a trade account pays, YourBlinds sends the invoice and the payment to Beverley Blinds&rsquo;
             QuickBooks. QuickBooks already has a customer for nearly every account. This page pairs them up <b>once</b>, so the
             invoice lands on the right customer and QuickBooks never gets a duplicate.</p>

          <p><b>Where it is.</b> In the menu under <b>Accounts</b> &rarr; <b>QuickBooks customers</b>. (Super-admins: <b>QuickBooks</b>
             &rarr; <b>Match customers &rarr;</b>.) If it says <em>&ldquo;QuickBooks isn&rsquo;t connected&rdquo;</em>, ask the administrator
             to connect it first.</p>

          <p><b>The top of the page.</b> &ldquo;Connected to <i>company</i> &middot; <i>N</i> customers in QuickBooks&rdquo;, then three
             counts: <b>linked</b>, <b>suggested &mdash; check and save</b>, and <b>no match found</b>. Normally you only see the accounts
             that need a decision (<em>&ldquo;Showing the N that need a decision.&rdquo;</em>). Click <b>Show all N accounts</b> to see the
             linked ones too, for example to change one.</p>

          <p><b>Each row</b> shows the trade account (with its account ref), a status, and a <b>QuickBooks customer</b> dropdown already
             set to our best guess:</p>
          <ul class="steps">
            <li><b>Same name</b> (blue) &mdash; the names match. Nearly always right, but glance at it.</li>
            <li><b>Check this one</b> (amber, the row is shaded) &mdash; a close name, not the same. Check it carefully; if it&rsquo;s wrong,
                pick the right customer from the dropdown.</li>
            <li><b>No match found</b> &mdash; nothing obvious. Open the dropdown and look: they&rsquo;re often in QuickBooks under a
                different name. If you&rsquo;re not sure, leave it on <b>&mdash; not linked &mdash;</b>.</li>
            <li><b>Linked</b> (green) &mdash; already saved.</li>
            <li><b>Not found in QuickBooks</b> (red) &mdash; &ldquo;This customer was deleted or merged in QuickBooks &mdash; pick the right
                one.&rdquo;</li>
          </ul>
          <p>Inactive QuickBooks customers show <b>(inactive)</b> in the list. If two trade accounts are linked to the same QuickBooks
             customer, the row says <em>&ldquo;Another trade account is linked to this same QuickBooks customer.&rdquo;</em> &mdash; check
             that&rsquo;s really meant.</p>

          <p><b>Saving.</b> <b>Nothing is linked until you press Save matches</b> (at the bottom: &ldquo;Saves every row as shown
             above.&rdquo;). You&rsquo;ll see <b>&ldquo;Saved: N linked.&rdquo;</b> To undo a match, set it to <b>&mdash; not linked
             &mdash;</b> and save again.</p>

          <p><b>Create in QuickBooks.</b> Only for an account that <b>really isn&rsquo;t in QuickBooks yet</b> (a new customer). It
             asks: <em>&ldquo;Create a NEW customer &hellip; in QuickBooks? Only do this if it really isn&rsquo;t in QuickBooks already.
             Otherwise pick it from the list instead.&rdquo;</em> Press OK and you&rsquo;ll see <b>&ldquo;Created &hellip; in QuickBooks
             and linked it.&rdquo;</b> The button isn&rsquo;t shown when there&rsquo;s a same-name match, and YourBlinds refuses if
             QuickBooks already has that exact name (&ldquo;&hellip; link to that one instead of creating a duplicate&rdquo;). New trade
             accounts are <b>never</b> created in QuickBooks automatically.</p>

          <p><b>What happens if an account isn&rsquo;t matched.</b> Its payments are <b>held</b>, not sent. The QuickBooks page shows
             <em>&ldquo;Match &lsquo;<i>account</i>&rsquo; to its QuickBooks customer first (QuickBooks &rarr; Match customers).&rdquo;</em>
             Match it here and it goes on the next run, within fifteen minutes.</p>

          <p>When every account is done the page says <b>&ldquo;Every trade account is linked. &#127881;&rdquo;</b></p>',
        'script'  => [
            ['1',  'What this page is for',   'When a trade account pays, its invoice goes to QuickBooks. QuickBooks already has a customer for nearly every account. This page pairs them up, once. After that, the invoice always lands on the right customer, and no duplicates are made. Find it in the menu, under Accounts, QuickBooks customers.', 1],
            ['2',  'The top of the page',     'At the top, three counts. How many are linked. How many are suggested, waiting for you to check and save. And how many have no match. Underneath is a short reminder: blue means the same name, amber means a close name, so check it. And nothing is linked until you save.', 2],
            ['3',  'Only what needs a decision', 'To keep it short, the list only shows the accounts that need a decision. Linked ones are tucked away. If you want to see them, perhaps to change one, click Show all accounts. To go back, click Show only those needing a decision.', 3],
            ['4',  'Reading a row',           'Each row is one trade account. On the left, its name and account reference. Next, a coloured label that says how sure we are. Then a dropdown, already set to the QuickBooks customer we think it is. You only change the dropdown if it is wrong.', 4],
            ['5',  'Blue: same name',         'A blue label, Same name, means the names match exactly. These are nearly always right. Still, give each one a quick glance before you save.', 5],
            ['6',  'Amber: check this one',   'An amber label, Check this one, means the names are close, but not the same. The row is shaded so it stands out. Look carefully. If it is the wrong customer, open the dropdown and pick the right one.', 6],
            ['7',  'No match found',          'No match found means nothing obvious turned up. Open the dropdown and have a look. They are often in QuickBooks under a slightly different name. If you are not sure, leave it on not linked, and ask. A wrong match is worse than none.', 7],
            ['8',  'Save matches',            'When the list looks right, press Save matches at the bottom. Nothing is linked until you do. A green bar tells you how many were linked, and they move out of the list. To undo one later, set it back to not linked, and save again.', 8],
            ['9',  'Create in QuickBooks',    'Create in QuickBooks is only for an account that really is not in QuickBooks yet, such as a brand new customer. It asks you first. Press OK, and it makes the customer in QuickBooks and links it. If QuickBooks already has that exact name, it refuses, and tells you to link to that one.', 9],
            ['10', 'Warnings, and held payments', 'Two warnings to look out for. Not found in QuickBooks means the customer was deleted or merged there, so pick the right one. And a note if two accounts share one customer. Until an account is matched, its payments are held. Match it, and they go on the next run.', 10],
        ],
];
