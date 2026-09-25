<?php
declare(strict_types=1);

/**
 * Load a TENANT-uploaded spreadsheet with a size check first.
 *
 * IOFactory::load() on an upload trusted whatever dimensions the file claims:
 * a 10 MB xlsx that declares a cell at XFD1048576, or a highly-compressed
 * "zip bomb", ties up a PHP worker until it runs out of memory/time — and a
 * free self-signup admin can reach every price/option import. We now:
 *   - accept only real spreadsheet types (xlsx / xls / csv / ods);
 *   - read each sheet's row/column count from the file's own index
 *     (listWorksheetInfo streams it — cheap) and refuse anything bigger than a
 *     real price grid or option list before loading it for real.
 * How the sheet is then read is unchanged, so every import behaves as before.
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

function yb_safe_load_spreadsheet(string $path, int $maxRows = 20000, int $maxCols = 300): Spreadsheet
{
    $type = IOFactory::identify($path);
    if (!in_array($type, ['Xlsx', 'Xls', 'Csv', 'Ods'], true)) {
        throw new RuntimeException('Please upload an Excel (.xlsx / .xls), CSV or .ods file.');
    }
    $reader = IOFactory::createReader($type);
    foreach ($reader->listWorksheetInfo($path) as $sheet) {
        $rows = (int) ($sheet['totalRows'] ?? 0);
        $cols = (int) ($sheet['totalColumns'] ?? 0);
        if ($rows > $maxRows || $cols > $maxCols) {
            throw new RuntimeException(sprintf(
                'That spreadsheet is too big to import (sheet "%s" is %s rows × %s columns; the limit is %s × %s). '
                . 'Delete any unused rows/columns — or stray content far down/right — and try again.',
                (string) ($sheet['worksheetName'] ?? '?'),
                number_format($rows), number_format($cols), number_format($maxRows), number_format($maxCols)
            ));
        }
    }
    return $reader->load($path);
}
