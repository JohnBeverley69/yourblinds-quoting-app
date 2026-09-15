<?php
declare(strict_types=1);

/**
 * Canonical machine codes for the Bev Roller Blinds label-referenced options.
 *
 * The roller worksheet label used to resolve options by NAME (opt:<name>), which
 * broke silently whenever an option was renamed. These stable codes are the
 * rename-proof keys: product_extras.code carries them, the worksheet template's
 * field sources use opt:<code>, and worksheet-print resolves opt:<code> first
 * (falling back to name for any un-migrated layout).
 *
 * Keyed by the option's lower-cased NAME so a name→code migration is a lookup.
 * Only the label-referenced options are listed; anything else keeps/derives its
 * own code. The fascia cascade is already coded (kept here so a layout that
 * references those by name also migrates).
 */
function roller_label_code_map(): array
{
    return [
        // Fitting / fabric
        'exact or recess'                    => 'exact_or_recess',
        'fit height'                         => 'fit_height',
        'fabric roll'                        => 'fabric_roll',
        'fabric strip'                       => 'fabric_strip',
        // Control
        'control options'                    => 'control_options',
        'control side'                       => 'control_side',
        'chain type'                         => 'chain_type',
        'mech colour'                        => 'mech_colour',
        'remote options'                     => 'remote_options',
        'child safety'                       => 'child_safety',
        'bracket covers?'                    => 'bracket_covers',
        // Bottom bar
        'bottom bar options'                 => 'bottom_bar_options',
        'senses bottom bar colour'           => 'senses_bottom_bar_colour',
        'unishade bottom bar colour'         => 'unishade_bottom_bar_colour',
        'senses bottom bar end cap colours'  => 'senses_bottom_bar_end_cap_colours',
        'uni shade end cap colours'          => 'unishade_end_cap_colours',
        // Fascia / profile
        'fascia options'                     => 'fascia_options',
        'fascia sizing'                      => 'fascia_sizing',
        'fascia width'                       => 'fascia_width',
        'number of blinds?'                  => 'fascia_blind_count',
        'senses profile colour'              => 'senses_profile_colour',
        'll profile colour'                  => 'll_profile_colour',
        'senses end cap colour'              => 'senses_end_cap_colour',
        'll end cap colour'                  => 'll_end_cap_colour',
        'fixings'                            => 'fixings',
        // Trims / extras
        'scallops and trims'                 => 'scallops_and_trims',
        'braid colour'                       => 'braid_colour',
        'pole'                               => 'pole',
        'optional extras'                    => 'optional_extras',
    ];
}

/** Slug fallback for any option not in the canonical map. */
function roller_label_slug(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
    return trim($s, '_');
}
