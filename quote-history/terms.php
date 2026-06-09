<?php
declare(strict_types=1);

/**
 * Public Terms & Conditions / Privacy Policy page for a quote.
 *
 * Reached from the accept checkbox / footer link on public.php. Same token
 * auth as the quote itself (the URL token is the auth). Renders the trade
 * business's T&Cs + Privacy Policy, personalised via {{tokens}}, on a clean
 * mobile-friendly page so they're readable without scrolling through the
 * quote. Opens in a new tab from the quote.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';        // for e()
require __DIR__ . '/../_partials/legal_text.php';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{40,128}$/i', $token)) {
    http_response_code(404);
    exit('Not found.');
}

$qStmt = db()->prepare(
    'SELECT q.quote_number, q.end_customer_name, q.client_id,
            c.company_name AS trade_company_name,
            c.address1     AS trade_addr1,
            c.address2     AS trade_addr2,
            c.town         AS trade_town,
            c.county       AS trade_county,
            c.postcode     AS trade_postcode,
            c.email        AS trade_email,
            c.phone        AS trade_phone
       FROM quotes q
       JOIN clients c ON c.id = q.client_id
      WHERE q.public_token = ?
      LIMIT 1'
);
$qStmt->execute([$token]);
$quote = $qStmt->fetch();
if (!$quote) {
    http_response_code(404);
    exit('Not found.');
}

// Legal text (guarded — columns may not exist on an un-migrated schema).
// NULL / no row = never configured → standard template (live by default).
$tcText = $ppText = '';
try {
    $lStmt = db()->prepare(
        'SELECT terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1'
    );
    $lStmt->execute([(int) $quote['client_id']]);
    $lRow  = $lStmt->fetch();
    $tcText = trim(legal_effective_terms($lRow ? ($lRow['terms_conditions'] ?? null) : null));
    $ppText = trim(legal_effective_privacy($lRow ? ($lRow['privacy_policy'] ?? null) : null));
} catch (Throwable $e) { /* not migrated — show nothing */ }

$company = (string) ($quote['trade_company_name'] ?? '');
$backUrl = '/quote-history/public.php?token=' . urlencode($token);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($company) ?> &mdash; Terms &amp; Conditions</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
               color: #1f2937; line-height: 1.6; margin: 0; background: #f4f7fb; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 1.25rem 1.125rem 3rem; }
        .back { display: inline-block; margin-bottom: 1rem; color: #2563eb;
                text-decoration: none; font-size: 0.9375rem; }
        h1 { font-size: 1.25rem; margin: 0 0 0.25rem; }
        .sub { color: #6b7280; font-size: 0.875rem; margin: 0 0 1.25rem; }
        .doc { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
               padding: 1.125rem 1.25rem; margin-bottom: 1.25rem; }
        .doc h2 { font-size: 1.0625rem; margin: 0 0 0.75rem; color: #111827; }
        .doc .body { white-space: pre-line; font-size: 0.9375rem; color: #374151; }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="<?= e($backUrl) ?>">&larr; Back to your quote</a>
    <h1><?= e($company) ?></h1>
    <p class="sub">Quote <?= e((string) $quote['quote_number']) ?></p>

    <?php if ($tcText !== ''): ?>
        <div class="doc" id="terms">
            <h2>Terms &amp; Conditions</h2>
            <div class="body"><?= e(legal_render_tokens($tcText, $quote)) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($ppText !== ''): ?>
        <div class="doc" id="privacy">
            <h2>Privacy Policy</h2>
            <div class="body"><?= e(legal_render_tokens($ppText, $quote)) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($tcText === '' && $ppText === ''): ?>
        <div class="doc"><p style="margin:0">No terms have been published for this quote.</p></div>
    <?php endif; ?>
</div>
</body>
</html>
