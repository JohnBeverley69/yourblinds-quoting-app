<?php
declare(strict_types=1);

/**
 * Fresh CSRF token for the signed-in session, as JSON.
 *
 * The offline outbox (_partials/offline_guard.php) sends changes that were
 * saved on the tablet with no signal. By then the page's token may be stale:
 * the session was renewed, or the user signed in again in another tab. It
 * fetches the current token here and retries once.
 *
 * Same-origin only: no CORS headers, so another site can't read the token.
 * Signed out: 401 {"login": true} rather than a redirect to the login page.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['login' => true]);
    exit;
}

echo json_encode([
    'token'   => csrf_token(),
    'user_id' => (int) (current_user()['user_id'] ?? 0),
]);
