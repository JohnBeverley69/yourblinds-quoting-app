<?php
declare(strict_types=1);

/**
 * Factory · Worksheets.
 *
 * The configurable replacement for Blind Matrix's fixed worksheet — the "huge
 * bugbear" of no control over what prints on the shop-floor label. Per product
 * you lay out a worksheet template: an order HEADER block, then a per-line-item
 * set of labels (e.g. a cutting label + a fabric label, as the real vertical
 * worksheet does). Each field names its source (a build variable, an order/line
 * detail, free text, or a barcode), the caption to print, and a show-when rule.
 *
 * The editor is client-side (add/remove/reorder fields, add labels); Save posts
 * the whole layout as JSON. A live PREVIEW renders the template against sample
 * data so you can see the sheet before wiring real orders. Templates belong to
 * the Beverley master products (client #3); one per product is the print default.
 *
 * Storage: worksheet_templates (see migrate_worksheet_templates.php).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

// Order/line detail fields available to drop onto a label. key => [label, sample].
$ORDER_FIELDS = [
    'order_no'     => ['Order no',     'ON066564'],
    'order_date'   => ['Order date',   '10/07/2026'],
    'customer'     => ['Customer',     'Sample Trade Co.'],
    'address'      => ['Address',      '1 Example Way, Town'],
    'post_code'    => ['Post code',    'AB1 2CD'],
    'cust_ref'     => ['Cust ref',     'REF123'],
    'line_no'      => ['Line no',      '1/2'],
    'system'       => ['System',       'SlimLine'],
    'colour'       => ['Colour',       'White'],
    'hd_colour'    => ['Headrail colour', 'Black'],
    'control'      => ['Control',      'Corded'],
    'draw'         => ['Draw',         'R/R'],
    'wand_length'  => ['Wand length',  '600'],
    'bracket'      => ['Bracket',      'Plastic'],
    'fabric'       => ['Fabric',       'Signature Sand'],
    'location'     => ['Location',     'LOUNGE'],
    'size'         => ['Size',         '2360 x 1490'],
    'width'        => ['Width',        '2360'],
    'drop'         => ['Drop',         '1490'],
    'recess_exact' => ['Recess/Exact', 'Recess'],
    'fix'          => ['Fix',          'Top Fix'],
    'welded'       => ['Welded',       'Welded'],
    'qty'          => ['Qty',          '1'],
    'notes'        => ['Notes',        ''],
];

// Master products.
$products = [];
try {
    $ps = $pdo->prepare("SELECT id, name FROM products WHERE client_id = ? AND name LIKE 'Bev%' ORDER BY name");
    $ps->execute([$MASTER]);
    $products = $ps->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* handled in view */ }

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
if ($productId === 0 && $products) $productId = (int) $products[0]['id'];
$productName = '';
foreach ($products as $p) { if ((int) $p['id'] === $productId) $productName = (string) $p['name']; }

$hasTable = false;
try { $pdo->query('SELECT 1 FROM worksheet_templates LIMIT 0'); $hasTable = true; }
catch (Throwable $e) { /* not migrated */ }

// This product's build variables → the "Build variable" field sources.
$buildVars = [];
try {
    $bv = $pdo->prepare('SELECT name FROM build_variables WHERE product_id = ? ORDER BY seq, id');
    $bv->execute([$productId]);
    $buildVars = $bv->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { /* build_variables not migrated */ }

// ---- POST: save / delete --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasTable) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'delete') {
        $tid = (int) ($_POST['template_id'] ?? 0);
        if ($tid > 0) {
            try { $pdo->prepare('DELETE FROM worksheet_templates WHERE id = ? AND product_id = ?')->execute([$tid, $productId]); }
            catch (Throwable $e) { $_SESSION['flash_error'] = 'Could not delete: ' . $e->getMessage(); }
        }
        header('Location: /factory/worksheets.php?product_id=' . $productId);
        exit;
    }

    if ($action === 'save' && $productId > 0) {
        $tid       = (int) ($_POST['template_id'] ?? 0);
        $name      = trim((string) ($_POST['name'] ?? '')) ?: 'Worksheet';
        $name      = mb_substr($name, 0, 120);
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;
        $layout    = json_decode((string) ($_POST['payload'] ?? ''), true);

        if (!is_array($layout)) {
            $_SESSION['flash_error'] = 'Could not read the layout — nothing saved.';
        } else {
            $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE);
            try {
                $pdo->beginTransaction();
                if ($tid > 0) {
                    $upd = $pdo->prepare('UPDATE worksheet_templates SET name = ?, is_default = ?, layout_json = ? WHERE id = ? AND product_id = ?');
                    $upd->execute([$name, $isDefault, $layoutJson, $tid, $productId]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO worksheet_templates (product_id, name, is_default, layout_json) VALUES (?, ?, ?, ?)');
                    $ins->execute([$productId, $name, $isDefault, $layoutJson]);
                    $tid = (int) $pdo->lastInsertId();
                }
                if ($isDefault) {
                    $pdo->prepare('UPDATE worksheet_templates SET is_default = 0 WHERE product_id = ? AND id <> ?')->execute([$productId, $tid]);
                }
                $pdo->commit();
                $_SESSION['flash_success'] = "Saved “{$name}”.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
            }
        }
        header('Location: /factory/worksheets.php?product_id=' . $productId . '&template_id=' . $tid);
        exit;
    }
}

// ---- Load templates for this product --------------------------------------
$templates = [];
if ($hasTable && $productId > 0) {
    try {
        $ts = $pdo->prepare('SELECT id, name, is_default, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, name');
        $ts->execute([$productId]);
        $templates = $ts->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
}

$templateId = (int) ($_GET['template_id'] ?? 0);
$current = null;
foreach ($templates as $t) { if ((int) $t['id'] === $templateId) $current = $t; }
if (!$current && $templates) { $current = $templates[0]; $templateId = (int) $current['id']; }

$currentLayout = $current ? (json_decode((string) $current['layout_json'], true) ?: null) : null;
$currentName   = $current ? (string) $current['name'] : ($productName ? $productName . ' worksheet' : 'Worksheet');
$currentIsDef  = $current ? (int) $current['is_default'] : 1;

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

// Field sources + samples for JS.
$jsOrderFields = [];
$jsSamples     = [];
foreach ($ORDER_FIELDS as $key => [$label, $sample]) {
    $jsOrderFields[] = ['key' => $key, 'label' => $label];
    $jsSamples['order:' . $key] = $sample;
}
$varSamples = ['H_Cut' => '2330', 'C_L' => '7700', 'CH_L' => '2980', 'Hem_To_Hem' => '1435', 'Mtrs' => '51', 'Vanes' => '32'];
foreach ($buildVars as $vn) { $jsSamples['var:' . $vn] = $varSamples[$vn] ?? '0'; }

$factoryTitle = 'Worksheets';
$factoryNav   = 'worksheets';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .ws-head { display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap; margin:0 0 0.6rem; }
    .ws-head h1 { font-size:1.6rem; font-weight:700; margin:0; }
    .ws-sub { color:var(--text-muted,#667); margin:0 0 1.2rem; max-width:76ch; line-height:1.55; }
    .ws-flash { padding:0.7rem 1rem; border-radius:10px; margin:0 0 1.2rem; font-size:0.9375rem; }
    .ws-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .ws-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .ws-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); margin-bottom:1rem; }
    select, input[type=text], textarea { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:0.4rem 0.55rem; background:var(--bg-input,#fff); color:inherit; }
    .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.5rem 1rem; }
    .btn.primary { background:#166534; color:#fff; } .btn.primary:hover{ background:#14532d; }
    .btn.dark { background:#1f2a37; color:#fff; } .btn.dark:hover{ background:#111a24; }
    .btn.ghost { background:#f1f5f9; color:#334155; font-weight:500; padding:0.4rem 0.7rem; font-size:0.85rem; }
    .btn.ghost:hover { background:#e2e8f0; }
    .btn.danger { background:#fff; color:#b91c1c; border:1px solid #fca5a5; font-weight:500; padding:0.4rem 0.7rem; font-size:0.85rem; }
    .btn.danger:hover { background:#fef2f2; }
    .ws-hint { font-size:0.8rem; color:var(--text-faint,#94a3b8); line-height:1.5; }
    code { background:var(--bg-subtle,#f1f5f9); padding:0.05rem 0.3rem; border-radius:4px; font-size:0.85em; }

    .sec { border:1px solid #e5e7eb; border-radius:12px; padding:0.9rem 1rem; margin-bottom:0.9rem; background:#fcfcfd; }
    .sec-top { display:flex; align-items:center; gap:0.6rem; margin-bottom:0.6rem; }
    .sec-top .tag { font-size:0.68rem; letter-spacing:0.05em; text-transform:uppercase; color:#64748b; font-weight:700; background:#eef2f7; padding:0.2rem 0.5rem; border-radius:6px; }
    .sec-top input.title { font-weight:600; width:14rem; }
    .sec-top .rm { margin-left:auto; }
    .fld { display:flex; align-items:center; gap:0.4rem; padding:0.2rem 0; border-top:2px solid transparent; border-bottom:2px solid transparent; }
    .fld .grip { cursor:grab; color:#94a3b8; font-size:1.15rem; line-height:1; padding:0 0.35rem; user-select:none; touch-action:none; align-self:stretch; display:flex; align-items:center; }
    .fld .grip:hover { color:#334155; }
    .fld .grip:active { cursor:grabbing; }
    .fld.dragging { opacity:0.4; }
    .fld.drop-above { border-top-color:#166534; }
    .fld.drop-below { border-bottom-color:#166534; }
    .fld .fld-rm { cursor:pointer; border:none; background:none; color:#cbd5e1; font-size:0.9rem; line-height:1; padding:0 0.15rem; }
    .fld .fld-rm:hover { color:#b91c1c; }
    .fld .fld-rm { font-size:1rem; }
    .fld .fld-rm:hover { color:#ef4444; }
    .fld-break { background:#f8fafc; border-radius:6px; }
    .fld-break .break-label { flex:1; font-size:0.78rem; color:#64748b; font-style:italic; padding:0.15rem 0; }
    .fld input.cap { width:9rem; }
    .fld select.src { min-width:12rem; }
    .fld select.show { width:8.5rem; }
    .fld .free { font-size:0.7rem; color:#94a3b8; width:8.5rem; text-align:center; }
    .add-fld { margin-top:0.5rem; }
    .add-fld select { min-width:14rem; }
    .empty { color:#94a3b8; font-size:0.88rem; padding:0.3rem 0; }

    .size-ctl { margin-left:0.6rem; font-size:0.75rem; color:#64748b; display:inline-flex; align-items:center; gap:0.25rem; }
    .size-ctl input { width:3.4rem; padding:0.25rem 0.35rem; }
    .ws-preview { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1.25rem; margin-top:0.4rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .pv-sheet { overflow-x:auto; }
    .pv-head { display:flex; align-items:flex-start; gap:1rem; margin-bottom:0.6rem; }
    .pv-head .ws-hint { flex:1; margin:0; }
    .pv-zoom { flex:0 0 auto; display:inline-flex; align-items:center; gap:0.3rem; font-size:0.75rem; color:#64748b; }
    .pv-zoom button { cursor:pointer; border:1px solid #cbd5e1; background:#fff; color:#334155; border-radius:6px; width:1.7rem; height:1.7rem; line-height:1; font-size:1rem; padding:0; }
    .pv-zoom button:hover { background:#f1f5f9; }
    .pv-zoom .pv-zoom-reset { width:auto; padding:0 0.5rem; font-size:0.72rem; }
    .pv-zoom #zoom-val { min-width:2.8rem; text-align:center; font-variant-numeric:tabular-nums; }
    .pv-warn { color:#b91c1c; font-size:0.82rem; margin-bottom:0.7rem; line-height:1.4; display:none; }
    .pv-labelbox { border:1px solid #94a3b8; border-radius:2px; padding:2px 4px; font:9px/1.3 ui-monospace,Consolas,monospace; color:#111; overflow:hidden; box-sizing:border-box; background:#fff; display:flex; flex-wrap:wrap; align-content:flex-start; gap:0 5px; }
    .pv-labelbox.over { border-color:#ef4444; box-shadow:0 0 0 1px #ef4444; }
    .pv-labelbox span { white-space:nowrap; }
    .pv-break { flex:0 0 100%; display:flex; align-items:center; justify-content:center; height:0.85em; color:#94a3b8; border-top:1px dashed #cbd5e1; margin-top:1px; }
    .pv-never { text-decoration:line-through; }
    .pv-cap { font-size:0.6rem; text-transform:uppercase; letter-spacing:0.03em; color:#94a3b8; margin:0 0 0.15rem; }
    .pv-line2 { display:flex; gap:10px; padding:0.5rem 0; }
    .pv-badge { font-size:0.62rem; color:#94a3b8; margin:0.35rem 0; }
    .pv-drag { cursor:grab; border-radius:2px; touch-action:none; }
    .pv-drag:hover { background:#eef2ff; }
    .pv-drag:active { cursor:grabbing; }
    .pv-fld.pv-ghost { opacity:0.45; font-style:italic; }
    .pv-fld.pv-dragging { opacity:0.35; }
    .pv-fld.pv-drop-before { box-shadow:-2px 0 0 #166534; }
    .pv-fld.pv-drop-after { box-shadow:2px 0 0 #166534; }
    /* this page wants the room for a side-by-side editor + full-size preview */
    body main.factory-main { max-width:1700px; }
    /* editor + live preview side by side */
    .ws-layout { display:flex; gap:1rem; align-items:flex-start; }
    .ws-layout > .ws-card { flex:1 1 480px; min-width:0; margin:0; }
    .ws-layout > .ws-preview { flex:0 0 auto; width:50%; max-width:900px; position:sticky; top:0.6rem; margin:0; }
    @media (max-width:1100px) { .ws-layout { flex-direction:column; } .ws-layout > .ws-preview { position:static; width:auto; max-width:none; } }
</style>

<div class="ws-head">
    <h1>Worksheets</h1>
    <form method="get" action="/factory/worksheets.php" style="margin:0">
        <select name="product_id" onchange="this.form.submit()">
            <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $productId ? 'selected' : '' ?>><?= e((string) $p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if ($templates): ?>
    <form method="get" action="/factory/worksheets.php" style="margin:0">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <select name="template_id" onchange="this.form.submit()">
            <?php foreach ($templates as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $templateId ? 'selected' : '' ?>>
                    <?= e((string) $t['name']) ?><?= (int) $t['is_default'] ? ' (default)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
    <button type="button" class="btn ghost" id="new-tpl">+ New template</button>
</div>
<p class="ws-sub">Lay out the shop-floor worksheet: an <strong>order header</strong>, then a set of <strong>labels per blind</strong> (e.g. a cutting label and a fabric label). Drop fields onto a label — a build variable (<code>H_Cut</code>), an order detail, free text or a barcode — and set each field's printed caption and whether it shows. This is the flexibility Blind Matrix never gave you.</p>

<?php if ($flashOk !== ''): ?><div class="ws-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="ws-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$hasTable): ?>
    <div class="ws-flash err">The <code>worksheet_templates</code> table isn't there yet — run <code>/migrate_worksheet_templates.php</code>.</div>
<?php elseif (!$products): ?>
    <div class="ws-flash err">No Beverley master products found.</div>
<?php else: ?>

<div class="ws-layout">
<div class="ws-card">
    <div style="display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap; margin-bottom:0.9rem;">
        <label style="font-size:0.8rem; color:#64748b; font-weight:600;">Template name</label>
        <input type="text" id="tpl-name" value="<?= e($currentName) ?>" style="width:16rem;">
        <label style="font-size:0.85rem; display:flex; align-items:center; gap:0.35rem;"><input type="checkbox" id="tpl-default" <?= $currentIsDef ? 'checked' : '' ?>> Default for printing</label>
        <?php if (!$buildVars): ?><span class="ws-hint">Tip: this product has no build variables yet — add them in <a href="/factory/build-rules.php?product_id=<?= $productId ?>">Build rules</a> and they'll appear as field sources here.</span><?php endif; ?>
    </div>

    <div id="editor"></div>

    <div style="display:flex; gap:0.6rem; flex-wrap:wrap; margin-top:0.9rem;">
        <button type="button" class="btn ghost" id="add-label">+ Add label</button>
        <button type="button" class="btn ghost" id="load-starter">Load starter vertical layout</button>
    </div>

    <form method="post" action="/factory/worksheets.php?product_id=<?= $productId ?>" id="save-form" style="margin-top:1rem; display:flex; align-items:center; gap:0.8rem; flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <input type="hidden" name="template_id" id="f-tid" value="<?= $templateId ?>">
        <input type="hidden" name="name" id="f-name">
        <input type="hidden" name="is_default" id="f-default">
        <input type="hidden" name="payload" id="payload">
        <button type="submit" class="btn primary">Save worksheet</button>
        <?php if ($templateId > 0): ?>
        <span style="flex:1"></span>
        <button type="button" class="btn danger" id="del-btn">Delete this template</button>
        <?php endif; ?>
    </form>
    <?php if ($templateId > 0): ?>
    <form method="post" action="/factory/worksheets.php?product_id=<?= $productId ?>" id="del-form" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" id="del-action" value="delete">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <input type="hidden" name="template_id" value="<?= $templateId ?>">
    </form>
    <?php endif; ?>
</div>

<div class="ws-preview" id="preview-wrap">
    <div class="pv-head">
        <div class="ws-hint">Live preview · sample data, drawn at real label size. <strong>Drag a field on the label to reorder it.</strong> Faint italic fields only print when they have a value.</div>
        <div class="pv-zoom" title="Zoom the preview (this screen only — doesn't change the label)">
            <button type="button" id="zoom-out" aria-label="Zoom out">−</button>
            <span id="zoom-val">100%</span>
            <button type="button" id="zoom-in" aria-label="Zoom in">+</button>
            <button type="button" id="zoom-reset" class="pv-zoom-reset">Reset</button>
        </div>
    </div>
    <div class="pv-warn" id="pv-warn">⚠ Some labels are overflowing (outlined in red) — that content won't all fit at this size. Trim fields, shorten captions, or make the label bigger.</div>
    <div class="pv-sheet" id="preview"></div>
</div>
</div>

<script>
(function () {
    var BUILD_VARS  = <?= json_encode(array_values($buildVars), $jsonFlags) ?>;
    var ORDER_FIELDS = <?= json_encode($jsOrderFields, $jsonFlags) ?>;
    var SAMPLES     = <?= json_encode($jsSamples, $jsonFlags) ?>;
    var LAYOUT      = <?= json_encode($currentLayout, $jsonFlags) ?>;

    // Available field sources, grouped, for the add-field / source dropdowns.
    var SOURCES = [];
    BUILD_VARS.forEach(function (v) { SOURCES.push({ value: 'var:' + v, label: v, group: 'Build variable' }); });
    ORDER_FIELDS.forEach(function (f) { SOURCES.push({ value: 'order:' + f.key, label: f.label, group: 'Order detail' }); });
    SOURCES.push({ value: 'text', label: 'Free text', group: 'Extras' });
    SOURCES.push({ value: 'barcode:order_no', label: 'Barcode (order no)', group: 'Extras' });

    var srcLabel = {};
    SOURCES.forEach(function (s) { srcLabel[s.value] = s.label; });

    function defaultCaption(src) {
        if (src === 'text') return 'Text';
        if (src.indexOf('barcode:') === 0) return '';
        return srcLabel[src] || src;
    }

    // Starter layout modelled on the real vertical worksheet.
    var STARTER = {
        stock: 'a4-diecut',
        header: { w: 170, h: 22, fields: [
            { source: 'order:order_no',   caption: 'ONO',       show: 'always' },
            { source: 'order:order_date', caption: 'Date',      show: 'always' },
            { source: 'order:customer',   caption: 'Customer',  show: 'always' },
            { source: 'order:address',    caption: '',          show: 'ifvalue' },
            { source: 'order:post_code',  caption: '',          show: 'ifvalue' },
            { source: 'order:cust_ref',   caption: 'Cust Ref',  show: 'always' }
        ] },
        labels: [
            { title: 'Cutting label', w: 80, h: 18, fields: [
                { source: 'order:line_no',      caption: '',      show: 'always' },
                { source: 'order:system',       caption: '',      show: 'always' },
                { source: 'order:colour',       caption: '',      show: 'always' },
                { source: 'order:control',      caption: '',      show: 'always' },
                { source: 'order:bracket',      caption: '',      show: 'always' },
                { source: 'order:draw',         caption: '',      show: 'always' },
                { source: 'var:C_L',            caption: 'C/L',   show: 'always' },
                { source: 'var:CH_L',           caption: 'CH/L',  show: 'always' },
                { source: 'var:H_Cut',          caption: 'H_Cut', show: 'always' },
                { source: 'order:location',     caption: 'Loc',   show: 'always' },
                { source: 'order:size',         caption: 'Size',  show: 'always' },
                { source: 'order:recess_exact', caption: '',      show: 'always' },
                { source: 'order:fix',          caption: '',      show: 'always' },
                { source: 'order:notes',        caption: 'Notes', show: 'always' }
            ] },
            { title: 'Fabric label', w: 80, h: 18, fields: [
                { source: 'order:line_no',      caption: '',        show: 'always' },
                { source: 'order:fabric',       caption: '',        show: 'always' },
                { source: 'order:location',     caption: 'Loc',     show: 'always' },
                { source: 'var:Hem_To_Hem',     caption: 'Hem',     show: 'always' },
                { source: 'var:Mtrs',           caption: 'Mtrs',    show: 'always' },
                { source: 'var:Vanes',          caption: 'Vanes',   show: 'always' },
                { source: 'order:size',         caption: 'Size',    show: 'always' },
                { source: 'order:recess_exact', caption: '',        show: 'always' },
                { source: 'order:welded',       caption: '',        show: 'always' },
                { source: 'order:notes',        caption: 'Notes',   show: 'always' }
            ] }
        ]
    };

    var STATE = LAYOUT && LAYOUT.header ? LAYOUT
              : { stock: 'a4-diecut', header: { w: 170, h: 22, fields: [] }, labels: [] };

    var editor = document.getElementById('editor');

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    function num(v, d) { v = parseFloat(v); return (isFinite(v) && v > 0) ? v : d; }

    // Older saved layouts (and blank ones) may lack label sizes — fill defaults.
    function ensureSizes() {
        if (!STATE.header) STATE.header = { fields: [] };
        STATE.header.w = num(STATE.header.w, 170);
        STATE.header.h = num(STATE.header.h, 22);
        STATE.labels.forEach(function (l) { l.w = num(l.w, 80); l.h = num(l.h, 18); });
    }

    function sizeCtl(w, h) {
        return '<span class="size-ctl"><input type="number" class="sw" value="' + w + '" min="10" max="210" step="any" title="width mm"> ×'
             + '<input type="number" class="sh" value="' + h + '" min="5" max="297" step="any" title="height mm"> mm</span>';
    }

    function srcSelect(source) {
        var groups = {}, order = [];
        SOURCES.forEach(function (s) { if (!groups[s.group]) { groups[s.group] = []; order.push(s.group); } groups[s.group].push(s); });
        var seen = SOURCES.some(function (s) { return s.value === source; });
        var html = '<select class="src">';
        if (!seen && source) html += '<option value="' + esc(source) + '" selected>' + esc(source) + '</option>';
        order.forEach(function (g) {
            html += '<optgroup label="' + esc(g) + '">';
            groups[g].forEach(function (s) {
                html += '<option value="' + esc(s.value) + '"' + (s.value === source ? ' selected' : '') + '>' + esc(s.label) + '</option>';
            });
            html += '</optgroup>';
        });
        return html + '</select>';
    }

    function fieldRow(f) {
        if (f.source === '__break__') {
            return '<div class="fld fld-break">'
                 + '<span class="grip" title="Drag to reorder">⠿</span>'
                 + '<span class="break-label">↵ line break — fields after this start on a new line</span>'
                 + '<button type="button" class="fld-rm" title="Remove line break">×</button>'
                 + '</div>';
        }
        var isText = f.source === 'text';
        var html = '<div class="fld">';
        html += '<span class="grip" title="Drag to reorder">⠿</span>';
        html += '<input type="text" class="cap" value="' + esc(f.caption || '') + '" placeholder="' + (isText ? 'text to print' : 'caption') + '">';
        html += srcSelect(f.source);
        if (isText) html += '<span class="free">prints the caption</span>';
        else html += '<select class="show">'
            + '<option value="always"' + (f.show !== 'ifvalue' && f.show !== 'never' ? ' selected' : '') + '>Always</option>'
            + '<option value="ifvalue"' + (f.show === 'ifvalue' ? ' selected' : '') + '>If it has a value</option>'
            + '<option value="never"' + (f.show === 'never' ? ' selected' : '') + '>Never</option>'
            + '</select>';
        html += '<button type="button" class="fld-rm" title="Remove field">×</button>';
        html += '</div>';
        return html;
    }

    function addFieldControl() {
        var groups = {}, order = [];
        SOURCES.forEach(function (s) { if (!groups[s.group]) { groups[s.group] = []; order.push(s.group); } groups[s.group].push(s); });
        var html = '<div class="add-fld"><select class="add-src"><option value="">+ Add field…</option>';
        order.forEach(function (g) {
            html += '<optgroup label="' + esc(g) + '">';
            groups[g].forEach(function (s) { html += '<option value="' + esc(s.value) + '">' + esc(s.label) + '</option>'; });
            html += '</optgroup>';
        });
        html += '<optgroup label="Layout"><option value="__break__">↵ Line break</option></optgroup>';
        return html + '</select></div>';
    }

    function fieldsBlock(fields) {
        if (!fields.length) return '<div class="empty">No fields yet — add one below.</div>';
        return fields.map(fieldRow).join('');
    }

    function render() {
        var html = '';
        // Header section.
        html += '<div class="sec" data-sec="header">';
        html += '<div class="sec-top"><span class="tag">Order header</span><span class="ws-hint">prints once at the top</span>' + sizeCtl(STATE.header.w, STATE.header.h) + '</div>';
        html += '<div class="flds">' + fieldsBlock(STATE.header.fields) + '</div>';
        html += addFieldControl();
        html += '</div>';
        // Label sections.
        STATE.labels.forEach(function (lab, li) {
            html += '<div class="sec" data-sec="label" data-li="' + li + '">';
            html += '<div class="sec-top"><span class="tag">Label</span><input type="text" class="title" value="' + esc(lab.title || '') + '" placeholder="e.g. Cutting label">' + sizeCtl(lab.w, lab.h) + '<button type="button" class="btn ghost rm rm-label">Remove label</button></div>';
            html += '<div class="flds">' + fieldsBlock(lab.fields) + '</div>';
            html += addFieldControl();
            html += '</div>';
        });
        editor.innerHTML = html;
        renderPreview();
    }

    function sectionFields(sec) {
        if (sec.dataset.sec === 'header') return STATE.header.fields;
        return STATE.labels[+sec.dataset.li].fields;
    }

    function sync() {
        editor.querySelectorAll('.sec').forEach(function (sec) {
            var fields = sectionFields(sec);
            var sw = sec.querySelector('.sw'), sh = sec.querySelector('.sh');
            if (sec.dataset.sec === 'header') {
                if (sw) STATE.header.w = num(sw.value, 170);
                if (sh) STATE.header.h = num(sh.value, 22);
            } else {
                var L = STATE.labels[+sec.dataset.li];
                L.title = sec.querySelector('.title').value;
                if (sw) L.w = num(sw.value, 80);
                if (sh) L.h = num(sh.value, 18);
            }
            var rows = sec.querySelectorAll('.flds .fld');
            var out = [];
            rows.forEach(function (r) {
                if (r.classList.contains('fld-break')) { out.push({ source: '__break__' }); return; }
                var showSel = r.querySelector('select.show');
                out.push({
                    source: r.querySelector('select.src').value,
                    caption: r.querySelector('input.cap').value,
                    show: showSel ? showSel.value : 'always'
                });
            });
            fields.length = 0; Array.prototype.push.apply(fields, out);
        });
    }

    editor.addEventListener('click', function (e) {
        var sec = e.target.closest('.sec'); if (!sec) return;
        var fields = sectionFields(sec);
        if (e.target.classList.contains('fld-rm')) {
            sync();
            var idx = Array.prototype.indexOf.call(sec.querySelectorAll('.flds .fld'), e.target.closest('.fld'));
            fields.splice(idx, 1); render();
        } else if (e.target.classList.contains('rm-label')) {
            sync(); STATE.labels.splice(+sec.dataset.li, 1); render();
        }
    });

    // ---- Pointer-based drag reordering (works with mouse AND touch) -------
    // Native HTML5 drag-and-drop is flaky and dead on touchscreens, so we drive
    // it ourselves. Reused for the editor list (vertical) and the on-label
    // preview chips (horizontal).
    function makeSortable(root, opts) {
        var drag = null;   // { container, item, fromIdx, startX, startY, pid, moving }
        function items(container) {
            return Array.prototype.slice.call(container.querySelectorAll(opts.item));
        }
        function clearMarks() {
            root.querySelectorAll('.' + opts.before + ',.' + opts.after).forEach(function (x) {
                x.classList.remove(opts.before, opts.after);
            });
        }
        function targetIndex(container, ev, dragged) {
            // Index (in the current item list, dragged included) to insert before.
            var list = items(container), pos = opts.axis === 'x' ? ev.clientX : ev.clientY;
            for (var i = 0; i < list.length; i++) {
                if (list[i] === dragged) continue;
                var r = list[i].getBoundingClientRect();
                var mid = opts.axis === 'x' ? r.left + r.width / 2 : r.top + r.height / 2;
                if (pos < mid) return i;
            }
            return list.length;
        }
        root.addEventListener('pointerdown', function (e) {
            if (e.button != null && e.button !== 0) return;
            var handle = e.target.closest(opts.handle); if (!handle) return;
            var item = handle.closest(opts.item); if (!item) return;
            var container = item.closest(opts.container); if (!container) return;
            drag = { container: container, item: item, fromIdx: items(container).indexOf(item),
                     startX: e.clientX, startY: e.clientY, pid: e.pointerId, moving: false };
            e.preventDefault();   // stop text selection / native image drag
        });
        root.addEventListener('pointermove', function (e) {
            if (!drag || e.pointerId !== drag.pid) return;
            if (!drag.moving) {
                if (Math.abs(e.clientX - drag.startX) < 4 && Math.abs(e.clientY - drag.startY) < 4) return;
                drag.moving = true;
                drag.item.classList.add(opts.dragging);
                try { root.setPointerCapture(drag.pid); } catch (err) {}
            }
            var idx = targetIndex(drag.container, e, drag.item);
            var list = items(drag.container);
            clearMarks();
            if (idx < list.length) list[idx].classList.add(opts.before);
            else if (list.length) list[list.length - 1].classList.add(opts.after);
        });
        function finish(e) {
            if (!drag) return;
            var d = drag; drag = null;
            d.item.classList.remove(opts.dragging);
            try { root.releasePointerCapture(d.pid); } catch (err) {}
            if (!d.moving) { clearMarks(); return; }   // was a click, not a drag
            var insertBefore = targetIndex(d.container, e, d.item);
            clearMarks();
            sync();
            var arr = opts.arrayFor(d.container), from = d.fromIdx;
            if (from >= 0 && from < arr.length) {
                var moved = arr.splice(from, 1)[0];
                if (from < insertBefore) insertBefore--;
                if (insertBefore < 0) insertBefore = 0;
                if (insertBefore > arr.length) insertBefore = arr.length;
                arr.splice(insertBefore, 0, moved);
            }
            render();
        }
        root.addEventListener('pointerup', finish);
        root.addEventListener('pointercancel', function () {
            if (drag) { drag.item.classList.remove(opts.dragging); clearMarks(); drag = null; }
        });
    }

    makeSortable(editor, {
        handle: '.grip', item: '.fld', container: '.flds', axis: 'y',
        dragging: 'dragging', before: 'drop-above', after: 'drop-below',
        arrayFor: function (container) { return sectionFields(container.closest('.sec')); }
    });

    editor.addEventListener('change', function (e) {
        if (e.target.classList.contains('add-src')) {
            var src = e.target.value; if (!src) return;
            var sec = e.target.closest('.sec');
            sync();
            sectionFields(sec).push(src === '__break__' ? { source: '__break__' }
                                                        : { source: src, caption: defaultCaption(src), show: 'always' });
            render();
        }
    });

    document.getElementById('add-label').addEventListener('click', function () {
        sync(); STATE.labels.push({ title: 'Label', w: 80, h: 18, fields: [] }); render();
    });

    document.getElementById('load-starter').addEventListener('click', function () {
        if (STATE.header.fields.length || STATE.labels.length) {
            if (!confirm('Replace the current layout with the starter vertical layout?')) return;
        }
        STATE = JSON.parse(JSON.stringify(STARTER)); render();
    });

    document.getElementById('new-tpl').addEventListener('click', function () {
        document.getElementById('f-tid').value = '0';
        document.getElementById('tpl-name').value = 'New worksheet';
        STATE = { stock: 'a4-diecut', header: { w: 170, h: 22, fields: [] }, labels: [] };
        render();
    });

    // ---- Live preview (sample data) + drag-to-reorder on the label --------
    function valueFor(f) {
        if (f.source === 'text') return f.caption || '';
        if (f.source.indexOf('barcode:') === 0) return '▏▎▍▌▍▎▏ ' + (SAMPLES['order:' + f.source.slice(8)] || '');
        return SAMPLES[f.source] != null ? SAMPLES[f.source] : '';
    }
    // One rendered field span. `i` is the field's index in its section array;
    // `interactive` makes it draggable. Fields hidden by "if it has a value"
    // are shown as faint ghosts so they stay visible and reorderable.
    function fieldSpan(f, i, interactive) {
        if (f.source === '__break__') {
            return '<span class="pv-fld pv-break' + (interactive ? ' pv-drag' : '') + '" title="line break">↵ new line</span>';
        }
        var v = valueFor(f);
        var never = (f.show === 'never');
        var hidden = never || (f.show === 'ifvalue' && (v === '' || v == null));
        var cap = (f.caption || '').trim();
        var t;
        if (f.source === 'text') t = esc(v || cap || '(text)');
        else t = esc(cap ? (cap + ' ' + v) : String(v !== '' && v != null ? v : '·'));
        var cls = 'pv-fld' + (hidden ? ' pv-ghost' : '') + (never ? ' pv-never' : '') + (interactive ? ' pv-drag' : '');
        return '<span class="' + cls + '">' + t + '</span>';
    }
    // px per mm on screen. BASE = true label size (1:1 with the die-cut); the
    // zoom control scales it up/down for legibility (remembered per computer).
    // Font scales WITH the box so proportions — and the overflow check — hold.
    var BASE_SCALE = 4;
    var SCALE = Math.max(2, Math.min(12, parseFloat(localStorage.getItem('lblPreviewZoom')) || BASE_SCALE));
    function inlineFields(fields, ln, interactive) {
        var out = '';
        fields.forEach(function (f, i) {
            var ff = f;
            if (ln && f.source === 'order:line_no') ff = { source: 'text', caption: ln, show: 'always' };
            out += fieldSpan(ff, i, interactive);
        });
        return out || '<span class="pv-empty" style="color:#cbd5e1">(no fields — add some on the left)</span>';
    }
    function labelBox(w, h, inner, secAttr) {
        return '<div class="pv-labelbox" ' + (secAttr || '') + ' style="width:' + (w * SCALE) + 'px;height:' + (h * SCALE) + 'px;font-size:' + (2.25 * SCALE) + 'px">' + inner + '</div>';
    }
    function renderPreview() {
        sync();
        var pv = document.getElementById('preview');
        var html = '';
        html += '<div class="pv-cap">Order header · ' + STATE.header.w + ' × ' + STATE.header.h + ' mm</div>';
        html += labelBox(STATE.header.w, STATE.header.h, inlineFields(STATE.header.fields, null, true), 'data-sec="header"');
        html += '<div class="pv-badge">▼ one row per blind — sample shows two (drag on the first row) ▼</div>';
        [ '1/2', '2/2' ].forEach(function (ln, ri) {
            var interactive = (ri === 0);   // only the first sample row is draggable
            html += '<div class="pv-line2">';
            (STATE.labels.length ? STATE.labels : [{ title: '', w: 80, h: 18, fields: [] }]).forEach(function (lab, li) {
                var sec = interactive ? ('data-sec="label" data-li="' + li + '"') : '';
                html += '<div><div class="pv-cap">' + esc(lab.title || 'Label') + ' · ' + lab.w + ' × ' + lab.h + ' mm</div>'
                      + labelBox(lab.w, lab.h, inlineFields(lab.fields, ln, interactive), sec) + '</div>';
            });
            html += '</div>';
        });
        pv.innerHTML = html;
        var over = false;
        pv.querySelectorAll('.pv-labelbox').forEach(function (b) {
            if (b.scrollHeight > b.clientHeight + 1) { b.classList.add('over'); over = true; }
        });
        document.getElementById('pv-warn').style.display = over ? 'block' : 'none';
    }

    // Drag a chip on the label itself to reorder within that label (or header).
    var preview = document.getElementById('preview');
    makeSortable(preview, {
        handle: '.pv-drag', item: '.pv-drag', container: '.pv-labelbox[data-sec]', axis: 'x',
        dragging: 'pv-dragging', before: 'pv-drop-before', after: 'pv-drop-after',
        arrayFor: function (box) {
            return box.dataset.sec === 'header' ? STATE.header.fields : STATE.labels[+box.dataset.li].fields;
        }
    });

    // Keep the preview live as fields are added, edited, removed or reordered.
    editor.addEventListener('input', function () { renderPreview(); });
    editor.addEventListener('change', function (e) {
        if (!e.target.classList.contains('add-src')) renderPreview();
    });

    // Zoom control — scales the whole preview (screen only; label size unchanged).
    function setZoom(s) {
        SCALE = Math.max(2, Math.min(12, s));
        localStorage.setItem('lblPreviewZoom', SCALE);
        document.getElementById('zoom-val').textContent = Math.round(SCALE / BASE_SCALE * 100) + '%';
        renderPreview();
    }
    document.getElementById('zoom-in').addEventListener('click', function () { setZoom(SCALE + 1); });
    document.getElementById('zoom-out').addEventListener('click', function () { setZoom(SCALE - 1); });
    document.getElementById('zoom-reset').addEventListener('click', function () { setZoom(BASE_SCALE); });
    document.getElementById('zoom-val').textContent = Math.round(SCALE / BASE_SCALE * 100) + '%';

    document.getElementById('save-form').addEventListener('submit', function (e) {
        sync();
        document.getElementById('f-name').value = document.getElementById('tpl-name').value;
        document.getElementById('f-default').value = document.getElementById('tpl-default').checked ? '1' : '';
        document.getElementById('payload').value = JSON.stringify(STATE);
    });

    <?php if ($templateId > 0): ?>
    var delBtn = document.getElementById('del-btn');
    if (delBtn) delBtn.addEventListener('click', function () {
        if (confirm('Delete this worksheet template?')) document.getElementById('del-form').submit();
    });
    <?php endif; ?>

    ensureSizes();
    render();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
