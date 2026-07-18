<?php
declare(strict_types=1);

/**
 * Migration: WiFi scan-in — no PC at the bench.
 *
 * The re-thought model: workstations have no PC. A WiFi scanner at each bench
 * fires the worksheet's barcode straight at the system, and the office board
 * shows it move. The barcode already says which blind and which part (the stream
 * digit), so a scan is entirely self-describing — no login needed, which is what
 * lets it work with no PC there.
 *
 *   factory_kv         — factory-wide settings; holds the scan-in secret. With no
 *                        login, that secret in the scanner's URL is what stops
 *                        anyone POSTing scans at us.
 *   factory_scan_log   — every scan-in: what, when, outcome, which scanner. Serves
 *                        two jobs at once — de-duping an accidental double-scan,
 *                        and the "true logging for the factory" John wanted since
 *                        the admin page isn't always open.
 *
 * A random scan key is generated if there isn't one; it's printed here so it can
 * be pasted into the scanner config. Run via web: /migrate_factory_scan_in.php
 * (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
require_once __DIR__ . '/_partials/factory_kv.php';
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

if (!$tableExists('factory_kv')) {
    $pdo->exec(
        "CREATE TABLE factory_kv (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created factory_kv.\n";
} else {
    echo "  factory_kv already exists — skipped.\n";
}

if (!$tableExists('factory_scan_log')) {
    $pdo->exec(
        "CREATE TABLE factory_scan_log (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            code          VARCHAR(20) NOT NULL,
            quote_item_id INT NULL,
            unit_no       INT NULL,
            stream_digit  INT NULL,
            result        VARCHAR(16) NOT NULL,        -- ok | already | dup | bad_code | not_found | bad_key | error
            detail        VARCHAR(255) NULL,
            source        VARCHAR(60) NULL,            -- optional scanner id from the URL
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_scan_recent (quote_item_id, unit_no, created_at),
            KEY idx_scan_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created factory_scan_log.\n";
} else {
    echo "  factory_scan_log already exists — skipped.\n";
}

$key = fx_scan_key($pdo);
if ($key === '') {
    $key = bin2hex(random_bytes(12));   // 24 hex chars — plenty, and short enough to type
    fx_kv_set($pdo, 'scan_key', $key);
    echo "  Generated a scan key.\n";
} else {
    echo "  Scan key already set — kept.\n";
}

echo "\n  YOUR SCAN KEY:  {$key}\n";
echo "\n  Point each WiFi scanner at:\n";
echo "    https://www.yourblinds.uk/factory/scan-in.php?key={$key}&c={CODE}\n";
echo "  (whatever the scanner uses for the barcode goes where {CODE} is).\n";
echo "\nDone.\n";
