<?php
declare(strict_types=1);

/**
 * Guide: settings-company
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company details',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Set up the details that head every quote — the first thing to do, done once.',
        'lede'    => 'The first thing to set up, and you only do it <b>once</b>. Your company
                      details head every quote and invoice you send, so it looks like it came
                      from <b>you</b>. Here\'s the whole thing, start to finish.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .frow3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.7rem .8rem; margin-top:.7rem; }
          .gd .frow + .frow{ margin-top:.7rem; }
          .gd .vfield{ margin-top:.7rem; }
          .gd .vhint{ font-size:.64rem; color:var(--faint); margin-top:.2rem; }
          .gd #nameBox{ transition:border-color .25s, box-shadow .25s; }
          /* poster (step 0) is blank; contact/email/phone = f1, VAT+address = f2, name = f4 (shared fill engine).
             The slip: Save with the name still blank -> nudge at step 3; the name types in at step 4. */
          .gd .stage[data-step="3"] #nameBox{ border-color:var(--err); box-shadow:0 0 0 3px var(--err-wash); }
          .gd .stage[data-step="3"] .hint{ opacity:1; }
          .gd .stage[data-step="3"] .save, .gd .stage[data-step="5"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="5"] .toast{ opacity:1; transform:none; }
          @media(max-width:620px){ .gd .frow3{ grid-template-columns:1fr; } }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Company details saved</div>
                <div class="card-t">Company details</div>
                <div class="frow">
                  <div class="fld"><label>Company name <span class="req">*</span></label>
                    <div class="box f4" id="nameBox"><span class="ph">Your company name</span><span class="val">Demo Blinds Ltd</span></div></div>
                  <div class="fld"><label>Contact name</label><div class="box f1"><span class="ph">Contact name</span><span class="val">Alex Sample</span></div></div>
                </div>
                <div class="frow">
                  <div class="fld"><label>Email</label><div class="box f1"><span class="ph">you@&hellip;</span><span class="val">hello@demoblinds.example</span></div></div>
                  <div class="fld"><label>Phone</label><div class="box f1"><span class="ph">Phone</span><span class="val">01234 567890</span></div></div>
                </div>
                <div class="fld vfield"><label>VAT number</label>
                  <div class="box f2"><span class="ph">e.g. GB123456789</span><span class="val">GB 123 4567 89</span></div>
                  <div class="vhint">Leave blank if you&rsquo;re not VAT-registered.</div></div>
                <div class="frow">
                  <div class="fld"><label>Address line 1</label><div class="box f2"><span class="ph">Address line 1</span><span class="val">Unit 4, Sample Way</span></div></div>
                  <div class="fld"><label>Address line 2</label><div class="box f2"><span class="ph">Address line 2</span><span class="val">Sample Business Park</span></div></div>
                </div>
                <div class="frow3">
                  <div class="fld"><label>Town</label><div class="box f2"><span class="ph">Town</span><span class="val">Leeds</span></div></div>
                  <div class="fld"><label>County</label><div class="box f2"><span class="ph">County</span><span class="val">West Yorkshire</span></div></div>
                  <div class="fld"><label>Postcode</label><div class="box f2"><span class="ph">Postcode</span><span class="val">LS1 1AA</span></div></div>
                </div>
                <div class="hint">&#9888; Pop your company name in &mdash; it goes on every quote.</div>
                <div class="save">Save company details</div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Fill in your details &mdash; contact, email, phone&hellip;</b>
                  <b class="c2"><span class="n">2</span> &hellip;your VAT number and address.</b>
                  <b class="c3 err"><span class="n">3</span> Save &mdash; company name&rsquo;s blank, the one it won&rsquo;t skip.</b>
                  <b class="c4"><span class="n">4</span> Pop the name in.</b>
                  <b class="c5 good"><span class="n">5</span> Saved &mdash; every field&rsquo;s in.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Open <b>Settings</b> from the sidebar. You land on the <b>Company</b> tab — that\'s this one.
             Work down the form:</p>
          <ul class="steps">
            <li><b>Company name</b> — the name that heads your quotes. This one\'s required (the little red
                <span class="req">*</span> gives it away).</li>
            <li><b>Contact name, Email, Phone</b> — how customers reach a real person.</li>
            <li><b>VAT number</b> — only if you\'re VAT-registered. Not? Leave it blank, nothing bad happens.</li>
            <li><b>Address, Town, County, Postcode</b> — your business address for the paperwork.</li>
          </ul>
          <p>Then hit <b>Save company details</b> — and that\'s it. You won\'t need to come back here.</p>
          <div class="oops"><b>If you miss the company name</b> and hit Save, the app nudges you rather than
             letting a nameless quote out. Add it, save again — hard to get wrong.</div>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Contact, email, phone type in.',            'Let\'s get your company details in — these print on every quote you send.', 1],
            ['0:07', 'VAT number and address type in.',           'Contact, email and phone; your VAT number if you have one; and your business address.', 2],
            ['0:15', 'Clicks Save — name blank; nudge appears.',  'Save — and it stops me. I\'ve left the company name blank, and that\'s the one it won\'t allow. Better it catches that than a customer.', 3],
            ['0:23', 'Types the company name in.',                'Pop the name in…', 4],
            ['0:28', 'Clicks Save. Green "saved" appears.',       '…and save. Every field\'s in, and your details are done.', 5],
        ],
];
