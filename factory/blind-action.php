<?php
declare(strict_types=1);

/**
 * Factory action: move one of a blind's streams along its route.
 *
 * POST: stream_id, action (start|done|back|set_stage), _csrf, optional
 * return_to and step_id. Factory staff only.
 *
 * Actions are per STREAM, not per blind: a vertical's headrail and fabric move
 * independently and never imply anything about each other.
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
    $_SESSION['flash_error'] = 'Floor tracking isn\'t set up yet — run /migrate_factory_blind_jobs.php and /migrate_route_streams.php.';
    header('Location: ' . $backTo);
    exit;
}

$streamId = (int) ($_POST['stream_id'] ?? 0);
$action   = (string) ($_POST['action'] ?? '');
$user     = current_user();
$userId   = (int) ($user['user_id'] ?? 0) ?: null;

if ($streamId <= 0) { header('Location: ' . $backTo); exit; }

try {
    switch ($action) {
        case 'start': bj_stream_start($pdo, $streamId, $userId);   break;
        case 'done':  bj_stream_advance($pdo, $streamId, $userId); break;
        case 'back':  bj_stream_back($pdo, $streamId, $userId);    break;
        case 'set_stage':
            // From the floor strip: "done" = this stream ran off the end.
            $step = (string) ($_POST['step_id'] ?? '');
            bj_stream_set_stage($pdo, $streamId, $step === 'done' ? null : (int) $step, $userId);
            break;
        default:
            $_SESSION['flash_error'] = 'Unknown action.';
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update that blind: ' . $e->getMessage();
}

header('Location: ' . $backTo);
exit;
