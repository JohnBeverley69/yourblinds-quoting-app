<?php
declare(strict_types=1);

/**
 * Help & guide — step-by-step guided walkthroughs ("dummies' guide").
 *
 * A guide is a full page: an animated walkthrough of the real screen, the
 * written steps, and a narration script you can play aloud (free browser
 * text-to-speech, defaulting to the "Google UK English Female" voice — or,
 * where the guide has been recorded with tools/guide_voice.php, a natural
 * recorded voice (Alice), one clip per script line).
 *
 * Guides live in the $GUIDES registry below, keyed by slug (?g=slug). Add a
 * section by adding an entry — the Help & guide index links to any guide whose
 * slug it references. Keep the written steps matched to the real screen.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/guide_clips.php';

requireLogin();

$user    = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$isSuper = function_exists('is_super_admin') && is_super_admin();

/** can the current user open a guide of this audience? */
$canSee = static function (string $aud) use ($isAdmin, $isSuper): bool {
    if ($aud === 'super') return $isSuper;
    if ($aud === 'admin') return $isAdmin || $isSuper;
    // Factory Console guides: the factory's office staff (and the super-admin), never an ordinary business.
    if ($aud === 'factory') return $isSuper || (function_exists('factory_console_user') && factory_console_user());
    return true;
};

// Guide registry (shared with help/index.php's guide list).
$GUIDES = require __DIR__ . '/_guides.php';

$slug = (string) ($_GET['g'] ?? '');
$g    = $GUIDES[$slug] ?? null;
if ($g === null || !$canSee($g['aud'])) {
    http_response_code($g === null ? 404 : 403);
    $notFound = true;
}

$activeNav = 'help';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($notFound) ? 'Guide not found' : e($g['title']) ?> &middot; Help &amp; guide</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <?php require __DIR__ . '/_guide_head.php'; ?>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
    <?php if (isset($notFound)): ?>
        <div class="page-header"><div><h1 class="page-title">Guide not found</h1></div></div>
        <p><a href="/help/index.php">&larr; Back to Help &amp; guide</a></p>
    <?php else: ?>
        <?php require __DIR__ . '/_guide_body.php'; ?>
    <?php endif; ?>
    </main>
</div>
</body>
</html>
