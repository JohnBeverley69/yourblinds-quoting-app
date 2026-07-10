<?php
declare(strict_types=1);

/**
 * Go-live checklist — a super-admin pre-launch readiness page.
 *
 * Read-only. Auto-detects what it can (PayPal mode, APP_ENV, API keys, terms,
 * VAT, backup age), scans THIS account for left-over test data, and lists the
 * manual steps (incl. the PayPal sandbox → live switch). Nothing here changes
 * data; it's a window + a tick-list.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/paypal.php';

requireSuperAdmin();

$user       = current_user();
$clientId   = (int) $user['client_id'];
$pdo        = db();
$activeNav   = 'go-live';

// ── Auto-detected readiness signals ────────────────────────────────────────
// Each: ['pass'|'warn'|'fail', label, detail].
$checks = [];

// PayPal env.
$ppMode = strtolower((string) (env('PAYPAL_ENV', 'sandbox') ?? 'sandbox'));
$ppOk   = function_exists('paypal_is_configured') && paypal_is_configured();
$checks[] = $ppMode === 'live'
    ? ['pass', 'PayPal mode', 'Live' . ($ppOk ? ' — credentials present.' : ' — but credentials look missing.')]
    : ['fail', 'PayPal mode', 'SANDBOX — no real money moves. Switch to live before charging (steps below).'];

// APP_ENV.
$appEnv = strtolower((string) (env('APP_ENV', 'production') ?? 'production'));
$checks[] = in_array($appEnv, ['production', 'prod'], true)
    ? ['pass', 'App environment', 'production — errors are hidden from users.']
    : ['warn', 'App environment', "APP_ENV is '" . $appEnv . "' — set it to production so errors never show to customers."];

// API keys present.
$mapsKey = (string) (env('GOOGLE_MAPS_API_KEY', '') ?? '');
$postKey = (string) (env('POSTCODER_API_KEY', '') ?? env('GETADDRESS_API_KEY', '') ?? '');
$checks[] = $mapsKey !== ''
    ? ['pass', 'Google Maps key', 'Set — check it has billing enabled and is restricted to your domain.']
    : ['warn', 'Google Maps key', 'Not set — calendar maps/directions will be limited.'];
$checks[] = $postKey !== ''
    ? ['pass', 'Postcode lookup key', 'Set.']
    : ['warn', 'Postcode lookup key', 'Not set — address lookup will be off.'];

// Terms & Privacy + VAT + backup, for this account.
try {
    $cs = $pdo->prepare('SELECT terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1');
    $cs->execute([$clientId]);
    $csRow = $cs->fetch(PDO::FETCH_ASSOC) ?: [];
    $hasTerms = trim((string) ($csRow['terms_conditions'] ?? '')) !== '';
    $checks[] = $hasTerms
        ? ['pass', 'Terms & Conditions', 'Configured — customers accept these on a quote.']
        : ['warn', 'Terms & Conditions', 'Using the built-in default. Set your own in Settings → Legal if needed.'];
} catch (Throwable $e) { /* legal columns may be absent */ }

try {
    $cl = $pdo->prepare('SELECT vat_number, last_backup_at FROM clients WHERE id = ? LIMIT 1');
    $cl->execute([$clientId]);
    $clRow = $cl->fetch(PDO::FETCH_ASSOC) ?: [];
    $vat = trim((string) ($clRow['vat_number'] ?? ''));
    $checks[] = $vat !== ''
        ? ['pass', 'VAT number', 'Set — shows on quotes/invoices.']
        : ['warn', 'VAT number', 'Not set. Add it (Settings) if you\'re VAT registered.'];

    $lb = $clRow['last_backup_at'] ?? null;
    if ($lb && strtotime((string) $lb)) {
        $ageDays = (int) floor((time() - strtotime((string) $lb)) / 86400);
        $checks[] = [$ageDays <= 7 ? 'pass' : 'warn', 'Manual backup',
            'Last full export: ' . date('j M Y', strtotime((string) $lb)) . " ($ageDays day" . ($ageDays === 1 ? '' : 's') . ' ago). Take a fresh one before launch.'];
    } else {
        $checks[] = ['warn', 'Manual backup', 'No manual export recorded. The host backs up nightly, but take a manual one before launch (Backup & restore).'];
    }
} catch (Throwable $e) { /* columns may be absent */ }

// ── Left-over test data on THIS account ────────────────────────────────────
$scan = static function (string $sql) use ($pdo, $clientId): array {
    try { $st = $pdo->prepare($sql); $st->execute([$clientId]); return $st->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
};
$testLike = "(LOWER(name) LIKE '%test%' OR LOWER(name) LIKE '%demo%' OR LOWER(name) LIKE '%dummy%')";
$testProducts  = $scan("SELECT id, name FROM products  WHERE client_id = ? AND $testLike ORDER BY name LIMIT 50");
$testSuppliers = $scan("SELECT id, name FROM suppliers WHERE client_id = ? AND $testLike ORDER BY name LIMIT 50");
$testCustomers = $scan("SELECT id, name FROM customers WHERE client_id = ? AND $testLike ORDER BY name LIMIT 50");

// Quote funnel snapshot (so you know what's sitting there).
$qCounts = [];
try {
    $qc = $pdo->prepare('SELECT status, COUNT(*) AS n FROM quotes WHERE client_id = ? GROUP BY status');
    $qc->execute([$clientId]);
    foreach ($qc->fetchAll(PDO::FETCH_ASSOC) as $r) $qCounts[(string) $r['status']] = (int) $r['n'];
} catch (Throwable $e) { /* ignore */ }
$qTotal = array_sum($qCounts);

$badge = static function (string $s): string {
    $map = ['pass' => ['#15803d', '✓'], 'warn' => ['#b45309', '!'], 'fail' => ['#b91c1c', '✕']];
    [$c, $ch] = $map[$s] ?? ['#6b7280', '·'];
    return '<span style="display:inline-flex;align-items:center;justify-content:center;width:1.25rem;height:1.25rem;border-radius:999px;background:' . $c . ';color:#fff;font-weight:700;font-size:0.75rem;flex:none">' . $ch . '</span>';
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Go-live checklist &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .gl-row { display:flex; gap:0.65rem; align-items:flex-start; padding:0.6rem 0; border-bottom:1px solid var(--border); }
        .gl-row:last-child { border-bottom:0; }
        .gl-row .lbl { font-weight:600; color:var(--text-primary); }
        .gl-row .det { color:var(--text-muted); font-size:0.875rem; }
        .gl-card { border:1px solid var(--border); border-radius:10px; background:var(--bg-card); padding:0.5rem 1rem; }
        .gl-manual li { margin:0 0 0.5rem; line-height:1.5; }
        .gl-pill { display:inline-block; background:var(--bg-subtle-2); color:var(--text-muted); font-size:0.8125rem; border-radius:999px; padding:0.05rem 0.55rem; margin:0 0.25rem 0.25rem 0; }
        code { background:var(--bg-subtle-2); padding:0.05rem 0.35rem; border-radius:4px; font-size:0.85em; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Go-live checklist</h1>
                <p class="page-subtitle">Pre-launch readiness for <strong><?= e((string) ($user['company_name'] ?? 'this account')) ?></strong>. Read-only — nothing here changes data.</p>
            </div>
        </div>

        <!-- Auto-detected -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Automatic checks</h2>
            <div class="gl-card">
                <?php foreach ($checks as [$st, $lbl, $det]): ?>
                    <div class="gl-row">
                        <?= $badge($st) ?>
                        <div><div class="lbl"><?= e($lbl) ?></div><div class="det"><?= e($det) ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Test data -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Left-over test data (this account)</h2>
            <div class="gl-card">
                <div class="gl-row">
                    <?= $badge($testProducts ? 'warn' : 'pass') ?>
                    <div><div class="lbl">Products named test/demo/dummy</div>
                        <div class="det"><?php if ($testProducts): foreach ($testProducts as $p): ?><span class="gl-pill"><?= e((string) $p['name']) ?></span><?php endforeach; else: ?>None found.<?php endif; ?></div></div>
                </div>
                <div class="gl-row">
                    <?= $badge($testSuppliers ? 'warn' : 'pass') ?>
                    <div><div class="lbl">Suppliers named test/demo/dummy</div>
                        <div class="det"><?php if ($testSuppliers): foreach ($testSuppliers as $s): ?><span class="gl-pill"><?= e((string) $s['name']) ?></span><?php endforeach; else: ?>None found.<?php endif; ?>
                        <?php if ($testSuppliers): ?><br><span style="font-size:0.8125rem">Delete in <strong>Settings → Suppliers</strong>.</span><?php endif; ?></div></div>
                </div>
                <div class="gl-row">
                    <?= $badge($testCustomers ? 'warn' : 'pass') ?>
                    <div><div class="lbl">Customers named test/demo/dummy</div>
                        <div class="det"><?php if ($testCustomers): foreach ($testCustomers as $c): ?><span class="gl-pill"><?= e((string) $c['name']) ?></span><?php endforeach; else: ?>None found.<?php endif; ?></div></div>
                </div>
                <div class="gl-row">
                    <?= $badge('warn') ?>
                    <div><div class="lbl">Quotes &amp; orders on file: <?= (int) $qTotal ?></div>
                        <div class="det"><?php foreach ($qCounts as $st => $n): ?><span class="gl-pill"><?= e(ucfirst($st)) ?>: <?= (int) $n ?></span><?php endforeach; ?>
                        <?php if ($qTotal): ?><br><span style="font-size:0.8125rem">Review these — clear any test quotes before launch (Quote/Order history).</span><?php endif; ?></div></div>
                </div>
            </div>
            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.5rem 0 0">Name-match only (test / demo / dummy) — eyeball your products and customers lists too.</p>
        </section>

        <!-- PayPal switch -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Switch PayPal to live <?= $ppMode === 'live' ? '<span style="color:#15803d;font-size:0.8125rem">(already live)</span>' : '<span style="color:#b91c1c;font-size:0.8125rem">(currently sandbox)</span>' ?></h2>
            <div class="gl-card">
                <p style="margin:0 0 0.5rem;color:var(--text-muted);font-size:0.9375rem">Only needed if you'll charge subscriptions or take card/PayPal payments.</p>
                <ol class="gl-manual" style="margin:0;padding-left:1.2rem;color:var(--text-muted)">
                    <li>In your <strong>live</strong> PayPal developer account, create an app → copy its <strong>Client ID</strong> and <strong>Secret</strong>.</li>
                    <li>On the server's <code>.env</code> set: <code>PAYPAL_ENV=live</code>, <code>PAYPAL_CLIENT_ID=…</code>, <code>PAYPAL_SECRET=…</code> (your live values).</li>
                    <li>Recreate the plans on live: <strong>Master Admin → <a href="/master-admin/pricing.php">Pricing</a> → "Create on PayPal"</strong> for Silver / Gold.</li>
                    <li>Register the live <strong>webhook</strong> in PayPal pointing at your billing return/webhook URL, then put its id in <code>.env</code> as <code>PAYPAL_WEBHOOK_ID</code>.</li>
                    <li>Reload this page — the PayPal check above should turn green.</li>
                </ol>
                <p style="margin:0.5rem 0 0;font-size:0.8125rem;color:var(--text-faint)">Editing <code>.env</code> is a server file change — do it via Cloudways' file manager, or ask me to walk you through it.</p>
            </div>
        </section>

        <!-- Manual -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Do these by hand</h2>
            <div class="gl-card">
                <ul class="gl-manual" style="margin:0;padding-left:1.2rem">
                    <li><strong>Email test:</strong> send a real quote to your own address — confirm the PDF arrives (and isn't in spam). Check the "from" address and your domain's email setup.</li>
                    <li><strong>Full dry run:</strong> quote → accept via the public link → order → book on calendar → record a payment → export the CSV. End to end.</li>
                    <li><strong>Finish the catalogue:</strong> products priced and fabrics banded (e.g. the Sunwood / Faux Woods rebuild).</li>
                    <li><strong>Staff logins:</strong> create real user accounts with the right permissions (Users).</li>
                    <li><strong>Fresh backup</strong> right before you flip the switch (Backup &amp; restore).</li>
                    <li><strong>API keys:</strong> confirm Maps / Postcode keys are production, billed, and locked to your domain.</li>
                </ul>
            </div>
        </section>
    </main>
</div>
</body>
</html>
