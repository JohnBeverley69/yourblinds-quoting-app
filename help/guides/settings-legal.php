<?php
declare(strict_types=1);

/**
 * Guide: settings-legal — "Terms, trade terms & privacy" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors Settings → Legal (admin/settings.php, section "Terms & Conditions &
 * Privacy Policy", POST _action=legal): the four boxes (retail T&Cs, trade
 * T&Cs, Privacy Policy, Thank-you email), the {{placeholders}} and the live
 * previews (example customer Jane Smith, quote BEV-2026-0042), and "Save terms,
 * privacy & email" → "Terms, Privacy Policy and acceptance email saved."
 * Where it goes: pdf-generator/pdf.php LINKS to legal/view.php (signed link;
 * doc=trade for a quote raised for a trade account, else retail) instead of
 * printing the text; quote-history/public.php links /quote-history/terms.php
 * (retail wording) and shows the "I agree to the Terms & Conditions" tick only
 * while the retail box has text. Defaults: _partials/legal_text.php.
 *
 * v2: one scene per script line; data-len is worked out from the line's own
 * length (characters ÷ 13.6), so editing a line keeps its scene in step.
 */

$vo = [
    1  => ['Four ready-written boxes',
           'Your terms live in Settings, on the Legal tab. There are four boxes. Your retail terms, your trade terms, your privacy policy, and a thank-you email. The good news is that all four come ready-written. So your quotes are covered from day one. You are only changing the wording, not starting from nothing.'],
    2  => ['Placeholders and the preview',
           'Words in double curly brackets fill themselves in. Company name becomes your company\'s name, and customer name becomes the name on the quote. Your company details come from the Company tab, so fill that in first. Under every box there is a preview. It redraws as you type, using an example customer, Jane Smith, so you can see exactly how it reads.'],
    3  => ['Your retail terms',
           'The first box is your retail terms, used on retail quotes. These are the terms an ordinary household customer gets. Read them through, and change anything that is not how you work. Deposits, lead times and the length of your guarantee are the parts people change most often.'],
    4  => ['Your trade terms',
           'The second box looks the same, but it is a different document. These are your trade terms, used on quotes raised for a trade account. There is nothing to switch on. If a quote belongs to a trade account, the trade terms go with it. Every other quote gets the retail terms. Business customers have different rights, so the wording is different.'],
    5  => ['Your privacy policy',
           'The third box is your privacy policy. It tells customers what you do with their details, and why. It is written for UK data protection law, and it already explains how a customer can complain. Check the parts about who you share details with, and how long you keep them.'],
    6  => ['The thank-you email',
           'The last box is the thank-you email. It goes to the customer when they accept a quote online. It has its own placeholders, and the useful one is quote link. That drops in the web address of their own quote, so they can look at it again at any time. Leave this box empty, and no thank-you email is sent.'],
    7  => ['Save all four',
           'One button at the bottom saves all four boxes together. Click Save terms, privacy and email. The page reloads, and a green bar says, Terms, Privacy Policy and acceptance email saved. You come back on the Legal tab, so you can read your previews straight away.'],
    8  => ['Quotes link to your terms',
           'So where do your terms actually appear? Not as pages of small print. The quote P D F prints one short line, with a link to your terms, and another to your privacy policy. The customer clicks, and reads them on a plain web page with your company name at the top. A trade quote links to your trade terms instead.'],
    9  => ['Always up to date',
           'Because quotes link to the page, rather than printing it, the page is always up to date. Change a word here today, and a quote you sent last week shows the new wording the next time it is opened. Anyone with the link can read the page, without logging in. So never put private notes in these boxes.'],
    10 => ['Careful with an empty box',
           'Be careful with an empty box. Clear your retail terms and save, and the link disappears from your quotes. The online quote also stops asking the customer to tick, I agree to the Terms and Conditions. There is no undo, so copy the wording somewhere safe first. And remember, these are a starting point, not legal advice. Have them checked by a solicitor.'],
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

$L = [
    'retail'  => 'Terms &amp; Conditions <span class="q">(retail &mdash; used on retail quotes)</span>',
    'trade'   => 'Terms &amp; Conditions <span class="q">(trade &mdash; used on quotes raised for a trade account)</span>',
    'privacy' => 'Privacy Policy',
    'email'   => 'Thank-you email (sent when a customer accepts a quote)',
];
$tok = static fn (string $t): string => '<code class="tk">{{' . $t . '}}</code>';

$pdfLine = '<div class="pdfl">This quotation is subject to our Terms &amp; Conditions of sale: <u>https://yourblinds.uk/legal/view.php?c=12&amp;doc=retail&amp;k=&hellip;</u>.<br>
            Privacy Policy: <u>https://yourblinds.uk/legal/view.php?c=12&amp;doc=privacy&amp;k=&hellip;</u>.</div>';

$script = [];
foreach ($vo as $n => [$cap, $line]) $script[] = [(string) $n, $cap, $line, $n];

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Terms, trade terms & privacy',
        'eyebrow' => 'Settings · Legal',
        'v'       => 2,
        'blurb'   => 'The four documents behind every quote — retail terms, trade terms, privacy, and the thank-you email.',
        'lede'    => 'Four boxes on one tab, and <b>all four come ready-written</b> &mdash; you are editing wording, not starting from
                      nothing. Your quotes don&rsquo;t print pages of small print: they print <b>one line with a link</b> to a public
                      page, so a word changed here changes every quote already out there. The second box is the <b>trade</b> one,
                      used whenever a quote belongs to a trade account. To get there: <b>Settings</b> &rarr; the <b>Legal</b> tab.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin:0 0 .75rem; }
          .gd .tab{ font-size:.66rem; font-weight:600; color:var(--soft); padding:.28rem .45rem; border-radius:6px 6px 0 0; }
          .gd .tab.on{ color:var(--accent); box-shadow:inset 0 -2px 0 var(--accent); }
          .gd .sh{ font-size:.86rem; font-weight:800; color:var(--ink); margin:0 0 .5rem; }
          .gd .lb{ display:block; font-size:.68rem; font-weight:700; color:var(--soft); margin:0 0 .2rem; }
          .gd .lb .q{ font-weight:400; color:var(--faint); border-radius:4px; }
          .gd .ta3{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:.35rem .5rem; font-size:.68rem;
                    color:var(--ink); line-height:1.5; max-width:32rem; position:relative; }
          .gd .ta3 .h{ font-weight:700; display:block; border-radius:4px; }
          .gd .pvl{ font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); margin:.35rem 0 .15rem; }
          .gd .pvl span{ text-transform:none; letter-spacing:normal; }
          .gd .prev{ background:var(--panel); border:1px solid var(--line); border-radius:6px; padding:.35rem .5rem; font-size:.68rem; color:var(--soft); line-height:1.5; max-width:32rem; }
          .gd .prev b{ color:var(--ink); }
          .gd .tk{ font-size:.62rem; background:var(--accent-wash); color:var(--accent-ink); border-radius:4px; padding:0 .25rem; font-family:ui-monospace,Menlo,Consolas,monospace; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .row{ display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; } .gd .mt{ margin-top:.7rem; }
          .gd .arrow{ color:var(--faint); font-weight:800; }
          .gd .stk{ display:inline-grid; } .gd .stk > *{ grid-area:1/1; }

          /* 1 — the four boxes */
          .gd .boxes{ display:grid; gap:.45rem; max-width:32rem; }
          .gd .boxes .ta3{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--soft); }

          /* 4 — which terms go */
          .gd .route{ display:grid; grid-template-columns:auto auto auto; gap:.4rem .5rem; align-items:center; justify-content:start; font-size:.72rem; }
          .gd .qc{ border:1px solid var(--line); border-radius:9px; padding:.3rem .55rem; background:var(--surface); font-size:.7rem; color:var(--ink); }
          .gd .qc small{ display:block; color:var(--faint); font-size:.6rem; }

          /* 8/9 — PDF line + public page */
          .gd .pdf{ border:1px solid var(--line); border-radius:8px; background:#fff; color:#1f2937; padding:.5rem .7rem; max-width:32rem; box-shadow:var(--gd-shadow); }
          .gd .pdf i{ display:block; height:.3rem; border-radius:3px; background:#e5e7eb; margin:.25rem 0; }
          .gd .pdfl{ font-size:.6rem; line-height:1.55; color:#374151; margin-top:.4rem; word-break:break-all; }
          .gd .pdfl u{ color:#2563eb; }
          .gd .pub{ border:1px solid #e5e7eb; border-radius:10px; background:#fff; color:#374151; padding:.6rem .8rem; max-width:24rem; box-shadow:var(--gd-shadow); font-size:.66rem; line-height:1.5; }
          .gd .pub .co{ font-size:.86rem; font-weight:700; color:#1f3b5b; }
          .gd .pub .tt{ font-size:.6rem; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin:0 0 .35rem; }
          .gd .pub .ft{ text-align:center; color:#9ca3af; font-size:.56rem; margin-top:.4rem; }
          .gd .side2{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; align-items:start; max-width:40rem; }

          /* 10 — online quote */
          .gd .oq{ border:1px solid var(--line); border-radius:9px; padding:.45rem .65rem; background:var(--surface); font-size:.7rem; color:var(--ink); max-width:24rem; }
          .gd .gone{ color:var(--err); font-weight:700; font-size:.64rem; }
          .gd .oq .cbx{ display:inline-block; width:12px; height:12px; border:1.5px solid var(--border-strong,#c7ccd4); border-radius:3px; vertical-align:-2px; margin-right:.3rem; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; }
            .gd .side2{ grid-template-columns:1fr; }
            .gd .sc{ min-height:430px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / legal</span></div>
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
                  ' . $tabs('Legal') . '
                  <div class="sh">Terms &amp; Conditions &amp; Privacy Policy</div>
                  <div class="boxes">
                    <div><span class="lb">' . $L['retail'] . '</span><div class="ta3">TERMS &amp; CONDITIONS OF SALE &mdash; {{company_name}}</div></div>
                    <div><span class="lb">' . $L['trade'] . '</span><div class="ta3">TRADE TERMS &amp; CONDITIONS OF SALE &mdash; {{company_name}}</div></div>
                  </div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; ten short chapters.</p>
                </div>

                <!-- 1 — four boxes -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  ' . $tabs('Legal', '3s') . '
                  <div class="a-move" style="--fx:70%;--fy:80%;--tx:7.7rem;--ty:.9rem;--d:.8s;--md:1.8s">' . $ptr . '</div>
                  <div class="sh a-fade" style="--d:3.5s">Terms &amp; Conditions &amp; Privacy Policy</div>
                  <div class="boxes">
                    <div class="a-drop" style="--d:5.5s"><span class="lb">' . $L['retail'] . '</span><div class="ta3">TERMS &amp; CONDITIONS OF SALE &mdash; {{company_name}} &middot; These terms apply to your order with&hellip;</div></div>
                    <div class="a-drop" style="--d:7s"><span class="lb">' . $L['trade'] . '</span><div class="ta3">1. APPLICATION &amp; FORMATION OF CONTRACT &middot; 2. PRICE &middot; 3. ORDERS&hellip;</div></div>
                    <div class="a-drop" style="--d:8.5s"><span class="lb">' . $L['privacy'] . '</span><div class="ta3">1. THE LAW WE FOLLOW &middot; 2. THE INFORMATION WE COLLECT&hellip;</div></div>
                    <div class="a-drop" style="--d:10s"><span class="lb">' . $L['email'] . '</span><div class="ta3">Hello {{customer_name}}, Thank you for accepting your quote&hellip;</div></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:12.5s;border-color:var(--good)">&#10003; All four ready-written</span>
                    <span class="chip a-pop" style="--d:18s">You change wording &mdash; you don&rsquo;t start from nothing</span>
                  </div>
                </div>

                <!-- 2 — placeholders + preview -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">Placeholders fill themselves in</div>
                  <div class="row" style="gap:.3rem;margin:.4rem 0 .6rem">
                    <span class="a-pop" style="--d:1.5s">' . $tok('company_name') . '</span><span class="a-pop" style="--d:1.8s">' . $tok('company_address') . '</span>
                    <span class="a-pop" style="--d:2.1s">' . $tok('company_email') . '</span><span class="a-pop" style="--d:2.4s">' . $tok('company_phone') . '</span>
                    <span class="a-pop" style="--d:2.7s">' . $tok('customer_name') . '</span><span class="a-pop" style="--d:3s">' . $tok('quote_number') . '</span>
                    <span class="a-pop" style="--d:3.3s">' . $tok('date') . '</span>
                  </div>
                  <div class="ta3 a-rise" style="--d:4s">These terms apply to your order with <span class="a-ring" style="--d:4.5s;border-radius:4px">' . $tok('company_name') . '</span>.
                    &ldquo;You&rdquo; means <span class="a-ring" style="--d:7.5s;border-radius:4px">' . $tok('customer_name') . '</span>, the customer named on the quotation.</div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:11s">Company details come from the <b>Company</b> tab &mdash; fill it in first</span></div>
                  <div class="a-rise" style="--d:17s">
                    <div class="pvl">Preview <span>&mdash; with example customer &amp; quote</span></div>
                    <div class="prev">These terms apply to your order with <b class="a-fade" style="--d:19s">Bright Blinds</b>.
                      &ldquo;You&rdquo; means <b class="a-fade" style="--d:22s">Jane Smith</b>, the customer named on the quotation.</div>
                  </div>
                </div>

                <!-- 3 — retail terms -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <span class="lb a-fade" style="--d:.3s">Terms &amp; Conditions <span class="q a-ring" style="--d:2s">(retail &mdash; used on retail quotes)</span></span>
                  <div class="ta3 a-rise" style="--d:1s">
                    <span class="h">1. OUR QUOTATION</span>Quotations are valid for 30 days and include supply and fitting unless stated otherwise.
                    <span class="h a-ring" style="--d:15s;margin-top:.3rem">3. ORDERS, DEPOSIT &amp; PAYMENT</span>A 50% deposit is required to commence your order, with the balance due on completion of installation.
                    <span class="h a-ring" style="--d:16.5s;margin-top:.3rem">6. LEAD TIMES &amp; DELIVERY</span>
                    <span class="h a-ring" style="--d:18s;margin-top:.3rem">11. GUARANTEE</span>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:5s">&#127968; For household customers</span>
                    <span class="chip a-pop" style="--d:9s">Read it through &mdash; change what isn&rsquo;t how you work</span>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:15s">Deposits</span><span class="chip a-pop" style="--d:16.5s">Lead times</span><span class="chip a-pop" style="--d:18s">Guarantee</span>
                  </div>
                </div>

                <!-- 4 — trade terms -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <span class="lb a-fade" style="--d:.3s">Terms &amp; Conditions <span class="q a-ring" style="--d:4s">(trade &mdash; used on quotes raised for a trade account)</span></span>
                  <div class="ta3 a-rise" style="--d:1s">
                    <span class="h">4. PAYMENT</span>&hellip;pay each invoice in full within 20 days of the end of the month of the invoice date&hellip;
                    <span class="h" style="margin-top:.3rem">5. DIRECTOR&rsquo;S PERSONAL GUARANTEE</span>
                  </div>
                  <div class="route mt">
                    <span class="qc a-fly" style="--d:11s">Quote BRI-2026-0051<small>Trade account: Harper Interiors</small></span><span class="arrow a-fade" style="--d:12.5s">&rarr;</span><span class="chip a-pop" style="--d:13s;border-color:var(--accent)">Trade terms</span>
                    <span class="qc a-fly" style="--d:16s">Quote BRI-2026-0052<small>Mrs Hall</small></span><span class="arrow a-fade" style="--d:17.5s">&rarr;</span><span class="chip a-pop" style="--d:18s">Retail terms</span>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:8.5s">Nothing to switch on</span>
                    <span class="chip a-pop" style="--d:21.5s">Business customers, different rights</span></div>
                </div>

                <!-- 5 — privacy -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <span class="lb a-fade" style="--d:.3s">Privacy Policy</span>
                  <div class="ta3 a-rise" style="--d:1s">
                    <span class="h">1. THE LAW WE FOLLOW</span>
                    <span class="h">2. THE INFORMATION WE COLLECT</span>
                    <span class="h">4. WHY WE USE IT, AND OUR LAWFUL BASIS</span>
                    <span class="h a-ring" style="--d:16s">5. WHO WE SHARE IT WITH</span>
                    <span class="h a-ring" style="--d:18s">7. KEEPING YOUR INFORMATION</span>
                    <span class="h a-ring" style="--d:12s">11. HOW TO COMPLAIN</span>
                    <span class="h">13. CONTACT US</span>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:4s">What you do with their details &mdash; and why</span>
                    <span class="chip a-pop" style="--d:9s">&#127468;&#127463; UK data protection law</span>
                  </div>
                </div>

                <!-- 6 — thank-you email -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <span class="lb a-fade" style="--d:.3s">' . $L['email'] . '</span>
                  <div class="row" style="gap:.3rem;margin:.2rem 0 .4rem;font-size:.64rem;color:var(--faint)">Placeholders:
                    <span class="a-pop" style="--d:6s">' . $tok('customer_name') . '</span><span class="a-pop" style="--d:6.3s">' . $tok('company_name') . '</span>
                    <span class="a-pop" style="--d:6.6s">' . $tok('quote_number') . '</span><span class="a-pop" style="--d:6.9s"><span class="a-ring" style="--d:7.5s;border-radius:4px;display:inline-block">' . $tok('quote_link') . '</span></span><span>.
                    Leave empty to send no thank-you email.</span></div>
                  <div class="ta3 a-rise" style="--d:1.5s">Hello {{customer_name}},<br>Thank you for accepting your quote {{quote_number}} &hellip;<br>
                    You can view your quote any time here:<br><span class="a-ring" style="--d:9s;border-radius:4px">' . $tok('quote_link') . '</span></div>
                  <div class="a-rise" style="--d:12s">
                    <div class="pvl">Preview <span>&mdash; what the customer receives</span></div>
                    <div class="prev">Hello <b>Jane Smith</b>,<br>Thank you for accepting your quote <b>BEV-2026-0042</b> &hellip;<br>
                      You can view your quote any time here:<br><b class="a-fade" style="--d:14s;color:var(--accent)">https://your-site/quote-history/public.php?token=abc123</b></div>
                  </div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:20s">Empty box = no thank-you email</span></div>
                </div>

                <!-- 7 — save -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="bnr a-drop" style="--d:10s">&#10003; Terms, Privacy Policy and acceptance email saved.</div>
                  ' . $tabs('Legal', '15s') . '
                  <div class="boxes" style="opacity:.75">
                    <div><span class="lb">' . $L['privacy'] . '</span><div class="ta3">1. THE LAW WE FOLLOW &middot; 2. THE INFORMATION WE COLLECT&hellip;</div></div>
                    <div><span class="lb">' . $L['email'] . '</span><div class="ta3">Hello {{customer_name}}, Thank you for accepting your quote&hellip;</div></div>
                  </div>
                  <div class="mt"><span class="btnp a-press" style="--d:5.5s">Save terms, privacy &amp; email</span>
                    <span class="chip a-pop" style="--d:2s;margin-left:.4rem">One button &middot; all four boxes</span></div>
                  <div class="a-move" style="--fx:80%;--fy:95%;--tx:7rem;--ty:13.2rem;--d:3.5s;--md:1.8s">' . $ptr . '</div>
                </div>

                <!-- 8 — quotes link -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="sct a-fade" style="--d:.2s">The quote PDF links to your terms</div>
                  <div class="side2" style="margin-top:.5rem">
                    <div class="pdf a-rise" style="--d:4s"><b style="font-size:.72rem">Quote BRI-2026-0042</b><i></i><i style="width:80%"></i><i></i><i style="width:60%"></i>
                      <div class="a-ring" style="--d:9s;border-radius:6px">' . $pdfLine . '</div></div>
                    <div class="pub a-drop" style="--d:14s">
                      <div class="co">Bright Blinds</div><div class="tt">Terms &amp; Conditions</div>
                      TERMS &amp; CONDITIONS OF SALE &mdash; Bright Blinds<br>1. OUR QUOTATION<br>Quotations are valid for 30 days&hellip;
                      <div class="ft">Provided via YourBlinds</div></div>
                  </div>
                  <div class="a-move" style="--fx:30%;--fy:90%;--tx:12rem;--ty:7.4rem;--d:11.5s;--md:1.6s">' . $ptr . '</div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:2s">Not pages of small print</span>
                    <span class="chip a-pop" style="--d:21s;border-color:var(--accent)">Trade quote &rarr; <b>Terms &amp; Conditions (Trade)</b></span>
                  </div>
                </div>

                <!-- 9 — always up to date -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">One page, always up to date</div>
                  <div class="pub a-rise" style="--d:1s;margin-top:.5rem">
                    <div class="co">Bright Blinds</div><div class="tt">Terms &amp; Conditions</div>
                    1. OUR QUOTATION<br>Quotations are valid for
                    <span class="stk"><span class="a-out" style="--d:8s">30</span><b class="a-fade" style="--d:8s;color:#2563eb">60</b></span> days&hellip;
                    <div class="ft">Provided via YourBlinds</div></div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:10s">&#9993; A quote sent last week shows the new wording</span>
                  </div>
                  <div class="row mt">
                    <span class="chip warn a-pop" style="--d:15.5s">&#128275; No login needed to read it</span>
                    <span class="chip bad a-pop" style="--d:19s">Never put private notes in these boxes</span>
                  </div>
                </div>

                <!-- 10 — empty box -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <span class="lb a-fade" style="--d:.3s">' . $L['retail'] . '</span>
                  <div class="ta3" style="min-height:2.2rem"><span class="a-out" style="--d:3.5s">TERMS &amp; CONDITIONS OF SALE &mdash; {{company_name}} &middot; These terms apply to your order&hellip;</span></div>
                  <div class="side2 mt">
                    <div class="pdf a-rise" style="--d:5s"><b style="font-size:.66rem">Quote PDF</b><i></i><i style="width:70%"></i>
                      <div class="pdfl stk" style="display:grid"><span class="a-out" style="--d:7.5s">This quotation is subject to our Terms &amp; Conditions of sale: <u>https://yourblinds.uk/legal/view.php?&hellip;</u></span>
                        <span class="gone a-fade" style="--d:8s">&mdash; no terms link &mdash;</span></div></div>
                    <div class="oq a-rise" style="--d:9s"><b>Accept this quote</b><br>
                      <span class="stk" style="display:grid"><span class="a-out" style="--d:13s"><span class="cbx"></span>I agree to the <u style="color:#2563eb">Terms &amp; Conditions</u> of Bright Blinds.</span>
                        <span class="gone a-fade" style="--d:13.5s">&mdash; no &ldquo;I agree&rdquo; tick &mdash;</span></span>
                      <span class="btnp" style="margin-top:.35rem;padding:.2rem .6rem;font-size:.66rem">Accept quote</span></div>
                  </div>
                  <div class="row mt">
                    <span class="chip bad a-pop" style="--d:16s">No undo &mdash; copy the wording out first</span>
                    <span class="chip warn a-pop" style="--d:22s">Not legal advice &mdash; have it checked by a solicitor</span>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Settings</b> &rarr; the <b>Legal</b> tab (third along: Company, Quoting, <b>Legal</b>, Status colours,
             Suppliers, Accounting, Back up data). One section, <b>Terms &amp; Conditions &amp; Privacy Policy</b>: four boxes and one Save
             button. <b>Every box arrives ready-written</b> &mdash; <em>&ldquo;A suggested template is pre-filled below &mdash; edit it to suit
             your business, then Save. Leave a box empty to show nothing.&rdquo;</em> A box you have never saved uses the standard wording;
             once you save, your wording replaces it.</p>

          <ul class="steps">
            <li><b>Placeholders.</b> These fill in automatically: <code>{{company_name}}</code>, <code>{{company_address}}</code>,
                <code>{{company_email}}</code>, <code>{{company_phone}}</code>, <code>{{customer_name}}</code>, <code>{{quote_number}}</code> and
                <code>{{date}}</code>. The company ones come from the <b>Company</b> tab &mdash; fill that in first. Under every box is a
                <b>Preview &mdash; with example customer &amp; quote</b> that redraws as you type (customer <b>Jane Smith</b>, quote
                <b>BEV-2026-0042</b>).</li>
            <li><b>Terms &amp; Conditions (retail &mdash; used on retail quotes)</b> &mdash; for household customers. As supplied it covers
                quotation validity (30 days), survey and measurements, a 50% deposit with the balance on completion, made-to-measure goods and
                cancellation, colour variation, lead times, access, child safety, motorised products, condensation, a guarantee, fit-only work,
                your statutory rights, complaints, data protection and general terms. Deposits, lead times and guarantee length are what people
                usually change.</li>
            <li><b>Terms &amp; Conditions (trade &mdash; used on quotes raised for a trade account)</b> &mdash; a separate business-to-business
                document. Nothing to switch on: a quote raised for a <b>trade account</b> uses these; everything else uses the retail terms.
                Notable clauses: payment within 20 days of the end of the month of the invoice, late-payment interest, a <b>director&rsquo;s
                personal guarantee</b>, retention of title, and 14 days to report damage or shortage.</li>
            <li><b>Privacy Policy</b> &mdash; what you collect, why, who it is shared with, how long it is kept, the customer&rsquo;s rights and
                how to complain, written for UK data protection law.</li>
            <li><b>Thank-you email (sent when a customer accepts a quote)</b> &mdash; its own placeholders: <code>{{customer_name}}</code>,
                <code>{{company_name}}</code>, <code>{{quote_number}}</code> and <code>{{quote_link}}</code> (the web address of their quote).
                Subject: <em>&ldquo;Thank you for accepting quote BEV-2026-0042&rdquo;</em>. Sent only when the quote has a customer email.
                <b>Leave empty to send no thank-you email.</b></li>
            <li><b>Save terms, privacy &amp; email</b> &mdash; saves all four. The page reloads with <b>&ldquo;Terms, Privacy Policy and acceptance
                email saved.&rdquo;</b> and reopens on the <b>Legal</b> tab (the last tab is remembered in this browser).</li>
          </ul>

          <p><b>Where the wording appears.</b> Quotes don&rsquo;t print the documents in full. The <b>quote PDF</b> prints one line with a link:
             <em>&ldquo;This quotation is subject to our Terms &amp; Conditions of sale: https://yourblinds.uk/legal/view.php?&hellip;&rdquo;</em> and
             <em>&ldquo;Privacy Policy: &hellip;&rdquo;</em> (an invoice reads &ldquo;This invoice is subject to&hellip;&rdquo;). A quote raised for a
             <b>trade account</b> links to the trade terms. The link opens a plain public page &mdash; your company name, the title
             (<b>Terms &amp; Conditions</b>, <b>Terms &amp; Conditions (Trade)</b> or <b>Privacy Policy</b>), your wording, and
             &ldquo;Provided via YourBlinds&rdquo;. <b>No login is needed</b>, so keep private notes out of these boxes. Because the page is read
             live, a change here shows on quotes you have already sent. A link only prints when that document has something in it.</p>

          <p><b>The customer&rsquo;s online quote</b> has a <b>Terms &amp; Conditions &amp; Privacy Policy</b> link at the foot, and, while the
             retail box has text, an <b>&ldquo;I agree to the Terms &amp; Conditions of &hellip;&rdquo;</b> tick beside <b>Accept quote</b>
             (<em>&ldquo;Please tick the box to agree to the Terms &amp; Conditions.&rdquo;</em> if they miss it). The online quote always shows
             the <b>retail</b> wording &mdash; the trade wording is what a trade quote&rsquo;s PDF links to.</p>

          <div class="oops"><b>Careful with an empty box.</b> Clearing the <b>retail</b> box removes the terms link from your quotes and the
             &ldquo;I agree&rdquo; tick from the online quote. There is <b>no undo and no reset</b> &mdash; the standard wording only shows in a box
             that has never been saved &mdash; so copy the text somewhere safe before you clear it. If you see
             <code>Could not save: &hellip; &mdash; have you run migrate_terms_conditions.php?</code>, nothing was saved; pass it to whoever set the
             system up.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Not legal advice.</b> All four are a starting point for a typical blinds
             business. Have them checked by a solicitor before you rely on them &mdash; especially the <b>director&rsquo;s personal guarantee</b>
             in the trade terms, which normally needs signing separately.</div></div>

          <p class="prose"><b>Not the same as &ldquo;Trade terms&rdquo; in the menu.</b> The <b>Trade terms</b> page under <b>Setup</b> shows the
             buying <em>discounts</em> your supplier gives you. The trade <em>document</em> lives here.</p>',
        'script'  => $script,
];
