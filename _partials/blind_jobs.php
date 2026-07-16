<?php
declare(strict_types=1);

/**
 * Phase B helpers — releasing blinds to the floor and moving them along their
 * route. All state lives in factory_blind_jobs (see migrate_factory_blind_jobs.php).
 *
 * A blind = one quote_items line. It's anchored to a product_route_steps row
 * (route_step_id); station_id + seq are denormalised copies of that step so the
 * board/queues group without a join. Routes are editable, so every hop is
 * recomputed from the LIVE route rather than trusting the stored seq — and the
 * seq is kept only as a fallback anchor if the current step was deleted mid-run.
 *
 * Pure function library — safe to require anywhere after bootstrap + middleware.
 */

/** True once /migrate_factory_blind_jobs.php has run. Cached per request. */
function bj_tables_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try { $pdo->query('SELECT 1 FROM factory_blind_jobs LIMIT 0'); return $ready = true; }
    catch (Throwable $e) { return $ready = false; }
}

/**
 * The live, ordered route for a product: each stage with its station. Active
 * steps only, ordered by seq. Cached per product per request.
 * @return array<int,array<string,mixed>>
 */
function bj_route_steps(PDO $pdo, int $productId): array
{
    static $cache = [];
    if (isset($cache[$productId])) return $cache[$productId];
    $st = $pdo->prepare(
        'SELECT rs.id, rs.seq, rs.station_id, rs.label,
                s.name AS station, s.is_outsourced
           FROM product_route_steps rs
           JOIN factory_stations s ON s.id = rs.station_id
          WHERE rs.product_id = ? AND rs.active = 1
          ORDER BY rs.seq, rs.id'
    );
    $st->execute([$productId]);
    return $cache[$productId] = $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Release an order's Beverley blinds onto the floor, each parked at the first
 * stage of its product's route (or unrouted — station NULL — if the product has
 * no route yet). Returns how many NEW blind jobs were created.
 *
 * ONE CARD PER PHYSICAL BLIND: a qty-3 line becomes three jobs (unit 1..3) that
 * move through the route independently, since the workshop can have them at
 * three different benches at once.
 *
 * Idempotent on (line, unit) — a blind already tracked is left exactly where it
 * is, so re-releasing only tops up units that don't exist yet.
 *
 * The line points at the TENANT's pushed copy of the product, but routes are
 * defined once on Beverley's master product — so source_product_id maps the
 * copy back and the job stores the MASTER product id.
 */
function bj_release_order(PDO $pdo, int $quoteId, int $master): int
{
    $items = $pdo->prepare(
        'SELECT qi.id, qi.quantity, COALESCE(p.source_product_id, p.id) AS master_product_id
           FROM quote_items qi JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND p.source_client_id = ?
          ORDER BY qi.line_no, qi.id'
    );
    $items->execute([$quoteId, $master]);

    $ins = $pdo->prepare(
        "INSERT IGNORE INTO factory_blind_jobs
             (quote_id, quote_item_id, unit_no, product_id, route_step_id, station_id, seq, status, step_started_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())"
    );
    $created = 0;
    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $steps = bj_route_steps($pdo, (int) $it['master_product_id']);
        $first = $steps[0] ?? null;
        $qty   = max(1, (int) $it['quantity']);
        for ($unit = 1; $unit <= $qty; $unit++) {
            $ins->execute([
                $quoteId, (int) $it['id'], $unit, (int) $it['master_product_id'],
                $first ? (int) $first['id'] : null,
                $first ? (int) $first['station_id'] : null,
                $first ? (int) $first['seq'] : 0,
            ]);
            $created += $ins->rowCount();   // 0 when the UNIQUE key made it a no-op
        }
    }
    return $created;
}

/** Remove an order's blinds from the floor (used when it's reset to "new"). */
function bj_clear_order(PDO $pdo, int $quoteId): void
{
    $pdo->prepare('DELETE FROM factory_blind_jobs WHERE quote_id = ?')->execute([$quoteId]);
}

/** Fetch a single blind job row, or null. */
function bj_get(PDO $pdo, int $jobId): ?array
{
    $st = $pdo->prepare('SELECT * FROM factory_blind_jobs WHERE id = ?');
    $st->execute([$jobId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Mark a queued blind as being worked on right now. */
function bj_start(PDO $pdo, int $jobId, ?int $userId): void
{
    $pdo->prepare(
        "UPDATE factory_blind_jobs
            SET status = 'in_progress',
                started_at      = COALESCE(started_at, NOW()),
                step_started_at = COALESCE(step_started_at, NOW()),
                updated_by = ?
          WHERE id = ? AND status = 'queued'"
    )->execute([$userId, $jobId]);
}

/**
 * Finish the current stage and move the blind to the next one on its route.
 * If there's no next stage it's complete. Recomputed from the live route; if
 * the current step was deleted, the stored seq anchors the jump forward.
 */
function bj_advance(PDO $pdo, int $jobId, ?int $userId): void
{
    $job = bj_get($pdo, $jobId);
    if (!$job || $job['status'] === 'complete') return;

    $steps = bj_route_steps($pdo, (int) $job['product_id']);
    $idx = null;
    foreach ($steps as $i => $s) {
        if ((int) $s['id'] === (int) $job['route_step_id']) { $idx = $i; break; }
    }

    $next = null;
    if ($idx !== null) {
        $next = $steps[$idx + 1] ?? null;
    } else {
        // Current step was removed from the route mid-run: pick up at the next
        // live stage after where we were.
        foreach ($steps as $s) {
            if ((int) $s['seq'] > (int) $job['seq']) { $next = $s; break; }
        }
    }

    if ($next) {
        $pdo->prepare(
            "UPDATE factory_blind_jobs
                SET route_step_id = ?, station_id = ?, seq = ?, status = 'queued',
                    step_started_at = NOW(), started_at = COALESCE(started_at, NOW()),
                    updated_by = ?
              WHERE id = ?"
        )->execute([(int) $next['id'], (int) $next['station_id'], (int) $next['seq'], $userId, $jobId]);
    } else {
        $pdo->prepare(
            "UPDATE factory_blind_jobs
                SET route_step_id = NULL, station_id = NULL, status = 'complete',
                    completed_at = NOW(), started_at = COALESCE(started_at, NOW()),
                    updated_by = ?
              WHERE id = ?"
        )->execute([$userId, $jobId]);
        bj_maybe_complete_order($pdo, (int) $job['quote_id']);
    }
}

/** Step a blind back one stage (undo a premature "done"). */
function bj_back(PDO $pdo, int $jobId, ?int $userId): void
{
    $job = bj_get($pdo, $jobId);
    if (!$job) return;
    $steps = bj_route_steps($pdo, (int) $job['product_id']);
    if (!$steps) return;

    $prev = null;
    if ($job['status'] === 'complete') {
        $prev = $steps[count($steps) - 1];   // back onto the last stage
    } else {
        $idx = null;
        foreach ($steps as $i => $s) {
            if ((int) $s['id'] === (int) $job['route_step_id']) { $idx = $i; break; }
        }
        if ($idx !== null) {
            $prev = $steps[$idx - 1] ?? null; // already at stage 1 -> nothing before it
        } else {
            for ($i = count($steps) - 1; $i >= 0; $i--) {
                if ((int) $steps[$i]['seq'] < (int) $job['seq']) { $prev = $steps[$i]; break; }
            }
        }
    }
    if (!$prev) return;

    $pdo->prepare(
        "UPDATE factory_blind_jobs
            SET route_step_id = ?, station_id = ?, seq = ?, status = 'queued',
                completed_at = NULL, step_started_at = NOW(), updated_by = ?
          WHERE id = ?"
    )->execute([(int) $prev['id'], (int) $prev['station_id'], (int) $prev['seq'], $userId, $jobId]);
}

/**
 * When every blind on an order has finished its route, nudge the order-level
 * status to "made" — but only from "in_production", so a manual dispatch or a
 * step-back isn't clobbered.
 */
function bj_maybe_complete_order(PDO $pdo, int $quoteId): void
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS total, SUM(status = 'complete') AS done_cnt
           FROM factory_blind_jobs WHERE quote_id = ?"
    );
    $st->execute([$quoteId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'done_cnt' => 0];
    if ((int) $r['total'] > 0 && (int) $r['total'] === (int) $r['done_cnt']) {
        try {
            $pdo->prepare(
                "UPDATE factory_jobs SET status = 'made', status_at = NOW()
                  WHERE quote_id = ? AND status = 'in_production'"
            )->execute([$quoteId]);
        } catch (Throwable $e) { /* factory_jobs optional — ignore */ }
    }
}

/**
 * Per-order floor progress for a set of quote ids: [quoteId => [total, done]].
 * Used by Incoming Orders to show "X of Y made" once an order is on the floor.
 */
function bj_order_progress(PDO $pdo, array $quoteIds): array
{
    $quoteIds = array_values(array_filter(array_map('intval', $quoteIds)));
    if (!$quoteIds) return [];
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));
    $st = $pdo->prepare(
        "SELECT quote_id, COUNT(*) AS total, SUM(status = 'complete') AS done_cnt
           FROM factory_blind_jobs WHERE quote_id IN ($ph) GROUP BY quote_id"
    );
    $st->execute($quoteIds);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['quote_id']] = ['total' => (int) $r['total'], 'done' => (int) $r['done_cnt']];
    }
    return $out;
}
