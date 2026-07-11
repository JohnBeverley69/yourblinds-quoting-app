<?php
declare(strict_types=1);

/**
 * Factory app landing. For now the home IS the Incoming Orders queue, so send
 * factory staff straight there. As more factory screens land (production,
 * work sheets, dispatch) this becomes a proper dashboard.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

header('Location: /factory/incoming-orders.php');
exit;
