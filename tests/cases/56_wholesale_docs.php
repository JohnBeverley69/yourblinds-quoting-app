<?php
declare(strict_types=1);

/**
 * WHOLESALE DOCUMENTS. The Wholesale hub used to load only the newest 100
 * delivery notes / invoices / credit notes for the whole factory. Once more than
 * 100 had been raised, an older order on the page lost its documents: the wrong
 * stage pill, no Void / Full credit / Part credit, and a "Raise delivery note"
 * button that made a second note for an order already delivered.
 */

require_once APP_ROOT . '/_partials/factory_ar.php';

test('an older order still shows its invoice and actions once 100+ documents have been raised, and is not re-raised', function () {
    $q = flow_ready_order('OLD-DOCS');
    $f = as_user('factory.office');
    t_submit($f, '/master-admin/wholesale.php', ['_action' => 'dn_raise', 'quote_id' => $q]);
    $dnId = (int) t_val("SELECT id FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND factory_client_id = 3 AND status = 'draft'", [$q]);
    check($dnId > 0, 'set-up: no draft delivery note');
    t_submit($f, '/master-admin/wholesale.php', ['_action' => 'dn_dispatch', 'dn_id' => $dnId]);
    t_submit($f, '/master-admin/wholesale.php', ['_action' => 'inv_raise', 'quote_id' => $q]);
    $invNo = (string) t_val("SELECT i.inv_number FROM factory_ar_invoices i JOIN factory_ar_invoice_orders o ON o.invoice_id = i.id
                              WHERE o.quote_id = ? AND i.status <> 'void'", [$q]);
    check($invNo !== '', 'set-up: no invoice raised');
    $ordNo = strtolower((string) t_val('SELECT quote_number FROM quotes WHERE id = ?', [$q]));

    // 120 newer delivery notes and invoices for other (made-up) orders, so this
    // order's documents are well outside "the newest 100".
    $pdo = db();
    $made = ['dn' => [], 'inv' => []];
    try {
        $insDn  = $pdo->prepare("INSERT INTO factory_ar_delivery_notes (factory_client_id, account_client_id, dn_number, source_quote_id, status, dispatched_at)
                                 VALUES (3, 101, ?, ?, 'dispatched', NOW())");
        $insInv = $pdo->prepare("INSERT INTO factory_ar_invoices (factory_client_id, account_client_id, inv_number, status, issue_date, subtotal, vat, total)
                                 VALUES (3, 101, ?, 'raised', CURDATE(), 10, 2, 12)");
        $insIo  = $pdo->prepare('INSERT INTO factory_ar_invoice_orders (invoice_id, quote_id, factory_client_id) VALUES (?, ?, 3)');
        for ($n = 1; $n <= 120; $n++) {
            $fake = 900000 + $n;
            $insDn->execute(['DN-BULK-' . $n, $fake]);
            $made['dn'][] = (int) $pdo->lastInsertId();
            $insInv->execute(['INV-BULK-' . $n]);
            $iid = (int) $pdo->lastInsertId();
            $made['inv'][] = $iid;
            $insIo->execute([$iid, $fake]);
        }

        $page = $f->get('/master-admin/wholesale.php');
        check_page_ok($page, 'Wholesale');
        $body = $page->body;
        $at = strpos($body, 'data-order="' . $ordNo . '"');
        check($at !== false, "order $ordNo is not listed on Wholesale");
        $end = strpos($body, 'class="wh-row"', $at + 10);
        $row = substr($body, $at, $end === false ? null : $end - $at);

        check(!preg_match('/data-status="(ordered|delivered)"/', substr($row, 0, 300)), 'the order\'s stage lost its invoice (shows Ordered/Delivered)');
        check_contains($invNo, $row, 'the order\'s invoice on Wholesale');
        check_contains('Full credit', $row, 'the Full credit action');
        check_contains('Part credit', $row, 'the Part credit action');
        check_contains('value="inv_void"', $row, 'the Void action');
        check_not_contains('Raise delivery note', $row, 'a "Raise delivery note" button on an already-delivered order');

        // A stale "Raise delivery note" post (old page, second tab, double click)
        // must not make a second note...
        t_submit($f, '/master-admin/wholesale.php', ['_action' => 'dn_raise', 'quote_id' => $q]);
        check_same(1, (int) t_val("SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND factory_client_id = 3 AND status <> 'cancelled'", [$q]),
            'live delivery notes after a second "Raise delivery note"');

        // ...but the deliberate "+ Duplicate delivery note" still does.
        t_submit($f, '/master-admin/wholesale.php', ['_action' => 'dn_raise', 'quote_id' => $q, 'duplicate' => 1]);
        check_same(2, (int) t_val("SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND factory_client_id = 3 AND status <> 'cancelled'", [$q]),
            'live delivery notes after "+ Duplicate delivery note"');
        check_same(1, (int) t_val("SELECT COUNT(*) FROM factory_ar_invoice_orders o JOIN factory_ar_invoices i ON i.id = o.invoice_id WHERE o.quote_id = ? AND i.status <> 'void'", [$q]),
            'invoices after "+ Duplicate delivery note"');
        // Tidy: cancel the deliberate duplicate so later checks see one live note.
        $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'cancelled' WHERE source_quote_id = ? AND factory_client_id = 3 AND status = 'draft'")->execute([$q]);
    } finally {
        if ($made['inv']) {
            $in = implode(',', array_map('intval', $made['inv']));
            $pdo->exec("DELETE FROM factory_ar_invoice_orders WHERE invoice_id IN ($in)");
            $pdo->exec("DELETE FROM factory_ar_invoices WHERE id IN ($in)");
        }
        if ($made['dn']) {
            $pdo->exec('DELETE FROM factory_ar_delivery_notes WHERE id IN (' . implode(',', array_map('intval', $made['dn'])) . ')');
        }
    }
});
