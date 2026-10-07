<?php
declare(strict_types=1);

/**
 * "How it works" tour stats — anonymous view/play/progress counting.
 *
 * The tour page (how-it-works.php) beacons small events to
 * api/tour-event.php; Master admin → Tour stats reads them back.
 *
 * Privacy: no cookies, no IP address, nothing about the person. A "visit" is
 * one page load (a random id made in the browser and forgotten when the tab
 * closes), so refreshes count as new visits. Source is just "facebook",
 * "direct", a ?src= tag or the referring site's host name.
 *
 * Self-creating table; best-effort throughout — stats never break the page.
 */

const TOUR_EVENTS = ['view', 'play', 'step', 'finish', 'cta_signup', 'cta_price'];

function tour_stats_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tour_events (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visit_id   CHAR(16)         NOT NULL,
            event      VARCHAR(16)      NOT NULL,
            step       TINYINT UNSIGNED NOT NULL DEFAULT 0,
            source     VARCHAR(40)      NOT NULL DEFAULT \'\',
            device     VARCHAR(8)       NOT NULL DEFAULT \'\',
            created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_visit_event (visit_id, event, step),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

/** Where a visit came from: a ?src= tag wins, else the referrer's site. */
function tour_stats_source(string $src, string $referrer): string
{
    $src = strtolower(trim($src));
    if ($src !== '' && preg_match('/^[a-z0-9_-]{1,30}$/', $src)) {
        return in_array($src, ['fb', 'facebook'], true) ? 'facebook' : $src;
    }
    $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    if ($host === '') return 'direct';
    if (preg_match('/(^|\.)(facebook\.com|fb\.com|fb\.me|messenger\.com)$/', $host)) return 'facebook';
    if (preg_match('/(^|\.)yourblinds\.uk$/', $host)) return 'our site';
    if (preg_match('/(^|\.)google\./', $host)) return 'google';
    return substr(preg_replace('/^www\./', '', $host), 0, 40);
}

/** Rough device class from the user agent (nothing stored beyond this). */
function tour_stats_device(string $ua): string
{
    if (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) return 'tablet';
    if (preg_match('/Mobi|iPhone|Android/i', $ua)) return 'phone';
    return 'desktop';
}

/** Known crawlers / link-preview bots (Facebook's previewer included). */
function tour_stats_is_bot(string $ua): bool
{
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse/i', $ua);
}
