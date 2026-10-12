<?php
declare(strict_types=1);

/**
 * ORDER STATE. The awkward moments between Ready and Invoiced (PRs #1107–#1113):
 * a dispatch that fails half-way, a delivery note that couldn't be made, a draft
 * note, the dispatch tray's Print & dispatch, two invoice clicks at once, and the
 * reconciliation checks that flag an order whose records disagree.
 */

require_once APP_ROOT . '/_partials/order_stage.php';
require_once APP_ROOT . '/_partials/factory_ar.php';
require_once APP_ROOT . '/_partials/order_checks.php';

$dnCount = static function (int $q, string $status = ''): int {
    return $status !== ''
        ? (int) t_val('SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND factory_client_id = 3 AND status = ?', [$q, $status])
        : (int) t_val("SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND factory_client_id = 3 AND status <> 'cancelled'", [$q]);
};
$invCount = static fn (int $q) => (int) t_val("SELECT COUNT(*) FROM factory_ar_invoice_orders o JOIN factory_ar_invoices i ON i.id = o.invoice_id
                                                WHERE o.quote_id = ? AND i.status <> 'void'", [$q]);
$stage = static fn (int $q) => (string) t_val('SELECT fulfilment_stage FROM quotes WHERE id = ?', [$q]);

/** dispatch_to_invoice_orders() lives in a page (master-admin/dispatch.php); the same rule, read here. */
function os_to_invoice_ids(): array
{
    $out = [];
    foreach (ar_placed_orders(db(), 3) as $o) {
        if (((int) ($o['dn_dispatched'] ?? 0)) < 1) continue;
        if (ar_order_invoice_number(db(), (int) $o['id']) !== '') continue;
        $out[] = (int) $o['id'];
    }
    return $out;
}

function os_missing_ids(): array
{
    return array_map(static fn ($r) => (int) ($r['quote_id'] ?? $r['id'] ?? 0), os_paperwork_missing(db(), 3));
}

test('a dispatch whose status write fails makes no delivery note and no invoice (#1107)', function () use ($dnCount, $invCount, $stage) {
    $q = flow_ready_order('FAILED-DISPATCH');
    app_setting_set('auto_invoice_on_dispatch', '1');   // an invoice would follow straight on
    t_break_writes('factory_jobs', 'UPDATE', 'yb_test_fj_upd');
    t_break_writes('factory_jobs', 'INSERT', 'yb_test_fj_ins');
    try {
        $r = t_submit(as_user('factory.office'), '/factory/set-status.php', ['quote_id' => $q, 'status' => 'dispatched', 'back' => '/factory/pipeline.php']);
        check(!$r->phpErrors, "PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    } finally {
        t_unbreak_writes('yb_test_fj_upd');
        t_unbreak_writes('yb_test_fj_ins');
        app_setting_set('auto_invoice_on_dispatch', '0');
    }
    check_same(0, $dnCount($q), 'delivery notes after the failed dispatch');
    check_same(0, $invCount($q), 'invoices after the failed dispatch');
    check_same('ready', $stage($q), 'stage after the failed dispatch');
});

test('a delivery note that can\'t be made is reported, flagged "Paperwork missing" and can be retried (#1111)', function () use ($dnCount, $stage) {
    $q = flow_ready_order('DN-FAILS');
    $f = as_user('factory.office');
    t_break_writes('factory_ar_delivery_notes', 'INSERT', 'yb_test_dn_ins');
    try {
        $r = t_submit($f, '/factory/set-status.php', ['quote_id' => $q, 'status' => 'dispatched', 'back' => '/factory/pipeline.php']);
        check(!$r->phpErrors, "PHP problems:\n    " . implode("\n    ", $r->phpErrors));
        $pipe = $f->get('/factory/pipeline.php');
        check_page_ok($pipe, 'Pipeline');
        check_contains('delivery note couldn', html_entity_decode($pipe->body, ENT_QUOTES), 'the warning after dispatch');
    } finally {
        t_unbreak_writes('yb_test_dn_ins');
    }
    check_same(0, $dnCount($q), 'delivery notes while writes were refused');
    check(in_array($q, os_missing_ids(), true), 'the order is not listed as "paperwork missing"');
    check_contains('Paperwork missing', $f->get('/factory/pipeline.php')->body, 'Pipeline banner');

    $r = t_submit($f, '/factory/retry-paperwork.php', ['quote_id' => $q, 'back' => '/factory/pipeline.php']);
    check(!$r->phpErrors, "retry: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check_same(1, $dnCount($q, 'dispatched'), 'dispatched delivery notes after Retry');
    check(!in_array($q, os_missing_ids(), true), 'still "paperwork missing" after Retry');
    check_same('dispatched', $stage($q), 'stage after Retry');
});

test('a DRAFT delivery note does not make an order invoiceable; dispatching it does (#1110)', function () use ($dnCount, $invCount) {
    $q = flow_ready_order('DRAFT-DN');
    $f = as_user('factory.office');
    t_submit($f, '/master-admin/wholesale.php', ['_action' => 'dn_raise', 'quote_id' => $q]);
    check_same(1, $dnCount($q, 'draft'), 'draft delivery notes after "raise"');
    check(!in_array($q, os_to_invoice_ids(), true), 'an order with only a DRAFT note is listed "to invoice"');
    t_submit($f, '/master-admin/dispatch.php', ['_action' => 'invoice', 'quote_ids' => [$q]]);
    check_same(0, $invCount($q), 'invoices raised from the tray for an order with only a draft note');
    $dn = (int) t_val("SELECT id FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND status = 'draft'", [$q]);
    t_submit($f, '/master-admin/wholesale.php', ['_action' => 'dn_dispatch', 'dn_id' => $dn]);
    check_same(1, $dnCount($q, 'dispatched'), 'dispatched notes after "dispatch"');
    check(in_array($q, os_to_invoice_ids(), true), 'not listed to invoice once its note went out');
    t_submit($f, '/master-admin/dispatch.php', ['_action' => 'invoice', 'quote_ids' => [$q]]);
    check_same(1, $invCount($q), 'invoices raised from the tray once the note went out');
});

test('the dispatch tray\'s "Print & dispatch" dispatches what it printed (#1110)', function () use ($dnCount, $stage) {
    $q = flow_ready_order('TRAY-PRINT');
    $f = as_user('factory.office');
    $num = (string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$q]);
    check_contains($num, $f->get('/master-admin/dispatch.php')->body, 'the ready order in the dispatch tray');
    $r = t_submit($f, '/master-admin/dispatch.php', ['_action' => 'print', 'quote_ids' => [$q]]);
    check(!$r->phpErrors, "print: PHP problems:\n    " . implode("\n    ", $r->phpErrors));
    check($r->status === 200 && strncmp($r->body, '%PDF', 4) === 0, 'the tray did not return a PDF (HTTP ' . $r->status . ')');
    check_same(1, $dnCount($q, 'dispatched'), 'dispatched notes after printing');
    check_same('dispatched', $stage($q), 'stage after printing');
    check(in_array($q, os_to_invoice_ids(), true), 'the printed order is not "to invoice"');
});

test('five invoice requests at the same moment make ONE invoice (#1109)', function () use ($invCount) {
    $q = flow_ready_order('RACE');
    t_submit(as_user('factory.office'), '/factory/set-status.php', ['quote_id' => $q, 'status' => 'dispatched', 'back' => '/factory/pipeline.php']);
    // Five separate PHP processes all call ar_create_invoice() for the order at once.
    $script = (string) getenv('YB_TEST_OUT') . '/race_invoice.php';
    file_put_contents($script, "<?php\n"
        . 'require ' . var_export(APP_ROOT . '/tests/lib/server_prepend.php', true) . ";\n"
        . 'require ' . var_export(APP_ROOT . '/bootstrap.php', true) . ";\n"
        . 'require ' . var_export(APP_ROOT . '/auth/middleware.php', true) . ";\n"
        . 'require_once ' . var_export(APP_ROOT . '/_partials/factory_ar.php', true) . ";\n"
        . '$go = (float) $argv[2]; while (microtime(true) < $go) usleep(500);' . "\n"
        . 'try { $i = ar_create_invoice(db(), 3, (int) $argv[1], 101, 0, false); echo "made " . $i["number"]; }' . "\n"
        . 'catch (ArAlreadyInvoicedException $e) { echo "already"; }' . "\n"
        . 'catch (Throwable $e) { echo "error " . $e->getMessage(); }' . "\n");
    $go = (string) (microtime(true) + 2.0);
    $procs = []; $logs = [];
    for ($i = 0; $i < 5; $i++) {
        $logs[$i] = $script . ".$i.log";
        $procs[$i] = proc_open([PHP_BINARY, $script, (string) $q, $go], [0 => ['pipe', 'r'], 1 => ['file', $logs[$i], 'w'], 2 => ['file', $logs[$i], 'a']], $pipes, APP_ROOT, getenv());
        fclose($pipes[0]);
    }
    $results = [];
    foreach ($procs as $i => $p) { proc_close($p); $results[] = trim((string) @file_get_contents($logs[$i])); @unlink($logs[$i]); }
    @unlink($script);
    check_same(1, $invCount($q), 'invoices after 5 simultaneous requests (' . implode(' | ', $results) . ')');
    check_same(1, count(array_filter($results, static fn ($r) => strpos($r, 'made') === 0)), 'requests that made an invoice (' . implode(' | ', $results) . ')');
});

test('the reconciliation check flags orders whose records disagree (#1113)', function () {
    $a = flow_ready_order('CHECK-NODN');
    db()->prepare("UPDATE factory_jobs SET status = 'dispatched' WHERE quote_id = ?")->execute([$a]);    // dispatched, no note
    $b = flow_ready_order('CHECK-FLOOR');
    db()->prepare("INSERT INTO factory_ar_delivery_notes (factory_client_id, account_client_id, dn_number, source_quote_id, status, dispatched_at, created_by)
                   VALUES (3, 101, 'DN-CHECK-B', ?, 'dispatched', NOW(), 0)")->execute([$b]);             // note out, floor says made
    $found = [];
    foreach (oc_findings(db(), 3) as $f) $found[$f['check']][] = (int) $f['quote_id'];
    check(in_array($a, $found['dispatched_no_dn'] ?? [], true), 'dispatched with no delivery note was not flagged: ' . t_show(array_keys($found)));
    check(in_array($b, $found['dn_floor_not'] ?? [], true), 'note dispatched while the floor says not was not flagged: ' . t_show(array_keys($found)));
    check_page_ok(as_user('super')->get('/factory/checks.php'), 'the checks page');
    // Put them straight so later tests aren't confused.
    db()->prepare("UPDATE factory_jobs SET status = 'made' WHERE quote_id = ?")->execute([$a]);
    db()->exec("DELETE FROM factory_ar_delivery_notes WHERE dn_number = 'DN-CHECK-B'");
});
