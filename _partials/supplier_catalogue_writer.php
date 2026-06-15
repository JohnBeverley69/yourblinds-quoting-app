<?php
declare(strict_types=1);

/**
 * Supplier catalogue writer — turns a parsed supplier price list (the read-only
 * output of supplier_read_catalogue()) into real products + price tables on the
 * master/source tenant, prefixed for the supplier.
 *
 * Scope (deliberate): it imports the WIDTH × DROP band GRIDS only — the heavy,
 * error-prone part. Each worksheet becomes a product named "<prefix> <sheet>",
 * and each band becomes a price_table (system_id NULL) full of price cells.
 * Systems and fabrics are NOT in a price sheet, so they stay a manual finishing
 * step in Products afterwards.
 *
 * Idempotent: products are matched by name, price tables by (product, band),
 * cells upserted by (table, width, drop) — re-importing an updated sheet
 * refreshes prices without duplicating anything. Each product is its own
 * transaction so one bad sheet can't poison the rest.
 *
 * Writes into core tables (products / price_tables / price_table_rows) that
 * predate the new-table collation default, so there's no cross-collation join
 * risk here.
 */

/**
 * @param array $parsed  output of supplier_read_catalogue() — ['products'=>[...], ...]
 * @return array summary with counts, per-product detail and any errors.
 */
function supplier_import_to_catalogue(PDO $pdo, int $masterClientId, string $prefix, array $parsed): array
{
    $summary = [
        'products_added'     => 0,
        'products_updated'   => 0,
        'products_skipped'   => 0,
        'price_tables_added' => 0,
        'cells_written'      => 0,
        'per_product'        => [],
        'errors'             => [],
    ];

    if ($masterClientId <= 0) {
        throw new InvalidArgumentException('supplier_import_to_catalogue: no master tenant.');
    }
    $prefix = trim($prefix);

    foreach (($parsed['products'] ?? []) as $sheet) {
        $sheetName = trim((string) ($sheet['name'] ?? ''));
        if ($sheetName === '') continue;

        // "<prefix> <sheet>", but don't double-prefix if the sheet already
        // carries it (e.g. a re-export named "Bev Roller").
        $productName = ($prefix !== '' && stripos($sheetName, $prefix) === 0)
            ? $sheetName
            : trim($prefix . ' ' . $sheetName);

        try {
            $pdo->beginTransaction();
            $detail = sciw_write_one_product(
                $pdo, $masterClientId, $productName, (array) ($sheet['bands'] ?? []), $summary
            );
            $pdo->commit();
            $summary['per_product'][] = ['product' => $productName] + $detail;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $summary['errors'][] = ['product' => $productName, 'message' => $e->getMessage()];
        }
    }

    return $summary;
}

/**
 * Write one product + its band price tables. Mutates $summary; returns a
 * per-product detail row.
 */
function sciw_write_one_product(
    PDO $pdo,
    int $masterClientId,
    string $productName,
    array $bands,
    array &$summary
): array {
    // ---- Product (match by name within the master tenant) ----
    $find = $pdo->prepare('SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1');
    $find->execute([$masterClientId, $productName]);
    $pid = $find->fetchColumn();

    $isNew = false;
    if ($pid === false) {
        $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?');
        $sortStmt->execute([$masterClientId]);
        $nextSort = (int) $sortStmt->fetchColumn();

        $pdo->prepare(
            'INSERT INTO products (client_id, name, option_label, sort_order, active)
             VALUES (?, ?, ?, ?, 1)'
        )->execute([$masterClientId, $productName, 'Fabric', $nextSort]);
        $pid = (int) $pdo->lastInsertId();
        $isNew = true;
        $summary['products_added']++;
    } else {
        $pid = (int) $pid;

        // Safety: if this existing product is already structured with SYSTEMS
        // (it has system-scoped price tables), the importer's system_id-NULL
        // grids don't belong to it — writing them would create parallel,
        // never-matched tables. Skip it and tell the user to import per-system
        // in Products instead.
        $sysChk = $pdo->prepare(
            'SELECT COUNT(*) FROM price_tables
              WHERE client_id = ? AND product_id = ? AND system_id IS NOT NULL'
        );
        $sysChk->execute([$masterClientId, $pid]);
        if ((int) $sysChk->fetchColumn() > 0) {
            $summary['products_skipped']++;
            return [
                'new'     => false,
                'skipped' => true,
                'reason'  => 'already set up with systems — import this one per-system in Products',
                'bands'   => 0,
                'cells'   => 0,
            ];
        }
        $summary['products_updated']++;
    }

    // ---- Price tables (one per band, system_id NULL) + cells ----
    $findPt = $pdo->prepare(
        'SELECT id FROM price_tables
          WHERE client_id = ? AND product_id = ? AND system_id IS NULL AND band_code = ? LIMIT 1'
    );
    $insPt = $pdo->prepare(
        'INSERT INTO price_tables (client_id, product_id, system_id, band_code, name, notes, active)
         VALUES (?, ?, NULL, ?, ?, NULL, 1)'
    );
    $cellUpsert = null;
    try {
        $cellUpsert = $pdo->prepare(
            'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price)
                VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)'
        );
    } catch (Throwable $e) {
        $cellUpsert = null;
    }

    $bandCount = 0;
    $cellCount = 0;
    foreach ($bands as $band) {
        $code = strtoupper(trim((string) ($band['code'] ?? '')));
        if ($code === '') continue;
        $cells = (array) ($band['cells'] ?? []);
        if (!$cells) continue;

        $findPt->execute([$masterClientId, $pid, $code]);
        $ptId = $findPt->fetchColumn();
        if ($ptId === false) {
            $insPt->execute([$masterClientId, $pid, $code, $code]);
            $ptId = (int) $pdo->lastInsertId();
            $summary['price_tables_added']++;
        } else {
            $ptId = (int) $ptId;
        }
        $bandCount++;

        foreach ($cells as $cell) {
            $w = (int) ($cell[0] ?? 0);
            $d = (int) ($cell[1] ?? 0);
            $p = (float) ($cell[2] ?? 0);
            if ($w <= 0 || $p <= 0) continue;

            if ($cellUpsert !== null) {
                try {
                    $cellUpsert->execute([$ptId, $w, $d, $p]);
                    $summary['cells_written']++;
                    $cellCount++;
                    continue;
                } catch (Throwable $e) {
                    // schema missing the UNIQUE key — fall through to manual upsert
                }
            }
            $pdo->prepare(
                'DELETE FROM price_table_rows WHERE price_table_id = ? AND width_mm = ? AND drop_mm = ?'
            )->execute([$ptId, $w, $d]);
            $pdo->prepare(
                'INSERT INTO price_table_rows (price_table_id, width_mm, drop_mm, price) VALUES (?, ?, ?, ?)'
            )->execute([$ptId, $w, $d, $p]);
            $summary['cells_written']++;
            $cellCount++;
        }
    }

    return ['new' => $isNew, 'bands' => $bandCount, 'cells' => $cellCount];
}
