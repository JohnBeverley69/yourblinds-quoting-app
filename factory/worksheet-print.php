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
        'bracket'      => $pick($byName, ['brackets', 'bracket']),
        'recess_exact' => $pick($byName, ['exact or recess', 'recess or exact', 'recess']),
        'fix'          => $pick($byName, ['fix', 'fixing', 'fit type']),
        'welded'       => $pick($byName, ['welded', 'weld', 'joint']),
        'bottom_weight' => $pick($byName, ['bottom weight option', 'bottom weight', 'weight']),
    ];

    $rendered[] = [
        'ctx'      => array_merge($orderVals, $lineVals),
        'computed' => $eval['vars'],
        'template' => $loadTemplate($pdo, $masterPid),
        'product'  => (string) ($ln['product_name_snapshot'] ?? ''),
    ];
}

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
    elseif (strncmp($src, 'barcode:', 8) === 0) { $k = substr($src, 8); $val = '▏▎▍▌▍▎▏ ' . (string) ($ctx[$k] ?? ''); }
    if ($show === 'never') return null;
    if ($show === 'ifvalue' && trim($val) === '') return null;
    return $cap !== '' ? ($cap . ' ' . $val) : $val;
};

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
    .wp-header { border-bottom:2px solid #111; padding-bottom:0.6rem; margin-bottom:0.9rem; display:flex; flex-wrap:wrap; gap:0.2rem 1.4rem; font-family:ui-monospace,Consolas,monospace; font-size:0.9rem; }
    .wp-line { display:grid; grid-template-columns:1fr 1fr; gap:1rem; padding:0.7rem 0; border-bottom:1px dashed #d1d5db; }
    .wp-label { border:1px solid #cbd5e1; border-radius:8px; padding:0.5rem 0.7rem; }
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
                <?php foreach ($tpl['labels'] as $lab): ?>
                    <div class="wp-label">
                        <div class="lt"><?= e((string) ($lab['title'] ?? 'Label')) ?></div>
                        <div class="fields">
                            <?php foreach (($lab['fields'] ?? []) as $f): ?>
                                <?php if (($f['source'] ?? '') === '__break__'): ?><span class="wp-break"></span><?php continue; endif; ?>
                                <?php $t = $fieldText($f, $r['ctx'], $r['computed']); if ($t !== null): ?><span<?= (($f['align'] ?? '') === 'right') ? ' class="wp-right"' : ((($f['align'] ?? '') === 'centre') ? ' class="wp-centre"' : '') ?>><?= e($t) ?></span><?php endif; ?>
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
