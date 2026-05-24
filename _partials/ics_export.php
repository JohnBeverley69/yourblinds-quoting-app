<?php
declare(strict_types=1);

/**
 * iCalendar (RFC 5545) generator. Tiny — just what the calendar
 * subscription feed needs.
 *
 * The format looks simple but has three nasty gotchas you have to
 * get right or the consuming app (Google Calendar especially)
 * silently drops events:
 *
 *   1. Line endings MUST be CRLF (\r\n), not LF.
 *   2. Lines MUST be folded at 75 octets — long lines split with
 *      \r\n followed by a single leading space.
 *   3. Text values MUST escape backslash, comma, semicolon, and
 *      newline. Get any of these wrong and an address with a
 *      comma in it (which is most of them) breaks the event.
 *
 * Helpers below do all three correctly. Public API:
 *
 *   ics_escape($text)
 *   ics_fold($line)
 *   ics_fmt_utc(DateTimeInterface $dt)
 *   ics_build($name, $events)         // returns the full text/calendar body
 *
 * Each $event entry is an array:
 *   [
 *     'uid'         => 'unique-stable-string@yourblinds.uk',
 *     'summary'     => 'Mrs Smith — Roller Blinds survey',
 *     'description' => "Customer: Mrs Smith\nPhone: 07700 900000",
 *     'location'    => '12 High Street, Bristol, BS1 4AB',
 *     'start'       => DateTimeImmutable (UTC or with explicit TZ),
 *     'end'         => DateTimeImmutable,
 *     'url'         => optional deep-link to the event in YB
 *   ]
 */

/**
 * Escape a text value per RFC 5545 §3.3.11.
 *   \  →  \\
 *   ,  →  \,
 *   ;  →  \;
 *   \n →  \n  (literal backslash-n, two characters)
 *   \r →  (strip)
 */
function ics_escape(string $text): string
{
    $text = str_replace("\r", '', $text);
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
}

/**
 * Fold a content line to 75 octets per RFC 5545 §3.1. Counts
 * octets, not characters — UTF-8 multi-byte runes count as their
 * byte length. Continuation lines start with a single space.
 */
function ics_fold(string $line): string
{
    // Fast path: short lines pass through unchanged.
    if (strlen($line) <= 75) return $line;

    $out   = '';
    $first = true;
    while (strlen($line) > 0) {
        $take = $first ? 75 : 74; // continuation lines have a leading space, so they fit 74 of the original
        $chunk = substr($line, 0, $take);
        $line  = (string) substr($line, $take);
        if ($first) {
            $out  = $chunk;
            $first = false;
        } else {
            $out .= "\r\n " . $chunk;
        }
    }
    return $out;
}

/**
 * Format a datetime as an ICS UTC timestamp (e.g. 20260524T093000Z).
 * Caller is responsible for ensuring the input is UTC; we coerce
 * via setTimezone() to be safe.
 */
function ics_fmt_utc(DateTimeInterface $dt): string
{
    $utc = (clone $dt)->setTimezone(new DateTimeZone('UTC'));
    return $utc->format('Ymd\THis\Z');
}

/**
 * Build the full ICS document text. Caller writes it to the response
 * with Content-Type: text/calendar; charset=utf-8.
 */
function ics_build(string $calendarName, array $events): string
{
    $now = ics_fmt_utc(new DateTimeImmutable('now', new DateTimeZone('UTC')));

    // PRODID is recommended unique per generator. -//Company//Product//EN
    // is the standard pattern.
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//YourBlinds//Trade Calendar 1.0//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . ics_escape($calendarName),
        'X-WR-CALDESC:' . ics_escape($calendarName . ' — YourBlinds appointments'),
        // Hint Google Calendar to poll hourly. Some clients honour
        // this, most still poll on their own schedule.
        'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
        'X-PUBLISHED-TTL:PT1H',
    ];

    foreach ($events as $ev) {
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . ics_escape((string) $ev['uid']);
        $lines[] = 'DTSTAMP:' . $now;
        $lines[] = 'DTSTART:' . ics_fmt_utc($ev['start']);
        $lines[] = 'DTEND:'   . ics_fmt_utc($ev['end']);
        $lines[] = 'SUMMARY:' . ics_escape((string) ($ev['summary'] ?? ''));
        if (!empty($ev['description'])) {
            $lines[] = 'DESCRIPTION:' . ics_escape((string) $ev['description']);
        }
        if (!empty($ev['location'])) {
            $lines[] = 'LOCATION:' . ics_escape((string) $ev['location']);
        }
        if (!empty($ev['url'])) {
            $lines[] = 'URL:' . ics_escape((string) $ev['url']);
        }
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';

    // Fold each line, then join with CRLF. Order matters — fold
    // BEFORE joining so we don't accidentally fold the CRLF itself.
    $folded = array_map('ics_fold', $lines);
    return implode("\r\n", $folded) . "\r\n";
}
