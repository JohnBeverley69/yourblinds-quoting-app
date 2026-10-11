<?php
declare(strict_types=1);

/**
 * Settings → Texts: save the company's texting settings, or send a test text.
 * The panel itself is _partials/settings_texts_panel.php (inside Settings).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/sms.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings.php#texts');
    exit;
}
csrf_check();

$user     = current_user();
$clientId = (int) $user['client_id'];
$back     = '/admin/settings.php#texts';

if (!sms_feature_enabled($clientId)) {
    $_SESSION['flash_error'] = 'Customer texts are part of the Silver and Gold plans.';
    header('Location: ' . $back);
    exit;
}

$action = (string) ($_POST['_action'] ?? '');

if ($action === 'save') {
    $rawSender = trim((string) ($_POST['sms_sender_name'] ?? ''));
    $sender    = sms_clean_sender($rawSender);
    if ($rawSender !== '' && $sender === null) {
        $_SESSION['flash_error'] = 'The sender name must be 3 to 11 letters or numbers (spaces allowed), with at least one letter.';
        header('Location: ' . $back);
        exit;
    }

    // Wording: store only what differs from the default, so improving a
    // default later reaches everyone who hasn't changed theirs.
    $templates = [];
    $ticks     = [];
    foreach (sms_kinds() as $kind => $k) {
        $ticks[$k['column']] = !empty($_POST[$k['column']]) ? 1 : 0;
        $txt = trim(str_replace("\r\n", "\n", (string) ($_POST['tpl_' . $kind] ?? '')));
        if ($txt !== '' && $txt !== $k['default']) $templates[$kind] = mb_substr($txt, 0, 480);
    }

    try {
        $cols = array_merge(['sms_sender_name', 'sms_templates'], array_keys($ticks));
        $vals = array_merge([$sender, $templates ? json_encode($templates, JSON_UNESCAPED_UNICODE) : null], array_values($ticks));
        $sql  = 'INSERT INTO client_settings (client_id, ' . implode(', ', $cols) . ')
                 VALUES (?' . str_repeat(', ?', count($cols)) . ')
                 ON DUPLICATE KEY UPDATE '
              . implode(', ', array_map(static fn ($c) => "$c = VALUES($c)", $cols));
        db()->prepare($sql)->execute(array_merge([$clientId], $vals));
        $_SESSION['flash_success'] = 'Text settings saved.';
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage() . ' — has migrate_messages.php been run?';
    }
    header('Location: ' . $back);
    exit;
}

if ($action === 'test') {
    $kind = (string) ($_POST['test_kind'] ?? 'booking_confirm');
    if (!isset(sms_kinds()[$kind])) $kind = 'booking_confirm';
    $st = db()->prepare('SELECT company_name, phone FROM clients WHERE id = ? LIMIT 1');
    $st->execute([$clientId]);
    $client = $st->fetch() ?: [];
    $body = sms_render(sms_template($clientId, $kind), sms_sample_vars($client));
    $res  = sms_send($clientId, (string) ($_POST['test_to'] ?? ''), $body, [
        'kind' => 'test', 'user_id' => (int) ($user['user_id'] ?? 0),
    ]);
    $_SESSION[$res['ok'] ? 'flash_success' : 'flash_error'] = $res['ok']
        ? $res['message'] . ' It counts towards this month\'s texts.'
        : 'Test text not sent: ' . $res['message'];
    header('Location: ' . $back);
    exit;
}

header('Location: ' . $back);
exit;
