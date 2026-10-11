<?php
declare(strict_types=1);

/**
 * Messages — every text this company has sent its customers, with what
 * happened to it (sent / delivered / failed / not sent and why).
 *
 * Built on the `messages` table (see _partials/sms.php). Emails will appear
 * here too once they're logged into the same table (messages.channel).
 *
 * Who sees it: admins and staff who can see all customers' jobs (the texts
 * name customers and show their numbers). Paid plans only, like texting.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/customer_access.php';
require_once __DIR__ . '/../_partials/sms.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];

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

$kindLabels = array_map(static fn ($k) => $k['label'], sms_kinds()) + ['test' => 'Test text'];
$kind = (string) ($_GET['kind'] ?? '');
if (!isset($kindLabels[$kind])) $kind = '';

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
    if ($customerId > 0)  { $where[] = 'm.customer_id = ?'; $params[] = $customerId; }
    if ($q !== '') {
        $digits = preg_replace('/\D/', '', $q) ?? '';
        $like   = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $or     = ['c.name LIKE ?', 'm.body LIKE ?'];
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
        "SELECT m.id, m.created_at, m.kind, m.to_addr, m.body, m.segments, m.status,
                m.status_detail, m.delivered_at, m.customer_id, m.appointment_id,
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
$link = static function (array $over) use ($status, $kind, $q, $customerId): string {
    $p = array_merge(['status' => $status, 'kind' => $kind, 'q' => $q, 'customer' => $customerId ?: '', 'page' => 1], $over);
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
        .msg-body { max-width: 30rem; white-space: normal; font-size: 0.8125rem; color: var(--text-secondary, #4b5563); }
        .msg-muted { color: var(--text-faint); font-size: 0.8125rem; }
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
                    Texts sent to your customers, and whether they arrived.
                    <?php if ($smsOn): ?>
                        &middot; <?= number_format($usedThisMonth) ?> of <?= number_format($limit) ?> texts used in <?= e(date('F')) ?>.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!empty($user['role']) && $user['role'] === 'admin'): ?>
                <div><a href="/admin/settings.php#texts" class="btn btn-secondary">Text settings</a></div>
            <?php endif; ?>
        </div>

        <?php if (!$smsOn): ?>
            <div class="alert alert-info" role="status">
                Customer texts are included in the Silver and Gold plans. <a href="/billing/index.php">See plans</a>
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
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search customer, number or wording" aria-label="Search messages">
            <select name="kind" aria-label="Type of text" onchange="this.form.submit()">
                <option value="">All texts</option>
                <?php foreach ($kindLabels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $k === $kind ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($q !== '' || $kind !== ''): ?>
                <a href="<?= e($link(['q' => '', 'kind' => '', 'page' => 1])) ?>">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (!$rows): ?>
            <div class="table-empty" style="padding:2rem 1rem;text-align:center;border:1px dashed var(--border);border-radius:8px">
                <?= ($q !== '' || $kind !== '' || $status !== 'all' || $customerId > 0)
                    ? 'No messages match.'
                    : 'No texts yet. Once you switch texts on in Settings &rarr; Texts, every one sent appears here.' ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
            <table class="table msg-table">
                <thead><tr>
                    <th>When</th><th>Customer</th><th>Text</th><th>Status</th>
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
                            <strong style="font-size:0.875rem"><?= e($kindLabels[(string) $m['kind']] ?? (string) $m['kind']) ?></strong>
                            <?php if ((int) $m['segments'] > 1): ?><span class="msg-muted">(counts as <?= (int) $m['segments'] ?>)</span><?php endif; ?>
                            <?php if (!empty($m['appointment_id'])): ?>
                                &middot; <a href="/calendar/view.php?id=<?= (int) $m['appointment_id'] ?>" class="msg-muted">appointment</a>
                            <?php endif; ?>
                            <div class="msg-body"><?= e((string) $m['body']) ?></div>
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
