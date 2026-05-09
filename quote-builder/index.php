<?php
declare(strict_types=1);

// Quote-builder is reached via "New Quote" (→ new.php) or by editing a
// specific quote (→ edit.php?id=N). The bare directory has no canonical
// landing — bounce to the history list.

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';

requireLogin();

header('Location: /quote-history/index.php');
exit;
