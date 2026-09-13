<?php
declare(strict_types=1);

/**
 * "Bought-in vs made in-house" for factory lines.
 *
 * A blind is BOUGHT-IN when its product's supplier is a real third party
 * (e.g. a PF Venetian from "Hunter Douglas") — the factory orders it rather than
 * making it. It's MADE IN-HOUSE when the supplier is blank or "In House".
 *
 * The catch: the catalogue push does NOT copy a product's supplier_name onto the
 * tenant's mirrored copy (only the fabric wholesaler is copied). So the supplier
 * lives ONLY on the MASTER product. Everything factory-side must therefore read
 * the master via source_product_id — never the tenant copy's blank value.
 *
 * COALESCE(NULLIF(source_product_id,0), id) resolves to the master for a pushed
 * copy, and to the product itself for the factory's own products (no source id).
 */

/** The master-product join, given the line's product alias (default `p`). */
function bought_in_master_join(string $p = 'p', string $mp = 'mp'): string
{
    return "LEFT JOIN products {$mp} ON {$mp}.id = COALESCE(NULLIF({$p}.source_product_id,0), {$p}.id)";
}

/**
 * SQL predicate (true = MADE IN-HOUSE) on the master alias. Blank / "In House" /
 * "In-House" all count as in-house; anything else is a real supplier (bought-in).
 * Mirrors the normalisation used in change_status.php / order_suppliers.php.
 */
function bought_in_inhouse_predicate(string $mp = 'mp'): string
{
    return "({$mp}.supplier_name IS NULL OR TRIM({$mp}.supplier_name) = ''"
         . " OR LOWER(REPLACE(REPLACE({$mp}.supplier_name,' ',''),'-','')) = 'inhouse')";
}

/** SQL predicate (true = BOUGHT-IN) on the master alias. */
function bought_in_predicate(string $mp = 'mp'): string
{
    return 'NOT ' . bought_in_inhouse_predicate($mp);
}

/**
 * The third-party supplier for a single product (via its master), or '' when the
 * product is made in-house. Used for per-line checks in PHP.
 */
function bought_in_supplier_for_product(PDO $pdo, int $productId): string
{
    if ($productId <= 0) return '';
    try {
        $st = $pdo->prepare(
            'SELECT mp.supplier_name
               FROM products p
               ' . bought_in_master_join('p', 'mp') . '
              WHERE p.id = ? LIMIT 1'
        );
        $st->execute([$productId]);
        $sup = trim((string) ($st->fetchColumn() ?: ''));
        if ($sup === '') return '';
        if (strtolower(preg_replace('/[\s\-]+/', '', $sup)) === 'inhouse') return '';
        return $sup;
    } catch (Throwable $e) {
        return '';
    }
}
