<?php
declare(strict_types=1);

/**
 * Guide: settings-suppliers — "Suppliers" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors Settings → Suppliers (admin/settings.php, POST _action=suppliers):
 * Delivery address (where suppliers ship to); the table Supplier / Order
 * email / Account no. (only once migrate_supplier_account_number has run) /
 * Remove; the "+ Add a supplier" row; In House sorted to the top; "Save
 * suppliers" → "Suppliers saved." / the rename-clash and bad-email messages.
 * What it feeds: quote-builder/order_suppliers.php ("Send order to
 * suppliers": one email per supplier, matched BY NAME, with a spec PDF from
 * pdf-generator/pdf.php carrying "Deliver to" and "Account no:"), and the
 * factory's Order bought-in screen.
 *
 * v2: one scene per script line; data-len is worked out from the line's own
 * length (characters ÷ 13.6), so editing a line keeps its scene in step.
 */

$vo = [
    1  => ['Who you buy from',
           'The Suppliers tab is your list of the firms you buy stock from. It is not your fabric list. Each name here can be a product\'s order supplier. When you place an order, each supplier is emailed their own lines, using the details on this tab. So two things matter most. The delivery address, and each supplier\'s order email.'],
    2  => ['The delivery address',
           'Start with the delivery address. This is where your suppliers send the goods. It goes on every supplier order, so put in the full address, exactly as a courier would need it. Leave it blank, and the send screen warns you in red. But it does not stop you, and the order goes out with no address on it.'],
    3  => ['One row per supplier',
           'Below it is the table, with one row per supplier. Supplier is the name. Order email is where their orders are sent. Account number is your trade account number with them, so they know whose order it is. And Remove is a tick box, for deleting a row.'],
    4  => ['The order email',
           'The order email is the important one. Your order is emailed to exactly this address, and nowhere else. So copy it from the supplier\'s own paperwork. With no email, that supplier cannot be sent an order, and the send screen tells you to fix it here.'],
    5  => ['The list fills itself',
           'You rarely need to add a supplier by hand. Whenever you save a product with an order supplier on it, that name appears here by itself. Usually, all you need to add is the email. To add one yourself, use the bottom row, the one that says, plus Add a supplier.'],
    6  => ['In House',
           'If you have a row called In House, it sits at the top of the list. It means you make the blind yourself. So leave its email blank, and nothing is sent. Put a real address on In House, and the app takes you at your word. It will email an order to that address, just like any other supplier.'],
    7  => ['Save, and the email check',
           'Click Save suppliers, and a green bar says, Suppliers saved. If an email address is not valid, nothing is saved at all, not even the delivery address. A red message names the supplier, so you know which one to fix. Correct it, and save again.'],
    8  => ['Removing a supplier',
           'To delete a stray supplier, tick Remove on its row, and save. Clearing the name box does not delete it. That row is simply skipped. And if a product still uses that name, the supplier comes back the next time that product is saved.'],
    9  => ['Careful renaming',
           'Be careful renaming a supplier. Your products hold the supplier\'s name as plain words, not a link. Rename it here, and the products still use the old name. The send screen can then no longer find an email for them. So change the name on your products first, then tidy this list to match.'],
    10 => ['What it is all for',
           'Here is what it all does. Once a quote is accepted, Send to suppliers splits the order by supplier. Each one gets an email with only their own lines, and a spec P D F, sent to their order email. Your delivery address and account number go with it. Every send is logged, so an order already sent is left unticked, and you do not order twice.'],
];
$len = static fn (int $n): string => (string) round(mb_strlen($vo[$n][1]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$tabs = static function (string $on, string $ring = ''): string {
    $h = '<div class="tabs">';
    foreach (['Company', 'Quoting', 'Legal', 'Status colours', 'Suppliers', 'Accounting', 'Back up data'] as $t) {
        $cls = 'tab' . ($t === $on ? ' on' : '');
        $h  .= $t === $on && $ring !== ''
            ? '<span class="' . $cls . ' a-ring" style="--d:' . $ring . '">' . $t . '</span>'
            : '<span class="' . $cls . '">' . $t . '</span>';
    }
    return $h . '</div>';
};

$addr = 'Bright Blinds Ltd<br>Unit 4, Mill Lane<br>Leeds LS12 3AB';

/**
 * The suppliers table. $rows: [name, email, account, extraClassForRow, removeTicked, cellOverrides[]].
 * Cell overrides (keys name/email/acct/rm) replace a cell's inner HTML.
 */
$table = static function (array $rows, bool $addRow = true, array $addCells = []): string {
    $h = '<div class="st"><div class="sth"><span>Supplier</span><span>Order email</span><span>Account no.</span><span class="c">Remove</span></div>';
    foreach ($rows as $r) {
        [$name, $email, $acct] = $r;
        $cls = $r[3] ?? ''; $rm = $r[4] ?? false; $ov = $r[5] ?? [];
        $h .= '<div class="str ' . $cls . '">'
            . '<span class="in">' . ($ov['name'] ?? $name) . '</span>'
            . '<span class="in">' . ($ov['email'] ?? ($email !== '' ? $email : '<span class="ph">orders@supplier.com</span>')) . '</span>'
            . '<span class="in">' . ($ov['acct'] ?? ($acct !== '' ? $acct : '<span class="ph">Your account no.</span>')) . '</span>'
            . '<span class="c">' . ($ov['rm'] ?? '<span class="cb">' . ($rm ? '<i>&#10003;</i>' : '') . '</span>') . '</span></div>';
    }
    if ($addRow) {
        $h .= '<div class="str">'
            . '<span class="in">' . ($addCells['name'] ?? '<span class="ph">+ Add a supplier</span>') . '</span>'
            . '<span class="in">' . ($addCells['email'] ?? '<span class="ph">orders@supplier.com</span>') . '</span>'
            . '<span class="in">' . ($addCells['acct'] ?? '<span class="ph">Your account no.</span>') . '</span><span></span></div>';
    }
    return $h . '</div>';
};
$IH = ['In House', '', ''];
$BW = ['Brightwell Fabrics', 'orders@brightwell-fabrics.co.uk', 'BB2201'];
$CS = ['Coastline Supply', 'trade@coastline-supply.co.uk', 'C-4471'];

$script = [];
foreach ($vo as $n => [$cap, $line]) $script[] = [(string) $n, $cap, $line, $n];

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Suppliers',
        'eyebrow' => 'Settings · Suppliers',
        'v'       => 2,
        'blurb'   => 'Your address book of the firms you buy stock from — the delivery address and each supplier\'s order email, and why the name matters more than it looks.',
        'lede'    => 'This tab is your <b>address book of the firms you buy stock from</b>, plus the one address they all ship to.
                      Two things have to be right: the <b>delivery address</b>, because it goes on every supplier order, and each
                      supplier&rsquo;s <b>order email</b>, because that is exactly where the order is sent. Get those right and
                      <b>Send to suppliers</b> does the rest. To get there: <b>Settings</b> &rarr; the <b>Suppliers</b> tab.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin:0 0 .7rem; }
          .gd .tab{ font-size:.66rem; font-weight:600; color:var(--soft); padding:.28rem .45rem; border-radius:6px 6px 0 0; }
          .gd .tab.on{ color:var(--accent); box-shadow:inset 0 -2px 0 var(--accent); }
          .gd .sh{ font-size:.86rem; font-weight:800; color:var(--ink); margin:0 0 .3rem; }
          .gd .hn{ display:block; font-size:.64rem; color:var(--faint); line-height:1.4; margin:0 0 .5rem; max-width:34rem; }
          .gd .lb{ display:block; font-size:.68rem; font-weight:700; color:var(--soft); margin:0 0 .2rem; }
          .gd .lb .q{ font-weight:400; color:var(--faint); }
          .gd .ph{ color:var(--faint); }
          .gd .ta3{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:.35rem .5rem; font-size:.7rem;
                    color:var(--ink); line-height:1.45; max-width:24rem; min-height:3.4rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .ebnr{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.45rem .65rem; font-size:.72rem; color:var(--ink); margin:0 0 .6rem; max-width:34rem; }
          .gd .row{ display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; } .gd .mt{ margin-top:.7rem; }
          .gd .stk{ display:inline-grid; } .gd .stk > *{ grid-area:1/1; }
          .gd .arrow{ color:var(--faint); font-weight:800; }

          /* the suppliers table */
          .gd .st{ max-width:36rem; font-size:.68rem; }
          .gd .sth, .gd .str{ display:grid; grid-template-columns:1.1fr 1.6fr .9fr 3rem; gap:.3rem; align-items:center; }
          .gd .sth{ font-size:.6rem; font-weight:700; color:var(--soft); padding:0 0 .25rem; border-bottom:1px solid var(--line); }
          .gd .str{ padding:.22rem 0; border-bottom:1px solid var(--line-2); position:relative; }
          .gd .st .c{ text-align:center; display:flex; justify-content:center; }
          .gd .st .in{ display:flex; align-items:center; min-height:24px; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; background:var(--surface);
                       padding:0 .35rem; color:var(--ink); white-space:nowrap; overflow:hidden; font-size:.66rem; }
          .gd .st .in.bad{ border-color:var(--err); box-shadow:0 0 0 2px var(--err-wash); }
          .gd .cb{ position:relative; display:inline-block; width:15px; height:15px; border-radius:4px; border:1.5px solid var(--border-strong,#c7ccd4); background:var(--surface); box-sizing:border-box; }
          .gd .cb i{ position:absolute; inset:-1.5px; border-radius:4px; background:var(--accent); color:#fff; font-size:.6rem; font-style:normal; font-weight:800; display:grid; place-items:center; }
          .gd .str.hl{ background:var(--accent-wash); border-radius:6px; }
          .gd .str.gone{ opacity:.45; text-decoration:line-through; }

          /* send screen / spec */
          .gd .sendc{ border:1px solid var(--line); border-radius:10px; background:var(--surface); padding:.45rem .6rem; font-size:.68rem; color:var(--soft); max-width:36rem; }
          .gd .sendc b{ color:var(--ink); }
          .gd .warnrow{ color:#b45309; font-weight:700; font-size:.64rem; margin-top:.2rem; }
          .gd .mail{ border:1px solid var(--line); border-radius:10px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.45rem .6rem; font-size:.66rem; color:var(--soft); }
          .gd .mail b{ color:var(--ink); }
          .gd .mail .att{ display:inline-block; margin-top:.3rem; border:1px solid var(--line); border-radius:5px; padding:.1rem .4rem; background:var(--panel); font-weight:700; color:var(--ink); }
          .gd .mails{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; max-width:36rem; }
          .gd .pdel{ border:1px solid #d1d5db; border-radius:6px; padding:.35rem .5rem; background:#fff; color:#1f2937; font-size:.62rem; }
          .gd .pdel .bl{ font-size:.54rem; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; font-weight:700; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; }
            .gd .sth, .gd .str{ grid-template-columns:1fr 1.4fr .8fr 2.2rem; }
            .gd .mails{ grid-template-columns:1fr; }
            .gd .nophone{ display:none; }
            .gd .sc{ min-height:430px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / suppliers</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a><a>Billing</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $tabs('Suppliers') . '
                  <div class="sh">Suppliers</div>
                  ' . $table([$IH, $BW, $CS]) . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters.</p>
                </div>

                <!-- 1 — who you buy from -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  ' . $tabs('Suppliers', '2.5s') . '
                  <div class="a-move" style="--fx:70%;--fy:80%;--tx:16.1rem;--ty:.9rem;--d:.6s;--md:1.6s">' . $ptr . '</div>
                  <div class="sh a-fade" style="--d:2.5s">Suppliers</div>
                  <span class="hn a-fade" style="--d:3s">Who you <b>order stock from</b> &mdash; these fill a product&rsquo;s <em>Order supplier</em> field and go on purchase orders.
                    Tick a row&rsquo;s delete box to remove a stray (they get added automatically when you save a product).</span>
                  <div class="row">
                    <span class="chip bad a-pop" style="--d:5.5s">&#10007; Not your fabric list</span>
                    <span class="chip a-pop" style="--d:8s">Product &rarr; <b>Order supplier</b></span>
                    <span class="chip a-pop" style="--d:11s">&#9993; Each supplier gets their own lines</span>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:17.5s">1 &middot; Delivery address</span>
                    <span class="chip warn a-pop" style="--d:19.5s">2 &middot; Each order email</span>
                  </div>
                </div>

                <!-- 2 — delivery address -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <span class="lb a-fade" style="--d:.3s">Delivery address <span class="q">(where suppliers ship to)</span></span>
                  <div class="ta3 a-ring" style="--d:2s"><span class="stk"><span class="ph a-out" style="--d:4s">Your business / warehouse address &mdash; this goes on every supplier order</span>
                    <span class="a-fade" style="--d:4s">' . $addr . '</span></span></div>
                  <div class="row mt"><span class="chip a-pop" style="--d:8s">&#128666; Exactly as a courier would need it</span></div>
                  <div class="a-rise mt" style="--d:13s">
                    <div class="ebnr" style="margin:0">No delivery address set &mdash; suppliers won&rsquo;t know where to ship. Add one under <b>Settings &rsaquo; Suppliers</b> first.</div>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:17s">It warns &mdash; it doesn&rsquo;t stop you</span>
                    <span class="chip bad a-pop" style="--d:19s">Deliver to: &mdash; no delivery address set &mdash;</span>
                  </div>
                </div>

                <!-- 3 — the table -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">One row per supplier</div>
                  <span class="hn a-fade" style="--d:.8s">Add the order email for each supplier. You can rename a supplier, tick <b>Remove</b> to delete it, or add one in the bottom row &mdash; then Save.
                    Suppliers you set on products appear here automatically.</span>
                  <div class="a-rise" style="--d:1.5s">' . $table([$IH, $BW, $CS]) . '</div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:4.5s">Supplier &mdash; the name</span>
                    <span class="chip a-pop" style="--d:6.5s">Order email &mdash; where orders go</span>
                    <span class="chip a-pop" style="--d:9.5s">Account no. &mdash; whose order it is</span>
                    <span class="chip a-pop" style="--d:15s">Remove &mdash; delete the row</span>
                  </div>
                </div>

                <!-- 4 — order email -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">The order email is the important one</div>
                  <div class="a-rise" style="--d:1s">' . $table([$IH, [$BW[0], '', $BW[2], 'hl', false, ['email' => '<span class="stk"><span class="ph a-out" style="--d:3s">orders@supplier.com</span><span class="a-type" style="--d:3s;--ts:31;--tt:1.8s">orders@brightwell-fabrics.co.uk</span></span>']], [$CS[0], '', $CS[2]]], false) . '</div>
                  <div class="row mt"><span class="chip a-pop" style="--d:6s">&#9993; Sent to exactly this address &mdash; nowhere else</span>
                    <span class="chip a-pop" style="--d:9.5s">&#128196; Copy it from their paperwork</span></div>
                  <div class="sendc a-rise mt" style="--d:12.5s"><b>&#128230; Coastline Supply</b>
                    <div class="warnrow">No order email for <b>Coastline Supply</b> &mdash; fix it under Settings &rsaquo; Suppliers to send this.</div></div>
                </div>

                <!-- 5 — fills itself -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">The list fills itself</div>
                  <div class="row" style="margin:.4rem 0 .6rem">
                    <span class="chip a-pop" style="--d:3s">Product saved &middot; Order supplier: <b>Harbour Tracks</b></span>
                    <span class="arrow a-fade" style="--d:5s">&rarr;</span>
                  </div>
                  <div>' . $table([$IH, $BW, $CS, ['Harbour Tracks', '', '', 'a-drop', false]], true, ['name' => '<span class="ph a-ring" style="--d:16.5s;border-radius:4px">+ Add a supplier</span>']) . '</div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:10s">Usually all you add is the email</span></div>
                </div>

                <!-- 6 — In House -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">In House &mdash; you make it yourself</div>
                  <div class="a-rise" style="--d:1s">' . $table([['In House', '', '', 'hl'], $BW], false) . '</div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:4.5s">&#127981; Made by you</span>
                    <span class="chip a-pop" style="--d:7s;border-color:var(--good)">Email blank &rarr; nothing is sent</span>
                  </div>
                  <div class="row mt"><span class="chip bad a-pop" style="--d:14s">Real address on In House &rarr; it gets emailed an order too</span></div>
                </div>

                <!-- 7 — save + email check -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="stk" style="display:grid">
                    <div class="bnr a-mid" style="--d:3s;--d2:6s">&#10003; Suppliers saved.</div>
                    <div class="ebnr a-drop" style="--d:7s">That doesn&rsquo;t look like a valid email for &ldquo;Coastline Supply&rdquo;.</div>
                  </div>
                  ' . $table([$BW, [$CS[0], '', $CS[2], '', false, ['email' => '<span class="stk"><span class="a-out" style="--d:6.5s">trade@coastline-supply.co.uk</span><span class="a-fade" style="--d:6.5s;color:var(--err)">trade@coastline</span></span>']]], false) . '
                  <div class="row mt">
                    <span class="btnp a-press" style="--d:2.5s">Save suppliers</span>
                    <span class="chip bad a-pop" style="--d:9.5s">Nothing saved &mdash; not even the address</span>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:13s">The message names the supplier &rarr; fix it &rarr; save again</span></div>
                </div>

                <!-- 8 — removing -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">Removing a supplier</div>
                  ' . $table([$BW, ['Old Supplier Ltd', '', '', '', false, ['rm' => '<span class="cb"><i class="a-pop" style="--d:3s">&#10003;</i></span>']], $CS], false) . '
                  <div class="row mt">
                    <span class="btnp a-press" style="--d:5s">Save suppliers</span>
                    <span class="chip a-pop" style="--d:5.5s;border-color:var(--good)">Ticked Remove &rarr; deleted</span>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:8s">Clearing the name box &rarr; the row is just skipped</span>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:12.5s">&#8635; Still on a product? It comes back when that product is saved</span></div>
                </div>

                <!-- 9 — renaming -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">Careful renaming</div>
                  ' . $table([[ '', $BW[1], $BW[2], 'hl', false, ['name' => '<span class="stk"><span class="a-out" style="--d:7s">Brightwell Fabrics</span><span class="a-fade" style="--d:7s">Brightwell Fabrics Ltd</span></span>']]], false) . '
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:3.5s">Product &middot; Order supplier: <b>Brightwell Fabrics</b></span>
                    <span class="chip a-pop" style="--d:4.5s">plain words, not a link</span>
                  </div>
                  <div class="sendc a-rise mt" style="--d:13s"><b>&#128230; Brightwell Fabrics</b>
                    <div class="warnrow">No order email for <b>Brightwell Fabrics</b> &mdash; fix it under Settings &rsaquo; Suppliers to send this.</div></div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:17s">Rename on the products first &rarr; then tidy this list</span></div>
                </div>

                <!-- 10 — what it is for -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="row"><span class="btnp a-press" style="--d:3.5s">&#128230; Send to suppliers</span>
                    <span class="arrow a-fade" style="--d:5s">&rarr;</span><span class="chip a-pop" style="--d:5.5s">Split by supplier</span></div>
                  <div class="mails mt">
                    <div class="mail a-drop" style="--d:7s">To: <b>orders@brightwell-fabrics.co.uk</b><br>Only their lines: 3 roller blinds<br><span class="att">&#128206; spec PDF</span></div>
                    <div class="mail a-drop" style="--d:8.5s">To: <b>trade@coastline-supply.co.uk</b><br>Only their lines: 2 vertical blinds<br><span class="att">&#128206; spec PDF</span></div>
                  </div>
                  <div class="a-rise mt" style="--d:13.5s"><div class="bl" style="font-size:.6rem;font-weight:800;color:var(--soft);letter-spacing:.06em">PURCHASE ORDER</div>
                  <div class="row" style="align-items:stretch;margin-top:.25rem">
                    <div class="pdel a-ring" style="--d:16s"><div class="bl">Supplier</div><b>Brightwell Fabrics</b><br><span style="color:#6b7280">Account no: BB2201</span></div>
                    <div class="pdel a-ring" style="--d:14.5s"><div class="bl">Deliver to</div>' . $addr . '</div>
                  </div></div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:20s">&#9888; Already sent &rarr; left unticked so you don&rsquo;t double-order</span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Settings</b> &rarr; the <b>Suppliers</b> tab (fifth of seven: Company, Quoting, Legal, Status colours,
             <b>Suppliers</b>, Accounting, Back up data). The grey line at the top says it all: <em>&ldquo;Who you order stock from &mdash; these
             fill a product&rsquo;s Order supplier field and go on purchase orders.&rdquo;</em> It is not your fabric list.</p>

          <ul class="steps">
            <li><b>Delivery address <span class="muted">(where suppliers ship to)</span></b> &mdash; the one address all your suppliers send to
                (placeholder <em>&ldquo;Your business / warehouse address &mdash; this goes on every supplier order&rdquo;</em>). It prints in the
                <b>Deliver to</b> box of every supplier order. Leave it empty and the send screen shows <em>&ldquo;No delivery address set &mdash;
                suppliers won&rsquo;t know where to ship. Add one under Settings &rsaquo; Suppliers first.&rdquo;</em> &mdash; a warning only: the
                order still goes, with <em>&ldquo;&mdash; no delivery address set &mdash;&rdquo;</em> in the box.</li>
            <li><b>The table</b> &mdash; one row per supplier, every cell editable: <b>Supplier</b> (the name; typing over it renames it),
                <b>Order email</b> (where the order is sent; placeholder <code>orders@supplier.com</code>), <b>Account no.</b> (your account number
                with them, printed on the order; this column only appears once that upgrade has been run on your site) and <b>Remove</b> (a tick
                box).</li>
            <li><b>Adding one</b> &mdash; the bottom row, showing <code>+ Add a supplier</code>. Most of the time you won&rsquo;t need it: saving a
                product with an <em>Order supplier</em> on it adds that name here automatically, so usually you only add the email.</li>
            <li><b>Save suppliers</b> &mdash; <b>&ldquo;Suppliers saved.&rdquo;</b> If any email isn&rsquo;t valid you get
                <code>That doesn&rsquo;t look like a valid email for &ldquo;Coastline Supply&rdquo;.</code> and <b>nothing</b> is saved, not even the
                delivery address &mdash; fix it and save again.</li>
          </ul>

          <p><b>In House</b> (if you have it) is sorted to the top. It means you make the blind yourself, so leave its <b>Order email blank</b>
             &mdash; then there is nothing to send. Put a real address on it and it is treated like any other supplier and gets emailed an order.
             Products that come from your <b>factory&rsquo;s catalogue</b> are different again: they need no supplier here at all &mdash; the product
             says <em>&ldquo;Made by &hellip; &mdash; orders go straight to their manufacturing.&rdquo;</em> and the send screen routes them there
             with <em>&ldquo;No supplier email needed.&rdquo;</em></p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Names are plain text &mdash; your products don&rsquo;t follow a rename.</b>
             Products hold the supplier&rsquo;s <em>name</em>. Rename a supplier here and the products still say the old name, so the send screen
             can&rsquo;t find an email: <em>&ldquo;No order email for Brightwell Fabrics &mdash; fix it under Settings &rsaquo; Suppliers to send
             this.&rdquo;</em> Change the name on the products first, then tidy this list. Renaming onto a name already in the list is skipped:
             <code>Suppliers saved &mdash; a rename was skipped because that name is already in your list.</code></div></div>

          <div class="oops"><b>Removing.</b> Only the <b>Remove</b> tick deletes a row &mdash; clearing the name box just skips that row. A
             supplier still set on a product comes back the next time that product is saved. If you see <code>Could not save suppliers: &hellip;
             &mdash; have you run migrate_suppliers.php?</code>, pass it to whoever looks after your site.</div>

          <p><b>What it&rsquo;s all for.</b> Once a quote is accepted, <b>&#128230; Send to suppliers</b> opens <b>Send order to suppliers</b>:
             <em>&ldquo;Each supplier below gets an email with only their lines and a spec PDF. Tick the ones to send, then Send selected
             orders.&rdquo;</em> The spec PDF carries your <b>Deliver to</b> address and your <b>Account no:</b> with that supplier. A product with no
             supplier shows <b>&#9888;&#65039; No supplier set</b>. Every send is logged: one already sent shows <b>&#9888;&#65039; Already sent</b> and is
             <em>&ldquo;left unticked so you don&rsquo;t double-order&rdquo;</em>. If nothing can be sent you&rsquo;ll see <em>&ldquo;Nothing&rsquo;s
             ready to send yet &mdash; set suppliers on your products and their emails in Settings.&rdquo;</em></p>

          <p class="prose"><b>Factory accounts</b> use the same list and delivery address from <b>Order bought-in</b> on their incoming orders,
             and the factory-only <b>Auto-order bought-in items</b> setting (Settings &rarr; Quoting) sends those orders automatically &mdash; only
             switch that on once every email here is confirmed.</p>',
        'script'  => $script,
];
