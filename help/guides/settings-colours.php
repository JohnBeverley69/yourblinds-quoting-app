<?php
declare(strict_types=1);

/**
 * Guide: settings-colours
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
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
];
