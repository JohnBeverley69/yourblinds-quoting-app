<?php
declare(strict_types=1);

/**
 * Super-admin: edit default plan pricing + manage per-client comps.
 *
 * This is the central source of truth for "how much does the
 * Accounts add-on cost?" Editing the price here:
 *   1. Updates plan_pricing.price_gbp_monthly so the Billing page,
 *      Master Admin index, and any future paid feature column reflect
 *      the new number immediately.
 *   2. (If the plan has a paypal_plan_id) calls PayPal's
 *      /update-pricing-schemes API so existing subscribers start
 *      paying the new price at their NEXT billing cycle automatically.
 *      New subscribers get the new price straight away.
 *
 * Per-client comp overrides let the super-admin grant a specific tenant
 * free access to a paid plan (testing, partner deals, "you broke it,
 * have a month on us"). A comp'd tenant gets the plan's feature flags
 * without any PayPal billing — they don't even need a PayPal account.
 *
 * Phase 1 of comps only supports override_type='comp' (= free). Per-
 * client custom prices are out of scope — PayPal doesn't make per-
 * customer-pricing easy, and 95% of "I want to charge this one less"
 * cases are actually "I want to give it to them free."
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireSuperAdmin();

// The sidebar partial reads $user to decide which entries to show
// (admin / super-admin / staff gates). Without this the menu
// collapses to just "Calendar" + "My Schedule" because every
// permission check evaluates against null.
$user = current_user();

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---- POST handlers --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $pdo = db();

    try {
        switch ($action) {

            // Edit default price + PayPal Plan ID + notes for a plan.
            // If the price changed AND a PayPal Plan ID is set, push
            // the new price up to PayPal so existing subscribers'
            // next-billing-cycle uses it. If the API call fails we
            // still save locally and flash a warning — the admin can
            // retry later, and the next charge would mismatch anyway
            // if we silently rolled back.
            case 'save_price': {
                $code     = (string) ($_POST['plan_code'] ?? '');
                $price    = (float)  ($_POST['price']     ?? 0);
                $paypalId = trim((string) ($_POST['paypal_plan_id'] ?? '')) ?: null;
                $notes    = trim((string) ($_POST['notes'] ?? '')) ?: null;

                if ($code === '' || !isset(billing_plans()[$code])) {
                    throw new RuntimeException('Unknown plan code: ' . $code);
                }
                if ($price < 0) {
                    throw new RuntimeException('Price cannot be negative.');
                }

                // What was the old price/plan id? Need this to decide
                // whether to call PayPal.
                $st = $pdo->prepare(
                    'SELECT price_gbp_monthly, paypal_plan_id
                       FROM plan_pricing WHERE plan_code = ? LIMIT 1'
                );
                $st->execute([$code]);
                $old = $st->fetch() ?: ['price_gbp_monthly' => 0, 'paypal_plan_id' => null];
                $oldPrice = (float) $old['price_gbp_monthly'];
                $oldId    = (string) ($old['paypal_plan_id'] ?? '');

                // UPSERT — the migration seeds rows for every plan in
                // the registry, but be tolerant of brand-new plans.
                $pdo->prepare(
                    'INSERT INTO plan_pricing
                       (plan_code, price_gbp_monthly, paypal_plan_id, notes)
                       VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                       price_gbp_monthly = VALUES(price_gbp_monthly),
                       paypal_plan_id    = VALUES(paypal_plan_id),
                       notes             = VALUES(notes)'
                )->execute([$code, $price, $paypalId, $notes]);

                $payPalWarn = null;
                $priceChanged = abs($price - $oldPrice) > 0.001;
                if ($priceChanged && $paypalId !== null && $paypalId !== '') {
                    try {
                        paypal_update_plan_price($paypalId, $price);
                    } catch (Throwable $e) {
                        $payPalWarn = 'Local price saved, but PayPal sync failed: '
                            . $e->getMessage()
                            . ' — existing subscribers will keep paying the OLD price '
                            . 'until you retry. Click Save again once the issue is fixed.';
                        error_log('paypal_update_plan_price failed: ' . $e->getMessage());
                    }
                }

                if ($payPalWarn !== null) {
                    $_SESSION['flash_error'] = $payPalWarn;
                } else {
                    $msg = 'Saved ' . $code . ' at £' . number_format($price, 2) . '/mo.';
                    if ($priceChanged && $paypalId !== null && $paypalId !== '') {
                        $msg .= ' Existing PayPal subscribers will be charged the new '
                              . 'amount from their next billing cycle.';
                    }
                    $_SESSION['flash_success'] = $msg;
                }
                break;
            }

            // Create a brand-new PayPal Product+Plan for a plan_code
            // that doesn't have one yet. Useful when adding a NEW paid
            // plan to the static registry — instead of running a
            // separate setup_paypal_plan.php, do it from the UI.
            case 'create_paypal_plan': {
                $code = (string) ($_POST['plan_code'] ?? '');
                $plan = billing_plan($code);
                if (!$plan) throw new RuntimeException('Unknown plan code.');

                $price = (float) ($plan['price_gbp_monthly'] ?? 0);
                if ($price <= 0) {
                    throw new RuntimeException('Set a non-zero price first, then click Create.');
                }
                if (!paypal_is_configured()) {
                    throw new RuntimeException('PayPal API credentials not configured in .env.');
                }

                $newId = paypal_create_plan(
                    $code,
                    (string) ($plan['name'] ?? $code),
                    (string) ($plan['description'] ?? ''),
                    $price
                );

                $pdo->prepare(
                    'UPDATE plan_pricing SET paypal_plan_id = ? WHERE plan_code = ?'
                )->execute([$newId, $code]);

                $_SESSION['flash_success'] = 'Created PayPal Plan ' . $newId . ' for ' . $code . '. '
                    . 'Tenants can now subscribe.';
                break;
            }

            // Add or update a comp / trial override for this client.
            //   override_type='comp'   => free forever (NULL expires_at)
            //   override_type='trial'  => free until expires_at
            // The form posts both with the same handler — the supplied
            // expires_at field decides which type we save.
            case 'add_comp':
            case 'add_trial':
            case 'extend_trial': {
                $clientId = (int)    ($_POST['client_id'] ?? 0);
                $code     = (string) ($_POST['plan_code']  ?? '');
                $notes    = trim((string) ($_POST['notes'] ?? '')) ?: null;
                $expires  = trim((string) ($_POST['expires_at'] ?? '')) ?: null;

                if ($clientId <= 0)             throw new RuntimeException('Pick a client.');
                if (!billing_plan($code))       throw new RuntimeException('Pick a plan.');
                if ($code === 'free')           throw new RuntimeException('Free plan doesn\'t need a comp.');

                // Validate expiry date if supplied; reject malformed
                // strings outright rather than silently storing NULL.
                if ($expires !== null) {
                    if (DateTimeImmutable::createFromFormat('!Y-m-d', $expires) === false) {
                        throw new RuntimeException('Bad expiry date: ' . $expires);
                    }
                }

                $type = ($action === 'add_comp') ? 'comp' : 'trial';
                if ($type === 'trial' && $expires === null) {
                    throw new RuntimeException('Trial needs an expiry date.');
                }
                if ($type === 'comp') {
                    // Comps are forever — null out any inherited expiry.
                    $expires = null;
                }

                // Upsert — reactivates if a soft-deleted row exists.
                try {
                    $pdo->prepare(
                        "INSERT INTO client_plan_overrides
                           (client_id, plan_code, override_type, expires_at,
                            notes, active)
                           VALUES (?, ?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE
                           override_type = VALUES(override_type),
                           expires_at    = VALUES(expires_at),
                           notes         = VALUES(notes),
                           active        = 1"
                    )->execute([$clientId, $code, $type, $expires, $notes]);
                } catch (Throwable $colErr) {
                    // expires_at column missing (migrate_trials.php not
                    // run) — degrade to comp.
                    $pdo->prepare(
                        "INSERT INTO client_plan_overrides
                           (client_id, plan_code, override_type, notes, active)
                           VALUES (?, ?, 'comp', ?, 1)
                         ON DUPLICATE KEY UPDATE
                           override_type = 'comp',
                           notes         = VALUES(notes),
                           active        = 1"
                    )->execute([$clientId, $code, $notes]);
                }

                billing_sync_feature_flags_force($clientId);

                if ($type === 'trial') {
                    $_SESSION['flash_success'] = $action === 'extend_trial'
                        ? 'Trial updated — expires ' . $expires . '.'
                        : 'Trial added — paid features on for this client until ' . $expires . '.';
                } else {
                    $_SESSION['flash_success'] = 'Comp added — paid features turned on indefinitely.';
                }
                break;
            }

            case 'remove_comp': {
                $id = (int) ($_POST['override_id'] ?? 0);
                if ($id <= 0) throw new RuntimeException('Missing override id.');

                $st = $pdo->prepare('SELECT client_id FROM client_plan_overrides WHERE id = ?');
                $st->execute([$id]);
                $cid = (int) ($st->fetchColumn() ?: 0);

                $pdo->prepare('DELETE FROM client_plan_overrides WHERE id = ?')->execute([$id]);

                if ($cid > 0) {
                    billing_sync_feature_flags_force($cid);
                }

                $_SESSION['flash_success'] = 'Override removed. Paid features turned off — '
                    . 'unless the client has an active PayPal subscription, in which case '
                    . 'they remain on.';
                break;
            }

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    header('Location: /master-admin/pricing.php');
    exit;
}

// ---- Page data ------------------------------------------------------
$plans       = billing_plans();
$pdo         = db();
$paypalReady = paypal_is_configured();

// Live plan_pricing rows (so we can show the notes/updated_at audit
// info per plan).
$priceRows = [];
try {
    $rs = $pdo->query(
        'SELECT plan_code, price_gbp_monthly, paypal_plan_id, notes, updated_at
           FROM plan_pricing'
    )->fetchAll();
    foreach ($rs as $r) $priceRows[(string) $r['plan_code']] = $r;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'plan_pricing table missing — run /migrate_plan_pricing.php first.';
    header('Location: /master-admin/index.php');
    exit;
}

// All clients (for the comp dropdown).
$clients = $pdo->query(
    'SELECT id, company_name FROM clients WHERE active = 1 ORDER BY company_name'
)->fetchAll();

// Current overrides (comps + trials). Includes expired trials so the
// admin can see them with a "lapsed" badge — they're harmless (the
// helper filters them out for entitlement) but useful for audit.
$comps = [];
$hasTrialsCol = false;
try {
    $comps = $pdo->query(
        "SELECT o.id, o.client_id, o.plan_code, o.override_type,
                o.expires_at, o.notes, o.active, o.created_at,
                c.company_name
           FROM client_plan_overrides o
           JOIN clients c ON c.id = o.client_id
          WHERE o.active = 1
          ORDER BY c.company_name, o.plan_code"
    )->fetchAll();
    $hasTrialsCol = true;
} catch (Throwable $e) {
    // expires_at column missing — fall back.
    try {
        $comps = $pdo->query(
            "SELECT o.id, o.client_id, o.plan_code, o.override_type,
                    NULL AS expires_at, o.notes, o.active, o.created_at,
                    c.company_name
               FROM client_plan_overrides o
               JOIN clients c ON c.id = o.client_id
              WHERE o.active = 1
              ORDER BY c.company_name, o.plan_code"
        )->fetchAll();
    } catch (Throwable $e2) {
        // table missing — migration not run
    }
}

// Default expiry date for new trials = today + 30 days.
$defaultTrialExpiry = date('Y-m-d', strtotime('+30 days'));

$activeNav = 'pricing';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pricing &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .price-card {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
            padding: 1.125rem 1.25rem; margin-bottom: 1rem;
        }
        .price-card h3 {
            margin: 0 0 0.25rem; color: var(--text-body); font-size: 1.125rem;
        }
        .price-card .pc-desc { color: var(--text-muted); font-size: 0.875rem; margin: 0 0 0.875rem; }
        .price-card form {
            display: grid;
            grid-template-columns: 8rem 1fr 2fr auto;
            gap: 0.625rem 0.75rem; align-items: end;
        }
        .price-card .pc-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .price-card .pc-field label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .price-card .pc-field input {
            padding: 0.4375rem 0.5625rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; background: var(--bg-input);
        }
        .price-card .pc-field code {
            display: block; padding: 0.4375rem 0.5625rem;
            background: var(--bg-subtle-2); border-radius: 6px;
            font-size: 0.8125rem; color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        .price-card .pc-meta {
            color: var(--text-faint); font-size: 0.75rem; margin-top: 0.625rem;
        }
        .price-card .pc-create {
            margin-top: 0.5rem; padding: 0.625rem 0.875rem;
            background: #fef3c7; border: 1px solid #fcd34d;
            border-radius: 8px; font-size: 0.8125rem; color: #78350f;
            display: flex; gap: 0.625rem; align-items: center; flex-wrap: wrap;
        }
        .price-card .pc-create form { display: inline; grid-template-columns: none; gap: 0; }
        .price-card .pc-create button {
            background: #f59e0b; color: #fff; border: 0;
            padding: 0.3125rem 0.75rem; border-radius: 6px;
            font-weight: 600; font-size: 0.8125rem; cursor: pointer;
        }
        @media (max-width: 800px) {
            .price-card form { grid-template-columns: 1fr 1fr; }
            .price-card form > .pc-submit { grid-column: 1 / -1; justify-self: end; }
        }
        .comp-row {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px;
            padding: 0.5rem 0.875rem; margin-bottom: 0.375rem;
            display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;
        }
        .comp-row .c-name { font-weight: 600; color: var(--text-primary); }
        .comp-row .c-plan {
            background: #d1fae5; color: #065f46; padding: 0.125rem 0.5rem;
            border-radius: 999px; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .comp-row .c-plan-trial {
            background: #fef3c7; color: #78350f;
        }
        .comp-row .c-notes { color: var(--text-faint); font-size: 0.8125rem; flex: 1 1 12rem; }
        .comp-row form { margin: 0; }
        .comp-row button {
            background: transparent; color: #b91c1c; border: 0; cursor: pointer;
            font-size: 0.8125rem;
        }
        .comp-row button:hover { text-decoration: underline; }
        .add-comp {
            background: var(--bg-subtle); border: 1px dashed var(--border-strong);
            border-radius: 8px; padding: 0.875rem 1rem; margin-top: 0.75rem;
        }
        .add-comp form {
            display: grid;
            grid-template-columns: 2fr 1fr 3fr auto;
            gap: 0.625rem 0.75rem; align-items: end;
        }
        .add-comp .pc-field { display: flex; flex-direction: column; gap: 0.1875rem; }
        .add-comp label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .add-comp select, .add-comp input {
            padding: 0.4375rem 0.5625rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit; background: var(--bg-input);
        }
        @media (max-width: 800px) {
            .add-comp form { grid-template-columns: 1fr 1fr; }
            .add-comp form > .pc-submit { grid-column: 1 / -1; justify-self: end; }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Pricing</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; Default plan prices, free trials, and per-client comp overrides.
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
                <h2 class="section-title">Default plan prices</h2>
            </div>
            <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 1rem;line-height:1.5">
                Edit the monthly fee for each plan. When you change a price on a
                plan that's wired to PayPal, existing subscribers automatically
                get the new rate from their <strong>next billing cycle</strong> —
                PayPal handles the prorating-and-notification. New subscribers
                get the new price straight away.
                <?php if (!$paypalReady): ?>
                    <br><span style="color:#b91c1c">
                        <strong>Note:</strong> PayPal API credentials aren't set in <code>.env</code> —
                        price changes will save locally but won't propagate to PayPal.
                    </span>
                <?php endif; ?>
            </p>

            <?php foreach ($plans as $code => $plan):
                $isFree  = ($code === 'free');   // Bronze — the free base tier every account has
                $row     = $priceRows[$code] ?? null;
                $price   = (float) ($row['price_gbp_monthly'] ?? ($plan['price_gbp_monthly'] ?? 0));
                $ppId    = (string) ($row['paypal_plan_id']    ?? '');
                $notes   = (string) ($row['notes']             ?? '');
                $updated = (string) ($row['updated_at']        ?? '');
            ?>
                <div class="price-card">
                    <h3><?= e($plan['name']) ?><?php if ($isFree): ?> <span style="font-weight:400;font-size:0.8125rem;color:var(--text-faint)">— free base tier</span><?php endif; ?></h3>
                    <div class="pc-desc"><?= e($plan['description']) ?></div>
                    <?php if ($isFree): ?>
                        <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.25rem 0 0.5rem;line-height:1.5">
                            Every account has Bronze. It's <strong>free</strong> and isn't billed through PayPal, so you can
                            record a price here for future use &mdash; but it won't charge anyone until Bronze is wired up
                            for billing.
                        </p>
                    <?php endif; ?>

                    <form method="post" action="/master-admin/pricing.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_price">
                        <input type="hidden" name="plan_code" value="<?= e($code) ?>">

                        <div class="pc-field">
                            <label for="price-<?= e($code) ?>">Price (£/month)</label>
                            <input id="price-<?= e($code) ?>" name="price" type="number"
                                   step="0.01" min="0" required
                                   value="<?= e(number_format($price, 2, '.', '')) ?>">
                        </div>

                        <?php if (!$isFree): ?>
                        <div class="pc-field">
                            <label for="ppid-<?= e($code) ?>">PayPal Plan ID</label>
                            <input id="ppid-<?= e($code) ?>" name="paypal_plan_id" type="text"
                                   value="<?= e($ppId) ?>"
                                   placeholder="P-XXXXXXXXX (auto-filled if you create below)">
                        </div>
                        <?php endif; ?>

                        <div class="pc-field">
                            <label for="notes-<?= e($code) ?>">Internal notes</label>
                            <input id="notes-<?= e($code) ?>" name="notes" type="text"
                                   maxlength="500" value="<?= e($notes) ?>"
                                   placeholder="e.g. raised from £8 → £10 on 14 May 2026">
                        </div>

                        <div class="pc-submit">
                            <button type="submit" class="btn btn-primary"
                                    style="padding:0.4375rem 1rem;font-size:0.875rem">
                                Save
                            </button>
                        </div>
                    </form>

                    <?php if (!$isFree && $ppId === '' && $paypalReady): ?>
                        <div class="pc-create">
                            <span>
                                No PayPal Plan attached. Tenants can't subscribe to this plan via PayPal yet.
                            </span>
                            <form method="post" action="/master-admin/pricing.php"
                                  data-confirm="Create a new Product + Plan on PayPal for <?= e($plan['name']) ?> at £<?= number_format($price, 2) ?>/month?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="create_paypal_plan">
                                <input type="hidden" name="plan_code" value="<?= e($code) ?>">
                                <button type="submit">Create on PayPal &raquo;</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($updated): ?>
                        <div class="pc-meta">
                            Last updated <?= e(date('j M Y, H:i', strtotime($updated))) ?>
                            <?php if ($notes !== ''): ?>
                                &middot; <em><?= e($notes) ?></em>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="section" id="comps">
            <div class="section-header">
                <h2 class="section-title">Comps &amp; trials</h2>
            </div>
            <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 1rem;line-height:1.5">
                Give a specific tenant free access to a paid plan — no PayPal
                billing, just the plan's feature flags turned on. Two flavours:
                <strong>trials</strong> have an expiry date (tenant sees a
                countdown), <strong>comps</strong> are free forever.
                Newly-created tenants automatically get a 30-day trial across
                every paid add-on; you'll see those rows here. Trials can be
                extended, converted to a permanent comp, or revoked early.
                Removing an override turns the paid features off
                <em>(unless the tenant has an active PayPal subscription)</em>.
            </p>

            <?php if ($comps): ?>
                <?php foreach ($comps as $c):
                    $plan      = billing_plan((string) $c['plan_code']);
                    $type      = (string) ($c['override_type'] ?? 'comp');
                    $expires   = (string) ($c['expires_at'] ?? '');
                    $isExpired = $type === 'trial' && $expires !== '' && strtotime($expires) < strtotime('today');
                    $daysLeft  = $type === 'trial' && $expires !== ''
                        ? (int) ceil((strtotime($expires) - strtotime('today')) / 86400)
                        : null;
                ?>
                    <div class="comp-row">
                        <span class="c-name"><?= e((string) $c['company_name']) ?></span>
                        <?php if ($type === 'trial'): ?>
                            <span class="c-plan c-plan-trial">
                                <?= e($plan['name'] ?? $c['plan_code']) ?>
                                &mdash; trial
                                <?php if ($isExpired): ?>
                                    (expired <?= e(date('j M Y', strtotime($expires))) ?>)
                                <?php elseif ($daysLeft !== null && $daysLeft <= 0): ?>
                                    (expires today)
                                <?php elseif ($daysLeft !== null): ?>
                                    (<?= $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?> left)
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="c-plan">
                                <?= e($plan['name'] ?? $c['plan_code']) ?> &mdash; comp
                            </span>
                        <?php endif; ?>

                        <span class="c-notes">
                            <?= $c['notes'] ? e((string) $c['notes']) : '<span style="color:var(--text-faint)">no notes</span>' ?>
                        </span>

                        <?php if ($type === 'trial' && $hasTrialsCol): ?>
                            <!-- Quick extend / convert form for trial rows -->
                            <form method="post" action="/master-admin/pricing.php"
                                  style="display:flex;gap:0.25rem;align-items:center;margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="extend_trial">
                                <input type="hidden" name="client_id" value="<?= (int) $c['client_id'] ?>">
                                <input type="hidden" name="plan_code" value="<?= e((string) $c['plan_code']) ?>">
                                <input type="hidden" name="notes" value="<?= e((string) ($c['notes'] ?? '')) ?>">
                                <input type="date" name="expires_at"
                                       value="<?= e($expires ?: $defaultTrialExpiry) ?>"
                                       style="padding:0.25rem 0.375rem;border:1px solid var(--border-strong);
                                              border-radius:4px;font-size:0.8125rem">
                                <button type="submit" style="color:var(--brand)">Save</button>
                            </form>
                            <form method="post" action="/master-admin/pricing.php"
                                  data-confirm="Convert this trial to a permanent comp (free forever) for <?= e((string) $c['company_name']) ?>?"
                                  style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_comp">
                                <input type="hidden" name="client_id" value="<?= (int) $c['client_id'] ?>">
                                <input type="hidden" name="plan_code" value="<?= e((string) $c['plan_code']) ?>">
                                <input type="hidden" name="notes" value="<?= e((string) ($c['notes'] ?? '')) ?>">
                                <button type="submit" style="color:#5b21b6">Convert to comp</button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="/master-admin/pricing.php"
                              data-confirm="Remove the <?= e($type) ?> for <?= e((string) $c['company_name']) ?>? Paid features will turn off (unless they have a separate active subscription).">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_comp">
                            <input type="hidden" name="override_id" value="<?= (int) $c['id'] ?>">
                            <button type="submit">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--text-faint);font-style:italic;margin:0 0 0.875rem">
                    No overrides in place yet. Add one below.
                </p>
            <?php endif; ?>

            <!-- Add a trial -->
            <div class="add-comp">
                <h3 style="margin:0 0 0.5rem;font-size:0.9375rem;color:#78350f">
                    🎁 Add a free trial
                </h3>
                <form method="post" action="/master-admin/pricing.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_trial">

                    <div class="pc-field">
                        <label for="trial-client">Client</label>
                        <select id="trial-client" name="client_id" required>
                            <option value="">— pick a client —</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= (int) $cl['id'] ?>">
                                    <?= e((string) $cl['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label for="trial-plan">Plan</label>
                        <select id="trial-plan" name="plan_code" required>
                            <?php foreach ($plans as $code => $p):
                                if ($code === 'free') continue; ?>
                                <option value="<?= e($code) ?>"><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label for="trial-expires">Expires</label>
                        <input id="trial-expires" name="expires_at" type="date"
                               required value="<?= e($defaultTrialExpiry) ?>">
                    </div>

                    <div class="pc-field" style="grid-column:1/-1">
                        <label for="trial-notes">Internal notes (why)</label>
                        <input id="trial-notes" name="notes" type="text" maxlength="500"
                               placeholder="e.g. extended trial for onboarding, partner pilot">
                    </div>

                    <div class="pc-submit" style="grid-column:1/-1;justify-self:end">
                        <button type="submit" class="btn btn-primary"
                                style="padding:0.4375rem 1rem;font-size:0.875rem">
                            Add trial
                        </button>
                    </div>
                </form>
            </div>

            <!-- Add a forever-comp -->
            <div class="add-comp" style="margin-top:0.625rem">
                <h3 style="margin:0 0 0.5rem;font-size:0.9375rem;color:#5b21b6">
                    🎁 Add a permanent comp (free forever)
                </h3>
                <form method="post" action="/master-admin/pricing.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_comp">

                    <div class="pc-field">
                        <label for="comp-client">Client</label>
                        <select id="comp-client" name="client_id" required>
                            <option value="">— pick a client —</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= (int) $cl['id'] ?>">
                                    <?= e((string) $cl['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label for="comp-plan">Plan</label>
                        <select id="comp-plan" name="plan_code" required>
                            <?php foreach ($plans as $code => $p):
                                if ($code === 'free') continue; ?>
                                <option value="<?= e($code) ?>"><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="pc-field">
                        <label for="comp-notes">Internal notes (why)</label>
                        <input id="comp-notes" name="notes" type="text" maxlength="500"
                               placeholder="e.g. partner deal, internal account">
                    </div>

                    <div class="pc-submit">
                        <button type="submit" class="btn btn-primary"
                                style="padding:0.4375rem 1rem;font-size:0.875rem">
                            Add comp
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
