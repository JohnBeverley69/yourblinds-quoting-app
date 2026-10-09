<?php
declare(strict_types=1);

/**
 * Remakes — Factory Console stage 3 (John, 2026-10-09).
 *
 * A remake starts on the ORIGINAL order: some of its blinds (and how many), a
 * reason from the factory's own list, a note, optionally a photo. It is raised
 * either by the factory office (approved there and then) or by the trade account
 * from their own order ("requested" until the office approves or declines it).
 *
 * Approving decides who pays — free (our fault), charged to the account (any
 * amount, decided on merit) or free and logged as a supplier claim — and turns the
 * remake into a REMAKE ORDER: a new placed order owned like the original (same
 * account), lines copied, numbered <original>-R1, -R2 … and tagged
 * quotes.remake_of_quote_id. From there it is an ordinary order: it lands in
 * Incoming orders as New, goes through the floor, dispatch and delivery note.
 *
 * Money:
 *   cost          = the TRADE PRICE of the blinds remade (labour is in that price),
 *                   taken from what the original order bills — John's rule.
 *   charge_amount = what the account is billed for the remake (0 when free). The
 *                   remake order's invoice bills exactly that, spread over its lines,
 *                   so a free remake shows on the invoice as £0 lines (John's rule).
 *
 * Requires migrate_remakes.php. Everything degrades to "not available" before it.
 */

require_once __DIR__ . '/factory_ar.php';
require_once __DIR__ . '/order_stage.php';

function rm_ready(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $pdo->query('SELECT 1 FROM factory_remakes LIMIT 0');
        $pdo->query('SELECT remake_of_quote_id FROM quotes LIMIT 0');
        $ok = true;
    } catch (Throwable $e) { $ok = false; }
    return $ok;
}

/** Who-pays choices => label. */
function rm_charge_modes(): array
{
    return [
        'free'     => 'Free — our fault',
        'charge'   => 'Charge the account',
        'supplier' => 'Free — supplier claim',
    ];
}

/** The reasons the list starts with; the factory edits its own copy. */
function rm_default_reasons(): array
{
    return ['Measuring error', 'Fitting damage', 'Factory fault', 'Fabric fault', 'Wrong spec', 'Other'];
}

/**
 * The factory's reasons [id => label]. Seeds the defaults the first time.
 * $activeOnly false also returns retired ones (for the editor).
 */
function rm_reasons(PDO $pdo, int $factory, bool $activeOnly = true): array
{
    if (!rm_ready($pdo)) return [];
    $load = static function () use ($pdo, $factory, $activeOnly): array {
        $st = $pdo->prepare('SELECT id, label, active, sort_order FROM factory_remake_reasons
                              WHERE factory_client_id = ?' . ($activeOnly ? ' AND active = 1' : '') . '
                           ORDER BY sort_order, id');
        $st->execute([$factory]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    };
    $rows = $load();
    if (!$rows) {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM factory_remake_reasons WHERE factory_client_id = ?');
        $cnt->execute([$factory]);
        if ((int) $cnt->fetchColumn() === 0) {
            $ins = $pdo->prepare('INSERT INTO factory_remake_reasons (factory_client_id, label, sort_order) VALUES (?, ?, ?)');
            foreach (rm_default_reasons() as $i => $label) $ins->execute([$factory, $label, ($i + 1) * 10]);
            $rows = $load();
        }
    }
    if (!$activeOnly) return $rows;
    $out = [];
    foreach ($rows as $r) $out[(int) $r['id']] = (string) $r['label'];
    return $out;
}

/**
 * The factory-made/supplied blinds on an order that can be remade, each with its
 * trade price per blind (what the original order bills, per blind).
 * Rows: id, line_no, room_name, product, system, fabric, width_mm, drop_mm, quantity, unit_trade.
 */
function rm_order_lines(PDO $pdo, int $factory, int $quoteId): array
{
    $lines = ar_order_lines_for_doc($pdo, $factory, $quoteId);
    if (!$lines) return [];
    $unitBy = [];
    try {
        foreach (ar_invoice_lines_from_order($pdo, $factory, $quoteId, false)['lines'] as $l) {
            if (($l['line_type'] ?? '') === 'blind' && $l['source_quote_item_id']) {
                $q = max(1, (int) $l['quantity']);
                $unitBy[(int) $l['source_quote_item_id']] = round((float) $l['line_net'] / $q, 2);
            }
        }
    } catch (Throwable $e) { /* cost unknown — 0 */ }
    $out = [];
    foreach ($lines as $l) {
        $fab = trim(implode(' / ', array_filter([(string) $l['fabric_name_snapshot'], (string) $l['fabric_colour_snapshot']],
            static fn ($s) => trim($s) !== '')));
        $out[] = [
            'id'         => (int) $l['id'],
            'line_no'    => (int) $l['line_no'],
            'room_name'  => (string) ($l['room_name'] ?? ''),
            'product'    => (string) $l['product_name_snapshot'],
            'system'     => (string) ($l['system_name_snapshot'] ?? ''),
            'fabric'     => $fab,
            'width_mm'   => $l['width_mm'],
            'drop_mm'    => $l['drop_mm'],
            'quantity'   => max(1, (int) $l['quantity']),
            'unit_trade' => $unitBy[(int) $l['id']] ?? 0.0,
        ];
    }
    return $out;
}

/** One-line description of a blind for lists. */
function rm_line_label(array $l): string
{
    $s = trim($l['product'] . ($l['system'] !== '' ? ' — ' . $l['system'] : ''));
    if ($l['fabric'] !== '') $s .= ', ' . $l['fabric'];
    if ($l['width_mm'] && $l['drop_mm']) $s .= ' · ' . (int) $l['width_mm'] . ' × ' . (int) $l['drop_mm'];
    if ($l['room_name'] !== '') $s = $l['room_name'] . ': ' . $s;
    return $s;
}

/** The placed order behind a remake, checked to be one the factory supplies. */
function rm_source_order(PDO $pdo, int $factory, int $quoteId): ?array
{
    $st = $pdo->prepare('SELECT q.*, c.company_name AS tenant_name FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ? LIMIT 1');
    $st->execute([$quoteId]);
    $q = $st->fetch(PDO::FETCH_ASSOC);
    if (!$q || !in_array((string) $q['status'], os_placed_statuses(), true)) return null;
    if (!rm_order_lines($pdo, $factory, $quoteId)) return null;
    $acct = (int) ($q['account_client_id'] ?? 0) > 0 ? (int) $q['account_client_id'] : (int) $q['client_id'];
    $q['_account_id'] = $acct;
    return $q;
}

/**
 * Save a photo upload (optional) into uploads/remakes/. Returns the relative path,
 * '' when none was sent. Throws RuntimeException with a user-facing message.
 */
function rm_store_photo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('The photo did not upload — please try again.');
    if ((int) ($file['size'] ?? 0) > 12 * 1024 * 1024) throw new RuntimeException('That photo is too large (12 MB at most).');
    $info = @getimagesize((string) $file['tmp_name']);
    $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2] ?? 0] ?? '';
    if ($ext === '') throw new RuntimeException('The photo must be a JPG, PNG, WebP or GIF picture.');
    $dir = __DIR__ . '/../uploads/remakes';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Could not save the photo — please try again.');
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    $name = 'rm_' . bin2hex(random_bytes(10)) . '.' . $ext;
    if (!@move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the photo — please try again.');
    return 'uploads/remakes/' . $name;
}

/**
 * Raise a remake on an order. $items = [source_item_id => qty]. Returns the new id.
 * Throws RuntimeException with a user-facing message.
 */
function rm_create(PDO $pdo, int $factory, int $quoteId, array $items, int $reasonId, string $note,
                   string $photoPath, string $raisedBy, int $userId): int
{
    if (!rm_ready($pdo)) throw new RuntimeException('Remakes are not switched on yet.');
    $src = rm_source_order($pdo, $factory, $quoteId);
    if (!$src) throw new RuntimeException('That order can’t have a remake — it isn’t a placed order with our blinds on it.');
    $lines = [];
    foreach (rm_order_lines($pdo, $factory, $quoteId) as $l) $lines[$l['id']] = $l;
    $chosen = [];
    foreach ($items as $iid => $qty) {
        $iid = (int) $iid; $qty = (int) $qty;
        if ($qty <= 0 || !isset($lines[$iid])) continue;
        $chosen[$iid] = min($qty, $lines[$iid]['quantity']);
    }
    if (!$chosen) throw new RuntimeException('Tick at least one blind to remake.');
    $reasons = rm_reasons($pdo, $factory);
    if (!isset($reasons[$reasonId])) throw new RuntimeException('Choose a reason for the remake.');

    $cost = 0.0;
    foreach ($chosen as $iid => $qty) $cost += $lines[$iid]['unit_trade'] * $qty;

    $own = $pdo->inTransaction() ? false : $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO factory_remakes
                (factory_client_id, account_client_id, source_quote_id, status, reason_id, reason_label, note,
                 photo_path, raised_by, raised_by_user_id, cost)
                VALUES (?, ?, ?, \'requested\', ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$factory, (int) $src['_account_id'], $quoteId, $reasonId, $reasons[$reasonId],
                       trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null,
                       $photoPath !== '' ? $photoPath : null, $raisedBy === 'account' ? 'account' : 'factory',
                       $userId ?: null, round($cost, 2)]);
        $rid = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO factory_remake_items (remake_id, source_item_id, quantity) VALUES (?, ?, ?)');
        foreach ($chosen as $iid => $qty) $ins->execute([$rid, $iid, $qty]);
        if ($own) $pdo->commit();
        return $rid;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** One remake with its source order number/account name, or null. */
function rm_get(PDO $pdo, int $factory, int $id): ?array
{
    if (!rm_ready($pdo)) return null;
    $st = $pdo->prepare('SELECT r.*, q.quote_number AS source_number, q.customer_reference AS source_ref,
                                rq.quote_number AS remake_number, rq.fulfilment_stage AS remake_stage,
                                c.company_name AS account_name
                           FROM factory_remakes r
                           JOIN quotes q        ON q.id = r.source_quote_id
                           LEFT JOIN quotes rq  ON rq.id = r.remake_quote_id
                           LEFT JOIN clients c  ON c.id = r.account_client_id
                          WHERE r.id = ? AND r.factory_client_id = ? LIMIT 1');
    $st->execute([$id, $factory]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/** [remake_id => [[source_item_id, quantity, label], …]] for a set of remakes. */
function rm_items_for(PDO $pdo, int $factory, array $remakes): array
{
    $out = [];
    $bySource = [];
    foreach ($remakes as $r) $bySource[(int) $r['source_quote_id']][] = (int) $r['id'];
    if (!$bySource) return [];
    $ids = array_merge(...array_values($bySource));
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT remake_id, source_item_id, quantity FROM factory_remake_items WHERE remake_id IN ($ph) ORDER BY id");
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $labels = [];
    foreach (array_keys($bySource) as $qid) {
        foreach (rm_order_lines($pdo, $factory, $qid) as $l) $labels[$l['id']] = rm_line_label($l);
    }
    foreach ($rows as $r) {
        $out[(int) $r['remake_id']][] = [
            'source_item_id' => (int) $r['source_item_id'],
            'quantity'       => (int) $r['quantity'],
            'label'          => $labels[(int) $r['source_item_id']] ?? 'Blind (no longer on the order)',
        ];
    }
    return $out;
}

/**
 * Approve a remake: record who pays, then create the remake order. Returns the
 * remake order id. Throws RuntimeException with a user-facing message.
 */
function rm_approve(PDO $pdo, int $factory, int $id, string $mode, float $charge, string $supplier,
                    string $dueDate, int $userId): int
{
    $r = rm_get($pdo, $factory, $id);
    if (!$r) throw new RuntimeException('That remake no longer exists.');
    if ($r['status'] !== 'requested') throw new RuntimeException('That remake has already been ' . $r['status'] . '.');
    if (!isset(rm_charge_modes()[$mode])) throw new RuntimeException('Choose who pays for the remake.');
    $charge = $mode === 'charge' ? round(max(0.0, $charge), 2) : 0.0;
    if ($mode === 'charge' && $charge <= 0) throw new RuntimeException('Enter what to charge the account, or choose a free option.');
    $supplier = $mode === 'supplier' ? mb_substr(trim($supplier), 0, 120) : '';
    if ($mode === 'supplier' && $supplier === '') throw new RuntimeException('Enter which supplier the claim is against.');
    $due = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) && strtotime($dueDate)) ? $dueDate : null;

    $own = $pdo->inTransaction() ? false : $pdo->beginTransaction();
    try {
        $newId = rm_create_remake_order($pdo, $factory, $r, $charge, $due);
        $pdo->prepare("UPDATE factory_remakes SET status = 'approved', remake_quote_id = ?, charge_mode = ?, charge_amount = ?,
                              supplier_name = ?, due_date = ?, decided_by_user_id = ?, decided_at = NOW()
                        WHERE id = ? AND factory_client_id = ?")
            ->execute([$newId, $mode, $charge, $supplier !== '' ? $supplier : null, $due, $userId ?: null, $id, $factory]);
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    try { recompute_order_stage($pdo, $newId); } catch (Throwable $e) { /* best-effort */ }
    return $newId;
}

function rm_decline(PDO $pdo, int $factory, int $id, string $reason, int $userId): void
{
    $r = rm_get($pdo, $factory, $id);
    if (!$r) throw new RuntimeException('That remake no longer exists.');
    if ($r['status'] !== 'requested') throw new RuntimeException('That remake has already been ' . $r['status'] . '.');
    $reason = mb_substr(trim($reason), 0, 255);
    if ($reason === '') throw new RuntimeException('Say briefly why it’s declined — the account sees this.');
    $pdo->prepare("UPDATE factory_remakes SET status = 'declined', decline_reason = ?, decided_by_user_id = ?, decided_at = NOW()
                    WHERE id = ? AND factory_client_id = ?")
        ->execute([$reason, $userId ?: null, $id, $factory]);
}

/**
 * Copy the original order's chosen blinds into a new placed order, tagged as a
 * remake. Prices on the remake order carry the CHARGE (0 when free) so the
 * account's own screen and the invoice agree.
 */
function rm_create_remake_order(PDO $pdo, int $factory, array $r, float $charge, ?string $due): int
{
    $srcId = (int) $r['source_quote_id'];
    $st = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
    $st->execute([$srcId]);
    $q = $st->fetch(PDO::FETCH_ASSOC);
    if (!$q) throw new RuntimeException('The original order is missing.');

    $items = [];
    foreach (rm_items_for($pdo, $factory, [$r])[(int) $r['id']] ?? [] as $it) $items[$it['source_item_id']] = $it['quantity'];
    if (!$items) throw new RuntimeException('None of the blinds on this remake are on the order any more.');

    // Number: <original>-R1, -R2 …
    $n = $pdo->prepare('SELECT COUNT(*) FROM quotes WHERE remake_of_quote_id = ?');
    $n->execute([$srcId]);
    $seq = (int) $n->fetchColumn() + 1;
    do {
        $number = $q['quote_number'] . '-R' . $seq;
        $chk = $pdo->prepare('SELECT 1 FROM quotes WHERE client_id = ? AND quote_number = ? LIMIT 1');
        $chk->execute([(int) $q['client_id'], $number]);
        $seq++;
    } while ($chk->fetchColumn());

    $skip = ['id', 'quote_number', 'status', 'fulfilment_stage', 'pre_paid_status', 'deposit_amount', 'deposit_paid_at',
             'public_token', 'sent_at', 'accepted_at', 'acceptance_signature_name', 'acceptance_ip',
             'acceptance_signature_png', 'acceptance_method', 'acceptance_by_user_id', 'archived_at', 'receipt_sent_at',
             'factory_notified_at', 'supplier_ordered_at', 'supplier_received_at', 'created_at', 'updated_at',
             'price_override', 'wt_amount', 'remake_of_quote_id', 'due_date', 'subtotal', 'vat', 'total',
             'additional_reference', 'notes'];
    $vatPct = (float) ($q['vat_percent'] ?? 0);
    $vat    = round($charge * $vatPct / 100, 2);
    $note   = 'REMAKE of ' . $q['quote_number'] . ' — ' . $r['reason_label']
            . (trim((string) ($r['note'] ?? '')) !== '' ? ': ' . trim((string) $r['note']) : '');
    $row = [];
    foreach ($q as $col => $val) if (!in_array($col, $skip, true)) $row[$col] = $val;
    $row += [
        'quote_number' => $number, 'status' => 'ordered', 'public_token' => bin2hex(random_bytes(16)),
        'subtotal' => $charge, 'vat' => $vat, 'total' => round($charge + $vat, 2),
        'remake_of_quote_id' => $srcId, 'due_date' => $due, 'wt_amount' => 0,
        'additional_reference' => 'Remake of ' . $q['quote_number'], 'notes' => mb_substr($note, 0, 2000),
    ];
    $cols = array_keys($row);
    $sql  = 'INSERT INTO quotes (`' . implode('`,`', $cols) . '`, created_at, updated_at) VALUES ('
          . implode(',', array_fill(0, count($cols), '?')) . ', NOW(), NOW())';
    $pdo->prepare($sql)->execute(array_values($row));
    $newId = (int) $pdo->lastInsertId();

    // Lines — copied with their options; priced at the charge, spread by trade value.
    $lines = [];
    foreach (rm_order_lines($pdo, $factory, $srcId) as $l) if (isset($items[$l['id']])) $lines[$l['id']] = $l;
    $weights = [];
    foreach ($lines as $iid => $l) $weights[$iid] = max(0.01, $l['unit_trade'] * $items[$iid]);
    $wTotal = array_sum($weights) ?: 1.0;
    $left = $charge; $k = 0; $last = count($lines);
    $itemSkip = ['id', 'quote_id', 'created_at', 'updated_at', 'line_no', 'quantity', 'sell_price', 'line_total',
                 'subtotal_per_blind'];
    $lineNo = 1;
    foreach ($lines as $iid => $l) {
        $k++;
        $qty  = $items[$iid];
        $lineTotal = $k === $last ? round($left, 2) : round($charge * $weights[$iid] / $wTotal, 2);
        $left -= $lineTotal;
        $unit = round($lineTotal / max(1, $qty), 2);

        $s = $pdo->prepare('SELECT * FROM quote_items WHERE id = ? LIMIT 1');
        $s->execute([$iid]);
        $src = $s->fetch(PDO::FETCH_ASSOC);
        if (!$src) continue;
        $irow = [];
        foreach ($src as $col => $val) if (!in_array($col, $itemSkip, true)) $irow[$col] = $val;
        $irow += ['quote_id' => $newId, 'line_no' => $lineNo++, 'quantity' => $qty,
                  'sell_price' => $unit, 'line_total' => $lineTotal, 'subtotal_per_blind' => $unit];
        $c = array_keys($irow);
        $pdo->prepare('INSERT INTO quote_items (`' . implode('`,`', $c) . '`, created_at, updated_at) VALUES ('
                      . implode(',', array_fill(0, count($c), '?')) . ', NOW(), NOW())')->execute(array_values($irow));
        $newItem = (int) $pdo->lastInsertId();

        $e = $pdo->prepare('SELECT * FROM quote_item_extras WHERE quote_item_id = ?');
        $e->execute([$iid]);
        foreach ($e->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            unset($ex['id'], $ex['created_at']);
            $ex['quote_item_id'] = $newItem;
            $ec = array_keys($ex);
            $pdo->prepare('INSERT INTO quote_item_extras (`' . implode('`,`', $ec) . '`) VALUES ('
                          . implode(',', array_fill(0, count($ec), '?')) . ')')->execute(array_values($ex));
        }
    }
    return $newId;
}

/**
 * Remake charges for a set of quote ids: [remake_order_id => charge] for those that
 * are remake orders. Used by the invoice so a remake bills exactly its charge.
 */
function rm_charges_for_quotes(PDO $pdo, array $quoteIds): array
{
    $quoteIds = array_values(array_filter(array_map('intval', $quoteIds)));
    if (!$quoteIds || !rm_ready($pdo)) return [];
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));
    $st = $pdo->prepare("SELECT remake_quote_id, charge_amount FROM factory_remakes WHERE remake_quote_id IN ($ph)");
    $st->execute($quoteIds);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['remake_quote_id']] = round((float) $r['charge_amount'], 2);
    return $out;
}

/** Remake order ids among $quoteIds => original order number (for REMAKE badges). */
function rm_remake_orders(PDO $pdo, array $quoteIds): array
{
    $quoteIds = array_values(array_filter(array_map('intval', $quoteIds)));
    if (!$quoteIds || !rm_ready($pdo)) return [];
    $ph = implode(',', array_fill(0, count($quoteIds), '?'));
    $st = $pdo->prepare("SELECT r.id, o.quote_number FROM quotes r JOIN quotes o ON o.id = r.remake_of_quote_id
                          WHERE r.id IN ($ph) AND r.remake_of_quote_id IS NOT NULL");
    $st->execute($quoteIds);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['id']] = (string) $r['quote_number'];
    return $out;
}

function rm_badge(string $originalNumber = ''): string
{
    return '<span class="rm-badge" title="' . htmlspecialchars($originalNumber !== '' ? 'Remake of ' . $originalNumber : 'Remake', ENT_QUOTES, 'UTF-8')
         . '" style="display:inline-block;font-size:.6875rem;font-weight:800;letter-spacing:.04em;border-radius:999px;'
         . 'padding:.0625rem .5rem;color:#fff;background:#7c3aed;white-space:nowrap">REMAKE</span>';
}

/** Remakes waiting for approval (dashboard / menu badge). */
function rm_waiting_count(PDO $pdo, int $factory): int
{
    if (!rm_ready($pdo)) return 0;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM factory_remakes WHERE factory_client_id = ? AND status = 'requested'");
        $st->execute([$factory]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** All remakes raised on one original order (for the account's order screen). */
function rm_for_order(PDO $pdo, int $quoteId): array
{
    if (!rm_ready($pdo)) return [];
    $st = $pdo->prepare('SELECT r.*, rq.quote_number AS remake_number, rq.fulfilment_stage AS remake_stage
                           FROM factory_remakes r LEFT JOIN quotes rq ON rq.id = r.remake_quote_id
                          WHERE r.source_quote_id = ? ORDER BY r.created_at DESC');
    $st->execute([$quoteId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Remakes for the console list. $view: waiting|open|done|all. Newest first.
 * "open" = approved and not dispatched yet; "done" = dispatched or declined.
 */
function rm_list(PDO $pdo, int $factory, string $view = 'all', string $from = '', string $to = ''): array
{
    if (!rm_ready($pdo)) return [];
    $where = 'r.factory_client_id = ?'; $args = [$factory];
    if ($view === 'waiting') $where .= " AND r.status = 'requested'";
    if ($view === 'open')    $where .= " AND r.status = 'approved' AND COALESCE(rq.fulfilment_stage,'') <> 'dispatched'";
    if ($view === 'done')    $where .= " AND (r.status = 'declined' OR (r.status = 'approved' AND rq.fulfilment_stage = 'dispatched'))";
    if ($from !== '') { $where .= ' AND r.created_at >= ?'; $args[] = $from; }
    if ($to !== '')   { $where .= ' AND r.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $args[] = $to; }
    $st = $pdo->prepare("SELECT r.*, q.quote_number AS source_number, q.customer_reference AS source_ref,
                                rq.quote_number AS remake_number, rq.fulfilment_stage AS remake_stage,
                                c.company_name AS account_name,
                                (SELECT COALESCE(SUM(i.quantity),0) FROM factory_remake_items i WHERE i.remake_id = r.id) AS blinds
                           FROM factory_remakes r
                           JOIN quotes q       ON q.id = r.source_quote_id
                           LEFT JOIN quotes rq ON rq.id = r.remake_quote_id
                           LEFT JOIN clients c ON c.id = r.account_client_id
                          WHERE $where
                       ORDER BY r.created_at DESC, r.id DESC
                          LIMIT 500");
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Who may open a remake photo: the factory office, or a user of the account it belongs to. */
function rm_can_see_photo(array $r): bool
{
    if (function_exists('factory_user_is_office') && factory_user_is_office()) return true;
    return (int) ($_SESSION['client_id'] ?? 0) === (int) $r['account_client_id'];
}

function rm_get_account_name(PDO $pdo, int $accountId): string
{
    $st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
    $st->execute([$accountId]);
    return (string) ($st->fetchColumn() ?: '');
}
