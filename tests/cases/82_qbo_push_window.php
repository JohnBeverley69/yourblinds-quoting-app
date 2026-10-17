<?php
declare(strict_types=1);

/**
 * QUICKBOOKS PUSH NEVER LOSES A NEW PAYMENT (overnight audit 2026-10-17).
 * The push looked at "the oldest 200 payments since the send-from date" (20 for
 * the instant send after a payment is saved) — and those were already sent. So
 * from payment 201 (21 for the instant send) nothing new ever reached
 * QuickBooks, and every run said "0 sent, 0 held, 0 failed".
 *
 * These tests never talk to QuickBooks: ac_http() is stubbed, and the first
 * test checks the stub was never even called.
 */

require_once APP_ROOT . '/_partials/qbo_push.php';

const QPW_FACTORY = 3;
const QPW_ACCOUNT = 101;   // Alpha Blinds, a trade account
const QPW_FROM    = '2026-12-20';
const QPW_REALM   = 'TEST-REALM-QPW';

/** Connected (to nowhere), push on, mapping filled in. */
function qpw_connect(): void
{
    ac_save_connection(QPW_FACTORY, 'quickbooks', [
        'environment' => 'sandbox', 'realm_id' => QPW_REALM, 'company_name' => 'Test books',
        'access_token' => 'test-access', 'refresh_token' => 'test-refresh',
        'access_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'refresh_expires_at' => date('Y-m-d H:i:s', time() + 86400 * 30),
        'status' => 'connected', 'connected_at' => date('Y-m-d H:i:s'),
        'mapping_json' => json_encode(['push_on' => true, 'push_from' => QPW_FROM,
            'sales_item_id' => '1', 'tax_code_id' => '2', 'deposit_account_id' => '3']),
    ]);
}

function qpw_cleanup(): void
{
    $pdo = db();
    $pdo->prepare("DELETE a FROM factory_ar_payment_allocations a JOIN factory_ar_payments p ON p.id = a.payment_id
                    WHERE p.factory_client_id = ? AND p.pay_number LIKE 'ZZQ-%'")->execute([QPW_FACTORY]);
    $pdo->prepare("DELETE FROM factory_ar_payments WHERE factory_client_id = ? AND pay_number LIKE 'ZZQ-%'")->execute([QPW_FACTORY]);
    $pdo->prepare("DELETE FROM factory_ar_invoices WHERE factory_client_id = ? AND inv_number LIKE 'ZZQ-%'")->execute([QPW_FACTORY]);
    $pdo->prepare("DELETE FROM accounting_sync WHERE client_id = ? AND realm_id = ?")->execute([QPW_FACTORY, QPW_REALM]);
    $pdo->prepare("DELETE FROM accounting_log WHERE client_id = ? AND provider = 'quickbooks'")->execute([QPW_FACTORY]);
    $pdo->prepare("DELETE FROM accounting_connections WHERE client_id = ? AND provider = 'quickbooks'")->execute([QPW_FACTORY]);
    unset($GLOBALS['ac_http_test_stub']);
}

function qpw_invoice(string $num, string $date, string $status = 'issued'): int
{
    db()->prepare("INSERT INTO factory_ar_invoices (factory_client_id, account_client_id, inv_number, status, issue_date, subtotal, vat, total)
                   VALUES (?, ?, ?, ?, ?, 10, 0, 10)")->execute([QPW_FACTORY, QPW_ACCOUNT, $num, $status, $date]);
    return (int) db()->lastInsertId();
}

function qpw_payment(string $num, string $date, int $invoiceId): int
{
    db()->prepare("INSERT INTO factory_ar_payments (factory_client_id, account_client_id, pay_number, payment_date, amount)
                   VALUES (?, ?, ?, ?, 10)")->execute([QPW_FACTORY, QPW_ACCOUNT, $num, $date]);
    $id = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO factory_ar_payment_allocations (payment_id, invoice_id, amount) VALUES (?, ?, 10)')->execute([$id, $invoiceId]);
    return $id;
}

/** An accounting_sync row saying it already went across, exactly as qbo_push_payment() would have left it. */
function qpw_mark_sent(string $entity, int $localId, string $ref, string $fp): void
{
    db()->prepare("INSERT INTO accounting_sync (client_id, provider, realm_id, entity, local_id, external_id, status, fingerprint, ref, sent_at)
                   VALUES (?, 'quickbooks', ?, ?, ?, ?, 'sent', ?, ?, NOW())")
        ->execute([QPW_FACTORY, QPW_REALM, $entity, $localId, 'QB' . $entity . $localId, $fp, $ref]);
}

test('a new payment still reaches the push when 25 older ones have already been sent', function () {
    qpw_cleanup();
    $calls = [];
    $GLOBALS['ac_http_test_stub'] = static function (string $m, string $u) use (&$calls): array {
        $calls[] = "$m $u";
        return ['status' => 500, 'data' => [], 'raw' => 'stubbed', 'headers' => [], 'tid' => null];
    };
    try {
        qpw_connect();
        qbo_push_ensure_schema(db());
        // 25 payments, all already in QuickBooks and unchanged since.
        $sentIds = [];
        for ($i = 1; $i <= 25; $i++) {
            $d   = date('Y-m-d', strtotime(QPW_FROM . " +$i day"));
            $inv = qpw_invoice(sprintf('ZZQ-I%03d', $i), $d);
            $pay = qpw_payment(sprintf('ZZQ-P%03d', $i), $d, $inv);
            // Same fingerprint qbo_push_payment() works out: [amount, [[invoice id, allocated]]].
            $fp = sha1(json_encode([round(10.0, 2), [[$inv, round(0.0 + 10.0, 2)]]]));
            qpw_mark_sent('payment', $pay, sprintf('ZZQ-P%03d', $i), $fp);
            qpw_mark_sent('invoice', $inv, sprintf('ZZQ-I%03d', $i), 'x');
            $sentIds[] = $pay;
        }
        // Proves the rows above look "already sent, nothing changed" to the push.
        check_same('unchanged', qbo_push_payment(db(), QPW_FACTORY, qbo_push_settings(QPW_FACTORY), $sentIds[0]), 'an already-sent payment');

        // The new one pays an invoice from before the send-from date, so the push
        // decides "nothing to send" locally — no QuickBooks call needed to see it was looked at.
        $old = qpw_invoice('ZZQ-IOLD', '2026-11-01');
        $new = qpw_payment('ZZQ-PNEW', '2027-02-01', $old);

        $r = qbo_push_run(QPW_FACTORY, 20);   // the size of the instant send after a payment is saved
        check($r['ran'], 'the push did not run: ' . $r['message']);
        $st = t_val("SELECT status FROM accounting_sync WHERE client_id = ? AND realm_id = ? AND entity = 'payment' AND local_id = ?",
                    [QPW_FACTORY, QPW_REALM, $new]);
        check($st !== null && $st !== false, 'the new payment was never looked at (it fell outside the first 20) — run said: ' . $r['message']);
        check_same('skipped', $st, 'the new payment\'s sync status');
        check_same(0, $r['failed'], 'failed count');
        check_same([], $calls, 'calls that tried to reach QuickBooks');
    } finally {
        qpw_cleanup();
    }
});

test('already-sent payments still get re-checked in turn (so a re-allocation is noticed)', function () {
    qpw_cleanup();
    $GLOBALS['ac_http_test_stub'] = static fn (): array => ['status' => 500, 'data' => [], 'raw' => 'stubbed', 'headers' => [], 'tid' => null];
    try {
        qpw_connect();
        qbo_push_ensure_schema(db());
        $ids = [];
        for ($i = 1; $i <= 25; $i++) {
            $d   = date('Y-m-d', strtotime(QPW_FROM . " +$i day"));
            $inv = qpw_invoice(sprintf('ZZQ-I%03d', $i), $d);
            $pay = qpw_payment(sprintf('ZZQ-P%03d', $i), $d, $inv);
            qpw_mark_sent('payment', $pay, sprintf('ZZQ-P%03d', $i), sha1(json_encode([10.0, [[$inv, 10.0]]])));
            $ids[] = $pay;
        }
        qbo_push_run(QPW_FACTORY, 20);
        qbo_push_run(QPW_FACTORY, 20);
        $in = implode(',', array_map('intval', $ids));
        check_same(25, (int) t_val("SELECT COUNT(*) FROM accounting_sync WHERE client_id = ? AND realm_id = ? AND entity = 'payment'
                                    AND local_id IN ($in) AND checked_at IS NOT NULL", [QPW_FACTORY, QPW_REALM]),
                   'sent payments re-checked after two runs of 20');
    } finally {
        qpw_cleanup();
    }
});

test('one invoice QuickBooks won\'t void doesn\'t stop the others being voided', function () {
    qpw_cleanup();
    $voidPosts = [];
    $GLOBALS['ac_http_test_stub'] = static function (string $m, string $u, array $o) use (&$voidPosts): array {
        if ($m === 'GET') return ['status' => 200, 'data' => ['Invoice' => ['SyncToken' => '0']], 'raw' => '{}', 'headers' => [], 'tid' => null];
        $b = json_decode((string) ($o['body'] ?? '{}'), true);
        $voidPosts[] = (string) ($b['Id'] ?? '');
        if (($b['Id'] ?? '') === 'BAD') {
            return ['status' => 400, 'data' => ['Fault' => ['Error' => [['Message' => 'Stubbed refusal']]]], 'raw' => '{}', 'headers' => [], 'tid' => null];
        }
        return ['status' => 200, 'data' => ['Invoice' => ['Id' => $b['Id'] ?? '']], 'raw' => '{}', 'headers' => [], 'tid' => null];
    };
    try {
        qpw_connect();
        qbo_push_ensure_schema(db());
        qbc_realm_schema(db(), QPW_FACTORY);
        $bad  = qpw_invoice('ZZQ-IBAD', '2026-12-21', 'void');
        $good = qpw_invoice('ZZQ-IGOOD', '2026-12-22', 'void');
        db()->prepare("INSERT INTO accounting_sync (client_id, provider, realm_id, entity, local_id, external_id, status, ref)
                       VALUES (?, 'quickbooks', ?, 'invoice', ?, 'BAD', 'sent', 'ZZQ-IBAD'), (?, 'quickbooks', ?, 'invoice', ?, 'GOOD', 'sent', 'ZZQ-IGOOD')")
            ->execute([QPW_FACTORY, QPW_REALM, $bad, QPW_FACTORY, QPW_REALM, $good]);
        $failed = 0;
        $n = qbo_push_voided_invoices(db(), QPW_FACTORY, $failed);
        check(in_array('GOOD', $voidPosts, true), 'the second invoice was never tried — voids sent: ' . implode(',', $voidPosts));
        check_same(1, $n, 'invoices voided');
        check_same(1, $failed, 'invoices that failed');
        check_same('voided', t_val("SELECT status FROM accounting_sync WHERE realm_id = ? AND entity = 'invoice' AND local_id = ?", [QPW_REALM, $good]), 'good invoice status');
        check_same('sent', t_val("SELECT status FROM accounting_sync WHERE realm_id = ? AND entity = 'invoice' AND local_id = ?", [QPW_REALM, $bad]), 'refused invoice stays to be tried again');
    } finally {
        qpw_cleanup();
    }
});
