<?php
declare(strict_types=1);

/**
 * Guide: orders-pipeline — "The Pipeline board" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors /orders/pipeline.php: the seven columns (Quote = draft + sent),
 * per-column count + £ value, the "N jobs · £X in pipeline" summary, the
 * filter bar (search, Window: Last 30–365 days, All time, Mine only,
 * Apply), the cards (name, number · postcode, Not sent, Paid / Part paid
 * chip, value, age with "Last touched:", "£X outstanding" on Invoiced),
 * the 50-card cap footer, "No jobs", the money gate (Can see money), no
 * drag-drop, and the 20-second live sync (orders/pipeline_poll.php). The
 * automatic movers are from quote-history/public.php + accept.php,
 * calendar/view.php, pdf-generator/send_invoice.php and qb_settle_if_paid.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// Columns: key => [label, palette colour, text colour, count, £ value]
$COLS = [
    'quote'    => ['Quote',    '#f59e0b', '#111827', 5, '6,420'],
    'declined' => ['Declined', '#dc2626', '#fff',    1, '455'],
    'accepted' => ['Accepted', '#16a34a', '#fff',    2, '1,840'],
    'ordered'  => ['Ordered',  '#0891b2', '#fff',    2, '2,100'],
    'fitted'   => ['Fitted',   '#0d9488', '#fff',    1, '540'],
    'invoiced' => ['Invoiced', '#ea580c', '#fff',    1, '1,600'],
    'paid'     => ['Paid',     '#475569', '#fff',    2, '3,045'],
];
// Cards: [name, number, postcode, £, age, flags]; flags: ns = Not sent, full / part = paid chip, owe = outstanding
$CARDS = [
    'quote'    => [['Kim Ward', 'ABC-2026-0051', 'CV35 9RT', '710.00', '2d ago', ['ns']], ['Lucy Hart', 'ABC-2026-0049', 'CV32 6NB', '1,385.00', '6d ago', []]],
    'declined' => [['Sam Cole', 'ABC-2026-0047', 'CV37 7HQ', '455.00', '2w ago', []]],
    'accepted' => [['Ray Patel', 'ABC-2026-0053', 'CV8 2LE', '860.00', '5h ago', ['full']], ['Joe Bloggs', 'ABC-2026-0029', 'CV8 1AA', '980.00', '1d ago', []]],
    'ordered'  => [['Emma Fletcher', 'ABC-2026-0042', 'CV32 5PJ', '1,240.00', '1d ago', ['part']], ['Tom Shah', 'ABC-2026-0045', 'CV34 4AB', '860.00', '4d ago', ['part']]],
    'fitted'   => [['Dan Price', 'ABC-2026-0038', 'CV31 2QA', '540.00', '1w ago', []]],
    'invoiced' => [['Mia Grant', 'ABC-2026-0033', 'CV10 0TU', '1,600.00', '3d ago', ['part', 'owe']]],
    'paid'     => [['Angela Reed', 'ABC-2026-0031', 'CV31 1XY', '2,145.00', '2w ago', ['full']], ['Bob Lee', 'ABC-2026-0027', 'CV4 7AL', '900.00', '28 Aug', ['full']]],
];

/** One card. $ring = [part => '--d:…'] puts an a-ring on that part (name/ns/chip/age/owe/card). */
$card = static function (array $c, array $ring = [], string $x = '', string $st = ''): string {
    [$nm, $no, $pc, $val, $age, $fl] = $c;
    $r = static fn (string $k, string $html, string $br = '4px') => isset($ring[$k]) ? '<span class="a-ring" style="' . $ring[$k] . ';display:inline-block;border-radius:' . $br . '">' . $html . '</span>' : $html;
    $chip = in_array('full', $fl, true) ? '<span class="pchip full" title="Paid in full">Paid</span>'
          : (in_array('part', $fl, true) ? '<span class="pchip part">Part paid</span>' : '');
    $h  = '<span class="pcard ' . $x . ($chip ? ' haschip' : '') . '" style="' . $st . '">' . ($chip ? $r('chip', $chip, '999px') : '');
    $h .= '<span class="nm">' . $nm . '</span><span class="nu">' . $no . ' <i>&middot; ' . $pc . '</i></span>';
    if (in_array('ns', $fl, true)) $h .= '<span>' . $r('ns', '<span class="ns" title="This quote hasn\'t been sent to the customer yet">Not sent</span>', '999px') . '</span>';
    $h .= '<span class="rw"><b>&pound;' . $val . '</b>' . $r('age', '<span class="ag">' . $age . '</span>') . '</span>';
    if (in_array('owe', $fl, true)) $h .= $r('owe', '<span class="owe">&pound;800.00 outstanding</span>');
    return $h . '</span>';
};

/**
 * The board. $colAnim(key, i) → [class, style] for each column; $cardAnim(key, j) → [class, style] per card;
 * $ring = [key => [j => ring map]]; $head(key) → extra html after the header meta.
 */
$board = static function (?callable $colAnim = null, ?callable $cardAnim = null, array $ring = [], ?callable $headX = null, array $extra = []) use ($COLS, $CARDS, $card): string {
    $h = '<div class="board">';
    $i = 0;
    foreach ($COLS as $k => [$lbl, $bg, $fg, $n, $v]) {
        [$cc, $cs] = $colAnim ? $colAnim($k, $i) : ['', ''];
        $h .= '<div class="col ' . $cc . '" style="' . $cs . '"><div class="ch"><span class="cn" style="background:' . $bg . ';color:' . $fg . '">' . $lbl . '</span>'
            . '<span class="cm"><b>' . $n . '</b><i>&pound;' . $v . '</i></span>' . ($headX ? $headX($k) : '') . '</div><div class="cb">';
        foreach ($CARDS[$k] as $j => $c) {
            [$xc, $xs] = $cardAnim ? $cardAnim($k, $j) : ['', ''];
            $h .= $card($c, $ring[$k][$j] ?? [], $xc, $xs);
        }
        $h .= ($extra[$k] ?? '') . '</div></div>';
        $i++;
    }
    return $h . '</div>';
};

$head = '<div class="hd"><div class="ttl">Pipeline</div><div class="sub">Where every job is in the funnel.</div>'
      . '<span class="tray"><span>List</span><span class="on">Pipeline</span></span></div>';

/** The filter bar. */
$bar = static fn (string $q = '', string $win = 'Last 90 days', string $all = '<span class="pch">All time</span>', string $mine = '<span class="pch">Mine only</span>', string $sum = '<b>14</b> jobs &middot; <b>&pound;16,000</b> in pipeline'): string =>
    '<div class="fbar"><span class="sbx">' . ($q !== '' ? $q : '<span class="phd">Search customer / quote # / postcode&hellip;</span>') . '</span>'
  . '<span class="lb">Window: <span class="sel">' . $win . '</span></span>' . $all . $mine . '<span class="btns">Apply</span><span class="sum">' . $sum . '</span></div>';

return [
        'aud'     => 'admin',
        'section' => 'Orders',
        'title'   => 'The Pipeline board',
        'eyebrow' => 'Orders · Pipeline',
        'v'       => 2,
        'blurb'   => 'Every job you have on, on one board — seven columns with a count and the money in each, what the flags on a card mean, and what actually moves a job from one column to the next.',
        'lede'    => 'The <b>Pipeline</b> shows the same jobs as your <b>Quotes</b> and <b>Orders</b> lists &mdash; the whole funnel on one
                      board instead of two tables. Seven columns, <b>Quote</b> to <b>Paid</b>, each with how many jobs are in it and what they
                      are worth. You <b>read</b> this board; you don&rsquo;t drag cards on it &mdash; jobs move from their own page, or by
                      themselves. This guide goes <b>slowly</b>, one idea per chapter. To get there: <b>Pipeline</b>, under <b>Work</b> in the menu.',
        'open'    => '/orders/pipeline.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:370px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .row{ display:flex; gap:.45rem; flex-wrap:wrap; align-items:center; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:6px; padding:.2rem .5rem; font-size:.62rem; font-weight:600; white-space:nowrap; }
          .gd .stk{ display:inline-grid; justify-items:start; } .gd .stk > *{ grid-area:1/1; }

          /* heading + filter bar */
          .gd .hd{ margin-bottom:.45rem; }
          .gd .ttl{ font-size:1rem; font-weight:800; color:var(--ink); }
          .gd .sub{ font-size:.66rem; color:var(--faint); margin:.1rem 0 .3rem; }
          .gd .tray{ display:inline-flex; background:var(--panel); border-radius:8px; padding:.12rem; font-size:.64rem; font-weight:700; }
          .gd .tray span{ padding:.16rem .6rem; border-radius:6px; color:var(--faint); }
          .gd .tray span.on{ background:var(--surface); color:var(--ink); box-shadow:0 1px 2px rgba(0,0,0,.08); }
          .gd .fbar{ display:flex; flex-wrap:wrap; gap:.35rem .45rem; align-items:center; border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--surface); margin-bottom:.5rem; font-size:.62rem; }
          .gd .sbx{ flex:0 1 11rem; min-width:7rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.18rem .4rem; color:var(--ink); white-space:nowrap; overflow:hidden; }
          .gd .phd{ color:var(--faint); }
          .gd .lb{ color:var(--faint); }
          .gd .sel{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.12rem .4rem; color:var(--ink); }
          .gd .sel::after{ content:"\25BE"; margin-left:.35rem; color:var(--faint); }
          .gd .pch{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.12rem .45rem; color:var(--accent); white-space:nowrap; }
          .gd .pch.on{ background:var(--accent); color:#fff; border-color:var(--accent); }
          .gd .sum{ margin-left:auto; color:var(--soft); white-space:nowrap; border-radius:5px; }
          .gd .sum b{ color:var(--ink); }

          /* the board */
          .gd .stage{ min-width:0; }
          .gd .board{ display:grid; grid-template-columns:repeat(7,minmax(5.4rem,1fr)); gap:.3rem; overflow:hidden; max-width:100%; }
          .gd .col{ background:var(--panel); border:1px solid var(--line); border-radius:8px; min-height:11rem; min-width:0; }
          .gd .ch{ padding:.3rem .35rem; border-bottom:1px solid var(--line); }
          .gd .cn{ display:inline-flex; border-radius:999px; padding:.02rem .4rem; font-size:.5rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
          .gd .cm{ display:flex; gap:.3rem; align-items:baseline; margin-top:.15rem; font-size:.66rem; }
          .gd .cm b{ color:var(--ink); } .gd .cm i{ font-style:normal; color:var(--faint); font-size:.6rem; }
          .gd .cb{ padding:.25rem; display:flex; flex-direction:column; gap:.25rem; }
          .gd .pcard{ display:block; position:relative; background:var(--surface); border:1px solid var(--line); border-radius:6px; padding:.25rem .3rem; font-size:.54rem; line-height:1.35; color:var(--ink); }
          .gd .pcard .nm{ display:block; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .pcard.haschip .nm{ padding-right:1.9rem; }
          .gd .pcard .nu{ display:block; color:var(--faint); font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.46rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .pcard .nu i{ font-style:normal; font-family:inherit; }
          .gd .pcard .rw{ display:flex; justify-content:space-between; gap:.2rem; margin-top:.1rem; }
          .gd .pcard .rw b{ color:#16a34a; }
          .gd .pcard .ag{ color:var(--faint); font-size:.48rem; }
          .gd .pcard .owe{ display:block; color:#ef4444; font-weight:700; font-size:.48rem; }
          .gd .ns{ display:inline-flex; margin-top:.15rem; border-radius:999px; padding:0 .3rem; font-size:.42rem; font-weight:800; text-transform:uppercase; color:#92400e; background:#fef3c7; border:1px solid #fde68a; }
          .gd .pchip{ position:absolute; top:.22rem; right:.22rem; border-radius:999px; padding:0 .25rem; font-size:.4rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
          .gd .pchip.full{ background:#d1fae5; color:#065f46; } .gd .pchip.part{ background:#fef3c7; color:#92400e; }
          .gd .trunc{ font-size:.5rem; color:var(--faint); text-align:center; padding:.25rem; border-top:1px dashed var(--line); background:var(--surface); border-radius:0 0 6px 6px; }

          /* zoomed card (4) + single column (5, 9) */
          .gd .zoom .pcard{ font-size:.82rem; padding:.55rem .7rem; max-width:17rem; border-radius:10px; }
          .gd .zoom .pcard .nu{ font-size:.7rem; } .gd .zoom .pcard .ag{ font-size:.72rem; } .gd .zoom .pcard .owe{ font-size:.7rem; }
          .gd .zoom .ns{ font-size:.6rem; padding:.05rem .45rem; } .gd .zoom .pchip{ font-size:.58rem; padding:.05rem .4rem; top:.45rem; right:.45rem; }
          .gd .zoom .pcard.haschip .nm{ padding-right:4.2rem; }
          .gd .tip{ display:inline-block; background:#0f172a; color:#fff; font-size:.62rem; font-weight:700; border-radius:6px; padding:.2rem .45rem; margin-left:.4rem; }
          .gd .solo{ display:grid; grid-template-columns:minmax(0,13rem) 1fr; gap:.9rem; align-items:start; }
          .gd .solo .col .pcard{ font-size:.66rem; } .gd .solo .col .nu{ font-size:.56rem; } .gd .solo .col .ag{ font-size:.58rem; }
          .gd .arrows{ display:flex; flex-direction:column; justify-content:space-between; gap:.5rem; min-height:12rem; }
          .gd .ev{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; border:1px solid var(--line); border-radius:10px; padding:.4rem .55rem; background:var(--surface); font-size:.7rem; color:var(--ink); margin-bottom:.4rem; }
          .gd .ev .to{ margin-left:auto; display:inline-flex; border-radius:999px; padding:.04rem .45rem; font-size:.56rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
          .gd .ghost{ display:block; width:6.5rem; transform:rotate(-3deg); box-shadow:0 8px 18px -10px rgba(20,30,45,.55); }
          .gd .nodrop{ position:absolute; z-index:6; width:1.7rem; height:1.7rem; border-radius:50%; background:var(--err); color:#fff; display:grid; place-items:center; font-weight:900; font-size:.9rem; }
          .gd .qa{ border:1px solid var(--line); border-radius:10px; padding:.45rem .55rem; background:var(--surface); max-width:26rem; }
          .gd .qa h4{ margin:0 0 .35rem; font-size:.74rem; color:var(--ink); }
          .gd .qa .row .btns{ font-size:.66rem; padding:.26rem .55rem; }
          .gd .pulse{ display:inline-flex; align-items:center; gap:.35rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:440px; }
            .gd .board{ grid-template-columns:repeat(7,5.4rem); }
            .gd .solo{ grid-template-columns:1fr; } .gd .arrows{ min-height:0; }
            .gd .sum{ margin-left:0; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / orders / pipeline</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a class="on">Pipeline</a><a>Factory</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head . $bar() . $board() . '
                  <p class="sub" style="margin-top:.6rem">Press <b>&#9654; Play</b> below &mdash; twelve short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what the board is -->
                <div class="sc" data-scene="1" data-len="22">
                  <div class="a-fade" style="--d:.4s">' . $head . '</div>
                  ' . $board(static fn ($k, $i) => ['a-rise', '--d:' . (4.2 + $i * .5) . 's'], static fn ($k, $j) => ['a-drop', '--d:' . (8 + $j * .6) . 's']) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:1.4s">Menu: <b>Work</b> &rarr; <b>Pipeline</b></span>
                    <span class="chip a-pop" style="--d:6.4s">Same jobs as your Quotes + Orders lists</span>
                    <span class="chip ok a-pop" style="--d:13.6s">Nothing extra to keep up to date</span>
                  </div>
                </div>

                <!-- 2 — seven columns -->
                <div class="sc" data-scene="2" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Seven columns, in the order a job travels</div>
                  ' . $board(static fn ($k, $i) => [($k === 'quote' ? 'a-ring' : ($k === 'declined' ? 'a-ring' : 'a-fade')), '--d:' . ($k === 'quote' ? 4.2 : ($k === 'declined' ? 12.4 : 17.8 + ($i - 2) * .45)) . 's']) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:5s">Quote = drafts + sent quotes</span>
                    <span class="chip a-pop" style="--d:13s">Declined &mdash; kept out of the way</span>
                    <span class="chip a-pop" style="--d:18.4s">Accepted &rarr; Ordered &rarr; Fitted &rarr; Invoiced &rarr; Paid</span>
                  </div>
                </div>

                <!-- 3 — how many and how much -->
                <div class="sc" data-scene="3" data-len="25">
                  ' . $bar('', 'Last 90 days', '<span class="pch">All time</span>', '<span class="pch">Mine only</span>', '<span class="a-ring" style="--d:10s;border-radius:5px"><b>14</b> jobs &middot; <b>&pound;16,000</b> in pipeline</span>') . '
                  ' . $board(static fn ($k, $i) => $k === 'quote' ? ['a-ring', '--d:17s'] : ['', ''], null, [], static fn ($k) => $k === 'quote'
                        ? '<span class="row" style="gap:.2rem;margin-top:.15rem"><span class="chip a-pop" style="--d:3.2s;font-size:.5rem;padding:.05rem .3rem">jobs</span><span class="chip a-pop" style="--d:6.8s;font-size:.5rem;padding:.05rem .3rem">worth</span></span>' : '') . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:13s">Top right: all the columns added up</span>
                    <span class="chip a-pop" style="--d:20.4s">Not allowed to see money? Counts only</span>
                  </div>
                </div>

                <!-- 4 — reading a card -->
                <div class="sc" data-scene="4" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">One card, close up</div>
                  <div class="zoom" style="margin:.8rem 0">' . $card($CARDS['ordered'][0], ['age' => '--d:8.4s'], 'a-rise', '--d:1s') . '</div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:2s">Customer</span>
                    <span class="chip a-pop" style="--d:4.2s">Quote # &middot; postcode</span>
                    <span class="chip a-pop" style="--d:7.6s">Value</span>
                    <span class="chip a-pop" style="--d:9.4s">Since anyone touched it</span>
                    <span class="tip a-pop" style="--d:13.4s">Last touched: 2026-10-06 14:22:07</span>
                  </div>
                  <span class="chip ok a-pop" style="--d:16.6s;margin-top:.6rem">Click anywhere &rarr; the job opens</span>
                  <div class="a-move" style="--fx:80%;--fy:16rem;--tx:9rem;--ty:3.8rem;--d:15.4s;--md:1.2s">' . $ptr . '</div>
                </div>

                <!-- 5 — newest touched first -->
                <div class="sc" data-scene="5" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Newest touched at the top</div>
                  <div class="solo">
                    <div class="col"><div class="ch"><span class="cn" style="background:#f59e0b;color:#111827">Quote</span><span class="cm"><b>5</b><i>&pound;6,420</i></span></div><div class="cb">
                      ' . $card(['Kim Ward', 'ABC-2026-0051', 'CV35 9RT', '710.00', 'just now', ['ns']], [], 'a-drop', '--d:1s') . '
                      ' . $card(['Lucy Hart', 'ABC-2026-0049', 'CV32 6NB', '1,385.00', '3h ago', []], [], 'a-fade', '--d:1.6s') . '
                      ' . $card(['Nina Shaw', 'ABC-2026-0046', 'CV33 0FG', '620.00', '4d ago', []], [], 'a-fade', '--d:5s') . '
                      ' . $card(['Pete Dunn', 'ABC-2026-0040', 'CV36 4DD', '1,150.00', '2w ago', []], [], 'a-fade', '--d:5.6s') . '
                      ' . $card(['Ann Moss', 'ABC-2026-0034', 'CV47 1PL', '890.00', '14 Aug', []], ['age' => '--d:13.8s'], 'a-fade', '--d:6.2s') . '
                    </div></div>
                    <div class="arrows">
                      <span class="chip a-pop" style="--d:1.4s">&uarr; busy &mdash; just worked on</span>
                      <span class="chip a-pop" style="--d:9.4s">reading down = busy &rarr; forgotten</span>
                      <span class="chip bad a-pop" style="--d:13.6s">&darr; forgotten &mdash; give these a nudge</span>
                    </div>
                  </div>
                </div>

                <!-- 6 — three flags -->
                <div class="sc" data-scene="6" data-len="24">
                  ' . $board(null, null, ['quote' => [0 => ['ns' => '--d:2.8s']], 'accepted' => [0 => ['chip' => '--d:9.8s']], 'ordered' => [0 => ['chip' => '--d:12s']], 'invoiced' => [0 => ['owe' => '--d:17.6s']]]) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:3.4s"><span class="ns" style="margin:0">Not sent</span> still on your desk</span>
                    <span class="chip a-pop" style="--d:10.4s"><span class="pchip full" style="position:static">Paid</span> / <span class="pchip part" style="position:static">Part paid</span> on any card</span>
                    <span class="chip bad a-pop" style="--d:18.2s">&pound; outstanding &mdash; your chasing list</span>
                  </div>
                </div>

                <!-- 7 — the time window -->
                <div class="sc" data-scene="7" data-len="22">
                  <div class="fbar"><span class="sbx"><span class="phd">Search customer / quote # / postcode&hellip;</span></span>
                    <span class="lb" style="position:relative">Window: <span class="sel a-ring" style="--d:.6s">Last 90 days</span>
                      <span class="a-mid" style="--d:3.6s;--d2:9.6s;position:absolute;left:2.6rem;top:1.4rem;z-index:4;background:var(--surface);border:1px solid var(--line);border-radius:6px;box-shadow:var(--gd-shadow);padding:.15rem;display:flex;flex-direction:column;font-size:.6rem;color:var(--ink);white-space:nowrap">
                        <span style="padding:.1rem .4rem">Last 30 days</span><span style="padding:.1rem .4rem">Last 60 days</span><span style="padding:.1rem .4rem;background:var(--accent);color:#fff;border-radius:4px">Last 90 days</span><span style="padding:.1rem .4rem">Last 180 days</span><span style="padding:.1rem .4rem">Last 365 days</span></span></span>
                    <span class="stk"><span class="pch a-out" style="--d:17.6s">All time</span><span class="pch on a-fade" style="--d:17.6s">All time &times;</span></span>
                    <span class="pch">Mine only</span><span class="btns">Apply</span>
                    <span class="sum stk"><span class="a-out" style="--d:18s"><b>14</b> jobs &middot; <b>&pound;16,000</b> in pipeline</span><span class="a-fade" style="--d:18s"><b>23</b> jobs &middot; <b>&pound;27,480</b> in pipeline</span></span></div>
                  ' . $board() . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:10.8s">Old job &ldquo;vanished&rdquo;? Older than the window</span>
                    <span class="chip a-pop" style="--d:19.6s">All time again &rarr; back to the window</span>
                  </div>
                </div>

                <!-- 8 — search and mine only -->
                <div class="sc" data-scene="8" data-len="21">
                  <div class="fbar"><span class="sbx a-ring" style="--d:.6s"><span class="stk"><span class="phd a-out" style="--d:2.6s">Search customer / quote # / postcode&hellip;</span><span class="a-type" style="--d:2.6s;--ts:8;--tt:.7s">Fletcher</span></span></span>
                    <span class="lb">Window: <span class="sel">Last 90 days</span></span><span class="pch">All time</span>
                    <span class="stk"><span class="pch a-out" style="--d:7.4s">Mine only</span><span class="pch on a-fade" style="--d:7.4s">Mine only &times;</span></span>
                    <span class="btns a-press" style="--d:4.8s">Apply</span><span class="sum"><b>1</b> job &middot; <b>&pound;1,240</b> in pipeline</span></div>
                  <div class="row" style="margin:.2rem 0 .6rem">
                    <span class="chip a-pop" style="--d:1.2s">customer &middot; quote # &middot; postcode</span>
                    <span class="chip a-pop" style="--d:8.4s">Mine only = jobs you created, or have an appointment on</span>
                    <span class="chip a-pop" style="--d:12.4s">Only there if you can see everyone&rsquo;s jobs</span>
                    <span class="chip ok a-pop" style="--d:16.4s">Can only see your own? It&rsquo;s already yours</span>
                  </div>
                </div>

                <!-- 9 — very full columns -->
                <div class="sc" data-scene="9" data-len="19">
                  <div class="solo">
                    <div class="col"><div class="ch"><span class="cn" style="background:#475569;color:#fff">Paid</span><span class="cm a-ring" style="--d:7s;border-radius:4px"><b>63</b><i>&pound;71,950</i></span></div><div class="cb">
                      ' . $card($CARDS['paid'][0]) . $card($CARDS['paid'][1]) . $card(['Hal Ford', 'ABC-2026-0024', 'CV2 3RR', '1,320.00', '28 Aug', ['full']]) . '
                      <span class="nu" style="text-align:center;color:var(--faint);font-size:.6rem">&hellip; 47 more &hellip;</span></div>
                      <div class="trunc a-pop" style="--d:2.4s;font-size:.58rem">Showing 50 of 63 &middot; refine the window or search</div></div>
                    <div class="arrows" style="justify-content:flex-start">
                      <span class="chip a-pop" style="--d:3.4s">The newest fifty cards are shown</span>
                      <span class="chip ok a-pop" style="--d:7.6s">Count and &pound; at the top = the true totals</span>
                      <span class="chip a-pop" style="--d:11.8s">Narrow the Window, or search</span>
                    </div>
                  </div>
                </div>

                <!-- 10 — cards do not drag -->
                <div class="sc" data-scene="10" data-len="23">
                  ' . $board() . '
                  <div class="a-move" style="--fx:30%;--fy:4.2rem;--tx:43%;--ty:5.4rem;--d:.8s;--md:2s"><span class="a-out" style="--d:3.6s;display:block">' . $card($CARDS['accepted'][1], [], 'ghost') . '</span>' . $ptr . '</div>
                  <span class="nodrop a-mid" style="--d:3s;--d2:6s;left:46%;top:5.6rem">&#10005;</span>
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip a-pop" style="--d:7.4s">stamps dates</span><span class="chip a-pop" style="--d:8.6s">books a fitting</span>
                    <span class="chip a-pop" style="--d:9.8s">emails suppliers</span><span class="chip a-pop" style="--d:11s">sets a promised date</span>
                  </div>
                  <div class="qa a-rise" style="--d:16.8s;margin-top:.6rem"><h4>Quote actions</h4><div class="row"><span class="btns">View PDF</span><span class="btns">Download PDF</span><span class="btnp">&#128230; Save as order</span><span class="btns a-ring" style="--d:19.6s">Mark as accepted</span><span class="btns">Mark as declined</span><span class="btns">Reopen as draft</span></div></div>
                </div>

                <!-- 11 — cards that move by themselves -->
                <div class="sc" data-scene="11" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Moves that happen by themselves</div>
                  <div class="ev a-fly" style="--d:2.4s">&#128064; The customer opens your link <span class="ns" style="margin:0" >Not sent</span> <span class="a-fade" style="--d:5.6s">&rarr; gone</span><span class="to" style="background:#f59e0b;color:#111827">Quote &middot; sent</span></div>
                  <div class="ev a-fly" style="--d:9.6s">&#10003; The customer accepts online<span class="to" style="background:#16a34a;color:#fff">Accepted</span></div>
                  <div class="ev a-fly" style="--d:12.6s">&#128197; The fitting is marked Completed on the calendar<span class="to" style="background:#0d9488;color:#fff">Fitted</span></div>
                  <div class="ev a-fly" style="--d:16.8s">&#129534; You send the invoice<span class="to" style="background:#ea580c;color:#fff">Invoiced</span></div>
                  <div class="ev a-fly" style="--d:19.9s">&pound; Deposit + payments cover the total<span class="to" style="background:#475569;color:#fff">Paid</span></div>
                  <span class="chip a-pop" style="--d:22.4s">All your own make? Accepting can jump straight to Ordered</span>
                </div>

                <!-- 12 — always up to date -->
                <div class="sc" data-scene="12" data-len="25">
                  <div class="hd"><div class="ttl">Pipeline</div><div class="sub">Where every job is in the funnel.</div>
                    <span class="tray a-ring" style="--d:19.4s"><span>List</span><span class="on">Pipeline</span></span></div>
                  ' . $board(null, null, [], null, ['accepted' => '<span class="a-drop" style="--d:7.2s;display:block">' . $card(['Ivy North', 'ABC-2026-0055', 'CV6 5JX', '1,075.00', 'just now', []]) . '</span>']) . '
                  <div class="row" style="margin-top:.6rem">
                    <span class="chip pulse a-pop" style="--d:2.6s">&#8635; checks every 20 seconds</span>
                    <span class="chip a-pop" style="--d:7.6s">Refreshes only when something moved</span>
                    <span class="chip a-pop" style="--d:10.2s">Only while the page is on screen</span>
                    <span class="chip ok a-pop" style="--d:16.2s">Fine on a second screen</span>
                    <span class="chip a-pop" style="--d:20.2s">List &rarr; back to the Orders list</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> <b>Pipeline</b> sits under <b>Work</b> in the menu (also the <b>Pipeline</b> half of the
             <b>List</b> | <b>Pipeline</b> tray on the Orders list). It is headed <b>Pipeline</b> &mdash; <em>&ldquo;Where every job is in the
             funnel.&rdquo;</em> It shows the same jobs as your Quotes and Orders lists, retail and trade together; there is nothing separate to
             keep up to date.</p>

          <ul class="steps">
            <li><b>Seven columns</b>, in the order a job travels: <b>Quote</b> (drafts and sent quotes together), <b>Declined</b>,
                <b>Accepted</b>, <b>Ordered</b>, <b>Fitted</b>, <b>Invoiced</b>, <b>Paid</b>. The column colours are your own, from
                <b>Settings &rarr; Status colours</b>. On a narrow screen the board scrolls sideways; an empty column says <b>No jobs</b>.</li>
            <li><b>Count and money.</b> Under each column name: the number of jobs (bold) and their total value (grey, to the pound). Top right of
                the filter bar: <b>14 jobs &middot; &pound;16,000 in pipeline</b> &mdash; all the columns added up. Without permission to see money,
                every &pound; figure and the Paid / Part paid chips are hidden and you see counts only.</li>
            <li><b>A card</b> is one job: the customer (<b>No name</b> if none), the quote number and postcode, the value, and how long since it
                was last touched (<em>just now</em>, <em>12m ago</em>, <em>5h ago</em>, <em>3d ago</em>, <em>2w ago</em>, then a date like
                <em>28 Aug</em>). Hover the age for <b>Last touched:</b> and the exact time. Click anywhere on the card to open the job. Each column
                is sorted newest-touched first, so forgotten jobs sink to the bottom.</li>
            <li><b>The flags.</b> <b>Not sent</b> (amber, drafts only &mdash; <em>&ldquo;This quote hasn&rsquo;t been sent to the customer
                yet&rdquo;</em>). <b>Paid</b> (<em>&ldquo;Paid in full&rdquo;</em>) or <b>Part paid</b> (hover: <em>&ldquo;&pound;620.00 received of
                &pound;1,240.00&rdquo;</em>) in the corner of any card with payments recorded, in any column. In the <b>Invoiced</b> column only,
                <b>&pound;800.00 outstanding</b> in red &mdash; your chasing list.</li>
            <li><b>Window:</b> <b>Last 30 days</b>, <b>Last 60 days</b>, <b>Last 90 days</b> (the default), <b>Last 180 days</b>, <b>Last 365
                days</b> &mdash; it reloads as soon as you choose. A job shows if it was created or changed within the window. <b>All time</b>
                ignores the window (it then reads <b>All time &times;</b>; click again to turn it off).</li>
            <li><b>Search</b> &mdash; <em>&ldquo;Search customer / quote # / postcode&hellip;&rdquo;</em>, then <b>Apply</b>. <b>Mine only</b>
                narrows the board to jobs you created or have an appointment on (<b>Mine only &times;</b> to undo); it only appears if you can see
                everyone&rsquo;s jobs &mdash; otherwise the board already shows only those.</li>
            <li><b>Full columns</b> show the newest 50 cards with <b>Showing 50 of 63 &middot; refine the window or search</b> at the foot; the
                count and value at the top stay the true totals.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Cards don&rsquo;t drag.</b> Moving a job has real side-effects &mdash;
             dates stamped, a fitting booked, suppliers emailed, a promised date set &mdash; so it is done on the job&rsquo;s own page with
             <b>Mark as accepted</b>, <b>Mark as declined</b>, <b>Mark as ordered</b>, <b>Mark as fitted</b>, <b>Mark as invoiced</b>,
             <b>Reopen as draft</b> or <b>&#128230; Save as order</b>. There is no &ldquo;Mark as paid&rdquo;.</div></div>

          <p><b>Moves that happen by themselves</b> (which is why the board can change while you watch):</p>
          <ul class="steps">
            <li>The customer opens the quote link (or you email the quote) &mdash; a draft becomes sent and <b>Not sent</b> disappears.</li>
            <li>The customer accepts online &mdash; <b>Accepted</b>, with a fitting placed in the calendar&rsquo;s Pending Fitting tray. If every
                blind is your factory&rsquo;s own product it can go straight on to <b>Ordered</b>.</li>
            <li>The fitting is marked <b>Completed</b> on the calendar &mdash; <b>Fitted</b>.</li>
            <li>The invoice is emailed with <b>&#129534; Send invoice</b> &mdash; <b>Invoiced</b>.</li>
            <li>The deposit plus recorded payments cover the total &mdash; <b>Paid</b> (and back again if money is taken off).</li>
          </ul>

          <p><b>Always up to date.</b> While the tab is on screen the board checks every 20 seconds whether anything has changed, and reloads only
             if it has. It is the shape of the work; for finding and working one job &mdash; quotes and orders apart, retail and trade apart, the
             archive, ticking several jobs &mdash; use the <b>List</b>. If the page shows <em>&ldquo;The pipeline isn&rsquo;t available yet &mdash; the
             quotes table is missing on this database.&rdquo;</em>, tell support.</p>',
        'script'  => [
            ['1', 'What the board is',        'The Pipeline is under Work, in the menu on the left. It shows the same jobs as your Quotes and Orders lists, but all on one board, so you can see the shape of everything at once. Nothing here is extra to keep up to date. It is simply another way of looking at the jobs you already have.', 1],
            ['2', 'Seven columns',            'There are seven columns, in the order a job travels. Quote holds the drafts and the sent quotes together, because a draft is just a quote that has not gone out yet. Declined has a column of its own, so lost jobs never clutter the live ones. Then come Accepted, Ordered, Fitted, Invoiced and Paid.', 2],
            ['3', 'How many, and how much',   'Under each column name are two figures. The bold one is how many jobs are sitting there. The grey one is what they are worth. Top right, the whole board is added up for you: the number of jobs, and the money in the pipeline. At a glance, you can see where the work is piling up. Not allowed to see money? Then you see the counts only.', 3],
            ['4', 'Reading a card',           'Each job is a card. The customer\'s name is at the top, with the quote number and postcode underneath. Then the value of the job, and on the right, how long since anyone touched it. Hover over that to see the exact time. Click anywhere on the card, and the job opens.', 4],
            ['5', 'Newest touched first',     'In every column, the jobs touched most recently float to the top. Jobs nobody has looked at for a while sink to the bottom. So reading down a column is reading from busy to forgotten. The bottom of each column is where to look for jobs that need a nudge.', 5],
            ['6', 'Three little flags',       'Three little flags do a lot of work. Not sent, in amber, means that quote is still on your desk. The customer has never seen it. Paid, or Part paid, in the corner, shows on any card in any column, so money paid up front is never missed. And in the Invoiced column, a red figure shows what is still owed. That is your chasing list.', 6],
            ['7', 'The time window',          'The board starts on the last ninety days. The Window box changes that to anything from thirty days to a year, and it updates as soon as you pick. If an old job seems to have vanished, it has not. It is just older than the window. Press All time to see everything, and press it again to go back.', 7],
            ['8', 'Search, and Mine only',    'The search box finds a customer, a quote number or a postcode. Type, and press Apply. Mine only narrows the board to jobs you created, or have an appointment on. You only see that button if you can see everyone\'s jobs. If you can only see your own, the board is already just yours.', 8],
            ['9', 'Very full columns',        'A very full column shows the newest fifty cards, and says so at the bottom. Nothing is lost. The count and the value at the top are still the true totals. The trimming just keeps the page quick. To see the rest, narrow the window, or search for what you want.', 9],
            ['10', 'Cards do not drag',       'You cannot drag a card to another column, and that is on purpose. Moving a job does real things. It stamps dates, books a fitting, emails your suppliers, and sets a promised date. A slip of the mouse should never do all that. So open the job, and press the button on its own page, such as Mark as accepted.', 10],
            ['11', 'Cards that move by themselves', 'Some cards move by themselves. When the customer opens your link, the quote stops being a draft, and the Not sent flag disappears. Accepting online moves it to Accepted. Completing the fitting on the calendar moves it to Fitted. Sending the invoice moves it to Invoiced. And Paid happens only when the money really covers the total.', 11],
            ['12', 'Always up to date',       'The board keeps itself up to date. Every twenty seconds it quietly checks whether anything has moved, and only then does it refresh. It only checks while the page is on screen, so it costs nothing in the background. You can leave it open on a second screen. When you want to work on a job, the List button takes you back to the Orders list.', 12],
        ],
];
