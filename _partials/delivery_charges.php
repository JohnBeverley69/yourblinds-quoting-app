<?php
declare(strict_types=1);

/**
 * Trade delivery charges — worked out per DELIVERY, not per order.
 *
 * Each trade account has a delivery method: Collected (free), Carrier or Van.
 * Carrier and Van each have a rule — "charge £X (+VAT) when the delivery is worth
 * less than £Y (net)" — kept in app_settings so they can be changed without a
 * code change. Today: carrier £16 under £260, van £10 under £100.
 *
 * The threshold is tested against everything going out together, because one
 * delivery can carry many orders. A DELIVERY is: the same account, the same day,
 * the same method (the delivery notes raised for that account that day). The
 * charge is fixed when the delivery notes are raised (dispatch) and sits on ONE of
 * the notes (factory_ar_delivery_notes.carriage_net); when that order is invoiced
 * it becomes a Carriage line on its invoice. Invoices stay one per order.
 *
 * An account can be marked "No delivery charge", and any one delivery's charge can
 * be waived or set to a custom amount from the Dispatch tray
 * (factory_ar_delivery_notes.carriage_override, held on every note in the group).
 *
 * Schema: migrate_delivery_charges.php. Everything here degrades to "no charge"
 * before that migration has run.
 */

require_once __DIR__ . '/app_settings.php';

/** Method key => label. Order = the order the picker shows them in. */
function dc_methods(): array
{
    return ['carrier' => 'Carrier', 'van' => 'Van', 'collect' => 'Collected'];
}

/** The method used when an account has never had one set. */
function dc_default_method(): string
{
    return 'carrier';
}

/** True once migrate_delivery_charges.php has run (cached per request). */
function dc_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT delivery_method, delivery_date, carriage_net, carriage_override FROM factory_ar_delivery_notes LIMIT 0');
            $pdo->query('SELECT account_client_id FROM factory_ar_account_delivery LIMIT 0');
            $ready = true;
        } catch (Throwable $e) { $ready = false; }
    }
    return $ready;
}

/**
 * The charge rules: ['carrier' => ['under' => 260.0, 'charge' => 16.0], 'van' => [...]].
 * Amounts are NET (VAT goes on top on the invoice). A charge of 0 = never charged.
 */
function dc_rules(): array
{
    $rules = [
        'carrier' => ['under' => 260.0, 'charge' => 16.0],
        'van'     => ['under' => 100.0, 'charge' => 10.0],
    ];
    $raw = app_setting_get('delivery_charge_rules', '');
    $j = $raw !== null && $raw !== '' ? json_decode($raw, true) : null;
    if (is_array($j)) {
        foreach (array_keys($rules) as $m) {
            foreach (['under', 'charge'] as $k) {
                if (isset($j[$m][$k]) && is_numeric($j[$m][$k]) && (float) $j[$m][$k] >= 0) {
                    $rules[$m][$k] = round((float) $j[$m][$k], 2);
                }
            }
        }
    }
    return $rules;
}

function dc_save_rules(array $rules): bool
{
    $clean = [];
    foreach (['carrier', 'van'] as $m) {
        $clean[$m] = [
            'under'  => max(0, round((float) ($rules[$m]['under'] ?? 0), 2)),
            'charge' => max(0, round((float) ($rules[$m]['charge'] ?? 0), 2)),
        ];
    }
    return app_setting_set('delivery_charge_rules', json_encode($clean));
}

/** An account's delivery terms: ['method' => 'carrier'|'van'|'collect', 'no_charge' => bool]. */
function dc_account_terms(PDO $pdo, int $accountId): array
{
    $out = ['method' => dc_default_method(), 'no_charge' => false];
    if ($accountId <= 0 || !dc_ready($pdo)) return $out;
    try {
        $s = $pdo->prepare('SELECT delivery_method, no_delivery_charge FROM factory_ar_account_delivery WHERE account_client_id = ? LIMIT 1');
        $s->execute([$accountId]);
        if ($r = $s->fetch(PDO::FETCH_ASSOC)) {
            if (isset(dc_methods()[(string) $r['delivery_method']])) $out['method'] = (string) $r['delivery_method'];
            $out['no_charge'] = (int) $r['no_delivery_charge'] === 1;
        }
    } catch (Throwable $e) { /* defaults */ }
    return $out;
}

function dc_save_account_terms(PDO $pdo, int $accountId, string $method, bool $noCharge): void
{
    if (!isset(dc_methods()[$method])) $method = dc_default_method();
    $pdo->prepare(
        'INSERT INTO factory_ar_account_delivery (account_client_id, delivery_method, no_delivery_charge)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE delivery_method = VALUES(delivery_method), no_delivery_charge = VALUES(no_delivery_charge)'
    )->execute([$accountId, $method, $noCharge ? 1 : 0]);
}

/** The rule's charge for a delivery worth $net (net) by $method. */
function dc_charge_for(string $method, float $net, bool $noCharge = false): float
{
    if ($noCharge) return 0.0;
    $r = dc_rules()[$method] ?? null;
    if (!$r || $r['charge'] <= 0) return 0.0;
    return round($net, 2) < $r['under'] ? $r['charge'] : 0.0;
}

/** One line for people, e.g. "Van: £10.00 + VAT on deliveries under £100.00". */
function dc_rule_text(string $method, bool $noCharge = false): string
{
    $label = dc_methods()[$method] ?? 'Delivery';
    if ($noCharge)            return $label . ': no delivery charge on this account';
    if ($method === 'collect') return 'Collected: no delivery charge';
    $r = dc_rules()[$method] ?? null;
    if (!$r || $r['charge'] <= 0) return $label . ': no delivery charge';
    return $label . ': £' . number_format($r['charge'], 2) . ' + VAT on deliveries under £'
        . number_format($r['under'], 2) . ' (orders going out together on the same day are added up)';
}

/**
 * Re-work the charge for ONE delivery (account + day + method) and park it on one
 * of its notes. Called whenever a note joins, leaves or changes group.
 *
 * $orderNet maps quote id => net value; orders missing from it are looked up.
 * Returns ['net', 'charge', 'warning'] — warning is non-empty when an invoice in
 * the group already billed a different charge (an invoice is never changed
 * silently; the person decides whether to add or credit the difference).
 */
function dc_recalc_delivery(PDO $pdo, int $factory, int $accountId, string $date, string $method, array $orderNet = []): array
{
    $res = ['net' => 0.0, 'charge' => 0.0, 'warning' => ''];
    if (!dc_ready($pdo) || $accountId <= 0 || $date === '' || $method === '') return $res;

    $g = $pdo->prepare(
        "SELECT id, source_quote_id, carriage_net, carriage_override
           FROM factory_ar_delivery_notes
          WHERE factory_client_id = ? AND account_client_id = ? AND delivery_date = ?
            AND delivery_method = ? AND status <> 'cancelled'
       ORDER BY id"
    );
    $g->execute([$factory, $accountId, $date, $method]);
    $notes = $g->fetchAll(PDO::FETCH_ASSOC);
    if (!$notes) return $res;

    // Net value of every order in the delivery — the same figure the invoice bills.
    $missing = array_filter(array_map(static fn ($n) => (int) $n['source_quote_id'], $notes),
        static fn ($q) => $q > 0 && !array_key_exists($q, $orderNet));
    if ($missing && function_exists('ar_placed_orders')) {
        foreach (ar_placed_orders($pdo, $factory, $accountId) as $o) {
            $orderNet[(int) $o['id']] = (float) $o['wholesale_total'];
        }
    }
    $net = 0.0;
    foreach ($notes as $n) $net += (float) ($orderNet[(int) $n['source_quote_id']] ?? 0);
    $net = round($net, 2);

    // A waive / custom amount set from the tray applies to the whole delivery.
    $override = null;
    foreach ($notes as $n) {
        if ($n['carriage_override'] !== null) { $override = round((float) $n['carriage_override'], 2); break; }
    }
    $terms  = dc_account_terms($pdo, $accountId);
    $charge = $override ?? dc_charge_for($method, $net, $terms['no_charge']);

    // Which notes are already invoiced? Their carriage is settled on the invoice.
    $invoiced = [];
    if (function_exists('ar_order_invoice_number')) {
        foreach ($notes as $n) {
            $q = (int) $n['source_quote_id'];
            if ($q > 0 && ar_order_invoice_number($pdo, $q) !== '') $invoiced[(int) $n['id']] = true;
        }
    }
    $billed = 0.0;
    foreach ($notes as $n) if (isset($invoiced[(int) $n['id']])) $billed += (float) $n['carriage_net'];
    $billed = round($billed, 2);

    $set = $pdo->prepare('UPDATE factory_ar_delivery_notes SET carriage_net = ? WHERE id = ?');
    if ($billed > 0.004) {
        // Already on an invoice: clear it from the rest so it can't be billed twice.
        foreach ($notes as $n) if (!isset($invoiced[(int) $n['id']])) $set->execute([0, (int) $n['id']]);
        if (abs($billed - $charge) > 0.004) {
            $res['warning'] = 'delivery charge for this delivery is now £' . number_format($charge, 2)
                . ' but £' . number_format($billed, 2) . ' was already invoiced — adjust it on the invoice or with a credit note';
        }
    } else {
        // Park the whole charge on the first note not yet invoiced.
        $bearer = 0;
        foreach ($notes as $n) if (!isset($invoiced[(int) $n['id']])) { $bearer = (int) $n['id']; break; }
        foreach ($notes as $n) {
            if (isset($invoiced[(int) $n['id']])) continue;
            $set->execute([(int) $n['id'] === $bearer ? $charge : 0, (int) $n['id']]);
        }
        if ($bearer === 0 && $charge > 0.004) {
            $res['warning'] = 'every order in this delivery is already invoiced without the £'
                . number_format($charge, 2) . ' delivery charge — add it to one of the invoices';
        }
    }

    $res['net'] = $net; $res['charge'] = $charge;
    return $res;
}

/**
 * The carriage an order's invoice should carry: ['amount', 'description'] from its
 * delivery note, or amount 0 when there's nothing to bill.
 */
function dc_order_carriage(PDO $pdo, int $factory, int $quoteId): array
{
    $out = ['amount' => 0.0, 'description' => ''];
    if (!dc_ready($pdo)) return $out;
    try {
        $s = $pdo->prepare(
            "SELECT carriage_net, delivery_method, delivery_date
               FROM factory_ar_delivery_notes
              WHERE factory_client_id = ? AND source_quote_id = ? AND status <> 'cancelled'
           ORDER BY id LIMIT 1"
        );
        $s->execute([$factory, $quoteId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $r = null; }
    if (!$r || (float) $r['carriage_net'] <= 0.004) return $out;

    $label = dc_methods()[(string) $r['delivery_method']] ?? '';
    $out['amount'] = round((float) $r['carriage_net'], 2);
    $out['description'] = 'Delivery charge' . ($label !== '' ? ' — ' . $label : '')
        . ($r['delivery_date'] ? ', ' . date('d/m/Y', strtotime((string) $r['delivery_date'])) : '');
    return $out;
}
