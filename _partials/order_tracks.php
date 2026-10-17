<?php
declare(strict_types=1);

/**
 * An order's two tracks: what the factory MAKES and what it BUYS IN.
 *
 * A factory order can mix blinds made here (rollers, verticals… they go to the
 * floor) and items bought in from a supplier (a PF Venetian from Hunter
 * Douglas: ordered, then received). One blended status hid which was which and
 * what an order was waiting for (John, 2026-10-11). This works out, per order,
 * each track's progress and a plain "waiting on …" list. Read-only: it derives
 * everything from data that already exists. Dispatch is still gated by
 * os_is_ready() in order_stage.php; this only explains WHY an order isn't ready.
 *
 *   make  — units made in-house and how many are complete on the floor
 *           (factory_blind_jobs, one row per unit once released).
 *   buy   — per supplier: units, to_order / ordered / received, from the
 *           supplier_orders log (keyed by quote + supplier, so receiving is per
 *           supplier, not per line).
 *
 * Once an order is processed (factory_jobs row, Process order) every bought-in
 * line should be on a PO, so a supplier still "to order" is a problem, never
 * silent (John, 2026-10-12):
 *   problem 'failed'   — its PO didn't go (no email, or the send failed);
 *   problem 'new_item' — a line added after its PO went / after processing.
 *
 * Make vs buy is the MASTER product's supplier (bought_in.php): blank or
 * "In House" is made here, anything else is bought in.
 */

require_once __DIR__ . '/bought_in.php';

/**
 * @param int[] $quoteIds
 * @return array<int, array{
 *   processed: bool,
 *   make: array{units:int, made:int, released:bool, products:array<string,int>},
 *   buy: array<string, array{units:int, state:string, sent_at:?string, received_at:?string, has_email:bool,
 *        email:string, problem:string, reason:string, new_units:int}>,
 *   total_units:int, ready_units:int, waiting_on:string[],
 *   lines: array<int, array{track:string, supplier:string, status:string}>
 * }>
 */
function ot_order_tracks(PDO $pdo, array $quoteIds, int $factoryId): array
{
    $quoteIds = array_values(array_unique(array_filter(array_map('intval', $quoteIds))));
    if (!$quoteIds || $factoryId <= 0) return [];
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));

    $out = [];
    foreach ($quoteIds as $q) {
        $out[$q] = ['processed' => false,
                    'make' => ['units' => 0, 'made' => 0, 'released' => false, 'products' => []], 'buy' => [],
                    'total_units' => 0, 'ready_units' => 0, 'waiting_on' => [], 'lines' => []];
    }

    // The factory's lines, each tagged make or buy (by the master product's supplier).
    $lines = [];
    try {
        $st = $pdo->prepare(
            'SELECT qi.id, qi.quote_id, qi.quantity, qi.created_at, qi.product_name_snapshot,
                    TRIM(COALESCE(mp.supplier_name, \'\')) AS supplier,
                    (' . bought_in_inhouse_predicate('mp') . ') AS inhouse
               FROM quote_items qi
               JOIN products p ON p.id = qi.product_id
               ' . bought_in_master_join('p', 'mp') . "
              WHERE qi.quote_id IN ($ph) AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
           ORDER BY qi.quote_id, qi.line_no, qi.id"
        );
        $st->execute([...$quoteIds, $factoryId]);
        $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return $out; }

    // Processed = taken in (factory_jobs row); its time is the cut-off for "added later".
    $processedAt = []; $fjStatus = [];
    try {
        try {
            // This factory's own part (factory_jobs is per order AND factory).
            $fj = $pdo->prepare("SELECT quote_id, status, COALESCE(received_at, status_at) AS at FROM factory_jobs WHERE quote_id IN ($ph) AND factory_client_id = ?");
            $fj->execute([...$quoteIds, $factoryId]);
        } catch (Throwable $e) {   // factory_client_id not migrated yet
            $fj = $pdo->prepare("SELECT quote_id, status, COALESCE(received_at, status_at) AS at FROM factory_jobs WHERE quote_id IN ($ph)");
            $fj->execute($quoteIds);
        }
        foreach ($fj->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ((string) $r['status'] === '') continue;
            $out[(int) $r['quote_id']]['processed'] = true;
            $processedAt[(int) $r['quote_id']] = (string) ($r['at'] ?? '');
            $fjStatus[(int) $r['quote_id']] = (string) $r['status'];
        }
    } catch (Throwable $e) { /* factory_jobs not migrated — nothing processed */ }

    // Floor progress per line (one factory_blind_jobs row per unit once released).
    $floor = [];
    try {
        $fs = $pdo->prepare(
            "SELECT quote_item_id, COUNT(*) AS total, SUM(status = 'complete') AS done
               FROM factory_blind_jobs WHERE quote_id IN ($ph) GROUP BY quote_item_id"
        );
        $fs->execute($quoteIds);
        foreach ($fs->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $floor[(int) $r['quote_item_id']] = ['total' => (int) $r['total'], 'done' => (int) $r['done']];
        }
    } catch (Throwable $e) { /* floor tables not migrated — nothing released */ }

    // Supplier log per (order, supplier). Received only when every row is.
    $log = [];
    try {
        $ls = $pdo->prepare(
            "SELECT quote_id, TRIM(supplier_name) AS supplier, MIN(sent_at) AS sent_at, MAX(sent_at) AS last_sent,
                    MAX(received_at) AS received_at, SUM(received_at IS NULL) AS open_rows
               FROM supplier_orders
              WHERE quote_id IN ($ph) AND ordered_by_factory_id = ?
           GROUP BY quote_id, TRIM(supplier_name)"
        );
        $ls->execute([...$quoteIds, $factoryId]);
        foreach ($ls->fetchAll(PDO::FETCH_ASSOC) as $r) $log[(int) $r['quote_id']][(string) $r['supplier']] = $r;
    } catch (Throwable $e) { /* supplier ordering not migrated */ }

    // The factory's suppliers' order emails (its own, never another factory's).
    $email = [];
    try {
        $es = $pdo->prepare('SELECT TRIM(name) AS name, TRIM(COALESCE(email, \'\')) AS email FROM suppliers WHERE client_id = ?');
        $es->execute([$factoryId]);
        foreach ($es->fetchAll(PDO::FETCH_ASSOC) as $r) $email[(string) $r['name']] = (string) $r['email'];
    } catch (Throwable $e) { /* suppliers table absent */ }

    // Why a PO didn't go: the email log keeps failed sends with the reason.
    $failedTo = [];
    try {
        $ms = $pdo->prepare(
            "SELECT quote_id, LOWER(TRIM(to_addr)) AS to_addr, status_detail
               FROM messages
              WHERE client_id = ? AND kind = 'supplier_order' AND status = 'failed' AND quote_id IN ($ph)
           ORDER BY id"
        );
        $ms->execute([$factoryId, ...$quoteIds]);
        foreach ($ms->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $failedTo[(int) $r['quote_id']][(string) $r['to_addr']] = trim((string) ($r['status_detail'] ?? ''));
        }
    } catch (Throwable $e) { /* email log not migrated */ }

    $day = static fn (?string $ts): string => $ts ? date('j M', strtotime($ts)) : '';

    foreach ($lines as $ln) {
        $q   = (int) $ln['quote_id'];
        $iid = (int) $ln['id'];
        $qty = max(1, (int) $ln['quantity']);
        $o   = &$out[$q];
        $o['total_units'] += $qty;

        if ((int) $ln['inhouse'] === 1) {
            $o['make']['units'] += $qty;
            $pn = trim((string) ($ln['product_name_snapshot'] ?? '')) ?: 'Blind';
            $o['make']['products'][$pn] = ($o['make']['products'][$pn] ?? 0) + $qty;
            $f = $floor[$iid] ?? null;
            if ($f !== null && $f['total'] > 0) {
                $o['make']['released'] = true;
                // Normally one floor job per unit (total = qty). Orders released
                // before per-unit jobs have ONE job for the whole line, so a
                // finished line counts in full — BEV-2026-0002 read "1/3 made"
                // while its line said Made.
                $o['make']['made'] += $f['done'] >= $f['total'] ? $qty : min($qty, $f['done']);
                $status = $f['done'] >= $f['total'] ? 'Made'
                        : 'In production (' . $f['done'] . '/' . $f['total'] . ')';
            } else {
                $status = 'Queued';
            }
            $o['lines'][$iid] = ['track' => 'make', 'supplier' => '', 'status' => $status];
        } else {
            $sup = (string) $ln['supplier'];
            $l   = $log[$q][$sup] ?? null;
            if (!isset($o['buy'][$sup])) {
                $state = $l === null ? 'to_order' : ((int) $l['open_rows'] === 0 ? 'received' : 'ordered');
                $addr  = $email[$sup] ?? '';
                $o['buy'][$sup] = [
                    'units' => 0, 'state' => $state,
                    'sent_at' => $l['sent_at'] ?? null,
                    'received_at' => $state === 'received' ? ($l['received_at'] ?? null) : null,
                    'has_email' => filter_var($addr, FILTER_VALIDATE_EMAIL) !== false,
                    'email' => $addr, 'problem' => '', 'reason' => '', 'new_units' => 0,
                ];
            }
            $b = &$o['buy'][$sup];
            $b['units'] += $qty;
            // Added after this supplier's PO went, or (never ordered) after the
            // order was processed: a new item to order, not a done one.
            $created = (string) ($ln['created_at'] ?? '');
            $cutoff  = $l !== null ? (string) $l['last_sent'] : ($o['processed'] ? ($processedAt[$q] ?? '') : '');
            $isNew   = $cutoff !== '' && $created !== '' && $created > $cutoff;
            if ($isNew) {
                $b['new_units'] += $qty;
                $status = 'New item to order';
            } elseif ($l === null) {
                $status = $o['processed'] ? 'Not ordered' : 'To order';
            } else {
                $status = (int) $l['open_rows'] === 0 ? 'Received' : 'Ordered ' . $day($l['sent_at'] ?? null);
            }
            $o['lines'][$iid] = ['track' => 'buy', 'supplier' => $sup, 'status' => $status];
            unset($b);
        }
        unset($o);
    }

    foreach ($out as $q => &$o) {
        // Still waiting for Process (new, or taken in the old way and never
        // floored): its suppliers are simply "to order", not problems yet.
        $o['needs_process'] = ot_needs_process($fjStatus[$q] ?? '', $o);
        if ($o['needs_process']) {
            foreach ($o['buy'] as &$b) { $b['problem'] = ''; $b['reason'] = ''; $b['new_units'] = 0; }
            unset($b);
            foreach ($o['lines'] as &$ol) {
                if ($ol['track'] === 'buy' && in_array($ol['status'], ['Not ordered', 'New item to order'], true)) $ol['status'] = 'To order';
            }
            unset($ol);
        }
        foreach ($o['buy'] as $sup => &$b) {
            if ($o['needs_process']) break;
            if ($b['new_units'] > 0) {
                $b['state']   = 'to_order';
                $b['problem'] = 'new_item';
                // Lines from before processing that never went still count as
                // a failure — the reason says so.
                if (!isset($log[$q][$sup]) && $b['new_units'] < $b['units']) $b['problem'] = 'failed';
            } elseif ($b['state'] === 'to_order' && $o['processed']) {
                $b['problem'] = 'failed';
            }
            // A new item for a supplier with no usable email can't be ordered
            // either: say so, rather than offer a send that will fail.
            if ($b['problem'] === 'new_item' && !$b['has_email']) $b['problem'] = 'failed';
            if ($b['problem'] === 'failed') {
                $addr = strtolower($b['email']);
                if ($b['email'] === '')      $b['reason'] = 'No order email is set for ' . $sup . ' (Settings › Suppliers).';
                elseif (!$b['has_email'])    $b['reason'] = 'The order email for ' . $sup . ' isn\'t a valid address (Settings › Suppliers).';
                elseif (isset($failedTo[$q][$addr])) {
                    $b['reason'] = 'The email to ' . $b['email'] . ' didn\'t send'
                                 . ($failedTo[$q][$addr] !== '' ? ': ' . rtrim($failedTo[$q][$addr], '. ') : '') . '.';
                } else                       $b['reason'] = 'Its purchase order hasn\'t been sent.';
            }
        }
        unset($b);
        foreach ($o['lines'] as &$ol) {
            if ($ol['track'] === 'buy' && $ol['status'] === 'New item to order'
                && ($o['buy'][$ol['supplier']]['problem'] ?? '') === 'failed') $ol['status'] = 'Not ordered';
        }
        unset($ol);

        $ready = $o['make']['made'];
        $left  = $o['make']['units'] - $o['make']['made'];
        if ($left > 0) {
            $o['waiting_on'][] = $o['make']['released']
                ? $left . ' on the floor'
                : $left . ' to make';
        }
        foreach ($o['buy'] as $sup => $b) {
            if ($b['state'] === 'received') { $ready += $b['units']; continue; }
            $o['waiting_on'][] = $sup . ' (' . ($b['problem'] === 'failed' ? 'couldn\'t order'
                : ($b['problem'] === 'new_item' ? 'new item to order'
                : ($b['state'] === 'ordered' ? 'ordered ' . $day($b['sent_at']) : 'to order'))) . ')';
        }
        $o['ready_units'] = $ready;
    }
    unset($o);

    // The other factory's part of OUR order (a white-label factory's own order
    // carrying the main factory's blinds): we buy it from them. Read-only —
    // its stage is theirs, from their part (order_parts.php).
    require_once __DIR__ . '/order_parts.php';
    foreach (op_partner_parts($pdo, $quoteIds, $factoryId) as $q => $parts) {
        if (!isset($out[$q])) continue;
        $out[$q]['partners'] = $parts;
        foreach ($parts as $name => $p) {
            if ($p['stage'] !== 'dispatched') $out[$q]['waiting_on'][] = $name . ' (' . strtolower(op_partner_stage_label($p['stage'])) . ')';
        }
    }
    foreach ($out as &$o) $o['partners'] = $o['partners'] ?? [];
    unset($o);
    return $out;
}

/**
 * Where a factory action returns to: the Incoming orders tab it was clicked from.
 * Only known lanes are accepted, so a posted value can't send the user elsewhere.
 */
function ot_back_url(?string $lane): string
{
    if ($lane === 'pipeline') return '/factory/pipeline.php';   // Factory Pipeline cards
    $lanes = ['new', 'make', 'problem', 'order', 'awaiting', 'ready', 'open', 'dispatched'];
    $lane  = (string) $lane;
    return '/factory/incoming-orders.php' . (in_array($lane, $lanes, true) && $lane !== 'open' ? '?lane=' . $lane : '');
}

/**
 * Does the order still need Process order? A new order (no factory_jobs row),
 * or one taken in the old way ("Received") whose blinds never went to the floor
 * — the Received stage is gone (John, 2026-10-12), so those go back to New and
 * Process finishes the job: floor + any purchase orders still outstanding.
 * $t may be null when the tracks weren't loaded.
 */
function ot_needs_process(string $fj, ?array $t): bool
{
    if ($fj === '') return true;
    if ($fj !== 'received' || !$t) return false;
    return $t['make']['units'] > 0 && !$t['make']['released'] && $t['make']['made'] === 0;
}

/**
 * The jobs an order still has, in doing order — what Incoming orders offers as
 * its buttons and what the Factory Pipeline cards offer (one rule for both).
 *   $fj = factory_jobs.status ('' = not processed), $t = ot_order_tracks() entry,
 *   $fs = quotes.fulfilment_stage.
 * Each job: ['lane' => Incoming orders tab, 'kind' => process|retry|order_new|
 * order|start|receive|dispatch|floor, 'supplier' => for the supplier jobs].
 *
 * A new order has one job, Process order: it takes the order in, puts its
 * blinds on the floor and emails every bought-in supplier its PO.
 */
function ot_next_jobs(string $fj, array $t, string $fs): array
{
    if (ot_needs_process($fj, $t)) return [['lane' => 'new', 'kind' => 'process']];
    $jobs = [];
    foreach ($t['buy'] as $sup => $b) {
        if ($b['state'] !== 'to_order') continue;
        $p = (string) ($b['problem'] ?? '');
        if ($p === 'failed')       $jobs[] = ['lane' => 'problem', 'kind' => 'retry', 'supplier' => $sup];
        elseif ($p === 'new_item') $jobs[] = ['lane' => 'order', 'kind' => 'order_new', 'supplier' => $sup];
        else                       $jobs[] = ['lane' => 'order', 'kind' => 'order', 'supplier' => $sup];
    }
    if ($fj === 'received' && $t['make']['units'] > 0) $jobs[] = ['lane' => 'make', 'kind' => 'start'];
    foreach ($t['buy'] as $sup => $b) {
        if ($b['state'] === 'ordered') $jobs[] = ['lane' => 'awaiting', 'kind' => 'receive', 'supplier' => $sup];
    }
    if ($fs === 'ready' && $fj !== 'dispatched') $jobs[] = ['lane' => 'ready', 'kind' => 'dispatch'];
    if ($fj === 'in_production' && $t['make']['made'] < $t['make']['units']) {
        $jobs[] = ['lane' => 'make', 'kind' => 'floor'];   // the floor is doing it; nothing to click but a look
    }
    return $jobs;
}
