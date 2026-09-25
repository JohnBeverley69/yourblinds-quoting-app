<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_partials/product_lock.php';

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

$user     = current_user();
$id       = (int) ($_POST['id']        ?? 0);
$systemId = (int) ($_POST['system_id'] ?? 0);
// Factory-catalogue pricing lock (_partials/product_lock.php).
if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    pl_require_unlocked_any([pl_product_of('table', $id), pl_product_of('system', $systemId)], '/admin/products/index.php');
}

if ($id > 0) {
    // FK cascades wipe price_table_rows for this table.
    $stmt = db()->prepare(
        'DELETE FROM price_tables WHERE id = ? AND client_id = ?'
    );
    $stmt->execute([$id, $user['client_id']]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Price table deleted.';
    }
}

$default = $systemId > 0
    ? '/admin/products/price-tables.php?system_id=' . $systemId
    : '/admin/products/index.php';

// Honour a same-origin return_to from callers — used by the setup
// wizard so deletes from step 4 land back on the wizard.
$returnTo = trim((string) ($_POST['return_to'] ?? ''));
$target   = $returnTo !== ''
    ? safe_local_redirect($returnTo, $default)
    : $default;
header('Location: ' . $target);
exit;
