<?php
declare(strict_types=1);

/**
 * Master Admin: Trade Account — single-account detail + edit.
 *
 * The per-client control page that didn't exist before: a super-admin can view
 * and edit an account's business/contact profile, toggle it active/inactive, and
 * see its logins. Login control (reset password / change login) is added in 1B;
 * per-account discounts in 1C — this page is the host for both.
 *
 * Super-admin only. All mutation is POST + CSRF + flash-then-redirect.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../_partials/verification.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$myClient = (int) $user['client_id'];

$clientId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($clientId <= 0) {
    header('Location: /master-admin/trade-accounts.php');
    exit;
}

$colExists = static function (string $table, string $col) use ($pdo): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $col]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
};
$hasCreated = $colExists('clients', 'created_at');

// ── POST handlers ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');

    // Confirm the target exists before mutating.
    $chk = $pdo->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
    $chk->execute([$clientId]);
    if ($chk->fetchColumn() === false) {
        $_SESSION['flash_error'] = 'Account not found.';
        header('Location: /master-admin/trade-accounts.php');
        exit;
    }

    if ($action === 'profile') {
        // Same field set as the tenant-side business profile (admin/settings.php),
        // but a super-admin can edit any account's here.
        $company = trim((string) ($_POST['company_name'] ?? ''));
        if ($company === '') {
            $_SESSION['flash_error'] = 'Company name is required.';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE clients
                        SET company_name = ?, contact_name = ?, email = ?, phone = ?,
                            vat_number = ?, address1 = ?, address2 = ?, town = ?,
                            county = ?, postcode = ?
                      WHERE id = ?'
                )->execute([
                    mb_substr($company, 0, 150),
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
                // If we just renamed our OWN client, refresh the session copy.
                if ($clientId === $myClient) $_SESSION['company_name'] = $company;
                $_SESSION['flash_success'] = 'Account details saved.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
            }
        }
        header('Location: /master-admin/trade-account.php?id=' . $clientId);
        exit;
    }

    if ($action === 'active') {
        $to = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;
        // Never let a super-admin deactivate their OWN account (self-lockout).
        if ($clientId === $myClient && $to === 0) {
            $_SESSION['flash_error'] = 'You can\'t deactivate your own account.';
        } else {
            try {
                $pdo->prepare('UPDATE clients SET active = ? WHERE id = ?')->execute([$to, $clientId]);
                $_SESSION['flash_success'] = $to === 1 ? 'Account activated.' : 'Account deactivated.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'Could not update: ' . $e->getMessage();
            }
        }
        header('Location: /master-admin/trade-account.php?id=' . $clientId);
        exit;
    }

    // ── Login (client_users) actions ────────────────────────────────────────
    // Every one is scoped to THIS account (WHERE id=? AND client_id=?) and
    // refuses to touch a super-admin user — parity with delete-client.php.
    $userActions = ['user_reset_link','user_set_password','user_change_login',
                    'user_toggle_active','user_resend_verification','user_mark_verified','user_add'];
    if (in_array($action, $userActions, true)) {
        $companyName = (string) ($pdo->query('SELECT company_name FROM clients WHERE id = ' . (int) $clientId)->fetchColumn() ?: '');
        $redirect = '/master-admin/trade-account.php?id=' . $clientId . '#logins';

        // Create a brand-new login on this account.
        if ($action === 'user_add') {
            $name  = trim((string) ($_POST['full_name'] ?? ''));
            $mail  = trim((string) ($_POST['email'] ?? ''));
            $pw    = (string) ($_POST['new_password'] ?? '');
            if ($name === '') {
                $_SESSION['flash_error'] = 'Full name is required.';
            } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_error'] = 'A valid email is required.';
            } elseif (strlen($pw) < 8) {
                $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
            } else {
                $dup = $pdo->prepare('SELECT 1 FROM client_users WHERE email = ? LIMIT 1');
                $dup->execute([$mail]);
                if ($dup->fetchColumn()) {
                    $_SESSION['flash_error'] = 'That email is already used by another login.';
                } else {
                    try {
                        // Admin-created → verified now (admin vouches), so they aren't
                        // blocked by the confirm-email gate. They can set their own
                        // password via "Send reset link" afterwards.
                        $pdo->prepare(
                            'INSERT INTO client_users
                               (client_id, email, full_name, password_hash, role, active, is_super_admin, email_verified_at)
                             VALUES (?, ?, ?, ?, "admin", 1, 0, NOW())'
                        )->execute([$clientId, $mail, $name, password_hash($pw, PASSWORD_DEFAULT)]);
                        $_SESSION['flash_success'] = 'Login added for ' . $mail . '. Use "Send reset link" if you want them to set their own password.';
                    } catch (Throwable $e) {
                        $_SESSION['flash_error'] = 'Could not add login: ' . $e->getMessage();
                    }
                }
            }
            header('Location: ' . $redirect);
            exit;
        }

        // All remaining actions target an existing user on this account.
        $uid = (int) ($_POST['user_id'] ?? 0);
        $tu  = $pdo->prepare(
            'SELECT id, client_id, email, username, full_name, active, is_super_admin, email_verified_at
               FROM client_users WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $tu->execute([$uid, $clientId]);
        $target = $tu->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            $_SESSION['flash_error'] = 'Login not found on this account.';
            header('Location: ' . $redirect); exit;
        }
        if ((int) $target['is_super_admin'] === 1) {
            $_SESSION['flash_error'] = 'That is a super-admin login — manage it from the owner\'s own account settings, not here.';
            header('Location: ' . $redirect); exit;
        }

        try {
            if ($action === 'user_reset_link') {
                if (($target['email'] ?? '') === '') {
                    $_SESSION['flash_error'] = 'This login has no email — set one, or use "Set a temporary password".';
                } else {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
                        ->execute([$uid, hash('sha256', $token), (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s')]);
                    $base = trim((string) (env('APP_URL', '') ?? ''));
                    $url  = ($base !== '' ? rtrim($base, '/') : '') . '/auth/reset_password.php?token=' . $token;
                    $body = "Hello,\n\n"
                          . "The administrator has set up a password reset for your YourBlinds account.\n\n"
                          . "Use the link below to choose a new password (valid for 24 hours):\n"
                          . $url . "\n\n"
                          . "If you weren't expecting this, please contact us.\n\n— YourBlinds";
                    $ok = mailer_send($target['email'], 'Set your YourBlinds password', $body);
                    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
                        ? ('Reset link sent to ' . $target['email'] . '.')
                        : ('Could not send the email — check mail settings (or Testing mode is on).');
                }
            } elseif ($action === 'user_set_password') {
                $pw = (string) ($_POST['new_password'] ?? '');
                if (strlen($pw) < 8) {
                    $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
                } else {
                    $pdo->prepare('UPDATE client_users SET password_hash = ? WHERE id = ? AND client_id = ?')
                        ->execute([password_hash($pw, PASSWORD_DEFAULT), $uid, $clientId]);
                    // Invalidate any outstanding reset tokens for this user.
                    try { $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$uid]); }
                    catch (Throwable $e) { /* table/shape tolerant */ }
                    $_SESSION['flash_success'] = 'Temporary password set for ' . ($target['full_name'] ?: $target['email']) . '. Share it securely; they can change it in Settings.';
                }
            } elseif ($action === 'user_change_login') {
                $newEmail = trim((string) ($_POST['email'] ?? ''));
                $newUser  = trim((string) ($_POST['username'] ?? ''));
                if ($newEmail === '' && $newUser === '') {
                    $_SESSION['flash_error'] = 'Enter an email or a username.';
                } elseif ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['flash_error'] = 'That email address isn\'t valid.';
                } else {
                    // Uniqueness against OTHER users.
                    $clash = false;
                    if ($newEmail !== '') {
                        $c = $pdo->prepare('SELECT 1 FROM client_users WHERE email = ? AND id <> ? LIMIT 1');
                        $c->execute([$newEmail, $uid]);
                        if ($c->fetchColumn()) { $clash = true; $_SESSION['flash_error'] = 'That email is already used by another login.'; }
                    }
                    if (!$clash && $newUser !== '') {
                        $c = $pdo->prepare('SELECT 1 FROM client_users WHERE username = ? AND id <> ? LIMIT 1');
                        $c->execute([$newUser, $uid]);
                        if ($c->fetchColumn()) { $clash = true; $_SESSION['flash_error'] = 'That username is already taken.'; }
                    }
                    if (!$clash) {
                        $pdo->prepare('UPDATE client_users SET email = ?, username = ? WHERE id = ? AND client_id = ?')
                            ->execute([$newEmail !== '' ? $newEmail : null, $newUser !== '' ? $newUser : null, $uid, $clientId]);
                        $_SESSION['flash_success'] = 'Login details updated.';
                    }
                }
            } elseif ($action === 'user_toggle_active') {
                $to = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;
                if ($uid === (int) ($user['user_id'] ?? 0) && $to === 0) {
                    $_SESSION['flash_error'] = 'You can\'t deactivate your own login.';
                } else {
                    $pdo->prepare('UPDATE client_users SET active = ? WHERE id = ? AND client_id = ?')->execute([$to, $uid, $clientId]);
                    $_SESSION['flash_success'] = $to === 1 ? 'Login activated.' : 'Login deactivated.';
                }
            } elseif ($action === 'user_resend_verification') {
                if (($target['email'] ?? '') === '') {
                    $_SESSION['flash_error'] = 'This login has no email to verify.';
                } else {
                    $token = verification_create_token($pdo, $uid);
                    $ok = verification_send_email($target['email'], verification_build_url($token), $companyName);
                    $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $ok
                        ? ('Confirmation email sent to ' . $target['email'] . '.')
                        : ('Could not send the email — check mail settings (or Testing mode is on).');
                }
            } elseif ($action === 'user_mark_verified') {
                try {
                    $pdo->prepare('UPDATE client_users SET email_verified_at = NOW() WHERE id = ? AND client_id = ?')->execute([$uid, $clientId]);
                    $_SESSION['flash_success'] = 'Marked as verified — they can sign in now.';
                } catch (Throwable $e) {
                    $_SESSION['flash_error'] = 'Could not update (email_verified_at column missing?).';
                }
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Action failed: ' . $e->getMessage();
        }
        header('Location: ' . $redirect);
        exit;
    }

    // ── Trade discount actions (buying discount off our trade price) ─────────
    if ($action === 'td_add' || $action === 'td_delete') {
        $redirect = '/master-admin/trade-account.php?id=' . $clientId . '#discounts';
        $whoId    = (int) ($user['user_id'] ?? 0);
        $whoName  = (string) ($user['full_name'] ?? '');

        // system_id column present? (added by the updated migration) — degrade
        // gracefully to product/band only on an older schema.
        $tdHasSys = false;
        try {
            $cs = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trade_discounts' AND COLUMN_NAME = 'system_id' LIMIT 1");
            $cs->execute();
            $tdHasSys = $cs->fetchColumn() !== false;
        } catch (Throwable $e) { /* keep false */ }

        $audit = static function (array $row) use ($pdo, $clientId, $whoId, $whoName, $tdHasSys): void {
            try {
                if ($tdHasSys) {
                    $pdo->prepare(
                        'INSERT INTO trade_discount_audit
                           (client_id, product_id, product_name, system_id, system_name, band_code, old_pct, new_pct, action, changed_by, changed_by_name)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $clientId, $row['product_id'] ?? null, $row['product_name'] ?? null,
                        $row['system_id'] ?? null, $row['system_name'] ?? null,
                        $row['band_code'] ?? null, $row['old_pct'] ?? null, $row['new_pct'] ?? null,
                        $row['action'], $whoId ?: null, $whoName ?: null,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO trade_discount_audit
                           (client_id, product_id, product_name, band_code, old_pct, new_pct, action, changed_by, changed_by_name)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $clientId, $row['product_id'] ?? null, $row['product_name'] ?? null,
                        $row['band_code'] ?? null, $row['old_pct'] ?? null, $row['new_pct'] ?? null,
                        $row['action'], $whoId ?: null, $whoName ?: null,
                    ]);
                }
            } catch (Throwable $e) { /* audit is best-effort */ }
        };

        try {
            if ($action === 'td_add') {
                $pid  = (int) ($_POST['product_id'] ?? 0);
                $band = trim((string) ($_POST['band_code'] ?? ''));
                $bandCode = ($band === '' || strcasecmp($band, 'all') === 0 || $band === '*ALL*') ? null : $band;
                $pct  = (float) ($_POST['discount_percent'] ?? 0);
                $pct  = max(0.0, min(100.0, $pct));

                // Product must belong to THIS account.
                $pc = $pdo->prepare('SELECT name FROM products WHERE id = ? AND client_id = ? LIMIT 1');
                $pc->execute([$pid, $clientId]);
                $pname = $pc->fetchColumn();
                if ($pname === false) {
                    $_SESSION['flash_error'] = 'Pick a product on this account.';
                    header('Location: ' . $redirect); exit;
                }

                // System (optional) — validate it belongs to this product/account.
                $systemId   = null;
                $systemName = null;
                if ($tdHasSys) {
                    $sid = (int) ($_POST['system_id'] ?? 0);
                    if ($sid > 0) {
                        $sc = $pdo->prepare('SELECT name FROM product_systems WHERE id = ? AND product_id = ? AND client_id = ? LIMIT 1');
                        $sc->execute([$sid, $pid, $clientId]);
                        $sn = $sc->fetchColumn();
                        if ($sn !== false) { $systemId = $sid; $systemName = (string) $sn; }
                    }
                }

                // One row per (client, product, system, band) — upsert. Build the
                // NULL-aware WHERE for the two optional axes.
                $where  = 'client_id = ? AND product_id = ?';
                $params = [$clientId, $pid];
                if ($tdHasSys) {
                    $where .= $systemId === null ? ' AND system_id IS NULL' : ' AND system_id = ?';
                    if ($systemId !== null) $params[] = $systemId;
                }
                $where .= $bandCode === null ? ' AND band_code IS NULL' : ' AND band_code = ?';
                if ($bandCode !== null) $params[] = $bandCode;

                $ex = $pdo->prepare("SELECT id, discount_percent FROM trade_discounts WHERE $where LIMIT 1");
                $ex->execute($params);
                $existing = $ex->fetch(PDO::FETCH_ASSOC);

                $auditRow = ['product_id' => $pid, 'product_name' => (string) $pname,
                             'system_id' => $systemId, 'system_name' => $systemName, 'band_code' => $bandCode];

                if ($existing) {
                    $pdo->prepare('UPDATE trade_discounts SET discount_percent = ?, active = 1 WHERE id = ?')
                        ->execute([$pct, (int) $existing['id']]);
                    $audit($auditRow + ['old_pct' => (float) $existing['discount_percent'], 'new_pct' => $pct, 'action' => 'update']);
                    $_SESSION['flash_success'] = 'Discount updated.';
                } else {
                    if ($tdHasSys) {
                        $pdo->prepare('INSERT INTO trade_discounts (client_id, product_id, system_id, band_code, discount_percent, active) VALUES (?, ?, ?, ?, ?, 1)')
                            ->execute([$clientId, $pid, $systemId, $bandCode, $pct]);
                    } else {
                        $pdo->prepare('INSERT INTO trade_discounts (client_id, product_id, band_code, discount_percent, active) VALUES (?, ?, ?, ?, 1)')
                            ->execute([$clientId, $pid, $bandCode, $pct]);
                    }
                    $audit($auditRow + ['old_pct' => null, 'new_pct' => $pct, 'action' => 'add']);
                    $_SESSION['flash_success'] = 'Discount added.';
                }
            } else { // td_delete
                $tid = (int) ($_POST['td_id'] ?? 0);
                $selCols = 'td.id, td.product_id, td.band_code, td.discount_percent, p.name AS product_name'
                         . ($tdHasSys ? ', td.system_id, s.name AS system_name' : '');
                $selJoin = $tdHasSys ? ' LEFT JOIN product_systems s ON s.id = td.system_id' : '';
                $row = $pdo->prepare(
                    "SELECT $selCols FROM trade_discounts td JOIN products p ON p.id = td.product_id$selJoin
                      WHERE td.id = ? AND td.client_id = ? LIMIT 1"
                );
                $row->execute([$tid, $clientId]);
                $d = $row->fetch(PDO::FETCH_ASSOC);
                if ($d) {
                    $pdo->prepare('DELETE FROM trade_discounts WHERE id = ? AND client_id = ?')->execute([$tid, $clientId]);
                    $audit(['product_id' => (int) $d['product_id'], 'product_name' => (string) $d['product_name'],
                            'system_id' => $d['system_id'] ?? null, 'system_name' => $d['system_name'] ?? null,
                            'band_code' => $d['band_code'], 'old_pct' => (float) $d['discount_percent'], 'new_pct' => null, 'action' => 'delete']);
                    $_SESSION['flash_success'] = 'Discount removed.';
                } else {
                    $_SESSION['flash_error'] = 'Discount not found.';
                }
            }
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Could not save discount: ' . $e->getMessage()
                . ' (has /migrate_trade_discounts.php been run?)';
        }
        header('Location: ' . $redirect);
        exit;
    }

    header('Location: /master-admin/trade-account.php?id=' . $clientId);
    exit;
}

// ── Load the account ────────────────────────────────────────────────────────
$sel = 'SELECT id, company_name, contact_name, email, phone, vat_number,
               address1, address2, town, county, postcode, active, logo_path'
     . ($hasCreated ? ', created_at' : '') . '
          FROM clients WHERE id = ? LIMIT 1';
$st = $pdo->prepare($sel);
$st->execute([$clientId]);
$acc = $st->fetch(PDO::FETCH_ASSOC);

if (!$acc) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title>'
       . '<h1>Account not found</h1><p><a href="/master-admin/trade-accounts.php">Back to Trade Accounts</a></p>';
    exit;
}

// Logins (read-only here; management arrives in 1B).
$logins = [];
try {
    $lu = $pdo->prepare(
        'SELECT id, full_name, email, username, role, active, last_login_at, email_verified_at, is_super_admin
           FROM client_users WHERE client_id = ? ORDER BY is_super_admin DESC, full_name, email'
    );
    $lu->execute([$clientId]);
    $logins = $lu->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* leave empty */ }

// Quick counts.
$countOf = static function (string $sql, int $id) use ($pdo): int {
    try { $s = $pdo->prepare($sql); $s->execute([$id]); return (int) $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
};
$productCount  = $countOf('SELECT COUNT(*) FROM products WHERE client_id = ?', $clientId);

// ── Trade discounts (buying discount off our trade price) ────────────────────
$tdReady = false;
try {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trade_discounts' LIMIT 1");
    $s->execute();
    $tdReady = $s->fetchColumn() !== false;
} catch (Throwable $e) { /* not migrated yet */ }

$tdHasSys = false;
if ($tdReady) {
    try {
        $cs = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trade_discounts' AND COLUMN_NAME = 'system_id' LIMIT 1");
        $cs->execute();
        $tdHasSys = $cs->fetchColumn() !== false;
    } catch (Throwable $e) { /* older schema — product/band only */ }
}

$accProducts    = [];   // id => name (this account's products)
$productBands   = [];   // id => [band codes]
$productSystems = [];   // id => [{id,name}]
$tradeDiscounts = [];   // rows for this account
$tdAudit        = [];   // recent change history
if ($tdReady) {
    try {
        $ps = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? ORDER BY sort_order, name');
        $ps->execute([$clientId]);
    } catch (Throwable $e) {
        $ps = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? ORDER BY name');
        $ps->execute([$clientId]);
    }
    foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $p) $accProducts[(int) $p['id']] = (string) $p['name'];

    if ($accProducts) {
        $pids = array_keys($accProducts);
        $ph   = implode(',', array_fill(0, count($pids), '?'));
        try {
            $bs = $pdo->prepare(
                "SELECT product_id, band_code FROM (
                    SELECT product_id, band_code FROM product_options WHERE client_id = ? AND product_id IN ($ph)
                    UNION
                    SELECT product_id, band_code FROM price_tables   WHERE client_id = ? AND product_id IN ($ph)
                 ) x WHERE band_code IS NOT NULL AND band_code <> ''"
            );
            $bs->execute(array_merge([$clientId], $pids, [$clientId], $pids));
            foreach ($bs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $productBands[(int) $r['product_id']][(string) $r['band_code']] = true;
            }
        } catch (Throwable $e) { /* leave empty */ }
        // Band sort: premium A-runs first (AAA, AA, A), then alphabetical.
        $bandCmp = static function (string $a, string $b): int {
            $aA = (bool) preg_match('/^A+$/i', $a);
            $bA = (bool) preg_match('/^A+$/i', $b);
            if ($aA && $bA) return strlen($b) <=> strlen($a);
            if ($aA !== $bA) return $aA ? -1 : 1;
            return strcmp($a, $b);
        };
        foreach ($productBands as $pid => $set) {
            $list = array_keys($set);
            usort($list, $bandCmp);
            $productBands[$pid] = $list;
        }

        // Systems per product (for the System dropdown).
        try {
            $ss = $pdo->prepare("SELECT id, product_id, name FROM product_systems WHERE client_id = ? AND product_id IN ($ph) AND active = 1 ORDER BY sort_order, name");
            $ss->execute(array_merge([$clientId], $pids));
        } catch (Throwable $e) {
            $ss = $pdo->prepare("SELECT id, product_id, name FROM product_systems WHERE client_id = ? AND product_id IN ($ph) ORDER BY name");
            $ss->execute(array_merge([$clientId], $pids));
        }
        foreach ($ss->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $productSystems[(int) $r['product_id']][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
        }
    }

    $selCols = 'td.id, td.product_id, td.band_code, td.discount_percent, td.active'
             . ($tdHasSys ? ', td.system_id, s.name AS system_name' : '');
    $selJoin = $tdHasSys ? ' LEFT JOIN product_systems s ON s.id = td.system_id' : '';
    $ds = $pdo->prepare("SELECT $selCols FROM trade_discounts td$selJoin WHERE td.client_id = ? ORDER BY td.product_id");
    $ds->execute([$clientId]);
    $tradeDiscounts = $ds->fetchAll(PDO::FETCH_ASSOC);

    try {
        $as = $pdo->prepare('SELECT * FROM trade_discount_audit WHERE client_id = ? ORDER BY changed_at DESC, id DESC LIMIT 30');
        $as->execute([$clientId]);
        $tdAudit = $as->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { /* no audit table */ }
}
$discountCount = count($tradeDiscounts);

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$isActive  = (int) $acc['active'] === 1;
$hasPortal = count($logins) > 0;

$activeNav = 'trade-accounts';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $acc['company_name']) ?> &middot; Trade Account</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .ta-badge { display:inline-block; margin-left:0.4rem; padding:0.0625rem 0.5rem; font-size:0.6875rem; font-weight:700; border-radius:999px; text-transform:uppercase; letter-spacing:0.04em; }
        .ta-badge.inactive { background:var(--bg-subtle-2); color:var(--text-faint); }
        .ta-badge.noportal { background:#fef3c7; color:#92400e; }
        .ta-badge.portal { background:#d1fae5; color:#065f46; }
        .ta-badge.you { background:#1f3b5b; color:#fff; }
        .ta-form-grid { display:grid; gap:0.75rem 1rem; grid-template-columns:1fr 1fr; }
        @media (max-width:720px){ .ta-form-grid { grid-template-columns:1fr; } }
        .ta-form-grid .full { grid-column:1 / -1; }
        .ta-form-grid label { display:block; font-size:0.75rem; font-weight:600; color:var(--text-faint); text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.25rem; }
        .ta-form-grid input { width:100%; box-sizing:border-box; padding:0.5rem 0.7rem; border:1px solid var(--border-strong); border-radius:8px; font:inherit; background:var(--bg-input); }
        .ta-counts { display:flex; gap:1.25rem; flex-wrap:wrap; color:var(--text-muted); font-size:0.9375rem; margin:0 0 0.25rem; }
        .ta-counts b { color:var(--text-primary); }
        .login-card { border:1px solid var(--border); border-radius:10px; background:var(--bg-card); padding:0.75rem 0.9rem; margin:0 0 0.6rem; }
        .login-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; }
        .login-sub { color:var(--text-faint); font-size:0.8125rem; margin-top:0.15rem; }
        .login-meta { color:var(--text-faint); font-size:0.8125rem; white-space:nowrap; }
        .login-actions { margin-top:0.5rem; }
        .login-actions > summary { cursor:pointer; font-size:0.8125rem; font-weight:600; color:var(--link); }
        .login-actions-body { display:flex; flex-direction:column; gap:0.55rem; margin-top:0.6rem; padding-top:0.6rem; border-top:1px solid var(--border); }
        .action-row { display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap; }
        .action-row input { padding:0.35rem 0.5rem; border:1px solid var(--border-strong); border-radius:6px; font:inherit; font-size:0.85rem; background:var(--bg-input); }
        .action-row .lbl { font-size:0.75rem; color:var(--text-faint); font-weight:600; min-width:6.5rem; }
        .btn-sm { font-size:0.8125rem; padding:0.3rem 0.7rem; }
        .protected { color:var(--text-faint); font-size:0.8125rem; font-style:italic; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <?= e((string) $acc['company_name']) ?>
                    <?php if ($clientId === $myClient): ?><span class="ta-badge you">You</span><?php endif; ?>
                    <?php if (!$isActive): ?><span class="ta-badge inactive">Inactive</span><?php endif; ?>
                    <?php if ($hasPortal): ?><span class="ta-badge portal">Portal</span><?php else: ?><span class="ta-badge noportal">No portal</span><?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <a href="/master-admin/trade-accounts.php">&larr; Trade Accounts</a>
                    <?php if ($hasCreated && !empty($acc['created_at'])): ?>
                        &middot; account since <?= e(date('j M Y', strtotime((string) $acc['created_at']))) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0"
                      <?php if ($isActive): ?>data-confirm="Deactivate <?= e((string) $acc['company_name']) ?>? Their logins won't be able to sign in until you re-activate."<?php endif; ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="active">
                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                    <input type="hidden" name="to" value="<?= $isActive ? 0 : 1 ?>">
                    <?php if ($clientId === $myClient && $isActive): ?>
                        <button type="button" class="btn btn-secondary" disabled title="You can't deactivate your own account">Deactivate</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-<?= $isActive ? 'secondary' : 'primary' ?>"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <div class="ta-counts">
            <span><b><?= count($logins) ?></b> login<?= count($logins) === 1 ? '' : 's' ?></span>
            <span><b><?= number_format($productCount) ?></b> products</span>
            <span><b><?= number_format($discountCount) ?></b> discount<?= $discountCount === 1 ? '' : 's' ?> set</span>
        </div>

        <!-- Business / contact profile -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">Account details</h2>
            <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="profile">
                <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                <div class="ta-form-grid">
                    <div class="full">
                        <label for="company_name">Company name *</label>
                        <input id="company_name" name="company_name" type="text" required maxlength="150" value="<?= e((string) $acc['company_name']) ?>">
                    </div>
                    <div>
                        <label for="contact_name">Contact name</label>
                        <input id="contact_name" name="contact_name" type="text" maxlength="150" value="<?= e((string) ($acc['contact_name'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" maxlength="190" value="<?= e((string) ($acc['email'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" maxlength="60" value="<?= e((string) ($acc['phone'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="vat_number">VAT number</label>
                        <input id="vat_number" name="vat_number" type="text" maxlength="40" value="<?= e((string) ($acc['vat_number'] ?? '')) ?>">
                    </div>
                    <div class="full">
                        <label for="address1">Address line 1</label>
                        <input id="address1" name="address1" type="text" maxlength="150" value="<?= e((string) ($acc['address1'] ?? '')) ?>">
                    </div>
                    <div class="full">
                        <label for="address2">Address line 2</label>
                        <input id="address2" name="address2" type="text" maxlength="150" value="<?= e((string) ($acc['address2'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="town">Town / city</label>
                        <input id="town" name="town" type="text" maxlength="100" value="<?= e((string) ($acc['town'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="county">County</label>
                        <input id="county" name="county" type="text" maxlength="100" value="<?= e((string) ($acc['county'] ?? '')) ?>">
                    </div>
                    <div>
                        <label for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" type="text" maxlength="20" value="<?= e((string) ($acc['postcode'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-actions" style="margin-top:1rem">
                    <button type="submit" class="btn btn-primary">Save details</button>
                </div>
            </form>
        </section>

        <!-- Logins — view + manage -->
        <section class="section" id="logins">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin:0 0 0.6rem">
                <h2 class="section-title" style="margin:0">Logins</h2>
            </div>

            <?php if (!$logins): ?>
                <p style="color:var(--text-faint);margin:0 0 0.75rem">
                    No portal login yet &mdash; this is a <strong>no-portal</strong> account (a one-off you invoice/ship
                    to). Add a login below to give them portal access.
                </p>
            <?php else: foreach ($logins as $u):
                $isSuper   = (int) $u['is_super_admin'] === 1;
                $unverified = ($u['email'] ?? '') !== '' && empty($u['email_verified_at']);
            ?>
                <div class="login-card">
                    <div class="login-head">
                        <div>
                            <strong><?= e((string) ($u['full_name'] ?? '') ?: ($u['email'] ?: $u['username'] ?? '(no name)')) ?></strong>
                            <?php if ($isSuper): ?><span class="ta-badge you">Super</span><?php endif; ?>
                            <?php if ((int) $u['active'] !== 1): ?><span class="ta-badge inactive">Inactive</span><?php endif; ?>
                            <?php if ($unverified): ?><span class="ta-badge noportal">Unverified</span><?php endif; ?>
                            <div class="login-sub">
                                <?= e((string) ($u['email'] ?: $u['username'] ?? '')) ?>
                                &middot; <span style="text-transform:capitalize"><?= e((string) ($u['role'] ?? '')) ?></span>
                            </div>
                        </div>
                        <div class="login-meta">
                            <?= !empty($u['last_login_at']) ? 'last login ' . e(date('j M Y', strtotime((string) $u['last_login_at']))) : 'never signed in' ?>
                        </div>
                    </div>

                    <?php if ($isSuper): ?>
                        <div style="margin-top:0.4rem"><span class="protected">Protected &mdash; manage from the owner's own account settings.</span></div>
                    <?php else: ?>
                        <details class="login-actions">
                            <summary>Manage this login</summary>
                            <div class="login-actions-body">
                                <!-- Send reset link -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_reset_link">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <span class="lbl">Password</span>
                                    <button type="submit" class="btn btn-secondary btn-sm"
                                            <?= ($u['email'] ?? '') === '' ? 'disabled title="No email on this login"' : '' ?>>Send reset link</button>
                                </form>
                                <!-- Set a temporary password -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_set_password">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <span class="lbl">or set one</span>
                                    <input type="text" name="new_password" minlength="8" placeholder="temporary password (8+)" autocomplete="off" style="min-width:14rem">
                                    <button type="submit" class="btn btn-secondary btn-sm">Set password</button>
                                </form>
                                <!-- Change login (email / username) -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_change_login">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <span class="lbl">Login</span>
                                    <input type="email" name="email" value="<?= e((string) ($u['email'] ?? '')) ?>" placeholder="email" style="min-width:13rem">
                                    <input type="text" name="username" value="<?= e((string) ($u['username'] ?? '')) ?>" placeholder="username (optional)" style="min-width:10rem">
                                    <button type="submit" class="btn btn-secondary btn-sm">Save login</button>
                                </form>
                                <!-- Verification (only when an email is unverified) -->
                                <?php if ($unverified): ?>
                                    <div class="action-row">
                                        <span class="lbl">Verification</span>
                                        <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="user_resend_verification">
                                            <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Resend confirmation</button>
                                        </form>
                                        <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="user_mark_verified">
                                            <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Mark verified</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <!-- Activate / deactivate -->
                                <form class="action-row" method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>"
                                      <?php if ((int) $u['active'] === 1): ?>data-confirm="Deactivate this login? They won't be able to sign in until re-activated."<?php endif; ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_action" value="user_toggle_active">
                                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="to" value="<?= (int) $u['active'] === 1 ? 0 : 1 ?>">
                                    <span class="lbl">Access</span>
                                    <?php if ((int) $u['id'] === (int) ($user['user_id'] ?? 0) && (int) $u['active'] === 1): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="You can't deactivate your own login">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-secondary btn-sm"><?= (int) $u['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>

            <!-- Add a login -->
            <details style="margin-top:0.5rem">
                <summary style="cursor:pointer;font-weight:600;color:var(--link)">+ Add a login</summary>
                <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin-top:0.6rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="user_add">
                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                    <div class="action-row">
                        <input type="text" name="full_name" placeholder="Full name" required style="min-width:12rem">
                        <input type="email" name="email" placeholder="Email" required style="min-width:14rem">
                        <input type="text" name="new_password" minlength="8" placeholder="Initial password (8+)" autocomplete="off" required style="min-width:12rem">
                        <button type="submit" class="btn btn-primary btn-sm">Add login</button>
                    </div>
                    <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.4rem 0 0">
                        Created as an <strong>admin</strong> login, already verified. Then use <em>Send reset link</em>
                        so they can set their own password.
                    </p>
                </form>
            </details>
        </section>

        <!-- Discounts — buying discount off our trade price, per product / band -->
        <section class="section" id="discounts">
            <h2 class="section-title" style="margin:0 0 0.4rem">Discounts</h2>
            <p style="color:var(--text-faint);font-size:0.875rem;margin:0 0 0.75rem;max-width:70ch">
                The buying discount this account gets off <strong>our trade price</strong>, per <strong>product</strong> and
                optional <strong>material group</strong> (band; <em>All</em> = every band). Add as many rows as you need.
            </p>

            <?php if (!$tdReady): ?>
                <div class="alert alert-error" role="alert">
                    The discounts table isn't set up yet — run
                    <a href="/migrate_trade_discounts.php"><code>/migrate_trade_discounts.php</code></a> (super-admin), then reload.
                </div>
            <?php else: ?>
                <!-- Add / update a discount -->
                <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0 0 1rem">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="td_add">
                    <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                    <div class="action-row" style="align-items:flex-end">
                        <div>
                            <div class="lbl" style="margin-bottom:0.2rem">Discount %</div>
                            <input type="number" name="discount_percent" step="0.01" min="0" max="100" required placeholder="25" style="width:6rem">
                        </div>
                        <div>
                            <div class="lbl" style="margin-bottom:0.2rem">Product</div>
                            <select id="td-product" name="product_id" required style="min-width:15rem;padding:0.35rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);font:inherit">
                                <option value="">— choose a product —</option>
                                <?php foreach ($accProducts as $pid => $pname): ?>
                                    <option value="<?= (int) $pid ?>"><?= e((string) $pname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($tdHasSys): ?>
                        <div>
                            <div class="lbl" style="margin-bottom:0.2rem">System</div>
                            <select id="td-system" name="system_id" style="min-width:10rem;padding:0.35rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);font:inherit">
                                <option value="">All</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div class="lbl" style="margin-bottom:0.2rem">Material Group</div>
                            <select id="td-band" name="band_code" style="min-width:10rem;padding:0.35rem 0.5rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);font:inherit">
                                <option value="">All</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Add discount</button>
                    </div>
                    <?php if (!$accProducts): ?>
                        <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.4rem 0 0">This account has no products yet — seed/push its catalogue first.</p>
                    <?php endif; ?>
                </form>

                <?php if ($tradeDiscounts): ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th style="text-align:right">Discount %</th><th>Product</th><?php if ($tdHasSys): ?><th>System</th><?php endif; ?><th>Material Group</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($tradeDiscounts as $d):
                                    $pn = $accProducts[(int) $d['product_id']] ?? ('#' . (int) $d['product_id']);
                                    $bg = ($d['band_code'] ?? '') === '' ? 'All' : (string) $d['band_code'];
                                    $sy = ($d['system_name'] ?? '') !== '' ? (string) $d['system_name'] : 'All';
                                ?>
                                    <tr>
                                        <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((float) $d['discount_percent'], 2) ?></td>
                                        <td><?= e((string) $pn) ?></td>
                                        <?php if ($tdHasSys): ?><td><?= e($sy) ?></td><?php endif; ?>
                                        <td><?= e($bg) ?></td>
                                        <td style="text-align:right">
                                            <form method="post" action="/master-admin/trade-account.php?id=<?= (int) $clientId ?>" style="margin:0;display:inline"
                                                  data-confirm="Remove the <?= e(number_format((float) $d['discount_percent'], 2)) ?>% discount on <?= e((string) $pn) ?> (<?= e($bg) ?>)?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_action" value="td_delete">
                                                <input type="hidden" name="id" value="<?= (int) $clientId ?>">
                                                <input type="hidden" name="td_id" value="<?= (int) $d['id'] ?>">
                                                <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-faint);margin:0">No discounts set for this account yet.</p>
                <?php endif; ?>

                <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.75rem 0 0">
                    <strong>Note:</strong> recording discounts here doesn't change any live prices yet — the pricing engine
                    starts applying them in the next update (kept separate so nothing moves unexpectedly).
                </p>

                <?php if ($tdAudit): ?>
                    <details style="margin-top:0.75rem">
                        <summary style="cursor:pointer;font-weight:600;color:var(--link);font-size:0.875rem">Change history</summary>
                        <div class="table-wrap" style="margin-top:0.5rem">
                            <table class="table">
                                <thead><tr><th>When</th><th>Change</th><th>Product</th><?php if ($tdHasSys): ?><th>System</th><?php endif; ?><th>Group</th><th>By</th></tr></thead>
                                <tbody>
                                    <?php foreach ($tdAudit as $a):
                                        $ba = ($a['band_code'] ?? '') === '' ? 'All' : (string) $a['band_code'];
                                        $sy = ($a['system_name'] ?? '') !== '' ? (string) $a['system_name'] : 'All';
                                        $change = $a['action'] === 'delete'
                                            ? ('removed ' . number_format((float) ($a['old_pct'] ?? 0), 2) . '%')
                                            : ($a['action'] === 'update'
                                                ? (number_format((float) ($a['old_pct'] ?? 0), 2) . '% → ' . number_format((float) ($a['new_pct'] ?? 0), 2) . '%')
                                                : ('set ' . number_format((float) ($a['new_pct'] ?? 0), 2) . '%'));
                                    ?>
                                        <tr>
                                            <td style="white-space:nowrap"><?= e(date('j M Y H:i', strtotime((string) $a['changed_at']))) ?></td>
                                            <td><?= e($change) ?></td>
                                            <td><?= e((string) ($a['product_name'] ?? '')) ?></td>
                                            <?php if ($tdHasSys): ?><td><?= e($sy) ?></td><?php endif; ?>
                                            <td><?= e($ba) ?></td>
                                            <td><?= e((string) ($a['changed_by_name'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php if ($tdReady): ?>
<script>
(function () {
    var PRODUCT_BANDS   = <?= json_encode($productBands, JSON_UNESCAPED_UNICODE) ?>;
    var PRODUCT_SYSTEMS = <?= json_encode($productSystems, JSON_UNESCAPED_UNICODE) ?>;
    var prod = document.getElementById('td-product');
    var band = document.getElementById('td-band');
    var sys  = document.getElementById('td-system');
    if (!prod) return;
    prod.addEventListener('change', function () {
        if (band) {
            var bands = PRODUCT_BANDS[prod.value] || [];
            band.innerHTML = '';
            var ab = document.createElement('option'); ab.value = ''; ab.textContent = 'All'; band.appendChild(ab);
            bands.forEach(function (b) { var o = document.createElement('option'); o.value = b; o.textContent = b; band.appendChild(o); });
        }
        if (sys) {
            var systems = PRODUCT_SYSTEMS[prod.value] || [];
            sys.innerHTML = '';
            var asy = document.createElement('option'); asy.value = ''; asy.textContent = 'All'; sys.appendChild(asy);
            systems.forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sys.appendChild(o); });
        }
    });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
