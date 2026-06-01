<?php
declare(strict_types=1);

// Quote-builder is reached via the "+ New quote" button (→ new.php)
// or by editing a specific quote (→ edit.php?id=N). The bare
// directory has no canonical landing — bounce to the Order history
// list (the merged quotes+orders page).

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

header('Location: /orders/index.php');
exit;
