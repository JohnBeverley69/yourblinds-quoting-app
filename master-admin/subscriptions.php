<?php
declare(strict_types=1);

/**
 * Super-admin: subscription state for every tenant, per add-on plan.
 *
 * Compact list, one row per tenant. Each row shows the tenant name
 * plus a pill-summary of their active subscriptions ("Accounts ✓
 * Active", "Maps ⏳ Trial"). Click the row to expand into the
 * inline editor (plan / status / period dates / notes / Save / Delete
 * forms — one per subscription, plus an "Add plan" row at the bottom).
 *
 * Tenants with problem statuses (past_due, expired, cancelled) sort
 * to the top automatically. Quick text filter at the top of the page
 * narrows the list when there are many tenants.
 *
 * Save → fires billing_sync_feature_flags_force() so the relevant
 * feature_* flags pick up the change. The PayPal webhook is the
 * canonical source of truth for state on PayPal-managed subs; this
 * page is for manual fixes + the rare manual-state scenario.
 *
 * Comps (free-of-charge access) for premium clients are also managed
 * inline on each tenant card — these write to client_plan_overrides
 * (same table as /master-admin/pricing.php uses) so the two pages
 * stay in sync. A comp survives subscription cancellation; a paid
 * subscription supersedes a comp at the entitlement level (no double-
 * counting). Defaults: new manual subscription rows start on 'trial'.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireSuperAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── POST handler (save / delete) ────────────────────────────────────
//
// Unchanged from the original — same form fields, same validation.
// We just bounce back to the page with a flash; the redesigned UI
// shows it at the top.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');

    try {
        $pdo = db();

        // Re-sync one subscription's TRUE state from PayPal (handles webhook
        // misses, and shows the real PayPal status). If it comes back active,
        // run the tier-activation side-effects (commitment + supersede).
        if ($action === 'resync') {
            $targetClient = (int)    ($_POST['client_id'] ?? 0);
            $plan         = (string) ($_POST['plan_code'] ?? '');
            if ($targetClient <= 0 || $plan === '') throw new RuntimeException('Missing client/plan.');

            $row = $pdo->prepare(
                'SELECT external_subscription_id FROM tenant_subscriptions
                  WHERE client_id = ? AND plan_code = ? LIMIT 1'
            );
            $row->execute([$targetClient, $plan]);
            $extId = (string) ($row->fetchColumn() ?: '');
            if ($extId === '') throw new RuntimeException('No PayPal subscription id on this row to re-sync.');
            if (!paypal_is_configured()) throw new RuntimeException('PayPal is not configured on this server.');

            $r = paypal_request('GET', '/v1/billing/subscriptions/' . rawurlencode($extId));
            $full = $r['data'];
            $ppStatus    = strtoupper((string) ($full['status'] ?? ''));
            $localStatus = paypal_map_status($ppStatus);
            $nbt = $full['billing_info']['next_billing_time'] ?? null;
            $pe  = (is_string($nbt) && strlen($nbt) >= 10) ? substr($nbt, 0, 10) : null;

            $pdo->prepare(
                "UPDATE tenant_subscriptions
                    SET status = ?, current_period_end = ?,
                        cancelled_at = CASE WHEN ? = 'active' THEN NULL ELSE cancelled_at END
                  WHERE client_id = ? AND plan_code = ?"
            )->execute([$localStatus, $pe, $localStatus, $targetClient, $plan]);

            if ($localStatus === 'active') billing_on_tier_activated($targetClient, $plan);
            billing_sync_feature_flags_force($targetClient);

            $_SESSION['flash_success'] = 'Re-synced ' . ((string) (billing_plan($plan)['name'] ?? $plan))
                . ' from PayPal — PayPal says "' . $ppStatus . '" (recorded as ' . $localStatus . ').';
            header('Location: /master-admin/subscriptions.php');
            exit;
        }

        if ($action === 'delete') {
            $targetClient = (int)    ($_POST['client_id'] ?? 0);
            $plan         = (string) ($_POST['plan_code'] ?? '');
            if ($targetClient <= 0 || $plan === '') throw new RuntimeException('Missing client/plan.');
            $pdo->prepare(
                'DELETE FROM tenant_subscriptions WHERE client_id = ? AND plan_code = ?'
            )->execute([$targetClient, $plan]);
            billing_sync_feature_flags_force($targetClient);
            $_SESSION['flash_success'] = 'Subscription row removed.';
            header('Location: /master-admin/subscriptions.php');
            exit;
        }

        // ── Comp (free forever) — premium-client perk ────────────────
        //
        // Writes to client_plan_overrides with override_type='comp' so
        // the existing entitlement model (billing_plan_active_for)
        // picks it up without any further wiring. Mirrors the handler
        // on /master-admin/pricing.php so behaviour is identical.
        //
        // Accepts EITHER plan_code (single) or plan_codes[] (multi —
        // tick several plans, one submit). Both go through the same
        // upsert loop so semantics stay identical.
        if ($action === 'add_comp') {
            $targetClient = (int) ($_POST['client_id'] ?? 0);
            $notes        = trim((string) ($_POST['notes'] ?? '')) ?: null;

            $codes = [];
            if (isset($_POST['plan_codes']) && is_array($_POST['plan_codes'])) {
                foreach ($_POST['plan_codes'] as $c) $codes[] = (string) $c;
            } elseif (!empty($_POST['plan_code'])) {
                $codes[] = (string) $_POST['plan_code'];
            }
            $codes = array_values(array_unique(array_filter($codes, static fn ($c) => $c !== '')));

            if ($targetClient <= 0) throw new RuntimeException('Missing client.');
            if (!$codes)            throw new RuntimeException('Pick at least one plan to comp.');
            foreach ($codes as $c) {
                if (!billing_plan($c)) throw new RuntimeException('Unknown plan: ' . $c);
                if ($c === 'free')     throw new RuntimeException('Free plan needs no comp.');
            }

            foreach ($codes as $c) {
                try {
                    $pdo->prepare(
                        "INSERT INTO client_plan_overrides
                           (client_id, plan_code, override_type, expires_at,
                            notes, active)
                           VALUES (?, ?, 'comp', NULL, ?, 1)
                         ON DUPLICATE KEY UPDATE
                           override_type = 'comp',
                           expires_at    = NULL,
                           notes         = VALUES(notes),
                           active        = 1"
                    )->execute([$targetClient, $c, $notes]);
                } catch (Throwable $colErr) {
                    // expires_at column missing on older schemas — degrade.
                    $pdo->prepare(
                        "INSERT INTO client_plan_overrides
                           (client_id, plan_code, override_type, notes, active)
                           VALUES (?, ?, 'comp', ?, 1)
                         ON DUPLICATE KEY UPDATE
                           override_type = 'comp',
                           notes         = VALUES(notes),
                           active        = 1"
                    )->execute([$targetClient, $c, $notes]);
                }
            }

            billing_sync_feature_flags_force($targetClient);
            $n = count($codes);
            $_SESSION['flash_success'] = $n === 1
                ? 'Comp added — paid features turned on free of charge.'
                : $n . ' comps added — paid features turned on free of charge.';
            header('Location: /master-admin/subscriptions.php');
            exit;
        }

        if ($action === 'remove_comp') {
            $id = (int) ($_POST['override_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Missing override id.');

            $st = $pdo->prepare('SELECT client_id FROM client_plan_overrides WHERE id = ?');
            $st->execute([$id]);
            $cid = (int) ($st->fetchColumn() ?: 0);

            $pdo->prepare('DELETE FROM client_plan_overrides WHERE id = ?')->execute([$id]);

            if ($cid > 0) billing_sync_feature_flags_force($cid);

            $_SESSION['flash_success'] = 'Comp removed. Paid features turn off unless the '
                . 'tenant has a separate active subscription.';
            header('Location: /master-admin/subscriptions.php');
            exit;
        }

        // 'save' action — accepts EITHER plan_code (per-row Save on an
        // existing subscription) or plan_codes[] (multi-tick on the
        // "Add plans" form). All ticked plans share one status / one
        // period / one notes string, which is exactly what a super-admin
        // setting up a new tenant wants: "give them Maps + Postcode +
        // Accounts, all on trial until 30 June, click once."
        $targetClient = (int)    ($_POST['client_id'] ?? 0);
        $status       = (string) ($_POST['status']    ?? 'active');
        $periodStart  = trim((string) ($_POST['current_period_start'] ?? '')) ?: null;
        $periodEnd    = trim((string) ($_POST['current_period_end']   ?? '')) ?: null;
        $notes        = trim((string) ($_POST['notes'] ?? '')) ?: null;

        $plans_in = [];
        if (isset($_POST['plan_codes']) && is_array($_POST['plan_codes'])) {
            foreach ($_POST['plan_codes'] as $c) $plans_in[] = (string) $c;
        } elseif (!empty($_POST['plan_code'])) {
            $plans_in[] = (string) $_POST['plan_code'];
        }
        $plans_in = array_values(array_unique(array_filter($plans_in, static fn ($c) => $c !== '')));

        if ($targetClient <= 0) throw new RuntimeException('Bad client id.');
        if (!$plans_in)         throw new RuntimeException('Pick at least one plan.');
        foreach ($plans_in as $p) {
            if (!billing_plan($p) || $p === 'free') {
                throw new RuntimeException('Pick a paid plan (bad code: ' . $p . ').');
            }
        }

        if (!in_array($status, array_keys(billing_status_labels()), true)) {
            $status = 'trial';
        }
        foreach (['periodStart', 'periodEnd'] as $var) {
            $v = $$var;
            if ($v !== null && DateTimeImmutable::createFromFormat('!Y-m-d', $v) === false) {
                $$var = null;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tenant_subscriptions
              (client_id, plan_code, status,
               current_period_start, current_period_end, notes)
              VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              status               = VALUES(status),
              current_period_start = VALUES(current_period_start),
              current_period_end   = VALUES(current_period_end),
              notes                = VALUES(notes)'
        );
        foreach ($plans_in as $p) {
            $stmt->execute([$targetClient, $p, $status, $periodStart, $periodEnd, $notes]);
        }

        billing_sync_feature_flags_force($targetClient);

        $n = count($plans_in);
        $_SESSION['flash_success'] = $n === 1
            ? 'Subscription saved.'
            : $n . ' subscriptions saved.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /master-admin/subscriptions.php');
    exit;
}

// ─── Load data ────────────────────────────────────────────────────────
$clients = db()->query(
    'SELECT id, company_name, active FROM clients ORDER BY company_name'
)->fetchAll();

$subRows = db()->query(
    'SELECT ts.*
       FROM tenant_subscriptions ts
       JOIN clients c ON c.id = ts.client_id
   ORDER BY c.company_name, ts.plan_code'
)->fetchAll();

$byClient = [];
foreach ($subRows as $r) {
    $byClient[(int) $r['client_id']][(string) $r['plan_code']] = $r;
}

// Comps for every tenant — used both for the summary pills and the
// inline "Comp this plan" controls inside each card. We include only
// override_type='comp' here (forever-free, no expiry). Trials live on
// /master-admin/pricing.php; they're a separate concept from this
// page's "give a premium client free access" workflow.
$compsByClient = [];
try {
    $compRows = db()->query(
        "SELECT o.id, o.client_id, o.plan_code, o.notes, o.created_at
           FROM client_plan_overrides o
          WHERE o.active = 1
            AND o.override_type = 'comp'"
    )->fetchAll();
    foreach ($compRows as $cr) {
        $compsByClient[(int) $cr['client_id']][(string) $cr['plan_code']] = $cr;
    }
} catch (Throwable $e) {
    // client_plan_overrides table missing — feature gracefully absent.
    $compsByClient = [];
}

$paidPlans = billing_paid_plans();
$plans     = billing_plans();
$statuses  = billing_status_labels();

// ─── Sort priority — problems first ──────────────────────────────────
//
// "Health score": higher = more urgent. past_due / expired bubble to
// the top, then trial (expiring soon), then active, then no subs.
// Inactive tenants drop to the very bottom regardless.
$healthOf = static function (array $subs): int {
    $worst = 0;
    foreach ($subs as $s) {
        $st = (string) ($s['status'] ?? '');
        $score = match ($st) {
            'past_due'  => 100,
            'expired'   => 90,
            'cancelled' => 50,
            'trial'     => 30,
            'active'    => 10,
            default     => 0,
        };
        if ($score > $worst) $worst = $score;
    }
    return $worst;
};

usort($clients, static function ($a, $b) use ($byClient, $healthOf) {
    $aActive = (int) $a['active'];
    $bActive = (int) $b['active'];
    if ($aActive !== $bActive) return $bActive <=> $aActive;   // active first
    $ha = $healthOf($byClient[(int) $a['id']] ?? []);
    $hb = $healthOf($byClient[(int) $b['id']] ?? []);
    if ($ha !== $hb) return $hb <=> $ha;                       // worst health first
    return strcasecmp((string) $a['company_name'], (string) $b['company_name']);
});

$activeNav = 'subscriptions';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscriptions &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .sub-search {
            width: 100%; max-width: 28rem;
            padding: 0.5rem 0.75rem; font: inherit; font-size: 0.9375rem;
            border: 1px solid var(--border-strong); border-radius: 8px; background: var(--bg-input);
            margin-bottom: 0.875rem;
        }
        details.tenant-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px;
            margin-bottom: 0.5rem; overflow: hidden;
        }
        details.tenant-card > summary {
            list-style: none; cursor: pointer;
            padding: 0.75rem 1rem;
            display: flex; align-items: center; gap: 0.625rem;
            flex-wrap: wrap;
        }
        details.tenant-card[open] > summary { background: var(--bg-subtle); border-bottom: 1px solid var(--border); }
        details.tenant-card > summary::-webkit-details-marker { display: none; }
        details.tenant-card > summary::before {
            content: '▸'; color: var(--text-faint); font-size: 0.75rem;
            transition: transform 150ms;
        }
        details.tenant-card[open] > summary::before { transform: rotate(90deg); }
        details.tenant-card .t-name {
            font-weight: 600; color: var(--text-primary); font-size: 1rem;
        }
        details.tenant-card .t-self {
            background: #1f3b5b; color: #fff; padding: 0.0625rem 0.5rem;
            border-radius: 999px; font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        details.tenant-card .t-inactive {
            background: var(--border); color: var(--text-secondary); padding: 0.0625rem 0.5rem;
            border-radius: 999px; font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        details.tenant-card .sub-summary {
            display: inline-flex; align-items: center; gap: 0.3125rem;
            font-size: 0.8125rem; padding: 0.125rem 0.5rem;
            border-radius: 999px; background: var(--bg-subtle-2); color: var(--text-secondary);
        }
        details.tenant-card .sub-summary.has-active    { background: #d1fae5; color: #065f46; }
        details.tenant-card .sub-summary.has-trial     { background: #fef3c7; color: #92400e; }
        details.tenant-card .sub-summary.has-past_due  { background: #fed7aa; color: #9a3412; }
        details.tenant-card .sub-summary.has-cancelled { background: #fee2e2; color: #991b1b; }
        details.tenant-card .sub-summary.has-expired   { background: var(--border); color: var(--text-secondary); }
        details.tenant-card .sub-summary.has-comp      { background: #ede9fe; color: #5b21b6; }
        details.tenant-card .sub-summary.has-comp strong::before {
            content: '🎁 '; font-size: 0.875rem;
        }
        details.tenant-card .none-yet {
            color: var(--text-faint); font-style: italic; font-size: 0.875rem;
        }
        details.tenant-card .t-meta {
            color: var(--text-faint); font-size: 0.8125rem; margin-left: auto;
        }

        details.tenant-card > .body { padding: 0.5rem 1rem 1rem; }
        .sub-row {
            border-top: 1px solid var(--bg-subtle-2);
            padding: 0.625rem 0;
        }
        .sub-row:first-child { border-top: 0; padding-top: 0.25rem; }
        .sub-row form {
            display: grid;
            grid-template-columns: 1.25fr 1fr 8rem 8rem 1.5fr auto auto;
            gap: 0.5rem 0.5rem; align-items: end;
        }
        .sub-row .sub-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .sub-row .sub-field label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .sub-row .sub-field input,
        .sub-row .sub-field select {
            padding: 0.375rem 0.5rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; background: var(--bg-input);
        }
        .sub-row .sub-delete button {
            background: transparent; color: #b91c1c; border: 0;
            cursor: pointer; font-size: 0.8125rem; padding: 0.375rem 0.5rem;
        }
        .sub-row .sub-delete button:hover { text-decoration: underline; }
        .sub-row .sub-ext-id {
            margin-top: 0.25rem; color: var(--text-faint); font-size: 0.75rem;
        }
        .sub-row.add-new {
            background: var(--bg-subtle); border-radius: 8px;
            padding: 0.625rem 0.75rem;
            margin-top: 0.5rem; border-top: 0;
        }
        /* Superseded — a paid sub row exists for a plan that's been
           comp'd. The comp grants entitlement, so this row is
           paperwork. We collapse it into a muted strip with a quick
           Delete button + a "show editor" toggle. Stays editable for
           the rare case of moving the tenant back to paid. */
        details.sub-superseded {
            background: var(--bg-subtle); border: 1px dashed var(--border);
            border-radius: 8px; margin-top: 0.5rem; padding: 0;
        }
        details.sub-superseded > summary {
            list-style: none; cursor: pointer;
            padding: 0.375rem 0.625rem;
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.8125rem; color: var(--text-faint);
        }
        details.sub-superseded > summary::-webkit-details-marker { display: none; }
        details.sub-superseded > summary::before {
            content: '▸'; color: var(--text-faint); font-size: 0.6875rem;
            transition: transform 150ms;
        }
        details.sub-superseded[open] > summary::before { transform: rotate(90deg); }
        details.sub-superseded .ss-tag {
            background: #ede9fe; color: #5b21b6;
            padding: 0.0625rem 0.4375rem; border-radius: 999px;
            font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        details.sub-superseded .ss-plan { color: var(--text-secondary); font-weight: 600; }
        details.sub-superseded .ss-spacer { flex: 1; }
        details.sub-superseded .ss-quick-delete {
            background: transparent; color: #b91c1c; border: 0;
            cursor: pointer; font-size: 0.8125rem; padding: 0.125rem 0.375rem;
        }
        details.sub-superseded .ss-quick-delete:hover { text-decoration: underline; }
        details.sub-superseded > .ss-body { padding: 0 0.625rem 0.625rem; }
        /* Multi-plan picker — replaces the old single-plan <select>.
           Each plan = a checkbox tile so the admin can tick several in
           one go ("give them Maps AND Postcode AND Accounts, all on
           trial") and submit once. */
        .plan-picker {
            display: flex; flex-wrap: wrap; gap: 0.375rem;
            grid-column: 1 / -1;
        }
        .plan-picker label {
            display: inline-flex; align-items: center; gap: 0.375rem;
            padding: 0.375rem 0.625rem;
            background: var(--bg-card); border: 1px solid var(--border-strong);
            border-radius: 999px; font-size: 0.8125rem;
            cursor: pointer; user-select: none;
            transition: background 100ms, border-color 100ms;
        }
        .plan-picker label:hover { background: var(--bg-subtle-2); border-color: var(--text-faint); }
        .plan-picker input[type="checkbox"] { margin: 0; }
        .plan-picker input[type="checkbox"]:checked + span {
            font-weight: 600;
        }
        .plan-picker label:has(input:checked) {
            background: #dbeafe; border-color: #60a5fa; color: #1e3a8a;
        }
        .comp-add .plan-picker label:has(input:checked) {
            background: #ede9fe; border-color: #a78bfa; color: #5b21b6;
        }
        .plan-picker .pp-price {
            color: var(--text-faint); font-weight: 400;
        }
        .add-row-shared {
            display: grid;
            grid-template-columns: 1fr 8rem 8rem 1.5fr auto;
            gap: 0.5rem; align-items: end;
            margin-top: 0.5rem;
        }
        .add-row-shared > .sub-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .add-row-shared label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .add-row-shared input, .add-row-shared select {
            padding: 0.375rem 0.5rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; background: var(--bg-input);
        }
        @media (max-width: 1000px) {
            .add-row-shared { grid-template-columns: 1fr 1fr; }
            .add-row-shared > button { grid-column: 1 / -1; justify-self: end; }
        }
        @media (max-width: 1000px) {
            .sub-row form { grid-template-columns: 1fr 1fr; }
            .sub-row .sub-submit, .sub-row .sub-delete { grid-column: auto; }
        }
        /* Comp section — visually distinct from the paid-sub rows so
           there's no chance of mixing the two up at a glance. */
        .comp-block {
            margin-top: 0.875rem; padding-top: 0.625rem;
            border-top: 1px dashed #ddd6fe;
        }
        .comp-block h4 {
            margin: 0 0 0.5rem; font-size: 0.8125rem; color: #5b21b6;
            text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;
        }
        .comp-existing {
            display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
            background: #f5f3ff; border: 1px solid #ddd6fe;
            border-radius: 8px; padding: 0.375rem 0.625rem;
            margin-bottom: 0.375rem; font-size: 0.8125rem;
        }
        .comp-existing strong { color: #5b21b6; }
        .comp-existing .c-notes { color: var(--text-faint); flex: 1 1 8rem; }
        .comp-existing form { margin: 0; }
        .comp-existing button {
            background: transparent; color: #b91c1c; border: 0;
            cursor: pointer; font-size: 0.8125rem; padding: 0.125rem 0.25rem;
        }
        .comp-existing button:hover { text-decoration: underline; }
        .comp-add form {
            display: grid;
            grid-template-columns: 1fr 2fr auto;
            gap: 0.5rem; align-items: end;
            background: #faf5ff; border: 1px dashed #c4b5fd;
            border-radius: 8px; padding: 0.5rem 0.625rem;
        }
        .comp-add label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .comp-add select, .comp-add input {
            padding: 0.375rem 0.5rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; background: var(--bg-input);
        }
        .comp-add button {
            background: #5b21b6; color: #fff; border: 0;
            padding: 0.4375rem 0.875rem; border-radius: 6px;
            font-size: 0.8125rem; font-weight: 600; cursor: pointer;
        }
        .comp-add button:hover { background: #4c1d95; }
        @media (max-width: 1000px) {
            .comp-add form { grid-template-columns: 1fr 1fr; }
            .comp-add form > button { grid-column: 1 / -1; justify-self: end; }
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
                    &middot;
                    <a href="/master-admin/pricing.php">Pricing &amp; comps</a>
                    &middot; per-tenant PayPal subscription state.
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:0.75rem 1rem;margin-bottom:0.875rem">
            <p style="margin:0;color:#0c4a6e;font-size:0.875rem;line-height:1.5">
                PayPal-managed subscriptions update automatically via webhook;
                edit here only when you need to manually fix state. New manual
                rows default to <em>trial</em>. Tenants with problem statuses
                (<em>past due</em>, <em>expired</em>) appear at the top. To give a
                premium client an add-on <strong>free of charge</strong>, use the
                purple <em>Comp this client</em> form inside their card
                — that turns the features on with no PayPal billing.
            </p>
        </section>

        <input type="text" id="tenant-filter" class="sub-search"
               placeholder="Filter tenants by name…"
               autocomplete="off">

        <section class="section" id="tenant-list">
            <?php foreach ($clients as $c):
                $cid        = (int) $c['id'];
                $isSelf     = $cid === $clientId;
                $tenantSubs = $byClient[$cid] ?? [];
                $tenantComps = $compsByClient[$cid] ?? [];

                // Comp supersedes a paid subscription for the same plan
                // — the entitlement is granted by the comp, the paid
                // row becomes paperwork. Split the subs into "active"
                // (no comp covers them) and "superseded" so the UI can
                // collapse the noise. A "problem" status (past_due /
                // expired) is hidden too if the comp is keeping the
                // tenant alive — that's the desired outcome of a comp.
                $activeSubs     = [];
                $supersededSubs = [];
                foreach ($tenantSubs as $code => $r) {
                    if (isset($tenantComps[$code])) {
                        $supersededSubs[$code] = $r;
                    } else {
                        $activeSubs[$code] = $r;
                    }
                }

                $hasIssue = false;
                foreach ($activeSubs as $s) {
                    $st = (string) ($s['status'] ?? '');
                    if ($st === 'past_due' || $st === 'expired') { $hasIssue = true; break; }
                }

                // Effective row count = what the admin actually cares
                // about. Superseded rows count as one combined "free
                // (comp)" entry, not separate noise.
                $rowCount = count($activeSubs) + count($tenantComps);
            ?>
                <details class="tenant-card"
                         data-name="<?= e(strtolower((string) $c['company_name'])) ?>"
                         <?= $hasIssue ? 'open' : '' ?>>
                    <summary>
                        <span class="t-name"><?= e((string) $c['company_name']) ?></span>
                        <?php if ($isSelf): ?>
                            <span class="t-self">You</span>
                        <?php endif; ?>
                        <?php if (!(int) $c['active']): ?>
                            <span class="t-inactive">Inactive</span>
                        <?php endif; ?>

                        <?php if (!$activeSubs && !$tenantComps): ?>
                            <span class="none-yet">No subscriptions</span>
                        <?php else:
                            // Only non-superseded paid pills here — if
                            // a comp covers the plan, the comp pill below
                            // is the truthful representation.
                            foreach ($activeSubs as $code => $r):
                                $plan   = (string) ($r['plan_code'] ?? $code);
                                $status = (string) ($r['status']    ?? 'active');
                                $label  = $statuses[$status] ?? $status;
                                $planName = (string) ($plans[$plan]['name'] ?? $plan);
                        ?>
                            <span class="sub-summary has-<?= e($status) ?>">
                                <strong><?= e($planName) ?></strong>
                                · <?= e($label) ?>
                                <?php if (!empty($r['current_period_end']) && $status === 'active'): ?>
                                    · until <?= e(date('j M', strtotime((string) $r['current_period_end']))) ?>
                                <?php endif; ?>
                            </span>
                        <?php
                            endforeach;
                            // Comp pills come last so the eye reads
                            // paid → free in a natural left-to-right order.
                            foreach ($tenantComps as $code => $cr):
                                $planName = (string) ($plans[$code]['name'] ?? $code);
                        ?>
                            <span class="sub-summary has-comp">
                                <strong><?= e($planName) ?></strong>
                                · Free (comp)
                            </span>
                        <?php endforeach; endif; ?>

                        <span class="t-meta">
                            <?= $rowCount ?>
                            <?= $rowCount === 1 ? 'row' : 'rows' ?>
                            <?php if ($supersededSubs): ?>
                                <span style="color:var(--text-faint)">
                                    (+<?= count($supersededSubs) ?> superseded)
                                </span>
                            <?php endif; ?>
                        </span>
                    </summary>

                    <div class="body">
                        <?php if ($activeSubs): foreach ($activeSubs as $code => $r):
                            $plan    = (string) ($r['plan_code'] ?? $code);
                            $status  = (string) ($r['status']    ?? 'active');
                            $started = (string) ($r['current_period_start'] ?? '');
                            $ends    = (string) ($r['current_period_end']   ?? '');
                            $notes   = (string) ($r['notes'] ?? '');
                        ?>
                            <div class="sub-row">
                                <form method="post" action="/master-admin/subscriptions.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="client_id" value="<?= $cid ?>">
                                    <input type="hidden" name="plan_code" value="<?= e($plan) ?>">

                                    <div class="sub-field">
                                        <label>Plan</label>
                                        <div style="padding:0.375rem 0;font-weight:600;color:var(--text-body)">
                                            <?= e($plans[$plan]['name'] ?? $plan) ?>
                                        </div>
                                    </div>

                                    <div class="sub-field">
                                        <label for="status-<?= $cid ?>-<?= e($plan) ?>">Status</label>
                                        <select id="status-<?= $cid ?>-<?= e($plan) ?>" name="status">
                                            <?php foreach ($statuses as $s => $label): ?>
                                                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                                                    <?= e($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="sub-field">
                                        <label>Period start</label>
                                        <input name="current_period_start" type="date" value="<?= e($started) ?>">
                                    </div>

                                    <div class="sub-field">
                                        <label>Period end</label>
                                        <input name="current_period_end" type="date" value="<?= e($ends) ?>">
                                    </div>

                                    <div class="sub-field">
                                        <label>Notes</label>
                                        <input name="notes" type="text" maxlength="500" value="<?= e($notes) ?>"
                                               placeholder="e.g. fixed manually after webhook miss">
                                    </div>

                                    <div class="sub-submit">
                                        <button type="submit" class="btn btn-primary"
                                                style="padding:0.4375rem 0.875rem;font-size:0.8125rem">
                                            Save
                                        </button>
                                    </div>

                                    <div class="sub-delete">
                                        <button type="submit" name="action" value="delete"
                                                data-confirm="Remove the <?= e($plans[$plan]['name'] ?? $plan) ?> subscription row for <?= e((string) $c['company_name']) ?>? This won't cancel anything on PayPal — only deletes the local record.">
                                            Delete
                                        </button>
                                    </div>
                                </form>

                                <?php if (!empty($r['external_subscription_id'])): ?>
                                    <div class="sub-ext-id">
                                        <?= e((string) $r['external_provider']) ?>:
                                        <code><?= e((string) $r['external_subscription_id']) ?></code>
                                        <?php if (!empty($r['cancelled_at'])): ?>
                                            &middot; cancelled <?= e(date('j M Y', strtotime((string) $r['cancelled_at']))) ?>
                                        <?php endif; ?>
                                        <form method="post" action="/master-admin/subscriptions.php" style="display:inline;margin:0 0 0 0.5rem">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="resync">
                                            <input type="hidden" name="client_id" value="<?= $cid ?>">
                                            <input type="hidden" name="plan_code" value="<?= e($plan) ?>">
                                            <button type="submit" style="background:transparent;border:0;color:#2563eb;cursor:pointer;font-size:0.75rem;padding:0;text-decoration:underline">↻ Re-sync from PayPal</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>

                        <!--
                            Superseded paid rows — a comp covers this plan,
                            so the paid row is paperwork. Rendered as a
                            collapsed muted strip with a one-click Delete
                            and the full editor available on expand (rare
                            case: moving the tenant back to paid).
                        -->
                        <?php foreach ($supersededSubs as $code => $r):
                            $plan    = (string) ($r['plan_code'] ?? $code);
                            $status  = (string) ($r['status']    ?? 'active');
                            $label   = $statuses[$status] ?? $status;
                            $started = (string) ($r['current_period_start'] ?? '');
                            $ends    = (string) ($r['current_period_end']   ?? '');
                            $notes   = (string) ($r['notes'] ?? '');
                            $planName = (string) ($plans[$plan]['name'] ?? $plan);
                        ?>
                            <details class="sub-superseded">
                                <summary>
                                    <span class="ss-tag">Superseded by comp</span>
                                    <span class="ss-plan"><?= e($planName) ?></span>
                                    <span>(was <?= e(strtolower($label)) ?>)</span>
                                    <span class="ss-spacer"></span>
                                    <!-- Quick delete — most common action
                                         once a comp lands. Sits in its own
                                         tiny form so it doesn't submit the
                                         full editor below. -->
                                    <form method="post" action="/master-admin/subscriptions.php"
                                          style="margin:0"
                                          data-confirm="Delete the superseded <?= e($planName) ?> row? This won't touch the comp; it just removes the paperwork. Won't cancel anything on PayPal either.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="client_id" value="<?= $cid ?>">
                                        <input type="hidden" name="plan_code" value="<?= e($plan) ?>">
                                        <button type="submit" class="ss-quick-delete">Delete row</button>
                                    </form>
                                </summary>
                                <div class="ss-body">
                                    <div class="sub-row">
                                        <form method="post" action="/master-admin/subscriptions.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="save">
                                            <input type="hidden" name="client_id" value="<?= $cid ?>">
                                            <input type="hidden" name="plan_code" value="<?= e($plan) ?>">

                                            <div class="sub-field">
                                                <label>Plan</label>
                                                <div style="padding:0.375rem 0;font-weight:600;color:var(--text-body)">
                                                    <?= e($planName) ?>
                                                </div>
                                            </div>

                                            <div class="sub-field">
                                                <label for="ss-status-<?= $cid ?>-<?= e($plan) ?>">Status</label>
                                                <select id="ss-status-<?= $cid ?>-<?= e($plan) ?>" name="status">
                                                    <?php foreach ($statuses as $s => $lbl): ?>
                                                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                                                            <?= e($lbl) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="sub-field">
                                                <label>Period start</label>
                                                <input name="current_period_start" type="date" value="<?= e($started) ?>">
                                            </div>

                                            <div class="sub-field">
                                                <label>Period end</label>
                                                <input name="current_period_end" type="date" value="<?= e($ends) ?>">
                                            </div>

                                            <div class="sub-field">
                                                <label>Notes</label>
                                                <input name="notes" type="text" maxlength="500" value="<?= e($notes) ?>">
                                            </div>

                                            <div class="sub-submit">
                                                <button type="submit" class="btn btn-secondary"
                                                        style="padding:0.4375rem 0.875rem;font-size:0.8125rem">
                                                    Save
                                                </button>
                                            </div>

                                            <div></div>
                                        </form>

                                        <?php if (!empty($r['external_subscription_id'])): ?>
                                            <div class="sub-ext-id">
                                                <?= e((string) $r['external_provider']) ?>:
                                                <code><?= e((string) $r['external_subscription_id']) ?></code>
                                                <?php if (!empty($r['cancelled_at'])): ?>
                                                    &middot; cancelled <?= e(date('j M Y', strtotime((string) $r['cancelled_at']))) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>

                        <!--
                            Add MULTIPLE subscription rows in one go. The
                            handler accepts plan_codes[] so a single Save
                            click creates rows for every ticked plan with
                            the same status / period / notes. Avoids the
                            old "open card, pick Maps, save, repeat for
                            Postcode" rigmarole when setting up a new
                            tenant on a bundle of add-ons.

                            If the tenant already has every paid add-on
                            we hide the form entirely.
                        -->
                        <?php
                            $addableCodes = [];
                            foreach ($paidPlans as $pCode => $p) {
                                if (!isset($tenantSubs[$pCode])) {
                                    $addableCodes[$pCode] = $p;
                                }
                            }
                        ?>
                        <?php if ($addableCodes): ?>
                            <div class="sub-row add-new">
                                <form method="post" action="/master-admin/subscriptions.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="client_id" value="<?= $cid ?>">

                                    <div style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-faint);font-weight:600;margin-bottom:0.25rem">
                                        Add plans (tick one or more)
                                    </div>
                                    <div class="plan-picker">
                                        <?php foreach ($addableCodes as $pCode => $p): ?>
                                            <label>
                                                <input type="checkbox" name="plan_codes[]" value="<?= e($pCode) ?>">
                                                <span>
                                                    <?= e($p['name']) ?>
                                                    <span class="pp-price">(£<?= number_format((float) $p['price_gbp_monthly'], 2) ?>/mo)</span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="add-row-shared">
                                        <div class="sub-field">
                                            <label>Status (applies to all)</label>
                                            <!-- Default = trial. Almost every new
                                                 tenant starts on a trial; making
                                                 it the default saves a click and
                                                 avoids accidentally marking
                                                 brand-new clients 'active'. -->
                                            <select name="status">
                                                <?php foreach ($statuses as $s => $label): ?>
                                                    <option value="<?= e($s) ?>" <?= $s === 'trial' ? 'selected' : '' ?>>
                                                        <?= e($label) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="sub-field">
                                            <label>Period start</label>
                                            <input name="current_period_start" type="date">
                                        </div>

                                        <div class="sub-field">
                                            <label>Period end</label>
                                            <input name="current_period_end" type="date">
                                        </div>

                                        <div class="sub-field">
                                            <label>Notes</label>
                                            <input name="notes" type="text" maxlength="500"
                                                   placeholder="optional admin notes">
                                        </div>

                                        <button type="submit" class="btn btn-primary"
                                                style="padding:0.4375rem 0.875rem;font-size:0.8125rem">
                                            Add selected
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!--
                            Comps (free forever) — for premium clients we
                            want to give an add-on to as a perk. Writes to
                            client_plan_overrides; the entitlement model
                            already treats a comp as equivalent to an
                            active subscription, so feature flags switch
                            on the moment the form posts.

                            Existing comps appear first with a Remove
                            button; the "Comp this client" form is below
                            and only lists plans not already comp'd.
                        -->
                        <div class="comp-block">
                            <h4>🎁 Free-of-charge access (comps)</h4>

                            <?php foreach ($tenantComps as $code => $cr):
                                $planName = (string) ($plans[$code]['name'] ?? $code);
                            ?>
                                <div class="comp-existing">
                                    <strong><?= e($planName) ?></strong>
                                    <span>Free forever</span>
                                    <span class="c-notes">
                                        <?= !empty($cr['notes'])
                                            ? e((string) $cr['notes'])
                                            : '<span style="color:var(--text-faint)">no notes</span>' ?>
                                    </span>
                                    <form method="post" action="/master-admin/subscriptions.php"
                                          data-confirm="Remove the free <?= e($planName) ?> comp for <?= e((string) $c['company_name']) ?>? Paid features turn off unless they have a separate active subscription.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_comp">
                                        <input type="hidden" name="override_id" value="<?= (int) $cr['id'] ?>">
                                        <button type="submit">Remove comp</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>

                            <?php
                                // Plans the tenant is NOT already comp'd on.
                                // (They CAN have both a paid sub AND a comp —
                                // we just don't surface the comp option for
                                // plans they're already comp'd on.)
                                $compAvailable = [];
                                foreach ($paidPlans as $pCode => $p) {
                                    if (!isset($tenantComps[$pCode])) {
                                        $compAvailable[$pCode] = $p;
                                    }
                                }
                            ?>
                            <?php if ($compAvailable): ?>
                                <div class="comp-add">
                                    <form method="post" action="/master-admin/subscriptions.php"
                                          data-confirm="Give this client FREE access to the ticked plans? They won't be charged anything — features turn on immediately.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add_comp">
                                        <input type="hidden" name="client_id" value="<?= $cid ?>">

                                        <div style="grid-column:1/-1;font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-faint);font-weight:600">
                                            Comp this client (tick one or more)
                                        </div>
                                        <div class="plan-picker" style="grid-column:1/-1">
                                            <?php foreach ($compAvailable as $pCode => $p): ?>
                                                <label>
                                                    <input type="checkbox" name="plan_codes[]" value="<?= e($pCode) ?>">
                                                    <span>
                                                        <?= e($p['name']) ?>
                                                        <span class="pp-price">(normally £<?= number_format((float) $p['price_gbp_monthly'], 2) ?>/mo)</span>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                        <div style="grid-column:1/-1;display:grid;grid-template-columns:1fr auto;gap:0.5rem;align-items:end">
                                            <div>
                                                <label>Why (internal notes — applies to all)</label>
                                                <input name="notes" type="text" maxlength="500"
                                                       placeholder="e.g. premium client perk, partner deal">
                                            </div>
                                            <button type="submit">Give free access</button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p style="margin:0.375rem 0 0;font-size:0.8125rem;color:var(--text-faint);font-style:italic">
                                    Every paid add-on is already comp'd for this client.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    </main>
</div>

<script>
(function () {
    // Live-filter tenant cards by name. Pure DOM, no fetch — the list
    // is already on the page (6 tenants today; if it ever grows
    // beyond a few dozen we can add a server-side filter).
    var input = document.getElementById('tenant-filter');
    var cards = document.querySelectorAll('details.tenant-card');
    if (input) {
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            cards.forEach(function (card) {
                var name = card.dataset.name || '';
                card.style.display = (q === '' || name.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    // Multi-pick guard: block submission of an "Add plans" / "Comp"
    // form if zero plans are ticked. The server rejects this too,
    // but stopping it here gives instant feedback instead of a flash
    // round-trip and avoids confusing the user with "Pick a plan."
    document.querySelectorAll('form .plan-picker').forEach(function (picker) {
        var form = picker.closest('form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            var anyChecked = picker.querySelector('input[type="checkbox"]:checked');
            if (!anyChecked) {
                e.preventDefault();
                e.stopPropagation();
                // Subtle prompt — flash the picker border red briefly.
                picker.style.outline = '2px solid #dc2626';
                picker.style.outlineOffset = '4px';
                picker.style.borderRadius = '8px';
                setTimeout(function () {
                    picker.style.outline = '';
                    picker.style.outlineOffset = '';
                }, 1200);
                alert('Pick at least one plan first.');
            }
        });
    });
})();
</script>

<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
