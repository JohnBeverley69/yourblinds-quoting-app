<?php
declare(strict_types=1);

/**
 * Migration: scope a choice to specific FABRICS, not just bands.
 *
 * Band scoping (product_extra_choice_bands) already answers "which price bands
 * does this choice apply to". That covers most gating — a tape option that only
 * exists on tape bands, say. But some restrictions are per FABRIC, and the
 * fabrics sharing a band are exactly the ones you need to tell apart.
 *
 * Bev Infusions is the case that forced it. The supplier's sheet offers:
 *   Snow          in 38mm, 50mm and 63mm
 *   Cool White    in 38mm and 50mm
 *   everything else 50mm only
 * and tape only on 50mm slat ("N/A" against Snow 38/63 and Cool White 38).
 *
 * Snow sits on band C alongside Astral, Flint and eight others; Cool White
 * shares band D with Highshine White. So "38mm applies to Snow and Cool White"
 * cannot be said in bands — scoping 38mm to band C would offer it on every C
 * fabric, which the supplier does not make.
 *
 *   product_extra_choice_options (choice_id, option_id)
 *
 * Same shape and same default as the band junction: NO rows for a choice means
 * "applies to every fabric", so every existing choice keeps working untouched.
 * Rows cascade away with either the choice or the fabric.
 *
 * Run via web: /migrate_choice_option_scoping.php (super-admin). Idempotent.
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') { require_once __DIR__ . '/auth/middleware.php'; requireSuperAdmin(); header('Content-Type: text/plain; charset=utf-8'); }
ini_set('display_errors', '1'); error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = false;
try { $pdo->query('SELECT 1 FROM product_extra_choice_options LIMIT 0'); $exists = true; }
catch (Throwable $e) { $exists = false; }

if ($exists) {
    echo "  product_extra_choice_options already exists — skipped.\n";
} else {
    $pdo->exec(
        "CREATE TABLE product_extra_choice_options (
            choice_id INT UNSIGNED NOT NULL,
            option_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (choice_id, option_id),
            KEY idx_option (option_id),
            CONSTRAINT fk_peco_choice FOREIGN KEY (choice_id)
                REFERENCES product_extra_choices (id) ON DELETE CASCADE,
            CONSTRAINT fk_peco_option FOREIGN KEY (option_id)
                REFERENCES product_options (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  Created product_extra_choice_options.\n";
}

echo "\nDone. No rows = the choice applies to every fabric, which is what every\n";
echo "existing choice does today — nothing changes until you scope one.\n";
