<?php
declare(strict_types=1);

/**
 * Guide: settings-bank — "Bank details for payments" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the last section of Settings → Quoting, "Bank details for customer
 * payments" (admin/settings.php, POST _action=payment_details): Account name,
 * Sort code, Account number, Payment note (optional), Save bank details →
 * "Bank / payment details saved." Where it prints: the "How to pay — bank
 * transfer" box in quote-history/public.php (online quote) and
 * pdf-generator/pdf.php (quote / invoice / receipt PDF) — shown only when the
 * account name or account number is filled in, always ending "Please use
 * <quote number> as your payment reference." The factory's wholesale invoice
 * reads the factory's own boxes (_partials/factory_ar.php, "Payment").
 *
 * v2: one scene per script line; data-len is worked out from the line's own
 * length (characters ÷ 13.6), so editing a line keeps its scene in step.
 */

$vo = [
    1 => ['Where it lives',
          'Bank details sit at the very bottom of the Quoting tab in Settings, under Quote defaults. The section is called Bank details for customer payments. Fill it in once, and every customer can see how to pay you by bank transfer. It has its own Save button, so saving it does not change anything else on the tab.'],
    2 => ['Account name',
          'The first box is Account name. Type it exactly as your bank holds it, for example, Bright Blinds Limited. Your customer copies it into their own banking app. If the name does not match, their bank may warn them before they pay, and that can put them off.'],
    3 => ['Sort code and account number',
          'Next comes Sort code on the left, and Account number on the right. Nothing here is checked for you. A mistyped number saves quite happily, and then goes out on every quote. So copy the numbers from a bank statement, and read them back once before you save.'],
    4 => ['The payment note',
          'Payment note is optional. It is one line, for anything else the customer needs to know, such as, bank transfer only. There is no need to ask for a reference here. The quote number is added as the payment reference for you, on every document.'],
    5 => ['Save bank details',
          'When all four boxes look right, click Save bank details. The page reloads, and a green bar appears at the top of the page, saying the bank and payment details are saved. The button is at the bottom, so scroll up to see it.'],
    6 => ['What the customer sees',
          'Here is what your customer sees. A box headed, How to pay, bank transfer. It shows your account name, sort code and account number, with your note underneath in grey. The last line asks them to use the quote number as their payment reference. That way, money landing in your bank is easy to match to the right job.'],
    7 => ['Where it appears',
          'The box appears on the customer\'s online quote, just under the deposit. It is also on the quote P D F. Invoices and receipts are made from the same document, so they carry the box too. Change the details here, and the next document you send picks up the new ones.'],
    8 => ['What switches it on',
          'One thing catches people out. The box only appears when the Account name or the Account number has something in it. A sort code, or a note, on its own shows nothing at all. And if you would rather customers rang you to pay, clear all four boxes and save. The box then disappears.'],
];
$len = static fn (int $n): string => (string) round(mb_strlen($vo[$n][1]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

$tabs = static function (string $on): string {
    $h = '<div class="tabs">';
    foreach (['Company', 'Quoting', 'Legal', 'Status colours', 'Suppliers', 'Accounting', 'Back up data'] as $t) {
        $h .= '<span class="tab' . ($t === $on ? ' on' : '') . '">' . $t . '</span>';
    }
    return $h . '</div>';
};

// One field: label + box. $val = '' shows the placeholder; $type = [start, steps, secs] types $val in.
$fld = static function (string $label, string $ph, string $val = '', ?array $type = null, string $boxCls = ''): string {
    $in = $val === '' ? '<span class="ph">' . $ph . '</span>'
        : ($type === null ? $val
            : '<span class="stk"><span class="ph a-out" style="--d:' . $type[0] . 's">' . $ph . '</span>'
            . '<span class="a-type" style="--d:' . $type[0] . 's;--ts:' . $type[1] . ';--tt:' . $type[2] . 's">' . $val . '</span></span>');
    return '<div><span class="lb">' . $label . '</span><span class="in ' . $boxCls . '">' . $in . '</span></div>';
};

// The customer's "How to pay" box. $parts: which lines show.
$howto = static function (array $parts = ['name', 'sort', 'acc', 'note'], string $refCls = '', string $refStyle = ''): string {
    $h = '<div class="pay"><b class="payh">How to pay &mdash; bank transfer</b>';
    if (in_array('name', $parts, true)) $h .= '<div>Account name: <b>Bright Blinds Ltd</b></div>';
    if (in_array('sort', $parts, true)) $h .= '<div>Sort code: <b>20-00-00</b></div>';
    if (in_array('acc', $parts, true))  $h .= '<div>Account number: <b>12345678</b></div>';
    if (in_array('note', $parts, true)) $h .= '<div class="g">Bank transfer only, please.</div>';
    return $h . '<div class="g ' . $refCls . '" style="' . $refStyle . '">Please use <b>BRI-2026-0042</b> as your payment reference.</div></div>';
};

$script = [];
foreach ($vo as $n => [$cap, $line]) $script[] = [(string) $n, $cap, $line, $n];

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Bank details for payments',
        'eyebrow' => 'Settings · Quoting',
        'v'       => 2,
        'blurb'   => 'Four boxes at the bottom of the Quoting tab that put a "How to pay — bank transfer" block on every quote, invoice and receipt — with the quote number added as the reference.',
        'lede'    => 'Right at the bottom of the <b>Quoting</b> tab there are <b>four boxes</b>. Fill them in once and a
                      <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> box appears on your customer&rsquo;s quote, and on the
                      invoice and receipt too, with the <b>quote number</b> added as the payment reference for you. This guide
                      goes one box at a time, then shows exactly what the customer sees. To get there: <b>Settings</b> &rarr;
                      <b>Quoting</b> &rarr; scroll to <b>Bank details for customer payments</b>.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin:0 0 .75rem; }
          .gd .tab{ font-size:.66rem; font-weight:600; color:var(--soft); padding:.28rem .45rem; border-radius:6px 6px 0 0; }
          .gd .tab.on{ color:var(--accent); box-shadow:inset 0 -2px 0 var(--accent); }
          .gd .sh{ font-size:.86rem; font-weight:800; color:var(--ink); margin:0 0 .3rem; }
          .gd .hn{ display:block; font-size:.64rem; color:var(--faint); line-height:1.4; margin:0 0 .55rem; max-width:31rem; }
          .gd .lb{ display:block; font-size:.68rem; font-weight:700; color:var(--soft); margin:0 0 .2rem; }
          .gd .in{ display:flex; align-items:center; min-height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                   background:var(--surface); padding:0 .5rem; font-size:.78rem; color:var(--ink); white-space:nowrap; overflow:hidden; }
          .gd .ph{ color:var(--faint); }
          .gd .stk{ display:inline-grid; } .gd .stk > *{ grid-area:1/1; }
          .gd .form{ display:grid; gap:.55rem; max-width:31rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.55rem .8rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .row{ display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; } .gd .mt{ margin-top:.7rem; }
          .gd .on2{ border-color:var(--accent) !important; }

          /* the customer box */
          .gd .pay{ border:1px solid #d1d5db; border-radius:8px; padding:.55rem .8rem; background:#f9fafb; color:#1f2937; font-size:.74rem; line-height:1.6; max-width:24rem; }
          .gd .payh{ display:block; margin-bottom:.2rem; font-size:.78rem; }
          .gd .pay .g{ color:#6b7280; }
          .gd .ref{ background:rgba(37,99,235,.12); border-radius:5px; padding:0 .25rem; margin:0 -.25rem; }
          .gd .docs{ display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; max-width:34rem; }
          .gd .doc{ border:1px solid var(--line); border-radius:9px; background:var(--surface); padding:.45rem .5rem; font-size:.66rem; color:var(--soft); text-align:center; }
          .gd .doc b{ display:block; color:var(--ink); font-size:.74rem; margin-bottom:.3rem; }
          .gd .doc i{ display:block; height:.35rem; border-radius:3px; background:var(--line); margin:.2rem 0; }
          .gd .doc em{ display:block; font-style:normal; border:1px solid #d1d5db; background:#f9fafb; border-radius:4px; font-size:.56rem; color:#374151; padding:.15rem; margin-top:.3rem; }
          .gd .deposit{ border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; padding:.45rem .8rem; font-size:.72rem; color:#166534; max-width:24rem; margin-bottom:.5rem; }
          .gd .vs{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; max-width:34rem; }
          .gd .case{ border:1px dashed var(--line); border-radius:10px; padding:.5rem .6rem; font-size:.7rem; color:var(--soft); }
          .gd .case h4{ margin:0 0 .35rem; font-size:.74rem; color:var(--ink); }
          .gd .nothing{ display:grid; place-items:center; min-height:4.2rem; border:1px dashed var(--err); border-radius:8px; color:var(--err); font-weight:700; font-size:.7rem; background:var(--err-wash); }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; }
            .gd .two, .gd .vs{ grid-template-columns:1fr; } .gd .docs{ grid-template-columns:1fr 1fr; }
            .gd .sc{ min-height:430px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / quoting</span></div>
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
                  ' . $tabs('Quoting') . '
                  <div class="sh">Bank details for customer payments</div>
                  <span class="hn">Printed on the customer&rsquo;s quote / invoice so they can pay by bank transfer. Leave blank to hide the &ldquo;How to pay&rdquo; block entirely.</span>
                  ' . $howto() . '
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; eight short chapters.</p>
                </div>

                <!-- 1 — where it lives -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  ' . $tabs('Quoting') . '
                  <div class="row" style="flex-direction:column;align-items:flex-start;gap:.35rem">
                    <span class="chip a-fly" style="--d:1.5s">Default margins</span>
                    <span class="chip a-fly" style="--d:2.1s">Measurements</span>
                    <span class="chip a-fly" style="--d:2.7s">Quote defaults</span>
                  </div>
                  <div class="a-rise mt" style="--d:5s">
                    <div class="sh a-ring" style="--d:6s;display:inline-block;border-radius:6px;padding:0 .2rem">Bank details for customer payments</div>
                    <span class="hn">Printed on the customer&rsquo;s quote / invoice so they can pay by bank transfer. Leave blank to hide the &ldquo;How to pay&rdquo; block entirely.</span>
                    <div class="form">
                      ' . $fld('Account name', 'e.g. Beverley Blinds Ltd') . '
                      <div class="two">' . $fld('Sort code', '00-00-00') . $fld('Account number', '12345678') . '</div>
                      ' . $fld('Payment note (optional)', 'e.g. Please use your quote number as the reference') . '
                    </div>
                    <div class="mt"><span class="btnp a-ring" style="--d:16.5s">Save bank details</span>
                      <span class="chip a-pop" style="--d:17.5s;margin-left:.4rem">Saves this section only</span></div>
                  </div>
                </div>

                <!-- 2 — account name -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Account name &mdash; exactly as your bank holds it</div>
                  <div class="form" style="margin-top:.6rem">
                    ' . $fld('Account name', 'e.g. Beverley Blinds Ltd', 'Bright Blinds Ltd', [3, 17, 1.4], 'on2') . '
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:9s">&#128241; The customer copies it into their banking app</span>
                  </div>
                  <div class="row mt">
                    <span class="chip bad a-pop" style="--d:14s">&#9888; Name doesn&rsquo;t match? Their bank may warn them</span>
                  </div>
                </div>

                <!-- 3 — sort code + account number -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">Sort code and account number</div>
                  <div class="form" style="margin-top:.6rem">
                    <div class="two">
                      ' . $fld('Sort code', '00-00-00', '20-00-00', [2.5, 8, .8], 'on2') . '
                      ' . $fld('Account number', '12345678', '12345678', [5, 8, .8], 'on2') . '
                    </div>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:8s">Nothing here is checked</span>
                    <span class="chip bad a-pop" style="--d:11s">A typo goes out on every quote</span>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:15s">&#128196; Copy from a bank statement &middot; read it back once</span></div>
                </div>

                <!-- 4 — payment note -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">The payment note &mdash; optional, one line</div>
                  <div class="form" style="margin-top:.6rem">
                    ' . $fld('Payment note (optional)', 'e.g. Please use your quote number as the reference', 'Bank transfer only, please.', [5.5, 27, 1.6], 'on2') . '
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:10s">No need to ask for a reference &hellip;</span>
                    <span class="chip a-pop" style="--d:13s">&hellip; the quote number is added for you</span>
                  </div>
                  <div class="mt a-rise" style="--d:14.5s">' . $howto(['name', 'sort', 'acc', 'note'], 'ref a-ring', '--d:15.5s') . '</div>
                </div>

                <!-- 5 — save -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="bnr a-drop" style="--d:6.5s">&#10003; Bank / payment details saved.</div>
                  <div class="form">
                    ' . $fld('Account name', '', 'Bright Blinds Ltd') . '
                    <div class="two">' . $fld('Sort code', '', '20-00-00') . $fld('Account number', '', '12345678') . '</div>
                    ' . $fld('Payment note (optional)', '', 'Bank transfer only, please.') . '
                  </div>
                  <div class="mt"><span class="btnp a-press" style="--d:4.5s">Save bank details</span></div>
                  <div class="a-move" style="--fx:80%;--fy:95%;--tx:5.5rem;--ty:12.4rem;--d:2.5s;--md:1.8s">' . $ptr . '</div>
                  <div class="row mt"><span class="chip a-pop" style="--d:13s">&#8593; The green bar is at the top &mdash; scroll up</span></div>
                </div>

                <!-- 6 — what the customer sees -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">What the customer sees</div>
                  <div class="pay a-rise" style="--d:2s">
                    <b class="payh">How to pay &mdash; bank transfer</b>
                    <div class="a-fly" style="--d:6s">Account name: <b>Bright Blinds Ltd</b></div>
                    <div class="a-fly" style="--d:7s">Sort code: <b>20-00-00</b></div>
                    <div class="a-fly" style="--d:8s">Account number: <b>12345678</b></div>
                    <div class="g a-fly" style="--d:10s">Bank transfer only, please.</div>
                    <div class="g"><span class="ref a-pop" style="--d:13s">Please use <b>BRI-2026-0042</b> as your payment reference.</span></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:18s">&#127974; Money arrives with <b>BRI-2026-0042</b> on it</span>
                    <span class="arrow a-fade" style="--d:20s">&rarr;</span>
                    <span class="chip a-pop" style="--d:21s;border-color:var(--good)">Matched to the right job</span>
                  </div>
                </div>

                <!-- 7 — where it appears -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">Where the box appears</div>
                  <div class="docs" style="margin-top:.6rem">
                    <div class="doc a-drop" style="--d:2s"><b>Online quote</b><i></i><i></i><span style="color:#166534">Deposit card</span><em>How to pay</em></div>
                    <div class="doc a-drop" style="--d:6s"><b>Quote PDF</b><i></i><i></i><i></i><em>How to pay</em></div>
                    <div class="doc a-drop" style="--d:9s"><b>Invoice</b><i></i><i></i><i></i><em>How to pay</em></div>
                    <div class="doc a-drop" style="--d:10s"><b>Receipt</b><i></i><i></i><i></i><em>How to pay</em></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:11.5s">Same document &mdash; same box</span>
                    <span class="chip a-pop" style="--d:14s">&#8635; Change it here &rarr; the next document picks it up</span>
                  </div>
                </div>

                <!-- 8 — what switches it on -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">What switches the box on</div>
                  <div class="vs" style="margin-top:.6rem">
                    <div class="case a-rise" style="--d:3s"><h4>&#10003; Account name or account number filled in</h4>' . $howto(['name', 'acc']) . '</div>
                    <div class="case a-rise" style="--d:8s"><h4>&#10007; Only a sort code, or only a note</h4><div class="nothing a-pop" style="--d:10s">Nothing shows</div></div>
                  </div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:15s">To switch it off: clear all four boxes &rarr; Save bank details</span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Settings</b> &rarr; the <b>Quoting</b> tab &rarr; scroll to the very bottom, past <b>Default margins</b>,
             <b>Measurements</b> and <b>Quote defaults</b>, to <b>Bank details for customer payments</b>. It is a small form of its own with
             its own <b>Save bank details</b> button &mdash; saving it doesn&rsquo;t touch the other sections, and saving those doesn&rsquo;t
             touch this. The grey line under the heading says: <em>&ldquo;Printed on the customer&rsquo;s quote / invoice so they can pay by
             bank transfer. Leave blank to hide the &lsquo;How to pay&rsquo; block entirely.&rdquo;</em> (Compact mode hides that line.)</p>

          <ul class="steps">
            <li><b>Account name</b> &mdash; up to 120 characters (placeholder <code>e.g. Beverley Blinds Ltd</code>). Type it exactly as your bank
                holds it; customers copy it into their banking app.</li>
            <li><b>Sort code</b> (left, placeholder <code>00-00-00</code>) and <b>Account number</b> (right, placeholder <code>12345678</code>).
                Printed exactly as you type them, dashes or not.</li>
            <li><b>Payment note (optional)</b> &mdash; one line, up to 500 characters (placeholder
                <code>e.g. Please use your quote number as the reference</code>). Use it for something the customer needs, such as
                &ldquo;Bank transfer only&rdquo;. The reference line is added automatically, so you don&rsquo;t need to ask for it here.</li>
            <li><b>Save bank details</b> &mdash; the page reloads and a green <b>&ldquo;Bank / payment details saved.&rdquo;</b> appears at the
                <b>top</b> of the page. If you see <code>Could not save bank details &mdash; run /migrate_bank_details.php first.</code>, the
                database needs a one-off update: pass it to whoever looks after your system.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Nothing is checked.</b> There is no format check and nothing is required &mdash;
             a mistyped account number saves happily and then goes out on every document. Copy the numbers from a bank statement, read them
             back, and send yourself a test quote.</div></div>

          <p><b>What the customer sees.</b> A box headed <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> with <b>Account name:</b>,
             <b>Sort code:</b> and <b>Account number:</b> (each line only if filled in), your note in grey, and always, last:
             <b>&ldquo;Please use BRI-2026-0042 as your payment reference.&rdquo;</b> &mdash; that document&rsquo;s own quote number.</p>
          <ul class="steps">
            <li><b>The online quote</b> &mdash; the box sits just under the deposit card.</li>
            <li><b>The quote PDF</b> &mdash; after the Notes, before the Terms &amp; Conditions links.</li>
            <li><b>The invoice and the receipt</b> &mdash; made from the same document, so the same box in the same place. The invoice email
                says <em>&ldquo;Payment details are on the invoice.&rdquo;</em></li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>What switches the box on.</b> It appears only when <b>Account name</b> or
             <b>Account number</b> has something in it. A sort code on its own, or a note on its own, prints nothing. To switch the box off
             (if you&rsquo;d rather customers rang to pay), clear <b>all four</b> boxes and save.</div></div>

          <p class="prose"><b>Trade paperwork.</b> The factory&rsquo;s <b>wholesale invoice</b> prints a block headed <b>Payment</b>
             (<b>Account:</b> / <b>Sort code:</b> / <b>Account no:</b>, then the note) &mdash; but it reads the <em>factory&rsquo;s</em> own
             four boxes, not yours. Credit notes and account statements print no payment block. And the <b>bank account</b> you map on the
             <b>Accounting</b> tab is a ledger account in your accounts package &mdash; a different thing that doesn&rsquo;t change what prints
             here.</p>',
        'script'  => $script,
];
