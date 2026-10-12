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
 *     factory line), the Making labels (fc_stage_meta), the Money labels
 *     (fc_money_meta), the Next labels (fc_next_action), the chip groups
 *     (fc_filter_groups) and who sees the Money side (fc_money_visible), where
 *     an order opens;
 *   - _partials/sidebar.php ($isFactoryConsole) — the console menu, its
 *     "Remakes (n to approve)" / "Calendar (n due)" labels, Trade accounts for
 *     the super-admin only;
 *   - auth/middleware.php — factory_console_user(), landing on
 *     /factory/dashboard.php, factory_console_redirect() from the old screens;
 *   - factory/dashboard.php — the stage tiles, the money tiles, the "not placed
 *     yet" line, the rest-of-the-day tiles, Latest orders, Busiest accounts;
 *   - factory/orders.php — the grouped chips, account picker, search, the
 *     Making / Money / Next columns, invoice lines, REMAKE badge and the
 *     ↻ Remake link;
 *   - master-admin/dispatch.php — the "Waiting on suppliers" list (#1073);
 *     the Awaiting supplier stage/chip/tile and Next's "Order from X" /
 *     "Waiting on X" (#1072/#1073). The Pipeline has its own guide.
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step). Start times are
 * worked out from where a phrase falls in the voice-over ($at), at Alice's
 * ~13.6 characters a second, and data-len from the line's length ($len).
 */

// ── The voice-over first: scene timings are read from it ───────────────
$S = [
    ['1', 'Why there is a Factory Console', 'When a trade account places an order on their own portal, it is stored as their order, not ours. So the old sales screens, which only listed the factory\'s own jobs, could never show it. The Factory Console fixes that. It finds every order with our blinds on it, whoever placed it, and puts them all in one place for the office.', 1],
    ['2', 'Signing in', 'The console is for the office staff of the factory. When you sign in, you land straight on the console Dashboard, and the menu says Factory Console under the logo. If you open one of the old screens, like the sales dashboard, the sales Pipeline or the old orders list, it simply takes you to the console version instead.', 2],
    ['3', 'The Work menu', 'The menu has its own sections. Work comes first. Dashboard is your home page. Pipeline shows every open order as a card, from New to Paid, and has its own guide. Orders is the one list of everything placed with us, and Quotes is our own quotes not placed yet. Remakes is for faults and remakes, and Calendar is the office\'s shared calendar. When something needs you, the menu says so, like Remakes, two to approve.', 3],
    ['4', 'Production and Accounts', 'Under Production are the factory screens you already know: Incoming orders, the Floor, and Dispatch. Under Accounts are Invoices, Bank, Statements and Commissions. Trade accounts and The Numbers are there too, but only for the super admin. Setup and Platform sit at the bottom, for the people allowed to use them.', 4],
    ['5', 'The stage tiles', 'The top row counts orders by stage. New means it has come in, but is not received on the floor yet. Received means it is in, but not started. In production means it is being made. Awaiting supplier means our part is made, but a bought-in item has not arrived. Ready to dispatch means everything is made and in. Click a tile to open it. New opens Incoming orders, and Ready to dispatch opens Dispatch.', 5],
    ['6', 'Waiting on suppliers', 'Orders that are awaiting a supplier are listed on the Dispatch tray too, under Waiting on suppliers. Each row shows the account, the order, their reference, how many are made, and which supplier it is waiting on, with the date it was ordered, or not ordered yet. When the item arrives, mark it received on Incoming orders, and the order moves up to Ready to go out.', 6],
    ['7', 'The money tiles', 'The second row counts the money. Needs invoicing means an order has been dispatched, but has no invoice yet. Awaiting payment means it is invoiced, but not paid yet. Overdue means it is past the invoice due date. Click a tile to see those orders in the Orders list. The money tiles, and the Money column, only show on the main factory account.', 7],
    ['8', 'Quotes not placed yet', 'Just under those tiles, you may see a line saying how many of our own quotes are not placed yet. These are quotes the office has keyed in, which have not been turned into orders. Click the line, and you see them in the Orders list, under Quotes.', 8],
    ['9', 'The rest of the day', 'The next row is the rest of the day at a glance. Calendar shows how many reminders and callbacks are due now. Remakes shows how many are in progress, and how many are waiting for approval. Blinds to make counts the blinds on orders that are not ready yet. And Out today counts the delivery notes dispatched today.', 9],
    ['10', 'This week so far', 'Two more tiles look at the week so far. Orders this week counts the orders placed since Monday. Invoiced this week is the net value of the invoices raised since Monday. Underneath it, owed is everything still unpaid on our invoices. Click it to open Invoices. Like the money tiles, it is only on the main factory account.', 10],
    ['11', 'Latest orders and busiest accounts', 'Below the tiles are two panels. Latest orders shows the eight newest orders, with the account, the number of blinds, where the making is up to, and the money. Click an order number to open it, or See all for the whole list. Busiest accounts shows who has ordered the most over the last thirty days, by value.', 11],
    ['12', 'One Orders list', 'Orders, in the Work menu, is the heart of the console. It is one list of every order placed with us, whoever placed it. From their portal, as a direct order, or keyed in here by the office. To key one in yourself, use the New order button at the top right.', 12],
    ['13', 'The filter chips', 'Across the top are the filter chips, in groups, each with a count. Open is everything not yet both dispatched and paid, and that is where the list starts. To make has New, Received, In production, Awaiting supplier and Ready to go. To bill has Needs invoicing, Awaiting payment and Overdue. Finished has Done. Not placed has Quotes. And All shows every order.', 13],
    ['14', 'Finding an order', 'To find one order, use the account picker and the search box. Choose an account, and you see only their orders. Or type in the search box. An order number, the account, their reference, the name for the labels, or an invoice number all work. Then click Search.', 14],
    ['15', 'Making and Money', 'Each row shows the order number, the account, their reference, the date, how many of our blinds are on it, and its value. Then Making is where the blinds are, and Money is where the bill is. Under the money pill is each invoice number, what is still owed, and when it is due. A purple REMAKE badge marks a remake of an earlier order.', 15],
    ['16', 'What to do next', 'The Next column tells you the one thing to do next. While it is being made, that is Mark received, Start production, Being made, Order from a supplier, or Waiting on a supplier, then Dispatch. After that comes the money. Raise invoice, Wait for payment, or Wait for the rest. If a payment is late, Chase payment comes first, in red. Done means dispatched and paid. Click it to go to the right screen.', 16],
    ['17', 'Opening an order', 'Click the order number to open it. An order an account placed opens in the factory\'s Edit order screen. Our own orders, and quotes not placed yet, open in the quote builder. In the Next column of every placed order is a Remake link. That is the quickest way to raise a remake, and the Remakes guide explains the rest.', 17],
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
    'notplaced'     => ['Quotes',        '#475569', '#e2e8f0'],
    'new'           => ['New',           '#b91c1c', '#fee2e2'],
    'confirmed'     => ['Received',      '#5b6b7f', '#e6ebf1'],
    'in_production' => ['In production', '#b5730f', '#f7ecd6'],
    'awaiting_supplier' => ['Awaiting supplier', '#7c3aed', '#ede9fe'],
    'ready'         => ['Ready',         '#245ea3', '#dde8f6'],
    'dispatched'    => ['Dispatched',    '#0d7a67', '#d6ece6'],
];
$pill = static fn (string $k, string $cls = '', string $style = ''): string =>
    '<span class="fpill ' . $cls . '" style="color:' . $PILL[$k][1] . ';background:' . $PILL[$k][2] . ';' . $style . '">' . $PILL[$k][0] . '</span>';

// ── Money pills, as fc_money_meta() colours them ──────────────────────
$MPILL = [
    'none'            => ['&mdash;',                          '#94a3b8', 'transparent'],
    'not_invoiced'    => ['Not invoiced',                     '#64748b', '#f1f5f9'],
    'needs_invoicing' => ['Needs invoicing',                  '#b91c1c', '#fee2e2'],
    'awaiting'        => ['Invoiced &middot; awaiting payment', '#245ea3', '#dde8f6'],
    'part_paid'       => ['Part paid',                        '#b5730f', '#f7ecd6'],
    'overdue'         => ['Overdue',                          '#ffffff', '#b91c1c'],
    'paid'            => ['Paid',                             '#ffffff', '#0d7a67'],
];
$mpill = static fn (string $k, string $cls = '', string $style = ''): string =>
    '<span class="fpill ' . $cls . '" style="color:' . $MPILL[$k][1] . ';background:' . $MPILL[$k][2] . ';' . $style . '">' . $MPILL[$k][0] . '</span>';

/** The Next column's label, as fc_next_action() gives it: [label, urgent?, a link?]. */
$next = static function (string $label, bool $urgent = false, bool $link = true, string $cls = '', string $style = ''): string {
    $c = 'fnx' . ($link ? ' lk' : '') . ($urgent ? ' urg' : '') . ($label === 'Done' ? ' dn' : '') . ($cls !== '' ? ' ' . $cls : '');
    return '<span class="' . $c . '" style="' . $style . '">' . $label . '</span>';
};
$rmb = '<span class="rmb">REMAKE</span>';

/**
 * The console menu, drawn in the stage (the demo sidebar is hidden on phones).
 * $ring = [item label => [animation class, style]] for items to animate.
 */
$menu = static function (array $ring = [], string $rem = 'Remakes', string $cal = 'Calendar') : string {
    $sec = [
        'Work'       => [$cal, 'Dashboard', 'Orders', 'Pipeline', 'Quotes', $rem],   // A to Z, as the real menu
        'Production' => ['Dispatch', 'Floor', 'Incoming orders'],
        'Accounts'   => ['Bank', 'Commissions', 'Invoices', 'Statements', 'The Numbers', 'Trade accounts'],
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
    // number, sub (label name), account, their ref, date, blinds, value, making, money,
    // invoices [[number, owed line]], next [label, urgent, link], remake?
    ['ABC-2026-0041',    'Mrs Patel', 'ABC Blinds',       'PO 4471', '8 Oct 2026',  6, '&pound;412.80', 'new',           'not_invoiced',    [], ['Mark received', false, true], false],
    ['HUG-2026-0012',    '',          'Hughes Interiors', 'Kitchen', '7 Oct 2026',  2, '&pound;101.15', 'in_production', 'not_invoiced',    [], ['Being made', false, false], false],
    ['ABC-2026-0038-R1', '',          'ABC Blinds',       'PO 4402', '6 Oct 2026',  1, '&pound;0.00',   'ready',         'not_invoiced',    [], ['Dispatch', false, true], true],
    ['CST-2026-0007',    'The Lodge', 'Coastal Shutters', 'CS-118',  '2 Oct 2026',  4, '&pound;286.40', 'dispatched',    'awaiting',        [['INV-BEV-2026-0007', '&pound;343.68 owed, due 1 Nov']], ['Wait for payment', false, true], false],
    ['BEV-2026-0090',    '',          'One-off sale',     '',        '1 Oct 2026',  3, '&pound;164.00', 'notplaced',     'none',            [], ['Place the order', false, true], false],
    ['HUG-2026-0009',    '',          'Hughes Interiors', 'Lounge',  '1 Sep 2026',  3, '&pound;198.50', 'dispatched',    'overdue',         [['INV-BEV-2026-0003', '&pound;238.20 owed, due 30 Sep']], ['Chase payment', true, true], false],
    ['CST-2026-0006',    '',          'Coastal Shutters', 'CS-117',  '29 Sep 2026', 2, '&pound;140.00', 'dispatched',    'needs_invoicing', [], ['Raise invoice', true, true], false],
    ['ABC-2026-0031',    '',          'ABC Blinds',       'PO 4310', '15 Sep 2026', 5, '&pound;350.25', 'dispatched',    'paid',            [['INV-BEV-2026-0001', '']], ['Done', false, false], false],
];
$row = static function (array $r, string $cls = '', string $style = '') use ($pill, $mpill, $next, $rmb): string {
    [$num, $sub, $acc, $ref, $date, $bl, $val, $stage, $mon, $invs, $nx, $rm] = $r;
    $inv = '';
    foreach ($invs as [$no, $owed]) {
        $inv .= '<small class="fiv"><span class="lnk">' . $no . '</span>' . ($owed !== '' ? ' &middot; ' . $owed : '') . '</small>';
    }
    return '<div class="orow ' . $cls . '" style="' . $style . '">'
        . '<span class="on"><b class="lnk">' . $num . '</b>' . ($rm ? ' ' . $rmb : '') . ($sub !== '' ? '<small>' . $sub . '</small>' : '') . '</span>'
        . '<span>' . $acc . '</span><span class="hs">' . $ref . '</span><span class="hs">' . $date . '</span>'
        . '<span class="n">' . $bl . '</span><span class="n hs">' . $val . '</span>'
        . '<span class="st">' . $pill($stage) . '</span>'
        . '<span class="st mo">' . $mpill($mon) . $inv . '</span>'
        . '<span class="st nx">' . $next($nx[0], $nx[1], $nx[2])
        . ($stage !== 'notplaced' ? '<span class="frm">&#8635; Remake</span>' : '') . '</span></div>';
};
$thead = '<div class="orow th"><span>Order</span><span>Account</span><span class="hs">Their ref</span><span class="hs">Date</span><span class="n">Blinds</span><span class="n hs">Value</span><span>Making</span><span>Money</span><span>Next</span></div>';

/**
 * The filter chips in their groups, as fc_filter_groups(); $active = key, or
 * [key => delay] to move the active chip through them. Needs invoicing and
 * Overdue show red while they have any (the real page's .warn).
 */
$CHIPS = [
    ''           => ['open' => ['Open', 34]],
    'To make'    => ['new' => ['New', 3], 'confirmed' => ['Received', 5], 'in_production' => ['In production', 12], 'awaiting_supplier' => ['Awaiting supplier', 1], 'ready' => ['Ready to go', 6]],
    'To bill'    => ['needs_invoicing' => ['Needs invoicing', 2], 'awaiting' => ['Awaiting payment', 5], 'overdue' => ['Overdue', 1]],
    'Finished'   => ['done' => ['Done', 140]],
    'Not placed' => ['notplaced' => ['Quotes', 1]],
    ' '          => ['all' => ['All', 175]],
];
$chips = static function ($active = 'open', array $sel = [], array $grp = []) use ($CHIPS): string {
    $h = '<div class="fchips">';
    foreach ($CHIPS as $g => $set) {
        $h .= '<span class="fgrp">';
        if (trim($g) !== '') {
            $h .= '<span class="fgl' . (isset($grp[$g]) ? ' a-ring' : '') . '"' . (isset($grp[$g]) ? ' style="--d:' . $grp[$g] . 's"' : '') . '>' . $g . '</span>';
        }
        foreach ($set as $k => [$l, $n]) {
            $warn = in_array($k, ['needs_invoicing', 'overdue'], true) ? ' warn' : '';
            if (isset($sel[$k])) {
                $h .= '<span class="fchip a-sel' . $warn . '" style="--d:' . $sel[$k] . 's">' . $l . ' <i>' . $n . '</i></span>';
            } else {
                $h .= '<span class="fchip' . ($k === $active ? ' on' : $warn) . '">' . $l . ' <i>' . $n . '</i></span>';
            }
        }
        $h .= '</span>';
    }
    return $h . '</div>';
};

$filters = static fn (string $acct, string $q, string $btn = '') =>
    '<div class="ffil"><span class="ib grow">' . $q . '</span><span class="ib sel">' . $acct . '</span><span class="btns ' . $btn . '">Search</span></div>';
$ph = static fn (string $t): string => '<span class="gph">' . $t . '</span>';

/** The four stage tiles and the three money tiles, at rest. */
$stageTiles = $tile('New', '3', 'Not received on the floor yet', '#b91c1c') . $tile('Received', '5', 'In, not started', '#5b6b7f')
            . $tile('In production', '12', 'Being made', '#b5730f') . $tile('Awaiting supplier', '1', 'Made, waiting on a bought-in item', '#7c3aed')
            . $tile('Ready to dispatch', '6', 'Everything made and in', '#245ea3');
$moneyTiles = $tile('Needs invoicing', '2', 'Dispatched, no invoice yet', '#b91c1c') . $tile('Awaiting payment', '5', 'Invoiced, not paid yet', '#245ea3')
            . $tile('Overdue', '1', 'Past the invoice due date', '#b91c1c');

$dashHead = '<div class="dh"><div><div class="pt">Good morning, Sam</div><div class="psub">Friday 9 October 2026</div></div><span class="btns">All orders</span></div>';

return [
        'aud'     => 'factory',
        'section' => 'Factory Console',
        'title'   => 'The Factory Console: dashboard and orders',
        'eyebrow' => 'Factory Console',
        'v'       => 2,
        'blurb'   => 'Why the factory office has its own console, the menu, what each Dashboard tile counts, and the one Orders list — filter chips, account picker, search, the Making, Money and Next columns, and where an order opens.',
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
          .gd .fiv{ display:block; font-size:.52rem; color:var(--faint); white-space:nowrap; line-height:1.35; }
          .gd .fnx{ font-size:.6rem; font-weight:600; color:var(--soft); white-space:nowrap; border-radius:4px; }
          .gd .fnx.lk{ color:var(--accent); } .gd .fnx.urg{ color:#b91c1c; } .gd .fnx.dn{ color:#0d7a67; }
          .gd .frm{ display:inline-block; margin-left:.35rem; font-size:.6rem; color:var(--accent); white-space:nowrap; border-radius:4px; }

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
          .gd .fgrid.g5{ grid-template-columns:repeat(5,minmax(0,1fr)); }
          .gd .fgrid.g2{ grid-template-columns:repeat(2,minmax(0,1fr)); max-width:26rem; }
          .gd .ftile{ background:var(--surface); border:1px solid var(--line); border-top:3px solid var(--fc); border-radius:10px; padding:.5rem .6rem; min-width:0; }
          .gd .ftile .fl{ font-size:.54rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .ftile .fv{ font-size:1.35rem; font-weight:800; color:var(--ink); line-height:1.15; font-variant-numeric:tabular-nums; }
          .gd .ftile .fs{ font-size:.58rem; color:var(--faint); line-height:1.3; }
          .gd .ftile .fs b{ font-weight:700; }
          .gd .goes{ display:block; font-size:.56rem; font-weight:700; color:var(--accent); margin-top:.25rem; }
          .gd .legend{ font-size:.66rem; color:var(--soft); margin-top:.55rem; }
          .gd .legend b{ color:var(--ink); }
          .gd .nseq{ display:flex; flex-wrap:wrap; gap:.25rem .35rem; align-items:center; }
          .gd .nseq i{ font-style:normal; color:var(--faint); font-size:.62rem; }
          .gd .npl{ font-size:.7rem; color:var(--soft); margin:.1rem 0 .6rem; }
          .gd .npl b{ color:var(--accent); text-decoration:underline; border-radius:4px; }

          /* dashboard panels + tables */
          .gd .cols{ display:grid; grid-template-columns:3fr 2fr; gap:.6rem; align-items:start; }
          .gd .pnl{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); min-width:0; }
          .gd .pnl h5{ margin:0 0 .35rem; font-size:.76rem; color:var(--ink); display:flex; justify-content:space-between; gap:.4rem; }
          .gd .pnl h5 span{ font-size:.62rem; font-weight:600; color:var(--accent); } .gd .pnl h5 small{ font-size:.58rem; font-weight:400; color:var(--faint); }
          .gd .mt{ font-size:.62rem; }
          .gd .mt div{ display:grid; grid-template-columns:1.9fr 1.5fr .6fr 1.1fr 1.7fr; gap:.25rem; padding:.22rem 0; border-top:1px solid var(--line); align-items:center; color:var(--ink); }
          .gd .mt.b div{ grid-template-columns:2fr .8fr 1.2fr; }
          .gd .mt.w div{ grid-template-columns:1.3fr 1.3fr .8fr .5fr 1fr 2fr; } .gd .mt.w div > :nth-child(5){ padding-left:.6rem; }
          .gd .mt.r div{ grid-template-columns:.35fr 1.3fr 1.4fr .8fr .5fr .7fr; }
          .gd .mt div:first-child{ border-top:0; font-weight:700; color:var(--soft); font-size:.54rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .mt .n{ text-align:right; font-variant-numeric:tabular-nums; }

          /* orders list */
          .gd .fchips{ display:flex; gap:.35rem .8rem; flex-wrap:wrap; margin:0 0 .5rem; }
          .gd .fgrp{ display:inline-flex; gap:.25rem; align-items:center; flex-wrap:wrap; }
          .gd .fgl{ font-size:.5rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); border-radius:4px; padding:0 .1rem; }
          .gd .fchip{ display:inline-flex; gap:.25rem; align-items:center; padding:.18rem .5rem; font-size:.64rem; border-radius:999px; background:var(--panel); color:var(--soft); border:1px solid var(--line); }
          .gd .fchip i{ font-style:normal; opacity:.75; font-variant-numeric:tabular-nums; }
          .gd .fchip.warn{ color:#b91c1c; background:#fee2e2; }
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
          .gd .otab{ border:1px solid var(--line); border-radius:10px; overflow-x:auto; overflow-y:hidden; background:var(--surface); }
          .gd .orow{ display:grid; grid-template-columns:1.7fr 1.3fr .75fr .85fr .5fr .8fr 1.25fr 2.1fr 1.5fr; gap:.4rem; align-items:center; padding:.3rem .5rem;
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
            .gd .fgrid, .gd .fgrid.g3, .gd .fgrid.g5{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .gd .orow{ grid-template-columns:1.9fr 1.3fr .5fr 1.1fr 1.8fr 1.5fr; min-width:34rem; }
            .gd .pnl{ overflow-x:auto; } .gd .mt div{ min-width:24rem; }
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
                <a>Calendar</a><a class="on">Dashboard</a><a>Orders</a><a>Pipeline</a><a>Quotes</a><a>Remakes</a>
                <div class="navh">Production</div>
                <a>Dispatch</a><a>Floor</a><a>Incoming orders</a>
                <div class="navh">Accounts</div>
                <a>Bank</a><a>Invoices</a><a>Statements</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $dashHead . '
                  <div class="fgrid g5">' . $stageTiles . '</div>
                  <div class="fgrid g3">' . $moneyTiles . '</div>
                  <div class="otab">' . $thead . $row($ROWS[0]) . $row($ROWS[3]) . $row($ROWS[5]) . '</div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
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
                        <span class="chip a-pop" style="' . $d(2, 'the sales Pipeline') . '">Sales pipeline</span><span class="arrow a-fade" style="' . $d(2, 'the sales Pipeline', .4) . '">&rarr;</span><span class="chip ok a-pop" style="' . $d(2, 'the sales Pipeline', .7) . '">Orders</span>
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
                        'Pipeline'  => ['a-sel', $d(3, 'Pipeline shows')],
                        'Orders'    => ['a-sel', $d(3, 'Orders is')],
                        'Quotes'    => ['a-sel', $d(3, 'and Quotes is')],
                        'Remakes (2 to approve)'  => ['a-sel', $d(3, 'Remakes is')],
                        'Calendar (3 due)' => ['a-sel', $d(3, 'and Calendar')],
                    ], 'Remakes (2 to approve)', 'Calendar (3 due)') . '
                    <div class="mnote">
                      <span class="chip a-pop" style="' . $d(3, 'Dashboard is') . '"><b>Dashboard</b> &mdash; your home page</span>
                      <span class="chip a-pop" style="' . $d(3, 'Pipeline shows') . '"><b>Pipeline</b> &mdash; every open order as a card</span>                      <span class="chip a-pop" style="' . $d(3, 'Orders is') . '"><b>Orders</b> &mdash; everything placed with us</span>
                      <span class="chip a-pop" style="' . $d(3, 'and Quotes is') . '"><b>Quotes</b> &mdash; our own, not placed yet</span>
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

                <!-- 5 — the stage tiles -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">The stage tiles</div>
                  ' . $dashHead . '
                  <div class="fgrid g5">
                    <div class="a-drop" style="' . $d(5, 'New means') . '">' . $tile('New', '3', 'Not received on the floor yet', '#b91c1c') . '<span class="goes a-fade" style="' . $d(5, 'New opens') . '">&rarr; Incoming orders</span></div>
                    <div class="a-drop" style="' . $d(5, 'Received means') . '">' . $tile('Received', '5', 'In, not started', '#5b6b7f') . '<span class="goes a-fade" style="' . $d(5, 'Click a tile') . '">&rarr; Orders &middot; Received</span></div>
                    <div class="a-drop" style="' . $d(5, 'In production means') . '">' . $tile('In production', '12', 'Being made', '#b5730f') . '<span class="goes a-fade" style="' . $d(5, 'Click a tile') . '">&rarr; Orders &middot; In production</span></div>
                    <div class="a-drop" style="' . $d(5, 'Awaiting supplier means') . '">' . $tile('Awaiting supplier', '1', 'Made, waiting on a bought-in item', '#7c3aed') . '<span class="goes a-fade" style="' . $d(5, 'Click a tile') . '">&rarr; Orders &middot; Awaiting supplier</span></div>
                    <div class="a-drop" style="' . $d(5, 'Ready to dispatch means') . '">' . $tile('Ready to dispatch', '6', 'Everything made and in', '#245ea3') . '<span class="goes a-fade" style="' . $d(5, 'Ready to dispatch opens') . '">&rarr; Dispatch</span></div>
                  </div>
                  <div class="a-move" style="--fx:60%;--fy:95%;--tx:8%;--ty:6.8rem;' . $d(5, 'Click a tile') . ';--md:1.5s">' . $ptr . '</div>
                </div>

                <!-- 6 — waiting on suppliers (Dispatch tray) -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">Waiting on suppliers</div>
                  <p class="scs a-fade" style="--d:.6s">Production &rarr; Dispatch &middot; the Dispatch tray</p>
                  <div class="pnl a-rise" style="' . $d(6, 'Orders that are') . '"><h5>Ready to go out</h5>
                    <div class="mt r"><div><span></span><span>Account</span><span>Order</span><span>Their ref</span><span class="n">Blinds</span><span class="n">Value</span></div>
                      <div><span class="tick"></span><span>ABC Blinds</span><b class="lnk">ABC-2026-0038-R1</b><span>PO 4402</span><span class="n">1</span><span class="n">&pound;0.00</span></div></div></div>
                  <div class="pnl a-rise" style="' . $d(6, 'under Waiting on suppliers') . ';margin-top:.6rem"><h5 class="a-ring" style="' . $d(6, 'under Waiting on suppliers', .4) . ';border-radius:4px">Waiting on suppliers</h5>
                    <div class="phint" style="margin-top:0">Everything we make on these is done; they&rsquo;re waiting on a bought-in item. When it arrives, mark it
                      received on <b class="lnk">Incoming orders</b> and the order moves up to <b>Ready to go out</b>.</div>
                    <div class="mt w"><div><span>Account</span><span>Order</span><span>Their ref</span><span class="n">Blinds</span><span>Made</span><span>Waiting on</span></div>
                      <div class="a-fly" style="' . $d(6, 'Each row shows') . '"><span>ABC Blinds</span><b class="lnk">ABC-2026-0039</b><span>PO 4420</span><span class="n">22</span><span class="a-ring" style="' . $d(6, 'how many are made') . ';border-radius:4px">20/20 made</span><span class="a-ring" style="' . $d(6, 'with the date') . ';border-radius:4px">Home Creations &middot; ordered 9 Oct</span></div>
                      <div class="a-fly" style="' . $d(6, 'Each row shows', .6) . '"><span>Coastal Shutters</span><b class="lnk">CST-2026-0010</b><span>CS-121</span><span class="n">1</span><span>Nothing to make</span><span class="a-ring" style="' . $d(6, 'or not ordered yet') . ';border-radius:4px">Hunter Douglas &middot; <b style="color:#b91c1c">not ordered yet</b></span></div>
                    </div></div>
                  <div class="chips"><span class="chip ok a-pop" style="' . $d(6, 'When the item arrives') . '">Received on <b>Incoming orders</b> &rarr; up to <b>Ready to go out</b></span></div>
                </div>

                <!-- 7 — the money tiles -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">The money tiles</div>
                  ' . $dashHead . '
                  <div class="fgrid g5">' . $stageTiles . '</div>
                  <div class="fgrid g3">
                    <div class="a-drop" style="' . $d(7, 'Needs invoicing means') . '">' . $tile('Needs invoicing', '2', 'Dispatched, no invoice yet', '#b91c1c') . '<span class="goes a-fade" style="' . $d(7, 'Click a tile') . '">&rarr; Orders &middot; Needs invoicing</span></div>
                    <div class="a-drop" style="' . $d(7, 'Awaiting payment means') . '">' . $tile('Awaiting payment', '5', 'Invoiced, not paid yet', '#245ea3') . '<span class="goes a-fade" style="' . $d(7, 'Click a tile', .4) . '">&rarr; Orders &middot; Awaiting payment</span></div>
                    <div class="a-drop" style="' . $d(7, 'Overdue means') . '">' . $tile('Overdue', '1', 'Past the invoice due date', '#b91c1c') . '<span class="goes a-fade" style="' . $d(7, 'Click a tile', .8) . '">&rarr; Orders &middot; Overdue</span></div>
                  </div>
                  <span class="chip bad a-pop" style="' . $d(7, 'The money tiles, and') . '">Money tiles and the Money column: main factory account only</span>
                  <div class="a-move" style="--fx:60%;--fy:95%;--tx:10%;--ty:10.6rem;' . $d(7, 'Click a tile') . ';--md:1.5s">' . $ptr . '</div>
                </div>

                <!-- 8 — not placed -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">Quotes not placed yet</div>
                  ' . $dashHead . '
                  <div class="fgrid g5">' . $stageTiles . '</div>
                  <div class="fgrid g3">' . $moneyTiles . '</div>
                  <p class="npl a-rise" style="' . $d(8, 'you may see') . '"><b class="a-ring" style="' . $d(8, 'Click the line') . '">1 of our own quote</b> not placed yet.</p>
                  <div class="a-rise" style="' . $d(8, 'you see them') . '">
                    ' . $chips('notplaced') . '
                    <div class="otab">' . $thead . $row($ROWS[4]) . '</div>
                  </div>
                </div>

                <!-- 9 — the rest of the day -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">The rest of the day</div>
                  <p class="scs a-fade" style="--d:.6s">The rest of the day at a glance.</p>
                  <div class="fgrid">
                    ' . $tile('Calendar', '3', '<b style="color:#b91c1c">due now</b> &middot; reminders, callbacks, remakes', '#b91c1c', 'a-drop', $d(9, 'Calendar shows'))
                      . $tile('Remakes', '4', '<b style="color:#7c3aed">2 waiting for approval</b>', '#7c3aed', 'a-drop', $d(9, 'Remakes shows'))
                      . $tile('Blinds to make', '86', 'On orders not yet ready', 'var(--line)', 'a-drop', $d(9, 'Blinds to make'))
                      . $tile('Out today', '7', 'Delivery notes dispatched', 'var(--line)', 'a-drop', $d(9, 'Out today')) . '
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(9, 'and how many are waiting') . '">Nothing waiting? It says <b>In progress &middot; none waiting</b></span>
                    <span class="chip a-pop" style="' . $d(9, 'Calendar shows', 3) . '">Nothing due? It says <b>Nothing due</b></span>
                  </div>
                </div>

                <!-- 10 — this week -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="sct a-fade" style="--d:.2s">This week so far</div>
                  <div class="fgrid g2" style="margin-top:.6rem">
                    ' . $tile('Orders this week', '23', 'Placed since Monday', 'var(--line)', 'a-drop', $d(10, 'Orders this week'))
                      . '<div class="a-drop" style="' . $d(10, 'Invoiced this week') . '"><div class="ftile" style="--fc:var(--line)"><div class="fl">Invoiced this week</div><div class="fv" style="font-size:1.1rem">&pound;4,812.60</div><div class="fs">Net, since Monday &middot; <b class="a-ring" style="' . $d(10, 'owed is') . ';border-radius:4px">owed &pound;9,205.18</b></div></div></div>' . '
                  </div>
                  <div class="a-move" style="--fx:20%;--fy:95%;--tx:55%;--ty:5.6rem;' . $d(10, 'Click it') . ';--md:1.3s">' . $ptr . '</div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="' . $d(10, 'Click it', 1.3) . '">&rarr; Accounts &middot; Invoices</span>
                    <span class="chip bad a-pop" style="' . $d(10, 'Like the money tiles') . '">Invoiced this week: main factory account only</span>
                  </div>
                </div>

                <!-- 11 — panels -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="sct a-fade" style="--d:.2s">Latest orders and busiest accounts</div>
                  <div class="cols" style="margin-top:.5rem">
                    <div class="pnl a-rise" style="' . $d(11, 'Latest orders shows') . '"><h5>Latest orders <span class="a-ring" style="' . $d(11, 'or See all') . ';border-radius:4px">See all</span></h5>
                      <div class="mt"><div><span>Order</span><span>Account</span><span class="n">Blinds</span><span><span class="a-ring" style="' . $d(11, 'where the making') . ';border-radius:4px">Making</span></span><span><span class="a-ring" style="' . $d(11, 'and the money') . ';border-radius:4px">Money</span></span></div>
                        <div class="a-fly" style="' . $d(11, 'eight newest', .3) . '"><b class="lnk a-ring" style="' . $d(11, 'Click an order') . ';border-radius:4px">ABC-2026-0041</b><span>ABC Blinds</span><span class="n">6</span><span>' . $pill('new') . '</span><span>' . $mpill('not_invoiced') . '</span></div>
                        <div class="a-fly" style="' . $d(11, 'eight newest', .6) . '"><b class="lnk">HUG-2026-0012</b><span>Hughes Interiors</span><span class="n">2</span><span>' . $pill('in_production') . '</span><span>' . $mpill('not_invoiced') . '</span></div>
                        <div class="a-fly" style="' . $d(11, 'eight newest', .9) . '"><b class="lnk">ABC-2026-0038-R1</b><span>ABC Blinds</span><span class="n">1</span><span>' . $pill('ready') . '</span><span>' . $mpill('not_invoiced') . '</span></div>
                        <div class="a-fly" style="' . $d(11, 'eight newest', 1.2) . '"><b class="lnk">CST-2026-0006</b><span>Coastal Shutters</span><span class="n">2</span><span>' . $pill('dispatched') . '</span><span>' . $mpill('needs_invoicing') . '</span></div>
                      </div></div>
                    <div class="pnl a-rise" style="' . $d(11, 'Busiest accounts') . '"><h5>Busiest accounts <small>last 30 days</small></h5>
                      <div class="mt b"><div><span>Account</span><span class="n">Orders</span><span class="n">Value</span></div>
                        <div class="a-fly" style="' . $d(11, 'Busiest accounts', .5) . '"><span>ABC Blinds</span><span class="n">14</span><span class="n">&pound;6,140.20</span></div>
                        <div class="a-fly" style="' . $d(11, 'Busiest accounts', .8) . '"><span>Coastal Shutters</span><span class="n">9</span><span class="n">&pound;3,882.00</span></div>
                        <div class="a-fly" style="' . $d(11, 'Busiest accounts', 1.1) . '"><span>Hughes Interiors</span><span class="n">6</span><span class="n">&pound;1,240.75</span></div>
                      </div></div>
                  </div>
                </div>

                <!-- 12 — one Orders list -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="dh a-fade" style="--d:.2s">
                    <div><div class="pt">Orders</div>
                      <div class="phint a-fade" style="' . $d(12, 'It is one list') . '">Every order placed with us, whoever placed it: from their portal, as a direct order, or keyed in here. <b>Making</b> is where the blinds are, <b>Money</b> is where the bill is, and <b>Open</b> is anything not yet both dispatched and paid.</div></div>
                    <span class="btnp a-ring" style="' . $d(12, 'use the New order') . '">+ New order</span></div>
                  <div class="chips" style="margin:0 0 .6rem">
                    <span class="chip a-pop" style="' . $d(12, 'From their portal') . '">from their portal</span>
                    <span class="chip a-pop" style="' . $d(12, 'as a direct order') . '">as a direct order</span>
                    <span class="chip a-pop" style="' . $d(12, 'keyed in here') . '">keyed in here by the office</span>
                  </div>
                  <div class="otab a-rise" style="' . $d(12, 'It is one list') . '">' . $thead . $row($ROWS[0]) . $row($ROWS[1]) . $row($ROWS[3]) . '</div>
                </div>

                <!-- 13 — chips -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="sct a-fade" style="--d:.2s">The filter chips</div>
                  <p class="scs a-fade" style="--d:.6s">In groups, each with its own count. The list starts on <b>Open</b>.</p>
                  ' . $chips('open', [
                        'new'             => $at(13, 'New, Received'),
                        'confirmed'       => $at(13, 'Received, In'),
                        'in_production'   => $at(13, 'In production, Awaiting'),
                        'awaiting_supplier' => $at(13, 'Awaiting supplier and'),
                        'ready'           => $at(13, 'Ready to go'),
                        'needs_invoicing' => $at(13, 'Needs invoicing,'),
                        'awaiting'        => $at(13, 'Awaiting payment and'),
                        'overdue'         => $at(13, 'Overdue. Finished'),
                        'done'            => $at(13, 'Done. Not'),
                        'notplaced'       => $at(13, 'Quotes. And'),
                        'all'             => $at(13, 'All shows'),
                    ], [
                        'To make'    => $at(13, 'To make has'),
                        'To bill'    => $at(13, 'To bill has'),
                        'Finished'   => $at(13, 'Finished has'),
                        'Not placed' => $at(13, 'Not placed has'),
                    ]) . '
                  <span class="chip a-pop" style="' . $d(13, 'Open is') . ';margin:0 0 .6rem"><b>Open</b> = not yet both dispatched and paid</span>
                  <div class="otab">' . $thead . $row($ROWS[1]) . $row($ROWS[6]) . $row($ROWS[5]) . '</div>
                </div>

                <!-- 14 — finding one -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="sct a-fade" style="--d:.2s">Finding an order</div>
                  ' . $chips('open') . '
                  <div class="ffil">
                    <span class="ib grow"><span class="swap"><span class="gph a-out" style="' . $d(14, 'Or type') . '">Search order number, account, their reference, label name or invoice</span>
                      <span class="a-fade" style="' . $d(14, 'Or type') . '"><span class="a-type" style="' . $d(14, 'Or type', .2) . ';--ts:7;--tt:.8s">PO 4471</span></span></span></span>
                    <span class="ib sel a-ring" style="' . $d(14, 'Choose an account') . '"><span class="swap"><span class="a-out" style="' . $d(14, 'Choose an account', .8) . '">All accounts</span><span class="a-fade" style="' . $d(14, 'Choose an account', .8) . '">ABC Blinds</span></span></span>
                    <span class="btns a-press" style="' . $d(14, 'Then click Search') . '">Search</span>
                  </div>
                  <div class="otab">' . $thead . $row($ROWS[0]) . $row($ROWS[1], 'a-out', $d(14, 'Choose an account', 1)) . $row($ROWS[3], 'a-out', $d(14, 'Choose an account', 1)) . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(14, 'An order number') . '">order number</span><span class="chip a-pop" style="' . $d(14, 'the account,') . '">account</span>
                    <span class="chip a-pop" style="' . $d(14, 'their reference') . '">their reference</span><span class="chip a-pop" style="' . $d(14, 'the name for') . '">name for the labels</span>
                    <span class="chip a-pop" style="' . $d(14, 'an invoice number') . '">invoice number</span>
                  </div>
                </div>

                <!-- 15 — Making and Money -->
                <div class="sc" data-scene="15" data-len="' . $len(15) . '">
                  <div class="sct a-fade" style="--d:.2s">Making and Money</div>
                  <div class="otab" style="margin-top:.4rem">' . $thead
                    . str_replace('<small class="fiv">', '<small class="fiv a-ring" style="' . $d(15, 'Under the money pill') . '">', $row($ROWS[3], 'a-fly', $d(15, 'Each row')))
                    . $row($ROWS[2], 'a-fly', $d(15, 'A purple REMAKE')) . '</div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(15, 'the order number') . '">order &middot; account &middot; their ref &middot; date</span>
                    <span class="chip a-pop" style="' . $d(15, 'how many of our') . '">our blinds &middot; value</span>
                    <span class="chip a-pop" style="' . $d(15, 'Under the money pill') . '">invoice &middot; what&rsquo;s owed &middot; when it&rsquo;s due</span>
                    <span class="chip a-pop" style="' . $d(15, 'A purple REMAKE') . ';border-color:#7c3aed">REMAKE &mdash; a remake of an earlier order</span>
                  </div>
                  <div class="legend a-rise" style="' . $d(15, 'Then Making is') . '"><b>Making</b> &mdash; where the blinds are
                    <div class="chips" style="margin-top:.25rem">' . $pill('new', 'a-pop', $d(15, 'Then Making is', .3)) . $pill('confirmed', 'a-pop', $d(15, 'Then Making is', .6)) . $pill('in_production', 'a-pop', $d(15, 'Then Making is', .9)) . $pill('awaiting_supplier', 'a-pop', $d(15, 'Then Making is', 1.2)) . $pill('ready', 'a-pop', $d(15, 'Then Making is', 1.5)) . $pill('dispatched', 'a-pop', $d(15, 'Then Making is', 1.8)) . '</div></div>
                  <div class="legend a-rise" style="' . $d(15, 'and Money is') . '"><b>Money</b> &mdash; where the bill is
                    <div class="chips" style="margin-top:.25rem">' . $mpill('not_invoiced', 'a-pop', $d(15, 'and Money is', .3)) . $mpill('needs_invoicing', 'a-pop', $d(15, 'and Money is', .6)) . $mpill('awaiting', 'a-pop', $d(15, 'and Money is', .9)) . $mpill('part_paid', 'a-pop', $d(15, 'and Money is', 1.2)) . $mpill('overdue', 'a-pop', $d(15, 'and Money is', 1.5)) . $mpill('paid', 'a-pop', $d(15, 'and Money is', 1.8)) . '</div></div>
                </div>

                <!-- 16 — what to do next -->
                <div class="sc" data-scene="16" data-len="' . $len(16) . '">
                  <div class="sct a-fade" style="--d:.2s">What to do next</div>
                  <div class="otab" style="margin:.4rem 0 .7rem">' . $thead . $row($ROWS[0]) . $row($ROWS[6]) . $row($ROWS[5]) . $row($ROWS[7]) . '</div>
                  <div class="two">
                    <div class="card a-rise" style="' . $d(16, 'While it is being made') . '"><h4>While it&rsquo;s being made</h4>
                      <div class="nseq">' . $next('Mark received', false, true, 'a-pop', $d(16, 'Mark received,')) . '<i>&rarr;</i>'
                        . $next('Start production', false, true, 'a-pop', $d(16, 'Start production,')) . '<i>&rarr;</i>'
                        . $next('Being made', false, false, 'a-pop', $d(16, 'Being made,')) . '<i>&rarr;</i>'
                        . $next('Order from Hunter Douglas', true, true, 'a-pop', $d(16, 'Order from a supplier')) . '<i>or</i>'
                        . $next('Waiting on Hunter Douglas', false, true, 'a-pop', $d(16, 'Waiting on a supplier')) . '<i>&rarr;</i>'
                        . $next('Dispatch', false, true, 'a-pop', $d(16, 'then Dispatch')) . '</div></div>
                    <div class="card a-rise" style="' . $d(16, 'After that comes') . '"><h4>Then the money</h4>
                      <div class="nseq">' . $next('Raise invoice', true, true, 'a-pop', $d(16, 'Raise invoice,')) . '<i>&rarr;</i>'
                        . $next('Wait for payment', false, true, 'a-pop', $d(16, 'Wait for payment,')) . '<i>or</i>'
                        . $next('Wait for the rest', false, true, 'a-pop', $d(16, 'Wait for the rest')) . '</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip bad a-pop" style="' . $d(16, 'If a payment is late') . '">Payment late? <b>Chase payment</b> comes first</span>
                    <span class="chip ok a-pop" style="' . $d(16, 'Done means') . '"><b>Done</b> = dispatched and paid</span>
                  </div>
                  <div class="a-move" style="--fx:20%;--fy:95%;--tx:86%;--ty:5rem;' . $d(16, 'Click it to go') . ';--md:1.3s">' . $ptr . '</div>
                </div>

                <!-- 17 — opening an order -->
                <div class="sc" data-scene="17" data-len="' . $len(17) . '">
                  <div class="sct a-fade" style="--d:.2s">Opening an order</div>
                  <div class="otab" style="margin:.4rem 0 .8rem">' . $thead
                    . str_replace(['<b class="lnk">ABC-2026-0041</b>', '<span class="frm">&#8635; Remake</span>'],
                                  ['<b class="lnk a-ring" style="' . $d(17, 'Click the order') . '">ABC-2026-0041</b>', '<span class="frm a-ring" style="' . $d(17, 'Remake link') . '">&#8635; Remake</span>'], $row($ROWS[0]))
                    . $row($ROWS[4]) . '</div>
                  <div class="two">
                    <div class="card a-rise" style="' . $d(17, 'An order an account') . '"><h4>An account&rsquo;s order</h4>opens in the factory&rsquo;s <b>Edit order</b> screen</div>
                    <div class="card a-rise" style="' . $d(17, 'Our own orders') . '"><h4>Our own orders &middot; Quotes</h4>open in the <b>quote builder</b></div>
                  </div>
                  <span class="chip a-pop" style="' . $d(17, 'the Remakes guide') . ';margin-top:.7rem;border-color:#7c3aed">&#8635; Remake &mdash; see &ldquo;Remakes&rdquo;</span>
                  <div class="a-move" style="--fx:70%;--fy:90%;--tx:6%;--ty:4.3rem;' . $d(17, 'Click the order', -.8) . ';--md:1s">' . $ptr . '</div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Why it exists.</b> When a trade account places an order on their own portal, it is stored <b>once, as their order</b>.
             Nothing on it says &ldquo;factory&rdquo; except its lines &mdash; the blinds belong to the factory. The factory&rsquo;s own
             screens (Incoming orders, Dispatch, Invoices) always found orders that way, but the retail-shaped screens (Dashboard, Pipeline,
             the Orders list) only listed the factory&rsquo;s own jobs, so they could never see an account&rsquo;s order. The console reads
             orders the factory way: <b>every placed order with at least one of our blinds on it, from any account</b>, plus our own
             quotes that are not placed yet.</p>

          <p><b>Who gets it.</b> The office staff signed in to the factory account. When you sign in you land on
             <b>Work &rarr; Dashboard</b>, and the menu reads <b>Factory Console</b> under the logo. Open one of the old screens and it takes
             you to the console one instead: the sales dashboard &rarr; <b>Dashboard</b>; the orders and quotes lists and the sales Pipeline &rarr;
             <b>Orders</b> (quotes land on <b>Quotes</b>); the old calendar &rarr; the office <b>Calendar</b>. Nobody else is affected.</p>

          <ul class="steps">
            <li><b>Work</b> &mdash; <b>Dashboard</b>, <b>Pipeline</b> (every open order as a card, New to Paid &mdash; see the <b>Factory Pipeline</b> guide), <b>Orders</b>, <b>Quotes</b> (our own, not placed yet), <b>Remakes</b> (reads <b>Remakes (2 to approve)</b> when accounts are
                waiting), <b>Calendar</b> (reads <b>Calendar (3 due)</b> when reminders, callbacks or remakes are due).</li>
            <li><b>Production</b> &mdash; <b>Incoming orders</b>, <b>Floor</b>, <b>Dispatch</b>.</li>
            <li><b>Accounts</b> &mdash; <b>Trade accounts</b> (super-admin only), <b>Invoices</b>, <b>Bank</b>, <b>Statements</b>,
                <b>Commissions</b>, <b>The Numbers</b> (super-admin only — what you make on the blinds, at your real cost: today, this month, this year or any dates you choose).</li>
            <li><b>Setup</b> &mdash; <b>Products</b>, <b>Users</b>, <b>Settings</b> (admins) and <b>Factory settings</b>; then
                <b>Platform</b> for the super-admin.</li>
          </ul>

          <p><b>The Dashboard.</b> A greeting and today&rsquo;s date, an <b>All orders</b> button, then the tiles:</p>
          <ul class="steps">
            <li><b>New</b> &mdash; <em>Not received on the floor yet</em> (opens <b>Incoming orders</b>). <b>Received</b> &mdash; <em>In, not
                started</em>. <b>In production</b> &mdash; <em>Being made</em>. <b>Awaiting supplier</b> &mdash; <em>Made, waiting on a bought-in item</em>. <b>Ready to dispatch</b> &mdash; <em>Everything made and in</em>
                (opens <b>Dispatch</b>). Received, In production and Awaiting supplier open the Orders list on that chip.</li>
            <li>The money tiles: <b>Needs invoicing</b> &mdash; <em>Dispatched, no invoice yet</em>. <b>Awaiting payment</b> &mdash;
                <em>Invoiced, not paid yet</em>. <b>Overdue</b> &mdash; <em>Past the invoice due date</em>. Each opens the Orders list on
                that chip.</li>
            <li>If any of our own quotes are not placed yet, a line under the tiles says so &mdash; e.g. <em>2 of our own quotes
                not placed yet.</em> &mdash; and opens Orders on <b>Quotes</b>.</li>
            <li><b>Calendar</b> &mdash; how many are <b>due now</b> (reminders, callbacks, remakes), or <em>Nothing due</em>. Opens the office
                Calendar.</li>
            <li><b>Remakes</b> &mdash; remakes in progress, with <b>n waiting for approval</b> underneath (or <em>In progress &middot; none
                waiting</em>). Opens Remakes.</li>
            <li><b>Blinds to make</b> &mdash; <em>On orders not yet ready</em>. <b>Out today</b> &mdash; <em>Delivery notes dispatched</em>
                (opens Dispatch).</li>
            <li><b>Orders this week</b> &mdash; <em>Placed since Monday</em>. <b>Invoiced this week</b> &mdash; <em>Net, since Monday</em>,
                with <b>owed</b> (everything still unpaid on our invoices) underneath. Opens Invoices.</li>
            <li><b>Latest orders</b> &mdash; the eight newest placed orders: Order, Account, Blinds, Making, Money, with <b>See all</b>.
                <b>Busiest accounts</b> &mdash; <em>last 30 days</em>, the top five by value: Account, Orders, Value.</li>
          </ul>

          <p><b>The Orders list</b> (<b>Work &rarr; Orders</b>). <em>Every order placed with us, whoever placed it: from their portal, as a
             direct order, or keyed in here.</em> <b>+ New order</b> (top right) keys one in.</p>
          <ul class="steps">
            <li><b>Chips</b>, in groups, each with a count: <b>Open</b> (anything not yet both dispatched and paid &mdash; where the
                list starts); <b>To make</b> &mdash; <b>New</b>, <b>Received</b>, <b>In production</b>, <b>Awaiting supplier</b>, <b>Ready to go</b>;
                <b>To bill</b> &mdash; <b>Needs invoicing</b>, <b>Awaiting payment</b> (part paid too), <b>Overdue</b> (these two
                show red while they have any); <b>Finished</b> &mdash; <b>Done</b> (dispatched and paid); <b>Not placed</b> &mdash;
                <b>Quotes</b>; and <b>All</b>.</li>
            <li><b>All accounts</b> picker &mdash; choose one to see only theirs (our own sales show as <b>One-off sale</b>).</li>
            <li><b>Search</b> &mdash; <em>order number, account, their reference, label name or invoice</em>. Nothing found says
                <em>Nothing matches.</em> with <b>Show all open orders</b>.</li>
            <li>Columns: <b>Order</b> (with the label name under it), <b>Account</b>, <b>Their ref</b>, <b>Date</b>, <b>Blinds</b> (ours on
                it), <b>Value</b> (what the invoice bills), <b>Making</b>, <b>Money</b>, <b>Next</b>. On a narrower screen Their ref
                and Date are hidden, and on a phone Value too.</li>
            <li><b>Making</b> &mdash; where the blinds are: <b>Quotes</b>, <b>New</b>, <b>Received</b>, <b>In production</b>, <b>Awaiting supplier</b> (our part made, a bought-in item still to come),
                <b>Ready</b>, <b>Dispatched</b>.</li>
            <li><b>Money</b> &mdash; where the bill is: <b>Not invoiced</b>, <b>Needs invoicing</b> (dispatched, no invoice yet),
                <b>Invoiced &middot; awaiting payment</b>, <b>Part paid</b>, <b>Overdue</b>, <b>Paid</b>. Under it, each invoice number
                (opens the PDF) with what&rsquo;s owed and when it&rsquo;s due &mdash; e.g. <em>INV-BEV-2026-0007 &middot; &pound;343.68
                owed, due 1 Nov</em>.</li>
            <li><b>Next</b> &mdash; the one thing to do next: <b>Mark received</b>, <b>Start production</b>, <b>Being made</b>, <b>Order from &lt;supplier&gt;</b> (red) or <b>Waiting on &lt;supplier&gt;</b> once the floor is done,
                <b>Dispatch</b>, then <b>Raise invoice</b>, <b>Wait for payment</b> or <b>Wait for the rest</b>, and <b>Done</b> once
                dispatched and paid. A late payment shows <b>Chase payment</b> first. <b>Raise invoice</b> and <b>Chase payment</b> are red.
                A quote not placed yet shows <b>Place the order</b>. Click it to go to the right screen.</li>
            <li>A purple <b>REMAKE</b> badge marks a remake order (hover it: <em>Remake of &hellip;</em>).</li>
            <li><b>&#8635; Remake</b> in the Next column of every placed order raises a remake on it &mdash; see the <b>Remakes</b> guide.</li>
            <li><b>The Money side is on the main factory account only.</b> Anyone else (a white-label factory) sees no money tiles, no
                Invoiced this week, no Money column and no To bill chips; for them <b>Open</b> means not dispatched yet and <b>Done</b>
                means dispatched.</li>
            <li>The list shows the newest 400 orders.</li>
          </ul>

          <p><b>Where an order opens.</b> An order an account placed opens in the factory&rsquo;s <b>Edit order</b> screen. Our own orders
             (one-off sales) and anything on the <b>Quotes</b> chip open in the <b>quote builder</b>.</p>

          <p><b>Waiting on suppliers.</b> An order whose own blinds are all made but which is still waiting on a bought-in item reads
             <b>Awaiting supplier</b> (display only &mdash; it is still in production underneath). The <b>Dispatch tray</b>
             (<b>Production &rarr; Dispatch</b>) lists these under <b>Waiting on suppliers</b>, below <b>Ready to go out</b>: Account, Order,
             Their ref, Blinds, Made (e.g. <em>20/20 made</em>, or <em>Nothing to make</em>) and Waiting on (e.g. <em>Home Creations &middot;
             ordered 9 Oct</em>, or <em>not ordered yet</em> in red). They can&rsquo;t be ticked; when the item arrives, mark it received on
             <b>Incoming orders</b> and the order moves up to <b>Ready to go out</b>.</p>

          <div class="heads"><span class="hi">&#9432;</span><div><b>Nothing here changes an order.</b> The Dashboard and the Orders list
             only read &mdash; Making moves on from the floor, dispatch and delivery notes, and Money from the invoices and payments,
             exactly as before.</div></div>',
        'script'  => $S,
];
