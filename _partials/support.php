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
 * The deployed app version = the short git commit the site is running.
 * Cloudways deploys with a git pull, so .git is on the server; read HEAD
 * straight off disk (no shell-out). Returns '' if it can't be worked out.
 */
function support_app_version(): string
{
    static $ver = null;
    if ($ver !== null) {
        return $ver;
    }
    $ver = '';
    $git = APP_ROOT . '/.git';
    $head = @file_get_contents($git . '/HEAD');
    if ($head === false) {
        return $ver;
    }
    $head = trim($head);
    if (strpos($head, 'ref: ') !== 0) {
        return $ver = substr($head, 0, 7);           // detached HEAD = the sha
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
    return $ver = ($sha !== false && $sha !== null) ? substr(trim((string) $sha), 0, 7) : '';
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

/** Count of tickets still marked New — for the sidebar badge. Defensive. */
function support_new_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // pre-migration
    }
}
