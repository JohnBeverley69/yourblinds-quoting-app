<?php
declare(strict_types=1);

/**
 * Supplier catalogue reader — reads a supplier price-list spreadsheet into a
 * structured catalogue (products -> bands -> width×drop price cells), using a
 * per-supplier "profile" to absorb that supplier's layout quirks.
 *
 * READ-ONLY: this never writes to the database. It's the front half of the
 * supplier price-list library — parse + preview now; the DB import + the
 * "verify before publish" gate build on top of it later.
 *
 * It reuses the app's existing grid parser (ptp_parse_band_blocks) untouched —
 * a profile is just preprocessing (strip the empty left margin, rewrite a
 * supplier's band-header wording to the canonical "Band X" the parser wants).
 * Proven against Decora's 2026 trade book: 27/56 sheets, 109 bands, ~22k cells.
 */

require_once __DIR__ . '/price_table_parser.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (function_exists('supplier_read_catalogue')) {
    return;
}

/**
 * A sensible default profile. Works for both the app's native "Band X" exports
 * AND Decora-style "PRICE RANGE X" books (the rewrite only fires on the latter;
 * native "Band X" passes straight through to the parser).
 */
function supplier_default_profile(): array
{
    return [
        // Auto-detect and strip leading empty columns so the band header lands
        // in column A, where the parser looks.
        'strip_left_margin'    => true,
        // regex => replacement, applied per cell to normalise band headers to
        // the canonical "Band X" the parser recognises.
        'band_header_rewrites' => [
            '/^\s*PRICE\s+RANGE\s+(.+)$/i' => 'Band $1',
        ],
        // Sheet names (case-insensitive, exact) to ignore as non-product chrome.
        'skip_sheets' => [
            'home', 'intro', 'introduction', 'contacts', 'contact', 'index',
            'notes', 'cover', 'child safety info', 'minimum & maximum size',
        ],
        'max_rows' => 600,
        'max_cols' => 24,
    ];
}

/**
 * Read a supplier file into a structured catalogue.
 *
 * @return array{products: array<int, array{name:string, band_count:int, cell_count:int, bands:array}>,
 *               skipped:  array<int, array{sheet:string, reason:string}>}
 */
function supplier_read_catalogue(string $filePath, array $profile = []): array
{
    $profile   = array_merge(supplier_default_profile(), $profile);
    $skipLower = array_map(static fn ($s) => strtolower(trim((string) $s)), $profile['skip_sheets']);

    $reader = IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(true);
    $ss = $reader->load($filePath);

    $products = [];
    $skipped  = [];

    foreach ($ss->getSheetNames() as $name) {
        if (in_array(strtolower(trim($name)), $skipLower, true)) {
            $skipped[] = ['sheet' => $name, 'reason' => 'on the skip list (not a product)'];
            continue;
        }

        $ws     = $ss->getSheetByName($name);
        $maxRow = min((int) $ws->getHighestDataRow(), (int) $profile['max_rows']);

        // Left margin: the first column that holds any data in the top rows.
        $margin = 1;
        if (!empty($profile['strip_left_margin'])) {
            $m = 999;
            for ($r = 1; $r <= min($maxRow, 25); $r++) {
                for ($c = 1; $c <= 26; $c++) {
                    if (trim((string) $ws->getCell([$c, $r])->getValue()) !== '') {
                        if ($c < $m) $m = $c;
                        break;
                    }
                }
            }
            $margin = ($m >= 1 && $m <= 26) ? $m : 1;
        }

        // Build letter-keyed, margin-shifted, band-header-rewritten rows for the
        // parser (which reads $row['A'], $row['B'], …).
        $rows = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $row = [];
            for ($oc = 1; $oc <= (int) $profile['max_cols']; $oc++) {
                $v = trim((string) $ws->getCell([$oc + $margin - 1, $r])->getValue());
                foreach ($profile['band_header_rewrites'] as $re => $rep) {
                    if (preg_match($re, $v)) { $v = preg_replace($re, $rep, $v); break; }
                }
                $row[Coordinate::stringFromColumnIndex($oc)] = $v;
            }
            $rows[$r] = $row;
        }

        try {
            $bands = ptp_parse_band_blocks($rows);
        } catch (Throwable $e) {
            $bands = [];
        }

        $cells = 0;
        foreach ($bands as $b) {
            $cells += is_array($b['cells'] ?? null) ? count($b['cells']) : 0;
        }

        if ($cells > 0) {
            $products[] = [
                'name'       => $name,
                'band_count' => count($bands),
                'cell_count' => $cells,
                'bands'      => $bands,
            ];
        } else {
            $skipped[] = [
                'sheet'  => $name,
                'reason' => 'no width × drop price grid found (chrome, or a different layout — needs manual setup)',
            ];
        }
    }

    return ['products' => $products, 'skipped' => $skipped];
}
