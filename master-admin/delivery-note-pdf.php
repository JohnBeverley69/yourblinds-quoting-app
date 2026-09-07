<?php
declare(strict_types=1);

/**
 * Stream a delivery note PDF (Beverley → trade account). Super-admin only.
 * Usage: /master-admin/delivery-note-pdf.php?id=N[&download=1]
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../pdf-generator/ar_pdf.php';

requireSuperAdmin();

$pdo     = db();
$factory = ar_factory_id();
$id      = (int) ($_GET['id'] ?? 0);

$dn = null;
try {
    $st = $pdo->prepare('SELECT * FROM factory_ar_delivery_notes WHERE id = ? AND factory_client_id = ? LIMIT 1');
    $st->execute([$id, $factory]);
    $dn = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) { /* handled below */ }

if (!$dn) { http_response_code(404); header('Content-Type: text/plain'); echo 'Delivery note not found.'; exit; }

// Lines (snapshot).
$lines = [];
try {
    $ls = $pdo->prepare('SELECT * FROM factory_ar_delivery_note_lines WHERE delivery_note_id = ? ORDER BY sort_order, id');
    $ls->execute([$id]);
    $lines = $ls->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* none */ }

// Beverley (factory) letterhead + order ref.
$fac = [];
try {
    $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    $fs->execute([$factory]);
    $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { /* letterhead degrades gracefully */ }

$orderRef = '';
if (!empty($dn['source_quote_id'])) {
    try {
        $qs = $pdo->prepare('SELECT quote_number FROM quotes WHERE id = ? LIMIT 1');
        $qs->execute([(int) $dn['source_quote_id']]);
        $orderRef = (string) ($qs->fetchColumn() ?: '');
    } catch (Throwable $e) { /* optional */ }
}

$items = array_map(static function (array $l): array {
    return [
        'product'   => $l['product_name'] ?? '',
        'system'    => $l['system_name'] ?? '',
        'options'   => $l['options_snapshot'] !== null && $l['options_snapshot'] !== ''
                       ? array_values(array_filter(explode("\n", (string) $l['options_snapshot']), static fn ($s) => trim($s) !== ''))
                       : [],
        'fabric'    => $l['fabric'] ?? '',
        'band'      => $l['band_code'] ?? '',
        'width_mm'  => $l['width_mm'] ?? null,
        'drop_mm'   => $l['drop_mm'] ?? null,
        'quantity'  => (int) ($l['quantity'] ?? 1),
        'room'      => $l['room'] ?? '',
        'notes'     => $l['line_notes'] ?? '',
    ];
}, $lines);

$ctx = [
    'factory'    => $fac,
    'dn_number'  => (string) $dn['dn_number'],
    'date'       => $dn['status'] === 'dispatched' && $dn['dispatched_at']
                    ? date('j F Y', strtotime((string) $dn['dispatched_at']))
                    : date('j F Y', strtotime((string) $dn['created_at'])),
    'order_ref'  => $orderRef,
    'deliver_to' => (string) ($dn['delivery_address'] ?? ''),
    'notes'      => $dn['status'] === 'cancelled' ? 'CANCELLED' : (string) ($dn['notes'] ?? ''),
];

$pdf = ar_render_delivery_note($ctx, $items);
if ($pdf === null) { http_response_code(500); header('Content-Type: text/plain'); echo 'PDF engine unavailable.'; exit; }

$disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
$filename    = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $dn['dn_number']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
echo $pdf;
