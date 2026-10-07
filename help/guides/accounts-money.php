<?php
declare(strict_types=1);

/**
 * Guide: accounts-money — "The Payments page (Accounts)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Covers /accounts/index.php — the tenant-wide payments ledger (sidebar
 * Retail -> Payments, needs the Accounts feature = Gold plan, and the user's
 * "Can see money"). The sister guide quote-payments covers the money panels
 * ON one order; accounts-to-package covers the CSV exports in detail.
 * Labels and messages are copied from accounts/index.php, payment_save.php,
 * payment_delete.php, accounts/_helpers.php and _partials/confirm_modal.php.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// Swap one bit of text for another at --d (stacked in one grid cell).
$swap = static fn (string $from, string $to, float $at, string $cls = ''): string =>
    '<span class="sw ' . $cls . '"><span class="a-out" style="--d:' . $at . 's">' . $from . '</span>'
  . '<span class="a-fade" style="--d:' . $at . 's">' . $to . '</span></span>';

/**
 * One closed payment-history card. $verdict is the right-hand status bit.
 * $anim/$d: animation class + start for the whole card.
 */
$card = static function (string $name, ?string $quote, ?string $total, string $paid, int $n, string $verdict, string $cls = '', string $style = ''): string {
    return '<div class="pgc ' . $cls . '" style="' . $style . '"><span class="car">&#9656;</span><b class="cu">' . $name . '</b>'
         . ($quote ? '<span class="ql">' . $quote . '</span>' : '<i class="sa">Standalone</i>')
         . '<span class="mo">' . ($total ? '<span>Total <b>' . $total . '</b></span>' : '')
         . '<span>Paid <b>' . $paid . '</b> <small>(' . $n . ')</small></span>' . $verdict . '</span></div>';
};
$owed  = static fn (string $v): string => '<span class="ow">Owed <b>' . $v . '</b></span>';
$full  = '<span class="fp">&check; Fully paid</span>';
$over  = static fn (string $v): string => '<span class="ov">Overpaid <b>' . $v . '</b></span>';

// The detail table inside an opened card.
$detail = static fn (string $rows): string =>
    '<div class="pgd"><div class="tb"><span class="h">Date</span><span class="h">Method</span><span class="h xs">Reference</span><span class="h r">Amount</span><span class="h"></span>' . $rows . '</div></div>';
$depRow = '<span class="dep">7 Oct 2026</span><span class="dep"><i class="mp">Deposit</i></span><span class="dep xs">Deposit</span><span class="dep r">&pound;330.00</span><span class="dep"><em class="mo2">managed on the order</em></span>';
$payRow = static fn (string $date, string $method, string $ref, string $amt, string $btnCls = ''): string =>
    '<span>' . $date . '</span><span><i class="mp">' . $method . '</i></span><span class="xs">' . $ref . '</span><span class="r">' . $amt . '</span>'
  . '<span class="bt"><i class="ed ' . $btnCls . '">Edit</i><i class="btnx">&times;</i></span>';

// Page heading strip (title, subtitle, buttons).
$top = static fn (string $extra = ''): string =>
    '<div class="ph"><div><div class="pt">Payments</div><div class="ps">Payments received against your orders.</div></div>'
  . '<div class="pb"><span class="btns">Export invoices (CSV)</span><span class="btns">Export for QuickBooks (CSV)</span><span class="btns">Export payments (CSV)</span>'
  . '<span class="btnp ' . $extra . '">+ Record payment</span></div></div>';

$sumCards = static fn (string $o = '&pound;4,870.00', string $m = '&pound;2,315.00', string $a = '&pound;61,240.50', array $c = ['', '', '']): string =>
    '<div class="sum3"><div class="scd out ' . $c[0] . '"><div class="l">Outstanding</div><div class="v">' . $o . '</div></div>'
  . '<div class="scd mon ' . $c[1] . '"><div class="l">Received this month</div><div class="v">' . $m . '</div></div>'
  . '<div class="scd all ' . $c[2] . '"><div class="l">All-time received</div><div class="v">' . $a . '</div></div></div>';

// The record/edit form fields (Payments page version).
$form = static function (array $v): string {
    $f = static fn (string $cls, string $label, string $inner) => '<div class="f ' . $cls . '"><label>' . $label . '</label>' . $inner . '</div>';
    return '<div class="npg">'
        . $f('f1', 'Order (optional)', '<span class="selectbox ' . ($v['oc'] ?? '') . '" style="' . ($v['os'] ?? '') . '">' . $v['order'] . '</span>' . ($v['note'] ?? ''))
        . $f('', 'Amount &pound;', '<span class="inp ' . ($v['ac'] ?? '') . '" style="' . ($v['as'] ?? '') . '">' . $v['amount'] . '</span>')
        . $f('', 'Received on', '<span class="inp ' . ($v['dc'] ?? '') . '" style="' . ($v['ds'] ?? '') . '">' . ($v['date'] ?? '07/10/2026') . '</span>')
        . $f('', 'Method', '<span class="selectbox ' . ($v['mc'] ?? '') . '" style="' . ($v['ms'] ?? '') . '">' . ($v['method'] ?? 'Bank transfer') . '</span>')
        . $f('f2', 'Received from (optional)', '<span class="inp ' . ($v['pc'] ?? '') . '" style="' . ($v['pst'] ?? '') . '">' . ($v['payer'] ?? '<span class="phd">Who paid &mdash; e.g. customer name</span>') . '</span>')
        . $f('f3', 'Reference (optional)', '<span class="inp ' . ($v['rc'] ?? '') . '" style="' . ($v['rs'] ?? '') . '">' . ($v['ref'] ?? '<span class="phd">e.g. cheque #, Stripe id...</span>') . '</span>')
        . '</div>';
};
$depNote = static fn (string $style): string =>
    '<div class="depnote a-fade" style="' . $style . '">&check; Deposit of &pound;330.00 is already recorded on this order &mdash; the amount above is the remaining balance, so don&rsquo;t re-enter the deposit.</div>';

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'The Payments page (Accounts)',
        'eyebrow' => 'Accounts',
        'v'       => 2,
        'blurb'   => 'Every payment your business has taken, in one place: what is still owed, what came in this month, recording and correcting payments, and finding any payment again.',
        'lede'    => 'One page for all the money: the <b>Outstanding</b>, <b>Received this month</b> and <b>All-time received</b> figures at the
                      top, every payment underneath grouped by the order it belongs to, and a <b>+ Record payment</b> box for money that
                      arrives in the post or the bank. This guide goes through it slowly, one idea per chapter. Find it in the menu under
                      <b>Retail &rarr; Payments</b>. It comes with the <b>Accounts</b> feature, part of the <b>Gold</b> plan.',
        'open'    => '/accounts/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:400px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          .gd .stk{ display:grid; } .gd .stk > *{ grid-area:1/1; }
          /* fades AND folds away (a deleted row) */
          .gd .shrink{ max-height:4rem; overflow:hidden; }
          .gd .gd-play .shrink{ animation:amShrink .7s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .shrink{ display:none; }
          @keyframes amShrink{ to{ opacity:0; max-height:0; padding-top:0; padding-bottom:0; border-width:0; } }

          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.26rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .btnx{ display:inline-grid; place-items:center; background:var(--err); color:#fff; border-radius:6px; width:1.3rem; height:1.2rem;
                     font-size:.72rem; font-weight:800; font-style:normal; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; } .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .flash{ border-radius:8px; padding:.4rem .65rem; font-size:.74rem; font-weight:700; margin-bottom:.55rem; }
          .gd .flash.ok{ background:var(--good-wash); color:var(--good); } .gd .flash.err{ background:var(--err-wash); color:var(--err); }

          /* page header */
          .gd .ph{ display:flex; justify-content:space-between; gap:.6rem; flex-wrap:wrap; align-items:flex-start; margin-bottom:.6rem; }
          .gd .pt{ font-size:1rem; font-weight:800; color:var(--ink); } .gd .ps{ font-size:.66rem; color:var(--faint); }
          .gd .pb{ display:flex; gap:.3rem; flex-wrap:wrap; justify-content:flex-end; }

          /* summary cards */
          .gd .sum3{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.5rem; margin-bottom:.6rem; }
          .gd .scd{ border:1px solid var(--line); border-radius:10px; padding:.45rem .65rem; background:var(--surface); }
          .gd .scd .l{ font-size:.56rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; }
          .gd .scd .v{ font-size:1.05rem; font-weight:800; font-variant-numeric:tabular-nums; margin-top:.1rem; }
          .gd .scd.out .v{ color:#b45309; } .gd .scd.mon .v{ color:#1f3b5b; } .gd .scd.all .v{ color:#065f46; }
          :root[data-theme="dark"] .gd .scd.out .v{ color:#fbbf24; } :root[data-theme="dark"] .gd .scd.mon .v{ color:#93c5fd; } :root[data-theme="dark"] .gd .scd.all .v{ color:#86efac; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .scd.out .v{ color:#fbbf24; } :root:not([data-theme="light"]) .gd .scd.mon .v{ color:#93c5fd; } :root:not([data-theme="light"]) .gd .scd.all .v{ color:#86efac; } }
          .gd .stl{ display:flex; gap:.3rem; flex-wrap:wrap; margin-top:.5rem; }
          .gd .stl span{ font-size:.62rem; font-weight:700; border-radius:999px; padding:.1rem .5rem; color:#fff; background:#64748b; }
          .gd .month{ position:relative; display:grid; grid-template-columns:repeat(31,1fr); gap:2px; max-width:26rem; margin-top:.6rem; }
          .gd .month i{ height:14px; border-radius:2px; background:var(--line); }
          .gd .month .fill{ position:absolute; left:0; top:0; height:14px; width:22.6%; border-radius:2px; background:#1f3b5b; opacity:.85; }
          .gd .mlab{ display:flex; justify-content:space-between; max-width:26rem; font-size:.6rem; color:var(--faint); margin-top:.2rem; }

          /* history cards */
          .gd .sech{ font-size:.82rem; font-weight:800; color:var(--ink); margin:.2rem 0 .4rem; }
          .gd .pgs{ display:flex; flex-direction:column; gap:.3rem; }
          .gd .pgc{ display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; border:1px solid var(--line); border-radius:8px; background:var(--surface);
                    padding:.35rem .6rem; font-size:.74rem; }
          .gd .pgc.paid{ background:var(--good-wash); border-color:color-mix(in srgb,var(--good) 40%,transparent); }
          .gd .pgc.open{ border-color:var(--accent); border-bottom-left-radius:0; border-bottom-right-radius:0; }
          .gd .pgc .car{ color:var(--faint); font-size:.66rem; }
          .gd .pgc.open .car{ color:var(--accent); }
          .gd .pgc .cu{ color:var(--ink); }
          .gd .pgc .ql{ background:var(--panel); color:var(--accent); font-weight:700; border-radius:4px; padding:.05rem .4rem; font-size:.66rem; }
          .gd .pgc .sa{ color:var(--faint); font-size:.68rem; }
          .gd .pgc .mo{ margin-left:auto; display:flex; gap:.7rem; flex-wrap:wrap; color:var(--soft); font-size:.7rem; align-items:baseline; }
          .gd .pgc .mo b{ color:var(--ink); font-variant-numeric:tabular-nums; } .gd .pgc .mo small{ color:var(--faint); }
          .gd .ow, .gd .ow b{ color:#92400e !important; } .gd .fp{ color:var(--good); font-weight:700; } .gd .ov, .gd .ov b{ color:#1e40af !important; }
          :root[data-theme="dark"] .gd .ow, :root[data-theme="dark"] .gd .ow b{ color:#fbbf24 !important; }
          :root[data-theme="dark"] .gd .ov, :root[data-theme="dark"] .gd .ov b{ color:#93c5fd !important; }
          .gd .pgd{ border:1px solid var(--accent); border-top:0; border-radius:0 0 8px 8px; background:var(--panel); padding:.35rem .5rem .45rem; }
          .gd .tb{ display:grid; grid-template-columns:1fr 1fr 1fr .9fr auto; border:1px solid var(--line); border-radius:7px; overflow:hidden; font-size:.68rem; }
          .gd .tb > span{ padding:.28rem .45rem; border-top:1px solid var(--line); background:var(--surface); color:var(--ink); white-space:nowrap;
                          overflow:hidden; text-overflow:ellipsis; font-variant-numeric:tabular-nums; }
          .gd .tb > span.h{ background:var(--panel); color:var(--soft); font-weight:700; font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; border-top:0; }
          .gd .tb > span.r{ text-align:right; }
          .gd .tb > span.dep{ background:var(--good-wash); }
          .gd .mp{ display:inline-block; font-style:normal; font-size:.56rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
                   border-radius:999px; padding:.02rem .45rem; background:var(--accent-wash); color:var(--accent-ink); }
          .gd .mo2{ color:var(--faint); font-size:.62rem; }
          .gd .bt{ display:flex; gap:.25rem; align-items:center; }
          .gd .ed{ font-style:normal; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:.02rem .4rem; font-size:.62rem; font-weight:600; background:var(--surface); }
          .gd .lbl{ position:absolute; z-index:4; font-size:.62rem; font-weight:700; color:#fff; background:#0f172a; border-radius:6px; padding:.15rem .45rem; }

          /* record / edit panel */
          .gd .np{ border:1px solid var(--line); border-radius:10px; padding:.45rem .7rem .6rem; background:var(--surface); margin-bottom:.55rem; }
          .gd .np .sm{ color:var(--accent); font-weight:700; font-size:.76rem; padding-bottom:.35rem; border-bottom:1px solid var(--line); margin-bottom:.45rem; }
          .gd .npg{ display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:.4rem .5rem; }
          .gd .npg .f{ position:relative; min-width:0; }
          .gd .npg .f label{ display:block; font-size:.55rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.15rem; white-space:nowrap; }
          .gd .npg .f2{ grid-column:span 2; } .gd .npg .f3{ grid-column:span 2; }
          .gd .inp{ display:flex; align-items:center; width:100%; box-sizing:border-box; height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    background:var(--surface); padding:0 .45rem; font-size:.7rem; color:var(--ink); font-variant-numeric:tabular-nums; white-space:nowrap; overflow:hidden; }
          .gd .npg .selectbox{ width:100%; min-width:0; box-sizing:border-box; height:26px; font-size:.68rem; padding:.15rem .45rem; white-space:nowrap; overflow:hidden; }
          .gd .phd{ color:var(--faint); }
          .gd .depnote{ font-size:.62rem; color:#92400e; margin-top:.25rem; line-height:1.35; }
          :root[data-theme="dark"] .gd .depnote{ color:#fbbf24; }
          .gd .npa{ display:flex; gap:.4rem; margin-top:.5rem; }
          .gd .dd{ position:absolute; z-index:5; left:0; top:100%; width:100%; background:var(--surface); border:1px solid var(--line);
                   border-radius:7px; box-shadow:var(--gd-shadow); padding:.2rem 0; font-size:.64rem; }
          .gd .dd span{ display:block; padding:.14rem .5rem; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; } .gd .dd span.on{ background:var(--accent); color:#fff; }

          /* filter */
          .gd .flt{ border:1px dashed var(--border-strong,#c7ccd4); border-radius:10px; padding:.45rem .65rem .6rem; }
          .gd .flt .ft{ font-size:.58rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem; }
          .gd .fb{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:flex-end; }
          .gd .fb .inp{ width:auto; min-width:6.5rem; } .gd .fb .srch{ flex:1 1 9rem; }
          .gd .fd{ display:flex; flex-direction:column; gap:.1rem; font-size:.58rem; color:var(--faint); }
          .gd .fb .selectbox{ min-width:7rem; height:26px; padding:.15rem .45rem; font-size:.68rem; box-sizing:border-box; }

          /* misc */
          .gd .cfm{ position:absolute; z-index:6; left:16%; top:26%; width:19rem; max-width:84%; background:var(--surface); border:1px solid var(--line);
                    border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.74rem; color:var(--ink); line-height:1.4; }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.6rem; }
          .gd .cfm .yes{ background:var(--err); color:#fff; border-radius:7px; padding:.28rem .6rem; font-size:.7rem; font-weight:700; }
          .gd .note{ border:1px solid var(--line); border-radius:10px; padding:.55rem .7rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .note h4{ margin:0 0 .3rem; font-size:.78rem; color:var(--ink); }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin-top:.6rem; }
          .gd .gd-play .topaid{ animation:amPaid .6s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .topaid{ background:var(--good-wash); border-color:color-mix(in srgb,var(--good) 40%,transparent); }
          @keyframes amPaid{ to{ background:var(--good-wash); border-color:color-mix(in srgb,var(--good) 40%,transparent); } }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:minmax(0,1fr); } .gd .side{ display:none; } .gd .stage{ min-width:0; overflow:hidden; }
            .gd .sc{ min-height:560px; }
            .gd .sum3{ grid-template-columns:1fr; gap:.35rem; } .gd .scd{ display:flex; justify-content:space-between; align-items:center; }
            .gd .npg{ grid-template-columns:1fr 1fr; } .gd .npg .f1{ grid-column:span 2; }
            .gd .tb{ grid-template-columns:1fr 1fr .9fr auto; } .gd .tb .xs{ display:none; }
            .gd .pb .btns{ display:none; } .gd .two{ grid-template-columns:1fr; }
            .gd .pgc .mo{ margin-left:0; width:100%; }
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
                  ' . $top() . $sumCards() . '
                  <div class="sech">Payment history</div>
                  <div class="pgs">
                    ' . $card('Emma Fletcher', 'BEV-2026-0042', '&pound;660.00', '&pound;330.00', 1, $owed('&pound;330.00')) . '
                    ' . $card('Raj Patel', 'BEV-2026-0039', '&pound;1,240.00', '&pound;1,240.00', 3, $full, 'paid') . '
                    ' . $card('Sue Walker', 'BEV-2026-0035', '&pound;480.00', '&pound;485.00', 2, $over('&pound;5.00')) . '
                  </div>
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — one page for all the money -->
                <div class="sc" data-scene="1" data-len="21">
                  <div class="a-fade" style="--d:.3s">' . $top() . '</div>
                  <div class="two">
                    <div class="note a-rise" style="--d:5s"><h4>&#128203; On an order</h4>The Deposit and Payments panels: <b>one job&rsquo;s</b> money.</div>
                    <div class="note a-rise" style="--d:8.5s;border-color:var(--accent)"><h4>&#128202; This page</h4>The <b>whole business</b>: what is owed, what came in, every payment.</div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:2s">Retail &rarr; Payments</span><span class="chip a-pop" style="--d:17.5s">&#11088; Accounts feature &mdash; Gold plan</span></div>
                </div>

                <!-- 2 — Outstanding -->
                <div class="sc" data-scene="2" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Outstanding: what is still owed</div>
                  ' . $sumCards('&pound;4,870.00', '&pound;2,315.00', '&pound;61,240.50', ['a-ring" style="--d:2s', '', '']) . '
                  <p class="scs a-fade" style="--d:5s">Every order that has reached&hellip;</p>
                  <div class="stl"><span class="a-pop" style="--d:6.5s;background:#2563eb">accepted</span><span class="a-pop" style="--d:7s;background:#7c3aed">ordered</span>
                    <span class="a-pop" style="--d:7.5s;background:#0891b2">fitted</span><span class="a-pop" style="--d:8s;background:#d97706">invoiced</span><span class="a-pop" style="--d:8.5s;background:#059669">paid</span></div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:12s">The filter lower down does <b>not</b> change it</span><span class="chip a-pop" style="--d:18s">&#128222; High? Time to chase some balances</span></div>
                </div>

                <!-- 3 — this month / all-time -->
                <div class="sc" data-scene="3" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">This month, and all time</div>
                  ' . $sumCards('&pound;4,870.00', '&pound;2,315.00', '&pound;61,240.50', ['', 'a-ring" style="--d:1.5s', 'a-ring" style="--d:12.5s']) . '
                  <div class="month a-fade" style="--d:4s">' . str_repeat('<i></i>', 31) . '<span class="fill a-wide" style="--d:5.5s"></span></div>
                  <div class="mlab a-fade" style="--d:4s"><span>1 Oct</span><span>today: 7 Oct</span><span>31 Oct</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:7s">Since the 1st &mdash; not the last 30 days</span>
                    <span class="chip ok a-pop" style="--d:14s">All-time: every payment ever, deposits too</span></div>
                </div>

                <!-- 4 — one card per order -->
                <div class="sc" data-scene="4" data-len="22">
                  <div class="sech a-fade" style="--d:.3s">Payment history</div>
                  <div class="pgs">
                    ' . $card('Emma Fletcher', 'BEV-2026-0042', '&pound;660.00', '&pound;330.00', 1, $owed('&pound;330.00'), 'a-drop', '--d:2s') . '
                    ' . $card('Raj Patel', 'BEV-2026-0039', '&pound;1,240.00', '&pound;1,240.00', 3, $full, 'paid a-drop', '--d:3s') . '
                    ' . $card('Sue Walker', 'BEV-2026-0035', '&pound;480.00', '&pound;485.00', 2, $over('&pound;5.00'), 'a-drop', '--d:4s') . '
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:7s">Raj paid in <b>3</b> goes &rarr; still <b>one</b> card</span><span class="chip a-pop" style="--d:12s">&#8593; Newest money at the top</span>
                    <span class="chip a-pop" style="--d:16.5s">The quote number opens the order</span></div>
                </div>

                <!-- 5 — reading a card -->
                <div class="sc" data-scene="5" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Reading a card</div>
                  <div class="pgc"><span class="car">&#9656;</span><b class="cu">Raj Patel</b><span class="ql">BEV-2026-0039</span>
                    <span class="mo"><span class="a-ring" style="--d:3s;border-radius:4px">Total <b>&pound;1,240.00</b></span><span class="a-ring" style="--d:5.5s;border-radius:4px">Paid <b>&pound;1,240.00</b> <small>(3)</small></span>
                    <span class="a-ring" style="--d:10s;border-radius:4px">' . $full . '</span></span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:7s">(3) = three payments made it up</span></div>
                  <div class="pgs" style="margin-top:.7rem">
                    ' . $card('Raj Patel', 'BEV-2026-0039', '&pound;1,240.00', '&pound;1,240.00', 3, $full, 'paid a-fly', '--d:11s') . '
                    ' . $card('Emma Fletcher', 'BEV-2026-0042', '&pound;660.00', '&pound;330.00', 1, $owed('&pound;330.00'), 'a-fly', '--d:15.5s') . '
                    ' . $card('Sue Walker', 'BEV-2026-0035', '&pound;480.00', '&pound;485.00', 2, $over('&pound;5.00'), 'a-fly', '--d:20s') . '
                  </div>
                </div>

                <!-- 6 — open a card -->
                <div class="sc" data-scene="6" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Open a card for the detail</div>
                  <div class="pgc open">' . '<span class="car">&#9662;</span><b class="cu">Emma Fletcher</b><span class="ql">BEV-2026-0042</span><span class="mo"><span>Total <b>&pound;660.00</b></span><span>Paid <b>&pound;330.00</b> <small>(1)</small></span>' . $owed('&pound;330.00') . '</span></div>
                  <div class="a-rise" style="--d:2s">' . $detail(str_replace('<span class="dep"><em class="mo2">', '<span class="dep"><em class="mo2 a-ring" style="--d:13s;border-radius:4px">', $depRow)) . '</div>
                  <div class="a-move" style="--fx:60%;--fy:90%;--tx:30%;--ty:2.1rem;--d:.6s;--md:1.2s"><span class="a-out" style="--d:3s;display:block">' . $ptr . '</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:4.5s">Date &middot; Method &middot; Reference &middot; Amount</span>
                    <span class="chip ok a-pop" style="--d:8.5s">The deposit, shaded green</span><span class="chip warn a-pop" style="--d:17s">&#128274; Changed only on the order&rsquo;s Deposit panel</span></div>
                </div>

                <!-- 7 — record: choose the order -->
                <div class="sc" data-scene="7" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Record a payment: choose the order</div>
                  <div style="display:flex;justify-content:flex-end;margin-bottom:.4rem"><span class="btnp a-press a-ring" style="--d:3s">+ Record payment</span></div>
                  <div class="np a-drop" style="--d:4.5s"><div class="sm">&#9662; + Record payment</div>
                    ' . $form([
                        'order' => $swap('&mdash; Standalone payment (no order linked) &mdash;', 'BEV-2026-0042 &mdash; Emma Fletcher (&pound;330.00 outstanding)', 14),
                        'oc' => 'a-ring', 'os' => '--d:7s',
                        'note' => '<div class="dd a-mid" style="--d:9s;--d2:14s"><span>&mdash; Standalone payment (no order linked) &mdash;</span><span class="on">BEV-2026-0042 &mdash; Emma Fletcher (&pound;330.00 outstanding)</span><span>BEV-2026-0044 &mdash; Tom Reid (&pound;1,180.00 outstanding)</span><span>BEV-2026-0041 &mdash; Ann Cole (&pound;95.00 outstanding)</span></div>',
                        'amount' => $swap('<span class="phd">0.00</span>', '330.00', 15.5), 'ac' => 'a-ring', 'as' => '--d:16s',
                    ]) . '
                    <div class="npa"><span class="btnp">Save payment</span><span class="btns">Cancel</span></div>
                  </div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:20s">Not in the list? It&rsquo;s probably paid off already</span></div>
                </div>

                <!-- 8 — don’t count the deposit twice -->
                <div class="sc" data-scene="8" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Don&rsquo;t count the deposit twice</div>
                  <div class="np"><div class="sm">&#9662; + Record payment</div>
                    ' . $form([
                        'order' => 'BEV-2026-0042 &mdash; Emma Fletcher (&pound;330.00 outstanding)',
                        'note' => '<div class="a-ring" style="--d:2.5s;border-radius:6px">' . $depNote('--d:1s') . '</div>',
                        'amount' => $swap('330.00', '<span class="a-type" style="--d:18.5s;--ts:6;--tt:.6s">150.00</span>', 18), 'ac' => 'a-ring', 'as' => '--d:12s',
                    ]) . '
                  </div>
                  <div class="chips"><span class="chip bad a-pop" style="--d:7.5s">&pound;330.00 balance + &pound;330.00 deposit again = counted twice</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:12.5s">Fills itself in only while the box is empty</span><span class="chip ok a-pop" style="--d:16s">Part-payment: order first, then the amount</span></div>
                </div>

                <!-- 9 — date, method, who from, reference -->
                <div class="sc" data-scene="9" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Date, method, who from, reference</div>
                  <div class="np"><div class="sm">&#9662; + Record payment</div>
                    ' . $form([
                        'order' => 'BEV-2026-0042 &mdash; Emma Fletcher (&pound;330.00 outstanding)',
                        'amount' => '330.00',
                        'date' => $swap('07/10/2026', '06/10/2026', 3.5), 'dc' => 'a-ring', 'ds' => '--d:1.5s',
                        'method' => 'Bank transfer', 'mc' => 'a-ring', 'ms' => '--d:9.5s',
                        'payer' => '<span class="a-type" style="--d:12.5s;--ts:13;--tt:.9s">Emma Fletcher</span>', 'pc' => 'a-ring', 'pst' => '--d:12s',
                        'ref' => '<span class="a-type" style="--d:15.5s;--ts:12;--tt:.9s">FT 20261006</span>', 'rc' => 'a-ring', 'rs' => '--d:15s',
                    ]) . '
                    <div class="npa"><span class="btnp a-press a-ring" style="--d:23s">Save payment</span><span class="btns">Cancel</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:5s">The day it really arrived &mdash; the figures count by it</span><span class="chip a-pop" style="--d:19s">&#128269; Search looks in the reference</span></div>
                </div>

                <!-- 10 — standalone -->
                <div class="sc" data-scene="10" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Money with no order behind it</div>
                  <div class="np"><div class="sm">&#9662; + Record payment</div>
                    ' . $form([
                        'order' => '&mdash; Standalone payment (no order linked) &mdash;', 'oc' => 'a-ring', 'os' => '--d:3.5s',
                        'amount' => '<span class="a-type" style="--d:6s;--ts:5;--tt:.5s">75.00</span>',
                        'method' => 'Cash',
                        'payer' => '<span class="a-type" style="--d:8s;--ts:13;--tt:.9s">J. Hargreaves</span>', 'pc' => 'a-ring', 'pst' => '--d:7.5s',
                    ]) . '
                    <div class="npa"><span class="btnp a-press" style="--d:11s">Save payment</span><span class="btns">Cancel</span></div>
                  </div>
                  ' . $card('J. Hargreaves', null, null, '&pound;75.00', 1, '', 'a-drop', '--d:12.5s') . '
                  <div class="chips"><span class="chip a-pop" style="--d:16s">No total to compare &mdash; it shows what was paid</span></div>
                </div>

                <!-- 11 — it settles itself -->
                <div class="sc" data-scene="11" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Saved &mdash; and the order settles itself</div>
                  <div class="flash ok a-pop" style="--d:1s">Payment recorded: &pound;330.00.</div>
                  ' . $sumCards($swap('&pound;4,870.00', '&pound;4,540.00', 20), $swap('&pound;2,315.00', '&pound;2,645.00', 20), $swap('&pound;61,240.50', '&pound;61,570.50', 20)) . '
                  <div class="pgc topaid" style="--d:6s"><span class="car">&#9656;</span><b class="cu">Emma Fletcher</b><span class="ql">BEV-2026-0042</span>
                    <span class="mo"><span>Total <b>&pound;660.00</b></span><span>Paid <b>' . $swap('&pound;330.00', '&pound;660.00', 4) . '</b> <small>' . $swap('(1)', '(2)', 4) . '</small></span>' . $swap($owed('&pound;330.00'), '<span class="fp a-ring" style="--d:7s;border-radius:4px">&check; Fully paid</span>', 6) . '</span></div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:9s">Order &rarr; <b>paid</b>, with no button pressed</span>
                    <span class="chip a-pop" style="--d:13s">&#9993; Paid-in-full receipt: once only, needs an email + the setting ticked</span></div>
                </div>

                <!-- 12 — edit -->
                <div class="sc" data-scene="12" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Edit a payment</div>
                  <div class="np a-drop" style="--d:4s"><div class="sm">&#9662; ' . $swap('+ Record payment', '&#9998; Edit payment', 6.5) . '</div>
                    ' . $form([
                        'order' => 'BEV-2026-0035 &mdash; Sue Walker', 'oc' => 'a-ring', 'os' => '--d:15.5s',
                        'amount' => $swap('245.00', '<span class="a-type" style="--d:11.5s;--ts:6;--tt:.5s">240.00</span>', 11), 'ac' => 'a-ring', 'as' => '--d:10s',
                        'date' => '02/10/2026', 'method' => 'Card',
                    ]) . '
                    <div class="npa"><span class="btnp a-press" style="--d:19.5s">' . $swap('Save payment', 'Update payment', 6.5) . '</span><span class="btns">Cancel</span></div>
                  </div>
                  <div class="pgc open"><span class="car">&#9662;</span><b class="cu">Sue Walker</b><span class="ql">BEV-2026-0035</span><span class="mo"><span>Total <b>&pound;480.00</b></span><span>Paid <b>' . $swap('&pound;485.00', '&pound;480.00', 20.5) . '</b> <small>(2)</small></span>' . $swap($over('&pound;5.00'), $full, 20.5) . '</span></div>
                  ' . $detail($payRow('28 Sep 2026', 'Cash', '', '&pound;240.00') . $payRow('2 Oct 2026', 'Card', '', $swap('&pound;245.00', '&pound;240.00', 20.5), 'a-press a-ring" style="--d:2.5s')) . '
                  <div class="chips"><span class="chip a-pop" style="--d:15.5s">Wrong order? Pick another &mdash; both orders are checked again</span></div>
                </div>

                <!-- 13 — delete, and when it won’t save -->
                <div class="sc" data-scene="13" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Delete one &mdash; and when it won&rsquo;t save</div>
                  <div class="pgc open"><span class="car">&#9662;</span><b class="cu">Tom Reid</b><span class="ql">BEV-2026-0044</span><span class="mo"><span>Total <b>&pound;1,180.00</b></span><span>Paid <b>' . $swap('&pound;1,180.00', '&pound;940.00', 10.5) . '</b> <small>' . $swap('(2)', '(1)', 10.5) . '</small></span>' . $swap($full, $owed('&pound;240.00'), 10.5) . '</span></div>
                  ' . $detail('<span>25 Sep 2026</span><span><i class="mp">Bank transfer</i></span><span class="xs">FT 0925</span><span class="r">&pound;940.00</span><span class="bt"><i class="ed">Edit</i><i class="btnx">&times;</i></span>'
                    . '<span class="shrink" style="--d:9.8s">2 Oct 2026</span><span class="shrink" style="--d:9.8s"><i class="mp">Card</i></span><span class="xs shrink" style="--d:9.8s"></span><span class="r shrink" style="--d:9.8s">&pound;240.00</span><span class="bt shrink" style="--d:9.8s"><i class="ed">Edit</i><i class="btnx a-ring" style="--d:1.5s">&times;</i></span>') . '
                  <div class="cfm a-mid" style="--d:3.5s;--d2:9.5s">Delete this payment? (Won&rsquo;t undo the bank entry &mdash; adjust on your bank reconciliation if needed.)
                    <div class="act"><span class="btns">Cancel</span><span class="yes a-press" style="--d:8s">Yes, continue</span></div></div>
                  <div class="flash err a-pop" style="--d:14s;margin-top:.7rem">Amount must be at least 1p.</div>
                  <div class="np a-rise" style="--d:16s"><div class="sm">&#9662; + Record payment</div>
                    ' . $form(['order' => '&mdash; Standalone payment (no order linked) &mdash;', 'amount' => '<span class="phd">0.00</span>', 'ac' => 'a-ring', 'as' => '--d:17.5s']) . '</div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:18.5s">The box stays open &mdash; but empty. Fill it in again.</span></div>
                </div>

                <!-- 14 — filter -->
                <div class="sc" data-scene="14" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Find any payment again</div>
                  <div class="flt a-rise" style="--d:1s"><div class="ft">Filter the list</div>
                    <div class="fb">
                      <span class="inp srch a-ring" style="--d:3s">' . $swap('<span class="phd">Customer, quote #, reference...</span>', '<span class="a-type" style="--d:4.5s;--ts:8;--tt:.6s">Fletcher</span>', 4.3) . '</span>
                      <span class="fd">From<span class="inp a-ring" style="--d:9s">' . $swap('<span class="phd">dd/mm/yyyy</span>', '01/10/2026', 13) . '</span></span>
                      <span class="fd">To<span class="inp a-ring" style="--d:9.5s">' . $swap('<span class="phd">dd/mm/yyyy</span>', '31/10/2026', 13) . '</span></span>
                      <span class="btns a-sel" style="--d:12.5s">This month</span><span class="btns">Last month</span>
                      <span class="selectbox a-ring" style="--d:15.5s">All methods</span>
                      <span class="btns a-press" style="--d:19s">Apply filter</span><span class="btns a-pop" style="--d:20s">Clear</span>
                    </div></div>
                  <div class="pgs" style="margin-top:.6rem">' . $card('Emma Fletcher', 'BEV-2026-0042', '&pound;660.00', '&pound;660.00', 2, $full, 'paid a-fade', '--d:20.5s') . '</div>
                </div>

                <!-- 15 — exports + who sees what -->
                <div class="sc" data-scene="15" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Exports, and who sees what</div>
                  <div class="pb" style="justify-content:flex-start;margin-bottom:.5rem">
                    <span class="btns a-ring" style="--d:2.5s">Export invoices (CSV)</span><span class="btns a-ring" style="--d:3.3s">Export for QuickBooks (CSV)</span><span class="btns a-ring" style="--d:4.1s">Export payments (CSV)</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:6s">Admins only</span><span class="chip warn a-pop" style="--d:7.5s">They follow <b>From</b> and <b>To</b> &mdash; nothing else</span>
                    <span class="chip a-pop" style="--d:10.5s">&#128214; See: Get your figures into Xero, QuickBooks or Sage</span></div>
                  <div class="two">
                    <div class="note a-rise" style="--d:13s"><h4>&#128100; Staff without &ldquo;View all customer jobs&rdquo;</h4>See payments only on the orders they are booked onto &mdash; so their three figures are smaller. That&rsquo;s correct.</div>
                    <div class="note a-rise" style="--d:19s"><h4>&#128274; No &ldquo;Can see money&rdquo;</h4>No Payments link at all.</div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>There are two money screens, and it helps to know which is which. The <b>Deposit</b> and <b>Payments</b> panels <em>on an
             order</em> are <b>one job&rsquo;s money</b> &mdash; that is the <em>Payments &amp; accounts</em> guide. <b>This</b> page is
             <b>the whole business&rsquo;s money</b>. Find it in the left-hand menu under <b>Retail &rarr; Payments</b>. It is headed
             <b>Payments</b>, with <em>&ldquo;Payments received against your orders.&rdquo;</em> underneath.</p>
          <p>It comes with the <b>Accounts</b> feature, part of the <b>Gold</b> plan. Without it the menu link is not there, and going to the
             page directly gives <b>&ldquo;Accounts module not enabled&rdquo;</b> &mdash; <em>&ldquo;The Accounts add-on isn&rsquo;t enabled
             for your account. Contact your supplier to enable it.&rdquo;</em> Each person also needs <b>Can see money</b> ticked on their
             user; without it they get <b>&ldquo;Not available&rdquo;</b>.</p>
          <ul class="steps">
            <li><b>The three figures at the top.</b> <b>Outstanding</b> (amber) is everything still owed, added up across every order that
                has reached <em>accepted</em> or beyond &mdash; accepted, ordered, fitted, invoiced, paid. It ignores the filter further down.
                It is a net sum, so an overpaid order pulls it down slightly. <b>Received this month</b> (navy) is money received since the
                <b>1st of this month</b> &mdash; a calendar month to date, not the last thirty days. <b>All-time received</b> (green) is every
                payment ever recorded, deposits included.</li>
            <li><b>Payment history.</b> <b>One card per order</b>, not one row per payment, newest money first (the 300 most recent payments).
                A closed card reads: the customer&rsquo;s name, the <b>quote number</b> (a link to the order) or an italic <b>Standalone</b>,
                then <b>Total</b>, <b>Paid &pound;X (n)</b> &mdash; <em>(n)</em> is how many payments &mdash; and one of <b>&check; Fully
                paid</b> (the card turns green), <b>Owed &pound;X</b> (amber) or <b>Overpaid &pound;X</b> (blue). A standalone card shows only
                <b>Paid</b>.</li>
            <li><b>Open a card.</b> Click it (the <b>&#9656;</b> turns to <b>&#9662;</b>) to see each payment: <b>Date</b>, <b>Method</b> as a
                small pill, <b>Reference</b>, <b>Amount</b>, then <b>Edit</b> and a red <b>&times;</b>. The <b>deposit</b> is in there too,
                shaded green, method <b>DEPOSIT</b>, reference &ldquo;Deposit&rdquo;, and instead of buttons it says <em>&ldquo;managed on the
                order&rdquo;</em> &mdash; change it on the order&rsquo;s own <b>Deposit</b> panel. (The <b>Method</b> filter can&rsquo;t pick
                out deposits: &ldquo;Deposit&rdquo; isn&rsquo;t one of its eight methods.)</li>
            <li><b>Record a payment.</b> The blue <b>+ Record payment</b> button (top right) opens the box. <b>Order (optional)</b> starts on
                <em>&ldquo;&mdash; Standalone payment (no order linked) &mdash;&rdquo;</em>, then lists orders that <b>still owe something</b>,
                newest first, like <em>&ldquo;BEV-2026-0042 &mdash; Emma Fletcher (&pound;330.00 outstanding)&rdquo;</em>. Pick one and
                <b>Amount &pound;</b> fills itself with the balance &mdash; <b>only while the amount box is empty</b>, so for a part-payment pick
                the order first, then type over the amount. If a deposit is already in, an amber line says <em>&ldquo;&check; Deposit of
                &pound;330.00 is already recorded on this order &mdash; the amount above is the remaining balance, so don&rsquo;t re-enter the
                deposit.&rdquo;</em></li>
            <li><b>The rest of the box.</b> <b>Received on</b> starts on today &mdash; change it to the day the money really arrived, because
                the monthly figure and the date filter count by it. <b>Method</b>: Cash, Card, <b>Bank transfer</b> (already chosen), Cheque,
                PayPal, Stripe, GoCardless, Other. <b>Received from (optional)</b> (<em>&ldquo;Who paid &mdash; e.g. customer name&rdquo;</em>)
                and <b>Reference (optional)</b> (<em>&ldquo;e.g. cheque #, Stripe id...&rdquo;</em>) &mdash; the search box looks in the
                reference. Then <b>Save payment</b>, or <b>Cancel</b>.</li>
            <li><b>Money with no order.</b> Leave <b>Order</b> on <b>Standalone</b> and put the sender in <b>Received from</b>. It gets its own
                card, named after the sender and marked <b>Standalone</b>.</li>
            <li><b>The shortcut in.</b> On <b>Retail &rarr; Orders</b> the amber <b>Outstanding</b> figure is a link (<em>&ldquo;Click to take
                a payment against this order&rdquo;</em>) that opens this page with the box open, the order chosen and the amount filled
                in.</li>
            <li><b>It settles itself.</b> You get <code>Payment recorded: &pound;330.00.</code> When the money covers the order total, the order
                turns <b>paid</b> on its own and the customer is emailed a <b>paid-in-full receipt</b> (subject <em>&ldquo;Receipt BEV-2026-0042
                &mdash; paid in full &middot; Beverley Blinds&rdquo;</em>) &mdash; <b>once only</b>, if the order has a valid email address
                and <b>Setup &rarr; Settings &rarr; Quoting &rarr; Paid-in-full receipt</b> is ticked (it is unless someone unticks it).</li>
            <li><b>Edit a payment.</b> <b>Edit</b> on its row fills the same box at the top: the heading becomes <b>&#9998; Edit payment</b> and
                the button <b>Update payment</b> (<code>Payment updated.</code>). You can change the amount, date, method, sender and
                reference, and move it to a different order or to Standalone &mdash; both orders are re-checked, so <em>paid</em> follows the
                money. <b>Cancel</b> puts the box back to a new payment.</li>
            <li><b>Delete a payment.</b> The red <b>&times;</b> asks first: <em>&ldquo;Delete this payment? (Won&rsquo;t undo the bank entry
                &mdash; adjust on your bank reconciliation if needed.)&rdquo;</em> &mdash; <b>Cancel</b> or <b>Yes, continue</b>, then
                <code>Payment deleted.</code> If that leaves a paid order short, it steps back to the status it had before. (A receipt already
                sent is not un-sent.)</li>
            <li><b>Filter the list.</b> The dashed <b>Filter the list</b> box: a search box (<em>&ldquo;Customer, quote #,
                reference...&rdquo;</em>), <b>From</b> and <b>To</b> dates (the day the money came in), one-click <b>This month</b> and
                <b>Last month</b> (they keep your search, and turn blue while in effect), the <b>All methods</b> dropdown, <b>Apply filter</b>,
                and <b>Clear</b> to drop the lot. Nothing found: <em>&ldquo;Nothing matches your filter.&rdquo;</em></li>
            <li><b>The exports (admins only).</b> <b>Export invoices (CSV)</b>, <b>Export for QuickBooks (CSV)</b> and <b>Export payments
                (CSV)</b>, top right, with a note underneath. They follow <b>only the From and To dates</b> &mdash; not the search box and not
                the method. See the guide <em>Get your figures into Xero, QuickBooks or Sage</em>.</li>
          </ul>
          <div class="oops"><b>When it won&rsquo;t save.</b> A red line at the top says why:
             <code>Amount must be at least 1p.</code>, <code>Received date is required (YYYY-MM-DD).</code>,
             <code>That amount looks wrong &mdash; payments over &pound;1,000,000 aren&rsquo;t accepted.</code> or
             <code>Payments can be recorded once the quote has been accepted.</code> The box <b>stays open but empty</b> &mdash; amount blank,
             date back on today, method back on Bank transfer, order back on Standalone &mdash; so put it all in again (and if you were editing,
             press <b>Edit</b> on the row again first, or it will save as a new payment). Trying to change the deposit here gives
             <code>The deposit is managed on the order &mdash; change it from the order&rsquo;s deposit panel.</code> And an order missing from
             the dropdown has almost certainly been paid off already.</div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Not everyone sees the same figures.</b> A member of staff without
             <b>View all customer jobs</b> (set per person in <b>Setup &rarr; Users</b>) sees only payments on orders they are booked onto,
             and their three figures are added up from just those &mdash; smaller than yours, and correct. Standalone payments are back-office
             only: they can&rsquo;t see or record one (<em>&ldquo;You don&rsquo;t have permission to record payments for that order.&rdquo;</em>).
             This page is your <b>retail</b> money &mdash; your own customers&rsquo; payments. The figures always agree with the order&rsquo;s
             own panels: <em>received = deposit + payments</em>, <em>balance = total &minus; received</em>.</div></div>',
        'script'  => [
            ['1', 'One page for all the money',        'This is the Payments page. Find it in the menu, under Retail, then Payments. The panels on an order show one job\'s money. This page shows the whole business at once: what is still owed, what has come in, and every payment you have taken. It comes with the Accounts feature, on the Gold plan.', 1],
            ['2', 'Outstanding',                       'Three figures sit across the top. The first, Outstanding, in amber, is everything still owed to you. It adds up every order that has been accepted, or gone further. It always counts every order, so the filter further down the page does not change it. If it looks high, it may be time to chase some balances.', 2],
            ['3', 'This month, and all time',          'Next, Received this month, in navy. It counts money in since the first of this month, not the last thirty days. So early in the month, it is meant to look small. Then All-time received, in green, is every payment ever recorded, deposits included. Together, they tell you how the month is going.', 3],
            ['4', 'One card per order',                'Below them is the Payment history. It gives you one card for each order, not one line for each payment. So an order paid in three goes is still one tidy card. The newest money is at the top. Each card starts with the customer\'s name, then the quote number, which is a link straight to the order.', 4],
            ['5', 'Reading a card',                    'On the right of each card is the money. Total is the order total. Paid is what has come in, and the small number in brackets says how many payments made it up. Then the verdict. A green tick means fully paid, and the whole card turns green. Owed, in amber, is still to come. Overpaid, in blue, means too much has gone in.', 5],
            ['6', 'Open a card for the detail',        'Click a card, and it opens to show each payment: the date, the method, the reference and the amount. The deposit is in the list too, shaded green. But you cannot change it here. Where the buttons would be, it says managed on the order, because the deposit is only ever changed on the order\'s own Deposit panel.', 6],
            ['7', 'Record a payment: choose the order', 'Money arrives in the bank, or in the post. Click plus Record payment, at the top right, and the box opens. First, choose the order. The list only shows orders that still owe something, each with its outstanding amount. Pick one, and the amount fills itself in with the balance. If an order is missing, it has probably been paid off already.', 7],
            ['8', 'Don\'t count the deposit twice',    'Now read the amber line under the order. It says the deposit is already recorded, and the amount shown is the remaining balance. So please do not add the deposit again. One more thing: the amount only fills itself in while the box is empty. For a part-payment, choose the order first, and then type over the amount.', 8],
            ['9', 'Date, method, who from, reference', 'Received on starts on today. Change it to the day the money really arrived, because the monthly figure and the filters count by that date. Method starts on bank transfer. Received from is who sent it. Reference is a cheque number or a bank reference, and the search box looks in it, so it is worth filling in. Then click Save payment.', 9],
            ['10', 'Money with no order',              'Sometimes money comes in with no job behind it. Leave the order on Standalone payment, no order linked, and put the sender\'s name in Received from. It gets a card of its own in the history, marked Standalone, under that name. There is no order total to compare against, so it simply shows what was paid.', 10],
            ['11', 'It settles itself',                'When you save, a green line says Payment recorded, with the amount. If that covers the order total, the card turns green, and the order marks itself paid. Nobody presses a button. The customer is emailed a paid in full receipt, once only, if they have an email address and the receipt setting is ticked. And the figures at the top move too.', 11],
            ['12', 'Edit a payment',                   'Got a figure wrong? Open the card, and click Edit on that payment. The box at the top fills with its details. The heading changes to Edit payment, and the button to Update payment. You can change the amount, the date, the method, and even move it to a different order. Both orders are checked again, so paid follows the money.', 12],
            ['13', 'Delete one, and when it won\'t save', 'Wrong payment altogether? Click the red cross. It asks first, and warns that it will not undo the bank entry. Say yes, and an order that is no longer covered steps back out of paid. If a payment will not save, a red line at the top says why. The box stays open, but it is empty, so fill it in again.', 13],
            ['14', 'Find any payment again',           'To find something, use Filter the list. The search box looks at the customer\'s name, the quote number and the reference. From and To set the dates the money came in. This month and Last month are one-click shortcuts. You can also pick one method. Click Apply filter to run it, and Clear to go back to everything.', 14],
            ['15', 'Exports, and who sees what',       'At the top right, admins also get three export buttons, for your bookkeeper. They follow the From and To dates, and nothing else. There is a separate guide on those. And staff without View all customer jobs only see payments on the orders they are booked onto. So their figures are smaller than yours, and that is correct.', 15],
        ],
];
