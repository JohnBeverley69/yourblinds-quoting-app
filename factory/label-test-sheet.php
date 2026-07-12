<?php
declare(strict_types=1);

/**
 * Factory · Label test sheet.
 *
 * A print-to-scale A4 page with a thin box outlined at every die-cut label
 * position, so you can print at 100% (actual size, no margins) and lay it over
 * the real BlindMatrix label stock to check the alignment before printing live
 * worksheets onto it.
 *
 * Geometry defaults to Beverley's measured sheet (mm); every dimension can be
 * overridden via query string to nudge and reprint without a redeploy, e.g.
 *   ?small=20.5&gap=6&top=15
 *
 * Dims: top, left, w (label width), large (large label height), gap (large→1st
 * small), small (small label height), n (small labels per column), centre
 * (gap between the two columns).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$f = static fn (string $k, float $d): float => isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : $d;
$i = static fn (string $k, int $d): int => isset($_GET[$k]) && ctype_digit((string) $_GET[$k]) ? (int) $_GET[$k] : $d;

$topPad    = $f('top', 15);
$leftPad   = $f('left', 10);
$labelW    = $f('w', 90);
$largeH    = $f('large', 50);
$gap       = $f('gap', 7);
$smallH    = $f('small', 20.9);
$count     = max(1, min(30, $i('n', 10)));
$centreGap = $f('centre', 10);
$cal       = (($_GET['cal'] ?? '1') !== '0');   // calibration marks (crop marks + 100mm ruler); ?cal=0 to hide

$cols = [$leftPad, $leftPad + $labelW + $centreGap];   // left of each column

$firstSmallTop = $topPad + $largeH + $gap;
$lastBottom    = $firstSmallTop + $count * $smallH;
$fromBottom    = 297 - $lastBottom;

// mm number → tidy string.
$mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Label test sheet</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { background: #666; font-family: system-ui, sans-serif; }
    .toolbar { position: fixed; top: 0; left: 0; right: 0; background: #1f2a37; color: #e5edf5;
               padding: 10px 16px; display: flex; gap: 14px; align-items: center; z-index: 10; font-size: 14px; }
    .toolbar b { color: #fff; }
    .toolbar .note { color: #b9c6d3; }
    .toolbar button { font: inherit; font-weight: 600; cursor: pointer; border: none; border-radius: 8px;
                      padding: 7px 16px; background: #38bdf8; color: #06263a; }
    .sheet { position: relative; width: 210mm; height: 297mm; background: #fff;
             margin: 60px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.4); }
    .box { position: absolute; border: 0.3mm solid #111; }
    .box .tag { position: absolute; top: 0.4mm; left: 0.8mm; font-size: 6pt; color: #999; font-family: monospace; }
    .mk-h { position: absolute; border-top: 0.3mm solid #111; }   /* border prints; background does not */
    .mk-v { position: absolute; border-left: 0.3mm solid #111; }
    .cal-txt { position: absolute; font-size: 6pt; color: #333; font-family: sans-serif; white-space: nowrap; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .sheet { margin: 0; box-shadow: none; }
        @page { size: A4 portrait; margin: 0; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <b>Label test sheet</b>
    <span class="note">Print at <b>100% / Actual size</b>, margins <b>None</b>. Small <?= $mm($labelW) ?>×<?= $mm($smallH) ?>mm, ends <?= $mm($fromBottom) ?>mm from bottom. Calibrate with the 100mm line &amp; corner marks (5mm from edges, 200×280mm apart) &mdash; if they measure short, your printer isn't at 100%.</span>
    <button onclick="window.print()">Print</button>
</div>
<div class="sheet">
    <?php foreach ($cols as $ci => $x): ?>
        <?php $side = $ci === 0 ? 'C' : 'F'; ?>
        <div class="box" style="left:<?= $mm($x) ?>mm; top:<?= $mm($topPad) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($largeH) ?>mm;">
            <span class="tag">HEADER</span>
        </div>
        <?php for ($k = 0; $k < $count; $k++): $t = $firstSmallTop + $k * $smallH; ?>
            <div class="box" style="left:<?= $mm($x) ?>mm; top:<?= $mm($t) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($smallH) ?>mm;">
                <span class="tag"><?= $side . ($k + 1) ?></span>
            </div>
        <?php endfor; ?>
    <?php endforeach; ?>

    <?php if ($cal): ?>
        <?php /* Corner crop marks (crosses) at 5mm from top/left/right; 200mm across, 280mm down. */ ?>
        <?php foreach ([[5, 5], [205, 5], [5, 285], [205, 285]] as [$cx, $cy]): ?>
            <div class="mk-h" style="left:<?= $cx - 4 ?>mm; top:<?= $cy ?>mm; width:8mm;"></div>
            <div class="mk-v" style="left:<?= $cx ?>mm; top:<?= $cy - 4 ?>mm; height:8mm;"></div>
        <?php endforeach; ?>
        <?php /* 100mm reference ruler in the top margin, with end ticks. */ ?>
        <div class="mk-h" style="left:50mm; top:10mm; width:100mm;"></div>
        <div class="mk-v" style="left:50mm; top:8mm; height:4mm;"></div>
        <div class="mk-v" style="left:150mm; top:8mm; height:4mm;"></div>
        <div class="cal-txt" style="left:152mm; top:8.4mm;">= 100 mm (should measure exactly 100mm)</div>
        <div class="cal-txt" style="left:8mm; top:291mm;">Crop marks: 5mm from each edge · 200mm across · 280mm down. Measure these to confirm true scale &amp; no offset.</div>
    <?php endif; ?>
</div>
</body>
</html>
