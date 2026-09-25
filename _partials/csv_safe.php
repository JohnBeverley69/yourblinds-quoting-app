<?php
declare(strict_types=1);

/**
 * Spreadsheet (CSV) formula-injection guard.
 *
 * A customer name, reference or note typed as "=HYPERLINK(…)" / "+cmd…" /
 * "-…" / "@SUM(…)" is exported as-is and then RUNS as a formula when the
 * CSV is opened in Excel or Google Sheets (can exfiltrate data via a link, or
 * trigger DDE prompts). Prefix such cells with an apostrophe so the
 * spreadsheet treats them as text. Real numbers (ints/floats, and numeric
 * strings like "-12.50") are left alone so amounts still add up.
 *
 * For the XLSX export see admin/export.php, which uses PhpSpreadsheet's
 * StringValueBinder instead (formula-looking strings stored as text).
 */

if (function_exists('csv_safe_cell')) {
    return;
}

function csv_safe_cell($v)
{
    if (!is_string($v) || $v === '' || is_numeric($v)) {
        return $v;
    }
    return strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
}

/** fputcsv() with every cell passed through csv_safe_cell(). */
function fputcsv_safe($handle, array $fields)
{
    return fputcsv($handle, array_map('csv_safe_cell', $fields));
}
