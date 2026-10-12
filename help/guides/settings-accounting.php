<?php
declare(strict_types=1);

/**
 * Guide: settings-accounting — "QuickBooks live link (Beverley Blinds' own books)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * The slug stays settings-accounting (help topics and links point at it), but
 * since Oct 2026 the live link is for the platform's OWN books only: Factory
 * Console → Accounts → QuickBooks (master-admin/quickbooks.php, super-admin).
 * Companies get the downloads on Settings → Accounting (accounts-to-package).
 *
 * Sources: master-admin/quickbooks.php (page title + subtitle, the QuickBooks
 * Online card: Live / Sandbox (test) badge, Not set up / Not connected /
 * ● Connected / ● Needs reconnecting + its alert, the www note, Connect /
 * Reconnect to QuickBooks, Set up / Edit mapping, Disconnect + its confirm,
 * "Mapping: item … · VAT … · paid into …"; "How it will work" steps with
 * "Match customers →" and "(N linked so far)"; "Swap-over from Blind Matrix";
 * "Sending to QuickBooks": Off / ● On / ● On, but waiting, "Send invoices dated
 * from", "Sending on", Save, Send now + its hint, the Payments counts line;
 * "What's been sent"; "Log" with "Intuit ref"), admin/accounting/
 * quickbooks-mapping.php (for=factory: no record-as select, the Invoice +
 * Payment note, three selects, Save mapping, "← Back"), callback.php /
 * disconnect.php flash messages, _partials/qbo_push.php (blocker + held
 * messages, "Sending is on. Paid invoices dated from …").
 *
 * v2: one SCENE per script line (data-scene = the line's step).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$pageHead = '<div class="h1">QuickBooks</div>'
          . '<p class="sub">The live link to the company&rsquo;s own QuickBooks: invoices and payments go across once they&rsquo;re paid.</p>';

// The QuickBooks Online card. $state = right-hand status HTML, $inner = the rest.
$card = static fn (string $state, string $inner = '', string $badge = 'Live'): string =>
    '<div class="qcard"><div class="qtop"><div><span class="qname">QuickBooks Online</span><span class="qbadge">' . $badge . '</span></div>'
  . $state . '</div>' . $inner . '</div>';

$on  = '<span class="qstate on">&#9679; Connected</span>';
$linkedTo = '<p class="qtxt">Linked to <b>Beverley Blinds Ltd</b> <span class="qfaint">&middot; since 1 Jan 2027</span></p>';
$mapLine  = '<p class="qfaint" style="margin:.5rem 0 0">Mapping: item <b>Blinds</b> &middot; VAT <b>20.0% S</b> &middot; paid into <b>Current Account</b></p>';
$acts     = '<div class="qacts"><span class="btnp">Edit mapping</span><span class="btns">Disconnect</span></div>';

$howItWorks = static fn (string $match): string =>
    '<div class="qcard"><b class="ct">How it will work</b><ol class="steps2">'
  . '<li>Connect, and set up the mapping (sales item, VAT code, bank account).</li>'
  . '<li>Match each trade account to its customer already in QuickBooks, once. ' . $match . '</li>'
  . '<li>When a payment is confirmed (bank feed or recorded by hand), the invoice(s) it pays go across with one payment against them, matching the bank line. Nothing goes across before it&rsquo;s paid.</li>'
  . '</ol></div>';

$sendHint = '<p class="qtxt sm">When on, every payment recorded in YourBlinds (Bank screen or by hand) sends the invoice(s) it pays, then one payment against them, into QuickBooks. Voiding a payment or invoice here voids it there. Only invoices dated on or after the date below are sent &mdash; anything earlier (for example entered from Blind Matrix) is left alone, so nothing goes in twice.</p>';

// The "Sending to QuickBooks" box. $state = status HTML; $date/$tick/$save = inner bits.
$sending = static fn (string $state, string $date, string $tick, string $save, string $extra = ''): string =>
    '<div class="qcard"><div class="qtop"><b class="ct">Sending to QuickBooks</b>' . $state . '</div>' . $sendHint
  . '<div class="sform"><label class="dl">Send invoices dated from<span class="ib">' . $date . '</span></label>'
  . '<span class="ck">' . $tick . ' Sending on</span>' . $save . '</div>' . $extra . '</div>';

$swap = static fn (string $old, string $new, float $d): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $d . 's">' . $old . '</span><span class="a-fade" style="--d:' . ($d + .1) . 's">' . $new . '</span></span>';

$box   = '<span class="cb"></span>';
$boxOn = '<span class="cb on">&#10003;</span>';

return [
        'aud'     => 'super',
        'section' => 'Factory Console',
        'title'   => 'QuickBooks live link (Beverley Blinds\' own books)',
        'eyebrow' => 'Factory Console · QuickBooks',
        'v'       => 2,
        'blurb'   => 'Connect Beverley Blinds\' own QuickBooks, set the mapping, match customers, then switch sending on: paid invoices and their payment go across by themselves. Companies use the downloads instead.',
        'lede'    => 'The <b>QuickBooks</b> page is the <b>live link to Beverley Blinds&rsquo; own QuickBooks</b>. Once it&rsquo;s set up,
                      every payment confirmed in YourBlinds sends the <b>invoices it pays</b>, with <b>one payment</b> against them, so
                      QuickBooks matches the bank line. <b>Nothing goes across before it&rsquo;s paid.</b> Only super-admins see the page:
                      <b>Factory Console</b> &rarr; <b>Accounts</b> &rarr; <b>QuickBooks</b>. Other companies don&rsquo;t get this link;
                      they use the downloads on <b>Settings &rarr; Accounting</b> (see <b>&ldquo;Get your figures into Xero, QuickBooks or
                      Sage&rdquo;</b>).',
        'open'    => '/master-admin/quickbooks.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .h1{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .sub{ font-size:.68rem; color:var(--soft); margin:.1rem 0 .7rem; max-width:31rem; line-height:1.4; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .75rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .65rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.4rem .6rem; font-size:.7rem; font-weight:700; color:var(--ink); margin:0 0 .55rem; max-width:31rem; }
          .gd .bnr.err{ background:var(--err-wash,#fee2e2); border-left-color:var(--err); font-weight:600; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }

          /* cards */
          .gd .qcard{ border:1px solid var(--border-strong,#c7ccd4); border-radius:10px; padding:.6rem .75rem; background:var(--surface); max-width:31rem; margin-bottom:.55rem; }
          .gd .qtop{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
          .gd .qname{ font-weight:800; font-size:.84rem; color:var(--ink); }
          .gd .ct{ font-size:.78rem; color:var(--ink); }
          .gd .qbadge{ margin-left:.4rem; font-size:.6rem; padding:.08rem .38rem; border-radius:6px; background:var(--panel); color:var(--soft); font-weight:600; }
          .gd .qstate{ font-size:.68rem; color:var(--faint); }
          .gd .qstate.on{ color:#065f46; font-weight:700; }
          .gd .qstate.red{ color:#991b1b; font-weight:700; }
          .gd .qstate.amb{ color:#92400e; font-weight:700; }
          :root[data-theme="dark"] .gd .qstate.on{ color:#34d399; }
          .gd .qtxt{ font-size:.7rem; color:var(--soft); margin:.45rem 0 0; line-height:1.45; }
          .gd .qtxt.sm{ font-size:.6rem; }
          .gd .qtxt b{ color:var(--ink); }
          .gd .qfaint{ font-size:.64rem; color:var(--faint); }
          .gd .qfaint b{ color:var(--soft); }
          .gd .qacts{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.55rem; }
          .gd .steps2{ margin:.35rem 0 0; padding-left:1.1rem; font-size:.63rem; color:var(--soft); line-height:1.45; }
          .gd .steps2 li{ margin-bottom:.2rem; }
          .gd .lnk{ color:var(--accent); font-weight:700; }

          /* 2 — the flow */
          .gd .flow{ display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; margin:.8rem 0 .4rem; }
          .gd .node{ border:1px solid var(--line); border-radius:10px; padding:.45rem .6rem; background:var(--surface); font-size:.7rem; font-weight:700; color:var(--ink); text-align:center; }
          .gd .node small{ display:block; font-weight:500; color:var(--faint); font-size:.58rem; }
          .gd .arr{ font-size:1rem; color:var(--faint); }

          /* 3 — states */
          .gd .states{ display:grid; grid-template-columns:repeat(2,1fr); gap:.45rem; max-width:31rem; margin-top:.6rem; }
          .gd .st{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; font-size:.63rem; color:var(--soft); background:var(--surface); line-height:1.4; }
          .gd .st b{ display:block; font-size:.7rem; color:var(--ink); margin-bottom:.1rem; }

          /* 4 — before you start */
          .gd .prep{ display:grid; gap:.4rem; max-width:31rem; }
          .gd .prep div{ display:flex; gap:.55rem; align-items:center; border:1px solid var(--line); border-radius:9px; padding:.4rem .6rem; background:var(--surface); font-size:.72rem; color:var(--ink); }
          .gd .prep i{ font-style:normal; font-size:1.05rem; width:1.4rem; text-align:center; }
          .gd .prep small{ display:block; color:var(--faint); font-size:.6rem; }

          /* 5 — Intuit (their site) */
          .gd .ext{ border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:22rem; padding:.65rem .85rem; box-shadow:var(--gd-shadow); }
          .gd .ext .who{ font-size:.58rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; }
          .gd .ext h4{ margin:.25rem 0 .45rem; font-size:.86rem; color:var(--ink); }
          .gd .ext .grn{ display:block; text-align:center; background:#2ca01c; color:#fff; border-radius:7px; padding:.35rem; font-size:.72rem; font-weight:700; margin-top:.45rem; }
          .gd .co{ display:flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:8px; padding:.35rem .5rem; font-size:.72rem; margin:.25rem 0; color:var(--ink); }
          .gd .co .dot{ width:14px; height:14px; border-radius:50%; border:1.5px solid var(--border-strong,#c7ccd4); box-sizing:border-box; flex:0 0 auto; }
          .gd .lock{ font-size:.64rem; color:var(--soft); margin-top:.45rem; }

          /* 6 — mapping page */
          .gd .mphead{ display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; flex-wrap:wrap; margin-bottom:.45rem; }
          .gd .mf{ margin:0 0 .45rem; max-width:31rem; }
          .gd .mf label{ display:block; font-size:.66rem; font-weight:700; color:var(--ink); margin-bottom:.18rem; }
          .gd .req{ color:#b91c1c; }
          .gd .sb{ min-width:0; width:100%; box-sizing:border-box; font-size:.7rem; padding:.28rem .5rem; }
          .gd .sb .sw{ flex:1 1 auto; }
          .gd .mh{ font-size:.58rem; color:var(--faint); margin:.18rem 0 0; line-height:1.4; }
          .gd .ph{ color:var(--faint); }

          /* 8 — sending form */
          .gd .sform{ display:flex; gap:.5rem .8rem; align-items:flex-end; flex-wrap:wrap; margin-top:.5rem; }
          .gd .dl{ display:flex; flex-direction:column; gap:.2rem; font-size:.64rem; color:var(--soft); }
          .gd .ib{ display:flex; align-items:center; height:26px; min-width:8.5rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface);
                   padding:0 .45rem; font-size:.68rem; color:var(--ink); }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.7rem; color:var(--ink); padding-bottom:.35rem; }
          .gd .cb{ display:inline-grid; place-items:center; width:13px; height:13px; border:1.5px solid var(--border-strong,#c7ccd4); border-radius:3px; font-size:.55rem; color:#fff; box-sizing:border-box; }
          .gd .cb.on{ background:var(--accent); border-color:var(--accent); }

          /* 10/11 — tables */
          .gd .tbl{ width:100%; max-width:33rem; border-collapse:collapse; font-size:.6rem; color:var(--ink); }
          .gd .tbl th{ text-align:left; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); padding:.22rem .3rem; }
          .gd .tbl td{ border-bottom:1px solid var(--line); padding:.28rem .3rem; vertical-align:top; }
          .gd .pill{ display:inline-block; padding:0 .4rem; border-radius:999px; font-size:.58rem; font-weight:700; white-space:nowrap; }
          .gd .pill.ok{ background:#dcfce7; color:#166534; } .gd .pill.bad{ background:#fee2e2; color:#991b1b; }
          .gd .h2{ font-size:.82rem; font-weight:800; color:var(--ink); margin:.2rem 0 .2rem; }

          /* 12 — confirm */
          .gd .confirm{ position:absolute; z-index:5; left:8%; top:40%; max-width:21rem; background:var(--surface); border:1px solid var(--line);
                        border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.72rem; color:var(--ink); }
          .gd .confirm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:470px; }
            .gd .states{ grid-template-columns:1fr; }
            .gd .confirm{ left:0; top:50%; }
            .gd .tbl .hide{ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / master-admin / quickbooks</span></div>
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
                  ' . $pageHead . '
                  ' . $card($on, $linkedTo . $acts . $mapLine) . '
                  ' . $howItWorks('<span class="lnk">Match customers &rarr;</span> <span class="qfaint">(42 linked so far)</span>') . '
                  <p class="scs">Press <b>&#9654; Play</b> below &mdash; twelve short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — the page -->
                <div class="sc" data-scene="1" data-len="21">
                  <div class="a-fade" style="--d:.2s">' . $pageHead . '</div>
                  <div class="a-rise" style="--d:5s">' . $card($on, $linkedTo . $acts) . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3s">Factory Console &rarr; Accounts &rarr; QuickBooks</span>
                    <span class="chip ok a-pop" style="--d:7s">Super-admins only</span>
                    <span class="chip a-pop" style="--d:1.5s">Beverley Blinds&rsquo; own books</span>
                    <span class="chip bad a-pop" style="--d:14s">Other companies: Settings &rarr; Accounting &rarr; downloads</span>
                  </div>
                </div>

                <!-- 2 — paid first -->
                <div class="sc" data-scene="2" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">Nothing goes across until it&rsquo;s paid</div>
                  <div class="flow">
                    <div class="node a-pop" style="--d:6.5s">Payment confirmed<small>Bank screen, or by hand</small></div>
                    <span class="arr a-fade" style="--d:10s">&rarr;</span>
                    <div class="node a-pop" style="--d:11s">Invoice(s) it pays<small>+ one payment</small></div>
                    <span class="arr a-fade" style="--d:13s">&rarr;</span>
                    <div class="node a-pop" style="--d:13.5s">QuickBooks<small>Beverley Blinds Ltd</small></div>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:15.5s">&#10003; One payment = one bank line</span>
                    <span class="chip a-pop" style="--d:18.5s">Ticks off easily in QuickBooks</span>
                  </div>
                </div>

                <!-- 3 — badge + states -->
                <div class="sc" data-scene="3" data-len="21">
                  <div class="a-rise" style="--d:.5s">' . $card('<span class="qstate">Not connected</span>', '', '<span class="a-ring" style="--d:5s;border-radius:6px">Live</span>') . '</div>
                  <div class="chips" style="margin-top:0">
                    <span class="chip ok a-pop" style="--d:6.5s">Live = the real company</span>
                    <span class="chip a-pop" style="--d:8s">Sandbox (test) = a test company</span>
                  </div>
                  <div class="states">
                    <div class="st a-rise" style="--d:10.5s"><b>Not set up</b>The app keys aren&rsquo;t in yet.</div>
                    <div class="st a-rise" style="--d:11.5s"><b>Not connected</b>Ready &mdash; the Connect button is there.</div>
                    <div class="st a-rise" style="--d:12.5s"><b style="color:#065f46">&#9679; Connected</b>Linked to the company.</div>
                    <div class="st a-rise" style="--d:13.5s"><b style="color:#991b1b">&#9679; Needs reconnecting</b>Nothing is sent until you reconnect.</div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:17.5s">Keys: see &ldquo;Set up the accounting app keys&rdquo;</span></div>
                </div>

                <!-- 4 — before you start -->
                <div class="sc" data-scene="4" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Before you start &mdash; five minutes in QuickBooks</div>
                  <div class="prep">
                    <div class="a-fly" style="--d:3s"><i>&#128273;</i><span>Be an <b>admin</b> on the QuickBooks company</span></div>
                    <div class="a-fly" style="--d:6.5s"><i>&#9998;</i><span>One sales item called <b>Blinds</b><small>Pointing at your sales / income account.</small></span></div>
                    <div class="a-fly" style="--d:11.5s"><i>%</i><span>VAT codes there, such as <b>20.0% S</b></span></div>
                    <div class="a-fly" style="--d:14.5s"><i>&#127974;</i><span>Know which <b>bank account</b> the money lands in</span></div>
                  </div>
                </div>

                <!-- 5 — connect -->
                <div class="sc" data-scene="5" data-len="21">
                  <div class="a-mid" style="--d:.3s;--d2:4.5s">' . $card('<span class="qstate">Not connected</span>', '<div class="qacts"><span class="btnp a-press" style="--d:3s">Connect to QuickBooks</span></div>') . '
                    <div class="a-move" style="--fx:60%;--fy:80%;--tx:10%;--ty:2.6rem;--d:1s;--md:1.6s">' . $ptr . '</div></div>
                  <div class="a-mid" style="--d:4.5s;--d2:11.8s;position:absolute;left:0;top:0;width:100%">
                    <div class="ext">
                      <div class="who">Intuit &middot; not YourBlinds</div>
                      <h4>Which company?</h4>
                      <div class="co"><span class="dot a-sel" style="--d:9.5s"></span>Beverley Blinds Ltd</div>
                      <div class="co"><span class="dot"></span>Sandbox Company GB</div>
                      <span class="grn a-press" style="--d:11s">Connect</span>
                      <div class="lock a-fade" style="--d:6s">&#128274; Your QuickBooks password goes to QuickBooks only.</div>
                    </div>
                  </div>
                  <div class="a-fade" style="--d:12s;position:absolute;left:0;top:0;width:100%">
                    <div class="bnr">&#10003; Connected to QuickBooks Online (Beverley Blinds Ltd).</div>
                    ' . $card('<span class="qstate on">&#9679; Connected</span>', $linkedTo . '<div class="qacts"><span class="btnp">Set up mapping</span><span class="btns">Disconnect</span></div>') . '
                    <p class="qfaint a-fade" style="--d:17s">Sent to www.yourblinds.uk first? That&rsquo;s normal &mdash; QuickBooks sends you back there.</p>
                  </div>
                </div>

                <!-- 6 — mapping -->
                <div class="sc" data-scene="6" data-len="21">
                  <div class="bnr a-drop" style="--d:20.3s">&#10003; QuickBooks mapping saved.</div>
                  <div class="mphead"><div><div class="h1">QuickBooks mapping</div>
                    <p class="sub" style="margin:0">Tell YourBlinds where paid sales should post in <b>Beverley Blinds Ltd</b>.</p></div>
                    <span class="btns">&larr; Back</span></div>
                  <p class="sub a-ring" style="--d:6s">Paid sales go on as an <b>Invoice</b> with a <b>Payment</b> against it, once the money is in.</p>
                  <div class="mf"><label>Sales item <span class="req">*</span></label><span class="selectbox sb">' . $swap('<span class="ph">&mdash; Select an item &mdash;</span>', 'Blinds', 10.5) . '</span></div>
                  <div class="mf"><label>Default VAT / tax code</label><span class="selectbox sb">' . $swap('<span class="ph">&mdash; Select a tax code &mdash;</span>', '20.0% S', 13) . '</span></div>
                  <div class="mf"><label>Bank account paid sales land in</label><span class="selectbox sb">' . $swap('<span class="ph">&mdash; Select an account &mdash;</span>', 'Current Account', 17) . '</span></div>
                  <span class="btnp a-press" style="--d:19.5s">Save mapping</span>
                </div>

                <!-- 7 — match customers -->
                <div class="sc" data-scene="7" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Match each trade account, once</div>
                  <div class="a-rise" style="--d:1s">' . $howItWorks('<span class="lnk a-ring" style="--d:12.5s">Match customers &rarr;</span> <span class="qfaint">(0 linked so far)</span>') . '</div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:8s">Done once &mdash; never duplicated</span>
                    <span class="chip a-pop" style="--d:14.5s">The office: &ldquo;QuickBooks customers&rdquo; in their menu</span>
                    <span class="chip a-pop" style="--d:17.5s">Own guide: &ldquo;Match customers&rdquo;</span>
                  </div>
                </div>

                <!-- 8 — sending on -->
                <div class="sc" data-scene="8" data-len="20">
                  <div class="bnr a-drop" style="--d:17.3s">&#10003; Sending is on. Paid invoices dated from 1 Jan 2027 will go to QuickBooks.</div>
                  ' . $sending(
                        $swap('<span class="qstate">Off</span>', '<span class="qstate on">&#9679; On</span>', 17.5),
                        '<span class="a-type" style="--d:4s;--ts:10;--tt:.8s">01/01/2027</span>',
                        $swap($box, $boxOn, 15),
                        '<span class="btnp a-press" style="--d:16.5s">Save</span>'
                    ) . '
                  <div class="chips" style="margin-top:0"><span class="chip a-pop" style="--d:9s">Earlier invoices (Blind Matrix) are left alone</span></div>
                </div>

                <!-- 9 — runs by itself -->
                <div class="sc" data-scene="9" data-len="21.5">
                  ' . $sending('<span class="qstate on">&#9679; On</span>', '01/01/2027', $boxOn, '<span class="btnp">Save</span>',
                        '<div class="qacts"><span class="btns a-ring" style="--d:9s">Send now</span><span class="qfaint" style="align-self:center">It also runs by itself after each payment and every 15 minutes, retrying anything that failed.</span></div>') . '
                  <div class="chips" style="margin-top:0">
                    <span class="chip ok a-pop" style="--d:2s">After each payment</span>
                    <span class="chip ok a-pop" style="--d:4s">Every 15 minutes</span>
                    <span class="chip a-pop" style="--d:12s">Void here = void there</span>
                    <span class="chip bad a-pop" style="--d:16s">&#9679; On, but waiting &rarr; read the line under it</span>
                  </div>
                </div>

                <!-- 10 — what has been sent -->
                <div class="sc" data-scene="10" data-len="20">
                  <p class="qfaint a-fade" style="--d:.3s">Payments: 12 sent &middot; 1 held &middot; 0 failed &middot; 0 nothing to send</p>
                  <div class="h2 a-fade" style="--d:1s">What&rsquo;s been sent</div>
                  <p class="qfaint a-fade" style="--d:1.5s;margin:0 0 .35rem">Held and failed ones first, with the reason. Fix the reason (for example match the customer) and it goes on the next run.</p>
                  <table class="tbl">
                    <tr><th>Item</th><th>Status</th><th>Details</th><th class="hide">When</th></tr>
                    <tr class="a-fly" style="--d:4s"><td>Payment <b>PAY-2027-0013</b></td><td><span class="pill bad a-ring" style="--d:6s">held</span></td>
                      <td>Match &ldquo;Brightside Blinds&rdquo; to its QuickBooks customer first (QuickBooks &rarr; Match customers).</td><td class="hide">5 Jan 09:12</td></tr>
                    <tr class="a-fly" style="--d:5s"><td>Payment <b>PAY-2027-0012</b></td><td><span class="pill ok">sent</span></td><td></td><td class="hide">5 Jan 09:02</td></tr>
                    <tr class="a-fly" style="--d:5.6s"><td>Invoice <b>INV-BEV-2027-0040</b></td><td><span class="pill ok">sent</span></td><td></td><td class="hide">5 Jan 09:02</td></tr>
                  </table>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:7.5s">Held = waiting for you</span>
                    <span class="chip ok a-pop" style="--d:11s">Match it &rarr; goes on the next run</span>
                    <span class="chip a-pop" style="--d:14.5s">Failed &rarr; retries by itself</span>
                  </div>
                </div>

                <!-- 11 — reconnect + log -->
                <div class="sc" data-scene="11" data-len="21">
                  <div class="a-rise" style="--d:.5s">' . $card('<span class="qstate red a-pop" style="--d:5s">&#9679; Needs reconnecting</span>',
                        '<div class="bnr err" style="margin:.45rem 0 0">QuickBooks has stopped accepting the link (it happens after about 100 days unused, or if someone disconnected YourBlinds inside QuickBooks). Nothing can be sent until you reconnect.</div>'
                      . '<div class="qacts"><span class="btnp a-ring" style="--d:9.5s">Reconnect to QuickBooks</span><span class="btns">Disconnect</span></div>') . '</div>
                  <div class="h2 a-fade" style="--d:12.8s">Log</div>
                  <table class="tbl a-fade" style="--d:13s">
                    <tr><th>When</th><th>What</th><th>Result</th><th>Intuit ref</th></tr>
                    <tr><td>5 Jan 09:02</td><td>send payment</td><td><span class="pill ok">ok</span></td><td class="a-ring" style="--d:17.6s">1-6f2c&hellip;</td></tr>
                  </table>
                </div>

                <!-- 12 — disconnect -->
                <div class="sc" data-scene="12" data-len="18">
                  <div class="a-rise" style="--d:.5s">' . $card($on, $linkedTo . '<div class="qacts"><span class="btnp">Edit mapping</span><span class="btns a-ring" style="--d:2.5s">Disconnect</span></div>') . '</div>
                  <div class="confirm a-mid" style="--d:4s;--d2:9s">Disconnect QuickBooks? Nothing more will be sent until you reconnect.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp">OK</span></div></div>
                  <div class="chips" style="margin-top:4.5rem">
                    <span class="chip a-pop" style="--d:7s">Nothing already in QuickBooks is touched</span>
                    <span class="chip ok a-pop" style="--d:12s">A different QuickBooks company starts clean</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <div class="heads"><span class="hi">&#9432;</span><div><b>Who this is for.</b> The live QuickBooks link is for
             <b>Beverley Blinds&rsquo; own books</b> only, and only super-admins see the page. Other companies on YourBlinds
             don&rsquo;t get it (yet): they use <b>Download for QuickBooks / Xero / Sage</b> on <b>Settings &rarr; Accounting</b>
             &mdash; see <b>&ldquo;Get your figures into Xero, QuickBooks or Sage&rdquo;</b>.</div></div>

          <p><b>How it works.</b> Nothing goes to QuickBooks until it&rsquo;s paid (cash accounting). When a payment is confirmed
             &mdash; on the <b>Bank</b> screen, or recorded by hand &mdash; the invoice(s) it pays go across, then <b>one payment</b>
             against them, into the bank account you chose. That one payment matches the line on the bank statement, so it ticks
             off easily in QuickBooks.</p>

          <p><b>Where it is.</b> <b>Factory Console</b> &rarr; <b>Accounts</b> &rarr; <b>QuickBooks</b>. The page is headed
             <b>QuickBooks</b>, &ldquo;The live link to the company&rsquo;s own QuickBooks: invoices and payments go across once
             they&rsquo;re paid.&rdquo;</p>

          <p><b>The card.</b> The <b>QuickBooks Online</b> card has a small badge: <b>Live</b> (the real company) or <b>Sandbox
             (test)</b>. On the right it says:</p>
          <ul class="steps">
            <li><b>Not set up</b> &mdash; &ldquo;The QuickBooks app keys aren&rsquo;t in yet.&rdquo; See the guide <b>&ldquo;Set up
                the accounting app keys (platform owner)&rdquo;</b>.</li>
            <li><b>Not connected</b> &mdash; ready, with a <b>Connect to QuickBooks</b> button.</li>
            <li><b>&#9679; Connected</b> &mdash; &ldquo;Linked to <i>company</i> &middot; since <i>date</i>&rdquo;, with
                <b>Set up mapping</b> (later <b>Edit mapping</b>) and <b>Disconnect</b>.</li>
            <li><b>&#9679; Needs reconnecting</b> &mdash; QuickBooks has stopped accepting the link (after about 100 days unused, or
                if someone disconnected YourBlinds inside QuickBooks). <b>Nothing can be sent</b> until you press
                <b>Reconnect to QuickBooks</b>.</li>
          </ul>

          <p><b>Before you start &mdash; in QuickBooks.</b> Be an <b>admin</b> on the company. Have one sales item called
             <code>Blinds</code>, pointing at your sales / income account. Check VAT is on, so <code>20.0% S</code> exists. Know which
             <b>bank account</b> the money lands in.</p>

          <p><b>1. Connect.</b></p>
          <ul class="steps">
            <li>Press <b>Connect to QuickBooks</b>. If the card says <em>&ldquo;Connecting starts on www.yourblinds.uk (QuickBooks
                sends you back there)&rdquo;</em>, the button takes you to that address first &mdash; sign in again if it asks.</li>
            <li>You land on QuickBooks&rsquo; own page. Sign in, pick the right company and approve. YourBlinds never sees the
                QuickBooks password.</li>
            <li>You come back with <b>&ldquo;Connected to QuickBooks Online (<i>company</i>).&rdquo;</b> and the card says
                <b>&#9679; Connected</b>.</li>
          </ul>

          <p><b>2. Mapping.</b> Press <b>Set up mapping</b>. The page <b>QuickBooks mapping</b> says &ldquo;Paid sales go on as an
             <b>Invoice</b> with a <b>Payment</b> against it, once the money is in.&rdquo; Every list comes from your QuickBooks:</p>
          <ul class="steps">
            <li><b>Sales item *</b> &mdash; <b>Blinds</b>.</li>
            <li><b>Default VAT / tax code</b> &mdash; usually <b>20.0% S</b>.</li>
            <li><b>Bank account paid sales land in</b> &mdash; usually the current account.</li>
            <li>Press <b>Save mapping</b> (&ldquo;QuickBooks mapping saved.&rdquo;), then <b>&larr; Back</b>. The card shows
                <b>Mapping: item Blinds &middot; VAT 20.0% S &middot; paid into &hellip;</b></li>
          </ul>

          <p><b>3. Match customers.</b> Under <b>How it will work</b>, click <b>Match customers &rarr;</b> (it shows how many are
             linked so far). Each trade account is paired once with its customer already in QuickBooks, so nothing is ever
             duplicated. The office can do this too, from <b>QuickBooks customers</b> in their menu. See the guide
             <b>&ldquo;Match customers (QuickBooks)&rdquo;</b>.</p>

          <p><b>4. Switch sending on.</b> In <b>Sending to QuickBooks</b>:</p>
          <ul class="steps">
            <li><b>Send invoices dated from</b> &mdash; only invoices dated on or after this date are sent. Anything earlier (for
                example invoices from Blind Matrix) is left alone, so nothing goes in twice. For the swap-over, use the swap-over
                date.</li>
            <li>Tick <b>Sending on</b> and press <b>Save</b>. You&rsquo;ll see <b>&ldquo;Sending is on. Paid invoices dated from
                <i>date</i> will go to QuickBooks.&rdquo;</b> and the label changes from <b>Off</b> to <b>&#9679; On</b>.</li>
            <li>It then runs by itself after each payment and every 15 minutes, retrying anything that failed. <b>Send now</b> runs
                it straight away.</li>
            <li>Voiding a payment or invoice in YourBlinds voids it in QuickBooks too.</li>
            <li><b>&#9679; On, but waiting</b> means something is missing; the line underneath says what, for example
                &ldquo;Set up the mapping first (sales item, VAT code and bank account).&rdquo; or &ldquo;Choose the date to send
                invoices from.&rdquo;</li>
            <li>To stop, untick <b>Sending on</b> and <b>Save</b>: &ldquo;Sending is off. Nothing will be sent to QuickBooks.&rdquo;</li>
          </ul>

          <p><b>What&rsquo;s been sent.</b> The Sending box counts payments <b>sent &middot; held &middot; failed &middot; nothing to
             send</b>. The table below lists them, held and failed first, with the reason:</p>
          <ul class="steps">
            <li><b>held</b> &mdash; waiting for you. Usually &ldquo;Match &lsquo;<i>account</i>&rsquo; to its QuickBooks customer
                first (QuickBooks &rarr; Match customers).&rdquo; Match it and it goes on the next run. A payment against an invoice
                that has a <b>credit note</b> is held too: enter that one in QuickBooks by hand.</li>
            <li><b>failed &mdash; will retry</b> &mdash; QuickBooks didn&rsquo;t take it; it tries again by itself.</li>
            <li><b>sent</b>, with the QuickBooks id. If a payment was bigger than the invoices it paid, the rest stays as credit in
                YourBlinds and isn&rsquo;t sent.</li>
          </ul>

          <p><b>Log.</b> At the bottom, every connection, send and problem. If Intuit support ever asks, give them the
             <b>Intuit ref</b> from here.</p>

          <p><b>Disconnecting.</b> Press <b>Disconnect</b>: &ldquo;Disconnect QuickBooks? Nothing more will be sent until you
             reconnect.&rdquo; Nothing already in QuickBooks is touched. Matches and the sent list belong to that QuickBooks company, so
             connecting a different company (say, from a sandbox to the real one) starts clean.</p>

          <p><b>Swap-over from Blind Matrix.</b> The card <b>Swap-over from Blind Matrix</b> links to
             <b>Swap-over: Blind Matrix balances</b>, which brings every unpaid Blind Matrix invoice into YourBlinds &mdash; see that
             guide.</p>

          <p><b>If something goes wrong when connecting.</b></p>
          <ul class="steps">
            <li><b>&ldquo;Security check failed on the accounting connection (state mismatch).&rdquo;</b> &mdash; it started on one
                address and finished on another (with and without <code>www</code>). Go back to the page on
                <code>www.yourblinds.uk</code> and connect again.</li>
            <li><b>&ldquo;The connection took too long and expired.&rdquo;</b> &mdash; start again.</li>
            <li><b>&ldquo;Connection cancelled or refused by the provider (&hellip;)&rdquo;</b> &mdash; Cancel was pressed at
                QuickBooks, or you&rsquo;re not an admin there.</li>
            <li><b>&ldquo;Couldn&rsquo;t load lists from QuickBooks: &hellip;&rdquo;</b> on the mapping page &mdash; try again in a
                minute; if it keeps happening, reconnect.</li>
          </ul>',
        'script'  => [
            ['1',  'The QuickBooks page',        'This guide is for Beverley Blinds\' own books. In the Factory Console menu, under Accounts, open QuickBooks. Only super admins see it. It is the live link to the company\'s QuickBooks. Other companies do not get this link. They use the downloads in Settings, on the Accounting tab.', 1],
            ['2',  'Paid first, then sent',      'Here is the idea. Nothing goes to QuickBooks until it is paid. When a payment is confirmed, on the Bank screen or recorded by hand, the invoices it pays go across, with one payment against them. That payment matches the line on the bank statement, so it ticks off easily.', 2],
            ['3',  'The card and its badge',     'At the top is the QuickBooks Online card. The little badge says Live for the real company, or Sandbox for a test one. On the right it says Not set up, Not connected, Connected, or Needs reconnecting. Not set up means the app keys are missing. The platform owner guide covers those.', 3],
            ['4',  'Before you start',           'Before connecting, spend five minutes in QuickBooks. Make sure you are an admin on the company. Have one sales item, called Blinds, pointing at your sales account. Check the VAT codes are there. And know which bank account the money lands in.', 4],
            ['5',  'Connect to QuickBooks',      'Press Connect to QuickBooks. You land on QuickBooks\' own page, so YourBlinds never sees the password. Sign in, pick the right company, and press Connect. You come straight back, with a green bar, and the card says Connected. If it sends you to the www address first, that is normal.', 5],
            ['6',  'The mapping',                'Next, press Set up mapping. Every list here comes from your own QuickBooks. Paid sales always go on as an invoice, with a payment against it. Choose the sales item, Blinds. Then the VAT code, normally twenty percent standard. Then the bank account the money lands in. Press Save mapping.', 6],
            ['7',  'Match customers',            'Each trade account must be paired with its customer already in QuickBooks. It is done once, so nothing is ever duplicated. Under How it will work, click Match customers. The office can do this too, from QuickBooks customers in their menu. It has its own guide.', 7],
            ['8',  'Switch sending on',          'Now the Sending to QuickBooks box. First, fill in Send invoices dated from. Only invoices on or after that date are sent. Anything earlier, such as invoices from Blind Matrix, is left alone, so nothing goes in twice. Then tick Sending on, and press Save. Off changes to On.', 8],
            ['9',  'It runs by itself',          'From then on, it runs by itself, after each payment, and every fifteen minutes, retrying anything that failed. Send now runs it straight away. Voiding a payment or an invoice here voids it in QuickBooks too. If it says On, but waiting, read the line underneath. It tells you what is missing.', 9],
            ['10', 'What has been sent',         'Below is What\'s been sent. Held and failed ones come first, with the reason. Held usually means the customer is not matched yet. Match it, and it goes on the next run. Failed ones simply retry by themselves. The Sending box counts how many were sent, held and failed.', 10],
            ['11', 'Reconnecting, and the log',  'QuickBooks drops the link after about a hundred days unused. The card then says Needs reconnecting, and nothing is sent. Press Reconnect to QuickBooks to fix it. The Log at the bottom lists every connection and send. If Intuit support ever asks, give them the Intuit ref from there.', 11],
            ['12', 'Disconnecting',              'To unlink, press Disconnect, and say OK. Nothing more is sent until you connect again, and nothing already in QuickBooks is touched. Matches and the sent list belong to that QuickBooks company, so connecting a different one starts clean.', 12],
        ],
];
