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
          .gd .ldesc{ color:var(--soft); font-size:.8rem; margin:0 0 .8rem; }
          /* the real file input: a "Choose File" button + the file name */
          .gd .fileinput{ display:inline-flex; align-items:center; gap:.6rem; border:1px solid var(--line); border-radius:7px; padding:.32rem .5rem; background:var(--surface); font-size:.82rem; }
          .gd .choosebtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.22rem .6rem; background:var(--panel); color:var(--ink); font-size:.78rem; transition:transform .1s, background .15s, border-color .15s, color .15s; }
          .gd .filename .nofile{ color:var(--faint); }
          .gd .filename .chosenfile{ display:none; color:var(--ink); font-weight:600; }
          .gd .stage[data-step="2"] .nofile, .gd .stage[data-step="3"] .nofile, .gd .stage[data-step="4"] .nofile{ display:none; }
          .gd .stage[data-step="2"] .chosenfile, .gd .stage[data-step="3"] .chosenfile, .gd .stage[data-step="4"] .chosenfile{ display:inline; }
          .gd .stage[data-step="1"] .choosebtn{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); transform:scale(.96); }
          .gd .uploadbtn{ margin-top:.8rem; display:inline-flex; align-items:center; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .8rem; font-size:.82rem; font-weight:600; transition:box-shadow .15s, transform .1s, filter .1s; }
          .gd .stage[data-step="2"] .uploadbtn{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .uploadbtn{ transform:scale(.96); filter:brightness(1.2); }
          /* the file-picker dialog that opens when Choose File is clicked */
          .gd .filedlg{ display:none; position:absolute; left:1.2rem; top:3.4rem; width:238px; z-index:6; background:var(--surface); border:1px solid var(--line); border-radius:10px; box-shadow:0 16px 34px -12px rgba(20,30,45,.45); overflow:hidden; font-size:.78rem; }
          .gd .stage[data-step="1"] .filedlg{ display:block; }
          .gd .fd-bar{ background:var(--panel); border-bottom:1px solid var(--line); padding:.4rem .6rem; font-weight:700; color:var(--soft); font-size:.72rem; }
          .gd .fd-body{ padding:.3rem; }
          .gd .fd-item{ display:flex; align-items:center; gap:.45rem; padding:.28rem .45rem; border-radius:6px; color:var(--ink); }
          .gd .fd-item.sel{ background:var(--accent-wash); color:var(--accent-ink); font-weight:600; }
          .gd .fd-actions{ display:flex; align-items:center; gap:.6rem; padding:.4rem .6rem; border-top:1px solid var(--line); }
          .gd .fd-file{ margin-right:auto; color:var(--soft); font-size:.72rem; }
          .gd .fd-open{ background:var(--accent); color:#fff; border-radius:6px; padding:.16rem .6rem; font-weight:600; }
          .gd .fd-cancel{ color:var(--soft); }
          /* preview + remove */
          .gd .logo-preview{ display:none; align-items:center; gap:1rem; margin-top:.9rem; }
          .gd .stage[data-step="3"] .logo-preview, .gd .stage[data-step="4"] .logo-preview{ display:flex; }
          .gd .logo-mark{ font-weight:800; font-size:1.15rem; letter-spacing:-.02em; color:#1c2733; background:#fff; border:1px solid var(--line); border-radius:8px; padding:.5rem .8rem; }
          .gd .logo-mark b{ color:#2563eb; }
          .gd .logo-mark.sm{ font-size:.85rem; padding:.3rem .55rem; }
          .gd .lp-actions{ display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; }
          .gd .ghostbtn{ border:1px solid var(--line); border-radius:7px; padding:.3rem .6rem; font-size:.78rem; font-weight:600; color:var(--soft); }
          .gd .muted{ color:var(--faint); font-size:.8rem; }
          /* mini quote */
          .gd .mini-quote{ display:none; margin-top:1rem; border:1px solid var(--line); border-radius:10px; padding:.8rem .9rem; background:#fff; }
          .gd .stage[data-step="4"] .mini-quote{ display:block; }
          .gd .mq-head{ display:flex; align-items:center; justify-content:space-between; gap:1rem; border-bottom:1px solid #eef2f6; padding-bottom:.6rem; }
          .gd .mq-meta{ text-align:right; font-size:.72rem; color:#5b6b7b; font-weight:700; }
          .gd .mq-lines{ display:flex; flex-direction:column; gap:.4rem; padding-top:.7rem; }
          .gd .mq-lines span{ height:8px; border-radius:4px; background:#eef2f6; }
          .gd .mq-lines span:nth-child(1){ width:70%; } .gd .mq-lines span:nth-child(2){ width:88%; } .gd .mq-lines span:nth-child(3){ width:52%; }',
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
                <p class="ldesc">Used at the top of your quote PDFs and the online accept page. JPG, PNG or GIF, up to 2&nbsp;MB.</p>
                <div class="fld"><label>Upload logo</label>
                  <div class="fileinput"><span class="choosebtn">Choose File</span>
                    <span class="filename"><span class="nofile">No file chosen</span><span class="chosenfile">logo.png</span></span></div>
                </div>
                <div class="uploadbtn">Upload logo</div>
                <div class="filedlg">
                  <div class="fd-bar">Open &mdash; Pictures</div>
                  <div class="fd-body">
                    <div class="fd-item"><span>&#128247;</span> banner.jpg</div>
                    <div class="fd-item sel"><span>&#128247;</span> logo.png</div>
                    <div class="fd-item"><span>&#128247;</span> team-photo.jpg</div>
                    <div class="fd-item"><span>&#128196;</span> prices.pdf</div>
                  </div>
                  <div class="fd-actions"><span class="fd-file">logo.png</span><span class="fd-cancel">Cancel</span><span class="fd-open">Open</span></div>
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
                  <b class="c1"><span class="n">1</span> Click Choose File &mdash; and pick your logo.</b>
                  <b class="c2"><span class="n">2</span> Selected. Now hit Upload.</b>
                  <b class="c3"><span class="n">3</span> Up it pops. Wrong one? Remove it.</b>
                  <b class="c4 good"><span class="n">4</span> And there it is, heading your quote.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Still on the <b>Company</b> tab, scroll down to <b>Company logo</b>. This is the logo that
             prints at the top of every quote PDF and on the online page where customers accept.</p>
          <ul class="steps">
            <li>Click <b>Choose File</b> and pick your logo from your computer — a <b>JPG, PNG or GIF</b>, up to
                <b>2&nbsp;MB</b>. A see-through (transparent) PNG looks tidiest on the page.</li>
            <li>Then click <b>Upload logo</b>. It shows straight away as a <b>preview</b> &mdash; that&rsquo;s it saved.</li>
            <li>Wrong file? Click <b>Remove logo</b> and choose another. To change it later, just upload
                again &mdash; the button then reads <b>Replace logo</b>.</li>
          </ul>
          <p><b>Tip:</b> a wide logo on a see-through background sits best in the quote header. Very large
             images over 2&nbsp;MB are turned away &mdash; shrink it first if that happens. No logo yet? Quotes
             simply show your company name until you add one.</p>',
        'script'  => [
            ['0:00', 'Clicks Choose File; folder opens.',      'Your logo goes on every quote — click Choose File, and pick it from your computer.', 1],
            ['0:07', 'logo.png selected; shows in the field.',  'A JPG, PNG or GIF, up to two meg. That\'s it selected — now hit Upload.', 2],
            ['0:14', 'Preview appears with Remove.',            'Up it pops as a preview. Wrong one? Remove it and choose another.', 3],
            ['0:20', 'Logo heads a mini quote.',               'And there it is, heading your quote. Done.', 4],
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
          .gd .wrows{ display:none; flex-direction:column; gap:.4rem; margin-top:.55rem; }
          .gd .stage[data-step="3"] .wrows, .gd .stage[data-step="4"] .wrows{ display:flex; }
          .gd .wrow{ display:flex; align-items:center; gap:.35rem; font-size:.74rem; color:var(--soft); flex-wrap:wrap; }
          .gd .wlab{ min-width:4.3rem; font-weight:600; color:var(--ink); }
          .gd .tfield, .gd .capfield{ border:1px solid var(--border-strong,#c7ccd4); border-radius:5px; padding:.08rem .4rem; background:var(--surface); color:var(--ink); font-size:.72rem; }
          .gd .capfield{ min-width:1.9rem; text-align:center; }
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
                  <small>A window, not an exact time &mdash; set your own hours and limits for each.</small>
                  <div class="wrows">
                    <div class="wrow"><span class="wlab">Morning</span> From <span class="tfield">08:00</span> To <span class="tfield">12:30</span> &middot; <span class="capfield">5</span> / day</div>
                    <div class="wrow"><span class="wlab">Afternoon</span> From <span class="tfield">12:30</span> To <span class="tfield">17:00</span> &middot; <span class="capfield">3</span> / day</div>
                  </div>
                </div>
                <div class="caps">
                  <b class="c0"><span class="n">1</span> Three quick calendar choices.</b>
                  <b class="c1"><span class="n">2</span> Show the money on jobs &mdash; or keep it off.</b>
                  <b class="c2"><span class="n">3</span> The map app your address links open in.</b>
                  <b class="c3"><span class="n">4</span> Windows for measures &mdash; your own hours &amp; limits.</b>
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
            <li><b>&#128344; Morning / afternoon booking slots</b> &mdash; for <b>measure (quote) visits</b>, offer a
                <b>Morning</b> or <b>Afternoon</b> window instead of an exact time, so you promise a window, not a specific hour.
                Set <b>your own times</b> for each window and <b>how many bookings each holds per day</b> (the two can differ &mdash;
                say 5 mornings but 3 afternoons). Once a window is full it can&rsquo;t be booked. Fittings aren&rsquo;t affected.</li>
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
            ['0:22', 'Slots on; windows appear.',          'And morning or afternoon windows for measure visits, so you give a window, not an exact time. Set your own hours, and how many fit in each.', 3],
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
                      <b>calendar</b>, in your <b>orders list</b>, and across the <b>Pipeline</b> &mdash; updating itself as the job moves along.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .scgrp{ margin-bottom:.55rem; }
          .gd .scgrp-h{ font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:.3rem 0 .35rem; }
          .gd .scards{ display:flex; flex-wrap:wrap; gap:.4rem; }
          .gd .scard{ display:flex; align-items:center; gap:.4rem; border:1px solid var(--line); border-radius:8px; padding:.3rem .45rem; background:var(--surface); transition:box-shadow .2s, border-color .2s; }
          .gd .swatch{ width:20px; height:20px; border-radius:4px; border:1px solid rgba(0,0,0,.2); flex:none; }
          .gd .pillc{ padding:.08rem .45rem; border-radius:999px; font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#fff; white-space:nowrap; }
          .gd .sw-acc{ background:#16a34a; } .gd .pc-acc{ background:#16a34a; }
          /* the Accepted stage is changed to violet from step 2 on */
          .gd .stage[data-step="2"] .sw-acc, .gd .stage[data-step="3"] .sw-acc, .gd .stage[data-step="4"] .sw-acc,
          .gd .stage[data-step="2"] .pc-acc, .gd .stage[data-step="3"] .pc-acc, .gd .stage[data-step="4"] .pc-acc{ background:#9333ea; }
          /* while the picker is open (step 1) highlight the Accepted card */
          .gd .stage[data-step="1"] .card-acc{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          /* the colour-picker popup */
          .gd .picker{ display:none; position:absolute; left:8.5rem; top:4.4rem; z-index:6; background:var(--surface); border:1px solid var(--line); border-radius:10px; box-shadow:0 14px 30px -10px rgba(20,30,45,.4); padding:.5rem; }
          .gd .stage[data-step="1"] .picker{ display:block; }
          .gd .pk-grid{ display:grid; grid-template-columns:repeat(5,18px); gap:5px; }
          .gd .pk-cell{ width:18px; height:18px; border-radius:4px; }
          .gd .pk-cell.sel{ outline:2px solid var(--ink); outline-offset:1px; }
          /* the chosen colour applied on a calendar chip (step 3 on) */
          .gd .calchip2{ display:none; margin-top:.9rem; border:1px solid var(--line); border-radius:8px; padding:.45rem .65rem; font-size:.8rem; color:var(--soft); background:var(--panel); }
          .gd .stage[data-step="3"] .calchip2, .gd .stage[data-step="4"] .calchip2{ display:block; }
          .gd .jobpill{ padding:.14rem .5rem; border-radius:6px; color:#fff; font-weight:700; font-size:.72rem; background:#9333ea; }
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
                <div class="toast">&check; Status colours saved</div>
                <div class="card-t">Status colours</div>
                <div class="scgrp">
                  <div class="scgrp-h">Quote stages</div>
                  <div class="scards">
                    <div class="scard"><span class="swatch" style="background:#7c3aed"></span><span class="pillc" style="background:#7c3aed">Quote drafted</span></div>
                    <div class="scard"><span class="swatch" style="background:#f59e0b"></span><span class="pillc" style="background:#f59e0b">Quote sent</span></div>
                    <div class="scard card-acc"><span class="swatch sw-acc"></span><span class="pillc pc-acc">Accepted</span></div>
                    <div class="scard"><span class="swatch" style="background:#dc2626"></span><span class="pillc" style="background:#dc2626">Declined</span></div>
                    <div class="scard"><span class="swatch" style="background:#0891b2"></span><span class="pillc" style="background:#0891b2">Ordered</span></div>
                  </div>
                </div>
                <div class="scgrp">
                  <div class="scgrp-h">Appointments &amp; job</div>
                  <div class="scards">
                    <div class="scard"><span class="swatch" style="background:#2563eb"></span><span class="pillc" style="background:#2563eb">Appointment booked</span></div>
                    <div class="scard"><span class="swatch" style="background:#6366f1"></span><span class="pillc" style="background:#6366f1">Fitting booked</span></div>
                    <div class="scard"><span class="swatch" style="background:#0d9488"></span><span class="pillc" style="background:#0d9488">Fitted</span></div>
                    <div class="scard"><span class="swatch" style="background:#ea580c"></span><span class="pillc" style="background:#ea580c">Invoiced</span></div>
                    <div class="scard"><span class="swatch" style="background:#475569"></span><span class="pillc" style="background:#475569">Paid</span></div>
                    <div class="scard"><span class="swatch" style="background:#b91c1c"></span><span class="pillc" style="background:#b91c1c">Cancelled</span></div>
                    <div class="scard"><span class="swatch" style="background:#9ca3af"></span><span class="pillc" style="background:#9ca3af">No-show</span></div>
                  </div>
                </div>
                <div class="scgrp">
                  <div class="scgrp-h">Flags</div>
                  <div class="scards">
                    <div class="scard"><span class="swatch" style="background:#e11d48"></span><span class="pillc" style="background:#e11d48">Issue</span></div>
                  </div>
                </div>
                <div class="picker">
                  <div class="pk-grid">
                    <span class="pk-cell" style="background:#ef4444"></span><span class="pk-cell" style="background:#f59e0b"></span>
                    <span class="pk-cell" style="background:#16a34a"></span><span class="pk-cell" style="background:#2563eb"></span>
                    <span class="pk-cell sel" style="background:#9333ea"></span><span class="pk-cell" style="background:#ec4899"></span>
                    <span class="pk-cell" style="background:#0d9488"></span><span class="pk-cell" style="background:#475569"></span>
                    <span class="pk-cell" style="background:#84cc16"></span><span class="pk-cell" style="background:#0ea5e9"></span>
                  </div>
                </div>
                <div class="calchip2">10:00 &middot; Mrs Patel &middot; <span class="jobpill">Accepted</span></div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Click a stage&rsquo;s colour box&hellip;</b>
                  <b class="c2"><span class="n">2</span> &hellip;pick your colour &mdash; the pill updates.</b>
                  <b class="c3"><span class="n">3</span> Shows everywhere &mdash; calendar, orders, Pipeline.</b>
                  <b class="c4 good"><span class="n">4</span> Save &mdash; done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Status colours</b> tab is your traffic-light system &mdash; a colour for every stage a job goes through, laid out in
             groups: <b>Quote stages</b>, <b>Appointments &amp; job</b>, and <b>Flags</b>.</p>
          <ul class="steps">
            <li>Each status has its own little <b>colour box</b> beside a <b>pill</b> in that colour. <b>Click a colour box</b>
                to open the picker and choose any colour you like &mdash; the pill updates as you pick.</li>
            <li>That colour then shows <b>everywhere the job appears</b> &mdash; on the <b>calendar</b>, in your <b>orders list</b>,
                and across the <b>Pipeline</b> &mdash; so you can read the board at a glance.</li>
            <li>The colour <b>updates itself</b> as a job moves from stage to stage &mdash; you never recolour anything by hand.</li>
          </ul>
          <p>Then <b>Save status colours</b>. The text (black or white) is chosen automatically so your labels stay readable on any colour.</p>',
        'script'  => [
            ['0:00', 'Colour picker opens on a stage.',    'Each stage has a colour. Click its box to change it.', 1],
            ['0:06', 'Accepted becomes purple.',           'Pick any colour you like — the pill updates as you go.', 2],
            ['0:12', 'Calendar chip shows the new colour.', 'That colour then shows everywhere — the calendar, your orders list, and the pipeline.', 3],
            ['0:17', 'Saved.',                             'Save, and you\'re done.', 4],
        ],
    ],

    'settings-suppliers' => [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Suppliers',
        'eyebrow' => 'Settings · Suppliers',
        'blurb'   => 'Who you order stock from — your delivery address + each supplier\'s order email. Get it right.',
        'lede'    => 'Who you <b>order stock from</b> &mdash; your <b>delivery address</b> (it prints on every order) and each
                      supplier&rsquo;s <b>order email</b>. Get these right: they&rsquo;re exactly what &ldquo;Send to suppliers&rdquo; uses,
                      so a wrong email sends an order into the void.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .slbl{ display:block; font-size:.72rem; font-weight:600; color:var(--ink); margin-bottom:.3rem; }
          .gd .muted{ color:var(--faint); font-weight:400; }
          .gd .suptable2{ margin-top:.85rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.78rem; }
          .gd .sup-head, .gd .sup-row{ display:grid; grid-template-columns:1.05fr 1.7fr .9fr; gap:.5rem; align-items:center; padding:.35rem .55rem; }
          .gd .sup-head{ background:var(--panel); font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .sup-row{ border-top:1px solid var(--line); }
          .gd .sup-name{ color:var(--ink); font-weight:600; }
          .gd .em{ color:var(--soft); }
          .gd .add-row{ opacity:.5; } .gd .add-row .sup-name{ color:var(--soft); font-weight:500; }
          .gd .sup-row .box{ height:26px; border:1px solid var(--line); border-radius:6px; background:var(--panel); display:flex; align-items:center; padding:0 .45rem; font-size:.74rem; color:var(--ink); overflow:hidden; }
          .gd .po{ display:none; margin-top:.9rem; border:1px solid var(--line); border-left:3px solid var(--accent); border-radius:8px; padding:.5rem .7rem; font-size:.8rem; color:var(--soft); }
          .gd .stage[data-step="3"] .po{ display:block; }
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
                <label class="slbl">Delivery address <span class="muted">(where suppliers ship to)</span></label>
                <div class="ta f1"><span class="ph">Your business / warehouse address &mdash; goes on every supplier order</span><span class="val">Demo Blinds Ltd, Unit 4, Sample Way, Leeds LS1&nbsp;1AA</span></div>
                <div class="suptable2">
                  <div class="sup-head"><span>Supplier</span><span>Order email</span><span>Account no.</span></div>
                  <div class="sup-row">
                    <span class="sup-name">Louvolite</span>
                    <span class="box f2"><span class="ph">orders@&hellip;</span><span class="val">orders@louvolite.example</span></span>
                    <span class="box f2"><span class="ph">Acc no.</span><span class="val">LV-4471</span></span>
                  </div>
                  <div class="sup-row"><span class="sup-name">Decora</span><span class="em">trade@decora.example</span><span class="em">DEC-208</span></div>
                  <div class="sup-row add-row"><span class="sup-name">+ Add a supplier</span><span class="em">orders@supplier.com</span><span class="em"></span></div>
                </div>
                <div class="po">&#9993; Purchase order &rarr; <span class="to">orders@louvolite.example</span> &mdash; their lines only (sizes, no customer prices), shipped to your delivery address.</div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Your delivery address &mdash; on every supplier order.</b>
                  <b class="c2 err"><span class="n">2</span> Order email &amp; account &mdash; get these exactly right.</b>
                  <b class="c3 good"><span class="n">3</span> &ldquo;Send to suppliers&rdquo; emails each their own order.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Suppliers</b> tab is who you <b>order stock from</b> (not the fabric library). It fills each product&rsquo;s
             <em>Order supplier</em> field and drives your purchase orders.</p>
          <ul class="steps">
            <li><b>Delivery address</b> &mdash; where suppliers ship to. It <b>prints on every supplier order</b>, so make sure it&rsquo;s
                your correct, full address.</li>
            <li>For each supplier, enter their <b>order email</b> and (if the column shows) your <b>account number</b>. <b>Rename</b> one,
                tick <b>Remove</b> to delete a stray, or add one in the bottom row.</li>
            <li>Suppliers you set on a product <b>appear here automatically</b>, so the list mostly fills itself &mdash; but still
                <b>check the email on each one</b>.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Get the order email and account number exactly right.</b> The order email is
             <b>where your purchase order is sent</b> &mdash; one typo and it goes to the wrong place or bounces, and you might not find out
             until the job is late. Double-check every supplier&rsquo;s email and account, and if you&rsquo;re unsure, send a test order to
             yourself first. A wrong delivery address means your stock is shipped to the wrong place, too.</div></div>
          <p>Then <b>Save suppliers</b>. When you press <b>&ldquo;Send to suppliers&rdquo;</b> on an accepted order, each supplier is
             emailed only their own lines (sizes, no customer prices), shipped to your delivery address.</p>',
        'script'  => [
            ['0:00', 'Delivery address fills in.',          'First, your delivery address — this goes on every order you send a supplier, so get it right.', 1],
            ['0:07', 'Order email & account fill; flagged.', 'Then each supplier\'s order email and account number. This is the important bit — the order is emailed to exactly this address, so a typo and it\'s lost.', 2],
            ['0:16', 'Purchase order emailed out.',         'When you send an order to suppliers, each gets just their own lines — sizes, no customer prices — off to the email you set.', 3],
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
          .gd .bdesc{ color:var(--soft); font-size:.8rem; margin:0 0 .85rem; }
          .gd .bk-range{ display:flex; align-items:flex-end; gap:.55rem .8rem; flex-wrap:wrap; margin-bottom:.9rem; }
          .gd .bk-field{ display:flex; flex-direction:column; gap:.18rem; }
          .gd .bk-field label{ font-size:.66rem; color:var(--faint); font-weight:600; }
          .gd .datebox{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.24rem .45rem; font-size:.74rem; color:var(--faint); background:var(--surface); min-width:6.6rem; display:inline-flex; align-items:center; justify-content:space-between; gap:.4rem; }
          .gd .datebox::after{ content:"\1F4C5"; font-size:.72rem; }
          .gd .bk-presets{ display:flex; gap:.35rem; flex-wrap:wrap; }
          .gd .bk-btn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.28rem .6rem; font-size:.76rem; font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .stage[data-step="1"] .pset-30{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); }
          .gd .bk-btns{ display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .dl{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); border-radius:8px; padding:.4rem .75rem; font-size:.8rem; font-weight:700; color:var(--ink); background:var(--surface); transition:transform .1s, filter .1s; }
          .gd .dl.pri{ background:var(--accent); color:#fff; border-color:transparent; }
          .gd .stage[data-step="2"] .dl.pri{ transform:scale(.97); filter:brightness(1.12); }
          .gd .bk-file{ display:none; align-items:center; gap:.5rem; margin-top:.75rem; border:1px solid var(--line); border-radius:8px; padding:.5rem .7rem; font-size:.82rem; background:var(--panel); color:var(--ink); }
          .gd .stage[data-step="2"] .bk-file, .gd .stage[data-step="3"] .bk-file{ display:flex; }
          .gd .bk-file .grn{ color:var(--good); font-weight:700; }
          .gd .bk-since{ display:none; margin-top:.85rem; border:1px solid var(--line); border-radius:8px; padding:.55rem .7rem; font-size:.78rem; color:var(--soft); background:var(--panel); max-width:30rem; }
          .gd .bk-since b{ color:var(--ink); }
          .gd .sincebtn{ display:inline-block; margin-top:.45rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.2rem .55rem; font-size:.74rem; font-weight:600; color:var(--soft); }
          .gd .stage[data-step="3"] .bk-since{ display:block; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .bnote{ color:var(--faint); font-size:.72rem; margin-top:.75rem; line-height:1.5; }',
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
                <p class="bdesc">Download a copy of your quotes and orders (line items, totals and payments) to keep on your own computer.</p>
                <div class="bk-range">
                  <div class="bk-field"><label>From</label><span class="datebox">dd/mm/yyyy</span></div>
                  <div class="bk-field"><label>To</label><span class="datebox">dd/mm/yyyy</span></div>
                  <div class="bk-presets"><span class="bk-btn pset-all">All time</span><span class="bk-btn pset-30">Last 30 days</span><span class="bk-btn pset-yr">This year</span></div>
                </div>
                <div class="bk-btns"><span class="dl pri">&#11015; Download Excel (.xlsx)</span><span class="dl">&#11015; Download PDF summary</span></div>
                <div class="bk-file">&#128196; yourblinds-backup.xlsx <span class="grn">&check; downloaded</span></div>
                <div class="bk-since"><b>Last full backup:</b> today. Get just the quotes and orders changed since then:
                  <span class="sincebtn">&#11015; Changes since last backup (Excel)</span></div>
                <p class="bnote">The <b>Excel</b> file has two sheets &mdash; a Quotes &amp; Orders summary and a full Line items list. The <b>PDF</b> is a printable summary. Dates filter by order date.</p>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Pick a range &mdash; or leave it for everything.</b>
                  <b class="c2"><span class="n">2</span> Download Excel &mdash; the copy to keep.</b>
                  <b class="c3 good"><span class="n">3</span> Next time, grab just what changed.</b>
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
            ['0:00', 'Range: From/To + presets.',          'This downloads your quotes and orders to keep safe. Pick a date range with From and To, or a preset — or leave it for the lot.', 1],
            ['0:08', 'Excel file downloaded.',             'Download the Excel file — that\'s the one to keep. Two sheets: a summary, and every line.', 2],
            ['0:15', 'Changes-since-last-backup appears.', 'And once you\'ve taken one, it can hand you just what\'s changed since — quick and easy.', 3],
        ],
    ],

    'users-add' => [
        'aud'     => 'admin',
        'section' => 'Users',
        'title'   => 'Adding users & permissions',
        'eyebrow' => 'Users',
        'blurb'   => 'Add login accounts and set roles + permissions — including who can see your costs.',
        'lede'    => 'Your login accounts &mdash; one per person who uses the system. Set who they are, what <b>roles</b>
                      they fill, and what they&rsquo;re allowed to do and see. Get the permissions right and everyone sees just
                      what they should.',
        'open'    => '/admin/users.php',
        'css'     => '
          .gd .muted{ color:var(--faint); font-weight:400; }
          .gd .plbl{ font-size:.72rem; font-weight:600; color:var(--ink); margin:.75rem 0 .3rem; }
          .gd .permbox{ border:1px solid var(--line); border-radius:8px; padding:.5rem .65rem; display:flex; flex-wrap:wrap; gap:.4rem .9rem; background:var(--surface); transition:box-shadow .2s, border-color .2s; }
          .gd .chkopt{ display:inline-flex; align-items:center; gap:.35rem; font-size:.78rem; color:var(--soft); }
          .gd .stage[data-step="2"] .roles-box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="3"] .perm-box{ border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="2"] .t-fitter, .gd .stage[data-step="3"] .t-fitter, .gd .stage[data-step="4"] .t-fitter{ background:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .t-fitonly, .gd .stage[data-step="4"] .t-fitonly{ background:var(--accent); color:#fff; }
          .gd .addbtn{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .9rem; font-size:.82rem; font-weight:700; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="4"] .addbtn{ transform:scale(.97); filter:brightness(1.12); }
          .gd .userrow{ display:none; align-items:center; gap:.5rem; margin-top:.9rem; border:1px solid var(--line); border-radius:8px; padding:.45rem .65rem; font-size:.78rem; background:var(--panel); color:var(--ink); }
          .gd .stage[data-step="4"] .userrow{ display:flex; }
          .gd .userrow .grn{ color:var(--good); font-weight:700; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / users</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Add user</div>
                <div class="frow">
                  <div class="fld"><label>First name</label><div class="box f1"><span class="ph">First name</span><span class="val">Dave</span></div></div>
                  <div class="fld"><label>Last name</label><div class="box f1"><span class="ph">Last name</span><span class="val">Miller</span></div></div>
                </div>
                <div class="frow">
                  <div class="fld"><label>Email <span class="muted">(optional)</span></label><div class="box"><span class="ph">you@&hellip; (or use a username)</span></div></div>
                  <div class="fld"><label>Username <span class="muted">(for staff with no email)</span></label><div class="box f1"><span class="ph">username</span><span class="val">dave</span></div></div>
                </div>
                <div class="fld" style="margin-top:.7rem"><label>Password <span class="req">*</span></label>
                  <div class="box f1"><span class="ph">at least 8 characters</span><span class="val">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span></div></div>
                <div class="plbl">Roles</div>
                <div class="permbox roles-box">
                  <span class="chkopt"><span class="tick">&check;</span> Admin</span>
                  <span class="chkopt"><span class="tick">&check;</span> Owner</span>
                  <span class="chkopt"><span class="tick">&check;</span> Office</span>
                  <span class="chkopt"><span class="tick">&check;</span> Sales</span>
                  <span class="chkopt"><span class="tick">&check;</span> Agent</span>
                  <span class="chkopt"><span class="tick t-fitter">&check;</span> Fitter</span>
                  <span class="chkopt"><span class="tick">&check;</span> Readonly</span>
                </div>
                <div class="plbl">Permissions</div>
                <div class="permbox perm-box">
                  <span class="chkopt"><span class="tick">&check;</span> Create quotes</span>
                  <span class="chkopt"><span class="tick">&check;</span> Create orders</span>
                  <span class="chkopt"><span class="tick">&check;</span> View all customer jobs</span>
                  <span class="chkopt"><span class="tick">&check;</span> View costs</span>
                  <span class="chkopt"><span class="tick t-fitonly">&check;</span> Fittings only</span>
                </div>
                <div class="addbtn">Add user</div>
                <div class="userrow">&#128100; Dave Miller &middot; <span class="muted">Fitter</span> <span class="grn">&check; added</span></div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Name and a login &mdash; email, or a username.</b>
                  <b class="c2"><span class="n">2</span> Tick every role &mdash; here, Fitter.</b>
                  <b class="c3"><span class="n">3</span> Permissions &mdash; what they can do &amp; see.</b>
                  <b class="c4 good"><span class="n">4</span> Add user &mdash; they can log in now.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Users</b> page (in the admin area) holds your login accounts &mdash; add one for each person who uses the system.</p>
          <ul class="steps">
            <li><b>Name + login</b> &mdash; first and last name, then <b>either an email or a username</b>. Workshop staff with no
                email can log in with just a <b>username</b>. Set a <b>password</b> (at least 8 characters).</li>
            <li><b>Roles</b> &mdash; tick <b>every</b> role this person fills (someone who fits and also sells gets both). The most
                privileged role drives admin access. The roles are <b>Admin, Owner, Office, Sales, Agent, Fitter, Readonly</b>.</li>
            <li><b>Permissions</b> &mdash; fine-tune what they can do and see: <b>Create quotes</b>, <b>Create orders</b>,
                <b>View all customer jobs</b>, <b>View costs</b>, and <b>Fittings only</b> (their calendar shows only fitting jobs).</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>These decide who sees the money.</b> <b>View costs</b> is what lets
             someone see your cost and profit figures &mdash; leave it <b>off</b> for fitters and sales staff who shouldn&rsquo;t.
             (Remember the Calendar &ldquo;show order value&rdquo; toggle overrides this <em>on the calendar</em>, so mind that one too.)
             <b>Fittings only</b> keeps a fitter&rsquo;s calendar to just their jobs. Get these wrong and people see too much &mdash; or too little.</div></div>
          <p>Click <b>Add user</b> and they appear in <b>Existing users</b> below, where you can <b>Edit</b> them later &mdash; change
             roles or permissions, reset the password, or switch the account off.</p>',
        'script'  => [
            ['0:00', 'Name, username, password fill in.',      'Add a login for each person. Their name, then an email — or just a username for workshop staff with no email — and a password.', 1],
            ['0:09', 'Tick the Fitter role.',                  'Tick every role they fill. Dave here is a fitter, so we tick Fitter.', 2],
            ['0:15', 'Tick Fittings only; View costs stays off.', 'Then permissions — what they can do and see. Fittings only keeps his calendar to fittings, and we leave View costs off so he doesn\'t see your figures.', 3],
            ['0:24', 'Add user; appears in the list.',         'Add user, and he can log in. You can edit his roles or reset his password any time.', 4],
        ],
    ],

    'products-wizard' => [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Setting up a product (wizard)',
        'eyebrow' => 'Products',
        'blurb'   => 'The setup wizard: Name → Systems → Fabrics → Price tables, start to finish.',
        'lede'    => 'A new product in four guided steps &mdash; <b>Name</b>, <b>Systems</b>, <b>Fabrics</b>, <b>Price tables</b>.
                      The wizard walks you through each one (and remembers where you got to); here&rsquo;s the whole flow.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .stepper{ display:flex; flex-wrap:wrap; gap:.4rem .7rem; margin-bottom:.9rem; }
          .gd .wstep{ display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; color:var(--faint); font-weight:600; }
          .gd .wstep .num{ width:18px; height:18px; border-radius:50%; background:var(--line); color:var(--soft); display:inline-flex; align-items:center; justify-content:center; font-size:.64rem; }
          .gd .stage[data-step="0"] .ws1, .gd .stage[data-step="1"] .ws1, .gd .stage[data-step="2"] .ws2, .gd .stage[data-step="3"] .ws3, .gd .stage[data-step="4"] .ws4{ color:var(--ink); }
          .gd .stage[data-step="0"] .ws1 .num, .gd .stage[data-step="1"] .ws1 .num, .gd .stage[data-step="2"] .ws2 .num, .gd .stage[data-step="3"] .ws3 .num, .gd .stage[data-step="4"] .ws4 .num{ background:var(--accent); color:#fff; }
          .gd .plbl2{ font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.4rem; }
          .gd .wpanel{ display:none; }
          .gd .stage[data-step="0"] .p1, .gd .stage[data-step="1"] .p1, .gd .stage[data-step="2"] .p2, .gd .stage[data-step="3"] .p3, .gd .stage[data-step="4"] .p4{ display:block; }
          .gd .nextbtn{ margin-top:.8rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.4rem .85rem; font-size:.8rem; font-weight:700; }
          .gd .addedrow{ display:flex; align-items:center; gap:.4rem; border:1px solid var(--line); border-radius:7px; padding:.35rem .55rem; font-size:.78rem; color:var(--ink); background:var(--panel); margin-bottom:.5rem; }
          .gd .addedrow .grn{ color:var(--good); font-weight:700; margin-left:auto; }
          .gd .fablist{ display:flex; flex-direction:column; gap:.35rem; }
          .gd .fabrow{ display:flex; justify-content:space-between; border:1px solid var(--line); border-radius:7px; padding:.3rem .55rem; font-size:.78rem; color:var(--ink); background:var(--panel); }
          .gd .fabrow .bnd{ color:var(--accent-ink); font-weight:700; font-size:.72rem; }
          .gd .pgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:2px; border:1px solid var(--line); border-radius:8px; overflow:hidden; max-width:20rem; }
          .gd .pgrid span{ padding:.25rem; font-size:.68rem; text-align:center; background:var(--panel); color:var(--soft); }
          .gd .pgrid span.h{ background:var(--surface); color:var(--faint); font-weight:700; }
          .gd .pdone{ display:inline-flex; align-items:center; gap:.4rem; margin-top:.6rem; color:var(--good); font-weight:700; font-size:.8rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / wizard</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Set up a product</div>
                <div class="stepper">
                  <span class="wstep ws1"><span class="num">1</span> Name</span>
                  <span class="wstep ws2"><span class="num">2</span> Systems</span>
                  <span class="wstep ws3"><span class="num">3</span> Fabrics</span>
                  <span class="wstep ws4"><span class="num">4</span> Price tables</span>
                </div>
                <div class="wpanel p1">
                  <div class="fld"><label>Product name</label><div class="box f1"><span class="ph">e.g. Louvolite Roller Blind</span><span class="val">Louvolite Roller Blind</span></div></div>
                  <div class="fld"><label>What do you call the material this is made of?</label><div class="box f1"><span class="ph">Fabric</span><span class="val">Fabric</span></div></div>
                  <div class="nextbtn">Create &amp; continue &rarr;</div>
                </div>
                <div class="wpanel p2">
                  <div class="plbl2">Systems (variants)</div>
                  <div class="addedrow">Standard <span class="grn">&check; added</span></div>
                  <div class="fld"><label>Add a system</label><div class="box"><span class="ph">e.g. Standard, or a slat size like 25mm</span></div></div>
                  <div class="nextbtn">Next: fabrics &rarr;</div>
                </div>
                <div class="wpanel p3">
                  <div class="plbl2">Fabrics &mdash; add a band, then paste the names</div>
                  <div class="frow">
                    <div class="fld"><label>Band</label><div class="box" style="max-width:8rem"><span class="val">Plain</span></div></div>
                    <div class="fld"><label>Available on</label><div class="selectbox">All systems (optional)</div></div>
                  </div>
                  <div class="fld"><label>Names &mdash; one per line or comma-separated</label><div class="ta"><span class="val">Plain White, Plain Cream, Silver, Anthracite</span></div></div>
                  <div class="fablist">
                    <div class="fabrow"><span>Plain White</span><span class="bnd">Plain</span></div>
                    <div class="fabrow"><span>Plain Cream</span><span class="bnd">Plain</span></div>
                    <div class="fabrow"><span>Silver</span><span class="bnd">Plain</span></div>
                  </div>
                  <div class="nextbtn">Next: price tables &rarr;</div>
                </div>
                <div class="wpanel p4">
                  <div class="plbl2">Price tables &mdash; Band: Plain (width &times; drop)</div>
                  <div class="pgrid">
                    <span class="h">mm</span><span class="h">600</span><span class="h">900</span><span class="h">1200</span>
                    <span class="h">1000</span><span>38</span><span>44</span><span>52</span>
                    <span class="h">1500</span><span>46</span><span>55</span><span>66</span>
                    <span class="h">2000</span><span>58</span><span>70</span><span>84</span>
                  </div>
                  <div style="font-size:.7rem;color:var(--soft);margin-top:.5rem">&#43; your <b>buying discount &amp; markup</b> per system (or inherit your defaults)</div>
                  <div class="pdone">&check; Prices in &mdash; product ready to quote.</div>
                </div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Name it, supplier first &mdash; and the material word.</b>
                  <b class="c2"><span class="n">2</span> Add a system &mdash; a variant, like a slat size.</b>
                  <b class="c3"><span class="n">3</span> Add a band (Plain), paste the names.</b>
                  <b class="c4 good"><span class="n">4</span> Import your price grids &mdash; done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>setup wizard</b> builds a product in four steps: <b>Name &rarr; Systems &rarr; Fabrics &rarr; Price tables</b>.
             It nudges you through each one, and you can close the tab and pick up where you left off.</p>
          <ul class="steps">
            <li><b>Name</b> &mdash; name the product, and say <b>what you call the material</b> it&rsquo;s made of. That&rsquo;s
                &ldquo;Fabric&rdquo; for a roller, but for a Venetian it&rsquo;s <em>Colours</em> or <em>Louvres</em> &mdash; not fabric.
                That word is the one your customer sees in the quote builder, so pick the one that fits the product.</li>
            <li><b>Systems</b> &mdash; add at least one <b>system</b> (a variant of the product &mdash; <em>Standard</em>, <em>Motorised</em>,
                or a slat size like <em>25mm</em>). Paste a whole list, <b>one per line</b>. A product can have several.</li>
            <li><b>Fabrics</b> &mdash; add a <b>band</b> first, then paste the names that sit in it. <b>Name the band for what it is</b>
                &mdash; <em>Plain</em>, <em>Blackout</em>, <em>Special effects</em> &mdash; you don&rsquo;t have to use A/B/C. Paste the names
                <b>one per line OR comma-separated</b> &mdash; whichever your list is in. <b>Available on</b> is optional: leave it blank and
                the band applies to every system, or pick one system to tie it down tight. (Some products &mdash; e.g. headrails &mdash; have
                no fabric; tick &ldquo;no fabric&rdquo; and skip this step.)</li>
            <li><b>Price tables</b> &mdash; import your <b>width &times; drop price grids</b>, one per band. This is what makes the product
                quotable. Setting up prices also means choosing the <b>pricing source</b> (our list vs a supplier&rsquo;s) and your
                <b>buying discount &amp; markup per system</b> &mdash; see <em>Pricing: source, markup &amp; mode</em> and
                <em>Building price tables</em> for the fast paste-from-Excel method.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Start the product name with the supplier.</b> The supplier acts like a
             <b>prefix</b> &mdash; at price-update time the system finds a product&rsquo;s prices by that prefix, so a name like
             <em>Louvolite Roller Blind</em> updates cleanly when Louvolite send a new price list. Forget it? Don&rsquo;t panic &mdash; it&rsquo;s
             easily changed afterwards; but getting it right up front saves work later.</div></div>
          <p><b>Tip:</b> on the Fabrics step there&rsquo;s a <em>&ldquo;Price tables first &rarr;&rdquo;</em> shortcut &mdash; import your
             grids first and the <b>bands auto-suggest</b> in the fabric&rsquo;s Band box, so you don&rsquo;t type band names twice.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The #1 thing to get right: the band must match.</b> A fabric&rsquo;s
             <b>band</b> is what links it to its prices &mdash; the <b>same band name must appear on the fabric AND on a price table</b>.
             Put a fabric on &ldquo;Plain&rdquo; but only import a price table for &ldquo;Special effects&rdquo;, and that fabric shows <b>no price</b>
             (or &ldquo;Needs fabric&rdquo;) in the quote builder. Bands are case- and spelling-sensitive, so &ldquo;Plain&rdquo; and
             &ldquo;plain&rdquo; are treated as different. Using the <em>&ldquo;Price tables first&rdquo;</em> shortcut avoids this by
             offering you the exact band names to pick.</div></div>
          <p>Once all four are done, the product is <b>ready to quote</b> &mdash; the last touch is its <b>options</b> (the extras a
             customer picks, like controls or a cassette): see <em>Adding options</em>. Separate guides also cover <b>pricing modes</b>,
             <b>building price tables</b>, <b>importing fabrics</b>, and <b>combining products</b> &mdash; and those show the common
             input errors and how to fix them.</p>',
        'script'  => [
            ['0:00', 'Name + material word.',            'The wizard sets up a product in four steps. Name it — and start the name with the supplier, like Louvolite Roller Blind, because price updates find it by that prefix. Then say what you call the material: Fabric for a roller, Colours for slats.', 1],
            ['0:13', 'Systems step; Standard added.',    'Add a system — a variant of the product, like Standard, Motorised, or a slat size. Paste a whole list, one per line.', 2],
            ['0:21', 'Fabrics; band Plain, names pasted.', 'Next, add a band — name it for what it is, like Plain — and paste the names, one per line or comma-separated. Leave "available on" blank for every system, or pick one to tie it down.', 3],
            ['0:31', 'Price tables; discount & markup.', 'And finally, the prices — import your width-by-drop grids, one per band, and set your buying discount and markup per system. That\'s the product ready to quote.', 4],
        ],
    ],

    'products-pricing-modes' => [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Pricing: source, markup & mode',
        'eyebrow' => 'Products',
        'blurb'   => 'The product Edit page pricing block: own vs supplier list, your markup & buying discount per system, and how the base price is looked up.',
        'lede'    => 'The <b>Pricing</b> block on a product&rsquo;s <b>Edit</b> page has three parts: <b>where the prices come from</b>
                      (our own list or a supplier&rsquo;s), your <b>markup &amp; buying discount per system</b>, and the <b>pricing mode</b>
                      (width &times; drop, width-only, per slat, or per m&sup2;). Here&rsquo;s the whole block.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB{ display:block; }
          .gd .stage[data-step="3"] .scC, .gd .stage[data-step="4"] .scC{ display:block; }
          .gd .fsleg{ font-size:.66rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin:0 0 .5rem; }
          .gd .fshelp{ font-size:.68rem; color:var(--faint); margin:.5rem 0 0; }
          .gd .fshelp a, .gd .srcnote b{ color:var(--accent); }
          .gd .srcrow{ display:flex; gap:.5rem; align-items:flex-start; margin-bottom:.5rem; }
          .gd .srcrow .radio{ margin:0; }
          .gd .srctxt{ font-size:.74rem; color:var(--soft); }
          .gd .srctxt b{ color:var(--ink); }
          /* per-system markup/discount table */
          .gd .ptbl{ width:100%; max-width:23rem; border-collapse:collapse; }
          .gd .ptbl th{ text-align:left; font-size:.6rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .45rem; }
          .gd .ptbl td{ padding:.4rem .45rem; border-bottom:1px solid var(--line); color:var(--ink); font-size:.74rem; vertical-align:top; }
          .gd .boxs{ height:26px; border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; background:var(--surface); display:inline-flex; align-items:center; padding:0 .45rem; font-size:.76rem; color:var(--ink); min-width:3rem; font-variant-numeric:tabular-nums; }
          .gd .boxs.empty{ color:var(--faint); }
          .gd .tag{ font-size:.58rem; margin-top:.14rem; line-height:1.1; }
          .gd .tag.def{ color:var(--faint); }
          .gd .tag.ovr{ color:#9333ea; font-weight:700; }
          /* modes */
          .gd .modes{ display:flex; flex-direction:column; gap:.5rem; }
          .gd .chkrow{ display:flex; align-items:flex-start; gap:.5rem; font-size:.78rem; color:var(--soft); }
          .gd .chkrow .tick{ margin-top:1px; flex:none; }
          .gd .stage[data-step="3"] .t-slat, .gd .stage[data-step="4"] .t-slat{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .stage[data-step="4"] .t-sqm{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .stage[data-step="3"] .m-slat, .gd .stage[data-step="4"] .m-slat, .gd .stage[data-step="4"] .m-sqm{ color:var(--ink); font-weight:600; }
          .gd .minarea{ display:none; margin-top:.7rem; max-width:15rem; }
          .gd .stage[data-step="4"] .minarea{ display:block; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / edit</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Pricing</div>

                <!-- Scene A: pricing source -->
                <div class="osc scA">
                  <p class="fsleg">Pricing source</p>
                  <div class="srcrow">
                    <span class="radio"><span class="dot"></span></span>
                    <span class="srctxt"><b>Our price list</b> &mdash; we make it. Trade accounts get these prices exactly as they are.</span>
                  </div>
                  <div class="srcrow">
                    <span class="radio on"><span class="dot"></span></span>
                    <span class="srctxt"><b>Supplier price list</b> &mdash; we buy it in. Trade accounts get it <b>less the discount plus the margin</b> set below.</span>
                  </div>
                </div>

                <!-- Scene B: markup + discount per system -->
                <div class="osc scB">
                  <p class="fsleg">Pricing per system</p>
                  <table class="ptbl">
                    <thead><tr><th>System</th><th>Markup %</th><th>Discount %</th></tr></thead>
                    <tbody>
                      <tr><td>Standard</td><td><span class="boxs empty">&nbsp;</span><div class="tag def">using default (50%)</div></td><td><span class="boxs">0.00</span></td></tr>
                      <tr><td>Motorised</td><td><span class="boxs">100</span><div class="tag ovr">override</div></td><td><span class="boxs">25</span></td></tr>
                    </tbody>
                  </table>
                  <p class="fshelp">Your markup is applied on top of the price-table base; discount comes off after that. Set markup to 0 to inherit the tenant default (<b>50%</b> &mdash; change on Settings).</p>
                </div>

                <!-- Scene C: pricing mode -->
                <div class="osc scC">
                  <p class="fsleg">Pricing mode</p>
                  <p class="ldesc2">Most products price on a width &times; drop grid &mdash; tick a box only if this one is different.</p>
                  <div class="modes">
                    <label class="chkrow"><span class="tick">&check;</span> This product has no fabrics (headrail only, track, spares)</label>
                    <label class="chkrow"><span class="tick">&check;</span> Priced by width only (no drop) &mdash; e.g. a headrail or track</label>
                    <label class="chkrow m-slat"><span class="tick t-slat">&check;</span> Priced per slat (by drop) &mdash; e.g. vertical fabric only</label>
                    <label class="chkrow m-sqm"><span class="tick t-sqm">&check;</span> Priced per square metre &mdash; e.g. shutters</label>
                  </div>
                  <div class="minarea"><label>Minimum billable area (m&sup2;)</label><div class="boxs" style="min-width:8rem">0.5</div></div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Pricing source &mdash; our list, or a supplier&rsquo;s?</b>
                  <b class="c2"><span class="n">2</span> Markup &amp; buying discount, per system.</b>
                  <b class="c3"><span class="n">3</span> Pricing mode &mdash; how the base price is looked up.</b>
                  <b class="c4 good"><span class="n">4</span> Per m&sup2;? Add a minimum billable area.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Pricing</b> block on a product&rsquo;s <b>Edit</b> page is three settings stacked together. Get them right here and every
             quote for this product prices correctly.</p>
          <ul class="steps">
            <li><b>Pricing source</b> &mdash; what the numbers in the price tables actually <em>are</em>.
                <b>Our price list</b> = our own selling prices; trade accounts get them <em>exactly as they are</em>.
                <b>Supplier price list</b> = a bought-in trade list; trade accounts (and your quotes) get it <b>less your buying discount, plus
                your markup</b>. This is the same choice shown in the banner on the price-table screen.</li>
            <li><b>Pricing per system</b> &mdash; your <b>Markup %</b> and <b>Discount %</b>, tuned per system (Standard / Motorised are usually
                different). Your markup goes <b>on top of the price-table base; the discount comes off after that</b>. Leave a box <b>blank / 0</b>
                to <b>inherit the tenant default</b> (a &ldquo;using default (X%)&rdquo; tag shows); type a number and it becomes an
                <b>override</b> (purple tag). These are the <em>same numbers</em> you can edit straight on the price table
                (see <em>Building price tables</em>).</li>
            <li><b>Pricing mode</b> &mdash; how the base price is looked up. The default is a <b>width &times; drop grid</b> (tick nothing).
                The others:
                <ul>
                  <li><b>No fabrics</b> &mdash; headrail, track or spares; priced on system &times; size, no fabric picker.</li>
                  <li><b>Width only</b> &mdash; priced on width, no drop; each table is a width &rarr; price list.</li>
                  <li><b>Per slat</b> &mdash; priced by the <b>drop</b> &times; the <b>number of slats</b> (vertical fabric replacement).</li>
                  <li><b>Per square metre</b> &mdash; a single <b>&pound;/m&sup2; rate</b> &times; area (width &times; height), e.g. shutters, with an optional <b>minimum billable area</b>.</li>
                </ul>
            </li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin?</b> Whether the column says <b>Markup %</b> or
             <b>Margin %</b> is set once in <b>Settings &rarr; Default margins</b> &mdash; the customer price is identical either way, it only
             changes which number you type. The tenant default there is what an empty box inherits.</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Pick one mode, and pick the right one.</b> Width-only, per-slat and per-m&sup2;
             <b>can&rsquo;t be combined</b> &mdash; they price in different shapes, and the mode decides what the quote builder asks for and what
             your price tables look like. Switching it later means re-importing your prices, so get it right <em>before</em> you build the tables.</div></div>',
        'script'  => [
            ['0:00', 'Pricing source: supplier selected.',    'The pricing block starts with the source. Is this our own price list — which trade accounts get exactly as-is — or a supplier list we buy in? Pick supplier, and your discount and margin get applied on top.', 1],
            ['0:11', 'Markup & discount per system.',         'Then your markup and buying discount, per system. Leave a system blank to inherit your Settings default, or type a value to override it — like a hundred percent markup, twenty-five off, on motorised.', 2],
            ['0:22', 'Pricing mode; per slat ticked.',        'Next, the pricing mode — how the base price is looked up. Most products use a width-by-drop grid, so you tick nothing. Vertical fabric prices per slat.', 3],
            ['0:31', 'Per m² ticked; minimum area appears.',  'And shutters price per square metre — width times height — with an optional minimum billable area.', 4],
        ],
    ],

    'products-import-price-tables' => [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Building price tables',
        'eyebrow' => 'Products',
        'blurb'   => 'The real workflow: paste the supplier grid, set your buying discount and markup right on the table, save, clone the next band, then prove the sell price in Live preview.',
        'lede'    => 'A price table is the <b>supplier&rsquo;s grid</b> &mdash; every width &times; drop and their list price. You paste that in,
                      then set <b>your buying discount and your markup right here</b>, and the system works out the sell price. Set the sizes,
                      paste the prices, <b>enter your discount &amp; markup</b>, save, clone the next band, and <b>prove it in Live preview</b>.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }

          /* ---- price-source banner (all steps) ---- */
          .gd .psbanner{ display:flex; align-items:center; flex-wrap:wrap; gap:.35rem .5rem; border:1px solid #e6b64c; background:rgba(230,182,76,.14); border-radius:9px; padding:.42rem .6rem; font-size:.7rem; color:var(--soft); margin-bottom:.7rem; }
          .gd .psbanner .pill{ background:#e6b64c; color:#4a3600; font-weight:700; border-radius:20px; padding:.08rem .55rem; font-size:.66rem; white-space:nowrap; }
          .gd .psbanner .chg{ margin-left:auto; color:var(--accent); font-weight:600; }

          /* ---- Quick start dialog (steps 1-2) ---- */
          .gd .qsdlg{ display:none; border:1px solid var(--line); border-radius:10px; padding:.7rem .8rem; background:var(--panel); max-width:24rem; }
          .gd .stage[data-step="1"] .qsdlg, .gd .stage[data-step="2"] .qsdlg{ display:block; }
          .gd .qshd{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.04em; margin-bottom:.55rem; }
          .gd .qsrow{ margin-bottom:.55rem; }
          .gd .qsrow label{ display:block; font-size:.68rem; font-weight:600; color:var(--soft); margin-bottom:.2rem; }
          .gd .ta2{ position:relative; min-height:1.9rem; border:1px solid var(--line); border-radius:7px; background:var(--surface); padding:.34rem .5rem; font-size:.74rem; color:var(--ink); overflow:hidden; }
          .gd .ta2 .def{ color:var(--faint); }
          .gd .stage[data-step="1"] .ta2 .def{ text-decoration:line-through; opacity:.55; }
          .gd .stage[data-step="2"] .ta2 .def{ display:none; }
          .gd .ta2 .rv{ display:none; }
          .gd .stage[data-step="2"] .ta2 .rv{ display:inline-block; clip-path:inset(0 100% 0 0); animation:gdRoll .8s ease forwards; }
          .gd .stage[data-step="2"] .ta2{ box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.35)); }
          .gd .clearchip{ display:inline-block; margin-top:.15rem; font-size:.64rem; color:var(--faint); border:1px solid var(--line); border-radius:20px; padding:.05rem .5rem; }
          .gd .stage[data-step="1"] .clearchip{ box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.35)); color:var(--ink); }
          .gd .buildbtn{ display:inline-flex; margin-top:.2rem; background:var(--nav); color:#fff; border-radius:8px; padding:.4rem .8rem; font-size:.78rem; font-weight:600; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="3"] .buildbtn{ transform:scale(.96); filter:brightness(1.25); }

          /* ---- the grid (steps 3-8) ---- */
          .gd .ptwrap{ display:none; margin-top:.2rem; }
          .gd .stage[data-step="3"] .ptwrap, .gd .stage[data-step="4"] .ptwrap,
          .gd .stage[data-step="5"] .ptwrap, .gd .stage[data-step="6"] .ptwrap,
          .gd .stage[data-step="7"] .ptwrap, .gd .stage[data-step="8"] .ptwrap{ display:block; }
          .gd .ptcap{ font-size:.64rem; color:var(--faint); margin:0 0 .25rem; }
          .gd .ptgrid{ display:grid; grid-template-columns:2.9rem repeat(4,1fr); gap:2px; background:var(--line); border:1px solid var(--line); border-radius:8px; padding:2px; max-width:23rem; }
          .gd .ptc{ background:var(--surface); padding:.26rem; text-align:center; font-size:.7rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .ptc.hd{ background:var(--panel); color:var(--faint); font-weight:700; }
          .gd .ptc .pn{ opacity:0; transition:opacity .45s ease; }
          .gd .stage[data-step="4"] .ptc .pn, .gd .stage[data-step="5"] .ptc .pn,
          .gd .stage[data-step="6"] .ptc .pn, .gd .stage[data-step="7"] .ptc .pn,
          .gd .stage[data-step="8"] .ptc .pn{ opacity:1; }
          /* the list cell we trace through to the sell price */
          .gd .stage[data-step="7"] .ptc.qc{ background:#d1fae5; color:#065f46; font-weight:700; box-shadow:inset 0 0 0 2px #34d399; }

          .gd .savebtn2{ margin-top:.7rem; display:inline-flex; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .85rem; font-size:.82rem; font-weight:600; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="5"] .savebtn2{ transform:scale(.97); filter:brightness(1.2); }
          .gd .ok-saved{ display:none; margin-top:.6rem; }
          .gd .stage[data-step="5"] .ok-saved, .gd .stage[data-step="6"] .ok-saved,
          .gd .stage[data-step="7"] .ok-saved, .gd .stage[data-step="8"] .ok-saved{ display:flex; }

          /* ---- trade-terms form: buying discount + markup (steps 6-8) ---- */
          .gd .tterms{ display:none; margin-top:.7rem; border:1px solid #e6b64c; border-radius:9px; padding:.55rem .7rem; background:rgba(230,182,76,.1); max-width:23rem; }
          .gd .stage[data-step="6"] .tterms, .gd .stage[data-step="7"] .tterms, .gd .stage[data-step="8"] .tterms{ display:block; }
          .gd .stage[data-step="6"] .tterms{ box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.3)); }
          .gd .tthd{ font-size:.68rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.45rem; }
          .gd .ttrow{ display:flex; gap:1rem; }
          .gd .ttf label{ display:block; font-size:.64rem; font-weight:600; color:var(--soft); margin-bottom:.18rem; }
          .gd .boxs{ height:28px; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; background:var(--surface); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); min-width:3.4rem; font-variant-numeric:tabular-nums; }
          .gd .savebtn3{ margin-top:.55rem; display:inline-flex; background:var(--nav); color:#fff; border-radius:8px; padding:.38rem .8rem; font-size:.78rem; font-weight:600; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="6"] .savebtn3{ transform:scale(.97); filter:brightness(1.2); }
          .gd .tthelp{ font-size:.64rem; color:var(--faint); margin-top:.4rem; }

          /* ---- live-preview card (step 7) ---- */
          .gd .pvcard{ display:none; margin-top:.7rem; border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:23rem; }
          .gd .stage[data-step="7"] .pvcard{ display:block; }
          .gd .pvhd{ display:flex; align-items:center; gap:.4rem; background:var(--panel); padding:.4rem .65rem; font-size:.72rem; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); }
          .gd .pvbody{ padding:.55rem .65rem; }
          .gd .pvdim{ font-size:.72rem; color:var(--soft); margin-bottom:.4rem; }
          .gd .pvdim b{ color:var(--ink); }
          .gd .pvcalc{ font-size:.72rem; color:var(--soft); margin-bottom:.45rem; font-variant-numeric:tabular-nums; }
          .gd .pvcalc b{ color:var(--ink); }
          .gd .pvcalc .ar{ color:var(--faint); }
          .gd .pvres{ background:#d1fae5; color:#065f46; border-radius:8px; padding:.5rem .65rem; font-size:.74rem; }
          .gd .pvres .big{ font-size:1.05rem; font-weight:700; }

          /* ---- clone panel (step 8) ---- */
          .gd .clonep{ display:none; margin-top:.7rem; border:1px dashed var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); max-width:23rem; }
          .gd .stage[data-step="8"] .clonep{ display:block; }
          .gd .clonep .ct{ font-size:.74rem; color:var(--ink); margin-bottom:.4rem; }
          .gd .clonebtn{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--line); border-radius:7px; padding:.32rem .7rem; font-size:.74rem; font-weight:600; color:var(--ink); box-shadow:0 0 0 2px var(--accent-ring, rgba(37,99,235,.3)); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / price-table</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Price table &mdash; 25mm Venetian / Band: Plain</div>

                <!-- price-source banner: this grid is a supplier list -->
                <div class="psbanner"><span class="pill">&#9888; Supplier price list</span> These are the supplier&rsquo;s list prices &mdash; our price = list &minus; buying discount + markup. <span class="chg">Change</span></div>

                <!-- Quick start dialog: sample sizes you clear, then paste your own -->
                <div class="qsdlg">
                  <div class="qshd">Quick start &mdash; default grid</div>
                  <div class="qsrow">
                    <label>Widths (mm)</label>
                    <div class="ta2"><span class="def">500, 600, 700, 800, 900, 1000</span><span class="rv">600, 900, 1200, 1500</span></div>
                    <span class="clearchip">Clear</span>
                  </div>
                  <div class="qsrow">
                    <label>Drops (mm)</label>
                    <div class="ta2"><span class="def">600, 900, 1200, 1500</span><span class="rv">900, 1200, 1500, 1800</span></div>
                    <span class="clearchip">Clear</span>
                  </div>
                  <div class="buildbtn">Build grid</div>
                </div>

                <!-- the grid: supplier LIST prices -->
                <div class="ptwrap">
                  <p class="ptcap">Supplier list prices (&pound;) &mdash; Drop &#92; Width</p>
                  <div class="ptgrid">
                    <span class="ptc hd">mm</span><span class="ptc hd">600</span><span class="ptc hd">900</span><span class="ptc hd">1200</span><span class="ptc hd">1500</span>
                    <span class="ptc hd">900</span><span class="ptc"><span class="pn">22.80</span></span><span class="ptc"><span class="pn">28.50</span></span><span class="ptc"><span class="pn">34.20</span></span><span class="ptc"><span class="pn">40.60</span></span>
                    <span class="ptc hd">1200</span><span class="ptc"><span class="pn">26.40</span></span><span class="ptc"><span class="pn">33.20</span></span><span class="ptc qc"><span class="pn">40.00</span></span><span class="ptc"><span class="pn">46.80</span></span>
                    <span class="ptc hd">1500</span><span class="ptc"><span class="pn">30.90</span></span><span class="ptc"><span class="pn">38.40</span></span><span class="ptc"><span class="pn">45.80</span></span><span class="ptc"><span class="pn">53.70</span></span>
                    <span class="ptc hd">1800</span><span class="ptc"><span class="pn">35.20</span></span><span class="ptc"><span class="pn">43.80</span></span><span class="ptc"><span class="pn">52.10</span></span><span class="ptc"><span class="pn">61.40</span></span>
                  </div>
                  <div class="savebtn2">Save grid</div>
                  <div class="okbanner ok-saved"><span>&check;</span> Saved 16 price cells.</div>
                </div>

                <!-- trade terms: buying discount + our markup -->
                <div class="tterms">
                  <div class="tthd">Supplier terms &mdash; applies to every band on 25mm Venetian</div>
                  <div class="ttrow">
                    <div class="ttf"><label>Buying discount %</label><div class="boxs">25</div></div>
                    <div class="ttf"><label>Our markup %</label><div class="boxs">100</div></div>
                  </div>
                  <div class="savebtn3">Save terms</div>
                  <div class="tthelp">Leave blank or 0 to inherit the default (Settings &rarr; Default margins).</div>
                </div>

                <!-- qualify in Live preview: list -> discount -> markup -> sell -->
                <div class="pvcard">
                  <div class="pvhd">&#128065; Live preview</div>
                  <div class="pvbody">
                    <div class="pvdim">25mm Venetian &middot; Plain &middot; <b>1200</b> &times; <b>1200</b> mm</div>
                    <div class="pvcalc">List <b>&pound;40.00</b> <span class="ar">&minus;25%&rarr;</span> cost &pound;30.00 <span class="ar">+100%&rarr;</span> sell <b>&pound;60.00</b></div>
                    <div class="pvres">Sell price <span class="big">&pound;60.00</span><br>&check; the &pound;40 list cell, less your 25% discount, plus your 100% markup.</div>
                  </div>
                </div>

                <!-- clone the next band; terms already cover it -->
                <div class="clonep">
                  <div class="ct">Next band: <b>Special effects</b> &mdash; same shape, and your terms already apply.</div>
                  <span class="clonebtn">&#9635; Clone Plain</span>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Quick start offers sample sizes &mdash; clear them.</b>
                  <b class="c2"><span class="n">2</span> Paste your real widths &amp; drops from Excel.</b>
                  <b class="c3"><span class="n">3</span> Build grid &mdash; a cell per width &times; drop.</b>
                  <b class="c4"><span class="n">4</span> Paste the supplier&rsquo;s list prices in.</b>
                  <b class="c5"><span class="n">5</span> Save grid &mdash; 16 cells saved.</b>
                  <b class="c6"><span class="n">6</span> Set your buying discount &amp; markup.</b>
                  <b class="c7 good"><span class="n">7</span> List &minus; discount + markup = the sell price.</b>
                  <b class="c8"><span class="n">8</span> Clone the next band &mdash; terms already apply.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A price table is a <b>supplier&rsquo;s grid</b> for one band: every <b>width &times; drop</b> and their <b>list price</b> at that size.
             The strip at the top tells you what the grid holds &mdash; <b>&ldquo;Supplier price list&rdquo;</b> (a bought-in list you mark up) or
             <b>&ldquo;Our price list&rdquo;</b> (your own selling prices). Open a band&rsquo;s table from the system&rsquo;s <b>Price tables</b> page;
             it starts empty with a <b>Quick start</b> grid of sample sizes &mdash; a placeholder, so clear it and put your own in.</p>
          <ul class="steps">
            <li><b>Set your real sizes.</b> In Quick start, <b>Clear</b> the sample widths and drops, <b>paste your own</b> from the supplier&rsquo;s
                sheet (drag across in Excel, <kbd>Ctrl</kbd>+<kbd>C</kbd>, click the box, <kbd>Ctrl</kbd>+<kbd>V</kbd>), then <b>Build grid</b>.</li>
            <li><b>Paste the prices.</b> Select the whole block of list prices in Excel, copy, click the first grid cell and paste &mdash; the range
                spreads across the cells. Then <b>Save grid</b>.</li>
            <li><b>Set your discount &amp; markup &mdash; right here.</b> On a <b>supplier</b> list the table shows a <b>Buying discount %</b> and an
                <b>Our markup %</b> box. Fill them and <b>Save terms</b>: your price = <b>list &minus; discount, then + markup</b>. In the demo a
                &pound;40 list cell, less 25%, is &pound;30 cost; plus 100% markup is a <b>&pound;60 sell price</b>. These terms <b>apply to every
                band on that system</b>, so you set them once.</li>
            <li><b>Clone the next band.</b> The next band is usually the <em>same shape</em> at different prices. Open the empty one, click
                <b>Clone</b> beside a filled band, paste the column that differs, and Save &mdash; your discount/markup already cover it.</li>
            <li><b>Prove it.</b> On the product page click <b>&#128065; Live preview</b>, pick the band and a size, and check the <b>sell price</b>
                traces back to the grid cell through your discount and markup.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Markup or margin &mdash; your choice of wording.</b> The box is labelled
             <b>&ldquo;Our markup %&rdquo;</b> or <b>&ldquo;Our margin %&rdquo;</b> depending on what you picked in <b>Settings &rarr; Default margins</b>
             (<em>Markup</em> = added on top of cost; <em>Margin</em> = the profit slice of the sell price). The customer price is identical either
             way &mdash; it only changes which number you type.</div></div>
          <p><b>Where these numbers come from (the pecking order).</b> A blank or <b>0</b> discount/markup on the table means <b>&ldquo;use the
             default&rdquo;</b> &mdash; the tenant-wide figures in <b>Settings &rarr; Default margins</b>. Type a value here (or on the product
             <b>Edit &rarr; Pricing per system</b> table, which writes the very same numbers) and it <b>overrides the default</b> for that product
             &amp; system. Setting it back to 0 clears the override and drops back to the default.</p>
          <p><b>&ldquo;Our price list&rdquo; products are simpler:</b> there&rsquo;s <b>no discount/markup box</b> &mdash; the grid <em>is</em> the
             sell price, pushed to trade accounts exactly as typed. (Master-admins also see a small <b>list &rarr; cost &rarr; sell &middot;
             margin%</b> line under each cell.)</p>
          <p><b>Had a price rise?</b> Use <b>Adjust all prices by [ ]%</b> &mdash; &ldquo;Apply&rdquo; multiplies every saved cell (a negative number
             reduces), rounded to 2 dp. And <b>Bulk import (multiple bands)</b> reads one Excel file with every band&rsquo;s grid (each block
             starting with a <code>Band X</code> row in column A; pick the worksheet if there are several).</p>
          <div class="oops"><b>Import says &ldquo;No band sections detected&rdquo;?</b> Each grid in the file needs a <code>Band A</code>
             (or B, C&hellip;) row in <b>column A</b> above it. Add those and re-import. (Spelling is forgiving &mdash; <code>Band A</code>,
             <code>Price Band A</code>, even a <code>Bnad A</code> typo &mdash; but the marker must be there. &pound; signs and commas in prices are stripped automatically.)</div>
          <p><b>Other pricing modes build differently:</b> a <b>width-only</b> product is a simple <b>width &rarr; price</b> list; <b>per-slat</b>
             is a <b>drop &rarr; rate</b> list (with <em>Import width prices</em> / <em>Import per-slat rates</em> buttons); and <b>per m&sup2;</b>
             is a single <b>&pound;/m&sup2; rate</b> per system &amp; band. Same idea &mdash; type, paste, or import; the discount &amp; markup work the same way.</p>
          <p>Whichever way, <b>re-saving or re-importing replaces</b> that table&rsquo;s prices, and it&rsquo;s all one screen &mdash; use your
             browser&rsquo;s <b>Back</b> button to step out.</p>',
        'script'  => [
            ['0:00', 'Supplier list; Quick start sizes.',  'This grid is a supplier price list. Open the band\'s table and it starts empty — Quick start offers some default sizes, but those aren\'t yours, so clear them out.', 1],
            ['0:09', 'Real widths and drops pasted.',      'Now paste your real widths and drops straight from the supplier\'s spreadsheet — a whole row or column at a time.', 2],
            ['0:16', 'Build grid.',                        'Click Build grid, and you get a cell for every width and drop — empty, waiting for prices.', 3],
            ['0:23', 'List prices pasted from Excel.',     'These are the supplier\'s list prices. Copy the whole block in Excel and paste it straight into the grid.', 4],
            ['0:30', 'Saved 16 price cells.',              'Click Save grid — sixteen cells saved. That\'s the supplier\'s list in.', 5],
            ['0:37', 'Buying discount + markup entered.',  'Now the money bit. Because it\'s a supplier list, set your buying discount — twenty-five percent off — and your own markup, a hundred percent. Save terms. These apply to every band on this system.', 6],
            ['0:49', 'List, discount, markup = sell.',     'Live preview proves it. The forty-pound list cell, less your twenty-five percent discount, is thirty pounds cost — plus your hundred percent markup makes a sixty-pound sell price.', 7],
            ['1:00', 'Clone the next band.',               'The next band is the same shape, and your terms already cover it — so just clone it and paste the prices that differ.', 8],
        ],
    ],

    'products-import-fabrics' => [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Importing fabrics',
        'eyebrow' => 'Products',
        'blurb'   => 'Add fabrics from a template — one product or many — and fix the row errors.',
        'lede'    => 'Add a product&rsquo;s fabrics from a spreadsheet instead of one by one. Use the template, and if a row&rsquo;s
                      missing its <b>band</b> or <b>name</b>, the import tells you exactly which.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.8rem; margin:0 0 .75rem; }
          .gd .tmplbtn{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.3rem .6rem; font-size:.78rem; font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .cols{ font-size:.76rem; color:var(--soft); margin:.6rem 0 .7rem; }
          .gd .cols b{ color:var(--ink); }
          .gd .filebox{ display:inline-flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:7px; padding:.3rem .5rem; background:var(--surface); font-size:.8rem; }
          .gd .choosebtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.2rem .55rem; background:var(--panel); color:var(--ink); font-size:.76rem; }
          .gd .fn{ color:var(--soft); }
          .gd .impbtn{ margin-top:.75rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.4rem .8rem; font-size:.8rem; font-weight:700; }
          .gd .stage[data-step="2"] .impbtn, .gd .stage[data-step="3"] .impbtn{ transform:scale(.97); filter:brightness(1.1); }
          .gd .err-row{ display:none; margin-top:.75rem; }
          .gd .stage[data-step="2"] .err-row{ display:flex; }
          .gd .ok-fab{ display:none; margin-top:.75rem; }
          .gd .stage[data-step="3"] .ok-fab{ display:flex; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / import-fabrics</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Import fabrics &mdash; Roller Blind</div>
                <p class="ldesc2">Fill the template, then upload. Band and name are required; colour, supplier and code are optional.</p>
                <div class="tmplbtn">&#11015; Download blank template (.xlsx)</div>
                <div class="cols">Columns: <b>Band*</b> &middot; <b>Fabric name*</b> &middot; Colour &middot; Supplier &middot; Code</div>
                <div class="fld"><label>Filled template (.xlsx)</label>
                  <div class="filebox"><span class="choosebtn">Choose File</span> <span class="fn">fabrics.xlsx</span></div></div>
                <div class="impbtn">Upload &amp; import</div>
                <div class="errbanner err-row"><span>&#9888;</span><div><b>Some rows had problems:</b> Row 3: missing band</div></div>
                <div class="okbanner ok-fab"><span>&check;</span> Imported 12 fabrics. Skipped 1 duplicate.</div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Fill the template &mdash; Band + name required.</b>
                  <b class="c2 err"><span class="n">2</span> A row missing its Band? It names it.</b>
                  <b class="c3 good"><span class="n">3</span> Fix it, re-import &mdash; fabrics in.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A product&rsquo;s fabrics are its <b>colour / material choices</b> (its extras &mdash; controls, cassettes and the like &mdash;
             are separate <em>options</em>, covered in <em>Adding options</em>). Add fabrics from a spreadsheet rather than one at a time.</p>
          <ul class="steps">
            <li><b>For one product</b> &mdash; on its <b>Fabrics</b> page, <b>Import from Excel</b>. Download the blank <b>template</b>,
                fill it in (columns <b>Band*</b>, <b>Fabric name*</b>, Colour, Supplier, Code &mdash; * = required), and upload.
                Duplicate rows (band + name + colour) are skipped automatically.</li>
            <li><b>For lots of products at once</b> &mdash; the <b>Bulk import fabrics</b> button on the Products page reads a workbook
                with <b>one sheet per product</b> (columns Name, Colour, Band). Each sheet is matched to a product; you can <b>pick
                several</b> (Ctrl/Cmd-click) so one shared range feeds every blind that uses it.</li>
          </ul>
          <p><b>The Band is yours to name.</b> Whatever you type in the <b>Band</b> column is the band &mdash; <b>name it for what it is</b>
             (<em>Plain</em>, <em>Blackout</em>, <em>Special effects</em>), you don&rsquo;t have to use A/B/C. Just keep it <b>identical</b> to
             the band on the matching price table (see the heads-up below).</p>
          <div class="oops"><b>&ldquo;Row 3: missing band&rdquo; (or missing name)?</b> Every fabric needs a <b>band</b> and a <b>name</b> &mdash;
             the import lists the exact rows that don&rsquo;t, and the good rows still go in. Fill the gaps and re-import. Always use the
             <b>.xlsx template</b> &mdash; a hand-made CSV with everything crammed into one column won&rsquo;t map to the Band/Name/Colour
             columns and every row will be rejected.</div>
          <p><b>Just a few to add?</b> You don&rsquo;t need a spreadsheet at all &mdash; the wizard&rsquo;s <b>Fabrics</b> step lets you type a
             band and <b>paste the names straight in, one per line or comma-separated</b>, and leave <b>Available on</b> blank for every
             system (or pick one to tie the band to a single system).</p>
          <p>Once a product has at least one fabric and a price table, its <b>&ldquo;Needs fabric&rdquo;</b> flag clears and it&rsquo;s ready to
             quote. Remember the <b>band on the fabric must match a price-table band</b> exactly, or it shows no price.</p>',
        'script'  => [
            ['0:00', 'Import form; template + columns.', 'Add your fabrics from a spreadsheet. Download the template — Band and name are required, colour and code optional.', 1],
            ['0:08', 'Error: Row 3 missing band.',       'Leave a band off a row and it tells you which — some rows had problems, row 3, missing band.', 2],
            ['0:15', 'Imported 12 fabrics.',             'Fill that band in, re-import, and your fabrics are in — and the product\'s "needs fabric" flag clears.', 3],
        ],
    ],

    'products-options' => [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Adding options',
        'eyebrow' => 'Products',
        'blurb'   => 'Build the extras a salesperson picks — control type, motor, bottom weight — with prices, follow-on options and measurement boxes.',
        'lede'    => 'Options are the <b>extras</b> your salesperson picks for each blind &mdash; <em>Control type</em>, <em>Bottom weight</em>,
                      <em>Motor type</em>, <em>Bracket colour</em>. Each option has <b>choices</b>, each choice can carry a <b>price</b>, and an
                      option can be set to <b>appear only when</b> another choice is picked. Here&rsquo;s the whole thing, end to end.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.8rem; margin:0 0 .75rem; }
          /* a statically-filled field (looks like .fld .box, but always shows its value) */
          .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }
          .gd .orow{ display:flex; gap:1.3rem; margin-top:.5rem; }
          .gd .chkline{ display:inline-flex; align-items:center; gap:.4rem; font-size:.76rem; color:var(--ink); }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA{ display:block; }
          .gd .stage[data-step="2"] .scB, .gd .stage[data-step="3"] .scB{ display:block; }
          .gd .stage[data-step="4"] .scC{ display:block; }
          .gd .stage[data-step="5"] .scD{ display:block; }
          .gd .stage[data-step="6"] .scE{ display:block; }

          /* add-option form */
          .gd .exlabel{ font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin:0 0 .45rem; }
          .gd .exhelp{ font-size:.68rem; color:var(--faint); margin:.3rem 0 0; }
          .gd .addbtn{ margin-top:.8rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.4rem .85rem; font-size:.8rem; font-weight:700; }

          /* choices grid */
          .gd .ogrid{ display:grid; grid-template-columns:1fr 3.4rem 2.4rem 2.4rem 2.4rem; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:8px; overflow:hidden; max-width:23rem; }
          .gd .oc{ background:var(--surface); padding:.28rem .3rem; font-size:.68rem; color:var(--ink); text-align:center; }
          .gd .oc.l{ text-align:left; }
          .gd .oc.hd{ background:var(--panel); color:var(--faint); font-weight:700; font-size:.62rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .oc.newrow{ color:var(--faint); font-style:italic; }
          .gd .oflat{ opacity:0; transition:opacity .4s; }
          .gd .stage[data-step="3"] .oflat{ opacity:1; }
          .gd .stage[data-step="3"] .oc.flatcell{ box-shadow:inset 0 0 0 2px var(--accent); }
          .gd .otick{ display:inline-block; width:.85rem; height:.85rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:3px; position:relative; vertical-align:middle; }
          .gd .otick.on{ background:var(--accent); border-color:var(--accent); }
          .gd .otick.on::after{ content:"\2713"; color:#fff; font-size:.6rem; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; }
          .gd .gridcols{ font-size:.66rem; color:var(--faint); margin-top:.4rem; }

          /* appears-when list */
          .gd .awlist{ border:1px solid var(--line); border-radius:8px; padding:.4rem .55rem; background:var(--panel); max-width:20rem; }
          .gd .awrow{ display:flex; align-items:center; gap:.45rem; font-size:.74rem; color:var(--soft); padding:.16rem 0; }
          .gd .awrow.on{ color:var(--ink); font-weight:600; }

          /* number-input fieldset */
          .gd .nfield{ border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--panel); max-width:22rem; }
          .gd .nlegend{ font-size:.7rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin-bottom:.4rem; }

          /* quote-builder preview */
          .gd .pvq{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:22rem; }
          .gd .pvqhd{ background:var(--panel); padding:.4rem .65rem; font-size:.7rem; font-weight:700; color:var(--faint); border-bottom:1px solid var(--line); }
          .gd .pvqbody{ padding:.55rem .65rem; display:flex; flex-direction:column; gap:.5rem; }
          .gd .pvqrow label{ display:block; font-size:.68rem; font-weight:600; color:var(--soft); margin-bottom:.18rem; }
          .gd .pvqrow .req{ color:#b91c1c; }
          .gd .pvqchild{ margin-left:.7rem; padding-left:.55rem; border-left:2px solid var(--line); }
          .gd .pvqsum{ margin-top:.2rem; background:#d1fae5; color:#065f46; border-radius:7px; padding:.4rem .6rem; font-size:.74rem; font-weight:600; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / options</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Options &mdash; Roller Blind</div>

                <!-- Scene A: add an option -->
                <div class="osc scA">
                  <p class="exlabel">Add an option</p>
                  <div class="fld"><label>Name</label><div class="box f1"><span class="ph">e.g. Control side</span><span class="val">Control type</span></div></div>
                  <div class="orow">
                    <span class="chkline"><span class="tick on">&check;</span> Required</span>
                    <span class="chkline"><span class="tick">&check;</span> Allow multiple choices</span>
                  </div>
                  <p class="exhelp">Examples: Control side, Control type, Draw side, Lining, Motor type, Headrail colour.</p>
                  <div class="addbtn">Add option</div>
                </div>

                <!-- Scene B: choices grid -->
                <div class="osc scB">
                  <p class="exlabel">Choices for &ldquo;Control type&rdquo;</p>
                  <div class="ogrid">
                    <span class="oc hd l">Label</span><span class="oc hd">Flat &pound;</span><span class="oc hd">%</span><span class="oc hd">Default</span><span class="oc hd">Active</span>
                    <span class="oc l">Cord</span><span class="oc"></span><span class="oc"></span><span class="oc"><span class="otick on"></span></span><span class="oc"><span class="otick on"></span></span>
                    <span class="oc l">Motorised</span><span class="oc flatcell"><span class="oflat">120.00</span></span><span class="oc"></span><span class="oc"><span class="otick"></span></span><span class="oc"><span class="otick on"></span></span>
                    <span class="oc l newrow">Type new label and press Enter&hellip;</span><span class="oc"></span><span class="oc"></span><span class="oc"></span><span class="oc"></span>
                  </div>
                  <p class="gridcols">&hellip; plus columns for <b>Available on</b> (systems), <b>Bands</b>, <b>&pound;/m</b> and a per-choice width price table.</p>
                </div>

                <!-- Scene C: appears-when gating -->
                <div class="osc scC">
                  <p class="exlabel">Add an option &mdash; Motor type</p>
                  <div class="fld"><label>Name</label><div class="boxv">Motor type</div></div>
                  <p class="exlabel" style="margin:.6rem 0 .3rem">Appears when (optional)</p>
                  <div class="awlist">
                    <div class="awrow"><span class="otick"></span> Control type = Cord</div>
                    <div class="awrow on"><span class="otick on"></span> Control type = Motorised</div>
                  </div>
                  <p class="exhelp">Shows in the quote builder when <b>any</b> ticked choice is selected. Tick none = always visible.</p>
                </div>

                <!-- Scene D: number input on a choice -->
                <div class="osc scD">
                  <p class="exlabel">Choice &mdash; Motorised</p>
                  <div class="nfield">
                    <div class="nlegend">Ask for a number on this choice</div>
                    <div class="chkline" style="margin-bottom:.5rem"><span class="tick on">&check;</span> Show a number input when this choice is picked</div>
                    <div class="fld"><label>What to call this field</label><div class="boxv">Cable length (mm)</div></div>
                    <p class="exhelp">The salesperson types a value alongside. Recorded on the quote line &mdash; it doesn&rsquo;t change the price.</p>
                  </div>
                </div>

                <!-- Scene E: quote-builder preview -->
                <div class="osc scE">
                  <p class="exlabel">What the salesperson sees</p>
                  <div class="pvq">
                    <div class="pvqhd">&#128065; Quote builder</div>
                    <div class="pvqbody">
                      <div class="pvqrow"><label>Control type <span class="req">*</span></label><div class="selectbox">Motorised</div></div>
                      <div class="pvqchild">
                        <div class="pvqrow"><label>Motor type</label><div class="selectbox">Tubular</div></div>
                        <div class="pvqrow"><label>Cable length (mm)</label><div class="boxv">3000</div></div>
                      </div>
                      <div class="pvqsum">Options: +&pound;120.00</div>
                    </div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Add an option &mdash; name it, mark it required.</b>
                  <b class="c2"><span class="n">2</span> Add its choices &mdash; set the default.</b>
                  <b class="c3"><span class="n">3</span> Price a choice, right in the grid.</b>
                  <b class="c4"><span class="n">4</span> Make an option appear only when&hellip;</b>
                  <b class="c5"><span class="n">5</span> Capture a measurement alongside.</b>
                  <b class="c6 good"><span class="n">6</span> That&rsquo;s what the salesperson sees.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Options</b> (the code and some tooltips call them &ldquo;extras&rdquo;) are the add-ons a salesperson picks per blind &mdash;
             <em>Control type</em>, <em>Bottom weight</em>, <em>Motor type</em>, <em>Bracket colour</em>. They&rsquo;re separate from a
             product&rsquo;s <b>fabrics</b> (its colour/material choices). Manage them on the product&rsquo;s <b>Edit</b> page &mdash; the
             <b>Options</b> section &mdash; or open <b>Full manage &raquo;</b>.</p>
          <ul class="steps">
            <li><b>Add the option.</b> Give it a <b>Name</b> (e.g. <em>Control type</em>). <b>Required</b> means the customer must pick one
                (on by default). <b>Allow multiple choices</b> turns the picker into <b>tick-boxes</b> so they can pick any combination.
                There&rsquo;s <b>no price on the option itself</b> &mdash; price lives on the choices.</li>
            <li><b>Add its choices.</b> The choices editor is a <b>live grid</b> &mdash; type a label in the bottom row and press <b>Enter</b>,
                or use <b>+ Bulk add</b> (one label per line, e.g. <em>Left</em> then <em>Right</em>). No Save button: each cell saves as you
                Tab or click away. Tick <b>Default</b> for the one that&rsquo;s pre-selected, and <b>Active</b> to show/hide it.</li>
            <li><b>Price a choice.</b> Each choice can stack up to four price modes, all combined: <b>Flat &pound;</b>, <b>Percent %</b>,
                <b>Per metre &pound;/m</b> (measured along Width / Drop / Width+Drop / Perimeter), and <b>Price per unit</b> (&times; a Quantity
                box &mdash; for brackets and fixings). A choice can also have its own <b>width-based price table</b>. Leave them blank for a
                free choice.</li>
            <li><b>Make it conditional.</b> Under <b>Appears when</b>, tick one or more parent choices and the option only shows when
                <b>any</b> of them is picked (e.g. <em>Motor type</em> appears when <em>Control type = Motorised</em>). Tick none = always
                visible. You can also build these as <b>sub-options</b> from inside a choice.</li>
            <li><b>Capture a measurement.</b> Tick <b>&ldquo;Also show a number input&rdquo;</b> (on the option) or <b>&ldquo;Ask for a number
                on this choice&rdquo;</b> and name it (e.g. <em>Cable length (mm)</em>, <em>Top offset (mm)</em>). The salesperson types a value;
                it&rsquo;s recorded on the line for the supplier docs but <b>doesn&rsquo;t change the price</b>.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Scope choices where needed.</b> On a choice&rsquo;s full edit page you can limit it
             to certain <b>systems</b>, <b>bands</b>, or even specific <b>fabrics</b> &mdash; and add a <b>thumbnail image</b> the customer sees
             when they pick it. Leave the scoping blank and the choice shows everywhere.</div></div>
          <div class="oops"><b>Common trips &mdash; and what it says:</b>
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li>Typed text in a price cell &rarr; <code>Must be a number.</code> (also <code>Flat surcharge must be a number.</code> on the full edit page).</li>
               <li>Built a follow-on option but ticked no parent &rarr; <code>Pick at least one parent choice to gate the sub-option.</code></li>
               <li>Left the name empty &rarr; <code>Name is required.</code></li>
             </ul></div>
          <p>Everything flows straight to the <b>quote builder</b>: each option shows as a dropdown (or tick-boxes if multiple), required
             ones get a red <b>*</b>, the picked choice&rsquo;s <b>thumbnail</b> and any <b>measurement box</b> appear, follow-on options
             slide in when their trigger is chosen, and the surcharges land on the line. Use <b>&#128065; Live preview</b> on the product page
             to check it before it goes live.</p>',
        'script'  => [
            ['0:00', 'Add option: Control type, required.',   'Options are the extras your salesperson picks — control type, bottom weight, a motor. Add one: give it a name, like Control type, and mark it required.', 1],
            ['0:09', 'Choices Cord + Motorised; Cord default.', 'Then add its choices — the values to pick from. Type Cord, then Motorised, and tick the one that should be pre-selected as the default.', 2],
            ['0:18', 'Flat £120 on Motorised.',               'Each choice can carry its own price. Put a flat surcharge on Motorised — a hundred and twenty pounds — right there in the grid. Percent and per-metre work too.', 3],
            ['0:27', 'Motor type appears when Motorised.',     'Some options only make sense after another is picked. Add Motor type, and set it to appear only when Control type is Motorised.', 4],
            ['0:36', 'Number box: Cable length (mm).',         'Need a measurement? Turn on a number box — Cable length in millimetres — and the salesperson types it alongside. It is recorded on the line, but doesn\'t change the price.', 5],
            ['0:46', 'Quote builder shows it all.',            'And here is what they see in the quote builder: pick Motorised, and Motor type and the cable-length box appear, with the hundred-and-twenty-pound surcharge added.', 6],
        ],
    ],

    'products-combine' => [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Combining products into one',
        'eyebrow' => 'Products',
        'blurb'   => 'Fold separate sizes (15/25/35mm) into one product with a system for each.',
        'lede'    => 'Imported a family as separate products (e.g. <em>15/25/35mm Venetian</em>)? <b>Combine</b> folds them into one
                      product with a <b>system</b> for each size &mdash; their fabrics and prices come along.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.8rem; margin:0 0 .75rem; }
          .gd .ctable{ margin-top:.8rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.78rem; }
          .gd .ct-head, .gd .ct-row{ display:grid; grid-template-columns:1.3fr 1.2fr .55fr; gap:.5rem; padding:.35rem .55rem; align-items:center; }
          .gd .ct-head{ background:var(--panel); font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .ct-row{ border-top:1px solid var(--line); color:var(--ink); }
          .gd .masterpill{ display:inline-block; padding:.08rem .45rem; border-radius:999px; background:var(--accent-wash); color:var(--accent-ink); font-size:.6rem; font-weight:700; text-transform:uppercase; }
          .gd .sysin{ border:1px solid var(--line); border-radius:6px; padding:.15rem .4rem; background:var(--panel); font-size:.74rem; color:var(--ink); }
          .gd .cmbbtn{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .9rem; font-size:.82rem; font-weight:700; }
          .gd .stage[data-step="3"] .cmbbtn{ transform:scale(.97); filter:brightness(1.12); }
          .gd .ok-cmb{ display:none; margin-top:.85rem; }
          .gd .stage[data-step="3"] .ok-cmb{ display:flex; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / combine</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Combine into one product</div>
                <p class="ldesc2">The first product becomes the master; the rest fold in as systems.</p>
                <div class="fld"><label>Master product name <span class="req">*</span></label>
                  <div class="box f1"><span class="ph">e.g. Metal Venetian</span><span class="val">Metal Venetian</span></div></div>
                <div class="ctable">
                  <div class="ct-head"><span>Product</span><span>Becomes system</span><span>Fabrics</span></div>
                  <div class="ct-row"><span>15mm Venetian</span><span class="masterpill">Master</span><span>42</span></div>
                  <div class="ct-row"><span>25mm Venetian</span><span><span class="sysin">25mm</span></span><span>38</span></div>
                  <div class="ct-row"><span>35mm Venetian</span><span><span class="sysin">35mm</span></span><span>40</span></div>
                </div>
                <div class="cmbbtn">Combine into one product</div>
                <div class="okbanner ok-cmb"><span>&check;</span><div>Combined into &ldquo;Metal Venetian&rdquo; with 3 systems. The others are now empty &amp; deactivated &mdash; delete once checked.</div></div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> First one&rsquo;s the master &mdash; name it.</b>
                  <b class="c2"><span class="n">2</span> The rest become systems &mdash; 25mm, 35mm.</b>
                  <b class="c3 good"><span class="n">3</span> Combined &mdash; delete the empty husks.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Imported a family as separate products (e.g. <em>15/25/35mm Venetian</em>) and want them under one? <b>Combine</b> folds
             them into a single product with a <b>system</b> for each size.</p>
          <ul class="steps">
            <li>On the <b>Products</b> list, <b>tick</b> the ones to combine, then press <b>&ldquo;Combine into product&hellip;&rdquo;</b>.
                The <b>first one you ticked becomes the master</b> (it keeps its group and settings).</li>
            <li>Name the <b>master</b> (e.g. <em>Metal Venetian</em>) and give each other one its <b>system</b> name (15mm, 25mm&hellip;).
                Their fabrics, price tables and settings move across.</li>
            <li>The folded-in products are <b>deactivated</b> &mdash; empty husks, nothing lost. <b>Delete them</b> once you&rsquo;ve
                checked the master looks right.</li>
          </ul>
          <div class="oops"><b>&ldquo;These products are priced differently&hellip; can&rsquo;t be systems of one product&rdquo;?</b> Everything you
             combine must share the <b>same pricing mode</b> (all width&times;drop, or all per-slat, etc.) &mdash; you can&rsquo;t mix a
             per-slat product in with grid ones. Untick the odd one out. (You&rsquo;ll also be asked to give the master and each system a
             name of 1&ndash;150 characters.)</div>
          <p><b>Adding more later:</b> tick the existing master <b>first</b>, then the new single-size product(s), and Combine again
             &mdash; they&rsquo;re appended as extra systems.</p>',
        'script'  => [
            ['0:00', 'Master name fills.',            'Combining sizes into one product — tick them on the products list, then here name the master. The first one becomes it.', 1],
            ['0:07', 'Rows become systems.',          'The others fold in as systems — 25mm, 35mm — carrying their fabrics and prices across.', 2],
            ['0:14', 'Combined; husks deactivated.',  'Combine, and it\'s one product with three systems. The old ones are emptied and switched off — delete them once you\'ve checked.', 3],
        ],
    ],

    'customers-add' => [
        'aud'     => 'admin',
        'section' => 'Customers',
        'title'   => 'Adding & managing customers',
        'eyebrow' => 'Customers',
        'blurb'   => 'Your address book: add a customer, handle the "same name" check, and read their record — recent quotes, colour-coded by status.',
        'lede'    => 'The <b>Customers</b> page is your address book &mdash; everyone you quote lives here. Add one (only the <b>name</b> is
                      required), and the same page becomes their <b>record</b>: their recent quotes, colour-coded by status. Pick them on a
                      <b>New quote</b> and their address fills itself in.',
        'open'    => '/customer-manager/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.78rem; margin:0 0 .6rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="0"] .scList, .gd .stage[data-step="1"] .scList{ display:block; }
          .gd .stage[data-step="2"] .scForm, .gd .stage[data-step="3"] .scForm, .gd .stage[data-step="4"] .scForm{ display:block; }
          .gd .stage[data-step="5"] .scDetail{ display:block; }
          .gd .stage[data-step="6"] .scQuote{ display:block; }

          /* list */
          .gd .chead{ display:flex; align-items:center; gap:.6rem; margin-bottom:.55rem; }
          .gd .chead .h{ font-weight:700; color:var(--ink); font-size:.92rem; }
          .gd .addcust{ margin-left:auto; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.34rem .7rem; font-size:.76rem; font-weight:700; }
          .gd .stage[data-step="1"] .addcust{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .csearch{ display:flex; align-items:center; border:1px solid var(--line); border-radius:8px; padding:.34rem .55rem; font-size:.74rem; color:var(--faint); background:var(--surface); max-width:24rem; margin-bottom:.6rem; }
          .gd .csearch::before{ content:"\1F50D"; margin-right:.4rem; font-size:.72rem; }
          .gd .ctbl{ width:100%; border-collapse:collapse; font-size:.72rem; }
          .gd .ctbl th{ text-align:left; font-size:.6rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .45rem; }
          .gd .ctbl td{ padding:.38rem .45rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ctbl td b{ color:var(--ink); }
          .gd .ctbl a{ color:var(--accent); font-weight:600; }

          /* form */
          .gd .cols3{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:.7rem .8rem; }
          .gd .waline{ display:inline-flex; align-items:center; gap:.4rem; font-size:.72rem; color:var(--soft); margin-top:.35rem; }
          .gd .savec{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .85rem; font-size:.8rem; font-weight:700; transition:transform .1s, filter .1s; }
          .gd .stage[data-step="2"] .savec, .gd .stage[data-step="3"] .savec{ } /* Save customer */
          .gd .savelbl2{ display:none; }
          .gd .stage[data-step="4"] .savelbl{ display:none; }
          .gd .stage[data-step="4"] .savelbl2{ display:inline; }
          .gd .stage[data-step="4"] .savec{ background:#b45309; }
          .gd .dupwarn{ display:none; margin:.2rem 0 .6rem; border:1px solid #e6b64c; background:rgba(230,182,76,.14); border-radius:9px; padding:.5rem .65rem; font-size:.72rem; color:var(--soft); }
          .gd .stage[data-step="4"] .dupwarn{ display:block; }
          .gd .dupwarn b{ color:var(--ink); }

          /* detail / recent quotes */
          .gd .rqh{ font-size:.72rem; font-weight:700; color:var(--faint); text-transform:uppercase; letter-spacing:.03em; margin:.2rem 0 .45rem; }
          .gd .bdg{ display:inline-block; font-size:.58rem; font-weight:700; border-radius:20px; padding:.08rem .5rem; text-transform:capitalize; }
          .gd .bdg.draft{ background:var(--line); color:var(--soft); }
          .gd .bdg.sent{ background:#dbeafe; color:#1e40af; }
          .gd .bdg.accepted{ background:#dcfce7; color:#166534; }
          .gd .bdg.ordered{ background:#fef3c7; color:#92400e; }

          .gd .t-wa{ background:var(--accent); border-color:var(--accent); color:#fff; }
          .gd .boxv{ border:1px solid var(--line); border-radius:7px; background:var(--panel); color:var(--ink); }

          /* quote picker */
          .gd .qnote{ font-size:.7rem; color:var(--faint); margin-top:.5rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / customers</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a class="on">Customers</a><a>Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: list -->
                <div class="osc scList">
                  <div class="chead"><span class="h">Customers</span><span class="addcust">+ Add customer</span></div>
                  <div class="csearch">Search by name, email, phone, town or postcode&hellip;</div>
                  <table class="ctbl">
                    <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Town</th><th>Postcode</th><th>Quotes</th><th></th></tr></thead>
                    <tbody>
                      <tr><td><b>Angela Reed</b></td><td>angela@&hellip;</td><td>07700 900112</td><td>Kenilworth</td><td>CV8 1AA</td><td>3</td><td><a>Edit</a></td></tr>
                      <tr><td><b>David Cole</b></td><td>d.cole@&hellip;</td><td>07700 900431</td><td>Coventry</td><td>CV5 6RT</td><td>1</td><td><a>Edit</a></td></tr>
                    </tbody>
                  </table>
                </div>

                <!-- Scene: add form -->
                <div class="osc scForm">
                  <div class="card-t">Add customer</div>
                  <div class="fld"><label>Name <span style="color:#b91c1c">*</span></label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">Emma Fletcher</span></div></div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld"><label>Email</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">emma.fletcher@gmail.com</span></div></div>
                    <div class="fld"><label>Phone</label><div class="box f2"><span class="ph">&nbsp;</span><span class="val">07700 900318</span></div>
                      <span class="waline"><span class="tick t-wa">&check;</span> Customer has WhatsApp on this number</span></div>
                  </div>
                  <div class="dupwarn"><b>A customer with this name already exists.</b> Pick the existing customer below, or &mdash; if this really is a different person with the same name &mdash; click <b>Save anyway</b>.</div>
                  <div class="fld" style="margin-top:.5rem"><label>Address line 1</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">14 Willow Drive</span></div></div>
                  <div class="cols3" style="margin-top:.5rem">
                    <div class="fld"><label>Town</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">Leamington Spa</span></div></div>
                    <div class="fld"><label>County</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">Warwickshire</span></div></div>
                    <div class="fld"><label>Postcode</label><div class="box f3"><span class="ph">&nbsp;</span><span class="val">CV32 5PJ</span></div></div>
                  </div>
                  <div class="fld" style="margin-top:.5rem"><label>Notes</label><div class="ta f3"><span class="ph">&nbsp;</span><span class="val">Prefers afternoon fittings. Dog in the garden.</span></div></div>
                  <div class="savec"><span class="savelbl">Save customer</span><span class="savelbl2">Save anyway (it really is a different person)</span></div>
                </div>

                <!-- Scene: detail / recent quotes -->
                <div class="osc scDetail">
                  <div class="card-t">Emma Fletcher</div>
                  <p class="ldesc2">&larr; Back to customers &nbsp;&middot;&nbsp; this page is also her record.</p>
                  <div class="rqh">Recent quotes</div>
                  <table class="ctbl">
                    <thead><tr><th>Quote #</th><th>Status</th><th>Total</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                      <tr><td><b>Q-1042</b></td><td><span class="bdg accepted">accepted</span></td><td>&pound;486.00</td><td>2 Aug 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>Q-1039</b></td><td><span class="bdg sent">sent</span></td><td>&pound;302.50</td><td>28 Jul 2026</td><td><a>Open</a></td></tr>
                      <tr><td><b>Q-1031</b></td><td><span class="bdg ordered">ordered</span></td><td>&pound;915.00</td><td>19 Jul 2026</td><td><a>Open</a></td></tr>
                    </tbody>
                  </table>
                </div>

                <!-- Scene: new quote picker -->
                <div class="osc scQuote">
                  <div class="card-t">New quote</div>
                  <div class="fld"><label>Existing customer</label><div class="selectbox">Emma Fletcher &mdash; Leamington Spa &middot; CV32 5PJ</div></div>
                  <div class="fld" style="margin-top:.5rem"><label>Address (auto-filled)</label><div class="boxv" style="height:auto;padding:.4rem .5rem;line-height:1.4">14 Willow Drive, Leamington Spa, Warwickshire, CV32 5PJ</div></div>
                  <p class="qnote">Picking a customer fills their address in for you &mdash; no re-typing.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Customers &mdash; your address book. + Add customer.</b>
                  <b class="c2"><span class="n">2</span> Name is the only must &mdash; add email, phone, WhatsApp.</b>
                  <b class="c3"><span class="n">3</span> Address for the fitting + any notes (optional).</b>
                  <b class="c4"><span class="n">4</span> Same name exists? Pick them, or Save anyway.</b>
                  <b class="c5"><span class="n">5</span> Their record: recent quotes, colour-coded.</b>
                  <b class="c6 good"><span class="n">6</span> New quote &rarr; pick them &rarr; address auto-fills.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>Customers</b> page (in the sidebar) is your address book &mdash; every end-customer you quote. It opens as a searchable
             <b>list</b>; up top are <b>+ Add customer</b> and (for admins) <b>Find duplicates</b>.</p>
          <ul class="steps">
            <li><b>Find someone fast.</b> The search box matches <b>name, email, phone, town or postcode</b> &mdash; type any part and hit
                Search. The list shows Name, Email, Phone, Town, Postcode and their <b>Quotes</b> count; <b>Edit</b> opens the record.</li>
            <li><b>Add a customer.</b> Only <b>Name</b> is required (the red *). Add <b>Email</b> and <b>Phone</b> &mdash; tick
                <b>&ldquo;Customer has WhatsApp on this number&rdquo;</b> if it does &mdash; then the <b>address</b> (line 1 &amp; 2, Town,
                County, Postcode) and any <b>Notes</b>. There&rsquo;s <b>one address per customer</b> (no separate billing/site address here),
                and everything except the name is optional &mdash; but the more you add now, the less you re-type on every quote. Click
                <b>Save customer</b>.</li>
            <li><b>The &ldquo;same name&rdquo; check.</b> If a customer with that name already exists you&rsquo;ll see
                <b>&ldquo;A customer with this name already exists&rdquo;</b> with the matches listed &mdash; so you don&rsquo;t create a
                duplicate by accident. Either <b>pick the existing person</b>, or, if it genuinely is a different customer who happens to share
                the name, the button becomes <b>&ldquo;Save anyway (it really is a different person)&rdquo;</b>. (The check is by <em>name</em>
                only &mdash; two people can share a phone or email.)</li>
            <li><b>The record.</b> Saving opens that customer&rsquo;s page &mdash; the <b>same screen you edit on</b>. Below the details is
                <b>Recent quotes</b> (their last five): Quote #, <b>Status</b>, Total, Created and <b>Open</b>. The status is a
                <b>colour-coded badge</b> &mdash; the very colours you set in <em>Settings &rarr; Status colours</em> (draft, sent, accepted,
                ordered&hellip;) &mdash; so you can see at a glance where each job stands.</li>
          </ul>
          <p><b>Starting a quote for them:</b> you don&rsquo;t do it from here &mdash; open <b>New quote</b> and, in the <b>Existing customer</b>
             box, start typing their name (or town/postcode). Pick them and <b>their address fills in automatically</b>. Brand-new customer?
             Just type the name straight into that box and flesh out the rest later.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Deleting a customer is permanent</b> &mdash; you&rsquo;ll be asked
             &ldquo;Delete &lt;name&gt;? This cannot be undone.&rdquo; Their <b>existing quotes are kept</b> but are <b>no longer linked</b> to a
             customer record, so only delete a genuine mistake, not someone with history. Two records for the same person? Admins can use
             <b>Find duplicates</b> to merge them &mdash; the oldest is kept and all quotes/appointments re-point to it.</div></div>
          <p><b>Heads-up on emails:</b> if you type an email it must be valid &mdash; a malformed one gives
             <em>&ldquo;Please enter a valid email address.&rdquo;</em> Leave it blank if you don&rsquo;t have one. (There&rsquo;s no bulk
             customer import &mdash; customers are added here or created inline on a quote.)</p>',
        'script'  => [
            ['0:00', 'Customers list; + Add customer.',   'Customers is your address book — everyone you quote lives here. Hit Add customer to start a new one, or search to find an existing one.', 1],
            ['0:08', 'Name, email, phone, WhatsApp.',      'Only the name is required. Add their email and phone — tick WhatsApp if that number takes it — so you can reach them later.', 2],
            ['0:16', 'Address and notes filled in.',       'Then their address for the fitting, and any notes. None of it is compulsory, but the more you put in now, the less you type on every quote.', 3],
            ['0:25', 'Same-name warning; Save anyway.',    'Save. If the name already exists it stops you — pick the existing person, or, if it really is a different customer with the same name, click Save anyway.', 4],
            ['0:35', 'Their record; recent quotes.',       'Saved. This same page is their record — their recent quotes, colour-coded by status, so you can see at a glance what is drafted, sent, accepted or ordered.', 5],
            ['0:45', 'New quote auto-fills address.',      'And that is the payoff: when you start a New quote, pick them from the customer box and their address fills itself in.', 6],
        ],
    ],

    'quote-build' => [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Building a quote',
        'eyebrow' => 'Quotes',
        'blurb'   => 'The quote builder end to end: add a blind (product → system → fabric → size → options), watch the live price, read the totals, and send it.',
        'lede'    => 'The <b>quote builder</b> is where a sale is built &mdash; one blind at a time. Pick the <b>product</b>, <b>fabric</b> and
                      <b>size</b>, watch the <b>live price</b>, add any <b>options</b>, and it lands on the quote with running <b>totals</b>.
                      Then <b>send it</b> and move it down the line. Here&rsquo;s the whole thing, including the mistake everyone hits once.',
        'open'    => '/quote-builder/new.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scStart{ display:block; }
          .gd .stage[data-step="2"] .scAdd, .gd .stage[data-step="3"] .scAdd, .gd .stage[data-step="4"] .scAdd,
          .gd .stage[data-step="5"] .scAdd, .gd .stage[data-step="6"] .scAdd{ display:block; }
          .gd .stage[data-step="7"] .scList{ display:block; }
          .gd .stage[data-step="8"] .scSend{ display:block; }

          /* sticky quote bar (all steps) */
          .gd .qbar{ display:flex; align-items:center; gap:.5rem; background:var(--nav); color:#fff; border-radius:8px; padding:.42rem .65rem; font-size:.74rem; margin-bottom:.7rem; }
          .gd .qbar .qn{ font-weight:700; }
          .gd .qpill{ font-size:.58rem; font-weight:700; border-radius:20px; padding:.06rem .5rem; background:rgba(255,255,255,.22); text-transform:capitalize; }
          .gd .qpill.acc{ display:none; background:#16a34a; }
          .gd .stage[data-step="8"] .qpill.draft{ display:none; }
          .gd .stage[data-step="8"] .qpill.acc{ display:inline; }
          .gd .qbar .qtot{ margin-left:auto; font-weight:700; }
          .gd .qtot .t1{ display:none; }
          .gd .stage[data-step="7"] .qtot .t0, .gd .stage[data-step="8"] .qtot .t0{ display:none; }
          .gd .stage[data-step="7"] .qtot .t1, .gd .stage[data-step="8"] .qtot .t1{ display:inline; }

          /* system slip swap */
          .gd .sys-cas{ display:none; }
          .gd .stage[data-step="4"] .sys-std{ display:none; }
          .gd .stage[data-step="4"] .sys-cas{ display:inline; }
          .gd .stage[data-step="4"] .sysbox{ box-shadow:0 0 0 2px #ef4444; }

          /* live-preview box */
          .gd .prev{ margin-top:.7rem; border-radius:8px; padding:.5rem .65rem; font-size:.73rem; max-width:24rem; }
          .gd .prev.idle{ background:var(--panel); color:var(--faint); font-style:italic; }
          .gd .prev.err{ background:#fee2e2; color:#991b1b; }
          .gd .prev.ok{ background:#d1fae5; color:#065f46; }
          .gd .prev.ok b{ color:#065f46; }
          .gd .pv{ display:none; }
          .gd .stage[data-step="2"] .pv-i1{ display:block; }
          .gd .stage[data-step="3"] .pv-i2{ display:block; }
          .gd .stage[data-step="4"] .pv-err{ display:block; }
          .gd .stage[data-step="5"] .pv-ok1{ display:block; }
          .gd .stage[data-step="6"] .pv-ok2{ display:block; }

          /* options (step 6) */
          .gd .optwrap{ display:none; margin-top:.6rem; }
          .gd .stage[data-step="6"] .optwrap{ display:block; }
          .gd .optrow{ display:flex; align-items:center; gap:.5rem; font-size:.74rem; }
          .gd .optrow label{ min-width:5.5rem; color:var(--faint); font-size:.64rem; text-transform:uppercase; letter-spacing:.03em; }
          .gd .plus{ color:#065f46; font-weight:600; }

          /* save bar */
          .gd .savebar{ margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap; }
          .gd .qbtn{ display:inline-flex; align-items:center; gap:.3rem; border-radius:8px; padding:.4rem .8rem; font-size:.76rem; font-weight:600; }
          .gd .qsave{ background:var(--line); color:var(--faint); }
          .gd .stage[data-step="6"] .qsave{ background:var(--accent); color:#fff; }
          .gd .qbtn.ghost{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }

          /* blinds list + totals */
          .gd .btl{ width:100%; border-collapse:collapse; font-size:.7rem; }
          .gd .btl th{ text-align:left; font-size:.58rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.3rem .4rem; }
          .gd .btl td{ padding:.4rem .4rem; border-bottom:1px solid var(--line); color:var(--ink); vertical-align:top; }
          .gd .btl td b{ color:var(--ink); }
          .gd .tot{ margin:.7rem 0 0 auto; max-width:20rem; font-size:.74rem; }
          .gd .tot .r{ display:flex; justify-content:space-between; padding:.2rem 0; color:var(--soft); }
          .gd .tot .r.wt{ color:#7c3aed; }
          .gd .tot .r.grand{ font-weight:700; color:var(--ink); border-top:1px solid var(--line); margin-top:.2rem; padding-top:.32rem; }
          .gd .tot .r.dep{ color:var(--faint); font-style:italic; }

          /* send actions */
          .gd .actlist{ display:flex; flex-direction:column; gap:.42rem; max-width:20rem; }
          .gd .actbtn{ display:inline-flex; align-items:center; gap:.45rem; border:1px solid var(--line); border-radius:8px; padding:.42rem .65rem; font-size:.74rem; color:var(--ink); background:var(--surface); }
          .gd .actbtn.acc{ background:#16a34a; color:#fff; border-color:#16a34a; }
          .gd .flow{ font-size:.7rem; color:var(--faint); margin-top:.6rem; }
          .gd .flow b{ color:var(--ink); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote-builder</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Quotes</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- persistent quote bar -->
                <div class="qbar">
                  <span class="qn">Quote PRE-2026-0042</span>
                  <span class="qpill draft">Draft</span><span class="qpill acc">Accepted</span>
                  <span class="qtot">Total <span class="t0">&pound;0.00</span><span class="t1">&pound;66.00</span></span>
                </div>

                <!-- Scene: start -->
                <div class="osc scStart">
                  <div class="card-t">New quote</div>
                  <div class="fld"><label>Existing customer</label><div class="selectbox">Emma Fletcher &mdash; Leamington Spa &middot; CV32 5PJ</div></div>
                  <p class="ldesc2" style="margin-top:.5rem">Their details fill in below. New customer? Just type the name.</p>
                  <div class="savebar"><span class="qbtn qsave" style="background:var(--accent);color:#fff">Create quote</span></div>
                </div>

                <!-- Scene: add a blind (the cascade) -->
                <div class="osc scAdd">
                  <div class="card-t">Add a blind</div>
                  <div class="frow">
                    <div class="fld"><label>Product</label><div class="selectbox">Roller Blind</div></div>
                    <div class="fld"><label>System</label><div class="selectbox sysbox"><span class="sys-std">Standard</span><span class="sys-cas">Cassette</span></div></div>
                  </div>
                  <div class="frow" style="margin-top:.5rem">
                    <div class="fld"><label>Band</label><div class="selectbox">All bands</div></div>
                    <div class="fld"><label>Fabric</label><div class="box f3"><span class="ph">Type to search fabrics&hellip;</span><span class="val">Sunset White &middot; Band A</span></div></div>
                  </div>
                  <div class="cols3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem;margin-top:.5rem">
                    <div class="fld"><label>Width (mm)</label><div class="box f5"><span class="ph">&nbsp;</span><span class="val">1200</span></div></div>
                    <div class="fld"><label>Drop (mm)</label><div class="box f5"><span class="ph">&nbsp;</span><span class="val">1500</span></div></div>
                    <div class="fld"><label>Qty</label><div class="boxv" style="height:30px;display:flex;align-items:center;padding:0 .5rem;border:1px solid var(--line);border-radius:7px;background:var(--panel);font-size:.8rem">1</div></div>
                  </div>

                  <!-- options (step 6) -->
                  <div class="optwrap">
                    <div class="optrow"><label>Bottom weight</label><span class="selectbox" style="min-width:8rem">Chained</span><span class="plus">+&pound;5.00</span></div>
                  </div>

                  <!-- live preview -->
                  <div class="prev idle pv pv-i1">Still need: fabric, width, drop.</div>
                  <div class="prev idle pv pv-i2">Still need: width, drop.</div>
                  <div class="prev err pv pv-err">&#9888; No price table for Roller Blind band A on system &lsquo;Cassette&rsquo;.</div>
                  <div class="prev ok pv pv-ok1"><b>&pound;45.00</b> per blind &middot; base &pound;45.00</div>
                  <div class="prev ok pv pv-ok2"><b>&pound;50.00</b> per blind &middot; base &pound;45.00 &middot; + extras &pound;5.00</div>

                  <div class="savebar">
                    <span class="qbtn qsave">Save</span>
                    <span class="qbtn ghost">Save and add another blind</span>
                  </div>
                </div>

                <!-- Scene: blinds list + totals -->
                <div class="osc scList">
                  <div class="card-t">Blinds (1)</div>
                  <table class="btl">
                    <thead><tr><th>#</th><th>Description</th><th>Size</th><th>Qty</th><th>Total</th></tr></thead>
                    <tbody>
                      <tr><td>1</td><td><b>Living Room</b><br>Roller Blind &mdash; Standard<br>Band A &mdash; Sunset White<br><span style="color:var(--soft)">+ Bottom weight: Chained (&pound;5.00)</span></td><td>1200 &times; 1500</td><td>1</td><td>&pound;50.00</td></tr>
                    </tbody>
                  </table>
                  <div class="tot">
                    <div class="r wt"><span>WT (internal &mdash; never shown to the customer)</span><span>&pound;5.00</span></div>
                    <div class="r"><span>Subtotal</span><span>&pound;55.00</span></div>
                    <div class="r"><span>VAT (20.00%)</span><span>&pound;11.00</span></div>
                    <div class="r grand"><span>Total</span><span>&pound;66.00</span></div>
                    <div class="r dep"><span>Deposit due on acceptance</span><span>&pound;33.00</span></div>
                  </div>
                </div>

                <!-- Scene: send + status -->
                <div class="osc scSend">
                  <div class="card-t">Send &amp; status</div>
                  <div class="actlist">
                    <span class="actbtn acc">&check; Customer accepted</span>
                    <span class="actbtn">&#128231; Email PDF + accept link</span>
                    <span class="actbtn">&#128172; Send via WhatsApp</span>
                    <span class="actbtn">&#128279; Copy public link</span>
                  </div>
                  <p class="flow">Status moves along: <b>draft &rarr; sent &rarr; accepted &rarr; ordered &rarr; fitted &rarr; invoiced &rarr; paid</b>. Only a <b>draft</b> can be edited.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Start from New quote &rarr; pick the customer &rarr; Create.</b>
                  <b class="c2"><span class="n">2</span> Choose the product; its system fills in.</b>
                  <b class="c3"><span class="n">3</span> Pick the fabric &mdash; search, or filter by band.</b>
                  <b class="c4 err"><span class="n">4</span> No prices for that band + system? Save is blocked.</b>
                  <b class="c5"><span class="n">5</span> Priced system + size &rarr; the price goes green.</b>
                  <b class="c6"><span class="n">6</span> Add options, then Save the blind.</b>
                  <b class="c7"><span class="n">7</span> Totals: subtotal, VAT, WT (internal), deposit.</b>
                  <b class="c8 good"><span class="n">8</span> Send it, and mark it accepted.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>quote builder</b> is the heart of the app. Start it from <b>+ New quote</b>, pick the customer (their address fills in),
             and <b>Create quote</b> &mdash; you land in the builder on a fresh <b>draft</b>. Now add blinds one at a time.</p>
          <ul class="steps">
            <li><b>The cascade.</b> Pick a <b>Product</b> and its <b>System</b> fills in (a variant &mdash; Standard, Motorised&hellip;). Pick the
                <b>Fabric</b> &mdash; type to search by name, colour or code, or narrow it with the <b>Band</b> filter first. Give it a
                <b>Room</b> name, then the <b>Width</b> and <b>Drop</b> (type 1500, or 150cm, or 60in &mdash; the <b>unit</b> selector re-shows
                every size), a <b>Qty</b>, and any <b>Options</b>.</li>
            <li><b>Watch the live price.</b> As you fill it in, the price box updates: grey (<em>&ldquo;Still need: &hellip;&rdquo;</em>) until it
                has enough, then <b>green</b> with the breakdown &mdash; <em>&pound;X per blind &middot; base &middot; + extras</em> (cost-viewers
                also see the markup &amp; discount). <b>Save stays disabled until the price is valid</b>, so you can&rsquo;t save a broken line.</li>
            <li><b>Save.</b> <b>Save</b> drops the blind onto the quote (right-hand list); <b>Save and add another blind</b> keeps the form open
                for the next one. Each blind can carry its own <b>per-blind price tweak</b> (a discount or markup override, cost-viewers only) &mdash;
                it only affects that one line.</li>
          </ul>
          <div class="oops"><b>&ldquo;No price table for &hellip; band A on system &lsquo;Cassette&rsquo;.&rdquo;</b> The single most common slip:
             the fabric&rsquo;s <b>band has no price list on the system you picked</b>. The Band filter is just a filter &mdash; it doesn&rsquo;t
             guarantee a price. <b>Fix:</b> switch to a <b>System</b> that&rsquo;s priced for that band (the Band list re-scopes to it), or pick a
             band that has a price table, then reselect the fabric. Save unlocks once the price turns green.</div>
          <div class="oops"><b>&ldquo;Size 2400 &times; 3000 mm exceeds the largest cell in this price table.&rdquo;</b> The size is bigger than the
             grid goes. <b>Fix:</b> double-check the <b>measurement unit</b> (a value typed as mm while the quote is in cm reads ten times too
             big!), and confirm the price list actually covers that size.</div>
          <ul class="steps">
            <li><b>The list &amp; totals.</b> Each blind shows its room, product/system, fabric, size, qty and line total, with <b>Edit</b>,
                <b>Dup</b> (duplicate &mdash; handy for the same blind in another size) and <b>&times;</b> (remove). Totals stack up on the right:
                <b>Subtotal</b>, <b>VAT</b>, <b>Total</b>, and the <b>Deposit due on acceptance</b>. The purple <b>WT</b> line is your internal
                <b>Wally tax</b> &mdash; a hassle surcharge folded into the price; the customer <b>never</b> sees it as a line.</li>
            <li><b>Send it.</b> <b>Email PDF + accept link</b> sends the customer a PDF with a one-click accept button; or <b>Send via WhatsApp</b>
                (when their number is flagged for it) or <b>Copy public link</b>. You can also <b>View / Download PDF</b> any time.</li>
            <li><b>Move it along.</b> When they say yes, hit <b>&check; Customer accepted</b> (or Declined). Status runs
                <b>draft &rarr; sent &rarr; accepted &rarr; ordered &rarr; fitted &rarr; invoiced &rarr; paid</b>. Accepting seeds the <b>deposit</b>
                and drops a <b>Pending Fitting</b> into the calendar; <b>Send to suppliers</b> and <b>Send invoice</b> live in the Quote actions panel.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>There&rsquo;s no big &ldquo;Save quote&rdquo; button &mdash; every panel saves
             itself.</b> Adding a blind, editing the customer, setting the WT or deposit each save on the spot, and the totals recompute. And
             <b>only a draft is editable</b> &mdash; once it&rsquo;s sent/accepted it&rsquo;s locked (<em>&ldquo;Quote is locked&hellip; Reopen it
             to add blinds&rdquo;</em>); use <b>Reopen as draft</b> if you need to change it.</div></div>',
        'script'  => [
            ['0:00', 'New quote; pick the customer.',      'A quote starts from New quote. Pick the customer — their address fills in — and Create quote. You land in the builder.', 1],
            ['0:08', 'Product chosen; system fills in.',   'Now build it a blind at a time. Choose the product, and its system fills in — here, a standard roller.', 2],
            ['0:15', 'Fabric picked.',                     'Pick the fabric — type to search, or filter by band first. This one is Sunset White, band A.', 3],
            ['0:22', 'Red: no price for band + system.',   'Keep an eye on the live price. If that band has no prices on the system you picked, it says so — no price table for band A on Cassette — and won\'t let you save.', 4],
            ['0:32', 'Fixed system + size; price green.',  'Switch to a system that is priced — Standard — then type the width and drop. The price turns green: forty-five pounds a blind.', 5],
            ['0:41', 'Option added; Save.',                'Add any options — a chained bottom weight adds five pounds — then Save. That is one blind on the quote.', 6],
            ['0:49', 'Blinds list and totals.',           'It lands on the right with the running totals: subtotal, VAT, grand total. WT is your internal Wally-tax line, never shown to the customer — and it works out the deposit due.', 7],
            ['1:00', 'Send it; mark accepted.',            'Then send it — email the PDF with an accept link, or WhatsApp. When they say yes, mark it accepted, and it moves along: sent, accepted, ordered.', 8],
        ],
    ],

    'quote-send-accept' => [
        'aud'     => 'admin',
        'section' => 'Quotes',
        'title'   => 'Sending & accepting',
        'eyebrow' => 'Quotes',
        'blurb'   => 'Send the quote (email, WhatsApp or link), what the customer sees on the public accept page, and what their Yes triggers on your side.',
        'lede'    => 'Once a quote is built, get it to the customer &mdash; <b>email the PDF with a one-click accept link</b>, WhatsApp it, or copy
                      the link. They open it with <b>no login</b>, read it, and <b>Accept</b>. Here&rsquo;s both sides: what you send, what they
                      see, and what their <em>Yes</em> sets off for you.',
        'open'    => '/orders/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.76rem; margin:0 0 .55rem; }
          .gd .osc{ display:none; }
          .gd .stage[data-step="1"] .scSend, .gd .stage[data-step="2"] .scSend{ display:block; }
          .gd .stage[data-step="3"] .scEmail{ display:block; }
          .gd .stage[data-step="4"] .scPublic, .gd .stage[data-step="5"] .scPublic{ display:block; }
          .gd .stage[data-step="6"] .scYours{ display:block; }

          /* send section */
          .gd .em-e{ display:none; color:var(--faint); }
          .gd .stage[data-step="2"] .em-v{ display:none; }
          .gd .stage[data-step="2"] .em-e{ display:inline; }
          .gd .sbtns{ display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.7rem; }
          .gd .sbtn{ display:inline-flex; align-items:center; gap:.35rem; border-radius:8px; padding:.42rem .75rem; font-size:.75rem; font-weight:600; }
          .gd .sbtn.email{ background:var(--accent); color:#fff; }
          .gd .sbtn.wa{ background:#25d366; color:#053b1b; }
          .gd .sbtn.copy{ background:var(--surface); border:1px solid var(--line); color:var(--soft); }
          .gd .stage[data-step="2"] .sbtn.email{ background:var(--line); color:var(--faint); }
          .gd .stage[data-step="2"] .sbtn.wa, .gd .stage[data-step="2"] .sbtn.copy{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .plink{ margin-top:.55rem; font-size:.64rem; color:var(--faint); }
          .gd .plink code{ background:var(--panel); border-radius:5px; padding:.05rem .3rem; }
          .gd .sliperr{ display:none; margin-top:.6rem; }
          .gd .stage[data-step="2"] .sliperr{ display:flex; }

          /* email card */
          .gd .emailcard{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:25rem; }
          .gd .ehead{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); font-size:.72rem; }
          .gd .ehead .subj{ font-weight:700; color:var(--ink); }
          .gd .ehead .frm{ color:var(--faint); }
          .gd .ebody{ padding:.6rem .7rem; font-size:.74rem; color:var(--soft); line-height:1.5; }
          .gd .eaccept{ display:inline-flex; margin:.35rem 0; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.74rem; font-weight:600; }
          .gd .eatt{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--line); border-radius:6px; padding:.25rem .5rem; font-size:.68rem; color:var(--soft); background:var(--surface); margin-top:.3rem; }

          /* public page */
          .gd .pubpage{ border:1px solid var(--line); border-radius:10px; overflow:hidden; max-width:26rem; }
          .gd .pbrand{ background:var(--panel); padding:.5rem .7rem; border-bottom:1px solid var(--line); }
          .gd .pbrand .co{ font-weight:700; color:var(--ink); }
          .gd .pbrand .ad{ font-size:.62rem; color:var(--faint); }
          .gd .pbody{ padding:.6rem .7rem; }
          .gd .pmeta{ font-size:.66rem; color:var(--soft); margin-bottom:.5rem; }
          .gd .pmeta b{ color:var(--ink); }
          .gd .ptbl{ width:100%; border-collapse:collapse; font-size:.66rem; }
          .gd .ptbl th{ text-align:left; font-size:.56rem; text-transform:uppercase; letter-spacing:.03em; color:var(--faint); font-weight:700; border-bottom:1px solid var(--line); padding:.25rem .35rem; }
          .gd .ptbl td{ padding:.3rem .35rem; border-bottom:1px solid var(--line); color:var(--ink); }
          .gd .ptbl .r{ text-align:right; }
          .gd .ptot{ text-align:right; font-weight:700; color:var(--ink); font-size:.74rem; margin-top:.35rem; }
          .gd .pdep{ margin-top:.5rem; background:#fef9c3; color:#854d0e; border-radius:7px; padding:.4rem .6rem; font-size:.68rem; }
          [data-theme="dark"] .gd .pdep{ background:#3a2f05; color:#fde68a; }
          .gd .acceptbox{ display:none; margin-top:.55rem; border:1px solid var(--line); border-radius:9px; padding:.55rem .7rem; background:var(--surface); }
          .gd .stage[data-step="4"] .acceptbox{ display:block; }
          .gd .acch{ font-weight:700; color:var(--ink); font-size:.76rem; margin-bottom:.2rem; }
          .gd .accsub{ font-size:.66rem; color:var(--faint); margin-bottom:.45rem; }
          .gd .agree{ display:inline-flex; align-items:center; gap:.4rem; font-size:.68rem; color:var(--soft); margin:.4rem 0; }
          .gd .accbtns{ display:flex; gap:.5rem; margin-top:.2rem; }
          .gd .accbtn{ display:inline-flex; background:#16a34a; color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.75rem; font-weight:700; }
          .gd .decbtn{ display:inline-flex; background:var(--surface); border:1px solid var(--line); color:var(--soft); border-radius:7px; padding:.34rem .7rem; font-size:.75rem; }
          .gd .acceptedcard{ display:none; margin-top:.55rem; background:#d1fae5; color:#065f46; border-radius:9px; padding:.6rem .7rem; font-size:.74rem; }
          .gd .stage[data-step="5"] .acceptedcard{ display:block; }
          .gd .acceptedcard b{ color:#065f46; }

          /* your side */
          .gd .bdg{ display:inline-block; font-size:.6rem; font-weight:700; border-radius:20px; padding:.08rem .55rem; background:#dcfce7; color:#166534; }
          .gd .yrow{ display:flex; align-items:center; gap:.5rem; font-size:.78rem; color:var(--ink); margin-bottom:.5rem; }
          .gd .traycard{ border:1px dashed var(--line); border-radius:9px; padding:.5rem .7rem; background:var(--panel); font-size:.72rem; color:var(--soft); margin-bottom:.45rem; }
          .gd .traycard .tt{ font-weight:600; color:var(--ink); }
          .gd .ynote{ font-size:.68rem; color:var(--faint); }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quote</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Quotes</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- Scene: send to customer -->
                <div class="osc scSend">
                  <div class="card-t">Send to customer</div>
                  <p class="ldesc2">Email the PDF and a link the customer can click to accept the quote online.</p>
                  <div class="fld"><label>Recipient email</label><div class="box"><span class="em-v">emma.fletcher@gmail.com</span><span class="em-e">&mdash; no email on file &mdash;</span></div></div>
                  <div class="fld" style="margin-top:.5rem"><label>Message (optional)</label><div class="ta"><span class="ph">Optional &mdash; anything to add above the standard text.</span></div></div>
                  <div class="errbanner sliperr"><span>&#9888;</span><div>Please provide a valid recipient email address. &mdash; no email? <b>Copy the link</b> or <b>WhatsApp</b> it instead.</div></div>
                  <div class="sbtns">
                    <span class="sbtn email">&#128231; Email PDF + accept link</span>
                    <span class="sbtn wa">&#128172; Send via WhatsApp</span>
                    <span class="sbtn copy">&#128279; Copy public link</span>
                  </div>
                  <p class="plink">Public link: <code>yourblinds.uk/quote-history/public.php?token=&hellip;</code></p>
                </div>

                <!-- Scene: the email the customer receives -->
                <div class="osc scEmail">
                  <div class="card-t">What lands in their inbox</div>
                  <div class="emailcard">
                    <div class="ehead">
                      <div class="subj">Your quote PRE-2026-0042 from Beverley Blinds</div>
                      <div class="frm">from Beverley Blinds &middot; to emma.fletcher@gmail.com</div>
                    </div>
                    <div class="ebody">
                      Hello Emma,<br>
                      Please find your quote (PRE-2026-0042) attached as a PDF.<br><br>
                      You can also view it online and accept it here:<br>
                      <span class="eaccept">View &amp; accept &rarr;</span><br>
                      <span class="eatt">&#128206; PRE-2026-0042.pdf</span><br><br>
                      Kind regards,<br>Beverley Blinds
                    </div>
                  </div>
                </div>

                <!-- Scene: the public quote page (accept box → accepted) -->
                <div class="osc scPublic">
                  <div class="pubpage">
                    <div class="pbrand"><div class="co">Beverley Blinds</div><div class="ad">Kenilworth &middot; 02476 644 684</div></div>
                    <div class="pbody">
                      <div class="pmeta"><b>Quote PRE-2026-0042</b> &middot; Date 31 Aug 2026 &middot; Status: Sent<br>Quote for: Emma Fletcher &mdash; Leamington Spa</div>
                      <table class="ptbl">
                        <thead><tr><th>#</th><th>Description</th><th>Qty</th><th class="r">Total</th></tr></thead>
                        <tbody>
                          <tr><td>1</td><td>Roller Blind &mdash; Sunset White (Living Room)</td><td>1</td><td class="r">&pound;50.00</td></tr>
                          <tr><td>2</td><td>Roller Blind &mdash; Storm Grey (Kitchen)</td><td>1</td><td class="r">&pound;16.00</td></tr>
                        </tbody>
                      </table>
                      <div class="ptot">Total &pound;66.00</div>
                      <div class="pdep">Deposit on acceptance: <b>&pound;33.00</b>. The balance will be due on completion.</div>

                      <div class="acceptbox">
                        <div class="acch">Accept this quote</div>
                        <div class="accsub">Type your full name to confirm acceptance. We&rsquo;ll record it as your digital sign-off and let Beverley Blinds know.</div>
                        <div class="fld"><label>Your full name</label><div class="box"><span>Emma Fletcher</span></div></div>
                        <label class="agree"><span class="tick on">&check;</span> I agree to the Terms &amp; Conditions of Beverley Blinds.</label>
                        <div class="accbtns"><span class="accbtn">Accept quote</span><span class="decbtn">Decline</span></div>
                      </div>

                      <div class="acceptedcard"><b>Quote accepted &check;</b><br>Thanks Emma! This quote was accepted on 31 Aug 2026. Beverley Blinds will be in touch.</div>
                    </div>
                  </div>
                </div>

                <!-- Scene: your side after acceptance -->
                <div class="osc scYours">
                  <div class="card-t">Back on your side</div>
                  <div class="yrow">Quote PRE-2026-0042 <span class="bdg">Accepted</span></div>
                  <div class="traycard"><span class="tt">&#128176; Deposit due on acceptance: &pound;33.00</span> &mdash; seeded at your 50% default, ready to record when it&rsquo;s paid.</div>
                  <div class="traycard"><span class="tt">&#128197; Pending Fitting</span> &mdash; &ldquo;Install: PRE-2026-0042 &mdash; Emma Fletcher&rdquo; is waiting in your calendar tray. Drag it onto a date and assign a fitter.</div>
                  <p class="ynote">No email pings you &mdash; you just see the quote move to <b>Accepted</b>, the deposit worked out, and the fitting waiting.</p>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Send it: email the PDF + a one-click accept link.</b>
                  <b class="c2 err"><span class="n">2</span> No email on file? Copy the link or WhatsApp it.</b>
                  <b class="c3"><span class="n">3</span> They get the quote + a link to view &amp; accept.</b>
                  <b class="c4"><span class="n">4</span> They open it (no login) and Accept &mdash; name + terms.</b>
                  <b class="c5"><span class="n">5</span> &ldquo;Quote accepted&rdquo; &mdash; and they get a thank-you.</b>
                  <b class="c6 good"><span class="n">6</span> Your side: Accepted, deposit set, fitting waiting.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A built quote sits as a <b>draft</b> until you send it. The <b>Send to customer</b> panel (on the quote) gives you three ways to
             get it to them &mdash; and sending flips the quote to <b>Sent</b>.</p>
          <ul class="steps">
            <li><b>Email PDF + accept link</b> &mdash; emails the customer the quote as a <b>PDF</b> plus a link to view it online and accept.
                Needs a <b>valid recipient email</b> (it pre-fills from the customer record); add an optional message above the standard text.
                Subject: <em>&ldquo;Your quote &lt;number&gt; from &lt;you&gt;&rdquo;</em>.</li>
            <li><b>Send via WhatsApp</b> &mdash; opens WhatsApp with the same link and a short message. Only shows when the customer has a
                <b>phone number</b> AND <b>&ldquo;Customer has WhatsApp on this number&rdquo;</b> is ticked (the panel tells you which is missing).</li>
            <li><b>Copy public link</b> &mdash; copies the link to paste anywhere. The link is the customer&rsquo;s way in &mdash; <b>no login</b>,
                the long token is the key.</li>
          </ul>
          <div class="oops"><b>No email on file?</b> The email button needs a valid address, or you&rsquo;ll get
             <em>&ldquo;Please provide a valid recipient email address.&rdquo;</em> Just <b>Copy the link</b> or <b>WhatsApp</b> it instead &mdash;
             both work with no email (opening the link marks the quote Sent on its own). Note the customer then won&rsquo;t get an automatic
             thank-you email on acceptance either, since that also needs their email.</div>
          <p><b>What the customer sees.</b> The link opens a clean, branded page &mdash; your logo, the quote number and date, the blinds
             (description &amp; qty; <b>prices too</b> unless you&rsquo;ve turned line prices off in <em>Settings &rarr; Quoting</em>), the
             <b>Total</b>, the <b>deposit due on acceptance</b>, your <b>bank details</b> for payment, and a <b>Terms &amp; Conditions</b> link.
             Widths and drops are never shown.</p>
          <ul class="steps">
            <li><b>They accept.</b> Under <b>&ldquo;Accept this quote&rdquo;</b> they type their <b>full name</b> (recorded as a digital sign-off,
                with the date and their IP), tick the <b>Terms</b> box if you have terms, and hit <b>Accept quote</b>. Up comes
                <em>&ldquo;Quote accepted &check; &mdash; Thanks &lt;name&gt;! &hellip; will be in touch&rdquo;</em>, and (if they have an email) an
                automatic <b>thank-you email</b> goes out.</li>
            <li><b>Or they decline</b> &mdash; <b>Decline</b> flips it to <b>Declined</b> and quietly removes the pending fitting.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Their Yes does three things on your side &mdash; with no email to you.</b> The
             quote flips to <b>Accepted</b>, a <b>deposit</b> is worked out from your default (e.g. 50%) ready to record, and a
             <b>&ldquo;Pending Fitting&rdquo;</b> appointment (&ldquo;Install: &lt;number&gt; &mdash; &lt;name&gt;&rdquo;) drops into your
             calendar&rsquo;s tray to drag onto a date. You find out by seeing it move, not by a ping &mdash; so keep an eye on the pipeline.
             (You can also mark it accepted yourself for a phone/in-person yes &mdash; same result.)</div></div>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Accept links don&rsquo;t expire.</b> A sent quote can be accepted whenever &mdash;
             even weeks later, at the old prices. If a quote is stale, <b>Reopen it as draft</b> and re-price (or start a fresh one) rather than
             leaving an old link live. (The default Terms say &ldquo;valid for 30 days&rdquo;, but nothing enforces it.)</div></div>',
        'script'  => [
            ['0:00', 'Send to customer: three ways.',       'Once the quote is ready, send it. Email the PDF with an accept link the customer can click — or share the same link by WhatsApp, or copy it.', 1],
            ['0:09', 'No email? Copy link or WhatsApp.',     'No email on file? The email button needs one. Copy the public link or send it on WhatsApp instead — the link works for anyone, no login.', 2],
            ['0:18', 'The email they receive.',             'The customer gets an email: their quote as a PDF, and a link to view it online and accept.', 3],
            ['0:26', 'The public page; Accept.',            'That link opens their quote — no login needed. They see the blinds, the total, the deposit due on acceptance, then type their name and Accept.', 4],
            ['0:36', 'Quote accepted; thank-you sent.',     'Up comes "Quote accepted" — and a thank-you email goes to them automatically.', 5],
            ['0:44', 'Your side: accepted, deposit, fitting.', 'Nothing pings you — you just watch it move to Accepted, with the deposit worked out and a Pending Fitting waiting in your calendar to drag onto a date.', 6],
        ],
    ],
];
