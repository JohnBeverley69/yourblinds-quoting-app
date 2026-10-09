<?php
declare(strict_types=1);

/**
 * Guide: settings-quote-defaults — "Quote defaults" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the "Quote defaults" form on Settings → Quoting
 * (admin/settings.php, the section headed "Quote defaults", POST
 * _action=quote): Quote prefix (unique across accounts, a free one suggested
 * on a clash), the VAT % slot (now only a note pointing to the Company tab,
 * where the rate sits beside the VAT number - commit f84cc09),
 * Default deposit, Prices / Sizes on the customer quote, the WT charge, New
 * order alerts, Auto-place in-house orders, Paid-in-full receipt, the email
 * from-name / reply-to and the quote footer. The factory-only blocks (Default
 * sale type, Factory — new order received, Auto-order bought-in items) are
 * covered in the written steps only. Wording copied from those files plus
 * quote-builder/edit.php + save_wt.php (the WT row), quote-history/accept.php
 * (the alert email) and quote-builder/_helpers.php (numbering, receipt).
 *
 * v2: one scene per script line; data-len is worked out from the line's own
 * length (characters ÷ 13.6), so editing a line keeps its scene in step.
 */

// ── Voice-over, one line per chapter ─────────────────────────────────
$vo = [
    1  => ['Where it lives',
           'Quote defaults lives in Settings, on the Quoting tab. That tab holds four sections, one under another. Default margins, Measurements, Quote defaults, and Bank details. Each section has its own Save button. So Save quote defaults saves this section, and nothing else. If you change a margin as well, press that section\'s own button too.'],
    2  => ['The quote prefix',
           'Start with the quote prefix. These are the letters in front of every quote number. Type them in, and they are saved in capitals. So your first quote this year becomes B R I, twenty twenty six, nought nought nought one. The next is nought nought nought two, and the count starts again each January. Leave the box blank, and the system uses the first three letters of your company name instead.'],
    3  => ['One prefix, one business',
           'Each prefix can belong to one business only. If another account already uses the letters you typed, the save is stopped, and a red message explains why. Two accounts sharing a prefix would end up with the same order numbers. To help, a free set of letters is put in the box for you, made from your company name. Click Save to use them, or type your own and save again.'],
    4  => ['VAT is on the Company tab',
           'Next to the prefix is VAT percent, but there is no box to fill in here any more. Instead, a short note says, Set beside your VAT number on the Company tab. Your VAT rate now lives right next to your VAT number, so the two always go together. Click Company in the note to go straight there. Each quote keeps the rate it was made with, so a new rate only changes new quotes.'],
    5  => ['The default deposit',
           'The default deposit is what you usually ask for when a customer says yes. Choose Percentage of total, and type a figure, such as fifty. Or choose Flat amount, and type a sum in pounds. The figure is put on a quote the moment it moves to Accepted. So a four hundred pound order asks for two hundred. You can still change it on any one quote, and a deposit you typed yourself is never overwritten.'],
    6  => ['Prices on the customer quote',
           'Now, what the customer sees. Prices on the customer quote has one tick box, Show the price of each blind. It starts ticked. Ticked, the quote P D F and the online quote show a price for every blind, plus the total. Untick it, and each blind is listed without a price. The customer then sees only the total for the whole job.'],
    7  => ['Sizes on the customer quote',
           'Sizes on the customer quote works the same way. Show the size of each blind is ticked to start with. Ticked, every blind shows its width and drop. That is right for trade customers, because they check the sizes before they order. Untick it for a tidier retail quote. The sizes are hidden, and just the description is left.'],
    8  => ['The Wally tax',
           'The next box is the Wally tax, also called the W T charge. It is off until you tick it. It is a quiet extra for a job that will be more hassle than it is worth. Once ticked, a W T box appears on the quote builder. Type an amount and press Set. It is added before VAT. If prices are shown, it is spread across the blind prices, so the sums still add up. The customer never sees a W T line.'],
    9  => ['New order alerts',
           'New order alerts is an email address. When a customer clicks Accept on a quote link you sent them, we email this address to say a new order has come in. Leave it blank to turn the alert off. One thing to watch. If the address is mistyped, it is quietly ignored, and the old one stays. So after saving, check your address is still in the box.'],
    10 => ['Two boxes ticked for you',
           'Two boxes start ticked. Auto-place in-house orders sends an accepted quote straight to the workshop, but only if every blind on it is one you make yourself. A quote with a bought-in blind still waits for you to place it, so the supplier gets emailed. Paid-in-full receipt emails the customer a thank-you receipt when their balance reaches nought. It goes once per order, and only if they have an email address.'],
    11 => ['Emails, footer and Save',
           'Last come the emails and the footer. Email from name is the name your emails are sent from. Reply-to email is where your customers\' replies land. The quote footer is a line or two printed at the bottom of the quote P D F, such as your guarantee. Then click Save quote defaults. The page reloads, and a green bar at the top says, Quote settings saved.'],
];
$len = static fn (int $n): string => (string) round(mb_strlen($vo[$n][1]) / 13.6);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// The Settings tab row, with one tab marked on.
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

// A small customer quote: $price / $size say what each line shows.
$mini = static function (string $head, bool $price, bool $size, string $cls = '', string $d = ''): string {
    $lines = [['Roller blind &middot; Lounge', '1200 &times; 1600', '120.00'], ['Roller blind &middot; Kitchen', '900 &times; 1100', '95.00'], ['Vertical blind &middot; Bedroom', '1800 &times; 2000', '185.00']];
    $h = '<div class="mq ' . $cls . '"' . ($d !== '' ? ' style="--d:' . $d . '"' : '') . '><div class="mqh">' . $head . '</div>';
    foreach ($lines as [$desc, $sz, $p]) {
        $h .= '<div class="mql"><span>' . $desc . ($size ? ' <i>' . $sz . '</i>' : '') . '</span>' . ($price ? '<b>&pound;' . $p . '</b>' : '') . '</div>';
    }
    return $h . '<div class="mqt"><span>Total</span><b>&pound;400.00</b></div></div>';
};

$script = [];
foreach ($vo as $n => [$cap, $line]) $script[] = [(string) $n, $cap, $line, $n];

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Quote defaults',
        'eyebrow' => 'Settings · Quoting',
        'v'       => 2,
        'blurb'   => 'Quote numbers, deposits, what the customer sees, the automatic emails and the Wally tax (WT charge) — plus where your VAT rate lives now.',
        'lede'    => 'One form on the <b>Quoting</b> tab quietly shapes every quote you send: the <b>letters in front of your quote
                      numbers</b>, the <b>deposit</b> you ask for, <b>what the customer does and doesn&rsquo;t
                      see</b>, the emails that go out on their own, and the <b>Wally tax (WT charge)</b>. (Your <b>VAT rate</b> isn&rsquo;t
                      here any more &mdash; it sits beside your VAT number on the <b>Company</b> tab.) This guide takes it one box
                      at a time, slowly. Watch it through once, then use <b>Jump to a chapter</b> to go back over any part.
                      To get there: <b>Settings</b> &rarr; the <b>Quoting</b> tab &rarr; <b>Quote defaults</b>.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* settings furniture */
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin:0 0 .75rem; }
          .gd .tab{ font-size:.66rem; font-weight:600; color:var(--soft); padding:.28rem .45rem; border-radius:6px 6px 0 0; }
          .gd .tab.on{ color:var(--accent); box-shadow:inset 0 -2px 0 var(--accent); }
          .gd .sh{ font-size:.86rem; font-weight:800; color:var(--ink); margin:0 0 .5rem; }
          .gd .fs{ border:1px solid var(--line); border-radius:10px; padding:.5rem .7rem .6rem; margin:0 0 .6rem; background:var(--surface); max-width:31rem; }
          .gd .lg{ font-size:.6rem; font-weight:700; color:var(--accent-ink); text-transform:uppercase; letter-spacing:.05em; margin:0 0 .35rem; }
          .gd .lb{ display:block; font-size:.68rem; font-weight:700; color:var(--soft); margin:0 0 .2rem; }
          .gd .in{ display:flex; align-items:center; min-height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px;
                   background:var(--surface); padding:0 .5rem; font-size:.78rem; color:var(--ink); white-space:nowrap; overflow:hidden; }
          .gd .in.off{ background:var(--panel); color:var(--faint); }
          .gd .in.sm{ display:inline-flex; width:4.2rem; }
          .gd .ta3{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); padding:.35rem .5rem; font-size:.74rem;
                    color:var(--ink); min-height:3rem; line-height:1.45; }
          .gd .ph{ color:var(--faint); }
          .gd .vlink{ display:block; font-size:.72rem; color:var(--soft); padding-top:.35rem; line-height:1.4; }
          .gd .vlink u{ color:var(--accent); font-weight:700; border-radius:4px; }
          .gd .stk{ display:inline-grid; } .gd .stk > *{ grid-area:1/1; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem .8rem; max-width:31rem; }
          .gd .opt{ display:flex; align-items:flex-start; gap:.45rem; font-size:.78rem; color:var(--ink); font-weight:600; }
          .gd .hn{ display:block; font-size:.64rem; color:var(--faint); font-weight:400; line-height:1.4; margin-top:.15rem; }
          .gd .cb{ position:relative; flex:0 0 auto; width:15px; height:15px; margin-top:.1rem; border-radius:4px; border:1.5px solid var(--border-strong,#c7ccd4); background:var(--surface); box-sizing:border-box; }
          .gd .cb i{ position:absolute; inset:-1.5px; border-radius:4px; background:var(--accent); color:#fff; font-size:.6rem; font-style:normal; font-weight:800; display:grid; place-items:center; }
          .gd .rd{ position:relative; flex:0 0 auto; width:15px; height:15px; border-radius:50%; border:1.5px solid var(--border-strong,#c7ccd4); box-sizing:border-box; display:inline-grid; place-items:center; }
          .gd .rd i{ width:7px; height:7px; border-radius:50%; background:var(--accent); }
          .gd .rrow{ display:flex; flex-wrap:wrap; gap:.5rem 1.2rem; align-items:center; font-size:.78rem; color:var(--ink); }
          .gd .rrow > span{ display:inline-flex; align-items:center; gap:.35rem; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.72rem; font-weight:600; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px; padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.warn{ border-color:#f59e0b; background:color-mix(in srgb,#f59e0b 12%,transparent); }
          .gd .bnr{ background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px; padding:.45rem .65rem; font-size:.74rem; font-weight:700; color:var(--ink); margin:0 0 .6rem; }
          .gd .ebnr{ background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.45rem .65rem; font-size:.7rem; color:var(--ink); margin:0 0 .6rem; line-height:1.45; max-width:31rem; }
          .gd .mt{ margin-top:.7rem; } .gd .row{ display:flex; flex-wrap:wrap; gap:.45rem; align-items:center; }

          /* 1 — the four sections */
          .gd .secs{ display:grid; gap:.4rem; max-width:24rem; }
          .gd .secs > div{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; border:1px solid var(--line); border-radius:9px; padding:.4rem .6rem; font-size:.76rem; font-weight:700; color:var(--ink); background:var(--surface); }
          .gd .secs .me{ border-color:var(--accent); background:var(--accent-wash); }

          /* 2 — quote numbers */
          .gd .qn{ font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.78rem; font-weight:700; }

          /* 4 — totals */
          .gd .tot{ display:grid; grid-template-columns:auto auto; gap:.15rem 1.2rem; font-size:.74rem; border:1px solid var(--line); border-radius:9px; padding:.45rem .7rem; background:var(--panel); }
          .gd .tot b{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .tot .g{ font-weight:800; border-top:1px solid var(--line); padding-top:.15rem; }
          .gd .tcap{ font-size:.6rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin:0 0 .25rem; }

          /* 5 — accepted card */
          .gd .acc{ display:inline-flex; align-items:center; gap:.7rem; flex-wrap:wrap; border:1px solid var(--line); border-radius:10px; padding:.5rem .75rem; background:var(--surface); position:relative; }
          .gd .stamp{ display:inline-block; border:2px solid var(--good); color:var(--good); font-weight:800; font-size:.7rem; letter-spacing:.08em; padding:.1rem .4rem; border-radius:5px; }

          /* 6/7 — mini customer quotes */
          .gd .mqs{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; max-width:34rem; }
          .gd .mq{ border:1px solid var(--line); border-radius:10px; padding:.45rem .6rem; background:var(--surface); font-size:.68rem; }
          .gd .mqh{ font-size:.6rem; font-weight:800; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin-bottom:.3rem; }
          .gd .mql{ display:flex; justify-content:space-between; gap:.4rem; padding:.18rem 0; border-bottom:1px dashed var(--line); color:var(--ink); }
          .gd .mql i{ font-style:normal; color:var(--accent); font-weight:700; }
          .gd .mql b{ font-variant-numeric:tabular-nums; }
          .gd .mqt{ display:flex; justify-content:space-between; padding-top:.25rem; font-weight:800; color:var(--ink); }

          /* 8 — WT row */
          .gd .wtrow{ display:flex; align-items:center; justify-content:flex-end; gap:.4rem; flex-wrap:wrap; border:1px solid var(--line); border-radius:9px;
                      padding:.4rem .6rem; font-size:.74rem; color:#9333ea; font-weight:700; max-width:31rem; background:var(--surface); }
          .gd .wtrow small{ font-weight:400; color:var(--faint); }
          .gd .tst{ display:inline-block; margin-top:.5rem; background:var(--good-wash); color:var(--good); font-weight:700; font-size:.7rem;
                    border:1px solid color-mix(in srgb,var(--good) 35%,transparent); border-radius:8px; padding:.4rem .6rem; }

          /* 9 — email */
          .gd .mail{ border:1px solid var(--line); border-radius:10px; background:var(--surface); box-shadow:var(--gd-shadow); padding:.5rem .7rem; font-size:.7rem; max-width:22rem; }
          .gd .mail .subj{ font-weight:800; color:var(--ink); font-size:.74rem; }
          .gd .mail .to{ color:var(--faint); font-size:.64rem; }

          /* 10 — routes */
          .gd .flow{ display:grid; grid-template-columns:auto auto auto; justify-content:start; gap:.35rem .5rem; align-items:center; font-size:.72rem; max-width:31rem; }
          .gd .arrow{ color:var(--faint); font-weight:800; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; }
            .gd .two, .gd .mqs{ grid-template-columns:1fr; }
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
                  <div class="sh">Quote defaults</div>
                  <div class="two">
                    <div><span class="lb">Quote prefix</span><span class="in">BRI</span></div>
                    <div><span class="lb">VAT %</span><span class="vlink">Set beside your VAT number on the <u>Company</u> tab.</span></div>
                  </div>
                  <div class="fs mt"><div class="lg">Default deposit</div>
                    <div class="rrow"><span><span class="rd"><i></i></span> Percentage of total <span class="in sm">50</span> %</span>
                      <span><span class="rd"></span> Flat amount &pound; <span class="in sm">0</span></span></div></div>
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; eleven short chapters, one box at a time.</p>
                </div>

                <!-- 1 — where it lives -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  ' . $tabs('Quoting', '3s') . '
                  <div class="a-move" style="--fx:70%;--fy:80%;--tx:4.6rem;--ty:.9rem;--d:1s;--md:1.8s">' . $ptr . '</div>
                  <div class="sct a-fade" style="--d:4.5s">The Quoting tab &mdash; four sections, one under another</div>
                  <div class="secs" style="margin-top:.5rem">
                    <div class="a-fly" style="--d:7s"><span>Default margins</span><span class="btns">Save margins</span></div>
                    <div class="a-fly" style="--d:8.3s"><span>Measurements</span><span class="btns">Save unit</span></div>
                    <div class="me a-fly" style="--d:9.6s"><span>Quote defaults</span><span class="btnp a-ring" style="--d:15.5s">Save quote defaults</span></div>
                    <div class="a-fly" style="--d:10.9s"><span>Bank details for customer payments</span><span class="btns">Save bank details</span></div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:12.5s">Each section has its own Save button</span>
                    <span class="chip warn a-pop" style="--d:20s">Changed a margin too? Press <b>Save margins</b> as well</span>
                  </div>
                </div>

                <!-- 2 — the quote prefix -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">The quote prefix</div>
                  <p class="scs a-fade" style="--d:2s">The letters in front of every quote number.</p>
                  <div style="max-width:14rem"><span class="lb">Quote prefix</span>
                    <span class="in a-ring" style="--d:5s"><span class="stk">
                      <span class="ph a-out" style="--d:6s">e.g. BRI</span>
                      <span class="a-mid" style="--d:6s;--d2:9s"><span class="a-type" style="--d:6s;--ts:3;--tt:.6s">bri</span></span>
                      <span class="a-fade" style="--d:9s"><b>BRI</b></span>
                    </span></span></div>
                  <span class="chip a-pop mt" style="--d:9.3s">Saved in capitals</span>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:11.5s"><span class="qn">BRI-2026-0001</span></span>
                    <span class="arrow a-fade" style="--d:16.5s">&rarr;</span>
                    <span class="chip a-pop" style="--d:17s"><span class="qn">BRI-2026-0002</span></span>
                    <span class="arrow a-fade" style="--d:19.5s">&rarr;</span>
                    <span class="chip a-pop" style="--d:20s">New year? Back to <span class="qn">0001</span></span>
                  </div>
                  <div class="row mt"><span class="chip warn a-pop" style="--d:23.5s">Blank box &rarr; the first three letters of your company name</span></div>
                </div>

                <!-- 3 — one prefix, one business -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">One prefix, one business</div>
                  <div class="ebnr a-drop" style="--d:8s">Quote prefix &ldquo;BRI&rdquo; is already in use. We&rsquo;ve put a free one &mdash; &ldquo;BRIG&rdquo; &mdash; in the box for you:
                    click Save below to use it, or type your own and click Save. Two accounts sharing a prefix would end up with the same order numbers.</div>
                  <div style="max-width:14rem"><span class="lb">Quote prefix</span>
                    <span class="in a-ring" style="--d:18s"><span class="stk">
                      <span class="a-out" style="--d:18.5s">BRI</span>
                      <span class="a-fade" style="--d:18.5s"><b>BRIG</b></span>
                    </span></span></div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:13s">Same prefix = same order numbers</span>
                    <span class="chip a-pop" style="--d:20s">Made from your company name &mdash; Bright Blinds</span>
                  </div>
                  <div class="mt"><span class="btnp a-press" style="--d:25s">Save quote defaults</span></div>
                </div>

                <!-- 4 — VAT: now set on the Company tab -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">VAT % is set on the Company tab now</div>
                  <div class="sh a-fade" style="--d:.6s">Quote defaults</div>
                  <div class="two" style="position:relative">
                    <div><span class="lb">Quote prefix</span><span class="in">BRI</span></div>
                    <div class="a-ring" style="--d:1.5s;border-radius:6px"><span class="lb">VAT %</span>
                      <span class="vlink a-fade" style="--d:6.6s">Set beside your VAT number on the <u class="a-ring" style="--d:17.8s;position:relative">Company<span class="a-move" style="--fx:-9rem;--fy:2.6rem;--tx:45%;--ty:55%;--d:16.3s;--md:1.5s">' . $ptr . '</span></u> tab.</span></div>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:2.8s">No box to fill in here any more</span></div>
                  <div class="fs a-rise mt" style="--d:19s"><div class="lg">Company tab &rarr; Company details</div>
                    <div class="two">
                      <div><span class="lb">VAT number</span><span class="in">GB123456789</span></div>
                      <div><span class="lb">VAT %</span><span class="in a-ring" style="--d:20s">20</span></div>
                    </div></div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:12s">Number and rate, side by side</span>
                    <span class="chip warn a-pop" style="--d:21.5s">Each quote keeps the rate it was made with</span>
                  </div>
                </div>

                <!-- 5 — default deposit -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">The default deposit</div>
                  <div class="fs a-rise" style="--d:1s"><div class="lg">Default deposit</div>
                    <span class="hn" style="margin:0 0 .45rem">Seeds the deposit figure on every quote the moment it moves into Accepted. Overrideable per quote.</span>
                    <div class="rrow">
                      <span class="a-ring" style="--d:5s;border-radius:6px"><span class="rd"><i class="a-pop" style="--d:5.5s"></i></span> Percentage of total
                        <span class="in sm"><span class="a-type" style="--d:7.5s;--ts:2;--tt:.4s">50</span></span> %</span>
                      <span class="a-ring" style="--d:10s;border-radius:6px"><span class="rd"></span> Flat amount &pound; <span class="in sm">0</span></span>
                    </div></div>
                  <div class="acc a-rise mt" style="--d:15s">
                    <span><b>Quote BRI-2026-0001</b> &middot; &pound;400.00</span>
                    <span class="stamp a-stamp" style="--d:16.5s">ACCEPTED</span>
                    <span class="chip a-pop" style="--d:20s;border-color:var(--good)">Deposit &pound;200.00</span>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:24s">Change it on any one quote</span>
                    <span class="chip a-pop" style="--d:26s">A deposit you typed is never overwritten</span>
                  </div>
                </div>

                <!-- 6 — prices -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="fs a-rise" style="--d:.5s"><div class="lg">Prices on the customer quote</div>
                    <div class="opt"><span class="cb a-ring" style="--d:4s"><i>&#10003;</i></span><span>Show the price of each blind
                      <span class="hn">Ticked: the quote PDF and the customer&rsquo;s online quote list a unit price and line total for every blind. Unticked: those per-blind prices are hidden and the customer only sees the quote total.</span></span></div></div>
                  <div class="mqs">
                    <div class="a-rise" style="--d:9s">' . $mini('Ticked', true, true) . '</div>
                    <div class="a-rise" style="--d:15s">' . $mini('Unticked', false, true) . '</div>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:19s">Unticked &rarr; one total for the whole job</span></div>
                </div>

                <!-- 7 — sizes -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="fs a-rise" style="--d:.5s"><div class="lg">Sizes on the customer quote</div>
                    <div class="opt"><span class="cb a-ring" style="--d:3.5s"><i>&#10003;</i></span><span>Show the size of each blind
                      <span class="hn">Ticked: the quote PDF and the customer&rsquo;s online quote show each blind&rsquo;s size (width &times; drop) &mdash; right for trade orders. Unticked: sizes are hidden (retail style), leaving just the description.</span></span></div></div>
                  <div class="mqs">
                    <div class="a-rise" style="--d:7s">' . $mini('Ticked &mdash; trade', true, true) . '</div>
                    <div class="a-rise" style="--d:16s">' . $mini('Unticked &mdash; retail', true, false) . '</div>
                  </div>
                  <div class="row mt"><span class="chip a-pop" style="--d:11s">Trade customers check sizes before they order</span></div>
                </div>

                <!-- 8 — WT -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="fs a-rise" style="--d:.5s"><div class="lg">WT charge &mdash; the &ldquo;Wally tax&rdquo; (internal)</div>
                    <div class="opt"><span class="cb"><i class="a-pop" style="--d:4s">&#10003;</i></span><span>Enable the Wally tax <span style="font-weight:400;color:var(--faint)">(WT charge)</span>
                      <span class="hn">A discretionary charge you can quietly add to a quote for a job that&rsquo;s more hassle than it&rsquo;s worth. Adds a WT box on the quote builder. It&rsquo;s internal only.</span></span></div></div>
                  <div class="sct a-fade" style="--d:9s;font-size:.78rem">On the quote builder:</div>
                  <div class="wtrow a-rise" style="--d:9.5s">WT <small>(internal &mdash; never shown to the customer)</small>
                    &pound; <span class="in sm"><span class="a-type" style="--d:12.5s;--ts:5;--tt:.6s">45.00</span></span>
                    <span class="btns a-press" style="--d:14s">Set</span></div>
                  <span class="tst a-pop" style="--d:14.5s">WT set to &pound;45.00 (internal &mdash; not shown to the customer).</span>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:17s">Added before VAT</span>
                    <span class="chip a-pop" style="--d:20s">Spread across the blind prices</span>
                    <span class="chip warn a-pop" style="--d:26s">The customer never sees a WT line</span>
                  </div>
                </div>

                <!-- 9 — new order alerts -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="fs a-rise" style="--d:.5s"><div class="lg">New order alerts</div>
                    <span class="opt" style="font-weight:600">Email me when a customer accepts a quote online</span>
                    <span class="in a-ring mt" style="--d:2s;max-width:20rem;margin-top:.35rem"><span class="stk">
                      <span class="ph a-out" style="--d:3s">orders@yourbusiness.co.uk</span>
                      <span class="a-type" style="--d:3s;--ts:24;--tt:1.6s">orders@brightblinds.co.uk</span></span></span></div>
                  <div class="tcap a-fade" style="--d:5.5s">The customer&rsquo;s online quote</div>
                  <div class="row" style="align-items:flex-start;gap:.8rem">
                    <span class="btnp a-press" style="--d:6.5s;background:var(--good)">Accept</span>
                    <div class="mail a-drop" style="--d:9s"><div class="to">To: orders@brightblinds.co.uk</div>
                      <div class="subj">New order &mdash; BRI-2026-0001 accepted (Mrs Hall)</div>Good news &mdash; a new order has come in.</div>
                  </div>
                  <div class="row mt">
                    <span class="chip a-pop" style="--d:13s">Blank = alert off</span>
                    <span class="chip warn a-pop" style="--d:18s">Mistyped? It&rsquo;s ignored &mdash; check the box after saving</span>
                  </div>
                </div>

                <!-- 10 — auto-place + receipt -->
                <div class="sc" data-scene="10" data-len="' . $len(10) . '">
                  <div class="fs a-rise" style="--d:.5s"><div class="lg">Auto-place in-house orders</div>
                    <div class="opt"><span class="cb a-ring" style="--d:2s"><i>&#10003;</i></span><span>Send in-house orders straight to the workshop when accepted</span></div></div>
                  <div class="flow">
                    <span class="chip a-fly" style="--d:6s">Accepted &middot; all made in-house</span><span class="arrow a-fade" style="--d:7s">&rarr;</span><span class="chip a-pop" style="--d:8s;border-color:var(--good)">Straight to the workshop</span>
                    <span class="chip a-fly" style="--d:11s">Accepted &middot; has a bought-in blind</span><span class="arrow a-fade" style="--d:12s">&rarr;</span><span class="chip a-pop" style="--d:13s">Waits for <b>Place order</b></span>
                  </div>
                  <div class="fs a-rise mt" style="--d:17s"><div class="lg">Paid-in-full receipt</div>
                    <div class="opt"><span class="cb a-ring" style="--d:18s"><i>&#10003;</i></span><span>Email a receipt when an order is paid in full</span></div></div>
                  <div class="row">
                    <span class="chip a-pop" style="--d:21s">Balance &pound;0.00 &rarr; &#9993; Receipt</span>
                    <span class="chip a-pop" style="--d:26s">Once per order &middot; needs an email on file</span>
                  </div>
                </div>

                <!-- 11 — emails, footer, save -->
                <div class="sc" data-scene="11" data-len="' . $len(11) . '">
                  <div class="bnr a-drop" style="--d:24s">&#10003; Quote settings saved.</div>
                  <div class="two">
                    <div class="a-rise" style="--d:2.5s"><span class="lb">Email &ldquo;from&rdquo; name</span>
                      <span class="in"><span class="a-type" style="--d:3.5s;--ts:13;--tt:.9s">Bright Blinds</span></span></div>
                    <div class="a-rise" style="--d:6s"><span class="lb">Reply-to email</span>
                      <span class="in"><span class="a-type" style="--d:7s;--ts:23;--tt:1.3s">hello@brightblinds.co.uk</span></span></div>
                  </div>
                  <div class="a-rise mt" style="--d:10.5s;max-width:31rem"><span class="lb">Quote footer (printed at the bottom of the PDF)</span>
                    <div class="ta3"><span class="a-type" style="--d:12s;--ts:40;--tt:2s">Every blind carries our 12-month guarantee.</span></div></div>
                  <div class="mt"><span class="btnp a-ring" style="--d:19s">Save quote defaults</span></div>
                  <div class="a-move" style="--fx:80%;--fy:95%;--tx:6rem;--ty:12.6rem;--d:17s;--md:1.8s">' . $ptr . '</div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Getting here.</b> <b>Settings</b> (in the <b>Setup</b> group of the sidebar) &rarr; the <b>Quoting</b> tab. That tab is
             <b>four sections</b>, one under another: <b>Default margins</b>, <b>Measurements</b>, <b>Quote defaults</b> (this guide) and
             <b>Bank details for customer payments</b>. Each has <b>its own save button</b> &mdash; <b>Save quote defaults</b> saves this
             section and nothing else, so if you also changed a margin or the unit, press <b>Save margins</b> or <b>Save unit</b> too.</p>

          <ul class="steps">
            <li><b>Quote prefix</b> &mdash; the letters in front of every quote number (up to 20 characters, placeholder <code>e.g. BRI</code>).
                It is saved <b>in capitals</b> whatever you type. Numbers are built as <b>PREFIX-YEAR-0001</b>: <code>BRI-2026-0001</code>,
                then <code>0002</code>, counting afresh each year. <b>Leave it blank</b> and the first three letters of your company name are
                used (or <code>QTE</code> if there are none).</li>
            <li><b>A prefix belongs to one business.</b> If another account already uses it, the save is refused with a red message:
                <code>Quote prefix &ldquo;BRI&rdquo; is already in use. We&rsquo;ve put a free one &mdash; &ldquo;BRIG&rdquo; &mdash; in the box for you:
                click Save below to use it, or type your own and click Save. Two accounts sharing a prefix would end up with the same order
                numbers.</code> The suggestion is made from your own company name and is always free. Nothing else on the form is saved
                until you save again.</li>
            <li><b>VAT %</b> &mdash; no longer set here. Beside the prefix, under the <b>VAT %</b> heading, there is just the note
                <em>&ldquo;Set beside your VAT number on the Company tab.&rdquo;</em> &mdash; click <b>Company</b> in it to go there. The rate
                box now sits to the right of <b>VAT number</b> on the <b>Company</b> tab: greyed out until a VAT number is typed, then filled
                in with 20 (the UK standard rate) for you to change, and saved with <b>Save company details</b> (see
                <a href="/help/guide.php?g=settings-company"><b>Your company details</b></a>). Each quote <b>keeps the rate it was created
                with</b>, so a new rate only affects new quotes.</li>
            <li><b>Default deposit</b> &mdash; two radio buttons: <b>Percentage of total</b> (with a % box, 50 to start) or <b>Flat amount</b>
                (with a &pound; box). It <em>&ldquo;Seeds the deposit figure on every quote the moment it moves into Accepted. Overrideable per
                quote.&rdquo;</em> Only a blank deposit is filled &mdash; one you typed yourself is never overwritten.</li>
            <li><b>Prices on the customer quote</b> &mdash; <b>Show the price of each blind</b> (ticked to start). Ticked: the quote PDF and the
                customer&rsquo;s online quote list a unit price and line total for every blind. Unticked: those prices are hidden and the
                customer sees only the quote total.</li>
            <li><b>Sizes on the customer quote</b> &mdash; <b>Show the size of each blind</b> (ticked to start). Ticked shows each blind&rsquo;s
                <b>width &times; drop</b>, right for trade orders; unticked hides sizes (retail style), leaving just the description.</li>
            <li><b>WT charge &mdash; the &ldquo;Wally tax&rdquo; (internal)</b> &mdash; tick <b>Enable the Wally tax (WT charge)</b> and the quote
                builder gains a row <b>WT (internal &mdash; never shown to the customer)</b> with a &pound; box and a <b>Set</b> button
                (<code>WT set to &pound;45.00 (internal &mdash; not shown to the customer).</code> / <code>WT cleared.</code>). It is added
                <b>before VAT</b>; if &ldquo;Show the price of each blind&rdquo; is on it is spread across the blind prices so the figures still
                add up, otherwise it simply lifts the total. The customer never sees &ldquo;WT&rdquo;, &ldquo;Wally tax&rdquo; or a separate line.
                Without the tick the builder refuses: <code>WT is not enabled &mdash; turn it on in Settings &rarr; Quoting first.</code></li>
            <li><b>New order alerts</b> &mdash; <b>Email me when a customer accepts a quote online</b>. When a customer clicks <b>Accept</b> on a
                quote link you sent, this address is emailed (<em>&ldquo;New order &mdash; BRI-2026-0001 accepted (Mrs Hall)&rdquo;</em>). Leave it
                blank to turn it off.</li>
            <li><b>Auto-place in-house orders</b> &mdash; <b>Send in-house orders straight to the workshop when accepted</b> (ticked to start).
                Only when <b>every</b> blind on the quote is one you make yourself; a quote with any bought-in / supplier line still needs the
                manual <b>Place order</b> so the supplier gets emailed.</li>
            <li><b>Paid-in-full receipt</b> &mdash; <b>Email a receipt when an order is paid in full</b> (ticked to start). When a payment
                brings the balance to zero the customer is emailed a thank-you receipt &mdash; <b>once</b> per order, and only if they have an
                email on file.</li>
            <li><b>Email &ldquo;from&rdquo; name</b> and <b>Reply-to email</b> &mdash; the name your emails are sent under and where replies go.</li>
            <li><b>Quote footer (printed at the bottom of the PDF)</b> &mdash; a line or two of your own (a guarantee, a lead time). It also
                shows at the foot of the customer&rsquo;s online quote. Leave it empty for none.</li>
            <li><b>Save quote defaults</b> &mdash; the page reloads with a green <b>&ldquo;Quote settings saved.&rdquo;</b> at the top.</li>
          </ul>

          <div class="heads"><span class="hi">&#9888;</span><div><b>A mistyped alert address is dropped without a word.</b> If the
             <b>New order alerts</b> address isn&rsquo;t a proper email, that one setting is skipped &mdash; you still get &ldquo;Quote settings
             saved.&rdquo; and the old address comes back. Glance at the box after saving.</div></div>

          <p><b>Factory login only.</b> Three more blocks appear only for the factory (super-admin) login:
             <b>Default sale type</b> (<em>When you click New, start as</em> <b>Trade</b> / <b>Retail</b>),
             <b>Factory &mdash; new order received</b> (<em>Email the factory when a trade order lands in the queue</em>; blank uses the address
             above) and <b>Auto-order bought-in items</b> (<em>Automatically order bought-in blinds from their supplier when an order is
             placed</em> &mdash; <b>off by default</b>, because it sends a real purchase order; a supplier with no email is left for you to order
             by hand).</p>

          <div class="oops"><b>If a save goes wrong:</b>
             <code>Could not save settings: &hellip;</code> is followed by the reason &mdash; pass it to whoever looks after your setup.</div>

          <p class="prose"><b>Markup and discount aren&rsquo;t here.</b> The grey line in the form says so: they are set per product
             (<b>Products</b> &rarr; Edit &rarr; Pricing overrides), with your usual margin under <b>Default margins</b> at the top of this tab.</p>',
        'script'  => $script,
];
