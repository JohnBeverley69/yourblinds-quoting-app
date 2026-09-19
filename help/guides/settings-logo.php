<?php
declare(strict_types=1);

/**
 * Guide: settings-logo
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
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
];
