<?php
declare(strict_types=1);

/**
 * Super-admin: subscription state for every tenant.
 *
 * One row per tenant. Inline form on each row to change plan, status
 * and (optional) current-period dates. Save → fires
 * billing_sync_feature_flags() which writes through to client_settings
 * so the relevant feature flags pick up the change.
 *
 * Phase 1: state is all manual. Phase 2 wires PayPal webhooks to do
 * this automatically.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';

requireSuperAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $targetClient = (int) ($_POST['client_id'] ?? 0);
    $plan         = (string) ($_POST['plan_code'] ?? 'free');
    $status       = (string) ($_POST['status']    ?? 'active');
    $periodStart  = trim((string) ($_POST['current_period_start'] ?? '')) ?: null;
    $periodEnd    = trim((string) ($_POST['current_period_end']   ?? '')) ?: null;
    $notes        = trim((string) ($_POST['notes'] ?? '')) ?: null;

    // Validate inputs against the registry / enum.
    $plans = billing_plans();
    if (!isset($plans[$plan]))       $plan   = 'free';
    if (!in_array($status, array_keys(billing_status_labels()), true)) {
        $status = 'active';
    }
    foreach (['periodStart', 'periodEnd'] as $var) {
        $v = $$var;
        if ($v !== null && DateTimeImmutable::createFromFormat('!Y-m-d', $v) === false) {
            $$var = null;
        }
    }

    if ($targetClient <= 0) {
        $_SESSION['flash_error'] = 'Bad client id.';
        header('Location: /master-admin/subscriptions.php');
        exit;
    }

    try {
        $pdo = db();
        // Upsert. UNIQUE(client_id) means ON DUPLICATE KEY just
        // updates the existing row.
        $pdo->prepare(
            'INSERT INTO tenant_subscriptions
              (client_id, plan_code, status,
               current_period_start, current_period_end, notes)
              VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              plan_code            = VALUES(plan_code),
              status               = VALUES(status),
              current_period_start = VALUES(current_period_start),
              current_period_end   = VALUES(current_period_end),
              notes                = VALUES(notes)'
        )->execute([$targetClient, $plan, $status, $periodStart, $periodEnd, $notes]);

        // Sync feature flags to match the new subscription state.
        billing_sync_feature_flags($targetClient);

        $_SESSION['flash_success'] = 'Subscription updated.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /master-admin/subscriptions.php');
    exit;
}

// Load all tenants + their subscription row (LEFT JOIN — newly-
// created tenants might not have one yet, the form lets you create).
$rows = db()->query(
    "SELECT c.id, c.company_name, c.active,
            ts.plan_code, ts.status,
            ts.current_period_start, ts.current_period_end,
            ts.cancelled_at, ts.notes,
            ts.external_provider, ts.external_subscription_id
       FROM clients c
       LEFT JOIN tenant_subscriptions ts ON ts.client_id = c.id
   ORDER BY c.company_name"
)->fetchAll();

$plans     = billing_plans();
$statuses  = billing_status_labels();
$activeNav = 'master-admin';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscriptions &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .sub-row {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 0.5rem;
        }
        .sub-row .sub-head {
            display: flex; gap: 0.875rem; align-items: center;
            flex-wrap: wrap; margin-bottom: 0.5rem;
        }
        .sub-row .sub-name {
            font-weight: 600; color: #111827; font-size: 1rem;
            flex: 0 0 auto;
        }
        .sub-row .sub-meta {
            color: #6b7280; font-size: 0.8125rem; flex: 0 0 auto;
        }
        .sub-row form {
            display: grid;
            grid-template-columns: 1fr 1fr 9rem 9rem 2fr auto;
            gap: 0.5rem 0.625rem; align-items: end;
        }
        .sub-row .sub-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .sub-row .sub-field label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: #6b7280; font-weight: 600;
        }
        .sub-row .sub-field input,
        .sub-row .sub-field select {
            padding: 0.375rem 0.5rem; border: 1px solid #d1d5db;
            border-radius: 6px; font: inherit; background: #fff;
        }
        .sub-row .sub-submit { align-self: end; }
        .status-pill {
            display: inline-block; padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px;
        }
        .status-trial     { background: #fef3c7; color: #92400e; }
        .status-active    { background: #d1fae5; color: #065f46; }
        .status-past_due  { background: #fed7aa; color: #9a3412; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-expired   { background: #e5e7eb; color: #374151; }
        @media (max-width: 900px) {
            .sub-row form {
                grid-template-columns: 1fr 1fr;
            }
            .sub-row .sub-submit { grid-column: 1 / -1; justify-self: end; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Subscriptions</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; manual state management (Phase 2 will wire
                    PayPal webhooks to do this automatically).
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Tenants (<?= count($rows) ?>)</h2>
            </div>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.875rem">
                Change a tenant's plan, status, or current-period dates and click
                Save. The relevant <code>feature_*</code> flags on the tenant's
                settings update automatically — no need to also toggle the
                checkboxes on the Master Admin index.
            </p>

            <?php foreach ($rows as $r):
                $cid     = (int) $r['id'];
                $plan    = (string) ($r['plan_code'] ?? 'free');
                $status  = (string) ($r['status']    ?? 'active');
                $started = (string) ($r['current_period_start'] ?? '');
                $ends    = (string) ($r['current_period_end']   ?? '');
                $notes   = (string) ($r['notes'] ?? '');
                $isSelf  = $cid === $clientId;
            ?>
                <div class="sub-row">
                    <div class="sub-head">
                        <span class="sub-name">
                            <?= e((string) $r['company_name']) ?>
                            <?php if ($isSelf): ?>
                                <span style="color:#6b7280;font-size:0.8125rem">(you)</span>
                            <?php endif; ?>
                            <?php if (!(int) $r['active']): ?>
                                <span style="color:#9ca3af;font-size:0.75rem">— inactive</span>
                            <?php endif; ?>
                        </span>
                        <span class="status-pill status-<?= e($status) ?>"><?= e($statuses[$status] ?? $status) ?></span>
                        <span class="sub-meta">
                            Plan: <strong><?= e($plans[$plan]['name'] ?? $plan) ?></strong>
                        </span>
                        <?php if (!empty($r['external_subscription_id'])): ?>
                            <span class="sub-meta">
                                <?= e((string) $r['external_provider']) ?>:
                                <code><?= e((string) $r['external_subscription_id']) ?></code>
                            </span>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="/master-admin/subscriptions.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="client_id" value="<?= $cid ?>">

                        <div class="sub-field">
                            <label for="plan-<?= $cid ?>">Plan</label>
                            <select id="plan-<?= $cid ?>" name="plan_code">
                                <?php foreach ($plans as $code => $p): ?>
                                    <option value="<?= e($code) ?>" <?= $plan === $code ? 'selected' : '' ?>>
                                        <?= e($p['name']) ?>
                                        <?php if ($p['price_gbp_monthly'] > 0): ?>
                                            (£<?= number_format((float) $p['price_gbp_monthly'], 2) ?>/mo)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sub-field">
                            <label for="status-<?= $cid ?>">Status</label>
                            <select id="status-<?= $cid ?>" name="status">
                                <?php foreach ($statuses as $s => $label): ?>
                                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sub-field">
                            <label for="start-<?= $cid ?>">Period start</label>
                            <input id="start-<?= $cid ?>" name="current_period_start" type="date"
                                   value="<?= e($started) ?>">
                        </div>

                        <div class="sub-field">
                            <label for="end-<?= $cid ?>">Period end</label>
                            <input id="end-<?= $cid ?>" name="current_period_end" type="date"
                                   value="<?= e($ends) ?>">
                        </div>

                        <div class="sub-field">
                            <label for="notes-<?= $cid ?>">Notes (admin only)</label>
                            <input id="notes-<?= $cid ?>" name="notes" type="text" maxlength="500"
                                   value="<?= e($notes) ?>"
                                   placeholder="e.g. trial extended, comp'd for Q1 promo…">
                        </div>

                        <div class="sub-submit">
                            <button type="submit" class="btn btn-primary"
                                    style="padding:0.4375rem 0.875rem;font-size:0.875rem">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</div>
</body>
</html>
