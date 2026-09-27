<?php
declare(strict_types=1);

/**
 * The pricing code the tablet runs with no signal, as text, plus what this user
 * is allowed to see. offline/engine.js keeps it on the device and runs it inside
 * PHP-in-WebAssembly. It's the same files the server runs (_engine_files.php),
 * so an offline price is worked out exactly as the server would. The repo is
 * public, so shipping the source reveals nothing.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_engine_files.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['login' => true]);
    exit;
}

$user    = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$files   = offline_engine_sources();

echo json_encode([
    'version'        => substr(sha1(json_encode($files)), 0, 16),
    'files'          => $files,
    'user_id'        => (int) $user['user_id'],
    'client_id'      => (int) $user['client_id'],
    'can_costs'      => $isAdmin || !empty(current_user_permissions()['can_view_costs']),
    'is_super_admin' => is_super_admin(),
], JSON_UNESCAPED_UNICODE);
