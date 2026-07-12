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

if (!function_exists('build_evaluate')) {
    /**
     * @param array<string,mixed>  $numVars       name => number/string inputs
     * @param array<string,string> $optSelections column ref => chosen option label
     * @return array{vars:array<string,mixed>, results:array<int,array<string,mixed>>}
     */
    function build_evaluate(PDO $pdo, int $productId, array $numVars, array $optSelections): array
    {
        // Variables for this product, in evaluation order.
        $variables = [];
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
        } catch (Throwable $e) { /* no build_variables */ }

        // Shared allowance tables for LOOKUP()/BESTFIT().
        $allowances = [];
        try {
            foreach ($pdo->query('SELECT table_name, key_norm, value FROM allowance_rows') as $ar) {
                $allowances[strtolower((string) $ar['table_name'])][(string) $ar['key_norm']] = (float) $ar['value'];
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
                    $sel = trim((string) ($optSelections[(string) ($col['ref'] ?? '')] ?? ''));
                    if (mb_strtolower($cell) !== mb_strtolower($sel)) { $ok = false; break; }
                }
                if ($ok) { $match = $row; break; }
            }
            if ($match === null) {
                $results[] = ['name' => $name, 'ok' => false, 'value' => 'no rule matched'];
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
