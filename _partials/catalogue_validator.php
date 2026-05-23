<?php
declare(strict_types=1);

/**
 * Catalogue health-check rules.
 *
 * Walks one product's rows (or all of a tenant's) and returns the
 * list of broken / risky / nice-to-fix states it finds. Used by:
 *
 *   - admin/products/edit.php  — "Catalogue health" chip strip near
 *                                the top of the page, one chip per
 *                                issue, colour-coded by severity.
 *
 *   - admin/products/index.php — could surface a "things to fix"
 *                                pill on each row (not wired up in
 *                                the first cut — count is enough).
 *
 *   - (future) dashboard       — tenant-wide "things to fix" panel.
 *
 * The validator is pure-ish: it makes read-only DB calls, never
 * writes. Errors during a check log + skip — a missing table on an
 * older schema must never break the edit page.
 *
 * SEVERITY contract:
 *   critical → product literally cannot be quoted (no fabric, no
 *              price table, or every price table is empty / orphaned).
 *              These are red, and the products index "Ready to quote"
 *              pill should turn amber the moment any critical issue
 *              is present.
 *   warning  → catalogue half-built — e.g. an option exists with no
 *              choices to pick from. Salesperson will see a broken
 *              dropdown / empty cascade. Amber chip.
 *   hint     → nice-to-have. System with no price table yet; option
 *              marked required but no default choice. Grey chip.
 *
 * Issue shape:
 *   [
 *     'severity' => 'critical' | 'warning' | 'hint',
 *     'code'     => 'no_active_fabric',          // stable id for tests + future i18n
 *     'message'  => 'Add at least one fabric…',  // human-readable
 *     'fix_url'  => '/admin/products/...'        // optional — null if no obvious destination
 *   ]
 */

/**
 * Run every check against one product. Returns the (possibly empty)
 * list of issues, ordered: criticals first, then warnings, then
 * hints. Within each severity, ordered by the order the rules ran.
 */
function catalogue_validate_product(int $productId, int $clientId): array
{
    if ($productId <= 0 || $clientId <= 0) return [];

    $pdo = db();
    $issues = [];

    // ── Helpers ────────────────────────────────────────────────────
    $count = static function (string $sql, array $args) use ($pdo): int {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('catalogue_validator count failed: ' . $e->getMessage());
            return 0;
        }
    };
    $fetchAll = static function (string $sql, array $args) use ($pdo): array {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($args);
            return $st->fetchAll();
        } catch (Throwable $e) {
            error_log('catalogue_validator fetchAll failed: ' . $e->getMessage());
            return [];
        }
    };

    $issue = static function (
        array &$issues,
        string $severity,
        string $code,
        string $message,
        ?string $fixUrl = null
    ): void {
        $issues[] = [
            'severity' => $severity,
            'code'     => $code,
            'message'  => $message,
            'fix_url'  => $fixUrl,
        ];
    };

    $fabricsUrl     = '/admin/products/options.php?product_id=' . $productId;
    $systemsUrl     = '/admin/products/systems.php?product_id=' . $productId;
    $extrasUrl      = '/admin/products/extras.php?product_id=' . $productId;
    $editUrl        = '/admin/products/edit.php?id=' . $productId;

    // ── CRITICAL — product cannot quote ────────────────────────────
    $fabricCount = $count(
        'SELECT COUNT(*) FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1',
        [$productId, $clientId]
    );
    if ($fabricCount === 0) {
        $issue($issues, 'critical', 'no_active_fabric',
            'No fabrics added. Salespeople won\'t be able to pick a fabric for this product.',
            $fabricsUrl);
    }

    $ptCount = $count(
        'SELECT COUNT(*) FROM price_tables
          WHERE product_id = ? AND client_id = ? AND active = 1',
        [$productId, $clientId]
    );
    if ($ptCount === 0) {
        $issue($issues, 'critical', 'no_active_price_table',
            'No price tables. Without one, this product can\'t generate a price for any width × drop.',
            $systemsUrl);
    }

    // Price tables exist but a system reference is now inactive /
    // missing (system was archived after the table was built). The
    // table is orphaned — the quote builder can't surface it. One
    // chip per orphan, deep-linking to that table so the user can
    // delete it or re-link it to an active system.
    if ($ptCount > 0) {
        $orphanTables = $fetchAll(
            'SELECT t.id, t.band_code, t.name, t.system_id
               FROM price_tables t
          LEFT JOIN product_systems s
                 ON s.id = t.system_id AND s.active = 1
              WHERE t.product_id = ? AND t.client_id = ?
                AND t.active = 1
                AND t.system_id IS NOT NULL
                AND s.id IS NULL',
            [$productId, $clientId]
        );
        foreach ($orphanTables as $t) {
            $tLabel = ($t['name'] !== null && $t['name'] !== '')
                ? (string) $t['name']
                : 'Band ' . (string) $t['band_code'];
            $issue($issues, 'critical', 'price_table_orphan_system',
                'Price table "' . $tLabel . '" references a system that\'s been deleted. '
                . 'Salespeople can\'t reach it.',
                '/admin/products/price-table.php?id=' . (int) $t['id']);
        }

        // Active price tables with zero rows = empty grid = no price.
        $emptyTables = $fetchAll(
            'SELECT t.id, t.band_code, t.name
               FROM price_tables t
              WHERE t.product_id = ? AND t.client_id = ?
                AND t.active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM price_table_rows r
                     WHERE r.price_table_id = t.id
                )',
            [$productId, $clientId]
        );
        foreach ($emptyTables as $t) {
            $tLabel = ($t['name'] !== null && $t['name'] !== '')
                ? (string) $t['name']
                : 'Band ' . (string) $t['band_code'];
            $issue($issues, 'critical', 'price_table_no_rows',
                'Price table "' . $tLabel . '" has no width × drop rows yet — quoting will fall through to "no price".',
                '/admin/products/price-table.php?id=' . (int) $t['id']);
        }
    }

    // ── WARNING — catalogue half-built ─────────────────────────────
    //
    // Per-entity emit: one chip per affected option/system/table so
    // the "Fix →" link can deep-link to the specific row the user
    // needs to edit, not a generic list page. Saves a "where do I
    // even start?" round-trip.

    // Active options with zero active choices. Salesperson would see
    // an empty dropdown, which is worse than the option not existing.
    $emptyExtras = $fetchAll(
        'SELECT e.id, e.name
           FROM product_extras e
          WHERE e.product_id = ? AND e.client_id = ? AND e.active = 1
            AND NOT EXISTS (
                SELECT 1 FROM product_extra_choices c
                 WHERE c.product_extra_id = e.id AND c.active = 1
            )',
        [$productId, $clientId]
    );
    foreach ($emptyExtras as $e) {
        $issue($issues, 'warning', 'option_no_choices',
            'Option "' . (string) $e['name'] . '" has no choices — '
            . 'it\'ll render as an empty dropdown.',
            '/admin/products/extra.php?id=' . (int) $e['id']);
    }

    // Choices that point at an inactive/missing system. They'll be
    // hidden from the picker but still sit in the DB. Emit one chip
    // per affected option (deduping at the option level so we don't
    // spam a chip per choice).
    $orphanChoiceExtras = $fetchAll(
        'SELECT DISTINCT e.id, e.name
           FROM product_extra_choices c
           JOIN product_extras e ON e.id = c.product_extra_id
      LEFT JOIN product_systems  s ON s.id = c.system_id AND s.active = 1
          WHERE e.product_id = ? AND e.client_id = ?
            AND c.active = 1
            AND c.system_id IS NOT NULL
            AND s.id IS NULL',
        [$productId, $clientId]
    );
    foreach ($orphanChoiceExtras as $e) {
        $issue($issues, 'warning', 'choice_orphan_system',
            'Option "' . (string) $e['name'] . '" has choices pointing at a deleted system. '
            . 'Those choices are invisible to salespeople.',
            '/admin/products/extra.php?id=' . (int) $e['id']);
    }

    // Extras whose parent_choice_id points at an inactive/missing
    // choice. The extra would never become visible to the salesperson
    // because its trigger condition can never be met. Link to the
    // option's edit page where the "Appears when" cascade is set.
    $orphanCascade = $fetchAll(
        'SELECT e.id, e.name
           FROM product_extras e
      LEFT JOIN product_extra_choices c
             ON c.id = e.parent_choice_id AND c.active = 1
          WHERE e.product_id = ? AND e.client_id = ?
            AND e.active = 1
            AND e.parent_choice_id IS NOT NULL
            AND c.id IS NULL',
        [$productId, $clientId]
    );
    foreach ($orphanCascade as $e) {
        $issue($issues, 'warning', 'extra_orphan_parent',
            'Option "' . (string) $e['name'] . '" depends on a parent choice that no longer exists '
            . '— it\'ll never show up in the quote builder.',
            '/admin/products/extra-edit.php?id=' . (int) $e['id']);
    }

    // ── HINT — nice-to-have ────────────────────────────────────────

    // Systems with no price tables. Salesperson would pick the
    // system, then hit a wall. Only worth flagging if SOME systems
    // have tables — if zero exist anywhere, the critical
    // "no_active_price_table" already covers it.
    if ($ptCount > 0) {
        $sysNoPt = $fetchAll(
            'SELECT s.id, s.name
               FROM product_systems s
              WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM price_tables t
                     WHERE t.system_id = s.id AND t.active = 1
                )',
            [$productId, $clientId]
        );
        foreach ($sysNoPt as $s) {
            $issue($issues, 'hint', 'system_no_price_table',
                'System "' . (string) $s['name'] . '" has no price table yet.',
                '/admin/products/price-tables.php?system_id=' . (int) $s['id']);
        }
    }

    // Required options with no default choice. The salesperson can
    // still pick one, but the form opens with "— pick —" which is
    // an extra click for the most-common case. One chip per option
    // with a deep link to its choices editor so the user can land
    // on the right page and just tick "Default".
    $needsDefault = $fetchAll(
        'SELECT e.id, e.name
           FROM product_extras e
          WHERE e.product_id = ? AND e.client_id = ?
            AND e.active = 1 AND e.is_required = 1
            AND NOT EXISTS (
                SELECT 1 FROM product_extra_choices c
                 WHERE c.product_extra_id = e.id
                   AND c.active = 1 AND c.is_default = 1
            )
            AND EXISTS (
                SELECT 1 FROM product_extra_choices c
                 WHERE c.product_extra_id = e.id AND c.active = 1
            )',
        [$productId, $clientId]
    );
    foreach ($needsDefault as $e) {
        $issue($issues, 'hint', 'required_no_default',
            'Required option "' . (string) $e['name'] . '" has no default choice. '
            . 'Pick a default so the form opens pre-filled.',
            '/admin/products/extra.php?id=' . (int) $e['id']);
    }

    // ── Sort: critical first, then warning, then hint ──────────────
    $rank = ['critical' => 0, 'warning' => 1, 'hint' => 2];
    usort($issues, static function ($a, $b) use ($rank) {
        return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
    });

    return $issues;
}

/**
 * Convenience: just the highest severity present, or null for "all
 * clear". Used by index.php to colour the status pill without
 * fetching the whole issue list.
 */
function catalogue_worst_severity(array $issues): ?string
{
    foreach (['critical', 'warning', 'hint'] as $s) {
        foreach ($issues as $i) {
            if ($i['severity'] === $s) return $s;
        }
    }
    return null;
}

/**
 * Render a floating "Fix next →" pill for the deep-link fix pages.
 *
 * When the user is on extra.php / extra-edit.php / price-table.php
 * having clicked "Fix" on a chip, they often have several more
 * issues to work through. The pill stays visible in the corner of
 * every fix-target page so they can chain through without going
 * back to the product edit page between each one.
 *
 *   $productId       — the product we're checking
 *   $clientId        — tenant scope
 *   $currentFixUrl   — the URL of the page the user is on right now
 *                      (typically the current REQUEST_URI). Used to
 *                      skip the issue belonging to the current
 *                      entity when picking the "next" link target.
 *   $productName     — optional, used in the pill label
 *
 * Returns empty string when there are no remaining issues (the user
 * has cleaned up — pill disappears as a reward signal).
 */
function catalogue_render_fix_next_pill(
    int $productId,
    int $clientId,
    string $currentFixUrl = '',
    string $productName = ''
): string {
    $issues = catalogue_validate_product($productId, $clientId);
    if (!$issues) return '';

    // Normalise the current URL down to path + id param so the
    // pill can detect "this issue points at the page we're on"
    // and skip it when picking the next target.
    $normalise = static function (string $url): string {
        $path = strtok($url, '?');
        $qs   = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($qs, $params);
        $id   = (int) ($params['id'] ?? 0);
        return $path . '|' . $id;
    };
    $currentKey = $currentFixUrl !== '' ? $normalise($currentFixUrl) : '';

    // Pick the next issue whose fix URL is NOT the page we're on.
    $next = null;
    foreach ($issues as $iss) {
        if (empty($iss['fix_url'])) continue;
        if ($currentKey !== '' && $normalise((string) $iss['fix_url']) === $currentKey) continue;
        $next = $iss;
        break;
    }

    // If every remaining issue points at the page we're already on,
    // there's no useful "next" — just hint at the count and link
    // back to the product page where the chip list lives.
    $totalCount = count($issues);

    // Severity-aware colour. Worst issue's colour wins so the pill
    // visibly screams when criticals are outstanding.
    $worst = catalogue_worst_severity($issues) ?? 'hint';
    $palettes = [
        'critical' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'fg' => '#991b1b', 'tag' => '#dc2626'],
        'warning'  => ['bg' => '#fffbeb', 'border' => '#fde68a', 'fg' => '#78350f', 'tag' => '#d97706'],
        'hint'     => ['bg' => '#f3f4f6', 'border' => '#e5e7eb', 'fg' => '#374151', 'tag' => '#6b7280'],
    ];
    $p = $palettes[$worst];

    $productHref = '/admin/products/edit.php?id=' . $productId . '#catalogue-health';

    ob_start();
    ?>
    <!--
        Floating "Fix next" pill. Sits in the bottom-right of the
        viewport (above the page chrome) so it survives long-scroll
        pages without getting lost. Disappears the moment the
        validator returns zero issues — that's the reward signal.
    -->
    <div style="position:fixed;bottom:1rem;right:1rem;z-index:100;
                background:<?= $p['bg'] ?>;border:1px solid <?= $p['border'] ?>;
                color:<?= $p['fg'] ?>;border-radius:10px;
                padding:0.625rem 0.75rem;font-size:0.8125rem;
                box-shadow:0 8px 24px rgba(0,0,0,0.08);
                max-width:24rem;line-height:1.4">
        <div style="display:flex;align-items:center;gap:0.5rem;
                    margin-bottom:0.375rem">
            <span style="background:<?= $p['tag'] ?>;color:#fff;
                          padding:0.0625rem 0.4375rem;border-radius:999px;
                          font-size:0.6875rem;font-weight:700;
                          text-transform:uppercase;letter-spacing:0.04em">
                <?= (int) $totalCount ?> remaining
            </span>
            <strong style="font-size:0.875rem">
                Catalogue health<?= $productName !== '' ? ' &middot; ' . htmlspecialchars($productName, ENT_QUOTES) : '' ?>
            </strong>
        </div>
        <?php if ($next): ?>
            <div style="margin-bottom:0.375rem;color:<?= $p['fg'] ?>">
                <?= htmlspecialchars((string) $next['message'], ENT_QUOTES) ?>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                <a href="<?= htmlspecialchars((string) $next['fix_url'], ENT_QUOTES) ?>"
                   style="background:<?= $p['tag'] ?>;color:#fff;text-decoration:none;
                          padding:0.3125rem 0.75rem;border-radius:6px;
                          font-weight:600;font-size:0.8125rem">
                    Fix next &rarr;
                </a>
                <a href="<?= htmlspecialchars($productHref, ENT_QUOTES) ?>"
                   style="color:<?= $p['fg'] ?>;text-decoration:underline;
                          font-size:0.75rem">
                    View all
                </a>
            </div>
        <?php else: ?>
            <!-- Every remaining issue points at THIS page, so the user
                 is in the right place — they just need to save their
                 current fix and the pill will re-evaluate. -->
            <div style="margin-bottom:0.375rem">
                Save the fix above to refresh and see what's next.
            </div>
            <a href="<?= htmlspecialchars($productHref, ENT_QUOTES) ?>"
               style="color:<?= $p['fg'] ?>;text-decoration:underline;font-size:0.75rem">
                &larr; Back to product
            </a>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Render the chip strip — used on edit.php. Self-contained HTML +
 * inline styles so callers don't have to know the markup. Returns
 * empty string when there are no issues so the caller can wrap with
 * "Catalogue health: All clear" outside.
 */
function catalogue_render_chips(array $issues): string
{
    if (!$issues) return '';

    $colours = [
        'critical' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'fg' => '#991b1b', 'tag' => '#dc2626'],
        'warning'  => ['bg' => '#fffbeb', 'border' => '#fde68a', 'fg' => '#78350f', 'tag' => '#d97706'],
        'hint'     => ['bg' => '#f3f4f6', 'border' => '#e5e7eb', 'fg' => '#374151', 'tag' => '#6b7280'],
    ];
    $labels = [
        'critical' => 'Critical',
        'warning'  => 'Warning',
        'hint'     => 'Hint',
    ];

    ob_start();
    ?>
    <div style="display:flex;flex-direction:column;gap:0.375rem;margin-bottom:1rem">
        <?php foreach ($issues as $iss):
            $sev = $iss['severity'];
            $c   = $colours[$sev] ?? $colours['hint'];
        ?>
            <div style="background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>;
                        color:<?= $c['fg'] ?>;border-radius:8px;padding:0.5rem 0.75rem;
                        font-size:0.875rem;display:flex;align-items:center;gap:0.625rem;
                        flex-wrap:wrap">
                <span style="background:<?= $c['tag'] ?>;color:#fff;padding:0.125rem 0.4375rem;
                             border-radius:999px;font-size:0.6875rem;font-weight:700;
                             text-transform:uppercase;letter-spacing:0.05em;
                             flex-shrink:0">
                    <?= htmlspecialchars($labels[$sev] ?? $sev, ENT_QUOTES) ?>
                </span>
                <span style="flex:1;line-height:1.45">
                    <?= htmlspecialchars((string) $iss['message'], ENT_QUOTES) ?>
                </span>
                <?php if (!empty($iss['fix_url'])): ?>
                    <a href="<?= htmlspecialchars((string) $iss['fix_url'], ENT_QUOTES) ?>"
                       style="color:<?= $c['fg'] ?>;font-weight:600;text-decoration:underline;
                              font-size:0.8125rem;white-space:nowrap;flex-shrink:0">
                        Fix &rarr;
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
