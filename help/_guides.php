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
        'css'     => '
          .gd #nameBox{ position:relative; transition:border-color .25s, box-shadow .25s; }
          .gd #nameBox .ph{ color:var(--faint); transition:opacity .2s; }
          .gd #nameBox .val{ position:absolute; left:.5rem; color:var(--ink); opacity:0; transition:opacity .2s; }
          .gd .stage[data-step="1"] #nameBox{ border-color:var(--err); box-shadow:0 0 0 3px var(--err-wash); }
          .gd .stage[data-step="1"] .hint{ opacity:1; }
          .gd .stage[data-step="1"] .save, .gd .stage[data-step="3"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="2"] #nameBox .ph, .gd .stage[data-step="3"] #nameBox .ph{ opacity:0; }
          .gd .stage[data-step="2"] #nameBox .val, .gd .stage[data-step="3"] #nameBox .val{ opacity:1; }
          .gd .stage[data-step="3"] .toast{ opacity:1; transform:none; }
          @media (prefers-reduced-motion:reduce){ .gd #nameBox, .gd #nameBox .ph, .gd #nameBox .val{ transition:none !important; } }',
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
                    <div class="box" id="nameBox"><span class="ph">Your company name</span><span class="val">Demo Blinds Ltd</span></div></div>
                  <div class="fld"><label>Contact name</label><div class="box filled">Alex Sample</div></div>
                  <div class="fld"><label>Email</label><div class="box filled">hello@demoblinds.example</div></div>
                  <div class="fld"><label>Phone</label><div class="box filled">01234 567890</div></div>
                  <div class="hint">&#9888; Pop your company name in — it goes on every quote.</div>
                </div>
                <div class="save">Save company details</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Filling your details in…</b>
                  <b class="c1 err"><span class="n">2</span> Company name\'s blank — the one field it won\'t skip.</b>
                  <b class="c2"><span class="n">3</span> Pop the name in.</b>
                  <b class="c3 good"><span class="n">4</span> Saved.</b>
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
            ['0:00', 'Settings opens on the Company tab.',            'Let\'s get your company details in — these print on every quote you send.', 0],
            ['0:06', 'Cursor fills contact, email, phone.',          'Name, email and phone, so customers can reach a real person.', 0],
            ['0:13', 'Clicks Save — company name still blank. Nudge appears.', 'Save — and it stops me. I\'ve left the company name blank, and that\'s the one it won\'t allow. Better it catches that than a customer.', 1],
            ['0:20', 'Types the company name into the field.',        'Pop the name in.', 2],
            ['0:26', 'Clicks Save. Green "saved" appears.',           'Save again, and your details are done.', 3],
        ],
    ],

    'settings-logo' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company logo',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Add the logo that prints on every quote and your online accept page.',
        'lede'    => 'Your logo sits at the top of every quote PDF and on the page where customers
                      accept online. Add it once here and it appears on everything you send.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .logo-drop{ display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
            border:1.5px dashed var(--border-dashed,#cbd5e1); border-radius:12px; padding:1rem 1.1rem; background:var(--panel); transition:opacity .25s; }
          .gd .logo-drop-in{ display:flex; align-items:center; gap:.7rem; }
          .gd .ld-ic{ font-size:1.5rem; }
          .gd .logo-drop small{ display:block; color:var(--faint); font-size:.72rem; }
          .gd .logo-preview{ display:none; align-items:center; gap:1rem; margin-top:.2rem; }
          .gd .logo-mark{ font-weight:800; font-size:1.15rem; letter-spacing:-.02em; color:#1c2733; background:#fff; border:1px solid var(--line); border-radius:8px; padding:.5rem .8rem; }
          .gd .logo-mark b{ color:#2563eb; }
          .gd .logo-mark.sm{ font-size:.85rem; padding:.3rem .55rem; }
          .gd .lp-actions{ display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; }
          .gd .ghostbtn{ border:1px solid var(--line); border-radius:7px; padding:.3rem .6rem; font-size:.78rem; font-weight:600; color:var(--soft); }
          .gd .muted{ color:var(--faint); font-size:.8rem; }
          .gd .mini-quote{ display:none; margin-top:1rem; border:1px solid var(--line); border-radius:10px; padding:.8rem .9rem; background:#fff; }
          .gd .mq-head{ display:flex; align-items:center; justify-content:space-between; gap:1rem; border-bottom:1px solid #eef2f6; padding-bottom:.6rem; }
          .gd .mq-meta{ text-align:right; font-size:.72rem; color:#5b6b7b; font-weight:700; }
          .gd .mq-lines{ display:flex; flex-direction:column; gap:.4rem; padding-top:.7rem; }
          .gd .mq-lines span{ height:8px; border-radius:4px; background:#eef2f6; }
          .gd .mq-lines span:nth-child(1){ width:70%; } .gd .mq-lines span:nth-child(2){ width:88%; } .gd .mq-lines span:nth-child(3){ width:52%; }
          .gd .stage[data-step="1"] .logo-drop, .gd .stage[data-step="2"] .logo-drop{ opacity:.45; }
          .gd .stage[data-step="1"] .logo-preview, .gd .stage[data-step="2"] .logo-preview{ display:flex; }
          .gd .stage[data-step="2"] .mini-quote{ display:block; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Company logo</div>
                <div class="logo-drop">
                  <div class="logo-drop-in"><span class="ld-ic">&#128247;</span>
                    <div><b>Upload logo</b><small>JPG, PNG or GIF &middot; up to 2&nbsp;MB</small></div></div>
                  <div class="save">Upload logo</div>
                </div>
                <div class="logo-preview">
                  <div class="logo-mark">Demo<b>Blinds</b></div>
                  <div class="lp-actions"><span class="ghostbtn">Remove logo</span><span class="muted">Looks good.</span></div>
                </div>
                <div class="mini-quote">
                  <div class="mq-head"><div class="logo-mark sm">Demo<b>Blinds</b></div>
                    <div class="mq-meta">QUOTE #1042<br><span style="font-weight:400">Demo Blinds Ltd</span></div></div>
                  <div class="mq-lines"><span></span><span></span><span></span></div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Your logo prints on every quote.</b>
                  <b class="c1"><span class="n">2</span> Uploaded — there it is. Wrong one? Remove it.</b>
                  <b class="c2 good"><span class="n">3</span> And there it is, heading your quote.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Still on the <b>Company</b> tab, scroll down to <b>Company logo</b>. This is the logo that
             prints at the top of every quote PDF and on the online page where customers accept.</p>
          <ul class="steps">
            <li><b>Click Upload logo</b> and choose your file — a <b>JPG, PNG or GIF</b>, up to <b>2&nbsp;MB</b>.
                A see-through (transparent) PNG looks tidiest on the page.</li>
            <li>It shows straight away as a <b>preview</b>. Happy with it? You\'re done — it\'s saved.</li>
            <li>Wrong file? Click <b>Remove logo</b> and upload another. To change it later, just upload
                again — the button then reads <b>Replace logo</b>.</li>
          </ul>
          <p><b>Tip:</b> a wide logo on a see-through background sits best in the quote header. Very large
             images over 2&nbsp;MB are turned away — shrink it first if that happens. No logo yet? Quotes
             simply show your company name until you add one.</p>',
        'script'  => [
            ['0:00', 'Company logo section.',                 'Your logo goes at the top of every quote — let\'s add it.', 0],
            ['0:06', 'Clicks Upload, picks a file.',          'Click Upload, and pick your logo — a JPG, PNG or GIF, up to two megabytes.', 1],
            ['0:12', 'Preview appears; Remove available.',     'Up it pops. Wrong one? Remove it and choose another.', 1],
            ['0:18', 'Logo shown heading a mini quote.',       'And there it is, heading your quote. That\'s the logo done.', 2],
        ],
    ],
];
