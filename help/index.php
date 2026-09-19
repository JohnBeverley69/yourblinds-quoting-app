<?php
declare(strict_types=1);

/**
 * Help & guide — a searchable, in-app operator manual.
 *
 * Self-contained: topics live in the $TOPICS array below; the search box
 * filters them client-side by title + keywords + body. Topics are tagged by
 * audience (all / admin / super) and only the ones the current user can act on
 * are rendered. Keep entries short and task-focused; update when features land.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$user    = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$isSuper = function_exists('is_super_admin') && is_super_admin();

// Support contact shown on the page.
$SUPPORT_EMAIL = 'hello@yourblinds.uk';

// ── Video tutorials — super-admin curates, everyone sees ────────────────────
$pdo = db();
$videoTableOk = true;
try { $pdo->query('SELECT 1 FROM help_videos LIMIT 0'); }
catch (Throwable $e) { $videoTableOk = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSuper && $videoTableOk) {
    csrf_check();
    $act = (string) ($_POST['_action'] ?? '');
    try {
        if ($act === 'add_video' || $act === 'update_video') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $url   = trim((string) ($_POST['url'] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
            if ($title === '' || $url === '') {
                $_SESSION['flash_error'] = 'A video needs a title and a link.';
            } elseif ($act === 'add_video') {
                $next = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM help_videos')->fetchColumn();
                $pdo->prepare('INSERT INTO help_videos (title, url, sort_order) VALUES (?, ?, ?)')
                    ->execute([mb_substr($title, 0, 160), mb_substr($url, 0, 500), $next]);
                $_SESSION['flash_success'] = 'Video added.';
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    $pdo->prepare('UPDATE help_videos SET title = ?, url = ? WHERE id = ?')
                        ->execute([mb_substr($title, 0, 160), mb_substr($url, 0, 500), $id]);
                    $_SESSION['flash_success'] = 'Video updated.';
                }
            }
        } elseif ($act === 'delete_video') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM help_videos WHERE id = ?')->execute([$id]);
                $_SESSION['flash_success'] = 'Video removed.';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /help/index.php');
    exit;
}

$videos = $videoTableOk
    ? $pdo->query('SELECT id, title, url FROM help_videos ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC)
    : [];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/** can the current user use a topic of this audience? */
$canSee = static function (string $aud) use ($isAdmin, $isSuper): bool {
    if ($aud === 'super') return $isSuper;
    if ($aud === 'admin') return $isAdmin || $isSuper;
    return true; // 'all'
};

// ── Guided walkthroughs — the step-by-step "dummies' guide" pages ────────────
$guides = [];
foreach (require __DIR__ . '/_guides.php' as $slug => $gd) {
    if ($canSee($gd['aud'])) $guides[$slug] = $gd;
}

// ── Topics ────────────────────────────────────────────────────────────────
// Each: ['aud'=>all|admin|super, 'cat'=>section, 'title'=>.., 'keys'=>extra
//        search words, 'body'=>HTML].
$TOPICS = require __DIR__ . '/_topics.php';

// Sections in display order.
$ORDER = ['Getting started', 'Quoting', 'Calendar & customers', 'Products & pricing',
          'Fabric Library', 'Master catalogue', 'Accounts', 'Settings', 'Backups & safety'];

// Group the topics the user can see.
$bySection = [];
foreach ($TOPICS as [$aud, $cat, $title, $keys, $body]) {
    if (!$canSee($aud)) continue;
    $bySection[$cat][] = ['title' => $title, 'keys' => $keys, 'body' => $body];
}

$activeNav = 'help';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Help &amp; guide &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .help-search { position: relative; max-width: 32rem; margin: 0 0 0.5rem; }
        .help-search input {
            width: 100%; padding: 0.6rem 0.75rem; font: inherit; font-size: 1rem;
            border: 1px solid var(--border-strong); border-radius: 10px; background: var(--bg-input);
        }
        .help-meta { color: var(--text-faint); font-size: 0.8125rem; margin: 0 0 1.25rem; }
        .help-section { margin: 0 0 1.5rem; }
        .help-section h2 {
            font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em;
            color: var(--text-faint); font-weight: 700; margin: 0 0 0.6rem;
        }
        .help-card {
            border: 1px solid var(--border); border-radius: 10px; background: var(--bg-card);
            padding: 0.85rem 1rem; margin: 0 0 0.6rem;
        }
        .help-card > h3 { margin: 0; font-size: 1rem; color: var(--text-primary); cursor: pointer;
                          display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .help-card > h3 .tw { color: var(--text-faint); font-size: 0.75rem; transition: transform 120ms; }
        .help-card.open > h3 .tw { transform: rotate(90deg); }
        .help-card .body { margin: 0.6rem 0 0; color: var(--text-muted); font-size: 0.9375rem; line-height: 1.55; }
        .help-card .body ul { margin: 0.4rem 0 0; padding-left: 1.2rem; }
        .help-card .body p:first-child { margin-top: 0; }
        .help-card:not(.open) .body { display: none; }
        .help-card code { background: var(--bg-subtle-2); padding: 0.05rem 0.35rem; border-radius: 4px; font-size: 0.85em; }
        .help-empty { color: var(--text-faint); padding: 1rem 0; }
        .help-tools { display: flex; gap: 0.75rem; margin: 0 0 1rem; font-size: 0.8125rem; }
        .help-tools button { background: none; border: 0; color: var(--link); cursor: pointer; text-decoration: underline; padding: 0; font: inherit; }

        .help-contact { background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 10px; padding: 0.6rem 0.9rem; margin: 0 0 1rem; font-size: 0.9375rem; color: var(--text-muted); }
        .help-vid-title { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.6rem; }
        .vid-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: 0.6rem; }
        .vid-card { border: 1px solid var(--border); border-radius: 10px; padding: 0.7rem 0.85rem; background: var(--bg-card); }
        .vid-card .vid-title { font-weight: 600; color: var(--text-primary); margin-bottom: 0.3rem; }
        .vid-watch { color: var(--link); font-weight: 600; text-decoration: none; }
        .vid-watch:hover { text-decoration: underline; }
        .vid-edit { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.5rem; }
        .vid-edit input { padding: 0.25rem 0.4rem; border: 1px solid var(--border-strong); border-radius: 6px; font: inherit; font-size: 0.8125rem; background: var(--bg-input); flex: 1 1 8rem; }
        .vid-del-form { display: inline; }
        .vid-del { background: none; border: 0; color: #b91c1c; cursor: pointer; font-size: 0.75rem; text-decoration: underline; padding: 0.1rem 0; margin-top: 0.25rem; }
        .vid-add { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.85rem; }
        .vid-add input { padding: 0.4rem 0.55rem; border: 1px solid var(--border-strong); border-radius: 7px; font: inherit; background: var(--bg-input); }
        .vid-add input[name="title"] { flex: 1 1 16rem; }
        .vid-add input[name="url"] { flex: 2 1 20rem; }

        .guide-group { border: 1px solid var(--border); border-radius: 12px; margin: 0 0 0.7rem; background: var(--bg-card); overflow: hidden; }
        .guide-group > summary { cursor: pointer; list-style: none; padding: 0.7rem 0.95rem; font-weight: 700; font-size: 1rem;
            color: var(--text-primary); display: flex; align-items: center; gap: 0.55rem; }
        .guide-group > summary::-webkit-details-marker { display: none; }
        .guide-group > summary::before { content: "\25B8"; color: var(--text-faint); font-size: 0.8rem; transition: transform 120ms; }
        .guide-group[open] > summary::before { transform: rotate(90deg); }
        .gg-count { font-size: 0.75rem; font-weight: 600; color: var(--text-faint); background: var(--bg-subtle-2); border-radius: 999px; padding: 0.05rem 0.5rem; }
        .gg-tab { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-faint); font-weight: 700; padding: 0.5rem 0.95rem 0.3rem; }
        .guide-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 0.6rem; padding: 0 0.95rem 0.75rem; }
        .gg-tab + .guide-grid { padding-top: 0; }
        .guide-card { display: flex; flex-direction: column; gap: 0.15rem; text-decoration: none;
            border: 1px solid var(--border); border-radius: 10px; padding: 0.75rem 0.9rem; background: var(--bg-card);
            transition: border-color 120ms, transform 120ms; }
        .guide-card:hover { border-color: var(--link); transform: translateY(-1px); }
        .guide-title { font-size: 0.98rem; font-weight: 700; color: var(--text-primary); }
        .guide-blurb { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; margin-top: 0.15rem; }
        .guide-go { font-size: 0.8125rem; font-weight: 600; color: var(--link); margin-top: 0.45rem; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Help &amp; guide</h1>
                <p class="page-subtitle">How to use YourBlinds. Search, or click a topic to open it.</p>
            </div>
        </div>

        <?php if ($flashMsg): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <!-- Support contact -->
        <div class="help-contact">
            Need a hand? Email <a href="mailto:<?= e($SUPPORT_EMAIL) ?>"><strong><?= e($SUPPORT_EMAIL) ?></strong></a>
            and we'll help you out.
        </div>

        <!-- Video tutorials -->
        <?php if ($videos || $isSuper): ?>
            <section class="section">
                <h2 class="help-vid-title">Video tutorials</h2>
                <?php if (!$videos): ?>
                    <p class="help-empty" style="padding:0.25rem 0 0.75rem">No videos yet.<?= $isSuper ? ' Add the first one below.' : '' ?></p>
                <?php else: ?>
                    <div class="vid-grid">
                        <?php foreach ($videos as $v): $vUrl = (string) $v['url']; $safe = (bool) preg_match('#^https?://#i', $vUrl); ?>
                            <div class="vid-card">
                                <div class="vid-title"><?= e((string) $v['title']) ?></div>
                                <?php if ($safe): ?>
                                    <a class="vid-watch" href="<?= e($vUrl) ?>" target="_blank" rel="noopener noreferrer">Watch &rsaquo;</a>
                                <?php else: ?>
                                    <span style="color:var(--text-faint);font-size:0.8125rem"><?= e($vUrl) ?></span>
                                <?php endif; ?>
                                <?php if ($isSuper): ?>
                                    <form method="post" action="/help/index.php" class="vid-edit">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="update_video">
                                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                        <input type="text" name="title" value="<?= e((string) $v['title']) ?>" maxlength="160" placeholder="Title">
                                        <input type="url" name="url" value="<?= e($vUrl) ?>" maxlength="500" placeholder="https://…">
                                        <button type="submit" class="btn btn-secondary" style="font-size:.75rem;padding:.2rem .5rem">Save</button>
                                    </form>
                                    <form method="post" action="/help/index.php" class="vid-del-form"
                                          data-confirm="Remove &quot;<?= e((string) $v['title']) ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_action" value="delete_video">
                                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                        <button type="submit" class="vid-del">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isSuper): ?>
                    <?php if (!$videoTableOk): ?>
                        <p class="help-empty" style="padding:0.5rem 0 0">
                            Run <a href="/migrate_help_videos.php"><code>/migrate_help_videos.php</code></a> (super-admin) to enable the video list.
                        </p>
                    <?php else: ?>
                        <form method="post" action="/help/index.php" class="vid-add">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="add_video">
                            <input type="text" name="title" maxlength="160" placeholder="Video title (e.g. Building your first quote)" required>
                            <input type="url" name="url" maxlength="500" placeholder="Paste the link (YouTube / Vimeo / Loom…)" required>
                            <button type="submit" class="btn btn-primary" style="font-size:.8125rem;padding:.35rem .8rem">+ Add video</button>
                        </form>
                        <p style="color:var(--text-faint);font-size:0.8125rem;margin:.4rem 0 0">You're the only one who sees these add/edit controls; everyone sees the videos.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- Guided walkthroughs — grouped by area (collapsible), sub-grouped by tab -->
        <?php if ($guides):
            // eyebrow convention is "Area · Tab" (e.g. "Settings · Company").
            $guidesByArea = [];
            foreach ($guides as $slug => $gd) {
                $parts = array_map('trim', explode('·', (string) ($gd['eyebrow'] ?? 'Guides')));
                $area  = ($parts[0] ?? '') !== '' ? $parts[0] : 'Guides';
                $tab   = count($parts) > 1 ? (string) end($parts) : '';
                $guidesByArea[$area][$tab][$slug] = $gd;
            }
        ?>
            <section class="section">
                <h2 class="help-vid-title">Step-by-step guides</h2>
                <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.7rem">
                    Animated walk-throughs with a voice-over you can play aloud — written for a first-timer.
                </p>
                <?php foreach ($guidesByArea as $area => $tabs):
                    $areaCount = array_sum(array_map('count', $tabs)); ?>
                    <details class="guide-group" open>
                        <summary><?= e($area) ?> <span class="gg-count"><?= (int) $areaCount ?></span></summary>
                        <?php foreach ($tabs as $tab => $items): ?>
                            <?php if ($tab !== ''): ?><div class="gg-tab"><?= e($tab) ?></div><?php endif; ?>
                            <div class="guide-grid">
                                <?php foreach ($items as $slug => $gd): ?>
                                    <a class="guide-card" href="/help/guide.php?g=<?= e(rawurlencode($slug)) ?>">
                                        <span class="guide-title"><?= e($gd['title']) ?></span>
                                        <span class="guide-blurb"><?= e($gd['blurb'] ?? '') ?></span>
                                        <span class="guide-go">Open guide &rarr;</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <div class="help-search">
            <input type="search" id="help-search" placeholder="Search help — e.g. band, import, export, supplier, deposit…" autofocus>
        </div>
        <p class="help-meta" id="help-meta"></p>
        <div class="help-tools">
            <button type="button" id="help-expand">Expand all</button>
            <button type="button" id="help-collapse">Collapse all</button>
        </div>

        <div id="help-results">
            <?php foreach ($ORDER as $section): if (empty($bySection[$section])) continue; ?>
                <div class="help-section" data-section>
                    <h2><?= e($section) ?></h2>
                    <?php foreach ($bySection[$section] as $t):
                        $blob = strtolower($section . ' ' . $t['title'] . ' ' . $t['keys'] . ' ' . strip_tags($t['body']));
                    ?>
                        <div class="help-card" data-search="<?= e($blob) ?>">
                            <h3><span><?= e($t['title']) ?></span> <span class="tw">&#9654;</span></h3>
                            <div class="body"><?= $t['body'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="help-empty" id="help-empty" hidden>No help topics match — try another word.</p>
    </main>
</div>

<script>
(function () {
    var search   = document.getElementById('help-search');
    var meta     = document.getElementById('help-meta');
    var empty    = document.getElementById('help-empty');
    var cards    = Array.prototype.slice.call(document.querySelectorAll('.help-card'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('[data-section]'));

    // Click a card heading to open/close it.
    cards.forEach(function (c) {
        c.querySelector('h3').addEventListener('click', function () { c.classList.toggle('open'); });
    });
    document.getElementById('help-expand').addEventListener('click', function () {
        cards.forEach(function (c) { if (!c.hidden) c.classList.add('open'); });
    });
    document.getElementById('help-collapse').addEventListener('click', function () {
        cards.forEach(function (c) { c.classList.remove('open'); });
    });

    function apply() {
        var q = (search.value || '').trim().toLowerCase();
        var words = q ? q.split(/\s+/) : [];
        var shown = 0;
        cards.forEach(function (c) {
            var hay = c.getAttribute('data-search') || '';
            var match = words.every(function (w) { return hay.indexOf(w) !== -1; });
            c.hidden = !match;
            // Auto-open matches when searching so the answer is visible.
            c.classList.toggle('open', match && q !== '');
            if (match) shown++;
        });
        // Hide a section whose cards are all filtered out.
        sections.forEach(function (s) {
            var any = s.querySelector('.help-card:not([hidden])');
            s.hidden = !any;
        });
        empty.hidden = shown !== 0;
        meta.textContent = q
            ? shown + ' topic' + (shown === 1 ? '' : 's') + ' match “' + q + '”'
            : cards.length + ' topics';
    }
    search.addEventListener('input', apply);
    apply();
})();
</script>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
