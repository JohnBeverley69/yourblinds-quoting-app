<?php
declare(strict_types=1);

/**
 * The SMS Works delivery reports → messages.status.
 *
 * Each text we send carries deliveryreporturl = this script with ?k=<secret>
 * (see sms_webhook_url()). The SMS Works doesn't sign its webhooks, so the
 * secret in the URL is the auth; a wrong/missing key gets 403 and touches
 * nothing. The only thing a report can do is move the status of a message we
 * already sent (looked up by the provider's messageid).
 *
 * Replies (later add-on): an inbound text arrives with messagetype 'incoming'
 * and a `to` number. That will be routed by TO number → company, FROM number →
 * customer and stored as direction 'in'. Until then inbound posts are
 * acknowledged and ignored.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../_partials/sms.php';

// No login — The SMS Works isn't signed in. The URL key below is the auth.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$key  = (string) ($_GET['k'] ?? '');
$want = pc_get('SMS_WEBHOOK_KEY', '') ?? '';
if ($want === '' || !hash_equals($want, $key)) {
    http_response_code(403);
    exit('Forbidden');
}

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    exit('Bad JSON');
}

if (($data['messagetype'] ?? '') === 'incoming') {
    exit('OK (replies not enabled)');
}

$ref    = trim((string) ($data['messageid'] ?? ''));
$status = strtoupper(trim((string) ($data['status'] ?? '')));
if ($ref === '' || !sms_ready()) {
    exit('OK');
}

sms_apply_provider_status($ref, $status, $data['failurereason'] ?? null);

echo 'OK';
