<?php
declare(strict_types=1);

/**
 * Pure-SVG pie / donut chart — no JS, no external library.
 *
 * Usage:
 *   require_once __DIR__ . '/../_partials/pie_chart.php';
 *   echo render_pie_chart([
 *       ['label' => 'Roller blinds',  'value' => 1240.00],
 *       ['label' => 'Venetian',       'value' => 480.00],
 *       ['label' => 'Roman',          'value' => 200.00],
 *   ], [
 *       'size'   => 180,           // px, pie diameter (default 180)
 *       'donut'  => true,          // donut hole in the middle (default true)
 *       'unit'   => '£',           // legend value prefix (default '')
 *       'format' => 'money',       // 'money' | 'integer' | 'percent' (default 'money')
 *       'colours' => [...],        // override palette
 *   ]);
 *
 * Zero-value or negative slices are skipped. An empty input renders a
 * grey "no data" disc so the layout doesn't collapse.
 *
 * The legend is rendered alongside the chart with colour swatches +
 * value + percentage per slice.
 */

/**
 * Default colour palette — tuned to be distinguishable, colour-blind-
 * friendly (Wong palette + a few extras), and to look OK against a
 * white panel background.
 */
function pc_default_palette(): array
{
    return [
        '#1f3b5b',   // dark navy (matches the app's primary)
        '#15803d',   // green
        '#f59e0b',   // amber
        '#7c3aed',   // violet
        '#0891b2',   // cyan
        '#dc2626',   // red
        '#0ea5e9',   // sky
        '#a16207',   // brown
        '#9333ea',   // purple
        '#65a30d',   // lime
    ];
}

/**
 * Convert a polar coordinate (angle in degrees, radius) to a flat
 * (x, y) point on the SVG canvas, with the origin at the centre and
 * 0° at 12 o'clock (clockwise). Matches what humans expect from a pie.
 */
function pc_polar(float $cx, float $cy, float $radius, float $deg): array
{
    $rad = ($deg - 90) * M_PI / 180;
    return [
        $cx + $radius * cos($rad),
        $cy + $radius * sin($rad),
    ];
}

/**
 * Format a number for the legend according to the requested style.
 */
function pc_format_value(float $v, string $style, string $unit): string
{
    return match ($style) {
        'integer' => number_format($v, 0),
        'percent' => number_format($v, 1) . '%',
        default   => $unit . number_format($v, 2),
    };
}

/**
 * Render the chart. Returns HTML — caller is responsible for placing
 * it in a flex/grid layout if they want it inline with other panels.
 */
function render_pie_chart(array $slices, array $opts = []): string
{
    $size    = (int)    ($opts['size']    ?? 180);
    $donut   = (bool)   ($opts['donut']   ?? true);
    $unit    = (string) ($opts['unit']    ?? '');
    $format  = (string) ($opts['format']  ?? 'money');
    $palette = $opts['colours'] ?? pc_default_palette();

    // Drop empty/negative slices; build the renderable set with totals.
    $clean = [];
    foreach ($slices as $s) {
        $v = (float) ($s['value'] ?? 0);
        if ($v <= 0) continue;
        $clean[] = [
            'label' => (string) ($s['label'] ?? ''),
            'value' => $v,
        ];
    }
    $total = array_sum(array_column($clean, 'value'));

    $cx = $cy = $size / 2;
    $r  = ($size / 2) - 2;   // 2px breathing room inside the viewBox
    $innerR = $donut ? $r * 0.55 : 0;

    // Empty-state — render a soft grey disc with "no data" caption.
    if ($total <= 0) {
        $out  = '<div class="pie-wrap">';
        $out .= '<svg viewBox="0 0 ' . $size . ' ' . $size . '" '
              . 'width="' . $size . '" height="' . $size . '" '
              . 'role="img" aria-label="No data to chart">';
        $out .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r
              . '" fill="#f3f4f6" />';
        if ($donut) {
            $out .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $innerR
                  . '" fill="#fff" />';
        }
        $out .= '<text x="' . $cx . '" y="' . ($cy + 4) . '" text-anchor="middle" '
              . 'font-size="12" fill="#9ca3af">no data</text>';
        $out .= '</svg></div>';
        return $out;
    }

    // Build slice paths.
    //
    // For a single slice covering 100% (only one item in the input),
    // SVG's arc command can't draw a full circle in a single path —
    // so we special-case it as a plain <circle>.
    $paths = '';
    $legendRows = '';
    $startDeg = 0.0;
    $colourCount = count($palette);

    if (count($clean) === 1) {
        $colour = $palette[0];
        $paths .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r
                . '" fill="' . $colour . '" />';
        if ($donut) {
            $paths .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $innerR
                    . '" fill="#fff" />';
        }
        $pct = 100.0;
        $legendRows .= '<li><span class="pie-swatch" style="background:'
            . $colour . '"></span>'
            . '<span class="pie-lbl">' . htmlspecialchars((string) $clean[0]['label'], ENT_QUOTES) . '</span>'
            . '<span class="pie-val">' . pc_format_value($clean[0]['value'], $format, $unit) . '</span>'
            . '<span class="pie-pct">' . number_format($pct, 1) . '%</span></li>';
    } else {
        foreach ($clean as $i => $slice) {
            $share   = $slice['value'] / $total;
            $endDeg  = $startDeg + $share * 360;

            [$x1, $y1] = pc_polar($cx, $cy, $r, $startDeg);
            [$x2, $y2] = pc_polar($cx, $cy, $r, $endDeg);

            // Large-arc flag: 1 if the slice is more than half the pie.
            $largeArc = ($endDeg - $startDeg) > 180 ? 1 : 0;
            $colour   = $palette[$i % $colourCount];

            $path = sprintf(
                'M %.4f %.4f L %.4f %.4f A %.4f %.4f 0 %d 1 %.4f %.4f Z',
                $cx, $cy,
                $x1, $y1,
                $r, $r, $largeArc,
                $x2, $y2
            );
            $paths .= '<path d="' . $path . '" fill="' . $colour . '" '
                    . 'stroke="#fff" stroke-width="1.5" />';

            $pct = $share * 100;
            $legendRows .= '<li><span class="pie-swatch" style="background:' . $colour . '"></span>'
                . '<span class="pie-lbl">' . htmlspecialchars((string) $slice['label'], ENT_QUOTES) . '</span>'
                . '<span class="pie-val">' . pc_format_value($slice['value'], $format, $unit) . '</span>'
                . '<span class="pie-pct">' . number_format($pct, 1) . '%</span></li>';

            $startDeg = $endDeg;
        }
        // Donut hole on top — drawn after all slices so it punches through.
        if ($donut) {
            $paths .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $innerR
                    . '" fill="#fff" />';
        }
    }

    // Centre label — useful in donut mode to show total at a glance.
    $centreLabel = '';
    if ($donut) {
        $centreLabel = '<text x="' . $cx . '" y="' . ($cy - 4) . '" text-anchor="middle" '
                     . 'font-size="11" fill="#6b7280" font-weight="600" '
                     . 'text-transform="uppercase" letter-spacing="0.05em">Total</text>'
                     . '<text x="' . $cx . '" y="' . ($cy + 14) . '" text-anchor="middle" '
                     . 'font-size="14" fill="#1f3b5b" font-weight="700">'
                     . htmlspecialchars(pc_format_value($total, $format, $unit), ENT_QUOTES)
                     . '</text>';
    }

    $out  = '<div class="pie-wrap">';
    $out .= '<svg viewBox="0 0 ' . $size . ' ' . $size . '" '
          . 'width="' . $size . '" height="' . $size . '" '
          . 'role="img" aria-label="Pie chart">';
    $out .= $paths . $centreLabel;
    $out .= '</svg>';
    $out .= '<ul class="pie-legend">' . $legendRows . '</ul>';
    $out .= '</div>';
    return $out;
}
