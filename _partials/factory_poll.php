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
 * $what: 'incoming' | 'floor'
 */
function fx_poll_version(PDO $pdo, string $what, int $master): string
{
    try {
        if ($what === 'floor') {
            // A blind moving, being released, or finishing — wherever it was scanned.
            $r = $pdo->query(
                "SELECT COUNT(*) AS n, COALESCE(MAX(UNIX_TIMESTAMP(updated_at)),0) AS t
                   FROM factory_blind_streams"
            )->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 't' => 0];
            return 'f' . (int) $r['n'] . '.' . (int) $r['t'];
        }

        // incoming: a new placed order, or one whose factory status moved.
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT q.id) AS n, COALESCE(MAX(q.id),0) AS mx
               FROM quotes q
               JOIN quote_items qi ON qi.quote_id = q.id
               JOIN products p     ON p.id = qi.product_id
              WHERE q.status IN ('ordered','fitted','invoiced','paid')
                AND p.source_client_id = ?"
        );
        $st->execute([$master]);
        $a = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'mx' => 0];

        $t = 0;
        try {
            $t = (int) $pdo->query("SELECT COALESCE(MAX(UNIX_TIMESTAMP(status_at)),0) FROM factory_jobs")->fetchColumn();
        } catch (Throwable $e) { /* factory_jobs optional */ }

        return 'i' . (int) $a['n'] . '.' . (int) $a['mx'] . '.' . $t;
    } catch (Throwable $e) {
        return 'x';   // can't tell — never claim there's news
    }
}
