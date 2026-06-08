<?php
declare(strict_types=1);

// YourBlinds bootstrap — required by every page.
// Loads .env, configures error handling, starts a hardened session,
// and exposes db() via db.php.
//
// In production, place this file OUTSIDE the public web docroot,
// or ensure the web server only serves the intended public/ subdirectory.

define('APP_ROOT', __DIR__);

// ---------------------------------------------------------------------------
// .env loader (no Composer dependency)
// Existing environment variables (set by the host / web server) take
// precedence over .env — so production can override without touching files.
// ---------------------------------------------------------------------------
(function (): void {
    $envFile = APP_ROOT . '/.env';
    if (!is_readable($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last  = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }
        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            // putenv() is disabled on some managed hosts (e.g. Cloudways) via
            // disable_functions — so guard it. $_ENV / $_SERVER (read by the
            // env() helper below) are the real source of truth either way.
            if (function_exists('putenv')) {
                @putenv("$key=$val");
            }
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }
    }
})();

function env(string $key, ?string $default = null): ?string
{
    // getenv() first (real host env vars / putenv where available), then the
    // arrays the .env loader populates — so config still works on hosts that
    // disable putenv().
    $v = getenv($key);
    if ($v !== false)                     return $v;
    if (array_key_exists($key, $_ENV))    return (string) $_ENV[$key];
    if (array_key_exists($key, $_SERVER)) return (string) $_SERVER[$key];
    return $default;
}

// ---------------------------------------------------------------------------
// Third-party API keys exposed as constants for readability at call sites.
// Empty string when unset so consumers can branch on `=== ''` rather than null.
// ---------------------------------------------------------------------------
define('GOOGLE_MAPS_API_KEY', (string) env('GOOGLE_MAPS_API_KEY', ''));
define('POSTCODER_API_KEY',   (string) env('POSTCODER_API_KEY',   ''));

// ---------------------------------------------------------------------------
// Timezone & error reporting
// ---------------------------------------------------------------------------
date_default_timezone_set(env('APP_TIMEZONE', 'Europe/London') ?? 'Europe/London');

$appEnv = strtolower(env('APP_ENV', 'production') ?? 'production');
if ($appEnv === 'development' || $appEnv === 'dev' || $appEnv === 'local') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}
unset($appEnv);

// ---------------------------------------------------------------------------
// Session — hardened defaults. Pages that need auth should additionally call
// session_regenerate_id(true) on login to defeat session fixation.
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Lax');
    ini_set('session.use_only_cookies', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// ---------------------------------------------------------------------------
// Composer autoloader — present once `composer install` has been run.
// Pages that depend on a Composer package (e.g. PHPMailer) should defensively
// check class_exists() before using it, so a fresh checkout without vendor/
// fails with a clear error rather than a fatal.
// ---------------------------------------------------------------------------
$composerAutoload = APP_ROOT . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
}
unset($composerAutoload);

// ---------------------------------------------------------------------------
// Cache-busting asset URLs. Appends the file's last-modified time as a
// version query (?v=…) so browsers and the edge cache fetch a fresh copy
// whenever the asset changes — the mtime updates on every deploy (git pull),
// so new CSS/JS shows up immediately without anyone hard-refreshing. Falls
// back to the bare path if the file can't be stat'd. Memoised per request.
// Usage in a page head: asset('/app.css') returns e.g. /app.css?v=1718000000
// ---------------------------------------------------------------------------
function asset(string $path): string
{
    static $cache = [];
    if (isset($cache[$path])) {
        return $cache[$path];
    }
    $v = @filemtime(APP_ROOT . '/' . ltrim($path, '/'));
    return $cache[$path] = $v ? $path . '?v=' . $v : $path;
}

require_once APP_ROOT . '/db.php';
