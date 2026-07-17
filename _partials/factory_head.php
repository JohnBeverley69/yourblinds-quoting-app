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

// Nav grows as factory features land; the queue is the first.
$factoryNavItems = [
    'incoming'   => ['/factory/incoming-orders.php', 'Incoming Orders'],
    'floor'      => ['/factory/floor.php',           'Floor'],
    'routes'     => ['/factory/routes.php',          'Routes'],
    'build'      => ['/factory/build-rules.php',     'Build rules'],
    'allowances' => ['/factory/allowances.php',      'Allowances'],
    'worksheets' => ['/factory/worksheets.php',      'Worksheets'],
    'labelsheet' => ['/factory/label-test-sheet.php', 'Label sheet'],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($factoryTitle) ?> &middot; Beverley Factory</title>
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
        /* 1200px is a comfortable reading width for forms. Dense tables want the
           whole monitor instead — capping them just hides columns behind a
           scrollbar on a screen that had the room all along. */
        .factory-main { max-width: 1200px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
        .factory-main.is-wide { max-width: none; }
    </style>
</head>
<body class="factory-body">
<header class="factory-topbar">
    <div class="factory-brand">Beverley <span>Factory</span></div>
    <nav class="factory-nav">
        <?php foreach ($factoryNavItems as $key => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $factoryNav === $key ? ' class="is-active"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="factory-user">
        <span><?= e((string) ($fu['full_name'] ?? 'Factory staff')) ?></span>
        <a href="/auth/logout.php">Log out</a>
    </div>
</header>
<main class="factory-main<?= $factoryWide ? ' is-wide' : '' ?>">
