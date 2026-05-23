<?php
declare(strict_types=1);

/**
 * Relative-time formatter — "2 days ago", "1 wk ago", "just now".
 *
 * Used wherever an admin grid shows an Updated / Created column.
 * Switches to an absolute date once it's past a year so old rows read
 * as a date, not "47 wk ago" which is harder to parse.
 *
 * Returns "—" for null/empty inputs and the input as-is if it doesn't
 * parse — never throws.
 */
function time_ago(?string $ts): string
{
    if (!$ts) return '—';
    $t = strtotime($ts);
    if ($t === false) return (string) $ts;
    $diff = time() - $t;
    if ($diff < 60)         return 'just now';
    if ($diff < 3600)       return floor($diff / 60)    . ' min ago';
    if ($diff < 86400)      return floor($diff / 3600)  . ' hr ago';
    if ($diff < 86400 * 7)  return floor($diff / 86400) . ' day' . ($diff < 86400 * 2 ? '' : 's') . ' ago';
    if ($diff < 86400 * 30) return floor($diff / (86400 * 7))  . ' wk ago';
    if ($diff < 86400 * 365) return date('j M', $t);
    return date('j M Y', $t);
}
