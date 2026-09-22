<?php
declare(strict_types=1);

/**
 * Master Admin: Support inbox.
 *
 * Every report sent from the floating "Help / Report a problem" widget
 * (_partials/support_widget.php), with the context it captured automatically:
 * page, tenant + user, app version (git commit), browser, recent JavaScript
 * errors and the last clicks before the report. List view filters by status;
 * ?id=N opens one ticket to read, change status and keep private notes.
 *
 * "Copy for Claude" puts a ready-to-paste bug brief on the clipboard.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/support.php';
require_once __DIR__ . '/../_partials/time_ago.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$statuses = support_statuses();
$cats     = support_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && array_key_exists($status, $statuses)) {
        try {
            $pdo->prepare('UPDATE support_tickets SET status = ?, admin_notes = ? WHERE id = ?')
                ->execute([$status, trim((string) ($_POST['admin_notes'] ?? '')) ?: null, $id]);
            $_SESSION['flash_success'] = "Ticket #{$id} saved.";
        } catch (Throwable $e) {
            error_log('[YourBlinds] support ticket update failed: ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Could not save the ticket.';
        }
    }
    header('Location: /master-admin/support.php?id=' . $id);
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$badge = ['new' => 'badge-sent', 'in_progress' => 'badge-ordered', 'resolved' => 'badge-accepted'];

$viewId  = (int) ($_GET['id'] ?? 0);
$filter  = (string) ($_GET['status'] ?? 'open');
$ticket  = null;
$tickets = [];
$counts  = [];
$missing = false;

try {
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status') as $r) {
        $counts[$r['status']] = (int) $r['n'];
    }
    if ($viewId > 0) {
        $st = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ?');
        $st->execute([$viewId]);
        $ticket = $st->fetch() ?: null;
    } else {
        $where = match (true) {
            $filter === 'all'                        => '1 = 1',
            array_key_exists($filter, $statuses)     => 'status = ' . $pdo->quote($filter),
            default                                  => "status <> 'resolved'",
        };
        $tickets = $pdo->query(
            "SELECT id, category, message, company_name, user_name, page_url, status, created_at,
                    (js_errors IS NOT NULL) AS has_errors
               FROM support_tickets WHERE $where
           ORDER BY (status = 'new') DESC, created_at DESC LIMIT 200"
        )->fetchAll();
    }
} catch (Throwable $e) {
    $missing = true;
}

$decode = static fn (?string $j): array => $j ? ((array) json_decode($j, true)) : [];

// Plain-text brief for pasting into a Claude session.
$brief = '';
if ($ticket) {
    $catLabel = $cats[$ticket['category']] ?? $ticket['category'];
    $lines = [
        "Support ticket #{$ticket['id']} ({$catLabel}) — {$ticket['created_at']}",
        "From: {$ticket['user_name']} @ {$ticket['company_name']} (client_id {$ticket['client_id']}, user_id {$ticket['user_id']})",
        "Page: {$ticket['page_url']}" . ($ticket['page_title'] ? " — \"{$ticket['page_title']}\"" : ''),
        "Version: " . ($ticket['app_version'] ?: 'unknown') . " | Viewport: {$ticket['viewport']}",
        "Browser: {$ticket['user_agent']}",
        '',
        'What they said:',
        $ticket['message'],
    ];
    $errs = $decode($ticket['js_errors']);
    if ($errs) {
        $lines[] = '';
        $lines[] = 'JavaScript errors:';
        foreach ($errs as $er) $lines[] = "- [{$er['t']}] {$er['page']}: {$er['msg']}" . (!empty($er['src']) ? " ({$er['src']})" : '');
    }
    $bcs = $decode($ticket['breadcrumbs']);
    if ($bcs) {
        $lines[] = '';
        $lines[] = 'Last actions (oldest first):';
        foreach ($bcs as $b) $lines[] = "- [{$b['t']}] {$b['act']} \"{$b['what']}\" on {$b['page']}";
    }
    $brief = implode("\n", $lines);
}

$activeNav = 'support';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support inbox &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .sup-msg { white-space: pre-wrap; background: var(--bg-subtle); border: 1px solid var(--border);
                   border-radius: 8px; padding: .75rem 1rem; margin: 0 0 1rem; color: var(--text-body); }
        .sup-meta { display: grid; grid-template-columns: max-content 1fr; gap: .3rem 1rem; margin: 0 0 1rem; font-size: .9rem; }
        .sup-meta dt { color: var(--text-faint); }
        .sup-meta dd { margin: 0; word-break: break-word; }
        .sup-list { margin: 0 0 1rem; padding-left: 1.1rem; font: .82rem/1.5 ui-monospace, Consolas, monospace; }
        .sup-list li { word-break: break-word; }
        .sup-err { color: #b91c1c; }
        [data-theme="dark"] .sup-err { color: #fca5a5; }
        .sup-snip { color: var(--text-secondary); max-width: 34rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= $ticket ? 'Support ticket #' . (int) $ticket['id'] : 'Support inbox' ?></h1>
                <p class="page-subtitle">
                    Reports sent from the <strong>? Help</strong> button on every page, with the page, version,
                    browser, JavaScript errors and last clicks captured automatically.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <?php if ($ticket): ?>
                    <a href="/master-admin/support.php" class="btn btn-secondary">&larr; Inbox</a>
                <?php else: ?>
                    <a href="/master-admin/index.php" class="btn btn-secondary">&larr; Master Admin</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php if ($missing): ?>
            <section class="section">
                <p style="color:var(--text-secondary)">
                    The support inbox isn't set up yet &mdash; run <strong>/migrate_support_tickets.php</strong> first.
                </p>
            </section>

        <?php elseif ($viewId > 0 && !$ticket): ?>
            <section class="section"><p>That ticket doesn't exist.</p></section>

        <?php elseif ($ticket): ?>
            <?php $errs = $decode($ticket['js_errors']); $bcs = $decode($ticket['breadcrumbs']); ?>
            <section class="section">
                <p style="margin:0 0 .5rem">
                    <span class="badge <?= e($badge[$ticket['status']] ?? 'badge-draft') ?>"><?= e($statuses[$ticket['status']] ?? $ticket['status']) ?></span>
                    &nbsp;<strong><?= e($cats[$ticket['category']] ?? $ticket['category']) ?></strong>
                    &middot; <?= e(time_ago((string) $ticket['created_at'])) ?>
                </p>
                <div class="sup-msg"><?= e((string) $ticket['message']) ?></div>

                <dl class="sup-meta">
                    <dt>From</dt>
                    <dd><?= e((string) $ticket['user_name']) ?> &middot; <?= e((string) $ticket['company_name']) ?>
                        <?php if ($ticket['user_email']): ?>
                            &middot; <a href="mailto:<?= e((string) $ticket['user_email']) ?>?subject=<?= rawurlencode('Re: your YourBlinds report #' . $ticket['id']) ?>"><?= e((string) $ticket['user_email']) ?></a>
                        <?php endif; ?>
                    </dd>
                    <dt>Page</dt>
                    <dd><?php if (preg_match('#^https?://#i', (string) $ticket['page_url'])): ?>
                            <a href="<?= e((string) $ticket['page_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $ticket['page_url']) ?></a>
                        <?php else: ?><?= e((string) $ticket['page_url']) ?><?php endif; ?>
                        <?php if ($ticket['page_title']): ?><br><span style="color:var(--text-faint)"><?= e((string) $ticket['page_title']) ?></span><?php endif; ?></dd>
                    <dt>Version</dt>
                    <dd><?php if ($ticket['app_version']): ?>
                            <a href="https://github.com/JohnBeverley69/yourblinds-quoting-app/commit/<?= e((string) $ticket['app_version']) ?>" target="_blank" rel="noopener"><code><?= e((string) $ticket['app_version']) ?></code></a>
                        <?php else: ?>unknown<?php endif; ?></dd>
                    <dt>Browser</dt>
                    <dd><?= e((string) $ticket['user_agent']) ?> &middot; <?= e((string) $ticket['viewport']) ?></dd>
                    <dt>IDs</dt>
                    <dd>client <?= (int) $ticket['client_id'] ?> &middot; user <?= (int) $ticket['user_id'] ?></dd>
                </dl>

                <h2 style="font-size:1rem;margin:1.25rem 0 .4rem">JavaScript errors (<?= count($errs) ?>)</h2>
                <?php if ($errs): ?>
                    <ol class="sup-list">
                        <?php foreach ($errs as $er): ?>
                            <li class="sup-err">[<?= e((string) ($er['t'] ?? '')) ?>] <?= e((string) ($er['page'] ?? '')) ?> &mdash; <?= e((string) ($er['msg'] ?? '')) ?>
                                <?php if (!empty($er['src'])): ?><span style="color:var(--text-faint)">(<?= e((string) $er['src']) ?>)</span><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p style="color:var(--text-faint);font-size:.9rem">None captured.</p>
                <?php endif; ?>

                <h2 style="font-size:1rem;margin:1.25rem 0 .4rem">Last actions (oldest first)</h2>
                <?php if ($bcs): ?>
                    <ol class="sup-list">
                        <?php foreach ($bcs as $b): ?>
                            <li>[<?= e((string) ($b['t'] ?? '')) ?>] <?= e((string) ($b['act'] ?? '')) ?> &ldquo;<?= e((string) ($b['what'] ?? '')) ?>&rdquo; <span style="color:var(--text-faint)">on <?= e((string) ($b['page'] ?? '')) ?></span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p style="color:var(--text-faint);font-size:.9rem">None captured.</p>
                <?php endif; ?>

                <p style="margin:1rem 0">
                    <button type="button" class="btn btn-secondary" id="supCopy">Copy for Claude</button>
                    <span id="supCopied" role="status" style="margin-left:.5rem;color:var(--text-faint)"></span>
                </p>
                <textarea id="supBrief" hidden><?= e($brief) ?></textarea>
            </section>

            <section class="section">
                <form method="post" action="/master-admin/support.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
                    <div class="form-group">
                        <label for="supStatus">Status</label>
                        <select id="supStatus" name="status">
                            <?php foreach ($statuses as $sk => $sl): ?>
                                <option value="<?= e($sk) ?>"<?= $ticket['status'] === $sk ? ' selected' : '' ?>><?= e($sl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="supNotes">Private notes</label>
                        <textarea id="supNotes" name="admin_notes" rows="4" placeholder="What you found, the PR that fixed it…"><?= e((string) ($ticket['admin_notes'] ?? '')) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </section>
            <script>
            document.getElementById('supCopy').addEventListener('click', function () {
                var t = document.getElementById('supBrief').value, out = document.getElementById('supCopied');
                (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject())
                    .then(function () { out.textContent = 'Copied — paste it into Claude.'; })
                    .catch(function () { out.textContent = 'Copy blocked — select the text below instead.';
                        var ta = document.getElementById('supBrief'); ta.hidden = false; ta.style.width = '100%'; ta.rows = 12; ta.select(); });
            });
            </script>

        <?php else: ?>
            <nav class="tabs" aria-label="Filter tickets">
                <?php
                $open = ($counts['new'] ?? 0) + ($counts['in_progress'] ?? 0);
                $tabs = ['open' => "Open ($open)"];
                foreach ($statuses as $sk => $sl) $tabs[$sk] = $sl . ' (' . ($counts[$sk] ?? 0) . ')';
                $tabs['all'] = 'All (' . array_sum($counts) . ')';
                foreach ($tabs as $tk => $tl): ?>
                    <a class="tab<?= $filter === $tk || ($tk === 'open' && !isset($tabs[$filter])) ? ' active' : '' ?>"
                       href="/master-admin/support.php?status=<?= e($tk) ?>"><?= e($tl) ?></a>
                <?php endforeach; ?>
            </nav>

            <section class="section">
                <?php if (!$tickets): ?>
                    <p style="color:var(--text-secondary)">Nothing here. 🎉</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Status</th>
                                    <th>Report</th>
                                    <th>From</th>
                                    <th>Page</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td><a href="/master-admin/support.php?id=<?= (int) $t['id'] ?>"><?= (int) $t['id'] ?></a></td>
                                    <td><span class="badge <?= e($badge[$t['status']] ?? 'badge-draft') ?>"><?= e($statuses[$t['status']] ?? $t['status']) ?></span></td>
                                    <td>
                                        <a href="/master-admin/support.php?id=<?= (int) $t['id'] ?>" class="sup-snip" style="display:block">
                                            <?= (int) $t['has_errors'] ? '<span class="sup-err" title="JavaScript errors captured">⚠</span> ' : '' ?><?= e((string) $t['message']) ?>
                                        </a>
                                        <span style="font-size:.78rem;color:var(--text-faint)"><?= e($cats[$t['category']] ?? $t['category']) ?></span>
                                    </td>
                                    <td><?= e((string) $t['user_name']) ?><br><span style="font-size:.8rem;color:var(--text-faint)"><?= e((string) $t['company_name']) ?></span></td>
                                    <td style="font-size:.82rem"><?= e((string) (parse_url((string) $t['page_url'], PHP_URL_PATH) ?: '')) ?></td>
                                    <td style="white-space:nowrap"><?= e(time_ago((string) $t['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
