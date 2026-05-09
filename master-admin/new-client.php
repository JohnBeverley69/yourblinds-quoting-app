<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

$user        = current_user();
$myClientId  = (int) $user['client_id']; // master admin's own client = template source

$f = [
    'company_name' => '',
    'admin_email'  => '',
    'admin_name'   => '',
    'seed'         => 1,
];
$error   = null;
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f['company_name'] = trim((string) ($_POST['company_name'] ?? ''));
    $f['admin_email']  = trim((string) ($_POST['admin_email']  ?? ''));
    $f['admin_name']   = trim((string) ($_POST['admin_name']   ?? ''));
    $f['seed']         = !empty($_POST['seed']) ? 1 : 0;
    $password          = (string) ($_POST['password'] ?? '');

    if ($f['company_name'] === '') {
        $error = 'Company name is required.';
    } elseif (strlen($f['company_name']) > 150) {
        $error = 'Company name is too long (max 150 chars).';
    } elseif (!filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid admin email is required.';
    } elseif ($f['admin_name'] === '') {
        $error = 'Admin user full name is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check email isn't already in client_users (unique).
        $check = db()->prepare('SELECT 1 FROM client_users WHERE email = ? LIMIT 1');
        $check->execute([$f['admin_email']]);
        if ($check->fetchColumn()) {
            $error = 'A user with that email already exists. Pick a different email.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // 1. Create the client.
                $pdo->prepare(
                    'INSERT INTO clients (company_name, active) VALUES (?, 1)'
                )->execute([$f['company_name']]);
                $newClientId = (int) $pdo->lastInsertId();

                // 2. Create the empty client_settings row so master admin
                //    feature toggles can flip it without create-on-write.
                $pdo->prepare(
                    'INSERT INTO client_settings (client_id) VALUES (?)'
                )->execute([$newClientId]);

                // 3. Create the admin user.
                $pdo->prepare(
                    'INSERT INTO client_users
                       (client_id, email, full_name, password_hash,
                        role, active, is_super_admin)
                     VALUES (?, ?, ?, ?, "admin", 1, 0)'
                )->execute([
                    $newClientId,
                    $f['admin_email'],
                    $f['admin_name'],
                    password_hash($password, PASSWORD_DEFAULT),
                ]);

                // 4. Optionally seed the catalogue from the master admin's client.
                if ($f['seed'] === 1) {
                    require_once __DIR__ . '/../_partials/seed_client_from_template.php';
                    $summary = seed_client_from_template($pdo, $myClientId, $newClientId);
                }

                $pdo->commit();

                $_SESSION['flash_success'] =
                    'Client "' . $f['company_name'] . '" created'
                    . ($summary
                        ? ' with seeded catalogue: '
                          . $summary['products']     . ' products, '
                          . $summary['fabrics']      . ' fabrics, '
                          . $summary['systems']      . ' systems, '
                          . $summary['extras']       . ' options, '
                          . $summary['choices']      . ' choices, '
                          . $summary['price_tables'] . ' price tables ('
                          . $summary['price_table_rows'] . ' cells), '
                          . $summary['width_table_rows'] . ' width-table rows.'
                        : ' (empty catalogue — no seed).');
                header('Location: /master-admin/index.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Could not create client: ' . $e->getMessage();
            }
        }
    }
}

// Quick read on the master admin's catalogue size — gives the user a sanity
// check on what "seed" will copy.
$sizeStmt = db()->prepare(
    'SELECT
        (SELECT COUNT(*) FROM products              WHERE client_id = ?) AS products,
        (SELECT COUNT(*) FROM product_options       WHERE client_id = ?) AS fabrics,
        (SELECT COUNT(*) FROM product_systems       WHERE client_id = ?) AS systems,
        (SELECT COUNT(*) FROM product_extras        WHERE client_id = ?) AS extras,
        (SELECT COUNT(*) FROM price_tables          WHERE client_id = ?) AS price_tables'
);
$sizeStmt->execute(array_fill(0, 5, $myClientId));
$mySizes = $sizeStmt->fetch();

$activeNav = 'master-admin';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New client &middot; Master Admin &middot; YourBlinds</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%; font: inherit; padding: 0.5625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
            box-sizing: border-box;
        }
        .toggle-stack {
            display: flex; flex-direction: column; gap: 0.625rem;
            margin: 1.25rem 0;
        }
        .toggle-stack label {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.9375rem; color: #111827; cursor: pointer;
            margin: 0; padding: 0;
        }
        .toggle-stack input[type="checkbox"] { width: 18px; height: 18px; }
        .toggle-stack small {
            color: #6b7280; font-size: 0.8125rem; margin-left: 0.375rem;
        }
        .seed-summary {
            background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
            padding: 0.75rem 1rem; margin: 0.5rem 0 1.25rem;
            font-size: 0.875rem; color: #4b5563;
        }
        .seed-summary strong { color: #111827; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">New client</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Back to Master Admin</a>
                </p>
            </div>
        </div>

        <?php if ($error !== null): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="section">
            <p style="color:#6b7280;font-size:0.9375rem;margin:0 0 1rem">
                Creates a new tenant (company) plus its first admin user, and
                optionally clones your master-admin catalogue across so the new
                client launches with sensible defaults they can then edit.
            </p>

            <form method="post" action="/master-admin/new-client.php" class="form" novalidate>
                <?= csrf_field() ?>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="company_name">Company name <span class="required">*</span></label>
                        <input id="company_name" name="company_name" type="text"
                               required maxlength="150" autofocus
                               value="<?= e((string) $f['company_name']) ?>"
                               placeholder="e.g. Bristol Blinds">
                    </div>
                </div>

                <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem">Initial admin user</h3>
                <p style="color:#6b7280;font-size:0.875rem;margin:0 0 0.75rem">
                    The first login for this client. They can add more users via Users once they're in.
                </p>
                <div class="form-row cols-2">
                    <div class="form-group">
                        <label for="admin_name">Full name <span class="required">*</span></label>
                        <input id="admin_name" name="admin_name" type="text"
                               required maxlength="150"
                               value="<?= e((string) $f['admin_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="admin_email">Email <span class="required">*</span></label>
                        <input id="admin_email" name="admin_email" type="email"
                               required maxlength="150"
                               value="<?= e((string) $f['admin_email']) ?>">
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label for="password">Initial password (8+ chars) <span class="required">*</span></label>
                        <input id="password" name="password" type="password"
                               required minlength="8" autocomplete="new-password">
                        <small style="color:#6b7280;font-size:0.8125rem">
                            Tell them in person, or send via your usual channel. They can change it via Settings once logged in.
                        </small>
                    </div>
                </div>

                <h3 style="margin:1.5rem 0 0.5rem;font-size:1rem">Catalogue seeding</h3>
                <div class="seed-summary">
                    Your master-admin catalogue currently has:
                    <strong><?= (int) $mySizes['products'] ?></strong> products,
                    <strong><?= (int) $mySizes['fabrics'] ?></strong> fabrics,
                    <strong><?= (int) $mySizes['systems'] ?></strong> systems,
                    <strong><?= (int) $mySizes['extras'] ?></strong> options,
                    <strong><?= (int) $mySizes['price_tables'] ?></strong> price tables.
                </div>

                <div class="toggle-stack">
                    <label for="seed">
                        <input type="checkbox" id="seed" name="seed" value="1"
                               <?= $f['seed'] === 1 ? 'checked' : '' ?>>
                        Seed catalogue from master admin
                        <small>uncheck for a blank tenant (rare — usually only for testing)</small>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create client</button>
                    <a href="/master-admin/index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
