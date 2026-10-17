<?php
declare(strict_types=1);

/**
 * Email log: every customer / trade-account / supplier email a company sends
 * is recorded in `messages` (channel 'email') so it shows on the Messages
 * page next to the texts, with whether it went (BlindDesk's "Email Log").
 *
 * Called from mailer_send() when the caller passes $opts['log'] — so the
 * record is written whatever happened (sent, failed, paused).
 *
 * Privacy: only what's needed to answer "did it go?" is kept — recipient,
 * subject, date, status and the attachment's NAME. Never the message text or
 * the file. Entries are deleted after 12 months (sms_purge_old(), run by
 * cron_messages.php). Never throws: logging must not break a send.
 *
 * "Sent" means our mail server accepted it. Bounces (an address that doesn't
 * exist) come back to the send-from mailbox and are not tracked yet.
 */

/** Kinds of email, for labels on the Messages page. */
function email_log_kinds(): array
{
    return [
        'quote'            => 'Quote',
        'quote_accepted'   => 'Quote accepted',
        'invoice'          => 'Invoice',
        'receipt'          => 'Receipt',
        'appointment'      => 'Appointment',
        'quote_follow_up'  => 'Quote follow-up',
        'remake'           => 'Remake',
        'account_document' => 'Invoice / credit note',
        'statement'        => 'Statement',
        'supplier_order'   => 'Supplier order',
        'texts_low'        => 'Texts running low',
        'texts_out'        => 'Texts stopped',
    ];
}

/**
 * @param array        $log  client_id, kind, and optionally customer_id, quote_id, user_id
 * @param string|array $to
 */
function email_log_record(array $log, $to, string $subject, string $body, ?string $attachmentName,
                          bool $ok, ?string $note, array $opts = []): void
{
    $clientId = (int) ($log['client_id'] ?? 0);
    if ($clientId <= 0) return;
    try {
        $pdo = db();
        static $hasQuoteCol = null;
        if ($hasQuoteCol === null) {
            $hasQuoteCol = (bool) $pdo->query("SHOW COLUMNS FROM messages LIKE 'quote_id'")->fetchColumn();
        }

        if ($note === 'paused') {
            $status = 'blocked';
            $detail = 'Email sending was paused (testing mode) — not sent.';
        } else {
            $status = $ok ? 'sent' : 'failed';
            $detail = $note;
        }
        $toList = implode(', ', array_filter(array_map(static fn ($r) => trim((string) $r), (array) $to)));
        $text   = $subject . ($attachmentName ? "\n[Attached: " . $attachmentName . ']' : '');   // never the body

        $cols = ['client_id', 'channel', 'direction', 'kind', 'customer_id', 'user_id',
                 'to_addr', 'from_addr', 'body', 'segments', 'status', 'status_detail', 'provider', 'sent_at'];
        $vals = [$clientId, 'email', 'out', (string) ($log['kind'] ?? ''),
                 !empty($log['customer_id']) ? (int) $log['customer_id'] : null,
                 !empty($log['user_id']) ? (int) $log['user_id'] : null,
                 mb_substr($toList, 0, 190), mb_substr((string) ($opts['from_name'] ?? ''), 0, 190),
                 mb_substr($text, 0, 60000), 0, $status, $detail !== null ? mb_substr($detail, 0, 255) : null,
                 'smtp', $status === 'sent' ? date('Y-m-d H:i:s') : null];
        if ($hasQuoteCol && !empty($log['quote_id'])) {
            $cols[] = 'quote_id';
            $vals[] = (int) $log['quote_id'];
        }
        $pdo->prepare('INSERT INTO messages (' . implode(', ', $cols) . ') VALUES ('
                      . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute($vals);
    } catch (Throwable $e) {
        error_log('email_log_record: ' . $e->getMessage());   // no messages table yet, etc.
    }
}
