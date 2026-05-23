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

// 3. Extras + their choices. The new model puts system scope on the
//    choice itself (system_id, NULL = "all systems"). Option-level
//    scope is gone — an option appears whenever any of its choices
//    is available for the chosen system, which the JS handles client-
//    side after the filter runs.
//    (Fabrics moved to /api/fabrics-search.php for typeahead — see header.)
// Optional columns:
//   length_input_label (migrate_extra_length_input.php)
//   allow_multi        (migrate_extra_allow_multi.php)
// Try the full SELECT first; cascade down if either column is missing.
try {
    $st = $pdo->prepare(
        'SELECT id, name, is_required, parent_choice_id,
                length_input_label, allow_multi, sort_order
           FROM product_extras
          WHERE product_id = ? AND client_id = ? AND active = 1
       ORDER BY sort_order, name'
    );
    $st->execute([$productId, $clientId]);
    $extrasRaw = $st->fetchAll();
} catch (Throwable $eA) {
    try {
        $st = $pdo->prepare(
            'SELECT id, name, is_required, parent_choice_id,
                    length_input_label, sort_order
               FROM product_extras
              WHERE product_id = ? AND client_id = ? AND active = 1
           ORDER BY sort_order, name'
        );
        $st->execute([$productId, $clientId]);
        $extrasRaw = $st->fetchAll();
        foreach ($extrasRaw as &$r) $r['allow_multi'] = 0;
        unset($r);
    } catch (Throwable $eB) {
        $st = $pdo->prepare(
            'SELECT id, name, is_required, parent_choice_id, sort_order
               FROM product_extras
              WHERE product_id = ? AND client_id = ? AND active = 1
           ORDER BY sort_order, name'
        );
        $st->execute([$productId, $clientId]);
        $extrasRaw = $st->fetchAll();
        foreach ($extrasRaw as &$r) {
            $r['length_input_label'] = null;
            $r['allow_multi']        = 0;
        }
        unset($r);
    }
}
$extraIds  = array_map(static fn ($r) => (int) $r['id'], $extrasRaw);

// Parent-choice gating uses the junction table — an option can be
// gated to MULTIPLE parents. We fold into [extra_id => [choice_id, ...]].
//
// **Label expansion**: when a choice gets duplicated across systems
// (e.g. "Corded" exists three times — one per system — so each can
// have its own pricing), the gate stays linked to whichever of those
// rows existed when the admin set it up. From the admin's mental
// model these are "the same choice", just system-specific. So when
// we hand the list to the front-end we expand it: for every gated
// choice, include every sibling in the same product_extra that has
// the same active label. "Gated on Corded" effectively means "gated
// on any choice called Corded".
$parentsByExtra = [];
if ($extraIds) {
    $pph = implode(',', array_fill(0, count($extraIds), '?'));
    $pSt = $pdo->prepare(
        "SELECT DISTINCT pep.product_extra_id, sibling.id AS choice_id
           FROM product_extra_parent_choices pep
           JOIN product_extra_choices anchor
                ON anchor.id = pep.product_extra_choice_id
           JOIN product_extra_choices sibling
                ON sibling.product_extra_id = anchor.product_extra_id
               AND sibling.label  = anchor.label
               AND sibling.active = 1
          WHERE pep.product_extra_id IN ($pph)"
    );
    $pSt->execute($extraIds);
    foreach ($pSt->fetchAll() as $r) {
        $parentsByExtra[(int) $r['product_extra_id']][] = (int) $r['choice_id'];
    }
    // De-dup per extra (the DISTINCT covers the SQL side but the
    // multi-row fold can still drop dupes via array_unique).
    foreach ($parentsByExtra as $eid => $ids) {
        $parentsByExtra[$eid] = array_values(array_unique($ids));
    }
}

$choicesByExtra = [];
if ($extraIds) {
    $ph = implode(',', array_fill(0, count($extraIds), '?'));
    $st = $pdo->prepare(
        "SELECT id, product_extra_id, system_id, label,
                price_delta, price_percent, price_per_metre,
                is_default, sort_order, image_path
           FROM product_extra_choices
          WHERE product_extra_id IN ($ph) AND active = 1
       ORDER BY product_extra_id, sort_order, label"
    );
    $st->execute($extraIds);
    foreach ($st->fetchAll() as $r) {
        $choicesByExtra[(int) $r['product_extra_id']][] = [
            'id'         => (int) $r['id'],
            // null = "available on every system". Otherwise the choice
            // is only available when the picked system matches.
            'system_id'  => $r['system_id'] !== null ? (int) $r['system_id'] : null,
            'label'      => (string) $r['label'],
            'is_default' => (bool)   $r['is_default'],
            'image_url'  => !empty($r['image_path']) ? (string) $r['image_path'] : null,
        ];
    }
}

$extras = array_map(static function ($r) use ($choicesByExtra, $parentsByExtra) {
    $eid = (int) $r['id'];
    return [
        'id'                 => $eid,
        'name'               => (string) $r['name'],
        'is_required'        => (bool)   $r['is_required'],
        // parent_choice_ids — list of choice ids that gate this extra.
        // Empty list = always visible. Any one match in the user's
        // current selections is enough to show the extra.
        'parent_choice_ids'  => $parentsByExtra[$eid] ?? [],
        // length_input_label — when non-empty, the quote builder JS
        // renders a number input next to this extra so the salesperson
        // can capture a spec value (e.g. "Wand length (mm)") alongside
        // the chosen option. Empty / null = choice-only (the default).
        'length_input_label' => $r['length_input_label'] ?? null,
        // allow_multi — when truthy, the quote builder renders this
        // option as checkboxes (multi-pick) instead of a dropdown.
        'allow_multi'        => (int) ($r['allow_multi'] ?? 0) === 1,
        'choices'            => $choicesByExtra[$eid] ?? [],
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
