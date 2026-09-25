<?php
declare(strict_types=1);

/**
 * Support → "🛠 Draft a fix" (phase 3 of the Help widget).
 *
 * From a ticket, the owner sends a REDACTED bug brief to the GitHub Actions
 * workflow .github/workflows/draft-fix.yml, where Claude Code (on the owner's
 * Claude subscription) investigates and a script opens a DRAFT pull request
 * on branch fix/ticket-<id>-<run>. Nothing merges or deploys without a person.
 *
 * The repo is PUBLIC, so the brief carries no names, emails, phone numbers,
 * postcodes, street addresses or tenant ids — the tenant's own customers,
 * addresses and staff names are redacted too, the page title is left out —
 * and the owner sees and can edit it before sending.
 *
 * Needs a fine-grained GitHub token (this repo only; Actions: read & write,
 * Pull requests: read) saved in Master Admin → Support inbox → AI assistant.
 */

if (defined('SUPPORT_FIX_REPO')) {
    return;
}

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/accounting.php';   // pc_get / pc_set / ac_seal / ac_open

const SUPPORT_FIX_REPO     = 'JohnBeverley69/yourblinds-quoting-app';
const SUPPORT_FIX_WORKFLOW = 'draft-fix.yml';

function support_fix_github_token(): string
{
    $stored = pc_get('SUPPORT_FIX_GITHUB_TOKEN');
    return ($stored !== null && $stored !== '') ? (string) (ac_open($stored) ?? '') : '';
}

/**
 * Personal values held by the ticket's tenant that could turn up in a report:
 * its end-customers' names and street addresses (customers + quotes), and its
 * staff's names. Returned as value => tag, longest first so "Mrs Jane Smith"
 * is replaced before "Jane Smith". The regexes below catch emails, phones and
 * postcodes; this catches what a regex can't — who the customer is and where
 * they live.
 */
function support_fix_tenant_pii(int $clientId): array
{
    $out = [];
    if ($clientId <= 0) return $out;
    $grab = static function (string $sql, string $tag) use ($clientId, &$out): void {
        try {
            $st = db()->prepare($sql);
            $st->execute([$clientId]);
            foreach ($st->fetchAll(PDO::FETCH_NUM) as $row) {
                foreach ($row as $v) {
                    $v = trim((string) $v);
                    if (mb_strlen($v) >= 4 && !isset($out[$v])) $out[$v] = $tag;
                }
            }
        } catch (Throwable $e) { /* column/table absent — skip */ }
    };
    $grab('SELECT name FROM customers WHERE client_id = ? LIMIT 20000', '[customer]');
    $grab('SELECT address1, address2 FROM customers WHERE client_id = ? LIMIT 20000', '[address]');
    $grab('SELECT end_customer_name FROM quotes WHERE client_id = ? LIMIT 20000', '[customer]');
    $grab('SELECT end_customer_address1, end_customer_address2 FROM quotes WHERE client_id = ? LIMIT 20000', '[address]');
    $grab('SELECT full_name FROM client_users WHERE client_id = ?', '[user]');
    uksort($out, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    return $out;
}

/** Strip personal details from free text before it can leave for GitHub. */
function support_fix_redact(string $s, array $ticket): string
{
    foreach (['user_name' => '[user]', 'company_name' => '[business]', 'user_email' => '[email]'] as $col => $tag) {
        $v = trim((string) ($ticket[$col] ?? ''));
        if (mb_strlen($v) >= 3) $s = str_ireplace($v, $tag, $s);
    }
    // The tenant's own customers / addresses / staff, matched as whole words.
    foreach (support_fix_tenant_pii((int) ($ticket['client_id'] ?? 0)) as $v => $tag) {
        if (mb_stripos($s, $v) === false) continue;
        $s = (string) preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($v, '/') . '(?![\p{L}\p{N}])/iu', $tag, $s);
    }
    $s = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $s);
    $s = preg_replace('/(?<!\d)(?:\+44\s?|0)\d(?:[\s-]?\d){8,9}(?!\d)/', '[phone]', $s);
    $s = preg_replace('/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i', '[postcode]', $s);
    return (string) $s;
}

/** The brief sent to the workflow: technical detail only, redacted. */
function support_fix_brief(array $ticket): string
{
    $cats = support_categories();
    $path = (string) (parse_url((string) $ticket['page_url'], PHP_URL_PATH) ?? '');
    $qs   = (string) (parse_url((string) $ticket['page_url'], PHP_URL_QUERY) ?? '');
    // Secrets ride in some query strings (customer quote links' token=, scan
    // keys' key=, signed legal links' k=, cron token=) — never publish them.
    if ($qs !== '') {
        parse_str($qs, $qArr);
        foreach ($qArr as $qk => $qv) {
            if (preg_match('/^(token|key|k|t|sig|signature|code|secret|password|pass)$/i', (string) $qk)) {
                $qArr[$qk] = 'REDACTED';
            }
        }
        $qs = http_build_query($qArr);
    }
    $lines = [
        'Type: ' . ($cats[$ticket['category']] ?? $ticket['category']),
        // Page title left out on purpose: it often names the customer.
        'Page: ' . $path . ($qs !== '' ? '?' . $qs : ''),
        'App version (git commit): ' . ($ticket['app_version'] ?: 'unknown'),
        'Browser: ' . $ticket['user_agent'] . ' | viewport ' . $ticket['viewport'],
        'Reporter role: ' . support_fix_reporter_role((int) $ticket['user_id']),
        '',
        'Report:',
        (string) $ticket['message'],
    ];
    $errs = $ticket['js_errors'] ? (array) json_decode((string) $ticket['js_errors'], true) : [];
    if ($errs) {
        $lines[] = '';
        $lines[] = 'JavaScript errors captured:';
        foreach ($errs as $e) $lines[] = '- ' . ($e['page'] ?? '') . ': ' . ($e['msg'] ?? '') . (!empty($e['src']) ? ' (' . $e['src'] . ')' : '');
    }
    $bcs = $ticket['breadcrumbs'] ? (array) json_decode((string) $ticket['breadcrumbs'], true) : [];
    if ($bcs) {
        $lines[] = '';
        $lines[] = 'Last actions before the report (oldest first):';
        foreach ($bcs as $b) $lines[] = '- ' . ($b['act'] ?? '') . ' "' . ($b['what'] ?? '') . '" on ' . ($b['page'] ?? '');
    }
    return support_fix_redact(implode("\n", $lines), $ticket);
}

function support_fix_reporter_role(int $userId): string
{
    try {
        $st = db()->prepare('SELECT role, is_super_admin FROM client_users WHERE id = ?');
        $st->execute([$userId]);
        $r = $st->fetch();
        if (!$r) return 'unknown';
        return !empty($r['is_super_admin']) ? 'platform owner (super-admin)' : (string) $r['role'];
    } catch (Throwable $e) {
        return 'unknown';
    }
}

/** Minimal GitHub REST call. Returns [httpCode, decodedBody|null, rawError]. */
function support_fix_github(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.github.com' . $path);
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: YourBlinds-support',
    ];
    $tok = support_fix_github_token();
    if ($tok !== '') $headers[] = 'Authorization: Bearer ' . $tok;
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = $raw === false ? curl_error($ch) : '';
    curl_close($ch);
    $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if ($err === '' && $code >= 400) $err = is_array($json) ? (string) ($json['message'] ?? '') : ('HTTP ' . $code);
    return [$code, is_array($json) ? $json : null, $err];
}

/** Start the workflow. Returns null on success, else an error message. */
function support_fix_dispatch(int $ticketId, string $brief): ?string
{
    if (support_fix_github_token() === '') {
        return 'Add a GitHub token in the AI assistant settings first.';
    }
    [$code, , $err] = support_fix_github('POST',
        '/repos/' . SUPPORT_FIX_REPO . '/actions/workflows/' . SUPPORT_FIX_WORKFLOW . '/dispatches',
        ['ref' => 'main', 'inputs' => ['ticket_id' => (string) $ticketId, 'brief' => mb_substr($brief, 0, 20000)]]
    );
    if ($code !== 204) {
        return 'GitHub said no (HTTP ' . $code . '): ' . $err;
    }
    try {
        db()->prepare('UPDATE support_tickets SET fix_requested_at = NOW(), status = IF(status = \'new\', \'in_progress\', status) WHERE id = ?')
            ->execute([$ticketId]);
    } catch (Throwable $e) { /* pre-migration: the run still started */ }
    return null;
}

/** Draft-fix pull requests opened for this ticket (newest first). */
function support_fix_prs(int $ticketId): array
{
    [$code, $list] = support_fix_github('GET', '/repos/' . SUPPORT_FIX_REPO . '/pulls?state=all&per_page=50&sort=created&direction=desc');
    if ($code !== 200 || !is_array($list)) return [];
    $prefix = 'fix/ticket-' . $ticketId . '-';
    $out = [];
    foreach ($list as $pr) {
        if (strpos((string) ($pr['head']['ref'] ?? ''), $prefix) !== 0) continue;
        $out[] = [
            'number' => (int) $pr['number'],
            'url'    => (string) $pr['html_url'],
            'state'  => !empty($pr['merged_at']) ? 'merged' : ((string) $pr['state'] === 'closed' ? 'closed' : ((!empty($pr['draft'])) ? 'draft' : 'open')),
        ];
    }
    return $out;
}
