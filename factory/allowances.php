<?php
declare(strict_types=1);

/**
 * Factory · Allowances.
 *
 * Named multi-key lookup tables the build rules reference via LOOKUP(). E.g.
 * the "vertical_headrail" table holds the mm deducted from the width for the
 * headrail cut, keyed by system · control · draw · Recess/Exact — shared by
 * Vertical Blinds and Vertical Head Rail Only. Edit an allowance in one place
 * instead of duplicating a big IF.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$pdo = db();

$hasTable = false;
try { $pdo->query('SELECT 1 FROM allowance_rows LIMIT 0'); $hasTable = true; }
catch (Throwable $e) { /* not migrated */ }

// Existing table names.
$tables = [];
if ($hasTable) {
    try { $tables = $pdo->query('SELECT DISTINCT table_name FROM allowance_rows ORDER BY table_name')->fetchAll(PDO::FETCH_COLUMN); }
    catch (Throwable $e) { /* ignore */ }
}

$table = trim((string) ($_GET['table'] ?? $_POST['table_name'] ?? ''));
if ($table === '' && $tables) $table = (string) $tables[0];

$normKeys = static function (string $raw): array {
    // Split a "A · B · C" (or |, comma) keys string into trimmed parts.
    $parts = preg_split('/\s*[·|,]\s*/u', trim($raw));
    return array_values(array_filter(array_map('trim', $parts), static fn ($p) => $p !== ''));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasTable) {
    csrf_check();
    $table = trim((string) ($_POST['table_name'] ?? ''));
    if ($table !== '') {
        $keysIn = (array) ($_POST['keys'] ?? []);
        $valsIn = (array) ($_POST['value'] ?? []);
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM allowance_rows WHERE table_name = ?')->execute([$table]);
            $ins = $pdo->prepare(
                'INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq) VALUES (?, ?, ?, ?, ?)'
            );
            $n = 0;
            foreach ($keysIn as $i => $raw) {
                $keys = $normKeys((string) $raw);
                $valRaw = trim((string) ($valsIn[$i] ?? ''));
                if (!$keys || $valRaw === '' || !is_numeric($valRaw)) continue;
                $ins->execute([$table, strtolower(implode('|', $keys)), implode(' · ', $keys), (float) $valRaw, $n]);
                $n++;
            }
            $pdo->commit();
            $_SESSION['flash_success'] = "Saved {$n} allowance" . ($n === 1 ? '' : 's') . " in “{$table}”.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
        }
    }
    header('Location: /factory/allowances.php?table=' . urlencode($table));
    exit;
}

// Load rows for the selected table.
$rows = [];
if ($hasTable && $table !== '') {
    try {
        $rs = $pdo->prepare('SELECT keys_display, value FROM allowance_rows WHERE table_name = ? ORDER BY seq, id');
        $rs->execute([$table]);
        $rows = $rs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* ignore */ }
}

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$fmtNum = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

$factoryTitle = 'Allowances';
$factoryNav   = 'allowances';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .al-head { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:0 0 1rem; }
    .al-head h1 { font-size:1.6rem; font-weight:700; margin:0; }
    .al-sub { color:var(--text-muted,#667); margin:0 0 1.3rem; max-width:72ch; }
    .al-flash { padding:0.7rem 1rem; border-radius:10px; margin:0 0 1.2rem; font-size:0.9375rem; }
    .al-flash.ok  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .al-flash.err { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .al-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); max-width:820px; }
    select, input[type=text], input[type=number] { font:inherit; border:1px solid var(--border-strong,#cbd5e1); border-radius:8px; padding:0.45rem 0.6rem; background:var(--bg-input,#fff); color:inherit; }
    .al-rules { width:100%; border-collapse:collapse; }
    .al-rules th { text-align:left; font-size:0.7rem; letter-spacing:0.05em; text-transform:uppercase; color:var(--text-faint,#94a3b8); font-weight:600; padding:0 0.4rem 0.4rem; }
    .al-rules td { padding:0.25rem 0.4rem; }
    .al-rules input.keys { width:100%; }
    .al-rules input.val { width:6rem; text-align:right; font-variant-numeric:tabular-nums; }
    .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.5rem 1rem; }
    .btn.primary { background:#166534; color:#fff; } .btn.primary:hover{ background:#14532d; }
    .al-hint { font-size:0.8rem; color:var(--text-faint,#94a3b8); margin-top:0.5rem; line-height:1.5; }
    code { background:var(--bg-subtle,#f1f5f9); padding:0.05rem 0.3rem; border-radius:4px; font-size:0.85em; }
    .al-new { display:inline-flex; gap:0.4rem; align-items:center; margin-left:auto; }
</style>

<div class="al-head">
    <h1>Allowances</h1>
    <?php if ($tables): ?>
    <form method="get" action="/factory/allowances.php" style="margin:0">
        <select name="table" onchange="this.form.submit()">
            <?php foreach ($tables as $t): ?>
                <option value="<?= e((string) $t) ?>" <?= $t === $table ? 'selected' : '' ?>><?= e((string) $t) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
    <form method="get" action="/factory/allowances.php" class="al-new">
        <input type="text" name="table" placeholder="new_table_name" style="width:12rem">
        <button type="submit" class="btn primary" style="padding:0.45rem 0.8rem">New table</button>
    </form>
</div>
<p class="al-sub">Named lookup tables the build rules read via <code>LOOKUP("table", key1, key2, …)</code>. Each row is a key combination and its value; keys match in order, case-insensitively. Shared, so one edit updates every formula that uses it.</p>

<?php if ($flashOk !== ''): ?><div class="al-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="al-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$hasTable): ?>
    <div class="al-flash err">The <code>allowance_rows</code> table isn't there yet — run <code>/migrate_allowance_rows.php</code>.</div>
<?php elseif ($table === ''): ?>
    <div class="al-card">No allowance tables yet. Create one above, or run <code>/seed_vertical_allowances.php</code> to load the vertical headrail data.</div>
<?php else: ?>
<div class="al-card">
    <h2 style="margin:0 0 0.9rem;font-size:1rem"><?= e($table) ?></h2>
    <form method="post" action="/factory/allowances.php">
        <?= csrf_field() ?>
        <input type="hidden" name="table_name" value="<?= e($table) ?>">
        <table class="al-rules">
            <thead><tr><th>Keys (system · control · … · Recess/Exact)</th><th style="width:7rem">Value (mm)</th></tr></thead>
            <tbody>
                <?php
                    $rowsOut = $rows;
                    for ($k = 0; $k < 3; $k++) $rowsOut[] = ['keys_display' => '', 'value' => ''];
                    foreach ($rowsOut as $r):
                ?>
                    <tr>
                        <td><input class="keys" type="text" name="keys[]" value="<?= e((string) ($r['keys_display'] ?? '')) ?>" placeholder="Nova · Corded · Recess"></td>
                        <td><input class="val" type="number" step="any" name="value[]" value="<?= $r['value'] === '' ? '' : e($fmtNum($r['value'])) ?>"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:0.9rem"><button type="submit" class="btn primary">Save allowances</button></div>
        <p class="al-hint">To delete a row, clear its Keys and Save. Separate keys with <code>·</code> (or <code>|</code>/comma). The build rule must LOOKUP with the same keys in the same order.</p>
    </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
