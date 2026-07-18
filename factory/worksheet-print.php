<?php
declare(strict_types=1);

/**
 * Factory · Worksheet (real order).
 *
 * Renders the production worksheet for a placed order: the order header, then
 * per Beverley line a set of labels (cutting + fabric …) from the product's
 * worksheet template, with the build variables (Trucks, H_Cut, Vanes, Mtrs …)
 * computed live from the line's own width/drop + option selections. Replaces
 * re-keying + Blind Matrix's fixed worksheet.
 *
 * Data path: quotes (header) → quote_items (lines) → quote_item_extras (options).
 * Template: worksheet_templates (per product). Engine: _partials/build_eval.php.
 *
 * ?order=<quote id>.  Print-ready (chrome hidden in print). Die-cut positioning
 * + per-printer nudge come next.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/build_eval.php';
require __DIR__ . '/../_partials/qr.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();
$qid    = (int) ($_GET['order'] ?? 0);

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('d/m/Y'); }
    catch (Throwable $e) { return (string) $ts; }
};
$fmtVal = static function ($v): string {
    if (is_float($v)) return rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
    if (is_bool($v))  return $v ? 'TRUE' : 'FALSE';
    return (string) $v;
};

// ---- Order header (trade customer) ----------------------------------------
$order = null;
try {
    $q = $pdo->prepare(
        "SELECT q.id, q.quote_number, q.created_at, q.customer_reference, q.additional_reference,
                q.end_customer_name, q.client_id,
                c.company_name, c.address1, c.address2, c.town, c.county, c.postcode
           FROM quotes q JOIN clients c ON c.id = q.client_id
          WHERE q.id = ? LIMIT 1"
    );
    $q->execute([$qid]);
    $order = $q->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { /* handled below */ }

// ---- Beverley lines --------------------------------------------------------
$lines = [];
if ($order) {
    try {
        // source_product_id maps the tenant's catalogue copy back to the Beverley
        // master product, where the build rules + worksheet template live.
        $li = $pdo->prepare(
            "SELECT qi.id, qi.line_no, qi.quantity, qi.product_id, qi.system_id, qi.width_mm, qi.drop_mm,
                    qi.product_name_snapshot, qi.system_name_snapshot,
                    qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.room_name, qi.notes,
                    COALESCE(p.source_product_id, p.id) AS master_product_id
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id = ? AND p.source_client_id = ?
           ORDER BY qi.line_no, qi.id"
        );
        $li->execute([$qid, $MASTER]);
        $lines = $li->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* leave empty */ }
}
$totalLines = count($lines);

// ---- Per-line option selections (for the build engine + detail fields) -----
$extrasBy = [];
if ($lines) {
    $ids = array_map(static fn ($l) => (int) $l['id'], $lines);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT quote_item_id, product_extra_id, extra_name_snapshot, choice_label_snapshot, user_value
              FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id";
    try {
        $ex = $pdo->prepare($sql);
        $ex->execute($ids);
        foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extrasBy[(int) $r['quote_item_id']][] = $r; }
    } catch (Throwable $e) {
        // user_value column may be absent on un-migrated installs — retry without it.
        try {
            $ex = $pdo->prepare("SELECT quote_item_id, product_extra_id, extra_name_snapshot, choice_label_snapshot, NULL AS user_value
                                   FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id");
            $ex->execute($ids);
            foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extrasBy[(int) $r['quote_item_id']][] = $r; }
        } catch (Throwable $e2) { /* no extras */ }
    }
}

// ---- Worksheet template per product (default) ------------------------------
$templateByProduct = [];
$loadTemplate = static function (PDO $pdo, int $pid) use (&$templateByProduct) {
    if (array_key_exists($pid, $templateByProduct)) return $templateByProduct[$pid];
    $tpl = null;
    try {
        $ts = $pdo->prepare('SELECT layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
        $ts->execute([$pid]);
        $row = $ts->fetch(PDO::FETCH_ASSOC);
        if ($row) $tpl = json_decode((string) $row['layout_json'], true) ?: null;
    } catch (Throwable $e) { /* none */ }
    return $templateByProduct[$pid] = $tpl;
};

// ---- Order-level detail fields (order:<key>) -------------------------------
$addr = trim(implode(', ', array_filter([
    trim((string) ($order['address1'] ?? '')),
    trim((string) ($order['address2'] ?? '')),
    trim((string) ($order['town'] ?? '')),
    trim((string) ($order['county'] ?? '')),
])), ', ');
$orderVals = $order ? [
    'order_no'   => (string) ($order['quote_number'] ?? ('#' . $qid)),
    'order_date' => $fmtDate($order['created_at'] ?? null),
    'customer'   => (string) ($order['company_name'] ?? ''),
    'address'    => $addr,
    'post_code'  => (string) ($order['postcode'] ?? ''),
    'cust_ref'   => (string) ($order['customer_reference'] ?? ''),
] : [];

// ---- Resolve each line: detail fields + computed build variables ----------
$rendered = [];   // [ ['ctx'=>lineDetailVals merged with order, 'computed'=>vars, 'template'=>tpl, 'product'=>name], ... ]
foreach ($lines as $ln) {
    $extras = $extrasBy[(int) $ln['id']] ?? [];

    $byName     = [];   // lower group name => chosen label
    $userVal    = [];   // lower group name => typed numeric input (user_value)
    foreach ($extras as $r) {
        $nm = strtolower(trim((string) $r['extra_name_snapshot']));
        $byName[$nm] = (string) $r['choice_label_snapshot'];
        if (is_numeric($r['user_value'] ?? null)) $userVal[$nm] = (float) $r['user_value'];
    }
    $fitHeight = $userVal['fit height'] ?? 0.0;
    // Wand length is a typed input riding on the "Wand Options" row (user_value).
    $numTidy  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $wandLen  = isset($userVal['wand options']) ? $numTidy($userVal['wand options']) : '';
    // Decision tables match on the System axis + option group NAME (tenant group
    // ids differ from the master's), so key selections by name.
    $optSel = array_merge(['system' => (string) ($ln['system_name_snapshot'] ?? '')], $byName);
    $masterPid = (int) ($ln['master_product_id'] ?? $ln['product_id']);
    $pick = static function (array $byName, array $names): string {
        foreach ($names as $n) { if (isset($byName[$n])) return $byName[$n]; }
        return '';
    };

    $numVars = ['Width' => (float) $ln['width_mm'], 'Drop' => (float) $ln['drop_mm'], 'Fit_height' => $fitHeight];
    $eval    = build_evaluate($pdo, $masterPid, $numVars, $optSel);

    $lineVals = [
        'line_no'      => $ln['line_no'] . '/' . $totalLines,
        'system'       => (string) ($ln['system_name_snapshot'] ?? ''),
        'colour'       => (string) ($ln['fabric_colour_snapshot'] ?? ''),
        'hd_colour'    => $pick($byName, ['hd colour', 'headrail colour', 'head rail colour']),
        'fabric'       => (string) ($ln['fabric_name_snapshot'] ?? ''),
        'location'     => (string) ($ln['room_name'] ?? ''),
        'size'         => (int) $ln['width_mm'] . ' x ' . (int) $ln['drop_mm'],
        'width'        => (string) (int) $ln['width_mm'],
        'drop'         => (string) (int) $ln['drop_mm'],
        'qty'          => (string) (int) $ln['quantity'],
        'notes'        => (string) ($ln['notes'] ?? ''),
        'control'      => $pick($byName, ['control options', 'control']),
        'chain'        => $pick($byName, ['chain', 'chain type']),
        'draw'         => $pick($byName, ['draw options', 'wand options', 'draw']),
        'wand_length'  => $wandLen,
        'fit_height'   => $fitHeight > 0 ? $numTidy($fitHeight) : '',
        'bracket'      => $pick($byName, ['brackets', 'bracket', 'fix', 'fixing', 'fitting', 'fit type']),
        'recess_exact' => $pick($byName, ['exact or recess', 'recess or exact', 'recess']),
        'welded'       => $pick($byName, ['fabric finish', 'welded', 'weld', 'joint']),
        'bottom_weight' => $pick($byName, ['bottom weight option', 'bottom weight', 'weight']),
        'weight_colour' => $pick($byName, ['colour', 'weight colour', 'bottom weight colour']),
    ];

    // Generic per-product option values (opt:<group name>) — every option group
    // on this order, keyed by name, so any product's own options resolve. The
    // chosen label, or the typed number (fit height / wand length) when the
    // group carries a value rather than a choice.
    foreach ($byName as $gname => $label) $lineVals['opt:' . $gname] = $label;
    foreach ($userVal as $gname => $uv) {
        $ok = 'opt:' . $gname;
        if (($lineVals[$ok] ?? '') === '') $lineVals[$ok] = $numTidy($uv);
    }

    // ONE LABEL PER PHYSICAL BLIND, not per order line. A qty-3 line is three
    // blinds that are tracked, routed and scanned separately, so each needs its
    // own ticket carrying its own code — a single label saying "qty 3" can't
    // follow three blinds round three different benches.
    $qty  = max(1, (int) $ln['quantity']);
    $tpl     = $loadTemplate($pdo, $masterPid);
    $streams = function_exists('bj_streams_ordered') ? bj_streams_ordered($pdo, $masterPid) : [];
    for ($u = 1; $u <= $qty; $u++) {
        $unitVals = [
            'unit'     => $qty > 1 ? $u . '/' . $qty : '',
            'unit_no'  => (string) $u,
            // A per-label code is filled in at render time (each label carries
            // its own part's stream), so this whole-blind code is only a
            // fallback for a label with no stream position.
            'qr_code'  => function_exists('qr_blind_code') ? qr_blind_code((int) $ln['id'], $u) : '',
        ];
        $rendered[] = [
            'ctx'      => array_merge($orderVals, $lineVals, $unitVals),
            'computed' => $eval['vars'],
            'template' => $tpl,
            'product'  => (string) ($ln['product_name_snapshot'] ?? ''),
            'item_id'  => (int) $ln['id'],
            'unit_no'  => $u,
            'streams'  => $streams,   // ordered stream names for this product
        ];
    }
}

/**
 * The context for one LABEL of a blind, with its QR pointing at that label's
 * part. A label's position IS its stream position: on a vertical, label 0 (the
 * cutting label) carries the Headrail code, label 1 (the fabric label) the
 * Fabric code, so each scan finishes exactly that part. Single-stream products
 * (roller, pleated) use digit 0 = the whole blind, whichever label is scanned.
 */
$labelCtx = static function (array $r, int $labelIndex): array {
    $ctx = $r['ctx'];
    if (function_exists('qr_blind_code')) {
        $streams = $r['streams'] ?? [];
        $digit   = count($streams) <= 1 ? 0 : min($labelIndex + 1, count($streams));
        $ctx['qr_code'] = qr_blind_code((int) ($r['item_id'] ?? 0), (int) ($r['unit_no'] ?? 1), $digit);
    }
    return $ctx;
};

// Header template: take the first line's product template (they share the die-cut header).
$headerFields = [];
foreach ($rendered as $r) { if ($r['template'] && !empty($r['template']['header']['fields'])) { $headerFields = $r['template']['header']['fields']; break; } }

/** Render one template field to "caption value" (or null to omit). */
$fieldText = static function (array $f, array $ctx, array $computed) use ($fmtVal): ?string {
    $src  = (string) ($f['source'] ?? '');
    $show = (string) ($f['show'] ?? 'always');
    $cap  = trim((string) ($f['caption'] ?? ''));
    $val  = '';
    if ($src === 'text') { $val = $cap; $cap = ''; }
    elseif (strncmp($src, 'var:', 4) === 0) { $n = substr($src, 4); $val = array_key_exists($n, $computed) ? $fmtVal($computed[$n]) : ''; }
    elseif (strncmp($src, 'order:', 6) === 0) { $k = substr($src, 6); $val = (string) ($ctx[$k] ?? ''); }
    elseif (strncmp($src, 'opt:', 4) === 0)   { $val = (string) ($ctx[$src] ?? ''); }
    elseif (strncmp($src, 'barcode:', 8) === 0) { $k = substr($src, 8); $val = '▏▎▍▌▍▎▏ ' . (string) ($ctx[$k] ?? ''); }
    if ($show === 'never') return null;
    if ($show === 'ifvalue' && trim($val) === '') return null;
    return $cap !== '' ? ($cap . ' ' . $val) : $val;
};

/**
 * A field as HTML. Everything is escaped text EXCEPT the QR, which is a graphic
 * — so it can't go through $fieldText, whose callers e() the result and would
 * print the SVG source as gibberish. Every render path goes through here, so a
 * qr field can't work on one view and silently vanish on another.
 */
$fieldHtml = static function (array $f, array $ctx, array $computed, float $qrMm = 12) use ($fieldText): ?string {
    if (($f['source'] ?? '') === 'qr') {
        $code = (string) ($ctx['qr_code'] ?? '');
        if ($code === '') return null;
        return '<span class="qr">' . qr_svg($code, $qrMm) . '</span>';
    }
    $t = $fieldText($f, $ctx, $computed);
    return $t === null ? null : e($t);
};

// A product prints on its OWN stock: rollers on the 102×76 thermal roll, the
// rest on the A4 die-cut sheet — different printers. A mixed order (a vertical
// AND a roller) therefore needs BOTH runs, so split the blinds by stock rather
// than forcing the whole order onto whichever product happened to come first.
$rollBlinds   = [];
$diecutBlinds = [];
foreach ($rendered as $r) {
    if ((string) ($r['template']['stock'] ?? '') === 'roll-102x76') $rollBlinds[] = $r;
    else                                                            $diecutBlinds[] = $r;
}
$hasRoll   = $rollBlinds   !== [];
$hasDiecut = $diecutBlinds !== [];

// ---- Roll label print (?rolllabel=1) — one self-contained label per blind --
// For roller blinds: a single label per blind on a thermal roll (default
// 102x76mm), not the vertical A4 die-cut sheet. Per-computer nudge + font.
if ($order && ($_GET['rolllabel'] ?? '0') !== '0') {
    $ff = static fn (string $k, float $d): float => isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : $d;
    $LW = $ff('w', 102); $LH = $ff('h', 76); $fs = $ff('fs', 9);
    $linesOn = (($_GET['lines'] ?? '1') !== '0');
    $mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

    $qrMm = $ff('qr', 20);   // roller: 102x76mm thermal, room to spare

    $renderFields = static function (array $fields, array $ctx, array $computed) use ($fieldHtml, $qrMm): string {
        $out = '';
        foreach ($fields as $f) {
            if (($f['source'] ?? '') === '__break__') { $out .= '<span class="rlbr"></span>'; continue; }
            $t = $fieldHtml($f, $ctx, $computed, $qrMm);
            if ($t === null) continue;
            $al = (string) ($f['align'] ?? '');
            $c  = $al === 'right' ? ' class="r"' : ($al === 'centre' ? ' class="c"' : '');
            if (($f['source'] ?? '') === 'qr') { $out .= $t; continue; }   // already markup
            $out .= '<span' . $c . '>' . $t . '</span>';
        }
        return $out;
    };
    header('Content-Type: text/html; charset=utf-8');
    $ono = e((string) ($order['quote_number'] ?? ('#' . $qid)));
    $ol  = $linesOn ? ' outline' : '';
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Roll label · <?= $ono ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    :root { --fs:<?= $mm($fs) ?>pt; --nx:0mm; --ny:0mm; }
    body { background:#666; font-family:system-ui,sans-serif; }
    .toolbar { position:fixed; top:0; left:0; right:0; background:#1f2a37; color:#e5edf5; padding:10px 16px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; z-index:10; font-size:14px; }
    .toolbar b { color:#fff; } .toolbar .note { color:#b9c6d3; flex:1; min-width:220px; }
    .toolbar > button { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:7px 16px; background:#38bdf8; color:#06263a; }
    .nudge { display:flex; align-items:center; gap:4px; color:#b9c6d3; white-space:nowrap; }
    .nudge .lbl { color:#8ba0b3; } .nudge input { width:3.2rem; font:inherit; border:none; border-radius:6px; padding:5px 6px; text-align:right; }
    .nudge button { font:inherit; cursor:pointer; border:none; border-radius:6px; padding:5px 9px; background:#2c3a4a; color:#e5edf5; }
    .stack { padding:72px 0 40px; display:flex; flex-direction:column; align-items:center; gap:10px; }
    .rl-label { position:relative; width:<?= $mm($LW) ?>mm; height:<?= $mm($LH) ?>mm; background:#fff; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.4); }
    .rl-label.outline { outline:0.2mm solid #c9c9c9; outline-offset:-0.2mm; }
    .rl-label .flds { position:absolute; inset:0; padding:2.5mm 3mm; transform:translate(var(--nx),var(--ny));
                      display:flex; flex-wrap:wrap; align-content:flex-start; gap:0.4mm 2.4mm; line-height:1.18;
                      font-family:ui-monospace,Consolas,monospace; font-size:var(--fs); color:#000; }
    .rl-label .flds span { white-space:nowrap; }
    /* The QR is a graphic, not text — keep line-height off it or the span padding
       eats into the quiet zone that scanners need. */
    /* The QR is a graphic, not a word — pin it to the bottom-right corner rather
       than letting it flow along with the text and land wherever. The matching
       padding reserves that corner so words can't run under it and eat the quiet
       zone the scanner needs. */
    .rl-label .flds .qr { line-height:0; position:absolute; right:3mm; bottom:2.5mm; }
    .rl-label .flds .qr svg { display:block; }
    .rl-label .flds:has(.qr) { padding-right:<?= $mm($qrMm + 4) ?>mm; }
    .rl-label .flds .r { margin-left:auto; } .rl-label .flds .c { margin-left:auto; margin-right:auto; }
    .rlbr { flex:0 0 100%; height:0; }
    @media print {
        body { background:#fff; } .toolbar { display:none; }
        .stack { padding:0; gap:0; display:block; }
        .rl-label { box-shadow:none; page-break-after:always; break-after:page; }
        .rl-label:last-child { page-break-after:auto; break-after:auto; }
        @page { size:<?= $mm($LW) ?>mm <?= $mm($LH) ?>mm; margin:0; }
    }
</style></head>
<body>
<div class="toolbar">
    <b>Roll label</b>
    <span class="note">Order <?= $ono ?> · <?= count($rollBlinds) ?> roller label<?= count($rollBlinds) === 1 ? '' : 's' ?>. Print at <b>100% / Actual size</b> on <b><?= $mm($LW) ?>×<?= $mm($LH) ?>mm</b> labels, margins <b>None</b>. <b>Nudge</b> + <b>Font</b> saved for this computer.</span>
    <span class="nudge"><span class="lbl">Nudge&nbsp;mm</span>
        <button type="button" data-nx="-0.5">&#9664;</button><input id="ox" type="number" step="0.5" value="0"><button type="button" data-nx="0.5">&#9654;</button>
        <button type="button" data-ny="-0.5">&#9650;</button><input id="oy" type="number" step="0.5" value="0"><button type="button" data-ny="0.5">&#9660;</button>
        <button type="button" id="nudge-reset">&#8635;</button></span>
    <span class="nudge"><span class="lbl">Font&nbsp;pt</span>
        <button type="button" id="fs-down">&#8722;</button><input id="fsv" type="number" step="0.5" value="<?= $mm($fs) ?>"><button type="button" id="fs-up">+</button></span>
    <button onclick="window.print()">Print</button>
</div>
<div class="stack">
<?php // Roller blinds only — verticals go on the die-cut sheet, a different printer. ?>
<?php foreach ($rollBlinds as $r): $labFields = $r['template']['labels'][0]['fields'] ?? []; ?>
    <div class="rl-label<?= $ol ?>"><div class="flds"><?= $renderFields($labFields, $labelCtx($r, 0), $r['computed']) ?></div></div>
<?php endforeach; ?>
</div>
<script>
(function () {
    var root = document.documentElement;
    var iox = document.getElementById('ox'), ioy = document.getElementById('oy');
    var ox = parseFloat(localStorage.getItem('lblRollX') || '0') || 0;
    var oy = parseFloat(localStorage.getItem('lblRollY') || '0') || 0;
    function apply() { root.style.setProperty('--nx', ox + 'mm'); root.style.setProperty('--ny', oy + 'mm'); iox.value = ox; ioy.value = oy; localStorage.setItem('lblRollX', ox); localStorage.setItem('lblRollY', oy); }
    iox.addEventListener('input', function () { ox = parseFloat(iox.value) || 0; apply(); });
    ioy.addEventListener('input', function () { oy = parseFloat(ioy.value) || 0; apply(); });
    document.querySelectorAll('[data-nx]').forEach(function (b) { b.addEventListener('click', function () { ox = Math.round((ox + parseFloat(b.dataset.nx)) * 10) / 10; apply(); }); });
    document.querySelectorAll('[data-ny]').forEach(function (b) { b.addEventListener('click', function () { oy = Math.round((oy + parseFloat(b.dataset.ny)) * 10) / 10; apply(); }); });
    document.getElementById('nudge-reset').addEventListener('click', function () { ox = 0; oy = 0; apply(); });
    apply();
    var fsBase = parseFloat('<?= $mm($fs) ?>') || 9;
    var fs = parseFloat(localStorage.getItem('lblRollFs')) || fsBase;
    var fsv = document.getElementById('fsv');
    function applyFs() { root.style.setProperty('--fs', fs + 'pt'); fsv.value = fs; localStorage.setItem('lblRollFs', fs); }
    fsv.addEventListener('input', function () { fs = parseFloat(fsv.value) || fsBase; applyFs(); });
    document.getElementById('fs-up').addEventListener('click', function () { fs = Math.round((fs + 0.5) * 10) / 10; applyFs(); });
    document.getElementById('fs-down').addEventListener('click', function () { fs = Math.max(4, Math.round((fs - 0.5) * 10) / 10); applyFs(); });
    applyFs();
})();
</script>
</body></html>
<?php
    exit;
}

// ---- Die-cut print layout (?diecut=1) --------------------------------------
// Same A4 geometry as the Label Sheet page, but each box filled with the real
// content, so a 100% print drops straight onto the die-cut label stock. Header
// in both large labels; per line: cutting label (left col) + fabric (right).
// Reuses the per-computer nudge (lblNudgeX/Y). Overridable dims via query.
if ($order && ($_GET['diecut'] ?? '0') !== '0') {
    $ff = static fn (string $k, float $d): float => isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : $d;
    $topPad = $ff('top', 15); $leftPad = $ff('left', 10); $labelW = $ff('w', 90); $largeH = $ff('large', 50);
    $gap = $ff('gap', 7); $smallH = $ff('small', 21); $centreGap = $ff('centre', 10); $fs = $ff('fs', 8);
    $linesOn = (($_GET['lines'] ?? '1') !== '0');   // draw thin label outlines (for the plain-paper test)
    $cal     = (($_GET['cal'] ?? '1') !== '0');      // crop marks + 100mm ruler
    $cols = [$leftPad, $leftPad + $labelW + $centreGap];
    $firstSmallTop = $topPad + $largeH + $gap;
    // Die-cut sheet holds the die-cut blinds only (verticals); rollers print on
    // the thermal roll. One row per blind, up to 10 to a sheet.
    $dieCount = count($diecutBlinds);
    $rowCap = min($dieCount, 10);
    $mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    $ol = $linesOn ? ' dc-outline' : '';

    // 12mm on the die-cut: 20% above the 10mm John proved on the real stock with
    // a hard thumb rub, and the label is only 21mm tall. Override with ?qr=
    $qrMm = $ff('qr', 12);

    $renderFields = static function (array $fields, array $ctx, array $computed) use ($fieldHtml, $qrMm): string {
        $out = '';
        foreach ($fields as $f) {
            if (($f['source'] ?? '') === '__break__') { $out .= '<span class="dcbr"></span>'; continue; }
            $t = $fieldHtml($f, $ctx, $computed, $qrMm);
            if ($t === null) continue;
            $al = (string) ($f['align'] ?? '');
            $c  = $al === 'right' ? ' class="r"' : ($al === 'centre' ? ' class="c"' : '');
            if (($f['source'] ?? '') === 'qr') { $out .= $t; continue; }   // already markup
            $out .= '<span' . $c . '>' . $t . '</span>';
        }
        return $out;
    };
    header('Content-Type: text/html; charset=utf-8');
    $ono = e((string) ($order['quote_number'] ?? ('#' . $qid)));
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Worksheet die-cut · <?= $ono ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:#666; font-family:system-ui,sans-serif; }
    .toolbar { position:fixed; top:0; left:0; right:0; background:#1f2a37; color:#e5edf5; padding:10px 16px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; z-index:10; font-size:14px; }
    .toolbar b { color:#fff; } .toolbar .note { color:#b9c6d3; flex:1; min-width:200px; }
    .toolbar a { color:#7dd3fc; text-decoration:none; } .toolbar a:hover { text-decoration:underline; }
    .toolbar > button { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:7px 16px; background:#38bdf8; color:#06263a; }
    .nudge { display:flex; align-items:center; gap:4px; color:#b9c6d3; white-space:nowrap; }
    .nudge input { width:3.4rem; font:inherit; border:none; border-radius:6px; padding:5px 6px; text-align:right; }
    .nudge button { font:inherit; cursor:pointer; border:none; border-radius:6px; padding:5px 9px; background:#2c3a4a; color:#e5edf5; }
    .nudge button:hover { background:#3a4d61; }
    .sheet { position:relative; width:210mm; height:297mm; background:#fff; margin:60px auto 40px; box-shadow:0 4px 24px rgba(0,0,0,0.4); overflow:hidden; }
    #sheet-inner { position:absolute; inset:0; }
    .dc-label { position:absolute; overflow:hidden; padding:0.8mm 1.2mm; font-family:ui-monospace,Consolas,monospace; color:#000; }
    /* The QR is a graphic, not text — keep line-height off it or the span padding
       eats into the quiet zone that scanners need. */
    /* Pinned to the corner, with the corner reserved — see the roll label above.
       On a 21mm ticket a QR flowing with the text would shove lines off the
       label entirely. */
    .dc-label .qr { line-height:0; position:absolute; right:1.2mm; bottom:0.8mm; }
    .dc-label .qr svg { display:block; }
    .dc-label:has(.qr) .flds { padding-right:<?= $mm($qrMm + 1.5) ?>mm; }
    .dc-label .flds { display:flex; flex-wrap:wrap; align-content:flex-start; gap:0 1.8mm; line-height:1.05; }
    :root { --fs-s:<?= $mm($fs) ?>pt; --fs-l:<?= $mm($fs + 1.5) ?>pt; }
    .dc-label.dc-small .flds { font-size:var(--fs-s); }
    .dc-label.dc-large .flds { font-size:var(--fs-l); }
    .dc-label .flds span { white-space:nowrap; }
    .dc-label .flds .dcbr { flex:0 0 100%; height:0; }
    .dc-label .flds .r { margin-left:auto; } .dc-label .flds .c { margin-left:auto; margin-right:auto; }
    .dc-outline { border:0.2mm solid #c9c9c9; }
    .mk-h { position:absolute; border-top:0.3mm solid #111; } .mk-v { position:absolute; border-left:0.3mm solid #111; }
    .cal-txt { position:absolute; font-size:6pt; color:#333; white-space:nowrap; }
    @media print {
        body { background:#fff; } .toolbar { display:none; } .sheet { margin:0; box-shadow:none; }
        @page { size:A4 portrait; margin:0; }
    }
</style></head>
<body>
<div class="toolbar">
    <b>Die-cut label print</b>
    <span class="note">Order <?= $ono ?> · <?= (int) $dieCount ?> die-cut blind<?= $dieCount === 1 ? '' : 's' ?>. Print at <b>100% / Actual size</b>, margins <b>None</b>. Lay the plain print over the label stock to check it lands right.</span>
    <a href="?order=<?= (int) $qid ?>">&larr; content view</a>
    <span class="nudge"><span>Nudge&nbsp;mm</span>
        <button type="button" data-nx="-0.5" title="left">&#9664;</button><input id="ox" type="number" step="0.5" value="0"><button type="button" data-nx="0.5" title="right">&#9654;</button>
        <button type="button" data-ny="-0.5" title="up">&#9650;</button><input id="oy" type="number" step="0.5" value="0"><button type="button" data-ny="0.5" title="down">&#9660;</button>
        <button type="button" id="nudge-reset" title="reset">&#8635;</button>
    </span>
    <span class="nudge"><span>Font&nbsp;pt</span>
        <button type="button" id="fs-down" title="smaller">&#8722;</button><input id="fsv" type="number" step="0.5" value="<?= $mm($fs) ?>"><button type="button" id="fs-up" title="bigger">+</button>
    </span>
    <button onclick="window.print()">Print</button>
</div>
<div class="sheet"><div id="sheet-inner">
<?php foreach ($cols as $ci => $x): ?>
    <div class="dc-label dc-large<?= $ol ?>" style="left:<?= $mm($x) ?>mm; top:<?= $mm($topPad) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($largeH) ?>mm;">
        <div class="flds"><?= $renderFields($headerFields, $orderVals, []) ?></div>
    </div>
    <?php for ($k = 0; $k < $rowCap; $k++): $r = $diecutBlinds[$k]; $lab = ($r['template']['labels'] ?? [])[$ci] ?? null; $t = $firstSmallTop + $k * $smallH; ?>
        <div class="dc-label dc-small<?= $ol ?>" style="left:<?= $mm($x) ?>mm; top:<?= $mm($t) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($smallH) ?>mm;">
            <div class="flds"><?= $lab ? $renderFields($lab['fields'] ?? [], $labelCtx($r, $ci), $r['computed']) : '' ?></div>
        </div>
    <?php endfor; ?>
<?php endforeach; ?>
<?php if ($cal): ?>
    <?php foreach ([[5, 5], [205, 5], [5, 285], [205, 285]] as [$cx, $cy]): ?>
        <div class="mk-h" style="left:<?= $cx - 4 ?>mm; top:<?= $cy ?>mm; width:8mm;"></div>
        <div class="mk-v" style="left:<?= $cx ?>mm; top:<?= $cy - 4 ?>mm; height:8mm;"></div>
    <?php endforeach; ?>
    <div class="mk-h" style="left:50mm; top:10mm; width:100mm;"></div>
    <div class="mk-v" style="left:50mm; top:8mm; height:4mm;"></div>
    <div class="mk-v" style="left:150mm; top:8mm; height:4mm;"></div>
    <div class="cal-txt" style="left:152mm; top:8.4mm;">= 100 mm</div>
<?php endif; ?>
</div></div>
<?php if ($dieCount > $rowCap): ?><div style="position:fixed; bottom:6px; left:16px; color:#fbbf24; font-size:13px;">Note: <?= (int) $dieCount ?> die-cut blinds on this order — only the first <?= (int) $rowCap ?> fit one sheet.</div><?php endif; ?>
<script>
(function () {
    var inner = document.getElementById('sheet-inner');
    var iox = document.getElementById('ox'), ioy = document.getElementById('oy');
    var ox = parseFloat(localStorage.getItem('lblNudgeX') || '0') || 0;
    var oy = parseFloat(localStorage.getItem('lblNudgeY') || '0') || 0;
    function apply() { inner.style.transform = 'translate(' + ox + 'mm,' + oy + 'mm)'; iox.value = ox; ioy.value = oy; localStorage.setItem('lblNudgeX', ox); localStorage.setItem('lblNudgeY', oy); }
    iox.addEventListener('input', function () { ox = parseFloat(iox.value) || 0; apply(); });
    ioy.addEventListener('input', function () { oy = parseFloat(ioy.value) || 0; apply(); });
    document.querySelectorAll('[data-nx]').forEach(function (b) { b.addEventListener('click', function () { ox = Math.round((ox + parseFloat(b.dataset.nx)) * 10) / 10; apply(); }); });
    document.querySelectorAll('[data-ny]').forEach(function (b) { b.addEventListener('click', function () { oy = Math.round((oy + parseFloat(b.dataset.ny)) * 10) / 10; apply(); }); });
    document.getElementById('nudge-reset').addEventListener('click', function () { ox = 0; oy = 0; apply(); });
    apply();

    // Live font-size control (remembered per computer). Small = fs, large = fs + 1.5.
    var fsBase = parseFloat('<?= $mm($fs) ?>') || 8;
    var fs = parseFloat(localStorage.getItem('lblDiecutFs')) || fsBase;
    var fsv = document.getElementById('fsv');
    function applyFs() {
        document.documentElement.style.setProperty('--fs-s', fs + 'pt');
        document.documentElement.style.setProperty('--fs-l', (fs + 1.5) + 'pt');
        fsv.value = fs; localStorage.setItem('lblDiecutFs', fs);
    }
    fsv.addEventListener('input', function () { fs = parseFloat(fsv.value) || fsBase; applyFs(); });
    document.getElementById('fs-up').addEventListener('click', function () { fs = Math.round((fs + 0.5) * 10) / 10; applyFs(); });
    document.getElementById('fs-down').addEventListener('click', function () { fs = Math.max(4, Math.round((fs - 0.5) * 10) / 10); applyFs(); });
    applyFs();
})();
</script>
</body></html>
<?php
    exit;
}

$factoryTitle = 'Worksheet';
$factoryNav   = '';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .wp-bar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:0 0 1.1rem; }
    .wp-bar h1 { font-size:1.5rem; font-weight:700; margin:0; }
    .wp-bar .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.5rem 1.1rem; background:#1f2a37; color:#fff; }
    .wp-bar .btn:hover { background:#111a24; }
    .wp-sheet { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1.25rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    /* Bottom-right corner, same as the real prints — see the label CSS below.
       Reserve the corner so text can't run under it. */
    .wp-sheet .qr { line-height:0; display:inline-block; position:absolute; right:0.6rem; bottom:0.6rem; }
    .wp-sheet .qr svg { display:block; }
    .wp-label:has(.qr) .fields { padding-right:15mm; }
    .wp-header { border-bottom:2px solid #111; padding-bottom:0.6rem; margin-bottom:0.9rem; display:flex; flex-wrap:wrap; gap:0.2rem 1.4rem; font-family:ui-monospace,Consolas,monospace; font-size:0.9rem; }
    .wp-line { display:grid; grid-template-columns:1fr 1fr; gap:1rem; padding:0.7rem 0; border-bottom:1px dashed #d1d5db; }
    .wp-label { border:1px solid #cbd5e1; border-radius:8px; padding:0.5rem 0.7rem; position:relative; }
    .wp-label .lt { font-size:0.66rem; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; margin-bottom:0.3rem; font-weight:600; }
    .wp-label .fields { font-family:ui-monospace,Consolas,monospace; font-size:0.82rem; line-height:1.6; display:flex; flex-wrap:wrap; gap:0.1rem 0.7rem; }
    .wp-break { flex:0 0 100%; height:0; }
    .wp-right { margin-left:auto; }
    .wp-centre { margin-left:auto; margin-right:auto; }
    .wp-flag { color:#b91c1c; }
    .wp-note { color:#94a3b8; font-size:0.85rem; }
    @media print {
        .factory-topbar, .wp-bar { display:none !important; }
        .factory-main { padding:0 !important; max-width:none !important; }
        .wp-sheet { border:none; box-shadow:none; border-radius:0; padding:0; }
    }
</style>

<div class="wp-bar">
    <h1>Worksheet</h1>
    <?php if ($order): ?>
        <span class="wp-note">Order <?= e((string) ($order['quote_number'] ?? ('#' . $qid))) ?> · <?= e((string) ($order['company_name'] ?? '')) ?> · <?= (int) $totalLines ?> line<?= $totalLines === 1 ? '' : 's' ?></span>
        <span style="flex:1"></span>
        <?php // A mixed order shows BOTH — each prints only its own products, on its own printer. ?>
        <?php if ($hasDiecut): ?>
        <a class="btn" href="?order=<?= (int) $qid ?>&diecut=1" style="background:#0369a1; text-decoration:none;">Die-cut sheet<?= $hasRoll ? ' (' . count($diecutBlinds) . ')' : '' ?> &#8599;</a>
        <?php endif; ?>
        <?php if ($hasRoll): ?>
        <a class="btn" href="?order=<?= (int) $qid ?>&rolllabel=1" style="background:#0369a1; text-decoration:none;">Roll labels<?= $hasDiecut ? ' (' . count($rollBlinds) . ')' : '' ?> &#8599;</a>
        <?php endif; ?>
        <button class="btn" onclick="window.print()">Print</button>
    <?php endif; ?>
</div>

<?php if (!$order): ?>
    <div class="wp-sheet"><p class="wp-flag">Order not found.</p></div>
<?php elseif (!$lines): ?>
    <div class="wp-sheet"><p class="wp-note">This order has no Beverley lines to work.</p></div>
<?php else: ?>
<div class="wp-sheet">
    <div class="wp-header">
        <?php foreach ($headerFields as $f): ?>
            <?php if (($f['source'] ?? '') === '__break__'): ?><span class="wp-break"></span><?php continue; endif; ?>
            <?php $t = $fieldText($f, $orderVals, []); if ($t !== null): ?><span<?= (($f['align'] ?? '') === 'right') ? ' class="wp-right"' : ((($f['align'] ?? '') === 'centre') ? ' class="wp-centre"' : '') ?>><?= e($t) ?></span><?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$headerFields): ?><span class="wp-note">No worksheet template for this product yet — build one in Worksheets.</span><?php endif; ?>
    </div>

    <?php foreach ($rendered as $r): $tpl = $r['template']; ?>
        <div class="wp-line">
            <?php if ($tpl && !empty($tpl['labels'])): ?>
                <?php foreach ($tpl['labels'] as $li => $lab): $lctx = $labelCtx($r, (int) $li); ?>
                    <div class="wp-label">
                        <div class="lt"><?= e((string) ($lab['title'] ?? 'Label')) ?></div>
                        <div class="fields">
                            <?php foreach (($lab['fields'] ?? []) as $f): ?>
                                <?php if (($f['source'] ?? '') === '__break__'): ?><span class="wp-break"></span><?php continue; endif; ?>
                                <?php $t = $fieldHtml($f, $lctx, $r['computed']); if ($t !== null): ?><span<?= (($f['align'] ?? '') === 'right') ? ' class="wp-right"' : ((($f['align'] ?? '') === 'centre') ? ' class="wp-centre"' : '') ?>><?= $t ?></span><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="wp-label" style="grid-column:1/-1"><span class="wp-note">No worksheet template for <?= e($r['product']) ?>.</span></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
