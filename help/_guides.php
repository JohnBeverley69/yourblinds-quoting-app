<?php
declare(strict_types=1);

/**
 * Guided-walkthrough registry — shared by help/guide.php (renders one) and
 * help/index.php (lists them). Returns the $GUIDES array.
 *
 * Each guide, keyed by slug (?g=slug):
 *   aud     all | admin | super  (who may open it)
 *   section the Help & guide section it belongs under
 *   title   page + link title
 *   eyebrow small label above the title
 *   blurb   one-line summary for the index card
 *   lede    one-paragraph intro (HTML allowed)
 *   open    href to the real screen this guide covers ("Open the screen →")
 *   demo    HTML for the animated walkthrough (uses the .gd demo styles)
 *   body    HTML for the written steps
 *   script  array of [beat, on-screen, voiceover] — feeds the table AND the
 *           narration (the voiceover column is read aloud, in order).
 */

return [
    'settings-company' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company details',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Set up the details that head every quote — the first thing to do, done once.',
        'lede'    => 'The first thing to set up, and you only do it <b>once</b>. Your company
                      details head every quote and invoice you send, so it looks like it came
                      from <b>you</b>. Here\'s the whole thing, start to finish.',
        'open'    => '/admin/settings.php',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage">
                <div class="toast">&check; Company details saved</div>
                <div class="card-t">Company details</div>
                <div class="frow">
                  <div class="fld"><label>Company name <span class="req">*</span></label>
                    <div class="box" id="nameBox"><span class="ph">Your company name</span><span class="val">Demo Blinds Ltd</span></div></div>
                  <div class="fld"><label>Contact name</label><div class="box filled">Alex Sample</div></div>
                  <div class="fld"><label>Email</label><div class="box filled">hello@demoblinds.example</div></div>
                  <div class="fld"><label>Phone</label><div class="box filled">01234 567890</div></div>
                  <div class="hint">&#9888; Pop your company name in — it goes on every quote.</div>
                </div>
                <div class="save">Save company details</div>
                <div class="caps">
                  <b><span class="n">1</span> Fill in your details…</b>
                  <b><span class="n">2</span> Company name\'s blank — the one field it won\'t skip.</b>
                  <b><span class="n">3</span> Pop the name in.</b>
                  <b><span class="n">4</span> Saved.</b>
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
        'script'  => [
            ['0:00', 'Settings opens on the Company tab.',            'Let\'s get your company details in — these print on every quote you send.'],
            ['0:06', 'Cursor fills contact, email, phone.',          'Name, email and phone, so customers can reach a real person.'],
            ['0:13', 'Clicks Save — company name still blank. Nudge appears.', 'Save — and it stops me. I\'ve left the company name blank, and that\'s the one it won\'t allow. Better it catches that than a customer.'],
            ['0:20', 'Types the company name into the field.',        'Pop the name in.'],
            ['0:26', 'Clicks Save. Green "saved" appears.',           'Save again, and your details are done.'],
        ],
    ],
];
