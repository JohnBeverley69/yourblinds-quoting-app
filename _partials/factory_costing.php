<?php
declare(strict_types=1);

/**
 * Real manufacturing cost of placed order lines — SUPER-ADMIN ONLY figures.
 *
 * Lifted out of factory/profit.php ("The Numbers") so the Factory Console
 * Dashboard and that page cost the same lines the same way. Nothing here writes,
 * and nothing here is ever stored on a tenant's order: cost is looked up on the
 * master price tables only.
 *
 * Cost comes from whichever of the two pricing models the product uses:
 *   'own'      we make it — cost is the parallel cost grid you import alongside
 *              the selling grid (price_table_rows.cost).
 *   'supplier' we buy it in — the grid IS the supplier's list price, so the cost
 *              is simply list − our buying discount.
 *
 * Honest by design:
 *   - A line we can't cost comes back with cost = null, so the caller can keep
 *     its revenue separate and the margin can't look falsely huge.
 *   - Each order size is costed at the same cell the pricing charged at — next
 *     cell up, on whichever axes the product actually uses (full grid,
 *     width-only, per-slat, or a £/m² rate).
 *   - Revenue is base_price × qty: the trade price after our buying discount,
 *     before the tenant's own markup. Options revenue (extras) is returned
 *     separately and never costed. A remake line's revenue is its line_total
 *     (the agreed charge share, £0 when free) — see the note in the loop.
 *
 * Requires pricing_engine.php and price_source.php (loaded below).
 */

require_once __DIR__ . '/pricing_engine.php';
require_once __DIR__ . '/price_source.php';

/**
 * Every factory-product line on a placed order created in [$since, $until]
 * (either bound may be null), costed. Each returned row:
 *   line_id, quote_id, quote_number, created_at, account_id, account_name,
 *   product, master_pid, source ('own'|'supplier'|''), supplier (master
 *   product's supplier_name, '' = in-house), qty, rev, extras, is_remake,
 *   cost (total for the line, or null), why (diagnostic array when $why and
 *   uncosted, else null)
 */
function fcost_lines(PDO $pdo, int $MASTER, ?string $since, ?string $until, bool $why = false): array
{
    $PLACED = ['ordered', 'fitted', 'invoiced', 'paid'];
    $inPl   = "'" . implode("','", $PLACED) . "'";

    $dateSql = '';
    $args    = [$MASTER];
    if ($since !== null) { $dateSql .= ' AND q.created_at >= ?'; $args[] = $since; }
    if ($until !== null) { $dateSql .= ' AND q.created_at <= ?'; $args[] = $until; }

    // Every Beverley-product line on a placed order, with whatever we have to point
    // the line back at the master grid: the tenant table's own identity link first,
    // its band + system name only as a last resort.
    $hasTableSrc = pe_col_exists($pdo, 'price_tables', 'source_table_id');
    $srcTableSel = $hasTableSrc ? 'tpt.source_table_id AS src_table_id,' : 'NULL AS src_table_id,';
    $hasAcct     = pe_col_exists($pdo, 'quotes', 'account_client_id');
    $acctExpr    = function_exists('ar_account_expr') ? ar_account_expr($pdo, 'q.')   // a white-label factory's order is its own account
                 : ($hasAcct ? 'COALESCE(NULLIF(q.account_client_id, 0), q.client_id)' : 'q.client_id');
    $hasRemake   = pe_col_exists($pdo, 'quotes', 'remake_of_quote_id');
    $hasSupplier = pe_col_exists($pdo, 'products', 'supplier_name');
    $sql =
        "SELECT qi.id AS line_id, qi.width_mm, qi.drop_mm, qi.quantity, qi.base_price, qi.extras_total,
                qi.line_total,
                q.id AS quote_id, q.quote_number, q.created_at, q.client_id AS quote_client_id,
                $acctExpr AS account_id, ac.company_name AS account_name,
                " . ($hasRemake ? 'q.remake_of_quote_id' : 'NULL') . " AS remake_of_quote_id,
                qi.product_name_snapshot,
                COALESCE(p.source_product_id, p.id) AS master_pid,
                " . ($hasSupplier ? 'mp.supplier_name' : 'NULL') . " AS master_supplier,
                $srcTableSel
                tpt.id AS tenant_table_id, tpt.client_id AS tenant_table_client,
                tpt.system_id AS tenant_system_id,
                tpt.band_code AS band, tsys.name AS system_name,
                qi.price_table_row_id AS line_row_id,
                qi.system_id           AS line_system_id,
                qi.system_name_snapshot AS sys_snap,
                qi.fabric_band_snapshot AS band_snap
           FROM quote_items qi
           JOIN quotes q   ON q.id = qi.quote_id
           JOIN products p ON p.id = qi.product_id
           LEFT JOIN products mp ON mp.id = COALESCE(p.source_product_id, p.id)
           LEFT JOIN clients ac  ON ac.id = $acctExpr
           LEFT JOIN price_tables tpt   ON tpt.id = qi.price_table_id
           LEFT JOIN product_systems tsys ON tsys.id = tpt.system_id
          WHERE COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
            AND q.status IN ($inPl)
            $dateSql";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    // --- Which master grid does this line price against? --------------------------
    // An order line can carry the link in several forms depending on how it was
    // raised. Try them all, best first:
    //   1. The line's table carries source_table_id — the master table's own id.
    //   2. The line's table IS a master table (the order was raised here).
    //   3. The cell it priced from names its table, even when the line didn't.
    //   4. The line's SYSTEM points at a master system (source_system_id), so the
    //      master table is (product, that system, the band it was quoted at).
    //   5. Match on the system name — the snapshot taken at order time first, then
    //      the table's live name. Fragile: a renamed system breaks it.
    //   6. The product has exactly one grid, so there is nothing to choose between.
    $byIdCache = [];
    $masterTableById = function (int $tid, int $mpid) use ($pdo, $MASTER, &$byIdCache): ?array {
        $key = $tid . '|' . $mpid;
        if (array_key_exists($key, $byIdCache)) return $byIdCache[$key];
        $q = $pdo->prepare('SELECT id, system_id FROM price_tables WHERE id = ? AND client_id = ? AND product_id = ? LIMIT 1');
        $q->execute([$tid, $MASTER, $mpid]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $byIdCache[$key] = ($row ? ['id' => (int) $row['id'], 'system_id' => $row['system_id'] !== null ? (int) $row['system_id'] : null] : null);
    };

    $nameCache = [];
    $masterTableByName = function (int $mpid, ?string $systemName, ?string $band) use ($pdo, $MASTER, &$nameCache): ?array {
        if ($systemName === null || $band === null) return null;
        $key = $mpid . '|' . $systemName . '|' . $band;
        if (array_key_exists($key, $nameCache)) return $nameCache[$key];
        $q = $pdo->prepare(
            'SELECT pt.id, pt.system_id FROM price_tables pt
               JOIN product_systems s ON s.id = pt.system_id
              WHERE pt.client_id = ? AND pt.product_id = ? AND s.name = ? AND pt.band_code = ?
              LIMIT 1'
        );
        $q->execute([$MASTER, $mpid, $systemName, $band]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $nameCache[$key] = ($row ? ['id' => (int) $row['id'], 'system_id' => $row['system_id'] !== null ? (int) $row['system_id'] : null] : null);
    };

    // The table a priced cell belongs to, and — if that table is a tenant's copy —
    // the master table behind it.
    $rowTableCache = [];
    $masterTableByRow = function (int $rowId, int $mpid) use ($pdo, $masterTableById, &$rowTableCache): ?array {
        $key = $rowId . '|' . $mpid;
        if (array_key_exists($key, $rowTableCache)) return $rowTableCache[$key];
        $q = $pdo->prepare('SELECT price_table_id FROM price_table_rows WHERE id = ? LIMIT 1');
        $q->execute([$rowId]);
        $tid = $q->fetchColumn();
        if ($tid === false || $tid === null) return $rowTableCache[$key] = null;

        $hit = $masterTableById((int) $tid, $mpid);
        if ($hit === null) {
            // A tenant's own cell — hop to the master table it was copied from.
            try {
                $s = $pdo->prepare('SELECT source_table_id FROM price_tables WHERE id = ? LIMIT 1');
                $s->execute([(int) $tid]);
                $src = $s->fetchColumn();
                if ($src) $hit = $masterTableById((int) $src, $mpid);
            } catch (Throwable $e) { /* column not migrated — nothing else to try */ }
        }
        return $rowTableCache[$key] = $hit;
    };

    // The master system behind a system on an order line — its own id if the line
    // was raised here, otherwise the source_system_id the catalogue push wrote.
    $hasSysSrc = pe_col_exists($pdo, 'product_systems', 'source_system_id');
    $sysCache  = [];
    $masterSystemOf = function (?int $lineSysId) use ($pdo, $MASTER, $hasSysSrc, &$sysCache): ?int {
        if ($lineSysId === null || $lineSysId <= 0) return null;
        if (array_key_exists($lineSysId, $sysCache)) return $sysCache[$lineSysId];
        $sysCache[$lineSysId] = null;
        $sel = $hasSysSrc ? 'id, client_id, source_system_id' : 'id, client_id';
        $q = $pdo->prepare("SELECT $sel FROM product_systems WHERE id = ? LIMIT 1");
        $q->execute([$lineSysId]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $sysCache[$lineSysId] = (int) $r['client_id'] === $MASTER
                ? (int) $r['id']
                : (!empty($r['source_system_id']) ? (int) $r['source_system_id'] : null);
        }
        return $sysCache[$lineSysId];
    };

    $bandCache = [];
    $masterTableBySystem = function (?int $lineSysId, int $mpid, ?string $band)
        use ($pdo, $MASTER, $masterSystemOf, &$bandCache): ?array {
        $masterSys = $masterSystemOf($lineSysId);
        if ($masterSys === null) return null;

        $key = $mpid . '|' . $masterSys . '|' . (string) $band;
        if (array_key_exists($key, $bandCache)) return $bandCache[$key];

        // The band the order was quoted at, if that band still exists. It often
        // doesn't: band codes get renamed on the master (STANDARD becoming Std,
        // Infusions' slat sizes becoming A/B/C/D) and the order keeps the old one.
        // So when the system has exactly ONE grid there is nothing to choose
        // between, and the band not matching is beside the point.
        $q = $pdo->prepare('SELECT id FROM price_tables WHERE client_id = ? AND product_id = ? AND system_id = ? AND band_code = ? LIMIT 1');
        $q->execute([$MASTER, $mpid, $masterSys, (string) $band]);
        $id = $q->fetchColumn();
        if ($id === false || $id === null) {
            $q = $pdo->prepare('SELECT id FROM price_tables WHERE client_id = ? AND product_id = ? AND system_id = ? LIMIT 2');
            $q->execute([$MASTER, $mpid, $masterSys]);
            $all = $q->fetchAll(PDO::FETCH_COLUMN);
            $id  = count($all) === 1 ? $all[0] : null;
        }
        return $bandCache[$key] = ($id ? ['id' => (int) $id, 'system_id' => (int) $masterSys] : null);
    };

    // A product with exactly one grid leaves nothing to choose between.
    $soleCache = [];
    $masterSoleTable = function (int $mpid) use ($pdo, $MASTER, &$soleCache): ?array {
        if (array_key_exists($mpid, $soleCache)) return $soleCache[$mpid];
        $q = $pdo->prepare('SELECT id, system_id FROM price_tables WHERE client_id = ? AND product_id = ? LIMIT 2');
        $q->execute([$MASTER, $mpid]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        return $soleCache[$mpid] = (count($rows) === 1
            ? ['id' => (int) $rows[0]['id'], 'system_id' => $rows[0]['system_id'] !== null ? (int) $rows[0]['system_id'] : null]
            : null);
    };

    $findMasterTable = function (array $ln) use (
        $MASTER, $masterTableById, $masterTableByName, $masterTableByRow, $masterTableBySystem, $masterSoleTable
    ): ?array {
        $mpid = (int) $ln['master_pid'];
        $band = $ln['band'] !== null && $ln['band'] !== '' ? (string) $ln['band'] : (string) ($ln['band_snap'] ?? '');
        $band = $band !== '' ? $band : null;

        if (!empty($ln['src_table_id'])) {
            $hit = $masterTableById((int) $ln['src_table_id'], $mpid);
            if ($hit !== null) return $hit;
        }
        if (!empty($ln['tenant_table_id']) && (int) ($ln['tenant_table_client'] ?? 0) === $MASTER) {
            return [
                'id'        => (int) $ln['tenant_table_id'],
                'system_id' => $ln['tenant_system_id'] !== null ? (int) $ln['tenant_system_id'] : null,
            ];
        }
        if (!empty($ln['line_row_id'])) {
            $hit = $masterTableByRow((int) $ln['line_row_id'], $mpid);
            if ($hit !== null) return $hit;
        }
        $hit = $masterTableBySystem(
            !empty($ln['line_system_id']) ? (int) $ln['line_system_id'] : null, $mpid, $band
        );
        if ($hit !== null) return $hit;

        foreach ([$ln['sys_snap'] ?? null, $ln['system_name'] ?? null] as $name) {
            if ($name === null || $name === '') continue;
            $hit = $masterTableByName($mpid, (string) $name, $band);
            if ($hit !== null) return $hit;
        }
        return $masterSoleTable($mpid);
    };

    // --- Master product facts: which pricing model, and which shape of grid -------
    // Schema-tolerant, same as the pricing engine: a column a migration hasn't added
    // yet simply reads as "off".
    $prodCols = ['id'];
    foreach (['price_source', 'width_only', 'price_per_slat', 'price_per_sqm', 'min_area_m2'] as $c) {
        if (pe_col_exists($pdo, 'products', $c)) $prodCols[] = $c;
    }
    $prodSt    = $pdo->prepare('SELECT ' . implode(', ', $prodCols) . ' FROM products WHERE id = ? LIMIT 1');
    $prodCache = [];
    $masterProduct = function (int $mpid) use ($prodSt, &$prodCache): array {
        if (isset($prodCache[$mpid])) return $prodCache[$mpid];
        $prodSt->execute([$mpid]);
        $r = $prodSt->fetch(PDO::FETCH_ASSOC);
        return $prodCache[$mpid] = ($r ?: []);
    };

    // Cost of one blind at the master grid, or null if we genuinely can't cost it.
    $rowCost = $pdo->prepare('SELECT cost FROM price_table_rows WHERE id = ? LIMIT 1');
    // Fallback for a patchy cost grid: the next cell up that actually carries a cost.
    $costCell = $pdo->prepare(
        'SELECT cost FROM price_table_rows
          WHERE price_table_id = ? AND width_mm >= ? AND drop_mm >= ? AND cost IS NOT NULL
          ORDER BY width_mm ASC, drop_mm ASC LIMIT 1'
    );

    $unitCost = function (int $mpid, int $tableId, ?int $sysId, int $w, int $d)
        use ($pdo, $MASTER, $masterProduct, $rowCost, $costCell): ?float {

        $prod      = $masterProduct($mpid);
        $widthOnly = (int) ($prod['width_only']    ?? 0) === 1;
        $perSlat   = !$widthOnly && (int) ($prod['price_per_slat'] ?? 0) === 1;
        $perSqm    = !$widthOnly && !$perSlat && (int) ($prod['price_per_sqm'] ?? 0) === 1;

        // The same cell the pricing engine would have charged at.
        if     ($widthOnly) $row = pe_find_matrix_row_width_only($pdo, $tableId, $w, true);
        elseif ($perSlat)   $row = pe_find_matrix_row_by_drop   ($pdo, $tableId, $d, true);
        elseif ($perSqm)    $row = pe_find_rate_per_sqm         ($pdo, $tableId);
        else                $row = pe_find_matrix_row           ($pdo, $tableId, $w, $d, true);
        if ($row === null) return null;

        if (ps_normalise($prod['price_source'] ?? null) === PRICE_SOURCE_SUPPLIER) {
            // Bought in: the grid is the supplier's list, our cost is list less the
            // buying discount we've negotiated on this product/system.
            $disc = pe_discount_for_system($pdo, $MASTER, $mpid, $sysId);
            $unit = (float) $row['price'] * (1 - $disc / 100);
        } else {
            // Ours to make: the cost grid, at the cell we priced from — falling back
            // to the next cell up that carries a cost if that one was left blank.
            $rowCost->execute([(int) $row['id']]);
            $c = $rowCost->fetchColumn();
            if ($c === null || $c === false) {
                $costCell->execute([$tableId, $w, $d]);
                $c = $costCell->fetchColumn();
            }
            if ($c === null || $c === false) return null;
            $unit = (float) $c;
        }

        // A £/m² rate is a rate, not a price — it still needs the area.
        if ($perSqm) {
            $unit *= max(($w / 1000.0) * ($d / 1000.0), (float) ($prod['min_area_m2'] ?? 0));
        }
        return $unit;
    };

    // Panels a saved line was split into — read from its saved option choices with
    // the same rule the pricing engine used (pe_panel_count). 1 = not split.
    $lineChoices = $pdo->prepare(
        'SELECT product_extra_choice_id AS choice_id FROM quote_item_extras
          WHERE quote_item_id = ? AND product_extra_choice_id IS NOT NULL'
    );
    $panelsOf = function (int $lineId) use ($pdo, $lineChoices): int {
        $lineChoices->execute([$lineId]);
        $sel = $lineChoices->fetchAll(PDO::FETCH_ASSOC);
        return $sel ? pe_panel_count($pdo, $sel) : 1;
    };

    $out = [];
    $unitMemo = [];
    foreach ($lines as $ln) {
        $qty = max(1, (int) $ln['quantity']);
        // A remake earns only what it was actually charged at, while still costing
        // what it cost to make. Its lines are COPIES of the original's
        // (rm_create_remake_order keeps base_price, extras_total and the price-table
        // pointers), so base_price * qty would book the remade blinds as a second
        // sale at full trade price. line_total is the right figure:
        // rm_create_remake_order spreads the agreed charge across the lines into
        // it, so it is £0 on a free remake and the charge share on a billed one.
        // The cost is worked out from the grid independently of revenue, so it
        // still counts — the point is to show what putting a fault right costs.
        $isRemakeLine = (int) ($ln['remake_of_quote_id'] ?? 0) > 0;
        $rev = $isRemakeLine
            ? (float) ($ln['line_total'] ?? 0)
            : (float) $ln['base_price'] * $qty;
        $mpid = (int) $ln['master_pid'];
        $prod = (string) ($ln['product_name_snapshot'] ?: 'product ' . $mpid);

        $cost = null;
        $tbl  = $findMasterTable($ln);
        if ($tbl !== null) {
            // A line split into equal panels (an option flagged "Splits the blind
            // into equal panels") was priced as panels × the cell at width ÷ panels,
            // so cost it the same way — not as one blind at the full width.
            $panels = $panelsOf((int) $ln['line_id']);
            $w = $panels > 1 ? (int) ceil((int) $ln['width_mm'] / $panels) : (int) $ln['width_mm'];
            // The same size on the same grid costs the same — a year of lines
            // repeats a lot of them, so look each one up once.
            $ck = $mpid . '|' . $tbl['id'] . '|' . (int) $tbl['system_id'] . '|' . $w . '|' . (int) $ln['drop_mm'];
            if (!array_key_exists($ck, $unitMemo)) {
                $unitMemo[$ck] = $unitCost($mpid, $tbl['id'], $tbl['system_id'], $w, (int) $ln['drop_mm']);
            }
            $cost = $unitMemo[$ck];
            if ($cost !== null) $cost *= $panels * $qty;
        }

        $whyRow = null;
        if ($cost === null && $why) {
            $whyRow = [
                'product'    => $prod,
                'size'       => (int) $ln['width_mm'] . ' × ' . (int) $ln['drop_mm'],
                'master_pid' => $mpid,
                'src_table'  => $ln['src_table_id']        !== null ? (int) $ln['src_table_id']        : null,
                'line_table' => $ln['tenant_table_id']     !== null ? (int) $ln['tenant_table_id']     : null,
                'table_owner'=> $ln['tenant_table_client'] !== null ? (int) $ln['tenant_table_client'] : null,
                'line_row'   => $ln['line_row_id']    !== null ? (int) $ln['line_row_id']    : null,
                'line_sys'   => $ln['line_system_id'] !== null ? (int) $ln['line_system_id'] : null,
                'master_sys' => $masterSystemOf(!empty($ln['line_system_id']) ? (int) $ln['line_system_id'] : null),
                'system'     => $ln['system_name'] ?: ($ln['sys_snap'] ?? null),
                'band'       => $ln['band'] ?: ($ln['band_snap'] ?? null),
                'master_tbl' => $tbl['id'] ?? null,
                'source'     => $tbl !== null ? ps_normalise($masterProduct($mpid)['price_source'] ?? null) : null,
                'reason'     => $tbl === null
                    ? 'no master price table found for this line'
                    : 'master table found, but no priced cell at this size (or no cost in the grid)',
            ];
        }

        $own = (int) $ln['quote_client_id'] === $MASTER && (int) $ln['account_id'] === $MASTER;
        $out[] = [
            'line_id'      => (int) $ln['line_id'],
            'quote_id'     => (int) $ln['quote_id'],
            'quote_number' => (string) $ln['quote_number'],
            'created_at'   => (string) $ln['created_at'],
            'account_id'   => $own ? 0 : (int) $ln['account_id'],
            'account_name' => $own ? 'One-off sales' : (string) ($ln['account_name'] ?? ('Account ' . $ln['account_id'])),
            'product'      => $prod,
            'master_pid'   => $mpid,
            'source'       => ps_normalise($masterProduct($mpid)['price_source'] ?? null),
            'supplier'     => trim((string) ($ln['master_supplier'] ?? '')),
            'qty'          => $qty,
            'rev'          => $rev,
            // Options on a remake are inherited from the original, so they are not
            // extra revenue either.
            'extras'       => $isRemakeLine ? 0.0 : (float) ($ln['extras_total'] ?? 0) * $qty,
            'is_remake'    => $isRemakeLine,
            'cost'         => $cost,
            'why'          => $whyRow,
        ];
    }
    return $out;
}
