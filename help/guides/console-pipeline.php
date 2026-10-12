<?php
declare(strict_types=1);

/**
 * Guide: console-pipeline — "The Factory Pipeline: where's my order?" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Audience 'factory': Factory Console users and the super-admin only.
 *
 * Mirrors:
 *   - factory/pipeline.php — the columns and their hints ($COLS), Dispatched
 *     instead of the money columns for non-accounts users, Paid / Dispatched
 *     cards leaving after 7 days, the summary pills, the search box, account
 *     picker, Find / Clear, the card (number, value, account · ref, REMAKE,
 *     Due, lines, money pill + invoices), each line's status ($lineStatus), the
 *     one button ($action), the footer;
 *   - _partials/order_tracks.php — ot_next_jobs(): the order the jobs come in
 *     (process, retry, order_new, order, start, receive, dispatch, floor);
 *   - _partials/factory_console.php — fc_money_meta() pills, fc_money_visible();
 *   - _partials/process_order.php + factory/process-order.php — Process order,
 *     its summary, Retry, Order new item from;
 *   - factory/set-status.php — the "Can't dispatch yet" check;
 *   - master-admin/dispatch.php — the Dispatch tray.
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step). Start times are
 * worked out from where a phrase falls in the voice-over ($at), at Alice's
 * ~13.6 characters a second, and data-len from the line's length ($len).
 */

// ── The voice-over first: scene timings are read from it ───────────────
$S = [
    ['1', 'What the Pipeline is for', 'The Pipeline is the quickest way to answer the question, where\'s my order? Open it from the Work menu. It shows every open order in columns, from left to right. New is on the left, and Paid is on the right. Each order is one short line. Click it to open its card, which says what the order is waiting on, and gives you the one next step to take.', 1],
    ['2', 'The making columns', 'The first five columns follow the making. New means placed, but not taken in yet. Received means it is in, but not started on the floor. In production means it is being made. Awaiting supplier means our part is made, but a bought-in item has not arrived yet. And Ready to go means everything is made and in.', 2],
    ['3', 'The money columns', 'Then come the money columns. Dispatched, to invoice, means it has gone out, with no invoice yet. Awaiting payment means it is invoiced, but not paid. Paid shows the last seven days, then the card leaves the board, though it stays on the Orders list. These columns only show for the main factory\'s accounts staff. Everyone else sees one Dispatched column instead.', 3],
    ['4', 'Counts and order', 'Each column has a count at the top, and a short line saying what it holds. The oldest card sits at the top, so the order that has waited longest comes first. Above the board, a row of totals says how many orders are open, how many are waiting on suppliers, and how many are ready to go. Overdue invoices show there too, in red.', 4],
    ['5', 'One line per order', 'To keep the board tidy, each order is one line, showing just its order number. A red dot means something needs chasing: a bought-in item not ordered or not in yet, or money that needs invoicing or is overdue. Click the number to open its card, and click again to close it. Open all and Close all, at the top right, do every card at once.', 5],
    ['6', 'What an open card shows', 'Now look at an open card. At the top is Open order. Click it to open the order itself. Next to it is the value. Under that is the account, and their reference. A purple REMAKE badge marks a remake of an earlier order. Then comes the due date, so you can see at a glance when it has to go out.', 6],
    ['7', 'Each line has its own status', 'Under that, every line on the order has its own status. A blind made here says Not started, then Being made, three of five, then a tick and Made. A bought-in item says the supplier, not ordered yet, then Waiting on the supplier, with the date it was ordered. So on a mixed order, you can see exactly which part is holding it up.', 7],
    ['8', 'The money on a card', 'Once an order has an invoice, its card shows the money pill. Invoiced, awaiting payment, Part paid, Overdue, or Paid. Under the pill is each invoice number, what is still owed, and when it is due. Once it is paid, it shows the date it was paid instead.', 8],
    ['9', 'Remakes on the board', 'Remakes show up too. A remake\'s line has a purple R after the order number. Hover over the R to see which order it is a remake of. Above the board, the totals say how many remakes are in progress. If any are waiting for approval, a purple link takes you to the Remakes page. Those are not orders yet, so they have no card.', 9],
    ['10', 'One button: taking it in', 'Each card has one button, and it is always the next step. The same rules as Incoming orders decide it, so the two never disagree. A new order shows Process order. First, a summary says what will happen: which blinds go to the floor, and which suppliers are emailed their purchase order. Click Process order, and it does all of that in one go. While the floor is making it, the button says See progress, so you can see how far each blind has got.', 10],
    ['11', 'Bought-in items', 'Bought-in items are ordered by Process order, so there is nothing more to click. If a purchase order cannot go, say the supplier has no order email, the card says Couldn\'t order from, and the supplier\'s name, in red, with the reason. Fix it, then click Retry. An item added after processing shows New item to order. When the goods arrive, click Received from, and the supplier\'s name. Once everything is made and in, the card moves on to Ready to go.', 11],
    ['12', 'Sending it out', 'On a ready order, the button says Add to today\'s run. It takes you to the Dispatch tray, where the van run is ticked and printed, just as before. Once it has gone out, the card moves on to Dispatched, to invoice. If you do not see the money columns, the button simply says Dispatch.', 12],
    ['13', 'Getting paid', 'In the money columns, the button follows the bill. Raise invoice, in green, when it has gone out with no invoice. Record payment, while it is waiting to be paid, or part paid. And Chase payment, in red, once it is overdue. That opens the account, so you can see everything they owe.', 13],
    ['14', 'Cards move by their buttons', 'You cannot drag a card from one column to another. Cards move with their buttons, and with the work itself, like the floor finishing the blinds. That way nothing skips a check. For example, an order cannot be dispatched until every blind is made, and every bought-in item has been received.', 14],
    ['15', 'Column colours', 'You can choose the colour of each column. Click Colours, next to Open all and Close all. It opens Factory Settings, at the Pipeline colours card. There is one colour picker for each column. Each colour sets the column\'s top bar, its count, and a faint tint behind it. Click Save pipeline colours, or Reset to defaults to go back.', 15],
    ['16', 'Where\'s my order?', 'When a customer rings to ask where their order is, use the search box at the top. Type the account, the order number, their reference, the name, or an invoice number, then tap Find. The cards that match open up on their own. Or pick an account from the list, to see only their orders. Clear takes you back to the whole board. On a phone, swipe sideways to see every column.', 16],
];
$len = static fn (int $n): string => (string) round(mb_strlen($S[$n - 1][2]) / 13.6, 1);
/** Seconds into line $n at which $phrase is spoken (+ $add). */
$at = static function (int $n, string $phrase, float $add = 0.0) use ($S): string {
    $p = mb_strpos($S[$n - 1][2], $phrase);
    if ($p === false) { error_log('console-pipeline guide: "' . $phrase . '" not in line ' . $n); $p = 0; }
    return (string) round($p / 13.6 + $add, 1);
};
$d = static fn (int $n, string $phrase, float $add = 0.0): string => '--d:' . $at($n, $phrase, $add) . 's';

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// ── The columns, as factory/pipeline.php $COLS: key => [title, hint, colour] ──
$COLS = [
    'new'               => ['New',                     'Placed, not taken in yet',                      '#b91c1c'],
    'confirmed'         => ['Received',                'In, not started on the floor',                  '#5b6b7f'],
    'in_production'     => ['In production',           'Being made on the floor',                       '#b5730f'],
    'awaiting_supplier' => ['Awaiting supplier',       'Our part is made, waiting on a bought-in item', '#7c3aed'],
    'ready'             => ['Ready to go',             'Everything made and in',                        '#245ea3'],
    'to_invoice'        => ['Dispatched &middot; to invoice', 'Gone out, no invoice yet',               '#0d7a67'],
    'to_pay'            => ['Awaiting payment',        'Invoiced, not paid yet',                        '#1d4f8c'],
    'paid'              => ['Paid',                    'Last 7 days, then it leaves the board',         '#0d7a67'],
    'dispatched'        => ['Dispatched',              'Gone out in the last 7 days',                   '#0d7a67'],
];

// ── Money pills, as fc_money_meta() colours them ──────────────────────
$MPILL = [
    'needs_invoicing' => ['Needs invoicing',                    '#b91c1c', '#fee2e2'],
    'awaiting'        => ['Invoiced &middot; awaiting payment', '#245ea3', '#dde8f6'],
    'part_paid'       => ['Part paid',                          '#b5730f', '#f7ecd6'],
    'overdue'         => ['Overdue',                            '#ffffff', '#b91c1c'],
    'paid'            => ['Paid',                               '#ffffff', '#0d7a67'],
];
$mpill = static fn (string $k, string $cls = '', string $style = ''): string =>
    '<span class="fpill ' . $cls . '" style="color:' . $MPILL[$k][1] . ';background:' . $MPILL[$k][2] . ';' . $style . '">' . $MPILL[$k][0] . '</span>';
$rmb = '<span class="rmb">REMAKE</span>';

/**
 * The cards. Each: number, value, account, their ref, remake?, due, lines
 * [[what, status, tone]], money pill key|'' , invoice lines, button [label, colour class].
 */
$CARDS = [
    'A' => ['ABC-2026-0041', '&pound;412.80', 'ABC Blinds', 'PO 4471', false, 'Due 16 Oct',
            [['Roller Blind &times; 4', 'Not started', 'muted'], ['Vertical Blind &times; 2', 'Not started', 'muted']],
            '', [], ['Process order', '']],
    'B' => ['HUG-2026-0012', '&pound;101.15', 'Hughes Interiors', 'Kitchen', false, 'Due 15 Oct',
            [['Roller Blind &times; 2', 'Not started', 'muted']],
            '', [], ['Start production', '']],
    'C' => ['CST-2026-0009', '&pound;318.60', 'Coastal Shutters', 'CS-120', false, 'Due 14 Oct',
            [['Roller Blind &times; 3', 'Being made &middot; 1 of 3', 'amber'], ['PF Venetian &times; 1', 'Couldn&rsquo;t order from Hunter Douglas &middot; No order email is set for Hunter Douglas (Settings &rsaquo; Suppliers).', 'bad']],
            '', [], ['Retry Hunter Douglas', 'bad']],
    // C once the Hunter Douglas item is in: the floor is still making the rollers.
    'C2' => ['CST-2026-0009', '&pound;318.60', 'Coastal Shutters', 'CS-120', false, 'Due 14 Oct',
            [['Roller Blind &times; 3', 'Being made &middot; 1 of 3', 'amber'], ['PF Venetian &times; 1', '&#10003; Received from Hunter Douglas', 'ok']],
            '', [], ['See progress', 'ghost']],
    'D' => ['ABC-2026-0039', '&pound;530.00', 'ABC Blinds', 'PO 4420', false, 'Due 13 Oct',
            [['Vertical Blind &times; 20', '&#10003; Made', 'ok'], ['Forest Wood &times; 2', 'Waiting on Home Creations &middot; ordered 9 Oct', 'bad']],
            '', [], ['Received from Home Creations', 'purple']],
    'E' => ['ABC-2026-0038-R1', '&pound;0.00', 'ABC Blinds', 'PO 4402', true, 'Due 12 Oct',
            [['Roller Blind &times; 1', '&#10003; Made', 'ok']],
            '', [], ['Add to today&rsquo;s run &rarr;', 'blue']],
    'F' => ['CST-2026-0006', '&pound;140.00', 'Coastal Shutters', 'CS-117', false, '',
            [['Roller Blind &times; 2', '&#10003; Dispatched', 'ok']],
            'needs_invoicing', [], ['Raise invoice', 'green']],
    'G' => ['CST-2026-0007', '&pound;286.40', 'Coastal Shutters', 'CS-118', false, '',
            [['Roller Blind &times; 4', '&#10003; Dispatched', 'ok']],
            'awaiting', ['INV-BEV-2026-0007 &middot; &pound;343.68 owed, due 1 Nov'], ['Record payment', '']],
    'H' => ['HUG-2026-0009', '&pound;198.50', 'Hughes Interiors', 'Lounge', false, '',
            [['Vertical Blind &times; 3', '&#10003; Dispatched', 'ok']],
            'overdue', ['INV-BEV-2026-0003 &middot; &pound;238.20 owed, due 30 Sep'], ['Chase payment', 'bad']],
    'I' => ['ABC-2026-0031', '&pound;350.25', 'ABC Blinds', 'PO 4310', false, '',
            [['Roller Blind &times; 5', '&#10003; Dispatched', 'ok']],
            'paid', ['INV-BEV-2026-0001 &middot; paid 8 Oct'], []],
];

/**
 * One card, as the real <details>: a one-line summary (caret, order number, red
 * dot when something needs chasing) and, when $open, the card underneath.
 * $o = optional per-part animation: ['card' => [cls, style], 'num' =>, 'dot' =>,
 * 'link' =>, 'val' =>, 'acc' =>, 'rm' =>, 'due' =>, 'line0' =>, 'line1' =>,
 * 'money' =>, 'inv' =>, 'btn' =>].
 * $noMoney = drawn as a non-accounts user sees it (no value, no money).
 */
$card = static function (string $k, array $o = [], bool $noMoney = false, bool $open = true) use ($CARDS, $mpill, $rmb): string {
    [$num, $val, $acc, $ref, $rm, $due, $lines, $mon, $invs, $btn] = $CARDS[$k];
    $a = static function (string $part) use ($o): array { return $o[$part] ?? ['', '']; };
    // The red dot: a line whose status is red, or money needing invoicing / overdue.
    $urgent = !$noMoney && in_array($mon, ['needs_invoicing', 'overdue'], true);
    foreach ($lines as [, , $tone]) if ($tone === 'bad') $urgent = true;
    [$c, $s] = $a('card');
    $h = '<div class="pcd' . ($open ? ' open' : '') . ' ' . $c . '" style="' . $s . '">';
    [$c, $s] = $a('num'); [$c2, $s2] = $a('dot');
    $h .= '<div class="psm"><span class="car">' . ($open ? '&#9662;' : '&#9656;') . '</span><b class="pnum ' . $c . '" style="' . $s . '">' . $num . '</b>'
        . ($rm ? '<span class="prm ' . ($o['r'][0] ?? '') . '" style="' . ($o['r'][1] ?? '') . '" title="Remake of ABC-2026-0038">R</span>' : '')
        . ($urgent ? '<span class="pdot ' . $c2 . '" style="' . $s2 . '"></span>' : '') . '</div>';
    if (!$open) return $h . '</div>';
    $h .= '<div class="pbody">';
    [$c, $s] = $a('link'); [$c2, $s2] = $a('val');
    $h .= '<div class="prw"><span class="plink ' . $c . '" style="' . $s . '">Open order &rarr;</span>'
        . ($noMoney ? '' : '<span class="pval ' . $c2 . '" style="' . $s2 . '">' . $val . '</span>') . '</div>';
    [$c, $s] = $a('acc'); [$c2, $s2] = $a('rm');
    $h .= '<div class="pacc"><span class="' . $c . '" style="' . $s . '">' . $acc . ' <span class="pmeta">&middot; ' . $ref . '</span></span>'
        . ($rm ? ' <span class="' . $c2 . '" style="' . $s2 . '">' . $rmb . '</span>' : '') . '</div>';
    if ($due !== '') { [$c, $s] = $a('due'); $h .= '<div class="pmeta"><span class="' . $c . '" style="' . $s . '">' . $due . '</span></div>'; }
    $h .= '<ul class="plns">';
    foreach ($lines as $i => [$what, $st, $tone]) {
        [$c, $s] = $a('line' . $i);
        $h .= '<li class="' . $c . '" style="' . $s . '"><span class="what">' . $what . '</span><span class="' . $tone . '">' . $st . '</span></li>';
    }
    $h .= '</ul>';
    if (!$noMoney && $mon !== '') {
        [$c, $s] = $a('money'); [$c2, $s2] = $a('inv');
        $h .= '<div class="pmon"><span class="' . $c . '" style="' . $s . '">' . $mpill($mon) . '</span>';
        foreach ($invs as $iv) $h .= '<div class="pmeta ' . $c2 . '" style="' . $s2 . '">' . $iv . '</div>';
        $h .= '</div>';
    }
    // A non-accounts user's Dispatched card has no button (no money side).
    if ($btn && !($noMoney && $mon !== '')) {
        [$c, $s] = $a('btn');
        $label = $btn[0];
        if ($noMoney && $k === 'E') { $label = 'Dispatch'; }
        $h .= '<span class="pbt ' . $btn[1] . ' ' . $c . '" style="' . $s . '">' . $label . '</span>';
    }
    return $h . '</div></div>';
};

/**
 * A column. $cards = card keys (or ready HTML strings starting with '<');
 * $o = ['col' => [cls, style], 'head' => [cls, style]].
 */
$col = static function (string $key, array $cards, array $o = [], string $count = '') use ($COLS, $card): string {
    [$t, $hint, $c] = $COLS[$key];
    [$cc, $cs] = $o['col'] ?? ['', ''];
    [$hc, $hs] = $o['head'] ?? ['', ''];
    $h = '<div class="pcol ' . $cc . '" style="--pc:' . $c . ';background:rgba(' . implode(',', array_map('hexdec', str_split(ltrim($c, '#'), 2))) . ',.07);' . $cs . '">'
       . '<header><span class="pt2 ' . $hc . '" style="' . $hs . '">' . $t . '</span><span class="pn">' . ($count !== '' ? $count : count($cards)) . '</span></header>'
       . '<p class="phn">' . $hint . '</p>';
    if (!$cards) $h .= '<p class="pem">Nothing here.</p>';
    // On the board a card is closed (one line) unless a scene hands in ready HTML.
    foreach ($cards as $k) $h .= str_starts_with($k, '<') ? $k : $card($k, [], false, false);
    return $h . '</div>';
};

/** The search bar, as the real one: search box, All accounts select, Find. */
$find = static fn (string $q = '', string $acc = 'All accounts', string $extra = ''): string =>
    '<div class="pfind"><span class="ib grow">' . ($q !== '' ? $q : '<span class="gph">Where&rsquo;s my order? Account, order no., their ref, name&hellip;</span>') . '</span>'
    . '<span class="ib sel">' . $acc . '</span><span class="btns">Find</span>' . $extra . '</div>';

$sum = static fn (array $anim = []): string =>
    '<div class="psum">'
    . '<span class="' . ($anim[0][0] ?? '') . '" style="' . ($anim[0][1] ?? '') . '"><b>8</b> open orders</span>'
    . '<span class="' . ($anim[6][0] ?? '') . '" style="' . ($anim[6][1] ?? '') . '"><b>1</b> remake in progress <span class="prm">R</span></span>'
    . '<span class="prmlink ' . ($anim[7][0] ?? '') . '" style="' . ($anim[7][1] ?? '') . '"><b>2</b> remakes waiting for approval &rarr;</span>'
    . '<span class="' . ($anim[1][0] ?? '') . '" style="' . ($anim[1][1] ?? '') . '"><b>1</b> waiting on suppliers</span>'
    . '<span class="' . ($anim[2][0] ?? '') . '" style="' . ($anim[2][1] ?? '') . '"><b>1</b> ready to go</span>'
    . '<span class="od ' . ($anim[3][0] ?? '') . '" style="' . ($anim[3][1] ?? '') . '"><b>1</b> overdue</span>'
    . '<span class="ptools"><span class="btns sm ' . ($anim[4][0] ?? '') . '" style="' . ($anim[4][1] ?? '') . '">Open all</span>'
    . '<span class="btns sm ' . ($anim[5][0] ?? '') . '" style="' . ($anim[5][1] ?? '') . '">Close all</span>'
    . '<span class="btns sm ' . ($anim[8][0] ?? '') . '" style="' . ($anim[8][1] ?? '') . '">Colours</span></span></div>';

$head = '<div class="ph"><div><div class="pt">Pipeline</div>'
      . '<div class="psub">Every open order, left to right from new to paid. Each card says what it&rsquo;s waiting on and gives the next step.</div></div></div>';

// The whole board at rest (poster), compact.
$fullBoard = '<div class="pbd sm">'
    . $col('new', ['A']) . $col('confirmed', ['B']) . $col('in_production', ['C']) . $col('awaiting_supplier', ['D'])
    . $col('ready', ['E']) . $col('to_invoice', ['F']) . $col('to_pay', ['G', 'H']) . $col('paid', ['I'])
    . '</div>';

return [
        'aud'     => 'factory',
        'section' => 'Factory Console',
        'title'   => 'The Factory Pipeline: where&rsquo;s my order?',
        'eyebrow' => 'Factory Console',
        'v'       => 2,
        'blurb'   => 'Every open order as a card, from New to Paid: the columns, what a card and each of its lines says, the one next-step button, and finding an order fast with the search box and account picker.',
        'lede'    => 'The <b>Pipeline</b> answers <b>&ldquo;where&rsquo;s my order?&rdquo;</b> in one look. Every open order sits on it as a
                      <b>card</b>, in columns from <b>New</b> on the left to <b>Paid</b> on the right. Each card shows every line with its own status
                      &mdash; made here or bought in &mdash; and gives you <b>one button</b>: the next step. This guide walks through it
                      <b>slowly</b>, one idea per chapter. To get there: <b>Work &rarr; Pipeline</b>.',
        'open'    => '/factory/pipeline.php',
        'css'     => '
          .gd .stage{ min-width:0; } .gd .sc{ position:relative; min-height:400px; min-width:0; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .ph{ display:flex; justify-content:space-between; gap:.6rem; margin-bottom:.45rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.62rem; color:var(--faint); margin:.1rem 0 0; max-width:30rem; line-height:1.4; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.7rem; font-weight:600; white-space:nowrap; }
          .gd .ib{ display:inline-flex; align-items:center; min-height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.66rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.grow{ flex:1 1 12rem; min-width:0; }
          .gd .ib.sel{ padding-right:1.2rem; } .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.35rem; color:var(--faint); font-size:.64rem; }
          .gd .gph{ color:var(--faint); }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .pfind{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; margin:0 0 .5rem; }
          .gd .psum{ display:flex; flex-wrap:wrap; gap:.35rem; margin:0 0 .55rem; font-size:.6rem; }
          .gd .psum > span{ padding:.12rem .5rem; border-radius:999px; background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .psum b{ color:var(--ink); }
          .gd .psum .od{ background:#fee2e2; color:#991b1b; border-color:#fecaca; } .gd .psum .od b{ color:#991b1b; }

          /* the board */
          .gd .pbd{ display:grid; grid-auto-flow:column; grid-auto-columns:minmax(0,1fr); gap:.4rem; align-items:start; overflow-x:auto; padding-bottom:.3rem; font-size:.6rem; }
          .gd .pbd.sm{ font-size:.52rem; gap:.25rem; }
          .gd .pbd.big{ font-size:.72rem; }
          .gd .pcol{ background:var(--panel); border-radius:9px; padding:.4em; display:flex; flex-direction:column; gap:.45em; min-height:8em; min-width:0; }
          .gd .pcol header{ display:flex; justify-content:space-between; align-items:center; gap:.3em; padding:0 .1em .3em; border-bottom:3px solid var(--pc); }
          .gd .pt2{ font-weight:700; font-size:1.05em; color:var(--ink); border-radius:4px; line-height:1.2; }
          .gd .pn{ font-size:.92em; font-weight:700; color:var(--pc); background:var(--surface); border-radius:999px; padding:0 .45em; }
          .gd .phn{ margin:0 .1em; font-size:.88em; color:var(--faint); line-height:1.3; }
          .gd .pem{ margin:.1em; font-size:.95em; color:var(--faint); }
          .gd .pcd{ background:var(--surface); border:1px solid var(--line); border-radius:7px; min-width:0; }
          .gd .pcd.open{ box-shadow:0 2px 8px rgba(15,23,42,.12); }
          .gd .psm{ display:flex; align-items:center; gap:.35em; padding:.25em .5em; white-space:nowrap; overflow:hidden; }
          .gd .psm .car{ font-size:.9em; color:var(--faint); }
          .gd .psm .pnum{ overflow:hidden; text-overflow:ellipsis; }
          .gd .prm{ display:inline-block; flex:none; font-size:.8em; font-weight:800; line-height:1; color:#fff; background:#7c3aed; border-radius:4px; padding:.15em .3em; }
          .gd .psum > .prmlink{ background:#ede9fe; color:#5b21b6; border-color:#ede9fe; font-weight:600; } .gd .psum > .prmlink b{ color:#5b21b6; }
          .gd .ptip{ position:absolute; z-index:3; left:30%; top:105%; background:#1f2937; color:#fff; font-size:.6rem; padding:.2rem .45rem; border-radius:5px; white-space:nowrap; }
          .gd .focr{ display:grid; grid-template-columns:1fr 1.6rem 3.6rem; gap:.4rem; align-items:center; padding:.2rem .4rem; margin:0 0 .2rem; border-radius:6px; font-size:.6rem; color:var(--ink); font-weight:600; }
          .gd .focr .sw{ width:1.4rem; height:.95rem; border:1px solid var(--border-strong,#9aa3ad); border-radius:3px; }
          .gd .focr .hx{ font-family:monospace; font-weight:400; color:var(--soft); }
          .gd .pdot{ width:.6em; height:.6em; border-radius:50%; background:#b91c1c; flex:none; margin-left:auto; }
          .gd .pbody{ padding:0 .55em .55em; display:flex; flex-direction:column; gap:.3em; }
          .gd .plink{ font-weight:700; font-size:1.05em; color:var(--ink); border-radius:4px; }
          .gd .psum > .ptools{ display:flex; gap:.3rem; margin-left:auto; padding:0; border:0; background:none; }
          .gd .btns.sm{ padding:.15rem .5rem; font-size:.6rem; }
          .gd .prw{ display:flex; justify-content:space-between; gap:.3em; align-items:baseline; }
          .gd .pnum{ font-weight:700; font-size:1.05em; color:var(--ink); border-radius:4px; white-space:nowrap; }
          .gd .pval{ font-size:.95em; font-weight:600; color:var(--soft); white-space:nowrap; border-radius:4px; }
          .gd .pacc{ font-size:.95em; color:var(--ink); } .gd .pacc > span{ border-radius:4px; }
          .gd .pmeta{ font-size:.88em; color:var(--faint); } .gd .pmeta span{ border-radius:4px; }
          .gd .plns{ list-style:none; margin:0; padding:.35em 0 0; border-top:1px solid var(--line); display:flex; flex-direction:column; gap:.3em; }
          .gd .plns li{ display:flex; flex-direction:column; line-height:1.3; font-size:.92em; border-radius:4px; }
          .gd .plns .what{ color:var(--ink); font-weight:500; }
          .gd .plns .ok{ color:#0d7a67; font-weight:600; } .gd .plns .bad{ color:#b91c1c; font-weight:700; }
          .gd .plns .amber{ color:#9a5b00; font-weight:600; } .gd .plns .muted{ color:var(--faint); }
          .gd .pmon{ font-size:.92em; line-height:1.5; } .gd .pmon > span{ border-radius:999px; }
          .gd .fpill{ display:inline-block; padding:.05em .55em; font-size:.92em; font-weight:700; border-radius:999px; white-space:nowrap; }
          .gd .rmb{ display:inline-block; font-size:.82em; font-weight:800; letter-spacing:.04em; border-radius:999px; padding:.05em .5em; color:#fff; background:#7c3aed; white-space:nowrap; }
          .gd .pbt{ display:block; text-align:center; font-weight:600; font-size:.95em; padding:.55em .4em; border-radius:7px; background:#1f3b5b; color:#fff; line-height:1.25; }
          .gd .pbt.bad{ background:#b91c1c; } .gd .pbt.purple{ background:#7c3aed; } .gd .pbt.blue{ background:#245ea3; } .gd .pbt.green{ background:#0d7a67; }
          .gd .pbt.ghost{ background:transparent; color:var(--accent); border:1px solid var(--border-strong,#c7ccd4); }
          .gd .pfoot{ font-size:.62rem; color:var(--faint); margin:.5rem 0 0; line-height:1.45; }
          .gd .pfoot b{ color:var(--ink); }

          /* side-by-side: a card + notes */
          .gd .cw{ display:grid; grid-template-columns:13.5rem 1fr; gap:1rem; align-items:start; font-size:.74rem; }
          .gd .cw .pcd{ font-size:.74rem; box-shadow:var(--gd-shadow); }
          .gd .notes{ display:flex; flex-direction:column; gap:.4rem; align-items:flex-start; }
          .gd .seq{ display:flex; flex-wrap:wrap; gap:.3rem .4rem; align-items:center; }
          .gd .seq i{ font-style:normal; color:var(--faint); font-size:.7rem; }
          .gd .seq .pbt{ display:inline-block; padding:.35rem .6rem; font-size:.66rem; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .scr{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--panel); font-size:.66rem; color:var(--soft); }
          .gd .scr b.h{ display:block; color:var(--ink); font-size:.76rem; margin-bottom:.2rem; }
          .gd .tk{ display:inline-flex; gap:.3rem; align-items:center; font-size:.68rem; color:var(--ink); }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }

          /* the console menu, drawn in the stage */
          .gd .cmnu{ background:#1f2937; border-radius:10px; padding:.6rem .55rem; color:#cbd5e1; width:11rem; }
          .gd .cmnu .lg{ font-weight:800; color:#fff; font-size:.88rem; } .gd .cmnu .lg b{ color:#5b9bff; }
          .gd .cmnu .tag{ display:block; font-size:.52rem; letter-spacing:.14em; color:#8aa0b2; margin:.05rem 0 .4rem; }
          .gd .cmnu .nh{ font-size:.52rem; letter-spacing:.12em; text-transform:uppercase; color:#8aa0b2; font-weight:700; margin:.4rem 0 .05rem .25rem; }
          .gd .cmnu .it{ display:block; font-size:.68rem; padding:.12rem .35rem; border-radius:6px; color:#e2e8f0; }
          .gd .mwrap{ display:grid; grid-template-columns:11rem 1fr; gap:.8rem; align-items:start; }

          @media (max-width:640px){
            .gd .sc{ min-height:580px; }
            .gd .cw, .gd .mwrap, .gd .two{ grid-template-columns:1fr; }
            .gd .cmnu{ width:auto; }
            .gd .ib.grow{ min-width:0; flex-basis:100%; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / factory / pipeline</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>FACTORY CONSOLE</small>
                <div class="navh">Work</div>
                <a>Calendar</a><a>Dashboard</a><a>Orders</a><a class="on">Pipeline</a><a>Quotes</a><a>Remakes</a>
                <div class="navh">Production</div>
                <a>Dispatch</a><a>Floor</a><a>Incoming orders</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head . $find() . $sum() . $fullBoard . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is for -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="mwrap">
                    <div class="cmnu a-rise" style="' . $d(1, 'Open it from') . '"><div class="lg">Your<b>Blinds</b></div><small class="tag">FACTORY CONSOLE</small>
                      <div class="nh">Work</div><span class="it">Calendar</span><span class="it">Dashboard</span><span class="it">Orders</span>
                      <span class="it a-sel" style="' . $d(1, 'Open it from', 1) . '">Pipeline</span><span class="it">Quotes</span><span class="it">Remakes</span></div>
                    <div>
                      <div class="chip a-pop" style="' . $d(1, 'where\'s my order') . ';margin-bottom:.5rem">&ldquo;Where&rsquo;s my order?&rdquo;</div>
                      <div class="a-fade" style="' . $d(1, 'It shows every') . '">' . $head . '</div>
                      <div class="pbd sm" style="grid-auto-columns:6.2rem">'
                        . $col('new', ['A'], ['col' => ['a-fly', $d(1, 'New is on the left')]])
                        . $col('in_production', ['C'], ['col' => ['a-fly', $d(1, 'in columns', .4)]])
                        . $col('ready', ['E'], ['col' => ['a-fly', $d(1, 'from left to right')]])
                        . $col('paid', ['I'], ['col' => ['a-fly', $d(1, 'Paid is on the right')]]) . '
                      </div>
                      <div class="chips">
                        <span class="chip a-pop" style="' . $d(1, 'Click it to open') . '">click to open: what it&rsquo;s waiting on</span>
                        <span class="chip ok a-pop" style="' . $d(1, 'the one next step') . '">the one next step</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 2 — the making columns -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">The making columns</div>
                  <div class="pbd">'
                    . $col('new', ['A'], ['col' => ['a-drop', $d(2, 'New means')], 'head' => ['a-ring', $d(2, 'New means')]])
                    . $col('confirmed', ['B'], ['col' => ['a-drop', $d(2, 'Received means')], 'head' => ['a-ring', $d(2, 'Received means')]])
                    . $col('in_production', ['C'], ['col' => ['a-drop', $d(2, 'In production means')], 'head' => ['a-ring', $d(2, 'In production means')]])
                    . $col('awaiting_supplier', ['D'], ['col' => ['a-drop', $d(2, 'Awaiting supplier means')], 'head' => ['a-ring', $d(2, 'Awaiting supplier means')]])
                    . $col('ready', ['E'], ['col' => ['a-drop', $d(2, 'And Ready to go')], 'head' => ['a-ring', $d(2, 'And Ready to go')]]) . '
                  </div>
                </div>

                <!-- 3 — the money columns -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">The money columns</div>
                  <div class="pbd">'
                    . $col('ready', ['E'])
                    . $col('to_invoice', ['F'], ['col' => ['a-drop', $d(3, 'Dispatched, to invoice')], 'head' => ['a-ring', $d(3, 'Dispatched, to invoice')]])
                    . $col('to_pay', ['G'], ['col' => ['a-drop', $d(3, 'Awaiting payment means')], 'head' => ['a-ring', $d(3, 'Awaiting payment means')]])
                    . $col('paid', ['I'], ['col' => ['a-drop', $d(3, 'Paid shows')], 'head' => ['a-ring', $d(3, 'Paid shows')]]) . '
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(3, 'though it stays') . '">After 7 days: still on <b>Orders</b></span>
                    <span class="chip bad a-pop" style="' . $d(3, 'These columns only') . '">Money columns + values: main factory&rsquo;s accounts staff</span>
                  </div>
                  <div class="scr a-rise" style="' . $d(3, 'Everyone else') . ';margin-top:.5rem;max-width:22rem"><b class="h">Everyone else sees</b>
                    <div class="pbd" style="grid-auto-columns:9rem">' . $col('ready', [$card('E', [], true)]) . $col('dispatched', [$card('F', [], true)]) . '</div></div>
                </div>

                <!-- 4 — counts and order -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">Counts and order</div>
                  ' . $sum([
                        ['a-pop', $d(4, 'how many orders are open')],
                        ['a-pop', $d(4, 'waiting on suppliers')],
                        ['a-pop', $d(4, 'ready to go')],
                        ['a-pop', $d(4, 'Overdue invoices')],
                    ]) . '
                  <div class="pbd">' . $col('to_invoice', ['F'])
                    . str_replace(['<span class="pn">', '<p class="phn">'], ['<span class="pn a-ring" style="' . $d(4, 'a count at the top') . '">', '<p class="phn a-ring" style="' . $d(4, 'a short line') . '">'],
                        $col('to_pay', [
                            '<div class="a-fly" style="' . $d(4, 'The oldest card') . '"><span class="chip ok" style="font-size:.85em;margin-bottom:.3em">oldest first</span>' . $card('H', [], false, false) . '</div>',
                            '<div class="a-fly" style="' . $d(4, 'The oldest card', .8) . '">' . $card('G', [], false, false) . '</div>',
                        ])) . '
                    ' . $col('paid', ['I']) . '
                  </div>
                </div>

                <!-- 5 — one line per order -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">One line per order</div>
                  ' . $sum([[], [], [], [], ['a-ring', $d(5, 'Open all and Close all')], ['a-ring', $d(5, 'Open all and Close all', .7)]]) . '
                  <div class="pbd" style="font-size:.66rem">'
                    . $col('in_production', ['<div style="display:grid"><div class="a-out" style="grid-area:1/1;' . $d(5, 'Click the number', 1.4) . '">' . $card('C', ['dot' => ['a-ring', $d(5, 'a bought-in item not ordered')]], false, false) . '</div>'
                        . '<div class="a-fade" style="grid-area:1/1;' . $d(5, 'Click the number', 1.4) . '">' . $card('C') . '</div></div>'])
                    . $col('awaiting_supplier', [$card('D', ['dot' => ['a-ring', $d(5, 'or not in yet')]], false, false)])
                    . $col('ready', ['E'])
                    . $col('to_invoice', [$card('F', ['dot' => ['a-ring', $d(5, 'money that needs invoicing')]], false, false)]) . '
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(5, 'each order is one line') . '">&#9656; one line: the order number</span>
                    <span class="chip bad a-pop" style="' . $d(5, 'A red dot') . '"><span class="pdot" style="font-size:.9rem;margin:0"></span> something needs chasing</span>
                    <span class="chip a-pop" style="' . $d(5, 'click again to close') . '">click again to close</span>
                  </div>
                  <div class="a-move" style="--fx:70%;--fy:95%;--tx:8%;--ty:6.9rem;' . $d(5, 'Click the number') . ';--md:1.3s">' . $ptr . '</div>
                </div>

                <!-- 6 — what a card shows -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">What an open card shows</div>
                  <div class="cw" style="margin-top:.5rem">
                    ' . $card('E', [
                        'card' => ['a-rise', $d(6, 'Now look')],
                        'link' => ['a-ring', $d(6, 'At the top is Open order')],
                        'val'  => ['a-ring', $d(6, 'Next to it')],
                        'acc'  => ['a-ring', $d(6, 'Under that is')],
                        'rm'   => ['a-ring', $d(6, 'A purple REMAKE')],
                        'due'  => ['a-ring', $d(6, 'Then comes the due')],
                    ]) . '
                    <div class="notes">
                      <span class="chip a-pop" style="' . $d(6, 'At the top is Open order') . '"><b>Open order &rarr;</b> &mdash; opens the order</span>
                      <span class="chip a-pop" style="' . $d(6, 'Next to it') . '">Value</span>
                      <span class="chip a-pop" style="' . $d(6, 'Under that is') . '">Account &middot; their reference</span>
                      <span class="chip a-pop" style="' . $d(6, 'A purple REMAKE') . ';border-color:#7c3aed">REMAKE &mdash; a remake of an earlier order</span>
                      <span class="chip a-pop" style="' . $d(6, 'Then comes the due') . '">Due date</span>
                    </div>
                  </div>
                  <div class="a-move" style="--fx:70%;--fy:95%;--tx:6%;--ty:3.6rem;' . $d(6, 'Click it to open') . ';--md:1.2s">' . $ptr . '</div>
                </div>

                <!-- 7 — each line -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">Each line has its own status</div>
                  <div class="cw" style="margin-top:.5rem">
                    ' . $card('C', [
                        'line0' => ['a-ring', $d(7, 'A blind made here')],
                        'line1' => ['a-ring', $d(7, 'A bought-in item')],
                    ]) . '
                    <div class="notes">
                      <div class="card a-rise" style="' . $d(7, 'A blind made here') . '"><h4>Made here</h4>
                        <ul class="plns" style="border:0;padding:0;font-size:.72rem">
                          <li class="a-pop" style="' . $d(7, 'Not started') . '"><span class="muted">Not started</span></li>
                          <li class="a-pop" style="' . $d(7, 'Being made') . '"><span class="amber">Being made &middot; 3 of 5</span></li>
                          <li class="a-pop" style="' . $d(7, 'a tick and Made') . '"><span class="ok">&#10003; Made</span></li></ul></div>
                      <div class="card a-rise" style="' . $d(7, 'A bought-in item') . '"><h4>Bought in</h4>
                        <ul class="plns" style="border:0;padding:0;font-size:.72rem">
                          <li class="a-pop" style="' . $d(7, 'not ordered yet') . '"><span class="bad">Hunter Douglas &middot; not ordered yet</span></li>
                          <li class="a-pop" style="' . $d(7, 'Waiting on the supplier') . '"><span class="bad">Waiting on Hunter Douglas &middot; ordered 12 Oct</span></li>
                          <li class="a-pop" style="' . $d(7, 'Waiting on the supplier', 2) . '"><span class="ok">&#10003; Received from Hunter Douglas</span></li></ul></div>
                      <span class="chip ok a-pop" style="' . $d(7, 'So on a mixed') . '">You can see which part is holding it up</span>
                    </div>
                  </div>
                </div>

                <!-- 8 — money on a card -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">The money on a card</div>
                  <div class="cw" style="margin-top:.5rem">
                    ' . $card('G', [
                        'money' => ['a-ring', $d(8, 'its card shows')],
                        'inv'   => ['a-ring', $d(8, 'Under the pill')],
                    ]) . '
                    <div class="notes">
                      <div class="seq">' . $mpill('awaiting', 'a-pop', $d(8, 'Invoiced, awaiting')) . $mpill('part_paid', 'a-pop', $d(8, 'Part paid')) . $mpill('overdue', 'a-pop', $d(8, 'Overdue, or')) . $mpill('paid', 'a-pop', $d(8, 'or Paid')) . '</div>
                      <span class="chip a-pop" style="' . $d(8, 'Under the pill') . '">invoice &middot; what&rsquo;s owed &middot; when it&rsquo;s due</span>
                      <div class="a-rise" style="' . $d(8, 'Once it is paid') . ';width:13.5rem">' . $card('I') . '</div>
                    </div>
                  </div>
                </div>

                <!-- 9 — remakes on the board -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">Remakes on the board</div>
                  ' . $sum([[], [], [], [], [], [], ['a-ring', $d(9, 'how many remakes are in progress')], ['a-ring', $d(9, 'a purple link')]]) . '
                  <div class="pbd" style="font-size:.66rem">'
                    . $col('in_production', ['C']) . $col('awaiting_supplier', ['D'])
                    . $col('ready', ['<div style="position:relative">' . $card('E', ['r' => ['a-ring', $d(9, 'a purple R')]], false, false)
                        . '<span class="ptip a-pop" style="' . $d(9, 'Hover over the R', 1) . '">Remake of ABC-2026-0038</span></div>'])
                    . $col('to_invoice', ['F']) . '
                  </div>
                  <div class="chips" style="margin-top:2.2rem">
                    <span class="chip a-pop" style="' . $d(9, 'a purple R') . ';border-color:#7c3aed"><span class="prm" style="font-size:.7rem">R</span> a remake</span>
                    <span class="chip a-pop" style="' . $d(9, 'a purple link') . ';border-color:#7c3aed">waiting for approval &rarr; <b>Remakes</b> page</span>
                    <span class="chip a-pop" style="' . $d(9, 'Those are not orders') . '">not orders yet &mdash; no card</span>
                  </div>
                  <div class="a-move" style="--fx:70%;--fy:95%;--tx:70%;--ty:8.6rem;' . $d(9, 'Hover over the R') . ';--md:1.2s">' . $ptr . '</div>
                </div>

                <!-- 10 — taking it in -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="sct a-fade" style="--d:.2s">One button: taking it in</div>
                  <div class="pbd big" style="margin-top:.4rem">'
                    . $col('new', [$card('A', ['btn' => ['a-press', $d(10, 'A new order shows', 1.5)]])], ['col' => ['a-rise', $d(10, 'A new order shows')]])
                    . $col('in_production', [$card('C2', ['btn' => ['a-ring', $d(10, 'the button says See')]])], ['col' => ['a-rise', $d(10, 'While the floor')]]) . '
                  </div>
                  <div class="scr a-rise" style="margin-top:.5rem;' . $d(10, 'First, a summary') . '"><b class="h">Process ABC-2026-0041</b>
                    <div class="a-pop" style="' . $d(10, 'which blinds go') . '">Put 6 blinds on the floor: Roller Blind &times;4, Vertical Blind &times;2</div>
                    <div class="a-pop" style="' . $d(10, 'which suppliers are emailed') . '">Email Hunter Douglas its purchase order (1 item) &rarr; orders@example.com</div>
                    <div style="margin-top:.45rem"><span class="pbt a-press" style="display:inline-block;padding:.3rem .7rem;' . $d(10, 'Click Process order') . '">Process order</span> <span style="margin-left:.4rem">Cancel</span></div></div>
                  <div class="chips"><span class="chip ok a-pop" style="' . $d(10, 'The same rules') . '">Same rules as <b>Incoming orders</b></span></div>
                </div>

                <!-- 11 — bought-in items -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="sct a-fade" style="--d:.2s">Bought-in items</div>
                  <div class="two" style="margin-top:.4rem">
                    <div style="font-size:.74rem">' . $card('C', ['btn' => ['a-press', $d(11, 'click Retry')], 'line1' => ['a-ring', $d(11, 'If a purchase order')]]) . '</div>
                    <div class="scr a-rise" style="' . $d(11, 'If a purchase order') . '"><b class="h">Couldn&rsquo;t order</b>
                      <div style="margin:.2rem 0 .35rem">A supplier&rsquo;s purchase order didn&rsquo;t go, so the order sits in <b>Couldn&rsquo;t order</b> on Incoming orders, in red, until it does. Nothing fails quietly.</div>
                      <span class="tk a-pop" style="' . $d(11, 'with the reason') . '">No order email is set for Hunter Douglas (Settings &rsaquo; Suppliers).</span>
                      <div style="margin-top:.45rem"><span class="pbt a-press" style="display:inline-block;padding:.3rem .7rem;' . $d(11, 'click Retry') . '">Retry Hunter Douglas</span></div>
                      <div class="a-pop" style="margin-top:.45rem;' . $d(11, 'An item added') . '">Added a bought-in blind later? <b>New item to order from &hellip;</b> sends just the new item.</div></div>
                  </div>
                  <div class="two" style="margin-top:.6rem">
                    <div class="a-rise" style="font-size:.74rem;' . $d(11, 'When the goods') . '">' . $card('D', ['btn' => ['a-press', $d(11, 'click Received from', 1)]]) . '</div>
                    <div class="notes">
                      <span class="chip a-pop" style="' . $d(11, 'When the goods') . '">Goods arrived? <b>Received from &hellip;</b></span>
                      <span class="chip ok a-pop" style="' . $d(11, 'Once everything') . '">&rarr; <b>Ready to go</b></span>
                    </div>
                  </div>
                </div>

                <!-- 12 — sending it out -->
                <div class="sc" data-scene="12" data-len="' . $len(12) . '">
                  <div class="sct a-fade" style="--d:.2s">Sending it out</div>
                  <div class="two" style="margin-top:.4rem">
                    <div style="font-size:.74rem">' . $card('E', ['btn' => ['a-press', $d(12, 'It takes you')]]) . '</div>
                    <div class="scr a-rise" style="' . $d(12, 'It takes you', .8) . '"><b class="h">Dispatch tray</b>
                      <div style="font-weight:700;color:var(--ink);margin:.2rem 0">Ready to go out</div>
                      <span class="tk"><span class="tick a-sel" style="' . $d(12, 'ticked and printed') . '">&#10003;</span> ABC Blinds &middot; ABC-2026-0038-R1</span>
                      <div class="chip a-pop" style="' . $d(12, 'just as before') . ';margin-top:.4rem;font-size:.62rem">the van run, ticked and printed</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="' . $d(12, 'Once it has gone') . '">&rarr; <b>Dispatched &middot; to invoice</b></span>
                    <span class="chip a-pop" style="' . $d(12, 'If you do not see') . '">No money columns? The button says <span class="pbt blue" style="display:inline-block;padding:.15rem .5rem;font-size:.66rem">Dispatch</span></span>
                  </div>
                </div>

                <!-- 13 — getting paid -->
                <div class="sc" data-scene="13" data-len="' . $len(13) . '">
                  <div class="sct a-fade" style="--d:.2s">Getting paid</div>
                  <div class="pbd big" style="margin-top:.4rem">'
                    . $col('to_invoice', [$card('F', ['btn' => ['a-press', $d(13, 'Raise invoice, in green', 1)]])], ['col' => ['a-rise', $d(13, 'Raise invoice, in green')]])
                    . $col('to_pay', [$card('G', ['btn' => ['a-press', $d(13, 'Record payment', 1)]]), $card('H', ['btn' => ['a-press', $d(13, 'And Chase payment', 1)]])], ['col' => ['a-rise', $d(13, 'Record payment')]]) . '
                  </div>
                  <div class="chips"><span class="chip a-pop" style="' . $d(13, 'That opens the account') . '">Chase payment opens the <b>account</b></span></div>
                </div>

                <!-- 14 — move by buttons -->
                <div class="sc" data-scene="14" data-len="' . $len(14) . '">
                  <div class="sct a-fade" style="--d:.2s">Cards move by their buttons</div>
                  <div class="pbd" style="margin-top:.4rem">' . $col('awaiting_supplier', ['D']) . $col('ready', []) . '</div>
                  <div class="chips">
                    <span class="chip bad a-pop" style="' . $d(14, 'You cannot drag') . '">&#10007; no dragging</span>
                    <span class="chip ok a-pop" style="' . $d(14, 'Cards move with') . '">&#10003; buttons, and the work itself</span>
                  </div>
                  <div class="scr a-rise" style="' . $d(14, 'For example') . ';margin-top:.5rem;max-width:30rem;border-color:#fecaca;background:#fef2f2;color:#991b1b">
                    Can&rsquo;t dispatch yet &mdash; the order isn&rsquo;t ready. Every blind must be made and every bought-in item received first.</div>
                  <p class="pfoot a-fade" style="' . $d(14, 'That way nothing') . '">Cards move only with their buttons, so nothing skips a check (no dispatch before every bought-in item is in).</p>
                </div>

                <!-- 15 — column colours -->
                <div class="sc" data-scene="15" data-len="' . $len(15) . '">
                  <div class="sct a-fade" style="--d:.2s">Column colours</div>
                  ' . $sum([[], [], [], [], [], [], [], [], ['a-ring', $d(15, 'Click Colours')]]) . '
                  <div class="two">
                    <div class="scr a-rise" style="' . $d(15, 'It opens Factory Settings') . '"><b class="h">Pipeline colours</b>
                      <div class="phn" style="font-size:.6rem;margin:0 0 .4rem">The colour of each column on the <b>Pipeline</b> board: its top bar, its count and a faint tint behind the column. Pick any colour you like for each one.</div>'
                      . (static function () use ($COLS, $d): string {
                          $h = '';
                          foreach (['new', 'confirmed', 'in_production', 'awaiting_supplier', 'ready', 'to_invoice', 'to_pay', 'paid', 'dispatched'] as $k) {
                              [$t, , $c] = $COLS[$k]; if ($k === 'dispatched') $t = 'Dispatched (white-label factories)';
                              $row = static fn (string $hex, string $cls = '', string $st = ''): string =>
                                  '<div class="focr ' . $cls . '" style="grid-area:1/1;border-top:3px solid ' . $hex . ';background:rgba(' . implode(',', array_map('hexdec', str_split(ltrim($hex, '#'), 2))) . ',.10);' . $st . '">'
                                  . '<span>' . $t . '</span><span class="sw" style="background:' . $hex . '"></span><span class="hx">' . $hex . '</span></div>';
                              $h .= $k === 'ready'
                                  ? '<div style="display:grid">' . $row($c, 'a-out', $d(15, 'one colour picker', 1.5)) . $row('#0e7490', 'a-fade', $d(15, 'one colour picker', 1.5)) . '</div>'
                                  : $row($c);
                          }
                          return $h;
                      })()
                      . '<div style="display:flex;gap:.35rem;margin-top:.45rem"><span class="pbt a-press" style="display:inline-block;padding:.3rem .6rem;' . $d(15, 'Click Save') . '">Save pipeline colours</span>'
                      . '<span class="btns a-pop" style="' . $d(15, 'or Reset to defaults') . '">Reset to defaults</span></div></div>
                    <div>
                      <div class="pbd" style="font-size:.66rem;display:grid;grid-template-columns:1fr"><div class="a-out" style="grid-area:1/1;' . $d(15, 'Click Save', 1) . '">' . $col('ready', ['E']) . '</div><div class="a-fade" style="grid-area:1/1;' . $d(15, 'Click Save', 1) . '">' . str_replace('--pc:#245ea3;background:rgba(36,94,163,.07)', '--pc:#0e7490;background:rgba(14,116,144,.07)', $col('ready', ['E'])) . '</div></div>
                      <div class="chips">
                        <span class="chip a-pop" style="' . $d(15, 'top bar') . '">top bar</span>
                        <span class="chip a-pop" style="' . $d(15, 'its count') . '">count</span>
                        <span class="chip a-pop" style="' . $d(15, 'a faint tint') . '">faint tint</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 16 — where\'s my order -->
                <div class="sc" data-scene="16" data-len="' . $len(16) . '">
                  <div class="sct a-fade" style="--d:.2s">Where&rsquo;s my order?</div>
                  <div class="pfind">
                    <span class="ib grow a-ring" style="' . $d(16, 'use the search box') . '"><span class="swap"><span class="gph a-out" style="' . $d(16, 'Type the account') . '">Where&rsquo;s my order? Account, order no., their ref, name&hellip;</span>
                      <span class="a-fade" style="' . $d(16, 'Type the account') . '"><span class="a-type" style="' . $d(16, 'Type the account', .2) . ';--ts:7;--tt:.8s">PO 4420</span></span></span></span>
                    <span class="ib sel a-ring" style="' . $d(16, 'Or pick an account') . '"><span class="swap"><span class="a-out" style="' . $d(16, 'Or pick an account', .8) . '">All accounts</span><span class="a-fade" style="' . $d(16, 'Or pick an account', .8) . '">ABC Blinds</span></span></span>
                    <span class="btns a-press" style="' . $d(16, 'then tap Find') . '">Find</span>
                    <span class="btns a-pop" style="' . $d(16, 'Clear takes you') . '">Clear</span>
                  </div>
                  <div class="pbd">'
                    . $col('new', [], [], '0') . $col('in_production', [], [], '0')
                    . $col('awaiting_supplier', ['<div class="a-pop" style="' . $d(16, 'then tap Find', .6) . '">' . $card('D') . '</div>']) . $col('ready', []) . '
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="' . $d(16, 'or an invoice number') . '">account &middot; order number &middot; their ref &middot; name &middot; invoice number</span>
                    <span class="chip ok a-pop" style="' . $d(16, 'On a phone') . '">On a phone: swipe sideways &harr;</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>What it is.</b> <b>Work &rarr; Pipeline</b> shows every open order placed with us as a one-line <b>card</b>, in columns from left
             to right: <em>Every open order, left to right from new to paid. Each card says what it&rsquo;s waiting on and gives the next
             step.</em> It reads the same orders as the Orders list (quotes not placed yet are left off).</p>

          <p><b>The columns</b> (each with a count and a short hint; the oldest card is at the top):</p>
          <ul class="steps">
            <li><b>New</b> &mdash; <em>Placed, not taken in yet</em>.</li>
            <li><b>Received</b> &mdash; <em>In, not started on the floor</em>.</li>
            <li><b>In production</b> &mdash; <em>Being made on the floor</em>.</li>
            <li><b>Awaiting supplier</b> &mdash; <em>Our part is made, waiting on a bought-in item</em>.</li>
            <li><b>Ready to go</b> &mdash; <em>Everything made and in</em>.</li>
            <li><b>Dispatched &middot; to invoice</b> &mdash; <em>Gone out, no invoice yet</em>.</li>
            <li><b>Awaiting payment</b> &mdash; <em>Invoiced, not paid yet</em> (part paid and overdue too).</li>
            <li><b>Paid</b> &mdash; <em>Last 7 days, then it leaves the board</em>. Paid orders stay on <b>Orders</b> (the Done chip).</li>
          </ul>
          <p>The three money columns, the values on the cards and the money pills show <b>only for the main factory&rsquo;s accounts
             users</b>. Everyone else sees one <b>Dispatched</b> column instead &mdash; <em>Gone out in the last 7 days</em> &mdash; and
             dispatched orders then stay on <b>Orders</b>.</p>

          <p><b>The totals</b> above the board: <b>n open orders</b>, <b>n remakes in progress</b>, <b>n remakes waiting for approval &rarr;</b>, <b>n waiting on suppliers</b>, <b>n ready to go</b>, and
             (accounts users) <b>n overdue</b> in red.</p>

          <p><b>One line per order.</b> On the board each order is a single line: a &#9656; and its <b>order number</b>. A <b>red dot</b>
             on the line means something needs chasing &mdash; a bought-in item not ordered or not in yet, or money that is
             <b>Needs invoicing</b> or <b>Overdue</b>. Click the number to open the card underneath; click again to close it.
             <b>Open all</b> and <b>Close all</b>, at the right of the totals, open or close every card at once. The eight columns share
             the page width; on a screen under about 1100px wide they scroll sideways.</p>

          <p><b>Column colours.</b> <b>Colours</b>, next to Open all and Close all, opens <b>Factory Settings</b> at the <b>Pipeline colours</b> card: one colour picker per column (including <em>Dispatched (white-label factories)</em>). Each colour sets the column&rsquo;s top bar, its count and a faint tint behind it. <b>Save pipeline colours</b> keeps them; <b>Reset to defaults</b> puts the original colours back.</p>

          <p><b>Remakes.</b> A remake order&rsquo;s line has a purple <b>R</b> after the order number (hover it: <em>Remake of
             &hellip;</em>). The totals add <b>n remakes in progress</b> with an R, and, when any are waiting, a purple link
             <b>n remakes waiting for approval &rarr;</b> to the <b>Remakes</b> page. Remakes waiting for approval aren&rsquo;t orders
             yet, so they have no card.</p>

          <p><b>An open card</b> shows:</p>
          <ul class="steps">
            <li><b>Open order &rarr;</b> (opens the order itself) and its <b>value</b>.</li>
            <li>The <b>account</b> &middot; <b>their reference</b>, and a purple <b>REMAKE</b> badge on a remake.</li>
            <li><b>Due</b> date (until it is dispatched).</li>
            <li>Every one of our lines, <em>name &times; qty</em>, with its own status. Made here: <b>Not started</b>,
                <b>Being made &middot; 3 of 5</b>, <b>&#10003; Made</b>. Bought in: <b>Hunter Douglas &middot; not ordered yet</b>,
                <b>Waiting on Hunter Douglas &middot; ordered 9 Oct</b>, <b>&#10003; Received from Hunter Douglas</b>. Once gone out:
                <b>&#10003; Dispatched</b>.</li>
            <li>Once invoiced (accounts users): the money pill &mdash; <b>Invoiced &middot; awaiting payment</b>, <b>Part paid</b>,
                <b>Overdue</b>, <b>Paid</b> &mdash; and each invoice, e.g. <em>INV-BEV-2026-0007 &middot; &pound;343.68 owed, due 1 Nov</em>
                or <em>&middot; paid 8 Oct</em>. On Dispatched &middot; to invoice it shows <b>Needs invoicing</b>.</li>
          </ul>

          <p><b>The one button</b> is the next step, worked out by the same rules as <b>Incoming orders</b>:</p>
          <ul class="steps">
            <li><b>Process order</b> &mdash; on a new order. It takes the order in, sends its made-here blinds to the floor and emails each
                bought-in supplier its purchase order (one per supplier, with only their lines and a spec PDF), in one go. A summary
                comes first &mdash; e.g. <em>Put 6 blinds on the floor &middot; Email Hunter Douglas its purchase order (1 item)</em> &mdash;
                with <b>Process order</b> and <b>Cancel</b>. A factory can switch the summary off: Factory &rarr; Settings &rarr;
                <b>Process orders without the summary</b>.</li>
            <li><b>Retry &lt;supplier&gt;</b> (red) &mdash; a purchase order that couldn&rsquo;t go (no order email, or the email
                failed). The line says <b>Couldn&rsquo;t order from &lt;supplier&gt;</b> and why; fix it, then retry.</li>
            <li><b>Order new item from &lt;supplier&gt;</b> (red) &mdash; a bought-in item added after the order was processed. Only
                the new item is sent, marked as additional to the first purchase order.</li>
            <li><b>Start production</b> &mdash; only on an order taken in before <b>Process order</b>: sends its blinds to the floor.</li>
            <li><b>Received from &lt;supplier&gt;</b> (purple) &mdash; the bought-in goods have arrived.</li>
            <li><b>Add to today&rsquo;s run &rarr;</b> (blue) &mdash; opens the <b>Dispatch tray</b>, where the van run is ticked and
                printed. Without the money columns the button is <b>Dispatch</b>.</li>
            <li><b>See progress</b> &mdash; the floor is making it: opens the order&rsquo;s blinds with the stage each one is at.</li>
            <li><b>Raise invoice</b> (green), <b>Record payment</b> (awaiting or part paid), <b>Chase payment</b> (red, overdue &mdash;
                opens the account).</li>
          </ul>

          <p><b>Cards move only by their buttons</b> &mdash; and by the work itself, like the floor finishing the blinds. There is no
             dragging, so nothing skips a check: dispatching an order that isn&rsquo;t ready says <em>Can&rsquo;t dispatch yet &mdash;
             the order isn&rsquo;t ready. Every blind must be made and every bought-in item received first.</em></p>

          <p><b>Where&rsquo;s my order?</b> Type in the search box (<em>Where&rsquo;s my order? Account, order no., their ref,
             name&hellip;</em> &mdash; an invoice number works too) and tap <b>Find</b>, or pick an account from <b>All accounts</b>
             (it filters straight away). The cards that match open up on their own. <b>Clear</b> shows the whole board again. On a
             phone the columns scroll sideways.</p>

          <div class="heads"><span class="hi">&#9432;</span><div>The <b>Dispatch tray</b> is still where the van run is ticked and printed,
             and orders made but <b>waiting on suppliers</b> are listed there under <b>Waiting on suppliers</b>.</div></div>',
        'script'  => $S,
];
