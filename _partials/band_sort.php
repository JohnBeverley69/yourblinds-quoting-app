<?php
declare(strict_types=1);

/**
 * Shared band-ordering SQL so every list ranks price bands the same way:
 * premium "A" tiers first, the longest run first (AAAA → AAA → AA → A),
 * then everything else alphabetically (B, C, D …).
 *
 * Returns an ORDER-BY fragment (WITHOUT the "ORDER BY" keyword) for a given
 * column expression, e.g.  "ORDER BY " . band_sort_sql('t.band_code').
 * Use it as the band key; append your own tie-breakers (name, colour, …)
 * after it, or put a manual sort_order BEFORE it if the list is drag-sortable.
 *
 * $col must be a trusted column expression (a literal in the query), never
 * user input — it is interpolated into SQL verbatim.
 */
if (!function_exists('band_sort_sql')) {
    function band_sort_sql(string $col = 'band_code'): string
    {
        // 1) pure A-runs (^A+$) sort ahead of everything else;
        // 2) within them, more A's = more premium, so longer first (DESC);
        // 3) otherwise plain alphabetical. Non-A rows get length key 0, so the
        //    DESC on the middle term doesn't disturb their A→Z ordering.
        return "CASE WHEN $col REGEXP '^A+\$' THEN 0 ELSE 1 END, "
             . "CASE WHEN $col REGEXP '^A+\$' THEN CHAR_LENGTH($col) ELSE 0 END DESC, "
             . "$col";
    }
}
