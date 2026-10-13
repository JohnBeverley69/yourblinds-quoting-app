<?php
declare(strict_types=1);

/**
 * Give a brand-new client the template client's catalogue.
 *
 * It runs the catalogue PUSH (push_one_product in catalogue_push.php) for each
 * product, so a new company gets exactly what a push would give it. It used to
 * be a separate, simpler copy, and that twin had drifted (John, 2026-10-11):
 *   - no "came from" markers (source_client_id / source_*_id), so the factory
 *     treated the products as the company's own. Trade-account discounts said
 *     "no products yet", and a later push refused them as "already has its own
 *     product called …";
 *   - supplier price lists copied at list price, without the trade conversion
 *     the push applies;
 *   - no fabric/system scoping, choice band/fabric scoping, before-size and
 *     other option flags.
 * One path now, so they can't drift again.
 *
 * Only ACTIVE, READY-TO-QUOTE products are copied (pr_is_ready, the dashboard
 * rule). Anything still being set up reaches the company by a push later.
 *
 * Prices: the company gets OUR TRADE PRICE LIST only, the price we sell the
 * blinds to them at (John, 2026-10-13). The pushed grids already carry the
 * trade conversion for supplier products. The template's own markups and
 * discounts (client_markups / client_discounts) are NOT copied: on the factory
 * those are buying discounts and margins (e.g. 40% off Forest Wood's list), and
 * copied onto a company they became its SELLING discount, so it sold below its
 * trade cost. A company sets its own markup later if it wants one.
 *
 * Not copied: client_settings, users, feature flags, quotes/orders/customers,
 * client_markups / client_discounts.
 *
 * No transaction of its own: the caller wraps this, with the client and user
 * inserts, in one transaction so a failure rolls everything back.
 *
 * @return array{products:int,fabrics:int,systems:int,extras:int,choices:int,price_tables:int,price_table_rows:int,width_table_rows:int,skipped_not_ready:int}
 */
function seed_client_from_template(PDO $pdo, int $sourceClientId, int $newClientId): array
{
    if ($sourceClientId <= 0 || $newClientId <= 0 || $sourceClientId === $newClientId) {
        throw new InvalidArgumentException('seed_client_from_template: invalid client ids.');
    }
    require_once __DIR__ . '/catalogue_push.php';
    require_once __DIR__ . '/product_readiness.php';

    $readyIds = [];
    foreach (pr_products($sourceClientId) as $p) {
        if (pr_is_ready($p)) $readyIds[(int) $p['id']] = true;
    }

    // Same product columns and flags the push reads (push_catalogue_to_client).
    $flagCols = [];
    foreach (['requires_option', 'width_only', 'price_per_slat', 'price_per_sqm',
              'min_area_m2', 'show_colour_field', 'band_label', 'band_start_first', 'line_charge'] as $col) {
        if (pp_column_exists($pdo, 'products', $col)) $flagCols[] = $col;
    }
    $sel = $pdo->prepare(
        'SELECT ' . implode(', ', array_merge(['id', 'name', 'option_label', 'sort_order', 'active'], $flagCols)) . '
           FROM products WHERE client_id = ? ORDER BY sort_order, id'
    );
    $sel->execute([$sourceClientId]);

    // The push's own counters (it increments these, so they must exist).
    $push = array_fill_keys([
        'products_added', 'products_updated', 'systems_added', 'systems_removed',
        'fabrics_added', 'fabrics_updated', 'fabrics_removed',
        'extras_added', 'extras_updated', 'extras_removed',
        'choices_added', 'choices_updated', 'choices_removed',
        'price_tables_added', 'price_tables_removed', 'price_table_cells', 'width_table_cells',
    ], 0);
    $push['errors'] = [];
    $skipped = 0;
    foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $sp) {
        if (!isset($readyIds[(int) $sp['id']])) { $skipped++; continue; }
        // Any failure throws, and the caller's transaction undoes the whole client.
        push_one_product($pdo, (int) $sp['id'], $sp, $sourceClientId, $newClientId, $push, $flagCols);
    }

    $n = static fn (string $k): int => (int) ($push[$k] ?? 0);
    return [
        'products'          => $n('products_added'),
        'fabrics'           => $n('fabrics_added'),
        'systems'           => $n('systems_added'),
        'extras'            => $n('extras_added'),
        'choices'           => $n('choices_added'),
        'price_tables'      => $n('price_tables_added'),
        'price_table_rows'  => $n('price_table_cells'),
        'width_table_rows'  => $n('width_table_cells'),
        'skipped_not_ready' => $skipped,
    ];
}
