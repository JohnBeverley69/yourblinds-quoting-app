<?php
declare(strict_types=1);

/**
 * Trade terms — the buying discounts this account gets from its supplier
 * (Beverley), shown to the tenant read-only. These are the `trade_discounts`
 * (and any live `trade_promotions`) the supplier's master-admin has set for this
 * client; the pricing engine applies them to the trade/base price on every quote.
 * This page just SURFACES them so the account can see the deal it's on.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$tableExists = static function (string $t) use ($pdo): bool {
    try {
        $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $s->execute([$t]);
        return $s->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    try {
        $s = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $s->execute([$t, $c]);
        return $s->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};

// Supplier name for the friendly heading (the factory account). Best-effort.
$supplier = 'your supplier';
try {
    $fid = function_exists('factory_client_id') ? (int) factory_client_id() : 3;
    $s = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
    $s->execute([$fid]);
    $nm = (string) ($s->fetchColumn() ?: '');
    if ($nm !== '') $supplier = $nm;
} catch (Throwable $e) { /* keep default */ }

// ── Standing discounts (trade_discounts) ─────────────────────────────────────
$discounts = [];
$tdReady   = $tableExists('trade_discounts');
if ($tdReady) {
    $hasSys   = $colExists('trade_discounts', 'system_id');
    $hasExtra = $colExists('trade_discounts', 'extra_id');
    $cols = 'td.discount_percent, td.product_id, td.band_code, p.name AS product_name'
          . ($hasSys   ? ', td.system_id, s.name AS system_name' : '')
          . ($hasExtra ? ', td.extra_id, td.choice_id, pe.name AS extra_name, pec.label AS choice_label' : '');
    $join = ($hasSys   ? ' LEFT JOIN product_systems s ON s.id = td.system_id' : '')
          . ($hasExtra ? ' LEFT JOIN product_extras pe ON pe.id = td.extra_id LEFT JOIN product_extra_choices pec ON pec.id = td.choice_id' : '');
    try {
        $st = $pdo->prepare(
            "SELECT $cols FROM trade_discounts td
               JOIN products p ON p.id = td.product_id $join
              WHERE td.client_id = ? AND td.active = 1
           ORDER BY p.name, td.discount_percent DESC"
        );
        $st->execute([$clientId]);
        $discounts = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $discounts = []; }
}

// ── Live promotions (trade_promotions — global or for this account, in window) ─
$promos = [];
if ($tableExists('trade_promotions')) {
    $hasExtraP = $colExists('trade_promotions', 'extra_id');
    $selP = 'tp.discount_percent, tp.name, tp.product_id, tp.band_code, tp.ends_on'
          . ($hasExtraP ? ', tp.extra_id, tp.choice_id, pe.name AS extra_name, pec.label AS choice_label' : '')
          . ', p.name AS product_name, ps.name AS master_product_name, s.name AS system_name';
    $joinP = ($hasExtraP ? ' LEFT JOIN product_extras pe ON pe.id = tp.extra_id LEFT JOIN product_extra_choices pec ON pec.id = tp.choice_id' : '')
           // resolve the product name for the tenant: their own row, or the master this promo names (global)
           . ' LEFT JOIN products p  ON p.id = tp.product_id AND p.client_id = ?'
           . ' LEFT JOIN products ps ON ps.client_id = ? AND COALESCE(NULLIF(ps.source_product_id,0), ps.id) = tp.product_id'
           . ' LEFT JOIN product_systems s ON s.id = tp.system_id';
    try {
        $st = $pdo->prepare(
            "SELECT $selP FROM trade_promotions tp $joinP
              WHERE tp.active = 1
                AND (tp.client_id IS NULL OR tp.client_id = ?)
                AND (tp.starts_on IS NULL OR tp.starts_on <= CURDATE())
                AND (tp.ends_on   IS NULL OR tp.ends_on   >= CURDATE())
           ORDER BY tp.ends_on IS NULL, tp.ends_on"
        );
        $st->execute([$clientId, $clientId, $clientId]);
        $promos = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $promos = []; }
}

$appliesLabel = static function (array $r): string {
    if (!empty($r['extra_id'])) {
        $t = 'Option: ' . (string) ($r['extra_name'] ?? ('#' . (int) $r['extra_id']));
        if (!empty($r['choice_id'])) $t .= ' → ' . (string) ($r['choice_label'] ?? ('#' . (int) $r['choice_id']));
        return $t;
    }
    $sy = ($r['system_name'] ?? '') !== '' ? (string) $r['system_name'] : 'All systems';
    $bd = ($r['band_code']   ?? '') !== '' ? ('Band ' . (string) $r['band_code']) : 'All bands';
    return $sy . ' · ' . $bd;
};
$pct = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') . '%';

$activeNav = 'trade-terms';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trade terms &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .tt-lead { color:var(--text-faint); max-width:70ch; margin:0 0 1rem; }
        .tt-pct { font-weight:800; color:#065f46; font-variant-numeric:tabular-nums; }
        .tt-note { color:var(--text-faint); font-size:0.8125rem; margin:0.75rem 0 0; max-width:74ch; }
        .tt-promo-end { color:var(--text-faint); font-size:0.8125rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Trade terms</h1>
                <p class="page-subtitle">The buying discounts your account gets from <?= e($supplier) ?>.</p>
            </div>
        </div>

        <?php if (!$tdReady): ?>
            <div class="alert alert-info" role="status">Your trade terms aren't set up yet.</div>
        <?php endif; ?>

        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Your standing discounts</h2>
            <p class="tt-lead">
                These come off the <strong>trade price</strong> you pay <?= e($supplier) ?> — applied automatically on
                every quote you build, so your costs are already reduced. (They're not a discount to your own customers;
                that's set separately under Products.)
            </p>

            <?php if (!$discounts): ?>
                <p style="color:var(--text-faint);margin:0">You're on standard terms — no special discounts set for your account.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th style="text-align:right">Discount</th><th>Product</th><th>Applies to</th></tr></thead>
                        <tbody>
                            <?php foreach ($discounts as $d): ?>
                                <tr>
                                    <td style="text-align:right"><span class="tt-pct"><?= e($pct($d['discount_percent'])) ?></span></td>
                                    <td><?= e((string) ($d['product_name'] ?? ('#' . (int) $d['product_id']))) ?></td>
                                    <td><?= e($appliesLabel($d)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <p class="tt-note">
                <strong>Where you'll see it:</strong> when you price a discounted product, the <em>base / trade price</em>
                is already reduced by the percentage above. Your own markup and any discount you give your customer are
                then applied on top.
            </p>
        </section>

        <?php if ($promos): ?>
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.4rem">Current promotions</h2>
            <p class="tt-lead">Time-limited offers running now. Where a promotion and a standing discount both apply, you get the larger.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th style="text-align:right">Discount</th><th>Product</th><th>Applies to</th><th>Until</th></tr></thead>
                    <tbody>
                        <?php foreach ($promos as $p):
                            $pname = (string) ($p['product_name'] ?? ($p['master_product_name'] ?? ('#' . (int) $p['product_id'])));
                        ?>
                            <tr>
                                <td style="text-align:right"><span class="tt-pct"><?= e($pct($p['discount_percent'])) ?></span></td>
                                <td><?= e($pname) ?><?php if (($p['name'] ?? '') !== ''): ?><br><span class="tt-promo-end"><?= e((string) $p['name']) ?></span><?php endif; ?></td>
                                <td><?= e($appliesLabel($p)) ?></td>
                                <td class="tt-promo-end"><?= $p['ends_on'] ? e(date('j M Y', strtotime((string) $p['ends_on']))) : 'ongoing' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
