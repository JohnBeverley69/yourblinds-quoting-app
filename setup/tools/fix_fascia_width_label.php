<?php
declare(strict_types=1);

/**
 * One-off: drop the confusing "— blank = fit blind" hint from the Fascia width
 * option's number-box placeholder, leaving just "Fascia width (mm)". The field
 * only shows for Over size / Multi blind, so the hint was noise. Idempotent.
 *
 * Web-runnable: /setup/tools/fix_fascia_width_label.php (super-admin).
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once dirname(__DIR__, 2) . '/auth/middleware.php'; requireSuperAdmin(); require_run_confirmation(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$n = $pdo->exec(
    "UPDATE product_extras
        SET length_input_label = 'Fascia width (mm)'
      WHERE code = 'fascia_width'
        AND length_input_label <> 'Fascia width (mm)'"
);

echo "Updated {$n} fascia-width label(s) to 'Fascia width (mm)' (hint removed).\n";
