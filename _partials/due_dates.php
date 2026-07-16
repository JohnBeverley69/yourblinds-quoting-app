<?php
declare(strict_types=1);

/**
 * Production times + order due dates.
 *
 * THE RULE: a due date is stamped ONCE, when the order is placed, from the lead
 * times in force at that moment — and is never recomputed. Turning a production
 * time up because the workshop is swamped must not move the promised date on
 * work already taken; it only affects orders placed after the edit. Every write
 * path here either stamps a NULL due_date or is an explicit human override.
 *
 * Lead times are per master product and counted in WORKING days (Mon-Fri).
 * NOTE: bank holidays are not skipped yet — see dd_add_working_days().
 *
 * Pure function library — safe to require anywhere after bootstrap.
 */

if (!defined('DD_DEFAULT_LEAD_DAYS')) {
    define('DD_DEFAULT_LEAD_DAYS', 10);   // used for a product with no time set
}

/** True once /migrate_factory_due_dates.php has run. Cached per request. */
function dd_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $pdo->query('SELECT due_date FROM quotes LIMIT 0');
        $pdo->query('SELECT 1 FROM product_lead_times LIMIT 0');
        return $ready = true;
    } catch (Throwable $e) { return $ready = false; }
}

/** A master product's production time in working days. */
function dd_lead_days(PDO $pdo, int $masterProductId): int
{
    static $cache = [];
    if (isset($cache[$masterProductId])) return $cache[$masterProductId];
    try {
        $st = $pdo->prepare('SELECT lead_days FROM product_lead_times WHERE product_id = ?');
        $st->execute([$masterProductId]);
        $v = $st->fetchColumn();
    } catch (Throwable $e) { $v = false; }
    return $cache[$masterProductId] = ($v === false || $v === null) ? DD_DEFAULT_LEAD_DAYS : max(0, (int) $v);
}

/** Set a master product's production time. Applies to orders placed from now on. */
function dd_set_lead_days(PDO $pdo, int $masterProductId, int $days): void
{
    $days = max(0, min(365, $days));
    $pdo->prepare(
        'INSERT INTO product_lead_times (product_id, lead_days) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE lead_days = VALUES(lead_days)'
    )->execute([$masterProductId, $days]);
}

/**
 * Add N working days (Mon-Fri) to a date. 0 days = the same day.
 *
 * Bank holidays are NOT skipped — the workshop's closures aren't recorded
 * anywhere yet. Add a shutdown-dates list here when they are.
 */
function dd_add_working_days(DateTimeImmutable $from, int $days): DateTimeImmutable
{
    $d = $from;
    for ($added = 0; $added < $days; ) {
        $d = $d->modify('+1 day');
        if ((int) $d->format('N') <= 5) $added++;   // 1=Mon .. 7=Sun
    }
    return $d;
}

/**
 * The production time for a whole order: the SLOWEST of its Beverley lines,
 * since the order ships when the last blind is made. Null if it carries no
 * Beverley lines at all (not ours to promise a date for).
 */
function dd_order_lead_days(PDO $pdo, int $quoteId, int $master): ?int
{
    $st = $pdo->prepare(
        'SELECT DISTINCT COALESCE(p.source_product_id, p.id) AS master_product_id
           FROM quote_items qi JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ? AND p.source_client_id = ?'
    );
    $st->execute([$quoteId, $master]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return null;

    $max = 0;
    foreach ($ids as $id) $max = max($max, dd_lead_days($pdo, (int) $id));
    return $max;
}

/**
 * Stamp an order's due date at placement. Idempotent and one-way: if a date is
 * already there it is left ALONE, so re-placing, rewinding, or a later change
 * to the production times can never move it.
 *
 * Returns the date stamped (Y-m-d), or null if nothing was written.
 */
function dd_stamp_order(PDO $pdo, int $quoteId, int $master): ?string
{
    if (!dd_ready($pdo)) return null;

    $st = $pdo->prepare('SELECT due_date FROM quotes WHERE id = ? LIMIT 1');
    $st->execute([$quoteId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !empty($row['due_date'])) return null;   // never re-stamp

    $lead = dd_order_lead_days($pdo, $quoteId, $master);
    if ($lead === null) return null;

    $due = dd_add_working_days(new DateTimeImmutable('today'), $lead)->format('Y-m-d');
    $pdo->prepare('UPDATE quotes SET due_date = ? WHERE id = ? AND due_date IS NULL')
        ->execute([$due, $quoteId]);
    return $due;
}

/** Human override of one order's due date. '' clears it back to none. */
function dd_set_due(PDO $pdo, int $quoteId, string $date): void
{
    $date = trim($date);
    if ($date === '') {
        $pdo->prepare('UPDATE quotes SET due_date = NULL WHERE id = ?')->execute([$quoteId]);
        return;
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) return;   // ignore junk
    $pdo->prepare('UPDATE quotes SET due_date = ? WHERE id = ?')->execute([$date, $quoteId]);
}
