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
$smallH    = $f('small', 21);
$count     = max(1, min(30, $i('n', 10)));
$centreGap = $f('centre', 10);
$cal       = (($_GET['cal'] ?? '1') !== '0');   // calibration marks (crop marks + 100mm ruler); ?cal=0 to hide

$cols = [$leftPad, $leftPad + $labelW + $centreGap];   // left of each column

$firstSmallTop = $topPad + $largeH + $gap;
$lastBottom    = $firstSmallTop + $count * $smallH;
$fromBottom    = 297 - $lastBottom;

// mm number → tidy string.
$mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

// Representative sample content so the Font control has something to size —
// mirrors a real vertical worksheet's fields, so you can judge legibility at
// print scale while calibrating position + font together.
$hdrSample = 'ONO ABC-2026-0001   Date 12/07/2026   Sample Trade Co.   1 Example Way, Town   AB1 2CD   Cust Ref';
$cutSample = '1/1  SlimLine  White  Corded  Chain Steel  R/R  1200 x 1200  Recess  H_Cut 1170  C/L 5360  CH 1480  Top Fix  Notes';
$fabSample = '1/1  Amaris  White  Hem 1145  Mtrs 23  Vanes 17  1200 x 1200  Recess  Welded  Weight Standard';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Label test sheet</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root { --fs-s: 8pt; --fs-l: 9.5pt; }
    body { background: #666; font-family: system-ui, sans-serif; }
    .toolbar { position: fixed; top: 0; left: 0; right: 0; background: #1f2a37; color: #e5edf5;
               padding: 10px 16px; display: flex; gap: 14px; align-items: center; flex-wrap: wrap; z-index: 10; font-size: 14px; }
    .toolbar b { color: #fff; }
    .toolbar .note { color: #b9c6d3; flex: 1; min-width: 220px; }
    .toolbar > button { font: inherit; font-weight: 600; cursor: pointer; border: none; border-radius: 8px;
                        padding: 7px 16px; background: #38bdf8; color: #06263a; }
    .nudge { display: flex; align-items: center; gap: 4px; color: #b9c6d3; white-space: nowrap; }
    .nudge .lbl { color: #8ba0b3; margin-right: 2px; }
    .nudge input { width: 3.4rem; font: inherit; border: none; border-radius: 6px; padding: 5px 6px; text-align: right; }
    .nudge button { font: inherit; cursor: pointer; border: none; border-radius: 6px; padding: 5px 9px; background: #2c3a4a; color: #e5edf5; }
    .nudge button:hover { background: #3a4d61; }
    .sheet { position: relative; width: 210mm; height: 297mm; background: #fff;
             margin: 60px auto 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.4); overflow: hidden; }
    #sheet-inner { position: absolute; inset: 0; }
    .box { position: absolute; border: 0.3mm solid #111; overflow: hidden; }
    .box .tag { position: absolute; top: 0.3mm; left: 0.8mm; font-size: 5pt; color: #c00; font-family: monospace; z-index: 2; }
    .box .sample { position: absolute; top: 3mm; left: 1mm; right: 1mm; bottom: 0.6mm; overflow: hidden;
                   font-family: ui-monospace, Consolas, monospace; line-height: 1.05; color: #111;
                   font-size: var(--fs-s); word-break: break-word; }
    .box.large .sample { top: 4.2mm; font-size: var(--fs-l); }
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
    <span class="note">Print at <b>100% / Actual size</b>, margins <b>None</b>. Small <?= $mm($labelW) ?>×<?= $mm($smallH) ?>mm. <b>Nudge</b> to line up on your label stock and set the <b>Font</b> for legibility — both are saved for this computer and used by the real worksheet print.</span>
    <span class="nudge">
        <span class="lbl">Nudge&nbsp;mm</span>
        <button type="button" data-nx="-0.5" title="left">◀</button>
        <input id="ox" type="number" step="0.5" value="0" title="right (mm)">
        <button type="button" data-nx="0.5" title="right">▶</button>
        <button type="button" data-ny="-0.5" title="up">▲</button>
        <input id="oy" type="number" step="0.5" value="0" title="down (mm)">
        <button type="button" data-ny="0.5" title="down">▼</button>
        <button type="button" id="nudge-reset" title="reset to 0">⟳</button>
    </span>
    <span class="nudge">
        <span class="lbl">Font&nbsp;pt</span>
        <button type="button" id="fs-down" title="smaller">&#8722;</button>
        <input id="fsv" type="number" step="0.5" value="8" title="label font (pt)">
        <button type="button" id="fs-up" title="bigger">+</button>
    </span>
    <button onclick="window.print()">Print</button>
</div>
<div class="sheet"><div id="sheet-inner">
    <?php foreach ($cols as $ci => $x): ?>
        <?php $side = $ci === 0 ? 'C' : 'F'; ?>
        <div class="box large" style="left:<?= $mm($x) ?>mm; top:<?= $mm($topPad) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($largeH) ?>mm;">
            <span class="tag">HEADER</span>
            <div class="sample"><?= e($hdrSample) ?></div>
        </div>
        <?php for ($k = 0; $k < $count; $k++): $t = $firstSmallTop + $k * $smallH; ?>
            <div class="box" style="left:<?= $mm($x) ?>mm; top:<?= $mm($t) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($smallH) ?>mm;">
                <span class="tag"><?= $side . ($k + 1) ?></span>
                <div class="sample"><?= e($ci === 0 ? $cutSample : $fabSample) ?></div>
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
</div></div>
<script>
(function () {
    var inner = document.getElementById('sheet-inner');
    var iox = document.getElementById('ox'), ioy = document.getElementById('oy');
    var ox = parseFloat(localStorage.getItem('lblNudgeX') || '0') || 0;
    var oy = parseFloat(localStorage.getItem('lblNudgeY') || '0') || 0;
    function apply() {
        inner.style.transform = 'translate(' + ox + 'mm,' + oy + 'mm)';
        iox.value = ox; ioy.value = oy;
        localStorage.setItem('lblNudgeX', ox); localStorage.setItem('lblNudgeY', oy);
    }
    iox.addEventListener('input', function () { ox = parseFloat(iox.value) || 0; apply(); });
    ioy.addEventListener('input', function () { oy = parseFloat(ioy.value) || 0; apply(); });
    document.querySelectorAll('[data-nx]').forEach(function (b) {
        b.addEventListener('click', function () { ox = Math.round((ox + parseFloat(b.dataset.nx)) * 10) / 10; apply(); });
    });
    document.querySelectorAll('[data-ny]').forEach(function (b) {
        b.addEventListener('click', function () { oy = Math.round((oy + parseFloat(b.dataset.ny)) * 10) / 10; apply(); });
    });
    document.getElementById('nudge-reset').addEventListener('click', function () { ox = 0; oy = 0; apply(); });
    apply();

    // Font size — same per-computer key (lblDiecutFs) the die-cut worksheet reads,
    // so calibrating it here saves it for the real print too. Small = fs, large = fs + 1.5.
    var fsv = document.getElementById('fsv');
    var fs = parseFloat(localStorage.getItem('lblDiecutFs')) || 8;
    function applyFs() {
        document.documentElement.style.setProperty('--fs-s', fs + 'pt');
        document.documentElement.style.setProperty('--fs-l', (fs + 1.5) + 'pt');
        fsv.value = fs; localStorage.setItem('lblDiecutFs', fs);
    }
    fsv.addEventListener('input', function () { fs = parseFloat(fsv.value) || 8; applyFs(); });
    document.getElementById('fs-up').addEventListener('click', function () { fs = Math.round((fs + 0.5) * 10) / 10; applyFs(); });
    document.getElementById('fs-down').addEventListener('click', function () { fs = Math.max(4, Math.round((fs - 0.5) * 10) / 10); applyFs(); });
    applyFs();
})();
</script>
</body>
</html>
