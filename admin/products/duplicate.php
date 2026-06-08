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
 *   product_options                — fabrics (no FK chain, straight copy)
 *   product_extras                 — options, parent_choice_id NULL first
 *   product_extra_choices          — choices, remapped to new extras + systems
 *   product_extras.parent_choice_id — patched up after choices exist
 *   product_extra_parent_choices   — choice cascade table (both ids remapped)
 *   price_tables                   — system_id remapped
 *   price_table_rows               — price_table_id remapped
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

if ($sourceId <= 0) {
    $_SESSION['flash_error'] = 'No product specified to duplicate.';
    header('Location: /admin/products/index.php');
    exit;
}

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // ── Verify the source belongs to this tenant ─────────────────────
    //
    // Tenant scoping is enforced once here at the gate, then trusted
    // for every subsequent SELECT (which all filter by source product
    // id, which we've already verified is in this tenant's client_id).
    // Optional product columns that vary by schema age. Probe once and
    // reuse for BOTH the source SELECT and the INSERT, so a duplicate
    // keeps the original's behaviour flags. Historically only cost_price
    // was carried — the behaviour flags were NOT copied, so a duplicated
    // headrail silently reverted to a normal width×drop product
    // (width_only lost) and started showing a drop again. That's the bug
    // this fixes.
    //   cost_price        — nullable trade cost
    //   requires_option   — 0 = no-fabric line (headrail/track/spares)
    //   width_only        — 1 = priced on width alone (no drop)
    //   price_per_slat    — 1 = width→rate table, price = rate × drop
    //   show_colour_field — 1 = show the dedicated Colour sub-field
    //   band_label        — per-product label for the band/range field
    $optionalCols = [];
    foreach (['cost_price', 'requires_option', 'width_only',
              'price_per_slat', 'show_colour_field', 'band_label'] as $col) {
        try {
            $pdo->query("SELECT $col FROM products LIMIT 1");
            $optionalCols[] = $col;
        } catch (Throwable $e) {
            // column not present on this schema — skip it everywhere
        }
    }

    $srcCols = array_merge(['id', 'name', 'option_label', 'sort_order', 'active'], $optionalCols);
    $st = $pdo->prepare(
        'SELECT ' . implode(', ', $srcCols) . '
           FROM products
          WHERE id = ? AND client_id = ?
          LIMIT 1'
    );
    $st->execute([$sourceId, $clientId]);
    $src = $st->fetch();
    if (!$src) {
        throw new RuntimeException('Product not found (or belongs to a different tenant).');
    }

    $pdo->beginTransaction();

    // ── 1. products row ──────────────────────────────────────────────
    //
    // Name = "Original (copy)". If a previous copy exists we just stack
    // another " (copy)" on — keeps the rule trivial. The sort_order is
    // bumped past the current max so the new product sits at the end,
    // ready to be dragged into place.
    $maxSortRow = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), 0) FROM products WHERE client_id = ?'
    );
    $maxSortRow->execute([$clientId]);
    $newSort = ((int) $maxSortRow->fetchColumn()) + 1;

    $newName = $src['name'] . ' (copy)';

    // Build the INSERT from the fixed columns plus whichever optional
    // columns exist — values copied straight from the source row, except
    // name (suffixed " (copy)") and sort_order (recalculated to last).
    $insCols = ['client_id', 'name', 'option_label', 'sort_order', 'active'];
    $insVals = [
        $clientId,
        $newName,
        (string) ($src['option_label'] ?? ''),
        $newSort,
        (int) $src['active'],
    ];
    foreach ($optionalCols as $col) {
        $insCols[] = $col;
        $insVals[] = $src[$col];  // copy as-is (nullable + int both fine via PDO)
    }
    $ins = $pdo->prepare(
        'INSERT INTO products (' . implode(', ', $insCols) . ')
          VALUES (' . implode(', ', array_fill(0, count($insCols), '?')) . ')'
    );
    $ins->execute($insVals);

    $newProductId = (int) $pdo->lastInsertId();

    // ── 2. product_systems ───────────────────────────────────────────
    //
    // Build systemsMap[old_id] = new_id so anything referencing a
    // system_id later (choices, price tables, markups) can remap.
    $systemsMap = [];
    $srcSystems = $pdo->prepare(
        'SELECT id, name, sort_order, active
           FROM product_systems
          WHERE product_id = ?'
    );
    $srcSystems->execute([$sourceId]);
    $insSys = $pdo->prepare(
        'INSERT INTO product_systems
          (client_id, product_id, name, sort_order, active)
          VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($srcSystems->fetchAll() as $s) {
        $insSys->execute([
            $clientId, $newProductId,
            (string) $s['name'], (int) $s['sort_order'], (int) $s['active'],
        ]);
        $systemsMap[(int) $s['id']] = (int) $pdo->lastInsertId();
    }

    // ── 3. product_options (fabrics) ─────────────────────────────────
    //
    // No FK chain inside the catalogue, just a straight copy.
    $srcFab = $pdo->prepare(
        'SELECT band_code, supplier_name, name, colour, code, sort_order, active
           FROM product_options
          WHERE product_id = ?'
    );
    $srcFab->execute([$sourceId]);
    $insFab = $pdo->prepare(
        'INSERT INTO product_options
          (client_id, product_id, band_code, supplier_name,
           name, colour, code, sort_order, active)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($srcFab->fetchAll() as $f) {
        $insFab->execute([
            $clientId, $newProductId,
            $f['band_code'], $f['supplier_name'],
            $f['name'], $f['colour'], $f['code'],
            (int) $f['sort_order'], (int) $f['active'],
        ]);
    }

    // ── 4. product_extras (options) — pass 1: parent_choice_id NULL ─
    //
    // Chicken-and-egg: extras can reference a parent choice on the
    // same product, but choices reference extras. We insert extras
    // first with parent_choice_id=NULL, build choices (which now have
    // valid extra ids), then patch up parent_choice_id in a second
    // pass.
    $extrasMap = [];
    $srcExtras = $pdo->prepare(
        'SELECT id, parent_choice_id, name, is_required,
                length_input_label, allow_multi, sort_order, active
           FROM product_extras
          WHERE product_id = ?'
    );
    $srcExtras->execute([$sourceId]);
    $srcExtrasRows = $srcExtras->fetchAll();

    // Probe for optional columns — some are post-launch additions and
    // older schemas may be missing them. Build the INSERT dynamically.
    $extrasCols = ['client_id', 'product_id', 'parent_choice_id', 'name',
                   'is_required', 'sort_order', 'active'];
    $extrasOptionalCols = ['length_input_label', 'allow_multi'];
    foreach ($extrasOptionalCols as $col) {
        try {
            $pdo->query("SELECT $col FROM product_extras LIMIT 1");
            $extrasCols[] = $col;
        } catch (Throwable $e) {
            // column not present — skip
        }
    }
    $extrasPlaceholders = implode(',', array_fill(0, count($extrasCols), '?'));
    $insExtra = $pdo->prepare(
        'INSERT INTO product_extras (' . implode(',', $extrasCols) . ')
          VALUES (' . $extrasPlaceholders . ')'
    );

    foreach ($srcExtrasRows as $e) {
        $vals = [
            $clientId, $newProductId,
            null,  // parent_choice_id — patched in pass 2
            (string) $e['name'],
            (int) ($e['is_required'] ?? 0),
            (int) ($e['sort_order'] ?? 0),
            (int) ($e['active'] ?? 1),
        ];
        if (in_array('length_input_label', $extrasCols, true)) {
            $vals[] = $e['length_input_label'] ?? null;
        }
        if (in_array('allow_multi', $extrasCols, true)) {
            $vals[] = (int) ($e['allow_multi'] ?? 0);
        }
        $insExtra->execute($vals);
        $extrasMap[(int) $e['id']] = (int) $pdo->lastInsertId();
    }

    // ── 5. product_extra_choices — remap extra_id + system_id ────────
    //
    // system_id can be NULL (a choice that applies to every system).
    $choicesMap = [];
    if ($extrasMap) {
        // per_metre_basis is optional (migrate_per_metre_basis.php). Carry it
        // through when present so a duplicated trim keeps its perimeter basis.
        $dupHasBasis = false;
        try {
            $pdo->query('SELECT per_metre_basis FROM product_extra_choices LIMIT 1');
            $dupHasBasis = true;
        } catch (Throwable $e) { /* column absent — width everywhere */ }
        $basisSel = $dupHasBasis ? ', per_metre_basis' : '';
        $basisCol = $dupHasBasis ? ', per_metre_basis' : '';
        $basisPh  = $dupHasBasis ? ', ?' : '';

        $oldExtraIds = array_keys($extrasMap);
        $in = implode(',', array_fill(0, count($oldExtraIds), '?'));
        $srcChoices = $pdo->prepare(
            "SELECT id, product_extra_id, system_id, label, image_path,
                    price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active$basisSel
               FROM product_extra_choices
              WHERE product_extra_id IN ($in)"
        );
        $srcChoices->execute($oldExtraIds);
        $insChoice = $pdo->prepare(
            "INSERT INTO product_extra_choices
              (product_extra_id, system_id, label, image_path,
               price_delta, price_percent, price_per_metre,
               is_default, sort_order, active$basisCol)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?$basisPh)"
        );
        foreach ($srcChoices->fetchAll() as $c) {
            $newExtraId  = $extrasMap[(int) $c['product_extra_id']];
            $newSystemId = null;
            if ($c['system_id'] !== null) {
                $newSystemId = $systemsMap[(int) $c['system_id']] ?? null;
                // If the choice referenced a system we didn't copy
                // (orphaned data), fall back to NULL = "any system".
            }
            $vals = [
                $newExtraId, $newSystemId,
                (string) $c['label'], $c['image_path'] ?? null,
                $c['price_delta'] ?? 0, $c['price_percent'] ?? 0,
                $c['price_per_metre'] ?? 0,
                (int) ($c['is_default'] ?? 0),
                (int) ($c['sort_order'] ?? 0),
                (int) ($c['active'] ?? 1),
            ];
            if ($dupHasBasis) $vals[] = (string) ($c['per_metre_basis'] ?? 'width');
            $insChoice->execute($vals);
            $choicesMap[(int) $c['id']] = (int) $pdo->lastInsertId();
        }
    }

    // ── 6. product_extras — pass 2: patch parent_choice_id ───────────
    //
    // Now that choices have new ids, fill in the parent pointers.
    $updExtra = $pdo->prepare(
        'UPDATE product_extras
            SET parent_choice_id = ?
          WHERE id = ?'
    );
    foreach ($srcExtrasRows as $e) {
        if ($e['parent_choice_id'] === null) continue;
        $newExtraId  = $extrasMap[(int) $e['id']] ?? null;
        $newParentId = $choicesMap[(int) $e['parent_choice_id']] ?? null;
        if ($newExtraId && $newParentId) {
            $updExtra->execute([$newParentId, $newExtraId]);
        }
    }

    // ── 7. product_extra_parent_choices — both ids remap ─────────────
    //
    // Many-to-many join: an extra can be gated on multiple parent
    // choices ("show this option only if Choice A or Choice B was
    // picked"). Both columns reference within-product rows, so both
    // remap from the maps we built above.
    if ($extrasMap && $choicesMap) {
        $oldExtraIds = array_keys($extrasMap);
        $in = implode(',', array_fill(0, count($oldExtraIds), '?'));
        $srcPC = $pdo->prepare(
            "SELECT product_extra_id, product_extra_choice_id
               FROM product_extra_parent_choices
              WHERE product_extra_id IN ($in)"
        );
        $srcPC->execute($oldExtraIds);
        $insPC = $pdo->prepare(
            'INSERT INTO product_extra_parent_choices
              (product_extra_id, product_extra_choice_id)
              VALUES (?, ?)'
        );
        foreach ($srcPC->fetchAll() as $pc) {
            $newExtraId  = $extrasMap[(int)  $pc['product_extra_id']]        ?? null;
            $newChoiceId = $choicesMap[(int) $pc['product_extra_choice_id']] ?? null;
            if ($newExtraId && $newChoiceId) {
                $insPC->execute([$newExtraId, $newChoiceId]);
            }
        }
    }

    // ── 8. price_tables — system_id remap ────────────────────────────
    //
    // system_id is nullable too (a "default" price table not tied to
    // a specific system). NULL passes through unchanged.
    $priceTablesMap = [];
    $srcPT = $pdo->prepare(
        'SELECT id, system_id, band_code, name, notes, active
           FROM price_tables
          WHERE product_id = ?'
    );
    $srcPT->execute([$sourceId]);
    $insPT = $pdo->prepare(
        'INSERT INTO price_tables
          (client_id, product_id, system_id, band_code, name, notes, active)
          VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($srcPT->fetchAll() as $pt) {
        $newSystemId = null;
        if ($pt['system_id'] !== null) {
            $newSystemId = $systemsMap[(int) $pt['system_id']] ?? null;
            // If the price table referenced a system we didn't copy,
            // skip the row entirely — a NULL system_id would make it
            // a "fallback" table for any system, which is misleading.
            if ($newSystemId === null) continue;
        }
        $insPT->execute([
            $clientId, $newProductId, $newSystemId,
            $pt['band_code'], $pt['name'], $pt['notes'],
            (int) ($pt['active'] ?? 1),
        ]);
        $priceTablesMap[(int) $pt['id']] = (int) $pdo->lastInsertId();
    }

    // ── 9. price_table_rows — price_table_id remap ───────────────────
    if ($priceTablesMap) {
        $oldPtIds = array_keys($priceTablesMap);
        $in = implode(',', array_fill(0, count($oldPtIds), '?'));
        $srcRows = $pdo->prepare(
            "SELECT price_table_id, width_mm, drop_mm, price
               FROM price_table_rows
              WHERE price_table_id IN ($in)"
        );
        $srcRows->execute($oldPtIds);
        $insRow = $pdo->prepare(
            'INSERT INTO price_table_rows
              (price_table_id, width_mm, drop_mm, price)
              VALUES (?, ?, ?, ?)'
        );
        // Could batch this, but volumes are typically <1000 cells per
        // table — a per-row prepare/execute is fast enough and easier
        // to reason about. Revisit if a tenant ever has 50k+ cells.
        foreach ($srcRows->fetchAll() as $r) {
            $newPtId = $priceTablesMap[(int) $r['price_table_id']];
            $insRow->execute([
                $newPtId, (int) $r['width_mm'], (int) $r['drop_mm'],
                $r['price'],
            ]);
        }
    }

    // ── 10. extra_choice_price_rows — choice_id remap ────────────────
    //
    // Width-only price grid per choice (used when a single option has
    // a price that varies by width — e.g. "bottom bar wrap" priced
    // per metre).
    if ($choicesMap) {
        $oldChoiceIds = array_keys($choicesMap);
        $in = implode(',', array_fill(0, count($oldChoiceIds), '?'));
        try {
            $srcEcp = $pdo->prepare(
                "SELECT product_extra_choice_id, width_mm, price
                   FROM extra_choice_price_rows
                  WHERE product_extra_choice_id IN ($in)"
            );
            $srcEcp->execute($oldChoiceIds);
            $insEcp = $pdo->prepare(
                'INSERT INTO extra_choice_price_rows
                  (product_extra_choice_id, width_mm, price)
                  VALUES (?, ?, ?)'
            );
            foreach ($srcEcp->fetchAll() as $r) {
                $newChoiceId = $choicesMap[(int) $r['product_extra_choice_id']];
                $insEcp->execute([
                    $newChoiceId, (int) $r['width_mm'], $r['price'],
                ]);
            }
        } catch (Throwable $e) {
            // extra_choice_price_rows table may not exist on older
            // schemas — skip silently.
        }
    }

    // ── 11. client_markups & client_discounts — system_id remap ──────
    //
    // These are per (client, product, system). The new product needs
    // its own set of rows so future markup changes don't bleed across
    // the two products. system_id can be NULL (a global product markup
    // not specific to a system) — passes through.
    foreach (['client_markups' => 'markup_percent',
              'client_discounts' => 'discount_percent'] as $table => $pctCol) {
        try {
            $srcM = $pdo->prepare(
                "SELECT system_id, $pctCol
                   FROM $table
                  WHERE client_id = ? AND product_id = ?"
            );
            $srcM->execute([$clientId, $sourceId]);
            $insM = $pdo->prepare(
                "INSERT INTO $table
                  (client_id, product_id, system_id, $pctCol)
                  VALUES (?, ?, ?, ?)"
            );
            foreach ($srcM->fetchAll() as $m) {
                $newSystemId = null;
                if ($m['system_id'] !== null) {
                    $newSystemId = $systemsMap[(int) $m['system_id']] ?? null;
                    if ($newSystemId === null) continue;
                }
                $insM->execute([
                    $clientId, $newProductId, $newSystemId,
                    $m[$pctCol],
                ]);
            }
        } catch (Throwable $e) {
            // Table missing on older schemas — skip.
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
    $_SESSION['flash_error'] = 'Could not duplicate product: ' . $e->getMessage();
    header('Location: /admin/products/index.php');
    exit;
}
