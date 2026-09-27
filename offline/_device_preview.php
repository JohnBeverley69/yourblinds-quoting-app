<?php
declare(strict_types=1);

/**
 * ON THE TABLET (PHP-in-WebAssembly), not a web page. Answers one live-price
 * request with no signal, with the SAME code the server uses
 * (quote-builder/_preview_core.php → pricing_engine.php), against the tablet's
 * catalogue built by _device_build.php.
 *
 *   in:  /app/req.json   {"qs": "<the preview.php query string>", "can_costs": bool, "for_account_id": int}
 *        (parsed with parse_str, exactly as PHP builds $_GET on the server)
 *   out: the preview.php JSON, plus "offline": true
 */

if (PHP_SAPI !== 'cli' && !is_file('/data/meta.json')) { http_response_code(404); exit; }   // on the server: nothing to do

require __DIR__ . '/_device_catalogue.php';

$meta = json_decode((string) file_get_contents('/data/meta.json'), true);
define('OFFLINE_FACTORY_CLIENT_ID', (int) ($meta['factory_client_id'] ?? 3));
if (!function_exists('factory_client_id')) {
    function factory_client_id(): int { return OFFLINE_FACTORY_CLIENT_ID; }
}

require __DIR__ . '/../_partials/pricing_engine.php';
require __DIR__ . '/../_partials/price_table_parser.php';
require __DIR__ . '/../_partials/units.php';
require __DIR__ . '/../_partials/multi_fascia_pricer.php';
require __DIR__ . '/../quote-builder/_preview_core.php';

try {
    $req = json_decode((string) file_get_contents('/app/req.json'), true, 64, JSON_THROW_ON_ERROR);
    $q = [];
    parse_str((string) ($req['qs'] ?? ''), $q);
    $pdo = offline_sqlite_open('/data/catalogue.sqlite', (string) $meta['today']);
    $out = qb_preview_response(
        $pdo,
        (int) $meta['client_id'],
        $q,
        !empty($req['can_costs']),
        false,
        (int) ($req['for_account_id'] ?? 0)
    );
    $out['offline'] = true;
    $out['catalogue_date'] = (string) $meta['today'];
    echo json_encode($out, JSON_PRESERVE_ZERO_FRACTION);
} catch (Throwable $e) {
    echo json_encode(['error' => 'Offline pricing failed: ' . $e->getMessage(), 'stage' => 'offline']);
}
