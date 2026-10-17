<?php
declare(strict_types=1);

/**
 * ORDER PARTS — one order, one "work part" per factory that makes it.
 *
 * An order can mix lines made by two factories: a white-label factory's own
 * blinds alongside the main factory's (Tyler's TYL-2026-0006: two Beverley
 * rollers plus Tyler's own vertical). The customer sees ONE order — one number,
 * one invoice, one deposit. Each factory sees only its part, with its own
 * status, due date, "new order" email and Pipeline stage, and nothing it does
 * moves the other factory's part (John, 2026-10-17).
 *
 *   factory_jobs            one row per (quote, factory) — the factory's own
 *                           production status. No row = new (not processed).
 *   order_factory_parts     one row per (quote, factory) — that factory's
 *                           fulfilment stage, due date and notified stamp.
 *   quotes.fulfilment_stage the CUSTOMER-FACING roll-up: the least advanced part.
 *   quotes.due_date         the customer-facing roll-up: the latest part's date.
 *
 * A single-factory order has one part whose values equal the quote's, so every
 * screen shows exactly what it did before. Factory screens read their own part
 * (COALESCE(part, quote) so a not-yet-backfilled order still shows).
 *
 * Requires setup/migrations/migrate_order_factory_parts.php.
 */

/** Stage order, least advanced first — the roll-up takes the minimum. */
function op_stage_rank(?string $s): int
{
    $i = array_search((string) $s, ['confirmed', 'in_production', 'ready', 'dispatched'], true);
    return $i === false ? -1 : (int) $i;
}

/**
 * The factories that make a line on this order — each line's owner
 * (COALESCE(NULLIF(source_client_id,0), client_id)) that is a factory account.
 * The main factory first, then by id. [] for a quote with no factory-made line.
 */
function op_order_factories(PDO $pdo, int $quoteId): array
{
    if ($quoteId <= 0) return [];
    try {
        $st = $pdo->prepare(
            'SELECT DISTINCT COALESCE(NULLIF(p.source_client_id,0), p.client_id)
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id = ?'
        );
        $st->execute([$quoteId]);
        $owners = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) { return []; }
    if (!function_exists('qb_factory_client_ids')) require_once __DIR__ . '/../quote-builder/_helpers.php';
    $factories = qb_factory_client_ids($pdo);
    $main = function_exists('factory_client_id') ? (int) factory_client_id() : 0;
    $out = array_values(array_filter($owners, static fn (int $o): bool => in_array($o, $factories, true)));
    usort($out, static fn (int $a, int $b): int => [$a !== $main, $a] <=> [$b !== $main, $b]);
    return $out;
}

/** This factory's production status on the order ('' = not processed / new). */
function op_fj_status(PDO $pdo, int $quoteId, int $factory): string
{
    try {
        $s = $pdo->prepare('SELECT status FROM factory_jobs WHERE quote_id = ? AND factory_client_id = ? LIMIT 1');
        $s->execute([$quoteId, $factory]);
        return (string) ($s->fetchColumn() ?: '');
    } catch (Throwable $e) { return ''; }
}

/** This factory's part row (fulfilment_stage, due_date, notified_at), or null. */
function op_part(PDO $pdo, int $quoteId, int $factory): ?array
{
    try {
        $s = $pdo->prepare('SELECT * FROM order_factory_parts WHERE quote_id = ? AND factory_client_id = ? LIMIT 1');
        $s->execute([$quoteId, $factory]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}

/**
 * Write fields on this factory's part, creating it if needed. Only the columns
 * fulfilment_stage / due_date / notified_at are accepted. Best-effort.
 */
function op_set_part(PDO $pdo, int $quoteId, int $factory, array $fields): void
{
    $fields = array_intersect_key($fields, array_flip(['fulfilment_stage', 'due_date', 'notified_at']));
    if ($quoteId <= 0 || $factory <= 0 || !$fields) return;
    $cols = array_keys($fields);
    $sql  = 'INSERT INTO order_factory_parts (quote_id, factory_client_id, ' . implode(', ', $cols) . ')
             VALUES (?, ?' . str_repeat(', ?', count($cols)) . ')
             ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn ($c) => "$c = VALUES($c)", $cols));
    try { $pdo->prepare($sql)->execute(array_merge([$quoteId, $factory], array_values($fields))); }
    catch (Throwable $e) { error_log('op_set_part failed for quote ' . $quoteId . ': ' . $e->getMessage()); }
}

/** True once the parts table exists (the migration has run). */
function op_ready(PDO $pdo): bool
{
    static $ok = null;
    if ($ok === null) {
        try { $pdo->query('SELECT 1 FROM order_factory_parts LIMIT 0'); $ok = true; }
        catch (Throwable $e) { $ok = false; }
    }
    return $ok;
}

/**
 * The customer-facing due date onto the quote: the LATEST part's date (the
 * order is complete when the slowest factory finishes), null when no part has
 * one. Returns it.
 */
function op_rollup_due(PDO $pdo, int $quoteId): ?string
{
    try {
        $s = $pdo->prepare('SELECT MAX(due_date) FROM order_factory_parts WHERE quote_id = ?');
        $s->execute([$quoteId]);
        $due = $s->fetchColumn() ?: null;
        $pdo->prepare('UPDATE quotes SET due_date = ? WHERE id = ?')->execute([$due, $quoteId]);
        return $due !== null ? (string) $due : null;
    } catch (Throwable $e) { return null; }
}

/**
 * This factory's stage for each order: [quoteId => stage|null], its own part's
 * stage, falling back to the quote's roll-up when it has no part yet.
 */
function op_stages_for(PDO $pdo, array $quoteIds, int $factory): array
{
    $quoteIds = array_values(array_filter(array_map('intval', $quoteIds)));
    if (!$quoteIds) return [];
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));
    try {
        $s = $pdo->prepare(
            "SELECT q.id, COALESCE(o.fulfilment_stage, q.fulfilment_stage) AS fulfilment_stage
               FROM quotes q LEFT JOIN order_factory_parts o ON o.quote_id = q.id AND o.factory_client_id = ?
              WHERE q.id IN ($ph)"
        );
        $s->execute([$factory, ...$quoteIds]);
    } catch (Throwable $e) {   // parts table not migrated yet
        $s = $pdo->prepare("SELECT id, fulfilment_stage FROM quotes WHERE id IN ($ph)");
        $s->execute($quoteIds);
    }
    $out = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id']] = $r['fulfilment_stage'];
    return $out;
}

/**
 * This factory's due date for each order: [quoteId => 'Y-m-d'|''], its own
 * part's date, falling back to the quote's when it has none.
 */
function op_due_for(PDO $pdo, array $quoteIds, int $factory): array
{
    $quoteIds = array_values(array_filter(array_map('intval', $quoteIds)));
    if (!$quoteIds) return [];
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));
    try {
        $s = $pdo->prepare(
            "SELECT q.id, COALESCE(o.due_date, q.due_date) AS due_date
               FROM quotes q LEFT JOIN order_factory_parts o ON o.quote_id = q.id AND o.factory_client_id = ?
              WHERE q.id IN ($ph)"
        );
        $s->execute([$factory, ...$quoteIds]);
    } catch (Throwable $e) {   // parts table not migrated yet
        try {
            $s = $pdo->prepare("SELECT id, due_date FROM quotes WHERE id IN ($ph)");
            $s->execute($quoteIds);
        } catch (Throwable $e2) { return []; }
    }
    $out = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id']] = (string) ($r['due_date'] ?? '');
    return $out;
}

/**
 * SQL for this factory's due date on quote alias $q: its part's date, else the
 * quote's. Safe to inline — $factory is cast to int.
 */
function op_due_sql(PDO $pdo, int $factory, string $q = 'q'): string
{
    if (!op_ready($pdo)) return "$q.due_date";
    return "COALESCE((SELECT ofp_d.due_date FROM order_factory_parts ofp_d
                       WHERE ofp_d.quote_id = $q.id AND ofp_d.factory_client_id = " . (int) $factory . "), $q.due_date)";
}

/**
 * The OTHER factories' parts of orders $factory placed itself — what it buys
 * from them (Tyler's own order carrying Beverley's rollers):
 *   [quoteId => [factory name => ['factory_id', 'units', 'stage']]]
 * stage is that factory's own ('' = not placed yet / no part).
 */
function op_partner_parts(PDO $pdo, array $quoteIds, int $factory): array
{
    $quoteIds = array_values(array_filter(array_map('intval', $quoteIds)));
    if (!$quoteIds || $factory <= 0) return [];
    if (!function_exists('qb_factory_client_ids')) require_once __DIR__ . '/../quote-builder/_helpers.php';
    $others = array_values(array_diff(qb_factory_client_ids($pdo), [$factory]));
    if (!$others) return [];
    $ph  = implode(',', array_fill(0, count($quoteIds), '?'));
    $oph = implode(',', array_fill(0, count($others), '?'));
    $stageSel = op_ready($pdo)
        ? '(SELECT o.fulfilment_stage FROM order_factory_parts o WHERE o.quote_id = qi.quote_id AND o.factory_client_id = c.id)'
        : 'NULL';
    try {
        $st = $pdo->prepare(
            "SELECT qi.quote_id, c.id AS fid, c.company_name, SUM(GREATEST(1, qi.quantity)) AS units, $stageSel AS stage
               FROM quote_items qi
               JOIN quotes q   ON q.id = qi.quote_id AND q.client_id = ?
               JOIN products p ON p.id = qi.product_id
               JOIN clients c  ON c.id = COALESCE(NULLIF(p.source_client_id,0), p.client_id)
              WHERE qi.quote_id IN ($ph) AND c.id IN ($oph)
           GROUP BY qi.quote_id, c.id, c.company_name"
        );
        $st->execute([$factory, ...$quoteIds, ...$others]);
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['quote_id']][(string) $r['company_name']] = [
            'factory_id' => (int) $r['fid'], 'units' => (int) $r['units'], 'stage' => (string) ($r['stage'] ?? ''),
        ];
    }
    return $out;
}

/** "With Beverley: …" words for another factory's part. */
function op_partner_stage_label(string $stage): string
{
    switch ($stage) {
        case 'in_production': return 'In production';
        case 'ready':         return 'Ready to dispatch';
        case 'dispatched':    return 'Dispatched';
        case 'confirmed':     return 'Not started yet';
        default:              return 'Not placed yet';
    }
}

/**
 * Is $factory's own order still waiting for another factory's part? Then its
 * own part can't be ready to go out — the job ships to the customer whole.
 */
function op_waiting_on_partner(PDO $pdo, int $quoteId, int $factory): bool
{
    foreach (op_partner_parts($pdo, [$quoteId], $factory)[$quoteId] ?? [] as $p) {
        if ($p['stage'] !== 'dispatched') return true;
    }
    return false;
}

/**
 * True when some part of a multi-factory order is dispatched but the roll-up
 * isn't — the customer-facing "Part dispatched".
 */
function op_part_dispatched(PDO $pdo, int $quoteId): bool
{
    try {
        $s = $pdo->prepare(
            "SELECT SUM(fulfilment_stage = 'dispatched') AS d, COUNT(*) AS n FROM order_factory_parts WHERE quote_id = ?"
        );
        $s->execute([$quoteId]);
        $r = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int) ($r['d'] ?? 0) > 0 && (int) ($r['d'] ?? 0) < (int) ($r['n'] ?? 0);
    } catch (Throwable $e) { return false; }
}
