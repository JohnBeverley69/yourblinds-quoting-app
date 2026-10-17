<?php
declare(strict_types=1);

/**
 * Messages — every text and automatic email this company has sent its
 * customers, with what happened to it (sent / delivered / failed / not sent
 * and why).
 *
 * Built on the `messages` table (see _partials/sms.php); messages.channel says
 * text or email. Emails are logged by mailer_send() when the sender passes
 * $opts['log'] (_partials/email_log.php) — quotes, invoices, receipts,
 * appointments, follow-ups, trade documents, supplier orders.
 *
 * Who sees it: admins and staff who can see all customers' jobs (the messages
 * name customers and show their numbers). Every plan — emails are logged for
 * all; texts are the Silver/Gold extra.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/customer_access.php';
require_once __DIR__ . '/../_partials/sms.php';
require_once __DIR__ . '/../_partials/email_log.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];
require_once __DIR__ . '/../_partials/plan_gate.php';
plan_require_feature((int) $clientId, 'messages_log');   // plan features (Master Admin → Plan features)

if (!cm_can_view_all_customers($user)) {
    http_response_code(403);
    exit('You don\'t have access to the message log.');
}

$smsOn = sms_feature_enabled($clientId);

// ── Filters ──────────────────────────────────────────────────────────
$statusGroups = [
    'all'       => ['label' => 'All',       'in' => null],
    'delivered' => ['label' => 'Delivered', 'in' => ['delivered']],
    'sent'      => ['label' => 'Sent',      'in' => ['queued', 'sent']],
    'failed'    => ['label' => 'Failed',    'in' => ['failed']],
    'blocked'   => ['label' => 'Not sent',  'in' => ['blocked']],
];
$status = (string) ($_GET['status'] ?? 'all');
if (!isset($statusGroups[$status])) $status = 'all';

$kindLabels = array_map(static fn ($k) => $k['label'], sms_kinds()) + email_log_kinds() + ['test' => 'Test text'];
$kind = (string) ($_GET['kind'] ?? '');
if (!isset($kindLabels[$kind])) $kind = '';
$channel = (string) ($_GET['channel'] ?? '');
if (!in_array($channel, ['sms', 'email'], true)) $channel = '';

$q          = trim((string) ($_GET['q'] ?? ''));
$customerId = (int) ($_GET['customer'] ?? 0);
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 50;

$rows = [];
$counts = array_fill_keys(array_keys($statusGroups), 0);
$total = 0;
$customerName = null;

if (sms_ready()) {
    $where  = ['m.client_id = ?'];
    $params = [$clientId];
    if ($kind !== '')     { $where[] = 'm.kind = ?';        $params[] = $kind; }
    if ($channel !== '')  { $where[] = 'm.channel = ?';     $params[] = $channel; }
    if ($customerId > 0)  { $where[] = 'm.customer_id = ?'; $params[] = $customerId; }
    if ($q !== '') {
        $digits = preg_replace('/\D/', '', $q) ?? '';
        $like   = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $or     = ['c.name LIKE ?', 'm.body LIKE ?', 'm.to_addr LIKE ?'];   // to_addr: email addresses
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        if (strlen($digits) >= 4) {
            // Numbers are stored as 447…; match "07700…" typed in too.
            $or[] = 'm.to_addr LIKE ?';
            $params[] = '%' . (str_starts_with($digits, '0') ? substr($digits, 1) : $digits) . '%';
        }
        $where[] = '(' . implode(' OR ', $or) . ')';
    }
    $base = 'FROM messages m LEFT JOIN customers c ON c.id = m.customer_id AND c.client_id = m.client_id
             WHERE ' . implode(' AND ', $where);

    // Tab counts (for the current kind / search, across statuses).
    $st = db()->prepare("SELECT m.status, COUNT(*) $base GROUP BY m.status");
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$s, $n]) {
        $counts['all'] += (int) $n;
        foreach ($statusGroups as $g => $def) {
            if ($def['in'] !== null && in_array($s, $def['in'], true)) $counts[$g] += (int) $n;
        }
    }

    $statusSql = '';
    $listParams = $params;
    if ($statusGroups[$status]['in'] !== null) {
        $in = $statusGroups[$status]['in'];
        $statusSql = ' AND m.status IN (' . implode(',', array_fill(0, count($in), '?')) . ')';
        $listParams = array_merge($listParams, $in);
    }
    $total  = $counts[$status];
    $offset = ($page - 1) * $perPage;
    $st = db()->prepare(
        "SELECT m.*,
                c.name AS customer_name,
                (SELECT u.full_name FROM client_users u WHERE u.id = m.user_id) AS sent_by
         $base$statusSql
         ORDER BY m.id DESC LIMIT $perPage OFFSET $offset"
    );
    $st->execute($listParams);
    $rows = $st->fetchAll();

    if ($customerId > 0) {
        $cs = db()->prepare('SELECT name FROM customers WHERE id = ? AND client_id = ? LIMIT 1');
        $cs->execute([$customerId, $clientId]);
        $customerName = $cs->fetchColumn() ?: null;
    }
}

$pages = max(1, (int) ceil($total / $perPage));
$link = static function (array $over) use ($status, $kind, $channel, $q, $customerId): string {
    $p = array_merge(['status' => $status, 'channel' => $channel, 'kind' => $kind, 'q' => $q, 'customer' => $customerId ?: '', 'page' => 1], $over);
    if ((int) $p['page'] <= 1) unset($p['page']);
    $p = array_filter($p, static fn ($v) => $v !== '' && $v !== 'all' && $v !== null);
    return '/messages/index.php' . ($p ? '?' . http_build_query($p) : '');
};
$statusLabel = [
    'queued' => 'Sending', 'sent' => 'Sent', 'delivered' => 'Delivered',
    'failed' => 'Failed', 'blocked' => 'Not sent',
];
$usedThisMonth = sms_used_this_month($clientId);
$limit         = sms_monthly_limit($clientId);

$activeNav = 'messages';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .msg-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 1rem; }
        .msg-filters input[type="search"] { min-width: 14rem; flex: 1 1 14rem; max-width: 24rem; }
        .msg-filters select, .msg-filters input { padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: inherit; font: inherit; font-size: 0.875rem; }
        .msg-pill { display: inline-block; padding: 0.05rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .msg-pill.delivered { background: #dcfce7; color: #166534; }
        .msg-pill.sent, .msg-pill.queued { background: #e0f2fe; color: #075985; }
        .msg-pill.failed { background: #fee2e2; color: #991b1b; }
        .msg-pill.blocked { background: #f3f4f6; color: #4b5563; }
        .msg-why { display: block; font-size: 0.75rem; color: var(--text-faint); margin-top: 0.15rem; max-width: 16rem; white-space: normal; }
        .msg-body { max-width: 30rem; white-space: normal; overflow-wrap: anywhere; font-size: 0.8125rem; color: var(--text-secondary, #4b5563); }
        .msg-muted { color: var(--text-faint); font-size: 0.8125rem; }
        .msg-chan { display: inline-block; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-faint); border: 1px solid var(--border); border-radius: 4px; padding: 0 0.3rem; margin-right: 0.25rem; }
        .msg-table td { vertical-align: top; }
        .msg-pager { display: flex; gap: 0.75rem; align-items: center; margin-top: 1rem; font-size: 0.875rem; }
        @media (max-width: 720px) {
            .msg-table thead { display: none; }
            .msg-table tr { display: block; padding: 0.6rem 0; border-bottom: 1px solid var(--border); }
            .msg-table td { display: block; border: 0 !important; padding: 0.1rem 0 !important; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Messages</h1>
                <p class="page-subtitle">
                    Texts and emails sent to your customers, and whether they arrived.
                    <?php if ($smsOn): $bundleLeft = sms_bundle_balance($clientId); ?>
                        &middot; <?= number_format($usedThisMonth) ?> of your <?= number_format($limit) ?> monthly texts used in <?= e(date('F')) ?><?=
                            $bundleLeft > 0 ? ', plus ' . number_format($bundleLeft) . ' bundle texts left' : '' ?>.
                        When the month&rsquo;s texts run out, texts come from bundles you can buy &mdash; any you don&rsquo;t use carry over.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!empty($user['role']) && $user['role'] === 'admin'): ?>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <?php if ($smsOn): ?><a href="/billing/texts.php" class="btn btn-primary">Buy more texts</a><?php endif; ?>
                    <a href="/admin/settings.php#texts" class="btn btn-secondary">Text settings</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$smsOn): ?>
            <div class="alert alert-info" role="status">
                Emails you send are logged here on every plan. <?php require_once __DIR__ . '/../_partials/billing_helpers.php'; ?>Customer texts are included in <?= e(billing_feature_plans_phrase('feature_sms')) ?>. <a href="/billing/index.php">See plans</a>
            </div>
        <?php endif; ?>

        <?php if ($customerName !== null): ?>
            <p style="margin:0 0 0.75rem">
                Showing messages for <strong><?= e((string) $customerName) ?></strong>
                &middot; <a href="<?= e($link(['customer' => '', 'page' => 1])) ?>">Show everyone</a>
            </p>
        <?php endif; ?>

        <nav class="tabs" aria-label="Filter by status">
            <?php foreach ($statusGroups as $g => $def): ?>
                <a class="tab<?= $g === $status ? ' active' : '' ?>" href="<?= e($link(['status' => $g, 'page' => 1])) ?>">
                    <?= e($def['label']) ?><span class="tab-count"><?= number_format($counts[$g]) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="get" action="/messages/index.php" class="msg-filters" role="search">
            <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <?php if ($customerId > 0): ?><input type="hidden" name="customer" value="<?= (int) $customerId ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search customer, number, email or wording" aria-label="Search messages">
            <select name="channel" aria-label="Texts or emails" onchange="this.form.submit()">
                <option value="">Texts and emails</option>
                <option value="sms" <?= $channel === 'sms' ? 'selected' : '' ?>>Texts only</option>
                <option value="email" <?= $channel === 'email' ? 'selected' : '' ?>>Emails only</option>
            </select>
            <select name="kind" aria-label="Type of message" onchange="this.form.submit()">
                <option value="">All messages</option>
                <?php foreach ($kindLabels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $k === $kind ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($q !== '' || $kind !== '' || $channel !== ''): ?>
                <a href="<?= e($link(['q' => '', 'kind' => '', 'channel' => '', 'page' => 1])) ?>">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (!$rows): ?>
            <div class="table-empty" style="padding:2rem 1rem;text-align:center;border:1px dashed var(--border);border-radius:8px">
                <?= ($q !== '' || $kind !== '' || $channel !== '' || $status !== 'all' || $customerId > 0)
                    ? 'No messages match.'
                    : 'Nothing sent yet. Once you switch texts or quote follow-ups on in Settings &rarr; Texts, every one sent appears here.' ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
            <table class="table msg-table">
                <thead><tr>
                    <th>When</th><th>Customer</th><th>Message</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $m):
                    $st = (string) $m['status'];
                ?>
                    <tr>
                        <td style="white-space:nowrap">
                            <?= e(date('j M, H:i', strtotime((string) $m['created_at']))) ?>
                            <?php if (!empty($m['sent_by'])): ?>
                                <div class="msg-muted">by <?= e((string) $m['sent_by']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($m['customer_id']) && !empty($m['customer_name'])): ?>
                                <a href="/customer-manager/edit.php?id=<?= (int) $m['customer_id'] ?>"><?= e((string) $m['customer_name']) ?></a>
                            <?php elseif ((string) $m['kind'] === 'test'): ?>
                                <span class="msg-muted">Test</span>
                            <?php endif; ?>
                            <div class="msg-muted"><?= e(sms_display_number((string) $m['to_addr'])) ?></div>
                        </td>
                        <td>
                            <?php $isEmail = (string) ($m['channel'] ?? 'sms') === 'email'; ?>
                            <span class="msg-chan"><?= $isEmail ? 'Email' : 'Text' ?></span>
                            <strong style="font-size:0.875rem"><?= e($kindLabels[(string) $m['kind']] ?? (string) $m['kind']) ?></strong>
                            <?php if ((int) $m['segments'] > 1): ?><span class="msg-muted">(counts as <?= (int) $m['segments'] ?>)</span><?php endif; ?>
                            <?php if (!empty($m['appointment_id'])): ?>
                                &middot; <a href="/calendar/view.php?id=<?= (int) $m['appointment_id'] ?>" class="msg-muted">appointment</a>
                            <?php endif; ?>
                            <?php if (!empty($m['quote_id'])): ?>
                                &middot; <a href="/quote-builder/edit.php?id=<?= (int) $m['quote_id'] ?>" class="msg-muted">quote</a>
                            <?php endif; ?>
                            <?php $bodyTxt = (string) $m['body']; if ($isEmail && mb_strlen($bodyTxt) > 220) $bodyTxt = mb_substr($bodyTxt, 0, 220) . '…'; ?>
                            <div class="msg-body"<?= $isEmail ? ' style="white-space:pre-line"' : '' ?>><?= e($bodyTxt) ?></div>
                        </td>
                        <td>
                            <span class="msg-pill <?= e($st) ?>"><?= e($statusLabel[$st] ?? ucfirst($st)) ?></span>
                            <?php if ($st === 'delivered' && !empty($m['delivered_at'])): ?>
                                <span class="msg-why"><?= e(date('j M, H:i', strtotime((string) $m['delivered_at']))) ?></span>
                            <?php elseif (!empty($m['status_detail'])): ?>
                                <span class="msg-why"><?= e((string) $m['status_detail']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if ($pages > 1): ?>
                <div class="msg-pager">
                    <?php if ($page > 1): ?><a href="<?= e($link(['page' => $page - 1])) ?>">&larr; Newer</a><?php endif; ?>
                    <span class="msg-muted">Page <?= $page ?> of <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a href="<?= e($link(['page' => $page + 1])) ?>">Older &rarr;</a><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
