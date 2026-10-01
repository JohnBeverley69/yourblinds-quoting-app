<?php
declare(strict_types=1);

/**
 * One tenant's pricing catalogue, as the offline tablet engine needs it: only
 * the tables + columns the live-price code reads (pricing_engine.php,
 * multi_fascia_pricer.php). Shared by:
 *   • offline/catalogue.php      — the tablet's own download (signed-in user)
 *   • offline/parity_sample.php  — the super-admin parity check
 *
 * An explicit column list (never SELECT *), so nothing sensitive in a wider row
 * (e.g. client_settings API keys) ever leaves. Columns that don't exist on this
 * database are simply dropped; the engine's schema probes then take the same
 * fallback path on the device.
 *
 * $withCosts = false blanks the wholesale cost columns (cost_price). They only
 * feed the cost figures, which the preview hides from non-cost-viewers anyway,
 * so the sell price is unaffected.
 */

const OFFLINE_CATALOGUE_TABLES = [
    'products'                => ['id', 'client_id', 'name', 'cost_price', 'requires_option', 'width_only', 'price_per_slat',
                                  'price_per_sqm', 'min_area_m2', 'line_charge', 'price_source', 'source_client_id',
                                  'source_product_id', 'active'],
    'product_systems'         => ['id', 'client_id', 'product_id', 'name', 'active'],
    'product_options'         => ['id', 'client_id', 'product_id', 'band_code', 'supplier_name', 'name', 'colour', 'code',
                                  'cost_price', 'active'],
    'price_tables'            => ['id', 'client_id', 'product_id', 'system_id', 'band_code', 'name', 'active'],
    'price_table_rows'        => ['id', 'price_table_id', 'width_mm', 'drop_mm', 'price'],
    'product_extras'          => ['id', 'client_id', 'product_id', 'name', 'parent_choice_id', 'length_input_label',
                                  'is_width_source', 'splits_panels', 'source_extra_id', 'active'],
    'product_extra_choices'   => ['id', 'product_extra_id', 'system_id', 'label', 'price_delta', 'price_percent',
                                  'price_per_metre', 'cost_price', 'markup_pct_override', 'per_metre_basis',
                                  'length_input_label', 'price_per_unit', 'face_value', 'source_choice_id', 'active'],
    'extra_choice_price_rows' => ['id', 'product_extra_choice_id', 'width_mm', 'price'],
    'client_settings'         => ['client_id', 'default_price_table_markup_pct', 'default_options_markup_pct'],
    'client_markups'          => ['id', 'client_id', 'product_id', 'system_id', 'markup_percent'],
    'client_discounts'        => ['id', 'client_id', 'product_id', 'system_id', 'discount_percent'],
    'trade_discounts'         => ['id', 'client_id', 'product_id', 'system_id', 'band_code', 'extra_id', 'choice_id',
                                  'discount_percent', 'active'],
    'trade_promotions'        => ['id', 'client_id', 'product_id', 'system_id', 'band_code', 'extra_id', 'choice_id',
                                  'discount_percent', 'active', 'starts_on', 'ends_on'],
    // Multi-blind fascia join gap (multi_fascia_pricer.php). A global table.
    'allowance_rows'          => ['id', 'table_name', 'key_norm', 'value'],
];

/**
 * @return array{client_id:int, factory_client_id:int, today:string, generated_at:string,
 *               version:string, schema:array, data:array}
 */
function offline_export_catalogue(PDO $pdo, int $clientId, bool $withCosts): array
{
    $c = $clientId;
    $where = [
        'products'                => ['client_id = ?', [$c]],
        'product_systems'         => ['client_id = ?', [$c]],
        'product_options'         => ['client_id = ?', [$c]],
        'price_tables'            => ['client_id = ?', [$c]],
        'price_table_rows'        => ['price_table_id IN (SELECT id FROM price_tables WHERE client_id = ?)', [$c]],
        'product_extras'          => ['client_id = ?', [$c]],
        'product_extra_choices'   => ['product_extra_id IN (SELECT id FROM product_extras WHERE client_id = ?)', [$c]],
        'extra_choice_price_rows' => ['product_extra_choice_id IN (SELECT pc.id FROM product_extra_choices pc
                                        JOIN product_extras pe ON pe.id = pc.product_extra_id WHERE pe.client_id = ?)', [$c]],
        'client_settings'         => ['client_id = ?', [$c]],
        'client_markups'          => ['client_id = ?', [$c]],
        'client_discounts'        => ['client_id = ?', [$c]],
        'trade_discounts'         => ['client_id = ?', [$c]],
        'trade_promotions'        => ['client_id IS NULL OR client_id = ?', [$c]],
        'allowance_rows'          => ["table_name = 'roller_fascia_join'", []],
    ];

    $out = [
        'client_id'         => $clientId,
        'factory_client_id' => function_exists('factory_client_id') ? factory_client_id() : 3,
        'today'             => (string) $pdo->query('SELECT CURDATE()')->fetchColumn(),
        'generated_at'      => date('c'),
        'version'           => '',
        'schema'            => [],
        'data'              => [],
    ];

    $colInfo = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    foreach (OFFLINE_CATALOGUE_TABLES as $table => $wanted) {
        $colInfo->execute([$table]);
        $have = [];
        foreach ($colInfo->fetchAll(PDO::FETCH_ASSOC) as $ci) $have[$ci['COLUMN_NAME']] = strtolower((string) $ci['DATA_TYPE']);
        if (!$have) continue;   // table not on this database — engine falls back the same way offline

        $cols = array_values(array_filter($wanted, static fn ($col) => isset($have[$col])));
        $out['schema'][$table] = [];
        foreach ($cols as $col) {
            $t = $have[$col];
            $out['schema'][$table][$col] = in_array($t, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true) ? 'INTEGER'
                : (in_array($t, ['decimal', 'float', 'double'], true) ? 'REAL' : 'TEXT');
        }
        $select = array_map(static fn ($col) => (!$withCosts && $col === 'cost_price') ? 'NULL AS `cost_price`' : "`$col`", $cols);
        [$w, $args] = $where[$table];
        $st = $pdo->prepare('SELECT ' . implode(', ', $select) . " FROM `$table` WHERE $w ORDER BY 1");
        $st->execute($args);
        // Rows as positional lists (column order = schema order) — keeps the file small.
        $out['data'][$table] = $st->fetchAll(PDO::FETCH_NUM);
    }

    // Changes whenever anything that affects a price changes (not the timestamps),
    // so the tablet can ask "is my copy current?" cheaply.
    $out['version'] = substr(sha1(json_encode([$out['schema'], $out['data'], $out['today'], $withCosts])), 0, 16);
    return $out;
}
