<?php
declare(strict_types=1);

/**
 * Fabric search for the quote-builder line-item form.
 *
 * Designed for tenants with thousands of fabrics — a plain <select> would
 * blow up the page. Instead the form lets the user type and hits this
 * endpoint after a small debounce.
 *
 * GET /quote-builder/api/fabrics-search.php
 *   ?product_id=N         (required)
 *   &q=string             (optional — empty returns first 200 alphabetical)
 *   &limit=N              (optional, default 200, max 200)
 *
 * Match is OR across name / colour / supplier_name / band_code / code,
 * substring, case-insensitive (MySQL collation default).
 *
 * Returns: { "fabrics": [ {id, band, supplier, name, colour, code, label}, ... ] }
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user      = current_user();
$clientId  = (int) $user['client_id'];
$productId = (int) ($_GET['product_id'] ?? 0);
$q         = trim((string) ($_GET['q'] ?? ''));
$limit     = max(1, min(200, (int) ($_GET['limit'] ?? 200)));

if ($productId <= 0) {
    echo json_encode(['fabrics' => [], 'error' => 'product_id required']);
    exit;
}

$pdo = db();

if ($q === '') {
    // Empty query — return the alphabetically-first $limit fabrics so the
    // user gets *something* to scroll on the very first focus, before
    // they type a single character.
    $st = $pdo->prepare(
        "SELECT id, band_code, supplier_name, name, colour, code
           FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1
       ORDER BY band_code, supplier_name, name, colour
          LIMIT $limit"
    );
    $st->execute([$productId, $clientId]);
} else {
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        "SELECT id, band_code, supplier_name, name, colour, code
           FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1
            AND (name           LIKE ?
              OR colour         LIKE ?
              OR supplier_name  LIKE ?
              OR band_code      LIKE ?
              OR code           LIKE ?)
       ORDER BY band_code, supplier_name, name, colour
          LIMIT $limit"
    );
    $st->execute([$productId, $clientId, $like, $like, $like, $like, $like]);
}

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

echo json_encode(['fabrics' => $fabrics]);
