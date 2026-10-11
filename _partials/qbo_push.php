<?php
declare(strict_types=1);

/**
 * QuickBooks push (step 2c): when the factory records a payment from a trade
 * account, the invoice(s) it pays go into the company's QuickBooks, followed
 * by ONE payment applied across them — so QuickBooks matches the single
 * Barclays line. Cash accounting (Annette): nothing goes across until it's
 * paid; an unpaid invoice is never sent.
 *
 * It is a RECONCILER, not a hook that fires once: qbo_push_run() works out,
 * for every payment since the "send from" date, what QuickBooks should hold
 * and brings it into line. So a payment recorded on the Bank screen, by hand,
 * by "apply credit", or later voided, all come right on the next run, and a
 * failed send is simply tried again. accounting_sync remembers what was sent
 * (one row per payment / invoice, with QuickBooks' id and a fingerprint of
 * what was sent), so nothing is ever sent twice.
 *
 * Runs: straight after a payment is saved (qbo_push_kick, best effort), every
 * ~15 minutes from cron_messages.php, and on "Send now" in Master Admin.
 *
 * Off until switched on in Master Admin → QuickBooks, and only for invoices
 * dated on/after the "send from" date — so invoices Annette already entered
 * from Blind Matrix are never sent again during the changeover.
 *
 * Held (not sent, shown with the reason) rather than guessed:
 *   - the trade account isn't matched to a QuickBooks customer yet
 *   - an invoice has a credit note against it (enter that one by hand for now)
 *   - no QuickBooks VAT code for the invoice's VAT rate
 */

require_once __DIR__ . '/accounting.php';
require_once __DIR__ . '/quickbooks.php';
require_once __DIR__ . '/qbo_customers.php';
require_once __DIR__ . '/factory_ar.php';

/** A reason to hold something back (shown to the user) rather than a failure to retry. */
if (!class_exists('QboHeld')) {
    final class QboHeld extends RuntimeException {}
}

/** Push settings for the factory's QuickBooks connection (kept in its mapping_json). */
function qbo_push_settings(int $factory): array
{
    $conn = ac_get_connection($factory, 'quickbooks');
    $m = json_decode((string) ($conn['mapping_json'] ?? ''), true) ?: [];
    $from = (string) ($m['push_from'] ?? '');
    return [
        'on'      => !empty($m['push_on']),
        'from'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '',
        'mapping' => $m,
        'conn'    => $conn,
    ];
}

function qbo_push_save_settings(int $factory, bool $on, string $from): void
{
    $conn = ac_get_connection($factory, 'quickbooks');
    if (!$conn) throw new RuntimeException('Connect QuickBooks first.');
    $m = json_decode((string) ($conn['mapping_json'] ?? ''), true) ?: [];
    $m['push_on']   = $on;
    $m['push_from'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : '';
    ac_save_connection($factory, 'quickbooks', ['mapping_json' => json_encode($m)]);
}

/** Why sending can't run yet ('' = it can). */
function qbo_push_blocker(array $s): string
{
    if (!ac_is_connected($s['conn']) || ac_needs_reconnect($s['conn'])) return 'QuickBooks isn\'t connected.';
    $m = $s['mapping'];
    if (empty($m['sales_item_id']) || empty($m['tax_code_id']) || empty($m['deposit_account_id'])) {
        return 'Set up the mapping first (sales item, VAT code and bank account).';
    }
    if ($s['from'] === '') return 'Choose the date to send invoices from.';
    return '';
}

/** accounting_sync gets two columns the push needs (added on first use; idempotent). */
function qbo_push_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $has = $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounting_sync'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!$has) throw new RuntimeException('Run setup/migrations/migrate_accounting_sync.php first.');
    if (!in_array('fingerprint', $has, true)) $pdo->exec("ALTER TABLE accounting_sync ADD COLUMN fingerprint VARCHAR(64) NULL");
    if (!in_array('ref', $has, true))         $pdo->exec("ALTER TABLE accounting_sync ADD COLUMN ref VARCHAR(40) NULL");
    $done = true;
}

function qbo_sync_row(PDO $pdo, int $factory, string $entity, int $localId): ?array
{
    $st = $pdo->prepare('SELECT * FROM accounting_sync WHERE client_id = ? AND provider = ? AND entity = ? AND local_id = ?');
    $st->execute([$factory, 'quickbooks', $entity, $localId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function qbo_sync_set(PDO $pdo, int $factory, string $entity, int $localId, string $ref, array $f): void
{
    $cur = qbo_sync_row($pdo, $factory, $entity, $localId);
    $f += ['ref' => mb_substr($ref, 0, 40)];
    if (in_array($f['status'] ?? '', ['failed'], true)) $f['attempts'] = (int) ($cur['attempts'] ?? 0) + 1;
    if (($f['status'] ?? '') === 'sent') $f['attempts'] = 0;
    if ($cur) {
        $set = []; $vals = [];
        foreach ($f as $k => $v) { $set[] = "$k = ?"; $vals[] = $v; }
        $vals[] = (int) $cur['id'];
        $pdo->prepare('UPDATE accounting_sync SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
    } else {
        $cols = array_merge(['client_id', 'provider', 'entity', 'local_id'], array_keys($f));
        $vals = array_merge([$factory, 'quickbooks', $entity, $localId], array_values($f));
        $pdo->prepare('INSERT INTO accounting_sync (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute($vals);
    }
}

/** Escape a value for a QuickBooks query string literal. */
function qbo_q(string $s): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $s);
}

/**
 * The QuickBooks VAT code for a VAT rate. 20% (or the mapped rate) uses the
 * code chosen in the mapping; anything else is looked up by its rate in the
 * company's own codes. Null when there's no unambiguous code.
 */
function qbo_tax_code_for_rate(int $factory, array $mapping, float $rate): ?string
{
    static $byRate = null;
    $rate = round($rate, 2);
    if ($byRate === null) {
        $byRate = [];
        $rates = [];
        foreach ((array) (qbo_query($factory, 'SELECT Id, RateValue, Active FROM TaxRate MAXRESULTS 1000')['TaxRate'] ?? []) as $r) {
            $rates[(string) $r['Id']] = round((float) ($r['RateValue'] ?? -1), 2);
        }
        foreach ((array) (qbo_query($factory, 'SELECT * FROM TaxCode MAXRESULTS 1000')['TaxCode'] ?? []) as $c) {
            if (isset($c['Active']) && $c['Active'] === false) continue;
            $details = (array) ($c['SalesTaxRateList']['TaxRateDetail'] ?? []);
            if (count($details) !== 1) continue;   // a single sales rate only
            $rv = $rates[(string) ($details[0]['TaxRateRef']['value'] ?? '')] ?? null;
            if ($rv === null) continue;
            $byRate[(string) $rv][] = ['id' => (string) $c['Id'], 'name' => (string) ($c['Name'] ?? '')];
        }
    }
    $mappedId = (string) ($mapping['tax_code_id'] ?? '');
    foreach ($byRate[(string) $rate] ?? [] as $c) if ($c['id'] === $mappedId) return $mappedId;
    if (abs($rate - 20.0) < 0.01 && $mappedId !== '') return $mappedId;
    $cands = $byRate[(string) $rate] ?? [];
    if (count($cands) === 1) return $cands[0]['id'];
    // Several codes share the rate (e.g. 0%: No VAT / zero-rated / exempt): take the
    // plain one, in the same order the CSV export uses — never guess beyond that.
    $prefs = $rate == 0.0
        ? [static fn ($n) => str_contains($n, 'no vat'), static fn ($n) => str_contains($n, 'zero') || preg_match('/%\s*z$/', $n)]
        : [static fn ($n) => preg_match('/%\s*[sr]$/', $n)];
    foreach ($prefs as $want) {
        $hits = array_values(array_filter($cands, static fn ($c) => $want(strtolower(trim($c['name'])))));
        if (count($hits) === 1) return $hits[0]['id'];
    }
    return null;
}

/**
 * Invoice lines for QuickBooks: [description, qty, net]. No negative lines —
 * an adjustment/discount line is folded into the others so the invoice still
 * totals its net exactly (same approach as the CSV export).
 */
function qbo_invoice_lines(PDO $pdo, array $inv): array
{
    $st = $pdo->prepare('SELECT description, quantity, line_net, width_mm, drop_mm FROM factory_ar_invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id');
    $st->execute([(int) $inv['id']]);
    $pos = []; $neg = 0.0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $net = round((float) $l['line_net'], 2);
        if ($net < 0) { $neg += $net; continue; }
        if ($net == 0.0) continue;
        $d = trim((string) $l['description']);
        if (!empty($l['width_mm']) && !empty($l['drop_mm'])) $d .= ' (' . (int) $l['width_mm'] . ' x ' . (int) $l['drop_mm'] . 'mm)';
        $pos[] = [$d !== '' ? $d : 'Blinds', max(1, (int) $l['quantity']), $net];
    }
    $target = round((float) $inv['subtotal'], 2);
    if (!$pos) return [['Invoice ' . $inv['inv_number'], 1, $target]];
    $sum = round(array_sum(array_column($pos, 2)), 2);
    if (abs($sum - $target) >= 0.01 && $sum > 0) {
        $k = $target / $sum;
        foreach ($pos as &$p) $p[2] = round($p[2] * $k, 2);
        unset($p);
        $rem = round($target - array_sum(array_column($pos, 2)), 2);
        if (abs($rem) >= 0.01) {
            $big = 0;
            foreach ($pos as $i => $p) if ($p[2] > $pos[$big][2]) $big = $i;
            $pos[$big][2] = round($pos[$big][2] + $rem, 2);
        }
    }
    return $pos;
}

/**
 * Make sure one invoice is in QuickBooks; returns its QuickBooks id.
 * Already sent → that id. Already there with the same number (entered by
 * hand, or a send whose reply was lost) → adopted, never duplicated.
 */
function qbo_push_invoice(PDO $pdo, int $factory, array $mapping, array $inv, string $customerId): string
{
    $row = qbo_sync_row($pdo, $factory, 'invoice', (int) $inv['id']);
    if ($row && $row['status'] === 'sent' && !empty($row['external_id'])) return (string) $row['external_id'];

    $doc = mb_substr((string) $inv['inv_number'], 0, 21);
    $found = (array) (qbo_query($factory, "SELECT Id, TotalAmt, CustomerRef FROM Invoice WHERE DocNumber = '" . qbo_q($doc) . "'")['Invoice'] ?? []);
    if ($found) {
        $f = $found[0];
        if ((string) ($f['CustomerRef']['value'] ?? '') !== $customerId) {
            throw new RuntimeException('QuickBooks already has invoice ' . $doc . ' for a different customer.');
        }
        qbo_sync_set($pdo, $factory, 'invoice', (int) $inv['id'], $doc, [
            'status' => 'sent', 'external_id' => (string) $f['Id'], 'last_error' => null, 'sent_at' => date('Y-m-d H:i:s'),
        ]);
        ac_log($factory, 'quickbooks', 'send invoice', 'ok', $doc . ' was already in QuickBooks — linked to it, not sent again.', null, null, $doc);
        return (string) $f['Id'];
    }

    $tax = qbo_tax_code_for_rate($factory, $mapping, (float) $inv['vat_percent']);
    if ($tax === null) {
        throw new QboHeld('No QuickBooks VAT code for ' . rtrim(rtrim(number_format((float) $inv['vat_percent'], 2), '0'), '.') . '% (invoice ' . $doc . ').');
    }
    $lines = [];
    foreach (qbo_invoice_lines($pdo, $inv) as [$desc, $qty, $net]) {
        $lines[] = [
            'DetailType'  => 'SalesItemLineDetail',
            'Amount'      => $net,
            'Description' => mb_substr($desc, 0, 4000),
            'SalesItemLineDetail' => [
                'ItemRef'    => ['value' => (string) $mapping['sales_item_id']],
                'TaxCodeRef' => ['value' => $tax],
                'Qty'        => $qty,
                'UnitPrice'  => round($net / $qty, 4),
            ],
        ];
    }
    $body = [
        'CustomerRef'          => ['value' => $customerId],
        'DocNumber'            => $doc,
        'TxnDate'              => (string) ($inv['issue_date'] ?: date('Y-m-d')),
        'GlobalTaxCalculation' => 'TaxExcluded',
        'Line'                 => $lines,
        // Our VAT figure is the one on the invoice the customer has; QuickBooks
        // would otherwise round per line and could differ by a penny.
        'TxnTaxDetail'         => ['TotalTax' => round((float) $inv['vat'], 2)],
        'PrivateNote'          => 'From YourBlinds',
    ];
    if (!empty($inv['due_date'])) $body['DueDate'] = (string) $inv['due_date'];

    $r = qbo_api_post($factory, '/invoice', $body, 'send invoice');
    $q = $r['data']['Invoice'] ?? null;
    if (!$q || empty($q['Id'])) throw new RuntimeException('QuickBooks did not return the invoice.');
    $note = '';
    $qTotal = round((float) ($q['TotalAmt'] ?? 0), 2);
    if (abs($qTotal - round((float) $inv['total'], 2)) >= 0.01) {
        $note = ' QuickBooks total £' . number_format($qTotal, 2) . ' vs ours £' . number_format((float) $inv['total'], 2) . ' — check the VAT.';
    }
    qbo_sync_set($pdo, $factory, 'invoice', (int) $inv['id'], $doc, [
        'status' => 'sent', 'external_id' => (string) $q['Id'], 'last_error' => $note !== '' ? trim($note) : null,
        'provider_tid' => $r['tid'] ?? null, 'sent_at' => date('Y-m-d H:i:s'),
    ]);
    ac_log($factory, 'quickbooks', 'send invoice', 'ok', $doc . ' sent (£' . number_format($qTotal, 2) . ').' . $note,
           (int) $r['status'], $r['tid'] ?? null, $doc);
    return (string) $q['Id'];
}

/** Read an entity's SyncToken (needed to change or void it). */
function qbo_sync_token(int $factory, string $entity, string $id): string
{
    $r = qbo_request($factory, 'GET', '/' . strtolower($entity) . '/' . rawurlencode($id));
    return (string) ($r['data'][$entity]['SyncToken'] ?? '0');
}

/**
 * Bring one YourBlinds payment into line in QuickBooks.
 * Returns the resulting status: sent | held | skipped | voided | unchanged.
 */
function qbo_push_payment(PDO $pdo, int $factory, array $s, int $payId): string
{
    $mapping = $s['mapping'];
    $p = $pdo->prepare('SELECT * FROM factory_ar_payments WHERE id = ? AND factory_client_id = ?');
    $p->execute([$payId, $factory]);
    $pay = $p->fetch(PDO::FETCH_ASSOC);
    if (!$pay) return 'skipped';
    $ref = (string) $pay['pay_number'];
    $row = qbo_sync_row($pdo, $factory, 'payment', $payId);
    // The live QuickBooks payment, if any (a voided one can't be changed; a new one is sent instead).
    $sentId = ($row && !empty($row['external_id']) && $row['status'] !== 'voided') ? (string) $row['external_id'] : '';

    // Voided in YourBlinds → void it in QuickBooks too (if it ever went).
    if (!empty($pay['voided_at'])) {
        if ($sentId === '') {
            if ($row && $row['status'] !== 'voided') qbo_sync_set($pdo, $factory, 'payment', $payId, $ref, ['status' => 'skipped', 'last_error' => 'Voided before it was sent.']);
            return 'skipped';
        }
        $r = qbo_request($factory, 'POST', '/payment', ['operation' => 'void'],
                         ['Id' => $sentId, 'SyncToken' => qbo_sync_token($factory, 'Payment', $sentId), 'sparse' => true], 'void payment');
        qbo_sync_set($pdo, $factory, 'payment', $payId, $ref, ['status' => 'voided', 'last_error' => null, 'provider_tid' => $r['tid'] ?? null, 'fingerprint' => 'void']);
        ac_log($factory, 'quickbooks', 'void payment', 'ok', $ref . ' voided in QuickBooks (voided in YourBlinds).', (int) $r['status'], $r['tid'] ?? null, $ref);
        return 'voided';
    }

    // What it pays: live allocations to invoices dated on/after the send-from date.
    $a = $pdo->prepare(
        "SELECT i.*, a.amount AS alloc
           FROM factory_ar_payment_allocations a
           JOIN factory_ar_invoices i ON i.id = a.invoice_id
          WHERE a.payment_id = ? AND i.status <> 'void' AND i.issue_date >= ?
          ORDER BY i.issue_date, i.id"
    );
    $a->execute([$payId, $s['from']]);
    $invs = $a->fetchAll(PDO::FETCH_ASSOC);
    $byInv = [];
    foreach ($invs as $i) {
        $k = (int) $i['id'];
        if (!isset($byInv[$k])) { $byInv[$k] = $i; $byInv[$k]['alloc'] = 0.0; }
        $byInv[$k]['alloc'] = round($byInv[$k]['alloc'] + (float) $i['alloc'], 2);
    }
    $amount = round(array_sum(array_column($byInv, 'alloc')), 2);
    $fp = sha1(json_encode([$amount, array_map(static fn ($i) => [(int) $i['id'], $i['alloc']], array_values($byInv))]));

    if ($row && $row['status'] === 'sent' && ($row['fingerprint'] ?? '') === $fp) return 'unchanged';
    if ($amount <= 0.004 && $sentId !== '') {
        // It went across, but now pays nothing (its invoices were voided or it was
        // re-allocated): void it in QuickBooks rather than leave a stray payment.
        $r = qbo_request($factory, 'POST', '/payment', ['operation' => 'void'],
                         ['Id' => $sentId, 'SyncToken' => qbo_sync_token($factory, 'Payment', $sentId), 'sparse' => true], 'void payment');
        qbo_sync_set($pdo, $factory, 'payment', $payId, $ref, ['status' => 'voided', 'fingerprint' => $fp, 'last_error' => 'No longer pays any invoice — voided in QuickBooks.', 'provider_tid' => $r['tid'] ?? null]);
        ac_log($factory, 'quickbooks', 'void payment', 'ok', $ref . ' no longer pays any invoice — voided in QuickBooks.', (int) $r['status'], $r['tid'] ?? null, $ref);
        return 'voided';
    }
    if ($amount <= 0.004) {
        $why = 'Nothing to send: not against any invoice dated from ' . date('j M Y', strtotime($s['from'])) . ' (credit on account, or an earlier invoice).';
        if (!$row || $row['last_error'] !== $why) qbo_sync_set($pdo, $factory, 'payment', $payId, $ref, ['status' => 'skipped', 'last_error' => $why, 'fingerprint' => $fp]);
        return 'skipped';
    }

    try {
        $customerId = qbc_customer_id($factory, (int) $pay['account_client_id']);
        if ($customerId === null) {
            $nm = $pdo->prepare('SELECT company_name FROM clients WHERE id = ?');
            $nm->execute([(int) $pay['account_client_id']]);
            throw new QboHeld('Match "' . (string) $nm->fetchColumn() . '" to its QuickBooks customer first (QuickBooks → Match customers).');
        }
        $cn = $pdo->prepare("SELECT cn_number FROM factory_ar_credit_notes WHERE against_invoice_id = ? AND status <> 'void' LIMIT 1");
        foreach ($byInv as $i) {
            $cn->execute([(int) $i['id']]);
            $c = $cn->fetchColumn();
            if ($c !== false) throw new QboHeld('Invoice ' . $i['inv_number'] . ' has credit note ' . $c . ' — enter this payment in QuickBooks by hand.');
        }

        $lines = [];
        foreach ($byInv as $i) {
            $qid = qbo_push_invoice($pdo, $factory, $mapping, $i, $customerId);
            $lines[] = ['Amount' => $i['alloc'], 'LinkedTxn' => [['TxnId' => $qid, 'TxnType' => 'Invoice']]];
        }
        $body = [
            'CustomerRef'         => ['value' => $customerId],
            'TotalAmt'            => $amount,
            'TxnDate'             => (string) $pay['payment_date'],
            'PaymentRefNum'       => mb_substr($ref, 0, 21),
            'DepositToAccountRef' => ['value' => (string) $mapping['deposit_account_id']],
            'PrivateNote'         => mb_substr('YourBlinds ' . $ref . (!empty($pay['reference']) ? ' · ' . $pay['reference'] : ''), 0, 4000),
            'Line'                => $lines,
        ];
        if ($sentId !== '') {   // already in QuickBooks, but what it pays has changed (credit applied, invoice voided…)
            $body += ['Id' => $sentId, 'SyncToken' => qbo_sync_token($factory, 'Payment', $sentId), 'sparse' => true];
        }
        $r = qbo_api_post($factory, '/payment', $body, $sentId !== '' ? 'update payment' : 'send payment');
        $q = $r['data']['Payment'] ?? null;
        if (!$q || empty($q['Id'])) throw new RuntimeException('QuickBooks did not return the payment.');
        $left = round((float) $pay['amount'] - $amount, 2);
        $note = $left > 0.004 ? '£' . number_format($left, 2) . ' of it stays as credit in YourBlinds (not sent).' : null;
        qbo_sync_set($pdo, $factory, 'payment', $payId, $ref, [
            'status' => 'sent', 'external_id' => (string) $q['Id'], 'fingerprint' => $fp, 'last_error' => $note,
            'provider_tid' => $r['tid'] ?? null, 'sent_at' => date('Y-m-d H:i:s'),
        ]);
        ac_log($factory, 'quickbooks', $sentId !== '' ? 'update payment' : 'send payment', 'ok',
               $ref . ' £' . number_format($amount, 2) . ' against ' . implode(', ', array_column($byInv, 'inv_number')) . '.' . ($note ? ' ' . $note : ''),
               (int) $r['status'], $r['tid'] ?? null, $ref);
        return 'sent';
    } catch (QboHeld $h) {
        if (!$row || $row['status'] !== 'held' || $row['last_error'] !== $h->getMessage()) {
            qbo_sync_set($pdo, $factory, 'payment', $payId, $ref, ['status' => 'held', 'last_error' => $h->getMessage()]);
            ac_log($factory, 'quickbooks', 'send payment', 'held', $h->getMessage(), null, null, $ref);
        }
        return 'held';
    }
}

/**
 * Invoices sent to QuickBooks and since voided in YourBlinds: void them there
 * too (after the payments have been updated so nothing still points at them).
 */
function qbo_push_voided_invoices(PDO $pdo, int $factory): int
{
    $st = $pdo->prepare(
        "SELECT s.local_id, s.external_id, i.inv_number
           FROM accounting_sync s JOIN factory_ar_invoices i ON i.id = s.local_id
          WHERE s.client_id = ? AND s.provider = 'quickbooks' AND s.entity = 'invoice'
            AND s.status = 'sent' AND i.status = 'void'"
    );
    $st->execute([$factory]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $id = (string) $v['external_id'];
        $r = qbo_request($factory, 'POST', '/invoice', ['operation' => 'void'],
                         ['Id' => $id, 'SyncToken' => qbo_sync_token($factory, 'Invoice', $id)], 'void invoice');
        qbo_sync_set($pdo, $factory, 'invoice', (int) $v['local_id'], (string) $v['inv_number'], ['status' => 'voided', 'last_error' => null, 'provider_tid' => $r['tid'] ?? null]);
        ac_log($factory, 'quickbooks', 'void invoice', 'ok', $v['inv_number'] . ' voided in QuickBooks (voided in YourBlinds).', (int) $r['status'], $r['tid'] ?? null, (string) $v['inv_number']);
        $n++;
    }
    return $n;
}

/**
 * The run: every payment that may need sending, changing or voiding.
 * One run at a time per company (MySQL named lock), so the page and the cron
 * can never both send the same payment.
 *
 * @return array{ran:bool, sent:int, held:int, failed:int, skipped:int, voided:int, message:string}
 */
function qbo_push_run(int $factory, int $limit = 200): array
{
    $res = ['ran' => false, 'sent' => 0, 'held' => 0, 'failed' => 0, 'skipped' => 0, 'voided' => 0, 'message' => ''];
    $s = qbo_push_settings($factory);
    if (!$s['on']) { $res['message'] = 'Sending is switched off.'; return $res; }
    if (($b = qbo_push_blocker($s)) !== '') { $res['message'] = $b; return $res; }

    $pdo = db();
    qbo_push_ensure_schema($pdo);
    $lockName = 'yb_qbo_push_' . $factory;
    if ((int) $pdo->query("SELECT GET_LOCK(" . $pdo->quote($lockName) . ", 0)")->fetchColumn() !== 1) {
        $res['message'] = 'Already sending — try again in a moment.';
        return $res;
    }
    try {
        $res['ran'] = true;
        // Live payments since the send-from date, plus any voided one that did go across.
        $st = $pdo->prepare(
            "SELECT p.id FROM factory_ar_payments p
               LEFT JOIN accounting_sync s ON s.client_id = p.factory_client_id AND s.provider = 'quickbooks'
                                          AND s.entity = 'payment' AND s.local_id = p.id
              WHERE p.factory_client_id = ?
                AND ( (p.voided_at IS NULL AND p.payment_date >= ?)
                   OR (p.voided_at IS NOT NULL AND s.status IN ('sent','failed','held') AND s.external_id IS NOT NULL) )
              ORDER BY p.payment_date, p.id
              LIMIT " . (int) $limit
        );
        $st->execute([$factory, $s['from']]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $payId) {
            try {
                $out = qbo_push_payment($pdo, $factory, $s, (int) $payId);
                if (isset($res[$out])) $res[$out]++;
            } catch (Throwable $e) {
                $res['failed']++;
                $p = $pdo->prepare('SELECT pay_number FROM factory_ar_payments WHERE id = ?');
                $p->execute([(int) $payId]);
                qbo_sync_set($pdo, $factory, 'payment', (int) $payId, (string) $p->fetchColumn(), ['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000)]);
                // Not connected / needs reconnecting: every other payment would fail the same way.
                if (ac_needs_reconnect(ac_get_connection($factory, 'quickbooks'))) break;
            }
        }
        try { $res['voided'] += qbo_push_voided_invoices($pdo, $factory); }
        catch (Throwable $e) { $res['failed']++; }
        $res['message'] = sprintf('%d sent, %d held, %d failed.', $res['sent'], $res['held'], $res['failed']);
        return $res;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($lockName) . ")");
    }
}

/**
 * Best-effort run straight after a payment is saved / voided / credit applied.
 * Never throws and never blocks the page for long: if QuickBooks is slow or
 * down, the 15-minute run picks it up.
 */
function qbo_push_kick(int $factory): void
{
    try {
        $s = qbo_push_settings($factory);
        if (!$s['on'] || qbo_push_blocker($s) !== '') return;
        qbo_push_run($factory, 20);
    } catch (Throwable $e) {
        error_log('qbo_push_kick: ' . $e->getMessage());
    }
}
