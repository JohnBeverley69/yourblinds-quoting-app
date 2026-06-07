<?php
declare(strict_types=1);

/**
 * Calendar ICS subscription feed — RETIRED.
 *
 * The per-user phone / Google / Apple calendar subscription was removed:
 * third-party calendars poll on their own slow schedule (Google ~12h),
 * which isn't fast enough for fitting work, and the in-app calendar is
 * the single source of truth.
 *
 * Returns 410 Gone so any calendar app still subscribed to an old URL
 * stops polling and drops the dead calendar, rather than silently
 * serving stale jobs forever. (The old feature lived in git history if
 * it's ever wanted back.)
 */

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "This calendar subscription has been retired. Please use the YourBlinds in-app calendar.\n";
