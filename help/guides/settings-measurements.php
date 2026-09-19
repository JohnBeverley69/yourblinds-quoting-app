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
];
