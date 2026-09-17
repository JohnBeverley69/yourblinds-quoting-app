<?php
declare(strict_types=1);

/**
 * Factory · Roller label — box grid editor.
 *
 * The bespoke boxed roller label's top grid + cut row (rendered by
 * worksheet-print.php from the template's labels[0].box) are editable here:
 * rows of cells, each cell a caption + a source (option / cut var / order
 * detail) + a column width. Saves the box onto the roller worksheet template.
 * The label SIZE / font / QR stay on the Worksheets page; this is only the grid.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/roller_box.php';

requireFactory();

$pdo    = db();
$MASTER = function_exists('current_factory_id') ? current_factory_id()
        : (function_exists('factory_client_id') ? factory_client_id() : 3);

// Resolve the product + its roller worksheet template. ?product_id=N, else the
// factory's "Bev Roller Blinds".
$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
if ($productId === 0) {
    $p = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = 'Bev Roller Blinds' LIMIT 1");
    $p->execute([$MASTER]);
    $productId = (int) $p->fetchColumn();
}
$pn = $pdo->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
$pn->execute([$productId]);
$productName = (string) $pn->fetchColumn();

$t = $pdo->prepare('SELECT id, name, layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
$t->execute([$productId]);
$tpl = $t->fetch(PDO::FETCH_ASSOC);

$flashOk = ''; $flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$tpl) {
        $flashErr = 'No roller worksheet template for this product — build one on the Worksheets page first.';
    } else {
        $raw = json_decode((string) ($_POST['box_json'] ?? ''), true);
        if (!is_array($raw)) {
            $flashErr = 'Could not read the layout — nothing saved.';
        } else {
            $box = roller_box_normalise($raw);
            if (!$box['grid'] && !$box['cut']) {
                $flashErr = 'Refusing to save an empty label. Add at least one cell.';
            } else {
                try {
                    $layout = json_decode((string) $tpl['layout_json'], true);
                    if (!is_array($layout)) $layout = ['labels' => [[]]];
                    if (empty($layout['labels']) || !is_array($layout['labels'][0])) $layout['labels'] = [[]];
                    // Snapshot the current layout first (undoable) if the history table exists.
                    try {
                        $cnt = count($box['grid']) + count($box['cut']);
                        $pdo->prepare(
                            'INSERT INTO worksheet_template_versions (template_id, product_id, name, layout_json, field_count, saved_by_user_id)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        )->execute([(int) $tpl['id'], $productId, (string) $tpl['name'], (string) $tpl['layout_json'], $cnt, null]);
                    } catch (Throwable $e) { /* no history table — proceed */ }
                    $layout['labels'][0]['box'] = $box;
                    $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?')
                        ->execute([json_encode($layout, JSON_UNESCAPED_UNICODE), (int) $tpl['id']]);
                    $tpl['layout_json'] = json_encode($layout, JSON_UNESCAPED_UNICODE);
                    $flashOk = 'Saved — the roller label now uses this layout.';
                } catch (Throwable $e) {
                    $flashErr = 'Could not save: ' . $e->getMessage();
                }
            }
        }
    }
}

// The box to edit: stored one, else today's default.
$layout = $tpl ? (json_decode((string) $tpl['layout_json'], true) ?: []) : [];
$stored = $layout['labels'][0]['box'] ?? null;
$box    = (is_array($stored) && (!empty($stored['grid']) || !empty($stored['cut']))) ? roller_box_normalise($stored) : roller_box_default();

// A recent roller order to preview against (best-effort).
$previewOrder = 0;
try {
    $po = $pdo->prepare(
        "SELECT qi.quote_id FROM quote_items qi JOIN products p ON p.id = qi.product_id
          WHERE (p.id = ? OR p.source_product_id = ?) ORDER BY qi.id DESC LIMIT 1"
    );
    $po->execute([$productId, $productId]);
    $previewOrder = (int) $po->fetchColumn();
} catch (Throwable $e) { /* none */ }

$SOURCES = roller_box_sources();
$e2 = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// Source <option> HTML reused for every cell picker.
$srcOptions = '';
foreach ($SOURCES as $group => $opts) {
    $srcOptions .= '<optgroup label="' . $e2($group) . '">';
    foreach ($opts as $val => $lab) $srcOptions .= '<option value="' . $e2($val) . '">' . $e2($lab) . '</option>';
    $srcOptions .= '</optgroup>';
}

$factoryTitle = 'Roller label layout';
$factoryNav   = 'build';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .rb-head { display:flex; align-items:baseline; gap:1rem; flex-wrap:wrap; margin:0 0 0.4rem; }
    .rb-head h1 { font-size:1.5rem; font-weight:700; margin:0; }
    .rb-sub { color:var(--text-muted,#667); margin:0 0 1.1rem; max-width:80ch; line-height:1.5; }
    .rb-flash { padding:0.65rem 1rem; border-radius:9px; margin:0 0 1rem; font-size:0.92rem; }
    .rb-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .rb-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .rb-sec { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1rem 1.1rem; margin:0 0 1rem; max-width:1000px; }
    .rb-sec h2 { font-size:0.78rem; text-transform:uppercase; letter-spacing:0.05em; color:#1f3b5b; margin:0 0 0.7rem; }
    .bx-row { border:1px solid #e5e7eb; border-radius:9px; padding:0.55rem 0.6rem; margin:0 0 0.55rem; background:#fafbfc; }
    .bx-row-top { display:flex; align-items:center; gap:0.6rem; margin:0 0 0.5rem; font-size:0.8rem; color:#64748b; }
    .bx-cells { display:flex; flex-direction:column; gap:0.4rem; }
    .bx-cell { display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap; }
    .bx-cell input.cap { width:12rem; }
    .bx-cell select.src { min-width:15rem; }
    .bx-cell input.w { width:4rem; text-align:right; }
    .bx-cell .lbl { font-size:0.72rem; color:#94a3b8; }
    input, select { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:7px; padding:0.35rem 0.5rem; background:var(--bg-input,#fff); color:inherit; }
    .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.4rem 0.85rem; }
    .btn.primary { background:#166534; color:#fff; } .btn.primary:hover { background:#14532d; }
    .btn.ghost { background:#eef2f7; color:#334155; } .btn.ghost:hover { background:#e2e8f0; }
    .btn.rm { background:none; color:#cbd5e1; padding:0.2rem 0.4rem; } .btn.rm:hover { color:#dc2626; }
    .rb-actions { display:flex; gap:0.7rem; align-items:center; flex-wrap:wrap; margin-top:0.4rem; }
    .rb-hint { font-size:0.78rem; color:#94a3b8; line-height:1.5; margin-top:0.5rem; }
    a.rb-link { color:#1f3b5b; font-weight:600; }
</style>

<div class="rb-head">
    <h1>Roller label layout</h1>
    <?php if ($productName !== ''): ?><span style="color:var(--text-muted,#667)"><?= $e2($productName) ?></span><?php endif; ?>
    <a class="rb-link" href="/factory/worksheets.php?product_id=<?= (int) $productId ?>">&larr; Worksheets (size / font / QR)</a>
</div>
<p class="rb-sub">The boxes on the bespoke roller label. Each row holds one or more boxes; a box shows a <b>caption</b> and a <b>value</b> (an option, a cut size, or an order detail), with a <b>width</b> (relative — bigger = wider). The bottom <b>cut row</b> prints big and bold. The label’s overall size, font and QR are set on the <a class="rb-link" href="/factory/worksheets.php?product_id=<?= (int) $productId ?>">Worksheets</a> page.</p>

<?php if ($flashOk !== ''): ?><div class="rb-flash ok"><?= $e2($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="rb-flash err"><?= $e2($flashErr) ?></div><?php endif; ?>

<?php if (!$tpl): ?>
<div class="rb-sec">No roller worksheet template for this product yet — create one on the <a class="rb-link" href="/factory/worksheets.php?product_id=<?= (int) $productId ?>">Worksheets</a> page first.</div>
<?php else: ?>
<form method="post" action="/factory/roller-label-editor.php?product_id=<?= (int) $productId ?>" id="rb-form">
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
    <input type="hidden" name="box_json" id="box_json">

    <div class="rb-sec">
        <h2>Grid rows</h2>
        <div id="grid"></div>
        <div class="rb-actions"><button type="button" class="btn ghost" id="add-row">+ Add row</button></div>
    </div>

    <div class="rb-sec">
        <h2>Cut row (big numbers)</h2>
        <div id="cut" class="bx-cells"></div>
        <div class="rb-actions"><button type="button" class="btn ghost" id="add-cut">+ Add cut box</button></div>
    </div>

    <div class="rb-actions">
        <button type="submit" class="btn primary">Save layout</button>
        <?php if ($previewOrder > 0): ?>
            <a class="rb-link" href="/factory/worksheet-print.php?order=<?= (int) $previewOrder ?>&rolllabel=1" target="_blank">View printed label →</a>
        <?php else: ?>
            <span class="rb-hint">Print a roller order’s roll label to see the result.</span>
        <?php endif; ?>
    </div>
    <p class="rb-hint">Removing a row’s last box deletes the row. “Only show if it has a value” hides that row on labels where all its boxes are blank (e.g. Braid / Pole).</p>
</form>

<script>
(function () {
    var BOX = <?= json_encode($box, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var SRC_OPTIONS = <?= json_encode($srcOptions, JSON_UNESCAPED_UNICODE) ?>;
    var grid = document.getElementById('grid');
    var cut  = document.getElementById('cut');

    function cellNode(c, isCut) {
        var d = document.createElement('div'); d.className = 'bx-cell';
        var sel = '<select class="src">' + SRC_OPTIONS + '</select>';
        d.innerHTML = '<span class="lbl">Caption</span><input class="cap" type="text" value="">'
                    + '<span class="lbl">Value</span>' + sel
                    + '<span class="lbl">Width</span><input class="w" type="number" min="0.5" step="0.5" value="' + ((c && c.w) || 3) + '">'
                    + '<button type="button" class="btn rm" title="Remove box">✕</button>';
        d.querySelector('.cap').value = (c && c.cap) || '';
        var s = d.querySelector('.src'); if (c && c.src) s.value = c.src;
        d.querySelector('.rm').addEventListener('click', function () {
            var wrap = d.parentNode; d.remove();
            // if a grid row lost its last cell, drop the row
            if (!isCut && wrap && wrap.classList.contains('bx-cells') && wrap.children.length === 0) wrap.closest('.bx-row').remove();
        });
        return d;
    }
    function rowNode(r) {
        var row = document.createElement('div'); row.className = 'bx-row';
        var hide = !!(r && r.hide_if_empty);
        row.innerHTML = '<div class="bx-row-top">'
            + '<label style="display:flex;align-items:center;gap:0.35rem;cursor:pointer"><input type="checkbox" class="hide"' + (hide ? ' checked' : '') + '> Only show if it has a value</label>'
            + '<button type="button" class="btn ghost addcell" style="margin-left:auto;padding:0.25rem 0.6rem">+ Box</button>'
            + '<button type="button" class="btn rm delrow" title="Remove row">Remove row</button>'
            + '</div><div class="bx-cells"></div>';
        var cells = row.querySelector('.bx-cells');
        ((r && r.cells) || []).forEach(function (c) { cells.appendChild(cellNode(c, false)); });
        row.querySelector('.addcell').addEventListener('click', function () { cells.appendChild(cellNode({ w: 3 }, false)); });
        row.querySelector('.delrow').addEventListener('click', function () { row.remove(); });
        return row;
    }

    (BOX.grid || []).forEach(function (r) { grid.appendChild(rowNode(r)); });
    (BOX.cut  || []).forEach(function (c) { cut.appendChild(cellNode(c, true)); });

    document.getElementById('add-row').addEventListener('click', function () {
        grid.appendChild(rowNode({ cells: [{ w: 3 }] }));
    });
    document.getElementById('add-cut').addEventListener('click', function () {
        cut.appendChild(cellNode({ w: 2 }, true));
    });

    function readCell(d) {
        return { cap: d.querySelector('.cap').value.trim(), src: d.querySelector('.src').value, w: parseFloat(d.querySelector('.w').value) || 1 };
    }
    document.getElementById('rb-form').addEventListener('submit', function () {
        var out = { grid: [], cut: [] };
        grid.querySelectorAll('.bx-row').forEach(function (row) {
            var cells = [];
            row.querySelectorAll('.bx-cells .bx-cell').forEach(function (d) { var c = readCell(d); if (c.cap || c.src) cells.push(c); });
            if (!cells.length) return;
            var r = { cells: cells };
            if (row.querySelector('.hide').checked) r.hide_if_empty = true;
            out.grid.push(r);
        });
        cut.querySelectorAll('.bx-cell').forEach(function (d) { var c = readCell(d); if (c.cap || c.src) out.cut.push(c); });
        document.getElementById('box_json').value = JSON.stringify(out);
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
