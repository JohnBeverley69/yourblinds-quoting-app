<?php
declare(strict_types=1);

/**
 * Buy more texts — prepaid TEXT BUNDLES (John, 2026-10-13).
 *
 * Texts cost us money, so each plan's monthly texts are an allowance; past it
 * a company texts from bundles bought here, up front, through PayPal. Unused
 * bundle texts carry over. The packs and prices are set on Master Admin →
 * Texts; prices there are NET and VAT is added on top, like the plans.
 *
 * Flow (PayPal Orders v2, one-off payment):
 *   POST buy      → a pending sms_bundles row + a PayPal order → off to PayPal
 *   GET  ?paid=ID → back from PayPal: capture the order; on COMPLETED with the
 *                   right amount the bundle is marked paid (once — capture is
 *                   idempotent here: only a pending row of THIS company moves)
 *   GET  ?cancelled=ID → they backed out: the pending row is marked cancelled
 *
 * Admins only (requireAdmin), like Billing.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require __DIR__ . '/../_partials/paypal.php';
require __DIR__ . '/../_partials/sms.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();
$ready    = sms_bundles_ready();
$vatPct   = defined('BILLING_VAT_PERCENT') ? (float) BILLING_VAT_PERCENT : 0.0;
$grossOf  = static fn (float $net): string => number_format(round($net * (1 + $vatPct / 100), 2), 2, '.', '');
$back     = static function (string $kind, string $msg): void {
    $_SESSION[$kind === 'ok' ? 'flash_success' : 'flash_error'] = $msg;
    header('Location: /billing/texts.php');
    exit;
};

// ---- Buy: create a pending bundle + a PayPal order, then go to PayPal ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_action'] ?? '') === 'buy') {
    csrf_check();
    if (!$ready) $back('err', 'Text bundles aren\'t switched on yet.');
    if (!sms_feature_enabled($clientId)) $back('err', 'Texts are part of the Silver and Gold plans — choose one on Billing first.');
    $want = (int) ($_POST['texts'] ?? 0);
    $pack = null;
    foreach (sms_bundle_packs() as $p) if ($p['texts'] === $want) $pack = $p;   // price from OUR list, never the form
    if ($pack === null) $back('err', 'That bundle isn\'t on sale any more — pick one below.');
    if (!paypal_is_configured()) $back('err', 'Payments aren\'t set up yet. Please try again later.');
    $appBase = rtrim((string) (env('APP_URL', '') ?? ''), '/');
    if ($appBase === '') $back('err', 'Payments aren\'t set up yet (APP_URL). Please try again later.');

    $gross = $grossOf((float) $pack['price']);
    $pdo->prepare("INSERT INTO sms_bundles (client_id, texts, price, currency, status, bought_by_user_id)
                   VALUES (?, ?, ?, 'GBP', 'pending', ?)")
        ->execute([$clientId, $pack['texts'], $gross, (int) $user['user_id']]);
    $bundleId = (int) $pdo->lastInsertId();
    try {
        $r = paypal_request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'sms-bundle-' . $bundleId,
                'custom_id'    => 'sms_bundle:' . $bundleId . '|client:' . $clientId,
                'description'  => $pack['texts'] . ' texts (YourBlinds text bundle)',
                'amount'       => ['currency_code' => 'GBP', 'value' => $gross],
            ]],
            'application_context' => [
                'brand_name'          => 'YourBlinds',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url'          => $appBase . '/billing/texts.php?paid=' . $bundleId,
                'cancel_url'          => $appBase . '/billing/texts.php?cancelled=' . $bundleId,
            ],
        ]);
        $orderId = (string) ($r['data']['id'] ?? '');
        $approve = '';
        foreach ((array) ($r['data']['links'] ?? []) as $l) {
            if (($l['rel'] ?? '') === 'approve') $approve = (string) $l['href'];
        }
        if ($orderId === '' || $approve === '') throw new RuntimeException('PayPal gave no approval link');
        $pdo->prepare('UPDATE sms_bundles SET paypal_order_id = ? WHERE id = ? AND client_id = ?')
            ->execute([$orderId, $bundleId, $clientId]);
        header('Location: ' . $approve);
        exit;
    } catch (Throwable $e) {
        error_log('[sms bundle] create order failed: ' . $e->getMessage());
        $pdo->prepare("UPDATE sms_bundles SET status = 'cancelled', note = 'PayPal order failed' WHERE id = ? AND client_id = ?")
            ->execute([$bundleId, $clientId]);
        $back('err', 'Couldn\'t start the PayPal payment. Nothing was charged — please try again.');
    }
}

// ---- Back from PayPal: capture, then mark the bundle paid ----
if ($ready && isset($_GET['paid'])) {
    $bid = (int) $_GET['paid'];
    $st  = $pdo->prepare('SELECT * FROM sms_bundles WHERE id = ? AND client_id = ? LIMIT 1');
    $st->execute([$bid, $clientId]);
    $b = $st->fetch();
    if (!$b) $back('err', 'That purchase wasn\'t found.');
    if ($b['status'] === 'paid') $back('ok', number_format((int) $b['texts']) . ' texts are already on your account.');
    if ($b['status'] !== 'pending' || (string) $b['paypal_order_id'] === '') $back('err', 'That purchase was cancelled — nothing was charged.');
    // PayPal returns ?token=<order id>; it must be this bundle's order.
    if ((string) ($_GET['token'] ?? '') !== (string) $b['paypal_order_id']) $back('err', 'That payment didn\'t match this purchase — nothing was added.');
    try {
        $r = paypal_request('POST', '/v2/checkout/orders/' . rawurlencode((string) $b['paypal_order_id']) . '/capture', null,
            ['PayPal-Request-Id: sms-bundle-capture-' . $bid]);
        $d   = $r['data'];
        $cap = $d['purchase_units'][0]['payments']['captures'][0] ?? [];
        $ok  = ($d['status'] ?? '') === 'COMPLETED'
            && ($cap['status'] ?? '') === 'COMPLETED'
            && ($cap['amount']['currency_code'] ?? '') === 'GBP'
            && abs((float) ($cap['amount']['value'] ?? 0) - (float) $b['price']) < 0.005;
        if (!$ok) {
            error_log('[sms bundle] capture not completed for bundle ' . $bid . ': ' . json_encode($d));
            $back('err', 'PayPal hasn\'t confirmed the payment yet. If you were charged, contact support and we\'ll add the texts.');
        }
        $up = $pdo->prepare("UPDATE sms_bundles SET status = 'paid', paid_at = NOW(), paypal_capture_id = ?
                              WHERE id = ? AND client_id = ? AND status = 'pending'");
        $up->execute([(string) ($cap['id'] ?? ''), $bid, $clientId]);
        $back('ok', number_format((int) $b['texts']) . ' texts added. They\'re used once this month\'s allowance runs out, and carry over.');
    } catch (Throwable $e) {
        error_log('[sms bundle] capture failed for bundle ' . $bid . ': ' . $e->getMessage());
        $back('err', 'Couldn\'t confirm the payment with PayPal. If you were charged, contact support and we\'ll add the texts.');
    }
}
if ($ready && isset($_GET['cancelled'])) {
    $pdo->prepare("UPDATE sms_bundles SET status = 'cancelled' WHERE id = ? AND client_id = ? AND status = 'pending'")
        ->execute([(int) $_GET['cancelled'], $clientId]);
    $back('err', 'Payment cancelled — nothing was charged.');
}

// ---- The page ----
$hasTexts  = sms_feature_enabled($clientId);
$limit     = sms_monthly_limit($clientId);
$used      = sms_used_this_month($clientId);
$balance   = sms_bundle_balance($clientId);
$packs     = sms_bundle_packs();
$history   = [];
if ($ready) {
    $st = $pdo->prepare("SELECT texts, price, status, created_at, paid_at, note FROM sms_bundles
                          WHERE client_id = ? AND status IN ('paid', 'pending') ORDER BY id DESC LIMIT 20");
    $st->execute([$clientId]);
    $history = $st->fetchAll();
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
    <title>Buy more texts &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .tx-sum { display: flex; flex-wrap: wrap; gap: .75rem; margin: 0 0 1rem; }
        .tx-sum div { background: var(--bg-card, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 10px; padding: .7rem 1rem; min-width: 10rem; }
        .tx-sum b { display: block; font-size: 1.35rem; }
        .tx-sum span { font-size: .8125rem; color: var(--text-muted, #6b7280); }
        .tx-packs { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: .75rem; }
        .tx-pack { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; padding: 1rem; text-align: center; background: var(--bg-card, #fff); }
        .tx-pack .n { font-size: 1.5rem; font-weight: 800; }
        .tx-pack .p { margin: .25rem 0 .75rem; color: var(--text-muted, #6b7280); font-size: .875rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Buy more texts</h1>
                <p class="page-subtitle">Your plan includes <?= number_format($limit) ?> texts a month. Once they&rsquo;re used, texts come
                    out of bundles you buy here &mdash; paid up front, and any you don&rsquo;t use carry over to next month.</p>
            </div>
        </div>
        <?php if ($flashMsg): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <div class="tx-sum">
            <div><b><?= number_format($used) ?> / <?= number_format($limit) ?></b><span>allowance used in <?= e(date('F')) ?></span></div>
            <div><b><?= number_format($balance) ?></b><span>bundle texts left</span></div>
        </div>

        <section class="section">
            <h2 class="section-title">Bundles</h2>
            <?php if (!$ready): ?>
                <p>Text bundles aren&rsquo;t switched on yet.</p>
            <?php elseif (!$hasTexts): ?>
                <p>Texts are part of the Silver and Gold plans. <a href="/billing/index.php">Choose a plan on Billing &rarr;</a></p>
            <?php elseif (!$packs): ?>
                <p>Bundles aren&rsquo;t on sale yet &mdash; please check back soon.</p>
            <?php else: ?>
                <div class="tx-packs">
                    <?php foreach ($packs as $p): ?>
                        <form method="post" class="tx-pack">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="buy">
                            <input type="hidden" name="texts" value="<?= (int) $p['texts'] ?>">
                            <div class="n"><?= number_format($p['texts']) ?> texts</div>
                            <div class="p">&pound;<?= e($p['price']) ?><?= $vatPct > 0 ? ' + VAT (&pound;' . e($grossOf((float) $p['price'])) . ')' : '' ?></div>
                            <button type="submit" class="btn btn-primary">Buy with PayPal</button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <p style="margin:.75rem 0 0;font-size:.8125rem;color:var(--text-muted)">A long text counts as more than one (every 160 characters, or 70 with emoji).</p>
            <?php endif; ?>
        </section>

        <?php if ($history): ?>
        <section class="section">
            <h2 class="section-title">Your bundles</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Bought</th><th>Texts</th><th>Paid</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e(date('j M Y', strtotime((string) ($h['paid_at'] ?: $h['created_at'])))) ?></td>
                        <td><?= number_format((int) $h['texts']) ?></td>
                        <td>&pound;<?= e(number_format((float) $h['price'], 2)) ?></td>
                        <td><?= $h['status'] === 'paid' ? 'Paid' : 'Waiting for PayPal' ?><?= !empty($h['note']) ? ' &middot; ' . e((string) $h['note']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
