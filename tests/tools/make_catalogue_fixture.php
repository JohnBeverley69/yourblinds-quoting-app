<?php
declare(strict_types=1);

/**
 * DEVELOPER TOOL — not run by the test suite.
 *
 * Rebuilds tests/fixtures/10_factory_catalogue.sql from a LOCAL PII-free test
 * copy of the database (XAMPP MariaDB `yb_test`, made from Master Admin →
 * Backup & Restore → "Download test copy"). It takes the factory's (client 3)
 * ACTIVE products and everything they price and build from, keeps original ids,
 * trims each product's fabric list to a few per band, and scrubs contact
 * details. Supplier price lists are not confidential (John, 2026-10-12).
 *
 *   php tests/tools/make_catalogue_fixture.php [source_db]
 *
 * NEVER point this at the live database. After regenerating, re-run the suite:
 * the price goldens are tied to this catalogue, so expect to re-capture them
 * (php tests/run.php --update-goldens) and review the diff.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$srcDb = $argv[1] ?? 'yb_test';
$pdo = new PDO("mysql:host=127.0.0.1;dbname=$srcDb;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

const FACTORY = 3;
const FABRICS_PER_BAND = 3;   // per product + system + band

$P  = "SELECT id FROM products WHERE client_id = " . FACTORY . " AND active = 1";
$E  = "SELECT id FROM product_extras WHERE product_id IN ($P)";
$C  = "SELECT id FROM product_extra_choices WHERE product_extra_id IN ($E)";
$T  = "SELECT id FROM price_tables WHERE product_id IN ($P)";

// Fabrics: the first few per product/system/band, plus any a choice is scoped to.
$keepOptions = $pdo->query(
    "SELECT id FROM (
        SELECT id, ROW_NUMBER() OVER (PARTITION BY product_id, COALESCE(system_id,0), band_code ORDER BY active DESC, sort_order, id) rn
          FROM product_options WHERE product_id IN ($P)
     ) x WHERE rn <= " . FABRICS_PER_BAND . "
     UNION SELECT option_id FROM product_extra_choice_options WHERE choice_id IN ($C)
     UNION SELECT id FROM product_options WHERE product_id IN ($P) AND is_default = 1"
)->fetchAll(PDO::FETCH_COLUMN);
$O = $keepOptions ? implode(',', array_map('intval', $keepOptions)) : '0';

$tables = [
    // table => WHERE
    'clients'                      => 'id = ' . FACTORY,
    'client_settings'              => 'client_id = ' . FACTORY,
    'product_categories'           => "id IN (SELECT category_id FROM products WHERE id IN ($P))",
    'products'                     => "id IN ($P)",
    'product_systems'              => "product_id IN ($P)",
    'product_options'              => "id IN ($O)",
    'price_tables'                 => "id IN ($T)",
    'price_table_rows'             => "price_table_id IN ($T)",
    'product_extras'               => "id IN ($E)",
    'product_extra_systems'        => "product_extra_id IN ($E)",
    'product_extra_parent_choices' => "product_extra_id IN ($E)",
    'product_extra_choices'        => "id IN ($C)",
    'product_extra_choice_bands'   => "choice_id IN ($C)",
    'product_extra_choice_options' => "choice_id IN ($C) AND option_id IN ($O)",
    'extra_choice_price_rows'      => "product_extra_choice_id IN ($C)",
    'allowance_rows'               => 'client_id = ' . FACTORY,
    'client_markups'               => "client_id = " . FACTORY . " AND (product_id IS NULL OR product_id IN ($P))",
    'client_discounts'             => "client_id = " . FACTORY . " AND (product_id IS NULL OR product_id IN ($P))",
    'build_variables'              => "product_id IN ($P)",
    'build_rules'                  => "product_id IN ($P)",
    'product_route_steps'          => "product_id IN ($P)",
    'production_areas'             => 'client_id = ' . FACTORY,
    'product_area_map'             => "product_id IN ($P)",
    'product_lead_times'           => "product_id IN ($P)",
    'worksheet_templates'          => "product_id IN ($P)",
    'worksheet_template_versions'  => "product_id IN ($P)",
    'suppliers'                    => 'client_id = ' . FACTORY,
    'factory_remake_reasons'       => 'factory_client_id = ' . FACTORY,
    'plan_pricing'                 => '1',
];

// Column overrides: contact details and anything identifying is replaced.
$scrub = [
    'clients' => [
        'company_name' => 'Test Factory Ltd', 'contact_name' => 'Factory Office',
        'email' => 'factory@example.test', 'phone' => '01000 000000', 'mobile' => null,
        'vat_number' => 'GB000000000', 'address1' => '1 Test Park', 'address2' => '',
        'town' => 'Testtown', 'county' => 'Testshire', 'postcode' => 'TE1 1ST',
        'logo_path' => null, 'office_order_email' => null, 'office_quote_email' => null,
        'notes' => null, 'last_backup_at' => null,
    ],
    'client_settings' => [
        'email_from_name' => 'Test Factory', 'reply_to_email' => 'factory@example.test',
        'accept_email_body' => null, 'quote_footer' => null,
        'terms_conditions' => 'Test terms for {{company_name}}.',
        'trade_terms_conditions' => 'Test trade terms.', 'privacy_policy' => 'Test privacy policy.',
        'bank_account_name' => 'Test Factory', 'bank_sort_code' => '00-00-00', 'bank_account_number' => '00000000',
        'order_notify_email' => 'orders@example.test', 'factory_notify_email' => 'factory@example.test',
        'supplier_delivery_address' => '', 'sms_sender_name' => null, 'sms_templates' => null,
        'feature_sms' => 0, 'feature_maps' => 0, 'feature_postcode_lookup' => 0,
        'auto_send_suppliers' => 0,
    ],
    'suppliers' => ['account_number' => null],
    'plan_pricing' => ['paypal_plan_id' => null, 'notes' => null],
];

$out = fopen(dirname(__DIR__) . '/fixtures/10_factory_catalogue.sql', 'wb');
fwrite($out, "-- GENERATED by tests/tools/make_catalogue_fixture.php from a PII-free local test copy.\n");
fwrite($out, "-- The factory (client " . FACTORY . ") and its active catalogue. Do not hand-edit; regenerate.\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n\n");

$total = 0;
foreach ($tables as $table => $where) {
    // Generated (computed) columns can't be inserted into — leave them out.
    $st = $pdo->prepare("SELECT column_name FROM information_schema.columns
                          WHERE table_schema = DATABASE() AND table_name = ?
                            AND (extra LIKE '%GENERATED%' OR extra IN ('VIRTUAL','PERSISTENT','STORED'))");
    $st->execute([$table]);
    $generated = $st->fetchAll(PDO::FETCH_COLUMN);
    $rows = $pdo->query("SELECT * FROM `$table` WHERE $where ORDER BY 1")->fetchAll();
    if (!$rows) { fwrite($out, "-- $table: no rows\n\n"); continue; }
    foreach ($rows as &$r) foreach ($generated as $g) unset($r[$g]);
    unset($r);
    $cols = array_keys($rows[0]);
    $colSql = '`' . implode('`,`', $cols) . '`';
    foreach (array_chunk($rows, 200) as $chunk) {
        $vals = [];
        foreach ($chunk as $r) {
            if ($table === 'suppliers') $r['email'] = 'supplier' . $r['id'] . '@example.test';
            foreach ($scrub[$table] ?? [] as $k => $v) if (array_key_exists($k, $r)) $r[$k] = $v;
            $vals[] = '(' . implode(',', array_map(static function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string) $v);
            }, $r)) . ')';
        }
        fwrite($out, "INSERT INTO `$table` ($colSql) VALUES\n" . implode(",\n", $vals) . ";\n");
    }
    fwrite($out, "\n");
    $total += count($rows);
    fprintf(STDERR, "%-30s %6d\n", $table, count($rows));
}
fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($out);
fprintf(STDERR, "total rows: %d\n", $total);
