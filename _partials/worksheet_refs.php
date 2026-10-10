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

if (!function_exists('ws_rules_audience')) {
    /**
     * Every product whose worksheet depends on this product's build variables:
     * itself, plus any product set to "Same as <this>" in the rules editor
     * (factory_kv, key build_rules_from:<product>). Renaming or deleting a
     * variable breaks the sharers' tickets just as surely as its own.
     */
    function ws_rules_audience(PDO $pdo, int $rulesProductId): array
    {
        $ids = [$rulesProductId];
        try {
            $st = $pdo->prepare("SELECT k FROM factory_kv WHERE k LIKE 'build_rules_from:%' AND v = ?");
            $st->execute([(string) $rulesProductId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $k) {
                $pid = (int) substr((string) $k, strlen('build_rules_from:'));
                if ($pid > 0 && $pid !== $rulesProductId) $ids[] = $pid;
            }
        } catch (Throwable $e) { /* factory_kv not migrated */ }
        return $ids;
    }
}

if (!function_exists('ws_orphan_report')) {
    /**
     * Plain-English lines naming every worksheet field that no longer resolves,
     * for every product affected by a change to this product's build variables.
     * Empty when nothing is broken.
     *
     * Called after saving or deleting build rules: the save itself always
     * succeeds, and the damage only shows on a printed ticket, so the editor
     * has to say so at the moment it happens.
     */
    function ws_orphan_report(PDO $pdo, int $rulesProductId): array
    {
        $out = [];
        try {
            foreach (ws_rules_audience($pdo, $rulesProductId) as $pid) {
                $st = $pdo->prepare(
                    'SELECT t.name AS tpl, t.layout_json, p.name AS pname, p.client_id
                       FROM worksheet_templates t JOIN products p ON p.id = t.product_id
                      WHERE t.product_id = ?'
                );
                $st->execute([$pid]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
                    $lay = json_decode((string) $t['layout_json'], true);
                    if (!is_array($lay)) continue;
                    $bad = ws_orphan_sources(
                        $lay,
                        ws_valid_var_names($pdo, $pid),
                        ws_valid_opt_keys($pdo, $pid, (int) $t['client_id'])
                    );
                    foreach ($bad as $src => $cap) {
                        $out[] = $src . ($cap !== '' ? ' ("' . $cap . '")' : '')
                               . ' on ' . (string) $t['pname'] . ' — ' . (string) $t['tpl'];
                    }
                }
            }
        } catch (Throwable $e) { /* reporting only — never block a save */ }
        return $out;
    }
}

if (!function_exists('bv_replace_identifier')) {
    /**
     * Rewrite one identifier in a build-rule formula, leaving quoted strings
     * alone. BESTFIT("vogue_split_cord", Width, 1) and
     * Trucks & " x " & Truck_Size & "mm" both carry text that must not be
     * touched, so the formula is split on double-quoted runs and only the code
     * between them is rewritten.
     *
     * Word boundaries treat "_" as part of a word, so renaming Trucks leaves
     * Truck_Size and Truck_Spec alone, which is what you want.
     */
    function bv_replace_identifier(string $formula, string $old, string $new): string
    {
        if ($old === '' || $new === '' || $old === $new) return $formula;
        // The formula language writes a quote inside a string by doubling it
        // ("" — see _partials/formula_engine.php); there are no backslash
        // escapes at all. So splitting on the quote character alternates
        // reliably: even pieces are code, odd pieces are string text. A ""
        // escape lands as an empty even piece, which rewrites to nothing.
        $segs = explode('"', $formula);
        $pat  = '/\b' . preg_quote($old, '/') . '\b/u';
        foreach ($segs as $i => $seg) {
            if ($i % 2 === 0) $segs[$i] = (string) preg_replace($pat, $new, $seg);
        }
        return implode('"', $segs);
    }
}

if (!function_exists('bv_rename_variable')) {
    /**
     * Rename a build variable and take everything that names it along.
     *
     * A build variable is identified by its NAME everywhere — build-rules-v2
     * addresses rows as (product_id, name), other rules reference it inside
     * their formulas as a bare identifier, and worksheet fields print it as
     * var:<Name>. Nothing is an id, so a rename done in one place alone leaves
     * the rest pointing at a name that no longer exists, and the only symptom
     * is a ticket printing a caption with no value.
     *
     * So this rewrites, in one transaction:
     *   1. the variable's own row
     *   2. every OTHER rule on the same product whose formula names it
     *      (Vanes = Trucks + 1, Metres = ROUNDUP((Drop + 95) * Vanes / 1000))
     *   3. var:<Old> on every worksheet template of every product that uses
     *      these rules — including any product set to "Same as <this one>"
     *
     * Returns ['rules' => n, 'fields' => n]. Throws if the new name is not a
     * legal variable name, or is already taken on this product.
     */
    function bv_rename_variable(PDO $pdo, int $productId, string $old, string $new): array
    {
        $old = trim($old);
        $new = trim($new);
        // Names are code, not labels — the same rule build-rules-v2 applies when
        // a rule is created.
        if ($new === '' || $new !== preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', $new))) {
            throw new RuntimeException('A rule name can only use letters, digits and underscores.');
        }
        if (mb_strlen($new) > 64) throw new RuntimeException('That name is too long (64 characters max).');
        if ($old === '' || $old === $new) return ['rules' => 0, 'fields' => 0];

        $builtin = array_map('mb_strtolower', bv_builtin_vars());
        if (in_array(mb_strtolower($new), $builtin, true)) {
            throw new RuntimeException('"' . $new . '" is one of the engine\'s own inputs (Width, Drop, Quantity…) — pick another name.');
        }

        $own = !$pdo->inTransaction();
        if ($own) $pdo->beginTransaction();
        try {
            $all = $pdo->prepare('SELECT id, name, rows_json FROM build_variables WHERE product_id = ?');
            $all->execute([$productId]);
            $rows  = $all->fetchAll(PDO::FETCH_ASSOC);
            $found = false;
            foreach ($rows as $r) {
                if ((string) $r['name'] === $old) { $found = true; }
                elseif (mb_strtolower((string) $r['name']) === mb_strtolower($new)) {
                    throw new RuntimeException('This product already has a rule called "' . $r['name'] . '".');
                }
            }
            if (!$found) throw new RuntimeException('There is no rule called "' . $old . '" on this product.');

            $pdo->prepare('UPDATE build_variables SET name = ? WHERE product_id = ? AND name = ?')
                ->execute([$new, $productId, $old]);

            $touchedRules = 0;
            $updRows = $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE id = ?');
            foreach ($rows as $r) {
                if ((string) $r['name'] === $old) continue;          // its own formula can't name itself
                $rj = json_decode((string) $r['rows_json'], true);
                if (!is_array($rj)) continue;
                $dirty = false;
                foreach ($rj as &$row) {
                    $res = (string) ($row['result'] ?? '');
                    $new2 = bv_replace_identifier($res, $old, $new);
                    if ($new2 !== $res) { $row['result'] = $new2; $dirty = true; }
                }
                unset($row);
                if ($dirty) {
                    $updRows->execute([json_encode($rj, JSON_UNESCAPED_UNICODE), (int) $r['id']]);
                    $touchedRules++;
                }
            }

            $touchedFields = 0;
            $updTpl = $pdo->prepare('UPDATE worksheet_templates SET layout_json = ? WHERE id = ?');
            foreach (ws_rules_audience($pdo, $productId) as $pid) {
                $ts = $pdo->prepare('SELECT id, layout_json FROM worksheet_templates WHERE product_id = ?');
                $ts->execute([$pid]);
                foreach ($ts->fetchAll(PDO::FETCH_ASSOC) as $t) {
                    $lay = json_decode((string) $t['layout_json'], true);
                    if (!is_array($lay)) continue;
                    $hits = 0;
                    $walk = function (&$node) use (&$walk, $old, $new, &$hits): void {
                        if (isset($node['source']) && is_string($node['source']) && $node['source'] === 'var:' . $old) {
                            $node['source'] = 'var:' . $new;
                            $hits++;
                        }
                        foreach ($node as &$child) { if (is_array($child)) $walk($child); }
                        unset($child);
                    };
                    $walk($lay);
                    if ($hits > 0) {
                        $updTpl->execute([json_encode($lay, JSON_UNESCAPED_UNICODE), (int) $t['id']]);
                        $touchedFields += $hits;
                    }
                }
            }

            if ($own) $pdo->commit();
            return ['rules' => $touchedRules, 'fields' => $touchedFields];
        } catch (Throwable $e) {
            if ($own && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
