<?php
declare(strict_types=1);

/**
 * RETIRED — do not use.
 *
 * This seed used to load a "vertical_headrail" allowance table (headrail-cut
 * deductions keyed by system · control · draw · Recess/Exact). That table was a
 * DUPLICATE of the deductions already baked into the H_Cut build rule
 * (seed_vertical_hcut.php → build_variables), and NO formula ever LOOKUP'd it, so
 * it drove nothing and could silently diverge from the live cut. It was removed by
 * migrate_drop_vertical_headrail.php.
 *
 * It was also mis-spelled ("Vouge", "Cord and Chain") so its keys never matched the
 * rules anyway. Re-seeding it would only recreate the trap, so this script is now a
 * no-op. The headrail cut lives in the H_Cut build rule (edit it under Build Rules);
 * the Vogue best-fit charts live in seed_vogue_bestfit.php.
 */

require_once __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

echo "seed_vertical_allowances.php is RETIRED and does nothing.\n\n";
echo "The 'vertical_headrail' allowance was a dead duplicate of the H_Cut build rule\n";
echo "and was dropped (migrate_drop_vertical_headrail.php). The headrail cut now lives\n";
echo "only in the H_Cut build rule (Build Rules page); the best-fit charts are seeded\n";
echo "by seed_vogue_bestfit.php. Nothing to seed here.\n";
