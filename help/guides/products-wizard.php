<?php
declare(strict_types=1);

/**
 * Guide: products-wizard
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Setting up a product (wizard)',
        'eyebrow' => 'Products',
        'blurb'   => 'The setup wizard: Name → Systems → Fabrics → Price tables, start to finish.',
        'lede'    => 'A new product in four guided steps &mdash; <b>Name</b>, <b>Systems</b>, <b>Fabrics</b>, <b>Price tables</b>.
                      The wizard walks you through each one (and remembers where you got to); here&rsquo;s the whole flow.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .stepper{ display:flex; flex-wrap:wrap; gap:.4rem .7rem; margin-bottom:.9rem; }
          .gd .wstep{ display:inline-flex; align-items:center; gap:.35rem; font-size:.72rem; color:var(--faint); font-weight:600; }
          .gd .wstep .num{ width:18px; height:18px; border-radius:50%; background:var(--line); color:var(--soft); display:inline-flex; align-items:center; justify-content:center; font-size:.64rem; }
          .gd .stage[data-step="0"] .ws1, .gd .stage[data-step="1"] .ws1, .gd .stage[data-step="2"] .ws2, .gd .stage[data-step="3"] .ws3, .gd .stage[data-step="4"] .ws4{ color:var(--ink); }
          .gd .stage[data-step="0"] .ws1 .num, .gd .stage[data-step="1"] .ws1 .num, .gd .stage[data-step="2"] .ws2 .num, .gd .stage[data-step="3"] .ws3 .num, .gd .stage[data-step="4"] .ws4 .num{ background:var(--accent); color:#fff; }
          .gd .plbl2{ font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; color:var(--faint); font-weight:700; margin-bottom:.4rem; }
          .gd .wpanel{ display:none; }
          .gd .stage[data-step="0"] .p1, .gd .stage[data-step="1"] .p1, .gd .stage[data-step="2"] .p2, .gd .stage[data-step="3"] .p3, .gd .stage[data-step="4"] .p4{ display:block; }
          .gd .nextbtn{ margin-top:.8rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.4rem .85rem; font-size:.8rem; font-weight:700; }
          .gd .addedrow{ display:flex; align-items:center; gap:.4rem; border:1px solid var(--line); border-radius:7px; padding:.35rem .55rem; font-size:.78rem; color:var(--ink); background:var(--panel); margin-bottom:.5rem; }
          .gd .addedrow .grn{ color:var(--good); font-weight:700; margin-left:auto; }
          .gd .fablist{ display:flex; flex-direction:column; gap:.35rem; }
          .gd .fabrow{ display:flex; justify-content:space-between; border:1px solid var(--line); border-radius:7px; padding:.3rem .55rem; font-size:.78rem; color:var(--ink); background:var(--panel); }
          .gd .fabrow .bnd{ color:var(--accent-ink); font-weight:700; font-size:.72rem; }
          .gd .pgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:2px; border:1px solid var(--line); border-radius:8px; overflow:hidden; max-width:20rem; }
          .gd .pgrid span{ padding:.25rem; font-size:.68rem; text-align:center; background:var(--panel); color:var(--soft); }
          .gd .pgrid span.h{ background:var(--surface); color:var(--faint); font-weight:700; }
          .gd .pdone{ display:inline-flex; align-items:center; gap:.4rem; margin-top:.6rem; color:var(--good); font-weight:700; font-size:.8rem; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / wizard</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Set up a product</div>
                <div class="stepper">
                  <span class="wstep ws1"><span class="num">1</span> Name</span>
                  <span class="wstep ws2"><span class="num">2</span> Systems</span>
                  <span class="wstep ws3"><span class="num">3</span> Fabrics</span>
                  <span class="wstep ws4"><span class="num">4</span> Price tables</span>
                </div>
                <div class="wpanel p1">
                  <div class="fld"><label>Product name</label><div class="box f1"><span class="ph">e.g. Louvolite Roller Blind</span><span class="val">Louvolite Roller Blind</span></div></div>
                  <div class="fld"><label>What do you call the material this is made of?</label><div class="box f1"><span class="ph">Fabric</span><span class="val">Fabric</span></div></div>
                  <div class="nextbtn">Create &amp; continue &rarr;</div>
                </div>
                <div class="wpanel p2">
                  <div class="plbl2">Systems (variants)</div>
                  <div class="addedrow">Standard <span class="grn">&check; added</span></div>
                  <div class="fld"><label>Add a system</label><div class="box"><span class="ph">e.g. Standard, or a slat size like 25mm</span></div></div>
                  <div class="nextbtn">Next: fabrics &rarr;</div>
                </div>
                <div class="wpanel p3">
                  <div class="plbl2">Fabrics &mdash; add a band, then paste the names</div>
                  <div class="frow">
                    <div class="fld"><label>Band</label><div class="box" style="max-width:8rem"><span class="val">Plain</span></div></div>
                    <div class="fld"><label>Available on</label><div class="selectbox">All systems (optional)</div></div>
                  </div>
                  <div class="fld"><label>Names &mdash; one per line or comma-separated</label><div class="ta"><span class="val">Plain White, Plain Cream, Silver, Anthracite</span></div></div>
                  <div class="fablist">
                    <div class="fabrow"><span>Plain White</span><span class="bnd">Plain</span></div>
                    <div class="fabrow"><span>Plain Cream</span><span class="bnd">Plain</span></div>
                    <div class="fabrow"><span>Silver</span><span class="bnd">Plain</span></div>
                  </div>
                  <div class="nextbtn">Next: price tables &rarr;</div>
                </div>
                <div class="wpanel p4">
                  <div class="plbl2">Price tables &mdash; Band: Plain (width &times; drop)</div>
                  <div class="pgrid">
                    <span class="h">mm</span><span class="h">600</span><span class="h">900</span><span class="h">1200</span>
                    <span class="h">1000</span><span>38</span><span>44</span><span>52</span>
                    <span class="h">1500</span><span>46</span><span>55</span><span>66</span>
                    <span class="h">2000</span><span>58</span><span>70</span><span>84</span>
                  </div>
                  <div style="font-size:.7rem;color:var(--soft);margin-top:.5rem">&#43; your <b>buying discount &amp; markup</b> per system (or inherit your defaults)</div>
                  <div class="pdone">&check; Prices in &mdash; product ready to quote.</div>
                </div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Name it, supplier first &mdash; and the material word.</b>
                  <b class="c2"><span class="n">2</span> Add a system &mdash; a variant, like a slat size.</b>
                  <b class="c3"><span class="n">3</span> Add a band (Plain), paste the names.</b>
                  <b class="c4 good"><span class="n">4</span> Import your price grids &mdash; done.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>The <b>setup wizard</b> builds a product in four steps: <b>Name &rarr; Systems &rarr; Fabrics &rarr; Price tables</b>.
             It nudges you through each one, and you can close the tab and pick up where you left off.</p>
          <ul class="steps">
            <li><b>Name</b> &mdash; name the product, and say <b>what you call the material</b> it&rsquo;s made of. That&rsquo;s
                &ldquo;Fabric&rdquo; for a roller, but for a Venetian it&rsquo;s <em>Colours</em> or <em>Louvres</em> &mdash; not fabric.
                That word is the one your customer sees in the quote builder, so pick the one that fits the product.</li>
            <li><b>Systems</b> &mdash; add at least one <b>system</b> (a variant of the product &mdash; <em>Standard</em>, <em>Motorised</em>,
                or a slat size like <em>25mm</em>). Paste a whole list, <b>one per line</b>. A product can have several.</li>
            <li><b>Fabrics</b> &mdash; add a <b>band</b> first, then paste the names that sit in it. <b>Name the band for what it is</b>
                &mdash; <em>Plain</em>, <em>Blackout</em>, <em>Special effects</em> &mdash; you don&rsquo;t have to use A/B/C. Paste the names
                <b>one per line OR comma-separated</b> &mdash; whichever your list is in. <b>Available on</b> is optional: leave it blank and
                the band applies to every system, or pick one system to tie it down tight. (Some products &mdash; e.g. headrails &mdash; have
                no fabric; tick &ldquo;no fabric&rdquo; and skip this step.)</li>
            <li><b>Price tables</b> &mdash; import your <b>width &times; drop price grids</b>, one per band. This is what makes the product
                quotable. Setting up prices also means choosing the <b>pricing source</b> (our list vs a supplier&rsquo;s) and your
                <b>buying discount &amp; markup per system</b> &mdash; see <em>Pricing: source, markup &amp; mode</em> and
                <em>Building price tables</em> for the fast paste-from-Excel method.</li>
          </ul>
          <div class="heads"><span class="hi">&#9888;</span><div><b>Start the product name with the supplier.</b> The supplier acts like a
             <b>prefix</b> &mdash; at price-update time the system finds a product&rsquo;s prices by that prefix, so a name like
             <em>Louvolite Roller Blind</em> updates cleanly when Louvolite send a new price list. Forget it? Don&rsquo;t panic &mdash; it&rsquo;s
             easily changed afterwards; but getting it right up front saves work later.</div></div>
          <p><b>Tip:</b> on the Fabrics step there&rsquo;s a <em>&ldquo;Price tables first &rarr;&rdquo;</em> shortcut &mdash; import your
             grids first and the <b>bands auto-suggest</b> in the fabric&rsquo;s Band box, so you don&rsquo;t type band names twice.</p>
          <div class="heads"><span class="hi">&#9888;</span><div><b>The #1 thing to get right: the band must match.</b> A fabric&rsquo;s
             <b>band</b> is what links it to its prices &mdash; the <b>same band name must appear on the fabric AND on a price table</b>.
             Put a fabric on &ldquo;Plain&rdquo; but only import a price table for &ldquo;Special effects&rdquo;, and that fabric shows <b>no price</b>
             (or &ldquo;Needs fabric&rdquo;) in the quote builder. Bands are case- and spelling-sensitive, so &ldquo;Plain&rdquo; and
             &ldquo;plain&rdquo; are treated as different. Using the <em>&ldquo;Price tables first&rdquo;</em> shortcut avoids this by
             offering you the exact band names to pick.</div></div>
          <p>Once all four are done, the product is <b>ready to quote</b> &mdash; the last touch is its <b>options</b> (the extras a
             customer picks, like controls or a cassette): see <em>Adding options</em>. Separate guides also cover <b>pricing modes</b>,
             <b>building price tables</b>, <b>importing fabrics</b>, and <b>combining products</b> &mdash; and those show the common
             input errors and how to fix them.</p>',
        'script'  => [
            ['0:00', 'Name + material word.',            'The wizard sets up a product in four steps. Name it — and start the name with the supplier, like Louvolite Roller Blind, because price updates find it by that prefix. Then say what you call the material: Fabric for a roller, Colours for slats.', 1],
            ['0:13', 'Systems step; Standard added.',    'Add a system — a variant of the product, like Standard, Motorised, or a slat size. Paste a whole list, one per line.', 2],
            ['0:21', 'Fabrics; band Plain, names pasted.', 'Next, add a band — name it for what it is, like Plain — and paste the names, one per line or comma-separated. Leave "available on" blank for every system, or pick one to tie it down.', 3],
            ['0:31', 'Price tables; discount & markup.', 'And finally, the prices — import your width-by-drop grids, one per band, and set your buying discount and markup per system. That\'s the product ready to quote.', 4],
        ],
];
