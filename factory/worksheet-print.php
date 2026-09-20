<?php
declare(strict_types=1);

/**
 * Factory · Worksheet (real order).
 *
 * Renders the production worksheet for a placed order: the order header, then
 * per Beverley line a set of labels (cutting + fabric …) from the product's
 * worksheet template, with the build variables (Trucks, H_Cut, Vanes, Mtrs …)
 * computed live from the line's own width/drop + option selections. Replaces
 * re-keying + Blind Matrix's fixed worksheet.
 *
 * Data path: quotes (header) → quote_items (lines) → quote_item_extras (options).
 * Template: worksheet_templates (per product). Engine: _partials/build_eval.php.
 *
 * ?order=<quote id>.  Print-ready (chrome hidden in print). Die-cut positioning
 * + per-printer nudge come next.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/build_eval.php';
require __DIR__ . '/../_partials/qr.php';
require __DIR__ . '/../_partials/blind_jobs.php';   // bj_streams_ordered — a label's part-specific QR
require __DIR__ . '/../_partials/roller_box.php';   // roller_box_default() — the editable boxed-label grid

requireFactory();

$pdo    = db();
$MASTER = current_factory_id();
$qid    = (int) ($_GET['order'] ?? 0);

$fmtDate = static function (?string $ts): string {
    if (!$ts) return '';
    try { return (new DateTimeImmutable($ts))->format('d/m/Y'); }
    catch (Throwable $e) { return (string) $ts; }
};
$fmtVal = static function ($v): string {
    if (is_float($v)) return rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
    if (is_bool($v))  return $v ? 'TRUE' : 'FALSE';
    return (string) $v;
};

// ---- Order header (trade customer) ----------------------------------------
$order = null;
try {
    // The "customer" on the ticket is who the order is FOR. Three cases:
    //   • account order (account_client_id set — the "New order" flow) → the trade
    //     account, e.g. Blind Corner (ac.*).
    //   • the factory's OWN retail quote (client_id = factory, no account) → the
    //     END CUSTOMER named on the quote (handled just after the fetch below) —
    //     NOT the factory itself.
    //   • a tenant/retailer's order → the owning client (c.*).
    // COALESCE here covers account-vs-owning-client; the end-customer override runs
    // after, so we also pull the quote's end_customer_* address fields.
    $q = $pdo->prepare(
        "SELECT q.id, q.quote_number, q.created_at, q.customer_reference, q.additional_reference,
                q.end_customer_name, q.end_customer_address1, q.end_customer_address2,
                q.end_customer_town, q.end_customer_county, q.end_customer_postcode, q.end_customer_phone,
                q.client_id, q.account_client_id,
                COALESCE(ac.company_name, c.company_name) AS company_name,
                ac.contact_name                           AS contact_name,
                COALESCE(ac.address1, c.address1)         AS address1,
                COALESCE(ac.address2, c.address2)         AS address2,
                COALESCE(ac.town, c.town)                 AS town,
                COALESCE(ac.county, c.county)             AS county,
                COALESCE(ac.postcode, c.postcode)         AS postcode,
                COALESCE(ac.phone, c.phone)               AS phone
           FROM quotes q
           JOIN clients c  ON c.id = q.client_id
      LEFT JOIN clients ac ON ac.id = q.account_client_id
          WHERE q.id = ? LIMIT 1"
    );
    $q->execute([$qid]);
    $order = $q->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    // Pre-migration install without quotes.account_client_id — fall back.
    try {
        $q = $pdo->prepare(
            "SELECT q.id, q.quote_number, q.created_at, q.customer_reference, q.additional_reference,
                    q.end_customer_name, q.end_customer_address1, q.end_customer_address2,
                    q.end_customer_town, q.end_customer_county, q.end_customer_postcode, q.end_customer_phone,
                    q.client_id,
                    c.company_name, c.address1, c.address2, c.town, c.county, c.postcode, c.phone
               FROM quotes q JOIN clients c ON c.id = q.client_id
              WHERE q.id = ? LIMIT 1"
        );
        $q->execute([$qid]);
        $order = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e2) { /* handled below */ }
}

// For ANY retail quote (no trade account) the "customer" on the ticket is the end
// customer named on the quote — the person the blind is for — not the business
// that owns the quote. Without this a tenant's order (e.g. ABC Blinds selling to
// Tyler Smith) shows "ABC Blinds" instead of "Tyler Smith", and the factory's own
// retail shows "Beverley Blinds Trade" instead of the customer. Trade-ACCOUNT
// orders (account_client_id set) are untouched — there the account IS the customer.
if ($order
    && (int) ($order['account_client_id'] ?? 0) === 0
    && trim((string) ($order['end_customer_name'] ?? '')) !== '') {
    $order['company_name'] = trim((string) $order['end_customer_name']);
    $order['address1']     = (string) ($order['end_customer_address1'] ?? '');
    $order['address2']     = (string) ($order['end_customer_address2'] ?? '');
    $order['town']         = (string) ($order['end_customer_town'] ?? '');
    $order['county']       = (string) ($order['end_customer_county'] ?? '');
    $order['postcode']     = (string) ($order['end_customer_postcode'] ?? '');
    $order['phone']        = (string) ($order['end_customer_phone'] ?? '');
}

// ---- Beverley lines --------------------------------------------------------
$lines = [];
if ($order) {
    try {
        // source_product_id maps the tenant's catalogue copy back to the Beverley
        // master product, where the build rules + worksheet template live.
        $li = $pdo->prepare(
            "SELECT qi.id, qi.line_no, qi.quantity, qi.product_id, qi.system_id, qi.width_mm, qi.drop_mm,
                    qi.product_name_snapshot, qi.system_name_snapshot,
                    qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.room_name, qi.notes, qi.fascia_group,
                    COALESCE(p.source_product_id, p.id) AS master_product_id
               FROM quote_items qi JOIN products p ON p.id = qi.product_id
              WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
           ORDER BY qi.line_no, qi.id"
        );
        $li->execute([$qid, $MASTER]);
        $lines = $li->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // fascia_group column may be absent on an un-migrated install — retry without it.
        try {
            $li = $pdo->prepare(
                "SELECT qi.id, qi.line_no, qi.quantity, qi.product_id, qi.system_id, qi.width_mm, qi.drop_mm,
                        qi.product_name_snapshot, qi.system_name_snapshot,
                        qi.fabric_name_snapshot, qi.fabric_colour_snapshot, qi.room_name, qi.notes, NULL AS fascia_group,
                        COALESCE(p.source_product_id, p.id) AS master_product_id
                   FROM quote_items qi JOIN products p ON p.id = qi.product_id
                  WHERE qi.quote_id = ? AND COALESCE(NULLIF(p.source_client_id,0), p.client_id) = ?
               ORDER BY qi.line_no, qi.id"
            );
            $li->execute([$qid, $MASTER]);
            $lines = $li->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) { /* leave empty */ }
    }
}
$totalLines = count($lines);

// Blinds sharing one fascia (same non-empty fascia_group, 2+ members) are cut
// with the editable "multiple blinds in one fascia" allowances. Expose that to
// the build engine as a synthetic "Multiple Blinds in One Fascia = Yes" option
// selection so the Tube_Cut / Fabric_W decision tables' Multiple branch fires —
// no stored option needed. Single blinds and 1-member groups are untouched.
$fasciaGroupCounts = [];
foreach ($lines as $ln) {
    $g = strtoupper(trim((string) ($ln['fascia_group'] ?? '')));
    if ($g !== '') $fasciaGroupCounts[$g] = ($fasciaGroupCounts[$g] ?? 0) + 1;
}

// ---- Per-line option selections (for the build engine + detail fields) -----
$extrasBy = [];
if ($lines) {
    $ids = array_map(static fn ($l) => (int) $l['id'], $lines);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    // Join the live option row for its stable machine code + current name, so the
    // label can resolve opt:<code> (rename-proof) as well as opt:<snapshot-name>.
    $sql = "SELECT qie.quote_item_id, qie.product_extra_id, qie.extra_name_snapshot, qie.choice_label_snapshot, qie.user_value,
                   pe.code AS extra_code, pe.name AS extra_live_name
              FROM quote_item_extras qie
              LEFT JOIN product_extras pe ON pe.id = qie.product_extra_id
             WHERE qie.quote_item_id IN ($ph) ORDER BY qie.id";
    try {
        $ex = $pdo->prepare($sql);
        $ex->execute($ids);
        foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extrasBy[(int) $r['quote_item_id']][] = $r; }
    } catch (Throwable $e) {
        // user_value and/or product_extras.code may be absent on un-migrated
        // installs — retry without them (name-only resolution still works).
        try {
            $ex = $pdo->prepare("SELECT quote_item_id, product_extra_id, extra_name_snapshot, choice_label_snapshot, NULL AS user_value,
                                        NULL AS extra_code, NULL AS extra_live_name
                                   FROM quote_item_extras WHERE quote_item_id IN ($ph) ORDER BY id");
            $ex->execute($ids);
            foreach ($ex->fetchAll(PDO::FETCH_ASSOC) as $r) { $extrasBy[(int) $r['quote_item_id']][] = $r; }
        } catch (Throwable $e2) { /* no extras */ }
    }
}

// ---- Worksheet template per product (default) ------------------------------
$templateByProduct = [];
$loadTemplate = static function (PDO $pdo, int $pid) use (&$templateByProduct) {
    if (array_key_exists($pid, $templateByProduct)) return $templateByProduct[$pid];
    $tpl = null;
    try {
        $ts = $pdo->prepare('SELECT layout_json FROM worksheet_templates WHERE product_id = ? ORDER BY is_default DESC, id LIMIT 1');
        $ts->execute([$pid]);
        $row = $ts->fetch(PDO::FETCH_ASSOC);
        if ($row) $tpl = json_decode((string) $row['layout_json'], true) ?: null;
    } catch (Throwable $e) { /* none */ }
    return $templateByProduct[$pid] = $tpl;
};

// ---- Order-level detail fields (order:<key>) -------------------------------
$addr = trim(implode(', ', array_filter([
    trim((string) ($order['address1'] ?? '')),
    trim((string) ($order['address2'] ?? '')),
    trim((string) ($order['town'] ?? '')),
    trim((string) ($order['county'] ?? '')),
])), ', ');
// Trade orders carry a contact person on the account — show "Contact — Company"
// so the workshop knows who to ask for; tenant/retail orders have no contact and
// stay as the company/customer name alone.
$company = (string) ($order['company_name'] ?? '');
$contact = trim((string) ($order['contact_name'] ?? ''));
$customerLine = $contact !== '' ? ($contact . ' — ' . $company) : $company;
$orderVals = $order ? [
    'order_no'   => (string) ($order['quote_number'] ?? ('#' . $qid)),
    'order_date' => $fmtDate($order['created_at'] ?? null),
    'customer'   => $customerLine,
    'company'    => $company,
    'contact'    => $contact,
    'address'    => $addr,   // whole address on one line (kept for existing templates)
    // …and the pieces, so an address block can be built line-by-line on the header.
    'address1'   => (string) ($order['address1'] ?? ''),
    'address2'   => (string) ($order['address2'] ?? ''),
    'town'       => (string) ($order['town'] ?? ''),
    'county'     => (string) ($order['county'] ?? ''),
    'post_code'  => (string) ($order['postcode'] ?? ''),
    'phone'      => (string) ($order['phone'] ?? ''),
    'cust_ref'   => (string) ($order['customer_reference'] ?? ''),
] : [];

// ---- Resolve each line: detail fields + computed build variables ----------
$rendered = [];   // [ ['ctx'=>lineDetailVals merged with order, 'computed'=>vars, 'template'=>tpl, 'product'=>name], ... ]
foreach ($lines as $ln) {
    $extras = $extrasBy[(int) $ln['id']] ?? [];

    $byName     = [];   // lower group name (order-time snapshot) => chosen label
    $userVal    = [];   // lower group name => typed numeric input (user_value)
    $byCode     = [];   // stable machine code => chosen label (rename-proof)
    $uvCode     = [];   // stable machine code => typed numeric input
    $byLive     = [];   // current option name (lower) => chosen label
    $uvLive     = [];   // current option name (lower) => typed numeric input
    foreach ($extras as $r) {
        $nm  = strtolower(trim((string) $r['extra_name_snapshot']));
        $lbl = (string) $r['choice_label_snapshot'];
        $num = is_numeric($r['user_value'] ?? null) ? (float) $r['user_value'] : null;
        $byName[$nm] = $lbl;
        if ($num !== null) $userVal[$nm] = $num;
        $code = strtolower(trim((string) ($r['extra_code'] ?? '')));
        if ($code !== '') { $byCode[$code] = $lbl; if ($num !== null) $uvCode[$code] = $num; }
        $live = strtolower(trim((string) ($r['extra_live_name'] ?? '')));
        if ($live !== '' && $live !== $nm) { $byLive[$live] = $lbl; if ($num !== null) $uvLive[$live] = $num; }
    }
    $fitHeight = $userVal['fit height'] ?? 0.0;
    // Wand length is a typed input riding on the "Wand Options" row (user_value).
    $numTidy  = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $wandLen  = isset($userVal['wand options']) ? $numTidy($userVal['wand options']) : '';
    // Fascia width: the number typed in the "Fascia width" option box (blank/0 =
    // fit blind). Exposed so a build rule (Fascia_Cut) can cut the fascia to a
    // manual width — e.g. full width past a skirting board — instead of the blind
    // width. Defaults to the ordered width so a blank box behaves as before.
    // (The value used to ride on the "Fascia Options" group; it now lives in its
    // own "Fascia width" option — a child of Fascia Sizing.)
    $fasciaWidth = (($userVal['fascia width'] ?? 0) > 0)
        ? (float) $userVal['fascia width'] : (float) $ln['width_mm'];
    // Decision tables match on the System axis + option group NAME (tenant group
    // ids differ from the master's), so key selections by name.
    $optSel = array_merge(['system' => (string) ($ln['system_name_snapshot'] ?? '')], $byName);
    // Shared-fascia member? Flag it for the cut rules' "multiple blinds in one
    // fascia" branch (keyed on the option-group name, lower-cased).
    $lnGroup = strtoupper(trim((string) ($ln['fascia_group'] ?? '')));
    if ($lnGroup !== '' && ($fasciaGroupCounts[$lnGroup] ?? 0) >= 2) {
        $optSel['multiple blinds in one fascia'] = 'Yes';
    }
    $masterPid = (int) ($ln['master_product_id'] ?? $ln['product_id']);
    $pick = static function (array $byName, array $names): string {
        foreach ($names as $n) { if (isset($byName[$n])) return $byName[$n]; }
        return '';
    };

    // Quantity is exposed so build rules can reference the ordered qty — e.g. a
    // fabric-only Vanes = Quantity + 1 (the spare vane) and a total-metres calc.
    $numVars = ['Width' => (float) $ln['width_mm'], 'Drop' => (float) $ln['drop_mm'], 'Fit_height' => $fitHeight,
                'Quantity' => (float) $ln['quantity'], 'Fascia_Width' => $fasciaWidth];
    $eval    = build_evaluate($pdo, $masterPid, $numVars, $optSel);

    $lineVals = [
        'line_no'      => $ln['line_no'] . '/' . $totalLines,
        'system'       => (string) ($ln['system_name_snapshot'] ?? ''),
        'colour'       => (string) ($ln['fabric_colour_snapshot'] ?? ''),
        'hd_colour'    => $pick($byName, ['hd colour', 'headrail colour', 'head rail colour']),
        'fabric'       => (string) ($ln['fabric_name_snapshot'] ?? ''),
        'location'     => (string) ($ln['room_name'] ?? ''),
        'size'         => (int) $ln['width_mm'] . ' x ' . (int) $ln['drop_mm'],
        'width'        => (string) (int) $ln['width_mm'],
        'drop'         => (string) (int) $ln['drop_mm'],
        'qty'          => (string) (int) $ln['quantity'],
        'notes'        => (string) ($ln['notes'] ?? ''),
        'control'      => $pick($byName, ['control options', 'control']),
        'chain'        => $pick($byName, ['chain', 'chain type']),
        'draw'         => $pick($byName, ['draw options', 'wand options', 'draw']),
        'wand_length'  => $wandLen,
        'fit_height'   => $fitHeight > 0 ? $numTidy($fitHeight) : '',
        'bracket'      => $pick($byName, ['brackets', 'bracket', 'fix', 'fixing', 'fitting', 'fit type']),
        'recess_exact' => $pick($byName, ['exact or recess', 'recess or exact', 'recess']),
        'welded'       => $pick($byName, ['fabric finish', 'welded', 'weld', 'joint']),
        'bottom_weight' => $pick($byName, ['bottom weight option', 'bottom weight', 'weight']),
        'weight_colour' => $pick($byName, ['colour', 'weight colour', 'bottom weight colour']),
    ];

    // Generic per-product option values (opt:<group name>) — every option group
    // on this order, keyed by name, so any product's own options resolve. The
    // chosen label, or the typed number (fit height / wand length) when the
    // group carries a value rather than a choice.
    // Key each opt: value by the option's stable code AND its name (order-time
    // snapshot and current), so a label field source of opt:<code> resolves even
    // after the option is renamed, while legacy opt:<name> sources keep working.
    $optLabel = $byName + $byLive + $byCode;   // union; earlier arrays win on key clash
    $optUv    = $userVal + $uvLive + $uvCode;
    foreach ($optLabel as $gk => $label) $lineVals['opt:' . $gk] = $label;
    foreach ($optUv as $gk => $uv) {
        $ok = 'opt:' . $gk;
        if (($lineVals[$ok] ?? '') === '') $lineVals[$ok] = $numTidy($uv);
    }

    // ONE LABEL PER PHYSICAL BLIND, not per order line. A qty-3 line is three
    // blinds that are tracked, routed and scanned separately, so each needs its
    // own ticket carrying its own code — a single label saying "qty 3" can't
    // follow three blinds round three different benches.
    // Default: one label per physical blind. A template can opt into one label
    // per LINE (layout.one_per_line) — fabric-only slats want a single "Qty 50"
    // ticket, not 50. Then the label shows order:qty instead of a unit number.
    $qty  = max(1, (int) $ln['quantity']);
    $tpl        = $loadTemplate($pdo, $masterPid);
    $onePerLine = is_array($tpl) && !empty($tpl['one_per_line']);
    $labelCount = $onePerLine ? 1 : $qty;
    $streams = function_exists('bj_streams_ordered') ? bj_streams_ordered($pdo, $masterPid) : [];
    for ($u = 1; $u <= $labelCount; $u++) {
        $unitVals = [
            'unit'     => $onePerLine ? '' : ($qty > 1 ? $u . '/' . $qty : ''),
            'unit_no'  => (string) $u,
            // A per-label code is filled in at render time (each label carries
            // its own part's stream), so this whole-blind code is only a
            // fallback for a label with no stream position.
            'qr_code'  => function_exists('qr_blind_code') ? qr_blind_code((int) $ln['id'], $u) : '',
        ];
        $rendered[] = [
            'ctx'      => array_merge($orderVals, $lineVals, $unitVals),
            'computed' => $eval['vars'],
            'template' => $tpl,
            'product'  => (string) ($ln['product_name_snapshot'] ?? ''),
            'item_id'  => (int) $ln['id'],
            'unit_no'  => $u,
            'streams'  => $streams,   // ordered stream names for this product
        ];
    }
}

// ---- Global per-blind numbering across the whole order ----------------------
// Every PHYSICAL blind (a per-blind ticket, not a one-per-line "Qty N" ticket)
// gets a running "N of M" so a 48-blind order numbers its tickets 1..48 of 48,
// regardless of how the lines/quantities were entered (one qty-48 line or 48
// separate lines). Exposed as order:blind_no (N), order:blind_total (M) and
// order:blind_seq ("N of M"). The old order:line_no ("1/1") is the LINE number,
// which is identical on every label of a single multi-qty line.
$blindTotal = 0;
foreach ($rendered as $r) { if (empty($r['template']['one_per_line'])) $blindTotal++; }
$blindNo = 0;
foreach ($rendered as &$r) {
    $perBlind = empty($r['template']['one_per_line']);
    if ($perBlind) $blindNo++;
    $r['ctx']['blind_no']    = $perBlind ? (string) $blindNo : '';
    $r['ctx']['blind_total'] = (string) $blindTotal;
    $r['ctx']['blind_seq']   = $perBlind ? ($blindNo . ' of ' . $blindTotal) : '';
}
unset($r);

/**
 * The context for one LABEL of a blind, with its QR pointing at that label's
 * part. A label's position IS its stream position: on a vertical, label 0 (the
 * cutting label) carries the Headrail code, label 1 (the fabric label) the
 * Fabric code, so each scan finishes exactly that part. Single-stream products
 * (roller, pleated) use digit 0 = the whole blind, whichever label is scanned.
 */
$labelCtx = static function (array $r, int $labelIndex): array {
    $ctx = $r['ctx'];
    if (function_exists('qr_blind_code')) {
        $streams = $r['streams'] ?? [];
        $digit   = count($streams) <= 1 ? 0 : min($labelIndex + 1, count($streams));
        $ctx['qr_code'] = qr_blind_code((int) ($r['item_id'] ?? 0), (int) ($r['unit_no'] ?? 1), $digit);
    }
    return $ctx;
};

// Header template: take the first line's product template (they share the die-cut header).
$headerFields = [];
$headerBlock  = [];   // the header section itself — carries its font size / line spacing
$layoutQr     = null; // QR size (mm) set in the Worksheets editor, if any
foreach ($rendered as $r) { if ($r['template'] && !empty($r['template']['header']['fields'])) { $headerFields = $r['template']['header']['fields']; $headerBlock = $r['template']['header']; break; } }
foreach ($rendered as $r) { if (!empty($r['template']) && isset($r['template']['qr']) && is_numeric($r['template']['qr'])) { $layoutQr = (float) $r['template']['qr']; break; } }

// Per-label font (pt) + line spacing, set in the Worksheets editor. Falls back to
// the stock's built-in default so a template saved before this feature prints
// unchanged. Emitted as an inline style on the label's .flds.
$labelTypeStyle = static function (array $labelBlock, float $fsDefault, float $lhDefault): string {
    $fs = isset($labelBlock['fs']) && is_numeric($labelBlock['fs']) ? (float) $labelBlock['fs'] : $fsDefault;
    $lh = isset($labelBlock['lh']) && is_numeric($labelBlock['lh']) ? (float) $labelBlock['lh'] : $lhDefault;
    $n  = static fn (float $v): string => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    return 'font-size:' . $n($fs) . 'pt;line-height:' . $n($lh);
};

/** Render one template field to "caption value" (or null to omit). */
$fieldText = static function (array $f, array $ctx, array $computed) use ($fmtVal): ?string {
    $src  = (string) ($f['source'] ?? '');
    $show = (string) ($f['show'] ?? 'always');
    $cap  = trim((string) ($f['caption'] ?? ''));
    $val  = '';
    if ($src === 'text') { $val = $cap; $cap = ''; }
    elseif (strncmp($src, 'var:', 4) === 0) { $n = substr($src, 4); $val = array_key_exists($n, $computed) ? $fmtVal($computed[$n]) : ''; }
    elseif (strncmp($src, 'order:', 6) === 0) { $k = substr($src, 6); $val = (string) ($ctx[$k] ?? ''); }
    elseif (strncmp($src, 'opt:', 4) === 0)   { $val = (string) ($ctx[$src] ?? ''); }
    elseif (strncmp($src, 'barcode:', 8) === 0) { $k = substr($src, 8); $val = '▏▎▍▌▍▎▏ ' . (string) ($ctx[$k] ?? ''); }
    if ($show === 'never') return null;
    if ($show === 'ifvalue' && trim($val) === '') return null;
    return $cap !== '' ? ($cap . ' ' . $val) : $val;
};

/**
 * A field as HTML. Everything is escaped text EXCEPT the QR, which is a graphic
 * — so it can't go through $fieldText, whose callers e() the result and would
 * print the SVG source as gibberish. Every render path goes through here, so a
 * qr field can't work on one view and silently vanish on another.
 */
$fieldHtml = static function (array $f, array $ctx, array $computed, float $qrMm = 12) use ($fieldText): ?string {
    if (($f['source'] ?? '') === 'qr') {
        $code = (string) ($ctx['qr_code'] ?? '');
        if ($code === '') return null;
        return '<span class="qr">' . qr_svg($code, $qrMm) . '</span>';
    }
    $t = $fieldText($f, $ctx, $computed);
    return $t === null ? null : e($t);
};

/**
 * Render a label's fields as line blocks, with the QR floated into the bottom-
 * right corner. Grouping each line (fields between "line break" markers) into its
 * own <div class="ln"> is what lets a right-aligned field align to the RIGHT edge
 * of its line — which, on the bottom line beside the QR, is the QR's left edge. So
 * a right-aligned date stops AT the QR instead of running under it, while the top
 * lines still use the full width. Empty lines collapse to nothing (height 0), so
 * a break with no content behaves exactly like before. QR comes last (it floats).
 */
$renderLineFields = static function (array $fields, array $ctx, array $computed, callable $fieldHtml, float $qrMm): string {
    // Group into lines (split on breaks) and pull out the QR.
    $lineGroups = [[]]; $qr = '';
    foreach ($fields as $f) {
        $src = (string) ($f['source'] ?? '');
        if ($src === 'qr') { $t = $fieldHtml($f, $ctx, $computed, $qrMm); if ($t !== null) $qr = $t; continue; }
        $lineGroups[count($lineGroups) - 1][] = $f;
        if ($src === '__break__') $lineGroups[] = [];
    }
    $out = '';
    foreach ($lineGroups as $grp) {
        // A line with a CENTRE field is laid out in three slots — left, centre,
        // right — with the outer two sharing the leftover space equally. That is
        // what makes the centre an actual centre of the LABEL.
        //
        // It used to be margin-left:auto + margin-right:auto on the field, and a
        // right-aligned field on the same line was given margin-left:auto too.
        // Flex splits the free space equally between every auto margin, so with
        // three of them in play the "centred" field landed a third of the way
        // across, biased toward whichever side had less text. Which is why a
        // field set to Centre sat left of centre, and why centre used to be
        // quietly downgraded to left whenever the line also had a right field.
        $hasCentre = false;
        foreach ($grp as $f) { if (($f['align'] ?? '') === 'centre') { $hasCentre = true; break; } }

        $slots = ['' => '', 'centre' => '', 'right' => ''];   // used only when centred
        $inOrder = '';                                        // used otherwise
        foreach ($grp as $f) {
            if ((string) ($f['source'] ?? '') === '__break__') continue;   // boundary only
            $t = $fieldHtml($f, $ctx, $computed, $qrMm);
            if ($t === null) continue;
            $al  = (string) ($f['align'] ?? '');
            if ($al !== 'centre' && $al !== 'right') $al = '';
            $cls = $al === 'right' ? ' class="r"' : ($al === 'centre' ? ' class="c"' : '');
            $span = '<span' . $cls . '>' . $t . '</span>';
            $slots[$al] .= $span;
            $inOrder    .= $span;
        }

        if ($hasCentre) {
            $out .= '<div class="ln has-c">'
                  . '<span class="g gl">' . $slots[''] . '</span>'
                  . '<span class="g gc">' . $slots['centre'] . '</span>'
                  . '<span class="g gr">' . $slots['right'] . '</span>'
                  . '</div>';
        } else {
            // No centre field on this line: emitted in the authored order, exactly
            // as before, so every existing label prints identically.
            $out .= '<div class="ln">' . $inOrder . '</div>';
        }
    }
    return $out . $qr;
};

// A product prints on its OWN stock: rollers on the 102×76 thermal roll, the
// rest on the A4 die-cut sheet — different printers. A mixed order (a vertical
// AND a roller) therefore needs BOTH runs, so split the blinds by stock rather
// than forcing the whole order onto whichever product happened to come first.
$rollBlinds   = [];
$diecutBlinds = [];
foreach ($rendered as $r) {
    if ((string) ($r['template']['stock'] ?? '') === 'roll-102x76') $rollBlinds[] = $r;
    else                                                            $diecutBlinds[] = $r;
}

// Number each print RUN on its own. Rollers print on the thermal roll and
// verticals on the die-cut sheet as SEPARATE jobs, so their "X of Y" must be
// scoped to their own run — otherwise a 3-blind order (1 vertical + 2 rollers)
// numbers across the whole order, the lone vertical reads "1 of 3", and the bench
// hunts for two blinds that are on the other printer. Renumber line_no + blind_seq
// within each group so the vertical reads "1 of 1" and the rollers "1 of 2"/"2 of 2".
$renumberRun = static function (array $group): array {
    $lineIdx = [];   // item_id => sequential line number within this run
    foreach ($group as $g) {
        $id = (int) ($g['item_id'] ?? 0);
        if (!isset($lineIdx[$id])) $lineIdx[$id] = count($lineIdx) + 1;
    }
    $lineTotal  = max(1, count($lineIdx));
    $blindTotal = 0;
    foreach ($group as $g) { if (empty($g['template']['one_per_line'])) $blindTotal++; }
    $blindNo = 0;
    foreach ($group as &$g) {
        $id = (int) ($g['item_id'] ?? 0);
        $g['ctx']['line_no'] = ($lineIdx[$id] ?? 1) . '/' . $lineTotal;
        $perBlind = empty($g['template']['one_per_line']);
        if ($perBlind) $blindNo++;
        $g['ctx']['blind_no']    = $perBlind ? (string) $blindNo : '';
        $g['ctx']['blind_total'] = (string) $blindTotal;
        $g['ctx']['blind_seq']   = $perBlind ? ($blindNo . ' of ' . $blindTotal) : '';
    }
    unset($g);
    return $group;
};
$rollBlinds   = $renumberRun($rollBlinds);
$diecutBlinds = $renumberRun($diecutBlinds);

$hasRoll   = $rollBlinds   !== [];
$hasDiecut = $diecutBlinds !== [];

// ---- Roll label print (?rolllabel=1) — one self-contained label per blind --
// For roller blinds: a single label per blind on a thermal roll (default
// 102x76mm), not the vertical A4 die-cut sheet. Per-computer nudge + font.
if ($order && ($_GET['rolllabel'] ?? '0') !== '0') {
    $ff = static fn (string $k, float $d): float => isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : $d;
    // Label size + font default come from the roller template's OWN label (set in
    // the Worksheets editor), so changing the size there actually changes what
    // prints. Falls back to the 102x76 thermal-roll default when the template has
    // no explicit size; a URL ?w=&h=&fs= still overrides (the on-screen tools).
    $rollLab = $rollBlinds[0]['template']['labels'][0] ?? null;
    $labDim  = static fn (string $k, float $d): float =>
        (is_array($rollLab) && isset($rollLab[$k]) && is_numeric($rollLab[$k]) && (float) $rollLab[$k] > 0)
            ? (float) $rollLab[$k] : $d;
    $LW = $ff('w', $labDim('w', 101)); $LH = $ff('h', $labDim('h', 152)); $fs = $ff('fs', $labDim('fs', 9));
    $linesOn = (($_GET['lines'] ?? '1') !== '0');
    $mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

    // QR size from the ROLLER template's own setting (set in the Worksheets
    // editor) — not the first blind of a mixed order, which could be a vertical.
    // Falls back to any template qr, then the 20mm roll default; URL ?qr= wins.
    $rollTpl = $rollBlinds[0]['template'] ?? [];
    $rollQr  = (is_array($rollTpl) && isset($rollTpl['qr']) && is_numeric($rollTpl['qr']) && (float) $rollTpl['qr'] > 0)
        ? (float) $rollTpl['qr']
        : (is_numeric($layoutQr) ? (float) $layoutQr : 20);
    $qrMm = $ff('qr', $rollQr);   // roller: 101x152mm thermal roll, room to spare

    $renderFields = static function (array $fields, array $ctx, array $computed) use ($renderLineFields, $fieldHtml, $qrMm): string {
        return $renderLineFields($fields, $ctx, $computed, $fieldHtml, $qrMm);
    };

    // Pull the first non-blank option value from a list of candidate opt: keys
    // (option group names are lower-cased in $ctx). Placeholder "no value"
    // selections are treated as blank so empty boxes stay clean — but real
    // negatives like "No" / "No Fascia" / "No Scallop" are kept (the bench needs
    // to see them). Candidate lists let a box read whichever of several system
    // options fed it (e.g. Senses vs Louvolite colour, or the two Fixings options).
    $rlVal = static function (array $ctx, array $keys): string {
        $skip = ['none', 'not needed', 'none required', 'not applicable', 'n/a', 'na',
                 'please select', 'select', 'not required', 'select braid type'];
        foreach ($keys as $k) {
            $v = trim((string) ($ctx[$k] ?? ''));
            if ($v === '' || in_array(strtolower($v), $skip, true)) continue;
            return $v;
        }
        return '';
    };

    // The roller roll label — a boxed, ruled grid modelled on the workshop's
    // Excel label, driven by the order's real option values with the SYSTEM
    // option names as captions. Optional rows (braid/pole/safety, motor extras)
    // collapse out when empty so a plain chain roller stays compact, and expand
    // for a motorised blind. Bracket Covers prints Yes/No only.
    // The bespoke boxed roller label. The TOP grid + the CUT row are now DATA —
    // rows of cells {cap, src, w, big} stored on the template as labels[0].box —
    // so the Worksheets editor can change them. When the template stores no box,
    // roller_box_default() (below, shared with the editor + seed) supplies today's
    // exact layout, so nothing changes until someone edits it.
    $rollerLabelHtml = static function (array $ctx, array $computed, array $bottomFields = [], ?array $boxDef = null) use ($rlVal, $qrMm): string {
        $e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        // A computed build variable (cut size), tidied: mm -> integer; "" stays blank.
        $cv = static function (string $name) use ($computed): string {
            $v = $computed[$name] ?? null;
            if ($v === null) return '';
            if (is_numeric($v)) return rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
            return trim((string) $v);
        };
        // Composite + brand-merged values, exposed as ctx keys so every grid cell
        // references a SINGLE source (a fascia's colour lives under senses_* OR
        // ll_*/unishade_* depending on the order; only one is populated).
        $name = trim((string) ($ctx['customer'] ?? '')); if ($name === '') $name = trim((string) ($ctx['company'] ?? ''));
        $ref  = trim((string) ($ctx['cust_ref'] ?? '')); $orderNo = trim((string) ($ctx['order_no'] ?? ''));
        $wv = trim((string) ($ctx['width'] ?? '')); $dv = trim((string) ($ctx['drop'] ?? ''));
        $meas = $rlVal($ctx, ['opt:exact_or_recess', 'opt:exact or recess', 'recess_exact']);
        $fh = trim((string) ($ctx['fit_height'] ?? '')); if ($fh !== '') $meas = trim($meas . '  FH ' . $fh);
        $ctx['name_cell']     = $name;
        $ctx['order_cell']    = $ref !== '' ? ($orderNo . ' · ' . $ref) : $orderNo;
        $ctx['size']          = ($wv !== '' || $dv !== '') ? ($wv . ' × ' . $dv) : (string) ($ctx['size'] ?? '');
        $ctx['measurement']   = $meas;
        $ctx['bb_colour']     = $rlVal($ctx, ['opt:senses_bottom_bar_colour', 'opt:unishade_bottom_bar_colour', 'opt:senses bottom bar colour', 'opt:unishade bottom bar colour']);
        $ctx['bb_endcaps']    = $rlVal($ctx, ['opt:senses_bottom_bar_end_cap_colours', 'opt:unishade_end_cap_colours', 'opt:senses bottom bar end cap colours', 'opt:uni shade end cap colours']);
        $ctx['fascia_colour'] = $rlVal($ctx, ['opt:senses_profile_colour', 'opt:ll_profile_colour', 'opt:senses profile colour', 'opt:ll profile colour']);
        $ctx['fascia_endcaps']= $rlVal($ctx, ['opt:senses_end_cap_colour', 'opt:ll_end_cap_colour', 'opt:senses end cap colour', 'opt:ll end cap colour']);
        $ctx['fixings_val']   = $rlVal($ctx, ['opt:fixings', 'opt:fixings_2', 'opt:fixings (senses)', 'opt:fixings (louvolite)']);

        // Resolve one cell's single source to its value (no caption).
        $val = static function (string $src) use ($ctx, $cv): string {
            if ($src === '') return '';
            if (strncmp($src, 'var:', 4) === 0)  return $cv(substr($src, 4));
            if (strncmp($src, 'order:', 6) === 0) return (string) ($ctx[substr($src, 6)] ?? '');
            if (strncmp($src, 'opt:', 4) === 0)   return (string) ($ctx[$src] ?? '');
            return '';
        };
        $cellHtml = static function (array $c) use ($e, $val): string {
            $wt = (float) ($c['w'] ?? 1); if ($wt <= 0) $wt = 1;
            $cls = 'rc' . (!empty($c['big']) ? ' rcut' : '');
            return '<div class="' . $cls . '" style="flex:' . $wt . '"><span class="rcap">'
                 . $e((string) ($c['cap'] ?? '')) . '</span><span class="rval">' . $e($val((string) ($c['src'] ?? ''))) . '</span></div>';
        };

        $box = (is_array($boxDef) && (!empty($boxDef['grid']) || !empty($boxDef['cut']))) ? $boxDef : roller_box_default();

        // TOP grid — each row. A cell flagged 'ifvalue' is dropped when its value
        // is blank (so "Braid / Pole / …" don't print empty captions); a row-level
        // hide_if_empty skips the whole row when all cells are blank; and a row that
        // ends up with no cells (all hidden) is skipped too.
        $grid = '';
        foreach (($box['grid'] ?? []) as $row) {
            $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
            if (!$cells) continue;
            if (!empty($row['hide_if_empty'])) {
                $any = false; foreach ($cells as $c) { if (trim($val((string) ($c['src'] ?? ''))) !== '') { $any = true; break; } }
                if (!$any) continue;
            }
            $inner = '';
            foreach ($cells as $c) {
                if (!empty($c['ifvalue']) && trim($val((string) ($c['src'] ?? ''))) === '') continue;
                $inner .= $cellHtml($c);
            }
            if ($inner === '') continue;
            $grid .= '<div class="rr">' . $inner . '</div>';
        }

        // CUT row (big bold numbers) — a cut cell can also hide when blank.
        $cutInner = '';
        foreach (($box['cut'] ?? []) as $c) {
            if (!empty($c['ifvalue']) && trim($val((string) ($c['src'] ?? ''))) === '') continue;
            $c['big'] = true; $cutInner .= $cellHtml($c);
        }
        $cutRow = $cutInner !== '' ? '<div class="rr rcutrow">' . $cutInner . '</div>' : '';

        // Notes + QR. (The old flat "field list" that printed here as a row of
        // text spans is retired — it duplicated the box grid above, which is now
        // the single editable source. $bottomFields is intentionally ignored.)
        $qr = ''; $code = (string) ($ctx['qr_code'] ?? '');
        if ($code !== '') $qr = '<span class="qr">' . qr_svg($code, $qrMm) . '</span>';
        $notes = '<div class="rr rnotes"><div class="rc" style="flex:10"><span class="rcap">Additional Notes</span><span class="rval">'
               . $e(trim((string) ($ctx['notes'] ?? ''))) . '</span></div>' . $qr . '</div>';

        return '<div class="rl-grid">' . $grid . '</div>'
             . '<div class="rl-bottom">' . $cutRow . $notes . '</div>';
    };
    header('Content-Type: text/html; charset=utf-8');
    $ono = e((string) ($order['quote_number'] ?? ('#' . $qid)));
    $ol  = $linesOn ? ' outline' : '';
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Roll label · <?= $ono ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    :root { --fs:<?= $mm($fs) ?>pt; --nx:0mm; --ny:0mm; }
    body { background:#666; font-family:system-ui,sans-serif; }
    .toolbar { position:fixed; top:0; left:0; right:0; background:#1f2a37; color:#e5edf5; padding:10px 16px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; z-index:10; font-size:14px; }
    .toolbar b { color:#fff; } .toolbar .note { color:#b9c6d3; flex:1; min-width:220px; }
    .toolbar a { color:#7dd3fc; text-decoration:none; } .toolbar a:hover { text-decoration:underline; }
    .toolbar > button { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:7px 16px; background:#38bdf8; color:#06263a; }
    .nudge { display:flex; align-items:center; gap:4px; color:#b9c6d3; white-space:nowrap; }
    .nudge .lbl { color:#8ba0b3; } .nudge input { width:3.2rem; font:inherit; border:none; border-radius:6px; padding:5px 6px; text-align:right; }
    .nudge button { font:inherit; cursor:pointer; border:none; border-radius:6px; padding:5px 9px; background:#2c3a4a; color:#e5edf5; }
    .stack { padding:72px 0 40px; display:flex; flex-direction:column; align-items:center; gap:10px; }
    .rl-label { position:relative; width:<?= $mm($LW) ?>mm; height:<?= $mm($LH) ?>mm; background:#fff; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.4); }
    .rl-label.outline { outline:0.2mm solid #c9c9c9; outline-offset:-0.2mm; }
    .rl-label .flds { position:absolute; inset:0; padding:2.5mm 3mm; transform:translate(var(--nx),var(--ny));
                      line-height:1.18;
                      font-family:Arial,"Helvetica Neue",Helvetica,sans-serif; font-variant-numeric:tabular-nums; font-size:var(--fs); color:#000; }
    /* Each line is a flex row → true Left / Centre / Right via auto margins; a script
       reserves the QR width for right fields level with the QR. */
    .rl-label .flds .ln { display:flex; flex-wrap:wrap; align-content:flex-start; gap:0 2.4mm; }
    .rl-label .flds .ln span { white-space:nowrap; }
    /* QR pinned ABSOLUTELY to the bottom-right corner — fixed, never pushed off by
       the content flow. line-height:0 keeps span padding out of the scanner quiet zone. */
    .rl-label .flds .qr { position:absolute; right:3mm; bottom:2.5mm; line-height:0; }
    .rl-label .flds .qr svg { display:block; }
    .rl-label .flds .ln .r { margin-left:auto; }
    /* Three equal-outer slots: the middle one is the true centre of the label,
       whatever is either side of it. See renderLineFields for why auto margins
       couldn't do this. */
    .rl-label .flds .ln.has-c { display:grid; grid-template-columns:1fr auto 1fr; align-items:baseline; }
    .rl-label .flds .ln.has-c > .g { display:flex; flex-wrap:wrap; gap:0 2.4mm; min-width:0; }
    .rl-label .flds .ln.has-c > .gl { justify-content:flex-start; }
    .rl-label .flds .ln.has-c > .gc { justify-content:center; }
    .rl-label .flds .ln.has-c > .gr { justify-content:flex-end; }
    .rl-label .flds .ln.has-c .r,
    .rl-label .flds .ln.has-c .c { margin-left:0; margin-right:0; }   /* the slots do it now */
    /* Boxed, ruled roller label (Excel-style grid) — rows share the height, each
       cell stacks a small uppercase caption over the value. Nudge shifts the
       whole label for a mis-registered printer. */
    .rl-label { display:flex; flex-direction:column; border:0.4mm solid #000;
                transform:translate(var(--nx),var(--ny)); font-family:"Arial Narrow",Arial,sans-serif; }
    /* Top: fixed option grid (upper ~62%). Bottom: cut sizes + designer area. */
    .rl-label .rl-grid   { flex:0 0 62%; display:flex; flex-direction:column; min-height:0; }
    .rl-label .rl-bottom { flex:1 1 0;  display:flex; flex-direction:column; min-height:0; border-top:0.8mm solid #000; }
    .rl-label .rr { display:flex; flex:1 1 0; min-height:0; border-top:0.3mm solid #000; }
    .rl-label .rl-grid .rr:first-child, .rl-label .rl-bottom .rr:first-child { border-top:none; }
    .rl-label .rr.rnotes { flex:1.6 1 0; position:relative; }
    .rl-label .rc { display:flex; flex-direction:column; justify-content:flex-start;
                    padding:0.4mm 1mm; border-left:0.3mm solid #000; overflow:hidden; min-width:0; }
    .rl-label .rc:first-child { border-left:none; }
    .rl-label .rcap { font-size:1.9mm; font-weight:700; letter-spacing:0.1px; line-height:1.05;
                      text-transform:uppercase; color:#000; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .rl-label .rval { font-size:3.3mm; font-weight:700; line-height:1.06; margin-top:0.4mm; color:#000; overflow-wrap:anywhere; }
    .rl-label .rnotes .rval { font-size:2.7mm; font-weight:600; }
    /* Cut sizes — the key numbers the bench works to: taller row, bigger bold
       values, heavier rules top & bottom so they stand out on the ticket. */
    .rl-label .rr.rcutrow { flex:1 1 0; border-top:none; border-bottom:0.6mm solid #000; }
    .rl-label .rcut .rcap { font-size:2mm; }
    .rl-label .rcut .rval { font-size:5.5mm; font-weight:800; line-height:1.02; }
    /* Designer extras row — free-flow captioned fields the user adds in the editor. */
    .rl-label .rr.rextra { flex:0.9 1 0; display:flex; flex-wrap:wrap; gap:0 3mm; align-content:flex-start;
                           padding:0.6mm 1mm; border-bottom:0.4mm solid #000; }
    .rl-label .rextra .rx { font-size:2.7mm; font-weight:600; white-space:nowrap; line-height:1.2; }
    .rl-label .qr { position:absolute; right:1mm; bottom:1mm; line-height:0; }
    .rl-label .qr svg { display:block; }
    @media print {
        body { background:#fff; } .toolbar { display:none; }
        .stack { padding:0; gap:0; display:block; }
        /* Same shape as the die-cut sheets below: break BEFORE each label but the
           first, so nothing added after the last one can leave a blank at the end. */
        .rl-label { box-shadow:none; }
        .rl-label + .rl-label { page-break-before:always; break-before:page; }
        @page { size:<?= $mm($LW) ?>mm <?= $mm($LH) ?>mm; margin:0; }
    }
</style></head>
<body>
<div class="toolbar">
    <b>Roll label</b>
    <span class="note">Order <?= $ono ?> · <?= count($rollBlinds) ?> roller label<?= count($rollBlinds) === 1 ? '' : 's' ?>. Print at <b>100% / Actual size</b> on <b><?= $mm($LW) ?>×<?= $mm($LH) ?>mm</b> labels, margins <b>None</b>. <b>Nudge</b> saved for this computer. Font &amp; line spacing are set per label in the Worksheets editor.</span>
    <a href="?order=<?= (int) $qid ?>">&larr; content view</a>
    <a href="/factory/incoming-orders.php">&larr; Factory orders</a>
    <span class="nudge"><span class="lbl">Nudge&nbsp;mm</span>
        <button type="button" data-nx="-0.5">&#9664;</button><input id="ox" type="number" step="0.5" value="0"><button type="button" data-nx="0.5">&#9654;</button>
        <button type="button" data-ny="-0.5">&#9650;</button><input id="oy" type="number" step="0.5" value="0"><button type="button" data-ny="0.5">&#9660;</button>
        <button type="button" id="nudge-reset">&#8635;</button></span>
    <button onclick="window.print()">Print</button>
</div>
<div class="stack">
<?php // Roller blinds only — verticals go on the die-cut sheet, a different printer. ?>
<?php foreach ($rollBlinds as $r): ?>
    <div class="rl-label<?= $ol ?>"><?= $rollerLabelHtml($labelCtx($r, 0), $r['computed'], (is_array($r['template'] ?? null) ? ($r['template']['labels'][0]['fields'] ?? []) : []), (is_array($r['template'] ?? null) ? ($r['template']['labels'][0]['box'] ?? null) : null)) ?></div>
<?php endforeach; ?>
</div>
<script>
(function () {
    var root = document.documentElement;
    var iox = document.getElementById('ox'), ioy = document.getElementById('oy');
    var ox = parseFloat(localStorage.getItem('lblRollX') || '0') || 0;
    var oy = parseFloat(localStorage.getItem('lblRollY') || '0') || 0;
    function apply() { root.style.setProperty('--nx', ox + 'mm'); root.style.setProperty('--ny', oy + 'mm'); iox.value = ox; ioy.value = oy; localStorage.setItem('lblRollX', ox); localStorage.setItem('lblRollY', oy); }
    iox.addEventListener('input', function () { ox = parseFloat(iox.value) || 0; apply(); });
    ioy.addEventListener('input', function () { oy = parseFloat(ioy.value) || 0; apply(); });
    document.querySelectorAll('[data-nx]').forEach(function (b) { b.addEventListener('click', function () { ox = Math.round((ox + parseFloat(b.dataset.nx)) * 10) / 10; apply(); }); });
    document.querySelectorAll('[data-ny]').forEach(function (b) { b.addEventListener('click', function () { oy = Math.round((oy + parseFloat(b.dataset.ny)) * 10) / 10; apply(); }); });
    document.getElementById('nudge-reset').addEventListener('click', function () { ox = 0; oy = 0; apply(); });
    apply();
    // Font size + line spacing now live per-label in the Worksheets editor, so the
    // print toolbar is nudge-only.

    // Reserve the QR's width for right-aligned fields ONLY where they sit level with
    // the QR, so a top-right field reaches the full edge while a bottom-right one
    // stops at the QR instead of printing under it.
    function reserveQr(flds) {
        var qr = flds.querySelector('.qr'); if (!qr) return;
        var rights = flds.querySelectorAll('.ln .r');
        rights.forEach(function (f) { f.style.marginRight = ''; });
        var qb = qr.getBoundingClientRect();
        var reserve = (qb.width * 1.08) + 'px';
        rights.forEach(function (f) {
            if (f.getBoundingClientRect().bottom > qb.top + 0.5) f.style.marginRight = reserve;
        });
    }
    document.querySelectorAll('.rl-label .flds, .dc-label .flds').forEach(reserveQr);
})();
</script>
</body></html>
<?php
    exit;
}

// ---- Die-cut print layout (?diecut=1) --------------------------------------
// Same A4 geometry as the Label Sheet page, but each box filled with the real
// content, so a 100% print drops straight onto the die-cut label stock. Header
// in both large labels; per line: cutting label (left col) + fabric (right).
// Reuses the per-computer nudge (lblNudgeX/Y). Overridable dims via query.
if ($order && ($_GET['diecut'] ?? '0') !== '0') {
    $ff = static fn (string $k, float $d): float => isset($_GET[$k]) && is_numeric($_GET[$k]) ? (float) $_GET[$k] : $d;
    $topPad = $ff('top', 15); $leftPad = $ff('left', 10); $labelW = $ff('w', 90); $largeH = $ff('large', 50);
    $gap = $ff('gap', 7); $smallH = $ff('small', 21); $centreGap = $ff('centre', 10); $fs = $ff('fs', 8);
    $linesOn = (($_GET['lines'] ?? '1') !== '0');   // draw thin label outlines (for the plain-paper test)
    $cal     = (($_GET['cal'] ?? '1') !== '0');      // crop marks + 100mm ruler
    $cols = [$leftPad, $leftPad + $labelW + $centreGap];
    $firstSmallTop = $topPad + $largeH + $gap;
    // Die-cut sheet holds the die-cut blinds only (verticals); rollers print on
    // the thermal roll. One row per blind, up to 10 to a sheet.
    $dieCount = count($diecutBlinds);
    $rowCap = min($dieCount, 10);
    $mm = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    $ol = $linesOn ? ' dc-outline' : '';

    // 12mm on the die-cut: 20% above the 10mm John proved on the real stock with
    // a hard thumb rub, and the label is only 21mm tall. Override with ?qr=
    // QR from the die-cut template's own setting (not the first blind of a mixed
    // order); falls back to any template qr, then 12mm; URL ?qr= wins.
    $dieTpl = $diecutBlinds[0]['template'] ?? [];
    $dieQr  = (is_array($dieTpl) && isset($dieTpl['qr']) && is_numeric($dieTpl['qr']) && (float) $dieTpl['qr'] > 0)
        ? (float) $dieTpl['qr']
        : (is_numeric($layoutQr) ? (float) $layoutQr : 12);
    $qrMm = $ff('qr', $dieQr);

    $renderFields = static function (array $fields, array $ctx, array $computed) use ($renderLineFields, $fieldHtml, $qrMm): string {
        return $renderLineFields($fields, $ctx, $computed, $fieldHtml, $qrMm);
    };
    header('Content-Type: text/html; charset=utf-8');
    $ono = e((string) ($order['quote_number'] ?? ('#' . $qid)));
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Worksheet die-cut · <?= $ono ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:#666; font-family:system-ui,sans-serif; }
    .toolbar { position:fixed; top:0; left:0; right:0; background:#1f2a37; color:#e5edf5; padding:10px 16px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; z-index:10; font-size:14px; }
    .toolbar b { color:#fff; } .toolbar .note { color:#b9c6d3; flex:1; min-width:200px; }
    .toolbar a { color:#7dd3fc; text-decoration:none; } .toolbar a:hover { text-decoration:underline; }
    .toolbar > button { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:7px 16px; background:#38bdf8; color:#06263a; }
    .nudge { display:flex; align-items:center; gap:4px; color:#b9c6d3; white-space:nowrap; }
    .nudge input { width:3.4rem; font:inherit; border:none; border-radius:6px; padding:5px 6px; text-align:right; }
    .nudge button { font:inherit; cursor:pointer; border:none; border-radius:6px; padding:5px 9px; background:#2c3a4a; color:#e5edf5; }
    .nudge button:hover { background:#3a4d61; }
    .sheet { position:relative; width:210mm; height:297mm; background:#fff; margin:60px auto 40px; box-shadow:0 4px 24px rgba(0,0,0,0.4); overflow:hidden; }
    #sheet-inner { position:absolute; inset:0; }
    .dc-label { position:absolute; overflow:hidden; padding:0.8mm 1.2mm; font-family:Arial,"Helvetica Neue",Helvetica,sans-serif; font-variant-numeric:tabular-nums; color:#000; }
    .dc-label .flds { position:relative; height:100%; line-height:1.05; }
    /* QR pinned ABSOLUTELY to the bottom-right corner — fixed, so the content flow
       can never push it off the label. line-height:0 keeps the span padding out of
       the scanner quiet zone. */
    .dc-label .flds .qr { position:absolute; right:0; bottom:0; line-height:0; }
    .dc-label .flds .qr svg { display:block; }
    :root { --fs-s:<?= $mm($fs) ?>pt; --fs-l:<?= $mm($fs + 1.5) ?>pt; }
    .dc-label.dc-small .flds { font-size:var(--fs-s); }
    .dc-label.dc-large .flds { font-size:var(--fs-l); }
    /* The header (dc-large) has room, so let a long field (e.g. a full address) wrap
       down into it instead of running off the width. Small labels keep no-wrap. */
    .dc-label.dc-large .flds .ln span { white-space:normal; overflow-wrap:anywhere; min-width:0; }
    /* Each line is a flex row → true Left / Centre / Right via auto margins. A script
       reserves the QR width for right fields level with the QR, so they stop at its
       edge while top-right fields reach the full edge. */
    .dc-label .flds .ln { display:flex; flex-wrap:wrap; align-content:flex-start; gap:0 1.8mm; }
    .dc-label .flds .ln span { white-space:nowrap; }
    .dc-label .flds .ln .r { margin-left:auto; }
    /* Same three slots as the roll label — see the .rl-label rules above. */
    .dc-label .flds .ln.has-c { display:grid; grid-template-columns:1fr auto 1fr; align-items:baseline; }
    .dc-label .flds .ln.has-c > .g { display:flex; flex-wrap:wrap; gap:0 1.8mm; min-width:0; }
    .dc-label .flds .ln.has-c > .gl { justify-content:flex-start; }
    .dc-label .flds .ln.has-c > .gc { justify-content:center; }
    .dc-label .flds .ln.has-c > .gr { justify-content:flex-end; }
    .dc-label .flds .ln.has-c .r,
    .dc-label .flds .ln.has-c .c { margin-left:0; margin-right:0; }
    .dc-outline { border:0.2mm solid #c9c9c9; }
    .mk-h { position:absolute; border-top:0.3mm solid #111; } .mk-v { position:absolute; border-left:0.3mm solid #111; }
    .cal-txt { position:absolute; font-size:6pt; color:#333; white-space:nowrap; }
    .sheet-page { position:absolute; right:4mm; bottom:2mm; font:600 10px system-ui,sans-serif; color:#94a3b8; }
    @media print {
        body { background:#fff; } .toolbar { display:none; } .sheet-page { display:none; }
        .sheet { margin:0; box-shadow:none; }
        /* Every sheet after the first starts a new page. This used to be
           "break AFTER every sheet, except the last one" — but the sheets are
           direct children of <body> and a <script> sits after them, so
           .sheet:last-child matched nothing, the final sheet kept its break, and
           the printer spat out a blank page on every single run. Breaking
           BEFORE each sheet but the first can't have that fault: it doesn't care
           what comes after the last one. */
        .sheet + .sheet { page-break-before:always; break-before:page; }
        @page { size:A4 portrait; margin:0; }
    }
</style></head>
<body>
<div class="toolbar">
    <b>Die-cut label print</b>
    <span class="note">Order <?= $ono ?> · <?= (int) $dieCount ?> die-cut blind<?= $dieCount === 1 ? '' : 's' ?>. Print at <b>100% / Actual size</b>, margins <b>None</b>. Lay the plain print over the label stock to check it lands right.</span>
    <a href="?order=<?= (int) $qid ?>">&larr; content view</a>
    <a href="/factory/incoming-orders.php">&larr; Factory orders</a>
    <span class="nudge"><span>Nudge&nbsp;mm</span>
        <button type="button" data-nx="-0.5" title="left">&#9664;</button><input id="ox" type="number" step="0.5" value="0"><button type="button" data-nx="0.5" title="right">&#9654;</button>
        <button type="button" data-ny="-0.5" title="up">&#9650;</button><input id="oy" type="number" step="0.5" value="0"><button type="button" data-ny="0.5" title="down">&#9660;</button>
        <button type="button" id="nudge-reset" title="reset">&#8635;</button>
    </span>
    <button onclick="window.print()">Print</button>
</div>
<?php
$headerFldsStyle = $labelTypeStyle($headerBlock, $fs + 1.5, 1.05);
// One A4 sheet holds the header + up to $rowCap rows; split the blinds across as
// many sheets as needed (48 blinds / 10 = 5 sheets), each a full page.
$sheets    = array_chunk($diecutBlinds, $rowCap);
$sheetTotal = count($sheets);
foreach ($sheets as $si => $sheetBlinds): ?>
<div class="sheet"><div class="sheet-inner">
<?php foreach ($cols as $ci => $x): ?>
    <div class="dc-label dc-large<?= $ol ?>" style="left:<?= $mm($x) ?>mm; top:<?= $mm($topPad) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($largeH) ?>mm;">
        <div class="flds" style="<?= e($headerFldsStyle) ?>"><?= $renderFields($headerFields, $orderVals, []) ?></div>
    </div>
    <?php foreach ($sheetBlinds as $k => $r): $lab = ($r['template']['labels'] ?? [])[$ci] ?? null; $t = $firstSmallTop + $k * $smallH; ?>
        <div class="dc-label dc-small<?= $ol ?>" style="left:<?= $mm($x) ?>mm; top:<?= $mm($t) ?>mm; width:<?= $mm($labelW) ?>mm; height:<?= $mm($smallH) ?>mm;">
            <div class="flds" style="<?= e($labelTypeStyle(is_array($lab) ? $lab : [], $fs, 1.05)) ?>"><?= $lab ? $renderFields($lab['fields'] ?? [], $labelCtx($r, $ci), $r['computed']) : '' ?></div>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php if ($cal): ?>
    <?php foreach ([[5, 5], [205, 5], [5, 285], [205, 285]] as [$cx, $cy]): ?>
        <div class="mk-h" style="left:<?= $cx - 4 ?>mm; top:<?= $cy ?>mm; width:8mm;"></div>
        <div class="mk-v" style="left:<?= $cx ?>mm; top:<?= $cy - 4 ?>mm; height:8mm;"></div>
    <?php endforeach; ?>
    <div class="mk-h" style="left:50mm; top:10mm; width:100mm;"></div>
    <div class="mk-v" style="left:50mm; top:8mm; height:4mm;"></div>
    <div class="mk-v" style="left:150mm; top:8mm; height:4mm;"></div>
    <div class="cal-txt" style="left:152mm; top:8.4mm;">= 100 mm</div>
<?php endif; ?>
    <?php if ($sheetTotal > 1): ?><div class="sheet-page">Sheet <?= $si + 1 ?> of <?= $sheetTotal ?></div><?php endif; ?>
</div></div>
<?php endforeach; ?>
<script>
(function () {
    var inners = document.querySelectorAll('.sheet-inner');   // one per A4 sheet — nudge them together
    var iox = document.getElementById('ox'), ioy = document.getElementById('oy');
    var ox = parseFloat(localStorage.getItem('lblNudgeX') || '0') || 0;
    var oy = parseFloat(localStorage.getItem('lblNudgeY') || '0') || 0;
    function apply() { inners.forEach(function (inner) { inner.style.transform = 'translate(' + ox + 'mm,' + oy + 'mm)'; }); iox.value = ox; ioy.value = oy; localStorage.setItem('lblNudgeX', ox); localStorage.setItem('lblNudgeY', oy); }
    iox.addEventListener('input', function () { ox = parseFloat(iox.value) || 0; apply(); });
    ioy.addEventListener('input', function () { oy = parseFloat(ioy.value) || 0; apply(); });
    document.querySelectorAll('[data-nx]').forEach(function (b) { b.addEventListener('click', function () { ox = Math.round((ox + parseFloat(b.dataset.nx)) * 10) / 10; apply(); }); });
    document.querySelectorAll('[data-ny]').forEach(function (b) { b.addEventListener('click', function () { oy = Math.round((oy + parseFloat(b.dataset.ny)) * 10) / 10; apply(); }); });
    document.getElementById('nudge-reset').addEventListener('click', function () { ox = 0; oy = 0; apply(); });
    apply();
    // Font size + line spacing now live per-label in the Worksheets editor, so the
    // print toolbar is nudge-only.

    // Reserve the QR's width for right-aligned fields ONLY where they sit level with
    // the QR, so a top-right field reaches the full edge while a bottom-right one
    // stops at the QR instead of printing under it.
    function reserveQr(flds) {
        var qr = flds.querySelector('.qr'); if (!qr) return;
        var rights = flds.querySelectorAll('.ln .r');
        rights.forEach(function (f) { f.style.marginRight = ''; });
        var qb = qr.getBoundingClientRect();
        var reserve = (qb.width * 1.08) + 'px';
        rights.forEach(function (f) {
            if (f.getBoundingClientRect().bottom > qb.top + 0.5) f.style.marginRight = reserve;
        });
    }
    document.querySelectorAll('.rl-label .flds, .dc-label .flds').forEach(reserveQr);
})();
</script>
</body></html>
<?php
    exit;
}

$factoryTitle = 'Worksheet';
$factoryNav   = '';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .wp-bar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin:0 0 1.1rem; }
    .wp-bar h1 { font-size:1.5rem; font-weight:700; margin:0; }
    .wp-bar .btn { font:inherit; font-weight:600; cursor:pointer; border:none; border-radius:8px; padding:0.5rem 1.1rem; background:#1f2a37; color:#fff; }
    .wp-bar .btn:hover { background:#111a24; }
    .wp-sheet { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1.25rem; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
    /* Bottom-right corner, same as the real prints — see the label CSS below.
       Reserve the corner so text can't run under it. */
    .wp-sheet .qr { line-height:0; display:inline-block; position:absolute; right:0.6rem; bottom:0.6rem; }
    .wp-sheet .qr svg { display:block; }
    .wp-label:has(.qr) .fields { padding-right:15mm; }
    .wp-header { border-bottom:2px solid #111; padding-bottom:0.6rem; margin-bottom:0.9rem; display:flex; flex-wrap:wrap; gap:0.2rem 1.4rem; font-family:Arial,"Helvetica Neue",Helvetica,sans-serif; font-variant-numeric:tabular-nums; font-size:0.9rem; }
    .wp-line { display:grid; grid-template-columns:1fr 1fr; gap:1rem; padding:0.7rem 0; border-bottom:1px dashed #d1d5db; }
    .wp-label { border:1px solid #cbd5e1; border-radius:8px; padding:0.5rem 0.7rem; position:relative; }
    .wp-label .lt { font-size:0.66rem; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; margin-bottom:0.3rem; font-weight:600; }
    .wp-label .fields { font-family:Arial,"Helvetica Neue",Helvetica,sans-serif; font-variant-numeric:tabular-nums; font-size:0.82rem; line-height:1.6; display:flex; flex-wrap:wrap; gap:0.1rem 0.7rem; }
    .wp-break { flex:0 0 100%; height:0; }
    .wp-right { margin-left:auto; }
    .wp-centre { margin-left:auto; margin-right:auto; }
    .wp-flag { color:#b91c1c; }
    .wp-note { color:#94a3b8; font-size:0.85rem; }
    @media print {
        .factory-topbar, .wp-bar { display:none !important; }
        .factory-main { padding:0 !important; max-width:none !important; }
        .wp-sheet { border:none; box-shadow:none; border-radius:0; padding:0; }
    }
</style>

<div class="wp-bar">
    <a class="btn" href="/factory/incoming-orders.php" style="text-decoration:none">&larr; Factory orders</a>
    <h1>Worksheet</h1>
    <?php if ($order): ?>
        <span class="wp-note">Order <?= e((string) ($order['quote_number'] ?? ('#' . $qid))) ?> · <?= e($customerLine) ?> · <?= (int) $totalLines ?> line<?= $totalLines === 1 ? '' : 's' ?></span>
        <span style="flex:1"></span>
        <?php // A mixed order shows BOTH — each prints only its own products, on its own printer. ?>
        <?php if ($hasDiecut): ?>
        <a class="btn" href="?order=<?= (int) $qid ?>&diecut=1" style="background:#0369a1; text-decoration:none;">Die-cut sheet<?= $hasRoll ? ' (' . count($diecutBlinds) . ')' : '' ?> &#8599;</a>
        <?php endif; ?>
        <?php if ($hasRoll): ?>
        <a class="btn" href="?order=<?= (int) $qid ?>&rolllabel=1" style="background:#0369a1; text-decoration:none;">Roll labels<?= $hasDiecut ? ' (' . count($rollBlinds) . ')' : '' ?> &#8599;</a>
        <?php endif; ?>
        <button class="btn" onclick="window.print()">Print</button>
    <?php endif; ?>
</div>

<?php if (!$order): ?>
    <div class="wp-sheet"><p class="wp-flag">Order not found.</p></div>
<?php elseif (!$lines): ?>
    <div class="wp-sheet"><p class="wp-note">This order has no Beverley lines to work.</p></div>
<?php else: ?>
<div class="wp-sheet">
    <div class="wp-header">
        <?php foreach ($headerFields as $f): ?>
            <?php if (($f['source'] ?? '') === '__break__'): ?><span class="wp-break"></span><?php continue; endif; ?>
            <?php $t = $fieldText($f, $orderVals, []); if ($t !== null): ?><span<?= (($f['align'] ?? '') === 'right') ? ' class="wp-right"' : ((($f['align'] ?? '') === 'centre') ? ' class="wp-centre"' : '') ?>><?= e($t) ?></span><?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$headerFields): ?><span class="wp-note">No worksheet template for this product yet — build one in Worksheets.</span><?php endif; ?>
    </div>

    <?php foreach ($rendered as $r): $tpl = $r['template']; ?>
        <div class="wp-line">
            <?php if ($tpl && !empty($tpl['labels'])): ?>
                <?php foreach ($tpl['labels'] as $li => $lab): $lctx = $labelCtx($r, (int) $li); ?>
                    <div class="wp-label">
                        <div class="lt"><?= e((string) ($lab['title'] ?? 'Label')) ?></div>
                        <div class="fields">
                            <?php foreach (($lab['fields'] ?? []) as $f): ?>
                                <?php if (($f['source'] ?? '') === '__break__'): ?><span class="wp-break"></span><?php continue; endif; ?>
                                <?php $t = $fieldHtml($f, $lctx, $r['computed']); if ($t !== null): ?><span<?= (($f['align'] ?? '') === 'right') ? ' class="wp-right"' : ((($f['align'] ?? '') === 'centre') ? ' class="wp-centre"' : '') ?>><?= $t ?></span><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="wp-label" style="grid-column:1/-1"><span class="wp-note">No worksheet template for <?= e($r['product']) ?>.</span></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
