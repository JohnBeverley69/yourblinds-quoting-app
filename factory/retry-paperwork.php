<?php
declare(strict_types=1);

/**
 * Factory · Retry the delivery note for a dispatched order whose note couldn't
 * be made at dispatch ("Paperwork missing" on the Pipeline / Dispatch tray —
 * see os_paperwork_missing()). Makes (or dispatches) the note, then runs the
 * auto-invoice-on-dispatch rule that was held back without it, and re-rolls the
 * stage. POST: quote_id, back, _csrf.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/order_stage.php';

requireFactoryOffice();

$back = in_array((string) ($_POST['back'] ?? ''), ['/factory/pipeline.php', '/master-admin/dispatch.php'], true)
    ? (string) $_POST['back'] : '/factory/pipeline.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . $back); exit; }
csrf_check();

$pdo     = db();
$factory = current_factory_id();
$quoteId = (int) ($_POST['quote_id'] ?? 0);
$userId  = (int) (current_user()['user_id'] ?? 0) ?: null;

// Only an order this factory has flagged — never any quote id typed in.
$flagged = [];
foreach (os_paperwork_missing($pdo, $factory) as $r) $flagged[(int) $r['id']] = (string) $r['quote_number'];

if (!isset($flagged[$quoteId])) {
    $_SESSION['flash_success'] = 'That order already has its delivery note.';
} else {
    $err = os_mark_dispatched_dn($pdo, $quoteId, $factory, $userId);
    if ($err !== '') {
        $_SESSION['flash_error'] = 'Still couldn\'t make the delivery note for ' . $flagged[$quoteId] . ': ' . $err;
    } else {
        os_auto_invoice_on_dispatch($pdo, $quoteId, $factory, $userId);
        $_SESSION['flash_success'] = 'Delivery note made for ' . $flagged[$quoteId] . '.';
    }
    recompute_order_stage($pdo, $quoteId);
}
header('Location: ' . $back);
exit;
