<?php
declare(strict_types=1);

/**
 * Factory · Build Rules (decision tables).
 *
 * The self-serviceable replacement for Blind Matrix's formula screen. Each
 * product has a series of named build VARIABLES (H_Cut, Control_Length, …) —
 * the items that print on the worksheet/label. A variable's value is worked
 * out from a small decision table you read left → right:
 *
 *   question columns  →  what happens
 *   System · Control · Draw · Exact/Recess  →  Width - 25
 *
 * The question columns are bound to the product's OWN options (product_systems
 * + product_extras), so every cell is a dropdown of real values — nothing can
 * be mistyped. Rows are first-match-wins top to bottom; a blank cell means
 * "— any —". A live TEST panel picks options and shows each variable's value,
 * or flags the ones with no matching row.
 *
 * Rules belong to the Beverley master products (client #3). Storage:
 * build_variables (see migrate_build_variables.php). Evaluated by
 * _partials/formula_engine.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/formula_engine.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

/** Normalise pretty math glyphs (−, ×, ÷, dashes, nbsp) to ASCII for the engine. */
function br_norm_math(string $s): string {
    return strtr($s, [
        "\u{2212}" => '-', "\u{2013}" => '-', "\u{2014}" => '-',
        "\u{00D7}" => '*', "\u{00F7}" => '/', "\u{00A0}" => ' ',
    ]);
}

// Master products (the "Bev …" catalogue) — the things rules attach to.
$products = [];
try {
    $ps = $pdo->prepare(
        "SELECT id, name FROM products
          WHERE client_id = ? AND name LIKE 'Bev%'
          ORDER BY name"
    );
    $ps->execute([$MASTER]);
    $products = $ps->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* handled in view */ }

$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
if ($productId === 0 && $products) $productId = (int) $products[0]['id'];
$productName = '';
foreach ($products as $p) { if ((int) $p['id'] === $productId) $productName = (string) $p['name']; }

$hasTable = false;
try { $pdo->query('SELECT 1 FROM build_variables LIMIT 0'); $hasTable = true; }
catch (Throwable $e) { /* not migrated */ }

// ---- Option sources for this product (what the columns bind to) -----------
// Each source: ['ref' => 'system' | 'extra:<id>', 'label' => name, 'values' => [labels]].
$optionSources = [];
if ($productId > 0) {
    // The System axis.
    try {
        $ss = $pdo->prepare(
            "SELECT name FROM product_systems
              WHERE product_id = ? AND client_id = ? AND active = 1
              ORDER BY sort_order, name"
        );
        $ss->execute([$productId, $MASTER]);
        $systems = $ss->fetchAll(PDO::FETCH_COLUMN);
        if ($systems) $optionSources[] = ['ref' => 'system', 'label' => 'System', 'values' => array_values($systems)];
    } catch (Throwable $e) { /* no systems table/rows */ }

    // The option-groups (Control, Draw, …) and their distinct values.
    try {
        $gs = $pdo->prepare(
            "SELECT id, name FROM product_extras
              WHERE product_id = ? AND client_id = ? AND active = 1
              ORDER BY sort_order, name"
        );
        $gs->execute([$productId, $MASTER]);
        $extraRows = $gs->fetchAll(PDO::FETCH_ASSOC);
        if ($extraRows) {
            $ids = array_map(static fn ($r) => (int) $r['id'], $extraRows);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $cs  = $pdo->prepare(
                "SELECT product_extra_id, label FROM product_extra_choices
                  WHERE product_extra_id IN ($in) AND active = 1
                  ORDER BY product_extra_id, sort_order, label"
            );
            $cs->execute($ids);
            $byExtra = [];   // choices duplicate across systems → keep distinct labels
            foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $byExtra[(int) $c['product_extra_id']][(string) $c['label']] = true;
            }
            foreach ($extraRows as $er) {
                $eid = (int) $er['id'];
                $optionSources[] = [
                    'ref'    => 'extra:' . $eid,
                    'label'  => (string) $er['name'],
                    'values' => array_keys($byExtra[$eid] ?? []),
                ];
            }
        }
    } catch (Throwable $e) { /* no extras */ }
}
// Quick ref → source lookup.
$sourceByRef = [];
foreach ($optionSources as $os) $sourceByRef[$os['ref']] = $os;

// ---- Load this product's saved variables ----------------------------------
$variables = [];
if ($hasTable && $productId > 0) {
    try {
        $vs = $pdo->prepare('SELECT name, columns_json, rows_json FROM build_variables WHERE product_id = ? ORDER BY seq, id');
        $vs->execute([$productId]);
        foreach ($vs->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $variables[] = [
                'name'    => (string) $r['name'],
                'columns' => json_decode((string) $r['columns_json'], true) ?: [],
                'rows'    => json_decode((string) $r['rows_json'], true) ?: [],
            ];
        }
    } catch (Throwable $e) { /* ignore */ }
}

// ---- POST: save the tables, or run the test -------------------------------
$testResults = null;
$testOpts    = [];   // ref => selected label (to repopulate)
$testNums    = ['Width' => '', 'Drop' => ''];
$testExtra   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'save' && $productId > 0 && $hasTable) {
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
        if (!is_array($payload)) {
            $_SESSION['flash_error'] = 'Could not read the editor data — nothing saved.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM build_variables WHERE product_id = ?')->execute([$productId]);
                $ins = $pdo->prepare(
                    'INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json) VALUES (?, ?, ?, ?, ?)'
                );
                $seq = 0; $saved = 0; $seen = [];
                foreach ($payload as $v) {
                    $name = trim((string) ($v['name'] ?? ''));
                    if ($name === '') continue;
                    $name = mb_substr($name, 0, 64);
                    $key  = mb_strtolower($name);
                    if (isset($seen[$key])) continue;   // unique per product
                    $seen[$key] = true;

                    $cols = [];
                    foreach ((array) ($v['columns'] ?? []) as $c) {
                        $ref = (string) ($c['ref'] ?? '');
                        if ($ref !== 'system' && !preg_match('/^extra:\d+$/', $ref)) continue;
                        $cols[] = ['ref' => $ref, 'label' => trim((string) ($c['label'] ?? ''))];
                    }
                    $rows = [];
                    foreach ((array) ($v['rows'] ?? []) as $row) {
                        $cells = array_map(static fn ($x) => trim((string) $x), (array) ($row['cells'] ?? []));
                        $cells = array_slice(array_pad($cells, count($cols), ''), 0, count($cols));
                        $result = br_norm_math(trim((string) ($row['result'] ?? '')));
                        $hasCell = false;
                        foreach ($cells as $cc) { if ($cc !== '') { $hasCell = true; break; } }
                        if ($result === '' && !$hasCell) continue;   // blank row dropped
                        $rows[] = ['cells' => $cells, 'result' => $result];
                    }
                    $ins->execute([
                        $productId, $name, $seq++,
                        json_encode($cols, JSON_UNESCAPED_UNICODE),
                        json_encode($rows, JSON_UNESCAPED_UNICODE),
                    ]);
                    $saved++;
                }
                $pdo->commit();
                $_SESSION['flash_success'] = "Saved {$saved} variable" . ($saved === 1 ? '' : 's') . " for {$productName}.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
            }
        }
        header('Location: /factory/build-rules.php?product_id=' . $productId);
        exit;
    }

    if ($action === 'test') {
        foreach ((array) ($_POST['sample_opt'] ?? []) as $ref => $label) {
            $testOpts[(string) $ref] = trim((string) $label);
        }
        foreach (['Width', 'Drop'] as $k) {
            $testNums[$k] = trim((string) ($_POST['num'][$k] ?? ''));
        }
        $testExtra = (string) ($_POST['num_extra'] ?? '');

        // Numeric/context vars available to the result formulas.
        $vars = [];
        foreach ($testNums as $k => $val) {
            if ($val !== '' && is_numeric($val)) $vars[$k] = (float) $val;
        }
        foreach (preg_split('/[\r\n,]+/', $testExtra) as $pair) {
            if (strpos($pair, '=') === false) continue;
            [$k, $val] = explode('=', $pair, 2);
            $k = trim($k); $val = trim($val);
            if ($k === '') continue;
            $vars[$k] = is_numeric($val) ? (float) $val : $val;
        }

        // Shared allowance tables, so a "what happens" formula can still LOOKUP().
        $allowances = [];
        try {
            foreach ($pdo->query('SELECT table_name, key_norm, value FROM allowance_rows') as $ar) {
                $allowances[strtolower((string) $ar['table_name'])][(string) $ar['key_norm']] = (float) $ar['value'];
            }
        } catch (Throwable $e) { /* allowance_rows not migrated */ }

        // Evaluate each variable: first matching row wins; result feeds later vars.
        $testResults = [];
        foreach ($variables as $v) {
            $match = null;
            foreach ($v['rows'] as $row) {
                $ok = true;
                foreach ($v['columns'] as $i => $col) {
                    $cell = trim((string) ($row['cells'][$i] ?? ''));
                    if ($cell === '') continue;   // — any —
                    $sel = trim((string) ($testOpts[$col['ref']] ?? ''));
                    if (mb_strtolower($cell) !== mb_strtolower($sel)) { $ok = false; break; }
                }
                if ($ok) { $match = $row; break; }
            }
            if ($match === null) {
                $testResults[] = ['var' => $v['name'], 'ok' => false, 'value' => 'no rule matched'];
                continue;
            }
            try {
                $val = formula_eval(br_norm_math((string) $match['result']), $vars, $allowances);
                $vars[$v['name']] = $val;
                $testResults[] = ['var' => $v['name'], 'ok' => true, 'value' => $val];
            } catch (Throwable $e) {
                $testResults[] = ['var' => $v['name'], 'ok' => false, 'value' => $e->getMessage()];
            }
        }
    }
}

// Distinct question-columns across saved variables → the test panel's dropdowns.
$testCols = [];
foreach ($variables as $v) {
    foreach ($v['columns'] as $col) {
        $ref = (string) ($col['ref'] ?? '');
        if ($ref === '' || isset($testCols[$ref])) continue;
        $src = $sourceByRef[$ref] ?? null;
        $testCols[$ref] = [
            'label'  => $src['label'] ?? (string) ($col['label'] ?? $ref),
            'values' => $src['values'] ?? [],
        ];
    }
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fmtVal = static function ($v): string {
    if (is_float($v)) return rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
    if (is_bool($v))  return $v ? 'TRUE' : 'FALSE';
    return (string) $v;
};

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

$factoryTitle = 'Build Rules';
$factoryNav   = 'build';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .br-head { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:0 0 0.6rem; }
    .br-head h1 { font-size:1.6rem; font-weight:700; margin:0; }
    .br-sub { color:var(--text-muted,#667); margin:0 0 1.3rem; max-width:74ch; line-height:1.55; }
    .br-flash { padding:0.7rem 1rem; border-radius:10px; margin:0 0 1.2rem; font-size:0.9375rem; }
    .br-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .br-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .br-grid { display:grid; grid-template-columns:minmax(0,2.3fr) minmax(0,1fr); gap:1.25rem; align-items:start; }
    @media (max-width:1000px){ .br-grid{ grid-template-columns:1fr; } }
    .br-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .br-card h2 { font-size:1rem; margin:0 0 0.9rem; }
    select, input[type=text], input[type=number], textarea { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:0.4rem 0.55rem; background:var(--bg-input,#fff); color:inherit; }
    select:disabled { color:#94a3b8; background:#f8fafc; }
    .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.5rem 1rem; }
    .btn.primary { background:#166534; color:#fff; } .btn.primary:hover{ background:#14532d; }
    .btn.dark { background:#1f2a37; color:#fff; } .btn.dark:hover{ background:#111a24; }
    .btn.ghost { background:#f1f5f9; color:#334155; font-weight:500; padding:0.4rem 0.7rem; font-size:0.85rem; }
    .btn.ghost:hover { background:#e2e8f0; }
    .br-hint { font-size:0.8rem; color:var(--text-faint,#94a3b8); margin-top:0.5rem; line-height:1.5; }
    code { background:var(--bg-subtle,#f1f5f9); padding:0.05rem 0.3rem; border-radius:4px; font-size:0.85em; }

    .var-card { border:1px solid #e5e7eb; border-radius:12px; padding:1rem 1.1rem; margin-bottom:1rem; background:#fcfcfd; }
    .var-top { display:flex; align-items:center; gap:0.6rem; margin-bottom:0.2rem; }
    .var-top .lbl { font-size:0.72rem; letter-spacing:0.04em; text-transform:uppercase; color:#94a3b8; font-weight:600; }
    .var-top input.vname { font-weight:600; width:13rem; font-family:ui-monospace,Consolas,monospace; }
    .var-top .rm-var { margin-left:auto; }
    .var-cap { font-size:0.78rem; color:#94a3b8; margin:0 0 0.8rem; }
    .var-cap code { font-size:0.9em; }
    .dt-wrap { overflow-x:auto; }
    table.dt { border-collapse:separate; border-spacing:0; width:100%; font-size:0.86rem; }
    table.dt th { text-align:left; font-weight:600; color:#475569; padding:4px 7px; white-space:nowrap; }
    table.dt th.res { color:#166534; border-left:1px solid #e5e7eb; padding-left:12px; }
    table.dt td { padding:3px 7px; }
    table.dt td.res { border-left:1px solid #e5e7eb; padding-left:12px; }
    table.dt select { min-width:9rem; }
    table.dt input.res { font-family:ui-monospace,Consolas,monospace; min-width:12rem; width:100%; }
    .col-rm, .row-rm { cursor:pointer; color:#cbd5e1; font-weight:700; border:none; background:none; padding:0 0.2rem; font-size:0.95rem; }
    .col-rm:hover, .row-rm:hover { color:#ef4444; }
    .var-actions { display:flex; gap:0.5rem; margin-top:0.8rem; flex-wrap:wrap; }
    .empty { color:#94a3b8; font-size:0.9rem; padding:0.4rem 0; }

    .tp-field { margin-bottom:0.7rem; }
    .tp-field label { display:block; font-size:0.78rem; color:#64748b; font-weight:600; margin-bottom:0.2rem; }
    .tp-field select, .tp-field input, .tp-field textarea { width:100%; }
    .tp-nums { display:flex; gap:0.6rem; }
    .tp-nums .tp-field { flex:1; }
    .br-res { width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.9rem; }
    .br-res td { padding:0.35rem 0.5rem; border-bottom:1px solid var(--border,#eee); }
    .br-res .v { font-family:ui-monospace,Consolas,monospace; font-weight:600; }
    .br-res .val { text-align:right; font-variant-numeric:tabular-nums; }
    .br-res tr.err td { color:#991b1b; }
    .br-res tr.err .val { text-align:left; font-family:inherit; font-size:0.82rem; }
</style>

<div class="br-head">
    <h1>Build Rules</h1>
    <form method="get" action="/factory/build-rules.php" style="margin:0">
        <select name="product_id" onchange="this.form.submit()">
            <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $productId ? 'selected' : '' ?>>
                    <?= e((string) $p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<p class="br-sub">Each <strong>variable</strong> is an item that prints on the worksheet &amp; label (e.g. <code>H_Cut</code>). Its value is worked out from a table you read left&nbsp;→&nbsp;right: the question columns are this product's own options, the last column is what happens. Rows are checked top&nbsp;to&nbsp;bottom and the <em>first match wins</em>; a blank cell means <strong>— any —</strong>. What happens can be a figure (<code>-25</code>) or a formula (<code>Width - 12</code>, <code>Drop * 1.5 + 2 * Width</code>).</p>

<?php if ($flashOk !== ''): ?><div class="br-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="br-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$hasTable): ?>
    <div class="br-flash err">The <code>build_variables</code> table isn't there yet — run <code>/migrate_build_variables.php</code>.</div>
<?php elseif (!$products): ?>
    <div class="br-flash err">No Beverley master products found.</div>
<?php else: ?>
<div class="br-grid">
    <!-- Editor -->
    <div class="br-card">
        <h2>Rules for <?= e($productName) ?></h2>
        <?php if (!$optionSources): ?>
            <div class="br-flash err" style="margin-bottom:1rem">This product has no options (systems or option-groups) set up yet, so there's nothing for the question columns to bind to. Add its options first, then build the rules.</div>
        <?php endif; ?>
        <div id="editor"></div>
        <div class="var-actions" style="margin-top:1rem">
            <button type="button" class="btn ghost" id="add-variable"><span style="font-size:1.05em">+</span> Add variable</button>
        </div>
        <form method="post" action="/factory/build-rules.php" id="save-form" style="margin-top:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <input type="hidden" name="payload" id="payload">
            <button type="submit" class="btn primary" id="save-btn">Save rules</button>
            <span class="br-hint" style="margin-left:0.6rem">A variable with no name, and fully-blank rows, are dropped on save.</span>
        </form>
    </div>

    <!-- Test panel -->
    <div class="br-card">
        <h2>Test panel</h2>
        <?php if (!$variables): ?>
            <p class="br-hint">Add and save a variable, then test it here.</p>
        <?php else: ?>
        <form method="post" action="/factory/build-rules.php?product_id=<?= $productId ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="test">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <div class="tp-nums">
                <div class="tp-field">
                    <label>Width (mm)</label>
                    <input type="number" step="any" name="num[Width]" value="<?= e($testNums['Width']) ?>" placeholder="1200">
                </div>
                <div class="tp-field">
                    <label>Drop (mm)</label>
                    <input type="number" step="any" name="num[Drop]" value="<?= e($testNums['Drop']) ?>" placeholder="1500">
                </div>
            </div>
            <?php foreach ($testCols as $ref => $tc): ?>
                <div class="tp-field">
                    <label><?= e((string) $tc['label']) ?></label>
                    <select name="sample_opt[<?= e((string) $ref) ?>]">
                        <option value="">— choose —</option>
                        <?php foreach ($tc['values'] as $val): ?>
                            <option value="<?= e((string) $val) ?>" <?= ($testOpts[$ref] ?? '') === $val ? 'selected' : '' ?>><?= e((string) $val) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="tp-field">
                <label>Other inputs (optional, <code>Name = value</code>)</label>
                <textarea name="num_extra" rows="2" placeholder="Louvres = 12"><?= e($testExtra) ?></textarea>
            </div>
            <button type="submit" class="btn dark">Run test</button>
        </form>
        <?php endif; ?>

        <?php if ($testResults !== null): ?>
            <?php if (!$testResults): ?>
                <p class="br-hint">No variables to evaluate yet.</p>
            <?php else: ?>
                <table class="br-res">
                    <?php foreach ($testResults as $tr): ?>
                        <tr class="<?= $tr['ok'] ? '' : 'err' ?>">
                            <td class="v"><?= e($tr['var']) ?></td>
                            <td class="val"><?= $tr['ok'] ? e($fmtVal($tr['value'])) : '⚠ ' . e((string) $tr['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var SOURCES = <?= json_encode($optionSources, $jsonFlags) ?>;
    var STATE   = <?= json_encode($variables, $jsonFlags) ?>;

    var srcByRef = {};
    SOURCES.forEach(function (s) { srcByRef[s.ref] = s; });

    var editor = document.getElementById('editor');

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    }); }

    // Build a <select> of a source's values (+ "— any —") for a cell.
    function cellSelect(ref, value) {
        var src = srcByRef[ref];
        var opts = ['<option value="">— any —</option>'];
        var vals = (src && src.values) ? src.values.slice() : [];
        if (value && vals.indexOf(value) === -1) vals.push(value);   // preserve unknown
        vals.forEach(function (v) {
            opts.push('<option value="' + esc(v) + '"' + (v === value ? ' selected' : '') + '>' + esc(v) + '</option>');
        });
        return '<select class="cell">' + opts.join('') + '</select>';
    }

    function render() {
        if (!STATE.length) {
            editor.innerHTML = '<div class="empty">No variables yet. Click “Add variable” to start.</div>';
            return;
        }
        var html = '';
        STATE.forEach(function (v, vi) {
            html += '<div class="var-card" data-vi="' + vi + '">';
            html += '<div class="var-top">';
            html += '<span class="lbl">Variable</span>';
            html += '<input type="text" class="vname" value="' + esc(v.name) + '" placeholder="H_Cut">';
            html += '<button type="button" class="btn ghost rm-var">Remove variable</button>';
            html += '</div>';
            html += '<p class="var-cap">Prints on the worksheet &amp; label' + (v.name ? ' as <code>' + esc(v.name) + '</code>' : '') + '.</p>';

            html += '<div class="dt-wrap"><table class="dt"><thead><tr>';
            v.columns.forEach(function (c, ci) {
                html += '<th>' + esc(c.label) + ' <button type="button" class="col-rm" data-ci="' + ci + '" title="Remove column">×</button></th>';
            });
            html += '<th class="res">→ What happens</th><th></th></tr></thead><tbody>';

            if (!v.rows.length) {
                html += '<tr><td colspan="' + (v.columns.length + 2) + '" class="empty">No rows yet — add one below.</td></tr>';
            }
            v.rows.forEach(function (row, ri) {
                html += '<tr data-ri="' + ri + '">';
                v.columns.forEach(function (c, ci) {
                    html += '<td>' + cellSelect(c.ref, (row.cells && row.cells[ci]) || '') + '</td>';
                });
                html += '<td class="res"><input type="text" class="res" value="' + esc(row.result || '') + '" placeholder="Width - 12"></td>';
                html += '<td><button type="button" class="row-rm" title="Remove row">×</button></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            html += '<div class="var-actions">';
            html += '<button type="button" class="btn ghost add-row">+ Add row</button>';
            // Add-question dropdown: sources not already used by this variable.
            var used = {};
            v.columns.forEach(function (c) { used[c.ref] = true; });
            var avail = SOURCES.filter(function (s) { return !used[s.ref]; });
            if (avail.length) {
                html += '<select class="add-col"><option value="">+ Add question column…</option>';
                avail.forEach(function (s) { html += '<option value="' + esc(s.ref) + '">' + esc(s.label) + '</option>'; });
                html += '</select>';
            }
            html += '</div>';
            html += '</div>';
        });
        editor.innerHTML = html;
    }

    // Pull the current DOM values back into STATE (names, cells, results).
    function sync() {
        editor.querySelectorAll('.var-card').forEach(function (card) {
            var vi = +card.dataset.vi, v = STATE[vi];
            v.name = card.querySelector('.vname').value;
            card.querySelectorAll('tbody tr[data-ri]').forEach(function (tr) {
                var ri = +tr.dataset.ri, row = v.rows[ri];
                var cells = tr.querySelectorAll('select.cell');
                row.cells = Array.prototype.map.call(cells, function (s) { return s.value; });
                row.result = tr.querySelector('input.res').value;
            });
        });
    }

    editor.addEventListener('click', function (e) {
        var card = e.target.closest('.var-card');
        if (!card) return;
        var vi = +card.dataset.vi, v = STATE[vi];
        if (e.target.classList.contains('rm-var')) {
            sync(); STATE.splice(vi, 1); render();
        } else if (e.target.classList.contains('add-row')) {
            sync(); v.rows.push({ cells: v.columns.map(function () { return ''; }), result: '' }); render();
        } else if (e.target.classList.contains('row-rm')) {
            sync(); v.rows.splice(+e.target.closest('tr').dataset.ri, 1); render();
        } else if (e.target.classList.contains('col-rm')) {
            sync();
            var ci = +e.target.dataset.ci;
            v.columns.splice(ci, 1);
            v.rows.forEach(function (r) { r.cells.splice(ci, 1); });
            render();
        }
    });

    editor.addEventListener('change', function (e) {
        if (e.target.classList.contains('add-col')) {
            var ref = e.target.value; if (!ref) return;
            var card = e.target.closest('.var-card'), v = STATE[+card.dataset.vi];
            var src = srcByRef[ref]; if (!src) return;
            sync();
            v.columns.push({ ref: ref, label: src.label });
            v.rows.forEach(function (r) { r.cells.push(''); });
            render();
        }
    });

    document.getElementById('add-variable').addEventListener('click', function () {
        sync();
        STATE.push({ name: '', columns: [], rows: [{ cells: [], result: '' }] });
        render();
    });

    document.getElementById('save-form').addEventListener('submit', function () {
        sync();
        document.getElementById('payload').value = JSON.stringify(STATE);
    });

    render();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
