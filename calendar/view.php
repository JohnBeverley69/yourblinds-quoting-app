<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /calendar/index.php');
    exit;
}

$validStatuses = ['booked', 'completed', 'cancelled', 'no_show'];

// ---------------------------------------------------------------------------
// Status update — POST to self with action=update_status.
// ---------------------------------------------------------------------------
$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'update_status') {
        $newStatus = (string) ($_POST['status'] ?? '');
        if (!in_array($newStatus, $validStatuses, true)) {
            $_SESSION['flash_error'] = 'Invalid status.';
        } else {
            $u = db()->prepare(
                'UPDATE appointments SET status = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([$newStatus, $id, $clientId]);
            $_SESSION['flash_success'] = 'Status updated to ' . $newStatus . '.';
        }
        header('Location: /calendar/view.php?id=' . $id);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Load appointment, joined to customer and assigned user.
// Tenant-scoped via a.client_id = ?
// ---------------------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT a.*,
            c.id    AS cust_id,
            c.name  AS cust_name,
            c.email AS cust_email,
            c.phone AS cust_phone,
            u.id        AS assignee_id,
            u.full_name AS assignee_name,
            u.email     AS assignee_email
       FROM appointments a
  LEFT JOIN customers    c ON c.id = a.customer_id
  LEFT JOIN client_users u ON u.id = a.client_user_id
      WHERE a.id = ? AND a.client_id = ?'
);
$stmt->execute([$id, $clientId]);
$appt = $stmt->fetch();

if (!$appt) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Appointment not found</h1>'
       . '<p><a href="/calendar/index.php">Back to calendar</a></p>';
    exit;
}

$dateObj = DateTimeImmutable::createFromFormat('Y-m-d', (string) $appt['appointment_date'])
        ?: new DateTimeImmutable($appt['appointment_date']);
$timeObj = DateTimeImmutable::createFromFormat('H:i:s', (string) $appt['appointment_time'])
        ?: DateTimeImmutable::createFromFormat('H:i', (string) $appt['appointment_time']);

$dateLabel = $dateObj->format('l, j F Y');
$timeLabel = $timeObj === false ? (string) $appt['appointment_time'] : strtolower($timeObj->format('g:ia'));
$monthParam = $dateObj->format('Y-m');

$endTimeLabel = '';
if ($timeObj instanceof DateTimeImmutable && (int) $appt['duration_minutes'] > 0) {
    $endObj = $timeObj->modify('+' . (int) $appt['duration_minutes'] . ' minutes');
    $endTimeLabel = strtolower($endObj->format('g:ia'));
}

$statusLabel = ucfirst(str_replace('_', '-', (string) $appt['status']));

// Build the joined installation address as a single human-readable line.
$instParts = array_values(array_filter([
    $appt['installation_address1'] ?? null,
    $appt['installation_address2'] ?? null,
    $appt['installation_town']     ?? null,
    $appt['installation_county']   ?? null,
    $appt['installation_postcode'] ?? null,
], static fn ($v) => $v !== null && $v !== ''));

$billingDifferent = (int) ($appt['different_billing_address'] ?? 0) === 1;
$billParts = $billingDifferent ? array_values(array_filter([
    $appt['billing_address1'] ?? null,
    $appt['billing_address2'] ?? null,
    $appt['billing_town']     ?? null,
    $appt['billing_county']   ?? null,
    $appt['billing_postcode'] ?? null,
], static fn ($v) => $v !== null && $v !== '')) : [];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }
        .detail-card h3 {
            margin: 0 0 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1f3b5b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .detail-list {
            margin: 0;
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 0.5rem 1rem;
            font-size: 0.9375rem;
        }
        .detail-list dt {
            color: #6b7280;
            font-weight: 500;
        }
        .detail-list dd {
            margin: 0;
            color: #111827;
        }
        .address-block {
            font-size: 0.9375rem;
            line-height: 1.45;
            color: #111827;
        }
        .status-pill {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fff;
        }
        .status-pill.status-booked    { background: #2563eb; }
        .status-pill.status-completed { background: #16a34a; }
        .status-pill.status-cancelled { background: #dc2626; }
        .status-pill.status-no_show   { background: #6b7280; }
        .status-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .status-form select {
            font: inherit;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            color: inherit;
            min-width: 9rem;
        }
        .actions-bar {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .notes-block {
            white-space: pre-wrap;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.875rem 1rem;
            font-size: 0.9375rem;
            color: #1f2937;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <input type="checkbox" id="navToggle" class="nav-toggle-input">
    <label class="nav-fab" for="navToggle" aria-label="Open menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </label>
    <label class="nav-close" for="navToggle" aria-label="Close menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </label>
    <label class="nav-backdrop" for="navToggle" aria-hidden="true"></label>
    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="/calendar/index.php" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag"><?= $isAdmin ? 'Admin Console' : 'Trade Portal' ?></span>
        </div>
        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>
        <nav class="app-sidebar-nav">
            <a href="/calendar/index.php" class="active">Calendar</a>
            <a href="<?= $isAdmin ? '/admin/index.php' : '/quote-builder/index.php' ?>">Dashboard</a>
            <a href="/quote-builder/new.php">New Quote</a>
            <a href="/quote-history/index.php">Quote History</a>
            <a href="/customer-manager/index.php">Customers</a>
            <?php if ($isAdmin): ?>
                <a href="/admin/pricing.php">Price Lists</a>
                <a href="/admin/settings.php">Settings</a>
            <?php endif; ?>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= e((string) $appt['title']) ?></h1>
                <p class="page-subtitle">
                    <a href="/calendar/index.php?month=<?= e($monthParam) ?>">&larr; Back to calendar</a>
                </p>
            </div>
            <div class="actions-bar">
                <a href="/quote-builder/new.php?appointment_id=<?= (int) $appt['id'] ?>"
                   class="btn btn-primary">Start quote</a>
                <a href="/calendar/edit.php?id=<?= (int) $appt['id'] ?>"
                   class="btn btn-secondary">Edit</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">When &amp; status</h2>
                <span class="status-pill status-<?= e((string) $appt['status']) ?>">
                    <?= e($statusLabel) ?>
                </span>
            </div>

            <dl class="detail-list">
                <dt>Date</dt><dd><?= e($dateLabel) ?></dd>
                <dt>Time</dt>
                <dd>
                    <?= e($timeLabel) ?>
                    <?php if ($endTimeLabel !== ''): ?>
                        &ndash; <?= e($endTimeLabel) ?>
                    <?php endif; ?>
                    <span style="color:#6b7280">(<?= (int) $appt['duration_minutes'] ?> mins)</span>
                </dd>
                <dt>Assigned to</dt>
                <dd>
                    <?php if (!empty($appt['assignee_name'])): ?>
                        <?= e((string) $appt['assignee_name']) ?>
                    <?php else: ?>
                        <span style="color:#6b7280">Unassigned</span>
                    <?php endif; ?>
                </dd>
            </dl>

            <hr style="border:0;border-top:1px solid #e5e7eb;margin:1.25rem 0">

            <form method="post" action="/calendar/view.php?id=<?= (int) $appt['id'] ?>"
                  class="status-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="update_status">
                <label for="status" style="font-size:0.9375rem;color:#374151;font-weight:500">
                    Update status:
                </label>
                <select id="status" name="status">
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?= e($s) ?>"
                            <?= $s === $appt['status'] ? 'selected' : '' ?>>
                            <?= e(ucfirst(str_replace('_', '-', $s))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Save status</button>
            </form>
        </section>

        <section class="section">
            <div class="detail-grid">
                <div class="detail-card">
                    <h3>Customer</h3>
                    <?php if (!empty($appt['cust_name'])): ?>
                        <dl class="detail-list">
                            <dt>Name</dt>
                            <dd>
                                <a href="/customer-manager/edit.php?id=<?= (int) $appt['cust_id'] ?>">
                                    <?= e((string) $appt['cust_name']) ?>
                                </a>
                            </dd>
                            <?php if (!empty($appt['cust_email'])): ?>
                                <dt>Email</dt>
                                <dd><a href="mailto:<?= e((string) $appt['cust_email']) ?>">
                                    <?= e((string) $appt['cust_email']) ?>
                                </a></dd>
                            <?php endif; ?>
                            <?php if (!empty($appt['cust_phone'])): ?>
                                <dt>Phone</dt>
                                <dd><a href="tel:<?= e((string) $appt['cust_phone']) ?>">
                                    <?= e((string) $appt['cust_phone']) ?>
                                </a></dd>
                            <?php endif; ?>
                        </dl>
                    <?php else: ?>
                        <p style="color:#6b7280;margin:0">
                            Customer record no longer exists.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="detail-card">
                    <h3>Installation address</h3>
                    <?php if ($instParts): ?>
                        <div class="address-block">
                            <?= nl2br(e(implode("\n", $instParts))) ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#6b7280;margin:0">No address recorded.</p>
                    <?php endif; ?>
                </div>

                <div class="detail-card">
                    <h3>Billing address</h3>
                    <?php if ($billingDifferent && $billParts): ?>
                        <div class="address-block">
                            <?= nl2br(e(implode("\n", $billParts))) ?>
                        </div>
                    <?php else: ?>
                        <p style="color:#6b7280;margin:0">
                            Same as installation address.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (!empty($appt['notes'])): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Notes</h2>
                </div>
                <div class="notes-block"><?= e((string) $appt['notes']) ?></div>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
