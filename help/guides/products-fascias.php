<?php
declare(strict_types=1);

/**
 * Guide: products-fascias — "Fascias: sizing, several blinds on one fascia,
 * and joins" (v2 player). Its own help group ("Fascias"), at John's request
 * (2026-10-01): fascia pricing has enough rules to need a guide of its own.
 *
 * Mirrors:
 *  - quote-builder/edit.php — the roller fascia options above Width / Drop
 *    (renderExtras: single-pick <select>s, children nested with ↳, a number-only
 *    option as a label + "mm" + number box), fasciaSizingMode() /
 *    applyFasciaSizingUI() (Width reads "multi blind"), the "Blinds in this
 *    fascia" panel + fit check (runMultiPreview), the joined note on the price
 *    line ("<choice> joined: 2 × 2000 mm").
 *  - quote-builder/add_item.php — the server fit check ("These blinds won't
 *    fit: …") and the fan-out into one line per blind.
 *  - _partials/multi_fascia_pricer.php — carrier = first blind keeps the fascia
 *    at the whole width; the others go to "No Fascia"; each blind priced at its
 *    own width and drop; fits = sum of widths <= typed Fascia Width.
 *  - _partials/pricing_engine.php — the width-source override stays inside the
 *    Fascia Options family (pe_extra_root), so bottom bar / chain price at each
 *    blind's own width; a joinable option past its width table is split into
 *    the fewest EQUAL pieces, each charged at its length (no joint charge).
 *  - admin/products/extra-edit.php — the "Can be joined when longer than its
 *    width table" tick and its neighbours.
 *  - factory/worksheet-print.php — Fascia_Width (typed, else the blind width)
 *    feeds Fascia_Cut; a shared fascia is cut once, on the carrier's label.
 * Option captions are those in the roller data ("To Fit Blind", "Over Size For
 * Single Blind", "Multi Blind", "Fascia Width", "Number Of Blinds?",
 * "Blind 1".."Blind 5"); the form keys off codes, so captions can be renamed.
 *
 * v2: one SCENE per script line. Beat times come from where the matching words
 * fall in the voice-over ($at); data-len = line length ÷ 13.6.
 */

// ── The voice-over, by step ───────────────────────────────────────────
$S = [
    1 => ['1', 'Fascia Options',
        'A fascia is the cover across the top of a roller blind. In the quote builder it sits above Width and Drop, because you decide it first. Open the Fascia Options dropdown, and pick a fascia. Say, L L seventy millimetre Cassette. Leave it on No Fascia, and nothing more appears. Pick a real fascia, and a second dropdown opens up underneath it, called Fascia Sizing.'],
    2 => ['2', 'To Fit Blind',
        'Fascia Sizing has three choices. To Fit Blind is already picked for you. It means the fascia is made to the width of the blind, so there is nothing else to do. Just type the Width and the Drop as normal. The fascia is priced and cut at the blind\'s own width. Most of your blinds will stay on To Fit Blind.'],
    3 => ['3', 'Over size for one blind',
        'Sometimes the fascia must be wider than the blind. Say the blind is fifteen hundred wide, but the fascia runs on to seventeen hundred, past a skirting board. Choose Over Size For Single Blind, and a Fascia Width box appears. Type the fascia width in millimetres. The fascia is then priced at that width, and cut to it on the roll label. The blind itself is still made to its own width.'],
    4 => ['4', 'Several blinds, one fascia',
        'Choose Multi Blind when several blinds share one long fascia. Number Of Blinds asks how many, from two up to five. Then a box appears for each blind\'s width. Blind 1, Blind 2, and so on. The Width box further down now reads multi blind, because each blind has its own. Type the whole fascia width in Fascia Width, and one Drop for them all.'],
    5 => ['5', 'One blind carries the fascia',
        'A panel called Blinds in this fascia lists every blind, with its width, drop and price. The first blind is the carrier. It carries the fascia, at the full fascia width, so the fascia is only charged once. The other blinds are set to No Fascia. A blind can have its own drop, or leave it on same. When you save, each blind becomes a line of its own on the quote.'],
    6 => ['6', 'Do they fit?',
        'The blinds must fit inside the fascia. The panel adds up their widths, and checks them against the Fascia Width. If they fit, you get a green tick and the word Fits. If they come to more, it says Won\'t fit, in red, and Save blind stays greyed out until you make a blind narrower, or the fascia wider. Leave Fascia Width empty, and it reminds you to enter one.'],
    7 => ['7', 'Bottom bars and chains',
        'Only the fascia itself, and its colours, use the fascia width. Everything else on each blind is priced at that blind\'s own width. So the bottom bar and the chain on a fifteen hundred blind are priced at fifteen hundred, even under a four metre fascia. The fascia is charged once on the carrier, and each blind pays for its own bar and chain. That is why the prices in the panel differ.'],
    8 => ['8', 'Letting long fascias be joined',
        'Some fascias come longer than the longest length in their price table. You can let them be joined, rather than refused. Open the product, click the Options tile, and click Edit beside Fascia Options. Tick Can be joined when longer than its width table, and press Save changes. Leave it unticked, and a fascia that is too long stops the quote with an error.'],
    9 => ['9', 'How a join is priced',
        'Here is how a join is priced. The fascia is split into equal pieces, normally two, joined in the middle. Each piece is charged at its own length. Say the Senses table stops at thirty five hundred. Then a four thousand Senses fascia is charged as two pieces at the two thousand price. There is no charge for the joint itself. The price line says joined, two times two thousand millimetres.'],
];

/** "Ns" — when the words $p are spoken in line $n (13.6 characters a second). */
$at = static function (int $n, string $p, float $plus = 0.0) use ($S): string {
    $i = mb_strpos($S[$n][2], $p);
    if ($i === false) { $GLOBALS['gd_at_miss'][] = "$n: $p"; $i = 0; }
    return round($i / 13.6 + $plus, 1) . 's';
};
$len = static fn (int $n): string => (string) max(8, (int) round(mb_strlen($S[$n][2]) / 13.6));

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

// ── Little builders for the quote-builder mock ────────────────────────
/** A dropdown, drawn like the real <select>. */
$sb = static fn (string $inner, string $cls = '', string $st = ''): string =>
    '<span class="sb ' . $cls . '" style="' . $st . '">' . $inner . '</span>';
/** Old text out, new text in, in one cell (at time $t). */
$swap = static fn (string $old, string $new, string $t): string =>
    '<span class="sw"><span class="a-out" style="--d:' . $t . '">' . $old . '</span><span class="a-fade" style="--d:' . $t . '">' . $new . '</span></span>';
/** A number-only option: name, the small "mm" label, the box. */
$num = static fn (string $name, string $val, string $cls = '', string $st = '', bool $child = true): string =>
    '<div class="' . ($child ? 'xch ' : '') . $cls . '" style="' . $st . '"><label>' . $name . '</label>'
  . '<small class="uv">mm</small><span class="ib">' . $val . '</span></div>';
/** A tick box, drawn like the real checkbox; $on = ticked. */
$tk = static fn (bool $on): string => '<span class="tick' . ($on ? ' on' : '') . '">&#10003;</span>';
/** The size row under the options. */
$sizes = static fn (string $w, string $d, string $wCls = ''): string =>
    '<div class="szr"><div><label>Width (mm) <i>*</i></label><span class="ib ' . $wCls . '">' . $w . '</span></div>'
  . '<div><label>Drop (mm) <i>*</i></label><span class="ib">' . $d . '</span></div>'
  . '<div><label>Quantity</label><span class="ib">1</span></div></div>';
/** The open list of a dropdown. */
$dd = static fn (array $opts, string $pick, string $st): string =>
    '<div class="dd a-mid" style="' . $st . '">' . implode('', array_map(
        static fn ($o) => '<span' . ($o === $pick ? ' class="pk"' : '') . '>' . $o . '</span>', $opts)) . '</div>';

$FASCIAS = ['Senses', 'LL 70mm Cassette', 'LL 40mm Cassette', 'Grip Fix Cassette', 'No Fascia'];
$SIZING  = ['To Fit Blind', 'Over Size For Single Blind', 'Multi Blind'];

/** The "Blinds in this fascia" panel. $rows = [[width, drop, price, carrier?], …]. */
$panel = static function (array $rows, string $fit, string $fitCls, string $total, float $t0 = 0.0, float $step = 0.0): string {
    $h = '<div class="mp"><div class="mbh">Blinds in this fascia</div><table><thead><tr><th>Blind</th><th>Width</th><th>Drop</th><th class="r">Price</th></tr></thead><tbody>';
    foreach ($rows as $i => [$w, $d, $p, $car]) {
        $cls = $step > 0 ? ' class="a-fly" style="--d:' . round($t0 + $i * $step, 1) . 's"' : '';
        $h .= '<tr' . $cls . '><td>Blind ' . ($i + 1) . '</td><td>' . $w . ' mm' . ($car ? ' &middot; fascia' : '') . '</td>'
            . '<td><span class="mdrop">' . $d . '</span></td><td class="r">&pound;' . $p . '</td></tr>';
    }
    return $h . '</tbody><tfoot><tr><td colspan="2" class="fit ' . $fitCls . '">' . $fit . '</td><td class="r">Total</td><td class="r">&pound;' . $total . '</td></tr></tfoot></table></div>';
};
$ROWS = [['1500', '<span class="ph">same</span>', '142.60', true], ['1500', '<span class="ph">same</span>', '88.20', false], ['1000', '<span class="ph">same</span>', '71.40', false]];

return [
        'aud'     => 'admin',
        'section' => 'Fascias',
        'title'   => 'Fascias: sizing, several blinds on one fascia, and joins',
        'eyebrow' => 'Fascias',
        'v'       => 2,
        'blurb'   => 'Roller fascias from start to finish: Fascia Options and Fascia Sizing, an over-size fascia, several blinds under one fascia with the fit check, why bottom bars and chains stay on each blind\'s own width, and joining a fascia longer than its price table.',
        'lede'    => 'The <b>fascia</b> is the cover across the top of a roller blind, and it has a few rules of its own. This guide
                      takes them one at a time: picking the fascia and its <b>Fascia Sizing</b>, a fascia <b>wider than the blind</b>,
                      <b>several blinds sharing one fascia</b> (one blind carries it, the rest say <b>No Fascia</b>), the
                      <b>fit check</b>, and <b>joining</b> a fascia that is longer than its price table. You will find the fascia
                      options in the quote builder, above <b>Width</b> and <b>Drop</b>, on a roller product. The joining tick is
                      on the product: <b>Products</b> &rarr; the product &rarr; <b>Options</b> &rarr; <b>Edit</b> beside <b>Fascia Options</b>.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:470px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .25rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .two{ display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,1fr); gap:1rem; align-items:start; }

          /* the quote-builder option cell, children nested with a left rule */
          .gd .cell{ display:flex; flex-direction:column; gap:.32rem; background:var(--panel); border:1px solid var(--line);
                     border-radius:10px; padding:.6rem .7rem; max-width:21rem; position:relative; }
          .gd .cell label{ display:block; font-size:.7rem; font-weight:600; color:var(--soft); margin-bottom:.18rem; }
          .gd .xch{ margin-left:.55rem; padding-left:.55rem; border-left:2px solid var(--border-strong,#c7ccd4); }
          .gd .xch > label{ font-size:.66rem; color:var(--faint); }
          .gd .xch > label::before{ content:"\21B3  "; }
          .gd .sb{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; border:1px solid var(--border-strong,#c7ccd4);
                   border-radius:7px; padding:.24rem .55rem; font-size:.76rem; color:var(--ink); background:var(--surface); }
          .gd .sb::after{ content:"\25BE"; color:var(--faint); font-size:.66rem; }
          .gd .sw{ display:inline-grid; } .gd .sw > span{ grid-area:1/1; white-space:nowrap; }
          .gd .uv{ display:block; font-size:.58rem; font-weight:600; color:var(--faint); text-transform:uppercase; letter-spacing:.05em; margin:-.1rem 0 .08rem; }
          .gd .ib{ display:flex; align-items:center; min-height:1.5rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:7px;
                   background:var(--surface); padding:0 .55rem; font-size:.78rem; color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .ib.lock{ font-style:italic; color:var(--faint); }
          .gd .ph{ color:var(--faint); }
          .gd .dd{ position:absolute; z-index:5; left:.7rem; right:.7rem; top:3.1rem; background:var(--surface); border:1px solid var(--line);
                   border-radius:8px; box-shadow:var(--gd-shadow); padding:.2rem 0; font-size:.74rem; }
          .gd .dd span{ display:block; padding:.22rem .6rem; color:var(--ink); }
          .gd .dd span.pk{ background:var(--accent); color:#fff; }
          .gd .szr{ display:grid; grid-template-columns:1fr 1fr .7fr; gap:.5rem; max-width:21rem; margin-top:.65rem; }
          .gd .szr label{ display:block; font-size:.66rem; font-weight:600; color:var(--soft); margin-bottom:.15rem; }
          .gd .szr i{ color:#b91c1c; font-style:normal; }

          /* notes on the right */
          .gd .chips{ display:flex; flex-direction:column; gap:.45rem; align-items:flex-start; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .chip.ok{ border-color:var(--good); color:var(--good); }
          .gd .chip.bad{ border-color:var(--err); color:var(--err); }
          .gd .chip.acc{ border-color:var(--accent); }

          /* the price box under the form */
          .gd .pv{ border-radius:8px; padding:.45rem .65rem; font-size:.74rem; margin-top:.6rem; max-width:21rem; }
          .gd .pv.ok{ background:var(--good-wash); color:var(--ink); border:1px solid color-mix(in srgb,var(--good) 30%,transparent); }
          .gd .pv.idle{ background:var(--panel); color:var(--soft); border:1px solid var(--line); }
          .gd .btnp{ display:inline-flex; align-items:center; gap:.3rem; background:var(--accent); color:#fff; border-radius:7px;
                     padding:.34rem .8rem; font-size:.74rem; font-weight:700; }
          .gd .btnp.off{ background:var(--line); color:var(--faint); }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .7rem; font-size:.72rem; font-weight:600; }

          /* "Blinds in this fascia" panel */
          .gd .mp{ border:1px solid var(--line); border-radius:8px; padding:.45rem .6rem; background:var(--surface); font-size:.72rem; max-width:24rem; }
          .gd .mp .mbh{ font-weight:700; margin-bottom:.3rem; color:var(--ink); }
          .gd .mp table{ width:100%; border-collapse:collapse; }
          .gd .mp th, .gd .mp td{ padding:.18rem .3rem; text-align:left; border-bottom:1px solid var(--line); color:var(--ink); font-variant-numeric:tabular-nums; }
          .gd .mp th{ font-size:.64rem; color:var(--soft); }
          .gd .mp .r{ text-align:right; }
          .gd .mp tfoot td{ border-bottom:0; font-weight:700; }
          .gd .mp .mdrop{ display:inline-block; min-width:3.2rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:4px; padding:0 .25rem; font-size:.68rem; }
          .gd .mp .fit.okk{ color:#127a29; } .gd .mp .fit.bad{ color:#b00020; }
          :root[data-theme="dark"] .gd .mp .fit.okk{ color:#34d399; } :root[data-theme="dark"] .gd .mp .fit.bad{ color:#f87171; }
          .gd .fitsw{ display:inline-grid; } .gd .fitsw > span{ grid-area:1/1; }

          /* the opening drawn to scale-ish: fascia across, blinds under */
          .gd .open{ position:relative; max-width:24rem; margin:.4rem 0 .2rem; }
          .gd .fas{ height:1.35rem; border-radius:4px; background:#1f3b5b; color:#fff; font-size:.64rem; font-weight:700;
                    display:flex; align-items:center; justify-content:center; letter-spacing:.03em; }
          .gd .bl{ display:flex; gap:3px; margin-top:3px; }
          .gd .bl > div{ display:flex; flex-direction:column; gap:2px; }
          .gd .bl .cl{ height:3.4rem; background:var(--accent-wash); border:1px solid color-mix(in srgb,var(--accent) 35%,transparent);
                       border-radius:0 0 3px 3px; display:flex; align-items:center; justify-content:center; font-size:.64rem; font-weight:700; color:var(--ink); }
          .gd .bl .bb{ height:.5rem; background:#64748b; border-radius:2px; }
          .gd .bl small{ text-align:center; font-size:.58rem; color:var(--soft); font-weight:700; }

          /* the Edit option page */
          .gd .crumb{ font-size:.64rem; color:var(--faint); margin-bottom:.15rem; }
          .gd .crumb b{ color:var(--accent); font-weight:600; }
          .gd .h1{ font-size:.92rem; font-weight:800; color:var(--ink); margin:0 0 .5rem; }
          .gd .tst{ display:flex; flex-direction:column; gap:.38rem; max-width:31rem; }
          .gd .tst > div{ display:flex; align-items:flex-start; gap:.45rem; font-size:.74rem; color:var(--ink); line-height:1.3; }
          .gd .tst > div.ind{ margin-left:1.2rem; }
          .gd .tst small{ color:var(--faint); font-size:.62rem; margin-left:.2rem; }
          .gd .tst .tick{ flex:none; margin-top:1px; }
          .gd .tsw{ display:inline-grid; flex:none; } .gd .tsw > span{ grid-area:1/1; }
          .gd .hit{ border-radius:6px; padding:.1rem .2rem; margin:-.1rem -.2rem; }
          .gd .errb{ display:flex; gap:.4rem; background:var(--err-wash); border-left:3px solid var(--err); border-radius:8px; padding:.4rem .6rem;
                     font-size:.7rem; color:var(--ink); max-width:31rem; margin-top:.6rem; }

          /* the join */
          .gd .jbar{ position:relative; display:flex; max-width:24rem; height:1.7rem; margin:.5rem 0 .25rem; }
          .gd .jbar > div{ flex:1; background:#1f3b5b; color:#fff; font-size:.68rem; font-weight:700; display:flex; align-items:center; justify-content:center; }
          .gd .jbar > div:first-child{ border-radius:4px 0 0 4px; } .gd .jbar > div:last-child{ border-radius:0 4px 4px 0; }
          .gd .jbar .jn{ position:absolute; left:50%; top:-.35rem; bottom:-.35rem; width:3px; margin-left:-1.5px; background:#f59e0b; border-radius:2px; }
          .gd .wt{ display:inline-grid; grid-template-columns:auto auto; gap:1px; background:var(--line); border:1px solid var(--line); border-radius:6px; overflow:hidden; font-size:.66rem; }
          .gd .wt span{ background:var(--surface); padding:.16rem .5rem; font-variant-numeric:tabular-nums; color:var(--ink); }
          .gd .wt span.h{ background:var(--panel); font-weight:700; color:var(--soft); }
          .gd .wt span.mx{ background:var(--accent-wash); font-weight:800; }
          .gd .sum{ display:flex; align-items:center; flex-wrap:wrap; gap:.4rem; margin:.7rem 0; }
          .gd .sum .v{ border:1px solid var(--line); border-radius:9px; padding:.35rem .6rem; background:var(--surface); font-size:.74rem; font-weight:700; color:var(--ink); }
          .gd .sum .v.out{ border-color:var(--good); color:var(--good); }
          .gd .sum .op{ font-size:.72rem; font-weight:800; color:var(--faint); }

          @media (max-width:640px){
            .gd .two{ grid-template-columns:1fr; }
            .gd .sc{ min-height:600px; }
          }',
        'demo'    => '
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / quotes / add a blind</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a class="on">Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a>Products</a><a>Users</a><a>Settings</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="sct">Roller fascias</div>
                  <p class="scs">Three blinds under one 4000 mm fascia &mdash; one carries it, the others say No Fascia.</p>
                  <div class="two">
                    <div class="cell">
                      <div><label>Fascia Options</label>' . $sb('LL 70mm Cassette') . '</div>
                      <div class="xch"><label>Fascia Sizing</label>' . $sb('Multi Blind') . '</div>
                      ' . $num('Fascia Width', '4000') . '
                      <div class="xch"><label>Number Of Blinds?</label>' . $sb('3') . '</div>
                      ' . $num('Blind 1', '1500') . $num('Blind 2', '1500') . $num('Blind 3', '1000') . '
                    </div>
                    <div>' . $panel($ROWS, '&#10003; Fits: 4000 mm in 4000 mm fascia', 'okk', '302.20') . '
                      <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; nine short chapters.</p></div>
                  </div>
                </div>

                <!-- 1 — Fascia Options -->
                <div class="sc" data-scene="1" data-len="' . $len(1) . '">
                  <div class="sct a-fade" style="--d:.2s">Fascia Options &mdash; above the size</div>
                  <div class="two">
                    <div>
                      <div class="cell a-rise" style="--d:' . $at(1, 'In the quote builder') . '">
                        <div><label>Fascia Options</label>' . $sb($swap('No Fascia', 'LL 70mm Cassette', $at(1, 'Say, L L', 1.5)), 'a-ring', '--d:' . $at(1, 'Open the Fascia Options')) . '</div>
                        ' . $dd($FASCIAS, 'LL 70mm Cassette', '--d:' . $at(1, 'Open the Fascia Options', 1.2) . ';--d2:' . $at(1, 'Say, L L', 1.5)) . '
                        <div class="xch a-rise" style="--d:' . $at(1, 'called Fascia Sizing') . '"><label>Fascia Sizing</label>' . $sb('To Fit Blind', 'a-ring', '--d:' . $at(1, 'called Fascia Sizing', .6)) . '</div>
                      </div>
                      <div class="a-fade" style="--d:' . $at(1, 'sits above Width') . '">' . $sizes('<span class="ph">Width in mm</span>', '<span class="ph">Drop in mm</span>') . '</div>
                    </div>
                    <div class="chips">
                      <span class="chip a-pop" style="--d:' . $at(1, 'because you decide') . '">&#8593; Sits above Width and Drop</span>
                      <span class="chip a-pop" style="--d:' . $at(1, 'Leave it on No Fascia') . '">No Fascia &rarr; nothing more appears</span>
                      <span class="chip acc a-pop" style="--d:' . $at(1, 'Pick a real fascia') . '">A real fascia &rarr; Fascia Sizing opens underneath</span>
                    </div>
                  </div>
                </div>

                <!-- 2 — To Fit Blind -->
                <div class="sc" data-scene="2" data-len="' . $len(2) . '">
                  <div class="sct a-fade" style="--d:.2s">To Fit Blind &mdash; the usual one</div>
                  <div class="two">
                    <div>
                      <div class="cell">
                        <div><label>Fascia Options</label>' . $sb('LL 70mm Cassette') . '</div>
                        <div class="xch"><label>Fascia Sizing</label>' . $sb('To Fit Blind', 'a-ring', '--d:' . $at(2, 'To Fit Blind is already')) . '</div>
                        ' . $dd($SIZING, 'To Fit Blind', 'top:6.55rem;left:1.8rem;--d:' . $at(2, 'three choices') . ';--d2:' . $at(2, 'It means')) . '
                      </div>
                      ' . $sizes('<span class="a-type" style="--d:' . $at(2, 'Just type') . ';--ts:4;--tt:.6s">1200</span>',
                                 '<span class="a-type" style="--d:' . $at(2, 'and the Drop') . ';--ts:4;--tt:.6s">1600</span>') . '
                    </div>
                    <div class="chips">
                      <span class="chip a-pop" style="--d:' . $at(2, 'It means the fascia') . '">Fascia = the blind&rsquo;s width</span>
                      <span class="chip a-pop" style="--d:' . $at(2, 'nothing else to do') . '">Nothing else to fill in</span>
                      <span class="chip acc a-pop" style="--d:' . $at(2, 'The fascia is priced') . '">Priced and cut at 1200 mm</span>
                      <span class="chip ok a-pop" style="--d:' . $at(2, 'Most of your blinds') . '">&#10003; Most blinds stay here</span>
                    </div>
                  </div>
                </div>

                <!-- 3 — Over Size For Single Blind -->
                <div class="sc" data-scene="3" data-len="' . $len(3) . '">
                  <div class="sct a-fade" style="--d:.2s">A fascia wider than the blind</div>
                  <div class="two">
                    <div>
                      <div class="cell">
                        <div><label>Fascia Options</label>' . $sb('LL 70mm Cassette') . '</div>
                        <div class="xch"><label>Fascia Sizing</label>' . $sb($swap('To Fit Blind', 'Over Size For Single Blind', $at(3, 'Choose Over Size', 1.5)), 'a-ring', '--d:' . $at(3, 'Choose Over Size')) . '</div>
                        ' . $num('Fascia Width', '<span class="a-type" style="--d:' . $at(3, 'Type the fascia width') . ';--ts:4;--tt:.6s">1700</span>', 'a-rise', '--d:' . $at(3, 'a Fascia Width box')) . '
                      </div>
                      ' . $sizes('1500', '1600') . '
                    </div>
                    <div>
                      <div class="open a-rise" style="--d:' . $at(3, 'Say the blind') . '">
                        <div class="fas a-wide" style="--d:' . $at(3, 'runs on to') . '">Fascia 1700</div>
                        <div class="bl"><div style="width:85%"><div class="cl">Blind 1500</div></div></div>
                      </div>
                      <div class="chips" style="margin-top:.6rem">
                        <span class="chip acc a-pop" style="--d:' . $at(3, 'priced at that width') . '">Fascia priced at 1700</span>
                        <span class="chip acc a-pop" style="--d:' . $at(3, 'cut to it on the roll label') . '">Fascia cut from 1700 on the roll label</span>
                        <span class="chip ok a-pop" style="--d:' . $at(3, 'The blind itself') . '">Blind still made at 1500</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 4 — Multi Blind -->
                <div class="sc" data-scene="4" data-len="' . $len(4) . '">
                  <div class="sct a-fade" style="--d:.2s">Multi Blind &mdash; several blinds, one fascia</div>
                  <div class="two">
                    <div>
                      <div class="cell">
                        <div><label>Fascia Options</label>' . $sb('LL 70mm Cassette') . '</div>
                        <div class="xch"><label>Fascia Sizing</label>' . $sb($swap('To Fit Blind', 'Multi Blind', $at(4, 'Choose Multi Blind', 1)), 'a-ring', '--d:.6s') . '</div>
                        ' . $num('Fascia Width', '<span class="a-type" style="--d:' . $at(4, 'Type the whole') . ';--ts:4;--tt:.6s">4000</span>', 'a-rise', '--d:' . $at(4, 'Choose Multi Blind', 2)) . '
                        <div class="xch a-rise" style="--d:' . $at(4, 'Number Of Blinds asks') . '"><label>Number Of Blinds?</label>' . $sb($swap('2', '3', $at(4, 'up to five')), 'a-ring', '--d:' . $at(4, 'how many')) . '</div>
                        ' . $num('Blind 1', '<span class="a-type" style="--d:' . $at(4, 'Blind 1,') . ';--ts:4;--tt:.5s">1500</span>', 'a-rise', '--d:' . $at(4, 'Then a box')) . '
                        ' . $num('Blind 2', '<span class="a-type" style="--d:' . $at(4, 'Blind 2,') . ';--ts:4;--tt:.5s">1500</span>', 'a-rise', '--d:' . $at(4, 'Then a box', .3)) . '
                        ' . $num('Blind 3', '<span class="a-type" style="--d:' . $at(4, 'and so on') . ';--ts:4;--tt:.5s">1000</span>', 'a-pop', '--d:' . $at(4, 'up to five', .4)) . '
                      </div>
                      ' . $sizes('<span class="sw"><span class="a-out" style="--d:' . $at(4, 'The Width box') . '"><span class="ph">Width in mm</span></span><span class="a-fade" style="--d:' . $at(4, 'The Width box') . '">multi blind</span></span>',
                                 '<span class="a-type" style="--d:' . $at(4, 'one Drop') . ';--ts:4;--tt:.5s">1600</span>', 'lock a-ring" style="--d:' . $at(4, 'The Width box', .3)) . '
                    </div>
                    <div class="chips">
                      <span class="chip a-pop" style="--d:' . $at(4, 'Number Of Blinds asks') . '">Number Of Blinds? &middot; 2 to 5</span>
                      <span class="chip a-pop" style="--d:' . $at(4, 'Then a box') . '">A width box for each blind</span>
                      <span class="chip a-pop" style="--d:' . $at(4, 'The Width box') . '">Width reads <i>multi blind</i></span>
                      <span class="chip acc a-pop" style="--d:' . $at(4, 'Type the whole') . '">Fascia Width = the whole opening</span>
                      <span class="chip a-pop" style="--d:' . $at(4, 'one Drop') . '">One Drop for them all</span>
                    </div>
                  </div>
                </div>

                <!-- 5 — the carrier -->
                <div class="sc" data-scene="5" data-len="' . $len(5) . '">
                  <div class="sct a-fade" style="--d:.2s">One blind carries the fascia</div>
                  <div class="two">
                    <div>
                      <div class="a-rise" style="--d:.8s">' . $panel($ROWS, '&#10003; Fits: 4000 mm in 4000 mm fascia', 'okk', '302.20', 1.6, .5) . '</div>
                      <div class="pv ok a-fade" style="--d:2.5s"><b>3 blinds</b> under one fascia &mdash; <b>&pound;302.20</b> total</div>
                      <div style="margin-top:.6rem"><span class="btnp a-press" style="--d:' . $at(5, 'When you save') . '">Save blind</span></div>
                    </div>
                    <div>
                      <div class="open a-rise" style="--d:' . $at(5, 'The first blind') . '">
                        <div class="fas a-wide" style="--d:' . $at(5, 'It carries the fascia') . '">Fascia 4000 &middot; on Blind 1</div>
                        <div class="bl">
                          <div style="width:37.5%"><div class="cl a-ring" style="--d:' . $at(5, 'The first blind') . '">1 &middot; carrier</div></div>
                          <div style="width:37.5%"><div class="cl">2</div><small class="a-pop" style="--d:' . $at(5, 'set to No Fascia') . '">No Fascia</small></div>
                          <div style="width:25%"><div class="cl">3</div><small class="a-pop" style="--d:' . $at(5, 'set to No Fascia', .4) . '">No Fascia</small></div>
                        </div>
                      </div>
                      <div class="chips" style="margin-top:.6rem">
                        <span class="chip acc a-pop" style="--d:' . $at(5, 'only charged once') . '">Fascia charged once</span>
                        <span class="chip a-pop" style="--d:' . $at(5, 'its own drop') . '">Drop box: leave on <i>same</i>, or type one</span>
                        <span class="chip ok a-pop" style="--d:' . $at(5, 'each blind becomes') . '">Saved as 3 lines &middot; tagged Fascia A</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 6 — the fit check -->
                <div class="sc" data-scene="6" data-len="' . $len(6) . '">
                  <div class="sct a-fade" style="--d:.2s">Do they fit?</div>
                  <div class="two">
                    <div>
                      <div class="cell" style="margin-bottom:.6rem">
                        ' . $num('Fascia Width', '4000', 'a-ring', '--d:' . $at(6, 'checks them against'), false) . '
                        ' . $num('Blind 3', '<span class="sw"><span class="a-out" style="--d:' . $at(6, 'If they come to more') . '">1000</span><span class="a-mid" style="--d:' . $at(6, 'If they come to more') . ';--d2:' . $at(6, 'or the fascia wider') . '">1500</span><span class="a-fade" style="--d:' . $at(6, 'or the fascia wider') . '">1000</span></span>', 'a-ring', '--d:' . $at(6, 'If they come to more'), false) . '
                      </div>
                      <div class="mp"><div class="mbh">Blinds in this fascia</div>
                        <div class="fitsw">
                          <span class="fit okk a-mid" style="font-weight:700;color:#127a29;--d:' . $at(6, 'If they fit') . ';--d2:' . $at(6, 'If they come to more') . '">&#10003; Fits: 4000 mm in 4000 mm fascia</span>
                          <span class="a-mid" style="font-weight:700;color:#b00020;--d:' . $at(6, 'If they come to more', .4) . ';--d2:' . $at(6, 'or the fascia wider') . '">&#9888; Won&rsquo;t fit: blinds total 4500 mm vs fascia 4000 mm</span>
                          <span class="a-fade" style="font-weight:700;color:#127a29;--d:' . $at(6, 'or the fascia wider', .4) . '">&#10003; Fits: 4000 mm in 4000 mm fascia</span>
                        </div>
                      </div>
                      <div style="margin-top:.6rem" class="fitsw">
                        <span class="btnp a-out" style="--d:' . $at(6, 'and Save blind') . '">Save blind</span>
                        <span class="btnp off a-mid" style="--d:' . $at(6, 'and Save blind') . ';--d2:' . $at(6, 'or the fascia wider', .4) . '">Save blind</span>
                        <span class="btnp a-fade" style="--d:' . $at(6, 'or the fascia wider', .4) . '">Save blind</span>
                      </div>
                    </div>
                    <div class="chips">
                      <span class="chip a-pop" style="--d:' . $at(6, 'The panel adds up') . '">1500 + 1500 + 1000 = 4000</span>
                      <span class="chip ok a-pop" style="--d:' . $at(6, 'If they fit') . '">&#10003; Fits</span>
                      <span class="chip bad a-pop" style="--d:' . $at(6, 'If they come to more') . '">&#9888; Won&rsquo;t fit &rarr; Save blind greyed out</span>
                      <span class="chip a-pop" style="--d:' . $at(6, 'Leave Fascia Width empty') . '">Empty Fascia Width &rarr; &ldquo;Tip: enter a Fascia width to fit-check.&rdquo;</span>
                    </div>
                  </div>
                </div>

                <!-- 7 — each blind\'s own width -->
                <div class="sc" data-scene="7" data-len="' . $len(7) . '">
                  <div class="sct a-fade" style="--d:.2s">Bottom bars and chains stay on each blind&rsquo;s width</div>
                  <div class="open a-rise" style="--d:.8s;max-width:30rem">
                    <div class="fas a-wide" style="--d:' . $at(7, 'use the fascia width') . '">Fascia and its colours &middot; priced at 4000</div>
                    <div class="bl">
                      <div style="width:37.5%"><div class="cl">Blind 1 &middot; 1500</div><div class="bb a-wide" style="--d:' . $at(7, 'So the bottom bar') . '"></div><small class="a-pop" style="--d:' . $at(7, 'are priced at fifteen') . '">bar + chain at 1500</small></div>
                      <div style="width:37.5%"><div class="cl">Blind 2 &middot; 1500</div><div class="bb a-wide" style="--d:' . $at(7, 'So the bottom bar', .4) . '"></div><small class="a-pop" style="--d:' . $at(7, 'are priced at fifteen', .4) . '">bar + chain at 1500</small></div>
                      <div style="width:25%"><div class="cl">Blind 3 &middot; 1000</div><div class="bb a-wide" style="--d:' . $at(7, 'So the bottom bar', .8) . '"></div><small class="a-pop" style="--d:' . $at(7, 'are priced at fifteen', .8) . '">bar + chain at 1000</small></div>
                    </div>
                  </div>
                  <div class="chips" style="flex-direction:row;flex-wrap:wrap;margin:.8rem 0">
                    <span class="chip acc a-pop" style="--d:' . $at(7, 'Only the fascia itself') . '">Fascia + colours &rarr; the fascia width</span>
                    <span class="chip a-pop" style="--d:' . $at(7, 'Everything else') . '">Everything else &rarr; the blind&rsquo;s own width</span>
                    <span class="chip ok a-pop" style="--d:' . $at(7, 'The fascia is charged once') . '">Fascia once, on Blind 1</span>
                  </div>
                  <div class="a-rise" style="--d:' . $at(7, 'That is why') . '">' . $panel($ROWS, '&#10003; Fits: 4000 mm in 4000 mm fascia', 'okk', '302.20') . '</div>
                </div>

                <!-- 8 — the joinable tick -->
                <div class="sc" data-scene="8" data-len="' . $len(8) . '">
                  <div class="a-fade" style="--d:' . $at(8, 'Open the product') . '">
                    <div class="crumb"><b>Products</b> &rsaquo; <b>Roller Blind</b> &rsaquo; <b>Options</b> &rsaquo; Fascia Options</div>
                    <div class="h1">Edit option: Fascia Options</div>
                  </div>
                  <div class="tst a-rise" style="--d:' . $at(8, 'click Edit beside', .8) . '">
                    <div>' . $tk(false) . '<span>Required<small>customer must pick a choice</small></span></div>
                    <div>' . $tk(false) . '<span>Allow multiple choices<small>renders as tick-boxes instead of a dropdown</small></span></div>
                    <div class="ind">' . $tk(false) . '<span>Collapse to &ldquo;None&rdquo; until needed</span></div>
                    <div>' . $tk(true) . '<span>Show above the size fields<small>e.g. the roller fascia group</small></span></div>
                    <div>' . $tk(false) . '<span>Splits the blind into equal panels</span></div>
                    <div class="hit a-ring" style="--d:' . $at(8, 'Tick Can be joined') . '"><span class="tsw"><span class="a-out" style="--d:' . $at(8, 'Tick Can be joined', 1.2) . '">' . $tk(false) . '</span><span class="a-fade" style="--d:' . $at(8, 'Tick Can be joined', 1.2) . '">' . $tk(true) . '</span></span>
                      <span><b>Can be joined when longer than its width table</b><small>made in equal pieces joined in the middle and each piece is charged at its length</small></span></div>
                    <div>' . $tk(true) . '<span>Active<small>uncheck to hide from quote builder</small></span></div>
                  </div>
                  <div style="display:flex;gap:.4rem;margin-top:.7rem"><span class="btnp a-press" style="--d:' . $at(8, 'press Save changes', .6) . '">Save changes</span><span class="btns">Cancel</span></div>
                  <div class="errb a-pop" style="--d:' . $at(8, 'Leave it unticked') . '"><b style="color:var(--err)">Unticked:</b> Width 4000 mm exceeds the largest entry in the width table for &lsquo;Senses&rsquo;.</div>
                  <div class="a-move" style="--fx:70%;--fy:90%;--tx:1%;--ty:11.2rem;--d:' . $at(8, 'Tick Can be joined', -.8) . ';--md:1.6s">' . $ptr . '</div>
                </div>

                <!-- 9 — how a join is priced -->
                <div class="sc" data-scene="9" data-len="' . $len(9) . '">
                  <div class="sct a-fade" style="--d:.2s">How a join is priced</div>
                  <div class="two">
                    <div>
                      <div class="jbar a-rise" style="--d:' . $at(9, 'The fascia is split') . '">
                        <div>' . $swap('Senses 4000', '2000', $at(9, 'joined in the middle')) . '</div><div><span class="a-fade" style="--d:' . $at(9, 'joined in the middle') . '">2000</span></div>
                        <span class="jn a-pop" style="--d:' . $at(9, 'joined in the middle') . '"></span>
                      </div>
                      <p class="scs a-fade" style="--d:' . $at(9, 'joined in the middle', .5) . '">Equal pieces, joined in the middle.</p>
                      <div class="sum">
                        <span class="v a-pop" style="--d:' . $at(9, 'charged as two pieces') . '">2 pieces</span>
                        <span class="op a-fade" style="--d:' . $at(9, 'charged as two pieces', .4) . '">&times;</span>
                        <span class="v a-pop" style="--d:' . $at(9, 'at the two thousand price') . '">2000 price &pound;24.50</span>
                        <span class="op a-fade" style="--d:' . $at(9, 'There is no charge') . '">+</span>
                        <span class="v a-pop" style="--d:' . $at(9, 'There is no charge') . '">joint &pound;0.00</span>
                        <span class="op a-fade" style="--d:' . $at(9, 'There is no charge', 1) . '">=</span>
                        <span class="v out a-pop" style="--d:' . $at(9, 'There is no charge', 1.4) . '">&pound;49.00</span>
                      </div>
                      <div class="pv ok a-rise" style="--d:' . $at(9, 'The price line') . '"><b>&pound;186.40</b> per blind &middot; <span class="a-ring" style="--d:' . $at(9, 'says joined', .3) . ';border-radius:4px">Senses joined: 2 &times; 2000 mm</span></div>
                    </div>
                    <div>
                      <div class="a-fade" style="font-size:.64rem;font-weight:700;color:var(--soft);margin-bottom:.25rem;--d:1s">Senses &middot; price by width (example)</div>
                      <div class="wt a-rise" style="--d:' . $at(9, 'Say the Senses table') . '">
                        <span class="h">Width</span><span class="h">Price</span>
                        <span>1500</span><span>&pound;18.90</span>
                        <span class="a-ring" style="--d:' . $at(9, 'at the two thousand price') . '">2000</span><span class="a-ring" style="--d:' . $at(9, 'at the two thousand price') . '">&pound;24.50</span>
                        <span>3000</span><span>&pound;33.10</span>
                        <span class="mx">3500</span><span class="mx">&pound;37.80</span>
                      </div>
                      <p class="scs a-fade" style="--d:' . $at(9, 'stops at thirty') . ';margin-top:.4rem">Longest: 3500. A 4000 fascia runs past it.</p>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>',
        'body'    => '
          <p><b>Where the fascia lives.</b> On a roller product the fascia options sit in the quote builder <b>above Width and Drop</b>
             (the option has <b>Show above the size fields</b> ticked), so you settle the fascia before the size. Each one is a dropdown,
             and the ones that belong to it sit indented underneath it.</p>

          <ul class="steps">
            <li><b>Fascia Options.</b> Pick the fascia &mdash; for example <b>Senses</b>, <b>LL 70mm Cassette</b>, <b>LL 40mm Cassette</b> or
                <b>Grip Fix Cassette</b> &mdash; or leave it on <b>No Fascia</b>. With <b>No Fascia</b> nothing more appears. Pick a real fascia
                and <b>Fascia Sizing</b> appears underneath.</li>
            <li><b>Fascia Sizing &rarr; To Fit Blind</b> (already picked). The fascia is made to the blind&rsquo;s width. Type the
                <b>Width</b> and <b>Drop</b> as normal; the fascia is priced and cut at that width.</li>
            <li><b>Fascia Sizing &rarr; Over Size For Single Blind.</b> A <b>Fascia Width</b> box (in mm) appears. Type the fascia&rsquo;s
                own width &mdash; e.g. a 1500 blind with a 1700 fascia running past a skirting board. The fascia is <b>priced</b> at that width
                (its price-by-width table is read at 1700, not 1500), and the roll label&rsquo;s fascia <b>cut</b> works from it. The blind
                itself is still made to its own Width. Leave the box empty and everything uses the blind&rsquo;s Width.</li>
            <li><b>Fascia Sizing &rarr; Multi Blind.</b> For several blinds under one fascia. <b>Number Of Blinds?</b> asks how many
                (2 to 5), and a box appears for each: <b>Blind 1</b>, <b>Blind 2</b> and so on, each its own width in mm. The top
                <b>Width</b> box reads <i>multi blind</i> and can&rsquo;t be typed in. Type the whole opening in <b>Fascia Width</b> and one
                <b>Drop</b> for them all. (Multi Blind is only offered when adding a new blind &mdash; you can&rsquo;t turn one saved line into
                several; delete it and add the group again.)</li>
          </ul>

          <p><b>Several blinds in one fascia.</b> With Multi Blind chosen, a panel called <b>Blinds in this fascia</b> appears under the
             price, listing each blind&rsquo;s <b>Width</b>, <b>Drop</b> and <b>Price</b>, with the <b>Total</b> at the foot. The price box reads
             <code>3 blinds under one fascia &mdash; &pound;302.20 total</code>.</p>
          <ul class="steps">
            <li><b>The carrier.</b> The first blind carries the fascia (its width shows <code>1500 mm &middot; fascia</code>), priced at the
                whole fascia width &mdash; so the fascia is charged <b>once</b>. Every other blind is set to <b>No Fascia</b>, and the fascia
                colours are dropped from them.</li>
            <li><b>Drops.</b> Each blind&rsquo;s drop box says <i>same</i> &mdash; it uses the shared Drop. Type a figure to give that blind its
                own drop.</li>
            <li><b>The fit check.</b> The blind widths are added up and checked against <b>Fascia Width</b>:
                <code>&#10003; Fits: 4000 mm in 4000 mm fascia</code> or
                <code>&#9888; Won&rsquo;t fit: blinds total 4500 mm vs fascia 4000 mm</code>. While it won&rsquo;t fit, <b>Save blind</b> is greyed
                out; if it is sent anyway the quote refuses it with <code>These blinds won&rsquo;t fit: &hellip; Reduce a blind width or widen the
                fascia.</code> Leave Fascia Width empty and there is nothing to check against &mdash; it says
                <code>Tip: enter a Fascia width to fit-check.</code></li>
            <li><b>Saving.</b> <b>Save blind</b> saves each blind as its own line on the quote, tagged with a fascia group letter
                (<b>Fascia A</b>) so you can see which blinds go together.</li>
            <li><b>Bottom bar, chain and the rest.</b> Only the fascia and the options under it (its colours) use the fascia width.
                Everything else &mdash; bottom bar, chain, motor, fabric &mdash; is priced at <b>each blind&rsquo;s own width</b>, so a bottom bar on
                a 1500 blind is priced at 1500 even under a 4000 fascia. That is why the three prices in the panel differ.</li>
            <li><b>On the factory floor.</b> Each blind gets its own roll label with its own cuts. The labels show which fascia group they
                belong to (e.g. <code>A &middot; 1 of 3</code>); the fascia cut is printed on the carrier&rsquo;s label only, and the others say
                <code>See #</code> and the carrier&rsquo;s label number, so the fascia is cut once.</li>
          </ul>

          <p><b>Joining a fascia longer than its price table.</b> Each fascia is priced from a <b>price-by-width table</b> on its choice. When a
             fascia is longer than the table&rsquo;s last (longest) row:</p>
          <ul class="steps">
            <li><b>Turn joining on.</b> <b>Products</b> &rarr; the product &rarr; the <b>Options</b> tile &rarr; <b>Edit</b> beside
                <b>Fascia Options</b>. Tick <b>Can be joined when longer than its width table</b> and press <b>Save changes</b>.</li>
            <li><b>How it&rsquo;s priced.</b> The length is split into the fewest <b>equal</b> pieces that each fit the table &mdash; normally two,
                joined in the middle &mdash; and <b>each piece is charged at its own length</b>. A 4000 fascia whose table stops at 3500 is
                charged as <b>2 &times; the 2000 price</b>. There is <b>no charge for the joint</b>.</li>
            <li><b>Where you see it.</b> The price line adds <code>Senses joined: 2 &times; 2000 mm</code>; in the Blinds in this fascia panel
                the carrier shows <code>(joined 2 &times; 2000 mm)</code>.</li>
          </ul>

          <div class="oops"><b>Not ticked?</b> A fascia longer than its table is refused:
             <code>Width 4000 mm exceeds the largest entry in the width table for \'Senses\'.</code> Tick the joining box, or shorten the
             fascia.</div>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Keep these names.</b> The several-blinds pricing looks for the option
             <b>Fascia Options</b>, its <b>No Fascia</b> choice and the <b>Fascia Width</b> option by name, and the fascia cut reads the
             <b>Fascia Width</b> box by name too &mdash; so don&rsquo;t rename those. The <b>Fascia Sizing</b> choices (To Fit Blind, Over Size For
             Single Blind, Multi Blind) are found by a hidden code instead, so their captions can be reworded safely.</div></div>',
        'script'  => array_map(static fn ($k, $l) => [$l[0], $l[1], $l[2], $k], array_keys($S), $S),
];
