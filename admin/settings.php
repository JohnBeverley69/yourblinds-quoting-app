<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    if ($action === 'company') {
        $stmt = db()->prepare(
            'UPDATE clients
                SET company_name       = ?,
                    contact_name       = ?,
                    email              = ?,
                    phone              = ?,
                    vat_number         = ?,
                    address1           = ?,
                    address2           = ?,
                    town               = ?,
                    county             = ?,
                    postcode           = ?,
                    order_destination  = ?,
                    quote_destination  = ?,
                    office_order_email = ?,
                    office_quote_email = ?
              WHERE id = ?'
        );
        $stmt->execute([
            trim((string) ($_POST['company_name']       ?? '')) ?: $user['company_name'],
            trim((string) ($_POST['contact_name']       ?? '')) ?: null,
            trim((string) ($_POST['email']              ?? '')) ?: null,
            trim((string) ($_POST['phone']              ?? '')) ?: null,
            trim((string) ($_POST['vat_number']         ?? '')) ?: null,
            trim((string) ($_POST['address1']           ?? '')) ?: null,
            trim((string) ($_POST['address2']           ?? '')) ?: null,
            trim((string) ($_POST['town']               ?? '')) ?: null,
            trim((string) ($_POST['county']             ?? '')) ?: null,
            trim((string) ($_POST['postcode']           ?? '')) ?: null,
            in_array($_POST['order_destination'] ?? '', ['beverley_blinds','customer_office','both'], true)
                ? (string) $_POST['order_destination'] : 'beverley_blinds',
            in_array($_POST['quote_destination'] ?? '', ['customer_office','both','none'], true)
                ? (string) $_POST['quote_destination'] : 'customer_office',
            trim((string) ($_POST['office_order_email'] ?? '')) ?: null,
            trim((string) ($_POST['office_quote_email'] ?? '')) ?: null,
            $clientId,
        ]);
        // Refresh session-cached company name in case it changed
        $newName = trim((string) ($_POST['company_name'] ?? ''));
        if ($newName !== '') {
            $_SESSION['company_name'] = $newName;
        }
        $_SESSION['flash_success'] = 'Company details saved.';
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'logo') {
        // Validate the uploaded file: real image, sensible size, allowed type.
        if (!isset($_FILES['logo']) || ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Please choose a logo file.';
        } elseif (filesize($_FILES['logo']['tmp_name']) > 2 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Logo too large (2 MB max).';
        } else {
            $info = @getimagesize($_FILES['logo']['tmp_name']);
            $ext  = null;
            if ($info !== false) {
                switch ($info[2]) {
                    case IMAGETYPE_JPEG: $ext = 'jpg'; break;
                    case IMAGETYPE_PNG:  $ext = 'png'; break;
                    case IMAGETYPE_GIF:  $ext = 'gif'; break;
                }
            }
            if ($ext === null) {
                $_SESSION['flash_error'] = 'File must be a JPG, PNG, or GIF image.';
            } else {
                $dir = __DIR__ . '/../uploads/logos';
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    $_SESSION['flash_error'] = 'Could not create uploads/logos directory.';
                } else {
                    // Wipe any existing logo for this client (could be a different
                    // extension), so we never end up with two files for one client.
                    foreach (glob($dir . '/' . $clientId . '.*') ?: [] as $old) {
                        @unlink($old);
                    }
                    $dest = $dir . '/' . $clientId . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        // Web-relative path stored on the row — handy for HTML
                        // <img src="..."> rendering. The PDF renderer reads the
                        // file directly via filesystem path.
                        $webPath = '/uploads/logos/' . $clientId . '.' . $ext;
                        db()->prepare('UPDATE clients SET logo_path = ? WHERE id = ?')
                            ->execute([$webPath, $clientId]);
                        $_SESSION['flash_success'] = 'Logo uploaded.';
                    } else {
                        $_SESSION['flash_error'] = 'Could not save the uploaded file.';
                    }
                }
            }
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'remove_logo') {
        $dir = __DIR__ . '/../uploads/logos';
        foreach (glob($dir . '/' . $clientId . '.*') ?: [] as $old) {
            @unlink($old);
        }
        db()->prepare('UPDATE clients SET logo_path = NULL WHERE id = ?')->execute([$clientId]);
        $_SESSION['flash_success'] = 'Logo removed.';
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'quote') {
        $prefix    = strtoupper(trim((string) ($_POST['quote_prefix'] ?? '')));
        $markup    = (float) ($_POST['default_markup_percent'] ?? 0);
        $vat       = (float) ($_POST['vat_percent'] ?? 20);
        $emailFrom = trim((string) ($_POST['email_from_name'] ?? '')) ?: null;
        $replyTo   = trim((string) ($_POST['reply_to_email']  ?? '')) ?: null;
        $footer    = trim((string) ($_POST['quote_footer']    ?? '')) ?: null;

        $stmt = db()->prepare(
            'INSERT INTO client_settings
              (client_id, quote_prefix, default_markup_percent, vat_percent,
               email_from_name, reply_to_email, quote_footer)
              VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              quote_prefix           = VALUES(quote_prefix),
              default_markup_percent = VALUES(default_markup_percent),
              vat_percent            = VALUES(vat_percent),
              email_from_name        = VALUES(email_from_name),
              reply_to_email         = VALUES(reply_to_email),
              quote_footer           = VALUES(quote_footer)'
        );
        $stmt->execute([
            $clientId, $prefix ?: null, $markup, $vat,
            $emailFrom, $replyTo, $footer,
        ]);
        $_SESSION['flash_success'] = 'Quote settings saved.';
        header('Location: /admin/settings.php');
        exit;
    }
}

$clientStmt = db()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
$clientStmt->execute([$clientId]);
$client = $clientStmt->fetch() ?: [];

$settingsStmt = db()->prepare('SELECT * FROM client_settings WHERE client_id = ? LIMIT 1');
$settingsStmt->execute([$clientId]);
$settings = $settingsStmt->fetch() ?: [
    'quote_prefix'           => '',
    'default_markup_percent' => 0,
    'vat_percent'            => 20,
    'email_from_name'        => '',
    'reply_to_email'         => '',
    'quote_footer'           => '',
];
$activeNav = 'settings';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-subtitle">Company details and per-quote defaults.</p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Company details</h2>
            </div>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="company">

                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">Company name <span class="required">*</span></label>
                        <input id="company_name" name="company_name" type="text" required maxlength="150"
                               value="<?= e((string) ($client['company_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_name">Contact name</label>
                        <input id="contact_name" name="contact_name" type="text" maxlength="150"
                               value="<?= e((string) ($client['contact_name'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="150"
                               value="<?= e((string) ($client['email'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="tel" maxlength="50"
                               value="<?= e((string) ($client['phone'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="vat_number">VAT number</label>
                        <input id="vat_number" name="vat_number" type="text" maxlength="50"
                               value="<?= e((string) ($client['vat_number'] ?? '')) ?>"
                               placeholder="e.g. GB123456789">
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Leave blank if your business isn't VAT-registered.
                            When set, it appears below your contact details on every quote PDF.
                        </small>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="address1">Address line 1</label>
                        <input id="address1" name="address1" type="text" maxlength="150"
                               value="<?= e((string) ($client['address1'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="address2">Address line 2</label>
                        <input id="address2" name="address2" type="text" maxlength="150"
                               value="<?= e((string) ($client['address2'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="town">Town</label>
                        <input id="town" name="town" type="text" maxlength="100"
                               value="<?= e((string) ($client['town'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="county">County</label>
                        <input id="county" name="county" type="text" maxlength="100"
                               value="<?= e((string) ($client['county'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" type="text" maxlength="20"
                               value="<?= e((string) ($client['postcode'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="order_destination">Order destination</label>
                        <select id="order_destination" name="order_destination">
                            <?php foreach (['beverley_blinds' => 'Beverley Blinds', 'customer_office' => 'Customer office', 'both' => 'Both'] as $val => $lbl): ?>
                                <option value="<?= e($val) ?>" <?= ($client['order_destination'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quote_destination">Quote destination</label>
                        <select id="quote_destination" name="quote_destination">
                            <?php foreach (['customer_office' => 'Customer office', 'both' => 'Both', 'none' => 'None'] as $val => $lbl): ?>
                                <option value="<?= e($val) ?>" <?= ($client['quote_destination'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="office_order_email">Office order email</label>
                        <input id="office_order_email" name="office_order_email" type="email" maxlength="150"
                               value="<?= e((string) ($client['office_order_email'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="office_quote_email">Office quote email</label>
                        <input id="office_quote_email" name="office_quote_email" type="email" maxlength="150"
                               value="<?= e((string) ($client['office_quote_email'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save company details</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Company logo</h2>
            </div>
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Used in the header of customer-facing quote PDFs and the public
                accept page. JPG, PNG, or GIF, up to 2 MB.
            </p>

            <?php if (!empty($client['logo_path'])): ?>
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                    <img src="<?= e((string) $client['logo_path']) ?>" alt="Logo"
                         style="max-height:80px;max-width:200px;background:#fff;padding:0.25rem;border:1px solid #e5e7eb;border-radius:6px">
                    <form method="post" action="/admin/settings.php" style="margin:0"
                          onsubmit="return confirm('Remove the company logo?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="remove_logo">
                        <button type="submit" class="btn btn-secondary btn-sm">Remove logo</button>
                    </form>
                </div>
            <?php endif; ?>

            <form method="post" action="/admin/settings.php" class="form" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="logo">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="logo">
                            <?= !empty($client['logo_path']) ? 'Replace logo' : 'Upload logo' ?>
                        </label>
                        <input id="logo" name="logo" type="file"
                               accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif"
                               required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= !empty($client['logo_path']) ? 'Replace logo' : 'Upload logo' ?>
                    </button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Quote defaults</h2>
            </div>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="quote">

                <div class="form-row cols-3">
                    <div class="form-group">
                        <label for="quote_prefix">Quote prefix</label>
                        <input id="quote_prefix" name="quote_prefix" type="text" maxlength="20"
                               placeholder="e.g. BRI"
                               value="<?= e((string) ($settings['quote_prefix'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="default_markup_percent">Default markup %</label>
                        <input id="default_markup_percent" name="default_markup_percent" type="number"
                               step="0.01" min="0" max="999"
                               value="<?= e((string) ($settings['default_markup_percent'] ?? '0')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="vat_percent">VAT %</label>
                        <input id="vat_percent" name="vat_percent" type="number"
                               step="0.01" min="0" max="99"
                               value="<?= e((string) ($settings['vat_percent'] ?? '20')) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email_from_name">Email "from" name</label>
                        <input id="email_from_name" name="email_from_name" type="text" maxlength="150"
                               value="<?= e((string) ($settings['email_from_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="reply_to_email">Reply-to email</label>
                        <input id="reply_to_email" name="reply_to_email" type="email" maxlength="150"
                               value="<?= e((string) ($settings['reply_to_email'] ?? '')) ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="quote_footer">Quote footer (printed at the bottom of the PDF)</label>
                        <textarea id="quote_footer" name="quote_footer" rows="3"><?= e((string) ($settings['quote_footer'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save quote defaults</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
