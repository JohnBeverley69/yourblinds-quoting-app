<?php
declare(strict_types=1);

/**
 * The signed-in user's pricing catalogue, for pricing on the tablet with no
 * signal (offline/engine.js keeps it on the device).
 *
 * Only the tables + columns the live-price code reads (_catalogue_export.php).
 * Wholesale costs are left out unless this user may see costs. The sell price
 * doesn't use them.
 *
 * ETag = the catalogue version: the tablet sends If-None-Match and gets a 304
 * when its copy is still current. The version also changes each day, so
 * date-windowed promotions stay right.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_catalogue_export.php';

header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['login' => true]);
    exit;
}

$user     = current_user();
$isAdmin  = ($user['role'] ?? '') === 'admin';
$canCosts = $isAdmin || !empty(current_user_permissions()['can_view_costs']);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$cat = offline_export_catalogue($pdo, (int) $user['client_id'], $canCosts);

$etag = '"' . $cat['version'] . '"';
header('ETag: ' . $etag);
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
