<?php
declare(strict_types=1);

/**
 * Push a master tenant's prefixed products into a client tenant.
 *
 * Design rules (agreed with the boss):
 *
 *   - Only products whose name starts with $prefix (default 'Beverley')
 *     are pushed. The prefix is the boundary between "managed by us"
 *     and "client's own product" — anything not prefixed never gets
 *     touched on the client side.
 *
 *   - Matching across tenants is done by NAME within scope:
 *       products            : by name (exact)
 *       product_systems     : by name within the product
 *       product_options     : by (band_code, name, colour) within product
 *       product_extras      : by name within product
 *       product_extra_choices : by label within extra
 *
 *   - ADDITIVE for items: products / systems / options / extras /
 *     choices are added if missing, updated in place if matched. We
 *     NEVER delete items on the client — they may have quotes that
 *     reference them, and the snapshot fields on quote_items mean
 *     historic data is safe regardless.
 *
 *   - MERGE for price grids (by width × drop or width-only): master's
 *     cell wins where it overlaps; client's extra cells (e.g. sizes
 *     beyond master's range) are kept. Same rule for width-table
 *     pricing on extra choices.
 *
 *   - client_markups and client_discounts are NEVER touched. Pricing
 *     strategy is each tenant's own concern.
 *
 *   - Each (master product → client) sync runs in its own transaction
 *     so a failure on one product doesn't poison the others.
 *
 * Returns a per-tenant summary so the UI can show "23 new fabrics
 * added, 4 price grids refreshed" etc.
 */

function push_catalogue_to_client(
    PDO $pdo,
    int $sourceClientId,
    int $targetClientId,
    string $prefix = 'Beverley'
): array {
    if ($sourceClientId <= 0 || $targetClientId <= 0 || $sourceClientId === $targetClientId) {
        throw new InvalidArgumentException('push_catalogue_to_client: invalid client ids.');
    }
    if (trim($prefix) === '') {
        throw new InvalidArgumentException('push_catalogue_to_client: prefix is empty.');
    }

    $summary = [
        'products_added'         => 0,
        'products_updated'       => 0,
        'systems_added'          => 0,
        'fabrics_added'          => 0,
        'fabrics_updated'        => 0,
        'extras_added'           => 0,
        'extras_updated'         => 0,
        'choices_added'          => 0,
        'choices_updated'        => 0,
        'price_tables_added'     => 0,
        'price_table_cells'      => 0,
        'width_table_cells'      => 0,
        'errors'                 => [],
    ];

    // Behaviour flags that vary by schema age. Probe once so a pushed
    // product carries the same pricing behaviour as the master — without
    // this, a pushed headrail lost width_only (and friends) and started
    // showing a drop on the target tenant. Empty on older schemas.
    $flagCols = [];
    foreach (['requires_option', 'width_only', 'price_per_slat',
              'show_colour_field', 'band_label', 'cost_price'] as $col) {
        try {
            $pdo->query("SELECT $col FROM products LIMIT 1");
            $flagCols[] = $col;
        } catch (Throwable $e) {
            // column not present on this schema — skip it everywhere
        }
    }

    // Pull all source products that start with the prefix. LIKE with
    // an escaped trailing % matches anything beginning with the
    // (possibly user-supplied) prefix.
    $selCols = array_merge(['id', 'name', 'option_label', 'sort_order', 'active'], $flagCols);
    $sel = $pdo->prepare(
        'SELECT ' . implode(', ', $selCols) . '
           FROM products
          WHERE client_id = ?
            AND name LIKE ?
       ORDER BY id'
    );
    $sel->execute([$sourceClientId, $prefix . '%']);
    $sourceProducts = $sel->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sourceProducts as $sp) {
        try {
            $pdo->beginTransaction();
            push_one_product($pdo, (int) $sp['id'], $sp, $sourceClientId, $targetClientId, $summary, $flagCols);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $summary['errors'][] = [
                'product' => (string) $sp['name'],
                'message' => $e->getMessage(),
            ];
        }
    }

    return $summary;
}

/**
 * Sync one source product into the target tenant. Wraps every child
 * sync in its own helper for readability. Mutates $summary in place.
 */
function push_one_product(
    PDO $pdo,
    int $sourceProductId,
    array $sourceProduct,
    int $sourceClientId,
    int $targetClientId,
    array &$summary,
    array $flagCols = []
): void {
    // Behaviour flags present on the source row (subset of $flagCols that
    // actually came back in the SELECT). Carried onto the target so a
    // pushed product prices the same way as the master.
    $flags = array_values(array_filter(
        $flagCols,
        static fn ($c) => array_key_exists($c, $sourceProduct)
    ));

    // ---- 1. The product itself ----
    $tgtPid = pp_find_product_by_name($pdo, $targetClientId, (string) $sourceProduct['name']);
    if ($tgtPid === null) {
        // New product on the target.
        $sortStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'
        );
        $sortStmt->execute([$targetClientId]);
        $nextSort = (int) $sortStmt->fetchColumn();

        $insCols = ['client_id', 'name', 'option_label', 'sort_order', 'active'];
        $insVals = [
            $targetClientId,
            (string) $sourceProduct['name'],
            (string) ($sourceProduct['option_label'] ?? 'Fabric'),
            $nextSort,
            (int) ($sourceProduct['active'] ?? 1),
        ];
        foreach ($flags as $col) {
            $insCols[] = $col;
            $insVals[] = $sourceProduct[$col];  // copy as-is
        }
        $ins = $pdo->prepare(
            'INSERT INTO products (' . implode(', ', $insCols) . ')
             VALUES (' . implode(', ', array_fill(0, count($insCols), '?')) . ')'
        );
        $ins->execute($insVals);
        $tgtPid = (int) $pdo->lastInsertId();
        $summary['products_added']++;
    } else {
        // Update non-name fields on the existing product. NAME is the
        // match key so we deliberately don't update it. Behaviour flags
        // are kept in sync with the master so a corrected flag propagates.
        $setCols = ['option_label = ?', 'active = ?'];
        $setVals = [
            (string) ($sourceProduct['option_label'] ?? 'Fabric'),
            (int) ($sourceProduct['active'] ?? 1),
        ];
        foreach ($flags as $col) {
            $setCols[] = "$col = ?";
            $setVals[] = $sourceProduct[$col];
        }
        $setVals[] = $tgtPid;
        $setVals[] = $targetClientId;
        $upd = $pdo->prepare(
            'UPDATE products SET ' . implode(', ', $setCols) . '
              WHERE id = ? AND client_id = ?'
        );
        $upd->execute($setVals);
        $summary['products_updated']++;
    }

    // ---- 2. Systems (need this BEFORE fabrics so price tables can
    //         find their system + band match key). systemMap goes
    //         source_id => target_id for downstream use. ----
    $systemMap = pp_sync_systems($pdo, $sourceClientId, $sourceProductId, $targetClientId, $tgtPid, $summary);

    // ---- 3. Fabrics / options ----
    pp_sync_options(
        $pdo, $sourceClientId, $sourceProductId, $targetClientId, $tgtPid,
        $systemMap, $summary
    );

    // ---- 4. Extras + choices + width-table cells.
    //         The 2-pass parent_choice_id wiring matches what the
    //         seed function does. ----
    [$extraMap, $choiceMap] = pp_sync_extras_and_choices(
        $pdo, $sourceClientId, $sourceProductId, $targetClientId, $tgtPid,
        $systemMap, $summary
    );
    pp_wire_extra_parent_choices($pdo, $sourceClientId, $sourceProductId, $targetClientId, $extraMap, $choiceMap);

    // ---- 5. Price tables + cells (merge by width × drop) ----
    pp_sync_price_tables($pdo, $sourceClientId, $sourceProductId, $targetClientId, $tgtPid, $systemMap, $summary);
}

// ---------------------------------------------------------------------
// Lookup helpers
// ---------------------------------------------------------------------

function pp_find_product_by_name(PDO $pdo, int $clientId, string $name): ?int
{
    $st = $pdo->prepare(
        'SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1'
    );
    $st->execute([$clientId, $name]);
    $id = $st->fetchColumn();
    return $id !== false ? (int) $id : null;
}

// ---------------------------------------------------------------------
// Systems
// ---------------------------------------------------------------------

function pp_sync_systems(
    PDO   $pdo,
    int   $sourceClientId,
    int   $sourceProductId,
    int   $targetClientId,
    int   $targetProductId,
    array &$summary
): array {
    $systemMap = [];
    $src = $pdo->prepare(
        'SELECT id, name, sort_order, active, is_default
           FROM product_systems WHERE client_id = ? AND product_id = ? ORDER BY id'
    );
    $src->execute([$sourceClientId, $sourceProductId]);

    $find = $pdo->prepare(
        'SELECT id FROM product_systems
          WHERE client_id = ? AND product_id = ? AND name = ? LIMIT 1'
    );

    foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $find->execute([$targetClientId, $targetProductId, (string) $r['name']]);
        $tgtId = $find->fetchColumn();
        if ($tgtId === false) {
            // is_default: never let our push silently flip an existing
            // tenant's default. For NEW systems on the target, copy the
            // source's value — they're starting fresh anyway.
            $ins = $pdo->prepare(
                'INSERT INTO product_systems
                   (client_id, product_id, name, sort_order, active, is_default)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $targetClientId, $targetProductId,
                (string) $r['name'],
                (int) ($r['sort_order'] ?? 0),
                (int) ($r['active']     ?? 1),
                (int) ($r['is_default'] ?? 0),
            ]);
            $tgtId = (int) $pdo->lastInsertId();
            $summary['systems_added']++;
        } else {
            $tgtId = (int) $tgtId;
            // Update non-name fields. Skip is_default — the tenant may
            // have set their own preference.
            $pdo->prepare(
                'UPDATE product_systems SET sort_order = ?, active = ? WHERE id = ?'
            )->execute([
                (int) ($r['sort_order'] ?? 0),
                (int) ($r['active']     ?? 1),
                $tgtId,
            ]);
        }
        $systemMap[(int) $r['id']] = $tgtId;
    }

    return $systemMap;
}

// ---------------------------------------------------------------------
// Fabrics / options
// ---------------------------------------------------------------------

function pp_sync_options(
    PDO   $pdo,
    int   $sourceClientId,
    int   $sourceProductId,
    int   $targetClientId,
    int   $targetProductId,
    array $systemMap,
    array &$summary
): void {
    // Schema-aware: tenants on a target DB that hasn't run
    // migrate_option_system_scope.php yet won't have the system_id
    // column. Detect once and degrade gracefully — we'll skip the
    // scope copy entirely on those targets and behave like the
    // pre-scope version.
    $hasSystemIdCol = false;
    try {
        $colStmt = $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'product_options'
                AND COLUMN_NAME  = 'system_id'"
        );
        $hasSystemIdCol = (bool) $colStmt->fetchColumn();
    } catch (Throwable $e) { /* keep false */ }

    $selectCols = $hasSystemIdCol
        ? 'id, system_id, band_code, supplier_name, name, colour, code, sort_order, active'
        : 'id, band_code, supplier_name, name, colour, code, sort_order, active';
    $src = $pdo->prepare(
        "SELECT $selectCols
           FROM product_options
          WHERE client_id = ? AND product_id = ?
          ORDER BY id"
    );
    $src->execute([$sourceClientId, $sourceProductId]);

    // Match by the FULL unique key (band_code, supplier_name, name,
    // colour) within the product. Must match what's on
    // product_options.uniq_option_per_product — anything less risks
    // updating the wrong row when a fabric with the same band/name/
    // colour comes from multiple suppliers (same "Stratford Cream" in
    // band A from two wholesalers, very common). The original
    // 5-field find returned the wrong row, the UPDATE silently
    // corrupted its supplier_name, then the NEXT iteration's INSERT
    // tripped a duplicate-key violation against the row we'd just
    // moved out of the way.
    //
    // <=> is MySQL's null-safe equals — needed because supplier_name
    // and colour are nullable and `column = NULL` is always NULL,
    // never matched.
    $find = $pdo->prepare(
        "SELECT id FROM product_options
          WHERE client_id = ? AND product_id = ?
            AND band_code = ?
            AND (supplier_name <=> ?)
            AND name = ?
            AND (colour <=> ?)
          LIMIT 1"
    );

    foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $r) {
        // Translate the source system_id → target's via the system
        // map built during the systems pass. NULL means "universal
        // fabric" — keeps NULL on the target so it stays universal.
        // Missing map entry (system on source that wasn't present
        // on target somehow) → drop to NULL so the fabric still
        // works, just universally on the target.
        $tgtSystemId = null;
        if ($hasSystemIdCol && $r['system_id'] !== null) {
            $srcSys = (int) $r['system_id'];
            $tgtSystemId = $systemMap[$srcSys] ?? null;
        }

        $find->execute([
            $targetClientId, $targetProductId,
            (string) $r['band_code'],
            $r['supplier_name'] !== null ? (string) $r['supplier_name'] : null,
            (string) $r['name'],
            $r['colour'] !== null ? (string) $r['colour'] : null,
        ]);
        $tgtId = $find->fetchColumn();
        if ($tgtId === false) {
            // INSERT — include system_id only when the column exists.
            if ($hasSystemIdCol) {
                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                       (client_id, product_id, system_id, band_code,
                        supplier_name, name, colour, code, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $targetClientId, $targetProductId,
                    $tgtSystemId,
                    (string) $r['band_code'],
                    $r['supplier_name'] !== null ? (string) $r['supplier_name'] : null,
                    (string) $r['name'],
                    $r['colour'] !== null ? (string) $r['colour'] : null,
                    $r['code']   !== null ? (string) $r['code']   : null,
                    (int) ($r['sort_order'] ?? 0),
                    (int) ($r['active']     ?? 1),
                ]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                       (client_id, product_id, band_code, supplier_name, name, colour, code, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $targetClientId, $targetProductId,
                    (string) $r['band_code'],
                    $r['supplier_name'] !== null ? (string) $r['supplier_name'] : null,
                    (string) $r['name'],
                    $r['colour'] !== null ? (string) $r['colour'] : null,
                    $r['code']   !== null ? (string) $r['code']   : null,
                    (int) ($r['sort_order'] ?? 0),
                    (int) ($r['active']     ?? 1),
                ]);
            }
            $summary['fabrics_added']++;
        } else {
            // UPDATE — re-sync system_id too, so a re-push after the
            // user fixes scoping on the source tenant actually
            // updates the target. Otherwise existing rows would
            // stay stuck with their original (possibly wrong)
            // system_id forever.
            if ($hasSystemIdCol) {
                $pdo->prepare(
                    'UPDATE product_options
                        SET system_id = ?, supplier_name = ?, code = ?,
                            sort_order = ?, active = ?
                      WHERE id = ?'
                )->execute([
                    $tgtSystemId,
                    $r['supplier_name'] !== null ? (string) $r['supplier_name'] : null,
                    $r['code']          !== null ? (string) $r['code']          : null,
                    (int) ($r['sort_order'] ?? 0),
                    (int) ($r['active']     ?? 1),
                    (int) $tgtId,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE product_options
                        SET supplier_name = ?, code = ?, sort_order = ?, active = ?
                      WHERE id = ?'
                )->execute([
                    $r['supplier_name'] !== null ? (string) $r['supplier_name'] : null,
                    $r['code']          !== null ? (string) $r['code']          : null,
                    (int) ($r['sort_order'] ?? 0),
                    (int) ($r['active']     ?? 1),
                    (int) $tgtId,
                ]);
            }
            $summary['fabrics_updated']++;
        }
    }
}

// ---------------------------------------------------------------------
// Extras + choices + width-table cells. Returns [extraMap, choiceMap].
// ---------------------------------------------------------------------

function pp_sync_extras_and_choices(
    PDO   $pdo,
    int   $sourceClientId,
    int   $sourceProductId,
    int   $targetClientId,
    int   $targetProductId,
    array $systemMap,
    array &$summary
): array {
    $extraMap  = [];
    $choiceMap = [];

    // What columns exist on product_extras? Older schemas may not have
    // length_input_label / allow_multi yet — drop them from the write
    // shape if the columns aren't there. We probe once upfront.
    $hasLengthLabel = pp_column_exists($pdo, 'product_extras', 'length_input_label');
    $hasAllowMulti  = pp_column_exists($pdo, 'product_extras', 'allow_multi');

    $extraCols = 'id, name, is_required, sort_order, active';
    if ($hasLengthLabel) $extraCols .= ', length_input_label';
    if ($hasAllowMulti)  $extraCols .= ', allow_multi';

    $src = $pdo->prepare(
        "SELECT $extraCols FROM product_extras
          WHERE client_id = ? AND product_id = ? ORDER BY id"
    );
    $src->execute([$sourceClientId, $sourceProductId]);

    $find = $pdo->prepare(
        'SELECT id FROM product_extras
          WHERE client_id = ? AND product_id = ? AND name = ? LIMIT 1'
    );

    foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $find->execute([$targetClientId, $targetProductId, (string) $r['name']]);
        $tgtId = $find->fetchColumn();

        if ($tgtId === false) {
            // INSERT — same dynamic column shape.
            $cols   = ['client_id', 'product_id', 'name', 'is_required', 'sort_order', 'active'];
            $params = [
                $targetClientId, $targetProductId,
                (string) $r['name'],
                (int)    ($r['is_required'] ?? 0),
                (int)    ($r['sort_order']  ?? 0),
                (int)    ($r['active']      ?? 1),
            ];
            if ($hasLengthLabel) {
                $cols[] = 'length_input_label';
                $params[] = $r['length_input_label'] !== null ? (string) $r['length_input_label'] : null;
            }
            if ($hasAllowMulti) {
                $cols[] = 'allow_multi';
                $params[] = (int) ($r['allow_multi'] ?? 0);
            }
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $colsSql      = implode(',', $cols);
            $pdo->prepare("INSERT INTO product_extras ($colsSql) VALUES ($placeholders)")
                ->execute($params);
            $tgtId = (int) $pdo->lastInsertId();
            $summary['extras_added']++;
        } else {
            // UPDATE — non-name fields only.
            $tgtId = (int) $tgtId;
            $sets   = ['is_required = ?', 'sort_order = ?', 'active = ?'];
            $params = [
                (int) ($r['is_required'] ?? 0),
                (int) ($r['sort_order']  ?? 0),
                (int) ($r['active']      ?? 1),
            ];
            if ($hasLengthLabel) {
                $sets[]   = 'length_input_label = ?';
                $params[] = $r['length_input_label'] !== null ? (string) $r['length_input_label'] : null;
            }
            if ($hasAllowMulti) {
                $sets[]   = 'allow_multi = ?';
                $params[] = (int) ($r['allow_multi'] ?? 0);
            }
            $params[] = $tgtId;
            $pdo->prepare(
                'UPDATE product_extras SET ' . implode(', ', $sets) . ' WHERE id = ?'
            )->execute($params);
            $summary['extras_updated']++;
        }
        $extraMap[(int) $r['id']] = $tgtId;

        // ---- Choices for this extra ----
        $choiceMapPart = pp_sync_choices(
            $pdo, $sourceClientId, (int) $r['id'], $tgtId, $systemMap, $summary
        );
        foreach ($choiceMapPart as $sCid => $tCid) {
            $choiceMap[$sCid] = $tCid;
        }
    }

    return [$extraMap, $choiceMap];
}

function pp_sync_choices(
    PDO   $pdo,
    int   $sourceClientId,
    int   $sourceExtraId,
    int   $targetExtraId,
    array $systemMap,
    array &$summary
): array {
    $map = [];

    // cost_price exists on product_extra_choices but the boss decided
    // tenants set their own costs (and the UI is currently hidden) —
    // skip it from the push payload.
    //
    // image_path IS pushed — choice thumbnails live in the shared
    // /uploads/choice-images/ directory (not tenant-scoped) so all
    // tenants can resolve the same path. Without this the client side
    // shows the choice but no thumbnail, which is what triggered the
    // "wand image doesn't show on client side" report.
    $src = $pdo->prepare(
        'SELECT id, label, system_id, price_delta, price_percent, price_per_metre,
                is_default, sort_order, active, image_path
           FROM product_extra_choices
          WHERE product_extra_id = ?
          ORDER BY id'
    );
    $src->execute([$sourceExtraId]);

    $find = $pdo->prepare(
        'SELECT id FROM product_extra_choices
          WHERE product_extra_id = ? AND label = ? LIMIT 1'
    );

    foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $find->execute([$targetExtraId, (string) $r['label']]);
        $tgtId = $find->fetchColumn();

        // Translate source's system_id → target's via the map. NULL
        // stays NULL (choice available on every system).
        $tgtSystemId = null;
        if ($r['system_id'] !== null) {
            $srcSys = (int) $r['system_id'];
            $tgtSystemId = $systemMap[$srcSys] ?? null;
            // If we couldn't map (system missing on target somehow),
            // leave NULL — degrade to "all systems" rather than crash.
        }

        $imagePath = $r['image_path'] !== null ? (string) $r['image_path'] : null;

        if ($tgtId === false) {
            $ins = $pdo->prepare(
                'INSERT INTO product_extra_choices
                   (product_extra_id, label, system_id, price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active, image_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $targetExtraId,
                (string) $r['label'],
                $tgtSystemId,
                (float) ($r['price_delta']     ?? 0),
                (float) ($r['price_percent']   ?? 0),
                (float) ($r['price_per_metre'] ?? 0),
                (int)   ($r['is_default']      ?? 0),
                (int)   ($r['sort_order']      ?? 0),
                (int)   ($r['active']          ?? 1),
                $imagePath,
            ]);
            $tgtId = (int) $pdo->lastInsertId();
            $summary['choices_added']++;
        } else {
            $tgtId = (int) $tgtId;
            // Don't update is_default — the tenant may have moved it.
            // image_path IS updated so a master-side image swap
            // propagates on the next push.
            $pdo->prepare(
                'UPDATE product_extra_choices
                    SET system_id = ?, price_delta = ?, price_percent = ?, price_per_metre = ?,
                        sort_order = ?, active = ?, image_path = ?
                  WHERE id = ?'
            )->execute([
                $tgtSystemId,
                (float) ($r['price_delta']     ?? 0),
                (float) ($r['price_percent']   ?? 0),
                (float) ($r['price_per_metre'] ?? 0),
                (int)   ($r['sort_order']      ?? 0),
                (int)   ($r['active']          ?? 1),
                $imagePath,
                $tgtId,
            ]);
            $summary['choices_updated']++;
        }
        $map[(int) $r['id']] = $tgtId;

        // Width-table cells on this choice — merge by width_mm.
        pp_sync_extra_choice_price_rows($pdo, (int) $r['id'], $tgtId, $summary);
    }

    return $map;
}

function pp_sync_extra_choice_price_rows(
    PDO   $pdo,
    int   $sourceChoiceId,
    int   $targetChoiceId,
    array &$summary
): void {
    $src = $pdo->prepare(
        'SELECT width_mm, price FROM extra_choice_price_rows
          WHERE product_extra_choice_id = ?'
    );
    $src->execute([$sourceChoiceId]);

    $upsert = $pdo->prepare(
        "INSERT INTO extra_choice_price_rows (product_extra_choice_id, width_mm, price)
            VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE price = VALUES(price)"
    );
    foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $r) {
        try {
            $upsert->execute([$targetChoiceId, (int) $r['width_mm'], (float) $r['price']]);
            $summary['width_table_cells']++;
        } catch (Throwable $e) {
            // Schema doesn't have a UNIQUE on (choice, width)? Fall
            // back to a manual upsert.
            $del = $pdo->prepare(
                'DELETE FROM extra_choice_price_rows
                  WHERE product_extra_choice_id = ? AND width_mm = ?'
            );
            $del->execute([$targetChoiceId, (int) $r['width_mm']]);
            $ins = $pdo->prepare(
                'INSERT INTO extra_choice_price_rows
                   (product_extra_choice_id, width_mm, price) VALUES (?, ?, ?)'
            );
            $ins->execute([$targetChoiceId, (int) $r['width_mm'], (float) $r['price']]);
            $summary['width_table_cells']++;
        }
    }
}

/**
 * Second-pass wiring of product_extras.parent_choice_id and the
 * many-to-many product_extra_parent_choices junction. We need both
 * extraMap and choiceMap populated first.
 */
function pp_wire_extra_parent_choices(
    PDO   $pdo,
    int   $sourceClientId,
    int   $sourceProductId,
    int   $targetClientId,
    array $extraMap,
    array $choiceMap
): void {
    // Junction table (preferred — supports multiple parents per extra).
    // Use a schema-tolerant approach: if the junction doesn't exist
    // (old install), fall back to writing the legacy parent_choice_id
    // column.
    $hasJunction = false;
    try {
        $pdo->query('SELECT 1 FROM product_extra_parent_choices LIMIT 0');
        $hasJunction = true;
    } catch (Throwable $e) {
        $hasJunction = false;
    }

    if ($hasJunction) {
        // Get all (extra, parent_choice) pairs in the source for this
        // product, mapped to target ids via the maps.
        $src = $pdo->prepare(
            'SELECT pep.product_extra_id, pep.product_extra_choice_id
               FROM product_extra_parent_choices pep
               JOIN product_extras e ON e.id = pep.product_extra_id
              WHERE e.client_id = ? AND e.product_id = ?'
        );
        $src->execute([$sourceClientId, $sourceProductId]);

        $del = $pdo->prepare(
            'DELETE FROM product_extra_parent_choices WHERE product_extra_id = ?'
        );
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO product_extra_parent_choices
               (product_extra_id, product_extra_choice_id) VALUES (?, ?)'
        );

        // Track which target extras we've already cleared so we don't
        // wipe rows we just inserted.
        $cleared = [];
        foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sExtra  = (int) $r['product_extra_id'];
            $sChoice = (int) $r['product_extra_choice_id'];
            $tExtra  = $extraMap[$sExtra]  ?? null;
            $tChoice = $choiceMap[$sChoice] ?? null;
            if ($tExtra === null || $tChoice === null) continue;
            if (!isset($cleared[$tExtra])) {
                $del->execute([$tExtra]);
                $cleared[$tExtra] = true;
            }
            $ins->execute([$tExtra, $tChoice]);
        }
    }

    // Always update the legacy parent_choice_id column too, in case
    // anything still reads it.
    $sel = $pdo->prepare(
        'SELECT id, parent_choice_id FROM product_extras
          WHERE client_id = ? AND product_id = ?'
    );
    $sel->execute([$sourceClientId, $sourceProductId]);
    $upd = $pdo->prepare('UPDATE product_extras SET parent_choice_id = ? WHERE id = ?');
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sExtra = (int) $r['id'];
        $tExtra = $extraMap[$sExtra] ?? null;
        if ($tExtra === null) continue;
        $tChoice = null;
        if ($r['parent_choice_id'] !== null) {
            $tChoice = $choiceMap[(int) $r['parent_choice_id']] ?? null;
        }
        $upd->execute([$tChoice, $tExtra]);
    }
}

// ---------------------------------------------------------------------
// Price tables — match by (system, band_code). Cells merge by
// (width_mm, drop_mm).
// ---------------------------------------------------------------------

function pp_sync_price_tables(
    PDO   $pdo,
    int   $sourceClientId,
    int   $sourceProductId,
    int   $targetClientId,
    int   $targetProductId,
    array $systemMap,
    array &$summary
): void {
    $src = $pdo->prepare(
        'SELECT id, system_id, band_code, name, notes, active
           FROM price_tables
          WHERE client_id = ? AND product_id = ?
          ORDER BY id'
    );
    $src->execute([$sourceClientId, $sourceProductId]);

    $find = $pdo->prepare(
        "SELECT id FROM price_tables
          WHERE client_id = ? AND product_id = ?
            AND (system_id <=> ?)
            AND band_code = ? LIMIT 1"
    );

    $cellSel = $pdo->prepare(
        'SELECT width_mm, drop_mm, price FROM price_table_rows WHERE price_table_id = ?'
    );
    $cellUpsert = null;
    try {
        $cellUpsert = $pdo->prepare(
            'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price)
                VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)'
        );
    } catch (Throwable $e) {
        $cellUpsert = null;   // fall back to manual upsert
    }

    foreach ($src->fetchAll(PDO::FETCH_ASSOC) as $pt) {
        // Map source system → target system. NULL stays NULL (products
        // without systems use the IS NULL bucket).
        $tgtSystemId = null;
        if ($pt['system_id'] !== null) {
            $tgtSystemId = $systemMap[(int) $pt['system_id']] ?? null;
            if ($tgtSystemId === null) continue; // can't push without a target system
        }

        $find->execute([
            $targetClientId, $targetProductId,
            $tgtSystemId,
            (string) $pt['band_code'],
        ]);
        $tgtPtId = $find->fetchColumn();

        if ($tgtPtId === false) {
            $ins = $pdo->prepare(
                'INSERT INTO price_tables
                   (client_id, product_id, system_id, band_code, name, notes, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $targetClientId, $targetProductId,
                $tgtSystemId,
                strtoupper((string) $pt['band_code']),
                $pt['name']  !== null ? (string) $pt['name']  : null,
                $pt['notes'] !== null ? (string) $pt['notes'] : null,
                (int) ($pt['active'] ?? 1),
            ]);
            $tgtPtId = (int) $pdo->lastInsertId();
            $summary['price_tables_added']++;
        } else {
            $tgtPtId = (int) $tgtPtId;
            $pdo->prepare(
                'UPDATE price_tables SET name = ?, notes = ?, active = ? WHERE id = ?'
            )->execute([
                $pt['name']  !== null ? (string) $pt['name']  : null,
                $pt['notes'] !== null ? (string) $pt['notes'] : null,
                (int) ($pt['active'] ?? 1),
                $tgtPtId,
            ]);
        }

        // Merge cells: master's price wins for matching (width, drop);
        // client's extra cells (outside master's range) are kept.
        $cellSel->execute([(int) $pt['id']]);
        foreach ($cellSel->fetchAll(PDO::FETCH_ASSOC) as $cell) {
            $w = (int) $cell['width_mm'];
            $d = (int) $cell['drop_mm'];
            $p = (float) $cell['price'];
            if ($cellUpsert !== null) {
                try {
                    $cellUpsert->execute([$tgtPtId, $w, $d, $p]);
                    $summary['price_table_cells']++;
                    continue;
                } catch (Throwable $e) {
                    // schema missing UNIQUE — fall through to manual.
                }
            }
            $pdo->prepare(
                'DELETE FROM price_table_rows
                  WHERE price_table_id = ? AND width_mm = ? AND drop_mm = ?'
            )->execute([$tgtPtId, $w, $d]);
            $pdo->prepare(
                'INSERT INTO price_table_rows
                   (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)'
            )->execute([$tgtPtId, $w, $d, $p]);
            $summary['price_table_cells']++;
        }
    }
}

// ---------------------------------------------------------------------
// Utility
// ---------------------------------------------------------------------

function pp_column_exists(PDO $pdo, string $table, string $col): bool
{
    static $cache = [];
    $key = $table . '.' . $col;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ? LIMIT 1'
        );
        $st->execute([$table, $col]);
        $exists = $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        $exists = false;
    }
    return $cache[$key] = $exists;
}
