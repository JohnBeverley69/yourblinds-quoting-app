<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /admin/products/index.php');
    exit;
}

csrf_check();

$user = current_user();

// Accept either a single id (per-row Delete button) or ids[] (bulk
// select). Merge, dedupe, drop non-positives — tenant scoping on the
// DELETE keeps it safe even with a crafted form.
$ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
if (($single = (int) ($_POST['id'] ?? 0)) > 0) $ids[] = $single;
$ids = array_values(array_unique(array_filter(
    array_map('intval', $ids),
    static fn ($n) => $n > 0
)));

if ($ids) {
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    // ON DELETE CASCADE on options/extras/systems/price_tables handles children.
    $stmt = db()->prepare("DELETE FROM products WHERE id IN ($ph) AND client_id = ?");
    $stmt->execute(array_merge($ids, [$user['client_id']]));
    $n = $stmt->rowCount();
    if ($n > 0) {
        $_SESSION['flash_success'] = $n === 1 ? 'Product deleted.' : "$n products deleted.";
    }
}

header('Location: /admin/products/index.php');
exit;
