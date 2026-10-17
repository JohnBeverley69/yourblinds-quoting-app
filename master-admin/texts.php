<?php
declare(strict_types=1);

/**
 * Master Admin: customer texts (SMS).
 *
 *  - Connection to The SMS Works (the platform's one account, paid by
 *    YourBlinds): paste the API token here. Never echoed back.
 *  - The platform default monthly limit, and each client's own limit
 *    (blank = default). The limit is a safety net, not an allowance.
 *  - This month's usage per client, and the latest texts platform-wide.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/sms.php';

requireSuperAdmin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'provider') {
            $token = trim((string) ($_POST['smsworks_token'] ?? ''));
            // Blank keeps the stored token (the box always renders empty).
            if ($token !== '') pc_set('SMSWORKS_TOKEN', $token);
            if (!empty($_POST['clear_token'])) pc_set('SMSWORKS_TOKEN', '');
            $def = (int) ($_POST['default_limit'] ?? 0);
            if ($def > 0) pc_set('SMS_DEFAULT_MONTHLY_LIMIT', (string) $def);
            sms_webhook_key();   // make sure the delivery-report key exists
            $_SESSION['flash_success'] = 'Text settings saved.';
        } elseif ($action === 'limits') {
            $limits = is_array($_POST['limit'] ?? null) ? $_POST['limit'] : [];
            $ensure = $pdo->prepare('INSERT IGNORE INTO client_settings (client_id) VALUES (?)');
            $upd    = $pdo->prepare('UPDATE client_settings SET sms_monthly_limit = ? WHERE client_id = ?');
            foreach ($limits as $cid => $val) {
                $cid = (int) $cid;
                $val = trim((string) $val);
                if ($cid <= 0) continue;
                $ensure->execute([$cid]);
                $upd->execute([$val === '' ? null : max(0, (int) $val), $cid]);
            }
            // "Add texts": a free bundle from us (goodwill, or a PayPal payment
            // that didn't come back) — recorded like a bought one, at £0.
            $grants = is_array($_POST['grant'] ?? null) ? $_POST['grant'] : [];
            $added  = 0;
            if ($grants && sms_bundles_ready()) {
                $ins = $pdo->prepare("INSERT INTO sms_bundles (client_id, texts, price, status, bought_by_user_id, note, paid_at)
                                      VALUES (?, ?, 0, 'paid', ?, 'added by YourBlinds', NOW())");
                foreach ($grants as $cid => $n) {
                    $cid = (int) $cid; $n = (int) $n;
                    if ($cid <= 0 || $n <= 0 || $n > 100000) continue;
                    $ins->execute([$cid, $n, (int) (current_user()['user_id'] ?? 0)]);
                    $added++;
                }
            }
            $_SESSION['flash_success'] = 'Monthly text allowances saved.' . ($added ? " Texts added for $added client" . ($added === 1 ? '' : 's') . '.' : '');
        } elseif ($action === 'bundles') {
            // One pack per line: "250 = 10.00" (texts = price in £, before VAT).
            $packs = [];
            foreach (preg_split('/\R/', (string) ($_POST['packs'] ?? '')) as $line) {
                if (preg_match('/^\s*(\d+)\s*(?:texts?)?\s*[=:,]\s*(?:£)?\s*(\d+(?:\.\d{1,2})?)\s*$/iu', $line, $m) && (int) $m[1] > 0 && (float) $m[2] > 0) {
                    $packs[] = ['texts' => (int) $m[1], 'price' => number_format((float) $m[2], 2, '.', '')];
                }
            }
            pc_set('SMS_BUNDLES', json_encode($packs));
            $_SESSION['flash_success'] = $packs ? count($packs) . ' text bundle' . (count($packs) === 1 ? '' : 's') . ' on sale.' : 'No text bundles on sale.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage()
            . ' — have migrate_platform_config.php and migrate_messages.php been run?';
    }
    header('Location: /master-admin/texts.php');
    exit;
}

$ready       = sms_ready();
$hasToken    = sms_provider_configured();
$defaultLim  = sms_default_limit();
$loadError   = null;
$rows        = [];
$recent      = [];
$monthStart  = date('Y-m-01 00:00:00');

if ($ready) {
    try {
        $st = $pdo->prepare(
            "SELECT c.id, c.company_name,
                    COALESCE(s.feature_sms, 0) AS feature_sms,
                    s.sms_sender_name, s.sms_monthly_limit,
                    COALESCE(s.sms_booking_confirm, 0) AS sms_booking_confirm,
                    COALESCE(s.sms_day_before, 0)      AS sms_day_before,
                    COALESCE(s.sms_on_the_way, 0)      AS sms_on_the_way,
                    (SELECT COALESCE(SUM(m.segments), 0) FROM messages m
                      WHERE m.client_id = c.id AND m.channel = 'sms' AND m.direction = 'out'
                        AND m.status IN ('queued', 'sent', 'delivered')
                        AND m.created_at >= ?" . (sms_bundles_ready() ? ' AND m.from_bundle = 0' : '') . ") AS used
               FROM clients c
               LEFT JOIN client_settings s ON s.client_id = c.id
           ORDER BY c.company_name"
        );
        $st->execute([$monthStart]);
        $rows = $st->fetchAll();

        $recent = $pdo->query(
            "SELECT m.created_at, m.kind, m.to_addr, m.from_addr, m.segments, m.status, m.status_detail,
                    c.company_name
               FROM messages m
               LEFT JOIN clients c ON c.id = m.client_id
              WHERE m.channel = 'sms'
           ORDER BY m.id DESC LIMIT 25"
        )->fetchAll();
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
    }
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$kindLabels = array_map(static fn ($k) => $k['label'], sms_kinds()) + ['quote_follow_up' => 'Quote follow-up', 'test' => 'Test'];
$activeNav  = 'texts';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Texts &middot; Master Admin &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .texts-table td, .texts-table th { white-space: nowrap; }
        .texts-table input[type="number"] { width: 6.5rem; }
        .texts-muted { color: var(--text-faint); }
        .texts-pill { display:inline-block; padding:0 0.45rem; border-radius:999px; font-size:0.75rem; font-weight:600; }
        .texts-pill.delivered { background:#dcfce7; color:#166534; }
        .texts-pill.sent, .texts-pill.queued { background:#e0f2fe; color:#075985; }
        .texts-pill.failed, .texts-pill.blocked { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Customer texts</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; the platform's text account, each client's monthly limit, and usage.
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>
        <?php if (!$ready): ?>
            <div class="alert alert-error" role="alert">Run <a href="/setup/migrations/migrate_messages.php">migrate_messages.php</a> first.</div>
        <?php endif; ?>
        <?php if ($loadError !== null): ?>
            <div class="alert alert-error" role="alert">Could not load: <?= e($loadError) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header"><h2 class="section-title">Text account (<a href="https://thesmsworks.co.uk" target="_blank" rel="noopener">The SMS Works</a>)</h2></div>
            <form method="post" action="/master-admin/texts.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="provider">
                <p style="margin:0 0 1rem">
                    Status:
                    <?php if ($hasToken && sms_token_kind((string) pc_get('SMSWORKS_TOKEN', '')) === 'key'): ?>
                        <strong style="color:#991b1b">Wrong code saved</strong> — this looks like the API key or the secret.
                        Paste the <strong>JWT</strong> instead (the long code starting <code>eyJ</code>, or <code>JWT eyJ</code>).
                    <?php elseif ($hasToken): ?><strong style="color:#166534">Connected</strong> — a JWT is saved.
                    <?php else: ?><strong style="color:#991b1b">Not connected</strong> — no texts will go out until a token is saved.
                    <?php endif; ?>
                </p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="smsworks_token">JWT <span style="color:#9ca3af;font-weight:400">(The SMS Works → API Key page has three codes: key, secret and JWT. Paste the JWT. Leave blank to keep the saved one.)</span></label>
                        <input id="smsworks_token" name="smsworks_token" type="password" autocomplete="off" value="">
                    </div>
                    <div class="form-group">
                        <label for="default_limit">Default monthly text allowance per client</label>
                        <input id="default_limit" name="default_limit" type="number" min="1" step="1" value="<?= (int) $defaultLim ?>">
                    </div>
                </div>
                <?php if ($hasToken): ?>
                    <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:400;font-size:0.875rem;margin-bottom:1rem">
                        <input type="checkbox" name="clear_token" value="1"> Disconnect (remove the saved token)
                    </label>
                <?php endif; ?>
                <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 1rem">
                    Delivery reports come back automatically — each text tells The SMS Works where to report, so nothing needs setting up on their side.
                </p>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </section>

        <?php if ($ready): $packsNow = sms_bundle_packs(); ?>
        <section class="section">
            <div class="section-header"><h2 class="section-title">Text bundles</h2></div>
            <?php if (!sms_bundles_ready()): ?>
                <div class="alert alert-error" role="alert">Run <a href="/setup/migrations/migrate_sms_bundles.php?run=1">migrate_sms_bundles.php</a> first.</div>
            <?php endif; ?>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem">
                Texts cost us money, so the monthly number is an <strong>allowance</strong>. Past it, a client texts from bundles they
                buy up front with PayPal on <strong>Messages &rarr; Buy more texts</strong>; unused bundle texts carry over.
                One pack per line, <code>texts = price</code> in pounds <strong>before VAT</strong> (VAT is added at checkout, like the plans),
                e.g. <code>250 = 10.00</code>. Leave empty and no bundles are on sale.
            </p>
            <form method="post" action="/master-admin/texts.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="bundles">
                <textarea name="packs" rows="4" style="width:100%;max-width:20rem;font-family:monospace" placeholder="250 = 10.00&#10;500 = 18.00"><?php
                    foreach ($packsNow as $p) echo e($p['texts'] . ' = ' . $p['price']) . "\n";
                ?></textarea>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Save bundles</button></div>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($rows): ?>
        <section class="section">
            <div class="section-header"><h2 class="section-title">Clients — <?= e(date('F Y')) ?></h2></div>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem">
                Leave an allowance blank to use the default (<?= number_format($defaultLim) ?>). Once a client has used it,
                texts come from their bundle balance; with none left, texts stop until next month or until they buy a bundle.
                <strong>Add texts</strong> gives a client free bundle texts (goodwill, or a payment that didn't come through).
                Texting is on for <?= e(billing_feature_tier_names('feature_sms') ?: 'every') ?> clients only.
            </p>
            <form method="post" action="/master-admin/texts.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="limits">
                <div class="table-wrap" style="overflow-x:auto">
                <table class="table texts-table">
                    <thead><tr>
                        <th>Client</th><th>Texting</th><th>Sender</th><th>Texts on</th>
                        <th style="text-align:right">Used</th><th>Monthly allowance</th>
                        <th style="text-align:right">Bundle left</th><th>Add texts</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $cid   = (int) $r['id'];
                        $limit = $r['sms_monthly_limit'] !== null ? (int) $r['sms_monthly_limit'] : $defaultLim;
                        $used  = (int) $r['used'];
                        $pct   = $limit > 0 ? (int) round($used * 100 / $limit) : ($used > 0 ? 100 : 0);
                        $on    = sms_feature_enabled($cid);
                        $which = [];
                        foreach (sms_kinds() as $k) if ((int) $r[$k['column']] === 1) $which[] = $k['label'];
                    ?>
                        <tr>
                            <td><?= e((string) $r['company_name']) ?></td>
                            <td><?= $on ? 'On' : '<span class="texts-muted">Off (Bronze)</span>' ?></td>
                            <td><?= $r['sms_sender_name'] ? e((string) $r['sms_sender_name']) : '<span class="texts-muted">—</span>' ?></td>
                            <td><?= $which ? e(implode(', ', $which)) : '<span class="texts-muted">none</span>' ?></td>
                            <td style="text-align:right;<?= $pct >= 100 ? 'color:#991b1b;font-weight:700' : ($pct >= SMS_WARN_PERCENT ? 'color:#b45309;font-weight:700' : '') ?>">
                                <?= number_format($used) ?><?= $used > 0 ? ' <span class="texts-muted">(' . $pct . '%)</span>' : '' ?>
                            </td>
                            <td>
                                <input type="number" min="0" step="1" name="limit[<?= $cid ?>]"
                                       value="<?= $r['sms_monthly_limit'] !== null ? (int) $r['sms_monthly_limit'] : '' ?>"
                                       placeholder="<?= (int) $defaultLim ?>" aria-label="Monthly text allowance for <?= e((string) $r['company_name']) ?>">
                            </td>
                            <td style="text-align:right"><?= number_format(sms_bundle_balance($cid)) ?></td>
                            <td>
                                <input type="number" min="0" step="1" name="grant[<?= $cid ?>]" value="" placeholder="0" style="width:6rem"
                                       aria-label="Free texts to add for <?= e((string) $r['company_name']) ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($ready): ?>
        <section class="section">
            <div class="section-header"><h2 class="section-title">Latest texts</h2></div>
            <?php if (!$recent): ?>
                <p class="texts-muted">No texts yet.</p>
            <?php else: ?>
            <div class="table-wrap" style="overflow-x:auto">
            <table class="table texts-table">
                <thead><tr><th>When</th><th>Client</th><th>Text</th><th>To</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $m): ?>
                    <tr>
                        <td><?= e(date('j M H:i', strtotime((string) $m['created_at']))) ?></td>
                        <td><?= e((string) ($m['company_name'] ?? '')) ?></td>
                        <td><?= e($kindLabels[(string) $m['kind']] ?? (string) $m['kind']) ?><?= (int) $m['segments'] > 1 ? ' <span class="texts-muted">(' . (int) $m['segments'] . ' texts)</span>' : '' ?></td>
                        <td><?= e(sms_display_number((string) $m['to_addr'])) ?></td>
                        <td>
                            <span class="texts-pill <?= e((string) $m['status']) ?>"><?= e(ucfirst((string) $m['status'])) ?></span>
                            <?php if (!empty($m['status_detail'])): ?><span class="texts-muted" style="white-space:normal"> <?= e((string) $m['status_detail']) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
