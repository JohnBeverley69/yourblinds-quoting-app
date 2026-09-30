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

/**
 * The ordering account's view of factory progress: a small pill such as
 * "With the factory: In Production" for an order placed with the factory.
 * '' for no stage (not placed / not a factory order). Colours match the
 * factory's own Incoming Orders stage pills.
 */
function os_factory_progress_pill(?string $stage): string
{
    if ($stage === null || $stage === '' || !in_array($stage, os_stages(), true)) return '';
    $cols = [
        'confirmed'     => ['#5b6b7f', '#e6ebf1'],
        'in_production' => ['#b5730f', '#f7ecd6'],
        'ready'         => ['#245ea3', '#dde8f6'],
        'dispatched'    => ['#0d7a67', '#d6ece6'],
    ];
    [$fg, $bg] = $cols[$stage];
    $label = $stage === 'ready' ? 'Ready to dispatch' : os_stage_label($stage);
    return '<span class="factory-progress-pill" title="Progress at the factory making this order"'
         . ' style="display:inline-block;font-size:0.6875rem;font-weight:700;border-radius:999px;padding:0.0625rem 0.5rem;'
         . 'white-space:nowrap;color:' . $fg . ';background:' . $bg . '">'
         . 'With the factory: ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
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
        // Once blinds have been released to the floor, the FLOOR is the authority:
        // factory_jobs 'made' alone no longer counts (the office's "Mark made"
        // used to read Ready with 0 of 2 blinds made). Only an order that never
        // went onto the floor falls back to the order-level 'made' status.
        $inhouseComplete = ($inhouseLines === 0)
            || ($floorTotal > 0 ? $floorDone === $floorTotal : $fj === 'made');
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

/**
 * Phase 2 billing rule: when an order is dispatched, raise + send its invoice —
 * but ONLY when the installation setting `auto_invoice_on_dispatch` is ON (it
 * ships OFF, so dispatch just marks the order ready to invoice). Idempotent: an
 * order that already has a non-void invoice is skipped. Best-effort — never
 * blocks a dispatch. Call it AFTER the order has been marked dispatched.
 */
function os_auto_invoice_on_dispatch(PDO $pdo, int $quoteId, ?int $factory = null, ?int $userId = null): void
{
    try {
        require_once __DIR__ . '/app_settings.php';
        if (!function_exists('app_setting_on') || !app_setting_on('auto_invoice_on_dispatch')) return;

        $factory = $factory ?? os_factory_id();
        require_once __DIR__ . '/factory_ar.php';
        if (!function_exists('ar_create_invoice')) return;

        // Already invoiced (non-void)? Don't double-invoice.
        if (function_exists('ar_order_invoice_number') && ar_order_invoice_number($pdo, $quoteId) !== '') return;

        $q = $pdo->prepare('SELECT account_client_id, client_id FROM quotes WHERE id = ? LIMIT 1');
        $q->execute([$quoteId]);
        $r   = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        $acc = (int) ($r['account_client_id'] ?? 0) ?: (int) ($r['client_id'] ?? 0);

        // true = email it to the account once created; it is only marked 'sent'
        // if the email actually went (else it stays 'raised' to send from Wholesale).
        $inv = ar_create_invoice($pdo, $factory, $quoteId, $acc, (int) $userId, true);
        if (empty($inv['sent']) && ($inv['send_message'] ?? '') !== '') {
            error_log('os_auto_invoice_on_dispatch: ' . $inv['number'] . ' raised but not emailed — ' . $inv['send_message']);
        }
    } catch (Throwable $e) {
        error_log('os_auto_invoice_on_dispatch failed for quote ' . $quoteId . ': ' . $e->getMessage());
    }
}

/**
 * Why an order's LINES can no longer be edited from the factory side, or '' when
 * they still can. Once an order has been dispatched or invoiced, its lines are
 * what was delivered / billed — changing them would make the delivery note and
 * the invoice disagree with the order. Such a change needs a credit note or a
 * new order instead. Every check is guarded (tables may be pre-migration).
 */
function os_line_edit_lock(PDO $pdo, int $quoteId): string
{
    try {
        $q = $pdo->prepare('SELECT status FROM quotes WHERE id = ? LIMIT 1');
        $q->execute([$quoteId]);
        $status = (string) ($q->fetchColumn() ?: '');
        if (in_array($status, ['invoiced', 'paid'], true)) return 'it has been invoiced';
    } catch (Throwable $e) { /* fall through */ }

    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM factory_ar_invoice_orders io
               JOIN factory_ar_invoices i ON i.id = io.invoice_id
              WHERE io.quote_id = ? AND i.status <> 'void' LIMIT 1"
        );
        $st->execute([$quoteId]);
        if ($st->fetchColumn()) return 'it has been invoiced';
    } catch (Throwable $e) { /* not migrated */ }

    try {
        $s = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? LIMIT 1');
        $s->execute([$quoteId]);
        if ((string) ($s->fetchColumn() ?: '') === 'dispatched') return 'it has been dispatched';
    } catch (Throwable $e) { /* not migrated */ }

    try {
        $d = $pdo->prepare("SELECT 1 FROM factory_ar_delivery_notes WHERE source_quote_id = ? AND status = 'dispatched' LIMIT 1");
        $d->execute([$quoteId]);
        if ($d->fetchColumn()) return 'it has been dispatched';
    } catch (Throwable $e) { /* not migrated */ }

    return '';
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
