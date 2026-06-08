<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/legal_text.php';

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
        // order_destination / quote_destination / office_*_email are
        // intentionally NOT in this UPDATE. The fields exist in the
        // schema and the UI was hidden because the routing isn't wired
        // up yet (the Beverley Blinds path is blocked on Blind Matrix
        // integration; the customer-office path is redundant given
        // tenants see their own orders in the dashboard). Leaving the
        // columns untouched preserves any existing values for the day
        // we wire it up.
        $stmt = db()->prepare(
            'UPDATE clients
                SET company_name = ?,
                    contact_name = ?,
                    email        = ?,
                    phone        = ?,
                    vat_number   = ?,
                    address1     = ?,
                    address2     = ?,
                    town         = ?,
                    county       = ?,
                    postcode     = ?
              WHERE id = ?'
        );
        $stmt->execute([
            trim((string) ($_POST['company_name'] ?? '')) ?: $user['company_name'],
            trim((string) ($_POST['contact_name'] ?? '')) ?: null,
            trim((string) ($_POST['email']        ?? '')) ?: null,
            trim((string) ($_POST['phone']        ?? '')) ?: null,
            trim((string) ($_POST['vat_number']   ?? '')) ?: null,
            trim((string) ($_POST['address1']     ?? '')) ?: null,
            trim((string) ($_POST['address2']     ?? '')) ?: null,
            trim((string) ($_POST['town']         ?? '')) ?: null,
            trim((string) ($_POST['county']       ?? '')) ?: null,
            trim((string) ($_POST['postcode']     ?? '')) ?: null,
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

    if ($action === 'margins') {
        // Tenant-wide default margins. Saves to client_settings columns
        // added by migrate_default_margins.php. Defensive against the
        // columns not existing — the UI shouldn't have rendered the
        // form in that case, but a manual POST could still arrive.
        $ptMarkup  = (float) ($_POST['default_price_table_markup_pct'] ?? 0);
        $optMarkup = (float) ($_POST['default_options_markup_pct']     ?? 0);
        // Bound 0–999 — sky-high markups are presumably typos rather
        // than legitimate. (We don't bound negatively because we let
        // them set 0 to "turn off".)
        $ptMarkup  = max(0, min(999, $ptMarkup));
        $optMarkup = max(0, min(999, $optMarkup));

        try {
            db()->prepare(
                'UPDATE client_settings
                    SET default_price_table_markup_pct = ?,
                        default_options_markup_pct     = ?
                  WHERE client_id = ?'
            )->execute([$ptMarkup, $optMarkup, $clientId]);
            $_SESSION['flash_success'] = 'Default margins saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save margins — '
                . 'has migrate_default_margins.php been run? '
                . $e->getMessage();
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'units') {
        // Tenant default measurement unit (migrate_measurement_unit.php).
        // Dimensions are always stored in mm — this only sets how sizes are
        // entered and shown. INSERT…ON DUPLICATE so it works even before a
        // client_settings row exists.
        $unit = (string) ($_POST['default_measurement_unit'] ?? 'mm');
        if (!in_array($unit, ['mm', 'cm', 'm', 'in'], true)) $unit = 'mm';
        try {
            db()->prepare(
                'INSERT INTO client_settings (client_id, default_measurement_unit)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE default_measurement_unit = VALUES(default_measurement_unit)'
            )->execute([$clientId, $unit]);
            $_SESSION['flash_success'] = 'Default measurement unit saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save measurement unit — '
                . 'has migrate_measurement_unit.php been run? ' . $e->getMessage();
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'quote') {
        // default_markup_percent has been retired — markup is now set
        // per-product (Product → Edit → Markup %). The column is still
        // in the DB for back-compat but no longer written from the UI.
        $prefix     = strtoupper(trim((string) ($_POST['quote_prefix'] ?? '')));
        $vat        = (float) ($_POST['vat_percent'] ?? 20);
        // VAT bounded like the deposit % — accept 0–100 only.
        if ($vat < 0)   $vat = 0;
        if ($vat > 100) $vat = 100;
        $depMode    = (string) ($_POST['default_deposit_mode'] ?? 'percent');
        if (!in_array($depMode, ['percent', 'flat'], true)) {
            $depMode = 'percent';
        }
        $depPct     = (float) ($_POST['default_deposit_percent'] ?? 50);
        if ($depPct < 0)   $depPct = 0;
        if ($depPct > 100) $depPct = 100;
        $depFlat    = (float) ($_POST['default_deposit_flat'] ?? 0);
        if ($depFlat < 0) $depFlat = 0;
        $emailFrom  = trim((string) ($_POST['email_from_name'] ?? '')) ?: null;
        $replyTo    = trim((string) ($_POST['reply_to_email']  ?? '')) ?: null;
        $footer     = trim((string) ($_POST['quote_footer']    ?? '')) ?: null;

        // Two-tier save: try the full multi-mode-deposit shape first.
        // If the deposit-mode columns aren't there yet (admin uploaded
        // the new settings.php before running migrate_deposit_flat_mode.php),
        // fall back to writing the percent-only column. Anything else
        // wrong bubbles up to the user as a flash error rather than
        // 500ing the page.
        try {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO client_settings
                      (client_id, quote_prefix, vat_percent,
                       default_deposit_mode, default_deposit_percent, default_deposit_flat,
                       email_from_name, reply_to_email, quote_footer)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      quote_prefix            = VALUES(quote_prefix),
                      vat_percent             = VALUES(vat_percent),
                      default_deposit_mode    = VALUES(default_deposit_mode),
                      default_deposit_percent = VALUES(default_deposit_percent),
                      default_deposit_flat    = VALUES(default_deposit_flat),
                      email_from_name         = VALUES(email_from_name),
                      reply_to_email          = VALUES(reply_to_email),
                      quote_footer            = VALUES(quote_footer)'
                );
                $stmt->execute([
                    $clientId, $prefix ?: null, $vat,
                    $depMode, $depPct, $depFlat,
                    $emailFrom, $replyTo, $footer,
                ]);
            } catch (PDOException $e) {
                // SQLSTATE 42S22 = column not found (MySQL 1054).
                // Older DB without the flat-mode columns: drop those
                // from the INSERT and keep percent only.
                if ($e->getCode() !== '42S22') throw $e;
                $stmt = db()->prepare(
                    'INSERT INTO client_settings
                      (client_id, quote_prefix, vat_percent,
                       default_deposit_percent,
                       email_from_name, reply_to_email, quote_footer)
                      VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      quote_prefix            = VALUES(quote_prefix),
                      vat_percent             = VALUES(vat_percent),
                      default_deposit_percent = VALUES(default_deposit_percent),
                      email_from_name         = VALUES(email_from_name),
                      reply_to_email          = VALUES(reply_to_email),
                      quote_footer            = VALUES(quote_footer)'
                );
                $stmt->execute([
                    $clientId, $prefix ?: null, $vat, $depPct,
                    $emailFrom, $replyTo, $footer,
                ]);
                $_SESSION['flash_error'] =
                    'Settings saved, but the deposit-mode columns are missing. '
                  . 'Run migrate_deposit_flat_mode.php to unlock the flat-amount option.';
                header('Location: /admin/settings.php');
                exit;
            }
            $_SESSION['flash_success'] = 'Quote settings saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save settings: ' . $e->getMessage();
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'legal') {
        // Terms & Conditions + Privacy Policy — free text, stored per client.
        // Saved separately from the quote-defaults block so it can never
        // disturb it. An empty box is a valid "configured but blank" value
        // (disables that document); a NULL column means never configured.
        $terms   = trim((string) ($_POST['terms_conditions'] ?? ''));
        $privacy = trim((string) ($_POST['privacy_policy']   ?? ''));
        try {
            $stmt = db()->prepare(
                'INSERT INTO client_settings (client_id, terms_conditions, privacy_policy)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   terms_conditions = VALUES(terms_conditions),
                   privacy_policy   = VALUES(privacy_policy)'
            );
            $stmt->execute([$clientId, $terms, $privacy]);
            $_SESSION['flash_success'] = 'Terms & Conditions and Privacy Policy saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage()
                . ' — have you run migrate_terms_conditions.php?';
        }
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
    'quote_prefix'             => '',
    'vat_percent'              => 20,
    'default_deposit_mode'     => 'percent',
    'default_deposit_percent'  => 50,
    'default_deposit_flat'     => 0,
    'email_from_name'          => '',
    'reply_to_email'           => '',
    'quote_footer'             => '',
    'default_measurement_unit' => 'mm',
];
// May be absent if the row exists but the migration hasn't run.
$currentUnit = $settings['default_measurement_unit'] ?? 'mm';
if (!in_array($currentUnit, ['mm', 'cm', 'm', 'in'], true)) $currentUnit = 'mm';

// Terms & Privacy textareas: show the stored value if configured, else
// pre-fill with the suggested template as a starting point. A NULL column
// (key absent — never saved, or migration not yet run) ⇒ show the template.
$tcStored  = $settings['terms_conditions'] ?? null;
$ppStored  = $settings['privacy_policy']   ?? null;
$tcDisplay = $tcStored === null ? legal_default_terms()   : (string) $tcStored;
$ppDisplay = $ppStored === null ? legal_default_privacy() : (string) $ppStored;

$activeNav = 'settings';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
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

                <!--
                    Order/quote destination + office email fields were
                    here. Hidden until routing is wired up — Beverley
                    Blinds path waits on the Blind Matrix integration,
                    customer-office path is redundant given tenants see
                    their own orders in the dashboard. DB columns are
                    preserved on save (see the company-update SQL).
                -->

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
                          data-confirm="Remove the company logo?">
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

        <!--
            Default margins. Two tenant-wide percentages that act as
            fallbacks so a tenant doesn't have to set the same margin
            on every product × system or every option choice. Each is
            overridable at the more-specific level (product edit for
            price tables; per-choice for options).

            Defensive against the schema migration not having run —
            we read the columns inside a try/catch and the section
            shows a banner pointing at the migration if absent.
        -->
        <?php
            $defaultMarginsAvailable = true;
            $defaultPtMarkup  = 0.0;
            $defaultOptMarkup = 0.0;
            try {
                $dmSt = db()->prepare(
                    'SELECT default_price_table_markup_pct,
                            default_options_markup_pct
                       FROM client_settings WHERE client_id = ? LIMIT 1'
                );
                $dmSt->execute([$clientId]);
                $dmRow = $dmSt->fetch();
                if ($dmRow) {
                    $defaultPtMarkup  = (float) ($dmRow['default_price_table_markup_pct'] ?? 0);
                    $defaultOptMarkup = (float) ($dmRow['default_options_markup_pct']     ?? 0);
                }
            } catch (Throwable $e) {
                $defaultMarginsAvailable = false;
            }
        ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Default margins</h2>
            </div>
            <?php if (!$defaultMarginsAvailable): ?>
                <div class="alert alert-error" role="alert">
                    The default-margins columns aren't on this database yet —
                    run <a href="/migrate_default_margins.php"><code>/migrate_default_margins.php</code></a>
                    (super-admin) to enable this section.
                </div>
            <?php else: ?>
                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem;line-height:1.55">
                    Set your usual margin once and the engine applies it
                    everywhere &mdash; no need to set markup on every product
                    or option choice. You can still override at the
                    product / option level when needed.
                </p>
                <form method="post" action="/admin/settings.php" class="form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="margins">

                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label for="default_price_table_markup_pct">
                                Default price-table markup %
                            </label>
                            <input id="default_price_table_markup_pct"
                                   name="default_price_table_markup_pct"
                                   type="number" step="0.01" min="0" max="999"
                                   value="<?= e(number_format($defaultPtMarkup, 2, '.', '')) ?>">
                            <small style="color:#6b7280;font-size:0.75rem;line-height:1.45;display:block;margin-top:0.25rem">
                                Applied to every (product, system) that
                                doesn't have an explicit markup set on the
                                product edit page.
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="default_options_markup_pct">
                                Default options &amp; extras markup %
                            </label>
                            <input id="default_options_markup_pct"
                                   name="default_options_markup_pct"
                                   type="number" step="0.01" min="0" max="999"
                                   value="<?= e(number_format($defaultOptMarkup, 2, '.', '')) ?>">
                            <small style="color:#6b7280;font-size:0.75rem;line-height:1.45;display:block;margin-top:0.25rem">
                                Uniform uplift on every option choice's
                                price &mdash; fixed-£, per-metre, and
                                width-table modes all included. Only
                                applies to new choices you add after
                                this feature went live; existing choices
                                stay at their entered prices.
                            </small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save margins</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Measurements</h2>
            </div>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem;line-height:1.55">
                The unit your team enters and sees blind sizes in. Sizes are
                always stored the same way under the hood, so you can change
                this any time. On a quote you can still override the unit for
                one job, and you can always type a unit directly
                (e.g. <code>60in</code>, <code>1.5m</code>) for a one-off.
            </p>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="units">
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="default_measurement_unit">Default measurement unit</label>
                        <select id="default_measurement_unit" name="default_measurement_unit">
                            <?php foreach (['mm' => 'Millimetres (mm)', 'cm' => 'Centimetres (cm)',
                                            'm' => 'Metres (m)', 'in' => 'Inches (in)'] as $uVal => $uLabel): ?>
                                <option value="<?= e($uVal) ?>" <?= $currentUnit === $uVal ? 'selected' : '' ?>>
                                    <?= e($uLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save unit</button>
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

                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="quote_prefix">Quote prefix</label>
                        <input id="quote_prefix" name="quote_prefix" type="text" maxlength="20"
                               placeholder="e.g. BRI"
                               value="<?= e((string) ($settings['quote_prefix'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="vat_percent">VAT %</label>
                        <input id="vat_percent" name="vat_percent" type="number"
                               step="0.01" min="0" max="99"
                               value="<?= e((string) ($settings['vat_percent'] ?? '20')) ?>">
                    </div>
                </div>

                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;
                                 padding:0.875rem 1rem;margin:0 0 1rem">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;
                                   font-weight:600;color:#1f3b5b;
                                   text-transform:uppercase;letter-spacing:0.05em">
                        Default deposit
                    </legend>
                    <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                        Seeds the deposit figure on every quote the moment it
                        moves into Accepted. Overrideable per quote. Pick
                        whichever mode matches how you actually take deposits.
                    </p>
                    <?php $depMode = (string) ($settings['default_deposit_mode'] ?? 'percent'); ?>
                    <div style="display:flex;flex-wrap:wrap;gap:1rem 1.25rem;align-items:center">
                        <label style="display:inline-flex;align-items:center;gap:0.4rem;
                                      font-weight:500;font-size:0.9375rem;cursor:pointer">
                            <input type="radio" name="default_deposit_mode"
                                   value="percent"
                                   <?= $depMode === 'percent' ? 'checked' : '' ?>>
                            Percentage of total
                            <input name="default_deposit_percent" type="number"
                                   step="0.01" min="0" max="100"
                                   value="<?= e((string) ($settings['default_deposit_percent'] ?? '50')) ?>"
                                   style="width:6rem;padding:0.375rem 0.5rem;
                                          border:1px solid #d1d5db;border-radius:6px;
                                          font:inherit;margin-left:0.5rem">
                            <span style="color:#6b7280">%</span>
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:0.4rem;
                                      font-weight:500;font-size:0.9375rem;cursor:pointer">
                            <input type="radio" name="default_deposit_mode"
                                   value="flat"
                                   <?= $depMode === 'flat' ? 'checked' : '' ?>>
                            Flat amount
                            <span style="color:#6b7280">£</span>
                            <input name="default_deposit_flat" type="number"
                                   step="0.01" min="0"
                                   value="<?= e((string) ($settings['default_deposit_flat'] ?? '0')) ?>"
                                   style="width:7rem;padding:0.375rem 0.5rem;
                                          border:1px solid #d1d5db;border-radius:6px;
                                          font:inherit;margin-left:0.25rem">
                        </label>
                    </div>
                </fieldset>

                <p style="color:#6b7280;font-size:0.8125rem;margin:-0.25rem 0 0.75rem">
                    Markup and discount are set per product
                    (<a href="/admin/products/index.php" style="color:#1f3b5b">Products</a>
                    → Edit → Pricing overrides).
                </p>

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

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Terms &amp; Conditions &amp; Privacy Policy</h2>
            </div>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="legal">

                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                    These print, personalised, at the bottom of your quote PDF and the
                    customer-facing quote. A suggested template is pre-filled below &mdash;
                    edit it to suit your business, then Save. <strong>Leave a box empty to
                    show nothing.</strong> This is a starting point, not legal advice &mdash;
                    have it reviewed before relying on it.
                </p>
                <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 1rem">
                    These placeholders fill in automatically on each quote:
                    <?php foreach (legal_token_list() as $tok => $desc): ?>
                        <code style="background:var(--bg-subtle-2);padding:0.05rem 0.3rem;border-radius:4px;font-size:0.8125rem"><?= e($tok) ?></code>
                    <?php endforeach; ?>
                </p>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="terms_conditions">Terms &amp; Conditions</label>
                        <textarea id="terms_conditions" name="terms_conditions" rows="16"
                                  style="font-family:inherit;line-height:1.5"><?= e($tcDisplay) ?></textarea>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="privacy_policy">Privacy Policy</label>
                        <textarea id="privacy_policy" name="privacy_policy" rows="16"
                                  style="font-family:inherit;line-height:1.5"><?= e($ppDisplay) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save terms &amp; privacy policy</button>
                </div>
            </form>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
