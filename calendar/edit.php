<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/appointment_conflict.php';
require __DIR__ . '/../_partials/bookable_users.php';

requireLogin();

$user     = current_user();
$clientId = $user['client_id'];
$isAdmin  = $user['role'] === 'admin';

// Postcode lookup add-on — gates the lookup widget rendered inside the
// Installation address fieldset.
$pcStmt = db()->prepare(
    'SELECT COALESCE(feature_postcode_lookup, 0) FROM client_settings WHERE client_id = ?'
);
$pcStmt->execute([$clientId]);
$postcodeLookupEnabled = ((int) $pcStmt->fetchColumn()) === 1;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /calendar/index.php');
    exit;
}

// ---------------------------------------------------------------------------
// Load appointment, joined to customer for the read-only customer panel.
// Tenant-scoped via a.client_id = ?
// ---------------------------------------------------------------------------
$loadStmt = db()->prepare(
    'SELECT a.*,
            c.id    AS cust_id,
            c.name  AS cust_name,
            c.email AS cust_email,
            c.phone AS cust_phone
       FROM appointments a
  LEFT JOIN customers    c ON c.id = a.customer_id
      WHERE a.id = ? AND a.client_id = ?'
);
$loadStmt->execute([$id, $clientId]);
$appt = $loadStmt->fetch();

if (!$appt) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Appointment not found</h1>'
       . '<p><a href="/calendar/index.php">Back to calendar</a></p>';
    exit;
}

// Form defaults — refilled from $_POST after a validation error, otherwise
// pre-populated from the existing row.
$f = [
    'title'                     => (string) $appt['title'],
    'appointment_date'          => (string) $appt['appointment_date'],
    'appointment_time'          => substr((string) $appt['appointment_time'], 0, 5),
    'duration_minutes'          => (int) $appt['duration_minutes'],
    'assigned_to'               => (int) ($appt['client_user_id'] ?? 0),
    'quote_id'                  => (int) ($appt['quote_id'] ?? 0),
    'installation_address1'     => (string) ($appt['installation_address1'] ?? ''),
    'installation_address2'     => (string) ($appt['installation_address2'] ?? ''),
    'installation_town'         => (string) ($appt['installation_town'] ?? ''),
    'installation_county'       => (string) ($appt['installation_county'] ?? ''),
    'installation_postcode'     => (string) ($appt['installation_postcode'] ?? ''),
    'different_billing_address' => (int) ($appt['different_billing_address'] ?? 0),
    'billing_address1'          => (string) ($appt['billing_address1'] ?? ''),
    'billing_address2'          => (string) ($appt['billing_address2'] ?? ''),
    'billing_town'              => (string) ($appt['billing_town'] ?? ''),
    'billing_county'            => (string) ($appt['billing_county'] ?? ''),
    'billing_postcode'          => (string) ($appt['billing_postcode'] ?? ''),
    'notes'                     => (string) ($appt['notes'] ?? ''),
    'has_issue'                 => !empty($appt['has_issue']) ? 1 : 0,
    'issue_note'                => (string) ($appt['issue_note'] ?? ''),
];

$error        = null;
$conflictWarn = null;   // soft double-booking warning (overridable)

// Bookable users — scoped to the appointment's kind: a fitting offers
// 'fitter', a measure offers 'sales'. The currently-assigned user is always
// kept in the list so an existing assignment is never silently dropped.
$bookableUsers = bookable_users_for_kind(
    (int) $clientId,
    (string) ($appt['appt_kind'] ?? 'measure'),
    (int) ($appt['client_user_id'] ?? 0)
);

// If the appointment is still unassigned and there's exactly one eligible
// person for its kind (e.g. the only fitter / only salesperson), pre-select
// them so it doesn't sit unassigned by accident — mirrors the new-booking
// form. First load only; a POST carries whatever the user actually chose
// (including a deliberate "Unassigned").
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    && (int) ($appt['client_user_id'] ?? 0) === 0
    && count($bookableUsers) === 1) {
    $f['assigned_to'] = (int) $bookableUsers[0]['id'];
}

// Quotes available for linking. Most-recent first, capped at 200 —
// covers small/medium tenants; bigger ones get the search box below
// the select. Defensive against the quotes table being absent.
$linkableQuotes = [];
$hasQuotes = false;
try {
    $hasQuotes = (bool) db()->query("SHOW TABLES LIKE 'quotes'")->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

if ($hasQuotes) {
    try {
        $qSt = db()->prepare(
            'SELECT q.id, q.quote_number, q.end_customer_name, q.status,
                    q.created_at
               FROM quotes q
              WHERE q.client_id = ?
           ORDER BY q.created_at DESC
              LIMIT 200'
        );
        $qSt->execute([$clientId]);
        $linkableQuotes = $qSt->fetchAll();
    } catch (Throwable $e) {
        $linkableQuotes = [];
    }
}

// Currently-linked quote (if any) — used to render a small summary
// pill above the picker so the user can see at a glance "this is
// already linked to BEV-2026-0002 (John Smith)".
$linkedQuote = null;
if ((int) ($appt['quote_id'] ?? 0) > 0 && $hasQuotes) {
    try {
        $lqSt = db()->prepare(
            'SELECT id, quote_number, end_customer_name, status
               FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $lqSt->execute([(int) $appt['quote_id'], $clientId]);
        $linkedQuote = $lqSt->fetch() ?: null;
    } catch (Throwable $e) { /* ignore */ }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($f) as $k) {
        if ($k === 'different_billing_address' || $k === 'has_issue') {
            $f[$k] = !empty($_POST[$k]) ? 1 : 0;
        } elseif ($k === 'duration_minutes' || $k === 'assigned_to' || $k === 'quote_id') {
            $f[$k] = (int) ($_POST[$k] ?? 0);
        } else {
            $f[$k] = trim((string) ($_POST[$k] ?? ''));
        }
    }

    // Validate quote_id — must be 0 (= "no link") OR an actual
    // quote on THIS tenant. Stops a user from POSTing a foreign
    // quote_id and stitching their appointment to someone else's
    // quote. Failure resets to 0 (= "no link") rather than
    // throwing; that's the safer default.
    if ($f['quote_id'] > 0 && $hasQuotes) {
        $vq = db()->prepare(
            'SELECT 1 FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $vq->execute([$f['quote_id'], $clientId]);
        if (!$vq->fetchColumn()) {
            $f['quote_id'] = 0;
        }
    }

    // ---- Validation ------------------------------------------------------
    if ($f['title'] === '') {
        $error = 'Appointment title is required.';
    } elseif ($f['appointment_date'] === ''
              || DateTimeImmutable::createFromFormat('!Y-m-d', $f['appointment_date']) === false) {
        $error = 'Please choose a valid appointment date.';
    } elseif ($f['appointment_time'] === ''
              || (DateTimeImmutable::createFromFormat('H:i', $f['appointment_time']) === false
                  && DateTimeImmutable::createFromFormat('G:i', $f['appointment_time']) === false
                  && DateTimeImmutable::createFromFormat('H:i:s', $f['appointment_time']) === false)) {
        $error = 'Please choose a valid appointment time (HH:MM).';
    } elseif ($f['duration_minutes'] < 5 || $f['duration_minutes'] > 1440) {
        $error = 'Duration must be between 5 and 1440 minutes.';
    } else {
        // assigned_to must be a real user belonging to this client (or 0/none).
        $assignedId   = null;
        $assigneeName = '';
        if ($f['assigned_to'] > 0) {
            foreach ($bookableUsers as $u) {
                if ((int) $u['id'] === $f['assigned_to']) {
                    $assignedId   = (int) $u['id'];
                    $assigneeName = (string) $u['full_name'];
                    break;
                }
            }
            if ($assignedId === null) {
                $error = 'Selected assignee is not valid.';
            }
        }

        if ($error === null) {
            // Normalise time to HH:MM:SS for storage.
            $timeObj = DateTimeImmutable::createFromFormat('H:i',   $f['appointment_time'])
                    ?: DateTimeImmutable::createFromFormat('G:i',   $f['appointment_time'])
                    ?: DateTimeImmutable::createFromFormat('H:i:s', $f['appointment_time']);
            $timeStored = $timeObj->format('H:i:s');

            // Double-booking guard — exclude this appointment from its own check.
            $clash = appointment_find_conflict(
                db(), (int) $clientId, $assignedId,
                $f['appointment_date'], $timeStored, (int) $f['duration_minutes'], $id
            );
        }

        // Soft double-booking warning — overridable via "Save anyway".
        if ($error === null && isset($clash) && $clash !== null && empty($_POST['override_conflict'])) {
            $conflictWarn = appointment_conflict_message($clash, $assigneeName);
        }

        if ($error === null && $conflictWarn === null) {
            $billingDifferent = $f['different_billing_address'] === 1;

            $u = db()->prepare(
                'UPDATE appointments SET
                    client_user_id            = ?,
                    title                     = ?,
                    appointment_date          = ?,
                    appointment_time          = ?,
                    duration_minutes          = ?,
                    quote_id                  = ?,
                    installation_address1     = ?,
                    installation_address2     = ?,
                    installation_town         = ?,
                    installation_county       = ?,
                    installation_postcode     = ?,
                    different_billing_address = ?,
                    billing_address1          = ?,
                    billing_address2          = ?,
                    billing_town              = ?,
                    billing_county            = ?,
                    billing_postcode          = ?,
                    notes                     = ?
                  WHERE id = ? AND client_id = ?'
            );
            $u->execute([
                $assignedId,
                $f['title'],
                $f['appointment_date'],
                $timeStored,
                $f['duration_minutes'],
                $f['quote_id'] > 0 ? $f['quote_id'] : null,
                $f['installation_address1'] !== '' ? $f['installation_address1'] : null,
                $f['installation_address2'] !== '' ? $f['installation_address2'] : null,
                $f['installation_town']     !== '' ? $f['installation_town']     : null,
                $f['installation_county']   !== '' ? $f['installation_county']   : null,
                $f['installation_postcode'] !== '' ? $f['installation_postcode'] : null,
                $billingDifferent ? 1 : 0,
                $billingDifferent && $f['billing_address1'] !== '' ? $f['billing_address1'] : null,
                $billingDifferent && $f['billing_address2'] !== '' ? $f['billing_address2'] : null,
                $billingDifferent && $f['billing_town']     !== '' ? $f['billing_town']     : null,
                $billingDifferent && $f['billing_county']   !== '' ? $f['billing_county']   : null,
                $billingDifferent && $f['billing_postcode'] !== '' ? $f['billing_postcode'] : null,
                $f['notes'] !== '' ? $f['notes'] : null,
                $id,
                $clientId,
            ]);

            // Issue flag + note on a small guarded UPDATE so editing still
            // works on a schema where migrate_appointment_issue.php hasn't run.
            try {
                db()->prepare(
                    'UPDATE appointments SET has_issue = ?, issue_note = ? WHERE id = ? AND client_id = ?'
                )->execute([
                    (int) $f['has_issue'],
                    ($f['has_issue'] && trim((string) $f['issue_note']) !== '') ? trim((string) $f['issue_note']) : null,
                    $id, $clientId,
                ]);
            } catch (Throwable $e) { /* columns not present yet — skip */ }

            $_SESSION['flash_success'] = 'Appointment updated.';
            header('Location: /calendar/view.php?id=' . $id);
            exit;
        }
    }
}

$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit appointment &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        /* Mirror of calendar/new.php form styles. */
        .form { max-width: 820px; }
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="number"] {
            width: 100%;
            font: inherit;
            padding: 0.5625rem 0.75rem;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: #fff;
            color: inherit;
        }
        .form-group input[type="date"]:focus,
        .form-group input[type="time"]:focus,
        .form-group input[type="number"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .form-row.cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        @media (max-width: 700px) {
            .form-row,
            .form-row.cols-3,
            .form-row.cols-4 { grid-template-columns: 1fr; }
        }
        .form-fieldset {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.125rem 0.25rem;
            margin-bottom: 1rem;
        }
        .form-fieldset legend {
            padding: 0 0.5rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1f3b5b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .checkbox-row {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9375rem;
            color: var(--text-primary);
            cursor: pointer;
        }
        .checkbox-row input { width: 18px; height: 18px; }
        #billing-block { display: <?= $f['different_billing_address'] === 1 ? 'block' : 'none' ?>; }
        .customer-readonly {
            background: var(--bg-subtle);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
        }
        .customer-readonly a { color: #2563eb; text-decoration: none; }
        .customer-readonly a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Edit appointment</h1>
                <p class="page-subtitle">
                    <a href="/calendar/view.php?id=<?= (int) $id ?>">&larr; Back to appointment</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/calendar/edit.php?id=<?= (int) $id ?>" class="form" novalidate>
                <?= csrf_field() ?>

                <?php if ($conflictWarn !== null): ?>
                    <div role="alert" style="background:#fffbeb;border:1px solid #fde68a;
                         color:#92400e;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1rem;
                         font-size:0.9375rem">
                        &#9888;&#65039; <?= e($conflictWarn) ?>
                        <div style="margin-top:0.625rem">
                            <button type="submit" name="override_conflict" value="1"
                                    class="btn btn-primary"
                                    style="background:#d97706;border-color:#d97706">
                                Save anyway
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <fieldset class="form-fieldset">
                    <legend>Customer</legend>
                    <div class="customer-readonly">
                        <?php if (!empty($appt['cust_name'])): ?>
                            <strong><?= e((string) $appt['cust_name']) ?></strong>
                            <?php if (!empty($appt['cust_phone'])): ?>
                                &middot; <a href="tel:<?= e((string) $appt['cust_phone']) ?>"><?= e((string) $appt['cust_phone']) ?></a>
                            <?php endif; ?>
                            <?php if (!empty($appt['cust_email'])): ?>
                                &middot; <a href="mailto:<?= e((string) $appt['cust_email']) ?>"><?= e((string) $appt['cust_email']) ?></a>
                            <?php endif; ?>
                            <div style="margin-top:0.375rem;font-size:0.875rem;color:var(--text-faint)">
                                Customer details aren't editable from here.
                                <a href="/customer-manager/edit.php?id=<?= (int) $appt['cust_id'] ?>">Edit customer &rarr;</a>
                            </div>
                        <?php else: ?>
                            <span style="color:var(--text-faint)">Customer record no longer exists.</span>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="form-fieldset">
                    <legend>Appointment</legend>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="title">Title <span class="required">*</span></label>
                            <input id="title" name="title" type="text"
                                   required maxlength="200"
                                   value="<?= e((string) $f['title']) ?>">
                        </div>
                    </div>

                    <!--
                        Quote linker — connect this appointment to an
                        existing quote so the day/week views pick up the
                        Q-number chip and the status-progress bars on the
                        card. Appointments auto-created from quote
                        acceptance already have this set; manually-created
                        appointments need to be linked here.
                    -->
                    <?php if ($hasQuotes): ?>
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="quote_id">Linked quote</label>
                                <?php if ($linkedQuote): ?>
                                    <div style="background:#ecfdf5;border:1px solid #a7f3d0;
                                                color:#065f46;padding:0.4375rem 0.625rem;
                                                border-radius:6px;margin-bottom:0.375rem;
                                                font-size:0.875rem;display:flex;
                                                align-items:center;gap:0.625rem;flex-wrap:wrap">
                                        <span>
                                            <strong><?= e((string) $linkedQuote['quote_number']) ?></strong>
                                            &middot; <?= e((string) ($linkedQuote['end_customer_name'] ?? 'no name')) ?>
                                            &middot; <em><?= e((string) ($linkedQuote['status'] ?? 'unknown')) ?></em>
                                        </span>
                                        <a href="/quote-builder/edit.php?id=<?= (int) $linkedQuote['id'] ?>"
                                           style="color:#065f46;font-weight:600">Open quote &rarr;</a>
                                    </div>
                                <?php endif; ?>
                                <select id="quote_id" name="quote_id">
                                    <option value="0">
                                        <?= $linkedQuote ? '— Unlink (no quote) —' : '— No quote linked —' ?>
                                    </option>
                                    <?php foreach ($linkableQuotes as $q):
                                        $sel = (int) $q['id'] === (int) $f['quote_id'];
                                        $lbl = (string) $q['quote_number']
                                             . ' — ' . (string) ($q['end_customer_name'] ?? 'no name')
                                             . ' (' . (string) ($q['status'] ?? '?') . ')';
                                    ?>
                                        <option value="<?= (int) $q['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                            <?= e($lbl) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color:var(--text-faint);font-size:0.75rem;line-height:1.45;display:block;margin-top:0.25rem">
                                    Shows the 200 most recent quotes for this tenant.
                                    Linking surfaces the Q-number + status bars on calendar
                                    cards. Set to <em>"— No quote —"</em> to unlink.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-row cols-4">
                        <div class="form-group">
                            <label for="appointment_date">Date <span class="required">*</span></label>
                            <input id="appointment_date" name="appointment_date"
                                   type="date" required
                                   value="<?= e((string) $f['appointment_date']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="appointment_time">Time <span class="required">*</span></label>
                            <?php
                                $value = (string) $f['appointment_time'];
                                require __DIR__ . '/../_partials/time_picker.php';
                            ?>
                        </div>
                        <div class="form-group">
                            <label for="duration_minutes">Duration (mins)</label>
                            <input id="duration_minutes" name="duration_minutes"
                                   type="number" min="5" max="1440" step="5"
                                   value="<?= (int) $f['duration_minutes'] ?>">
                        </div>
                        <div class="form-group">
                            <label for="assigned_to">Assigned to</label>
                            <select id="assigned_to" name="assigned_to">
                                <option value="0">— Unassigned —</option>
                                <?php foreach ($bookableUsers as $bu): ?>
                                    <option value="<?= (int) $bu['id'] ?>"
                                        <?= ((int) $bu['id'] === (int) $f['assigned_to']) ? 'selected' : '' ?>>
                                        <?= e((string) $bu['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="4"><?= e((string) $f['notes']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label style="display:inline-flex;align-items:center;gap:.4rem;font-weight:600;color:#be123c;">
                                <input type="checkbox" name="has_issue" value="1" <?= !empty($f['has_issue']) ? 'checked' : '' ?>>
                                &#9888;&#65039; Flag this job as having an issue
                            </label>
                            <textarea id="issue_note" name="issue_note" rows="2" maxlength="280"
                                      placeholder="What's the problem? (optional) — e.g. wrong colour, no access, remake needed"
                                      style="margin-top:0.5rem"><?= e((string) $f['issue_note']) ?></textarea>
                            <small style="color:var(--text-faint);font-size:0.8125rem">
                                Flagged jobs show a red ⚠ ring on the calendar and appear in the Issues filter.
                            </small>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-fieldset">
                    <legend>Installation address</legend>

                    <?php if ($postcodeLookupEnabled): ?>
                        <?php require __DIR__ . '/../_partials/postcode_lookup.php'; ?>
                    <?php endif; ?>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="installation_address1">Address line 1</label>
                            <input id="installation_address1" name="installation_address1"
                                   type="text" maxlength="150"
                                   value="<?= e((string) $f['installation_address1']) ?>">
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="installation_address2">Address line 2</label>
                            <input id="installation_address2" name="installation_address2"
                                   type="text" maxlength="150"
                                   value="<?= e((string) $f['installation_address2']) ?>">
                        </div>
                    </div>

                    <div class="form-row cols-3">
                        <div class="form-group">
                            <label for="installation_town">Town</label>
                            <input id="installation_town" name="installation_town"
                                   type="text" maxlength="100"
                                   value="<?= e((string) $f['installation_town']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="installation_county">County</label>
                            <input id="installation_county" name="installation_county"
                                   type="text" maxlength="100"
                                   value="<?= e((string) $f['installation_county']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="installation_postcode">Postcode</label>
                            <input id="installation_postcode" name="installation_postcode"
                                   type="text" maxlength="20" autocomplete="postal-code"
                                   value="<?= e((string) $f['installation_postcode']) ?>">
                        </div>
                    </div>

                    <label class="checkbox-row" for="different_billing_address">
                        <input type="checkbox" id="different_billing_address"
                               name="different_billing_address" value="1"
                               <?= $f['different_billing_address'] === 1 ? 'checked' : '' ?>>
                        Different billing address?
                    </label>

                    <div id="billing-block">
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="billing_address1">Billing address line 1</label>
                                <input id="billing_address1" name="billing_address1"
                                       type="text" maxlength="150"
                                       value="<?= e((string) $f['billing_address1']) ?>">
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label for="billing_address2">Billing address line 2</label>
                                <input id="billing_address2" name="billing_address2"
                                       type="text" maxlength="150"
                                       value="<?= e((string) $f['billing_address2']) ?>">
                            </div>
                        </div>
                        <div class="form-row cols-3">
                            <div class="form-group">
                                <label for="billing_town">Town</label>
                                <input id="billing_town" name="billing_town"
                                       type="text" maxlength="100"
                                       value="<?= e((string) $f['billing_town']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="billing_county">County</label>
                                <input id="billing_county" name="billing_county"
                                       type="text" maxlength="100"
                                       value="<?= e((string) $f['billing_county']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="billing_postcode">Postcode</label>
                                <input id="billing_postcode" name="billing_postcode"
                                       type="text" maxlength="20"
                                       value="<?= e((string) $f['billing_postcode']) ?>">
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/calendar/view.php?id=<?= (int) $id ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
    (function () {
        var cb    = document.getElementById('different_billing_address');
        var block = document.getElementById('billing-block');
        if (!cb || !block) return;
        var sync = function () {
            block.style.display = cb.checked ? 'block' : 'none';
        };
        cb.addEventListener('change', sync);
        sync();
    })();
</script>
</body>
</html>
