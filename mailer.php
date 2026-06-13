<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Global "pause emails" switch lives here. Required at file scope (not lazily
// inside the function) so app_setting_on() is ALWAYS defined when we check it —
// a missing symbol would silently read as "not paused", which on the live site
// is exactly the failure this guard exists to prevent.
require_once __DIR__ . '/_partials/app_settings.php';

/**
 * YourBlinds — generic SMTP send helper.
 *
 * Reads SMTP credentials from .env (MAIL_HOST, MAIL_PORT, MAIL_USERNAME,
 * MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME). Returns true on send, false on any
 * configuration or transport failure (logged to PHP error log).
 *
 * @param string|array     $to          single address or list of addresses
 * @param string           $subject
 * @param string           $body        plain text body
 * @param array|null       $attachment  ['content' => bytes, 'filename' => name, 'mime' => mime]
 * @param string|null      $htmlBody    optional HTML alternative
 * @param array|null       $opts        per-send overrides:
 *                                       ['from_email','from_name',
 *                                        'reply_to_email','reply_to_name']
 *                                       — e.g. send "from" the trade client's
 *                                       own settings. Invalid emails fall back
 *                                       to the MAIL_FROM default.
 */
function mailer_send(
    $to,
    string $subject,
    string $body,
    ?array $attachment = null,
    ?string $htmlBody = null,
    ?array $opts = null
): bool {
    if (!class_exists(PHPMailer::class)) {
        error_log('[YourBlinds] PHPMailer not installed — run "composer install" to enable email.');
        return false;
    }

    // ── Global "pause emails" switch (testing mode) ─────────────────────────
    // A super-admin toggle (Master Admin) that stops ALL outgoing email
    // site-wide — used while a QA tester pokes the LIVE site, so they can't
    // email a real supplier or customer. Works in every environment, including
    // production. Drops + logs the message and reports success so app flows
    // continue exactly as if it had sent. Defensive: pre-migration / DB error
    // simply means "not paused".
    if (function_exists('app_setting_on') && app_setting_on('email_paused')) {
        $origList = implode(', ', array_filter(array_map(
            static fn ($r) => trim((string) $r), (array) $to
        ), static fn ($r) => $r !== ''));
        error_log('[YourBlinds] email PAUSED (testing mode) — would have gone to: '
            . ($origList !== '' ? $origList : '(no recipient)') . ' | subject: ' . $subject);
        return true;
    }

    // ── Non-production guard ────────────────────────────────────────────────
    // A staging / test copy must NEVER email a real customer or supplier. Off
    // production, redirect every message to a single intercept inbox
    // (MAIL_INTERCEPT_TO) so testers can hammer "send" with zero real-world
    // fallout; if no inbox is configured, drop it silently. We return true in
    // both cases so the app's flows continue exactly as if the mail had sent.
    // Inert on production (APP_ENV defaults to 'production').
    $appEnv = strtolower((string) (env('APP_ENV', 'production') ?? 'production'));
    if ($appEnv !== 'production' && $appEnv !== 'prod') {
        $origList  = implode(', ', array_filter(array_map(
            static fn ($r) => trim((string) $r), (array) $to
        ), static fn ($r) => $r !== ''));
        $intercept = trim((string) (env('MAIL_INTERCEPT_TO', '') ?? ''));
        if ($intercept === '' || !filter_var($intercept, FILTER_VALIDATE_EMAIL)) {
            error_log('[YourBlinds][' . $appEnv . '] email suppressed (set MAIL_INTERCEPT_TO to capture) — '
                . 'would have gone to: ' . ($origList !== '' ? $origList : '(no recipient)'));
            return true;
        }
        $to      = $intercept;
        $subject = '[' . strtoupper($appEnv) . ($origList !== '' ? ' → ' . $origList : '') . '] ' . $subject;
    }

    $host     = (string) (env('MAIL_HOST',      'mail.authsmtp.com')     ?? 'mail.authsmtp.com');
    $port     = (int)    (env('MAIL_PORT',      '2525')                  ?? 2525);
    $username = (string) (env('MAIL_USERNAME',  '')                      ?? '');
    $password = (string) (env('MAIL_PASS',      '')                      ?? '');
    $from     = (string) (env('MAIL_FROM',      'noreply@yourblinds.uk') ?? 'noreply@yourblinds.uk');
    $fromName = (string) (env('MAIL_FROM_NAME', 'YourBlinds')            ?? 'YourBlinds');

    if ($host === '' || $username === '' || $password === '') {
        error_log('[YourBlinds] SMTP not configured — set MAIL_HOST / MAIL_USERNAME / MAIL_PASS in .env');
        return false;
    }

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $host;
        $mailer->Port       = $port;
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $username;
        $mailer->Password   = $password;
        $mailer->SMTPSecure = $port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet    = 'UTF-8';
        $mailer->Timeout    = 15;

        // Per-send overrides (e.g. the trade client's own from/reply-to from
        // their Settings). Bad addresses fall back to the configured default.
        $opts      = $opts ?? [];
        $fromEmail = (!empty($opts['from_email']) && filter_var($opts['from_email'], FILTER_VALIDATE_EMAIL))
            ? (string) $opts['from_email'] : $from;
        $fromNm    = !empty($opts['from_name']) ? (string) $opts['from_name'] : $fromName;
        $replyEmail = (!empty($opts['reply_to_email']) && filter_var($opts['reply_to_email'], FILTER_VALIDATE_EMAIL))
            ? (string) $opts['reply_to_email'] : $fromEmail;
        $replyNm    = !empty($opts['reply_to_name']) ? (string) $opts['reply_to_name'] : $fromNm;

        $mailer->setFrom($fromEmail, $fromNm);
        $mailer->addReplyTo($replyEmail, $replyNm);

        foreach ((array) $to as $recipient) {
            $recipient = trim((string) $recipient);
            if ($recipient !== '') {
                $mailer->addAddress($recipient);
            }
        }

        $mailer->Subject = $subject;
        if ($htmlBody !== null) {
            $mailer->isHTML(true);
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = $body;
        } else {
            $mailer->Body = $body;
        }

        if ($attachment !== null && !empty($attachment['content'])) {
            $mailer->addStringAttachment(
                (string) $attachment['content'],
                (string) ($attachment['filename'] ?? 'attachment'),
                'base64',
                (string) ($attachment['mime'] ?? 'application/octet-stream')
            );
        }

        $mailer->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[YourBlinds] PHPMailer error: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('[YourBlinds] Email send failed: ' . $e->getMessage());
    }
    return false;
}
