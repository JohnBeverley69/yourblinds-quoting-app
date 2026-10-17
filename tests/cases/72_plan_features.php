<?php
declare(strict_types=1);

/**
 * PLAN FEATURES (John, 2026-10-17). Master Admin → Plan features: drag each
 * extra onto the lowest tier that should have it. Higher tiers include it;
 * Bronze is everyone's; "Not in a plan" leaves it to the hand switch. Saving
 * re-syncs every company's feature flags straight away.
 */

require_once APP_ROOT . '/_partials/billing_helpers.php';

$flag = static fn (int $client, string $col): int
    => (int) t_val("SELECT COALESCE($col, 0) FROM client_settings WHERE client_id = ?", [$client]);

test('Plan features: super-admin only', function () {
    check_page_ok(as_user('super')->get('/master-admin/plan-features.php'), 'Plan features as the super-admin');
    check_refused(as_user('alpha.admin')->get('/master-admin/plan-features.php'), 'Plan features as a company admin');
    check_refused(t_submit(as_user('alpha.admin'), '/master-admin/plan-features.php',
        ['action' => 'save', 'layout' => json_encode(['feature_accounts' => 'free'])]), 'a company admin saving tiers');
    check_same([], billing_feature_layout(), 'saved layout after a refused save');
});

test('a layout re-cuts the ladder: up the tiers, Bronze for all, or no plan', function () {
    $std  = require APP_ROOT . '/_partials/billing_plans.php';
    $cut  = billing_apply_feature_layout($std, [
        'feature_maps'            => 'gold',
        'feature_postcode_lookup' => 'free',
        'feature_accounts'        => '',
        'feature_nonsense'        => 'silver',   // unknown flag — ignored
        'feature_sms'             => 'platinum', // unknown tier — ignored
    ]);
    check(!in_array('feature_maps', $cut['silver']['features'], true), 'Maps still on Silver after moving it to Gold');
    check(in_array('feature_maps', $cut['gold']['features'], true), 'Maps missing from Gold');
    foreach (['free', 'silver', 'gold'] as $t) {
        check(in_array('feature_postcode_lookup', $cut[$t]['features'], true), "Postcode lookup on Bronze but missing from $t");
        check(!in_array('feature_accounts', $cut[$t]['features'], true), "Accounts in no plan but still on $t");
        check(!in_array('feature_nonsense', $cut[$t]['features'], true), "an unknown flag landed on $t");
    }
    check(in_array('feature_sms', $cut['silver']['features'], true), 'an unknown tier moved Texts off Silver');
    check_contains('Postcode lookup', $cut['free']['description'], 'Bronze description');
    check_contains('Everything in Silver, plus Maps', $cut['gold']['description'], 'Gold description');
    check_not_contains('Accounts', $cut['gold']['description'], 'Gold description (Accounts is in no plan)');
});

test('saving switches companies on and off; reset puts it back', function () use ($flag) {
    $super = as_user('super');
    // Bravo has no plan; Alpha is on Silver by comp (65_text_bundles), or give it one.
    if (!billing_client_has_comp(101, 'silver')) {
        db()->prepare("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, notes) VALUES (101, 'silver', 'comp', 'test')")->execute();
    }
    billing_sync_feature_flags_force(101);
    billing_sync_feature_flags_force(102);
    check_same(0, $flag(102, 'feature_postcode_lookup'), 'Bravo (Bronze) postcode lookup before');
    check_same(0, $flag(101, 'feature_accounts'), 'Alpha (Silver) Accounts before');

    $r = t_submit($super, '/master-admin/plan-features.php', ['action' => 'save', 'layout' => json_encode([
        'feature_postcode_lookup' => 'free',
        'feature_accounts'        => 'silver',
        'feature_maps'            => 'silver',
        'feature_sms'             => 'silver',
        'feature_online_booking'  => 'silver',
    ])]);
    check($r->status < 400, 'saving the tiers failed (HTTP ' . $r->status . ')');
    check_same('free', billing_feature_layout()['feature_postcode_lookup'] ?? null, 'saved placement of Postcode lookup');
    check_same(1, $flag(102, 'feature_postcode_lookup'), 'Bravo (Bronze) postcode lookup after moving it to Bronze');
    check_same(1, $flag(101, 'feature_accounts'), 'Alpha (Silver) Accounts after moving it to Silver');
    check_same(0, $flag(102, 'feature_accounts'), 'Bravo (Bronze) got Accounts');
    check_contains('Also includes Postcode lookup', (new YbHttp())->get('/welcome.php')->body, 'welcome page Bronze card');

    t_submit($super, '/master-admin/plan-features.php', ['action' => 'reset']);
    check_same([], billing_feature_layout(), 'layout after Reset');
    check_same(0, $flag(102, 'feature_postcode_lookup'), 'Bravo postcode lookup after Reset');
    check_same(0, $flag(101, 'feature_accounts'), 'Alpha Accounts after Reset');
});
