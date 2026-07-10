<?php
declare(strict_types=1);

/**
 * Minimal "feature retired" notice page.
 *
 * Included by pages whose feature has been withdrawn but whose files are
 * kept on disk (so the route doesn't 404 and the change is easily
 * reversible). Set $retiredHeading / $retiredMessage before including to
 * customise the copy. Callers should set an HTTP status (e.g. 410) and
 * exit immediately after including this partial.
 */

$retiredHeading = $retiredHeading ?? 'Feature no longer available';
$retiredMessage = $retiredMessage ?? 'This feature has been retired.';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($retiredHeading, ENT_QUOTES) ?> &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('/app.css') : '/app.css' ?>">
</head>
<body>
<main class="app-main" style="max-width:640px;margin:4rem auto;padding:0 1.5rem;text-align:center">
    <h1 class="page-title"><?= htmlspecialchars($retiredHeading, ENT_QUOTES) ?></h1>
    <p class="page-subtitle" style="margin-top:0.75rem"><?= htmlspecialchars($retiredMessage, ENT_QUOTES) ?></p>
    <p style="margin-top:1.5rem"><a href="/calendar/index.php">&larr; Back to YourBlinds</a></p>
</main>
</body>
</html>
