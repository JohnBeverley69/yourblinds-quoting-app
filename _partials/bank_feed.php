<?php
declare(strict_types=1);

/**
 * Factory bank feed — Beverley's own Barclays account, read through Lunch Flow
 * (lunchflow.app Personal API, Open Banking via GoCardless, read-only). Factory
 * only: bank lines land in factory_bank_transactions, money-in lines are matched
 * to trade-account invoices and recorded as factory_ar_payments.
 *
 * All Lunch Flow specifics live in the bf_lf_* functions, so a different
 * provider later only replaces those. Config (platform_config):
 *   LUNCHFLOW_API_KEY     sealed with ac_seal() (re-sealed by rotate_encryption_key.php)
 *   LUNCHFLOW_ACCOUNT_ID  which Lunch Flow account is the Barclays business account
 *   BANK_LAST_SYNC        unix time of the last successful fetch
 */

require_once __DIR__ . '/accounting.php';    // pc_get/pc_set, ac_seal/ac_open, ac_http
require_once __DIR__ . '/factory_ar.php';

const BF_LF_BASE = 'https://www.lunchflow.app/api/v1';

function bf_ready(PDO $pdo): bool
{
    return ar_table_ready($pdo, 'factory_bank_transactions');
}

function bf_api_key(): string
{
    return (string) (ac_open(pc_get('LUNCHFLOW_API_KEY', '')) ?? '');
}

/* ── Lunch Flow ─────────────────────────────────────────────────────────── */

function bf_lf_get(string $path, array $query = []): array
{
    $key = bf_api_key();
    if ($key === '') throw new RuntimeException('No Lunch Flow API key saved yet.');
    $url = BF_LF_BASE . $path . ($query ? '?' . http_build_query($query) : '');
    $r = ac_http('GET', $url, ['headers' => ['x-api-key: ' . $key, 'Accept: application/json']]);
    if ($r['status'] === 401 || $r['status'] === 403) {
        throw new RuntimeException('Lunch Flow refused the API key (HTTP ' . $r['status'] . ') — check it in the Connection panel.');
    }
    if ($r['status'] < 200 || $r['status'] >= 300) {
        $msg = (string) ($r['data']['message'] ?? $r['data']['error'] ?? substr($r['raw'], 0, 200));
        throw new RuntimeException('Lunch Flow error (HTTP ' . $r['status'] . '): ' . $msg);
    }
    // What came back, for the Connection panel when a list is unexpectedly
    // empty — shape only (status, top-level keys, size), never the key.
    $GLOBALS['bf_last_reply'] = 'HTTP ' . $r['status'] . ', ' . strlen($r['raw']) . ' bytes, '
        . ($r['data'] ? 'fields: ' . implode(', ', array_slice(array_map('strval', array_keys($r['data'])), 0, 8))
                      : 'not JSON (starts "' . substr(preg_replace('/\s+/', ' ', $r['raw']) ?? '', 0, 40) . '")');
    return $r['data'];
}

/** Connected bank accounts: [{id, name, institution_name, currency, status}]. */
function bf_lf_accounts(): array
{
    $d = bf_lf_get('/accounts');
    // Documented as {accounts:[…]}; accept a bare list or {data:[…]} too.
    $list = $d['accounts'] ?? $d['data'] ?? (array_is_list($d) ? $d : []);
    return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
}

/** Settled transactions for one account between two dates (inclusive). */
function bf_lf_transactions(string $accountId, string $from, string $to): array
{
    $d = bf_lf_get('/accounts/' . rawurlencode($accountId) . '/transactions',
                   ['from' => $from, 'to' => $to, 'include_pending' => 'false']);
    $list = $d['transactions'] ?? $d['data'] ?? (array_is_list($d) ? $d : []);
    return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
}

/* ── Sync ───────────────────────────────────────────────────────────────── */

/**
 * The account to read: the saved one, or the only connected one. '' if it
 * can't be decided (several accounts, none chosen).
 */
function bf_account_id(): string
{
    $id = (string) (pc_get('LUNCHFLOW_ACCOUNT_ID', '') ?? '');
    if ($id !== '') return $id;
    $accs = bf_lf_accounts();
    return count($accs) === 1 ? (string) $accs[0]['id'] : '';
}

/**
 * Pull new bank lines into factory_bank_transactions. First run goes back 90
 * days; later runs re-read from a week before the newest stored line (a bank
 * can post a line a few days late). Existing rows keep their match status.
 * Returns the number of NEW lines stored.
 */
function bf_sync(PDO $pdo, int $factory): int
{
    $accountId = bf_account_id();
    if ($accountId === '') throw new RuntimeException('Choose which bank account to read in the Connection panel.');

    $last = $pdo->prepare("SELECT MAX(txn_date) FROM factory_bank_transactions WHERE factory_client_id = ? AND provider = 'lunchflow'");
    $last->execute([$factory]);
    $newest = (string) ($last->fetchColumn() ?: '');
    $from = $newest !== ''
        ? date('Y-m-d', strtotime($newest . ' -7 days'))
        : date('Y-m-d', strtotime('-90 days'));

    $ins = $pdo->prepare(
        "INSERT IGNORE INTO factory_bank_transactions
           (factory_client_id, provider, provider_txn_id, bank_account_id, txn_date, amount, currency, description, merchant)
         VALUES (?, 'lunchflow', ?, ?, ?, ?, ?, ?, ?)"
    );
    $added = 0;
    foreach (bf_lf_transactions($accountId, $from, date('Y-m-d')) as $t) {
        $tid  = trim((string) ($t['id'] ?? ''));
        $date = (string) ($t['date'] ?? '');
        if ($tid === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $date) || !empty($t['isPending'])) continue;
        $ins->execute([
            $factory, substr($tid, 0, 191), $accountId, substr($date, 0, 10),
            round((float) ($t['amount'] ?? 0), 2),
            ($t['currency'] ?? null) !== null ? substr((string) $t['currency'], 0, 8) : null,
            ($t['description'] ?? '') !== '' ? mb_substr((string) $t['description'], 0, 255) : null,
            ($t['merchant'] ?? '') !== '' ? mb_substr((string) $t['merchant'], 0, 255) : null,
        ]);
        $added += $ins->rowCount();
    }
    pc_set('BANK_LAST_SYNC', (string) time());
    return $added;
}

/* ── Matching ───────────────────────────────────────────────────────────── */

/** Upper-case letters+digits only — bank refs lose spaces/dashes and get cut short. */
function bf_norm(string $s): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($s)) ?? '';
}

/** A company name reduced to what a payer reference would carry (no LTD etc). */
function bf_name_key(string $name): string
{
    $n = strtoupper($name);
    $n = preg_replace('/\b(LTD|LIMITED|PLC|LLP|CO|COMPANY|THE|AND|&|T\/A|UK)\b\.?/', ' ', $n) ?? $n;
    return bf_norm($n);
}

/**
 * Everything the matcher needs, read once per page: every trade account's open
 * invoices (oldest first, balance > 0) plus the account's name + Acc Ref.
 * Returns [accountId => ['id','name','ref','invoices'=>[…]]].
 */
function bf_open_book(PDO $pdo, int $factory): array
{
    if (!ar_table_ready($pdo, 'factory_ar_invoices')) return [];
    $credSql = ar_table_ready($pdo, 'factory_ar_credit_notes')
        ? "COALESCE((SELECT SUM(cn.total) FROM factory_ar_credit_notes cn
                      WHERE cn.against_invoice_id = i.id AND cn.status <> 'void'), 0)"
        : '0';
    $refSql = '';
    try { $pdo->query('SELECT account_ref FROM clients LIMIT 0'); $refSql = ', c.account_ref'; } catch (Throwable $e) {}
    $st = $pdo->prepare(
        "SELECT i.id, i.account_client_id, i.inv_number, i.issue_date, i.total, i.amount_paid,
                $credSql AS credited, c.company_name $refSql
           FROM factory_ar_invoices i
           JOIN clients c ON c.id = i.account_client_id
          WHERE i.factory_client_id = ? AND i.status <> 'void'
          ORDER BY (i.issue_date IS NULL), i.issue_date, i.id"
    );
    $st->execute([$factory]);
    $book = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bal = round((float) $r['total'] - (float) $r['amount_paid'] - (float) $r['credited'], 2);
        if ($bal <= 0.004) continue;
        $aid = (int) $r['account_client_id'];
        $book[$aid] ??= ['id' => $aid, 'name' => (string) $r['company_name'],
                         'ref' => (string) ($r['account_ref'] ?? ''), 'invoices' => []];
        $book[$aid]['invoices'][] = ['id' => (int) $r['id'], 'inv_number' => (string) $r['inv_number'],
                                     'issue_date' => $r['issue_date'], 'balance' => $bal];
    }
    return $book;
}

/**
 * Suggest who a money-in line is from and which invoices it pays.
 * Clues, strongest first:
 *   1. an open invoice number (or its order-number part) in the bank reference
 *   2. the account's Acc Ref or company name in the payer text
 *   3. the amount equals exactly one open invoice balance anywhere (weak)
 * Returns null, or ['account_id','account_name','alloc'=>[invId=>amt],'invoices'=>[inv_number…],
 *                   'why'=>string,'strong'=>bool,'exact'=>bool].
 */
function bf_suggest(array $book, array $txn): ?array
{
    $amount = round((float) $txn['amount'], 2);
    if ($amount <= 0) return null;
    $text = bf_norm(($txn['description'] ?? '') . ' ' . ($txn['merchant'] ?? ''));
    if ($text === '') $text = '-';

    $accountId = null; $hitInv = []; $why = '';

    // 1. Invoice / order number in the reference.
    foreach ($book as $aid => $acc) {
        foreach ($acc['invoices'] as $inv) {
            $full  = bf_norm($inv['inv_number']);
            $order = preg_replace('/^INV/', '', $full) ?? $full;       // the order number part
            if ((strlen($full) >= 6 && str_contains($text, $full))
                || (strlen($order) >= 6 && str_contains($text, $order))) {
                $hitInv[$aid][] = $inv['id'];
            }
        }
    }
    if (count($hitInv) === 1) {
        $accountId = (int) array_key_first($hitInv);
        $why = 'invoice number in the bank reference';
    }

    // 2. Acc Ref or company name.
    if ($accountId === null) {
        $found = [];
        foreach ($book as $aid => $acc) {
            $ref  = bf_norm($acc['ref']);
            $name = bf_name_key($acc['name']);
            if ((strlen($ref) >= 4 && str_contains($text, $ref))
                || (strlen($name) >= 5 && str_contains($text, $name))) {
                $found[] = $aid;
            }
        }
        if (count($found) === 1) {
            $accountId = (int) $found[0];
            $why = 'account name / Acc Ref in the payer details';
        }
    }

    // 3. Amount equals exactly one open invoice.
    $strong = $accountId !== null;
    if ($accountId === null) {
        $same = [];
        foreach ($book as $aid => $acc) {
            foreach ($acc['invoices'] as $inv) {
                if (abs($inv['balance'] - $amount) < 0.005) $same[] = [$aid, $inv['id']];
            }
        }
        if (count($same) !== 1) return null;
        $accountId = (int) $same[0][0];
        $hitInv = [$accountId => [$same[0][1]]];
        $why = 'amount matches one open invoice (no name or reference match — check it)';
    }

    // Allocate: referenced invoices first, then oldest-first, never past the amount.
    $acc = $book[$accountId];
    $order = [];
    foreach ($acc['invoices'] as $inv) if (in_array($inv['id'], $hitInv[$accountId] ?? [], true)) $order[] = $inv;
    foreach ($acc['invoices'] as $inv) if (!in_array($inv['id'], $hitInv[$accountId] ?? [], true)) $order[] = $inv;
    $left = $amount; $alloc = []; $nums = [];
    foreach ($order as $inv) {
        if ($left <= 0.004) break;
        $give = round(min($inv['balance'], $left), 2);
        $alloc[$inv['id']] = $give; $nums[] = $inv['inv_number'];
        $left = round($left - $give, 2);
    }
    return [
        'account_id'   => $accountId,
        'account_name' => $acc['name'],
        'alloc'        => $alloc,
        'invoices'     => $nums,
        'unallocated'  => max(0.0, $left),
        'why'          => $why,
        'strong'       => $strong,
        'exact'        => $left <= 0.004,
    ];
}

/** Link a bank line to a recorded payment. */
function bf_mark_matched(PDO $pdo, int $factory, int $txnId, int $paymentId, int $userId): void
{
    $pdo->prepare(
        "UPDATE factory_bank_transactions
            SET status = 'matched', payment_id = ?, actioned_by = ?, actioned_at = NOW()
          WHERE id = ? AND factory_client_id = ?"
    )->execute([$paymentId, $userId ?: null, $txnId, $factory]);
}

/** One bank line (factory-scoped) or null. */
function bf_txn(PDO $pdo, int $factory, int $txnId): ?array
{
    $st = $pdo->prepare("SELECT * FROM factory_bank_transactions WHERE id = ? AND factory_client_id = ? LIMIT 1");
    $st->execute([$txnId, $factory]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** The bank reference as a payment reference (fits factory_ar_payments.reference). */
function bf_payment_ref(array $txn): string
{
    $r = trim((string) ($txn['description'] ?? '') ?: (string) ($txn['merchant'] ?? ''));
    return mb_substr($r, 0, 120);
}
