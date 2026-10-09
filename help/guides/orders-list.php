<?php
declare(strict_types=1);

/**
 * Guide: orders-list — "Finding a job: the Orders list" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors /orders/index.php: the four sidebar doors (?scope=quotes|orders,
 * ?type=retail|trade), the heading + subtitle, the List | Pipeline tray,
 * the status chips (zero-count chips hidden), the search box, each column
 * (Send to suppliers link, status pill + factory progress pill, Deposit,
 * Outstanding), the 200-row cap and its sort, the bulk Archive / Restore /
 * Delete (orders/archive.php, quote-history/bulk_delete.php) and the empty
 * states. Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// Default traffic-light palette (_partials/job_status_colours.php).
$PILL = [
    'quote' => ['#f59e0b', '#111827'], 'declined' => ['#dc2626', '#fff'], 'accepted' => ['#16a34a', '#fff'],
    'ordered' => ['#0891b2', '#fff'], 'fitted' => ['#0d9488', '#fff'], 'invoiced' => ['#ea580c', '#fff'], 'paid' => ['#475569', '#fff'],
];
$pill = static fn (string $s): string => '<span class="spl" style="background:' . $PILL[$s][0] . ';color:' . $PILL[$s][1] . '">' . $s . '</span>';
$notSent = '<span class="nsb" title="This quote hasn\'t been sent to the customer yet">Not sent</span>';
$fpill = '<span class="fpl">With the factory: In Production</span>';

// Orders-side rows: newest ACCEPTED first (so a July job accepted today leads).
$ORD = [
    ['ABC-2026-0029', 'Joe Bloggs',    'CV8 1AA',  'accepted', '21 Jul 2026', '&pound;980.00',   '<span class="due">&pound;490.00 due</span>',       '<span class="oslink">&pound;980.00</span>', 'send'],
    ['ABC-2026-0045', 'Tom Shah',      'CV34 4AB', 'ordered',  '2 Oct 2026',  '&pound;860.00',   '<span class="pd">&check; &pound;430.00 paid</span>', '<span class="oslink">&pound;430.00</span>', 'sent'],
    ['ABC-2026-0042', 'Emma Fletcher', 'CV32 5PJ', 'ordered',  '14 Sep 2026', '&pound;1,240.00', '<span class="pd">&check; &pound;620.00 paid</span>', '<span class="oslink">&pound;620.00</span>', 'factory'],
    ['ABC-2026-0031', 'Angela Reed',   'CV31 1XY', 'paid',     '3 Jul 2026',  '&pound;2,145.00', '<span class="pd">&check; &pound;1,072.50 paid</span>', '<span class="pd">&check; paid</span>', 'sent'],
];
$QUO = [
    ['ABC-2026-0051', 'Kim Ward',  'CV35 9RT', 'quote', '6 Oct 2026', '&pound;710.00', true],
    ['ABC-2026-0049', 'Lucy Hart', 'CV32 6NB', 'quote', '1 Oct 2026', '&pound;1,385.00', false],
    ['ABC-2026-0047', 'Sam Cole',  'CV37 7HQ', 'declined', '24 Sep 2026', '&pound;455.00', false],
];

/**
 * The table. $orders = orders view (adds Deposit / Outstanding). $rowAttr($i)
 * → [class, style] for each <div class="tr">; $tick($i) → the tick-box html;
 * $cell($i, $col, $html) may wrap any cell.
 */
$table = static function (bool $orders, ?callable $rowAttr = null, ?callable $tick = null, ?callable $cell = null, string $hdTick = '<span class="tk"></span>') use ($ORD, $QUO, $pill, $notSent, $fpill): string {
    $cols = $orders ? 'ord' : 'quo';
    $h = '<div class="tbl ' . $cols . '"><div class="tr th"><span>' . $hdTick . '</span><span>Quote #</span><span>Customer</span><span class="pc">Postcode</span><span>Status</span><span class="cr">Created</span><span class="num tt">Total</span>'
       . ($orders ? '<span>Deposit</span><span class="num">Outstanding</span>' : '') . '</div>';
    $rows = $orders ? $ORD : $QUO;
    $wrap = static fn ($i, $c, $html) => $cell ? $cell($i, $c, $html) : $html;
    foreach ($rows as $i => $r) {
        [$rc, $rs] = $rowAttr ? $rowAttr($i) : ['', ''];
        $num = '<a class="qn">' . $r[0] . '</a>';
        if ($orders) {
            if ($r[8] === 'send') $num .= '<span class="sup">&#128230; Send to suppliers</span>';
            if ($r[8] === 'sent') $num .= '<span class="sup ok">&#10003; Sent to suppliers <i>&middot; resend</i></span>';
        }
        $status = $pill($r[3]) . ($orders ? '' : ($r[6] ? ' ' . $notSent : '')) . ($orders && $r[8] === 'factory' ? '<br>' . $fpill : '');
        $h .= '<div class="tr ' . $rc . '" style="' . $rs . '"><span>' . ($tick ? $tick($i) : '<span class="tk"></span>') . '</span>'
            . '<span>' . $wrap($i, 'num', $num) . '</span><span>' . $r[1] . '</span><span class="pc">' . $r[2] . '</span>'
            . '<span>' . $wrap($i, 'status', $status) . '</span><span class="cr">' . $wrap($i, 'created', $r[4]) . '</span><span class="num tt">' . $r[5] . '</span>'
            . ($orders ? '<span>' . $wrap($i, 'dep', $r[6]) . '</span><span class="num">' . $wrap($i, 'out', $r[7]) . '</span>' : '')
            . '</div>';
    }
    return $h . '</div>';
};

/** Only these rows (the rest hidden) — for a filtered copy of the table. */
$only = static fn (array $keep, array $extra = []) => static fn ($i) => in_array($i, $keep, true) ? ($extra[$i] ?? ['', '']) : ['', 'display:none'];
/** Swap a full table for a filtered one at $t seconds. */
$swapT = static fn (string $full, string $filtered, float $t): string =>
    '<div class="stkb"><div class="a-out" style="--d:' . $t . 's">' . $full . '</div><div class="a-fade" style="--d:' . $t . 's">' . $filtered . '</div></div>';

/** The page heading, the List | Pipeline tray and + New quote. */
$head = static fn (string $title = 'Retail Orders', string $sub = 'Accepted onward &mdash; orders, invoices and paid jobs.', bool $newBtn = true): string =>
    '<div class="hd"><div><div class="ttl">' . $title . '</div><div class="sub">' . $sub . '</div>'
  . '<span class="tray"><span class="on">List</span><span>Pipeline</span></span></div>'
  . ($newBtn ? '<span class="btnp">+ New quote</span>' : '') . '</div>';

/** The chip bar. $chips = [[label, count, extraClass, style], …]; $arch = right-hand chip html. */
$chips = static function (array $chips, string $arch = '<span class="chp">&#128452; Archived (6)</span>'): string {
    $h = '<div class="chips">';
    foreach ($chips as $c) {
        [$l, $n, $x, $s] = $c + ['', '', '', ''];
        $h .= '<span class="chp ' . $x . '" style="' . $s . '">' . $l . ($n !== '' ? ' (' . $n . ')' : '') . '</span>';
    }
    return $h . '<span class="arch">' . $arch . '</span></div>';
};
$ordChips = [['All', 53, 'on'], ['Accepted', 4], ['Ordered', 7], ['Fitted', 2], ['Invoiced', 9], ['Paid', 31]];

$search = static fn (string $val = '', string $x = ''): string =>
    '<div class="srch ' . $x . '"><span class="sbox">' . ($val !== '' ? $val : '<span class="phd">Search by quote #, customer name, or postcode...</span>') . '</span><span class="btns">Search</span></div>';

$bulk = static fn (string $count = '(none selected)', bool $live = false, string $arch = '&#128452; Archive selected'): string =>
    '<div class="bulk' . ($live ? '' : ' off') . '"><span class="btns">' . $arch . '</span><span class="btnd">Delete selected</span><span class="cnt">' . $count . '</span></div>';

return [
        'aud'     => 'admin',
        'section' => 'Orders',
        'title'   => 'Finding a job: the Orders list',
        'eyebrow' => 'Orders',
        'v'       => 2,
        'blurb'   => 'One list, several doors: Quotes or Orders, Retail or Trade, the status chips and the search box — reading a row, and ticking rows to archive or delete.',
        'lede'    => 'Every quote and every order lives in <b>one list</b>. Which jobs you see depends on the door you came in by
                      &mdash; <b>Quotes</b> or <b>Orders</b>, <b>Retail</b> or <b>Trade</b> &mdash; then on the <b>status chip</b> that is lit
                      and what is in the <b>search box</b>. Learn to read those, and you can find any job in seconds. This guide goes
                      <b>slowly</b>, one idea per chapter. To get there: <b>Orders</b> (or <b>Quotes</b>) under <b>Retail</b> in the menu.',
        'open'    => '/orders/index.php?scope=orders&type=retail',
        'css'     => '
          .gd .sc{ position:relative; min-height:370px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .row{ display:flex; gap:.45rem; flex-wrap:wrap; align-items:center; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:7px; padding:.26rem .6rem; font-size:.66rem; font-weight:600; white-space:nowrap; }
          .gd .btnd{ display:inline-flex; align-items:center; background:var(--err); color:#fff; border-radius:7px; padding:.26rem .6rem; font-size:.66rem; font-weight:700; white-space:nowrap; }
          .gd .stk{ display:inline-grid; justify-items:start; } .gd .stk > *{ grid-area:1/1; }
          .gd .stkb{ display:grid; } .gd .stkb > *{ grid-area:1/1; }
          .gd .okb{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.38rem .6rem; font-size:.66rem; color:var(--ink); font-weight:600; margin-bottom:.45rem; }
          .gd .errb{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.38rem .6rem; font-size:.66rem; color:var(--ink); margin-bottom:.45rem; line-height:1.45; }
          .gd .cdlg{ position:absolute; z-index:5; left:12%; right:12%; top:6rem; max-width:22rem; border:1px solid var(--line); border-radius:12px; background:var(--surface);
                     box-shadow:var(--gd-shadow); padding:.6rem .75rem; font-size:.7rem; color:var(--ink); line-height:1.45; }
          .gd .cdlg .act{ display:flex; gap:.35rem; justify-content:flex-end; margin-top:.45rem; }

          /* heading */
          .gd .hd{ display:flex; align-items:flex-start; gap:.6rem; margin-bottom:.45rem; }
          .gd .hd > .btnp{ margin-left:auto; }
          .gd .ttl{ font-size:1rem; font-weight:800; color:var(--ink); border-radius:5px; }
          .gd .sub{ font-size:.66rem; color:var(--faint); margin:.1rem 0 .3rem; border-radius:4px; }
          .gd .tray{ display:inline-flex; background:var(--panel); border-radius:8px; padding:.12rem; font-size:.64rem; font-weight:700; }
          .gd .tray span{ padding:.16rem .6rem; border-radius:6px; color:var(--faint); }
          .gd .tray span.on{ background:var(--surface); color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.08); }

          /* chips, search, bulk bar */
          .gd .chips{ display:flex; gap:.35rem; flex-wrap:wrap; margin:0 0 .45rem; align-items:center; }
          .gd .chp{ display:inline-flex; border-radius:999px; padding:.14rem .55rem; font-size:.64rem; background:var(--panel); color:var(--soft); border:1px solid transparent; white-space:nowrap; }
          .gd .chp.on{ background:var(--accent); color:#fff; }
          .gd .arch{ margin-left:auto; }
          .gd .srch{ display:flex; gap:.35rem; margin-bottom:.45rem; }
          .gd .sbox{ flex:1; display:flex; align-items:center; min-height:1.7rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:0 .55rem; font-size:.68rem; color:var(--ink); background:var(--surface); min-width:0; overflow:hidden; white-space:nowrap; }
          .gd .phd{ color:var(--faint); }
          .gd .bulk{ display:flex; gap:.35rem; align-items:center; flex-wrap:wrap; margin-bottom:.4rem; }
          .gd .bulk.off .btns, .gd .bulk.off .btnd{ opacity:.45; }
          .gd .bulk .cnt{ font-size:.62rem; color:var(--faint); }

          /* the table */
          .gd .tbl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.6rem; color:var(--ink); }
          .gd .tr{ display:grid; gap:.3rem; padding:.3rem .45rem; align-items:center; border-top:1px solid var(--line); background:var(--surface); }
          .gd .tbl.ord .tr{ grid-template-columns:1rem 5.8rem minmax(4rem,1fr) 3.2rem 6rem 3.9rem 3.6rem 5rem 4.3rem; gap:.25rem; }
          .gd .tbl.quo .tr{ grid-template-columns:1rem 5.8rem minmax(4rem,1fr) 3.8rem 7rem 4.4rem 4rem; }
          .gd .tr.th{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .tr > span{ min-width:0; }
          .gd .num{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .cr{ color:var(--faint); white-space:nowrap; }
          .gd .qn{ font-weight:800; color:var(--ink); display:inline-block; border-radius:4px; }
          .gd .sup{ display:block; font-size:.5rem; color:var(--accent); margin-top:.1rem; line-height:1.25; }
          .gd .sup.ok{ color:#166534; } .gd .sup i{ font-style:normal; color:var(--faint); }
          .gd .spl{ display:inline-flex; border-radius:999px; padding:.03rem .4rem; font-size:.5rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
          .gd .nsb{ display:inline-flex; border-radius:999px; padding:.02rem .35rem; font-size:.48rem; font-weight:800; text-transform:uppercase; color:#92400e; background:#fef3c7; border:1px solid #fde68a; }
          .gd .fpl{ display:inline-flex; margin-top:.15rem; border-radius:999px; padding:.02rem .35rem; font-size:.48rem; font-weight:700; color:#b5730f; background:#f7ecd6; }
          .gd .pd{ color:#065f46; font-weight:700; white-space:nowrap; } .gd .due{ color:#92400e; font-weight:700; white-space:nowrap; }
          .gd .oslink{ color:#92400e; font-weight:800; border-bottom:1px dashed #92400e; }
          .gd .tk{ width:12px; height:12px; border-radius:3px; border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); display:inline-grid; place-items:center; font-size:.5rem; color:transparent; vertical-align:middle; }
          .gd .tk.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          :root[data-theme="dark"] .gd .pd{ color:#34d399; } :root[data-theme="dark"] .gd .sup.ok{ color:#34d399; }
          :root[data-theme="dark"] .gd .due, :root[data-theme="dark"] .gd .oslink{ color:#fbbf24; border-color:#fbbf24; }

          /* 1 — the doors */
          .gd .doors{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; max-width:24rem; margin:.6rem 0; }
          .gd .door{ border:1px solid var(--line); border-radius:10px; padding:.45rem .6rem; background:var(--surface); font-size:.72rem; color:var(--ink); }
          .gd .door small{ display:block; font-size:.55rem; letter-spacing:.1em; text-transform:uppercase; color:var(--faint); font-weight:800; }
          .gd .one{ display:flex; align-items:center; gap:.5rem; border:2px solid var(--accent); border-radius:12px; padding:.55rem .75rem; max-width:24rem; background:var(--accent-wash); font-size:.76rem; font-weight:800; color:var(--ink); }

          /* 4 — retail / trade */
          .gd .sides{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; max-width:28rem; margin:.6rem 0; }
          .gd .side2{ border:1px dashed var(--line); border-radius:10px; padding:.5rem; min-height:5rem; }
          .gd .side2 h4{ margin:0 0 .35rem; font-size:.74rem; color:var(--ink); }
          .gd .job{ display:inline-flex; flex-direction:column; border:1px solid var(--line); border-radius:8px; padding:.3rem .5rem; background:var(--surface); font-size:.64rem; color:var(--ink); position:relative; }
          .gd .stamp{ position:absolute; right:-.6rem; top:-.6rem; border:2px solid #16a34a; color:#16a34a; border-radius:6px; padding:0 .3rem; font-size:.56rem; font-weight:900; background:var(--surface); }

          @media (max-width:640px){
            .gd .sc{ min-height:440px; }
            .gd .tbl.ord .tr{ grid-template-columns:1rem 5.6rem minmax(0,1fr) 6rem; }
            .gd .tbl.quo .tr{ grid-template-columns:1rem 5.6rem minmax(0,1fr) 6rem; }
            .gd .tr .pc, .gd .tr .cr, .gd .tr .tt{ display:none; }
            .gd .tbl.ord .tr > span:nth-child(8), .gd .tbl.ord .tr > span:nth-child(9){ display:none; }
            .gd .sides{ grid-template-columns:1fr; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / orders</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a>Pipeline</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a class="on">Orders</a><a>Payments</a>
                <div class="navh">Trade</div>
                <a>Quotes</a><a>Orders</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head() . $chips($ordChips) . $search() . $bulk() . $table(true) . '
                  <p class="sub" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; thirteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — one list, several doors -->
                <div class="sc" data-scene="1" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">One list &mdash; several doors</div>
                  <div class="doors">
                    <span class="door a-pop" style="--d:7.4s"><small>Retail</small>Quotes</span>
                    <span class="door a-pop" style="--d:8.2s"><small>Retail</small>Orders</span>
                    <span class="door a-pop" style="--d:11.4s"><small>Trade</small>Quotes</span>
                    <span class="door a-pop" style="--d:12s"><small>Trade</small>Orders</span>
                  </div>
                  <div class="a-fade" style="--d:16.4s;font-size:1.1rem;color:var(--faint);margin:.1rem 0 .35rem 7rem">&#8595;</div>
                  <div class="one a-rise" style="--d:17.2s">&#128203; The same screen &mdash; every quote and order</div>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:13.6s">Trade &mdash; only if you supply trade accounts</span>
                    <span class="chip a-pop" style="--d:19.8s">What changes: which jobs get through</span>
                  </div>
                </div>

                <!-- 2 — read the heading -->
                <div class="sc" data-scene="2" data-len="23">
                  <div class="hd"><div><div class="ttl a-ring" style="--d:3.4s">Retail Orders</div>
                    <div class="sub a-ring" style="--d:14.4s">Accepted onward &mdash; orders, invoices and paid jobs.</div>
                    <span class="tray"><span class="on">List</span><span>Pipeline</span></span></div><span class="btnp">+ New quote</span></div>
                  <div class="row" style="margin:.4rem 0 .6rem">
                    <span class="chip a-pop" style="--d:7.4s">Retail &rarr; your own customers</span>
                    <span class="chip a-pop" style="--d:11.6s">Orders &rarr; accepted onward</span>
                  </div>
                  <div class="a-fade" style="--d:1s">' . $chips($ordChips) . $table(true) . '</div>
                  <span class="chip ok a-pop" style="--d:19.8s;margin-top:.6rem">Read the heading &rarr; you know where you are</span>
                </div>

                <!-- 3 — the quotes side -->
                <div class="sc" data-scene="3" data-len="25">
                  <div class="hd"><div><div class="ttl stk"><span class="a-out" style="--d:1.8s">Retail Orders</span><span class="a-fade" style="--d:1.8s">Retail Quotes</span></div>
                    <div class="sub stkb"><span class="a-out" style="--d:1.8s">Accepted onward &mdash; orders, invoices and paid jobs.</span><span class="a-fade" style="--d:1.8s">Quotes still in the pipeline &mdash; drafts, sent, and declined.</span></div>
                    <span class="tray"><span class="on">List</span><span>Pipeline</span></span></div><span class="btnp">+ New quote</span></div>
                  <div class="stkb">
                    <div class="a-out" style="--d:2.4s">' . $chips($ordChips) . $table(true) . '</div>
                    <div class="a-fade" style="--d:2.6s">' . $chips([['All', 15, 'on'], ['Quote', 12], ['Declined', 3]], '<span class="chp">&#128452; Archived (2)</span>')
                      . $table(false, null, null, static fn ($i, $c, $h) => ($i === 0 && $c === 'status') ? '<span class="a-ring" style="--d:14s;display:inline-block;border-radius:999px">' . $h . '</span>' : $h) . '</div>
                  </div>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:9.6s">draft + sent = <b>Quote</b> &middot; and <b>Declined</b></span>
                    <span class="chip a-pop" style="--d:15s">Not sent = the customer hasn&rsquo;t seen it</span>
                    <span class="chip a-pop" style="--d:20.2s">No Deposit / Outstanding on a quote</span>
                  </div>
                </div>

                <!-- 4 — retail or trade -->
                <div class="sc" data-scene="4" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Retail or trade &mdash; decided when the job is created</div>
                  <div class="sides">
                    <div class="side2 a-rise" style="--d:1s"><h4>Retail</h4><span class="job a-drop" style="--d:3s">ABC-2026-0042<br>Emma Fletcher<span class="stamp a-stamp" style="--d:4.2s">RETAIL</span></span></div>
                    <div class="side2 a-rise" style="--d:1.4s"><h4>Trade</h4></div>
                  </div>
                  <div class="row">
                    <span class="chip bad a-pop" style="--d:6.4s">&#8644; Move to trade &mdash; there is no such switch</span>
                    <span class="chip a-pop" style="--d:11.4s">Wrong side? It was raised on the wrong side</span>
                  </div>
                  <div class="row" style="margin-top:.5rem">
                    <span class="chip ok a-pop" style="--d:15.8s">1. Raise it again on the right side</span>
                    <span class="chip ok a-pop" style="--d:18.4s">2. Archive the one you don&rsquo;t want</span>
                  </div>
                </div>

                <!-- 5 — the status chips -->
                <div class="sc" data-scene="5" data-len="24">
                  ' . $head() . '
                  <div class="chips">
                    <span class="stk"><span class="chp on a-out" style="--d:13.6s"><span class="a-ring" style="--d:2.8s;border-radius:999px">All (53)</span></span><span class="chp a-fade" style="--d:13.6s">All (53)</span></span>
                    <span class="chp a-ring" style="--d:9.8s">Accepted (4)</span><span class="chp a-sel" style="--d:13.4s">Ordered (7)</span>
                    <span class="chp a-ring" style="--d:11s">Fitted (2)</span><span class="chp a-ring" style="--d:11.6s">Invoiced (9)</span><span class="chp a-ring" style="--d:12.2s">Paid (31)</span>
                    <span class="arch"><span class="chp">&#128452; Archived (6)</span></span>
                  </div>
                  ' . $swapT($table(true), $table(true, $only([1, 2])), 14) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:16s">Click <b>All</b> &rarr; everything again</span>
                    <span class="chip a-pop" style="--d:18.6s">No Declined chip? Nothing declined &mdash; not a fault</span>
                  </div>
                  <div class="a-move" style="--fx:80%;--fy:16rem;--tx:9.2rem;--ty:4.7rem;--d:12s;--md:1.3s">' . $ptr . '</div>
                </div>

                <!-- 6 — searching -->
                <div class="sc" data-scene="6" data-len="23">
                  ' . $chips($ordChips) . '
                  <div class="srch"><span class="sbox a-ring" style="--d:.6s"><span class="stk"><span class="phd a-out" style="--d:8.6s">Search by quote #, customer name, or postcode...</span><span class="a-type" style="--d:8.6s;--ts:8;--tt:.7s">Fletcher</span></span></span>
                    <span class="btns a-press" style="--d:9.6s">Search</span><span class="btns a-pop" style="--d:11s">Clear</span></div>
                  ' . $swapT($table(true), $table(true, $only([2])), 10.2) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:2.8s">quote # &middot; name &middot; postcode</span>
                    <span class="chip a-pop" style="--d:7s">part of any will do</span>
                    <span class="chip a-pop" style="--d:14.8s">Only inside the view you&rsquo;re in</span>
                    <span class="chip bad a-pop" style="--d:19s">Clicking a chip clears the search</span>
                  </div>
                </div>

                <!-- 7 — reading a row -->
                <div class="sc" data-scene="7" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Reading a row</div>
                  ' . $table(true, null, null, static function ($i, $c, $h) {
                        $t = ['num' => 1.4, 'status' => 13, 'dep' => 0, 'out' => 0, 'created' => 0][$c] ?? 0;
                        if ($i === 2 && $c === 'num') return '<span class="a-ring" style="--d:1.4s;display:inline-block;border-radius:4px">' . $h . '</span>';
                        if ($i === 1 && $c === 'num') return '<span class="a-ring" style="--d:6.2s;display:inline-block;border-radius:4px">' . $h . '</span>';
                        if ($i === 0 && $c === 'num') return '<span class="a-ring" style="--d:7.4s;display:inline-block;border-radius:4px">' . $h . '</span>';
                        if ($i === 2 && $c === 'status') return '<span class="a-ring" style="--d:13s;display:inline-block;border-radius:999px">' . $h . '</span>';
                        return $h;
                    }) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:3.6s">Quote # &rarr; opens the job</span>
                    <span class="chip a-pop" style="--d:7.8s">&#128230; Send to suppliers &middot; or &#10003; Sent to suppliers</span>
                    <span class="chip a-pop" style="--d:16.6s">Your colours &mdash; Settings &rarr; Status colours</span>
                    <span class="chip a-pop" style="--d:19s">&ldquo;With the factory: &hellip;&rdquo; = how the factory&rsquo;s getting on</span>
                  </div>
                </div>

                <!-- 8 — the money columns -->
                <div class="sc" data-scene="8" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">The money columns</div>
                  ' . $table(true, null, null, static function ($i, $c, $h) {
                        if ($c === 'dep' && $i === 1) return '<span class="a-ring" style="--d:1.6s;display:inline-block;border-radius:4px">' . $h . '</span>';
                        if ($c === 'dep' && $i === 0) return '<span class="a-ring" style="--d:4.6s;display:inline-block;border-radius:4px">' . $h . '</span>';
                        if ($c === 'out' && $i === 2) return '<span class="a-ring" style="--d:10.2s;display:inline-block;border-radius:4px">' . $h . '</span>';
                        return $h;
                    }) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:2.4s"><span class="pd">&check; paid</span>&nbsp;or&nbsp;<span class="due">due</span></span>
                    <span class="chip a-pop" style="--d:7.6s">Outstanding = still owed</span>
                    <span class="chip a-pop" style="--d:14s">&#128183; &rarr; Payments, this order picked</span>
                  </div>
                  <div class="row" style="margin-top:.5rem">
                    <span class="chip a-pop" style="--d:18.2s">Outstanding needs the Accounts add-on</span>
                    <span class="chip a-pop" style="--d:21s">Money columns: only for &ldquo;Can see money&rdquo;</span>
                  </div>
                  <div class="a-move" style="--fx:30%;--fy:17rem;--tx:94%;--ty:5.4rem;--d:12s;--md:1.4s">' . $ptr . '</div>
                </div>

                <!-- 9 — the order of the rows -->
                <div class="sc" data-scene="9" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Newest accepted at the top</div>
                  ' . $table(true, static fn ($i) => $i === 0 ? ['a-drop', '--d:5.2s'] : ['', ''], null, static fn ($i, $c, $h) => $c === 'created'
                        ? '<span class="a-ring" style="--d:' . (10.8 + $i * .3) . 's;display:inline-block;border-radius:4px">' . $h . '</span>' : $h) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:6.4s">Written 21 July &middot; accepted this morning &rarr; top</span>
                    <span class="chip a-pop" style="--d:14.8s">Stops at 200 jobs &mdash; no next page</span>
                    <span class="chip ok a-pop" style="--d:19.2s">Old job missing? Search for it</span>
                  </div>
                </div>

                <!-- 10 — ticking rows -->
                <div class="sc" data-scene="10" data-len="20">
                  <div class="stkb">
                    <div class="a-out" style="--d:12.2s">' . $bulk() . '</div>
                    <div class="a-fade" style="--d:12.2s">' . $bulk('(2 of 4 selected)', true) . '</div>
                  </div>
                  ' . $table(true, null,
                        static fn ($i) => in_array($i, [1, 2], true) ? '<span class="tk a-sel" style="--d:' . ($i === 1 ? 12 : 12.6) . 's">&check;</span>' : '<span class="tk"></span>',
                        null,
                        '<span class="stk"><span class="tk a-ring" style="--d:3.2s"></span><span class="tk on a-fade" style="--d:12.8s">&minus;</span></span>') . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:3.6s">Header box = every row on the page</span>
                    <span class="chip a-pop" style="--d:7.2s">Nothing ticked &rarr; buttons greyed out</span>
                    <span class="chip a-pop" style="--d:14.6s">&ldquo;2 of 4&rdquo; = of the rows on screen</span>
                  </div>
                </div>

                <!-- 11 — archive -->
                <div class="sc" data-scene="11" data-len="21">
                  <div class="okb stkb a-pop" style="--d:5.4s"><span class="a-out" style="--d:15.8s">2 jobs archived.</span><span class="a-fade" style="--d:15.8s">1 job restored to active.</span></div>
                  <div class="chips">
                    <span class="stk"><span class="chp on a-out" style="--d:13s">All (51)</span><span class="chp a-fade" style="--d:13s">All (8)</span></span><span class="chp">Paid (8)</span>
                    <span class="arch stk"><span class="chp a-out" style="--d:13s"><span class="a-ring" style="--d:9.2s;border-radius:999px">&#128452; Archived (8)</span></span><span class="chp on a-fade" style="--d:13s"><span class="a-ring" style="--d:18.2s;border-radius:999px">&larr; Back to active</span></span></span>
                  </div>
                  <div class="stkb">
                    <div class="a-out" style="--d:13s">' . $bulk('(2 of 4 selected)', true, '<span class="a-press" style="--d:1.2s">&#128452; Archive selected</span>') . '</div>
                    <div class="a-fade" style="--d:13s">' . $bulk('(1 of 8 selected)', true, '<span class="a-ring" style="--d:14s">Restore selected</span>') . '</div>
                  </div>
                  <div class="stkb">
                    <div class="a-mid" style="--d:0s;--d2:4.4s">' . $table(true, null, static fn ($i) => $i < 2 ? '<span class="tk on">&check;</span>' : '<span class="tk"></span>') . '</div>
                    <div class="a-mid" style="--d:4.4s;--d2:13s">' . $table(true, $only([2, 3])) . '</div>
                    <div class="a-mid" style="--d:13s;--d2:15.8s">' . $table(true, $only([0, 1]), static fn ($i) => $i === 0 ? '<span class="tk on">&check;</span>' : '<span class="tk"></span>') . '</div>
                    <div class="a-fade" style="--d:15.8s">' . $table(true, $only([1])) . '</div>
                  </div>
                  <span class="chip ok a-pop" style="--d:2.6s;margin-top:.6rem">Nothing is lost</span>
                </div>

                <!-- 12 — delete -->
                <div class="sc" data-scene="12" data-len="24">
                  <div class="errb a-fly" style="--d:16.6s">1 quote deleted. 1 quote was kept because it has payment(s) recorded against it: ABC-2026-0031. Delete the payments first if you really want to remove these.</div>
                  ' . $bulk('(2 of 4 selected)', true, '&#128452; Archive selected') . '
                  ' . $swapT($table(true, null, static fn ($i) => in_array($i, [0, 3], true) ? '<span class="tk on">&check;</span>' : '<span class="tk"></span>'),
                        $table(true, $only([1, 2, 3], [3 => ['a-ring', '--d:17.6s']])), 15.6) . '
                  <div class="cdlg a-mid" style="--d:8.2s;--d2:13.6s">Delete the selected quotes? This is permanent &mdash; all blinds, items and appointments go too.<div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:12.6s">OK</span></div></div>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip bad a-pop" style="--d:2.6s">Job + blinds + items + appointments</span>
                    <span class="chip a-pop" style="--d:12.6s">&#163; Payments recorded &rarr; kept</span>
                    <span class="chip ok a-pop" style="--d:22.2s">If in doubt, archive</span>
                  </div>
                </div>

                <!-- 13 — who sees what -->
                <div class="sc" data-scene="13" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Signed in as a fitter</div>
                  <div class="a-rise" style="--d:1.6s">' . $head('Retail Orders', 'Accepted onward &mdash; orders, invoices and paid jobs.', false) . '</div>
                  <div class="stkb">
                    <div class="a-mid" style="--d:3s;--d2:10.6s">' . $chips([['All', 2, 'on'], ['Ordered', 1], ['Fitted', 1]], '') . $table(true, static fn ($i) => in_array($i, [1, 2], true) ? ['', ''] : ['', 'display:none']) . '</div>
                    <div class="a-fade" style="--d:11s;border:1px dashed var(--line);border-radius:10px;padding:1.2rem .8rem;text-align:center;font-size:.72rem;color:var(--faint)">Quotes you&rsquo;re assigned to fit will appear here.</div>
                  </div>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:5.4s">Only the jobs they&rsquo;re booked on &mdash; counts too</span>
                    <span class="chip a-pop" style="--d:17.6s">No <b>+ New quote</b> without &ldquo;create quotes&rdquo;</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>One page, several doors.</b> There is one list of jobs, opened from the menu by <b>Retail &rarr; Quotes</b> and <b>Retail &rarr;
             Orders</b>. Read the heading:
             <b>Retail Orders</b>, <b>Trade Quotes</b> and so on, with the line under it &mdash; <em>&ldquo;Accepted onward &mdash; orders, invoices
             and paid jobs.&rdquo;</em> on the orders side, <em>&ldquo;Quotes still in the pipeline &mdash; drafts, sent, and declined.&rdquo;</em> on
             the quotes side. Under the heading, the <b>List</b> | <b>Pipeline</b> tray swaps to the board view (which shows every job, with no
             retail/trade or quotes/orders filter); top right, <b>+ New quote</b> if you may create quotes.</p>

          <ul class="steps">
            <li><b>Quotes or Orders.</b> A job is a quote until the customer accepts it. The quotes side shows drafts, sent and declined jobs; a
                draft has a small amber <b>Not sent</b> badge (<em>&ldquo;This quote hasn&rsquo;t been sent to the customer yet&rdquo;</em>). The
                orders side shows Accepted, Ordered, Fitted, Invoiced and Paid, and adds the <b>Deposit</b> and <b>Outstanding</b> columns.</li>
            <li><b>Retail or Trade</b> is fixed when the job is created; nothing on this list or in the job moves it. If a job is on the wrong
                side, raise it again on the right one and archive or delete the other.</li>
            <li><b>The chips.</b> <b>All (53)</b> then one chip per stage with its count &mdash; on the orders side <b>Accepted</b>,
                <b>Ordered</b>, <b>Fitted</b>, <b>Invoiced</b>, <b>Paid</b>; on the quotes side <b>Quote</b> (drafts and sent together) and
                <b>Declined</b>. Counts follow the side you&rsquo;re on. A chip with nothing in it isn&rsquo;t drawn at all.</li>
            <li><b>Search</b> &mdash; <em>&ldquo;Search by quote #, customer name, or postcode...&rdquo;</em>; any part of any of them, then
                <b>Search</b>; <b>Clear</b> puts the list back. It only searches the view you&rsquo;re in, and keeps the chip you&rsquo;re on.</li>
            <li><b>Reading a row.</b> <b>Quote #</b> (click to open the job), with &mdash; on an order you may place &mdash; <b>&#128230; Send to
                suppliers</b> (<em>&ldquo;Send this order to its suppliers&rdquo;</em>) or <b>&#10003; Sent to suppliers &middot; resend</b> once it
                has gone (an order your factory makes entirely in-house has neither). Then <b>Customer</b>, <b>Postcode</b>, <b>Status</b> (your
                colours from <b>Settings &rarr; Status colours</b>, plus <b>With the factory: Confirmed / In Production / Ready to dispatch /
                Dispatched</b> on orders placed with your factory), <b>Created</b> and <b>Total</b>.</li>
            <li><b>Deposit</b> &mdash; <b>&check; &pound;620.00 paid</b> in green, <b>&pound;430.00 due</b> in amber, or a dash when there is no
                deposit. <b>Outstanding</b> &mdash; an amber, dashed-underlined figure is a link (<em>&ldquo;Click to take a payment against this
                order&rdquo;</em>) that opens Payments with the order chosen; <b>&check; paid</b> when settled; a blue <b>+&pound;20.00</b> when
                overpaid. Outstanding needs the Accounts add-on; Total, Deposit and Outstanding only show to people allowed to see money.</li>
            <li><b>Order and limit.</b> Rows run newest-accepted first (newest-created for anything not yet accepted), so <b>Created</b> dates can
                look out of order on the orders side. The list stops at <b>200</b> jobs with no next page &mdash; search for an older one.</li>
          </ul>

          <p><b>Ticking rows.</b> Tick a row&rsquo;s box, or the header box (<em>&ldquo;Select all visible quotes&rdquo;</em> &mdash; it shows a dash
             when only some are ticked). Until something is ticked the buttons are greyed out; the counter reads <b>(none selected)</b> then, e.g.,
             <b>(2 of 4 selected)</b> &mdash; of the rows on screen.</p>
          <ul class="steps">
            <li><b>&#128452; Archive selected</b> hides finished jobs without losing anything: <code>2 jobs archived.</code> They sit behind
                <b>&#128452; Archived (n)</b> at the end of the chips (shown once anything is archived); inside, the chip reads <b>&larr; Back to
                active</b> and the button <b>Restore selected</b>: <code>1 job restored to active.</code> Archiving is for admins and people who
                can see everyone&rsquo;s jobs (<em>&ldquo;Only an admin can archive jobs.&rdquo;</em>).</li>
            <li><b>Delete selected</b> asks <em>&ldquo;Delete the selected quotes? This is permanent &mdash; all blinds, items and appointments go
                too.&rdquo;</em> A job with payments recorded against it is kept: <code>1 quote deleted. 1 quote was kept because it has payment(s)
                recorded against it: ABC-2026-0031. Delete the payments first if you really want to remove these.</code></li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The filters don&rsquo;t all stack.</b> Clicking a chip clears what you typed
             in the search box. Searching from inside <b>Archived</b> takes you back to the active list. And the <b>Pipeline</b> button shows the
             whole board, whatever side you were on.</div></div>

          <p><b>Who sees what.</b> Someone who may only see their own work (a fitter, say) sees just the jobs they are booked on, and the chip
             counts follow. Empty pages say <em>&ldquo;Quotes you&rsquo;re assigned to fit will appear here.&rdquo;</em>, <em>&ldquo;Nothing matches
             your filter. Clear filters.&rdquo;</em> or, on a new account, <em>&ldquo;No quotes yet. Start a new quote &rarr;&rdquo;</em>.</p>',
        'script'  => [
            ['1', 'One list, several doors',   'Every quote and every order lives in one list. The menu on the left opens it from more than one door. Under Retail there are Quotes and Orders. They both open the same screen. What changes is which jobs it lets through: quotes still being worked on, or orders from accepted onward.', 1],
            ['2', 'Read the heading first',    'So the first thing to do is read the heading. Here it says Retail Orders. That tells you two things. These are your own retail customers, not trade accounts. And these are orders, not quotes. The line underneath agrees: accepted onward, orders, invoices and paid jobs. Read it, and you always know where you are.', 2],
            ['3', 'The quotes side',           'Now click Quotes instead. It is the same page, showing the other end of the job. A job is a quote until the customer accepts it. So here you see drafts, sent quotes, and declined ones. A draft carries a small amber Not sent badge, meaning the customer has never seen it. The money columns have gone, because there is no deposit on a quote.', 3],
            ['4', 'Retail or trade',           'Whether a job is retail or trade is decided once, when it is created. Nothing afterwards moves it, and there is no switch on this list to do so. So if a job is on the wrong side, it was raised on the wrong side. The fix is to raise it again on the right side, then archive the one you do not want.', 4],
            ['5', 'The status chips',          'Under the heading is a row of chips. All shows everything in this view, with the number of jobs in brackets. The others are the stages: Accepted, Ordered, Fitted, Invoiced and Paid. Click one to keep only those jobs. Click All to see them all again. A stage with nothing in it has no chip at all, so a missing chip is never a fault.', 5],
            ['6', 'Searching',                 'The search box looks at three things: the quote number, the customer\'s name, and the postcode. Part of any of them will do. Type, then press Search. A Clear button appears, to put the whole list back. Remember, search only looks inside the view you are in. Clicking a chip also clears the search, so search last.', 6],
            ['7', 'Reading a row',             'Now read a row. The quote number is the way in: click it, and the job opens. Under it, on an order, a small link sends it to its suppliers, or says it has already been sent. Then the customer, the postcode, and the status. The status colours are your own, from Settings, so a job looks the same here as on the calendar.', 7],
            ['8', 'The money columns',         'On the orders side, Deposit shows whether the money up front is in, in green, or still due, in amber. Outstanding is what is still owed. When the figure is underlined, it is a link. Click it, and Payments opens with that order already picked. Outstanding only shows with the Accounts add-on, and money columns only show to people allowed to see money.', 8],
            ['9', 'The order of the rows',     'The newest jobs are at the top, newest by the day they were accepted. So a job written in July, but accepted this morning, sits at the very top. That is why the Created dates can look out of order. The list also stops at two hundred jobs, with no next page. If an old job will not appear, search for it.', 9],
            ['10', 'Ticking rows',             'Down the left of each row is a tick box. The one in the header ticks every row on the page. Until something is ticked, the buttons above the table are greyed out. Tick a few, and they wake up, and the counter tells you how many of the rows on screen you have picked.', 10],
            ['11', 'Archive, the safe one',    'Archive selected tidies finished jobs out of the way, and nothing is lost. A green bar tells you how many were archived. They wait behind the Archived chip at the end of the row. Open it, tick a job, and press Restore selected to bring it back. Back to active takes you home again.', 11],
            ['12', 'Delete, the permanent one', 'Delete selected is different. It removes the job and everything on it: the blinds, the items and the appointments. It asks once, and that is your only chance to stop. A job with a payment recorded against it is never deleted. It is kept, and named in a red bar, so your books never have a hole in them. If in doubt, archive.', 12],
            ['13', 'Who sees what',            'Not everyone sees every job. Someone allowed only their own work, such as a fitter, sees just the jobs they are booked on, and the counts follow suit. With nothing to show yet, the page says, quotes you\'re assigned to fit will appear here. And the plus New quote button only shows to people who can create quotes.', 13],
        ],
];
