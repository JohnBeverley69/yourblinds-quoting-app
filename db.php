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

    // Read via env() (bootstrap's helper) so config still resolves on hosts
    // that disable putenv()/getenv() — env() also reads the $_ENV/$_SERVER
    // arrays the .env loader fills.
    $host    = (string) (env('DB_HOST') ?? '');
    $name    = (string) (env('DB_NAME') ?? '');
    $user    = (string) (env('DB_USER') ?? '');
    $pass    = env('DB_PASS');                          // null when unset
    $port    = (string) (env('DB_PORT', '3306') ?? '3306');
    $charset = (string) (env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4');

    if ($host === '' || $name === '' || $user === '' || $pass === null) {
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

    // Align the DB session's time zone with the app's. bootstrap.php sets PHP to
    // Europe/London, but MySQL was left on the server zone (UTC on Cloudways), so
    // NOW()/CURRENT_TIMESTAMP were written in UTC while PHP formatted those naive
    // strings as London — every stored timestamp then displayed an hour behind
    // during BST. We set a NUMERIC offset computed from PHP's current zone rather
    // than a named zone ('Europe/London'), because managed hosts often don't load
    // MySQL's named-timezone tables (which makes SET time_zone='Europe/London'
    // throw). The offset already reflects DST as of now; connections are per
    // request (not persistent), so a request after a DST switch gets the new
    // offset. Guarded — a failure here must never break the connection.
    try {
        $off  = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTimeImmutable('now'));
        $abs  = abs($off);
        $tz   = sprintf('%s%02d:%02d', $off < 0 ? '-' : '+', intdiv($abs, 3600), intdiv($abs % 3600, 60));
        $pdo->exec("SET time_zone = '$tz'");
    } catch (Throwable $e) { /* leave the server default if this can't be set */ }

    return $pdo;
}
