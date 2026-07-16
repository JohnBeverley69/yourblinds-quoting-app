<?php
declare(strict_types=1);

/**
 * Factory action: move an incoming order through the production flow.
 *
 * POST: quote_id, status (target: new|received|in_production|made|dispatched),
 *       _csrf. Factory staff only; the order must carry Beverley lines.
 *
 * 'new' removes the factory_jobs row (order drops back to unactioned). Any
 * other target upserts the row with the new status + who/when.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/blind_jobs.php';

requireFactory();

$backTo = '/factory/incoming-orders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $backTo);
    exit;
}
csrf_check();

/** The production flow, in order. 'new' = no factory_jobs row. */
const FACTORY_STAGES = ['new', 'received', 'in_production', 'made', 'dispatched'];
const FACTORY_STAGE_LABELS = [
    'new'           => 'new',
    'received'      => 'received',
    'in_production' => 'in production',
    'made'          => 'made',
    'dispatched'    => 'dispatched',
];

$pdo     = db();
$MASTER  = factory_client_id();
$quoteId = (int) ($_POST['quote_id'] ?? 0);
$target  = (string) ($_POST['status'] ?? '');
$user    = current_user();
$userId  = (int) ($user['user_id'] ?? 0);

if (!in_array($target, FACTORY_STAGES, true)) {
    $_SESSION['flash_error'] = 'Unknown production stage.';
    header('Location: ' . $backTo);
    exit;
}

// Guard: only orders that actually carry Beverley lines can be actioned.
$isBev = false;
if ($quoteId > 0) {
    $chk = $pdo->prepare(
        "SELECT 1 FROM quote_items qi
           JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND p.source_client_id = ? LIMIT 1"
    );
    $chk->execute([$quoteId, $MASTER]);
    $isBev = (bool) $chk->fetchColumn();
}

if ($quoteId <= 0 || !$isBev) {
    $_SESSION['flash_error'] = 'That order could not be actioned.';
    header('Location: ' . $backTo);
    exit;
}

try {
    if ($target === 'new') {
        $pdo->prepare("DELETE FROM factory_jobs WHERE quote_id = ?")->execute([$quoteId]);
        // Reset pulls the order's blinds back off the floor too.
        if (bj_tables_ready($pdo)) bj_clear_order($pdo, $quoteId);
        $_SESSION['flash_success'] = 'Order moved back to new.';
    } else {
        $recvAt = $target === 'received' ? date('Y-m-d H:i:s') : null;
        $recvBy = $target === 'received' ? ($userId ?: null)   : null;
        $pdo->prepare(
            "INSERT INTO factory_jobs (quote_id, status, status_at, status_by, received_at, received_by)
             VALUES (?, ?, NOW(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 status      = VALUES(status),
                 status_at   = NOW(),
                 status_by   = VALUES(status_by),
                 received_at = COALESCE(received_at, VALUES(received_at)),
                 received_by = COALESCE(received_by, VALUES(received_by))"
        )->execute([$quoteId, $target, $userId ?: null, $recvAt, $recvBy]);

        // Moving into production releases the order's Beverley blinds onto the
        // floor (idempotent — re-entering production won't duplicate them).
        $released = '';
        if ($target === 'in_production' && bj_tables_ready($pdo)) {
            $n = bj_release_order($pdo, $quoteId, $MASTER);
            if ($n > 0) $released = " {$n} blind" . ($n === 1 ? '' : 's') . ' released to the floor.';
        }
        $_SESSION['flash_success'] = 'Order moved to ' . (FACTORY_STAGE_LABELS[$target] ?? $target) . '.' . $released;
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Could not update the order: ' . $e->getMessage()
        . ' — have the factory_jobs migrations been run?';
}

header('Location: ' . $backTo);
exit;
