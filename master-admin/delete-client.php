<?php
declare(strict_types=1);

/**
 * Delete a business (client / trade account) and everything held for it —
 * _partials/client_delete.php does the work (incl. the factory's invoices,
 * payments, delivery notes, remakes and callbacks for the account, which don't
 * cascade). Posted from Platform → Clients → Overview and from the trade account
 * page's Danger zone; the latter also asks for the account name to be typed.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/client_delete.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$targetId = (int) ($_POST['client_id'] ?? 0);
$fromTA   = ($_POST['return'] ?? '') === 'trade-account';
$back     = $fromTA ? '/master-admin/trade-account.php?id=' . $targetId : '/master-admin/index.php';
$pdo      = db();

$block = cl_delete_block_reason($pdo, $targetId, (int) $user['client_id']);
if ($block !== '') {
    $_SESSION['flash_error'] = $block;
    header('Location: ' . $back);
    exit;
}

// The trade account page asks for the name to be typed, so a stray click can't do it.
if ($fromTA) {
    $st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
    $st->execute([$targetId]);
    $name = (string) ($st->fetchColumn() ?: '');
    if (mb_strtolower(trim((string) ($_POST['confirm_name'] ?? ''))) !== mb_strtolower(trim($name))) {
        $_SESSION['flash_error'] = 'Nothing deleted — type the account name exactly as shown to confirm.';
        header('Location: ' . $back . '#delete-account');
        exit;
    }
}

$res = cl_delete_client($pdo, $targetId);
$_SESSION[$res['ok'] ? 'flash_success' : 'flash_error'] = $res['message'];
header('Location: ' . ($res['ok'] ? ($fromTA ? '/master-admin/trade-accounts.php' : '/master-admin/index.php') : $back));
exit;
