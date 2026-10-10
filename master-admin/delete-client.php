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

// Type the name, from wherever the delete was asked for. This used to be
// `if ($fromTA)`, so only the trade-account page's Danger zone asked — and
// Platform → Clients posted here without return=trade-account, giving a
// one-click path to the same operation.
//
// That operation is no longer what its old confirm text said. Since #942,
// cl_delete_client() destroys the factory's invoices and invoice lines, credit
// notes, payments and allocations, delivery notes and lines, the
// statement-email log, bank payer aliases, office-calendar callbacks, remakes,
// floor jobs/streams/scan log, and every order the factory raised for the
// account (_partials/client_delete.php:82-121). An account with £6k
// outstanding went in one transaction behind a modal that only mentioned
// catalogue and quote data.
$st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
$st->execute([$targetId]);
$name = (string) ($st->fetchColumn() ?: '');
if (mb_strtolower(trim((string) ($_POST['confirm_name'] ?? ''))) !== mb_strtolower(trim($name))) {
    $_SESSION['flash_error'] = 'Nothing deleted — type the account name exactly as shown to confirm.';
    header('Location: /master-admin/trade-account.php?id=' . $targetId . '#delete-account');
    exit;
}

$res = cl_delete_client($pdo, $targetId);
$_SESSION[$res['ok'] ? 'flash_success' : 'flash_error'] = $res['message'];
header('Location: ' . ($res['ok'] ? ($fromTA ? '/master-admin/trade-accounts.php' : '/master-admin/index.php') : $back));
exit;
