<?php
declare(strict_types=1);

/**
 * READ-ONLY system check. Scans the build rules, options, allowance/best-fit tables
 * and worksheet templates for anomalies. Makes NO changes to any data.
 *
 * Checks, per product that has build rules:
 *   1. Every decision-table cell value has a matching active option choice / system.
 *   2. Every BESTFIT/LOOKUP table a rule references exists (for that factory).
 *   3. Every variable a formula reads is a built-in or another rule on the product.
 *   4. Every worksheet "var:" field is backed by a real build variable.
 * Global:
 *   5. Allowance/chart tables referenced by NO formula (inert duplicates).
 *   6. Best-fit tables: sizes strictly increase within each count (+ coverage max).
 *   7. Mixed spellings in stored data (Vouge / Center / "C / L" / Cord and Chain).
 *
 * Run as super-admin: /system_check.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ISSUES = 0;
$hdr = static function (string $t) { echo "\n" . str_repeat('=', 74) . "\n{$t}\n" . str_repeat('=', 74) . "\n"; };
$bad = static function (string $m) use (&$ISSUES) { $ISSUES++; echo "  [!] {$m}\n"; };
$okc = static function (string $m) { echo "  ok  {$m}\n"; };

$FUNCS = ['if','and','or','not','round','roun_up','roundup','roun_down','rounddown',
          'evn','even','find','max','min','lookup','bestfit','true','false'];

$tableRefs = static function (string $f): array {
    preg_match_all('/\b(?:BESTFIT|LOOKUP)\s*\(\s*"([^"]+)"/i', $f, $m);
    return array_values(array_unique(array_map('strtolower', $m[1])));
};
$varRefs = static function (string $f) use ($FUNCS): array {
    $noStr = preg_replace('/"[^"]*"/', ' ', $f);              // drop string literals
    preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', (string) $noStr, $m);
    $out = [];
    foreach ($m[0] as $id) {
        $l = strtolower($id);
        if (!in_array($l, $FUNCS, true) && !ctype_digit($id)) $out[$l] = $id;
    }
    return $out;                                              // lower => original
};
$colExists = static function (string $table, string $col) use ($pdo): bool {
    try { $pdo->query("SELECT `{$col}` FROM `{$table}` LIMIT 0"); return true; }
    catch (Throwable $e) { return false; }
};

$hasSrc      = $colExists('products', 'source_client_id');
$hasAlwClient = $colExists('allowance_rows', 'client_id');
$hasWsTpl     = false;
try { $pdo->query('SELECT 1 FROM worksheet_templates LIMIT 0'); $hasWsTpl = true; } catch (Throwable $e) {}

// ---- Products that have build rules -------------------------------------------
$prodSel = 'SELECT id, name, client_id' . ($hasSrc ? ', source_client_id' : '') . ' FROM products';
$products = [];
foreach ($pdo->query($prodSel) as $p) { $products[(int) $p['id']] = $p; }

$bvByProd = [];
foreach ($pdo->query('SELECT product_id, name, columns_json, rows_json FROM build_variables ORDER BY product_id, seq, id') as $r) {
    $bvByProd[(int) $r['product_id']][] = $r;
}

// Allowance tables per factory client.
$alwByClient = [];   // client => [tablename_lower => ['max'=>float,'rows'=>[key=>val]]]
$alwClientCol = $hasAlwClient;
foreach ($pdo->query('SELECT ' . ($alwClientCol ? 'client_id' : '0 AS client_id') . ', table_name, key_norm, value FROM allowance_rows') as $a) {
    $cid = (int) $a['client_id']; $t = strtolower((string) $a['table_name']);
    $alwByClient[$cid][$t]['rows'][(string) $a['key_norm']] = (float) $a['value'];
}
$factoryOf = static function (array $p) use ($hasSrc): int {
    if ($hasSrc && (int) ($p['source_client_id'] ?? 0) !== 0) return (int) $p['source_client_id'];
    return (int) $p['client_id'];
};

$allReferencedTables = [];   // client => set of table names referenced by a formula

$hdr('BUILD RULES — per product');
foreach ($bvByProd as $pid => $vars) {
    $p = $products[$pid] ?? null;
    if (!$p) continue;
    $pname = (string) $p['name'];
    echo "\n• Product {$pid}: {$pname}\n";
    $factory = $factoryOf($p);

    // Option groups + choices + systems for this product.
    $extras = [];   // lower(name) => [id, name]
    $es = $pdo->prepare('SELECT id, name FROM product_extras WHERE product_id = ?');
    $es->execute([$pid]);
    foreach ($es->fetchAll(PDO::FETCH_ASSOC) as $e) { $extras[strtolower((string) $e['name'])] = ['id' => (int) $e['id'], 'name' => (string) $e['name']]; }

    $choicesByExtra = [];   // extraId => set(lower label)
    if ($extras) {
        $ids = array_map(static fn ($e) => $e['id'], $extras);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $cs  = $pdo->prepare("SELECT product_extra_id, label FROM product_extra_choices WHERE product_extra_id IN ($in) AND active = 1");
        $cs->execute($ids);
        foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) { $choicesByExtra[(int) $c['product_extra_id']][strtolower(trim((string) $c['label']))] = true; }
    }
    $sysSet = [];
    $ss = $pdo->prepare('SELECT name FROM product_systems WHERE product_id = ?');
    $ss->execute([$pid]);
    foreach ($ss->fetchAll(PDO::FETCH_COLUMN) as $sn) { $sysSet[strtolower(trim((string) $sn))] = true; }

    $validVars = ['width' => 1, 'drop' => 1, 'fit_height' => 1, 'quantity' => 1];
    foreach ($vars as $v) { $validVars[strtolower((string) $v['name'])] = 1; }

    $seenCellMiss = []; $seenTblMiss = []; $seenVarMiss = [];
    foreach ($vars as $v) {
        $cols = json_decode((string) $v['columns_json'], true) ?: [];
        $rows = json_decode((string) $v['rows_json'], true) ?: [];
        $vname = (string) $v['name'];

        // Valid value-set per column.
        $colValid = [];   // colIndex => set(lower value) or null (any)
        foreach ($cols as $i => $col) {
            $lbl = strtolower(trim((string) ($col['label'] ?? '')));
            $ref = (string) ($col['ref'] ?? '');
            if ($lbl === 'system' || $ref === 'system') { $colValid[$i] = $sysSet; continue; }
            if (isset($extras[$lbl])) { $colValid[$i] = $choicesByExtra[$extras[$lbl]['id']] ?? []; }
            else { $colValid[$i] = null; $bad("{$pname}: rule '{$vname}' column '" . ($col['label'] ?? '?') . "' — no option group of that name on this product"); }
        }

        foreach ($rows as $row) {
            // 1. cell values must be a real choice / system.
            foreach (($row['cells'] ?? []) as $i => $cell) {
                $cell = trim((string) $cell);
                if ($cell === '') continue;
                $set = $colValid[$i] ?? null;
                if ($set === null) continue;
                if (!isset($set[strtolower($cell)])) {
                    $k = $vname . '|' . $i . '|' . $cell;
                    if (!isset($seenCellMiss[$k])) { $seenCellMiss[$k] = 1;
                        $lbl = $cols[$i]['label'] ?? ('col' . $i);
                        $bad("{$pname}: rule '{$vname}' — '{$cell}' in '{$lbl}' has no matching active choice/system");
                    }
                }
            }
            // 2 + 3. table + variable refs in the result formula.
            $res = (string) ($row['result'] ?? '');
            foreach ($tableRefs($res) as $t) {
                $allReferencedTables[$factory][$t] = true;
                if (!isset($alwByClient[$factory][$t]) && !isset($seenTblMiss[$t])) { $seenTblMiss[$t] = 1;
                    $bad("{$pname}: rule '{$vname}' uses table \"{$t}\" — not found in allowance_rows for factory {$factory}");
                }
            }
            foreach ($varRefs($res) as $lv => $orig) {
                if (!isset($validVars[$lv]) && !isset($seenVarMiss[$lv])) { $seenVarMiss[$lv] = 1;
                    $bad("{$pname}: rule '{$vname}' reads variable '{$orig}' — not a built-in or a rule on this product");
                }
            }
        }
    }
    if (!$seenCellMiss && !$seenTblMiss && !$seenVarMiss) $okc("{$pname}: rules, options, tables and variables all resolve");

    // 4. Worksheet var: fields backed by a build variable.
    if ($hasWsTpl) {
        $wt = $pdo->prepare('SELECT layout_json FROM worksheet_templates WHERE product_id = ?');
        $wt->execute([$pid]);
        $seenWs = [];
        foreach ($wt->fetchAll(PDO::FETCH_COLUMN) as $lj) {
            $lay = json_decode((string) $lj, true);
            if (!is_array($lay)) continue;
            $stack = [$lay];
            while ($stack) {
                $node = array_pop($stack);
                if (!is_array($node)) continue;
                if (isset($node['source']) && is_string($node['source']) && stripos($node['source'], 'var:') === 0) {
                    $vn = strtolower(trim(substr($node['source'], 4)));
                    if ($vn !== '' && !isset($validVars[$vn]) && !isset($seenWs[$vn])) { $seenWs[$vn] = 1;
                        $bad("{$pname}: worksheet prints var:'{$vn}' — no build variable of that name (renders blank)");
                    }
                }
                foreach ($node as $child) { if (is_array($child)) $stack[] = $child; }
            }
        }
    }
}

// ---- Global: inert allowance tables -------------------------------------------
$hdr('INERT ALLOWANCE / CHART TABLES (referenced by no formula)');
$anyInert = false;
foreach ($alwByClient as $cid => $tables) {
    foreach ($tables as $t => $_) {
        if (empty($allReferencedTables[$cid][$t])) {
            $anyInert = true;
            $bad("factory {$cid}: table \"{$t}\" is in allowance_rows but no formula references it (inert — edits do nothing)");
        }
    }
}
if (!$anyInert) $okc('every allowance/chart table is referenced by at least one rule');

// ---- Global: best-fit monotonicity + coverage ---------------------------------
$hdr('BEST-FIT CHARTS (sizes must increase within each count)');
foreach ($alwByClient as $cid => $tables) {
    foreach ($tables as $t => $data) {
        // best-fit keys look like "count|size"; skip non-best-fit tables.
        $byCount = []; $isBestfit = false; $maxWidth = 0.0;
        foreach ($data['rows'] as $k => $val) {
            if (!preg_match('/^(\d+)\|(\d+)$/', $k, $m)) continue;
            $isBestfit = true;
            $byCount[(int) $m[1]][(int) $m[2]] = (float) $val;
            $maxWidth = max($maxWidth, (float) $val);
        }
        if (!$isBestfit) continue;
        $violations = 0;
        foreach ($byCount as $count => $sizes) {
            ksort($sizes);
            $prev = null; $prevSz = null;
            foreach ($sizes as $sz => $val) {
                if ($prev !== null && $val <= $prev) { $violations++;
                    $bad("factory {$cid}: chart \"{$t}\" count {$count}: size {$sz} ({$val}) not > size {$prevSz} ({$prev})");
                }
                $prev = $val; $prevSz = $sz;
            }
        }
        if ($violations === 0) $okc("chart \"{$t}\" (factory {$cid}) monotonic; covers to {$maxWidth}mm");
    }
}

// ---- Global: spelling drift in stored data ------------------------------------
$hdr('SPELLING DRIFT IN STORED DATA');
$spellHits = 0;
$scan = [
    ['build_variables', 'rows_json'],
    ['build_variables', 'columns_json'],
    ['allowance_rows',  'keys_display'],
];
foreach ($scan as [$tbl, $col]) {
    foreach (['Vouge', 'Center ', 'C / L', 'C / R', 'Cord and Chain'] as $needle) {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$col}` LIKE ?");
            $q->execute(['%' . $needle . '%']);
            $n = (int) $q->fetchColumn();
            if ($n > 0) { $spellHits++; $bad("{$tbl}.{$col}: '{$needle}' found in {$n} row(s) — should be the canonical form"); }
        } catch (Throwable $e) { /* column absent */ }
    }
}
if ($spellHits === 0) $okc('no Vouge / Center / spaced C-slash / "Cord and Chain" in stored data');

// ---- Summary ------------------------------------------------------------------
$hdr('SUMMARY');
echo $ISSUES === 0
    ? "  No anomalies found. Everything resolves cleanly.\n"
    : "  {$ISSUES} anomal" . ($ISSUES === 1 ? 'y' : 'ies') . " flagged above — read-only, nothing was changed.\n";
