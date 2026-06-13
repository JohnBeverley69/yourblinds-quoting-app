<?php
declare(strict_types=1);

/**
 * Guided "set up your first product" wizard.
 *
 * Brand-new tenants land on /admin/products/index.php with zero
 * products and a vague "click + Add product, then figure out the
 * rest" empty state. This wizard replaces the figuring-out with a
 * forced sequence:
 *
 *   1. Name        — create the product row
 *   2. Systems     — add at least one operating system (Standard /
 *                    Motorised / etc.). Required because price tables
 *                    hang off systems.
 *   3. Fabrics     — add at least one fabric (or whatever the
 *                    product's option_label is). Required for the
 *                    product to be quotable.
 *   4. Done        — celebration screen explaining what's left
 *                    (price tables) and dropping them onto the edit
 *                    page where they can add those inline.
 *
 * Each step uses Post/Redirect/Get — refreshing the page never
 * re-submits a form. The current step is inferred from the product's
 * state if no ?step= is provided, so a user who closes the tab can
 * resume exactly where they left off by hitting the wizard URL with
 * just the product id.
 *
 * "Skip wizard" link in the header drops out to the standard edit
 * page at any time. The wizard never blocks; it just suggests an
 * order.
 *
 * Sibling pages (/new.php, /systems.php, /options.php) still work
 * — the wizard wraps them, it doesn't replace them.
 */

require __DIR__ . '/../../bootstrap.php';
require __DIR__ . '/../../auth/middleware.php';

requireAdmin();

$user     = current_user();
$clientId = (int) $user['client_id'];
$pdo      = db();

$productId = (int) ($_GET['id'] ?? 0);
$forcedStep = isset($_GET['step']) ? (int) $_GET['step'] : 0;

// Optional columns (migrations may or may not have run). Detected once so
// the INSERT/UPDATE/SELECT below can include them only when present.
$colExists = static function (string $col) use ($pdo): bool {
    try {
        return (bool) $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'products'
                AND COLUMN_NAME  = " . $pdo->quote($col)
        )->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
};
$hasRequiresOption = $colExists('requires_option');
$hasWidthOnly      = $colExists('width_only');
$hasPricePerDrop   = $colExists('price_per_slat');
$hasPriceSqm       = $colExists('price_per_sqm');
$hasShowColField   = $colExists('show_colour_field');

// ── Load product if id supplied ────────────────────────────────────────
$product = null;
if ($productId > 0) {
    $cols = 'id, name, option_label'
          . ($hasRequiresOption ? ', requires_option'      : '')
          . ($hasWidthOnly      ? ', width_only'           : '')
          . ($hasPricePerDrop   ? ', price_per_slat' : '')
          . ($hasPriceSqm       ? ', price_per_sqm'        : '');
    $st = $pdo->prepare(
        "SELECT $cols FROM products WHERE id = ? AND client_id = ?"
    );
    $st->execute([$productId, $clientId]);
    $product = $st->fetch() ?: null;
    if (!$product) {
        // Bad id — restart the wizard from scratch.
        header('Location: /admin/products/wizard.php');
        exit;
    }
}

// requires_option = 0 marks a "no-fabric" product (headrail/track/spares):
// the fabric step (3) is skipped and pricing is system × size alone.
// Absent column ⇒ true (the historical default — every product needs a
// fabric).
$requiresOption = !isset($product['requires_option'])
    || (int) $product['requires_option'] === 1;

// width_only = priced on width alone (headrail/track). Drives the step-4
// width-price importer CTA. Absent column ⇒ false.
$widthOnly = isset($product['width_only']) && (int) $product['width_only'] === 1;

// price_per_slat = price table is a width→rate list (× drop). Drives
// the step-4 rate importer CTA. Absent column ⇒ false.
$pricePerDrop = isset($product['price_per_slat'])
    && (int) $product['price_per_slat'] === 1;

// price_per_sqm = priced by area (shutters). Absent column ⇒ false.
$perSqm = isset($product['price_per_sqm'])
    && (int) $product['price_per_sqm'] === 1;

// ── State counts (drives both step inference and the "you're done with
//    step X" indicators in the UI) ─────────────────────────────────────
$systemCount = 0;
$fabricCount = 0;
if ($product) {
    $cnt = static function (string $sql, array $args) use ($pdo): int {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return (int) $st->fetchColumn();
    };
    $systemCount = $cnt(
        'SELECT COUNT(*) FROM product_systems
          WHERE product_id = ? AND client_id = ? AND active = 1',
        [$productId, $clientId]
    );
    $fabricCount = $cnt(
        'SELECT COUNT(*) FROM product_options
          WHERE product_id = ? AND client_id = ? AND active = 1',
        [$productId, $clientId]
    );
}

// ── Determine current step ────────────────────────────────────────────
//
// If the user explicitly asked for a step via ?step=N, honour that —
// they may be using the back link. Otherwise infer from state: no
// product = step 1, no system = step 2, no fabric = step 3, else
// step 4 (done).
if ($forcedStep >= 1 && $forcedStep <= 4) {
    $step = $forcedStep;
    // A no-fabric product has no step 3 — bounce a forced step-3 link
    // (e.g. the stepper back-link) on to price tables.
    if ($step === 3 && $product && !$requiresOption) {
        header('Location: /admin/products/wizard.php?id=' . $productId . '&step=4');
        exit;
    }
} elseif (!$product) {
    $step = 1;
} elseif ($systemCount === 0) {
    $step = 2;
} elseif ($requiresOption && $fabricCount === 0) {
    $step = 3;
} else {
    $step = 4;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$error = null;

// ── POST handlers ─────────────────────────────────────────────────────
//
// Always Post/Redirect/Get. The redirect target is normally the next
// step, except for "add another" which loops back to the same step
// so the user can pile on more rows.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $postStep = (int) ($_POST['_step'] ?? 0);
    $action   = (string) ($_POST['_action'] ?? '');

    try {
        // ── Step 1: create the product ─────────────────────────────────
        if ($postStep === 1) {
            $name        = trim((string) ($_POST['name']         ?? ''));
            $optionLabel = trim((string) ($_POST['option_label'] ?? '')) ?: 'Fabric';

            if ($name === '')              throw new RuntimeException('Product name is required.');
            if (strlen($name) > 150)       throw new RuntimeException('Product name too long (max 150).');
            if (strlen($optionLabel) > 40) throw new RuntimeException('Option label too long (max 40).');

            $sortStmt = $pdo->prepare(
                'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM products WHERE client_id = ?'
            );
            $sortStmt->execute([$clientId]);
            $nextSort = (int) $sortStmt->fetchColumn();

            // Smart default for show_colour_field: hide the Colour
            // sub-field if the typed option_label already means
            // "colour" (e.g. "Slat Colour"). Both this and
            // requires_option are appended only when the schema has the
            // column (migrations may not have run yet).
            //
            // no_fabric checkbox → requires_option = 0: a headrail/track/
            // spares product with no fabric axis. Skips step 3 and prices
            // on system × size alone.
            $scfDefault     = preg_match('/colou?r/i', $optionLabel) ? 0 : 1;
            $requiresOptVal = empty($_POST['no_fabric']) ? 1 : 0;

            $insCols = ['client_id', 'name', 'option_label', 'sort_order', 'active'];
            $insVals = [$clientId, $name, $optionLabel, $nextSort, 1];
            if ($hasShowColField) {
                $insCols[] = 'show_colour_field';
                $insVals[] = $scfDefault;
            }
            if ($hasRequiresOption) {
                $insCols[] = 'requires_option';
                $insVals[] = $requiresOptVal;
            }
            if ($hasWidthOnly) {
                $insCols[] = 'width_only';
                $insVals[] = empty($_POST['width_only']) ? 0 : 1;
            }
            if ($hasPricePerDrop) {
                $insCols[] = 'price_per_slat';
                $insVals[] = empty($_POST['price_per_slat']) ? 0 : 1;
            }
            if ($hasPriceSqm) {
                $insCols[] = 'price_per_sqm';
                $insVals[] = empty($_POST['price_per_sqm']) ? 0 : 1;
            }
            $insPh = implode(', ', array_fill(0, count($insCols), '?'));
            $ins = $pdo->prepare(
                'INSERT INTO products (' . implode(', ', $insCols) . ") VALUES ($insPh)"
            );
            $ins->execute($insVals);
            $newId = (int) $pdo->lastInsertId();

            header('Location: /admin/products/wizard.php?id=' . $newId . '&step=2');
            exit;
        }

        // ── Step 2: add a system ───────────────────────────────────────
        if ($postStep === 2 && $product) {
            if ($action === 'add') {
                $sysName = trim((string) ($_POST['system_name'] ?? ''));
                if ($sysName === '')         throw new RuntimeException('System name is required.');
                if (strlen($sysName) > 150)  throw new RuntimeException('System name too long (max 150).');

                $sortStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1
                       FROM product_systems
                      WHERE product_id = ? AND client_id = ?'
                );
                $sortStmt->execute([$productId, $clientId]);
                $nextSort = (int) $sortStmt->fetchColumn();

                $ins = $pdo->prepare(
                    'INSERT INTO product_systems
                      (client_id, product_id, name, sort_order, active)
                      VALUES (?, ?, ?, ?, 1)'
                );
                $ins->execute([$clientId, $productId, $sysName, $nextSort]);

                $_SESSION['flash_success'] = 'Added system "' . $sysName . '".';
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=2');
                exit;
            }
            if ($action === 'add_bulk') {
                // Same shape as the fabric bulk-add. Paste one system
                // name per line; each line becomes a system in
                // sort_order. Skips blank / overlong lines and counts
                // dupe-name failures separately so one bad row doesn't
                // sink the whole batch.
                $raw   = (string) ($_POST['bulk_names'] ?? '');
                $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
                $names = [];
                foreach ($lines as $line) {
                    $n = trim($line);
                    if ($n === '' || strlen($n) > 150) continue;
                    $names[] = $n;
                }
                if (!$names) {
                    throw new RuntimeException('No system names — paste at least one name into the box.');
                }

                $sortStmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), -1) + 1
                       FROM product_systems
                      WHERE product_id = ? AND client_id = ?'
                );
                $sortStmt->execute([$productId, $clientId]);
                $nextSort = (int) $sortStmt->fetchColumn();

                $ins = $pdo->prepare(
                    'INSERT INTO product_systems
                      (client_id, product_id, name, sort_order, active)
                      VALUES (?, ?, ?, ?, 1)'
                );
                $added   = 0;
                $skipped = 0;
                foreach ($names as $n) {
                    try {
                        $ins->execute([$clientId, $productId, $n, $nextSort]);
                        $added++;
                        $nextSort++;
                    } catch (Throwable $e) {
                        $skipped++;
                    }
                }
                $msg = "Added $added system" . ($added === 1 ? '' : 's') . '.';
                if ($skipped > 0) $msg .= " Skipped $skipped (likely duplicates).";
                $_SESSION['flash_success'] = $msg;
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=2');
                exit;
            }
            if ($action === 'continue') {
                // Server-side guard — the button is also disabled in HTML
                // when count = 0, but defence-in-depth.
                if ($systemCount === 0) {
                    throw new RuntimeException('Add at least one system before continuing.');
                }
                // No-fabric products skip the fabric step entirely.
                $next = $requiresOption ? 3 : 4;
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=' . $next);
                exit;
            }
        }

        // ── Step 3: add a fabric ───────────────────────────────────────
        //
        // Simpler fields than the full /options.php form — wizard
        // collects just band + name. Supplier / colour / code can be
        // filled in later. Keeps the cognitive load low at the worst
        // moment (brand-new tenant doesn't yet know which fields
        // actually matter to them).
        //
        // Two add modes: single-add (one row at a time) and bulk-add
        // (paste a list of names, all go into the chosen band). The
        // bulk path is the workhorse — a typical venetian / vertical
        // tenant has 50-200 colours per band, and one-at-a-time was
        // unusable for that scale.
        if ($postStep === 3 && $product) {
            // "This product has no fabrics" — flip it to a no-fabric
            // product and jump to price tables. Lets a user who picked
            // the wrong path (or a product created before this feature)
            // convert without starting over.
            if ($action === 'skip_no_fabric') {
                if (!$hasRequiresOption) {
                    throw new RuntimeException('No-fabric products need the requires_option column — run migrate_requires_option.php first.');
                }
                $pdo->prepare(
                    'UPDATE products SET requires_option = 0 WHERE id = ? AND client_id = ?'
                )->execute([$productId, $clientId]);
                $_SESSION['flash_success'] = 'Marked as a no-fabric product — fabric step skipped.';
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=4');
                exit;
            }
            if ($action === 'add') {
                $band = trim((string) ($_POST['band_code'] ?? ''));
                $fab  = trim((string) ($_POST['fabric_name'] ?? ''));
                $band = (string) preg_replace('/^band\s+/i', '', $band);
                // system_id: blank string or "0" → NULL (universal).
                $sysIdRaw = (string) ($_POST['system_id'] ?? '');
                $sysId    = ($sysIdRaw === '' || $sysIdRaw === '0') ? null : (int) $sysIdRaw;

                if ($band === '')          throw new RuntimeException('Band code is required (e.g. A, B, C, or Standard, Special).');
                if (strlen($band) > 20)    throw new RuntimeException('Band code too long (max 20).');
                if ($fab === '')           throw new RuntimeException('Fabric name is required.');
                if (strlen($fab) > 150)    throw new RuntimeException('Fabric name too long (max 150).');
                if ($sysId !== null) {
                    // Validate the chosen system belongs to this product / tenant.
                    $check = $pdo->prepare(
                        'SELECT 1 FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ?'
                    );
                    $check->execute([$sysId, $productId, $clientId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Chosen system is not on this product.');
                    }
                }

                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                      (client_id, product_id, system_id, band_code, name, sort_order, active)
                      VALUES (?, ?, ?, ?, ?, 0, 1)'
                );
                $ins->execute([$clientId, $productId, $sysId, $band, $fab]);

                $_SESSION['flash_success'] = 'Added "' . $fab . '" (Band ' . $band
                    . ($sysId !== null ? ', system #' . $sysId : '') . ').';
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=3');
                exit;
            }
            if ($action === 'add_bulk') {
                $band = trim((string) ($_POST['bulk_band'] ?? ''));
                $band = (string) preg_replace('/^band\s+/i', '', $band);
                $namesRaw = (string) ($_POST['bulk_names'] ?? '');
                $sysIdRaw = (string) ($_POST['bulk_system_id'] ?? '');
                $sysId    = ($sysIdRaw === '' || $sysIdRaw === '0') ? null : (int) $sysIdRaw;

                if ($band === '')         throw new RuntimeException('Band code is required.');
                if (strlen($band) > 20)   throw new RuntimeException('Band code too long (max 20).');
                if ($sysId !== null) {
                    $check = $pdo->prepare(
                        'SELECT 1 FROM product_systems
                          WHERE id = ? AND product_id = ? AND client_id = ?'
                    );
                    $check->execute([$sysId, $productId, $clientId]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Chosen system is not on this product.');
                    }
                }

                // Two formats accepted in the same textarea:
                //
                // 1. Single-section: every line is a colour name; all
                //    rows land on the system picked in the "Available
                //    on" dropdown above (or universal if nothing's
                //    picked). The original behaviour.
                //
                // 2. Multi-section: lines that look like "[System Name]"
                //    act as section headers. Subsequent rows go to
                //    that system until the next header. Unknown system
                //    names get skipped silently. This lets a tenant
                //    with 10+ systems paste every colour batch in one
                //    submission instead of repeating the form per
                //    system.
                //
                // Detection: if ANY line matches the [Name] shape we
                // switch to multi-section parsing. Otherwise the
                // textarea is treated as one flat colour list.
                $lines = preg_split('/\r\n|\r|\n/', $namesRaw) ?: [];
                $hasSections = false;
                foreach ($lines as $line) {
                    if (preg_match('/^\s*\[(.+)\]\s*$/', $line)) {
                        $hasSections = true;
                        break;
                    }
                }

                $ins = $pdo->prepare(
                    'INSERT INTO product_options
                      (client_id, product_id, system_id, band_code, name, sort_order, active)
                      VALUES (?, ?, ?, ?, ?, 0, 1)'
                );
                $added   = 0;
                $skipped = 0;
                $unknownSystems = [];
                $touchedSystems = [];

                if ($hasSections) {
                    // Build a name → id lookup for systems on this
                    // product. Case-insensitive on the assumption
                    // tenants paste system names sloppily.
                    $sysLookup = [];
                    foreach ($systems as $s) {
                        $sysLookup[strtolower(trim((string) $s['name']))] = (int) $s['id'];
                    }

                    $currentSysId = $sysId;  // fall back to dropdown
                    foreach ($lines as $line) {
                        if (preg_match('/^\s*\[(.+)\]\s*$/', $line, $m)) {
                            $key = strtolower(trim($m[1]));
                            if (isset($sysLookup[$key])) {
                                $currentSysId = $sysLookup[$key];
                            } else {
                                $currentSysId = null;
                                $unknownSystems[$m[1]] = true;
                            }
                            continue;
                        }
                        $name = trim($line);
                        if ($name === '' || strlen($name) > 150) continue;
                        if ($currentSysId === null) {
                            $skipped++;
                            continue;
                        }
                        try {
                            $ins->execute([$clientId, $productId, $currentSysId, $band, $name]);
                            $added++;
                            $touchedSystems[$currentSysId] = true;
                        } catch (Throwable $e) {
                            $skipped++;
                        }
                    }
                } else {
                    // Single-section — original behaviour.
                    $names = [];
                    foreach ($lines as $line) {
                        $name = trim($line);
                        if ($name === '')          continue;
                        if (strlen($name) > 150)   continue;
                        $names[] = $name;
                    }
                    if (!$names) {
                        throw new RuntimeException('No names to add — paste at least one name into the box.');
                    }
                    foreach ($names as $name) {
                        try {
                            $ins->execute([$clientId, $productId, $sysId, $band, $name]);
                            $added++;
                        } catch (Throwable $e) {
                            $skipped++;
                        }
                    }
                    if ($added > 0 && $sysId !== null) {
                        $touchedSystems[$sysId] = true;
                    }
                }

                if ($added === 0) {
                    throw new RuntimeException('Nothing added. Check the paste — colours need a [System Name] header above them, or pick a system in the dropdown.');
                }

                $msg = "Added $added to Band $band";
                if ($hasSections) {
                    $msg .= ' across ' . count($touchedSystems) . ' system'
                          . (count($touchedSystems) === 1 ? '' : 's') . '.';
                } else {
                    $msg .= ($sysId !== null ? ' (one system only).' : ' (all systems).');
                }
                if ($skipped > 0)        $msg .= " Skipped $skipped (likely duplicates).";
                if ($unknownSystems)     $msg .= ' Unknown system names: "'
                    . implode('", "', array_keys($unknownSystems)) . '".';
                $_SESSION['flash_success'] = $msg;
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=3');
                exit;
            }
            if ($action === 'continue') {
                if ($fabricCount === 0) {
                    throw new RuntimeException('Add at least one fabric before continuing.');
                }
                header('Location: /admin/products/wizard.php?id=' . $productId . '&step=4');
                exit;
            }
        }

        // ── Step 4: create missing price-table stubs ───────────────────
        //
        // User explicitly opted in via the "Create missing" button.
        // Same INSERT IGNORE as the first-time auto-create — safe to
        // run again.
        if ($postStep === 4 && $product && $action === 'create_missing') {
            if (!$requiresOption) {
                // No-fabric product: one price table per system, with an
                // empty band_code (there's no band axis). The engine's
                // no-fabric lookup picks the system's table regardless of
                // band, so '' is just a placeholder key.
                $stubStmt = $pdo->prepare(
                    "INSERT IGNORE INTO price_tables
                        (client_id, product_id, system_id, band_code, active)
                     SELECT ?, ?, s.id, '', 1
                       FROM product_systems s
                      WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1"
                );
                $stubStmt->execute([
                    $clientId, $productId,
                    $productId, $clientId,
                ]);
            } else {
                // Same scope-aware logic as the first-time auto-create:
                // only combos with at least one matching fabric.
                $stubStmt = $pdo->prepare(
                    "INSERT IGNORE INTO price_tables
                        (client_id, product_id, system_id, band_code, active)
                     SELECT DISTINCT ?, ?, s.id, po.band_code, 1
                       FROM product_systems s
                       JOIN product_options po
                         ON po.product_id = s.product_id
                        AND po.client_id  = s.client_id
                        AND po.active     = 1
                        AND po.band_code IS NOT NULL
                        AND po.band_code != ''
                        AND (po.system_id IS NULL OR po.system_id = s.id)
                      WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1"
                );
                $stubStmt->execute([
                    $clientId, $productId,
                    $productId, $clientId,
                ]);
            }
            $created = $stubStmt->rowCount();
            $_SESSION['flash_success'] = $created === 1
                ? '1 price table created.'
                : $created . ' price tables created.';
            header('Location: /admin/products/wizard.php?id=' . $productId . '&step=4');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// ── Load lists for display on steps 2 + 3 ─────────────────────────────
$systems = [];
$fabrics = [];
if ($product && $step >= 2) {
    $sysSt = $pdo->prepare(
        'SELECT id, name FROM product_systems
          WHERE product_id = ? AND client_id = ? AND active = 1
          ORDER BY sort_order, name'
    );
    $sysSt->execute([$productId, $clientId]);
    $systems = $sysSt->fetchAll();
}
if ($product && $step >= 3) {
    // Pull system_id + system name so we can show "Standard only" /
    // "Special only" tags next to scoped fabrics, and "All systems"
    // (i.e. unscoped) for universal ones.
    $fabSt = $pdo->prepare(
        'SELECT o.id, o.band_code, o.name, o.colour, o.system_id,
                s.name AS system_name
           FROM product_options o
           LEFT JOIN product_systems s ON s.id = o.system_id
          WHERE o.product_id = ? AND o.client_id = ? AND o.active = 1
          ORDER BY o.band_code, o.name'
    );
    $fabSt->execute([$productId, $clientId]);
    $fabrics = $fabSt->fetchAll();
}

// Distinct band codes already in use on this product — used to
// populate the <datalist> autocomplete on the band input so the
// user can pick from existing bands rather than retyping. Pulls
// from BOTH product_options and price_tables so a band created in
// step 4 is still pickable from step 3 and vice versa.
//
// Note: no active filter here. A deactivated price_table or option
// still represents a band the tenant has defined on this product;
// surfacing it on autocomplete is more helpful than gating on
// active=1 (where typos / deactivations would silently swallow
// bands the user expects to see).
$knownBands = [];
if ($product) {
    $bandSt = $pdo->prepare(
        "SELECT DISTINCT band_code FROM (
            SELECT band_code FROM product_options
             WHERE product_id = ? AND client_id = ?
            UNION
            SELECT band_code FROM price_tables
             WHERE product_id = ? AND client_id = ?
         ) x
         WHERE band_code IS NOT NULL AND band_code != ''
         ORDER BY band_code"
    );
    $bandSt->execute([$productId, $clientId, $productId, $clientId]);
    $knownBands = array_map(
        static fn ($r) => (string) $r,
        $bandSt->fetchAll(PDO::FETCH_COLUMN)
    );
}

// ── Step 4 setup: load price tables + detect missing combos ──────────
//
// Each (system × distinct band_code) combination CAN have a price
// table. The wizard shows the existing ones with fill status, plus
// a list of any (system × band) combos that have no table yet —
// the user can create them all with one click, individually via
// the edit page, or leave them alone if a combo doesn't apply.
//
// Auto-create on FIRST entry to step 4 only (when zero tables
// exist for the product). After that, the user explicitly
// chooses what to create via "Create missing" — otherwise a
// deleted stub would silently come back on next step 4 visit,
// which would be infuriating.
//
// Schema notes:
//   - price_tables has a UNIQUE (product_id, system_id, band_code)
//     constraint (per tenant via client_id). INSERT IGNORE on the
//     create-missing path handles races safely.
//   - Fabrics without band_code don't generate combos (the band
//     is the price-band key — without it, no pricing).
$priceTables   = [];
$missingCombos = [];
if ($product && $step === 4) {
    // NOTE: no implicit auto-create here. The previous "create
    // stubs on first visit" heuristic couldn't distinguish "tenant
    // hasn't set up yet" from "tenant just deleted everything" —
    // both look like zero tables — so deletions could bounce back
    // on the next page load. Stub creation is now ALWAYS explicit
    // via the "Create the missing tables" button below, which
    // hits the create_missing POST action.

    // Load every existing (system, band) combo with its fill status.
    $combosStmt = $pdo->prepare(
        "SELECT t.id, s.name AS system_name, t.band_code,
                (SELECT COUNT(*) FROM price_table_rows r
                  WHERE r.price_table_id = t.id) AS cell_count
           FROM price_tables t
           JOIN product_systems s ON s.id = t.system_id
          WHERE t.product_id = ? AND t.client_id = ? AND t.active = 1
            AND s.active = 1
          ORDER BY s.sort_order, s.name, t.band_code"
    );
    $combosStmt->execute([$productId, $clientId]);
    $priceTables = $combosStmt->fetchAll();

    if (!$requiresOption) {
        // No-fabric product: a "missing" combo is simply any active
        // system with no price table yet (one table per system, no band
        // axis).
        $missingStmt = $pdo->prepare(
            "SELECT s.id AS system_id, s.name AS system_name, '' AS band_code
               FROM product_systems s
              WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1
                AND NOT EXISTS (
                  SELECT 1 FROM price_tables t
                   WHERE t.product_id = s.product_id
                     AND t.system_id  = s.id
                     AND t.client_id  = ?
                     AND t.active     = 1
                )
              ORDER BY s.sort_order, s.name"
        );
        $missingStmt->execute([$productId, $clientId, $clientId]);
        $missingCombos = $missingStmt->fetchAll();
    } else {
        // Detect (system × band) combos that don't yet have a table.
        // System scoping respected: a band only counts as "missing" for
        // a system if there's at least one fabric of that band that
        // could be used with that system (universal or scoped to it).
        $missingStmt = $pdo->prepare(
            "SELECT DISTINCT s.id AS system_id, s.name AS system_name, po.band_code
               FROM product_systems s
               JOIN product_options po
                 ON po.product_id = s.product_id
                AND po.client_id  = s.client_id
                AND po.active     = 1
                AND po.band_code IS NOT NULL
                AND po.band_code != ''
                AND (po.system_id IS NULL OR po.system_id = s.id)
              WHERE s.product_id = ? AND s.client_id = ? AND s.active = 1
                AND NOT EXISTS (
                  SELECT 1 FROM price_tables t
                   WHERE t.product_id = s.product_id
                     AND t.system_id  = s.id
                     AND t.band_code  = po.band_code
                     AND t.client_id  = ?
                     AND t.active     = 1
                )
              ORDER BY s.sort_order, s.name, po.band_code"
        );
        $missingStmt->execute([
            $productId, $clientId,
            $clientId,
        ]);
        $missingCombos = $missingStmt->fetchAll();
    }
}

// Steps metadata for the stepper UI.
$STEPS = [
    1 => ['Name',         'What kind of blind is this?'],
    2 => ['Systems',      'Standard / Motorised / etc.'],
    3 => $requiresOption
        ? ['Fabrics',  'The materials you actually sell']
        : ['Fabrics',  'Not needed — no-fabric product'],
    4 => ['Price tables', 'Width × drop grids per band / system'],
];

// Highlight the "Setup wizard" sidebar entry while in the wizard
// flow, not "Products" — the user is doing focused setup work and
// the breadcrumb should reflect that. Once they hit "Open product
// edit page →" at the end, they're back on the Products nav.
$activeNav = 'wizard';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup wizard &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .wiz-wrap { max-width: 44rem; margin: 0 auto; }

        /* Progress stepper at the top — clickable for completed
           steps so users can go back to fix a previous entry. */
        .wiz-stepper {
            display: flex; align-items: stretch; gap: 0;
            margin: 0 0 1.25rem; padding: 0; list-style: none;
        }
        .wiz-stepper li {
            flex: 1; text-align: center; position: relative;
            padding: 0.5rem 0.25rem 0.625rem;
        }
        .wiz-stepper li::after {
            content: ''; position: absolute;
            top: 1.5rem; right: -50%;
            width: 100%; height: 2px;
            background: var(--border); z-index: 0;
        }
        .wiz-stepper li:last-child::after { display: none; }
        .wiz-stepper li.done::after    { background: #10b981; }
        .wiz-stepper li.current::after { background: var(--border); }
        .wiz-stepper .num {
            position: relative; z-index: 1;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: 999px;
            background: var(--border); color: var(--text-faint);
            font-weight: 700; font-size: 0.875rem;
        }
        .wiz-stepper li.done    .num { background: #10b981; color: #fff; }
        .wiz-stepper li.current .num { background: #1f3b5b; color: #fff;
                                       box-shadow: 0 0 0 4px #dbeafe; }
        .wiz-stepper .lbl {
            display: block; margin-top: 0.375rem;
            font-size: 0.75rem; color: var(--text-faint);
            text-transform: uppercase; letter-spacing: 0.04em;
            font-weight: 600;
        }
        .wiz-stepper li.current .lbl { color: var(--text-body); }
        .wiz-stepper li.done    .lbl { color: #065f46; }
        .wiz-stepper a { color: inherit; text-decoration: none; }

        .wiz-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; padding: 1.5rem 1.625rem;
        }
        .wiz-card h2 {
            margin: 0 0 0.25rem; font-size: 1.25rem; color: var(--text-body);
        }
        .wiz-card .lede {
            color: var(--text-muted); font-size: 0.9375rem;
            margin: 0 0 1.25rem; line-height: 1.55;
        }
        .wiz-card .helper {
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 8px; padding: 0.625rem 0.875rem;
            font-size: 0.8125rem; color: #1e40af;
            margin-bottom: 1rem; line-height: 1.5;
        }
        .wiz-list {
            background: var(--bg-subtle); border: 1px solid var(--border);
            border-radius: 8px; padding: 0.5rem 0.75rem;
            margin-bottom: 0.875rem;
        }
        .wiz-list-item {
            padding: 0.3125rem 0;
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--bg-subtle-2);
        }
        .wiz-list-item:last-child { border-bottom: 0; }
        .wiz-list-item .check {
            color: #10b981; font-weight: 700; font-size: 1rem;
        }
        .wiz-list-empty {
            color: var(--text-faint); font-style: italic; font-size: 0.875rem;
            padding: 0.5rem 0;
        }
        .wiz-form .row {
            display: grid; gap: 0.5rem 0.75rem; align-items: end;
            margin-bottom: 0.625rem;
        }
        .wiz-form .row.cols-2 { grid-template-columns: 8rem 1fr auto; }
        .wiz-form label {
            font-size: 0.6875rem; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--text-faint); font-weight: 600;
        }
        .wiz-form input {
            padding: 0.5rem 0.625rem; border: 1px solid var(--border-strong);
            border-radius: 6px; font: inherit;
        }
        .wiz-actions {
            display: flex; gap: 0.5rem; align-items: center;
            justify-content: space-between;
            margin-top: 1rem; padding-top: 1rem;
            border-top: 1px solid var(--bg-subtle-2);
        }
        .wiz-actions .left { display: flex; gap: 0.5rem; align-items: center; }
        .wiz-skip {
            color: var(--text-faint); font-size: 0.8125rem; text-decoration: underline;
        }
        .wiz-done-tile {
            background: linear-gradient(135deg, #d1fae5 0%, #ecfdf5 100%);
            border: 1px solid #a7f3d0; border-radius: 12px;
            padding: 1.5rem 1.625rem; margin-bottom: 1rem;
            text-align: center;
        }
        .wiz-done-tile .icon { font-size: 3rem; line-height: 1; }
        .wiz-done-tile h2 { color: #065f46; margin: 0.5rem 0 0.375rem; }
        .wiz-done-tile p  { color: #047857; margin: 0; line-height: 1.55; }
        .wiz-next-step {
            background: #fef3c7; border: 1px solid #fcd34d;
            border-radius: 8px; padding: 0.875rem 1rem;
            font-size: 0.875rem; color: #78350f;
            margin-top: 0.875rem; line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="wiz-wrap">
            <div class="page-header" style="margin-bottom:0.875rem">
                <div>
                    <h1 class="page-title" style="margin:0">Set up a product</h1>
                    <p class="page-subtitle" style="margin:0.25rem 0 0">
                        Guided 4-step setup.
                        <?php if ($product): ?>
                            <a href="/admin/products/edit.php?id=<?= (int) $productId ?>" class="wiz-skip">
                                Skip wizard &rarr; jump to the edit page
                            </a>
                        <?php else: ?>
                            <a href="/admin/products/new.php" class="wiz-skip">
                                Skip wizard &rarr; standard "add product" form
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Stepper -->
            <ol class="wiz-stepper">
                <?php foreach ($STEPS as $n => [$lbl, $sub]):
                    $isDone    = $n < $step;
                    $isCurrent = $n === $step;
                    $cls = $isDone ? 'done' : ($isCurrent ? 'current' : '');
                    // Allow back-navigation only to already-completed
                    // steps (don't let users skip forward by clicking).
                    $href = ($isDone && $product)
                        ? '/admin/products/wizard.php?id=' . $productId . '&step=' . $n
                        : null;
                ?>
                    <li class="<?= e($cls) ?>">
                        <?php if ($href): ?><a href="<?= e($href) ?>"><?php endif; ?>
                            <span class="num"><?= $isDone ? '&check;' : $n ?></span>
                            <span class="lbl"><?= e($lbl) ?></span>
                        <?php if ($href): ?></a><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($flashMsg): ?>
                <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
            <?php endif; ?>
            <?php if ($flashErr): ?>
                <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <!-- ─── STEP 1: Name ──────────────────────────────────────── -->
            <?php if ($step === 1): ?>
                <div class="wiz-card">
                    <h2>What kind of blind are we adding?</h2>
                    <p class="lede">
                        A <strong>product</strong> is one type of blind &mdash; e.g.
                        <em>Roller Blind</em>, <em>Vertical</em>, <em>Roman</em>,
                        <em>Metal Venetian</em>. Each product gets its own systems,
                        fabrics, options and price tables.
                    </p>

                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="1">

                        <div class="row" style="grid-template-columns:1fr">
                            <div>
                                <label for="name">Product name *</label>
                                <input id="name" name="name" type="text"
                                       required maxlength="150" autofocus
                                       placeholder="e.g. Roller Blind"
                                       value="<?= e((string) ($_POST['name'] ?? '')) ?>">
                            </div>
                        </div>

                        <div class="row" style="grid-template-columns:1fr">
                            <div>
                                <label for="option_label">What do you call the material this product is made of?</label>
                                <input id="option_label" name="option_label" type="text"
                                       maxlength="40"
                                       placeholder="Fabric"
                                       value="<?= e((string) ($_POST['option_label'] ?? 'Fabric')) ?>">
                            </div>
                        </div>

                        <div class="helper">
                            For rollers / romans use <strong>Fabric</strong>.
                            For metal venetians, try <strong>Colour</strong>.
                            For wood venetians, <strong>Finish</strong>.
                            (You can change this later.)
                        </div>

                        <?php if ($hasRequiresOption): ?>
                            <div class="row" style="grid-template-columns:1fr">
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer;
                                              text-transform:none;letter-spacing:normal;font-weight:500;
                                              color:var(--text-body)">
                                    <input type="checkbox" name="no_fabric" value="1"
                                           <?= !empty($_POST['no_fabric']) ? 'checked' : '' ?>
                                           style="margin-top:0.2rem">
                                    <span>
                                        This product has <strong>no fabrics</strong>
                                        (e.g. headrail only, track, spares).
                                        <small style="display:block;color:var(--text-faint);font-size:0.8125rem;
                                                      font-weight:400;margin-top:0.2rem;line-height:1.5">
                                            We'll skip the fabric step and price it on
                                            system &times; size alone.
                                        </small>
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasWidthOnly): ?>
                            <div class="row" style="grid-template-columns:1fr">
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer;
                                              text-transform:none;letter-spacing:normal;font-weight:500;
                                              color:var(--text-body)">
                                    <input type="checkbox" name="width_only" value="1"
                                           <?= !empty($_POST['width_only']) ? 'checked' : '' ?>
                                           style="margin-top:0.2rem">
                                    <span>
                                        Priced by <strong>width only</strong> (no drop) —
                                        e.g. a headrail or track.
                                        <small style="display:block;color:var(--text-faint);font-size:0.8125rem;
                                                      font-weight:400;margin-top:0.2rem;line-height:1.5">
                                            The Drop field is hidden at quote time and each
                                            price table is a single width &rarr; price list.
                                        </small>
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasPricePerDrop): ?>
                            <div class="row" style="grid-template-columns:1fr">
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer;
                                              text-transform:none;letter-spacing:normal;font-weight:500;
                                              color:var(--text-body)">
                                    <input type="checkbox" name="price_per_slat" value="1"
                                           <?= !empty($_POST['price_per_slat']) ? 'checked' : '' ?>
                                           style="margin-top:0.2rem">
                                    <span>
                                        Priced <strong>per slat</strong> (by drop) —
                                        e.g. vertical fabric only.
                                        <small style="display:block;color:var(--text-faint);font-size:0.8125rem;
                                                      font-weight:400;margin-top:0.2rem;line-height:1.5">
                                            Price table is a drop &rarr; price-per-slat list; the line
                                            price is that rate &times; number of slats. Leave the boxes
                                            above unticked.
                                        </small>
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasPriceSqm): ?>
                            <div class="row" style="grid-template-columns:1fr">
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;cursor:pointer;
                                              text-transform:none;letter-spacing:normal;font-weight:500;
                                              color:var(--text-body)">
                                    <input type="checkbox" name="price_per_sqm" value="1"
                                           <?= !empty($_POST['price_per_sqm']) ? 'checked' : '' ?>
                                           style="margin-top:0.2rem">
                                    <span>
                                        Priced <strong>per square metre</strong> &mdash; e.g. shutters.
                                        <small style="display:block;color:var(--text-faint);font-size:0.8125rem;
                                                      font-weight:400;margin-top:0.2rem;line-height:1.5">
                                            A single &pound;/m&sup2; rate &times; area (width &times; height),
                                            with an optional minimum area (set on the product edit page).
                                            Leave the boxes above unticked.
                                        </small>
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>

                        <div class="wiz-actions">
                            <div class="left"></div>
                            <button type="submit" class="btn btn-primary">
                                Create product &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ─── STEP 2: Systems ───────────────────────────────────── -->
            <?php if ($step === 2 && $product): ?>
                <div class="wiz-card">
                    <h2>What systems does <em><?= e((string) $product['name']) ?></em> come in?</h2>
                    <p class="lede">
                        A <strong>system</strong> is the operating mechanism &mdash;
                        e.g. <em>Standard</em>, <em>Motorised</em>, <em>Battery</em>,
                        <em>Pelmet</em>. Each system can have its own price table.
                        Most products need just one or two.
                    </p>

                    <?php if ($systems): ?>
                        <div class="wiz-list">
                            <?php foreach ($systems as $s): ?>
                                <div class="wiz-list-item">
                                    <span class="check">&check;</span>
                                    <strong><?= e((string) $s['name']) ?></strong>
                                    <form method="post" action="/admin/products/system-delete.php"
                                          style="margin:0 0 0 auto;display:inline"
                                          data-confirm="Remove the &quot;<?= e((string) $s['name']) ?>&quot; system? Any price tables it has go too.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                        <input type="hidden" name="return_to"
                                               value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=2">
                                        <button type="submit"
                                                style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="wiz-list">
                            <div class="wiz-list-empty">No systems yet — add at least one below.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Bulk-add: paste a list of system names, one per
                         line. Each line becomes a system in sort_order.
                         Same pattern as the fabric bulk-add. -->
                    <h3 style="margin:1.25rem 0 0.5rem;font-size:0.9375rem;color:var(--text-primary)">
                        Add systems
                    </h3>
                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="2">
                        <input type="hidden" name="_action" value="add_bulk">

                        <div>
                            <label for="bulk_names">One system per line (paste from Excel or type)</label>
                            <textarea id="bulk_names" name="bulk_names" rows="6" required
                                      placeholder="Standard&#10;Motorised&#10;FW 35mm String&#10;FW 50mm Tape&#10;…"
                                      style="width:100%;font:inherit;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body)"></textarea>
                        </div>
                        <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.5rem">
                            <button type="submit" class="btn btn-secondary">+ Add</button>
                            <span style="color:var(--text-faint);font-size:0.8125rem">
                                One line = one system. Single name = single add.
                            </span>
                        </div>
                    </form>

                    <div class="helper" style="margin-top:1rem">
                        <strong>What's a system?</strong> The operating mechanism
                        or physical variant — e.g. <em>Standard</em>, <em>Motorised</em>,
                        or size variants like <em>35mm String</em>, <em>50mm Tape</em>.
                        Each system gets its own price table.
                    </div>

                    <form method="post" class="wiz-actions" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="2">
                        <input type="hidden" name="_action" value="continue">
                        <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=1"
                           class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                            &larr; Back
                        </a>
                        <button type="submit" class="btn btn-primary"
                                <?= $systemCount === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>>
                            Continue &rarr;
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ─── STEP 3: Fabrics ──────────────────────────────────── -->
            <?php if ($step === 3 && $product):
                $label = (string) ($product['option_label'] ?? 'Fabric');
                if ($label === '') $label = 'Fabric';
                $labelL = strtolower($label);
            ?>
                <div class="wiz-card">
                    <h2>What <?= e($labelL) ?>s do you sell?</h2>
                    <p class="lede">
                        Add at least one <?= e($labelL) ?> with a <strong>band
                        code</strong>. The band groups <?= e($labelL) ?>s that
                        share the same price table — your "cheap range" and
                        "premium range" are usually different bands. Common
                        codes: <code>A</code>/<code>B</code>/<code>C</code>
                        from a supplier price list, or words like
                        <code>Standard</code>/<code>Special</code>.
                    </p>
                    <?php if (count($systems) >= 2): ?>
                        <p class="lede" style="margin-top:-0.5rem">
                            By default a <?= e($labelL) ?> is <strong>universal</strong>
                            — available with every system. If a colour only
                            applies to a specific system (e.g. Standard slats
                            and Special slats on a Venetian have different
                            ranges), pick that system in the dropdown.
                        </p>
                    <?php endif; ?>

                    <!-- Copy the whole range from an existing product (e.g. a
                         "fabric only" line that reuses the vertical blind's
                         fabrics) instead of typing/pasting them here. -->
                    <div class="helper" style="display:flex;align-items:center;
                                justify-content:space-between;gap:1rem;flex-wrap:wrap">
                        <span>
                            <strong>Same <?= e($labelL) ?>s as another product?</strong>
                            Copy the whole range across (bands and all) rather than
                            re-entering it.
                        </span>
                        <a href="/admin/products/options-copy.php?product_id=<?= (int) $productId ?>"
                           class="btn btn-secondary">Copy from another product &rarr;</a>
                    </div>

                    <?php if ($hasRequiresOption): ?>
                        <!-- Escape hatch for headrail-only / track / spares
                             products that have no fabric axis at all. Flips
                             requires_option = 0 and jumps to price tables. -->
                        <div class="helper" style="display:flex;align-items:center;
                                    justify-content:space-between;gap:1rem;flex-wrap:wrap">
                            <span>
                                <strong>No <?= e($labelL) ?>s for this product?</strong>
                                For a headrail-only line, track, or spares you can
                                skip this step and price on system &times; size alone.
                            </span>
                            <form method="post" style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_step" value="3">
                                <input type="hidden" name="_action" value="skip_no_fabric">
                                <button type="submit" class="btn btn-secondary"
                                        data-confirm="Mark this as a no-fabric product and skip straight to price tables?">
                                    No <?= e($labelL) ?>s — skip &rarr;
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($fabrics): ?>
                        <!-- "Delete all" bulk-clear. Posts every fabric id
                             on the product as ids[]; option-delete.php
                             already handles the array case. Behind a
                             data-confirm with the count so a misclick
                             can't wipe a 140-colour range. -->
                        <div style="display:flex;justify-content:space-between;align-items:center;
                                    margin:0 0 0.375rem;font-size:0.8125rem;color:var(--text-faint)">
                            <span><?= count($fabrics) ?> <?= e($labelL) ?><?= count($fabrics) === 1 ? '' : 's' ?> added</span>
                            <form method="post" action="/admin/products/option-delete.php"
                                  style="margin:0"
                                  data-confirm="Delete ALL <?= count($fabrics) ?> <?= e($labelL) ?>s on this product? Cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                <input type="hidden" name="return_to"
                                       value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=3">
                                <?php foreach ($fabrics as $f): ?>
                                    <input type="hidden" name="ids[]" value="<?= (int) $f['id'] ?>">
                                <?php endforeach; ?>
                                <button type="submit"
                                        style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                    Delete all
                                </button>
                            </form>
                        </div>
                        <div class="wiz-list" style="max-height:18rem;overflow-y:auto">
                            <?php foreach ($fabrics as $f): ?>
                                <div class="wiz-list-item">
                                    <span class="check">&check;</span>
                                    <strong><?= e((string) $f['name']) ?></strong>
                                    <span style="color:var(--text-faint);font-size:0.8125rem">
                                        — Band <?= e((string) $f['band_code']) ?>
                                        <?php if (!empty($f['colour'])): ?>
                                            &middot; <?= e((string) $f['colour']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($f['system_name'])): ?>
                                            &middot; <em style="color:#7c3aed">
                                                <?= e((string) $f['system_name']) ?> only
                                            </em>
                                        <?php elseif (count($systems) >= 2): ?>
                                            &middot; <em style="color:var(--text-faint)">all systems</em>
                                        <?php endif; ?>
                                    </span>
                                    <form method="post" action="/admin/products/option-delete.php"
                                          style="margin:0 0 0 auto;display:inline"
                                          data-confirm="Remove &quot;<?= e((string) $f['name']) ?>&quot; from your <?= e($labelL) ?>s?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                                        <input type="hidden" name="return_to"
                                               value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=3">
                                        <button type="submit"
                                                style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="wiz-list">
                            <div class="wiz-list-empty">No <?= e($labelL) ?>s yet — add at least one below.</div>
                        </div>
                    <?php endif; ?>

                    <!-- One unified add form — pick a band (and scope, if
                         the product has multiple systems) once, then put
                         one OR many names in the textarea. Single-name
                         and bulk-name behave the same on the server. -->
                    <h3 style="margin:1.25rem 0 0.5rem;font-size:0.9375rem;color:var(--text-primary)">
                        Add <?= e($labelL) ?>s
                    </h3>
                    <form method="post" class="wiz-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="3">
                        <input type="hidden" name="_action" value="add_bulk">

                        <div class="row" style="grid-template-columns:8rem<?= count($systems) >= 2 ? ' 12rem' : '' ?> 1fr;align-items:start;gap:0.5rem">
                            <div>
                                <label for="bulk_band">Band *</label>
                                <input id="bulk_band" name="bulk_band" type="text"
                                       required maxlength="20"
                                       placeholder="A"
                                       list="known-bands"
                                       value="">
                            </div>
                            <!-- Autocomplete options from bands already
                                 defined on this product (in either
                                 fabrics or price tables). Typing a new
                                 value still works for the very first
                                 band on a fresh product. -->
                            <?php if ($knownBands): ?>
                                <datalist id="known-bands">
                                    <?php foreach ($knownBands as $b): ?>
                                        <option value="<?= e($b) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            <?php endif; ?>
                            <?php if (count($systems) >= 2): ?>
                                <div>
                                    <label for="bulk_system_id">Available on</label>
                                    <select id="bulk_system_id" name="bulk_system_id"
                                            style="padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;font:inherit;background:var(--bg-input);color:var(--text-body);width:100%">
                                        <option value="">All systems (universal)</option>
                                        <?php foreach ($systems as $s): ?>
                                            <option value="<?= (int) $s['id'] ?>">
                                                <?= e((string) $s['name']) ?> only
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label for="bulk_names">
                                    <?= e(ucfirst($labelL)) ?>s (one per line — paste from Excel or type)
                                </label>
                                <textarea id="bulk_names" name="bulk_names"
                                          rows="10"
                                          required
                                          placeholder="Plain White&#10;Plain Cream&#10;Plain Black&#10;Silver&#10;…&#10;&#10;Or paste multiple systems at once:&#10;&#10;[Forest Wood 35mm String]&#10;Plain White&#10;Plain Cream&#10;&#10;[Forest Wood 35mm Tape]&#10;Cherry&#10;Mahogany&#10;…"
                                          style="width:100%;font:inherit;padding:0.5rem 0.625rem;border:1px solid var(--border-strong);border-radius:6px;background:var(--bg-input);color:var(--text-body);font-family:ui-monospace,Menlo,Consolas,monospace;font-size:0.8125rem"></textarea>
                            </div>
                        </div>
                        <div style="display:flex;gap:0.5rem;align-items:center;margin-top:0.5rem">
                            <button type="submit" class="btn btn-secondary">+ Add</button>
                            <span style="color:var(--text-faint);font-size:0.8125rem">
                                One line = one <?= e($labelL) ?>.
                                <?php if (count($systems) >= 2): ?>
                                    Or use <code>[System Name]</code> headers in the textarea to
                                    add to many systems in one go.
                                <?php endif; ?>
                            </span>
                        </div>
                    </form>

                    <div class="helper" style="margin-top:1.25rem">
                        <strong>Tip:</strong> The full
                        <?= e($labelL) ?> editor on the edit page lets you
                        bulk-import from an XLSX and add supplier / colour /
                        code details too — useful when you already have a
                        spreadsheet.
                    </div>

                    <form method="post" class="wiz-actions" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_step" value="3">
                        <input type="hidden" name="_action" value="continue">
                        <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=2"
                           class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                            &larr; Back
                        </a>
                        <button type="submit" class="btn btn-primary"
                                <?= $fabricCount === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>>
                            Continue &rarr;
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ─── STEP 4: Price tables ─────────────────────────────── -->
            <?php if ($step === 4 && $product):
                $totalTables  = count($priceTables);
                $filledTables = 0;
                foreach ($priceTables as $t) {
                    if ((int) $t['cell_count'] > 0) $filledTables++;
                }
                $allFilled = $totalTables > 0 && $filledTables === $totalTables;
            ?>
                <?php if ($allFilled): ?>
                    <div class="wiz-done-tile">
                        <div class="icon">🎉</div>
                        <h2>All set — <?= e((string) $product['name']) ?> is ready to quote</h2>
                        <p>
                            Product, <?= (int) $systemCount ?>
                            system<?= $systemCount === 1 ? '' : 's' ?>,
                            <?= (int) $fabricCount ?>
                            <?= strtolower((string) ($product['option_label'] ?? 'fabric')) ?><?= $fabricCount === 1 ? '' : 's' ?>,
                            and all <?= (int) $totalTables ?>
                            price table<?= $totalTables === 1 ? '' : 's' ?> filled in.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="wiz-done-tile" style="background:linear-gradient(135deg,#fef3c7 0%,#fffbeb 100%);border-color:#fcd34d">
                        <div class="icon">📋</div>
                        <h2 style="color:#78350f">One thing left — price tables</h2>
                        <p style="color:#92400e">
                            <?php if ($totalTables === 0 && !$requiresOption): ?>
                                This is a no-fabric product, so it needs one price
                                grid per system. Create them below, then fill in the
                                width × drop prices.
                            <?php elseif ($totalTables === 0 && empty($missingCombos)): ?>
                                Your fabrics don't have band codes yet — go back
                                to step 3 and add at least one band (A, B, C…).
                                Price tables are keyed by band, so we need that
                                first.
                            <?php elseif ($totalTables === 0 && $pricePerDrop): ?>
                                Import your rate sheet below (quickest), or create the
                                empty tables and enter the rates by hand.
                            <?php elseif ($totalTables === 0): ?>
                                Create the price tables below — one per band × system —
                                then fill in the prices.
                            <?php else: ?>
                                <?= $filledTables ?> of <?= $totalTables ?> filled in.
                                Click <em>Fill in</em> on each to type or paste
                                prices straight from a supplier sheet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($widthOnly): ?>
                    <!-- Width-only products are easiest to fill via the
                         width-price importer (one width → price list per
                         system) rather than the 2-D grid editor. -->
                    <div class="wiz-card" style="background:#eff6ff;border-color:#bfdbfe">
                        <h2 style="color:#1e40af">Priced by width — import the price lists</h2>
                        <p class="lede" style="color:#1e40af">
                            This product is priced on width alone. Upload your width &rarr;
                            price spreadsheet (one block per system) and we'll fill every
                            system's price list in one go.
                        </p>
                        <a href="/admin/products/price-import-width.php?product_id=<?= (int) $productId ?>"
                           class="btn btn-primary">Import width prices &rarr;</a>
                    </div>
                <?php endif; ?>

                <?php if ($pricePerDrop): ?>
                    <!-- Per-slat products: import the rate workbook (drop →
                         price-per-slat per system + band) rather than hand-
                         entering the tables. The importer creates them. -->
                    <div class="wiz-card" style="background:#eff6ff;border-color:#bfdbfe">
                        <h2 style="color:#1e40af">Priced per slat — import the rates</h2>
                        <p class="lede" style="color:#1e40af">
                            Upload your rate spreadsheet (a drop &rarr; price-per-slat grid
                            per band, with Chains / Chainless sub-tables) and we'll create and
                            fill every system + band table in one go — no need to create the
                            empty tables first.
                        </p>
                        <a href="/admin/products/price-import-rates.php?product_id=<?= (int) $productId ?>"
                           class="btn btn-primary">Import rates &rarr;</a>
                    </div>
                <?php endif; ?>

                <?php if (!$widthOnly && !$pricePerDrop && empty($perSqm) && $systems): ?>
                    <!-- Normal width × drop products: bulk-import a price grid
                         per system (all its bands in one file) rather than hand-
                         filling each table. The importer creates the tables. -->
                    <div class="wiz-card" style="background:#eff6ff;border-color:#bfdbfe">
                        <h2 style="color:#1e40af">Got a price spreadsheet? Import it</h2>
                        <p class="lede" style="color:#1e40af">
                            Upload your width &times; drop price grid for a system (all its
                            bands in one file) and we'll create and fill its tables in one
                            go &mdash; no need to create the empty tables first. Do each
                            system in turn.
                        </p>
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                            <?php foreach ($systems as $s): ?>
                                <a href="/admin/products/price-tables-bulk-import.php?system_id=<?= (int) $s['id'] ?>"
                                   class="btn btn-primary">Import <?= e((string) $s['name']) ?> &rarr;</a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($missingCombos)): ?>
                    <div class="wiz-card" style="background:#fffbeb;border-color:#fcd34d">
                        <h2 style="color:#78350f">
                            <?= count($missingCombos) === 1
                                ? '1 price table needs setting up'
                                : count($missingCombos) . ' price tables need setting up' ?>
                        </h2>
                        <p class="lede" style="color:#92400e">
                            <?php if (!$requiresOption): ?>
                                Each system needs its own price grid. We've spotted
                                <?= count($missingCombos) === 1 ? 'this system' : 'these systems' ?>
                                without one. Clicking <em>Create</em> below makes an empty
                                price table for each — you'll fill in the actual width × drop
                                prices afterwards.
                            <?php else: ?>
                                Each <?= e($labelL) ?> band on each system needs its own
                                price grid. We've spotted these
                                <strong>(system + band) combination<?= count($missingCombos) === 1 ? '' : 's' ?></strong>
                                without one. Clicking <em>Create</em> below makes an empty
                                price table for each — you'll fill in the actual width × drop
                                prices afterwards.
                            <?php endif; ?>
                        </p>
                        <div class="wiz-list" style="background:transparent;border:0;padding:0;margin-bottom:0.875rem">
                            <?php foreach ($missingCombos as $m): ?>
                                <div class="wiz-list-item" style="border-bottom-color:#fde68a">
                                    <strong><?= e((string) $m['system_name']) ?></strong>
                                    <?php if ($requiresOption): ?>
                                        <span style="color:#92400e">+ Band <?= e((string) $m['band_code']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_step" value="4">
                            <input type="hidden" name="_action" value="create_missing">
                            <button type="submit" class="btn btn-primary">
                                <?php if (count($missingCombos) === 1): ?>
                                    Create the empty price table
                                <?php else: ?>
                                    Create all <?= count($missingCombos) ?> empty price tables
                                <?php endif; ?>
                            </button>
                            <span style="color:var(--text-faint);font-size:0.8125rem;margin-left:0.625rem;line-height:1.4;display:inline-block">
                                One click — the empty grids appear instantly. Leave any
                                out that don't apply to what you sell.
                            </span>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($totalTables > 0): ?>
                    <div class="wiz-card">
                        <h2>Price tables</h2>
                        <p class="lede">
                            One price grid per system + band. Empty tables won't
                            generate a price at quote time — fill them in now,
                            or come back via the product edit page later.
                        </p>

                        <div class="wiz-list" style="max-height:none;padding:0.25rem 0.5rem">
                            <?php foreach ($priceTables as $t):
                                $cells   = (int) $t['cell_count'];
                                $filled  = $cells > 0;
                                $href    = '/admin/products/price-table.php?id=' . (int) $t['id']
                                         . '&from=wizard&product_id=' . (int) $productId;
                            ?>
                                <div class="wiz-list-item" style="padding:0.5rem 0.25rem">
                                    <?php if ($filled): ?>
                                        <span style="color:#16a34a;font-weight:700;font-size:1rem">&check;</span>
                                    <?php else: ?>
                                        <span style="background:#fef3c7;color:#92400e;font-size:0.625rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;padding:0.125rem 0.4375rem;border-radius:999px;line-height:1.4">
                                            Empty
                                        </span>
                                    <?php endif; ?>
                                    <div style="flex:1;display:flex;flex-wrap:wrap;gap:0.375rem;align-items:baseline">
                                        <strong><?= e((string) $t['system_name']) ?></strong>
                                        <?php if ($requiresOption || (string) $t['band_code'] !== ''): ?>
                                            <span style="color:var(--text-faint)">— Band <?= e((string) $t['band_code']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($filled): ?>
                                            <span style="color:var(--text-faint);font-size:0.8125rem">
                                                · <?= $cells ?> cell<?= $cells === 1 ? '' : 's' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= e($href) ?>"
                                       class="btn <?= $filled ? 'btn-secondary' : 'btn-primary' ?> btn-sm">
                                        <?= $filled ? 'Edit' : 'Fill in' ?>
                                    </a>
                                    <form method="post" action="/admin/products/price-table-delete.php"
                                          style="margin:0;display:inline"
                                          data-confirm="Delete the <?= e((string) $t['system_name']) ?> &mdash; Band <?= e((string) $t['band_code']) ?> price table? <?= $filled ? 'Its ' . $cells . ' price cells will be wiped too.' : '' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="return_to"
                                               value="/admin/products/wizard.php?id=<?= (int) $productId ?>&amp;step=4">
                                        <button type="submit"
                                                style="background:transparent;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="wiz-actions" style="margin-top:0">
                    <a href="/admin/products/wizard.php?id=<?= (int) $productId ?>&step=3"
                       class="wiz-skip" style="text-decoration:none;color:var(--text-faint)">
                        &larr; Back to fabrics
                    </a>
                    <a href="/admin/products/edit.php?id=<?= (int) $productId ?>"
                       class="btn <?= $allFilled ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $allFilled
                            ? 'Open product edit page →'
                            : 'Finish later — open product →' ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../../_partials/confirm_modal.php'; ?>
</body>
</html>
