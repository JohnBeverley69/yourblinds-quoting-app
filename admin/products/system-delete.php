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

$user      = current_user();
$id        = (int) ($_POST['id']         ?? 0);
$productId = (int) ($_POST['product_id'] ?? 0);

if ($id > 0) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // client_markups / client_discounts have FKs on system_id but
        // with NO ACTION (InnoDB forbids cascading actions on a column
        // referenced by a stored generated column — see
        // migrate_markup_per_system.php). Clean them up manually so
        // the parent delete doesn't fail with FK violation.
        $pdo->prepare(
            'DELETE FROM client_markups
              WHERE system_id = ? AND client_id = ?'
        )->execute([$id, $user['client_id']]);
        $pdo->prepare(
            'DELETE FROM client_discounts
              WHERE system_id = ? AND client_id = ?'
        )->execute([$id, $user['client_id']]);

        // The remaining FK cascades wipe price_tables and
        // price_table_rows for this system.
        $stmt = $pdo->prepare(
            'DELETE FROM product_systems WHERE id = ? AND client_id = ?'
        );
        $stmt->execute([$id, $user['client_id']]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_success'] = 'System deleted.';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = 'Could not delete system: ' . $e->getMessage();
    }
}

// Default redirect target.
$default = $productId > 0
    ? '/admin/products/systems.php?product_id=' . $productId
    : '/admin/products/index.php';

// Honour a same-origin return_to from callers (e.g. the setup wizard
// passes its own URL so the user lands back on the wizard after the
// delete). Falls back to $default if return_to is missing or unsafe.
$returnTo = trim((string) ($_POST['return_to'] ?? ''));
$target   = $returnTo !== ''
    ? safe_local_redirect($returnTo, $default)
    : $default;
header('Location: ' . $target);
exit;
