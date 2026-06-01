<?php
declare(strict_types=1);

/**
 * Quote History — now permanently merged into Order History per
 * Tyler's review feedback (Quotes #3).
 *
 * This file remains as a redirect target so existing bookmarks and
 * deep links (the back-link in /quote-builder/edit.php, anything
 * shared internally, search engines that crawled the old URL) keep
 * working. Preserves the status + q querystring on the way through.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$qs = [];
$status = trim((string) ($_GET['status'] ?? ''));
$q      = trim((string) ($_GET['q']      ?? ''));
if ($status !== '') $qs[] = 'status=' . urlencode($status);
if ($q      !== '') $qs[] = 'q='      . urlencode($q);

$target = '/orders/index.php' . ($qs ? '?' . implode('&', $qs) : '');
header('Location: ' . $target, true, 301);
exit;
