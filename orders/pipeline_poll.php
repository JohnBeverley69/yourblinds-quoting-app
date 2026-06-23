<?php
declare(strict_types=1);

/**
 * Pipeline live-sync poll. Returns the current "state signature" for the
 * quotes the user can see — count + most-recent change. The pipeline page
 * polls this and reloads when the signature differs from the one it rendered,
 * so the board stays current without a manual refresh (and never reloads when
 * nothing has changed). Mirrors the $pipelineSig query in orders/pipeline.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user       = current_user();
$clientId   = (int) $user['client_id'];
$myUserId   = (int) $user['user_id'];
$isAdmin    = ($user['role'] ?? '') === 'admin';
$perms      = function_exists('current_user_permissions') ? current_user_permissions() : [];
$canViewAll = $isAdmin || !empty($perms['can_view_all_customer_jobs']);
$mineOnly   = !empty($_GET['mine']);

$sig = '';
try {
    $pdo = db();
    if ((bool) $pdo->query("SHOW TABLES LIKE 'quotes'")->fetchColumn()) {
        $sw = ['client_id = ?'];
        $sp = [$clientId];
        if (!$canViewAll || $mineOnly) {
            $sw[] = '(salesperson_id = ? OR EXISTS (
                        SELECT 1 FROM appointments a
                         WHERE a.quote_id = quotes.id AND a.client_user_id = ?))';
            $sp[] = $myUserId;
            $sp[] = $myUserId;
        }
        $st = $pdo->prepare(
            "SELECT CONCAT(COUNT(*), ':', COALESCE(MAX(updated_at), ''))
               FROM quotes WHERE " . implode(' AND ', $sw)
        );
        $st->execute($sp);
        $sig = (string) $st->fetchColumn();
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true, 'sig' => $sig]);
