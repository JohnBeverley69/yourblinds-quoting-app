<?php
declare(strict_types=1);

/**
 * Guide: products-price-import — "Importing price tables from a spreadsheet" (v2 player).
 *
 * One entry of the guided-walkthrough registry. Loaded by help/_guides.php,
 * rendered by help/guide.php. Fields: aud, section, title, eyebrow, blurb,
 * lede, open, v, css, demo, body, script.
 *
 * Mirrors a system's band list (/admin/products/price-tables.php), the
 * multi-band "Bulk import" (/admin/products/price-tables-bulk-import.php,
 * incl. the "Which worksheet?" step), the "Single-band import"
 * (/admin/products/price-tables-single-import.php), the shared file parser
 * (_partials/price_table_parser.php — ptp_parse_band_blocks) and the Undo
 * strip (_partials/price_table_undo.php + price-undo.php). Every label,
 * button and message is taken from those files.
 *
 * v2: one SCENE per script line (data-scene = the line's step), each with
 * its own animation timeline (a-* classes, start times in --d seconds,
 * stretched to the recorded line's length via data-len).
 */

// ── The voice-over (built first so each scene's data-len comes from it) ──
$script = [
    ['1',  'Why import a spreadsheet',   "Typing prices one square at a time is slow, and it is easy to make a slip. If your supplier sends their price list as a spreadsheet, the system can read it for you. One file can fill every band on a system in one go. This guide shows where to upload it, what shape the file needs to be, and how to undo an import that goes wrong.", 1],
    ['2',  'Getting there',              "Go to Products, and open the product. In its Systems section, click Open on the system you want. That opens the system's price tables, with the product and the system in the title. Across the top are the import buttons: Single-band import, and Bulk import, multiple bands. Underneath, every band is listed, with how many price cells it holds.", 2],
    ['3',  'One system at a time',       "Which button should you use? Bulk import reads every band in the file, and fills them all. It is the one you will use most. Single-band import takes just one band. Both work on one system at a time. So if a product has Standard and Motorised, and each has its own prices, you import each system in turn.", 3],
    ['4',  'The band header',            "Before you upload, check the file's shape. The system finds each band by a header row. In column A, there must be the word Band, then the band's name, like Band A, or Price Band B. If one grid serves two bands, write both, like Band B slash C, and the same prices go into each. Anything above the first header is ignored, so a title at the top is fine.", 4],
    ['5',  'Widths, then drops',         "Straight after the header comes the row of widths, running across from column B. They can be in millimetres, centimetres or metres, and it works out which. So nought point eight becomes eight hundred millimetres. Label rows, such as Drop, Width or Metric, are skipped. Then each row below starts with a drop, followed by its prices.", 5],
    ['6',  'Prices and gaps',            "Prices can keep their pound signs and commas. They are cleaned off for you. A blank square, or a nought, is left out, and that means there is no price at that size. A quote at that size will not price. You can stack several bands down one sheet, one block under another, with a new header above each.", 6],
    ['7',  'Start from a blank sheet',   "No spreadsheet from your supplier, or want to make your own? Start with a blank one. On the Bulk import page, click Download a blank template. It is already laid out the right way, with a block for each band on this system. Or make your own in Excel. Type Band A in the very first cell. Put your widths across the next row, starting from column B. Then put your drops down column A.", 7],
    ['8',  'Fill it in, then the next band', "Now type or paste your prices into the empty squares, each one under its width and next to its drop. Leave a square empty if you do not sell that size. For the next band, leave one blank row, type Band B in column A, and repeat the widths and drops underneath. Save the file, and it is ready to upload.", 8],
    ['9',  'Upload the file',            "Click Bulk import. The box called What this expects repeats these rules, so read it once. Under Upload, choose your file, then click Upload and import. Excel files work, and so do C S V and O D S files. The file can be up to ten megabytes.", 9],
    ['10',  'Which worksheet?',           "Some supplier files have several worksheets, one for each slat size, for instance. If more than one sheet has bands in it, the import stops and asks, Which worksheet? Each sheet shows how many bands and cells it found. Pick the one for this system, then click Import selected sheet.", 10],
    ['11',  'What you get',               "When it works, a green message says how many bands were imported, with a line for each band and its number of cells. Any band that did not exist yet is created for you. The whole file goes in together, or not at all, so you are never left with half an import.", 11],
    ['12', 'Importing replaces',         "Be clear about one thing. Importing a band replaces it. Every old price in that band, on this system, is wiped, and the file's prices go in their place. Bands that are not in the file are left alone. So to update just one band, you can import a file with only that band in it.", 12],
    ['13', 'On to the next system',      "Underneath, it helps you carry on. If another system on this product still has bands with no prices, there is a button to import that one next. When every system is priced, it says, That's every system priced for this product, it's ready to quote. Or click View prices, to check the grids.", 13],
    ['14', 'Single-band import',         "Single-band import is for one band. It reads the first band it finds in the file, and that band's header decides which table it fills. So a file headed Band C fills Band C, and creates it if needed. If the file holds more bands, only the first is used, and it suggests Bulk import instead.", 14],
    ['15', 'When a file will not read',  "If the file is not the right shape, a red message explains. No band sections detected means there is no Band header in column A. Add one above each block, and try again. Please choose a file means nothing was picked. File too large means it is over ten megabytes.", 15],
    ['16', 'Undo an import',             "Imported the wrong file? You can undo it. After an import, a strip appears, saying Last change, Band import, with the date and time. Click the Undo button, and say OK. Every price goes back to exactly what it was before, and any band the import created is removed again.", 16],
    ['17', 'When undo says no',          "Undo has one safety rule. If the prices have been changed again since the import, it will not undo, because that would wipe the newer work. The strip then says it can't be undone, the prices have been edited since. You will find the same strip on the band list, so you can undo without opening each table.", 17],
    ['18', 'Check the result',           "Last of all, check the result. Open one band, and compare a few prices with the supplier's sheet. Look out for an empty square along an edge, where a price was missing. And remember, an import only copies the numbers in. Whether they are your selling prices, or a supplier list with your discount and markup added, is set on the product.", 18],
];
$len = static fn (int $n): string => (string) round(mb_strlen($script[$n - 1][2]) / 13.6);
foreach (range(1, 18) as $n) ${'L' . $n} = $len($n);

$ptr = '<span class="gd-ptr"><svg viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></span>';

/**
 * A spreadsheet. $rows = list of [rowNo, [A, B, C, D, E], cls, style]; each
 * cell may be a string or [html, extraClass, style].
 */
$xl = static function (string $file, array $rows, string $cls = '', string $style = ''): string {
    $h = '<div class="xl ' . $cls . '" style="' . $style . '"><div class="xh">' . $file . '</div><div class="xg">'
       . '<span class="cn"></span><span class="cn">A</span><span class="cn">B</span><span class="cn">C</span><span class="cn">D</span><span class="cn">E</span>';
    foreach ($rows as $r) {
        [$no, $cells] = $r;
        $rc = $r[2] ?? ''; $rs = $r[3] ?? '';
        $h .= '<span class="rn ' . $rc . '" style="' . $rs . '">' . $no . '</span>';
        foreach (range(0, 4) as $i) {
            $c = $cells[$i] ?? '';
            if (is_array($c)) {
                $h .= '<span class="xc ' . ($c[1] ?? '') . ' ' . $rc . '" style="' . ($c[2] ?? $rs) . '">' . $c[0] . '</span>';
            } else {
                $h .= '<span class="xc ' . $rc . '" style="' . $rs . '">' . $c . '</span>';
            }
        }
    }
    return $h . '</div></div>';
};

// The supplier file used through the guide.
$bandRows = static fn (string $band, int $start, array $px, string $rc = '', string $rs = '') => [
    [$start,     [['Band ' . $band, 'bh'], '', '', '', ''], $rc, $rs],
    [$start + 1, ['', '600mm', '900mm', '1200mm', '1500mm'], $rc, $rs],
    [$start + 2, ['1000', $px[0][0], $px[0][1], $px[0][2], $px[0][3]], $rc, $rs],
    [$start + 3, ['1500', $px[1][0], $px[1][1], $px[1][2], $px[1][3]], $rc, $rs],
];
$pxA = [['&pound;24.10', '&pound;28.40', '&pound;32.70', '&pound;37.00'], ['&pound;27.90', '&pound;32.80', '&pound;37.60', '&pound;42.50']];
$pxB = [['&pound;26.30', '&pound;31.00', '&pound;35.80', '&pound;40.40'], ['&pound;30.50', '&pound;35.90', '&pound;41.20', '&pound;46.60']];

$xlPoster = $xl('Supplier price list 2026.xlsx', array_merge(
    [[1, [['<b>Supplier price list 2026</b>', 'wide'], '', '', '', '']]],
    $bandRows('A', 2, $pxA),
    $bandRows('B', 6, $pxB)
));

// Scene 1 — the file, then bands filling.
$xl1 = $xl('Supplier price list 2026.xlsx', array_merge(
    [[1, [['<b>Supplier price list 2026</b>', 'wide'], '', '', '', '']]],
    $bandRows('A', 2, $pxA),
    $bandRows('B', 6, $pxB)
), 'a-rise', '--d:4s');

// Scene 4 — the band header.
$xl4 = $xl('Supplier price list 2026.xlsx', [
    [1, [['<b>Supplier price list 2026</b>', 'wide skip a-ring', '--d:22s'], '', '', '', '']],
    [2, [['<span class="a-type" style="--d:6s;--ts:6;--tt:.6s">Band A</span>', 'bh a-ring', '--d:6.8s'], '', '', '', '']],
    [3, ['', '600mm', '900mm', '1200mm', '1500mm']],
    [4, ['1000', '&pound;24.10', '&pound;28.40', '&pound;32.70', '&pound;37.00']],
    [5, [['<span class="a-type" style="--d:16s;--ts:10;--tt:.8s">Band B / C</span>', 'bh'], '', '', '', '']],
    [6, ['', '600mm', '900mm', '1200mm', '1500mm']],
    [7, ['1000', '&pound;26.30', '&pound;31.00', '&pound;35.80', '&pound;40.40']],
]);

// Scene 5 — widths in metres, label rows, drops.
$xl5 = $xl('Supplier price list 2026.xlsx', [
    [1, [['Band A', 'bh'], '', '', '', '']],
    [2, [['WIDTH', 'lbl a-ring', '--d:17s'], ['Metric', 'lbl'], '', '', '']],
    [3, ['', ['0.6', 'wv a-ring', '--d:4s'], ['0.9', 'wv'], ['1.2', 'wv'], ['1.5', 'wv']]],
    [4, [['DROP', 'lbl'], '', '', '', '']],
    [5, [['1.0', 'dv a-ring', '--d:20.5s'], '&pound;24.10', '&pound;28.40', '&pound;32.70', '&pound;37.00']],
    [6, [['1.5', 'dv'], '&pound;27.90', '&pound;32.80', '&pound;37.60', '&pound;42.50']],
]);

// Scene 6 — £ and commas, a blank, stacked bands.
$xl6 = $xl('Supplier price list 2026.xlsx', [
    [1, [['Band A', 'bh'], '', '', '', '']],
    [2, ['', '600mm', '900mm', '1200mm', '1500mm']],
    [3, ['1000', ['&pound;24.10', 'a-ring', '--d:2s'], '&pound;28.40', '&pound;32.70', '&pound;37.00']],
    [4, ['3000', '&pound;1,024.10', '&pound;1,128.40', '&pound;1,232.70', ['', 'blank a-ring', '--d:9s']]],
    [5, [['Band B', 'bh'], '', '', '', ''], 'a-fade', '--d:16.5s'],
    [6, ['', '600mm', '900mm', '1200mm', '1500mm'], 'a-fade', '--d:16.9s'],
    [7, ['1000', '&pound;26.30', '&pound;31.00', '&pound;35.80', '&pound;40.40'], 'a-fade', '--d:17.3s'],
]);

// Scene 7 — a blank sheet: Band A, widths from B, drops down A.
$xl7 = $xl('My price list.xlsx', [
    [1, [['<span class="a-type" style="--d:15.5s;--ts:6;--tt:.6s">Band A</span>', 'bh a-ring', '--d:16.5s'], '', '', '', '']],
    [2, ['', ['<span class="a-pop" style="--d:19.5s">800</span>', 'wv'], ['<span class="a-pop" style="--d:19.8s">1200</span>', 'wv'], ['<span class="a-pop" style="--d:20.1s">1600</span>', 'wv'], ['<span class="a-pop" style="--d:20.4s">2000</span>', 'wv']]],
    [3, [['<span class="a-pop" style="--d:24s">800</span>', 'dv'], '', '', '', '']],
    [4, [['<span class="a-pop" style="--d:24.3s">1200</span>', 'dv'], '', '', '', '']],
    [5, [['<span class="a-pop" style="--d:24.6s">1600</span>', 'dv'], '', '', '', '']],
]);

// Scene 8 — prices in, a blank row, then Band B.
$p8 = static fn (float $v, float $d): array => ['<span class="a-pop" style="--d:' . $d . 's">' . number_format($v, 2) . '</span>', ''];
$xl8 = $xl('My price list.xlsx', [
    [1, [['Band A', 'bh'], '', '', '', '']],
    [2, ['', ['800', 'wv'], ['1200', 'wv'], ['1600', 'wv'], ['2000', 'wv']]],
    [3, [['800', 'dv'], $p8(22.80, 2), $p8(28.50, 2.3), $p8(34.20, 2.6), $p8(40.60, 2.9)]],
    [4, [['1200', 'dv'], $p8(26.40, 3.2), $p8(33.20, 3.5), $p8(40.00, 3.8), ['', 'blank a-ring', '--d:8s']]],
    [5, ['', '', '', '', '']],
    [6, [['<span class="a-type" style="--d:13.5s;--ts:6;--tt:.6s">Band B</span>', 'bh a-ring', '--d:14.3s'], '', '', '', '']],
    [7, ['', ['800', 'wv'], ['1200', 'wv'], ['1600', 'wv'], ['2000', 'wv']], 'a-fade', '--d:16.5s'],
    [8, [['800', 'dv'], '', '', '', ''], 'a-fade', '--d:17s'],
]);

// Scene 13 — no header → error → header added.
$xl13 = $xl('Supplier price list 2026.xlsx', [
    [1, [['<span class="a-out" style="--d:12s">&nbsp;</span><span class="a-type" style="--d:12s;--ts:6;--tt:.6s">Band A</span>', 'bh stk a-ring', '--d:12.8s'], '', '', '', '']],
    [2, ['', '600mm', '900mm', '1200mm', '1500mm']],
    [3, ['1000', '&pound;24.10', '&pound;28.40', '&pound;32.70', '&pound;37.00']],
], '', 'max-width:22rem');

// The band list rows.
$bandList = static function (array $rows, string $cls = '', string $style = ''): string {
    $h = '<div class="bl ' . $cls . '" style="' . $style . '"><div class="blh"><span>Band</span><span class="nm">Name</span><span class="num">Cells</span><span>Updated</span><span></span></div>';
    foreach ($rows as $r) {
        [$band, $name, $cells, $upd] = $r;
        $rc = $r[4] ?? ''; $rs = $r[5] ?? '';
        $h .= '<div class="blr ' . $rc . '" style="' . $rs . '"><span><span class="bp">Band ' . $band . '</span></span><span class="nm">' . $name . '</span>'
            . '<span class="num">' . $cells . '</span><span class="faint">' . $upd . '</span><span class="ra"><span class="lnk">Open</span> <span class="del">Delete</span></span></div>';
    }
    return $h . '</div>';
};
$cnt = static fn (string $from, string $to, float $d): string =>
    '<span class="swp"><span class="a-out' . ($from === '0' ? ' e0' : '') . '" style="--d:' . $d . 's">' . $from . '</span><span class="a-fade' . ($to === '0' ? ' e0' : '') . '" style="--d:' . $d . 's">' . $to . '</span></span>';

$bl1 = $bandList([
    ['A', '', $cnt('0', '16', 12), $cnt('2 days ago', 'just now', 12)],
    ['B', '', $cnt('0', '16', 12.6), $cnt('2 days ago', 'just now', 12.6)],
], 'a-rise', '--d:9s');
$bl2 = $bandList([
    ['A', '', '<span class="e0">0</span>', '2 days ago'],
    ['B', '', '<span class="e0">0</span>', '2 days ago'],
    ['C', '', '<span class="e0">0</span>', '2 days ago'],
], 'a-rise', '--d:21.5s');
$bl14 = $bandList([
    ['A', '', $cnt('16', '0', 15.5), 'just now'],
    ['B', '', $cnt('16', '0', 15.8), 'just now'],
    ['C', '', $cnt('16', '0', 16.1), 'just now'],
    ['D', 'Imported 2026-10-07', '16', 'just now', 'gonerow', '--d:17.5s'],
]);
$bl15 = $bandList([
    ['A', '', '16', 'just now', 'a-ring', '--d:19s'],
    ['B', '', '16', 'just now'],
    ['C', '', '16', 'just now'],
], 'a-rise', '--d:17s');

// Scene 10 — Band A's prices replaced.
$old = ['22.00', '26.10', '30.20', '34.40', '25.60', '30.10', '34.50', '39.00'];
$new = ['24.10', '28.40', '32.70', '37.00', '27.90', '32.80', '37.60', '42.50'];
$mini = static function (array $vals, ?array $to = null, float $d = 0) {
    $h = '<div class="mg"><span class="mc hd">&nbsp;</span><span class="mc hd">600</span><span class="mc hd">900</span><span class="mc hd">1200</span><span class="mc hd">1500</span>';
    foreach ([1000, 1500] as $ri => $drop) {
        $h .= '<span class="mc hd">' . $drop . '</span>';
        foreach (range(0, 3) as $wi) {
            $i = $ri * 4 + $wi;
            $h .= $to
                ? '<span class="mc swp"><span class="a-out" style="--d:' . ($d + $i * .25) . 's">' . $vals[$i] . '</span><span class="a-pop" style="--d:' . ($d + .1 + $i * .25) . 's">' . $to[$i] . '</span></span>'
                : '<span class="mc">' . $vals[$i] . '</span>';
        }
    }
    return $h . '</div>';
};
$g10a = $mini($old, $new, 6.5);
$g16 = (static function (): string {
    $v = ['24.10', '28.40', '32.70', '37.00', '27.90', '32.80', '37.60', ''];
    $h = '<div class="mg"><span class="mc hd">&nbsp;</span><span class="mc hd">600</span><span class="mc hd">900</span><span class="mc hd">1200</span><span class="mc hd">1500</span>';
    foreach ([1000, 1500] as $ri => $drop) {
        $h .= '<span class="mc hd">' . $drop . '</span>';
        foreach (range(0, 3) as $wi) {
            $i = $ri * 4 + $wi;
            $h .= $v[$i] === ''
                ? '<span class="mc emp a-ring" style="--d:11s">&nbsp;</span>'
                : '<span class="mc"><span class="ok a-pop" style="--d:' . (5 + $i * .3) . 's">&#10003;</span>' . $v[$i] . '</span>';
        }
    }
    return $h . '</div>';
})();
$g10c = $mini(['21.00', '25.00', '29.10', '33.20', '24.40', '28.90', '33.30', '37.80']);

$demo = <<<HTML
          <div class="demo-shell">
            <div class="demo-bar"><i></i><i></i><i></i><span>yourblinds.uk / products / price tables</span></div>
            <div class="app">
              <div class="side">
                <div class="logo">Your<b>Blinds</b></div><small>ADMIN CONSOLE</small>
                <div class="navh">Work</div>
                <a>Dashboard</a><a>Calendar</a>
                <div class="navh">Retail</div>
                <a>Customers</a><a>Quotes</a>
                <div class="navh">Setup <span class="chev">&#9662;</span></div>
                <a class="on">Products</a><a>Users</a><a>Settings</a><a>Trade terms</a>
              </div>
              <div class="stage" id="gdStage" data-step="0">

                <!-- 0 — poster -->
                <div class="sc" data-scene="0">
                  <div class="sct">Importing price tables from a spreadsheet</div>
                  <p class="scs">Your supplier&rsquo;s file in &mdash; every band filled in one go.</p>
                  {$xlPoster}
                  <p class="scs" style="margin-top:.8rem">Press <b>&#9654; Play</b> below &mdash; sixteen short chapters, at an easy pace.</p>
                </div>

                <!-- 1 — why -->
                <div class="sc" data-scene="1" data-len="{$L1}">
                  <div class="sct a-fade" style="--d:.2s">Why import a spreadsheet?</div>
                  <div class="side2">
                    <div>{$xl1}</div>
                    <div>
                      <div class="ttl a-fade" style="--d:9s">Roller Blind &mdash; Standard</div>
                      {$bl1}
                      <span class="chip a-pop" style="--d:14.5s;margin-top:.6rem">One file &rarr; every band</span>
                    </div>
                  </div>
                  <span class="slow a-pop" style="--d:1s">&#9998; one square at a time = slow</span>
                </div>

                <!-- 2 — getting there -->
                <div class="sc" data-scene="2" data-len="{$L2}">
                  <div class="sct a-fade" style="--d:.2s">Getting there</div>
                  <div class="crumbs">
                    <span class="chip a-pop" style="--d:1s">Products</span><span class="arrow a-fade" style="--d:1.8s">&rarr;</span>
                    <span class="chip a-pop" style="--d:2.4s">Roller Blind</span><span class="arrow a-fade" style="--d:3.2s">&rarr;</span>
                    <span class="chip a-pop" style="--d:4s">Systems: Standard</span><span class="arrow a-fade" style="--d:5s">&rarr;</span>
                    <span class="chip a-pop" style="--d:5.8s">Open</span>
                  </div>
                  <div class="bcr a-fade" style="--d:9s">Products &rsaquo; Roller Blind &rsaquo; Systems &rsaquo; Standard &rsaquo; Price tables</div>
                  <div class="ttl a-fade" style="--d:11s">Roller Blind &mdash; Standard</div>
                  <div class="tools a-rise" style="--d:13s">
                    <span class="btns">&larr; Back to setup wizard</span>
                    <span class="btns a-ring" style="--d:17s">Single-band import</span>
                    <span class="btns a-ring" style="--d:19.5s">Bulk import (multiple bands)</span>
                    <span class="btnp">Next: options &rarr;</span>
                  </div>
                  {$bl2}
                </div>

                <!-- 3 — which button -->
                <div class="sc" data-scene="3" data-len="{$L3}">
                  <div class="sct a-fade" style="--d:.2s">Which button?</div>
                  <div class="two">
                    <div class="kind a-rise" style="--d:3s"><span class="btns">Bulk import (multiple bands)</span>
                      <p>Reads <b>every band</b> in the file and fills them all. The one you&rsquo;ll use most.</p>
                      <div class="bpills"><span class="bp a-pop" style="--d:5s">Band A</span><span class="bp a-pop" style="--d:5.3s">Band B</span><span class="bp a-pop" style="--d:5.6s">Band C</span><span class="bp a-pop" style="--d:5.9s">Band D</span></div></div>
                    <div class="kind a-rise" style="--d:9.5s"><span class="btns">Single-band import</span>
                      <p>Takes <b>just one band</b> from the file.</p>
                      <div class="bpills"><span class="bp a-pop" style="--d:11s">Band C</span></div></div>
                  </div>
                  <div class="sysrow">
                    <span class="sys a-pop" style="--d:14.5s">System: <b>Standard</b></span>
                    <span class="arrow a-fade" style="--d:16s">then</span>
                    <span class="sys a-pop" style="--d:16.5s">System: <b>Motorised</b></span>
                  </div>
                  <p class="scs a-fade" style="--d:18s;margin-top:.5rem">Each import fills <b>one system</b>. Import each one in turn.</p>
                </div>

                <!-- 4 — band header -->
                <div class="sc" data-scene="4" data-len="{$L4}">
                  <div class="sct a-fade" style="--d:.2s">The band header</div>
                  <div class="side2">
                    <div class="a-rise" style="--d:1.5s">{$xl4}</div>
                    <div class="notes">
                      <span class="chip a-pop" style="--d:8s"><b>Band A</b> in column A</span>
                      <span class="chip a-pop" style="--d:12.5s"><b>Price Band B</b> works too</span>
                      <span class="chip a-pop" style="--d:17.5s"><b>Band B / C</b> &rarr; same prices in both</span>
                      <span class="chip a-pop" style="--d:22.5s">Above the first header = ignored</span>
                    </div>
                  </div>
                </div>

                <!-- 5 — widths then drops -->
                <div class="sc" data-scene="5" data-len="{$L5}">
                  <div class="sct a-fade" style="--d:.2s">Widths, then drops</div>
                  <div class="side2">
                    <div class="a-rise" style="--d:1s">{$xl5}</div>
                    <div class="notes">
                      <div class="conv a-pop" style="--d:7s">mm &middot; cm &middot; m &mdash; worked out for you</div>
                      <div class="wrow"><span class="w a-pop" style="--d:12.5s">600</span><span class="w a-pop" style="--d:12.8s">900</span><span class="w a-pop" style="--d:13.1s">1200</span><span class="w a-pop" style="--d:13.4s">1500</span><span class="faint" style="font-size:.66rem">&nbsp;mm</span></div>
                      <span class="chip a-pop" style="--d:17.5s;text-decoration:line-through;color:var(--faint)">WIDTH &middot; DROP &middot; Metric</span>
                      <span class="chip a-pop" style="--d:21.5s">Each row: <b>drop</b> first, then prices</span>
                    </div>
                  </div>
                </div>

                <!-- 6 — prices and gaps -->
                <div class="sc" data-scene="6" data-len="{$L6}">
                  <div class="sct a-fade" style="--d:.2s">Prices and gaps</div>
                  <div class="side2">
                    <div class="a-rise" style="--d:.8s">{$xl6}</div>
                    <div class="notes">
                      <div class="clean a-pop" style="--d:3s"><span class="o">&pound;1,024.10</span> <span class="arrow">&rarr;</span> <b>1024.10</b></div>
                      <span class="chip a-pop" style="--d:9.5s;border-color:var(--err);color:var(--err)">Blank or 0 = no price at that size</span>
                      <span class="chip a-pop" style="--d:14s">A quote at that size won&rsquo;t price</span>
                      <span class="chip a-pop" style="--d:18s">Stack bands down one sheet</span>
                    </div>
                  </div>
                </div>

                <!-- 7 — start from a blank sheet -->
                <div class="sc" data-scene="7" data-len="{$L7}">
                  <div class="sct a-fade" style="--d:.2s">Start from a blank sheet</div>
                  <div class="side2">
                    <div>
                      <div class="tip a-rise" style="--d:2s"><b>Bulk import &mdash; What this expects</b> &hellip; Starting from scratch?
                        <span class="chip a-pop" style="--d:5.5s;border-color:var(--accent);color:var(--accent);margin-top:.35rem">&#11015; Download a blank template (.xlsx)</span></div>
                      <span class="chip ok a-pop" style="--d:9s;margin-top:.5rem">Already laid out &mdash; a block for each band</span>
                    </div>
                    <div class="notes">
                      <div class="a-rise" style="--d:13s">{$xl7}</div>
                      <span class="chip a-pop" style="--d:16.5s">A1: <b>Band A</b></span>
                      <span class="chip a-pop" style="--d:20.5s">Widths across row 2, <b>from column B</b></span>
                      <span class="chip a-pop" style="--d:25s">Drops down <b>column A</b></span>
                    </div>
                  </div>
                </div>

                <!-- 8 — fill it in, then the next band -->
                <div class="sc" data-scene="8" data-len="{$L8}">
                  <div class="sct a-fade" style="--d:.2s">Fill it in, then the next band</div>
                  <div class="side2">
                    <div class="a-rise" style="--d:.8s">{$xl8}</div>
                    <div class="notes">
                      <span class="chip a-pop" style="--d:4.5s">Each price under its width, next to its drop</span>
                      <span class="chip a-pop" style="--d:8.5s;border-color:var(--err);color:var(--err)">Empty square = no price at that size</span>
                      <span class="chip a-pop" style="--d:11.5s">One blank row, then <b>Band B</b></span>
                      <span class="chip ok a-pop" style="--d:21s">&#128190; Save &mdash; ready to upload</span>
                    </div>
                  </div>
                </div>

                <!-- 7 — upload -->
                <div class="sc" data-scene="9" data-len="{$L9}">
                  <div class="sct a-fade" style="--d:.2s">Upload the file</div>
                  <div class="ttl a-fade" style="--d:.8s">Bulk import &mdash; Roller Blind / Standard</div>
                  <div class="lnk sm a-fade" style="--d:1s">&larr; Back to Standard price tables</div>
                  <div class="tip a-rise" style="--d:2.5s"><b>What this expects</b> A multi-band Excel file with each band block looking like this: one row with <code>Band X</code> in column A &middot; a widths row &middot; data rows: drop in column A, prices across&hellip;</div>
                  <div class="upl a-rise" style="--d:7s">
                    <div class="lbl2">Multi-band file (.xlsx)</div>
                    <div class="filerow"><span class="btns a-press" style="--d:8.5s">Choose file</span>
                      <span class="fname"><span class="faint a-out" style="--d:9.5s">No file chosen</span><span class="a-type" style="--d:9.5s;--ts:20;--tt:.8s">Supplier price list 2026.xlsx</span></span></div>
                    <div class="tools"><span class="btnp a-press" style="--d:11s">Upload &amp; import &rarr;</span><span class="btns">Cancel</span></div>
                  </div>
                  <span class="chip a-pop" style="--d:13s;margin-top:.6rem">.xlsx &middot; .xlsm &middot; .xls &middot; .csv &middot; .ods &mdash; up to 10&nbsp;MB</span>
                  <div class="a-move" style="--fx:80%;--fy:30%;--tx:5.2rem;--ty:13rem;--d:6.8s;--md:1.6s">{$ptr}</div>
                </div>

                <!-- 8 — which worksheet -->
                <div class="sc" data-scene="10" data-len="{$L10}">
                  <div class="sct a-fade" style="--d:.2s">Which worksheet?</div>
                  <div class="tabs a-fade" style="--d:1s"><span class="tb">25mm</span><span class="tb">50mm</span><span class="tb">Notes</span></div>
                  <div class="h2 a-fade" style="--d:10s">Which worksheet?</div>
                  <p class="fsh a-fade" style="--d:10.5s">This file has more than one worksheet with bands &mdash; common when one file holds several slat sizes. Pick the one to import into <b>Standard</b>.</p>
                  <div class="opt2 a-rise" style="--d:13.5s"><span class="dot dotoff"><i class="a-out" style="--d:17s"></i></span><span><b>25mm</b> <span class="faint">&mdash; 4 bands, 160 cells</span></span></div>
                  <div class="opt2 a-rise" style="--d:14s"><span class="dot a-sel" style="--d:17s"></span><span><b>50mm</b> <span class="faint">&mdash; 3 bands, 96 cells</span></span></div>
                  <div class="tools"><span class="btnp a-press" style="--d:19.5s">Import selected sheet into Standard &rarr;</span><span class="btns">Start over</span></div>
                </div>

                <!-- 9 — success -->
                <div class="sc" data-scene="11" data-len="{$L11}">
                  <div class="sct a-fade" style="--d:.2s">What you get</div>
                  <div class="okbanner a-pop" style="--d:2s"><span>&#10003;</span><div>Imported <b>4</b> bands into <b>Standard</b>:</div></div>
                  <ul class="sl">
                    <li class="a-fly" style="--d:4.5s"><span class="bp">Band A</span> &mdash; 16 cells</li>
                    <li class="a-fly" style="--d:5s"><span class="bp">Band B</span> &mdash; 16 cells</li>
                    <li class="a-fly" style="--d:5.5s"><span class="bp">Band C</span> &mdash; 16 cells</li>
                    <li class="a-fly" style="--d:6s"><span class="bp new">Band D</span> &mdash; 16 cells <span class="newtag a-pop" style="--d:11.5s">created</span></li>
                  </ul>
                  <div class="aon a-pop" style="--d:13.5s">All or nothing &mdash; never half a file</div>
                </div>

                <!-- 10 — replaces -->
                <div class="sc" data-scene="12" data-len="{$L12}">
                  <div class="sct a-fade" style="--d:.2s">Importing replaces</div>
                  <div class="two">
                    <div class="kind a-rise" style="--d:2s"><h4><span class="bp">Band A</span> in the file</h4>{$g10a}<p class="a-fade" style="--d:9s">Old prices wiped &mdash; the file&rsquo;s go in.</p></div>
                    <div class="kind a-rise" style="--d:13.5s"><h4><span class="bp">Band E</span> not in the file</h4>{$g10c}<p class="a-fade" style="--d:15s">Left exactly as it was.</p></div>
                  </div>
                  <span class="chip a-pop" style="--d:17.5s;margin-top:.7rem">Just one band to update? Import a file with only that band.</span>
                </div>

                <!-- 11 — next system -->
                <div class="sc" data-scene="13" data-len="{$L13}">
                  <div class="sct a-fade" style="--d:.2s">On to the next system</div>
                  <div class="okbanner" style="margin-bottom:.6rem"><span>&#10003;</span><div>Imported <b>4</b> bands into <b>Standard</b>:</div></div>
                  <div class="swapblk">
                    <div class="a-mid" style="--d:3s;--d2:12.5s"><p class="nx">Got prices for the other system too? Import next:</p>
                      <div class="tools"><span class="btnp a-ring" style="--d:9s">Import Motorised &rarr;</span><span class="btns">View Standard prices</span><span class="btns">Back to setup wizard</span></div></div>
                    <div class="a-fade" style="--d:13s"><p class="nx good">&#10003; That&rsquo;s every system priced for this product &mdash; it&rsquo;s ready to quote.</p>
                      <div class="tools"><span class="btns a-ring" style="--d:19.5s">View Standard prices</span><span class="btnp">Back to setup wizard</span></div></div>
                  </div>
                </div>

                <!-- 12 — single-band -->
                <div class="sc" data-scene="14" data-len="{$L14}">
                  <div class="sct a-fade" style="--d:.2s">Single-band import</div>
                  <div class="ttl a-fade" style="--d:.8s">Single-band import &mdash; Roller Blind / Standard</div>
                  <div class="side2">
                    <div class="filecard a-rise" style="--d:3s">
                      <div class="fc"><span class="bp a-ring" style="--d:7s">Band C</span> <span class="faint">16 prices</span><span class="usedtag a-pop" style="--d:9s">used</span></div>
                      <div class="fc dimx"><span class="bp">Band D</span> <span class="faint">16 prices</span><span class="skiptag a-pop" style="--d:17s">ignored</span></div>
                    </div>
                    <div class="lbl2 a-fade" style="--d:3s">Single-band file (.xlsx)<div class="tools" style="margin-top:.3rem"><span class="btnp a-press" style="--d:11s">Upload &amp; import</span><span class="btns">Cancel</span></div></div>
                  </div>
                  <div class="okbanner blk2 a-pop" style="--d:12.5s"><span>&#10003;</span><div>Imported <span class="bp">Band C</span> &mdash; <b>16</b> cells (new price table created).
                    <em class="a-fade" style="--d:17.5s">The file contained 1 other band; only Band C was used. To bring all of them in at once, use <b>Bulk import</b> instead.</em></div></div>
                  <div class="tools a-fade" style="--d:20s"><span class="btnp">View price table</span><span class="btns">Back to price tables</span></div>
                </div>

                <!-- 13 — errors -->
                <div class="sc" data-scene="15" data-len="{$L15}">
                  <div class="sct a-fade" style="--d:.2s">When a file won&rsquo;t read</div>
                  <div class="bstack"><div class="errbanner a-mid" style="--d:4.5s;--d2:13s"><span>&#9888;</span><div><b>No band sections detected. Each band block should start with a row containing &ldquo;Band X&rdquo; in column A.</b></div></div>
                  <div class="okbanner a-pop" style="--d:13.5s"><span>&#10003;</span><div>Imported <b>1</b> band into <b>Standard</b>:</div></div></div>
                  <div style="margin-top:.7rem">{$xl13}</div>
                  <div class="tools">
                    <span class="chip a-pop" style="--d:15s;border-color:var(--err);color:var(--err)">Please choose a file to upload.</span>
                    <span class="chip a-pop" style="--d:17.5s;border-color:var(--err);color:var(--err)">File too large (10 MB max).</span>
                  </div>
                </div>

                <!-- 14 — undo -->
                <div class="sc" data-scene="16" data-len="{$L16}">
                  <div class="sct a-fade" style="--d:.2s">Undo an import</div>
                  <div class="bstack">
                    <div class="undobar a-mid" style="--d:5s;--d2:15s"><span>Last change: <b>Band import (this system)</b> &middot; 7 Oct 10:42</span>
                      <span class="btns a-ring" style="--d:10.5s">&#8630; Undo Band import</span></div>
                    <div class="flash a-pop" style="--d:15s">Undone: Band import &mdash; 64 prices are back to what they were.</div>
                  </div>
                  {$bl14}
                  <div class="confirm a-mid" style="--d:12s;--d2:14.5s">Undo &ldquo;Band import (this system)&rdquo;? Every price it changed goes back to exactly what it was before (7 Oct 10:42). Unsaved edits on this page will be lost.
                    <div class="act"><span class="btns">Cancel</span><span class="btnp a-press" style="--d:13.5s">OK</span></div></div>
                </div>

                <!-- 15 — stale -->
                <div class="sc" data-scene="17" data-len="{$L17}">
                  <div class="sct a-fade" style="--d:.2s">When undo says no</div>
                  <div class="seq">
                    <span class="chip a-pop" style="--d:2s">Band import &middot; 10:42</span><span class="arrow a-fade" style="--d:3s">&rarr;</span>
                    <span class="chip a-pop" style="--d:4s">&#9998; Band A edited &middot; 11:05</span>
                  </div>
                  <div class="undobar stale a-rise" style="--d:12s"><span>Last change: <b>Band import (this system)</b> &middot; 7 Oct 10:42 &mdash; can&rsquo;t be undone, the prices have been edited since.</span></div>
                  <span class="chip a-pop" style="--d:9.5s;margin-bottom:.6rem">Never overwrites newer work</span>
                  {$bl15}
                </div>

                <!-- 16 — check the result -->
                <div class="sc" data-scene="18" data-len="{$L18}">
                  <div class="sct a-fade" style="--d:.2s">Check the result</div>
                  <div class="ttl a-fade" style="--d:2s">Roller Blind / Standard &mdash; Band A</div>
                  <div class="side2">
                    <div class="a-rise" style="--d:3s">{$g16}</div>
                    <div class="notes">
                      <span class="chip a-pop" style="--d:7.5s">&#10003; matches the supplier&rsquo;s sheet</span>
                      <span class="chip a-pop" style="--d:10.5s;border-color:var(--err);color:var(--err)">Empty square at the edge?</span>
                    </div>
                  </div>
                  <div class="psbar a-rise" style="--d:17.5s"><b>Supplier price list</b>
                    <span class="pshint">These are the supplier&rsquo;s list prices. Our price = list &minus; buying discount + margin.</span>
                    <span class="lnk a-ring" style="--d:21s">Change</span></div>
                  <p class="scs a-fade" style="--d:19s">The import copies numbers in. <b>Whose</b> numbers they are is set on the product.</p>
                </div>

              </div>
            </div>
          </div>
HTML;

return [
        'aud'     => 'admin',
        'section' => 'Products',
        'title'   => 'Importing price tables from a spreadsheet',
        'eyebrow' => 'Products',
        'v'       => 2,
        'blurb'   => 'Load a supplier\'s spreadsheet straight into your price tables — every band at once with Bulk import, or one band with Single-band import — what shape the file needs, and how to undo an import.',
        'lede'    => 'When your supplier sends their prices as a <b>spreadsheet</b>, you don&rsquo;t need to type them in. <b>Bulk import</b>
                      reads every band in the file and fills a whole system in one go; <b>Single-band import</b> takes just one band.
                      This guide covers where the buttons are, <b>what shape the file must be</b>, what you see when it works or
                      doesn&rsquo;t, and how to <b>undo</b> an import &mdash; slowly, one idea per chapter. To get there: <b>Products</b>
                      &rarr; the product &rarr; <b>Systems</b> &rarr; <b>Open</b> on the system.',
        'open'    => '/admin/products/index.php',
        'css'     => '
          .gd .sc{ position:relative; min-height:360px; }
          .gd .sct{ font-weight:800; font-size:.92rem; color:var(--ink); margin:0 0 .6rem; }
          .gd .scs{ font-size:.7rem; color:var(--soft); margin:0 0 .7rem; }
          .gd .faint{ color:var(--faint); font-weight:400; }
          .gd .ttl{ font-size:.86rem; font-weight:800; color:var(--ink); margin:0 0 .3rem; }
          .gd .chip{ display:inline-flex; align-items:center; gap:.3rem; border:1px solid var(--line); background:var(--surface); border-radius:999px;
                     padding:.28rem .7rem; font-size:.72rem; font-weight:700; color:var(--ink); }
          .gd .arrow{ color:var(--faint); font-weight:800; margin:0 .2rem; font-size:.74rem; }
          .gd .crumbs{ display:flex; align-items:center; flex-wrap:wrap; gap:.2rem; margin-bottom:.7rem; }
          .gd .bcr{ font-size:.62rem; color:var(--faint); margin-bottom:.2rem; }
          .gd .btnp{ display:inline-flex; align-items:center; background:var(--accent); color:#fff; border-radius:7px; padding:.34rem .8rem; font-size:.72rem; font-weight:700; }
          .gd .btns{ display:inline-flex; align-items:center; background:var(--surface); border:1px solid var(--border-strong,#c7ccd4);
                     color:var(--ink); border-radius:7px; padding:.3rem .65rem; font-size:.7rem; font-weight:600; }
          .gd .lnk{ color:var(--accent); font-weight:600; font-size:.72rem; }
          .gd .lnk.sm{ font-size:.66rem; margin-bottom:.5rem; }
          .gd .tools{ display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; margin:.55rem 0; }
          .gd .bp{ display:inline-block; padding:.05rem .45rem; font-weight:700; font-size:.66rem; color:#fff; background:#1f3b5b; border-radius:6px; white-space:nowrap; }
          .gd .side2{ display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); gap:1rem; align-items:start; }
          .gd .notes{ display:flex; flex-direction:column; align-items:flex-start; gap:.5rem; }
          .gd .two{ display:grid; grid-template-columns:1fr 1fr; gap:.8rem; }
          .gd .kind{ border:1px solid var(--line); border-radius:10px; padding:.6rem .7rem; background:var(--surface); }
          .gd .kind h4{ margin:0 0 .4rem; font-size:.76rem; color:var(--ink); }
          .gd .kind p{ margin:.45rem 0 0; font-size:.68rem; color:var(--soft); line-height:1.45; }
          .gd .bpills{ display:flex; gap:.3rem; flex-wrap:wrap; margin-top:.45rem; }

          /* the spreadsheet */
          .gd .xl{ border:1px solid #1d6f42; border-radius:8px; overflow:hidden; font-size:.62rem; background:#fff; color:#1f2937; max-width:24rem; }
          .gd .xl .xh{ background:#1d6f42; color:#fff; font-weight:700; padding:.28rem .5rem; font-size:.64rem; }
          .gd .xl .xg{ display:grid; grid-template-columns:1.4rem 3.4rem repeat(4,1fr); }
          .gd .xl .xg > span{ border-right:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; padding:.2rem .25rem; min-height:1.05rem;
                              font-variant-numeric:tabular-nums; white-space:nowrap; overflow:visible; text-align:right; position:relative; }
          .gd .xl .cn, .gd .xl .rn{ background:#f3f4f6; color:#6b7280; text-align:center !important; font-weight:600; }
          .gd .xl .bh{ font-weight:800; color:#1d6f42; text-align:left; z-index:1; }
          .gd .xl .wide{ text-align:left; z-index:1; }
          .gd .xl .lbl{ color:#9ca3af; text-align:left; font-style:italic; }
          .gd .xl .wv{ background:#ecfdf5; font-weight:700; }
          .gd .xl .dv{ background:#eff6ff; font-weight:700; text-align:left; }
          .gd .xl .blank{ background:rgba(220,38,38,.12); }
          .gd .xl .stk{ display:grid; } .gd .xl .stk > span{ grid-area:1/1; }

          /* band list */
          .gd .bl{ border:1px solid var(--line); border-radius:9px; overflow:hidden; font-size:.68rem; max-width:28rem; margin-top:.5rem; }
          .gd .bl > div{ display:grid; grid-template-columns:3.9rem minmax(0,1fr) 2.3rem 4.3rem auto; gap:.3rem; align-items:center; padding:.32rem .55rem; border-top:1px solid var(--line); color:var(--ink); }
          .gd .bl .blh{ border-top:0; background:var(--panel); font-weight:700; color:var(--soft); font-size:.58rem; text-transform:uppercase; letter-spacing:.04em; }
          .gd .bl .num{ text-align:right; font-variant-numeric:tabular-nums; }
          .gd .bl .nm{ color:var(--soft); font-size:.62rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
          .gd .bl .ra{ text-align:right; white-space:nowrap; }
          .gd .bl .del{ color:#b91c1c; font-size:.66rem; margin-left:.3rem; }
          .gd .e0{ color:#b45309; }
          .gd .swp{ display:inline-grid; } .gd .swp > span{ grid-area:1/1; }
          .gd .gd-play .gonerow{ animation:piGone .7s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          .gd .gd-done .gonerow{ display:none !important; }
          @keyframes piGone{ to{ opacity:0; background:var(--err-wash); } }

          /* 1 */
          .gd .slow{ position:absolute; right:0; top:0; background:var(--err-wash); color:var(--err); font-weight:700; font-size:.68rem; border-radius:8px; padding:.3rem .55rem; }

          /* 3 */
          .gd .sysrow{ display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin-top:.9rem; }
          .gd .sys{ border:1px solid var(--line); border-radius:8px; padding:.35rem .6rem; font-size:.72rem; background:var(--panel); color:var(--ink); }

          /* 5/6 */
          .gd .conv{ font-size:.72rem; font-weight:700; color:var(--good); background:var(--good-wash); border-radius:8px; padding:.3rem .55rem; }
          .gd .wrow{ display:flex; gap:.25rem; align-items:center; }
          .gd .wrow .w{ border:1px solid var(--good); color:var(--good); font-weight:800; font-size:.7rem; border-radius:6px; padding:.1rem .35rem; }
          .gd .clean{ font-size:.8rem; color:var(--ink); border:1px solid var(--line); border-radius:8px; padding:.35rem .6rem; }
          .gd .clean .o{ color:var(--faint); text-decoration:line-through; }

          /* 7 — upload page */
          .gd .tip{ background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:.5rem .65rem; font-size:.66rem; color:var(--soft); line-height:1.5; max-width:31rem; }
          .gd .tip b{ display:block; color:var(--ink); font-size:.72rem; margin-bottom:.15rem; }
          .gd .tip code{ background:var(--surface); border:1px solid var(--line); border-radius:4px; padding:0 .25rem; font-size:.62rem; }
          .gd .upl{ margin-top:.7rem; }
          .gd .lbl2{ font-size:.68rem; font-weight:700; color:var(--soft); margin-bottom:.25rem; }
          .gd .filerow{ display:flex; align-items:center; gap:.5rem; border:1px solid var(--line); border-radius:7px; padding:.3rem .4rem; max-width:22rem; background:var(--surface); }
          .gd .fname{ display:inline-grid; font-size:.7rem; color:var(--ink); } .gd .fname > span{ grid-area:1/1; }

          /* 8 */
          .gd .tabs{ display:flex; gap:2px; margin-bottom:.6rem; }
          .gd .tb{ background:#e7f3ec; color:#1d6f42; border:1px solid #1d6f42; border-radius:5px 5px 0 0; font-size:.62rem; font-weight:700; padding:.15rem .55rem; }
          .gd .h2{ font-size:.8rem; font-weight:800; color:var(--ink); margin:0 0 .2rem; }
          .gd .fsh{ font-size:.66rem; color:var(--soft); margin:0 0 .5rem; line-height:1.45; max-width:31rem; }
          .gd .opt2{ display:flex; align-items:center; gap:.55rem; border:1px solid var(--border-strong,#c7ccd4); border-radius:8px; padding:.4rem .6rem; font-size:.72rem;
                     color:var(--ink); margin:0 0 .4rem; max-width:20rem; }
          .gd .dot{ position:relative; flex:none; width:14px; height:14px; border-radius:50%; border:1.5px solid var(--border-strong,#c7ccd4); box-sizing:border-box; background:var(--surface); }
          .gd .gd-done .dot.a-sel{ background:var(--surface) !important; border-color:var(--accent) !important; box-shadow:inset 0 0 0 3px var(--surface), inset 0 0 0 7px var(--accent); }
          .gd .gd-play .dot.a-sel{ animation:piDot .35s ease calc(var(--d,0s) * var(--k,1)) forwards; }
          @keyframes piDot{ to{ border-color:var(--accent); box-shadow:inset 0 0 0 3px var(--surface), inset 0 0 0 7px var(--accent); } }
          .gd .dotoff i{ position:absolute; inset:2.5px; border-radius:50%; background:var(--accent); }

          /* 9 */
          .gd .sl{ list-style:disc; margin:.6rem 0 0; padding-left:1.2rem; font-size:.72rem; color:var(--soft); }
          .gd .sl li{ margin:.25rem 0; }
          .gd .newtag{ font-size:.6rem; font-weight:800; color:var(--good); background:var(--good-wash); border-radius:999px; padding:.05rem .4rem; margin-left:.3rem; }
          .gd .aon{ display:inline-block; margin-top:.8rem; font-size:.74rem; font-weight:800; color:var(--accent-ink); background:var(--accent-wash); border-radius:8px; padding:.35rem .65rem; }

          /* 10 — mini grid */
          .gd .mg{ display:grid; grid-template-columns:2.6rem repeat(4,1fr); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:6px; overflow:hidden; }
          .gd .mc{ background:var(--surface); text-align:center; font-size:.62rem; padding:.22rem .1rem; font-variant-numeric:tabular-nums; color:var(--ink); }
          .gd .mc.hd{ background:var(--panel); color:var(--soft); font-weight:700; font-size:.58rem; }
          .gd .mc.swp{ display:grid; } .gd .mc.swp > span{ grid-area:1/1; }

          /* 11 */
          .gd .swapblk{ display:grid; } .gd .swapblk > div{ grid-area:1/1; }
          .gd .nx{ font-size:.74rem; color:var(--soft); margin:.2rem 0 .1rem; }
          .gd .nx.good{ color:var(--good); font-weight:700; }

          /* 12 */
          .gd .filecard{ border:1px solid #1d6f42; border-radius:8px; padding:.4rem .5rem; background:#fff; max-width:15rem; }
          .gd .fc{ display:flex; align-items:center; gap:.4rem; font-size:.68rem; padding:.2rem 0; color:#1f2937; }
          .gd .fc.dimx{ opacity:.7; }
          .gd .usedtag, .gd .skiptag{ margin-left:auto; font-size:.58rem; font-weight:800; border-radius:999px; padding:.05rem .4rem; }
          .gd .usedtag{ background:rgba(21,157,92,.14); color:#159d5c; } .gd .skiptag{ background:rgba(220,38,38,.12); color:#dc2626; }
          .gd .blk2{ margin-top:.7rem; align-items:flex-start; max-width:31rem; } .gd .blk2 em{ display:block; font-weight:400; margin-top:.2rem; font-size:.7rem; }

          /* 14/15 — undo */
          .gd .undobar{ display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; background:var(--panel); border:1px solid var(--line); border-radius:8px;
                        padding:.45rem .6rem; margin-bottom:.5rem; font-size:.7rem; color:var(--soft); max-width:31rem; }
          .gd .undobar .btns{ margin-left:auto; }
          .gd .undobar.stale{ border-color:#f59e0b; }
          .gd .confirm{ position:absolute; z-index:5; left:8%; top:30%; max-width:21rem; background:var(--surface); border:1px solid var(--line);
                        border-radius:12px; box-shadow:var(--gd-shadow); padding:.7rem .8rem; font-size:.7rem; color:var(--ink); line-height:1.45; }
          .gd .confirm .act{ display:flex; justify-content:flex-end; gap:.4rem; margin-top:.55rem; }
          .gd .flash{ align-self:start; background:var(--good-wash); border-left:3px solid var(--good); border-radius:8px;
                      padding:.45rem .6rem; font-size:.72rem; font-weight:700; color:var(--ink); max-width:31rem; }
          .gd .bstack{ display:grid; } .gd .bstack > div{ grid-area:1/1; }
          .gd .mc.emp{ background:var(--err-wash); }
          .gd .mc .ok{ color:var(--good); font-weight:800; margin-right:.15rem; font-size:.56rem; }
          .gd .psbar{ border:1px solid #f59e0b; background:rgba(245,158,11,.08); border-radius:10px; padding:.5rem .7rem; display:flex; gap:.5rem;
                      align-items:baseline; flex-wrap:wrap; margin:.9rem 0 .5rem; max-width:31rem; font-size:.78rem; color:var(--ink); }
          .gd .pshint{ font-size:.66rem; color:var(--faint); }
          .gd .psbar .lnk{ margin-left:auto; }
          .gd .seq{ display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; margin-bottom:.7rem; }

          @media (max-width:640px){
            .gd .app{ grid-template-columns:1fr; } .gd .side{ display:none; } .gd .stage{ min-width:0; padding:.9rem .8rem; }
            .gd .sc{ min-height:460px; }
            .gd .side2, .gd .two{ grid-template-columns:1fr; }
            .gd .slow{ position:static; display:inline-block; margin-bottom:.5rem; }
            .gd .bl > div{ grid-template-columns:3.6rem 0 2.4rem 4.2rem 1fr; }
            .gd .bl .nm{ visibility:hidden; }
            .gd .sc[data-scene="9"] .a-move{ display:none; }
            .gd .confirm{ left:4%; right:4%; }
          }',
        'demo'    => $demo,
        'body'    => '
          <p><b>Getting here.</b> <b>Products</b> &rarr; click the product &rarr; in its <b>Systems</b> section click <b>Open</b> on the system
             (or the system&rsquo;s name in the <b>Price tables</b> section, which also has a <b>Bulk import &raquo;</b> link). You land on that
             system&rsquo;s band list, titled e.g. <b>&ldquo;Roller Blind &mdash; Standard&rdquo;</b>. Across the top:
             <b>&larr; Back to setup wizard</b>, <b>Single-band import</b>, <b>Bulk import (multiple bands)</b> and <b>Next: options &rarr;</b>.
             The <b>Price tables</b> list shows each band with its <b>Cells</b> count (an empty band shows <b>0</b>) and when it was
             <b>Updated</b>.</p>

          <p class="prose"><b>Which import?</b> Both work on <b>one system at a time</b> &mdash; a product with Standard and Motorised prices is
             imported once per system.</p>
          <ul class="steps">
            <li><b>Bulk import (multiple bands)</b> reads every band in the file and fills them all. It&rsquo;s the quickest way to price a
                whole system.</li>
            <li><b>Single-band import</b> takes the <b>first</b> band it finds. That band&rsquo;s own header decides which table it fills
                (a block headed <code>Band C</code> fills Band C). If the file has more bands you&rsquo;re told
                <em>&ldquo;The file contained 1 other band; only Band C was used. To bring all of them in at once, use Bulk import
                instead.&rdquo;</em></li>
          </ul>

          <p class="prose"><b>Starting from a blank sheet.</b> On the <b>Bulk import</b> page, under <b>What this expects</b>, click
             <b>&#11015; Download a blank template (.xlsx)</b>. You get a workbook already laid out the right way, with a block for each band
             on this system (or <b>Band A</b> and <b>Band B</b> if it has none yet) and the standard 800&ndash;4000&nbsp;mm sizes &mdash;
             change the sizes if yours differ, type or paste your prices into the empty squares, save, and upload it. To make one yourself
             in Excel instead:</p>
          <ul class="steps">
            <li>In a new, blank workbook, type <code>Band A</code> in cell <b>A1</b>.</li>
            <li>In the next row, leave <b>A2</b> empty and type your widths across from <b>B2</b> (<code>800</code>, <code>1200</code>,
                <code>1600</code>&hellip;) &mdash; millimetres, centimetres or metres, it works out which.</li>
            <li>From <b>A3</b> down, type your drops in column A.</li>
            <li>Fill in each price under its width and beside its drop. Leave a square empty if you don&rsquo;t sell that size.</li>
            <li>For the next band, leave <b>one blank row</b>, type <code>Band B</code> in column A, and repeat the widths and drops under
                it. Add as many bands as you need, one block under another.</li>
            <li>Save it (.xlsx is best) and upload it on the <b>Bulk import</b> page.</li>
          </ul>

          <p class="prose"><b>The shape of the file</b> (the page&rsquo;s own <b>What this expects</b> box says the same):</p>
          <ul class="steps">
            <li><b>A band header row</b> &mdash; <code>Band X</code> (or <code>Price Band X</code>, or the common typo <code>Bnad X</code>) in
                <b>column A</b>. One header can serve several bands with the same grid: <code>Band A / B</code> or <code>Band A, B</code>.
                Band names are stored in capitals. Anything above the first header (a title, a logo) is ignored.</li>
            <li><b>A widths row</b> straight after it, from column B across &mdash; in <b>mm</b> (<code>610mm</code>), <b>cm</b> or
                <b>metres</b> (<code>0.800</code>); worked out per file. Label rows such as <code>DROP</code>, <code>WIDTH</code>,
                <code>Metric</code> or an inches reference row are skipped.</li>
            <li><b>Data rows</b> &mdash; the drop in column A, then the prices across under each width. <b>&pound;</b> signs and commas are
                stripped. A blank or 0 cell is left out, which means <b>no price at that size</b>.</li>
            <li><b>Several bands</b> can be stacked down one sheet, each block under its own header. If the file has <b>several worksheets</b>
                with bands (one per slat size, say), Bulk import asks <b>Which worksheet?</b> &mdash; each listed as e.g.
                <em>25mm &mdash; 4 bands, 160 cells</em> &mdash; then <b>Import selected sheet into Standard &rarr;</b> (or
                <b>Start over</b>).</li>
            <li><b>File types:</b> .xlsx, .xlsm, .xls, .csv, .ods, up to 10&nbsp;MB.</li>
          </ul>

          <p class="prose"><b>Running it.</b> Choose the file under <b>Upload</b> (<b>Multi-band file (.xlsx)</b> or <b>Single-band file
             (.xlsx)</b>), then <b>Upload &amp; import &rarr;</b> / <b>Upload &amp; import</b>.</p>
          <ul class="steps">
            <li><b>Bulk:</b> <code>Imported 4 bands into Standard:</code> with a line per band (<b>Band A &mdash; 16 cells</b>&hellip;). Bands
                that didn&rsquo;t exist are created (named <em>Imported</em> + the date). The whole file goes in as one &mdash; all or nothing.</li>
            <li><b>Single:</b> <code>Imported Band C &mdash; 16 cells (new price table created).</code> or <code>(replaced existing price
                table)</code>, with <b>View price table</b> and <b>Back to price tables</b>.</li>
            <li><b>Importing replaces.</b> Every existing price in each imported band, on this system, is wiped and replaced by the file&rsquo;s.
                Bands not in the file are left alone.</li>
            <li><b>Carry on.</b> If other systems on the product still have empty bands you get <em>&ldquo;Got prices for the other system too?
                Import next:&rdquo;</em> and an <b>Import Motorised &rarr;</b> button; when nothing is left,
                <em>&ldquo;&#10003; That&rsquo;s every system priced for this product &mdash; it&rsquo;s ready to quote.&rdquo;</em> Plus
                <b>View Standard prices</b> and <b>Back to setup wizard</b>.</li>
          </ul>

          <div class="oops"><b>When it won&rsquo;t go in</b> (red message at the top):
             <ul style="margin:.4rem 0 0;padding-left:1.15rem">
               <li><code>No band sections detected. Each band block should start with a row containing "Band X" in column A.</code>
                   (single-band: <code>No band section detected. The file should contain a "Band X" header row.</code>) &rarr; add a header above each block.</li>
               <li><code>Band C had no price cells.</code> &rarr; the header was found but every price under it was blank or unreadable.</li>
               <li><code>Please choose a file to upload.</code> &middot; <code>File too large (10 MB max).</code> &middot;
                   <code>Could not read the file: &hellip;</code> (not a real spreadsheet).</li>
               <li><code>That selection expired — please upload the file again.</code> &rarr; on the <b>Which worksheet?</b> step.</li>
               <li>A product from the <b>factory catalogue</b> can&rsquo;t be imported into: its prices are set by the factory.</li>
             </ul></div>

          <p class="prose"><b>Undo.</b> After an import a strip appears &mdash; on the import page and on the band list &mdash;
             <b>Last change: Band import (this system) &middot; 7 Oct 10:42</b> with <b>&#8630; Undo Band import</b> (a single-band import
             shows as <b>Single-band import</b>). It asks first &mdash; <em>&ldquo;Every price it changed goes back to exactly what it was
             before&hellip; Unsaved edits on this page will be lost.&rdquo;</em> &mdash; then puts every price back and removes any band the
             import created: <code>Undone: Band import &mdash; 64 prices are back to what they were.</code> Undo again to step further back.
             If the prices have been edited since, the strip says <em>&ldquo;can&rsquo;t be undone, the prices have been edited since&rdquo;</em>
             &mdash; undo never overwrites newer work. The band list may show a second strip marked <b>(all systems)</b> for changes made
             across the whole product.</p>

          <div class="heads"><span class="hi">&#9888;</span><div><b>Check the price source first.</b> An import just copies the file&rsquo;s
             numbers into the grid. Whether they&rsquo;re your selling prices or a supplier&rsquo;s list (with your buying discount and markup
             applied) is set on the product &mdash; see <b>Pricing: source, markup &amp; mode</b>. Products priced <b>by width only</b> or
             <b>per slat</b> use their own importers (<b>Import width prices &raquo;</b> / <b>Import rates &raquo;</b> on the product page),
             and a single table can also be filled from its own <b>Advanced &mdash; XLSX import / export</b> section.</div></div>',
        'script'  => $script,
];
