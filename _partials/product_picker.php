<?php
declare(strict_types=1);

/**
 * Shared product-picker helpers — used by the quote builder and InstaPrice so
 * the product dropdown groups products under their category headings
 * (<optgroup>) consistently. Category support is optional (probed), so this
 * degrades to a flat list before migrate_product_categories.php has run.
 */

if (function_exists('product_picker_products')) {
    return;
}

/** Are product categories available on this schema? Cached per request. */
function pp_categories_available(): bool
{
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        db()->query('SELECT 1 FROM product_categories LIMIT 0');
        $r = db()->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
                AND COLUMN_NAME = 'category_id' LIMIT 1"
        );
        $cache = $r->fetchColumn() !== false;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

/**
 * Active products for a tenant, ordered for a grouped picker: by category
 * (category order, then name), ungrouped last. Each row: id, name,
 * category_name (null = ungrouped).
 */
function product_picker_products(int $clientId): array
{
    if (pp_categories_available()) {
        $sql = 'SELECT p.id, p.name, c.name AS category_name, c.sort_order AS category_sort
                  FROM products p
             LEFT JOIN product_categories c ON c.id = p.category_id AND c.client_id = p.client_id
                 WHERE p.client_id = ? AND p.active = 1
              ORDER BY (c.sort_order IS NULL), c.sort_order, c.name, p.sort_order, p.name';
    } else {
        $sql = 'SELECT p.id, p.name, NULL AS category_name, NULL AS category_sort
                  FROM products p
                 WHERE p.client_id = ? AND p.active = 1
              ORDER BY p.sort_order, p.name';
    }
    $st = db()->prepare($sql);
    $st->execute([$clientId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build the <option>/<optgroup> HTML for a product picker. If no products
 * carry a category, returns a flat option list (no optgroups). Otherwise each
 * category becomes an <optgroup>, with any ungrouped products under "Other".
 * Does NOT emit the leading placeholder option — the caller owns that.
 */
function product_picker_options_html(array $products, int $selectedId = 0): string
{
    $anyCat = false;
    foreach ($products as $p) {
        if (!empty($p['category_name'])) { $anyCat = true; break; }
    }

    $html = '';
    if (!$anyCat) {
        foreach ($products as $p) {
            $sel = ((int) $p['id'] === $selectedId) ? ' selected' : '';
            $html .= '<option value="' . (int) $p['id'] . '"' . $sel . '>' . e((string) $p['name']) . '</option>';
        }
        return $html;
    }

    $curGroup = "\0";   // sentinel so the first row always opens a group
    $open = false;
    foreach ($products as $p) {
        $g = !empty($p['category_name']) ? (string) $p['category_name'] : '';
        if ($g !== $curGroup) {
            if ($open) $html .= '</optgroup>';
            $html .= '<optgroup label="' . e($g !== '' ? $g : 'Other') . '">';
            $open = true;
            $curGroup = $g;
        }
        $sel = ((int) $p['id'] === $selectedId) ? ' selected' : '';
        $html .= '<option value="' . (int) $p['id'] . '"' . $sel . '>' . e((string) $p['name']) . '</option>';
    }
    if ($open) $html .= '</optgroup>';
    return $html;
}
