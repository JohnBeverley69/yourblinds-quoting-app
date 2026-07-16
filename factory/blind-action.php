<?php
declare(strict_types=1);

/**
 * Factory action (Phase B): move one blind along its route.
 *
 * POST: job_id, action (start|done|back), _csrf, optional return_to.
 * Factory staff only. Redirects back to the floor (or the given return_to).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';

requireFactory();

$fallback = '/factory/floor.php';
$backTo   = safe_local_redirect((string) ($_POST['return_to'] ?? ''), $fallback);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $backTo); exit; }
csrf_check();

$pdo = db();
if (!bj_tables_ready($pdo)) {
    $_SESSION['flash_error'] = 'Floor tracking isn\'t set up yet — run /migrate_factory_blind_jobs.php.';
    header('Location: ' . $backTo);
    exit;
}

$jobId  = (int) ($_POST['job_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$user   = current_user();
$userId = (int) ($user['user_id'] ?? 0) ?: null;

if ($jobId <= 0) { header('Location: ' . $backTo); exit; }

try {
    switch ($action) {
        case 'start': bj_start($pdo, $jobId, $userId);   break;
        case 'done':  bj_advance($pdo, $jobId, $userId); break;
        case 'back':  bj_back($pdo, $jobId, $userId);    break;
        default:
            $_SESSION['flash_error'] = 'Unknown action.';
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update that blind: ' . $e->getMessage();
}

header('Location: ' . $backTo);
exit;
