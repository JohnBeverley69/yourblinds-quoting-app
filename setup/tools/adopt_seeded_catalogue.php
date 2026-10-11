<?php
declare(strict_types=1);

/**
 * Link a company's copied products back to the factory catalogue.
 *
 * Companies set up with "seed catalogue" before 2026-10-11 got a copy with no
 * "came from" markers (products.source_client_id / source_product_id), so:
 *   - Trade account → discounts said "This account has no products yet";
 *   - a push refused them ("already has its own product called …");
 *   - the factory treated them as the company's own products.
 * The new-company copy now runs the push itself (seed_client_from_template.php).
 * This mends the ones made before.
 *
 * For one company it marks each product whose name EXACTLY matches a ready
 * factory product as that product's copy, then runs the push over it. The push
 * links up its systems, fabrics, options and price tables, and brings it in
 * line with the factory, including trade prices on supplier price lists.
 * Products with no matching name are the company's own and are left alone.
 *
 * Super-admin, web-runnable:
 *   /setup/tools/adopt_seeded_catalogue.php                → companies that need it
 *   /setup/tools/adopt_seeded_catalogue.php?client=ID      → what it would do
 *   /setup/tools/adopt_seeded_catalogue.php?client=ID&apply=1
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
require_once dirname(__DIR__, 2) . '/_partials/catalogue_push.php';
require_once dirname(__DIR__, 2) . '/_partials/product_readiness.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$facId    = (int) (function_exists('factory_client_id') ? factory_client_id() : 3);
$clientId = (int) ($_GET['client'] ?? 0);
$apply    = !empty($_GET['apply']);

// The factory's ready products, by exact name, with the columns the push reads.
$flagCols = [];
foreach (['requires_option', 'width_only', 'price_per_slat', 'price_per_sqm',
          'min_area_m2', 'show_colour_field', 'band_label', 'band_start_first', 'line_charge'] as $col) {
    if (pp_column_exists($pdo, 'products', $col)) $flagCols[] = $col;
}
$ready = [];
foreach (pr_products($facId) as $p) if (pr_is_ready($p)) $ready[(int) $p['id']] = true;
$st = $pdo->prepare('SELECT ' . implode(', ', array_merge(['id', 'name', 'option_label', 'sort_order', 'active'], $flagCols))
                  . ' FROM products WHERE client_id = ?');
$st->execute([$facId]);
$master = [];   // name => row
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($ready[(int) $r['id']])) $master[(string) $r['name']] = $r;
}

if ($clientId <= 0) {
    $rows = $pdo->prepare(
        "SELECT p.client_id, c.company_name, p.name FROM products p JOIN clients c ON c.id = p.client_id
          WHERE p.client_id <> ? AND (p.source_client_id IS NULL OR p.source_client_id = 0)
       ORDER BY c.company_name, p.name"
    );
    $rows->execute([$facId]);
    $by = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($master[$r['name']])) continue;
        $k = (int) $r['client_id'];
        $by[$k] ??= ['name' => $r['company_name'], 'n' => 0];
        $by[$k]['n']++;
    }
    if (!$by) { echo "No company has unlinked copies of factory products.\n"; exit; }
    echo "Companies with copied factory products that aren't linked:\n\n";
    foreach ($by as $id => $b) echo "  client $id · {$b['name']} — {$b['n']} product(s)   → ?client=$id\n";
    exit;
}

$name = $pdo->prepare('SELECT company_name FROM clients WHERE id = ?');
$name->execute([$clientId]);
echo 'Company: ' . ($name->fetchColumn() ?: '(unknown)') . " (client $clientId)\n\n";

$own = $pdo->prepare(
    'SELECT id, name FROM products WHERE client_id = ? AND (source_client_id IS NULL OR source_client_id = 0) ORDER BY name'
);
$own->execute([$clientId]);
$stamp = $pdo->prepare('UPDATE products SET source_client_id = ?, source_product_id = ? WHERE id = ? AND client_id = ?');

$done = 0; $left = 0; $failed = 0;
foreach ($own->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $m = $master[(string) $p['name']] ?? null;
    if ($m === null) { echo "LEFT    {$p['name']} — no ready factory product of that name (the company's own)\n"; $left++; continue; }
    if (!$apply) { echo "WOULD   {$p['name']} → factory #{$m['id']}, then push\n"; $done++; continue; }

    $sum = array_fill_keys(['products_added', 'products_updated', 'systems_added', 'systems_removed',
        'fabrics_added', 'fabrics_updated', 'fabrics_removed', 'extras_added', 'extras_updated', 'extras_removed',
        'choices_added', 'choices_updated', 'choices_removed', 'price_tables_added', 'price_tables_removed',
        'price_table_cells', 'width_table_cells'], 0);
    $sum['errors'] = [];
    try {
        $pdo->beginTransaction();
        $stamp->execute([$facId, (int) $m['id'], (int) $p['id'], $clientId]);
        push_one_product($pdo, (int) $m['id'], $m, $facId, $clientId, $sum, $flagCols);
        $pdo->commit();
        echo "LINKED  {$p['name']} — +{$sum['fabrics_added']} fabrics, +{$sum['extras_added']} options, "
           . "+{$sum['price_tables_added']} price tables, {$sum['price_table_cells']} price cells refreshed\n";
        $done++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "FAILED  {$p['name']} — " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n$done " . ($apply ? 'linked' : 'to link') . ", $left left as the company's own"
   . ($apply ? ", $failed failed" : '') . ".\n";
if (!$apply && $done) echo "Run again with &apply=1 to make the changes.\n";
