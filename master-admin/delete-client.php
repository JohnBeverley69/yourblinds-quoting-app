<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user        = current_user();
$myClientId  = (int) $user['client_id'];
$targetId    = (int) ($_POST['client_id'] ?? 0);

if ($targetId <= 0) {
    $_SESSION['flash_error'] = 'No client specified.';
    header('Location: /master-admin/index.php');
    exit;
}

// Belt: never delete your own client.
if ($targetId === $myClientId) {
    $_SESSION['flash_error'] = "You can't delete the client you're logged in as.";
    header('Location: /master-admin/index.php');
    exit;
}

$pdo = db();

// Look up the target so we have its name for the flash, and so we can sanity-
// check it actually exists.
$st = $pdo->prepare('SELECT id, company_name FROM clients WHERE id = ? LIMIT 1');
$st->execute([$targetId]);
$client = $st->fetch();
if (!$client) {
    $_SESSION['flash_error'] = 'Client not found.';
    header('Location: /master-admin/index.php');
    exit;
}

// Braces: refuse if any user in the target client is a super-admin. This
// stops you accidentally killing a tenant that holds another master admin
// account, even if it's not the one you're logged in as.
$superSt = $pdo->prepare(
    'SELECT COUNT(*) FROM client_users WHERE client_id = ? AND is_super_admin = 1'
);
$superSt->execute([$targetId]);
if ((int) $superSt->fetchColumn() > 0) {
    $_SESSION['flash_error'] = 'Cannot delete: client has a master admin user. '
                             . 'Clear the super_admin flag in client_users first.';
    header('Location: /master-admin/index.php');
    exit;
}

// Cascade does the rest. clients → client_users, client_settings, customers,
// products → systems, options, extras → choices → price rows, etc. Every
// FK referencing clients.id was set to ON DELETE CASCADE in the schema.
try {
    $pdo->prepare('DELETE FROM clients WHERE id = ?')->execute([$targetId]);
    $_SESSION['flash_success'] = 'Client "' . $client['company_name'] . '" deleted.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not delete client: ' . $e->getMessage();
}

header('Location: /master-admin/index.php');
exit;
