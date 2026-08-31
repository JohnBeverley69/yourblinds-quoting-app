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
          .gd .box{ position:relative; }
          .gd .box .ph{ color:var(--faint); }
          .gd .box .val{ position:absolute; inset:0; display:flex; align-items:center; padding:0 .5rem; background:var(--panel); color:var(--ink); opacity:0; transition:opacity .25s; white-space:nowrap; overflow:hidden; }
          .gd .frow3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.7rem .8rem; margin-top:.7rem; }
          .gd .frow + .frow{ margin-top:.7rem; }
          .gd .vfield{ margin-top:.7rem; }
          .gd .vhint{ font-size:.64rem; color:var(--faint); margin-top:.2rem; }
          .gd #nameBox{ transition:border-color .25s, box-shadow .25s; }
          /* the rest of the form fills in as the voice-over reaches it (step 1) */
          .gd .stage[data-step="1"] .g2 .val, .gd .stage[data-step="2"] .g2 .val, .gd .stage[data-step="3"] .g2 .val, .gd .stage[data-step="4"] .g2 .val{ opacity:1; }
          /* company name typed in from step 3 */
          .gd .stage[data-step="3"] #nameBox .val, .gd .stage[data-step="4"] #nameBox .val{ opacity:1; }
          /* the slip: Save with the name blank -> nudge at step 2 */
          .gd .stage[data-step="2"] #nameBox{ border-color:var(--err); box-shadow:0 0 0 3px var(--err-wash); }
          .gd .stage[data-step="2"] .hint{ opacity:1; }
          /* both Saves press */
          .gd .stage[data-step="2"] .save, .gd .stage[data-step="4"] .save{ transform:scale(.96); filter:brightness(1.25); }
          /* saved toast */
          .gd .stage[data-step="4"] .toast{ opacity:1; transform:none; }
          @media(max-width:620px){ .gd .frow3{ grid-template-columns:1fr; } }
          @media (prefers-reduced-motion:reduce){ .gd .box .val, .gd #nameBox{ transition:none !important; } }',
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
                </div>
                <div class="frow">
                  <div class="fld"><label>Email</label><div class="box filled">hello@demoblinds.example</div></div>
                  <div class="fld"><label>Phone</label><div class="box filled">01234 567890</div></div>
                </div>
                <div class="fld vfield"><label>VAT number</label>
                  <div class="box g2"><span class="ph">e.g. GB123456789</span><span class="val">GB 123 4567 89</span></div>
                  <div class="vhint">Leave blank if you&rsquo;re not VAT-registered.</div></div>
                <div class="frow">
                  <div class="fld"><label>Address line 1</label><div class="box g2"><span class="ph">Address line 1</span><span class="val">Unit 4, Sample Way</span></div></div>
                  <div class="fld"><label>Address line 2</label><div class="box g2"><span class="ph">Address line 2</span><span class="val">Sample Business Park</span></div></div>
                </div>
                <div class="frow3">
                  <div class="fld"><label>Town</label><div class="box g2"><span class="ph">Town</span><span class="val">Leeds</span></div></div>
                  <div class="fld"><label>County</label><div class="box g2"><span class="ph">County</span><span class="val">West Yorkshire</span></div></div>
                  <div class="fld"><label>Postcode</label><div class="box g2"><span class="ph">Postcode</span><span class="val">LS1 1AA</span></div></div>
                </div>
                <div class="hint">&#9888; Pop your company name in &mdash; it goes on every quote.</div>
                <div class="save">Save company details</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Fill in your details &mdash; contact, email, phone&hellip;</b>
                  <b class="c1"><span class="n">2</span> &hellip;your VAT number and address.</b>
                  <b class="c2 err"><span class="n">3</span> Save &mdash; company name&rsquo;s blank, the one it won&rsquo;t skip.</b>
                  <b class="c3"><span class="n">4</span> Pop the name in.</b>
                  <b class="c4 good"><span class="n">5</span> Saved &mdash; every field&rsquo;s in.</b>
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
            ['0:00', 'Company tab; details filling, name blank.', 'Let\'s get your company details in — these print on every quote you send.', 0],
            ['0:07', 'VAT number and address fill in.',           'Contact, email and phone; your VAT number if you have one; and your business address.', 1],
            ['0:15', 'Clicks Save — name blank; nudge appears.',  'Save — and it stops me. I\'ve left the company name blank, and that\'s the one it won\'t allow. Better it catches that than a customer.', 2],
            ['0:23', 'Types the company name in.',                'Pop the name in…', 3],
            ['0:28', 'Clicks Save. Green "saved" appears.',       '…and save. Every field\'s in, and your details are done.', 4],
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

    'settings-dashboard' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'The dashboard joke',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'One friendly toggle — a "Joke of the day" for your team on the dashboard.',
        'lede'    => 'A tiny bit of fun: a different one-liner on your dashboard each day, just for your
                      team (never customers). It\'s on by default — here\'s how to keep it or switch it off.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .chk{ display:inline-flex; align-items:center; gap:.5rem; font-weight:600; font-size:.85rem; color:var(--ink); }
          .gd .tick{ width:18px; height:18px; border-radius:5px; border:1px solid var(--border-strong,#d1d5db); background:var(--accent); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; transition:background .2s, color .2s; }
          .gd .hlp{ display:block; color:var(--faint); font-size:.75rem; margin-top:.35rem; }
          .gd .stage[data-step="1"] .tick{ background:var(--surface); color:transparent; }
          .gd .stage[data-step="2"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="2"] .toast{ opacity:1; transform:none; }
          .gd .dashprev{ margin-top:1rem; border:1px solid var(--line); border-radius:10px; background:var(--panel); padding:.7rem .85rem; }
          .gd .dp-h{ font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:var(--faint); font-weight:700; margin-bottom:.5rem; }
          .gd .joke{ background:var(--surface); border:1px solid var(--line); border-radius:8px; padding:.55rem .7rem; font-size:.84rem; color:var(--ink); transition:opacity .25s, transform .25s; }
          .gd .stage[data-step="1"] .joke{ opacity:0; transform:translateY(-4px); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Saved</div>
                <div class="card-t">Dashboard</div>
                <label class="chk"><span class="tick">&check;</span> &#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard</label>
                <span class="hlp">Staff only &mdash; never shown to customers. Dismissible each day.</span>
                <div class="save">Save</div>
                <div class="dashprev">
                  <div class="dp-h">Your dashboard</div>
                  <div class="joke">&#128516; Why did the scarecrow win an award? He was outstanding in his field.</div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> A joke on your dashboard &mdash; on by default.</b>
                  <b class="c1"><span class="n">2</span> Not for you? Untick it and it&rsquo;s gone.</b>
                  <b class="c2 good"><span class="n">3</span> Ticked, saved &mdash; done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Still on the <b>Company</b> tab, the <b>Dashboard</b> section has one friendly little option.</p>
          <ul class="steps">
            <li><b>&#128516; Show a &ldquo;Joke of the day&rdquo; on the dashboard</b> &mdash; tick it and your team
                sees a different one-liner each day when they open the app. It&rsquo;s <b>staff only</b>; customers
                never see it.</li>
            <li>Anyone can <b>dismiss</b> the day&rsquo;s joke if they&rsquo;ve had enough &mdash; it&rsquo;s back tomorrow.</li>
            <li>Not for your team? <b>Untick it</b> and it disappears. Either way, press <b>Save</b>.</li>
          </ul>
          <p>That&rsquo;s the whole section &mdash; a bit of light relief, nothing that touches your quotes or customers.</p>',
        'script'  => [
            ['0:00', 'Dashboard section; joke on.',    'Your dashboard can show a little joke each day, just for your team, never customers.', 0],
            ['0:07', 'Untick; joke disappears.',       'Not for you? Untick it, and it\'s gone.', 1],
            ['0:12', 'Tick again; Save; saved toast.', 'Leave it ticked, hit Save, and you\'re done.', 2],
        ],
    ],

    'settings-calendar' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Calendar options',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Three choices: show money on jobs, your map app, and morning/afternoon slots.',
        'lede'    => 'The <b>Calendar</b> section has three quick choices — whether jobs show their money,
                      which map app your address links open in, and whether measure visits are booked as
                      morning or afternoon windows. Each saves on its own.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .opt{ border:1px solid var(--line); border-radius:10px; padding:.6rem .75rem; margin-bottom:.55rem; transition:box-shadow .2s, border-color .2s; }
          .gd .opt-h{ display:flex; align-items:center; gap:.5rem; font-weight:600; font-size:.82rem; color:var(--ink); }
          .gd .opt small{ display:block; color:var(--faint); font-size:.72rem; margin-top:.2rem; }
          .gd .tick{ width:17px; height:17px; border-radius:5px; border:1px solid var(--border-strong,#d1d5db); background:var(--surface); color:transparent; display:inline-flex; align-items:center; justify-content:center; font-size:.66rem; transition:background .2s,color .2s; }
          .gd .stage[data-step="1"] .t-money, .gd .stage[data-step="2"] .t-money, .gd .stage[data-step="3"] .t-money, .gd .stage[data-step="4"] .t-money{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .t-slots, .gd .stage[data-step="4"] .t-slots{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="1"] .opt-money, .gd .stage[data-step="2"] .opt-nav, .gd .stage[data-step="3"] .opt-slots{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .jobchip{ margin-top:.5rem; border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px; padding:.35rem .55rem; font-size:.74rem; background:var(--surface); display:inline-block; }
          .gd .jobchip .money{ display:none; color:var(--soft); margin-top:.2rem; }
          .gd .jobchip .paid{ display:none; margin-left:.35rem; color:var(--good); font-weight:700; }
          .gd .stage[data-step="1"] .jobchip .money, .gd .stage[data-step="2"] .jobchip .money, .gd .stage[data-step="3"] .jobchip .money, .gd .stage[data-step="4"] .jobchip .money{ display:block; }
          .gd .stage[data-step="1"] .jobchip .paid, .gd .stage[data-step="2"] .jobchip .paid, .gd .stage[data-step="3"] .jobchip .paid, .gd .stage[data-step="4"] .jobchip .paid{ display:inline; }
          .gd .pill{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:999px; padding:.18rem .55rem; font-size:.74rem; margin-right:.35rem; color:var(--soft); }
          .gd .pill.sel{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); }
          .gd .stage[data-step="0"] .rg, .gd .stage[data-step="1"] .rg, .gd .stage[data-step="2"] .rw, .gd .stage[data-step="3"] .rw, .gd .stage[data-step="4"] .rw{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="0"] .rg .dot, .gd .stage[data-step="1"] .rg .dot, .gd .stage[data-step="2"] .rw .dot, .gd .stage[data-step="3"] .rw .dot, .gd .stage[data-step="4"] .rw .dot{ border-color:var(--accent); }
          .gd .stage[data-step="0"] .rg .dot::after, .gd .stage[data-step="1"] .rg .dot::after, .gd .stage[data-step="2"] .rw .dot::after, .gd .stage[data-step="3"] .rw .dot::after, .gd .stage[data-step="4"] .rw .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .opens{ display:block; margin-top:.4rem; font-size:.74rem; color:var(--soft); }
          .gd .nn-waze{ display:none; }
          .gd .stage[data-step="2"] .nn-google, .gd .stage[data-step="3"] .nn-google, .gd .stage[data-step="4"] .nn-google{ display:none; }
          .gd .stage[data-step="2"] .nn-waze, .gd .stage[data-step="3"] .nn-waze, .gd .stage[data-step="4"] .nn-waze{ display:inline; }
          .gd .windows{ display:none; gap:.4rem; margin-top:.5rem; }
          .gd .stage[data-step="3"] .windows, .gd .stage[data-step="4"] .windows{ display:flex; }
          .gd .win{ border:1px solid var(--line); border-radius:7px; padding:.22rem .5rem; font-size:.72rem; color:var(--soft); background:var(--surface); }
          .gd .stage[data-step="4"] .toast{ opacity:1; transform:none; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="toast">&check; Saved</div>
                <div class="card-t">Calendar</div>
                <div class="opt opt-money">
                  <div class="opt-h"><span class="tick t-money">&check;</span> &#128183; Show order value + balance on the calendar</div>
                  <div class="jobchip"><b>10:00 &middot; Mrs Patel &middot; Fit</b>
                    <span class="money">Order &pound;540 &middot; Paid &pound;540 &middot; Balance &pound;0 <span class="paid">PAID</span></span></div>
                </div>
                <div class="opt opt-nav">
                  <div class="opt-h">&#129517; Navigation app</div>
                  <div style="margin-top:.5rem"><span class="radio rg"><span class="dot"></span> Google Maps</span><span class="radio rw"><span class="dot"></span> Waze</span></div>
                  <span class="opens">Tap an address &rarr; opens in <b class="nn-google">Google Maps</b><b class="nn-waze">Waze</b></span>
                </div>
                <div class="opt opt-slots">
                  <div class="opt-h"><span class="tick t-slots">&check;</span> &#128344; Morning / afternoon booking slots</div>
                  <small>A window, not an exact time. Bookings per window: <b>4</b></small>
                  <div class="windows"><span class="win">Morning 9&ndash;1</span><span class="win">Afternoon 1&ndash;5</span></div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Three quick calendar choices.</b>
                  <b class="c1"><span class="n">2</span> Show the money on jobs &mdash; or keep it off.</b>
                  <b class="c2"><span class="n">3</span> The map app your address links open in.</b>
                  <b class="c3"><span class="n">4</span> Morning or afternoon windows for measures.</b>
                  <b class="c4 good"><span class="n">5</span> Each saves on its own &mdash; done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Still on the <b>Company</b> tab, scroll to <b>Calendar</b>. Three separate choices, each with its own <b>Save</b>:</p>
          <ul class="steps">
            <li><b>&#128183; Show order value + balance on the calendar</b> &mdash; each job linked to a quote shows its
                order value, what&rsquo;s been paid (deposit + payments) and the balance left, with a <b>PAID</b> badge once
                it&rsquo;s settled.</li>
            <li><b>&#129517; Navigation app</b> &mdash; when you tap an address on your schedule or the day calendar, it opens
                in the app you pick here: <b>Google Maps</b> (the default) or <b>Waze</b> if your fitters prefer it for live traffic.</li>
            <li><b>&#128344; Morning / afternoon booking slots</b> &mdash; for <b>measure (quote) visits</b>, offer
                <b>Morning (9am&ndash;1pm)</b> or <b>Afternoon (1pm&ndash;5pm)</b> instead of an exact time, so you promise a
                window. Set <b>bookings per window, per day</b> (e.g. 4) &mdash; once a window is full, it can&rsquo;t be booked.
                Fittings aren&rsquo;t affected.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Heads up about the money:</b> this shows the figures to
             <b>everyone who can open the calendar</b> &mdash; including people whose logins normally hide costs, like fitters.
             It ignores the per-person <b>&ldquo;Can view costs&rdquo;</b> option on the <b>Users</b> page, just for the calendar.
             Leave it off if anyone who sees the calendar shouldn&rsquo;t see the figures.</div></div>
          <p>Each of the three has its <b>own Save button</b> &mdash; change one and save it; they don&rsquo;t depend on each other.</p>',
        'script'  => [
            ['0:00', 'Calendar section.',                 'Your calendar has three little choices. Here they are.', 0],
            ['0:06', 'Money on; job shows figures.',       'Show each job\'s value, what\'s paid, and what\'s left. But careful — everyone who can open the calendar sees it, even fitters. Leave it off if that\'s not what you want.', 1],
            ['0:14', 'Waze selected.',                     'Next, the map app your address links open in. Google Maps, or Waze if your fitters prefer it.', 2],
            ['0:22', 'Slots on; windows appear.',          'And morning or afternoon windows for measure visits, so you give a window, not an exact time. Set how many fit in each.', 3],
            ['0:31', 'Saved.',                             'Each choice saves on its own. That\'s your calendar sorted.', 4],
        ],
    ],

    'settings-margins' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Default margins',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'Markup vs margin — the pricing choice people get wrong, with a live calculator.',
        'lede'    => 'The choice that trips people up. You set your profit as <b>markup</b> or as <b>margin</b> —
                      and they are <b>not</b> the same number. Mix them up and every quote is priced wrong. Here\'s
                      the difference, plainly, with a calculator to play with.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .mlabel{ font-size:.78rem; color:var(--soft); font-weight:600; margin-bottom:.45rem; }
          .gd .mradios{ margin-bottom:.9rem; }
          .gd .stage[data-step="0"] .rmk, .gd .stage:not([data-step="0"]) .rmg{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="0"] .rmk .dot, .gd .stage:not([data-step="0"]) .rmg .dot{ border-color:var(--accent); }
          .gd .stage[data-step="0"] .rmk .dot::after, .gd .stage:not([data-step="0"]) .rmg .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .mrates{ margin-bottom:.7rem; }
          .gd .mhint{ font-size:.66rem; color:var(--accent); margin-top:.2rem; }
          .gd .wmg, .gd .vmg, .gd .hmg{ display:none; }
          .gd .stage:not([data-step="0"]) .wmk, .gd .stage:not([data-step="0"]) .vmk, .gd .stage:not([data-step="0"]) .hmk{ display:none; }
          .gd .stage:not([data-step="0"]) .wmg, .gd .stage:not([data-step="0"]) .vmg, .gd .stage:not([data-step="0"]) .hmg{ display:inline; }
          .gd .exwrap{ min-height:52px; }
          .gd .ex{ display:none; align-items:center; gap:.5rem; font-size:1rem; padding:.55rem .8rem; border:1px solid var(--line); border-radius:10px; background:var(--panel); }
          .gd .ex .exc{ color:var(--soft); }
          .gd .ex .exs{ color:var(--ink); font-size:1.05rem; }
          .gd .ex .exp{ color:var(--good); font-size:.8rem; margin-left:auto; }
          .gd .ex.danger{ border-color:var(--err); background:var(--err-wash); }
          .gd .ex.danger .exs{ color:var(--err); }
          .gd .stage[data-step="0"] .e0, .gd .stage[data-step="1"] .e1, .gd .stage[data-step="2"] .e2, .gd .stage[data-step="3"] .e3{ display:flex; }
          /* interactive pop-out */
          .gd .calc-open{ margin-top:.7rem; display:inline-flex; align-items:center; gap:.45rem; cursor:pointer; font:inherit; font-weight:700; font-size:.92rem; border:none; border-radius:9px; padding:.55rem 1rem; background:var(--accent); color:#fff; }
          .gd .calc-open:hover{ background:var(--accent-ink); }
          .gd .calc-modal{ display:none; position:fixed; inset:0; z-index:50; background:rgba(10,15,22,.55); align-items:center; justify-content:center; padding:1rem; }
          .gd .calc-modal.open{ display:flex; }
          .gd .calc-box{ position:relative; width:min(560px,100%); max-height:90vh; overflow:auto; background:var(--surface); border:1px solid var(--line); border-radius:16px; box-shadow:var(--gd-shadow); padding:1.3rem 1.4rem; color:var(--ink); }
          .gd .calc-box h3{ margin:0 0 1rem; font-size:1.15rem; }
          .gd .calc-x{ position:absolute; top:.55rem; right:.7rem; border:none; background:none; font-size:1.5rem; line-height:1; cursor:pointer; color:var(--faint); }
          .gd .calc-row{ display:flex; flex-direction:column; gap:.7rem; margin-bottom:1rem; }
          .gd .calc-row label{ font-size:.85rem; color:var(--soft); font-weight:600; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
          .gd .calc-row input[type=number]{ width:6rem; font:inherit; padding:.3rem .5rem; border:1px solid var(--line); border-radius:7px; background:var(--panel); color:var(--ink); }
          .gd .calc-row input[type=range]{ flex:1; min-width:11rem; accent-color:var(--accent); }
          .gd .calc-cards{ display:grid; grid-template-columns:1fr 1fr; gap:.7rem; }
          .gd .calc-card{ border:1px solid var(--line); border-radius:12px; padding:.8rem .9rem; background:var(--panel); text-align:center; }
          .gd .cc-h{ font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700; color:var(--faint); }
          .gd .cc-sell{ font-size:1.5rem; font-weight:800; margin:.2rem 0; letter-spacing:-.02em; }
          .gd .cc-sub{ font-size:.78rem; color:var(--soft); }
          .gd .calc-card.cc-margin .cc-sell{ color:var(--accent-ink); }
          .gd .calc-card.danger{ border-color:var(--err); background:var(--err-wash); }
          .gd .calc-card.danger .cc-sell{ color:var(--err); }
          .gd .calc-bars{ margin:.9rem 0 .3rem; display:flex; flex-direction:column; gap:.4rem; }
          .gd .bar{ height:12px; background:var(--line-2); border-radius:6px; overflow:hidden; }
          .gd .bar span{ display:block; height:100%; border-radius:6px; transition:width .15s; width:0; }
          .gd .bar-mk span{ background:var(--soft); }
          .gd .bar-mg span{ background:var(--accent); }
          .gd .calc-note{ font-size:.9rem; color:var(--ink); margin-top:.7rem; line-height:1.5; }
          .gd .calc-eq{ font-size:.88rem; color:var(--soft); margin-top:.5rem; }
          .gd .calc-eq .boom{ display:block; margin-top:.4rem; color:var(--err); font-weight:600; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Default margins</div>
                <div class="mlabel">Enter your margins as</div>
                <div class="mradios"><span class="radio rmk"><span class="dot"></span> Markup&nbsp;%</span><span class="radio rmg"><span class="dot"></span> Margin&nbsp;%</span></div>
                <div class="frow mrates">
                  <div class="fld"><label>Default price-table <span class="wmk">markup</span><span class="wmg">margin</span> %</label>
                    <div class="box filled"><span class="vmk">100</span><span class="vmg">50</span></div>
                    <div class="mhint"><span class="hmk">&asymp; 50% margin</span><span class="hmg">&asymp; 100% markup</span></div></div>
                  <div class="fld"><label>Default options &amp; extras <span class="wmk">markup</span><span class="wmg">margin</span> %</label>
                    <div class="box filled"><span class="vmk">100</span><span class="vmg">50</span></div>
                    <div class="mhint"><span class="hmk">&asymp; 50% margin</span><span class="hmg">&asymp; 100% markup</span></div></div>
                </div>
                <div class="exwrap">
                  <div class="ex e0"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;150</b> <span class="exp">profit &pound;50</span></div>
                  <div class="ex e1"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;200</b> <span class="exp">profit &pound;100</span></div>
                  <div class="ex e2"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;500</b> <span class="exp">profit &pound;400</span></div>
                  <div class="ex e3 danger"><span class="exc">Cost &pound;100</span> &rarr; <b class="exs">Sell &pound;1,000</b> <span class="exp">profit &pound;900</span></div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Markup: profit added on top of cost.</b>
                  <b class="c1"><span class="n">2</span> Same 50% as margin? That&rsquo;s &pound;200, not &pound;150.</b>
                  <b class="c2"><span class="n">3</span> Push margin up and the price climbs fast.</b>
                  <b class="c3 err"><span class="n">4</span> 90% margin = ten times cost. Careful!</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>On the <b>Quoting</b> tab, open <b>Default margins</b>. This sets the profit the pricing engine adds &mdash;
             once, for everything &mdash; so you don&rsquo;t have to set it on every product.</p>
          <p><b>The one choice that matters</b> is &ldquo;Enter your margins as&rdquo;: <b>Markup&nbsp;%</b> or <b>Margin&nbsp;%</b>.
             They are <b>not</b> the same number, and mixing them up quietly under- or over-charges every quote.</p>
          <ul class="steps">
            <li><b>Markup</b> is added <b>on top of your cost</b>. Cost &pound;100 + 50% markup = <b>&pound;150</b>.</li>
            <li><b>Margin</b> is profit as a slice of the <b>sell price</b>. A 50% margin on &pound;100 cost = <b>&pound;200</b>
                (your cost is half the sell price).</li>
            <li>So the <b>same &ldquo;50%&rdquo;</b> means &pound;150 as markup but &pound;200 as margin &mdash; pick the one you actually mean.</li>
            <li>Set your <b>price-table</b> rate and your <b>options &amp; extras</b> rate, then <b>Save margins</b>. The little
                blue hint under each box shows the equivalent (e.g. &ldquo;&asymp; 100% markup&rdquo;) so you can sense-check it.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Why margin is the dangerous one:</b> as a margin climbs past about
             <b>70&ndash;80%</b> the sell price runs away &mdash; an <b>80% margin</b> is 5&times; your cost, <b>90%</b> is 10&times;,
             <b>95%</b> is 20&times;. A tiny change near the top swings the price wildly. Markup is far steadier (80% markup is only
             1.8&times; cost). If your figures suddenly look mad, check you haven&rsquo;t typed a margin where you meant a markup.</div></div>
          <p><b>Not sure which you want?</b> Have a play &mdash; this shows both side by side and lets you watch the margin figure take off.</p>
          <button type="button" class="calc-open" id="mmOpen">&#128200; Open the markup vs margin calculator</button>
          <div class="calc-modal" id="mmModal">
            <div class="calc-box" role="dialog" aria-modal="true" aria-label="Markup versus margin calculator">
              <button type="button" class="calc-x" id="mmClose" aria-label="Close">&times;</button>
              <h3>Markup vs margin &mdash; see the difference</h3>
              <div class="calc-row">
                <label>Your cost &pound; <input id="mmCost" type="number" value="100" min="0" step="1"></label>
                <label>The % you enter: <b id="mmPctlbl">50%</b> <input id="mmPct" type="range" min="0" max="99" value="50"></label>
              </div>
              <div class="calc-cards">
                <div class="calc-card"><div class="cc-h">As markup</div><div class="cc-sell" id="mmMkSell">&pound;150.00</div><div class="cc-sub">profit <span id="mmMkProfit">&pound;50.00</span></div></div>
                <div class="calc-card cc-margin" id="mmMgCard"><div class="cc-h">As margin</div><div class="cc-sell" id="mmMgSell">&pound;200.00</div><div class="cc-sub">profit <span id="mmMgProfit">&pound;100.00</span></div></div>
              </div>
              <div class="calc-bars"><div class="bar bar-mk"><span id="mmBarMk"></span></div><div class="bar bar-mg"><span id="mmBarMg"></span></div></div>
              <div class="calc-note" id="mmNote"></div>
              <div class="calc-eq" id="mmEq"></div>
            </div>
          </div>',
        'js'      => <<<'JS'
(function(){
  var open = document.getElementById('mmOpen'), modal = document.getElementById('mmModal');
  if (!open || !modal) return;
  var q = function(id){ return document.getElementById(id); };
  var cost=q('mmCost'), pct=q('mmPct'), pctl=q('mmPctlbl'),
      mkSell=q('mmMkSell'), mkPro=q('mmMkProfit'), mgSell=q('mmMgSell'), mgPro=q('mmMgProfit'),
      mgCard=q('mmMgCard'), barMk=q('mmBarMk'), barMg=q('mmBarMg'), note=q('mmNote'), eq=q('mmEq');
  function money(n){ if(!isFinite(n)) return 'off the chart'; return '£' + n.toLocaleString('en-GB',{minimumFractionDigits:2, maximumFractionDigits:2}); }
  function calc(){
    var c = Math.max(0, parseFloat(cost.value)||0), p = parseFloat(pct.value)||0;
    pctl.textContent = p + '%';
    var mk = c*(1 + p/100), mkP = mk - c;
    var mg = (p>=100) ? Infinity : c/(1 - p/100), mgP = (mg===Infinity) ? Infinity : mg - c;
    mkSell.textContent = money(mk); mkPro.textContent = money(mkP);
    mgSell.textContent = money(mg); mgPro.textContent = money(mgP);
    var ref = Math.max(mk, isFinite(mg)?mg:mk, c) || 1;
    barMk.style.width = (mk/ref*100) + '%';
    barMg.style.width = ((isFinite(mg)?mg/ref:1)*100) + '%';
    mgCard.classList.toggle('danger', p >= 70);
    note.innerHTML = 'Type <b>' + p + '</b> and mean <b>markup</b> → you charge <b>' + money(mk) +
                     '</b>. Mean <b>margin</b> → you charge <b>' + money(mg) + '</b>. Same number, very different price.';
    var eqMk = (p>=100) ? null : (p<=0 ? 0 : p*100/(100-p));
    var txt = 'A <b>' + p + '% margin</b> is the same as <b>' + (eqMk===null ? '∞' : Math.round(eqMk)) + '% markup</b>.';
    if (isFinite(mg) && c>0 && p>=75){
      txt += '<span class="boom">⚠ At ' + p + '% margin the sell price is ' + (mg/c).toFixed(1) +
             '× your cost — margins explode as you near 100%.</span>';
    }
    eq.innerHTML = txt;
  }
  function show(){ modal.classList.add('open'); calc(); }
  function hide(){ modal.classList.remove('open'); }
  open.addEventListener('click', show);
  q('mmClose').addEventListener('click', hide);
  modal.addEventListener('click', function(e){ if (e.target === modal) hide(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hide(); });
  cost.addEventListener('input', calc); pct.addEventListener('input', calc);
})();
JS,
        'script'  => [
            ['0:00', 'Default margins; markup; £100 → £150.', 'First choice: do you enter your profit as markup, or as margin? They\'re not the same thing.', 0],
            ['0:07', 'Switch to margin; £100 → £200.',        'Fifty percent markup on a hundred-pound cost is a hundred and fifty. But fifty percent margin is two hundred. Same number, bigger price.', 1],
            ['0:16', 'Margin 80; £100 → £500.',               'And margin climbs fast. Push it to eighty percent and you\'re already at five hundred.', 2],
            ['0:23', 'Margin 90; £100 → £1,000; danger.',     'Ninety percent margin is ten times your cost. Near the top the figures go wild, so pick the one you really mean.', 3],
        ],
    ],

    'settings-measurements' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Measurement units',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'The unit your team measures in — mm, cm, m or inches. Change it any time.',
        'lede'    => 'Just picks the unit your team types and reads sizes in. Sizes are stored the same way
                      underneath, so it changes nothing about your existing quotes.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .selrow{ margin:.3rem 0 .2rem; }
          .gd .selectbox span{ display:none; }
          .gd .stage[data-step="0"] .u0, .gd .stage[data-step="1"] .u1, .gd .stage[data-step="2"] .u2{ display:inline; }
          .gd .exwrap{ min-height:48px; margin-top:.4rem; }
          .gd .ex .exs{ color:var(--ink); font-size:1.05rem; font-weight:700; }
          .gd .stage[data-step="0"] .e0, .gd .stage[data-step="1"] .e1, .gd .stage[data-step="2"] .e2{ display:flex; }
          .gd .tipchip{ margin-top:.7rem; font-size:.78rem; color:var(--soft); background:var(--panel); border:1px dashed var(--border-dashed,#cbd5e1); border-radius:8px; padding:.4rem .6rem; display:inline-block; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Measurements</div>
                <div class="mlabel" style="font-size:.78rem;color:var(--soft);font-weight:600;margin-bottom:.4rem">Default measurement unit</div>
                <div class="selrow"><span class="selectbox"><span class="u0">Millimetres (mm)</span><span class="u1">Centimetres (cm)</span><span class="u2">Inches (in)</span></span></div>
                <div class="exwrap">
                  <div class="ex e0"><span class="exc">Width</span> <b class="exs">1500 mm</b></div>
                  <div class="ex e1"><span class="exc">Width</span> <b class="exs">150 cm</b></div>
                  <div class="ex e2"><span class="exc">Width</span> <b class="exs">59 in</b></div>
                </div>
                <div class="tipchip">&#128161; Tip: type <b>1.5m</b> or <b>60in</b> straight into a size box for a one-off.</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Pick the unit your team measures in.</b>
                  <b class="c1"><span class="n">2</span> Same size &mdash; just shown your way.</b>
                  <b class="c2"><span class="n">3</span> Type a unit any time for a one-off.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>On the <b>Quoting</b> tab, <b>Measurements</b> sets the unit your team types and reads blind sizes in.</p>
          <ul class="steps">
            <li>Choose the <b>Default measurement unit</b> &mdash; <b>mm, cm, m</b> or <b>inches</b>.</li>
            <li>Sizes are always stored the same way underneath, so you can <b>change this any time</b> without touching existing quotes.</li>
            <li>On a single quote you can <b>override the unit</b> for that job, or just <b>type a unit directly</b> in a size box
                (e.g. <code>60in</code>, <code>1.5m</code>) for a one-off.</li>
          </ul>
          <p>Then <b>Save unit</b>. That&rsquo;s the whole section.</p>',
        'script'  => [
            ['0:00', 'Unit = mm; Width 1500 mm.', 'Pick the unit your team measures in — millimetres, centimetres, metres or inches.', 0],
            ['0:07', 'Unit = cm; Width 150 cm.',  'It\'s only how sizes show. Underneath everything\'s stored the same, so you can switch whenever you like.', 1],
            ['0:14', 'Unit = in; Width 59 in.',   'And for a one-off, type the unit right on the quote — like one-point-five metres, or sixty inches.', 2],
        ],
    ],

    'settings-quote-defaults' => [
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
          .gd .deprow{ display:flex; align-items:center; gap:.5rem 1rem; flex-wrap:wrap; font-size:.82rem; }
          .gd .depval{ opacity:0; transition:opacity .25s; font-weight:600; color:var(--ink); }
          .gd .chkrow{ display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:var(--soft); margin:.3rem 0; }
          .gd .muted{ color:var(--faint); }
          /* the form fills in as the voice-over reaches each part */
          .gd .g0 .val{ opacity:1; }
          .gd .stage[data-step="1"] .depval, .gd .stage[data-step="2"] .depval, .gd .stage[data-step="3"] .depval, .gd .stage[data-step="4"] .depval{ opacity:1; }
          .gd .stage[data-step="1"] .rperc, .gd .stage[data-step="2"] .rperc, .gd .stage[data-step="3"] .rperc, .gd .stage[data-step="4"] .rperc{ color:var(--ink); font-weight:600; }
          .gd .stage[data-step="1"] .rperc .dot, .gd .stage[data-step="2"] .rperc .dot, .gd .stage[data-step="3"] .rperc .dot, .gd .stage[data-step="4"] .rperc .dot{ border-color:var(--accent); }
          .gd .stage[data-step="1"] .rperc .dot::after, .gd .stage[data-step="2"] .rperc .dot::after, .gd .stage[data-step="3"] .rperc .dot::after, .gd .stage[data-step="4"] .rperc .dot::after{ content:""; position:absolute; inset:3px; border-radius:50%; background:var(--accent); }
          .gd .stage[data-step="2"] .tShow, .gd .stage[data-step="3"] .tShow, .gd .stage[data-step="4"] .tShow, .gd .stage[data-step="2"] .tRec, .gd .stage[data-step="3"] .tRec, .gd .stage[data-step="4"] .tRec{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .g3 .val, .gd .stage[data-step="4"] .g3 .val{ opacity:1; }
          .gd .stage[data-step="4"] .save{ transform:scale(.96); filter:brightness(1.25); }
          .gd .stage[data-step="4"] .toast{ opacity:1; transform:none; }',
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
                  <div class="fld"><label>Quote prefix</label><div class="box g0"><span class="ph">e.g. BRI</span><span class="val">BRI</span></div></div>
                  <div class="fld"><label>VAT %</label><div class="box g0"><span class="ph">20</span><span class="val">20</span></div></div>
                </div>
                <div class="fset">
                  <div class="fset-lg">Default deposit</div>
                  <div class="deprow">
                    <span class="radio rperc"><span class="dot"></span> Percentage of total</span>
                    <span class="depval">50 %</span>
                    <span class="radio rflat"><span class="dot"></span> Flat amount &pound;&mdash;</span>
                  </div>
                </div>
                <div class="fset">
                  <div class="chkrow"><span class="tick tShow">&check;</span> Show the price of each blind</div>
                  <div class="chkrow"><span class="tick tWt">&check;</span> Enable the WT charge <span class="muted">(internal)</span></div>
                  <div class="chkrow"><span class="tick tRec">&check;</span> Email a receipt when an order is paid in full</div>
                </div>
                <div class="frow">
                  <div class="fld"><label>Email &ldquo;from&rdquo; name</label><div class="box g3"><span class="ph">Your name</span><span class="val">Demo Blinds</span></div></div>
                  <div class="fld"><label>Reply-to email</label><div class="box g3"><span class="ph">you@&hellip;</span><span class="val">hello@demoblinds.example</span></div></div>
                </div>
                <div class="fld qfield"><label>Quote footer</label>
                  <div class="ta g3"><span class="ph">A line for the bottom of the quote&hellip;</span><span class="val">Thank you for your custom &mdash; 5-year guarantee.</span></div></div>
                <div class="save">Save quote defaults</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Your quote prefix and VAT rate.</b>
                  <b class="c1"><span class="n">2</span> A default deposit &mdash; percentage or flat.</b>
                  <b class="c2"><span class="n">3</span> What the customer sees, WT, and receipts.</b>
                  <b class="c3"><span class="n">4</span> Your email name, reply-to and footer.</b>
                  <b class="c4 good"><span class="n">5</span> Save &mdash; all set.</b>
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
            <li><b>WT charge (internal)</b> &mdash; adds a discretionary &ldquo;WT&rdquo; box in the quote builder. It goes on
                <b>before VAT</b> and is <b>completely internal</b> &mdash; the customer never sees the letters &ldquo;WT&rdquo; or a separate line.</li>
            <li><b>Paid-in-full receipt</b> &mdash; when an order&rsquo;s balance hits zero, the customer is automatically emailed a
                thank-you receipt (once per order).</li>
            <li><b>Email &ldquo;from&rdquo; name</b> and <b>Reply-to email</b> &mdash; how your emails to customers are signed, and where their replies land.</li>
            <li><b>Quote footer</b> &mdash; a line or two printed at the bottom of every quote PDF (a thank-you, a lead time, whatever you like).</li>
          </ul>
          <p>Then <b>Save quote defaults</b>.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Two the customer sees:</b> &ldquo;Show the price of each blind&rdquo;
             and your bank &ldquo;How to pay&rdquo; block both change what&rsquo;s on the customer&rsquo;s quote &mdash; so glance at a preview
             after changing them. The <b>WT charge</b> is the opposite: purely internal, never shown.</div></div>',
        'script'  => [
            ['0:00', 'Prefix + VAT filled.',              'Set the prefix for your quote numbers, and your VAT rate.', 0],
            ['0:07', 'Deposit: 50% percentage selected.', 'A default deposit that lands on every quote — a percentage, or a flat amount.', 1],
            ['0:15', 'Three checkboxes set.',             'Choose whether the customer sees each blind\'s price, switch on the internal WT charge if you use it, and the automatic paid-in-full receipt.', 2],
            ['0:25', 'Email name, reply-to, footer fill.', 'Add your email from-name, a reply-to address, and a footer line for the bottom of the quote.', 3],
            ['0:33', 'Save; saved toast.',                'Save, and your quote defaults are set.', 4],
        ],
    ],

    'settings-bank' => [
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
          .gd .stage[data-step="1"] .htp, .gd .stage[data-step="2"] .htp{ display:block; }
          .gd .htp-h{ font-weight:700; margin-bottom:.4rem; }
          .gd .htp-r{ display:flex; justify-content:space-between; padding:.18rem 0; border-bottom:1px solid #f5f7fa; }
          .gd .htp-r span{ color:#5b6b7b; }
          .gd .htp-r.ref{ border-bottom:none; }
          .gd .stage[data-step="2"] .htp-r.ref{ background:#e5edff; border-radius:6px; padding:.18rem .4rem; }',
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
                  <div class="fld wide"><label>Account name</label><div class="box filled">Demo Blinds Ltd</div></div>
                  <div class="fld"><label>Sort code</label><div class="box filled">20-00-00</div></div>
                  <div class="fld"><label>Account number</label><div class="box filled">12345678</div></div>
                  <div class="fld wide"><label>Payment note (optional)</label><div class="box filled">Please use your quote number as the reference</div></div>
                </div>
                <div class="htp">
                  <div class="htp-h">How to pay &mdash; bank transfer</div>
                  <div class="htp-r"><span>Account name</span><b>Demo Blinds Ltd</b></div>
                  <div class="htp-r"><span>Sort code</span><b>20-00-00</b></div>
                  <div class="htp-r"><span>Account no.</span><b>12345678</b></div>
                  <div class="htp-r ref"><span>Reference</span><b>BRI-1042</b></div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Add your bank details.</b>
                  <b class="c1"><span class="n">2</span> They print as a &ldquo;How to pay&rdquo; block.</b>
                  <b class="c2 good"><span class="n">3</span> Quote number = the payment reference.</b>
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
            ['0:00', 'Bank detail fields filled.',        'Pop in your bank details — account name, sort code and account number.', 0],
            ['0:07', '"How to pay" block appears.',       'They print on the customer\'s quote as a "How to pay" block, so they can pay by transfer.', 1],
            ['0:13', 'Reference row highlighted.',        'The quote number goes on as the reference, so payments are easy to match. Leave the fields blank and the block just doesn\'t show.', 2],
        ],
    ],

    'settings-legal' => [
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
    ],

    'settings-colours' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Status colours',
        'eyebrow' => 'Settings · Status colours',
        'blurb'   => 'Your traffic-light colours — a colour per job stage, shown everywhere.',
        'lede'    => 'Your traffic-light system: give each job stage a colour and every job wears it &mdash; on the
                      calendar and in your orders list &mdash; updating itself as the job moves along.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .stagepills{ display:flex; flex-wrap:wrap; gap:.4rem; margin:.2rem 0 1rem; }
          .gd .sp{ padding:.15rem .55rem; border-radius:999px; font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#fff; }
          .gd .sp-quote{ background:#6b7280; } .gd .sp-acc{ background:#2563eb; } .gd .sp-ord{ background:#d97706; } .gd .sp-fit{ background:#0d9488; } .gd .sp-paid{ background:#059669; }
          .gd .calchip{ border:1px solid var(--line); border-radius:8px; padding:.45rem .65rem; font-size:.82rem; color:var(--soft); background:var(--panel); }
          .gd .job{ display:none; padding:.18rem .55rem; border-radius:6px; font-weight:700; font-size:.76rem; color:#fff; }
          .gd .stage[data-step="0"] .jb0, .gd .stage[data-step="1"] .jb1, .gd .stage[data-step="2"] .jb2, .gd .stage[data-step="3"] .jb3{ display:inline-block; }
          .gd .jb0{ background:#6b7280; } .gd .jb1{ background:#2563eb; } .gd .jb2{ background:#d97706; } .gd .jb3{ background:#059669; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Status colours</div>
                <div class="stagepills"><span class="sp sp-quote">Quote</span><span class="sp sp-acc">Accepted</span><span class="sp sp-ord">Ordered</span><span class="sp sp-fit">Fitted</span><span class="sp sp-paid">Paid</span></div>
                <div class="calchip">10:00 &middot; Mrs Patel &middot;
                  <span class="job jb0">Quote</span><span class="job jb1">Accepted</span><span class="job jb2">Ordered</span><span class="job jb3">Paid</span></div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> A colour for each job stage.</b>
                  <b class="c1"><span class="n">2</span> Jobs wear it on the calendar and orders list.</b>
                  <b class="c2"><span class="n">3</span> The colour updates as the job moves.</b>
                  <b class="c3 good"><span class="n">4</span> All the way to paid.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Status colours</b> tab is your traffic-light system. Give each job stage a colour and every job wears it everywhere.</p>
          <ul class="steps">
            <li>Pick a <b>colour for each stage</b> (Quote, Accepted, Ordered, Fitted, Paid, and so on). The little pill preview updates as you choose.</li>
            <li>A job shows that colour <b>everywhere it appears</b> &mdash; on the <b>calendar</b> and in your <b>orders list</b> &mdash; so you can read the board at a glance.</li>
            <li>The colour <b>updates itself</b> as the job moves from stage to stage &mdash; you never recolour anything by hand.</li>
          </ul>
          <p>Then <b>Save status colours</b>. The text colour (black or white) is chosen automatically so your labels stay readable on any colour.</p>',
        'script'  => [
            ['0:00', 'Stage pills; job = Quote (grey).',   'This is your traffic-light system — a colour for each stage a job goes through.', 0],
            ['0:06', 'Job = Accepted (blue).',             'A job wears its colour everywhere — on the calendar, and in your orders list.', 1],
            ['0:12', 'Job = Ordered (amber).',             'And it recolours itself as the job moves along. You don\'t touch a thing.', 2],
            ['0:18', 'Job = Paid (green).',                'All the way through to paid. One glance tells you where everything is.', 3],
        ],
    ],

    'settings-suppliers' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Suppliers',
        'eyebrow' => 'Settings · Suppliers',
        'blurb'   => 'Who you order stock from — their order emails and where they ship to.',
        'lede'    => 'Who you <b>order stock from</b> &mdash; each supplier&rsquo;s order email and your delivery address.
                      This is what &ldquo;Send to suppliers&rdquo; uses to email your orders.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .sup-addr{ border:1px solid var(--line); border-radius:8px; background:var(--panel); padding:.5rem .65rem; font-size:.8rem; color:var(--soft); margin-bottom:.7rem; }
          .gd .sup-addr b{ color:var(--ink); }
          .gd .suptable{ width:100%; border-collapse:collapse; font-size:.8rem; }
          .gd .suptable th{ text-align:left; font-size:.62rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); padding:.2rem .4rem; }
          .gd .suptable td{ border-top:1px solid var(--line); padding:.35rem .4rem; color:var(--ink); }
          .gd .suptable .em{ color:var(--soft); }
          .gd .suprow-new{ opacity:.45; transition:opacity .2s; }
          .gd .stage[data-step="1"] .suprow-new, .gd .stage[data-step="2"] .suprow-new{ opacity:1; }
          .gd .stage[data-step="1"] .suprow-new td, .gd .stage[data-step="2"] .suprow-new td{ background:var(--accent-wash); }
          .gd .po{ display:none; margin-top:.8rem; border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px; padding:.5rem .7rem; font-size:.8rem; color:var(--soft); }
          .gd .stage[data-step="2"] .po{ display:block; }
          .gd .po .to{ color:var(--accent-ink); font-weight:700; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Suppliers</div>
                <div class="sup-addr">Delivery address &mdash; <b>Demo Blinds Ltd, Unit 4, Leeds LS1&nbsp;1AA</b> (goes on every supplier order)</div>
                <table class="suptable"><thead><tr><th>Supplier</th><th>Order email</th></tr></thead>
                  <tbody>
                    <tr><td>Louvolite</td><td class="em">orders@louvolite.example</td></tr>
                    <tr><td>Decora</td><td class="em">trade@decora.example</td></tr>
                    <tr class="suprow-new"><td>+ Add a supplier</td><td class="em">orders@supplier.com</td></tr>
                  </tbody></table>
                <div class="po">&#9993; Purchase order &rarr; <span class="to">orders@louvolite.example</span> &mdash; their lines only (sizes, no customer prices), shipped to your address.</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Each supplier&rsquo;s order email + where they ship to.</b>
                  <b class="c1"><span class="n">2</span> Add, rename or remove &mdash; products add them too.</b>
                  <b class="c2 good"><span class="n">3</span> &ldquo;Send to suppliers&rdquo; emails each their own order.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Suppliers</b> tab is who you <b>order stock from</b> (not the fabric library). It fills each product&rsquo;s
             <em>Order supplier</em> field and drives your purchase orders.</p>
          <ul class="steps">
            <li>Set your <b>Delivery address</b> &mdash; where suppliers ship to. It goes on every supplier order.</li>
            <li>Give each supplier their <b>order email</b> (and account number, if the column shows). <b>Rename</b> one, tick
                <b>Remove</b> to delete a stray, or add one in the bottom row.</li>
            <li>Suppliers you set on a product <b>appear here automatically</b>, so the list mostly fills itself.</li>
          </ul>
          <p>Then <b>Save suppliers</b>. When you press <b>&ldquo;Send to suppliers&rdquo;</b> on an accepted order, each supplier is
             emailed only their own lines (sizes, no customer prices), shipped to your delivery address.</p>',
        'script'  => [
            ['0:00', 'Delivery address + supplier table.', 'These are the people you order stock from — each one\'s order email, and where they ship to.', 0],
            ['0:07', 'New supplier row highlighted.',      'Add, rename or remove any of them. And when you set a supplier on a product, it turns up here on its own.', 1],
            ['0:15', 'Purchase order emailed out.',        'Then "Send to suppliers" on an order emails each of them just their own lines. Sizes, no customer prices.', 2],
        ],
    ],

    'settings-backup' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Back up your data',
        'eyebrow' => 'Settings · Back up data',
        'blurb'   => 'Download your quotes and orders to keep safe — Excel or PDF.',
        'lede'    => 'Download a copy of your <b>quotes and orders</b> &mdash; with line items, totals and payments &mdash;
                      to keep on your own computer. A good habit to get into.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .bk-range{ display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:.8rem; }
          .gd .bk-btns{ display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .bk-btn{ border:1px solid var(--line); border-radius:8px; padding:.35rem .7rem; font-size:.8rem; font-weight:600; color:var(--soft); }
          .gd .bk-btn.pri{ background:var(--accent); color:#fff; border-color:transparent; }
          .gd .bk-btn.on{ border-color:var(--accent); color:var(--accent-ink); background:var(--accent-wash); }
          .gd .bk-file{ display:none; align-items:center; gap:.5rem; margin-top:.8rem; border:1px solid var(--line); border-radius:8px; padding:.5rem .7rem; font-size:.82rem; background:var(--panel); color:var(--ink); }
          .gd .stage[data-step="1"] .bk-file, .gd .stage[data-step="2"] .bk-file{ display:flex; }
          .gd .bk-file .grn{ color:var(--good); font-weight:700; }
          .gd .bk-since{ display:none; margin-top:.7rem; font-size:.8rem; color:var(--soft); border:1px dashed var(--border-dashed,#cbd5e1); border-radius:8px; padding:.4rem .6rem; }
          .gd .stage[data-step="2"] .bk-since{ display:block; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Back up your data</div>
                <div class="bk-range"><span class="bk-btn on">All time</span><span class="bk-btn">Last 30 days</span><span class="bk-btn">This year</span></div>
                <div class="bk-btns"><span class="bk-btn pri">&#11015; Download Excel (.xlsx)</span><span class="bk-btn">&#11015; PDF summary</span></div>
                <div class="bk-file">&#128196; yourblinds-backup.xlsx <span class="grn">&check; downloaded</span></div>
                <div class="bk-since">&#11015; Changes since last backup (Excel) &mdash; just what&rsquo;s new or changed.</div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Pick a range &mdash; or leave it for everything.</b>
                  <b class="c1"><span class="n">2</span> Download Excel &mdash; the copy to keep.</b>
                  <b class="c2 good"><span class="n">3</span> Next time, grab just what changed.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Back up data</b> tab downloads a copy of your <b>quotes and orders</b> (with line items, totals and payments)
             to keep on your own computer.</p>
          <ul class="steps">
            <li>Choose a <b>date range</b> &mdash; use <b>All time / Last 30 days / This year</b>, or leave both dates blank for everything.</li>
            <li><b>Download Excel (.xlsx)</b> is the one to keep. It has two sheets: a <b>Quotes &amp; Orders</b> summary and a full
                <b>Line items</b> list, so you can open, sort and filter it.</li>
            <li><b>Download PDF summary</b> gives a printable, one-look version instead.</li>
            <li>After a full Excel backup, a <b>&ldquo;Changes since last backup&rdquo;</b> option appears &mdash; grab only what&rsquo;s new or changed next time.</li>
          </ul>
          <p>Dates filter by <b>order date</b> (accepted, or created if not yet accepted). Do this regularly and keep the file
             somewhere safe, off the computer.</p>',
        'script'  => [
            ['0:00', 'Range presets + download buttons.',  'This downloads your quotes and orders to keep safe. Pick a date range, or leave it for the lot.', 0],
            ['0:07', 'Excel file downloaded.',             'Download the Excel file — that\'s the one to keep. Two sheets: a summary, and every line.', 1],
            ['0:14', 'Changes-since-last-backup appears.', 'And once you\'ve taken one, it can hand you just what\'s changed since — quick and easy.', 2],
        ],
    ],
];
