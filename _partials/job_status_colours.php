<?php
declare(strict_types=1);

/**
 * Traffic-light status colours — the single source of truth for colouring a
 * job wherever it shows up: the calendar (appointment cards) and the orders
 * list (status pills). A job is the SAME colour everywhere it appears.
 *
 * The palette is keyed by the full pipeline status set:
 *
 *   Quote side:   draft, sent, accepted, declined, ordered
 *   Job side:     booked, fitted, invoiced, paid, cancelled, no_show
 *
 * Defaults live here; each tenant can override any colour on the Settings
 * page (stored as a JSON map in client_settings.job_status_colours and merged
 * over these defaults by job_client_palette()).
 *
 * Calendar appointments don't carry a "status" from this set directly — their
 * stage is derived from the appointment status + the linked quote status via
 * job_stage().
 */

if (!function_exists('job_status_defaults')) {

    /** Built-in colour for every pipeline status. */
    function job_status_defaults(): array
    {
        return [
            'draft'     => '#7c3aed',  // quote drafted   (purple)
            'sent'      => '#f59e0b',  // quote sent      (amber)
            'accepted'  => '#16a34a',  // accepted        (green)
            'declined'  => '#dc2626',  // declined        (red)
            'ordered'           => '#0891b2',  // ordered            (cyan)
            'appointment_booked'=> '#2563eb',  // appointment booked (blue)
            'booked'            => '#6366f1',  // fitting booked     (indigo)
            'fitted'            => '#0d9488',  // fitted             (teal)
            'invoiced'  => '#ea580c',  // invoiced        (orange)
            'paid'      => '#475569',  // paid            (slate)
            'cancelled' => '#b91c1c',  // cancelled       (dark red)
            'no_show'   => '#9ca3af',  // no-show         (grey)
            'issue'     => '#e11d48',  // issue / problem (crimson) — an OVERLAY
        ];                              //  flag, not a stage: shows as a ⚠ pill
    }                                   //  + ring on top of the card's stage colour.

    /** Human label for each status. */
    function job_status_labels(): array
    {
        return [
            'draft'     => 'Quote drafted',
            'sent'      => 'Quote sent',
            'accepted'  => 'Accepted',
            'declined'  => 'Declined',
            'ordered'            => 'Ordered',
            'appointment_booked' => 'Appointment booked',
            'booked'             => 'Fitting booked',
            'fitted'             => 'Fitted',
            'invoiced'  => 'Invoiced',
            'paid'      => 'Paid',
            'cancelled' => 'Cancelled',
            'no_show'   => 'No-show',
            'issue'     => 'Issue',
        ];
    }

    /** Grouping for the Settings colour-picker UI. */
    function job_status_groups(): array
    {
        return [
            'Quote stages'          => ['draft', 'sent', 'accepted', 'declined', 'ordered'],
            'Appointments & job'    => ['appointment_booked', 'booked', 'fitted', 'invoiced', 'paid', 'cancelled', 'no_show'],
            'Flags'                 => ['issue'],
        ];
    }

    /** A #rrggbb string, or null if not a valid hex colour. */
    function job_status_sanitise_hex(?string $v): ?string
    {
        $v = strtolower(trim((string) $v));
        return preg_match('/^#[0-9a-f]{6}$/', $v) === 1 ? $v : null;
    }

    /**
     * The resolved palette for a tenant: defaults with their saved overrides
     * merged on top. Cached per client_id for the request. Bad/missing data
     * silently falls back to defaults — colours must never break a page.
     */
    function job_client_palette(int $clientId): array
    {
        static $cache = [];
        if (isset($cache[$clientId])) return $cache[$clientId];

        $palette = job_status_defaults();

        try {
            $st = db()->prepare(
                'SELECT job_status_colours FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $st->execute([$clientId]);
            $raw = $st->fetchColumn();
            if (is_string($raw) && $raw !== '') {
                $over = json_decode($raw, true);
                if (is_array($over)) {
                    foreach ($palette as $key => $_) {
                        $hex = job_status_sanitise_hex($over[$key] ?? null);
                        if ($hex !== null) $palette[$key] = $hex;
                    }
                }
            }
        } catch (Throwable $e) {
            // Column not migrated yet, or DB hiccup — defaults are fine.
        }

        return $cache[$clientId] = $palette;
    }

    /** Look up one status colour from a resolved palette (or the defaults). */
    function job_status_colour(string $status, ?array $palette = null): string
    {
        $palette = $palette ?? job_status_defaults();
        return $palette[$status] ?? ($palette['booked'] ?? '#2563eb');
    }

    /**
     * Readable text colour (#fff or near-black) for a given background, by
     * perceived luminance — so a pill/card stays legible whatever colour the
     * tenant picks.
     */
    function job_status_text_colour(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#ffffff';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Rec. 601 luma; >150 reads as a light background.
        $luma = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
        return $luma > 150 ? '#1f2937' : '#ffffff';
    }

    /**
     * A translucent wash of a colour — for the richer week/day cards, where a
     * solid fill would swamp the text. Returns an rgba() string so the same
     * hue reads through in both light and dark themes; pair it with a solid
     * border/accent of the source colour for a glanceable match to the palette.
     */
    function job_status_tint(string $hex, float $alpha = 0.14): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return 'transparent';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba($r, $g, $b, $alpha)";
    }

    /**
     * Map a calendar appointment to one pipeline status (its "stage"), aware of
     * whether it's the MEASURE visit or the FITTING — the two track different
     * halves of the job:
     *
     *   measure  : Appointment booked (no quote) → draft → sent → accepted /
     *              declined / ordered, following the linked quote's life.
     *   fitting  : Fitting booked (accepted/ordered, scheduled) → fitted →
     *              invoiced → paid.
     *
     * Cancel / no-show on the appointment always win.
     */
    function job_stage(string $apptStatus, ?string $quoteStatus, string $apptKind = 'measure'): string
    {
        if ($apptStatus === 'cancelled') return 'cancelled';
        if ($apptStatus === 'no_show')   return 'no_show';

        $qs = (string) ($quoteStatus ?? '');

        if ($apptKind === 'fitting') {
            // The install visit. Accepted/ordered read as "fitting booked"
            // (scheduled, awaiting the fit); then it tracks the job to paid.
            if (in_array($qs, ['fitted', 'invoiced', 'paid'], true)) return $qs;
            if ($apptStatus === 'completed') return 'fitted';
            return 'booked';
        }

        // Measure / survey visit — follows the quote through its early life.
        if ($qs === '') return 'appointment_booked';   // booked, no quote yet
        if (in_array($qs, ['draft', 'sent', 'accepted', 'declined', 'ordered',
                           'fitted', 'invoiced', 'paid'], true)) {
            return $qs;
        }
        return 'appointment_booked';
    }

    /** Calendar card colour for an appointment, from a resolved palette. */
    function job_stage_colour(string $apptStatus, ?string $quoteStatus, ?array $palette = null, string $apptKind = 'measure'): string
    {
        return job_status_colour(job_stage($apptStatus, $quoteStatus, $apptKind), $palette);
    }
}
