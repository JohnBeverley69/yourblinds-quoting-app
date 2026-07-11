<?php
declare(strict_types=1);

/**
 * Factory action: mark an incoming order as received (into production), or undo.
 *
 * POST: quote_id, action ('receive' | 'unreceive'), _csrf.
 * Factory staff only. The order must actually contain Beverley lines — you
 * can't action an arbitrary quote.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireFactory();

$backTo = '/factory/incoming-orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $backTo);
    exit;
}
csrf_check();

$pdo     = db();
$MASTER  = factory_client_id();
$quoteId = (int) ($_POST['quote_id'] ?? 0);
$action  = (string) ($_POST['action'] ?? '');
$user    = current_user();
$userId  = (int) ($user['user_id'] ?? 0);

// Guard: the quote must exist and carry at least one Beverley line, so this
// action can only ever touch orders that legitimately belong to the factory.
$isBev = false;
if ($quoteId > 0) {
    $chk = $pdo->prepare(
        "SELECT 1
           FROM quote_items qi
           JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND p.source_client_id = ?
          LIMIT 1"
    );
    $chk->execute([$quoteId, $MASTER]);
    $isBev = (bool) $chk->fetchColumn();
}

if ($quoteId > 0 && $isBev) {
    try {
        if ($action === 'receive') {
            $pdo->prepare(
                "INSERT INTO factory_jobs (quote_id, status, received_at, received_by)
                 VALUES (?, 'received', NOW(), ?)
                 ON DUPLICATE KEY UPDATE status = 'received',
                                         received_at = NOW(),
                                         received_by = VALUES(received_by)"
            )->execute([$quoteId, $userId ?: null]);
            $_SESSION['flash_success'] = 'Order marked as received — into production.';
        } elseif ($action === 'unreceive') {
            $pdo->prepare("DELETE FROM factory_jobs WHERE quote_id = ?")->execute([$quoteId]);
            $_SESSION['flash_success'] = 'Order moved back to new.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update the order: ' . $e->getMessage()
            . ' — has migrate_factory_jobs.php been run?';
    }
} else {
    $_SESSION['flash_error'] = 'That order could not be actioned.';
}

header('Location: ' . $backTo);
exit;
