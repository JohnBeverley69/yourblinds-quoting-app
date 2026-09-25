<?php
declare(strict_types=1);

/**
 * Booking-window ("time slot") helpers (feature_ampm_slots — see
 * migrate_ampm_slots.php, migrate_ampm_window_config.php and
 * migrate_ampm_windows_list.php).
 *
 * A quote (measure) visit can be booked into a WINDOW instead of a clock time.
 * Each tenant keeps its own LIST of windows (Settings → Calendar): a name,
 * From/To times and a per-day CAPACITY each, and can add or remove windows —
 * e.g. Morning 10–1, Afternoon 2–4, Evening 6–8. Default: Morning 9am-1pm and
 * Afternoon 1pm-5pm, 4 each.
 *
 * Storage: client_settings.ampm_windows_json — a JSON list of
 *   {"k":"am","label":"Morning","start":"09:00:00","end":"13:00:00","cap":4,"off":false}
 * Keys: the first two stay 'am' / 'pm' (every booking made before the list
 * existed uses them); added windows get 'w1'…'w9'. A key is never reused —
 * a removed window stays in the list with "off":true so its old bookings keep
 * their label. When the JSON is empty/absent the list is built from the legacy
 * ampm_am_* / ampm_pm_* columns, so nothing changes until a tenant saves.
 *
 * The window is stored on the booking canonically as appointment_time +
 * duration_minutes so every calendar view keeps working, plus a slot_window
 * marker (the key, VARCHAR(2)) so renderers can show the label and capacity is
 * counted per window per day. Capacity counts purely on slot_window (only ever
 * set on slot-booked measure visits) so it never includes fittings.
 *
 * Pure-ish functions; safe to require more than once.
 */

if (!defined('AMPM_MAX_WINDOWS')) {
    define('AMPM_MAX_WINDOWS', 6);   // bookable windows per tenant
}

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

if (!function_exists('ampm_is_window_key')) {
    /** A syntactically valid window key: 'am', 'pm' or 'w1'…'w9'. */
    function ampm_is_window_key(?string $k): bool
    {
        return $k === 'am' || $k === 'pm' || (is_string($k) && preg_match('/^w[1-9]$/', $k) === 1);
    }
}

if (!function_exists('ampm_default_config')) {
    /** The out-of-the-box window list (keyed config rows). */
    function ampm_default_config(): array
    {
        return [
            'am' => ['label' => 'Morning',   'start' => '09:00:00', 'end' => '13:00:00', 'cap' => 4, 'off' => false],
            'pm' => ['label' => 'Afternoon', 'start' => '13:00:00', 'end' => '17:00:00', 'cap' => 4, 'off' => false],
        ];
    }
}

if (!function_exists('ampm_parse_config')) {
    /**
     * Decode + sanitise a stored ampm_windows_json value into keyed config
     * rows (sorted by start time). Returns [] if blank/invalid.
     */
    function ampm_parse_config(?string $json): array
    {
        if ($json === null || trim($json) === '') return [];
        $list = json_decode($json, true);
        if (!is_array($list)) return [];
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            $k = (string) ($row['k'] ?? '');
            if (!ampm_is_window_key($k) || isset($out[$k])) continue;
            $out[$k] = [
                'label' => trim((string) ($row['label'] ?? '')) ?: 'Window',
                'start' => (string) ($row['start'] ?? '09:00:00'),
                'end'   => (string) ($row['end'] ?? '13:00:00'),
                'cap'   => max(1, (int) ($row['cap'] ?? 4)),
                'off'   => !empty($row['off']),
            ];
        }
        uasort($out, static fn ($a, $b) => strcmp($a['start'], $b['start']));
        return $out;
    }
}

if (!function_exists('ampm_settings')) {
    /**
     * Read the tenant's slot settings, guarded so a tenant that hasn't run the
     * migrations simply gets the feature off / the defaults (never a 500).
     * Returns: on(bool), config (keyed rows incl. removed ones — see
     * ampm_parse_config), capacity (first window's, legacy), and the legacy
     * am_/pm_ start/end/capacity keys.
     */
    function ampm_settings(PDO $pdo, int $clientId): array
    {
        static $cache = [];
        if (isset($cache[$clientId])) return $cache[$clientId];

        $on  = false;
        $cfg = ampm_default_config();

        try {
            $st = $pdo->prepare('SELECT * FROM client_settings WHERE client_id = ? LIMIT 1');
            $st->execute([$clientId]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $r = [];
        }

        if ($r) {
            $on = (int) ($r['feature_ampm_slots'] ?? 0) === 1;
            $listed = ampm_parse_config(isset($r['ampm_windows_json']) ? (string) $r['ampm_windows_json'] : null);
            if ($listed) {
                $cfg = $listed;
            } else {
                // Legacy two-window columns (pre list migration, or never saved since).
                $legacyCap = (int) ($r['ampm_slot_capacity'] ?? 4);
                $cfg['am']['start'] = (string) ($r['ampm_am_start'] ?? '09:00:00');
                $cfg['am']['end']   = (string) ($r['ampm_am_end']   ?? '13:00:00');
                $cfg['pm']['start'] = (string) ($r['ampm_pm_start'] ?? '13:00:00');
                $cfg['pm']['end']   = (string) ($r['ampm_pm_end']   ?? '17:00:00');
                $cfg['am']['cap']   = max(1, (int) ($r['ampm_am_capacity'] ?? $legacyCap));
                $cfg['pm']['cap']   = max(1, (int) ($r['ampm_pm_capacity'] ?? $legacyCap));
            }
        }

        $live  = array_filter($cfg, static fn ($w) => !$w['off']);
        $first = reset($live) ?: ['cap' => 4];
        return $cache[$clientId] = [
            'on'          => $on,
            'config'      => $cfg,
            'capacity'    => (int) $first['cap'],
            // Legacy keys — kept for any caller still reading them.
            'am_capacity' => (int) ($cfg['am']['cap'] ?? 4),
            'pm_capacity' => (int) ($cfg['pm']['cap'] ?? 4),
            'am_start'    => (string) ($cfg['am']['start'] ?? '09:00:00'), 'am_end' => (string) ($cfg['am']['end'] ?? '13:00:00'),
            'pm_start'    => (string) ($cfg['pm']['start'] ?? '13:00:00'), 'pm_end' => (string) ($cfg['pm']['end'] ?? '17:00:00'),
        ];
    }
}

if (!function_exists('ampm_build_windows')) {
    /**
     * Window defs (time, duration, label, range) from a settings array. Removed
     * windows are left out unless $withOff — used for LABELS, so a booking in a
     * since-removed window still reads e.g. "Evening" rather than a bare time.
     */
    function ampm_build_windows(array $s, bool $withOff = false): array
    {
        $out = [];
        foreach (($s['config'] ?? ampm_default_config()) as $k => $w) {
            if ($w['off'] && !$withOff) continue;
            $out[$k] = ampm_one_window($w['start'], $w['end'], $w['label']);
        }
        return $out;
    }
}

if (!function_exists('ampm_windows_default')) {
    /** The out-of-the-box windows (used as a fallback pre-migration / no tenant). */
    function ampm_windows_default(): array
    {
        return ampm_build_windows(['config' => ampm_default_config()]);
    }
}

if (!function_exists('ampm_windows_for')) {
    /** The bookable window defs for a specific tenant (cached per request). */
    function ampm_windows_for(PDO $pdo, int $clientId): array
    {
        static $cache = [];
        if (isset($cache[$clientId])) return $cache[$clientId];
        return $cache[$clientId] = ampm_build_windows(ampm_settings($pdo, $clientId));
    }
}

if (!function_exists('ampm_current_client_id')) {
    /** The logged-in tenant's client id, or 0. */
    function ampm_current_client_id(): int
    {
        try {
            $u = function_exists('current_user') ? current_user() : null;
            return (int) ($u['client_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('ampm_current_windows')) {
    /** The bookable window defs for the CURRENT logged-in tenant (falls back to defaults). */
    function ampm_current_windows(): array
    {
        static $c = null;
        if ($c !== null) return $c;
        try {
            $cid = ampm_current_client_id();
            if ($cid > 0 && function_exists('db')) {
                return $c = ampm_windows_for(db(), $cid);
            }
        } catch (Throwable $e) { /* fall through to defaults */ }
        return $c = ampm_windows_default();
    }
}

if (!function_exists('ampm_label_windows')) {
    /** Every window the current tenant has ever had (removed ones too), for labels. */
    function ampm_label_windows(): array
    {
        static $c = null;
        if ($c !== null) return $c;
        try {
            $cid = ampm_current_client_id();
            if ($cid > 0 && function_exists('db')) {
                return $c = ampm_build_windows(ampm_settings(db(), $cid), true);
            }
        } catch (Throwable $e) { /* fall through to defaults */ }
        return $c = ampm_windows_default();
    }
}

if (!function_exists('ampm_windows')) {
    /**
     * Bookable window definitions for the current tenant: start time, duration
     * and labels, in start-time order. No-arg accessor used across the calendar.
     */
    function ampm_windows(): array
    {
        return ampm_current_windows();
    }
}

if (!function_exists('is_ampm_window')) {
    /** True if $w is a window key the current tenant has (or had) — for labelling. */
    function is_ampm_window(?string $w): bool
    {
        return $w !== null && isset(ampm_label_windows()[$w]);
    }
}

if (!function_exists('ampm_window_bookable')) {
    /** True if $w is one of the current tenant's bookable (not removed) windows. */
    function ampm_window_bookable(?string $w): bool
    {
        return $w !== null && isset(ampm_windows()[$w]);
    }
}

if (!function_exists('ampm_window_for_time')) {
    /**
     * Which bookable window a clock time falls in — or, if it's in none (a gap
     * between windows, or before/after them all), the nearest one.
     */
    function ampm_window_for_time(string $time): string
    {
        $wins = ampm_windows();
        $t    = strtotime('1970-01-01 ' . $time);
        $best = (string) (array_key_first($wins) ?? 'am');
        $bestDist = PHP_INT_MAX;
        foreach ($wins as $wk => $win) {
            $s = strtotime('1970-01-01 ' . $win['time']);
            if ($t === false || $s === false) continue;
            $e = $s + $win['duration'] * 60;
            $dist = $t < $s ? $s - $t : ($t >= $e ? $t - $e + 1 : 0);
            if ($dist < $bestDist) { $best = (string) $wk; $bestDist = $dist; }
        }
        return $best;
    }
}

if (!function_exists('ampm_window_label')) {
    /**
     * Customer/staff-facing window label, e.g. "Morning (9am–1pm)".
     * Returns '' for an unrecognised window so callers can fall back to a time.
     */
    function ampm_window_label(?string $w): string
    {
        $win = ampm_label_windows()[$w] ?? null;
        return $win ? "{$win['label']} ({$win['range']})" : '';
    }
}

if (!function_exists('slot_window_short_label')) {
    /**
     * Compact window label for calendar cards, e.g. "Morning" / "Evening".
     * Returns '' for an unrecognised window so callers fall back to the time.
     */
    function slot_window_short_label(?string $w): string
    {
        $win = ampm_label_windows()[$w] ?? null;
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
    /** The per-day capacity for one window for this tenant. */
    function ampm_window_capacity(PDO $pdo, int $clientId, string $window): int
    {
        $s = ampm_settings($pdo, $clientId);
        return (int) ($s['config'][$window]['cap'] ?? $s['capacity']);
    }
}

if (!function_exists('ampm_availability')) {
    /**
     * Remaining capacity for each bookable window on a date, using each
     * window's own per-day capacity. Returns, in start-time order:
     *   ['am' => ['taken'=>int,'remaining'=>int,'full'=>bool,'capacity'=>int], 'pm' => [...], 'w1' => [...]].
     */
    function ampm_availability(PDO $pdo, int $clientId, string $date, int $excludeId = 0): array
    {
        $out = [];
        foreach (array_keys(ampm_windows_for($pdo, $clientId)) as $w) {
            $taken = ampm_window_count($pdo, $clientId, $date, (string) $w, $excludeId);
            $cap   = ampm_window_capacity($pdo, $clientId, (string) $w);
            $rem   = $cap - $taken;
            if ($rem < 0) $rem = 0;
            $out[$w] = ['taken' => $taken, 'remaining' => $rem, 'full' => $rem <= 0, 'capacity' => $cap];
        }
        return $out;
    }
}
