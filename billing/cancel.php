<?php
declare(strict_types=1);

/**
 * Cancel one of the current tenant's PayPal subscriptions.
 *
 * POST + CSRF. Requires `plan_code` so we cancel the right add-on
 * (a tenant may have Maps, Postcode and Accounts subs all active).
 * Calls PayPal's cancel endpoint, then updates the local row to
 * 'cancelled' and re-syncs feature flags (which turns OFF any flags
 * not still granted by other subs/comps).
 *
 * Note on timing: PayPal's cancellation is immediate (no future
 * billing), but the tenant has PAID THROUGH current_period_end, so we
 * keep their features until that date — billing_subscription_grants_access()
 * counts a 'cancelled' sub as entitled while its period_end is in the future
 * (the Billing total still uses the strict is_active, so it isn't billed), and
 * billing_reconcile_if_due() flips the flags off once it passes.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];

// plan_code is now mandatory — legacy calls without it default to
// 'accounts' for back-compat with bookmarked Cancel buttons.
$planCode = trim((string) ($_POST['plan_code'] ?? 'accounts'));
$plan     = billing_plan($planCode);
if (!$plan || $planCode === 'free') {
    $_SESSION['flash_error'] = 'Unknown plan: ' . $planCode;
    header('Location: /billing/index.php');
    exit;
}

$sub    = billing_subscription_for_plan($clientId, $planCode);
$subId  = (string) ($sub['external_subscription_id'] ?? '');

// Minimum-term contract guard (for any tier with a term_months commitment).
// Refuse the in-app cancel until the commitment date. No current tier uses
// one, but the mechanism is kept for future term plans.
$commitEnd = billing_commitment_end($clientId, $planCode);
if ($commitEnd !== null) {
    $_SESSION['flash_error'] = ($plan['name'] ?? $planCode)
        . ' is a 12-month contract and can\'t be cancelled until '
        . date('j M Y', strtotime($commitEnd)) . '.';
    header('Location: /billing/index.php');
    exit;
}
$reason = trim((string) ($_POST['reason'] ?? '')) ?: 'Cancelled from YourBlinds billing page';

// If there's a PayPal subscription on record, ask PayPal to cancel it.
// Tolerate "already cancelled" errors so a double-click doesn't 500.
if ($subId !== '' && paypal_is_configured()) {
    try {
        paypal_request(
            'POST',
            '/v1/billing/subscriptions/' . rawurlencode($subId) . '/cancel',
            ['reason' => $reason]
        );
    } catch (Throwable $e) {
        error_log('PayPal cancel call failed (continuing with local cancel): '
            . $e->getMessage());
    }
}

db()->prepare(
    "UPDATE tenant_subscriptions
        SET status = 'cancelled',
            cancelled_at = NOW()
      WHERE client_id = ? AND plan_code = ?"
)->execute([$clientId, $planCode]);

billing_sync_feature_flags_force($clientId);

$periodEnd = (string) ($sub['current_period_end'] ?? '');
$pe        = $periodEnd !== '' ? strtotime($periodEnd) : false;
if ($pe !== false && $pe >= strtotime('today')) {
    $_SESSION['flash_success'] = 'Cancelled ' . ($plan['name'] ?? $planCode)
        . '. You keep its features until ' . date('j M Y', $pe)
        . " (the end of the period you've already paid for) — no further billing.";
} else {
    $_SESSION['flash_success'] = 'Cancelled ' . ($plan['name'] ?? $planCode)
        . '. Paid features for that add-on have been turned off.';
}
header('Location: /billing/index.php');
exit;
