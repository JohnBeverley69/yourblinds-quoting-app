<?php
declare(strict_types=1);

/**
 * Bulk re-band fabrics on a product. POST + CSRF. Reuses the Fabrics page's
 * bulk-select checkboxes (ids[]) + a band, posted from the "Set band on
 * selected" button. Tenant-scoped to the product.
 */

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

$user      = current_user();
$clientId  = (int) $user['client_id'];
$productId = (int) ($_POST['product_id'] ?? 0);

// Normalise the band the same way the Fabrics page does: drop a "Band " prefix,
// uppercase. An empty band is allowed (clears it) but won't price.
$band = trim((string) ($_POST['band_code'] ?? ''));
$band = (string) preg_replace('/^band\s+/i', '', $band);
$band = strtoupper($band);

$ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn ($n) => $n > 0));

$redirect = '/admin/products/options.php?product_id=' . $productId;

if ($productId <= 0 || !$ids) {
    $_SESSION['flash_error'] = 'Tick the fabrics you want to re-band first.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(
        "UPDATE product_options SET band_code = ?
          WHERE product_id = ? AND client_id = ? AND id IN ($place)"
    );
    $st->execute(array_merge([$band, $productId, $clientId], $ids));
    $n = $st->rowCount();
    $_SESSION['flash_success'] = $n . ' fabric' . ($n === 1 ? '' : 's')
        . ' set to band ' . ($band !== '' ? $band : '(none)') . '.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not re-band: ' . $e->getMessage();
}

header('Location: ' . $redirect);
exit;
