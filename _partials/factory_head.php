<?php
/**
 * Factory app shell — top half. Include at the start of a factory page's
 * output, after the page has done its data work. Expects in scope:
 *   $factoryTitle (string)  page title
 *   $factoryNav   (string)  active nav key ('incoming' | 'production' | ...)
 *   $factoryWide  (bool)    optional — drop the reading-width cap and use the
 *                           whole monitor. For dense tables like the floor,
 *                           where 1200px just buys you a scrollbar.
 * Close the page with _partials/factory_foot.php.
 *
 * Deliberately its OWN chrome (a top bar, not the tenant sidebar) so the
 * factory back-office reads as a standalone app at factory.yourblinds.uk.
 */
$factoryTitle = $factoryTitle ?? 'Factory';
$factoryNav   = $factoryNav   ?? '';
$factoryWide  = $factoryWide  ?? false;
$fu           = function_exists('current_user') ? current_user() : null;

// Which factory is this? White-label: the shell brands itself with the acting
// factory's name, not a hardcoded "Beverley". Super-admin also gets a switcher
// to look at any factory's back-office.
$factoryActingId = function_exists('current_factory_id')
    ? current_factory_id()
    : (function_exists('factory_client_id') ? factory_client_id() : 3);
$factoryName = 'Factory';
try {
    $st = db()->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
    $st->execute([$factoryActingId]);
    $factoryName = trim((string) $st->fetchColumn()) ?: 'Factory';
} catch (Throwable $e) { /* fall back to generic */ }
$factoryIsSuper = function_exists('is_super_admin') && is_super_admin();
$factoryChoices = [];
if ($factoryIsSuper) {
    try {
        $factoryChoices = db()
            ->query('SELECT id, company_name FROM clients WHERE is_factory = 1 ORDER BY company_name')
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $factoryChoices = []; }   // is_factory not migrated yet
}

// Nav grows as factory features land; the queue is the first.
$factoryNavItems = [
    'incoming'   => ['/factory/incoming-orders.php', 'Incoming Orders'],
    'floor'      => ['/factory/floor.php',           'Floor'],
    // Points at the scan LOG, not the tablet page — with WiFi scanners the log
    // is the scan surface you actually watch. The tablet page (/factory/scan.php)
    // is still there by URL for the deferred tablet option.
    'scan'       => ['/factory/scan-log.php',        'Scan log'],
];
// Profit shows real cost, so it's super-admin ONLY — added after the shared
// items so factory-role staff never even see the link.
if (function_exists('is_super_admin') && is_super_admin()) {
    $factoryNavItems['profit'] = ['/factory/profit.php', 'Profit'];
}
$factoryNavItems += [
    'routes'     => ['/factory/routes.php',          'Routes'],
    'build'      => ['/factory/build-rules-v2.php',  'Build rules'],
    'allowances' => ['/factory/allowances.php',      'Allowances'],
    'worksheets' => ['/factory/worksheets.php',      'Worksheets'],
    'labelsheet' => ['/factory/label-test-sheet.php', 'Label sheet'],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($factoryTitle) ?> &middot; <?= e($factoryName) ?></title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        :root { --fac-bar: #1f2a37; --fac-bar-2: #111a24; --fac-accent: #38bdf8; }
        body.factory-body { margin: 0; background: var(--bg, #f6f7f9); }
        .factory-topbar {
            display: flex; align-items: center; gap: 1.5rem;
            background: var(--fac-bar); color: #e5edf5;
            padding: 0 1.25rem; height: 56px; position: sticky; top: 0; z-index: 20;
            box-shadow: 0 1px 0 rgba(0,0,0,0.25);
        }
        .factory-brand {
            font-weight: 700; font-size: 1.05rem; letter-spacing: -0.01em; white-space: nowrap;
        }
        .factory-brand span { color: var(--fac-accent); font-weight: 600; }
        .factory-nav { display: flex; gap: 0.25rem; flex: 1; }
        .factory-nav a {
            color: #b9c6d3; text-decoration: none; font-size: 0.9375rem; font-weight: 500;
            padding: 0.4rem 0.75rem; border-radius: 8px;
        }
        .factory-nav a:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .factory-nav a.is-active { background: rgba(56,189,248,0.15); color: #fff; }
        .factory-user { display: flex; align-items: center; gap: 0.9rem; font-size: 0.875rem; color: #b9c6d3; }
        .factory-user a { color: #e5edf5; text-decoration: none; font-weight: 600; }
        .factory-user a:hover { text-decoration: underline; }
        .factory-switch { margin: 0; }
        .factory-switch select {
            font: inherit; font-size: 0.8125rem; color: #e5edf5; cursor: pointer;
            background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18);
            border-radius: 8px; padding: 0.3rem 0.5rem; max-width: 12rem;
        }
        .factory-switch select option { color: #111; }
        .factory-back {
            border: 1px solid rgba(255,255,255,0.22); border-radius: 8px;
            padding: 0.3rem 0.6rem; font-size: 0.8125rem;
        }
        .factory-back:hover { background: rgba(255,255,255,0.08); text-decoration: none !important; }
        /* 1200px is a comfortable reading width for forms. Dense tables want the
           whole monitor instead — capping them just hides columns behind a
           scrollbar on a screen that had the room all along. */
        .factory-main { max-width: 1200px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
        .factory-main.is-wide { max-width: none; }
    </style>
</head>
<body class="factory-body">
<header class="factory-topbar">
    <div class="factory-brand"><?= e($factoryName) ?> <span>Factory</span></div>
    <nav class="factory-nav">
        <?php foreach ($factoryNavItems as $key => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $factoryNav === $key ? ' class="is-active"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="factory-user">
        <?php if (!function_exists('current_user_is_workstation') || !current_user_is_workstation()): ?>
            <a href="/dashboard/index.php" class="factory-back" title="Back to the main app">&larr; App</a>
        <?php endif; ?>
        <?php if ($factoryIsSuper && count($factoryChoices) > 1): ?>
            <form method="post" action="/factory/act-as.php" class="factory-switch" title="View another factory">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/factory/incoming-orders.php')) ?>">
                <select name="factory_id" onchange="this.form.submit()" aria-label="Viewing factory">
                    <?php foreach ($factoryChoices as $fc): ?>
                        <option value="<?= (int) $fc['id'] ?>"<?= (int) $fc['id'] === (int) $factoryActingId ? ' selected' : '' ?>>
                            <?= e((string) $fc['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <span><?= e((string) ($fu['full_name'] ?? 'Factory staff')) ?></span>
        <a href="/auth/logout.php">Log out</a>
    </div>
</header>
<main class="factory-main<?= $factoryWide ? ' is-wide' : '' ?>">
