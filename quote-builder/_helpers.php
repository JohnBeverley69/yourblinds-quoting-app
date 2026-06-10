<?php
declare(strict_types=1);

// Quote-builder helpers — loaded by every page in this module. Caller must
// have already required bootstrap.php + auth/middleware.php.

/**
 * Load a quote scoped to the given client. 404s if not found / wrong tenant.
 */
function qb_load_quote_or_404(int $quoteId, int $clientId): array
{
    if ($quoteId <= 0) {
        http_response_code(404);
        exit('Quote not found.');
    }
    $st = db()->prepare(
        'SELECT * FROM quotes WHERE id = ? AND client_id = ? LIMIT 1'
    );
    $st->execute([$quoteId, $clientId]);
    $q = $st->fetch();
    if (!$q) {
        http_response_code(404);
        exit('Quote not found.');
    }
    return $q;
}

/**
 * Recompute and persist subtotal / VAT / total based on current line items.
 * Called after every add / delete on items.
 */
function qb_recompute_totals(int $quoteId): void
{
    $pdo = db();

    $sumSt = $pdo->prepare(
        'SELECT COALESCE(SUM(line_total), 0) FROM quote_items WHERE quote_id = ?'
    );
    $sumSt->execute([$quoteId]);
    $subtotal = round((float) $sumSt->fetchColumn(), 2);

    $rateSt = $pdo->prepare('SELECT vat_percent FROM quotes WHERE id = ?');
    $rateSt->execute([$quoteId]);
    $vatPct = (float) ($rateSt->fetchColumn() ?: 0);

    $vat   = round($subtotal * $vatPct / 100, 2);
    $total = round($subtotal + $vat, 2);

    $pdo->prepare('UPDATE quotes SET subtotal = ?, vat = ?, total = ? WHERE id = ?')
        ->execute([$subtotal, $vat, $total, $quoteId]);
}

/**
 * Generate the next sequential quote number for a client: PRE-YYYY-####.
 * Prefix is from client_settings.quote_prefix, falling back to the first
 * 3 alpha chars of company_name. Subject to a small race window — the
 * UNIQUE index on quotes(client_id, quote_number) catches collisions.
 */
function qb_generate_quote_number(int $clientId): string
{
    $pdo = db();

    $st = $pdo->prepare('SELECT quote_prefix FROM client_settings WHERE client_id = ? LIMIT 1');
    $st->execute([$clientId]);
    $prefix = trim((string) ($st->fetchColumn() ?: ''));

    if ($prefix === '') {
        $st = $pdo->prepare('SELECT company_name FROM clients WHERE id = ? LIMIT 1');
        $st->execute([$clientId]);
        $name   = (string) ($st->fetchColumn() ?: '');
        $clean  = (string) (preg_replace('/[^A-Za-z]/', '', $name) ?? '');
        $prefix = strtoupper(substr($clean, 0, 3));
        if ($prefix === '') $prefix = 'QTE';
    }

    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';

    $st = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(quote_number, '-', -1) AS UNSIGNED))
           FROM quotes
          WHERE client_id = ? AND quote_number LIKE ?"
    );
    $st->execute([$clientId, $like]);
    $next = ((int) ($st->fetchColumn() ?? 0)) + 1;

    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

/**
 * Random 64-char hex token for the customer-facing accept URL.
 * Stored on quotes.public_token (UNIQUE).
 */
function qb_generate_public_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Format an integer mm value for display: "1500 mm".
 * Used in the line-item table; the user-facing form takes flexible units.
 */
function qb_fmt_mm(int $mm): string
{
    return number_format($mm) . ' mm';
}

/**
 * Format a money value: "£1,234.56".
 */
function qb_fmt_money($n): string
{
    return '£' . number_format((float) $n, 2);
}

/**
 * Set a flash message and redirect. Used by POST handlers.
 */
function qb_flash_redirect(string $location, string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
    header('Location: ' . $location);
    exit;
}

/**
 * True if a quote is in an editable state. Drafts are fully editable; once
 * sent or beyond, the quote is locked unless the user explicitly reopens.
 */
function qb_is_editable(array $quote): bool
{
    return ((string) $quote['status']) === 'draft';
}

/**
 * Statuses available to transition TO from the current status.
 * The pipeline is roughly draft → sent → accepted → ordered → invoiced → paid,
 * with declined as a terminal alternative to accepted, and "Reopen" allowing
 * a return to draft from any state for late edits.
 */
/**
 * Auto-advance a quote to 'fitted' when the linked fitting
 * appointment is marked complete. Called from /calendar/view.php
 * after the appointment's status updates.
 *
 * Guarded so we don't downgrade or override anything we shouldn't:
 *   - Only advances if current quote status is 'accepted' or
 *     'ordered' (the two pre-fit states). Anything else is a no-op.
 *   - 'declined' → don't touch (the customer said no, finishing
 *     a phantom appointment shouldn't resurrect it).
 *   - 'fitted' / 'invoiced' / 'paid' → already past this point,
 *     don't rewind.
 *
 * Returns the updated quote_number on success (so the caller can
 * mention it in the flash message), or null if nothing changed
 * (already fitted, no quote linked, terminal state, etc.).
 *
 * Idempotent — safe to call multiple times. The single UPDATE has
 * status IN ('accepted','ordered') in its WHERE, so a second call
 * affects zero rows.
 */
function qb_advance_quote_to_fitted(PDO $pdo, int $quoteId, int $clientId): ?string
{
    if ($quoteId <= 0 || $clientId <= 0) return null;

    try {
        // Pull current status + quote_number so we can both check
        // the state machine and return a friendly identifier on the
        // success flash. Tenant-scoped via client_id.
        $st = $pdo->prepare(
            'SELECT status, quote_number FROM quotes
              WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $st->execute([$quoteId, $clientId]);
        $q = $st->fetch();
        if (!$q) return null;

        $status = (string) ($q['status'] ?? '');
        if (!in_array($status, ['accepted', 'ordered'], true)) {
            return null;
        }

        $upd = $pdo->prepare(
            "UPDATE quotes
                SET status = 'fitted'
              WHERE id = ? AND client_id = ?
                AND status IN ('accepted', 'ordered')"
        );
        $upd->execute([$quoteId, $clientId]);

        if ($upd->rowCount() < 1) return null;
        return (string) ($q['quote_number'] ?? ('#' . $quoteId));
    } catch (Throwable $e) {
        // Quotes table missing / column drift — log and skip rather
        // than blow up the appointment-status update.
        error_log('qb_advance_quote_to_fitted failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Companion to qb_advance_quote_to_fitted() — moves a quote back
 * OUT of 'fitted' when the install gets cancelled/no-showed after
 * the auto-advance had already fired. Doesn't trigger
 * automatically; the user has to click the banner on the
 * appointment view page, because most cancellations are just
 * paperwork after a real install (the rewind would be wrong).
 *
 * Default rewind target is 'ordered' (the most common pre-fit
 * state). If the user actually wants to go further back, they
 * adjust via the quote edit page.
 *
 * Guarded just like the advance helper — only rewinds if current
 * status is 'fitted'. 'invoiced' / 'paid' don't rewind because
 * money's already moved; user should void the invoice/payment
 * separately if needed.
 */
function qb_rewind_quote_from_fitted(PDO $pdo, int $quoteId, int $clientId): ?string
{
    if ($quoteId <= 0 || $clientId <= 0) return null;

    try {
        $st = $pdo->prepare(
            'SELECT status, quote_number FROM quotes
              WHERE id = ? AND client_id = ? LIMIT 1'
        );
        $st->execute([$quoteId, $clientId]);
        $q = $st->fetch();
        if (!$q) return null;
        if ((string) ($q['status'] ?? '') !== 'fitted') return null;

        $upd = $pdo->prepare(
            "UPDATE quotes
                SET status = 'ordered'
              WHERE id = ? AND client_id = ? AND status = 'fitted'"
        );
        $upd->execute([$quoteId, $clientId]);
        if ($upd->rowCount() < 1) return null;
        return (string) ($q['quote_number'] ?? ('#' . $quoteId));
    } catch (Throwable $e) {
        error_log('qb_rewind_quote_from_fitted failed: ' . $e->getMessage());
        return null;
    }
}

function qb_allowed_transitions(string $current): array
{
    switch ($current) {
        // From draft we now allow direct → accepted / declined so the
        // trade user can record an in-person/phone acceptance without
        // having to click "sent" first and then "accepted". The
        // change_status handler auto-stamps sent_at when going draft →
        // accepted/declined so the lifecycle timeline stays consistent.
        case 'draft':     return ['sent', 'accepted', 'declined'];
        case 'sent':      return ['accepted', 'declined', 'draft'];
        case 'accepted':  return ['ordered', 'fitted', 'draft'];
        case 'declined':  return ['draft'];
        case 'ordered':   return ['fitted', 'invoiced', 'draft'];
        case 'fitted':    return ['invoiced', 'ordered', 'draft'];
        case 'invoiced':  return ['paid', 'draft'];
        case 'paid':      return [];
    }
    return ['draft'];
}

/**
 * Which user-permission flag does this target status require?
 *
 *   'can_create_quotes' — sales-side transitions (sent / accepted /
 *                         declined). Closing a sale belongs to whoever
 *                         can also originate a quote.
 *   'can_create_orders' — order-lifecycle transitions (ordered /
 *                         invoiced / paid). Office / admin action.
 *   ''                  — no specific flag required beyond being able
 *                         to access the quote at all (draft reopen).
 *
 * Admins bypass this entirely.
 */
function qb_target_permission(string $target): string
{
    switch ($target) {
        case 'sent':
        case 'accepted':
        case 'declined':
            return 'can_create_quotes';
        case 'ordered':
        case 'fitted':
        case 'invoiced':
        case 'paid':
            return 'can_create_orders';
        case 'draft':
        default:
            return '';   // either role can reopen as draft
    }
}

/**
 * Convenience: given a user's perm flags + admin flag, can they move
 * the quote to this target status?
 */
function qb_user_can_change_to(bool $isAdmin, array $perms, string $target): bool
{
    if ($isAdmin) return true;
    $need = qb_target_permission($target);
    if ($need === '') {
        // 'draft' reopen — either creator-style perm is enough.
        return !empty($perms['can_create_quotes'])
            || !empty($perms['can_create_orders']);
    }
    return !empty($perms[$need]);
}

/**
 * Create an installation appointment off the back of a quote acceptance.
 *
 * Idempotent: if an appointment already exists pointing at this quote
 * (appointments.quote_id = N), returns its id without inserting.
 *
 * The appointment lands on a placeholder date (today + 14 days) at 09:00
 * for 60 minutes — it WILL appear on the calendar so the trade user can
 * find it, drag it to the real date, and assign a fitter. Status is
 * 'booked' (the existing default appointment status).
 *
 * Customer + installation address copied from the quote snapshot fields,
 * so even if the customer record changes later, the appointment carries
 * the address as the quote captured it.
 *
 * Returns the appointment id (new or pre-existing), or null if the quote
 * doesn't exist / has no client_id.
 */
function qb_create_appointment_from_quote(PDO $pdo, int $quoteId): ?int
{
    if ($quoteId <= 0) return null;

    $q = $pdo->prepare(
        'SELECT id, client_id, quote_number, customer_id,
                end_customer_name, end_customer_email, end_customer_phone,
                end_customer_address1, end_customer_address2,
                end_customer_town, end_customer_county, end_customer_postcode,
                notes
           FROM quotes WHERE id = ? LIMIT 1'
    );
    $q->execute([$quoteId]);
    $quote = $q->fetch();
    if (!$quote || empty($quote['client_id'])) {
        return null;
    }

    // appt_kind distinguishes the measure visit from the fitting. Probe once;
    // pre-migration installs just have the single linked appointment.
    static $hasKind = null;
    if ($hasKind === null) {
        try { $pdo->query('SELECT appt_kind FROM appointments LIMIT 1'); $hasKind = true; }
        catch (Throwable $e) { $hasKind = false; }
    }

    // Idempotency check — re-acceptance, double-click, replay attack, all
    // safe; we never end up with two installation appointments per quote.
    // Only ever reuse a FITTING here, so a measure appointment now linked to
    // the same quote isn't mistaken for the install.
    $exist = $pdo->prepare(
        $hasKind
            ? "SELECT id FROM appointments WHERE quote_id = ? AND appt_kind = 'fitting' LIMIT 1"
            : 'SELECT id FROM appointments WHERE quote_id = ? LIMIT 1'
    );
    $exist->execute([$quoteId]);
    $existingId = $exist->fetchColumn();
    if ($existingId !== false) {
        return (int) $existingId;
    }

    // Land in the Pending Fitting tray (NULL appointment_date) so
    // the trade user has to consciously place it on a real date —
    // dragging from the tray onto a calendar cell, or opening the
    // appointment to edit. Default time + duration are set so once
    // dropped on a date it's instantly displayable; the user can
    // tweak both via the edit form when the install is firmed up.
    $title = 'Install: ' . (string) $quote['quote_number']
           . ' — ' . (string) $quote['end_customer_name'];

    $notes = "Auto-created from accepted quote " . $quote['quote_number'] . ".\n"
           . "Drag onto the right date (or open to edit) when the install is scheduled."
           . (!empty($quote['notes']) ? "\n\nQuote notes:\n" . $quote['notes'] : '');

    $ins = $pdo->prepare(
        'INSERT INTO appointments
           (client_id, client_user_id, customer_id, quote_id,
            title, appointment_date, appointment_time, duration_minutes,
            installation_address1, installation_address2,
            installation_town, installation_county, installation_postcode,
            notes, status' . ($hasKind ? ', appt_kind' : '') . ')
         VALUES (?, NULL, ?, ?,
                 ?, NULL, ?, 60,
                 ?, ?, ?, ?, ?,
                 ?, ?' . ($hasKind ? ", 'fitting'" : '') . ')'
    );
    $ins->execute([
        (int) $quote['client_id'],
        $quote['customer_id'] !== null ? (int) $quote['customer_id'] : null,
        (int) $quote['id'],
        $title,
        '09:00:00',
        $quote['end_customer_address1'] ?: null,
        $quote['end_customer_address2'] ?: null,
        $quote['end_customer_town']     ?: null,
        $quote['end_customer_county']   ?: null,
        $quote['end_customer_postcode'] ?: null,
        $notes,
        'booked',
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Create a MEASURE calendar entry for a quote raised without a prior
 * appointment — the "walk-up" case (a salesperson quotes a neighbour on the
 * spot). Gives the new customer a calendar presence so the consoles see the
 * job and its chain (draft → sent → accepted → fitting), exactly like a quote
 * raised from a booked measure.
 *
 * Lands TODAY (the visit is happening), assigned to the creating user, marked
 * completed. Idempotent: one measure per quote. Returns the appointment id, or
 * null if the quote is missing / already has a measure.
 */
function qb_create_measure_from_quote(PDO $pdo, int $quoteId, ?int $assignedUserId = null): ?int
{
    if ($quoteId <= 0) return null;

    static $hasKind = null;
    if ($hasKind === null) {
        try { $pdo->query('SELECT appt_kind FROM appointments LIMIT 1'); $hasKind = true; }
        catch (Throwable $e) { $hasKind = false; }
    }

    $q = $pdo->prepare(
        'SELECT id, client_id, quote_number, customer_id,
                end_customer_name,
                end_customer_address1, end_customer_address2,
                end_customer_town, end_customer_county, end_customer_postcode,
                notes
           FROM quotes WHERE id = ? LIMIT 1'
    );
    $q->execute([$quoteId]);
    $quote = $q->fetch();
    if (!$quote || empty($quote['client_id'])) return null;

    // Idempotency — only ever one measure per quote.
    $exist = $pdo->prepare(
        $hasKind
            ? "SELECT id FROM appointments WHERE quote_id = ? AND appt_kind = 'measure' LIMIT 1"
            : 'SELECT id FROM appointments WHERE quote_id = ? LIMIT 1'
    );
    $exist->execute([$quoteId]);
    if (($existingId = $exist->fetchColumn()) !== false) return (int) $existingId;

    $title = trim((string) $quote['end_customer_name']) !== ''
        ? (string) $quote['end_customer_name']
        : ('Quote ' . (string) $quote['quote_number']);
    $notes = "On-site quote — created in the field for a walk-up customer.\n"
           . 'Quote ' . $quote['quote_number'] . '.'
           . (!empty($quote['notes']) ? "\n\nQuote notes:\n" . $quote['notes'] : '');

    $ins = $pdo->prepare(
        'INSERT INTO appointments
           (client_id, client_user_id, customer_id, quote_id,
            title, appointment_date, appointment_time, duration_minutes,
            installation_address1, installation_address2,
            installation_town, installation_county, installation_postcode,
            notes, status' . ($hasKind ? ', appt_kind' : '') . ')
         VALUES (?, ?, ?, ?,
                 ?, ?, ?, 30,
                 ?, ?, ?, ?, ?,
                 ?, ?' . ($hasKind ? ", 'measure'" : '') . ')'
    );
    $ins->execute([
        (int) $quote['client_id'],
        ($assignedUserId !== null && $assignedUserId > 0) ? $assignedUserId : null,
        $quote['customer_id'] !== null ? (int) $quote['customer_id'] : null,
        (int) $quote['id'],
        $title,
        date('Y-m-d'),
        date('H:i:s'),
        $quote['end_customer_address1'] ?: null,
        $quote['end_customer_address2'] ?: null,
        $quote['end_customer_town']     ?: null,
        $quote['end_customer_county']   ?: null,
        $quote['end_customer_postcode'] ?: null,
        $notes,
        'completed',
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Create a draft quote from a prepared $f field bundle (the New-quote form's
 * shape) and return ['id' => int, 'number' => string].
 *
 * Everything the New-quote POST handler used to do inline:
 *   - auto-create a customer record when none is linked but a name is given,
 *   - snapshot the tenant VAT rate,
 *   - generate a unique quote number (small retry against the insert race),
 *   - insert the draft quote,
 *   - link the originating measure appointment, or — for a walk-up with no
 *     appointment — create a measure entry so the job shows on the consoles.
 *
 * Runs in its own transaction and throws on failure (rolls back first). Shared
 * by the manual form submit and the "Start quote" calendar shortcut that
 * creates a quote straight from an appointment without the confirm screen.
 */
function qb_create_quote_from_fields(PDO $pdo, int $clientId, array $f, int $appointmentId, int $userId): array
{
    $pdo->beginTransaction();
    try {
        // If no existing customer picked but a name was entered, auto-create a
        // customer record so the same person is findable on the next quote.
        if ((int) ($f['customer_id'] ?? 0) === 0 && (string) $f['end_customer_name'] !== '') {
            $emptyToNull = static fn (string $v) => $v === '' ? null : $v;
            $custIns = $pdo->prepare(
                'INSERT INTO customers
                   (client_id, name, email, phone, has_whatsapp,
                    address1, address2, town, county, postcode)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $custIns->execute([
                $clientId,
                (string) $f['end_customer_name'],
                $emptyToNull((string) $f['end_customer_email']),
                $emptyToNull((string) $f['end_customer_phone']),
                (int) $f['has_whatsapp'],
                $emptyToNull((string) $f['end_customer_address1']),
                $emptyToNull((string) $f['end_customer_address2']),
                $emptyToNull((string) $f['end_customer_town']),
                $emptyToNull((string) $f['end_customer_county']),
                $emptyToNull((string) $f['end_customer_postcode']),
            ]);
            $f['customer_id'] = (int) $pdo->lastInsertId();
        }

        // Snapshot the tenant's VAT rate at the time the quote is created.
        $vatSt = $pdo->prepare(
            'SELECT vat_percent FROM client_settings WHERE client_id = ? LIMIT 1'
        );
        $vatSt->execute([$clientId]);
        $vatPct = (float) ($vatSt->fetchColumn() ?? 20.0);

        // Generate a quote number with a couple of retries against the tiny
        // race window between SELECT MAX and INSERT.
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $quoteNumber = qb_generate_quote_number($clientId);
                $token       = qb_generate_public_token();
                $st = $pdo->prepare(
                    'INSERT INTO quotes
                      (client_id, quote_number, customer_id,
                       end_customer_name, end_customer_email, end_customer_phone, has_whatsapp,
                       end_customer_address1, end_customer_address2,
                       end_customer_town, end_customer_county, end_customer_postcode,
                       status, vat_percent, notes,
                       public_token, created_by_user_id)
                     VALUES
                      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                       "draft", ?, ?, ?, ?)'
                );
                $st->execute([
                    $clientId,
                    $quoteNumber,
                    (int) $f['customer_id'] > 0 ? (int) $f['customer_id'] : null,
                    (string) $f['end_customer_name'],
                    (string) $f['end_customer_email']    !== '' ? (string) $f['end_customer_email']    : null,
                    (string) $f['end_customer_phone']    !== '' ? (string) $f['end_customer_phone']    : null,
                    (int) $f['has_whatsapp'],
                    (string) $f['end_customer_address1'] !== '' ? (string) $f['end_customer_address1'] : null,
                    (string) $f['end_customer_address2'] !== '' ? (string) $f['end_customer_address2'] : null,
                    (string) $f['end_customer_town']     !== '' ? (string) $f['end_customer_town']     : null,
                    (string) $f['end_customer_county']   !== '' ? (string) $f['end_customer_county']   : null,
                    (string) $f['end_customer_postcode'] !== '' ? (string) $f['end_customer_postcode'] : null,
                    $vatPct,
                    (string) ($f['notes'] ?? '') !== '' ? (string) $f['notes'] : null,
                    $token,
                    $userId,
                ]);
                break;
            } catch (PDOException $e) {
                if ($attempt >= 3 || !str_contains($e->getMessage(), 'uniq_quote_number_per_client')) {
                    throw $e;
                }
                // race window — try a fresh number
            }
        }
        $newId = (int) $pdo->lastInsertId();

        // Link the originating measure appointment to this quote so its
        // calendar entry tracks the quote's progress. Only fills an as-yet-
        // unlinked appointment in this tenant.
        if ($appointmentId > 0) {
            $pdo->prepare(
                'UPDATE appointments SET quote_id = ?
                  WHERE id = ? AND client_id = ? AND quote_id IS NULL'
            )->execute([$newId, $appointmentId, $clientId]);
        } else {
            // No prior appointment — walk-up / on-site quote. Give it a measure
            // entry (today, assigned to the creator) so it appears on the
            // consoles as a new customer with a chain to follow.
            qb_create_measure_from_quote($pdo, $newId, $userId);
        }

        $pdo->commit();
        return ['id' => $newId, 'number' => $quoteNumber];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
