<?php
declare(strict_types=1);

/**
 * Who a customer-facing email is "from": the business, not the platform.
 *
 * Every email still goes OUT through yourblinds.uk (that's what SPF / DKIM
 * cover, so it isn't rejected as forged), but the name the customer sees and
 * the address their reply goes to should be the business they're dealing
 * with. Without this a quote arrived as "YourBlinds <noreply@yourblinds.uk>"
 * — which mail filters (iCloud especially) junk as a likely phish, and which
 * swallowed any reply to the "reply to this email" lines.
 *
 * Uses Settings → Quote defaults: Email "from" name and Reply-to email,
 * falling back to the business name and its contact email.
 *
 * Returns mailer_send() $opts: from_name, reply_to_email, reply_to_name.
 */
function tenant_mail_opts(PDO $pdo, int $clientId): array
{
    static $cache = [];
    if (isset($cache[$clientId])) return $cache[$clientId];

    $name = ''; $reply = '';
    try {
        $st = $pdo->prepare(
            'SELECT c.company_name, c.email, s.email_from_name, s.reply_to_email
               FROM clients c
               LEFT JOIN client_settings s ON s.client_id = c.id
              WHERE c.id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $name  = trim((string) ($r['email_from_name'] ?? '')) ?: trim((string) ($r['company_name'] ?? ''));
        $reply = trim((string) ($r['reply_to_email'] ?? '')) ?: trim((string) ($r['email'] ?? ''));
    } catch (Throwable $e) {
        // Optional columns missing on an old schema: fall back to the platform defaults.
    }

    $opts = [];
    if ($name !== '') $opts['from_name'] = $name;
    if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
        $opts['reply_to_email'] = $reply;
        $opts['reply_to_name']  = $name !== '' ? $name : $reply;
    }
    return $cache[$clientId] = $opts;
}

/**
 * Is this a free personal mailbox (Gmail, Hotmail, iCloud…)? Spam filters
 * score "From a business domain, Reply-To a free mailbox" as a likely scam
 * (SpamAssassin FREEMAIL_FORGED_REPLYTO, 2.5 points — mail-tester, Oct 2026),
 * so Settings warns a company whose reply address is one. An address on the
 * company's own domain doesn't trigger it.
 */
function tenant_mail_is_freemail(string $email): bool
{
    $domain = strtolower(trim((string) substr(strrchr($email, '@') ?: '', 1)));
    if ($domain === '') return false;
    $free = [
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.co.uk', 'outlook.com', 'outlook.co.uk',
        'live.com', 'live.co.uk', 'msn.com', 'icloud.com', 'me.com', 'mac.com', 'yahoo.com',
        'yahoo.co.uk', 'ymail.com', 'aol.com', 'aol.co.uk', 'btinternet.com', 'btopenworld.com',
        'sky.com', 'talktalk.net', 'virginmedia.com', 'blueyonder.co.uk', 'ntlworld.com',
        'tiscali.co.uk', 'gmx.com', 'gmx.co.uk', 'mail.com', 'protonmail.com', 'proton.me',
        'zoho.com', 'yandex.com',
    ];
    return in_array($domain, $free, true);
}

/** The reply address customers' emails will actually use (Reply-to, else company email). */
function tenant_mail_reply_address(PDO $pdo, int $clientId): string
{
    return (string) (tenant_mail_opts($pdo, $clientId)['reply_to_email'] ?? '');
}
