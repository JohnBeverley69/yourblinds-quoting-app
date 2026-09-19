<?php
declare(strict_types=1);

/**
 * Guide: settings-backup
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
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
];
