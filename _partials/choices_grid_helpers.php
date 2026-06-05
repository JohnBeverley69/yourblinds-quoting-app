<?php
declare(strict_types=1);

/**
 * Shared helpers for the inline-editable choices grid.
 *
 * make_render_system_multi_select($systems) returns the closure the
 * choices_grid partial expects in `$renderSystemMultiSelect`. The
 * closure emits the "Available on" multi-select widget for one
 * existing choice row.
 *
 * Behaviour (per the JS in choices_grid_js.php):
 *   - Current row's own system: ticked + DISABLED (use × to delete).
 *   - All other specific systems: clickable; ticking spawns a sibling.
 *   - "All systems" entry: ticked+disabled when this row IS All systems;
 *                          unticked+enabled when this row is system-
 *                          specific — ticking converts to All systems
 *                          in place.
 */
/**
 * make_render_band_multi_select($knownBands, $bandsByChoice) returns
 * the closure the choices_grid partial expects in
 * `$renderBandMultiSelect`. The closure emits the "Available for
 * bands" multi-select widget for one existing choice row.
 *
 * Model differs from systems:
 *   - A choice can apply to MULTIPLE bands (no row-spawning).
 *   - Empty band list = "applies to every band" (the migration's
 *     default). Ticking "All bands" clears the junction rows.
 *
 * Summary shows:
 *   - "All bands"             when the choice has no band scope
 *   - "<band name>"           when exactly one
 *   - "N bands"               when more (full list inside the
 *                             dropdown anyway).
 */
function make_render_band_multi_select(array $knownBands, array $bandsByChoice): Closure
{
    return static function (int $choiceId) use ($knownBands, $bandsByChoice): string {
        $picked = $bandsByChoice[$choiceId] ?? [];
        $isAll  = empty($picked);

        if ($isAll) {
            $summaryText = 'All bands';
        } elseif (count($picked) === 1) {
            $summaryText = $picked[0];
        } else {
            $summaryText = count($picked) . ' bands';
        }

        $html  = '<details class="multi-select row-multi row-bands">';
        $html .= '<summary>' . htmlspecialchars($summaryText, ENT_QUOTES) . '</summary>';
        $html .= '<div class="multi-opts">';

        $html .= '<label>'
               . '<input type="checkbox" class="row-band-tick" data-band=""'
               . ($isAll ? ' checked' : '')
               . '> <strong>All bands</strong>'
               . '</label>';

        if ($knownBands) {
            $html .= '<hr>';
            foreach ($knownBands as $b) {
                $checked = in_array($b, $picked, true);
                $html .= '<label>'
                       . '<input type="checkbox" class="row-band-tick" data-band="'
                       . htmlspecialchars($b, ENT_QUOTES) . '"'
                       . ($checked ? ' checked' : '')
                       . '> ' . htmlspecialchars($b, ENT_QUOTES)
                       . '</label>';
            }
        }
        $html .= '</div></details>';
        return $html;
    };
}

function make_render_system_multi_select(array $systems): Closure
{
    return static function (?int $currentSystemId) use ($systems): string {
        $isAll = $currentSystemId === null;

        $summaryText = 'All systems';
        if (!$isAll) {
            foreach ($systems as $s) {
                if ((int) $s['id'] === $currentSystemId) {
                    $summaryText = (string) $s['name'];
                    break;
                }
            }
        }

        $html  = '<details class="multi-select row-multi">';
        $html .= '<summary>' . htmlspecialchars($summaryText, ENT_QUOTES) . '</summary>';
        $html .= '<div class="multi-opts">';

        $html .= '<label>'
               . '<input type="checkbox" class="row-system-tick" data-system="0"'
               . ($isAll ? ' checked disabled' : '')
               . '>'
               . ' <strong>All systems</strong>'
               . '</label>';

        if ($systems) {
            $html .= '<hr>';
            foreach ($systems as $s) {
                $sid       = (int) $s['id'];
                $isCurrent = ($sid === $currentSystemId);
                $html .= '<label>'
                       . '<input type="checkbox" class="row-system-tick" data-system="' . $sid . '"'
                       . ($isCurrent ? ' checked' : '')
                       . ($isCurrent ? ' disabled' : '')
                       . '> ' . htmlspecialchars((string) $s['name'], ENT_QUOTES)
                       . '</label>';
            }
        }
        $html .= '</div></details>';
        return $html;
    };
}
