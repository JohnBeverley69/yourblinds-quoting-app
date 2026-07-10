<?php
declare(strict_types=1);

/**
 * Diagnose: what PHP version + relevant extensions is PRODUCTION running?
 *
 * Hit /diag_php_version.php while logged in as a super-admin.
 *
 * Read-only. Reports the live server's PHP version and a few things we care
 * about when deciding whether to bump dependencies:
 *   - PHP >= 8.1 gates a future PHPMailer 7.x upgrade (7.0 dropped < 8.1).
 *   - ext-gd is required by phpoffice/phpspreadsheet (spreadsheet export).
 *   - the installed phpmailer version, read from composer's runtime constant.
 *
 * Local XAMPP is PHP 8.0.30, but this app is deployed elsewhere — this tells
 * us what actually runs in production. Delete this file once you've noted it.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
requireSuperAdmin();

header('Content-Type: text/plain; charset=utf-8');

echo "PHP environment diagnostic (production)\n";
echo "Read-only — nothing is modified.\n";
echo str_repeat('-', 60) . "\n\n";

echo "PHP version   : " . PHP_VERSION . "\n";
echo "SAPI          : " . PHP_SAPI . "\n\n";

/** Yes/no line for a boolean condition. */
function flag(string $label, bool $ok, string $note = ''): void
{
    echo "  " . str_pad($label, 22) . (($ok ? 'yes' : 'NO ')) .
         ($note !== '' ? '  — ' . $note : '') . "\n";
}

echo "Dependency-relevant checks:\n";
flag('PHP >= 8.1 (PHPMailer 7)', PHP_VERSION_ID >= 80100,
     PHP_VERSION_ID >= 80100 ? 'v7 upgrade possible' : 'stay on PHPMailer ^6.9');
flag('ext-gd (phpspreadsheet)', extension_loaded('gd'),
     extension_loaded('gd') ? '' : 'spreadsheet export needs this');
flag('ext-mbstring', extension_loaded('mbstring'));
flag('ext-openssl (SMTP TLS)', extension_loaded('openssl'));
flag('ext-pdo_mysql', extension_loaded('pdo_mysql'));

echo "\n";

$pm = defined('PHPMailer\\PHPMailer\\PHPMailer::VERSION')
    ? constant('PHPMailer\\PHPMailer\\PHPMailer::VERSION')
    : (class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? '(loaded, version constant absent)' : '(not installed)');
echo "PHPMailer      : " . $pm . "\n";

echo "\n" . str_repeat('-', 60) . "\n";
echo PHP_VERSION_ID >= 80100
    ? "RESULT: production is on 8.1+, so a PHPMailer 7.x bump is technically\n        possible if ever wanted (still not required — 6.x is patched).\n"
    : "RESULT: production is below 8.1 — keep PHPMailer pinned to ^6.9.\n        A v7 upgrade would require raising PHP first.\n";
