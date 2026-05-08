<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Location: /master-admin/index.php');
    exit;
}

csrf_check();

$flags = require __DIR__ . '/../_partials/feature_flags.php';

$pdo     = db();
$clients = $pdo->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_COLUMN);
$posted  = is_array($_POST['flags'] ?? null) ? $_POST['flags'] : [];

// Build dynamic UPDATE: SET col1 = ?, col2 = ? ... WHERE client_id = ?.
// Column names come from $flags (server-side allowlist) so no user input is
// interpolated into the SQL.
$cols      = array_keys($flags);
$setClause = implode(', ', array_map(static fn ($c) => "$c = ?", $cols));

$ensure = $pdo->prepare(
    'INSERT IGNORE INTO client_settings (client_id) VALUES (?)'
);
$update = $pdo->prepare(
    "UPDATE client_settings SET $setClause WHERE client_id = ?"
);

$pdo->beginTransaction();
try {
    foreach ($clients as $cid) {
        $cid = (int) $cid;
        $ensure->execute([$cid]);

        $values = [];
        foreach ($cols as $col) {
            $values[] = isset($posted[$col][$cid]) ? 1 : 0;
        }
        $values[] = $cid;
        $update->execute($values);
    }
    $pdo->commit();
    $_SESSION['flash_success'] = 'Feature flags saved.';
} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Could not save feature flags. Please try again.';
}

header('Location: /master-admin/index.php');
exit;
