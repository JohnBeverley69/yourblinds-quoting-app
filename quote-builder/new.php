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
    'has_whatsapp'          => 0,
    'notes'                 => '',
];
$error = null;

// Pre-fill from an appointment when arriving via the calendar's
// "Create quote" link (/quote-builder/new.php?appointment_id=N).
// Copies the linked customer + the appointment's installation
// address so the trade user doesn't retype anything they already
// have. Tenant-scoped — silently ignored if the appointment
// doesn't belong to the current client.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['appointment_id'])) {
    $apptId = (int) $_GET['appointment_id'];
    if ($apptId > 0) {
        $aSt = db()->prepare(
            'SELECT a.customer_id,
                    a.installation_address1, a.installation_address2,
                    a.installation_town, a.installation_county, a.installation_postcode,
                    c.name AS cust_name, c.email AS cust_email,
                    c.phone AS cust_phone, c.has_whatsapp AS cust_whatsapp,
                    c.address1 AS cust_addr1, c.address2 AS cust_addr2,
                    c.town AS cust_town, c.county AS cust_county,
                    c.postcode AS cust_postcode
               FROM appointments a
          LEFT JOIN customers c ON c.id = a.customer_id
              WHERE a.id = ? AND a.client_id = ?
              LIMIT 1'
        );
        $aSt->execute([$apptId, $clientId]);
        $appt = $aSt->fetch();
        if ($appt) {
            $f['customer_id']           = (int) ($appt['customer_id'] ?? 0);
            $f['end_customer_name']     = (string) ($appt['cust_name']    ?? '');
            $f['end_customer_email']    = (string) ($appt['cust_email']   ?? '');
            $f['end_customer_phone']    = (string) ($appt['cust_phone']   ?? '');
            $f['has_whatsapp']          = !empty($appt['cust_whatsapp']) ? 1 : 0;
            // Prefer the appointment's installation address (the
            // physical place where the blinds go) since that's what
            // the customer's quote is really about. Fall back to the
            // customer's address fields if the appointment didn't
            // capture one.
            $f['end_customer_address1'] = (string) ($appt['installation_address1'] ?: ($appt['cust_addr1']    ?? ''));
            $f['end_customer_address2'] = (string) ($appt['installation_address2'] ?: ($appt['cust_addr2']    ?? ''));
            $f['end_customer_town']     = (string) ($appt['installation_town']     ?: ($appt['cust_town']     ?? ''));
            $f['end_customer_county']   = (string) ($appt['installation_county']   ?: ($appt['cust_county']   ?? ''));
            $f['end_customer_postcode'] = (string) ($appt['installation_postcode'] ?: ($appt['cust_postcode'] ?? ''));
        }
    }
}

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
    $f['has_whatsapp']          = !empty($_POST['has_whatsapp']) ? 1 : 0;
    $f['notes']                 = trim((string) ($_POST['notes']                 ?? ''));

    // If a customer is picked, copy their fields into any blank snapshot
    // fields the user left empty — keeps the link but lets edits flow.
    if ($f['customer_id'] > 0) {
        $cs = db()->prepare(
            'SELECT name, email, phone, has_whatsapp,
                    address1, address2, town, county, postcode
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
            // has_whatsapp: a hidden POST field copies the picked customer's
            // value via JS, but if the user untoggled it before submit we
            // respect that — only fall through when no POST value.
            if (!isset($_POST['has_whatsapp']) && !empty($cust['has_whatsapp'])) {
                $f['has_whatsapp'] = 1;
            }
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
            // If no existing customer picked but a name was entered, auto-
            // create a customer record so the same person is findable on
            // the next quote. The quote is then linked to that new id.
            if ($f['customer_id'] === 0 && $f['end_customer_name'] !== '') {
                $emptyToNull = static fn (string $v) => $v === '' ? null : $v;
                $custIns = $pdo->prepare(
                    'INSERT INTO customers
                       (client_id, name, email, phone, has_whatsapp,
                        address1, address2, town, county, postcode)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $custIns->execute([
                    $clientId,
                    $f['end_customer_name'],
                    $emptyToNull($f['end_customer_email']),
                    $emptyToNull($f['end_customer_phone']),
                    (int) $f['has_whatsapp'],
                    $emptyToNull($f['end_customer_address1']),
                    $emptyToNull($f['end_customer_address2']),
                    $emptyToNull($f['end_customer_town']),
                    $emptyToNull($f['end_customer_county']),
                    $emptyToNull($f['end_customer_postcode']),
                ]);
                $f['customer_id'] = (int) $pdo->lastInsertId();
            }

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
                           end_customer_name, end_customer_email, end_customer_phone, has_whatsapp,
                           end_customer_address1, end_customer_address2,
                           end_customer_town, end_customer_county, end_customer_postcode,
                           status, vat_percent, notes,
                           public_token, created_by_user_id)
                         VALUES
                          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                           "draft", ?, ?, ?, ?)'
                    );
                    $st->execute([
                        $clientId,
                        $quoteNumber,
                        $f['customer_id'] > 0 ? $f['customer_id'] : null,
                        $f['end_customer_name'],
                        $f['end_customer_email']    !== '' ? $f['end_customer_email']    : null,
                        $f['end_customer_phone']    !== '' ? $f['end_customer_phone']    : null,
                        (int) $f['has_whatsapp'],
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
            // Land on the Add-line section so the user can start picking
            // products straight away — customer details are already filled
            // in from the form they just submitted.
            header('Location: /quote-builder/edit.php?id=' . $newId . '#add-line');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not create quote: ' . $e->getMessage();
        }
    }
}

// Postcode lookup feature flag — gates the "Find by postcode" widget.
$pcFlag = db()->prepare(
    'SELECT COALESCE(feature_postcode_lookup, 0) FROM client_settings WHERE client_id = ?'
);
$pcFlag->execute([$clientId]);
$postcodeLookupEnabled = (int) $pcFlag->fetchColumn() === 1;

$custStmt = db()->prepare(
    'SELECT id, name, email, phone, has_whatsapp,
            address1, address2, town, county, postcode
       FROM customers
      WHERE client_id = ? ORDER BY name LIMIT 500'
);
$custStmt->execute([$clientId]);
$customers = $custStmt->fetchAll();

// Build display labels + a per-customer field bundle for the typeahead.
// The bundle gets dropped onto each <option> as data-* attrs; the JS pulls
// it out on pick and populates the address fields client-side.
$customerLabels  = [];
$customerData    = [];
foreach ($customers as $c) {
    $cid  = (int) $c['id'];
    $bits = array_filter([
        (string) $c['name'],
        (string) ($c['town'] ?? ''),
        (string) ($c['postcode'] ?? ''),
    ], static fn ($s) => $s !== '');
    $customerLabels[$cid] = implode(' — ', $bits);
    $customerData[$cid]   = [
        'name'         => (string) $c['name'],
        'email'        => (string) ($c['email']    ?? ''),
        'phone'        => (string) ($c['phone']    ?? ''),
        'has_whatsapp' => !empty($c['has_whatsapp']) ? '1' : '',
        'address1'     => (string) ($c['address1'] ?? ''),
        'address2'     => (string) ($c['address2'] ?? ''),
        'town'         => (string) ($c['town']     ?? ''),
        'county'       => (string) ($c['county']   ?? ''),
        'postcode'     => (string) ($c['postcode'] ?? ''),
    ];
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
                            <?php foreach ($customerLabels as $cid => $label): $d = $customerData[$cid]; ?>
                                <option value="<?= e($label) ?>"
                                        data-id="<?= (int) $cid ?>"
                                        data-name="<?= e($d['name']) ?>"
                                        data-email="<?= e($d['email']) ?>"
                                        data-phone="<?= e($d['phone']) ?>"
                                        data-has_whatsapp="<?= e($d['has_whatsapp']) ?>"
                                        data-address1="<?= e($d['address1']) ?>"
                                        data-address2="<?= e($d['address2']) ?>"
                                        data-town="<?= e($d['town']) ?>"
                                        data-county="<?= e($d['county']) ?>"
                                        data-postcode="<?= e($d['postcode']) ?>"></option>
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
                        <label style="display:inline-flex;align-items:center;gap:0.4rem;margin-top:0.5rem;font-weight:400;font-size:0.875rem;color:#4b5563;cursor:pointer">
                            <input type="checkbox" id="end_customer_has_whatsapp" name="has_whatsapp" value="1"
                                   <?= !empty($f['has_whatsapp']) ? 'checked' : '' ?>>
                            Customer has WhatsApp on this number
                        </label>
                    </div>
                </div>

                <?php if ($postcodeLookupEnabled): ?>
                    <?php
                        $pcFieldMap = [
                            'line1'    => 'end_customer_address1',
                            'line2'    => 'end_customer_address2',
                            'town'     => 'end_customer_town',
                            'county'   => 'end_customer_county',
                            'postcode' => 'end_customer_postcode',
                        ];
                        require __DIR__ . '/../_partials/postcode_lookup.php';
                    ?>
                <?php endif; ?>

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
    // Customer typeahead. When the user picks (or types an exact match for)
    // an option from the datalist:
    //   - copy its data-id into the hidden customer_id field
    //   - populate the customer-detail fields (name, email, phone, address)
    //     from the data-* attrs, so the form prefills with that customer's
    //     last-known details.
    //
    // If they type something that doesn't match any option, customer_id
    // resets to 0 (= new customer) and the populated fields are left as-is.
    var search   = document.getElementById('customer_search');
    var hidden   = document.getElementById('customer_id');
    var dataList = document.getElementById('customer-options');
    if (!search || !hidden || !dataList) return;

    var FIELDS = ['name','email','phone','address1','address2','town','county','postcode'];

    function setField(suffix, value) {
        var el = document.getElementById(
            suffix === 'name' ? 'end_customer_name' : 'end_customer_' + suffix
        );
        if (el) el.value = value || '';
    }

    function syncFromMatch() {
        var typed = search.value.trim();
        var matched = null;
        for (var i = 0; i < dataList.options.length; i++) {
            if (dataList.options[i].value === typed) { matched = dataList.options[i]; break; }
        }
        if (matched) {
            hidden.value = matched.dataset.id || '0';
            FIELDS.forEach(function (f) { setField(f, matched.dataset[f]); });
            // has_whatsapp is a checkbox, not a text field
            var wa = document.getElementById('end_customer_has_whatsapp');
            if (wa) wa.checked = matched.dataset.has_whatsapp === '1';
        } else {
            hidden.value = '0';
        }
    }

    search.addEventListener('input',  syncFromMatch);
    search.addEventListener('change', syncFromMatch);
})();
</script>
</body>
</html>
