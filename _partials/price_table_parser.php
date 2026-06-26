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
    function ptp_parse_dimension(string $raw, string $defaultUnit = 'mm'): ?int
    {
        $str = trim($raw);
        if ($str === '') return null;

        $cleaned = preg_replace('/[^\d.\-]/', '', $str);
        if ($cleaned === '' || !is_numeric($cleaned)) return null;
        $num = (float) $cleaned;
        if ($num <= 0) return null;

        // Explicit unit suffix ALWAYS wins, regardless of the tenant
        // default — so a metres-shop user can still type "60in" for one
        // odd blind and get inches.
        $lower = strtolower($str);
        if (strpos($lower, 'mm') !== false)              return (int) round($num);
        if (strpos($lower, 'cm') !== false)              return (int) round($num * 10);
        if (preg_match('/\d\s*m\b/i', $str))             return (int) round($num * 1000);
        // Inches — quote characters (' or ") or an in/ins/inch/inches
        // suffix right after the number. Note: NO leading \b — there's no
        // word boundary between a digit and a letter, so "60in" (no space)
        // would otherwise be missed and fall through to a bare number.
        if (preg_match('/[\'"]|\d\s*in(?:s|ch|ches)?\b/i', $str)) return (int) round($num * 25.4);

        // No unit suffix — interpret in the caller's default unit (the
        // tenant / quote setting). Defaults to millimetres so price-sheet
        // imports and any caller that doesn't pass a unit behave exactly
        // as before.
        $factor = ['mm' => 1.0, 'cm' => 10.0, 'm' => 1000.0, 'in' => 25.4][$defaultUnit] ?? 1.0;
        return (int) round($num * $factor);
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

                // ── Layout auto-detection ─────────────────────────────
                //
                // We accept TWO shapes for the same data:
                //
                //   "vertical"   — width in column A, price in column B,
                //                  one row per width. The historic format.
                //
                //   "horizontal" — widths spread across row 1, prices
                //                  spread across the last numeric row.
                //                  Common in supplier price sheets;
                //                  the bottom bar pricing from Eclipse
                //                  uses this, sometimes with a metric
                //                  + imperial inches reference row in
                //                  between.
                //
                // "Numeric" here uses the parsers, not raw is_numeric() —
                // otherwise currency-formatted cells ("£3.92" with the
                // numFmt applied by PhpSpreadsheet when formatData=true)
                // get rejected as non-numeric and rows of real prices
                // are missed entirely. The parsers strip £ / , / spaces
                // before testing, which is the behaviour we want here.
                $rowIsPriceLike = static function (string $v): bool {
                    return ptp_parse_price($v) !== null;
                };

                $dataRows = [];
                foreach ($sheetRows as $rowNum => $row) {
                    $numeric = 0;
                    foreach ($row as $val) {
                        $v = trim((string) ($val ?? ''));
                        if ($v === '') continue;
                        if ($rowIsPriceLike($v)) $numeric++;
                    }
                    if ($numeric > 0) $dataRows[$rowNum] = $row;
                }

                $rowNums = array_keys($dataRows);
                // Horizontal layout if there are 2+ data rows and the
                // FIRST + LAST both look "wide" (more than 2 numeric
                // cells). Middle rows are tolerated and ignored — many
                // supplier sheets include a metric row, an imperial-
                // inches reference row, then prices. We pair first
                // (widths) with last (prices) and skip any inches /
                // labels rows in between.
                if (count($rowNums) >= 2) {
                    $firstRowNum = $rowNums[0];
                    $lastRowNum  = $rowNums[count($rowNums) - 1];
                    $widthsRow   = $dataRows[$firstRowNum];
                    $pricesRow   = $dataRows[$lastRowNum];
                    // Same parser-based filter as the row-detection step
                    // above — survives currency-formatted price cells.
                    $widthCells  = array_values(array_filter($widthsRow, static fn ($v) => $rowIsPriceLike(trim((string) ($v ?? '')))));
                    $priceCells  = array_values(array_filter($pricesRow, static fn ($v) => $rowIsPriceLike(trim((string) ($v ?? '')))));
                    if (count($widthCells) > 2 && count($priceCells) > 2) {
                        // Horizontal layout. Pair up cell-for-cell.
                        // Sanity check: prices should look like prices
                        // (positive, generally <= 10,000 for a single
                        // blind component). Widths should look like
                        // widths (positive). If the "prices" row has
                        // values that look like inches (24..200, in a
                        // sequence that matches the metres row × 39.4),
                        // someone's almost certainly given us a 2-row
                        // file with metres + inches but no real prices.
                        // Refuse and tell them.
                        if (count($widthCells) === count($priceCells)) {
                            $looksLikeInches = true;
                            for ($i = 0; $i < count($widthCells); $i++) {
                                // Use the parser to strip currency / unit
                                // suffixes before casting; raw (float)
                                // on "£3.92" returns 0.0 and would
                                // falsely trigger this check.
                                $wRaw = ptp_parse_price((string) $widthCells[$i]);
                                $pRaw = ptp_parse_price((string) $priceCells[$i]);
                                $w = $wRaw !== null ? (float) $wRaw : 0.0;
                                $p = $pRaw !== null ? (float) $pRaw : 0.0;
                                if ($w <= 0) { $looksLikeInches = false; break; }
                                $ratio = $p / $w;
                                // 39.37 = inches per metre. Allow a
                                // generous ±10% so rounding doesn't
                                // bite, but flag if every cell looks
                                // like that conversion.
                                if ($ratio < 35.0 || $ratio > 43.0) {
                                    $looksLikeInches = false;
                                    break;
                                }
                            }
                            if ($looksLikeInches) {
                                return [
                                    'rows'  => [],
                                    'error' => 'The "prices" row looks like inches — every value is roughly the width × 39.4. '
                                             . 'Add a real prices row to the spreadsheet and re-upload.',
                                ];
                            }
                        }

                        $pairs = min(count($widthCells), count($priceCells));
                        for ($i = 0; $i < $pairs; $i++) {
                            $w = ptp_parse_dimension((string) $widthCells[$i]);
                            $p = ptp_parse_price((string) $priceCells[$i]);
                            if ($w === null || $p === null || $p <= 0) continue;
                            $rows[$w] = (float) $p;
                        }
                        return ['rows' => $rows, 'error' => null];
                    }
                }

                // Vertical layout — original behaviour: each row holds
                // a (width, price) pair in its first two non-empty cells.
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
        $current   = null;     // array of band codes this block serves, or null
        $widths    = [];
        $cells     = [];
        $expecting = 'band';   // band | widths | data
        $dropCol   = null;     // detected once per band — which column holds drops
        $bareUnit  = null;     // default unit for bare (suffix-less) dimensions

        // Pick the default unit for bare numbers, detected from the first
        // widths row. Blind grids are written in metres (0.6–4.8), centimetres
        // (60–480) or millimetres (600–4800). A unit suffix on a cell always
        // wins; this only rescues suffix-less numbers — without it a metre grid
        // collapses (0.6 → 1mm) and every cell collides on the unique key.
        $detectUnit = static function (array $rawVals): string {
            $maxBare = 0.0;
            foreach ($rawVals as $s) {
                $s = trim((string) $s);
                if ($s === '' || preg_match('/[a-z\'"]/i', $s)) continue;   // suffixed → self-describes
                $n = (float) str_replace(',', '', $s);
                if ($n > $maxBare) $maxBare = $n;
            }
            if ($maxBare <= 0)  return 'mm';
            if ($maxBare < 50)  return 'm';    // 0.6–4.8 → metres
            if ($maxBare < 600) return 'cm';   // 60–480 → centimetres
            return 'mm';
        };

        $finalise = function () use (&$bands, &$current, &$cells) {
            if ($current !== null && $cells) {
                // One block can serve several bands ("AAA / AA") — same grid.
                foreach ((array) $current as $code) {
                    $bands[] = ['code' => $code, 'cells' => $cells];
                }
            }
            $current = null;
            $cells   = [];
        };

        foreach ($rows as $rowNum => $row) {
            $a = trim((string) ($row['A'] ?? ''));

            // Band header — one block may list several codes ("Band AAA / AA"
            // or "Band A, B"). Capture all; the grid applies to each. Requires
            // "band "/"bnad " so it won't match "abandon".
            if (preg_match('/(?:price\s+)?b(?:and|nad)\s+(.+)$/i', $a, $m)) {
                $codes = [];
                foreach (preg_split('#[/,]#', $m[1]) ?: [] as $tok) {
                    // Collapse internal whitespace so "VFM  BO" -> "VFM BO".
                    $tok = strtoupper(trim(preg_replace('/\s+/', ' ', $tok)));
                    // Band codes can be long, descriptive names — suppliers use
                    // spaces, digits, "&", and tape/finish words ("50mm Bamboo &
                    // Gloss String", "35mm Herringbone Tapes"). Allow up to 60
                    // chars (matches the band_code column) plus & . - ( ). The
                    // old 12-char cap silently dropped these headers, stacking
                    // their grids onto the previous band and colliding on the
                    // unique cell key. Must still start alphanumeric so a
                    // descriptive "Band widths in mm" prose row isn't a code.
                    if (preg_match('/^[A-Z0-9][A-Z0-9 &.()-]{0,59}$/', $tok)) $codes[] = $tok;
                }
                if ($codes) {
                    $finalise();
                    $current   = $codes;
                    $widths    = [];
                    $dropCol   = null;
                    $expecting = 'widths';
                    continue;
                }
            }

            if ($current === null) continue;

            if ($expecting === 'widths') {
                // Gather RAW (unconverted) candidate width strings from cols B+
                // so we can detect their unit before converting. Skip column A
                // (drop axis / label) and word labels ("Mtrs", "(ins)"). Need
                // ≥2 to accept this as the widths row.
                $rawWidths = [];
                foreach ($row as $col => $val) {
                    if ($col === 'A') continue;
                    $s = trim((string) ($val ?? ''));
                    if ($s === '' || !preg_match('/\d/', $s)) continue;   // a width must carry a number
                    // Accept a dimension even with a unit suffix (25cm, 610mm,
                    // 1.5m, 24"): strip the number + a known unit and require
                    // nothing alphabetic left. Word labels ("Mtrs", "Metric",
                    // "(ins)") have no digit (or letters remain) so are still
                    // skipped — but "25cm"-style headers now parse instead of
                    // being thrown out and the first price row grabbed instead.
                    $probe = preg_replace('/[0-9.,\s]+|(?:mm|cm|inches|inch|ins|in|m)|["\x27]/i', '', $s);
                    if ($probe === '') {
                        $rawWidths[$col] = $s;
                    }
                }
                if (count($rawWidths) >= 2) {
                    if ($bareUnit === null) $bareUnit = $detectUnit($rawWidths);
                    $widths = [];
                    foreach ($rawWidths as $col => $s) {
                        $w = ptp_parse_dimension($s, $bareUnit);
                        if ($w !== null && $w > 0) $widths[$col] = $w;
                    }
                    if (count($widths) >= 2) $expecting = 'data';
                }
                continue;
            }

            // expecting === 'data'
            //
            // The drop column isn't always A — some files put it in B (with
            // column A holding label text in the widths row). On the first
            // data row of each band, sniff for the leftmost non-widths column
            // that holds a parseable dimension and lock it in.
            $unit = $bareUnit ?? 'mm';
            if ($dropCol === null) {
                foreach ($row as $col => $val) {
                    if (isset($widths[$col])) continue;
                    $d = ptp_parse_dimension((string) ($val ?? ''), $unit);
                    if ($d !== null && $d > 0) {
                        $dropCol = $col;
                        break;
                    }
                }
                if ($dropCol === null) continue; // not a data row yet
            }

            $dropMm = ptp_parse_dimension((string) ($row[$dropCol] ?? ''), $unit);
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
