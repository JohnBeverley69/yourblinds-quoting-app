<?php
declare(strict_types=1);

/**
 * InstaPrice → full quote.
 *
 * Takes the current InstaPrice spec, creates a draft quote with this one
 * line (placeholder customer), and lands the user in the quote builder to
 * fill in the customer details and add more blinds. Reuses the same
 * pricing + snapshot logic as quote-builder/add_item.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../quote-builder/_helpers.php';
require __DIR__ . '/../_partials/pricing_engine.php';
require __DIR__ . '/../_partials/price_table_parser.php';
require __DIR__ . '/../_partials/units.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$isAdmin  = ($user['role'] ?? '') === 'admin';
$perms    = current_user_permissions();

if (!($isAdmin || !empty($perms['can_create_quotes']))) {
    $_SESSION['flash_error'] = 'You don\'t have permission to create quotes.';
    header('Location: /instaprice/index.php');
    exit;
}

// --- Parse the spec --------------------------------------------------------
// The unit the operator was working in (from the InstaPrice switcher).
// Bare numbers are read in it; it's also stamped on the new quote so the
// builder opens in the same unit.
$quoteUnit = unit_is_valid($_POST['unit'] ?? null)
    ? (string) $_POST['unit']
    : client_default_unit(db(), $clientId);
$widthRaw = (string) ($_POST['width'] ?? '');
$widthMm  = ptp_parse_dimension($widthRaw, $quoteUnit);
$dropRaw = (string) ($_POST['drop'] ?? '');
$dropMm  = ptp_parse_dimension($dropRaw, $quoteUnit);
// Blank width → 0 (per-slat products) / blank drop → 0 (width-only); the
// engine decides which are required.
if ($widthMm === null && trim($widthRaw) === '') {
    $widthMm = 0;
}
if ($dropMm === null && trim($dropRaw) === '') {
    $dropMm = 0;
}
if ($widthMm === null || $dropMm === null) {
    $_SESSION['flash_error'] = 'Could not read the size — go back and try again.';
    header('Location: /instaprice/index.php');
    exit;
}

$extras    = [];
$rawExtras = json_decode((string) ($_POST['extras_json'] ?? '[]'), true);
if (is_array($rawExtras)) {
    foreach ($rawExtras as $e) {
        if (!is_array($e)) continue;
        $eid = (int) ($e['extra_id'] ?? 0);
        if ($eid <= 0) continue;

        $uv      = $e['user_value'] ?? null;
        $uvFloat = ($uv !== null && $uv !== '' && is_numeric($uv) && (float) $uv > 0)
            ? (float) $uv : null;

        if (array_key_exists('choice_id', $e)) {
            // Choice-backed option — only counts when a choice was picked.
            $cid = (int) ($e['choice_id'] ?? 0);
            if ($cid > 0) {
                $row = ['extra_id' => $eid, 'choice_id' => $cid];
                if ($uvFloat !== null) $row['user_value'] = $uvFloat;
                $extras[] = $row;
            }
        } else {
            // Number-only option (no choices) — carry the typed measurement.
            $row = ['extra_id' => $eid, 'choice_id' => 0];
            if ($uvFloat !== null) $row['user_value'] = $uvFloat;
            $extras[] = $row;
        }
    }
}

$input = [
    'product_id' => (int) ($_POST['product_id'] ?? 0),
    'system_id'  => (int) ($_POST['system_id']  ?? 0),
    'option_id'  => (int) ($_POST['option_id']  ?? 0),
    'width_mm'   => $widthMm,
    'drop_mm'    => $dropMm,
    'quantity'   => max(1, (int) ($_POST['quantity'] ?? 1)),
    'extras'     => $extras,
    'round_up'   => true,
];

$pdo = db();
$pdo->beginTransaction();
try {
    $priced = pe_calculate_item($pdo, $clientId, $input);
    if (isset($priced['error'])) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Could not price that: ' . $priced['error'];
        header('Location: /instaprice/index.php');
        exit;
    }

    // VAT snapshot (same as new.php).
    $vatSt = $pdo->prepare('SELECT vat_percent FROM client_settings WHERE client_id = ? LIMIT 1');
    $vatSt->execute([$clientId]);
    $vatPct = (float) ($vatSt->fetchColumn() ?? 20.0);

    // Create the draft quote with a placeholder customer name (the user
    // fills the real details on the editor we land them on). Retry on the
    // tiny quote-number race window, like new.php.
    $attempt = 0;
    $quoteNumber = '';
    while (true) {
        $attempt++;
        try {
            $quoteNumber = qb_generate_quote_number($clientId);
            $token       = qb_generate_public_token();
            $st = $pdo->prepare(
                'INSERT INTO quotes
                   (client_id, quote_number, customer_id, end_customer_name,
                    has_whatsapp, status, vat_percent, public_token, created_by_user_id)
                 VALUES (?, ?, NULL, ?, 0, "draft", ?, ?, ?)'
            );
            $st->execute([
                $clientId, $quoteNumber, 'Quick price (add customer)',
                $vatPct, $token, (int) $user['user_id'],
            ]);
            break;
        } catch (PDOException $e) {
            if ($attempt >= 3 || !str_contains($e->getMessage(), 'uniq_quote_number_per_client')) {
                throw $e;
            }
        }
    }
    $quoteId = (int) $pdo->lastInsertId();

    // Stamp the chosen unit on the quote so the builder opens in the same
    // unit. Best-effort — older schema (no column) just falls back to the
    // tenant default. Stays NULL/skipped if it equals the tenant default
    // is unnecessary; storing it explicitly is harmless.
    try {
        $pdo->prepare('UPDATE quotes SET measurement_unit = ? WHERE id = ?')
            ->execute([$quoteUnit, $quoteId]);
    } catch (Throwable $e) { /* column absent — ignore */ }

    // Insert the line — mirrors quote-builder/add_item.php exactly.
    $ins = $pdo->prepare(
        'INSERT INTO quote_items
          (quote_id, line_no,
           product_id, product_name_snapshot,
           system_id, system_name_snapshot,
           option_id,
           fabric_band_snapshot, fabric_supplier_snapshot, fabric_name_snapshot,
           fabric_colour_snapshot, fabric_code_snapshot,
           room_name,
           width_mm, drop_mm, width_matrix_mm, drop_matrix_mm,
           quantity,
           price_table_id, price_table_row_id,
           base_price, cost_price_snapshot, extras_cost_snapshot,
           extras_total, subtotal_per_blind,
           markup_percent, discount_percent,
           sell_price, line_total,
           notes)
         VALUES
          (?, 1,
           ?, ?,
           ?, ?,
           ?,
           ?, ?, ?,
           ?, ?,
           ?,
           ?, ?, ?, ?,
           ?,
           ?, ?,
           ?, ?, ?,
           ?, ?,
           ?, ?,
           ?, ?,
           ?)'
    );
    $ins->execute([
        $quoteId,
        $priced['product_id'], $priced['product_name'],
        $priced['system_id'],  $priced['system_name'],
        $priced['option_id'],
        $priced['fabric_band'], $priced['fabric_supplier'], $priced['fabric_name'],
        $priced['fabric_colour'], $priced['fabric_code'],
        null,
        $priced['width_mm'], $priced['drop_mm'],
        $priced['matrix_width_mm'], $priced['matrix_drop_mm'],
        $priced['quantity'],
        $priced['price_table_id'], $priced['price_table_row_id'],
        $priced['base_price'], $priced['cost_price_per_blind'] ?? 0, $priced['extras_cost_total'] ?? 0,
        $priced['extras_total'], $priced['subtotal_per_blind'],
        $priced['markup_percent'], $priced['discount_percent'],
        $priced['sell_price'], $priced['line_total'],
        null,
    ]);
    $newItemId = (int) $pdo->lastInsertId();

    if (!empty($priced['extras_applied'])) {
        try {
            $insE = $pdo->prepare(
                'INSERT INTO quote_item_extras
                   (quote_item_id, product_extra_id, extra_name_snapshot,
                    product_extra_choice_id, choice_label_snapshot,
                    mode, amount_applied, cost_snapshot, user_value)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($priced['extras_applied'] as $ex) {
                $insE->execute([
                    $newItemId, $ex['extra_id'], $ex['extra_name'],
                    $ex['choice_id'], $ex['choice_label'],
                    $ex['mode'], $ex['amount_applied'],
                    $ex['cost_snapshot'] ?? 0, $ex['user_value'] ?? null,
                ]);
            }
        } catch (Throwable $e) {
            $insE = $pdo->prepare(
                'INSERT INTO quote_item_extras
                   (quote_item_id, product_extra_id, extra_name_snapshot,
                    product_extra_choice_id, choice_label_snapshot,
                    mode, amount_applied, cost_snapshot)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($priced['extras_applied'] as $ex) {
                $insE->execute([
                    $newItemId, $ex['extra_id'], $ex['extra_name'],
                    $ex['choice_id'], $ex['choice_label'],
                    $ex['mode'], $ex['amount_applied'],
                    $ex['cost_snapshot'] ?? 0,
                ]);
            }
        }
    }

    qb_recompute_totals($quoteId);
    $pdo->commit();

    $_SESSION['flash_success'] = 'Quote ' . $quoteNumber
        . ' started from InstaPrice — add the customer details (and any more blinds) below.';
    header('Location: /quote-builder/edit.php?id=' . $quoteId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('instaprice to-quote failed (client ' . $clientId . '): ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not start the quote — please try again.';
    header('Location: /instaprice/index.php');
    exit;
}
