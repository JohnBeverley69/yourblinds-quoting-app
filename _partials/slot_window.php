<?php
declare(strict_types=1);

/**
 * AM/PM booking-slot helpers (feature_ampm_slots — see migrate_ampm_slots.php
 * and migrate_ampm_window_config.php).
 *
 * A quote (measure) visit can be booked into a half-day WINDOW instead of a
 * clock time: a Morning and an Afternoon window. The window START time and
 * LENGTH, and the per-window daily CAPACITY, are configurable per tenant
 * (Settings → Calendar). Defaults: Morning 9am-1pm, Afternoon 1pm-5pm, 4 each.
 *
 * The window is stored canonically as appointment_time + duration_minutes so
 * every existing calendar view keeps working, plus a slot_window marker
 * ('am' | 'pm') so renderers can show the label and capacity is counted per
 * window per day. Capacity counts purely on slot_window (only ever set on
 * slot-booked measure visits) so it never includes fittings.
 *
 * Pure-ish functions; safe to require more than once.
 */

if (!function_exists('ampm_fmt_time')) {
    /** "09:00:00" -> "9am"; "13:30:00" -> "1:30pm". */
    function ampm_fmt_time(string $t): string
    {
        $ts = strtotime('1970-01-01 ' . $t);
        if ($ts === false) return $t;
        return date(date('i', $ts) === '00' ? 'ga' : 'g:ia', $ts);
    }
}

if (!function_exists('ampm_range_str')) {
    /** "9am–1pm" from a start and end time. */
    function ampm_range_str(string $start, string $end): string
    {
        return ampm_fmt_time($start) . '–' . ampm_fmt_time($end);
    }
}

if (!function_exists('ampm_one_window')) {
    /** Build one window def from a start + end time and a label. */
    function ampm_one_window(string $start, string $end, string $label): array
    {
        $ss  = strtotime('1970-01-01 ' . $start);
        $ee  = strtotime('1970-01-01 ' . $end);
        $dur = ($ss !== false && $ee !== false) ? (int) round(($ee - $ss) / 60) : 240;
        if ($dur < 15) $dur = 15;   // guard against a zero/negative range
        return [
            'time'     => substr($start, 0, 8) ?: $start,
            'duration' => $dur,
            'label'    => $label,
            'range'    => ampm_range_str($start, $end),
        ];
    }
}

if (!function_exists('ampm_windows_default')) {
    /** The out-of-the-box windows (used as a fallback pre-migration / no tenant). */
    function ampm_windows_default(): array
    {
        return [
            'am' => ampm_one_window('09:00:00', '13:00:00', 'Morning'),
            'pm' => ampm_one_window('13:00:00', '17:00:00', 'Afternoon'),
        ];
    }
}

if (!function_exists('ampm_build_windows')) {
    /** Build the window defs from a tenant settings array (see ampm_settings). */
    function ampm_build_windows(array $s): array
    {
        return [
            'am' => ampm_one_window((string) ($s['am_start'] ?? '09:00:00'), (string) ($s['am_end'] ?? '13:00:00'), 'Morning'),
            'pm' => ampm_one_window((string) ($s['pm_start'] ?? '13:00:00'), (string) ($s['pm_end'] ?? '17:00:00'), 'Afternoon'),
        ];
    }
}

if (!function_exists('ampm_settings')) {
    /**
     * Read the tenant's slot settings, guarded so a tenant that hasn't run the
     * migrations simply gets the feature off / the defaults (never a 500).
     * Returns: on(bool), capacity(int legacy = morning), am_capacity, pm_capacity,
     *          am_start, am_end, pm_start, pm_end.
     */
    function ampm_settings(PDO $pdo, int $clientId): array
    {
        static $cache = [];
        if (isset($cache[$clientId])) return $cache[$clientId];

        $def = [
            'on' => false, 'capacity' => 4, 'am_capacity' => 4, 'pm_capacity' => 4,
            'am_start' => '09:00:00', 'am_end' => '13:00:00',
            'pm_start' => '13:00:00', 'pm_end' => '17:00:00',
        ];

        // Full read (post window-config migration).
        try {
            $st = $pdo->prepare(
                'SELECT COALESCE(feature_ampm_slots, 0)                        AS on_flag,
                        COALESCE(ampm_slot_capacity, 4)                        AS cap,
                        COALESCE(ampm_am_capacity, ampm_slot_capacity, 4)      AS am_cap,
                        COALESCE(ampm_pm_capacity, ampm_slot_capacity, 4)      AS pm_cap,
                        COALESCE(ampm_am_start, \'09:00:00\')                    AS am_s,
                        COALESCE(ampm_am_end,   \'13:00:00\')                    AS am_e,
                        COALESCE(ampm_pm_start, \'13:00:00\')                    AS pm_s,
                        COALESCE(ampm_pm_end,   \'17:00:00\')                    AS pm_e
                   FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $st->execute([$clientId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                return $cache[$clientId] = [
                    'on'          => (int) $r['on_flag'] === 1,
                    'capacity'    => max(1, (int) $r['am_cap']),
                    'am_capacity' => max(1, (int) $r['am_cap']),
                    'pm_capacity' => max(1, (int) $r['pm_cap']),
                    'am_start'    => (string) $r['am_s'], 'am_end' => (string) $r['am_e'],
                    'pm_start'    => (string) $r['pm_s'], 'pm_end' => (string) $r['pm_e'],
                ];
            }
            return $cache[$clientId] = $def;
        } catch (Throwable $e) {
            // Pre-window-config migration: only feature + single capacity exist.
            try {
                $st = $pdo->prepare(
                    'SELECT COALESCE(feature_ampm_slots, 0) AS on_flag,
                            COALESCE(ampm_slot_capacity, 4) AS cap
                       FROM client_settings WHERE client_id = ? LIMIT 1'
                );
                $st->execute([$clientId]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if ($r) {
                    $cap = max(1, (int) $r['cap']);
                    return $cache[$clientId] = array_merge($def, [
                        'on' => (int) $r['on_flag'] === 1,
                        'capacity' => $cap, 'am_capacity' => $cap, 'pm_capacity' => $cap,
                    ]);
                }
            } catch (Throwable $e2) { /* column not migrated at all — feature off */ }
            return $cache[$clientId] = $def;
        }
    }
}

if (!function_exists('ampm_windows_for')) {
    /** The window defs for a specific tenant (cached per request). */
    function ampm_windows_for(PDO $pdo, int $clientId): array
    {
        static $cache = [];
        if (isset($cache[$clientId])) return $cache[$clientId];
        return $cache[$clientId] = ampm_build_windows(ampm_settings($pdo, $clientId));
    }
}

if (!function_exists('ampm_current_windows')) {
    /** The window defs for the CURRENT logged-in tenant (falls back to defaults). */
    function ampm_current_windows(): array
    {
        static $c = null;
        if ($c !== null) return $c;
        try {
            $u   = function_exists('current_user') ? current_user() : null;
            $cid = (int) ($u['client_id'] ?? 0);
            if ($cid > 0 && function_exists('db')) {
                return $c = ampm_windows_for(db(), $cid);
            }
        } catch (Throwable $e) { /* fall through to defaults */ }
        return $c = ampm_windows_default();
    }
}

if (!function_exists('ampm_windows')) {
    /**
     * Window definitions for the current tenant: start time, duration, and
     * labels. Back-compatible no-arg accessor used across the calendar; it now
     * resolves the tenant's configured times rather than a fixed 9-1 / 1-5.
     */
    function ampm_windows(): array
    {
        return ampm_current_windows();
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

if (!function_exists('ampm_window_capacity')) {
    /** The per-day capacity for one window ('am' | 'pm') for this tenant. */
    function ampm_window_capacity(PDO $pdo, int $clientId, string $window): int
    {
        $s = ampm_settings($pdo, $clientId);
        return $window === 'pm' ? (int) $s['pm_capacity'] : (int) $s['am_capacity'];
    }
}

if (!function_exists('ampm_availability')) {
    /**
     * Remaining capacity for both windows on a date, using each window's own
     * per-day capacity. Returns:
     *   ['am' => ['taken'=>int,'remaining'=>int,'full'=>bool,'capacity'=>int], 'pm' => [...]].
     */
    function ampm_availability(PDO $pdo, int $clientId, string $date, int $excludeId = 0): array
    {
        $s     = ampm_settings($pdo, $clientId);
        $capBy = ['am' => (int) $s['am_capacity'], 'pm' => (int) $s['pm_capacity']];
        $out   = [];
        foreach (array_keys(ampm_windows_for($pdo, $clientId)) as $w) {
            $taken = ampm_window_count($pdo, $clientId, $date, $w, $excludeId);
            $cap   = $capBy[$w] ?? (int) $s['capacity'];
            $rem   = $cap - $taken;
            if ($rem < 0) $rem = 0;
            $out[$w] = ['taken' => $taken, 'remaining' => $rem, 'full' => $rem <= 0, 'capacity' => $cap];
        }
        return $out;
    }
}
