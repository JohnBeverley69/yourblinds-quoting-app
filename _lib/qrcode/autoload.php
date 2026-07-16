<?php
declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the vendored QR code library.
 *
 * WHY THIS EXISTS INSTEAD OF `composer require`:
 * deploys are a Cloudways git pull (see .github/workflows/deploy.yml) and
 * /vendor/ is gitignored — nothing ever runs `composer install` on the server.
 * A normal dependency would therefore deploy as a fatal "class not found" on
 * the label pages. So the library source lives here, in the repo, and reaches
 * production like any other file. Don't "tidy" this into composer.json unless
 * the deploy learns to install dependencies first.
 *
 * Vendored: chillerlan/php-qrcode 5.0.5 (MIT OR Apache-2.0) and its only
 * dependency chillerlan/php-settings-container 2.1.6 (MIT). Licence files are
 * kept alongside each package. Upstream: github.com/chillerlan/php-qrcode
 *
 * To update: composer require the package in a scratch dir, copy its src/ and
 * LICENSE files over the top, and re-run the QR test sheet to confirm.
 */

spl_autoload_register(static function (string $class): void {
    static $roots = [
        'chillerlan\\QRCode\\'  => __DIR__ . '/php-qrcode/src/',
        'chillerlan\\Settings\\' => __DIR__ . '/php-settings-container/src/',
    ];
    foreach ($roots as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
        $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $dir . $rel . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});
