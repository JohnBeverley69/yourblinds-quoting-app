<?php
declare(strict_types=1);

/**
 * Stream a commission statement PDF for a sales consultant (internal). Super-admin.
 * Usage: /master-admin/commission-pdf.php?consultant_id=N[&from=&to=][&download=1]
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo          = db();
$factory      = ar_factory_id();
$consultantId = (int) ($_GET['consultant_id'] ?? 0);
$from         = trim((string) ($_GET['from'] ?? ''));
$to           = trim((string) ($_GET['to'] ?? ''));

$consultantName = '';
try {
    if (ar_table_ready($pdo, 'sales_consultants')) {
        $s = $pdo->prepare('SELECT name FROM sales_consultants WHERE id = ? LIMIT 1');
        $s->execute([$consultantId]);
        $consultantName = (string) ($s->fetchColumn() ?: '');
    }
} catch (Throwable $e) { /* none */ }
if ($consultantName === '') { http_response_code(404); header('Content-Type: text/plain'); echo 'Consultant not found.'; exit; }

$fac = [];
try {
    $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $fs->execute([$factory]);
    $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* letterhead degrades gracefully */ }

$data = ar_commission_statement($pdo, $factory, $consultantId, $from, $to);
$ctx  = [
    'factory'         => $fac,
    'consultant_name' => $consultantName,
    'statement_date'  => date('Y-m-d'),
    'from'            => $data['from'],
    'to'              => $data['to'],
];

$pdf = ar_render_commission($ctx, $data);
if ($pdf === null) { http_response_code(500); header('Content-Type: text/plain'); echo 'PDF engine unavailable.'; exit; }

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$namePart    = preg_replace('/[^A-Za-z0-9._-]/', '_', $consultantName);
$filename    = 'Commission-' . $namePart . '-' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
