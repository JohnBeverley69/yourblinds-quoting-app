<?php
declare(strict_types=1);

/**
 * "Ready to quote" — the bulk, cheap version of the product readiness rules,
 * shared by the Products list (admin/products/index.php) and the dashboard's
 * Quoting readiness panel (dashboard/index.php).
 *
 * The per-product, detailed version is catalogue_validate_product() in
 * _partials/catalogue_validator.php (the edit page's health chips). The two
 * must agree on the rules and their order: systems → fabrics → price tables
 * (price tables hang off a system, so no system means nothing to price).
 * Change one, change the other.
 *
 * A product is ready when it is active, has an active price table, and — if
 * it needs a fabric (products.requires_option = 1, the default) — at least one
 * active fabric/option.
 */

/** Every product for the tenant with the counts the rules need. */
function pr_products(int $clientId, bool $withCategory = false): array
{
    $catSelect = $withCategory ? 'p.category_id,' : '';
    $st = db()->prepare(
        'SELECT p.id, p.name, p.sort_order, p.active, p.updated_at, p.requires_option,
                ' . $catSelect . '
                (SELECT COUNT(*) FROM product_options o WHERE o.product_id = p.id AND o.active = 1) AS option_count,
                (SELECT COUNT(*) FROM product_extras  e WHERE e.product_id = p.id AND e.active = 1) AS extra_count,
                (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id AND s.active = 1) AS system_count,
                (SELECT COUNT(*) FROM price_tables   t WHERE t.product_id = p.id AND t.active = 1) AS price_table_count,
                (CASE
                    WHEN (SELECT COUNT(*) FROM product_systems s WHERE s.product_id = p.id AND s.active = 1) = 1
                    THEN (SELECT s.id FROM product_systems s WHERE s.product_id = p.id AND s.active = 1 LIMIT 1)
                    ELSE NULL
                 END) AS solo_system_id
           FROM products p
          WHERE p.client_id = ?
       ORDER BY p.sort_order, p.name'
    );
    $st->execute([$clientId]);
    return $st->fetchAll();
}

function pr_needs_fabric(array $p): bool
{
    return !isset($p['requires_option']) || (int) $p['requires_option'] === 1;
}

function pr_is_ready(array $p): bool
{
    return (!pr_needs_fabric($p) || (int) $p['option_count'] > 0)
        && (int) $p['price_table_count'] > 0
        && (int) $p['active'] === 1;
}

/** What an active product still needs, in setup order: ['system', 'fabric', 'price table']. */
function pr_missing(array $p): array
{
    $missing = [];
    if ((int) $p['system_count'] === 0)                       $missing[] = 'system';
    if (pr_needs_fabric($p) && (int) $p['option_count'] === 0) $missing[] = 'fabric';
    if ((int) $p['price_table_count'] === 0)                  $missing[] = 'price table';
    return $missing;
}

/** Where to price this product: its one system's price tables, else its systems list. */
function pr_price_tables_href(array $p): string
{
    return !empty($p['solo_system_id'])
        ? '/admin/products/price-tables.php?system_id=' . (int) $p['solo_system_id']
        : '/admin/products/systems.php?product_id='     . (int) $p['id'];
}

/** The page that fixes the FIRST missing thing (same order as pr_missing()). */
function pr_fix_href(array $p): string
{
    if ((int) $p['system_count'] === 0) {
        return '/admin/products/systems.php?product_id=' . (int) $p['id'];
    }
    if (pr_needs_fabric($p) && (int) $p['option_count'] === 0) {
        return '/admin/products/options.php?product_id=' . (int) $p['id'];
    }
    return pr_price_tables_href($p);
}

/**
 * Company details a customer's quote relies on, each with whether it's set
 * and where to set it. Missing columns (old schema) count as set, so the
 * panel never nags about something the install can't hold.
 */
function pr_company_checks(int $clientId): array
{
    $c = [];
    $s = [];
    try {
        $st = db()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $st->execute([$clientId]);
        $c = $st->fetch() ?: [];
        $st = db()->prepare('SELECT * FROM client_settings WHERE client_id = ? LIMIT 1');
        $st->execute([$clientId]);
        $s = $st->fetch() ?: [];
    } catch (Throwable $e) { /* treat as unknown */ }
    $has = static fn (array $row, string $col): bool =>
        !array_key_exists($col, $row) || trim((string) $row[$col]) !== '';

    return [
        ['label' => 'Company phone number', 'ok' => $has($c, 'phone'),
         'why'   => 'printed on quotes and at the end of customer texts', 'href' => '/admin/settings.php#company'],
        ['label' => 'Company email', 'ok' => $has($c, 'email'),
         'why'   => 'customers reply to it', 'href' => '/admin/settings.php#company'],
        ['label' => 'Company address', 'ok' => $has($c, 'address1') && $has($c, 'postcode'),
         'why'   => 'printed on quotes and invoices', 'href' => '/admin/settings.php#company'],
        ['label' => 'Logo', 'ok' => $has($c, 'logo_path'),
         'why'   => 'shown at the top of every quote', 'href' => '/admin/settings.php#company'],
        ['label' => 'Bank / payment details',
         'ok'    => !array_key_exists('bank_account_number', $s)
                    || trim((string) ($s['bank_account_number'] ?? '')) !== ''
                    || trim((string) ($s['payment_instructions'] ?? '')) !== '',
         'why'   => 'so customers know how to pay', 'href' => '/admin/settings.php#quoting'],
    ];
}
