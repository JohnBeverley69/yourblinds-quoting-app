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
