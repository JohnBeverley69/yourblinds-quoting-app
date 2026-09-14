<?php
declare(strict_types=1);

/**
 * The single canonical order fulfilment stage — Phase 0 of the flow redesign.
 *
 * Today an order's real state is scattered across several trackers that never
 * reconcile: quotes.status, factory_jobs.status (order-level), factory_blind_jobs
 * / factory_blind_streams (the floor), the bought-in log (supplier_orders) and
 * the delivery notes. Nothing answers "where is this order?" in one place.
 *
 * recompute_order_stage() is a READ-ONLY ROLL-UP: it derives one stage
 * (Confirmed / In Production / Ready / Dispatched) from those existing trackers
 * and writes it to quotes.fulfilment_stage. It never changes any of the detail
 * trackers, so this is behaviour-preserving — it only adds a derived field the
 * screens can start trusting. Best-effort: any failure is swallowed so it can
 * never break the action that called it.
 *
 * Requires migrate_order_fulfilment_stage.php (adds quotes.fulfilment_stage).
 */

require_once __DIR__ . '/bought_in.php';
require_once __DIR__ . '/blind_jobs.php';
require_once __DIR__ . '/factory_boughtin.php';

/** An order only has a fulfilment stage once it's placed. */
function os_placed_statuses(): array { return ['ordered', 'fitted', 'invoiced', 'paid']; }

/** The acting/master factory id. */
function os_factory_id(): int
{
    if (function_exists('current_factory_id')) return (int) current_factory_id();
    if (function_exists('factory_client_id'))  return (int) factory_client_id();
    return (int) (function_exists('env') ? (env('FACTORY_CLIENT_ID', '3') ?? 3) : 3);
}

/** Human label for a stage code. */
function os_stage_label(?string $s): string
{
    switch ($s) {
        case 'confirmed':     return 'Confirmed';
        case 'in_production': return 'In Production';
        case 'ready':         return 'Ready';
        case 'dispatched':    return 'Dispatched';
        default:              return '—';
    }
}

/** Ordered list of the stages (for UI / legends). */
function os_stages(): array { return ['confirmed', 'in_production', 'ready', 'dispatched']; }

/**
 * Derive + persist the fulfilment stage for one order. Returns the stage code,
 * or null for a non-placed / non-factory order. Read-only over the detail
 * trackers; only quotes.fulfilment_stage is written.
 */
function recompute_order_stage(PDO $pdo, int $quoteId): ?string
{
    try {
        if ($quoteId <= 0) return null;
        $factory = os_factory_id();

        $q = $pdo->prepare('SELECT status FROM quotes WHERE id = ? LIMIT 1');
        $q->execute([$quoteId]);
        $status = (string) ($q->fetchColumn() ?: '');
        $placed = in_array($status, os_placed_statuses(), true);

        // Must contain at least one factory-owned line to be a factory order.
        $fc = $pdo->prepare(
            'SELECT COUNT(*) FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?'
        );
        $fc->execute([$quoteId, $factory]);
        $isFactoryOrder = ((int) $fc->fetchColumn()) > 0;

        $stage = ($placed && $isFactoryOrder) ? os_derive_stage($pdo, $quoteId, $factory) : null;

        try {
            $pdo->prepare('UPDATE quotes SET fulfilment_stage = ? WHERE id = ?')->execute([$stage, $quoteId]);
        } catch (Throwable $e) { /* column absent pre-migration — ignore */ }

        return $stage;
    } catch (Throwable $e) {
        error_log('recompute_order_stage failed for quote ' . $quoteId . ': ' . $e->getMessage());
        return null;
    }
}

/** The derivation. Assumes a placed factory order. */
function os_derive_stage(PDO $pdo, int $quoteId, int $factory): string
{
    // Order-level factory status: (none) / new / received / in_production / made / dispatched.
    $fj = 'new';
    try {
        $s = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? LIMIT 1');
        $s->execute([$quoteId]);
        $v = $s->fetchColumn();
        if ($v !== false && $v !== null && $v !== '') $fj = (string) $v;
    } catch (Throwable $e) { /* table absent — treat as new */ }

    // A dispatched delivery note counts as dispatched too.
    $dnDispatched = false;
    try {
        $d = $pdo->prepare("SELECT COUNT(*) FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND status = 'dispatched'");
        $d->execute([$quoteId]);
        $dnDispatched = ((int) $d->fetchColumn()) > 0;
    } catch (Throwable $e) { /* table absent — ignore */ }

    if ($fj === 'dispatched' || $dnDispatched) return 'dispatched';

    // In-house floor progress (whole blinds), and the in-house lines the order has.
    $prog       = bj_order_progress($pdo, [$quoteId])[$quoteId] ?? ['total' => 0, 'done' => 0];
    $floorTotal = (int) $prog['total'];
    $floorDone  = (int) $prog['done'];
    $inhouseLines = os_inhouse_line_count($pdo, $quoteId, $factory);

    // Bought-in state.
    $biNames    = factory_boughtin_supplier_names($pdo, $quoteId, $factory);
    $hasBI      = !empty($biNames);
    $biOrdered  = $hasBI && !empty(factory_boughtin_ordered_names($pdo, $quoteId, $factory));
    $biComplete = !$hasBI || !factory_boughtin_awaiting($pdo, $quoteId, $factory);

    // In-house complete: no in-house lines to make, OR the floor says all made.
    $inhouseComplete = ($inhouseLines === 0) || ($fj === 'made') || ($floorTotal > 0 && $floorDone === $floorTotal);

    // Real work started, so a fresh Confirmed order can't read as Ready.
    $workStarted = ($floorTotal > 0) || in_array($fj, ['received', 'in_production', 'made', 'dispatched'], true) || $biOrdered;

    if ($inhouseComplete && $biComplete && $workStarted) return 'ready';
    if ($fj === 'in_production' || $floorTotal > 0 || $biOrdered) return 'in_production';
    return 'confirmed';
}

/** Count of the order's in-house (made-here) factory-owned lines. */
function os_inhouse_line_count(PDO $pdo, int $quoteId, int $factory): int
{
    try {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM quote_items qi JOIN products p ON p.id = qi.product_id
              ' . bought_in_master_join('p', 'mp') . '
             WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
               AND NOT (' . bought_in_predicate('mp') . ')'
        );
        $st->execute([$quoteId, $factory]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
