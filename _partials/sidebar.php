<?php
declare(strict_types=1);

/**
 * Shared sidebar + mobile navigation drawer.
 *
 * Includers must have already required bootstrap.php and auth/middleware.php
 * (so `e()` is defined) and called requireLogin() / requireAdmin().
 *
 * Required scope:
 *   $user       array  current_user() row — uses full_name, company_name, role
 *
 * Optional scope (sensible defaults applied if missing):
 *   $isAdmin    bool   defaults to ($user['role'] === 'admin')
 *   $dashHref   string defaults to /admin/index.php (admin) or /quote-builder/index.php
 *   $dashTag    string defaults to 'Admin Console' or 'Trade Portal'
 *   $activeNav  string one of: calendar, dashboard, new-quote, quote-history,
 *                      customers, pricing, users, settings. Empty = no highlight.
 */

$isAdmin      = $isAdmin      ?? (($user['role'] ?? '') === 'admin');
$isSuperAdmin = $isSuperAdmin ?? (bool) ($user['is_super_admin'] ?? false);
$dashHref     = $dashHref     ?? ($isAdmin ? '/admin/index.php' : '/quote-builder/index.php');
$dashTag      = $dashTag      ?? ($isAdmin ? 'Admin Console'    : 'Trade Portal');
$activeNav    = $activeNav    ?? '';

// [href, label, visible]. Order = display order.
$navLinks = [
    'calendar'      => ['/calendar/index.php',         'Calendar',      true],
    'dashboard'     => [$dashHref,                     'Dashboard',     true],
    'new-quote'     => ['/quote-builder/new.php',      'New Quote',     true],
    'quote-history' => ['/quote-history/index.php',    'Quote History', true],
    'customers'     => ['/customer-manager/index.php', 'Customers',     true],
    'products'      => ['/admin/products/index.php',   'Products',      $isAdmin],
    'users'         => ['/admin/users.php',            'Users',         $isAdmin],
    'settings'      => ['/admin/settings.php',         'Settings',      $isAdmin],
    'master-admin'  => ['/master-admin/index.php',     'Master Admin',  $isSuperAdmin],
];
?>
    <input type="checkbox" id="navToggle" class="nav-toggle-input">
    <label class="nav-fab" for="navToggle" aria-label="Open menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
    </label>
    <label class="nav-close" for="navToggle" aria-label="Close menu" tabindex="0">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </label>
    <label class="nav-backdrop" for="navToggle" aria-hidden="true"></label>

    <aside class="app-sidebar" aria-label="Primary navigation">
        <div class="app-sidebar-brand">
            <a href="/calendar/index.php" class="app-brand-mark">Your<span class="accent">Blinds</span></a>
            <span class="app-brand-tag"><?= e($dashTag) ?></span>
        </div>
        <div class="app-sidebar-user">
            <div class="app-sidebar-user-name"><?= e($user['full_name']) ?></div>
            <div class="app-sidebar-user-meta">
                <?= e($user['company_name']) ?> &middot; <?= e($user['role']) ?>
            </div>
        </div>
        <nav class="app-sidebar-nav">
<?php foreach ($navLinks as $navKey => [$navHref, $navLabel, $navShow]): ?>
<?php if (!$navShow) continue; ?>
            <a href="<?= e($navHref) ?>"<?= $navKey === $activeNav ? ' class="active"' : '' ?>><?= e($navLabel) ?></a>
<?php endforeach; ?>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>
