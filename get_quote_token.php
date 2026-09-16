<?php
declare(strict_types=1);
// READ-ONLY temp helper: print a quote's public_token so we can view its public
// page during verification. /get_quote_token.php?id=N (super-admin). Delete after.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/middleware.php';
requireSuperAdmin();
header('Content-Type: text/plain; charset=utf-8');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT public_token FROM quotes WHERE id = ? LIMIT 1');
$st->execute([$id]);
echo (string) $st->fetchColumn();
