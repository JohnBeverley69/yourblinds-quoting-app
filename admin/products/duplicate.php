<?php
declare(strict_types=1);

/**
 * Deep-copy a product, including every dependent row across the
 * catalogue schema, and redirect to the new product's edit page.
 *
 * Big time-saver for tenants adding a near-identical product
 * ("Roller Blind Premium" → start from "Roller Blind Standard"
 * and tweak). Without this, building the second product from
 * scratch means re-entering systems, fabrics, options, choices,
 * and price tables one row at a time.
 *
 * What gets copied (all wrapped in a single transaction so a
 * partial failure rolls back — never leaves a half-built product
 * littering the catalogue):
 *
 *   products                       — the row itself, name suffixed " (copy)"
 *   product_systems                — system list, with old→new id remap
 *   product_options                — fabrics, system_id remapped
 *   product_extras                 — options, parent_choice_id NULL first
 *   product_extra_choices          — choices, remapped to new extras + systems
 *   product_extras.parent_choice_id — patched up after choices exist
 *   product_extra_parent_choices   — choice cascade table (both ids remapped)
 *   price_tables                   — system_id remapped
 *   price_table_rows               — price_table_id remapped
 *   product_extra_choice_bands / _options — choice limits (band / fabric) remapped
 *   extra_choice_price_rows        — product_extra_choice_id remapped
 *   client_markups                 — per (product, system), system_id remapped
 *   client_discounts               — same shape as markups
 *
 * What does NOT get copied — by design:
 *   - audit timestamps reset to NOW (this is a new product)
 *   - the new product's name has " (copy)" appended so the admin
 *     can tell at a glance which is the original
 *   - sort_order is recalculated to "last in the list" so the new
 *     product appears at the bottom, ready to be dragged into place
 *
 * Permission: admin (super-admin not required — any tenant admin
 * can duplicate their own products). Multi-tenant safe via the
 * client_id = current tenant check on the source product lookup.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/products/index.php');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$sourceId = (int) ($_POST['id'] ?? 0);


$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Copy one row: EVERY column of the source row is carried across except its
 * own id, timestamps, source_* push markers (a duplicate is the tenant's OWN
 * product, not a linked copy) and generated columns; $set overrides the links.
 * Reading the columns from the row itself — rather than a hand-written list —
 * is what keeps a duplicate from silently dropping a column added later
 * (price_per_sqm, line_charge, a fabric's system, option codes… were all lost
 * by the old hand-written lists; tests/cases/40_copies_match.php checks it).
 */
$generatedCols = static function (string $table) use ($pdo): array {
    static $cache = [];
    if (!isset($cache[$table])) {
        $st = $pdo->prepare("SELECT column_name AS c FROM information_schema.columns
                              WHERE table_schema = DATABASE() AND table_name = ?
                                AND (extra LIKE '%GENERATED%' OR extra IN ('VIRTUAL','PERSISTENT','STORED'))");
        $st->execute([$table]);
        $cache[$table] = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cache[$table];
};
$copyRow = static function (string $table, array $row, array $set) use ($pdo, $generatedCols): int {
    foreach ($row as $col => $v) {
        if (in_array($col, ['id', 'created_at', 'updated_at'], true) || strncmp($col, 'source_', 7) === 0) unset($row[$col]);
    }
    foreach ($generatedCols($table) as $g) unset($row[$g]);
    $row = $set + $row;
    $pdo->prepare("INSERT INTO `$table` (`" . implode('`,`', array_keys($row)) . '`) VALUES ('
        . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    return (int) $pdo->lastInsertId();
};
$rowsOf = static function (string $sql, array $params) use ($pdo): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};

try {
    // ── Verify the source belongs to this tenant ─────────────────────
    //
    // Tenant scoping is enforced once here at the gate, then trusted
    // for every subsequent SELECT (which all filter by source product
    // id, which we've already verified is in this tenant's client_id).
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ? AND client_id = ? LIMIT 1');
    $st->execute([$sourceId, $clientId]);
    $src = $st->fetch(PDO::FETCH_ASSOC);
    if (!$src) {
        throw new RuntimeException('Product not found (or belongs to a different tenant).');
    }

    $pdo->beginTransaction();

    // ── 1. products row ──────────────────────────────────────────────
    //
    // Name = "Original (copy)". If a previous copy exists we just stack
    // another " (copy)" on — keeps the rule trivial. The sort_order is
    // bumped past the current max so the new product sits at the end,
    // ready to be dragged into place. Every other column (behaviour flags,
    // price source, supplier, cost…) is copied as-is.
    $maxSortRow = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM products WHERE client_id = ?');
    $maxSortRow->execute([$clientId]);
    $newSort = ((int) $maxSortRow->fetchColumn()) + 1;
    $newName = $src['name'] . ' (copy)';
    $newProductId = $copyRow('products', $src, ['client_id' => $clientId, 'name' => $newName, 'sort_order' => $newSort]);

    // ── 2. product_systems ───────────────────────────────────────────
    //
    // systemsMap[old_id] = new_id so anything referencing a system_id
    // later (fabrics, choices, price tables, markups) can remap.
    $systemsMap = [];
    foreach ($rowsOf('SELECT * FROM product_systems WHERE product_id = ? ORDER BY id', [$sourceId]) as $s) {
        $systemsMap[(int) $s['id']] = $copyRow('product_systems', $s, ['client_id' => $clientId, 'product_id' => $newProductId]);
    }
    $mapSystem = static fn ($id) => $id === null ? null : ($systemsMap[(int) $id] ?? null);

    // ── 3. product_options (fabrics) — system_id remapped ────────────
    $optionsMap = [];
    foreach ($rowsOf('SELECT * FROM product_options WHERE product_id = ? ORDER BY id', [$sourceId]) as $f) {
        $set = ['client_id' => $clientId, 'product_id' => $newProductId];
        if (array_key_exists('system_id', $f)) $set['system_id'] = $mapSystem($f['system_id']);
        $optionsMap[(int) $f['id']] = $copyRow('product_options', $f, $set);
    }

    // ── 4. product_extras (options) — pass 1: parent_choice_id NULL ─
    //
    // Chicken-and-egg: extras can reference a parent choice on the
    // same product, but choices reference extras. We insert extras
    // first with parent_choice_id=NULL, build choices (which now have
    // valid extra ids), then patch up parent_choice_id in a second pass.
    $extrasMap = [];
    $srcExtrasRows = $rowsOf('SELECT * FROM product_extras WHERE product_id = ? ORDER BY id', [$sourceId]);
    foreach ($srcExtrasRows as $e) {
        $extrasMap[(int) $e['id']] = $copyRow('product_extras', $e,
            ['client_id' => $clientId, 'product_id' => $newProductId, 'parent_choice_id' => null]);
    }

    // ── 5. product_extra_choices — remap extra_id + system_id ────────
    //
    // system_id can be NULL (a choice that applies to every system); a
    // choice on a system we didn't copy (orphaned data) falls back to NULL.
    $choicesMap = [];
    if ($extrasMap) {
        $in = implode(',', array_fill(0, count($extrasMap), '?'));
        foreach ($rowsOf("SELECT * FROM product_extra_choices WHERE product_extra_id IN ($in) ORDER BY id", array_keys($extrasMap)) as $c) {
            $choicesMap[(int) $c['id']] = $copyRow('product_extra_choices', $c, [
                'product_extra_id' => $extrasMap[(int) $c['product_extra_id']],
                'system_id'        => $mapSystem($c['system_id']),
            ]);
        }
    }

    // ── 6. product_extras — pass 2: patch parent_choice_id ───────────
    $updExtra = $pdo->prepare('UPDATE product_extras SET parent_choice_id = ? WHERE id = ?');
    foreach ($srcExtrasRows as $e) {
        if ($e['parent_choice_id'] === null) continue;
        $newExtraId  = $extrasMap[(int) $e['id']] ?? null;
        $newParentId = $choicesMap[(int) $e['parent_choice_id']] ?? null;
        if ($newExtraId && $newParentId) $updExtra->execute([$newParentId, $newExtraId]);
    }

    // ── 7. Choice link tables — every id remapped ────────────────────
    //   product_extra_parent_choices — an option gated on several parent choices
    //   product_extra_choice_bands   — a choice limited to some bands
    //   product_extra_choice_options — a choice limited to some fabrics
    if ($choicesMap) {
        $inE = implode(',', array_fill(0, count($extrasMap), '?'));
        foreach ($rowsOf("SELECT * FROM product_extra_parent_choices WHERE product_extra_id IN ($inE)", array_keys($extrasMap)) as $pc) {
            $ne = $extrasMap[(int) $pc['product_extra_id']] ?? null;
            $nc = $choicesMap[(int) $pc['product_extra_choice_id']] ?? null;
            if ($ne && $nc) $copyRow('product_extra_parent_choices', $pc, ['product_extra_id' => $ne, 'product_extra_choice_id' => $nc]);
        }
        $inC = implode(',', array_fill(0, count($choicesMap), '?'));
        try {
            foreach ($rowsOf("SELECT * FROM product_extra_choice_bands WHERE choice_id IN ($inC)", array_keys($choicesMap)) as $b) {
                $copyRow('product_extra_choice_bands', $b, ['choice_id' => $choicesMap[(int) $b['choice_id']]]);
            }
            foreach ($rowsOf("SELECT * FROM product_extra_choice_options WHERE choice_id IN ($inC)", array_keys($choicesMap)) as $o) {
                $no = $optionsMap[(int) $o['option_id']] ?? null;
                if ($no) $copyRow('product_extra_choice_options', $o, ['choice_id' => $choicesMap[(int) $o['choice_id']], 'option_id' => $no]);
            }
        } catch (PDOException $e) {
            // Scoping tables absent on an older schema — nothing to copy.
            if (!in_array($e->getCode(), ['42S02'], true)) throw $e;
        }

        // ── 8. extra_choice_price_rows — choice_id remapped ──────────
        foreach ($rowsOf("SELECT * FROM extra_choice_price_rows WHERE product_extra_choice_id IN ($inC)", array_keys($choicesMap)) as $r) {
            $copyRow('extra_choice_price_rows', $r, ['product_extra_choice_id' => $choicesMap[(int) $r['product_extra_choice_id']]]);
        }
    }

    // ── 9. price_tables + price_table_rows — system_id remapped ──────
    //
    // A NULL system_id (a "default" table not tied to a system) passes
    // through. A table on a system we didn't copy is skipped — NULL would
    // turn it into a fallback for every system, which is misleading.
    foreach ($rowsOf('SELECT * FROM price_tables WHERE product_id = ? ORDER BY id', [$sourceId]) as $pt) {
        $newSystemId = $mapSystem($pt['system_id']);
        if ($pt['system_id'] !== null && $newSystemId === null) continue;
        $newPtId = $copyRow('price_tables', $pt, ['client_id' => $clientId, 'product_id' => $newProductId, 'system_id' => $newSystemId]);
        foreach ($rowsOf('SELECT * FROM price_table_rows WHERE price_table_id = ?', [(int) $pt['id']]) as $r) {
            $copyRow('price_table_rows', $r, ['price_table_id' => $newPtId]);
        }
    }

    // ── 10. client_markups & client_discounts — system_id remapped ───
    //
    // Per (client, product, system). The new product gets its own rows so
    // future markup changes don't bleed across the two products.
    foreach (['client_markups', 'client_discounts'] as $table) {
        foreach ($rowsOf("SELECT * FROM $table WHERE client_id = ? AND product_id = ?", [$clientId, $sourceId]) as $m) {
            $newSystemId = $mapSystem($m['system_id']);
            if ($m['system_id'] !== null && $newSystemId === null) continue;
            $copyRow($table, $m, ['client_id' => $clientId, 'product_id' => $newProductId, 'system_id' => $newSystemId]);
        }
    }

    $pdo->commit();

    // Audit (outside the transaction — even if the audit insert fails,
    // the duplicate is real and shouldn't roll back).
    require_once __DIR__ . '/../../_partials/catalogue_audit.php';
    catalogue_audit_log(
        'product', $newProductId, 'duplicate',
        $newName,
        ['name' => $src['name'], 'source_id' => $sourceId],
        ['name' => $newName],
        $newProductId,
        ['source_product' => $src['name'], 'source_id' => $sourceId]
    );

    $_SESSION['flash_success'] = 'Duplicated "' . $src['name'] . '" → "' . $newName . '". '
        . 'Tweak as needed.';
    header('Location: /admin/products/edit.php?id=' . $newProductId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Product duplicate failed (source=' . $sourceId . '): ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not duplicate product: ' . user_error_text($e);
    header('Location: /admin/products/index.php');
    exit;
}
