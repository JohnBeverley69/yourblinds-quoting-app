<?php
declare(strict_types=1);

/**
 * Phase B helpers — releasing blinds to the floor and moving them along.
 *
 * A blind (factory_blind_jobs) is one physical unit. Its progress lives in
 * factory_blind_streams: ONE POSITION PER STREAM.
 *
 * Why streams: a vertical is two jobs that run alongside each other and never
 * meet in the workshop — the headrail (profile cut -> headrail assembly) and the
 * fabric (fabric cut -> sew/weld -> weighting & linking). They ship as parts.
 * The old single-pointer model treated every stage left of the pointer as done,
 * so cutting fabric first marked the headrail assembled when nobody had touched
 * it. Within a stream a pointer is honest (that work really is sequential);
 * across streams it never was.
 *
 * Roller and pleated have one stream ('main') and behave exactly as before.
 *
 * A pleasant consequence: a stream's current step is by definition the next
 * ready piece of work, so per-station queues only ever show what a bench can
 * actually start — no extra "is it ready?" logic anywhere.
 *
 * Routes are editable, so every hop is recomputed from the LIVE route; the
 * stored seq is only a fallback anchor if the current step was deleted mid-run.
 */

/**
 * The processes a workstation login covers: [['product_id'=>, 'stream'=>], …].
 *
 * A workstation is a PROCESS, not a bench — "Vertical Head Rail" owns the
 * vertical's Headrail stream, profile cut through to assembly, wherever the
 * saw happens to be. A login may cover any number (staff move where they're
 * needed) and several logins may cover the same one.
 */
function bj_workstation_streams(PDO $pdo, int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    try {
        $st = $pdo->prepare('SELECT product_id, stream FROM workstation_streams WHERE user_id = ?');
        $st->execute([$userId]);
        return $cache[$userId] = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return $cache[$userId] = []; }   // not migrated yet
}

/** Does this login cover this product's stream? */
function bj_covers(array $workstationStreams, int $productId, string $stream): bool
{
    foreach ($workstationStreams as $w) {
        if ((int) $w['product_id'] === $productId && (string) $w['stream'] === $stream) return true;
    }
    return false;
}

/** True once the blind-job + stream tables exist. Cached per request. */
function bj_tables_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $pdo->query('SELECT 1 FROM factory_blind_jobs LIMIT 0');
        $pdo->query('SELECT 1 FROM factory_blind_streams LIMIT 0');
        return $ready = true;
    } catch (Throwable $e) { return $ready = false; }
}

/**
 * The live, ordered route for a product, with each stage's stream. Active steps
 * only, ordered by seq. Cached per product per request.
 */
function bj_route_steps(PDO $pdo, int $productId): array
{
    static $cache = [];
    if (isset($cache[$productId])) return $cache[$productId];
    try {
        $st = $pdo->prepare(
            "SELECT rs.id, rs.seq, rs.station_id, rs.label,
                    COALESCE(NULLIF(rs.stream, ''), 'main') AS stream,
                    s.name AS station, s.is_outsourced
               FROM product_route_steps rs
               JOIN factory_stations s ON s.id = rs.station_id
              WHERE rs.product_id = ? AND rs.active = 1
              ORDER BY rs.seq, rs.id"
        );
        $st->execute([$productId]);
        return $cache[$productId] = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $cache[$productId] = [];
    }
}

/** The route grouped into streams, preserving order within each. */
function bj_route_by_stream(PDO $pdo, int $productId): array
{
    $out = [];
    foreach (bj_route_steps($pdo, $productId) as $s) $out[(string) $s['stream']][] = $s;
    return $out;
}

/**
 * A product's stream NAMES in a stable order (earliest stage first). This is
 * what the single stream-digit on a label maps to: digit 0 = the whole blind,
 * digit N = the Nth stream here. So a vertical's cutting label carries digit 1
 * (Headrail) and its fabric label digit 2 (Fabric), and each scan completes
 * exactly that part.
 */
function bj_streams_ordered(PDO $pdo, int $productId): array
{
    static $cache = [];
    if (isset($cache[$productId])) return $cache[$productId];
    $names = [];
    foreach (bj_route_by_stream($pdo, $productId) as $name => $_steps) $names[] = (string) $name;
    return $cache[$productId] = $names;
}

/**
 * Complete a blind's part from a scanned code — the whole point of the WiFi
 * scan-in path. No login: the code says everything.
 *
 * streamDigit 0 = the whole blind (single-stream products: roller, pleated).
 * N = the Nth stream (a vertical's headrail or fabric).
 *
 * PER-STAGE: one scan finishes the CURRENT stage and moves the stream to its
 * next stage — the label travels with the blind and is scanned at each station.
 * A stream is done only when its last stage is scanned; the blind is made only
 * when every stream is done. A double pull can't skip a stage — the 10s de-dupe
 * in scan-in.php swallows it.
 *
 * @return array{ok:bool, title:string, detail:string, already?:bool}
 */
function bj_complete_by_code(PDO $pdo, int $itemId, int $unitNo, int $streamDigit, ?int $userId): array
{
    $q = $pdo->prepare(
        'SELECT bj.id, bj.status, bj.product_id, qi.product_name_snapshot, qi.width_mm, qi.drop_mm,
                qi.quantity, qi.room_name, qi.line_no, q.quote_number
           FROM factory_blind_jobs bj
           JOIN quote_items qi ON qi.id = bj.quote_item_id
           JOIN quotes q       ON q.id = bj.quote_id
          WHERE bj.quote_item_id = ? AND bj.unit_no = ? LIMIT 1'
    );
    $q->execute([$itemId, $unitNo]);
    $job = $q->fetch(PDO::FETCH_ASSOC);
    if (!$job) return ['ok' => false, 'title' => 'Not on the floor', 'detail' => 'Blind not released to production.'];

    $ref = (string) $job['quote_number'] . '-' . (int) $job['line_no'] . '-(' . $unitNo . '/' . max(1, (int) $job['quantity']) . ')';
    $streams = bj_streams_for($pdo, [(int) $job['id']])[(int) $job['id']] ?? [];

    // Which stream rows does this scan finish?
    $targets = [];
    if ($streamDigit === 0) {
        $targets = $streams;                              // whole blind
    } else {
        $ordered = bj_streams_ordered($pdo, (int) $job['product_id']);
        $name = $ordered[$streamDigit - 1] ?? null;
        if ($name !== null && isset($streams[$name])) $targets = [$name => $streams[$name]];
    }
    if (!$targets) return ['ok' => false, 'title' => 'Nothing to finish', 'detail' => $ref . ' — that part isn\'t on this blind.'];

    $what = trim((string) $job['product_name_snapshot'] . '  ' . (int) $job['width_mm'] . '×' . (int) $job['drop_mm']);

    // Advance each target stream ONE stage. $moved records the first (in normal
    // use the only) transition, for the line the scanner reads back.
    $didAny = false;
    $moved  = null;   // [stream, fromStage, toStage, streamFinished]
    foreach ($targets as $streamName => $sr) {
        if ($sr['status'] === 'done') continue;
        $list = bj_route_by_stream($pdo, (int) $job['product_id'])[(string) $streamName] ?? [];
        $from = bj_step_label($list, $sr['route_step_id'] !== null ? (int) $sr['route_step_id'] : null);

        bj_stream_advance($pdo, (int) $sr['id'], $userId);
        $didAny = true;

        $after     = bj_stream_get($pdo, (int) $sr['id']);
        $finished  = !$after || $after['status'] === 'done' || $after['route_step_id'] === null;
        $to        = $finished ? '' : bj_step_label($list, (int) $after['route_step_id']);
        if ($moved === null) $moved = [(string) $streamName, $from, $to, $finished];
    }

    if (!$didAny) {
        return ['ok' => true, 'already' => true, 'title' => $ref, 'detail' => 'already finished · ' . $what];
    }

    // Made only once every stream of this blind is done.
    $fresh   = bj_streams_for($pdo, [(int) $job['id']])[(int) $job['id']] ?? [];
    $allDone = $fresh !== [] && count(array_filter($fresh, static fn ($s) => $s['status'] !== 'done')) === 0;

    [$sName, $fromLabel, $toLabel, $streamFinished] = $moved;
    $part = $streamDigit === 0 ? '' : $sName;   // 'main' means nothing to an operator

    if ($allDone) {
        $detail = 'made · ' . $what;
    } elseif ($streamFinished) {
        $detail = ($part !== '' ? $part . ' finished' : 'part finished') . ' · ' . $what;
    } else {
        $detail = ($fromLabel !== '' ? $fromLabel . ' done' : 'stage done')
                . ($toLabel !== '' ? ' → next: ' . $toLabel : '')
                . ' · ' . $what;
    }
    return ['ok' => true, 'title' => $ref, 'detail' => $detail];
}

/** A route step's display label (its own label, else the station name), or ''. */
function bj_step_label(array $list, ?int $stepId): string
{
    if ($stepId === null) return '';
    foreach ($list as $s) {
        if ((int) $s['id'] === $stepId) {
            $l = trim((string) ($s['label'] ?? ''));
            return $l !== '' ? $l : trim((string) ($s['station'] ?? ''));
        }
    }
    return '';
}

/**
 * Release an order's Beverley blinds onto the floor: one blind per physical unit
 * (a qty-3 line is 3), and one open position per stream on its route.
 *
 * Idempotent on (line, unit) and on (blind, stream), so re-releasing only fills
 * in what's missing. Returns how many NEW blinds were created.
 *
 * The line points at the TENANT's pushed copy of the product, but routes are
 * defined once on Beverley's master product — so source_product_id maps the copy
 * back and the job stores the MASTER product id.
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

    $insJob = $pdo->prepare(
        "INSERT IGNORE INTO factory_blind_jobs
             (quote_id, quote_item_id, unit_no, product_id, status)
         VALUES (?, ?, ?, ?, 'queued')"
    );
    $findJob = $pdo->prepare('SELECT id FROM factory_blind_jobs WHERE quote_item_id = ? AND unit_no = ? LIMIT 1');
    $insStream = $pdo->prepare(
        "INSERT IGNORE INTO factory_blind_streams
             (blind_job_id, stream, route_step_id, station_id, seq, status, step_started_at)
         VALUES (?, ?, ?, ?, ?, 'queued', NOW())"
    );

    $created = 0;
    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $it) {
        $pid   = (int) $it['master_product_id'];
        $byStr = bj_route_by_stream($pdo, $pid);
        $qty   = max(1, (int) $it['quantity']);

        for ($unit = 1; $unit <= $qty; $unit++) {
            $insJob->execute([$quoteId, (int) $it['id'], $unit, $pid]);
            $isNew = $insJob->rowCount() > 0;
            if ($isNew) $created++;

            // lastInsertId is unreliable after an ignored insert — always look it up.
            $findJob->execute([(int) $it['id'], $unit]);
            $jobId = (int) $findJob->fetchColumn();
            if ($jobId === 0) continue;

            if (!$byStr) {   // product has no route yet — unrouted, one open stream
                $insStream->execute([$jobId, 'main', null, null, 0]);
                continue;
            }
            foreach ($byStr as $stream => $list) {
                $f = $list[0];
                $insStream->execute([$jobId, (string) $stream, (int) $f['id'], (int) $f['station_id'], (int) $f['seq']]);
            }
        }
    }
    return $created;
}

/** Remove an order's blinds from the floor (used when it's reset to "new"). */
function bj_clear_order(PDO $pdo, int $quoteId): void
{
    $pdo->prepare(
        'DELETE s FROM factory_blind_streams s
           JOIN factory_blind_jobs j ON j.id = s.blind_job_id
          WHERE j.quote_id = ?'
    )->execute([$quoteId]);
    $pdo->prepare('DELETE FROM factory_blind_jobs WHERE quote_id = ?')->execute([$quoteId]);
}

/** One stream row (a blind's position in one stream), or null. */
function bj_stream_get(PDO $pdo, int $streamId): ?array
{
    $st = $pdo->prepare(
        'SELECT s.*, j.product_id, j.quote_id
           FROM factory_blind_streams s JOIN factory_blind_jobs j ON j.id = s.blind_job_id
          WHERE s.id = ? LIMIT 1'
    );
    $st->execute([$streamId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Every stream position for a set of blinds: [blind_job_id => [stream => row]]. */
function bj_streams_for(PDO $pdo, array $jobIds): array
{
    $jobIds = array_values(array_filter(array_map('intval', $jobIds)));
    if (!$jobIds) return [];
    $ph = implode(',', array_fill(0, count($jobIds), '?'));
    $st = $pdo->prepare("SELECT * FROM factory_blind_streams WHERE blind_job_id IN ($ph)");
    $st->execute($jobIds);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['blind_job_id']][(string) $r['stream']] = $r;
    }
    return $out;
}

/**
 * How far through is a blind: [done, total] stages, counted across every stream.
 * Within a stream, the stages before its position are genuinely finished —
 * that work is sequential. Nothing is inferred between streams.
 */
function bj_progress(array $routeByStream, array $streamRows): array
{
    $done = 0; $total = 0;
    foreach ($routeByStream as $stream => $list) {
        $total += count($list);
        $row = $streamRows[$stream] ?? null;
        if (!$row) continue;
        if ($row['status'] === 'done' || $row['route_step_id'] === null) { $done += count($list); continue; }
        foreach ($list as $i => $s) {
            if ((int) $s['id'] === (int) $row['route_step_id']) { $done += $i; break; }
        }
    }
    return [$done, $total];
}

/** Mark the stage a stream is sitting on as being worked on right now. */
function bj_stream_start(PDO $pdo, int $streamId, ?int $userId): void
{
    $pdo->prepare(
        "UPDATE factory_blind_streams
            SET status = 'in_progress',
                started_at      = COALESCE(started_at, NOW()),
                step_started_at = COALESCE(step_started_at, NOW()),
                updated_by = ?
          WHERE id = ? AND status = 'queued'"
    )->execute([$userId, $streamId]);
    $s = bj_stream_get($pdo, $streamId);
    if ($s) bj_recompute_job($pdo, (int) $s['blind_job_id']);
}

/**
 * Finish this stream's current stage and move to the next one IN THE SAME
 * STREAM. When a stream runs out of stages it's done; the blind is made once
 * every one of its streams is.
 */
function bj_stream_advance(PDO $pdo, int $streamId, ?int $userId): void
{
    $row = bj_stream_get($pdo, $streamId);
    if (!$row || $row['status'] === 'done') return;

    $list = bj_route_by_stream($pdo, (int) $row['product_id'])[(string) $row['stream']] ?? [];
    $idx = null;
    foreach ($list as $i => $s) {
        if ((int) $s['id'] === (int) $row['route_step_id']) { $idx = $i; break; }
    }

    $next = null;
    if ($idx !== null) {
        $next = $list[$idx + 1] ?? null;
    } else {
        // Current step was removed from the route mid-run: pick up at the next
        // live stage in this stream after where we were.
        foreach ($list as $s) {
            if ((int) $s['seq'] > (int) $row['seq']) { $next = $s; break; }
        }
    }

    if ($next) {
        $pdo->prepare(
            "UPDATE factory_blind_streams
                SET route_step_id = ?, station_id = ?, seq = ?, status = 'queued',
                    step_started_at = NOW(), started_at = COALESCE(started_at, NOW()), updated_by = ?
              WHERE id = ?"
        )->execute([(int) $next['id'], (int) $next['station_id'], (int) $next['seq'], $userId, $streamId]);
    } else {
        $pdo->prepare(
            "UPDATE factory_blind_streams
                SET route_step_id = NULL, station_id = NULL, status = 'done',
                    completed_at = NOW(), started_at = COALESCE(started_at, NOW()), updated_by = ?
              WHERE id = ?"
        )->execute([$userId, $streamId]);
    }
    bj_recompute_job($pdo, (int) $row['blind_job_id']);
}

/** Step one stream back a stage (undo a premature "done"). */
function bj_stream_back(PDO $pdo, int $streamId, ?int $userId): void
{
    $row = bj_stream_get($pdo, $streamId);
    if (!$row) return;
    $list = bj_route_by_stream($pdo, (int) $row['product_id'])[(string) $row['stream']] ?? [];
    if (!$list) return;

    $prev = null;
    if ($row['status'] === 'done' || $row['route_step_id'] === null) {
        $prev = $list[count($list) - 1];               // back onto the last stage
    } else {
        $idx = null;
        foreach ($list as $i => $s) {
            if ((int) $s['id'] === (int) $row['route_step_id']) { $idx = $i; break; }
        }
        if ($idx !== null) {
            $prev = $list[$idx - 1] ?? null;           // already at stage 1 -> nothing before it
        } else {
            for ($i = count($list) - 1; $i >= 0; $i--) {
                if ((int) $list[$i]['seq'] < (int) $row['seq']) { $prev = $list[$i]; break; }
            }
        }
    }
    if (!$prev) return;

    $pdo->prepare(
        "UPDATE factory_blind_streams
            SET route_step_id = ?, station_id = ?, seq = ?, status = 'queued',
                completed_at = NULL, step_started_at = NOW(), updated_by = ?
          WHERE id = ?"
    )->execute([(int) $prev['id'], (int) $prev['station_id'], (int) $prev['seq'], $userId, $streamId]);
    bj_recompute_job($pdo, (int) $row['blind_job_id']);
}

/**
 * Jump a stream straight to any stage on its own list — what clicking a chip on
 * the floor strip does. $stepId null means "this stream is finished". A step
 * from another stream (or another product) is ignored.
 */
function bj_stream_set_stage(PDO $pdo, int $streamId, ?int $stepId, ?int $userId): void
{
    $row = bj_stream_get($pdo, $streamId);
    if (!$row) return;

    if ($stepId === null) {
        $pdo->prepare(
            "UPDATE factory_blind_streams
                SET route_step_id = NULL, station_id = NULL, status = 'done',
                    completed_at = NOW(), started_at = COALESCE(started_at, NOW()), updated_by = ?
              WHERE id = ?"
        )->execute([$userId, $streamId]);
        bj_recompute_job($pdo, (int) $row['blind_job_id']);
        return;
    }

    $target = null;
    foreach (bj_route_by_stream($pdo, (int) $row['product_id'])[(string) $row['stream']] ?? [] as $s) {
        if ((int) $s['id'] === $stepId) { $target = $s; break; }
    }
    if (!$target) return;

    $pdo->prepare(
        "UPDATE factory_blind_streams
            SET route_step_id = ?, station_id = ?, seq = ?, status = 'queued',
                completed_at = NULL, step_started_at = NOW(),
                started_at = COALESCE(started_at, NOW()), updated_by = ?
          WHERE id = ?"
    )->execute([(int) $target['id'], (int) $target['station_id'], (int) $target['seq'], $userId, $streamId]);
    bj_recompute_job($pdo, (int) $row['blind_job_id']);
}

/**
 * Roll a blind's overall status up from its streams: made only when every one of
 * them has run out of stages. Then nudge the order if that was the last blind.
 */
function bj_recompute_job(PDO $pdo, int $jobId): void
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status = 'done')        AS done_cnt,
                SUM(status = 'in_progress') AS working_cnt
           FROM factory_blind_streams WHERE blind_job_id = ?"
    );
    $st->execute([$jobId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'done_cnt' => 0, 'working_cnt' => 0];

    $total = (int) $r['total'];
    $allDone = $total > 0 && $total === (int) $r['done_cnt'];
    $status  = $allDone ? 'complete' : (((int) $r['working_cnt'] > 0) ? 'in_progress' : 'queued');

    $pdo->prepare(
        'UPDATE factory_blind_jobs
            SET status = ?, completed_at = ' . ($allDone ? 'COALESCE(completed_at, NOW())' : 'NULL') . '
          WHERE id = ?'
    )->execute([$status, $jobId]);

    if ($allDone) {
        $q = $pdo->prepare('SELECT quote_id FROM factory_blind_jobs WHERE id = ?');
        $q->execute([$jobId]);
        $quoteId = (int) $q->fetchColumn();
        if ($quoteId > 0) bj_maybe_complete_order($pdo, $quoteId);
    }
}

/**
 * When every blind on an order has finished all its streams, nudge the order to
 * "made" — but only from "in_production", so a manual dispatch or a step-back
 * isn't clobbered.
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
 * Per-order floor progress for a set of quote ids: [quoteId => [total, done]],
 * counted in whole blinds. Used by Incoming Orders for the "X of Y made" pill.
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
