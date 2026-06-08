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
        ];
    }

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
        ];
    }

    /** Grouping for the Settings colour-picker UI. */
    function job_status_groups(): array
    {
        return [
            'Quote stages'          => ['draft', 'sent', 'accepted', 'declined', 'ordered'],
            'Appointments & job'    => ['appointment_booked', 'booked', 'fitted', 'invoiced', 'paid', 'cancelled', 'no_show'],
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
     * Map a calendar appointment to one pipeline status (its "stage").
     * Cancel / no-show on the appointment always win; otherwise the quote's
     * own fitted/invoiced/paid status carries through. An appointment with no
     * linked quote is a measure/survey visit — its own "Appointment booked"
     * stage; once it's tied to a quote it's a fitting (booked → fitted).
     */
    function job_stage(string $apptStatus, ?string $quoteStatus): string
    {
        if ($apptStatus === 'cancelled') return 'cancelled';
        if ($apptStatus === 'no_show')   return 'no_show';
        if (in_array($quoteStatus, ['fitted', 'invoiced', 'paid'], true)) {
            return (string) $quoteStatus;
        }
        // No linked quote = a booked appointment (measure/survey), not a fitting.
        if ($quoteStatus === null || $quoteStatus === '') return 'appointment_booked';
        if ($apptStatus === 'completed') return 'fitted';
        return 'booked';
    }

    /** Calendar card colour for an appointment, from a resolved palette. */
    function job_stage_colour(string $apptStatus, ?string $quoteStatus, ?array $palette = null): string
    {
        return job_status_colour(job_stage($apptStatus, $quoteStatus), $palette);
    }
}
