<?php
declare(strict_types=1);

/**
 * Email-verification helpers — shared by signup, resend, and (read side) the
 * login gate. Tokens mirror the password-reset design: a random 64-hex token
 * is emailed; only its SHA-256 hash is stored, with a 24-hour expiry.
 *
 * Sends go through mailer_send() (not a private PHPMailer) so the global
 * "pause emails" switch + the non-production intercept both apply.
 */

require_once __DIR__ . '/../mailer.php';

if (function_exists('verification_create_token')) {
    return;
}

/**
 * Mint a fresh confirmation token for a user. Any earlier unused tokens for the
 * same user are invalidated first, so only the latest link works. Returns the
 * RAW token (to put in the URL) — only its hash is stored.
 */
function verification_create_token(PDO $pdo, int $userId): string
{
    $token   = bin2hex(random_bytes(32));            // 64 hex chars
    $hash    = hash('sha256', $token);
    $expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    try {
        $pdo->prepare(
            'UPDATE email_verifications SET used_at = NOW()
              WHERE user_id = ? AND used_at IS NULL'
        )->execute([$userId]);
    } catch (Throwable $e) { /* best-effort invalidation */ }

    $pdo->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $hash, $expires]);

    return $token;
}

/**
 * Absolute confirmation URL from APP_URL (.env) — never the request Host header
 * (host-header injection). Falls back to a relative path if APP_URL is unset.
 */
function verification_build_url(string $token): string
{
    $base = trim((string) (env('APP_URL', '') ?? ''));
    $path = '/auth/verify.php?token=' . $token;
    if ($base === '') {
        error_log('[YourBlinds] APP_URL not set — verification link emitted as a relative path. '
                . 'Set APP_URL to e.g. https://yourblinds.uk.');
        return $path;
    }
    return rtrim($base, '/') . $path;
}

/** Send the "confirm your email" message. Returns mailer_send()'s result. */
function verification_send_email(string $to, string $url, string $company = ''): bool
{
    $hello = $company !== '' ? "Welcome to YourBlinds, " . $company . '!' : 'Welcome to YourBlinds!';
    $body  = $hello . "\n\n"
           . "Please confirm your email address to activate your account:\n"
           . $url . "\n\n"
           . "This link is valid for 24 hours.\n\n"
           . "If you didn't create a YourBlinds account, you can safely ignore this email.\n\n"
           . "— YourBlinds";

    return mailer_send($to, 'Confirm your YourBlinds account', $body);
}
