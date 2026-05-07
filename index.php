<?php
declare(strict_types=1);

// Bare-domain landing — send visitors to the login page. Trade users and
// admins both start their journey there; the post-login redirect routes
// them to the right dashboard.
header('Location: /auth/login.php', true, 302);
exit;
