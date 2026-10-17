<?php
declare(strict_types=1);

/**
 * Customer texting (SMS) engine — the one place that sends a text.
 *
 * Model (decided with John, 2026-10-11):
 *  - ONE provider account, paid for by YourBlinds. Each company's texts go out
 *    under its own NAME (alphanumeric sender, up to 11 chars) — customers can't
 *    reply, so the wording ends with the company's phone number.
 *  - Paid plans only (Silver/Gold grant feature_sms; never Bronze). The main
 *    factory (Beverley, ours) always has it; other factories need the plan.
 *  - Every text is logged in `messages` and counted against a per-company
 *    MONTHLY ALLOWANCE. NULL on the company means the platform default
 *    (SMS_DEFAULT_MONTHLY_LIMIT, 1000); master-admin can set it per company on
 *    /master-admin/texts.php.
 *  - Texts are never free (John, 2026-10-13): past the allowance a company
 *    texts from prepaid BUNDLES it buys through PayPal (sms_bundles; unused
 *    bundle texts carry over). Packs and prices: Master Admin → Texts.
 *  - A customer with "Don't text" ticked (customers.sms_opt_out) is never texted.
 *
 * Replies are a later paid add-on: a rented UK number per company, inbound
 * webhook routed by TO number → company, FROM number → customer, stored as
 * messages.direction = 'in'. Nothing in the send path needs changing for it —
 * sms_send() would just use the company's number as the sender.
 *
 * Provider: The SMS Works (api.thesmsworks.co.uk). The token lives in
 * platform_config (SMSWORKS_TOKEN), pasted in by a super-admin. Delivery
 * reports come back per message to /api/sms_webhook.php?k=<SMS_WEBHOOK_KEY>.
 *
 * Defensive throughout: if the migration (migrate_messages.php) hasn't run,
 * texting is simply off — nothing else in the app breaks.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/accounting.php';   // pc_get() / pc_set()

if (!defined('SMS_DEFAULT_MONTHLY_LIMIT')) define('SMS_DEFAULT_MONTHLY_LIMIT', 1000);
if (!defined('SMS_WARN_PERCENT'))          define('SMS_WARN_PERCENT', 80);

/**
 * The automatic texts a company can tick, with their client_settings column
 * and default wording. Placeholders are filled by sms_render().
 */
function sms_kinds(): array
{
    return [
        'booking_confirm' => [
            'label'   => 'Booking confirmation',
            'hint'    => 'Sent when an appointment is booked, and again if it is moved.',
            'column'  => 'sms_booking_confirm',
            'default' => 'Hi {{first_name}}, your appointment with {{company}} is booked for {{date}}, {{time}}. To change it please call {{company_phone}}.',
        ],
        'day_before' => [
            'label'   => 'Reminder the day before',
            'hint'    => 'Sent at 10am the day before the appointment.',
            'column'  => 'sms_day_before',
            'default' => 'Reminder: {{company}} will see you tomorrow, {{date}}, {{time}}. To rearrange please call {{company_phone}}.',
        ],
        'on_the_way' => [
            'label'   => 'Your fitter is on the way',
            'hint'    => 'Sent when the fitter taps "On my way" on My Schedule or the appointment page (on the day).',
            'column'  => 'sms_on_the_way',
            'default' => 'Hi {{first_name}}, {{staff_name}} from {{company}} is on the way to you now. Any questions please call {{company_phone}}.',
        ],
    ];
}

/** Placeholders the wording can use, with a description for the Settings page. */
function sms_placeholders(): array
{
    return [
        '{{first_name}}'    => "Customer's first name",
        '{{company}}'       => 'Your company name',
        '{{company_phone}}' => 'Your company phone number',
        '{{date}}'          => 'Appointment date, e.g. Tue 14 Oct',
        '{{time}}'          => 'Appointment time or slot, e.g. 10:30 or AM',
        '{{staff_name}}'    => 'The fitter or salesperson',
    ];
}

/** Fill placeholders. Unknown ones are left as typed so a typo is visible. */
function sms_render(string $template, array $vars): string
{
    $map = [];
    foreach ($vars as $k => $v) $map['{{' . $k . '}}'] = trim((string) $v);
    $out = strtr($template, $map);
    // Tidy doubled spaces left by an empty value.
    return trim((string) preg_replace('/ {2,}/', ' ', $out));
}

/** Example values, for the Settings preview and the test text. */
function sms_sample_vars(array $client): array
{
    return [
        'first_name'    => 'Sarah',
        'company'       => (string) ($client['company_name'] ?? 'Your company'),
        'company_phone' => (string) ($client['phone'] ?? ''),
        'date'          => date('D j M', strtotime('+2 days')),
        'time'          => '10:30',
        'staff_name'    => 'Dave',
    ];
}

/* ------------------------------------------------------------------ *
 *  Text maths
 * ------------------------------------------------------------------ */

/**
 * How many billable texts (segments) a message takes. Plain GSM-7 text is 160
 * characters for one text, 153 per part after that; anything outside GSM-7
 * (emoji, curly quotes) drops it to 70 / 67.
 */
function sms_segments(string $text): int
{
    $basic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
           . "¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    $ext   = "^{}\\[~]|€\f";
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $len   = 0;
    $gsm   = true;
    foreach ($chars as $c) {
        if (mb_strpos($basic, $c) !== false)      { $len += 1; }
        elseif (mb_strpos($ext, $c) !== false)    { $len += 2; }
        else { $gsm = false; break; }
    }
    if (!$gsm) {
        $len = count($chars);
        return $len <= 70 ? 1 : (int) ceil($len / 67);
    }
    return $len <= 160 ? 1 : (int) ceil($len / 153);
}

/**
 * Swap typographic characters (curly quotes, dashes, ellipsis, non-breaking
 * space) for plain ones. One curly apostrophe pasted in from Word would
 * otherwise make a 160-character text count as three (70 characters each).
 */
function sms_plain(string $text): string
{
    return strtr($text, [
        "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
        "\u{2013}" => '-', "\u{2014}" => '-', "\u{2026}" => '...', "\u{00A0}" => ' ',
    ]);
}

/**
 * A UK mobile in international form without the + (447xxxxxxxxx), or null if
 * it isn't one. Accepts 07…, +447…, 00447…, 447… with spaces/dashes/brackets.
 */
function sms_normalise_uk_mobile(?string $raw): ?string
{
    $d = preg_replace('/[^0-9+]/', '', (string) $raw) ?? '';
    if (str_starts_with($d, '+'))   $d = substr($d, 1);
    if (str_starts_with($d, '00'))  $d = substr($d, 2);
    if (str_starts_with($d, '07'))  $d = '44' . substr($d, 1);
    if (str_starts_with($d, '440')) $d = '44' . substr($d, 3);   // +44 (0)7…
    return preg_match('/^447\d{9}$/', $d) ? $d : null;
}

/** Display form of a stored number: 447700900123 → 07700 900123. */
function sms_display_number(string $intl): string
{
    if (preg_match('/^447(\d{3})(\d{6})$/', $intl, $m)) return '07' . $m[1] . ' ' . $m[2];
    return $intl;
}

/**
 * Clean a sender name: letters, numbers and spaces, 3–11 characters, with at
 * least one letter (an all-digit sender would look like a phone number).
 * Returns null if it can't be used.
 */
function sms_clean_sender(?string $name): ?string
{
    $s = trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', (string) $name));
    $s = (string) preg_replace('/ {2,}/', ' ', $s);
    if (strlen($s) < 3 || strlen($s) > 11 || !preg_match('/[A-Za-z]/', $s)) return null;
    return $s;
}

/** A suggested sender from the company name: "Beverley Blinds Ltd" → "BeverleyBli". */
function sms_suggest_sender(string $companyName): string
{
    $s = preg_replace('/\b(ltd|limited|plc|llp)\b\.?/i', '', $companyName) ?? '';
    $s = preg_replace('/[^A-Za-z0-9]/', '', $s) ?? '';
    return substr($s, 0, 11);
}

/* ------------------------------------------------------------------ *
 *  Per-company settings, usage and the limit
 * ------------------------------------------------------------------ */

/** True once migrate_messages.php has run. */
function sms_ready(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        $has = (bool) db()->query("SHOW TABLES LIKE 'messages'")->fetchColumn();
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/** This company's texting settings (defaults when unset / not migrated). */
function sms_settings(int $clientId, bool $fresh = false): array
{
    static $cache = [];
    if (!$fresh && isset($cache[$clientId])) return $cache[$clientId];
    $s = [
        'feature_sms' => 0, 'sms_sender_name' => null, 'sms_monthly_limit' => null,
        'sms_booking_confirm' => 0, 'sms_day_before' => 0, 'sms_on_the_way' => 0,
        'sms_templates' => [],
    ];
    if (!sms_ready()) return $s;
    try {
        $st = db()->prepare(
            'SELECT feature_sms, sms_sender_name, sms_monthly_limit,
                    sms_booking_confirm, sms_day_before, sms_on_the_way, sms_templates
               FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $st->execute([$clientId]);
        $row = $st->fetch();
        if ($row) {
            $s = array_merge($s, $row);
            $t = json_decode((string) ($row['sms_templates'] ?? ''), true);
            $s['sms_templates'] = is_array($t) ? $t : [];
        }
    } catch (Throwable $e) { /* not migrated — defaults */ }
    return $cache[$clientId] = $s;
}

/** Does this company get texting? (paid plan, or the main factory — ours) */
function sms_feature_enabled(int $clientId): bool
{
    if (!sms_ready()) return false;
    // Only the main factory gets it without a plan: texts cost money, so a
    // white-label factory pays like any other company (John, 2026-10-13).
    if (function_exists('factory_client_id') && $clientId === (int) factory_client_id()) return true;
    return (int) sms_settings($clientId)['feature_sms'] === 1;
}

/** The wording a company uses for one kind (its own, or the default). */
function sms_template(int $clientId, string $kind): string
{
    $own = sms_settings($clientId)['sms_templates'][$kind] ?? '';
    if (is_string($own) && trim($own) !== '') return $own;
    return sms_kinds()[$kind]['default'] ?? '';
}

/** Platform default monthly limit (master-admin can change it). */
function sms_default_limit(): int
{
    $v = (int) (pc_get('SMS_DEFAULT_MONTHLY_LIMIT', (string) SMS_DEFAULT_MONTHLY_LIMIT) ?? 0);
    return $v > 0 ? $v : SMS_DEFAULT_MONTHLY_LIMIT;
}

/** This company's monthly limit, in texts (segments). */
function sms_monthly_limit(int $clientId): int
{
    $own = sms_settings($clientId)['sms_monthly_limit'];
    return ($own !== null && (int) $own >= 0) ? (int) $own : sms_default_limit();
}

/**
 * Delete Messages-log entries (texts and emails) older than 12 months — the
 * retention the privacy policy states. Run by cron_messages.php; a batch at a
 * time so it never holds the table long. Also strips the message text from any
 * email logged before the log stopped storing it (subject line kept).
 * Returns the number of rows deleted.
 */
function sms_purge_old(int $months = 12): int
{
    if (!sms_ready()) return 0;
    try {
        $cut = (new DateTimeImmutable('now'))->modify("-{$months} months")->format('Y-m-d H:i:s');
        $st  = db()->prepare('DELETE FROM messages WHERE created_at < ? LIMIT 2000');
        $st->execute([$cut]);
        $gone = $st->rowCount();
        // Old-format email rows: "Subject\n\nBody…" → keep the subject only.
        db()->exec("UPDATE messages SET body = SUBSTRING_INDEX(body, '\n\n', 1)
                     WHERE channel = 'email' AND body LIKE '%\n\n%' LIMIT 2000");
        return $gone;
    } catch (Throwable $e) {
        error_log('sms_purge_old: ' . $e->getMessage());
        return 0;
    }
}

/** Texts (segments) this company has used this calendar month OUT OF ITS
 *  ALLOWANCE — texts paid for from a bundle don't count against it. */
function sms_used_this_month(int $clientId): int
{
    if (!sms_ready()) return 0;
    try {
        $st = db()->prepare(
            "SELECT COALESCE(SUM(segments), 0) FROM messages
              WHERE client_id = ? AND channel = 'sms' AND direction = 'out'
                AND status IN ('queued', 'sent', 'delivered')
                AND created_at >= ?" . (sms_bundles_ready() ? ' AND from_bundle = 0' : '')
        );
        $st->execute([$clientId, date('Y-m-01 00:00:00')]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ------------------------------------------------------------------ *
 *  Prepaid text bundles (migrate_sms_bundles.php)
 * ------------------------------------------------------------------ */

function sms_bundles_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->query('SELECT 1 FROM sms_bundles LIMIT 0');
        db()->query('SELECT from_bundle FROM messages LIMIT 0');
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

/** Bundle texts this company has left: paid bundles minus texts sent from them. */
function sms_bundle_balance(int $clientId): int
{
    if (!sms_bundles_ready()) return 0;
    try {
        $st = db()->prepare("SELECT COALESCE(SUM(texts), 0) FROM sms_bundles WHERE client_id = ? AND status = 'paid'");
        $st->execute([$clientId]);
        $bought = (int) $st->fetchColumn();
        $st = db()->prepare(
            "SELECT COALESCE(SUM(segments), 0) FROM messages
              WHERE client_id = ? AND channel = 'sms' AND direction = 'out' AND from_bundle = 1
                AND status IN ('queued', 'sent', 'delivered')"
        );
        $st->execute([$clientId]);
        return max(0, $bought - (int) $st->fetchColumn());
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * The packs on sale, set on Master Admin → Texts (platform_config SMS_BUNDLES,
 * JSON [{"texts":250,"price":"10.00"}, …]). Empty until the super-admin sets
 * them — no prices are made up here.
 *
 * @return list<array{texts:int, price:string}>
 */
function sms_bundle_packs(): array
{
    $raw = json_decode((string) (pc_get('SMS_BUNDLES', '') ?? ''), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $p) {
        $t = (int) ($p['texts'] ?? 0);
        $v = round((float) ($p['price'] ?? 0), 2);
        if ($t > 0 && $t <= 100000 && $v > 0) $out[] = ['texts' => $t, 'price' => number_format($v, 2, '.', '')];
    }
    usort($out, static fn ($a, $b) => $a['texts'] <=> $b['texts']);
    return $out;
}

/**
 * Can this company send $segments more texts, and paid from where? The
 * month's allowance first, then the bundle balance.
 *
 * @return array{ok:bool, from_bundle:bool, block:?string}
 */
function sms_allowance_check(int $clientId, int $segments): array
{
    $limit = sms_monthly_limit($clientId);
    if (sms_used_this_month($clientId) + $segments <= $limit) {
        return ['ok' => true, 'from_bundle' => false, 'block' => null];
    }
    if (sms_bundle_balance($clientId) >= $segments) {
        return ['ok' => true, 'from_bundle' => true, 'block' => null];
    }
    return ['ok' => false, 'from_bundle' => false,
            'block' => "This month's {$limit} texts are used up and there are no bundle texts left — buy more on Messages → Buy more texts."];
}

/* ------------------------------------------------------------------ *
 *  Provider: The SMS Works
 * ------------------------------------------------------------------ */

function sms_provider_configured(): bool
{
    return (pc_get('SMSWORKS_TOKEN', '') ?? '') !== '';
}

/** Secret in the delivery-report URL (generated on first use). */
function sms_webhook_key(): string
{
    $k = pc_get('SMS_WEBHOOK_KEY', '') ?? '';
    if ($k === '') {
        $k = bin2hex(random_bytes(16));
        try { pc_set('SMS_WEBHOOK_KEY', $k); } catch (Throwable $e) { /* no table — report URL just won't verify */ }
    }
    return $k;
}

function sms_webhook_url(): string
{
    $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');
    return $base . '/api/sms_webhook.php?k=' . sms_webhook_key();
}

/** The pasted token with any "JWT " / "Bearer " prefix, spaces and line breaks removed. */
function sms_token_bare(string $token): string
{
    $t = trim((string) preg_replace('/\s+/', ' ', $token));
    if (preg_match('/^(jwt|bearer)\s+(.+)$/i', $t, $m)) $t = $m[2];
    return str_replace(' ', '', $t);
}

/**
 * The Authorization header values to try, best first. The SMS Works shows its
 * generated token in the dashboard and its docs don't say whether the "JWT "
 * part belongs in the header, so both forms are tried (a rejected key sends
 * nothing, so a retry is safe) and the one that works is remembered.
 */
function sms_auth_candidates(string $token): array
{
    $bare  = sms_token_bare($token);
    if ($bare === '') return [];
    $forms = ['bare' => $bare, 'jwt' => 'JWT ' . $bare];
    $pref  = pc_get('SMSWORKS_AUTH_STYLE', '') ?? '';
    if (!isset($forms[$pref])) $pref = preg_match('/^jwt\s/i', trim($token)) ? 'jwt' : 'bare';
    return [$pref => $forms[$pref]] + $forms;
}

/**
 * What kind of value was pasted, for the Master Admin page (never shows it).
 * 'token' = a generated JSON Web Token (eyJ….….…), which is right; 'key' =
 * anything else, usually the API key or secret, which are only used to
 * generate the token and are rejected if sent with a text.
 */
function sms_token_kind(string $token): string
{
    $b = sms_token_bare($token);
    if ($b === '') return 'none';
    return (str_starts_with($b, 'eyJ') && substr_count($b, '.') === 2) ? 'token' : 'key';
}

/**
 * Send one text through The SMS Works.
 * @return array{ok:bool, ref:?string, error:?string}
 */
function sms_provider_send(string $sender, string $to, string $body): array
{
    $token = pc_get('SMSWORKS_TOKEN', '') ?? '';
    if ($token === '') return ['ok' => false, 'ref' => null, 'error' => 'Texting is not connected yet.'];

    $payload = json_encode([
        'sender'            => $sender,
        'destination'       => $to,
        'content'           => $body,
        'deliveryreporturl' => sms_webhook_url(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // SMSWORKS_API_URL in .env points at a stand-in server for local testing.
    $url = (env('SMSWORKS_API_URL', '') ?: 'https://api.thesmsworks.co.uk/v1') . '/message/send';
    $raw = false; $status = 0; $err = '';
    foreach (sms_auth_candidates($token) as $style => $auth) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $auth,
            ],
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $status !== 401) {
            // Remember the header form that was accepted, so later sends go first time.
            if ($status >= 200 && $status < 300 && (pc_get('SMSWORKS_AUTH_STYLE', '') ?? '') !== $style) {
                try { pc_set('SMSWORKS_AUTH_STYLE', $style); } catch (Throwable $e) { /* just retries next time */ }
            }
            break;
        }
    }

    if ($raw === false) return ['ok' => false, 'ref' => null, 'error' => 'Could not reach the text provider: ' . $err];
    $json = json_decode((string) $raw, true);
    if ($status >= 200 && $status < 300) {
        $ref = is_array($json) ? (string) ($json['messageid'] ?? $json['messageId'] ?? '') : '';
        return ['ok' => true, 'ref' => $ref !== '' ? $ref : null, 'error' => null];
    }
    $why = [401 => sms_token_kind($token) === 'key'
                ? 'the saved code is the API key or secret, not the JWT (Master Admin → Texts)'
                : 'the text provider rejected our key',
            402 => 'the text account is out of credit',
            403 => 'the text provider refused the send'][$status] ?? ('the text provider said ' . $status);
    $msg = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? '') : '';
    return ['ok' => false, 'ref' => null, 'error' => 'Not sent: ' . $why . ($msg !== '' ? ' (' . $msg . ')' : '') . '.'];
}

/* ------------------------------------------------------------------ *
 *  The one send function
 * ------------------------------------------------------------------ */

/**
 * Send (and log) one text for a company. Every check that stops a send is
 * logged too (status 'blocked'), so the Messages log can say why.
 *
 * $ctx: kind (booking_confirm | day_before | on_the_way | quote_follow_up | test),
 *       customer_id, appointment_id, quote_id, user_id, ref_key — all optional.
 *
 * @return array{ok:bool, status:string, message:string, id:?int}
 */
function sms_send(int $clientId, string $toRaw, string $body, array $ctx = []): array
{
    $kind   = (string) ($ctx['kind'] ?? '');
    $custId = isset($ctx['customer_id']) ? (int) $ctx['customer_id'] : null;
    $apptId = isset($ctx['appointment_id']) ? (int) $ctx['appointment_id'] : null;
    $userId = isset($ctx['user_id']) ? (int) $ctx['user_id'] : null;
    $refKey = isset($ctx['ref_key']) ? substr((string) $ctx['ref_key'], 0, 40) : null;
    $quoteId = isset($ctx['quote_id']) ? (int) $ctx['quote_id'] : null;

    if (!sms_ready()) {
        return ['ok' => false, 'status' => 'blocked', 'message' => 'Texting is not set up yet.', 'id' => null];
    }

    $settings = sms_settings($clientId);
    $sender   = sms_clean_sender($settings['sms_sender_name'] ?? '') ?? '';
    $to       = sms_normalise_uk_mobile($toRaw);
    $body     = sms_plain(trim($body));
    $segments = max(1, sms_segments($body));

    $block = null;
    if (!sms_feature_enabled($clientId)) {
        require_once __DIR__ . '/billing_helpers.php';
        $block = 'Texts are part of ' . billing_feature_plans_phrase('feature_sms') . '.';
    } elseif ($sender === '') {
        $block = 'Set a sender name in Settings → Texts first.';
    } elseif ($to === null) {
        $block = 'Not a UK mobile number.';
    } elseif ($body === '') {
        $block = 'The text is empty.';
    } elseif ($custId) {
        try {
            $st = db()->prepare('SELECT sms_opt_out FROM customers WHERE id = ? AND client_id = ? LIMIT 1');
            $st->execute([$custId, $clientId]);
            if ((int) $st->fetchColumn() === 1) $block = 'This customer is marked "Don\'t text".';
        } catch (Throwable $e) { /* column missing — allow */ }
    }
    $fromBundle = false;
    if ($block === null) {
        $allow      = sms_allowance_check($clientId, $segments);
        $block      = $allow['block'];
        $fromBundle = $allow['from_bundle'];
    }
    if ($block === null && !sms_provider_configured()) {
        $block = 'Texting is not connected yet.';
    }

    // quote_id only when there is one, so other texts don't depend on that column.
    $quoteCol = $quoteId !== null ? ', quote_id' : '';
    // Paid from a bundle (past the month's allowance).
    $bundleCol = ($block === null && $fromBundle) ? ', from_bundle' : '';
    $ins = db()->prepare(
        "INSERT INTO messages
            (client_id, channel, direction, kind, customer_id, appointment_id, user_id,
             to_addr, from_addr, body, segments, status, status_detail, provider, ref_key{$quoteCol}{$bundleCol})
         VALUES (?, 'sms', 'out', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'smsworks', ?" . ($quoteId !== null ? ', ?' : '')
              . ($bundleCol !== '' ? ', 1' : '') . ")"
    );
    $ins->execute(array_merge([
        $clientId, $kind, $custId, $apptId, $userId,
        $to ?? substr(trim($toRaw), 0, 190), $sender, $body, $segments,
        $block === null ? 'queued' : 'blocked', $block, $refKey,
    ], $quoteId !== null ? [$quoteId] : []));
    $id = (int) db()->lastInsertId();
    if ($block !== null) {
        return ['ok' => false, 'status' => 'blocked', 'message' => $block, 'id' => $id];
    }

    $res = sms_provider_send($sender, (string) $to, $body);
    if ($res['ok']) {
        db()->prepare("UPDATE messages SET status = 'sent', provider_ref = ?, sent_at = NOW() WHERE id = ?")
            ->execute([$res['ref'], $id]);
        return ['ok' => true, 'status' => 'sent', 'message' => 'Text sent to ' . sms_display_number((string) $to) . '.', 'id' => $id];
    }
    db()->prepare("UPDATE messages SET status = 'failed', status_detail = ? WHERE id = ?")
        ->execute([substr((string) $res['error'], 0, 255), $id]);
    return ['ok' => false, 'status' => 'failed', 'message' => (string) $res['error'], 'id' => $id];
}

/* ------------------------------------------------------------------ *
 *  The automatic texts (Step 2): booking confirmation, day-before
 *  reminder, "on the way". Each is only sent when the company has
 *  texting AND has ticked that text in Settings → Texts.
 * ------------------------------------------------------------------ */

/** Has this company ticked this kind of text? */
function sms_kind_on(int $clientId, string $kind): bool
{
    $col = sms_kinds()[$kind]['column'] ?? null;
    return $col !== null && sms_feature_enabled($clientId) && (int) (sms_settings($clientId)[$col] ?? 0) === 1;
}

/** "Mrs J Smith" → "Mrs Smith"; "John Smith" → "John". */
function sms_first_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (!$parts || $parts[0] === '') return '';
    if (preg_match('/^(mr|mrs|ms|miss|dr|mx)\.?$/i', $parts[0])) {
        return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $parts[0];
    }
    return $parts[0];
}

/** Is appointments.online_request there yet? (migrate_online_booking.php) */
function sms_has_online_request_col(): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $has = (bool) db()->query("SHOW COLUMNS FROM appointments LIKE 'online_request'")->fetchColumn();
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

/** Everything a text about one appointment needs, or null if it doesn't exist. */
function sms_appointment(int $clientId, int $apptId): ?array
{
    $pdo = db();
    $slotCol = '';
    try {
        if ($pdo->query("SHOW COLUMNS FROM appointments LIKE 'slot_window'")->fetchColumn()) $slotCol = 'a.slot_window, ';
    } catch (Throwable $e) { /* no AM/PM column */ }
    if (sms_has_online_request_col()) $slotCol .= 'a.online_request, ';
    $st = $pdo->prepare(
        "SELECT {$slotCol}a.id, a.client_id, a.customer_id, a.client_user_id, a.status,
                a.appointment_date, a.appointment_time,
                c.name AS customer_name, c.mobile AS customer_mobile, c.phone AS customer_phone,
                u.full_name AS staff_name,
                cl.company_name, cl.phone AS company_phone
           FROM appointments a
      LEFT JOIN customers c     ON c.id = a.customer_id AND c.client_id = a.client_id
      LEFT JOIN client_users u  ON u.id = a.client_user_id
      LEFT JOIN clients cl      ON cl.id = a.client_id
          WHERE a.id = ? AND a.client_id = ? LIMIT 1"
    );
    $st->execute([$apptId, $clientId]);
    $a = $st->fetch();
    return $a ?: null;
}

/** The mobile to text for an appointment: the mobile field, else a phone that is a mobile. */
function sms_appointment_mobile(array $a): ?string
{
    return sms_normalise_uk_mobile((string) ($a['customer_mobile'] ?? ''))
        ?? sms_normalise_uk_mobile((string) ($a['customer_phone'] ?? ''));
}

/** "Morning (9am-1pm)" for a window booking, else "10:30am". */
function sms_appointment_time_label(array $a): string
{
    $slot = (string) ($a['slot_window'] ?? '');
    if ($slot !== '') {
        require_once __DIR__ . '/slot_window.php';
        try {
            $wins = ampm_build_windows(ampm_settings(db(), (int) $a['client_id']), true);
            if (isset($wins[$slot])) return $wins[$slot]['label'] . ' (' . $wins[$slot]['range'] . ')';
        } catch (Throwable $e) { /* fall back to the clock time */ }
    }
    $t = (string) ($a['appointment_time'] ?? '');
    return $t !== '' ? strtolower(date('g:ia', strtotime('2000-01-01 ' . $t))) : '';
}

/** Placeholder values for one appointment. $staffName overrides the assignee. */
function sms_appointment_vars(array $a, ?string $staffName = null): array
{
    $staff = trim((string) ($staffName ?? $a['staff_name'] ?? ''));
    return [
        'first_name'    => ucfirst(sms_first_name((string) ($a['customer_name'] ?? ''))),
        'company'       => (string) ($a['company_name'] ?? ''),
        'company_phone' => (string) ($a['company_phone'] ?? ''),
        'date'          => !empty($a['appointment_date']) ? date('D j M', strtotime((string) $a['appointment_date'])) : '',
        'time'          => sms_appointment_time_label($a),
        'staff_name'    => $staff !== '' ? (preg_split('/\s+/', $staff)[0] ?? $staff) : 'our fitter',
    ];
}

/**
 * What the booking-confirmation text depends on: date + time + window. A
 * changed key means the appointment moved, so the customer gets a new one.
 */
function sms_appointment_key(int $clientId, int $apptId): ?string
{
    try {
        $a = sms_appointment($clientId, $apptId);
    } catch (Throwable $e) {
        return null;
    }
    if (!$a || empty($a['appointment_date'])) return null;
    return $a['appointment_date'] . '|' . substr((string) $a['appointment_time'], 0, 5) . '|' . (string) ($a['slot_window'] ?? '');
}

/**
 * Call after an appointment is booked or saved. Sends the booking
 * confirmation when the company has it ticked, the appointment is booked
 * for today or later, and its date/time differs from $previousKey (pass
 * sms_appointment_key() from BEFORE the save; null for a new booking).
 * Never throws — a text problem must never stop a booking saving.
 */
function sms_after_appointment_saved(int $clientId, int $apptId, ?string $previousKey = null, ?int $userId = null): void
{
    try {
        if (!sms_kind_on($clientId, 'booking_confirm')) return;
        $key = sms_appointment_key($clientId, $apptId);
        if ($key === null || $key === $previousKey) return;
        $a = sms_appointment($clientId, $apptId);
        if (!$a || (string) $a['status'] !== 'booked') return;
        // An online booking waiting for the office to confirm — the text goes when it's confirmed.
        if ((int) ($a['online_request'] ?? 0) === 1) return;
        if ((string) $a['appointment_date'] < date('Y-m-d')) return;

        // Already confirmed for exactly this date/time? (e.g. saved twice)
        $st = db()->prepare(
            "SELECT ref_key FROM messages
              WHERE appointment_id = ? AND client_id = ? AND kind = 'booking_confirm'
           ORDER BY id DESC LIMIT 1"
        );
        $st->execute([$apptId, $clientId]);
        if ($st->fetchColumn() === $key) return;

        $to = sms_appointment_mobile($a);
        if ($to === null) return;   // no mobile on file — nothing to log
        $body = sms_render(sms_template($clientId, 'booking_confirm'), sms_appointment_vars($a));
        sms_send($clientId, $to, $body, [
            'kind' => 'booking_confirm', 'customer_id' => $a['customer_id'],
            'appointment_id' => $apptId, 'user_id' => $userId, 'ref_key' => $key,
        ]);
    } catch (Throwable $e) {
        error_log('sms_after_appointment_saved(' . $apptId . '): ' . $e->getMessage());
    }
}

/**
 * "On my way": sent when the fitter taps the button. Once per appointment
 * per day.
 * @return array{ok:bool, message:string}
 */
function sms_send_on_the_way(int $clientId, int $apptId, int $userId, ?string $userName): array
{
    if (!sms_kind_on($clientId, 'on_the_way')) {
        return ['ok' => false, 'message' => '"On my way" texts are switched off in Settings → Texts.'];
    }
    $a = sms_appointment($clientId, $apptId);
    if (!$a) return ['ok' => false, 'message' => 'Appointment not found.'];
    if ((string) $a['appointment_date'] !== date('Y-m-d')) {
        return ['ok' => false, 'message' => '"On my way" can only be sent on the day of the appointment.'];
    }
    $sent = sms_on_the_way_sent_at($clientId, [$apptId]);
    if (isset($sent[$apptId])) {
        return ['ok' => false, 'message' => 'The customer was already texted at ' . $sent[$apptId] . '.'];
    }
    $to = sms_appointment_mobile($a);
    if ($to === null) return ['ok' => false, 'message' => 'There is no mobile number for this customer.'];
    $body = sms_render(sms_template($clientId, 'on_the_way'), sms_appointment_vars($a, $userName));
    $res  = sms_send($clientId, $to, $body, [
        'kind' => 'on_the_way', 'customer_id' => $a['customer_id'],
        'appointment_id' => $apptId, 'user_id' => $userId, 'ref_key' => date('Y-m-d'),
    ]);
    return ['ok' => $res['ok'], 'message' => $res['ok'] ? "Customer texted that you're on the way." : $res['message']];
}

/** apptId => "10:42" for appointments that already had their "on the way" text today. */
function sms_on_the_way_sent_at(int $clientId, array $apptIds): array
{
    $ids = array_values(array_filter(array_map('intval', $apptIds)));
    if (!$ids || !sms_ready()) return [];
    try {
        $st = db()->prepare(
            "SELECT appointment_id, MAX(created_at) FROM messages
              WHERE client_id = ? AND kind = 'on_the_way' AND ref_key = ?
                AND status IN ('queued', 'sent', 'delivered')
                AND appointment_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
           GROUP BY appointment_id"
        );
        $st->execute(array_merge([$clientId, date('Y-m-d')], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$id, $at]) $out[(int) $id] = date('H:i', strtotime((string) $at));
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Day-before reminders, run by cron_messages.php every ~15 minutes. Sends
 * from 10am (UK time) until 8pm, so a missed cron run catches up later the
 * same day, for every booked appointment tomorrow whose company has the
 * reminder ticked. One per appointment per date, whatever the outcome (a
 * blocked one isn't retried every 15 minutes).
 * @return array{sent:int, skipped:int, blocked:int}
 */
function sms_run_day_before(?DateTimeImmutable $now = null): array
{
    $now   = $now ?? new DateTimeImmutable('now');
    $stats = ['sent' => 0, 'skipped' => 0, 'blocked' => 0];
    $hour  = (int) $now->format('G');
    if (!sms_ready() || $hour < 10 || $hour >= 20) return $stats;

    $tomorrow = $now->modify('+1 day')->format('Y-m-d');
    $st = db()->prepare(
        "SELECT a.id, a.client_id
           FROM appointments a
           JOIN client_settings s ON s.client_id = a.client_id
          WHERE a.appointment_date = ? AND a.status = 'booked'"
          . (sms_has_online_request_col() ? ' AND a.online_request = 0' : '') . "
            AND s.sms_day_before = 1
            AND NOT EXISTS (SELECT 1 FROM messages m
                             WHERE m.appointment_id = a.id AND m.kind = 'day_before' AND m.ref_key = ?)"
    );
    $st->execute([$tomorrow, $tomorrow]);
    foreach ($st->fetchAll() as $row) {
        $cid = (int) $row['client_id'];
        try {
            if (!sms_kind_on($cid, 'day_before')) { $stats['skipped']++; continue; }
            $a  = sms_appointment($cid, (int) $row['id']);
            $to = $a ? sms_appointment_mobile($a) : null;
            if ($to === null) { $stats['skipped']++; continue; }
            $res = sms_send($cid, $to, sms_render(sms_template($cid, 'day_before'), sms_appointment_vars($a)), [
                'kind' => 'day_before', 'customer_id' => $a['customer_id'],
                'appointment_id' => (int) $row['id'], 'ref_key' => $tomorrow,
            ]);
            $stats[$res['ok'] ? 'sent' : 'blocked']++;
        } catch (Throwable $e) {
            error_log('sms_run_day_before(' . $row['id'] . '): ' . $e->getMessage());
            $stats['blocked']++;
        }
    }
    return $stats;
}
