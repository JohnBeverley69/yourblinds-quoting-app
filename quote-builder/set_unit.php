<?php
declare(strict_types=1);

/**
 * Persist the per-quote measurement unit (the unit switcher on the quote
 * builder). Dimensions stay stored in mm — this only changes how sizes
 * are entered/shown for this quote. Redirects back to the editor.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../_partials/units.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$quoteId  = (int) ($_POST['quote_id'] ?? 0);
$unit     = (string) ($_POST['unit'] ?? '');

// Tenant-scope + editability check (reuses the builder's gate).
$quote = qb_load_quote_or_404($quoteId, $clientId);
if (!qb_is_editable($quote)) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error',
        'Quote is locked — reopen it to change the measurement unit.');
}

if (!unit_is_valid($unit)) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error', 'Unknown measurement unit.');
}

try {
    db()->prepare('UPDATE quotes SET measurement_unit = ? WHERE id = ? AND client_id = ?')
        ->execute([$unit, $quoteId, $clientId]);
} catch (Throwable $e) {
    qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId, 'error',
        'Could not set the unit — has migrate_measurement_unit.php been run? ' . $e->getMessage());
}

qb_flash_redirect('/quote-builder/edit.php?id=' . $quoteId . '#add-line', 'success',
    'Measurement unit set to ' . unit_label($unit) . '.');
