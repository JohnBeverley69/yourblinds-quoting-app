<?php
declare(strict_types=1);

/**
 * Guide: settings-accounting — "Link your accounts package (QuickBooks Online)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Sources: admin/settings.php (Accounting panel — "Accounting integration"
 * heading + hint, the QuickBooks Online card: Live / Sandbox (test) badge,
 * ● Connected / Not connected / Not set up, "Linked to … · since …",
 * "Next: map your sales item…", "Mapping set: …", Set up / Edit mapping,
 * Disconnect + its confirm, "Xero & Sage — Coming soon"),
 * admin/accounting/connect.php, callback.php (flash messages), disconnect.php,
 * quickbooks-mapping.php (four fields, hints, "QuickBooks mapping saved.",
 * "← Back to Accounting"). The Intuit screens are Intuit's, not ours, so
 * they are drawn plainly and described in general terms.
 *
 * KEEP TRUE: the push of paid sales into QuickBooks is NOT built (the
 * integration is on hold). Connecting + mapping only prepares it; say so and
 * never promise automatic syncing.
 *
 * v2: one SCENE per script line (data-scene = the line's step).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$tabs = '<div class="sttabs"><span class="sttab">Company</span><span class="sttab">Quoting</span><span class="sttab">Legal</span>'
      . '<span class="sttab">Status colours</span><span class="sttab">Suppliers</span><span class="sttab on">Accounting</span><span class="sttab">Back up data</span></div>';

$hint = '<p class="hnt">Connect your accounting package and set up the mapping now, so it&rsquo;s ready. You connect on the provider&rsquo;s own site, so YourBlinds never sees your accounting password. <b>Sending sales across isn&rsquo;t switched on yet</b> &mdash; until it is, use the CSV exports on the Payments page. When it is, nothing will be sent until an invoice is paid (cash accounting).</p>';

// The QuickBooks Online card head: $state is the right-hand status HTML.
$cardHead = static fn (string $state, string $badge = 'Live'): string =>
    '<div class="qtop"><div><span class="qname">QuickBooks Online</span><span class="qbadge">' . $badge . '</span></div>' . $state . '</div>';

$notConnected = '<p class="qtxt">Connect your QuickBooks company to get started. You&rsquo;ll sign in at QuickBooks and approve access.</p>';
$xero = '<div class="xs"><b>Xero &amp; Sage</b> <span>Coming soon &mdash; same one-click connect.</span></div>';

// Mapping page form: $v = values (HTML) for the four selects.
$mapForm = static fn (array $v): string =>
    '<div class="mf"><label>How to record a paid sale</label><span class="selectbox sb">' . $v[0] . '</span>'
  . '<p class="mh">You only put paid sales on QuickBooks (cash accounting), so a Sales Receipt is usually the cleanest.</p></div>'
  . '<div class="mf"><label>Sales item <span class="req">*</span></label><span class="selectbox sb">' . $v[1] . '</span>'
  . '<p class="mh">QuickBooks needs an item on each line &mdash; a single &ldquo;Blinds&rdquo; sales item is fine; it points at your income account.</p></div>'
  . '<div class="mf"><label>Default VAT / tax code</label><span class="selectbox sb">' . $v[2] . '</span></div>'
  . '<div class="mf"><label>Bank account paid sales land in</label><span class="selectbox sb">' . $v[3] . '</span>'
  . '<p class="mh">Where the money shows as received &mdash; usually your current account (or Undeposited Funds if payments arrive batched).</p></div>';

$mapHead = '<div class="mphead"><div><div class="h2" style="margin:0">QuickBooks mapping</div>'
         . '<p class="hnt" style="margin:.1rem 0 0">Tell YourBlinds where paid sales should post in <b>Demo Blinds Ltd</b>.</p></div>'
         . '<span class="btns">&larr; Back to Accounting</span></div>';

$swap = static fn (string $old, string $new, float $d): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $d . 's">' . $old . '</span><span class="a-fade" style="--d:' . ($d + .1) . 's">' . $new . '</span></span>';

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Link your accounts package (QuickBooks Online)',
        'eyebrow' => 'Settings · Accounting',
        'v'       => 2,
        'blurb'   => 'Connect your QuickBooks Online company and tell YourBlinds which item, VAT code and bank account paid sales belong to. Sending sales across is not switched on yet — use the CSV exports for now.',
        'lede'    => 'The <b>Accounting</b> tab links YourBlinds to <b>your own QuickBooks Online</b> company. You sign in on
                      <b>QuickBooks&rsquo; own website</b> &mdash; YourBlinds never sees your QuickBooks password &mdash; and then answer
                      <b>four questions</b> about where paid sales should go. <b>Please read this first:</b> today the tab
                      <b>connects and maps only</b>. The part that sends paid sales into QuickBooks is <b>not switched on yet</b>, so
                      keep using the CSV exports on the <b>Payments</b> page (see <b>&ldquo;Get your figures into Xero, QuickBooks or
                      Sage&rdquo;</b>). To get there: <b>Setup</b> &rarr; <b>Settings</b> &rarr; the <b>Accounting</b> tab.',
        'open'    => '/admin/settings.php#accounting',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .sttabs{ display:flex; flex-wrap:wrap; gap:.1rem .2rem; border-bottom:1px solid var(--line); margin:0 0 .7rem; }
          .gd .sttab{ font-size:.66rem; font-weight:600; color:var(--faint); padding:.28rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; border-radius:6px 6px 0 0; }
          .gd .sttab.on{ color:var(--accent); border-bottom-color:var(--accent); }
          .gd .h2{ font-size:.9rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .hnt{ font-size:.68rem; color:var(--soft); line-height:1.45; margin:0 0 .7rem; max-width:31rem; }
          .gd .hnt b{ color:var(--ink); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.36rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.32rem .7rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; max-width:31rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }

          /* the QuickBooks Online card */
          .gd .qcard{ border:1px solid var(--border-strong,#c7ccd4); border-radius:10px; padding:.65rem .8rem; background:var(--surface); max-width:31rem; }
          .gd .qtop{ display:flex; align-items:center; justify-content:space-between; gap:.6rem; flex-wrap:wrap; }
          .gd .qname{ font-weight:800; font-size:.86rem; color:var(--ink); }
          .gd .qbadge{ margin-left:.4rem; font-size:.6rem; padding:.08rem .38rem; border-radius:6px; background:var(--panel); color:var(--soft); font-weight:600; }
          .gd .qstate{ font-size:.7rem; color:var(--faint); }
          .gd .qstate.on{ color:#065f46; font-weight:700; }
          :root[data-theme="dark"] .gd .qstate.on{ color:#34d399; }
          .gd .qtxt{ font-size:.72rem; color:var(--soft); margin:.5rem 0 .6rem; line-height:1.45; }
          .gd .qtxt b{ color:var(--ink); }
          .gd .qfaint{ font-size:.66rem; color:var(--faint); margin:.35rem 0 .2rem; }
          .gd .qacts{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.55rem; }
          .gd .xs{ border:1px dashed var(--line); border-radius:10px; padding:.5rem .8rem; max-width:31rem; margin-top:.6rem; font-size:.72rem; color:var(--soft); }
          .gd .xs span{ color:var(--faint); margin-left:.3rem; }

          /* 2 — not yet */
          .gd .flow{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin:.9rem 0 .4rem; }
          .gd .node{ border:1px solid var(--line); border-radius:10px; padding:.5rem .7rem; background:var(--surface); font-size:.74rem; font-weight:700; color:var(--ink); text-align:center; }
          .gd .node small{ display:block; font-weight:500; color:var(--faint); font-size:.6rem; }
          .gd .pipe{ position:relative; width:5.5rem; height:4px; background:var(--line); border-radius:4px; }
          .gd .pipe .gap{ position:absolute; left:50%; top:-9px; transform:translateX(-50%); font-size:.95rem; color:var(--err); font-weight:800; line-height:1; }
          .gd .csv{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.4rem; }

          /* 3 — badges */
          .gd .states{ display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; max-width:31rem; margin-top:.8rem; }
          .gd .st{ border:1px solid var(--line); border-radius:9px; padding:.45rem .55rem; font-size:.66rem; color:var(--soft); background:var(--surface); line-height:1.4; }
          .gd .st b{ display:block; font-size:.72rem; color:var(--ink); margin-bottom:.15rem; }

          /* 4 — before you start */
          .gd .prep{ display:grid; gap:.45rem; max-width:31rem; }
          .gd .prep div{ display:flex; gap:.55rem; align-items:center; border:1px solid var(--line); border-radius:9px; padding:.45rem .6rem; background:var(--surface); font-size:.74rem; color:var(--ink); }
          .gd .prep i{ font-style:normal; font-size:1.05rem; width:1.4rem; text-align:center; }
          .gd .prep small{ display:block; color:var(--faint); font-size:.62rem; }

          /* 5/6 — Intuit (their site) */
          .gd .ext{ border:1px solid var(--line); border-radius:12px; background:var(--surface); max-width:22rem; padding:.75rem .9rem; box-shadow:var(--gd-shadow); }
          .gd .ext .who{ font-size:.58rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; }
          .gd .ext h4{ margin:.3rem 0 .55rem; font-size:.9rem; color:var(--ink); }
          .gd .ext .fbox{ height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; margin:.3rem 0; display:flex; align-items:center; padding:0 .5rem; font-size:.72rem; color:var(--ink); }
          .gd .ext .grn{ display:block; text-align:center; background:#2ca01c; color:#fff; border-radius:7px; padding:.4rem; font-size:.74rem; font-weight:700; margin-top:.5rem; }
          .gd .urlbar{ display:inline-flex; gap:.35rem; align-items:center; font-family:ui-monospace,Menlo,monospace; font-size:.66rem; color:var(--soft);
                       border:1px solid var(--line); background:var(--panel); border-radius:999px; padding:.2rem .65rem; margin-bottom:.6rem; }
          .gd .co{ display:flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:8px; padding:.4rem .55rem; font-size:.74rem; margin:.3rem 0; color:var(--ink); }
          .gd .co .dot{ width:14px; height:14px; border-radius:50%; border:1.5px solid var(--border-strong,#c7ccd4); box-sizing:border-box; flex:0 0 auto; }
          .gd .co .dot.a-sel{ border-radius:50%; }
          .gd .lock{ font-size:.66rem; color:var(--soft); margin-top:.5rem; }

          /* 8/9 — the mapping page */
          .gd .mphead{ display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; flex-wrap:wrap; margin-bottom:.55rem; }
          .gd .mf{ margin:0 0 .5rem; max-width:31rem; }
          .gd .mf label{ display:block; font-size:.68rem; font-weight:700; color:var(--ink); margin-bottom:.2rem; }
          .gd .sb{ min-width:0; width:100%; box-sizing:border-box; font-size:.72rem; padding:.3rem .5rem; }
          .gd .sb .sw{ flex:1 1 auto; }
          .gd .mh{ font-size:.6rem; color:var(--faint); margin:.2rem 0 0; line-height:1.4; }
          .gd .ddl{ position:absolute; z-index:5; border:1px solid var(--line); border-radius:8px; background:var(--surface); box-shadow:var(--gd-shadow);
                    font-size:.68rem; min-width:15rem; overflow:hidden; }
          .gd .ddl div{ padding:.3rem .55rem; color:var(--ink); }
          .gd .ddl div.hi{ background:var(--accent-wash); }
          .gd .ph{ color:var(--faint); }

          /* 10 — disconnect confirm */
          .gd .confirm{ position:absolute; z-index:5; left:8%; top:45%; max-width:21rem; background:var(--surface); border:1px solid var(--line);
                        border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.72rem; color:var(--ink); }
          .gd .confirm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:440px; }
            .gd .states{ grid-template-columns:1fr; }
            .gd .sttab{ font-size:.6rem; padding:.22rem .3rem; }
            .gd .pipe{ width:2.5rem; }
            .gd .confirm{ left:0; top:55%; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / accounting</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Payments</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $tabs . '
                  <div class="h2">Accounting integration</div>
                  ' . $hint . '
                  <div class="qcard">' . $cardHead('<span class="qstate">Not connected</span>') . $notConnected . '<span class="btnp">Connect to QuickBooks</span></div>
                  ' . $xero . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — the tab -->
                <div class="sc" data-scene="1" data-len="17">
                  <div class="a-fade" style="--d:.2s">' . $tabs . '</div>
                  <div class="h2 a-fade" style="--d:1.5s">Accounting integration</div>
                  <div class="a-fade" style="--d:2.5s">' . $hint . '</div>
                  <div class="qcard a-rise" style="--d:5s">' . $cardHead('<span class="qstate">Not connected</span>') . $notConnected . '<span class="btnp">Connect to QuickBooks</span></div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:10s">&#128274; You sign in on QuickBooks&rsquo; own site</span>
                    <span class="chip a-pop" style="--d:13s">YourBlinds never sees that password</span></div>
                </div>

                <!-- 2 — what it does today -->
                <div class="sc" data-scene="2" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Read this first &mdash; what it does today</div>
                  <div class="flow">
                    <div class="node a-pop" style="--d:2s">YourBlinds<small>paid jobs</small></div>
                    <div class="pipe a-wide" style="--d:3s"><span class="gap a-pop" style="--d:7s">&#10007;</span></div>
                    <div class="node a-pop" style="--d:4s">QuickBooks<small>your company</small></div>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:2.5s">&#10003; Connect</span>
                    <span class="chip ok a-pop" style="--d:3.5s">&#10003; Map</span>
                    <span class="chip bad a-pop" style="--d:7.5s">&#10007; Sending sales across &mdash; not switched on yet</span>
                  </div>
                  <p class="scs a-fade" style="--d:12s;margin-top:.9rem">For now, on the <b>Payments</b> page:</p>
                  <div class="csv">
                    <span class="btns a-pop" style="--d:13s">Export invoices (CSV)</span>
                    <span class="btns a-pop" style="--d:13.6s">Export for QuickBooks (CSV)</span>
                    <span class="btns a-pop" style="--d:14.2s">Export payments (CSV)</span>
                  </div>
                </div>

                <!-- 3 — the card and its badge -->
                <div class="sc" data-scene="3" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">The card, and its little badge</div>
                  <div class="qcard a-rise" style="--d:.8s">' . $cardHead('<span class="qstate">Not connected</span>', '<span class="a-ring" style="--d:3s;border-radius:6px">Live</span>') . $notConnected . '<span class="btnp">Connect to QuickBooks</span></div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:4s">Live = your real company</span>
                    <span class="chip bad a-pop" style="--d:7s">Sandbox (test) = still being tested &mdash; leave it</span>
                  </div>
                  <div class="states">
                    <div class="st a-rise" style="--d:12s"><b>Not set up</b>No button &mdash; not switched on for your account yet.</div>
                    <div class="st a-rise" style="--d:15.5s"><b>Not connected</b>Ready. The Connect button is there.</div>
                    <div class="st a-rise" style="--d:18.5s"><b style="color:#065f46">&#9679; Connected</b>Linked to your company.</div>
                  </div>
                </div>

                <!-- 4 — before you start -->
                <div class="sc" data-scene="4" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">Before you start &mdash; five minutes in QuickBooks</div>
                  <div class="prep">
                    <div class="a-fly" style="--d:2.5s"><i>&#128273;</i><span>Be an <b>admin</b> on your QuickBooks company<small>Not sure? Ask your bookkeeper.</small></span></div>
                    <div class="a-fly" style="--d:7.5s"><i>&#9998;</i><span>One sales item called <b>Blinds</b><small>A Service, pointing at your sales / income account.</small></span></div>
                    <div class="a-fly" style="--d:13s"><i>%</i><span>VAT switched on, so <b>20.0% S</b> exists<small>Only if you are VAT registered.</small></span></div>
                    <div class="a-fly" style="--d:17s"><i>&#127974;</i><span>Know which <b>bank account</b> the money lands in</span></div>
                  </div>
                </div>

                <!-- 5 — connect: QuickBooks own page -->
                <div class="sc" data-scene="5" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Connect &mdash; on QuickBooks&rsquo; own website</div>
                  <div class="a-mid" style="--d:.6s;--d2:4.5s"><div class="qcard">' . $cardHead('<span class="qstate">Not connected</span>') . '<p class="qtxt"></p><span class="btnp a-press" style="--d:2.8s">Connect to QuickBooks</span></div>
                  <div class="a-move" style="--fx:60%;--fy:85%;--tx:14%;--ty:3.1rem;--d:1s;--md:1.6s">' . $ptr . '</div></div>
                  <div class="a-fade" style="--d:5s;position:absolute;left:0;top:2rem;width:100%">
                    <span class="urlbar">&#128274; https://appcenter.intuit.com/&hellip;</span>
                    <div class="ext">
                      <div class="who">Intuit &middot; not YourBlinds</div>
                      <h4>Sign in to QuickBooks</h4>
                      <div class="fbox"><span class="a-type" style="--d:11.5s;--ts:20;--tt:1s">you@demoblinds.co.uk</span></div>
                      <div class="fbox"><span class="a-type" style="--d:13s;--ts:10;--tt:.6s">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span></div>
                      <span class="grn a-press" style="--d:14.5s">Sign in</span>
                      <div class="lock a-fade" style="--d:16s">&#128274; This password goes to QuickBooks only.</div>
                    </div>
                  </div>
                </div>

                <!-- 6 — pick the company, approve -->
                <div class="sc" data-scene="6" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Pick the right company, then approve</div>
                  <span class="urlbar a-fade" style="--d:.5s">&#128274; https://appcenter.intuit.com/&hellip;</span>
                  <div class="ext a-rise" style="--d:1s">
                    <div class="who">Intuit &middot; not YourBlinds</div>
                    <h4>Which company?</h4>
                    <div class="co"><span class="dot a-sel" style="--d:6.5s"></span>Demo Blinds Ltd</div>
                    <div class="co"><span class="dot"></span>Demo Property Lettings</div>
                    <p class="lock a-fade" style="--d:12.5s">YourBlinds will be able to work with this company&rsquo;s accounting data.</p>
                    <span class="grn a-press" style="--d:15.5s">Connect</span>
                  </div>
                  <div class="a-move" style="--fx:80%;--fy:30%;--tx:5%;--ty:8.2rem;--d:4.5s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 7 — back, connected -->
                <div class="sc" data-scene="7" data-len="19">
                  <div class="bnr a-drop" style="--d:1s">&#10003; Connected to QuickBooks Online (Demo Blinds Ltd).</div>
                  <div class="qcard a-rise" style="--d:3s">
                    ' . $cardHead('<span class="qstate on a-pop" style="--d:5s">&#9679; Connected</span>') . '
                    <p class="qtxt a-fade" style="--d:7s">Linked to <b>Demo Blinds Ltd</b> <span style="color:var(--faint)">&middot; since 7 Oct 2026</span></p>
                    <p class="qfaint a-fade" style="--d:11s">Next: map your sales item, VAT code and bank account so paid sales post to the right place.</p>
                    <div class="qacts"><span class="btnp a-ring" style="--d:17s">Set up mapping</span><span class="btns">Disconnect</span></div>
                  </div>
                </div>

                <!-- 8 — mapping: how to record -->
                <div class="sc" data-scene="8" data-len="20">
                  ' . $mapHead . '
                  <div class="mf a-rise" style="--d:1s"><label>How to record a paid sale</label>
                    <span class="selectbox sb a-ring" style="--d:7s">Sales Receipt (income + payment in one &mdash; no open debtor)</span>
                    <p class="mh">You only put paid sales on QuickBooks (cash accounting), so a Sales Receipt is usually the cleanest.</p></div>
                  <div class="ddl a-mid" style="--d:9s;--d2:20s;left:0;top:7.3rem">
                    <div class="hi">Sales Receipt (income + payment in one &mdash; no open debtor)</div>
                    <div>Invoice + Payment (keeps a customer invoice history)</div>
                  </div>
                  <div class="chips" style="margin-top:3.6rem">
                    <span class="chip a-pop" style="--d:4s">&#128203; Every list comes from <b>your</b> QuickBooks</span>
                    <span class="chip a-pop" style="--d:18s">Not sure? Ask your accountant</span>
                  </div>
                </div>

                <!-- 9 — item, VAT, bank, save -->
                <div class="sc" data-scene="9" data-len="20">
                  <div class="bnr a-drop" style="--d:18s">&#10003; QuickBooks mapping saved.</div>
                  ' . $mapForm([
                        'Sales Receipt (income + payment in one &mdash; no open debtor)',
                        $swap('<span class="ph">&mdash; Select an item &mdash;</span>', 'Blinds', 3),
                        $swap('<span class="ph">&mdash; Select a tax code &mdash;</span>', '20.0% S', 7.5),
                        $swap('<span class="ph">&mdash; Select an account &mdash;</span>', 'Business Current Account', 12),
                    ]) . '
                  <span class="btnp a-press" style="--d:16.5s">Save mapping</span>
                </div>

                <!-- 10 — mapping set; disconnect -->
                <div class="sc" data-scene="10" data-len="17">
                  <div class="qcard a-rise" style="--d:.5s">
                    ' . $cardHead('<span class="qstate on">&#9679; Connected</span>') . '
                    <p class="qtxt">Linked to <b>Demo Blinds Ltd</b> <span style="color:var(--faint)">&middot; since 7 Oct 2026</span></p>
                    <p class="qtxt a-fade" style="--d:2.5s;margin:.2rem 0">Mapping set: <b>Blinds</b> &middot; VAT <b>20.0% S</b> &middot; into <b>Business Current Account</b></p>
                    <div class="qacts"><span class="btnp a-ring" style="--d:6s">Edit mapping</span><span class="btns a-ring" style="--d:9s">Disconnect</span></div>
                  </div>
                  <div class="confirm a-mid" style="--d:10.5s;--d2:15.5s">Disconnect QuickBooks? You can reconnect at any time.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp">OK</span></div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:13s">Nothing already in QuickBooks is touched</span>
                    <span class="chip ok a-pop" style="--d:15.5s">Connect again any time</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <div class="heads"><span class="hi">&#9888;</span><div><b>Read this first &mdash; what the link does today.</b> Connecting and
             mapping <b>gets everything ready</b>. The part that actually <b>sends paid jobs into QuickBooks is not switched on yet</b>,
             so for now <b>nothing is sent automatically</b>, whatever the screen&rsquo;s wording says about paid sales flowing through.
             Until it is, keep getting your figures across with <b>Export invoices (CSV)</b>, <b>Export for QuickBooks (CSV)</b> and
             <b>Export payments (CSV)</b> on the <b>Payments</b> page &mdash; the guide
             <b>&ldquo;Get your figures into Xero, QuickBooks or Sage&rdquo;</b> walks through it. We&rsquo;ll tell you when sending goes
             live.</div></div>

          <p><b>Before you start &mdash; five minutes in QuickBooks.</b> Do these in QuickBooks Online first, so the lists YourBlinds shows
             you have the right things in them:</p>
          <ul class="steps">
            <li><b>Be a QuickBooks admin.</b> Only a user with admin rights on the QuickBooks company can approve the link. If you&rsquo;re
                not sure, ask whoever set QuickBooks up (often your bookkeeper or accountant).</li>
            <li><b>Make one &ldquo;Blinds&rdquo; sales item.</b> In QuickBooks, add a <b>Service</b> called <code>Blinds</code>, pointing at
                your <b>sales / income</b> account. QuickBooks insists every line of a sale has an item, and the mapping page says
                &ldquo;a single &lsquo;Blinds&rsquo; sales item is fine&rdquo;.</li>
            <li><b>Make sure VAT is switched on</b> in QuickBooks (if you&rsquo;re VAT registered), so a standard-rate code such as
                <code>20.0% S</code> exists.</li>
            <li><b>Know which bank account the money lands in</b> &mdash; normally your business current account, as it&rsquo;s named in
                QuickBooks.</li>
          </ul>

          <p><b>Connecting &mdash; step by step.</b></p>
          <ul class="steps">
            <li>Go to <b>Setup &rarr; Settings</b> and click the <b>Accounting</b> tab &mdash; the <b>sixth</b> tab along, between
                <b>Suppliers</b> and <b>Back up data</b>. Under the heading <b>Accounting integration</b> is a card headed
                <b>QuickBooks Online</b>.</li>
            <li>Look at the little grey badge beside the name. <b>Live</b> means you can connect your real company.
                <b>Sandbox (test)</b> means the link is still being tested &mdash; your real company won&rsquo;t connect, so leave it for
                now. On the right, the card says <b>Not connected</b> (ready, with a <b>Connect to QuickBooks</b> button),
                <b>&#9679; Connected</b>, or <b>Not set up</b>. <b>Not set up</b> reads &ldquo;QuickBooks isn&rsquo;t switched on yet &mdash;
                the app keys still need adding to the server configuration&rdquo; &mdash; that&rsquo;s on our side, so ask us through
                <b>? Help</b>.</li>
            <li>Press <b>Connect to QuickBooks</b>. You leave YourBlinds and land on <b>QuickBooks&rsquo; own sign-in page</b> (the
                address bar shows an intuit.com address). Sign in with your normal QuickBooks details. <b>YourBlinds never sees
                that password</b>.</li>
            <li>If you have more than one QuickBooks company, <b>pick the right one</b> &mdash; the business these blinds are sold by
                &mdash; and approve the connection. (Those screens are Intuit&rsquo;s, so their exact wording may differ.)</li>
            <li>You&rsquo;re sent straight back to the Accounting tab with a green bar &mdash;
                <b>&ldquo;Connected to QuickBooks Online (your company name).&rdquo;</b> &mdash; and the card reads
                <b>&#9679; Connected</b>, <b>Linked to</b> your company, <b>since</b> the date. Underneath:
                <em>&ldquo;Next: map your sales item, VAT code and bank account so paid sales post to the right place.&rdquo;</em></li>
          </ul>

          <p><b>Mapping &mdash; the four questions.</b> Press <b>Set up mapping</b>. The page is headed <b>QuickBooks mapping</b>
             (&ldquo;Tell YourBlinds where paid sales should post in <em>your company</em>&rdquo;), and every dropdown is filled
             <b>live from your own QuickBooks</b>:</p>
          <ul class="steps">
            <li><b>How to record a paid sale</b> &mdash; <b>Sales Receipt (income + payment in one &mdash; no open debtor)</b>, the default,
                or <b>Invoice + Payment (keeps a customer invoice history)</b>. The screen notes that a Sales Receipt is usually the cleanest
                for cash accounting; if in doubt, ask your accountant.</li>
            <li><b>Sales item *</b> (the only required one). Pick your <b>Blinds</b> item.</li>
            <li><b>Default VAT / tax code.</b> Your standard-rate code, usually <code>20.0% S</code>. Not VAT registered? Pick your no-VAT
                code.</li>
            <li><b>Bank account paid sales land in.</b> Usually your current account. The screen suggests <b>Undeposited Funds</b> only
                &ldquo;if payments arrive batched&rdquo; (a card machine paying out once a day, for example).</li>
            <li>Press <b>Save mapping</b>. The green bar <b>&ldquo;QuickBooks mapping saved.&rdquo;</b> appears. Press
                <b>&larr; Back to Accounting</b> and the card now shows <b>Mapping set: Blinds &middot; VAT 20.0% S &middot; into &hellip;</b>,
                and the button has become <b>Edit mapping</b>.</li>
          </ul>

          <p><b>If something goes wrong.</b></p>
          <ul class="steps">
            <li><b>&ldquo;Security check failed on the accounting connection (state mismatch). Please try connecting again.&rdquo;</b>
                &mdash; usually the connection started on one web address and finished on another (with and without <code>www</code>).
                Close the QuickBooks tab, go back to <b>Settings &rarr; Accounting</b> and press Connect again.</li>
            <li><b>&ldquo;The connection took too long and expired. Please try connecting again.&rdquo;</b> &mdash; you have 15 minutes
                from pressing Connect. Just start again.</li>
            <li><b>&ldquo;Connection cancelled or refused by the provider (&hellip;)&rdquo;</b> &mdash; you pressed Cancel on
                QuickBooks&rsquo; page, or you&rsquo;re not an admin on that QuickBooks company.</li>
            <li><b>&ldquo;Accounting connection was started on a different account. Please try again.&rdquo;</b> &mdash; you switched
                YourBlinds accounts part-way through.</li>
            <li><b>&ldquo;Couldn&rsquo;t load lists from QuickBooks: &hellip; Try again, or reconnect on the Accounting tab.&rdquo;</b>
                on the mapping page &mdash; QuickBooks didn&rsquo;t answer. Try again in a minute; if it keeps happening,
                <b>Disconnect</b> and connect again.</li>
            <li><b>The Blinds item or VAT code isn&rsquo;t in the list</b> &mdash; it doesn&rsquo;t exist in QuickBooks yet. Make it there,
                then reopen the mapping page; the lists are fetched fresh each time.</li>
          </ul>

          <p><b>Disconnecting.</b> On the Accounting tab press <b>Disconnect</b>. It asks
             <em>&ldquo;Disconnect QuickBooks? You can reconnect at any time.&rdquo;</em> (as sending isn&rsquo;t
             switched on yet, nothing is syncing today). Press OK and you&rsquo;ll see <b>&ldquo;Disconnected from QuickBooks
             Online.&rdquo;</b> Nothing already in QuickBooks is touched, and you can connect again any time with the same button.</p>

          <p><b>Xero or Sage?</b> The Accounting tab shows <b>Xero &amp; Sage &mdash; Coming soon &mdash; same one-click connect.</b>
             Until then, use the CSV exports on the Payments page; the guide <b>&ldquo;Get your figures into Xero, QuickBooks or
             Sage&rdquo;</b> has the steps for each package.</p>',
        'script'  => [
            ['1',  'The Accounting tab',            'Linking your accounts lives in Settings, on the Accounting tab. You will see a card for QuickBooks Online. When you connect, you sign in on QuickBooks\' own website, not ours. So YourBlinds never sees your QuickBooks password.', 1],
            ['2',  'What it does today',             'Please hear this part first. Today, this tab connects and maps, and that is all. The part that sends your paid sales into QuickBooks is not switched on yet. So nothing is sent automatically. Until it is, keep using the CSV export buttons on the Payments page. There is a separate guide for those.', 2],
            ['3',  'The card and its badge',         'Look at the little badge beside the name. Live means you can connect your real company. Sandbox means the link is still being tested, so leave it for now. On the right, the card says Not set up, Not connected, or Connected. Not set up means it has not been switched on for your account yet, so ask us.', 3],
            ['4',  'Before you start',               'Before you press anything, spend five minutes in QuickBooks. Make sure you are an admin on the company. Add one sales item, called Blinds, pointing at your sales account. If you are VAT registered, check your VAT codes are there. And know which bank account the money lands in.', 4],
            ['5',  'Connect to QuickBooks',          'Now press Connect to QuickBooks. You leave YourBlinds, and land on QuickBooks\' own sign in page. Look at the address bar: it is an intuit dot com address. Sign in with your usual QuickBooks details. That password goes to QuickBooks only, and never to us.', 5],
            ['6',  'Pick the company',               'If you run more than one company, QuickBooks asks which one to link. Choose the right one: the business these blinds are sold by. QuickBooks shows what YourBlinds will be allowed to do. Approve the connection, and it sends you straight back.', 6],
            ['7',  'Back, and connected',            'You land back on the Accounting tab, with a green bar saying you are connected. The card now reads Connected, linked to your company, with the date. Underneath, it tells you what to do next: map your sales item, VAT code and bank account. So press Set up mapping.', 7],
            ['8',  'How to record a paid sale',      'The mapping page asks four questions, and every list comes from your own QuickBooks. First, how to record a paid sale. It starts on Sales Receipt, which records the sale and the money together. The other choice is Invoice plus Payment. If you are not sure, ask your accountant.', 8],
            ['9',  'Item, VAT and bank',             'Second, the sales item. It is the only one you must fill in. Choose Blinds. Third, your VAT code, normally twenty percent standard rated. Fourth, the bank account the money lands in, usually your current account. Then press Save mapping, and look for the green bar.', 9],
            ['10', 'Mapping set, and disconnecting', 'Back on the Accounting tab, the card shows your mapping, and the button now says Edit mapping. If you ever need to unlink, press Disconnect, and say OK. Nothing already in QuickBooks is touched, and you can connect again any time.', 10],
        ],
];
