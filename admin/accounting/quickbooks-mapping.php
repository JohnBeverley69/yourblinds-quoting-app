<?php
declare(strict_types=1);

/**
 * QuickBooks field mapping (Phase 2).
 *
 * Pulls the connected company's items, tax codes and bank/income accounts and
 * lets the tenant choose:
 *   - how a PAID sale is recorded (Sales Receipt vs Invoice+Payment)
 *   - the sales item lines ride on (QBO requires an Item on each line)
 *   - the default VAT / tax code
 *   - the bank/deposit account a paid sale lands in
 * Stored as JSON in accounting_connections.mapping_json for the Phase 3 push.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';
require __DIR__ . '/../../_partials/accounting.php';
require __DIR__ . '/../../_partials/quickbooks.php';   // qbo_query / qbo_list_* helpers

requireAdmin();
$user     = current_user();
$clientId = (int) $user['client_id'];

$conn = ac_get_connection($clientId, 'quickbooks');
if (!ac_is_connected($conn)) {
    $_SESSION['flash_error'] = 'Connect QuickBooks first, then set up the mapping.';
    header('Location: /admin/settings.php#accounting');
    exit;
}

$mapping = [];
if (!empty($conn['mapping_json'])) {
    $decoded = json_decode((string) $conn['mapping_json'], true);
    if (is_array($decoded)) $mapping = $decoded;
}

// ---- Save -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $recordAs = (string) ($_POST['record_as'] ?? 'sales_receipt');
    if (!in_array($recordAs, ['sales_receipt', 'invoice_payment'], true)) $recordAs = 'sales_receipt';

    // Keep the human-readable label alongside each id so Settings can show the
    // mapping without another API round-trip.
    $label = static function (string $json, string $id): string {
        foreach ((array) json_decode($json ?: '[]', true) as $o) {
            if ((string) ($o['id'] ?? '') === $id) return (string) ($o['name'] ?? '');
        }
        return '';
    };
    $itemsJson    = (string) ($_POST['items_json']    ?? '[]');
    $taxJson      = (string) ($_POST['tax_json']      ?? '[]');
    $depositJson  = (string) ($_POST['deposit_json']  ?? '[]');

    $salesItemId  = (string) ($_POST['sales_item_id']  ?? '');
    $taxCodeId    = (string) ($_POST['tax_code_id']    ?? '');
    $depositId    = (string) ($_POST['deposit_id']     ?? '');

    $newMapping = [
        'record_as'            => $recordAs,
        'sales_item_id'        => $salesItemId,
        'sales_item_name'      => $label($itemsJson, $salesItemId),
        'tax_code_id'          => $taxCodeId,
        'tax_code_name'        => $label($taxJson, $taxCodeId),
        'deposit_account_id'   => $depositId,
        'deposit_account_name' => $label($depositJson, $depositId),
        'updated_at'           => date('c'),
    ];
    ac_save_connection($clientId, 'quickbooks', ['mapping_json' => json_encode($newMapping)]);
    $_SESSION['flash_success'] = 'QuickBooks mapping saved.';
    header('Location: /admin/accounting/quickbooks-mapping.php');
    exit;
}

// ---- Load lists from QuickBooks -------------------------------------------
$loadError = '';
$items = $taxCodes = []; $accounts = ['income' => [], 'deposit' => []];
try {
    $items    = qbo_list_items($clientId);
    $taxCodes = qbo_list_tax_codes($clientId);
    $accounts = qbo_list_accounts($clientId);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$companyName = (string) ($conn['company_name'] ?? 'your QuickBooks company');
$activeNav = 'settings';

/** Render a <select> from a [{id,name}] list, pre-selecting $current. */
function map_select(string $name, array $opts, string $current, string $placeholder): string
{
    $h = '<select name="' . e($name) . '" id="' . e($name) . '">';
    $h .= '<option value="">' . e($placeholder) . '</option>';
    foreach ($opts as $o) {
        $id = (string) ($o['id'] ?? '');
        $sel = $id === $current ? ' selected' : '';
        $h .= '<option value="' . e($id) . '"' . $sel . '>' . e((string) ($o['name'] ?? '')) . '</option>';
    }
    return $h . '</select>';
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QuickBooks mapping &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">QuickBooks mapping</h1>
                <p class="page-subtitle" style="color:var(--text-secondary)">
                    Tell YourBlinds where paid sales should post in <strong><?= e($companyName) ?></strong>.
                </p>
            </div>
            <div>
                <a class="btn btn-secondary" href="/admin/settings.php#accounting">&larr; Back to Accounting</a>
            </div>
        </div>

        <?php if ($flashMsg): ?><div class="flash flash-success" style="margin-bottom:1rem"><?= e($flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr): ?><div class="flash flash-error"   style="margin-bottom:1rem"><?= e($flashErr) ?></div><?php endif; ?>

        <section class="section" style="max-width:44rem">
            <?php if ($loadError !== ''): ?>
                <div class="flash flash-error" style="margin-bottom:1rem">
                    Couldn't load lists from QuickBooks: <?= e($loadError) ?>
                    <br>Try again, or reconnect on the Accounting tab.
                </div>
            <?php endif; ?>

            <form method="post" action="/admin/accounting/quickbooks-mapping.php">
                <?= csrf_field() ?>
                <input type="hidden" name="items_json"   value='<?= e(json_encode($items)) ?>'>
                <input type="hidden" name="tax_json"     value='<?= e(json_encode($taxCodes)) ?>'>
                <input type="hidden" name="deposit_json" value='<?= e(json_encode($accounts["deposit"])) ?>'>

                <div class="form-row">
                    <label for="record_as">How to record a paid sale</label>
                    <select name="record_as" id="record_as">
                        <option value="sales_receipt"   <?= ($mapping['record_as'] ?? 'sales_receipt') === 'sales_receipt'   ? 'selected' : '' ?>>Sales Receipt (income + payment in one — no open debtor)</option>
                        <option value="invoice_payment" <?= ($mapping['record_as'] ?? '') === 'invoice_payment' ? 'selected' : '' ?>>Invoice + Payment (keeps a customer invoice history)</option>
                    </select>
                    <p class="ui-hint" style="color:var(--text-faint);font-size:.8125rem;margin:.35rem 0 0">
                        You only put paid sales on QuickBooks (cash accounting), so a Sales Receipt is usually the cleanest.
                    </p>
                </div>

                <div class="form-row">
                    <label for="sales_item_id">Sales item <span style="color:#b91c1c">*</span></label>
                    <?= map_select('sales_item_id', $items, (string) ($mapping['sales_item_id'] ?? ''), '— Select an item —') ?>
                    <p class="ui-hint" style="color:var(--text-faint);font-size:.8125rem;margin:.35rem 0 0">
                        QuickBooks needs an item on each line — a single "Blinds" sales item is fine; it points at your income account.
                    </p>
                </div>

                <div class="form-row">
                    <label for="tax_code_id">Default VAT / tax code</label>
                    <?= map_select('tax_code_id', $taxCodes, (string) ($mapping['tax_code_id'] ?? ''), '— Select a tax code —') ?>
                </div>

                <div class="form-row">
                    <label for="deposit_id">Bank account paid sales land in</label>
                    <?= map_select('deposit_id', $accounts['deposit'], (string) ($mapping['deposit_account_id'] ?? ''), '— Select an account —') ?>
                    <p class="ui-hint" style="color:var(--text-faint);font-size:.8125rem;margin:.35rem 0 0">
                        Where the money shows as received — usually your current account (or Undeposited Funds if payments arrive batched).
                    </p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save mapping</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
