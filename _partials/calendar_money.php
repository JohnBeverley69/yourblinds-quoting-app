<?php
declare(strict_types=1);

/**
 * Calendar money helpers — shared by the month / week / day views so a job's
 * order value, amount received and outstanding balance read the same
 * everywhere. Gated by client_settings.calendar_show_money (the Settings
 * checkbox); the caller checks the flag and only renders when it's on.
 *
 * "Received" = the paid deposit (quotes.deposit_amount when deposit_paid_at is
 * set) PLUS the sum of the payments table for that quote — matching the
 * outstanding calc on the quote-edit Payments panel.
 */

if (!function_exists('calendar_money_for_quotes')) {

    /**
     * Batch-load money figures for a set of quote ids (one round-trip for the
     * quotes, one for the payments). Returns a map:
     *   quoteId => [
     *     'total'    => float, 'received' => float, 'balance' => float,
     *     'paid'     => bool,                    // fully settled?
     *   ]
     * Ids with no matching quote are simply absent from the result.
     */
    function calendar_money_for_quotes(PDO $pdo, int $clientId, array $quoteIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $quoteIds))));
        if (!$ids) return [];

        $place = implode(',', array_fill(0, count($ids), '?'));
        $out = [];

        try {
            $qs = $pdo->prepare(
                "SELECT id, total, deposit_amount, deposit_paid_at, status
                   FROM quotes WHERE client_id = ? AND id IN ($place)"
            );
            $qs->execute(array_merge([$clientId], $ids));
            foreach ($qs->fetchAll() as $r) {
                $dep = !empty($r['deposit_paid_at']) ? (float) ($r['deposit_amount'] ?? 0) : 0.0;
                $out[(int) $r['id']] = [
                    'total'    => (float) $r['total'],
                    'deposit'  => $dep,
                    'payments' => 0.0,
                    'status'   => (string) $r['status'],
                ];
            }
        } catch (Throwable $e) {
            return [];   // quotes table missing — nothing to show
        }

        // Payments are an add-on; the table may not exist. Treat as 0 if so.
        try {
            $ps = $pdo->prepare(
                "SELECT quote_id, COALESCE(SUM(amount), 0) AS s
                   FROM payments WHERE client_id = ? AND quote_id IN ($place)
               GROUP BY quote_id"
            );
            $ps->execute(array_merge([$clientId], $ids));
            foreach ($ps->fetchAll() as $r) {
                $qid = (int) $r['quote_id'];
                if (isset($out[$qid])) $out[$qid]['payments'] = (float) $r['s'];
            }
        } catch (Throwable $e) { /* no payments table — fine */ }

        foreach ($out as &$m) {
            $received     = round($m['deposit'] + $m['payments'], 2);
            $m['received'] = $received;
            $m['balance']  = round($m['total'] - $received, 2);
            // Settled if the money's all in OR the quote's been marked paid.
            $m['paid']     = ($m['total'] > 0 && $m['balance'] <= 0.0049)
                          || $m['status'] === 'paid';
        }
        unset($m);

        return $out;
    }

    /**
     * Render the money line for a card. $onDark = true for the month view's
     * solid-colour pills (white text); false for the lighter week/day cards
     * (dark text). Returns '' when there's nothing to show.
     */
    function calendar_money_html(array $m, bool $onDark = false): string
    {
        if (!$m) return '';
        $fmt = static fn ($n) => '£' . number_format((float) $n, 2);
        $e   = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if (!empty($m['paid'])) {
            $clr = $onDark ? '#ffffff' : '#047857';
            return '<div class="cal-money" style="margin-top:.1875rem;font-size:.6875rem;'
                 . 'font-weight:700;color:' . $clr . '">✓ PAID ' . $e($fmt($m['total'])) . '</div>';
        }

        $base   = $onDark ? 'rgba(255,255,255,0.90)' : 'var(--text-secondary)';
        $strong = $onDark ? '#ffffff' : 'var(--text-primary)';
        return '<div class="cal-money" style="margin-top:.1875rem;font-size:.6875rem;'
             . 'line-height:1.3;color:' . $base . '">'
             . $e($fmt($m['total'])) . ' &middot; paid ' . $e($fmt($m['received']))
             . ' &middot; <strong style="color:' . $strong . '">bal '
             . $e($fmt($m['balance'])) . '</strong></div>';
    }
}
