<?php
declare(strict_types=1);

/**
 * Guide: quote-build — "Building a quote" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Scope: the NEW QUOTE form (/quote-builder/new.php) and the BUILDER
 * (/quote-builder/edit.php + add_item.php) up to the point the quote is built:
 * the sticky bar, Quote actions, the Add blind form (Product / System / Band,
 * Fabric search, Room name, Measurement, Width / Drop / Quantity / Notes,
 * Adjust price for this blind, Options, the live price box, Save / Save and
 * add another blind), the Blinds list and its totals rows, the unsaved-blind
 * restore bar, and the locked-quote rule. Sending, accepting, ordering,
 * invoicing and payments each have their own guide. Every label, button and
 * message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

/** A labelled control. $kind 'sel' adds a dropdown chevron, 'dis' greys it. */
$f = static fn (string $label, string $box, string $kind = '', string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib ' . $kind . '">' . $box . '</span></div>';
$ph = static fn (string $t): string => '<span class="gph">' . $t . '</span>';
$rq = '<em class="rq">*</em>';

// The dark sticky bar at the top of the builder.
$bar = static fn (string $total = '&pound;0.00', string $cls = '', string $style = ''): string =>
    '<div class="qsb ' . $cls . '" style="' . $style . '">Quote BEV-2026-0042 <span class="pill">draft</span>'
    . '<span class="qa ok">&#10003; Customer accepted</span><span class="qa no">&#10005; Customer declined</span>'
    . '<span class="tt">Total ' . $total . '</span></div>';

// One saved blind in the Blinds list.
$row = static fn (string $cls = '', string $style = ''): string => '
  <div class="br ' . $cls . '" style="' . $style . '"><span>1</span>
    <span class="ds"><b>Living Room</b><br><b>Roller Blind</b> &mdash; Bev Roller<br>Band A &mdash; Louvolite &mdash; Sunset / Ivory
      <i class="opts">&#9656; 2 options</i></span>
    <span>1500 &times; 1600 mm</span><span class="n">1</span><span class="n">&pound;77.00</span><span class="n">&pound;77.00</span>
    <span class="ac"><i class="b">Edit</i><i class="b">Dup</i><i class="b x">&times;</i></span></div>';
$head = '<div class="br th"><span>#</span><span>Description</span><span>Size</span><span class="n">Qty</span><span class="n">Unit</span><span class="n">Total</span><span></span></div>';

$saveBtns = static fn (string $cls = 'off', string $extra = ''): string =>
    '<div class="fact"><span class="btnp ' . $cls . '" ' . $extra . '>Save</span><span class="btns ' . $cls . '">Save and add another blind</span></div>';

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Building a quote',
        'eyebrow' => 'Quotes',
        'v'       => 2,
        'blurb'   => 'Start a quote, then build it blind by blind — product, system, band, fabric, room, size, options — watching the live price, fixing the usual slips, and changing a price for one blind or the whole job.',
        'lede'    => 'This is the one you will use every day. It covers <b>both screens</b>: the short <b>New quote</b> form that
                      creates the job, and the <b>quote builder</b> where you add the blinds. You pick the <b>product</b>, let the
                      <b>system</b>, <b>band</b> and <b>fabric</b> follow on from it, name the <b>room</b>, type the <b>size</b>, and watch
                      the <b>live price</b> go green before you save. We go <b>slowly</b>, one idea per chapter, and walk straight into the
                      mistake everybody makes once. <b>Sending it, accepting it, ordering and invoicing all have guides of their own.</b>',
        'open'    => '/quote-builder/new.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:380px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .pt{ font-size:1.05rem; font-weight:800; color:var(--ink); }
          .gd .psub{ font-size:.68rem; color:var(--accent); margin:.1rem 0 .55rem; }
          .gd .hint{ font-size:.66rem; color:var(--faint); }

          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; white-space:nowrap; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; white-space:nowrap; }
          .gd .btnp.off, .gd .btns.off{ background:var(--panel); color:var(--faint); border:1px solid var(--line); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .chips{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
          .gd .bnr{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; background:var(--good-wash); border-left:3px solid var(--good);
                    border-radius:8px; padding:.4rem .6rem; font-size:.72rem; font-weight:700; color:var(--ink); margin:0 0 .5rem; }
          .gd .ebn{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.4rem .6rem; font-size:.72rem; font-weight:700; color:var(--err); margin:0 0 .5rem; }
          .gd .swap{ display:inline-grid; } .gd .swap > span{ grid-area:1/1; }
          .gd .stack{ display:grid; } .gd .stack > *{ grid-area:1/1; align-self:start; }
          .gd .fact{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.5rem; }

          /* form */
          .gd .frm{ display:flex; flex-direction:column; gap:.45rem; max-width:31rem; }
          .gd .g2{ display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
          .gd .g3{ display:grid; grid-template-columns:2fr 2fr 1fr; gap:.5rem; }
          .gd .g4{ display:grid; grid-template-columns:1fr 1fr .7fr 1.4fr; gap:.5rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.2rem; min-width:0; position:relative; }
          .gd .fl{ font-size:.64rem; font-weight:700; color:var(--soft); white-space:nowrap; }
          .gd .fl small{ font-weight:400; color:var(--faint); }
          .gd .ib{ display:flex; align-items:center; min-height:27px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.72rem; color:var(--ink); overflow:hidden; white-space:nowrap; position:relative; }
          .gd .ib.sel{ padding-right:1.3rem; }
          .gd .ib.sel::after{ content:"\25BE"; position:absolute; right:.4rem; color:var(--faint); font-size:.68rem; }
          .gd .ib.dis{ background:var(--panel); }
          .gd .gph{ color:var(--faint); }
          .gd .rq{ color:#b91c1c; font-style:normal; }
          .gd .ck{ display:inline-flex; align-items:center; gap:.35rem; font-size:.68rem; color:var(--ink); margin-right:.6rem; }
          .gd .ck > i{ width:13px; height:13px; border:1.5px solid var(--border-strong,#9aa3af); border-radius:3px; display:grid; place-items:center;
                       font-style:normal; font-size:.66rem; font-weight:900; color:var(--accent); background:var(--surface); }
          .gd .drop{ position:absolute; left:0; right:0; top:100%; z-index:5; margin-top:3px; background:var(--surface); border:1px solid var(--line);
                     border-radius:8px; box-shadow:var(--gd-shadow); font-size:.68rem; overflow:hidden; }
          .gd .drop div{ padding:.28rem .5rem; border-top:1px solid var(--line); color:var(--ink); white-space:nowrap; }
          .gd .drop div:first-child{ border-top:0; }
          .gd .drop small{ display:block; color:var(--faint); font-size:.58rem; }
          .gd .drop .hit{ background:var(--accent-wash); font-weight:700; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:.2rem 0 .1rem; display:flex; align-items:baseline; gap:.6rem; flex-wrap:wrap; }
          .gd .sech small{ margin-left:auto; font-weight:400; font-size:.62rem; color:var(--faint); }

          /* live price box */
          .gd .prv{ border-radius:8px; padding:.45rem .6rem; font-size:.72rem; border:1px solid var(--line); background:var(--panel); color:var(--faint); font-style:italic; max-width:31rem; }
          .gd .prv.ok{ background:var(--good-wash); border-color:color-mix(in srgb,var(--good) 40%,transparent); color:var(--ink); font-style:normal; }
          .gd .prv.bad{ background:var(--err-wash); border-color:color-mix(in srgb,var(--err) 40%,transparent); color:var(--err); font-style:normal; font-weight:600; }

          /* builder chrome */
          .gd .qsb{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; background:#1f2937; color:#fff; border-radius:9px; padding:.42rem .65rem; font-size:.72rem; font-weight:700; margin-bottom:.5rem; }
          .gd .qsb .pill{ background:#e5e7eb; color:#374151; border-radius:999px; padding:.05rem .5rem; font-size:.6rem; }
          .gd .qsb .qa{ border-radius:6px; padding:.12rem .45rem; font-size:.62rem; }
          .gd .qsb .qa.ok{ background:#16a34a; } .gd .qsb .qa.no{ background:#4b5563; }
          .gd .qsb .tt{ margin-left:auto; }
          .gd .qact{ border:1px solid var(--line); border-radius:9px; padding:.4rem .55rem; background:var(--surface); margin-bottom:.55rem; }
          .gd .qact b{ display:block; font-size:.7rem; color:var(--ink); margin-bottom:.3rem; }
          .gd .qact .row{ display:flex; gap:.3rem; flex-wrap:wrap; } .gd .qact .btns{ font-size:.62rem; padding:.18rem .45rem; }
          .gd .cols{ display:grid; grid-template-columns:1fr 1.15fr; gap:.7rem; align-items:start; }
          .gd .pane{ border:1px solid var(--line); border-radius:10px; padding:.5rem .6rem; background:var(--surface); }
          .gd .empty{ text-align:center; color:var(--faint); font-size:.72rem; padding:1rem .5rem; }

          /* blinds list */
          .gd .bl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.64rem; background:var(--surface); }
          .gd .br{ display:grid; grid-template-columns:1.2rem 2.6fr 1.3fr .5fr .9fr .9fr 5.2rem; gap:.3rem; align-items:start; padding:.35rem .45rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .br:first-child{ border-top:0; }
          .gd .br.th{ background:var(--panel); font-weight:700; color:var(--soft); font-size:.56rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .br .n{ text-align:right; }
          .gd .br .ds{ line-height:1.4; }
          .gd .br .opts{ display:block; font-style:normal; color:var(--accent); font-weight:600; margin-top:.1rem; }
          .gd .br .ac{ display:flex; gap:.2rem; }
          .gd .br .b{ font-style:normal; border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:.05rem .3rem; font-size:.6rem; font-weight:600; }
          .gd .br .b.x{ background:#dc2626; color:#fff; border-color:#dc2626; }
          .gd .tots{ font-size:.68rem; }
          .gd .tots div{ display:flex; justify-content:flex-end; gap:1rem; padding:.25rem .45rem; border-top:1px solid var(--line); align-items:center; flex-wrap:wrap; }
          .gd .tots div b{ min-width:4.5rem; text-align:right; }
          .gd .tots small{ color:var(--faint); font-weight:400; }
          .gd .tots div.ovrow{ display:block; text-align:right; line-height:1.5; }
          .gd .tots div.ovrow small{ display:block; }
          .gd .mini{ display:inline-flex; align-items:center; gap:.2rem; }
          .gd .mini .ib{ min-height:22px; width:4.2rem; }

          /* misc */
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .arrow{ color:var(--faint); font-weight:800; font-size:1.2rem; align-self:center; text-align:center; }
          .gd .appt{ border:1px solid var(--line); border-left:4px solid #8b5cf6; border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.7rem; color:var(--soft); }
          .gd .appt h4{ margin:0 0 .3rem; font-size:.8rem; color:var(--ink); }
          .gd .rbar{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; background:#fef9c3; border:1px solid #fde047; color:#713f12; border-radius:8px; padding:.4rem .6rem; font-size:.7rem; margin-bottom:.5rem; }
          .gd .rbar .btnp{ font-size:.66rem; padding:.2rem .55rem; } .gd .rbar .btns{ font-size:.66rem; padding:.18rem .5rem; }
          .gd .rob{ background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.4rem .6rem; font-size:.7rem; color:var(--soft); margin-bottom:.5rem; }
          .gd .ovr{ border:1px solid var(--line); border-radius:8px; padding:.4rem .55rem; font-size:.68rem; color:var(--soft); background:var(--surface); }
          .gd .ovr .sm{ color:var(--soft); }
          .gd .tip{ position:absolute; z-index:6; background:#0f172a; color:#fff; font-size:.62rem; border-radius:6px; padding:.3rem .45rem; max-width:15rem; line-height:1.4; }

          @media (max-width:640px){
            .gd .sc{ min-height:460px; }
            .gd .cols, .gd .two{ grid-template-columns:1fr; }
            .gd .g4{ grid-template-columns:1fr 1fr; }
            .gd .g3{ grid-template-columns:1fr 1fr; }
            .gd .br{ grid-template-columns:1rem 2.4fr 1.2fr .9fr 4.6rem; }
            .gd .br > :nth-child(4), .gd .br > :nth-child(5){ display:none; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote builder</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a class="on">Quotes</a><a>Orders</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $bar('&pound;92.40') . '
                  <div class="cols">
                    <div class="pane"><div class="sech">Add blind</div>
                      <div class="frm">
                        <div class="g2">' . $f('Product ' . $rq, 'Roller Blind', 'sel') . $f('System', 'Bev Roller', 'sel') . '</div>
                        <div class="g2">' . $f('Fabric ' . $rq, 'Sunset / Ivory') . $f('Room name', 'Living Room') . '</div>
                        <div class="prv ok"><b>&pound;77.00</b> per blind</div>
                        ' . $saveBtns('') . '
                      </div></div>
                    <div><div class="sech">Blinds (1)</div><div class="bl">' . $head . $row() . '</div></div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — two screens -->
                <div class="sc" data-scene="1" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Two screens</div>
                  <div style="display:grid;grid-template-columns:1fr 2rem 1.3fr;gap:.4rem">
                    <div class="card a-rise" style="--d:3s"><h4>1 &middot; New quote</h4>Creates the job, and the customer behind it.
                      <div class="frm" style="margin-top:.4rem">' . $f('Customer name ' . $rq, 'Emma Fletcher') . '<span class="btnp" style="align-self:flex-start">Create quote</span></div></div>
                    <div class="arrow a-fade" style="--d:7.8s">&rarr;</div>
                    <div class="card a-rise" style="--d:8.2s"><h4>2 &middot; The quote builder</h4>Add the blinds, one at a time.
                      <div class="prv ok a-pop" style="--d:11.5s;margin-top:.4rem"><b>&pound;77.00</b> per blind</div>
                      <div class="bl a-rise" style="--d:13s;margin-top:.4rem;font-size:.6rem"><div class="br" style="grid-template-columns:1rem 1fr auto"><span>1</span><span><b>Living Room</b> &middot; Roller Blind</span><span>&pound;77.00</span></div></div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:15.9s">+ New &mdash; top of the menu</span>
                    <span class="chip a-pop" style="--d:18.5s">+ New quote &mdash; on your Quotes list</span></div>
                </div>

                <!-- 2 — existing customer -->
                <div class="sc" data-scene="2" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">An existing customer</div>
                  <div class="pt">New quote</div><div class="psub">&larr; Order history</div>
                  <div class="frm">
                    <div class="fg"><span class="fl">Existing customer</span>
                      <span class="ib a-ring" style="--d:1s"><span class="swap"><span class="gph a-out" style="--d:4.3s">Type to search by name, town, or postcode...</span><span class="a-mid" style="--d:4.4s;--d2:9.5s"><span class="a-type" style="--d:4.4s;--ts:4;--tt:.6s">Flet</span></span><span class="a-fade" style="--d:9.6s">Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ</span></span></span>
                      <div class="drop a-mid" style="--d:6s;--d2:9.5s"><div class="hit">Emma Fletcher &mdash; Leamington Spa &mdash; CV32 5PJ</div><div>Jo Fletcher &mdash; Rugby &mdash; CV21 2LP</div></div>
                      <span class="hint">Type to filter &mdash; leave blank for a new customer.</span></div>
                    ' . $f('Customer name ' . $rq, '<span class="a-fade" style="--d:10.8s">Emma Fletcher</span>') . '
                    <div class="g3">' . $f('Email', '<span class="a-fade" style="--d:11.4s">emma.fletcher@gmail.com</span>')
                        . $f('Phone <small>(landline)</small>', '<span class="a-fade" style="--d:12s">01926 334455</span>')
                        . $f('Mobile', '<span class="a-fade" style="--d:12.6s">07700 900123</span>') . '</div>
                    <span class="ck" style="justify-content:flex-end;align-self:flex-end"><i><span class="a-pop" style="--d:13.8s">&#10003;</span></i>Mobile is on WhatsApp</span>
                    <div class="g3">' . $f('Address line 1', '<span class="a-fade" style="--d:14.6s">14 Clarendon Ave</span>')
                        . $f('Town', '<span class="a-fade" style="--d:15.2s">Leamington Spa</span>')
                        . $f('Postcode', '<span class="a-fade" style="--d:15.8s">CV32 5PJ</span>') . '</div>
                  </div>
                  <span class="chip a-pop" style="--d:19s;margin-top:.6rem">All their quotes stay on one customer</span>
                </div>

                <!-- 3 — new customer -->
                <div class="sc" data-scene="3" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">Someone new</div>
                  <div class="stack">
                    <div class="ebn a-mid" style="--d:8.9s;--d2:12.6s">Customer name is required.</div>
                    <div class="bnr a-pop" style="--d:20.5s"><span style="font-weight:400;color:var(--soft)">The builder opens:</span> Quote BEV-2026-0043 created.</div>
                  </div>
                  <div class="frm">
                    ' . $f('Existing customer', '<span class="gph">Type to search by name, town, or postcode...</span>') . '
                    ' . $f('Customer name ' . $rq, '<span class="a-type" style="--d:12.8s;--ts:10;--tt:.8s">Tom Hughes</span>', '', 'a-ring', '--d:2s') . '
                    <div class="g3">' . $f('Email', '<span class="a-type" style="--d:14.4s;--ts:25;--tt:1.2s">tom.hughes@btinternet.com</span>', '', 'a-ring', '--d:14s')
                        . $f('Phone <small>(landline)</small>', $ph('Phone (landline)'))
                        . $f('Mobile', '<span class="a-type" style="--d:16.5s;--ts:12;--tt:.8s">07700 900456</span>', '', 'a-ring', '--d:16.2s') . '</div>
                    <div class="g2">' . $f('Customer reference <small>(their order / PO)</small>', $ph('Customer reference (their order / PO)')) . $f('Quote notes', $ph('Quote notes')) . '</div>
                    <div class="fact"><span class="btnp a-press" style="--d:8.3s"><span class="a-press" style="--d:20.4s">Create quote</span></span><span class="btns">Cancel</span></div>
                  </div>
                  <span class="chip a-pop" style="--d:17.8s;margin-top:.5rem">Email + mobile = how the quote gets sent</span>
                </div>

                <!-- 4 — from the calendar -->
                <div class="sc" data-scene="4" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Measured from the calendar? Start there</div>
                  <div style="display:grid;grid-template-columns:1fr 2rem 1fr;gap:.4rem;align-items:start">
                    <div class="appt a-rise" style="--d:1.5s"><h4>Measure &mdash; Emma Fletcher</h4>Tue 7 Oct &middot; 10:00
                      <div style="margin:.35rem 0">&#128205; <b class="a-ring" style="--d:11.5s">14 Clarendon Ave, Leamington Spa</b></div>
                      <div class="fact"><span class="btnp a-press a-ring" style="--d:6.5s">Start quote</span><span class="btns">Edit</span></div></div>
                    <div class="arrow a-fade" style="--d:8s">&rarr;</div>
                    <div class="a-rise" style="--d:17s">' . $bar() . '<div class="pane"><div class="sech">Add blind</div>' . $f('Product ' . $rq, $ph('Choose product...'), 'sel') . '</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:9.3s">Customer carried across</span>
                    <span class="chip a-pop" style="--d:12.5s">Uses the installation address</span>
                    <span class="chip a-pop" style="--d:19.5s">New quote form skipped</span></div>
                </div>

                <!-- 5 — the builder -->
                <div class="sc" data-scene="5" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">The builder, at a glance</div>
                  <div class="a-ring" style="--d:1.7s;border-radius:9px">' . $bar() . '</div>
                  <div class="qact a-rise" style="--d:5s"><b>Quote actions</b><div class="row"><span class="btns">View PDF</span><span class="btns">Download PDF</span><span class="btns">Mark as sent</span><span class="btns">Mark as accepted</span><span class="btns">Mark as declined</span></div></div>
                  <div class="cols">
                    <div class="pane a-rise" style="--d:7.6s"><div class="sech">Add blind <small>Customer details &amp; references below &darr;</small></div>
                      ' . $f('Product ' . $rq, $ph('Choose product...'), 'sel') . '</div>
                    <div class="pane a-rise" style="--d:9.5s"><div class="sech">Blinds (0)</div><div class="empty">No blinds yet</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:13.2s">Save details</span><span class="chip a-pop" style="--d:13.8s">Save</span>
                    <span class="chip a-pop" style="--d:14.4s">Set</span><span class="chip a-pop" style="--d:15s">Save deposit</span>
                    <span class="chip a-pop" style="--d:15.8s;border-color:var(--good);color:var(--good)">Each part saves itself</span></div>
                </div>

                <!-- 6 — product first -->
                <div class="sc" data-scene="6" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Always the Product first</div>
                  <div class="pane"><div class="sech">Add blind</div>
                    <div class="frm">
                      <div class="g3">
                        <div class="fg"><span class="fl">Product ' . $rq . '</span><span class="ib sel a-ring" style="--d:0.5s"><span class="swap"><span class="gph a-out" style="--d:6.4s">Choose product...</span><span class="a-fade" style="--d:6.4s">Roller Blind</span></span></span></div>
                        <div class="fg"><span class="fl">System</span><span class="ib sel"><span class="swap"><span class="gph a-out" style="--d:9.2s">Choose product first</span><span class="a-fade" style="--d:9.2s">Bev Roller</span></span></span></div>
                        <div class="fg"><span class="fl">Band</span><span class="ib sel"><span class="a-fade" style="--d:13s">All bands</span></span></div>
                      </div>
                      <div class="g3">
                        <div class="fg"><span class="fl">Fabric ' . $rq . '</span><span class="ib"><span class="swap"><span class="gph a-out" style="--d:15.6s">Choose product first</span><span class="gph a-fade" style="--d:15.6s">Type to search fabrics (or click for recent)</span></span></span></div>
                        ' . $f('Room name', $ph('Type or pick &mdash; e.g. Living Room')) . $f('Measurement', 'mm', 'sel') . '
                      </div>
                    </div></div>
                  <div class="chips"><span class="chip a-pop" style="--d:2.6s">Greyed out until a product is picked</span>
                    <span class="chip a-pop" style="--d:18.4s">Some products: Fabric &rarr; <b>Slat</b>, Band &rarr; <b>Tape / String</b></span></div>
                </div>

                <!-- 7 — fabric search -->
                <div class="sc" data-scene="7" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Finding the fabric</div>
                  <div class="frm">
                    <div class="g2">
                      <div class="fg"><span class="fl">Fabric ' . $rq . '</span>
                        <span class="ib a-ring" style="--d:1s"><span class="swap"><span class="gph a-out" style="--d:2.5s">Type to search fabrics (or click for recent)</span><span class="a-mid" style="--d:2.6s;--d2:14.9s"><span class="a-type" style="--d:2.6s;--ts:3;--tt:.5s">sun</span></span><span class="a-fade" style="--d:15s">Sunset / Ivory</span></span></span>
                        <div class="drop a-mid" style="--d:5s;--d2:14.8s"><div class="hit">Sunset / Ivory<small>Louvolite &middot; Code SW-104</small></div><div>Sunset / Charcoal<small>Louvolite &middot; Code SW-109</small></div><div>Sunbury / White<small>Decora &middot; Code SB-01</small></div></div></div>
                      ' . $f('Room name', $ph('Type or pick &mdash; e.g. Living Room')) . '
                    </div>
                  </div>
                  <div class="chips" style="margin-top:7.4rem">
                    <span class="chip a-pop" style="--d:7.2s">Name / colour, then supplier &middot; code</span>
                    <span class="chip a-pop" style="--d:16.5s">Nothing matching? &ldquo;No matching fabrics.&rdquo;</span>
                    <span class="chip a-pop" style="--d:19.8s">Click without typing = recent ones</span></div>
                </div>

                <!-- 8 — room name -->
                <div class="sc" data-scene="8" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Name the room</div>
                  <div class="frm">
                    <div class="g2">' . $f('Fabric ' . $rq, 'Sunset / Ivory') . '
                      <div class="fg"><span class="fl">Room name</span>
                        <span class="ib a-ring" style="--d:1.6s"><span class="swap"><span class="gph a-out" style="--d:9.3s">Type or pick &mdash; e.g. Living Room</span><span class="a-fade" style="--d:9.4s">Living Room</span></span><span style="position:absolute;right:.45rem;color:var(--faint);font-size:.68rem">&#9662;</span></span>
                        <div class="drop a-mid" style="--d:3.5s;--d2:9.2s"><div>Kitchen / Diner</div><div>Landing</div><div class="hit">Living Room</div><div>Lounge</div><div>Master Bedroom</div></div></div>
                    </div>
                  </div>
                  <div class="chips" style="margin-top:7.6rem">
                    <span class="chip a-pop" style="--d:9.5s">Pick one, or type your own</span>
                    <span class="chip a-pop" style="--d:14.3s">&#128295; The fitter and the workshop read it on the ticket</span>
                    <span class="chip bad a-pop" style="--d:20.3s">&ldquo;Blind 3&rdquo; helps nobody</span></div>
                </div>

                <!-- 9 — sizes -->
                <div class="sc" data-scene="9" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">The sizes</div>
                  <div class="frm">
                    <div class="g3">' . $f('Fabric ' . $rq, 'Sunset / Ivory') . $f('Room name', 'Living Room') . $f('Measurement', 'mm', 'sel', 'a-ring', '--d:1.3s') . '</div>
                    <div class="g4">
                      ' . $f('Width (mm) ' . $rq, '<span class="a-type" style="--d:8.4s;--ts:5;--tt:.6s">150cm</span>', '', 'a-ring', '--d:7.4s') . '
                      ' . $f('Drop (mm) ' . $rq, '<span class="a-type" style="--d:12s;--ts:4;--tt:.5s">1600</span>') . '
                      <div class="fg"><span class="fl">Quantity</span><span class="ib a-ring" style="--d:14s">1</span></div>
                      ' . $f('Notes', '<span class="swap"><span class="gph a-out" style="--d:17.8s">Optional internal note</span><span class="a-type" style="--d:17.9s;--ts:17;--tt:1s">Fit inside recess</span></span>', '', 'a-ring', '--d:16.9s') . '
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4s">Unit is for the whole quote</span>
                    <span class="chip a-pop" style="--d:11s">150cm &rarr; read as 1500 mm</span>
                    <span class="chip a-pop" style="--d:15s">Per-slat products: &ldquo;Number of slats&rdquo;</span>
                    <span class="chip a-pop" style="--d:21.4s">&#128274; Notes never show on the customer&rsquo;s quote</span></div>
                </div>

                <!-- 10 — live price -->
                <div class="sc" data-scene="10" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">The live price does the checking</div>
                  <div class="frm">
                    <div class="g4">' . $f('Width (mm) ' . $rq, '1500') . $f('Drop (mm) ' . $rq, '<span class="a-type" style="--d:10.6s;--ts:4;--tt:.5s">1600</span>') . $f('Quantity', '1') . $f('Notes', $ph('Optional internal note')) . '</div>
                    <div class="stack">
                      <div class="prv a-out" style="--d:11s">Still need: drop.</div>
                      <div class="prv ok a-pop" style="--d:11.1s"><b>&pound;77.00</b> per blind &middot; base &pound;40.00 &middot; + extras &pound;5.00</div>
                    </div>
                    <div class="stack">
                      <div class="a-out" style="--d:15.5s">' . $saveBtns('off') . '</div>
                      <div class="a-fade" style="--d:15.6s">' . $saveBtns('') . '</div>
                    </div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5.4s">Grey = still waiting</span>
                    <span class="chip a-pop" style="--d:11.4s;border-color:var(--good);color:var(--good)">Green = priced</span>
                    <span class="chip a-pop" style="--d:18s">No price, no save</span></div>
                </div>

                <!-- 11 — the slip -->
                <div class="sc" data-scene="11" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">The slip everybody makes once</div>
                  <div class="frm">
                    <div class="g3">' . $f('Product ' . $rq, 'Roller Blind', 'sel')
                      . '<div class="fg"><span class="fl">System</span><span class="ib sel a-ring" style="--d:18.8s"><span class="swap"><span class="a-out" style="--d:20s">Grip Fit</span><span class="a-fade" style="--d:20s">Bev Roller</span></span></span></div>'
                      . $f('Band', 'Band A', 'sel', 'a-ring', '--d:8.5s') . '</div>
                    <div class="stack">
                      <div class="prv bad a-out" style="--d:20.1s"><span class="a-fade" style="--d:2.8s">No price table for Roller Blind band A on system &lsquo;Grip Fit&rsquo;.</span></div>
                      <div class="prv a-mid" style="--d:20.2s;--d2:22.3s">Still need: fabric.</div>
                      <div class="prv ok a-pop" style="--d:22.4s"><b>&pound;77.00</b> per blind</div>
                    </div>
                    <div class="stack"><div class="a-out" style="--d:22.5s">' . $saveBtns('off') . '</div><div class="a-fade" style="--d:22.6s">' . $saveBtns('') . '</div></div>
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:9.2s">Band only narrows the fabric list</span>
                    <span class="chip a-pop" style="--d:13.8s">Price = product + system + band</span></div>
                </div>

                <!-- 12 — past the end -->
                <div class="sc" data-scene="12" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">&ldquo;Exceeds the largest cell&rdquo;</div>
                  <div class="frm">
                    <div class="g4">' . $f('Width (mm) ' . $rq, '2400', '', 'a-ring', '--d:5.1s') . $f('Drop (mm) ' . $rq, '3000') . $f('Quantity', '1') . $f('Notes', $ph('Optional internal note')) . '</div>
                    <div class="prv bad a-pop" style="--d:1.3s">Size 2400 &times; 3000 mm exceeds the largest cell in this price table.</div>
                  </div>
                  <div class="two" style="margin-top:.7rem">
                    <div class="card a-rise" style="--d:8.7s"><h4>Check the unit first</h4><b>150</b> typed in a millimetre quote is a <b>15 cm</b> blind.</div>
                    <div class="card a-rise" style="--d:14.8s"><h4>Between two sizes? Fine</h4>
                      <span class="chip">1450</span> <span class="arrow" style="font-size:.8rem">&rarr;</span> <span class="chip a-sel" style="--d:18.2s">1600</span>
                      <div style="margin-top:.3rem">rounds up to the next one</div></div>
                  </div>
                  <span class="chip bad a-pop" style="--d:20.9s;margin-top:.6rem">Only a size past the end stops it</span>
                </div>

                <!-- 13 — options -->
                <div class="sc" data-scene="13" data-len="26">
                  <div class="sct a-fade" style="--d:.2s">Options &mdash; different on every product</div>
                  <div class="frm">
                    <div class="g2 a-rise" style="--d:19.5s">' . $f('Fascia Options', 'LL Cassette', 'sel') . $f('Fascia width (mm)', '1550') . '</div>
                    <div class="g4">' . $f('Width (mm) ' . $rq, '1500') . $f('Drop (mm) ' . $rq, '1600') . $f('Quantity', '1') . $f('Notes', $ph('Optional internal note')) . '</div>
                    <div class="sech a-fade" style="--d:1.3s">Options</div>
                    <div class="g2">
                      ' . $f('Control Side', 'Left', 'sel', 'a-rise', '--d:6.6s') . '
                      ' . $f('Bottom Weight', 'Chained', 'sel', 'a-rise', '--d:7s') . '
                    </div>
                    <div class="a-rise" style="--d:8.6s"><span class="ck"><i><span class="a-pop" style="--d:9.6s">&#10003;</span></i>Child safety cleat</span><span class="ck"><i><span class="a-pop" style="--d:10.2s">&#10003;</span></i>Side channels</span></div>
                    ' . $f('Fit height', '<span class="a-type" style="--d:12.3s;--ts:4;--tt:.4s">2100</span>', '', 'a-rise', '--d:11.5s;max-width:12rem') . '
                  </div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3.5s">Every product has its own</span>
                    <span class="chip a-pop" style="--d:14.8s">Some appear after fabric or system</span>
                    <span class="chip a-pop" style="--d:21s">&#8593; Fascia sits above the size</span></div>
                </div>

                <!-- 14 — save + the list -->
                <div class="sc" data-scene="14" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Save, and the blinds list</div>
                  <div class="bnr a-pop" style="--d:2.2s">Blind 1 added (&pound;77.00).</div>
                  <div class="fact" style="margin:0 0 .5rem"><span class="btnp a-press" style="--d:0.5s">Save</span><span class="btns a-ring" style="--d:12.3s">Save and add another blind</span></div>
                  <div class="sech">Blinds (1)</div>
                  <div class="bl" style="position:relative">' . $head . '<div class="a-drop" style="--d:2.7s">' . $row() . '</div></div>
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4.5s">Room in bold</span><span class="chip a-pop" style="--d:6.5s">Product, then fabric</span>
                    <span class="chip a-pop" style="--d:9s">&#9656; options folded underneath</span>
                    <span class="chip a-pop" style="--d:16.1s">Edit</span><span class="chip a-pop a-ring" style="--d:17.5s">Dup &mdash; copy, then change the size</span>
                    <span class="chip a-pop" style="--d:22.2s">&times; &mdash; &ldquo;Remove this blind?&rdquo;</span></div>
                </div>

                <!-- 15 — one blind, or the whole job -->
                <div class="sc" data-scene="15" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Changing a price: one blind, or the whole job</div>
                  <div class="two">
                    <div class="ovr a-rise" style="--d:2.8s"><b>&#9662; Adjust price for this blind</b>
                      <div class="g2" style="margin-top:.4rem">' . $f('Discount % (this blind)', '<span class="swap"><span class="gph a-out" style="--d:6.5s">product default</span><span class="a-type" style="--d:6.6s;--ts:2;--tt:.3s">15</span></span>') . $f('Markup % (this blind)', $ph('product default')) . '</div>
                      <div class="sm" style="margin-top:.3rem">Leave blank to use the product&rsquo;s set markup / discount. This only changes <b>this blind</b> on this quote.</div></div>
                    <div class="bl tots a-rise" style="--d:12.4s">
                      <div class="a-fade" style="--d:19.8s">Discount <small>(to agreed price)</small> <b>&minus;&pound;27.00</b></div>
                      <div>Subtotal <b><span class="swap"><span class="a-out" style="--d:18.6s">&pound;977.00</span><span class="a-fade" style="--d:18.6s">&pound;950.00</span></span></b></div>
                      <div>VAT (20.00%) <b><span class="swap"><span class="a-out" style="--d:18.6s">&pound;195.40</span><span class="a-fade" style="--d:18.6s">&pound;190.00</span></span></b></div>
                      <div>Total <b><span class="swap"><span class="a-out" style="--d:18.6s">&pound;1,172.40</span><span class="a-fade" style="--d:18.6s">&pound;1,140.00</span></span></b></div>
                      <div class="ovrow">Override price <small>(agreed price ex VAT &mdash; VAT added on top; blank to clear)</small>
                        <span class="mini">&pound;<span class="ib a-ring" style="--d:15s"><span class="a-type" style="--d:16s;--ts:3;--tt:.4s">950</span></span><span class="btns a-press" style="--d:18s">Set</span></span></div>
                    </div>
                  </div>
                </div>

                <!-- 16 — safety nets -->
                <div class="sc" data-scene="16" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Two safety nets</div>
                  <div class="rbar a-drop" style="--d:3s"><span>You have an unsaved blind from 10:42 (Living Room &mdash; Roller Blind &mdash; 1500 &times; 1600 mm).</span>
                    <span class="btnp a-ring" style="--d:6.5s">Put it back</span><span class="btns">Discard</span></div>
                  <span class="chip a-pop" style="--d:8.8s;border-color:var(--good);color:var(--good)">Nothing lost</span>
                  <div style="margin-top:.8rem">
                    <div class="qsb a-rise" style="--d:10s">Quote BEV-2026-0042 <span class="pill">sent</span><span class="tt">Total &pound;1,140.00</span></div>
                    <div class="rob a-rise" style="--d:12.5s">This quote is in <b>sent</b> state and is read-only. Use <b>Reopen as draft</b> above to edit it.</div>
                    <div class="qact a-rise" style="--d:16.5s"><b>Quote actions</b><div class="row"><span class="btns">View PDF</span><span class="btns">Mark as accepted</span><span class="btns a-ring" style="--d:18s">Reopen as draft</span></div></div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p>There are <b>two screens</b>. The short <b>New quote</b> form creates the job and the customer behind it. The <b>builder</b> is where you
             spend your time &mdash; one blind at a time, with the price worked out as you go. Start from <b>+ New</b> at the top of the menu, or
             <b>+ New quote</b> on your Quotes list.</p>

          <ul class="steps">
            <li><b>An existing customer.</b> The first box is <b>Existing customer</b> &mdash; type into it (<em>&ldquo;Type to search by name, town, or
                postcode...&rdquo;</em>, hint <em>&ldquo;Type to filter &mdash; leave blank for a new customer.&rdquo;</em>). The list shows
                <em>Name &mdash; Town &mdash; Postcode</em> so you can tell two Fletchers apart. Pick one and their details <b>copy down the form</b>: name,
                email, landline, mobile, the <b>Mobile is on WhatsApp</b> tick, address, town, county and postcode.</li>
            <li><b>Someone new.</b> Leave the search box empty and type their name into <b>Customer name *</b> &mdash; the only <b>required</b> field
                (<em>&ldquo;Customer name is required.&rdquo;</em>). Fill in what you know: <b>Email</b>, <b>Phone (landline)</b>, <b>Mobile</b>, the WhatsApp
                tick, <b>Address line 1</b> and <b>2</b>, <b>Town</b>, <b>County</b>, <b>Postcode</b>, <b>Customer reference (their order / PO)</b> and
                <b>Quote notes</b>. If <b>Find by postcode</b> is switched on for your company you get a postcode box that fills the address in. The email
                and mobile are what the quote is sent with. Then <b>Create quote</b> &rarr; <em>&ldquo;Quote BEV-2026-0043 created.&rdquo;</em> A new name
                is also added to <b>Customers</b> for you.</li>
            <li><b>From the calendar.</b> On a measure appointment, <b>Start quote</b> carries the customer across and prefers the appointment&rsquo;s
                <b>installation address</b>. When the appointment already has a customer and a name, the New quote form is skipped and you land in the
                builder. If something is missing you see <em>&ldquo;Could not start the quote automatically &mdash; please check the details below and click
                Create quote.&rdquo;</em></li>
          </ul>

          <p><b>In the builder.</b> A dark bar at the top shows <b>Quote BEV-2026-0042</b>, a status pill, the one-tap <b>&#10003; Customer accepted</b> /
             <b>&#10005; Customer declined</b> buttons and the running <b>Total</b>. Below it, <b>Quote actions</b>: <b>View PDF</b>, <b>Download PDF</b>, the
             status buttons (<b>Mark as sent</b>, <b>Mark as accepted</b>, <b>Mark as declined</b> on a draft) and, once there is a blind on the quote,
             <b>&#128230; Save as order</b>. The <b>Add blind</b> form is on the left, with <em>&ldquo;Customer details &amp; references below &darr;&rdquo;</em>
             pointing to the customer block underneath it; the <b>Blinds</b> list is on the right. <b>There is no big &ldquo;Save quote&rdquo; button</b>
             &mdash; every panel saves itself: <b>Save details</b> (customer), <b>Save</b> (the blind), <b>Set</b> (override price), <b>Save deposit</b>.</p>

          <ul class="steps">
            <li><b>Product first &mdash; always.</b> Until you choose one, <b>System</b> reads <em>Choose product first</em>, <b>Band</b> sits on
                <em>All bands</em>, and <b>Fabric</b> will not let you type. Pick the product and all three wake up: System pre-picks the default, Band
                narrows to that system&rsquo;s bands, and Fabric becomes a live search. <b>The labels rename themselves per product</b> &mdash; Fabric may read
                <b>Slat</b> or <b>Colour</b>, Band may read <b>Tape / String</b>.</li>
            <li><b>Find the fabric.</b> Click the box (<em>&ldquo;Type to search fabrics (or click for recent)&rdquo;</em>) and type a name, colour or code.
                Each row shows the <b>name / colour</b> with the <b>supplier &middot; code</b> underneath. Nothing matching: <em>&ldquo;No matching
                fabrics.&rdquo;</em>; a dropped connection: <em>&ldquo;Could not search fabrics.&rdquo;</em> Changing the <b>System</b> afterwards clears the
                fabric, so you re-pick one that is valid for it.</li>
            <li><b>Name the room.</b> Click the box or its <b>&#9662;</b> and a list of rooms opens (Living Room, Master Bedroom, Kitchen / Diner, En-suite
                and more), filtering as you type &mdash; or type your own. It is the label the fitter and the workshop read, so <b>always fill it in</b>.</li>
            <li><b>Sizes.</b> <b>Measurement</b> (mm, cm, m, inch) is set <b>per quote</b>: change it and every size is re-displayed; the stored
                measurements never change. You can type <code>150cm</code>, <code>1.5m</code> or <code>60in</code> straight into <b>Width</b>; something it
                cannot read comes back as <em>&ldquo;Could not read width &quot;abc&quot;.&rdquo;</em> <b>Quantity</b> is how many identical blinds (on a
                per-slat product it reads <b>Number of slats</b>). <b>Notes</b> (<em>Optional internal note</em>) is for your own paperwork &mdash; it never
                appears on the customer&rsquo;s quote.</li>
          </ul>

          <p><b>The live price box does the checking.</b> Grey means waiting &mdash; <em>&ldquo;Still need: product, fabric, width, drop.&rdquo;</em> Red
             means it could not price it. Green means good: <em>&ldquo;&pound;77.00 per blind&rdquo;</em>, or <em>&ldquo;&pound;154.00 for 2 blinds &middot;
             &pound;77.00 each&rdquo;</em>. If you are allowed to see costs it adds the base, the extras, the markup (or margin) and the discount.
             <b>Both save buttons stay greyed out until the box is green</b> &mdash; a blind with no price cannot go on a quote.</p>

          <div class="oops"><b>&ldquo;No price table for Roller Blind band A on system &lsquo;Grip Fit&rsquo;.&rdquo;</b> The slip everybody makes once. The
             <b>Band</b> box only <b>filters the fabric list</b> &mdash; it does not promise a price. The price lives on the <b>combination</b> of product +
             system + band. <b>Fix:</b> change the <b>System</b> to one priced for that band (or pick a band that has a price list), then re-pick the
             fabric. Its cousin <em>&ldquo;No price table set up for Roller Blind for system &lsquo;Grip Fit&rsquo;.&rdquo;</em> means that system has no
             prices at all yet.</div>

          <div class="oops"><b>&ldquo;Size 2400 &times; 3000 mm exceeds the largest cell in this price table.&rdquo;</b> The size is past the end of the grid.
             <b>Check the unit first</b> &mdash; 150 typed in a millimetre quote is a 15&nbsp;cm blind. You will <b>never</b> be told your exact size is not
             in the grid: the builder <b>rounds up to the next cell</b>, so a size between two rows prices at the larger one.</div>

          <p><b>Options.</b> Once the product loads, a section headed <b>Options</b> appears &mdash; different on every product. Some are a
             <b>dropdown</b> (with <em>&ldquo;&mdash; Select &mdash;&rdquo;</em> only when nothing is the default), some a <b>list of tick-boxes</b> (more than one
             allowed), some just a <b>number to type</b> under a small caption (<em>Fit height</em>). Some appear only <b>after</b> you pick the fabric or
             the system; before a fabric is picked you may see <em>&ldquo;Pick a fabric above to see its options.&rdquo;</em> Options flagged to show above the
             size &mdash; the roller <b>Fascia Options</b> and <b>Fascia Sizing</b> &mdash; sit between the top row and <b>Width</b>. The option grid never
             shows a price; the money shows in the live price line and on the saved blind.</p>

          <p><b>Save</b>, or <b>Save and add another blind</b> to keep the form going for the next window. You get <em>&ldquo;Blind 1 added
             (&pound;77.00).&rdquo;</em> Each blind lands in <b>Blinds (N)</b> &mdash; columns <b>#</b>, <b>Description</b>, <b>Size</b>, <b>Qty</b>,
             <b>Unit</b>, <b>Total</b>: the <b>room</b> in bold, the product and system, <em>Band A &mdash; Louvolite &mdash; Sunset / Ivory</em>, the options
             folded under <b>&#9656; 2 options</b> (<b>Show all options</b> opens them all), and your note in italics. Then <b>Edit</b>, <b>Dup</b>
             (<em>&ldquo;Duplicate this blind &mdash; copies fabric, system, options. New row opens in edit mode for you to tweak the size.&rdquo;</em>) and
             <b>&times;</b> (<em>&ldquo;Remove this blind?&rdquo;</em>). Before you add anything it says <em>No blinds yet</em>.</p>

          <p><b>Changing a price.</b> <b>Adjust price for this blind</b> (a folded panel, shown to admins and people allowed to see costs) holds
             <b>Discount % (this blind)</b> and <b>Markup % (this blind)</b> &mdash; <b>Margin % (this blind)</b> on a margin company &mdash; both showing
             <em>product default</em>: <em>&ldquo;Leave blank to use the product&rsquo;s set markup / discount. This only changes this blind on this
             quote.&rdquo;</em> For the whole job, the <b>Override price</b> row under the totals (<em>&ldquo;agreed price ex VAT &mdash; VAT added on top;
             blank to clear&rdquo;</em>) takes the figure you shook hands on &rarr; <b>Set</b>. The gap appears as <b>Discount (to agreed price)</b> (or
             <b>Price adjustment (to agreed price)</b> if it went up), so the sums still add up. The totals read <b>Subtotal</b>, <b>VAT (20.00%)</b>,
             <b>Total</b>, then a faint <b>Deposit due on acceptance</b>. If the WT charge is switched on for your company there is also a purple
             <b>WT (internal &mdash; never shown to the customer)</b> row with its own <b>Set</b>.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>You can&rsquo;t lose a half-typed blind.</b> The blind form is kept on the device as
             you fill it in. If the page reloads or the tablet dies, the next time you open the quote a yellow bar offers <em>&ldquo;You have an unsaved blind
             from 10:42 (&hellip;).&rdquo;</em> with <b>Put it back</b> and <b>Discard</b>. And if the server turns a blind down when you save it, what you
             typed stays on screen with the reason in red. <b>No signal?</b> See the guide <b>&ldquo;Working offline on a tablet or phone&rdquo;</b>.</div></div>

          <div class="oops"><b>&ldquo;Quote is locked (status: sent). Reopen it to add blinds.&rdquo;</b> Only a <b>draft</b> can be changed. The banner says
             <em>&ldquo;This quote is in sent state and is read-only. Use Reopen as draft above to edit it.&rdquo;</em> Click <b>Reopen as draft</b> in Quote
             actions, make your change, then send it again. (Once the factory has taken an order in, it can no longer be reopened from here.)</div>

          <p><b>What happens next.</b> Getting it to the customer and getting a yes is <b>Sending &amp; accepting</b>. Turning the yes into an order and a
             bill is <b>Ordering &amp; invoicing</b>. Taking the money is <b>Payments</b>.</p>',
        'script'  => [
            ['1', 'Two screens',                     'Building a quote happens on two screens. First, a short New quote form, which creates the job and its customer. Then the quote builder, where you add the blinds, one at a time, with the price worked out as you go. To start, click plus New at the top of the menu, or plus New quote, on your Quotes list.', 1],
            ['2', 'An existing customer',            'The first box on the New quote form is Existing customer. Type two or three letters of their name, town or postcode, and pick them from the list. Their details copy down the form for you: name, email, phone, mobile, the WhatsApp tick, and the whole address. That saves typing, and it keeps all their quotes on one customer.', 2],
            ['3', 'Someone new',                     'For someone new, leave that box empty, and type their name in Customer name. That is the only box you must fill in. Leave it out, and you are told: customer name is required. Add whatever else you know, especially the email and the mobile, because that is how the quote gets sent. Then click Create quote.', 3],
            ['4', 'Starting from the calendar',      'If you measured from a calendar appointment, start the quote from there instead. Open the appointment, and click Start quote. It carries the customer across, and uses the installation address, which is where the blinds are going. When the appointment already has a customer, it skips the form, and drops you straight into the builder.', 4],
            ['5', 'The builder, at a glance',        'This is the builder. The dark bar at the top shows the quote number, its status, and the running total. The Add blind form is on the left, and your blinds list is on the right. There is no big save quote button. Each part saves itself, with its own button. So if you clicked the button beside it, it is saved.', 5],
            ['6', 'Always the Product first',        'Always choose the Product first. Until you do, System, Band and Fabric are greyed out. Pick the product, and they wake up. System fills in, with the usual one already chosen. Band narrows to that system\'s bands. And the Fabric box lets you search. On some products the labels change, to Slat or Colour, but they are the same boxes.', 6],
            ['7', 'Finding the fabric',              'Click the fabric box, and type part of a name, a colour, or a code. A panel drops down. Each row shows the name and colour, with the supplier and code underneath, so you can be sure it is the right one. Click it. If nothing matches, it says so: no matching fabrics. Click the box without typing, to see the ones used recently.', 7],
            ['8', 'Name the room',                   'Next, the Room name. Click the box, or the little arrow, and a list of rooms opens, like Living Room, Kitchen, or Landing. Pick one, or type your own. The room is only a label, but it matters. It is what the fitter and the workshop read on the ticket, so always fill it in. Blind three helps nobody, on a landing with four windows.', 8],
            ['9', 'The sizes',                       'Now the sizes. Measurement sets the unit for the whole quote, and you can change it at any time. You can also type a unit on the end, like one hundred and fifty c m, and it is read for you. Quantity is how many identical blinds. And Notes is for you: an internal note for your own paperwork. It never shows on the customer\'s quote.', 9],
            ['10', 'The live price',                 'Under the form is the live price box, and it does the checking for you. Grey means it is still waiting, and it tells you what for. Still need: drop. Green means it has a price. Seventy seven pounds per blind. Now look at the two save buttons. They stay greyed out until the box is green, because a blind with no price cannot go on a quote.', 10],
            ['11', 'The slip everybody makes once',  'Here is the slip everyone makes once. The box goes red: no price table for Roller Blind band A, on system Grip Fit. The band box only narrows the fabric list. It never promises a price. The price belongs to the product, the system, and the band, together. So change the system to one that is priced for that band, and pick the fabric again.', 11],
            ['12', 'Exceeds the largest cell',       'You may also see: size exceeds the largest cell in this price table. Before you blame the price list, check the unit. One hundred and fifty, typed in a millimetre quote, is a fifteen centimetre blind. A size between two rows of the table is fine. It simply rounds up to the next one. Only a size past the end of the table stops it.', 12],
            ['13', 'Options',                        'Then the options. They look different on every product, because each product has its own. Some are a dropdown, some are tick boxes where you can pick more than one, and some are just a number to type. Some only appear once you have picked the fabric or the system. And a few, like the roller fascia, sit above the size, because you need them first.', 13],
            ['14', 'Save, and the blinds list',      'When the price is green, click Save. The blind lands in the list on the right, with the room in bold, then the product, the fabric, and its options folded underneath. Or click Save and add another blind, to keep going. Beside each blind are Edit, and Dup, which copies it, so you only change the size. The cross removes it, after asking.', 14],
            ['15', 'One blind, or the whole job',    'There are two ways to change a price. Adjust price for this blind changes one line only, with its own discount and markup. Leave them blank to use the product\'s rates. For the whole job, the Override price row, under the totals, takes the price you agreed, before VAT. The difference shows as a discount, so the sums still add up.', 15],
            ['16', 'Two safety nets',                'Two safety nets. If the page reloads, or the tablet dies, halfway through a blind, a yellow bar offers to put it back. Nothing is lost. And once a quote has been sent, it is locked, so it cannot change behind the customer\'s back. To change it, use Reopen as draft, make your change, and then send it again.', 16],
        ],
];
