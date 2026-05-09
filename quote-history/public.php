<?php
declare(strict_types=1);

// Customer-facing read-only quote view. Stubbed until the new public flow
// is built (Phase 3.4+). Returns a simple "coming soon" page so an old
// shared link doesn't 500.

require __DIR__ . '/../bootstrap.php';

http_response_code(503);
?><!doctype html>
<meta charset="utf-8">
<title>Coming soon &middot; YourBlinds</title>
<style>
  body { font: 16px/1.5 system-ui, sans-serif; max-width: 480px;
         margin: 4rem auto; padding: 0 1rem; text-align: center; color: #111; }
</style>
<h1>Coming soon</h1>
<p>This quote view is being rebuilt. Please contact your supplier directly for now.</p>
