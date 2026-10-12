<?php
declare(strict_types=1);

/**
 * OFFLINE RESENDS. A tablet with no signal queues each change with a one-off
 * client_ref and sends it when the signal returns. If the reply is lost it sends
 * again — possibly after signing in again (a new session). The server must do
 * each thing ONCE (offline_refs table, PR #1104): one blind, one quote, one email.
 */

require_once APP_ROOT . '/_partials/offline_refs.php';

$ref = static fn (string $tag) => 'T' . $tag . '-' . bin2hex(random_bytes(6));
$roller = static fn () => price_map_to_tenant(price_scenarios()['Roller, side winder, 1200 × 1600'], 101);

test('a blind sent twice — even after signing in again — is added once', function () use ($ref, $roller) {
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'RESEND-ITEM');
    $r = $ref('item');
    $post = ['quote_id' => $qid, 'client_ref' => $r, 'room_name' => 'Hall'] + $roller();
    t_submit($a, '/quote-builder/add_item.php', $post);
    t_submit($a, '/quote-builder/add_item.php', $post);                 // same session
    check_same(1, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qid]), 'blinds after two sends in one session');
    $b = as_user('alpha.admin');                                       // signed in again: a fresh session
    $resp = t_submit($b, '/quote-builder/add_item.php', $post);
    check(!$resp->phpErrors, 'PHP problems on resend');
    check_same(1, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qid]), 'blinds after a resend from a new session');
});

test('a multi-blind fascia (several lines from one send) resent adds no extra lines', function () use ($ref, $roller) {
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'RESEND-FASCIA');
    $q = price_map_to_tenant(price_scenarios()['Roller, 3 blinds on one Senses fascia'], 101);
    $post = ['quote_id' => $qid, 'client_ref' => $ref('mf'), 'room_name' => 'Lounge'] + $q;
    t_submit($a, '/quote-builder/add_item.php', $post);
    $n = (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qid]);
    check($n >= 2, "the fascia group saved $n line(s), expected one per blind");
    t_submit(as_user('alpha.admin'), '/quote-builder/add_item.php', $post);
    check_same($n, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qid]), 'lines after the fascia group was resent');
});

test('a new quote sent twice (fresh sign-in between) is created once and the resend lands on it', function () use ($ref) {
    $r = $ref('quote');
    $fields = ['customer_id' => fx('customer_alpha'), 'customer_reference' => 'RESEND-QUOTE', 'end_customer_name' => 'Mrs Test Customer', 'client_ref' => $r];
    $first = t_submit(as_user('alpha.admin'), '/quote-builder/new.php', $fields);
    check($first->status === 302 && preg_match('/id=(\d+)/', (string) $first->location, $m1), 'the first send did not create a quote');
    $again = t_submit(as_user('alpha.admin'), '/quote-builder/new.php', $fields);
    check(!$again->phpErrors, 'PHP problems on resend');
    check($again->status === 302 && preg_match('/id=(\d+)/', (string) $again->location, $m2), 'the resend did not go to a quote');
    check_same($m1[1], $m2[1], 'the resend went to a different quote');
    check_same(1, (int) t_val("SELECT COUNT(*) FROM quotes WHERE client_id = 101 AND customer_reference = 'RESEND-QUOTE'"), 'quotes created');
});

test('an emailed quote sent twice (fresh sign-in between) is emailed once', function () use ($ref, $roller) {
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'RESEND-EMAIL');
    flow_add_line($a, $qid, 101, price_scenarios()['Roller, side winder, 1200 × 1600']);
    $count = static fn () => (int) t_val("SELECT COUNT(*) FROM messages WHERE quote_id = ? AND kind = 'quote'", [$qid]);
    $post = ['id' => $qid, 'to' => 'alpha.customer@example.test', 'client_ref' => $ref('email')];
    $r1 = t_submit($a, '/pdf-generator/email_pdf.php', $post);
    check(!$r1->phpErrors, "PHP problems sending:\n    " . implode("\n    ", $r1->phpErrors));
    check_same(1, $count(), 'emails logged after the first send (mail itself is dropped in tests)');
    t_submit(as_user('alpha.admin'), '/pdf-generator/email_pdf.php', $post);
    check_same(1, $count(), 'emails logged after the resend');
    check_same('sent', (string) t_val('SELECT status FROM quotes WHERE id = ?', [$qid]), 'quote status after emailing');
});

test('the same one-off reference from another company is a different job', function () use ($ref, $roller) {
    $r = $ref('shared');
    $a = as_user('alpha.admin');
    $qa = flow_new_quote($a, fx('customer_alpha'), 'REF-A');
    t_submit($a, '/quote-builder/add_item.php', ['quote_id' => $qa, 'client_ref' => $r] + $roller());
    $b = as_user('bravo.admin');
    $qb = flow_new_quote($b, (int) t_val('SELECT id FROM customers WHERE client_id = 102 LIMIT 1'), 'REF-B');
    t_submit($b, '/quote-builder/add_item.php', ['quote_id' => $qb, 'client_ref' => $r]
        + price_map_to_tenant(price_scenarios()['Roller, side winder, 1200 × 1600'], 102));
    check_same(1, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qa]), 'Alpha\'s blinds');
    check_same(1, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qb]), 'Bravo\'s blind (same reference, other company)');
});

test('a send that failed can be retried with the same reference (the claim is given back)', function () use ($ref) {
    // email_pdf.php releases its claim when the mail fails, so the tablet's retry goes.
    $r = $ref('fail');
    check(oref_claim(db(), 101, 'email', $r, 1), 'first claim');
    check(!oref_claim(db(), 101, 'email', $r, 1), 'a second claim must be refused');
    oref_release(db(), 101, 'email', $r);
    check(oref_claim(db(), 101, 'email', $r, 1), 'after a failed send is released, the retry must be allowed');
    check(oref_claim(db(), 102, 'email', $r, 1), 'another company\'s identical reference is independent');
});
