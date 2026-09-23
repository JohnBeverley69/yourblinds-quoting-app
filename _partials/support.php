<?php
declare(strict_types=1);

/**
 * Support ("Report a problem") helpers — shared by the widget, the report
 * endpoint and the Master Admin inbox. See migrate_support_tickets.php.
 */

if (function_exists('support_app_version')) {
    return;
}

/** Ticket statuses, in workflow order: key => label. */
function support_statuses(): array
{
    return [
        'new'         => 'New',
        'in_progress' => 'In progress',
        'resolved'    => 'Resolved',
    ];
}

/** What the reporter says it is: key => label. */
function support_categories(): array
{
    return [
        'problem'  => 'Something\'s not working',
        'question' => 'How do I…?',
        'idea'     => 'Suggestion',
    ];
}

/**
 * Master pause switch for the whole support widget.
 *
 * Defaults to PAUSED when unset, so the feature ships DARK — the "💬 Support"
 * button never renders and /support/report.php refuses — until the owner turns
 * it on from Master Admin → Support inbox → AI assistant ("Support widget is
 * live"). This is how the widget "starts paused": no user sees it, and it
 * flips on with one tick when you're ready to field reports.
 */
function support_widget_paused(): bool
{
    require_once __DIR__ . '/accounting.php';   // pc_get lives here
    // Defensive: any read failure (e.g. table missing) leaves it paused.
    try {
        return pc_get('SUPPORT_WIDGET_PAUSED', '1') !== '0';
    } catch (Throwable $e) {
        return true;
    }
}

/**
 * The deployed app version = the short git commit the site is running.
 *
 * Locally that's read straight off .git. The live site has NO .git folder —
 * Cloudways' git deploy syncs the working files only (confirmed with
 * master-admin/diag-app-version.php, 2026-09-23) — so there it falls back to
 * the latest commit on main from GitHub. Every merge to main deploys within a
 * minute or two (.github/workflows/deploy.yml), so that is what's running
 * except in that short window. Cached for 10 minutes; '' if both fail.
 */
function support_app_version(): string
{
    static $ver = null;
    if ($ver === null) {
        $ver = support_git_head_sha();
        if ($ver === '') $ver = support_github_main_sha();
    }
    return $ver;
}

/** Short sha of HEAD read off disk (no shell-out), or '' if there's no .git. */
function support_git_head_sha(): string
{
    $git  = APP_ROOT . '/.git';
    $head = @file_get_contents($git . '/HEAD');
    if ($head === false) {
        return '';
    }
    $head = trim($head);
    if (strpos($head, 'ref: ') !== 0) {
        return substr($head, 0, 7);           // detached HEAD = the sha
    }
    $ref = substr($head, 5);
    $sha = @file_get_contents($git . '/' . $ref);
    if ($sha === false) {
        // Ref may only live in packed-refs after a gc.
        $packed = @file_get_contents($git . '/packed-refs');
        if ($packed !== false && preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m', $packed, $m)) {
            $sha = $m[1];
        }
    }
    return ($sha !== false && $sha !== null) ? substr(trim((string) $sha), 0, 7) : '';
}

/**
 * Short sha of the latest commit on main, from GitHub's public API (the repo
 * is public, so no token). Cached in app_settings for 10 minutes so ticket
 * creation never waits on GitHub more than once in a while; a failed lookup
 * falls back to the last known value.
 */
function support_github_main_sha(): string
{
    require_once __DIR__ . '/app_settings.php';
    $cached = (string) (app_setting_get('support_app_version_cache', '') ?? '');
    [$sha, $at] = array_pad(explode('|', $cached, 2), 2, '0');
    if ($sha !== '' && time() - (int) $at < 600) {
        return $sha;
    }
    $ch = curl_init('https://api.github.com/repos/JohnBeverley69/yourblinds-quoting-app/commits/main');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/vnd.github.sha',   // body is just the 40-char sha
            'User-Agent: YourBlinds-support',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($body) && preg_match('/^[0-9a-f]{40}$/', trim($body))) {
        $sha = substr(trim($body), 0, 7);
        app_setting_set('support_app_version_cache', $sha . '|' . time());
    }
    return $sha;
}

/**
 * Who gets the "new support report" email: SUPPORT_NOTIFY_EMAIL from .env if
 * set (comma-separated allowed), otherwise every super-admin with an email.
 */
function support_notify_recipients(): array
{
    $env = trim((string) (env('SUPPORT_NOTIFY_EMAIL', '') ?? ''));
    $list = [];
    if ($env !== '') {
        $list = array_map('trim', explode(',', $env));
    } else {
        try {
            $list = db()->query(
                "SELECT email FROM client_users
                  WHERE is_super_admin = 1 AND active = 1 AND email IS NOT NULL AND email <> ''"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $list = [];
        }
    }
    return array_values(array_unique(array_filter(
        array_map('strval', $list),
        static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
    )));
}

function support_clip($v, int $max): string
{
    $s = trim(str_replace("\0", '', (string) $v));
    return function_exists('mb_substr') ? mb_substr($s, 0, $max) : substr($s, 0, $max);
}

/**
 * The page context the widget posts with every report / chat message, cleaned
 * and size-capped. js_errors / breadcrumbs are re-encoded JSON (or null);
 * js_errors_list is the decoded error list for callers that want it.
 */
function support_capture_ctx(array $post): array
{
    $jsonList = static function ($raw, int $maxItems): array {
        $arr = json_decode((string) $raw, true);
        if (!is_array($arr)) return [];
        $out = [];
        foreach (array_slice(array_values($arr), -$maxItems) as $item) {
            if (!is_array($item)) continue;
            $row = [];
            foreach ($item as $k => $v) {
                if (!is_scalar($v)) continue;
                $row[support_clip($k, 20)] = support_clip($v, 1000);
            }
            if ($row) $out[] = $row;
        }
        return $out;
    };
    $errs = $jsonList($post['js_errors'] ?? '', 10);
    $bcs  = $jsonList($post['breadcrumbs'] ?? '', 20);
    $enc  = static fn (array $a) => $a ? json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    $url  = (string) ($post['page_url'] ?? '');
    return [
        // Only a real http(s) address is kept — it's rendered as a link in the inbox.
        'page_url'       => preg_match('#^https?://#i', $url) ? support_clip($url, 1000) : '',
        'page_title'     => support_clip($post['page_title'] ?? '', 255),
        'viewport'       => support_clip($post['viewport'] ?? '', 40),
        'js_errors'      => $enc($errs),
        'breadcrumbs'    => $enc($bcs),
        'js_errors_list' => $errs,
    ];
}

/**
 * Save a support ticket and email the owner. $source = 'form' (the report
 * form) or 'ai' (filed by the chat assistant). Returns the new id, or null
 * if the insert failed. The email is best-effort.
 */
function support_create_ticket(array $user, string $category, string $message, array $ctx, string $source = 'form'): ?int
{
    require_once APP_ROOT . '/mailer.php';

    $email = null;
    try {
        $st = db()->prepare('SELECT email FROM client_users WHERE id = ? LIMIT 1');
        $st->execute([(int) $user['user_id']]);
        $email = ($v = $st->fetchColumn()) !== false && $v !== null ? (string) $v : null;
    } catch (Throwable $e) { /* optional */ }

    $row = [
        'client_id'    => (int) $user['client_id'],
        'user_id'      => (int) $user['user_id'],
        'user_name'    => support_clip($user['full_name'] ?? '', 190),
        'company_name' => support_clip($user['company_name'] ?? '', 190),
        'user_email'   => $email !== null ? support_clip($email, 190) : null,
        'category'     => array_key_exists($category, support_categories()) ? $category : 'problem',
        'message'      => support_clip($message, 20000),
        'page_url'     => $ctx['page_url'] ?? '',
        'page_title'   => $ctx['page_title'] ?? '',
        'app_version'  => support_app_version(),
        'user_agent'   => support_clip($_SERVER['HTTP_USER_AGENT'] ?? '', 500),
        'viewport'     => $ctx['viewport'] ?? '',
        'js_errors'    => $ctx['js_errors'] ?? null,
        'breadcrumbs'  => $ctx['breadcrumbs'] ?? null,
    ];

    // `source` arrived with migrate_support_ai.php — include it only once the
    // column exists so the report form keeps working before that runs.
    $cols = array_keys($row);
    $vals = array_values($row);
    try {
        db()->query('SELECT source FROM support_tickets LIMIT 0');
        $cols[] = 'source';
        $vals[] = $source === 'ai' ? 'ai' : 'form';
    } catch (Throwable $e) { /* pre-migration */ }

    try {
        db()->prepare(
            'INSERT INTO support_tickets (' . implode(', ', $cols) . ')
             VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')'
        )->execute($vals);
        $id = (int) db()->lastInsertId();
    } catch (Throwable $e) {
        error_log('[YourBlinds] support ticket insert failed: ' . $e->getMessage());
        return null;
    }

    $to = support_notify_recipients();
    if ($to) {
        $cats   = support_categories();
        $errs   = count($ctx['js_errors_list'] ?? []);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourblinds.uk') . '/master-admin/support.php?id=' . $id;
        $via    = $source === 'ai' ? ' (filed by the AI assistant)' : '';
        $body = "New support report #{$id} — {$cats[$row['category']]}{$via}\n\n"
              . "From:    {$row['user_name']} ({$row['company_name']})" . ($row['user_email'] ? " <{$row['user_email']}>" : '') . "\n"
              . "Page:    {$row['page_url']}\n"
              . "Version: " . ($row['app_version'] !== '' ? $row['app_version'] : 'unknown') . "\n"
              . "JS errors captured: {$errs}\n\n"
              . "----\n{$row['message']}\n----\n\n"
              . "Open it: {$link}\n";
        try {
            mailer_send($to, "[YourBlinds support] #{$id} " . support_clip(preg_replace('/\s+/', ' ', $row['message']), 60), $body,
                null, null,
                $row['user_email'] ? ['reply_to_email' => $row['user_email'], 'reply_to_name' => $row['user_name']] : null);
        } catch (Throwable $e) {
            error_log('[YourBlinds] support ticket email failed: ' . $e->getMessage());
        }
    }
    return $id;
}

/** Count of tickets still marked New — for the sidebar badge. Defensive. */
function support_new_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // pre-migration
    }
}
