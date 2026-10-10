<?php
declare(strict_types=1);

/**
 * Cancelling a remake (John 2026-10-10).
 *
 * Until now rm_approve() and rm_decline() were the only two status writes in the
 * module and both refused anything but 'requested', so an APPROVED remake could
 * never be corrected or undone. Two new actions, for a factory Admin:
 *
 *   Undo   — approved → 'requested', so it can be approved or declined again
 *            (nothing new is stored; the existing decision columns are cleared)
 *   Cancel — approved → 'cancelled', a final state with a reason
 *
 * factory_remakes.status is VARCHAR(20), not an ENUM, so the new 'cancelled'
 * value needs no column change. These three columns record the cancellation:
 *
 *   cancelled_at         — when, and the completion date the Done list sorts by
 *   cancel_reason        — why; shown to the office and to the account
 *   cancelled_by_user_id — who
 *
 * Idempotent, super-admin, web-runnable: /setup/migrations/migrate_remake_cancel.php
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__, 2) . '/auth/middleware.php';
    requireSuperAdmin();
    require_run_confirmation();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tableExists = static function (string $t) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);
    return (bool) $s->fetchColumn();
};
$colExists = static function (string $t, string $c) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $s->execute([$t, $c]);
    return (bool) $s->fetchColumn();
};

if (!$tableExists('factory_remakes')) {
    echo "factory_remakes doesn't exist — run /setup/migrations/migrate_remakes.php first.\n";
    exit;
}

foreach ([
    'cancelled_at'         => 'DATETIME     NULL DEFAULT NULL',
    'cancel_reason'        => 'VARCHAR(255) NULL DEFAULT NULL',
    'cancelled_by_user_id' => 'INT          NULL DEFAULT NULL',
] as $col => $spec) {
    if ($colExists('factory_remakes', $col)) {
        echo "factory_remakes.$col already exists — skipped.\n";
        continue;
    }
    $pdo->exec("ALTER TABLE factory_remakes ADD COLUMN $col $spec");
    echo "Added factory_remakes.$col.\n";
}

// The Done list and the Overview's "Done in the last 30 days" read cancelled
// rows alongside declined ones, filtered by status.
if ($colExists('factory_remakes', 'status')) {
    $idx = $pdo->prepare("SELECT 1 FROM information_schema.STATISTICS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='factory_remakes'
                             AND INDEX_NAME='idx_rm_factory_status' LIMIT 1");
    $idx->execute();
    if ($idx->fetchColumn()) {
        echo "Index idx_rm_factory_status already exists — skipped.\n";
    } else {
        $pdo->exec('ALTER TABLE factory_remakes ADD INDEX idx_rm_factory_status (factory_client_id, status)');
        echo "Added index idx_rm_factory_status.\n";
    }
}

echo "\nDone. Cancel and Undo now appear on approved remakes in the Factory Console\n";
echo "for a factory Admin (Remakes → In progress).\n";
