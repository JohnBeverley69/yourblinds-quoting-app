<?php
declare(strict_types=1);

/**
 * Migration: quotes.account_client_id — the trade account a Beverley (factory)
 * quote is FOR. Set when a super-admin raises a quote/order on behalf of a trade
 * account from the "New order" launcher: the quote is OWNED by the factory, but
 * the customer is the account and the pricing uses the account's trade discount.
 * NULL = an ordinary quote (the vast majority) — completely unaffected.
 *
 * Idempotent. Run as super-admin: /migrate_quotes_account.php
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $s = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $s->execute([$table, $col]);
    return $s->fetchColumn() !== false;
};

$ops = [];
if (!$colExists('quotes', 'account_client_id')) {
    $pdo->exec('ALTER TABLE quotes ADD COLUMN account_client_id INT NULL DEFAULT NULL AFTER client_id');
    $ops[] = 'Added quotes.account_client_id.';
    try {
        $pdo->exec('ALTER TABLE quotes ADD KEY idx_quotes_account (account_client_id)');
        $ops[] = 'Added index idx_quotes_account.';
    } catch (Throwable $e) {
        $ops[] = 'Index idx_quotes_account skipped: ' . $e->getMessage();
    }
} else {
    $ops[] = 'quotes.account_client_id already exists — skipped.';
}

echo "Migration complete.\n\n";
foreach ($ops as $i => $op) echo sprintf("  %2d. %s\n", $i + 1, $op);
echo "\nThe \"New order\" launcher can now record which trade account a quote is for.\n";
