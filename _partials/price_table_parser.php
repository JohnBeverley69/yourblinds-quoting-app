<?php
declare(strict_types=1);

/**
 * Shared price-table parser used by both:
 *   - admin/products/price-tables-bulk-import.php (multi-band, multi-system)
 *   - admin/products/price-table.php             (single band into one table)
 *
 * Suppliers ship sheets in wildly different shapes, so the parser is
 * deliberately tolerant: tolerant on the band-header line, tolerant on
 * unit notation (mm, cm, m, bare numbers), and tolerant on currency
 * symbols / commas in price cells.
 *
 * Public functions:
 *   - ptp_parse_band_blocks(array $rows): array
 *   - ptp_parse_dimension(string $raw): ?int        // returns mm or null
 *   - ptp_parse_price(string $raw): ?float          // returns £ or null
 */

if (!function_exists('ptp_parse_dimension')) {
    /**
     * Convert a raw cell to integer millimetres.
     *
     * Detection (in order):
     *   - "1500mm"       -> 1500
     *   - "61.5cm"       -> 615
     *   - "1.5m" / "5m"  -> 1500 / 5000 (metres explicit, word-bounded)
     *   - "24.02''" / "24in"  -> 610 / 610 (inches × 25.4)
     *   - "0.800" (bare) -> 800   (heuristic: < 100 = metres)
     *   - "610"   (bare) -> 610   (heuristic: >= 100 = mm)
     *
     * Returns null for anything we can't pull a positive number out of.
     */
    function ptp_parse_dimension(string $raw): ?int
    {
        $str = trim($raw);
        if ($str === '') return null;

        $cleaned = preg_replace('/[^\d.\-]/', '', $str);
        if ($cleaned === '' || !is_numeric($cleaned)) return null;
        $num = (float) $cleaned;
        if ($num <= 0) return null;

        $lower = strtolower($str);
        if (strpos($lower, 'mm') !== false)              return (int) round($num);
        if (strpos($lower, 'cm') !== false)              return (int) round($num * 10);
        if (preg_match('/\d\s*m\b/i', $str))             return (int) round($num * 1000);
        // Inches — quote characters (' or ") or "in"/"ins" suffix.
        if (preg_match('/[\'"]|\bins?\b/i', $str))       return (int) round($num * 25.4);

        // No unit suffix — magnitude heuristic.
        // Anything below 100 is implausibly small for a blind in mm
        // (no real-world product is < 100mm wide), so treat as metres.
        if ($num < 100) return (int) round($num * 1000);
        return (int) round($num);
    }
}

if (!function_exists('ptp_parse_price')) {
    /**
     * Convert a raw cell to a non-negative float price.
     * Strips currency symbols, commas, whitespace.
     */
    function ptp_parse_price(string $raw): ?float
    {
        $str = trim($raw);
        if ($str === '') return null;
        $cleaned = preg_replace('/[^\d.\-]/', '', $str);
        if ($cleaned === '' || !is_numeric($cleaned)) return null;
        $num = (float) $cleaned;
        if ($num < 0) return null;
        return $num;
    }
}

if (!function_exists('ptp_parse_width_price_input')) {
    /**
     * Parse a width/price input from either a pasted textarea or an uploaded
     * .xlsx file.
     *
     * Returns ['rows' => [width_mm => price, ...], 'error' => string|null].
     *
     * If $filePath is a real path, parse Excel (file wins over textarea).
     * Else parse $textarea (one row per line, "width<sep>price"). Empty
     * input -> rows = [] (caller can treat as "clear the table").
     */
    function ptp_parse_width_price_input(string $textarea, ?string $filePath = null): array
    {
        $rows = [];

        if ($filePath !== null && is_readable($filePath)) {
            require_once __DIR__ . '/../vendor/autoload.php';
            try {
                $ss        = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $sheetRows = $ss->getActiveSheet()->toArray(null, true, true, true);
                foreach ($sheetRows as $rowNum => $row) {
                    $cells = [];
                    foreach ($row as $val) {
                        $v = trim((string) ($val ?? ''));
                        if ($v === '') continue;
                        $cells[] = $v;
                        if (count($cells) === 2) break;
                    }
                    if (count($cells) < 2) continue;
                    $w = ptp_parse_dimension($cells[0]);
                    $p = ptp_parse_price($cells[1]);
                    // Both null = header row, skip silently.
                    if ($w === null && $p === null) continue;
                    if ($w === null || $p === null) {
                        return [
                            'rows'  => [],
                            'error' => "Row $rowNum: couldn't read width/price ('"
                                     . $cells[0] . "', '" . $cells[1] . "').",
                        ];
                    }
                    $rows[$w] = (float) $p;
                }
                return ['rows' => $rows, 'error' => null];
            } catch (Throwable $e) {
                return ['rows' => [], 'error' => 'Could not read the file: ' . $e->getMessage()];
            }
        }

        // Textarea path.
        $lines = preg_split('/\r?\n/', trim($textarea)) ?: [];
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Skip header-y lines like "Width" / "Price" / "mm" with no digits.
            if (preg_match('/^(width|price|mm)/i', $line) && !preg_match('/\d/', $line)) continue;
            $parts = preg_split('/[\s,;|]+/', $line);
            if (count($parts) < 2) {
                return [
                    'rows'  => [],
                    'error' => 'Line ' . ($lineNum + 1)
                             . ': expected width and price separated by space, comma, tab, or |. Got "'
                             . $line . '".',
                ];
            }
            $w = ptp_parse_dimension((string) $parts[0]);
            $p = ptp_parse_price((string) $parts[1]);
            if ($w === null) {
                return ['rows' => [], 'error' => 'Line ' . ($lineNum + 1) . ': could not read width "' . $parts[0] . '".'];
            }
            if ($p === null) {
                return ['rows' => [], 'error' => 'Line ' . ($lineNum + 1) . ': could not read price "' . $parts[1] . '".'];
            }
            $rows[$w] = (float) $p;
        }
        return ['rows' => $rows, 'error' => null];
    }
}

if (!function_exists('ptp_parse_band_blocks')) {
    /**
     * Walk a sheet's rows looking for stacked band matrices.
     * Returns: [['code' => 'AAA', 'cells' => [[width_mm, drop_mm, price], ...]], ...]
     *
     * Format expected (loose):
     *   Header row: column A = something matching /(?:price\s+)?b(?:and|nad)\s+([A-Z]+)/i
     *   Optional label row(s) with text only ("DROP", "WIDTH", "Metric", ...)
     *   Widths row: numeric values (possibly with mm/m units) in columns B onwards
     *   Optional inches reference row (skipped — column A non-numeric)
     *   Data rows: column A = drop (mm or m), other columns = prices (with or without £)
     *
     * Tolerant of:
     *   - "Band AAA" / "Bnad AA" / "Price Band A" / "BAND XYZ"
     *   - mm vs metres (auto-detected per cell)
     *   - £ / , in prices
     *   - widths starting at column B (no leading "Metric" cell) or column C
     */
    function ptp_parse_band_blocks(array $rows): array
    {
        $bands     = [];
        $current   = null;
        $widths    = [];
        $cells     = [];
        $expecting = 'band';   // band | widths | data
        $dropCol   = null;     // detected once per band — which column holds drops

        $finalise = function () use (&$bands, &$current, &$cells) {
            if ($current !== null && $cells) {
                $bands[] = ['code' => $current, 'cells' => $cells];
            }
            $current = null;
            $cells   = [];
        };

        foreach ($rows as $rowNum => $row) {
            $a = trim((string) ($row['A'] ?? ''));

            // Tolerant band-header detection (anchored at end so we don't match
            // things like "abandon" mid-text).
            if (preg_match('/(?:price\s+)?b(?:and|nad)\s+([A-Z]+)\s*$/i', $a, $m)) {
                $finalise();
                $current   = strtoupper($m[1]);
                $widths    = [];
                $dropCol   = null;
                $expecting = 'widths';
                continue;
            }

            if ($current === null) continue;

            if ($expecting === 'widths') {
                // Try to capture widths from the row. Skip column A (it's the
                // drop axis or a label). Need at least 2 numeric values to
                // accept this as a widths row — that filters out lone-cell
                // label rows that happen to contain a digit.
                $candidate = [];
                foreach ($row as $col => $val) {
                    if ($col === 'A') continue;
                    $w = ptp_parse_dimension((string) ($val ?? ''));
                    if ($w !== null && $w > 0) {
                        $candidate[$col] = $w;
                    }
                }
                if (count($candidate) >= 2) {
                    $widths    = $candidate;
                    $expecting = 'data';
                }
                continue;
            }

            // expecting === 'data'
            //
            // The drop column isn't always A — some files put it in B (with
            // column A holding label text in the widths row). On the first
            // data row of each band, sniff for the leftmost non-widths column
            // that holds a parseable dimension and lock it in.
            if ($dropCol === null) {
                foreach ($row as $col => $val) {
                    if (isset($widths[$col])) continue;
                    $d = ptp_parse_dimension((string) ($val ?? ''));
                    if ($d !== null && $d > 0) {
                        $dropCol = $col;
                        break;
                    }
                }
                if ($dropCol === null) continue; // not a data row yet
            }

            $dropMm = ptp_parse_dimension((string) ($row[$dropCol] ?? ''));
            if ($dropMm === null) {
                // Could be an inches reference row, a blank spacer, anything.
                // Keep waiting for the next real data row.
                continue;
            }
            foreach ($widths as $col => $widthMm) {
                $price = ptp_parse_price((string) ($row[$col] ?? ''));
                if ($price === null || $price <= 0) continue;
                $cells[] = [$widthMm, $dropMm, $price];
            }
        }
        $finalise();
        return $bands;
    }
}
