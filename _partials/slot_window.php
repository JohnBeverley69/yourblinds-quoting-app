<?php
declare(strict_types=1);

/**
 * AM/PM booking-slot helpers (feature_ampm_slots — see migrate_ampm_slots.php).
 *
 * A quote (measure) visit can be booked into a half-day WINDOW instead of a
 * clock time: Morning (9am-1pm) or Afternoon (1pm-5pm). The window is stored
 * canonically as appointment_time + duration_minutes so every existing calendar
 * view keeps working, plus a slot_window marker ('am' | 'pm') so renderers can
 * show the window label and capacity can be counted per window per day.
 *
 * Capacity is counted purely on slot_window, which is only ever set on
 * slot-booked measure visits — so counting never depends on the optional
 * appt_kind column and never includes fittings.
 *
 * Pure functions; safe to require more than once.
 */

if (!function_exists('ampm_windows')) {
    /** Canonical window definitions: start time, duration, and labels. */
    function ampm_windows(): array
    {
        return [
            'am' => ['time' => '09:00:00', 'duration' => 240, 'label' => 'Morning',   'range' => '9am–1pm'],
            'pm' => ['time' => '13:00:00', 'duration' => 240, 'label' => 'Afternoon', 'range' => '1pm–5pm'],
        ];
    }
}

if (!function_exists('is_ampm_window')) {
    /** True if $w is a recognised window key ('am' | 'pm'). */
    function is_ampm_window(?string $w): bool
    {
        return $w === 'am' || $w === 'pm';
    }
}

if (!function_exists('ampm_window_label')) {
    /**
     * Customer/staff-facing window label, e.g. "Morning (9am–1pm)".
     * Returns '' for an unrecognised window so callers can fall back to a time.
     */
    function ampm_window_label(?string $w): string
    {
        $win = ampm_windows()[$w] ?? null;
        return $win ? "{$win['label']} ({$win['range']})" : '';
    }
}

if (!function_exists('slot_window_short_label')) {
    /**
     * Compact window label for calendar cards, e.g. "Morning" / "Afternoon".
     * Returns '' for an unrecognised window so callers fall back to the time.
     */
    function slot_window_short_label(?string $w): string
    {
        $win = ampm_windows()[$w] ?? null;
        return $win ? $win['label'] : '';
    }
}

if (!function_exists('ampm_settings')) {
    /**
     * Read the tenant's slot settings, guarded so a tenant that hasn't run
     * migrate_ampm_slots.php simply gets the feature off (never a 500).
     * Returns ['on' => bool, 'capacity' => int].
     */
    function ampm_settings(PDO $pdo, int $clientId): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT COALESCE(feature_ampm_slots, 0)  AS on_flag,
                        COALESCE(ampm_slot_capacity, 4)  AS capacity
                   FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $st->execute([$clientId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return ['on' => false, 'capacity' => 4];
        }
        $cap = (int) ($row['capacity'] ?? 4);
        return [
            'on'       => (int) ($row['on_flag'] ?? 0) === 1,
            'capacity' => $cap >= 1 ? $cap : 4,
        ];
    }
}

if (!function_exists('ampm_window_count')) {
    /**
     * How many bookings a window already holds on a given date, for this tenant.
     * Counts only slot-booked visits (slot_window set) and ignores cancelled /
     * no-show. Pass $excludeId when editing so an appointment doesn't count
     * against its own window.
     */
    function ampm_window_count(PDO $pdo, int $clientId, string $date, string $window, int $excludeId = 0): int
    {
        $sql = "SELECT COUNT(*) FROM appointments
                 WHERE client_id = ? AND appointment_date = ? AND slot_window = ?
                   AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))";
        $params = [$clientId, $date, $window];
        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }
}

if (!function_exists('ampm_availability')) {
    /**
     * Remaining capacity for both windows on a date.
     * Returns ['am' => ['taken'=>int,'remaining'=>int,'full'=>bool], 'pm' => [...]].
     */
    function ampm_availability(PDO $pdo, int $clientId, string $date, int $capacity, int $excludeId = 0): array
    {
        $out = [];
        foreach (array_keys(ampm_windows()) as $w) {
            $taken = ampm_window_count($pdo, $clientId, $date, $w, $excludeId);
            $rem   = $capacity - $taken;
            if ($rem < 0) $rem = 0;
            $out[$w] = ['taken' => $taken, 'remaining' => $rem, 'full' => $rem <= 0];
        }
        return $out;
    }
}
