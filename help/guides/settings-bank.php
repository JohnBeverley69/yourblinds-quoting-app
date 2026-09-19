<?php
declare(strict_types=1);

/**
 * Guide: settings-bank
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Bank details for payments',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Let customers pay by bank transfer — prints a "How to pay" block on the quote.',
        'lede'    => 'Enter your bank details once and a <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> block prints on the
                      customer&rsquo;s quote and invoice, with the quote number as the reference.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .bfields{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem .7rem; }
          .gd .bfields .wide{ grid-column:1 / -1; }
          .gd .htp{ display:none; margin-top:1rem; border:1px solid #e5e7eb; border-radius:10px; background:#fff; color:#1c2733; padding:.7rem .85rem; font-size:.82rem; }
          .gd .stage[data-step="2"] .htp, .gd .stage[data-step="3"] .htp{ display:block; }
          .gd .htp-h{ font-weight:700; margin-bottom:.4rem; }
          .gd .htp-r{ display:flex; justify-content:space-between; padding:.18rem 0; border-bottom:1px solid #f5f7fa; }
          .gd .htp-r span{ color:#5b6b7b; }
          .gd .htp-r.ref{ border-bottom:none; }
          .gd .stage[data-step="3"] .htp-r.ref{ background:#e5edff; border-radius:6px; padding:.18rem .4rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Bank details for customer payments</div>
                <div class="bfields">
                  <div class="fld wide"><label>Account name</label><div class="box f1"><span class="ph">Account name</span><span class="val">Demo Blinds Ltd</span></div></div>
                  <div class="fld"><label>Sort code</label><div class="box f1"><span class="ph">00-00-00</span><span class="val">20-00-00</span></div></div>
                  <div class="fld"><label>Account number</label><div class="box f1"><span class="ph">Account number</span><span class="val">12345678</span></div></div>
                  <div class="fld wide"><label>Payment note (optional)</label><div class="box f1"><span class="ph">e.g. use your quote number as the reference</span><span class="val">Please use your quote number as the reference</span></div></div>
                </div>
                <div class="htp">
                  <div class="htp-h">How to pay &mdash; bank transfer</div>
                  <div class="htp-r"><span>Account name</span><b>Demo Blinds Ltd</b></div>
                  <div class="htp-r"><span>Sort code</span><b>20-00-00</b></div>
                  <div class="htp-r"><span>Account no.</span><b>12345678</b></div>
                  <div class="htp-r ref"><span>Reference</span><b>BRI-1042</b></div>
                </div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Type in your bank details.</b>
                  <b class="c2"><span class="n">2</span> They print as a &ldquo;How to pay&rdquo; block.</b>
                  <b class="c3 good"><span class="n">3</span> Quote number = the payment reference.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>On the <b>Quoting</b> tab, <b>Bank details for customer payments</b> lets customers pay you by bank transfer.</p>
          <ul class="steps">
            <li>Enter your <b>Account name</b>, <b>Sort code</b> and <b>Account number</b>.</li>
            <li>Add an optional <b>Payment note</b> &mdash; e.g. &ldquo;Please use your quote number as the reference&rdquo;.</li>
            <li>These print as a <b>&ldquo;How to pay &mdash; bank transfer&rdquo;</b> block on the customer&rsquo;s quote and invoice,
                with the <b>quote number</b> suggested as the reference so payments are easy to match.</li>
          </ul>
          <p><b>Leave the fields blank to hide the block entirely.</b> Then <b>Save bank details</b>.</p>',
        'script'  => [
            ['0:00', 'Bank detail fields type in.',       'Pop in your bank details — account name, sort code and account number.', 1],
            ['0:07', '"How to pay" block appears.',       'They print on the customer\'s quote as a "How to pay" block, so they can pay by transfer.', 2],
            ['0:13', 'Reference row highlighted.',        'The quote number goes on as the reference, so payments are easy to match. Leave the fields blank and the block just doesn\'t show.', 3],
        ],
];
