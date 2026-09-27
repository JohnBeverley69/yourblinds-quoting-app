<?php
declare(strict_types=1);

/**
 * The PHP files the tablet runs offline, by repo path. They're shipped as text
 * (offline/engine_bundle.php, offline/proof.php) and written to /app/<same path>
 * inside PHP-in-WebAssembly, so their own __DIR__ requires work unchanged.
 * Add a file here if the live-price code starts requiring a new one.
 */
const OFFLINE_ENGINE_FILES = [
    '_partials/pricing_engine.php',
    '_partials/price_source.php',
    '_partials/price_table_parser.php',
    '_partials/safe_spreadsheet.php',
    '_partials/units.php',
    '_partials/multi_fascia_pricer.php',
    'quote-builder/_preview_core.php',
    'offline/_device_catalogue.php',
    'offline/_device_build.php',
    'offline/_device_preview.php',
    'offline/_parity_harness.php',
];

/** [repo path => source]. */
function offline_engine_sources(): array
{
    $root = dirname(__DIR__);
    $out = [];
    foreach (OFFLINE_ENGINE_FILES as $rel) {
        $out[$rel] = (string) file_get_contents($root . '/' . $rel);
    }
    return $out;
}
