<?php
declare(strict_types=1);

/**
 * LOCAL preview of a help guide, without logging in — for building and
 * checking guides on a dev machine (php -S 127.0.0.1:8090 -t <checkout>,
 * then /tools/guide_preview.php?g=<slug>). Renders exactly what
 * help/guide.php renders inside the app shell.
 *
 * Loopback only, and /tools is blocked on the live site (.htaccess).
 */

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';   // e() — no login is asked for

$GUIDES = require __DIR__ . '/../help/_guides.php';
$slug   = (string) ($_GET['g'] ?? '');
$g      = $GUIDES[$slug] ?? null;
$theme  = ($_GET['theme'] ?? '') === 'dark' ? 'dark' : 'light';
?><!doctype html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $g ? e($g['title']) : 'Guides' ?> &middot; preview</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <?php if ($g) { require __DIR__ . '/../help/_guide_head.php'; } ?>
    <style>body{ background:var(--bg-page, #f4f6f9); } .pv{ max-width:900px; margin:0 auto; padding:1.25rem 1rem 4rem; }</style>
</head>
<body>
<div class="pv">
<?php if ($g): ?>
    <?php require __DIR__ . '/../help/_guide_body.php'; ?>
    <?php if (isset($_GET['scene'])):
        // Screenshot mode: just the walkthrough, frozen on one scene —
        // finished (no &t) or t seconds into its timeline (&t=4.5).
        $shotScene = (int) $_GET['scene'];
        $shotT     = isset($_GET['t']) ? (float) $_GET['t'] : -1.0; ?>
        <style>
            .gd .backlink, .gd .page-header, .gd .lede, .gd .openbtn, .gd .sec-h, .gd section:nth-of-type(2),
            .gd .ttsrow, .gd .gd-chaps, .gd .gd-chaps-h, .gd .ttsnote{ display:none !important; }
            .pv{ padding-top:.5rem; } .gd section{ margin:0; }
        </style>
        <script>
        (function(){
            var n = <?= $shotScene ?>, t = <?= json_encode($shotT) ?>;
            var st = document.getElementById('gdStage'); if (!st) return;
            st.setAttribute('data-step', String(n));
            st.querySelectorAll('[data-scene]').forEach(function(sc){
                var on = (' ' + sc.getAttribute('data-scene') + ' ').indexOf(' ' + n + ' ') !== -1;
                sc.hidden = !on; sc.classList.remove('gd-play', 'gd-done');
                if (!on) return;
                if (t < 0) { sc.classList.add('gd-done'); return; }
                sc.classList.add('gd-play');
                sc.getAnimations({ subtree: true }).forEach(function(a){ a.pause(); a.currentTime = t * 1000; });
            });
        })();
        </script>
    <?php endif; ?>
<?php else: ?>
    <h1>Guide previews</h1>
    <ol>
        <?php foreach ($GUIDES as $k => $x): ?>
            <li><a href="?g=<?= e((string) $k) ?>"><?= e($x['title']) ?></a> <small>(<?= e((string) $k) ?><?= (int) ($x['v'] ?? 1) >= 2 ? ', v2' : '' ?>)</small></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
</div>
</body>
</html>
