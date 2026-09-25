<?php
declare(strict_types=1);

/**
 * Undo the newest price change for a scope (see _partials/price_table_undo.php)
 * and go back to the page the Undo button was on. Tenant-scoped: snapshots
 * are looked up by the admin's own client_id.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require_once __DIR__ . '/../../_partials/price_table_undo.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

// Only ever bounce back into the product admin — never an arbitrary URL.
$return = (string) ($_POST['return'] ?? '');
if (!preg_match('#^/admin/products/[A-Za-z0-9_./?=&%-]*$#', $return)) {
    $return = '/admin/products/index.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $return, true, 303);
    exit;
}
csrf_check();

$scope = (string) ($_POST['scope'] ?? '');
if (!preg_match('/^(table|system|product):\d+$/', $scope)) {
    $_SESSION['flash_error'] = 'Nothing to undo.';
} else {
    [$ok, $msg, $cells] = pu_undo(db(), $clientId, $scope);
    if ($ok) {
        $_SESSION['flash_success'] = 'Undone: ' . $msg . ' — ' . number_format($cells)
            . ' price' . ($cells === 1 ? ' is' : 's are') . ' back to what they were.';
    } else {
        $_SESSION['flash_error'] = $msg;
    }
}

header('Location: ' . $return, true, 303);
exit;
