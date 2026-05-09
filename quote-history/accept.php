<?php
declare(strict_types=1);

// Customer-facing accept handler. Stubbed until the public quote view
// (public.php) and PDF email flow are rebuilt in a later phase. Returns
// a minimal "coming soon" page so a stale link doesn't 500.

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
<p>The customer-facing accept flow is being rebuilt. Please contact your supplier directly for now.</p>
