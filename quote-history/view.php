<?php
declare(strict_types=1);

// quote-history/view.php was the old read-only-display companion to
// quote-builder/edit.php. The new editor renders read-only itself when
// the quote is in any non-draft state, so this just bounces back there.

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /orders/index.php');
    exit;
}
header('Location: /quote-builder/edit.php?id=' . $id);
exit;
