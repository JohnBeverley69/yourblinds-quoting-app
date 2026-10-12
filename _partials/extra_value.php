<?php
declare(strict_types=1);

/*
 * The number typed into an option, as it should read on paperwork. A
 * per-unit option (priced unit price × quantity — the engine snapshots its
 * mode as 'per_unit') is a QUANTITY: "Swivel Bracket Extension × 4". Anything
 * else is a measurement in mm: "Pelmet Size: Custom Size — 123mm". Used by the
 * supplier order email, the factory's bought-in PO, the quote PDF and the
 * customer's online quote, which all used to print "— 4mm" for 4 brackets.
 */
if (!function_exists('extra_value_suffix')) {
    function extra_value_suffix(array $ex): string
    {
        if (!isset($ex['user_value']) || $ex['user_value'] === null || (float) $ex['user_value'] <= 0) {
            return '';
        }
        $n = rtrim(rtrim(number_format((float) $ex['user_value'], 2, '.', ''), '0'), '.');
        return (string) ($ex['mode'] ?? '') === 'per_unit' ? ' × ' . $n : ' — ' . $n . 'mm';
    }
}
