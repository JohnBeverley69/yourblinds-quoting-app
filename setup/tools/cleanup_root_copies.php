<?php
declare(strict_types=1);

/**
 * One-off: delete the OLD copies of the one-off scripts left in the site root.
 *
 * On 2026-10-09 (PR #943) every migrate_*, seed_* and seven tool scripts moved
 * from the site root into setup/{migrations,seeds,tools}, and seed_data/ into
 * setup/seeds/. The live deploy added the new folder but left the old root copies
 * behind. This page removes a root file ONLY when the same file now exists under
 * setup/ — so nothing that hasn't moved can be touched, and the app's own root
 * files (bootstrap, index, mailer …) are never candidates.
 *
 * Opening the page lists what would go and deletes nothing. "Delete these files"
 * (super-admin, CSRF) does the deleting and reports each one.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth/middleware.php';
requireSuperAdmin();

$root = dirname(__DIR__, 2);
// The app's own root files — never deleted, whatever else happens.
$keep = ['bootstrap.php', 'cron_backup.php', 'db.php', 'how-it-works.php', 'index.php', 'mailer.php', 'system_check.php', 'welcome.php'];

// Candidates: a root file with the same name as one now in setup/.
$files = [];
foreach (['migrations', 'seeds', 'tools'] as $d) {
    foreach (glob("$root/setup/$d/*.php") ?: [] as $new) {
        $name = basename($new);
        if (in_array($name, $keep, true) || $name === basename(__FILE__)) continue;
        if (is_file("$root/$name")) $files[] = $name;
    }
}
sort($files);
// seed_data/: each root file that also exists in setup/seeds/seed_data/.
$data = [];
if (is_dir("$root/seed_data") && is_dir("$root/setup/seeds/seed_data")) {
    foreach (glob("$root/seed_data/*") ?: [] as $f) {
        if (is_file($f) && is_file("$root/setup/seeds/seed_data/" . basename($f))) $data[] = basename($f);
    }
}

$done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $done = ['ok' => [], 'fail' => []];
    foreach ($files as $f) (@unlink("$root/$f") ? $done['ok'][] = $f : $done['fail'][] = $f);
    foreach ($data as $f) (@unlink("$root/seed_data/$f") ? $done['ok'][] = "seed_data/$f" : $done['fail'][] = "seed_data/$f");
    if (is_dir("$root/seed_data") && count(glob("$root/seed_data/*") ?: []) === 0) {
        @rmdir("$root/seed_data") ? $done['ok'][] = 'seed_data/ (empty folder)' : $done['fail'][] = 'seed_data/ (folder)';
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?><!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clean up old script copies</title>
<body style="font-family:system-ui,sans-serif;max-width:720px;margin:2.5rem auto;padding:0 16px;line-height:1.5">
<h1 style="font-size:1.35rem">Clean up old script copies</h1>
<?php if ($done !== null): ?>
  <p><b><?= count($done['ok']) ?></b> deleted<?= $done['fail'] ? ', <b style="color:#b91c1c">' . count($done['fail']) . ' could not be deleted</b>' : '' ?>.</p>
  <?php if ($done['fail']): ?>
    <p>These couldn’t be removed (file permissions) — delete them in Cloudways’ File Manager:</p>
    <ul><?php foreach ($done['fail'] as $f): ?><li><code><?= e($f) ?></code></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <details><summary>Deleted</summary><ul><?php foreach ($done['ok'] as $f): ?><li><code><?= e($f) ?></code></li><?php endforeach; ?></ul></details>
  <p>The copies in <code>setup/</code> are untouched. Reload this page — it should now say there’s nothing to clean up.</p>
<?php elseif (!$files && !$data): ?>
  <p>Nothing to clean up — the site root has no old copies of scripts that now live in <code>setup/</code>.</p>
<?php else: ?>
  <p>These are old copies in the site root of scripts that now live in <code>setup/</code>. Each is deleted only because the same
     file exists there. Nothing is deleted until you press the button.</p>
  <p><b><?= count($files) ?></b> scripts<?= $data ? ' and <b>' . count($data) . '</b> files in <code>seed_data/</code>' : '' ?>:</p>
  <details><summary>Show the list</summary>
    <ul style="columns:2"><?php foreach ($files as $f): ?><li><code><?= e($f) ?></code></li><?php endforeach; ?>
    <?php foreach ($data as $f): ?><li><code>seed_data/<?= e($f) ?></code></li><?php endforeach; ?></ul>
  </details>
  <form method="post" style="margin-top:1rem">
    <?= csrf_field() ?>
    <button type="submit" style="font-size:1rem;padding:.55rem 1.2rem;background:#b91c1c;color:#fff;border:0;border-radius:8px;cursor:pointer">Delete these files</button>
  </form>
<?php endif; ?>
</body>
