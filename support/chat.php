<?php
declare(strict_types=1);

/**
 * POST endpoint for the AI support chat in the Help widget.
 *
 *   action=status  → is the assistant available for this user?
 *   action=send    → one user message; returns the assistant's reply
 *   action=reset   → start a fresh conversation
 *
 * The conversation lives in the PHP session (append-only), so it follows the
 * user from page to page. Always answers JSON. When the assistant can't be
 * used (off, cap reached, key expired, API down) the reply says so and the
 * widget falls back to the plain report form — nobody is left stuck.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/support_ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$reply = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $reply(405, ['ok' => false, 'error' => 'POST only.']);
}
if (!is_logged_in()) {
    $reply(401, ['ok' => false, 'error' => 'Your session has expired — sign in again.']);
}
$supplied = $_POST['_csrf'] ?? '';
if (!is_string($supplied) || !hash_equals(csrf_token(), $supplied)) {
    $reply(419, ['ok' => false, 'error' => 'This page has expired — refresh it and try again.']);
}

$user   = current_user();
$action = (string) ($_POST['action'] ?? 'send');

$newChat = static fn (): array => ['id' => bin2hex(random_bytes(8)), 'messages' => [], 'turns' => 0, 'tickets' => []];
$chat = $_SESSION['_support_chat'] ?? null;
if (!is_array($chat) || !isset($chat['id'])) {
    $chat = $newChat();
}

// Visible history for re-drawing the panel on a new page: user text and
// assistant text only.
$visible = static function (array $chat): array {
    $out = [];
    foreach ($chat['messages'] as $m) {
        if (is_string($m['content'])) {
            $out[] = ['role' => 'user', 'text' => preg_replace('/^\[Context:[^\n]*\]\n/', '', $m['content'])];
        } elseif ($m['role'] === 'assistant') {
            $t = trim(implode("\n\n", array_map(static fn ($b) => (string) ($b['text'] ?? ''),
                array_filter($m['content'], static fn ($b) => ($b['type'] ?? '') === 'text'))));
            if ($t !== '') $out[] = ['role' => 'assistant', 'text' => $t];
        }
    }
    return $out;
};

$why = support_ai_unavailable_reason($user);
$whyText = [
    'off'         => null,
    'key_expired' => 'The assistant is resting just now — use the form below and the team will pick it up.',
    'cap'         => 'The assistant has had a busy month and is resting — use the form below and the team will pick it up.',
    'daily'       => 'You\'ve reached today\'s chat limit — use the form below and the team will pick it up.',
];

if ($action === 'reset') {
    $_SESSION['_support_chat'] = $newChat();
    $reply(200, ['ok' => true, 'available' => $why === null, 'history' => []]);
}

if ($action === 'status') {
    $reply(200, [
        'ok'        => true,
        'available' => $why === null,
        'notice'    => $why !== null ? ($whyText[$why] ?? null) : null,
        'history'   => $visible($chat),
    ]);
}

// ---- send ------------------------------------------------------------
// Serialise this user's turns, then re-check the limits INSIDE the lock so
// concurrent requests can't all pass the checks before any usage is recorded.
if ($why === null) {
    if (!support_ai_acquire_turn_lock((int) $user['user_id'])) {
        $reply(429, ['ok' => false, 'error' => 'Still working on your last message — give it a moment.']);
    }
    register_shutdown_function('support_ai_release_turn_lock', (int) $user['user_id']);
    $why = support_ai_unavailable_reason($user);
}
if ($why !== null) {
    $reply(200, ['ok' => false, 'fallback' => true, 'error' => $whyText[$why] ?? 'The assistant isn\'t available — please use the form.']);
}
$text = support_clip($_POST['message'] ?? '', 4000);
if ($text === '') {
    $reply(422, ['ok' => false, 'error' => 'Type a message first.']);
}
if ($chat['turns'] >= SUPPORT_AI_MAX_TURNS) {
    $reply(200, ['ok' => false, 'error' => 'This chat is getting long — press "New chat" to start fresh.']);
}

// Release the session lock during the (slow) API call so other tabs and
// pages aren't blocked; re-open it to save the result.
session_write_close();
@set_time_limit(180);
$res = support_ai_turn($chat, $user, $text, support_capture_ctx($_POST));
@session_start();
$_SESSION['_support_chat'] = $chat;
try { support_ai_alerts_if_due(); } catch (Throwable $e) { error_log('[YourBlinds] support_ai alerts: ' . $e->getMessage()); }

if ($res['error'] !== null) {
    $msg = $res['error'] === 'auth'
        ? 'The assistant isn\'t set up correctly just now — please use the form below.'
        : 'Sorry — I couldn\'t reach the assistant. Please try again, or use the form below.';
    $reply(200, ['ok' => false, 'fallback' => true, 'error' => $msg]);
}

$reply(200, ['ok' => true, 'reply' => $res['reply'], 'tickets' => $res['tickets']]);
