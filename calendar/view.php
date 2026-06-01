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
            header('Location: /calendar/view.php?id=' . $id);
            exit;
        }

        // Defence-in-depth — the view gate further down protects GET
        // but the POST handler fires before that. Anyone without admin
        // / view-all must be the assignee of THIS appointment to flip
        // its status.
        $canUpdateAny = $isAdmin;
        if (!$canUpdateAny) {
            $pSt = db()->prepare(
                'SELECT COALESCE(can_view_all_customer_jobs, 0)
                   FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
            );
            $pSt->execute([(int) $user['user_id'], $clientId]);
            $canUpdateAny = ((int) $pSt->fetchColumn()) === 1;
        }
        if (!$canUpdateAny) {
            $ownChk = db()->prepare(
                'SELECT 1 FROM appointments
                  WHERE id = ? AND client_id = ? AND client_user_id = ? LIMIT 1'
            );
            $ownChk->execute([$id, $clientId, (int) $user['user_id']]);
            if (!$ownChk->fetchColumn()) {
                http_response_code(404);
                exit('Appointment not found.');
            }
        }

        $u = db()->prepare(
            'UPDATE appointments SET status = ?
              WHERE id = ? AND client_id = ?'
        );
        $u->execute([$newStatus, $id, $clientId]);

        // Auto-advance the linked quote to 'fitted' when the
        // appointment is marked complete. Only fires for the
        // 'completed' status (not 'cancelled' or 'no_show'), only
        // when there's a quote_id, and only when the quote is
        // currently 'accepted' or 'ordered' (the helper guards the
        // state machine internally). Adds a sentence to the flash
        // so the user knows it happened.
        $autoAdvanceNote = '';
        if ($newStatus === 'completed') {
            $qLink = db()->prepare(
                'SELECT quote_id FROM appointments
                  WHERE id = ? AND client_id = ? LIMIT 1'
            );
            $qLink->execute([$id, $clientId]);
            $quoteId = (int) ($qLink->fetchColumn() ?: 0);
            if ($quoteId > 0) {
                require_once __DIR__ . '/../quote-builder/_helpers.php';
                $advancedRef = qb_advance_quote_to_fitted(db(), $quoteId, $clientId);
                if ($advancedRef !== null) {
                    $autoAdvanceNote = ' Linked quote ' . $advancedRef
                                     . ' advanced to "fitted".';
                }
            }
        }

        $_SESSION['flash_success'] = 'Status updated to ' . $newStatus . '.'
                                   . $autoAdvanceNote;
        header('Location: /calendar/view.php?id=' . $id);
        exit;
    }

    if ($action === 'update_assignee') {
        // Permission re-check on the server — the form's hidden in the
        // UI for users without it but we can't trust that alone.
        $canReassign = $isAdmin;
        if (!$canReassign) {
            $permSt = db()->prepare(
                'SELECT COALESCE(can_view_all_customer_jobs, 0)
                   FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
            );
            $permSt->execute([(int) $user['user_id'], $clientId]);
            $canReassign = ((int) $permSt->fetchColumn()) === 1;
        }
        if (!$canReassign) {
            $_SESSION['flash_error'] = 'You don\'t have permission to reassign appointments.';
            header('Location: /calendar/view.php?id=' . $id);
            exit;
        }

        // Empty value = unassign. Otherwise must be a real active user
        // in THIS tenant — otherwise we silently ignore (the SQL has
        // a tenant-scoped EXISTS subquery via the FK + WHERE).
        $newAssigneeRaw = (string) ($_POST['assignee_id'] ?? '');
        $newAssignee   = $newAssigneeRaw === '' ? null : (int) $newAssigneeRaw;

        if ($newAssignee !== null) {
            $chk = db()->prepare(
                'SELECT 1 FROM client_users
                  WHERE id = ? AND client_id = ? AND active = 1 LIMIT 1'
            );
            $chk->execute([$newAssignee, $clientId]);
            if (!$chk->fetchColumn()) {
                $_SESSION['flash_error'] = 'That user isn\'t available to assign.';
                header('Location: /calendar/view.php?id=' . $id);
                exit;
            }
        }

        $u = db()->prepare(
            'UPDATE appointments SET client_user_id = ?
              WHERE id = ? AND client_id = ?'
        );
        $u->execute([$newAssignee, $id, $clientId]);
        $_SESSION['flash_success'] = $newAssignee === null
            ? 'Appointment unassigned.'
            : 'Appointment reassigned.';
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
            u.email     AS assignee_email,
            COALESCE(s.feature_maps, 0) AS feature_maps
       FROM appointments a
  LEFT JOIN customers       c ON c.id = a.customer_id
  LEFT JOIN client_users    u ON u.id = a.client_user_id
  LEFT JOIN client_settings s ON s.client_id = a.client_id
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

// Permission gate: non-admin users without can_view_all_customer_jobs
// can only view appointments they're personally assigned to. 404
// (not 403) on a mismatch — don't leak existence to a fitter who's
// guessing IDs.
$canViewThis = $isAdmin;
if (!$canViewThis) {
    $permSt = db()->prepare(
        'SELECT COALESCE(can_view_all_customer_jobs, 0)
           FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $permSt->execute([(int) $user['user_id'], $clientId]);
    $canViewThis = ((int) $permSt->fetchColumn()) === 1;
}
if (!$canViewThis) {
    $canViewThis = ((int) ($appt['client_user_id'] ?? 0)) === (int) $user['user_id'];
}
if (!$canViewThis) {
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

// Can the current user reassign this appointment? Admin or anyone
// with can_view_all_customer_jobs (the dispatcher / office role).
$canReassign = $isAdmin;
if (!$canReassign) {
    $permSt = db()->prepare(
        'SELECT COALESCE(can_view_all_customer_jobs, 0)
           FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $permSt->execute([(int) $user['user_id'], $clientId]);
    $canReassign = ((int) $permSt->fetchColumn()) === 1;
}

// List of active users in this tenant for the assignee dropdown,
// only loaded if the user can actually reassign.
$tenantUsers = [];
if ($canReassign) {
    $usSt = db()->prepare(
        'SELECT id, full_name, role
           FROM client_users
          WHERE client_id = ? AND active = 1
       ORDER BY full_name'
    );
    $usSt->execute([$clientId]);
    $tenantUsers = $usSt->fetchAll();
}

// Build the joined installation address as a single human-readable line.
$instParts = array_values(array_filter([
    $appt['installation_address1'] ?? null,
    $appt['installation_address2'] ?? null,
    $appt['installation_town']     ?? null,
    $appt['installation_county']   ?? null,
    $appt['installation_postcode'] ?? null,
], static fn ($v) => $v !== null && $v !== ''));

// Maps add-on — when this client has feature_maps enabled and we have at
// least one address line, expose deep-links to both Google Maps and Waze.
// Both URLs hand off to the app if installed, otherwise the web equivalent.
$mapsEnabled   = ((int) ($appt['feature_maps'] ?? 0)) === 1;
$googleMapsUrl = '';
$wazeUrl       = '';
if ($mapsEnabled && $instParts) {
    $destination   = urlencode(implode(', ', $instParts));
    $googleMapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $destination;
    $wazeUrl       = 'https://waze.com/ul?q=' . $destination . '&navigate=yes';
}

$billingDifferent = (int) ($appt['different_billing_address'] ?? 0) === 1;
$billParts = $billingDifferent ? array_values(array_filter([
    $appt['billing_address1'] ?? null,
    $appt['billing_address2'] ?? null,
    $appt['billing_town']     ?? null,
    $appt['billing_county']   ?? null,
    $appt['billing_postcode'] ?? null,
], static fn ($v) => $v !== null && $v !== '')) : [];
$activeNav = 'calendar';
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
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= e((string) $appt['title']) ?></h1>
                <p class="page-subtitle">
                    <a href="/calendar/index.php?month=<?= e($monthParam) ?>">&larr; Back to calendar</a>
                </p>
            </div>
            <div class="actions-bar">
                <?php if ($googleMapsUrl !== ''): ?>
                    <a href="<?= e($googleMapsUrl) ?>"
                       class="btn btn-success"
                       target="_blank" rel="noopener">Google Maps &rarr;</a>
                    <a href="<?= e($wazeUrl) ?>"
                       class="btn btn-success"
                       target="_blank" rel="noopener">Waze &rarr;</a>
                <?php endif; ?>
                <?php if (!empty($appt['quote_id'])): ?>
                    <!-- The appointment has a linked quote (auto-created
                         on customer acceptance). It's an order by this
                         point, not a draft quote — label accordingly.
                         Primary CTA so fitters can jump straight from
                         the appointment to verify blinds + take payment. -->
                    <a href="/quote-builder/edit.php?id=<?= (int) $appt['quote_id'] ?>"
                       class="btn btn-primary">Open order &rarr;</a>
                <?php else: ?>
                    <a href="/quote-builder/new.php?appointment_id=<?= (int) $appt['id'] ?>"
                       class="btn btn-primary">Start quote</a>
                <?php endif; ?>
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
                    <?php if ($canReassign): ?>
                        <form method="post"
                              action="/calendar/view.php?id=<?= (int) $appt['id'] ?>"
                              style="display:flex;gap:0.375rem;align-items:center;margin:0;flex-wrap:wrap">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="update_assignee">
                            <select name="assignee_id"
                                    style="padding:0.3125rem 0.5rem;border:1px solid #d1d5db;
                                           border-radius:6px;font:inherit;min-width:10rem">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($tenantUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                            <?= (int) ($appt['assignee_id'] ?? 0) === (int) $u['id']
                                                ? 'selected' : '' ?>>
                                        <?= e((string) $u['full_name']) ?>
                                        <?php if (!empty($u['role'])): ?>
                                            (<?= e((string) $u['role']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm"
                                    style="padding:0.3125rem 0.75rem;font-size:0.8125rem">
                                Save
                            </button>
                        </form>
                    <?php else: ?>
                        <?php if (!empty($appt['assignee_name'])): ?>
                            <?= e((string) $appt['assignee_name']) ?>
                        <?php else: ?>
                            <span style="color:#6b7280">Unassigned</span>
                        <?php endif; ?>
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

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Danger zone</h2>
            </div>
            <p style="margin:0 0 0.875rem;color:#6b7280;font-size:0.9375rem">
                Deleting this appointment is permanent. The customer record and any
                linked quotes will be kept; only the calendar entry is removed. If you
                just want to record that the booking didn't happen, set the status to
                <em>Cancelled</em> or <em>No-show</em> above instead.
            </p>
            <form method="post" action="/calendar/delete.php" style="margin:0;"
                  data-confirm="Delete this appointment? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $appt['id'] ?>">
                <button type="submit" class="btn btn-danger">Delete appointment</button>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
