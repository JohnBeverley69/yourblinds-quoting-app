<?php
declare(strict_types=1);

/**
 * Take ownership of a pushed catalogue product (white-label manufacturing).
 *
 * A pushed product carries source_client_id = the master it came from, which is
 * also what routes its orders to that master's factory. A FACTORY tenant that
 * makes the product itself can detach it: clearing the source markers turns it
 * into the tenant's own native product — its orders then route to the tenant's
 * OWN factory (owning factory = COALESCE(source_client_id, client_id)), and it
 * stops receiving master price-pushes.
 *
 * Guarded: only a factory account, only a product it owns that actually came
 * from another account's master.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) ($user['client_id'] ?? 0);
$pid      = (int) ($_POST['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($pid <= 0) {
        $_SESSION['flash_error'] = 'No product specified.';
    } elseif (!function_exists('is_factory_client') || !is_factory_client($clientId)) {
        $_SESSION['flash_error'] = 'Only a factory account can take a product in-house.';
    } else {
        try {
            // Only detach a pushed product this account owns, sourced elsewhere.
            $st = db()->prepare(
                'UPDATE products
                    SET source_client_id = NULL, source_product_id = NULL
                  WHERE id = ? AND client_id = ?
                    AND source_client_id IS NOT NULL AND source_client_id <> ?'
            );
            $st->execute([$pid, $clientId, $clientId]);
            $_SESSION[$st->rowCount() > 0 ? 'flash_success' : 'flash_error'] = $st->rowCount() > 0
                ? 'This is now your own product — made by you, and its orders route to your factory.'
                : 'Nothing to take over here (already yours, or not a catalogue product).';
        } catch (Throwable $e) {
            error_log('[YourBlinds] detach-source failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Could not update this product.';
        }
    }

    header('Location: /admin/products/edit.php?id=' . $pid);
    exit;
}

header('Location: /admin/products/index.php');
exit;
