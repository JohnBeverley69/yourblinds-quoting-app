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

/*
 * An option left on "None" or "Select", or any choice starting with the word
 * "No" ("No Motor", "No Pelmet"), says nothing, so paperwork leaves it off:
 * quotes, invoices, the online quote and purchase orders. "No" must be a
 * whole word, so "Nordic", "Noir" or "Normal" still print, as does
 * "Selected Colour".
 */
if (!function_exists('extra_is_blank_choice')) {
    function extra_is_blank_choice(array $ex): bool
    {
        $c = trim((string) ($ex['choice_label_snapshot'] ?? ''));
        if ($c === '') return false;
        if (preg_match('/^[\W_]*no(?![\p{L}\p{N}])/iu', $c)) return true;   // "No", "No Motor", "No - Standard"
        return (bool) preg_match('/^[\s\-\x{2013}\x{2014}._*()\[\]]*(none|(please\s+)?select)[\s\-\x{2013}\x{2014}._*()\[\]\x{2026}]*$/iu', $c);
    }
}
