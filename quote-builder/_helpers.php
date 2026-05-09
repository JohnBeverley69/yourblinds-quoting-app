<?php
declare(strict_types=1);

// Quote-builder helpers — loaded by every page in this module. Caller must
// have already required bootstrap.php + auth/middleware.php.

/**
 * Load a quote scoped to the given client. 404s if not found / wrong tenant.
 */
function qb_load_quote_or_404(int $quoteId, int $clientId): array
{
    if ($quoteId <= 0) {
        http_response_code(404);
        exit('Quote not found.');
    }
    $st = db()->prepare(
        'SELECT * FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $st->execute([$quoteId, $clientId]);
    $q = $st->fetch();
    if (!$q) {
        http_response_code(404);
        exit('Quote not found.');
    }
    return $q;
}

/**
 * Recompute and persist subtotal / VAT / total based on current line items.
 * Called after every add / delete on items.
 */
function qb_recompute_totals(int $quoteId): void
{
    $pdo = db();

    $sumSt = $pdo->prepare(
        'SELECT COALESCE(SUM(line_total), 0) FROM quote_items WHERE quote_id = ?'
    );
    $sumSt->execute([$quoteId]);
    $subtotal = round((float) $sumSt->fetchColumn(), 2);

    $rateSt = $pdo->prepare('SELECT vat_percent FROM quotes WHERE id = ?');
    $rateSt->execute([$quoteId]);
    $vatPct = (float) ($rateSt->fetchColumn() ?: 0);

    $vat   = round($subtotal * $vatPct / 100, 2);
    $total = round($subtotal + $vat, 2);

    $pdo->prepare('UPDATE quotes SET subtotal = ?, vat = ?, total = ? WHERE id = ?')
        ->execute([$subtotal, $vat, $total, $quoteId]);
}

/**
 * Generate the next sequential quote number for a client: PRE-YYYY-####.
 * Prefix is from client_settings.quote_prefix, falling back to the first
 * 3 alpha chars of company_name. Subject to a small race window — the
 * UNIQUE index on quotes(client_id, quote_number) catches collisions.
 */
function qb_generate_quote_number(int $clientId): string
{
    $pdo = db();

    $st = $pdo->prepare('SELECT quote_prefix FROM client_settings WHERE client_id = ? LIMIT 1');
    $st->execute([$clientId]);
    $prefix = trim((string) ($st->fetchColumn() ?: ''));

    if ($prefix === '') {
        $st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
        $st->execute([$clientId]);
        $name   = (string) ($st->fetchColumn() ?: '');
        $clean  = (string) (preg_replace('/[^A-Za-z]/', '', $name) ?? '');
        $prefix = strtoupper(substr($clean, 0, 3));
        if ($prefix === '') $prefix = 'QTE';
    }

    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $st = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(quote_number, '-', -1) AS UNSIGNED))
           FROM quotes
          WHERE client_id = ? AND quote_number LIKE ?"
    );
    $st->execute([$clientId, $like]);
    $next = ((int) ($st->fetchColumn() ?? 0)) + 1;

    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

/**
 * Random 64-char hex token for the customer-facing accept URL.
 * Stored on quotes.public_token (UNIQUE).
 */
function qb_generate_public_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Format an integer mm value for display: "1500 mm".
 * Used in the line-item table; the user-facing form takes flexible units.
 */
function qb_fmt_mm(int $mm): string
{
    return number_format($mm) . ' mm';
}

/**
 * Format a money value: "£1,234.56".
 */
function qb_fmt_money($n): string
{
    return '£' . number_format((float) $n, 2);
}

/**
 * Set a flash message and redirect. Used by POST handlers.
 */
function qb_flash_redirect(string $location, string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
    header('Location: ' . $location);
    exit;
}

/**
 * True if a quote is in an editable state. Drafts are fully editable; once
 * sent or beyond, the quote is locked unless the user explicitly reopens.
 */
function qb_is_editable(array $quote): bool
{
    return ((string) $quote['status']) === 'draft';
}

/**
 * Statuses available to transition TO from the current status.
 * The pipeline is roughly draft → sent → accepted → ordered → invoiced → paid,
 * with declined as a terminal alternative to accepted, and "Reopen" allowing
 * a return to draft from any state for late edits.
 */
function qb_allowed_transitions(string $current): array
{
    switch ($current) {
        case 'draft':     return ['sent'];
        case 'sent':      return ['accepted', 'declined', 'draft'];
        case 'accepted':  return ['ordered', 'draft'];
        case 'declined':  return ['draft'];
        case 'ordered':   return ['invoiced', 'draft'];
        case 'invoiced':  return ['paid', 'draft'];
        case 'paid':      return [];
    }
    return ['draft'];
}
