<?php
declare(strict_types=1);

/**
 * Stream open-item statement PDFs for the statement run. Super-admin (factory) only.
 *   ?to=YYYY-MM-DD                → COMBINED: every owing account, one page each.
 *   ?account_id=N[&to=…]          → a single account's statement.
 *   [&download=1]                 → force download instead of inline.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo       = db();
$factory   = ar_factory_id();
$accountId = (int) ($_GET['account_id'] ?? 0);
$asAt      = trim((string) ($_GET['to'] ?? ''));
if ($asAt === '' || !strtotime($asAt)) $asAt = date('Y-m-d');
$asAt = date('Y-m-d', strtotime($asAt));

$fail = static function (int $code, string $msg): void {
    http_response_code($code); header('Content-Type: text/plain'); echo $msg; exit;
};

if ($accountId > 0) {
    // Single account.
    $bundle = ar_statement_bundle($pdo, $factory, $accountId, $asAt);
    if ($bundle === null) $fail(404, 'Account not found.');
    $pdf  = ar_render_statement_bm($bundle['ctx'], $bundle['data']);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($bundle['ctx']['account_name'] ?? 'account'));
    $filename = 'Statement-' . $name . '-' . $asAt . '.pdf';
} else {
    // Combined run — every account that owes as at the date.
    $statements = [];
    foreach (ar_statement_accounts($pdo, $factory, $asAt) as $a) {
        $bundle = ar_statement_bundle($pdo, $factory, (int) $a['account_id'], $asAt);
        if ($bundle !== null) $statements[] = $bundle;
    }
    $pdf = ar_render_statements_bm_combined($statements);
    $filename = 'Statements-' . $asAt . '.pdf';
}

if ($pdf === null) $fail(500, 'PDF engine unavailable (composer install dompdf/dompdf).');

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
