<?php
declare(strict_types=1);

/**
 * Seed: initial production stations + the three confirmed routes (roller,
 * vertical, pleated). Everything is editable afterwards on the factory Routes
 * screen — this just populates day one. Idempotent (upsert by name; routes
 * rebuilt for the three seeded products only, others left untouched).
 *
 * Run via web: /seed_factory_routes.php (super-admin). Needs the tables from
 * /migrate_factory_routes.php.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

// ---- Stations (upsert by name) --------------------------------------------
$stations = [
    ['Safety Saw',          0],
    ['Pleated Cutter',      0],
    ['Pleated Bench',       0],
    ['Roller Fabric Table', 0],
    ['Bottom Bar Bench',    0],
    ['Headrail Bench',      0],
    ['VB1 Machine',         0],
    ['Sew / Weld',          0],
    ['Weighting & Linking', 0],
    ['Outsourced',          1],   // is_outsourced
];
$findStation = $pdo->prepare('SELECT id FROM factory_stations WHERE client_id = ? AND name = ? LIMIT 1');
$insStation  = $pdo->prepare('INSERT INTO factory_stations (client_id, name, sort_order, is_outsourced, active) VALUES (?, ?, ?, ?, 1)');
$stationId = [];
$seq = 0;
foreach ($stations as [$name, $outsourced]) {
    $findStation->execute([$MASTER, $name]);
    $id = (int) $findStation->fetchColumn();
    if ($id === 0) {
        $insStation->execute([$MASTER, $name, $seq, $outsourced]);
        $id = (int) $pdo->lastInsertId();
        echo "  + station: {$name}\n";
    } else {
        echo "  = station exists: {$name}\n";
    }
    $stationId[$name] = $id;
    $seq++;
}

// ---- Routes (rebuilt for the three seeded products) -----------------------
// [station, label, stream]. Steps sharing a stream run in order; different
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
$insStep = $hasStream
    ? $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, stream, active) VALUES (?, ?, ?, ?, ?, 1)')
    : $pdo->prepare('INSERT INTO product_route_steps (product_id, seq, station_id, label, active) VALUES (?, ?, ?, ?, 1)');
if (!$hasStream) echo "  ! product_route_steps.stream is missing — run /migrate_route_streams.php to split the vertical.\n";

foreach ($routes as $productName => $steps) {
    $findProduct->execute([$MASTER, $productName]);
    $pid = (int) $findProduct->fetchColumn();
    if ($pid === 0) { echo "  ! product not found: {$productName}\n"; continue; }
    $delSteps->execute([$pid]);
    $s = 0;
    $streams = [];
    foreach ($steps as [$stationName, $label, $stream]) {
        $streams[$stream] = true;
        $hasStream
            ? $insStep->execute([$pid, $s++, $stationId[$stationName], $label, $stream])
            : $insStep->execute([$pid, $s++, $stationId[$stationName], $label]);
    }
    $note = ($hasStream && count($streams) > 1) ? ' in ' . count($streams) . ' parallel streams (' . implode(', ', array_keys($streams)) . ')' : '';
    echo "  route: {$productName} — " . count($steps) . " stages{$note}\n";
}

echo "\nDone — " . count($stationId) . " stations, " . count($routes) . " routes seeded.\n";
echo "Edit anytime on the factory Routes screen. Other products have no route yet — assign them there.\n";
