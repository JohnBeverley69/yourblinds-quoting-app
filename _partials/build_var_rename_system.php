<?php
declare(strict_types=1);

/**
 * Build rules store a system as a NAME string in the "System" column of a build
 * variable's rows_json (there is no system_id FK). So renaming a system on the
 * product must rewrite those cells, or the rules silently stop matching at ticket
 * time. This keeps the two in step.
 *
 * build_var_rename_system(): rewrite every System-column cell equal to $oldName
 * (case-insensitive) to $newName, across all of a product's build variables.
 * Idempotent (only the old name matches). Returns rows changed.
 */

if (!function_exists('bv_system_col_index')) {
    /** The index of the "System" column in a build variable's columns, or null. */
    function bv_system_col_index(array $columns): ?int
    {
        foreach ($columns as $i => $c) {
            $ref = strtolower((string) ($c['ref'] ?? ''));
            $lbl = strtolower((string) ($c['label'] ?? ''));
            if ($ref === 'system' || strpos($lbl, 'system') !== false) return (int) $i;
        }
        return null;
    }
}

if (!function_exists('build_var_rename_system')) {
    function build_var_rename_system(PDO $pdo, int $productId, string $oldName, string $newName): int
    {
        $oldName = trim($oldName);
        $newName = trim($newName);
        if ($oldName === '' || $newName === '' || $oldName === $newName) return 0;
        try {
            $sel = $pdo->prepare('SELECT id, columns_json, rows_json FROM build_variables WHERE product_id = ?');
            $sel->execute([$productId]);
            $upd = $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE id = ?');
            $oldLc = mb_strtolower($oldName);
            $changed = 0;
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $cols = json_decode((string) $v['columns_json'], true) ?: [];
                $rows = json_decode((string) $v['rows_json'], true) ?: [];
                $sysIdx = bv_system_col_index($cols);
                if ($sysIdx === null) continue;
                $dirty = false;
                foreach ($rows as &$row) {
                    if (!isset($row['cells'][$sysIdx])) continue;
                    if (mb_strtolower(trim((string) $row['cells'][$sysIdx])) === $oldLc) {
                        $row['cells'][$sysIdx] = $newName;
                        $dirty = true; $changed++;
                    }
                }
                unset($row);
                if ($dirty) $upd->execute([json_encode($rows), (int) $v['id']]);
            }
            return $changed;
        } catch (Throwable $e) {
            // build_variables absent, or malformed JSON — never break the caller.
            error_log('build_var_rename_system failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('build_var_rename_group')) {
    /**
     * Same problem, the other two name strings.
     *
     * A build variable's COLUMN is bound to an option group by `label` (the
     * `ref` carries extra:<id>, but every consumer matches on the label —
     * system_check.php and build_evaluate both look the group up by name). And
     * each CELL is the choice's label. So renaming an option group, or one of
     * its choices, silently unbinds the rule: the column matches no group, or
     * the row matches no choice, and the blind sizes wrong or blank at ticket
     * time with nothing to show for it but a system_check line.
     *
     * Only the System rename used to cascade. These two close the gap.
     *
     * Rewrites every column whose label equals $oldLabel. Returns columns changed.
     */
    function build_var_rename_group(PDO $pdo, int $productId, string $oldLabel, string $newLabel): int
    {
        $oldLabel = trim($oldLabel);
        $newLabel = trim($newLabel);
        if ($oldLabel === '' || $newLabel === '' || $oldLabel === $newLabel) return 0;
        try {
            $sel = $pdo->prepare('SELECT id, columns_json FROM build_variables WHERE product_id = ?');
            $sel->execute([$productId]);
            $upd = $pdo->prepare('UPDATE build_variables SET columns_json = ? WHERE id = ?');
            $oldLc = mb_strtolower($oldLabel);
            $changed = 0;
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $cols = json_decode((string) $v['columns_json'], true) ?: [];
                $dirty = false;
                foreach ($cols as &$c) {
                    if (mb_strtolower(trim((string) ($c['label'] ?? ''))) === $oldLc) {
                        $c['label'] = $newLabel;
                        $dirty = true; $changed++;
                    }
                }
                unset($c);
                if ($dirty) $upd->execute([json_encode($cols), (int) $v['id']]);
            }
            return $changed;
        } catch (Throwable $e) {
            error_log('build_var_rename_group failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('build_var_rename_choice')) {
    /**
     * Rewrites cells equal to $oldValue in the column bound to $groupLabel.
     * Scoped to that one column so renaming "None" in Fascia Options cannot
     * touch a "None" that legitimately lives in Braid or Eyelets.
     *
     * Returns cells changed.
     */
    function build_var_rename_choice(PDO $pdo, int $productId, string $groupLabel, string $oldValue, string $newValue): int
    {
        $groupLabel = trim($groupLabel);
        $oldValue   = trim($oldValue);
        $newValue   = trim($newValue);
        if ($groupLabel === '' || $oldValue === '' || $newValue === '' || $oldValue === $newValue) return 0;
        try {
            $sel = $pdo->prepare('SELECT id, columns_json, rows_json FROM build_variables WHERE product_id = ?');
            $sel->execute([$productId]);
            $upd = $pdo->prepare('UPDATE build_variables SET rows_json = ? WHERE id = ?');
            $groupLc = mb_strtolower($groupLabel);
            $oldLc   = mb_strtolower($oldValue);
            $changed = 0;
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $cols = json_decode((string) $v['columns_json'], true) ?: [];
                $rows = json_decode((string) $v['rows_json'], true) ?: [];
                $idx  = null;
                foreach ($cols as $i => $c) {
                    if (mb_strtolower(trim((string) ($c['label'] ?? ''))) === $groupLc) { $idx = (int) $i; break; }
                }
                if ($idx === null) continue;
                $dirty = false;
                foreach ($rows as &$row) {
                    if (!isset($row['cells'][$idx])) continue;
                    if (mb_strtolower(trim((string) $row['cells'][$idx])) === $oldLc) {
                        $row['cells'][$idx] = $newValue;
                        $dirty = true; $changed++;
                    }
                }
                unset($row);
                if ($dirty) $upd->execute([json_encode($rows), (int) $v['id']]);
            }
            return $changed;
        } catch (Throwable $e) {
            error_log('build_var_rename_choice failed: ' . $e->getMessage());
            return 0;
        }
    }
}
