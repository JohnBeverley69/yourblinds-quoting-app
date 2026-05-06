<?php
declare(strict_types=1);

/**
 * Debug wrapper for database/migrate_calendar.php.
 *
 * Forces all errors visible (overriding bootstrap's production defaults) and
 * catches any Throwable from the migration so you see the full stack trace
 * instead of a blank page.
 *
 * DELETE THIS FILE once the migration has been run successfully — it bypasses
 * the production error_reporting hardening and could leak details if hit.
 */

// Make every PHP error/notice/warning visible BEFORE bootstrap runs.
error_reporting(E_ALL);
ini_set('display_errors',         '1');
ini_set('display_startup_errors', '1');

// Plain text so the output reads cleanly in CLI and in the browser.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=== debug_migrate.php ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "SAPI:        " . PHP_SAPI . "\n\n";

try {
    echo "-- Loading bootstrap.php --\n";
    require_once __DIR__ . '/bootstrap.php';
    echo "    bootstrap.php loaded OK\n\n";

    // bootstrap.php sets display_errors=0 in production; force it back on so
    // anything the migration emits is visible.
    error_reporting(E_ALL);
    ini_set('display_errors',         '1');
    ini_set('display_startup_errors', '1');

    echo "-- Running migrate_calendar.php --\n";
    require_once __DIR__ . '/migrate_calendar.php';
    echo "\n=== migration script returned without throwing ===\n";
} catch (Throwable $e) {
    echo "\n=== UNCAUGHT " . get_class($e) . " ===\n";
    echo "Message: " . $e->getMessage()       . "\n";
    echo "File:    " . $e->getFile() . ':' . $e->getLine() . "\n";
    if ($e->getCode() !== 0) {
        echo "Code:    " . $e->getCode() . "\n";
    }
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";

    // Walk the chain of previous exceptions, if any (PDO often nests them).
    $prev = $e->getPrevious();
    while ($prev !== null) {
        echo "\n--- Previous: " . get_class($prev) . " ---\n";
        echo "Message: " . $prev->getMessage() . "\n";
        echo "File:    " . $prev->getFile() . ':' . $prev->getLine() . "\n";
        echo $prev->getTraceAsString() . "\n";
        $prev = $prev->getPrevious();
    }

    http_response_code(500);
    exit(1);
}
