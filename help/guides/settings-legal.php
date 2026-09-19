<?php
declare(strict_types=1);

/**
 * Guide: settings-legal
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Terms, privacy & emails',
        'eyebrow' => 'Settings · Legal',
        'blurb'   => 'The wording on your quotes, and the email customers get when they accept.',
        'lede'    => 'The wording that prints on your quotes &mdash; Terms, Privacy &mdash; plus the thank-you email
                      customers get when they accept. Templates are pre-filled; edit to suit.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .legal-ed{ border:1px solid var(--line); border-radius:10px; padding:.65rem .8rem; font-size:.82rem; background:var(--panel); }
          .gd .le-h, .gd .lp-h{ font-size:.64rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; margin-bottom:.4rem; }
          .gd .le-body{ color:var(--soft); line-height:1.55; }
          .gd .tok{ background:var(--accent-wash); color:var(--accent-ink); border-radius:4px; padding:0 .2rem; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:.9em; }
          .gd .legal-pv{ display:none; border:1px solid var(--line); border-radius:10px; padding:.65rem .8rem; font-size:.82rem; margin-top:.7rem; background:var(--surface); }
          .gd .lp-body{ color:var(--ink); line-height:1.55; }
          .gd .stage[data-step="1"] .legal-pv, .gd .stage[data-step="2"] .legal-pv{ display:block; }
          .gd .thanks{ display:none; margin-top:.7rem; border:1px dashed var(--border-dashed,#cbd5e1); border-radius:10px; padding:.55rem .75rem; font-size:.82rem; color:var(--soft); }
          .gd .stage[data-step="2"] .thanks{ display:block; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Terms, privacy &amp; thank-you email</div>
                <div class="legal-ed"><div class="le-h">Terms &amp; Conditions</div>
                  <div class="le-body">These terms are between <span class="tok">{{company_name}}</span> and
                    <span class="tok">{{customer_name}}</span> for quote <span class="tok">{{quote_number}}</span>. Goods remain ours until paid in full&hellip;</div></div>
                <div class="legal-pv"><div class="lp-h">Preview &mdash; example customer</div>
                  <div class="lp-body">These terms are between <b>Demo Blinds Ltd</b> and <b>Alex Sample</b> for quote <b>BRI-1042</b>. Goods remain ours until paid in full&hellip;</div></div>
                <div class="thanks">&#9993; <b>Thank-you email</b> &mdash; sent when a customer accepts, using the same placeholders.</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Edit the ready-made Terms&hellip;</b>
                  <b class="c1"><span class="n">2</span> &hellip;the preview fills in a real example.</b>
                  <b class="c2 good"><span class="n">3</span> Plus the thank-you email on accept.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Legal</b> tab holds the wording that prints on your quotes, plus the email customers get when they accept.</p>
          <ul class="steps">
            <li><b>Terms &amp; Conditions</b> and <b>Privacy Policy</b> come <b>pre-filled with a template</b> &mdash; edit them to
                suit your business. They print, personalised, at the bottom of the quote PDF and the online quote.</li>
            <li>The <b>placeholders</b> in curly brackets (e.g. <code>{{company_name}}</code>, <code>{{customer_name}}</code>,
                <code>{{quote_number}}</code>) fill in automatically on each quote. The <b>Preview</b> under each box shows a real
                example so you can check it reads right.</li>
            <li>The <b>thank-you email</b> is sent automatically when a customer accepts a quote &mdash; edit it, or leave it empty to send none.</li>
          </ul>
          <p><b>Leave any box empty to show nothing.</b> Then <b>Save terms, privacy &amp; email</b>.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Not legal advice.</b> The templates are a starting point only &mdash;
             have your Terms and Privacy wording reviewed before you rely on them.</div></div>',
        'script'  => [
            ['0:00', 'Terms editor with {{tokens}}.',      'Your Terms and Privacy come ready-written — edit them to suit your business.', 0],
            ['0:07', 'Preview fills the placeholders.',    'The bits in brackets fill in on each quote, and the preview shows you exactly how it\'ll read.', 1],
            ['0:15', 'Thank-you email card.',              'There\'s a thank-you email too, sent automatically when a customer accepts.', 2],
        ],
];
