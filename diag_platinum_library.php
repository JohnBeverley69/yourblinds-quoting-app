<?php
declare(strict_types=1);

/**
 * Diagnose: any live tenants still on the retired Platinum tier / price-library?
 *
 * Hit /diag_platinum_library.php while logged in as a super-admin.
 *
 * Read-only. Makes NO changes — it only reports, so you can decide what to
 * cancel in PayPal / reset on the DB. Reports:
 *   1. tenant_subscriptions rows with plan_code = 'platinum' (any status) —
 *      the ones that may still be billing in PayPal.
 *   2. client_settings rows with feature_price_library = 1 — tenants still
 *      granted the withdrawn add-on.
 *   3. plan_pricing row for 'platinum' — a leftover PayPal plan id, if any.
 *   4. client_library_suppliers — tenants that had enabled library suppliers
 *      (informational; the client-side library page is now disabled).
 *
 * Every section is wrapped in try/catch so a missing table/column reports
 * "n/a" rather than fatalling. Delete this file once the cleanup is done.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

/** Run a query and pretty-print rows; report gracefully if the table is absent. */
function section(PDO $pdo, string $title, string $sql, array $params = []): int
{
    echo "== $title ==\n";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "  n/a — " . $e->getMessage() . "\n\n";
        return 0;
    }
    if (!$rows) {
        echo "  none found ✓\n\n";
        return 0;
    }
    foreach ($rows as $r) {
        $parts = [];
        foreach ($r as $k => $v) {
            $parts[] = $k . '=' . ($v === null ? 'NULL' : (string) $v);
        }
        echo "  - " . implode('  ', $parts) . "\n";
    }
    echo "  (" . count($rows) . " row" . (count($rows) === 1 ? '' : 's') . ")\n\n";
    return count($rows);
}

echo "Platinum / price-library cleanup diagnostic\n";
echo "Read-only — nothing is modified.\n";
echo str_repeat('-', 60) . "\n\n";

$total = 0;

// 1. Subscriptions on the retired Platinum plan (any status). These are the
//    ones that may still be charging in PayPal — cancel/refund there.
$total += section(
    $pdo,
    "tenant_subscriptions on plan_code = 'platinum'",
    "SELECT ts.client_id, c.company_name, ts.status,
            ts.external_subscription_id, ts.current_period_end,
            ts.commitment_end_at, ts.cancelled_at
       FROM tenant_subscriptions ts
       LEFT JOIN clients c ON c.id = ts.client_id
      WHERE ts.plan_code = 'platinum'
      ORDER BY ts.status, ts.client_id"
);

// 2. Tenants still granted the withdrawn feature flag.
$total += section(
    $pdo,
    "client_settings with feature_price_library = 1",
    "SELECT cs.client_id, c.company_name, cs.feature_price_library
       FROM client_settings cs
       LEFT JOIN clients c ON c.id = cs.client_id
      WHERE cs.feature_price_library = 1
      ORDER BY cs.client_id"
);

// 3. Leftover PayPal plan for Platinum (informational — deactivate in PayPal).
$total += section(
    $pdo,
    "plan_pricing row for 'platinum'",
    "SELECT plan_code, price_gbp_monthly, paypal_plan_id
       FROM plan_pricing
      WHERE plan_code = 'platinum'"
);

// 4. Tenants that had enabled library suppliers (informational only — the
//    client-side library page is now disabled; their copied products remain).
section(
    $pdo,
    "client_library_suppliers (enabled suppliers — informational)",
    "SELECT cls.client_id, c.company_name, cls.supplier_key,
            cls.last_imported_at
       FROM client_library_suppliers cls
       LEFT JOIN clients c ON c.id = cls.client_id
      ORDER BY cls.client_id, cls.supplier_key"
);

echo str_repeat('-', 60) . "\n";
if ($total === 0) {
    echo "RESULT: nothing to clean up — no Platinum subscriptions and no\n";
    echo "        tenants hold feature_price_library. ✓\n";
} else {
    echo "RESULT: $total item(s) above need attention.\n";
    echo "  • Cancel/refund any 'active'/'trial'/'past_due'/'pending' Platinum\n";
    echo "    subscriptions in PayPal (use external_subscription_id).\n";
    echo "  • Then reset those tenants: UPDATE client_settings\n";
    echo "    SET feature_price_library = 0 WHERE client_id IN (...);\n";
    echo "    and mark the tenant_subscriptions rows cancelled if appropriate.\n";
    echo "  (This script does NOT make those changes.)\n";
}
