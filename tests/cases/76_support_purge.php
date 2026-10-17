<?php
declare(strict_types=1);

/**
 * SUPPORT TICKET TIDY-UP (John, 2026-10-17, data protection): resolved tickets
 * are deleted 12 months after they were resolved; open ones are never touched.
 */

require_once APP_ROOT . '/_partials/support.php';

test('support tickets: only RESOLVED ones over 12 months old are deleted', function () {
    $ins = db()->prepare(
        "INSERT INTO support_tickets (user_name, user_email, category, message, status, created_at, updated_at)
         VALUES ('Purge Probe', 'probe@example.test', 'problem', ?, ?, ?, ?)"
    );
    $old = date('Y-m-d H:i:s', strtotime('-13 months'));
    $new = date('Y-m-d H:i:s', strtotime('-11 months'));
    $ins->execute(['old resolved', 'resolved', $old, $old]);
    $ins->execute(['recent resolved', 'resolved', $new, $new]);
    $ins->execute(['old but still open', 'new', $old, $old]);
    $ins->execute(['old in progress', 'in_progress', $old, $old]);

    $gone = support_purge_old();
    check($gone >= 1, 'something was deleted');
    $left = db()->query("SELECT message FROM support_tickets WHERE user_name = 'Purge Probe' ORDER BY message")->fetchAll(PDO::FETCH_COLUMN);
    check_same(['old but still open', 'old in progress', 'recent resolved'], $left, 'tickets left');
    db()->exec("DELETE FROM support_tickets WHERE user_name = 'Purge Probe'");
});
