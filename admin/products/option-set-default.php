<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_partials/product_lock.php';

/**
 * Make a fabric the product's default (or stop it being one). POST + CSRF,
 * from the Fabrics page's per-row "Make default" button. The default is what
 * the quote builder / InstaPrice fill in before anyone types, so the common
 * pick — Elegant White on Forest Wood — needs no search.
 *
 * One default per band: making a fabric the default clears any other in its
 * band on this product. A band with none still finds the same-named fabric
 * (see the front-ends), so ticking Elegant White once covers String AND Tape.
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
$id        = (int) ($_POST['id'] ?? 0);
$on        = !empty($_POST['on']);
pl_require_unlocked_any([$productId, pl_product_of('option', $id)], '/admin/products/options.php?product_id=' . $productId);

$redirect = '/admin/products/options.php?product_id=' . $productId;
$pdo = db();

try {
    $st = $pdo->prepare('SELECT band_code, name FROM product_options WHERE id = ? AND product_id = ? AND client_id = ?');
    $st->execute([$id, $productId, $clientId]);
    $row = $st->fetch();
    if (!$row) {
        $_SESSION['flash_error'] = 'That fabric is not on this product.';
        header('Location: ' . $redirect);
        exit;
    }

    $pdo->beginTransaction();
    if ($on) {
        $pdo->prepare(
            'UPDATE product_options SET is_default = 0
              WHERE product_id = ? AND client_id = ? AND band_code <=> ? AND id <> ?'
        )->execute([$productId, $clientId, $row['band_code'], $id]);
    }
    $pdo->prepare('UPDATE product_options SET is_default = ? WHERE id = ?')->execute([$on ? 1 : 0, $id]);
    $pdo->commit();

    $_SESSION['flash_success'] = $on
        ? $row['name'] . ' is now the default — the order page fills it in to start with.'
        : $row['name'] . ' is no longer the default.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('option-set-default failed: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not change the default — has migrate_fabric_default.php been run?';
}

header('Location: ' . $redirect);
exit;
