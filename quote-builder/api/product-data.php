<?php
declare(strict_types=1);

/**
 * Returns everything the quote-builder line-item form needs to render
 * cascading dropdowns for a given product:
 *   - systems[]  (id, name, is_default)
 *   - fabrics[]  (id, band, supplier, name, colour, code, label)
 *   - extras[]   (id, name, is_required, parent_choice_id, choices[])
 *     each choice: (id, system_id, label, is_default)
 *
 * Tenant-scoped via the logged-in user's client_id. Inactive rows skipped.
 *
 * GET /quote-builder/api/product-data.php?product_id=N
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user      = current_user();
$clientId  = (int) $user['client_id'];
$productId = (int) ($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

$pdo = db();

// 1. Product (tenant scope).
$st = $pdo->prepare(
    'SELECT id, name FROM products
      WHERE id = ? AND client_id = ? AND active = 1
      LIMIT 1'
);
$st->execute([$productId, $clientId]);
$product = $st->fetch();
if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit;
}

// 2. Systems.
$st = $pdo->prepare(
    'SELECT id, name, is_default
       FROM product_systems
      WHERE product_id = ? AND client_id = ? AND active = 1
   ORDER BY sort_order, name'
);
$st->execute([$productId, $clientId]);
$systems = array_map(static fn ($r) => [
    'id'         => (int)    $r['id'],
    'name'       => (string) $r['name'],
    'is_default' => (bool)   $r['is_default'],
], $st->fetchAll());

// 3. Fabrics. Includes a pre-built display label for the dropdown so the
//    UI doesn't have to format band/supplier/name/colour itself.
$st = $pdo->prepare(
    'SELECT id, band_code, supplier_name, name, colour, code
       FROM product_options
      WHERE product_id = ? AND client_id = ? AND active = 1
   ORDER BY band_code, supplier_name, name, colour'
);
$st->execute([$productId, $clientId]);
$fabrics = [];
foreach ($st->fetchAll() as $r) {
    $bits = array_filter([
        (string) ($r['supplier_name'] ?? ''),
        (string) $r['name'],
        (string) ($r['colour'] ?? ''),
    ], static fn ($s) => $s !== '');
    $fabrics[] = [
        'id'       => (int)    $r['id'],
        'band'     => (string) $r['band_code'],
        'supplier' => (string) ($r['supplier_name'] ?? ''),
        'name'     => (string) $r['name'],
        'colour'   => (string) ($r['colour'] ?? ''),
        'code'     => (string) ($r['code']   ?? ''),
        'label'    => 'Band ' . $r['band_code'] . ' — ' . implode(' / ', $bits),
    ];
}

// 4. Extras + their choices, in two queries (extras then choices in IN clause).
$st = $pdo->prepare(
    'SELECT id, name, is_required, parent_choice_id, sort_order
       FROM product_extras
      WHERE product_id = ? AND client_id = ? AND active = 1
   ORDER BY sort_order, name'
);
$st->execute([$productId, $clientId]);
$extrasRaw = $st->fetchAll();
$extraIds  = array_map(static fn ($r) => (int) $r['id'], $extrasRaw);

$choicesByExtra = [];
if ($extraIds) {
    $ph = implode(',', array_fill(0, count($extraIds), '?'));
    $st = $pdo->prepare(
        "SELECT id, product_extra_id, system_id, label,
                price_delta, price_percent, price_per_metre,
                is_default, sort_order
           FROM product_extra_choices
          WHERE product_extra_id IN ($ph) AND active = 1
       ORDER BY product_extra_id, sort_order, label"
    );
    $st->execute($extraIds);
    foreach ($st->fetchAll() as $r) {
        $choicesByExtra[(int) $r['product_extra_id']][] = [
            'id'         => (int)    $r['id'],
            'system_id'  => $r['system_id'] !== null ? (int) $r['system_id'] : null,
            'label'      => (string) $r['label'],
            'is_default' => (bool)   $r['is_default'],
        ];
    }
}

$extras = array_map(static function ($r) use ($choicesByExtra) {
    return [
        'id'               => (int)    $r['id'],
        'name'             => (string) $r['name'],
        'is_required'      => (bool)   $r['is_required'],
        'parent_choice_id' => $r['parent_choice_id'] !== null ? (int) $r['parent_choice_id'] : null,
        'choices'          => $choicesByExtra[(int) $r['id']] ?? [],
    ];
}, $extrasRaw);

echo json_encode([
    'product' => [
        'id'   => (int)    $product['id'],
        'name' => (string) $product['name'],
    ],
    'systems' => $systems,
    'fabrics' => $fabrics,
    'extras'  => $extras,
]);
