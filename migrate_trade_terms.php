<?php
declare(strict_types=1);

/**
 * Add a SEPARATE trade Terms & Conditions per tenant.
 *
 * client_settings.trade_terms_conditions — the B2B/trade terms, used on trade
 * quotes (those raised for an account). Retail quotes keep terms_conditions.
 * NULL = never configured → the tokenised default (legal_default_trade_terms())
 * applies; '' = configured-but-blank (no trade terms shown).
 *
 * Idempotent + web-runnable: /migrate_trade_terms.php (super-admin).
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

$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $s->execute([$t, $c]); return (bool) $s->fetchColumn();
};

if (!$colExists('client_settings', 'trade_terms_conditions')) {
    $pdo->exec('ALTER TABLE client_settings ADD COLUMN trade_terms_conditions TEXT NULL AFTER terms_conditions');
    echo "Added client_settings.trade_terms_conditions.\n";
} else {
    echo "client_settings.trade_terms_conditions already exists — skipped.\n";
}

echo "\nDone. Trade quotes will use the trade terms (default until you set your own on Settings → Legal).\n";
