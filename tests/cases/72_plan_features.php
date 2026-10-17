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
        'feature_sms'             => 'diamond',  // unknown tier — ignored
    ]);
    check(!in_array('feature_maps', $cut['silver']['features'], true), 'Maps still on Silver after moving it to Gold');
    check(in_array('feature_maps', $cut['gold']['features'], true), 'Maps missing from Gold');
    check(in_array('feature_maps', $cut['platinum']['features'], true), 'Maps on Gold but missing from Platinum (the tier above)');
    foreach (['free', 'silver', 'gold', 'platinum'] as $t) {
        check(in_array('feature_postcode_lookup', $cut[$t]['features'], true), "Postcode lookup on Bronze but missing from $t");
        check(!in_array('feature_accounts', $cut[$t]['features'], true), "Accounts in no plan but still on $t");
        check(!in_array('feature_nonsense', $cut[$t]['features'], true), "an unknown flag landed on $t");
    }
    check(in_array('feature_sms', $cut['silver']['features'], true), 'an unknown tier moved Texts off Silver');
    check_contains('Postcode lookup', $cut['free']['description'], 'Bronze description');
    check_contains('Everything in Silver, plus Maps', $cut['gold']['description'], 'Gold description');
    check_not_contains('Accounts', $cut['gold']['description'], 'Gold description (Accounts is in no plan)');
});

test('Platinum: above Gold, can hold Platinum-only extras', function () {
    $std = require APP_ROOT . '/_partials/billing_plans.php';
    check(isset($std['platinum']), 'Platinum tier missing');
    check((int) $std['platinum']['tier'] > (int) $std['gold']['tier'], 'Platinum is not above Gold');
    foreach ($std['gold']['features'] as $f) {
        check(in_array($f, $std['platinum']['features'], true), "Platinum is missing Gold's $f");
    }
    $cut = billing_apply_feature_layout($std, ['feature_accounts' => 'platinum']);
    check(in_array('feature_accounts', $cut['platinum']['features'], true), 'Accounts missing from Platinum');
    foreach (['free', 'silver', 'gold'] as $t) {
        check(!in_array('feature_accounts', $cut[$t]['features'], true), "Platinum-only Accounts still on $t");
    }
    check_contains('Everything in Gold, plus Accounts', $cut['platinum']['description'], 'Platinum description');
    check_same('the Silver, Gold, Platinum and Factory plans', billing_feature_plans_phrase('feature_sms'), 'texts upgrade wording');
});

test('Factory: the top tier, everything in Platinum, and it switches the factory side ON (never off)', function () {
    $std = require APP_ROOT . '/_partials/billing_plans.php';
    check(isset($std['factory']), 'Factory tier missing');
    check((int) $std['factory']['tier'] > (int) $std['platinum']['tier'], 'Factory is not above Platinum');
    foreach ($std['platinum']['features'] as $f) {
        check(in_array($f, $std['factory']['features'], true), "Factory is missing Platinum's $f");
    }
    check(!empty($std['factory']['grants_factory']), 'Factory does not grant the factory side');

    // Bravo (102) is not a factory. A Factory comp switches its factory side on…
    db()->exec('UPDATE clients SET is_factory = 0 WHERE id = 102');
    db()->prepare("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, notes) VALUES (102, 'factory', 'comp', 'test')")->execute();
    try {
        billing_sync_feature_flags_force(102);
        check_same(1, (int) t_val('SELECT is_factory FROM clients WHERE id = 102'), 'factory side after the Factory comp');
        // …and taking the comp away does NOT switch it off (a working floor must not vanish).
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102 AND plan_code = 'factory'");
        billing_sync_feature_flags_force(102);
        check_same(1, (int) t_val('SELECT is_factory FROM clients WHERE id = 102'), 'factory side after leaving the Factory plan');
    } finally {
        db()->exec("DELETE FROM client_plan_overrides WHERE client_id = 102 AND plan_code = 'factory'");
        db()->exec('UPDATE clients SET is_factory = 0 WHERE id = 102');
    }
});

test('Listed features: every one is on a tier, and the board saves where they go (describes only)', function () {
    $cat = billing_catalogue();
    check(count($cat) >= 20, 'expected the full feature list, got ' . count($cat));
    check_same('free', $cat['instaprice']['code'] ?? null, 'InstaPrice is a Bronze feature as standard');
    check_same('factory', $cat['factory_pipeline']['code'] ?? null, 'Factory Pipeline is a Factory feature as standard');
    $bronze = array_column(billing_plan_listed('free'), 'label');
    check(in_array('InstaPrice', $bronze, true) && !in_array('Factory Pipeline', $bronze, true), 'Bronze list: ' . implode(', ', $bronze));

    $super = as_user('super');
    check_contains('data-cat="instaprice"', $super->get('/master-admin/plan-features.php')->body, 'the board shows the feature boxes');
    $r = t_submit($super, '/master-admin/plan-features.php', ['action' => 'save',
        'layout'    => json_encode([]),
        'catalogue' => json_encode(['calendar' => 'silver'])]);
    check($r->status < 400, 'saving the board failed (HTTP ' . $r->status . ')');
    check_same('silver', billing_catalogue()['calendar']['code'], 'Calendar moved to Silver');
    check(in_array('Calendar', array_column(billing_plan_listed('silver', true), 'label'), true), 'Silver now adds Calendar');
    check_contains('What this plan adds', (new YbHttp())->get('/welcome.php')->body, 'welcome page shows the lists');

    t_submit($super, '/master-admin/plan-features.php', ['action' => 'reset']);
    check_same('free', billing_catalogue()['calendar']['code'], 'Calendar back on Bronze after Reset');
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
    billing_plans(true);   // the save happened over HTTP — re-read the layout here
    check(in_array('Postcode lookup', array_column(billing_plan_listed('free'), 'label'), true), 'Bronze card lists Postcode lookup once moved there');

    t_submit($super, '/master-admin/plan-features.php', ['action' => 'reset']);
    check_same([], billing_feature_layout(), 'layout after Reset');
    check_same(0, $flag(102, 'feature_postcode_lookup'), 'Bravo postcode lookup after Reset');
    check_same(0, $flag(101, 'feature_accounts'), 'Alpha Accounts after Reset');
});

test('the board order is saved, and the plan lists follow it', function () {
    $super = as_user('super');
    $r = t_submit($super, '/master-admin/plan-features.php', ['action' => 'save',
        'layout'    => json_encode([]),
        'catalogue' => json_encode([]),
        'order'     => json_encode(['c:calendar', 'c:instaprice'])]);
    check($r->status < 400, 'saving the order failed (HTTP ' . $r->status . ')');
    $keys = array_keys(billing_catalogue());
    check_same(['calendar', 'instaprice'], array_slice($keys, 0, 2), 'the first two listed features follow the saved order');
    t_submit($super, '/master-admin/plan-features.php', ['action' => 'reset']);
    check_same([], billing_feature_order(), 'order cleared by Reset');
});

test('a plan card lists EVERY box the board puts on that tier — switches and listed — under one heading', function () {
    billing_plans(true);   // earlier tests changed the layout over HTTP
    $silver = array_column(billing_plan_listed('silver', true), 'label');
    check(in_array('Maps', $silver, true), 'Silver card lists Maps (an original switch): ' . implode(', ', $silver));
    check(in_array('Sign on your device', $silver, true), 'Silver card lists Sign on your device (listed)');
    check(!in_array('Accounts', $silver, true), 'Accounts is Gold, not Silver');
    check_same('Everything in Silver, plus:', billing_plan_heading('gold'), 'Gold heading');
    check_contains('Everything in Bronze, plus:', (new YbHttp())->get('/welcome.php')->body, 'welcome page Silver card heading');
});
