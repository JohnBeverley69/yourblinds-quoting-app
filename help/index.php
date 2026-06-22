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

// ── Topics ────────────────────────────────────────────────────────────────
// Each: ['aud'=>all|admin|super, 'cat'=>section, 'title'=>.., 'keys'=>extra
//        search words, 'body'=>HTML].
$TOPICS = [
    // ---- Getting started ----
    ['all', 'Getting started', 'How the app flows', 'overview start begin pipeline funnel',
     '<p>The journey of a job runs: <strong>Quote → Order → Invoice → Payment</strong>.</p>
      <ul><li>Build a <strong>quote</strong> for a customer (or use <strong>InstaPrice</strong> for a quick figure).</li>
      <li>When the customer accepts, it becomes an <strong>order</strong>.</li>
      <li>Book the fit on the <strong>Calendar</strong>.</li>
      <li>Record <strong>payments</strong> (and the deposit) on the Payments page.</li></ul>'],

    // ---- Quoting ----
    ['all', 'Quoting', 'Building a quote', 'quote builder add blind product system band fabric width drop',
     '<p>In the quote builder you add one blind at a time: pick the <strong>product</strong> →
      <strong>system</strong> (variant) → <strong>band</strong> (price tier) → <strong>fabric / slat</strong> →
      enter <strong>width × drop</strong> and <strong>quantity</strong> → add any <strong>options</strong>.
      The price updates live. Add as many blinds as the job needs, then send or print.</p>'],
    ['all', 'Quoting', 'InstaPrice (quick price)', 'instaprice quick price no customer estimate',
     '<p><strong>InstaPrice</strong> (sidebar) gives a price with no customer attached — handy on the phone or
      a site visit. Same engine as the quote builder. Hit <em>“Turn into full quote”</em> to keep it.</p>'],
    ['admin', 'Quoting', 'Hide prices on the customer quote', 'hide price per blind line total settings',
     '<p>In <strong>Settings → Quoting</strong>, the <em>“Show the price of each blind”</em> tick controls whether
      the customer’s PDF / online quote shows a price per blind. Untick it to show only the overall total.</p>'],
    ['admin', 'Quoting', 'Measurement units', 'mm cm metres inches measurement unit',
     '<p>Set your company default in <strong>Settings → Quoting</strong> (mm / cm / m / in). Sizes are always stored
      in millimetres; the unit only changes how you type and read them. You can override the unit per quote.</p>'],
    ['admin', 'Quoting', 'Bank details on quotes (how customers pay)', 'bank transfer sort code account number payment details pay invoice',
     '<p>In <strong>Settings → Quoting → “Bank details for customer payments”</strong>, enter your account name,
      sort code and account number (plus an optional note). They then print a <strong>“How to pay — bank transfer”</strong>
      block on the customer’s quote / invoice (PDF + online), with the quote number as the suggested reference.
      Leave the fields blank to hide the block.</p>'],

    // ---- Calendar / customers ----
    ['all', 'Calendar & customers', 'Booking jobs on the calendar', 'calendar appointment booking fit day week maps waze',
     '<p>The <strong>Calendar</strong> shows your fits by month / week / day. Book or open an appointment, link it to an
      order, and use the <strong>Maps / Waze</strong> buttons for directions. The day/week view stretches only where
      bookings genuinely overlap.</p>'],
    ['all', 'Calendar & customers', 'Customers & postcode lookup', 'customer manager address postcode lookup',
     '<p>Manage customers in <strong>Customers</strong>. On the address fields, the <strong>postcode lookup</strong>
      finds the address for you (where enabled on your plan).</p>'],

    // ---- Products & pricing ----
    ['admin', 'Products & pricing', 'How pricing is structured', 'product system band price table grid model',
     '<p>The model is <strong>Product → System → Band → Price table</strong>. A <strong>system</strong> is a variant
      (e.g. a slat size or motorised). A <strong>band</strong> is a price tier. Each <strong>fabric/slat</strong> carries
      a band, and each <strong>price table</strong> is a width × drop grid for one (system, band). The band code is the
      link between a fabric and its prices.</p>'],
    ['admin', 'Products & pricing', 'Setting up a product (wizard)', 'wizard new product setup steps systems fabrics price tables',
     '<p>The <strong>setup wizard</strong> walks you through Name → Systems → Fabrics → Price tables.</p>
      <p><strong>Tip — pricing first:</strong> on the Fabrics step there’s a <em>“Price tables first →”</em> button.
      Use it to import your price grids first; the bands you import then <strong>auto-suggest</strong> in the fabric’s
      Band box, so you don’t type band names twice.</p>'],
    ['admin', 'Products & pricing', 'Pricing modes', 'width only per slat per square metre sqm shutter venetian headrail',
     '<p>A product prices one of four ways: the normal <strong>width × drop grid</strong>; <strong>width only</strong>
      (headrails / tracks); <strong>per slat</strong> (vertical fabric replacement); or <strong>per m²</strong>
      (shutters). Set it on the product’s edit page or wizard step 1.</p>'],
    ['admin', 'Products & pricing', 'Band codes can be descriptive', 'band code length name tape herringbone bamboo 60 characters',
     '<p>Band codes aren’t limited to A/B/C — they can be full names up to <strong>60 characters</strong>
      (e.g. “50mm Bamboo &amp; Gloss Herringbone Tape”). The same band must appear on the fabric/slat and on its
      price table for pricing to resolve.</p>'],
    ['admin', 'Products & pricing', 'Bulk-importing price tables', 'bulk import excel multi band worksheet sheet picker spreadsheet',
     '<p>On a system’s Price tables page, <strong>Bulk import (multiple bands)</strong> reads a multi-band Excel file —
      each band block starts with a <code>Band X</code> row. If the file has <strong>several worksheets</strong> with
      bands (e.g. one per slat size), you’ll be asked <strong>which worksheet</strong> to import into this system.
      Re-importing replaces that band’s prices.</p>'],
    ['admin', 'Products & pricing', 'Selecting many fabrics at once', 'shift click range select set band on selected bulk fabrics',
     '<p>On a product’s <strong>Fabrics</strong> page you can tick rows and use <em>Set band on selected</em> /
      <em>Delete selected</em>. To grab a run quickly: tick one row, then <strong>Shift-click</strong> another — every
      row between is selected. The search box filters the list first.</p>'],
    ['admin', 'Products & pricing', 'Order supplier vs Library supplier', 'supplier order purchase po library catalogue prefix confusion',
     '<p>Two different “suppliers”:</p>
      <ul><li><strong>Order supplier</strong> (on the product, and in <strong>Settings → Suppliers</strong>) = who you
      <em>order stock from</em>, for purchase orders.</li>
      <li><strong>Library supplier</strong> (master catalogue) = the catalogue grouping, matched by the product’s
      <strong>name prefix</strong>, not by the Order supplier field.</li></ul>
      <p>Settings → Suppliers is the single source for the Order-supplier dropdown — delete a stray there and it’s gone.</p>'],
    ['admin', 'Products & pricing', 'Markup vs margin (how you enter your profit)', 'markup margin profit basis pricing settings convert default',
     '<p>You can enter your profit as <strong>markup</strong> or <strong>margin</strong> — set which on
      <strong>Settings → Quoting → Default margins</strong> (“Enter your margins as”).</p>
      <ul><li><strong>Markup</strong> is added on top of your cost — cost&nbsp;+&nbsp;50% = sell.</li>
      <li><strong>Margin</strong> is the profit slice of the <em>sell</em> price — a 50% margin means cost is half the sell.</li></ul>
      <p>The customer price is identical either way; this only changes which number you type. Whatever you pick is used
      everywhere you set a rate — the default margins, per-product overrides, and InstaPrice. As you type, a small
      “≈ … markup” line shows the equivalent so you can sense-check it. You can switch basis any time
      <strong>without re-pricing anything</strong> — nothing already saved changes.</p>'],

    // ---- Fabric Library (super) ----
    ['super', 'Fabric Library', 'Fabric Library structure', 'fabric library supplier range fabrics group manufacturer',
     '<p>The Fabric Library holds the cloth, structured as <strong>Supplier → Range → Fabrics</strong>.
      Create a <strong>supplier group</strong> (e.g. Decora) and drag each range’s ⠿ grip into it. Inside a range you can
      also group fabrics under headings. Everything is non-destructive — nothing merges.</p>'],
    ['super', 'Fabric Library', 'Importing fabrics & groups carrying through', 'import fabrics excel pull into product carry group',
     '<p><strong>Import fabrics</strong> loads a manufacturer’s range from a spreadsheet. When you pull library fabrics
      into a product, their <strong>group rides along</strong> and shows as a Group column on the product’s Fabrics page.</p>'],

    // ---- Master catalogue (super) ----
    ['super', 'Master catalogue', 'Catalogue & pushing to clients', 'master catalogue push prefix library suppliers tenants',
     '<p>The <strong>Master Catalogue</strong> is the source every client is built from. Products are filed under a
      supplier by their <strong>name prefix</strong> (e.g. “Bev …”). <strong>Push updates</strong> copies a supplier’s
      products into the tenants that subscribe to it.</p>'],

    // ---- Accounts ----
    ['admin', 'Accounts', 'Recording payments & deposits', 'payment deposit record ledger received outstanding',
     '<p>On <strong>Payments</strong>, log each payment against an order (method, reference, date). The deposit is a
      payment flagged as a deposit. The page shows received vs outstanding per order.</p>'],
    ['admin', 'Accounts', 'Export to Xero / QuickBooks (CSV)', 'accounts csv export xero quickbooks sage invoices payments accounting',
     '<p>On <strong>Payments</strong>, admins get <strong>Export invoices (CSV)</strong> and
      <strong>Export payments (CSV)</strong>. Invoices export one row per order line (net of VAT) in Xero’s import shape;
      they default to account code <strong>200 (Sales)</strong> and <strong>20% VAT</strong> — remap on import if your
      chart of accounts differs. Use the <strong>This month / Last month</strong> buttons to scope the period in one
      click.</p>'],

    // ---- Settings & admin ----
    ['admin', 'Settings', 'Quote defaults & suppliers', 'settings vat deposit prefix supplier email terms legal',
     '<p><strong>Settings</strong> holds your quote prefix, VAT %, default deposit, measurement unit, the
      <em>Order suppliers</em> list (with their order emails) and your Terms / Privacy text.</p>'],
    ['admin', 'Settings', 'Plans & billing', 'billing tier bronze silver gold platinum plan subscription add-on cancel',
     '<p><strong>Billing</strong> shows your plan: Bronze (core) → Silver (+Maps/Postcode) → Gold (+Accounts) →
      Platinum (+price-library). Each tier includes everything below it.</p>
      <p>If you <strong>cancel</strong>, billing stops but you keep that plan\'s features until the end of the
      period you\'ve already paid for — they switch off on that date, not immediately.</p>'],

    // ---- Safety (super) ----
    ['super', 'Backups & safety', 'Backups and recovery', 'backup restore cloudways recover data loss snapshot',
     '<p>The host takes an automatic <strong>daily database backup</strong> — the lifeline if data is lost.
      <strong>Master Admin → Backup</strong> also downloads a manual SQL copy (and per-tenant exports). Recovery from a
      host backup is a full restore, so do a manual backup first.</p>'],
    ['super', 'Backups & safety', 'Go-live checklist', 'launch go live readiness paypal sandbox test data checklist',
     '<p><strong>Master Admin → Go-live checklist</strong> shows pre-launch readiness: it auto-checks PayPal mode
      (sandbox vs live), app environment, API keys, terms, VAT and backup age; scans this account for left-over
      <em>test/demo</em> products, suppliers and customers; and lists the manual steps (incl. switching PayPal to live).</p>'],
    ['super', 'Backups & safety', 'Wipe products (handle with care)', 'wipe products delete bulk master protect',
     '<p><strong>Wipe products</strong> bulk-deletes matching products across tenants. The <strong>master catalogue</strong>
      is protected — it’s never pre-ticked and is excluded unless you explicitly tick “include the master catalogue”.</p>'],
];

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
