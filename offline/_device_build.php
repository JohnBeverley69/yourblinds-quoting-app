<?php
declare(strict_types=1);

/**
 * ON THE TABLET (PHP-in-WebAssembly), not a web page. Builds the catalogue
 * database from the downloaded snapshot:
 *   /app/catalogue.json  (offline/catalogue.php)  →  /data/catalogue.sqlite + /data/meta.json
 * Prints {"ok":true,"rows":N,"ms":T} or {"error":"..."}.
 */

if (PHP_SAPI !== 'cli' && !is_file('/app/catalogue.json')) { http_response_code(404); exit; }   // on the server: nothing to do

ini_set('memory_limit', '1024M');   // a whole catalogue decoded at once
require __DIR__ . '/_device_catalogue.php';

try {
    $t0 = microtime(true);
    $d  = json_decode((string) file_get_contents('/app/catalogue.json'), true, 512, JSON_THROW_ON_ERROR);
    @mkdir('/data', 0777, true);
    @unlink('/data/catalogue.sqlite');
    $pdo  = offline_sqlite_open('/data/catalogue.sqlite', (string) $d['today']);
    $rows = offline_sqlite_build($pdo, $d);
    file_put_contents('/data/meta.json', json_encode([
        'client_id'         => (int) $d['client_id'],
        'factory_client_id' => (int) $d['factory_client_id'],
        'today'             => (string) $d['today'],
        'version'           => (string) $d['version'],
    ]));
    echo json_encode(['ok' => true, 'rows' => $rows, 'ms' => (int) round((microtime(true) - $t0) * 1000)]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
