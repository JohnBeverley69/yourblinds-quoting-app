<?php
declare(strict_types=1);

/**
 * Double-booking guard for the calendar.
 *
 * A salesperson / fitter can't be in two places at once, so before a booking
 * is saved (new appointment, edit, or drag-reschedule) we check whether the
 * assigned user already has an appointment whose time window overlaps the
 * proposed one on the same day.
 *
 * Unassigned appointments never clash — "two places at once" only means
 * anything for a specific person. Cancelled / no-show appointments free their
 * slot, so they're ignored.
 */

if (!function_exists('appointment_find_conflict')) {

    /**
     * Returns the clashing appointment row (id, title, customer_name,
     * appointment_time, duration_minutes) or null if the slot is free.
     *
     * Overlap test: existing.start < new.end AND existing.end > new.start.
     */
    function appointment_find_conflict(
        PDO    $pdo,
        int    $clientId,
        ?int   $userId,
        string $date,
        string $startTime,
        int    $durationMin,
        ?int   $excludeApptId = null
    ): ?array {
        if ($userId === null || $userId <= 0) return null;
        if ($durationMin <= 0) $durationMin = 60;

        $sql = "SELECT a.id, a.title, a.appointment_time,
                       COALESCE(a.duration_minutes, 60) AS duration_minutes,
                       c.name AS customer_name
                  FROM appointments a
             LEFT JOIN customers c ON c.id = a.customer_id
                 WHERE a.client_id        = ?
                   AND a.client_user_id   = ?
                   AND a.appointment_date = ?
                   AND a.status NOT IN ('cancelled', 'no_show')
                   AND a.appointment_time < ADDTIME(?, SEC_TO_TIME(? * 60))
                   AND ADDTIME(a.appointment_time, SEC_TO_TIME(COALESCE(a.duration_minutes, 60) * 60)) > ?";
        $params = [$clientId, $userId, $date, $startTime, $durationMin, $startTime];
        if ($excludeApptId !== null && $excludeApptId > 0) {
            $sql      .= ' AND a.id <> ?';
            $params[]  = $excludeApptId;
        }
        $sql .= ' ORDER BY a.appointment_time LIMIT 1';

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Friendly, specific message for a clash. */
    function appointment_conflict_message(array $clash, string $assigneeName): string
    {
        $start  = substr((string) $clash['appointment_time'], 0, 5);
        $endTs  = strtotime((string) $clash['appointment_time']) + ((int) $clash['duration_minutes'] * 60);
        $end    = $endTs ? date('H:i', $endTs) : '';
        $who    = trim($assigneeName) !== '' ? trim($assigneeName) : 'That person';
        $with   = trim((string) ($clash['customer_name'] ?? ''));
        $window = $end !== '' ? "$start–$end" : $start;
        return "$who is already booked $window" . ($with !== '' ? " ($with)" : '')
             . " that day — they can't be in two places at once. "
             . 'Pick another time, assignee, or day.';
    }
}
