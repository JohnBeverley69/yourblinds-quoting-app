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
