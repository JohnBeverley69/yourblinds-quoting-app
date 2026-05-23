<?php
declare(strict_types=1);

/**
 * Super-admin: PayPal health dashboard.
 *
 * Answers two operational questions at a glance:
 *   1. "Is PayPal actually connected right now?" — config sanity +
 *      a real OAuth ping to the PayPal API. If credentials work,
 *      we can also process subscribes/cancels.
 *   2. "Are webhooks actually arriving?" — last-received timestamp,
 *      24h / 7d / 30d counts, plus a table of the most recent events
 *      with their outcome (processed / no_matching_tenant /
 *      verification_failed / unhandled_event_type / etc.).
 *
 * Webhook data comes from the paypal_webhook_log table populated by
 * billing/paypal_webhook.php on every incoming event. If the table
 * doesn't exist yet (migration not run) the page renders a banner
 * pointing at /migrate_paypal_webhook_log.php instead.
 *
 * Read-only — no writes from this page. To actually fire a test
 * event, use PayPal Dashboard → Webhooks → Webhook simulator (we
 * link to it). PayPal doesn't expose a "trigger a test from the
 * partner side" API.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';

requireSuperAdmin();

// The sidebar partial reads $user to decide which entries to show.
// Without this the menu collapses because every gated entry's
// permission check evaluates against null.
$user = current_user();

$cfg = paypal_config();

// ─── Live API ping ────────────────────────────────────────────────────
//
// Doing an OAuth token round-trip proves: credentials are valid, the
// PayPal API is reachable, and we know which env we're talking to.
// "configured" alone just means the .env keys are present.
$apiPingOk     = null;            // null = not attempted, true / false = result
$apiPingDetail = '';
$apiPingMs     = 0;
if (paypal_is_configured()) {
    $t0 = microtime(true);
    try {
        paypal_access_token();
        $apiPingOk     = true;
        $apiPingDetail = 'OAuth token issued successfully.';
    } catch (Throwable $e) {
        $apiPingOk     = false;
        $apiPingDetail = $e->getMessage();
    }
    $apiPingMs = (int) round((microtime(true) - $t0) * 1000);
}

// ─── Webhook log stats ─────────────────────────────────────────────────
//
// All defensive against the migration not having been run yet.
$pdo = db();
$logTableExists = false;
$stats = [
    'last_event_at'    => null,
    'last_event_type'  => null,
    'last_outcome'     => null,
    'count_24h'        => 0,
    'count_7d'         => 0,
    'count_30d'        => 0,
    'count_unverified' => 0,
    'count_unmatched'  => 0,
];
$recentEvents = [];
$outcomeCounts = [];

try {
    $r = $pdo->query("SHOW TABLES LIKE 'paypal_webhook_log'");
    $logTableExists = (bool) $r->fetchColumn();
} catch (Throwable $e) {
    $logTableExists = false;
}

if ($logTableExists) {
    try {
        $row = $pdo->query(
            "SELECT received_at, event_type, outcome
               FROM paypal_webhook_log
              ORDER BY id DESC LIMIT 1"
        )->fetch();
        if ($row) {
            $stats['last_event_at']   = (string) $row['received_at'];
            $stats['last_event_type'] = (string) $row['event_type'];
            $stats['last_outcome']    = (string) ($row['outcome'] ?? '');
        }
    } catch (Throwable $e) { /* ignore */ }

    try {
        $stats['count_24h'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM paypal_webhook_log
              WHERE received_at >= NOW() - INTERVAL 24 HOUR"
        )->fetchColumn();
        $stats['count_7d']  = (int) $pdo->query(
            "SELECT COUNT(*) FROM paypal_webhook_log
              WHERE received_at >= NOW() - INTERVAL 7 DAY"
        )->fetchColumn();
        $stats['count_30d'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM paypal_webhook_log
              WHERE received_at >= NOW() - INTERVAL 30 DAY"
        )->fetchColumn();
        $stats['count_unverified'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM paypal_webhook_log
              WHERE verified = 0
                AND received_at >= NOW() - INTERVAL 30 DAY"
        )->fetchColumn();
        $stats['count_unmatched'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM paypal_webhook_log
              WHERE outcome = 'no_matching_tenant'
                AND received_at >= NOW() - INTERVAL 30 DAY"
        )->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }

    try {
        $recentEvents = $pdo->query(
            "SELECT l.id, l.event_type, l.event_id, l.subscription_id,
                    l.client_id, l.plan_code, l.verified, l.processed,
                    l.outcome, l.received_at,
                    c.company_name
               FROM paypal_webhook_log l
          LEFT JOIN clients c ON c.id = l.client_id
              ORDER BY l.id DESC
              LIMIT 50"
        )->fetchAll();
    } catch (Throwable $e) { /* ignore */ }

    try {
        $rows = $pdo->query(
            "SELECT COALESCE(outcome, 'processed') AS outcome, COUNT(*) AS n
               FROM paypal_webhook_log
              WHERE received_at >= NOW() - INTERVAL 7 DAY
           GROUP BY outcome
           ORDER BY n DESC"
        )->fetchAll();
        foreach ($rows as $r) {
            $outcomeCounts[(string) $r['outcome']] = (int) $r['n'];
        }
    } catch (Throwable $e) { /* ignore */ }
}

// ─── Human-friendly age formatter for the "last event" tile ────────────
$ageOf = static function (?string $ts): string {
    if (!$ts) return 'never';
    $diff = time() - strtotime($ts);
    if ($diff < 0)        return 'just now';
    if ($diff < 60)       return $diff . 's ago';
    if ($diff < 3600)     return floor($diff / 60) . 'm ago';
    if ($diff < 86400)    return floor($diff / 3600) . 'h ago';
    if ($diff < 86400*7)  return floor($diff / 86400) . 'd ago';
    return date('j M Y', strtotime($ts));
};

// Convenience flags
$envIsLive = $cfg['env'] === 'live';
$webhookConfigured = $cfg['webhook_id'] !== '';

$dashTag = 'Master Admin';
$activeNav = 'paypal-health';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PayPal health &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .ph-tiles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
            gap: 0.625rem;
            margin-bottom: 1rem;
        }
        .ph-tile {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 0.75rem 0.875rem;
        }
        .ph-tile .lbl {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: #6b7280; font-weight: 600;
        }
        .ph-tile .val {
            font-size: 1.5rem; font-weight: 700; color: #111827;
            margin-top: 0.25rem; line-height: 1.1;
        }
        .ph-tile .sub {
            font-size: 0.75rem; color: #6b7280; margin-top: 0.1875rem;
        }
        .ph-tile.ok      { background: #ecfdf5; border-color: #a7f3d0; }
        .ph-tile.ok .val { color: #065f46; }
        .ph-tile.warn      { background: #fffbeb; border-color: #fde68a; }
        .ph-tile.warn .val { color: #92400e; }
        .ph-tile.bad      { background: #fef2f2; border-color: #fecaca; }
        .ph-tile.bad .val { color: #991b1b; }

        .ph-card {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 1rem 1.125rem;
            margin-bottom: 1rem;
        }
        .ph-card h2 {
            margin: 0 0 0.625rem; font-size: 1rem;
            color: #1f3b5b;
        }
        .ph-grid {
            display: grid; grid-template-columns: 12rem 1fr;
            gap: 0.375rem 0.875rem; font-size: 0.875rem;
        }
        .ph-grid .k { color: #6b7280; }
        .ph-grid .v code {
            background: #f3f4f6; padding: 0.0625rem 0.375rem;
            border-radius: 4px; font-size: 0.8125rem;
        }
        .ph-pill {
            display: inline-block; padding: 0.125rem 0.5rem;
            border-radius: 999px; font-size: 0.75rem;
            font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .ph-pill.live      { background: #d1fae5; color: #065f46; }
        .ph-pill.sandbox   { background: #fef3c7; color: #92400e; }
        .ph-pill.ok        { background: #d1fae5; color: #065f46; }
        .ph-pill.fail      { background: #fee2e2; color: #991b1b; }
        .ph-pill.unknown   { background: #e5e7eb; color: #374151; }

        table.ph-events { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        table.ph-events th, table.ph-events td {
            padding: 0.4375rem 0.5625rem; text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        table.ph-events th {
            background: #f9fafb; font-size: 0.6875rem;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: #6b7280; font-weight: 600;
        }
        table.ph-events tr.row-processed         td { background: #fff; }
        table.ph-events tr.row-no_matching_tenant td { background: #fffbeb; }
        table.ph-events tr.row-unhandled_event_type td { background: #f9fafb; color: #6b7280; }
        table.ph-events tr.row-verification_failed td { background: #fef2f2; color: #991b1b; }
        table.ph-events tr.row-bad_json          td { background: #fef2f2; color: #991b1b; }
        table.ph-events tr.row-no_subscription_id td { background: #fffbeb; }
        table.ph-events code {
            background: #f3f4f6; padding: 0.0625rem 0.375rem;
            border-radius: 4px; font-size: 0.75rem;
        }
        .outcome-tag {
            display: inline-block; padding: 0.0625rem 0.4375rem;
            border-radius: 999px; font-size: 0.6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
        }
        .outcome-processed         { background: #d1fae5; color: #065f46; }
        .outcome-no_matching_tenant { background: #fef3c7; color: #92400e; }
        .outcome-unhandled_event_type { background: #e5e7eb; color: #374151; }
        .outcome-verification_failed { background: #fee2e2; color: #991b1b; }
        .outcome-bad_json          { background: #fee2e2; color: #991b1b; }
        .outcome-no_subscription_id { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">PayPal health</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; "Is PayPal actually working?" — at a glance.
                </p>
            </div>
        </div>

        <?php if (!$logTableExists): ?>
            <div class="alert alert-error" role="alert">
                <strong>Webhook log table missing.</strong>
                Webhook events aren't being recorded yet — run
                <a href="/migrate_paypal_webhook_log.php"><code>/migrate_paypal_webhook_log.php</code></a>
                once (super-admin only). After that, every event PayPal
                sends will appear in the table below.
            </div>
        <?php endif; ?>

        <!--
            Headline tiles: one-glance answers. Each tile is colour-coded
            so a green-everywhere row = healthy, anything orange/red
            needs investigation.
        -->
        <div class="ph-tiles">
            <div class="ph-tile <?= $envIsLive ? 'ok' : 'warn' ?>">
                <div class="lbl">Environment</div>
                <div class="val"><?= e(strtoupper($cfg['env'])) ?></div>
                <div class="sub">
                    <?= $envIsLive
                        ? 'Live mode — real money will move.'
                        : 'Sandbox — test data only.' ?>
                </div>
            </div>

            <?php
                $apiClass = $apiPingOk === true ? 'ok'
                    : ($apiPingOk === false ? 'bad' : 'warn');
                $apiVal = $apiPingOk === true ? 'OK'
                    : ($apiPingOk === false ? 'FAIL' : '—');
            ?>
            <div class="ph-tile <?= e($apiClass) ?>">
                <div class="lbl">API ping</div>
                <div class="val"><?= e($apiVal) ?></div>
                <div class="sub">
                    <?php if ($apiPingOk === true): ?>
                        OAuth token issued in <?= $apiPingMs ?>ms.
                    <?php elseif ($apiPingOk === false): ?>
                        <?= e(mb_substr($apiPingDetail, 0, 140)) ?>
                    <?php else: ?>
                        Not configured — fill <code>.env</code>.
                    <?php endif; ?>
                </div>
            </div>

            <div class="ph-tile <?= $webhookConfigured ? 'ok' : 'bad' ?>">
                <div class="lbl">Webhook ID</div>
                <div class="val"><?= $webhookConfigured ? 'Set' : 'Missing' ?></div>
                <div class="sub">
                    <?php if ($webhookConfigured): ?>
                        <code><?= e($cfg['webhook_id']) ?></code>
                    <?php else: ?>
                        Set <code>PAYPAL_WEBHOOK_ID</code> in <code>.env</code>.
                    <?php endif; ?>
                </div>
            </div>

            <?php
                // Severity:
                //   no events in 24h+30d  → warn  (might just be quiet)
                //   no events in 7d+      → bad   (probably broken)
                //   recent event present  → ok
                $eventSev = 'ok';
                if (!$stats['last_event_at']) {
                    $eventSev = 'warn';
                } else {
                    $age = time() - strtotime($stats['last_event_at']);
                    if ($age > 86400 * 30) $eventSev = 'warn';
                    if ($age > 86400 * 90) $eventSev = 'bad';
                }
            ?>
            <div class="ph-tile <?= e($eventSev) ?>">
                <div class="lbl">Last event</div>
                <div class="val"><?= e($ageOf($stats['last_event_at'])) ?></div>
                <div class="sub">
                    <?php if ($stats['last_event_at']): ?>
                        <?= e((string) $stats['last_event_type']) ?>
                        &middot; <?= e((string) ($stats['last_outcome'] ?: 'processed')) ?>
                    <?php else: ?>
                        No webhooks recorded yet.
                    <?php endif; ?>
                </div>
            </div>

            <div class="ph-tile">
                <div class="lbl">Last 24 h</div>
                <div class="val"><?= (int) $stats['count_24h'] ?></div>
                <div class="sub">events received</div>
            </div>

            <div class="ph-tile">
                <div class="lbl">Last 7 days</div>
                <div class="val"><?= (int) $stats['count_7d'] ?></div>
                <div class="sub">events received</div>
            </div>

            <?php if ($stats['count_unverified'] > 0): ?>
                <div class="ph-tile bad">
                    <div class="lbl">Unverified (30d)</div>
                    <div class="val"><?= (int) $stats['count_unverified'] ?></div>
                    <div class="sub">signature failed — possible spoof or wrong webhook ID</div>
                </div>
            <?php endif; ?>

            <?php if ($stats['count_unmatched'] > 0): ?>
                <div class="ph-tile warn">
                    <div class="lbl">Unmatched (30d)</div>
                    <div class="val"><?= (int) $stats['count_unmatched'] ?></div>
                    <div class="sub">no matching tenant — investigate</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Config details + how-to -->
        <div class="ph-card">
            <h2>Configuration</h2>
            <div class="ph-grid">
                <div class="k">Environment</div>
                <div class="v">
                    <span class="ph-pill <?= $envIsLive ? 'live' : 'sandbox' ?>">
                        <?= e($cfg['env']) ?>
                    </span>
                    <?= $envIsLive
                        ? '<span style="color:#6b7280;font-size:0.8125rem;margin-left:0.5rem">Real charges.</span>'
                        : '<span style="color:#6b7280;font-size:0.8125rem;margin-left:0.5rem">No real money moves.</span>' ?>
                </div>

                <div class="k">API base URL</div>
                <div class="v"><code><?= e($cfg['api_base_url']) ?></code></div>

                <div class="k">Web base URL</div>
                <div class="v"><code><?= e($cfg['web_base_url']) ?></code></div>

                <div class="k">Client ID</div>
                <div class="v">
                    <?php if ($cfg['client_id'] !== ''): ?>
                        <code><?= e(substr($cfg['client_id'], 0, 8) . '…' . substr($cfg['client_id'], -4)) ?></code>
                        <span class="ph-pill ok">Set</span>
                    <?php else: ?>
                        <span class="ph-pill fail">Missing</span>
                    <?php endif; ?>
                </div>

                <div class="k">Secret</div>
                <div class="v">
                    <?php if ($cfg['secret'] !== ''): ?>
                        <span class="ph-pill ok">Set</span>
                        <span style="color:#6b7280;font-size:0.8125rem;margin-left:0.5rem">
                            (never displayed)
                        </span>
                    <?php else: ?>
                        <span class="ph-pill fail">Missing</span>
                    <?php endif; ?>
                </div>

                <div class="k">Webhook ID</div>
                <div class="v">
                    <?php if ($webhookConfigured): ?>
                        <code><?= e($cfg['webhook_id']) ?></code>
                        <span class="ph-pill ok">Set</span>
                    <?php else: ?>
                        <span class="ph-pill fail">Missing</span>
                    <?php endif; ?>
                </div>

                <div class="k">Webhook URL (give to PayPal)</div>
                <div class="v">
                    <code><?= e(((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain')
                        . '/billing/paypal_webhook.php') ?></code>
                </div>

                <div class="k">API ping test</div>
                <div class="v">
                    <?php if ($apiPingOk === true): ?>
                        <span class="ph-pill ok">Pass</span>
                        <span style="color:#6b7280;font-size:0.8125rem;margin-left:0.5rem">
                            (<?= $apiPingMs ?>ms)
                        </span>
                    <?php elseif ($apiPingOk === false): ?>
                        <span class="ph-pill fail">Fail</span>
                        <div style="color:#991b1b;font-size:0.8125rem;margin-top:0.25rem">
                            <?= e($apiPingDetail) ?>
                        </div>
                    <?php else: ?>
                        <span class="ph-pill unknown">Not attempted</span>
                        <span style="color:#6b7280;font-size:0.8125rem;margin-left:0.5rem">
                            Credentials missing.
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <p style="margin:0.875rem 0 0;padding-top:0.75rem;border-top:1px solid #f3f4f6;color:#6b7280;font-size:0.8125rem;line-height:1.5">
                <strong style="color:#1f3b5b">Send a test event:</strong>
                PayPal's
                <a href="<?= $envIsLive
                    ? 'https://developer.paypal.com/dashboard/webhooks/simulator'
                    : 'https://developer.paypal.com/dashboard/webhooks/simulator?env=sandbox' ?>"
                   target="_blank" rel="noopener noreferrer">
                    Webhook Simulator
                </a>
                fires a synthetic event at any URL.
                Pick <code>BILLING.SUBSCRIPTION.CANCELLED</code>, paste the
                webhook URL above, send. If it lands as <em>processed</em> or
                <em>no_matching_tenant</em> (synthetic event won't match a real
                sub) — webhooks are working. If you get <em>verification_failed</em>,
                the <code>PAYPAL_WEBHOOK_ID</code> in <code>.env</code> doesn't
                match the one PayPal generated.
            </p>
        </div>

        <!-- Outcome breakdown -->
        <?php if ($outcomeCounts): ?>
            <div class="ph-card">
                <h2>Outcomes — last 7 days</h2>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                    <?php foreach ($outcomeCounts as $outcome => $n):
                        $cls = 'outcome-' . preg_replace('/[^a-z_]/', '_', strtolower($outcome));
                    ?>
                        <span class="outcome-tag <?= e($cls) ?>"
                              style="font-size:0.8125rem;padding:0.25rem 0.625rem">
                            <?= e($outcome) ?> &middot; <?= (int) $n ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent events table -->
        <?php if ($logTableExists): ?>
            <div class="ph-card">
                <h2>Recent webhook events (last 50)</h2>
                <?php if (!$recentEvents): ?>
                    <p style="margin:0;color:#9ca3af;font-style:italic">
                        No events recorded yet. Once PayPal sends one, it'll show up here.
                    </p>
                <?php else: ?>
                    <div style="overflow-x:auto">
                        <table class="ph-events">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Event type</th>
                                    <th>Tenant</th>
                                    <th>Plan</th>
                                    <th>Subscription</th>
                                    <th>Outcome</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEvents as $ev):
                                    $outcome = (string) ($ev['outcome'] ?? 'processed');
                                    $rowCls = 'row-' . preg_replace('/[^a-z_]/', '_', strtolower($outcome));
                                    $tagCls = 'outcome-' . preg_replace('/[^a-z_]/', '_', strtolower($outcome));
                                ?>
                                    <tr class="<?= e($rowCls) ?>">
                                        <td title="<?= e((string) $ev['received_at']) ?>">
                                            <?= e($ageOf((string) $ev['received_at'])) ?>
                                        </td>
                                        <td><code><?= e((string) $ev['event_type']) ?></code></td>
                                        <td>
                                            <?php if (!empty($ev['company_name'])): ?>
                                                <?= e((string) $ev['company_name']) ?>
                                            <?php elseif (!empty($ev['client_id'])): ?>
                                                <em style="color:#9ca3af">client #<?= (int) $ev['client_id'] ?></em>
                                            <?php else: ?>
                                                <em style="color:#9ca3af">—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($ev['plan_code'])): ?>
                                                <code><?= e((string) $ev['plan_code']) ?></code>
                                            <?php else: ?>
                                                <em style="color:#9ca3af">—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($ev['subscription_id'])): ?>
                                                <code><?= e((string) $ev['subscription_id']) ?></code>
                                            <?php else: ?>
                                                <em style="color:#9ca3af">—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="outcome-tag <?= e($tagCls) ?>">
                                                <?= e($outcome) ?>
                                            </span>
                                            <?php if (!(int) $ev['verified']): ?>
                                                <span class="outcome-tag outcome-verification_failed"
                                                      style="margin-left:0.25rem">
                                                    unverified
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p style="margin:0.625rem 0 0;color:#6b7280;font-size:0.75rem">
                        <strong>Outcomes:</strong>
                        <em>processed</em> = applied to tenant state ·
                        <em>no_matching_tenant</em> = event arrived for a sub we don't have (often a test or stale row) ·
                        <em>unhandled_event_type</em> = PayPal sent an event we don't act on (harmless) ·
                        <em>verification_failed</em> = signature wrong (possible spoof or webhook-id mismatch) ·
                        <em>bad_json</em> = malformed payload ·
                        <em>no_subscription_id</em> = event with no usable id.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
