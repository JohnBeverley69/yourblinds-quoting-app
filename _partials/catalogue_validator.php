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
    // table is orphaned — the quote builder can't surface it.
    if ($ptCount > 0) {
        $orphanTables = $fetchAll(
            'SELECT t.id, t.name, t.system_id
               FROM price_tables t
          LEFT JOIN product_systems s
                 ON s.id = t.system_id AND s.active = 1
              WHERE t.product_id = ? AND t.client_id = ?
                AND t.active = 1
                AND t.system_id IS NOT NULL
                AND s.id IS NULL',
            [$productId, $clientId]
        );
        if ($orphanTables) {
            $n = count($orphanTables);
            $issue($issues, 'critical', 'price_table_orphan_system',
                $n . ' price table' . ($n === 1 ? '' : 's') . ' reference a system that\'s been deleted '
                . 'or made inactive. Salespeople can\'t reach ' . ($n === 1 ? 'it' : 'them') . '.',
                $systemsUrl);
        }

        // Active price tables with zero rows = empty grid = no price.
        $emptyTables = $fetchAll(
            'SELECT t.id, t.name
               FROM price_tables t
              WHERE t.product_id = ? AND t.client_id = ?
                AND t.active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM price_table_rows r
                     WHERE r.price_table_id = t.id
                )',
            [$productId, $clientId]
        );
        if ($emptyTables) {
            $n = count($emptyTables);
            $issue($issues, 'critical', 'price_table_no_rows',
                $n . ' price table' . ($n === 1 ? ' has' : 's have') . ' no width × drop rows yet '
                . '— quoting will fall through to "no price".',
                $editUrl);
        }
    }

    // ── WARNING — catalogue half-built ─────────────────────────────

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
    if ($emptyExtras) {
        $names = array_slice(
            array_map(static fn ($r) => (string) $r['name'], $emptyExtras),
            0, 3
        );
        $extra = count($emptyExtras) > 3 ? ' (+' . (count($emptyExtras) - 3) . ' more)' : '';
        $issue($issues, 'warning', 'option_no_choices',
            'Options with no choices: ' . implode(', ', $names) . $extra
            . '. They\'ll render as empty dropdowns.',
            $extrasUrl);
    }

    // Choices that point at an inactive/missing system. They'll be
    // hidden from the picker but still sit in the DB.
    $orphanChoices = $count(
        'SELECT COUNT(*)
           FROM product_extra_choices c
           JOIN product_extras e ON e.id = c.product_extra_id
      LEFT JOIN product_systems  s ON s.id = c.system_id AND s.active = 1
          WHERE e.product_id = ? AND e.client_id = ?
            AND c.active = 1
            AND c.system_id IS NOT NULL
            AND s.id IS NULL',
        [$productId, $clientId]
    );
    if ($orphanChoices > 0) {
        $issue($issues, 'warning', 'choice_orphan_system',
            $orphanChoices . ' option choice' . ($orphanChoices === 1 ? '' : 's')
            . ' reference a deleted system. They\'ll be invisible to salespeople.',
            $extrasUrl);
    }

    // Extras whose parent_choice_id points at an inactive/missing
    // choice. The extra would never become visible to the salesperson
    // because its trigger condition can never be met.
    $orphanCascade = $fetchAll(
        'SELECT e.id, e.name, e.parent_choice_id
           FROM product_extras e
      LEFT JOIN product_extra_choices c
             ON c.id = e.parent_choice_id AND c.active = 1
          WHERE e.product_id = ? AND e.client_id = ?
            AND e.active = 1
            AND e.parent_choice_id IS NOT NULL
            AND c.id IS NULL',
        [$productId, $clientId]
    );
    if ($orphanCascade) {
        $n = count($orphanCascade);
        $issue($issues, 'warning', 'extra_orphan_parent',
            $n . ' option' . ($n === 1 ? '' : 's')
            . ' depend on a parent choice that no longer exists '
            . '— ' . ($n === 1 ? "it'll" : "they'll") . ' never show up in the quote builder.',
            $extrasUrl);
    }

    // ── HINT — nice-to-have ────────────────────────────────────────

    // Systems with no price tables. Salesperson would pick the
    // system, then hit a wall. Not strictly broken if the user
    // intends to add tables later, but worth flagging.
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
    if ($sysNoPt && $ptCount > 0) {
        // Only worth flagging if SOME systems have tables. If ZERO
        // tables exist anywhere, the critical "no_active_price_table"
        // covers it.
        $names = array_slice(
            array_map(static fn ($r) => (string) $r['name'], $sysNoPt),
            0, 3
        );
        $extra = count($sysNoPt) > 3 ? ' (+' . (count($sysNoPt) - 3) . ' more)' : '';
        $issue($issues, 'hint', 'system_no_price_table',
            'Systems with no price table yet: ' . implode(', ', $names) . $extra . '.',
            $systemsUrl);
    }

    // Required options with no default choice. The salesperson can
    // still pick one, but the form opens with "— pick — " which is
    // an extra click for the most-common case.
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
    if ($needsDefault) {
        $names = array_slice(
            array_map(static fn ($r) => (string) $r['name'], $needsDefault),
            0, 3
        );
        $extra = count($needsDefault) > 3 ? ' (+' . (count($needsDefault) - 3) . ' more)' : '';
        $issue($issues, 'hint', 'required_no_default',
            'Required options with no default choice: ' . implode(', ', $names) . $extra
            . '. Pick a default so the form opens pre-filled.',
            $extrasUrl);
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
