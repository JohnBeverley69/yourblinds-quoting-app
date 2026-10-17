<?php
declare(strict_types=1);

/**
 * SHARED FASCIA WIDTH. Several roller blinds under one fascia ("Multi blind"):
 * when nobody types a Fascia width, the carrier blind's fascia is priced and cut
 * at the blinds' total width (+ joins). That total is worked out by the system,
 * so it must FOLLOW the blinds — edit one, the fascia changes; delete one so a
 * single blind is left, and that blind loses the group's width.
 * (Before the fix the worked-out total was saved as if typed and never changed.)
 */

require_once APP_ROOT . '/tests/lib/flows.php';

/** The two-blind group on a fresh Alpha quote: 1000 + 1000, Fascia width blank. */
function fg_two_blind_quote(YbHttp $a, string $ref): int
{
    $qid = flow_new_quote($a, fx('customer_alpha'), $ref);
    $q = price_scenarios()['Roller, 3 blinds on one Senses fascia'];
    $q['multi_fascia'] = ['active' => 1, 'widths' => ['1000', '1000'], 'drops' => ['', '']];
    flow_add_line($a, $qid, 101, $q);
    check_same(2, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qid]), 'blinds in the group');
    return $qid;
}

/** Stored "Fascia width" on each line of the quote, by line id (null = none). */
function fg_widths(int $qid): array
{
    $out = [];
    foreach (t_rows('SELECT id FROM quote_items WHERE quote_id = ? ORDER BY line_no, id', [$qid]) as $r) {
        $v = t_val("SELECT user_value FROM quote_item_extras WHERE quote_item_id = ? AND extra_name_snapshot = 'Fascia Width' LIMIT 1", [(int) $r['id']]);
        $out[(int) $r['id']] = ($v === false || $v === null) ? null : (float) $v;
    }
    return $out;
}

function fg_gap(): float
{
    $g = t_val("SELECT value FROM allowance_rows WHERE table_name = 'roller_fascia_join' AND key_norm = 'gap' LIMIT 1");
    return ($g === false || $g === null) ? 0.0 : (float) $g;
}

/** Re-save a stored line through update_item.php, as the Edit form would, with a new width. */
function fg_edit_width(YbHttp $a, int $qid, int $itemId, int $width): void
{
    $ln = t_row('SELECT * FROM quote_items WHERE id = ?', [$itemId]);
    $extras = [];
    // A worked-out fascia width (is_auto) is not put back in the form (edit.php).
    foreach (t_rows('SELECT product_extra_id, product_extra_choice_id, user_value, is_auto FROM quote_item_extras WHERE quote_item_id = ? ORDER BY id', [$itemId]) as $e) {
        if ((int) $e['is_auto'] === 1) continue;
        $row = ['extra_id' => (int) $e['product_extra_id']];
        if ((int) $e['product_extra_choice_id'] > 0) $row['choice_id'] = (int) $e['product_extra_choice_id'];
        if ($e['user_value'] !== null && (float) $e['user_value'] > 0) $row['user_value'] = (string) (float) $e['user_value'];
        $extras[] = $row;
    }
    $r = t_submit($a, '/quote-builder/update_item.php', [
        'quote_id' => $qid, 'item_id' => $itemId, 'room_name' => (string) $ln['room_name'],
        'product_id' => (int) $ln['product_id'], 'system_id' => (int) $ln['system_id'], 'option_id' => (int) $ln['option_id'],
        'width' => (string) $width, 'drop' => (string) (int) $ln['drop_mm'], 'quantity' => (int) $ln['quantity'],
        'round_up' => 1, 'unit' => 'mm', 'extras' => $extras,
    ]);
    if ($r->phpErrors) fail("edit line: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same($width, (int) t_val('SELECT width_mm FROM quote_items WHERE id = ?', [$itemId]), 'the edited blind\'s width (' . t_flash($a, $qid) . ')');
}

test('shared fascia: editing a blind in the group re-works the fascia width and price', function () {
    $a = as_user('alpha.admin');
    $qid = fg_two_blind_quote($a, 'FASCIA-EDIT');
    $gap = fg_gap();
    $w = fg_widths($qid);
    [$carrier, $other] = array_keys($w);
    check_same(2000 + $gap, $w[$carrier], 'carrier fascia width after adding 1000 + 1000');
    check_same(null, $w[$other], 'the second blind carries no fascia width');
    $priceBefore = (float) t_val('SELECT line_total FROM quote_items WHERE id = ?', [$carrier]);

    fg_edit_width($a, $qid, $other, 1500);
    check_same(2500 + $gap, fg_widths($qid)[$carrier], 'carrier fascia width after blind 2 went to 1500');
    $priceAfter = (float) t_val('SELECT line_total FROM quote_items WHERE id = ?', [$carrier]);
    check($priceAfter > $priceBefore, "the carrier's price did not follow the wider fascia (£$priceBefore → £$priceAfter)");

    // Editing the carrier itself (nothing typed in Fascia width) keeps it worked out.
    fg_edit_width($a, $qid, $carrier, 1100);
    check_same(2600 + $gap, fg_widths($qid)[$carrier], 'carrier fascia width after the carrier went to 1100');
    fg_edit_width($a, $qid, $other, 1200);
    check_same(2300 + $gap, fg_widths($qid)[$carrier], 'carrier fascia width after blind 2 went to 1200');
});

test('shared fascia: deleting down to one blind drops the group width from the one left', function () {
    $a = as_user('alpha.admin');
    $qid = fg_two_blind_quote($a, 'FASCIA-DELETE');
    [$carrier, $other] = array_keys(fg_widths($qid));
    $r = t_submit($a, '/quote-builder/delete_item.php', ['quote_id' => $qid, 'item_id' => $other]);
    if ($r->phpErrors) fail("delete line: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same(1, (int) t_val('SELECT COUNT(*) FROM quote_items WHERE quote_id = ?', [$qid]), 'blinds left');
    check_same([$carrier => null], fg_widths($qid), 'the lone blind still carries the 2000mm group fascia width');
});

test('shared fascia: a Fascia width that was typed is kept when a blind changes', function () {
    $a = as_user('alpha.admin');
    $qid = flow_new_quote($a, fx('customer_alpha'), 'FASCIA-TYPED');
    $q = price_scenarios()['Roller, 3 blinds on one Senses fascia'];
    $q['multi_fascia'] = ['active' => 1, 'widths' => ['1000', '1000'], 'drops' => ['', '']];
    $q['extras'][] = ['extra_id' => 13077, 'user_value' => 2600];
    flow_add_line($a, $qid, 101, $q);
    $w = fg_widths($qid);
    [$carrier, $other] = array_keys($w);
    check_same(2600.0, $w[$carrier], 'typed fascia width on the carrier');
    fg_edit_width($a, $qid, $other, 1200);
    check_same(2600.0, fg_widths($qid)[$carrier], 'typed fascia width after blind 2 changed');
});
