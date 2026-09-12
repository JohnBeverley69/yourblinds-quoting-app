<?php
declare(strict_types=1);

/**
 * Seed: the three confirmed routes (roller, vertical, pleated). Everything is
 * editable afterwards on the factory Routes screen — this just populates day
 * one. Idempotent (routes rebuilt for the three seeded products only, others
 * left untouched).
 *
 * Run via web: /seed_factory_routes.php (super-admin). Needs the tables from
 * /migrate_factory_routes.php.
 *
 * NOTE: the old "stations" concept was retired — a stage is now just
 * stream + label, in order. The station name in each tuple below is kept only
 * as a human hint of which bench does the work; it is not stored (station_id
 * is written NULL).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

// ---- Routes (rebuilt for the three seeded products) -----------------------
// [bench hint, label, stream]. Steps sharing a stream run in order; different
// streams run alongside each other and imply nothing about one another.
// 'main' = a single line, which is roller and pleated: John confirmed the saw
// work really does have to finish before the fabric table starts on those.
//
// The vertical is the exception — the headrail and the fabric are two separate
// jobs that never meet in the workshop. They're finished as parts and the fitter
// marries them on site, so either can be done first, or both at once.
$routes = [
    'Bev Roller Blinds' => [
        ['Safety Saw',          'tube cut',            'main'],
        ['Roller Fabric Table', 'cut + mount to pole', 'main'],
        ['Bottom Bar Bench',    'bottom bar',          'main'],
    ],
    'Bev Vertical Blinds' => [
        ['Safety Saw',          'profile cut',       'Headrail'],
        ['Headrail Bench',      'headrail assembly', 'Headrail'],
        ['VB1 Machine',         'fabric cut',        'Fabric'],
        // Which one depends on the blind's fabric finish — but the label names
        // the job, not the condition; it's what the floor reads on the strip.
        ['Sew / Weld',          'sew or weld',       'Fabric'],
        ['Weighting & Linking', 'finish',            'Fabric'],
    ],
    'Bev Pleated' => [
        ['Safety Saw',     'profile cut', 'main'],
        ['Pleated Cutter', 'fabric cut',  'main'],
        ['Pleated Bench',  'assemble',    'main'],
    ],
];

$findProduct = $pdo->prepare("SELECT id FROM products WHERE client_id = ? AND name = ? LIMIT 1");
$delSteps    = $pdo->prepare('DELETE FROM product_route_steps WHERE product_id = ?');

// The stream column only exists once migrate_route_streams.php has run.
$hasStream = true;
try { $pdo->query('SELECT stream FROM product_route_steps LIMIT 0'); }
catch (Throwable $e) { $hasStream = false; }
// station_id is written NULL — the retired "stations" concept; a stage is
// stream + label. The column is kept as inert legacy metadata.
$insStep = $hasStream
    ? $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, stream, active) VALUES (?, ?, NULL, ?, ?, 1)')
    : $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, active) VALUES (?, ?, NULL, ?, 1)');
if (!$hasStream) echo "  ! product_route_steps.stream is missing — run /migrate_route_streams.php to split the vertical.\n";

foreach ($routes as $productName => $steps) {
    $findProduct->execute([$MASTER, $productName]);
    $pid = (int) $findProduct->fetchColumn();
    if ($pid === 0) { echo "  ! product not found: {$productName}\n"; continue; }
    $delSteps->execute([$pid]);
    $s = 0;
    $streams = [];
    foreach ($steps as [$benchHint, $label, $stream]) {
        $streams[$stream] = true;
        $hasStream
            ? $insStep->execute([$pid, $s++, $label, $stream])
            : $insStep->execute([$pid, $s++, $label]);
    }
    $note = ($hasStream && count($streams) > 1) ? ' in ' . count($streams) . ' parallel streams (' . implode(', ', array_keys($streams)) . ')' : '';
    echo "  route: {$productName} — " . count($steps) . " stages{$note}\n";
}

echo "\nDone — " . count($routes) . " routes seeded.\n";
echo "Edit anytime on the factory Routes screen. Other products have no route yet — assign them there.\n";
