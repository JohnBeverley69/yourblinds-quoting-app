<?php
declare(strict_types=1);

/**
 * Who may see a Help topic or guide — shared by help/index.php (the list) and
 * help/guide.php (one guide), so the two can never disagree.
 *
 *   all      everyone signed in
 *   admin    a company admin (or the super-admin)
 *   super    the super-admin only
 *   factory  the FACTORY side: someone on a factory account with the Factory
 *            role — the main factory and white-label factories alike — plus
 *            the super-admin. Never an ordinary shop (John, 2026-10-13: factory
 *            guides only on factory-enabled accounts). Same test as the
 *            sidebar's Factory side (_partials/sidebar.php $canSeeFactory).
 *   console  the main factory's Factory Console only (its trade accounts,
 *            invoices, QuickBooks, Orders/Remakes/Calendar screens) — screens
 *            a white-label factory doesn't have — plus the super-admin.
 */

if (!function_exists('help_can_see')) {
    function help_can_see(string $aud): bool
    {
        $user    = function_exists('current_user') ? (current_user() ?? []) : [];
        $isSuper = function_exists('is_super_admin') && is_super_admin();
        if ($isSuper) return true;
        switch ($aud) {
            case 'super':
                return false;
            case 'admin':
                return ($user['role'] ?? '') === 'admin'
                    || (function_exists('current_user_has_role') && current_user_has_role('admin'));
            case 'factory':
                return function_exists('is_factory_client') && is_factory_client((int) ($user['client_id'] ?? 0))
                    && function_exists('current_user_has_role') && current_user_has_role('factory');
            case 'console':
                return function_exists('factory_console_user') && factory_console_user();
            default:
                return true;   // 'all'
        }
    }
}
