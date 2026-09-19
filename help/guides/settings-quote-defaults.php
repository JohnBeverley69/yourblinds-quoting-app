<?php
declare(strict_types=1);

/**
 * Guide: settings-quote-defaults
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Quote defaults',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Quote number prefix, VAT, deposit, what the customer sees, receipts and footer.',
        'lede'    => 'Your quote housekeeping in one place &mdash; the prefix on your quote numbers, your VAT rate,
                      the deposit that lands on every quote, and what the customer does (and doesn\'t) see.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .fset{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; margin-top:.7rem; }
          .gd .fset-lg{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.45rem; }
          .gd .frow + .frow, .gd .qfield{ margin-top:.7rem; }
          .gd .deprow{ display:flex; align-items:center; gap:.4rem .7rem; flex-wrap:wrap; font-size:.82rem; }
          .gd .numbox{ display:inline-flex; align-items:center; justify-content:center; min-width:2.7rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.15rem .4rem; font-size:.8rem; color:var(--ink); background:var(--surface); }
          .gd .numbox.faint{ color:var(--faint); }
          .gd .unit{ color:var(--faint); font-size:.82rem; }
          .gd .depval{ opacity:0; transition:opacity .25s; font-weight:600; color:var(--ink); }
          .gd .chkrow{ display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:var(--soft); margin:.3rem 0; }
          .gd .muted{ color:var(--faint); }
          /* the form starts BLANK (poster = step 0); fields tagged f1/f4 fill via the shared engine */
          .gd .stage[data-step="2"] .depbox{ border-color:var(--accent) !important; box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="2"] .depval, .gd .stage[data-step="3"] .depval, .gd .stage[data-step="4"] .depval, .gd .stage[data-step="5"] .depval{ opacity:1; }
          .gd .stage[data-step="2"] .rperc, .gd .stage[data-step="3"] .rperc, .gd .stage[data-step="4"] .rperc, .gd .stage[data-step="5"] .rperc{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="2"] .rperc .dot, .gd .stage[data-step="3"] .rperc .dot, .gd .stage[data-step="4"] .rperc .dot, .gd .stage[data-step="5"] .rperc .dot{ border-color:var(--accent); }
          .gd .stage[data-step="2"] .rperc .dot::after, .gd .stage[data-step="3"] .rperc .dot::after, .gd .stage[data-step="4"] .rperc .dot::after, .gd .stage[data-step="5"] .rperc .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .stage[data-step="3"] .tShow, .gd .stage[data-step="4"] .tShow, .gd .stage[data-step="5"] .tShow, .gd .stage[data-step="3"] .tRec, .gd .stage[data-step="4"] .tRec, .gd .stage[data-step="5"] .tRec{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="5"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="5"] .toast{ opacity:1; transform:none; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Quote defaults saved</div>
                <div class="card-t">Quote defaults</div>
                <div class="frow">
                  <div class="fld"><label>Quote prefix</label><div class="box f1"><span class="ph">e.g. BRI</span><span class="val">BRI</span></div></div>
                  <div class="fld"><label>VAT %</label><div class="box f1"><span class="ph">20</span><span class="val">20</span></div></div>
                </div>
                <div class="fset">
                  <div class="fset-lg">Default deposit</div>
                  <div class="deprow">
                    <span class="radio rperc"><span class="dot"></span> Percentage of total</span>
                    <span class="numbox depbox"><span class="depval">50</span></span><span class="unit">%</span>
                    <span class="radio rflat"><span class="dot"></span> Flat amount</span>
                    <span class="unit">&pound;</span><span class="numbox faint">0.00</span>
                  </div>
                </div>
                <div class="fset">
                  <div class="chkrow"><span class="tick tShow">&check;</span> Show the price of each blind</div>
                  <div class="chkrow"><span class="tick tWt">&check;</span> Enable the Wally tax <span class="muted">(WT charge &mdash; internal)</span></div>
                  <div class="chkrow"><span class="tick tRec">&check;</span> Email a receipt when an order is paid in full</div>
                </div>
                <div class="frow">
                  <div class="fld"><label>Email &ldquo;from&rdquo; name</label><div class="box f4"><span class="ph">Your name</span><span class="val">Demo Blinds</span></div></div>
                  <div class="fld"><label>Reply-to email</label><div class="box f4"><span class="ph">you@&hellip;</span><span class="val">hello@demoblinds.example</span></div></div>
                </div>
                <div class="fld qfield"><label>Quote footer</label>
                  <div class="ta f4"><span class="ph">A line for the bottom of the quote&hellip;</span><span class="val">Thank you for your custom &mdash; 5-year guarantee.</span></div></div>
                <div class="save">Save quote defaults</div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Your quote prefix and VAT rate.</b>
                  <b class="c2"><span class="n">2</span> A default deposit &mdash; percentage or flat.</b>
                  <b class="c3"><span class="n">3</span> What the customer sees, the Wally tax, and receipts.</b>
                  <b class="c4"><span class="n">4</span> Your email name, reply-to and footer.</b>
                  <b class="c5 good"><span class="n">5</span> Save &mdash; all set.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>On the <b>Quoting</b> tab, <b>Quote defaults</b> is your quote housekeeping. Work down it:</p>
          <ul class="steps">
            <li><b>Quote prefix</b> &mdash; the letters in front of your quote numbers (e.g. <code>BRI</code> &rarr; BRI-1042).</li>
            <li><b>VAT %</b> &mdash; your VAT rate (usually 20). Set it to 0 if you&rsquo;re not VAT-registered.</li>
            <li><b>Default deposit</b> &mdash; seeded onto every quote when it&rsquo;s accepted. Choose <b>a percentage of the
                total</b> or <b>a flat &pound; amount</b>. You can still change it on any single quote.</li>
            <li><b>Show the price of each blind</b> &mdash; ticked, the customer&rsquo;s quote lists a price per blind;
                unticked, they see only the overall total.</li>
            <li><b>WT charge &mdash; the &ldquo;Wally tax&rdquo;</b> &mdash; a discretionary charge you can quietly add to a quote
                for a job that&rsquo;s going to be more hassle than it&rsquo;s worth (an awkward customer, a fiddly fit &mdash; you know the ones).
                Tick this to switch on a little <b>WT</b> box in the quote builder. It is <b>completely internal</b>: the customer
                <b>never</b> sees the letters &ldquo;WT&rdquo;, the words &ldquo;Wally tax&rdquo;, or a separate line anywhere on their quote
                or invoice. It&rsquo;s added <b>before VAT</b>, and if &ldquo;Show the price of each blind&rdquo; is on it&rsquo;s
                <b>spread across the blind prices</b> proportionally so the figures still add up; otherwise it just lifts the total.</li>
            <li><b>Paid-in-full receipt</b> &mdash; when an order&rsquo;s balance hits zero, the customer is automatically emailed a
                thank-you receipt (once per order).</li>
            <li><b>Email &ldquo;from&rdquo; name</b> and <b>Reply-to email</b> &mdash; how your emails to customers are signed, and where their replies land.</li>
            <li><b>Quote footer</b> &mdash; a line or two printed at the bottom of every quote PDF (a thank-you, a lead time, whatever you like).</li>
          </ul>
          <p>Then <b>Save quote defaults</b>.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Heads up:</b> <b>&ldquo;Show the price of each blind&rdquo;</b> changes what
             the customer sees &mdash; ticked, their quote lists a price per blind; unticked, they see only the total. Worth a quick
             preview after you change it. The <b>Wally tax</b> is the opposite: it&rsquo;s internal only, so the customer never sees it.</div></div>',
        'script'  => [
            ['0:00', 'Prefix + VAT typed in.',            'Set the prefix for your quote numbers, and your VAT rate.', 1],
            ['0:07', 'Deposit: 50% percentage selected.', 'A default deposit that lands on every quote — a percentage, or a flat amount.', 2],
            ['0:15', 'Three checkboxes set.',             'Choose whether the customer sees each blind\'s price; switch on the Wally tax — a discretionary internal charge — if you want it; and the automatic paid-in-full receipt.', 3],
            ['0:26', 'Email name, reply-to, footer fill.', 'Add your email from-name, a reply-to address, and a footer line for the bottom of the quote.', 4],
            ['0:34', 'Save; saved toast.',                'Save, and your quote defaults are set.', 5],
        ],
];
