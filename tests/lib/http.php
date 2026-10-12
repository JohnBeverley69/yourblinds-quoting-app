<?php
declare(strict_types=1);

/**
 * A browser stand-in for the page tests: one cookie jar per "person", talking
 * to the PHP built-in server the runner started (php -S 127.0.0.1:YB_TEST_PORT).
 *
 * Every response also carries any PHP warnings / errors the server logged while
 * producing it, so a page that "works" but spews warnings still fails.
 */

final class TResponse
{
    public int $status;
    public string $body;
    public ?string $location;
    /** @var string[] PHP warnings/errors logged while serving this request */
    public array $phpErrors;
    public string $url;

    public function __construct(string $url, int $status, string $body, ?string $location, array $phpErrors)
    {
        $this->url = $url; $this->status = $status; $this->body = $body;
        $this->location = $location; $this->phpErrors = $phpErrors;
    }
}

final class YbHttp
{
    private $ch;
    private string $base;
    public ?string $who = null;

    public function __construct()
    {
        $this->base = 'http://127.0.0.1:' . getenv('YB_TEST_PORT');
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE     => '',          // in-memory cookie jar for this person
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
    }

    /** The cookie jar, one Netscape-format line per cookie. */
    public function cookies(): array
    {
        return (array) curl_getinfo($this->ch, CURLINFO_COOKIELIST);
    }

    public function get(string $path): TResponse
    {
        return $this->request('GET', $path, null);
    }

    public function post(string $path, array $fields): TResponse
    {
        return $this->request('POST', $path, $fields);
    }

    private function request(string $method, string $path, ?array $fields): TResponse
    {
        $logFile = (string) getenv('YB_TEST_SERVER_LOG');
        clearstatcache();
        $before = is_file($logFile) ? filesize($logFile) : 0;

        $url = $this->base . $path;
        curl_setopt($this->ch, CURLOPT_URL, $url);
        if ($method === 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }
        $raw = curl_exec($this->ch);
        if ($raw === false) fail("HTTP $method $path failed: " . curl_error($this->ch));
        $status = (int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        $hsize  = (int) curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $head   = substr((string) $raw, 0, $hsize);
        $body   = substr((string) $raw, $hsize);
        $loc    = preg_match('/^Location:\s*(.+)$/mi', $head, $m) ? trim($m[1]) : null;

        clearstatcache();
        $errors = [];
        if (is_file($logFile) && filesize($logFile) > $before) {
            $fh = fopen($logFile, 'rb');
            fseek($fh, $before);
            $new = (string) stream_get_contents($fh);
            fclose($fh);
            foreach (preg_split('/\R/', $new) as $line) {
                if (preg_match('/PHP (Warning|Fatal error|Parse error|Recoverable fatal error)|Uncaught /', $line)) {
                    $errors[] = trim($line);
                }
            }
        }
        // Fatals that reach the page body (display_errors off should prevent it, but be sure).
        if (preg_match('/<b>(Fatal error|Warning|Parse error)<\/b>:/', $body, $m)) $errors[] = "in page body: {$m[1]}";
        return new TResponse($url, $status, $body, $loc, $errors);
    }

    /** The CSRF token from a page's form (<input name="_csrf" value="…">) or meta tag. */
    public static function csrf(string $html): string
    {
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) return html_entity_decode($m[1]);
        if (preg_match('/value="([^"]+)"\s+name="_csrf"/', $html, $m)) return html_entity_decode($m[1]);
        if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m)) return html_entity_decode($m[1]);
        fail('no CSRF token on page');
        return '';
    }

    /** Log in through the real login form. */
    public function login(string $username, string $password = 'yb-test-password'): void
    {
        $page = $this->get('/auth/login.php');
        $r = $this->post('/auth/login.php', [
            '_csrf' => self::csrf($page->body), 'identifier' => $username, 'password' => $password,
        ]);
        if ($r->status !== 302) {
            $msg = preg_match('/class="[^"]*alert[^"]*"[^>]*>(.*?)</s', $r->body, $m) ? trim(strip_tags($m[1])) : '';
            fail("login as $username failed (HTTP {$r->status}) $msg");
        }
        $this->who = $username;
    }
}

/** A fresh person, logged in. */
function as_user(string $username): YbHttp
{
    $h = new YbHttp();
    $h->login($username);
    return $h;
}

/** The page opened (200) with no PHP warnings or errors. */
function check_page_ok(TResponse $r, string $what = ''): void
{
    $what = $what !== '' ? $what : $r->url;
    if ($r->phpErrors) fail("$what: PHP problems while rendering:\n    " . implode("\n    ", array_slice($r->phpErrors, 0, 5)));
    if ($r->status !== 200) fail("$what: expected the page to open (200), got HTTP {$r->status}" . ($r->location ? " → {$r->location}" : ''));
}

/** The page refused this person: 403 / 404, or a redirect to the login page. */
function check_refused(TResponse $r, string $what = ''): void
{
    $what = $what !== '' ? $what : $r->url;
    if ($r->phpErrors) fail("$what: PHP problems:\n    " . implode("\n    ", array_slice($r->phpErrors, 0, 5)));
    if (in_array($r->status, [401, 403, 404], true)) return;
    if ($r->status === 302 && $r->location !== null && strpos($r->location, '/auth/login.php') !== false) return;
    fail("$what: expected to be refused, got HTTP {$r->status}" . ($r->location ? " → {$r->location}" : '') . ' (the page let them in)');
}

/** This person's CSRF token (one per session — the same one every form carries). */
function t_token(YbHttp $h): string
{
    $r = $h->get('/api/csrf.php');
    $j = json_decode($r->body, true);
    if (!is_array($j) || empty($j['token'])) fail('could not get a CSRF token (HTTP ' . $r->status . ')');
    return (string) $j['token'];
}

/** POST a form the way the browser would (CSRF token added). */
function t_submit(YbHttp $h, string $path, array $fields): TResponse
{
    return $h->post($path, ['_csrf' => t_token($h)] + $fields);
}

/** This person's PHP session id (the PHPSESSID cookie in their jar). */
function t_session_id(YbHttp $h): string
{
    foreach ($h->cookies() as $line) {
        $f = explode("\t", $line);
        if (($f[5] ?? '') === session_name() || ($f[5] ?? '') === 'PHPSESSID') return (string) ($f[6] ?? '');
    }
    fail('no session cookie');
    return '';
}

/**
 * Make the server re-check this person's account on their next request, as it
 * does every 30 seconds (session_still_valid), without waiting 30 seconds:
 * winds auth_checked_at back in their session file.
 */
function t_expire_auth_check(YbHttp $h): void
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yb_sessions' . DIRECTORY_SEPARATOR . 'sess_' . t_session_id($h);
    if (!is_file($file)) fail("session file not found: $file");
    $data = (string) file_get_contents($file);
    $new = preg_replace('/auth_checked_at\|i:\d+;/', 'auth_checked_at|i:0;', $data, 1, $n);
    if ($n !== 1) fail('auth_checked_at not in the session');
    file_put_contents($file, $new);
}
