<?php
declare(strict_types=1);

/**
 * Online booking (Phase 1) — customers book a measure visit themselves.
 *
 * The public page is /book/?c=<link name>. A company links to it from its
 * website, Facebook page or Google profile, or prints the QR code (Settings →
 * Online booking). The customer:
 *   1. types their postcode — checked against the company's area: a radius in
 *      miles from a base postcode and/or a list of postcode areas (HU, YO25…);
 *      passing either one is enough, and no rules at all means "anywhere";
 *   2. picks a day and a time. Two ways, chosen in Settings (booking_style):
 *      SLOTS — one of the company's Booking time slots (Company tab), offered
 *      only while it has room, counted exactly as the office booking form
 *      counts them; or EXACT TIMES — start times worked out from the opening
 *      hours for that day, every visit length + gap (60 + 30 → 9:00, 10:30,
 *      12:00…), offered while fewer visits overlap it than the company has
 *      salespeople (ob_time_busy);
 *   3. leaves their details and books.
 *
 * REQUEST mode (the default): the visit goes in the diary straight away so the
 * slot is held, flagged appointments.online_request = 1, and the customer is
 * told "we'll confirm shortly". The office confirms or declines it from the
 * appointment (calendar/online_decision.php). No booking text or day-before
 * reminder goes out while it is waiting (sms.php checks the flag).
 * INSTANT mode: booked and confirmed in one go.
 *
 * WHO GOES (Phase 2): every salesperson (Sales role) takes online bookings
 * unless unticked in Settings, each optionally with their own area (a radius
 * from their home postcode and/or a postcode list). A time is offered when a
 * salesperson whose area covers the postcode can do it — or anyone, if no one's
 * area covers it. "Group visits by area" (group_miles) keeps a salesperson who
 * already has a visit that day to customers near it. The booking is given to
 * the free salesperson with the fewest visits that day (auto_assign), or left
 * unassigned. A company with no salespeople works as before: unassigned.
 *
 * CHANGE OR CANCEL (Phase 2): the customer's emails carry a secret link
 * (online_bookings.manage_token → book/manage.php) to move the visit to
 * another free time or cancel it, up to the day before.
 *
 * SAFETY — this is the first public page that WRITES into a company's data:
 *  - everything is scoped to the one company the link name resolves to; the
 *    page never shows anything about other bookings, only "free" slots;
 *  - a repeat customer (same email or mobile, and same surname) is booked against their existing
 *    record, but the public form NEVER changes that record — what they typed
 *    goes on the appointment and its notes, so nobody can alter someone else's
 *    address by booking with their email;
 *  - spam limits per visitor and per company (ob_rate_limited), a hidden trap
 *    field and a minimum fill time (book/index.php);
 *  - the slot is re-checked inside a transaction that locks the company's
 *    online-booking row, so two customers can't both take the last place.
 *
 * Plans: whichever tiers have feature_online_booking (Plan features board), plus the main factory.
 * Tables: migrate_online_booking.php (+ _lookups, _times, _phase2).
 */

require_once __DIR__ . '/slot_window.php';

const OB_MODES          = ['request', 'instant'];
const OB_MAX_DAYS_AHEAD = 90;
const OB_MAX_NOTICE     = 14;
const OB_WEEKDAY_NAMES  = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

/** Defaults for a company that hasn't saved anything yet. */
function ob_default_settings(): array
{
    return [
        'enabled'         => 0,
        'slug'            => null,
        'mode'            => 'request',
        'min_notice_days' => 1,
        'max_days_ahead'  => 28,
        'weekdays'        => '1,2,3,4,5',
        'closed_dates'    => null,
        'base_postcode'   => null,
        'radius_miles'    => null,
        'districts'       => null,
        'intro_text'      => null,
        'notify_email'    => null,
        'booking_style'   => 'slots',
        'visit_minutes'   => 60,
        'gap_minutes'     => 30,
        'hours_json'      => null,
        'display_name'    => null,
        'auto_assign'     => 1,
        'group_miles'     => null,
    ];
}

/** The company's online-booking settings (defaults if none / not migrated). */
function ob_settings(int $clientId, bool $fresh = false): array
{
    static $cache = [];
    if (!$fresh && isset($cache[$clientId])) return $cache[$clientId];
    $s = ob_default_settings();
    try {
        $st = db()->prepare('SELECT * FROM online_booking_settings WHERE client_id = ? LIMIT 1');
        $st->execute([$clientId]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) $s = array_merge($s, $row);
    } catch (Throwable $e) { /* not migrated — defaults */ }
    return $cache[$clientId] = $s;
}

/** Is online booking on this company's plan? (Silver/Gold, or the main factory.) */
function ob_feature_enabled(int $clientId): bool
{
    if (function_exists('factory_client_id') && $clientId === (int) factory_client_id()) return true;
    try {
        $st = db()->prepare('SELECT COALESCE(feature_online_booking, 0) FROM client_settings WHERE client_id = ?');
        $st->execute([$clientId]);
        return (int) $st->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;   // not migrated
    }
}

/**
 * Can customers book this company online right now? Returns [ok, reason]; the
 * reason is for the company's own Settings screen, never shown to the public.
 */
function ob_status(int $clientId): array
{
    if (!ob_feature_enabled($clientId)) {
        require_once __DIR__ . '/billing_helpers.php';
        return [false, 'Online booking is part of ' . billing_feature_plans_phrase('feature_online_booking') . '.'];
    }
    $s = ob_settings($clientId);
    if ((int) $s['enabled'] !== 1)       return [false, 'Online booking is switched off.'];
    if ((string) ($s['slug'] ?? '') === '') return [false, 'There is no booking link name yet.'];
    if (ob_style($s) === 'times') {
        foreach (ob_weekdays($s) as $dow) {
            if (ob_day_starts($s, $dow)) return [true, ''];
        }
        return [false, 'None of your booking days has room for a visit — check the hours and the visit length.'];
    }
    if (!ampm_settings(db(), $clientId)['on']) {
        return [false, 'Booking by time slot uses your Booking time slots — switch them on on the Company tab (under Calendar), or choose "Exact times" below.'];
    }
    if (!ob_windows($clientId)) return [false, 'There are no time slots set up.'];
    return [true, ''];
}

/* ------------------------------------------------------------------
 *  Link name (slug)
 * ------------------------------------------------------------------ */

/** "Beverley Blinds & Shutters Ltd" → "beverley-blinds-shutters". */
function ob_slugify(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/\b(ltd|limited|llp|plc)\b\.?/', '', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if (strlen($s) > 50) $s = rtrim(substr($s, 0, 50), '-');
    return $s;
}

/** A valid link name: 3–50 lower-case letters, numbers and single dashes. */
function ob_slug_valid(string $slug): bool
{
    return preg_match('/^[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){2,49}$/', $slug) === 1;
}

/** Is this link name free (or already this company's)? */
function ob_slug_available(string $slug, int $clientId): bool
{
    $st = db()->prepare('SELECT client_id FROM online_booking_settings WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $owner = $st->fetchColumn();
    return $owner === false || (int) $owner === $clientId;
}

/** A free link name built from the company name, adding -2, -3… if taken. */
function ob_suggest_slug(string $companyName, int $clientId): string
{
    $base = ob_slugify($companyName);
    if (strlen($base) < 3) $base = 'company-' . $clientId;
    $try = $base;
    for ($n = 2; $n < 50 && !ob_slug_available($try, $clientId); $n++) {
        $try = substr($base, 0, 46) . '-' . $n;
    }
    return $try;
}

/** The public booking link for a link name. */
function ob_link(string $slug): string
{
    $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');
    return $base . '/book/?c=' . rawurlencode($slug);
}

/**
 * The company a public link name belongs to, with what the page needs to show,
 * or null when it doesn't exist or isn't bookable right now (the page then
 * says "not available" — never why).
 */
function ob_company_by_slug(string $slug): ?array
{
    $slug = strtolower(trim($slug));
    if (!ob_slug_valid($slug)) return null;
    try {
        $st = db()->prepare(
            'SELECT c.id, c.company_name, c.email, c.phone, c.logo_path, c.active
               FROM online_booking_settings o
               JOIN clients c ON c.id = o.client_id
              WHERE o.slug = ? LIMIT 1'
        );
        $st->execute([$slug]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
    if (!$c || (int) ($c['active'] ?? 1) !== 1) return null;
    if (!ob_status((int) $c['id'])[0]) return null;
    return $c;
}

/* ------------------------------------------------------------------
 *  Days and slots
 * ------------------------------------------------------------------ */

/** The company's bookable time slots (keyed am/pm/w1…), from ITS settings — no login needed. */
function ob_windows(int $clientId): array
{
    return ampm_build_windows(ampm_settings(db(), $clientId));
}

/** "Morning (9am–1pm)" for a slot key, including since-removed slots. */
function ob_window_label(int $clientId, ?string $key): string
{
    $wins = ampm_build_windows(ampm_settings(db(), $clientId), true);
    $w = $key !== null ? ($wins[$key] ?? null) : null;
    return $w ? $w['label'] . ' (' . $w['range'] . ')' : '';
}

/** 'times' (exact start times) or 'slots' (the company's Booking time slots). */
function ob_style(array $s): string
{
    return (string) ($s['booking_style'] ?? 'slots') === 'times' ? 'times' : 'slots';
}

/** Opening hours per weekday for exact-time booking: [1 => ['from' => '09:00', 'to' => '17:00'], …]. */
function ob_hours(array $s): array
{
    $saved = json_decode((string) ($s['hours_json'] ?? ''), true);
    $ok    = static fn ($t): bool => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
    $out   = [];
    foreach (OB_WEEKDAY_NAMES as $d => $_) {
        $h = is_array($saved) && isset($saved[(string) $d]) && is_array($saved[(string) $d]) ? $saved[(string) $d] : [];
        $out[$d] = [
            'from' => $ok($h['from'] ?? null) ? $h['from'] : '09:00',
            'to'   => $ok($h['to'] ?? null) ? $h['to'] : '17:00',
        ];
    }
    return $out;
}

/** Minutes since midnight for 'HH:MM'. */
function ob_minutes(string $hhmm): int
{
    return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);
}

/**
 * Start times on one weekday for exact-time booking, as 'HH:MM': from the
 * opening time, every visit length + gap, while the visit still finishes by
 * the closing time. 9:00–17:00 with 60 + 30 → 09:00, 10:30, 12:00, 13:30, 15:00.
 */
function ob_day_starts(array $s, int $dow): array
{
    $h     = ob_hours($s)[$dow] ?? null;
    $visit = max(15, min(480, (int) ($s['visit_minutes'] ?? 60)));
    $step  = $visit + max(0, min(240, (int) ($s['gap_minutes'] ?? 30)));
    if ($h === null) return [];
    $out = [];
    for ($t = ob_minutes($h['from']); $t + $visit <= ob_minutes($h['to']) && count($out) < 48; $t += $step) {
        $out[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    }
    return $out;
}

/** 90 → '1½ hours'; 60 → '1 hour'; 45 → '45 minutes'. */
function ob_duration_words(int $minutes): string
{
    if ($minutes < 60 || $minutes % 30 !== 0) return $minutes . ' minutes';
    $h = intdiv($minutes, 60);
    $half = $minutes % 60 === 30 ? '½' : '';
    return $h . $half . ($h === 1 && $half === '' ? ' hour' : ' hours');
}

/** '13:30' → '1:30pm'; '09:00' → '9am'. */
function ob_time_label(string $hhmm): string
{
    return ampm_fmt_time(substr($hhmm, 0, 5) . ':00');
}

/**
 * The salespeople who go out on quote visits (the 'sales' role). How many
 * exact-time visits may overlap is this count — at least 1.
 */
function ob_sales_ids(int $clientId, bool $reset = false): array
{
    static $cache = [];
    if ($reset) { $cache = []; return []; }
    if (isset($cache[$clientId])) return $cache[$clientId];
    try {
        $st = db()->prepare(
            "SELECT DISTINCT u.id FROM client_users u JOIN client_user_roles r ON r.user_id = u.id
              WHERE u.client_id = ? AND u.active = 1 AND r.role = 'sales'"
        );
        $st->execute([$clientId]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $st = db()->prepare("SELECT id FROM client_users WHERE client_id = ? AND active = 1 AND role = 'sales'");
        $st->execute([$clientId]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cache[$clientId] = array_map('intval', $ids);
}

/**
 * How many visits already overlap a proposed exact-time visit, with the gap
 * either side (travel time between visits). Counted: unassigned visits that
 * aren't fittings, and anything assigned to a salesperson. Cancelled and
 * no-show don't count.
 */
function ob_time_busy(int $clientId, array $s, string $date, string $hhmm, int $excludeId = 0): int
{
    $visit = max(15, (int) ($s['visit_minutes'] ?? 60));
    $gap   = max(0, (int) ($s['gap_minutes'] ?? 30));
    $start = ob_minutes($hhmm) - $gap;
    $end   = ob_minutes($hhmm) + $visit + $gap;
    $sales = ob_sales_ids($clientId);
    $who   = "(client_user_id IS NULL AND COALESCE(appt_kind, 'measure') <> 'fitting')"
           . ($sales ? ' OR client_user_id IN (' . implode(',', $sales) . ')' : '');
    $st = db()->prepare(
        "SELECT COUNT(*) FROM appointments
          WHERE client_id = ? AND appointment_date = ? AND id <> ?
            AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))
            AND ({$who})
            AND (HOUR(appointment_time) * 60 + MINUTE(appointment_time)) < ?
            AND (HOUR(appointment_time) * 60 + MINUTE(appointment_time) + duration_minutes) > ?"
    );
    $st->execute([$clientId, $date, $excludeId, $end, $start]);
    return (int) $st->fetchColumn();
}

/** How many exact-time visits can happen at once: one per salesperson, at least 1. */
function ob_time_capacity(int $clientId): int
{
    return max(1, count(ob_sales_ids($clientId)));
}

/**
 * The salespeople who take online bookings, with their areas:
 *   [['id' => 7, 'name' => 'Sam', 'home' => 'HU179AB'|null, 'radius' => 20|null, 'districts' => ['HU', …],
 *     'takes' => bool], …]
 * Every Sales-role user is listed; 'takes' is false only when unticked in
 * Settings (online_booking_staff). $all = include the unticked ones.
 */
function ob_staff(int $clientId, bool $all = false, bool $reset = false): array
{
    static $cache = [];
    if ($reset) { $cache = []; return []; }
    if (!isset($cache[$clientId])) {
        $ids  = ob_sales_ids($clientId);
        $rows = [];
        if ($ids) {
            $in = implode(',', $ids);
            $users = db()->query("SELECT id, full_name, home_postcode FROM client_users WHERE id IN ($in) ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
            $own = [];
            try {
                $st = db()->prepare('SELECT * FROM online_booking_staff WHERE client_id = ?');
                $st->execute([$clientId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $own[(int) $r['user_id']] = $r;
            } catch (Throwable $e) { /* not migrated — everyone, anywhere */ }
            foreach ($users as $u) {
                $o = $own[(int) $u['id']] ?? null;
                $rows[] = [
                    'id'        => (int) $u['id'],
                    'name'      => (string) $u['full_name'],
                    'home'      => ob_postcode((string) ($u['home_postcode'] ?? '')),
                    'radius'    => $o !== null && (int) $o['radius_miles'] > 0 ? (int) $o['radius_miles'] : null,
                    'districts' => $o !== null ? ob_parse_districts((string) ($o['districts'] ?? '')) : [],
                    'takes'     => $o === null || (int) $o['takes_online'] === 1,
                ];
            }
        }
        $cache[$clientId] = $rows;
    }
    return $all ? $cache[$clientId] : array_values(array_filter($cache[$clientId], static fn ($r) => $r['takes']));
}

/**
 * Does a salesperson's own area cover a postcode? No area set = anywhere.
 * A radius that can't be measured (lookup down, no home postcode) counts as
 * covered, so a customer is never turned away by a missing distance.
 */
function ob_staff_covers(array $st, string $compact): bool
{
    $useRadius = $st['radius'] !== null && $st['home'] !== null;
    if (!$st['districts'] && !$useRadius) return true;
    if ($st['districts'] && ob_district_match($compact, $st['districts'])) return true;
    if (!$useRadius) return false;
    $a = ob_geo($st['home']);
    $b = ob_geo($compact);
    return $a === null || $b === null || ob_miles($a, $b) <= $st['radius'];
}

/** Forget this request's cached diary and salespeople (call before re-checking inside a booking). */
function ob_cache_reset(): void
{
    ob_day_visits(0, '', 0, true);
    ob_sales_ids(0, true);
    ob_staff(0, false, true);
}

/**
 * One day's visits for a company, for the who-is-free checks, cached per request:
 *   [['id', 'user' => ?int, 'fitting' => bool, 'from' => minutes, 'to' => minutes, 'postcode' => ?string], …]
 * Cancelled and no-show are left out; so is $excludeId (a visit being moved).
 */
function ob_day_visits(int $clientId, string $date, int $excludeId = 0, bool $reset = false): array
{
    static $cache = [];
    if ($reset) { $cache = []; return []; }
    $k = "$clientId|$date|$excludeId";
    if (isset($cache[$k])) return $cache[$k];
    $st = db()->prepare(
        "SELECT id, client_user_id, COALESCE(appt_kind, 'measure') AS kind, appointment_time, duration_minutes, installation_postcode
           FROM appointments
          WHERE client_id = ? AND appointment_date = ? AND id <> ?
            AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))"
    );
    $st->execute([$clientId, $date, $excludeId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $from  = ob_minutes(substr((string) $r['appointment_time'], 0, 5));
        $out[] = [
            'id'       => (int) $r['id'],
            'user'     => $r['client_user_id'] !== null ? (int) $r['client_user_id'] : null,
            'fitting'  => (string) $r['kind'] === 'fitting',
            'from'     => $from,
            'to'       => $from + (int) $r['duration_minutes'],
            'postcode' => ob_postcode((string) ($r['installation_postcode'] ?? '')),
        ];
    }
    return $cache[$k] = $out;
}

/**
 * The salespeople who could take a visit at this postcode on this day:
 * those whose own area covers it (or everyone, if no one's does), less — when
 * "group visits by area" is on — anyone who already has visits that day and
 * none of them within the group distance. A distance that can't be measured
 * never rules anyone out.
 */
function ob_candidates(int $clientId, array $s, string $date, ?string $compact, int $excludeId = 0): array
{
    $staff = ob_staff($clientId);
    if ($compact === null) return $staff;
    $covering = array_values(array_filter($staff, static fn ($st) => ob_staff_covers($st, $compact)));
    $cands = $covering ?: $staff;

    $group = (int) ($s['group_miles'] ?? 0);
    if ($group <= 0) return $cands;
    $here = ob_geo($compact);
    if ($here === null) return $cands;
    $visits = ob_day_visits($clientId, $date, $excludeId);
    return array_values(array_filter($cands, static function ($st) use ($visits, $here, $group): bool {
        $known = false;
        foreach ($visits as $v) {
            if ($v['user'] !== $st['id'] || $v['postcode'] === null) continue;
            $there = ob_geo($v['postcode']);
            if ($there === null) return true;      // can't measure — don't rule them out
            $known = true;
            if (ob_miles($here, $there) <= $group) return true;
        }
        return !$known;                            // no visits yet that day — free to go anywhere
    }));
}

/** How many visits a salesperson has on a day (for sharing the work out). */
function ob_visits_for(array $visits, int $userId): int
{
    $n = 0;
    foreach ($visits as $v) if ($v['user'] === $userId) $n++;
    return $n;
}

/**
 * Can this day + slot/time be booked for this postcode, and by whom?
 * Returns null when it can't, else ['user' => ?int] — the salesperson it would
 * go to (null = leave it unassigned). The single source of truth for both
 * showing a time and booking it; ob_slot_open() is the yes/no form.
 */
function ob_slot_pick(int $clientId, array $s, string $date, string $key, ?DateTimeImmutable $now = null,
                      ?string $compact = null, int $excludeId = 0): ?array
{
    $now = $now ?? new DateTimeImmutable('now');
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($day === false || $day->format('Y-m-d') !== $date || !ob_day_allowed($s, $day, $now)) return null;
    $staff  = ob_staff($clientId);
    $assign = (int) ($s['auto_assign'] ?? 1) === 1;

    if (ob_style($s) === 'times') {
        if (!in_array($key, ob_day_starts($s, (int) $day->format('N')), true)) return null;
        if ($date === $now->format('Y-m-d') && $key . ':00' <= $now->format('H:i:s')) return null;
        if (!$staff) {
            return ob_time_busy($clientId, $s, $date, $key, $excludeId) < ob_time_capacity($clientId) ? ['user' => null] : null;
        }
        $visit  = max(15, (int) ($s['visit_minutes'] ?? 60));
        $gap    = max(0, (int) ($s['gap_minutes'] ?? 30));
        $from   = ob_minutes($key) - $gap;
        $to     = ob_minutes($key) + $visit + $gap;
        $visits = ob_day_visits($clientId, $date, $excludeId);
        $overlaps = static fn ($v): bool => $v['from'] < $to && $v['to'] > $from;
        $unassigned = count(array_filter($visits, static fn ($v) => $v['user'] === null && !$v['fitting'] && $overlaps($v)));
        $free = [];
        foreach (ob_candidates($clientId, $s, $date, $compact, $excludeId) as $st) {
            $busy = false;
            foreach ($visits as $v) if ($v['user'] === $st['id'] && $overlaps($v)) { $busy = true; break; }
            if (!$busy) $free[] = $st;
        }
        if (count($free) - $unassigned <= 0) return null;
        usort($free, static fn ($a, $b) => ob_visits_for($visits, $a['id']) <=> ob_visits_for($visits, $b['id']) ?: $a['id'] <=> $b['id']);
        return ['user' => $assign ? $free[0]['id'] : null];
    }

    $wins = ob_windows($clientId);
    if (!isset($wins[$key])) return null;
    if ($date === $now->format('Y-m-d') && $wins[$key]['time'] <= $now->format('H:i:s')) return null;
    if (ampm_window_count(db(), $clientId, $date, $key, $excludeId) >= ampm_window_capacity(db(), $clientId, $key)) return null;
    if (!$staff) return ['user' => null];
    $cands = ob_candidates($clientId, $s, $date, $compact, $excludeId);
    if (!$cands) return null;
    $visits = ob_day_visits($clientId, $date, $excludeId);
    usort($cands, static fn ($a, $b) => ob_visits_for($visits, $a['id']) <=> ob_visits_for($visits, $b['id']) ?: $a['id'] <=> $b['id']);
    return ['user' => $assign ? $cands[0]['id'] : null];
}

/** The name customers see: the booking-page name, or the company name. */
function ob_display_name(int $clientId, string $companyName): string
{
    $n = trim((string) (ob_settings($clientId)['display_name'] ?? ''));
    return $n !== '' ? $n : $companyName;
}

/** The customer's "change or cancel" link for a manage token. */
function ob_manage_link(string $token): string
{
    $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');
    return $base . '/book/manage.php?t=' . rawurlencode($token);
}

/** What the customer picked, in words: 'Morning (9am–1pm)' or '10:30am'. */
function ob_slot_label(int $clientId, array $s, string $key): string
{
    return ob_style($s) === 'times' ? ob_time_label($key) : ob_window_label($clientId, $key);
}

/** A booked visit's time in words: its slot name, or its start–finish time. */
function ob_appt_time_label(int $clientId, ?string $slotWindow, string $time, int $minutes): string
{
    if ($slotWindow !== null && $slotWindow !== '') {
        $l = ob_window_label($clientId, $slotWindow);
        if ($l !== '') return $l;
    }
    $t   = substr($time, 0, 5);
    $end = ob_minutes($t) + $minutes;
    return ob_time_label($t) . '–' . ob_time_label(sprintf('%02d:%02d', intdiv($end, 60) % 24, $end % 60));
}

/** Allowed weekdays as ISO numbers (1 = Monday … 7 = Sunday). */
function ob_weekdays(array $s): array
{
    $out = [];
    foreach (explode(',', (string) ($s['weekdays'] ?? '')) as $d) {
        $d = (int) trim($d);
        if ($d >= 1 && $d <= 7) $out[$d] = true;
    }
    return array_keys($out);
}

/**
 * Closed days typed one per line: "24/12/2026", "2026-12-24", or a range
 * "24/12/2026 - 2/1/2027". Returns ['Y-m-d' => true]. Lines that don't read
 * are listed in $bad so Settings can say which. A range is capped at 120 days.
 */
function ob_parse_closed(?string $text, &$bad = null): array
{
    $bad = [];
    $out = [];
    $read = static function (string $s): ?DateTimeImmutable {
        $s = trim($s);
        foreach (['!Y-m-d', '!j/n/Y', '!d/m/Y', '!j/n/y', '!d/m/y'] as $fmt) {
            $d = DateTimeImmutable::createFromFormat($fmt, $s);
            if ($d !== false && $d->format(str_replace('!', '', $fmt)) === $s) return $d;
        }
        return null;
    };
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = preg_split('/\s+(?:-|–|to)\s+/i', $line) ?: [$line];
        $from  = $read($parts[0]);
        $to    = isset($parts[1]) ? $read($parts[1]) : $from;
        if ($from === null || $to === null || $to < $from || count($parts) > 2) {
            $bad[] = $line;
            continue;
        }
        for ($d = $from, $i = 0; $d <= $to && $i < 120; $d = $d->modify('+1 day'), $i++) {
            $out[$d->format('Y-m-d')] = true;
        }
    }
    return $out;
}

/**
 * Is one slot on one day open to an online customer? Checks the day is
 * allowed (notice, how far ahead, weekday, closed days), the slot exists, it
 * hasn't started yet when same-day booking is allowed, and it still has room.
 */
function ob_slot_open(int $clientId, array $s, string $date, string $key, ?DateTimeImmutable $now = null,
                      ?string $compact = null, int $excludeId = 0): bool
{
    return ob_slot_pick($clientId, $s, $date, $key, $now, $compact, $excludeId) !== null;
}

/** Notice, how far ahead, weekday and closed days for one day. */
function ob_day_allowed(array $s, DateTimeImmutable $day, DateTimeImmutable $now): bool
{
    $today  = $now->setTime(0, 0);
    $first  = $today->modify('+' . max(0, min(OB_MAX_NOTICE, (int) $s['min_notice_days'])) . ' days');
    $last   = $today->modify('+' . max(1, min(OB_MAX_DAYS_AHEAD, (int) $s['max_days_ahead'])) . ' days');
    if ($day < $first || $day > $last) return false;
    if (!in_array((int) $day->format('N'), ob_weekdays($s), true)) return false;
    return !isset(ob_parse_closed($s['closed_dates'] ?? null)[$day->format('Y-m-d')]);
}

/**
 * The days a customer can pick, each with its free slots, in date order:
 *   [['date' => 'Y-m-d', 'label' => 'Tue 20 Oct', 'slots' => ['am' => 'Morning (9am–1pm)', …]], …]
 * Days with nothing free are left out.
 */
function ob_available_days(int $clientId, ?DateTimeImmutable $now = null, ?string $compact = null, int $excludeId = 0): array
{
    $now  = $now ?? new DateTimeImmutable('now');
    $s    = ob_settings($clientId);
    $wins = ob_windows($clientId);
    $out  = [];
    $day  = $now->setTime(0, 0);
    $max  = max(1, min(OB_MAX_DAYS_AHEAD, (int) $s['max_days_ahead']));
    for ($i = 0; $i <= $max; $i++, $day = $day->modify('+1 day')) {
        if (!ob_day_allowed($s, $day, $now)) continue;
        $date  = $day->format('Y-m-d');
        $slots = [];
        if (ob_style($s) === 'times') {
            foreach (ob_day_starts($s, (int) $day->format('N')) as $t) {
                if (ob_slot_open($clientId, $s, $date, $t, $now, $compact, $excludeId)) $slots[$t] = ob_time_label($t);
            }
            if ($slots) $out[] = ['date' => $date, 'label' => $day->format('D j M'), 'slots' => $slots];
            continue;
        }
        foreach ($wins as $k => $w) {
            if (ob_slot_open($clientId, $s, $date, (string) $k, $now, $compact, $excludeId)) {
                $slots[(string) $k] = $w['label'] . ' (' . $w['range'] . ')';
            }
        }
        if ($slots) $out[] = ['date' => $date, 'label' => $day->format('D j M'), 'slots' => $slots];
    }
    return $out;
}

/* ------------------------------------------------------------------
 *  Area: postcode list and/or radius
 * ------------------------------------------------------------------ */

/** "hu17 9ab" → "HU179AB", or null when it isn't a full UK postcode. */
function ob_postcode(string $raw): ?string
{
    $pc = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
    return preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/', $pc) === 1 ? $pc : null;
}

/** "HU179AB" → "HU17 9AB". */
function ob_postcode_pretty(string $compact): string
{
    return strlen($compact) > 3 ? substr($compact, 0, -3) . ' ' . substr($compact, -3) : $compact;
}

/**
 * The postcode list typed in Settings ("HU, YO25, DN14 6") as clean entries:
 * an area ("HU"), a district ("YO25") or a sector ("DN14 6"). $bad gets any
 * entry that isn't one of those.
 */
function ob_parse_districts(?string $text, &$bad = null): array
{
    $bad = [];
    $out = [];
    foreach (preg_split('/[,;\r\n]+/', strtoupper((string) $text)) ?: [] as $e) {
        $e = preg_replace('/\s+/', ' ', trim($e)) ?? '';
        if ($e === '') continue;
        if (preg_match('/^[A-Z]{1,2}$/', $e) || preg_match('/^[A-Z]{1,2}\d[A-Z\d]?$/', $e)
            || preg_match('/^[A-Z]{1,2}\d[A-Z\d]? \d$/', $e)) {
            $out[$e] = true;
        } else {
            $bad[] = $e;
        }
    }
    return array_keys($out);
}

/** Does a compact postcode fall in one of the list's areas / districts / sectors? */
function ob_district_match(string $compact, array $list): bool
{
    $outward = substr($compact, 0, -3);                 // HU17
    $sector  = $outward . ' ' . substr($compact, -3, 1); // HU17 9
    preg_match('/^[A-Z]+/', $outward, $m);
    $area    = $m[0] ?? '';                              // HU
    foreach ($list as $e) {
        if ($e === $area || $e === $outward || $e === $sector) return true;
    }
    return false;
}

/**
 * Latitude/longitude for a compact postcode: from the postcode_geo cache, or
 * looked up once on postcodes.io (free, open data, no key) and cached —
 * including "not found", so a made-up postcode isn't asked twice. Returns
 * [lat, lng], or null when unknown or the lookup failed. Never looks anything
 * up in the test environment.
 */
function ob_geo(string $compact): ?array
{
    $pdo = db();
    try {
        $st = $pdo->prepare('SELECT found, lat, lng FROM postcode_geo WHERE postcode = ? LIMIT 1');
        $st->execute([$compact]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            return (int) $row['found'] === 1 ? [(float) $row['lat'], (float) $row['lng']] : null;
        }
    } catch (Throwable $e) {
        return null;
    }
    if (strtolower((string) env('APP_ENV', 'production')) === 'testing' || !function_exists('curl_init')) return null;

    $ch = curl_init('https://api.postcodes.io/postcodes/' . rawurlencode($compact));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 404) {
        try {
            $pdo->prepare('INSERT IGNORE INTO postcode_geo (postcode, found) VALUES (?, 0)')->execute([$compact]);
        } catch (Throwable $e) { /* cache is best-effort */ }
        return null;
    }
    $j   = is_string($raw) ? json_decode($raw, true) : null;
    $lat = $j['result']['latitude'] ?? null;
    $lng = $j['result']['longitude'] ?? null;
    if ($code !== 200 || !is_numeric($lat) || !is_numeric($lng)) return null;   // outage — don't cache
    try {
        $pdo->prepare('INSERT IGNORE INTO postcode_geo (postcode, found, lat, lng) VALUES (?, 1, ?, ?)')
            ->execute([$compact, (float) $lat, (float) $lng]);
    } catch (Throwable $e) { /* cache is best-effort */ }
    return [(float) $lat, (float) $lng];
}

/** Straight-line miles between two points. */
function ob_miles(array $a, array $b): float
{
    $r    = 3958.8;
    $dLat = deg2rad($b[0] - $a[0]);
    $dLng = deg2rad($b[1] - $a[1]);
    $h    = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($h)));
}

/**
 * Is a compact postcode in the company's area? Returns
 *   ['ok' => bool, 'check' => 'none'|'list'|'radius'|'unchecked', 'miles' => ?float]
 * 'none'      — no area set, everywhere is fine;
 * 'list'      — matched the postcode list;
 * 'radius'    — within the radius (miles is set);
 * 'unchecked' — only a radius is set and the distance couldn't be worked out
 *               (lookup down): let it through so a real customer isn't turned
 *               away, but it always arrives as a request for the office to check.
 */
function ob_area_check(array $s, string $compact): array
{
    $list   = ob_parse_districts($s['districts'] ?? null);
    $base   = ob_postcode((string) ($s['base_postcode'] ?? ''));
    $radius = (int) ($s['radius_miles'] ?? 0);
    $useRadius = $base !== null && $radius > 0;

    if (!$list && !$useRadius) return ['ok' => true, 'check' => 'none', 'miles' => null];
    if ($list && ob_district_match($compact, $list)) return ['ok' => true, 'check' => 'list', 'miles' => null];
    if (!$useRadius) return ['ok' => false, 'check' => 'list', 'miles' => null];

    $from = ob_geo($base);
    $to   = ob_geo($compact);
    if ($from === null || $to === null) return ['ok' => true, 'check' => 'unchecked', 'miles' => null];
    $miles = round(ob_miles($from, $to), 1);
    return ['ok' => $miles <= $radius, 'check' => 'radius', 'miles' => $miles];
}

/* ------------------------------------------------------------------
 *  Address lookup (Postcoder) — "pick your address" on the details step
 * ------------------------------------------------------------------ */

/**
 * Can this company's booking page offer the address list? It needs the
 * Postcode lookup add-on (Silver/Gold, or the main factory) and the platform's
 * Postcoder key. Each lookup costs YourBlinds money, hence the limits below.
 */
function ob_address_lookup_on(int $clientId): bool
{
    if (!defined('POSTCODER_API_KEY') || POSTCODER_API_KEY === '') return false;
    if (function_exists('factory_client_id') && $clientId === (int) factory_client_id()) return true;
    try {
        $st = db()->prepare('SELECT COALESCE(feature_postcode_lookup, 0) FROM client_settings WHERE client_id = ?');
        $st->execute([$clientId]);
        return (int) $st->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Too many lookups? 6 an hour per visitor per company, 20 a day per visitor
 * across all companies, 150 a day per company. Past the limit the customer
 * just types the address — booking still works.
 */
function ob_address_rate_limited(int $clientId, string $ip): bool
{
    try {
        $q = static function (string $sql, array $p): int {
            $st = db()->prepare($sql);
            $st->execute($p);
            return (int) $st->fetchColumn();
        };
        if ($q('SELECT COUNT(*) FROM online_address_lookups WHERE client_id = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$clientId, $ip]) >= 6) return true;
        if ($q('SELECT COUNT(*) FROM online_address_lookups WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$ip]) >= 20) return true;
        if ($q('SELECT COUNT(*) FROM online_address_lookups WHERE client_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$clientId]) >= 150) return true;
    } catch (Throwable $e) {
        return true;   // not migrated / can't count — don't spend blind
    }
    return false;
}

/**
 * The addresses at a compact postcode, for the customer to pick from:
 *   [['line1' => …, 'line2' => …, 'town' => …], …]
 * Returns [] when the lookup is off, over the limit, failed or found nothing —
 * the customer then types the address, so a failure never stops a booking.
 * $fetch replaces the Postcoder call (tests); it gets the postcode and returns
 * Postcoder's decoded rows, or null.
 */
function ob_lookup_addresses(int $clientId, string $compact, string $ip, ?callable $fetch = null): array
{
    if ($fetch === null && !ob_address_lookup_on($clientId)) return [];
    if (ob_address_rate_limited($clientId, $ip)) return [];
    try {
        db()->prepare('INSERT INTO online_address_lookups (client_id, ip_address, postcode) VALUES (?, ?, ?)')
            ->execute([$clientId, mb_substr($ip, 0, 45), $compact]);
    } catch (Throwable $e) {
        return [];
    }

    $data = ($fetch ?? 'ob_postcoder_fetch')($compact);
    if (!is_array($data)) return [];

    $out = [];
    foreach ($data as $a) {
        if (!is_array($a)) continue;
        $line1 = trim((string) ($a['addressline1'] ?? ''));
        if ($line1 === '') continue;
        $out[] = [
            'line1' => mb_substr($line1, 0, 150),
            'line2' => mb_substr(trim((string) ($a['addressline2'] ?? '')), 0, 150),
            'town'  => mb_substr(trim((string) ($a['posttown'] ?? '')), 0, 100),
        ];
        if (count($out) >= 200) break;
    }
    return $out;
}

/** One Postcoder lookup: its decoded address rows, or null on any failure. */
function ob_postcoder_fetch(string $compact): ?array
{
    $ch = curl_init('https://ws.postcoder.com/pcw/' . rawurlencode(POSTCODER_API_KEY)
        . '/address/UK/' . rawurlencode($compact) . '?format=json&lines=2');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT      => 'YourBlinds/1.0 (+online-booking)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200 || !is_string($body)) return null;
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/* ------------------------------------------------------------------
 *  Spam limits
 * ------------------------------------------------------------------ */

/**
 * Too many bookings from this visitor, or for this company today? Limits: 3
 * an hour per visitor per company, 10 a day per visitor across all
 * companies, 40 a day per company.
 */
function ob_rate_limited(int $clientId, string $ip): bool
{
    try {
        $q = static function (string $sql, array $p): int {
            $st = db()->prepare($sql);
            $st->execute($p);
            return (int) $st->fetchColumn();
        };
        if ($q('SELECT COUNT(*) FROM online_bookings WHERE client_id = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$clientId, $ip]) >= 3) return true;
        if ($q('SELECT COUNT(*) FROM online_bookings WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$ip]) >= 10) return true;
        if ($q('SELECT COUNT(*) FROM online_bookings WHERE client_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$clientId]) >= 40) return true;
    } catch (Throwable $e) {
        return true;   // can't check — don't take public writes blind
    }
    return false;
}

/* ------------------------------------------------------------------
 *  Booking
 * ------------------------------------------------------------------ */

/** Digits only, with a UK 0 instead of +44 / 44: "+44 7700 900123" → "07700900123". */
function ob_phone_digits(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (strncmp($d, '44', 2) === 0 && strlen($d) >= 12) $d = '0' . substr($d, 2);
    return $d;
}

/**
 * An existing customer of this company with the same email, or the same phone
 * number (mobile or phone field). Read-only — never updated from here.
 */
function ob_match_customer(int $clientId, string $email, string $phone): ?array
{
    $pdo = db();
    if ($email !== '') {
        $st = $pdo->prepare('SELECT id, name FROM customers WHERE client_id = ? AND LOWER(TRIM(email)) = ? ORDER BY id LIMIT 1');
        $st->execute([$clientId, strtolower($email)]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) return $row;
    }
    $digits = ob_phone_digits($phone);
    if (strlen($digits) >= 10) {
        $tail = '%' . substr($digits, -4);
        $st = $pdo->prepare(
            'SELECT id, name, mobile, phone FROM customers
              WHERE client_id = ? AND (mobile LIKE ? OR phone LIKE ?) ORDER BY id LIMIT 200'
        );
        $st->execute([$clientId, $tail, $tail]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (ob_phone_digits((string) $c['mobile']) === $digits || ob_phone_digits((string) $c['phone']) === $digits) {
                return ['id' => $c['id'], 'name' => $c['name']];
            }
        }
    }
    return null;
}

/**
 * Do two names share a surname (the last word, letters only, any case)?
 * "Mrs J Smith" / "John Smith" → yes; "Jones Beverley" / "Stan Rogers" → no.
 */
function ob_same_surname(string $a, string $b): bool
{
    $last = static function (string $n): string {
        $words = preg_split('/\s+/', trim($n)) ?: [];
        return strtolower(preg_replace('/[^a-z]/i', '', (string) end($words)) ?? '');
    };
    $x = $last($a);
    return $x !== '' && $x === $last($b);
}

/**
 * Check what the customer typed. $in keys: name, email, phone, address1,
 * address2, town, postcode (compact), notes, date, slot. Returns an error
 * message or null.
 */
function ob_validate(array $in): ?string
{
    if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 120) return 'Please enter your name.';
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 150) return 'Please enter a valid email address.';
    if (strlen(ob_phone_digits($in['phone'])) < 10 || strlen(ob_phone_digits($in['phone'])) > 13) return 'Please enter a phone number we can reach you on.';
    if ($in['address1'] === '' || mb_strlen($in['address1']) > 150) return 'Please enter the first line of your address.';
    if (mb_strlen($in['address2']) > 150) return 'The second address line is too long.';
    if ($in['town'] === '' || mb_strlen($in['town']) > 100) return 'Please enter your town.';
    if (ob_postcode($in['postcode']) === null) return 'Please enter a full UK postcode.';
    if (mb_strlen($in['notes']) > 1000) return 'Please keep the message under 1,000 characters.';
    if ($in['date'] === '' || $in['slot'] === '') return 'Please choose a day and a time.';
    return null;
}

/**
 * Make the booking. $meta: ip, user_agent. Returns
 *   ['ok' => true,  'appointment_id' => int, 'requested' => bool]
 *   ['ok' => false, 'error' => string]   (safe to show the customer)
 */
function ob_create_booking(int $clientId, array $in, array $meta, ?DateTimeImmutable $now = null): array
{
    if (!ob_status($clientId)[0]) return ['ok' => false, 'error' => 'Online booking is not available at the moment.'];
    if (($err = ob_validate($in)) !== null) return ['ok' => false, 'error' => $err];

    $s    = ob_settings($clientId, true);
    $pc   = (string) ob_postcode($in['postcode']);
    $area = ob_area_check($s, $pc);
    if (!$area['ok']) return ['ok' => false, 'error' => 'Sorry, we don\'t cover ' . ob_postcode_pretty($pc) . ' online.'];

    if (ob_style($s) === 'times') {
        $win = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $in['slot']) === 1
            ? ['time' => $in['slot'] . ':00', 'duration' => max(15, (int) $s['visit_minutes'])] : null;
        $slotKey = null;
    } else {
        $win = ob_windows($clientId)[$in['slot']] ?? null;
        $slotKey = $in['slot'];
    }
    if ($win === null) return ['ok' => false, 'error' => 'Please choose a day and a time.'];

    $requested = (string) $s['mode'] !== 'instant' || $area['check'] === 'unchecked';
    $pdo = db();
    try {
        $pdo->beginTransaction();
        // One online booking at a time per company: the slot check and the
        // insert can't interleave with another customer's.
        $pdo->prepare('SELECT client_id FROM online_booking_settings WHERE client_id = ? FOR UPDATE')->execute([$clientId]);
        ob_cache_reset();
        $pick = ob_slot_pick($clientId, $s, $in['date'], $in['slot'], $now, $pc);
        if ($pick === null) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Sorry, that time has just been taken. Please choose another.', 'taken' => true];
        }

        // Same email/phone is only the same customer if the surname matches too —
        // a couple or a relative can share an email. Otherwise: a new customer,
        // with a "possible duplicate" note so the office can merge them.
        $match   = ob_match_customer($clientId, $in['email'], $in['phone']);
        $similar = null;
        if ($match !== null && !ob_same_surname((string) $match['name'], $in['name'])) {
            $similar = $match;
            $match   = null;
        }
        if ($match !== null) {
            $customerId = (int) $match['id'];
        } else {
            $isMobile = preg_match('/^07\d{9}$/', ob_phone_digits($in['phone'])) === 1;
            $pdo->prepare(
                'INSERT INTO customers (client_id, name, email, phone, mobile, address1, address2, town, postcode)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $clientId, $in['name'], $in['email'],
                $isMobile ? null : $in['phone'], $isMobile ? $in['phone'] : null,
                $in['address1'], $in['address2'] !== '' ? $in['address2'] : null, $in['town'],
                ob_postcode_pretty($pc),
            ]);
            $customerId = (int) $pdo->lastInsertId();
        }

        $notes = 'Booked online' . ($requested ? ' — waiting for you to confirm.' : '.');
        if ($area['check'] === 'unchecked') $notes .= "\nThe distance couldn't be checked — please make sure this postcode is in your area.";
        if ($area['miles'] !== null) $notes .= "\nAbout {$area['miles']} miles from your base (straight line).";
        if ($match !== null) {
            $notes .= "\nMatched to an existing customer. What they typed: {$in['name']}, {$in['phone']}, {$in['email']}.";
        } elseif ($similar !== null) {
            $notes .= "\nSame email or phone as {$similar['name']} — possible duplicate customer. Merge them if they're the same person.";
        }
        if ($pick['user'] !== null) {
            foreach (ob_staff($clientId) as $st) {
                if ($st['id'] === $pick['user']) $notes .= "\nGiven to {$st['name']} automatically.";
            }
        }
        if ($in['notes'] !== '') $notes .= "\n\nCustomer's message: " . $in['notes'];

        $pdo->prepare(
            'INSERT INTO appointments
               (client_id, client_user_id, customer_id, title, appointment_date, appointment_time, duration_minutes,
                installation_address1, installation_address2, installation_town, installation_postcode,
                notes, status, slot_window, online_request)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'booked\', ?, ?)'
        )->execute([
            $clientId, $pick['user'], $customerId, $in['name'], $in['date'], $win['time'], $win['duration'],
            $in['address1'], $in['address2'] !== '' ? $in['address2'] : null, $in['town'], ob_postcode_pretty($pc),
            $notes, $slotKey, $requested ? 1 : 0,
        ]);
        $apptId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO online_bookings
               (client_id, appointment_id, customer_id, matched_existing, status, area_check, distance_miles,
                ip_address, user_agent, submitted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $clientId, $apptId, $customerId, $match !== null ? 1 : 0,
            $requested ? 'requested' : 'instant', $area['check'], $area['miles'],
            mb_substr((string) ($meta['ip'] ?? ''), 0, 45), mb_substr((string) ($meta['user_agent'] ?? ''), 0, 255),
            json_encode(array_intersect_key($in, array_flip(['name', 'email', 'phone', 'address1', 'address2', 'town', 'postcode', 'notes'])), JSON_UNESCAPED_UNICODE),
        ]);
        // The customer's "change or cancel" link. Separate so a booking still
        // saves if migrate_online_booking_phase2.php hasn't run yet.
        try {
            $pdo->prepare('UPDATE online_bookings SET manage_token = ? WHERE id = ?')
                ->execute([bin2hex(random_bytes(32)), (int) $pdo->lastInsertId()]);
        } catch (Throwable $e) { /* no token column yet — no link in the emails */ }
        $pdo->commit();
        ob_cache_reset();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('ob_create_booking(' . $clientId . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Sorry, we couldn\'t save your booking. Please try again, or give us a call.'];
    }

    // Tell everyone. Best-effort — a mail or text problem never undoes the booking.
    ob_email_customer($clientId, $apptId, $requested ? 'requested' : 'confirmed');
    ob_email_company($clientId, $apptId, $requested);
    if (!$requested) {
        require_once __DIR__ . '/sms.php';
        sms_after_appointment_saved($clientId, $apptId);
    }
    return ['ok' => true, 'appointment_id' => $apptId, 'requested' => $requested];
}

/** Online bookings waiting for this company to confirm. */
function ob_pending_count(int $clientId): int
{
    try {
        $st = db()->prepare(
            "SELECT COUNT(*) FROM appointments
              WHERE client_id = ? AND online_request = 1 AND status = 'booked'"
        );
        $st->execute([$clientId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // not migrated
    }
}

/**
 * The office confirms or declines an online request. $decision 'confirm' |
 * 'decline'. Returns [ok, message].
 */
function ob_decide(int $clientId, int $apptId, string $decision, int $userId): array
{
    if (!in_array($decision, ['confirm', 'decline'], true)) return [false, 'Unknown choice.'];
    $pdo = db();
    $st = $pdo->prepare(
        "SELECT id, appointment_date FROM appointments
          WHERE id = ? AND client_id = ? AND online_request = 1 AND status = 'booked' LIMIT 1"
    );
    $st->execute([$apptId, $clientId]);
    if (!$st->fetch()) return [false, 'This booking has already been dealt with.'];

    if ($decision === 'confirm') {
        $pdo->prepare('UPDATE appointments SET online_request = 0 WHERE id = ? AND client_id = ?')->execute([$apptId, $clientId]);
    } else {
        $pdo->prepare("UPDATE appointments SET online_request = 0, status = 'cancelled' WHERE id = ? AND client_id = ?")
            ->execute([$apptId, $clientId]);
    }
    $pdo->prepare(
        'UPDATE online_bookings SET status = ?, decided_by = ?, decided_at = NOW()
          WHERE appointment_id = ? AND client_id = ?'
    )->execute([$decision === 'confirm' ? 'confirmed' : 'declined', $userId ?: null, $apptId, $clientId]);

    ob_email_customer($clientId, $apptId, $decision === 'confirm' ? 'confirmed' : 'declined');
    if ($decision === 'confirm') {
        require_once __DIR__ . '/sms.php';
        sms_after_appointment_saved($clientId, $apptId, null, $userId);
        return [true, 'Booking confirmed — the customer has been told.'];
    }
    return [true, 'Booking declined — the customer has been told and the time slot is free again.'];
}

/* ------------------------------------------------------------------
 *  The customer's "change or cancel" link (book/manage.php)
 * ------------------------------------------------------------------ */

const OB_MAX_RESCHEDULES = 3;

/** The booking behind a manage token, with its visit, or null. */
function ob_manage_load(string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) return null;
    try {
        $st = db()->prepare(
            'SELECT ob.id AS ob_id, ob.client_id, ob.appointment_id, ob.status AS ob_status, ob.reschedules, ob.submitted,
                    a.appointment_date, a.appointment_time, a.duration_minutes, a.slot_window, a.status AS appt_status,
                    a.online_request, a.installation_postcode, a.title, a.client_user_id
               FROM online_bookings ob
               JOIN appointments a ON a.id = ob.appointment_id AND a.client_id = ob.client_id
              WHERE ob.manage_token = ? LIMIT 1'
        );
        $st->execute([$token]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
    return $r ?: null;
}

/**
 * Can the customer still change or cancel it online? Returns [ok, reason] —
 * the reason is shown to the customer. Up to the day before the visit.
 */
function ob_manage_can_change(array $b, ?DateTimeImmutable $now = null): array
{
    $now = $now ?? new DateTimeImmutable('now');
    if ((string) $b['appt_status'] !== 'booked') return [false, 'This appointment has been cancelled.'];
    if ((string) $b['appointment_date'] <= $now->format('Y-m-d')) {
        return [false, "It's too late to change this appointment online. Please give us a call."];
    }
    if (!ob_status((int) $b['client_id'])[0]) return [false, "Online changes aren't available at the moment. Please give us a call."];
    return [true, ''];
}

/** The customer cancels. Returns [ok, message]. */
function ob_customer_cancel(string $token, ?DateTimeImmutable $now = null): array
{
    $b = ob_manage_load($token);
    if ($b === null) return [false, 'Booking not found.'];
    [$ok, $why] = ob_manage_can_change($b, $now);
    if (!$ok) return [false, $why];
    $cid = (int) $b['client_id'];
    $aid = (int) $b['appointment_id'];
    $pdo = db();
    $pdo->prepare(
        "UPDATE appointments SET status = 'cancelled', online_request = 0,
                notes = CONCAT(COALESCE(notes, ''), '\nCancelled online by the customer.')
          WHERE id = ? AND client_id = ? AND status = 'booked'"
    )->execute([$aid, $cid]);
    $pdo->prepare("UPDATE online_bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?")->execute([(int) $b['ob_id']]);
    ob_email_customer($cid, $aid, 'cancelled');
    ob_email_company($cid, $aid, false, 'cancelled');
    return [true, 'Your appointment is cancelled. We\'ve sent you an email to confirm.'];
}

/**
 * The customer moves the visit to another free time. Goes back to "waiting to
 * confirm" in request mode. Returns ['ok' => bool, 'message' => string, 'requested' => bool].
 */
function ob_customer_move(string $token, string $date, string $key, ?DateTimeImmutable $now = null): array
{
    $b = ob_manage_load($token);
    if ($b === null) return ['ok' => false, 'message' => 'Booking not found.'];
    [$ok, $why] = ob_manage_can_change($b, $now);
    if (!$ok) return ['ok' => false, 'message' => $why];
    if ((int) $b['reschedules'] >= OB_MAX_RESCHEDULES) {
        return ['ok' => false, 'message' => "This appointment has already been moved " . OB_MAX_RESCHEDULES . " times online. Please give us a call."];
    }
    $cid = (int) $b['client_id'];
    $aid = (int) $b['appointment_id'];
    $s   = ob_settings($cid, true);
    $pc  = ob_postcode((string) $b['installation_postcode']);

    if (ob_style($s) === 'times') {
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $key) !== 1) return ['ok' => false, 'message' => 'Please choose a day and a time.'];
        $time = $key . ':00'; $dur = max(15, (int) $s['visit_minutes']); $slot = null;
    } else {
        $win = ob_windows($cid)[$key] ?? null;
        if ($win === null) return ['ok' => false, 'message' => 'Please choose a day and a time.'];
        $time = $win['time']; $dur = $win['duration']; $slot = $key;
    }

    require_once __DIR__ . '/sms.php';
    $prevKey   = sms_appointment_key($cid, $aid);
    $was       = date('D j M', strtotime((string) $b['appointment_date'])) . ', '
               . ob_appt_time_label($cid, $b['slot_window'] !== null ? (string) $b['slot_window'] : null,
                     (string) $b['appointment_time'], (int) $b['duration_minutes']);
    $requested = (string) $s['mode'] !== 'instant';
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $pdo->prepare('SELECT client_id FROM online_booking_settings WHERE client_id = ? FOR UPDATE')->execute([$cid]);
        ob_cache_reset();
        $pick = ob_slot_pick($cid, $s, $date, $key, $now, $pc, $aid);
        if ($pick === null) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Sorry, that time has just been taken. Please choose another.'];
        }
        $user = (int) ($s['auto_assign'] ?? 1) === 1 ? $pick['user'] : ($b['client_user_id'] !== null ? (int) $b['client_user_id'] : null);
        $pdo->prepare(
            "UPDATE appointments
                SET appointment_date = ?, appointment_time = ?, duration_minutes = ?, slot_window = ?,
                    client_user_id = ?, online_request = ?,
                    notes = CONCAT(COALESCE(notes, ''), ?)
              WHERE id = ? AND client_id = ? AND status = 'booked'"
        )->execute([$date, $time, $dur, $slot, $user, $requested ? 1 : 0,
                    "\nMoved online by the customer (it was {$was}).", $aid, $cid]);
        $pdo->prepare('UPDATE online_bookings SET reschedules = reschedules + 1, status = ? WHERE id = ?')
            ->execute([$requested ? 'requested' : 'instant', (int) $b['ob_id']]);
        $pdo->commit();
        ob_cache_reset();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('ob_customer_move(' . $aid . '): ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Sorry, we couldn\'t move your appointment. Please try again, or give us a call.'];
    }

    ob_email_customer($cid, $aid, $requested ? 'requested' : 'confirmed');
    ob_email_company($cid, $aid, $requested, 'moved', $was);
    if (!$requested) sms_after_appointment_saved($cid, $aid, $prevKey);
    return ['ok' => true, 'requested' => $requested,
            'message' => $requested ? "We've got your new time and will confirm it by email shortly." : 'Your appointment has been moved. A confirmation is on its way.'];
}

/* ------------------------------------------------------------------
 *  Emails
 * ------------------------------------------------------------------ */

/** Company name, phone, email and the from-name / reply-to its emails use. */
function ob_company_contact(int $clientId): array
{
    $c = ['name' => '', 'phone' => '', 'email' => '', 'from_name' => '', 'reply_to' => ''];
    try {
        $st = db()->prepare('SELECT company_name, phone, email FROM clients WHERE id = ? LIMIT 1');
        $st->execute([$clientId]);
        if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $c['name']  = (string) $r['company_name'];
            $c['phone'] = (string) ($r['phone'] ?? '');
            $c['email'] = (string) ($r['email'] ?? '');
        }
        $rs = db()->prepare('SELECT email_from_name, reply_to_email FROM client_settings WHERE client_id = ? LIMIT 1');
        $rs->execute([$clientId]);
        if ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
            $c['from_name'] = (string) ($r['email_from_name'] ?? '');
            $c['reply_to']  = (string) ($r['reply_to_email'] ?? '');
        }
    } catch (Throwable $e) { /* optional columns — fall back */ }
    $c['name'] = ob_display_name($clientId, $c['name']);
    if ($c['from_name'] === '') $c['from_name'] = $c['name'] !== '' ? $c['name'] : 'YourBlinds';
    return $c;
}

/**
 * The customer's email for one stage: 'requested', 'confirmed' or 'declined'.
 * Pure — no database, no sending — so it can be tested. Returns subject + body.
 */
function ob_compose_customer_email(string $kind, array $co, string $name, string $date, string $slotLabel, string $address, string $bookLink = '', bool $exactTime = false, string $manageLink = ''): array
{
    $d       = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateStr = $d ? $d->format('l j F Y') : $date;
    $company = $co['name'] !== '' ? $co['name'] : 'us';
    $hello   = 'Hello ' . (trim($name) !== '' ? trim($name) : 'there') . ",\n\n";
    $contact = trim(($co['phone'] !== '' ? 'call us on ' . $co['phone'] : '')
             . ($co['phone'] !== '' ? ' or ' : '') . 'reply to this email');
    $when    = "  {$dateStr}\n  {$slotLabel}\n" . ($address !== '' ? "  {$address}\n" : '') . "\n";
    $sign    = "Kind regards,\n" . ($co['name'] !== '' ? $co['name'] : 'The team');
    // Each link sits at the end of its line, so the HTML copy (mailer_send's
    // 'links') reads "…: click here" while the plain-text copy keeps the full
    // address for email programs that can't show links.
    $links   = [];
    foreach ([$manageLink, $bookLink] as $u) if ($u !== '') $links[$u] = 'click here';

    if ($kind === 'requested') {
        return [
            'subject' => 'We\'ve received your booking request' . ($co['name'] !== '' ? ' — ' . $co['name'] : ''),
            'body'    => $hello . "Thank you for booking with {$company}. You asked for:\n\n" . $when
                       . "We'll check our diary and confirm by email shortly. If you need to change anything, just {$contact}.\n\n"
                       . ($manageLink !== '' ? "To change the time or cancel: {$manageLink}\n\n" : '') . $sign,
            'links'   => $links,
        ];
    }
    if ($kind === 'cancelled') {
        return [
            'subject' => 'Your appointment is cancelled' . ($co['name'] !== '' ? ' — ' . $co['name'] : ''),
            'body'    => $hello . "As you asked, we've cancelled your appointment on {$dateStr}, {$slotLabel}.\n\n"
                       . ($bookLink !== '' ? "If you'd like to book again: {$bookLink}\n\n" : '') . $sign,
            'links'   => $links,
        ];
    }
    if ($kind === 'declined') {
        return [
            'subject' => 'About your booking request' . ($co['name'] !== '' ? ' — ' . $co['name'] : ''),
            'body'    => $hello . "Thank you for your booking request for {$dateStr}, {$slotLabel}.\n\n"
                       . "Unfortunately we can't make that time. "
                       . ($bookLink !== '' ? "Please {$contact} and we'll find a time that suits you.\n\nOr choose another time online: {$bookLink}"
                                           : "Please {$contact} and we'll find a time that suits you.")
                       . "\n\n" . $sign,
            'links'   => $links,
        ];
    }
    return [
        'subject' => 'Your appointment is booked' . ($co['name'] !== '' ? ' with ' . $co['name'] : '') . ' — ' . ($d ? $d->format('j M Y') : $date),
        'body'    => $hello . "This confirms your appointment:\n\n" . $when
                   . ($exactTime ? '' : "We'll aim to be with you within that time slot rather than at a fixed time. ")
                   . "If you need to change or cancel, just {$contact}.\n\n"
                   . ($manageLink !== '' ? "Or change the time or cancel online: {$manageLink}\n\n" : '') . $sign,
        'links'   => $links,
    ];
}

/** Email the customer about their online booking. Best-effort; returns whether it sent. */
function ob_email_customer(int $clientId, int $apptId, string $kind): bool
{
    try {
        $st = db()->prepare(
            'SELECT a.appointment_date, a.slot_window, a.appointment_time, a.duration_minutes,
                    a.installation_address1, a.installation_town, a.installation_postcode,
                    a.customer_id, c.name, c.email
               FROM appointments a
               JOIN customers c ON c.id = a.customer_id AND c.client_id = a.client_id
              WHERE a.id = ? AND a.client_id = ? LIMIT 1'
        );
        $st->execute([$apptId, $clientId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        // The address typed on the public form (a matched customer's record may hold an older one).
        $sub = db()->prepare('SELECT * FROM online_bookings WHERE appointment_id = ? AND client_id = ? LIMIT 1');
        $sub->execute([$apptId, $clientId]);
        $obRow = $sub->fetch(PDO::FETCH_ASSOC) ?: [];
        $typed = json_decode((string) ($obRow['submitted'] ?? ''), true);
        $to    = is_array($typed) && !empty($typed['email']) ? (string) $typed['email'] : (string) ($a['email'] ?? '');
        $name  = is_array($typed) && !empty($typed['name']) ? (string) $typed['name'] : (string) ($a['name'] ?? '');
        if (!$a || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $co   = ob_company_contact($clientId);
        $addr = implode(', ', array_filter([
            (string) $a['installation_address1'], (string) $a['installation_town'], (string) $a['installation_postcode'],
        ], static fn ($v) => trim($v) !== ''));
        $slug = (string) (ob_settings($clientId)['slug'] ?? '');
        $mail = ob_compose_customer_email($kind, $co, $name, (string) $a['appointment_date'],
            ob_appt_time_label($clientId, $a['slot_window'] !== null ? (string) $a['slot_window'] : null,
                (string) $a['appointment_time'], (int) $a['duration_minutes']),
            $addr, $slug !== '' ? ob_link($slug) : '', (string) ($a['slot_window'] ?? '') === '',
            !in_array($kind, ['declined', 'cancelled'], true) && !empty($obRow['manage_token']) ? ob_manage_link((string) $obRow['manage_token']) : '');

        require_once __DIR__ . '/../mailer.php';
        $opts = ['from_name' => $co['from_name'], 'links' => $mail['links'] ?? [],
                 'log' => ['client_id' => $clientId, 'kind' => 'online_booking', 'customer_id' => (int) $a['customer_id']]];
        if ($co['reply_to'] !== '') {
            $opts['reply_to_email'] = $co['reply_to'];
            $opts['reply_to_name']  = $co['from_name'];
        }
        return mailer_send($to, $mail['subject'], $mail['body'], null, null, $opts);
    } catch (Throwable $e) {
        error_log('ob_email_customer(' . $apptId . '): ' . $e->getMessage());
        return false;
    }
}

/** Tell the company a booking came in. Goes to the notify address, else the reply-to, else the company email. */
function ob_email_company(int $clientId, int $apptId, bool $requested, string $event = 'new', string $was = ''): bool
{
    try {
        $co = ob_company_contact($clientId);
        $to = trim((string) (ob_settings($clientId)['notify_email'] ?? ''));
        if ($to === '') $to = $co['reply_to'] !== '' ? $co['reply_to'] : $co['email'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $st = db()->prepare(
            'SELECT a.appointment_date, a.slot_window, a.appointment_time, a.duration_minutes, a.installation_postcode, a.title,
                    u.full_name AS assignee
               FROM appointments a LEFT JOIN client_users u ON u.id = a.client_user_id
              WHERE a.id = ? AND a.client_id = ? LIMIT 1'
        );
        $st->execute([$apptId, $clientId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return false;

        $d    = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $a['appointment_date']);
        $when = ($d ? $d->format('D j M Y') : $a['appointment_date']) . ', '
              . ob_appt_time_label($clientId, $a['slot_window'] !== null ? (string) $a['slot_window'] : null,
                  (string) $a['appointment_time'], (int) $a['duration_minutes']);
        $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');
        if ($event === 'cancelled') {
            $subject = 'Online booking cancelled by the customer: ' . $a['title'] . ' — ' . $when;
            $body = "The customer has cancelled their online booking, using the link in their email.\n\n"
                  . "  {$a['title']}\n  {$when}\n  {$a['installation_postcode']}\n\n"
                  . "The time is free again. The appointment is marked Cancelled:\n"
                  . $base . '/calendar/view.php?id=' . $apptId . "\n";
            require_once __DIR__ . '/../mailer.php';
            return mailer_send($to, $subject, $body, null, null,
                ['from_name' => 'YourBlinds', 'log' => ['client_id' => $clientId, 'kind' => 'online_booking']]);
        }
        $subject = ($event === 'moved' ? 'Online booking moved by the customer: ' : ($requested ? 'Online booking request: ' : 'New online booking: '))
                 . $a['title'] . ' — ' . $when;
        $body = ($event === 'moved' ? "The customer has moved their online booking" . ($was !== '' ? " (it was {$was})" : '') . ".\n\n" : "A customer has booked online.\n\n")
              . "  {$a['title']}\n  {$when}\n  {$a['installation_postcode']}\n\n"
              . ($requested
                  ? "It's in your calendar and the time slot is held. Open it to confirm or decline — the customer is waiting to hear:\n"
                  : "It's in your calendar and the customer has been sent a confirmation:\n")
              . $base . '/calendar/view.php?id=' . $apptId . "\n\n"
              . (!empty($a['assignee']) ? "It has been given to {$a['assignee']}.\n" : "It isn't assigned to anyone yet.\n");

        require_once __DIR__ . '/../mailer.php';
        return mailer_send($to, $subject, $body, null, null,
            ['from_name' => 'YourBlinds', 'log' => ['client_id' => $clientId, 'kind' => 'online_booking']]);
    } catch (Throwable $e) {
        error_log('ob_email_company(' . $apptId . '): ' . $e->getMessage());
        return false;
    }
}
