<?php
declare(strict_types=1);

/**
 * Factory order colour-coding — the palette that tints each order row on the
 * Incoming Orders queue by its state, Blind-Matrix style. Colours are per-factory
 * and editable in the factory Settings tab (stored in client_settings
 * .factory_order_colours as JSON: {stateKey: "#rrggbb"}).
 *
 * Rendering is theme-safe: the stored colour drives a solid 4px LEFT RAIL plus a
 * faint wash (rgba overlay) behind the whole row, so it reads clearly in both
 * light and dark mode without turning the row unreadable. A tenant who wants
 * stronger colours just picks stronger ones.
 */

require_once __DIR__ . '/../bootstrap.php';

/** The order states we colour, in lifecycle order, with sensible defaults. */
function foc_default_states(): array
{
    return [
        'new'          => ['label' => 'New order',         'color' => '#e11d48'],  // just in, not started
        'in_production'=> ['label' => 'In production',      'color' => '#d97706'],  // on the floor
        'ready'        => ['label' => 'Ready to dispatch',  'color' => '#16a34a'],  // all made
        'dispatched'   => ['label' => 'Dispatched',         'color' => '#2563eb'],  // gone out
        'invoiced'     => ['label' => 'Invoiced',           'color' => '#ca8a04'],  // billed
        'paid'         => ['label' => 'Complete / paid',    'color' => '#64748b'],  // closed
        'remake'       => ['label' => 'Remake',             'color' => '#7c3aed'],  // a remake until it goes out
    ];
}

/** Merge the factory's stored overrides over the defaults → [key => [label,color]]. */
function foc_colours(PDO $pdo, int $clientId): array
{
    $states = foc_default_states();
    $stored = [];
    try {
        $s = $pdo->prepare('SELECT factory_order_colours FROM client_settings WHERE client_id = ? LIMIT 1');
        $s->execute([$clientId]);
        $j = (string) ($s->fetchColumn() ?: '');
        if ($j !== '') { $d = json_decode($j, true); if (is_array($d)) $stored = $d; }
    } catch (Throwable $e) { /* column not migrated — use defaults */ }

    $out = [];
    foreach ($states as $k => $meta) {
        $c = (isset($stored[$k]) && foc_is_hex((string) $stored[$k])) ? strtolower((string) $stored[$k]) : $meta['color'];
        $out[$k] = ['label' => $meta['label'], 'color' => $c];
    }
    return $out;
}

/** Which state an order is in, from its fulfilment stage + billing status. */
function foc_state_for_order(?string $fulfilmentStage, ?string $quoteStatus): string
{
    // Billing overlays production: once invoiced/paid, that's what the boss tracks.
    $st = strtolower((string) $quoteStatus);
    if ($st === 'paid')     return 'paid';
    if ($st === 'invoiced') return 'invoiced';
    $fs = strtolower((string) $fulfilmentStage);
    if ($fs === 'dispatched')    return 'dispatched';
    if ($fs === 'ready')         return 'ready';
    if ($fs === 'in_production') return 'in_production';
    return 'new';   // confirmed / not yet started
}

/**
 * The Factory Pipeline's columns (factory/pipeline.php), each with its own
 * editable colour: the column's top bar, count and a faint wash. Stored in the
 * same per-factory JSON as the order colours, under "pl_" keys, so the two
 * sets save and reset independently.
 */
function fpc_default_columns(): array
{
    return [
        'pl_new'               => ['label' => 'New',                     'color' => '#b91c1c'],
        'pl_confirmed'         => ['label' => 'Received',                'color' => '#5b6b7f'],
        'pl_in_production'     => ['label' => 'In production',           'color' => '#b5730f'],
        'pl_awaiting_supplier' => ['label' => 'Awaiting supplier',       'color' => '#7c3aed'],
        'pl_ready'             => ['label' => 'Ready to go',             'color' => '#245ea3'],
        'pl_to_invoice'        => ['label' => 'Dispatched · to invoice', 'color' => '#0d7a67'],
        'pl_to_pay'            => ['label' => 'Awaiting payment',        'color' => '#1d4f8c'],
        'pl_paid'              => ['label' => 'Paid',                    'color' => '#0d7a67'],
        'pl_dispatched'        => ['label' => 'Dispatched (white-label factories)', 'color' => '#0d7a67'],
    ];
}

/** The factory's pipeline colours → [key => [label, color]] (stored over defaults). */
function fpc_colours(PDO $pdo, int $clientId): array
{
    $stored = foc_stored($pdo, $clientId);
    $out = [];
    foreach (fpc_default_columns() as $k => $meta) {
        $c = (isset($stored[$k]) && foc_is_hex((string) $stored[$k])) ? strtolower((string) $stored[$k]) : $meta['color'];
        $out[$k] = ['label' => $meta['label'], 'color' => $c];
    }
    return $out;
}

/** The raw stored colour map ({} when nothing is stored or not migrated). */
function foc_stored(PDO $pdo, int $clientId): array
{
    try {
        $s = $pdo->prepare('SELECT factory_order_colours FROM client_settings WHERE client_id = ? LIMIT 1');
        $s->execute([$clientId]);
        $j = (string) ($s->fetchColumn() ?: '');
        $d = $j !== '' ? json_decode($j, true) : null;
        return is_array($d) ? $d : [];
    } catch (Throwable $e) { return []; }
}

/** Persist a colour map (only valid #rrggbb for known states are kept). */
function foc_save(PDO $pdo, int $clientId, array $colours, ?array $set = null): void
{
    // $set = which keys this save owns (default: the order states). The other
    // set's stored colours are kept — the Pipeline colours share this JSON.
    $set   = $set ?? foc_default_states();
    $clean = array_diff_key(foc_stored($pdo, $clientId), $set);
    foreach ($set as $k => $_meta) {
        if (isset($colours[$k]) && foc_is_hex((string) $colours[$k])) {
            $clean[$k] = strtolower((string) $colours[$k]);
        }
    }
    // Ensure the row exists, then store (INSERT…ON DUPLICATE keeps other settings).
    $pdo->prepare(
        'INSERT INTO client_settings (client_id, factory_order_colours) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE factory_order_colours = VALUES(factory_order_colours)'
    )->execute([$clientId, json_encode($clean)]);
}

/** Reset to built-in defaults (clears the stored overrides). */
function foc_reset(PDO $pdo, int $clientId, ?array $set = null): void
{
    // Only this set's keys go back to default; the other set is kept.
    $set  = $set ?? foc_default_states();
    $keep = array_diff_key(foc_stored($pdo, $clientId), $set);
    try {
        $pdo->prepare('UPDATE client_settings SET factory_order_colours = ? WHERE client_id = ?')
            ->execute([$keep ? json_encode($keep) : null, $clientId]);
    } catch (Throwable $e) { /* nothing stored / column absent — already default */ }
}

function foc_is_hex(string $c): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

/** #rrggbb → "r, g, b" for building an rgba() wash. */
function foc_rgb(string $hex): string
{
    if (!foc_is_hex($hex)) $hex = '#64748b';
    return hexdec(substr($hex, 1, 2)) . ', ' . hexdec(substr($hex, 3, 2)) . ', ' . hexdec(substr($hex, 5, 2));
}

/**
 * Inline style for an order row given its state colour: solid left rail + faint
 * wash. Theme-safe (the wash is an rgba overlay, not a solid light fill).
 */
function foc_row_style(string $hex): string
{
    $rgb = foc_rgb($hex);
    return 'border-left:4px solid ' . $hex . ';background:rgba(' . $rgb . ',0.16);';
}
