<?php
declare(strict_types=1);

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
$productId = (int) ($_POST['product_id'] ?? 0);

// Accept either ?id=N (single-row delete from per-row forms) or ?ids[]=N&ids[]=M
// (bulk delete from the checkbox toolbar). Filter to positive ints.
$rawIds = [];
if (isset($_POST['id'])) {
    $rawIds[] = (int) $_POST['id'];
}
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $i) { $rawIds[] = (int) $i; }
}
$ids = array_values(array_unique(array_filter($rawIds, static fn ($i) => $i > 0)));

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "DELETE FROM product_options
          WHERE id IN ($placeholders) AND client_id = ?"
    );
    $stmt->execute(array_merge($ids, [$user['client_id']]));
    $deleted = $stmt->rowCount();
    if ($deleted > 0) {
        $_SESSION['flash_success'] = $deleted === 1
            ? 'Option deleted.'
            : "$deleted options deleted.";
    }
}

$default = $productId > 0
    ? '/admin/products/options.php?product_id=' . $productId
    : '/admin/products/index.php';

// Honour a same-origin return_to from callers — used by the setup
// wizard so deletes from step 3 land back on the wizard.
$returnTo = trim((string) ($_POST['return_to'] ?? ''));
$target   = $returnTo !== ''
    ? safe_local_redirect($returnTo, $default)
    : $default;
header('Location: ' . $target);
exit;
