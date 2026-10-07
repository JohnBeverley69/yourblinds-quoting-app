<?php
declare(strict_types=1);

/**
 * Guide: accounts-to-package — "Get your figures into Xero, QuickBooks or Sage" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * The CSV route — the only one built today (a live accounting link is on
 * hold, so it is not described here). Sources:
 *   accounts/index.php     — the three export buttons, their tooltips, the note
 *                            under them, the date filter + This/Last month
 *   accounts/export.php    — ?type=invoices / quickbooks / payments: columns,
 *                            AccountCode 200, "20% (VAT on Income)" / "No VAT",
 *                            14-day due date, net unit price, the
 *                            "Discount — agreed price" / "Adjustment" line,
 *                            invoices dated by order (accepted) date, payments
 *                            by date received, filenames <company>-<type>-<date>.csv
 *   accounts/_qbo_export.php — QuickBooks shape: no minus lines (folded in),
 *                            VAT codes "20.0% S" / "5.0% R" / "No VAT", item
 *                            "Blinds", ≤100 invoices / 1,000 rows per file → .zip
 *                            of "-part-N-of-M.csv" + "READ ME.txt"
 * The package-side steps (Xero / QuickBooks / Sage / FreeAgent screens) come
 * from the vendors' own help as checked 26 Sep 2026 — re-check if they change.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$swap = static fn (string $from, string $to, float $at): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $at . 's">' . $from . '</span><span class="a-fade" style="--d:' . $at . 's">' . $to . '</span></span>';

// A spreadsheet mock: $cols = headings, $rows = [ [cells], 'cls' => …, 'st' => … ].
$sheet = static function (string $name, array $cols, array $rows, string $tpl, string $cls = '', string $style = ''): string {
    $h = '<div class="xl ' . $cls . '" style="' . $style . '"><div class="xh">' . $name . '</div><div class="xg" style="grid-template-columns:' . $tpl . '">';
    foreach ($cols as $c) $h .= '<span class="hd">' . $c . '</span>';
    foreach ($rows as $r) {
        $rc = $r['cls'] ?? ''; $rs = $r['st'] ?? '';
        foreach ($r[0] as $cell) $h .= '<span class="' . $rc . '" style="' . $rs . '">' . $cell . '</span>';
    }
    return $h . '</div></div>';
};

$file = static fn (string $name, string $cls = '', string $style = '', string $ico = 'CSV'): string =>
    '<div class="fil ' . $cls . '" style="' . $style . '"><span class="fic">' . $ico . '</span><span class="fnm">' . $name . '</span></div>';

$invCols = ['InvoiceNumber', 'InvoiceDate', 'DueDate', 'Description', 'Quantity', 'UnitAmount', 'AccountCode', 'TaxType'];
$invTpl  = '1.25fr .95fr .95fr 2.6fr .7fr .9fr .8fr 1.5fr';
$invRow  = static fn (string $desc, string $amt): array => ['BEV-2026-0042', '07/10/2026', '21/10/2026', $desc, '1', $amt, '200', '20% (VAT on Income)'];
$d1 = 'Roller Blind &mdash; Standard / Bloc Grey / (Lounge)';
$d2 = 'Roller Blind &mdash; Standard / Bloc Grey / (Kitchen)';

$qboCols = ['InvoiceNo', 'Customer', 'Item(Product/Service)', 'ItemDescription', 'ItemQuantity', 'ItemRate', 'ItemAmount', 'ItemTaxCode'];
$qboTpl  = '1.2fr 1.1fr 1.1fr 2fr .8fr 1fr .9fr .9fr';

$payCols = ['Date', 'InvoiceNumber', 'Customer', 'Amount', 'Method', 'Reference', 'Type'];
$payTpl  = '.95fr 1.25fr 1.15fr .8fr 1fr .95fr .8fr';

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Get your figures into Xero, QuickBooks or Sage',
        'eyebrow' => 'Accounts · Exports',
        'v'       => 2,
        'blurb'   => 'Download your sales and payments as spreadsheet files from the Payments page and bring them into Xero, QuickBooks Online, Sage or FreeAgent — what is in each file, and the traps for each package.',
        'lede'    => 'Your accounts package needs your sales. The <b>Payments</b> page gives you <b>three spreadsheet files</b> (CSV) for it:
                      the <b>invoices</b>, the same invoices <b>shaped for QuickBooks Online</b>, and the <b>payments</b> received. Your
                      package imports the invoices, and the payments are <b>matched from your bank feed</b>. This guide goes through what is
                      in each file, slowly, one idea per chapter. Do it once a week or once a month &mdash; whatever suits your bookkeeping.',
        'open'    => '/accounts/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink);
                     border-radius:7px; padding:.26rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; } .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .ph{ display:flex; justify-content:space-between; gap:.6rem; flex-wrap:wrap; align-items:flex-start; margin-bottom:.4rem; }
          .gd .pt{ font-size:1rem; font-weight:800; color:var(--ink); } .gd .ps{ font-size:.66rem; color:var(--faint); }
          .gd .pb{ display:flex; gap:.3rem; flex-wrap:wrap; }
          .gd .pnote{ font-size:.62rem; color:var(--faint); line-height:1.45; max-width:36rem; margin-bottom:.5rem; }

          /* files */
          .gd .files{ display:flex; flex-direction:column; gap:.35rem; margin-top:.6rem; }
          .gd .fil{ display:flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:8px; padding:.3rem .55rem; background:var(--surface);
                    font-size:.68rem; color:var(--ink); max-width:30rem; }
          .gd .fic{ flex:0 0 auto; font-size:.52rem; font-weight:800; color:#fff; background:#1d6f42; border-radius:4px; padding:.2rem .3rem; }
          .gd .fil.zip .fic{ background:#7c3aed; } .gd .fil.txt .fic{ background:#64748b; }
          .gd .fnm{ font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.64rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
          .gd .indent{ margin-left:1.4rem; }

          /* spreadsheet */
          .gd .xl{ border:1px solid #1d6f42; border-radius:8px; overflow:hidden; background:#fff; color:#1f2937; }
          .gd .xl .xh{ background:#1d6f42; color:#fff; font-weight:700; padding:.28rem .5rem; font-size:.62rem; font-family:ui-monospace,Menlo,Consolas,monospace;
                       white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .xg{ display:grid; }
          .gd .xg > span{ border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:.22rem .3rem; font-size:.58rem; white-space:nowrap;
                        overflow:hidden; text-overflow:ellipsis; font-variant-numeric:tabular-nums; min-width:0; }
          .gd .xg > span.hd{ background:#f3f4f6; font-weight:700; color:#374151; }
          .gd .xg > span.neg{ color:#b91c1c; font-weight:700; background:#fef2f2; }
          .gd .xg > span.grp{ background:#eef6f1; }
          /* a row that fades AND folds away (the minus line QuickBooks won’t take) */
          .gd .xg span.shrink{ max-height:3rem; }
          .gd .gd-play .xg span.shrink{ animation:apShrink .7s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .xg span.shrink{ display:none; }
          @keyframes apShrink{ to{ opacity:0; max-height:0; padding-top:0; padding-bottom:0; border-width:0; } }

          /* filter */
          .gd .flt{ border:1px dashed var(--border-strong,#c7ccd4); border-radius:10px; padding:.45rem .65rem .6rem; }
          .gd .flt .ft{ font-size:.58rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
          .gd .fb{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:flex-end; }
          .gd .inp{ display:inline-flex; align-items:center; min-width:6.5rem; height:26px; box-sizing:border-box; border:1px solid var(--border-strong,#c7ccd4);
                    border-radius:6px; background:var(--surface); padding:0 .45rem; font-size:.7rem; color:var(--ink); white-space:nowrap; }
          .gd .phd{ color:var(--faint); }
          .gd .fd{ display:flex; flex-direction:column; gap:.1rem; font-size:.58rem; color:var(--faint); }
          .gd .fb .selectbox{ min-width:7rem; height:26px; padding:.15rem .45rem; font-size:.68rem; box-sizing:border-box; }
          .gd .dim{ opacity:.45; position:relative; }
          .gd .xmark{ position:absolute; right:-.3rem; top:-.5rem; font-size:.58rem; font-weight:800; color:#fff; background:var(--err); border-radius:999px; padding:.05rem .35rem; }

          /* package panels */
          .gd .pkg{ border:1px solid var(--line); border-radius:10px; padding:.55rem .75rem; background:var(--surface); max-width:30rem; }
          .gd .pkg h4{ margin:0 0 .4rem; font-size:.8rem; color:var(--ink); }
          .gd .radio{ font-size:.74rem; margin:.15rem 1rem .15rem 0; }
          .gd .drafts{ display:flex; flex-direction:column; gap:.25rem; margin-top:.5rem; }
          .gd .drafts div{ display:flex; justify-content:space-between; align-items:center; font-size:.68rem; border:1px solid var(--line); border-radius:6px; padding:.25rem .5rem; }
          .gd .tag{ font-size:.58rem; font-weight:800; border-radius:999px; padding:.05rem .45rem; background:var(--panel); color:var(--soft); }
          .gd .tag.ok{ background:var(--good-wash); color:var(--good); }

          /* bank match */
          .gd .match{ position:relative; display:grid; grid-template-columns:1fr 3rem 1fr; align-items:center; gap:.3rem; margin-top:.7rem; max-width:34rem; }
          .gd .bank, .gd .inv{ border:1px solid var(--line); border-radius:8px; padding:.4rem .55rem; font-size:.66rem; background:var(--surface); line-height:1.4; }
          .gd .bank b, .gd .inv b{ color:var(--ink); }
          .gd .match svg{ width:100%; height:24px; }
          .gd .match path{ stroke:var(--good); stroke-width:3; fill:none; }
          .gd .ticks{ display:flex; flex-direction:column; gap:.45rem; font-size:.76rem; color:var(--ink); }
          .gd .ticks > div{ display:flex; gap:.5rem; align-items:center; }
          .gd .tick.on{ background:var(--accent); color:#fff; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:minmax(0,1fr); } .gd .side{ display:none; } .gd .stage{ min-width:0; overflow:hidden; }
            .gd .sc{ min-height:470px; }
            .gd .xl{ overflow-x:hidden; } .gd .xg > span{ font-size:.5rem; padding:.18rem .2rem; }
            .gd .xg .m{ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / accounts</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a><a class="on">Payments</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="ph"><div><div class="pt">Payments</div><div class="ps">Payments received against your orders.</div></div></div>
                  <div class="pb"><span class="btns">Export invoices (CSV)</span><span class="btns">Export for QuickBooks (CSV)</span><span class="btns">Export payments (CSV)</span><span class="btnp">+ Record payment</span></div>
                  <div class="files">
                    ' . $file('Beverley-Blinds-invoices-2026-10-07.csv') . $file('Beverley-Blinds-quickbooks-invoices-2026-10-07.csv') . $file('Beverley-Blinds-payments-2026-10-07.csv') . '
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; nine short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — three files -->
                <div class="sc" data-scene="1" data-len="24">
                  <div class="ph a-fade" style="--d:.3s"><div><div class="pt">Payments</div><div class="ps">Payments received against your orders.</div></div></div>
                  <div class="pb"><span class="btns a-ring" style="--d:6.5s">Export invoices (CSV)</span><span class="btns a-ring" style="--d:8s">Export for QuickBooks (CSV)</span><span class="btns a-ring" style="--d:10s">Export payments (CSV)</span><span class="btnp">+ Record payment</span></div>
                  <div class="files">
                    ' . $file('Beverley-Blinds-invoices-2026-10-07.csv', 'a-drop', '--d:7s')
                      . $file('Beverley-Blinds-quickbooks-invoices-2026-10-07.csv', 'a-drop', '--d:8.8s')
                      . $file('Beverley-Blinds-payments-2026-10-07.csv', 'a-drop', '--d:10.8s') . '
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:13.5s">Admins only</span><span class="chip a-pop" style="--d:15s">&#11088; Accounts feature</span>
                    <span class="chip ok a-pop" style="--d:19s">Readable by your package &mdash; or your bookkeeper</span></div>
                </div>

                <!-- 2 — pick the period -->
                <div class="sc" data-scene="2" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Pick the period first</div>
                  <div class="flt a-rise" style="--d:1s"><div class="ft">Filter the list</div>
                    <div class="fb">
                      <span class="inp dim a-fade" style="--d:10s;min-width:9rem"><span class="phd">Customer, quote #, reference...</span><i class="xmark">ignored</i></span>
                      <span class="fd">From<span class="inp a-ring" style="--d:6s">' . $swap('<span class="phd">dd/mm/yyyy</span>', '01/09/2026', 4.5) . '</span></span>
                      <span class="fd">To<span class="inp a-ring" style="--d:6.5s">' . $swap('<span class="phd">dd/mm/yyyy</span>', '30/09/2026', 4.5) . '</span></span>
                      <span class="btns">This month</span><span class="btns a-sel" style="--d:3.5s">Last month</span>
                      <span class="selectbox dim a-fade" style="--d:11s">All methods<i class="xmark">ignored</i></span>
                    </div></div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:8s">The files follow <b>From</b> and <b>To</b> &mdash; nothing else</span>
                    <span class="chip ok a-pop" style="--d:17s">&#128197; Same day every month &mdash; nothing missed, nothing twice</span></div>
                </div>

                <!-- 3 — the invoices file -->
                <div class="sc" data-scene="3" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">The invoices file</div>
                  ' . $sheet('Beverley-Blinds-invoices-2026-10-07.csv', $invCols, [
                        ['cls' => 'grp a-fly', 'st' => '--d:2s', $invRow($d1, '300.00')],
                        ['cls' => 'grp a-fly', 'st' => '--d:3s', $invRow($d2, '250.00')],
                        ['cls' => 'a-fly', 'st' => '--d:4s', ['BEV-2026-0043', '08/10/2026', '22/10/2026', 'Vertical Blind &mdash; Louvolite / Cream / (Office)', '2', '95.00', '200', '20% (VAT on Income)']],
                    ], $invTpl, 'a-rise', '--d:.8s') . '
                  <div class="chips"><span class="chip a-pop" style="--d:5.5s">One row per blind</span><span class="chip a-pop" style="--d:8s">Same quote number = one invoice</span>
                    <span class="chip warn a-pop" style="--d:11.5s">Prices before VAT</span><span class="chip a-pop" style="--d:16s">200 = Sales &middot; 20% or No VAT</span><span class="chip a-pop" style="--d:22s">Due 14 days after the order</span></div>
                </div>

                <!-- 4 — the agreed-price line -->
                <div class="sc" data-scene="4" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">You agreed a lower price</div>
                  ' . $sheet('Beverley-Blinds-invoices-2026-10-07.csv', $invCols, [
                        [$invRow($d1, '300.00')],
                        [$invRow($d2, '250.00')],
                        ['cls' => 'neg a-drop', 'st' => '--d:4s', ['BEV-2026-0042', '07/10/2026', '21/10/2026', 'Discount &mdash; agreed price', '1', '-50.00', '200', '20% (VAT on Income)']],
                    ], $invTpl) . '
                  <div class="chips"><span class="chip a-pop" style="--d:2s">Lines &pound;550.00 &middot; agreed &pound;500.00</span><span class="chip ok a-pop" style="--d:9s">Invoice total = what the customer was charged</span>
                    <span class="chip ok a-pop" style="--d:14s">Xero: fine</span><span class="chip bad a-pop" style="--d:16s">QuickBooks: no minus lines</span></div>
                </div>

                <!-- 5 — into Xero -->
                <div class="sc" data-scene="5" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Into Xero</div>
                  <div class="pkg a-rise" style="--d:1s"><h4>Xero &mdash; Sales &rarr; Invoices &rarr; Import</h4>
                    ' . $file('Beverley-Blinds-invoices-2026-10-07.csv', 'a-fly', '--d:2.5s') . '
                    <div style="margin-top:.5rem"><span class="radio a-ring" style="--d:5s"><span class="dot"></span>Tax inclusive</span><span class="radio on a-ring" style="--d:6s"><span class="dot"></span>Tax exclusive</span></div>
                    <div class="drafts">
                      <div class="a-fly" style="--d:9.5s">BEV-2026-0042 &middot; Emma Fletcher &middot; &pound;600.00 ' . $swap('<span class="tag">Draft</span>', '<span class="tag ok">Approved</span>', 14.5) . '</div>
                      <div class="a-fly" style="--d:10s">BEV-2026-0043 &middot; Raj Patel &middot; &pound;228.00 ' . $swap('<span class="tag">Draft</span>', '<span class="tag ok">Approved</span>', 15) . '</div>
                    </div></div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:7s">Prices are before VAT</span><span class="chip a-pop" style="--d:17.5s">Code doesn&rsquo;t match yours? Change it on import</span></div>
                </div>

                <!-- 6 — the QuickBooks file -->
                <div class="sc" data-scene="6" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">The QuickBooks file</div>
                  ' . $sheet('Beverley-Blinds-quickbooks-invoices-2026-10-07.csv', $qboCols, [
                        [['BEV-2026-0042', 'Emma Fletcher', '<b class="a-ring" style="--d:16s">Blinds</b>', $d1, '1', $swap('300', '272.7273', 7), $swap('300.00', '272.73', 7), '<b class="a-ring" style="--d:13s">20.0% S</b>']],
                        [['BEV-2026-0042', 'Emma Fletcher', 'Blinds', $d2, '1', $swap('250', '227.2727', 7.3), $swap('250.00', '227.27', 7.3), '20.0% S']],
                        ['cls' => 'neg shrink', 'st' => '--d:6s', ['BEV-2026-0042', 'Emma Fletcher', 'Blinds', 'Discount &mdash; agreed price', '1', '-50', '-50.00', '20.0% S']],
                    ], $qboTpl, 'a-rise', '--d:.8s') . '
                  <div class="chips"><span class="chip ok a-pop" style="--d:9s">No minus lines &mdash; the discount is spread across the lines</span>
                    <span class="chip a-pop" style="--d:13.5s">QuickBooks&rsquo; own VAT codes</span><span class="chip warn a-pop" style="--d:19s">Make an item called <b>Blinds</b> first &mdash; or let the import add it</span></div>
                </div>

                <!-- 7 — big months: a zip -->
                <div class="sc" data-scene="7" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">A big period comes as a zip</div>
                  <div class="chips" style="margin-top:0"><span class="chip a-pop" style="--d:1.5s">QuickBooks: up to 100 invoices &middot; 1,000 rows per file</span></div>
                  <div class="files">
                    ' . $file('Beverley-Blinds-quickbooks-invoices-2026-10-07.zip', 'zip a-drop', '--d:6s', 'ZIP')
                      . $file('Beverley-Blinds-quickbooks-invoices-2026-10-07-part-1-of-3.csv', 'indent a-fly', '--d:9s')
                      . $file('Beverley-Blinds-quickbooks-invoices-2026-10-07-part-2-of-3.csv', 'indent a-fly', '--d:9.8s')
                      . $file('Beverley-Blinds-quickbooks-invoices-2026-10-07-part-3-of-3.csv', 'indent a-fly', '--d:10.6s')
                      . $file('READ ME.txt', 'txt indent a-fly', '--d:12.5s', 'TXT') . '
                  </div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:16s">QuickBooks: Settings &#9881; &rarr; Import data &rarr; Invoices &mdash; one part at a time</span></div>
                </div>

                <!-- 8 — the payments file -->
                <div class="sc" data-scene="8" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">The payments file</div>
                  ' . $sheet('Beverley-Blinds-payments-2026-10-07.csv', $payCols, [
                        ['cls' => 'a-fly', 'st' => '--d:2s', ['07/10/2026', 'BEV-2026-0042', 'Emma Fletcher', '330.00', 'Deposit', 'Deposit', '<b class="a-ring" style="--d:9s">Deposit</b>']],
                        ['cls' => 'a-fly', 'st' => '--d:2.8s', ['21/10/2026', 'BEV-2026-0042', 'Emma Fletcher', '270.00', 'Bank transfer', 'FT 1021', 'Payment']],
                    ], $payTpl, 'a-rise', '--d:.8s') . '
                  <div class="chips"><span class="chip bad a-pop" style="--d:11.5s">No package imports payments from a file</span></div>
                  <div class="match">
                    <div class="bank a-fly" style="--d:15s">&#127974; Bank feed<br><b>FASTER PAYMENT E FLETCHER BEV-2026-0042</b> &pound;270.00</div>
                    <svg viewBox="0 0 100 24" preserveAspectRatio="none"><path class="a-draw" style="--d:18s" pathLength="100" d="M2 12 H98"/></svg>
                    <div class="inv a-fly" style="--d:16.5s">&#129534; Invoice <b>BEV-2026-0042</b><br>Emma Fletcher</div>
                  </div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:20.5s">The quote number is the clue</span></div>
                </div>

                <!-- 9 — good habits -->
                <div class="sc" data-scene="9" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Good habits</div>
                  <div class="ticks">
                    <div class="a-fly" style="--d:2.5s"><span class="tick on">&#10003;</span><span>The same period every time &mdash; <b>Last month</b>, on the 1st</span></div>
                    <div class="a-fly" style="--d:5s"><span class="tick on">&#10003;</span><span>Keep each month&rsquo;s files in a folder</span></div>
                    <div class="a-fly" style="--d:10s"><span class="tick on">&#10003;</span><span>Check: invoices file + VAT = your own figures for that month</span></div>
                    <div class="a-fly" style="--d:16s"><span class="tick on">&#10003;</span><span>Code <b>200</b> not right for you? Change it on import</span></div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>The three files.</b> Open <b>Payments</b> in the left-hand menu (under <b>Retail</b>). If it isn&rsquo;t there, the
             <b>Accounts</b> feature (part of the <b>Gold</b> plan) isn&rsquo;t on your account. The three export buttons, top right, are only
             shown to <b>admins</b>. The note under them says it too: <em>&ldquo;CSV for Xero / QuickBooks / Sage. Respects the date filter
             below.&rdquo;</em></p>
          <ul class="steps">
            <li><b>Pick the period.</b> In <b>Filter the list</b>, press <b>Last month</b> (or type your own <b>From</b> and <b>To</b> dates).
                The files follow <b>only those two dates</b> &mdash; not the search box and not the method. The invoice files pick orders by
                their <b>order date</b> (when accepted); the payments file picks payments by the <b>date received</b>. Do it the same way each
                time &mdash; say, on the 1st of every month &mdash; so nothing is missed or sent twice.</li>
            <li><b>Export invoices (CSV)</b> (<em>&ldquo;One row per order line &mdash; import as sales invoices&rdquo;</em>) downloads
                <code>&lt;Your-Company&gt;-invoices-&lt;date&gt;.csv</code> with the columns <b>ContactName, EmailAddress, InvoiceNumber,
                InvoiceDate, DueDate, Description, Quantity, UnitAmount, AccountCode, TaxType</b>. One row per blind; every row with the same
                quote number is one invoice. Every order that is accepted or beyond is included. Prices are <b>before VAT</b> &mdash; your
                package adds it. Each row has account code <code>200</code> (Sales) and <code>20% (VAT on Income)</code>, or
                <code>No VAT</code> if the order had none. The due date is <b>14 days</b> after the order date.</li>
            <li><b>The agreed-price line.</b> If you agreed a different total from the lines (<em>&ldquo;I&rsquo;ll do it for
                &pound;500&rdquo;</em>), the invoices file adds a line <b>&ldquo;Discount &mdash; agreed price&rdquo;</b> with a <b>minus</b>
                amount (or <b>&ldquo;Adjustment&rdquo;</b> if it was higher), so the invoice totals exactly what the customer was charged. Xero
                is happy with that; QuickBooks is not &mdash; hence its own button.</li>
            <li><b>Export for QuickBooks (CSV)</b> (<em>&ldquo;The same invoices, ready for QuickBooks Online &rarr; Import data &rarr;
                Invoices&rdquo;</em>) downloads <code>&lt;Your-Company&gt;-quickbooks-invoices-&lt;date&gt;.csv</code>: columns
                <b>InvoiceNo, Customer, InvoiceDate, DueDate, Item(Product/Service), ItemDescription, ItemQuantity, ItemRate, ItemAmount,
                ItemTaxCode</b>. <b>No minus lines</b> &mdash; an agreed-price difference is spread across that invoice&rsquo;s lines, so the total
                is still exactly what you charged. QuickBooks&rsquo; own VAT codes (<code>20.0% S</code>, <code>5.0% R</code>,
                <code>No VAT</code>), and the item <code>Blinds</code> on every line.</li>
            <li><b>Big periods.</b> QuickBooks takes at most <b>100 invoices / 1,000 rows</b> per file. Over that, you get a <b>.zip</b> of
                numbered files (<code>&hellip;-part-1-of-3.csv</code>, <code>&hellip;-part-2-of-3.csv</code>&hellip;) and a <b>READ ME.txt</b>.
                Import them one after another.</li>
            <li><b>Export payments (CSV)</b> (<em>&ldquo;Payments received &mdash; for your bookkeeper / accounting software&rdquo;</em>)
                downloads <code>&lt;Your-Company&gt;-payments-&lt;date&gt;.csv</code>: <b>Date, InvoiceNumber, Customer, Amount, Method,
                Reference, Type</b> &mdash; Type is <b>Deposit</b> or <b>Payment</b>. A standalone payment has no invoice number, and shows
                &ldquo;Customer&rdquo; as the name. It has no account codes.</li>
          </ul>

          <p><b>Xero &mdash; the easiest.</b> The invoices file is laid out the way Xero expects.</p>
          <ul class="steps">
            <li>In Xero go to <b>Sales &rarr; Invoices</b> and press <b>Import</b>. The first time, download Xero&rsquo;s template and check
                its column headings match ours (if Xero&rsquo;s have a star in front, like <code>*ContactName</code>, copy our rows under its
                headings). Don&rsquo;t delete columns or rename headings.</li>
            <li>Choose the invoices file. When asked, choose <b>Tax exclusive</b> &mdash; our prices are before VAT; getting this wrong puts every
                total out by the VAT.</li>
            <li>Import. The invoices arrive as <b>drafts</b>: check a couple against YourBlinds, then <b>Approve</b> them.</li>
          </ul>
          <p>Xero is fussy about: the <b>customer name</b> (it must match an existing contact exactly, or Xero makes a new one); <code>200</code>
             and <code>20% (VAT on Income)</code> existing in <em>your</em> Xero (they do in a standard UK setup); and no more than <b>500 rows</b>
             per file. An invoice number already in Xero is skipped, so importing the same month twice is harmless.</p>

          <p><b>QuickBooks Online.</b></p>
          <ul class="steps">
            <li><b>Once only:</b> make a <b>Service</b> item called <code>Blinds</code> in <b>Products and services</b>, pointing at your sales
                income account &mdash; or tick <em>add new products/services</em> during the import and check which income account it
                chose.</li>
            <li>Pick the period on the Payments page and press <b>Export for QuickBooks (CSV)</b>.</li>
            <li>In QuickBooks: <b>&#9881; Settings &rarr; Import data &rarr; Invoices</b>. Choose the file; the headings are QuickBooks&rsquo;
                own, so the column matching should line up by itself. Date format <b>D/M/YYYY</b>, VAT <b>Exclusive</b>. Check the summary and
                start the import; if an invoice fails, fix just that one.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Only put PAID sales into QuickBooks?</b> (cash accounting, as many
             bookkeepers do.) Then don&rsquo;t import invoices &mdash; it would create invoices that look owed. Use the <b>payments file</b> as
             your list and record each paid sale in QuickBooks, or ask your bookkeeper.</div></div>

          <p><b>Sage Accounting.</b> Sage&rsquo;s spreadsheet import now covers <b>purchase</b> invoices, and a sales import may not be offered.
             Either enter paid sales by hand from the payments file (<b>Sales &rarr; Quick entries</b>: date, customer, reference = the quote
             number, your sales ledger account, net, VAT <b>Standard</b>), then <b>Match</b> them to the bank feed &mdash; or hand both files to
             your bookkeeper. If your Sage does show a sales import, it wants <b>one row per invoice</b> and the customer&rsquo;s Sage
             <b>account reference</b>, so the file needs reshaping first.</p>
          <p><b>FreeAgent.</b> Only accountants and bookkeepers (on its Practice Partner scheme) can import invoices &mdash; send them both
             files. If you do your own FreeAgent, explain each bank receipt as an <b>Invoice Receipt</b>.</p>

          <p><b>Payments, in every package:</b> none of them imports customer payments from a file. When the money shows in your <b>bank
             feed</b>, match it to the invoice (Xero: <b>Reconcile &rarr; Find &amp; Match</b>; QuickBooks: under <b>Banking</b>). The quote
             number is the invoice number, and usually the customer&rsquo;s payment reference too. A deposit and a balance are two bank lines
             against the same invoice.</p>

          <p><b>Good habits.</b> Same period every time (<b>Last month</b>, on the 1st). Keep the files in a folder by month. Check the
             totals: the invoices file plus VAT should match YourBlinds&rsquo; figures for the same month. Code <code>200</code> not right
             (Sage uses 4000-style codes)? Change it in Excel or remap it on import.</p>',
        'script'  => [
            ['1', 'Three files from the Payments page', 'Your accounts package needs your sales. The Payments page gives you three spreadsheet files for it, at the top right. Export invoices. Export for QuickBooks. And Export payments. Only admins see these buttons, and they come with the Accounts feature. Each one downloads a file that your package, or your bookkeeper, can read.', 1],
            ['2', 'Pick the period first',              'First, choose the dates. In Filter the list, press Last month, or type your own From and To dates. The files follow those two dates, and nothing else, so the search box and the method make no difference. Do it the same way every time, say on the first of the month, so nothing is missed, and nothing is sent twice.', 2],
            ['3', 'The invoices file',                  'Export invoices gives you your sales. There is one row for each blind, and every row with the same quote number makes one invoice. The prices are before VAT, so your package adds the VAT itself. Each row goes to account code two hundred, sales, at twenty percent VAT, or no VAT if the order had none. And the due date is fourteen days after the order.', 3],
            ['4', 'You agreed a lower price',           'If you agreed a lower price than the blinds add up to, the file adds one more line to that invoice: Discount, agreed price, with a minus amount. That way, the invoice total matches exactly what the customer was charged. Xero is happy with a minus line. QuickBooks is not, which is why it has a button of its own.', 4],
            ['5', 'Into Xero',                          'In Xero, import the invoices file as sales invoices. When it asks, choose tax exclusive, because the prices are before VAT. The invoices arrive as drafts. Check one or two against YourBlinds, then approve them. If a code does not match your own accounts, change it as you import.', 5],
            ['6', 'The QuickBooks file',                'Export for QuickBooks gives you the same invoices, in QuickBooks\' own layout. There are no minus lines. An agreed discount is spread across that invoice\'s lines instead, so the total still matches. It uses QuickBooks\' own VAT codes, and puts the item Blinds on every line. So make an item called Blinds in QuickBooks first, or let the import add it.', 6],
            ['7', 'A big period comes as a zip',        'QuickBooks takes no more than a hundred invoices in one file. If your dates cover more than that, you get a zip file instead. Inside are numbered parts: part one of three, part two of three, and so on, with a short read me note. In QuickBooks, import them one after another, under Import data, Invoices.', 7],
            ['8', 'The payments file',                  'Export payments lists the money that came in: the date, the quote number, the customer, the amount, the method, the reference, and whether it was a deposit or a payment. No accounts package imports payments from a file. Use it as your checklist, and match each payment to its invoice from your bank feed. The quote number is the clue.', 8],
            ['9', 'Good habits',                        'A few good habits. Use the same period every time. Keep each month\'s files in a folder, so your bookkeeper can see what went in. Check the totals: the invoices file, plus VAT, should match your own figures for that month. And if code two hundred is not right for you, change it on import.', 9],
        ],
];
