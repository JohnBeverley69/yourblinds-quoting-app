<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$target   = trim((string) ($_POST['target_status'] ?? ''));

$quote = qb_load_quote_or_404($quoteId, $clientId);
$current = (string) $quote['status'];

if (!in_array($target, qb_allowed_transitions($current), true)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        "Can't move from $current to $target."
    );
}

// Update status, plus the timestamp columns where relevant.
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE quotes SET status = ? WHERE id = ? AND client_id = ?')
        ->execute([$target, $quoteId, $clientId]);

    if ($target === 'sent' && empty($quote['sent_at'])) {
        $pdo->prepare('UPDATE quotes SET sent_at = NOW() WHERE id = ?')
            ->execute([$quoteId]);
    }
    if ($target === 'accepted' && empty($quote['accepted_at'])) {
        $pdo->prepare('UPDATE quotes SET accepted_at = NOW() WHERE id = ?')
            ->execute([$quoteId]);
    }

    // Mirror the public-accept side: when the trade user marks a quote
    // accepted, also drop a placeholder installation appointment on the
    // calendar so it's never forgotten. Idempotent — safe to re-run if
    // the status was already accepted before this commit landed.
    $appointmentMsg = '';
    if ($target === 'accepted') {
        $apptId = qb_create_appointment_from_quote($pdo, $quoteId);
        if ($apptId !== null) {
            $appointmentMsg = ' Installation appointment is in the calendar\'s'
                            . ' "Pending Scheduling" tray — drag it onto the'
                            . ' right date and assign a fitter when ready.';
        }
    }

    $pdo->commit();
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'success',
        'Status: ' . $target . '.' . $appointmentMsg
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Could not change status: ' . $e->getMessage()
    );
}
