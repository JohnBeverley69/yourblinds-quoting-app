<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require_once __DIR__ . '/../_partials/app_settings.php';

requireSuperAdmin();

// Global "pause emails" (testing mode) — current state for the toggle card.
$emailPaused = app_setting_on('email_paused');
// Global "public sign-up closed" — current state for its toggle card.
$signupsPaused = app_setting_on('signups_paused');

$user        = current_user();
$myClientId  = (int) $user['client_id'];
$flags       = require __DIR__ . '/../_partials/feature_flags.php';
$plans       = billing_plans();

// Reverse-lookup: which plan grants each feature flag, and at what
// monthly price? Used in the feature-table header so the master admin
// sees price + subscription-status at a glance.
//
// Features not currently part of any plan show as "Manual" — they
// can still be toggled here but aren't subscribable via PayPal yet.
// Adding a new paid feature is two steps:
//   1. Add the column + label to _partials/feature_flags.php
//   2. Add (or extend) a plan in _partials/billing_plans.php listing
//      the new flag in its 'features' array
// The new feature then shows up here AND on the tenant Billing page.
$flagPricing = [];   // ['feature_accounts' => ['plan' => 'accounts', 'price' => 10.00], ...]
foreach ($flags as $col => $_label) {
    $flagPricing[$col] = null;   // null = no plan grants this yet
    foreach ($plans as $planCode => $plan) {
        if (!in_array($col, $plan['features'] ?? [], true)) continue;
        $flagPricing[$col] = [
            'plan'  => $planCode,
            'price' => (float) ($plan['price_gbp_monthly'] ?? 0),
            'name'  => (string) ($plan['name'] ?? $planCode),
        ];
        break;
    }
}

// Build a SELECT that pulls every flag column dynamically. Column names come
// from a server-side allowlist (the $flags array) — never from user input —
// so it's safe to interpolate directly.
$flagCols = implode(",\n            ",
    array_map(static fn ($k) => "COALESCE(s.$k, 0) AS $k", array_keys($flags))
);
$sql = "SELECT c.id, c.company_name, c.active,
            $flagCols,
            (SELECT COUNT(*) FROM client_users u WHERE u.client_id = c.id) AS user_count,
            (SELECT COUNT(*) FROM client_users u WHERE u.client_id = c.id AND u.is_super_admin = 1) AS super_count
       FROM clients c
       LEFT JOIN client_settings s ON s.client_id = c.id
   ORDER BY c.company_name";
$rows = db()->query($sql)->fetchAll();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeNav = 'master-admin';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Admin &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .feature-table th { white-space: nowrap; }
        .feature-table td.toggle { width: 1%; text-align: center; }
        .feature-table input[type="checkbox"] { width: 18px; height: 18px; }
        /* Price stamp under each paid-feature header. Subscription-
           managed features get a £/mo figure; manual-only features
           get a small "Manual" tag in grey to flag they're not
           billable via PayPal yet. */
        .feature-table .feature-price {
            font-size: 0.6875rem;
            color: #065f46;
            font-weight: 600;
            margin-top: 0.125rem;
            text-transform: none;
            letter-spacing: 0;
        }
        .feature-table .feature-price .unit {
            color: var(--text-faint); font-weight: 500;
        }
        .feature-table .feature-price .manual {
            color: var(--text-faint); font-weight: 500; font-style: italic;
        }
        .feature-table tr.is-inactive td:first-child { color: var(--text-faint); }
        .feature-table tr.is-inactive .inactive-tag {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--text-faint);
            background: var(--bg-subtle-2);
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .super-tag {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.0625rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: #fff;
            background: #1f3b5b;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .row-meta { color: var(--text-faint); font-size: 0.8125rem; margin-top: 0.125rem; }
        .row-actions { white-space: nowrap; text-align: right; }
        .row-actions form { margin: 0; display: inline; }
        .row-actions button {
            font-size: 0.875rem; color: #b91c1c; background: transparent;
            border: 0; cursor: pointer; padding: 0;
        }
        .row-actions button:hover { text-decoration: underline; }
        .row-actions .muted {
            font-size: 0.8125rem; color: var(--text-faint);
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Master Admin</h1>
                <p class="page-subtitle">
                    Per-client feature flags + tenant management. Tick to enable an add-on for a client.
                </p>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <a href="/master-admin/go-live.php" class="btn btn-secondary">Go-live checklist</a>
                <a href="/master-admin/pricing.php" class="btn btn-secondary">Pricing</a>
                <a href="/master-admin/subscriptions.php" class="btn btn-secondary">Subscriptions</a>
                <a href="/master-admin/backup.php" class="btn btn-secondary">Backup &amp; restore</a>
                <a href="/master-admin/new-client.php" class="btn btn-primary">+ New client</a>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <!-- Testing mode: pause ALL outgoing email site-wide. For letting a QA
             tester poke the live site without emailing real suppliers/customers. -->
        <section class="section" style="<?= $emailPaused ? 'border:2px solid #b91c1c;' : '' ?>">
            <h2 class="section-title" style="margin:0 0 0.5rem">Testing mode</h2>
            <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 1rem;line-height:1.5;max-width:46rem">
                Tick this to <strong>pause every outgoing email</strong> across the whole site —
                quote emails, supplier orders, password resets, the lot. Use it while a tester is
                trying things out, so they can't accidentally email a real customer or supplier.
                <strong>Remember to untick it before you go live.</strong>
            </p>
            <?php if ($emailPaused): ?>
                <p style="margin:0 0 1rem;font-weight:600;color:#b91c1c">
                    📧 Emails are currently PAUSED — nothing is being sent.
                </p>
            <?php endif; ?>
            <form method="post" action="/master-admin/email-toggle.php">
                <?= csrf_field() ?>
                <label style="display:flex;align-items:center;gap:0.6rem;margin:0 0 1rem;font-size:0.95rem;cursor:pointer">
                    <input type="checkbox" name="email_paused" value="1" <?= $emailPaused ? 'checked' : '' ?>
                           style="width:1.15rem;height:1.15rem">
                    Pause all outgoing emails (testing mode)
                </label>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </section>

        <!-- Public sign-up: open or close the self-service registration form.
             Close it while trial tenants are being cleared out; reopen when
             real clients should be able to register again. -->
        <section class="section" style="<?= $signupsPaused ? 'border:2px solid #b91c1c;' : '' ?>">
            <h2 class="section-title" style="margin:0 0 0.5rem">Public sign-up</h2>
            <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 1rem;line-height:1.5;max-width:46rem">
                Tick this to <strong>close the public sign-up form</strong> — the
                <code>/auth/signup.php</code> page stops creating accounts and the
                "Create an account" link disappears from the login page. Existing
                tenants and their logins are unaffected; this only turns off new
                self-service registrations. Untick it to reopen sign-ups.
            </p>
            <?php if ($signupsPaused): ?>
                <p style="margin:0 0 1rem;font-weight:600;color:#b91c1c">
                    🚫 Public sign-up is currently CLOSED — no new accounts can be created.
                </p>
            <?php endif; ?>
            <form method="post" action="/master-admin/signup-toggle.php">
                <?= csrf_field() ?>
                <label style="display:flex;align-items:center;gap:0.6rem;margin:0 0 1rem;font-size:0.95rem;cursor:pointer">
                    <input type="checkbox" name="signups_paused" value="1" <?= $signupsPaused ? 'checked' : '' ?>
                           style="width:1.15rem;height:1.15rem">
                    Close public sign-up (no new self-service accounts)
                </label>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </section>

        <section class="section">
            <?php
                // Tier ladder (live prices from plan_pricing where available).
                $tierBits = [];
                foreach (billing_plans() as $tCode => $tPlan) {
                    $tPrice = function_exists('billing_plan_price')
                        ? billing_plan_price($tCode)
                        : (float) ($tPlan['price_gbp_monthly'] ?? 0);
                    $tierBits[] = ($tPlan['name'] ?? $tCode)
                        . ($tPrice > 0 ? ' £' . rtrim(rtrim(number_format($tPrice, 2), '0'), '.') : ' (free)');
                }
            ?>
            <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 1rem;line-height:1.5">
                Billing is now <strong>tiers</strong>: <?= e(implode(' → ', $tierBits)) ?> — each tier
                includes the ones below. The label under each feature shows the <strong>tier that unlocks it</strong>.
                Tenants subscribe to a tier on their
                <a href="/billing/index.php" style="color:#1f3b5b">Billing</a> page; manage state on
                <a href="/master-admin/subscriptions.php" style="color:#1f3b5b">Subscriptions</a> and prices on
                <a href="/master-admin/pricing.php" style="color:#1f3b5b">Pricing</a>.
                For <strong>free access</strong>, use a
                <a href="/master-admin/pricing.php#comps" style="color:#1f3b5b">comp override</a>
                — it survives cancellations and is the documented audit trail. The checkboxes below are a
                manual per-feature override only.
            </p>
            <!--
                The feature-flags form is a sibling of the table, not its
                ancestor. Each <input> in the table uses form="flags-form"
                to attach itself to the form by id. This lets us put a
                separate delete-client form inside each row without nesting
                <form> elements (which is invalid HTML).
            -->
            <form id="flags-form" method="post" action="/master-admin/save.php" style="display:none">
                <?= csrf_field() ?>
            </form>

            <div class="table-wrap">
                <table class="table feature-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <?php foreach ($flags as $col => $label): $p = $flagPricing[$col] ?? null; ?>
                                <th class="toggle">
                                    <?= e($label) ?>
                                    <div class="feature-price">
                                        <?php if ($p): ?>
                                            <span class="unit">in</span> <?= e($p['name']) ?>
                                        <?php else: ?>
                                            <span class="manual">Manual</span>
                                        <?php endif; ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="<?= count($flags) + 2 ?>" class="table-empty">No clients yet.</td></tr>
                        <?php else: foreach ($rows as $r):
                            $isSelf  = (int) $r['id']          === $myClientId;
                            $isSuper = (int) $r['super_count'] > 0;
                        ?>
                            <tr class="<?= ((int) $r['active']) === 1 ? '' : 'is-inactive' ?>">
                                <td>
                                    <strong><?= e((string) $r['company_name']) ?></strong>
                                    <?php if ($isSuper): ?>
                                        <span class="super-tag">Master</span>
                                    <?php endif; ?>
                                    <?php if ((int) $r['active'] !== 1): ?>
                                        <span class="inactive-tag">Inactive</span>
                                    <?php endif; ?>
                                    <div class="row-meta">
                                        <?= (int) $r['user_count'] ?> user<?= (int) $r['user_count'] === 1 ? '' : 's' ?>
                                    </div>
                                </td>
                                <?php foreach ($flags as $col => $label): ?>
                                    <td class="toggle">
                                        <input type="checkbox"
                                               form="flags-form"
                                               name="flags[<?= e($col) ?>][<?= (int) $r['id'] ?>]"
                                               value="1"
                                               <?= ((int) $r[$col]) === 1 ? 'checked' : '' ?>
                                               aria-label="<?= e($label) ?> for <?= e((string) $r['company_name']) ?>">
                                    </td>
                                <?php endforeach; ?>
                                <td class="row-actions">
                                    <?php if ($isSelf): ?>
                                        <span class="muted" title="You're logged in as a user of this client.">
                                            (your client)
                                        </span>
                                    <?php elseif ($isSuper): ?>
                                        <span class="muted" title="Has a master admin user — clear the super-admin flag first.">
                                            (protected)
                                        </span>
                                    <?php else: ?>
                                        <form method="post" action="/master-admin/delete-client.php"
                                              data-confirm="Delete <?= e((string) $r['company_name']) ?>? This is permanent. ALL of the client's data is removed: users, customers, quotes, products, fabrics, systems, options, choices, price tables and rows. No undo.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="client_id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" form="flags-form" class="btn btn-primary">Save flag changes</button>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
