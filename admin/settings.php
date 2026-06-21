<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/legal_text.php';
require __DIR__ . '/../_partials/job_status_colours.php';

requireAdmin();

$user     = current_user();
$clientId = $user['client_id'];

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/**
 * Does the suppliers table have the optional account_number column?
 * Added by migrate_supplier_account_number.php. Lets the save handler +
 * the Suppliers tab degrade gracefully if the migration hasn't run yet.
 * Cached per-request.
 */
function suppliers_has_account_column(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    try {
        $st = db()->query("SHOW COLUMNS FROM suppliers LIKE 'account_number'");
        $has = $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

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
            // Per-blind price visibility on customer-facing quote/PDF.
            // Separate guarded write so a pre-migration DB (column absent)
            // still saves the rest of the quote settings. The INSERT above
            // guarantees the row exists by now. An unchecked checkbox sends
            // no field, so absence = hide.
            try {
                db()->prepare('UPDATE client_settings SET show_line_prices = ? WHERE client_id = ?')
                    ->execute([isset($_POST['show_line_prices']) ? 1 : 0, $clientId]);
            } catch (Throwable $e) {
                error_log('settings: show_line_prices not saved (run migrate_show_line_prices.php): ' . $e->getMessage());
            }
            $_SESSION['flash_success'] = 'Quote settings saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save settings: ' . $e->getMessage();
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'payment_details') {
        // Bank details printed on the customer's quote / invoice so they can
        // pay by transfer. Separate UPSERT so a pre-migration DB (columns
        // absent) flashes a clear message instead of breaking other settings.
        $bAccName = trim((string) ($_POST['bank_account_name']    ?? '')) ?: null;
        $bSort    = trim((string) ($_POST['bank_sort_code']       ?? '')) ?: null;
        $bAcc     = trim((string) ($_POST['bank_account_number']  ?? '')) ?: null;
        $bInstr   = trim((string) ($_POST['payment_instructions'] ?? '')) ?: null;
        try {
            db()->prepare(
                'INSERT INTO client_settings
                   (client_id, bank_account_name, bank_sort_code, bank_account_number, payment_instructions)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   bank_account_name    = VALUES(bank_account_name),
                   bank_sort_code       = VALUES(bank_sort_code),
                   bank_account_number  = VALUES(bank_account_number),
                   payment_instructions = VALUES(payment_instructions)'
            )->execute([$clientId, $bAccName, $bSort, $bAcc, $bInstr]);
            $_SESSION['flash_success'] = 'Bank / payment details saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save bank details — run /migrate_bank_details.php first.';
            error_log('settings: bank details not saved: ' . $e->getMessage());
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'legal') {
        // Terms & Conditions + Privacy Policy — free text, stored per client.
        // Saved separately from the quote-defaults block so it can never
        // disturb it. An empty box is a valid "configured but blank" value
        // (disables that document); a NULL column means never configured.
        $terms    = trim((string) ($_POST['terms_conditions']  ?? ''));
        $privacy  = trim((string) ($_POST['privacy_policy']    ?? ''));
        $acceptEm = trim((string) ($_POST['accept_email_body'] ?? ''));
        try {
            $stmt = db()->prepare(
                'INSERT INTO client_settings (client_id, terms_conditions, privacy_policy, accept_email_body)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   terms_conditions  = VALUES(terms_conditions),
                   privacy_policy    = VALUES(privacy_policy),
                   accept_email_body = VALUES(accept_email_body)'
            );
            $stmt->execute([$clientId, $terms, $privacy, $acceptEm]);
            $_SESSION['flash_success'] = 'Terms, Privacy Policy and acceptance email saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage()
                . ' — have you run migrate_terms_conditions.php?';
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'status_colours') {
        // Per-client traffic-light colours. Only store values that DIFFER
        // from the built-in defaults, so the stored JSON stays small and any
        // status added later inherits its default automatically. Invalid hex
        // is ignored (the field falls back to the default).
        $defaults  = job_status_defaults();
        $overrides = [];
        foreach ($defaults as $key => $default) {
            $hex = job_status_sanitise_hex($_POST['colour_' . $key] ?? null);
            if ($hex !== null && $hex !== strtolower($default)) {
                $overrides[$key] = $hex;
            }
        }
        try {
            $json = $overrides ? json_encode($overrides, JSON_UNESCAPED_SLASHES) : null;
            $stmt = db()->prepare(
                'INSERT INTO client_settings (client_id, job_status_colours)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE job_status_colours = VALUES(job_status_colours)'
            );
            $stmt->execute([$clientId, $json]);
            $_SESSION['flash_success'] = 'Status colours saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save colours: ' . $e->getMessage()
                . ' — have you run migrate_job_status_colours.php?';
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'joke') {
        // Dashboard "Joke of the day" on/off (migrate_joke_toggle.php).
        $jokeOn = !empty($_POST['feature_joke_of_day']) ? 1 : 0;
        try {
            db()->prepare(
                'INSERT INTO client_settings (client_id, feature_joke_of_day)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE feature_joke_of_day = VALUES(feature_joke_of_day)'
            )->execute([$clientId, $jokeOn]);
            $_SESSION['flash_success'] = $jokeOn ? 'Joke of the day is on.' : 'Joke of the day is off.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage()
                . ' — have you run migrate_joke_toggle.php?';
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'calendar_money') {
        // Show/hide order value + balance on the calendars (migrate_calendar_money.php).
        $on = !empty($_POST['calendar_show_money']) ? 1 : 0;
        try {
            db()->prepare(
                'INSERT INTO client_settings (client_id, calendar_show_money)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE calendar_show_money = VALUES(calendar_show_money)'
            )->execute([$clientId, $on]);
            $_SESSION['flash_success'] = $on
                ? 'Calendar will show order value + balance.'
                : 'Calendar money figures are now hidden.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage()
                . ' — have you run migrate_calendar_money.php?';
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'map_provider') {
        // Which navigation app the address links open in (migrate_map_provider.php).
        $prov = ((string) ($_POST['map_provider'] ?? 'google')) === 'waze' ? 'waze' : 'google';
        try {
            db()->prepare(
                'INSERT INTO client_settings (client_id, map_provider)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE map_provider = VALUES(map_provider)'
            )->execute([$clientId, $prov]);
            $_SESSION['flash_success'] = $prov === 'waze'
                ? 'Address links will now open in Waze.'
                : 'Address links will now open in Google Maps.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage()
                . ' — have you run migrate_map_provider.php?';
        }
        header('Location: /admin/settings.php');
        exit;
    }

    if ($action === 'suppliers') {
        // Master suppliers list: add / rename / set email / remove, plus the
        // delivery address. Rows keyed by suppliers.id (empty id = a new row);
        // supplier_delete[] holds the ids ticked for removal. Products also
        // auto-add their supplier here when saved, so the list fills itself.
        $ids      = (array) ($_POST['supplier_id']      ?? []);
        $names    = (array) ($_POST['supplier_name']    ?? []);
        $emails   = (array) ($_POST['supplier_email']   ?? []);
        $accounts = (array) ($_POST['supplier_account'] ?? []);
        $deletes  = array_values(array_filter(array_map('intval', (array) ($_POST['supplier_delete'] ?? []))));
        $delivery = trim((string) ($_POST['supplier_delivery_address'] ?? ''));
        $hasAcct  = suppliers_has_account_column();

        // Validate emails (rows being deleted are skipped).
        $badFor = null;
        foreach ($names as $i => $rawName) {
            if (in_array((int) ($ids[$i] ?? 0), $deletes, true)) continue;
            $nm = trim((string) $rawName);
            if ($nm === '') continue;
            $em = trim((string) ($emails[$i] ?? ''));
            if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) { $badFor = $nm; break; }
        }
        if ($badFor !== null) {
            $_SESSION['flash_error'] = 'That doesn\'t look like a valid email for "' . $badFor . '".';
            header('Location: /admin/settings.php');
            exit;
        }

        try {
            db()->prepare(
                'INSERT INTO client_settings (client_id, supplier_delivery_address)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE supplier_delivery_address = VALUES(supplier_delivery_address)'
            )->execute([$clientId, $delivery !== '' ? $delivery : null]);

            if ($deletes) {
                $place = implode(',', array_fill(0, count($deletes), '?'));
                db()->prepare("DELETE FROM suppliers WHERE client_id = ? AND id IN ($place)")
                    ->execute(array_merge([$clientId], $deletes));
            }

            if ($hasAcct) {
                $upd = db()->prepare('UPDATE suppliers SET name = ?, email = ?, account_number = ? WHERE id = ? AND client_id = ?');
                $ins = db()->prepare(
                    'INSERT INTO suppliers (client_id, name, email, account_number) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE email = VALUES(email), account_number = VALUES(account_number)'
                );
            } else {
                $upd = db()->prepare('UPDATE suppliers SET name = ?, email = ? WHERE id = ? AND client_id = ?');
                $ins = db()->prepare(
                    'INSERT INTO suppliers (client_id, name, email) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE email = VALUES(email)'
                );
            }
            $renameClash = false;
            foreach ($names as $i => $rawName) {
                $sid = (int) ($ids[$i] ?? 0);
                if (in_array($sid, $deletes, true)) continue;
                $nm = trim((string) $rawName);
                if ($nm === '') continue;
                $emVal   = (($em = trim((string) ($emails[$i] ?? ''))) !== '') ? $em : null;
                $acctVal = (($ac = trim((string) ($accounts[$i] ?? ''))) !== '') ? $ac : null;
                if ($sid > 0) {
                    try {
                        $upd->execute($hasAcct
                            ? [$nm, $emVal, $acctVal, $sid, $clientId]
                            : [$nm, $emVal, $sid, $clientId]);
                    } catch (PDOException $e) {
                        if (str_contains($e->getMessage(), 'uniq_supplier_per_client')) { $renameClash = true; continue; }
                        throw $e;
                    }
                } else {
                    $ins->execute($hasAcct
                        ? [$clientId, $nm, $emVal, $acctVal]
                        : [$clientId, $nm, $emVal]);
                }
            }
            $_SESSION['flash_success'] = $renameClash
                ? 'Suppliers saved — a rename was skipped because that name is already in your list.'
                : 'Suppliers saved.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save suppliers: ' . $e->getMessage()
                . ' — have you run migrate_suppliers.php?';
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
    'show_line_prices'         => 1,
    'bank_account_name'        => '',
    'bank_sort_code'           => '',
    'bank_account_number'      => '',
    'payment_instructions'     => '',
];
// May be absent if the row exists but the migration hasn't run.
$currentUnit = $settings['default_measurement_unit'] ?? 'mm';
if (!in_array($currentUnit, ['mm', 'cm', 'm', 'in'], true)) $currentUnit = 'mm';

// Suppliers list for the Suppliers tab — the master suppliers table, keyed by
// id so each row can be edited / renamed / removed. Products auto-add their
// supplier here when saved (so the list fills itself), and you can manage it.
$supplierRows = [];
$supHasAccount = suppliers_has_account_column();
try {
    $sSt = db()->prepare(
        'SELECT id, name, email, ' . ($supHasAccount ? 'account_number' : 'NULL AS account_number') . "
           FROM suppliers
          WHERE client_id = ? ORDER BY (name = 'In House') DESC, name"
    );
    $sSt->execute([$clientId]);
    $supplierRows = $sSt->fetchAll();
} catch (Throwable $e) {
    error_log('settings suppliers load failed: ' . $e->getMessage());
}
$supplierDelivery = (string) ($settings['supplier_delivery_address'] ?? '');

// Terms & Privacy textareas: show the stored value if configured, else
// pre-fill with the suggested template as a starting point. A NULL column
// (key absent — never saved, or migration not yet run) ⇒ show the template.
$tcStored  = $settings['terms_conditions']  ?? null;
$ppStored  = $settings['privacy_policy']    ?? null;
$aeStored  = $settings['accept_email_body'] ?? null;
$tcDisplay = $tcStored === null ? legal_default_terms()        : (string) $tcStored;
$ppDisplay = $ppStored === null ? legal_default_privacy()      : (string) $ppStored;
$aeDisplay = $aeStored === null ? legal_default_accept_email() : (string) $aeStored;

// Company address (one line) for the live preview of the legal/email
// templates — mirrors how legal_render_tokens builds {{company_address}}.
$previewAddress = implode(', ', array_filter([
    (string) ($client['address1'] ?? ''),
    (string) ($client['address2'] ?? ''),
    (string) ($client['town']     ?? ''),
    (string) ($client['county']   ?? ''),
    (string) ($client['postcode'] ?? ''),
], static fn ($p) => trim((string) $p) !== ''));

$activeNav = 'settings';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        /* Sub-menu tabs — break the long settings page into themed panels
           so there's no marathon scroll. Active panel is shown, the rest
           hidden; the chosen tab is remembered across saves (localStorage). */
        .settings-tabs {
            display: flex; flex-wrap: wrap; gap: 0.25rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.25rem;
        }
        .settings-tab {
            appearance: none; -webkit-appearance: none;
            border: 1px solid transparent; border-bottom: none;
            background: transparent; cursor: pointer;
            padding: 0.625rem 1rem; margin-bottom: -1px;
            font: inherit; font-weight: 600; font-size: 0.9375rem;
            color: var(--text-muted); border-radius: 8px 8px 0 0;
            min-height: 44px;
        }
        .settings-tab:hover { color: var(--text-primary); background: var(--bg-subtle-2); }
        .settings-tab.is-active {
            color: var(--brand); background: var(--bg-card);
            border-color: var(--border); border-bottom-color: var(--bg-card);
        }
        .settings-panel { display: none; }
        .settings-panel.is-active { display: block; }
        @media (max-width: 600px) {
            .settings-tab { padding: 0.5rem 0.75rem; font-size: 0.875rem; }
        }
    </style>
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

        <div class="settings-tabs" role="tablist" aria-label="Settings sections">
            <button type="button" class="settings-tab is-active" id="tab-company" data-tab="company" role="tab" aria-selected="true">Company</button>
            <button type="button" class="settings-tab" id="tab-quoting" data-tab="quoting" role="tab" aria-selected="false">Quoting</button>
            <button type="button" class="settings-tab" id="tab-legal" data-tab="legal" role="tab" aria-selected="false">Legal</button>
            <button type="button" class="settings-tab" id="tab-colours" data-tab="colours" role="tab" aria-selected="false">Status colours</button>
            <button type="button" class="settings-tab" id="tab-suppliers" data-tab="suppliers" role="tab" aria-selected="false">Suppliers</button>
            <button type="button" class="settings-tab" id="tab-backup" data-tab="backup" role="tab" aria-selected="false">Back up data</button>
        </div>

        <div class="settings-panels">
        <div class="settings-panel is-active" data-panel="company" role="tabpanel" aria-labelledby="tab-company">

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
                         style="max-height:80px;max-width:200px;background:#ffffff;padding:0.25rem;border:1px solid #e5e7eb;border-radius:6px">
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

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Dashboard</h2>
            </div>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="joke">
                <div class="form-row full">
                    <div class="form-group">
                        <label style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600;">
                            <input type="checkbox" name="feature_joke_of_day" value="1"
                                   <?= ((int) ($settings['feature_joke_of_day'] ?? 1)) === 1 ? 'checked' : '' ?>>
                            😄 Show a &ldquo;Joke of the day&rdquo; on the dashboard
                        </label>
                        <p style="margin:0.5rem 0 0;color:var(--text-faint);font-size:0.8125rem;">
                            A little light relief for your team on the dashboard (staff only — never
                            shown to customers). Dismissible per day. Untick to switch it off.
                        </p>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Calendar</h2>
            </div>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="calendar_money">
                <div class="form-row full">
                    <div class="form-group">
                        <label style="display:inline-flex;align-items:center;gap:.5rem;font-weight:600;">
                            <input type="checkbox" name="calendar_show_money" value="1"
                                   <?= ((int) ($settings['calendar_show_money'] ?? 0)) === 1 ? 'checked' : '' ?>>
                            💷 Show order value + balance on the calendar
                        </label>
                        <p style="margin:0.5rem 0 0;color:var(--text-faint);font-size:0.8125rem;">
                            On the month, week and day calendars, each job linked to a quote shows its
                            order value, amount received (deposit + payments) and outstanding balance —
                            with a PAID badge once it's settled. Leave unticked to keep figures off the
                            calendar (e.g. if fitters share the view).
                        </p>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>

            <?php $mapProvider = (($settings['map_provider'] ?? 'google') === 'waze') ? 'waze' : 'google'; ?>
            <form method="post" action="/admin/settings.php" class="form" novalidate
                  style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border);">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="map_provider">
                <div class="form-row full">
                    <div class="form-group">
                        <label style="display:block;font-weight:600;margin-bottom:0.5rem;">🧭 Navigation app</label>
                        <label style="display:inline-flex;align-items:center;gap:.5rem;margin-right:1.5rem;">
                            <input type="radio" name="map_provider" value="google"
                                   <?= $mapProvider === 'google' ? 'checked' : '' ?>>
                            Google Maps
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:.5rem;">
                            <input type="radio" name="map_provider" value="waze"
                                   <?= $mapProvider === 'waze' ? 'checked' : '' ?>>
                            Waze
                        </label>
                        <p style="margin:0.5rem 0 0;color:var(--text-faint);font-size:0.8125rem;">
                            When you tap an address on My Schedule or the day calendar, it opens in
                            the app you choose here. Google Maps is the default; pick Waze if your
                            fitters prefer it for live traffic and routing.
                        </p>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </section>

        </div><!-- /tab: company -->
        <div class="settings-panel" data-panel="quoting" role="tabpanel" aria-labelledby="tab-quoting">

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

                <?php
                // Column absent (pre-migration) ⇒ treat as ON, matching the
                // historic always-show-prices behaviour.
                $showLinePrices = !isset($settings['show_line_prices'])
                    || (int) $settings['show_line_prices'] === 1;
                ?>
                <fieldset style="border:1px solid #e5e7eb;border-radius:10px;
                                 padding:0.875rem 1rem;margin:0 0 1rem">
                    <legend style="padding:0 0.5rem;font-size:0.8125rem;
                                   font-weight:600;color:#1f3b5b;
                                   text-transform:uppercase;letter-spacing:0.05em">
                        Prices on the customer quote
                    </legend>
                    <label style="display:flex;align-items:flex-start;gap:0.55rem;
                                  font-size:0.9375rem;cursor:pointer">
                        <input type="checkbox" name="show_line_prices" value="1"
                               <?= $showLinePrices ? 'checked' : '' ?>
                               style="margin-top:0.2rem">
                        <span>
                            Show the price of each blind
                            <span style="display:block;color:#6b7280;font-size:0.8125rem;margin-top:0.2rem">
                                Ticked: the quote PDF and the customer's online quote list a
                                unit price and line total for every blind. Unticked: those
                                per-blind prices are hidden and the customer only sees the
                                quote total.
                            </span>
                        </span>
                    </label>
                </fieldset>

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
                <h2 class="section-title">Bank details for customer payments</h2>
            </div>
            <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                Printed on the customer's quote / invoice so they can pay by bank transfer.
                Leave blank to hide the "How to pay" block entirely.
            </p>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="payment_details">
                <div class="form-row full">
                    <div class="form-group">
                        <label for="bank_account_name">Account name</label>
                        <input id="bank_account_name" name="bank_account_name" type="text" maxlength="120"
                               value="<?= e((string) ($settings['bank_account_name'] ?? '')) ?>"
                               placeholder="e.g. Beverley Blinds Ltd">
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="bank_sort_code">Sort code</label>
                        <input id="bank_sort_code" name="bank_sort_code" type="text" maxlength="12"
                               value="<?= e((string) ($settings['bank_sort_code'] ?? '')) ?>"
                               placeholder="00-00-00">
                    </div>
                    <div class="form-group">
                        <label for="bank_account_number">Account number</label>
                        <input id="bank_account_number" name="bank_account_number" type="text" maxlength="40"
                               value="<?= e((string) ($settings['bank_account_number'] ?? '')) ?>"
                               placeholder="12345678">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="payment_instructions">Payment note (optional)</label>
                        <input id="payment_instructions" name="payment_instructions" type="text" maxlength="500"
                               value="<?= e((string) ($settings['payment_instructions'] ?? '')) ?>"
                               placeholder="e.g. Please use your quote number as the reference">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save bank details</button>
                </div>
            </form>
        </section>

        </div><!-- /tab: quoting -->
        <div class="settings-panel" data-panel="legal" role="tabpanel" aria-labelledby="tab-legal">

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
                        <div style="margin-top:0.5rem">
                            <div style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--text-faint);margin-bottom:0.25rem">Preview <span style="text-transform:none;letter-spacing:normal">— with example customer &amp; quote</span></div>
                            <div class="legal-preview" data-src="terms_conditions"
                                 style="white-space:pre-wrap;font-size:0.8125rem;line-height:1.6;color:var(--text-secondary);background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;padding:0.625rem 0.75rem;max-height:16rem;overflow:auto"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="privacy_policy">Privacy Policy</label>
                        <textarea id="privacy_policy" name="privacy_policy" rows="16"
                                  style="font-family:inherit;line-height:1.5"><?= e($ppDisplay) ?></textarea>
                        <div style="margin-top:0.5rem">
                            <div style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--text-faint);margin-bottom:0.25rem">Preview <span style="text-transform:none;letter-spacing:normal">— with example customer &amp; quote</span></div>
                            <div class="legal-preview" data-src="privacy_policy"
                                 style="white-space:pre-wrap;font-size:0.8125rem;line-height:1.6;color:var(--text-secondary);background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;padding:0.625rem 0.75rem;max-height:16rem;overflow:auto"></div>
                        </div>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="accept_email_body">Thank-you email (sent when a customer accepts a quote)</label>
                        <p style="color:#6b7280;font-size:0.8125rem;margin:0 0 0.5rem">
                            Placeholders: <code style="background:var(--bg-subtle-2);padding:0.05rem 0.3rem;border-radius:4px">{{customer_name}}</code>
                            <code style="background:var(--bg-subtle-2);padding:0.05rem 0.3rem;border-radius:4px">{{company_name}}</code>
                            <code style="background:var(--bg-subtle-2);padding:0.05rem 0.3rem;border-radius:4px">{{quote_number}}</code>
                            <code style="background:var(--bg-subtle-2);padding:0.05rem 0.3rem;border-radius:4px">{{quote_link}}</code>.
                            Leave empty to send no thank-you email.
                        </p>
                        <textarea id="accept_email_body" name="accept_email_body" rows="10"
                                  style="font-family:inherit;line-height:1.5"><?= e($aeDisplay) ?></textarea>
                        <div style="margin-top:0.5rem">
                            <div style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--text-faint);margin-bottom:0.25rem">Preview <span style="text-transform:none;letter-spacing:normal">— what the customer receives</span></div>
                            <div class="legal-preview" data-src="accept_email_body"
                                 style="white-space:pre-wrap;font-size:0.8125rem;line-height:1.6;color:var(--text-secondary);background:var(--bg-subtle);border:1px solid var(--border);border-radius:6px;padding:0.625rem 0.75rem;max-height:16rem;overflow:auto"></div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save terms, privacy &amp; email</button>
                </div>
            </form>
        </section>

        </div><!-- /tab: legal -->
        <div class="settings-panel" data-panel="colours" role="tabpanel" aria-labelledby="tab-colours">

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Status colours</h2>
            </div>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="status_colours">

                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 1rem">
                    Your &ldquo;traffic-light&rdquo; colours. A job shows the same colour
                    everywhere it appears &mdash; on the <strong>calendar</strong> and in your
                    <strong>orders list</strong> &mdash; and the calendar updates itself as the
                    job moves from stage to stage. Pick a colour for each stage below; the
                    sample pill updates as you go.
                </p>

                <?php
                    $colourPalette = job_client_palette((int) $clientId);
                    $colourLabels  = job_status_labels();
                    foreach (job_status_groups() as $groupName => $groupKeys):
                ?>
                    <div style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-faint);margin:0.5rem 0 0.5rem"><?= e($groupName) ?></div>
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem">
                        <?php foreach ($groupKeys as $sk):
                            $cur = $colourPalette[$sk] ?? '#2563eb';
                            $txt = job_status_text_colour($cur);
                        ?>
                            <div style="display:flex;align-items:center;gap:0.5rem;border:1px solid var(--border);border-radius:8px;padding:0.5rem 0.625rem;background:var(--bg-card)">
                                <input type="color"
                                       name="colour_<?= e($sk) ?>"
                                       value="<?= e($cur) ?>"
                                       data-status="<?= e($sk) ?>"
                                       class="status-colour-input"
                                       aria-label="<?= e($colourLabels[$sk] ?? $sk) ?> colour"
                                       style="width:2.25rem;height:2.25rem;border:none;background:none;padding:0;cursor:pointer">
                                <span class="status-colour-pill" data-for="<?= e($sk) ?>"
                                      style="display:inline-block;padding:0.0625rem 0.5rem;font-size:0.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;border-radius:999px;background:<?= e($cur) ?>;color:<?= e($txt) ?>">
                                    <?= e($colourLabels[$sk] ?? $sk) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save status colours</button>
                </div>
            </form>
        </section>

        </div><!-- /tab: colours -->
        <div class="settings-panel" data-panel="suppliers" role="tabpanel" aria-labelledby="tab-suppliers">

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Suppliers</h2>
            </div>
            <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.75rem">
                Who you <strong>order stock from</strong> — these fill a product's
                <em>Order supplier</em> field and go on purchase orders. Tick a row's delete box
                to remove a stray (they get added automatically when you save a product).
            </p>
            <form method="post" action="/admin/settings.php" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="suppliers">

                <div class="form-row full">
                    <div class="form-group">
                        <label for="supplier_delivery_address">Delivery address <span style="font-weight:400;color:var(--text-faint)">(where suppliers ship to)</span></label>
                        <textarea id="supplier_delivery_address" name="supplier_delivery_address" rows="4"
                                  placeholder="Your business / warehouse address — this goes on every supplier order"><?= e($supplierDelivery) ?></textarea>
                    </div>
                </div>

                <p style="color:var(--text-faint);font-size:0.8125rem;margin:1.25rem 0 0.5rem">
                    Add the order email for each supplier. You can rename a supplier, tick
                    <strong>Remove</strong> to delete it, or add one in the bottom row — then Save.
                    Suppliers you set on products appear here automatically.
                </p>

                <?php
                    $supInputStyle = 'width:100%;padding:0.375rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit';
                ?>
                <table class="table" style="font-size:0.9375rem;margin:0 0 1rem">
                    <thead><tr>
                        <th style="width:30%">Supplier</th>
                        <th>Order email</th>
                        <?php if ($supHasAccount): ?>
                            <th style="width:20%">Account no.</th>
                        <?php endif; ?>
                        <th style="width:5.5rem;text-align:center">Remove</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($supplierRows as $sup): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="supplier_id[]" value="<?= (int) $sup['id'] ?>">
                                    <input type="text" name="supplier_name[]" maxlength="150"
                                           value="<?= e((string) $sup['name']) ?>" style="<?= $supInputStyle ?>">
                                </td>
                                <td>
                                    <input type="email" name="supplier_email[]" maxlength="190"
                                           value="<?= e((string) ($sup['email'] ?? '')) ?>"
                                           placeholder="orders@supplier.com" style="<?= $supInputStyle ?>;max-width:24rem">
                                </td>
                                <?php if ($supHasAccount): ?>
                                <td>
                                    <input type="text" name="supplier_account[]" maxlength="100"
                                           value="<?= e((string) ($sup['account_number'] ?? '')) ?>"
                                           placeholder="Your account no." style="<?= $supInputStyle ?>;max-width:16rem">
                                </td>
                                <?php endif; ?>
                                <td style="text-align:center">
                                    <input type="checkbox" name="supplier_delete[]" value="<?= (int) $sup['id'] ?>"
                                           aria-label="Remove <?= e((string) $sup['name']) ?>"
                                           style="width:18px;height:18px;cursor:pointer">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td>
                                <input type="hidden" name="supplier_id[]" value="">
                                <input type="text" name="supplier_name[]" maxlength="150"
                                       placeholder="+ Add a supplier" style="<?= $supInputStyle ?>">
                            </td>
                            <td>
                                <input type="email" name="supplier_email[]" maxlength="190"
                                       placeholder="orders@supplier.com" style="<?= $supInputStyle ?>;max-width:24rem">
                            </td>
                            <?php if ($supHasAccount): ?>
                            <td>
                                <input type="text" name="supplier_account[]" maxlength="100"
                                       placeholder="Your account no." style="<?= $supInputStyle ?>;max-width:16rem">
                            </td>
                            <?php endif; ?>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save suppliers</button>
                </div>
            </form>
        </section>

        </div><!-- /tab: suppliers -->

        <div class="settings-panel" data-panel="backup" role="tabpanel" aria-labelledby="tab-backup">
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Back up your data</h2>
            </div>
            <?php $lastBackup = $client['last_backup_at'] ?? null; ?>
            <p style="color:var(--text-secondary);margin:0 0 1rem;max-width:42rem">
                Download a copy of <strong>your quotes and orders</strong> (with their line
                items, totals and payments) to keep on your own computer. Useful as a regular
                off-site backup or to work with your figures in a spreadsheet.
            </p>

            <!-- Date range — leave both blank for everything -->
            <div style="display:flex;gap:1rem 1.25rem;flex-wrap:wrap;align-items:flex-end;margin:0 0 0.5rem">
                <label style="display:flex;flex-direction:column;gap:0.25rem;font-size:0.8125rem;color:var(--text-secondary)">
                    From
                    <input type="date" id="backup-from" class="form-control" style="min-width:9.5rem">
                </label>
                <label style="display:flex;flex-direction:column;gap:0.25rem;font-size:0.8125rem;color:var(--text-secondary)">
                    To
                    <input type="date" id="backup-to" class="form-control" style="min-width:9.5rem">
                </label>
                <div style="display:flex;gap:0.375rem;flex-wrap:wrap">
                    <button type="button" class="btn btn-secondary btn-sm" data-backup-preset="all">All time</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-backup-preset="30">Last 30 days</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-backup-preset="ytd">This year</button>
                </div>
            </div>

            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;margin:0.75rem 0 0">
                <button type="button" id="backup-xlsx" class="btn btn-primary">⬇ Download Excel (.xlsx)</button>
                <button type="button" id="backup-pdf" class="btn btn-secondary">⬇ Download PDF summary</button>
            </div>

            <!-- Incremental: only what changed since the last FULL Excel backup -->
            <div style="margin:1rem 0 0;padding:0.875rem 1rem;border:1px solid var(--border);border-radius:0.5rem;max-width:42rem">
                <?php if ($lastBackup): ?>
                    <p style="margin:0 0 0.625rem;font-size:0.875rem;color:var(--text-secondary)">
                        Last full backup: <strong><?= e(date('j M Y, g:ia', strtotime((string) $lastBackup))) ?></strong>.
                        Get just the quotes and orders created or changed since then:
                    </p>
                    <a href="/admin/export.php?format=xlsx&amp;since_last=1" class="btn btn-secondary btn-sm">⬇ Changes since last backup (Excel)</a>
                <?php else: ?>
                    <p style="margin:0;font-size:0.875rem;color:var(--text-faint)">
                        Once you take a full Excel backup (the button above, with no dates set),
                        a <em>“Changes since last backup”</em> option appears here so you can grab
                        only what's new or changed next time.
                    </p>
                <?php endif; ?>
            </div>

            <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.875rem 0 0;max-width:42rem">
                The <strong>Excel</strong> file has two sheets — a Quotes &amp; Orders summary and a
                full Line items list — and is the one to keep as your backup (you can open, sort and
                filter it). The <strong>PDF</strong> is a printable one-look summary. Dates filter by
                <strong>order date</strong> (accepted, or created if not yet accepted). A full Excel
                backup (no dates) is what the “since last backup” option counts from.
            </p>
        </section>
        </div><!-- /tab: backup -->
        </div><!-- /settings-panels -->
    </main>
</div>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
<?php
// Live-preview token values: the tenant's REAL company details + EXAMPLE
// customer/quote data, so a client editing a template sees exactly what the
// customer gets — making the {{placeholders}} self-explanatory.
$legalPreviewTokens = [
    '{{company_name}}'    => (string) ($client['company_name'] ?? ''),
    '{{company_address}}' => $previewAddress,
    '{{company_email}}'   => (string) ($client['email'] ?? ''),
    '{{company_phone}}'   => (string) ($client['phone'] ?? ''),
    '{{customer_name}}'   => 'Jane Smith',
    '{{quote_number}}'    => 'BEV-2026-0042',
    '{{quote_link}}'      => 'https://your-site/quote-history/public.php?token=abc123',
    '{{date}}'            => date('j F Y'),
];
?>
<script>
(function () {
    var TOKENS = <?= json_encode($legalPreviewTokens, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    function render(ta, box) {
        var txt = ta.value;
        for (var k in TOKENS) { txt = txt.split(k).join(TOKENS[k]); }
        box.textContent = txt;   // textContent — never interprets HTML
    }
    Array.prototype.forEach.call(document.querySelectorAll('.legal-preview'), function (box) {
        var ta = document.getElementById(box.getAttribute('data-src'));
        if (!ta) { return; }
        render(ta, box);
        ta.addEventListener('input', function () { render(ta, box); });
    });

    // Live status-colour previews — mirrors job_status_text_colour() in PHP so
    // the sample pill stays legible whatever colour is picked.
    function textOn(hex) {
        var h = hex.replace('#', '');
        if (h.length !== 6) { return '#ffffff'; }
        var r = parseInt(h.substr(0, 2), 16),
            g = parseInt(h.substr(2, 2), 16),
            b = parseInt(h.substr(4, 2), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#1f2937' : '#ffffff';
    }
    Array.prototype.forEach.call(document.querySelectorAll('.status-colour-input'), function (inp) {
        var pill = document.querySelector('.status-colour-pill[data-for="' + inp.getAttribute('data-status') + '"]');
        if (!pill) { return; }
        inp.addEventListener('input', function () {
            pill.style.background = inp.value;
            pill.style.color = textOn(inp.value);
        });
    });

    // ---- Data backup: build the export URL from the date pickers ------------
    (function () {
        var from = document.getElementById('backup-from');
        var to   = document.getElementById('backup-to');
        if (!from || !to) { return; }

        function go(format) {
            var qs = ['format=' + format];
            if (from.value) { qs.push('from=' + encodeURIComponent(from.value)); }
            if (to.value)   { qs.push('to='   + encodeURIComponent(to.value)); }
            window.location.href = '/admin/export.php?' + qs.join('&');
        }
        var xlsx = document.getElementById('backup-xlsx');
        var pdf  = document.getElementById('backup-pdf');
        if (xlsx) { xlsx.addEventListener('click', function () { go('xlsx'); }); }
        if (pdf)  { pdf.addEventListener('click',  function () { go('pdf'); }); }

        // Local-date ISO (not toISOString → that's UTC and can slip a day).
        function iso(d) {
            var m = String(d.getMonth() + 1), day = String(d.getDate());
            if (m.length < 2)   { m = '0' + m; }
            if (day.length < 2) { day = '0' + day; }
            return d.getFullYear() + '-' + m + '-' + day;
        }
        Array.prototype.forEach.call(document.querySelectorAll('[data-backup-preset]'), function (btn) {
            btn.addEventListener('click', function () {
                var preset = btn.getAttribute('data-backup-preset'), today = new Date();
                if (preset === 'all') {
                    from.value = ''; to.value = '';
                } else if (preset === '30') {
                    var d = new Date(); d.setDate(d.getDate() - 30);
                    from.value = iso(d); to.value = iso(today);
                } else if (preset === 'ytd') {
                    from.value = today.getFullYear() + '-01-01'; to.value = iso(today);
                }
            });
        });
    })();

    // ---- Sub-menu tabs ------------------------------------------------------
    // Show one panel at a time. The active tab is remembered (localStorage) so
    // saving a section — which reloads the page — lands you back on it, not at
    // the top. A URL #hash wins over the saved tab (for deep links).
    var tabs   = Array.prototype.slice.call(document.querySelectorAll('.settings-tab'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.settings-panel'));
    if (tabs.length) {
        var STORE = 'yb_settings_tab';
        function activate(name, persist) {
            var matched = false;
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-tab') === name;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) { matched = true; }
            });
            if (!matched) { return false; }
            panels.forEach(function (p) {
                p.classList.toggle('is-active', p.getAttribute('data-panel') === name);
            });
            if (persist) { try { localStorage.setItem(STORE, name); } catch (e) {} }
            return true;
        }
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                var name = t.getAttribute('data-tab');
                activate(name, true);
                if (window.history && history.replaceState) {
                    history.replaceState(null, '', '#' + name);
                }
            });
        });
        var fromHash = (window.location.hash || '').replace('#', '');
        var saved = '';
        try { saved = localStorage.getItem(STORE) || ''; } catch (e) {}
        if (!activate(fromHash, false) && !activate(saved, false)) {
            activate(tabs[0].getAttribute('data-tab'), false);
        }
    }
})();
</script>
</body>
</html>
