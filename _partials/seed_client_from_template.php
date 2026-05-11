<?php
declare(strict_types=1);

/**
 * Copy a template client's product catalogue into a brand-new client.
 *
 * Tables copied (in dependency order):
 *   products
 *     ↳ product_options       (fabrics)
 *     ↳ product_systems
 *     ↳ product_extras        (parent_choice_id wired in a 2nd pass)
 *         ↳ product_extra_choices
 *             ↳ extra_choice_price_rows
 *     ↳ price_tables
 *         ↳ price_table_rows
 *
 * Tables intentionally NOT copied:
 *   client_settings   — caller creates a fresh empty row for the new client.
 *   client_users      — admin creates the new client's first user separately.
 *   feature flags     — master admin enables those per-client manually.
 *   quotes / orders / customers — tenant data, never seeded.
 *
 * The function does NOT manage its own transaction — the caller is expected
 * to wrap this (and the surrounding client/user inserts) in a transaction so
 * a failure rolls everything back atomically.
 *
 * @return array{products:int,fabrics:int,systems:int,extras:int,choices:int,price_tables:int,price_table_rows:int,width_table_rows:int}
 */
function seed_client_from_template(PDO $pdo, int $sourceClientId, int $newClientId): array
{
    if ($sourceClientId <= 0 || $newClientId <= 0 || $sourceClientId === $newClientId) {
        throw new InvalidArgumentException('seed_client_from_template: invalid client ids.');
    }

    $summary = [
        'products' => 0, 'fabrics' => 0, 'systems' => 0,
        'extras'   => 0, 'choices' => 0,
        'price_tables' => 0, 'price_table_rows' => 0,
        'width_table_rows' => 0,
    ];
    $productMap    = [];
    $systemMap     = [];
    $extraMap      = [];
    $choiceMap     = [];
    $priceTableMap = [];

    // -----------------------------------------------------------------------
    // 1. products
    // -----------------------------------------------------------------------
    $sel = $pdo->prepare(
        'SELECT id, name, option_label, sort_order, active
           FROM products WHERE client_id = ? ORDER BY id'
    );
    $sel->execute([$sourceClientId]);
    $ins = $pdo->prepare(
        'INSERT INTO products (client_id, name, option_label, sort_order, active)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ins->execute([
            $newClientId, $r['name'], $r['option_label'],
            (int) $r['sort_order'], (int) $r['active'],
        ]);
        $productMap[(int) $r['id']] = (int) $pdo->lastInsertId();
        $summary['products']++;
    }

    if (!$productMap) {
        return $summary; // no catalogue at the source — done.
    }

    // 1b. client_markups / client_discounts come AFTER systems are seeded
    //     (pricing is per [product, system] now) — see step 3b below.

    // -----------------------------------------------------------------------
    // 2. product_options (fabrics)
    // -----------------------------------------------------------------------
    $sel = $pdo->prepare(
        'SELECT product_id, band_code, supplier_name, name, colour, code, sort_order, active
           FROM product_options WHERE client_id = ? ORDER BY id'
    );
    $sel->execute([$sourceClientId]);
    $ins = $pdo->prepare(
        'INSERT INTO product_options
           (client_id, product_id, band_code, supplier_name, name, colour, code, sort_order, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($productMap[(int) $r['product_id']])) continue;
        $ins->execute([
            $newClientId, $productMap[(int) $r['product_id']],
            $r['band_code'], $r['supplier_name'], $r['name'], $r['colour'], $r['code'],
            (int) $r['sort_order'], (int) $r['active'],
        ]);
        $summary['fabrics']++;
    }

    // -----------------------------------------------------------------------
    // 3. product_systems
    // -----------------------------------------------------------------------
    $sel = $pdo->prepare(
        'SELECT id, product_id, name, sort_order, active, is_default
           FROM product_systems WHERE client_id = ? ORDER BY id'
    );
    $sel->execute([$sourceClientId]);
    $ins = $pdo->prepare(
        'INSERT INTO product_systems
           (client_id, product_id, name, sort_order, active, is_default)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($productMap[(int) $r['product_id']])) continue;
        $ins->execute([
            $newClientId, $productMap[(int) $r['product_id']],
            $r['name'], (int) $r['sort_order'], (int) $r['active'], (int) $r['is_default'],
        ]);
        $systemMap[(int) $r['id']] = (int) $pdo->lastInsertId();
        $summary['systems']++;
    }

    // -----------------------------------------------------------------------
    // 3b. client_markups / client_discounts (per [product, system])
    //     Has to come AFTER systems are seeded so we can rewrite system_id
    //     through $systemMap. A NULL system_id stays NULL (products without
    //     systems). Discounts only get copied where present (zero = no row).
    // -----------------------------------------------------------------------
    $sel = $pdo->prepare(
        'SELECT product_id, system_id, markup_percent
           FROM client_markups WHERE client_id = ?'
    );
    $sel->execute([$sourceClientId]);
    $insMarkup = $pdo->prepare(
        'INSERT INTO client_markups (client_id, product_id, system_id, markup_percent)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sourceProductId = (int) $r['product_id'];
        if (!isset($productMap[$sourceProductId])) continue;
        $newSystemId = null;
        if ($r['system_id'] !== null) {
            $sid = (int) $r['system_id'];
            if (!isset($systemMap[$sid])) continue;   // source system was inactive
            $newSystemId = $systemMap[$sid];
        }
        $insMarkup->execute([
            $newClientId, $productMap[$sourceProductId],
            $newSystemId, (float) $r['markup_percent'],
        ]);
    }

    $sel = $pdo->prepare(
        'SELECT product_id, system_id, discount_percent
           FROM client_discounts WHERE client_id = ?'
    );
    $sel->execute([$sourceClientId]);
    $insDiscount = $pdo->prepare(
        'INSERT INTO client_discounts (client_id, product_id, system_id, discount_percent)
         VALUES (?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sourceProductId = (int) $r['product_id'];
        if (!isset($productMap[$sourceProductId])) continue;
        $newSystemId = null;
        if ($r['system_id'] !== null) {
            $sid = (int) $r['system_id'];
            if (!isset($systemMap[$sid])) continue;
            $newSystemId = $systemMap[$sid];
        }
        $insDiscount->execute([
            $newClientId, $productMap[$sourceProductId],
            $newSystemId, (float) $r['discount_percent'],
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. product_extras  (pass 1: parent_choice_id = NULL — wired in pass 2)
    // -----------------------------------------------------------------------
    $sel = $pdo->prepare(
        'SELECT id, product_id, name, is_required, sort_order, active
           FROM product_extras WHERE client_id = ? ORDER BY id'
    );
    $sel->execute([$sourceClientId]);
    $ins = $pdo->prepare(
        'INSERT INTO product_extras
           (client_id, product_id, parent_choice_id, name, is_required, sort_order, active)
         VALUES (?, ?, NULL, ?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($productMap[(int) $r['product_id']])) continue;
        $ins->execute([
            $newClientId, $productMap[(int) $r['product_id']],
            $r['name'], (int) $r['is_required'], (int) $r['sort_order'], (int) $r['active'],
        ]);
        $extraMap[(int) $r['id']] = (int) $pdo->lastInsertId();
        $summary['extras']++;
    }

    // -----------------------------------------------------------------------
    // 5. product_extra_choices  (system_id column is the authoritative
    //    system scope in this model — NULL = "every system", otherwise
    //    the choice is only available on the matching system. Old
    //    junction tables aren't read or written by anyone any more.)
    // -----------------------------------------------------------------------
    if ($extraMap) {
        $oldExtraIds = array_keys($extraMap);
        $ph          = implode(',', array_fill(0, count($oldExtraIds), '?'));
        $sel = $pdo->prepare(
            "SELECT id, product_extra_id, system_id, label, image_path,
                    price_delta, price_percent, price_per_metre,
                    is_default, sort_order, active
               FROM product_extra_choices
              WHERE product_extra_id IN ($ph)
              ORDER BY id"
        );
        $sel->execute($oldExtraIds);
        $ins = $pdo->prepare(
            'INSERT INTO product_extra_choices
               (product_extra_id, system_id, label, image_path,
                price_delta, price_percent, price_per_metre,
                is_default, sort_order, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // Map the source's system_id to the new client's system_id.
            // NULL stays NULL (= every system).
            $newSystemId = null;
            if ($r['system_id'] !== null) {
                $newSystemId = $systemMap[(int) $r['system_id']] ?? null;
                // If we can't map it (orphaned), fall back to "all systems"
                // rather than dropping the row — the source data may have
                // a stray reference; we'd rather over-show than under-show.
            }
            $ins->execute([
                $extraMap[(int) $r['product_extra_id']],
                $newSystemId,
                $r['label'],
                $r['image_path'],
                $r['price_delta'], $r['price_percent'], $r['price_per_metre'],
                (int) $r['is_default'], (int) $r['sort_order'], (int) $r['active'],
            ]);
            $choiceMap[(int) $r['id']] = (int) $pdo->lastInsertId();
            $summary['choices']++;
        }
    }

    // -----------------------------------------------------------------------
    // 6. product_extras  (pass 2: wire up parent_choice_id + the junction
    //    product_extra_parent_choices so multi-parent gating is preserved)
    // -----------------------------------------------------------------------
    if ($extraMap && $choiceMap) {
        // 6a. Legacy single-column parent (kept for back-compat).
        $sel = $pdo->prepare(
            'SELECT id, parent_choice_id FROM product_extras
              WHERE client_id = ? AND parent_choice_id IS NOT NULL'
        );
        $sel->execute([$sourceClientId]);
        $upd = $pdo->prepare('UPDATE product_extras SET parent_choice_id = ? WHERE id = ?');
        foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $oldExtraId  = (int) $r['id'];
            $oldChoiceId = (int) $r['parent_choice_id'];
            if (!isset($extraMap[$oldExtraId]) || !isset($choiceMap[$oldChoiceId])) continue;
            $upd->execute([$choiceMap[$oldChoiceId], $extraMap[$oldExtraId]]);
        }

        // 6b. Junction rows — the multi-parent source of truth.
        $oldExtraIds = array_keys($extraMap);
        $ph          = implode(',', array_fill(0, count($oldExtraIds), '?'));
        $sel = $pdo->prepare(
            "SELECT product_extra_id, product_extra_choice_id
               FROM product_extra_parent_choices
              WHERE product_extra_id IN ($ph)"
        );
        $sel->execute($oldExtraIds);
        $ins = $pdo->prepare(
            'INSERT INTO product_extra_parent_choices
               (product_extra_id, product_extra_choice_id) VALUES (?, ?)'
        );
        foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $newExtra  = $extraMap[(int) $r['product_extra_id']]         ?? null;
            $newChoice = $choiceMap[(int) $r['product_extra_choice_id']] ?? null;
            if ($newExtra === null || $newChoice === null) continue;
            $ins->execute([$newExtra, $newChoice]);
        }
    }

    // -----------------------------------------------------------------------
    // 7. price_tables
    // -----------------------------------------------------------------------
    $sel = $pdo->prepare(
        'SELECT id, product_id, system_id, band_code, name, notes, active
           FROM price_tables WHERE client_id = ? ORDER BY id'
    );
    $sel->execute([$sourceClientId]);
    $ins = $pdo->prepare(
        'INSERT INTO price_tables
           (client_id, product_id, system_id, band_code, name, notes, active)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($productMap[(int) $r['product_id']])) continue;
        $newSystemId = $r['system_id'] !== null
            ? ($systemMap[(int) $r['system_id']] ?? null)
            : null;
        $ins->execute([
            $newClientId, $productMap[(int) $r['product_id']],
            $newSystemId, $r['band_code'], $r['name'], $r['notes'], (int) $r['active'],
        ]);
        $priceTableMap[(int) $r['id']] = (int) $pdo->lastInsertId();
        $summary['price_tables']++;
    }

    // -----------------------------------------------------------------------
    // 8. price_table_rows
    // -----------------------------------------------------------------------
    if ($priceTableMap) {
        $oldTableIds = array_keys($priceTableMap);
        $ph          = implode(',', array_fill(0, count($oldTableIds), '?'));
        $sel = $pdo->prepare(
            "SELECT price_table_id, width_mm, drop_mm, price
               FROM price_table_rows
              WHERE price_table_id IN ($ph)"
        );
        $sel->execute($oldTableIds);
        $ins = $pdo->prepare(
            'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ins->execute([
                $priceTableMap[(int) $r['price_table_id']],
                $r['width_mm'], $r['drop_mm'], $r['price'],
            ]);
            $summary['price_table_rows']++;
        }
    }

    // -----------------------------------------------------------------------
    // 9. extra_choice_price_rows  (the 4th surcharge mode — width tables)
    // -----------------------------------------------------------------------
    if ($choiceMap) {
        $oldChoiceIds = array_keys($choiceMap);
        $ph           = implode(',', array_fill(0, count($oldChoiceIds), '?'));
        $sel = $pdo->prepare(
            "SELECT product_extra_choice_id, width_mm, price
               FROM extra_choice_price_rows
              WHERE product_extra_choice_id IN ($ph)"
        );
        $sel->execute($oldChoiceIds);
        $ins = $pdo->prepare(
            'INSERT INTO extra_choice_price_rows
               (product_extra_choice_id, width_mm, price)
             VALUES (?, ?, ?)'
        );
        foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ins->execute([
                $choiceMap[(int) $r['product_extra_choice_id']],
                $r['width_mm'], $r['price'],
            ]);
            $summary['width_table_rows']++;
        }
    }

    return $summary;
}
