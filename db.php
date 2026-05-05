<?php
declare(strict_types=1);

// YourBlinds DB access — lazy PDO singleton.
// First call to db() opens the connection using credentials from the
// environment (loaded from .env by bootstrap.php). Subsequent calls
// return the same instance.

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = getenv('DB_HOST') ?: '';
    $name    = getenv('DB_NAME') ?: '';
    $user    = getenv('DB_USER') ?: '';
    $pass    = getenv('DB_PASS');
    $port    = getenv('DB_PORT') ?: '3306';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    if ($host === '' || $name === '' || $user === '' || $pass === false) {
        throw new RuntimeException(
            'Database configuration missing: DB_HOST, DB_NAME, DB_USER and DB_PASS '
            . 'must be set in .env or the host environment.'
        );
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host, $port, $name, $charset
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];

    $pdo = new PDO($dsn, $user, (string) $pass, $options);
    return $pdo;
}
