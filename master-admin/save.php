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

// We can't trust the submitted client IDs alone (an unchecked checkbox
// doesn't post anything), so we pull every client and decide on/off based
// on whether $_POST['maps'][id] is set.
$pdo     = db();
$clients = $pdo->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_COLUMN);
$mapsIn  = is_array($_POST['maps'] ?? null) ? $_POST['maps'] : [];

// Ensure every client has a settings row, then update.
$ensure  = $pdo->prepare(
    'INSERT IGNORE INTO client_settings (client_id) VALUES (?)'
);
$update  = $pdo->prepare(
    'UPDATE client_settings SET feature_maps = ? WHERE client_id = ?'
);

$pdo->beginTransaction();
try {
    foreach ($clients as $cid) {
        $cid = (int) $cid;
        $on  = isset($mapsIn[$cid]) ? 1 : 0;
        $ensure->execute([$cid]);
        $update->execute([$on, $cid]);
    }
    $pdo->commit();
    $_SESSION['flash_success'] = 'Feature flags saved.';
} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Could not save feature flags. Please try again.';
}

header('Location: /master-admin/index.php');
exit;
