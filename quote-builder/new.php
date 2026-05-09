<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/_helpers.php';

requireLogin();

$user     = current_user();
$clientId = (int) $user['client_id'];

$f = [
    'customer_id'           => 0,
    'end_customer_name'     => '',
    'end_customer_email'    => '',
    'end_customer_phone'    => '',
    'end_customer_address1' => '',
    'end_customer_address2' => '',
    'end_customer_town'     => '',
    'end_customer_county'   => '',
    'end_customer_postcode' => '',
    'notes'                 => '',
];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['customer_id']           = (int) ($_POST['customer_id'] ?? 0);
    $f['end_customer_name']     = trim((string) ($_POST['end_customer_name']     ?? ''));
    $f['end_customer_email']    = trim((string) ($_POST['end_customer_email']    ?? ''));
    $f['end_customer_phone']    = trim((string) ($_POST['end_customer_phone']    ?? ''));
    $f['end_customer_address1'] = trim((string) ($_POST['end_customer_address1'] ?? ''));
    $f['end_customer_address2'] = trim((string) ($_POST['end_customer_address2'] ?? ''));
    $f['end_customer_town']     = trim((string) ($_POST['end_customer_town']     ?? ''));
    $f['end_customer_county']   = trim((string) ($_POST['end_customer_county']   ?? ''));
    $f['end_customer_postcode'] = trim((string) ($_POST['end_customer_postcode'] ?? ''));
    $f['notes']                 = trim((string) ($_POST['notes']                 ?? ''));

    // If a customer is picked, copy their fields into any blank snapshot
    // fields the user left empty — keeps the link but lets edits flow.
    if ($f['customer_id'] > 0) {
        $cs = db()->prepare(
            'SELECT name, email, phone, address1, address2, town, county, postcode
               FROM customers WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $cs->execute([$f['customer_id'], $clientId]);
        $cust = $cs->fetch();
        if (!$cust) {
            $error = 'Selected customer not found.';
        } else {
            if ($f['end_customer_name']     === '') $f['end_customer_name']     = (string) $cust['name'];
            if ($f['end_customer_email']    === '') $f['end_customer_email']    = (string) ($cust['email']    ?? '');
            if ($f['end_customer_phone']    === '') $f['end_customer_phone']    = (string) ($cust['phone']    ?? '');
            if ($f['end_customer_address1'] === '') $f['end_customer_address1'] = (string) ($cust['address1'] ?? '');
            if ($f['end_customer_address2'] === '') $f['end_customer_address2'] = (string) ($cust['address2'] ?? '');
            if ($f['end_customer_town']     === '') $f['end_customer_town']     = (string) ($cust['town']     ?? '');
            if ($f['end_customer_county']   === '') $f['end_customer_county']   = (string) ($cust['county']   ?? '');
            if ($f['end_customer_postcode'] === '') $f['end_customer_postcode'] = (string) ($cust['postcode'] ?? '');
        }
    }

    if ($error === null && $f['end_customer_name'] === '') {
        $error = 'Customer name is required.';
    }
    if ($error === null && strlen($f['end_customer_name']) > 150) {
        $error = 'Customer name is too long (max 150 chars).';
    }

    if ($error === null) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Snapshot the tenant's VAT rate at the time the quote is created.
            $vatSt = $pdo->prepare(
                'SELECT vat_percent FROM client_settings WHERE client_id = ? LIMIT 1'
            );
            $vatSt->execute([$clientId]);
            $vatPct = (float) ($vatSt->fetchColumn() ?? 20.0);

            // Generate a quote number with a couple of retries against the
            // tiny race window between SELECT MAX and INSERT.
            $attempt = 0;
            while (true) {
                $attempt++;
                try {
                    $quoteNumber = qb_generate_quote_number($clientId);
                    $token       = qb_generate_public_token();
                    $st = $pdo->prepare(
                        'INSERT INTO quotes
                          (client_id, quote_number, customer_id,
                           end_customer_name, end_customer_email, end_customer_phone,
                           end_customer_address1, end_customer_address2,
                           end_customer_town, end_customer_county, end_customer_postcode,
                           status, vat_percent, notes,
                           public_token, created_by_user_id)
                         VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                           "draft", ?, ?, ?, ?)'
                    );
                    $st->execute([
                        $clientId,
                        $quoteNumber,
                        $f['customer_id'] > 0 ? $f['customer_id'] : null,
                        $f['end_customer_name'],
                        $f['end_customer_email']    !== '' ? $f['end_customer_email']    : null,
                        $f['end_customer_phone']    !== '' ? $f['end_customer_phone']    : null,
                        $f['end_customer_address1'] !== '' ? $f['end_customer_address1'] : null,
                        $f['end_customer_address2'] !== '' ? $f['end_customer_address2'] : null,
                        $f['end_customer_town']     !== '' ? $f['end_customer_town']     : null,
                        $f['end_customer_county']   !== '' ? $f['end_customer_county']   : null,
                        $f['end_customer_postcode'] !== '' ? $f['end_customer_postcode'] : null,
                        $vatPct,
                        $f['notes'] !== '' ? $f['notes'] : null,
                        $token,
                        (int) $user['user_id'],
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($attempt >= 3 || !str_contains($e->getMessage(), 'uniq_quote_number_per_client')) {
                        throw $e;
                    }
                    // race window — try a fresh number
                }
            }
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            $_SESSION['flash_success'] = 'Quote ' . $quoteNumber . ' created.';
            header('Location: /quote-builder/edit.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not create quote: ' . $e->getMessage();
        }
    }
}

$custStmt = db()->prepare(
    'SELECT id, name, town, postcode FROM customers
      WHERE client_id = ? ORDER BY name LIMIT 500'
);
$custStmt->execute([$clientId]);
$customers = $custStmt->fetchAll();

// Pre-build display labels keyed by id, so the typeahead can echo the
// chosen customer's text back into the search box on validation re-render.
$customerLabels = [];
foreach ($customers as $c) {
    $bits = array_filter([
        (string) $c['name'],
        (string) ($c['town'] ?? ''),
        (string) ($c['postcode'] ?? ''),
    ], static fn ($s) => $s !== '');
    $customerLabels[(int) $c['id']] = implode(' — ', $bits);
}
$selectedCustomerLabel = $customerLabels[(int) $f['customer_id']] ?? '';

$activeNav = 'new-quote';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New quote &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">New quote</h1>
                <p class="page-subtitle">
                    <a href="/quote-history/index.php">&larr; Quote history</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Pick an existing customer (their details auto-fill below), or type a new
                customer's name. You can flesh out the rest later from the editor.
            </p>
            <form method="post" action="/quote-builder/new.php" class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="customer_search">Existing customer</label>
                        <input type="text" id="customer_search" list="customer-options"
                               value="<?= e($selectedCustomerLabel) ?>"
                               placeholder="Type to search by name, town, or postcode...">
                        <input type="hidden" id="customer_id" name="customer_id"
                               value="<?= (int) $f['customer_id'] ?>">
                        <datalist id="customer-options">
                            <?php foreach ($customerLabels as $cid => $label): ?>
                                <option value="<?= e($label) ?>" data-id="<?= (int) $cid ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Type to filter — leave blank for a new customer.
                        </small>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_name">Customer name <span class="required">*</span></label>
                        <input id="end_customer_name" name="end_customer_name" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['end_customer_name']) ?>">
                    </div>
                </div>

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="end_customer_email">Email</label>
                        <input id="end_customer_email" name="end_customer_email" type="email" maxlength="150"
                               value="<?= e((string) $f['end_customer_email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_phone">Phone</label>
                        <input id="end_customer_phone" name="end_customer_phone" type="tel" maxlength="50"
                               value="<?= e((string) $f['end_customer_phone']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_address1">Address line 1</label>
                        <input id="end_customer_address1" name="end_customer_address1" type="text" maxlength="150"
                               value="<?= e((string) $f['end_customer_address1']) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="end_customer_address2">Address line 2</label>
                        <input id="end_customer_address2" name="end_customer_address2" type="text" maxlength="150"
                               value="<?= e((string) $f['end_customer_address2']) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="end_customer_town">Town</label>
                        <input id="end_customer_town" name="end_customer_town" type="text" maxlength="100"
                               value="<?= e((string) $f['end_customer_town']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_county">County</label>
                        <input id="end_customer_county" name="end_customer_county" type="text" maxlength="100"
                               value="<?= e((string) $f['end_customer_county']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_customer_postcode">Postcode</label>
                        <input id="end_customer_postcode" name="end_customer_postcode" type="text" maxlength="20"
                               value="<?= e((string) $f['end_customer_postcode']) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="notes">Quote notes</label>
                        <textarea id="notes" name="notes" rows="3"><?= e((string) $f['notes']) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create quote</button>
                    <a href="/quote-history/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
(function () {
    // Customer typeahead: when the user picks (or types an exact match for)
    // an option from the datalist, copy its data-id into the hidden
    // customer_id field. Anything else clears it (= new customer).
    var search   = document.getElementById('customer_search');
    var hidden   = document.getElementById('customer_id');
    var dataList = document.getElementById('customer-options');
    if (!search || !hidden || !dataList) return;

    function syncId() {
        var typed = search.value.trim();
        var matched = 0;
        for (var i = 0; i < dataList.options.length; i++) {
            if (dataList.options[i].value === typed) {
                matched = parseInt(dataList.options[i].dataset.id, 10) || 0;
                break;
            }
        }
        hidden.value = matched;
    }
    search.addEventListener('input',  syncId);
    search.addEventListener('change', syncId);
})();
</script>
</body>
</html>
