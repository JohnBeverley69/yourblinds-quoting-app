<?php
declare(strict_types=1);

/**
 * Super-admin: which extras are in which tier — drag and drop (John, 2026-10-17).
 *
 * One column per tier (Bronze → Silver → Gold → Platinum) plus "Not in a plan". Drop an
 * extra on the LOWEST tier that should have it; every tier above includes it
 * automatically (the ladder stays cumulative). Bronze is everyone's.
 *
 * Saved as JSON in app_settings[plan_feature_tiers] (flag => plan code, '' = no
 * plan) — billing_plans() re-cuts each tier's features from it, so the Billing
 * page, welcome page, tour tags and every feature gate follow. Saving re-syncs
 * every company's feature flags straight away (the same sync PayPal events and
 * comps use), so moving an extra up a tier switches it off for anyone below.
 *
 * "Not in a plan" = no tier grants it and the sync leaves it alone; it's then
 * switched on/off per company by hand on Master Admin → Overview (whoever has
 * it at the moment keeps it).
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/billing_helpers.php';
require_once __DIR__ . '/../_partials/app_settings.php';

requireSuperAdmin();

$user  = current_user();
$flags = require __DIR__ . '/../_partials/feature_flags.php';

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/** Every company's managed flag values, for counting what a save changed. */
function pf_flag_snapshot(array $cols): array
{
    if (!$cols) return [];
    $sel = implode(', ', array_map(static fn ($c) => "COALESCE($c, 0) AS $c", $cols));   // allowlisted names
    $out = [];
    foreach (db()->query("SELECT client_id, $sel FROM client_settings")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['client_id']] = $r;
    }
    return $out;
}

require_once __DIR__ . '/../_partials/plan_gate.php';

// The master switch: listed features become real switches (plan_gate.php).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'enforce') {
    csrf_check();
    $on = (string) ($_POST['on'] ?? '') === '1';
    $_SESSION[app_setting_set(PLAN_ENFORCE_KEY, $on ? '1' : '0') ? 'flash_success' : 'flash_error'] = $on
        ? 'Listed features are now switches: companies below a feature\'s tier can\'t use it, and its menu item hides.'
        : 'Listed features are describe-only again: every company can use them.';
    header('Location: /master-admin/plan-features.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $posted = json_decode((string) ($_POST['layout'] ?? ''), true);
            if (!is_array($posted)) throw new RuntimeException('Nothing to save — try again.');
            $plans  = billing_plans();
            $layout = [];
            foreach ($flags as $flag => $_label) {
                $code = (string) ($posted[$flag] ?? '');
                if ($code !== '' && !isset($plans[$code])) throw new RuntimeException('Unknown tier: ' . $code);
                $layout[$flag] = $code;
            }
            $value = json_encode($layout);

            // The LISTED features (describe the plans only — nothing switches).
            $postedCat = json_decode((string) ($_POST['catalogue'] ?? ''), true);
            $catValue  = null;
            if (is_array($postedCat)) {
                $catLayout = [];
                foreach (array_keys(require __DIR__ . '/../_partials/plan_feature_catalogue.php') as $key) {
                    if (!array_key_exists($key, $postedCat)) continue;
                    $code = (string) $postedCat[$key];
                    if ($code !== '' && !isset($plans[$code])) throw new RuntimeException('Unknown tier: ' . $code);
                    $catLayout[$key] = $code;
                }
                $catValue = json_encode($catLayout);
            }
        } elseif ($action === 'reset') {
            $value    = null;   // back to the placement in billing_plans.php
            $catValue = null;   // and plan_feature_catalogue.php
        } else {
            throw new RuntimeException('Unknown action.');
        }

        $existingCols = db()->query('SHOW COLUMNS FROM client_settings')->fetchAll(PDO::FETCH_COLUMN);
        $cols   = array_values(array_intersect(array_keys($flags), $existingCols));
        $before = pf_flag_snapshot($cols);

        if (!app_setting_set(BILLING_FEATURE_LAYOUT_KEY, $value)
            || ($action === 'reset' || $catValue !== null) && !app_setting_set(BILLING_CATALOGUE_KEY, $catValue)) {
            throw new RuntimeException('Could not save — is the app_settings table there?');
        }
        billing_plans(true);   // re-read the new layout for the sync below

        $ids = db()->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $cid) billing_sync_feature_flags_force((int) $cid);

        $after   = pf_flag_snapshot($cols);
        $changed = 0;
        foreach ($after as $cid => $row) {
            if (($before[$cid] ?? null) != $row) $changed++;
        }
        $_SESSION['flash_success'] = ($action === 'reset' ? 'Back to the standard tiers. ' : 'Saved. ')
            . ($changed === 0
                ? 'No company\'s features changed.'
                : $changed . ' compan' . ($changed === 1 ? 'y\'s' : 'ies\'') . ' features were switched on or off to match.');
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = user_error_text($e);
    }
    header('Location: /master-admin/plan-features.php');
    exit;
}

// ---- Page data ------------------------------------------------------
$plans = billing_plans();
uasort($plans, static fn ($a, $b) => (int) ($a['tier'] ?? 0) <=> (int) ($b['tier'] ?? 0));

// Where each extra sits now = the lowest tier that lists it ('' = none).
$placed = [];
foreach ($flags as $flag => $_label) {
    $placed[$flag] = '';
    foreach ($plans as $code => $p) {
        if (in_array($flag, $p['features'] ?? [], true)) { $placed[$flag] = (string) $code; break; }
    }
}
$catalogue = billing_catalogue();   // the LISTED features, with where each sits now
$isCustom  = billing_feature_layout() !== [] || (string) (app_setting_get(BILLING_CATALOGUE_KEY, '') ?? '') !== '';

$columns = [];
foreach ($plans as $code => $p) {
    $price = (float) ($p['price_gbp_monthly'] ?? 0);
    $columns[(string) $code] = [
        'name' => (string) ($p['name'] ?? $code),
        'sub'  => $price > 0 ? '£' . rtrim(rtrim(number_format($price, 2), '0'), '.') . '/month + VAT' : 'Free — every company',
    ];
}
$columns[''] = ['name' => 'Not in a plan', 'sub' => 'Switches: on by hand per company · Listed: not shown'];
$tierNames = array_values(array_map(static fn ($c) => $c['name'], array_slice($columns, 0, -1, true)));

$activeNav = 'plan-features';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plan features &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .pf-board { display: grid; grid-template-columns: repeat(<?= count($columns) ?>, minmax(0, 1fr)); gap: 0.875rem; }
        @media (max-width: 900px) { .pf-board { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 520px) { .pf-board { grid-template-columns: 1fr; } .pf-col { min-height: 0; } }
        .pf-col {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px;
            padding: 0.875rem; display: flex; flex-direction: column; min-height: 14rem;
        }
        .pf-col.is-none { background: var(--bg-subtle); border-style: dashed; }
        .pf-col h3 { margin: 0; font-size: 1rem; }
        .pf-col .pf-sub { font-size: 0.8125rem; color: var(--text-muted); margin: 0.125rem 0 0.75rem; }
        .pf-drop {
            flex: 1; display: flex; flex-direction: column; gap: 0.5rem;
            border-radius: 8px; padding: 0.25rem; transition: background 0.12s, outline-color 0.12s;
            outline: 2px dashed transparent; outline-offset: 2px;
        }
        .pf-col.is-over .pf-drop { background: var(--bg-subtle); outline-color: var(--border-strong); }
        .pf-inherit { font-size: 0.8125rem; color: var(--text-muted); border-top: 1px solid var(--border); padding-top: 0.5rem; margin-top: 0.5rem; }
        .pf-chip {
            display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.5rem; cursor: grab;
            background: var(--bg-input, #fff); border: 1px solid var(--border-strong); border-radius: 8px;
            padding: 0.5rem 0.625rem; font-weight: 600; font-size: 0.9375rem; user-select: none;
        }
        .pf-chip:active { cursor: grabbing; }
        .pf-chip.is-dragging { opacity: 0.4; }
        .pf-chip.is-moved { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15); }
        .pf-chip .pf-grip { color: var(--text-faint); font-size: 0.875rem; }
        .pf-chip span.pf-label { flex: 1; }
        .pf-chip select {
            font: inherit; font-size: 0.75rem; font-weight: 400; padding: 0.125rem 0.25rem;
            border: 1px solid var(--border); border-radius: 6px; background: transparent; width: 100%;
            color: var(--text-muted); cursor: pointer;
        }
        .pf-empty { font-size: 0.8125rem; color: var(--text-faint); text-align: center; padding: 1rem 0.25rem; }
        .pf-tag { font-size: 0.6875rem; font-weight: 700; border-radius: 4px; padding: 0.05rem 0.35rem; }
        .pf-chip.is-switch .pf-tag { background: #fef3c7; color: #92400e; }
        .pf-chip.is-listed { font-weight: 500; font-size: 0.875rem; padding: 0.375rem 0.5rem; border-style: dashed; }
        .pf-chip.is-listed .pf-tag { background: var(--bg-subtle-2, #eef1f6); color: var(--text-muted); }
        .pf-group { font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
                    color: var(--text-faint); margin: 0.25rem 0 0; }
        .pf-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
        .pf-dirty { font-size: 0.875rem; color: #92400e; display: none; }
        .pf-dirty.is-on { display: inline; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>

    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Plan features</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; Which extras come with each tier. Prices are on
                    <a href="/master-admin/pricing.php">Pricing</a>.
                </p>
            </div>
        </div>

        <?php if ($flashMsg): ?>
            <div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div>
        <?php endif; ?>

        <?php
            // Who is on which plan now — so switching enforcement on is never a surprise.
            $enforcing = plan_enforcing();
            $onTier = [];
            foreach (db()->query('SELECT id FROM clients')->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                if (function_exists('factory_client_id') && (int) $cid === (int) factory_client_id()) continue;
                $t = billing_current_tier_code((int) $cid);
                $onTier[$t] = ($onTier[$t] ?? 0) + 1;
            }
        ?>
        <section class="section" style="border:1px solid <?= $enforcing ? '#86efac' : 'var(--border)' ?>;border-radius:12px;padding:1rem">
            <h2 class="section-title" style="margin:0 0 .35rem">
                Listed features: <?= $enforcing ? '<span style="color:#15803d">real switches (on)</span>' : '<span style="color:var(--text-muted)">describe only (off)</span>' ?>
            </h2>
            <p style="color:var(--text-muted);font-size:.875rem;margin:0 0 .6rem;line-height:1.55">
                When this is <strong>on</strong>, a company below a feature's tier can't use it: its menu item hides and its
                page says which plan has it. People and records they already have keep working (existing staff and
                fitters still sign in). Your own factory and you are never locked.
                <br>Companies on each plan now (comps and paid):
                <?php foreach (billing_plans() as $pc => $pp): ?>
                    <strong><?= e((string) $pp['name']) ?></strong> <?= (int) ($onTier[$pc] ?? 0) ?><?= $pc !== array_key_last(billing_plans()) ? ' &middot;' : '' ?>
                <?php endforeach; ?>
            </p>
            <form method="post" data-confirm="<?= $enforcing
                ? 'Switch enforcement off? Every company can use every listed feature again.'
                : 'Switch enforcement on? Companies below a feature\'s tier lose it straight away.' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="enforce">
                <input type="hidden" name="on" value="<?= $enforcing ? '0' : '1' ?>">
                <button type="submit" class="btn <?= $enforcing ? 'btn-secondary' : 'btn-primary' ?>">
                    <?= $enforcing ? 'Switch enforcement off' : 'Switch enforcement on' ?>
                </button>
            </form>
        </section>

        <section class="section">
            <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 1rem;line-height:1.55">
                Drag each feature onto the <strong>lowest tier</strong> that should have it — every tier above
                includes it too (<?= e(implode(' → ', $tierNames)) ?>). Anything on <strong>Bronze</strong> is free
                for every company. On a phone, use the little <em>Move to</em> box on each one.
                <br>There are two kinds of box. <strong>⚡ Switch</strong> boxes really turn on and off: when you
                save, every company's switches change to match straight away, so moving one up a tier takes it away
                from anyone on the tier below (comps and trials count as being on that tier).
                <strong>Listed</strong> boxes (dashed) only change what each plan <em>says</em> it includes on the
                Billing and welcome pages; nothing is switched on or off by them. Hover over one to see its description.
            </p>

            <form method="post" id="pf-form"
                  data-confirm="Save the tiers? Every company's features switch on or off to match straight away.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="layout" id="pf-layout" value="">
                <input type="hidden" name="catalogue" id="pf-catalogue" value="">

                <div class="pf-board">
                    <?php $prevName = null; foreach ($columns as $code => $col): ?>
                        <div class="pf-col<?= $code === '' ? ' is-none' : '' ?>" data-tier="<?= e((string) $code) ?>">
                            <h3><?= e($col['name']) ?></h3>
                            <div class="pf-sub"><?= e($col['sub']) ?></div>
                            <div class="pf-drop">
                                <?php foreach ($placed as $flag => $at): if ($at !== (string) $code) continue; ?>
                                    <div class="pf-chip is-switch" draggable="true" data-flag="<?= e($flag) ?>" data-start="<?= e($at) ?>"
                                         title="A real switch: it turns on and off with the plan">
                                        <span class="pf-grip" aria-hidden="true">⠿</span>
                                        <span class="pf-label"><?= e((string) $flags[$flag]) ?></span>
                                        <span class="pf-tag">⚡ Switch</span>
                                        <select aria-label="Move <?= e((string) $flags[$flag]) ?> to">
                                            <?php foreach ($columns as $c2 => $col2): ?>
                                                <option value="<?= e((string) $c2) ?>"<?= (string) $c2 === $at ? ' selected' : '' ?>><?= e($col2['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($catalogue as $key => $f): if ($f['code'] !== (string) $code) continue; ?>
                                    <div class="pf-chip is-listed" draggable="true" data-cat="<?= e($key) ?>" data-start="<?= e($f['code']) ?>"
                                         title="<?= e($f['desc']) ?>">
                                        <span class="pf-grip" aria-hidden="true">⠿</span>
                                        <span class="pf-label"><?= e($f['label']) ?></span>
                                        <span class="pf-tag">Listed</span>
                                        <select aria-label="Move <?= e($f['label']) ?> to">
                                            <?php foreach ($columns as $c2 => $col2): ?>
                                                <option value="<?= e((string) $c2) ?>"<?= (string) $c2 === $f['code'] ? ' selected' : '' ?>><?= e($col2['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                                <div class="pf-empty">Drop a feature here</div>
                            </div>
                            <?php if ($code !== '' && $prevName !== null): ?>
                                <div class="pf-inherit">+ everything in <?= e($prevName) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php if ($code !== '') $prevName = $col['name']; endforeach; ?>
                </div>

                <div class="pf-actions">
                    <button type="submit" class="btn btn-primary" id="pf-save">Save tiers</button>
                    <span class="pf-dirty" id="pf-dirty">Unsaved changes</span>
                </div>
            </form>

            <?php if ($isCustom): ?>
                <form method="post" style="margin-top:1.5rem"
                      data-confirm="Put every extra back on its standard tier? Companies' features switch on or off to match.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-secondary">Reset to standard tiers</button>
                    <span style="font-size:0.8125rem;color:var(--text-faint);margin-left:0.5rem">
                        The standard tiers are the ones written into the system.
                    </span>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
(function () {
    var board  = document.querySelector('.pf-board');
    var form   = document.getElementById('pf-form');
    var dirty  = document.getElementById('pf-dirty');
    var drag   = null;

    function colOf(tier) { return board.querySelector('.pf-col[data-tier="' + tier + '"] .pf-drop'); }

    function refresh() {
        var changed = false;
        board.querySelectorAll('.pf-col').forEach(function (col) {
            var drop = col.querySelector('.pf-drop');
            var chips = drop.querySelectorAll('.pf-chip');
            drop.querySelector('.pf-empty').style.display = chips.length ? 'none' : '';
            drop.appendChild(drop.querySelector('.pf-empty'));   // keep the hint last
            chips.forEach(function (chip) {
                var now = col.getAttribute('data-tier');
                chip.querySelector('select').value = now;
                var moved = now !== chip.getAttribute('data-start');
                chip.classList.toggle('is-moved', moved);
                if (moved) changed = true;
            });
        });
        dirty.classList.toggle('is-on', changed);
        // Kept current here, not on submit: the confirm box sends the form
        // with form.submit(), which fires no submit event.
        var layout = {}, catalogue = {};
        board.querySelectorAll('.pf-chip').forEach(function (chip) {
            var tier = chip.closest('.pf-col').getAttribute('data-tier');
            if (chip.hasAttribute('data-flag')) layout[chip.getAttribute('data-flag')] = tier;   // ⚡ switches
            else catalogue[chip.getAttribute('data-cat')] = tier;                                // listed only
        });
        document.getElementById('pf-layout').value = JSON.stringify(layout);
        document.getElementById('pf-catalogue').value = JSON.stringify(catalogue);
    }

    function moveTo(chip, tier) {
        var drop = colOf(tier);
        if (drop) { drop.insertBefore(chip, drop.querySelector('.pf-empty')); refresh(); }
    }

    board.addEventListener('dragstart', function (e) {
        var chip = e.target.closest && e.target.closest('.pf-chip');
        if (!chip) return;
        drag = chip;
        chip.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', chip.getAttribute('data-flag') || chip.getAttribute('data-cat')); } catch (err) {}
    });
    board.addEventListener('dragend', function () {
        if (drag) drag.classList.remove('is-dragging');
        drag = null;
        board.querySelectorAll('.pf-col.is-over').forEach(function (c) { c.classList.remove('is-over'); });
    });
    board.querySelectorAll('.pf-col').forEach(function (col) {
        col.addEventListener('dragover', function (e) {
            if (!drag) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            col.classList.add('is-over');
        });
        col.addEventListener('dragleave', function (e) {
            if (!col.contains(e.relatedTarget)) col.classList.remove('is-over');
        });
        col.addEventListener('drop', function (e) {
            if (!drag) return;
            e.preventDefault();
            col.classList.remove('is-over');
            moveTo(drag, col.getAttribute('data-tier'));
        });
    });
    board.addEventListener('change', function (e) {
        if (e.target.tagName !== 'SELECT') return;
        moveTo(e.target.closest('.pf-chip'), e.target.value);
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty.classList.contains('is-on') && !form.dataset.confirmed) { e.preventDefault(); e.returnValue = ''; }
    });

    refresh();
})();
</script>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
