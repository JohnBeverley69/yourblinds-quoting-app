<?php
declare(strict_types=1);

/**
 * Bulk-delete quotes from /quote-history/index.php. POST-only.
 *
 * Body shape:
 *   csrf_token
 *   quote_ids[] = N, M, ...
 *
 * Tenant-scoped: the DELETE has WHERE client_id = ? so a crafted form
 * can't reach into another tenant's quotes even with a valid CSRF.
 * ON DELETE CASCADE on quote_items + quote_item_extras + appointments
 * handles the children.
 *
 * Redirects back to /quote-history/index.php with a flash message
 * stating how many were removed. Idempotent w.r.t. already-gone rows
 * (DELETE just returns 0 rowCount for them).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

$ids = $_POST['quote_ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    static fn ($n) => $n > 0
)));

// Preserve the filter / search the user was on so the redirect lands
// them back on the same view — useful when they're cleaning up a
// "drafts only" list and want to keep filtering after the delete.
$status = trim((string) ($_POST['return_status'] ?? ''));
$q      = trim((string) ($_POST['return_q'] ?? ''));
$qs     = [];
if ($status !== '') $qs[] = 'status=' . urlencode($status);
if ($q      !== '') $qs[] = 'q='      . urlencode($q);
$back   = '/quote-history/index.php' . ($qs ? '?' . implode('&', $qs) : '');

if (!$ids) {
    $_SESSION['flash_error'] = 'No quotes selected.';
    header('Location: ' . $back);
    exit;
}

// Parametrised IN. Tenant-scoped via the trailing client_id placeholder
// so the user can never accidentally reach another tenant's quotes
// even with a crafted form post.
$ph     = implode(',', array_fill(0, count($ids), '?'));
$stmt   = db()->prepare(
    "DELETE FROM quotes WHERE id IN ($ph) AND client_id = ?"
);
$params = array_merge($ids, [$clientId]);
$stmt->execute($params);
$deleted = $stmt->rowCount();

$_SESSION['flash_success'] =
    $deleted === 1
        ? '1 quote deleted.'
        : "$deleted quotes deleted.";
header('Location: ' . $back);
exit;
