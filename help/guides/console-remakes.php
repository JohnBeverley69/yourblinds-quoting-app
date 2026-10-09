<?php
declare(strict_types=1);

/**
 * Guide: console-remakes — "Remakes" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Audience 'factory': Factory Console users and the super-admin only.
 *
 * Mirrors:
 *   - _partials/remakes.php — the rules: a remake starts on the original order,
 *     the office's own is approved at once, an account's waits ("requested"),
 *     who-pays modes (rm_charge_modes), the remake order <original>-R1, -R2 …,
 *     cost = trade price of the blinds, the REMAKE badge, waiting count;
 *   - _partials/remake_form.php — "Which blinds need remaking?", How many,
 *     Reason, What's wrong?, Photo (optional), Who pays?, the charge / supplier
 *     boxes, Due date (optional);
 *   - factory/remake-new.php, factory/orders.php (↻ Remake), factory/edit-order.php
 *     (↻ Raise a remake);
 *   - remakes/request.php + quote-builder/edit.php ("Something wrong with a
 *     blind?" card, Report a problem, the outcome lines the account sees);
 *   - factory/remakes.php — tabs, approve / decline, flashes, In progress / Done
 *     table, Report (KPIs, By reason, By account, By production area, Supplier claims);
 *   - factory/remake-reasons.php — Edit reasons;
 *   - factory/incoming-orders.php + _partials/factory_order_colours.php — badge
 *     and the purple "Remake" row colour;
 *   - _partials/factory_ar.php — a remake order bills its charge ("REMAKE — "
 *     lines; £0 when free).
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line; start times come from where a phrase falls in
 * the voice-over ($at, ~13.6 characters a second), data-len from its length.
 */

$S = [
    ['1', 'What a remake is', 'Sometimes a blind has to be made again. A fabric fault, a measuring mistake, or damage on the way. A remake always starts on the original order. You say which blinds, and why. Once it is approved, it becomes a brand new order, numbered after the original, and it goes through the factory like any other order.', 1],
    ['2', 'Two ways in', 'A remake can start in two ways. The office can raise one itself, and because the office decides there and then, it is approved at once. Or the trade account can report a fault from their own order. Theirs waits for approval, and nothing is made until someone in the office says yes.', 2],
    ['3', 'Raising one yourself', 'To raise one yourself, find the order on the Orders list, and click Remake beside its stage. Or, from the order itself, click Raise a remake at the top. The Raise a remake page shows the order number and the account. Tick the blinds that need remaking, and say how many of each.', 3],
    ['4', 'Reason, note and photo', 'Next, choose a Reason from the list. Measuring error, Fitting damage, Factory fault and so on. Under What\'s wrong, say what the problem is, in a few words. You can add a photo of the fault too. It is optional, but it helps. A JPG, PNG or WebP picture, up to twelve megabytes.', 4],
    ['5', 'Who pays?', 'Then decide who pays. There are three choices. Free, our fault. Charge the account, where you type what to charge, before VAT. The full trade price of the blinds is shown beside it, as a guide. Or Free, supplier claim, where you type which supplier the claim is against. It is free to the account, and logged as a claim.', 5],
    ['6', 'Due date, and raise it', 'You can give it a due date, if you like, and it then shows on the office calendar. Click Raise remake. Because the office raised it, it is approved straight away. The message tells you its number, and that it is in Incoming orders as a new order.', 6],
    ['7', 'An account reports a fault', 'Accounts can report a fault themselves. On their own placed order, they see a box saying, Something wrong with a blind? They click Report a problem, tick the blinds, choose a reason, say what is wrong, and add a photo if they like. Then they click Send to the factory.', 7],
    ['8', 'Waiting for approval', 'Their request lands on the Remakes page, under Waiting for approval. The menu says how many are waiting, and so does the Dashboard. Each card shows the account and the order, when it was reported, the blinds, the reason, their note and photo, and the trade price of those blinds.', 8],
    ['9', 'Approving', 'To approve it, choose who pays, exactly as before. Free, charge the account, or a supplier claim. Add a due date if you want one, and click Approve remake. The remake order is made there and then, and goes into Incoming orders as a new order. The account sees the outcome on their own order.', 9],
    ['10', 'Declining', 'If it should not be remade, decline it instead. Type a short reason in the box. The account sees this, so keep it clear and polite. For example, measured by you, size made as ordered. Then click Decline. Their order shows that it was declined, with your reason.', 10],
    ['11', 'The remake order', 'A remake order is numbered after the original, with R one on the end, then R two, and so on. It is placed for the same account, with the same blinds. It goes through Incoming orders, the floor and dispatch like any other order. A purple REMAKE badge, and a purple row, make sure it stands out.', 11],
    ['12', 'Invoicing a remake', 'When it is invoiced, a remake bills exactly what you decided. Every line on it starts with the word REMAKE. A charged remake bills the amount you typed, spread across its blinds. A free remake still goes on the invoice, at nought pounds, so the account can see it was remade at no charge.', 12],
    ['13', 'In progress and Done', 'The In progress tab lists approved remakes that have not gone out yet. You see the account, the blinds, the reason, who pays, the cost, and the stage. Done lists the ones that have been dispatched, and the ones that were declined, with the reason that was given.', 13],
    ['14', 'The Report', 'The Report tab looks at one month at a time. Choose the month. It shows how many remakes, and how many blinds. Trade value remade, what was charged back, supplier claims, and the cost to us, after charges and claims. Below that, it breaks them down by reason, by account, by production area, and by supplier. A blind made across two benches counts under each.', 14],
    ['15', 'Editing the reasons', 'The reasons are your own list. Click Edit reasons, at the top of the Remakes page. You can rename one, change the order, or add a new one. Untick In use to retire a reason. Remakes that already used it keep it. Then click Save reasons.', 15],
];
$len = static fn (int $n): string => (string) round(mb_strlen($S[$n - 1][2]) / 13.6, 1);
$at = static function (int $n, string $phrase, float $add = 0.0) use ($S): string {
    $p = mb_strpos($S[$n - 1][2], $phrase);
    if ($p === false) { error_log('console-remakes guide: "' . $phrase . '" not in line ' . $n); $p = 0; }
    return (string) round($p / 13.6 + $add, 1);
};
$d = static fn (int $n, string $phrase, float $add = 0.0): string => '--d:' . $at($n, $phrase, $add) . 's';

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$rmb = '<span class="rmb">REMAKE</span>';
$ph  = static fn (string $t): string => '<span class="gph">' . $t . '</span>';

// The blinds on the original order (rm_line_label format).
$L1 = 'Lounge: Roller Blind &mdash; Bev Roller, Sunset / Ivory &middot; 1500 &times; 1600';
$L2 = 'Bedroom: Vertical Blind &mdash; Slimline, Linen / White &middot; 1200 &times; 2000';

/** One "which blinds" line: $on = ticked, $qty shown. */
$bline = static fn (string $label, int $lineNo, int $onOrder, string $tick = '', string $qty = '0', string $tickCls = '', string $tickStyle = ''): string =>
    '<div class="bln"><span class="tk ' . $tickCls . '" style="' . $tickStyle . '">' . $tick . '</span><span class="lb">' . $label
    . '<small>Line ' . $lineNo . ' &middot; ' . $onOrder . ' on the order</small></span><span class="hm">How many <span class="ib q">' . $qty . '</span></span></div>';

/** The Who pays? block. $sel = mode key selected; extra html under it. */
$radio = static fn (string $label, string $cls = '', string $style = ''): string =>
    '<div class="rd"><i class="' . $cls . '" style="' . $style . '"></i>' . $label . '</div>';

$tabs = static function (string $active, string $waiting = '1') : string {
    $h = '<div class="rtabs">';
    foreach (['waiting' => 'Waiting for approval' . ($waiting !== '' ? ' <b>' . $waiting . '</b>' : ''), 'open' => 'In progress', 'done' => 'Done', 'report' => 'Report'] as $k => $l) {
        $h .= '<span class="' . ($k === $active ? 'on' : '') . '">' . $l . '</span>';
    }
    return $h . '</div>';
};
$rmHead = '<div class="dh"><div><div class="pt">Remakes</div></div><span class="btns">Edit reasons</span></div>';

return [
        'aud'     => 'factory',
        'section' => 'Factory Console',
        'title'   => 'Remakes',
        'eyebrow' => 'Factory Console',
        'v'       => 2,
        'blurb'   => 'Raise a remake on an order, or approve or decline one an account reported; decide who pays; follow the -R1 remake order through the factory and onto the invoice; and read the monthly Report.',
        'lede'    => 'A <b>remake</b> is a blind made again &mdash; a fabric fault, a measuring mistake, damage on the way. It always starts
                      on the <b>original order</b>, and once approved it becomes a <b>new order</b> numbered after it
                      (<b>&hellip;-R1</b>) that goes through Incoming orders, the floor and dispatch like any other. The office can raise one
                      itself, or the trade account can report a fault from their own order for the office to approve. This guide goes
                      through it all <b>slowly</b>, one idea per chapter. To get there: <b>Work &rarr; Remakes</b>.',
        'open'    => '/factory/remakes.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:420px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.66rem; color:var(--soft); margin:.1rem 0 .5rem; }
          .gd .dh{ display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; margin-bottom:.5rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.32rem .75rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chip.pu{ border-color:#7c3aed; color:#7c3aed; }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .lnk{ color:var(--accent); font-weight:700; border-radius:4px; }
          .gd .arrow{ color:var(--faint); font-weight:800; font-size:1.1rem; align-self:center; text-align:center; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .flow{ display:grid; grid-template-columns:1fr 1.3rem 1fr 1.3rem 1fr; gap:.3rem; align-items:start; }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.4rem .6rem; font-size:.7rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; }
          .gd .rmb{ display:inline-block; font-size:.54rem; font-weight:800; letter-spacing:.04em; border-radius:999px; padding:.04rem .42rem; color:#fff; background:#7c3aed; white-space:nowrap; vertical-align:1px; }
          .gd .fpill{ display:inline-block; padding:.05rem .45rem; font-size:.6rem; font-weight:700; border-radius:999px; white-space:nowrap; background:var(--panel); color:var(--soft); }

          /* form bits */
          .gd .frm{ display:flex; flex-direction:column; gap:.5rem; max-width:34rem; }
          .gd .lg{ font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .lg small{ font-weight:400; color:var(--faint); }
          .gd .hint{ font-size:.6rem; color:var(--faint); }
          .gd .ib{ display:flex; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.68rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.sel{ padding-right:1.2rem; } .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.35rem; color:var(--faint); font-size:.64rem; }
          .gd .ib.ta{ min-height:40px; align-items:flex-start; padding-top:.3rem; white-space:normal; }
          .gd .ib.q{ display:inline-flex; width:2.6rem; min-height:22px; justify-content:center; }
          .gd .gph{ color:var(--faint); }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .bln{ display:grid; grid-template-columns:auto 1fr auto; gap:.5rem; align-items:center; padding:.4rem .55rem; border:1px solid var(--line); border-radius:9px; background:var(--surface); font-size:.64rem; color:var(--ink); }
          .gd .bln small{ display:block; color:var(--faint); font-size:.56rem; }
          .gd .bln .hm{ display:flex; align-items:center; gap:.3rem; color:var(--soft); white-space:nowrap; }
          .gd .tk{ width:14px; height:14px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:3px; display:grid; place-items:center;
                   font-size:.62rem; font-weight:900; color:#fff; background:var(--surface); }
          .gd .gd-done .tk.a-sel, .gd .tk.on{ background:var(--accent); border-color:var(--accent); }
          .gd .rd{ display:flex; align-items:center; gap:.4rem; font-size:.68rem; color:var(--ink); margin:.12rem 0; }
          .gd .rd i{ width:13px; height:13px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:50%; background:var(--surface); flex:none; }
          .gd .rd i.on{ background:var(--accent); box-shadow:inset 0 0 0 2.5px var(--surface); border-color:var(--accent); }
          .gd .gd-done .rd i.a-sel{ box-shadow:inset 0 0 0 2.5px var(--surface); }
          .gd .rd i{ position:relative; } .gd .rd i .dot{ position:absolute; inset:2px; border-radius:50%; background:var(--accent); }
          .gd .stk{ display:grid; } .gd .stk > *{ grid-area:1/1; }
          .gd .sub{ margin:.3rem 0 .2rem 1.2rem; display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; font-size:.62rem; color:var(--soft); }
          .gd .photo{ display:inline-grid; place-items:center; width:4.2rem; height:3rem; border-radius:7px; border:1px solid var(--line);
                      background:linear-gradient(135deg,#e9d5b7 0 60%,#c9a77a 60% 64%,#e9d5b7 64%); font-size:.5rem; color:#7c5a2c; font-weight:700; }

          /* remakes page */
          .gd .rtabs{ display:flex; gap:.3rem; flex-wrap:wrap; margin:0 0 .6rem; }
          .gd .rtabs span{ padding:.2rem .6rem; font-size:.66rem; border-radius:999px; background:var(--panel); color:var(--soft); border:1px solid var(--line); }
          .gd .rtabs span.on{ background:var(--accent); color:#fff; border-color:var(--accent); }
          .gd .rcard{ border:1px solid var(--line); border-left:4px solid #7c3aed; border-radius:10px; padding:.55rem .65rem; background:var(--surface);
                      display:grid; grid-template-columns:1.25fr 1fr; gap:.7rem; font-size:.64rem; color:var(--ink); }
          .gd .rcard h4{ margin:0 0 .15rem; font-size:.76rem; }
          .gd .rcard .m{ color:var(--soft); font-size:.6rem; }
          .gd .rcard ul{ margin:.35rem 0; padding-left:1rem; }
          .gd .rnote{ background:var(--panel); border-radius:7px; padding:.3rem .45rem; margin:.25rem 0; }
          .gd .rtab{ border:1px solid var(--line); border-radius:10px; overflow:hidden; background:var(--surface); }
          .gd .rrow{ display:grid; grid-template-columns:1.5fr 1.1fr 1.6fr 1fr 1.2fr .7fr .9fr; gap:.3rem; align-items:start; padding:.32rem .5rem;
                     border-top:1px solid var(--line); font-size:.6rem; color:var(--ink); }
          .gd .rrow:first-child{ border-top:0; }
          .gd .rrow.th{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.52rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .rrow small{ display:block; color:var(--faint); font-size:.54rem; }
          .gd .rrow .n{ text-align:right; }
          .gd .kpis{ display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.4rem; margin-bottom:.6rem; }
          .gd .kpi{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--surface); min-width:0; }
          .gd .kpi .l{ font-size:.5rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .kpi .v{ font-size:.95rem; font-weight:800; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .kpi small{ display:block; font-size:.52rem; color:var(--faint); }
          .gd .three{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; }
          .gd .mt{ border:1px solid var(--line); border-radius:9px; padding:.4rem .5rem; background:var(--surface); font-size:.58rem; min-width:0; }
          .gd .mt h5{ margin:0 0 .25rem; font-size:.7rem; color:var(--ink); }
          .gd .mt div{ display:flex; justify-content:space-between; gap:.3rem; border-top:1px solid var(--line); padding:.18rem 0; color:var(--ink); }
          .gd .mt div:first-of-type{ border-top:0; }

          /* the account card on their order */
          .gd .acard{ border:1px solid var(--line); border-left:4px solid #7c3aed; border-radius:10px; padding:.5rem .65rem; background:var(--surface); font-size:.66rem; color:var(--ink); max-width:34rem; }
          .gd .acard .top{ display:flex; justify-content:space-between; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .acard .ln{ margin-top:.35rem; font-size:.62rem; }

          /* incoming row + invoice */
          .gd .iorow{ display:grid; grid-template-columns:2fr 1.6fr 1fr; gap:.4rem; align-items:center; padding:.45rem .6rem; border-radius:9px; font-size:.66rem;
                      border:1px solid var(--line); margin-bottom:.35rem; color:var(--ink); }
          .gd .iorow.pu{ border-left:4px solid #7c3aed; background:rgba(124,58,237,.09); }
          .gd .iorow.rd2{ border-left:4px solid #e11d48; background:rgba(225,29,72,.06); }
          .gd .inv{ border:1px solid var(--line); border-radius:10px; background:var(--surface); font-size:.62rem; overflow:hidden; }
          .gd .inv div{ display:grid; grid-template-columns:3fr .5fr .9fr; gap:.3rem; padding:.3rem .55rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .inv div:first-child{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.52rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .inv .n{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .inv .tot{ font-weight:800; }

          /* reasons editor */
          .gd .rr{ display:flex; flex-direction:column; gap:.3rem; max-width:24rem; }
          .gd .rr .r{ display:grid; grid-template-columns:3rem 1fr 3rem; gap:.35rem; align-items:center; font-size:.66rem; }
          .gd .rr .r.h{ font-size:.54rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; }
          .gd .rr .r.off .ib{ color:var(--faint); text-decoration:line-through; }
          .gd .rr .r > .tk{ justify-self:center; }

          @media (max-width:640px){
            .gd .sc{ min-height:600px; }
            .gd .two, .gd .flow, .gd .rcard, .gd .three{ grid-template-columns:1fr; }
            .gd .flow .arrow i{ display:inline-block; transform:rotate(90deg); font-style:normal; line-height:1; }
            .gd .kpis{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .gd .rrow{ grid-template-columns:1.4fr 1fr 1fr .9fr; } .gd .rrow .hs{ display:none; }
            .gd .bln{ grid-template-columns:auto 1fr; } .gd .bln .hm{ grid-column:2; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / factory / remakes</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>FACTORY CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Orders</a><a class="on">Remakes (1 to approve)</a><a>Calendar</a>
                <div class="navh">Production</div>
                <a>Incoming orders</a><a>Floor</a><a>Dispatch</a>
                <div class="navh">Accounts</div>
                <a>Invoices</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $rmHead . $tabs('waiting') . '
                  <div class="rcard"><div><h4>ABC Blinds &middot; order ABC-2026-0038</h4><div class="m">Reported 8 Oct 2026, 14:12 &middot; their ref PO 4402 &middot; <span class="lnk">open order</span></div>
                    <ul><li>1 &times; ' . $L1 . '</li></ul><b>Fabric fault</b><div class="rnote">Flaw in the fabric 300mm from the bottom</div></div>
                    <div><div class="lg">Who pays?</div>' . $radio('Free &mdash; our fault', 'on') . $radio('Charge the account') . $radio('Free &mdash; supplier claim') . '<div style="margin-top:.4rem"><span class="btnp">Approve remake</span></div></div></div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what a remake is -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">What a remake is</div>
                  <div class="chips" style="margin:0 0 .8rem">
                    <span class="chip a-pop" style="' . $d(1, 'A fabric fault') . '">a fabric fault</span>
                    <span class="chip a-pop" style="' . $d(1, 'a measuring') . '">a measuring mistake</span>
                    <span class="chip a-pop" style="' . $d(1, 'or damage') . '">damage on the way</span>
                  </div>
                  <div class="flow">
                    <div class="card a-rise" style="' . $d(1, 'A remake always') . '"><h4>The original order</h4><b>ABC-2026-0038</b><br>
                      <span class="a-fade" style="' . $d(1, 'You say which') . '">&#10003; 1 &times; Lounge roller</span><br><span class="a-fade" style="' . $d(1, 'and why') . '">Fabric fault</span></div>
                    <div class="arrow a-fade" style="' . $d(1, 'Once it is approved') . '"><i>&rarr;</i></div>
                    <div class="card a-rise" style="' . $d(1, 'it becomes') . ';border-color:#7c3aed"><h4>A brand new order</h4><b class="a-type" style="' . $d(1, 'numbered after', .2) . ';--ts:16;--tt:1.2s">ABC-2026-0038-R1</b> ' . $rmb . '</div>
                    <div class="arrow a-fade" style="' . $d(1, 'and it goes') . '"><i>&rarr;</i></div>
                    <div class="card a-rise" style="' . $d(1, 'and it goes') . '"><h4>Through the factory</h4>Incoming orders &rarr; Floor &rarr; Dispatch</div>
                  </div>
                </div>

                <!-- 2 — two ways in -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Two ways in</div>
                  <div class="two" style="margin-top:.5rem">
                    <div class="card a-rise" style="' . $d(2, 'The office can') . '"><h4>The office raises it</h4>
                      &#8635; Remake on the order &rarr; <b>Raise remake</b>
                      <div class="chips"><span class="chip ok a-pop" style="' . $d(2, 'it is approved at once') . '">&#10003; approved at once</span></div></div>
                    <div class="card a-rise" style="' . $d(2, 'Or the trade account') . '"><h4>The account reports a fault</h4>
                      From their own order &rarr; <b>Report a problem</b>
                      <div class="chips"><span class="chip pu a-pop" style="' . $d(2, 'Theirs waits') . '">&#9203; waits for approval</span></div></div>
                  </div>
                  <span class="chip bad a-pop" style="' . $d(2, 'nothing is made') . ';margin-top:.8rem">Nothing is made until the office says yes</span>
                </div>

                <!-- 3 — raising one -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">Raising one yourself</div>
                  <div class="chips" style="margin:0 0 .6rem">
                    <span class="chip a-pop" style="' . $d(3, 'find the order') . '">Orders &rarr; <span class="lnk a-ring" style="' . $d(3, 'click Remake') . '">&#8635; Remake</span></span>
                    <span class="chip a-pop" style="' . $d(3, 'Or, from the order') . '">Edit order &rarr; <span class="lnk a-ring" style="' . $d(3, 'click Raise') . '">&#8635; Raise a remake</span></span>
                  </div>
                  <div class="a-rise" style="' . $d(3, 'The Raise a remake page') . '">
                    <div class="dh"><div><div class="pt">Raise a remake</div><div class="psub">On order <b>ABC-2026-0038</b> for ABC Blinds &middot; their ref PO 4402</div></div><span class="btns">All remakes</span></div>
                    <div class="frm"><div class="lg">Which blinds need remaking?</div>
                      ' . $bline($L1, 1, 2, '<span class="a-fade" style="' . $d(3, 'Tick the blinds', .4) . '">&#10003;</span>', '<span class="swap"><span class="a-out" style="' . $d(3, 'Tick the blinds', .4) . '">0</span><span class="a-fade" style="' . $d(3, 'how many of each') . '">1</span></span>', 'a-sel', $d(3, 'Tick the blinds')) . '
                      ' . $bline($L2, 2, 1) . '
                    </div>
                  </div>
                </div>

                <!-- 4 — reason / note / photo -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">Reason, note and photo</div>
                  <div class="frm" style="margin-top:.4rem">
                    <div class="lg">Reason</div>
                    <span class="ib sel a-ring" style="' . $d(4, 'choose a Reason') . ';max-width:18rem"><span class="swap"><span class="gph a-out" style="' . $d(4, 'Factory fault and') . '">Choose a reason&hellip;</span><span class="a-fade" style="' . $d(4, 'Factory fault and') . '">Fabric fault</span></span></span>
                    <div class="chips" style="margin:0">
                      <span class="chip a-pop" style="' . $d(4, 'Measuring error') . '">Measuring error</span><span class="chip a-pop" style="' . $d(4, 'Fitting damage') . '">Fitting damage</span>
                      <span class="chip a-pop" style="' . $d(4, 'Factory fault') . '">Factory fault</span><span class="chip a-pop" style="' . $d(4, 'and so on') . '">Fabric fault</span>
                      <span class="chip a-pop" style="' . $d(4, 'and so on', .3) . '">Wrong spec</span><span class="chip a-pop" style="' . $d(4, 'and so on', .6) . '">Other</span>
                    </div>
                    <div class="lg" style="margin-top:.3rem">What&rsquo;s wrong?</div>
                    <span class="ib ta"><span class="swap"><span class="gph a-out" style="' . $d(4, 'say what the problem') . '">e.g. fabric flaw 300mm from the bottom</span>
                      <span class="a-fade" style="' . $d(4, 'say what the problem') . '"><span class="a-type" style="' . $d(4, 'say what the problem', .2) . ';--ts:40;--tt:2.2s">Flaw in the fabric 300mm from the bottom</span></span></span></span>
                    <div class="lg" style="margin-top:.3rem">Photo <small>(optional)</small></div>
                    <div style="display:flex;gap:.6rem;align-items:center"><span class="btns">Choose file</span><span class="photo a-pop" style="' . $d(4, 'add a photo') . '">fault.jpg</span></div>
                    <div class="hint a-fade" style="' . $d(4, 'A JPG') . '">A picture of the fault helps. JPG, PNG or WebP, up to 12 MB.</div>
                  </div>
                </div>

                <!-- 5 — who pays -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">Who pays?</div>
                  <div class="frm" style="margin-top:.5rem">
                    <div class="lg">Who pays?</div>
                    <div class="rd"><i><b class="dot a-out" style="' . $d(5, 'Charge the account') . '"></b></i><span class="a-ring" style="' . $d(5, 'Free, our fault') . ';border-radius:4px;padding:0 .15rem">Free &mdash; our fault</span></div>
                    <div class="rd"><i><b class="dot a-mid" style="' . $d(5, 'Charge the account') . ';--d2:' . $at(5, 'Or Free, supplier') . 's"></b></i><span class="a-ring" style="' . $d(5, 'Charge the account') . ';border-radius:4px;padding:0 .15rem">Charge the account</span></div>
                    <div class="rd"><i><b class="dot a-fade" style="' . $d(5, 'Or Free, supplier') . '"></b></i><span class="a-ring" style="' . $d(5, 'Or Free, supplier') . ';border-radius:4px;padding:0 .15rem">Free &mdash; supplier claim</span></div>
                    <div class="stk">
                      <div class="sub a-mid" style="' . $d(5, 'where you type what') . ';--d2:' . $at(5, 'Or Free, supplier') . 's">Charge the account (&pound;, ex VAT) <span class="ib" style="width:5.5rem"><span class="a-type" style="' . $d(5, 'before VAT') . ';--ts:5;--tt:.6s">25.00</span></span>
                        <span class="a-fade" style="' . $d(5, 'The full trade price') . '">Full trade price of these blinds: &pound;48.20</span></div>
                      <div class="sub a-fade" style="' . $d(5, 'which supplier') . '">Supplier the claim is against <span class="ib" style="width:9rem"><span class="swap"><span class="gph a-out" style="' . $d(5, 'which supplier', 1) . '">e.g. Louvolite</span><span class="a-fade" style="' . $d(5, 'which supplier', 1) . '">Louvolite</span></span></span></div>
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(5, 'Free, our fault', 1) . '">Free &rarr; &pound;0 to the account</span>
                    <span class="chip a-pop" style="' . $d(5, 'as a guide') . '">Charge &rarr; any amount, decided on merit</span>
                    <span class="chip a-pop" style="' . $d(5, 'logged as a claim') . '">Supplier claim &rarr; &pound;0, logged as a claim</span>
                  </div>
                </div>

                <!-- 6 — due date + raise -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">Due date, and raise it</div>
                  <div class="frm" style="margin-top:.5rem">
                    <div class="lg">Due date <small>(optional)</small></div>
                    <span class="ib a-ring" style="' . $d(6, 'due date') . ';width:9rem"><span class="swap"><span class="gph a-out" style="' . $d(6, 'due date', 1) . '">dd/mm/yyyy</span><span class="a-fade" style="' . $d(6, 'due date', 1) . '">16/10/2026</span></span></span>
                    <span class="chip a-pop" style="' . $d(6, 'office calendar') . ';align-self:flex-start">&#128197; shows on the office Calendar</span>
                    <div><span class="btnp a-press a-ring" style="' . $d(6, 'Click Raise') . '">Raise remake</span></div>
                  </div>
                  <div class="bnr a-pop" style="' . $d(6, 'The message') . ';margin-top:.8rem;max-width:34rem">Remake ABC-2026-0038-R1 raised &mdash; it&rsquo;s in Incoming orders as a new order.</div>
                  <span class="chip ok a-pop" style="' . $d(6, 'approved straight') . '">&#10003; approved straight away</span>
                </div>

                <!-- 7 — the account reports -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">An account reports a fault</div>
                  <p class="scs a-fade" style="--d:.6s">What ABC Blinds see on their own order:</p>
                  <div class="acard a-rise" style="' . $d(7, 'they see a box') . '"><div class="top"><b>Something wrong with a blind?</b><span class="btns a-ring" style="' . $d(7, 'They click Report') . '">Report a problem</span></div></div>
                  <div class="a-rise" style="' . $d(7, 'tick the blinds') . ';margin-top:.7rem">
                    <div class="pt">Report a problem</div>
                    <div class="psub">Order <b>ABC-2026-0038</b>. Tell the factory which blinds need remaking and why. They&rsquo;ll check it and let you know on this order.</div>
                    <div class="frm">' . $bline($L1, 1, 2, '&#10003;', '1', 'on') . '
                      <span class="ib sel a-fade" style="' . $d(7, 'choose a reason') . ';max-width:18rem">Fabric fault</span>
                      <div><span class="btnp a-press a-ring" style="' . $d(7, 'Send to the factory') . '">Send to the factory</span></div></div>
                  </div>
                </div>

                <!-- 8 — waiting -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">Waiting for approval</div>
                  <div class="chips" style="margin:0 0 .6rem">
                    <span class="chip pu a-pop" style="' . $d(8, 'The menu says') . '">Menu: <b>Remakes (1 to approve)</b></span>
                    <span class="chip pu a-pop" style="' . $d(8, 'and so does the Dashboard') . '">Dashboard: <b>1 waiting for approval</b></span>
                  </div>
                  ' . $tabs('waiting') . '
                  <div class="rcard a-rise" style="' . $d(8, 'Each card') . '"><div>
                    <h4>ABC Blinds &middot; order ABC-2026-0038</h4>
                    <div class="m a-fade" style="' . $d(8, 'when it was reported') . '">Reported 8 Oct 2026, 14:12 &middot; their ref PO 4402 &middot; <span class="lnk">open order</span></div>
                    <ul class="a-fade" style="' . $d(8, 'the blinds') . '"><li>1 &times; ' . $L1 . '</li></ul>
                    <b class="a-fade" style="' . $d(8, 'the reason') . '">Fabric fault</b>
                    <div class="rnote a-fade" style="' . $d(8, 'their note') . '">Flaw in the fabric 300mm from the bottom</div>
                    <span class="photo a-pop" style="' . $d(8, 'and photo') . '">photo</span>
                    <div class="m a-fade" style="' . $d(8, 'and the trade price') . ';margin-top:.3rem">Trade price of these blinds: <b class="a-ring" style="' . $d(8, 'and the trade price', .3) . '">&pound;48.20</b></div>
                  </div><div style="color:var(--faint);font-size:.62rem">Approve / decline &rarr;</div></div>
                </div>

                <!-- 9 — approve -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">Approving</div>
                  <div class="rcard"><div>
                    <h4>ABC Blinds &middot; order ABC-2026-0038</h4><ul><li>1 &times; ' . $L1 . '</li></ul><b>Fabric fault</b>
                    <div class="m" style="margin-top:.3rem">Trade price of these blinds: &pound;48.20</div></div>
                    <div><div class="lg">Who pays?</div>
                      ' . $radio('Free &mdash; our fault', 'a-sel', $d(9, 'Free, charge')) . $radio('Charge the account') . $radio('Free &mdash; supplier claim') . '
                      <div class="lg" style="margin-top:.35rem">Due date <small>(optional)</small></div>
                      <span class="ib" style="width:8rem"><span class="swap"><span class="gph a-out" style="' . $d(9, 'Add a due date') . '">dd/mm/yyyy</span><span class="a-fade" style="' . $d(9, 'Add a due date') . '">16/10/2026</span></span></span>
                      <div style="margin-top:.4rem"><span class="btnp a-press a-ring" style="' . $d(9, 'click Approve') . '">Approve remake</span></div></div>
                  </div>
                  <div class="bnr a-pop" style="' . $d(9, 'The remake order is made') . ';margin-top:.7rem">Approved &mdash; remake ABC-2026-0038-R1 is in Incoming orders as a new order.</div>
                  <div class="acard a-rise" style="' . $d(9, 'The account sees') . '"><div class="top"><b>Something wrong with a blind?</b><span class="btns">Report a problem</span></div>
                    <div class="ln"><b>8 Oct 2026 &middot; Fabric fault</b> &mdash; Approved &mdash; being remade as ABC-2026-0038-R1 (Confirmed) &middot; no charge</div></div>
                </div>

                <!-- 10 — decline -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="sct a-fade" style="--d:.2s">Declining</div>
                  <div class="card" style="max-width:30rem">
                    <div class="lg">Or decline &mdash; why? <small>(the account sees this)</small></div>
                    <span class="ib a-ring" style="' . $d(10, 'Type a short') . ';margin:.3rem 0"><span class="swap"><span class="gph a-out" style="' . $d(10, 'For example') . '">e.g. Measured by you &mdash; size made as ordered</span>
                      <span class="a-fade" style="' . $d(10, 'For example') . '"><span class="a-type" style="' . $d(10, 'For example', .3) . ';--ts:41;--tt:2s">Measured by you &mdash; size made as ordered</span></span></span></span>
                    <span class="btns a-press" style="' . $d(10, 'Then click Decline') . '">Decline</span>
                  </div>
                  <div class="bnr a-pop" style="' . $d(10, 'Then click Decline', 1) . ';margin-top:.7rem;max-width:30rem">Declined &mdash; the account sees your reason on their order.</div>
                  <div class="acard a-rise" style="' . $d(10, 'Their order shows') . '"><div class="top"><b>Something wrong with a blind?</b><span class="btns">Report a problem</span></div>
                    <div class="ln"><b>8 Oct 2026 &middot; Fabric fault</b> &mdash; Declined by the factory: Measured by you &mdash; size made as ordered</div></div>
                </div>

                <!-- 11 — the remake order -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="sct a-fade" style="--d:.2s">The remake order</div>
                  <div class="chips" style="margin:0 0 .7rem">
                    <span class="chip a-pop" style="' . $d(11, 'A remake order') . '">ABC-2026-0038</span><span class="arrow a-fade" style="' . $d(11, 'with R one') . '">&rarr;</span>
                    <span class="chip pu a-pop" style="' . $d(11, 'with R one') . '"><span>ABC-2026-0038-<b>R1</b></span></span>
                    <span class="chip pu a-pop" style="' . $d(11, 'then R two') . '"><span>ABC-2026-0038-<b>R2</b></span></span>
                  </div>
                  <span class="chip a-pop" style="' . $d(11, 'same account') . '">Same account &middot; same blinds</span>
                  <div style="margin-top:.7rem">
                    <div class="lg a-fade" style="' . $d(11, 'It goes through') . ';margin-bottom:.3rem">Incoming orders</div>
                    <div class="iorow rd2 a-fly" style="' . $d(11, 'It goes through', .3) . '"><span><b>ABC-2026-0041</b></span><span>ABC Blinds</span><span class="fpill">New</span></div>
                    <div class="iorow pu a-fly" style="' . $d(11, 'A purple REMAKE') . '"><span><b>ABC-2026-0038-R1</b> <span class="a-pop" style="' . $d(11, 'A purple REMAKE', .4) . '">' . $rmb . '</span></span><span>ABC Blinds</span><span class="fpill">New</span></div>
                    <div class="iorow a-fly" style="' . $d(11, 'It goes through', .6) . '"><span><b>HUG-2026-0012</b></span><span>Hughes Interiors</span><span class="fpill">In production</span></div>
                  </div>
                  <p class="scs a-fade" style="' . $d(11, 'and a purple row') . ';margin-top:.4rem">The purple is the <b>Remake</b> colour in Factory settings. Once it has gone out, it takes the normal colours.</p>
                </div>

                <!-- 12 — invoicing -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="sct a-fade" style="--d:.2s">Invoicing a remake</div>
                  <div class="two" style="margin-top:.5rem">
                    <div class="a-rise" style="' . $d(12, 'A charged remake') . '"><div class="lg" style="margin-bottom:.3rem">Charged &pound;25.00</div>
                      <div class="inv"><div><span>Description</span><span class="n">Qty</span><span class="n">Net</span></div>
                        <div><span><b class="a-ring" style="' . $d(12, 'starts with the word') . ';border-radius:3px">REMAKE &mdash;</b> Roller Blind &mdash; Bev Roller, Sunset / Ivory</span><span class="n">1</span><span class="n">&pound;25.00</span></div>
                        <div class="tot"><span>Net</span><span></span><span class="n">&pound;25.00</span></div></div></div>
                    <div class="a-rise" style="' . $d(12, 'A free remake') . '"><div class="lg" style="margin-bottom:.3rem">Free</div>
                      <div class="inv"><div><span>Description</span><span class="n">Qty</span><span class="n">Net</span></div>
                        <div><span><b>REMAKE &mdash;</b> Roller Blind &mdash; Bev Roller, Sunset / Ivory</span><span class="n">1</span><span class="n a-ring" style="' . $d(12, 'at nought pounds') . '">&pound;0.00</span></div>
                        <div class="tot"><span>Net</span><span></span><span class="n">&pound;0.00</span></div></div></div>
                  </div>
                  <span class="chip a-pop" style="' . $d(12, 'spread across') . ';margin-top:.7rem">Several blinds? The charge is spread across them by value</span>
                </div>

                <!-- 13 — in progress / done -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="sct a-fade" style="--d:.2s">In progress and Done</div>
                  <div class="rtabs"><span>Waiting for approval</span><span class="a-sel" style="' . $d(13, 'The In progress') . '">In progress</span><span class="a-ring" style="' . $d(13, 'Done lists') . '">Done</span><span>Report</span></div>
                  <div class="rtab a-rise" style="' . $d(13, 'You see the account') . '">
                    <div class="rrow th"><span>Remake</span><span>Account</span><span class="hs">Blinds</span><span>Reason</span><span class="hs">Who pays</span><span class="n hs">Cost</span><span>Status</span></div>
                    <div class="rrow"><span><b class="lnk">ABC-2026-0038-R1</b><small>from ABC-2026-0038 &middot; 8 Oct &middot; due 16 Oct</small></span><span>ABC Blinds<small>reported by them</small></span>
                      <span class="hs">1 &times; Lounge: Roller Blind</span><span>Fabric fault &middot; <span class="lnk">photo</span></span><span class="hs">Free &mdash; our fault</span><span class="n hs">&pound;48.20</span><span><span class="fpill">In Production</span></span></div>
                    <div class="rrow"><span><b class="lnk">CST-2026-0004-R1</b><small>from CST-2026-0004 &middot; 5 Oct</small></span><span>Coastal Shutters</span>
                      <span class="hs">2 &times; Kitchen: Vertical Blind</span><span>Measuring error</span><span class="hs">Charge the account<small>&pound;40.00 charged</small></span><span class="n hs">&pound;80.40</span><span><span class="fpill">Confirmed</span></span></div>
                  </div>
                  <div class="rtab a-rise" style="' . $d(13, 'and the ones that were declined') . ';margin-top:.6rem">
                    <div class="rrow"><span><b>&mdash;</b><small>from HUG-2026-0009 &middot; 2 Oct</small></span><span>Hughes Interiors<small>reported by them</small></span>
                      <span class="hs">1 &times; Bedroom: Roller Blind</span><span>Wrong spec</span><span class="hs">&mdash;</span><span class="n hs">&pound;36.10</span><span><span class="fpill">Declined</span><small>Made as ordered</small></span></div>
                  </div>
                </div>

                <!-- 14 — report -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="sct a-fade" style="--d:.2s">The Report</div>
                  <div style="display:flex;gap:.4rem;align-items:center;margin:.3rem 0 .6rem;font-size:.68rem">Month <span class="ib a-ring" style="' . $d(14, 'Choose the month') . '">October 2026</span></div>
                  <div class="kpis">
                    <div class="kpi a-drop" style="' . $d(14, 'how many remakes') . '"><div class="l">Remakes</div><div class="v">7</div><small>9 blinds</small></div>
                    <div class="kpi a-drop" style="' . $d(14, 'Trade value') . '"><div class="l">Trade value remade</div><div class="v">&pound;412.60</div></div>
                    <div class="kpi a-drop" style="' . $d(14, 'charged back') . '"><div class="l">Charged back</div><div class="v">&pound;120.00</div></div>
                    <div class="kpi a-drop" style="' . $d(14, 'the cost to us') . '"><div class="l">Cost to us</div><div class="v">&pound;196.30</div><small>after charges and supplier claims</small></div>
                    <div class="kpi a-drop" style="' . $d(14, 'supplier claims') . '"><div class="l">Supplier claims</div><div class="v">&pound;96.30</div></div>
                  </div>
                  <div class="three">
                    <div class="mt a-rise" style="' . $d(14, 'by reason') . '"><h5>By reason</h5><div><span>Fabric fault</span><b>&pound;180.40</b></div><div><span>Measuring error</span><b>&pound;140.20</b></div><div><span>Fitting damage</span><b>&pound;92.00</b></div></div>
                    <div class="mt a-rise" style="' . $d(14, 'by account') . '"><h5>By account</h5><div><span>ABC Blinds</span><b>&pound;220.60</b></div><div><span>Coastal Shutters</span><b>&pound;192.00</b></div></div>
                    <div class="mt a-rise" style="' . $d(14, 'by production area') . '"><h5>By production area</h5><div><span>Roller Blinds</span><b>&pound;201.70</b></div><div><span>Vertical Fabrics</span><b>&pound;110.30</b></div><div><span>Not made on the floor</span><b>&pound;100.60</b></div></div>
                    <div class="mt a-rise" style="' . $d(14, 'and by supplier') . '"><h5>Supplier claims</h5><div><span>Louvolite</span><b>&pound;96.30</b></div></div>
                  </div>
                </div>

                <!-- 15 — reasons -->
                <div class="sc" data-scene="15" data-len="' . $len(15) . '">
                  <div class="sct a-fade" style="--d:.2s">Editing the reasons</div>
                  <div class="dh"><div class="pt">Remakes</div><span class="btns a-ring" style="' . $d(15, 'Click Edit reasons') . '">Edit reasons</span></div>
                  <div class="a-rise" style="' . $d(15, 'You can rename') . '">
                    <div class="pt" style="font-size:.9rem;margin-bottom:.4rem">Remake reasons</div>
                    <div class="rr">
                      <div class="r h"><span>Order</span><span>Reason</span><span>In use</span></div>
                      <div class="r"><span class="ib">10</span><span class="ib"><span class="swap"><span class="a-out" style="' . $d(15, 'rename one') . '">Measuring error</span><span class="a-fade" style="' . $d(15, 'rename one') . '">Measured wrong on site</span></span></span><span class="tk on">&#10003;</span></div>
                      <div class="r"><span class="ib"><span class="swap"><span class="a-out" style="' . $d(15, 'change the order') . '">20</span><span class="a-fade" style="' . $d(15, 'change the order') . '">5</span></span></span><span class="ib">Fitting damage</span><span class="tk on">&#10003;</span></div>
                      <div class="r"><span class="ib">30</span><span class="ib">Factory fault</span><span class="tk on">&#10003;</span></div>
                      <div class="r"><span class="ib">60</span><span class="ib">Other</span><span class="tk"><span class="tk on a-out" style="' . $d(15, 'Untick In use') . ';margin:-1.5px">&#10003;</span></span></div>
                      <div class="r"><span class="h" style="font-size:.54rem;font-weight:700;color:var(--faint)">NEW</span><span class="ib"><span class="swap"><span class="gph a-out" style="' . $d(15, 'add a new one') . '">Add a reason, e.g. Damaged in transit</span><span class="a-fade" style="' . $d(15, 'add a new one') . '">Damaged in transit</span></span></span><span></span></div>
                    </div>
                    <div style="margin-top:.5rem"><span class="btnp a-press a-ring" style="' . $d(15, 'Then click Save') . '">Save reasons</span></div>
                  </div>
                  <div class="bnr a-pop" style="' . $d(15, 'Then click Save', 1) . ';margin-top:.5rem;max-width:24rem">Reasons saved.</div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>How a remake works.</b> It always starts on the <b>original order</b>: which of its blinds (and how many), a reason, a note
             and, if you like, a photo. When it is approved, it becomes a <b>remake order</b> &mdash; a new placed order for the same account,
             with those blinds copied, numbered <b>&lt;original&gt;-R1</b>, <b>-R2</b> &hellip; &mdash; and from there it is an ordinary
             order: <b>Incoming orders</b> as <b>New</b>, the floor, dispatch and the delivery note. Its <b>cost</b> is the trade price of the
             blinds remade, taken from what the original order bills.</p>

          <p><b>Raising one yourself</b> (approved there and then):</p>
          <ul class="steps">
            <li><b>Work &rarr; Orders</b> &rarr; <b>&#8635; Remake</b> beside the order&rsquo;s stage &mdash; or, on the order&rsquo;s
                <b>Edit order</b> screen, <b>&#8635; Raise a remake</b> by the order number. The page says <em>On order &hellip; for &hellip;
                &middot; their ref &hellip;</em></li>
            <li><b>Which blinds need remaking?</b> &mdash; tick each blind and set <b>How many</b> (up to the number on the order).</li>
            <li><b>Reason</b> &mdash; from your list (to start with: Measuring error, Fitting damage, Factory fault, Fabric fault, Wrong spec,
                Other). <b>What&rsquo;s wrong?</b> &mdash; a few words. <b>Photo (optional)</b> &mdash; <em>JPG, PNG or WebP, up to 12 MB.</em></li>
            <li><b>Who pays?</b> &mdash; <b>Free &mdash; our fault</b>; <b>Charge the account</b> (type <b>Charge the account (&pound;, ex
                VAT)</b>; the <em>Full trade price of these blinds</em> is shown as a guide &mdash; charge any amount on merit); or
                <b>Free &mdash; supplier claim</b> (type <b>Supplier the claim is against</b>).</li>
            <li><b>Due date (optional)</b> &mdash; puts it on the office <b>Calendar</b> until it goes out.</li>
            <li><b>Raise remake</b> &rarr; <code>Remake ABC-2026-0038-R1 raised &mdash; it&rsquo;s in Incoming orders as a new order.</code></li>
          </ul>

          <p><b>When an account reports a fault.</b> On their own placed order they see <b>Something wrong with a blind?</b> with a
             <b>Report a problem</b> button. They tick the blinds, choose a reason, say what&rsquo;s wrong, add a photo if they like, and press
             <b>Send to the factory</b>. It waits on <b>Remakes &rarr; Waiting for approval</b> &mdash; nothing is made until the office approves
             it. The menu reads <b>Remakes (1 to approve)</b> and the Dashboard&rsquo;s Remakes tile says <b>1 waiting for approval</b>.</p>
          <ul class="steps">
            <li>Each card shows the account and order, <em>Reported &hellip;</em>, their ref, an <b>open order</b> link, the blinds, the reason,
                their note and photo, and <em>Trade price of these blinds</em>.</li>
            <li><b>Approve</b>: choose <b>Who pays?</b> and an optional <b>Due date</b>, then <b>Approve remake</b> &rarr;
                <code>Approved &mdash; remake &hellip; is in Incoming orders as a new order.</code></li>
            <li><b>Decline</b>: <b>Or decline &mdash; why? (the account sees this)</b> &mdash; a reason is required &mdash; then <b>Decline</b>
                &rarr; <code>Declined &mdash; the account sees your reason on their order.</code></li>
            <li>On their order the account sees <em>Waiting for the factory to check it</em>, then either <em>Approved &mdash; being remade as
                &hellip; (stage) &middot; no charge</em> (or <em>&middot; charge &pound;&hellip; + VAT</em>), or <em>Declined by the factory:
                &hellip;</em> with your reason.</li>
          </ul>

          <p><b>In the factory.</b> In <b>Incoming orders</b> the remake order carries a purple <b>REMAKE</b> badge and its row takes the
             <b>Remake</b> colour (purple unless changed in <b>Factory settings</b>) until it is dispatched. The Orders list shows the badge too.</p>

          <p><b>On the invoice.</b> A remake order bills <b>exactly the charge you decided</b>, spread over its blinds by value, and every line
             reads <b>REMAKE &mdash; &hellip;</b>. A free remake (our fault, or a supplier claim) shows as <b>&pound;0</b> lines.</p>

          <ul class="steps">
            <li><b>In progress</b> &mdash; approved remakes not dispatched yet: Remake (with <em>from &hellip;</em>, the date and any due date),
                Account (<em>reported by them</em> if the account raised it), Blinds, Reason (and photo), Who pays, Cost, Status.</li>
            <li><b>Done</b> &mdash; dispatched, or <b>Declined</b> with the reason given.</li>
            <li><b>Report</b> &mdash; choose a <b>Month</b>. <b>Remakes</b> (and blinds), <b>Trade value remade</b>, <b>Charged back</b>,
                <b>Cost to us</b> (<em>after charges and supplier claims</em>), <b>Supplier claims</b>; then <b>By reason</b> and
                <b>By account</b> (Remakes, Blinds, Value, Charged), <b>By production area</b> (where the original blind was made &mdash; a blind
                made across two areas, such as a vertical&rsquo;s headrail and fabric, counts under each with its value split; blinds that never
                went through the floor show as <em>Not made on the floor</em>) and <b>Supplier claims</b> (Supplier, Remakes, Value to claim). Approved
                remakes raised that month are counted.</li>
          </ul>

          <p><b>Edit reasons</b> (top of the Remakes page) opens <b>Remake reasons</b>: change the <b>Order</b> number or the wording, untick
             <b>In use</b> to retire one (remakes that already used it keep it), add one in the <b>New</b> row, then <b>Save reasons</b>.</p>',
        'script'  => $S,
];
