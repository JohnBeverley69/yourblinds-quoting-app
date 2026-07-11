<?php
declare(strict_types=1);

/**
 * Factory · Build Rules.
 *
 * The self-serviceable replacement for Blind Matrix's formula screen: per
 * product, edit the named build formulas (deductions, cut sizes, vane counts)
 * that turn an order's inputs into cut sizes + a component list. A live TEST
 * panel evaluates the saved rules against sample inputs and shows every
 * output — or the exact formula that errors — which is the thing Blind Matrix
 * utterly lacks.
 *
 * Rules belong to the Beverley master products (client #3).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/formula_engine.php';

requireFactory();

$pdo    = db();
$MASTER = factory_client_id();

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

$hasBuildRules = false;
try { $pdo->query('SELECT 1 FROM build_rules LIMIT 0'); $hasBuildRules = true; }
catch (Throwable $e) { /* not migrated */ }

// ---- POST: save rules, or run the test ------------------------------------
$sample      = '';
$testResults = null;
$testError   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'save' && $productId > 0 && $hasBuildRules) {
        $vars     = (array) ($_POST['variable'] ?? []);
        $formulas = (array) ($_POST['formula'] ?? []);
        $seqs     = (array) ($_POST['seq'] ?? []);
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM build_rules WHERE product_id = ?')->execute([$productId]);
            $ins = $pdo->prepare(
                'INSERT INTO build_rules (product_id, variable, formula, seq) VALUES (?, ?, ?, ?)'
            );
            $n = 0;
            foreach ($vars as $i => $v) {
                $v = trim((string) $v);
                $f = trim((string) ($formulas[$i] ?? ''));
                if ($v === '' || $f === '') continue;   // blank row = dropped
                $ins->execute([$productId, $v, $f, (int) ($seqs[$i] ?? $n)]);
                $n++;
            }
            $pdo->commit();
            $_SESSION['flash_success'] = "Saved {$n} rule" . ($n === 1 ? '' : 's') . " for {$productName}.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not save rules: ' . $e->getMessage();
        }
        header('Location: /factory/build-rules.php?product_id=' . $productId);
        exit;
    }

    if ($action === 'test') {
        $sample = (string) ($_POST['sample'] ?? '');
        // Parse "Name = value" pairs (comma- or newline-separated).
        $vars = [];
        foreach (preg_split('/[\r\n,]+/', $sample) as $pair) {
            if (strpos($pair, '=') === false) continue;
            [$k, $val] = explode('=', $pair, 2);
            $k = trim($k); $val = trim($val);
            if ($k === '') continue;
            $vars[$k] = is_numeric($val) ? (float) $val : $val;
        }
        // Evaluate the SAVED rules in seq order; each output feeds the next.
        $testResults = [];
        if ($hasBuildRules && $productId > 0) {
            $rs = $pdo->prepare('SELECT variable, formula FROM build_rules WHERE product_id = ? ORDER BY seq, id');
            $rs->execute([$productId]);
            foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $var = (string) $r['variable'];
                try {
                    $val = formula_eval((string) $r['formula'], $vars);
                    $vars[$var] = $val;
                    $testResults[] = ['var' => $var, 'ok' => true, 'value' => $val];
                } catch (Throwable $e) {
                    $testResults[] = ['var' => $var, 'ok' => false, 'value' => $e->getMessage()];
                }
            }
        }
    }
}

// Load this product's saved rules for the editor.
$rules = [];
if ($hasBuildRules && $productId > 0) {
    try {
        $rs = $pdo->prepare('SELECT variable, formula, seq FROM build_rules WHERE product_id = ? ORDER BY seq, id');
        $rs->execute([$productId]);
        $rules = $rs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fmtVal = static function ($v): string {
    if (is_float($v)) return rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
    if (is_bool($v))  return $v ? 'TRUE' : 'FALSE';
    return (string) $v;
};

$factoryTitle = 'Build Rules';
$factoryNav   = 'build';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .br-head { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:0 0 1rem; }
    .br-head h1 { font-size:1.6rem; font-weight:700; margin:0; }
    .br-sub { color:var(--text-muted,#667); margin:0 0 1.4rem; max-width:70ch; }
    .br-flash { padding:0.7rem 1rem; border-radius:10px; margin:0 0 1.2rem; font-size:0.9375rem; }
    .br-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .br-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .br-grid { display:grid; grid-template-columns:2fr 1fr; gap:1.25rem; align-items:start; }
    @media (max-width:900px){ .br-grid{ grid-template-columns:1fr; } }
    .br-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    .br-card h2 { font-size:1rem; margin:0 0 0.9rem; }
    select, input[type=text], input[type=number], textarea { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:0.45rem 0.6rem; background:var(--bg-input,#fff); color:inherit; }
    .br-rules { width:100%; border-collapse:collapse; }
    .br-rules th { text-align:left; font-size:0.7rem; letter-spacing:0.05em; text-transform:uppercase; color:var(--text-faint,#94a3b8); font-weight:600; padding:0 0.4rem 0.4rem; }
    .br-rules td { padding:0.25rem 0.4rem; vertical-align:top; }
    .br-rules input.var { width:100%; font-family:ui-monospace,Consolas,monospace; }
    .br-rules input.seq { width:3.2rem; text-align:center; }
    .br-rules textarea.formula { width:100%; min-height:2.3rem; font-family:ui-monospace,Consolas,monospace; font-size:0.85rem; resize:vertical; }
    .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.5rem 1rem; }
    .btn.primary { background:#166534; color:#fff; } .btn.primary:hover{ background:#14532d; }
    .btn.dark { background:#1f2a37; color:#fff; } .btn.dark:hover{ background:#111a24; }
    .br-actions { margin-top:0.9rem; display:flex; gap:0.6rem; }
    .br-test textarea { width:100%; min-height:6rem; font-family:ui-monospace,Consolas,monospace; font-size:0.85rem; }
    .br-res { width:100%; border-collapse:collapse; margin-top:0.9rem; font-size:0.9rem; }
    .br-res td { padding:0.35rem 0.5rem; border-bottom:1px solid var(--border,#eee); }
    .br-res .v { font-family:ui-monospace,Consolas,monospace; font-weight:600; }
    .br-res .val { text-align:right; font-variant-numeric:tabular-nums; }
    .br-res tr.err td { color:#991b1b; }
    .br-res tr.err .val { text-align:left; font-family:inherit; font-size:0.82rem; }
    .br-hint { font-size:0.8rem; color:var(--text-faint,#94a3b8); margin-top:0.5rem; line-height:1.5; }
    code { background:var(--bg-subtle,#f1f5f9); padding:0.05rem 0.3rem; border-radius:4px; font-size:0.85em; }
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
<p class="br-sub">Per-product formulas that turn an order's inputs (Width, Drop, options) into cut sizes and component counts. Evaluated top-to-bottom, so a rule can use the ones above it. Excel-style: <code>IF</code>, <code>AND</code>, <code>OR</code>, <code>ROUND</code>, <code>ROUN_UP</code>, <code>EVN</code>, <code>FIND</code>, <code>&amp;</code>.</p>

<?php if ($flashOk !== ''): ?><div class="br-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="br-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$hasBuildRules): ?>
    <div class="br-flash err">The <code>build_rules</code> table isn't there yet — run <code>/migrate_build_rules.php</code>.</div>
<?php elseif (!$products): ?>
    <div class="br-flash err">No Beverley master products found.</div>
<?php else: ?>
<div class="br-grid">
    <!-- Editor -->
    <div class="br-card">
        <h2>Rules for <?= e($productName) ?></h2>
        <form method="post" action="/factory/build-rules.php">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <table class="br-rules">
                <thead><tr><th style="width:3.5rem">Seq</th><th style="width:11rem">Variable</th><th>Formula</th></tr></thead>
                <tbody>
                    <?php
                        // Existing rules, then 3 blank rows for adding.
                        $rowsOut = $rules;
                        for ($k = 0; $k < 3; $k++) $rowsOut[] = ['seq' => count($rules) + $k, 'variable' => '', 'formula' => ''];
                        foreach ($rowsOut as $r):
                    ?>
                        <tr>
                            <td><input class="seq" type="number" name="seq[]" value="<?= (int) ($r['seq'] ?? 0) ?>"></td>
                            <td><input class="var" type="text" name="variable[]" value="<?= e((string) ($r['variable'] ?? '')) ?>" placeholder="H_Cut"></td>
                            <td><textarea class="formula" name="formula[]" placeholder="IF(ExactorRecess=&quot;Recess&quot;, Width-12, Width-2)"><?= e((string) ($r['formula'] ?? '')) ?></textarea></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="br-actions">
                <button type="submit" class="btn primary">Save rules</button>
            </div>
            <p class="br-hint">To delete a rule, clear its Variable or Formula and Save. Blank rows are ignored.</p>
        </form>
    </div>

    <!-- Test panel -->
    <div class="br-card br-test">
        <h2>Test panel</h2>
        <form method="post" action="/factory/build-rules.php?product_id=<?= $productId ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="test">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <textarea name="sample" placeholder="Width = 1200&#10;Drop = 1500&#10;ExactorRecess = Recess&#10;ControlOptions = Corded"><?= e($sample) ?></textarea>
            <div class="br-actions"><button type="submit" class="btn dark">Run test</button></div>
        </form>
        <p class="br-hint">Enter sample inputs (<code>Name = value</code>, one per line). Evaluates the <em>saved</em> rules top-to-bottom and shows each output — or the formula that errors.</p>

        <?php if ($testResults !== null): ?>
            <?php if (!$testResults): ?>
                <p class="br-hint">No rules to evaluate for this product yet.</p>
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
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
