<?php
declare(strict_types=1);

/**
 * Calendar-sync setup page — every logged-in user can generate (or
 * rotate) their personal ICS subscription URL and paste it into
 * Google Calendar / Apple Calendar / Outlook / Thunderbird.
 *
 * Routes:
 *   GET  /calendar/sync-setup.php           → show the user's URL +
 *                                             step-by-step instructions
 *   POST  _action=generate                  → create a fresh token
 *                                             (also rotates if one exists)
 *   POST  _action=revoke                    → delete the token,
 *                                             invalidating the URL
 *
 * Tenant scoping: each user's token belongs to them and their
 * client_id; the feed endpoint enforces the same client_id when it
 * pulls appointments. Tokens are random 48-hex-char strings — wide
 * enough that brute force is infeasible.
 *
 * Privacy: customer names + addresses + phones go into the user's
 * personal calendar, which they may or may not share with family
 * via Google's calendar-sharing features. Worth being upfront
 * about that on this page so the user makes an informed choice.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$userId   = (int) $user['user_id'];
$clientId = (int) $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$pdo = db();

// Defensive — page should explain itself if the migration hasn't run.
$tableExists = false;
try {
    $tableExists = (bool) $pdo->query(
        "SHOW TABLES LIKE 'user_calendar_tokens'"
    )->fetchColumn();
} catch (Throwable $e) { $tableExists = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    try {
        if ($action === 'generate') {
            // 48 hex chars = 192 bits of entropy — way more than
            // enough for an unauthenticated URL endpoint.
            $token = bin2hex(random_bytes(24));
            // Replace any existing row for this user (UNIQUE key on
            // user_id makes it an upsert).
            $pdo->prepare(
                'INSERT INTO user_calendar_tokens (user_id, client_id, token)
                  VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   token = VALUES(token),
                   created_at = CURRENT_TIMESTAMP,
                   last_used_at = NULL'
            )->execute([$userId, $clientId, $token]);
            $_SESSION['flash_success'] = 'Subscription URL generated. Copy it below and paste into your calendar app.';
        } elseif ($action === 'revoke') {
            $pdo->prepare('DELETE FROM user_calendar_tokens WHERE user_id = ?')
                ->execute([$userId]);
            $_SESSION['flash_success'] = 'Subscription revoked. Existing calendar subscriptions will stop syncing on their next poll.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not update token: ' . $e->getMessage();
    }
    header('Location: /calendar/sync-setup.php');
    exit;
}

// Load the user's current token (if any) for display.
$tokenRow = null;
if ($tableExists) {
    try {
        $st = $pdo->prepare(
            'SELECT token, created_at, last_used_at
               FROM user_calendar_tokens WHERE user_id = ? LIMIT 1'
        );
        $st->execute([$userId]);
        $tokenRow = $st->fetch() ?: null;
    } catch (Throwable $e) { /* ignore */ }
}

// Build the full subscription URL using the current request's scheme/host.
$scheme = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
$host   = (string) ($_SERVER['HTTP_HOST'] ?? 'yourblinds.uk');
$feedUrl = $tokenRow
    ? $scheme . '://' . $host . '/calendar/feed.php?t=' . $tokenRow['token']
    : '';

$dashTag = ($user['role'] ?? '') === 'admin' ? 'Admin Console' : 'Trade Portal';
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sync your calendar &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .sync-card {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 12px; padding: 1.125rem 1.25rem;
            margin-bottom: 1rem;
        }
        .sync-card h2 {
            margin: 0 0 0.5rem; font-size: 1.0625rem; color: #1f3b5b;
        }
        .url-box {
            display: flex; gap: 0.5rem; align-items: stretch;
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 8px; padding: 0.375rem;
            margin: 0.625rem 0;
        }
        .url-box input {
            flex: 1; padding: 0.4375rem 0.5625rem;
            border: 1px solid #d1d5db; border-radius: 6px;
            font: 14px/1.4 ui-monospace, Menlo, Consolas, monospace;
            background: #fff; min-width: 0;
        }
        .url-box button {
            padding: 0.4375rem 0.875rem; font-weight: 600;
            background: #1f3b5b; color: #fff; border: 0;
            border-radius: 6px; cursor: pointer; font-size: 0.875rem;
        }
        .url-box button.copied { background: #065f46; }
        .meta-row { font-size: 0.8125rem; color: #6b7280; margin-top: 0.375rem; }
        .step-list {
            margin: 0.5rem 0 0; padding-left: 1.25rem;
            line-height: 1.6; color: #374151;
        }
        .step-list li { margin-bottom: 0.375rem; }
        .step-list code {
            background: #f3f4f6; padding: 0.0625rem 0.375rem;
            border-radius: 4px; font-size: 0.8125rem;
        }
        details.app-instructions {
            border-top: 1px solid #f3f4f6; padding-top: 0.625rem;
            margin-top: 0.625rem;
        }
        details.app-instructions summary {
            cursor: pointer; font-weight: 600; color: #1f3b5b;
            font-size: 0.9375rem; padding: 0.25rem 0;
        }
        details.app-instructions[open] summary { margin-bottom: 0.375rem; }
        .privacy-note {
            background: #fef3c7; border: 1px solid #fde68a;
            color: #78350f; padding: 0.625rem 0.875rem;
            border-radius: 8px; font-size: 0.875rem;
            line-height: 1.5; margin-top: 1rem;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Sync your jobs to your phone calendar</h1>
                <p class="page-subtitle">
                    <a href="/calendar/index.php">&larr; Calendar</a>
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if (!$tableExists): ?>
            <div class="alert alert-error" role="alert">
                The calendar-sync feature isn't enabled on this database yet.
                A super-admin needs to run
                <a href="/migrate_calendar_tokens.php"><code>/migrate_calendar_tokens.php</code></a>
                once.
            </div>
        <?php else: ?>

            <div class="sync-card">
                <h2>How it works</h2>
                <p style="margin:0;color:#4b5563;font-size:0.9375rem;line-height:1.55">
                    Generate your personal subscription link below, then paste
                    it into Google Calendar, Apple Calendar, Outlook, or any
                    other calendar app. Your YourBlinds appointments will show
                    up alongside your personal events &mdash; including on
                    your phone. The calendar app polls the link every few
                    hours, so changes you make in YourBlinds appear in your
                    other calendar automatically (usually within 12 hours;
                    some apps poll faster).
                </p>
            </div>

            <div class="sync-card">
                <h2>Your subscription URL</h2>

                <?php if ($tokenRow): ?>
                    <p style="margin:0;color:#4b5563;font-size:0.875rem">
                        Copy this URL and paste it into your calendar app
                        as a "subscribed" / "from URL" calendar.
                        <strong>Keep it private</strong> — anyone with the
                        link can see your appointments.
                    </p>
                    <div class="url-box">
                        <input id="feed-url" type="text" readonly
                               value="<?= e($feedUrl) ?>"
                               onclick="this.select()">
                        <button type="button" id="copy-btn">Copy</button>
                    </div>
                    <div class="meta-row">
                        Created <?= e(date('j M Y, H:i', strtotime((string) $tokenRow['created_at']))) ?>
                        <?php if (!empty($tokenRow['last_used_at'])): ?>
                            &middot; last polled
                            <?= e(date('j M Y, H:i', strtotime((string) $tokenRow['last_used_at']))) ?>
                        <?php else: ?>
                            &middot; not yet polled by any calendar app
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:0.5rem;margin-top:0.875rem;flex-wrap:wrap">
                        <form method="post" action="/calendar/sync-setup.php"
                              data-confirm="Generate a fresh URL? Any calendar app you've already subscribed with the OLD URL will stop syncing — you'll need to re-paste the new one. Useful if you think your URL has leaked.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="generate">
                            <button type="submit" class="btn btn-secondary">Regenerate URL</button>
                        </form>
                        <form method="post" action="/calendar/sync-setup.php"
                              data-confirm="Revoke the URL? Existing subscriptions will stop syncing on their next poll.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="revoke">
                            <button type="submit" class="btn btn-secondary"
                                    style="color:#b91c1c">Revoke</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="margin:0 0 0.875rem;color:#4b5563;font-size:0.9375rem">
                        You don't have a subscription URL yet. Generate one to
                        get started.
                    </p>
                    <form method="post" action="/calendar/sync-setup.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="generate">
                        <button type="submit" class="btn btn-primary">
                            Generate my subscription URL
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($tokenRow): ?>
                    <div class="privacy-note">
                        <strong>Privacy heads-up:</strong> the URL lets anyone
                        with it see your appointments (customer name, phone,
                        address). Don't share it. If you've shared your
                        calendar with family via Google's calendar-sharing,
                        they'll see these appointments too. Revoke any time.
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($tokenRow): ?>
                <div class="sync-card">
                    <h2>How to add it to your calendar app</h2>

                    <details class="app-instructions" open>
                        <summary>📅 Google Calendar (web + Android phone)</summary>
                        <ol class="step-list">
                            <li>Open <a href="https://calendar.google.com/" target="_blank" rel="noopener">calendar.google.com</a> on a computer (the phone app doesn't have this option).</li>
                            <li>In the left sidebar, next to <strong>Other calendars</strong>, click the <strong>+</strong> button.</li>
                            <li>Choose <strong>From URL</strong>.</li>
                            <li>Paste the URL above and click <strong>Add calendar</strong>.</li>
                            <li>The calendar appears in your sidebar. Open the Google Calendar app on your phone — it'll show up there too within a few minutes.</li>
                        </ol>
                        <p style="margin:0.5rem 0 0;color:#6b7280;font-size:0.8125rem">
                            Google polls subscribed URLs every few hours. Don't expect instant updates — usually within 12 hours of a change in YourBlinds.
                        </p>
                    </details>

                    <details class="app-instructions">
                        <summary>🍎 Apple Calendar (iPhone, iPad, Mac)</summary>
                        <ol class="step-list">
                            <li><strong>iPhone / iPad:</strong> Settings → Calendar → Accounts → Add Account → Other → Add Subscribed Calendar. Paste the URL.</li>
                            <li><strong>Mac:</strong> Calendar app → File menu → New Calendar Subscription. Paste the URL.</li>
                        </ol>
                    </details>

                    <details class="app-instructions">
                        <summary>📨 Outlook (Microsoft 365 web + desktop)</summary>
                        <ol class="step-list">
                            <li>Open Outlook on the web at <a href="https://outlook.live.com/calendar" target="_blank" rel="noopener">outlook.live.com/calendar</a>.</li>
                            <li>In the left sidebar click <strong>Add calendar</strong> → <strong>Subscribe from web</strong>.</li>
                            <li>Paste the URL, give the calendar a name, click Import.</li>
                        </ol>
                    </details>

                    <details class="app-instructions">
                        <summary>🐦 Thunderbird (desktop)</summary>
                        <ol class="step-list">
                            <li>Open the Calendar tab.</li>
                            <li>Right-click in the calendar list → <strong>New Calendar</strong> → On the Network → iCalendar (ICS) → paste the URL.</li>
                        </ol>
                    </details>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>

<?php if ($tokenRow): ?>
<script>
(function () {
    var btn = document.getElementById('copy-btn');
    var url = document.getElementById('feed-url');
    if (!btn || !url) return;
    btn.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(url.value);
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(function () {
                btn.textContent = 'Copy';
                btn.classList.remove('copied');
            }, 1800);
        } catch (e) {
            // Older browsers / non-https — fall back to select.
            url.select();
            url.setSelectionRange(0, 99999);
            document.execCommand('copy');
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = 'Copy'; }, 1800);
        }
    });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
