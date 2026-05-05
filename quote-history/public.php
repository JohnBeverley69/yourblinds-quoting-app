<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';  // for csrf_field / e()

// Public, no-login quote viewer. Looks up by quotes.public_token (high-entropy
// random hex), so the token IS the credential. Treat it as such — log nothing
// that exposes it, never echo it back into HTML attributes outside the form.

$token = (string) ($_GET['token'] ?? '');

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$tokenValid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;

$quote = null;
if ($tokenValid) {
    $stmt = db()->prepare(
        'SELECT q.*,
                c.company_name,
                c.email   AS client_email,
                c.phone   AS client_phone,
                c.address1 AS client_addr1,
                c.address2 AS client_addr2,
                c.town    AS client_town,
                c.county  AS client_county,
                c.postcode AS client_postcode,
                cs.vat_percent,
                cs.quote_footer
           FROM quotes q
           JOIN clients c       ON c.id        = q.client_id
           LEFT JOIN client_settings cs ON cs.client_id = q.client_id
          WHERE q.public_token = ?
          LIMIT 1'
    );
    $stmt->execute([$token]);
    $quote = $stmt->fetch() ?: null;
}

if (!$quote) {
    http_response_code(404);
}

$items = [];
if ($quote) {
    $istmt = db()->prepare(
        'SELECT qi.*, p.name AS product_name
           FROM quote_items qi
           LEFT JOIN products p ON p.id = qi.product_id
          WHERE qi.quote_id = ?
          ORDER BY qi.line_no, qi.id'
    );
    $istmt->execute([(int) $quote['id']]);
    $items = $istmt->fetchAll();
}

$now        = time();
$validUntil = $quote && !empty($quote['valid_until'])
    ? strtotime((string) $quote['valid_until'])
    : null;
$isExpired  = $validUntil !== null && $validUntil < strtotime('today');
$isAccepted = $quote
    && (((string) $quote['status']) === 'accepted'
        || !empty($quote['accepted_at']));
$isOrdered  = $quote && ((string) $quote['status']) === 'ordered';
$isRejected = $quote && ((string) $quote['status']) === 'rejected';
$isDraft    = $quote && ((string) $quote['status']) === 'draft';
$canAccept  = $quote && !$isAccepted && !$isExpired && !$isRejected && !$isOrdered && !$isDraft;

$money    = static fn ($n)         => '£' . number_format((float) $n, 2);
$fmtSize  = static fn (float $v)   => number_format($v, 1, '.', '');
$fmtDate  = static function (?string $dt): string {
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('j F Y', $ts) : '—';
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $quote ? 'Quote ' . e((string) $quote['quote_number']) : 'Quote not found' ?></title>
    <link rel="stylesheet" href="/app.css">
    <style>
        body { background: #f4f7fb; }
        .public-shell { max-width: 820px; margin: 0 auto; padding: 1.5rem; }
        .public-header {
            background: #1f3b5b; color: #fff;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem 1.75rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }
        .public-header .company {
            font-weight: 700; font-size: 1.25rem; letter-spacing: -0.01em;
        }
        .public-header .meta {
            font-size: 0.8125rem; color: rgba(255,255,255,0.75);
            text-align: right;
        }
        .public-body {
            background: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.06);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
        }
        .public-status {
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }
        .public-status.expired  { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
        .public-status.accepted { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
        .public-status.ordered  { background:#fefce8; color:#854d0e; border:1px solid #fef08a; }
        .public-status.rejected { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .public-status.draft    { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
        .public-status strong   { font-weight: 700; }

        .public-title { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.25rem; color: #111827; }
        .public-subtitle { color: #6b7280; margin: 0 0 1.5rem; font-size: 0.9375rem; }

        .public-accept {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            text-align: center;
        }
        .public-accept p { margin: 0 0 1rem; color: #166534; font-size: 0.9375rem; }
        .public-accept .btn-accept {
            display: inline-block; font: inherit; font-weight: 600;
            padding: 0.75rem 2rem; border: 0; border-radius: 8px;
            background: #15803d; color: #fff; cursor: pointer;
            transition: background 0.15s;
            font-size: 1rem;
        }
        .public-accept .btn-accept:hover { background: #14532d; }

        .public-footer {
            text-align: center;
            color: #9ca3af;
            font-size: 0.8125rem;
            padding: 1rem 0 2rem;
        }
        .public-footer .powered { color: #6b7280; }

        .pre-line { white-space: pre-line; }

        @media (max-width: 600px) {
            .public-shell  { padding: 0.75rem; }
            .public-header { padding: 1.25rem; flex-direction: column; align-items: flex-start; }
            .public-header .meta { text-align: left; }
            .public-body   { padding: 1.25rem; }
            .public-title  { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<?php if (!$quote): ?>
    <div class="public-shell">
        <div class="public-body" style="border-radius: 12px; text-align: center; padding: 3rem 1.5rem;">
            <h1 class="public-title">Quote not found</h1>
            <p class="public-subtitle">
                That link is invalid or the quote has been removed.
                Please contact the company that sent you the quote for help.
            </p>
        </div>
        <div class="public-footer"><span class="powered">Powered by YourBlinds</span></div>
    </div>
<?php else: ?>

    <div class="public-shell">
        <header class="public-header">
            <div class="company"><?= e((string) $quote['company_name']) ?></div>
            <div class="meta">
                <?php if (!empty($quote['client_email'])): ?>
                    <?= e((string) $quote['client_email']) ?><br>
                <?php endif; ?>
                <?php if (!empty($quote['client_phone'])): ?>
                    <?= e((string) $quote['client_phone']) ?>
                <?php endif; ?>
            </div>
        </header>

        <div class="public-body">
            <?php if ($flashMsg !== null): ?>
                <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
            <?php endif; ?>
            <?php if ($flashErr !== null): ?>
                <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
            <?php endif; ?>

            <?php if ($isAccepted): ?>
                <div class="public-status accepted" role="status">
                    <strong>Accepted</strong> on <?= e($fmtDate((string) ($quote['accepted_at'] ?? ''))) ?>.
                    Thanks &mdash; the supplier has been notified.
                </div>
            <?php elseif ($isOrdered): ?>
                <div class="public-status ordered" role="status">
                    <strong>Order placed</strong> on <?= e($fmtDate((string) ($quote['order_date'] ?? ''))) ?>.
                </div>
            <?php elseif ($isRejected): ?>
                <div class="public-status rejected" role="status">
                    This quote was marked as <strong>rejected</strong>.
                    Contact <?= e((string) $quote['company_name']) ?> if you'd like a fresh quote.
                </div>
            <?php elseif ($isExpired): ?>
                <div class="public-status expired" role="status">
                    This quote <strong>expired</strong> on <?= e($fmtDate((string) $quote['valid_until'])) ?>.
                    Contact <?= e((string) $quote['company_name']) ?> for an updated price.
                </div>
            <?php elseif ($isDraft): ?>
                <div class="public-status draft" role="status">
                    This quote is still being prepared. We'll let you know when it's ready.
                </div>
            <?php endif; ?>

            <h1 class="public-title">Quote <?= e((string) $quote['quote_number']) ?></h1>
            <p class="public-subtitle">
                Prepared for <strong><?= e((string) $quote['end_customer_name']) ?></strong>
                <?php if (!empty($quote['quote_date'])): ?>
                    on <?= e($fmtDate((string) $quote['quote_date'])) ?>
                <?php endif; ?>
            </p>

            <section class="section">
                <div class="detail-cols">
                    <div>
                        <h3>Quote for</h3>
                        <p style="margin:0; font-weight:600; color:#111827;">
                            <?= e((string) $quote['end_customer_name']) ?>
                        </p>
                        <?php
                        $custLines = array_filter([
                            (string) ($quote['end_customer_address1'] ?? ''),
                            (string) ($quote['end_customer_address2'] ?? ''),
                            trim(((string) ($quote['end_customer_town'] ?? '')) . ' ' . ((string) ($quote['end_customer_postcode'] ?? ''))),
                            (string) ($quote['end_customer_county'] ?? ''),
                        ], static fn ($s) => trim((string) $s) !== '');
                        ?>
                        <?php if ($custLines): ?>
                            <p style="margin:.4rem 0 0; font-size:.9375rem; color:#374151; white-space:pre-line;"><?= e(implode("\n", $custLines)) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3>Details</h3>
                        <dl>
                            <dt>Quote date</dt>
                            <dd><?= e($fmtDate((string) ($quote['quote_date'] ?? $quote['created_at']))) ?></dd>
                            <dt>Valid until</dt>
                            <dd><?= e($fmtDate((string) ($quote['valid_until'] ?? null))) ?></dd>
                        </dl>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">Items</h2>
                </div>
                <?php if (empty($items)): ?>
                    <div class="table-empty">No items on this quote.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:3rem;">#</th>
                                    <th>Room / Description</th>
                                    <th>Size</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $i => $item): ?>
                                    <tr>
                                        <td><?= (int) ($item['line_no'] ?? ($i + 1)) ?></td>
                                        <td>
                                            <?php if (!empty($item['room_name'])): ?>
                                                <strong><?= e((string) $item['room_name']) ?></strong><br>
                                            <?php endif; ?>
                                            <span class="pre-line" style="font-size:.875rem; color:#374151;"><?= e((string) $item['description_text']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['width']) && !empty($item['drop_value'])): ?>
                                                <?= e($fmtSize((float) $item['width'])) ?>&times;<?= e($fmtSize((float) $item['drop_value'])) ?>m
                                            <?php endif; ?>
                                        </td>
                                        <td class="num"><?= (int) $item['quantity'] ?></td>
                                        <td class="num"><?= e($money($item['line_total'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="totals-row">
                                    <td colspan="4" class="label">Subtotal</td>
                                    <td class="num"><?= e($money($quote['subtotal'])) ?></td>
                                </tr>
                                <tr class="totals-row">
                                    <td colspan="4" class="label">VAT</td>
                                    <td class="num"><?= e($money($quote['vat'])) ?></td>
                                </tr>
                                <tr class="totals-row grand">
                                    <td colspan="4" class="label">Total</td>
                                    <td class="num"><?= e($money($quote['total'])) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($quote['notes'])): ?>
                <section class="section">
                    <div class="section-header">
                        <h2 class="section-title">Notes</h2>
                    </div>
                    <p class="pre-line" style="margin:0; color:#374151;"><?= e((string) $quote['notes']) ?></p>
                </section>
            <?php endif; ?>

            <?php if ($canAccept): ?>
                <div class="public-accept">
                    <p>Happy with this quote? Click below to accept and let us know.</p>
                    <form method="post" action="/quote-history/accept.php" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <button type="submit" class="btn-accept">Accept this quote</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="public-footer">
            <?php if (!empty($quote['quote_footer'])): ?>
                <?= e((string) $quote['quote_footer']) ?><br>
            <?php endif; ?>
            <span class="powered">Powered by YourBlinds</span>
        </div>
    </div>

<?php endif; ?>

</body>
</html>
