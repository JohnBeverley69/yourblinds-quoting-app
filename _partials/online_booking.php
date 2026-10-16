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
 *   2. picks a day and one of the company's time slots (Settings → Calendar →
 *      time slots — online booking needs those on). Only slots with room left
 *      are offered, counted exactly as the office booking form counts them;
 *   3. leaves their details and books.
 *
 * REQUEST mode (the default): the visit goes in the diary straight away so the
 * slot is held, flagged appointments.online_request = 1, and the customer is
 * told "we'll confirm shortly". The office confirms or declines it from the
 * appointment (calendar/online_decision.php). No booking text or day-before
 * reminder goes out while it is waiting (sms.php checks the flag).
 * INSTANT mode: booked and confirmed in one go.
 *
 * Bookings are left unassigned — the office gives them to a salesperson.
 *
 * SAFETY — this is the first public page that WRITES into a company's data:
 *  - everything is scoped to the one company the link name resolves to; the
 *    page never shows anything about other bookings, only "free" slots;
 *  - a repeat customer (same email or mobile) is booked against their existing
 *    record, but the public form NEVER changes that record — what they typed
 *    goes on the appointment and its notes, so nobody can alter someone else's
 *    address by booking with their email;
 *  - spam limits per visitor and per company (ob_rate_limited), a hidden trap
 *    field and a minimum fill time (book/index.php);
 *  - the slot is re-checked inside a transaction that locks the company's
 *    online-booking row, so two customers can't both take the last place.
 *
 * Plans: Silver and Gold (feature_online_booking), plus the main factory.
 * Tables: migrate_online_booking.php.
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
    if (!ob_feature_enabled($clientId)) return [false, 'Online booking is part of the Silver and Gold plans.'];
    $s = ob_settings($clientId);
    if ((int) $s['enabled'] !== 1)       return [false, 'Online booking is switched off.'];
    if ((string) ($s['slug'] ?? '') === '') return [false, 'There is no booking link name yet.'];
    if (!ampm_settings(db(), $clientId)['on']) {
        return [false, 'Online booking uses your time slots — switch on "Booking time slots" on the Company tab (under Calendar) first.'];
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
function ob_slot_open(int $clientId, array $s, string $date, string $key, ?DateTimeImmutable $now = null): bool
{
    $now  = $now ?? new DateTimeImmutable('now');
    $day  = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $wins = ob_windows($clientId);
    if ($day === false || $day->format('Y-m-d') !== $date || !isset($wins[$key])) return false;
    if (!ob_day_allowed($s, $day, $now)) return false;
    if ($date === $now->format('Y-m-d') && $wins[$key]['time'] <= $now->format('H:i:s')) return false;
    $taken = ampm_window_count(db(), $clientId, $date, $key);
    return $taken < ampm_window_capacity(db(), $clientId, $key);
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
function ob_available_days(int $clientId, ?DateTimeImmutable $now = null): array
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
        foreach ($wins as $k => $w) {
            if (ob_slot_open($clientId, $s, $date, (string) $k, $now)) {
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

    $wins = ob_windows($clientId);
    $win  = $wins[$in['slot']] ?? null;
    if ($win === null) return ['ok' => false, 'error' => 'Please choose a day and a time.'];

    $requested = (string) $s['mode'] !== 'instant' || $area['check'] === 'unchecked';
    $pdo = db();
    try {
        $pdo->beginTransaction();
        // One online booking at a time per company: the slot check and the
        // insert can't interleave with another customer's.
        $pdo->prepare('SELECT client_id FROM online_booking_settings WHERE client_id = ? FOR UPDATE')->execute([$clientId]);
        if (!ob_slot_open($clientId, $s, $in['date'], $in['slot'], $now)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Sorry, that time has just been taken. Please choose another.', 'taken' => true];
        }

        $match = ob_match_customer($clientId, $in['email'], $in['phone']);
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
        }
        if ($in['notes'] !== '') $notes .= "\n\nCustomer's message: " . $in['notes'];

        $pdo->prepare(
            'INSERT INTO appointments
               (client_id, client_user_id, customer_id, title, appointment_date, appointment_time, duration_minutes,
                installation_address1, installation_address2, installation_town, installation_postcode,
                notes, status, slot_window, online_request)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'booked\', ?, ?)'
        )->execute([
            $clientId, $customerId, $in['name'], $in['date'], $win['time'], $win['duration'],
            $in['address1'], $in['address2'] !== '' ? $in['address2'] : null, $in['town'], ob_postcode_pretty($pc),
            $notes, $in['slot'], $requested ? 1 : 0,
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
        $pdo->commit();
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
    if ($c['from_name'] === '') $c['from_name'] = $c['name'] !== '' ? $c['name'] : 'YourBlinds';
    return $c;
}

/**
 * The customer's email for one stage: 'requested', 'confirmed' or 'declined'.
 * Pure — no database, no sending — so it can be tested. Returns subject + body.
 */
function ob_compose_customer_email(string $kind, array $co, string $name, string $date, string $slotLabel, string $address, string $bookLink = ''): array
{
    $d       = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateStr = $d ? $d->format('l j F Y') : $date;
    $company = $co['name'] !== '' ? $co['name'] : 'us';
    $hello   = 'Hello ' . (trim($name) !== '' ? trim($name) : 'there') . ",\n\n";
    $contact = trim(($co['phone'] !== '' ? 'call us on ' . $co['phone'] : '')
             . ($co['phone'] !== '' ? ' or ' : '') . 'reply to this email');
    $when    = "  {$dateStr}\n  {$slotLabel}\n" . ($address !== '' ? "  {$address}\n" : '') . "\n";
    $sign    = "Kind regards,\n" . ($co['name'] !== '' ? $co['name'] : 'The team');

    if ($kind === 'requested') {
        return [
            'subject' => 'We\'ve received your booking request' . ($co['name'] !== '' ? ' — ' . $co['name'] : ''),
            'body'    => $hello . "Thank you for booking with {$company}. You asked for:\n\n" . $when
                       . "We'll check our diary and confirm by email shortly. If you need to change anything, just {$contact}.\n\n" . $sign,
        ];
    }
    if ($kind === 'declined') {
        return [
            'subject' => 'About your booking request' . ($co['name'] !== '' ? ' — ' . $co['name'] : ''),
            'body'    => $hello . "Thank you for your booking request for {$dateStr}, {$slotLabel}.\n\n"
                       . "Unfortunately we can't make that time. "
                       . ($bookLink !== '' ? "Please choose another time here: {$bookLink}\nor {$contact}" : 'Please ' . $contact)
                       . " and we'll find a time that suits you.\n\n" . $sign,
        ];
    }
    return [
        'subject' => 'Your appointment is booked' . ($co['name'] !== '' ? ' with ' . $co['name'] : '') . ' — ' . ($d ? $d->format('j M Y') : $date),
        'body'    => $hello . "This confirms your appointment:\n\n" . $when
                   . "We'll aim to be with you within that time slot rather than at a fixed time. "
                   . "If you need to change or cancel, just {$contact}.\n\n" . $sign,
    ];
}

/** Email the customer about their online booking. Best-effort; returns whether it sent. */
function ob_email_customer(int $clientId, int $apptId, string $kind): bool
{
    try {
        $st = db()->prepare(
            'SELECT a.appointment_date, a.slot_window, a.installation_address1, a.installation_town, a.installation_postcode,
                    a.customer_id, c.name, c.email
               FROM appointments a
               JOIN customers c ON c.id = a.customer_id AND c.client_id = a.client_id
              WHERE a.id = ? AND a.client_id = ? LIMIT 1'
        );
        $st->execute([$apptId, $clientId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        // The address typed on the public form (a matched customer's record may hold an older one).
        $sub = db()->prepare('SELECT submitted FROM online_bookings WHERE appointment_id = ? AND client_id = ? LIMIT 1');
        $sub->execute([$apptId, $clientId]);
        $typed = json_decode((string) $sub->fetchColumn(), true);
        $to    = is_array($typed) && !empty($typed['email']) ? (string) $typed['email'] : (string) ($a['email'] ?? '');
        $name  = is_array($typed) && !empty($typed['name']) ? (string) $typed['name'] : (string) ($a['name'] ?? '');
        if (!$a || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $co   = ob_company_contact($clientId);
        $addr = implode(', ', array_filter([
            (string) $a['installation_address1'], (string) $a['installation_town'], (string) $a['installation_postcode'],
        ], static fn ($v) => trim($v) !== ''));
        $slug = (string) (ob_settings($clientId)['slug'] ?? '');
        $mail = ob_compose_customer_email($kind, $co, $name, (string) $a['appointment_date'],
            ob_window_label($clientId, (string) $a['slot_window']), $addr, $slug !== '' ? ob_link($slug) : '');

        require_once __DIR__ . '/../mailer.php';
        $opts = ['from_name' => $co['from_name'],
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
function ob_email_company(int $clientId, int $apptId, bool $requested): bool
{
    try {
        $co = ob_company_contact($clientId);
        $to = trim((string) (ob_settings($clientId)['notify_email'] ?? ''));
        if ($to === '') $to = $co['reply_to'] !== '' ? $co['reply_to'] : $co['email'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $st = db()->prepare(
            'SELECT a.appointment_date, a.slot_window, a.installation_postcode, a.title
               FROM appointments a WHERE a.id = ? AND a.client_id = ? LIMIT 1'
        );
        $st->execute([$apptId, $clientId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return false;

        $d    = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $a['appointment_date']);
        $when = ($d ? $d->format('D j M Y') : $a['appointment_date']) . ', ' . ob_window_label($clientId, (string) $a['slot_window']);
        $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');
        $subject = ($requested ? 'Online booking request: ' : 'New online booking: ') . $a['title'] . ' — ' . $when;
        $body = "A customer has booked online.\n\n"
              . "  {$a['title']}\n  {$when}\n  {$a['installation_postcode']}\n\n"
              . ($requested
                  ? "It's in your calendar and the time slot is held. Open it to confirm or decline — the customer is waiting to hear:\n"
                  : "It's in your calendar and the customer has been sent a confirmation:\n")
              . $base . '/calendar/view.php?id=' . $apptId . "\n\n"
              . "It isn't assigned to anyone yet.\n";

        require_once __DIR__ . '/../mailer.php';
        return mailer_send($to, $subject, $body, null, null,
            ['from_name' => 'YourBlinds', 'log' => ['client_id' => $clientId, 'kind' => 'online_booking']]);
    } catch (Throwable $e) {
        error_log('ob_email_company(' . $apptId . '): ' . $e->getMessage());
        return false;
    }
}
