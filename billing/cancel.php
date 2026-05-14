<?php
declare(strict_types=1);

/**
 * Cancel the current tenant's PayPal subscription.
 *
 * POST + CSRF. Calls PayPal's cancel endpoint, then updates local
 * status to 'cancelled' and turns off any granted feature flags.
 *
 * Note on timing: PayPal's cancellation is immediate (no future
 * billing). If the tenant is mid-period they technically have
 * paid through current_period_end. For now we revoke features
 * immediately on cancel — a future tweak could keep them active
 * until the period actually ends.
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

$sub   = billing_subscription_for($clientId);
$subId = $sub['external_subscription_id'] ?? '';
$reason = trim((string) ($_POST['reason'] ?? '')) ?: 'Cancelled from YourBlinds billing page';

// If there's a PayPal subscription on record, ask PayPal to cancel it.
// Tolerate "already cancelled" errors so a double-click doesn't 500.
if ($subId && paypal_is_configured()) {
    try {
        paypal_request(
            'POST',
            '/v1/billing/subscriptions/' . rawurlencode((string) $subId) . '/cancel',
            ['reason' => $reason]
        );
    } catch (Throwable $e) {
        // Log + carry on. The local cancel still happens — better
        // to mark it locally cancelled than leave the tenant in a
        // half-cancelled state because PayPal's API was flaky.
        error_log('PayPal cancel call failed (continuing with local cancel): '
            . $e->getMessage());
    }
}

db()->prepare(
    "UPDATE tenant_subscriptions
        SET status = 'cancelled',
            cancelled_at = NOW()
      WHERE client_id = ?"
)->execute([$clientId]);

billing_sync_feature_flags($clientId);

$_SESSION['flash_success'] = 'Subscription cancelled. Paid features have been turned off.';
header('Location: /billing/index.php');
exit;
