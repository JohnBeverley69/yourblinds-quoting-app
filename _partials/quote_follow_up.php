<?php
declare(strict_types=1);

/**
 * Quote follow-up: a friendly nudge to a retail customer whose quote was sent
 * N days ago and is still waiting for an answer (status 'sent').
 *
 * Each company chooses (Settings → Texts → Quote follow-up):
 *   - on / off (client_settings.qfu_on)
 *   - after how many days (qfu_days, 1–25; default 7; quotes expire at 30)
 *   - how it goes: text, email or both (qfu_method)
 *   - the wording (stored with the text wording in sms_templates JSON)
 *
 * Run by cron_messages.php, 10am–8pm. One follow-up per quote per time it was
 * sent (messages.ref_key = quotes.sent_at), so re-sending a quote (which
 * restarts sent_at) earns one more. Only quotes sent between N and N+3 days ago
 * qualify, so switching it on never nudges a backlog of old quotes. Trade
 * quotes are left alone — these are for the company's own retail customers.
 *
 * Paid plans only, like texting. Texts go through sms_send() (limit,
 * "Don't text" tick); emails through mailer_send() and the daily email cap,
 * and both are logged in `messages`, so they show on the Messages page.
 */

require_once __DIR__ . '/sms.php';

const QFU_KIND = 'quote_follow_up';

function qfu_defaults(): array
{
    return [
        'text'    => 'Hi {{first_name}}, just checking you got your quote {{quote_number}} from {{company}}. Any questions or changes, please call {{company_phone}}.',
        'subject' => 'Your quote {{quote_number}} from {{company}}',
        'email'   => "Hello {{first_name}},\n\n"
                   . "Just checking you received your quote ({{quote_number}}). You can view and accept it online here:\n"
                   . "{{quote_link}}\n\n"
                   . "If you have any questions, or would like anything changed, just reply to this email or call us on {{company_phone}}.\n\n"
                   . "Kind regards,\n{{company}}",
    ];
}

/** Where each piece of wording lives in the sms_templates JSON. */
function qfu_template_keys(): array
{
    return ['text' => 'quote_follow_up', 'subject' => 'quote_follow_up_subject', 'email' => 'quote_follow_up_email'];
}

/** This company's follow-up settings, with defaults. */
function qfu_settings(int $clientId): array
{
    $s = ['on' => false, 'days' => 7, 'method' => 'email'] + qfu_defaults();
    if (!sms_ready()) return $s;
    try {
        $st = db()->prepare('SELECT qfu_on, qfu_days, qfu_method FROM client_settings WHERE client_id = ? LIMIT 1');
        $st->execute([$clientId]);
        if ($row = $st->fetch()) {
            $s['on']     = (int) $row['qfu_on'] === 1;
            $s['days']   = max(1, min(25, (int) ($row['qfu_days'] ?: 7)));
            $s['method'] = in_array($row['qfu_method'], ['sms', 'email', 'both'], true) ? $row['qfu_method'] : 'email';
        }
    } catch (Throwable $e) { /* not migrated — defaults */ }
    $own = sms_settings($clientId)['sms_templates'] ?? [];
    foreach (qfu_template_keys() as $part => $key) {
        if (isset($own[$key]) && is_string($own[$key]) && trim($own[$key]) !== '') $s[$part] = $own[$key];
    }
    return $s;
}

/** Placeholders the follow-up wording can use. */
function qfu_placeholders(): array
{
    return [
        '{{first_name}}'    => "Customer's first name",
        '{{quote_number}}'  => 'The quote number',
        '{{quote_link}}'    => 'Link to view and accept the quote (best in emails: it makes a text much longer)',
        '{{company}}'       => 'Your company name',
        '{{company_phone}}' => 'Your company phone number',
    ];
}

function qfu_quote_link(string $token): string
{
    $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');
    return $base . '/quote-history/public.php?token=' . urlencode($token);
}

/** Log one email in `messages` (the email side of the Messages page). */
function qfu_log_email(int $clientId, array $q, string $to, string $from, string $subject, string $body, bool $ok, ?string $why): void
{
    db()->prepare(
        "INSERT INTO messages
            (client_id, channel, direction, kind, ref_key, customer_id, quote_id,
             to_addr, from_addr, body, segments, status, status_detail, provider, sent_at)
         VALUES (?, 'email', 'out', ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'smtp', ?)"
    )->execute([
        $clientId, QFU_KIND, (string) $q['sent_at'], $q['customer_id'] ?: null, (int) $q['id'],
        substr($to, 0, 190), substr($from, 0, 190), $subject,   // subject only — never the message text
        $ok ? 'sent' : ($why !== null ? 'blocked' : 'failed'), $why ?? ($ok ? null : 'The email could not be sent.'),
        $ok ? date('Y-m-d H:i:s') : null,
    ]);
}

/**
 * Send every follow-up that's due. 10am–8pm UK time.
 * @return array{texts:int, emails:int, not_sent:int}
 */
function qfu_run(?DateTimeImmutable $now = null): array
{
    $now   = $now ?? new DateTimeImmutable('now');
    $stats = ['texts' => 0, 'emails' => 0, 'not_sent' => 0];
    $hour  = (int) $now->format('G');
    if (!sms_ready() || $hour < 10 || $hour >= 20) return $stats;

    require_once __DIR__ . '/../mailer.php';
    require_once __DIR__ . '/tenant_mail.php';
    require_once __DIR__ . '/send_quota.php';

    $nowSql = $now->format('Y-m-d H:i:s');
    $st = db()->prepare(
        "SELECT q.id, q.client_id, q.quote_number, q.sent_at, q.public_token, q.customer_id,
                q.end_customer_name, q.end_customer_email, q.end_customer_mobile, q.end_customer_phone,
                cl.company_name, cl.phone AS company_phone
           FROM quotes q
           JOIN client_settings cs ON cs.client_id = q.client_id AND cs.qfu_on = 1
           JOIN clients cl         ON cl.id = q.client_id
          WHERE q.status = 'sent' AND q.sent_at IS NOT NULL
            AND q.sent_at <= DATE_SUB(?, INTERVAL cs.qfu_days DAY)
            AND q.sent_at >  DATE_SUB(?, INTERVAL cs.qfu_days + 3 DAY)
            AND COALESCE(q.sale_type, 'retail') = 'retail' AND q.account_client_id IS NULL
            AND NOT EXISTS (SELECT 1 FROM messages m
                             WHERE m.quote_id = q.id AND m.kind = ? AND m.ref_key = q.sent_at)
       ORDER BY q.sent_at
          LIMIT 200"
    );
    $st->execute([$nowSql, $nowSql, QFU_KIND]);

    foreach ($st->fetchAll() as $q) {
        $cid = (int) $q['client_id'];
        try {
            if (!sms_feature_enabled($cid)) continue;   // paid plans only
            $cfg  = qfu_settings($cid);
            $vars = [
                'first_name'    => ucfirst(sms_first_name((string) $q['end_customer_name'])) ?: 'there',
                'quote_number'  => (string) $q['quote_number'],
                'quote_link'    => qfu_quote_link((string) $q['public_token']),
                'company'       => (string) $q['company_name'],
                'company_phone' => (string) $q['company_phone'],
            ];
            $ctx = ['kind' => QFU_KIND, 'customer_id' => $q['customer_id'] ?: null,
                    'quote_id' => (int) $q['id'], 'ref_key' => (string) $q['sent_at']];
            $didSomething = false;

            if (in_array($cfg['method'], ['sms', 'both'], true)) {
                $mob = sms_normalise_uk_mobile((string) $q['end_customer_mobile'])
                    ?? sms_normalise_uk_mobile((string) $q['end_customer_phone']);
                if ($mob !== null) {
                    $r = sms_send($cid, $mob, sms_render($cfg['text'], $vars), $ctx);
                    $stats[$r['ok'] ? 'texts' : 'not_sent']++;
                    $didSomething = true;
                }
            }

            if (in_array($cfg['method'], ['email', 'both'], true)) {
                $to = trim((string) $q['end_customer_email']);
                if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $subject = sms_render($cfg['subject'], $vars);
                    $body    = str_replace("\r\n", "\n", strtr($cfg['email'], array_combine(
                        array_map(static fn ($k) => '{{' . $k . '}}', array_keys($vars)), array_values($vars)
                    )));
                    $why = send_quota_allows($cid) ? null : 'Daily email limit reached.';
                    $ok  = false;
                    if ($why === null) {
                        $ok = mailer_send($to, $subject, $body, null, null,
                            tenant_mail_opts(db(), $cid) + ['links' => [$vars['quote_link'] => 'View and accept your quote']]);
                        if ($ok) send_quota_record($cid, 0, 'follow_up');
                    }
                    qfu_log_email($cid, $q, $to, (string) $q['company_name'], $subject, $body, $ok, $why);
                    $stats[$ok ? 'emails' : 'not_sent']++;
                    $didSomething = true;
                }
            }

            if (!$didSomething) {
                // No usable mobile / email for the chosen method: record it once so
                // it isn't re-checked every 15 minutes and shows on Messages.
                db()->prepare(
                    "INSERT INTO messages (client_id, channel, direction, kind, ref_key, customer_id, quote_id,
                                           to_addr, body, segments, status, status_detail)
                     VALUES (?, ?, 'out', ?, ?, ?, ?, '', '', 0, 'blocked', ?)"
                )->execute([$cid, $cfg['method'] === 'sms' ? 'sms' : 'email', QFU_KIND, (string) $q['sent_at'],
                            $q['customer_id'] ?: null, (int) $q['id'],
                            $cfg['method'] === 'sms' ? 'No mobile number on the quote.' : 'No email address on the quote.']);
                $stats['not_sent']++;
            }
        } catch (Throwable $e) {
            error_log('qfu_run(quote ' . $q['id'] . '): ' . $e->getMessage());
            $stats['not_sent']++;
        }
    }
    return $stats;
}
