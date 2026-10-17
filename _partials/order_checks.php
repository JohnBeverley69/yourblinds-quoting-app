<?php
declare(strict_types=1);

/**
 * Order checks — the daily "doesn't add up" report.
 *
 * An order's state lives in several places (quotes.status, quotes.fulfilment_stage,
 * factory_jobs, the floor's blind jobs, supplier_orders, delivery notes,
 * invoices). Each screen trusts one of them, so when two disagree nobody sees
 * it until a customer rings. This reads them side by side for one factory and
 * lists every order whose trackers contradict each other, with what to do.
 *
 * READ-ONLY. Scoped to the factory passed in (current_factory_id() on the page,
 * the main factory in the cron) — an order counts when it is placed and carries
 * at least one line that factory owns. Bounded to orders placed in the last
 * OC_DAYS days so the run stays cheap.
 *
 * Shown on factory/checks.php (super-admin, Factory Console) and mailed once a
 * day from cron_messages.php, only when something is wrong.
 */

require_once __DIR__ . '/order_stage.php';
require_once __DIR__ . '/order_tracks.php';

const OC_DAYS = 365;

/** Check key => [title, what to do]. Order = how they are listed. */
function oc_checks(): array
{
    return [
        'dispatched_no_dn'    => ['Dispatched, but no dispatched delivery note',
                                  'Retry under "Paperwork missing" on the Pipeline — it makes the note.'],
        'dn_floor_not'        => ['Delivery note dispatched, but the order isn\'t',
                                  'If it has gone, Dispatch it on the Pipeline; if not, cancel the note on Invoices.'],
        'invoice_not_out'     => ['Invoiced, but not dispatched',
                                  'Fine if billed up front on purpose; otherwise dispatch it, or void the invoice.'],
        'invoiced_no_invoice' => ['Marked Invoiced/Paid, but there is no invoice',
                                  'Raise the invoice on Invoices, or put the order back to Ordered.'],
        'production_no_floor' => ['In production, but nothing on the floor',
                                  'Open it on the Pipeline and put it on the floor (Accept / Process order).'],
        'ready_no_floor'      => ['Ready, but the blinds were never on the floor',
                                  'It was marked made by hand. Check the blinds really are made before it goes.'],
        'bi_received_unordered' => ['Bought-in marked received, but not everything was ordered',
                                  'An item was added after the PO went. Order it from the supplier on the Pipeline.'],
        'supplier_failed'     => ['Supplier order didn\'t go',
                                  'Fix the supplier email in Settings › Suppliers, then send it again on the Pipeline.'],
        'stage_stale'         => ['Stage shown is out of date',
                                  'Press "Bring stages up to date" on this page.'],
    ];
}

/**
 * Every contradiction for one factory. Rows:
 *   ['check', 'quote_id', 'quote_number', 'account', 'detail'].
 */
function oc_findings(PDO $pdo, int $factory): array
{
    if ($factory <= 0) return [];
    $in = "'" . implode("','", os_placed_statuses()) . "'";

    $has = static function (string $table) use ($pdo): bool {
        try {
            $s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
            $s->execute([$table]);
            return $s->fetchColumn() !== false;
        } catch (Throwable $e) { return false; }
    };
    $dnOn  = $has('factory_ar_delivery_notes');
    $invOn = $has('factory_ar_invoices') && $has('factory_ar_invoice_orders');
    $fjOn  = $has('factory_jobs');
    $f     = (int) $factory;
    require_once __DIR__ . '/factory_ar.php';
    $acctX = ar_account_expr($pdo, 'q.');   // who it is billed to (a white-label factory's order: that factory)

    $dnDisp = $dnOn  ? "(SELECT COUNT(*) FROM factory_ar_delivery_notes d WHERE d.source_quote_id = q.id AND d.factory_client_id = $f AND d.status = 'dispatched')" : '0';
    $invNum = $invOn ? "(SELECT i.inv_number FROM factory_ar_invoice_orders io JOIN factory_ar_invoices i ON i.id = io.invoice_id
                          WHERE io.quote_id = q.id AND i.factory_client_id = $f AND i.status <> 'void' LIMIT 1)" : 'NULL';
    $fjSel  = $fjOn  ? "(SELECT fj.status FROM factory_jobs fj WHERE fj.quote_id = q.id AND fj.factory_client_id = $f LIMIT 1)" : 'NULL';
    // This factory's own part's stage (a mixed order's other factory has its own).
    $fsSel  = $has('order_factory_parts')
        ? "COALESCE((SELECT o.fulfilment_stage FROM order_factory_parts o WHERE o.quote_id = q.id AND o.factory_client_id = $f), q.fulfilment_stage)"
        : 'q.fulfilment_stage';

    try {
        $st = $pdo->prepare(
            "SELECT q.id, q.quote_number, q.status, $fsSel AS fulfilment_stage, q.client_id,
                    $acctX AS account_id,
                    q.supplier_ordered_at, q.supplier_received_at,
                    c.company_name AS account,
                    $dnDisp AS dn_dispatched, $invNum AS inv_number, $fjSel AS fj
               FROM quotes q
               LEFT JOIN clients c ON c.id = $acctX
              WHERE q.status IN ($in)
                AND q.created_at >= DATE_SUB(NOW(), INTERVAL " . OC_DAYS . " DAY)
                AND EXISTS (SELECT 1 FROM quote_items qi JOIN products p ON p.id = qi.product_id
                             WHERE qi.quote_id = q.id AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?)
           ORDER BY q.created_at DESC
              LIMIT 2000"
        );
        $st->execute([$factory]);
        $orders = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Pre-migration schema (no account_client_id / supplier stamps): nothing to compare.
        error_log('oc_findings: ' . $e->getMessage());
        return [];
    }
    if (!$orders) return [];

    $ids    = array_map(static fn ($o) => (int) $o['id'], $orders);
    $floor  = bj_tables_ready($pdo) ? bj_order_progress($pdo, $ids, $factory) : [];
    // Bought-in tracks only for orders not yet out — a failed PO after dispatch is history.
    $notOut = [];
    foreach ($orders as $o) if ((string) $o['fj'] !== 'dispatched' && (int) $o['dn_dispatched'] === 0) $notOut[] = (int) $o['id'];
    $tracks = $notOut ? ot_order_tracks($pdo, $notOut, $factory) : [];

    $out = [];
    $add = static function (string $check, array $o, string $detail = '') use (&$out): void {
        $out[] = ['check' => $check, 'quote_id' => (int) $o['id'], 'quote_number' => (string) $o['quote_number'],
                  'account' => (string) ($o['account'] ?? ''), 'detail' => $detail];
    };

    foreach ($orders as $o) {
        $id      = (int) $o['id'];
        $fj      = (string) ($o['fj'] ?? '');
        $stage   = (string) ($o['fulfilment_stage'] ?? '');
        $dnOut   = (int) $o['dn_dispatched'] > 0;
        $inv     = (string) ($o['inv_number'] ?? '');
        $out_    = $fj === 'dispatched' || $dnOut;
        $trade   = (int) $o['account_id'] !== $f;              // billed through the factory's accounts

        if ($dnOn && $fj === 'dispatched' && !$dnOut) {
            $add('dispatched_no_dn', $o);
        }
        if ($dnOut && $fjOn && $fj !== 'dispatched') {
            $add('dn_floor_not', $o, 'Floor says: ' . ($fj !== '' ? str_replace('_', ' ', $fj) : 'new'));
        }
        if ($inv !== '' && !$out_) {
            $add('invoice_not_out', $o, $inv);
        }
        // Only an order the factory raised for a trade account is billed by a
        // factory invoice AND moved by its own status menu. A trade customer's
        // own portal order is "invoiced" by THEM to their customer — not ours.
        if ($invOn && $trade && (int) $o['client_id'] === $f && $inv === ''
            && in_array((string) $o['status'], ['invoiced', 'paid'], true)) {
            $add('invoiced_no_invoice', $o, 'Order status: ' . $o['status']);
        }

        $prog    = $floor[$id] ?? ['total' => 0, 'done' => 0];
        $inhouse = bj_tables_ready($pdo) ? os_inhouse_line_count($pdo, $id, $f) : 0;
        if ($inhouse > 0 && (int) $prog['total'] === 0 && !$out_) {
            if ($fj === 'in_production') $add('production_no_floor', $o);
            elseif ($stage === 'ready')  $add('ready_no_floor', $o);
        }

        $t = $tracks[$id] ?? null;
        if ($t) {
            if ((string) ($o['supplier_received_at'] ?? '') !== '') {
                $missing = array_keys(array_filter($t['buy'], static fn ($b) => $b['state'] !== 'received'));
                if ($missing) $add('bi_received_unordered', $o, implode(', ', $missing));
            }
            foreach ($t['buy'] as $sup => $b) {
                if (($b['problem'] ?? '') === 'failed') $add('supplier_failed', $o, $sup . (($b['reason'] ?? '') !== '' ? ' — ' . $b['reason'] : ''));
            }
        }

        // The stored roll-up vs. a fresh derivation (cheap for the open ones).
        if (!$out_ || $stage !== 'dispatched') {
            $now = os_derive_stage($pdo, $id, $f);
            if ($now !== $stage) $add('stage_stale', $o, 'Shows ' . os_stage_label($stage !== '' ? $stage : null) . ', really ' . os_stage_label($now));
        }
    }

    $order = array_flip(array_keys(oc_checks()));
    usort($out, static fn ($a, $b) => ($order[$a['check']] <=> $order[$b['check']]) ?: strcmp($b['quote_number'], $a['quote_number']));
    return $out;
}

/** Re-roll the stored stage for every order the report says is stale. Returns how many. */
function oc_fix_stale_stages(PDO $pdo, int $factory): int
{
    $n = 0;
    foreach (oc_findings($pdo, $factory) as $r) {
        if ($r['check'] !== 'stage_stale') continue;
        recompute_order_stage($pdo, $r['quote_id']);
        $n++;
    }
    return $n;
}

/** Plain-text summary for the daily email ('' when everything adds up). */
function oc_summary_text(array $findings, string $url): string
{
    if (!$findings) return '';
    $checks = oc_checks();
    $by = [];
    foreach ($findings as $r) $by[$r['check']][] = $r;
    $txt = count($findings) . " order " . (count($findings) === 1 ? 'check needs' : 'checks need') . " a look today.\n";
    foreach ($by as $k => $rows) {
        $txt .= "\n" . $checks[$k][0] . ' (' . count($rows) . ")\n";
        foreach (array_slice($rows, 0, 10) as $r) {
            $txt .= '  - ' . $r['quote_number'] . ($r['account'] !== '' ? ' · ' . $r['account'] : '')
                  . ($r['detail'] !== '' ? ' · ' . $r['detail'] : '') . "\n";
        }
        if (count($rows) > 10) $txt .= '  … and ' . (count($rows) - 10) . " more\n";
        $txt .= '  What to do: ' . $checks[$k][1] . "\n";
    }
    return $txt . "\nThe full list: " . $url . "\n";
}
