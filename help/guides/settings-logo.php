<?php
declare(strict_types=1);

/**
 * Guide: settings-logo — "Your company logo" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors the "Company logo" section on the Company tab of
 * /admin/settings.php: its hint line, the grey preview panel with
 * "Remove logo" (data-confirm "Remove the company logo?" → the shared
 * _partials/confirm_modal.php: Cancel / "Yes, continue"), the native file
 * input whose label and button read "Upload logo" / "Replace logo", and the
 * real flash strings from the 'logo' / 'remove_logo' handlers. Sizes on the
 * paperwork: max 64×240px on the PDFs (pdf-generator/pdf.php), 64×200 on the
 * public quote page (quote-history/public.php).
 *
 * v2: one SCENE per script line (data-scene = the line's step); data-len is
 * worked out from the line itself (characters ÷ 13.6).
 */

$script = [
    ['1', 'What the logo is for',  'Your logo is the one picture that makes your paperwork look like yours. It sits at the top of your quote PDF, your invoice and your receipt, and on the page where your customer accepts the quote. You do not design anything here. You simply hand over a picture file, from your own computer.', 1],
    ['2', 'Finding it',            'Go to Setup, then Settings, and stay on the Company tab. Scroll down past your company details, and the next section is Company logo. Under the heading, the screen tells you what it wants: a JPG, PNG or GIF picture, up to two megabytes.', 2],
    ['3', 'Pick a good picture',   'A little advice before you choose. The logo is squeezed to fit a short, wide space at the top of the page. So a long, wide logo fills it nicely, while a tall, square one comes out small. Crop off any empty white space round the edges first. And a see-through PNG sits best on white paper.', 3],
    ['4', 'Choose the file',       'Under Upload logo, click Choose File, and pick your picture. The picker only offers picture files, so a PDF or a spreadsheet will be greyed out. Once you have picked it, its name shows beside the button. But picking is not saving. Nothing has been uploaded yet.', 4],
    ['5', 'Upload it',             'So now click the blue Upload logo button. The page reloads, and a green bar at the top says, Logo uploaded. Your logo now sits in a grey panel, with a Remove logo button beside it. And the label and the button underneath both change to Replace logo. That is how you know it is on.', 5],
    ['6', 'If it will not take',   'If something is wrong, you get a red bar instead. Press the button with no file picked, and it says, please choose a logo file. A file over two megabytes is too large. And a file that is not really a JPG, PNG or GIF is turned away, even if it has been renamed. Either way, nothing is lost. Any logo you already had stays put.', 6],
    ['7', 'Changing it later',     'To change your logo later, there is no need to remove the old one first. Just choose the new file, and click Replace logo. There is only ever one logo for your company, so the old one is thrown away as the new one lands. If your screen still shows the old picture, just refresh the page.', 7],
    ['8', 'Taking it off',         'To take it off altogether, click Remove logo. It asks you first: remove the company logo? Click Yes, continue, and a green bar says, Logo removed. From then on, your paperwork simply starts with your company name. The rest of your letterhead stays exactly as it was.', 8],
    ['9', 'Where it shows',        'And that is where it shows: at the top of your quote PDF, your invoice and your receipt, and on the page your customer accepts on. It sits just above your company name and address, and those come from Company details, on this same tab.', 9],
];
$L = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);

$ptr  = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';
$logo = static fn (string $cls = '', string $txt = 'Demo<b>Blinds</b>') => '<span class="lg ' . $cls . '"><i>&#9632;</i>' . $txt . '</span>';
$hint = '<p class="hintg">Used in the header of customer-facing quote PDFs and the public accept page. JPG, PNG, or GIF, up to 2 MB.</p>';
$type = static fn (string $txt, float $d, int $steps = 12, float $tt = .8): string =>
    '<span class="a-type" style="--d:' . $d . 's;--ts:' . $steps . ';--tt:' . $tt . 's">' . $txt . '</span>';

// The section. $state: 'none' (no logo yet) or 'has' (preview panel shown).
$section = static function (string $state = 'has', string $file = '<span class="nf">No file chosen</span>', string $btnCls = '', string $btnSt = '', string $preview = '', string $more = '') use ($logo, $hint): string {
    $word = $state === 'has' ? 'Replace logo' : 'Upload logo';
    $pv = $state === 'has'
        ? ($preview !== '' ? $preview : '<div class="pvpanel">' . $logo() . '<span class="btns sm">Remove logo</span></div>')
        : '';
    return '<div class="secbox"><div class="sech">Company logo</div>' . $hint . $pv
         . '<span class="lab">' . $word . '</span>'
         . '<div class="filein"><span class="cf">Choose File</span>' . $file . '</div>'
         . '<div class="fact"><span class="btnp ' . $btnCls . '" style="' . $btnSt . '">' . $word . '</span>' . $more . '</div></div>';
};

// A finished paper header (PDF letterhead).
$paper = static function (string $logoHtml) {
    return '<div class="paper">' . $logoHtml
         . '<b class="pn">Demo Blinds Ltd</b><div>Unit 4, Mill Court</div><div>Riverside Estate</div><div>Kendal LA9 6AB</div><div>01632 960123</div></div>';
};

return [
        'aud'     => 'admin',
        'section' => 'Settings',
        'title'   => 'Your company logo',
        'eyebrow' => 'Settings · Company',
        'v'       => 2,
        'blurb'   => 'Put your logo on every quote, invoice, receipt and the page customers accept on.',
        'lede'    => 'This is the one picture that makes the paperwork yours. It heads your <b>quote PDF</b>, your
                      <b>invoice</b>, your <b>receipt</b> and the <b>public page where customers accept</b> &mdash; and you
                      set it once, on the <b>Company</b> tab. It&rsquo;s a picture file off your own computer: you don&rsquo;t
                      design anything here, you just hand it over. To get there: <b>Setup</b> &rarr; <b>Settings</b> &rarr;
                      <b>Company</b>, then scroll to <b>Company logo</b>.',
        'open'    => '/admin/settings.php#company',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }

          /* the settings page */
          .gd .ph1{ font-size:.98rem; font-weight:800; color:var(--ink); }
          .gd .ph2{ font-size:.66rem; color:var(--faint); margin:.05rem 0 .55rem; }
          .gd .tabs{ display:flex; flex-wrap:wrap; gap:.1rem; border-bottom:1px solid var(--line); margin-bottom:.65rem; }
          .gd .tab{ font-size:.64rem; font-weight:600; color:var(--faint); padding:.28rem .45rem; border:1px solid transparent; border-bottom:none;
                    border-radius:6px 6px 0 0; margin-bottom:-1px; white-space:nowrap; }
          .gd .tab.on{ color:var(--accent); background:var(--surface); border-color:var(--line); border-bottom-color:var(--surface); }
          .gd .secbox{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); max-width:27rem; }
          .gd .secbox + .secbox{ margin-top:.55rem; }
          .gd .sech{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .35rem; }
          .gd .lab{ display:block; font-size:.6rem; font-weight:600; color:var(--soft); margin:.45rem 0 .15rem; }
          .gd .hintg{ font-size:.6rem; color:var(--faint); line-height:1.45; margin:0 0 .45rem; }
          .gd .fact{ margin-top:.5rem; position:relative; }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px; padding:.32rem .75rem; font-size:.72rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; gap:.3rem; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.28rem .65rem; font-size:.7rem; font-weight:600; }
          .gd .btns.sm{ font-size:.64rem; padding:.22rem .55rem; }
          .gd .flash{ background:var(--good-wash); border:1px solid color-mix(in srgb,var(--good) 35%,transparent); color:var(--good); font-weight:700;
                      font-size:.72rem; border-radius:8px; padding:.4rem .65rem; margin-bottom:.55rem; max-width:27rem; }
          .gd .flash.err{ background:var(--err-wash); border-color:color-mix(in srgb,var(--err) 35%,transparent); color:var(--err); }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.26rem .65rem; font-size:.7rem; font-weight:700; color:var(--ink); }
          .gd .chip.good{ border-color:var(--good); color:var(--good); } .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.7rem; }
          .gd .chips{ display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.6rem; }
          .gd .split{ display:grid; grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); gap:.9rem; align-items:start; }
          .gd .side2{ display:flex; flex-direction:column; align-items:flex-start; gap:.55rem; min-width:0; }
          .gd .side2 .chips{ margin-top:0; }

          /* the logo section */
          .gd .lg{ display:inline-flex; align-items:center; gap:.3rem; font-weight:800; font-size:.9rem; color:#1e3a8a; letter-spacing:-.01em; white-space:nowrap; }
          .gd .lg i{ font-style:normal; color:#2563eb; font-size:1rem; }
          .gd .lg b{ color:#2563eb; }
          .gd .lg.new{ color:#065f46; } .gd .lg.new i, .gd .lg.new b{ color:#059669; }
          .gd .pvpanel{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:.5rem .6rem; }
          .gd .pvpanel .lg{ background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:.2rem .45rem; }
          .gd .pvswap{ display:grid; } .gd .pvswap > *{ grid-area:1/1; }
          .gd .filein{ display:flex; align-items:center; gap:.45rem; font-size:.68rem; color:var(--ink); }
          .gd .cf{ border:1px solid #9ca3af; background:#f3f4f6; color:#111827; border-radius:4px; padding:.12rem .45rem; font-size:.66rem; white-space:nowrap; }
          .gd .nf{ color:var(--soft); }
          .gd .swap{ display:inline-grid; } .gd .swap > *{ grid-area:1/1; }

          /* file picker */
          .gd .picker{ position:absolute; z-index:5; left:38%; top:3.2rem; width:15rem; background:var(--surface); border:1px solid var(--line); border-radius:10px;
                       box-shadow:var(--gd-shadow); padding:.5rem .55rem; font-size:.68rem; }
          .gd .picker .pt{ font-weight:800; color:var(--ink); margin-bottom:.35rem; font-size:.72rem; }
          .gd .picker .fr{ display:flex; align-items:center; gap:.4rem; padding:.22rem .35rem; border-radius:5px; color:var(--ink); }
          .gd .picker .fr.off{ color:var(--faint); opacity:.55; }
          .gd .picker .fr.pick{ border:1px solid transparent; }
          .gd .picker .act{ display:flex; justify-content:flex-end; gap:.35rem; margin-top:.45rem; }

          /* frames for the shape advice */
          .gd .slot{ position:relative; width:15rem; height:4rem; border:2px dashed var(--line); border-radius:6px; display:flex; align-items:center; padding:0 .4rem; background:#fff; }
          .gd .slot .tag{ position:absolute; top:-.55rem; left:.4rem; font-size:.56rem; font-weight:800; color:var(--faint); background:var(--surface); padding:0 .25rem; letter-spacing:.04em; }
          .gd .wide{ display:flex; align-items:center; gap:.35rem; height:3rem; font-weight:800; font-size:1.25rem; color:#1e3a8a; white-space:nowrap; }
          .gd .wide i{ font-style:normal; color:#2563eb; }
          .gd .crest{ width:2.1rem; height:2.1rem; border-radius:6px; background:linear-gradient(135deg,#7c3aed,#c084fc); color:#fff; display:grid; place-items:center;
                      font-size:.5rem; font-weight:800; text-align:center; line-height:1.1; }
          .gd .crest.pad{ outline:12px solid #fff; box-shadow:0 0 0 13px #e5e7eb; transform:scale(.7); }

          /* confirm modal */
          .gd .cfm{ position:absolute; z-index:5; left:8%; top:5rem; width:17rem; background:var(--surface); border:1px solid var(--line); border-radius:12px;
                    box-shadow:var(--gd-shadow); padding:.75rem .85rem; font-size:.76rem; color:var(--ink); }
          .gd .cfm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.65rem; }
          .gd .cfm .cx{ background:var(--line); color:var(--soft); border-radius:7px; padding:.3rem .65rem; font-weight:600; font-size:.7rem; }
          .gd .cfm .ok{ background:#dc2626; color:#fff; border-radius:7px; padding:.3rem .65rem; font-weight:600; font-size:.7rem; }

          /* paper */
          .gd .paper{ background:#fff; color:#374151; border:1px solid #e5e7eb; border-radius:6px; padding:.6rem .7rem; font-size:.62rem; line-height:1.5;
                      box-shadow:var(--gd-shadow); max-width:15rem; }
          .gd .paper .pn{ display:block; font-size:.8rem; color:#111827; margin-top:.25rem; }
          .gd .paper .lg{ font-size:.85rem; }
          .gd .ptag{ font-size:.58rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--faint); margin-bottom:.25rem; }

          @media (max-width:640px){
            .gd .split{ grid-template-columns:1fr; }
            .gd .sc{ min-height:430px; }
            .gd .picker{ left:4%; top:7rem; }
            .gd .slot{ width:100%; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings / company</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a class="on">Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="ph1">Settings</div><div class="ph2">Company details and per-quote defaults.</div>
                  ' . $section('has') . '
                  <p class="scs" style="margin-top:.7rem">Press <b>&#9654; Play</b> below &mdash; nine short chapters.</p>
                </div>

                <!-- 1 — what it is for -->
                <div class="sc" data-scene="1" data-len="' . $L(1) . '">
                  <div class="sct a-fade" style="--d:.2s">The picture at the top of your paperwork</div>
                  <div class="split" style="margin-top:.6rem">
                    <div class="paper a-rise" style="--d:1s;max-width:17rem">
                      <div class="a-drop" style="--d:3s">' . $logo() . '</div>
                      <b class="pn">Demo Blinds Ltd</b><div>Unit 4, Mill Court</div><div>Kendal LA9 6AB</div>
                    </div>
                    <div class="side2">
                      <span class="chip a-pop" style="--d:5.5s">&#128196; Quote PDF</span>
                      <span class="chip a-pop" style="--d:6.5s">&#128196; Invoice</span>
                      <span class="chip a-pop" style="--d:7.3s">&#128196; Receipt</span>
                      <span class="chip a-pop" style="--d:9.5s">&#9989; The page where they accept</span>
                      <span class="chip good a-pop" style="--d:16s">&#128444; Just a picture file from your computer</span>
                    </div>
                  </div>
                </div>

                <!-- 2 — finding it -->
                <div class="sc" data-scene="2" data-len="' . $L(2) . '">
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:.8s">Setup</span><span class="arrow a-fade" style="--d:1.5s">&rarr;</span>
                    <span class="chip a-pop" style="--d:2s">Settings</span><span class="arrow a-fade" style="--d:2.8s">&rarr;</span>
                    <span class="chip a-pop" style="--d:3.3s;border-color:var(--accent);color:var(--accent)">Company</span>
                  </div>
                  <div class="secbox a-rise" style="--d:5s;opacity:.6"><div class="sech" style="margin:0">Company details</div><div class="hintg" style="margin:.2rem 0 0">Company name &middot; Contact name &middot; Email &middot; Phone &middot; &hellip;</div></div>
                  <div class="secbox a-rise" style="--d:7.5s">
                    <div class="sech">Company logo</div>
                    <div class="a-ring" style="--d:11s;border-radius:6px">' . $hint . '</div>
                    <span class="lab">Upload logo</span>
                    <div class="filein"><span class="cf">Choose File</span><span class="nf">No file chosen</span></div>
                    <div class="fact"><span class="btnp">Upload logo</span></div>
                  </div>
                  <div class="chips"><span class="chip a-pop" style="--d:13s">JPG</span><span class="chip a-pop" style="--d:13.4s">PNG</span><span class="chip a-pop" style="--d:13.8s">GIF</span>
                    <span class="chip a-pop" style="--d:15s">up to 2 MB</span></div>
                </div>

                <!-- 3 — shape advice -->
                <div class="sc" data-scene="3" data-len="' . $L(3) . '">
                  <div class="sct a-fade" style="--d:.2s">Shape matters more than size</div>
                  <p class="scs a-fade" style="--d:.6s">The logo is fitted into a short, wide space at the top of the page.</p>
                  <div class="slot a-rise" style="--d:3s"><span class="tag">THE SPACE ON THE PAGE</span>
                    <span class="wide a-fly" style="--d:6s"><i>&#9632;</i>Demo<span style="color:#2563eb">Blinds</span></span></div>
                  <div class="chips"><span class="chip good a-pop" style="--d:7.5s">&#10003; long and wide &mdash; fills it nicely</span></div>
                  <div class="slot a-rise" style="--d:9s;margin-top:.9rem"><span class="tag">THE SPACE ON THE PAGE</span>
                    <span class="a-pop" style="--d:10.5s"><span class="crest pad">DB<br>EST.</span></span>
                    <span class="a-pop" style="--d:15.5s;margin-left:1.6rem"><span class="crest" style="width:2.9rem;height:2.9rem">DB<br>EST.</span></span></div>
                  <div class="chips"><span class="chip bad a-pop" style="--d:11.5s">tall and square &mdash; comes out small</span>
                    <span class="chip a-pop" style="--d:15s">&#9986; crop the white space off</span>
                    <span class="chip a-pop" style="--d:19s">see-through PNG on white paper</span></div>
                </div>

                <!-- 4 — choose the file -->
                <div class="sc" data-scene="4" data-len="' . $L(4) . '">
                  ' . $section('none', '<span class="swap"><span class="nf a-out" style="--d:11.5s">No file chosen</span><span class="a-fade" style="--d:11.5s">logo.png</span></span>', '', '',
                       '', '') . '
                  <div class="picker a-mid" style="--d:3.5s;--d2:11s">
                    <div class="pt">Open</div>
                    <div class="fr pick a-ring" style="--d:8.5s">&#128444; logo.png</div>
                    <div class="fr off">&#128196; prices.pdf</div>
                    <div class="fr off">&#128202; quotes.xlsx</div>
                    <div class="act"><span class="btns sm">Cancel</span><span class="btnp a-press" style="--d:10s;font-size:.64rem;padding:.22rem .55rem">Open</span></div>
                  </div>
                  <div class="a-move" style="--fx:60%;--fy:85%;--tx:2.6rem;--ty:5.6rem;--d:.6s;--md:1.8s">' . $ptr . '</div>
                  <div class="chips"><span class="chip a-pop" style="--d:6s">PDFs and spreadsheets are greyed out</span>
                    <span class="chip bad a-pop" style="--d:16s">Picked &ne; saved &mdash; not uploaded yet</span></div>
                </div>

                <!-- 5 — upload -->
                <div class="sc" data-scene="5" data-len="' . $L(5) . '">
                  <div class="flash a-pop" style="--d:4.5s">Logo uploaded.</div>
                  <div class="secbox"><div class="sech">Company logo</div>' . $hint . '
                    <div class="pvpanel a-drop" style="--d:7.5s">' . $logo() . '<span class="btns sm a-ring" style="--d:10.5s">Remove logo</span></div>
                    <span class="lab"><span class="swap"><span class="a-out" style="--d:14s">Upload logo</span><span class="a-fade" style="--d:14s">Replace logo</span></span></span>
                    <div class="filein"><span class="cf">Choose File</span><span class="swap"><span class="a-out" style="--d:4s">logo.png</span><span class="nf a-fade" style="--d:4s">No file chosen</span></span></div>
                    <div class="fact"><span class="btnp a-press a-ring" style="--d:1.8s"><span class="swap"><span class="a-out" style="--d:14.5s">Upload logo</span><span class="a-fade" style="--d:14.5s">Replace logo</span></span></span></div>
                  </div>
                  <div class="chips"><span class="chip good a-pop" style="--d:18s">&#10003; &ldquo;Replace logo&rdquo; = it&rsquo;s on</span></div>
                </div>

                <!-- 6 — errors -->
                <div class="sc" data-scene="6" data-len="' . $L(6) . '">
                  <div class="sct a-fade" style="--d:.2s">If it won&rsquo;t take</div>
                  <div class="flash err a-fly" style="--d:4s">Please choose a logo file.</div>
                  <div class="flash err a-fly" style="--d:8.5s">Logo too large (2 MB max).</div>
                  <div class="flash err a-fly" style="--d:12s">File must be a JPG, PNG, or GIF image.</div>
                  <div class="secbox a-rise" style="--d:19s;max-width:20rem"><div class="sech">Company logo</div>
                    <div class="pvpanel">' . $logo() . '<span class="btns sm">Remove logo</span></div></div>
                  <div class="chips"><span class="chip good a-pop" style="--d:21s">&#10003; nothing lost &mdash; your logo stays put</span></div>
                </div>

                <!-- 7 — replace -->
                <div class="sc" data-scene="7" data-len="' . $L(7) . '">
                  ' . $section('has', '<span class="swap"><span class="nf a-out" style="--d:5s">No file chosen</span><span class="a-fade" style="--d:5s">new-logo.png</span></span>', 'a-press a-ring', '--d:7.5s',
                       '<div class="pvpanel"><span class="pvswap"><span class="a-out" style="--d:10s">' . $logo() . '</span><span class="a-fade" style="--d:10s">' . $logo('new', 'Demo<b>Blinds</b> &amp; Shutters') . '</span></span><span class="btns sm">Remove logo</span></div>') . '
                  <div class="chips"><span class="chip a-pop" style="--d:2s">No need to remove the old one first</span>
                    <span class="chip good a-pop" style="--d:12s">One logo per company &mdash; the old one is thrown away</span></div>
                </div>

                <!-- 8 — remove -->
                <div class="sc" data-scene="8" data-len="' . $L(8) . '">
                  <div class="flash a-pop" style="--d:9s">Logo removed.</div>
                  <div class="split">
                    <div class="secbox"><div class="sech">Company logo</div>
                      <div class="pvpanel a-out" style="--d:9s">' . $logo() . '<span class="btns sm a-press a-ring" style="--d:2s">Remove logo</span></div>
                      <div class="a-fade" style="--d:9.5s"><span class="lab">Upload logo</span>
                        <div class="filein"><span class="cf">Choose File</span><span class="nf">No file chosen</span></div>
                        <div class="fact"><span class="btnp">Upload logo</span></div></div>
                    </div>
                    <div><div class="ptag a-fade" style="--d:12s">Your paperwork now</div>
                      <div class="paper a-rise" style="--d:12.5s"><b class="pn" style="margin-top:0">Demo Blinds Ltd</b><div>Unit 4, Mill Court</div><div>Kendal LA9 6AB</div></div></div>
                  </div>
                  <div class="cfm a-mid" style="--d:3.5s;--d2:8.5s">Remove the company logo?
                    <div class="act"><span class="cx">Cancel</span><span class="ok a-press" style="--d:7.5s">Yes, continue</span></div></div>
                </div>

                <!-- 9 — where it shows -->
                <div class="sc" data-scene="9" data-len="' . $L(9) . '">
                  <div class="split">
                    <div><div class="ptag a-fade" style="--d:.5s">Quote PDF &middot; invoice &middot; receipt</div>
                      <div class="a-rise" style="--d:1s">' . $paper('<span class="a-ring" style="--d:2s;border-radius:6px;display:inline-block">' . $logo() . '</span>') . '</div></div>
                    <div class="side2">
                      <span class="chip a-pop" style="--d:4s">&#128196; Quote PDF</span>
                      <span class="chip a-pop" style="--d:5s">&#128196; Invoice &middot; Receipt</span>
                      <span class="chip a-pop" style="--d:7s">&#9989; The accept page</span>
                      <span class="chip a-pop" style="--d:11s">Name &amp; address &larr; Company details</span>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where it is.</b> <b>Setup &rarr; Settings</b>. Along the top there&rsquo;s a row of seven tabs &mdash;
             <b>Company</b>, Quoting, Legal, Status colours, Suppliers, Accounting and Back up data. Settings remembers the
             tab you were on last, so if you land somewhere else, click <b>Company</b>. Scroll past <b>Company details</b>
             and the next section is <b>Company logo</b>. Under the heading it says: &ldquo;Used in the header of
             customer-facing quote PDFs and the public accept page. JPG, PNG, or GIF, up to 2 MB.&rdquo;</p>

          <p><b>What kind of file.</b> A <b>JPG, PNG or GIF</b>, up to <b>2&nbsp;MB</b>. The system checks what the file
             really <em>is</em>, not what it&rsquo;s called &mdash; renaming a WEBP or a BMP to &ldquo;.png&rdquo; won&rsquo;t
             sneak it through. A <b>see-through (transparent) PNG</b> sits best, because everything it prints on is
             white.</p>

          <p><b>Shape matters more than size.</b> Your logo is fitted into <b>64 pixels tall by 240 wide</b> on the PDFs,
             and <b>64 by 200</b> on the accept page. A long, wide logo fills that nicely; a tall square crest has to shrink
             to the height, so it comes out small. Crop the empty white space off the edges before you upload and it&rsquo;ll
             look much bigger.</p>

          <ul class="steps">
            <li>Under <b>Upload logo</b>, click <b>Choose File</b> and pick the picture. The picker only offers pictures
                &mdash; a PDF or a spreadsheet is greyed out.</li>
            <li>Check the <b>file name</b> now shows beside the button. Picking is <em>not</em> saving.</li>
            <li>Click <b>Upload logo</b>.</li>
            <li>The page reloads with a green <b>Logo uploaded.</b> at the top, and your logo in a grey panel next to a
                <b>Remove logo</b> button.</li>
            <li>The label and the button now both read <b>Replace logo</b> &mdash; that&rsquo;s how you know it&rsquo;s on.</li>
          </ul>

          <p><b>Changing it later.</b> Upload the new one straight over the top: choose the file, click <b>Replace
             logo</b>. You don&rsquo;t need to remove the old one first. There&rsquo;s only ever <b>one logo per company</b>
             &mdash; the old file is deleted as the new one lands. If your browser still shows the old picture, refresh
             the page.</p>

          <p><b>Taking it off.</b> <b>Remove logo</b> asks first &mdash; <b>&ldquo;Remove the company logo?&rdquo;</b> &mdash;
             with <b>Cancel</b> and a red <b>Yes, continue</b>. Say yes and you get <b>Logo removed.</b> From then on your
             paperwork simply starts with your <b>company name</b>. PDFs you&rsquo;ve already sent stay as they were;
             anything opened from now on is drawn fresh, without the logo.</p>

          <div class="oops"><b>If it won&rsquo;t take:</b>
            <b>&ldquo;Please choose a logo file.&rdquo;</b> &mdash; you pressed the button with nothing picked (the usual
            one): pick the file, then press the button.
            <b>&ldquo;Logo too large (2&nbsp;MB max).&rdquo;</b> &mdash; shrink it and try again.
            <b>&ldquo;File must be a JPG, PNG, or GIF image.&rdquo;</b> &mdash; whatever it&rsquo;s called, it isn&rsquo;t
            really one of those three; open it and re-save it as a PNG. Either way <b>nothing is lost</b> &mdash; any logo
            you already had is still there. Two rare ones, <b>&ldquo;Could not create uploads/logos directory.&rdquo;</b>
            and <b>&ldquo;Could not save the uploaded file.&rdquo;</b>, aren&rsquo;t your fault &mdash; tell us, that one&rsquo;s
            ours to fix.</div>

          <p><b>Where it shows up.</b> The quote PDF you download or email, the <b>invoice</b> PDF, the <b>receipt</b> PDF, and
             the <b>public page</b> your customer accepts on. On each it sits directly above your company name, address,
             phone and email &mdash; those come from <b>Company details</b>, on this same tab
             (<a href="/help/guide.php?g=settings-company">see that guide</a>).</p>

          <p>If you have <b>Compact mode</b> on, the grey line of advice under <b>Company logo</b> is hidden &mdash; that&rsquo;s
             the setting tidying up, not a fault. And no logo yet is perfectly fine: your paperwork simply starts with your
             company name until you add one.</p>',
        'script'  => $script,
];
