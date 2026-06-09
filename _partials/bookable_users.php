<?php
declare(strict_types=1);

/**
 * Users offered in an appointment's "Assign to" dropdown, scoped to the role
 * that fits the appointment KIND: a MEASURE / sales visit offers users with
 * the 'sales' role; a FITTING offers 'fitter'. Uses the multi-role junction
 * (client_user_roles) when present, falling back to the primary-role column.
 *
 * Safety nets:
 *   - if nobody holds the target role, return all active users (so assignment
 *     is never blocked);
 *   - always include $includeUserId (the currently-assigned person) even if
 *     they no longer hold the role, so editing can't silently drop them.
 */
if (!function_exists('bookable_users_for_kind')) {
    function bookable_users_for_kind(int $clientId, string $kind, ?int $includeUserId = null): array
    {
        $role = $kind === 'fitting' ? 'fitter' : 'sales';

        try {
            $st = db()->prepare(
                "SELECT DISTINCT u.id, u.full_name, u.role
                   FROM client_users u
                   JOIN client_user_roles r ON r.user_id = u.id
                  WHERE u.client_id = ? AND u.active = 1 AND r.role = ?
               ORDER BY u.full_name"
            );
            $st->execute([$clientId, $role]);
            $users = $st->fetchAll();
        } catch (Throwable $e) {
            // Junction table not present — fall back to the primary-role column.
            $st = db()->prepare(
                "SELECT id, full_name, role FROM client_users
                  WHERE client_id = ? AND active = 1 AND role = ?
               ORDER BY full_name"
            );
            $st->execute([$clientId, $role]);
            $users = $st->fetchAll();
        }

        // Nobody holds the role → show everyone so assignment isn't blocked.
        if (!$users) {
            $st = db()->prepare(
                'SELECT id, full_name, role FROM client_users
                  WHERE client_id = ? AND active = 1 ORDER BY full_name'
            );
            $st->execute([$clientId]);
            $users = $st->fetchAll();
        }

        // Always keep the currently-assigned user in the list.
        if ($includeUserId !== null && $includeUserId > 0) {
            $present = false;
            foreach ($users as $u) {
                if ((int) $u['id'] === $includeUserId) { $present = true; break; }
            }
            if (!$present) {
                $st = db()->prepare(
                    'SELECT id, full_name, role FROM client_users
                      WHERE id = ? AND client_id = ? LIMIT 1'
                );
                $st->execute([$includeUserId, $clientId]);
                if ($extra = $st->fetch()) {
                    $users[] = $extra;
                    usort($users, static fn ($a, $b) =>
                        strcasecmp((string) $a['full_name'], (string) $b['full_name']));
                }
            }
        }

        return $users;
    }
}
