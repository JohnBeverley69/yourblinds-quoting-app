<?php
declare(strict_types=1);

/**
 * Measurement units — the one place that knows how to convert between
 * the stored unit (always millimetres) and a tenant's chosen display /
 * input unit (mm / cm / m / inches).
 *
 * EVERYTHING in the database stays in millimetres. These helpers only
 * touch the edges: parsing what a user typed, and formatting what we
 * show them. The pricing engine, price tables and quote_items are never
 * affected.
 *
 * Unit codes: 'mm' | 'cm' | 'm' | 'in'.
 */

if (!function_exists('unit_is_valid')) {
    function unit_is_valid(?string $u): bool
    {
        return in_array($u, ['mm', 'cm', 'm', 'in'], true);
    }

    /** Millimetres per one of the given unit. */
    function unit_factor(string $unit): float
    {
        switch ($unit) {
            case 'cm': return 10.0;
            case 'm':  return 1000.0;
            case 'in': return 25.4;
            case 'mm':
            default:   return 1.0;
        }
    }

    /** Short suffix shown after a number, e.g. 1500 mm, 60". */
    function unit_suffix(string $unit): string
    {
        switch ($unit) {
            case 'cm': return 'cm';
            case 'm':  return 'm';
            case 'in': return '"';
            case 'mm':
            default:   return 'mm';
        }
    }

    /** Human label for dropdowns / settings. */
    function unit_label(string $unit): string
    {
        switch ($unit) {
            case 'cm': return 'Centimetres (cm)';
            case 'm':  return 'Metres (m)';
            case 'in': return 'Inches (in)';
            case 'mm':
            default:   return 'Millimetres (mm)';
        }
    }

    /** Decimal places used when displaying a value in the unit. */
    function unit_decimals(string $unit): int
    {
        switch ($unit) {
            case 'cm': return 1;
            case 'm':  return 3;
            case 'in': return 2;
            case 'mm':
            default:   return 0;
        }
    }

    /** Convert a value expressed in $unit to integer millimetres. */
    function unit_to_mm(float $value, string $unit): int
    {
        return (int) round($value * unit_factor($unit));
    }

    /**
     * Convert millimetres to a number in $unit (float, rounded to the
     * unit's display precision). Use unit_format() for a display string.
     */
    function mm_to_unit(int $mm, string $unit): float
    {
        $v = $mm / unit_factor($unit);
        return round($v, unit_decimals($unit));
    }

    /**
     * Format millimetres as a display number in $unit, trailing zeros
     * trimmed. $withSuffix appends the unit suffix (mm / cm / m / ").
     * E.g. unit_format(1524, 'in') => '60"', unit_format(1500, 'm') => '1.5m'.
     */
    function unit_format(int $mm, string $unit, bool $withSuffix = true): string
    {
        $v   = mm_to_unit($mm, $unit);
        $str = number_format($v, unit_decimals($unit), '.', '');
        // Trim trailing zeros / dot for the fractional units.
        if (strpos($str, '.') !== false) {
            $str = rtrim(rtrim($str, '0'), '.');
        }
        if (!$withSuffix) return $str;
        return $unit === 'in' ? $str . '"' : $str . ' ' . unit_suffix($unit);
    }

    /**
     * The tenant's default measurement unit from client_settings. Cached
     * per-request. Falls back to 'mm' if the column / row is absent.
     */
    function client_default_unit(PDO $pdo, int $clientId): string
    {
        static $cache = [];
        if (isset($cache[$clientId])) return $cache[$clientId];
        $unit = 'mm';
        try {
            $st = $pdo->prepare(
                'SELECT default_measurement_unit FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $st->execute([$clientId]);
            $v = $st->fetchColumn();
            if (unit_is_valid($v)) $unit = (string) $v;
        } catch (Throwable $e) {
            // Column missing (migration not run) — default mm.
        }
        return $cache[$clientId] = $unit;
    }

    /**
     * The effective unit for a quote: the quote's own measurement_unit if
     * set, otherwise the tenant default. $quoteUnit is whatever was read
     * from quotes.measurement_unit (may be null / '').
     */
    function effective_unit(?string $quoteUnit, PDO $pdo, int $clientId): string
    {
        if (unit_is_valid($quoteUnit)) return (string) $quoteUnit;
        return client_default_unit($pdo, $clientId);
    }
}
