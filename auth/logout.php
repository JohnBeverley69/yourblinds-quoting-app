<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/middleware.php';

// Signing out is a POST carrying the CSRF token (the sidebar / factory header
// "Sign out" links submit a hidden form). It used to work on a plain GET, so
// any other website could sign users out by embedding this URL. A GET — a
// typed URL, or a browser with JavaScript off — now shows a one-button
// confirm page instead.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if (!is_logged_in()) {
        header('Location: /auth/login.php');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Sign out · YourBlinds</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:420px;margin:4rem auto;padding:0 16px;text-align:center">'
       . '<h1 style="font-size:1.3rem">Sign out of YourBlinds?</h1>'
       . '<form method="post" action="/auth/logout.php">' . csrf_field()
       . '<button type="submit" style="font-size:1rem;padding:.6rem 1.4rem">Sign out</button></form>'
       . '<p><a href="/">Cancel</a></p></body>';
    exit;
}

csrf_check();

// Wipe session data, drop the cookie, destroy the session.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path']     ?? '/',
            'domain'   => $params['domain']   ?? '',
            'secure'   => $params['secure']   ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

header('Location: /auth/login.php');
exit;
