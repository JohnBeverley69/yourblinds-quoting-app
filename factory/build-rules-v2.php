<?php
declare(strict_types=1);

/**
 * Factory · Build Rules (v2) — the "Cuts / Calcs / Charts" redesign.
 *
 * Reads the LIVE build_variables for a product and re-presents them so they can
 * actually be read:
 *   Cuts   — variables whose every row is "Width − N" / "Drop − N": shown as a
 *            plain options→take-off table, no formula text.
 *   Calcs  — the genuine formulas (vanes, metres, chain, cord).
 *   Charts — the Vogue/Louvolite best-fit lookup (lives inside the truck plumbing).
 *   Plumbing — intermediate values that never print (spacing, trucks, size).
 *
 * Same data the worksheet reads, so nothing here forks the compute path. This
 * pass is READ-ONLY (proving it shows the real numbers); editing/save is the
 * next step. The live "cut tester" evaluates the real cut rows client-side.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$pdo    = db();
$MASTER = function_exists('current_factory_id') ? (int) current_factory_id() : 0;

// All of this factory's own products (including ones with no rules yet, so you
// can start a product from blank right here).
$products = [];
try {
    $ps = $pdo->prepare(
        "SELECT id, name FROM products
          WHERE client_id = ?
          ORDER BY name"
    );
    $ps->execute([$MASTER]);
    $products = $ps->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* handled in view */ }

// Default to Vertical Blinds if present, else first product.
$productId = (int) ($_GET['product_id'] ?? 0);
if ($productId === 0) {
    foreach ($products as $p) { if ($p['name'] === 'Bev Vertical Blinds') { $productId = (int) $p['id']; break; } }
    if ($productId === 0 && $products) $productId = (int) $products[0]['id'];
}
$productName = '';
foreach ($products as $p) { if ((int) $p['id'] === $productId) $productName = (string) $p['name']; }

/** Load this product's build variables in evaluation order. */
$vars = [];
if ($productId > 0) {
    try {
        $vs = $pdo->prepare(
            'SELECT name, columns_json, rows_json, seq
               FROM build_variables WHERE product_id = ? ORDER BY seq, id'
        );
        $vs->execute([$productId]);
        $vars = $vs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* table missing / not migrated */ }
}

// Allowance tables (for cuts stored as Base + LOOKUP("table", keys...)): resolve
// the looked-up value so those cuts show as a plain editable number too.
$alw = [];
try {
    $ars = $pdo->query('SELECT table_name, key_norm, value FROM allowance_rows');
    foreach ($ars->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $alw[strtolower((string) $a['table_name'])][(string) $a['key_norm']] = (float) $a['value'];
    }
} catch (Throwable $e) { /* no allowance_rows */ }

// Option sources for this product (what a new cut can be driven by): the System
// axis plus each option group and its distinct choice labels. Same shape the old
// editor uses. Feeds the "depends on" picker in the add-cut form.
$optionSources = [];
if ($productId > 0) {
    try {
        $ss = $pdo->prepare("SELECT name FROM product_systems WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, name");
        $ss->execute([$productId, $MASTER]);
        $systems = $ss->fetchAll(PDO::FETCH_COLUMN);
        if ($systems) $optionSources[] = ['ref' => 'system', 'label' => 'System', 'values' => array_values($systems)];
    } catch (Throwable $e) { /* none */ }
    try {
        $gs = $pdo->prepare("SELECT id, name FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1 ORDER BY sort_order, name");
        $gs->execute([$productId, $MASTER]);
        $extraRows = $gs->fetchAll(PDO::FETCH_ASSOC);
        if ($extraRows) {
            $ids = array_map(static fn ($r) => (int) $r['id'], $extraRows);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $cs  = $pdo->prepare("SELECT product_extra_id, label FROM product_extra_choices WHERE product_extra_id IN ($in) AND active = 1 ORDER BY product_extra_id, sort_order, label");
            $cs->execute($ids);
            $byExtra = [];
            foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) { $byExtra[(int) $c['product_extra_id']][(string) $c['label']] = true; }
            foreach ($extraRows as $er) {
                $eid = (int) $er['id'];
                $optionSources[] = ['ref' => 'extra:' . $eid, 'label' => (string) $er['name'], 'values' => array_keys($byExtra[$eid] ?? [])];
            }
        }
    } catch (Throwable $e) { /* none */ }
}
$sourceByRef = [];
foreach ($optionSources as $os) $sourceByRef[$os['ref']] = $os;

// ---- Formula help: valid names + a validator that mirrors the real engine ----
// The names a formula on THIS product may reference: the built-ins plus every
// rule on the product. Powers the clickable chips AND the "Check" validation.
require_once __DIR__ . '/../_partials/build_eval.php';   // be_norm_math + formula_eval (via formula_engine)
$validNames = ['Width', 'Drop', 'Fit_height', 'Quantity'];
foreach ($vars as $v) $validNames[] = (string) $v['name'];
$validNames = array_values(array_unique(array_filter($validNames)));

$checkFormula = static function (string $formula) use ($validNames, $alw): array {
    $formula = trim($formula);
    if ($formula === '') return ['ok' => false, 'error' => 'The formula is empty.'];
    $pool = [];
    foreach ($validNames as $n) $pool[$n] = 10.0;                 // sample values
    $pool['Width'] = 1200.0; $pool['Drop'] = 1500.0; $pool['Quantity'] = 10.0; $pool['Fit_height'] = 0.0;
    try {
        $r = formula_eval(be_norm_math($formula), $pool, $alw);
        return ['ok' => true, 'result' => is_numeric($r) ? (float) $r : (string) $r];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $suggest = '';
        if (preg_match('/variable[:\s]+([A-Za-z_][A-Za-z0-9_]*)/i', $msg, $m)) {
            $bad = $m[1]; $bestD = 99;
            foreach ($validNames as $n) { $d = levenshtein(strtolower($bad), strtolower($n)); if ($d < $bestD) { $bestD = $d; $suggest = $n; } }
            if ($bestD < 1 || $bestD > 3) $suggest = '';   // only suggest a genuinely-close name
        }
        return ['ok' => false, 'error' => $msg, 'suggest' => $suggest];
    }
};

// Live "Check" endpoint for the formula helper (GET, returns JSON).
if (($_GET['action'] ?? '') === 'checkcalc') {
    header('Content-Type: application/json');
    echo json_encode($checkFormula((string) ($_GET['formula'] ?? '')));
    exit;
}

// Friendly names for the cryptic variable codes.
$FRIENDLY = [
    'H_Cut' => 'Headrail cut', 'Hem_To_Hem' => 'Fabric drop', 'Vanes' => 'Vanes',
    'Mtrs' => 'Fabric metres', 'CH_L' => 'Tilt chain', 'C_L' => 'Draw cord',
    'Trucks' => 'Trucks', 'Truck_Size' => 'Truck size', 'Truck_Spec' => 'Truck spec',
    'Spacing' => 'Truck spacing', 'Truck_Spacing' => 'Truck spacing',
];
// Variables that are internal plumbing (never printed on a ticket).
$PLUMBING = ['Spacing', 'Truck_Spacing', 'Trucks', 'Truck_Size', 'Truck_Spec'];
// A little plain-English gloss for the known calcs (fallbacks to raw rows).
$CALC_GLOSS = [
    'Vanes' => 'number of trucks + 1',
    'Mtrs'  => 'round up  (Drop + 95) × Vanes ÷ 1000',
];

/** Parse a result expression as a cut: "Width − N" / "Drop + N" / bare "Width". */
$parseCut = static function (string $result): ?array {
    $r = trim($result);
    if (!preg_match('/^(Width|Drop|W_C|D_C)\s*(?:([+\-])\s*([0-9]+(?:\.[0-9]+)?))?$/i', $r, $m)) {
        return null;
    }
    $base = strtoupper($m[1][0]) === 'W' || stripos($m[1], 'Width') === 0 ? 'Width' : 'Drop';
    if (strcasecmp($m[1], 'W_C') === 0) $base = 'Width';
    if (strcasecmp($m[1], 'D_C') === 0) $base = 'Drop';
    $sign = ($m[2] ?? '') === '-' ? -1 : 1;
    $n    = isset($m[3]) && $m[3] !== '' ? (float) $m[3] : 0.0;
    return ['base' => $base, 'sign' => $sign, 'n' => $n];
};

// ---- Save handler: write edited take-offs back into build_variables --------
// Only the numeric take-off in each cut row changes; cells/order are preserved.
// The worksheet reads these same rows, so a save drives the real ticket.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    // ---- Delete a rule --------------------------------------------------------
    if (($delName = trim((string) ($_POST['deletevar'] ?? ''))) !== '') {
        try {
            $pdo->prepare('DELETE FROM build_variables WHERE product_id = ? AND name = ?')->execute([$productId, $delName]);
            $_SESSION['flash_success'] = "Removed “{$delName}”.";
        } catch (Throwable $e) { $_SESSION['flash_error'] = 'Could not remove: ' . $e->getMessage(); }
        header('Location: /factory/build-rules-v2.php?product_id=' . $productId); exit;
    }

    // ---- Create a brand-new rule (cut or calc) --------------------------------
    if ($action === 'addcut' || $action === 'addcalc') {
        $redirect = '/factory/build-rules-v2.php?product_id=' . $productId;
        // Names are code, not labels: no spaces, letters/digits/underscore only.
        $name = preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', trim((string) ($_POST['newname'] ?? ''))));
        if ($name === '') { $_SESSION['flash_error'] = 'Give the new rule a name (letters, digits, underscores).'; header("Location: $redirect"); exit; }

        // Numbers from the form are always positive; the direction says which way.
        $dir = ($_POST['dir'] ?? 'deduct') === 'add' ? 'add' : 'deduct';
        $mk = static function ($amount, string $base) use ($dir): string {
            // The direction sets the sign — ignore any sign the user typed.
            $adj = $dir === 'add' ? abs((float) $amount) : -abs((float) $amount);
            if (abs($adj) < 1e-9) return $base;
            $n = rtrim(rtrim(number_format(abs($adj), 3, '.', ''), '0'), '.');
            return $adj < 0 ? "$base - $n" : "$base + $n";
        };

        $columns = []; $rows = []; $err = null;
        if ($action === 'addcalc') {
            $formula = trim((string) ($_POST['formula'] ?? ''));
            if ($formula === '') {
                $err = 'Enter a formula for the calc.';
            } else {
                $chk = $checkFormula($formula);
                if (!$chk['ok']) $err = "That formula won't run: " . $chk['error'] . (!empty($chk['suggest']) ? " — did you mean “{$chk['suggest']}”?" : '');
                else $rows = [['cells' => [], 'result' => $formula]];
            }
        } else { // addcut
            $base    = ($_POST['base'] ?? 'Width') === 'Drop' ? 'Drop' : 'Width';
            $depends = (string) ($_POST['depends'] ?? '');
            $vals    = (array) ($_POST['cutval'] ?? []);
            if ($depends === '' || !isset($sourceByRef[$depends])) {
                $flat = $vals['__flat__'] ?? '';
                if (trim((string) $flat) === '' || !is_numeric($flat)) $err = 'Enter a number (mm).';
                else $rows = [['cells' => [], 'result' => $mk($flat, $base)]];
            } else {
                $src = $sourceByRef[$depends];
                $columns = [['label' => $src['label'], 'ref' => $src['ref']]];
                foreach ($src['values'] as $v) {
                    $t = $vals[$v] ?? '';
                    if (trim((string) $t) === '' || !is_numeric($t)) continue;
                    $rows[] = ['cells' => [$v], 'result' => $mk($t, $base)];
                }
                if (!$rows) $err = 'Enter a number for at least one option.';
            }
        }
        if ($err !== null) { $_SESSION['flash_error'] = $err; header("Location: $redirect"); exit; }

        try {
            $seqStmt = $pdo->prepare('SELECT COALESCE(MAX(seq), -1) + 1 FROM build_variables WHERE product_id = ?');
            $seqStmt->execute([$productId]);
            $seq = (int) $seqStmt->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO build_variables (product_id, name, seq, columns_json, rows_json) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$productId, $name, $seq, json_encode($columns, JSON_UNESCAPED_UNICODE), json_encode($rows, JSON_UNESCAPED_UNICODE)]);
            $_SESSION['flash_success'] = "Added “{$name}”. Edit it below any time.";
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = "Could not add “{$name}” — a rule with that name may already exist on this product.";
        }
        header("Location: $redirect"); exit;
    }

    $edits = (array) ($_POST['cut'] ?? []);   // [ varName => [ rowIdx => takeoff ] ]
    $fmtN = static fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    // $adj is the actual change to the measurement: negative shrinks, positive grows.
    $buildResult = static function (string $base, float $adj) use ($fmtN): string {
        if (abs($adj) < 1e-9) return $base;
        return $adj < 0 ? $base . ' - ' . $fmtN(abs($adj))
                        : $base . ' + ' . $fmtN($adj);
    };
    try {
        $pdo->beginTransaction();
        $sel = $pdo->prepare('SELECT rows_json FROM build_variables WHERE product_id = ? AND name = ?');
        $upd = $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE product_id = ? AND name = ?');
        $alwUpd = $pdo->prepare('UPDATE allowance_rows SET value = ? WHERE LOWER(table_name) = ? AND key_norm = ?');
        foreach ($edits as $vname => $rowTakes) {
            $vname = (string) $vname;
            // Each cut declares its direction: deduct (positive shrinks), add
            // (positive grows), or mixed (the number is the signed change itself).
            $dirRaw = (string) ($_POST['cutdir'][$vname] ?? 'deduct');
            $dir = in_array($dirRaw, ['add', 'mixed'], true) ? $dirRaw : 'deduct';
            $sel->execute([$productId, $vname]);
            $rj   = $sel->fetchColumn();
            $rows = ($rj !== false) ? (json_decode((string) $rj, true) ?: []) : [];
            $varBase = null;   // base (Width/Drop) for any brand-new row, from an existing row
            foreach ($rows as $rr) { $pp = $parseCut((string) ($rr['result'] ?? '')); if ($pp) { $varBase = $pp['base']; break; } }
            $dirty = false;
            foreach ((array) $rowTakes as $targetKey => $valRaw) {
                if (trim((string) $valRaw) === '' || !is_numeric($valRaw)) continue;
                // deduct/add: the heading sets the sign, so ignore any sign typed.
                // mixed: the number is the signed change itself, so keep it.
                $adj = $dir === 'mixed' ? (float) $valRaw
                     : ($dir === 'add' ? abs((float) $valRaw) : -abs((float) $valRaw));
                // A grid cell may stand for several targets (Centre = L+R, No Thrills
                // folded in, or several fascias sharing one allowance key).
                foreach (explode(',', (string) $targetKey) as $tok) {
                    if (strncmp($tok, 'bv:', 3) === 0) {
                        $ri = (int) substr($tok, 3);
                        if (!isset($rows[$ri])) continue;
                        $p = $parseCut((string) ($rows[$ri]['result'] ?? ''));
                        if ($p === null) continue;
                        $rows[$ri]['result'] = $buildResult($p['base'], $adj);
                        $dirty = true;
                    } elseif (strncmp($tok, 'alw:', 4) === 0) {
                        $rest = explode(':', substr($tok, 4), 2);
                        if (count($rest) < 2) continue;
                        // Base + LOOKUP(value): the stored allowance IS the signed change.
                        $alwUpd->execute([$adj, strtolower($rest[0]), $rest[1]]);
                    } elseif (strncmp($tok, 'new:', 4) === 0) {
                        // Brand-new row for a previously-rowless option choice.
                        if ($varBase === null) continue;
                        $rows[] = ['cells' => [substr($tok, 4)], 'result' => $buildResult($varBase, $adj)];
                        $dirty = true;
                    }
                }
            }
            if ($dirty) $upd->execute([json_encode($rows, JSON_UNESCAPED_UNICODE), $productId, $vname]);
        }
        // Calcs: an edited formula string per row. A formula that won't run is
        // left unchanged (not saved), so a typo can't silently break a ticket.
        $badCalcs = [];
        foreach ((array) ($_POST['calc'] ?? []) as $vname => $rowsMap) {
            $vname = (string) $vname;
            $sel->execute([$productId, $vname]);
            $rj = $sel->fetchColumn();
            if ($rj === false) continue;
            $rows = json_decode((string) $rj, true);
            if (!is_array($rows)) continue;
            $changed = false;
            foreach ((array) $rowsMap as $ri => $formula) {
                $ri = (int) $ri;
                if (!isset($rows[$ri])) continue;
                $f = trim((string) $formula);
                if ($f === '') continue;
                if (!$checkFormula($f)['ok']) { $badCalcs[] = $vname; continue; }
                $rows[$ri]['result'] = $f; $changed = true;
            }
            if ($changed) $upd->execute([json_encode($rows, JSON_UNESCAPED_UNICODE), $productId, $vname]);
        }
        $pdo->commit();
        if (!empty($badCalcs)) {
            $_SESSION['flash_error'] = 'Saved — but these formulas had an error and were left as they were: '
                . implode(', ', array_values(array_unique($badCalcs))) . '. Fix the formula and save again.';
        } else {
            $_SESSION['flash_success'] = 'Saved — the worksheet now uses these numbers.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /factory/build-rules-v2.php?product_id=' . $productId);
    exit;
}

// Sort each variable into a bucket and pre-compute a tidy shape.
$cuts = []; $calcs = []; $charts = []; $plumbing = [];
foreach ($vars as $v) {
    $name    = (string) $v['name'];
    $cols    = json_decode((string) $v['columns_json'], true) ?: [];
    $rows    = json_decode((string) $v['rows_json'], true) ?: [];
    $labels  = array_map(static fn ($c) => (string) ($c['label'] ?? ($c['ref'] ?? '')), $cols);
    $colrefs = array_map(static fn ($c) => (string) ($c['ref'] ?? ''), $cols);
    $friendly = $FRIENDLY[$name] ?? $name;

    $rawResults = array_map(static fn ($r) => (string) ($r['result'] ?? ''), $rows);
    $hasBestfit = false;
    foreach ($rawResults as $rr) { if (stripos($rr, 'BESTFIT') !== false) { $hasBestfit = true; break; } }

    // CUT: every row is Base ± N (inline, stored in build_variables) OR
    // Base + LOOKUP("table", keys…) (the take-off lives in an allowance table).
    // Each row carries its edit target: 'bv:<rowIdx>' or 'alw:<table>:<keynorm>'.
    $cutRows = []; $base = null; $isCut = !empty($rows);
    foreach ($rows as $ri => $r) {
        $res   = trim((string) ($r['result'] ?? ''));
        $cells = array_map('strval', (array) ($r['cells'] ?? []));
        $p = $parseCut($res);
        if ($p !== null) {
            $b = $p['base'];
            $take = $p['sign'] < 0 ? $p['n'] : -$p['n'];
            $target = 'bv:' . $ri;
        } elseif (preg_match('/^(Width|Drop)\s*\+\s*LOOKUP\(\s*"([^"]+)"\s*,\s*(.+)\)$/i', $res, $m)) {
            $b   = strcasecmp($m[1], 'Drop') === 0 ? 'Drop' : 'Width';
            $tbl = $m[2];
            preg_match_all('/"([^"]*)"/', $m[3], $km);
            $kn  = strtolower(implode('|', array_map('trim', $km[1])));
            $val = $alw[strtolower($tbl)][$kn] ?? null;
            if ($val === null) { $isCut = false; break; }   // can't resolve → treat as a formula
            $take   = -$val;
            $target = 'alw:' . $tbl . ':' . $kn;
        } else { $isCut = false; break; }
        if ($base === null) $base = $b;
        elseif ($b !== $base) { $isCut = false; break; }
        $cutRows[] = ['cells' => $cells, 'take' => $take, 'target' => $target];
    }

    if ($isCut && $base !== null) {
        // Hide columns that are blank in every row.
        $active = [];
        foreach ($labels as $i => $lab) {
            foreach ($cutRows as $cr) { if (($cr['cells'][$i] ?? '') !== '') { $active[] = $i; break; } }
        }
        $cuts[] = ['name' => $name, 'friendly' => $friendly, 'base' => $base,
                   'labels' => $labels, 'colrefs' => $colrefs, 'active' => $active, 'rows' => $cutRows];
    } elseif (in_array($name, $PLUMBING, true)) {
        $plumbing[] = ['name' => $name, 'friendly' => $friendly, 'results' => $rawResults, 'bestfit' => $hasBestfit];
    } else {
        $calcs[] = ['name' => $name, 'friendly' => $friendly, 'gloss' => $CALC_GLOSS[$name] ?? '',
                    'rows' => $rows, 'labels' => $labels];
    }
}
// A single conceptual "chart" card if any plumbing uses best-fit.
$usesChart = false; foreach ($plumbing as $pl) { if ($pl['bestfit']) { $usesChart = true; break; } }

$flashOk  = (string) ($_SESSION['flash_success'] ?? '');
$flashErr = (string) ($_SESSION['flash_error']   ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---- Fold each cut into a readable grid ------------------------------------
// Pivot the Recess/Exact "basis" column into value columns; group the remaining
// rows, aliasing Center Left/Right -> "Centre" and No Thrills -> its parent
// system, so ~24 raw rows read as ~8. Each grid cell keeps the comma-joined list
// of underlying row indices it represents, so one edit fans back to them all.
$SYSTEM_PARENT = ['no thrills' => 'SlimLine', 'no frills' => 'SlimLine'];
$BASIS_SET     = ['recess', 'exact', 'cloth size', 'cloth'];
$BASIS_ORDER   = ['recess' => 0, 'exact' => 1, 'cloth size' => 2, 'cloth' => 3];

$cutsGrid = [];
foreach ($cuts as $c) {
    $labels = $c['labels']; $active = $c['active'];
    $basisIdx = null; $sysIdx = null; $wandIdx = null; $ctlIdx = null;
    foreach ($active as $i) {
        $lab = strtolower($labels[$i] ?? '');
        $vals = [];
        foreach ($c['rows'] as $r) { $v = strtolower(trim((string) ($r['cells'][$i] ?? ''))); if ($v !== '') $vals[$v] = true; }
        if ($basisIdx === null && $vals && !array_diff(array_keys($vals), $BASIS_SET)) { $basisIdx = $i; continue; }
        if (strpos($lab, 'system')  !== false) $sysIdx  = $i;
        if (strpos($lab, 'wand')    !== false) $wandIdx = $i;
        if (strpos($lab, 'control') !== false) $ctlIdx  = $i;
    }
    $keyCols = array_values(array_filter($active, static fn ($i) => $i !== $basisIdx));

    $groups = []; $basisSeen = [];
    foreach ($c['rows'] as $ri => $r) {
        $basisRaw = $basisIdx !== null ? trim((string) ($r['cells'][$basisIdx] ?? '')) : '';
        $basisKey = strtolower($basisRaw);
        $basisSeen[$basisKey] = $basisRaw;
        $keyParts = [];
        foreach ($keyCols as $i) {
            $val = trim((string) ($r['cells'][$i] ?? ''));
            if ($i === $sysIdx)  $val = $SYSTEM_PARENT[strtolower($val)] ?? $val;
            if ($i === $wandIdx) { $lv = strtolower($val); if ($lv === 'center left' || $lv === 'center right') $val = 'Centre'; }
            $keyParts[$i] = $val;
        }
        $gk = implode('|', array_map('strtolower', $keyParts));
        if (!isset($groups[$gk])) $groups[$gk] = ['keyParts' => $keyParts, 'cells' => []];
        if (!isset($groups[$gk]['cells'][$basisKey])) $groups[$gk]['cells'][$basisKey] = ['take' => $r['take'], 'idx' => []];
        $groups[$gk]['cells'][$basisKey]['idx'][] = $r['target'];
    }

    $basisKeys = array_keys($basisSeen);
    usort($basisKeys, static function ($a, $b) use ($BASIS_ORDER) {
        $oa = $BASIS_ORDER[$a] ?? ($a === '' ? 99 : 50);
        $ob = $BASIS_ORDER[$b] ?? ($b === '' ? 99 : 50);
        return $oa <=> $ob ?: strcmp($a, $b);
    });

    // Merge groups whose cells resolve to the SAME target(s) per basis — e.g.
    // several fascias sharing one allowance key. Otherwise they'd render as
    // separate inputs with the same name and collide on submit. Their differing
    // key-column labels are combined with " / ".
    $merged = [];
    foreach ($groups as $g) {
        $sig = [];
        foreach ($basisKeys as $bk) {
            if (isset($g['cells'][$bk])) { $t = array_unique($g['cells'][$bk]['idx']); sort($t); $sig[] = $bk . '=' . implode('+', $t); }
        }
        $sigKey = implode(';', $sig);
        if (!isset($merged[$sigKey])) {
            $merged[$sigKey] = ['keySets' => [], 'cells' => []];
            foreach ($keyCols as $i) $merged[$sigKey]['keySets'][$i] = [];
        }
        foreach ($keyCols as $i) $merged[$sigKey]['keySets'][$i][(string) ($g['keyParts'][$i] ?? '')] = true;
        foreach ($g['cells'] as $bk => $cell) {
            if (!isset($merged[$sigKey]['cells'][$bk])) $merged[$sigKey]['cells'][$bk] = $cell;
            else $merged[$sigKey]['cells'][$bk]['idx'] = array_merge($merged[$sigKey]['cells'][$bk]['idx'], $cell['idx']);
        }
    }

    $gridRows = [];
    foreach ($merged as $g) {
        $disp = [];
        foreach ($keyCols as $i) {
            $rendered = [];
            foreach (array_keys($g['keySets'][$i]) as $val) {
                if ($i === $wandIdx && $val === '') {
                    $ctlVals = $ctlIdx !== null ? array_map('strtolower', array_keys($g['keySets'][$ctlIdx])) : [];
                    $isWand = false; foreach ($ctlVals as $cv) { if (strpos($cv, 'wand') !== false) $isWand = true; }
                    $rendered[] = $isWand ? 'Stack' : '—';
                } else {
                    $rendered[] = $val === '' ? '—' : $val;
                }
            }
            $disp[$i] = implode(' / ', array_values(array_unique($rendered)));
        }
        $cells = [];
        foreach ($basisKeys as $bk) {
            $cells[$bk] = isset($g['cells'][$bk])
                ? ['take' => $g['cells'][$bk]['take'], 'idxKey' => implode(',', array_values(array_unique($g['cells'][$bk]['idx'])))]
                : null;
        }
        $gridRows[] = ['disp' => $disp, 'cells' => $cells];
    }

    // Direction: does this cut take off (shrinks), add on (grows), or both (mixed)?
    $hasPos = false; $hasNeg = false;
    foreach ($c['rows'] as $rr) { if ($rr['take'] > 1e-9) $hasPos = true; elseif ($rr['take'] < -1e-9) $hasNeg = true; }
    $dir = ($hasPos && $hasNeg) ? 'mixed' : ($hasNeg ? 'add' : 'deduct');

    // Auto-append a blank, fillable row for any option choice with no row yet
    // (single-option-column cuts only), so newly-added choices appear ready to fill.
    if (count($keyCols) === 1 && $basisKeys === ['']) {
        $kc  = $keyCols[0];
        $ref = $c['colrefs'][$kc] ?? '';
        if ($ref !== '' && isset($sourceByRef[$ref])) {
            $present = [];
            foreach ($gridRows as $g2) { $present[strtolower((string) ($g2['disp'][$kc] ?? ''))] = true; }
            foreach ($sourceByRef[$ref]['values'] as $choice) {
                if (isset($present[strtolower((string) $choice)])) continue;
                $disp = []; foreach ($keyCols as $ci) $disp[$ci] = ($ci === $kc) ? (string) $choice : '—';
                $gridRows[] = ['disp' => $disp, 'cells' => ['' => ['take' => null, 'idxKey' => 'new:' . $choice]]];
            }
        }
    }

    $cutsGrid[] = [
        'name'      => $c['name'], 'friendly' => $c['friendly'], 'base' => $c['base'], 'dir' => $dir,
        'keyCols'   => $keyCols,
        'keyLabels' => array_map(static fn ($i) => $labels[$i] ?? '', $keyCols),
        'basisKeys' => $basisKeys,
        'basisDisp' => array_map(static fn ($k) => $k === '' ? '— any —' : $basisSeen[$k], $basisKeys),
        'rows'      => $gridRows,
    ];
}

$factoryTitle = 'Build rules';
$factoryNav   = 'build';
require __DIR__ . '/../_partials/factory_head.php';

$e2 = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<style>
  .brv2{ --ink:#1f2a37; --soft:#55616d; --faint:#8592a0; --line:#e4e9ef; --line-2:#eef2f6;
         --surface:#fff; --panel:#f6f8fb; --accent:#0284c7; --accent-ink:#075985; --accent-wash:#e0f2fe;
         --num:#b45309; --keep:#16794c; --keep-wash:#e4f3ec; color:var(--ink); }
  .brv2 h1{ font-size:1.6rem; letter-spacing:-.02em; margin:0 0 .3rem; }
  .brv2 .sub{ color:var(--soft); margin:0 0 .2rem; max-width:64ch; }
  .brv2 .preview-flag{ display:inline-flex; align-items:center; gap:.4rem; background:var(--accent-wash);
      color:var(--accent-ink); font-size:.78rem; font-weight:600; padding:.2rem .6rem; border-radius:999px;
      border:1px solid #bae6fd; margin-bottom:.9rem; }
  .brv2 .topline{ display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:1.1rem 0 1.6rem; }
  .brv2 select{ font:inherit; padding:.45rem .7rem; border:1px solid var(--line); border-radius:8px;
      background:var(--surface); color:var(--ink); font-weight:600; }
  .brv2 .layout{ display:grid; grid-template-columns:1fr 320px; gap:1.4rem; align-items:start; }
  @media(max-width:900px){ .brv2 .layout{ grid-template-columns:1fr; } }

  .brv2 .grouplabel{ display:flex; align-items:center; gap:.6rem; margin:1.6rem 0 .8rem; font-size:.72rem;
      letter-spacing:.13em; text-transform:uppercase; color:var(--faint); font-weight:600; }
  .brv2 .grouplabel:first-of-type{ margin-top:0; }
  .brv2 .grouplabel .ln{ flex:1; height:1px; background:var(--line); }
  .brv2 .badge{ font-size:.66rem; font-weight:600; padding:.08rem .5rem; border-radius:5px; text-transform:none; }
  .brv2 .badge.prints{ background:var(--keep-wash); color:var(--keep); }
  .brv2 .badge.hidden{ background:#eef2f6; color:var(--faint); }

  .brv2 .cut{ background:var(--surface); border:1px solid var(--line); border-radius:11px; padding:1rem 1.1rem; margin-bottom:.8rem; }
  .brv2 .cut-top{ display:flex; align-items:baseline; gap:.6rem; flex-wrap:wrap; }
  .brv2 .cut-name{ font-weight:700; font-size:1.05rem; }
  .brv2 .cut-def{ font-family:ui-monospace,Menlo,monospace; font-size:.85rem; color:var(--soft); }
  .brv2 .cut-def .m{ color:var(--accent); font-weight:600; } .brv2 .cut-def .n{ color:var(--num); font-weight:600; }
  .brv2 .code-name{ font-family:ui-monospace,Menlo,monospace; font-size:.72rem; color:var(--faint); }
  .brv2 .dirtag{ font-size:.62rem; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:.1rem .45rem; border-radius:5px; }
  .brv2 .dirtag.off{ background:var(--accent-wash); color:var(--accent-ink); }
  .brv2 .dirtag.add{ background:var(--keep-wash); color:var(--keep); }
  .brv2 .dirtag.mix{ background:var(--panel); color:var(--soft); }
  .brv2 .rmbtn{ margin-left:auto; font:inherit; font-size:.7rem; font-weight:600; color:var(--faint);
      background:none; border:1px solid var(--line); border-radius:6px; padding:.12rem .5rem; cursor:pointer; }
  .brv2 .rmbtn:hover{ color:#c0392b; border-color:#e2a3a0; }
  .brv2 .calcedit-head{ display:flex; align-items:center; gap:.45rem; }
  .brv2 table{ width:100%; border-collapse:collapse; margin-top:.7rem; font-size:.9rem; }
  .brv2 th{ text-align:left; font-size:.68rem; letter-spacing:.05em; text-transform:uppercase; color:var(--faint);
      font-weight:700; padding:.35rem .6rem; border-bottom:1px solid var(--line); }
  .brv2 td{ padding:.34rem .6rem; border-bottom:1px solid var(--line-2); color:var(--soft); }
  .brv2 tr:last-child td{ border-bottom:none; }
  .brv2 td.num{ font-family:ui-monospace,Menlo,monospace; color:var(--num); font-weight:600; text-align:right;
      font-variant-numeric:tabular-nums; }
  .brv2 th.r{ text-align:right; }
  .brv2 .scroll{ overflow-x:auto; }

  .brv2 .calc{ display:flex; gap:.9rem; align-items:baseline; padding:.5rem 0; border-bottom:1px solid var(--line-2); flex-wrap:wrap; }
  .brv2 .calc:last-child{ border-bottom:none; }
  .brv2 .calc .cn{ font-weight:700; font-size:.95rem; min-width:8rem; flex-shrink:0; }
  .brv2 .calc .cf{ font-family:ui-monospace,Menlo,monospace; font-size:.83rem; color:var(--soft); }

  .brv2 .chartcard{ background:var(--surface); border:1px solid var(--line); border-radius:11px; padding:1rem 1.1rem;
      display:flex; gap:.9rem; align-items:flex-start; }
  .brv2 .chartcard .ic{ font-size:1.3rem; line-height:1; }
  .brv2 .chartcard .cn{ font-weight:700; }
  .brv2 .chartcard p{ margin:.2rem 0 0; font-size:.86rem; color:var(--soft); }
  .brv2 details.working{ background:var(--panel); border:1px dashed var(--line); border-radius:11px; padding:.6rem 1rem; }
  .brv2 details.working summary{ cursor:pointer; font-size:.9rem; color:var(--soft); font-weight:600; }
  .brv2 details.working .pl{ font-size:.85rem; color:var(--soft); margin:.6rem 0 0; }
  .brv2 details.working code{ font-family:ui-monospace,Menlo,monospace; font-size:.8rem; }

  .brv2 .tester{ position:sticky; top:72px; background:var(--surface); border:1px solid var(--line);
      border-radius:12px; padding:1.1rem 1.15rem; box-shadow:0 8px 24px -18px rgba(20,30,40,.35); }
  .brv2 .tester h3{ margin:0 0 .2rem; font-size:1rem; }
  .brv2 .tester .hint{ font-size:.8rem; color:var(--soft); margin:0 0 .9rem; }
  .brv2 .field{ margin-bottom:.7rem; }
  .brv2 .field label{ display:block; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em;
      color:var(--faint); font-weight:700; margin-bottom:.2rem; }
  .brv2 .field input, .brv2 .field select{ width:100%; padding:.4rem .55rem; border:1px solid var(--line);
      border-radius:7px; font:inherit; background:var(--surface); color:var(--ink); }
  .brv2 .row2{ display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
  .brv2 .out{ margin-top:1rem; border-top:1px solid var(--line); padding-top:.9rem; display:grid; gap:.55rem; }
  .brv2 .out .o{ display:flex; justify-content:space-between; align-items:baseline; gap:.6rem; }
  .brv2 .out .ol{ font-size:.88rem; color:var(--soft); }
  .brv2 .out .ov{ font-family:ui-monospace,Menlo,monospace; font-weight:700; font-size:1.1rem;
      color:var(--keep); font-variant-numeric:tabular-nums; text-align:right; }
  .brv2 .out .ov small{ font-weight:500; color:var(--faint); font-size:.68rem; }
  .brv2 .empty{ background:var(--panel); border:1px dashed var(--line); border-radius:11px; padding:1rem 1.1rem; color:var(--soft); }

  .brv2 input.take{ width:5rem; text-align:right; font-family:ui-monospace,Menlo,monospace; font-weight:600;
      color:var(--num); border:1px solid var(--line); border-radius:6px; padding:.22rem .4rem;
      background:var(--surface); font-variant-numeric:tabular-nums; }
  .brv2 input.take:focus{ outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent); }
  .brv2 input.take.newrow{ border-style:dashed; border-color:var(--accent); }
  .brv2 .saverow{ display:flex; align-items:center; gap:.9rem; margin:.2rem 0 .4rem; flex-wrap:wrap; }
  .brv2 .savebtn{ font:inherit; font-weight:700; cursor:pointer; border:none; border-radius:8px;
      padding:.5rem 1.15rem; background:var(--accent); color:#fff; }
  .brv2 .savebtn:hover{ background:var(--accent-ink); }
  .brv2 .saverow span{ font-size:.83rem; color:var(--soft); }
  .brv2 .flash{ padding:.6rem .9rem; border-radius:9px; margin:.2rem 0 .4rem; font-size:.9rem; font-weight:600; }
  .brv2 .flash.ok{ background:var(--keep-wash); color:var(--keep); border:1px solid color-mix(in srgb,var(--keep) 30%,transparent); }
  .brv2 .flash.err{ background:#fdecec; color:#b03b3b; border:1px solid #f2b8b8; }
  .brv2 .calcedit{ padding:.55rem 0; border-bottom:1px solid var(--line-2); }
  .brv2 .calcedit:last-child{ border-bottom:none; }
  .brv2 .calcedit-head{ margin-bottom:.35rem; }
  .brv2 .calcrow{ display:flex; align-items:center; gap:.6rem; margin:.25rem 0; flex-wrap:wrap; }
  .brv2 .calcwhen{ font-size:.72rem; font-weight:600; color:var(--faint); background:var(--panel);
      border:1px solid var(--line); border-radius:5px; padding:.1rem .45rem; white-space:nowrap; }
  .brv2 input.formula{ flex:1; min-width:16rem; font-family:ui-monospace,Menlo,monospace; font-size:.83rem;
      color:var(--ink); border:1px solid var(--line); border-radius:7px; padding:.35rem .55rem; background:var(--surface); }
  .brv2 input.formula:focus{ outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent); }
  .brv2 a.chartlink{ display:inline-block; margin-top:.15rem; font-size:.85rem; font-weight:600;
      color:var(--accent-ink); text-decoration:none; }
  .brv2 a.chartlink:hover{ text-decoration:underline; }
  .brv2 a.advlink{ margin-left:auto; font-size:.8rem; color:var(--faint); text-decoration:none; }
  .brv2 a.advlink:hover{ color:var(--accent-ink); text-decoration:underline; }
  .brv2 .addwrap{ display:grid; grid-template-columns:1fr 1fr; gap:1rem; max-width:940px; }
  @media(max-width:720px){ .brv2 .addwrap{ grid-template-columns:1fr; } }
  .brv2 details.addbox{ background:var(--surface); border:1px solid var(--line); border-radius:11px; }
  .brv2 details.addbox summary{ cursor:pointer; font-weight:700; padding:.7rem .9rem; list-style:none; }
  .brv2 details.addbox summary::-webkit-details-marker{ display:none; }
  .brv2 details.addbox summary span{ font-weight:500; font-size:.8rem; color:var(--faint); margin-left:.4rem; }
  .brv2 .addform{ padding:.2rem 1rem 1rem; display:flex; flex-direction:column; gap:.6rem; }
  .brv2 .af-row{ display:flex; align-items:center; gap:.7rem; flex-wrap:wrap; }
  .brv2 .af-row label{ font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; color:var(--faint); font-weight:700; min-width:6.5rem; }
  .brv2 .af-row label small{ text-transform:none; letter-spacing:0; font-weight:500; }
  .brv2 .af-row input[type=text], .brv2 .af-row select{ font:inherit; padding:.4rem .55rem; border:1px solid var(--line); border-radius:7px; background:var(--surface); color:var(--ink); }
  .brv2 .af-row input[type=text]{ flex:1; min-width:10rem; }
  .brv2 .af-wide input{ font-family:ui-monospace,Menlo,monospace; }
  .brv2 .af-vals{ display:flex; flex-direction:column; gap:.5rem; }
  .brv2 .af-hint{ font-size:.78rem; color:var(--soft); margin:.1rem 0 0; }
  .brv2 .palette{ background:var(--panel); border:1px solid var(--line); border-radius:9px; padding:.6rem .7rem; }
  .brv2 .pal-row{ display:flex; flex-wrap:wrap; gap:.35rem; }
  .brv2 .pal-lbl{ font-size:.7rem; color:var(--faint); font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin:.6rem 0 .35rem; }
  .brv2 .chip{ font:inherit; font-size:.82rem; cursor:pointer; border:1px solid var(--line); border-radius:6px; padding:.2rem .55rem; background:var(--surface); color:var(--ink); }
  .brv2 .chip.fn{ font-family:ui-monospace,Menlo,monospace; }
  .brv2 .chip.var{ color:var(--accent-ink); font-weight:600; }
  .brv2 .chip:hover{ border-color:var(--accent); }
  .brv2 .checkbtn{ font:inherit; font-weight:600; cursor:pointer; border:1px solid var(--line); background:var(--surface); color:var(--ink); border-radius:7px; padding:.35rem .8rem; }
  .brv2 .checkbtn:hover{ border-color:var(--accent); }
  .brv2 .checkout{ font-size:.85rem; font-weight:600; }
  .brv2 .checkout.ok{ color:var(--keep); }
  .brv2 .checkout.bad{ color:#c0392b; }

  @media (prefers-color-scheme:dark){
    .brv2:not([data-lit]){ --ink:#e8eef3; --soft:#a3b0bc; --faint:#6e7d89; --line:#2b343d; --line-2:#232b33;
        --surface:#1a2128; --panel:#161d23; --accent:#38bdf8; --accent-ink:#7dd3fc; --accent-wash:#0c2b3a;
        --num:#e0a256; --keep:#4cba81; --keep-wash:#16302a; }
  }
</style>

<div class="brv2">
  <span class="preview-flag">● Live &amp; editable</span>
  <h1>Build rules</h1>
  <p class="sub">Your <em>actual</em> stored build rules — the same numbers the worksheet uses — as <b>Cuts</b>, <b>Calcs</b> and <b>Charts</b>, plumbing tucked away. Edit a take-off, hit <b>Save cuts</b>, and the worksheet uses it.</p>
  <?php if ($flashOk !== ''): ?><div class="flash ok"><?= $e2($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr !== ''): ?><div class="flash err"><?= $e2($flashErr) ?></div><?php endif; ?>

  <div class="topline">
    <label style="font-size:.8rem;color:var(--soft);font-weight:600">Product</label>
    <form method="get" style="margin:0">
      <select name="product_id" onchange="this.form.submit()">
        <?php foreach ($products as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $productId ? 'selected' : '' ?>><?= $e2($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if ($productId > 0): ?><a class="advlink" href="/factory/build-rules.php?product_id=<?= (int) $productId ?>">Advanced: raw editor →</a><?php endif; ?>
  </div>
  <script>
  function rmVar(btn, name){
    if(!confirm('Remove "'+name+'"? This deletes the rule from this product.')) return;
    var f = btn.form; if(!f) return;
    var h = document.createElement('input'); h.type='hidden'; h.name='deletevar'; h.value=name;
    f.appendChild(h); f.submit();
  }
  </script>

  <?php if (!$vars): ?>
    <div class="empty"><b><?= $e2($productName ?: 'This product') ?></b> has no rules yet. Add your first one below — a <b>Cut</b> (a measurement minus an allowance) or a <b>Calc</b> (a formula). As soon as you add one, it appears here to edit.</div>
  <?php else: ?>
  <div class="layout">
    <div>
      <!-- CUTS -->
      <?php if ($cutsGrid): ?>
      <div class="grouplabel"><span>Cuts &nbsp;·&nbsp; measurement − allowance</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>
      <form method="post" action="/factory/build-rules-v2.php?product_id=<?= (int) $productId ?>">
        <?= csrf_field() ?>
        <?php foreach ($cutsGrid as $c): $dir = $c['dir'];
          $def  = $dir === 'add' ? '+ <span class="n">add&nbsp;on</span>' : ($dir === 'mixed' ? '<span class="n">± adjust</span>' : '− <span class="n">take&nbsp;off</span>');
          $tag  = $dir === 'add' ? 'numbers add on (mm)' : ($dir === 'mixed' ? '+ bigger · − smaller (mm)' : 'numbers take off (mm)');
          $tagc = $dir === 'add' ? 'add' : ($dir === 'mixed' ? 'mix' : 'off');
        ?>
        <div class="cut">
          <input type="hidden" name="cutdir[<?= $e2($c['name']) ?>]" value="<?= $e2($dir) ?>">
          <div class="cut-top">
            <span class="cut-name"><?= $e2($c['friendly']) ?></span>
            <span class="cut-def">= <span class="m"><?= $e2($c['base']) ?></span> <?= $def ?></span>
            <span class="dirtag <?= $tagc ?>"><?= $e2($tag) ?></span>
            <span class="code-name">(<?= $e2($c['name']) ?>)</span>
            <button type="button" class="rmbtn" onclick="rmVar(this,'<?= $e2($c['name']) ?>')">Remove</button>
          </div>
          <div class="scroll">
          <table>
            <thead><tr>
              <?php foreach ($c['keyLabels'] as $kl): ?><th><?= $e2($kl) ?></th><?php endforeach; ?>
              <?php foreach ($c['basisDisp'] as $bd): ?><th class="r"><?= $e2($bd) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($c['rows'] as $gr): ?>
              <tr>
                <?php foreach ($c['keyCols'] as $ci): ?>
                  <td><?= $e2($gr['disp'][$ci] ?? '—') ?></td>
                <?php endforeach; ?>
                <?php foreach ($c['basisKeys'] as $bk): $cell = $gr['cells'][$bk]; ?>
                  <td class="num"><?php if ($cell): $isnew = ($cell['take'] === null); $amt = $isnew ? '' : ($dir === 'deduct' ? $cell['take'] : -$cell['take']); ?><input class="take<?= $isnew ? ' newrow' : '' ?>" type="number" step="any"<?= $dir === 'mixed' ? '' : ' min="0"' ?><?= $isnew ? ' placeholder="add"' : '' ?>
                      name="cut[<?= $e2($c['name']) ?>][<?= $e2($cell['idxKey']) ?>]"
                      value="<?= $isnew ? '' : $e2(rtrim(rtrim(number_format((float) $amt, 3, '.', ''), '0'), '.')) ?>"><?php else: ?><span style="opacity:.35">—</span><?php endif; ?></td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="saverow">
          <button type="submit" class="savebtn">Save cuts</button>
          <span>Every number is millimetres. Each cut's heading says whether it <b>takes off</b> or <b>adds on</b>.</span>
        </div>
      </form>
      <?php endif; ?>

      <!-- CALCS -->
      <?php if ($calcs): ?>
      <div class="grouplabel"><span>Calcs &nbsp;·&nbsp; the real sums</span><span class="badge prints">prints on the ticket</span><span class="ln"></span></div>
      <form method="post" action="/factory/build-rules-v2.php?product_id=<?= (int) $productId ?>">
        <?= csrf_field() ?>
        <div class="cut">
        <?php foreach ($calcs as $cc): ?>
          <div class="calcedit">
            <div class="calcedit-head"><span class="cut-name"><?= $e2($cc['friendly']) ?></span> <span class="code-name">(<?= $e2($cc['name']) ?>)</span><button type="button" class="rmbtn" onclick="rmVar(this,'<?= $e2($cc['name']) ?>')">Remove</button></div>
            <?php foreach ($cc['rows'] as $ri => $r):
              $ctx = [];
              foreach ((array) ($r['cells'] ?? []) as $cv) { $cv = trim((string) $cv); if ($cv !== '') $ctx[] = $cv; }
            ?>
              <div class="calcrow">
                <?php if ($ctx): ?><span class="calcwhen"><?= $e2(implode(' · ', $ctx)) ?></span><?php endif; ?>
                <input class="formula" type="text" spellcheck="false"
                    name="calc[<?= $e2($cc['name']) ?>][<?= (int) $ri ?>]"
                    value="<?= $e2((string) ($r['result'] ?? '')) ?>">
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        </div>
        <div class="saverow">
          <button type="submit" class="savebtn">Save calcs</button>
          <span>These are the real formulas. Change a number or the sum; keep the variable names (Width, Drop, Vanes…) as they are.</span>
        </div>
      </form>
      <?php endif; ?>

      <!-- CHART -->
      <?php if ($usesChart): ?>
      <div class="grouplabel"><span>Charts &nbsp;·&nbsp; supplier lookup</span><span class="badge prints">feeds the ticket</span><span class="ln"></span></div>
      <div class="chartcard">
        <span class="ic">📊</span>
        <div>
          <div class="cn">Vogue trucks — Louvolite chart</div>
          <p>Look up the width, get the number and size of trucks. Straight from Louvolite's sizing table — rarely touched. (The <em>Trucks</em> and <em>Truck size</em> plumbing below both read this one chart.)</p>
          <p><a class="chartlink" href="/factory/allowances.php">Edit the truck charts in Allowances →</a></p>
        </div>
      </div>
      <?php endif; ?>

      <!-- PLUMBING -->
      <?php if ($plumbing): ?>
      <div class="grouplabel"><span>Working values</span><span class="badge hidden">the floor never sees these</span><span class="ln"></span></div>
      <details class="working">
        <summary>Show the internal plumbing (<?= (int) count($plumbing) ?> values)</summary>
        <?php foreach ($plumbing as $pl): ?>
          <div class="pl"><b><?= $e2($pl['friendly']) ?></b> <span class="code-name">(<?= $e2($pl['name']) ?>)</span> — <?php
            $u = array_values(array_unique(array_filter($pl['results'])));
            echo $pl['bestfit'] ? 'from the Vogue chart, else <code>width ÷ spacing</code>' : $e2(implode(' · ', array_slice($u, 0, 3)));
          ?></div>
        <?php endforeach; ?>
      </details>
      <?php endif; ?>
    </div>

    <!-- LIVE TESTER (cuts only) -->
    <div class="tester" id="tester">
      <h3>Try a size</h3>
      <p class="hint">Live — runs your real cut rows for the values you pick.</p>
      <div class="row2">
        <div class="field"><label>Width (mm)</label><input id="t_w" type="number" value="1200" inputmode="numeric"></div>
        <div class="field"><label>Drop (mm)</label><input id="t_d" type="number" value="1500" inputmode="numeric"></div>
      </div>
      <div id="t_axes"></div>
      <div class="out" id="t_out"></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($productId > 0): ?>
    <div class="grouplabel" style="margin-top:2.4rem"><span>Add a rule</span><span class="ln"></span></div>
    <div class="addwrap">
      <details class="addbox">
        <summary>+ Add a cut <span>measurement − allowance</span></summary>
        <form method="post" action="/factory/build-rules-v2.php?product_id=<?= (int) $productId ?>" class="addform">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="addcut">
          <div class="af-row"><label>Name <small>(no spaces)</small></label><input type="text" name="newname" placeholder="e.g. Fabric_Cut" required></div>
          <div class="af-row"><label>Based on</label><select name="base"><option value="Width">Width</option><option value="Drop">Drop</option></select></div>
          <div class="af-row"><label>This cut</label><select name="dir"><option value="deduct">takes off (makes it smaller)</option><option value="add">adds on (makes it bigger)</option></select></div>
          <div class="af-row"><label>Depends on</label>
            <select name="depends" id="newcut_depends">
              <option value="">Nothing — one value for all</option>
              <?php foreach ($optionSources as $os): ?><option value="<?= $e2($os['ref']) ?>"><?= $e2($os['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div id="newcut_vals" class="af-vals"></div>
          <div><button type="submit" class="savebtn">Add cut</button></div>
          <p class="af-hint">Enter the millimetres as a plain positive number — the "takes off / adds on" choice above sets the direction. You can refine it in the grid afterwards.</p>
        </form>
      </details>
      <details class="addbox">
        <summary>+ Add a calc <span>a formula</span></summary>
        <form method="post" action="/factory/build-rules-v2.php?product_id=<?= (int) $productId ?>" class="addform" id="calcform">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="addcalc">
          <div class="af-row"><label>Name <small>(no spaces)</small></label><input type="text" name="newname" id="calc_name" placeholder="e.g. Vanes" required></div>
          <div class="af-row"><label>Start from</label>
            <select id="calc_starter">
              <option value="">— blank, or pick a common one —</option>
              <option value="Quantity + 1" data-name="Vanes">Vanes — quantity + 1 (spare)</option>
              <option value="Trucks + 1" data-name="Vanes">Vanes — trucks + 1</option>
              <option value="ROUNDUP((Drop + 95) * Vanes / 1000)" data-name="Metres">Fabric metres — total</option>
              <option value="(Drop + 95) / 1000" data-name="Metres">Fabric metres — per vane</option>
              <option value="2 * Width" data-name="Cord">Draw cord — 2 × width</option>
            </select>
          </div>
          <div class="af-row af-wide"><label>Formula</label><input type="text" name="formula" id="calc_formula" class="formula" spellcheck="false" placeholder="click the buttons below, or type"></div>
          <div class="palette">
            <div class="pal-row" id="pal_funcs"></div>
            <div class="pal-lbl">Values you can use — click to drop one in:</div>
            <div class="pal-row" id="pal_vars"></div>
          </div>
          <div class="af-row"><button type="button" class="checkbtn" id="calc_check_btn">Check it</button><span id="calc_check" class="checkout"></span></div>
          <div><button type="submit" class="savebtn">Add calc</button></div>
          <p class="af-hint">No need to remember names — click the chips. <b>Check it</b> tells you if it'll run (and roughly what it gives) before you save; a formula that won't run is refused.</p>
        </form>
      </details>
    </div>
    <script>
    (function(){
      var SOURCES = <?= json_encode(array_map(static fn ($s) => ['ref' => $s['ref'], 'label' => $s['label'], 'values' => array_values($s['values'])], $optionSources), JSON_UNESCAPED_UNICODE) ?>;
      var sel = document.getElementById('newcut_depends'), box = document.getElementById('newcut_vals');
      if (!sel || !box) return;
      function field(labelText, name){
        var w = document.createElement('div'); w.className = 'af-row';
        var l = document.createElement('label'); l.textContent = labelText; w.appendChild(l);
        var i = document.createElement('input'); i.type = 'number'; i.step = 'any'; i.name = name; i.className = 'take'; w.appendChild(i);
        return w;
      }
      function render(){
        box.innerHTML = '';
        var ref = sel.value;
        if (!ref) { box.appendChild(field('Amount (mm)', 'cutval[__flat__]')); return; }
        var src = SOURCES.filter(function(s){ return s.ref === ref; })[0];
        if (src) src.values.forEach(function(v){ box.appendChild(field(v, 'cutval[' + v + ']')); });
      }
      sel.addEventListener('change', render);
      render();
    })();
    </script>
    <script>
    (function(){
      var NAMES = <?= json_encode($validNames, JSON_UNESCAPED_UNICODE) ?>;
      var PID = <?= (int) $productId ?>;
      var formula = document.getElementById('calc_formula');
      var nameEl  = document.getElementById('calc_name');
      var out     = document.getElementById('calc_check');
      if (!formula) return;

      var FUNCS = [
        {t:'+', ins:' + '}, {t:'−', ins:' - '}, {t:'×', ins:' * '}, {t:'÷', ins:' / '},
        {t:'( )', ins:'()', off:-1},
        {t:'Round up', ins:'ROUNDUP()', off:-1}, {t:'Round', ins:'ROUND()', off:-1},
        {t:'Round down', ins:'ROUNDDOWN()', off:-1}, {t:'Even', ins:'EVEN()', off:-1},
        {t:'If…', ins:'IF( , , )', off:-6}, {t:'Min', ins:'MIN( , )', off:-3}, {t:'Max', ins:'MAX( , )', off:-3}
      ];

      function insAt(text, off){
        off = off || 0;
        var s = formula.selectionStart, e = formula.selectionEnd, v = formula.value;
        if (s == null) { s = e = v.length; }
        formula.value = v.slice(0, s) + text + v.slice(e);
        var pos = s + text.length + off;
        formula.focus(); try { formula.setSelectionRange(pos, pos); } catch(_){}
        schedule();
      }
      function chip(text, ins, off, cls){
        var b = document.createElement('button'); b.type = 'button'; b.className = 'chip' + (cls ? ' ' + cls : ''); b.textContent = text;
        b.addEventListener('click', function(){ insAt(ins, off); });
        return b;
      }
      var pf = document.getElementById('pal_funcs');
      FUNCS.forEach(function(f){ pf.appendChild(chip(f.t, f.ins, f.off || 0, 'fn')); });
      var pv = document.getElementById('pal_vars');
      NAMES.forEach(function(n){ pv.appendChild(chip(n, n, 0, 'var')); });

      var starter = document.getElementById('calc_starter');
      if (starter) starter.addEventListener('change', function(){
        if (!this.value) return;
        formula.value = this.value;
        if (nameEl && !nameEl.value) nameEl.value = this.options[this.selectedIndex].getAttribute('data-name') || '';
        check();
      });

      var timer = null;
      function schedule(){ if (timer) clearTimeout(timer); timer = setTimeout(check, 400); }
      function check(){
        var f = (formula.value || '').trim();
        if (!f){ out.textContent = ''; out.className = 'checkout'; return; }
        out.textContent = 'checking…'; out.className = 'checkout';
        fetch('/factory/build-rules-v2.php?action=checkcalc&product_id=' + PID + '&formula=' + encodeURIComponent(f))
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d.ok){ out.textContent = '✓ looks good — e.g. gives ' + d.result; out.className = 'checkout ok'; }
            else { out.textContent = '✗ ' + (d.error || "won't run") + (d.suggest ? ' — did you mean “' + d.suggest + '”?' : ''); out.className = 'checkout bad'; }
          })
          .catch(function(){ out.textContent = "(couldn't check just now)"; out.className = 'checkout'; });
      }
      formula.addEventListener('input', schedule);
      document.getElementById('calc_check_btn').addEventListener('click', check);
    })();
    </script>
  <?php endif; ?>
</div>

<script>
(function(){
  // Real cut definitions from the server: [{name, friendly, base, cols:[labels], rows:[{keys:[...], sign, n}]}]
  var CUTS = <?= json_encode(array_map(static function ($c) {
      $cols = [];
      foreach ($c['active'] as $i) $cols[] = $c['labels'][$i] ?? '';
      $rows = [];
      foreach ($c['rows'] as $cr) {
          $keys = [];
          foreach ($c['active'] as $i) $keys[] = $cr['cells'][$i] ?? '';
          $rows[] = ['keys' => $keys, 'take' => $cr['take']];
      }
      return ['name' => $c['name'], 'friendly' => $c['friendly'], 'base' => $c['base'], 'cols' => $cols, 'rows' => $rows];
  }, $cuts), JSON_UNESCAPED_UNICODE) ?>;

  if (!CUTS.length) { return; }

  // Build option axes = union of each column's distinct non-blank values across all cuts.
  var axes = {}; // label -> Set
  CUTS.forEach(function(c){
    c.cols.forEach(function(lab, ci){
      if (!axes[lab]) axes[lab] = {};
      c.rows.forEach(function(r){ var v = r.keys[ci]; if (v) axes[lab][v] = true; });
    });
  });
  var axesEl = document.getElementById('t_axes');
  var pickers = {};
  Object.keys(axes).forEach(function(lab){
    var vals = Object.keys(axes[lab]);
    if (!vals.length) return;
    var wrap = document.createElement('div'); wrap.className = 'field';
    var l = document.createElement('label'); l.textContent = lab; wrap.appendChild(l);
    var sel = document.createElement('select');
    vals.forEach(function(v){ var o = document.createElement('option'); o.textContent = v; sel.appendChild(o); });
    wrap.appendChild(sel); axesEl.appendChild(wrap);
    pickers[lab] = sel; sel.addEventListener('change', recompute);
  });

  var outEl = document.getElementById('t_out');
  function evalCut(c, w, d){
    var sel = {};
    c.cols.forEach(function(lab){ sel[lab] = pickers[lab] ? pickers[lab].value : ''; });
    // First matching row wins; blank key = wildcard.
    for (var i=0;i<c.rows.length;i++){
      var r = c.rows[i], ok = true;
      for (var j=0;j<c.cols.length;j++){
        var k = r.keys[j];
        if (k !== '' && k.toLowerCase() !== String(sel[c.cols[j]]).toLowerCase()) { ok = false; break; }
      }
      if (ok){
        var baseVal = (c.base === 'Drop') ? d : w;
        if (isNaN(baseVal)) return null;
        return { val: baseVal - r.take, base: baseVal, take: r.take };
      }
    }
    return null;
  }
  function recompute(){
    var w = parseInt(document.getElementById('t_w').value,10);
    var d = parseInt(document.getElementById('t_d').value,10);
    outEl.innerHTML = '';
    CUTS.forEach(function(c){
      var res = evalCut(c, w, d);
      var row = document.createElement('div'); row.className = 'o';
      var lab = document.createElement('span'); lab.className = 'ol'; lab.textContent = c.friendly;
      var v = document.createElement('span'); v.className = 'ov';
      if (res){
        var op = res.take >= 0 ? '−' : '+';
        v.innerHTML = Math.round(res.val) + ' <small>= ' + res.base + ' ' + op + ' ' + Math.abs(res.take) + '</small>';
      } else { v.textContent = '—'; }
      row.appendChild(lab); row.appendChild(v); outEl.appendChild(row);
    });
  }
  document.getElementById('t_w').addEventListener('input', recompute);
  document.getElementById('t_d').addEventListener('input', recompute);
  recompute();
})();
</script>
<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
