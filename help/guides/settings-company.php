<?php
declare(strict_types=1);

/**
 * Guide: settings-company — "Your company details" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the "Company details" section on the Company tab of
 * /admin/settings.php (the ten boxes in their real rows, the Save button and
 * the "Company details saved." flash), the sidebar's who-you-are block
 * (_partials/sidebar.php .app-sidebar-user), the letterhead built by
 * pdf-generator/pdf.php (name, address lines, "town postcode", county, phone,
 * email, "VAT No.") and the public quote page quote-history/public.php (same
 * block, stops at the email). The company-name fallback is the save handler's
 * `?: $user['company_name']`.
 *
 * v2: one SCENE per script line (data-scene = the line's step); data-len is
 * worked out from the line itself (characters ÷ 13.6).
 */

$script = [
    ['1',  'What these details are for',   'Your company details are your letterhead. Your name, your address, and how to reach you, are printed at the top of every quote, every invoice and every receipt you send. Your customer sees them on the quote page they open on their phone, tablet or computer, too. Fill them in once, properly, and everything that leaves the system looks like it came from you.', 1],
    ['2',  'Getting there',                'To get there, look at the menu down the left. Click Setup to open that section, and then click Settings. A row of seven tabs runs along the top of the page. Click Company, the first one. The first panel on it is Company details. It is just ten plain typing boxes. Nothing to tick, and nothing to pick from a list.', 2],
    ['3',  'Company name',                 'Start at the top, with Company name. It has a little red star, which means it must be filled in. This is the name that heads every quote, invoice and receipt, and it sits at the top of your terms and privacy pages too. So type it exactly as you want customers to read it.', 3],
    ['4',  'Contact name, email and phone','Next to it is Contact name. That is the real person a customer asks for when they ring. Underneath are Email and Phone. These two are printed on your paperwork, so the customer knows how to get hold of you. On a phone or a tablet, the Phone box brings up the number keypad, which makes it quicker to type.', 4],
    ['5',  'The email is printed, not the sender', 'One thing about that Email box. It is the address printed on the page. It is not the address your quote emails are sent from. That is set on the Quoting tab, under Email from name, and Reply-to email. So changing the box here will not change who your quote emails seem to come from.', 5],
    ['6',  'VAT number',                   'Next is VAT number. The screen tells you itself: leave it blank if your business is not VAT registered. If you are registered, type it in. It then prints as VAT number, under your phone and email, on every quote PDF, and on your invoices and receipts too. Leave it empty, and that line simply is not there.', 6],
    ['7',  'Your address',                 'Now your address. Address line one has a whole row to itself. Put the unit or the building first. Address line two sits on its own row underneath, for the estate or the street. Do not need line two? Leave it empty. Empty boxes are dropped when the address is printed, so you never get a gap. There is no need to type N A, or a dash.', 7],
    ['8',  'Town, county and postcode',    'The last three boxes sit side by side: Town, County and Postcode. Just put each one in its own box. When your letterhead is printed, the town and the postcode are joined together on one line, with the county underneath. You do not have to arrange anything. The system lays it out for you.', 8],
    ['9',  'Save, and check it took',      'When you have finished, click Save company details, at the bottom. The page reloads, and a green bar across the top says, Company details saved. Now look at the top of the menu on the left. Under your own name, it shows your company name and your role. That company name changes as soon as you save. That is how you know it went in.', 9],
    ['10', 'You cannot blank the name',    'One thing that catches people out. You cannot empty the company name. Clear the box and save, and the old name quietly comes back, with the same green message. The system would rather keep the name it has than send out a quote with no name on it. So to change it, simply type the new name straight over the top.', 10],
    ['11', 'Where it all ends up',         'And here is where it all ends up. On your quote, invoice and receipt PDFs, it sits at the top left: your name, your address, then phone, email and VAT number. The quote page your customer opens online shows the same block, but it stops at your email, so the VAT number is only on the PDF. Set it once, and come back if you move, or register for VAT.', 11],
];
$L = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The ten boxes, in the real rows. $v = what is in each box (HTML); $c = extra
// class per box (e.g. a ring). Keys: cn ct em ph vat a1 a2 tw co pc.
$VAL = ['cn' => 'Demo Blinds Ltd', 'ct' => 'Sarah Jones', 'em' => 'hello@demoblinds.co.uk', 'ph' => '01632 960123',
        'vat' => 'GB123456789', 'a1' => 'Unit 4, Mill Court', 'a2' => 'Riverside Estate', 'tw' => 'Kendal', 'co' => 'Cumbria', 'pc' => 'LA9 6AB'];
$form = static function (array $v = [], array $c = [], string $foot = '') use ($VAL): string {
    $box = static function (string $k, string $label) use ($v, $c, $VAL): string {
        $in = array_key_exists($k, $v) ? $v[$k] : $VAL[$k];
        return '<div class="fg"><span class="lab">' . $label . '</span><div class="inp ' . ($c[$k] ?? '') . '">' . $in . '</div></div>';
    };
    return '<div class="secbox"><div class="sech">Company details</div>'
         . '<div class="r r2">' . $box('cn', 'Company name <span class="req">*</span>') . $box('ct', 'Contact name') . '</div>'
         . '<div class="r r2">' . $box('em', 'Email') . $box('ph', 'Phone') . '</div>'
         . '<div class="r">' . $box('vat', 'VAT number') . '</div>'
         . '<div class="r">' . $box('a1', 'Address line 1') . '</div>'
         . '<div class="r">' . $box('a2', 'Address line 2') . '</div>'
         . '<div class="r r3">' . $box('tw', 'Town') . $box('co', 'County') . $box('pc', 'Postcode') . '</div>'
         . $foot . '</div>';
};
$empty = array_fill_keys(array_keys($VAL), '');
$empty['vat'] = '<span class="ph">e.g. GB123456789</span>';
$type = static fn (string $txt, float $d, int $steps = 16, float $tt = 1.0): string =>
    '<span class="a-type" style="--d:' . $d . 's;--ts:' . $steps . ';--tt:' . $tt . 's">' . $txt . '</span>';

// The printed letterhead (pdf.php order). $hl = lines to animate in: key => [class, delay].
$paper = static function (array $hl = [], bool $vat = true, string $extra = '') use ($VAL): string {
    $line = static function (string $k, string $txt) use ($hl): string {
        if (!isset($hl[$k])) return '<div>' . $txt . '</div>';
        return '<div class="' . $hl[$k][0] . '" style="--d:' . $hl[$k][1] . 's">' . $txt . '</div>';
    };
    return '<div class="paper ' . $extra . '"><div class="plogo">DB</div>'
         . $line('cn', '<b class="pn">' . $VAL['cn'] . '</b>')
         . $line('a1', $VAL['a1']) . $line('a2', $VAL['a2'])
         . $line('tp', $VAL['tw'] . ' ' . $VAL['pc']) . $line('co', $VAL['co'])
         . $line('ph', $VAL['ph']) . $line('em', $VAL['em'])
         . ($vat ? $line('vat', 'VAT No. ' . $VAL['vat']) : '')
         . '</div>';
};

$head = '<div class="ph1">Settings</div><div class="ph2">Company details and per-quote defaults.</div>';
$tabs = static fn (string $ring = '') => '<div class="tabs"><span class="tab on ' . $ring . '">Company</span><span class="tab">Quoting</span><span class="tab">Legal</span>'
      . '<span class="tab">Status colours</span><span class="tab">Suppliers</span><span class="tab">Accounting</span><span class="tab">Back up data</span></div>';
$saveBtn = static fn (string $cls = '', string $st = '', string $more = '') => '<div class="fact"><span class="btnp ' . $cls . '" style="' . $st . '">Save company details</span>' . $more . '</div>';

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company details',
        'eyebrow' => 'Settings · Company',
        'v'       => 2,
        'blurb'   => 'All ten boxes on the Company tab, in order — and the letterhead they build on every quote, invoice and legal page.',
        'lede'    => 'These ten boxes are your <b>letterhead</b>. Your name, your contact details, your VAT number and your
                      address are printed at the top-left of every quote, invoice and receipt PDF you send &mdash; and all
                      of it bar the VAT line on the quote page your customer opens on their phone. This guide goes through
                      every box <b>slowly</b>, one at a time, in the order you meet them. To get there: <b>Setup</b> &rarr;
                      <b>Settings</b> &rarr; the <b>Company</b> tab.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* the settings page */
          .gd .ph1{ font-size:.98rem; font-weight:800; color:var(--ink); }
          .gd .ph2{ font-size:.66rem; color:var(--faint); margin:.05rem 0 .55rem; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin-bottom:.65rem; }
          .gd .tab{ font-size:.64rem; font-weight:600; color:var(--faint); padding:.28rem .45rem; border:1px solid transparent; border-bottom:none;
                    border-radius:6px 6px 0 0; margin-bottom:-1px; white-space:nowrap; }
          .gd .tab.on{ color:var(--accent); background:var(--surface); border-color:var(--line); border-bottom-color:var(--surface); }
          .gd .secbox{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:27rem; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .45rem; }
          .gd .r{ display:grid; grid-template-columns:1fr; gap:.35rem .55rem; margin-bottom:.35rem; }
          .gd .r2{ grid-template-columns:1fr 1fr; } .gd .r3{ grid-template-columns:1fr 1fr 1fr; }
          .gd .fg{ min-width:0; }
          .gd .lab{ display:block; font-size:.6rem; font-weight:600; color:var(--soft); margin-bottom:.12rem; white-space:nowrap; }
          .gd .inp{ position:relative; display:grid; align-items:center; height:24px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                    background:var(--surface); padding:0 .45rem; font-size:.7rem; color:var(--ink); overflow:hidden; white-space:nowrap; }
          .gd .inp > span{ grid-area:1/1; }
          .gd .inp .ph{ color:var(--faint); }
          .gd .inp.focus{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .hintg{ font-size:.58rem; color:var(--faint); line-height:1.4; margin-top:.15rem; }
          .gd .fact{ margin-top:.5rem; position:relative; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; }
          .gd .flash{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); color:var(--good); font-weight:700;
                      font-size:.72rem; border-radius:8px; padding:.4rem .65rem; margin-bottom:.55rem; max-width:27rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.good{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.7rem; }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
          .gd .split{ display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:.9rem; align-items:start; }
          .gd .side2{ display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; min-width:0; }
          .gd .side2 .chips{ margin-top:0; }

          /* paper: the printed letterhead */
          .gd .paper{ background:#fff; color:#374151; border:1px solid #e5e7eb; border-radius:6px; padding:.55rem .65rem; font-size:.62rem; line-height:1.5;
                      box-shadow:var(--gd-shadow); max-width:15rem; }
          .gd .paper .pn{ font-size:.8rem; color:#111827; }
          .gd .plogo{ width:2.4rem; height:1.3rem; border-radius:4px; background:linear-gradient(135deg,#2563eb,#60a5fa); color:#fff; font-weight:800;
                      font-size:.62rem; display:grid; place-items:center; margin-bottom:.3rem; }
          .gd .ptag{ font-size:.58rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--faint); margin-bottom:.25rem; }
          .gd .mark{ background:#fef3c7; border-radius:3px; padding:0 .15rem; }
          .gd .phone{ border:2px solid #334155; border-radius:16px; padding:.5rem .45rem .7rem; background:#fff; max-width:11rem; }
          .gd .phone .paper{ box-shadow:none; border:0; padding:.2rem .3rem; }
          .gd .phone .bar{ width:2.2rem; height:.25rem; border-radius:9px; background:#cbd5e1; margin:0 auto .35rem; }

          /* little pictures */
          .gd .bubble{ display:inline-block; background:var(--panel); border:1px solid var(--line); border-radius:12px 12px 12px 3px; padding:.35rem .6rem;
                       font-size:.7rem; color:var(--ink); }
          .gd .keypad{ display:inline-grid; grid-template-columns:repeat(3,1.5rem); gap:.2rem; padding:.35rem; border-radius:9px; background:var(--panel); border:1px solid var(--line); }
          .gd .keypad span{ display:grid; place-items:center; height:1.2rem; border-radius:5px; background:var(--surface); border:1px solid var(--line); font-size:.62rem; font-weight:700; color:var(--ink); }
          .gd .mini{ border:1px solid var(--line); border-radius:9px; padding:.5rem .6rem; background:var(--surface); font-size:.66rem; color:var(--soft); }
          .gd .mini h4{ margin:0 0 .35rem; font-size:.72rem; color:var(--ink); }
          .gd .mini .lab{ margin-top:.3rem; }
          .gd .navuser{ background:#1f2a37; border-radius:9px; padding:.55rem .7rem; max-width:13rem; }
          .gd .navuser .nm{ font-size:.74rem; font-weight:700; color:#fff; }
          .gd .navuser .meta{ display:grid; font-size:.66rem; color:#8fa3b3; margin-top:.1rem; }
          .gd .navuser .meta > span{ grid-area:1/1; }
          .gd .navuser .meta b{ color:#fff; }
          .gd .logoside{ font-weight:800; color:#fff; font-size:.8rem; margin-bottom:.35rem; } .gd .logoside b{ color:#5b9bff; }
          .gd .legal{ border:1px solid #e5e7eb; background:#fff; color:#374151; border-radius:6px; padding:.5rem .6rem; font-size:.6rem; max-width:15rem; box-shadow:var(--gd-shadow); }
          .gd .legal b{ display:block; font-size:.74rem; color:#111827; }
          .gd .legal i{ display:block; font-style:normal; font-size:.8rem; font-weight:800; color:#111827; margin:.15rem 0 .2rem; }
          .gd .ten{ font-size:1.6rem; font-weight:800; color:var(--accent); line-height:1; }

          @media (max-width:640px){
            .gd .split{ grid-template-columns:1fr; }
            .gd .sc{ min-height:430px; }
            .gd .r3{ grid-template-columns:1fr 1fr; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / company</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  ' . $head . $tabs() . $form() . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; eleven short chapters, one box at a time.</p>
                </div>

                <!-- 1 — what it is for -->
                <div class="sc" data-scene="1" data-len="' . $L(1) . '">
                  <div class="sct a-fade" style="--d:.2s">Your letterhead</div>
                  <p class="scs a-fade" style="--d:.5s">Ten boxes in Settings &rarr; printed on everything you send.</p>
                  <div class="split">
                    <div class="paper a-rise" style="--d:1s;max-width:17rem">
                      <div class="plogo a-pop" style="--d:2s">DB</div>
                      <div class="pn" style="font-weight:800">' . $type('Demo Blinds Ltd', 3.2, 15, .9) . '</div>
                      <div class="a-fly" style="--d:5s">Unit 4, Mill Court</div>
                      <div class="a-fly" style="--d:5.4s">Riverside Estate</div>
                      <div class="a-fly" style="--d:5.8s">Kendal LA9 6AB</div>
                      <div class="a-fly" style="--d:6.2s">Cumbria</div>
                      <div class="a-fly" style="--d:7.3s">01632 960123</div>
                      <div class="a-fly" style="--d:7.7s">hello@demoblinds.co.uk</div>
                    </div>
                    <div class="side2">
                      <div class="chips" style="margin-top:0">
                        <span class="chip a-pop" style="--d:9.5s">&#128196; Quote</span>
                        <span class="chip a-pop" style="--d:10.5s">&#128196; Invoice</span>
                        <span class="chip a-pop" style="--d:11.5s">&#128196; Receipt</span>
                      </div>
                      <div class="chips"><span class="chip a-pop" style="--d:13.5s">&#128241; The quote page on their phone</span></div>
                      <div class="chips"><span class="chip good a-pop" style="--d:20s">&#10003; Fill in once &mdash; used everywhere</span></div>
                    </div>
                  </div>
                </div>

                <!-- 2 — getting there -->
                <div class="sc" data-scene="2" data-len="' . $L(2) . '">
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:2.5s">Setup</span><span class="arrow a-fade" style="--d:3.6s">&rarr;</span>
                    <span class="chip a-pop" style="--d:4.6s">Settings</span><span class="arrow a-fade" style="--d:6s">&rarr;</span>
                    <span class="chip a-pop" style="--d:10.5s;border-color:var(--accent);color:var(--accent)">Company</span>
                  </div>
                  <div class="a-rise" style="--d:6.5s">' . $head . '
                    <div class="a-fade" style="--d:7.5s">' . $tabs('a-ring" style="--d:10.5s') . '</div></div>
                  <div class="split">
                    <div class="a-rise" style="--d:14s">' . $form($empty) . '</div>
                    <div class="side2">
                      <span class="chip a-pop" style="--d:17.5s">Ten plain typing boxes</span>
                      <span class="chip a-pop" style="--d:20.5s">Nothing to tick &middot; nothing to pick</span>
                    </div>
                  </div>
                </div>

                <!-- 3 — company name -->
                <div class="sc" data-scene="3" data-len="' . $L(3) . '">
                  <div class="split">
                    <div>' . $form(['cn' => $type('Demo Blinds Ltd', 13, 15, 1)] + $empty, ['cn' => 'focus a-ring" style="--d:1.5s']) . '</div>
                    <div class="side2">
                      <span class="chip a-pop" style="--d:4s"><span class="req">*</span> must be filled in</span>
                      <div class="paper a-rise" style="--d:7s"><div class="ptag">Quote PDF</div><b class="pn mark">Demo Blinds Ltd</b><div>Unit 4, Mill Court &hellip;</div></div>
                      <div class="legal a-rise" style="--d:10.5s"><b class="mark">Demo Blinds Ltd</b><i>Terms &amp; Conditions</i>1. These terms apply to &hellip;</div>
                      <span class="chip good a-pop" style="--d:16.5s">Type it as customers should read it</span>
                    </div>
                  </div>
                </div>

                <!-- 4 — contact name, email, phone -->
                <div class="sc" data-scene="4" data-len="' . $L(4) . '">
                  <div class="split">
                    <div>' . $form(['cn' => $VAL['cn'], 'ct' => $type('Sarah Jones', 2.5, 11, .8), 'em' => $type('hello@demoblinds.co.uk', 9, 22, 1.2), 'ph' => $type('01632 960123', 10.8, 12, .9)] + $empty,
                                   ['ct' => 'focus a-ring" style="--d:1.2s', 'em' => 'a-ring" style="--d:8.5s', 'ph' => 'a-ring" style="--d:10.5s']) . '</div>
                    <div class="side2">
                      <span class="bubble a-pop" style="--d:5s">&#9742; &ldquo;Can I speak to Sarah, please?&rdquo;</span>
                      <div class="paper a-rise" style="--d:12.5s"><b class="pn">Demo Blinds Ltd</b><div class="mark">01632 960123</div><div class="mark">hello@demoblinds.co.uk</div></div>
                      <div class="a-pop" style="--d:17s"><div class="keypad"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span><span>8</span><span>9</span></div>
                        <div class="hintg">Phone box &rarr; number keypad</div></div>
                    </div>
                  </div>
                </div>

                <!-- 5 — email: printed, not the sender -->
                <div class="sc" data-scene="5" data-len="' . $L(5) . '">
                  <div class="sct a-fade" style="--d:.2s">Printed on the page &ne; who emails come from</div>
                  <div class="split" style="margin-top:.6rem">
                    <div class="mini a-rise" style="--d:1.5s"><h4>Company tab &rarr; Email</h4>
                      <div class="inp focus">hello@demoblinds.co.uk</div>
                      <div class="chips"><span class="chip good a-pop" style="--d:4s">&#10003; printed on your paperwork</span></div></div>
                    <div class="mini a-rise" style="--d:8s"><h4>Quoting tab</h4>
                      <span class="lab">Email &quot;from&quot; name</span><div class="inp a-ring" style="--d:11s">Demo Blinds</div>
                      <span class="lab">Reply-to email</span><div class="inp a-ring" style="--d:13s">sales@demoblinds.co.uk</div>
                      <div class="chips"><span class="chip a-pop" style="--d:13.5s">&#9993; who your quote emails come from</span></div></div>
                  </div>
                  <div class="chips"><span class="chip bad a-pop" style="--d:16.5s">Changing the Company tab email won&rsquo;t change the sender</span></div>
                </div>

                <!-- 6 — VAT number -->
                <div class="sc" data-scene="6" data-len="' . $L(6) . '">
                  <div class="split">
                    <div>
                      <div class="secbox">
                        <div class="sech">Company details</div>
                        <span class="lab">VAT number</span>
                        <div class="inp focus a-ring" style="--d:1s"><span class="ph a-out" style="--d:9.5s">e.g. GB123456789</span>' . $type('GB123456789', 10, 11, .8) . '</div>
                        <div class="hintg a-fade" style="--d:3s">Leave blank if your business isn&rsquo;t VAT-registered. When set, it appears below your contact details on every quote PDF.</div>
                      </div>
                    </div>
                    <div class="side2">
                      ' . $paper(['vat' => ['mark a-pop', 14]], true, 'a-rise" style="--d:12s') . '
                      <div class="chips" style="margin-top:.5rem"><span class="chip a-pop" style="--d:17s">Quote</span><span class="chip a-pop" style="--d:17.5s">Invoice</span><span class="chip a-pop" style="--d:18s">Receipt</span></div>
                      <span class="chip a-pop" style="--d:20.5s">Empty box &rarr; no VAT line at all</span>
                    </div>
                  </div>
                </div>

                <!-- 7 — address lines -->
                <div class="sc" data-scene="7" data-len="' . $L(7) . '">
                  <div class="split">
                    <div>' . $form(['a1' => $type('Unit 4, Mill Court', 3.5, 18, 1), 'a2' => $type('Riverside Estate', 7.5, 16, 1), 'tw' => '', 'co' => '', 'pc' => ''],
                                   ['a1' => 'focus a-ring" style="--d:2s', 'a2' => 'a-ring" style="--d:6.5s']) . '</div>
                    <div class="side2">
                      <div class="mini a-rise" style="--d:12s"><h4>Line 2 left empty?</h4>
                        <div class="paper" style="box-shadow:none"><b class="pn">Demo Blinds Ltd</b><div>Unit 4, Mill Court</div><div class="a-fade" style="--d:15s">Kendal LA9 6AB</div><div class="a-fade" style="--d:15.3s">Cumbria</div></div>
                        <div class="chips"><span class="chip good a-pop" style="--d:16s">&#10003; no gap</span></div></div>
                      <span class="chip bad a-pop" style="--d:20s">&#10007; Don&rsquo;t type N/A or a dash</span>
                    </div>
                  </div>
                </div>

                <!-- 8 — town, county, postcode -->
                <div class="sc" data-scene="8" data-len="' . $L(8) . '">
                  <div class="split">
                    <div>' . $form(['tw' => $type('Kendal', 3, 6, .5), 'co' => $type('Cumbria', 3.8, 7, .5), 'pc' => $type('LA9 6AB', 4.6, 7, .5)],
                                   ['tw' => 'a-ring" style="--d:2.5s', 'co' => 'a-ring" style="--d:3.3s', 'pc' => 'a-ring" style="--d:4.1s']) . '</div>
                    <div class="side2">
                      ' . $paper(['tp' => ['mark a-pop', 10], 'co' => ['mark a-pop', 12.5]], true, 'a-rise" style="--d:8s') . '
                      <div class="chips"><span class="chip a-pop" style="--d:10.5s">Town + postcode on one line</span><span class="chip a-pop" style="--d:12.8s">County underneath</span></div>
                      <span class="chip good a-pop" style="--d:17s">&#10003; laid out for you</span>
                    </div>
                  </div>
                </div>

                <!-- 9 — save -->
                <div class="sc" data-scene="9" data-len="' . $L(9) . '">
                  <div class="flash a-pop" style="--d:6s">Company details saved.</div>
                  <div class="split">
                    <div>' . $form([], [], $saveBtn('a-press a-ring', '--d:3.5s', '<div class="a-move" style="--fx:14rem;--fy:-7rem;--tx:6rem;--ty:.7rem;--d:1s;--md:2s">' . $ptr . '</div>')) . '</div>
                    <div class="side2">
                      <div class="navuser a-rise" style="--d:10s"><div class="logoside">Your<b>Blinds</b></div><div class="nm">Sarah Jones</div>
                        <div class="meta"><span class="a-out" style="--d:17s">Demo Blinds &middot; admin</span><span class="a-fade" style="--d:17s"><b>Demo Blinds Ltd</b> &middot; admin</span></div></div>
                      <span class="chip a-pop" style="--d:13s">Top of the menu: your name, then company &middot; role</span>
                      <span class="chip good a-pop" style="--d:21s">&#10003; changed &mdash; it went in</span>
                    </div>
                  </div>
                </div>

                <!-- 10 — cannot blank the name -->
                <div class="sc" data-scene="10" data-len="' . $L(10) . '">
                  <div class="sct a-fade" style="--d:.2s">The company name can&rsquo;t be emptied</div>
                  <div class="secbox a-rise" style="--d:1s;max-width:22rem;margin-top:.5rem">
                    <span class="lab">Company name <span class="req">*</span></span>
                    <div class="inp focus"><span class="a-out" style="--d:4s">Demo Blinds Ltd</span><span class="a-fade" style="--d:10s">Demo Blinds Ltd</span></div>
                    ' . $saveBtn('a-press', '--d:6.5s') . '
                  </div>
                  <div class="flash a-pop" style="--d:8s;margin-top:.6rem;max-width:22rem">Company details saved.</div>
                  <div class="chips"><span class="chip a-pop" style="--d:10.5s">&#8635; the old name came back</span></div>
                  <div class="secbox a-rise" style="--d:18.5s;max-width:22rem;margin-top:.7rem">
                    <span class="lab">Company name <span class="req">*</span></span>
                    <div class="inp focus">' . $type('Demo Blinds &amp; Shutters Ltd', 19.5, 28, 1.4) . '</div>
                    <div class="hintg">Type the new name over the top.</div>
                  </div>
                </div>

                <!-- 11 — where it ends up -->
                <div class="sc" data-scene="11" data-len="' . $L(11) . '">
                  <div class="split">
                    <div><div class="ptag a-fade" style="--d:1s">Quote, invoice &amp; receipt PDF</div>
                      ' . $paper(['cn' => ['a-fly', 2.5], 'a1' => ['a-fly', 3.5], 'a2' => ['a-fly', 3.8], 'tp' => ['a-fly', 4.1], 'co' => ['a-fly', 4.4],
                                  'ph' => ['a-fly', 5.5], 'em' => ['a-fly', 5.9], 'vat' => ['mark a-pop', 7]], true, 'a-rise" style="--d:1.5s') . '</div>
                    <div><div class="ptag a-fade" style="--d:10s">Online quote page</div>
                      <div class="phone a-rise" style="--d:10.5s"><div class="bar"></div>' . $paper([], false) . '</div>
                      <div class="chips"><span class="chip a-pop" style="--d:15s">Stops at the email &mdash; no VAT line</span></div></div>
                  </div>
                  <div class="chips"><span class="chip good a-pop" style="--d:21s">Come back if you move, or register for VAT</span></div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting there.</b> The menu down the left-hand side comes in sections &mdash; <b>Work</b>, <b>Retail</b>,
             <b>Trade</b>, <b>Setup</b> and <b>Platform</b> &mdash; and <b>Setup</b> starts <em>closed</em> unless you are
             already on one of its pages. Click the word <b>Setup</b> to open it, then click <b>Settings</b>. The page is headed
             <b>Settings</b>, with &ldquo;Company details and per-quote defaults.&rdquo; underneath, and a row of seven tabs:
             <b>Company</b> &middot; Quoting &middot; Legal &middot; Status colours &middot; Suppliers &middot; Accounting &middot;
             Back up data. Settings remembers the tab you used last, so if another one is showing, click <b>Company</b>.
             The first panel on it is <b>Company details</b> &mdash; ten plain typing boxes, nothing to tick, nothing to choose
             from a list.</p>

          <p><b>Work down the form.</b> They are in this order on screen:</p>
          <ul class="steps">
            <li><b>Company name</b> <span class="req">*</span> &mdash; the name that heads every quote, invoice and receipt; it
                sits at the top of your public terms and privacy pages, above the document&rsquo;s own heading, and makes up the
                second half of those pages&rsquo; browser-tab title. Type it exactly as you want customers to read it. Up to
                150 characters.</li>
            <li><b>Contact name</b> &mdash; beside it: the real person a customer asks for when they ring. Up to 150.</li>
            <li><b>Email</b> &mdash; your <em>printed</em> email address, on the paperwork. It is <b>not</b> the address your
                quotes are sent <em>from</em>: that is on the <b>Quoting</b> tab, under <b>Email &quot;from&quot; name</b> and
                <b>Reply-to email</b>. Filling this box in won&rsquo;t change who your quote emails appear to come from.</li>
            <li><b>Phone</b> &mdash; printed alongside the email. Up to 50 characters. It is a telephone box, so a phone or
                tablet pops up the number keypad for it.</li>
            <li><b>VAT number</b> &mdash; the only box with a faint example in it (<code>e.g. GB123456789</code>). The screen
                says: &ldquo;<em>Leave blank if your business isn&rsquo;t VAT-registered. When set, it appears below your contact
                details on every quote PDF.</em>&rdquo; Filled in, it prints as <b>VAT No.</b> under your phone and email on
                the quote, invoice and receipt PDFs. It is the one letterhead line that does <b>not</b> appear on the
                online quote page your customer opens &mdash; that page stops at your email address. Empty, the line simply
                isn&rsquo;t there. <em>(With <b>Compact mode</b> on, that grey advice line is hidden &mdash; the box works the
                same.)</em></li>
            <li><b>Address line 1</b> &mdash; full width, on its own row. Unit or building first. Up to 150.</li>
            <li><b>Address line 2</b> &mdash; its own row underneath. Estate, or street. Don&rsquo;t need it? Leave it empty
                &mdash; empty boxes are dropped when the address is printed, so you won&rsquo;t get a blank gap. Never type
                &ldquo;N/A&rdquo; or a dash into a box you don&rsquo;t use.</li>
            <li><b>Town</b>, <b>County</b>, <b>Postcode</b> &mdash; the last three, side by side (100, 100 and 20
                characters). On the printed letterhead the town and postcode are joined on one line, with the county under
                it &mdash; the system does that, just put each in its own box.</li>
          </ul>

          <p><b>Then save.</b> Click <b>Save company details</b>. The page reloads and a green bar appears above the tabs:
             <b>&ldquo;Company details saved.&rdquo;</b> Glance at the <b>top-left of the menu</b> as well &mdash; under the
             YourBlinds name there is your own name, and under that your company name and your role, like
             &ldquo;Demo Blinds Ltd &middot; admin&rdquo;. That company name changes the moment you save: that&rsquo;s your proof
             it went in.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>You can&rsquo;t empty the company name.</b> Clear that box,
             hit Save, and the old name quietly comes back &mdash; with the same green &ldquo;Company details saved.&rdquo;
             The system would sooner keep the name it has than let a quote out with no name on it. So to change it,
             <b>type the new name over the top</b> rather than clearing it first. Every other box <em>can</em> be emptied
             and saved blank.</div></div>

          <p><b>Where these details show up.</b></p>
          <ul class="steps">
            <li><b>Your quote, invoice and receipt PDFs</b> &mdash; the top-left letterhead: your logo, then the company name,
                the address, phone, email and <b>VAT No.</b></li>
            <li><b>The online quote page</b> your customer opens from their email &mdash; the same block, in the same order,
                <em>except</em> it ends at your email address. No VAT line. If a customer needs your VAT number, send the
                PDF.</li>
            <li><b>Your public terms and privacy pages</b> &mdash; your company name in bold at the top, with the document&rsquo;s
                heading under it (<b>Terms &amp; Conditions</b>, <b>Terms &amp; Conditions (Trade)</b> or <b>Privacy
                Policy</b>), and in the browser-tab title, as in &ldquo;Privacy Policy &middot; Demo Blinds Ltd&rdquo;.</li>
            <li><b>Your terms and acceptance email</b> &mdash; these use the placeholders <code>{{company_name}}</code>,
                <code>{{company_address}}</code>, <code>{{company_email}}</code> and <code>{{company_phone}}</code>, which are
                swapped for what you typed here. Both the retail and trade terms end with
                <em>&ldquo;Contact: {{company_name}}, {{company_address}} &mdash; {{company_email}} &mdash;
                {{company_phone}}.&rdquo;</em>, so a half-filled address shows as a broken sentence a customer can read. The
                <b>Legal</b> tab previews it with your real details &mdash; the quickest way to check.</li>
          </ul>

          <p>You&rsquo;ll set this up once and barely touch it again &mdash; come back if you move premises or register for
             VAT. Three more short jobs sit just below this panel on the same tab:
             <a href="/help/guide.php?g=settings-logo"><b>your company logo</b></a>, the
             <a href="/help/guide.php?g=settings-dashboard"><b>dashboard switch</b></a> and the
             <a href="/help/guide.php?g=settings-calendar"><b>calendar options</b></a>.</p>',
        'script'  => $script,
];
