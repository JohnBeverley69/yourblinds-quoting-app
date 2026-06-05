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
// Optional system filter — passed by the quote builder once the
// salesperson picks a system. Fabrics either belong to that system
// or are universal (system_id IS NULL). When omitted, all fabrics
// for the product return (used during initial product selection
// before a system is known).
$systemId  = (int) ($_GET['system_id'] ?? 0);
// Optional band filter — passed by the quote builder's "Band" dropdown
// so the salesperson can narrow a large fabric list to a single price
// band (e.g. "show only Band B fabrics"). Exact match, case-insensitive
// via the column collation. Empty = all bands.
$band      = trim((string) ($_GET['band'] ?? ''));
// Cap raised from 500 → 2000 so server-side typeahead can handle
// large supplier catalogues. Default lowered to 200 — for the
// empty-query "what's there?" focus case 200 is plenty, and any
// real search will narrow further. Callers wanting more pass
// limit= explicitly.
$limit     = max(1, min(2000, (int) ($_GET['limit'] ?? 200)));

if ($productId <= 0) {
    echo json_encode(['fabrics' => [], 'error' => 'product_id required']);
    exit;
}

$pdo = db();

// System-scope filter — applied to both query branches. Universal
// fabrics (system_id IS NULL) always show; system-scoped fabrics
// only show when the request matches their scope.
$scopeClause = '';
$scopeParams = [];
if ($systemId > 0) {
    $scopeClause = ' AND (system_id IS NULL OR system_id = ?)';
    $scopeParams = [$systemId];
}

// Band filter — applied to both query branches alongside the scope.
$bandClause = '';
$bandParams = [];
if ($band !== '') {
    $bandClause = ' AND band_code = ?';
    $bandParams = [$band];
}

if ($q === '') {
    // Empty query — return the alphabetically-first $limit fabrics so the
    // user gets *something* to scroll on the very first focus, before
    // they type a single character.
    //
    // Order by NAME first (not band_code) so the result mixes bands
    // fairly. Otherwise a tenant with a large band-A catalogue would
    // fill the whole limit with band A and never see B/C/D on the
    // empty-focus dropdown — exactly the bug the user reported.
    $st = $pdo->prepare(
        "SELECT id, band_code, supplier_name, name, colour, code
           FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1
            $scopeClause
            $bandClause
       ORDER BY name, colour, band_code, supplier_name
          LIMIT $limit"
    );
    $st->execute(array_merge([$productId, $clientId], $scopeParams, $bandParams));
} else {
    // Multi-word search — each whitespace-separated word in the
    // query must appear in AT LEAST ONE of (name, colour,
    // supplier_name, band_code, code). The query as a whole ANDs
    // the per-word clauses together, so "polaris cream" returns
    // ONLY fabrics where both "polaris" AND "cream" appear (across
    // any of the searched fields), instead of the old OR-only
    // behaviour that matched anything containing either word.
    //
    // Empty words (from double-spaces) are skipped. We also cap at
    // 10 words so a malicious 1000-word query can't blow up the
    // prepared statement.
    $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $words = array_slice($words, 0, 10);

    // Build one (col LIKE ? OR col LIKE ? OR …) clause per word,
    // then AND them. Collect the bound params in the same order.
    $clauses = [];
    $params  = [$productId, $clientId];
    foreach ($words as $w) {
        $like = '%' . $w . '%';
        $clauses[] = '(name LIKE ? OR colour LIKE ? OR supplier_name LIKE ? '
                   . 'OR band_code LIKE ? OR code LIKE ?)';
        for ($i = 0; $i < 5; $i++) $params[] = $like;
    }
    $whereWords = $clauses
        ? ' AND ' . implode(' AND ', $clauses)
        : '';

    $st = $pdo->prepare(
        "SELECT id, band_code, supplier_name, name, colour, code
           FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1
            $whereWords
            $scopeClause
            $bandClause
       ORDER BY band_code, supplier_name, name, colour
          LIMIT $limit"
    );
    $st->execute(array_merge($params, $scopeParams, $bandParams));
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
