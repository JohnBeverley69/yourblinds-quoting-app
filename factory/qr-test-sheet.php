<?php
declare(strict_types=1);

/**
 * Factory · QR test sheet.
 *
 * Answers one question with evidence instead of arithmetic: how small can a QR
 * code be printed on the die-cut work tickets — inkjet, white uncoated face
 * stock, ink spreading as it dries — and still scan first time?
 *
 * Prints a size sweep onto the REAL label positions of the A4 SET 22TV die, so
 * the test happens on the actual material through the actual printer. Left and
 * right columns repeat the same sizes, because inkjets don't lay ink down
 * evenly across a page.
 *
 * Every code is 8 digits, so every symbol is a version 1 (29 modules incl. the
 * quiet zone) exactly like a real blind's label — only the printed SIZE varies.
 * The code encodes its own cell (9=left/8=right, then the mm), so a scan
 * identifies which square it came from.
 *
 *   Left  column: 9 SS 00000  ->  91400000 = left, 14mm
 *   Right column: 8 SS 00000  ->  81400000 = right, 14mm
 *
 * Print at 100% / "Actual size" — any scaling invalidates the whole test. Check
 * the ruler on the header label with a tape measure before trusting a result.
 *
 * Geometry matches /factory/label-test-sheet.php and is overridable the same
 * way, e.g. ?small=21&top=15&from=10&to=19
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/qr.php';

requireFactory();

$f = static fn (string $k, float $d): float => isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : $d;
$i = static fn (string $k, int $d): int => isset($_GET[$k]) && ctype_digit((string) $_GET[$k]) ? (int) $_GET[$k] : $d;

// Die geometry (mm) — same defaults as the label test sheet.
$topPad    = $f('top', 15);
$leftPad   = $f('left', 10);
$labelW    = $f('w', 90);
$largeH    = $f('large', 50);
$gap       = $f('gap', 7);
$smallH    = $f('small', 21);
$centreGap = $f('centre', 10);

// The size sweep, in mm. 10 steps = the 10 small labels down a column.
$from  = max(6, min(19, $i('from', 10)));
$to    = max($from, min(19, $i('to', 19)));
$steps = [];
for ($s = $from; $s <= $to && count($steps) < 10; $s++) $steps[] = $s;

$cols = [$leftPad, $leftPad + $labelW + $centreGap];
$firstSmallTop = $topPad + $largeH + $gap;

$mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
$modules = qr_module_count('91400000', 'Q');

// Cell code: column marker (9 left / 8 right) + 2-digit mm + padding = 8 digits.
$cellCode = static fn (int $col, int $size): string => ($col === 0 ? '9' : '8') . str_pad((string) $size, 2, '0', STR_PAD_LEFT) . '00000';

$factoryTitle = 'QR test sheet';
$factoryNav   = 'labelsheet';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>QR test sheet</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    @page { size: A4 portrait; margin: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f6f7f9; }

    .panel { max-width: 780px; margin: 1.5rem auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.25rem 1.5rem; }
    .panel h1 { font-size: 1.35rem; margin: 0 0 .4rem; }
    .panel p  { color: #556; font-size: .92rem; margin: .35rem 0; line-height: 1.5; }
    .panel ol { margin: .6rem 0 .6rem 1.2rem; color: #556; font-size: .92rem; line-height: 1.6; }
    .panel .warn { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; padding: .6rem .8rem; border-radius: 8px; margin: .8rem 0; font-size: .9rem; }
    .btn { font: inherit; font-weight: 600; cursor: pointer; border: none; border-radius: 8px; padding: .5rem 1rem; background: #166534; color: #fff; }
    #scan { font: inherit; font-size: 1.1rem; padding: .5rem .7rem; border: 2px solid #166534; border-radius: 8px; width: 14rem; letter-spacing: .05em; }
    #results { margin-top: .8rem; display: flex; flex-wrap: wrap; gap: .35rem; }
    .chip { font-size: .8rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; background: #eef1f5; color: #64748b; }
    .chip.ok { background: #dcfce7; color: #166534; }
    .chip.bad { background: #fee2e2; color: #991b1b; }

    /* The printable sheet. */
    .sheet { position: relative; width: 210mm; height: 297mm; background: #fff; margin: 1rem auto; }
    .lbl { position: absolute; overflow: hidden; }
    .small { display: flex; align-items: center; gap: 2mm; padding: 0 2mm; }
    .small .meta { font-size: 3mm; line-height: 1.35; color: #000; }
    .small .meta b { font-size: 4mm; }
    .small .meta span { font-family: "Courier New", monospace; letter-spacing: .3mm; }
    .hdr { padding: 3mm; font-size: 3.2mm; line-height: 1.45; }
    .hdr h2 { font-size: 4.5mm; margin-bottom: 1.5mm; }
    /* Numbers sit BELOW the rule — above, they collide with the text block. */
    .ruler { margin: 2mm 0 4.5mm; height: 5mm; position: relative; border-left: .3mm solid #000; border-right: .3mm solid #000; border-bottom: .3mm solid #000; width: 80mm; }
    .ruler i { position: absolute; bottom: 0; width: .3mm; height: 2.5mm; background: #000; }
    .ruler em { position: absolute; top: 5.4mm; font-size: 2.4mm; font-style: normal; transform: translateX(-50%); line-height: 1; }
    .guide { outline: .2mm dashed #bbb; outline-offset: 0; }

    @media print {
        body { background: #fff; }
        .panel { display: none !important; }
        .sheet { margin: 0; }
        .guide { outline: none; }   /* don't waste ink outlining the die */
    }
</style>
</head>
<body>

<div class="panel">
    <h1>QR test sheet</h1>
    <p>Finds the smallest QR that scans reliably <strong>on your label stock, through your Epson</strong> — rather than us guessing about ink spread. Each square is a real version-1 code (<?= (int) $modules ?> modules incl. quiet zone), identical to a live blind's label. Only the printed size changes, <?= (int) $from ?>mm&ndash;<?= (int) $to ?>mm down each column.</p>
    <p>If a smaller size reads cleanly, take it &mdash; every millimetre you don't spend on the QR is width back for the text on a label that's only <?= $mm($smallH) ?>mm tall.</p>

    <div class="warn"><strong>Print at 100% / “Actual size”.</strong> Not “Fit to page”, not “Shrink oversized”. Any scaling makes every measurement below a lie. Check the 80mm ruler on the header label with a tape measure before you trust a single result.</div>

    <ol>
        <li>Load a sheet of the real <strong>SET 22TV label stock</strong> and print this page at 100%.</li>
        <li>Measure the ruler. If it isn't 80mm, fix the print scaling and reprint.</li>
        <li>Click in the box below, then scan each square with the D5100, smallest first.</li>
        <li>Each one that reads lights up green. The smallest green size is your answer — I'd then use one step <em>above</em> it on the real labels for margin.</li>
        <li><strong>Then the smudge test.</strong> Give a few of the squares a firm rub with your thumb and rescan them. Herma sell this stock for <em>laser</em> printers, and inkjet ink can sit on the surface instead of soaking in — a code that scans fresh but wipes off in the workshop is no use to anyone. If they smudge, tell me: that's a printer problem, not a size problem, and no amount of extra millimetres fixes it.</li>
    </ol>

    <p><strong>Scan into here:</strong> <input id="scan" autocomplete="off" placeholder="click, then scan…" autofocus></p>
    <div id="results"></div>
    <p style="margin-top:.9rem"><button class="btn" onclick="window.print()">Print sheet</button>
       <a href="/factory/label-test-sheet.php" style="margin-left:.8rem;font-size:.9rem">die alignment sheet</a></p>
</div>

<div class="sheet">
    <?php // Header label: instructions + the print-scale ruler. ?>
    <div class="lbl hdr guide" style="left:<?= $mm($cols[0]) ?>mm; top:<?= $mm($topPad) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($largeH) ?>mm;">
        <h2>QR size test &mdash; label stock / inkjet</h2>
        <div>Scan each square below, smallest first. The code tells us the cell:<br>
             <strong>9</strong>=left column, <strong>8</strong>=right, then the size in mm.<br>
             e.g. <span style="font-family:monospace">91400000</span> = left column, 14mm.</div>
        <div class="ruler">
            <?php for ($k = 0; $k <= 8; $k++): ?>
                <i style="left:<?= $mm($k * 10) ?>mm"></i>
                <em style="left:<?= $mm($k * 10) ?>mm"><?= $k * 10 ?></em>
            <?php endfor; ?>
        </div>
        <div style="margin-top:1mm">&#8593; must measure <strong>80mm</strong> exactly. If not, you printed scaled.</div>
    </div>

    <?php // Right header label: kept clear so the sweep is the only variable. ?>
    <div class="lbl hdr guide" style="left:<?= $mm($cols[1]) ?>mm; top:<?= $mm($topPad) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($largeH) ?>mm;">
        <h2>Right column</h2>
        <div>Same sizes as the left, repeated — inkjets don't lay ink down evenly across a page, so a size that reads on one side and not the other tells us it's marginal, not fine.</div>
        <div style="margin-top:2mm">Roller labels are thermal and get <strong>20mm</strong> on a 102&times;76mm label, so they aren't in question. This sheet is only about the small work tickets, where 21mm of height is the whole constraint.</div>
    </div>

    <?php foreach ($cols as $ci => $x): ?>
        <?php foreach ($steps as $si => $size): $code = $cellCode($ci, $size); ?>
            <div class="lbl small guide" style="left:<?= $mm($x) ?>mm; top:<?= $mm($firstSmallTop + $si * $smallH) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($smallH) ?>mm;">
                <?= qr_svg($code, (float) $size, 'Q') ?>
                <div class="meta">
                    <b><?= (int) $size ?>mm</b> &middot; ECC Q<br>
                    <span><?= e($code) ?></span><br>
                    <?= $mm(round($size / $modules, 3)) ?>mm per module
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var box = document.getElementById('scan'), out = document.getElementById('results'), seen = {};
    if (!box) return;
    // Keep focus in the box — a wedge scanner just types, so anything else loses the scan.
    setInterval(function () { if (document.activeElement !== box && !window.matchMedia('print').matches) box.focus(); }, 800);
    box.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var v = box.value.trim(); box.value = '';
        if (!v) return;
        var m = v.match(/^([89])(\d{2})00000$/);
        var chip = document.createElement('span');
        if (!m) {
            chip.className = 'chip bad';
            chip.textContent = 'not a test code: ' + v.slice(0, 20);
        } else {
            var col = m[1] === '9' ? 'left' : 'right', size = parseInt(m[2], 10);
            var key = m[1] + m[2];
            if (seen[key]) return;                    // already logged
            seen[key] = true;
            chip.className = 'chip ok';
            chip.textContent = size + 'mm ' + col + ' ✓';
        }
        out.appendChild(chip);
    });
})();
</script>
</body>
</html>
