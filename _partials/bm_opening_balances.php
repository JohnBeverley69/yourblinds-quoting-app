<?php
declare(strict_types=1);

/**
 * Swap-over from Blind Matrix: bring each trade account's UNPAID Blind Matrix
 * invoices into YourBlinds as "brought forward" invoices, from Blind Matrix's
 * own Statements export (Excel). After that, statements, balances, the Bank
 * screen and Record payment all carry straight on in YourBlinds: a customer
 * paying an old Blind Matrix invoice in January is matched like any other.
 *
 * The export (Blind Matrix → Statements, "open items"): one sheet of repeating
 * blocks, one per account —
 *     Acc. Ref     <company name>            (it's the NAME, not a code)
 *     Invoice Date | Invoice No | Ref. Number | Cust. Reference | Invoice Amount | Paid | Outstanding Amount
 *     09-10-2026   | 60127      | ON068440    | Ve fort acl sp  | £ 41.57        | £ 0.00 | £ 41.57
 *     … Total, then month buckets.
 * Columns are found by their headings, not position, so a slightly different
 * layout still reads.
 *
 * A brought-forward invoice keeps the Blind Matrix invoice number (customers
 * quote it when they pay), its date and their reference, and is for the amount
 * STILL OWED (a part-paid one comes in for the balance). VAT is split at 20%
 * from the gross (trade sales are standard-rated); it isn't re-sent to
 * QuickBooks automatically because it's dated before the "send from" date.
 *
 * SAFETY: reading + previewing never writes anything. Creating the invoices is
 * refused unless platform_config BM_IMPORT_ON = '1' (off until the swap-over),
 * and is all-or-nothing in one transaction; an invoice number that already
 * exists is skipped, so a second run can't double anything.
 */

require_once __DIR__ . '/factory_ar.php';
require_once __DIR__ . '/qbo_customers.php';   // qbc_norm / qbc_words_within / qbc_near_spelling

/** £ 1,234.56 / 1234.56 / (12.00) → float */
function bmo_money($v): float
{
    if (is_int($v) || is_float($v)) return round((float) $v, 2);
    $s = trim((string) $v);
    $neg = str_starts_with($s, '(') || str_contains($s, '-');
    $n = (float) preg_replace('/[^0-9.]/', '', $s);
    return round($neg ? -$n : $n, 2);
}

/** dd-mm-yyyy / dd/mm/yyyy / Excel serial → Y-m-d, or '' */
function bmo_date($v): string
{
    if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
    }
    $s = trim((string) $v);
    if (preg_match('#^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$#', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    return '';
}

/**
 * Read a Blind Matrix statements export.
 * @return array{from:string, to:string, accounts:array<int, array{name:string, invoices:array, total:float}>, warnings:string[]}
 */
function bmo_parse(string $path): array
{
    if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) require_once dirname(__DIR__) . '/vendor/autoload.php';
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $out = ['from' => '', 'to' => '', 'accounts' => [], 'warnings' => []];
    $cur = null; $cols = null;
    foreach ($reader->load($path)->getWorksheetIterator() as $ws) {
        foreach ($ws->toArray(null, true, false, false) as $rowNo => $r) {
            $c = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $r);
            $txt = array_map(static fn ($v) => strtolower(trim((string) $v)), $c);
            $lastVal = static function () use ($c) { foreach (array_reverse($c) as $v) if ($v !== null && trim((string) $v) !== '') return $v; return ''; };

            if (in_array('from date', $txt, true)) { $out['from'] = $out['from'] ?: bmo_date($lastVal()); continue; }
            if (in_array('to date', $txt, true))   { $out['to']   = $out['to']   ?: bmo_date($lastVal()); continue; }
            // "To" then the address block: its first line is the company — used when
            // an account has no Acc. Ref filled in (seen on old accounts).
            if ($txt === array_fill(0, count($txt), '') ) continue;
            $nonEmpty = array_values(array_filter($txt, static fn ($t) => $t !== ''));
            if ($nonEmpty === ['to']) { $wantTo = true; continue; }
            if (!empty($wantTo)) {
                $first = (string) array_values(array_filter($c, static fn ($v) => trim((string) $v) !== ''))[0];
                $toName = trim((string) strtok(str_replace("\r", '', $first), "\n"));
                $wantTo = false;
            }
            if (in_array('acc. ref', $txt, true) || in_array('acc ref', $txt, true)) {
                $name = trim((string) $lastVal());
                if ($name === '' || in_array(strtolower($name), ['acc. ref', 'acc ref'], true)) {
                    $name = ucwords(strtolower((string) ($toName ?? '')));
                    $out['warnings'][] = 'An account had no Acc. Ref in Blind Matrix — used the name on its address: "' . $name . '".';
                }
                $toName = null;
                $out['accounts'][] = ['name' => $name, 'invoices' => [], 'total' => 0.0];
                $cur = count($out['accounts']) - 1; $cols = null;
                continue;
            }
            if (in_array('invoice no', $txt, true)) {   // the heading row: find each column
                $find = static function (array $names) use ($txt) {
                    foreach ($txt as $i => $t) if (in_array($t, $names, true)) return $i;
                    return null;
                };
                $cols = [
                    'date' => $find(['invoice date']), 'no' => $find(['invoice no']), 'order' => $find(['ref. number', 'ref number']),
                    'cref' => $find(['cust. reference', 'cust reference']), 'amount' => $find(['invoice amount']),
                    'paid' => $find(['paid']), 'out' => $find(['outstanding amount', 'outstanding']),
                ];
                continue;
            }
            if ($cur === null || $cols === null || $cols['no'] === null || $cols['out'] === null) continue;
            $no = trim((string) ($c[$cols['no']] ?? ''));
            $date = bmo_date($c[$cols['date']] ?? '');
            if ($no === '' || $date === '') continue;   // totals, month buckets, blank rows

            // Money cells can sit a column or two right of their heading in this export:
            // take the first non-empty value from the heading's column up to the next heading.
            $grab = static function (string $k) use ($c, $cols) {
                $start = $cols[$k]; if ($start === null) return null;
                $next = PHP_INT_MAX;
                foreach ($cols as $v) if ($v !== null && $v > $start && $v < $next) $next = $v;
                for ($i = $start; $i < min($next, count($c)); $i++) if ($c[$i] !== null && trim((string) $c[$i]) !== '') return $c[$i];
                return null;
            };
            $inv = [
                'no'     => $no,
                'date'   => $date,
                'order'  => trim((string) ($grab('order') ?? '')),
                'cref'   => trim((string) ($grab('cref') ?? '')),
                'amount' => bmo_money($grab('amount') ?? 0),
                'paid'   => bmo_money($grab('paid') ?? 0),
                'out'    => bmo_money($grab('out') ?? 0),
            ];
            if (abs($inv['amount'] - $inv['paid'] - $inv['out']) > 0.011) {
                $out['warnings'][] = 'Row ' . ($rowNo + 1) . ': invoice ' . $no . ' — amount − paid ≠ outstanding; using the outstanding figure.';
            }
            if ($inv['out'] <= 0.004) continue;          // nothing owed on it
            $out['accounts'][$cur]['invoices'][] = $inv;
            $out['accounts'][$cur]['total'] = round($out['accounts'][$cur]['total'] + $inv['out'], 2);
        }
    }
    $out['accounts'] = array_values(array_filter($out['accounts'], static fn ($a) => $a['invoices']));
    return $out;
}

/** Trade accounts (everyone but the factory), for matching. */
function bmo_trade_accounts(PDO $pdo, int $factory): array
{
    $st = $pdo->prepare('SELECT id, company_name, account_ref, active FROM clients WHERE id <> ? ORDER BY company_name');
    $st->execute([$factory]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** The YourBlinds account a Blind Matrix name is, if it's clear. @return array{id:int, strength:string}|null */
function bmo_match_account(string $bmName, array $accounts): ?array
{
    $mine = qbc_norm($bmName);
    if ($mine === '') return null;
    $exact = []; $close = [];
    foreach ($accounts as $a) {
        foreach ([(string) $a['company_name'], (string) ($a['account_ref'] ?? '')] as $raw) {
            $t = qbc_norm($raw);
            if ($t === '') continue;
            if ($t === $mine) { $exact[(int) $a['id']] = $a; continue 2; }
            if (preg_replace('/\D+/', '', $t) !== preg_replace('/\D+/', '', $mine)) continue;
            if ((substr_count($mine, ' ') >= 1 && substr_count($t, ' ') >= 1 && (qbc_words_within($mine, $t) || qbc_words_within($t, $mine)))
                || qbc_near_spelling($mine, $t)) {
                $close[(int) $a['id']] = $a;
            }
        }
    }
    // Prefer an active account when two share a name.
    $pick = static function (array $set) {
        if (count($set) === 1) return (int) array_key_first($set);
        $act = array_filter($set, static fn ($a) => (int) $a['active'] === 1);
        return count($act) === 1 ? (int) array_key_first($act) : 0;
    };
    if ($exact && ($id = $pick($exact))) return ['id' => $id, 'strength' => 'exact'];
    if (!$exact && $close && ($id = $pick($close))) return ['id' => $id, 'strength' => 'likely'];
    return null;
}

/** Invoice numbers already in YourBlinds (so they're skipped, never doubled). */
function bmo_existing_numbers(PDO $pdo, int $factory): array
{
    $st = $pdo->prepare('SELECT inv_number FROM factory_ar_invoices WHERE factory_client_id = ?');
    $st->execute([$factory]);
    return array_flip(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN)));
}

function bmo_import_enabled(): bool
{
    return (string) (pc_get('BM_IMPORT_ON', '') ?? '') === '1';
}

/**
 * Create the brought-forward invoices. $plan = [[account_id, invoices[]], …].
 * All-or-nothing. Refused unless switched on. Returns counts.
 */
function bmo_import(PDO $pdo, int $factory, array $plan, int $userId): array
{
    if (!bmo_import_enabled()) throw new RuntimeException('Importing is switched off (test mode) — nothing was saved.');
    $existing = bmo_existing_numbers($pdo, $factory);
    $made = 0; $skipped = 0; $total = 0.0;
    $pdo->beginTransaction();
    try {
        $insI = $pdo->prepare(
            "INSERT INTO factory_ar_invoices
               (factory_client_id, account_client_id, inv_number, status, issue_date, due_date, vat_percent,
                subtotal, vat, total, amount_paid, notes, sent_at, created_by)
             VALUES (?, ?, ?, 'sent', ?, ?, 20.00, ?, ?, ?, 0, ?, ?, ?)"
        );
        $insL = $pdo->prepare(
            "INSERT INTO factory_ar_invoice_lines (invoice_id, line_type, description, quantity, unit_net, line_net, sort_order)
             VALUES (?, 'adjust', ?, 1, ?, ?, 0)"
        );
        foreach ($plan as [$accountId, $invoices]) {
            foreach ($invoices as $inv) {
                $no = mb_substr((string) $inv['no'], 0, 32);
                if (isset($existing[$no])) { $skipped++; continue; }
                $gross = round((float) $inv['out'], 2);
                $net   = round($gross / 1.2, 2);
                $vat   = round($gross - $net, 2);
                $desc  = 'Brought forward from Blind Matrix — invoice ' . $no
                       . ($inv['order'] !== '' ? ', order ' . $inv['order'] : '')
                       . ($inv['cref'] !== '' ? ', your ref ' . $inv['cref'] : '')
                       . ($inv['paid'] > 0.004 ? sprintf(' (balance of £%s; £%s already paid)', number_format($inv['amount'], 2), number_format($inv['paid'], 2)) : '');
                $insI->execute([$factory, (int) $accountId, $no, $inv['date'], date('Y-m-d', strtotime($inv['date'] . ' +30 days')),
                                $net, $vat, $gross, 'Brought forward from Blind Matrix', $inv['date'] . ' 00:00:00', $userId ?: null]);
                $insL->execute([(int) $pdo->lastInsertId(), mb_substr($desc, 0, 255), $net, $net]);
                $existing[$no] = true; $made++; $total += $gross;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['made' => $made, 'skipped' => $skipped, 'total' => round($total, 2)];
}
