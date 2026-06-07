<?php
declare(strict_types=1);

/**
 * Calendar sync setup — RETIRED.
 *
 * The personal ICS subscription feature (Google / Apple / Outlook /
 * phone) was removed: third-party calendars poll too slowly for fitting
 * work (Google up to ~12h) and the in-app calendar is the source of
 * truth. This stub keeps the old URL from 404-ing for anyone who
 * bookmarked it and bounces them back to the calendar. The matching
 * feed endpoint (feed.php) now returns 410 Gone. (Original page is in
 * git history if it's ever wanted back.)
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

header('Location: /calendar/index.php');
exit;
