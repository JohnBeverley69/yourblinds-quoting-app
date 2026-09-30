<?php
declare(strict_types=1);

/**
 * Build-variable evaluator — runs a product's decision-table build variables
 * (build_variables) against one blind's inputs, producing the computed values
 * (Trucks, H_Cut, C_L, Vanes, Mtrs, …) that print on the worksheet.
 *
 * Same logic as the factory Build Rules test panel, factored out so the
 * worksheet render and the test panel agree exactly.
 *
 *   $numVars       = ['Width' => 2360, 'Drop' => 1490, 'Fit_height' => 0, …]
 *   $optSelections = ['system' => 'SlimLine', 'extra:65' => 'Corded', …]  (ref => chosen label)
 *
 *   build_evaluate($pdo, $productId, $numVars, $optSelections)
 *     → ['vars' => [name => value (ok only)], 'results' => [name => [ok, value|error]]]
 *
 * Each variable is evaluated in seq order; its output feeds later variables.
 * A variable with no matching row flags "no rule matched" (never a wrong guess).
 */

require_once __DIR__ . '/formula_engine.php';

if (!function_exists('be_norm_math')) {
    /** Normalise pretty math glyphs (−, ×, ÷, dashes, nbsp) to ASCII for the engine. */
    function be_norm_math(string $s): string {
        return strtr($s, [
            "\u{2212}" => '-', "\u{2013}" => '-', "\u{2014}" => '-',
            "\u{00D7}" => '*', "\u{00F7}" => '/', "\u{00A0}" => ' ',
        ]);
    }
}

if (!function_exists('bv_builtin_vars')) {
    /**
     * The numeric inputs the build engine is fed for every blind, before any of
     * the product's own rules are added. THE one list — every consumer reads it
     * from here.
     *
     * It used to be written out by hand in four places and they drifted:
     * worksheet-print.php (the real render) passed Fascia_Width, but the Build
     * Rules editor's validator, its "Try a size" panel and system_check.php all
     * still listed only four names. So the roller's live Fascia_Cut rule — which
     * reads Fascia_Width and works correctly on the shop-floor ticket — could not
     * be saved from the editor ("Unknown variable: Fascia_Width", edit silently
     * discarded), and system_check.php reported a working rule as an anomaly.
     *
     * Adding a fifth input now means adding it here, once.
     *
     * @return list<string>
     */
    function bv_builtin_vars(): array
    {
        return ['Width', 'Drop', 'Fit_height', 'Quantity', 'Fascia_Width'];
    }
}

if (!function_exists('bv_synthetic_option_cols')) {
    /**
     * Option columns a build rule may use that are NOT stored option groups.
     * The renderer injects the selection itself, keyed on the lowercased label
     * (build_evaluate falls back to that), so no product_extras row exists.
     *
     *   'Multiple Blinds in One Fascia' — factory/worksheet-print.php:272 sets
     *   $optSel['multiple blinds in one fascia'] = 'Yes' when 2+ lines share a
     *   fascia_group, firing the Multiple branch of Tube_Cut / Fabric_W.
     *
     * Declared here so a checker can tell "this column is fed at render time"
     * apart from "this column is bound to a group that no longer exists".
     *
     * @return array<string, list<string>> label => the values the renderer sets
     */
    function bv_synthetic_option_cols(): array
    {
        return ['Multiple Blinds in One Fascia' => ['Yes']];
    }
}

if (!function_exists('bv_php_consumed_allowance_tables')) {
    /**
     * Allowance tables read directly by PHP rather than by a LOOKUP() inside a
     * build-rule formula. They are editable and they matter; they simply have no
     * formula referencing them, so a formula-only scan mistakes them for dead.
     *
     *   'roller_fascia_join' — quote-builder/_helpers.php:182 reads the 'gap'
     *   row to widen a shared fascia by one gap per join.
     *
     * @return list<string>
     */
    function bv_php_consumed_allowance_tables(): array
    {
        return ['roller_fascia_join'];
    }
}

if (!function_exists('build_rules_source')) {
    /**
     * The product whose build rules a product uses. Normally itself; a product
     * set to "use the cut rules of" another (Factory → Build rules) borrows that
     * product's variables instead — e.g. Head Rail Only is made exactly like the
     * vertical's headrail, so it shares the vertical's rules rather than keeping
     * a copy that would drift. Stored in factory_kv as build_rules_from:<id>.
     */
    function build_rules_source(PDO $pdo, int $productId): int
    {
        try {
            $s = $pdo->prepare('SELECT v FROM factory_kv WHERE k = ? LIMIT 1');
            $s->execute(['build_rules_from:' . $productId]);
            $src = (int) ($s->fetchColumn() ?: 0);
            if ($src > 0 && $src !== $productId) return $src;
        } catch (Throwable $e) { /* factory_kv not migrated */ }
        return $productId;
    }
}

if (!function_exists('be_norm_choice')) {
    /** Compare option labels loosely on slash spacing: "L / L" == "L/L". */
    function be_norm_choice(string $s): string
    {
        return mb_strtolower(trim((string) preg_replace('~\s*/\s*~u', '/', $s)));
    }
}

if (!function_exists('build_evaluate')) {
    /**
     * @param array<string,mixed>  $numVars       name => number/string inputs
     * @param array<string,string> $optSelections column ref => chosen option label
     * @return array{vars:array<string,mixed>, results:array<int,array<string,mixed>>}
     */
    function build_evaluate(PDO $pdo, int $productId, array $numVars, array $optSelections): array
    {
        // A product sharing another's rules evaluates against THAT product's
        // variables. Its systems are matched to the source's by name — "SlimLine"
        // picks the source's "SlimLine Vert" — so the shared rows fire.
        $rulesPid = build_rules_source($pdo, $productId);
        if ($rulesPid !== $productId && isset($optSelections['system']) && $optSelections['system'] !== '') {
            try {
                $sn = $pdo->prepare('SELECT name FROM product_systems WHERE product_id = ?');
                $sn->execute([$rulesPid]);
                $mine = mb_strtolower(trim((string) $optSelections['system']));
                $best = null;
                foreach ($sn->fetchAll(PDO::FETCH_COLUMN) as $srcName) {
                    $l = mb_strtolower(trim((string) $srcName));
                    if ($l === $mine) { $best = (string) $srcName; break; }
                    if ($best === null && strpos($l, $mine . ' ') === 0) $best = (string) $srcName;
                }
                if ($best !== null) $optSelections['system'] = $best;
            } catch (Throwable $e) { /* leave the selection as it is */ }
        }

        // Variables for this product (or the product it shares rules with), in evaluation order.
        $variables = [];
        try {
            $vs = $pdo->prepare('SELECT name, columns_json, rows_json FROM build_variables WHERE product_id = ? ORDER BY seq, id');
            $vs->execute([$rulesPid]);
            foreach ($vs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $variables[] = [
                    'name'    => (string) $r['name'],
                    'columns' => json_decode((string) $r['columns_json'], true) ?: [],
                    'rows'    => json_decode((string) $r['rows_json'], true) ?: [],
                ];
            }
        } catch (Throwable $e) { /* no build_variables */ }

        // Allowance tables for LOOKUP()/BESTFIT(), scoped to the factory that owns
        // this product (white-label: owning factory = COALESCE(source_client_id,
        // client_id)). Beverley products resolve to the canonical factory and all
        // existing rows backfill there, so the engine loads exactly what it did
        // before. Pre client-scope migration, load all (old behaviour).
        $allowances = [];
        try {
            $hasClientCol = false;
            try { $pdo->query('SELECT client_id FROM allowance_rows LIMIT 0'); $hasClientCol = true; }
            catch (Throwable $e) { /* pre migration */ }

            if ($hasClientCol) {
                $fid = null;
                try {
                    $fs = $pdo->prepare(
                        'SELECT COALESCE(NULLIF(source_client_id,0), client_id) FROM products WHERE id = ? LIMIT 1'
                    );
                    $fs->execute([$productId]);
                    $v = $fs->fetchColumn();
                    if ($v !== false && $v !== null) $fid = (int) $v;
                } catch (Throwable $e) { /* products.source_client_id absent — leave null */ }
                if ($fid !== null) {
                    $as = $pdo->prepare('SELECT table_name, key_norm, value FROM allowance_rows WHERE client_id = ?');
                    $as->execute([$fid]);
                    foreach ($as as $ar) {
                        $allowances[strtolower((string) $ar['table_name'])][(string) $ar['key_norm']] = (float) $ar['value'];
                    }
                }
            } else {
                foreach ($pdo->query('SELECT table_name, key_norm, value FROM allowance_rows') as $ar) {
                    $allowances[strtolower((string) $ar['table_name'])][(string) $ar['key_norm']] = (float) $ar['value'];
                }
            }
        } catch (Throwable $e) { /* allowance_rows not migrated */ }

        $vars    = $numVars;                 // computed variables feed forward into this pool
        $results = [];

        foreach ($variables as $v) {
            $name = $v['name'];
            // First row whose every set cell matches the order's selection.
            $match = null;
            foreach ($v['rows'] as $row) {
                $ok = true;
                foreach ($v['columns'] as $i => $col) {
                    $cell = trim((string) ($row['cells'][$i] ?? ''));
                    if ($cell === '') continue;   // — any —
                    // Match by the column's ref (test panel, master ids) OR its group
                    // name (real orders, whose tenant option-group ids differ).
                    $ref = (string) ($col['ref'] ?? '');
                    $lbl = strtolower(trim((string) ($col['label'] ?? '')));
                    $sel = $optSelections[$ref] ?? ($lbl !== '' ? ($optSelections[$lbl] ?? '') : '');
                    if (be_norm_choice($cell) !== be_norm_choice((string) $sel)) { $ok = false; break; }
                }
                if ($ok) { $match = $row; break; }
            }
            if ($match === null) {
                $results[] = ['name' => $name, 'ok' => false, 'value' => 'no rule matched'];
                continue;
            }
            // A blank formula is a deliberate "this combination doesn't have
            // this measurement" — Split Draw 2 Wands has no draw cord. Leave the
            // variable unset so the ticket simply prints nothing for it, and say
            // so plainly rather than reporting a broken formula.
            if (trim((string) ($match['result'] ?? '')) === '') {
                $results[] = ['name' => $name, 'ok' => true, 'blank' => true, 'value' => ''];
                continue;
            }
            try {
                $val = formula_eval(be_norm_math((string) $match['result']), $vars, $allowances);
                $vars[$name] = $val;
                $results[] = ['name' => $name, 'ok' => true, 'value' => $val];
            } catch (Throwable $e) {
                $results[] = ['name' => $name, 'ok' => false, 'value' => $e->getMessage()];
            }
        }

        return ['vars' => $vars, 'results' => $results];
    }
}
