<?php
declare(strict_types=1);

/**
 * Guide: quote-new-order — "Placing an order with us (New order)" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Covers the DIRECT ORDER: a trade client sending an order straight to the
 * factory, with no retail customer and no quoting —
 *   - the sidebar "New" button by permission (_partials/sidebar.php: Create
 *     quotes → New quote, Create orders → New order, both → a two-item menu),
 *   - the Create orders tick (admin/users_edit.php),
 *   - /quote-builder/new_order.php: Order reference * (required), Customer name
 *     (optional — also on the labels), Sold for + inc VAT (optional), Order
 *     notes, Start order, its error and flash, and the same-reference warning
 *     ("Start order anyway"),
 *   - Sold for on the order screen (save_sold_for.php, qb_save_sold_for) and
 *     the Customer contact & fitting address fold-out (save_details.php) that
 *     feeds the Pending Fitting appointment made on placing,
 *   - where direct orders are listed (Quotes / Pipeline with "Draft order",
 *     then Orders — orders/index.php, orders/pipeline.php) and Delete order
 *     (delete.php), and the Dashboard effect (dashboard/index.php),
 *   - /quote-builder/edit.php in order shape ($isDirectOrder): the Order bar,
 *     Order actions with only "📦 Place order" while draft, no PDFs until placed,
 *     buying prices (markup/discount forced to 0 in add_item / update_item /
 *     _preview_core), "Order total (your price, ex VAT)", no WT / override /
 *     per-blind adjust / deposit / payments / send / sign / accept-decline,
 *     and the Order details panel (save_details.php),
 *   - Place order → change_status.php (then_place): confirm modal, auto-place
 *     to ordered or on to /quote-builder/order_suppliers.php,
 *   - after placing: locked, PDFs, Reopen as draft and its refusal.
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

/** A labelled control. $kind 'sel' adds a dropdown chevron, 'ta' makes it a box of text. */
$f = static fn (string $label, string $box, string $kind = '', string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib ' . $kind . '">' . $box . '</span></div>';
$ph  = static fn (string $t): string => '<span class="gph">' . $t . '</span>';
$rq  = '<em class="rq">*</em>';
$opt = ' <small>(optional)</small>';

// The dark sticky bar — "Order", not "Quote"; no accept / decline buttons on an order.
$bar = static fn (string $status = 'draft', string $total = '&pound;0.00', string $cls = '', string $style = ''): string =>
    '<div class="qsb ' . $cls . '" style="' . $style . '">Order HUG-2026-0012 <span class="pill">' . $status . '</span>'
    . '<span class="tt">Total ' . $total . '</span></div>';

// Order actions while it is a draft: Place order only (once a blind is on).
$oact = static fn (string $inner): string => '<div class="qact"><b>Order actions</b><div class="row">' . $inner . '</div></div>';

// Saved blinds in the Blinds list.
$row1 = '<div class="br"><span>1</span><span class="ds"><b>Living Room</b><br>Roller Blind &mdash; Bev Roller<br>Sunset / Ivory</span><span>1500 &times; 1600 mm</span><span class="n">&pound;59.50</span></div>';
$row2 = '<div class="br"><span>2</span><span class="ds"><b>Kitchen</b><br>Roller Blind &mdash; Bev Roller<br>Sunset / Ivory</span><span>900 &times; 1200 mm</span><span class="n">&pound;41.65</span></div>';
$head = '<div class="br th"><span>#</span><span>Description</span><span>Size</span><span class="n">Total</span></div>';
$tot  = '<div class="tots"><div><span>Order total <small>(your price, ex VAT)</small></span><b>&pound;101.15</b></div></div>';

// The Sold for row (new_order.php and the order screen): £ box + "inc VAT" tick.
$soldFor = static fn (string $box, string $tick = '&#10003;', string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">Sold for <small>(optional &mdash; what your customer is paying)</small></span>'
    . '<div class="sfr"><span class="pnd">&pound;</span><span class="ib sfb">' . $box . '</span><span class="ck"><i>' . $tick . '</i>inc VAT</span></div></div>';

// The New order form.
$newForm = static function (array $p = []) use ($f, $ph, $rq, $soldFor): string {
    $d = $p + [
        'ref' => $ph('Order reference *'), 'refCls' => '', 'refStyle' => '',
        'lab' => $ph('Customer name (optional)'), 'labCls' => '', 'labStyle' => '',
        'sf' => $ph('Sold for (optional)'), 'sfCls' => '', 'sfStyle' => '', 'tick' => '&#10003;',
        'not' => $ph('Order notes'), 'notCls' => '', 'notStyle' => '',
        'btn' => 'Start order', 'btnCls' => '', 'btnStyle' => '',
    ];
    return '<div class="frm">
        <div class="g2">' . $f('Order reference ' . $rq, $d['ref'], '', $d['refCls'], $d['refStyle'])
                          . $f('Customer name <small>(optional &mdash; also on the labels)</small>', $d['lab'], '', $d['labCls'], $d['labStyle']) . '</div>
        ' . $soldFor($d['sf'], $d['tick'], $d['sfCls'], $d['sfStyle']) . '
        ' . $f('Order notes', $d['not'], 'ta', $d['notCls'], $d['notStyle']) . '
        <div class="fact"><span class="btnp ' . $d['btnCls'] . '" style="' . $d['btnStyle'] . '">' . $d['btn'] . '</span><span class="btns">Cancel</span></div>
      </div>';
};

// A struck-out quoting extra (scene 10).
$gone = static fn (string $label, float $d): string =>
    '<span class="gone a-pop" style="--d:' . $d . 's">' . $label . '<i class="strk a-wide" style="--d:' . ($d + .6) . 's"></i></span>';

return [
        'aud'     => 'all',
        'section' => 'Orders',
        'title'   => 'Placing an order with us (New order)',
        'eyebrow' => 'Orders',
        'v'       => 2,
        'blurb'   => 'No quote, no retail customer: New order takes your reference, the blinds at your buying price, and one Place order button — plus the optional Sold for price that puts it in your Dashboard figures, the fitting address, and where your orders show up.',
        'lede'    => 'Not every job starts with a quote. If you already know what you want made, <b>New order</b> sends it
                      <b>straight to us</b>, the way you would on the old portal: no retail customer, nothing to send out for approval.
                      You give it <b>your order reference</b>, add the blinds &mdash; priced at <b>your buying price</b> &mdash; and press
                      <b>&#128230; Place order</b>. If you tell it what you <b>sold it for</b>, it counts in your Dashboard&rsquo;s sales and profit
                      too. This guide goes through it <b>slowly</b>, one idea per chapter. To start: the
                      <b>+ New</b> button at the top of the menu &rarr; <b>New order</b> (or <b>+ New order</b>, if that is the only thing your
                      login can start). <em>Building the blinds themselves is the same as on a quote &mdash; see &ldquo;Building a quote&rdquo;.</em>',
        'open'    => '/quote-builder/new_order.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:400px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.68rem; color:var(--accent); margin:.1rem 0 .4rem; }
          .gd .ohint{ font-size:.66rem; color:var(--faint); margin:0 0 .55rem; max-width:31rem; line-height:1.45; }

          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); } .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.4rem .6rem; font-size:.72rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; }
          .gd .ebn{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.4rem .6rem; font-size:.72rem; font-weight:700; color:var(--err); margin:0 0 .5rem; }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .stack{ display:grid; } .gd .stack > *{ grid-area:1/1; align-self:start; }
          .gd .fact{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.3rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }
          .gd .three{ display:grid; grid-template-columns:repeat(3,1fr); gap:.6rem; align-items:start; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .arrow{ color:var(--faint); font-weight:800; font-size:1.2rem; align-self:center; text-align:center; }
          .gd .flow{ display:grid; grid-template-columns:1fr 1.4rem 1fr 1.4rem 1fr; gap:.3rem; align-items:start; }

          /* form */
          .gd .frm{ display:flex; flex-direction:column; gap:.45rem; max-width:31rem; }
          .gd .g2{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
          .gd .g3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.5rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.2rem; min-width:0; position:relative; }
          .gd .fl{ font-size:.64rem; font-weight:700; color:var(--soft); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .fl small{ font-weight:400; color:var(--faint); }
          .gd .ib{ display:flex; align-items:center; min-height:27px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.72rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.sel{ padding-right:1.3rem; }
          .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.4rem; color:var(--faint); font-size:.68rem; }
          .gd .ib.ta{ min-height:40px; align-items:flex-start; padding-top:.35rem; }
          .gd .gph{ color:var(--faint); }
          .gd .sfr{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .sfr .pnd{ font-size:.74rem; color:var(--ink); }
          .gd .sfr .sfb{ width:7.5rem; }
          .gd .sfr .ck{ margin:0; }
          .gd .cfa{ border:1px dashed var(--line); border-radius:8px; padding:.3rem .5rem; }
          .gd .cfs{ font-size:.66rem; color:var(--soft); font-weight:600; }
          .gd .cfs small{ color:var(--faint); font-weight:400; }
          .gd .fhint{ font-size:.6rem; color:var(--faint); line-height:1.4; }
          .gd .ylw{ color:#92400e; background:#fef3c7; border-radius:8px; padding:.4rem .6rem; font-size:.66rem; line-height:1.45; max-width:31rem; margin:0 0 .5rem; }
          .gd .dpill{ display:inline-block; margin-left:.25rem; font-size:.52rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#92400e;
                      background:#fef3c7; border:1px solid #fde68a; border-radius:999px; padding:.05rem .4rem; }
          .gd .spill{ display:inline-block; border-radius:999px; padding:.05rem .45rem; font-size:.56rem; font-weight:700; background:#dbeafe; color:#1e40af; }
          .gd .spill.ord{ background:#dcfce7; color:#166534; }
          .gd .plc{ border:1px solid var(--line); border-radius:9px; background:var(--panel); padding:.35rem; font-size:.62rem; }
          .gd .plc h5{ margin:0 0 .3rem; font-size:.62rem; color:#fff; background:#2563eb; border-radius:6px; padding:.15rem .4rem; }
          .gd .plc .cd{ background:var(--surface); border:1px solid var(--line); border-radius:7px; padding:.3rem .4rem; margin-bottom:.25rem; color:var(--ink); line-height:1.4; }
          .gd .btnd{ display:inline-flex; align-items:center; background:#dc2626; color:#fff; border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:700; }
          .gd .rq{ color:#b91c1c; font-style:normal; }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.7rem; color:var(--ink); margin:.15rem .8rem .15rem 0; }
          .gd .ck > i{ width:13px; height:13px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:3px; display:grid; place-items:center;
                       font-style:normal; font-size:.66rem; font-weight:900; color:var(--accent); background:var(--surface); }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:.1rem 0 .35rem; display:flex; align-items:baseline; gap:.6rem; flex-wrap:wrap; }
          .gd .sech small{ margin-left:auto; font-weight:400; font-size:.6rem; color:var(--faint); }
          .gd .prv{ border-radius:8px; padding:.45rem .6rem; font-size:.72rem; border:1px solid var(--line); background:var(--panel); color:var(--faint); font-style:italic; }
          .gd .prv.ok{ background:var(--good-wash); border-color:color-mix(in srgb,var(--good) 40%,transparent); color:var(--ink); font-style:normal; }

          /* the mini menu (scene 2) — the real sidebar is hidden on phones, so it is drawn in the stage */
          .gd .mnu{ border-radius:9px; padding:.5rem; background:#1f2937; min-height:8.5rem; }
          .gd .mnu .nw{ display:block; text-align:center; background:#fff; color:#1f3b5b; border-radius:7px; padding:.3rem; font-size:.74rem; font-weight:800; }
          .gd .mnu .mi{ display:block; text-align:center; background:rgba(255,255,255,.12); color:#fff; border-radius:7px; padding:.26rem; font-size:.68rem; font-weight:600; margin-top:.25rem; }
          .gd .mnu small{ display:block; color:#94a3b8; font-size:.55rem; text-transform:uppercase; letter-spacing:.06em; margin:.5rem 0 .1rem .2rem; }
          .gd .mnu span.it{ display:block; color:#cbd5e1; font-size:.64rem; padding:.1rem .25rem; }
          .gd .who{ font-size:.64rem; font-weight:700; color:var(--soft); margin-bottom:.3rem; line-height:1.35; }
          .gd .who b{ color:var(--ink); }

          /* builder chrome */
          .gd .qsb{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; background:#1f2937; color:#fff; border-radius:9px; padding:.42rem .65rem; font-size:.72rem; font-weight:700; margin-bottom:.5rem; }
          .gd .qsb .pill{ display:inline-grid; background:#e5e7eb; color:#374151; border-radius:999px; padding:.05rem .5rem; font-size:.6rem; }
          .gd .qsb .pill > span{ grid-area:1/1; }
          .gd .qsb .tt{ margin-left:auto; }
          .gd .qact{ border:1px solid var(--line); border-radius:9px; padding:.4rem .55rem; background:var(--surface); margin-bottom:.55rem; min-height:3.2rem; }
          .gd .qact b{ display:block; font-size:.7rem; color:var(--ink); margin-bottom:.3rem; }
          .gd .qact .row{ display:flex; gap:.3rem; flex-wrap:wrap; align-items:center; }
          .gd .qact .btns{ font-size:.64rem; padding:.2rem .5rem; }
          .gd .qact .btnp{ font-size:.66rem; padding:.24rem .6rem; }
          .gd .cols{ display:grid; grid-template-columns:1fr 1.1fr; gap:.7rem; align-items:start; }
          .gd .pane{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); }
          .gd .empty{ text-align:center; color:var(--faint); font-size:.72rem; padding:.8rem .5rem; }
          .gd .bl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.64rem; background:var(--surface); }
          .gd .br{ display:grid; grid-template-columns:1.1rem 2.4fr 1.5fr 1fr; gap:.3rem; align-items:start; padding:.35rem .45rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .br:first-child{ border-top:0; }
          .gd .br.th{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .br .n{ text-align:right; } .gd .br .ds{ line-height:1.4; }
          .gd .tots{ font-size:.7rem; }
          .gd .tots div{ display:flex; justify-content:flex-end; gap:1rem; padding:.3rem .45rem; border-top:1px solid var(--line); align-items:center; flex-wrap:wrap; font-weight:800; color:var(--ink); }
          .gd .tots small{ color:var(--faint); font-weight:400; }
          .gd .tots b{ min-width:4.2rem; text-align:right; }

          /* duplicate warning */
          .gd .dup{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.45rem .65rem; font-size:.7rem; color:var(--ink); margin:0 0 .6rem; max-width:31rem; line-height:1.5; }
          .gd .dup ul{ margin:.25rem 0 .25rem 1.1rem; padding:0; }

          /* price chain (scene 9) */
          .gd .chain{ display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; margin:.6rem 0 .8rem; }
          .gd .chain .v{ display:flex; flex-direction:column; align-items:center; border:1px solid var(--line); border-radius:10px; padding:.4rem .65rem; background:var(--surface); min-width:4.8rem; }
          .gd .chain .v b{ font-size:1rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .chain .v small{ font-size:.58rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
          .gd .chain .v.out{ border-color:var(--good); } .gd .chain .v.out b{ color:var(--good); }
          .gd .chain .op{ font-size:.7rem; font-weight:800; border-radius:999px; padding:.15rem .5rem; background:var(--err-wash); color:var(--err); }

          /* struck-out extras (scene 10) */
          .gd .goneg{ display:flex; flex-wrap:wrap; gap:.45rem; max-width:34rem; }
          .gd .gone{ position:relative; display:inline-flex; align-items:center; border:1px dashed var(--line); border-radius:8px; padding:.35rem .6rem;
                     font-size:.72rem; font-weight:700; color:var(--faint); background:var(--panel); }
          .gd .gone .strk{ position:absolute; left:.35rem; right:.35rem; top:50%; height:2px; background:var(--err); border-radius:2px; }

          /* confirm + outcomes (scene 12) */
          .gd .cfm{ position:absolute; z-index:5; left:14%; top:4.6rem; max-width:18rem; background:var(--surface); border:1px solid var(--line);
                    border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.74rem; color:var(--ink); font-weight:600; }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }
          .gd .lst{ border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.64rem; background:var(--surface); max-width:31rem; }
          .gd .lst div{ display:grid; grid-template-columns:1.4fr 1fr 1fr .9fr; gap:.3rem; padding:.32rem .5rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .lst div:first-child{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; text-transform:uppercase; letter-spacing:.04em; }

          .gd .lst.lst3 div{ grid-template-columns:1.3fr 1fr 1.4fr; }

          @media (max-width:640px){
            .gd .sc{ min-height:590px; }
            .gd .lst.lst3 div{ grid-template-columns:1.3fr 1fr 1.4fr; } .gd .lst.lst3 div > :nth-child(2){ display:block; }
            .gd .two, .gd .cols, .gd .three, .gd .flow{ grid-template-columns:1fr; }
            .gd .flow .arrow i{ display:inline-block; transform:rotate(90deg); font-style:normal; line-height:1; }
            .gd .g3{ grid-template-columns:1fr 1fr; }
            .gd .mnu{ min-height:0; }
            .gd .cfm{ left:4%; right:4%; }
            .gd .lst div{ grid-template-columns:1.4fr 1fr .9fr; } .gd .lst div > :nth-child(2){ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / new order</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a><a>Orders</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $bar('draft', '&pound;101.15') . '
                  ' . $oact('<span class="btnp">&#128230; Place order</span>') . '
                  <div class="bl">' . $head . $row1 . $row2 . $tot . '</div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — what it is -->
                <div class="sc" data-scene="1" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">An order, straight to us</div>
                  <div class="chips" style="margin:0 0 .8rem">
                    <span class="chip bad a-pop" style="--d:6.5s">&#10007; No retail customer</span>
                    <span class="chip bad a-pop" style="--d:8.5s">&#10007; No quote to send out</span></div>
                  <div class="flow">
                    <div class="card a-rise" style="--d:11.5s"><h4>1 &middot; Your reference</h4>' . $f('Order reference ' . $rq, 'PO 4471') . '</div>
                    <div class="arrow a-fade" style="--d:13.5s"><i>&rarr;</i></div>
                    <div class="card a-rise" style="--d:14s"><h4>2 &middot; The blinds</h4><div class="bl" style="font-size:.6rem"><div class="br" style="grid-template-columns:1fr auto"><span><b>Living Room</b></span><span>&pound;59.50</span></div><div class="br" style="grid-template-columns:1fr auto"><span><b>Kitchen</b></span><span>&pound;41.65</span></div></div></div>
                    <div class="arrow a-fade" style="--d:15.5s"><i>&rarr;</i></div>
                    <div class="card a-rise" style="--d:16s"><h4>3 &middot; Place it</h4><span class="btnp a-ring" style="--d:17s">&#128230; Place order</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:3s">Just like the old portal</span></div>
                </div>

                <!-- 2 — the New button -->
                <div class="sc" data-scene="2" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">The New button</div>
                  <p class="scs a-fade" style="--d:.6s">Top of the menu &mdash; what it offers follows your login.</p>
                  <div class="three">
                    <div class="a-rise" style="--d:7s"><div class="who"><b>Create quotes</b> + <b>Create orders</b></div>
                      <div class="mnu"><span class="nw a-press" style="--d:9.5s">+ New</span>
                        <span class="mi a-drop" style="--d:10.3s">New quote</span>
                        <span class="a-drop" style="--d:10.8s;display:block"><span class="mi a-ring" style="--d:13s">New order</span></span>
                        <small>Work</small><span class="it">Dashboard</span></div></div>
                    <div class="a-rise" style="--d:16s"><div class="who"><b>Create orders</b> only</div>
                      <div class="mnu"><span class="nw a-ring" style="--d:18s">+ New order</span>
                        <small>Work</small><span class="it">Dashboard</span><span class="it">Calendar</span></div></div>
                    <div class="a-rise" style="--d:3s"><div class="who"><b>Create quotes</b> only</div>
                      <div class="mnu"><span class="nw">+ New</span>
                        <small>Work</small><span class="it">Dashboard</span><span class="it">Calendar</span></div>
                      <div class="scs" style="margin-top:.3rem">&rarr; opens New quote</div></div>
                  </div>
                </div>

                <!-- 3 — who can -->
                <div class="sc" data-scene="3" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Who can place orders</div>
                  <div class="chips" style="margin:0 0 .7rem"><span class="chip a-pop" style="--d:2.5s">Setup &rarr; Users &rarr; the login</span></div>
                  <div class="card a-rise" style="--d:4s;max-width:31rem"><h4>Permissions</h4>
                    <span class="ck"><i>&#10003;</i>Create quotes</span>
                    <span class="ck a-ring" style="--d:6.5s;border-radius:4px"><i><span class="a-pop" style="--d:7.5s">&#10003;</span></i>Create orders</span>
                    <span class="ck"><i></i>View all customer jobs</span>
                    <span class="ck"><i></i>View costs</span></div>
                  <div class="chips">
                    <span class="chip ok a-pop" style="--d:9.5s">Start, open and place orders</span>
                    <span class="chip a-pop" style="--d:12.5s">Sees the direct orders in the list &mdash; even without View all customer jobs</span>
                    <span class="chip a-pop" style="--d:17.5s">Admin &rarr; always both choices</span></div>
                </div>

                <!-- 4 — your reference -->
                <div class="sc" data-scene="4" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Your order reference</div>
                  <div class="pt">New order</div><div class="psub">&larr; Order history</div>
                  <div class="stack">
                    <p class="ohint a-out" style="--d:16.4s">Place an order straight with us. Enter your reference, then add the blinds on the next screen &mdash; prices shown are your buying prices.</p>
                    <div class="ebn a-mid" style="--d:16.5s;--d2:21s">Please enter your order reference.</div>
                    <p class="ohint a-fade" style="--d:21.1s">Place an order straight with us. Enter your reference, then add the blinds on the next screen &mdash; prices shown are your buying prices.</p>
                  </div>
                  ' . $newForm([
                        'refCls' => 'a-ring', 'refStyle' => '--d:3s',
                        'ref' => '<span class="swap"><span class="gph a-out" style="--d:21.2s">Order reference *</span><span class="a-type" style="--d:21.3s;--ts:7;--tt:.6s">PO 4471</span></span>',
                        'btnCls' => 'a-press', 'btnStyle' => '--d:15.5s',
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:6.5s">Your own reference &mdash; the one you quote if you ring us</span>
                    <span class="chip a-pop" style="--d:11s">Travels with the order all the way through</span></div>
                </div>

                <!-- 5 — labels + notes -->
                <div class="sc" data-scene="5" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Customer name, and notes</div>
                  ' . $newForm([
                        'ref' => 'PO 4471',
                        'labCls' => 'a-ring', 'labStyle' => '--d:1s',
                        'lab' => '<span class="swap"><span class="gph a-out" style="--d:2.9s">Customer name (optional)</span><span class="a-type" style="--d:3s;--ts:9;--tt:.8s">Mrs Patel</span></span>',
                        'notCls' => 'a-ring', 'notStyle' => '--d:14.6s',
                        'not' => '<span class="swap"><span class="gph a-out" style="--d:19.5s">Order notes</span><span class="a-type" style="--d:19.6s;--ts:28;--tt:1.6s">Please pack the two together</span></span>',
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5.4s">&#127991; Also printed on the labels &mdash; tell your blinds apart</span>
                    <span class="chip a-pop" style="--d:11.3s">Just a name &mdash; no customer record is made</span></div>
                </div>

                <!-- 6 — Sold for -->
                <div class="sc" data-scene="6" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Sold for &mdash; what your customer is paying</div>
                  <div class="stack">
                    <p class="ylw a-out" style="--d:22.6s">An order only knows what you pay us. Fill in <b>Sold for</b> (now or later) and it counts in your Dashboard&rsquo;s sales and profit; leave it blank and the order is kept out of those figures.</p>
                    <div class="bnr a-pop" style="--d:22.8s">Order HUG-2026-0012 started &mdash; add the blinds, then Place order.</div>
                  </div>
                  ' . $newForm([
                        'ref' => 'PO 4471', 'lab' => 'Mrs Patel', 'not' => 'Please pack the two together',
                        'sfCls' => 'a-ring', 'sfStyle' => '--d:1s',
                        'sf' => '<span class="swap"><span class="gph a-out" style="--d:2.3s">Sold for (optional)</span><span class="a-type" style="--d:2.4s;--ts:6;--tt:.5s">180.00</span></span>',
                        'tick' => '<span class="a-pop" style="--d:9.8s">&#10003;</span>',
                        'btnCls' => 'a-press', 'btnStyle' => '--d:21.4s',
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:6.6s">&#128274; Only for your own figures &mdash; we never see it</span>
                    <span class="chip a-pop" style="--d:9.6s">inc VAT: starts ticked if you&rsquo;re VAT registered</span>
                    <span class="chip ok a-pop" style="--d:12.9s">With a price &rarr; in your sales and profit</span>
                    <span class="chip bad a-pop" style="--d:17.9s">Blank &rarr; kept out</span></div>
                </div>

                <!-- 7 — the same order twice -->
                <div class="sc" data-scene="7" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">The same order, keyed in twice</div>
                  <div class="dup a-rise" style="--d:7s"><b>You already have an order with the reference &ldquo;PO 4471&rdquo;:</b>
                    <ul><li class="a-fade" style="--d:9.5s">HUG-2026-0009 &mdash; ordered, started 2 Oct 2026</li></ul>
                    Is this a new, separate order? If so, press <b>Start order anyway</b>. If not, cancel so it isn&rsquo;t made twice.</div>
                  ' . $newForm([
                        'ref' => 'PO 4471', 'refCls' => 'a-ring', 'refStyle' => '--d:22s',
                        'btn' => '<span class="swap"><span class="a-out" style="--d:7s">Start order</span><span class="a-fade" style="--d:7s">Start order anyway</span></span>',
                        'btnCls' => 'a-ring', 'btnStyle' => '--d:15s',
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3s">Office <b>and</b> the boss? It happens.</span>
                    <span class="chip a-pop" style="--d:19s">Not a new order? <b>Cancel</b></span>
                    <span class="chip a-pop" style="--d:22.5s">Change the reference &rarr; checked again</span></div>
                </div>

                <!-- 8 — the order screen -->
                <div class="sc" data-scene="8" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">The order screen</div>
                  <div class="a-ring" style="--d:5.5s;border-radius:9px">' . $bar('draft', '<span class="swap"><span class="a-out" style="--d:16s">&pound;0.00</span><span class="a-fade" style="--d:16s">&pound;59.50</span></span>') . '</div>
                  <div class="qact a-rise" style="--d:10s"><b>Order actions</b><div class="row">
                    <span class="a-pop" style="--d:17s"><span class="btnp a-ring" style="--d:17.5s">&#128230; Place order</span></span>
                    <span class="scs a-out" style="--d:16.8s;margin:0">Place order appears once a blind is on</span></div></div>
                  <div class="cols">
                    <div class="pane a-rise" style="--d:2s"><div class="sech">Add blind <small>Customer details &amp; references below &darr;</small></div>
                      ' . $f('Product ' . $rq, $ph('Choose product...'), 'sel') . '</div>
                    <div class="pane a-rise" style="--d:3s"><div class="sech">Blinds</div>
                      <div class="stack"><div class="empty a-out" style="--d:16s">No blinds yet</div><div class="a-drop" style="--d:16s"><div class="bl">' . $row1 . '</div></div></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:20.5s">&#128196; No PDFs until it&rsquo;s placed</span></div>
                </div>

                <!-- 9 — adding blinds -->
                <div class="sc" data-scene="9" data-len="21">
                  <div class="sct a-fade" style="--d:.2s">Adding the blinds &mdash; just like a quote</div>
                  <div class="cols">
                    <div class="pane"><div class="sech">Add blind</div>
                      <div class="frm">
                        <div class="g3">' . $f('Product ' . $rq, '<span class="a-fade" style="--d:4s">Roller Blind</span>', 'sel')
                            . $f('System', '<span class="a-fade" style="--d:5.5s">Bev Roller</span>', 'sel')
                            . $f('Fabric ' . $rq, '<span class="a-fade" style="--d:7s">Sunset / Ivory</span>') . '</div>
                        <div class="g3">' . $f('Room name', '<span class="a-type" style="--d:8.6s;--ts:7;--tt:.6s">Kitchen</span>')
                            . $f('Width (mm) ' . $rq, '<span class="a-type" style="--d:10.4s;--ts:3;--tt:.3s">900</span>')
                            . $f('Drop (mm) ' . $rq, '<span class="a-type" style="--d:11.2s;--ts:4;--tt:.4s">1200</span>') . '</div>
                        <div class="prv ok a-pop" style="--d:13.5s"><b>&pound;41.65</b> per blind</div>
                        <div class="fact"><span class="btnp a-press" style="--d:16.5s">Save</span><span class="btns">Save and add another blind</span></div>
                      </div></div>
                    <div class="pane"><div class="sech">Blinds</div><div class="bl">' . $head . $row1 . '<div class="a-drop" style="--d:17.5s">' . $row2 . '</div></div></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:13.8s">Live price before you save</span></div>
                </div>

                <!-- 10 — buying price -->
                <div class="sc" data-scene="10" data-len="20">
                  <div class="sct a-fade" style="--d:.2s">Your buying price</div>
                  <div class="chain">
                    <div class="v a-pop" style="--d:2s"><small>Base</small><b>&pound;70.00</b></div>
                    <span class="op a-pop" style="--d:4s">&minus; your trade discount 15%</span>
                    <div class="v out a-pop" style="--d:5.5s"><small>Your price</small><b>&pound;59.50</b></div>
                  </div>
                  <div class="goneg" style="margin-bottom:.8rem">' . $gone('+ markup', 7) . $gone('&minus; retail discount', 8) . '</div>
                  <div class="bl a-rise" style="--d:12.5s;max-width:31rem">' . $head . $row1 . $row2 . '<div class="a-ring" style="--d:14s">' . $tot . '</div></div>
                  <div class="chips"><span class="chip a-pop" style="--d:17.5s">No VAT line on the order</span></div>
                </div>

                <!-- 11 — what\'s not here -->
                <div class="sc" data-scene="11" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">No customer, so no quoting extras</div>
                  <div class="goneg">'
                    . $gone('WT', 3) . $gone('Override price', 4) . $gone('Adjust price for this blind', 5.3)
                    . $gone('Deposit', 7.5) . $gone('Payments', 8.4)
                    . $gone('Send to customer', 10.5) . $gone('&#9997; Customer signs here', 11.8)
                    . $gone('&#10003; Customer accepted', 13.2) . $gone('&#10005; Customer declined', 13.8) . '</div>
                  <div class="a-rise" style="--d:17.5s;margin-top:1rem;max-width:31rem">' . $oact('<span class="btnp">&#128230; Place order</span>') . '</div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:18.5s">Just the blinds, and the order</span></div>
                </div>

                <!-- 12 — order details -->
                <div class="sc" data-scene="12" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Order details, and the fitting address</div>
                  <div class="stack"><div class="bnr a-pop" style="--d:25.6s">Order details saved.</div></div>
                  <div class="pane a-rise" style="--d:1.5s;max-width:33rem"><div class="sech">Order details</div>
                    <div class="frm">
                      ' . $f('Customer name <small>(optional &mdash; also printed on the labels)</small>', 'Mrs Patel', '', 'a-ring', '--d:5.1s') . '
                      <div class="g2">' . $f('Order reference ' . $rq, 'PO 4471', '', 'a-ring', '--d:6.5s')
                          . $f('Additional reference' . $opt, '<span class="swap"><span class="gph a-out" style="--d:9.9s">Additional reference (optional)</span><span class="a-type" style="--d:10s;--ts:9;--tt:.7s">Site 3B</span></span>', '', 'a-ring', '--d:9.6s') . '</div>
                      ' . $f('Order notes', 'Please pack the two together', '', 'a-ring', '--d:8s') . '
                      <div class="cfa a-ring" style="--d:11.2s;border-radius:8px"><div class="cfs">&#9662; Customer contact &amp; fitting address <small>(optional &mdash; goes on the fitting appointment)</small></div>
                        <div class="a-drop" style="--d:12.4s"><div class="frm" style="margin-top:.35rem;gap:.3rem">
                          <div class="g3">' . $f('Phone', '<span class="a-type" style="--d:15.3s;--ts:12;--tt:.6s">01823 555019</span>') . $f('Mobile', '<span class="gph">Mobile</span>') . $f('Email', '<span class="a-type" style="--d:15.9s;--ts:14;--tt:.6s">patel@mail.com</span>') . '</div>
                          <div class="g2">' . $f('Address line 1', '<span class="a-type" style="--d:16.3s;--ts:11;--tt:.5s">4 Mill Lane</span>') . $f('Address line 2', '<span class="gph">Address line 2</span>') . '</div>
                          <div class="g3">' . $f('Town', '<span class="a-type" style="--d:16.8s;--ts:7;--tt:.4s">Taunton</span>') . $f('County', '<span class="gph">County</span>') . $f('Postcode', '<span class="a-type" style="--d:17.2s;--ts:7;--tt:.4s">TA1 2PX</span>') . '</div>
                          <div class="fhint a-ring" style="--d:17.4s;border-radius:4px">Fill these in before you place the order &mdash; the fitting appointment it books picks them up.</div>
                        </div></div></div>
                      <div class="fact"><span class="btnp a-press" style="--d:25.2s">Save details</span></div>
                    </div></div>
                </div>

                <!-- 13 — place order -->
                <div class="sc" data-scene="13" data-len="28.5">
                  <div class="sct a-fade" style="--d:.2s">Place order</div>
                  ' . $bar('<span class="a-out" style="--d:13.9s">draft</span><span class="a-fade" style="--d:13.9s">ordered</span>', '&pound;101.15') . '
                  ' . $oact('<span class="stack"><span class="a-out" style="--d:13.9s"><span class="btnp a-press" style="--d:2.2s">&#128230; Place order</span></span><span class="a-fade" style="--d:14.1s"><span class="btns">View PDF</span> <span class="btns">Download PDF</span></span></span>') . '
                  <div class="cfm a-mid" style="--d:3.2s;--d2:8.5s">Place this order with us now?
                    <div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:7s">Yes, continue</span></div></div>
                  <div class="two">
                    <div class="card a-rise" style="--d:10.6s"><h4>All from our catalogue</h4>
                      <div class="bnr a-pop" style="--d:13.9s;margin:0">Status: ordered. Due 23 Oct 2026. Installation appointment is in the calendar&rsquo;s &ldquo;Pending Fitting&rdquo; tray &mdash; drag it onto the right date and assign a fitter when ready. Sent straight to the workshop &mdash; all in-house, no supplier order needed.</div></div>
                    <div class="card a-rise" style="--d:18.2s"><h4>Something bought in elsewhere</h4>
                      You go on to <b>Send order to suppliers</b> to finish:
                      <div style="margin-top:.4rem"><span class="btnp a-ring" style="--d:21.2s">&#128230; Send &amp; place order</span></div></div>
                  </div>
                  <div class="chips"><span class="chip ok a-pop" style="--d:23.5s"><span>&#128197; Either way: a fitting waits in the calendar&rsquo;s <b>Pending Fitting</b> tray</span></span></div>
                </div>

                <!-- 14 — afterwards -->
                <div class="sc" data-scene="14" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Once it&rsquo;s placed</div>
                  ' . $bar('ordered', '&pound;101.15') . '
                  ' . $oact('<span class="btns a-pop" style="--d:3.2s">View PDF</span><span class="btns a-pop" style="--d:3.6s">Download PDF</span><span class="a-pop" style="--d:13.2s"><span class="btns a-ring" style="--d:13.7s">Reopen as draft</span></span>') . '
                  <div class="pane a-rise" style="--d:6s;max-width:31rem">
                    ' . $soldFor('<span class="swap"><span class="gph a-out" style="--d:8.6s">0.00</span><span class="a-type" style="--d:8.7s;--ts:6;--tt:.5s">180.00</span></span>', '&#10003;', 'a-ring', '--d:6.3s') . '
                    <div class="fact"><span class="btns a-press" style="--d:9.8s">Save</span></div>
                    <div class="fhint" style="margin-top:.3rem">Only for your own figures &mdash; we never see it. With a price, this order counts in your Dashboard&rsquo;s sales and profit; without one, it&rsquo;s left out. You can add or change it at any time.</div></div>
                  <div class="stack" style="margin-top:.55rem;max-width:31rem">
                    <div class="bnr a-mid" style="--d:10.2s;--d2:17.5s">Sold for price saved &mdash; this order now counts in your sales and profit figures.</div>
                    <div class="ebn a-pop" style="--d:18.4s">This order is already being made &mdash; contact the factory to change it.</div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:2.4s">&#128274; Locked</span><span class="chip ok a-pop" style="--d:7s">Sold for stays open</span></div>
                </div>

                <!-- 15 — where to find it, and Delete -->
                <div class="sc" data-scene="15" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Where your orders are</div>
                  <div class="two">
                    <div>
                      <div class="scs a-fade" style="--d:6.9s;margin-bottom:.3rem"><b>Retail &rarr; Quotes</b> &mdash; while it&rsquo;s a draft</div>
                      <div class="lst lst3 a-rise" style="--d:6.9s"><div><span>Quote #</span><span>Customer</span><span>Status</span></div>
                        <div><span><b>HUG-2026-0012</b></span><span>Mrs Patel</span><span><span class="spill">Quote</span><span class="a-pop" style="--d:11.1s"><span class="dpill a-ring" style="--d:11.6s">Draft order</span></span></span></div>
                        <div><span><b>HUG-2026-0011</b></span><span>Mr Jones</span><span><span class="spill">Quote</span><span class="dpill" style="opacity:.75">Not sent</span></span></div></div>
                      <div class="scs a-fade" style="--d:15s;margin:.6rem 0 .3rem"><b>Retail &rarr; Orders</b> &mdash; once it&rsquo;s placed</div>
                      <div class="lst lst3 a-rise" style="--d:16.6s"><div><span>Quote #</span><span>Customer</span><span>Status</span></div>
                        <div><span><b>HUG-2026-0009</b></span><span>Mrs Patel</span><span><span class="spill ord">ordered</span></span></div></div>
                    </div>
                    <div>
                      <div class="scs a-fade" style="--d:8.8s;margin-bottom:.3rem"><b>Work &rarr; Pipeline</b></div>
                      <div class="plc a-rise" style="--d:8.8s"><h5>Quote</h5>
                        <div class="cd"><b>HUG-2026-0012</b> &middot; Mrs Patel<br><span class="dpill a-pop" style="--d:11.1s;margin:0">Draft order</span></div>
                        <div class="cd"><b>HUG-2026-0011</b> &middot; Mr Jones<br><span class="dpill" style="margin:0">Not sent</span></div></div>
                      <div class="pane a-rise" style="--d:19.6s;margin-top:.6rem"><div class="sech" style="color:#b91c1c">Danger zone</div>
                        <span class="btnd a-ring" style="--d:21.3s">Delete order</span>
                        <div class="fhint" style="margin-top:.3rem">Delete order HUG-2026-0012? This is permanent &mdash; all blinds go too.</div></div>
                    </div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:13.5s">Draft order = not placed yet</span></div>
                </div>

                <!-- 16 — quote or order: the money side -->
                <div class="sc" data-scene="16" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">Quote or order? What it does to your Dashboard</div>
                  <div class="three" style="margin-top:.5rem">
                    <div class="card a-rise" style="--d:2.4s"><h4>New quote</h4>
                      Your customer pays <b>&pound;180.00</b><br>You pay us <b>&pound;101.15</b><br>
                      <span class="a-pop" style="--d:8.6s;display:inline-block;color:var(--good);font-weight:800">Profit shown</span></div>
                    <div class="card a-rise" style="--d:9.9s"><h4>New order, no Sold for</h4>
                      Your customer pays <b>?</b><br>You pay us <b>&pound;101.15</b><br>
                      <span class="a-pop" style="--d:14.6s;display:inline-block;color:#b45309;font-weight:800">Kept out of sales &amp; profit</span></div>
                    <div class="a-rise" style="--d:17.7s"><div class="card a-ring" style="--d:18.4s"><h4>New order + Sold for</h4>
                      Sold for <b>&pound;180.00</b> <small>inc VAT</small><br>You pay us <b>&pound;101.15</b><br>
                      <span class="a-pop" style="--d:20.1s;display:inline-block;color:var(--good);font-weight:800">Counts, at that price</span></div></div>
                  </div>
                  <div class="a-rise" style="--d:21.2s;margin-top:.8rem;max-width:17rem;border:1px solid var(--line);border-radius:10px;background:var(--surface);padding:.45rem .55rem">
                    <div class="a-ring" style="--d:24.4s;border-radius:6px"><div style="font-size:.54rem;text-transform:uppercase;letter-spacing:.05em;color:var(--faint);font-weight:700">Direct orders &mdash; no selling price</div>
                    <div style="font-size:1rem;font-weight:800;color:var(--ink)">&pound;101.15</div>
                    <div style="font-size:.56rem;color:var(--faint)">1 order at your cost, ex VAT &middot; not counted in revenue or profit until you add what you sold it for</div></div></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>What it is.</b> A <b>direct order</b>: an order you send <b>straight to us</b>, with no retail customer and no quote to send out
             for approval &mdash; the way you used to on the old portal. You give it your reference, add the blinds at <b>your buying price</b>,
             and press <b>&#128230; Place order</b>.</p>

          <p><b>Getting there &mdash; the New button.</b> At the top of the menu. What it offers depends on your login&rsquo;s ticks on the
             <b>Users</b> page:</p>
          <ul class="steps">
            <li><b>Create quotes</b> and <b>Create orders</b> (and every admin): <b>+ New</b> opens a small menu &mdash; <b>New quote</b> and
                <b>New order</b>.</li>
            <li><b>Create orders</b> only: the button reads <b>+ New order</b> and goes straight there.</li>
            <li><b>Create quotes</b> only: <b>+ New</b> opens New quote, as before.</li>
          </ul>
          <p>A login with <b>Create orders</b> can start, open and place direct orders, and sees them in the orders list even without
             <b>View all customer jobs</b>. Without it, the New order screen says <em>&ldquo;You don&rsquo;t have permission to place orders. Speak
             to your admin if you think this is wrong.&rdquo;</em></p>

          <p><b>The New order screen.</b> <em>&ldquo;Place an order straight with us. Enter your reference, then add the blinds on the next screen
             &mdash; prices shown are your buying prices.&rdquo;</em></p>
          <ul class="steps">
            <li><b>Order reference *</b> &mdash; <b>required</b>. Your own reference (your PO or job number) &mdash; the one you&rsquo;d quote if you
                rang us about it. Leave it empty and <b>Start order</b> says <code>Please enter your order reference.</code></li>
            <li><b>Customer name (optional &mdash; also on the labels)</b> &mdash; your customer&rsquo;s name, also printed on the labels so you can
                tell your blinds apart. It is just a name; no customer record is made.</li>
            <li><b>Sold for (optional &mdash; what your customer is paying)</b> &mdash; a &pound; amount with an <b>inc VAT</b> tick (ticked to start
                with if your business has a VAT number; untick it if the price you type is without VAT). It is <b>only for your own figures</b>
                &mdash; we never see it. With a price, the order counts in your <b>Dashboard</b>&rsquo;s sales and profit; leave it blank and the
                order is kept out of those figures. You can fill it in now or later, on the order screen. The amber note on the screen says the
                same: <em>&ldquo;An order only knows what you pay us. Fill in Sold for (now or later) and it counts in your Dashboard&rsquo;s sales
                and profit; leave it blank and the order is kept out of those figures.&rdquo;</em></li>
            <li><b>Order notes</b> &mdash; anything else we should know.</li>
            <li><b>Start order</b> creates it and opens the order screen at <b>Add blind</b>:
                <code>Order HUG-2026-0012 started &mdash; add the blinds, then Place order.</code> <b>Cancel</b> goes back to Order history.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>The same order keyed in twice.</b> If the reference is already on one of your
             jobs (anything except a declined one), you are shown them before anything is made &mdash; <em>&ldquo;You already have an order with the
             reference &ldquo;PO 4471&rdquo;:&rdquo;</em> with each one&rsquo;s number, status and the date it was started, then <em>&ldquo;Is this a new,
             separate order? If so, press Start order anyway. If not, cancel so it isn&rsquo;t made twice.&rdquo;</em> The button now reads
             <b>Start order anyway</b>. Change the reference instead and it is checked again. The match ignores capitals and spaces at either end.
             (We see a similar warning on our side when two placed orders from you share a reference.)</div></div>

          <p><b>The order screen.</b> The same builder as a quote, shaped for an order. The dark bar reads <b>Order HUG-2026-0012</b> with its status
             (<b>draft</b> until you place it). <b>Order actions</b> holds just one button, <b>&#128230; Place order</b>, which appears once there is at
             least one blind on it. <b>View PDF</b> and <b>Download PDF</b> don&rsquo;t appear until the order is placed.</p>
          <ul class="steps">
            <li><b>Adding blinds</b> is exactly as on a quote &mdash; Product, System, Fabric, Room name, Width, Drop, options, the live price, then
                <b>Save</b> or <b>Save and add another blind</b>. (The guide <b>&ldquo;Building a quote&rdquo;</b> covers it in detail.)</li>
            <li><b>Your buying price.</b> Every price is what you pay us: your trade discount is already taken off, and <b>no markup and no retail
                discount</b> are added &mdash; on the live price and when the blind is saved. If your login can see costs, the live price spells it out
                &mdash; base before the trade discount, the trade discount, then your price, e.g. <em>&ldquo;base &pound;70.00 &middot; trade discount
                15% &middot; &pound;59.50 per blind&rdquo;</em> (behind the eye icon, as on a quote). The bottom line reads <b>Order total (your price, ex VAT)</b>; there is no VAT line on the
                order.</li>
            <li><b>Not on an order:</b> the WT charge, <b>Override price</b>, the per-blind price adjustment, <b>Deposit</b>, <b>Payments</b>,
                <b>Send to customer</b>, <b>&#9997; Customer signs here</b>, and <b>&#10003; Customer accepted</b> / <b>&#10005; Customer declined</b>.</li>
          </ul>

          <p><b>Order details.</b> In place of a customer, a panel called <b>Order details</b>: <b>Customer name (optional &mdash; also printed
             on the labels)</b>, <b>Order reference *</b>, <b>Additional reference (optional)</b> and <b>Order notes</b>. Below them, a fold-out
             <b>Customer contact &amp; fitting address (optional &mdash; goes on the fitting appointment)</b>: <b>Phone</b>, <b>Mobile</b>,
             <b>Email</b>, <b>Address line 1</b>, <b>Address line 2</b>, <b>Town</b>, <b>County</b> and <b>Postcode</b> &mdash;
             <em>&ldquo;Fill these in before you place the order &mdash; the fitting appointment it books picks them up.&rdquo;</em> (It opens by
             itself once an address or phone number is saved.) Then <b>Save details</b> &rarr; <code>Order details saved.</code> The order reference
             stays required: empty it and you get <code>The order reference is required.</code></p>

          <p><b>Sold for, on the order.</b> Under the details is the <b>Sold for</b> box again (<b>&pound;</b>, <b>inc VAT</b>, <b>Save</b>), with
             <em>&ldquo;Only for your own figures &mdash; we never see it. With a price, this order counts in your Dashboard&rsquo;s sales and profit;
             without one, it&rsquo;s left out. You can add or change it at any time.&rdquo;</em> It has its own <b>Save</b>, so it still works after the
             order is placed and locked: <code>Sold for price saved &mdash; this order now counts in your sales and profit figures.</code> Empty the
             box and save to take the price off again (<code>Sold for price cleared.</code>).</p>

          <p><b>Placing it.</b> Press <b>&#128230; Place order</b>. It asks <em>&ldquo;Place this order with us now?&rdquo;</em> &mdash;
             <b>Cancel</b> or <b>Yes, continue</b>. Then:</p>
          <ul class="steps">
            <li><b>Everything from our catalogue</b> (and <b>Auto-place in-house orders</b> left on, as it is by default): it goes straight through. The
                status becomes <b>ordered</b> and a green bar says so, with the due date &mdash; e.g. <code>Status: ordered. Due 23 Oct 2026. Sent straight
                to the workshop &mdash; all in-house, no supplier order needed.</code> (or <em>&ldquo;Sent straight to the factory &mdash; no supplier order
                needed from you (the factory orders any bought-in items itself).&rdquo;</em>)</li>
            <li><b>Anything bought in from another supplier</b>: you go on to <b>Send order to suppliers</b> (<em>&ldquo;Order accepted &mdash; place it
                below (suppliers get emailed their lines).&rdquo;</em>). Our lines are listed as going straight to manufacturing; tick the suppliers to
                email and press <b>&#128230; Send &amp; place order</b> (or <b>&#128230; Place order</b> if there is nothing to email). See
                <b>&ldquo;Quote &rarr; order &rarr; invoice&rdquo;</b> for that screen.</li>
            <li><b>The fitting.</b> Either way, placing it drops a fitting into the <b>Pending Fitting</b> tray on your <b>Calendar</b>, carrying the
                customer name, contact and fitting address from <b>Order details</b>. When it goes straight through, the green bar says so too:
                <em>&ldquo;Installation appointment is in the calendar&rsquo;s &ldquo;Pending Fitting&rdquo; tray &mdash; drag it onto the right date
                and assign a fitter when ready.&rdquo;</em></li>
          </ul>

          <p><b>Afterwards.</b> A placed order is locked; <b>View PDF</b> and <b>Download PDF</b> appear, and the <b>Sold for</b> box stays
             open. Need a change? <b>Reopen as draft</b> in Order actions works until the factory has taken the order in; after that it says
             <code>This order is already being made &mdash; contact the factory to change it.</code></p>

          <p><b>Where to find your orders.</b> Direct orders sit in the same lists as your quotes, under <b>Retail</b> in the menu:</p>
          <ul class="steps">
            <li><b>Quotes</b> &mdash; while it is still a draft. Its status reads <b>Quote</b> with an amber <b>Draft order</b> tag (<em>&ldquo;This
                order hasn&rsquo;t been placed yet&rdquo;</em>) where an unsent quote says <b>Not sent</b>.</li>
            <li><b>Pipeline</b> &mdash; drafts are in the <b>Quote</b> column with the same <b>Draft order</b> tag; placed ones move along the
                columns like any other job.</li>
            <li><b>Orders</b> &mdash; from the moment you press <b>&#128230; Place order</b> (including one still waiting on <b>Send order to
                suppliers</b>), right through to paid.</li>
          </ul>
          <p>A login with only <b>Create orders</b> doesn&rsquo;t have the <b>Quotes</b> link in its menu; it finds drafts on the <b>Pipeline</b>, and
             placed orders under <b>Orders</b>. The <b>&larr; Order history</b> link at the top of the New order screen goes to the orders list too.</p>
          <p><b>Deleting one.</b> At the very bottom of the order screen, <b>Danger zone</b> has <b>Delete order</b>. It asks <em>&ldquo;Delete order
             HUG-2026-0012? This is permanent &mdash; all blinds go too.&rdquo;</em>, then removes the order, its blinds and its calendar
             appointments, and takes you back to the orders list. An order with payments recorded against it can&rsquo;t be deleted until those are
             removed. Logins with <b>Create orders</b> can delete direct orders. <b>Delete order</b> is only offered on a draft: once it&rsquo;s
             placed, the Danger zone says to use <b>Reopen as draft</b> first (possible until the factory starts it), and once the factory has
             started it, to contact them instead.</p>

          <div class="heads"><span class="hi">&#163;</span><div><b>New quote or New order? The money side.</b> A <b>quote</b> that runs through the
             system knows both figures &mdash; what your customer pays you and what you pay us &mdash; so the <b>Dashboard</b> can show your
             <b>profit and margin</b> on it. A <b>direct order</b> only knows <b>your cost</b> (what you pay us). So, on its own, it is <b>kept out</b>
             of your Dashboard&rsquo;s sales and profit figures, and shown instead on a tile called <b>Direct orders &mdash; no selling price</b>
             (at your cost, ex VAT). Fill in <b>Sold for</b> and it counts as a sale: in revenue at the Sold for price including VAT, and in gross
             profit as the Sold for price ex VAT minus what you pay us. Close rate always leaves direct orders out &mdash; they were never quotes.
             If you&rsquo;d rather the system worked the price out for you, build the job as a <b>New quote</b> and use <b>&#128230; Save as order</b>
             when the customer says yes. See <a href="/help/guide.php?g=dashboard-tour"><b>Reading your dashboard</b></a>.</div></div>',
        'script'  => [
            ['1', 'An order, straight to us',  'Not every job needs a quote. If you already know what you want made, you can send an order straight to us, just as you would on the old portal. There is no retail customer to add, and nothing to send out for approval. You give it your reference, add the blinds, and place it. This guide walks through it, one step at a time.', 1],
            ['2', 'The New button',            'Everything starts from the New button, at the top of the menu. What it offers depends on what your login is allowed to do. If you can create quotes and orders, New opens a small menu with two choices: New quote, and New order. If you can only create orders, the button simply says New order, and takes you straight there.', 2],
            ['3', 'Who can place orders',      'Placing orders is its own permission. On the Users page, each login has a tick called Create orders. Tick it, and that person can start an order, open it, and place it with us. They see the orders in the list, even if they cannot see every job. Admins can always do everything, so they get both choices.', 3],
            ['4', 'Your order reference',      'The New order screen is short. The first box is Order reference, and it is required. Use your own reference, the one you would quote if you rang us about it. It travels with the order all the way through. Leave it empty and press Start order, and the screen stops you: please enter your order reference.', 4],
            ['5', 'Customer name, and notes',  'The next box, Customer name, is optional. Whatever you type here is also printed on the labels, so you can tell your blinds apart when they arrive. It is only a name, so no customer record is made. Order notes, further down, is for anything else we should know, like packing two blinds together.', 5],
            ['6', 'Sold for',                  'Sold for is optional too. It is what your customer is paying you, and it is only for your own figures. We never see it. If you are VAT registered, the inc VAT tick starts on. With a price, the order counts in your Dashboard\'s sales and profit. Leave it blank, and it is kept out. Then press Start order, and a green bar says the order has started.', 6],
            ['7', 'The same order twice',      'It is easy to key the same order in twice, say once in the office, and once by the boss. So if the reference is already on one of your jobs, you are shown them first: the number, where it has got to, and the date it was started. If it really is a new order, press Start order anyway. If not, press Cancel. Change the reference, and it checks again.', 7],
            ['8', 'The order screen',          'Now you are on the order screen. It looks like the quote builder, but it is shaped for an order. The dark bar at the top says Order, with its number, and its status: draft. Under Order actions there is just one button, Place order, and it only appears once there is a blind on it. There are no PDFs until the order is placed.', 8],
            ['9', 'Adding the blinds',         'Adding blinds works exactly as it does on a quote. Choose the product, then the system and the fabric. Name the room, type the width and the drop, and pick any options. The live price shows before you save. Press Save, or Save and add another blind, and each one is added to the Blinds list.', 9],
            ['10', 'Your buying price',        'The prices here are your buying prices. Your trade discount is already taken off, and nothing is added on: no markup, and no retail discount. So the figure you see is what you pay us. The total reads Order total, your price, ex VAT. There is no VAT line on the order itself.', 10],
            ['11', 'No quoting extras',        'Because there is no customer, the quoting extras are gone. There is no W T charge, no override price, and no adjusting the price of one blind. There is no deposit, and no payments panel. There is no Send to customer, no signing, and no customer accepted or declined. Just the blinds, and the order.', 11],
            ['12', 'Order details',            'Further down is a panel called Order details. Here you can change the customer name, the order reference and the notes, and add an additional reference. Open Customer contact and fitting address to add their phone, email and address. Fill these in before you place the order, because the fitting appointment it books picks them up. Then press Save details.', 12],
            ['13', 'Place order',              'When every blind is on, press Place order. It asks first: place this order with us now? Press Yes, continue. If everything on it comes from our catalogue, it goes straight through, and the status turns to ordered, with the due date. If anything is bought in from another supplier, you are taken on to finish sending it. Either way, a fitting waits in the calendar\'s Pending Fitting tray.', 13],
            ['14', 'Once it is placed',        'Once it is placed, the order is locked, and View PDF and Download PDF appear. The Sold for box stays open, so you can add or change the price at any time. Need to change something else? Reopen as draft works until the factory has taken the order in. After that, you are told it is already being made, so contact the factory to change it.', 14],
            ['15', 'Where your orders are',    'Your orders sit in the same lists as your quotes. While it is still a draft, you will find it under Quotes, and in the Pipeline\'s Quote column, marked Draft order, so you know it has not been placed yet. Once it is placed, it moves to Orders. Started one by mistake? Delete order is in the Danger zone, at the very bottom of the order screen.', 15],
            ['16', 'Quote or order: the money side', 'One last thing: the money side. A quote knows what your customer pays you, and what you pay us, so the Dashboard can show your profit. A direct order only knows what you pay us, so on its own it is kept out of your sales and profit figures. Fill in Sold for, and it counts, at that price. Until then, it waits on a tile of its own: direct orders, no selling price.', 16],
        ],
];
