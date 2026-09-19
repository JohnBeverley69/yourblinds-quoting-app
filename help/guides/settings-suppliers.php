<?php
declare(strict_types=1);

/**
 * Guide: settings-suppliers
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
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
];
