<?php
declare(strict_types=1);

/**
 * Migration — a build variable carries its own display name and its own
 * "the floor never sees this" flag.
 *
 * Adds to build_variables:
 *   label    VARCHAR(80) NULL   -- what the Build rules page calls it (NULL = its raw name)
 *   plumbing TINYINT(1)  NOT NULL DEFAULT 0   -- a working value, not printed on a ticket
 *
 * Why: factory/build-rules-v2.php held both as PHP arrays keyed by Bev Vertical
 * Blinds' variable names ($FRIENDLY, $PLUMBING). So that one product's rules
 * read "Headrail cut", "Fabric drop", "Fabric metres" and its plumbing was
 * tucked away, while every other product showed raw names like Fabric_Cut and
 * could not hide a working value at all. Nothing in this system is supposed to
 * be hard-coded per product.
 *
 * Seeded from exactly those arrays, by name, so the vertical reads the same
 * afterwards as it did before. Identity entries (Vanes => "Vanes") are skipped:
 * a NULL label means "use the name", which is the same thing with less data.
 *
 * Super-admin only; safe to run more than once.
 */

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 2) . '/auth/middleware.php';

requireLogin();
if (!function_exists('is_super_admin') || !is_super_admin()) {
    http_response_code(403);
    exit('Super-admin only.');
}

require_run_confirmation();

$pdo = db();
$ops = [];

$colExists = static function (string $table, string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
};

// The two tables being retired, copied here verbatim so the seed is auditable
// against what the page used to do.
$FRIENDLY = [
    'H_Cut'         => 'Headrail cut',
    'Hem_To_Hem'    => 'Fabric drop',
    'Mtrs'          => 'Fabric metres',
    'CH_L'          => 'Tilt chain',
    'C_L'           => 'Draw cord',
    'Truck_Size'    => 'Truck size',
    'Truck_Spec'    => 'Truck spec',
    'Spacing'       => 'Truck spacing',
    'Truck_Spacing' => 'Truck spacing',
];
$PLUMBING = ['Spacing', 'Truck_Spacing', 'Trucks', 'Truck_Size', 'Truck_Spec'];

try {
    if (!$colExists('build_variables', 'label')) {
        $pdo->exec('ALTER TABLE build_variables ADD COLUMN label VARCHAR(80) NULL');
        $ops[] = 'Added build_variables.label.';
    } else {
        $ops[] = 'build_variables.label already exists — skipped.';
    }
    if (!$colExists('build_variables', 'plumbing')) {
        $pdo->exec('ALTER TABLE build_variables ADD COLUMN plumbing TINYINT(1) NOT NULL DEFAULT 0');
        $ops[] = 'Added build_variables.plumbing.';
    } else {
        $ops[] = 'build_variables.plumbing already exists — skipped.';
    }

    // Seed only where nothing has been set, so re-running never undoes an edit.
    $lab = $pdo->prepare('UPDATE build_variables SET label = ? WHERE name = ? AND label IS NULL');
    $n = 0;
    foreach ($FRIENDLY as $name => $label) { $lab->execute([$label, $name]); $n += $lab->rowCount(); }
    $ops[] = "Labelled {$n} row(s) from the old \$FRIENDLY table.";

    $in = implode(',', array_fill(0, count($PLUMBING), '?'));
    $pl = $pdo->prepare("UPDATE build_variables SET plumbing = 1 WHERE plumbing = 0 AND name IN ($in)");
    $pl->execute($PLUMBING);
    $ops[] = 'Marked ' . $pl->rowCount() . ' row(s) as internal plumbing.';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration FAILED: " . $e->getMessage() . "\n\nDone so far:\n - " . implode("\n - ", $ops);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Build-variable label/plumbing migration complete.\n\n - " . implode("\n - ", $ops) . "\n";
