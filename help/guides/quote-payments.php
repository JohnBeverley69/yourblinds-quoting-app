<?php
declare(strict_types=1);

/**
 * Guide: quote-payments — "Payments & accounts" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Follows ONE order's money from deposit to paid in full. Mirrors:
 *   /quote-builder/edit.php   — the Deposit panel (quote and order versions),
 *                               the sticky bar's "💷 Take payment" link, the
 *                               Payments panel + "💷 Record a new payment" card
 *   /quote-builder/deposit.php — save_amount / record_paid / mark_paid + messages
 *   /orders/index.php          — the Deposit and Outstanding columns (+ link)
 *   /accounts/index.php        — where the Outstanding link lands (?prefill_quote)
 *   /accounts/payment_save.php, payment_delete.php — messages, settle-to-paid
 *   quote-builder/_helpers.php — qb_settle_if_paid + qb_send_receipt_if_due
 *   /admin/settings.php        — Quoting tab: Default deposit, Paid-in-full receipt
 * Every label, button and message is copied from those files.
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

// The order's heading strip (quote number, customer, status pill).
$head = static fn (string $status): string =>
    '<div class="ohead"><b>BEV-2026-0042</b><span class="who">Emma Fletcher</span>' . $status . '<span class="tot">Total &pound;660.00</span></div>';

$methods = ['Cash', 'Card', 'Bank transfer', 'Cheque', 'PayPal', 'Stripe', 'GoCardless', 'Other'];
$methodList = implode('', array_map(static fn ($m) => '<span' . ($m === 'Bank transfer' ? ' class="on"' : '') . '>' . $m . '</span>', $methods));

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Payments & accounts',
        'eyebrow' => 'Quotes',
        'v'       => 2,
        'blurb'   => 'Deposits, balances and receipts: set and record the deposit, take the balance, follow the Outstanding link, and watch the order settle itself to paid — with the receipt emailed for you.',
        'lede'    => 'Money on a job lives in <b>three places</b>, and they always agree: the <b>Deposit</b> panel on the order, the
                      <b>Payments</b> panel just below it, and the <b>Retail &rarr; Payments</b> page. This guide follows <b>one order</b>
                      from its deposit to paid in full &mdash; slowly, one idea per chapter. Watch it through once, then use
                      <b>Jump to a chapter</b> to go back over any part. To get there: <b>Retail &rarr; Orders</b> &rarr; open the order.',
        'open'    => '/orders/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; }
          /* fades AND folds away (a removed row / a card that disappears) */
          .gd .shrink{ max-height:12rem; overflow:hidden; }
          .gd .gd-play .shrink{ animation:qpShrink .7s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .shrink{ display:none; }
          @keyframes qpShrink{ to{ opacity:0; max-height:0; padding-top:0; padding-bottom:0; margin:0; border-width:0; } }

          /* buttons, links, chips */
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.32rem .75rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .btnx{ display:inline-grid; place-items:center; background:var(--err); color:#fff; border-radius:6px; width:1.3rem; height:1.2rem;
                     font-size:.72rem; font-weight:800; }
          .gd .lnk{ color:var(--accent); font-weight:700; border-radius:4px; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; } .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }

          /* the order mock */
          .gd .ohead{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; font-size:.76rem; color:var(--ink); margin-bottom:.6rem; }
          .gd .ohead .who{ color:var(--soft); }
          .gd .ohead .tot{ margin-left:auto; font-weight:800; }
          .gd .st{ display:inline-block; border-radius:999px; padding:.08rem .5rem; font-size:.62rem; font-weight:700; color:#fff; background:#64748b; }
          .gd .st.sent{ background:#2563eb; } .gd .st.ord{ background:#7c3aed; } .gd .st.paid{ background:#059669; }
          .gd .pnl{ border:1px solid var(--line); border-radius:10px; padding:.6rem .75rem; background:var(--surface); margin-bottom:.6rem; }
          .gd .pnl h5{ margin:0 0 .4rem; font-size:.8rem; color:var(--ink); font-weight:800; }
          .gd .ptxt{ font-size:.7rem; color:var(--soft); margin:0 0 .45rem; }
          .gd .frm{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
          .gd .frm label{ font-size:.66rem; color:var(--faint); }
          .gd .inp{ display:inline-flex; align-items:center; min-width:5.2rem; height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    background:var(--surface); padding:0 .45rem; font-size:.74rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .inp.wide{ min-width:9rem; } .gd .inp .ph{ color:var(--faint); }
          .gd .sugg{ font-size:.66rem; color:var(--faint); }
          .gd .ovr{ font-size:.66rem; color:var(--accent); font-weight:700; }
          .gd .okline{ background:var(--good-wash); color:var(--good); border-radius:7px; padding:.35rem .6rem; font-size:.74rem; font-weight:700; margin-bottom:.45rem; }
          .gd .amb{ background:color-mix(in srgb,#f59e0b 16%,transparent); color:#92400e; border-radius:7px; padding:.35rem .6rem; font-size:.74rem;
                    font-weight:700; margin-bottom:.45rem; }
          .gd .amb small{ font-weight:400; opacity:.75; }
          :root[data-theme="dark"] .gd .amb{ color:#fbbf24; }
          @media (prefers-color-scheme:dark){ :root:not([data-theme="light"]) .gd .amb{ color:#fbbf24; } }
          .gd .bannerbox{ display:grid; } .gd .bannerbox > div{ grid-area:1/1; }

          /* sticky bar */
          .gd .qsb{ display:flex; align-items:center; gap:.55rem; flex-wrap:wrap; background:var(--panel); border:1px solid var(--line); border-radius:9px;
                    padding:.4rem .6rem; font-size:.72rem; color:var(--ink); margin-bottom:.6rem; }
          .gd .qsb .tp{ border:1px solid var(--good); color:var(--good); border-radius:7px; padding:.18rem .5rem; font-weight:700; }
          .gd .qsb .tot{ margin-left:auto; font-weight:800; }

          /* record-a-payment card */
          .gd .rpc{ border:1px dashed var(--accent); border-radius:10px; padding:.55rem .7rem; background:var(--accent-wash); }
          .gd .rpc h6{ margin:0 0 .15rem; font-size:.76rem; color:var(--ink); font-weight:800; }
          .gd .rpc p{ margin:0 0 .45rem; font-size:.64rem; color:var(--soft); }
          .gd .rpg{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.4rem .5rem; align-items:end; }
          .gd .rpg .f label{ display:block; font-size:.58rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.15rem; }
          .gd .rpg .inp{ width:100%; min-width:0; box-sizing:border-box; }
          .gd .rpg .selectbox{ min-width:0; width:100%; box-sizing:border-box; padding:.2rem .45rem; font-size:.72rem; height:26px; }
          .gd .rpc .go{ margin-top:.5rem; }
          .gd .ddwrap{ position:relative; }
          .gd .dd{ position:absolute; z-index:5; left:0; top:100%; width:100%; min-width:8rem; background:var(--surface); border:1px solid var(--line);
                   border-radius:7px; box-shadow:var(--gd-shadow); padding:.2rem 0; font-size:.68rem; }
          .gd .dd span{ display:block; padding:.12rem .5rem; color:var(--ink); } .gd .dd span.on{ background:var(--accent); color:#fff; }

          /* small tables */
          .gd .tb{ display:grid; border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.7rem; margin-bottom:.5rem; }
          .gd .tb > span{ padding:.3rem .45rem; border-top:1px solid var(--line); background:var(--surface); color:var(--ink); white-space:nowrap;
                          overflow:hidden; text-overflow:ellipsis; font-variant-numeric:tabular-nums; }
          .gd .tb > span.h{ background:var(--panel); color:var(--soft); font-weight:700; font-size:.6rem; text-transform:uppercase; letter-spacing:.04em; border-top:0; }
          .gd .tb > span.r{ text-align:right; }
          .gd .tb.pay{ grid-template-columns:1fr 1fr 1.3fr .9fr auto; }
          .gd .tb.ord{ grid-template-columns:1.25fr 1.1fr .8fr .9fr 1.15fr 1fr; }
          .gd .gr, .gd .tb > span.gr{ color:var(--good); font-weight:700; } .gd .am, .gd .tb > span.am{ color:#b45309; font-weight:700; }
          .gd .amlink{ color:#b45309; font-weight:800; border-bottom:1px dashed #b45309; }
          :root[data-theme="dark"] .gd .am, :root[data-theme="dark"] .gd .amlink{ color:#fbbf24; border-color:#fbbf24; }
          .gd .rowgrp{ display:contents; }

          /* scene 1 — three homes */
          .gd .three{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.6rem; }
          .gd .home{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .home h4{ margin:0 0 .3rem; font-size:.8rem; color:var(--ink); }
          .gd .home .where{ display:block; margin-top:.35rem; font-weight:700; color:var(--accent); }
          .gd .sum{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; margin-top:.9rem; font-size:.74rem; font-weight:700; color:var(--ink); }
          .gd .sum span{ background:var(--panel); border:1px solid var(--line); border-radius:7px; padding:.25rem .5rem; }

          /* scene 3 — settings */
          .gd .tabs{ display:flex; gap:.2rem; flex-wrap:wrap; border-bottom:1px solid var(--line); margin-bottom:.7rem; }
          .gd .tabs span{ font-size:.68rem; font-weight:600; color:var(--soft); padding:.3rem .55rem; border-radius:6px 6px 0 0; }
          .gd .tabs span.on{ color:var(--accent); border-bottom:2px solid var(--accent); }
          .gd .fs{ border:1px solid var(--line); border-radius:10px; padding:.55rem .75rem .7rem; margin-bottom:.6rem; position:relative; }
          .gd .fs .lg{ font-size:.64rem; font-weight:700; color:var(--accent-ink); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; }
          .gd .fs p{ font-size:.66rem; color:var(--faint); margin:0 0 .5rem; }
          .gd .radio{ font-size:.74rem; margin:.15rem 1rem .15rem 0; }
          .gd .calc{ display:inline-flex; gap:.35rem; align-items:center; font-size:.74rem; font-weight:700; background:var(--panel); border-radius:8px; padding:.3rem .55rem; }

          /* scene 7 — the Payments page form */
          .gd .np{ border:1px solid var(--line); border-radius:10px; padding:.5rem .7rem .6rem; background:var(--surface); }
          .gd .np .sm{ color:var(--accent); font-weight:700; font-size:.76rem; padding-bottom:.4rem; border-bottom:1px solid var(--line); margin-bottom:.5rem; }
          .gd .npg{ display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:.4rem .5rem; }
          .gd .npg .f label{ display:block; font-size:.56rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.15rem; }
          .gd .npg .inp, .gd .npg .selectbox{ width:100%; min-width:0; box-sizing:border-box; height:26px; font-size:.68rem; padding:.15rem .4rem; }
          .gd .npg .selectbox{ white-space:nowrap; overflow:hidden; }
          .gd .npg .f2{ grid-column:span 2; }
          .gd .depnote{ font-size:.62rem; color:#92400e; margin-top:.25rem; line-height:1.35; }
          :root[data-theme="dark"] .gd .depnote{ color:#fbbf24; }
          .gd .mini{ display:inline-flex; gap:.6rem; align-items:center; border:1px solid var(--line); border-radius:8px; padding:.35rem .6rem; font-size:.7rem; margin-bottom:.6rem; }

          /* scene 12 — email */
          .gd .mail{ border:1px solid var(--line); border-radius:10px; background:var(--surface); box-shadow:var(--gd-shadow); max-width:30rem; overflow:hidden; }
          .gd .mail .mh{ background:var(--panel); padding:.4rem .65rem; font-size:.66rem; color:var(--soft); border-bottom:1px solid var(--line); line-height:1.5; }
          .gd .mail .mh b{ color:var(--ink); }
          .gd .mail .mb{ padding:.5rem .65rem; font-size:.7rem; color:var(--ink); line-height:1.5; }
          .gd .att{ display:inline-flex; align-items:center; gap:.45rem; border:1px solid var(--line); border-radius:8px; padding:.3rem .5rem; font-size:.68rem; font-weight:700; margin-top:.3rem; }
          .gd .pdf{ position:relative; width:2.2rem; height:2.8rem; border:1px solid var(--line); border-radius:4px; background:#fff; display:grid; place-items:center; }
          .gd .pdf b{ font-size:.42rem; color:#1f2937; }
          .gd .stamp{ position:absolute; right:-.6rem; bottom:-.3rem; font-size:.5rem; font-weight:800; color:var(--good); border:2px solid var(--good); border-radius:4px; padding:0 .2rem; background:var(--surface); }
          .gd .need{ display:flex; flex-direction:column; gap:.35rem; margin-top:.7rem; font-size:.72rem; color:var(--ink); }
          .gd .need > div{ display:flex; align-items:center; gap:.45rem; }

          /* scene 13 — confirm */
          .gd .cfm{ position:absolute; z-index:5; left:18%; top:28%; width:17rem; max-width:80%; background:var(--surface); border:1px solid var(--line);
                    border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.76rem; color:var(--ink); }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.6rem; }
          .gd .cfm .yes{ background:var(--err); color:#fff; border-radius:7px; padding:.28rem .6rem; font-size:.7rem; font-weight:700; }

          /* scene 14 — notes */
          .gd .note{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .note h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .note ul{ margin:0; padding-left:1rem; }
          .gd .tick.on{ background:var(--accent); color:#fff; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:minmax(0,1fr); } .gd .side{ display:none; } .gd .stage{ min-width:0; overflow:hidden; }
            .gd .sc{ min-height:440px; }
            .gd .three{ grid-template-columns:1fr; }
            .gd .rpg{ grid-template-columns:1fr 1fr; }
            .gd .npg{ grid-template-columns:1fr 1fr; } .gd .npg .f1{ grid-column:span 2; }
            .gd .tb.ord{ grid-template-columns:1.2fr 1fr 1fr; } .gd .tb.ord .xs{ display:none; }
            .gd .tb.pay{ grid-template-columns:1fr 1fr .9fr auto; } .gd .tb.pay .xs{ display:none; }
            .gd .cfm{ left:6%; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / orders / BEV-2026-0042</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a class="on">Orders</a><a>Payments</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="sct">One order&rsquo;s money</div>
                  <p class="scs">From the deposit to paid in full &mdash; and the receipt that goes out by itself.</p>
                  ' . $head('<span class="st paid">paid</span>') . '
                  <div class="pnl"><h5>Deposit</h5><div class="okline">&check; Deposit paid &pound;330.00 on 7 Oct 2026</div></div>
                  <div class="pnl"><h5>Payments</h5><div class="okline">&check; Fully paid (&pound;660.00)</div>
                    <div class="tb pay"><span class="h">Date</span><span class="h">Method</span><span class="h xs">Reference</span><span class="h r">Amount</span><span class="h"></span>
                      <span>21 Oct 2026</span><span>Card</span><span class="xs"></span><span class="r">&pound;330.00</span><span><i class="btnx">&times;</i></span></div></div>
                  <p class="scs">Press <b>&#9654; Play</b> below &mdash; fourteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — three homes -->
                <div class="sc" data-scene="1" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Where the money lives</div>
                  <p class="scs a-fade" style="--d:.5s">Three places &mdash; and they always agree.</p>
                  <div class="three">
                    <div class="home a-drop" style="--d:5s"><h4>&#128176; Deposit panel</h4>The money taken up front.<span class="where">On the order</span></div>
                    <div class="home a-drop" style="--d:9.5s"><h4>&#128183; Payments panel</h4>Everything paid after that.<span class="where">On the order, just below</span></div>
                    <div class="home a-drop" style="--d:13.5s"><h4>&#128202; Payments page</h4>Every payment, across the whole business.<span class="where">Retail &rarr; Payments</span></div>
                  </div>
                  <div class="sum a-rise" style="--d:17.5s"><span>Received = deposit + payments</span><span>Balance = total &minus; received</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:22s">&#128073; Following one order: BEV-2026-0042 &middot; &pound;660.00</span></div>
                </div>

                <!-- 2 — deposit on a quote -->
                <div class="sc" data-scene="2" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">On a quote: set the deposit due</div>
                  ' . $head('<span class="st sent">sent</span>') . '
                  <div class="pnl a-rise" style="--d:1.5s"><h5>Deposit</h5>
                    <p class="ptxt">The deposit due when the customer accepts. Leave it as your default, or type an override for this quote.</p>
                    <div class="frm"><label>Deposit due on acceptance &pound;</label>
                      <span class="inp a-ring" style="--d:9s">' . $swap('330.00', '<span class="a-type" style="--d:12.5s;--ts:6;--tt:.6s">200.00</span>', 12) . '</span>
                      <span class="btnp a-press" style="--d:15.5s">Save deposit</span>
                      <span class="sugg">Suggested 50%: <span class="lnk">&pound;330.00</span></span>
                      <span class="ovr a-pop" style="--d:17s">Override set</span></div>
                  </div>
                  <div class="okline a-pop" style="--d:16s;display:inline-block">Deposit set to &pound;200.00.</div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:24s">Nobody has paid anything yet &mdash; this only sets the figure</span></div>
                </div>

                <!-- 3 — default deposit -->
                <div class="sc" data-scene="3" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Your usual deposit</div>
                  <div class="tabs a-fade" style="--d:1s"><span>Company</span><span class="on">Quoting</span><span>Legal</span><span>Status colours</span><span>Suppliers</span></div>
                  <div class="fs a-rise" style="--d:3s"><div class="lg">Default deposit</div>
                    <p>Seeds the deposit figure on every quote the moment it moves into Accepted. Overrideable per quote.</p>
                    <span class="radio on a-ring" style="--d:8.5s"><span class="dot"></span>Percentage of total <span class="inp" style="min-width:3rem">50</span> %</span>
                    <span class="radio a-ring" style="--d:14s"><span class="dot"></span>Flat amount &pound; <span class="inp" style="min-width:3.5rem">0</span></span>
                  </div>
                  <div class="calc a-pop" style="--d:11s">&pound;660.00 &times; 50% = &pound;330.00</div>
                  <div class="chips"><span class="chip a-pop" style="--d:21s">&#9989; Filled in on every quote when it is accepted</span></div>
                </div>

                <!-- 4 — record the deposit paid -->
                <div class="sc" data-scene="4" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Accepted: record the deposit paid</div>
                  ' . $head($swap('<span class="st sent">sent</span>', '<span class="st ord">accepted</span>', 3)) . '
                  <div class="pnl a-rise" style="--d:5s"><h5>Deposit</h5>
                    <p class="ptxt">Enter the deposit the customer has paid.</p>
                    <div class="frm"><label>Deposit paid &pound;</label>
                      <span class="inp">' . $swap('<span class="ph">&nbsp;</span>', '330.00', 15.5) . '</span>
                      <span class="btnp a-press" style="--d:22.5s">Record deposit paid</span>
                      <span class="sugg">Suggested 50%: <span class="lnk a-ring" style="--d:11s">&pound;330.00</span></span></div>
                  </div>
                  <div class="a-move" style="--fx:30%;--fy:95%;--tx:68%;--ty:9.6rem;--d:12.5s;--md:2.2s">' . $ptr . '</div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:18s">Type what was really handed over &mdash; the balance is worked out from it</span></div>
                  <div class="okline a-pop" style="--d:23.5s;display:inline-block;margin-top:.6rem">Deposit of &pound;330.00 recorded as paid.</div>
                </div>

                <!-- 5 — deposit recorded; amend / mark unpaid -->
                <div class="sc" data-scene="5" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Recorded &mdash; and it can be changed</div>
                  <div class="pnl"><h5>Deposit</h5>
                    <div class="okline a-pop" style="--d:1s">&check; Deposit paid &pound;330.00 on 7 Oct 2026</div>
                    <div class="frm a-fade" style="--d:3s"><label>Amend &pound;</label><span class="inp a-ring" style="--d:7.5s">330.00</span><span class="btns a-ring" style="--d:9s">Save</span>
                      <span class="btns a-ring" style="--d:13.5s">Mark unpaid</span></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:8s">Wrong figure? Amend &rarr; Save</span>
                    <span class="chip a-pop" style="--d:14s">Recorded by mistake? Mark unpaid</span>
                  </div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:18.5s">&#128274; The deposit is only changed here, on the order</span></div>
                </div>

                <!-- 6 — the Orders list -->
                <div class="sc" data-scene="6" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">The Orders list keeps watch</div>
                  <div class="tb ord">
                    <span class="h">Quote #</span><span class="h xs">Customer</span><span class="h xs">Status</span><span class="h xs">Total</span>
                    <span class="h a-sel" style="--d:4.5s">Deposit</span><span class="h r a-sel" style="--d:10s">Outstanding</span>
                    <span class="a-fly" style="--d:1s">BEV-2026-0042</span><span class="xs a-fly" style="--d:1s">Emma Fletcher</span><span class="xs a-fly" style="--d:1s"><span class="st ord">ordered</span></span><span class="xs a-fly" style="--d:1s">&pound;660.00</span>
                    <span class="gr a-fly" style="--d:1s">&check; &pound;330.00 paid</span><span class="r a-fly" style="--d:1s"><b class="amlink">&pound;330.00</b></span>
                    <span class="a-fly" style="--d:1.6s">BEV-2026-0039</span><span class="xs a-fly" style="--d:1.6s">Raj Patel</span><span class="xs a-fly" style="--d:1.6s"><span class="st ord">ordered</span></span><span class="xs a-fly" style="--d:1.6s">&pound;1,240.00</span>
                    <span class="am a-fly" style="--d:1.6s">&pound;620.00 due</span><span class="r a-fly" style="--d:1.6s"><b class="amlink">&pound;1,240.00</b></span>
                    <span class="a-fly" style="--d:2.2s">BEV-2026-0035</span><span class="xs a-fly" style="--d:2.2s">Sue Walker</span><span class="xs a-fly" style="--d:2.2s"><span class="st paid">paid</span></span><span class="xs a-fly" style="--d:2.2s">&pound;480.00</span>
                    <span class="gr a-fly" style="--d:2.2s">&check; &pound;240.00 paid</span><span class="r gr a-fly" style="--d:2.2s"><b class="a-ring" style="--d:16s;border-radius:4px">&check; paid</b></span>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:6s">&check; paid, in green</span><span class="chip warn a-pop" style="--d:7.5s">still due, in amber</span>
                    <span class="chip a-pop" style="--d:12s">Outstanding = what is left on the whole job</span>
                  </div>
                </div>

                <!-- 7 — the Outstanding link -->
                <div class="sc" data-scene="7" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Click the amber figure</div>
                  <div class="mini a-fade" style="--d:.6s">BEV-2026-0042 &middot; Emma Fletcher <b class="amlink a-ring" style="--d:1.5s" title="Click to take a payment against this order">&pound;330.00</b></div>
                  <div class="a-move" style="--fx:70%;--fy:80%;--tx:16rem;--ty:3.1rem;--d:1s;--md:1.6s">' . $ptr . '</div>
                  <div class="np a-drop" style="--d:4.5s"><div class="sm">&#9662; + Record payment</div>
                    <div class="npg">
                      <div class="f f1"><label>Order (optional)</label><span class="selectbox a-ring" style="--d:7s">BEV-2026-0042 &mdash; Emma Fletcher (&pound;330.00 outstanding)</span>
                        <div class="depnote a-fade" style="--d:13s">&check; Deposit of &pound;330.00 is already recorded on this order &mdash; the amount above is the remaining balance, so don&rsquo;t re-enter the deposit.</div></div>
                      <div class="f"><label>Amount &pound;</label><span class="inp a-ring" style="--d:9.5s">330.00</span></div>
                      <div class="f"><label>Received on</label><span class="inp">07/10/2026</span></div>
                      <div class="f"><label>Method</label><span class="selectbox">Bank transfer</span></div>
                      <div class="f f2"><label>Received from (optional)</label><span class="inp"><span class="ph">Who paid &mdash; e.g. customer name</span></span></div>
                      <div class="f"><label>Reference (optional)</label><span class="inp"><span class="ph">e.g. cheque #&hellip;</span></span></div>
                    </div>
                    <div class="frm" style="margin-top:.5rem"><span class="btnp a-press" style="--d:21.5s">Save payment</span><span class="btns">Cancel</span></div>
                  </div>
                </div>

                <!-- 8 — Take payment on the order -->
                <div class="sc" data-scene="8" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Or take it on the order itself</div>
                  <div class="qsb a-fade" style="--d:1s"><b>BEV-2026-0042</b> <span class="tp a-ring" style="--d:4.5s">&#128183; Take payment</span><span class="tot">Total &pound;660.00</span></div>
                  <div class="a-move" style="--fx:60%;--fy:90%;--tx:9rem;--ty:3.3rem;--d:4s;--md:1.6s">' . $ptr . '</div>
                  <div class="pnl a-rise" style="--d:7.5s"><h5>Payments</h5>
                    <div class="amb a-pop" style="--d:10s">Outstanding: &pound;330.00 <small>of &pound;660.00</small></div>
                    <div class="rpc a-rise" style="--d:14s"><h6>&#128183; Record a new payment</h6>
                      <p>Outstanding amount pre-filled. Adjust if it&rsquo;s a part-payment, then click <b>Record payment</b>.</p>
                      <div class="rpg">
                        <div class="f"><label>Amount &pound;</label><span class="inp a-ring" style="--d:17.5s">330.00</span></div>
                        <div class="f"><label>Date received</label><span class="inp">07/10/2026</span></div>
                        <div class="f"><label>Method</label><span class="selectbox">Bank transfer</span></div>
                        <div class="f"><label>Reference (optional)</label><span class="inp"><span class="ph">e.g. cheque #&hellip;</span></span></div>
                      </div>
                    </div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:21.5s">&#128666; Handy for taking the balance at the door</span></div>
                </div>

                <!-- 9 — the four boxes -->
                <div class="sc" data-scene="9" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Four boxes, then Record payment</div>
                  <div class="rpc a-rise" style="--d:.8s"><h6>&#128183; Record a new payment</h6>
                    <p>Outstanding amount pre-filled. Adjust if it&rsquo;s a part-payment, then click <b>Record payment</b>.</p>
                    <div class="rpg">
                      <div class="f"><label>Amount &pound;</label><span class="inp a-ring" style="--d:3s">330.00</span></div>
                      <div class="f"><label>Date received</label><span class="inp a-ring" style="--d:6s">' . $swap('07/10/2026', '06/10/2026', 9.5) . '</span></div>
                      <div class="f ddwrap"><label>Method</label><span class="selectbox a-ring" style="--d:12s">' . $swap('Bank transfer', 'Cheque', 17) . '</span>
                        <div class="dd a-mid" style="--d:13s;--d2:17s">' . $methodList . '</div></div>
                      <div class="f"><label>Reference (optional)</label><span class="inp a-ring" style="--d:19s"><span class="a-type" style="--d:20.5s;--ts:12;--tt:1s">Cheque 100234</span></span></div>
                    </div>
                    <div class="go"><span class="btnp a-press" style="--d:24.5s">&check; Record payment</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:22.5s">&#128269; A reference finds it on the bank statement later</span></div>
                </div>

                <!-- 10 — part-payments -->
                <div class="sc" data-scene="10" data-len="18">
                  <div class="sct a-fade" style="--d:.2s">Part-payments add up</div>
                  <div class="pnl"><h5>Payments</h5>
                    <div class="amb">Outstanding: ' . $swap('&pound;330.00', '&pound;130.00', 9) . ' <small>of &pound;660.00</small></div>
                    <div class="tb pay a-rise" style="--d:7.5s"><span class="h">Date</span><span class="h">Method</span><span class="h xs">Reference</span><span class="h r">Amount</span><span class="h"></span>
                      <span>7 Oct 2026</span><span>Card</span><span class="xs"></span><span class="r">&pound;200.00</span><span><i class="btnx">&times;</i></span></div>
                    <div class="rpc"><div class="rpg">
                      <div class="f"><label>Amount &pound;</label><span class="inp a-ring" style="--d:2.5s">' . $swap('330.00', '<span class="a-type" style="--d:4s;--ts:6;--tt:.6s">200.00</span>', 3.5) . '</span></div>
                      <div class="f"><label>Date received</label><span class="inp">07/10/2026</span></div>
                      <div class="f"><label>Method</label><span class="selectbox">Card</span></div>
                      <div class="f"><span class="btnp a-press" style="--d:6s">&check; Record payment</span></div>
                    </div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:14s">Record as many as you need</span><span class="chip a-pop" style="--d:17s">The balance comes down each time</span></div>
                </div>

                <!-- 11 — settles itself -->
                <div class="sc" data-scene="11" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">It settles itself</div>
                  ' . $head($swap('<span class="st ord">ordered</span>', '<span class="st paid a-ring" style="--d:5s">paid</span>', 4)) . '
                  <div class="pnl"><h5>Payments</h5>
                    <div class="bannerbox">
                      <div class="amb a-out" style="--d:3.5s">Outstanding: &pound;130.00 <small>of &pound;660.00</small></div>
                      <div class="okline a-fade" style="--d:3.5s">&check; Fully paid (&pound;660.00)</div>
                    </div>
                    <div class="tb pay"><span class="h">Date</span><span class="h">Method</span><span class="h xs">Reference</span><span class="h r">Amount</span><span class="h"></span>
                      <span>9 Oct 2026</span><span>Bank transfer</span><span class="xs">BACS 5512</span><span class="r">&pound;130.00</span><span><i class="btnx">&times;</i></span>
                      <span>7 Oct 2026</span><span>Card</span><span class="xs"></span><span class="r">&pound;200.00</span><span><i class="btnx">&times;</i></span></div>
                    <div class="rpc shrink" style="--d:12s"><h6>&#128183; Record a new payment</h6><p>Outstanding amount pre-filled&hellip;</p></div>
                  </div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:7s">No &ldquo;mark as paid&rdquo; button to press</span><span class="chip a-pop" style="--d:19s">&#127981; The making carries on as before</span></div>
                </div>

                <!-- 12 — the receipt -->
                <div class="sc" data-scene="12" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">The receipt goes out by itself</div>
                  <div class="mail a-drop" style="--d:1.5s">
                    <div class="mh">To: <b>emma.fletcher@example.com</b><br>Subject: <b>Receipt BEV-2026-0042 &mdash; paid in full &middot; Beverley Blinds</b></div>
                    <div class="mb">Hello Emma Fletcher,<br>Thank you &mdash; your payment for BEV-2026-0042 has been received in full, so your account is now settled. Your receipt is attached.
                      <div><span class="att a-pop" style="--d:4.5s"><span class="pdf"><b>Receipt</b><i class="stamp a-stamp" style="--d:6s">PAID</i></span>Receipt_BEV-2026-0042.pdf</span></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:7.5s">Sent once, and only once</span></div>
                  <div class="need">
                    <div class="a-fly" style="--d:12s"><span class="tick on">&#10003;</span> A proper email address on the order</div>
                    <div class="a-fly" style="--d:16s"><span class="tick on a-ring" style="--d:17s">&#10003;</span> <span>Settings &rarr; <b>Paid-in-full receipt</b>: <em>Email a receipt when an order is paid in full</em></span></div>
                  </div>
                  <div class="chips"><span class="chip bad a-pop" style="--d:24s">No email address = no receipt, and no warning</span></div>
                </div>

                <!-- 13 — put a mistake right -->
                <div class="sc" data-scene="13" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Put a mistake right</div>
                  ' . $head($swap('<span class="st paid">paid</span>', '<span class="st ord">ordered</span>', 14)) . '
                  <div class="pnl"><h5>Payments</h5>
                    <div class="bannerbox">
                      <div class="okline a-out" style="--d:13s">&check; Fully paid (&pound;660.00)</div>
                      <div class="amb a-fade" style="--d:13s">Outstanding: &pound;130.00 <small>of &pound;660.00</small></div>
                    </div>
                    <div class="tb pay"><span class="h">Date</span><span class="h">Method</span><span class="h xs">Reference</span><span class="h r">Amount</span><span class="h"></span>
                      <span class="shrink" style="--d:11.5s">9 Oct 2026</span><span class="shrink" style="--d:11.5s">Bank transfer</span><span class="xs shrink" style="--d:11.5s">BACS 5512</span><span class="r shrink" style="--d:11.5s">&pound;130.00</span><span class="shrink" style="--d:11.5s"><i class="btnx a-ring" style="--d:4s">&times;</i></span>
                      <span>7 Oct 2026</span><span>Card</span><span class="xs"></span><span class="r">&pound;200.00</span><span><i class="btnx">&times;</i></span></div>
                  </div>
                  <div class="a-move" style="--fx:40%;--fy:95%;--tx:calc(100% - 2.2rem);--ty:9.4rem;--d:2.5s;--md:1.8s"><span class="a-out" style="--d:12s;display:block">' . $ptr . '</span></div>
                  <div class="cfm a-mid" style="--d:6s;--d2:11s">Delete this payment?<div class="act"><span class="btns">Cancel</span><span class="yes a-press" style="--d:9.5s">Yes, continue</span></div></div>
                  <div class="chips"><span class="chip warn a-pop" style="--d:19s">Your record only &mdash; the money is still in the bank</span><span class="chip a-pop" style="--d:24s">Change an amount: <b>Edit</b> on the Payments page</span></div>
                </div>

                <!-- 14 — who sees it -->
                <div class="sc" data-scene="14" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Who sees all this</div>
                  <div class="three">
                    <div class="note a-rise" style="--d:3s"><h4>&#11088; Accounts &mdash; Gold plan</h4><ul><li>Payments panel</li><li>Outstanding column</li><li>Payments page</li></ul></div>
                    <div class="note a-rise" style="--d:10s"><h4>Without it</h4>The <b>Deposit</b> panel still works, and an order can still settle to <b>paid</b> on its deposit.</div>
                    <div class="note a-rise" style="--d:16s"><h4>Per person</h4><span class="tick on a-ring" style="--d:19s">&#10003;</span> <b>Can see money</b><br>on their user, in <b>Setup &rarr; Users</b>. Without it, the money is hidden.</div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>There are <b>two panels and one page</b>. On the order itself (open it from <b>Retail &rarr; Orders</b>) you get a
             <b>Deposit</b> panel and, if your plan includes <b>Accounts</b>, a <b>Payments</b> panel below it &mdash; the sticky bar at
             the top even has a <b>&#128183; Take payment</b> link that jumps straight down to it. Then there&rsquo;s the whole-business
             view: <b>Retail &rarr; Payments</b> in the left-hand menu, headed <b>Payments</b> with <em>&ldquo;Payments received against
             your orders.&rdquo;</em> underneath (see <b>The Payments page</b> guide). One sum drives them all &mdash;
             <em>received = deposit + payments</em>, <em>balance = total &minus; received</em> &mdash; so the figures always agree, and the
             deposit is never counted twice.</p>

          <ul class="steps">
            <li><b>The deposit, before the customer accepts.</b> While it is still a quote the Deposit panel reads <em>&ldquo;The deposit due
                when the customer accepts. Leave it as your default, or type an override for this quote.&rdquo;</em> &mdash; a box labelled
                <b>Deposit due on acceptance &pound;</b> and a <b>Save deposit</b> button (<code>Deposit set to &pound;200.00.</code>). That only
                <b>sets the figure</b>; it does not say anyone has paid. Type a one-off amount and a small <b>Override set</b> note appears.
                Empty the box and save to clear it (<code>Deposit cleared.</code>). The deposit can&rsquo;t be more than the total:
                <code>The deposit can&rsquo;t be more than the quote total (&pound;660.00).</code></li>
            <li><b>Where the usual figure comes from.</b> <b>Setup &rarr; Settings &rarr; Quoting &rarr; Default deposit</b>: two radio buttons,
                <b>Percentage of total</b> (50 to begin with) or <b>Flat amount</b> in pounds. It <em>&ldquo;Seeds the deposit figure on every
                quote the moment it moves into Accepted. Overrideable per quote.&rdquo;</em> The suggestion beside the deposit box follows it:
                <em>&ldquo;Suggested 50%:&rdquo;</em> for a percentage, plain <em>&ldquo;Suggested deposit&rdquo;</em> for a flat amount.</li>
            <li><b>The deposit, once it&rsquo;s an order.</b> The panel changes to <em>&ldquo;Enter the deposit the customer has paid.&rdquo;</em>
                with a box labelled <b>Deposit paid &pound;</b> and a <b>Record deposit paid</b> button. The suggested figure sits beside it as a
                blue link &mdash; click it to drop it in, or type over it. <b>Type what was really handed over</b>: the balance is worked out
                from it. You get <code>Deposit of &pound;330.00 recorded as paid.</code> and a green line,
                <em>&ldquo;&check; Deposit paid &pound;330.00 on 7 Oct 2026&rdquo;</em>.</li>
            <li><b>Changing a recorded deposit.</b> Under the green line: <b>Amend &pound;</b> with a <b>Save</b> button for a wrong figure, and
                <b>Mark unpaid</b> (<code>Deposit marked unpaid.</code>) to take it back off. The deposit is <b>only</b> changed here, on the
                order &mdash; on the Payments page it shows, shaded green, with <em>&ldquo;managed on the order&rdquo;</em> where the buttons
                would be.</li>
            <li><b>Finding what&rsquo;s still owed.</b> On <b>Retail &rarr; Orders</b>, the <b>Deposit</b> column shows
                <em>&ldquo;&check; &pound;330.00 paid&rdquo;</em> in green, <em>&ldquo;&pound;620.00 due&rdquo;</em> in amber, or a dash when
                there is no deposit. <b>Outstanding</b> (Accounts only) shows what is left on the whole job: an <b>amber figure with a dashed
                underline</b>, or a green <em>&ldquo;&check; paid&rdquo;</em> when settled, or a blue <em>&ldquo;+&pound;5.00&rdquo;</em>
                (Overpaid) if too much has gone in.</li>
            <li><b>The Outstanding link.</b> The amber figure is a link (<em>&ldquo;Click to take a payment against this order&rdquo;</em>).
                It opens the Payments page with the <b>+ Record payment</b> box open, the order chosen and the amount already set to the
                balance &mdash; the deposit already taken off, as the amber note says: <em>&ldquo;&check; Deposit of &pound;330.00 is already
                recorded on this order &mdash; the amount above is the remaining balance, so don&rsquo;t re-enter the deposit.&rdquo;</em>
                Check it and press <b>Save payment</b> (<code>Payment recorded: &pound;330.00.</code>).</li>
            <li><b>Taking the balance on the order.</b> <b>&#128183; Take payment</b> in the top bar jumps to the <b>Payments</b> panel. It
                shows an amber <em>&ldquo;Outstanding: &pound;330.00 of &pound;660.00&rdquo;</em> and a card headed
                <b>&#128183; Record a new payment</b>: <em>&ldquo;Outstanding amount pre-filled. Adjust if it&rsquo;s a part-payment, then click
                Record payment.&rdquo;</em> Four boxes &mdash; <b>Amount &pound;</b>, <b>Date received</b> (today), <b>Method</b> (Cash, Card,
                Bank transfer, Cheque, PayPal, Stripe, GoCardless, Other &mdash; Bank transfer is pre-picked) and <b>Reference (optional)</b>
                &mdash; then <b>&check; Record payment</b>. Put a cheque number or bank reference in <b>Reference</b> so you can find the money on
                the statement.</li>
            <li><b>Part-payments.</b> Type the smaller figure. Each payment gets its own row in the panel&rsquo;s table (Date, Method,
                Reference, Amount) and the outstanding figure comes down. Record as many as you need.</li>
            <li><b>It settles itself.</b> The moment the deposit plus the payments <b>cover the total</b>, the order marks itself
                <b>paid</b> &mdash; there is no &ldquo;mark as paid&rdquo; button. The banner turns green, <em>&ldquo;&check; Fully paid
                (&pound;660.00)&rdquo;</em>, and the record-a-payment card disappears. Going paid doesn&rsquo;t stop the making: the factory
                side carries on as before. A deposit alone can settle an order too, if it covers the total.</li>
            <li><b>The receipt.</b> At that moment the customer is emailed a receipt: subject <em>&ldquo;Receipt BEV-2026-0042 &mdash; paid in
                full &middot; Beverley Blinds&rdquo;</em> (your company name), with the order attached as a PDF headed <b>Receipt</b>,
                <b>Receipt_BEV-2026-0042.pdf</b>. It goes <b>once only</b> per order. It needs a valid email address on the order, and
                <b>Setup &rarr; Settings &rarr; Quoting &rarr; Paid-in-full receipt &rarr; &ldquo;Email a receipt when an order is paid in
                full&rdquo;</b> ticked (it is unless someone unticks it). No email address means no receipt, with no warning.</li>
            <li><b>Putting a mistake right.</b> Each payment row on the order has a red <b>&times;</b>; it asks <em>&ldquo;Delete this
                payment?&rdquo;</em> (<b>Cancel</b> / <b>Yes, continue</b>), then <code>Payment deleted.</code> To change a payment&rsquo;s
                amount, date, method or order instead, use <b>Edit</b> on the <b>Payments</b> page. Taking money back off a paid order &mdash;
                deleting a payment, or <b>Mark unpaid</b> on the deposit &mdash; steps it <b>back to the status it had before it went
                paid</b>. Deleting only changes your record; the money is still in the bank.</li>
          </ul>

          <div class="oops"><b>What it says when it won&rsquo;t go &mdash; and what to do:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>A deposit can be recorded once the quote has been accepted.</code> &mdash; set the figure with <b>Save deposit</b> now,
                   and record it as paid after they accept.</li>
               <li><code>Enter the deposit amount the customer paid (more than &pound;0).</code> / <code>Set a deposit amount first &mdash; a
                   &pound;0 deposit can&rsquo;t be marked paid.</code> &mdash; a nought deposit is not a payment. If nothing was paid up front,
                   record nothing.</li>
               <li><code>Deposit must be a non-negative number.</code> &mdash; letters or a minus sign in the deposit box.</li>
               <li><code>Amount must be at least 1p.</code> / <code>Received date is required (YYYY-MM-DD).</code> &mdash; the two required
                   boxes on a payment.</li>
               <li><code>That amount looks wrong &mdash; payments over &pound;1,000,000 aren&rsquo;t accepted.</code> &mdash; check for an extra
                   nought.</li>
               <li><code>Payments can be recorded once the quote has been accepted.</code> &mdash; money is only taken on an order.</li>
               <li><code>The deposit is managed on the order &mdash; change it from the order&rsquo;s deposit panel.</code> &mdash; use
                   <b>Amend &pound;</b> or <b>Mark unpaid</b> on the order.</li>
               <li><code>This quote can no longer be edited.</code> &mdash; the quote is closed (declined, say).</li>
             </ul></div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Who sees what.</b> The <b>Payments</b> panel, the <b>Outstanding</b>
             column, the <b>Take payment</b> link and the <b>Retail &rarr; Payments</b> page come with the <b>Accounts</b> feature, part of
             the <b>Gold</b> plan. Without it the page shows <em>&ldquo;Accounts module not enabled&rdquo;</em> &mdash; the <b>Deposit</b>
             panel still works, and an order can still settle to paid on its deposit. Each person also needs <b>Can see money</b> ticked on
             their user (<b>Setup &rarr; Users</b>); without it the money is hidden from them, apart from setting the deposit due on a quote.
             A user without <b>View all customer jobs</b> can only take payments on orders they are booked onto, and gets
             <em>&ldquo;You don&rsquo;t have permission to record payments for that order.&rdquo;</em> otherwise.</div></div>',
        'script'  => [
            ['1', 'Where the money lives',          'Money on a job lives in three places, and they always agree. The Deposit panel, on the order, holds the money taken up front. The Payments panel, just below it, holds everything paid after that. And the Payments page, under Retail in the menu, shows every payment across the whole business. This guide follows one order, from its deposit to paid in full.', 1],
            ['2', 'On a quote: set the deposit due', 'While it is still a quote, the Deposit panel only sets the figure the customer will owe when they accept. Your usual deposit is already in the box. To ask for something different on this one quote, type the new figure, and click Save deposit. A small note saying Override set appears, so you can see it is not your usual amount. Nobody has paid anything yet.', 2],
            ['3', 'Your usual deposit',              'That usual figure comes from Settings, on the Quoting tab, under Default deposit. There are two choices. Percentage of total takes a share of the price, fifty percent to begin with. Flat amount asks for the same number of pounds every time. Pick the one that matches how you really take deposits. It fills in on every quote, the moment it is accepted.', 3],
            ['4', 'Accepted: record the deposit',    'Once the customer accepts, the Deposit panel asks a different question: enter the deposit the customer has paid. The suggested figure sits beside the box. Click it to drop it in, or type over it. Always type what was really handed over, not what you hoped for, because the balance is worked out from it. Then click Record deposit paid.', 4],
            ['5', 'Recorded, and it can be changed', 'A green line confirms it, with the amount and the date. Nothing here is set in stone. If the figure is wrong, change it in the Amend box, and click Save. If it was recorded by mistake, click Mark unpaid, and it comes straight back off. The deposit is only ever changed here, on the order.', 5],
            ['6', 'The Orders list keeps watch',     'Now the Orders list. Two money columns do the watching for you. Deposit shows a green tick with the amount paid, or the amount still due, in amber. Outstanding shows what is left to pay on the whole job. When a job is settled, it simply says paid, with a tick. One glance down the column shows who still owes you.', 6],
            ['7', 'Click the amber figure',          'That amber figure is also a link. Click it, and you land on the Payments page with the box already open. The order is chosen, and the amount is the balance, with the deposit already taken off. An amber note reminds you of that, so please do not type the deposit in again. Check the figure, and click Save payment.', 7],
            ['8', 'Or take it on the order',         'You can also take money on the order itself. The bar along the top has a Take payment link, which jumps straight down to the Payments panel. An amber line shows what is outstanding, out of the total. Below it is a card called Record a new payment, with the outstanding amount already filled in. Handy for taking the balance at the door.', 8],
            ['9', 'Four boxes, then Record payment', 'The card has four boxes. Amount is the money you were given. Date received starts on today, so change it if the money came in on another day. Method starts on bank transfer, and also offers cash, card, cheque and the rest. Reference is optional, but a cheque number or bank reference helps you find it on the statement later. Then click Record payment.', 9],
            ['10', 'Part-payments add up',           'If the customer pays only part of it, just type the smaller amount. Part-payments are perfectly fine. Each one gets its own line in the table, and the outstanding figure comes down each time. Record as many as you need, until nothing is left to pay.', 10],
            ['11', 'It settles itself',              'When the deposit and the payments cover the total, the order marks itself paid. There is no button to press. The banner turns green and says Fully paid, and the Record a new payment card disappears, because there is nothing left to take. Paying does not stop the making. The factory side carries on as before.', 11],
            ['12', 'The receipt goes out by itself', 'At that moment, the customer is emailed a receipt, with a copy of the order headed Receipt. It goes once, and only once. Two things must be true. The order needs a proper email address. And the Paid in full receipt box in Settings must be ticked, which it is, unless someone has turned it off. With no email address, nothing is sent, and nothing warns you.', 12],
            ['13', 'Put a mistake right',            'Made a mistake? Each payment in the table has a small red cross. Click it, and the app asks first: delete this payment? Say yes, and it is gone. If that leaves money owing, the order steps back out of paid, to whatever it was before. This only changes your record. The money is still in the bank. To change an amount instead, use Edit, on the Payments page.', 13],
            ['14', 'Who sees all this',              'Last, who sees all this. The Payments panel, the Outstanding column and the Payments page come with the Accounts feature, on the Gold plan. Without it, the Deposit panel still works, and an order can still settle on its deposit. And each person needs Can see money ticked on their user, in Setup, Users. Without it, the money is hidden from them.', 14],
        ],
];
