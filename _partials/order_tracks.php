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
 * Make vs buy is the MASTER product's supplier (bought_in.php): blank or
 * "In House" is made here, anything else is bought in.
 */

require_once __DIR__ . '/bought_in.php';

/**
 * @param int[] $quoteIds
 * @return array<int, array{
 *   make: array{units:int, made:int, released:bool},
 *   buy: array<string, array{units:int, state:string, sent_at:?string, received_at:?string, has_email:bool}>,
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
        $out[$q] = ['make' => ['units' => 0, 'made' => 0, 'released' => false], 'buy' => [],
                    'total_units' => 0, 'ready_units' => 0, 'waiting_on' => [], 'lines' => []];
    }

    // The factory's lines, each tagged make or buy (by the master product's supplier).
    $lines = [];
    try {
        $st = $pdo->prepare(
            'SELECT qi.id, qi.quote_id, qi.quantity, TRIM(COALESCE(mp.supplier_name, \'\')) AS supplier,
                    (' . bought_in_inhouse_predicate('mp') . ') AS inhouse
               FROM quote_items qi
               JOIN products p ON p.id = qi.product_id
               ' . bought_in_master_join('p', 'mp') . "
              WHERE qi.quote_id IN ($ph) AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?"
        );
        $st->execute([...$quoteIds, $factoryId]);
        $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return $out; }

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
            "SELECT quote_id, TRIM(supplier_name) AS supplier, MIN(sent_at) AS sent_at,
                    MAX(received_at) AS received_at, SUM(received_at IS NULL) AS open_rows
               FROM supplier_orders
              WHERE quote_id IN ($ph) AND ordered_by_factory_id = ?
           GROUP BY quote_id, TRIM(supplier_name)"
        );
        $ls->execute([...$quoteIds, $factoryId]);
        foreach ($ls->fetchAll(PDO::FETCH_ASSOC) as $r) $log[(int) $r['quote_id']][(string) $r['supplier']] = $r;
    } catch (Throwable $e) { /* supplier ordering not migrated */ }

    // Which suppliers have an order email (auto-send skips the rest silently).
    $email = [];
    try {
        $es = $pdo->prepare('SELECT TRIM(name) AS name, TRIM(COALESCE(email, \'\')) AS email FROM suppliers WHERE client_id = ?');
        $es->execute([$factoryId]);
        foreach ($es->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $email[(string) $r['name']] = filter_var((string) $r['email'], FILTER_VALIDATE_EMAIL) !== false;
        }
    } catch (Throwable $e) { /* suppliers table absent */ }

    $day = static fn (?string $ts): string => $ts ? date('j M', strtotime($ts)) : '';

    foreach ($lines as $ln) {
        $q   = (int) $ln['quote_id'];
        $iid = (int) $ln['id'];
        $qty = max(1, (int) $ln['quantity']);
        $o   = &$out[$q];
        $o['total_units'] += $qty;

        if ((int) $ln['inhouse'] === 1) {
            $o['make']['units'] += $qty;
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
            if (!isset($o['buy'][$sup])) {
                $l = $log[$q][$sup] ?? null;
                $state = $l === null ? 'to_order' : ((int) $l['open_rows'] === 0 ? 'received' : 'ordered');
                $o['buy'][$sup] = [
                    'units' => 0, 'state' => $state,
                    'sent_at' => $l['sent_at'] ?? null,
                    'received_at' => $state === 'received' ? ($l['received_at'] ?? null) : null,
                    'has_email' => $email[$sup] ?? false,
                ];
            }
            $o['buy'][$sup]['units'] += $qty;
            $b = $o['buy'][$sup];
            $status = $b['state'] === 'received' ? 'Received'
                    : ($b['state'] === 'ordered' ? 'Ordered ' . $day($b['sent_at']) : 'To order');
            $o['lines'][$iid] = ['track' => 'buy', 'supplier' => $sup, 'status' => $status];
        }
        unset($o);
    }

    foreach ($out as $q => &$o) {
        $ready = $o['make']['made'];
        $left  = $o['make']['units'] - $o['make']['made'];
        if ($left > 0) {
            $o['waiting_on'][] = $o['make']['released']
                ? $left . ' on the floor'
                : $left . ' to make';
        }
        foreach ($o['buy'] as $sup => $b) {
            if ($b['state'] === 'received') { $ready += $b['units']; continue; }
            $o['waiting_on'][] = $sup . ($b['state'] === 'ordered' ? ' (ordered ' . $day($b['sent_at']) . ')' : ' (to order)');
        }
        $o['ready_units'] = $ready;
    }
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
    $lanes = ['new', 'make', 'order', 'awaiting', 'ready', 'open', 'dispatched'];
    $lane  = (string) $lane;
    return '/factory/incoming-orders.php' . (in_array($lane, $lanes, true) && $lane !== 'open' ? '?lane=' . $lane : '');
}

/**
 * The jobs an order still has, in doing order — what Incoming orders offers as
 * its buttons and what the Factory Pipeline cards offer (one rule for both).
 *   $fj = factory_jobs.status ('' = not accepted), $t = ot_order_tracks() entry,
 *   $fs = quotes.fulfilment_stage.
 * Each job: ['lane' => Incoming orders tab, 'kind' => accept|order|start|receive|
 * dispatch|floor, 'supplier' => for order/receive].
 */
function ot_next_jobs(string $fj, array $t, string $fs): array
{
    $jobs = [];
    if ($fj === '') $jobs[] = ['lane' => 'new', 'kind' => 'accept'];
    foreach ($t['buy'] as $sup => $b) {
        if ($b['state'] === 'to_order') $jobs[] = ['lane' => 'order', 'kind' => 'order', 'supplier' => $sup];
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
