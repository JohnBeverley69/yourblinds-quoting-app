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

// Permission gate. Sales-side targets (sent/accepted/declined) need
// can_create_quotes; order-side targets (ordered/invoiced/paid) need
// can_create_orders. Admins bypass.
$isAdmin = ($user['role'] ?? '') === 'admin';
$_perms  = current_user_permissions();
if (!qb_user_can_change_to($isAdmin, $_perms, $target)) {
    qb_flash_redirect(
        '/quote-builder/edit.php?id=' . $quoteId,
        'error',
        'You don\'t have permission to mark this quote as "' . $target . '".'
    );
}

// Update status, plus the timestamp columns where relevant.
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE quotes SET status = ? WHERE id = ? AND client_id = ?')
        ->execute([$target, $quoteId, $clientId]);

    // sent_at gets stamped when moving INTO sent (obvious) AND when
    // jumping straight from draft → accepted/declined (so the audit
    // trail still answers "when did this quote leave draft?" instead
    // of just "when did the customer accept?"). Idempotent — won't
    // overwrite an existing sent_at.
    if (in_array($target, ['sent', 'accepted', 'declined'], true)
        && empty($quote['sent_at'])) {
        $pdo->prepare('UPDATE quotes SET sent_at = NOW() WHERE id = ?')
            ->execute([$quoteId]);
    }
    if ($target === 'accepted' && empty($quote['accepted_at'])) {
        $pdo->prepare('UPDATE quotes SET accepted_at = NOW() WHERE id = ?')
            ->execute([$quoteId]);
    }

    // First time a quote lands in 'accepted', seed deposit_amount from
    // the tenant's default deposit setting. Skipped if a deposit's
    // already been set (so re-accepting a previously-declined-and-
    // reopened quote doesn't overwrite a manual figure).
    //
    // Two modes ('percent' / 'flat'). Defensive against partially-
    // migrated databases: the mode/flat columns only exist after
    // migrate_deposit_flat_mode.php has run, and deposit_amount only
    // exists after migrate_quote_deposits.php has run. Either query
    // failing just means "skip the seed", not "500 the whole accept
    // action" — the trade user can still set the deposit manually
    // on the quote Edit page once the migrations are deployed.
    if ($target === 'accepted' && ($quote['deposit_amount'] ?? null) === null) {
        try {
            // Try the full multi-mode lookup first.
            try {
                $dpStmt = $pdo->prepare(
                    'SELECT default_deposit_mode,
                            default_deposit_percent,
                            default_deposit_flat
                       FROM client_settings WHERE client_id = ? LIMIT 1'
                );
                $dpStmt->execute([$clientId]);
                $dp = $dpStmt->fetch() ?: [];
            } catch (PDOException $e) {
                // SQLSTATE 42S22 = column not found (MySQL 1054).
                // Flat-mode columns missing → fall back to percent-only.
                // Anything else (real DB error) bubbles up to the
                // outer catch below where it's properly handled.
                if ($e->getCode() !== '42S22') throw $e;
                $dpStmt = $pdo->prepare(
                    'SELECT default_deposit_percent
                       FROM client_settings WHERE client_id = ? LIMIT 1'
                );
                $dpStmt->execute([$clientId]);
                $dp = $dpStmt->fetch() ?: [];
            }

            $total = (float) $quote['total'];
            $mode  = (string) ($dp['default_deposit_mode'] ?? 'percent');
            if ($mode === 'flat') {
                $depositAmt = min((float) ($dp['default_deposit_flat'] ?? 0), $total);
            } else {
                $depositAmt = $total * ((float) ($dp['default_deposit_percent'] ?? 50)) / 100;
            }
            $depositAmt = round($depositAmt, 2);

            $pdo->prepare(
                'UPDATE quotes SET deposit_amount = ? WHERE id = ?'
            )->execute([$depositAmt, $quoteId]);
        } catch (PDOException $e) {
            // deposit_amount column missing → skip seed quietly. Any
            // other PDOException bubbles up to the outer transaction
            // handler. Tightened from a blanket Throwable catch so
            // genuine errors (connection drop, etc.) aren't hidden.
            if ($e->getCode() !== '42S22') throw $e;
            error_log('Deposit auto-seed skipped on accept (column missing): '
                . $e->getMessage());
        }
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
                            . ' "Pending Fitting" tray — drag it onto the'
                            . ' right date and assign a fitter when ready.';
        }
    }

    // Declining cancels the job — pull its pending install off the calendar so
    // a phantom fitting doesn't linger. Never removes a completed install or a
    // measure visit.
    if ($target === 'declined') {
        $removed = qb_remove_fitting_for_quote($pdo, $quoteId, $clientId);
        if ($removed > 0) {
            $appointmentMsg = ' The pending fitting has been removed from the calendar.';
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
