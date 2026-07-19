<?php
declare(strict_types=1);

/**
 * Factory · WiFi scan-in.
 *
 * A WiFi scanner at a bench fires the worksheet barcode straight at this URL —
 * no PC there, no login. The code says which blind and which part, so this
 * finishes that part and the office board shows it move.
 *
 *   GET or POST  ?key=<secret>&c=<code>[&s=<scanner id>]
 *
 * NO login and NO CSRF on purpose — it's a machine endpoint. The secret in the
 * URL is the only guard, so the whole thing does exactly one narrow thing
 * (finish a production part) and returns a short line of plain text. Every hit
 * is logged, which also de-dupes an accidental double pull of the trigger.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../_partials/blind_jobs.php';
require __DIR__ . '/../_partials/qr.php';
require __DIR__ . '/../_partials/factory_kv.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$pdo = db();

$reply = static function (int $status, string $text) {
    http_response_code($status);
    echo $text . "\n";
    exit;
};

$log = static function (string $result, ?string $detail, ?string $code, ?array $parsed, ?string $source) use ($pdo): void {
    try {
        $pdo->prepare(
            'INSERT INTO factory_scan_log (code, quote_item_id, unit_no, stream_digit, result, detail, source)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr((string) $code, 0, 20),
            $parsed[0] ?? null, $parsed[1] ?? null, $parsed[2] ?? null,
            $result, $detail !== null ? mb_substr($detail, 0, 255) : null,
            $source !== null ? mb_substr($source, 0, 60) : null,
        ]);
    } catch (Throwable $e) { /* logging must never break a scan */ }
};

$key    = (string) ($_REQUEST['key'] ?? '');
$source = (string) ($_REQUEST['s'] ?? $_REQUEST['scanner'] ?? '');

// Different WiFi scanners send the code differently: a query param, a form
// field, a JSON body, or the bare code as the whole body. Accept all of them so
// whichever this scanner does, it lands. Query/form first, then the raw body.
$rawBody = file_get_contents('php://input') ?: '';
$code = trim((string) ($_REQUEST['c'] ?? $_REQUEST['code'] ?? ''));
if ($code === '' && $rawBody !== '') {
    $j = json_decode($rawBody, true);
    if (is_array($j)) {
        // 'msg' is the NetumScan wireless family — it posts {"id":"<device>","msg":"<code>"}.
        foreach (['c', 'code', 'data', 'barcode', 'qr', 'value', 'msg'] as $k) {
            if (isset($j[$k]) && is_scalar($j[$k])) { $code = trim((string) $j[$k]); break; }
        }
        // Same scanners carry their own id in the body, not the URL — use it as the
        // bench id so the scan log's scanner column isn't blank.
        if ($source === '' && isset($j['id']) && is_scalar($j['id'])) { $source = trim((string) $j['id']); }
    } elseif (preg_match('/^\s*[0-9]{8,9}\s*$/', $rawBody)) {
        $code = trim($rawBody);           // the scanner just sent the bare code
    }
}

// A setup/config QR (the scanner's OWN configuration — AT+URL, AT+RAP …) can get
// scanned as if it were a barcode. That payload carries the scan KEY, so it must
// never be written to the log. Detect the AT-command shape and drop it whole,
// recording nothing but a redacted note.
if (preg_match('~\bAT[+_]~i', $rawBody . ' ' . $code)) {
    $log('bad_code', 'config/setup scan ignored (redacted)', null, null, $source);
    $reply(400, 'CONFIG SCAN — IGNORED');
}

// A trigger held down (or a multi-read) can pack the same code in several times,
// e.g. "002924012 002924012 002924012". Reduce to the first whole 8–9 digit code
// so an over-eager scan still completes instead of being dropped as unreadable.
if ($code !== '' && !preg_match('/^\d{8,9}$/', $code) && preg_match('/\b\d{8,9}\b/', $code, $m)) {
    $code = $m[0];
}

// The secret. Timing-safe, and a missing/blank configured key can never match.
$expected = fx_scan_key($pdo);
if ($expected === '' || !hash_equals($expected, $key)) {
    $log('bad_key', null, $code, null, $source);
    $reply(403, 'NO');
}

$parsed = qr_parse_code($code);
if ($parsed === null) {
    // Capture HOW the scanner sent things, so the very first live test tells us
    // the payload format instead of just failing silently. This is the one place
    // we don't yet know a new scanner's shape.
    $seen = $_SERVER['REQUEST_METHOD'] . ' q=' . http_build_query(array_diff_key($_REQUEST, ['key' => 1]))
          . ' body=' . mb_substr($rawBody, 0, 120);
    $log('bad_code', $seen, $code !== '' ? $code : '(empty)', null, $source);
    $reply(400, 'BAD CODE');
}
[$itemId, $unitNo, $streamDigit] = $parsed;

// De-dupe: the same code landing again within 10s is a double pull, not a second
// blind. Completing an already-done part is harmless, but this keeps the log
// clean and protects a future per-stage mode where a double really would skip.
try {
    $dup = $pdo->prepare(
        "SELECT 1 FROM factory_scan_log
          WHERE quote_item_id = ? AND unit_no = ? AND stream_digit = ?
            AND result IN ('ok','already') AND created_at > (NOW() - INTERVAL 10 SECOND)
          LIMIT 1"
    );
    $dup->execute([$itemId, $unitNo, $streamDigit]);
    if ($dup->fetchColumn()) {
        $log('dup', 'within 10s', $code, $parsed, $source);
        $reply(200, 'OK (already just scanned)');
    }
} catch (Throwable $e) { /* table missing? fall through */ }

try {
    $res = bj_complete_by_code($pdo, $itemId, $unitNo, $streamDigit, null);
} catch (Throwable $e) {
    $log('error', $e->getMessage(), $code, $parsed, $source);
    $reply(500, 'ERROR');
}

$result = $res['ok'] ? (!empty($res['already']) ? 'already' : 'ok') : 'not_found';
$log($result, $res['detail'] ?? null, $code, $parsed, $source);

$reply($res['ok'] ? 200 : 404, ($res['ok'] ? 'OK ' : 'ERR ') . $res['title'] . ' — ' . $res['detail']);
