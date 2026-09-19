<?php
declare(strict_types=1);

/**
 * Guide: products-combine
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Combining products into one',
        'eyebrow' => 'Products',
        'blurb'   => 'Fold separate sizes (15/25/35mm) into one product with a system for each.',
        'lede'    => 'Imported a family as separate products (e.g. <em>15/25/35mm Venetian</em>)? <b>Combine</b> folds them into one
                      product with a <b>system</b> for each size &mdash; their fabrics and prices come along.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.8rem; margin:0 0 .75rem; }
          .gd .ctable{ margin-top:.8rem; border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.78rem; }
          .gd .ct-head, .gd .ct-row{ display:grid; grid-template-columns:1.3fr 1.2fr .55fr; gap:.5rem; padding:.35rem .55rem; align-items:center; }
          .gd .ct-head{ background:var(--panel); font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; }
          .gd .ct-row{ border-top:1px solid var(--line); color:var(--ink); }
          .gd .masterpill{ display:inline-block; padding:.08rem .45rem; border-radius:999px; background:var(--accent-wash); color:var(--accent-ink); font-size:.6rem; font-weight:700; text-transform:uppercase; }
          .gd .sysin{ border:1px solid var(--line); border-radius:6px; padding:.15rem .4rem; background:var(--panel); font-size:.74rem; color:var(--ink); }
          .gd .cmbbtn{ margin-top:.85rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.42rem .9rem; font-size:.82rem; font-weight:700; }
          .gd .stage[data-step="3"] .cmbbtn{ transform:scale(.97); filter:brightness(1.12); }
          .gd .ok-cmb{ display:none; margin-top:.85rem; }
          .gd .stage[data-step="3"] .ok-cmb{ display:flex; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / combine</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Combine into one product</div>
                <p class="ldesc2">The first product becomes the master; the rest fold in as systems.</p>
                <div class="fld"><label>Master product name <span class="req">*</span></label>
                  <div class="box f1"><span class="ph">e.g. Metal Venetian</span><span class="val">Metal Venetian</span></div></div>
                <div class="ctable">
                  <div class="ct-head"><span>Product</span><span>Becomes system</span><span>Fabrics</span></div>
                  <div class="ct-row"><span>15mm Venetian</span><span class="masterpill">Master</span><span>42</span></div>
                  <div class="ct-row"><span>25mm Venetian</span><span><span class="sysin">25mm</span></span><span>38</span></div>
                  <div class="ct-row"><span>35mm Venetian</span><span><span class="sysin">35mm</span></span><span>40</span></div>
                </div>
                <div class="cmbbtn">Combine into one product</div>
                <div class="okbanner ok-cmb"><span>&check;</span><div>Combined into &ldquo;Metal Venetian&rdquo; with 3 systems. The others are now empty &amp; deactivated &mdash; delete once checked.</div></div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> First one&rsquo;s the master &mdash; name it.</b>
                  <b class="c2"><span class="n">2</span> The rest become systems &mdash; 25mm, 35mm.</b>
                  <b class="c3 good"><span class="n">3</span> Combined &mdash; delete the empty husks.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>Imported a family as separate products (e.g. <em>15/25/35mm Venetian</em>) and want them under one? <b>Combine</b> folds
             them into a single product with a <b>system</b> for each size.</p>
          <ul class="steps">
            <li>On the <b>Products</b> list, <b>tick</b> the ones to combine, then press <b>&ldquo;Combine into product&hellip;&rdquo;</b>.
                The <b>first one you ticked becomes the master</b> (it keeps its group and settings).</li>
            <li>Name the <b>master</b> (e.g. <em>Metal Venetian</em>) and give each other one its <b>system</b> name (15mm, 25mm&hellip;).
                Their fabrics, price tables and settings move across.</li>
            <li>The folded-in products are <b>deactivated</b> &mdash; empty husks, nothing lost. <b>Delete them</b> once you&rsquo;ve
                checked the master looks right.</li>
          </ul>
          <div class="oops"><b>&ldquo;These products are priced differently&hellip; can&rsquo;t be systems of one product&rdquo;?</b> Everything you
             combine must share the <b>same pricing mode</b> (all width&times;drop, or all per-slat, etc.) &mdash; you can&rsquo;t mix a
             per-slat product in with grid ones. Untick the odd one out. (You&rsquo;ll also be asked to give the master and each system a
             name of 1&ndash;150 characters.)</div>
          <p><b>Adding more later:</b> tick the existing master <b>first</b>, then the new single-size product(s), and Combine again
             &mdash; they&rsquo;re appended as extra systems.</p>',
        'script'  => [
            ['0:00', 'Master name fills.',            'Combining sizes into one product — tick them on the products list, then here name the master. The first one becomes it.', 1],
            ['0:07', 'Rows become systems.',          'The others fold in as systems — 25mm, 35mm — carrying their fabrics and prices across.', 2],
            ['0:14', 'Combined; husks deactivated.',  'Combine, and it\'s one product with three systems. The old ones are emptied and switched off — delete them once you\'ve checked.', 3],
        ],
];
