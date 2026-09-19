<?php
declare(strict_types=1);

/**
 * Guide: settings-logo
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 *
 * Mirrors /admin/settings.php (Company tab, "Company logo" section): the tab
 * strip, the grey preview panel with "Remove logo", the native file input, the
 * flash banners, the confirm modal, and the real server error strings.
 */

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company logo',
        'eyebrow' => 'Settings · Company',
        'blurb'   => 'Put your logo on every quote, invoice, receipt and the page customers accept on.',
        'lede'    => 'This is the one picture that makes the paperwork yours. It heads your <b>quote PDF</b>,
                      your <b>invoice</b>, your <b>receipt</b> and the <b>public page where customers accept</b> &mdash;
                      and you set it once, on the <b>Company</b> tab. It&rsquo;s a picture file off your own
                      computer, by the way: you don&rsquo;t design anything here, you just hand it over.',
        'open'    => '/admin/settings.php#company',
        'css'     => '
          /* ---- flash banner slot: where the real page puts its alerts ---- */
          .gd .bslot{ margin-bottom:.7rem; }
          .gd .bslot .errbanner, .gd .bslot .okbanner{ display:none; }
          .gd .stage[data-step="5"] .bslot .okbanner{ display:flex; }
          .gd .stage[data-step="6"] .bslot .errbanner{ display:flex; }

          /* ---- the seven settings tabs ---- */
          .gd .tabstrip{ display:flex; flex-wrap:wrap; gap:.25rem; padding:.25rem; border-bottom:1px solid var(--line); margin-bottom:.8rem; border-radius:8px; transition:box-shadow .25s; }
          .gd .tabchip{ font-size:.66rem; font-weight:600; color:var(--soft); padding:.24rem .5rem; border-radius:6px; border:1px solid transparent; white-space:nowrap; }
          .gd .tabchip.on{ background:var(--accent-wash); color:var(--accent-ink); border-color:var(--accent); }
          .gd .stage[data-step="1"] .tabstrip{ box-shadow:0 0 0 3px var(--accent-wash); }

          /* ---- the Company details section, collapsed: the logo sits UNDER it ---- */
          .gd .cdbar{ display:flex; align-items:center; justify-content:space-between; gap:.8rem; border:1px solid var(--line-2); border-radius:9px; padding:.45rem .6rem; margin-bottom:.9rem; opacity:.6; }
          .gd .cdbar .cdt{ font-weight:700; font-size:.82rem; color:var(--ink); }
          .gd .cdbar .cdg{ border:1px solid var(--line); border-radius:7px; padding:.16rem .5rem; font-size:.66rem; font-weight:600; color:var(--soft); }

          .gd .ldesc{ color:var(--soft); font-size:.78rem; margin:0 0 .9rem; }

          /* ---- the grey preview panel (only once a logo is set) ---- */
          .gd .greybar{ display:none; align-items:center; gap:1rem; flex-wrap:wrap; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:.8rem; margin-bottom:1rem; }
          .gd .stage[data-step="5"] .greybar, .gd .stage[data-step="6"] .greybar, .gd .stage[data-step="7"] .greybar{ display:flex; }
          .gd .logo-tile{ background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:.34rem .6rem; font-weight:800; font-size:1.05rem; letter-spacing:-.02em; color:#1c2733; }
          .gd .logo-tile b{ color:#2563eb; }
          .gd .logo-tile.sm{ font-size:.8rem; padding:.24rem .45rem; }
          .gd .secbtn{ display:inline-flex; border:1px solid var(--border-strong,#c7ccd4); background:var(--surface); color:var(--ink); border-radius:7px; padding:.24rem .6rem; font-size:.75rem; font-weight:600; transition:transform .1s, box-shadow .15s; }
          .gd .stage[data-step="7"] .secbtn{ box-shadow:0 0 0 3px var(--accent-wash); transform:scale(.96); }

          /* ---- the real native file input: Choose File + the file name ---- */
          .gd .fileinput{ display:inline-flex; align-items:center; gap:.6rem; border:1px solid var(--line); border-radius:7px; padding:.32rem .5rem; background:var(--surface); font-size:.82rem; }
          .gd .choosebtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.22rem .6rem; background:var(--panel); color:var(--ink); font-size:.78rem; transition:transform .1s, background .15s, border-color .15s, color .15s; }
          .gd .stage[data-step="2"] .choosebtn{ border-color:var(--accent); background:var(--accent-wash); color:var(--accent-ink); transform:scale(.96); }
          .gd .filename .nofile{ color:var(--faint); }
          .gd .filename .chosenfile{ display:none; color:var(--ink); font-weight:600; }
          .gd .stage[data-step="3"] .nofile, .gd .stage[data-step="4"] .nofile{ display:none; }
          .gd .stage[data-step="3"] .chosenfile, .gd .stage[data-step="4"] .chosenfile{ display:inline; }

          /* ---- label AND button both flip to "Replace logo" once a logo exists ---- */
          .gd .t-rep{ display:none; }
          .gd .stage[data-step="5"] .t-up, .gd .stage[data-step="6"] .t-up,
          .gd .stage[data-step="7"] .t-up, .gd .stage[data-step="8"] .t-up{ display:none; }
          .gd .stage[data-step="5"] .t-rep, .gd .stage[data-step="6"] .t-rep,
          .gd .stage[data-step="7"] .t-rep, .gd .stage[data-step="8"] .t-rep{ display:inline; }
          .gd .stage[data-step="3"] .save{ box-shadow:0 0 0 3px var(--accent-wash); }
          .gd .stage[data-step="4"] .save{ transform:scale(.96); filter:brightness(1.25); }

          /* ---- the file-picker dialog (step 2) ---- */
          .gd .filedlg{ display:none; position:absolute; left:1.2rem; top:4.2rem; width:246px; z-index:6; background:var(--surface); border:1px solid var(--line); border-radius:10px; box-shadow:0 16px 34px -12px rgba(20,30,45,.45); overflow:hidden; font-size:.78rem; }
          .gd .stage[data-step="2"] .filedlg{ display:block; }
          .gd .fd-bar{ background:var(--panel); border-bottom:1px solid var(--line); padding:.4rem .6rem; font-weight:700; color:var(--soft); font-size:.72rem; }
          .gd .fd-body{ padding:.3rem; }
          .gd .fd-item{ display:flex; align-items:center; gap:.45rem; padding:.28rem .45rem; border-radius:6px; color:var(--ink); }
          .gd .fd-item.sel{ background:var(--accent-wash); color:var(--accent-ink); font-weight:600; }
          .gd .fd-item.dim{ color:var(--faint); opacity:.5; }
          .gd .fd-actions{ display:flex; align-items:center; gap:.6rem; padding:.4rem .6rem; border-top:1px solid var(--line); }
          .gd .fd-file{ margin-right:auto; color:var(--soft); font-size:.72rem; }
          .gd .fd-open{ background:var(--accent); color:#fff; border-radius:6px; padding:.16rem .6rem; font-weight:600; }
          .gd .fd-cancel{ color:var(--soft); }

          /* ---- the shared confirm dialog (step 7) ---- */
          .gd .cfm{ display:none; position:absolute; inset:0; z-index:9; background:rgba(15,23,42,.45); align-items:center; justify-content:center; }
          .gd .stage[data-step="7"] .cfm{ display:flex; }
          .gd .cfmcard{ background:var(--surface); border:1px solid var(--line); border-radius:12px; padding:.9rem 1rem; box-shadow:0 18px 40px -14px rgba(15,23,42,.55); max-width:17rem; text-align:center; }
          .gd .cfmmsg{ font-size:.85rem; font-weight:600; color:var(--ink); margin-bottom:.75rem; }
          .gd .cfmbtns{ display:flex; gap:.5rem; justify-content:center; }
          .gd .cfm-cancel{ border:1px solid var(--line); background:var(--panel); color:var(--soft); border-radius:7px; padding:.28rem .7rem; font-size:.78rem; font-weight:600; }
          .gd .cfm-ok{ background:var(--err); color:#fff; border-radius:7px; padding:.28rem .7rem; font-size:.78rem; font-weight:700; }

          /* ---- where it lands (step 8) ---- */
          .gd .stage[data-step="8"] .settingswrap{ display:none; }
          .gd .mini-doc{ display:none; }
          .gd .stage[data-step="8"] .mini-doc{ display:block; }
          .gd .docnote{ font-size:.68rem; color:var(--faint); margin:0 0 .4rem; }
          .gd .doc{ border:1px solid var(--line); border-radius:10px; padding:.85rem .9rem; background:#fff; }
          .gd .dochead{ display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; border-bottom:1px solid #eef2f6; padding-bottom:.65rem; }
          .gd .dname{ font-weight:700; font-size:.8rem; color:#1c2733; margin-top:.45rem; }
          .gd .dline{ font-size:.7rem; color:#5b6b7b; line-height:1.45; }
          .gd .docmeta{ text-align:right; font-size:.72rem; color:#5b6b7b; font-weight:700; white-space:nowrap; }
          .gd .doclines{ display:flex; flex-direction:column; gap:.4rem; padding-top:.7rem; }
          .gd .doclines span{ height:8px; border-radius:4px; background:#eef2f6; }
          .gd .doclines span:nth-child(1){ width:70%; } .gd .doclines span:nth-child(2){ width:88%; } .gd .doclines span:nth-child(3){ width:52%; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- flash banners land here, at the very top of the page -->
                <div class="bslot">
                  <div class="okbanner"><span>&check;</span> <b>Logo uploaded.</b></div>
                  <div class="errbanner"><span>&#9888;</span> <b>Logo too large (2&nbsp;MB max).</b></div>
                </div>

                <div class="settingswrap">
                  <!-- the seven settings tabs; you land on Company -->
                  <div class="tabstrip">
                    <span class="tabchip on">Company</span><span class="tabchip">Quoting</span><span class="tabchip">Legal</span>
                    <span class="tabchip">Status colours</span><span class="tabchip">Suppliers</span><span class="tabchip">Accounting</span>
                    <span class="tabchip">Back up data</span>
                  </div>

                  <!-- Company details sits above; the logo is the next section down -->
                  <div class="cdbar"><span class="cdt">Company details</span><span class="cdg">Save company details</span></div>

                  <div class="card-t">Company logo</div>
                  <p class="ldesc">Used in the header of customer-facing quote PDFs and the public accept page. JPG, PNG, or GIF, up to 2&nbsp;MB.</p>

                  <!-- the grey preview panel: only there once a logo is set -->
                  <div class="greybar">
                    <div class="logo-tile">Demo<b>Blinds</b></div>
                    <span class="secbtn">Remove logo</span>
                  </div>

                  <div class="fld"><label><span class="t-up">Upload logo</span><span class="t-rep">Replace logo</span></label>
                    <div class="fileinput"><span class="choosebtn">Choose File</span>
                      <span class="filename"><span class="nofile">No file chosen</span><span class="chosenfile">logo.png</span></span></div>
                  </div>
                  <div class="save"><span class="t-up">Upload logo</span><span class="t-rep">Replace logo</span></div>
                </div>

                <!-- your computer\'s own file picker; only pictures can be picked -->
                <div class="filedlg">
                  <div class="fd-bar">Open &mdash; Pictures</div>
                  <div class="fd-body">
                    <div class="fd-item"><span>&#128247;</span> banner.jpg</div>
                    <div class="fd-item sel"><span>&#128247;</span> logo.png</div>
                    <div class="fd-item"><span>&#128247;</span> team-photo.jpg</div>
                    <div class="fd-item dim"><span>&#128196;</span> prices.pdf</div>
                  </div>
                  <div class="fd-actions"><span class="fd-file">logo.png</span><span class="fd-cancel">Cancel</span><span class="fd-open">Open</span></div>
                </div>

                <!-- Remove logo asks first -->
                <div class="cfm">
                  <div class="cfmcard">
                    <div class="cfmmsg">Remove the company logo?</div>
                    <div class="cfmbtns"><span class="cfm-cancel">Cancel</span><span class="cfm-ok">Yes, continue</span></div>
                  </div>
                </div>

                <!-- and this is what it heads -->
                <div class="mini-doc">
                  <p class="docnote">Your quote PDF &mdash; the same heading prints on the invoice, the receipt and the accept page.</p>
                  <div class="doc">
                    <div class="dochead">
                      <div>
                        <div class="logo-tile sm">Demo<b>Blinds</b></div>
                        <div class="dname">Demo Blinds Ltd</div>
                        <div class="dline">Unit 4, Sample Way</div>
                        <div class="dline">Sample Business Park</div>
                        <div class="dline">Leeds, West Yorkshire, LS1 1AA</div>
                        <div class="dline">01234 567890</div>
                        <div class="dline">hello@demoblinds.example</div>
                        <div class="dline">VAT No. GB 123 4567 89</div>
                      </div>
                      <div class="docmeta">Quote 1042</div>
                    </div>
                    <div class="doclines"><span></span><span></span><span></span></div>
                  </div>
                </div>

                <div class="caps">
                  <b class="c1"><span class="n">1</span> Settings opens on the <b>Company</b> tab.</b>
                  <b class="c2"><span class="n">2</span> Choose File &mdash; pictures only.</b>
                  <b class="c3"><span class="n">3</span> Picked &mdash; but not saved yet.</b>
                  <b class="c4"><span class="n">4</span> Upload logo.</b>
                  <b class="c5 good"><span class="n">5</span> Logo uploaded &mdash; and it now says Replace.</b>
                  <b class="c6 err"><span class="n">6</span> If it&rsquo;s too big, it tells you straight.</b>
                  <b class="c7"><span class="n">7</span> Remove logo asks first.</b>
                  <b class="c8 good"><span class="n">8</span> And there it is, heading your quote.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where it is.</b> In the sidebar go to <b>Setup &rarr; Settings</b>. Along the top there&rsquo;s a row of
             seven tabs &mdash; <b>Company</b>, Quoting, Legal, Status colours, Suppliers, Accounting and Back up data.
             You normally land on <b>Company</b>, but the app remembers the tab you were on last, so if you find
             yourself somewhere else just click <b>Company</b>. Scroll past <b>Company details</b> and the next
             section down is <b>Company logo</b>. That&rsquo;s the one.</p>

          <p><b>What kind of file.</b> A <b>JPG, PNG or GIF</b>, under <b>2&nbsp;MB</b>. The app checks what the file
             really <em>is</em>, not what it&rsquo;s called &mdash; so renaming a WEBP or a BMP to &ldquo;.png&rdquo; won&rsquo;t
             sneak it through, it&rsquo;ll be turned away. A <b>see-through (transparent) PNG</b> sits best, because
             everything it prints on is white paper.</p>

          <p><b>Shape matters more than size.</b> Your logo is squeezed to fit <b>64 points tall by 240 wide</b> on
             the PDFs, and <b>64 by 200</b> on the accept page. A long, wide logo fills that nicely. A tall square
             crest has to shrink to fit the height, so it comes out small and hard to read. Crop the empty white
             space off the edges before you upload and it&rsquo;ll look twice the size.</p>

          <ul class="steps">
            <li>Click <b>Choose File</b> and pick the picture off your computer. The picker only lets you choose
                pictures &mdash; a PDF or a spreadsheet will be greyed out.</li>
            <li>Check the <b>file name</b> now shows beside the button. Picking is <em>not</em> saving.</li>
            <li>Click <b>Upload logo</b>.</li>
            <li>The page reloads and a green <b>Logo uploaded.</b> appears at the top, with your logo sitting in a
                grey panel next to a <b>Remove logo</b> button.</li>
            <li>The label and the button now both read <b>Replace logo</b> &mdash; that&rsquo;s how you know it&rsquo;s on.</li>
          </ul>

          <p><b>Changing it later.</b> Just upload the new one straight over the top &mdash; you don&rsquo;t need to remove
             the old one first. There&rsquo;s only ever <b>one logo per company</b>: the old file is deleted as the new one
             lands. If your browser stubbornly still shows the old picture, refresh the page.</p>

          <p><b>Taking it off.</b> <b>Remove logo</b> asks first &mdash; <b>&ldquo;Remove the company logo?&rdquo;</b> &mdash; with
             <b>Cancel</b> and a red <b>Yes, continue</b>. Say yes and you get <b>Logo removed.</b> From that moment every
             document heads with your <b>company name in plain text</b> instead. Nothing you&rsquo;ve already sent changes;
             documents are drawn fresh each time you open them.</p>

          <div class="oops"><b>If it won&rsquo;t take:</b>
            <b>&ldquo;Please choose a logo file.&rdquo;</b> &mdash; you pressed the button with nothing picked. The screen doesn&rsquo;t
            stop you doing that, so this is far and away the usual one: pick the file, then press the button.
            <b>&ldquo;Logo too large (2&nbsp;MB max).&rdquo;</b> &mdash; shrink it and try again.
            <b>&ldquo;File must be a JPG, PNG, or GIF image.&rdquo;</b> &mdash; whatever it&rsquo;s called, it isn&rsquo;t really one of
            those three. Open it and re-save it as a PNG. Either way <b>nothing is lost</b> &mdash; any logo you already had
            is still there. Two rare ones, <b>&ldquo;Could not create uploads/logos directory.&rdquo;</b> and
            <b>&ldquo;Could not save the uploaded file.&rdquo;</b>, aren&rsquo;t your fault at all &mdash; ring us, that one&rsquo;s ours to fix.</div>

          <p><b>Where it shows up.</b> The quote PDF you download, the quote you email, the <b>invoice</b> PDF, the
             <b>receipt</b> PDF, and the <b>public page</b> your customer clicks accept on. On every one of them it sits
             directly above your company name, address, phone, email and VAT number &mdash; and those come from
             <b>Company details</b>, on this same tab. It&rsquo;s stored on your site as part of your company record, so it
             survives updates; it isn&rsquo;t part of any one quote.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Heads up, trade:</b> paperwork that comes
             <em>to</em> you from your supplier&rsquo;s factory &mdash; delivery notes, invoices, credit notes, statements &mdash;
             carries <b>their</b> letterhead, not yours. Your logo goes on what you send <em>out</em>, not on what lands
             with you.</div></div>

          <p>Two last things. If you have <b>Compact mode</b> switched on, the little grey line of advice under
             <b>Company logo</b> is hidden &mdash; that&rsquo;s the setting tidying up, not a fault. And no logo yet is
             perfectly fine: quotes simply head with your company name until you add one.</p>',
        // 4th value = the walkthrough step this line drives (keeps voice + visuals in sync).
        'script'  => [
            ['0:00', 'Tab strip lit; Company is the active tab.',            'Settings lives under Setup, and it opens on the Company tab. Scroll past your company details and you\'ll find Company logo — this is the one you want.', 1],
            ['0:08', 'Choose File pressed; picker opens, prices.pdf greyed.', 'Click Choose File, and pick the picture off your computer. It\'ll only let you pick a JPG, a PNG or a GIF, so if a file\'s greyed out, that\'s why.', 2],
            ['0:17', 'logo.png now sits beside the button.',                  'There it is — logo dot p-n-g, sitting next to the button. Nothing\'s saved yet, mind. Picking it isn\'t uploading it.', 3],
            ['0:26', 'Upload logo pressed.',                                  'So press Upload logo. The page nips off and comes straight back.', 4],
            ['0:33', 'Green banner, preview panel, labels now say Replace.',  'Green banner — Logo uploaded — and there\'s your logo, sat in a grey box with a Remove logo button beside it. Notice the button underneath now says Replace logo. That\'s how you know it\'s on.', 5],
            ['0:45', 'Red banner; the logo you had is untouched.',            'If it\'s a whopper of a file you\'ll get a red one: Logo too large, two meg max. Pick nothing at all and it says, please choose a logo file. And if it isn\'t really a picture — a file just renamed to dot p-n-g — it says, file must be a JPG, PNG, or GIF image. Nothing\'s lost; the logo you had is still there.', 6],
            ['1:00', 'Remove logo pressed; the confirm dialog opens.',        'Changed your mind? Remove logo asks first — remove the company logo? — and you click, yes, continue. Though to swap one for another you don\'t need this at all: just upload the new one over the top.', 7],
            ['1:10', 'The logo heads the quote, above your company details.', 'And that\'s it everywhere. Top of your quote, your invoice, your receipt, and the page your customer clicks accept on. Job done.', 8],
        ],
];
