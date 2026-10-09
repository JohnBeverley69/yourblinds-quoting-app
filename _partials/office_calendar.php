<?php
declare(strict_types=1);

/**
 * Office calendar — Factory Console stage 4 (John, 2026-10-09).
 *
 * One SHARED list for the factory office (not per person), strictly internal.
 *   note      for a day only
 *   reminder  ticked when done; until then it carries forward and shows as due
 *   callback  a reminder tied to a trade account (and optionally an order); it
 *             also shows on that account's page
 * Remake due dates (factory_remakes.due_date, not yet dispatched) are shown on the
 * calendar automatically — read-only, they come from the remake itself.
 * "Due" (dashboard + menu badge) = open reminders/callbacks dated today or earlier,
 * plus remakes due today or earlier that haven't gone out.
 *
 * Requires migrate_office_calendar.php; everything degrades to "not available".
 */

require_once __DIR__ . '/factory_ar.php';

function oc_ready(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try { $pdo->query('SELECT 1 FROM factory_office_entries LIMIT 0'); $ok = true; }
    catch (Throwable $e) { $ok = false; }
    return $ok;
}

/** kind => [label, text colour, background]. */
function oc_kinds(): array
{
    return [
        'note'     => ['Note',     '#475569', '#e2e8f0'],
        'reminder' => ['Reminder', '#92400e', '#fef3c7'],
        'callback' => ['Callback', '#1e40af', '#dbeafe'],
    ];
}

function oc_chip(string $kind): string
{
    $k = oc_kinds()[$kind] ?? ['Remake', '#ffffff', '#7c3aed'];
    return '<span class="oc-chip" style="color:' . $k[1] . ';background:' . $k[2] . '">' . htmlspecialchars($k[0], ENT_QUOTES, 'UTF-8') . '</span>';
}

/** Account choices [id => name]. */
function oc_accounts(PDO $pdo, int $factory): array
{
    $out = [];
    // Every business on the platform bar the factory itself — the same list as
    // Trade accounts, so an account with no orders yet can still be called back.
    try {
        $st = $pdo->prepare("SELECT id, company_name FROM clients WHERE id <> ? AND company_name <> '' ORDER BY company_name");
        $st->execute([$factory]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) $out[(int) $a['id']] = (string) $a['company_name'];
    } catch (Throwable $e) { /* none */ }
    asort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/** Find an order by its number (scoped to the account when given). Returns quote id or 0. */
function oc_find_order(PDO $pdo, string $ref, int $accountId): int
{
    $ref = trim($ref);
    if ($ref === '') return 0;
    $sql = 'SELECT id FROM quotes WHERE quote_number = ?';
    $args = [$ref];
    if ($accountId > 0) { $sql .= ' AND (client_id = ? OR account_client_id = ?)'; $args[] = $accountId; $args[] = $accountId; }
    $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT 2');
    $st->execute($args);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    return count($ids) === 1 ? (int) $ids[0] : 0;
}

/**
 * Add or update an entry from posted fields. Returns the id.
 * Throws RuntimeException with a user-facing message.
 */
function oc_save(PDO $pdo, int $factory, array $in, int $userId, int $id = 0): int
{
    if (!oc_ready($pdo)) throw new RuntimeException('The office calendar isn’t switched on yet.');
    $kind = (string) ($in['kind'] ?? 'note');
    if (!isset(oc_kinds()[$kind])) $kind = 'note';
    $date = (string) ($in['entry_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) throw new RuntimeException('Choose a date.');
    $time = trim((string) ($in['entry_time'] ?? ''));
    $time = preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : null;
    $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 255);
    if ($title === '') throw new RuntimeException('Say what it is — e.g. “Order hangers”.');
    $detail = trim((string) ($in['detail'] ?? ''));
    $acct = (int) ($in['account_client_id'] ?? 0);
    if ($acct > 0 && !isset(oc_accounts($pdo, $factory)[$acct])) $acct = 0;
    if ($kind === 'callback' && $acct <= 0) throw new RuntimeException('Choose which account to call back.');
    $orderRef = mb_substr(trim((string) ($in['order_ref'] ?? '')), 0, 60);
    $quoteId  = $orderRef !== '' ? oc_find_order($pdo, $orderRef, $acct) : 0;

    $vals = [$kind, $date, $time, $title, $detail !== '' ? mb_substr($detail, 0, 4000) : null,
             $acct ?: null, $orderRef !== '' ? $orderRef : null, $quoteId ?: null];
    if ($id > 0) {
        $st = $pdo->prepare('UPDATE factory_office_entries SET kind=?, entry_date=?, entry_time=?, title=?, detail=?,
                                    account_client_id=?, order_ref=?, quote_id=? WHERE id=? AND factory_client_id=?');
        $st->execute([...$vals, $id, $factory]);
        return $id;
    }
    $pdo->prepare('INSERT INTO factory_office_entries (kind, entry_date, entry_time, title, detail, account_client_id, order_ref,
                          quote_id, factory_client_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([...$vals, $factory, $userId ?: null]);
    return (int) $pdo->lastInsertId();
}

function oc_get(PDO $pdo, int $factory, int $id): ?array
{
    if (!oc_ready($pdo)) return null;
    $st = $pdo->prepare('SELECT e.*, c.company_name AS account_name FROM factory_office_entries e
                           LEFT JOIN clients c ON c.id = e.account_client_id
                          WHERE e.id = ? AND e.factory_client_id = ? LIMIT 1');
    $st->execute([$id, $factory]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function oc_set_done(PDO $pdo, int $factory, int $id, bool $done, int $userId): void
{
    $pdo->prepare('UPDATE factory_office_entries SET done_at = ' . ($done ? 'NOW()' : 'NULL') . ', done_by_user_id = ?
                    WHERE id = ? AND factory_client_id = ? AND kind <> \'note\'')
        ->execute([$done ? ($userId ?: null) : null, $id, $factory]);
}

function oc_delete(PDO $pdo, int $factory, int $id): void
{
    $pdo->prepare('DELETE FROM factory_office_entries WHERE id = ? AND factory_client_id = ?')->execute([$id, $factory]);
}

/** Entries dated in [from, to], by day. */
function oc_range(PDO $pdo, int $factory, string $from, string $to): array
{
    if (!oc_ready($pdo)) return [];
    $st = $pdo->prepare('SELECT e.*, c.company_name AS account_name FROM factory_office_entries e
                           LEFT JOIN clients c ON c.id = e.account_client_id
                          WHERE e.factory_client_id = ? AND e.entry_date BETWEEN ? AND ?
                       ORDER BY e.entry_date, e.entry_time IS NULL, e.entry_time, e.id');
    $st->execute([$factory, $from, $to]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['entry_date']][] = $r;
    return $out;
}

/** Open reminders/callbacks dated on or before $upTo (overdue first). $accountId narrows to one account. */
function oc_open(PDO $pdo, int $factory, string $upTo, int $accountId = 0): array
{
    if (!oc_ready($pdo)) return [];
    $sql = 'SELECT e.*, c.company_name AS account_name FROM factory_office_entries e
              LEFT JOIN clients c ON c.id = e.account_client_id
             WHERE e.factory_client_id = ? AND e.kind <> \'note\' AND e.done_at IS NULL AND e.entry_date <= ?';
    $args = [$factory, $upTo];
    if ($accountId > 0) { $sql .= ' AND e.account_client_id = ?'; $args[] = $accountId; }
    $st = $pdo->prepare($sql . ' ORDER BY e.entry_date, e.entry_time IS NULL, e.entry_time, e.id');
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Every open callback for one account, whatever its date (for the account page). */
function oc_account_callbacks(PDO $pdo, int $factory, int $accountId): array
{
    return oc_open($pdo, $factory, '9999-12-31', $accountId);
}

/** Approved remakes with a due date in [from, to] that haven't gone out, by day. */
function oc_remakes_due(PDO $pdo, int $factory, string $from, string $to): array
{
    $out = [];
    try {
        $st = $pdo->prepare("SELECT r.id, r.due_date, r.reason_label, rq.quote_number AS remake_number, r.remake_quote_id,
                                    c.company_name AS account_name
                               FROM factory_remakes r
                               JOIN quotes rq      ON rq.id = r.remake_quote_id
                               LEFT JOIN clients c ON c.id = r.account_client_id
                              WHERE r.factory_client_id = ? AND r.status = 'approved' AND r.due_date BETWEEN ? AND ?
                                AND COALESCE(rq.fulfilment_stage, '') <> 'dispatched'
                           ORDER BY r.due_date, r.id");
        $st->execute([$factory, $from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(string) $r['due_date']][] = $r;
    } catch (Throwable $e) { /* remakes not migrated */ }
    return $out;
}

/** How many things are due now — the dashboard + menu badge. */
function oc_due_count(PDO $pdo, int $factory): int
{
    $n = 0;
    if (oc_ready($pdo)) {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM factory_office_entries
                                  WHERE factory_client_id = ? AND kind <> 'note' AND done_at IS NULL AND entry_date <= CURDATE()");
            $st->execute([$factory]);
            $n += (int) $st->fetchColumn();
        } catch (Throwable $e) { /* ignore */ }
    }
    foreach (oc_remakes_due($pdo, $factory, '2000-01-01', date('Y-m-d')) as $day) $n += count($day);
    return $n;
}

/** Where an entry's order link goes (factory side). */
function oc_order_url(PDO $pdo, int $factory, int $quoteId): string
{
    $st = $pdo->prepare('SELECT client_id FROM quotes WHERE id = ? LIMIT 1');
    $st->execute([$quoteId]);
    return ((int) $st->fetchColumn()) === $factory
        ? '/quote-builder/edit.php?id=' . $quoteId
        : '/factory/edit-order.php?order=' . $quoteId;
}

/** The add/edit fields (caller owns <form> + CSRF + buttons). */
function oc_form_fields(array $e, array $accounts): void
{
    $kind = (string) ($e['kind'] ?? 'reminder');
    ?>
    <div class="oc-form">
      <div class="oc-kinds" role="radiogroup" aria-label="Type">
        <?php foreach (oc_kinds() as $k => [$label]): ?>
          <label><input type="radio" name="kind" value="<?= e($k) ?>"<?= $kind === $k ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
      <label for="ocTitle">What</label>
      <input type="text" id="ocTitle" name="title" maxlength="255" required value="<?= e((string) ($e['title'] ?? '')) ?>"
             placeholder="e.g. Order hangers · Saw blade on number two saw · Ring about the fabric issue">
      <div class="oc-row">
        <div><label for="ocDate">Date</label>
          <input type="date" id="ocDate" name="entry_date" required value="<?= e((string) ($e['entry_date'] ?? date('Y-m-d'))) ?>"></div>
        <div><label for="ocTime">Time <span class="oc-faint">(optional)</span></label>
          <input type="time" id="ocTime" name="entry_time" value="<?= e(substr((string) ($e['entry_time'] ?? ''), 0, 5)) ?>"></div>
      </div>
      <div class="oc-row">
        <div><label for="ocAcct">Account <span class="oc-faint oc-acct-hint">(needed for a callback)</span></label>
          <select id="ocAcct" name="account_client_id">
            <option value="0">—</option>
            <?php foreach ($accounts as $id => $nm): ?>
              <option value="<?= (int) $id ?>"<?= (int) ($e['account_client_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= e($nm) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label for="ocOrder">Order number <span class="oc-faint">(optional)</span></label>
          <input type="text" id="ocOrder" name="order_ref" maxlength="60" value="<?= e((string) ($e['order_ref'] ?? '')) ?>" placeholder="e.g. ABC-2026-0004"></div>
      </div>
      <label for="ocDetail">Notes <span class="oc-faint">(optional)</span></label>
      <textarea id="ocDetail" name="detail" maxlength="4000" rows="3"><?= e((string) ($e['detail'] ?? '')) ?></textarea>
    </div>
    <?php
}

/** Shared styles for the calendar pages. */
function oc_styles(): string
{
    return '<style>
      .oc-chip { display:inline-block; font-size:.6875rem; font-weight:700; border-radius:999px; padding:0 .45rem; white-space:nowrap; }
      .oc-faint { color:var(--text-faint); font-weight:400; font-size:.85rem; }
      .oc-form { display:flex; flex-direction:column; gap:.45rem; }
      .oc-form label { font-weight:600; font-size:.875rem; color:var(--text-secondary); }
      .oc-form input[type=text], .oc-form input[type=date], .oc-form input[type=time], .oc-form select, .oc-form textarea {
            width:100%; padding:.45rem .6rem; border:1px solid var(--border-strong); border-radius:8px;
            background:var(--bg-input); color:var(--text-body); font:inherit; }
      .oc-row { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
      .oc-row > div { display:flex; flex-direction:column; gap:.3rem; min-width:0; }
      .oc-kinds { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:.2rem; }
      .oc-kinds label { font-weight:600; display:flex; gap:.35rem; align-items:center; }
      @media (max-width:560px){ .oc-row { grid-template-columns:1fr; } }
    </style>';
}
