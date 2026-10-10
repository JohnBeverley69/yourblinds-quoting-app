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

    // Is this order itself a remake? If so its lines' sell_price / line_total
    // are the spread remake CHARGE that rm_create_remake_order wrote (often
    // 0.00 on a free one), not the blinds' trade value — so billing figures
    // are the wrong source and a remake of a remake came out at £0. The raise
    // form then offered "Full trade price of these blinds: £0.00", cost was
    // stored as 0, and the Remakes report under-stated both "Trade value
    // remade" and "Cost to us" by the whole second remake.
    //
    // The trade figures ARE on the line: $itemSkip only drops sell_price,
    // line_total and subtotal_per_blind, so trade_price_per_blind and
    // base_price are inherited from the original intact. Use those directly
    // rather than walking back to the source order, which has no per-line link
    // to walk (the copy keeps no source_quote_item_id).
    $isRemake = false;
    try {
        $rq = $pdo->prepare('SELECT remake_of_quote_id FROM quotes WHERE id = ? LIMIT 1');
        $rq->execute([$quoteId]);
        $isRemake = (int) ($rq->fetchColumn() ?: 0) > 0;
    } catch (Throwable $e) { /* column absent — no remakes exist */ }

    $unitBy = [];
    if ($isRemake) {
        foreach ($lines as $l) {
            $unit = (float) ($l['trade_price_per_blind'] ?? 0);
            if ($unit <= 0) $unit = (float) ($l['base_price'] ?? 0);
            $unitBy[(int) $l['id']] = round($unit, 2);
        }
    } else {
        try {
            foreach (ar_invoice_lines_from_order($pdo, $factory, $quoteId, false)['lines'] as $l) {
                if (($l['line_type'] ?? '') === 'blind' && $l['source_quote_item_id']) {
                    $q = max(1, (int) $l['quantity']);
                    $unitBy[(int) $l['source_quote_item_id']] = round((float) $l['line_net'] / $q, 2);
                }
            }
        } catch (Throwable $e) { /* cost unknown — 0 */ }
    }
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
 * Bin a photo that rm_store_photo() saved for a remake that then failed to
 * save. The upload is written to disk before rm_create() runs, and rm_create()
 * is where the validation lives ("Tick at least one blind to remake", "Choose a
 * reason for the remake", "That order can't have a remake…"), so a rejected
 * submit left uploads/remakes/rm_<hex>.<ext> on disk with no factory_remakes
 * row referencing it — and the user's retry wrote a second copy. Nothing ever
 * cleaned the first one up.
 *
 * Only ever unlinks inside uploads/remakes/, and never complains: by the time
 * this is called the user is already being shown the real error.
 */
function rm_discard_photo(string $path): void
{
    if ($path === '' || strpos($path, 'uploads/remakes/') !== 0 || strpos($path, '..') !== false) return;
    $full = __DIR__ . '/../' . $path;
    if (is_file($full)) @unlink($full);
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
    $st = $pdo->prepare('SELECT r.*, COALESCE(q.quote_number, CONCAT(\'#\', r.source_quote_id, \' (order deleted)\')) AS source_number, q.customer_reference AS source_ref,
                                rq.quote_number AS remake_number, rq.fulfilment_stage AS remake_stage,
                                c.company_name AS account_name
                           FROM factory_remakes r
                           LEFT JOIN quotes q   ON q.id = r.source_quote_id
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
    // Labels from ONE query over quote_items, not rm_order_lines() per source
    // order. This used to loop every distinct source_quote_id and call
    // rm_order_lines(), which runs ar_order_lines_for_doc() plus a full
    // ar_invoice_lines_from_order() rebuild — quote lookup, extras rows, per-line
    // trade-discount resolution, the lot — around 8-10 queries each. All of it to
    // produce display strings like "2 x Roller, Bedroom".
    //
    // The Remakes page defaults to the Overview tab and is also where every
    // approve/decline redirects, and it calls this across waiting + open + done
    // (500 each). With a year of remakes over ~450 distinct source orders that
    // was several thousand queries to render a handful of labels.
    //
    // rm_line_label() only needs the snapshots, which are all on quote_items —
    // no pricing is involved. Ownership isn't filtered because labels are only
    // ever read for item ids that appear in factory_remake_items.
    $labels = [];
    $srcIds = array_keys($bySource);
    if ($srcIds) {
        $sph = implode(',', array_fill(0, count($srcIds), '?'));
        $ls  = $pdo->prepare(
            "SELECT id, room_name, product_name_snapshot, system_name_snapshot,
                    fabric_name_snapshot, fabric_colour_snapshot, width_mm, drop_mm
               FROM quote_items WHERE quote_id IN ($sph)"
        );
        $ls->execute($srcIds);
        foreach ($ls->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $fab = trim(implode(' / ', array_filter(
                [(string) $l['fabric_name_snapshot'], (string) $l['fabric_colour_snapshot']],
                static fn ($s) => trim($s) !== ''
            )));
            $labels[(int) $l['id']] = rm_line_label([
                'product'   => (string) $l['product_name_snapshot'],
                'system'    => (string) ($l['system_name_snapshot'] ?? ''),
                'fabric'    => $fab,
                'width_mm'  => $l['width_mm'],
                'drop_mm'   => $l['drop_mm'],
                'room_name' => (string) ($l['room_name'] ?? ''),
            ]);
        }
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
    if (!$items) throw new RuntimeException('No blinds are recorded on this remake.');

    // Which of those blinds are still ON the order — resolved here, before the
    // INSERT, because the test above can't tell. rm_items_for() reads
    // factory_remake_items straight out of the table and returns a row for
    // every stored blind, even labelling a vanished one "Blind (no longer on
    // the order)" (:254), so $items is always populated and the old guard here
    // could never fire. The surviving lines used to be worked out only after
    // the order row had been written.
    //
    // Delete the remade blind from the order before approving (allowed while it
    // isn't dispatched or invoiced) and approval produced a remake order with a
    // subtotal and ZERO quote_items: absent from Incoming orders, Factory
    // Console → Orders and the dispatch tray, impossible to act on — while the
    // flash said it was waiting in Incoming orders.
    $lines = [];
    foreach (rm_order_lines($pdo, $factory, $srcId) as $l) if (isset($items[$l['id']])) $lines[$l['id']] = $l;
    if (!$lines) {
        throw new RuntimeException('None of the blinds on this remake are on the order any more, so it can’t be approved. Raise a fresh remake against the blinds that are still there.');
    }

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
             'additional_reference', 'notes',
             // The original's "Sold for" is what the client charged their own
             // customer for the blind. They are not selling the remake to them
             // again, so these must not ride along: $skip is a denylist, and
             // every column missing from it is copied verbatim. Inherited, they
             // made a free remake read as a second full-price sale at ~100%
             // margin on the Dashboard's gross-profit panel.
             // direct_order is deliberately NOT skipped — a remake of a direct
             // order is still one, and #927 keys the client's own order lists
             // off that flag, so clearing it would hide the remake from them.
             'sold_for_amount', 'sold_for_inc_vat', 'sold_for_net', 'sold_for_gross'];
    $vatPct = (float) ($q['vat_percent'] ?? 0);
    $vat    = round($charge * $vatPct / 100, 2);
    $note   = 'REMAKE of ' . $q['quote_number'] . ' — ' . $r['reason_label']
            . (trim((string) ($r['note'] ?? '')) !== '' ? ': ' . trim((string) $r['note']) : '');
    $row = [];
    foreach ($q as $col => $val) if (!in_array($col, $skip, true)) $row[$col] = $val;
    $row += [
        'quote_number' => $number, 'status' => 'ordered',
        // 64 hex, like every other quote. This minted 32 (random_bytes(16)) while
        // qb_generate_public_token() produces 64, and all three public endpoints
        // require 40-128 hex — quote-history/public.php:25, accept.php:34 and
        // terms.php:19 — so every customer-facing link for a remake order came
        // back 404 "Quote not found", including the one in its own invoice email.
        'public_token' => function_exists('qb_generate_public_token')
            ? qb_generate_public_token() : bin2hex(random_bytes(32)),
        'subtotal' => $charge, 'vat' => $vat, 'total' => round($charge + $vat, 2),
        'remake_of_quote_id' => $srcId, 'due_date' => $due, 'wt_amount' => 0,
        'additional_reference' => 'Remake of ' . $q['quote_number'], 'notes' => mb_substr($note, 0, 2000),
    ];
    $cols = array_keys($row);
    $sql  = 'INSERT INTO quotes (`' . implode('`,`', $cols) . '`, created_at, updated_at) VALUES ('
          . implode(',', array_fill(0, count($cols), '?')) . ', NOW(), NOW())';
    $pdo->prepare($sql)->execute(array_values($row));
    $newId = (int) $pdo->lastInsertId();

    // Lines — copied with their options; priced at the charge, spread by trade
    // value. $lines was resolved above, before the INSERT, so a remake with
    // nothing left on the order is refused instead of creating an order no
    // screen can act on. rm_order_lines() runs ar_invoice_lines_from_order
    // underneath, so it is deliberately not called a second time here.
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

    // No due date given? Work it out from the product lead times, the same way
    // every other placed order gets one (dd_stamp_order is called from
    // change_status.php:114/:275, order_suppliers.php:416 and accept.php:197).
    // The approve form's "Due date (optional)" is usually left blank, and the
    // INSERT above just stored that NULL — so remade blinds sorted BELOW every
    // other order on the floor board, including ones due weeks out, and
    // oc_remakes_due() listed nothing for them on the office calendar. A remake
    // is the one job that shouldn't be queued last.
    //
    // After the lines, necessarily: dd_stamp_order reads the order's products to
    // get their lead times, so it has nothing to work from until they exist.
    if ($due === null) {
        try {
            require_once __DIR__ . '/due_dates.php';
            dd_stamp_order($pdo, $newId, $factory);
        } catch (Throwable $e) { /* lead times not set up — leave it unstamped */ }
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
    // LEFT JOIN to the original, with the "is this a remake?" test resting on
    // remake_of_quote_id IS NOT NULL alone — which is the actual question.
    // It used to be an INNER JOIN to the original order, so the moment that row
    // was deleted the remake stopped being recognised as one by every caller.
    // The consequence that bites: dc_recalc_delivery() stops excluding it, so a
    // FREE remake going out on its own is under the van threshold, picks up a
    // £10 carriage charge, and the invoice reads "REMAKE — £0.00" plus delivery.
    $st = $pdo->prepare("SELECT r.id, o.quote_number FROM quotes r LEFT JOIN quotes o ON o.id = r.remake_of_quote_id
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
    // The source order is LEFT JOINed, and its number falls back to a visible
    // "(order deleted)" marker. These used to be INNER JOINs, and
    // quotes.remake_of_quote_id / factory_remakes.source_quote_id have no
    // foreign key (migrate_remakes.php adds the column and an index only), so
    // nothing stops the original order being deleted — factory/edit-order.php
    // allows it while there is no delivery note, invoice or payment.
    //
    // With an INNER JOIN the remake then vanished from rm_list() and rm_get(),
    // so every tab was empty and approve/decline both threw "That remake no
    // longer exists" — while rm_waiting_count() does not join at all, so the
    // sidebar kept showing "Remakes 1" for ever. The badge could only be
    // cleared with SQL.
    $st = $pdo->prepare("SELECT r.*, COALESCE(q.quote_number, CONCAT('#', r.source_quote_id, ' (order deleted)')) AS source_number, q.customer_reference AS source_ref,
                                rq.quote_number AS remake_number, rq.fulfilment_stage AS remake_stage,
                                c.company_name AS account_name,
                                (SELECT COALESCE(SUM(i.quantity),0) FROM factory_remake_items i WHERE i.remake_id = r.id) AS blinds
                           FROM factory_remakes r
                           LEFT JOIN quotes q  ON q.id = r.source_quote_id
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

/**
 * Tell the account what happened to a remake THEY reported (approved or declined).
 * Goes to the person who reported it, else the account's own email. Best-effort:
 * never throws — the decision is already saved, the account also sees it on their
 * order. Returns the address it went to, or '' when nothing was sent.
 */
function rm_notify_account(PDO $pdo, int $factory, int $id): string
{
    try {
        $r = rm_get($pdo, $factory, $id);
        if (!$r || $r['raised_by'] !== 'account' || !in_array($r['status'], ['approved', 'declined'], true)) return '';
        $email = '';
        if ((int) ($r['raised_by_user_id'] ?? 0) > 0) {
            $st = $pdo->prepare('SELECT email FROM client_users WHERE id = ? LIMIT 1');
            $st->execute([(int) $r['raised_by_user_id']]);
            $email = trim((string) ($st->fetchColumn() ?: ''));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $st = $pdo->prepare('SELECT email FROM clients WHERE id = ? LIMIT 1');
            $st->execute([(int) $r['account_client_id']]);
            $email = trim((string) ($st->fetchColumn() ?: ''));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';

        $factoryName = rm_get_account_name($pdo, $factory);
        $orderNo = (string) $r['source_number'];
        $lines = [];
        foreach (rm_items_for($pdo, $factory, [$r])[$id] ?? [] as $it) $lines[] = '  ' . $it['quantity'] . ' × ' . $it['label'];
        $what = "Order {$orderNo} — {$r['reason_label']}\n" . implode("\n", $lines);

        if ($r['status'] === 'approved') {
            $subject = "Remake approved — order {$orderNo}";
            $charge  = (float) $r['charge_amount'] > 0
                ? 'There is a charge of £' . number_format((float) $r['charge_amount'], 2) . ' + VAT, which will be on your invoice.'
                : 'There is no charge for this remake.';
            $body = "Hello,\n\nWe've approved your remake request and it's going into production as order {$r['remake_number']}.\n\n"
                  . $what . "\n\n" . $charge
                  . ($r['due_date'] ? "\nWe expect it to be ready by " . date('j F Y', strtotime((string) $r['due_date'])) . '.' : '')
                  . "\n\nYou can follow it on your order:\n{LINK}\n\nThanks,\n{$factoryName}";
        } else {
            $subject = "Remake request — order {$orderNo}";
            $body = "Hello,\n\nWe've looked at your remake request and won't be remaking this one.\n\n"
                  . $what . "\n\nOur reason: " . (string) $r['decline_reason']
                  . "\n\nIf you'd like to talk it through, just reply to this email.\n\nYour order:\n{LINK}\n\nThanks,\n{$factoryName}";
        }
        $base = rtrim((string) (env('APP_URL', '') ?: 'https://yourblinds.uk'), '/');   // APP_URL, never the request Host
        $url  = $base . '/quote-builder/edit.php?id=' . (int) $r['source_quote_id'];
        $body = str_replace('{LINK}', $url, $body);

        require_once __DIR__ . '/../mailer.php';
        require_once __DIR__ . '/tenant_mail.php';
        $opts = tenant_mail_opts($pdo, $factory);
        $opts['links'] = [$url => 'View your order'];
        return mailer_send($email, $subject, $body, null, null, $opts) ? $email : '';
    } catch (Throwable $e) {
        error_log('rm_notify_account: ' . $e->getMessage());
        return '';
    }
}

/**
 * Remakes by PRODUCTION AREA (the bench). A remade blind is put against the
 * area(s) that made the ORIGINAL — from its floor streams, else its blind job.
 * A blind made across two benches (e.g. a vertical's headrail + fabric) counts
 * under each, with its value split between them so the column still adds up.
 * Blinds that never went through the floor (bought in, or not released) are
 * listed as "Not made on the floor". Rows: name => [n, blinds, cost].
 */
function rm_area_breakdown(PDO $pdo, int $factory, array $remakes): array
{
    $out = [];
    if (!$remakes) return $out;
    $items = rm_items_for($pdo, $factory, $remakes);
    $names = [];
    try {
        foreach ($pdo->query('SELECT id, name FROM production_areas')->fetchAll(PDO::FETCH_ASSOC) as $a) $names[(int) $a['id']] = (string) $a['name'];
    } catch (Throwable $e) { /* no areas configured */ }
    $unitBy = [];
    foreach ($remakes as $r) {
        $rid = (int) $r['id'];
        $src = (int) $r['source_quote_id'];
        if (!isset($unitBy[$src])) {
            $unitBy[$src] = [];
            foreach (rm_order_lines($pdo, $factory, $src) as $l) $unitBy[$src][$l['id']] = (float) $l['unit_trade'];
        }
        $seen = [];
        foreach ($items[$rid] ?? [] as $it) {
            $areas = [];
            try {
                $st = $pdo->prepare('SELECT DISTINCT COALESCE(s.area_id, j.area_id) FROM factory_blind_jobs j
                                       LEFT JOIN factory_blind_streams s ON s.blind_job_id = j.id
                                      WHERE j.quote_item_id = ? AND COALESCE(s.area_id, j.area_id) IS NOT NULL');
                $st->execute([$it['source_item_id']]);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $aid) $areas[] = $names[(int) $aid] ?? ('Area ' . (int) $aid);
            } catch (Throwable $e) { /* floor tables missing */ }
            if (!$areas) $areas = ['Not made on the floor'];
            $value = ($unitBy[$src][$it['source_item_id']] ?? 0.0) * $it['quantity'];
            foreach ($areas as $a) {
                $out[$a] ??= ['n' => 0, 'blinds' => 0, 'cost' => 0.0];
                if (!isset($seen[$a])) { $out[$a]['n']++; $seen[$a] = true; }
                $out[$a]['blinds'] += $it['quantity'];
                $out[$a]['cost']   += $value / count($areas);
            }
        }
    }
    uasort($out, static fn ($a, $b) => $b['cost'] <=> $a['cost']);
    return $out;
}
