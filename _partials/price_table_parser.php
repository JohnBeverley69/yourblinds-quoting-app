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
     * Detection:
     *   - "1500mm"       -> 1500   (mm explicit)
     *   - "61.5cm"       -> 615    (cm explicit)
     *   - "1.5m" / "5m"  -> 1500 / 5000 (metres explicit, word-bounded)
     *   - "0.800" (bare) -> 800    (heuristic: < 100 = metres)
     *   - "610"   (bare) -> 610    (heuristic: >= 100 = mm)
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
        $expecting = 'band'; // band | widths | data

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
            $dropMm = ptp_parse_dimension($a);
            if ($dropMm === null) {
                // Not a data row — inches reference, label spacer, blank,
                // anything else. Skip and keep waiting for the next data row
                // or band header.
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
