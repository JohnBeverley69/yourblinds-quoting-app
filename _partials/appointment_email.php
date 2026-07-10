<?php
declare(strict_types=1);

/**
 * Customer-facing appointment confirmation email (Phase 2 of feature_ampm_slots).
 *
 * Emails the customer the Morning/Afternoon WINDOW they've been booked into for
 * a quote (measure) visit — never an exact time. Plain text, sent through the
 * shared mailer_send() so it honours the global email-pause switch and the
 * non-production intercept. Replies go to the tenant's reply-to when set.
 *
 * Best-effort: returns mailer_send()'s bool, or false if there's no valid
 * recipient / unrecognised window / bad date. Callers must not let a false
 * result roll back the booking — the appointment is saved regardless.
 */

require_once __DIR__ . '/slot_window.php';

if (!function_exists('compose_appointment_slot_email')) {
    /**
     * Build the subject + plain-text body for a window confirmation. Pure — no
     * DB, no transport — so it's unit-testable. Returns null when the window is
     * unrecognised or the date can't be parsed (caller then skips the send).
     *
     * @return array{subject:string,body:string}|null
     */
    function compose_appointment_slot_email(
        string $company,
        string $customerName,
        string $date,
        string $slotWindow,
        ?string $addressLine = null,
        string $replyTo = ''
    ): ?array {
        if (!is_ampm_window($slotWindow)) {
            return null;
        }
        $dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($dateObj === false) {
            return null;
        }

        $greeting = trim($customerName) !== '' ? trim($customerName) : 'there';
        $window   = ampm_window_label($slotWindow);      // "Morning (9am–1pm)"
        $dateStr  = $dateObj->format('l j F Y');          // "Tuesday 15 July 2026"

        $subject = 'Your appointment'
            . ($company !== '' ? ' with ' . $company : '')
            . ' — ' . $dateObj->format('j M Y');

        $body  = "Hello {$greeting},\n\n";
        $body .= "This confirms your appointment:\n\n";
        $body .= "  {$dateStr}\n";
        $body .= "  {$window}\n\n";
        if ($addressLine !== null && trim($addressLine) !== '') {
            $body .= "Where: " . trim($addressLine) . "\n\n";
        }
        $body .= "We'll aim to be with you within that window rather than at a fixed time. "
               . "If you need to change or cancel, just reply to this email"
               . ($replyTo !== '' ? " ({$replyTo})" : '') . ".\n\n";
        $body .= "Kind regards,\n" . ($company !== '' ? $company : 'The team');

        return ['subject' => $subject, 'body' => $body];
    }
}

if (!function_exists('send_appointment_slot_email')) {
    function send_appointment_slot_email(
        PDO $pdo,
        int $clientId,
        string $toEmail,
        string $customerName,
        string $date,
        string $slotWindow,
        ?string $addressLine = null
    ): bool {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        require_once __DIR__ . '/../mailer.php';
        if (!function_exists('mailer_send')) {
            return false;
        }

        // Company name for the subject + sign-off.
        $company = '';
        try {
            $cs = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
            $cs->execute([$clientId]);
            $company = (string) $cs->fetchColumn();
        } catch (Throwable $e) { /* ignore — falls back to a generic sign-off */ }

        // Optional tenant from-name / reply-to so the customer's reply reaches
        // the trade client, not the shared noreply box. Columns are optional.
        $fromName = $company !== '' ? $company : 'YourBlinds';
        $replyTo  = '';
        try {
            $rs = $pdo->prepare(
                'SELECT email_from_name, reply_to_email FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $rs->execute([$clientId]);
            $row = $rs->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['email_from_name'])) $fromName = (string) $row['email_from_name'];
            if (!empty($row['reply_to_email']))  $replyTo  = (string) $row['reply_to_email'];
        } catch (Throwable $e) { /* columns optional — skip overrides */ }

        $mail = compose_appointment_slot_email(
            $company, $customerName, $date, $slotWindow, $addressLine, $replyTo
        );
        if ($mail === null) {
            return false;   // unrecognised window / bad date
        }

        $opts = ['from_name' => $fromName];
        if ($replyTo !== '') {
            $opts['reply_to_email'] = $replyTo;
            $opts['reply_to_name']  = $fromName;
        }

        return mailer_send($toEmail, $mail['subject'], $mail['body'], null, null, $opts);
    }
}
