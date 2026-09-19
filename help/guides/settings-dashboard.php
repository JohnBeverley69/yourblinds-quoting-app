<?php
declare(strict_types=1);

/**
 * Guide: settings-dashboard
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
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
];
