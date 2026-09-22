<?php
declare(strict_types=1);

/**
 * Public "What's it all about?" landing page — the lead-gen funnel's pitch.
 *
 * Reached from the open InstaPrice page (the "What's it all about?" link) and
 * from the signup gate (any attempt to save/send a quote or add a customer as
 * an anonymous visitor lands here, with ?from=... for a contextual headline).
 *
 * Shows the service story + the live tier ladder (Bronze/Silver/Gold, prices
 * pulled live from plan_pricing) and points at the existing self-signup.
 * No login required. Purely read-only.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/billing_helpers.php';
require_once __DIR__ . '/_partials/app_settings.php';

$loggedIn      = is_logged_in();
$signupsClosed = app_setting_on('signups_paused');
$vatPct        = defined('BILLING_VAT_PERCENT') ? (float) BILLING_VAT_PERCENT : 20.0;

// Live tier ladder (price + features merged from plan_pricing).
$plans = function_exists('billing_plans') ? billing_plans() : [];
uasort($plans, static fn ($a, $b) => (int) ($a['tier'] ?? 0) <=> (int) ($b['tier'] ?? 0));

// Contextual headline when the visitor arrived by hitting the signup gate.
$from = (string) ($_GET['from'] ?? '');
$gateLines = [
    'quote'    => 'Almost there — create your free account to save, price and send this quote.',
    'customer' => 'To add a customer and keep your work, create your free account.',
    'save'     => 'Create your free account to save what you\'ve started.',
];
$gateMsg = $gateLines[$from] ?? '';

/** Money for a tier: "Free" for £0, else "£N /mo". */
function wl_price(array $plan): string
{
    $p = (float) ($plan['price_gbp_monthly'] ?? 0);
    if ($p <= 0) return 'Free';
    // Whole pounds show without decimals; keep pennies only if present.
    $shown = (fmod($p, 1.0) === 0.0) ? number_format($p, 0) : number_format($p, 2);
    return '£' . $shown;
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>What's it all about? &middot; YourBlinds</title>
    <meta name="description" content="YourBlinds is the trade quoting &amp; ordering platform for blinds businesses — quote in seconds, run your calendar, customers and orders, and grow with Maps and Accounts. Free to start.">
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .wl-wrap { max-width: 1000px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }

        .wl-top { display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
        .wl-brand { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.01em;
            color: var(--brand, #1f3b5b); text-decoration: none; }
        .wl-brand .accent { color: var(--brand-accent, #2563eb); }
        [data-theme="dark"] .wl-brand { color: var(--text-primary); }
        .wl-top-links { display: flex; gap: 0.75rem; align-items: center; }
        .wl-top-links a { font-size: 0.95rem; text-decoration: none; color: var(--text-secondary); }
        .wl-top-links a:hover { color: var(--link); }

        .wl-gate { background: #fef3c7; border: 1px solid #fcd34d; color: #92600a;
            border-radius: 10px; padding: 0.8rem 1rem; font-weight: 600; margin-bottom: 1.5rem; }
        [data-theme="dark"] .wl-gate { background: rgba(250,204,21,0.12); border-color: rgba(250,204,21,0.35); color: #fbbf24; }

        .wl-hero { text-align: center; padding: 1rem 0 2.25rem; }
        .wl-hero h1 { font-size: clamp(1.8rem, 5vw, 2.8rem); line-height: 1.1;
            margin: 0 0 0.75rem; text-wrap: balance; color: var(--text-primary); }
        .wl-hero p { font-size: 1.125rem; color: var(--text-secondary);
            max-width: 60ch; margin: 0 auto 1.5rem; }
        .wl-cta-row { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
        .wl-btn { display: inline-block; border-radius: 10px; padding: 0.7rem 1.4rem;
            font-weight: 700; font-size: 1rem; text-decoration: none; border: 1px solid transparent; }
        .wl-btn-primary { background: var(--brand-accent, #2563eb); color: #fff; }
        .wl-btn-primary:hover { filter: brightness(1.05); }
        .wl-btn-ghost { background: transparent; color: var(--text-primary);
            border-color: var(--border-strong); }
        .wl-btn-ghost:hover { background: var(--bg-subtle); }

        .wl-why { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
            margin: 2.5rem 0 3rem; }
        @media (max-width: 760px) { .wl-why { grid-template-columns: 1fr; } }
        .wl-why-card { background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; padding: 1.1rem 1.2rem; }
        .wl-why-card h3 { margin: 0 0 0.35rem; font-size: 1.05rem; color: var(--text-primary); }
        .wl-why-card p { margin: 0; color: var(--text-secondary); font-size: 0.95rem; }

        .wl-section-title { text-align: center; font-size: 1.5rem; margin: 0 0 0.4rem; color: var(--text-primary); }
        .wl-section-sub { text-align: center; color: var(--text-faint); margin: 0 0 1.75rem; }

        .wl-tiers { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; align-items: stretch; }
        @media (max-width: 760px) { .wl-tiers { grid-template-columns: 1fr; } }
        .wl-tier { background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 14px; padding: 1.4rem 1.3rem; display: flex; flex-direction: column; }
        .wl-tier.is-featured { border-color: var(--brand-accent, #2563eb); box-shadow: var(--shadow-sm); }
        .wl-tier-name { font-size: 1.2rem; font-weight: 800; color: var(--text-primary); }
        .wl-tier-badge { display: inline-block; font-size: 0.68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em; color: #fff;
            background: var(--brand-accent, #2563eb); border-radius: 999px; padding: 0.12rem 0.5rem; margin-left: 0.4rem; }
        .wl-tier-price { margin: 0.6rem 0 0.1rem; font-size: 2rem; font-weight: 800; color: var(--text-primary); }
        .wl-tier-price .per { font-size: 0.9rem; font-weight: 600; color: var(--text-faint); }
        .wl-tier-vat { font-size: 0.8rem; color: var(--text-faint); min-height: 1.1rem; }
        .wl-tier-desc { color: var(--text-secondary); font-size: 0.95rem; margin: 0.8rem 0 1.1rem; flex: 1 1 auto; }
        .wl-tier .wl-btn { text-align: center; }

        .wl-foot { text-align: center; color: var(--text-faint); font-size: 0.9rem; margin-top: 2.5rem; }
        .wl-foot a { color: var(--link); }
    </style>
</head>
<body>
    <div class="wl-wrap">
        <div class="wl-top">
            <a class="wl-brand" href="/welcome.php">Your<span class="accent">Blinds</span></a>
            <div class="wl-top-links">
                <a href="/instaprice/index.php">Try InstaPrice</a>
                <?php if ($loggedIn): ?>
                    <a class="wl-btn wl-btn-ghost" href="/calendar/index.php">Go to my dashboard</a>
                <?php else: ?>
                    <a href="/auth/login.php">Sign in</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($gateMsg !== ''): ?>
            <div class="wl-gate"><?= e($gateMsg) ?></div>
        <?php endif; ?>

        <section class="wl-hero">
            <h1>The trade quoting &amp; ordering platform built around your service</h1>
            <p>
                Price a blind in seconds, turn it into a professional quote, and run your
                whole operation — calendar, customers, orders and products — in one place.
                Start free, upgrade only when you want the extras.
            </p>
            <div class="wl-cta-row">
                <?php if ($signupsClosed): ?>
                    <span class="wl-btn wl-btn-ghost">Sign-ups are paused just now</span>
                <?php elseif (!$loggedIn): ?>
                    <a class="wl-btn wl-btn-primary" href="/auth/signup.php">Start free &rarr;</a>
                <?php endif; ?>
                <a class="wl-btn wl-btn-ghost" href="/instaprice/index.php">Get a price now</a>
            </div>
        </section>

        <section class="wl-why">
            <div class="wl-why-card">
                <h3>Service second to none</h3>
                <p>Our forte isn't just software — it's the support behind it. Quality that keeps your customers coming back.</p>
            </div>
            <div class="wl-why-card">
                <h3>Quote in seconds</h3>
                <p>InstaPrice gives you a live sell price from real product rules — then converts straight into a full quote.</p>
            </div>
            <div class="wl-why-card">
                <h3>Everything in one place</h3>
                <p>Calendar, customers, orders, products, delivery and accounts — no more juggling spreadsheets.</p>
            </div>
        </section>

        <h2 class="wl-section-title">Simple, honest pricing</h2>
        <p class="wl-section-sub">One free tier, forever. Add features as you grow. Cancel any time.</p>

        <div class="wl-tiers">
            <?php foreach ($plans as $code => $plan):
                $isFeatured = ($code === 'silver');   // the popular middle tier
                $price      = wl_price($plan);
                $isFree     = ((float) ($plan['price_gbp_monthly'] ?? 0) <= 0);
            ?>
                <div class="wl-tier<?= $isFeatured ? ' is-featured' : '' ?>">
                    <div>
                        <span class="wl-tier-name"><?= e((string) ($plan['name'] ?? $code)) ?></span>
                        <?php if ($isFeatured): ?><span class="wl-tier-badge">Popular</span><?php endif; ?>
                    </div>
                    <div class="wl-tier-price">
                        <?= e($price) ?><?php if (!$isFree): ?><span class="per"> /month</span><?php endif; ?>
                    </div>
                    <div class="wl-tier-vat"><?= $isFree ? '' : '+ VAT' ?></div>
                    <p class="wl-tier-desc"><?= e((string) ($plan['description'] ?? '')) ?></p>
                    <?php if ($signupsClosed): ?>
                        <span class="wl-btn wl-btn-ghost">Sign-ups paused</span>
                    <?php elseif ($loggedIn): ?>
                        <a class="wl-btn wl-btn-ghost" href="/billing/index.php">Manage plan</a>
                    <?php elseif ($isFree): ?>
                        <a class="wl-btn wl-btn-primary" href="/auth/signup.php">Start free &rarr;</a>
                    <?php else: ?>
                        <a class="wl-btn wl-btn-ghost" href="/auth/signup.php">Start free, add later</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="wl-foot">
            Prices shown are per month<?= $vatPct > 0 ? ', excluding VAT' : '' ?>. The core platform is free forever.<br>
            <?php if (!$loggedIn): ?>
                Already with us? <a href="/auth/login.php">Sign in</a> &middot;
            <?php endif; ?>
            <a href="/instaprice/index.php">Try the price calculator</a>
        </p>
    </div>
</body>
</html>
