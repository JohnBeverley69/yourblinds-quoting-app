<?php
declare(strict_types=1);

/**
 * Migration: parallel streams on a route.
 *
 * A vertical blind is really TWO jobs that happen alongside each other and never
 * meet in the workshop — the headrail (profile cut -> headrail assembly) and the
 * fabric (fabric cut -> sew/weld -> weighting & linking). They ship as parts and
 * the fitter marries them on site.
 *
 * The old model gave a blind ONE position on ONE list, and treated everything to
 * the left of it as finished. So cutting fabric first marked the headrail
 * assembled when nobody had touched it. That's the bug this fixes.
 *
 *   product_route_steps.stream  — steps sharing a stream run in order; different
 *                                 streams run in parallel. NULL/'main' = the
 *                                 single-line case (roller, pleated), unchanged.
 *   factory_blind_streams       — one position PER STREAM per blind. Within a
 *                                 stream a pointer still legitimately implies the
 *                                 earlier stages are done, because that work IS
 *                                 sequential. Across streams, nothing is implied.
 *
 * A blind is made when every one of its streams has run out of stages.
 *
 * Backfills from the existing single pointer, then opens any stream a blind
 * should have had but couldn't. Run via web: /migrate_route_streams.php
 * (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colExists = static function (string $t, string $c) use ($pdo): bool {
    try { $pdo->query("SELECT `$c` FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};
$tableExists = static function (string $t) use ($pdo): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); return true; } catch (Throwable $e) { return false; }
};

foreach (['factory_stations', 'product_route_steps', 'factory_blind_jobs'] as $t) {
    if (!$tableExists($t)) { echo "Missing {$t} — run the earlier factory migrations first.\n"; exit; }
}

// ---- 1. stream on the route steps -----------------------------------------
if (!$colExists('product_route_steps', 'stream')) {
    $pdo->exec("ALTER TABLE product_route_steps ADD COLUMN stream VARCHAR(40) NULL AFTER product_id");
    echo "  Added product_route_steps.stream.\n";
} else {
    echo "  product_route_steps.stream already exists — skipped.\n";
}

// ---- 2. a position per stream ---------------------------------------------
if (!$tableExists('factory_blind_streams')) {
    $pdo->exec(
        "CREATE TABLE factory_blind_streams (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            blind_job_id    INT NOT NULL,
            stream          VARCHAR(40) NOT NULL DEFAULT 'main',
            route_step_id   INT NULL,                   -- current stage in THIS stream; NULL = stream finished
            station_id      INT NULL,                   -- denormalised, for the per-station queues
            seq             INT NOT NULL DEFAULT 0,
            status          VARCHAR(16) NOT NULL DEFAULT 'queued',   -- queued | in_progress | done
            started_at      DATETIME NULL,
            step_started_at DATETIME NULL,
            completed_at    DATETIME NULL,
            updated_by      INT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_job_stream (blind_job_id, stream),
            KEY idx_stream_station (station_id, status),
            KEY idx_stream_job (blind_job_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created factory_blind_streams.\n";
} else {
    echo "  factory_blind_streams already exists — skipped.\n";
}

// ---- 3. Repair positions that no longer fit the route ---------------------
// A route can be re-streamed after blinds are already on the floor — which is
// exactly what happens the first time round: this migration runs (everything is
// one 'main' stream), then seed_factory_routes splits the vertical into Headrail
// + Fabric, and every existing position is suddenly in a stream its route no
// longer has. Same if the seed re-cuts the steps and their ids change.
//
// A position like that can't be mapped onto the new shape, so drop it and let
// the backfill below re-open the stream at its first stage. That's the honest
// outcome: re-streaming a route resets where things are, rather than inventing
// a position. Left alone, a stale row would also block the blind ever counting
// as made, since a blind is only made when ALL its streams are done.
//
// Guarded on the product actually having a route, so a legitimately unrouted
// blind (station NULL) isn't swept away.
$cleared = $pdo->exec(
    "DELETE s FROM factory_blind_streams s
       JOIN factory_blind_jobs j ON j.id = s.blind_job_id
      WHERE EXISTS (SELECT 1 FROM product_route_steps rs
                     WHERE rs.product_id = j.product_id AND rs.active = 1)
        AND (
              NOT EXISTS (SELECT 1 FROM product_route_steps rs
                           WHERE rs.product_id = j.product_id AND rs.active = 1
                             AND COALESCE(NULLIF(rs.stream, ''), 'main') = s.stream)
           OR (s.route_step_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM product_route_steps rs WHERE rs.id = s.route_step_id))
        )"
);
if ($cleared > 0) echo "  Cleared {$cleared} position(s) that no longer match their route.\n";

// ---- 4. Backfill ----------------------------------------------------------
// Carry each blind's existing single pointer into the stream it belongs to,
// then open every other stream its product has at that stream's first stage.
$jobs = $pdo->query(
    "SELECT bj.id, bj.product_id, bj.route_step_id, bj.station_id, bj.seq, bj.status,
            bj.started_at, bj.step_started_at, bj.completed_at
       FROM factory_blind_jobs bj"
)->fetchAll(PDO::FETCH_ASSOC);

$stepsFor = static function (int $productId) use ($pdo): array {
    $st = $pdo->prepare(
        "SELECT id, COALESCE(NULLIF(stream, ''), 'main') AS stream, seq, station_id
           FROM product_route_steps WHERE product_id = ? AND active = 1 ORDER BY seq, id"
    );
    $st->execute([$productId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};

$has = $pdo->prepare('SELECT 1 FROM factory_blind_streams WHERE blind_job_id = ? AND stream = ? LIMIT 1');
$ins = $pdo->prepare(
    "INSERT INTO factory_blind_streams
        (blind_job_id, stream, route_step_id, station_id, seq, status, started_at, step_started_at, completed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$made = 0;
foreach ($jobs as $j) {
    $steps = $stepsFor((int) $j['product_id']);
    if (!$steps) continue;

    // Which stream was the old pointer in?
    $curStream = 'main';
    foreach ($steps as $s) {
        if ((int) $s['id'] === (int) $j['route_step_id']) { $curStream = (string) $s['stream']; break; }
    }

    $byStream = [];
    foreach ($steps as $s) $byStream[(string) $s['stream']][] = $s;

    foreach ($byStream as $stream => $list) {
        $has->execute([(int) $j['id'], $stream]);
        if ($has->fetchColumn()) continue;   // already backfilled

        if ($j['status'] === 'complete') {
            // Whole blind was finished — every stream is done.
            $ins->execute([(int) $j['id'], $stream, null, null, 0, 'done', $j['started_at'], null, $j['completed_at']]);
        } elseif ($stream === $curStream && $j['route_step_id'] !== null) {
            // The stream the blind was actually on keeps its exact position.
            $ins->execute([(int) $j['id'], $stream, (int) $j['route_step_id'], $j['station_id'], (int) $j['seq'],
                           (string) $j['status'], $j['started_at'], $j['step_started_at'], null]);
        } else {
            // A stream that couldn't be tracked before: start it at its first stage.
            // Deliberately NOT marked done — the old model's claim that this work
            // was finished is exactly the lie we're fixing.
            $f = $list[0];
            $ins->execute([(int) $j['id'], $stream, (int) $f['id'], (int) $f['station_id'], (int) $f['seq'],
                           'queued', null, null, null]);
        }
        $made++;
    }
}
echo "\n  Opened {$made} stream position(s) across " . count($jobs) . " blind(s).\n";
echo "\nDone. If you haven't yet, run /seed_factory_routes.php to split the\n";
echo "vertical's route into its Headrail and Fabric streams (roller + pleated\n";
echo "stay as one line) — then run THIS script once more, so blinds already on\n";
echo "the floor pick up the streams the new route gave them.\n";
