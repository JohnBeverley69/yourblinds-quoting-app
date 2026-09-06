<?php
declare(strict_types=1);

/**
 * Bulk-set the supplier on selected fabrics of a product. POST + CSRF. Reuses
 * the Fabrics page's bulk-select checkboxes (ids[]) + a supplier name, posted
 * from the "Set supplier on selected" button. Tenant-scoped to the product.
 *
 * The common case this exists for: an imported fabric file with no Supplier
 * column — tick "select all", type the supplier once, apply to all of them.
 * An empty supplier is allowed and clears it (stored NULL).
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /admin/products/index.php');
    exit;
}
csrf_check();

$user      = current_user();
$clientId  = (int) $user['client_id'];
$productId = (int) ($_POST['product_id'] ?? 0);

$supplier = trim((string) ($_POST['supplier_name'] ?? ''));
if (strlen($supplier) > 150) $supplier = substr($supplier, 0, 150);

$ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn ($n) => $n > 0));

$redirect = '/admin/products/options.php?product_id=' . $productId;

if ($productId <= 0 || !$ids) {
    $_SESSION['flash_error'] = 'Tick the fabrics you want to set a supplier on first.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(
        "UPDATE product_options SET supplier_name = ?
          WHERE product_id = ? AND client_id = ? AND id IN ($place)"
    );
    $st->execute(array_merge([$supplier !== '' ? $supplier : null, $productId, $clientId], $ids));
    $n = $st->rowCount();
    $_SESSION['flash_success'] = $n . ' fabric' . ($n === 1 ? '' : 's')
        . ' set to supplier ' . ($supplier !== '' ? '"' . $supplier . '"' : '(none)') . '.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not set supplier: ' . $e->getMessage();
}

header('Location: ' . $redirect);
exit;
