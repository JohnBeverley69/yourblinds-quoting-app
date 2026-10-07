<?php
declare(strict_types=1);

/**
 * Guide: settings-backup — "Back up your data" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the real screen: admin/settings.php (tab 7, data-panel="backup",
 * the date pickers, the All time / Last 30 days / This year presets, the two
 * download buttons and the "last full backup" box) and the download it fires,
 * admin/export.php (file names, sheet names, column headings, the PDF title
 * line, and the rule that only an UNFILTERED Excel moves last_backup_at).
 * Every label, string, column heading and file name is taken from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The seven Settings tabs; Back up data is the last.
$tabs = static function (string $lastCls = 'on', string $lastStyle = ''): string {
    $h = '<div class="sttabs">';
    foreach (['Company', 'Quoting', 'Legal', 'Status colours', 'Suppliers', 'Accounting'] as $t) {
        $h .= '<span class="sttab">' . $t . '</span>';
    }
    return $h . '<span class="sttab ' . $lastCls . '" style="' . $lastStyle . '">Back up data</span></div>';
};

// A date box: either a plain value/placeholder, or custom inner HTML.
$date = static fn (string $inner, string $cls = '', string $style = ''): string =>
    '<span class="dbox ' . $cls . '" style="' . $style . '">' . $inner . '</span>';
$ph = '<span class="dph">dd/mm/yyyy</span>';

// The date row (From / To + the three presets) — $from/$to are inner HTML.
$range = static function (string $from, string $to, array $press = []) use ($date): string {
    $b = static function (string $key, string $label) use ($press): string {
        $p = $press[$key] ?? '';
        return '<span class="btns sm' . ($p !== '' ? ' a-press' : '') . '"' . ($p !== '' ? ' style="--d:' . $p . 's"' : '') . '>' . $label . '</span>';
    };
    return '<div class="drow">'
         . '<label class="dl">From' . $date($from) . '</label>'
         . '<label class="dl">To' . $date($to) . '</label>'
         . '<div class="presets">' . $b('all', 'All time') . $b('30', 'Last 30 days') . $b('ytd', 'This year') . '</div>'
         . '</div>';
};

$dlRow = static fn (string $xl = '', string $pdf = ''): string =>
    '<div class="dlrow"><span class="btnp ' . $xl . '">&#11015; Download Excel (.xlsx)</span>'
  . '<span class="btns ' . $pdf . '">&#11015; Download PDF summary</span></div>';

$markerBefore = '<p class="mk0">Once you take a full Excel backup (the button above, with no dates set), a <em>&ldquo;Changes since last backup&rdquo;</em> option appears here so you can grab only what&rsquo;s new or changed next time.</p>';
$markerAfter  = '<p class="mk1">Last full backup: <b>7 Oct 2026, 9:14am</b>. Get just the quotes and orders created or changed since then:</p>'
              . '<span class="btns sm">&#11015; Changes since last backup (Excel)</span>';

$hint = '<p class="hnt">Download a copy of <b>your quotes and orders</b> (with their line items, totals and payments) to keep on your own computer. Useful as a regular off-site backup or to work with your figures in a spreadsheet.</p>';

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Back up your data',
        'eyebrow' => 'Settings · Back up data',
        'v'       => 2,
        'blurb'   => 'Download your quotes and orders — two Excel sheets or a printable PDF. A full Excel (no dates) is the one that counts, and the one that unlocks “changes since last backup”.',
        'lede'    => 'The last tab in <b>Settings</b>, <b>Back up data</b>, hands you a copy of your <b>quotes and orders</b> &mdash;
                      with their line items, totals and payments &mdash; to keep on your own computer. There is nothing to fill in and
                      nothing to save: every button just gives you a file. Take a <b>full Excel with no dates</b> and the page starts
                      tracking what has changed since, so next time you can grab just the new bits. To get there:
                      <b>Setup</b> &rarr; <b>Settings</b> &rarr; the <b>Back up data</b> tab.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* the seven Settings tabs */
          .gd .sttabs{ display:flex; flex-wrap:wrap; gap:.1rem .2rem; border-bottom:1px solid var(--line); margin:0 0 .75rem; }
          .gd .sttab{ font-size:.66rem; font-weight:600; color:var(--faint); padding:.28rem .45rem; border-bottom:2px solid transparent; white-space:nowrap; border-radius:6px 6px 0 0; }
          .gd .sttab.on{ color:var(--accent); border-bottom-color:var(--accent); }
          .gd .h2{ font-size:.9rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .hnt{ font-size:.68rem; color:var(--soft); line-height:1.45; margin:0 0 .7rem; max-width:31rem; }
          .gd .hnt b{ color:var(--ink); }

          /* date row */
          .gd .drow{ display:flex; gap:.6rem .8rem; flex-wrap:wrap; align-items:flex-end; margin:0 0 .5rem; }
          .gd .dl{ display:flex; flex-direction:column; gap:.2rem; font-size:.66rem; color:var(--soft); }
          .gd .dbox{ display:inline-grid; align-items:center; min-width:7.2rem; height:28px; border:1px solid var(--border-strong,#c7ccd4);
                     border-radius:6px; background:var(--surface); padding:0 .5rem; font-size:.74rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .dbox > span{ grid-area:1/1; }
          .gd .dph{ color:var(--faint); }
          .gd .presets{ display:flex; gap:.3rem; flex-wrap:wrap; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.38rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.34rem .7rem; font-size:.72rem; font-weight:600; }
          .gd .btns.sm{ padding:.22rem .5rem; font-size:.66rem; }
          .gd .dlrow{ display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin:.55rem 0 0; }
          .gd .mkbox{ margin:.75rem 0 0; padding:.55rem .7rem; border:1px solid var(--line); border-radius:8px; max-width:31rem; position:relative; }
          .gd .mk0{ margin:0; font-size:.68rem; color:var(--faint); line-height:1.45; }
          .gd .mk1{ margin:0 0 .45rem; font-size:.7rem; color:var(--soft); line-height:1.45; }
          .gd .mk1 b{ color:var(--ink); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }

          /* 2 — what is in it */
          .gd .what{ display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; margin:.4rem 0 .2rem; max-width:31rem; }
          .gd .what div{ border:1px solid var(--line); border-radius:10px; padding:.55rem .4rem; text-align:center; background:var(--surface); font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .what i{ display:block; font-style:normal; font-size:1.25rem; margin-bottom:.2rem; }

          /* 5 — the file landing */
          .gd .dlbar{ display:flex; align-items:center; gap:.55rem; border:1px solid var(--line); border-radius:10px; background:var(--panel);
                      padding:.5rem .7rem; margin-top:.9rem; max-width:31rem; box-shadow:var(--gd-shadow); }
          .gd .xlico{ display:inline-grid; place-items:center; width:30px; height:34px; border-radius:5px; background:#1d6f42; color:#fff; font-weight:800; font-size:.72rem; flex:0 0 auto; }
          .gd .pdfico{ background:#b91c1c; }
          .gd .fn{ font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.72rem; color:var(--ink); word-break:break-all; }
          .gd .fs{ font-size:.62rem; color:var(--faint); }

          /* 6 — the workbook */
          .gd .wb{ border:1px solid #1d6f42; border-radius:8px; overflow:hidden; background:#fff; color:#1f2937; max-width:32rem; }
          .gd .wb .wbh{ background:#1d6f42; color:#fff; font-weight:700; padding:.3rem .55rem; font-size:.66rem; }
          .gd .wb .cols{ display:flex; flex-wrap:wrap; gap:1px; background:#e5e7eb; }
          .gd .wb .cols span{ background:#f3f4f6; font-size:.58rem; font-weight:700; padding:.25rem .35rem; flex:1 1 auto; text-align:center; }
          .gd .wb .cols span.hot{ background:#fef3c7; }
          .gd .wb .rows{ display:flex; flex-direction:column; gap:1px; background:#e5e7eb; }
          .gd .wb .rows div{ background:#fff; height:.75rem; }
          .gd .wbtabs{ display:flex; gap:.15rem; padding:.2rem .3rem; background:#f3f4f6; border-top:1px solid #e5e7eb; }
          .gd .wbtabs span{ font-size:.6rem; padding:.15rem .5rem; border-radius:0 0 5px 5px; color:#374151; }
          .gd .wbtabs span.on{ background:#fff; color:#1d6f42; font-weight:800; border:1px solid #d1d5db; border-top:0; }
          .gd .sheet2{ display:grid; }
          .gd .sheet2 > div{ grid-area:1/1; }

          /* 7 — the PDF */
          .gd .pdf{ border:1px solid var(--line); border-radius:6px; background:#fff; color:#111827; padding:.6rem .7rem; max-width:31rem; box-shadow:var(--gd-shadow); }
          .gd .pdf h5{ margin:0; font-size:.8rem; }
          .gd .pdf .sub{ font-size:.6rem; color:#6b7280; margin:.1rem 0 .4rem; }
          .gd .pdf table{ width:100%; border-collapse:collapse; font-size:.56rem; }
          .gd .pdf th{ background:#1f3b5b; color:#fff; text-align:left; padding:.2rem .25rem; }
          .gd .pdf td{ border-bottom:1px solid #e5e7eb; padding:.18rem .25rem; }
          .gd .pdf .n{ text-align:right; }

          /* 8 — marker swap */
          .gd .mkswap{ display:grid; }
          .gd .mkswap > div{ grid-area:1/1; }

          /* 9 — the two clocks */
          .gd .clock{ display:grid; grid-template-columns:7.5rem 1fr; gap:.4rem .6rem; align-items:center; max-width:31rem; margin:.5rem 0; }
          .gd .clock .lb{ font-size:.66rem; font-weight:700; color:var(--soft); }
          .gd .line{ position:relative; height:30px; border-bottom:2px solid var(--line); }
          .gd .tick2{ position:absolute; bottom:-6px; width:10px; height:10px; border-radius:50%; background:var(--faint); transform:translateX(-50%); }
          .gd .tick2.mk{ background:var(--accent); width:3px; height:26px; border-radius:2px; bottom:-2px; }
          .gd .tlab{ position:absolute; top:-2px; transform:translateX(-50%); font-size:.56rem; font-weight:700; color:var(--soft); white-space:nowrap; }
          .gd .tlab.ac{ color:var(--accent); }

          /* 10 — not in it */
          .gd .nots{ display:flex; gap:.35rem; flex-wrap:wrap; margin:.3rem 0 .2rem; }
          .gd .strike{ position:relative; display:inline-block; }
          .gd .strike::after{ content:""; position:absolute; left:-2px; right:-2px; top:50%; height:2px; background:var(--err); transform:scaleX(var(--sx,1)); transform-origin:left; }

          @media (max-width:640px){
            .gd .sc{ min-height:430px; }
            .gd .what{ grid-template-columns:1fr 1fr; }
            .gd .clock{ grid-template-columns:1fr; }
            .gd .sttab{ font-size:.6rem; padding:.22rem .3rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / back up data</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a><a>Billing</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $tabs() . '
                  <div class="h2">Back up your data</div>
                  ' . $hint . '
                  ' . $range($ph, $ph) . '
                  ' . $dlRow() . '
                  <div class="mkbox">' . $markerBefore . '</div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — where it is -->
                <div class="sc" data-scene="1" data-len="17">
                  <div class="sct a-fade" style="--d:.2s">Settings &rarr; the last tab</div>
                  <p class="scs a-fade" style="--d:.5s">Seven tabs along the top. Back up data is the seventh.</p>
                  ' . $tabs('a-sel', '--d:4.2s') . '
                  <div class="a-move" style="--fx:30%;--fy:70%;--tx:63%;--ty:3.4rem;--d:2s;--md:1.8s">' . $ptr . '</div>
                  <div class="a-rise" style="--d:5s">
                    <div class="h2">Back up your data</div>
                    ' . $range($ph, $ph) . '
                    ' . $dlRow() . '
                  </div>
                  <div class="chips">
                    <span class="chip bad a-pop" style="--d:8s">&#10007; No Save button</span>
                    <span class="chip bad a-pop" style="--d:10s">&#10007; Nothing stored</span>
                    <span class="chip ok a-pop" style="--d:13s">&#11015; Every button = a file for you</span>
                  </div>
                </div>

                <!-- 2 — what is in it -->
                <div class="sc" data-scene="2" data-len="16">
                  <div class="sct a-fade" style="--d:.2s">What is in the file?</div>
                  <div class="a-fade" style="--d:.8s">' . $hint . '</div>
                  <div class="what">
                    <div class="a-drop" style="--d:3s"><i>&#128196;</i>Quotes &amp; orders</div>
                    <div class="a-drop" style="--d:4.6s"><i>&#128203;</i>Line items</div>
                    <div class="a-drop" style="--d:5.8s"><i>&pound;</i>Totals</div>
                    <div class="a-drop" style="--d:7s"><i>&#128179;</i>Payments</div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:10s">&#128202; Opens in a spreadsheet</span>
                    <span class="chip a-pop" style="--d:13s">&#128274; Keep it somewhere safe</span>
                  </div>
                </div>

                <!-- 3 — full backup: empty dates -->
                <div class="sc" data-scene="3" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">A proper backup: leave the dates empty</div>
                  ' . $range(
                        '<span class="a-out" style="--d:7.5s">07/09/2026</span><span class="dph a-fade" style="--d:7.6s">dd/mm/yyyy</span>',
                        '<span class="a-out" style="--d:7.5s">07/10/2026</span><span class="dph a-fade" style="--d:7.6s">dd/mm/yyyy</span>',
                        ['all' => '6.6']) . '
                  <div class="a-move" style="--fx:75%;--fy:80%;--tx:44%;--ty:3.1rem;--d:4.5s;--md:1.8s">' . $ptr . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:9s">All time just empties both boxes</span>
                    <span class="chip ok a-pop" style="--d:13.5s">&#10003; Empty dates = everything = a <b>full backup</b></span>
                  </div>
                </div>

                <!-- 4 — a slice -->
                <div class="sc" data-scene="4" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Just a slice? Set From and To</div>
                  ' . $range(
                        '<span class="a-type" style="--d:4.2s;--ts:10;--tt:.6s">07/09/2026</span>',
                        '<span class="a-type" style="--d:4.4s;--ts:10;--tt:.6s">07/10/2026</span>',
                        ['30' => '3.6']) . '
                  <div class="a-move" style="--fx:20%;--fy:85%;--tx:55%;--ty:3.1rem;--d:1.6s;--md:1.8s">' . $ptr . '</div>
                  <p class="scs a-fade" style="--d:6s;margin-top:.4rem">Last 30 days = today minus thirty days &rarr; today. This year = 1 January &rarr; today.</p>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:10s">&#128197; By <b>order date</b></span>
                    <span class="chip a-pop" style="--d:12.5s">accepted &mdash; or created, if not accepted yet</span>
                    <span class="chip ok a-pop" style="--d:19s">The <b>To</b> day is included, all of it</span>
                  </div>
                </div>

                <!-- 5 — download Excel -->
                <div class="sc" data-scene="5" data-len="15">
                  <div class="sct a-fade" style="--d:.2s">Download Excel &mdash; the one to keep</div>
                  ' . $range($ph, $ph) . '
                  ' . $dlRow('a-press', '') . '
                  <div class="a-move" style="--fx:70%;--fy:85%;--tx:18%;--ty:5.6rem;--d:.8s;--md:1.6s">' . $ptr . '</div>
                  <div class="dlbar a-drop" style="--d:4.5s">
                    <span class="xlico">X</span>
                    <div><div class="fn">Demo-Blinds-Ltd-orders-2026-10-07.xlsx</div><div class="fs">Downloads folder &middot; your company + today&rsquo;s date</div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:11s">Nothing kept on the server &mdash; it comes straight to you</span></div>
                </div>

                <!-- 6 — the two sheets -->
                <div class="sc" data-scene="6" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Inside the Excel: two sheets</div>
                  <div class="wb a-rise" style="--d:1s">
                    <div class="wbh">Demo-Blinds-Ltd-orders-2026-10-07.xlsx</div>
                    <div class="sheet2">
                      <div class="a-mid" style="--d:1.2s;--d2:14s">
                        <div class="cols"><span>Quote #</span><span>Customer</span><span>Postcode</span><span>Status</span><span>Created</span><span>Accepted</span><span>Total &pound;</span><span>Deposit paid &pound;</span><span class="hot">Received &pound;</span><span class="hot">Outstanding &pound;</span></div>
                      </div>
                      <div class="a-fade" style="--d:14.2s">
                        <div class="cols"><span>Quote #</span><span>Customer</span><span>Line</span><span>Room</span><span>Product</span><span>System</span><span>Fabric</span><span>Colour</span><span>Width (mm)</span><span>Drop (mm)</span><span>Qty</span><span>Line total &pound;</span></div>
                      </div>
                    </div>
                    <div class="rows"><div></div><div></div><div></div><div></div></div>
                    <div class="wbtabs">
                      <span class="on">Quotes &amp; Orders</span>
                      <span class="a-sel" style="--d:13.5s">Line items</span>
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4s">Sheet 1: a row per job</span>
                    <span class="chip ok a-pop" style="--d:9s">Sort by Outstanding = who still owes you</span>
                    <span class="chip a-pop" style="--d:15s">Sheet 2: a row per blind</span>
                  </div>
                </div>

                <!-- 7 — the PDF -->
                <div class="sc" data-scene="7" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Download PDF summary &mdash; for printing</div>
                  <div class="pdf a-drop" style="--d:2s">
                    <h5>Demo Blinds Ltd &mdash; Quotes &amp; Orders</h5>
                    <div class="sub">42 records &middot; All data &middot; generated 7 Oct 2026</div>
                    <table>
                      <tr><th>Quote #</th><th>Customer</th><th>Postcode</th><th>Status</th><th>Date</th><th class="n">Total</th><th class="n">Received</th><th class="n">Outstanding</th></tr>
                      <tr class="a-fade" style="--d:4s"><td>DEM-2026-0042</td><td>Mrs Halliwell</td><td>TA1 3QS</td><td>Accepted</td><td>6 Oct 2026</td><td class="n">&pound;1,284.00</td><td class="n">&pound;400.00</td><td class="n">&pound;884.00</td></tr>
                      <tr class="a-fade" style="--d:4.6s"><td>DEM-2026-0038</td><td>Miller</td><td>BS40 5RL</td><td>Paid</td><td>29 Sep 2026</td><td class="n">&pound;2,140.00</td><td class="n">&pound;2,140.00</td><td class="n">&pound;0.00</td></tr>
                    </table>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:8s">&#128424; A4 landscape &middot; good for filing</span>
                    <span class="chip bad a-pop" style="--d:13s">&#10007; Not your backup &mdash; can&rsquo;t sort it</span>
                    <span class="chip bad a-pop" style="--d:17s">&#10007; Never counts as one</span>
                  </div>
                </div>

                <!-- 8 — the last-backup box -->
                <div class="sc" data-scene="8" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">The box underneath keeps score</div>
                  ' . $dlRow('a-press', '') . '
                  <div class="mkbox a-ring" style="--d:2.5s">
                    <div class="mkswap">
                      <div class="a-out" style="--d:11s">' . $markerBefore . '</div>
                      <div class="a-fade" style="--d:11.2s">' . $markerAfter . '</div>
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:19s">&#10003; Full Excel, no dates &rarr; moves the date</span>
                    <span class="chip bad a-pop" style="--d:21s">&#10007; Dated Excel</span>
                    <span class="chip bad a-pop" style="--d:22.5s">&#10007; PDF</span>
                  </div>
                </div>

                <!-- 9 — changes since: a different clock -->
                <div class="sc" data-scene="9" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">&ldquo;Changes since&rdquo; works on a different clock</div>
                  <div class="mkbox a-rise" style="--d:1s">' . $markerAfter . '</div>
                  <div class="clock">
                    <span class="lb a-fade" style="--d:4s">Order date</span>
                    <div class="line a-fade" style="--d:4s">
                      <span class="tick2" style="left:12%"></span><span class="tlab" style="left:12%">March order</span>
                      <span class="tick2 mk" style="left:70%"></span><span class="tlab ac" style="left:70%">last full backup</span>
                    </div>
                    <span class="lb a-fade" style="--d:8s">Last touched</span>
                    <div class="line a-fade" style="--d:8s">
                      <span class="tick2 mk" style="left:70%"></span>
                      <span class="tick2 a-pop" style="left:90%;background:var(--good);--d:10s"></span><span class="tlab a-pop" style="left:88%;color:var(--good);--d:10s">edited yesterday</span>
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:12s">&#10003; Created, accepted or edited since &rarr; it&rsquo;s in</span>
                    <span class="chip a-pop" style="--d:16s">Ignores the From and To boxes</span>
                  </div>
                </div>

                <!-- 10 — what it isn&rsquo;t, and where it goes -->
                <div class="sc" data-scene="10" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Your quotes and orders &mdash; nothing else</div>
                  <div class="nots">
                    <span class="chip a-pop" style="--d:3s"><span class="strike">Customer list</span></span>
                    <span class="chip a-pop" style="--d:3.6s"><span class="strike">Products</span></span>
                    <span class="chip a-pop" style="--d:4.2s"><span class="strike">Price tables</span></span>
                    <span class="chip bad a-pop" style="--d:7s">&#10007; No restore &mdash; you can&rsquo;t load it back in</span>
                  </div>
                  <div class="dlbar a-rise" style="--d:10s"><span class="xlico">X</span>
                    <div><div class="fn">Demo-Blinds-Ltd-orders-2026-10-07.xlsx</div><div class="fs">Get it off this computer:</div></div></div>
                  <div class="chips">
                    <span class="chip a-fly" style="--d:12.5s">&#128190; Memory stick</span>
                    <span class="chip a-fly" style="--d:13.5s">&#9729; Cloud drive</span>
                    <span class="chip a-fly" style="--d:14.5s">&#9993; Email to yourself</span>
                    <span class="chip ok a-pop" style="--d:18s">&#128197; Once a week</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar (under <b>Setup</b>). There are <b>seven tabs</b> along the top &mdash; Company, Quoting,
             Legal, Status colours, Suppliers, Accounting and <b>Back up data</b> &mdash; and this one is the last.
             (Settings remembers the tab you were on, so once you have been here you land back here next time.)
             It is an <b>admin-only</b> page.</p>
          <p><b>There is no Save button on this tab, and nothing to fill in.</b> Nothing you do here is stored.
             Every button simply hands you a file to keep: your <b>quotes and orders</b>, with their line items,
             totals and payments, downloaded onto your own computer.</p>
          <ul class="steps">
            <li><b>For a proper backup, leave both dates empty.</b> Or press <b>All time</b>, which empties them for
                you &mdash; that is all that button does. Empty dates means everything you have ever quoted, and that
                is the only kind the system counts as a <b>full backup</b>.</li>
            <li><b>Want a slice instead?</b> Put a date in <b>From</b> and <b>To</b> (they are ordinary date boxes with
                the little calendar), or let <b>Last 30 days</b> or <b>This year</b> fill them in for you.
                <b>Last 30 days</b> puts today minus thirty days in From and today in To; <b>This year</b> puts the 1st
                of January in From and today in To. Neither button stays looking &ldquo;pressed&rdquo; &mdash; look at
                the boxes to see what it did.</li>
            <li><b>What the dates mean.</b> They go by the <b>order date</b>: the day the job was accepted, or the day
                it was created if it has not been accepted yet. The <b>To</b> date is <b>included</b> &mdash; the whole
                of that day, right up to midnight &mdash; so a 30 September &ldquo;To&rdquo; does include the 30th.</li>
            <li><b>&#11015; Download Excel (.xlsx)</b> is the one to keep. It lands in your <b>Downloads</b> folder,
                named after your company and today&rsquo;s date &mdash; <code>&lt;your company&gt;-orders-&lt;date&gt;.xlsx</code>,
                so <b>Demo Blinds Ltd</b> gets <code>Demo-Blinds-Ltd-orders-2026-10-07.xlsx</code>. It appears in your
                <b>browser&rsquo;s</b> download bar, not anywhere in the app &mdash; nothing is kept on the server.</li>
            <li><b>&#11015; Download PDF summary</b> gives you a printable list instead: A4 landscape, headed
                <b>&ldquo;Demo Blinds Ltd &mdash; Quotes &amp; Orders&rdquo;</b> with a line underneath like
                <b>&ldquo;42 records &middot; All data &middot; generated 7 Oct 2026&rdquo;</b>, then a row per order
                across eight columns: Quote #, Customer, Postcode, Status, Date, Total, Received, Outstanding. If there
                is nothing to list it prints one line: <b>&ldquo;No quotes or orders yet.&rdquo;</b> Good for filing or
                handing over &mdash; but it is <b>not your backup</b>: you cannot sort it or work in it.</li>
            <li><b>Then look at the bordered box.</b> Before your first full Excel it just explains itself in grey.
                After one, it turns into <b>&ldquo;Last full backup: 7 Oct 2026, 9:14am&rdquo;</b> &mdash; a date
                <em>and</em> a time &mdash; with a <b>&#11015; Changes since last backup (Excel)</b> button beside it.</li>
          </ul>
          <p><b>What is actually inside the Excel.</b> Two sheets, and it is worth knowing the headings before you open it:</p>
          <ul class="steps">
            <li><b>Sheet 1 &mdash; &ldquo;Quotes &amp; Orders&rdquo;</b>, a row per job:
                <b>Quote #, Customer, Postcode, Status, Created, Accepted, Total &pound;, Deposit paid &pound;,
                Received &pound;, Outstanding &pound;</b>. The last two are worked out from the payments and the paid
                deposit, so this sheet doubles as your <b>who-still-owes-me</b> list &mdash; sort by Outstanding.</li>
            <li><b>Sheet 2 &mdash; &ldquo;Line items&rdquo;</b>, a row per blind:
                <b>Quote #, Customer, Line, Room, Product, System, Fabric, Colour, Width (mm), Drop (mm), Qty,
                Line total &pound;</b>. The product, system, fabric and colour names are the ones <em>as they were when
                the job was quoted</em>, so an old order still reads correctly after you rename something.</li>
          </ul>
          <p>On a <b>trade order with no customer record</b>, the Customer and Postcode columns fall back to the
             <b>end customer&rsquo;s</b> name and postcode as typed on the order. If those were left blank as well,
             the cell simply comes out <b>empty</b> &mdash; nothing is invented to fill it.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Only a full Excel counts as a backup.</b>
             Excel with <b>both dates empty</b> &rarr; <b>counts</b>, and moves the &ldquo;last full backup&rdquo; date
             on. Excel with a From or a To in (including anything <b>Last 30 days</b> or <b>This year</b> typed in for
             you) &rarr; <b>does not count</b>, and the line does not move. The <b>PDF</b> and the <b>Changes since last
             backup</b> file &rarr; <b>never</b> count. That is deliberate: a part export must not be allowed to move the
             marker, or the next &ldquo;changes since&rdquo; file would quietly skip everything the part export left out.
             <b>All time</b> is safe, because it clears both boxes.</div></div>
          <div class="oops"><b>A badly typed date is ignored without a word.</b> If what is in a date box is not a proper
             date, the export simply drops that end of the range &mdash; no red banner, no message, you just quietly get
             <em>more</em> than you asked for. So if a file comes back much bigger than expected, go back and look at the
             two boxes.</div>
          <p><b>The catch in &ldquo;Changes since last backup&rdquo;.</b> That button works on a different clock from the
             From/To boxes. From/To go by the <b>order date</b>; this one goes by <b>when the job was last touched</b>
             &mdash; created, accepted or edited. So an order from March that you tweaked yesterday <em>is</em> in it.
             It also <b>ignores the From and To boxes completely</b>. It is there so that after one full Excel you can
             keep topping up with small files instead of pulling the lot every time.</p>
          <p><b>What this file is not.</b> It is your <b>quotes and orders only</b>. It does <em>not</em> contain your
             customer list, your products, your price tables, your fabrics, your invoices, the payments ledger detail,
             your uploaded logo and images, your settings, or anything from the factory floor. And there is <b>no
             &ldquo;restore&rdquo;</b> &mdash; you cannot load this file back in. It is a readable copy for your own
             records and your spreadsheet, not a system rebuild.</p>
          <p><b>Then get it off this computer.</b> The file is plain and unencrypted, and it is streamed straight to your
             browser &mdash; no copy is kept on the server. So treat it like paperwork: onto a memory stick, into a cloud
             drive, or emailed to yourself. A backup sitting on the machine that breaks is not a backup. Do it
             <b>once a week</b>, and always before anything big.</p>
          <p><b>Two things that catch people out.</b> The neighbouring <b>Accounting</b> tab (the QuickBooks link) is
             <em>not</em> a backup and does not replace this. And if you are running in <b>Compact mode</b>, the grey
             explanation paragraphs on this tab are hidden, so the real screen looks barer than the one in this guide &mdash;
             just the date boxes and the buttons. The buttons still do exactly the same thing.</p>',
        'script'  => [
            ['1',  'Settings, the last tab',          'Your backup lives in Settings, on the very last tab, called Back up data. There is no Save button here, and nothing to fill in. Nothing you do on this tab is stored. Every button simply hands you a file, to keep on your own computer.', 1],
            ['2',  'What is in the file',             'So what is in the file? Your quotes and your orders, with their line items, their totals and their payments. It is a copy of your selling records. You can open it in a spreadsheet, or tuck it away somewhere safe.', 2],
            ['3',  'A full backup: empty dates',      'For a proper backup, leave both date boxes empty. If they have dates in them, press All time, and it clears them for you. That is all that button does. Empty dates means everything you have ever quoted, and that is the only kind the system counts as a full backup.', 3],
            ['4',  'Just a slice',                    'Want just a slice, for your accountant say? Put a date in From and To, or press Last thirty days, or This year, and they fill the boxes in for you. The dates go by the order date: the day the job was accepted, or the day it was created if it has not been accepted yet. And the To date is included, the whole day.', 4],
            ['5',  'Download Excel',                  'Now press Download Excel. That is the one to keep. It drops straight into your Downloads folder, named after your company and today\'s date. Nothing is kept on the server. The file comes straight to you.', 5],
            ['6',  'Two sheets inside',               'Open it, and you will find two sheets. Quotes and Orders has one row per job, ending with what has been received and what is still outstanding. Sort by Outstanding, and it doubles as your who still owes me list. Line items has one row per blind: room, product, fabric, colour, width, drop and price.', 6],
            ['7',  'The PDF summary',                 'Download PDF summary gives you something to print instead. A4, landscape, with a line per order: its total, what has been received, and what is outstanding. It is handy for filing, or handing over. But it is not your backup. You cannot sort it or work in it, and it never counts as one.', 7],
            ['8',  'The box that keeps score',        'Now watch the box underneath. Until your first full backup, it just explains itself. Take a full Excel, with no dates, and it changes. It shows the date and time of your last full backup, with a button beside it for just the changes since. Only a full Excel moves that date. A dated one does not, and nor does the PDF.', 8],
            ['9',  'Changes since: a different clock', 'That changes button works on a different clock. It picks up every job created, accepted or edited since your last full backup, whatever its order date. So an order from March that you tweaked yesterday is in there. It also ignores the From and To boxes completely.', 9],
            ['10', 'What it is not',                  'Two last things. This file is your quotes and orders only. It is not your customer list, your products or your price tables, and you cannot load it back in. And once it is downloaded, get it off the computer: onto a memory stick, a cloud drive, or emailed to yourself. Do it once a week.', 10],
        ],
];
