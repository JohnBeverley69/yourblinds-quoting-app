<?php
declare(strict_types=1);

/**
 * ONLINE BOOKING (John, 2026-10-16). Customers book a measure visit on the
 * public page /book/?c=<link name>. Silver/Gold only. Request mode holds the
 * slot until the office confirms; the public form never changes an existing
 * customer's record; spam limits; one company can't touch another's bookings.
 * Postcode distances are never looked up in a test run (no network), so the
 * radius check reports "unchecked" and lets the booking through as a request.
 */

require_once APP_ROOT . '/_partials/online_booking.php';

$alpha = 101;
$bravo = 102;

// Alpha on Silver with time slots on (Morning 1 a day, Afternoon 4), every day
// bookable from tomorrow; Bravo on no plan. Set before any slot code runs —
// the slot settings are cached for the rest of this process.
if (!t_val("SELECT 1 FROM client_plan_overrides WHERE client_id = ? AND plan_code = 'silver' AND active = 1", [$alpha])) {
    db()->prepare("INSERT INTO client_plan_overrides (client_id, plan_code, override_type, notes) VALUES (?, 'silver', 'comp', 'test')")->execute([$alpha]);
}
db()->prepare('UPDATE client_settings SET feature_online_booking = 1, feature_ampm_slots = 1, ampm_windows_json = ? WHERE client_id = ?')
    ->execute([json_encode([
        ['k' => 'am', 'label' => 'Morning',   'start' => '09:00:00', 'end' => '13:00:00', 'cap' => 1, 'off' => false],
        ['k' => 'pm', 'label' => 'Afternoon', 'start' => '13:00:00', 'end' => '17:00:00', 'cap' => 4, 'off' => false],
    ]), $alpha]);
db()->prepare('UPDATE client_settings SET feature_online_booking = 0 WHERE client_id = ?')->execute([$bravo]);

$tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$dayAfter = (new DateTimeImmutable('tomorrow'))->modify('+1 day')->format('Y-m-d');
$person = static function (array $over = []) use ($tomorrow): array {
    return $over + [
        'name' => 'Sam Online', 'email' => 'sam.online@example.test', 'phone' => '07700 900456',
        'address1' => '5 New Street', 'address2' => '', 'town' => 'Hull', 'postcode' => 'HU17 9AB',
        'notes' => 'Two rollers for the kitchen', 'date' => $tomorrow, 'slot' => 'pm',
    ];
};
$settingsForm = static function (array $over = []): array {
    return $over + [
        '_action' => 'save', 'enabled' => '1', 'slug' => 'alpha-blinds', 'mode' => 'request',
        'min_notice_days' => '1', 'max_days_ahead' => '14', 'weekdays' => ['1', '2', '3', '4', '5', '6', '7'],
        'closed_dates' => '', 'base_postcode' => '', 'radius_miles' => '', 'districts' => 'HU, YO25',
        'intro_text' => '', 'notify_email' => '',
    ];
};

test('postcode lists, closed days and link names read the way Settings explains them', function () {
    $list = ob_parse_districts('hu, YO25 , dn14 6', $bad);
    check_same([], $bad, 'unreadable postcodes');
    check(ob_district_match('HU179AB', $list), 'HU should cover HU17 9AB');
    check(ob_district_match('YO251AA', $list), 'YO25 should cover YO25 1AA');
    check(!ob_district_match('YO421AA', $list), 'YO25 must not cover YO42');
    check(ob_district_match('DN146XY', $list), 'DN14 6 should cover DN14 6XY');
    check(!ob_district_match('DN145XY', $list), 'DN14 6 must not cover DN14 5XY');
    check(!ob_district_match('H11AA', ['HU']) , 'HU must not cover an H postcode');
    ob_parse_districts('HU, nonsense!', $bad);
    check_same(['NONSENSE!'], $bad, 'a bad entry is reported');

    $closed = ob_parse_closed("24/12/2026 - 2/1/2027\n2026-08-31", $bad);
    check_same([], $bad, 'unreadable closed days');
    check_same(11, count($closed), 'closed days (10 in the range + 1)');
    check(isset($closed['2027-01-01'], $closed['2026-08-31']), 'range and single day both closed');
    ob_parse_closed('31/02/2026', $bad);
    check_same(['31/02/2026'], $bad, 'an impossible date is reported, not rolled over');

    check(ob_slug_valid('alpha-blinds') && !ob_slug_valid('ab') && !ob_slug_valid('alpha--blinds') && !ob_slug_valid('-alpha'), 'link name rules');
    check_same('beverley-blinds-shutters', ob_slugify('Beverley Blinds & Shutters Ltd'), 'suggested link name');
});

test('Bronze can\'t switch it on; the settings tab offers the plans instead', function () use ($bravo, $settingsForm) {
    check(!ob_feature_enabled($bravo), 'Bravo has online booking without a plan');
    $b = as_user('bravo.admin');
    $page = $b->get('/admin/settings.php');
    check_page_ok($page, 'Settings for Bravo');
    check_contains('included in the <strong>Silver</strong>', $page->body, 'the upsell');
    t_submit($b, '/admin/online_booking_save.php', $settingsForm(['slug' => 'bravo-blinds']));
    check_same(null, t_row('SELECT * FROM online_booking_settings WHERE client_id = ?', [$bravo]), 'Bravo\'s settings row');
    check_same(404, (new YbHttp())->get('/book/?c=bravo-blinds')->status, 'Bravo\'s booking page');
});

test('Silver: the admin saves the settings and the link opens', function () use ($alpha, $settingsForm) {
    $a = as_user('alpha.admin');
    $r = t_submit($a, '/admin/online_booking_save.php', $settingsForm(['closed_dates' => 'not a date']));
    check_same(null, t_row('SELECT * FROM online_booking_settings WHERE client_id = ?', [$alpha]), 'saved despite a bad closed day');
    t_submit($a, '/admin/online_booking_save.php', $settingsForm());
    $row = t_row('SELECT * FROM online_booking_settings WHERE client_id = ?', [$alpha]);
    check($row !== null && (int) $row['enabled'] === 1 && $row['slug'] === 'alpha-blinds', 'settings not saved');
    check_same('HU, YO25', (string) $row['districts'], 'postcode list as saved');
    check_same('1,2,3,4,5,6,7', (string) $row['weekdays'], 'days');

    $page = $a->get('/admin/settings.php');
    check_page_ok($page, 'Settings for Alpha');
    check_contains('/book/?c=alpha-blinds', $page->body, 'the link on the Settings tab');
    check_contains('<svg', $page->body, 'the QR code');
    check_page_ok((new YbHttp())->get('/book/?c=alpha-blinds'), 'the public page');
    check_same(404, (new YbHttp())->get('/book/?c=no-such-company')->status, 'an unknown link name');
    check_refused(as_user('alpha.sales')->get('/admin/online_booking_save.php?qr=1'), 'QR download for a salesperson');
});

test('a customer outside the area is turned away with the phone number', function () {
    $c = new YbHttp();
    $p = $c->get('/book/?c=alpha-blinds');
    $r = $c->post('/book/?c=alpha-blinds', ['_csrf' => YbHttp::csrf($p->body), '_action' => 'postcode', 'postcode' => 'LS1 1AA']);
    check_page_ok($r, 'outside-area page');
    check_contains('can\'t book LS1 1AA online', $r->body, 'the outside-area message');
});

test('the public journey: postcode → time → book — held as a request, no text, office told', function () use ($alpha, $tomorrow) {
    $before = (int) t_val('SELECT COUNT(*) FROM customers WHERE client_id = ?', [$alpha]);
    $c = new YbHttp();
    $p = $c->get('/book/?c=alpha-blinds');
    $r = $c->post('/book/?c=alpha-blinds', ['_csrf' => YbHttp::csrf($p->body), '_action' => 'postcode', 'postcode' => 'hu17 9ab']);
    check_same(302, $r->status, 'postcode step redirect');
    $t = $c->get('/book/?c=alpha-blinds&step=time');
    check_page_ok($t, 'time step');
    check_contains('value="' . $tomorrow . '|am"', $t->body, 'tomorrow morning offered');
    check_not_contains('value="' . (new DateTimeImmutable('today'))->format('Y-m-d') . '|', $t->body, 'today offered with next-day notice');

    $fields = ['_csrf' => YbHttp::csrf($t->body), '_action' => 'book', 'pick' => $tomorrow . '|am',
        'name' => 'Pat Public', 'email' => 'pat.public@example.test', 'phone' => '07700 900111',
        'address1' => '9 Bay Road', 'address2' => '', 'town' => 'Beverley', 'notes' => 'Shutters', 'website' => ''];
    $fast = $c->post('/book/?c=alpha-blinds&step=time', $fields);
    check_contains('something went wrong', $fast->body, 'a form sent back instantly (a bot) is refused');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM appointments WHERE client_id = ? AND title = 'Pat Public'", [$alpha]), 'booked by the bot');

    $t = $c->get('/book/?c=alpha-blinds&step=time');
    sleep(5);
    $fields['_csrf'] = YbHttp::csrf($t->body);
    $trap = $c->post('/book/?c=alpha-blinds&step=time', ['website' => 'http://spam.example'] + $fields);
    check_contains('something went wrong', $trap->body, 'the hidden trap field filled in');
    sleep(5);   // the refused form re-shows and restarts the 4-second clock

    $done = $c->post('/book/?c=alpha-blinds&step=time', $fields);
    check_same(302, $done->status, 'booking redirect');
    $thanks = $c->get('/book/?c=alpha-blinds&step=done');
    check_contains('got your request', $thanks->body, 'the request message');

    $a = t_row("SELECT * FROM appointments WHERE client_id = ? AND title = 'Pat Public'", [$alpha]);
    check($a !== null, 'no appointment');
    check_same(1, (int) $a['online_request'], 'waiting for the office');
    check_same('booked', (string) $a['status'], 'status (holds the slot)');
    check_same('am', (string) $a['slot_window'], 'slot');
    check_same(fx('user_alpha_sales'), (int) $a['client_user_id'], 'given to Alpha\'s salesperson automatically');
    check_same('HU17 9AB', (string) $a['installation_postcode'], 'postcode from the session, tidied');
    check_contains('Shutters', (string) $a['notes'], 'the customer\'s message');
    check_same($before + 1, (int) t_val('SELECT COUNT(*) FROM customers WHERE client_id = ?', [$alpha]), 'new customer made');
    check_same('requested', (string) t_val('SELECT status FROM online_bookings WHERE appointment_id = ?', [(int) $a['id']]), 'log row');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM messages WHERE appointment_id = ? AND kind = 'booking_confirm'", [(int) $a['id']]), 'booking text sent while waiting');
    check_same(1, (int) t_val("SELECT COUNT(*) FROM messages WHERE client_id = ? AND channel = 'email' AND kind = 'online_booking' AND to_addr = 'pat.public@example.test'", [$alpha]), 'the customer\'s "request received" email');

    // Morning holds 1 a day — it's gone now.
    $c2 = new YbHttp();
    $p2 = $c2->get('/book/?c=alpha-blinds');
    $c2->post('/book/?c=alpha-blinds', ['_csrf' => YbHttp::csrf($p2->body), '_action' => 'postcode', 'postcode' => 'YO25 1AA']);
    $t2 = $c2->get('/book/?c=alpha-blinds&step=time');
    check_not_contains('value="' . $tomorrow . '|am"', $t2->body, 'the full morning still offered');
    check_contains('value="' . $tomorrow . '|pm"', $t2->body, 'the afternoon');
});

test('the last place can\'t be taken twice; a repeat customer\'s record is never changed', function () use ($alpha, $person, $dayAfter) {
    $first = ob_create_booking($alpha, $person(['date' => $dayAfter, 'slot' => 'am']), ['ip' => '10.0.0.1']);
    check($first['ok'], 'first booking: ' . ($first['error'] ?? ''));
    $second = ob_create_booking($alpha, $person(['date' => $dayAfter, 'slot' => 'am', 'email' => 'other@example.test']), ['ip' => '10.0.0.2']);
    check(!$second['ok'] && !empty($second['taken']), 'the second customer got the full morning');

    $cust = fx('customer_alpha');
    $was = t_row('SELECT name, email, address1, postcode FROM customers WHERE id = ?', [$cust]);
    $r = ob_create_booking($alpha, $person(['email' => 'ALPHA.customer@example.test', 'name' => 'Jo Customer', 'address1' => '1 Other Way']), ['ip' => '10.0.0.3']);
    check($r['ok'], 'repeat customer booking: ' . ($r['error'] ?? ''));
    check_same($cust, (int) t_val('SELECT customer_id FROM appointments WHERE id = ?', [$r['appointment_id']]), 'same email and surname: booked against the existing customer');
    check_same($was, t_row('SELECT name, email, address1, postcode FROM customers WHERE id = ?', [$cust]), 'the existing record');
    check_contains('Jo Customer', (string) t_val('SELECT notes FROM appointments WHERE id = ?', [$r['appointment_id']]), 'what they typed, on the appointment');
    check_same('1 Other Way', (string) t_val('SELECT installation_address1 FROM appointments WHERE id = ?', [$r['appointment_id']]), 'the visit address');

    // Same email, different surname (John's ABC test, 2026-10-16): a new customer, flagged.
    $before = (int) t_val('SELECT COUNT(*) FROM customers WHERE client_id = ?', [$alpha]);
    $r = ob_create_booking($alpha, $person(['email' => 'alpha.customer@example.test', 'name' => 'Stan Rogers', 'date' => $dayAfter]), ['ip' => '10.0.0.4']);
    check($r['ok'], 'different-surname booking: ' . ($r['error'] ?? ''));
    $newCust = (int) t_val('SELECT customer_id FROM appointments WHERE id = ?', [$r['appointment_id']]);
    check($newCust !== $cust, 'Stan Rogers was booked as Mrs Alpha Customer');
    check_same($before + 1, (int) t_val('SELECT COUNT(*) FROM customers WHERE client_id = ?', [$alpha]), 'a new customer made');
    check_same('Stan Rogers', (string) t_val('SELECT name FROM customers WHERE id = ?', [$newCust]), 'the new customer\'s name');
    check_contains('possible duplicate', (string) t_val('SELECT notes FROM appointments WHERE id = ?', [$r['appointment_id']]), 'the note to the office');
    check_same($was, t_row('SELECT name, email, address1, postcode FROM customers WHERE id = ?', [$cust]), 'the existing record, untouched');

    check(ob_same_surname('Mrs J Smith', 'John Smith') && ob_same_surname('ANNE O\'NEILL', 'Pat O\'Neill'), 'surnames that match');
    check(!ob_same_surname('Jones Beverley', 'Stan Rogers') && !ob_same_surname('', 'Smith'), 'surnames that don\'t');
});

test('spam limits: 3 an hour from one visitor', function () use ($alpha, $person) {
    for ($i = 0; $i < 3; $i++) {
        db()->prepare("INSERT INTO online_bookings (client_id, status, ip_address) VALUES (?, 'requested', '10.9.9.9')")->execute([$alpha]);
    }
    check(ob_rate_limited($alpha, '10.9.9.9'), 'a 4th booking from the same visitor allowed');
    check(!ob_rate_limited($alpha, '10.9.9.8'), 'a different visitor blocked');
});

test('bad details are refused with a plain message', function () use ($alpha, $person) {
    check_contains('email', (string) ob_create_booking($alpha, $person(['email' => 'nope']), ['ip' => '10.0.1.1'])['error'], 'bad email');
    check_contains('phone', (string) ob_create_booking($alpha, $person(['phone' => '123']), ['ip' => '10.0.1.1'])['error'], 'bad phone');
    check_contains('cover', (string) ob_create_booking($alpha, $person(['postcode' => 'LS1 1AA']), ['ip' => '10.0.1.1'])['error'], 'outside the area');
    check(!ob_create_booking($alpha, $person(['slot' => 'w9']), ['ip' => '10.0.1.1'])['ok'], 'a made-up slot');
    check(!ob_create_booking($alpha, $person(['date' => (new DateTimeImmutable('today'))->format('Y-m-d')]), ['ip' => '10.0.1.1'])['ok'], 'today, with next-day notice');
});

test('the office confirms one and declines another; the customer is told; Bravo can\'t touch them', function () use ($alpha) {
    $ids = array_map('intval', array_column(t_rows(
        "SELECT id FROM appointments WHERE client_id = ? AND online_request = 1 AND status = 'booked' ORDER BY id", [$alpha]), 'id'));
    check(count($ids) >= 2, 'need two waiting bookings, have ' . count($ids));
    [$keep, $drop] = $ids;

    $a = as_user('alpha.admin');
    check_contains('waiting for you to confirm', $a->get('/calendar/index.php')->body, 'the calendar notice');
    $view = $a->get('/calendar/view.php?id=' . $keep);
    check_contains('Confirm booking', $view->body, 'the confirm button');

    $b = as_user('bravo.admin');
    t_submit($b, '/calendar/view.php?id=' . $keep, ['_action' => 'online_decision', 'decision' => 'decline']);
    check_same(1, (int) t_val('SELECT online_request FROM appointments WHERE id = ?', [$keep]), 'Bravo decided Alpha\'s booking');

    $f = as_user('alpha.fitter');
    t_submit($f, '/calendar/view.php?id=' . $keep, ['_action' => 'online_decision', 'decision' => 'confirm']);
    check_same(1, (int) t_val('SELECT online_request FROM appointments WHERE id = ?', [$keep]), 'a fitter confirmed it');

    t_submit($a, '/calendar/view.php?id=' . $keep, ['_action' => 'online_decision', 'decision' => 'confirm']);
    check_same(['online_request' => 0, 'status' => 'booked'], t_row('SELECT online_request, status FROM appointments WHERE id = ?', [$keep]), 'confirmed');
    check_same('confirmed', (string) t_val('SELECT status FROM online_bookings WHERE appointment_id = ?', [$keep]), 'log row');

    t_submit($a, '/calendar/view.php?id=' . $drop, ['_action' => 'online_decision', 'decision' => 'decline']);
    check_same(['online_request' => 0, 'status' => 'cancelled'], t_row('SELECT online_request, status FROM appointments WHERE id = ?', [$drop]), 'declined');
    check_same('declined', (string) t_val('SELECT status FROM online_bookings WHERE appointment_id = ?', [$drop]), 'log row');

    t_submit($a, '/calendar/view.php?id=' . $drop, ['_action' => 'online_decision', 'decision' => 'confirm']);
    check_same('cancelled', (string) t_val('SELECT status FROM appointments WHERE id = ?', [$drop]), 'a declined booking brought back');
});

test('instant mode books and confirms in one go', function () use ($alpha, $person) {
    db()->prepare("UPDATE online_booking_settings SET mode = 'instant' WHERE client_id = ?")->execute([$alpha]);
    $r = ob_create_booking($alpha, $person(['email' => 'quick@example.test', 'phone' => '07700 900777']), ['ip' => '10.0.2.1']);
    check($r['ok'] && !$r['requested'], 'instant booking: ' . ($r['error'] ?? ''));
    check_same(0, (int) t_val('SELECT online_request FROM appointments WHERE id = ?', [$r['appointment_id']]), 'waiting flag');

    // A radius the test run can't measure (no network) still books, but as a request for the office to check.
    db()->prepare("UPDATE online_booking_settings SET districts = NULL, base_postcode = 'HU17 0LD', radius_miles = 20 WHERE client_id = ?")->execute([$alpha]);
    $r = ob_create_booking($alpha, $person(['email' => 'far@example.test', 'phone' => '07700 900778', 'postcode' => 'LS1 1AA']), ['ip' => '10.0.2.2']);
    check($r['ok'] && $r['requested'], 'unmeasured distance should arrive as a request');
    check_contains('couldn\'t be checked', (string) t_val('SELECT notes FROM appointments WHERE id = ?', [$r['appointment_id']]), 'the note to the office');
    db()->prepare("UPDATE online_booking_settings SET mode = 'request', districts = 'HU, YO25', base_postcode = NULL, radius_miles = NULL WHERE client_id = ?")->execute([$alpha]);
});

test('address lookup: off without a Postcoder key; each lookup is counted and capped at 6 an hour per visitor', function () use ($alpha) {
    check(!ob_address_lookup_on($alpha), 'lookup on in a test run (there is no Postcoder key)');
    check_same([], ob_lookup_addresses($alpha, 'HU179AB', '10.5.5.5'), 'addresses with no key');
    check_same(0, (int) t_val("SELECT COUNT(*) FROM online_address_lookups WHERE ip_address = '10.5.5.5'"), 'a lookup counted that never happened');

    $calls = 0;
    $fake = static function (string $pc) use (&$calls): array {
        $calls++;
        return [
            ['addressline1' => '1 Fake Street', 'addressline2' => 'Flat 2', 'posttown' => 'Beverley', 'postcode' => 'HU17 9AB'],
            ['addressline1' => '', 'posttown' => 'Beverley'],   // no first line — left out
            ['addressline1' => '3 Fake Street', 'posttown' => 'Beverley'],
        ];
    };
    $list = ob_lookup_addresses($alpha, 'HU179AB', '10.5.5.6', $fake);
    check_same([
        ['line1' => '1 Fake Street', 'line2' => 'Flat 2', 'town' => 'Beverley'],
        ['line1' => '3 Fake Street', 'line2' => '', 'town' => 'Beverley'],
    ], $list, 'addresses offered');
    for ($i = 0; $i < 7; $i++) ob_lookup_addresses($alpha, 'HU179AB', '10.5.5.6', $fake);
    check_same(6, $calls, 'Postcoder calls from one visitor in an hour');
    check_same(6, (int) t_val("SELECT COUNT(*) FROM online_address_lookups WHERE ip_address = '10.5.5.6'"), 'lookups counted');
    check_same(1, count(ob_lookup_addresses($bravo = 102, 'HU179AB', '10.5.5.6', static fn () => [['addressline1' => 'x']])), 'the same visitor on another company\'s page');
});

test('exact times: start times from the hours, one visit per salesperson, gap respected, fittings ignored', function () use ($alpha, $person) {
    $hours = [];
    foreach (range(1, 7) as $d) $hours[(string) $d] = ['from' => '09:00', 'to' => '17:00'];
    db()->prepare("UPDATE online_booking_settings SET booking_style = 'times', visit_minutes = 60, gap_minutes = 30, hours_json = ?,
                   weekdays = '1,2,3,4,5,6,7', mode = 'request', districts = 'HU' WHERE client_id = ?")->execute([json_encode($hours), $alpha]);
    $s = ob_settings($alpha, true);
    check_same(['09:00', '10:30', '12:00', '13:30', '15:00'], ob_day_starts($s, 1), 'start times for 9–5, 1 hour + 30 minutes');
    check_same(1, ob_time_capacity($alpha), 'visits at once (Alpha has one salesperson)');

    $day = (new DateTimeImmutable('tomorrow'))->modify('+4 days')->format('Y-m-d');
    $offered = static function () use ($alpha, $day): array {
        foreach (ob_available_days($alpha) as $d) if ($d['date'] === $day) return array_keys($d['slots']);
        return [];
    };
    check_same(['09:00', '10:30', '12:00', '13:30', '15:00'], $offered(), 'times offered on an empty day');

    $r = ob_create_booking($alpha, $person(['date' => $day, 'slot' => '10:30', 'email' => 'exact@example.test', 'phone' => '07700 900601']), ['ip' => '10.0.3.1']);
    check($r['ok'], 'exact-time booking: ' . ($r['error'] ?? ''));
    check_same(['appointment_time' => '10:30:00', 'duration_minutes' => 60, 'slot_window' => null],
        t_row('SELECT appointment_time, duration_minutes, slot_window FROM appointments WHERE id = ?', [$r['appointment_id']]), 'the visit as booked');
    check_same(['09:00', '12:00', '13:30', '15:00'], $offered(), 'after booking 10:30 (09:00 and 12:00 still clear with the 30-minute gap)');
    $again = ob_create_booking($alpha, $person(['date' => $day, 'slot' => '10:30', 'email' => 'exact2@example.test', 'phone' => '07700 900602']), ['ip' => '10.0.3.2']);
    check(!$again['ok'] && !empty($again['taken']), 'the same time booked twice');
    check(!ob_create_booking($alpha, $person(['date' => $day, 'slot' => '10:15']), ['ip' => '10.0.3.3'])['ok'], 'a time that isn\'t one of the start times');

    // The salesperson's own 1pm visit blocks 12:00 (gap) and 13:30; a fitter's job blocks nothing.
    $add = static function (int $user, string $kind, string $time) use ($alpha, $day): void {
        db()->prepare("INSERT INTO appointments (client_id, client_user_id, title, appointment_date, appointment_time, duration_minutes, status, appt_kind)
                       VALUES (?, ?, 'Office booking', ?, ?, 60, 'booked', ?)")->execute([$alpha, $user, $day, $time, $kind]);
    };
    $add(fx('user_alpha_sales'), 'measure', '13:00:00');
    $add(fx('user_alpha_fitter'), 'fitting', '09:00:00');
    check_same(['09:00', '15:00'], $offered(), 'after the salesperson\'s 1pm visit and a fitter\'s 9am fitting');

    $mail = ob_compose_customer_email('confirmed', ['name' => 'Alpha', 'phone' => '', 'email' => '', 'from_name' => '', 'reply_to' => ''],
        'Sam', $day, ob_appt_time_label($alpha, null, '10:30:00', 60), '', '', true);
    check_contains('10:30am–11:30am', $mail['body'], 'the time in the confirmation');
    check_not_contains('within that time slot', $mail['body'], 'time-slot wording on an exact-time booking');
});

test('exact times: Settings saves them and refuses hours too short for a visit', function () use ($alpha, $settingsForm) {
    $a = as_user('alpha.admin');
    $hours = static function (string $from, string $to): array {
        $f = []; $t = [];
        foreach (range(1, 7) as $d) { $f[$d] = $from; $t[$d] = $to; }
        return ['hours_from' => $f, 'hours_to' => $t];
    };
    t_submit($a, '/admin/online_booking_save.php', $settingsForm(['booking_style' => 'times', 'visit_minutes' => '120', 'gap_minutes' => '15'] + $hours('10:00', '11:00')));
    check_same('60', (string) t_val('SELECT visit_minutes FROM online_booking_settings WHERE client_id = ?', [$alpha]), 'saved with no room for a 2-hour visit');
    t_submit($a, '/admin/online_booking_save.php', $settingsForm(['booking_style' => 'times', 'visit_minutes' => '90', 'gap_minutes' => '15'] + $hours('08:30', '16:30')));
    $row = t_row('SELECT booking_style, visit_minutes, gap_minutes, hours_json FROM online_booking_settings WHERE client_id = ?', [$alpha]);
    check_same(['times', 90, 15], [$row['booking_style'], (int) $row['visit_minutes'], (int) $row['gap_minutes']], 'saved');
    check_same(['from' => '08:30', 'to' => '16:30'], json_decode((string) $row['hours_json'], true)['3'] ?? null, 'Wednesday hours');
    $page = $a->get('/admin/settings.php');
    check_page_ok($page, 'Settings with exact times');
    check_contains('Start times on Monday: 8:30am, 10:15am, 12pm, 1:45pm', $page->body, 'the start-time preview');

    // Time slots switched off: exact times still bookable, slots mode says why it isn't.
    db()->prepare('UPDATE client_settings SET feature_ampm_slots = 0 WHERE client_id = ?')->execute([$alpha]);
    check_page_ok((new YbHttp())->get('/book/?c=alpha-blinds'), 'booking page with exact times and no time slots');
    db()->prepare("UPDATE online_booking_settings SET booking_style = 'slots' WHERE client_id = ?")->execute([$alpha]);
    check_same(404, (new YbHttp())->get('/book/?c=alpha-blinds')->status, 'booking page by time slot with time slots off');
    db()->prepare('UPDATE client_settings SET feature_ampm_slots = 1 WHERE client_id = ?')->execute([$alpha]);
});

/* ---------------- Phase 2 ---------------- */

$d = static fn (int $n): string => (new DateTimeImmutable('tomorrow'))->modify("+$n days")->format('Y-m-d');

test('the name customers see, and the ready-made website button', function () use ($alpha, $settingsForm) {
    $a = as_user('alpha.admin');
    t_submit($a, '/admin/online_booking_save.php', $settingsForm(['display_name' => 'Alpha Home Blinds']));
    check_same('Alpha Home Blinds', (string) t_val('SELECT display_name FROM online_booking_settings WHERE client_id = ?', [$alpha]), 'saved name');
    $pub = (new YbHttp())->get('/book/?c=alpha-blinds');
    check_page_ok($pub, 'booking page');
    check_contains('<h1>Alpha Home Blinds</h1>', $pub->body, 'the name on the booking page');
    $set = $a->get('/admin/settings.php');
    check_contains('A ready-made button for your website', $set->body, 'the website button');
    check_contains('Book a free measure &amp;amp; quote', $set->body, 'the button\'s code to copy');
});

test('change-or-cancel link: move to another time, too late on the day, cancel, at most 3 moves', function () use ($alpha, $person, $d) {
    ob_cache_reset();
    $r = ob_create_booking($alpha, $person(['date' => $d(6), 'slot' => 'pm', 'email' => 'mover@example.test', 'phone' => '07700 900701']), ['ip' => '10.0.4.1']);
    check($r['ok'], 'booking: ' . ($r['error'] ?? ''));
    $token = (string) t_val('SELECT manage_token FROM online_bookings WHERE appointment_id = ?', [$r['appointment_id']]);
    check(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 'no change-or-cancel token');

    $c = new YbHttp();
    $page = $c->get('/book/manage.php?t=' . $token);
    check_page_ok($page, 'change-or-cancel page');
    check_contains('Choose a different time', $page->body, 'the move button');
    check_same(404, $c->get('/book/manage.php?t=' . str_repeat('0', 64))->status, 'a made-up link');
    check_page_ok($c->get('/book/manage.php?t=' . $token . '&move=1'), 'choosing a new time');

    $m = ob_customer_move($token, $d(7), 'am');
    check($m['ok'], 'move: ' . $m['message']);
    $a = t_row('SELECT appointment_date, appointment_time, slot_window, online_request, notes FROM appointments WHERE id = ?', [$r['appointment_id']]);
    check_same([$d(7), '09:00:00', 'am', 1], [$a['appointment_date'], $a['appointment_time'], $a['slot_window'], (int) $a['online_request']], 'the moved visit (waiting to confirm again)');
    check_contains('Moved online by the customer', (string) $a['notes'], 'the note to the office');
    check_same(1, (int) t_val('SELECT reschedules FROM online_bookings WHERE appointment_id = ?', [$r['appointment_id']]), 'moves counted');

    [$ok, $why] = ob_customer_cancel($token, new DateTimeImmutable($d(7) . ' 08:00'));
    check(!$ok && strpos($why, 'too late') !== false, 'cancelled on the day of the visit');
    [$ok] = ob_customer_cancel($token);
    check($ok, 'cancel');
    check_same('cancelled', (string) t_val('SELECT status FROM appointments WHERE id = ?', [$r['appointment_id']]), 'visit status');
    check_same('cancelled', (string) t_val('SELECT status FROM online_bookings WHERE appointment_id = ?', [$r['appointment_id']]), 'log status');
    [$ok] = ob_customer_cancel($token);
    check(!$ok, 'cancelled twice');

    $r2 = ob_create_booking($alpha, $person(['date' => $d(6), 'slot' => 'pm', 'email' => 'mover2@example.test', 'phone' => '07700 900702']), ['ip' => '10.0.4.2']);
    $t2 = (string) t_val('SELECT manage_token FROM online_bookings WHERE appointment_id = ?', [$r2['appointment_id']]);
    db()->prepare('UPDATE online_bookings SET reschedules = 3 WHERE appointment_id = ?')->execute([$r2['appointment_id']]);
    check(!ob_customer_move($t2, $d(8), 'pm')['ok'], 'a fourth move');

    // Cancelling through the page itself.
    $p = $c->get('/book/manage.php?t=' . $t2);
    $c->post('/book/manage.php?t=' . $t2, ['_csrf' => YbHttp::csrf($p->body), '_action' => 'cancel']);
    check_same('cancelled', (string) t_val('SELECT status FROM appointments WHERE id = ?', [$r2['appointment_id']]), 'cancelled on the page');
    check_contains('is <strong>cancelled</strong>', $c->get('/book/manage.php?t=' . $t2)->body, 'the page after cancelling');
});

test('who goes: each salesperson\'s own area, shared out, grouped by area, auto-assign off', function () use ($alpha, $person, $d, $settingsForm) {
    // Distances without the internet: seed the postcode cache (HU17 to YO25 is about 11 miles).
    foreach (['HU179AB' => [53.8400, -0.4300], 'HU170AA' => [53.8450, -0.4400], 'YO251AA' => [54.0050, -0.4400]] as $pc => $ll) {
        db()->prepare('REPLACE INTO postcode_geo (postcode, found, lat, lng) VALUES (?, 1, ?, ?)')->execute([$pc, $ll[0], $ll[1]]);
    }
    db()->prepare("INSERT INTO client_users (client_id, username, full_name, email, password_hash, role, active, home_postcode)
                   VALUES (?, 'alpha.sales2', 'Alpha Sales Two', 'alpha.sales2@example.test', 'x', 'sales', 1, 'YO25 1AA')")->execute([$alpha]);
    $s2 = (int) db()->lastInsertId();
    db()->prepare("INSERT INTO client_user_roles (user_id, role) VALUES (?, 'sales')")->execute([$s2]);
    $s1 = fx('user_alpha_sales');
    ob_cache_reset();
    check_same(2, count(ob_staff($alpha)), 'salespeople taking online bookings');

    $a = as_user('alpha.admin');
    t_submit($a, '/admin/online_booking_save.php', $settingsForm([
        'auto_assign' => '1', 'group_miles' => '0',
        'staff' => [$s1 => ['seen' => '1', 'takes' => '1', 'radius' => '', 'districts' => 'hu'],
                    $s2 => ['seen' => '1', 'takes' => '1', 'radius' => '', 'districts' => 'YO25']],
    ]));
    check_same('YO25', (string) t_val('SELECT districts FROM online_booking_staff WHERE user_id = ?', [$s2]), 'Sales Two postcodes');
    check_page_ok($a->get('/admin/settings.php'), 'Settings with the salespeople table');

    $who = static function (array $r): ?int {
        if (!$r['ok']) return -1;
        $u = t_val('SELECT client_user_id FROM appointments WHERE id = ?', [$r['appointment_id']]);
        return $u === null ? null : (int) $u;
    };
    ob_cache_reset();
    check_same($s1, $who(ob_create_booking($alpha, $person(['date' => $d(8), 'slot' => 'pm', 'email' => 'hu@example.test', 'phone' => '07700 900801']), ['ip' => '10.0.5.1'])), 'an HU customer');
    check_same($s2, $who(ob_create_booking($alpha, $person(['date' => $d(8), 'slot' => 'pm', 'postcode' => 'YO25 1AA', 'email' => 'yo@example.test', 'phone' => '07700 900802']), ['ip' => '10.0.5.2'])), 'a YO25 customer');

    // Nobody's area covers it: anyone (the one with fewer visits that day).
    db()->prepare("UPDATE online_booking_staff SET districts = 'HU1' WHERE user_id = ?")->execute([$s1]);
    ob_cache_reset();
    $r = ob_create_booking($alpha, $person(['date' => $d(9), 'slot' => 'pm', 'email' => 'nobody@example.test', 'phone' => '07700 900803']), ['ip' => '10.0.5.3']);
    check($r['ok'] && in_array($who($r), [$s1, $s2], true), 'a postcode no one covers still gets someone');

    // Group visits by area (5 miles): whoever is already in YO25 that day isn't sent to HU17.
    db()->prepare('UPDATE online_booking_staff SET districts = NULL WHERE client_id = ?')->execute([$alpha]);
    db()->prepare('UPDATE online_booking_settings SET group_miles = 5 WHERE client_id = ?')->execute([$alpha]);
    ob_cache_reset();
    $day = $d(10);
    $first  = $who(ob_create_booking($alpha, $person(['date' => $day, 'slot' => 'pm', 'postcode' => 'YO25 1AA', 'email' => 'g1@example.test', 'phone' => '07700 900811']), ['ip' => '10.0.5.4']));
    $second = $who(ob_create_booking($alpha, $person(['date' => $day, 'slot' => 'pm', 'email' => 'g2@example.test', 'phone' => '07700 900812']), ['ip' => '10.0.5.5']));
    $third  = $who(ob_create_booking($alpha, $person(['date' => $day, 'slot' => 'pm', 'postcode' => 'HU17 0AA', 'email' => 'g3@example.test', 'phone' => '07700 900813']), ['ip' => '10.0.5.6']));
    check($first !== -1 && $second !== -1 && $first !== $second, 'the HU17 visit went to whoever was already in YO25');
    check_same($second, $third, 'a second HU17 visit goes to the person already in HU17');

    // Exact times: two salespeople = two visits at the same time, a third refused.
    db()->prepare("UPDATE online_booking_settings SET booking_style = 'times', group_miles = NULL WHERE client_id = ?")->execute([$alpha]);
    ob_cache_reset();
    $t = ob_day_starts(ob_settings($alpha, true), (int) (new DateTimeImmutable($d(11)))->format('N'))[1];
    $x1 = ob_create_booking($alpha, $person(['date' => $d(11), 'slot' => $t, 'email' => 't1@example.test', 'phone' => '07700 900821']), ['ip' => '10.0.5.7']);
    $x2 = ob_create_booking($alpha, $person(['date' => $d(11), 'slot' => $t, 'email' => 't2@example.test', 'phone' => '07700 900822']), ['ip' => '10.0.5.8']);
    $x3 = ob_create_booking($alpha, $person(['date' => $d(11), 'slot' => $t, 'email' => 't3@example.test', 'phone' => '07700 900823']), ['ip' => '10.0.5.9']);
    check($x1['ok'] && $x2['ok'] && $who($x1) !== $who($x2), 'two salespeople, two visits at the same time');
    check(!$x3['ok'], 'a third visit at the same time with two salespeople');

    // Auto-assign off: unassigned.
    db()->prepare("UPDATE online_booking_settings SET booking_style = 'slots', auto_assign = 0 WHERE client_id = ?")->execute([$alpha]);
    ob_cache_reset();
    check_same(null, $who(ob_create_booking($alpha, $person(['date' => $d(12), 'slot' => 'pm', 'email' => 'u@example.test', 'phone' => '07700 900831']), ['ip' => '10.0.5.10'])), 'auto-assign off');

    db()->prepare('UPDATE online_booking_settings SET auto_assign = 1 WHERE client_id = ?')->execute([$alpha]);
    db()->prepare('UPDATE client_users SET active = 0 WHERE id = ?')->execute([$s2]);
});
