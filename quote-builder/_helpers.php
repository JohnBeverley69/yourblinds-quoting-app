<?php
declare(strict_types=1);

// Quote-builder helpers — loaded by every page in this module.
// Auth + bootstrap + db are loaded by the caller.

/**
 * Load a quote scoped to the given client. 404s if not found / wrong tenant.
 */
function qb_load_quote_or_404(int $quoteId, int $clientId): array
{
    if ($quoteId <= 0) {
        http_response_code(404);
        exit('Quote not found.');
    }
    $stmt = db()->prepare(
        'SELECT * FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $stmt->execute([$quoteId, $clientId]);
    $q = $stmt->fetch();
    if (!$q) {
        http_response_code(404);
        exit('Quote not found.');
    }
    return $q;
}

/**
 * Recompute and persist subtotal / VAT / total based on current line items.
 * Called after add / update / delete item.
 */
function qb_recompute_totals(int $quoteId): void
{
    $pdo = db();

    $sumStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(line_total), 0) FROM quote_items WHERE quote_id = ?'
    );
    $sumStmt->execute([$quoteId]);
    $subtotal = round((float) $sumStmt->fetchColumn(), 2);

    $vatStmt = $pdo->prepare(
        'SELECT COALESCE(cs.vat_percent, 0)
           FROM quotes q
           LEFT JOIN client_settings cs ON cs.client_id = q.client_id
          WHERE q.id = ?'
    );
    $vatStmt->execute([$quoteId]);
    $vatPct = (float) ($vatStmt->fetchColumn() ?? 0);

    $vat   = round($subtotal * $vatPct / 100, 2);
    $total = round($subtotal + $vat, 2);

    $pdo->prepare('UPDATE quotes SET subtotal = ?, vat = ?, total = ? WHERE id = ?')
        ->execute([$subtotal, $vat, $total, $quoteId]);
}

/**
 * Generate the next sequential quote number for a client: PRE-YYYY-####.
 * Prefix is from client_settings.quote_prefix, falling back to the first
 * 3 alpha chars of company_name. Subject to a small race window — the
 * UNIQUE index on quotes.quote_number will fail one of two parallel
 * inserts, callers should retry on collision (rare in single-user usage).
 */
function qb_generate_quote_number(int $clientId): string
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT quote_prefix FROM client_settings WHERE client_id = ? LIMIT 1');
    $stmt->execute([$clientId]);
    $prefix = trim((string) ($stmt->fetchColumn() ?: ''));

    if ($prefix === '') {
        $stmt = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$clientId]);
        $name  = (string) ($stmt->fetchColumn() ?: '');
        $clean = (string) (preg_replace('/[^A-Za-z]/', '', $name) ?? '');
        $prefix = strtoupper(substr($clean, 0, 3));
        if ($prefix === '') {
            $prefix = 'QTE';
        }
    }

    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(quote_number, '-', -1) AS UNSIGNED))
           FROM quotes
          WHERE client_id = ? AND quote_number LIKE ?"
    );
    $stmt->execute([$clientId, $like]);
    $next = ((int) ($stmt->fetchColumn() ?? 0)) + 1;

    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

/**
 * Format a metric size value to 1dp ("3.0", "2.5"). Mirrors the seed format.
 */
function qb_fmt_size(float $v): string
{
    return number_format($v, 1, '.', '');
}

/**
 * Build the multi-line description_text snapshot for a quote item.
 * Format matches the legacy / seed shape so PDF output is consistent.
 */
function qb_build_description(
    string $productName,
    string $fabric,
    string $colour,
    string $band,
    float  $width,
    float  $drop
): string {
    return "Type: {$productName}\nFabric: {$fabric}\nColour: {$colour}\nBand: {$band}\n"
         . 'Width: ' . qb_fmt_size($width) . "m\n"
         . 'Drop: '  . qb_fmt_size($drop)  . 'm';
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
