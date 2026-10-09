<?php
declare(strict_types=1);

/**
 * A remake's photo — served only to the factory office or the account it belongs
 * to (the files sit in uploads/remakes/, which denies direct web access).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/remakes.php';

requireLogin();

$pdo = db();
$id  = (int) ($_GET['id'] ?? 0);
$r   = null;
if (rm_ready($pdo)) {
    $st = $pdo->prepare('SELECT id, account_client_id, photo_path FROM factory_remakes WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$path = $r ? (string) $r['photo_path'] : '';
if (!$r || $path === '' || strpos($path, 'uploads/remakes/') !== 0 || strpos($path, '..') !== false || !rm_can_see_photo($r)) {
    http_response_code(404);
    exit('Not found');
}
$file = __DIR__ . '/../' . $path;
if (!is_file($file)) { http_response_code(404); exit('Not found'); }
$type = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
