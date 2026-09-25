<?php
declare(strict_types=1);

/**
 * Public legal-document viewer.
 *
 * Renders a tenant's Terms & Conditions (trade or retail) or Privacy Policy as a
 * plain, printable web page, so quotes/invoices can LINK to the current terms
 * instead of printing the whole thing on every PDF. No login — these are public
 * documents; the tenant's own company details fill the {{tokens}}.
 *
 *   /legal/view.php?c=<clientId>&doc=trade|retail|privacy
 */

require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../_partials/legal_text.php';

$pdo = db();

$clientId = (int) ($_GET['c'] ?? 0);
$doc      = (string) ($_GET['doc'] ?? 'retail');
if (!in_array($doc, ['trade', 'retail', 'privacy'], true)) $doc = 'retail';

// The tenant whose terms these are.
$company = null;
if ($clientId > 0) {
    $cs = $pdo->prepare(
        'SELECT company_name, address1, address2, town, county, postcode, email, phone
           FROM clients WHERE id = ? LIMIT 1'
    );
    $cs->execute([$clientId]);
    $company = $cs->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($company === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Document not found. If you followed a link on an old quote, please ask for an up-to-date copy.";
    exit;
}

// Stored overrides (NULL = use the tokenised default). trade column is optional
// (migrate_trade_terms.php) — degrade gracefully if it isn't there yet.
$row = ['terms_conditions' => null, 'trade_terms_conditions' => null, 'privacy_policy' => null];
try {
    $s = $pdo->prepare('SELECT terms_conditions, trade_terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1');
    $s->execute([$clientId]);
    $row = $s->fetch(PDO::FETCH_ASSOC) ?: $row;
} catch (Throwable $e) {
    try {
        $s = $pdo->prepare('SELECT terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1');
        $s->execute([$clientId]);
        $row = array_merge($row, $s->fetch(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e2) { /* table missing — defaults apply */ }
}

if ($doc === 'trade') {
    $title = 'Terms & Conditions (Trade)';
    $text  = legal_effective_trade_terms($row['trade_terms_conditions'] ?? null);
} elseif ($doc === 'privacy') {
    $title = 'Privacy Policy';
    $text  = legal_effective_privacy($row['privacy_policy'] ?? null);
} else {
    $title = 'Terms & Conditions';
    $text  = legal_effective_terms($row['terms_conditions'] ?? null);
}

// Fill company {{tokens}} (no quote context on a public page — quote-level tokens
// resolve blank, which the default documents never rely on).
$tokenCtx = [
    'trade_company_name' => (string) $company['company_name'],
    'trade_addr1'        => (string) $company['address1'],
    'trade_addr2'        => (string) $company['address2'],
    'trade_town'         => (string) $company['town'],
    'trade_county'       => (string) $company['county'],
    'trade_postcode'     => (string) $company['postcode'],
    'trade_email'        => (string) $company['email'],
    'trade_phone'        => (string) $company['phone'],
];
// Unsigned (old or hand-typed) link: refuse it, exactly like an unknown id,
// so stepping through ?c= ids reveals nothing — not even which accounts exist
// or their company names. Every link the app prints is signed (k=).
if (!hash_equals(legal_link_key($clientId), (string) ($_GET['k'] ?? ''))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Document not found. If you followed a link on an old quote, please ask for an up-to-date copy.";
    exit;
}
$rendered = legal_render_tokens((string) $text, $tokenCtx);

header('Content-Type: text/html; charset=utf-8');
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($title) ?> &middot; <?= $e($company['company_name']) ?></title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; background: #f3f4f6; color: #1f2937;
         font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  .wrap { max-width: 820px; margin: 0 auto; padding: 32px 20px 64px; }
  .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
          padding: 32px 36px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
  .co { font-size: 1.35rem; font-weight: 700; color: #1f3b5b; margin: 0 0 2px; }
  h1 { font-size: 1.05rem; font-weight: 600; color: #6b7280; margin: 0 0 20px;
       text-transform: uppercase; letter-spacing: .04em; }
  .doc { white-space: pre-wrap; font-size: 0.95rem; color: #374151; }
  .foot { margin-top: 24px; color: #9ca3af; font-size: .8rem; text-align: center; }
  @media print { body { background: #fff; } .card { border: none; box-shadow: none; padding: 0; } }
  @media (max-width: 600px) { .card { padding: 22px 18px; } }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="co"><?= $e($company['company_name']) ?></div>
      <h1><?= $e($title) ?></h1>
      <div class="doc"><?= $e($rendered) ?></div>
    </div>
    <div class="foot">Provided via YourBlinds</div>
  </div>
</body>
</html>
