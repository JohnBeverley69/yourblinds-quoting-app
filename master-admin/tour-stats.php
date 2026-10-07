<?php
declare(strict_types=1);

/**
 * Master Admin: "How it works" tour stats (read-only).
 *
 * How many people opened the public tour, how many pressed Play, how far
 * they got, where they came from and what they clicked next. Anonymous —
 * see _partials/tour_stats.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/tour_stats.php';

requireSuperAdmin();

$pdo    = db();
$SCENES = require __DIR__ . '/../_partials/how_it_works_scenes.php';

$ranges = ['1' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];
$range  = (string) ($_GET['days'] ?? '30');
if (!isset($ranges[$range])) $range = '30';
$since  = $range === 'all' ? '2000-01-01 00:00:00'
        : ($range === '1' ? date('Y-m-d 00:00:00') : date('Y-m-d H:i:s', strtotime('-' . (int) $range . ' days')));

$loadError = null;
$tot = ['visits' => 0, 'plays' => 0, 'finished' => 0, 'signup' => 0, 'price' => 0];
$bySource = $byDevice = $daily = [];
$reached  = array_fill(1, count($SCENES), 0);
$lastSeen = null;

/** Add one visit to a breakdown bucket. */
$add = static function (array $bucket, string $k, bool $played, int $finished): array {
    $bucket[$k] ??= ['visits' => 0, 'plays' => 0, 'finished' => 0];
    $bucket[$k]['visits']++;
    $bucket[$k]['plays']    += $played ? 1 : 0;
    $bucket[$k]['finished'] += $finished;
    return $bucket;
};

try {
    tour_stats_ensure($pdo);

    // Per-visit summary: one row per visit, flags for what it did.
    $visitSql = "SELECT visit_id,
                        MAX(CASE WHEN event = 'view' THEN source END)   AS source,
                        MAX(CASE WHEN event = 'view' THEN device END)   AS device,
                        MIN(created_at)                                AS first_at,
                        MAX(event = 'view')       AS viewed,
                        MAX(event = 'play')       AS played,
                        MAX(event = 'finish')     AS finished,
                        MAX(event = 'cta_signup') AS signup,
                        MAX(event = 'cta_price')  AS price,
                        MAX(CASE WHEN event = 'step' THEN step ELSE 0 END) AS furthest
                   FROM tour_events
                  WHERE created_at >= ?
               GROUP BY visit_id";
    $st = $pdo->prepare($visitSql);
    $st->execute([$since]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
        if (!(int) $v['viewed']) continue;                 // events without a page view = noise
        $src = (string) ($v['source'] ?: 'direct');
        $dev = (string) ($v['device'] ?: 'unknown');
        $day = substr((string) $v['first_at'], 0, 10);
        $played = (int) $v['played'] || (int) $v['furthest'] > 0;

        $tot['visits']++;
        $tot['plays']    += $played ? 1 : 0;
        $tot['finished'] += (int) $v['finished'];
        $tot['signup']   += (int) $v['signup'];
        $tot['price']    += (int) $v['price'];

        $bySource = $add($bySource, $src, $played, (int) $v['finished']);
        $byDevice = $add($byDevice, $dev, $played, (int) $v['finished']);
        $daily    = $add($daily, $day, $played, (int) $v['finished']);

        for ($k = 1; $k <= min((int) $v['furthest'], count($SCENES)); $k++) $reached[$k]++;
        if ($lastSeen === null || $v['first_at'] > $lastSeen) $lastSeen = (string) $v['first_at'];
    }
    uasort($bySource, static fn ($a, $b) => $b['visits'] <=> $a['visits']);
    uasort($byDevice, static fn ($a, $b) => $b['visits'] <=> $a['visits']);
    krsort($daily);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$pct = static fn (int $a, int $b): string => $b > 0 ? round($a / $b * 100) . '%' : '–';

$activeNav = 'tour-stats';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tour stats &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ts-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 1.25rem; }
        .ts-stat { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: .875rem 1rem; }
        .ts-stat .v { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
        .ts-stat .l { color: var(--text-faint); font-size: .8125rem; margin-top: .25rem; }
        .ts-ranges { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .ts-ranges a { border: 1px solid var(--border); border-radius: 999px; padding: .2rem .7rem; text-decoration: none;
            color: var(--text-secondary); font-size: .875rem; }
        .ts-ranges a.on { background: var(--link); border-color: var(--link); color: #fff; }
        .ts-cols { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .ts-bar { display: grid; grid-template-columns: 8.5rem 1fr 4.5rem; gap: .5rem; align-items: center; margin: .3rem 0; font-size: .875rem; }
        .ts-bar .track { background: var(--bg-subtle); border-radius: 5px; height: 14px; overflow: hidden; }
        .ts-bar .fill { background: var(--link); height: 100%; border-radius: 5px; }
        .ts-bar .n { text-align: right; color: var(--text-secondary); font-variant-numeric: tabular-nums; }
        .ts-note { color: var(--text-faint); font-size: .8125rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">&ldquo;How it works&rdquo; tour stats</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; who opened <a href="/how-it-works.php" target="_blank" rel="noopener">the tour</a>,
                    pressed Play, and how far they watched. Anonymous &mdash; no cookies, no personal data.
                </p>
            </div>
        </div>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-error" role="alert">Could not load stats: <?= e($loadError) ?></div>
        <?php endif; ?>

        <nav class="ts-ranges" aria-label="Period">
            <?php foreach ($ranges as $k => $label): ?>
                <a href="?days=<?= e((string) $k) ?>" class="<?= (string) $k === $range ? 'on' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="ts-grid">
            <div class="ts-stat"><div class="v"><?= number_format($tot['visits']) ?></div><div class="l">Opened the tour</div></div>
            <div class="ts-stat"><div class="v"><?= number_format($tot['plays']) ?></div><div class="l">Pressed Play (<?= $pct($tot['plays'], $tot['visits']) ?>)</div></div>
            <div class="ts-stat"><div class="v"><?= number_format($tot['finished']) ?></div><div class="l">Watched to the end (<?= $pct($tot['finished'], $tot['plays']) ?> of plays)</div></div>
            <div class="ts-stat"><div class="v"><?= number_format($tot['signup']) ?></div><div class="l">Clicked &ldquo;Start free&rdquo;</div></div>
            <div class="ts-stat"><div class="v"><?= number_format($tot['price']) ?></div><div class="l">Went on to InstaPrice</div></div>
        </div>

        <?php if ($tot['visits'] === 0): ?>
            <p class="ts-note">No visits in this period yet<?= $lastSeen ? '' : ' &mdash; counting started with this page' ?>.</p>
        <?php else: ?>
        <div class="ts-cols">
            <section class="section">
                <h2 class="section-title">How far people watched</h2>
                <p class="ts-note">Of the <?= number_format($tot['plays']) ?> who pressed Play, how many reached each step.</p>
                <?php foreach ($SCENES as $i => $s): $n = $reached[$i + 1] ?? 0; $w = $tot['plays'] ? $n / $tot['plays'] * 100 : 0; ?>
                    <div class="ts-bar">
                        <span><?= $i + 1 ?>. <?= e($s['nav']) ?></span>
                        <span class="track"><span class="fill" style="width:<?= number_format(min(100, $w), 1, '.', '') ?>%"></span></span>
                        <span class="n"><?= number_format($n) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="section">
                <h2 class="section-title">Where they came from</h2>
                <table class="table">
                    <thead><tr><th>Source</th><th style="text-align:right">Opened</th><th style="text-align:right">Played</th><th style="text-align:right">Finished</th></tr></thead>
                    <tbody>
                    <?php foreach ($bySource as $src => $r): ?>
                        <tr><td><?= e(ucfirst((string) $src)) ?></td><td style="text-align:right"><?= $r['visits'] ?></td>
                            <td style="text-align:right"><?= $r['plays'] ?></td><td style="text-align:right"><?= $r['finished'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="ts-note">Facebook&rsquo;s phone app often hides where a visitor came from, so some Facebook
                    visits show as &ldquo;Direct&rdquo;. Links ending <code>?src=fb</code> are always counted as Facebook.</p>

                <h2 class="section-title" style="margin-top:1.25rem">Device</h2>
                <table class="table">
                    <thead><tr><th>Device</th><th style="text-align:right">Opened</th><th style="text-align:right">Played</th></tr></thead>
                    <tbody>
                    <?php foreach ($byDevice as $dev => $r): ?>
                        <tr><td><?= e(ucfirst((string) $dev)) ?></td><td style="text-align:right"><?= $r['visits'] ?></td><td style="text-align:right"><?= $r['plays'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <section class="section">
            <h2 class="section-title">By day</h2>
            <table class="table">
                <thead><tr><th>Day</th><th style="text-align:right">Opened</th><th style="text-align:right">Played</th><th style="text-align:right">Finished</th></tr></thead>
                <tbody>
                <?php foreach ($daily as $day => $r): ?>
                    <tr><td><?= e(date('D j M Y', strtotime((string) $day))) ?></td><td style="text-align:right"><?= $r['visits'] ?></td>
                        <td style="text-align:right"><?= $r['plays'] ?></td><td style="text-align:right"><?= $r['finished'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="ts-note">A visit is one opening of the page, so someone who refreshes or comes back counts again.</p>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
