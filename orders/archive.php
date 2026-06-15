<?php
declare(strict_types=1);

/**
 * Archive / unarchive quotes (soft, reversible). POST + CSRF.
 *
 * Reuses the Order-history bulk checkboxes (quote_ids[]). `do` = archive |
 * unarchive. Tenant-scoped. Returns to the list preserving its filters.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /orders/index.php');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

$ids = $_POST['quote_ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($n) => $n > 0)));

$do = ((string) ($_POST['do'] ?? 'archive')) === 'unarchive' ? 'unarchive' : 'archive';

// Build the return URL from the list's preserved filters.
$scope  = (($_POST['return_scope']  ?? '') === 'quotes') ? 'quotes' : 'orders';
$status = trim((string) ($_POST['return_status'] ?? ''));
$qStr   = trim((string) ($_POST['return_q'] ?? ''));
$view   = (($_POST['return_view'] ?? '') === 'archived') ? 'archived' : 'active';
$return = '/orders/index.php?scope=' . urlencode($scope)
        . ($status !== '' ? '&status=' . urlencode($status) : '')
        . ($qStr   !== '' ? '&q=' . urlencode($qStr) : '')
        . ($view === 'archived' ? '&view=archived' : '');

if ($ids) {
    try {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $sql = $do === 'archive'
            ? "UPDATE quotes SET archived_at = NOW() WHERE client_id = ? AND id IN ($place)"
            : "UPDATE quotes SET archived_at = NULL  WHERE client_id = ? AND id IN ($place)";
        $st = db()->prepare($sql);
        $st->execute(array_merge([$clientId], $ids));
        $n = $st->rowCount();
        $_SESSION['flash_success'] = $n . ' ' . ($n === 1 ? 'job' : 'jobs') . ' '
            . ($do === 'archive' ? 'archived.' : 'restored to active.');
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage()
            . ' — has migrate_quote_archive.php been run?';
    }
} else {
    $_SESSION['flash_error'] = 'No jobs were selected.';
}

header('Location: ' . $return);
exit;
