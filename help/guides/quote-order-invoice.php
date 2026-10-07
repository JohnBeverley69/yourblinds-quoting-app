<?php
declare(strict_types=1);

/**
 * Guide: quote-order-invoice — "Orders, fulfilment & invoicing" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Follows one job from accepted to paid. Mirrors quote-builder/edit.php (the
 * sticky bar, the Quote actions panel, the read-only banner),
 * quote-builder/change_status.php (transitions, permissions, deposit seed,
 * pending fitting, auto-place in-house, flashes), quote-builder/
 * order_suppliers.php (Send order to suppliers), _partials/order_stage.php
 * ("With the factory: …" pill), calendar/view.php (completing a fitting) and
 * pdf-generator/send_invoice.php. Every label, button and message is copied
 * from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// Status pills in the default palette (_partials/job_status_colours.php).
$PILL = [
    'draft' => ['#7c3aed', '#fff'], 'sent' => ['#f59e0b', '#111827'], 'accepted' => ['#16a34a', '#fff'],
    'declined' => ['#dc2626', '#fff'], 'ordered' => ['#0891b2', '#fff'], 'fitted' => ['#0d9488', '#fff'],
    'invoiced' => ['#ea580c', '#fff'], 'paid' => ['#475569', '#fff'],
];
$pill = static fn (string $s, string $x = '', string $st = ''): string =>
    '<span class="spl ' . $x . '" style="background:' . $PILL[$s][0] . ';color:' . $PILL[$s][1] . ';' . $st . '">' . $s . '</span>';
/** A pill that swaps from one status to another at $t seconds. */
$swap = static fn (string $from, string $to, float $t): string =>
    '<span class="stk">' . $pill($from, 'a-out', '--d:' . $t . 's') . $pill($to, 'a-fade', '--d:' . $t . 's') . '</span>';

// "With the factory: …" pill (os_factory_progress_pill colours).
$FP = ['Confirmed' => ['#5b6b7f', '#e6ebf1'], 'In Production' => ['#b5730f', '#f7ecd6'],
       'Ready to dispatch' => ['#245ea3', '#dde8f6'], 'Dispatched' => ['#0d7a67', '#d6ece6']];
$fpill = static fn (string $lbl, string $x = '', string $st = ''): string =>
    '<span class="fpl ' . $x . '" style="color:' . $FP[$lbl][0] . ';background:' . $FP[$lbl][1] . ';' . $st . '">With the factory: ' . $lbl . '</span>';

/** The slim sticky bar at the top of a job. */
$bar = static fn (string $pillHtml, string $mid = ''): string =>
    '<div class="qsb"><span>Quote <b>ABC-2026-0042</b> ' . $pillHtml . '</span>' . $mid . '<span class="qtot">Total &pound;1,240.00</span></div>';

/** The Quote actions panel, with a list of [label, extraClass, style] buttons. */
$panel = static function (array $btns, string $x = '', string $st = ''): string {
    $h = '<div class="qa ' . $x . '" style="' . $st . '"><div class="qah">Quote actions</div><div class="qab">';
    foreach ($btns as $b) {
        [$lbl, $cls, $sty] = $b + ['', '', ''];
        $h .= '<span class="' . ($cls !== '' ? $cls : 'btns') . '" style="' . $sty . '">' . $lbl . '</span>';
    }
    return $h . '</div></div>';
};

/** A line on the place-order screen. */
$line = static fn (string $prod, string $sys, string $fab, string $size, string $room, string $anim = ''): string =>
    '<tr class="' . $anim . '"><td><b>' . $prod . '</b><br><span class="mt">' . $sys . '</span></td><td>' . $fab . '</td><td>' . $size . '</td><td>1</td><td>' . $room . '</td></tr>';
$thead = '<thead><tr><th>Product</th><th>Fabric / colour</th><th>Size</th><th>Qty</th><th>Room</th></tr></thead>';

return [
        'aud'     => 'admin',
        'section' => 'Orders',
        'title'   => 'Orders, fulfilment & invoicing',
        'eyebrow' => 'Orders',
        'v'       => 2,
        'blurb'   => 'One job followed all the way through: accepted, placed with your suppliers and your factory, made, fitted, invoiced — and Paid, which happens on its own.',
        'lede'    => 'A quote becomes an <b>order</b> the moment the customer says yes. This guide follows one job from there to the end:
                      <b>accepted</b>, <b>placed</b> with your suppliers and your factory, <b>made</b>, <b>fitted</b>, <b>invoiced</b> and finally
                      <b>paid</b> &mdash; which happens by itself. Every button you see is the real one, on the job&rsquo;s own page. It goes
                      <b>slowly</b>, one idea per chapter. To get there: <b>Orders</b> in the menu, then click the job&rsquo;s number.',
        'open'    => '/orders/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:370px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .row{ display:flex; gap:.45rem; flex-wrap:wrap; align-items:center; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:7px; padding:.27rem .6rem; font-size:.68rem; font-weight:600; white-space:nowrap; }
          .gd .stk{ display:inline-grid; justify-items:start; } .gd .stk > *{ grid-area:1/1; }
          .gd .spl{ display:inline-flex; border-radius:999px; padding:.06rem .5rem; font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
          .gd .fpl{ display:inline-flex; border-radius:999px; padding:.06rem .5rem; font-size:.6rem; font-weight:700; white-space:nowrap; }
          .gd .qsb{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; border:1px solid var(--line); border-radius:9px; padding:.4rem .6rem; background:var(--panel);
                    font-size:.74rem; color:var(--ink); margin-bottom:.55rem; }
          .gd .qsb .qtot{ margin-left:auto; font-weight:800; }
          .gd .qacc{ display:inline-flex; border-radius:999px; padding:.15rem .55rem; font-size:.64rem; font-weight:700; background:var(--good-wash); color:var(--good); border:1px solid var(--good); }
          .gd .qdec{ display:inline-flex; border-radius:999px; padding:.15rem .55rem; font-size:.64rem; font-weight:700; background:var(--err-wash); color:var(--err); border:1px solid var(--err); }
          .gd .qa{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); margin-bottom:.55rem; }
          .gd .qah{ font-size:.78rem; font-weight:800; color:var(--ink); margin-bottom:.4rem; }
          .gd .qab{ display:flex; gap:.35rem; flex-wrap:wrap; }
          .gd .okb{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.4rem .6rem; font-size:.66rem; color:var(--ink); font-weight:600; margin-bottom:.5rem; line-height:1.45; }
          .gd .errb{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.4rem .6rem; font-size:.66rem; color:var(--ink); margin-bottom:.45rem; line-height:1.45; }
          .gd .rob{ background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.5); border-radius:8px; padding:.4rem .6rem; font-size:.68rem; color:var(--ink); margin-bottom:.5rem; }
          .gd .cdlg{ position:absolute; z-index:5; left:10%; right:10%; top:6.5rem; max-width:23rem; border:1px solid var(--line); border-radius:12px; background:var(--surface);
                     box-shadow:var(--gd-shadow); padding:.6rem .75rem; font-size:.7rem; color:var(--ink); line-height:1.45; white-space:pre-line; }
          .gd .cdlg .act{ display:flex; gap:.35rem; justify-content:flex-end; margin-top:.45rem; white-space:normal; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }

          /* 1 — the journey */
          .gd .jr{ display:flex; align-items:stretch; gap:.35rem; flex-wrap:wrap; margin:1rem 0 .9rem; }
          .gd .st{ display:flex; flex-direction:column; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:10px; padding:.45rem .5rem; background:var(--surface); min-width:4.6rem; }
          .gd .st small{ font-size:.58rem; color:var(--soft); font-weight:700; text-align:center; line-height:1.25; }
          .gd .ar{ align-self:center; color:var(--faint); font-weight:800; }
          .gd .key{ display:flex; gap:.5rem; flex-wrap:wrap; }

          /* 2 — the orders list row */
          .gd .lst{ border:1px solid var(--line); border-radius:9px; overflow:hidden; margin-bottom:.6rem; font-size:.7rem; }
          .gd .lst > div{ display:grid; grid-template-columns:7rem 1fr 4.5rem 9.6rem; gap:.4rem; padding:.35rem .55rem; align-items:center; border-top:1px solid var(--line); color:var(--ink); }
          .gd .lst > div:first-child{ border-top:0; background:var(--panel); font-size:.6rem; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.04em; }
          .gd .qn{ font-weight:800; color:var(--ink); border-radius:4px; }

          /* 5–8 — the place-order screen */
          .gd .ph1{ font-size:.95rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.66rem; color:var(--accent); font-weight:700; margin:.1rem 0 .4rem; }
          .gd .intro{ font-size:.66rem; color:var(--soft); margin:0 0 .5rem; }
          .gd .grp{ border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.45rem .55rem; margin-bottom:.5rem; }
          .gd .grp.mfg{ border-color:rgba(22,163,74,.55); background:color-mix(in srgb, #16a34a 6%, var(--surface)); }
          .gd .gh{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; font-size:.72rem; }
          .gd .gh .nm{ font-weight:800; color:var(--ink); }
          .gd .gh .em{ color:var(--soft); font-size:.64rem; }
          .gd .gh .pick{ margin-left:auto; display:inline-flex; align-items:center; gap:.3rem; font-size:.66rem; font-weight:700; color:var(--ink); }
          .gd .tk{ width:15px; height:15px; border-radius:4px; border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); display:inline-grid; place-items:center; font-size:.6rem; color:transparent; }
          .gd .tk.on{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .sent{ font-size:.6rem; font-weight:700; color:#b45309; background:rgba(245,158,11,.14); border-radius:999px; padding:.05rem .45rem; }
          .gd .note{ font-size:.62rem; color:var(--soft); margin:.3rem 0 0; line-height:1.45; }
          .gd table.li{ width:100%; border-collapse:collapse; margin-top:.35rem; font-size:.6rem; color:var(--ink); }
          .gd table.li th{ text-align:left; font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); padding:.15rem .25rem; border-bottom:1px solid var(--line); }
          .gd table.li td{ padding:.2rem .25rem; border-bottom:1px solid var(--line); vertical-align:top; }
          .gd table.li .mt{ color:var(--faint); font-size:.56rem; }
          .gd .gd-play tr.a-fly{ animation:gdFly .7s ease-out calc(var(--d,0s) * var(--k,1)) forwards; }

          /* 10 — factory progress */
          .gd .prog{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin:.4rem 0 .6rem; }

          /* 11 — two ways to Fitted */
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); }
          .gd .card h4{ margin:0 0 .35rem; font-size:.76rem; color:var(--ink); }
          .gd .ib2{ display:inline-flex; align-items:center; min-height:1.55rem; min-width:7rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:0 .45rem; font-size:.7rem; color:var(--ink); }
          .gd .ib2::after{ content:"\25BE"; margin-left:auto; padding-left:.4rem; color:var(--faint); font-size:.6rem; }

          /* 12 — the email */
          .gd .mail{ border:1px solid var(--line); border-radius:10px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.5rem .65rem; font-size:.66rem; color:var(--ink); max-width:26rem; }
          .gd .mail .hd{ color:var(--soft); border-bottom:1px solid var(--line); padding-bottom:.3rem; margin-bottom:.35rem; line-height:1.5; }
          .gd .att{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:6px; padding:.12rem .45rem; font-size:.62rem; background:var(--panel); margin-top:.3rem; }
          .gd .tot{ display:grid; grid-template-columns:auto auto; gap:.15rem 1.2rem; justify-content:end; font-size:.7rem; color:var(--ink); border:1px solid var(--line); border-radius:9px; padding:.4rem .6rem; background:var(--surface); }
          .gd .tot b{ text-align:right; font-variant-numeric:tabular-nums; }

          /* 14 — the money bar */
          .gd .mbar{ display:flex; height:1.6rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; background:var(--panel); max-width:28rem; margin:.5rem 0; }
          .gd .mbar span{ display:grid; place-items:center; font-size:.6rem; font-weight:700; color:#fff; white-space:nowrap; overflow:hidden; }
          .gd .nob{ display:inline-flex; border:1px dashed var(--err); color:var(--err); border-radius:7px; padding:.27rem .6rem; font-size:.68rem; font-weight:700; text-decoration:line-through; }

          @media (max-width:640px){
            .gd .sc{ min-height:440px; }
            .gd .two{ grid-template-columns:1fr; }
            .gd .lst > div{ grid-template-columns:6rem 1fr 8.6rem; } .gd .lst > div > :nth-child(3){ display:none; }
            .gd table.li th:nth-child(2), .gd table.li td:nth-child(2){ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / orders / ABC-2026-0042</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a><a>Pipeline</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a class="on">Orders</a><a>Payments</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $bar($pill('ordered') . ' ' . $fpill('In Production')) . '
                  ' . $panel([['View PDF'], ['Download PDF'], ['Mark as fitted'], ['Mark as invoiced'], ['Reopen as draft'], ['&#128230; Send to suppliers'], ['&#129534; Send invoice']]) . '
                  <div class="jr">
                    <span class="st">' . $pill('accepted') . '</span><span class="ar">&rarr;</span><span class="st">' . $pill('ordered') . '</span><span class="ar">&rarr;</span>
                    <span class="st">' . $pill('fitted') . '</span><span class="ar">&rarr;</span><span class="st">' . $pill('invoiced') . '</span><span class="ar">&rarr;</span><span class="st">' . $pill('paid') . '</span>
                  </div>
                  <p class="scs">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — the journey -->
                <div class="sc" data-scene="1" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">One job, start to finish</div>
                  <div class="jr">
                    <span class="st a-pop" style="--d:3.6s">' . $pill('sent') . '<small>a quote</small></span><span class="ar a-fade" style="--d:4.4s">&rarr;</span>
                    <span class="st a-pop" style="--d:5.2s">' . $pill('accepted') . '<small>&#128070; customer says yes</small></span><span class="ar a-fade" style="--d:8s">&rarr;</span>
                    <span class="st a-pop" style="--d:8.4s">' . $pill('ordered') . '<small>&#128070; placed &middot; &#9881; made</small></span><span class="ar a-fade" style="--d:9.6s">&rarr;</span>
                    <span class="st a-pop" style="--d:10s">' . $pill('fitted') . '<small>&#128070; fitting done</small></span><span class="ar a-fade" style="--d:10.6s">&rarr;</span>
                    <span class="st a-pop" style="--d:11s">' . $pill('invoiced') . '<small>&#128070; invoice sent</small></span><span class="ar a-fade" style="--d:11.6s">&rarr;</span>
                    <span class="st a-pop" style="--d:12s">' . $pill('paid') . '<small>&#9881; money in</small></span>
                  </div>
                  <div class="key">
                    <span class="chip a-pop" style="--d:13.6s">&#128070; a button on the job&rsquo;s own page</span>
                    <span class="chip a-pop" style="--d:16.6s">&#9881; happens by itself</span>
                  </div>
                  <span class="chip ok a-pop" style="--d:20s;margin-top:.7rem">Know which is which &mdash; no head scratching</span>
                </div>

                <!-- 2 — the quote actions panel -->
                <div class="sc" data-scene="2" data-len="24">
                  <div class="lst a-rise" style="--d:.4s">
                    <div><span>Quote #</span><span>Customer</span><span>Postcode</span><span>Status</span></div>
                    <div><span class="qn a-ring" style="--d:2.2s">ABC-2026-0042</span><span>Emma Fletcher</span><span>CV32 5PJ</span><span>' . $pill('sent', '', 'font-size:.54rem') . '</span></div>
                  </div>
                  <div class="a-rise" style="--d:4s">
                    ' . $bar($swap('sent', 'ordered', 12.6)) . '
                    <div class="stk" style="display:grid;justify-items:stretch">
                      ' . $panel([['View PDF'], ['Download PDF'], ['&#128230; Save as order', 'btnp'], ['Mark as accepted'], ['Mark as declined'], ['Reopen as draft']], 'a-out', '--d:12.6s') . '
                      ' . $panel([['View PDF'], ['Download PDF'], ['Mark as fitted'], ['Mark as invoiced'], ['Reopen as draft'], ['&#128230; Send to suppliers'], ['&#129534; Send invoice']], 'a-fade', '--d:12.6s') . '
                    </div>
                  </div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:8.6s">Buttons follow the stage</span>
                    <span class="chip a-pop" style="--d:16.4s">Missing a button? Check the stage</span>
                  </div>
                  <div class="a-move" style="--fx:70%;--fy:16rem;--tx:3.5rem;--ty:2.5rem;--d:1s;--md:1.4s">' . $ptr . '</div>
                </div>

                <!-- 3 — accepted -->
                <div class="sc" data-scene="3" data-len="26">
                  <span class="chip a-pop" style="--d:4s;margin-bottom:.55rem">&#127760; or the customer presses Accept on their link</span>
                  ' . $bar($swap('sent', 'accepted', 9.8), '<span class="stk"><span class="a-out" style="--d:9.8s;display:inline-flex;gap:.3rem"><span class="qacc a-press" style="--d:9.2s">&#10003; Customer accepted</span><span class="qdec">&#10005; Customer declined</span></span></span>') . '
                  <div class="okb a-pop" style="--d:10.4s">Status: accepted. Installation appointment is in the calendar&rsquo;s &ldquo;Pending Fitting&rdquo; tray &mdash; drag it onto the right date and assign a fitter when ready.</div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:13.8s">Two things, quietly:</span>
                    <span class="chip a-pop" style="--d:15.8s">Deposit <b style="color:#92400e">&pound;620.00 due</b> &mdash; 50% unless you changed it</span>
                    <span class="chip a-pop" style="--d:22s">&#128197; Pending Fitting <b>1</b></span>
                  </div>
                  <div class="a-move" style="--fx:80%;--fy:16rem;--tx:13rem;--ty:3.6rem;--d:7.6s;--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 4 — save as order -->
                <div class="sc" data-scene="4" data-len="21">
                  ' . $panel([['View PDF'], ['Download PDF'], ['&#128230; Save as order', 'btnp a-ring', '--d:2.4s'], ['Mark as accepted'], ['Mark as declined'], ['Reopen as draft']]) . '
                  <div class="row" style="margin-bottom:.55rem"><span class="chip a-pop" style="--d:7s">accept + go to placing &mdash; in one go</span></div>
                  <div class="a-rise" style="--d:9s;border:1px solid var(--line);border-radius:10px;padding:.5rem .6rem">
                    <div class="ph1">Send order to suppliers</div><div class="psub">&larr; Back to order ABC-2026-0042</div>
                    <div class="okb a-pop" style="--d:13.4s">Order accepted &mdash; place it below (suppliers get emailed their lines).</div>
                  </div>
                  <div class="a-move" style="--fx:80%;--fy:15rem;--tx:11.5rem;--ty:2.6rem;--d:4.4s;--md:1.4s">' . $ptr . '</div>
                </div>

                <!-- 5 — the place order screen -->
                <div class="sc" data-scene="5" data-len="21">
                  <div class="ph1">Send order to suppliers</div><div class="psub">&larr; Back to order ABC-2026-0042</div>
                  <p class="intro a-fade" style="--d:.6s">Each supplier below gets an email with <b>only their lines</b> and a spec PDF. Tick the ones to send, then <b>Send selected orders</b>.</p>
                  <div class="grp a-rise" style="--d:3.4s">
                    <div class="gh"><span class="nm">Northfield Trade Supply</span><span class="em a-ring" style="--d:5.4s">orders@northfield.example</span>
                      <span class="pick a-ring" style="--d:17.8s"><span class="tk on">&check;</span> Send 2 lines</span></div>
                    <table class="li">' . $thead . '<tbody>
                      ' . $line('Venetian Blind', '25mm Aluminium', 'Satin / Silver', '900 &times; 1200 mm', 'Bathroom', 'a-fly" style="--d:9.8s') . '
                      ' . $line('Vertical Blind', '89mm', 'Carnival / Ivory', '1800 &times; 2100 mm', 'Lounge', 'a-fly" style="--d:10.8s') . '
                    </tbody></table>
                  </div>
                  <span class="chip a-pop" style="--d:15.4s">Read it through before you send</span>
                </div>

                <!-- 6 — your own products -->
                <div class="sc" data-scene="6" data-len="21">
                  <div class="grp mfg a-rise" style="--d:.6s">
                    <div class="gh"><span class="nm">&#127981; Beverley &mdash; manufacturing</span><span class="em">2 lines &middot; auto-routed</span></div>
                    <p class="note a-fade" style="--d:4s">These are your products from the <b>Beverley</b> catalogue &mdash; they go <b>straight to manufacturing</b> when you place the order. No supplier email needed.</p>
                    <p class="note a-fade" style="--d:13.8s">&#128666; <b>Delivery</b> &mdash; Van: &pound;10.00 + VAT on deliveries under &pound;100.00 (orders going out together on the same day are added up).</p>
                    <table class="li">' . $thead . '<tbody>
                      ' . $line('Roller Blind', 'Standard', 'Carnival / Blackout Ivory', '1200 &times; 1500 mm', 'Kitchen') . '
                      ' . $line('Roller Blind', 'Standard', 'Carnival / Blackout Ivory', '600 &times; 1500 mm', 'Kitchen') . '
                    </tbody></table>
                  </div>
                  <div class="row">
                    <span class="chip ok a-pop" style="--d:7s">No email &mdash; no tick box</span>
                    <span class="chip a-pop" style="--d:9.2s">&#127981; Straight to the factory on placing</span>
                  </div>
                </div>

                <!-- 7 — placing it -->
                <div class="sc" data-scene="7" data-len="21">
                  <div class="row" style="margin-bottom:.6rem">
                    <span class="stk"><span class="btnp a-out" style="--d:5.4s">&#128230; Send &amp; place order</span><span class="btnp a-mid" style="--d:5.4s;--d2:6.8s">&#128230; Send selected orders</span>
                      <span class="btnp a-mid" style="--d:6.8s;--d2:8.2s">&#128230; Place order</span><span class="btnp a-fade" style="--d:8.2s"><span class="a-press" style="--d:9s">&#128230; Send &amp; place order</span></span></span>
                    <span class="btns">Cancel</span>
                  </div>
                  <div class="row" style="margin-bottom:.6rem">
                    <span class="chip a-pop" style="--d:1.2s">suppliers + factory</span><span class="chip a-pop" style="--d:5.6s">suppliers only</span><span class="chip a-pop" style="--d:7s">factory only</span>
                  </div>
                  <div class="a-rise" style="--d:11s">
                    ' . $bar($swap('accepted', 'ordered', 12)) . '
                    <div class="okb a-pop" style="--d:15s">Order sent to Northfield Trade Supply. 2 blinds placed with Beverley manufacturing. Moved on to &ldquo;Ordered&rdquo;.</div>
                    <span class="chip a-pop" style="--d:13.2s">&#128197; due date set</span>
                  </div>
                </div>

                <!-- 8 — no double orders -->
                <div class="sc" data-scene="8" data-len="22">
                  <div class="grp a-rise" style="--d:.4s">
                    <div class="gh"><span class="nm">Northfield Trade Supply</span><span class="em">orders@northfield.example</span>
                      <span class="sent a-pop" style="--d:4.6s">&#9888;&#65039; Already sent 7 Oct 2026, 10:42</span>
                      <span class="pick"><span class="tk stk"><span>&nbsp;</span><span class="tk on a-mid" style="--d:13.6s;--d2:21s;border:0">&check;</span></span> Re-send 2 lines</span></div>
                    <p class="note a-fade" style="--d:2.4s">Already ordered from <b>Northfield Trade Supply</b> for this quote &mdash; left unticked so you don&rsquo;t double-order. Tick it only if you really mean to re-send.</p>
                  </div>
                  <span class="chip ok a-pop" style="--d:9.8s">Left unticked &mdash; no double order</span>
                  <div class="cdlg a-mid" style="--d:15.4s;--d2:21s">This order was already sent to:

  Northfield Trade Supply

Send again? This will place a second order.<div class="act"><span class="btns">Cancel</span><span class="btnp">OK</span></div></div>
                </div>

                <!-- 9 — all made in-house -->
                <div class="sc" data-scene="9" data-len="24">
                  ' . $bar($swap('sent', 'ordered', 8.4), '<span class="stk"><span class="a-out" style="--d:8.4s;display:inline-flex;gap:.3rem"><span class="qacc a-press" style="--d:7.8s">&#10003; Customer accepted</span><span class="qdec">&#10005; Customer declined</span></span></span>') . '
                  <div class="row" style="margin-bottom:.5rem">
                    <span class="chip a-pop" style="--d:1.6s">Every line: your factory&rsquo;s product</span>
                    <span class="chip bad a-pop" style="--d:3.6s"><s>Send order to suppliers</s> &mdash; not needed</span>
                  </div>
                  <div class="okb a-pop" style="--d:9.2s">Status: ordered. Due 23 Oct 2026. Installation appointment is in the calendar&rsquo;s &ldquo;Pending Fitting&rdquo; tray &mdash; drag it onto the right date and assign a fitter when ready. <span class="a-ring" style="--d:12.4s;border-radius:4px">Sent straight to the workshop &mdash; all in-house, no supplier order needed.</span></div>
                  <span class="chip a-pop" style="--d:19.2s">Settings &rarr; Quoting &rarr; <b>Auto-place in-house orders</b></span>
                  <div class="a-move" style="--fx:80%;--fy:16rem;--tx:12.4rem;--ty:1.4rem;--d:6.2s;--md:1.4s">' . $ptr . '</div>
                </div>

                <!-- 10 — where is it now -->
                <div class="sc" data-scene="10" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Where is it now?</div>
                  ' . $bar($pill('ordered') . ' <span class="stk">' . $fpill('Confirmed', 'a-mid', '--d:1.4s;--d2:12.8s') . $fpill('In Production', 'a-mid', '--d:12.8s;--d2:14.4s') . $fpill('Ready to dispatch', 'a-mid', '--d:14.4s;--d2:16s') . $fpill('In Production', 'a-fade', '--d:16s') . '</span>') . '
                  <div class="lst a-rise" style="--d:5.6s">
                    <div><span>Quote #</span><span>Customer</span><span>Postcode</span><span>Status</span></div>
                    <div><span class="qn">ABC-2026-0042</span><span>Emma Fletcher</span><span>CV32 5PJ</span><span>' . $pill('ordered', '', 'font-size:.54rem') . '<br>' . $fpill('In Production', '', 'font-size:.52rem;margin-top:.2rem') . '</span></div>
                  </div>
                  <div class="prog">
                    ' . $fpill('Confirmed', 'a-pop', '--d:10.4s') . '<span class="a-fade" style="--d:12.6s">&rarr;</span>
                    ' . $fpill('In Production', 'a-pop', '--d:12.8s') . '<span class="a-fade" style="--d:14.2s">&rarr;</span>
                    ' . $fpill('Ready to dispatch', 'a-pop', '--d:14.4s') . '<span class="a-fade" style="--d:15.8s">&rarr;</span>
                    ' . $fpill('Dispatched', 'a-pop', '--d:16s') . '
                  </div>
                  <span class="chip a-pop" style="--d:18s">&#9881; You never set it &mdash; it moves as the factory works</span>
                </div>

                <!-- 11 — fitted -->
                <div class="sc" data-scene="11" data-len="18">
                  <div class="two">
                    <div class="card a-rise" style="--d:3.4s">
                      <h4>&#128197; Calendar &rarr; the fitting</h4>
                      <div class="row" style="font-size:.66rem">Update status: <span class="ib2"><span class="stk"><span class="a-out" style="--d:6.6s">Booked</span><span class="a-fade" style="--d:6.6s">Completed</span></span></span><span class="btns a-press" style="--d:7.6s">Save status</span></div>
                      <div class="okb a-pop" style="--d:8.6s;margin:.45rem 0 0">Status updated to Completed. Linked quote ABC-2026-0042 advanced to &ldquo;fitted&rdquo;.</div>
                    </div>
                    <div class="card a-rise" style="--d:11.8s">
                      <h4>&#128221; Or on the job</h4>
                      <div class="qab"><span class="btns a-ring" style="--d:12.6s">Mark as fitted</span><span class="btns">Mark as invoiced</span><span class="btns">Reopen as draft</span></div>
                    </div>
                  </div>
                  <div style="margin-top:.7rem">' . $bar($swap('ordered', 'fitted', 9.4)) . '</div>
                  <span class="chip ok a-pop" style="--d:15.4s">Ready to invoice</span>
                </div>

                <!-- 12 — send the invoice -->
                <div class="sc" data-scene="12" data-len="22">
                  ' . $bar($swap('fitted', 'invoiced', 13.8)) . '
                  <div class="stk" style="display:grid;justify-items:stretch">
                  ' . $panel([['View PDF'], ['Download PDF'], ['Mark as invoiced'], ['Mark as ordered'], ['Reopen as draft'], ['&#128230; Send to suppliers'], ['&#129534; Send invoice', 'btns a-ring', '--d:1.2s']], 'a-out', '--d:13.8s') . '
                  ' . $panel([['View PDF'], ['Download PDF'], ['Reopen as draft'], ['&#128230; Send to suppliers'], ['&#129534; Resend invoice']], 'a-fade', '--d:13.8s') . '
                  </div>
                  <div class="cdlg a-mid" style="--d:4.4s;--d2:6.8s;top:3rem">Email this invoice to the customer now? This also marks the job as Invoiced.<div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:6s">OK</span></div></div>
                  <div class="two">
                    <div class="mail a-drop" style="--d:7.2s">
                      <div class="hd">To: emma@example.co.uk<br>Subject: <b style="color:var(--ink)">Invoice ABC-2026-0042 from Hart Interiors</b></div>
                      Hello Emma Fletcher,<br>Please find your invoice (ABC-2026-0042) attached as a PDF.<br><br><span class="a-ring" style="--d:11.4s;border-radius:4px">Balance due: &pound;620.00.</span> Payment details are on the invoice.
                      <div><span class="att">&#128206; Invoice_ABC-2026-0042.pdf</span></div>
                    </div>
                    <div>
                      <div class="okb a-pop" style="--d:13.8s">Invoice emailed to emma@example.co.uk. Marked as Invoiced.</div>
                      <div class="tot a-rise" style="--d:16.8s"><span>Total</span><b>&pound;1,240.00</b><span>Paid</span><b>&pound;620.00</b><span>Balance due</span><b>&pound;620.00</b></div>
                    </div>
                  </div>
                </div>

                <!-- 13 — when it stops you -->
                <div class="sc" data-scene="13" data-len="23">
                  <div class="errb a-fly" style="--d:4.4s">No valid customer email on this order &mdash; add one on the customer, then try again.</div>
                  <div class="errb a-fly" style="--d:8.6s">You can invoice once the job is ordered &mdash; move it to Ordered first.</div>
                  <div class="row" style="margin:.6rem 0">
                    <span class="stk"><span class="btns a-out" style="--d:14.4s">&#129534; Send invoice</span><span class="btns a-fade" style="--d:14.4s"><span class="a-ring" style="--d:15s;border-radius:6px">&#129534; Resend invoice</span></span></span>
                    <span class="chip a-pop" style="--d:14.8s">once it has gone</span>
                  </div>
                  <div class="rob a-fade" style="--d:17.4s">This invoice has already been sent. Send it to the customer AGAIN?</div>
                  <span class="chip ok a-pop" style="--d:20.4s">No accidental second invoice</span>
                </div>

                <!-- 14 — paid on its own -->
                <div class="sc" data-scene="14" data-len="20">
                  <div class="row"><span class="nob a-pop" style="--d:.6s">Mark as paid</span><span class="chip a-pop" style="--d:1.4s">there isn&rsquo;t one</span></div>
                  <div class="mbar a-fade" style="--d:2.6s">
                    <span class="a-wide" style="--d:3.4s;width:50%;background:#16a34a">Deposit &pound;620.00 &check;</span>
                    <span class="a-wide" style="--d:5.6s;width:50%;background:#0891b2">Payment &pound;620.00</span>
                  </div>
                  <div class="row" style="font-size:.7rem;color:var(--soft)">Covers the total &pound;1,240.00 &rarr; ' . $swap('invoiced', 'paid', 7.6) . '</div>
                  <div class="row" style="margin-top:.7rem">
                    <span class="chip a-pop" style="--d:9.8s">Money taken back off &rarr; it steps back again</span>
                    <span class="chip ok a-pop" style="--d:13s">Record the payment &mdash; the status follows</span>
                    <span class="chip a-pop" style="--d:16.8s">Paid = the money really is in</span>
                  </div>
                </div>

                <!-- 15 — changing an order -->
                <div class="sc" data-scene="15" data-len="24">
                  <div class="rob a-mid" style="--d:.8s;--d2:7.6s">This quote is in <b>ordered</b> state and is read-only. Use <b>Reopen as draft</b> above to edit it.</div>
                  ' . $bar($swap('ordered', 'draft', 7.6)) . '
                  <div class="stk" style="display:grid;justify-items:stretch">
                  ' . $panel([['View PDF'], ['Download PDF'], ['Mark as fitted'], ['Mark as invoiced'], ['Reopen as draft', 'btns a-ring', '--d:5.2s'], ['&#128230; Send to suppliers'], ['&#129534; Send invoice']], 'a-out', '--d:7.6s') . '
                  ' . $panel([['View PDF'], ['Download PDF'], ['&#128230; Save as order', 'btnp'], ['Mark as sent'], ['Mark as accepted'], ['Mark as declined']], 'a-fade', '--d:7.6s') . '
                  </div>
                  <div class="row"><span class="chip a-pop" style="--d:9.2s">Edit it, then accept it again</span><span class="chip a-pop" style="--d:11.2s">Unpaid deposit cleared &mdash; worked out again on the new total</span></div>
                  <div class="a-rise" style="--d:16.6s;margin-top:.6rem;font-size:.66rem;color:var(--soft)">If the factory has already started on it:</div>
                  <div class="errb a-fly" style="--d:17.4s;margin-top:.3rem">This order is already being made &mdash; contact the factory to change it.</div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> <b>Orders</b> (under <b>Retail</b>, or <b>Trade</b> where you have it) &rarr; click the job&rsquo;s number.
             At the top of the job: a slim bar &mdash; <b>Quote ABC-2026-0042</b>, its status, and the <b>Total</b> &mdash; then the
             <b>Quote actions</b> panel: <b>View PDF</b>, <b>Download PDF</b>, and only the moves that make sense from where the job is now.
             (Finding the job in the first place has its own guide, <a href="/help/guide.php?g=orders-list"><b>Finding a job: the Orders
             list</b></a>.)</p>

          <ul class="steps">
            <li><b>Accepted &mdash; the customer said yes.</b> They accept from the link you sent, or you press <b>&#10003; Customer accepted</b>
                in the bar (or <b>Mark as accepted</b>). <code>Status: accepted. Installation appointment is in the calendar&rsquo;s &ldquo;Pending
                Fitting&rdquo; tray &mdash; drag it onto the right date and assign a fitter when ready.</code> Two things happen quietly: the
                <b>deposit</b> is worked out from your default (a percentage &mdash; 50% unless you changed it &mdash; or a flat amount capped at the
                total), and a <b>fitting</b> lands in the calendar&rsquo;s Pending Fitting tray (retail jobs only). <b>&#10005; Customer declined</b>
                asks <em>&ldquo;Mark this quote as declined?&rdquo;</em> and takes a pending fitting back off the calendar.</li>
            <li><b>&#128230; Save as order</b> &mdash; on a draft or sent quote with lines, for people who may place orders. It accepts the job
                and takes you straight to the place-order screen: <code>Order accepted &mdash; place it below (suppliers get emailed their
                lines).</code></li>
            <li><b>Send order to suppliers</b> (also <b>&#128230; Send to suppliers</b> on any order). <em>&ldquo;Each supplier below gets an email
                with only their lines and a spec PDF. Tick the ones to send, then Send selected orders.&rdquo;</em> One box per supplier with its
                order email and a ticked <b>Send N lines</b>, listing <b>Product</b>, <b>Fabric / colour</b>, <b>Size</b>, <b>Qty</b>, <b>Room</b>.
                Your factory&rsquo;s products sit in a green <b>&#127981; &lt;factory&gt; &mdash; manufacturing</b> box (<em>&ldquo;&hellip; they go
                straight to manufacturing when you place the order. No supplier email needed.&rdquo;</em>), with the account&rsquo;s
                <b>&#128666; Delivery</b> rule where one applies.</li>
            <li><b>Place it.</b> The button reads <b>&#128230; Send &amp; place order</b> (suppliers and factory), <b>&#128230; Send selected
                orders</b> (suppliers only) or <b>&#128230; Place order</b> (factory only). It emails the ticked suppliers, moves the job to
                <b>Ordered</b>, stamps its due date and tells the factory: <code>Order sent to Northfield Trade Supply. 2 blinds placed with
                Beverley manufacturing. Moved on to &ldquo;Ordered&rdquo;.</code></li>
            <li><b>No double orders.</b> A supplier already sent for this job comes back <b>unticked</b>, marked <b>&#9888;&#65039; Already sent
                &lt;date&gt;</b> and <b>Re-send N lines</b>. Ticking it and sending asks <em>&ldquo;This order was already sent to: &hellip; Send again?
                This will place a second order.&rdquo;</em></li>
            <li><b>All in-house?</b> If every blind is your factory&rsquo;s own product, accepting places it at once &mdash; no place-order
                screen: <code>Status: ordered. Due &lt;date&gt;. &hellip; Sent straight to the workshop &mdash; all in-house, no supplier order
                needed.</code> (If the factory buys some items in itself: <em>&ldquo;Sent straight to the factory &mdash; no supplier order
                needed from you (the factory orders any bought-in items itself).&rdquo;</em>) It&rsquo;s on to start with; switch it off under
                <b>Settings &rarr; Quoting &rarr; Auto-place in-house orders</b>. Someone without permission to place orders gets <em>&ldquo;Not
                sent to the workshop yet &mdash; someone who can place orders needs to place it.&rdquo;</em> instead.</li>
            <li><b>Where is it now?</b> An order placed with your factory carries a pill in the job&rsquo;s bar and on the Orders list:
                <b>With the factory: Confirmed</b> &rarr; <b>In Production</b> &rarr; <b>Ready to dispatch</b> &rarr; <b>Dispatched</b>. It is
                worked out for you as the factory makes and receives the blinds; you never set it.</li>
            <li><b>Fitted.</b> Complete the fitting on the calendar (<b>Update status:</b> <b>Completed</b> &rarr; <b>Save status</b> &rarr;
                <em>&ldquo;Status updated to Completed. Linked quote ABC-2026-0042 advanced to &ldquo;fitted&rdquo;.&rdquo;</em>), or press
                <b>Mark as fitted</b> on the job.</li>
            <li><b>&#129534; Send invoice</b> &mdash; from Ordered onward. <em>&ldquo;Email this invoice to the customer now? This also marks the
                job as Invoiced.&rdquo;</em> The customer gets <b>Invoice_&lt;number&gt;.pdf</b>, subject <b>&ldquo;Invoice &lt;number&gt; from
                &lt;your company&gt;&rdquo;</b>, with the balance due in the message (or <em>&ldquo;This invoice is fully paid &mdash; thank
                you.&rdquo;</em>) and a link to view it online; then <code>Invoice emailed to emma@example.co.uk. Marked as Invoiced.</code> The
                invoice is the quote document headed <b>Invoice</b>; once money has been taken it shows <b>Paid</b> and <b>Balance due</b> under
                the total, and the <b>How to pay &mdash; bank transfer</b> block prints if you&rsquo;ve filled in <b>Settings &rarr; Quoting &rarr;
                Bank details for customer payments</b>. After that the button reads <b>&#129534; Resend invoice</b> and asks <em>&ldquo;This invoice
                has already been sent. Send it to the customer AGAIN?&rdquo;</em></li>
          </ul>

          <div class="oops"><b>When it stops you.</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>No valid customer email on this order &mdash; add one on the customer, then try again.</code></li>
               <li><code>You can invoice once the job is ordered &mdash; move it to Ordered first.</code></li>
               <li><code>Invoice ABC-2026-0042 has already been sent. Use &ldquo;Resend invoice&rdquo; if you really need to send it again.</code></li>
               <li><code>You don&rsquo;t have permission to invoice orders.</code> / <code>You don&rsquo;t have permission to mark this quote as &ldquo;ordered&rdquo;.</code></li>
               <li><code>Can&rsquo;t move from accepted to invoiced.</code> &mdash; only the moves on the panel are allowed.</li>
               <li>On the place-order screen: <code>No delivery address set &mdash; suppliers won&rsquo;t know where to ship. Add one under Settings
                   &rsaquo; Suppliers first.</code>, and a box saying a supplier has no (valid) order email &mdash; fix it under <b>Settings &rsaquo;
                   Suppliers</b>.</li>
             </ul></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Paid looks after itself, and an order is read-only.</b> There is no
             &ldquo;Mark as paid&rdquo;: a job becomes <b>Paid</b> the moment the deposit plus recorded payments cover the total, and steps back
             if money is taken off again &mdash; so just record the payment. Once a job is an order it is locked: <em>&ldquo;This quote is in
             ordered state and is read-only. Use Reopen as draft above to edit it.&rdquo;</em> Reopening clears an <b>unpaid</b> deposit so it
             re-works from the new total (a paid deposit is kept). Once the factory has the blinds in hand, reopening is refused:
             <em>&ldquo;This order is already being made &mdash; contact the factory to change it.&rdquo;</em></div></div>

          <p><b>Which moves are offered.</b> Draft &rarr; sent, accepted or declined. Sent &rarr; accepted, declined or back to draft. Accepted
             &rarr; ordered, fitted or back to draft. Declined &rarr; back to draft. Ordered &rarr; fitted, invoiced or back to draft. Fitted
             &rarr; invoiced, back to ordered or back to draft. Invoiced &rarr; back to draft. Paid &rarr; none. Sales moves (sent, accepted,
             declined) need <b>Create quotes</b>; order moves (ordered, fitted, invoiced, and the invoice and supplier buttons) need <b>Create
             orders</b>; either is enough for <b>Reopen as draft</b>. Admins have them all.</p>',
        'script'  => [
            ['1', 'One job, start to finish',   'This guide follows one job all the way through. A quote becomes an order the moment the customer says yes. Then it is placed, made, fitted, invoiced, and finally paid. Some of those steps are a button that you press, on the job\'s own page. Others happen all by themselves. Knowing which is which saves a lot of head scratching.', 1],
            ['2', 'The Quote actions panel',    'Open the job by clicking its number on the Orders list. At the top of the page is the Quote actions panel. Its buttons change with the stage the job has reached, so you only ever see the moves that make sense right now. If a button you expect is missing, look at the stage first. It is usually telling you something.', 2],
            ['3', 'Accepted: the customer said yes', 'When the customer accepts, the job becomes an order. They can accept online, from the link you sent them. Or, if they say yes in person, press Customer accepted at the top of the page. Two things happen quietly. The deposit is worked out from your settings, half the total unless you changed it. And a fitting appears in the calendar\'s Pending Fitting tray.', 3],
            ['4', 'Save as order',              'If you are ready to place the order there and then, use the blue Save as order button instead. It accepts the job and carries you straight on to the place order screen, in one go. The green message tells you what to do next: place it below, and your suppliers get emailed their lines.', 4],
            ['5', 'The place order screen',     'This screen splits the order up by who makes it. Each supplier gets its own box, with its order email, and a list of just their lines: the product, the fabric and colour, the size, the quantity and the room. Read it through before you send. Each supplier\'s box starts ticked, ready to go.', 5],
            ['6', 'Your own products',          'Products from your factory\'s catalogue sit in a green box of their own, marked manufacturing. They need no email at all. They go straight to the factory the moment you place the order. If your account has a delivery charge, it is shown here too, so it is never a surprise on the invoice.', 6],
            ['7', 'Placing it',                 'The button at the bottom changes its words to match: Send and place order, Send selected orders, or just Place order. Press it, and the emails go out, the job moves on to Ordered, and a due date is set. You are taken back to the job, with a message saying exactly who it was sent to.', 7],
            ['8', 'No double orders',           'Come back to this screen later, and any supplier you have already sent to is left unticked, with a warning showing when it went. That stops you ordering the same blinds twice. If you really do mean to send it again, tick it, and the app asks you once more, because this places a second order.', 8],
            ['9', 'All made in-house',          'If every blind on the job comes from your factory, you never see the place order screen at all. Accepting the job places it for you, straight away. The message says so: sent straight to the workshop, all in-house, no supplier order needed. This is switched on to start with, under Auto-place in-house orders, in Settings.', 9],
            ['10', 'Where is it now?',          'Once your factory has the order, a small pill tells you how it is getting on. You see it on the job, and on the Orders list. With the factory: Confirmed means it has landed. Then In Production, then Ready to dispatch, and finally Dispatched. You never set this. It moves on its own as the factory works.', 10],
            ['11', 'Fitted',                    'When the blinds are up, the job moves to Fitted. The easiest way is on the calendar: open the fitting, and mark it Completed. That moves the order to Fitted for you. Or, on the job itself, press Mark as fitted. Either way, it is ready to invoice.', 11],
            ['12', 'Send the invoice',          'The Send invoice button appears once the job is ordered. Press it, and it asks you to confirm. The invoice goes to the customer by email, as a PDF, with the balance due written in the message, and the job moves to Invoiced. If money has already come in, the invoice shows what is paid, and what is left.', 12],
            ['13', 'When it stops you',         'Sometimes the invoice will not send, and it tells you why. No valid customer email means you need to add one first. You can invoice once the job is ordered, means it has not been placed yet. And once an invoice has gone, the button changes to Resend invoice, so a stray click can never send a second one by mistake.', 13],
            ['14', 'Paid, all on its own',      'There is no Mark as paid button. A job becomes Paid by itself, the moment the deposit and the payments you record cover the total. Take money back off, and it steps back again. So just record the payments, and the status follows. Paid always means the money really is in.', 14],
            ['15', 'Changing an order',         'Once a job is an order, it is locked, so nothing changes by accident. To change it, press Reopen as draft, make your changes, then accept it again. An unpaid deposit is cleared, so it works itself out again from the new total. And once the factory has started making it, reopening is refused. Contact the factory instead.', 15],
        ],
];
