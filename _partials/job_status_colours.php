<?php
declare(strict_types=1);

/**
 * Traffic-light colours for calendar appointments, keyed by JOB STAGE.
 *
 * A calendar entry is a fitting, so it shows the *fitting* journey. The
 * stage is derived from the appointment status + the linked quote's status:
 *
 *   cancelled / no_show  — the appointment's own terminal states (override)
 *   fitted / invoiced / paid — taken from the quote (job) status
 *   completed appt       — treated as fitted
 *   otherwise            — "booked" (a scheduled-but-not-yet-done fitting)
 *
 * The earlier stages (sent / accepted / declined) belong to the quotes list,
 * not the dated calendar, so they're not in this palette.
 *
 * Defaults live here; per-client overrides come later (Settings colour
 * pickers). job_stage_palette() is the single source of truth shared by the
 * server render and the JS that re-renders cards on the live-refresh poll.
 */

if (!function_exists('job_stage_palette')) {
    function job_stage_palette(): array
    {
        return [
            'booked'    => '#2563eb',  // fitting booked (scheduled)
            'fitted'    => '#0d9488',  // fitted / completed
            'invoiced'  => '#ea580c',  // invoiced
            'paid'      => '#475569',  // paid
            'cancelled' => '#dc2626',  // cancelled
            'no_show'   => '#9ca3af',  // no-show
        ];
    }

    function job_stage_labels(): array
    {
        return [
            'booked'    => 'Fitting booked',
            'fitted'    => 'Fitted',
            'invoiced'  => 'Invoiced',
            'paid'      => 'Paid',
            'cancelled' => 'Cancelled',
            'no_show'   => 'No-show',
        ];
    }

    function job_stage(string $apptStatus, ?string $quoteStatus): string
    {
        if ($apptStatus === 'cancelled') return 'cancelled';
        if ($apptStatus === 'no_show')   return 'no_show';
        if (in_array($quoteStatus, ['fitted', 'invoiced', 'paid'], true)) {
            return (string) $quoteStatus;
        }
        if ($apptStatus === 'completed') return 'fitted';
        return 'booked';
    }

    function job_stage_colour(string $apptStatus, ?string $quoteStatus): string
    {
        $palette = job_stage_palette();
        return $palette[job_stage($apptStatus, $quoteStatus)] ?? '#2563eb';
    }
}
