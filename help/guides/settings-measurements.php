<?php
declare(strict_types=1);

/**
 * Guide: settings-measurements
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
    'aud'     => 'admin',
    'section' => 'Settings',
    'title'   => 'Measurement units',
    'eyebrow' => 'Settings · Quoting',
    'blurb'   => 'Pick the unit your team measures in — mm, cm, m or inches — and see everywhere it shows up.',
    'lede'    => 'One dropdown on the <b>Quoting</b> tab decides the unit your team <em>types</em> and <em>reads</em> sizes in.
                  Underneath, every size is still stored in millimetres, so you can change this whenever you like and nothing
                  already quoted moves. This guide shows the setting, then walks you through everywhere it turns up
                  afterwards &mdash; the quote builder, InstaPrice, your price tables &mdash; and the places that stay in
                  millimetres no matter what you choose.',
    'open'    => '/admin/settings.php',
    'css'     => '
      .gd .sc{ display:none; }
      .gd .stage[data-step="0"] .scA, .gd .stage[data-step="1"] .scA,
      .gd .stage[data-step="2"] .scA, .gd .stage[data-step="3"] .scA{ display:block; }
      .gd .stage[data-step="4"] .scB{ display:block; }
      .gd .stage[data-step="5"] .scC{ display:block; }
      .gd .stage[data-step="6"] .scD{ display:block; }
      .gd .stage[data-step="7"] .scE{ display:block; }
      .gd .stage[data-step="8"] .scF{ display:block; }

      /* Settings tab strip — mirrors the real seven buttons. */
      .gd .tabs{ display:flex; gap:.15rem; flex-wrap:wrap; border-bottom:1px solid var(--line); margin-bottom:.7rem; }
      .gd .tabs span{ font-size:.66rem; color:var(--soft); padding:.28rem .45rem; border:1px solid transparent; border-radius:6px 6px 0 0; white-space:nowrap; }
      .gd .tabs span.on{ color:var(--ink); font-weight:700; background:var(--surface); border-color:var(--line); border-bottom-color:var(--surface); }

      /* Greyed neighbours so the block is recognisable in place. */
      .gd .ghostsec{ font-size:.72rem; color:var(--faint); font-weight:700; padding:.3rem 0; opacity:.6; }
      .gd .secline{ border-top:1px solid var(--line); margin:.35rem 0 .5rem; }
      .gd .uihint{ font-size:.68rem; color:var(--soft); line-height:1.5; margin:.15rem 0 .55rem; max-width:34rem; }
      .gd .mlabel{ font-size:.64rem; color:var(--faint); font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.22rem; }
      .gd .boxv{ height:30px; border:1px solid var(--line); border-radius:7px; background:var(--panel); display:flex; align-items:center; padding:0 .5rem; font-size:.8rem; color:var(--ink); overflow:hidden; }

      /* The select and its open list. */
      .gd .selwrap{ position:relative; display:inline-block; }
      .gd .optlist{ display:none; position:absolute; left:0; top:calc(100% + 3px); z-index:5; min-width:190px; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; box-shadow:0 8px 20px rgba(15,23,42,.14); overflow:hidden; }
      .gd .stage[data-step="2"] .optlist{ display:block; }
      .gd .optlist i{ display:block; font-style:normal; font-size:.8rem; color:var(--ink); padding:.3rem .55rem; }
      .gd .optlist i.pick{ background:var(--accent-wash); color:var(--accent-ink); font-weight:700; }
      .gd .u-mm, .gd .u-in{ display:none; }
      .gd .stage[data-step="0"] .u-mm, .gd .stage[data-step="1"] .u-mm, .gd .stage[data-step="2"] .u-mm{ display:inline; }
      .gd .stage[data-step="3"] .u-in{ display:inline; }
      .gd .savedbanner{ display:none; margin-bottom:.55rem; }
      .gd .stage[data-step="3"] .savedbanner{ display:flex; }

      /* Small conversion strip + cards used by the later scenes. */
      .gd .conv{ display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.6rem; }
      .gd .conv b{ font-size:.72rem; font-weight:600; color:var(--soft); background:var(--panel); border:1px solid var(--line); border-radius:999px; padding:.2rem .55rem; }
      .gd .conv b em{ font-style:normal; color:var(--ink); font-weight:700; }
      /* Own grid class — the shared .frow (and its mobile fallback) is left alone. */
      .gd .qrow{ display:grid; gap:.5rem; margin-top:.5rem; }
      .gd .qrow.four{ grid-template-columns:repeat(4,1fr); }
      .gd .qrow.two{ grid-template-columns:repeat(2,1fr); }
      @media(max-width:620px){ .gd .qrow.four{ grid-template-columns:1fr 1fr; } }
      .gd .uihint code{ background:var(--bg-subtle-2,#f3f4f6); padding:.02rem .22rem; border-radius:4px; font-size:.95em; }
      .gd .card-s{ border:1px solid var(--line); border-radius:9px; background:var(--panel); padding:.5rem .6rem; }
      .gd .card-s h4{ margin:0 0 .2rem; font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; }
      .gd .card-s p{ margin:0; font-size:.78rem; color:var(--ink); }
      .gd .card-s p.mmm{ font-weight:700; }
      .gd .echo{ margin-top:.45rem; font-size:.76rem; color:var(--soft); font-weight:600; }
      .gd .minitab{ width:100%; border-collapse:collapse; margin-top:.4rem; font-size:.76rem; }
      .gd .minitab th{ text-align:left; font-size:.64rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); border-bottom:1px solid var(--line); padding:.22rem .3rem; font-weight:700; }
      .gd .minitab td{ border-bottom:1px solid var(--line); padding:.26rem .3rem; color:var(--ink); }
      .gd .minitab td .ph{ color:var(--faint); }
      .gd .note{ font-size:.72rem; color:var(--soft); margin-top:.5rem; }',
    'demo'    => '
      <div class="demo-shell">
        <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / settings</span></div>
        <div class="app">
          <div class="side">
            <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
            <a>Dashboard</a><a>Calendar</a><a>Customers</a><a>Products</a><a class="on">Settings</a>
          </div>
          <div class="stage" id="gdStage" data-step="0">

            <!-- Scene A: the real Settings → Quoting tab, Measurements block -->
            <div class="sc scA">
              <div class="okbanner savedbanner"><b>&#10003;</b> Default measurement unit saved.</div>
              <div class="tabs">
                <span>Company</span><span class="on">Quoting</span><span>Legal</span><span>Status colours</span>
                <span>Suppliers</span><span>Accounting</span><span>Back up data</span>
              </div>
              <div class="ghostsec">Default margins</div>
              <div class="secline"></div>
              <div class="card-t">Measurements</div>
              <p class="uihint">The unit your team enters and sees blind sizes in. Sizes are always stored the same way
                 under the hood, so you can change this any time. On a quote you can still override the unit for one job,
                 and you can always type a unit directly (e.g. <code>60in</code>, <code>1.5m</code>) for a one-off.</p>
              <div class="mlabel">Default measurement unit</div>
              <div class="selwrap">
                <span class="selectbox"><span class="u-mm">Millimetres (mm)</span><span class="u-in">Inches (in)</span></span>
                <div class="optlist">
                  <i>Millimetres (mm)</i><i>Centimetres (cm)</i><i>Metres (m)</i><i class="pick">Inches (in)</i>
                </div>
              </div>
              <div class="save">Save unit</div>
              <div class="secline"></div>
              <div class="ghostsec">Quote defaults</div>
            </div>

            <!-- Scene B: the quote builder\'s own per-quote unit switcher -->
            <div class="sc scB">
              <div class="card-t">Quote builder &mdash; add a blind</div>
              <div class="mlabel">Measurement unit (this quote)</div>
              <span class="selectbox">Inches (in)</span>
              <p class="uihint">Re-displays this quote&rsquo;s sizes in the chosen unit. Sizes are stored the same way regardless.</p>
              <div class="qrow four">
                <div class="fld"><label>Width (&quot;) <span class="req">*</span></label>
                  <div class="box f4"><span class="ph">Width in &quot;</span><span class="val">59.06</span></div></div>
                <div class="fld"><label>Drop (&quot;) <span class="req">*</span></label>
                  <div class="box f4"><span class="ph">Drop in &quot;</span><span class="val">39.37</span></div></div>
                <div class="fld"><label>Quantity</label><div class="boxv">1</div></div>
                <div class="fld"><label>Notes</label><div class="box"><span class="ph">&nbsp;</span></div></div>
              </div>
              <div class="conv">
                <b>Stored: <em>1500 &times; 1000 mm</em></b><b>mm <em>1500</em></b><b>cm <em>150</em></b><b>m <em>1.5</em></b><b>in <em>59.06</em></b>
              </div>
            </div>

            <!-- Scene C: typing a unit for a one-off (InstaPrice) -->
            <div class="sc scC">
              <div class="card-t">InstaPrice &mdash; one-off unit</div>
              <div class="mlabel">Measurement unit</div>
              <span class="selectbox">Inches (in)</span>
              <div class="mlabel" style="margin-top:.6rem">Dimensions (&quot;) &amp; quantity</div>
              <div class="qrow four">
                <div class="fld"><label>Width</label>
                  <div class="box f5"><span class="ph">Width</span><span class="val">1.5m</span></div></div>
                <div class="fld"><label>Drop</label>
                  <div class="box f5"><span class="ph">Drop</span><span class="val">60in</span></div></div>
                <div class="fld"><label>Qty</label><div class="boxv">1</div></div>
                <div class="fld"></div>
              </div>
              <div class="echo">Using 1500 &times; 1524 mm</div>
              <p class="note">A unit you type &mdash; <code>mm</code>, <code>cm</code>, <code>m</code>, <code>in</code>,
                 or a plain <code>&quot;</code> &mdash; always wins over the setting, just for that box.</p>
            </div>

            <!-- Scene D: the slip and the silent one -->
            <div class="sc scD">
              <div class="card-t">When a size won&rsquo;t read</div>
              <div class="errbanner"><b>!</b> <span>Could not read width &quot;two metres&quot;.</span></div>
              <div class="qrow two">
                <div class="fld"><label>Width (&quot;) <span class="req">*</span></label>
                  <div class="boxv">two metres</div></div>
                <div class="fld"><label>Drop (&quot;) <span class="req">*</span></label>
                  <div class="boxv">39.37</div></div>
              </div>
              <div class="heads"><span class="hi">&#9888;</span><div><b>A bare number is read in this quote&rsquo;s unit.</b>
                 On an inches quote, typing <b>1500</b> means 1500 inches &mdash; 38,100&nbsp;mm. No error, just a very large blind.</div></div>
            </div>

            <!-- Scene E: what stays in millimetres -->
            <div class="sc scE">
              <div class="card-t">Always millimetres, whatever you pick</div>
              <div class="qrow four">
                <div class="card-s"><h4>Customer quote PDF</h4><p class="mmm">1500 &times; 1000 mm</p></div>
                <div class="card-s"><h4>Online quote page</h4><p class="mmm">1500 &times; 1000 mm</p></div>
                <div class="card-s"><h4>Factory worksheet</h4><p class="mmm">mm</p></div>
                <div class="card-s"><h4>Export CSV</h4><p class="mmm">Width (mm)</p></div>
              </div>
              <p class="note">Customers and the workshop always see millimetres. The unit you choose is for
                 <b>your team&rsquo;s typing and reading</b>, nothing else.</p>
            </div>

            <!-- Scene F: the other screens that follow the company unit -->
            <div class="sc scF">
              <div class="card-t">Products &rarr; price table (width-only list)</div>
              <table class="minitab">
                <tr><th>Width (&quot;)</th><th>Price (&pound;)</th></tr>
                <tr><td>31.5</td><td>42.00</td></tr>
                <tr><td>39.37</td><td>48.50</td></tr>
                <tr><td><span class="ph">e.g. 31.5</span></td><td><span class="ph">&nbsp;</span></td></tr>
              </table>
              <p class="note">The header and the boxes follow your <b>company</b> unit &mdash; the prices and the stored
                 widths don&rsquo;t change. The big width &times; drop grid keeps its own
                 <b>Drop \\ Width (mm)</b> corner, and the product&rsquo;s <b>&#128065; Live preview</b> drawer says
                 <b>Dimensions (&quot;)</b> too.</p>
            </div>

            <div class="caps">
              <b class="c1"><span class="n">1</span> Settings &rarr; Quoting &rarr; Measurements.</b>
              <b class="c2"><span class="n">2</span> Four choices: mm, cm, m, inches.</b>
              <b class="c3 good"><span class="n">3</span> Save unit &mdash; and it&rsquo;s done.</b>
              <b class="c4"><span class="n">4</span> Every quote opens in your unit.</b>
              <b class="c5"><span class="n">5</span> Type a unit for a one-off.</b>
              <b class="c6 err"><span class="n">6</span> Words don&rsquo;t read. Numbers do.</b>
              <b class="c7"><span class="n">7</span> Customers always see millimetres.</b>
              <b class="c8"><span class="n">8</span> Price tables follow the setting too.</b>
            </div>
          </div>
        </div>
      </div>',
    'body'    => '
      <p>Everything in YourBlinds is stored in <b>millimetres</b> &mdash; every quote line, every price table, every
         factory ticket. This one setting decides what your team <em>types into</em> and <em>reads off</em> the screen.
         It changes the labels and the numbers you see, never the sizes underneath. That&rsquo;s why it is safe to change
         on a wet Tuesday with two hundred quotes already on the system.</p>

      <ul class="steps">
        <li><b>Find it.</b> <b>Settings</b> &rarr; the <b>Quoting</b> tab &rarr; the <b>Measurements</b> block. It sits
            between <b>Default margins</b> and <b>Quote defaults</b>. (Settings opens on <b>Company</b> the first time;
            after that it remembers the tab you were last on.)</li>
        <li><b>Pick the unit.</b> <b>Default measurement unit</b> is a plain dropdown with exactly four choices:
            <b>Millimetres (mm)</b>, <b>Centimetres (cm)</b>, <b>Metres (m)</b>, <b>Inches (in)</b>. Choose the one your
            fitters actually call out on site. Most UK blind shops stay on <b>mm</b>.</li>
        <li><b>Press <em>Save unit</em>.</b> That button saves <em>this block only</em> &mdash; every block on the Settings
            page has its own Save. The page reloads, drops you back on the <b>Quoting</b> tab and shows a green
            <b>&ldquo;Default measurement unit saved.&rdquo;</b> across the top.</li>
        <li><b>Check a quote.</b> Open any quote and look at the add-a-blind row: the <b>Width</b> and <b>Drop</b> labels
            now carry your unit &mdash; <b>Width (mm)</b>, <b>Width (cm)</b>, <b>Width (m)</b>, or <b>Width (&quot;)</b>
            for inches &mdash; and the placeholder says <b>&ldquo;Width in &hellip;&rdquo;</b> to match.</li>
      </ul>

      <p><b>How the numbers are shown.</b> Each unit has its own precision, so a 1500&nbsp;mm blind reads as
         <b>1500 mm</b>, <b>150 cm</b>, <b>1.5 m</b> or <b>59.06&quot;</b> &mdash; whole numbers for millimetres, one
         decimal for centimetres, three for metres, two for inches. Trailing zeros are trimmed, so you get
         <b>1.5 m</b> and not <b>1.500 m</b>.</p>

      <p><b>One job in a different unit.</b> On the quote builder there is a second dropdown,
         <b>Measurement unit (this quote)</b>, with the same four choices and the note
         <em>&ldquo;Re-displays this quote&rsquo;s sizes in the chosen unit.&rdquo;</em> Change it and the page reloads
         with <em>every</em> line on that quote re-shown in the new unit, and it sticks to that quote from then on &mdash;
         handy for a customer who works in feet and inches. If the quote is already locked you&rsquo;ll get
         <b>&ldquo;Quote is locked &mdash; reopen it to change the measurement unit.&rdquo;</b> &mdash; reopen it and try
         again. <b>InstaPrice</b> has the same switcher; it simply starts on the company default because it has no quote
         of its own, and whatever you leave it on is stamped onto the quote if you turn that price into one.</p>

      <p><b>One <em>blind</em> in a different unit.</b> You don&rsquo;t even need the switcher. Type the unit straight into
         the size box &mdash; <code>60in</code>, <code>60&quot;</code>, <code>1.5m</code>, <code>61.5cm</code>,
         <code>1500mm</code> &mdash; and that wins over every setting, for that box alone. So a metres shop can still take
         one odd American blind without touching anything. In <b>InstaPrice</b> a faint grey line appears under the size
         boxes and reads back what the pricing engine will actually use, in millimetres, e.g.
         <b>&ldquo;Using 1500 &times; 1524 mm&rdquo;</b> &mdash; or <b>&ldquo;Using 1500 mm wide&rdquo;</b> on a width-only
         product and <b>&ldquo;Using 1000 mm drop (per slat)&rdquo;</b> on a per-slat one. Glance at it and you&rsquo;ll
         catch a stray digit before you quote it.</p>

      <div class="heads"><span class="hi">&#9888;</span><div><b>A number with no unit is read in the quote&rsquo;s unit.</b>
         That is the one thing to watch after a change. On an inches quote, <b>1500</b> typed into Width means
         <em>1500 inches</em> &mdash; 38,100&nbsp;mm &mdash; and nothing will stop you, because it is a perfectly valid
         number. Glance at the size on the line you just added; if it looks mad, it is.</div></div>

      <div class="oops"><b>&ldquo;Could not read width &quot;two metres&quot;.&rdquo;</b> Size boxes take
         <em>numbers</em> (with an optional unit), not words. <code>two metres</code>, <code>approx</code> or a dash gets
         you that red message &mdash; and the matching <b>&ldquo;Could not read drop &hellip;&rdquo;</b> &mdash; and the line
         is not added. Retype it as <code>2m</code> or <code>2000</code>. A genuinely <em>empty</em> box is fine, mind:
         some products price on width alone, and some on drop alone.</div>

      <p><b>Where your unit shows up beyond quoting.</b> Two more screens follow the <em>company</em> default (not the
         per-quote one). On a <b>width-only</b> price table the list&rsquo;s header reads <b>Width (&quot;)</b> next to
         <b>Price (&pound;)</b>; on a <b>per-slat</b> one it reads <b>Drop (&quot;)</b> next to
         <b>Price per slat (&pound;)</b>. Either way the empty boxes prompt <b>&ldquo;e.g. 31.5&rdquo;</b> for inches,
         <b>&ldquo;e.g. 0.8&rdquo;</b> for metres, <b>&ldquo;e.g. 80&rdquo;</b> for centimetres,
         <b>&ldquo;e.g. 800&rdquo;</b> for millimetres &mdash; type 31.5 in an inches shop and 800&nbsp;mm is what gets
         stored. And a product&rsquo;s <b>&#128065; Live preview</b> drawer labels its boxes
         <b>Dimensions (&quot;)</b> as well. Change the company unit and those columns simply <em>re-label and
         re-display</em>; not one saved price or width moves.</p>

      <p><b>Where it never shows up.</b> The <b>customer&rsquo;s quote PDF</b> and the <b>online quote page</b> print sizes
         as <b>1500 &times; 1000 mm</b> whatever you choose; so do the <b>factory worksheet</b>, the factory order screens
         and the <b>Export CSV</b>, whose column is headed <b>Width (mm)</b>. The big width &times; drop price grid keeps
         its <b>Drop \\ Width (mm)</b> corner too. That is deliberate: the workshop cuts in millimetres, so the paperwork
         it works from stays in millimetres.</p>

      <p><b>If saving fails</b> you&rsquo;ll see a red
         <b>&ldquo;Could not save measurement unit &mdash; has migrate_measurement_unit.php been run?&rdquo;</b>. That is a
         one-off setup step on a brand-new system, not something you&rsquo;ve done wrong &mdash; send that message to
         support and carry on; until it&rsquo;s sorted the system quietly works in millimetres.</p>',
    'script'  => [
        ['0:00', 'Settings → Quoting tab; the Measurements block between Default margins and Quote defaults.',
                 'Open Settings, then the Quoting tab, and look for the block called Measurements. It sits between Default margins and Quote defaults.', 1],
        ['0:11', 'Dropdown open: Millimetres, Centimetres, Metres, Inches — Inches highlighted.',
                 'There is one dropdown, Default measurement unit, and it has four choices. Millimetres, centimetres, metres, or inches. Pick whichever your fitters actually call out on site. Most people stay on millimetres.', 2],
        ['0:26', 'Select now reads Inches (in); Save unit pressed; green banner.',
                 'Then press Save unit. That button saves this block only, and you get a green message across the top saying, default measurement unit saved. That is the whole setting.', 3],
        ['0:40', 'Quote builder: Measurement unit (this quote) = Inches; Width and Drop labelled in inches.',
                 'Now every quote opens in your unit. The width and drop boxes carry it in their labels. And there is a second dropdown on the quote itself, measurement unit, this quote, if one customer wants a different one. Underneath, the size is still fifteen hundred by a thousand millimetres.', 4],
        ['0:58', 'InstaPrice: Width typed as 1.5m, Drop as 60in; the grey line underneath reads Using 1500 × 1524 mm.',
                 'For a single odd blind you do not even need the dropdown. Type the unit straight into the box. One point five m, or sixty in. A typed unit always wins, just for that box. InstaPrice even reads the answer back to you in millimetres, so you can spot a stray digit.', 5],
        ['1:16', 'Red banner: Could not read width "two metres". Amber warning about bare numbers.',
                 'The boxes take numbers, not words. Type two metres in letters and you get, could not read width, and the line is not added. Type two m, or two thousand, instead. And do watch this one, a number with no unit is read in the quote\'s unit. On an inches quote, fifteen hundred means fifteen hundred inches.', 6],
        ['1:36', 'Customer PDF, online quote, factory worksheet and CSV export all showing mm.',
                 'Your customers never see this setting. The quote they receive, the online quote page, the factory worksheet and the exported spreadsheet all print millimetres, whatever you choose. The workshop cuts in millimetres, so its paperwork stays in millimetres.', 7],
        ['1:52', 'Price table width-only list headed Width (") with the e.g. 31.5 prompt.',
                 'One last place. Your width only and per slat price tables follow the company unit as well. The column re-labels itself and the little prompt changes to match. Nothing you have already saved moves, it is only shown a different way.', 8],
    ],
];
