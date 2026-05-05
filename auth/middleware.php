<?php
declare(strict_types=1);

// YourBlinds auth middleware — guards, CSRF, rate limiting.
// Pages should require this AFTER bootstrap.php so that $_SESSION is started.

if (!function_exists('e')) {
    function e(?string $s): string
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ---------------------------------------------------------------------------
// Session helpers
// ---------------------------------------------------------------------------
function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['client_id'], $_SESSION['role']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'      => (int) $_SESSION['user_id'],
        'client_id'    => (int) $_SESSION['client_id'],
        'role'         => (string) $_SESSION['role'],
        'company_name' => (string) ($_SESSION['company_name'] ?? ''),
        'full_name'    => (string) ($_SESSION['full_name'] ?? ''),
    ];
}

// ---------------------------------------------------------------------------
// Route guards
// ---------------------------------------------------------------------------
function requireLogin(): void
{
    if (is_logged_in()) {
        return;
    }
    $next = $_SERVER['REQUEST_URI'] ?? '';
    $qs   = $next !== '' ? '?next=' . urlencode($next) : '';
    header('Location: /auth/login.php' . $qs);
    exit;
}

function requireRole(array $roles): void
{
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>403 Forbidden</title>'
           . '<h1>403 Forbidden</h1><p>You do not have permission to view this page.</p>';
        exit;
    }
}

function requireAdmin(): void
{
    requireRole(['admin']);
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $supplied = $_POST['_csrf'] ?? '';
    if (!is_string($supplied) || !hash_equals(csrf_token(), $supplied)) {
        http_response_code(419);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>'
           . '<h1>Session expired</h1>'
           . '<p>This page has expired. <a href="javascript:history.back()">Go back</a> and try again.</p>';
        exit;
    }
}

// ---------------------------------------------------------------------------
// IP / rate limiting
// ---------------------------------------------------------------------------
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

function rate_limited(string $ip, int $maxAttempts = 5, int $windowSeconds = 600): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE ip_address = ?
            AND successful = 0
            AND created_at > (NOW() - INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, $windowSeconds]);
    return (int) $stmt->fetchColumn() >= $maxAttempts;
}

function record_login_attempt(string $ip, string $identifier, bool $success): void
{
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (ip_address, identifier, successful, user_agent)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$ip, $identifier, $success ? 1 : 0, $ua]);
}

// ---------------------------------------------------------------------------
// Post-login redirect
// Honours an optional ?next=/safe/path parameter (relative URLs only).
// ---------------------------------------------------------------------------
function redirect_after_login(): void
{
    $next = $_POST['next'] ?? $_GET['next'] ?? '';
    if (is_string($next) && $next !== ''
        && str_starts_with($next, '/')
        && !str_starts_with($next, '//')
        && strpos($next, "\r") === false
        && strpos($next, "\n") === false
    ) {
        header('Location: ' . $next);
        exit;
    }
    $role   = $_SESSION['role'] ?? '';
    $target = $role === 'admin' ? '/admin/index.php' : '/quote-builder/index.php';
    header('Location: ' . $target);
    exit;
}
