<?php
declare(strict_types=1);

/**
 * Guide: products-import-fabrics
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, css, demo, body, script (and optionally js).
 */

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Importing fabrics',
        'eyebrow' => 'Products',
        'blurb'   => 'Add fabrics from a template — one product or many — and fix the row errors.',
        'lede'    => 'Add a product&rsquo;s fabrics from a spreadsheet instead of one by one. Use the template, and if a row&rsquo;s
                      missing its <b>band</b> or <b>name</b>, the import tells you exactly which.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .ldesc2{ color:var(--soft); font-size:.8rem; margin:0 0 .75rem; }
          .gd .tmplbtn{ display:inline-flex; align-items:center; gap:.35rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px; padding:.3rem .6rem; font-size:.78rem; font-weight:600; color:var(--soft); background:var(--surface); }
          .gd .cols{ font-size:.76rem; color:var(--soft); margin:.6rem 0 .7rem; }
          .gd .cols b{ color:var(--ink); }
          .gd .filebox{ display:inline-flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:7px; padding:.3rem .5rem; background:var(--surface); font-size:.8rem; }
          .gd .choosebtn{ border:1px solid var(--border-strong,#c7ccd4); border-radius:6px; padding:.2rem .55rem; background:var(--panel); color:var(--ink); font-size:.76rem; }
          .gd .fn{ color:var(--soft); }
          .gd .impbtn{ margin-top:.75rem; display:inline-flex; background:var(--accent); color:#fff; border-radius:8px; padding:.4rem .8rem; font-size:.8rem; font-weight:700; }
          .gd .stage[data-step="2"] .impbtn, .gd .stage[data-step="3"] .impbtn{ transform:scale(.97); filter:brightness(1.1); }
          .gd .err-row{ display:none; margin-top:.75rem; }
          .gd .stage[data-step="2"] .err-row{ display:flex; }
          .gd .ok-fab{ display:none; margin-top:.75rem; }
          .gd .stage[data-step="3"] .ok-fab{ display:flex; }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / import-fabrics</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <a>Dashboard</a><a>Calendar</a><a>Customers</a><a class="on">Products</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">
                <div class="card-t">Import fabrics &mdash; Roller Blind</div>
                <p class="ldesc2">Fill the template, then upload. Band and name are required; colour, supplier and code are optional.</p>
                <div class="tmplbtn">&#11015; Download blank template (.xlsx)</div>
                <div class="cols">Columns: <b>Band*</b> &middot; <b>Fabric name*</b> &middot; Colour &middot; Supplier &middot; Code</div>
                <div class="fld"><label>Filled template (.xlsx)</label>
                  <div class="filebox"><span class="choosebtn">Choose File</span> <span class="fn">fabrics.xlsx</span></div></div>
                <div class="impbtn">Upload &amp; import</div>
                <div class="errbanner err-row"><span>&#9888;</span><div><b>Some rows had problems:</b> Row 3: missing band</div></div>
                <div class="okbanner ok-fab"><span>&check;</span> Imported 12 fabrics. Skipped 1 duplicate.</div>
                <div class="caps">
                  <b class="c1"><span class="n">1</span> Fill the template &mdash; Band + name required.</b>
                  <b class="c2 err"><span class="n">2</span> A row missing its Band? It names it.</b>
                  <b class="c3 good"><span class="n">3</span> Fix it, re-import &mdash; fabrics in.</b>
                </div>
              </div>
            </div>
          </div>',
        'body'    => '
          <p>A product&rsquo;s fabrics are its <b>colour / material choices</b> (its extras &mdash; controls, cassettes and the like &mdash;
             are separate <em>options</em>, covered in <em>Adding options</em>). Add fabrics from a spreadsheet rather than one at a time.</p>
          <ul class="steps">
            <li><b>For one product</b> &mdash; on its <b>Fabrics</b> page, <b>Import from Excel</b>. Download the blank <b>template</b>,
                fill it in (columns <b>Band*</b>, <b>Fabric name*</b>, Colour, Supplier, Code &mdash; * = required), and upload.
                Duplicate rows (band + name + colour) are skipped automatically.</li>
            <li><b>For lots of products at once</b> &mdash; the <b>Bulk import fabrics</b> button on the Products page reads a workbook
                with <b>one sheet per product</b> (columns Name, Colour, Band). Each sheet is matched to a product; you can <b>pick
                several</b> (Ctrl/Cmd-click) so one shared range feeds every blind that uses it.</li>
          </ul>
          <p><b>The Band is yours to name.</b> Whatever you type in the <b>Band</b> column is the band &mdash; <b>name it for what it is</b>
             (<em>Plain</em>, <em>Blackout</em>, <em>Special effects</em>), you don&rsquo;t have to use A/B/C. Just keep it <b>identical</b> to
             the band on the matching price table (see the heads-up below).</p>
          <div class="oops"><b>&ldquo;Row 3: missing band&rdquo; (or missing name)?</b> Every fabric needs a <b>band</b> and a <b>name</b> &mdash;
             the import lists the exact rows that don&rsquo;t, and the good rows still go in. Fill the gaps and re-import. Always use the
             <b>.xlsx template</b> &mdash; a hand-made CSV with everything crammed into one column won&rsquo;t map to the Band/Name/Colour
             columns and every row will be rejected.</div>
          <p><b>Just a few to add?</b> You don&rsquo;t need a spreadsheet at all &mdash; the wizard&rsquo;s <b>Fabrics</b> step lets you type a
             band and <b>paste the names straight in, one per line or comma-separated</b>, and leave <b>Available on</b> blank for every
             system (or pick one to tie the band to a single system).</p>
          <p>Once a product has at least one fabric and a price table, its <b>&ldquo;Needs fabric&rdquo;</b> flag clears and it&rsquo;s ready to
             quote. Remember the <b>band on the fabric must match a price-table band</b> exactly, or it shows no price.</p>',
        'script'  => [
            ['0:00', 'Import form; template + columns.', 'Add your fabrics from a spreadsheet. Download the template — Band and name are required, colour and code optional.', 1],
            ['0:08', 'Error: Row 3 missing band.',       'Leave a band off a row and it tells you which — some rows had problems, row 3, missing band.', 2],
            ['0:15', 'Imported 12 fabrics.',             'Fill that band in, re-import, and your fabrics are in — and the product\'s "needs fabric" flag clears.', 3],
        ],
];
