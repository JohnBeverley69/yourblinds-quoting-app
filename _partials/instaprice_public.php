<?php
declare(strict_types=1);

/**
 * Public (anonymous) InstaPrice support — the lead-gen hook.
 *
 * InstaPrice doubles as a shop window: anyone can price against the public
 * SHOWCASE catalogue without logging in. The moment they try to do more —
 * save/send a quote, add a customer — they hit the signup gate (welcome.php).
 *
 * The showcase catalogue is ONE designated tenant's real catalogue: by
 * default the factory (YourBlinds' own), whose prices the owner is happy to
 * show publicly. Override with the INSTAPRICE_PUBLIC_CLIENT_ID env var.
 *
 * SECURITY: the anonymous path is READ-ONLY and hard-scoped to the single
 * showcase client. Only the read-only pricing endpoints (product-data,
 * fabrics-search, preview) and the InstaPrice page itself may use it — never
 * anything that writes or that reads another tenant's data.
 */

require_once __DIR__ . '/../auth/middleware.php';

/**
 * The tenant whose catalogue the public InstaPrice prices against.
 */
function instaprice_showcase_client_id(): int
{
    $env = function_exists('env') ? env('INSTAPRICE_PUBLIC_CLIENT_ID') : null;
    if ($env !== null && (int) $env > 0) {
        return (int) $env;
    }
    return factory_client_id();
}

/**
 * True when the current request is an anonymous visitor using the public
 * showcase (i.e. nobody is logged in). Logged-in tenants always get their
 * own catalogue, exactly as before.
 */
function instaprice_is_public(): bool
{
    return !is_logged_in();
}

/**
 * Resolve the client id for the read-only InstaPrice-family APIs, allowing an
 * anonymous public showcase mode.
 *
 *   - Logged in           -> their own client_id (unchanged behaviour).
 *   - Anonymous + public=1 -> the showcase client, read-only.
 *   - Anonymous, no flag   -> 401 JSON, then exit.
 *
 * Returns [int $clientId, bool $isPublic]. MUST only be called by endpoints
 * that are strictly read-only.
 */
function instaprice_api_client(): array
{
    if (is_logged_in()) {
        // Per-tenant response — never let a shared cache store it.
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, private, max-age=0');
        }
        return [(int) current_user()['client_id'], false];
    }

    if ((int) ($_GET['public'] ?? 0) === 1) {
        instaprice_public_throttle();
        return [instaprice_showcase_client_id(), true];
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Not authorised — please sign in.']);
    exit;
}

/**
 * Anonymous public InstaPrice calls are unauthenticated, so without a limit a
 * script could scrape the whole showcase price grid or burn server CPU. Cap
 * each IP at a generous rate for a person using the page (live pricing fires
 * on most changes); a scraper hits 429. File-based counter per 10-minute
 * bucket — no DB writes on this hot path. Best-effort: any file error allows.
 */
function instaprice_public_throttle(int $max = 400, int $window = 600): void
{
    $ip     = function_exists('client_ip') ? client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $bucket = intdiv(time(), $window);
    $file   = rtrim(sys_get_temp_dir(), '/' . DIRECTORY_SEPARATOR) . '/yb_ipub_' . md5($ip . '|' . $bucket);
    $fh = @fopen($file, 'c+');
    if (!$fh) return;
    $n = 0;
    if (@flock($fh, LOCK_EX)) {
        $n = (int) stream_get_contents($fh) + 1;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) $n);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    if ($n > $max) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: ' . $window);
        echo json_encode(['error' => 'Too many requests — please slow down and try again shortly.']);
        exit;
    }
}
