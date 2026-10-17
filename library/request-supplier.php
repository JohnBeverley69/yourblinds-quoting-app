<?php
declare(strict_types=1);

/**
 * A company asks for a supplier to be added (from library/index.php — the
 * "Supplier catalogues" page): the supplier, their contact, the company's own
 * account number with them, who's asking, and any number of files (price
 * lists, option sheets, EDI files). POST + CSRF. Logged in supplier_requests
 * (+ supplier_request_files), shown in master-admin/supplier-requests.php and
 * emailed to the team.
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
$back     = '/library/index.php#request';

$field = static function (string $k, int $max): ?string {
    $v = trim((string) ($_POST[$k] ?? ''));
    return $v === '' ? null : mb_substr($v, 0, $max);
};
$name            = $field('supplier_name', 160);
$website         = $field('website', 255);
$supplierContact = $field('supplier_contact', 160);
$supplierEmail   = $field('supplier_email', 255);
$supplierPhone   = $field('supplier_phone', 60);
$accountNumber   = $field('account_number', 80);
$requesterName   = $field('requester_name', 160);
$requesterEmail  = $field('requester_email', 255);
$notes           = $field('notes', 2000);

$fail = static function (string $msg) use ($back): void {
    $_SESSION['flash_error'] = $msg;
    header('Location: ' . $back);
    exit;
};

if ($name === null)           $fail('Please enter the supplier company.');
if ($requesterName === null)  $fail('Please enter your name.');
if ($requesterEmail === null || !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) $fail('Please enter a valid email for yourself.');
if ($supplierEmail !== null && !filter_var($supplierEmail, FILTER_VALIDATE_EMAIL))   $fail('The supplier contact email doesn\'t look right.');

// ── Attachments: validate them all before saving any ─────────────────────
const SR_MAX_FILES = 10;
const SR_MAX_BYTES = 20 * 1024 * 1024;
$allowedExt = ['xlsx', 'xlsm', 'xls', 'csv', 'ods', 'pdf', 'txt', 'zip'];

$incoming = [];
$f = $_FILES['files'] ?? null;
if (is_array($f) && is_array($f['name'] ?? null)) {
    foreach ($f['name'] as $i => $orig) {
        $err = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $orig = (string) $orig;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) $fail('“' . $orig . '” is too large (max 20 MB each).');
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'][$i])) $fail('“' . $orig . '” didn\'t upload — please try again.');
        $ext = strtolower((string) pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) $fail('“' . $orig . '” isn\'t a file type we take — use Excel, CSV, PDF or ZIP.');
        $size = (int) ($f['size'][$i] ?? 0);
        if ($size > SR_MAX_BYTES) $fail('“' . $orig . '” is too large (max 20 MB each).');
        $incoming[] = ['orig' => $orig, 'ext' => $ext, 'tmp' => (string) $f['tmp_name'][$i], 'size' => $size];
    }
}
if (count($incoming) > SR_MAX_FILES) $fail('Please attach up to ' . SR_MAX_FILES . ' files (zip the rest together).');

$dir = __DIR__ . '/../uploads/supplier-requests';
if ($incoming && !is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    $fail('Could not save the files — please try again.');
}

$pdo = db();
$saved = [];   // [name, path, size] — removed again if the insert fails
try {
    foreach ($incoming as $in) {
        $stored = 'req_' . $clientId . '_' . bin2hex(random_bytes(8)) . '.' . $in['ext'];
        if (!@move_uploaded_file($in['tmp'], $dir . '/' . $stored)) {
            throw new RuntimeException('Could not save “' . $in['orig'] . '”.');
        }
        $saved[] = [mb_substr($in['orig'], 0, 255), 'uploads/supplier-requests/' . $stored, $in['size']];
    }

    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO supplier_requests
            (client_id, email, requester_name, supplier_name, supplier_contact, supplier_email,
             supplier_phone, account_number, website, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$clientId, $requesterEmail, $requesterName, $name, $supplierContact, $supplierEmail,
                $supplierPhone, $accountNumber, $website, $notes]);
    $reqId = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO supplier_request_files (request_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?)');
    foreach ($saved as [$fn, $fp, $sz]) $ins->execute([$reqId, $fn, $fp, $sz]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($saved as [, $fp]) @unlink(__DIR__ . '/../' . $fp);
    error_log('[YourBlinds] supplier request failed: ' . $e->getMessage());
    $fail('Sorry — the request couldn\'t be saved. Please try again.');
}

$_SESSION['flash_success'] = 'Thanks — your request for “' . $name . '” has been sent'
    . ($saved ? ' with ' . count($saved) . ' file' . (count($saved) === 1 ? '' : 's') . '.' : '.')
    . ' We\'ll be in touch.';

// Tell the team. Best-effort: the request is already logged, so a mail
// failure must never reach the company.
try {
    require_once __DIR__ . '/../mailer.php';
    if (function_exists('mailer_send')) {
        $company = (string) ($user['company_name'] ?? '');
        $to   = (string) (env('SUPPLIER_REQUEST_NOTIFY', '') ?: 'hello@yourblinds.uk');
        $base = rtrim((string) (env('APP_URL', 'https://yourblinds.uk') ?: 'https://yourblinds.uk'), '/');
        $body = implode("\n", [
            'A company has asked for a supplier to be added.',
            '',
            'Supplier:        ' . $name,
            'Website:         ' . ($website ?? '—'),
            'Supplier contact: ' . ($supplierContact ?? '—')
                . ($supplierEmail ? ' <' . $supplierEmail . '>' : '')
                . ($supplierPhone ? ', ' . $supplierPhone : ''),
            'Their account no: ' . ($accountNumber ?? '—'),
            '',
            'From:            ' . $requesterName . ' <' . $requesterEmail . '>, '
                . ($company !== '' ? $company : 'client #' . $clientId),
            'Notes:           ' . ($notes ?? '—'),
            'Files attached:  ' . count($saved),
            '',
            'Review: ' . $base . '/master-admin/supplier-requests.php',
        ]);
        mailer_send($to, 'New supplier request: ' . $name, $body, null, null,
            ['reply_to_email' => $requesterEmail, 'reply_to_name' => ($company ?: $requesterName)]);
    }
} catch (Throwable $e) {
    error_log('[YourBlinds] supplier-request notify failed: ' . $e->getMessage());
}

header('Location: /library/index.php');
exit;
