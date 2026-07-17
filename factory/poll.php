<?php
declare(strict_types=1);

/**
 * Factory · "has anything changed?" endpoint.
 *
 * ?what=incoming|floor -> {"v":"i12.748.1752…"}. The screens poll this and offer
 * a refresh when it differs from what they loaded with. Counts and timestamps
 * only — no order data, so it stays cheap enough to call every 20 seconds on
 * every bench PC in the building.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/factory_poll.php';

requireFactory();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$what = (string) ($_GET['what'] ?? 'incoming');
if (!in_array($what, ['incoming', 'floor'], true)) $what = 'incoming';

echo json_encode(['v' => fx_poll_version(db(), $what, factory_client_id())]);
