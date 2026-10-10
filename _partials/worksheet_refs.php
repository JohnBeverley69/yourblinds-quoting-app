<?php
declare(strict_types=1);

/**
 * What a product's worksheet fields can actually resolve to — one definition,
 * shared by the worksheet editor, the build-rules editors and system_check.php,
 * so the editor, the warning and the check can never disagree about whether a
 * field works.
 *
 * A worksheet field's "source" is a string: var:<BuildVariable>,
 * opt:<option code or name>, order:<detail>, text, qr, barcode:… or __break__.
 * factory/worksheet-print.php resolves var: and opt: by LOOKUP, and a miss
 * prints nothing at all — no error, no gap in the layout, just a caption with
 * no value beside it. That is how the Fabric Only ticket printed "Mtrs" and
 * "Cut" with nothing after them for weeks: its template asked for the parent
 * vertical's var:Mtrs / var:Hem_To_Hem while the product's own variables are
 * called Metres and Fabric_Cut.
 *
 * Build variables are identified by NAME, not by id: the raw editor saves by
 * deleting the product's rows and re-inserting them, so ids change on every
 * save, and factory/build-rules-v2.php addresses them as
 * (product_id, name). The name is also validated as code, not a label.
 */

require_once __DIR__ . '/build_eval.php';   // bv_builtin_vars(), build_rules_source()

if (!function_exists('ws_valid_var_names')) {
    /**
     * Build-variable names this product's worksheet can print, as
     * ['lower' => [lowercased => exact], 'exact' => [exact => 1]].
     *
     * Follows build_rules_source(): a product set to "Same as <other>" evaluates
     * THAT product's variables, and its worksheet palette offers them, so its
     * fields must be validated against them too. Built-ins (Width, Drop,
     * Quantity…) come from the engine's own list rather than a copy.
     */
    function ws_valid_var_names(PDO $pdo, int $productId): array
    {
        $lower = [];
        $exact = [];
        foreach (bv_builtin_vars() as $b) { $lower[mb_strtolower($b)] = $b; $exact[$b] = 1; }
        try {
            $st = $pdo->prepare('SELECT name FROM build_variables WHERE product_id = ?');
            $st->execute([build_rules_source($pdo, $productId)]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) {
                $n = (string) $n;
                $lower[mb_strtolower($n)] = $n;
                $exact[$n] = 1;
            }
        } catch (Throwable $e) { /* build_variables not migrated */ }
        return ['lower' => $lower, 'exact' => $exact];
    }
}

if (!function_exists('ws_valid_opt_keys')) {
    /**
     * opt: keys this product's worksheet can print => the option group's current
     * name. worksheet-print.php keys option values by the group's stable code,
     * its current name and the order-time snapshot name, all lowercased; only
     * the first two can be known without an order, and those are what the
     * editor writes. Most groups have no code (6,920 of 9,428 live ones), so
     * most opt: fields are held by name and a rename breaks them.
     */
    function ws_valid_opt_keys(PDO $pdo, int $productId, int $clientId): array
    {
        $keys = [];
        try {
            $hasCode = false;
            try { $pdo->query('SELECT code FROM product_extras LIMIT 1'); $hasCode = true; } catch (Throwable $e) {}
            $st = $pdo->prepare('SELECT name, ' . ($hasCode ? 'code' : 'NULL AS code')
                              . ' FROM product_extras WHERE product_id = ? AND client_id = ? AND active = 1');
            $st->execute([$productId, $clientId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $name = trim((string) $r['name']);
                if ($name !== '') $keys[mb_strtolower($name)] = $name;
                $code = mb_strtolower(trim((string) ($r['code'] ?? '')));
                if ($code !== '') $keys[$code] = $name;
            }
        } catch (Throwable $e) { /* product_extras not available */ }
        return $keys;
    }
}

if (!function_exists('ws_layout_sources')) {
    /**
     * Every field source in a decoded layout, in order, as
     * [['source' => string, 'caption' => string], …]. Walks the whole tree so it
     * does not care whether fields sit on the header or on a label, and skips
     * the structural entries that are not lookups.
     */
    function ws_layout_sources(array $layout): array
    {
        $out   = [];
        $walk  = function (array $node) use (&$walk, &$out): void {
            if (isset($node['source']) && is_string($node['source'])) {
                $s = $node['source'];
                if ($s !== '' && $s !== '__break__' && $s !== 'text' && $s !== 'qr'
                    && strncmp($s, 'barcode:', 8) !== 0) {
                    $out[] = ['source' => $s, 'caption' => (string) ($node['caption'] ?? '')];
                }
            }
            foreach ($node as $child) { if (is_array($child)) $walk($child); }
        };
        $walk($layout);
        return $out;
    }
}

if (!function_exists('ws_orphan_sources')) {
    /**
     * The var:/opt: sources in one layout that this product cannot resolve, as
     * [source => caption]. These are the fields that print a caption with
     * nothing beside it.
     *
     * order: sources are deliberately NOT judged here: the palette hides some of
     * them per product (factory/worksheets.php gates ten vertical-only details
     * on the product NAME containing "Vertical"), but the print path supplies
     * whatever the order has, so a hidden one is not necessarily a dead field.
     * That gate is its own question.
     */
    function ws_orphan_sources(array $layout, array $validVars, array $validOpts): array
    {
        $bad = [];
        foreach (ws_layout_sources($layout) as $f) {
            $s = $f['source'];
            if (strncmp($s, 'var:', 4) === 0) {
                $n = trim(substr($s, 4));
                // The runtime lookup is case-sensitive, so a different spelling
                // is just as dead as a missing one.
                if ($n === '' || !isset($validVars['exact'][$n])) $bad[$s] = $f['caption'];
            } elseif (strncmp($s, 'opt:', 4) === 0) {
                $k = mb_strtolower(trim(substr($s, 4)));
                if ($k === '' || !isset($validOpts[$k])) $bad[$s] = $f['caption'];
            }
        }
        return $bad;
    }
}
