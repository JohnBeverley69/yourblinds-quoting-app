<?php
declare(strict_types=1);

/**
 * Tenant-side Billing page. Shows the current plan, status, period
 * end date, and a list of all plans.
 *
 * Phase 1: read-only — tenants can't change their own plan from here.
 * The "Upgrade" button is a contact link for now; Phase 2 swaps it
 * for a PayPal subscription redirect.
 *
 * Admin-only (tenant admins manage billing for their own tenant).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$sub    = billing_subscription_for($clientId);
$plan   = $sub ? billing_plan((string) $sub['plan_code']) : null;
$active = billing_subscription_is_active($sub);
$plans  = billing_plans();
$statuses = billing_status_labels();
$paypalReady = paypal_is_configured();

// What the primary CTA on the Billing page should do.
//   'subscribe' — tenant is on the free plan, can upgrade
//   'cancel'    — tenant has an active paid plan, can cancel
//   'contact'   — paid plan but in an odd state (past_due/expired),
//                 contact support so we don't double-charge
$ctaMode = 'subscribe';
if ($sub && ((string) $sub['plan_code']) === 'accounts') {
    $s = (string) $sub['status'];
    $ctaMode = ($s === 'active' || $s === 'trial') ? 'cancel' : 'contact';
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'billing';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .bill-current {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 1.125rem 1.25rem; margin-bottom: 1.25rem;
            display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem;
            align-items: center;
        }
        .bill-current .b-plan {
            font-size: 1.25rem; font-weight: 700; color: #1f3b5b;
        }
        .bill-current .b-status {
            display: inline-block; padding: 0.1875rem 0.625rem;
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px;
        }
        .b-status.b-trial     { background: #fef3c7; color: #92400e; }
        .b-status.b-active    { background: #d1fae5; color: #065f46; }
        .b-status.b-past_due  { background: #fed7aa; color: #9a3412; }
        .b-status.b-cancelled { background: #fee2e2; color: #991b1b; }
        .b-status.b-expired   { background: #e5e7eb; color: #374151; }
        .bill-current .b-meta {
            color: #6b7280; font-size: 0.875rem;
        }
        .plan-grid {
            display: grid; gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }
        .plan-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 1.125rem 1.25rem;
            display: flex; flex-direction: column; gap: 0.5rem;
        }
        .plan-card.is-current { border-color: #15803d; background: #f0fdf4; }
        .plan-card .p-name {
            font-size: 1.125rem; font-weight: 700; color: #1f3b5b;
        }
        .plan-card .p-price {
            font-size: 1.5rem; font-weight: 800; color: #111827;
        }
        .plan-card .p-price small { font-size: 0.8125rem; color: #6b7280; font-weight: 500; }
        .plan-card .p-desc {
            color: #4b5563; font-size: 0.9375rem; line-height: 1.45;
            flex: 1 1 auto;
        }
        .plan-card .p-action { margin-top: 0.5rem; }
        .plan-card .p-current-tag {
            display: inline-block; padding: 0.1875rem 0.625rem;
            background: #15803d; color: #fff; border-radius: 999px;
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Billing</h1>
                <p class="page-subtitle">Your plan, status, and what each tier unlocks.</p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="bill-current">
                <span class="b-plan"><?= e($plan['name'] ?? 'Free') ?></span>
                <?php $sStatus = (string) ($sub['status'] ?? 'active'); ?>
                <span class="b-status b-<?= e($sStatus) ?>"><?= e($statuses[$sStatus] ?? $sStatus) ?></span>
                <span class="b-meta">
                    <?php if (!$active && $sStatus !== 'active'): ?>
                        Some paid features may be unavailable while in this state.
                    <?php elseif (!empty($sub['current_period_end'])): ?>
                        Current period ends
                        <strong><?= e(date('j M Y', strtotime((string) $sub['current_period_end']))) ?></strong>
                    <?php elseif (($plan['price_gbp_monthly'] ?? 0) > 0): ?>
                        Active subscription.
                    <?php else: ?>
                        No paid features active.
                    <?php endif; ?>
                </span>
                <?php if (!empty($sub['notes'])): ?>
                    <span class="b-meta" style="flex-basis:100%">
                        <em><?= e((string) $sub['notes']) ?></em>
                    </span>
                <?php endif; ?>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Plans</h2>
            </div>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem">
                Subscribe or cancel via PayPal below. You can cancel at any time —
                your features stay active until cancellation completes on PayPal's
                end (usually instant). Billing happens monthly in GBP through PayPal.
            </p>

            <div class="plan-grid">
                <?php foreach ($plans as $code => $p):
                    $isCurrent = $sub && ((string) $sub['plan_code']) === $code;
                ?>
                    <div class="plan-card <?= $isCurrent ? 'is-current' : '' ?>">
                        <div class="p-name"><?= e($p['name']) ?></div>
                        <div class="p-price">
                            <?php if ($p['price_gbp_monthly'] > 0): ?>
                                £<?= number_format((float) $p['price_gbp_monthly'], 2) ?>
                                <small>/month</small>
                            <?php else: ?>
                                Free
                            <?php endif; ?>
                        </div>
                        <div class="p-desc"><?= e($p['description']) ?></div>
                        <div class="p-action">
                            <?php if ($isCurrent): ?>
                                <span class="p-current-tag">✓ Current plan</span>
                                <?php if ($code === 'accounts' && $ctaMode === 'cancel'): ?>
                                    <form method="post" action="/billing/cancel.php"
                                          style="display:inline;margin-left:0.5rem"
                                          data-confirm="Cancel your subscription? Paid features will turn off straight away.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-secondary"
                                                style="padding:0.3125rem 0.75rem;font-size:0.8125rem">
                                            Cancel subscription
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($code === 'accounts'): ?>
                                <?php if (!$paypalReady): ?>
                                    <span style="color:#9ca3af;font-size:0.875rem">
                                        Online subscription not yet configured —
                                        contact your account manager.
                                    </span>
                                <?php else: ?>
                                    <form method="post" action="/billing/subscribe.php"
                                          style="margin:0">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary"
                                                style="background:#ffc439;color:#111;border:0;
                                                       font-weight:700;padding:0.5rem 1rem">
                                            Subscribe via PayPal
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($ctaMode === 'cancel'): ?>
                                    <span style="color:#9ca3af;font-size:0.875rem">
                                        Cancel current plan to switch back to Free.
                                    </span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:0.875rem">—</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
