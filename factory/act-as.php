<?php
declare(strict_types=1);

/**
 * Factory · super-admin "view as" switch.
 *
 * A super-admin isn't tied to one factory, so current_factory_id() lets them
 * pick which factory's back-office to view. This stores that choice in the
 * session; every factory page then scopes to it. Super-admin only — a factory
 * tenant is always locked to its own account.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (function_exists('is_super_admin') && is_super_admin()) {
        $fid = (int) ($_POST['factory_id'] ?? 0);
        if ($fid > 0 && is_factory_client($fid)) {
            $_SESSION['factory_acting_id'] = $fid;
        }
    }
}

// Return to wherever they were; only same-site absolute paths.
$back = (string) ($_POST['return_to'] ?? '');
if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//')) {
    $back = '/factory/incoming-orders.php';
}
header('Location: ' . $back);
exit;
