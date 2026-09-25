<?php
declare(strict_types=1);

/**
 * One free trial per person (see migrate_trial_grants.php).
 *
 * Every self sign-up used to get a fresh 30-day trial of all paid plans, so
 * signing up again with another email simply started another trial. A sign-up
 * now only gets the trial when BOTH are new:
 *   - the email address, normalised so the usual tricks count as the same
 *     inbox: case, "+anything" tags, and dots in Gmail addresses
 *     (John.Smith+2@gmail.com = johnsmith@gmail.com);
 *   - the sign-up IP address, within TRIAL_IP_WINDOW_DAYS (a shorter window,
 *     because mobile networks share IPs between strangers).
 * Otherwise the account is created as normal on the free plan — no trial, no
 * error shown (the free plan is fully usable; they can upgrade to pay).
 *
 * Only a hash of the normalised email is stored. Not bullet-proof — a new
 * inbox on a new network still gets a trial — but it stops casual repeats.
 * Defensive: if the table isn't there yet, the trial is granted as before.
 */

if (!defined('TRIAL_IP_WINDOW_DAYS')) {
    define('TRIAL_IP_WINDOW_DAYS', 30);
}

function trial_email_key(string $email): string
{
    $email = strtolower(trim($email));
    $at    = strrpos($email, '@');
    if ($at === false) return hash('sha256', $email);
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    $plus = strpos($local, '+');
    if ($plus !== false) $local = substr($local, 0, $plus);
    if ($domain === 'googlemail.com') $domain = 'gmail.com';
    if ($domain === 'gmail.com') $local = str_replace('.', '', $local);
    return hash('sha256', $local . '@' . $domain);
}

/** True if this person (email or recent IP) has already had a trial. */
function trial_already_used(string $email, string $ip): bool
{
    try {
        $st = db()->prepare(
            'SELECT 1 FROM trial_grants
              WHERE email_key = ?
                 OR (ip = ? AND ip <> \'\' AND created_at > (NOW() - INTERVAL ' . (int) TRIAL_IP_WINDOW_DAYS . ' DAY))
              LIMIT 1'
        );
        $st->execute([trial_email_key($email), $ip]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;   // table not migrated yet — behave as before
    }
}

function trial_record(int $clientId, string $email, string $ip): void
{
    try {
        db()->prepare('INSERT INTO trial_grants (email_key, ip, client_id) VALUES (?, ?, ?)')
            ->execute([trial_email_key($email), substr($ip, 0, 45), $clientId]);
    } catch (Throwable $e) {
        error_log('[YourBlinds] trial_record failed: ' . $e->getMessage());
    }
}
