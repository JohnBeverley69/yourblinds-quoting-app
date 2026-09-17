<?php
declare(strict_types=1);

/**
 * The bespoke boxed roller label's grid, as DATA (shared by the print renderer
 * worksheet-print.php, the Worksheets editor, and the seed). A "box" is:
 *   [ 'grid' => [ row, row, … ], 'cut' => [ cell, cell, … ] ]
 *   row  = [ 'cells' => [cell,…], 'hide_if_empty' => bool ]
 *   cell = [ 'cap' => caption, 'src' => source, 'w' => flex-weight, 'big' => bool ]
 * A cell 'src' is one of: opt:<code> · var:<BuildVar> · order:<detailKey>.
 * roller_box_default() is today's exact printed layout (the fallback when a
 * template stores no box).
 */

if (!function_exists('roller_box_default')) {
    function roller_box_default(): array
    {
        return [
            'grid' => [
                ['cells' => [['cap' => 'Name', 'src' => 'order:name_cell', 'w' => 6], ['cap' => 'Order', 'src' => 'order:order_cell', 'w' => 4]]],
                ['cells' => [['cap' => 'Fabric', 'src' => 'order:fabric', 'w' => 6], ['cap' => 'Colour', 'src' => 'order:colour', 'w' => 4]]],
                ['cells' => [['cap' => 'Size  W × Drop', 'src' => 'order:size', 'w' => 4], ['cap' => 'Measurement', 'src' => 'order:measurement', 'w' => 3], ['cap' => 'Location', 'src' => 'order:location', 'w' => 3]]],
                ['cells' => [['cap' => 'Fabric Roll', 'src' => 'opt:fabric_roll', 'w' => 3], ['cap' => 'Control', 'src' => 'opt:control_options', 'w' => 4], ['cap' => 'Side', 'src' => 'opt:control_side', 'w' => 3]]],
                ['cells' => [['cap' => 'Mech Colour', 'src' => 'opt:mech_colour', 'w' => 3], ['cap' => 'Chain', 'src' => 'opt:chain_type', 'w' => 3], ['cap' => 'Bracket Covers', 'src' => 'opt:bracket_covers', 'w' => 4]]],
                ['cells' => [['cap' => 'Bottom Bar', 'src' => 'opt:bottom_bar_options', 'w' => 4], ['cap' => 'BB Colour', 'src' => 'order:bb_colour', 'w' => 3], ['cap' => 'BB Endcaps', 'src' => 'order:bb_endcaps', 'w' => 3]]],
                ['cells' => [['cap' => 'Fascia', 'src' => 'opt:fascia_options', 'w' => 4], ['cap' => 'Fascia Colour', 'src' => 'order:fascia_colour', 'w' => 3], ['cap' => 'Fascia Endcaps', 'src' => 'order:fascia_endcaps', 'w' => 3]]],
                ['cells' => [['cap' => 'Fixings', 'src' => 'order:fixings_val', 'w' => 3], ['cap' => 'Fabric Strip', 'src' => 'opt:fabric_strip', 'w' => 3], ['cap' => 'Scallop / Shape', 'src' => 'opt:scallops_and_trims', 'w' => 4]]],
                ['hide_if_empty' => true, 'cells' => [['cap' => 'Braid', 'src' => 'opt:braid_colour', 'w' => 3], ['cap' => 'Pole', 'src' => 'opt:pole', 'w' => 3], ['cap' => 'Child Safety', 'src' => 'opt:child_safety', 'w' => 4]]],
                ['hide_if_empty' => true, 'cells' => [['cap' => 'Remote', 'src' => 'opt:remote_options', 'w' => 5], ['cap' => 'Optional Extras', 'src' => 'opt:optional_extras', 'w' => 5]]],
            ],
            'cut' => [
                ['cap' => 'Tube', 'src' => 'var:Tube_Cut', 'w' => 2],
                ['cap' => 'Fabric W', 'src' => 'var:Fabric_W', 'w' => 2],
                ['cap' => 'Fascia', 'src' => 'var:Fascia_Cut', 'w' => 2],
                ['cap' => 'Fabric Drop', 'src' => 'var:Fabric_Drop', 'w' => 2],
                ['cap' => 'Chain', 'src' => 'var:Chain_Length', 'w' => 2],
            ],
        ];
    }
}

if (!function_exists('roller_box_normalise')) {
    /** Coerce a posted/stored box into the strict {grid:[{cells:[{cap,src,w,big}],hide_if_empty}], cut:[…]} shape. */
    function roller_box_normalise($raw): array
    {
        $mkCell = static function ($c): ?array {
            if (!is_array($c)) return null;
            $cap = trim((string) ($c['cap'] ?? ''));
            $src = trim((string) ($c['src'] ?? ''));
            if ($cap === '' && $src === '') return null;
            $w = (float) ($c['w'] ?? 1); if ($w <= 0) $w = 1;
            $out = ['cap' => $cap, 'src' => $src, 'w' => $w];
            if (!empty($c['big'])) $out['big'] = true;
            return $out;
        };
        $grid = [];
        foreach ((array) ($raw['grid'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $cells = [];
            foreach ((array) ($row['cells'] ?? []) as $c) { $cc = $mkCell($c); if ($cc) $cells[] = $cc; }
            if (!$cells) continue;
            $r = ['cells' => $cells];
            if (!empty($row['hide_if_empty'])) $r['hide_if_empty'] = true;
            $grid[] = $r;
        }
        $cut = [];
        foreach ((array) ($raw['cut'] ?? []) as $c) { $cc = $mkCell($c); if ($cc) { unset($cc['big']); $cut[] = $cc; } }
        return ['grid' => $grid, 'cut' => $cut];
    }
}
