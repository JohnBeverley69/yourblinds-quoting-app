<?php
declare(strict_types=1);

/**
 * Returns the data the quote-builder line-item form needs to render
 * cascading dropdowns for a given product:
 *   - systems[]  (id, name, is_default)
 *   - extras[]   (id, name, is_required, parent_choice_id, choices[])
 *     each choice: (id, system_id, label, is_default)
 *
 * Fabrics are NOT returned here — for tenants with hundreds or thousands
 * of fabrics that'd inflate the response massively. The form drives the
 * fabric picker via /quote-builder/api/fabrics-search.php instead, which
 * matches by substring across name / colour / supplier / band.
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

// 3. Extras + their choices, in two queries (extras then choices in IN clause).
//    (Fabrics moved to /api/fabrics-search.php for typeahead — see header.)
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
        "SELECT id, product_extra_id, label,
                price_delta, price_percent, price_per_metre,
                is_default, sort_order
           FROM product_extra_choices
          WHERE product_extra_id IN ($ph) AND active = 1
       ORDER BY product_extra_id, sort_order, label"
    );
    $st->execute($extraIds);
    $rawChoices = $st->fetchAll();

    // Pull every choice's system-scope rows in one go, fold by choice id.
    $scopeByChoice = [];
    if ($rawChoices) {
        $choiceIds = array_map(static fn ($c) => (int) $c['id'], $rawChoices);
        $cph = implode(',', array_fill(0, count($choiceIds), '?'));
        $scopeSt = $pdo->prepare(
            "SELECT product_extra_choice_id, product_system_id
               FROM product_extra_choice_systems
              WHERE product_extra_choice_id IN ($cph)"
        );
        $scopeSt->execute($choiceIds);
        foreach ($scopeSt->fetchAll() as $r) {
            $scopeByChoice[(int) $r['product_extra_choice_id']][] = (int) $r['product_system_id'];
        }
    }

    foreach ($rawChoices as $r) {
        $cid = (int) $r['id'];
        $choicesByExtra[(int) $r['product_extra_id']][] = [
            'id'         => $cid,
            // Empty array means "all systems". Otherwise a JS-friendly list of
            // allowed system ids — JS filters choices on system change.
            'system_ids' => $scopeByChoice[$cid] ?? [],
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
    'extras'  => $extras,
]);
