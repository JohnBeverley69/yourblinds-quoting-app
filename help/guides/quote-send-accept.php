<?php
declare(strict_types=1);

/**
 * Guide: quote-send-accept — "Sending & accepting" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Covers BOTH sides of getting a quote out and getting a yes back:
 *   - the "Send to customer" panel on /quote-builder/edit.php (Recipient email,
 *     Message (optional), 📧 Email PDF + accept link, 💬 Send via WhatsApp,
 *     🔗 Copy public link, the "Public link:" line) and its flashes,
 *   - the plain-text email from /pdf-generator/email_pdf.php,
 *   - the public page /quote-history/public.php — header, Quote for, items,
 *     totals, Notes, deposit card, "How to pay — bank transfer", the accept
 *     card (Your full name, the terms tick, Accept quote / Decline) and the
 *     expired / accepted / declined / in-progress states,
 *   - what /quote-history/accept.php sets off (signature, fitting, thank-you
 *     email, new-order alert, auto-place to Ordered),
 *   - and the 30-day acceptance window (_partials/quote_expiry.php) with the
 *     builder's "Renew for 30 days".
 * Every label, button and message is copied from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with its
 * own animation timeline (a-* classes, start times in --d seconds, stretched
 * to the recorded line's length via data-len).
 */

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$f = static fn (string $label, string $box, string $cls = '', string $style = ''): string =>
    '<div class="fg ' . $cls . '" style="' . $style . '"><span class="fl">' . $label . '</span><span class="ib">' . $box . '</span></div>';

/** The Send to customer panel. $parts overrides pieces. */
$send = static function (array $p = []): string {
    $d = $p + [
        'to' => 'emma.fletcher@gmail.com', 'msg' => '<span class="gph">Optional &mdash; anything to add above the standard text.</span>',
        'red' => '', 'wa' => '<span class="wa">&#128172; Send via WhatsApp</span>', 'copy' => '<span class="btns">&#128279; Copy public link</span>',
        'cls' => '', 'style' => '', 'toCls' => '', 'toStyle' => '', 'msgCls' => '', 'msgStyle' => '',
    ];
    return '<div class="pane ' . $d['cls'] . '" style="' . $d['style'] . '"><div class="sech">Send to customer</div>
      <div class="fg ' . $d['toCls'] . '" style="' . $d['toStyle'] . '"><span class="fl">Recipient email</span><span class="ib">' . $d['to'] . '</span></div>
      <div class="fg ' . $d['msgCls'] . '" style="margin-top:.4rem;' . $d['msgStyle'] . '"><span class="fl">Message (optional)</span><span class="ib">' . $d['msg'] . '</span></div>
      <div class="sendrow"><span class="red ' . $d['red'] . '">&#128231; Email PDF + accept link</span>' . $d['wa'] . $d['copy'] . '</div>
      <div class="pl">Public link: <code>https://yourblinds.uk/quote-history/public.php?token=8f3c&hellip;</code></div></div>';
};

// The customer's public page, top part.
$pubHead = '
  <div class="pub-h"><div><div class="logo2">B</div><b>Beverley Blinds</b><br><small>12 Mill Lane, Warwick CV34 4AB &middot; 01926 400100</small></div>
    <div class="qm"><b>Quote BEV-2026-0042</b><small>Date 6 October 2026 &middot; Status Sent</small></div></div>';
$pubItems = '
  <div class="pit">
    <div class="pr th"><span>#</span><span>Description</span><span class="n">Qty</span><span class="n">Unit</span><span class="n">Total</span></div>
    <div class="pr"><span>1</span><span><b>Living Room</b><br>Roller Blind &mdash; Bev Roller<br>Louvolite / Sunset / Ivory<br><i>1500 &times; 1600 mm</i><br><small>+ Control Side: Left</small></span><span class="n">1</span><span class="n">&pound;520.00</span><span class="n">&pound;520.00</span></div>
    <div class="pr"><span>2</span><span><b>Kitchen</b><br>Roller Blind &mdash; Bev Roller<br>Louvolite / Sunset / Ivory<br><i>900 &times; 1200 mm</i></span><span class="n">1</span><span class="n">&pound;430.00</span><span class="n">&pound;430.00</span></div>
  </div>';
$pubTots = '<div class="ptot"><div>Subtotal <b>&pound;950.00</b></div><div>VAT (20%) <b>&pound;190.00</b></div><div class="g">Total <b>&pound;1,140.00</b></div></div>';

// The accept card.
$acceptCard = static fn (string $tick = '', string $err = '', string $cls = '', string $style = ''): string => '
  <div class="acc ' . $cls . '" style="' . $style . '"><h4>Accept this quote</h4>
    <p>Type your full name to confirm acceptance. We&rsquo;ll record it as your digital sign-off and let Beverley Blinds know.</p>
    <span class="fl">Your full name</span><span class="ib">Emma Fletcher</span>
    <div class="tc"><i class="cb">' . $tick . '</i><span>I agree to the <u>Terms &amp; Conditions</u> of Beverley Blinds.</span></div>
    ' . $err . '
    <div class="fact"><span class="btnp">Accept quote</span><span class="btns">Decline</span></div></div>';

return [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Sending & accepting',
        'eyebrow' => 'Quotes',
        'v'       => 2,
        'blurb'   => 'The Send to customer panel, the plain-text email, the whole public accept page, the typed-name sign-off, deposits, what a customer\'s Yes sets off on your side, and the thirty-day window.',
        'lede'    => 'You send a quote from <b>inside the quote itself</b> &mdash; scroll down the quote screen to the
                      <b>Send to customer</b> panel. From there you can <b>email the PDF with an accept link</b>, share the same link on
                      <b>WhatsApp</b>, or <b>copy the link</b> and paste it anywhere. The customer opens it with <b>no login</b>, reads it,
                      types their name and <b>accepts</b>. This guide shows both sides, <b>slowly</b>, one idea per chapter: what you press,
                      what they see, and what their <em>Yes</em> sets off for you. <em>(The button below opens your Retail Quotes list &mdash;
                      click a quote, then scroll down to <b>Send to customer</b>.)</em>',
        'open'    => '/orders/index.php?scope=quotes&type=retail',
        'css'     => '
          .gd .sc{ position:relative; min-height:420px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
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
          .gd .fact{ display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.5rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; }
          .gd .card{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .card h4{ margin:0 0 .35rem; font-size:.78rem; color:var(--ink); }
          .gd .gph{ color:var(--faint); }

          /* send panel */
          .gd .pane{ border:1px solid var(--line); border-radius:10px; padding:.55rem .65rem; background:var(--surface); max-width:33rem; }
          .gd .sech{ font-size:.82rem; font-weight:800; color:var(--ink); margin:0 0 .4rem; }
          .gd .fg{ display:flex; flex-direction:column; gap:.2rem; min-width:0; }
          .gd .fl{ font-size:.64rem; font-weight:700; color:var(--soft); }
          .gd .ib{ display:flex; align-items:center; min-height:27px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .45rem; font-size:.72rem; color:var(--ink); overflow:hidden; white-space:nowrap; }
          .gd .sendrow{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; margin-top:.55rem; }
          .gd .red{ background:rgba(220,38,38,.5); color:#000; font-weight:800; font-size:.76rem; border-radius:8px; padding:.45rem .8rem; white-space:nowrap; }
          .gd .wa{ background:rgba(37,211,102,.5); color:#000; font-weight:800; font-size:.76rem; border-radius:8px; padding:.45rem .8rem; white-space:nowrap; }
          :root[data-theme="dark"] .gd .red, :root[data-theme="dark"] .gd .wa{ color:#fff; }
          .gd .pl{ font-size:.6rem; color:var(--faint); margin-top:.45rem; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
          .gd .pl code{ font-size:.6rem; }

          /* email */
          .gd .mail{ border:1px solid var(--line); border-radius:10px; background:var(--surface); max-width:30rem; font-size:.7rem; color:var(--ink); overflow:hidden; }
          .gd .mail .mh{ background:var(--panel); padding:.4rem .6rem; border-bottom:1px solid var(--line); font-size:.66rem; color:var(--soft); line-height:1.5; }
          .gd .mail .mb{ padding:.55rem .7rem; line-height:1.55; white-space:normal; }
          .gd .mail .lnk{ color:var(--accent); text-decoration:underline; word-break:break-all; }
          .gd .att{ display:inline-flex; gap:.3rem; align-items:center; border:1px solid var(--line); border-radius:6px; padding:.2rem .45rem; font-size:.64rem; margin-top:.3rem; background:var(--panel); }

          /* public page */
          .gd .pub{ border:1px solid var(--line); border-radius:10px; background:#fff; color:#111827; padding:.6rem .75rem; max-width:34rem; font-size:.66rem; }
          :root[data-theme="dark"] .gd .pub{ background:#f9fafb; }
          .gd .pub-h{ display:flex; justify-content:space-between; gap:.6rem; border-bottom:1px solid #e5e7eb; padding-bottom:.45rem; margin-bottom:.45rem; }
          .gd .pub-h small{ color:#6b7280; font-size:.6rem; }
          .gd .logo2{ display:inline-grid; place-items:center; width:1.5rem; height:1.5rem; border-radius:6px; background:#1f3b5b; color:#fff; font-weight:900; margin-right:.3rem; float:left; }
          .gd .qm{ text-align:right; } .gd .qm b{ display:block; font-size:.8rem; }
          .gd .qm small{ display:block; }
          .gd .for{ background:#f9fafb; border-radius:7px; padding:.35rem .5rem; margin-bottom:.45rem; }
          .gd .for small{ display:block; color:#6b7280; font-size:.56rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; }
          .gd .pit{ border:1px solid #e5e7eb; border-radius:7px; overflow:hidden; }
          .gd .pr{ display:grid; grid-template-columns:1rem 3fr .5fr 1fr 1fr; gap:.3rem; padding:.22rem .45rem; line-height:1.3; border-top:1px solid #e5e7eb; align-items:start; line-height:1.35; }
          .gd .pr:first-child{ border-top:0; } .gd .pr.th{ background:#f3f4f6; font-weight:700; color:#4b5563; font-size:.56rem; text-transform:uppercase; }
          .gd .pr .n{ text-align:right; } .gd .pr small{ color:#6b7280; } .gd .pr i{ color:#374151; }
          .gd .ptot{ margin-left:auto; max-width:13rem; margin-top:.35rem; }
          .gd .ptot div{ display:flex; justify-content:space-between; padding:.12rem 0; }
          .gd .ptot .g{ border-top:2px solid #111827; font-weight:800; font-size:.76rem; margin-top:.15rem; padding-top:.25rem; }
          .gd .dep{ background:#fef3c7; border:1px solid #fde68a; border-radius:7px; padding:.4rem .55rem; margin-top:.45rem; }
          .gd .bank{ border:1px solid #d1d5db; background:#f9fafb; border-radius:7px; padding:.4rem .55rem; margin-top:.45rem; line-height:1.55; }
          .gd .acc{ border:2px solid #2563eb; border-radius:10px; padding:.55rem .65rem; background:#fff; color:#111827; max-width:30rem; font-size:.68rem; }
          .gd .acc h4{ margin:0 0 .25rem; font-size:.84rem; }
          .gd .acc p{ margin:0 0 .4rem; color:#4b5563; font-size:.64rem; }
          .gd .acc .ib{ background:#fff; color:#111827; border-color:#d1d5db; }
          .gd .acc .fl{ color:#374151; }
          .gd .tc{ display:flex; gap:.4rem; align-items:flex-start; margin:.5rem 0 .2rem; }
          .gd .tc u{ color:#2563eb; }
          .gd .cb{ width:13px; height:13px; border:1.5px solid #9ca3af; border-radius:3px; display:grid; place-items:center; font-style:normal; font-size:.66rem; font-weight:900; color:#2563eb; flex:0 0 auto; margin-top:.1rem; }
          .gd .aerr{ color:#b91c1c; font-weight:700; font-size:.64rem; margin-top:.3rem; }
          .gd .acc.done{ border-color:#16a34a; background:#f0fdf4; }
          .gd .acc.no{ border-color:#dc2626; background:#fef2f2; }
          .gd .cfm{ position:absolute; z-index:5; left:18%; top:40%; max-width:18rem; background:var(--surface); border:1px solid var(--line);
                    border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.72rem; color:var(--ink); }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }
          .gd .tab2{ border:1px solid var(--line); border-radius:9px; background:var(--surface); padding:.45rem .6rem; font-size:.66rem; color:var(--soft); max-width:16rem; box-shadow:var(--gd-shadow); }
          .gd .tab2 b{ color:var(--ink); }

          /* your side */
          .gd .four{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
          .gd .tray{ border:1px dashed #8b5cf6; border-radius:8px; padding:.35rem .5rem; font-size:.66rem; color:var(--soft); }
          .gd .tray b{ display:block; color:#6d28d9; font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.2rem; }
          .gd .tray span{ display:block; background:var(--surface); border:1px solid var(--line); border-radius:6px; padding:.2rem .4rem; color:var(--ink); }
          .gd .qsb{ display:flex; align-items:center; gap:.45rem; flex-wrap:wrap; background:#1f2937; color:#fff; border-radius:9px; padding:.42rem .65rem; font-size:.72rem; font-weight:700; margin-bottom:.5rem; }
          .gd .qsb .pill{ background:#e5e7eb; color:#374151; border-radius:999px; padding:.05rem .5rem; font-size:.6rem; }
          .gd .qsb .qa{ border-radius:6px; padding:.12rem .45rem; font-size:.62rem; }
          .gd .qsb .qa.ok{ background:#16a34a; } .gd .qsb .qa.no{ background:#4b5563; }
          .gd .qsb .tt{ margin-left:auto; }
          .gd .exp{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.45rem .6rem; font-size:.7rem; color:var(--ink); display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
          .gd .exp .btns{ margin-left:auto; }
          .gd .cal{ display:grid; grid-template-columns:repeat(7,1fr); gap:3px; margin-top:.4rem; }
          .gd .cal i{ font-style:normal; text-align:center; font-size:.56rem; padding:.18rem 0; border-radius:4px; background:var(--panel); color:var(--soft); }
          .gd .cal i.on{ background:var(--accent-wash); color:var(--accent); font-weight:800; }
          .gd .cal i.end{ background:var(--err-wash); color:var(--err); font-weight:800; }

          @media (max-width:640px){
            .gd .sc{ min-height:470px; }
            .gd .two, .gd .four{ grid-template-columns:1fr; }
            .gd .pr{ grid-template-columns:3fr .5fr 1fr; } .gd .pr > :first-child, .gd .pr > :nth-child(4){ display:none; }
            .gd .cfm{ left:4%; right:4%; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote &rarr; customer</span></div>
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
                  <div class="two">
                    <div>' . $send() . '</div>
                    <div>' . $acceptCard('&#10003;') . '</div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; fifteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — where you send from -->
                <div class="sc" data-scene="1" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Where you send from</div>
                  <div class="two">
                    <div class="card a-rise" style="--d:1.5s"><h4>Inside the quote</h4>
                      <div class="qsb" style="margin:0 0 .35rem">Quote BEV-2026-0042 <span class="pill">draft</span><span class="tt">Total &pound;1,140.00</span></div>
                      <div style="display:flex;flex-direction:column;gap:.25rem">
                        <span class="a-fade" style="--d:5s">Quote actions</span><span class="a-fade" style="--d:5.4s">Add blind &middot; Blinds (2)</span>
                        <span class="a-fade" style="--d:5.8s">Deposit</span>
                        <b class="a-pop a-ring" style="--d:9s;color:var(--accent)">&#8595; Send to customer</b></div></div>
                    <div class="card a-rise" style="--d:11.3s"><h4>The Quotes list</h4>Where you <b>watch</b> it afterwards &mdash; not where you send it.
                      <div class="chips"><span class="chip a-pop" style="--d:15s">Quote <i style="font-style:normal;color:#92400e;background:#fef3c7;border-radius:999px;padding:0 .35rem;font-size:.56rem">NOT SENT</i></span></div></div>
                  </div>
                  ' . $send(['cls' => 'a-rise', 'style' => '--d:9.5s;margin-top:.7rem']) . '
                </div>

                <!-- 2 — the two boxes -->
                <div class="sc" data-scene="2" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Two boxes to check</div>
                  ' . $send([
                        'toCls' => 'a-ring', 'toStyle' => '--d:1.5s',
                        'msgCls' => 'a-ring', 'msgStyle' => '--d:14s',
                        'msg' => '<span class="swap"><span class="gph a-out" style="--d:16.9s">Optional &mdash; anything to add above the standard text.</span><span class="a-type" style="--d:17s;--ts:36;--tt:1.8s">Lovely to meet you today &mdash; any questions, ring me.</span></span>',
                    ]) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:3s">Filled in from the quote</span>
                    <span class="chip a-pop" style="--d:9.9s">Typing over it here doesn&rsquo;t change their record</span>
                    <span class="chip a-pop" style="--d:21.2s">Empty? The email just leaves it out</span></div>
                </div>

                <!-- 3 — email it -->
                <div class="sc" data-scene="3" data-len="24">
                  <div class="sct a-fade" style="--d:.2s">Email PDF + accept link</div>
                  <div class="bnr a-pop" style="--d:9.5s">Quote PDF emailed to emma.fletcher@gmail.com.</div>
                  ' . $send(['red' => 'a-press a-ring', 'msg' => 'Lovely to meet you today &mdash; any questions, ring me.']) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:4s">&#128206; the quote as a PDF</span><span class="chip a-pop" style="--d:6s">&#128279; a link to accept online</span>
                    <span class="chip ok a-pop" style="--d:13.5s">Read the green bar &mdash; it&rsquo;s your confirmation</span>
                    <span class="chip a-pop" style="--d:17.1s">draft &rarr; <b>sent</b></span>
                    <span class="chip a-pop" style="--d:20.5s">&#9201; 30 days to accept, from today</span></div>
                </div>

                <!-- 4 — when it won\'t send -->
                <div class="sc" data-scene="4" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">When it won&rsquo;t send</div>
                  <div class="stack"><div class="ebn a-mid" style="--d:5.5s;--d2:9.1s">Please provide a valid recipient email address.</div>
                  <div class="ebn a-pop" style="--d:9.3s">Could not send the email. Check SMTP credentials in .env and the PHP error log.</div></div>
                  ' . $send(['to' => '<span class="gph">&nbsp;</span>', 'copy' => '<span class="btns a-ring" style="--d:21s">&#128279; Copy public link</span>', 'wa' => '<span class="wa a-ring" style="--d:20.5s">&#128172; Send via WhatsApp</span>']) . '
                  <div class="chips"><span class="chip a-pop" style="--d:18s">Nothing sent &middot; the quote hasn&rsquo;t moved</span>
                    <span class="chip a-pop" style="--d:20.2s">Send the link another way</span></div>
                </div>

                <!-- 5 — WhatsApp -->
                <div class="sc" data-scene="5" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Send via WhatsApp &mdash; and why it&rsquo;s sometimes missing</div>
                  <div class="two">
                    <div>' . $send(['wa' => '<span class="wa a-pop a-ring" style="--d:17.4s">&#128172; Send via WhatsApp</span>']) . '</div>
                    <div>
                      <div class="card a-rise" style="--d:1s"><h4>Opens WhatsApp with</h4>&ldquo;Hi Emma Fletcher, here&rsquo;s your quote BEV-2026-0042 from Beverley Blinds:&rdquo; and the link</div>
                      <div class="card a-rise" style="--d:6.7s;margin-top:.5rem"><h4>The green button needs both</h4>
                        <div class="a-fade" style="--d:12s">&#10003; a mobile number on the quote</div>
                        <div class="a-fade" style="--d:14.3s">&#10003; <b>Mobile is on WhatsApp</b> ticked</div></div>
                      <span class="chip a-pop" style="--d:20.4s;margin-top:.5rem">Trade account: the account&rsquo;s mobile, no tick needed</span>
                    </div>
                  </div>
                </div>

                <!-- 6 — copy the link -->
                <div class="sc" data-scene="6" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">Copy public link</div>
                  ' . $send(['copy' => '<span class="btns a-press a-ring" style="--d:1s"><span class="swap"><span class="a-out" style="--d:1.5s">&#128279; Copy public link</span><span class="a-mid" style="--d:1.5s;--d2:5s">&#10003; Link copied!</span><span class="a-fade" style="--d:5.1s">&#128279; Copy public link</span></span></span>']) . '
                  <div class="chips">
                    <span class="chip a-pop" style="--d:5.3s">Paste it in a text, an email, anywhere</span>
                    <span class="chip a-pop" style="--d:8.5s">Copy blocked? The link is printed underneath</span></div>
                  <div class="card a-rise" style="--d:13.8s;margin-top:.7rem;max-width:33rem;border-color:#f59e0b"><h4>&#9888; The first person to open it sends it</h4>
                    A draft quote turns to <b>sent</b> the first time somebody opens the link &mdash; so only share it when the quote is right.</div>
                </div>

                <!-- 7 — the email -->
                <div class="sc" data-scene="7" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">What lands in their inbox</div>
                  <div class="mail a-rise" style="--d:1s">
                    <div class="mh"><b>Your quote BEV-2026-0042 from Beverley Blinds</b><br>To: emma.fletcher@gmail.com</div>
                    <div class="mb">
                      <span class="a-fade" style="--d:5.2s">Hello Emma Fletcher,</span><br><br>
                      <span class="a-fade" style="--d:5.8s">Please find your quote (BEV-2026-0042) attached as a PDF.</span><br><br>
                      <span class="a-fade" style="--d:6.9s">Lovely to meet you today &mdash; any questions, ring me.</span><br><br>
                      <span class="a-fade" style="--d:9.6s">You can also view it online and accept it here:</span><br>
                      <span class="lnk a-fade a-ring" style="--d:10s">https://yourblinds.uk/quote-history/public.php?token=8f3c&hellip;</span><br><br>
                      <span class="a-fade" style="--d:15.1s">If you have any questions please reply to this email.</span><br><br>
                      <span class="a-fade" style="--d:17s">Kind regards,<br>Beverley Blinds</span>
                      <div><span class="att a-pop" style="--d:2.5s">&#128206; BEV-2026-0042.pdf</span></div>
                    </div></div>
                  <span class="chip a-pop" style="--d:12.3s;margin-top:.5rem">Plain text &mdash; no fancy button to hunt for</span>
                </div>

                <!-- 8 — the public page -->
                <div class="sc" data-scene="8" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">What they see &mdash; no login needed</div>
                  <div class="pub">
                    <div class="a-fade" style="--d:3.2s">' . $pubHead . '</div>
                    <div class="for a-fade" style="--d:7.4s"><small>Quote for</small><b>Emma Fletcher</b><br>14 Clarendon Ave, Leamington Spa CV32 5PJ</div>
                    <div class="a-rise" style="--d:8.8s">' . $pubItems . '</div>
                    <div class="a-fade" style="--d:14.9s">' . $pubTots . '</div>
                  </div>
                  <span class="chip a-pop" style="--d:18.1s;margin-top:.5rem">Sizes and line prices: Settings &rarr; Quoting</span>
                </div>

                <!-- 9 — deposit + how to pay -->
                <div class="sc" data-scene="9" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">The deposit, and how to pay</div>
                  <div class="pub">
                    ' . $pubTots . '
                    <div class="dep a-rise" style="--d:1s"><b>Deposit on acceptance:</b> <span class="a-ring" style="--d:3.5s">&pound;570.00</span>. The balance will be due on completion.</div>
                    <div class="bank a-rise" style="--d:8.4s"><b>How to pay &mdash; bank transfer</b><br>Account name: <b>Beverley Blinds Ltd</b><br>Sort code: <b>20-45-77</b><br>Account number: <b>43218765</b><br>
                      <span style="color:#6b7280">Please use <b class="a-ring" style="--d:16s">BEV-2026-0042</b> as your payment reference.</span></div>
                  </div>
                  <span class="chip a-pop" style="--d:19.5s;margin-top:.5rem">Bank details come from your Settings</span>
                </div>

                <!-- 10 — accepting -->
                <div class="sc" data-scene="10" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Accepting</div>
                  <div class="two">
                    <div>' . $acceptCard('<span class="a-pop" style="--d:16.8s">&#10003;</span>', '<div class="aerr a-mid" style="--d:13.8s;--d2:16.8s">Please tick the box to agree to the Terms &amp; Conditions.</div>', 'a-rise', '--d:1s') . '</div>
                    <div>
                      <span class="chip a-pop" style="--d:2.6s">Their name is already in the box</span>
                      <div class="tab2 a-drop" style="--d:18.1s;margin-top:.6rem"><b>Beverley Blinds</b> &middot; Terms &amp; Conditions<br><span style="color:var(--accent)">&larr; Back to your quote</span><br><i>opens in its own tab</i></div>
                    </div>
                  </div>
                </div>

                <!-- 11 — the yes -->
                <div class="sc" data-scene="11" data-len="23">
                  <div class="sct a-fade" style="--d:.2s">The sign-off</div>
                  <div class="two">
                    <div>
                      <div class="bnr a-pop" style="--d:1s">Quote accepted. Thanks!</div>
                      <div class="acc done a-rise" style="--d:1.2s"><h4>Quote accepted &#10003;</h4>
                        <p style="margin:0">Thanks Emma Fletcher! This quote was accepted on 7 October 2026. Beverley Blinds will be in touch.</p></div>
                      <div class="chips"><span class="chip a-pop" style="--d:3s">name typed</span><span class="chip a-pop" style="--d:4.5s">date</span><span class="chip a-pop" style="--d:6s">IP address</span></div>
                    </div>
                    <div class="mail a-rise" style="--d:12.5s"><div class="mh"><b>Thank you for accepting quote BEV-2026-0042</b></div>
                      <div class="mb">Hello Emma Fletcher,<br><br>Thank you for accepting your quote BEV-2026-0042 &mdash; we really appreciate your business&hellip;</div></div>
                  </div>
                  <span class="chip a-pop" style="--d:18.6s;margin-top:.6rem">Change it, or empty it to stop it: Settings &rarr; Legal</span>
                </div>

                <!-- 12 — declining -->
                <div class="sc" data-scene="12" data-len="19">
                  <div class="sct a-fade" style="--d:.2s">Or they decline</div>
                  <div class="stack">
                    <div class="a-out" style="--d:9.2s">' . $acceptCard('', '', '', '') . '</div>
                    <div class="acc no a-fade" style="--d:12s"><h4>Quote declined</h4><p style="margin:0">This quote was declined. If that was a mistake, please contact Beverley Blinds directly.</p></div>
                  </div>
                  <div class="cfm a-mid" style="--d:1s;--d2:9s">Decline this quote? Your supplier will be notified.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:8s">Yes, continue</span></div></div>
                  <span class="chip a-pop" style="--d:13.8s;margin-top:.6rem">Pending fitting taken back off your calendar</span>
                </div>

                <!-- 13 — your side -->
                <div class="sc" data-scene="13" data-len="27">
                  <div class="sct a-fade" style="--d:.2s">On your side, a Yes sets off&hellip;</div>
                  <div class="four">
                    <div class="card a-rise" style="--d:3.3s"><h4>&#128231; An email to you</h4>&ldquo;New order &mdash; BEV-2026-0042 accepted (Emma Fletcher)&rdquo;
                      <div style="margin-top:.3rem">If an address is set in <b>Settings &rarr; Quoting &rarr; New order alerts</b>.</div></div>
                    <div class="card a-rise" style="--d:11.2s"><h4>&#128197; A fitting to book</h4>
                      <div class="tray"><b>Pending Fitting</b><span class="a-drop" style="--d:12.5s">Install: BEV-2026-0042 &mdash; Emma Fletcher</span></div>
                      <div style="margin-top:.3rem">No date yet &mdash; drag it onto the day.</div></div>
                    <div class="card a-rise" style="--d:20s"><h4>&#163; The deposit shows as due</h4>&pound;570.00 due</div>
                    <div class="card a-rise" style="--d:21.9s"><h4>&#127981; Sometimes, straight to Ordered</h4>Every blind made in-house, no outside supplier, and <b>Auto-place in-house orders</b> on.</div>
                  </div>
                </div>

                <!-- 14 — yes on the phone -->
                <div class="sc" data-scene="14" data-len="22">
                  <div class="sct a-fade" style="--d:.2s">A yes on the phone, or at the door</div>
                  <div class="qsb a-rise" style="--d:1.5s">Quote BEV-2026-0042 <span class="pill"><span class="swap"><span class="a-out" style="--d:8.2s">sent</span><span class="a-fade" style="--d:8.2s">accepted</span></span></span>
                    <span class="a-out" style="--d:8.4s;display:inline-flex;gap:.45rem"><span class="qa ok a-ring" style="--d:6.6s">&#10003; Customer accepted</span><span class="qa no">&#10005; Customer declined</span></span><span class="tt">Total &pound;1,140.00</span></div>
                  <div class="card a-rise" style="--d:11.5s;max-width:30rem"><h4>Quote actions</h4><span class="btns a-ring" style="--d:12.5s">&#128230; Save as order</span>
                    <span style="margin-left:.4rem">accepts it <b>and</b> places the order, in one go</span></div>
                  <div class="chips"><span class="chip a-pop" style="--d:16.9s">Same result &mdash; but no &ldquo;New order&rdquo; alert email</span></div>
                </div>

                <!-- 15 — thirty days -->
                <div class="sc" data-scene="15" data-len="25">
                  <div class="sct a-fade" style="--d:.2s">Thirty days to accept</div>
                  <div class="two">
                    <div class="card a-rise" style="--d:1.5s"><h4>From the day it&rsquo;s sent</h4>
                      <div class="cal">' . implode('', array_map(static fn ($i) => '<i class="' . ($i === 29 ? 'end a-pop' : 'on a-fade') . '" style="--d:' . (2.5 + $i * .1) . 's">' . ($i + 1) . '</i>', range(0, 29))) . '</div></div>
                    <div class="acc no a-rise" style="--d:9.8s"><h4>This quote has expired</h4>
                      <p style="margin:0">Quotes can be accepted for 30 days, and this one ran out on 5 November 2026. Prices may have changed since &mdash; please contact Beverley Blinds for an updated quote.</p></div>
                  </div>
                  <div class="exp a-rise" style="--d:13.4s;margin-top:.7rem">This quote expired on 5 November 2026 (30 days after it was sent) &mdash; the customer can no longer accept it from their link.
                    <span class="btns a-ring" style="--d:16s">Renew for 30 days</span></div>
                  <span class="chip a-pop" style="--d:21.5s;margin-top:.5rem">Or email it again &mdash; that restarts it too</span>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where you send from.</b> Not from the Quotes list &mdash; from <em>inside</em> the quote. Open it and scroll down past the blinds to
             <b>Send to customer</b>. The list is where you <em>watch</em> it afterwards.</p>

          <ul class="steps">
            <li><b>Recipient email</b> &mdash; filled in from the quote&rsquo;s customer details. Check it&rsquo;s the address they use. Typing over it here
                doesn&rsquo;t change their customer record.</li>
            <li><b>Message (optional)</b> &mdash; faint wording <em>&ldquo;Optional &mdash; anything to add above the standard text.&rdquo;</em> Whatever you
                type goes into the email as its own paragraph; leave it empty and the email simply doesn&rsquo;t have it. Drag the corner to make the box
                taller.</li>
            <li><b>&#128231; Email PDF + accept link</b> &mdash; builds the quote PDF, attaches it, and emails it with a link to accept online. The page comes
                back with <b>&ldquo;Quote PDF emailed to emma.fletcher@gmail.com.&rdquo;</b> &mdash; your only confirmation, so read it. Sending turns a
                <b>draft</b> into <b>sent</b> and starts the <b>30-day</b> window to accept.</li>
            <li><b>&#128172; Send via WhatsApp</b> &mdash; opens WhatsApp with <em>&ldquo;Hi Emma Fletcher, here&rsquo;s your quote BEV-2026-0042 from Beverley
                Blinds:&rdquo;</em> and the link. <b>It isn&rsquo;t always there</b>: it needs a <b>mobile</b> number (or, failing that, the phone number) <b>and</b>
                the <b>Mobile is on WhatsApp</b> tick in the customer details. On a <b>trade-account</b> job it uses the account&rsquo;s own mobile and needs no
                tick &mdash; if it&rsquo;s missing, add a mobile to the trade account.</li>
            <li><b>&#128279; Copy public link</b> &mdash; copies the link; it flashes <b>&ldquo;&#10003; Link copied!&rdquo;</b>. If your browser blocks copying,
                the whole link is printed underneath (<b>Public link:</b> &hellip;) to select by hand.</li>
          </ul>

          <div class="oops"><b>When the red button doesn&rsquo;t work.</b>
             <b>&ldquo;Please provide a valid recipient email address.&rdquo;</b> &mdash; the box is empty or mistyped.
             <b>&ldquo;Could not send the email. Check SMTP credentials in .env and the PHP error log.&rdquo;</b> &mdash; the app&rsquo;s email settings need
             looking at; copy the link and send it another way meanwhile. <b>&ldquo;Could not render the quote PDF.&rdquo;</b> &mdash; the PDF, not the quote.
             <b>&ldquo;Daily email limit reached for this account &mdash; please try again tomorrow&hellip;&rdquo;</b> &mdash; exactly that. In every case nothing
             has been sent and the quote hasn&rsquo;t moved.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>A shared link sends the quote by itself.</b> The first time a <b>person</b> opens the
             public link, a quote still in <b>draft</b> is flipped to <b>sent</b> and dated (link previews in WhatsApp, Outlook and the like don&rsquo;t count).
             So don&rsquo;t paste the link anywhere until you&rsquo;re happy with the quote. <b>No signal?</b> On a tablet set up for offline, the red button can
             queue the email until the signal is back &mdash; see <b>&ldquo;Working offline on a tablet&rdquo;</b>.</div></div>

          <p><b>What lands in their inbox.</b> A plain-text email. Subject <em>&ldquo;Your quote BEV-2026-0042 from Beverley Blinds&rdquo;</em>; then
             &ldquo;Hello Emma Fletcher,&rdquo; (the full name on the quote), &ldquo;Please find your quote (BEV-2026-0042) attached as a PDF.&rdquo;, your
             message, &ldquo;You can also view it online and accept it here:&rdquo; with <b>the link on its own line</b>, &ldquo;If you have any questions please
             reply to this email.&rdquo;, &ldquo;Kind regards,&rdquo; and your company name. The attachment is named after the quote number. A reply comes
             straight back to you.</p>

          <p><b>What they see.</b> No login &mdash; the long code in the link is the key. Your <b>logo</b>, company name, address, phone and email;
             <b>Quote BEV-2026-0042</b> with the <b>Date</b> and <b>Status</b>; a <b>Quote for</b> panel with their name and address; then the items &mdash;
             <b>#, Description, Qty, Unit, Total</b> &mdash; each with the <b>room</b>, product and system, <b>supplier / fabric / colour</b>, the <b>size</b>
             and each option as &ldquo;+ Control Side: Left&rdquo;. Then <b>Subtotal</b>, <b>VAT (20%)</b> and <b>Total</b>, your quote <b>Notes</b> if any,
             the <b>deposit</b> card (<em>&ldquo;Deposit on acceptance: &pound;570.00. The balance will be due on completion.&rdquo;</em>) and <b>How to pay
             &mdash; bank transfer</b> with your account name, sort code, account number, your own payment wording and <em>&ldquo;Please use BEV-2026-0042 as
             your payment reference.&rdquo;</em> Whether sizes and line prices show is set by <b>Prices on the customer quote</b> and <b>Sizes on the customer
             quote</b> in <b>Settings &rarr; Quoting</b> (both start ticked). The WT charge is never shown by name; an override price shows as a plain
             <b>Discount</b> (or <b>Price adjustment</b>) row.</p>

          <ul class="steps">
            <li><b>They accept.</b> <b>Accept this quote</b> &mdash; <em>&ldquo;Type your full name to confirm acceptance. We&rsquo;ll record it as your digital
                sign-off and let &lt;your company&gt; know.&rdquo;</em> <b>Your full name</b> is already filled in; they can change it. If you have Terms &amp;
                Conditions there&rsquo;s an <b>unticked</b> box, <em>&ldquo;I agree to the Terms &amp; Conditions of &lt;your company&gt;.&rdquo;</em> Miss it and
                the page says <em>&ldquo;Please tick the box to agree to the Terms &amp; Conditions.&rdquo;</em>; clear the name and it says <em>&ldquo;Please type
                your full name.&rdquo;</em> The <b>Terms &amp; Conditions</b> words open your terms on <b>their own tab</b>, with <b>&ldquo;&larr; Back to your
                quote&rdquo;</b> at the top. Then <b>Accept quote</b>.</li>
            <li><b>Or they decline.</b> <b>Decline</b> asks first &mdash; <em>&ldquo;Decline this quote? Your supplier will be notified.&rdquo;</em> with
                <b>Cancel</b> and <b>Yes, continue</b>. The quote goes to <b>declined</b>, they see <b>&ldquo;Quote declined&rdquo;</b>, and the pending fitting is
                taken back off your calendar.</li>
            <li><b>The sign-off.</b> On a yes the app stores the <b>name they typed</b>, the <b>date</b> and their <b>IP address</b>, and shows
                <b>&ldquo;Quote accepted. Thanks!&rdquo;</b> and <b>&ldquo;Quote accepted &#10003;&rdquo;</b> &mdash; <em>&ldquo;Thanks Emma Fletcher! This quote was
                accepted on 7 October 2026. Beverley Blinds will be in touch.&rdquo;</em></li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Their Yes sets off four things.</b>
             <b>One:</b> an email to you &mdash; <em>&ldquo;New order &mdash; BEV-2026-0042 accepted (Emma Fletcher)&rdquo;</em> with the total and a link &mdash;
             <b>only if</b> an address is set under <b>Settings &rarr; Quoting &rarr; New order alerts</b>, and only for acceptances made <em>online</em>.
             <b>Two:</b> the <b>deposit</b> shows as due. <b>Three:</b> an appointment <b>&ldquo;Install: &lt;quote number&gt; &mdash; &lt;customer&gt;&rdquo;</b> lands
             in the calendar&rsquo;s <b>Pending Fitting</b> tray with no date, ready to drag onto the day. <b>Four:</b> if <b>Auto-place in-house orders</b> is on
             (it is by default) and <b>every</b> blind is made in-house with no outside supplier, the quote goes <b>straight to Ordered</b> &mdash; so it may read
             &ldquo;Ordered&rdquo; rather than &ldquo;Accepted&rdquo; when you look.</div></div>

          <p><b>The thank-you email.</b> If the customer has an email address, a thank-you goes out the moment they accept &mdash; subject <em>&ldquo;Thank you
             for accepting quote &lt;number&gt;&rdquo;</em>. Edit it in <b>Settings &rarr; Legal</b>, under <b>&ldquo;Thank-you email (sent when a customer accepts
             a quote)&rdquo;</b>, using <code>{{customer_name}}</code>, <code>{{company_name}}</code>, <code>{{quote_number}}</code> and
             <code>{{quote_link}}</code>. <b>&ldquo;Leave empty to send no thank-you email.&rdquo;</b></p>

          <p><b>Saying yes on their behalf.</b> In the dark bar at the top of the quote: <b>&ldquo;&#10003; Customer accepted&rdquo;</b> and <b>&ldquo;&#10005; Customer
             declined&rdquo;</b> (which asks <em>&ldquo;Mark this quote as declined?&rdquo;</em>). Or <b>&#128230; Save as order</b> in <b>Quote actions</b> accepts it
             <em>and</em> places it in one go. Same result &mdash; except the New-order alert email only fires for an online acceptance.</p>

          <p><b>Where to watch it.</b> <b>Retail &rarr; Quotes</b> &mdash; <em>&ldquo;Quotes still in the pipeline &mdash; drafts, sent, and declined.&rdquo;</em>
             A draft carries an amber <b>Not sent</b> badge (<em>&ldquo;This quote hasn&rsquo;t been sent to the customer yet&rdquo;</em>); the badge going is how
             you see it has gone out. Once accepted it moves to <b>Orders</b> &mdash; <em>&ldquo;Accepted onward &mdash; orders, invoices and paid jobs.&rdquo;</em></p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Thirty days to accept.</b> A sent quote can be accepted for <b>30 days</b> after it was
             sent. After that the link still opens, but instead of Accept the customer sees <b>&ldquo;This quote has expired&rdquo;</b> &mdash; <em>&ldquo;Quotes can be
             accepted for 30 days, and this one ran out on &hellip; Prices may have changed since &mdash; please contact &lt;your company&gt; for an updated
             quote.&rdquo;</em> (and an attempt to accept says <em>&ldquo;This quote has expired. Please contact &hellip;&rdquo;</em>). On your side the quote shows a red
             bar, <em>&ldquo;This quote expired on &hellip; (30 days after it was sent) &mdash; the customer can no longer accept it from their link. Check the prices,
             then renew it or email it again.&rdquo;</em> with <b>Renew for 30 days</b>. Emailing it again restarts the window too.</div></div>

          <p><b>If they come back to the link later.</b> Once the job has moved on they see <b>&ldquo;Quote in progress &mdash; This quote has moved on to
             ordered.&rdquo;</b>; pressing Accept twice gets <b>&ldquo;This quote is no longer awaiting your response.&rdquo;</b> &mdash; the app refusing to accept
             the same quote twice.</p>',
        'script'  => [
            ['1', 'Where you send from',         'You send a quote from inside the quote itself, not from the list. Open the quote, and scroll down, past the blinds, to the panel called Send to customer. The Quotes list is where you watch it afterwards. A quote that has not gone yet is marked Not sent.', 1],
            ['2', 'Two boxes to check',          'There are two boxes. Recipient email is already filled in, from the quote\'s customer details. Check it is the address they really use. Changing it here does not change their customer record. Underneath is Message. It is optional. Anything you type goes into the email, in your own words. Leave it empty, and the email simply leaves it out.', 2],
            ['3', 'Email PDF + accept link',     'Then press the red button: Email PDF plus accept link. It sends the quote as a PDF, with a link they can click to accept online. A green bar comes back, saying who it was emailed to. That green bar is your confirmation, so read it. Sending also turns a draft into a sent quote, and they then have thirty days to accept it.', 3],
            ['4', 'When it won\'t send',          'Two things can stop it. If the email box is empty, or mistyped, you are told: please provide a valid recipient email address. If the email itself will not go, you see a message about SMTP credentials. That is the email settings, not your quote. Either way, nothing was sent. So send the link another way, instead.', 4],
            ['5', 'Send via WhatsApp',           'The green Send via WhatsApp button opens WhatsApp, with a short message and the same link. If you cannot see the button, check two things in the customer details. There must be a mobile number. And Mobile is on WhatsApp must be ticked. Once both are there, the button appears. On a trade account job, it uses the account\'s own mobile.', 5],
            ['6', 'Copy public link',            'Copy public link puts the link on your clipboard, and says Link copied. Paste it into a text, or anywhere you like. If copying is blocked, the link is printed underneath, to copy by hand. One warning. The first time someone opens that link, a draft quote turns to sent. So only share it once the quote is right.', 6],
            ['7', 'What lands in their inbox',   'This is what arrives. A plain email, with the quote attached as a PDF. Hello, and their name. Your own message, if you wrote one. Then the link, on a line of its own. There is no fancy button to hunt for. And it asks them to reply to the email, so if they do, the reply comes straight back to you.', 7],
            ['8', 'What they see',               'The link opens this, with no login needed. Your logo, your address, the quote number, and the date. Then who it is for. Then every blind, with its room, its fabric and colour, its size, and its options. Then the subtotal, the VAT, and the total. Whether sizes and line prices show is up to you, in Settings.', 8],
            ['9', 'The deposit, and how to pay', 'Under the total, they see the deposit they will owe when they say yes, and that the balance is due on completion. Then a box called How to pay, bank transfer, with your account name, sort code and account number. And it asks them to use the quote number as the payment reference, so you can match it up.', 9],
            ['10', 'Accepting',                  'At the bottom is Accept this quote. Their full name is already in the box. If you have terms and conditions, there is a box to tick, to agree to them. If they forget, the page stops them: please tick the box to agree to the Terms and Conditions. The Terms and Conditions words open your terms in their own tab, so they never lose the quote.', 10],
            ['11', 'The sign-off',               'When they press Accept quote, the app records the name they typed, the date, and where it came from, as their digital sign-off. They see Quote accepted, with their name. And if you have their email address, a thank you email goes to them straight away. You can change its wording, or switch it off, in Settings.', 11],
            ['12', 'Or they decline',            'If they press Decline instead, it asks them first: decline this quote? Your supplier will be notified. They press Yes, continue, and the quote is marked declined. They see Quote declined. And the fitting that was waiting on your calendar is taken off again.', 12],
            ['13', 'Your side',                  'On your side, a yes sets off several things. You get an email, new order, quote accepted, as long as an address is set in New order alerts, in Settings. An install appointment lands in the Pending Fitting tray on your calendar, with no date yet, ready to drag onto a day. The deposit shows as due. And if every blind is made in-house, it can go straight to Ordered.', 13],
            ['14', 'A yes on the phone',         'Plenty of people say yes on the phone, or at the door. You do not need the link for that. In the dark bar at the top of the quote, press Customer accepted. Or press Save as order, which accepts it and places the order, in one go. The result is the same, except you do not get the new order email.', 14],
            ['15', 'Thirty days to accept',      'A sent quote can be accepted for thirty days. After that, the link still opens, but instead of the Accept button, the customer sees: this quote has expired. Prices may have changed. On your side, the quote shows a red bar, with a Renew for thirty days button. Check the prices, then renew it. Or email it again, which restarts it too.', 15],
        ],
];
