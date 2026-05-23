<?php
declare(strict_types=1);

/**
 * Catalogue audit logger.
 *
 * Single entry point — catalogue_audit_log() — called from every
 * mutation handler in admin/products/. Records who changed what,
 * when, with before/after snapshots so an admin can spot and undo
 * an accidental edit before it's been live long enough to matter.
 *
 * Designed as best-effort:
 *   - try/catch around the INSERT so the table being missing (older
 *     schema, fresh install with the migration not yet run) never
 *     breaks the calling handler.
 *   - actor identity pulled from current_user() at call time. If
 *     no user is logged in (CLI invocation, webhook) it logs with
 *     NULL user_id and "system" as the display name.
 *
 * Diff helper catalogue_audit_diff() lets the renderer show
 * "name: 'Roller Standard' → 'Roller Premium'" compactly.
 */

/**
 * Write one audit row.
 *
 *   $entityType — short string: 'product', 'system', 'fabric',
 *                 'extra', 'choice', 'price_table', etc.
 *   $entityId   — the row id within that entity's table. NULL for
 *                 bulk events (e.g. import) where it doesn't map to
 *                 a single row.
 *   $action     — 'create' | 'update' | 'delete' | 'restore' |
 *                 'duplicate' | 'import' | 'reorder' | 'upsert'
 *   $entityLabel — human-readable name for the log feed
 *                  ("Roller Standard"). Persisted so the feed still
 *                  reads cleanly after the row is renamed or deleted.
 *   $before/$after — array snapshots of the relevant fields. Either
 *                    or both may be null. Stored as JSON.
 *   $parentProductId — if known, lets the per-product "Recent changes"
 *                      panel filter without expensive joins. Caller's
 *                      responsibility to supply.
 *   $meta — free-form extras bucket. e.g. ['rows' => 348] on an
 *           import.
 */
function catalogue_audit_log(
    string $entityType,
    ?int $entityId,
    string $action,
    ?string $entityLabel = null,
    ?array $before = null,
    ?array $after = null,
    ?int $parentProductId = null,
    ?array $meta = null
): void {
    try {
        $user = function_exists('current_user') ? current_user() : null;
        $clientId = (int) ($user['client_id'] ?? 0);
        if ($clientId <= 0) {
            // Without a tenant context we can't write a useful row.
            // (CLI scripts that mutate the catalogue can pass the
            // tenant via a future extension if needed.)
            return;
        }
        $userId   = (int) ($user['user_id'] ?? 0) ?: null;
        $userName = (string) ($user['full_name'] ?? $user['display_name'] ?? 'system');

        $beforeJson = $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null;
        $afterJson  = $after  !== null ? json_encode($after,  JSON_UNESCAPED_UNICODE) : null;
        $metaJson   = $meta   !== null ? json_encode($meta,   JSON_UNESCAPED_UNICODE) : null;

        db()->prepare(
            'INSERT INTO catalogue_audit
              (client_id, user_id, user_name, entity_type, entity_id,
               parent_product_id, entity_label, action,
               before_json, after_json, meta_json)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $clientId,
            $userId,
            mb_substr($userName, 0, 150),
            mb_substr($entityType, 0, 40),
            $entityId,
            $parentProductId,
            $entityLabel !== null ? mb_substr($entityLabel, 0, 200) : null,
            mb_substr($action, 0, 20),
            $beforeJson,
            $afterJson,
            $metaJson,
        ]);
    } catch (Throwable $e) {
        // Table missing / DB hiccup — must not break the handler.
        // Note in error log so we know to run the migration.
        error_log('catalogue_audit_log failed: ' . $e->getMessage());
    }
}

/**
 * Compute a simple field-by-field diff for display.
 * Returns ['field' => ['from' => x, 'to' => y], ...] with only
 * the keys whose values actually changed.
 */
function catalogue_audit_diff(?array $before, ?array $after): array
{
    $before = $before ?? [];
    $after  = $after  ?? [];
    $keys   = array_unique(array_merge(array_keys($before), array_keys($after)));
    $diff   = [];
    foreach ($keys as $k) {
        $b = $before[$k] ?? null;
        $a = $after[$k]  ?? null;
        if ($b !== $a) {
            $diff[$k] = ['from' => $b, 'to' => $a];
        }
    }
    return $diff;
}

/**
 * Fetch the most recent N events for one product. Returns rows
 * ready to render — user_name, action, entity_type, entity_label,
 * created_at, before_json + after_json as arrays for diffing.
 *
 * Defensive against the table being missing (older schemas) —
 * returns empty array.
 */
function catalogue_audit_recent_for_product(int $productId, int $clientId, int $limit = 25): array
{
    try {
        $st = db()->prepare(
            'SELECT id, user_id, user_name, entity_type, entity_id,
                    entity_label, action, before_json, after_json, meta_json,
                    created_at
               FROM catalogue_audit
              WHERE client_id = ?
                AND (parent_product_id = ?
                  OR (entity_type = "product" AND entity_id = ?))
              ORDER BY id DESC
              LIMIT ' . (int) $limit
        );
        $st->execute([$clientId, $productId, $productId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['before'] = $r['before_json'] ? json_decode((string) $r['before_json'], true) : null;
            $r['after']  = $r['after_json']  ? json_decode((string) $r['after_json'],  true) : null;
            $r['meta']   = $r['meta_json']   ? json_decode((string) $r['meta_json'],   true) : null;
        }
        unset($r);
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Render the "Recent changes" panel. Self-contained markup so the
 * caller just needs to drop the return value into the page.
 *
 * Each row collapses to a single line by default ("3h ago · John
 * Beverley updated price table 'Roller Band A'") and expands on
 * click to show the field-by-field diff.
 */
function catalogue_audit_render_feed(array $rows): string
{
    if (!$rows) {
        return '<p style="margin:0;color:#9ca3af;font-style:italic;font-size:0.875rem">'
             . 'No changes recorded yet. Edits to this product will appear here.'
             . '</p>';
    }

    $actionLabels = [
        'create'    => ['lbl' => 'created',    'colour' => '#065f46', 'bg' => '#d1fae5'],
        'update'    => ['lbl' => 'updated',    'colour' => '#1e40af', 'bg' => '#dbeafe'],
        'upsert'    => ['lbl' => 'saved',      'colour' => '#1e40af', 'bg' => '#dbeafe'],
        'delete'    => ['lbl' => 'deleted',    'colour' => '#991b1b', 'bg' => '#fee2e2'],
        'restore'   => ['lbl' => 'restored',   'colour' => '#065f46', 'bg' => '#d1fae5'],
        'duplicate' => ['lbl' => 'duplicated', 'colour' => '#5b21b6', 'bg' => '#ede9fe'],
        'import'    => ['lbl' => 'imported',   'colour' => '#78350f', 'bg' => '#fef3c7'],
        'reorder'   => ['lbl' => 'reordered',  'colour' => '#374151', 'bg' => '#e5e7eb'],
    ];

    $ageOf = static function (string $ts): string {
        $diff = time() - strtotime($ts);
        if ($diff < 0)        return 'just now';
        if ($diff < 60)       return $diff . 's ago';
        if ($diff < 3600)     return floor($diff / 60) . 'm ago';
        if ($diff < 86400)    return floor($diff / 3600) . 'h ago';
        if ($diff < 86400*7)  return floor($diff / 86400) . 'd ago';
        return date('j M Y', strtotime($ts));
    };

    ob_start();
    ?>
    <div style="border:1px solid #e5e7eb;border-radius:8px;background:#fff">
        <?php foreach ($rows as $r):
            $action = (string) $r['action'];
            $ad     = $actionLabels[$action] ?? ['lbl' => $action, 'colour' => '#374151', 'bg' => '#e5e7eb'];
            $diff   = catalogue_audit_diff($r['before'] ?? null, $r['after'] ?? null);
            $hasDetail = !empty($diff) || !empty($r['meta']);
        ?>
            <details style="border-bottom:1px solid #f3f4f6">
                <summary style="cursor:<?= $hasDetail ? 'pointer' : 'default' ?>;
                                list-style:none;padding:0.5rem 0.75rem;
                                display:flex;align-items:center;gap:0.5rem;
                                font-size:0.875rem;flex-wrap:wrap">
                    <span style="color:#6b7280;font-size:0.75rem;white-space:nowrap;
                                  width:5rem;flex-shrink:0"
                          title="<?= htmlspecialchars((string) $r['created_at'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars($ageOf((string) $r['created_at']), ENT_QUOTES) ?>
                    </span>
                    <strong style="color:#111827;white-space:nowrap">
                        <?= htmlspecialchars((string) ($r['user_name'] ?? 'system'), ENT_QUOTES) ?>
                    </strong>
                    <span style="background:<?= $ad['bg'] ?>;color:<?= $ad['colour'] ?>;
                                  padding:0.0625rem 0.4375rem;border-radius:999px;
                                  font-size:0.6875rem;font-weight:700;
                                  text-transform:uppercase;letter-spacing:0.04em">
                        <?= htmlspecialchars($ad['lbl'], ENT_QUOTES) ?>
                    </span>
                    <span style="color:#374151">
                        <?= htmlspecialchars((string) $r['entity_type'], ENT_QUOTES) ?>
                        <?php if (!empty($r['entity_label'])): ?>
                            <em style="color:#6b7280">"<?= htmlspecialchars((string) $r['entity_label'], ENT_QUOTES) ?>"</em>
                        <?php endif; ?>
                    </span>
                </summary>
                <?php if ($hasDetail): ?>
                    <div style="padding:0.5rem 0.75rem 0.75rem;background:#fafafa;
                                font-size:0.8125rem">
                        <?php if (!empty($r['meta'])): ?>
                            <div style="margin-bottom:0.5rem;color:#6b7280">
                                <?php foreach ($r['meta'] as $k => $v): ?>
                                    <span style="margin-right:0.875rem">
                                        <strong><?= htmlspecialchars((string) $k, ENT_QUOTES) ?>:</strong>
                                        <?= htmlspecialchars(is_scalar($v) ? (string) $v : json_encode($v), ENT_QUOTES) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($diff)): ?>
                            <table style="width:100%;border-collapse:collapse;font-size:0.8125rem">
                                <thead>
                                    <tr style="color:#6b7280;font-size:0.6875rem;
                                                text-transform:uppercase;letter-spacing:0.04em">
                                        <th style="text-align:left;padding:0.25rem 0.375rem">Field</th>
                                        <th style="text-align:left;padding:0.25rem 0.375rem">From</th>
                                        <th style="text-align:left;padding:0.25rem 0.375rem">To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($diff as $field => $d):
                                        $from = $d['from']; $to = $d['to'];
                                        if (is_array($from)) $from = json_encode($from);
                                        if (is_array($to))   $to   = json_encode($to);
                                        if ($from === null) $from = '∅';
                                        if ($to   === null) $to   = '∅';
                                    ?>
                                        <tr>
                                            <td style="padding:0.25rem 0.375rem;color:#374151;font-weight:600">
                                                <?= htmlspecialchars((string) $field, ENT_QUOTES) ?>
                                            </td>
                                            <td style="padding:0.25rem 0.375rem;color:#991b1b">
                                                <?= htmlspecialchars((string) $from, ENT_QUOTES) ?>
                                            </td>
                                            <td style="padding:0.25rem 0.375rem;color:#065f46">
                                                <?= htmlspecialchars((string) $to, ENT_QUOTES) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
