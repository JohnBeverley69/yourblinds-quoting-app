<?php
declare(strict_types=1);

/**
 * Factory · Fabric search (for the order editor's fabric picker).
 *
 * Cross-tenant version of quote-builder/api/fabrics-search.php: the factory
 * edits any customer's order, so the tenant client_id is passed explicitly
 * rather than taken from the session. Searches the order's OWN catalogue
 * (product_options) scoped to product + client (+ system + band).
 *
 *   ?product_id=&client_id=   (required)
 *   &q=  &system_id=  &band=  &limit=
 *   &bands=1                  → distinct band codes for the product/system
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo       = db();
$productId = (int) ($_GET['product_id'] ?? 0);
$clientId  = (int) ($_GET['client_id'] ?? 0);
$systemId  = (int) ($_GET['system_id'] ?? 0);
$band      = trim((string) ($_GET['band'] ?? ''));
$q         = trim((string) ($_GET['q'] ?? ''));
$limit     = max(1, min(500, (int) ($_GET['limit'] ?? 100)));

if ($productId <= 0 || $clientId <= 0) { echo json_encode(['fabrics' => [], 'error' => 'product_id + client_id required']); exit; }

$scopeClause = ''; $scopeParams = [];
if ($systemId > 0) { $scopeClause = ' AND (system_id IS NULL OR system_id = ?)'; $scopeParams = [$systemId]; }

// Distinct bands for the band dropdown.
if (($_GET['bands'] ?? '') !== '' && ($_GET['bands'] ?? '0') !== '0') {
    $st = $pdo->prepare("SELECT DISTINCT band_code FROM product_options
                          WHERE product_id = ? AND client_id = ? AND active = 1 $scopeClause
                       ORDER BY band_code");
    $st->execute(array_merge([$productId, $clientId], $scopeParams));
    echo json_encode(['bands' => array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN), static fn ($b) => (string) $b !== ''))]);
    exit;
}

$bandClause = ''; $bandParams = [];
if ($band !== '') { $bandClause = ' AND band_code = ?'; $bandParams = [$band]; }

if ($q === '') {
    $st = $pdo->prepare("SELECT id, band_code, supplier_name, name, colour, code
                           FROM product_options
                          WHERE product_id = ? AND client_id = ? AND active = 1 $scopeClause $bandClause
                       ORDER BY name, colour, band_code, supplier_name LIMIT $limit");
    $st->execute(array_merge([$productId, $clientId], $scopeParams, $bandParams));
} else {
    $words   = array_slice(preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 10);
    $clauses = []; $params = [$productId, $clientId];
    foreach ($words as $w) {
        $like = '%' . $w . '%';
        $clauses[] = '(name LIKE ? OR colour LIKE ? OR band_code LIKE ? OR code LIKE ?)';
        for ($i = 0; $i < 4; $i++) $params[] = $like;
    }
    $whereWords = $clauses ? ' AND ' . implode(' AND ', $clauses) : '';
    $st = $pdo->prepare("SELECT id, band_code, supplier_name, name, colour, code
                           FROM product_options
                          WHERE product_id = ? AND client_id = ? AND active = 1 $whereWords $scopeClause $bandClause
                       ORDER BY band_code, supplier_name, name, colour LIMIT $limit");
    $st->execute(array_merge($params, $scopeParams, $bandParams));
}

$fabrics = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $bits = array_filter([(string) ($r['supplier_name'] ?? ''), (string) $r['name'], (string) ($r['colour'] ?? '')], static fn ($s) => $s !== '');
    $fabrics[] = [
        'id'       => (int) $r['id'],
        'band'     => (string) $r['band_code'],
        'supplier' => (string) ($r['supplier_name'] ?? ''),
        'name'     => (string) $r['name'],
        'colour'   => (string) ($r['colour'] ?? ''),
        'code'     => (string) ($r['code'] ?? ''),
        'label'    => 'Band ' . $r['band_code'] . ' — ' . implode(' / ', $bits),
    ];
}
echo json_encode(['fabrics' => $fabrics]);
