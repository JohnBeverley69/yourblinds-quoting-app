<?php
declare(strict_types=1);

/**
 * Client requests a supplier be added to the library, optionally attaching the
 * supplier's price list. POST + CSRF. Lands in master-admin/supplier-requests.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /library/index.php');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

$name    = trim((string) ($_POST['supplier_name'] ?? ''));
$website  = trim((string) ($_POST['website'] ?? '')) ?: null;
$notes    = trim((string) ($_POST['notes'] ?? '')) ?: null;

if ($name === '') {
    $_SESSION['flash_error'] = 'Please enter the supplier name.';
    header('Location: /library/index.php');
    exit;
}

// Optional price-list upload.
$fileName = null;
$filePath = null;
if (!empty($_FILES['price_list']['tmp_name']) && is_uploaded_file($_FILES['price_list']['tmp_name'])) {
    $orig = (string) ($_FILES['price_list']['name'] ?? '');
    $ext  = strtolower((string) pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xlsm', 'xls', 'csv', 'ods', 'pdf'], true)) {
        $_SESSION['flash_error'] = 'Attach the price list as Excel, CSV, ODS or PDF (or leave it blank).';
        header('Location: /library/index.php');
        exit;
    }
    if ((int) ($_FILES['price_list']['size'] ?? 0) > 20 * 1024 * 1024) {
        $_SESSION['flash_error'] = 'That file is too large (max 20 MB).';
        header('Location: /library/index.php');
        exit;
    }
    $dir = __DIR__ . '/../uploads/supplier-requests';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        $_SESSION['flash_error'] = 'Could not save the attachment — please try again.';
        header('Location: /library/index.php');
        exit;
    }
    $stored = 'req_' . $clientId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (@move_uploaded_file($_FILES['price_list']['tmp_name'], $dir . '/' . $stored)) {
        $fileName = mb_substr($orig, 0, 255);
        $filePath = 'uploads/supplier-requests/' . $stored;   // relative to app root
    }
}

// current_user() doesn't carry the email — fetch the submitter's from users.
$reqEmail = null;
try {
    $es = db()->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $es->execute([(int) ($user['user_id'] ?? 0)]);
    $reqEmail = trim((string) ($es->fetchColumn() ?: '')) ?: null;
} catch (Throwable $e) { /* no email available — fine */ }

// Store the requester's email only if the column is there (pre-migration safe).
$hasEmailCol = false;
try {
    $c = db()->prepare("SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_requests' AND COLUMN_NAME = 'email' LIMIT 1");
    $c->execute();
    $hasEmailCol = (bool) $c->fetchColumn();
} catch (Throwable $e) { /* probe failed → treat as absent */ }

$logged = false;
try {
    if ($hasEmailCol) {
        db()->prepare(
            'INSERT INTO supplier_requests (client_id, email, supplier_name, website, notes, file_name, file_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$clientId, $reqEmail, mb_substr($name, 0, 160), $website, $notes, $fileName, $filePath]);
    } else {
        db()->prepare(
            'INSERT INTO supplier_requests (client_id, supplier_name, website, notes, file_name, file_path)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$clientId, mb_substr($name, 0, 160), $website, $notes, $fileName, $filePath]);
    }
    $_SESSION['flash_success'] = 'Thanks — your request for “' . $name . '” has been logged'
        . ($fileName !== null ? ' with the price list attached.' : '.');
    $logged = true;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not log the request: ' . $e->getMessage()
        . ' — has migrate_supplier_requests.php been run?';
}

// Notify the team that a request came in. Best-effort: a mail failure must
// never affect the client, who has already had their request logged.
if ($logged) {
    try {
        require_once __DIR__ . '/../mailer.php';
        if (function_exists('mailer_send')) {
            $company = (string) ($user['company_name'] ?? '');
            $to   = (string) (env('SUPPLIER_REQUEST_NOTIFY', '') ?: 'hello@yourblinds.uk');
            $base = rtrim((string) (env('APP_URL', 'https://www.yourblinds.uk') ?? 'https://www.yourblinds.uk'), '/');
            $body = implode("\n", [
                'A client has requested a supplier be added to the library.',
                '',
                'Supplier: ' . $name,
                'Website:  ' . ($website ?? '—'),
                'From:     ' . ($company !== '' ? $company : 'client #' . $clientId)
                             . ($reqEmail ? ' <' . $reqEmail . '>' : ''),
                'Notes:    ' . ($notes ?? '—'),
                'Price list attached: ' . ($fileName !== null ? 'yes' : 'no'),
                '',
                'Review: ' . $base . '/master-admin/supplier-requests.php',
            ]);
            mailer_send(
                $to,
                'New supplier request: ' . $name,
                $body,
                null,
                null,
                $reqEmail ? ['reply_to_email' => $reqEmail, 'reply_to_name' => ($company ?: $reqEmail)] : null
            );
        }
    } catch (Throwable $e) {
        error_log('[YourBlinds] supplier-request notify failed: ' . $e->getMessage());
    }
}

header('Location: /library/index.php');
exit;
