<?php
declare(strict_types=1);

/**
 * Guide: console-orders — "The Factory Console: dashboard and orders" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Audience 'factory': Factory Console users (factory_console_user()) and the
 * super-admin only — an ordinary business never sees it.
 *
 * Mirrors:
 *   - _partials/factory_console.php — why the console exists (an account's order
 *     is stored as THEIR quote; the console finds every placed order with a
 *     factory line), the stage keys/labels, where an order opens;
 *   - _partials/sidebar.php ($isFactoryConsole) — the console menu, its
 *     "Remakes (n to approve)" / "Calendar (n due)" labels, Trade accounts for
 *     the super-admin only;
 *   - auth/middleware.php — factory_console_user(), landing on
 *     /factory/dashboard.php, factory_console_redirect() from the old screens;
 *   - factory/dashboard.php — the tiles, the "not placed yet" line, Latest
 *     orders, Busiest accounts;
 *   - factory/orders.php — the chips, account picker, search, columns, invoice
 *     badge, REMAKE badge and the ↻ Remake link.
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step). Start times are
 * worked out from where a phrase falls in the voice-over ($at), at Alice's
 * ~13.6 characters a second, and data-len from the line's length ($len).
 */

// ── The voice-over first: scene timings are read from it ───────────────
$S = [
    ['1', 'Why there is a Factory Console', 'When a trade account places an order on their own portal, it is stored as their order, not ours. So the old sales screens, which only listed the factory\'s own jobs, could never show it. The Factory Console fixes that. It finds every order with our blinds on it, whoever placed it, and puts them all in one place for the office.', 1],
    ['2', 'Signing in', 'The console is for the office staff of the factory. When you sign in, you land straight on the console Dashboard, and the menu says Factory Console under the logo. If you open one of the old screens, like the sales dashboard, the Pipeline or the old orders list, it simply takes you to the console version instead.', 2],
    ['3', 'The Work menu', 'The menu has its own sections. Work comes first. Dashboard is your home page. Orders is the one list of everything placed with us. Remakes is where faults and remakes are handled, and Calendar is the office\'s shared calendar. When something needs you, the menu says so. For example, Remakes, two to approve, or Calendar, three due.', 3],
    ['4', 'Production and Accounts', 'Under Production are the factory screens you already know: Incoming orders, the Floor, and Dispatch. Under Accounts are Invoices, Bank, Statements and Commissions. Trade accounts and Profit are there too, but only for the super admin. Setup and Platform sit at the bottom, for the people allowed to use them.', 4],
    ['5', 'The four stage tiles', 'The top row of the Dashboard counts orders by stage. New means an order has come in, but has not been received on the floor yet. Received means it is in, but not started. In production means it is being made. Ready to dispatch means everything is made and in. Click a tile to open it. New opens Incoming orders, and Ready to dispatch opens Dispatch.', 5],
    ['6', 'Quotes not placed yet', 'Just under those tiles, you may see a line saying how many of our own trade quotes are not placed yet. These are quotes the office has keyed in, which have not been turned into orders. Click the line, and you see them in the Orders list, under Not placed.', 6],
    ['7', 'The second row', 'The second row is the rest of the day at a glance. Calendar shows how many reminders and callbacks are due now. Remakes shows how many are in progress, and how many are waiting for approval. Blinds to make counts the blinds on orders that are not ready yet. And Out today counts the delivery notes dispatched today.', 7],
    ['8', 'This week so far', 'Two more tiles look at the week so far. Orders this week counts the orders placed since Monday. Invoiced this week is the net value of the invoices raised since Monday. Underneath it, owed is everything still unpaid on our invoices. Click it to open Invoices.', 8],
    ['9', 'Latest orders and busiest accounts', 'Below the tiles are two panels. Latest orders shows the eight newest orders, with the account, the number of blinds, and the stage. Click an order number to open it, or See all for the whole list. Busiest accounts shows who has ordered the most over the last thirty days, by value.', 9],
    ['10', 'One Orders list', 'Orders, in the Work menu, is the heart of the console. It is one list of every order placed with us, whoever placed it. From their portal, as a direct order, or keyed in here by the office. To key one in yourself, use the New order button at the top right.', 10],
    ['11', 'The stage chips', 'Across the top is a row of chips, each with a count. Open is everything not dispatched yet, and that is where the list starts. Then come Not placed, New, Received, In production, Ready and Dispatched. All shows every order. Click a chip to filter the list.', 11],
    ['12', 'Finding an order', 'To find one order, use the account picker and the search box. Choose an account, and you see only their orders. Or type in the search box. An order number, the account, their reference, the name for the labels, or an invoice number all work. Then click Search.', 12],
    ['13', 'Reading a row', 'Each row shows the order number, the account, their reference, the date, how many of our blinds are on it, and its value. The stage is the coloured pill. Once an order is invoiced, its invoice number sits beside the stage. And a purple REMAKE badge marks an order that is a remake of an earlier one.', 13],
    ['14', 'Opening an order', 'Click the order number to open it. An order an account placed opens in the factory\'s Edit order screen. Our own orders, and quotes not placed yet, open in the quote builder. Beside the stage of every placed order is a Remake link. That is the quickest way to raise a remake, and the Remakes guide explains the rest.', 14],
];
$len = static fn (int $n): string => (string) round(mb_strlen($S[$n - 1][2]) / 13.6, 1);
/** Seconds into line $n at which $phrase is spoken (+ $add). */
$at = static function (int $n, string $phrase, float $add = 0.0) use ($S): string {
    $p = mb_strpos($S[$n - 1][2], $phrase);
    if ($p === false) { error_log('console-orders guide: "' . $phrase . '" not in line ' . $n); $p = 0; }
    return (string) round($p / 13.6 + $add, 1);
};
$d = static fn (int $n, string $phrase, float $add = 0.0): string => '--d:' . $at($n, $phrase, $add) . 's';

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// ── Stage pills, as fc_stage_meta() colours them ──────────────────────
$PILL = [
    'notplaced'     => ['Not placed',    '#475569', '#e2e8f0'],
    'new'           => ['New',           '#b91c1c', '#fee2e2'],
    'confirmed'     => ['Received',      '#5b6b7f', '#e6ebf1'],
    'in_production' => ['In production', '#b5730f', '#f7ecd6'],
    'ready'         => ['Ready',         '#245ea3', '#dde8f6'],
    'dispatched'    => ['Dispatched',    '#0d7a67', '#d6ece6'],
];
$pill = static fn (string $k, string $cls = '', string $style = ''): string =>
    '<span class="fpill ' . $cls . '" style="color:' . $PILL[$k][1] . ';background:' . $PILL[$k][2] . ';' . $style . '">' . $PILL[$k][0] . '</span>';
$rmb = '<span class="rmb">REMAKE</span>';

/**
 * The console menu, drawn in the stage (the demo sidebar is hidden on phones).
 * $ring = [item label => [animation class, style]] for items to animate.
 */
$menu = static function (array $ring = [], string $rem = 'Remakes', string $cal = 'Calendar') : string {
    $sec = [
        'Work'       => ['Dashboard', 'Orders', $rem, $cal],
        'Production' => ['Incoming orders', 'Floor', 'Dispatch'],
        'Accounts'   => ['Trade accounts', 'Invoices', 'Bank', 'Statements', 'Commissions', 'Profit'],
        'Setup'      => [],
        'Platform'   => [],
    ];
    $h = '<div class="cmnu"><div class="lg">Your<b>Blinds</b></div><small class="tag">FACTORY CONSOLE</small>';
    foreach ($sec as $name => $items) {
        [$c, $s] = $ring['§' . $name] ?? ['', ''];
        $h .= '<div class="nh"><span class="' . $c . '" style="' . $s . '">' . $name . ($items ? '' : ' &#9662;') . '</span></div>';
        foreach ($items as $it) {
            [$c, $s] = $ring[$it] ?? ['', ''];
            $h .= '<span class="it ' . $c . '" style="' . $s . '">' . $it . ($it === 'Trade accounts' ? ' <i class="su">super-admin</i>' : '') . '</span>';
        }
    }
    return $h . '</div>';
};

/** A dashboard tile. */
$tile = static fn (string $label, string $val, string $sub, string $col = 'var(--line)', string $cls = '', string $style = ''): string =>
    '<div class="ftile ' . $cls . '" style="--fc:' . $col . ';' . $style . '"><div class="fl">' . $label . '</div><div class="fv">' . $val . '</div><div class="fs">' . $sub . '</div></div>';

// ── Orders list rows ──────────────────────────────────────────────────
$ROWS = [
    // number, sub (label name), account, their ref, date, blinds, value, stage, invoice, remake?
    ['ABC-2026-0041',    'Mrs Patel',  'ABC Blinds',       'PO 4471', '8 Oct 2026', 6, '&pound;412.80', 'new',           '',                   false],
    ['HUG-2026-0012',    '',           'Hughes Interiors', 'Kitchen', '7 Oct 2026', 2, '&pound;101.15', 'in_production', '',                   false],
    ['ABC-2026-0038-R1', '',           'ABC Blinds',       'PO 4402', '6 Oct 2026', 1, '&pound;0.00',   'ready',         '',                   true],
    ['CST-2026-0007',    'The Lodge',  'Coastal Shutters', 'CS-118',  '2 Oct 2026', 4, '&pound;286.40', 'dispatched',    'INV-CST-2026-0007', false],
    ['BEV-2026-0090',    '',           'One-off sale',     '',        '1 Oct 2026', 3, '&pound;164.00', 'notplaced',     '',                   false],
];
$row = static function (array $r, string $cls = '', string $style = '') use ($pill, $rmb): string {
    [$num, $sub, $acc, $ref, $date, $bl, $val, $stage, $inv, $rm] = $r;
    return '<div class="orow ' . $cls . '" style="' . $style . '">'
        . '<span class="on"><b class="lnk">' . $num . '</b>' . ($rm ? ' ' . $rmb : '') . ($sub !== '' ? '<small>' . $sub . '</small>' : '') . '</span>'
        . '<span>' . $acc . '</span><span class="hs">' . $ref . '</span><span class="hs">' . $date . '</span>'
        . '<span class="n">' . $bl . '</span><span class="n hs">' . $val . '</span>'
        . '<span class="st">' . $pill($stage) . ($inv !== '' ? '<span class="finv">' . $inv . '</span>' : '')
        . ($stage !== 'notplaced' ? '<span class="frm">&#8635; Remake</span>' : '') . '</span></div>';
};
$thead = '<div class="orow th"><span>Order</span><span>Account</span><span class="hs">Their ref</span><span class="hs">Date</span><span class="n">Blinds</span><span class="n hs">Value</span><span>Stage</span></div>';

/** The stage chips; $active = key, or [key => delay] to move the active chip through them. */
$CHIPS = ['open' => ['Open', 31], 'notplaced' => ['Not placed', 1], 'new' => ['New', 7], 'confirmed' => ['Received', 5],
          'in_production' => ['In production', 12], 'ready' => ['Ready', 6], 'dispatched' => ['Dispatched', 148], 'all' => ['All', 179]];
$chips = static function ($active = 'open', array $sel = []) use ($CHIPS): string {
    $h = '<div class="fchips">';
    foreach ($CHIPS as $k => [$l, $n]) {
        if (isset($sel[$k])) {
            $h .= '<span class="fchip a-sel" style="--d:' . $sel[$k] . 's">' . $l . ' <i>' . $n . '</i></span>';
        } else {
            $h .= '<span class="fchip' . ($k === $active ? ' on' : '') . '">' . $l . ' <i>' . $n . '</i></span>';
        }
    }
    return $h . '</div>';
};

$filters = static fn (string $acct, string $q, string $btn = '') =>
    '<div class="ffil"><span class="ib grow">' . $q . '</span><span class="ib sel">' . $acct . '</span><span class="btns ' . $btn . '">Search</span></div>';
$ph = static fn (string $t): string => '<span class="gph">' . $t . '</span>';

$dashHead = '<div class="dh"><div><div class="pt">Good morning, Sam</div><div class="psub">Friday 9 October 2026</div></div><span class="btns">All orders</span></div>';

return [
        'aud'     => 'factory',
        'section' => 'Factory Console',
        'title'   => 'The Factory Console: dashboard and orders',
        'eyebrow' => 'Factory Console',
        'v'       => 2,
        'blurb'   => 'Why the factory office has its own console, the menu, what each Dashboard tile counts, and the one Orders list — stage chips, account picker, search, invoice and REMAKE badges, and where an order opens.',
        'lede'    => 'The factory that makes the blinds is <b>trade-only</b>, so its office now signs in to its own
                      <b>Factory Console</b>. Its point is simple: an order a trade account places on <b>their</b> portal is stored as
                      <b>their</b> order, so the old sales screens never showed it &mdash; the console finds <b>every order with our blinds
                      on it</b>, whoever placed it. This guide walks through the menu, the <b>Dashboard</b> you land on, and the one
                      <b>Orders</b> list, <b>slowly</b>, one idea per chapter. To get there: just sign in &mdash; you land on
                      <b>Work &rarr; Dashboard</b>.',
        'open'    => '/factory/dashboard.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:390px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.68rem; color:var(--faint); margin:.1rem 0 0; }
          .gd .phint{ font-size:.66rem; color:var(--faint); margin:.15rem 0 .55rem; max-width:31rem; line-height:1.45; }
          .gd .dh{ display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; margin-bottom:.7rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .lnk{ color:var(--accent); font-weight:700; }
          .gd .arrow{ color:var(--faint); font-weight:800; font-size:1.1rem; align-self:center; text-align:center; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .card.good{ border-color:var(--good); } .gd .card.bad{ border-color:var(--err); }
          .gd .flow{ display:grid; grid-template-columns:1fr 1.3rem 1fr 1.3rem 1fr; gap:.3rem; align-items:start; }

          /* stage pills + badges */
          .gd .fpill{ display:inline-block; padding:.05rem .45rem; font-size:.6rem; font-weight:700; border-radius:999px; white-space:nowrap; }
          .gd .rmb{ display:inline-block; font-size:.54rem; font-weight:800; letter-spacing:.04em; border-radius:999px; padding:.04rem .42rem; color:#fff; background:#7c3aed; white-space:nowrap; vertical-align:1px; }
          .gd .finv{ display:inline-block; margin-left:.25rem; font-size:.54rem; font-weight:700; color:var(--soft); border:1px solid var(--border-strong,#c7ccd4); border-radius:999px; padding:0 .35rem; white-space:nowrap; }
          .gd .frm{ display:inline-block; margin-left:.3rem; font-size:.6rem; color:var(--accent); white-space:nowrap; border-radius:4px; }

          /* the console menu, drawn in the stage */
          .gd .cmnu{ background:#1f2937; border-radius:10px; padding:.6rem .55rem; color:#cbd5e1; width:12.5rem; }
          .gd .cmnu .lg{ font-weight:800; color:#fff; font-size:.88rem; } .gd .cmnu .lg b{ color:#5b9bff; }
          .gd .cmnu .tag{ display:block; font-size:.52rem; letter-spacing:.14em; color:#8aa0b2; margin:.05rem 0 .4rem; }
          .gd .cmnu .nh{ font-size:.52rem; letter-spacing:.12em; text-transform:uppercase; color:#8aa0b2; font-weight:700; margin:.4rem 0 .05rem .25rem; }
          .gd .cmnu .nh span{ display:inline-block; border-radius:4px; padding:0 .15rem; }
          .gd .cmnu .it{ display:block; font-size:.68rem; padding:.12rem .35rem; border-radius:6px; color:#e2e8f0; }
          .gd .cmnu .it .su{ font-style:normal; font-size:.5rem; color:#fbbf24; margin-left:.2rem; }
          .gd .gd-done .fchip.a-sel{ background:var(--panel) !important; color:var(--soft) !important; border-color:var(--line) !important; }
          .gd .cmnu .it.on{ background:rgba(91,155,255,.25); color:#fff; }
          .gd .mwrap{ display:grid; grid-template-columns:12.5rem 1fr; gap:1rem; align-items:start; }
          .gd .mnote{ display:flex; flex-direction:column; gap:.45rem; }

          /* dashboard tiles */
          .gd .fgrid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.5rem; margin-bottom:.6rem; }
          .gd .fgrid.g3{ grid-template-columns:repeat(3,minmax(0,1fr)); }
          .gd .fgrid.g2{ grid-template-columns:repeat(2,minmax(0,1fr)); max-width:26rem; }
          .gd .ftile{ background:var(--surface); border:1px solid var(--line); border-top:3px solid var(--fc); border-radius:10px; padding:.5rem .6rem; min-width:0; }
          .gd .ftile .fl{ font-size:.54rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .ftile .fv{ font-size:1.35rem; font-weight:800; color:var(--ink); line-height:1.15; font-variant-numeric:tabular-nums; }
          .gd .ftile .fs{ font-size:.58rem; color:var(--faint); line-height:1.3; }
          .gd .ftile .fs b{ font-weight:700; }
          .gd .goes{ display:block; font-size:.56rem; font-weight:700; color:var(--accent); margin-top:.25rem; }
          .gd .npl{ font-size:.7rem; color:var(--soft); margin:.1rem 0 .6rem; }
          .gd .npl b{ color:var(--accent); text-decoration:underline; border-radius:4px; }

          /* dashboard panels + tables */
          .gd .cols{ display:grid; grid-template-columns:3fr 2fr; gap:.6rem; align-items:start; }
          .gd .pnl{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); min-width:0; }
          .gd .pnl h5{ margin:0 0 .35rem; font-size:.76rem; color:var(--ink); display:flex; justify-content:space-between; gap:.4rem; }
          .gd .pnl h5 span{ font-size:.62rem; font-weight:600; color:var(--accent); } .gd .pnl h5 small{ font-size:.58rem; font-weight:400; color:var(--faint); }
          .gd .mt{ font-size:.62rem; }
          .gd .mt div{ display:grid; grid-template-columns:2fr 1.6fr .7fr 1.3fr; gap:.25rem; padding:.22rem 0; border-top:1px solid var(--line); align-items:center; color:var(--ink); }
          .gd .mt.b div{ grid-template-columns:2fr .8fr 1.2fr; }
          .gd .mt div:first-child{ border-top:0; font-weight:700; color:var(--soft); font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .mt .n{ text-align:right; font-variant-numeric:tabular-nums; }

          /* orders list */
          .gd .fchips{ display:flex; gap:.3rem; flex-wrap:wrap; margin:0 0 .5rem; }
          .gd .fchip{ display:inline-flex; gap:.25rem; align-items:center; padding:.18rem .5rem; font-size:.64rem; border-radius:999px; background:var(--panel); color:var(--soft); border:1px solid var(--line); }
          .gd .fchip i{ font-style:normal; opacity:.75; font-variant-numeric:tabular-nums; }
          .gd .fchip.on{ background:var(--accent); color:#fff; border-color:var(--accent); }
          .gd .gd-play .fchip.a-sel{ animation:coChip 3.2s ease calc(var(--d,0s) * var(--k,1)) both; }
          @keyframes coChip{ 0%{ background:var(--panel); color:var(--soft); } 8%,70%{ background:var(--accent); color:#fff; border-color:var(--accent); } 100%{ background:var(--panel); color:var(--soft); } }
          .gd .ffil{ display:flex; gap:.35rem; flex-wrap:wrap; margin:0 0 .5rem; align-items:center; }
          .gd .ib{ display:inline-flex; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.66rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.grow{ flex:1; min-width:11rem; }
          .gd .ib.sel{ padding-right:1.2rem; } .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.35rem; color:var(--faint); font-size:.64rem; }
          .gd .gph{ color:var(--faint); }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .otab{ border:1px solid var(--line); border-radius:10px; overflow:hidden; background:var(--surface); }
          .gd .orow{ display:grid; grid-template-columns:2.1fr 1.5fr 1fr 1fr .6fr .9fr 2fr; gap:.3rem; align-items:center; padding:.3rem .5rem;
                     border-top:1px solid var(--line); font-size:.62rem; color:var(--ink); }
          .gd .orow:first-child{ border-top:0; }
          .gd .orow.th{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .orow .n{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .orow .on small{ display:block; color:var(--faint); font-size:.56rem; }
          .gd .orow .st{ line-height:1.6; }
          .gd .orow .on b{ border-radius:4px; }
          .gd .tagn{ position:absolute; z-index:4; background:var(--surface); border:1px solid var(--accent); color:var(--ink); border-radius:8px;
                     padding:.25rem .5rem; font-size:.62rem; font-weight:700; box-shadow:var(--gd-shadow); white-space:nowrap; }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.4rem .6rem; font-size:.7rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; }
          .gd .scr{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--panel); font-size:.66rem; color:var(--soft); }
          .gd .scr b{ display:block; color:var(--ink); font-size:.76rem; margin-bottom:.2rem; }
          .gd .redir{ display:grid; grid-template-columns:auto 1.3rem auto; gap:.3rem .4rem; align-items:center; justify-content:start; font-size:.68rem; }

          @media (max-width:640px){
            .gd .sc{ min-height:560px; }
            .gd .two, .gd .cols, .gd .flow, .gd .mwrap{ grid-template-columns:1fr; }
            .gd .flow .arrow i{ display:inline-block; transform:rotate(90deg); font-style:normal; line-height:1; }
            .gd .fgrid, .gd .fgrid.g3{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .gd .orow{ grid-template-columns:2.2fr 1.6fr .6fr 2fr; }
            .gd .orow .hs{ display:none; }
            .gd .cmnu{ width:auto; }
            .gd .tagn{ white-space:normal; max-width:12rem; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / factory / dashboard</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>FACTORY CONSOLE</small>
                <div class="navh">Work</div>
                <a class="on">Dashboard</a><a>Orders</a><a>Remakes</a><a>Calendar</a>
                <div class="navh">Production</div>
                <a>Incoming orders</a><a>Floor</a><a>Dispatch</a>
                <div class="navh">Accounts</div>
                <a>Invoices</a><a>Bank</a><a>Statements</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $dashHead . '
                  <div class="fgrid">
                    ' . $tile('New', '3', 'Not received on the floor yet', '#b91c1c') . $tile('Received', '5', 'In, not started', '#5b6b7f')
                      . $tile('In production', '12', 'Being made', '#b5730f') . $tile('Ready to dispatch', '6', 'Everything made and in', '#245ea3') . '
                  </div>
                  <div class="otab">' . $thead . $row($ROWS[0]) . $row($ROWS[1]) . $row($ROWS[2]) . '</div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fourteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — why -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">Why there is a Factory Console</div>
                  <div class="flow" style="margin-top:.6rem">
                    <div class="card a-rise" style="' . $d(1, 'When a trade') . '"><h4>ABC Blinds &mdash; their portal</h4>
                      Order <b>ABC-2026-0041</b> placed<br><span class="chip a-pop" style="' . $d(1, 'it is stored') . ';margin-top:.35rem">stored as <b>their</b> order</span></div>
                    <div class="arrow a-fade" style="' . $d(1, 'So the old') . '"><i>&rarr;</i></div>
                    <div class="card bad a-rise" style="' . $d(1, 'So the old') . '"><h4>The old sales screens</h4>
                      Only listed the factory&rsquo;s own jobs<br><span class="chip bad a-pop" style="' . $d(1, 'could never') . ';margin-top:.35rem">&#10007; never showed it</span></div>
                    <div class="arrow a-fade" style="' . $d(1, 'The Factory Console fixes') . '"><i>&rarr;</i></div>
                    <div class="card good a-rise" style="' . $d(1, 'The Factory Console fixes') . '"><h4>The Factory Console</h4>
                      Every order with <b>our blinds</b> on it<br><span class="chip ok a-pop" style="' . $d(1, 'whoever placed') . ';margin-top:.35rem">&#10003; whoever placed it</span></div>
                  </div>
                  <div class="otab a-rise" style="' . $d(1, 'puts them all') . ';margin-top:.9rem">' . $thead . $row($ROWS[0], 'a-fly', $d(1, 'puts them all', .3)) . $row($ROWS[1], 'a-fly', $d(1, 'puts them all', .7)) . $row($ROWS[3], 'a-fly', $d(1, 'puts them all', 1.1)) . '</div>
                </div>

                <!-- 2 — signing in -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Signing in</div>
                  <div class="two" style="margin-top:.5rem">
                    <div>
                      <div class="card a-rise" style="' . $d(2, 'When you sign in') . '"><h4>Sign in</h4>
                        <div class="ib" style="width:100%;margin-bottom:.3rem">sam@factory</div>
                        <span class="btnp a-press" style="' . $d(2, 'When you sign in', 1.2) . '">Sign in</span></div>
                      <div class="a-rise" style="' . $d(2, 'you land straight') . ';margin-top:.6rem">' . $dashHead . '</div>
                      <div class="cmnu a-rise" style="' . $d(2, 'the menu says') . ';width:auto"><div class="lg">Your<b>Blinds</b></div><small class="tag a-ring" style="' . $d(2, 'Factory Console under') . ';display:inline-block;border-radius:4px;padding:0 .2rem">FACTORY CONSOLE</small></div>
                    </div>
                    <div class="scr a-rise" style="' . $d(2, 'If you open') . '"><b>Old screens take you to the console</b>
                      <div class="redir">
                        <span class="chip a-pop" style="' . $d(2, 'sales dashboard') . '">Sales dashboard</span><span class="arrow a-fade" style="' . $d(2, 'sales dashboard', .4) . '">&rarr;</span><span class="chip ok a-pop" style="' . $d(2, 'sales dashboard', .7) . '">Dashboard</span>
                        <span class="chip a-pop" style="' . $d(2, 'the Pipeline') . '">Pipeline</span><span class="arrow a-fade" style="' . $d(2, 'the Pipeline', .4) . '">&rarr;</span><span class="chip ok a-pop" style="' . $d(2, 'the Pipeline', .7) . '">Orders</span>
                        <span class="chip a-pop" style="' . $d(2, 'old orders list') . '">Orders &amp; quotes lists</span><span class="arrow a-fade" style="' . $d(2, 'old orders list', .4) . '">&rarr;</span><span class="chip ok a-pop" style="' . $d(2, 'old orders list', .7) . '">Orders</span>
                        <span class="chip a-pop" style="' . $d(2, 'console version') . '">Calendar</span><span class="arrow a-fade" style="' . $d(2, 'console version', .4) . '">&rarr;</span><span class="chip ok a-pop" style="' . $d(2, 'console version', .7) . '">office Calendar</span>
                      </div></div>
                  </div>
                </div>

                <!-- 3 — the Work menu -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">The Work menu</div>
                  <div class="mwrap" style="margin-top:.4rem">
                    ' . $menu([
                        '§Work'     => ['a-ring', $d(3, 'Work comes')],
                        'Dashboard' => ['a-sel', $d(3, 'Dashboard is')],
                        'Orders'    => ['a-sel', $d(3, 'Orders is')],
                        'Remakes (2 to approve)'  => ['a-sel', $d(3, 'Remakes is')],
                        'Calendar (3 due)' => ['a-sel', $d(3, 'and Calendar')],
                    ], 'Remakes (2 to approve)', 'Calendar (3 due)') . '
                    <div class="mnote">
                      <span class="chip a-pop" style="' . $d(3, 'Dashboard is') . '"><b>Dashboard</b> &mdash; your home page</span>
                      <span class="chip a-pop" style="' . $d(3, 'Orders is') . '"><b>Orders</b> &mdash; everything placed with us</span>
                      <span class="chip a-pop" style="' . $d(3, 'Remakes is') . '"><b>Remakes</b> &mdash; faults and remakes</span>
                      <span class="chip a-pop" style="' . $d(3, 'and Calendar') . '"><b>Calendar</b> &mdash; the office&rsquo;s shared calendar</span>
                      <span class="chip ok a-pop" style="' . $d(3, 'When something') . '">The label tells you when something needs you</span>
                    </div>
                  </div>
                </div>

                <!-- 4 — Production + Accounts -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">Production and Accounts</div>
                  <div class="mwrap" style="margin-top:.4rem">
                    ' . $menu([
                        '§Production'     => ['a-ring', $d(4, 'Under Production')],
                        'Incoming orders' => ['a-sel', $d(4, 'Incoming orders')],
                        'Floor'           => ['a-sel', $d(4, 'the Floor')],
                        'Dispatch'        => ['a-sel', $d(4, 'and Dispatch')],
                        '§Accounts'       => ['a-ring', $d(4, 'Under Accounts')],
                        'Invoices'        => ['a-sel', $d(4, 'Invoices')],
                        'Bank'            => ['a-sel', $d(4, 'Bank')],
                        'Statements'      => ['a-sel', $d(4, 'Statements')],
                        'Commissions'     => ['a-sel', $d(4, 'Commissions')],
                        'Trade accounts'  => ['a-ring', $d(4, 'Trade accounts')],
                        '§Setup'          => ['a-ring', $d(4, 'Setup and Platform')],
                        '§Platform'       => ['a-ring', $d(4, 'Setup and Platform', .5)],
                    ]) . '
                    <div class="mnote">
                      <span class="chip a-pop" style="' . $d(4, 'Under Production') . '"><b>Production</b> &mdash; the screens you already know</span>
                      <span class="chip a-pop" style="' . $d(4, 'Under Accounts') . '"><b>Accounts</b> &mdash; invoicing and money in</span>
                      <span class="chip bad a-pop" style="' . $d(4, 'but only for') . '"><b>Trade accounts</b> &mdash; super-admin only</span>
                      <span class="chip a-pop" style="' . $d(4, 'Setup and Platform') . '"><b>Setup</b> &middot; <b>Platform</b> &mdash; if your login allows</span>
                    </div>
                  </div>
                </div>

                <!-- 5 — the four stage tiles -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">The four stage tiles</div>
                  ' . $dashHead . '
                  <div class="fgrid">
                    <div class="a-drop" style="' . $d(5, 'New means') . '">' . $tile('New', '3', 'Not received on the floor yet', '#b91c1c') . '<span class="goes a-fade" style="' . $d(5, 'New opens') . '">&rarr; Incoming orders</span></div>
                    <div class="a-drop" style="' . $d(5, 'Received means') . '">' . $tile('Received', '5', 'In, not started', '#5b6b7f') . '<span class="goes a-fade" style="' . $d(5, 'Click a tile') . '">&rarr; Orders &middot; Received</span></div>
                    <div class="a-drop" style="' . $d(5, 'In production means') . '">' . $tile('In production', '12', 'Being made', '#b5730f') . '<span class="goes a-fade" style="' . $d(5, 'Click a tile') . '">&rarr; Orders &middot; In production</span></div>
                    <div class="a-drop" style="' . $d(5, 'Ready to dispatch means') . '">' . $tile('Ready to dispatch', '6', 'Everything made and in', '#245ea3') . '<span class="goes a-fade" style="' . $d(5, 'Ready to dispatch opens') . '">&rarr; Dispatch</span></div>
                  </div>
                  <div class="a-move" style="--fx:60%;--fy:95%;--tx:8%;--ty:6.8rem;' . $d(5, 'Click a tile') . ';--md:1.5s">' . $ptr . '</div>
                </div>

                <!-- 6 — not placed -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">Quotes not placed yet</div>
                  ' . $dashHead . '
                  <div class="fgrid">
                    ' . $tile('New', '3', 'Not received on the floor yet', '#b91c1c') . $tile('Received', '5', 'In, not started', '#5b6b7f')
                      . $tile('In production', '12', 'Being made', '#b5730f') . $tile('Ready to dispatch', '6', 'Everything made and in', '#245ea3') . '
                  </div>
                  <p class="npl a-rise" style="' . $d(6, 'you may see') . '"><b class="a-ring" style="' . $d(6, 'Click the line') . '">1 of our own trade quote</b> not placed yet.</p>
                  <div class="a-rise" style="' . $d(6, 'you see them') . '">
                    ' . $chips('notplaced') . '
                    <div class="otab">' . $thead . $row($ROWS[4]) . '</div>
                  </div>
                </div>

                <!-- 7 — the second row -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">The second row</div>
                  <p class="scs a-fade" style="--d:.6s">The rest of the day at a glance.</p>
                  <div class="fgrid">
                    ' . $tile('Calendar', '3', '<b style="color:#b91c1c">due now</b> &middot; reminders, callbacks, remakes', '#b91c1c', 'a-drop', $d(7, 'Calendar shows'))
                      . $tile('Remakes', '4', '<b style="color:#7c3aed">2 waiting for approval</b>', '#7c3aed', 'a-drop', $d(7, 'Remakes shows'))
                      . $tile('Blinds to make', '86', 'On orders not yet ready', 'var(--line)', 'a-drop', $d(7, 'Blinds to make'))
                      . $tile('Out today', '7', 'Delivery notes dispatched', 'var(--line)', 'a-drop', $d(7, 'Out today')) . '
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(7, 'and how many are waiting') . '">Nothing waiting? It says <b>In progress &middot; none waiting</b></span>
                    <span class="chip a-pop" style="' . $d(7, 'Calendar shows', 3) . '">Nothing due? It says <b>Nothing due</b></span>
                  </div>
                </div>

                <!-- 8 — this week -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">This week so far</div>
                  <div class="fgrid g2" style="margin-top:.6rem">
                    ' . $tile('Orders this week', '23', 'Placed since Monday', 'var(--line)', 'a-drop', $d(8, 'Orders this week'))
                      . '<div class="a-drop" style="' . $d(8, 'Invoiced this week') . '"><div class="ftile" style="--fc:var(--line)"><div class="fl">Invoiced this week</div><div class="fv" style="font-size:1.1rem">&pound;4,812.60</div><div class="fs">Net, since Monday &middot; <b class="a-ring" style="' . $d(8, 'owed is') . ';border-radius:4px">owed &pound;9,205.18</b></div></div></div>' . '
                  </div>
                  <div class="a-move" style="--fx:20%;--fy:95%;--tx:55%;--ty:5.6rem;' . $d(8, 'Click it') . ';--md:1.3s">' . $ptr . '</div>
                  <span class="chip ok a-pop" style="' . $d(8, 'Click it', 1.3) . ';margin-top:.6rem">&rarr; Accounts &middot; Invoices</span>
                </div>

                <!-- 9 — panels -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">Latest orders and busiest accounts</div>
                  <div class="cols" style="margin-top:.5rem">
                    <div class="pnl a-rise" style="' . $d(9, 'Latest orders shows') . '"><h5>Latest orders <span class="a-ring" style="' . $d(9, 'or See all') . ';border-radius:4px">See all</span></h5>
                      <div class="mt"><div><span>Order</span><span>Account</span><span class="n">Blinds</span><span>Stage</span></div>
                        <div class="a-fly" style="' . $d(9, 'eight newest', .3) . '"><b class="lnk a-ring" style="' . $d(9, 'Click an order') . ';border-radius:4px">ABC-2026-0041</b><span>ABC Blinds</span><span class="n">6</span><span>' . $pill('new') . '</span></div>
                        <div class="a-fly" style="' . $d(9, 'eight newest', .6) . '"><b class="lnk">HUG-2026-0012</b><span>Hughes Interiors</span><span class="n">2</span><span>' . $pill('in_production') . '</span></div>
                        <div class="a-fly" style="' . $d(9, 'eight newest', .9) . '"><b class="lnk">ABC-2026-0038-R1</b><span>ABC Blinds</span><span class="n">1</span><span>' . $pill('ready') . '</span></div>
                        <div class="a-fly" style="' . $d(9, 'eight newest', 1.2) . '"><b class="lnk">CST-2026-0007</b><span>Coastal Shutters</span><span class="n">4</span><span>' . $pill('dispatched') . '</span></div>
                      </div></div>
                    <div class="pnl a-rise" style="' . $d(9, 'Busiest accounts') . '"><h5>Busiest accounts <small>last 30 days</small></h5>
                      <div class="mt b"><div><span>Account</span><span class="n">Orders</span><span class="n">Value</span></div>
                        <div class="a-fly" style="' . $d(9, 'Busiest accounts', .5) . '"><span>ABC Blinds</span><span class="n">14</span><span class="n">&pound;6,140.20</span></div>
                        <div class="a-fly" style="' . $d(9, 'Busiest accounts', .8) . '"><span>Coastal Shutters</span><span class="n">9</span><span class="n">&pound;3,882.00</span></div>
                        <div class="a-fly" style="' . $d(9, 'Busiest accounts', 1.1) . '"><span>Hughes Interiors</span><span class="n">6</span><span class="n">&pound;1,240.75</span></div>
                      </div></div>
                  </div>
                </div>

                <!-- 10 — one Orders list -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="dh a-fade" style="--d:.2s">
                    <div><div class="pt">Orders</div>
                      <div class="phint a-fade" style="' . $d(10, 'It is one list') . '">Every order placed with us, whoever placed it: from their portal, as a direct order, or keyed in here. Our own trade quotes that aren&rsquo;t placed yet show as <b>Not placed</b>.</div></div>
                    <span class="btnp a-ring" style="' . $d(10, 'use the New order') . '">+ New order</span></div>
                  <div class="chips" style="margin:0 0 .6rem">
                    <span class="chip a-pop" style="' . $d(10, 'From their portal') . '">from their portal</span>
                    <span class="chip a-pop" style="' . $d(10, 'as a direct order') . '">as a direct order</span>
                    <span class="chip a-pop" style="' . $d(10, 'keyed in here') . '">keyed in here by the office</span>
                  </div>
                  <div class="otab a-rise" style="' . $d(10, 'It is one list') . '">' . $thead . $row($ROWS[0]) . $row($ROWS[1]) . $row($ROWS[3]) . '</div>
                </div>

                <!-- 11 — chips -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="sct a-fade" style="--d:.2s">The stage chips</div>
                  <p class="scs a-fade" style="--d:.6s">Each chip shows its own count. The list starts on <b>Open</b>.</p>
                  ' . $chips('open', [
                        'notplaced'     => $at(11, 'Not placed'),
                        'new'           => $at(11, 'New, Received'),
                        'confirmed'     => $at(11, 'Received, In'),
                        'in_production' => $at(11, 'In production'),
                        'ready'         => $at(11, 'Ready and'),
                        'dispatched'    => $at(11, 'Dispatched. All'),
                        'all'           => $at(11, 'All shows'),
                    ]) . '
                  <div class="otab a-rise" style="' . $d(11, 'Click a chip') . '">' . $thead . $row($ROWS[1]) . $row($ROWS[2]) . '</div>
                  <span class="chip a-pop" style="' . $d(11, 'Open is') . ';margin-top:.6rem"><b>Open</b> = everything not dispatched yet</span>
                </div>

                <!-- 12 — finding one -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="sct a-fade" style="--d:.2s">Finding an order</div>
                  ' . $chips('open') . '
                  <div class="ffil">
                    <span class="ib grow"><span class="swap"><span class="gph a-out" style="' . $d(12, 'Or type') . '">Search order number, account, their reference, label name or invoice</span>
                      <span class="a-fade" style="' . $d(12, 'Or type') . '"><span class="a-type" style="' . $d(12, 'Or type', .2) . ';--ts:7;--tt:.8s">PO 4471</span></span></span></span>
                    <span class="ib sel a-ring" style="' . $d(12, 'Choose an account') . '"><span class="swap"><span class="a-out" style="' . $d(12, 'Choose an account', .8) . '">All accounts</span><span class="a-fade" style="' . $d(12, 'Choose an account', .8) . '">ABC Blinds</span></span></span>
                    <span class="btns a-press" style="' . $d(12, 'Then click Search') . '">Search</span>
                  </div>
                  <div class="otab">' . $thead . $row($ROWS[0]) . $row($ROWS[1], 'a-out', $d(12, 'Choose an account', 1)) . $row($ROWS[3], 'a-out', $d(12, 'Choose an account', 1)) . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(12, 'An order number') . '">order number</span><span class="chip a-pop" style="' . $d(12, 'the account,') . '">account</span>
                    <span class="chip a-pop" style="' . $d(12, 'their reference') . '">their reference</span><span class="chip a-pop" style="' . $d(12, 'the name for') . '">name for the labels</span>
                    <span class="chip a-pop" style="' . $d(12, 'an invoice number') . '">invoice number</span>
                  </div>
                </div>

                <!-- 13 — reading a row -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="sct a-fade" style="--d:.2s">Reading a row</div>
                  <div style="position:relative;margin-top:2.4rem">
                    <div class="otab">' . $thead . $row($ROWS[3], 'a-fly', $d(13, 'Each row')) . $row($ROWS[2], 'a-fly', $d(13, 'And a purple')) . '</div>
                    <span class="tagn a-pop" style="' . $d(13, 'the order number') . ';left:0;top:-2.1rem">order &middot; account &middot; their ref &middot; date</span>
                    <span class="tagn a-pop" style="' . $d(13, 'how many of our') . ';right:28%;top:-2.1rem">our blinds &middot; value</span>
                    <span class="tagn a-pop" style="' . $d(13, 'its invoice number') . ';right:0;top:calc(100% + .4rem)">invoice number, once invoiced</span>
                    <span class="tagn a-pop" style="' . $d(13, 'REMAKE badge') . ';left:0;top:calc(100% + .4rem);border-color:#7c3aed">REMAKE &mdash; a remake of an earlier order</span>
                  </div>
                  <div class="chips" style="margin-top:4.4rem">' . $pill('new', 'a-pop', $d(13, 'coloured pill')) . $pill('confirmed', 'a-pop', $d(13, 'coloured pill', .3)) . $pill('in_production', 'a-pop', $d(13, 'coloured pill', .6)) . $pill('ready', 'a-pop', $d(13, 'coloured pill', .9)) . $pill('dispatched', 'a-pop', $d(13, 'coloured pill', 1.2)) . '</div>
                </div>

                <!-- 14 — opening an order -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="sct a-fade" style="--d:.2s">Opening an order</div>
                  <div class="otab" style="margin:.4rem 0 .8rem">' . $thead
                    . str_replace(['<b class="lnk">ABC-2026-0041</b>', '<span class="frm">&#8635; Remake</span>'],
                                  ['<b class="lnk a-ring" style="' . $d(14, 'Click the order') . '">ABC-2026-0041</b>', '<span class="frm a-ring" style="' . $d(14, 'Remake link') . '">&#8635; Remake</span>'], $row($ROWS[0]))
                    . $row($ROWS[4]) . '</div>
                  <div class="two">
                    <div class="card a-rise" style="' . $d(14, 'An order an account') . '"><h4>An account&rsquo;s order</h4>opens in the factory&rsquo;s <b>Edit order</b> screen</div>
                    <div class="card a-rise" style="' . $d(14, 'Our own orders') . '"><h4>Our own orders &middot; Not placed</h4>open in the <b>quote builder</b></div>
                  </div>
                  <span class="chip a-pop" style="' . $d(14, 'the Remakes guide') . ';margin-top:.7rem;border-color:#7c3aed">&#8635; Remake &mdash; see &ldquo;Remakes&rdquo;</span>
                  <div class="a-move" style="--fx:70%;--fy:90%;--tx:6%;--ty:4.3rem;' . $d(14, 'Click the order', -.8) . ';--md:1s">' . $ptr . '</div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Why it exists.</b> When a trade account places an order on their own portal, it is stored <b>once, as their order</b>.
             Nothing on it says &ldquo;factory&rdquo; except its lines &mdash; the blinds belong to the factory. The factory&rsquo;s own
             screens (Incoming orders, Dispatch, Invoices) always found orders that way, but the retail-shaped screens (Dashboard, Pipeline,
             the Orders list) only listed the factory&rsquo;s own jobs, so they could never see an account&rsquo;s order. The console reads
             orders the factory way: <b>every placed order with at least one of our blinds on it, from any account</b>, plus our own trade
             quotes that are not placed yet.</p>

          <p><b>Who gets it.</b> The office staff signed in to the factory account. When you sign in you land on
             <b>Work &rarr; Dashboard</b>, and the menu reads <b>Factory Console</b> under the logo. Open one of the old screens and it takes
             you to the console one instead: the sales dashboard &rarr; <b>Dashboard</b>; the orders and quotes lists and the Pipeline &rarr;
             <b>Orders</b> (quotes land on <b>Not placed</b>); the old calendar &rarr; the office <b>Calendar</b>. Nobody else is affected.</p>

          <ul class="steps">
            <li><b>Work</b> &mdash; <b>Dashboard</b>, <b>Orders</b>, <b>Remakes</b> (reads <b>Remakes (2 to approve)</b> when accounts are
                waiting), <b>Calendar</b> (reads <b>Calendar (3 due)</b> when reminders, callbacks or remakes are due).</li>
            <li><b>Production</b> &mdash; <b>Incoming orders</b>, <b>Floor</b>, <b>Dispatch</b>.</li>
            <li><b>Accounts</b> &mdash; <b>Trade accounts</b> (super-admin only), <b>Invoices</b>, <b>Bank</b>, <b>Statements</b>,
                <b>Commissions</b>, <b>Profit</b> (super-admin only — what you make on every order, at your real cost).</li>
            <li><b>Setup</b> &mdash; <b>Products</b>, <b>Users</b>, <b>Settings</b> (admins) and <b>Factory settings</b>; then
                <b>Platform</b> for the super-admin.</li>
          </ul>

          <p><b>The Dashboard.</b> A greeting and today&rsquo;s date, an <b>All orders</b> button, then the tiles:</p>
          <ul class="steps">
            <li><b>New</b> &mdash; <em>Not received on the floor yet</em> (opens <b>Incoming orders</b>). <b>Received</b> &mdash; <em>In, not
                started</em>. <b>In production</b> &mdash; <em>Being made</em>. <b>Ready to dispatch</b> &mdash; <em>Everything made and in</em>
                (opens <b>Dispatch</b>). Received and In production open the Orders list on that chip.</li>
            <li>If any of our own trade quotes are not placed yet, a line under the tiles says so &mdash; e.g. <em>2 of our own trade quotes
                not placed yet.</em> &mdash; and opens Orders on <b>Not placed</b>.</li>
            <li><b>Calendar</b> &mdash; how many are <b>due now</b> (reminders, callbacks, remakes), or <em>Nothing due</em>. Opens the office
                Calendar.</li>
            <li><b>Remakes</b> &mdash; remakes in progress, with <b>n waiting for approval</b> underneath (or <em>In progress &middot; none
                waiting</em>). Opens Remakes.</li>
            <li><b>Blinds to make</b> &mdash; <em>On orders not yet ready</em>. <b>Out today</b> &mdash; <em>Delivery notes dispatched</em>
                (opens Dispatch).</li>
            <li><b>Orders this week</b> &mdash; <em>Placed since Monday</em>. <b>Invoiced this week</b> &mdash; <em>Net, since Monday</em>,
                with <b>owed</b> (everything still unpaid on our invoices) underneath. Opens Invoices.</li>
            <li><b>Latest orders</b> &mdash; the eight newest placed orders: Order, Account, Blinds, Stage, with <b>See all</b>.
                <b>Busiest accounts</b> &mdash; <em>last 30 days</em>, the top five by value: Account, Orders, Value.</li>
          </ul>

          <p><b>The Orders list</b> (<b>Work &rarr; Orders</b>). <em>Every order placed with us, whoever placed it: from their portal, as a
             direct order, or keyed in here.</em> <b>+ New order</b> (top right) keys one in.</p>
          <ul class="steps">
            <li><b>Chips</b>, each with a count: <b>Open</b> (everything not dispatched &mdash; where the list starts), <b>Not placed</b>,
                <b>New</b>, <b>Received</b>, <b>In production</b>, <b>Ready</b>, <b>Dispatched</b>, <b>All</b>.</li>
            <li><b>All accounts</b> picker &mdash; choose one to see only theirs (our own sales show as <b>One-off sale</b>).</li>
            <li><b>Search</b> &mdash; <em>order number, account, their reference, label name or invoice</em>. Nothing found says
                <em>Nothing matches.</em> with <b>Show all open orders</b>.</li>
            <li>Columns: <b>Order</b> (with the label name under it), <b>Account</b>, <b>Their ref</b>, <b>Date</b>, <b>Blinds</b> (ours on
                it), <b>Value</b> (what the invoice bills), <b>Stage</b>. On a phone, Their ref, Date and Value are hidden.</li>
            <li>An invoiced order shows its <b>invoice number</b> beside the stage. A purple <b>REMAKE</b> badge marks a remake order
                (hover it: <em>Remake of &hellip;</em>).</li>
            <li><b>&#8635; Remake</b> beside the stage of every placed order raises a remake on it &mdash; see the <b>Remakes</b> guide.</li>
            <li>The list shows the newest 400 orders.</li>
          </ul>

          <p><b>Where an order opens.</b> An order an account placed opens in the factory&rsquo;s <b>Edit order</b> screen. Our own orders
             (one-off sales) and anything <b>Not placed</b> open in the <b>quote builder</b>.</p>

          <div class="heads"><span class="hi">&#9432;</span><div><b>Nothing here changes an order.</b> The Dashboard and the Orders list
             only read &mdash; stages move on from the floor, dispatch and delivery notes, exactly as before.</div></div>',
        'script'  => $S,
];
