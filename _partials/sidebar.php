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
 *   $dashTag    string defaults to 'Admin Console' or 'Trade Portal'
 *   $activeNav  string one of: calendar, new-quote, quote-history,
 *                      customers, products, users, settings, master-admin.
 *                      Empty = no highlight.
 */

$isAdmin      = $isAdmin      ?? (($user['role'] ?? '') === 'admin');
$isSuperAdmin = $isSuperAdmin ?? (bool) ($user['is_super_admin'] ?? false);
$dashTag      = $dashTag      ?? ($isAdmin ? 'Admin Console'    : 'Trade Portal');
$activeNav    = $activeNav    ?? '';

// Per-user permission flags from client_users. Used to gate menu
// entries for non-admin users — admins always pass via $isAdmin.
$_perms = function_exists('current_user_permissions')
    ? current_user_permissions()
    : ['can_create_quotes' => false, 'can_create_orders' => false,
       'can_view_all_customer_jobs' => false, 'can_view_costs' => false];

$canCreateQuotes  = $isAdmin || $_perms['can_create_quotes'];
$canCreateOrders  = $isAdmin || $_perms['can_create_orders'];
$canSeeAllJobs    = $isAdmin || $_perms['can_view_all_customer_jobs'];
// Quote History is hidden for users with no quote-related permission
// (they have nothing to see). Orders stays VISIBLE to everyone in the
// tenant, including fitters — they need to open the orders they're
// installing to verify details and take balance payments at the door.
// The Orders page row-filters down to "yours" automatically when the
// user lacks view-all, so the menu link isn't lying.
// "Staff" = anyone working the back-office side of jobs (creates
// quotes, processes orders, or has dispatcher visibility). Used to
// gate sidebar entries that aren't part of a pure fitter's daily
// flow.
//
// Fitters reach their orders via the in-context workflow:
//   My Schedule → tap appointment → tap "Open order" → Take payment.
// They don't navigate to a list-view of Customers / Orders / Accounts,
// so those entries are noise on their sidebar. Hiding them simplifies
// the menu to the screens they actually use.
//
// Page-level access on /orders/, /accounts/, /customer-manager/ is
// still permissive (row-filtered to their assignments) so the
// in-context workflow + any direct links keep working.
$isStaff = $canCreateQuotes || $canCreateOrders || $canSeeAllJobs;

$canSeeQuoteHistory = $canCreateQuotes || $canSeeAllJobs;
$canSeeOrders       = $isStaff;
$canSeeCustomers    = $isStaff;
$canSeeAccountsLink = $isStaff;   // separately AND-ed with the feature flag below

// Phase 2 dropped the `quotes` table; Phase 3 brings it back. While it's
// missing, hide the entries that would 500 on click. The check is a single
// metadata lookup — cheap enough to do per page render, and self-heals
// the moment Phase 3 lands the table.
$hasQuotes = $hasQuotes ?? (bool) db()->query("SHOW TABLES LIKE 'quotes'")->fetchColumn();

// Paid Accounts add-on flag (per tenant). Defensive: the column might
// not exist yet on tenants who haven't run migrate_feature_accounts.php —
// treat that as "feature off" so the sidebar entry stays hidden.
$hasAccountsFeature = $hasAccountsFeature ?? (function () use ($user) {
    try {
        $st = db()->prepare(
            'SELECT COALESCE(feature_accounts, 0) FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $st->execute([(int) ($user['client_id'] ?? 0)]);
        return ((int) $st->fetchColumn()) === 1;
    } catch (Throwable $e) {
        return false;
    }
})();

// [href, label, visible]. Order = display order. Calendar is the de-facto
// landing page (login redirects there); a separate "Dashboard" link was
// just a placeholder pointing at /admin/index.php and got removed.
$navLinks = [
    'calendar'      => ['/calendar/index.php',         'Calendar',      true],
    'my-schedule'   => ['/calendar/schedule.php',      'My Schedule',   true],
    'new-quote'     => ['/quote-builder/new.php',      'New Quote',     $hasQuotes && $canCreateQuotes],
    'quote-history' => ['/quote-history/index.php',    'Quote History', $hasQuotes && $canSeeQuoteHistory],
    'orders'        => ['/orders/index.php',           'Orders',        $hasQuotes && $canSeeOrders],
    // Accounts: paid add-on, AND restricted to staff (not pure
    // fitters — fitters take payment from the order page itself,
    // they don't need the listing).
    'accounts'      => ['/accounts/index.php',         'Accounts',      $hasQuotes && $hasAccountsFeature && $canSeeAccountsLink],
    'customers'     => ['/customer-manager/index.php', 'Customers',     $canSeeCustomers],
    'products'      => ['/admin/products/index.php',   'Products',      $isAdmin],
    'users'         => ['/admin/users.php',            'Users',         $isAdmin],
    'settings'      => ['/admin/settings.php',         'Settings',      $isAdmin],
    'billing'       => ['/billing/index.php',          'Billing',       $isAdmin],
    'master-admin'  => ['/master-admin/index.php',     'Master Admin',  $isSuperAdmin],
    'subscriptions' => ['/master-admin/subscriptions.php', 'Subscriptions', $isSuperAdmin],
    'backup'        => ['/master-admin/backup.php',    'Backup',        $isSuperAdmin],
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
                <?php
                    // Show ALL ticked roles, not just the primary. The
                    // "primary" was picked by privilege priority (sales
                    // > fitter, etc.), so a fitter who also had sales
                    // ticked showed up as "sales" — confusing. Joining
                    // the full set is more honest.
                    $roleLabel = !empty($user['roles']) && is_array($user['roles'])
                        ? implode(', ', $user['roles'])
                        : (string) ($user['role'] ?? '');
                ?>
                <?= e($user['company_name']) ?> &middot; <?= e($roleLabel) ?>
            </div>
        </div>
        <nav class="app-sidebar-nav">
<?php foreach ($navLinks as $navKey => [$navHref, $navLabel, $navShow]): ?>
<?php if (!$navShow) continue; ?>
            <a href="<?= e($navHref) ?>"<?= $navKey === $activeNav ? ' class="active"' : '' ?>><?= e($navLabel) ?></a>
<?php endforeach; ?>
        </nav>
        <div class="app-sidebar-foot">
            <a href="/auth/change_password.php">Change password</a>
            &middot;
            <a href="/auth/logout.php">Sign out &rarr;</a>
        </div>
    </aside>
