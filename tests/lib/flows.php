<?php
declare(strict_types=1);

/**
 * Shared steps that several test files need, done through the real screens
 * (HTTP), exactly as a person clicking would.
 */

require_once __DIR__ . '/prices.php';

/** Start a quote for a customer; returns the new quote id. */
function flow_new_quote(YbHttp $h, int $customerId, string $ref = 'Test job'): int
{
    $r = t_submit($h, '/quote-builder/new.php', ['customer_id' => $customerId, 'customer_reference' => $ref,
        'end_customer_name' => 'Mrs Test Customer']);
    if ($r->phpErrors) fail("new quote: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    if ($r->status !== 302 || !preg_match('/edit\.php\?id=(\d+)/', (string) $r->location, $m)) {
        fail('new quote was not created (HTTP ' . $r->status . ' → ' . $r->location . ')');
    }
    return (int) $m[1];
}

/**
 * Add a blind through add_item.php. $q is a line written against the FACTORY's
 * catalogue (as in prices.php); it is mapped to $clientId's copy. Returns the
 * new quote_items row.
 */
function flow_add_line(YbHttp $h, int $quoteId, int $clientId, array $q, string $room = 'Lounge'): array
{
    $q = price_map_to_tenant($q, $clientId);
    $before = (int) t_val('SELECT COALESCE(MAX(id),0) FROM quote_items WHERE quote_id = ?', [$quoteId]);
    $r = t_submit($h, '/quote-builder/add_item.php', ['quote_id' => $quoteId, 'room_name' => $room] + $q);
    if ($r->phpErrors) fail("add line: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    $row = t_row('SELECT * FROM quote_items WHERE quote_id = ? AND id > ? ORDER BY id DESC LIMIT 1', [$quoteId, $before]);
    if (!$row) {
        $flash = t_flash($h, $quoteId);
        fail("the blind was not added to quote $quoteId" . ($flash !== '' ? ": $flash" : ''));
    }
    return $row;
}

/** The message shown at the top of a quote after a redirect. */
function t_flash(YbHttp $h, int $quoteId): string
{
    $page = $h->get('/quote-builder/edit.php?id=' . $quoteId);
    return preg_match('/class="alert[^"]*"[^>]*>(.*?)<\/div>/s', $page->body, $m) ? trim(preg_replace('/\s+/', ' ', strip_tags($m[1]))) : '';
}

/** Move a quote to a status through change_status.php. */
function flow_status(YbHttp $h, int $quoteId, string $target, ?array $okIfNow = null): void
{
    $r = t_submit($h, '/quote-builder/change_status.php', ['quote_id' => $quoteId, 'target_status' => $target]);
    if ($r->phpErrors) fail("status → $target: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    $now = (string) t_val('SELECT status FROM quotes WHERE id = ?', [$quoteId]);
    if (!in_array($now, $okIfNow ?? [$target], true)) fail("status did not change to $target (still $now): " . t_flash($h, $quoteId));
}

/** Accept a trade account's quote and place it with the factory (Place order screen). */
function flow_place_order(YbHttp $h, int $quoteId): void
{
    flow_status($h, $quoteId, 'accepted', ['accepted', 'ordered']);
    // An all-in-house order goes straight to the factory on accept (auto_place_inhouse).
    if ((string) t_val('SELECT status FROM quotes WHERE id = ?', [$quoteId]) === 'ordered') return;
    $page = $h->get('/quote-builder/order_suppliers.php?id=' . $quoteId);
    check_page_ok($page, 'Place order screen');
    preg_match_all('/name="send\[\]"\s+value="([^"]*)"/', $page->body, $m);
    $r = t_submit($h, '/quote-builder/order_suppliers.php', ['quote_id' => $quoteId, 'send' => array_map('html_entity_decode', $m[1])]);
    if ($r->phpErrors) fail("place order: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    $now = (string) t_val('SELECT status FROM quotes WHERE id = ?', [$quoteId]);
    if ($now !== 'ordered') fail("the order was not placed (status $now)");
}

/**
 * A placed Alpha order for one made-in-house roller, processed by the factory
 * and finished on the floor — i.e. READY to dispatch. Returns the quote id.
 */
function flow_ready_order(string $ref): int
{
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), $ref);
    flow_add_line($a, $qid, 101, price_scenarios()['Roller, side winder, 1200 × 1600']);
    flow_place_order($a, $qid);
    $f = as_user('factory.office');
    t_submit($f, '/factory/process-order.php', ['quote_id' => $qid, 'back' => '/factory/pipeline.php']);
    t_submit($f, '/factory/set-status.php', ['quote_id' => $qid, 'status' => 'made', 'force_made' => 1, 'back' => '/factory/pipeline.php']);
    $stage = (string) t_val('SELECT fulfilment_stage FROM quotes WHERE id = ?', [$qid]);
    if ($stage !== 'ready') fail("set-up: order $ref is '$stage', not ready");
    return $qid;
}

/** Make one kind of database write fail for a while (a MySQL trigger), as a lost connection would. */
function t_break_writes(string $table, string $when, string $name): void
{
    db()->exec("DROP TRIGGER IF EXISTS `$name`");
    db()->exec("CREATE TRIGGER `$name` BEFORE $when ON `$table` FOR EACH ROW "
        . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test: write deliberately refused'");
}

function t_unbreak_writes(string $name): void
{
    db()->exec("DROP TRIGGER IF EXISTS `$name`");
}
