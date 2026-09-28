<?php
declare(strict_types=1);

/**
 * YourBlinds' OWN legal pages — the software company's licence (EULA) and
 * privacy policy, as opposed to each tenant's terms (legal/view.php).
 *
 * Needed for Intuit's production-keys application (it asks for public EULA +
 * privacy-policy URLs) and for anyone signing up. Public, no login.
 *
 *   /legal/licence.php   — End-user licence agreement / terms of service
 *   /legal/privacy.php   — Privacy policy
 *
 * Who runs the platform lives HERE, once, so a change of company or address
 * is a one-line edit. Bump PL_UPDATED whenever either document's wording
 * changes.
 *
 * NB: drafted plainly for a solicitor to review — not legal advice.
 */

const PL_BRAND     = 'YourBlinds';
const PL_COMPANY   = 'Beverley Blinds Ltd';
const PL_REG_NO    = '6058421';
const PL_ADDRESS   = 'Unit 2, Brindley Road, Exhall CV7 9EP';
const PL_EMAIL     = 'hello@yourblinds.uk';
const PL_UPDATED   = '28 September 2026';

/** Open the shared page shell (title, styles, header). */
function pl_head(string $title, string $lede): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    ?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($title) ?> &middot; <?= $e(PL_BRAND) ?></title>
<meta name="description" content="<?= $e($lede) ?>">
<style>
  :root { color-scheme: light dark; --bg:#f3f4f6; --card:#fff; --ink:#1f2937; --soft:#4b5563; --faint:#6b7280; --line:#e5e7eb; --brand:#1f3b5b; --link:#1d4ed8; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#0f172a; --card:#111827; --ink:#e5e7eb; --soft:#cbd5e1; --faint:#94a3b8; --line:#1f2937; --brand:#93c5fd; --link:#93c5fd; }
  }
  body { margin:0; background:var(--bg); color:var(--ink);
         font:15px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  .wrap { max-width:820px; margin:0 auto; padding:32px 16px 64px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:32px 36px; }
  .brand { font-size:1.35rem; font-weight:800; color:var(--brand); margin:0 0 2px; text-decoration:none; display:inline-block; }
  h1 { font-size:1.6rem; margin:.4rem 0 .25rem; line-height:1.25; }
  .meta { color:var(--faint); font-size:.85rem; margin:0 0 1.25rem; }
  .lede { color:var(--soft); font-size:1rem; }
  h2 { font-size:1.08rem; margin:1.9rem 0 .4rem; }
  h3 { font-size:.98rem; margin:1.2rem 0 .3rem; }
  p, li { color:var(--soft); }
  li { margin:.25rem 0; }
  a { color:var(--link); }
  table { width:100%; border-collapse:collapse; font-size:.9rem; margin:.5rem 0 1rem; }
  th, td { text-align:left; vertical-align:top; padding:.45rem .5rem; border-bottom:1px solid var(--line); color:var(--soft); }
  th { color:var(--ink); font-weight:600; }
  .tablewrap { overflow-x:auto; }
  .foot { margin-top:24px; color:var(--faint); font-size:.82rem; text-align:center; }
  .foot a { color:var(--faint); }
  @media print { body { background:#fff; color:#000; } .card { border:none; padding:0; } .foot { display:none; } }
  @media (max-width:600px) { .card { padding:22px 18px; } h1 { font-size:1.35rem; } }
</style>
</head>
<body>
<div class="wrap">
  <main class="card">
    <a class="brand" href="/welcome.php"><?= $e(PL_BRAND) ?></a>
    <h1><?= $e($title) ?></h1>
    <p class="meta">Last updated <?= $e(PL_UPDATED) ?></p>
    <p class="lede"><?= $e($lede) ?></p>
<?php
}

/** Close the shell, with links to both documents. */
function pl_foot(): void
{
    ?>
  </main>
  <div class="foot">
    <a href="/legal/licence.php">Licence agreement</a> &middot;
    <a href="/legal/privacy.php">Privacy policy</a> &middot;
    <a href="mailto:<?= htmlspecialchars(PL_EMAIL, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(PL_EMAIL, ENT_QUOTES, 'UTF-8') ?></a>
  </div>
</div>
</body>
</html>
<?php
}
