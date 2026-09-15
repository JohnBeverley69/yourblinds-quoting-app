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

    if (os_is_ready($pdo, $quoteId, $factory)) return 'ready';

    // In production once released to the floor, or the bought-in POs are out.
    $floorTotal = (int) (bj_order_progress($pdo, [$quoteId])[$quoteId]['total'] ?? 0);
    $biOrdered  = !empty(factory_boughtin_ordered_names($pdo, $quoteId, $factory));
    if ($fj === 'in_production' || $floorTotal > 0 || $biOrdered) return 'in_production';
    return 'confirmed';
}

/**
 * Ready to dispatch: every in-house blind made AND every bought-in line
 * received, once real work has started. This is THE gate for dispatch — Phase 1
 * enforces it on both the floor and the wholesale delivery-note paths.
 */
function os_is_ready(PDO $pdo, int $quoteId, ?int $factory = null): bool
{
    try {
        $factory = $factory ?? os_factory_id();

        $fj = 'new';
        try {
            $s = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? LIMIT 1');
            $s->execute([$quoteId]);
            $v = $s->fetchColumn();
            if ($v !== false && $v !== null && $v !== '') $fj = (string) $v;
        } catch (Throwable $e) { /* treat as new */ }

        $prog         = bj_order_progress($pdo, [$quoteId])[$quoteId] ?? ['total' => 0, 'done' => 0];
        $floorTotal   = (int) $prog['total'];
        $floorDone    = (int) $prog['done'];
        $inhouseLines = os_inhouse_line_count($pdo, $quoteId, $factory);

        $biNames   = factory_boughtin_supplier_names($pdo, $quoteId, $factory);
        $hasBI     = !empty($biNames);
        $biOrdered = $hasBI && !empty(factory_boughtin_ordered_names($pdo, $quoteId, $factory));
        $biComplete = !$hasBI || !factory_boughtin_awaiting($pdo, $quoteId, $factory);

        // In-house complete: no in-house lines to make, or the floor says all made.
        $inhouseComplete = ($inhouseLines === 0) || ($fj === 'made') || ($floorTotal > 0 && $floorDone === $floorTotal);
        // Real work started, so a fresh Confirmed order can't read as Ready.
        $workStarted = ($floorTotal > 0) || in_array($fj, ['received', 'in_production', 'made', 'dispatched'], true) || $biOrdered;

        return $inhouseComplete && $biComplete && $workStarted;
    } catch (Throwable $e) { return false; }
}

/**
 * Bridge: mark the order dispatched on the FLOOR side (factory_jobs). Called when
 * a delivery note is dispatched from wholesale, so the factory queue reflects it.
 * Best-effort — a missing factory_jobs table is not fatal.
 */
function os_set_factory_dispatched(PDO $pdo, int $quoteId, ?int $userId = null): void
{
    try {
        $pdo->prepare(
            "INSERT INTO factory_jobs (quote_id, status, status_at, status_by)
             VALUES (?, 'dispatched', NOW(), ?)
             ON DUPLICATE KEY UPDATE status = 'dispatched', status_at = NOW(), status_by = VALUES(status_by)"
        )->execute([$quoteId, $userId ?: null]);
    } catch (Throwable $e) { /* factory_jobs absent — ignore */ }
}

/**
 * Bridge: ensure a DISPATCHED delivery note exists for the order — the delivery
 * note is the dispatch artifact. Called when the order is dispatched on the
 * floor, so "one dispatch = one delivery note" holds whichever path is used.
 * Idempotent (dispatches an existing draft, never duplicates) and best-effort.
 */
function os_mark_dispatched_dn(PDO $pdo, int $quoteId, ?int $factory = null, ?int $userId = null): void
{
    try {
        $factory = $factory ?? os_factory_id();
        require_once __DIR__ . '/factory_ar.php';
        if (!function_exists('ar_create_delivery_note')) return;

        $existing = $pdo->prepare(
            "SELECT id, status FROM factory_ar_delivery_notes
              WHERE source_quote_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1"
        );
        $existing->execute([$quoteId]);
        $dn = $existing->fetch(PDO::FETCH_ASSOC);
        if ($dn) {
            if (($dn['status'] ?? '') !== 'dispatched') {
                $pdo->prepare("UPDATE factory_ar_delivery_notes SET status = 'dispatched', dispatched_at = NOW() WHERE id = ?")
                    ->execute([(int) $dn['id']]);
            }
            return;
        }

        $q = $pdo->prepare('SELECT account_client_id, client_id FROM quotes WHERE id = ? LIMIT 1');
        $q->execute([$quoteId]);
        $r   = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $acc = (int) ($r['account_client_id'] ?? 0) ?: (int) ($r['client_id'] ?? 0);
        ar_create_delivery_note($pdo, $factory, $quoteId, $acc, (int) $userId, true);
    } catch (Throwable $e) { /* non-fatal — never block a dispatch */ }
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
