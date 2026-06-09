<?php
declare(strict_types=1);

/**
 * Customer-facing read-only quote view. No login required — the URL token
 * IS the auth, generated when the quote was created. Tokens are 64 hex
 * chars (256 bits) so guessing one is infeasible.
 *
 * Same size-free rule as the PDF: the customer sees product / system /
 * fabric / colour / extras / qty / price. Width and drop never appear.
 *
 * If the quote is in 'draft' the public page declines to render — drafts
 * aren't supposed to be visible to anyone outside the trade business yet.
 * Other statuses render and show the appropriate state of the accept form.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';   // for e()
require __DIR__ . '/../_partials/legal_text.php';

// htmlspecialchars helper — we don't have e() yet outside middleware. Already
// pulled in via the require above so it's safe to call.

$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{40,128}$/i', $token)) {
    http_response_code(404);
    exit('Quote not found.');
}

$qStmt = db()->prepare(
    'SELECT q.*,
            c.company_name AS trade_company_name,
            c.address1     AS trade_addr1,
            c.address2     AS trade_addr2,
            c.town         AS trade_town,
            c.county       AS trade_county,
            c.postcode     AS trade_postcode,
            c.email        AS trade_email,
            c.phone        AS trade_phone,
            c.logo_path    AS trade_logo,
            cs.quote_footer
       FROM quotes q
       JOIN clients          c  ON c.id        = q.client_id
       LEFT JOIN client_settings cs ON cs.client_id = q.client_id
      WHERE q.public_token = ?
      LIMIT 1'
);
$qStmt->execute([$token]);
$quote = $qStmt->fetch();

if (!$quote) {
    http_response_code(404);
    exit('Quote not found.');
}

// Terms & Conditions + Privacy Policy (optional columns). Separate guarded
// query so the page still renders if migrate_terms_conditions.php hasn't run.
// $legalAvailable distinguishes "feature present" from the un-migrated case.
$quote['terms_conditions'] = null;
$quote['privacy_policy']   = null;
$legalAvailable = false;
try {
    $lStmt = db()->prepare(
        'SELECT terms_conditions, privacy_policy FROM client_settings WHERE client_id = ? LIMIT 1'
    );
    $lStmt->execute([(int) $quote['client_id']]);
    $legalAvailable = true;
    if ($lRow = $lStmt->fetch()) {
        $quote['terms_conditions'] = $lRow['terms_conditions'];
        $quote['privacy_policy']   = $lRow['privacy_policy'];
    }
} catch (Throwable $e) { /* columns not present yet — skip */ }
// NULL (or no settings row yet) = never configured → fall back to the standard
// template, so the T&Cs (and the accept checkbox) apply for every client by
// default. An explicitly saved empty string = "disabled" and stays empty.
$tcText = $legalAvailable ? trim(legal_effective_terms($quote['terms_conditions'] ?? null)) : '';
$ppText = $legalAvailable ? trim(legal_effective_privacy($quote['privacy_policy'] ?? null)) : '';

// First-view auto-promote: if the trade user shared the link via WhatsApp
// or copy-paste (i.e. without going through email_pdf.php which already
// promotes), the quote is still "draft" — but the customer has the link in
// hand, so it has effectively been sent. Flip status + stamp sent_at on the
// first view from the public URL; subsequent views are no-ops. Token in the
// URL is the auth — anyone with it has been given it deliberately.
if ((string) $quote['status'] === 'draft') {
    db()->prepare(
        'UPDATE quotes SET status = "sent", sent_at = NOW()
          WHERE id = ? AND status = "draft"'
    )->execute([(int) $quote['id']]);
    $quote['status']  = 'sent';
    $quote['sent_at'] = date('Y-m-d H:i:s');
}

$itemsSt = db()->prepare(
    'SELECT * FROM quote_items WHERE quote_id = ? ORDER BY line_no, id'
);
$itemsSt->execute([(int) $quote['id']]);
$items = $itemsSt->fetchAll();

$extrasByItem = [];
if ($items) {
    $itemIds = array_map(static fn ($r) => (int) $r['id'], $items);
    $ph = implode(',', array_fill(0, count($itemIds), '?'));
    // user_value column may not exist yet — fall back to column-less.
    try {
        $st = db()->prepare(
            "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot,
                    amount_applied, user_value
               FROM quote_item_extras
              WHERE quote_item_id IN ($ph)
              ORDER BY id"
        );
        $st->execute($itemIds);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        $st = db()->prepare(
            "SELECT quote_item_id, extra_name_snapshot, choice_label_snapshot, amount_applied
               FROM quote_item_extras
              WHERE quote_item_id IN ($ph)
              ORDER BY id"
        );
        $st->execute($itemIds);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) $r['user_value'] = null;
        unset($r);
    }
    foreach ($rows as $r) {
        $extrasByItem[(int) $r['quote_item_id']][] = $r;
    }
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$money   = static fn ($n) => '£' . number_format((float) $n, 2);
$fmtDate = static function (?string $dt): string {
    if (!$dt) return '—';
    $ts = strtotime((string) $dt);
    return $ts ? date('j F Y', $ts) : '—';
};

$status      = (string) $quote['status'];
$canAccept   = in_array($status, ['sent'], true);
$alreadyDone = in_array($status, ['accepted', 'declined', 'ordered', 'fitted', 'invoiced', 'paid'], true);

$tradeLines = array_values(array_filter([
    (string) ($quote['trade_addr1']    ?? ''),
    (string) ($quote['trade_addr2']    ?? ''),
    trim(((string) ($quote['trade_town'] ?? '')) . ' ' . ((string) ($quote['trade_postcode'] ?? ''))),
    (string) ($quote['trade_county']   ?? ''),
], static fn ($s) => trim((string) $s) !== ''));

$vatPct = $quote['vat_percent'] !== null
    ? rtrim(rtrim(number_format((float) $quote['vat_percent'], 2, '.', ''), '0'), '.')
    : '20';

// ---------------------------------------------------------------------------
// Deposit info for the public callout (between table and accept form).
//
// Two states to surface:
//   - Pre-acceptance: a PREDICTED deposit from the tenant defaults, so
//     the customer knows up front what they'll need to pay if they go
//     ahead. The actual deposit_amount only gets seeded by
//     change_status.php the moment they accept, so we recompute the
//     prediction here using the same logic.
//   - Post-acceptance: the ACTUAL stored deposit_amount + paid status.
//
// All defensive against schema not yet migrated — try/catch the
// settings lookup, fall back to "no deposit info shown" if anything
// goes wrong. The customer never sees an error message.
// ---------------------------------------------------------------------------
$depositInfo  = null;   // ['amount' => float, 'paid' => bool, 'paid_at' => ?string, 'predicted' => bool]
$depositStored = $quote['deposit_amount'] ?? null;
$depositPaidAt = $quote['deposit_paid_at'] ?? null;

if ($depositStored !== null) {
    // Real, stored deposit — quote has been (or was) accepted.
    $depositInfo = [
        'amount'    => (float) $depositStored,
        'paid'      => $depositPaidAt !== null,
        'paid_at'   => $depositPaidAt,
        'predicted' => false,
    ];
} elseif ($canAccept) {
    // Pre-acceptance — fetch tenant defaults and predict.
    try {
        $depSt = db()->prepare(
            'SELECT default_deposit_mode, default_deposit_percent, default_deposit_flat
               FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $depSt->execute([(int) $quote['client_id']]);
        $dp = $depSt->fetch();
        if ($dp) {
            $total = (float) $quote['total'];
            if (((string) ($dp['default_deposit_mode'] ?? 'percent')) === 'flat') {
                $amt = min((float) ($dp['default_deposit_flat'] ?? 0), $total);
            } else {
                $amt = $total * ((float) ($dp['default_deposit_percent'] ?? 50)) / 100;
            }
            $amt = round($amt, 2);
            if ($amt > 0) {
                $depositInfo = [
                    'amount'    => $amt,
                    'paid'      => false,
                    'paid_at'   => null,
                    'predicted' => true,
                ];
            }
        }
    } catch (Throwable $e) {
        // Columns missing (migrations not run) — quietly omit.
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote <?= e((string) $quote['quote_number']) ?></title>
    <style>
        body { font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
               background: #f3f4f6; color: #1f2937; margin: 0; padding: 2rem 1rem; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff;
                 border: 1px solid #e5e7eb; border-radius: 12px;
                 padding: 2rem 2rem 2.5rem; box-shadow: 0 6px 20px rgba(0,0,0,0.04); }
        .head { display: flex; justify-content: space-between; align-items: flex-start;
                gap: 1rem; flex-wrap: wrap; padding-bottom: 1.25rem;
                border-bottom: 1px solid #e5e7eb; margin-bottom: 1.5rem; }
        .brand img { max-height: 64px; max-width: 200px; display: block; margin-bottom: 0.5rem; }
        .brand .name { font-size: 1.375rem; font-weight: 700; color: #1f3b5b; line-height: 1; }
        .brand .lines { font-size: 0.875rem; color: #6b7280; line-height: 1.55; margin-top: 0.5rem; }
        .quote-meta { text-align: right; }
        .quote-meta h1 { font-size: 1.5rem; color: #111827; margin: 0 0 0.5rem; }
        .quote-meta .lbl { color: #6b7280; display: inline-block; min-width: 80px; }
        .quote-meta .val { color: #111827; font-weight: 600; }
        .for { background: #f9fafb; border-left: 3px solid #1f3b5b;
               padding: 0.875rem 1rem; margin-bottom: 1.5rem; border-radius: 0 8px 8px 0; }
        .for .lbl { font-size: 0.6875rem; color: #6b7280; text-transform: uppercase;
                    letter-spacing: 0.06em; }
        .for .name { font-weight: 700; color: #111827; margin-top: 0.125rem; }
        .for .addr { font-size: 0.875rem; color: #4b5563; margin-top: 0.25rem; white-space: pre-line; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
        table.items thead th { background: #1f3b5b; color: #fff; padding: 0.625rem 0.75rem;
                               text-align: left; font-size: 0.75rem; text-transform: uppercase;
                               letter-spacing: 0.04em; font-weight: 600; }
        table.items tbody td { padding: 0.75rem 0.75rem; border-bottom: 1px solid #e5e7eb;
                                vertical-align: top; }
        table.items td.num, table.items th.num { text-align: right; }
        .room { font-weight: 600; color: #111827; }
        .desc { color: #4b5563; font-size: 0.875rem; margin-top: 0.25rem; line-height: 1.45; }
        .extras { color: #6b7280; font-size: 0.8125rem; margin-top: 0.25rem; }
        table.items tfoot td { padding: 0.5rem 0.75rem; }
        table.items tfoot td.lbl { text-align: right; color: #6b7280; font-weight: 600; }
        table.items tfoot td.val { text-align: right; font-weight: 600; }
        table.items tfoot tr.grand td { font-size: 1.125rem; color: #111827; padding-top: 0.875rem; }
        table.items tfoot tr.grand td.lbl { color: #111827; }
        .deposit-card {
            padding: 0.875rem 1rem; border-radius: 10px;
            margin: 1rem 0; font-size: 0.9375rem; line-height: 1.5;
        }
        .deposit-card.deposit-due {
            background: #fef3c7; color: #78350f; border: 1px solid #fde68a;
        }
        .deposit-card.deposit-paid {
            background: #d1fae5; color: #064e3b; border: 1px solid #a7f3d0;
        }
        .notes { background: #fffbeb; border-left: 3px solid #f59e0b;
                 padding: 0.75rem 1rem; margin-top: 1.5rem; border-radius: 0 8px 8px 0; }
        .notes h3 { margin: 0 0 0.25rem; font-size: 0.75rem; color: #92400e; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 0.04em; }
        .notes p  { margin: 0; white-space: pre-line; }
        .accept-card { background: #f0fdf4; border: 1px solid #bbf7d0;
                       border-radius: 10px; padding: 1.25rem 1.5rem; margin-top: 1.5rem; }
        .accept-card.accepted { background: #ecfeff; border-color: #a5f3fc; }
        .accept-card.declined { background: #fef2f2; border-color: #fecaca; }
        .accept-card h2 { margin: 0 0 0.5rem; font-size: 1.125rem; color: #111827; }
        .accept-card p { margin: 0 0 0.75rem; }
        .accept-card label { display: block; margin: 0.5rem 0 0.25rem;
                             font-size: 0.875rem; font-weight: 600; color: #111827; }
        .accept-card input[type="text"] {
            width: 100%; max-width: 380px; padding: 0.625rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 8px; font: inherit;
            box-sizing: border-box;
        }
        .accept-card .actions { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
        .accept-card button, .accept-card input[type="submit"] {
            padding: 0.625rem 1.25rem; font: inherit; font-weight: 600;
            border-radius: 8px; cursor: pointer; border: 0;
        }
        .accept-card .btn-primary  { background: #15803d; color: #fff; }
        .accept-card .btn-secondary { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-error   { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;
                  font-size: 0.75rem; color: #9ca3af; text-align: center; }
        @media (max-width: 600px) {
            .head { flex-direction: column; }
            .quote-meta { text-align: left; }
        }
    </style>
</head>
<body>
<main class="sheet">

    <header class="head">
        <div class="brand">
            <?php if (!empty($quote['trade_logo'])): ?>
                <img src="<?= e((string) $quote['trade_logo']) ?>" alt="">
            <?php endif; ?>
            <div class="name"><?= e((string) ($quote['trade_company_name'] ?? '')) ?></div>
            <div class="lines">
                <?php foreach ($tradeLines as $line): ?>
                    <?= e((string) $line) ?><br>
                <?php endforeach; ?>
                <?php if (!empty($quote['trade_phone'])): ?>
                    <?= e((string) $quote['trade_phone']) ?><br>
                <?php endif; ?>
                <?php if (!empty($quote['trade_email'])): ?>
                    <?= e((string) $quote['trade_email']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="quote-meta">
            <h1>Quote <?= e((string) $quote['quote_number']) ?></h1>
            <div>
                <span class="lbl">Date</span> <span class="val"><?= e($fmtDate((string) ($quote['created_at'] ?? null))) ?></span><br>
                <span class="lbl">Status</span> <span class="val" style="text-transform:capitalize"><?= e($status) ?></span>
            </div>
        </div>
    </header>

    <?php if ($flashMsg !== null): ?>
        <div class="alert alert-success"><?= e((string) $flashMsg) ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== null): ?>
        <div class="alert alert-error"><?= e((string) $flashErr) ?></div>
    <?php endif; ?>

    <div class="for">
        <div class="lbl">Quote for</div>
        <div class="name"><?= e((string) $quote['end_customer_name']) ?></div>
        <?php
            $custLines = array_values(array_filter([
                (string) ($quote['end_customer_address1'] ?? ''),
                (string) ($quote['end_customer_address2'] ?? ''),
                trim(((string) ($quote['end_customer_town'] ?? '')) . ' ' . ((string) ($quote['end_customer_postcode'] ?? ''))),
                (string) ($quote['end_customer_county'] ?? ''),
            ], static fn ($s) => trim((string) $s) !== ''));
        ?>
        <?php if ($custLines): ?>
            <div class="addr"><?= e(implode("\n", $custLines)) ?></div>
        <?php endif; ?>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:2.5rem">#</th>
                <th>Description</th>
                <th class="num" style="width:3rem">Qty</th>
                <th class="num" style="width:5rem">Unit</th>
                <th class="num" style="width:5.5rem">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="5" style="text-align:center; padding:2rem; color:#9ca3af;">
                    No items on this quote.
                </td></tr>
            <?php else: foreach ($items as $i => $item):
                $descBits = [];
                if (!empty($item['product_name_snapshot'])) {
                    $descBits[] = (string) $item['product_name_snapshot']
                        . (!empty($item['system_name_snapshot']) ? ' — ' . (string) $item['system_name_snapshot'] : '');
                }
                $fabricBits = array_filter([
                    (string) ($item['fabric_supplier_snapshot'] ?? ''),
                    (string) ($item['fabric_name_snapshot']     ?? ''),
                    (string) ($item['fabric_colour_snapshot']   ?? ''),
                ], static fn ($s) => $s !== '');
                if ($fabricBits) {
                    $descBits[] = implode(' / ', $fabricBits);
                }
            ?>
                <tr>
                    <td><?= (int) ($item['line_no'] ?? ($i + 1)) ?></td>
                    <td>
                        <?php if (!empty($item['room_name'])): ?>
                            <div class="room"><?= e((string) $item['room_name']) ?></div>
                        <?php endif; ?>
                        <?php if ($descBits): ?>
                            <div class="desc"><?= e(implode("\n", $descBits)) ?></div>
                        <?php endif; ?>
                        <?php $exs = $extrasByItem[(int) $item['id']] ?? []; ?>
                        <?php if ($exs): ?>
                            <div class="extras">
                                <?php foreach ($exs as $ex): ?>
                                    + <?= e((string) $ex['extra_name_snapshot']) ?><?php if (($ex['choice_label_snapshot'] ?? '') !== ''): ?>: <?= e((string) $ex['choice_label_snapshot']) ?><?php endif;
                                        if (isset($ex['user_value']) && $ex['user_value'] !== null && (float) $ex['user_value'] > 0):
                                            echo ' &mdash; ' . e(rtrim(rtrim(number_format((float) $ex['user_value'], 2, '.', ''), '0'), '.')) . 'mm';
                                        endif;
                                    ?><br>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= (int) $item['quantity'] ?></td>
                    <td class="num"><?= e($money($item['sell_price'])) ?></td>
                    <td class="num"><?= e($money($item['line_total'])) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <?php if ((float) $quote['vat_percent'] > 0): ?>
                <tr><td colspan="3"></td><td class="lbl">Subtotal</td><td class="val"><?= e($money($quote['subtotal'])) ?></td></tr>
                <tr><td colspan="3"></td><td class="lbl">VAT (<?= e($vatPct) ?>%)</td><td class="val"><?= e($money($quote['vat'])) ?></td></tr>
            <?php endif; ?>
            <tr class="grand"><td colspan="3"></td><td class="lbl">Total</td><td class="val"><?= e($money($quote['total'])) ?></td></tr>
        </tfoot>
    </table>

    <?php if (!empty($quote['notes'])): ?>
        <div class="notes">
            <h3>Notes</h3>
            <p><?= e((string) $quote['notes']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($depositInfo): ?>
        <?php if ($depositInfo['paid']): ?>
            <div class="deposit-card deposit-paid">
                <strong>✓ Deposit paid</strong>
                <?= e($money($depositInfo['amount'])) ?>
                received on <?= e($fmtDate((string) $depositInfo['paid_at'])) ?>.
            </div>
        <?php elseif ($depositInfo['predicted']): ?>
            <div class="deposit-card deposit-due">
                <strong>Deposit on acceptance:</strong>
                <?= e($money($depositInfo['amount'])) ?>.
                The balance will be due on completion.
            </div>
        <?php else: ?>
            <div class="deposit-card deposit-due">
                <strong>Deposit outstanding:</strong>
                <?= e($money($depositInfo['amount'])) ?>.
                <?= e((string) ($quote['trade_company_name'] ?? 'Your supplier')) ?>
                will be in touch about payment.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($alreadyDone && (string) $quote['status'] === 'accepted'): ?>
        <div class="accept-card accepted">
            <h2>Quote accepted ✓</h2>
            <p>Thanks <?= e((string) ($quote['acceptance_signature_name'] ?? $quote['end_customer_name'])) ?>!
               This quote was accepted on <?= e($fmtDate((string) ($quote['accepted_at'] ?? null))) ?>.
               <?= e((string) ($quote['trade_company_name'] ?? '')) ?> will be in touch.</p>
        </div>
    <?php elseif ($alreadyDone && (string) $quote['status'] === 'declined'): ?>
        <div class="accept-card declined">
            <h2>Quote declined</h2>
            <p>This quote was declined. If that was a mistake, please contact
               <?= e((string) ($quote['trade_company_name'] ?? 'us')) ?> directly.</p>
        </div>
    <?php elseif ($alreadyDone): ?>
        <div class="accept-card accepted">
            <h2>Quote in progress</h2>
            <p>This quote has moved on to <strong><?= e($status) ?></strong>.
               If you have questions please contact
               <?= e((string) ($quote['trade_company_name'] ?? 'your supplier')) ?>.</p>
        </div>
    <?php elseif ($canAccept): ?>
        <div class="accept-card">
            <h2>Accept this quote</h2>
            <p>Type your full name to confirm acceptance. We'll record it as your
               digital sign-off and let
               <?= e((string) ($quote['trade_company_name'] ?? 'your supplier')) ?> know.</p>
            <form method="post" action="/quote-history/accept.php">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label for="signature_name">Your full name</label>
                <input id="signature_name" name="signature_name" type="text"
                       required maxlength="150" autocomplete="name"
                       value="<?= e((string) $quote['end_customer_name']) ?>">
                <?php if ($tcText !== ''): ?>
                <label for="agree_terms"
                       style="display:flex;align-items:flex-start;gap:0.5rem;margin:0.875rem 0 0.25rem;
                              font-weight:400;cursor:pointer;line-height:1.5">
                    <input id="agree_terms" name="agree_terms" type="checkbox" value="1"
                           required style="margin-top:0.2rem">
                    <span>I agree to the
                        <a href="/quote-history/terms.php?token=<?= e($token) ?>"
                           target="_blank" rel="noopener" style="color:#2563eb">Terms &amp; Conditions</a>
                        of <?= e((string) ($quote['trade_company_name'] ?? 'the supplier')) ?>.</span>
                </label>
                <?php endif; ?>
                <div class="actions">
                    <button type="submit" name="action" value="accept" class="btn-primary">
                        Accept quote
                    </button>
                    <button type="submit" name="action" value="decline" class="btn-secondary"
                            formnovalidate
                            data-confirm-click="Decline this quote? Your supplier will be notified.">
                        Decline
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($tcText !== '' || $ppText !== ''): ?>
        <?php
            $legalLinkLabel = ($tcText !== '' && $ppText !== '') ? 'Terms & Conditions & Privacy Policy'
                            : ($tcText !== '' ? 'Terms & Conditions' : 'Privacy Policy');
        ?>
        <p style="margin-top:1.25rem;font-size:0.875rem;text-align:center">
            <a href="/quote-history/terms.php?token=<?= e($token) ?>"
               target="_blank" rel="noopener" style="color:#2563eb"><?= e($legalLinkLabel) ?></a>
        </p>
    <?php endif; ?>

    <?php if (!empty($quote['quote_footer'])): ?>
        <div class="footer"><?= e((string) $quote['quote_footer']) ?></div>
    <?php else: ?>
        <div class="footer"><?= e((string) ($quote['trade_company_name'] ?? '')) ?> &middot; Quote <?= e((string) $quote['quote_number']) ?></div>
    <?php endif; ?>

</main>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
