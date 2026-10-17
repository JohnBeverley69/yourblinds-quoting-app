<?php
declare(strict_types=1);

/**
 * "Has anything changed?" for the factory screens.
 *
 * These pages sit open on a wall or a bench all day, so waiting to be refreshed
 * by hand means an order can land and nobody knows. But they also carry buttons
 * that DO things — "Start production" — so the page must never reload itself
 * under someone's hand and land a click on the wrong row. Hence a version
 * string, polled quietly, and an offer to refresh rather than a forced one.
 *
 * The version is deliberately cheap: counts and max timestamps, no row data.
 */

/**
 * A short string that changes whenever the given view has something new.
 * $what: 'incoming' | 'floor' | 'pipeline' (incoming + floor)
 */
function fx_poll_version(PDO $pdo, string $what, int $master): string
{
    try {
        if ($what === 'floor') {
            // A blind moving, being released, or finishing — wherever it was scanned.
            // Scoped to THIS factory (owning factory of the blind's product), so one
            // factory's board doesn't flash "refresh" on another factory's activity.
            $fs = $pdo->prepare(
                "SELECT COUNT(*) AS n, COALESCE(MAX(UNIX_TIMESTAMP(s.updated_at)),0) AS t
                   FROM factory_blind_streams s
                   JOIN factory_blind_jobs j ON j.id = s.blind_job_id
                   JOIN products p           ON p.id = j.product_id
                  WHERE COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?"
            );
            $fs->execute([$master]);
            $r = $fs->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 't' => 0];
            return 'f' . (int) $r['n'] . '.' . (int) $r['t'];
        }

        // incoming: a new placed order, or one whose factory status moved.
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT q.id) AS n, COALESCE(MAX(q.id),0) AS mx
               FROM quotes q
               JOIN quote_items qi ON qi.quote_id = q.id
               JOIN products p     ON p.id = qi.product_id
              WHERE q.status IN ('ordered','fitted','invoiced','paid')
                AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?"
        );
        $st->execute([$master]);
        $a = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'mx' => 0];

        // A status move on one of THIS factory's orders. It used to read the
        // newest factory_jobs row platform-wide, so another factory's click
        // refreshed this one's screens (John's per-client rule, 2026-10-12).
        $t = 0;
        try {
            $fj = $pdo->prepare(
                "SELECT COALESCE(MAX(UNIX_TIMESTAMP(fj.status_at)),0) FROM factory_jobs fj
                  WHERE fj.factory_client_id = ?"
            );
            $fj->execute([$master]);
            $t = (int) $fj->fetchColumn();
        } catch (Throwable $e) { /* factory_jobs optional */ }

        // A supplier order sent or received by this factory.
        $so = 0;
        try {
            $s = $pdo->prepare(
                'SELECT COALESCE(MAX(UNIX_TIMESTAMP(GREATEST(sent_at, COALESCE(received_at, sent_at)))),0)
                   FROM supplier_orders WHERE ordered_by_factory_id = ?'
            );
            $s->execute([$master]);
            $so = (int) $s->fetchColumn();
        } catch (Throwable $e) { /* supplier ordering optional */ }

        $inc = 'i' . (int) $a['n'] . '.' . (int) $a['mx'] . '.' . $t . '.' . $so;
        // 'pipeline': the cards also show floor progress, so a scan moves them too.
        return $what === 'pipeline' ? $inc . '~' . fx_poll_version($pdo, 'floor', $master) : $inc;
    } catch (Throwable $e) {
        return 'x';   // can't tell — never claim there's news
    }
}
