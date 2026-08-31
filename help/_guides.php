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
          .gd .stage[data-step="2"] .pill-google, .gd .stage[data-step="3"] .pill-google, .gd .stage[data-step="4"] .pill-google{ border-color:var(--line); background:transparent; color:var(--soft); }
          .gd .stage[data-step="2"] .pill-waze, .gd .stage[data-step="3"] .pill-waze, .gd .stage[data-step="4"] .pill-waze{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); }
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
                  <div style="margin-top:.4rem"><span class="pill pill-google sel">Google Maps</span><span class="pill pill-waze">Waze</span></div>
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
        'title'   => 'Markup vs margin',
        'eyebrow' => 'Settings · Quoting',
        'blurb'   => 'The pricing choice people get wrong — with a live calculator to feel the difference.',
        'lede'    => 'The choice that trips people up. You set your profit as <b>markup</b> or as <b>margin</b> —
                      and they are <b>not</b> the same number. Mix them up and every quote is priced wrong. Here\'s
                      the difference, plainly, with a calculator to play with.',
        'open'    => '/admin/settings.php',
        'css'     => '
          .gd .mlabel{ font-size:.78rem; color:var(--soft); font-weight:600; margin-bottom:.3rem; }
          .gd .mpills{ margin-bottom:.9rem; }
          .gd .mpills .pill{ display:inline-flex; border:1px solid var(--line); border-radius:999px; padding:.2rem .7rem; font-size:.78rem; margin-right:.4rem; color:var(--soft); }
          .gd .stage[data-step="0"] .pmk{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); }
          .gd .stage[data-step="1"] .pmg, .gd .stage[data-step="2"] .pmg, .gd .stage[data-step="3"] .pmg{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); }
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
                <div class="mpills"><span class="pill pmk">Markup&nbsp;%</span><span class="pill pmg">Margin&nbsp;%</span></div>
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
];
