<?php
declare(strict_types=1);

/**
 * Generic drag-to-reorder endpoint.
 *
 * POST ?type={products|systems|extras|choices}  ids[] = [...]
 *
 * Sets sort_order = position for each row in the given list, tenant-scoped.
 * Returns JSON {ok: bool, count?: int, error?: string}.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

csrf_check();

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? 'products');
$user = current_user();
$ids  = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];

$clean = [];
foreach ($ids as $i) {
    $n = (int) $i;
    if ($n > 0 && !in_array($n, $clean, true)) $clean[] = $n;
}

if (!$clean) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No ids supplied']);
    exit;
}

// Map each type to the UPDATE statement template (one ? for sort_order, one
// for id, one for tenant scoping). Choices tenant-scope via the parent extra.
$updates = [
    'products' =>
        'UPDATE products SET sort_order = ?
          WHERE id = ? AND client_id = ?',
    'systems'  =>
        'UPDATE product_systems SET sort_order = ?
          WHERE id = ? AND client_id = ?',
    'extras'   =>
        'UPDATE product_extras SET sort_order = ?
          WHERE id = ? AND client_id = ?',
    'choices'  =>
        'UPDATE product_extra_choices c
           JOIN product_extras e ON e.id = c.product_extra_id
            SET c.sort_order = ?
          WHERE c.id = ? AND e.client_id = ?',
    // price_tables = bands within a system. sort_order added by
    // migrate_price_tables_sort_order.php; tenant-scoped directly.
    'price_tables' =>
        'UPDATE price_tables SET sort_order = ?
          WHERE id = ? AND client_id = ?',
];

if (!isset($updates[$type])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown type']);
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $u = $pdo->prepare($updates[$type]);
    foreach ($clean as $position => $id) {
        $u->execute([$position, $id, $user['client_id']]);
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'count' => count($clean), 'type' => $type]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
