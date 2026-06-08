<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

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

// Accept ?date=YYYY-MM-DD from the calendar grid; fall back to today
// if missing or malformed. Strict format check via createFromFormat('!Y-m-d')
// rejects sloppy inputs like '2026-5-1' or '2026-13-40'.
$qsDate      = (string) ($_GET['date'] ?? '');
$defaultDate = ($qsDate !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $qsDate) !== false)
    ? $qsDate
    : (new DateTimeImmutable('today'))->format('Y-m-d');

// Also accept ?time=HH:MM and ?assigned_to=N — these come from
// the day-view's click-an-empty-slot handler so the new
// appointment opens with the right time + fitter already picked.
// Both are sanity-checked, with sensible defaults on bad input.
$qsTime = (string) ($_GET['time'] ?? '');
$defaultTime = preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $qsTime) === 1
    ? $qsTime
    : '09:00';

$qsAssigned = (int) ($_GET['assigned_to'] ?? 0);
$defaultAssigned = $qsAssigned > 0 ? $qsAssigned : (int) $user['user_id'];

// Form defaults — refilled from $_POST after a validation error.
$f = [
    'customer_name'             => '',
    'email'                     => '',
    'phone'                     => '',
    'has_whatsapp'              => 0,
    'installation_address1'     => '',
    'installation_address2'     => '',
    'installation_town'         => '',
    'installation_county'       => '',
    'installation_postcode'     => '',
    'different_billing_address' => 0,
    'billing_address1'          => '',
    'billing_address2'          => '',
    'billing_town'              => '',
    'billing_county'            => '',
    'billing_postcode'          => '',
    'appointment_date'          => $defaultDate,
    'appointment_time'          => $defaultTime,
    'duration_minutes'          => 60,
    'assigned_to'               => $defaultAssigned,
    'notes'                     => '',
];

$error = null;

// Bookable users: anyone active belonging to this client.
$usersStmt = db()->prepare(
    'SELECT id, full_name, role
       FROM client_users
      WHERE client_id = ? AND active = 1
      ORDER BY full_name'
);
$usersStmt->execute([$clientId]);
$bookableUsers = $usersStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Pull each input into $f. Cast checkbox + numeric fields explicitly.
    foreach (array_keys($f) as $k) {
        if ($k === 'different_billing_address' || $k === 'has_whatsapp') {
            $f[$k] = !empty($_POST[$k]) ? 1 : 0;
        } elseif ($k === 'duration_minutes' || $k === 'assigned_to') {
            $f[$k] = (int) ($_POST[$k] ?? 0);
        } else {
            $f[$k] = trim((string) ($_POST[$k] ?? ''));
        }
    }

    // ---- Validation ------------------------------------------------------
    if ($f['customer_name'] === '') {
        $error = 'Customer name is required.';
    } elseif ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($f['appointment_date'] === ''
              || DateTimeImmutable::createFromFormat('!Y-m-d', $f['appointment_date']) === false) {
        $error = 'Please choose a valid appointment date.';
    } elseif ($f['appointment_time'] === ''
              || (DateTimeImmutable::createFromFormat('H:i', $f['appointment_time']) === false
                  && DateTimeImmutable::createFromFormat('G:i', $f['appointment_time']) === false
                  && DateTimeImmutable::createFromFormat('H:i:s', $f['appointment_time']) === false)) {
        $error = 'Please choose a valid appointment time.';
    } elseif ($f['duration_minutes'] < 5 || $f['duration_minutes'] > 1440) {
        $error = 'Duration must be between 5 and 1440 minutes.';
    } else {
        // assigned_to must be a real user belonging to this client (or 0/none).
        $assignedId = null;
        if ($f['assigned_to'] > 0) {
            foreach ($bookableUsers as $u) {
                if ((int) $u['id'] === $f['assigned_to']) {
                    $assignedId = (int) $u['id'];
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

            $billingDifferent = $f['different_billing_address'] === 1;

            $pdo = db();
            try {
                $pdo->beginTransaction();

                // 1) Customer record. Customer's address = installation address.
                $cstmt = $pdo->prepare(
                    'INSERT INTO customers
                       (client_id, name, email, phone, has_whatsapp,
                        address1, address2, town, county, postcode)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $cstmt->execute([
                    $clientId,
                    $f['customer_name'],
                    $f['email'] !== '' ? $f['email'] : null,
                    $f['phone'] !== '' ? $f['phone'] : null,
                    $f['has_whatsapp'],
                    $f['installation_address1'] !== '' ? $f['installation_address1'] : null,
                    $f['installation_address2'] !== '' ? $f['installation_address2'] : null,
                    $f['installation_town']     !== '' ? $f['installation_town']     : null,
                    $f['installation_county']   !== '' ? $f['installation_county']   : null,
                    $f['installation_postcode'] !== '' ? $f['installation_postcode'] : null,
                ]);
                $newCustomerId = (int) $pdo->lastInsertId();

                // 2) Appointment record. Title defaults to customer name so it
                //    reads cleanly in the calendar cards.
                $astmt = $pdo->prepare(
                    'INSERT INTO appointments
                       (client_id, client_user_id, customer_id,
                        title, appointment_date, appointment_time, duration_minutes,
                        installation_address1, installation_address2,
                        installation_town, installation_county, installation_postcode,
                        different_billing_address,
                        billing_address1, billing_address2,
                        billing_town, billing_county, billing_postcode,
                        notes, status)
                     VALUES (?, ?, ?,
                             ?, ?, ?, ?,
                             ?, ?, ?, ?, ?,
                             ?,
                             ?, ?, ?, ?, ?,
                             ?, ?)'
                );
                $astmt->execute([
                    $clientId,
                    $assignedId,
                    $newCustomerId,
                    $f['customer_name'],
                    $f['appointment_date'],
                    $timeStored,
                    $f['duration_minutes'],
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
                    'booked',
                ]);

                $pdo->commit();

                $monthParam = (new DateTimeImmutable($f['appointment_date']))->format('Y-m');
                $_SESSION['flash_success'] = 'Appointment booked for '
                    . $f['customer_name'] . ' on '
                    . (new DateTimeImmutable($f['appointment_date']))->format('j M Y')
                    . '.';
                header('Location: /calendar/index.php?month=' . $monthParam);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Sorry, we could not save the appointment. Please try again.';
            }
        }
    }
}
$activeNav = 'calendar';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book appointment &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        /* Calendar form: extras on top of the shared form classes. */
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
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Book appointment</h1>
                <p class="page-subtitle">
                    <a href="/calendar/index.php">&larr; Back to calendar</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <form method="post" action="/calendar/new.php" class="form" novalidate>
                <?= csrf_field() ?>

                <fieldset class="form-fieldset">
                    <legend>Customer</legend>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="customer_name">Name <span class="required">*</span></label>
                            <input id="customer_name" name="customer_name" type="text"
                                   required maxlength="150" autofocus
                                   value="<?= e((string) $f['customer_name']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" maxlength="150"
                                   autocomplete="email"
                                   value="<?= e((string) $f['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" type="tel" maxlength="50"
                                   autocomplete="tel"
                                   value="<?= e((string) $f['phone']) ?>">
                            <label style="display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-weight:400;font-size:0.875rem;color:var(--text-muted);cursor:pointer">
                                <input type="checkbox" id="has_whatsapp" name="has_whatsapp" value="1"
                                       <?= (int) $f['has_whatsapp'] === 1 ? 'checked' : '' ?>>
                                Customer has WhatsApp on this number
                            </label>
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

                <fieldset class="form-fieldset">
                    <legend>Appointment</legend>

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
                                <?php foreach ($bookableUsers as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                        <?= ((int) $u['id'] === (int) $f['assigned_to']) ? 'selected' : '' ?>>
                                        <?= e((string) $u['full_name']) ?>
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
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Book appointment</button>
                    <a href="/calendar/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
    // Toggle billing block visibility from the checkbox.
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
