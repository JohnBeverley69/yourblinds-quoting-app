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
    require_once __DIR__ . '/../_partials/billing_helpers.php';
    $_SESSION['flash_error'] = 'Customer texts are part of ' . billing_feature_plans_phrase('feature_sms') . '.';
    header('Location: ' . $back);
    exit;
}

$action = (string) ($_POST['_action'] ?? '');

if ($action === 'save') {
    $rawSender = trim((string) ($_POST['sms_sender_name'] ?? ''));
    $sender    = sms_clean_sender($rawSender);
    if ($sender !== null && strcasecmp($sender, SMS_DEFAULT_SENDER) === 0) $sender = null;   // the standard one
    if ($rawSender !== '' && $sender === null) {
        $_SESSION['flash_error'] = 'The sender name must be 3 to 11 letters or numbers (spaces allowed), with at least one letter.';
        header('Location: ' . $back);
        exit;
    }

    // Wording: store only what differs from the default, so improving a
    // default later reaches everyone who hasn't changed theirs. Other wording
    // in the same JSON (the quote follow-up) is kept as it is.
    $templates = sms_settings($clientId)['sms_templates'] ?? [];
    $ticks     = [];
    foreach (sms_kinds() as $kind => $k) {
        $ticks[$k['column']] = !empty($_POST[$k['column']]) ? 1 : 0;
        unset($templates[$kind]);
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
        if (($risk = sms_sender_risk($sender)) !== null) {
            $_SESSION['flash_error'] = 'Saved, but check the sender name: ' . $risk;
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage() . ' — has migrate_messages.php been run?';
    }
    header('Location: ' . $back);
    exit;
}

if ($action === 'follow_up') {
    require_once __DIR__ . '/../_partials/quote_follow_up.php';
    $on     = !empty($_POST['qfu_on']) ? 1 : 0;
    $days   = max(1, min(25, (int) ($_POST['qfu_days'] ?? 7)));
    $method = (string) ($_POST['qfu_method'] ?? 'email');
    if (!in_array($method, ['sms', 'email', 'both'], true)) $method = 'email';

    // Same rule as the texts: keep only wording that differs from the standard.
    $templates = sms_settings($clientId)['sms_templates'] ?? [];
    $limits    = ['text' => 480, 'subject' => 150, 'email' => 3000];
    foreach (qfu_template_keys() as $part => $key) {
        unset($templates[$key]);
        $txt = trim(str_replace("\r\n", "\n", (string) ($_POST['qfu_' . $part] ?? '')));
        if ($txt !== '' && $txt !== qfu_defaults()[$part]) $templates[$key] = mb_substr($txt, 0, $limits[$part]);
    }

    try {
        db()->prepare(
            'INSERT INTO client_settings (client_id, qfu_on, qfu_days, qfu_method, sms_templates)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE qfu_on = VALUES(qfu_on), qfu_days = VALUES(qfu_days),
                                     qfu_method = VALUES(qfu_method), sms_templates = VALUES(sms_templates)'
        )->execute([$clientId, $on, $days, $method, $templates ? json_encode($templates, JSON_UNESCAPED_UNICODE) : null]);
        $how = ['sms' => 'by text', 'email' => 'by email', 'both' => 'by text and email'][$method];
        $_SESSION['flash_success'] = $on
            ? "Quote follow-up is on: {$days} day" . ($days === 1 ? '' : 's') . " after a quote is sent, {$how}."
            : 'Quote follow-up is off.';
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
