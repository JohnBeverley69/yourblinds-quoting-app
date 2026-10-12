<?php
// Loaded before every request the test web server answers (php -S … -d auto_prepend_file).
// Re-applies the test settings inside the request: Windows drops empty environment
// variables (DB_PASS='') on the way into a child process.
require_once __DIR__ . '/env.php';
yb_test_env_apply(yb_test_env());
