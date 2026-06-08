<?php
declare(strict_types=1);

/**
 * Tenant-side Billing page.
 *
 * Shows one card per available add-on with its own Subscribe / Cancel
 * / Comp'd badge. A tenant can have multiple active add-on subs
 * concurrently (Maps + Postcode + Accounts), independently subscribed
 * + cancelled via PayPal.
 *
 * Admin-only (tenant admins manage billing for their own tenant).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

$user        = current_user();
$clientId    = (int) $user['client_id'];
$plans       = billing_plans();
$paidPlans   = billing_paid_plans();
$statuses    = billing_status_labels();
$paypalReady = paypal_is_configured();

$subsByPlan = billing_subscriptions_for($clientId);

// Per-plan state used by the template:
//
//   $planState[code] = [
//     'sub'       => the subscription row (or null),
//     'isActive'  => bool — sub is active OR has a comp,
//     'hasComp'   => bool — admin granted free access,
//     'subActive' => bool — paid sub is currently in trial/active,
//     'cta'       => 'subscribe' | 'cancel' | 'comp' | 'contact',
//   ]
$planState = [];
foreach ($paidPlans as $code => $_p) {
    $sub       = $subsByPlan[$code] ?? null;
    $subActive = billing_subscription_is_active($sub);
    $trial     = billing_client_trial_for($clientId, $code);
    $hasComp   = billing_client_has_comp($clientId, $code) && !$trial; // comp = non-trial override
    $hasTrial  = $trial !== null;
    $isActive  = $subActive || $hasComp || $hasTrial;

    // CTA:
    //   'comp'      — admin granted permanent free access (no UI buttons)
    //   'trial'     — on a free trial, can still subscribe early to lock it in
    //   'cancel'    — paid sub active → cancel option
    //   'subscribe' — free / cancelled / trial-but-no-sub → can subscribe
    //   'contact'   — paid sub in past_due
    $cta = 'subscribe';
    if ($hasComp) {
        $cta = 'comp';
    } elseif ($subActive) {
        $cta = 'cancel';
    } elseif ($hasTrial) {
        $cta = 'trial';
    } elseif ($sub && (string) $sub['status'] === 'past_due') {
        $cta = 'contact';
    }

    $planState[$code] = [
        'sub'       => $sub,
        'isActive'  => $isActive,
        'hasComp'   => $hasComp,
        'hasTrial'  => $hasTrial,
        'trial'     => $trial,
        'subActive' => $subActive,
        'cta'       => $cta,
    ];
}

// Summary numbers for the header strip.
//   $activeCount  = anything granting features right now (sub/comp/trial)
//   $monthlyTotal = only counts paid (real subscription) — trials and
//                   comps are £0 to the tenant
//   $trialCount   = how many add-ons are on a trial countdown
$activeCount  = 0;
$trialCount   = 0;
$monthlyTotal = 0.0;
$soonestTrialExpiry = null;   // earliest trial end date across all add-ons
foreach ($planState as $code => $st) {
    if (!$st['isActive']) continue;
    $activeCount++;
    if ($st['hasTrial']) {
        $trialCount++;
        $exp = $st['trial']['expires_at'] ?? null;
        if ($exp && (!$soonestTrialExpiry || $exp < $soonestTrialExpiry)) {
            $soonestTrialExpiry = $exp;
        }
    }
    if ($st['subActive'] && !$st['hasComp'] && !$st['hasTrial']) {
        $monthlyTotal += (float) ($paidPlans[$code]['price_gbp_monthly'] ?? 0);
    }
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
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .bill-summary {
            background: #fff; border: 1px solid var(--border); border-radius: 12px;
            padding: 1rem 1.25rem; margin-bottom: 1.25rem;
            display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem;
            align-items: center;
        }
        .bill-summary .bs-count {
            font-size: 1.125rem; font-weight: 700; color: #1f3b5b;
        }
        .bill-summary .bs-total {
            font-size: 1rem; color: var(--text-secondary);
        }
        .bill-summary .bs-total strong { color: #065f46; }
        .bill-summary .bs-trial {
            background: #fef3c7; color: #78350f;
            padding: 0.25rem 0.75rem; border-radius: 999px;
            font-size: 0.875rem; font-weight: 600;
        }
        .bill-summary .bs-trial strong { color: #92400e; }
        .b-status {
            display: inline-block; padding: 0.1875rem 0.625rem;
            font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            border-radius: 999px;
        }
        .b-status.b-trial     { background: #fef3c7; color: #92400e; }
        .b-status.b-active    { background: #d1fae5; color: #065f46; }
        .b-status.b-past_due  { background: #fed7aa; color: #9a3412; }
        .b-status.b-cancelled { background: #fee2e2; color: #991b1b; }
        .b-status.b-expired   { background: var(--border); color: var(--text-secondary); }
        .b-status.b-comp      { background: #ddd6fe; color: #5b21b6; }

        .plan-grid {
            display: grid; gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .plan-card {
            background: #fff; border: 1px solid var(--border); border-radius: 12px;
            padding: 1.125rem 1.25rem;
            display: flex; flex-direction: column; gap: 0.5rem;
        }
        .plan-card.is-active { border-color: #15803d; background: #f0fdf4; }
        .plan-card.is-trial  { border-color: #f59e0b; background: #fffbeb; }
        .plan-card.is-trial.is-active { /* trial wins over generic active styling */
            border-color: #f59e0b; background: #fffbeb;
        }
        .plan-card .p-trial {
            color: #78350f; background: #fef3c7; border-radius: 6px;
            padding: 0.375rem 0.625rem;
        }
        .plan-card .p-trial-urgent {
            color: #7c2d12; background: #fed7aa; border-radius: 6px;
            padding: 0.375rem 0.625rem; font-weight: 600;
        }
        .plan-card .p-head {
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
        }
        .plan-card .p-name {
            font-size: 1.125rem; font-weight: 700; color: #1f3b5b;
            flex: 1 1 auto;
        }
        .plan-card .p-price {
            font-size: 1.375rem; font-weight: 800; color: var(--text-primary);
        }
        .plan-card .p-price small { font-size: 0.8125rem; color: var(--text-faint); font-weight: 500; }
        .plan-card .p-desc {
            color: var(--text-muted); font-size: 0.9375rem; line-height: 1.45;
            flex: 1 1 auto;
        }
        .plan-card .p-meta {
            color: var(--text-faint); font-size: 0.8125rem;
        }
        .plan-card .p-action { margin-top: 0.375rem; }
        .plan-card .p-action .btn-pp {
            background: #ffc439; color: #111; border: 0; font-weight: 700;
            padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;
            font-size: 0.9375rem;
        }
        .plan-card .p-action .btn-cancel {
            background: #fff; color: #b91c1c; border: 1px solid #fecaca;
            padding: 0.4375rem 0.75rem; border-radius: 6px; cursor: pointer;
            font-size: 0.8125rem;
        }
        .plan-card .p-action .btn-cancel:hover { background: #fef2f2; }
        .plan-card .p-comp {
            display: inline-block; padding: 0.1875rem 0.625rem;
            background: #ddd6fe; color: #5b21b6; border-radius: 999px;
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
                <p class="page-subtitle">Add-ons for your account — subscribe or cancel any time.</p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="bill-summary">
                <span class="bs-count">
                    <?php if ($activeCount === 0): ?>
                        Free plan only
                    <?php elseif ($activeCount === 1): ?>
                        1 add-on active
                    <?php else: ?>
                        <?= $activeCount ?> add-ons active
                    <?php endif; ?>
                </span>
                <?php if ($monthlyTotal > 0): ?>
                    <span class="bs-total">
                        Monthly total: <strong>£<?= number_format($monthlyTotal, 2) ?></strong>
                    </span>
                <?php endif; ?>
                <?php if ($trialCount > 0 && $soonestTrialExpiry):
                    $daysToSoonest = (int) ceil(
                        (strtotime($soonestTrialExpiry) - strtotime('today')) / 86400
                    );
                ?>
                    <span class="bs-trial">
                        🎁 <strong><?= $trialCount ?></strong>
                        <?= $trialCount === 1 ? 'add-on on a free trial' : 'add-ons on free trials' ?>
                        — <?= $daysToSoonest > 0 ? $daysToSoonest . ' day' . ($daysToSoonest === 1 ? '' : 's') . ' until next expires' : 'expiring today' ?>
                    </span>
                <?php endif; ?>
                <span style="color:var(--text-faint);font-size:0.875rem;flex:1 1 100%">
                    Billed monthly in GBP through PayPal. Cancellations turn off
                    paid features immediately — no future charges.
                </span>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Available add-ons</h2>
            </div>

            <div class="plan-grid">
                <?php foreach ($paidPlans as $code => $p):
                    $st        = $planState[$code];
                    $sub       = $st['sub'];
                    $isActive  = $st['isActive'];
                    $hasComp   = $st['hasComp'];
                    $hasTrial  = $st['hasTrial'];
                    $trial     = $st['trial'];
                    $subActive = $st['subActive'];
                    $cta       = $st['cta'];

                    // For trial countdown urgency styling.
                    $trialDaysLeft = $trial['days_left'] ?? null;
                    $trialUrgent   = $trialDaysLeft !== null && $trialDaysLeft <= 7;
                ?>
                    <div class="plan-card <?= $isActive ? 'is-active' : '' ?> <?= $hasTrial ? 'is-trial' : '' ?>">
                        <div class="p-head">
                            <span class="p-name"><?= e($p['name']) ?></span>
                            <?php if ($hasTrial): ?>
                                <span class="b-status b-trial">
                                    🎁 Free trial
                                </span>
                            <?php elseif ($hasComp): ?>
                                <span class="b-status b-comp">🎁 Comp'd</span>
                            <?php elseif ($subActive): ?>
                                <span class="b-status b-active">Active</span>
                            <?php elseif ($sub && (string) $sub['status'] === 'past_due'): ?>
                                <span class="b-status b-past_due">Past due</span>
                            <?php elseif ($sub && (string) $sub['status'] === 'cancelled'): ?>
                                <span class="b-status b-cancelled">Cancelled</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-price">
                            <?php if ($hasComp): ?>
                                <span style="color:#5b21b6">Free</span>
                                <small>(comp'd by your account manager)</small>
                            <?php elseif ($hasTrial): ?>
                                <span style="color:#92400e">Free</span>
                                <small>(then £<?= number_format((float) $p['price_gbp_monthly'], 2) ?>/mo)</small>
                            <?php else: ?>
                                £<?= number_format((float) $p['price_gbp_monthly'], 2) ?>
                                <small>/month</small>
                            <?php endif; ?>
                        </div>
                        <div class="p-desc"><?= e($p['description']) ?></div>

                        <?php if ($hasTrial && $trial['expires_at']): ?>
                            <div class="p-meta <?= $trialUrgent ? 'p-trial-urgent' : 'p-trial' ?>">
                                <?php if ($trialDaysLeft !== null && $trialDaysLeft <= 0): ?>
                                    Trial expires <strong>today</strong> — subscribe now to keep access.
                                <?php elseif ($trialDaysLeft !== null && $trialDaysLeft === 1): ?>
                                    Trial ends <strong>tomorrow</strong> — subscribe now to keep access.
                                <?php else: ?>
                                    Trial ends <strong><?= e(date('j M Y', strtotime((string) $trial['expires_at']))) ?></strong>
                                    (<?= (int) $trialDaysLeft ?> days left).
                                <?php endif; ?>
                            </div>
                        <?php elseif ($subActive && !empty($sub['current_period_end'])): ?>
                            <div class="p-meta">
                                Renews <strong><?= e(date('j M Y', strtotime((string) $sub['current_period_end']))) ?></strong>
                                — billed automatically.
                            </div>
                        <?php elseif ($sub && (string) $sub['status'] === 'cancelled' && !empty($sub['cancelled_at'])): ?>
                            <div class="p-meta">
                                Previously cancelled <?= e(date('j M Y', strtotime((string) $sub['cancelled_at']))) ?>.
                                Re-subscribe any time.
                            </div>
                        <?php endif; ?>

                        <div class="p-action">
                            <?php if ($cta === 'comp'): ?>
                                <span class="p-comp">✓ Active — no charge</span>

                            <?php elseif ($cta === 'cancel'): ?>
                                <form method="post" action="/billing/cancel.php"
                                      style="margin:0"
                                      data-confirm="Cancel <?= e($p['name']) ?>? Paid features for this add-on will turn off straight away.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="plan_code" value="<?= e($code) ?>">
                                    <button type="submit" class="btn-cancel">
                                        Cancel subscription
                                    </button>
                                </form>

                            <?php elseif ($cta === 'trial'): ?>
                                <?php if (!$paypalReady || ($p['paypal_plan_id'] ?? '') === ''): ?>
                                    <span style="color:var(--text-faint);font-size:0.875rem">
                                        Subscribe option coming soon — your trial is live, enjoy!
                                    </span>
                                <?php else: ?>
                                    <form method="post" action="/billing/subscribe.php" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="plan_code" value="<?= e($code) ?>">
                                        <button type="submit" class="btn-pp">
                                            <?= $trialUrgent ? 'Subscribe to keep this' : 'Subscribe via PayPal' ?>
                                        </button>
                                    </form>
                                    <div style="margin-top:0.375rem;color:var(--text-faint);font-size:0.75rem">
                                        Trial keeps running until you subscribe — no overlap, no double-charging.
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($cta === 'contact'): ?>
                                <span style="color:#9a3412;font-size:0.875rem">
                                    Payment is being retried by PayPal — check your PayPal account,
                                    or contact your account manager if this drags on.
                                </span>

                            <?php elseif (!$paypalReady): ?>
                                <span style="color:var(--text-faint);font-size:0.875rem">
                                    Online subscription not yet configured — contact your account manager.
                                </span>

                            <?php elseif (($p['paypal_plan_id'] ?? '') === ''): ?>
                                <span style="color:var(--text-faint);font-size:0.875rem">
                                    Not yet available — contact your account manager.
                                </span>

                            <?php else: ?>
                                <form method="post" action="/billing/subscribe.php" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="plan_code" value="<?= e($code) ?>">
                                    <button type="submit" class="btn-pp">
                                        Subscribe via PayPal
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
