<?php
declare(strict_types=1);

/**
 * Public beacon for the "How it works" tour stats (see _partials/tour_stats.php).
 *
 * POST JSON {v: visit id (16 hex), e: event, s: step, r: referrer, src: tag}.
 * Always answers 204 — a stats hiccup must never surface on the tour.
 */

require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../_partials/tour_stats.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
$in = json_decode((string) file_get_contents('php://input', false, null, 0, 4096), true);

if (!tour_stats_is_bot($ua) && is_array($in)) {
    $visit = (string) ($in['v'] ?? '');
    $event = (string) ($in['e'] ?? '');
    $step  = (int) ($in['s'] ?? 0);
    if (preg_match('/^[a-f0-9]{16}$/', $visit) && in_array($event, TOUR_EVENTS, true) && $step >= 0 && $step <= 50) {
        try {
            $pdo = db();
            tour_stats_ensure($pdo);
            $pdo->prepare(
                'INSERT IGNORE INTO tour_events (visit_id, event, step, source, device) VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $visit, $event, $step,
                tour_stats_source((string) ($in['src'] ?? ''), (string) ($in['r'] ?? '')),
                tour_stats_device($ua),
            ]);
        } catch (Throwable $e) {
            error_log('tour-event: ' . $e->getMessage());
        }
    }
}

http_response_code(204);
