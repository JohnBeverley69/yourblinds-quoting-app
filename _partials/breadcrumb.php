<?php
declare(strict_types=1);

/**
 * Breadcrumb renderer.
 *
 * Usage:
 *   require __DIR__ . '/../../_partials/breadcrumb.php';
 *   echo render_breadcrumb([
 *       ['Products',         '/admin/products/index.php'],
 *       ['Vertical Blinds',  '/admin/products/edit.php?id=8'],
 *       ['Options',          '/admin/products/extras.php?product_id=8'],
 *       ['Wand Option',      null],   // current page — no link
 *   ]);
 *
 * Output is a compact " A › B › C " trail. Items with a NULL href
 * render as plain text (the "you are here" segment).
 *
 * Inline-styled so it works anywhere without external CSS tweaks.
 */
function render_breadcrumb(array $segments): string
{
    if (!$segments) return '';
    $parts = [];
    foreach ($segments as $i => $seg) {
        $label = (string) ($seg[0] ?? '');
        $href  = $seg[1] ?? null;
        if ($label === '') continue;
        if ($href !== null && $href !== '') {
            $parts[] = '<a href="' . htmlspecialchars((string) $href, ENT_QUOTES)
                     . '" style="color:#1f3b5b;text-decoration:none">'
                     . htmlspecialchars($label, ENT_QUOTES)
                     . '</a>';
        } else {
            $parts[] = '<span style="color:#374151;font-weight:600">'
                     . htmlspecialchars($label, ENT_QUOTES)
                     . '</span>';
        }
    }
    $sep = ' <span style="color:#9ca3af;margin:0 0.3125rem">&rsaquo;</span> ';
    return '<nav aria-label="Breadcrumb" '
         . 'style="font-size:0.875rem;color:#6b7280;margin-bottom:0.875rem">'
         . implode($sep, $parts)
         . '</nav>';
}
