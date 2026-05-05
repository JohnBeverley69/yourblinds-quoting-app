<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

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
