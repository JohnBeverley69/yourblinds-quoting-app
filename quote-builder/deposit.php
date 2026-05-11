<?php
declare(strict_types=1);

/**
 * Deposit operations on an accepted quote.
 *
 * Two actions, both POST + CSRF + tenant-scoped:
 *
 *   _action=save_amount
 *     Updates deposit_amount to the typed value.
 *
 *   _action=mark_paid
 *     Stamps deposit_paid_at = NOW() if currently NULL, OR clears it
 *     back to NULL if already set (toggle). The button label in the
 *     UI flips between "Mark deposit paid" and "Mark unpaid" to make
 *     this obvious.
 */

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
$action   = (string) ($_POST['_action'] ?? '');
$quote    = qb_load_quote_or_404($quoteId, $clientId);

if (!qb_is_editable($quote)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'Quote is locked. Reopen as draft to change the deposit.'
    );
}

if ($action === 'save_amount') {
    $raw = trim((string) ($_POST['deposit_amount'] ?? ''));
    if ($raw === '') {
        // Empty input clears the deposit (back to "not required").
        db()->prepare(
            'UPDATE quotes SET deposit_amount = NULL WHERE id = ? AND client_id = ?'
        )->execute([$quoteId, $clientId]);
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId,
            'success',
            'Deposit cleared.'
        );
    }
    if (!is_numeric($raw) || (float) $raw < 0) {
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId,
            'error',
            'Deposit must be a non-negative number.'
        );
    }
    $amt = round((float) $raw, 2);
    db()->prepare(
        'UPDATE quotes SET deposit_amount = ? WHERE id = ? AND client_id = ?'
    )->execute([$amt, $quoteId, $clientId]);

    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'success',
        'Deposit set to £' . number_format($amt, 2) . '.'
    );
}

if ($action === 'mark_paid') {
    $isPaid = !empty($quote['deposit_paid_at']);
    if ($isPaid) {
        db()->prepare(
            'UPDATE quotes SET deposit_paid_at = NULL WHERE id = ? AND client_id = ?'
        )->execute([$quoteId, $clientId]);
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId,
            'success',
            'Deposit marked unpaid.'
        );
    } else {
        db()->prepare(
            'UPDATE quotes SET deposit_paid_at = NOW() WHERE id = ? AND client_id = ?'
        )->execute([$quoteId, $clientId]);
        qb_flash_redirect(
            '/quote-builder/edit.php?id=' . $quoteId,
            'success',
            'Deposit marked paid.'
        );
    }
}

qb_flash_redirect(
    '/quote-builder/edit.php?id=' . $quoteId,
    'error',
    'Unknown deposit action.'
);
