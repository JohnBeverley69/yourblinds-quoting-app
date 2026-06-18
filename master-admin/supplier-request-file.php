<?php
declare(strict_types=1);

/**
 * Stream a supplier-request's attached price list to a super-admin. Kept behind
 * auth (the file lives under /uploads but we never expose a guessable URL).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Not found'); }

try {
    $st = db()->prepare('SELECT file_name, file_path FROM supplier_requests WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(404);
    exit('Not found');
}

$relPath = (string) ($row['file_path'] ?? '');
$name    = (string) ($row['file_name'] ?? 'price-list');

// Only ever serve from the dedicated upload dir — no path traversal.
if ($relPath === '' || strpos($relPath, 'uploads/supplier-requests/') !== 0 || strpos($relPath, '..') !== false) {
    http_response_code(404);
    exit('Not found');
}
$abs = __DIR__ . '/../' . $relPath;
if (!is_file($abs)) { http_response_code(404); exit('File missing'); }

$safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'price-list';

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($abs));
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
readfile($abs);
exit;
