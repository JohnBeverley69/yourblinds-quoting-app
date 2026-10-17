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
        } elseif ($action === 'reset') {
            $value = null;   // back to the placement in billing_plans.php
        } else {
            throw new RuntimeException('Unknown action.');
        }

        $existingCols = db()->query('SHOW COLUMNS FROM client_settings')->fetchAll(PDO::FETCH_COLUMN);
        $cols   = array_values(array_intersect(array_keys($flags), $existingCols));
        $before = pf_flag_snapshot($cols);

        if (!app_setting_set(BILLING_FEATURE_LAYOUT_KEY, $value)) {
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
        $_SESSION['flash_error'] = $e->getMessage();
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
$isCustom = billing_feature_layout() !== [];

$columns = [];
foreach ($plans as $code => $p) {
    $price = (float) ($p['price_gbp_monthly'] ?? 0);
    $columns[(string) $code] = [
        'name' => (string) ($p['name'] ?? $code),
        'sub'  => $price > 0 ? '£' . rtrim(rtrim(number_format($price, 2), '0'), '.') . '/month + VAT' : 'Free — every company',
    ];
}
$columns[''] = ['name' => 'Not in a plan', 'sub' => 'Switched on by hand per company'];
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

        <section class="section">
            <p style="color:var(--text-muted);font-size:0.875rem;margin:0 0 1rem;line-height:1.55">
                Drag each extra onto the <strong>lowest tier</strong> that should have it — every tier above
                includes it too (<?= e(implode(' → ', $tierNames)) ?>). Anything on <strong>Bronze</strong> is free
                for every company. On a phone, use the little <em>Move to</em> box on each extra.
                <br>When you save, every company's features switch on or off to match straight away — moving an
                extra up a tier takes it away from anyone on the tier below (comps and trials count as being on that
                tier). The Billing page and the welcome page describe each tier from this, so they always match.
            </p>

            <form method="post" id="pf-form"
                  data-confirm="Save the tiers? Every company's features switch on or off to match straight away.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="layout" id="pf-layout" value="">

                <div class="pf-board">
                    <?php $prevName = null; foreach ($columns as $code => $col): ?>
                        <div class="pf-col<?= $code === '' ? ' is-none' : '' ?>" data-tier="<?= e((string) $code) ?>">
                            <h3><?= e($col['name']) ?></h3>
                            <div class="pf-sub"><?= e($col['sub']) ?></div>
                            <div class="pf-drop">
                                <?php foreach ($placed as $flag => $at): if ($at !== (string) $code) continue; ?>
                                    <div class="pf-chip" draggable="true" data-flag="<?= e($flag) ?>" data-start="<?= e($at) ?>">
                                        <span class="pf-grip" aria-hidden="true">⠿</span>
                                        <span class="pf-label"><?= e((string) $flags[$flag]) ?></span>
                                        <select aria-label="Move <?= e((string) $flags[$flag]) ?> to">
                                            <?php foreach ($columns as $c2 => $col2): ?>
                                                <option value="<?= e((string) $c2) ?>"<?= (string) $c2 === $at ? ' selected' : '' ?>><?= e($col2['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                                <div class="pf-empty">Drop an extra here</div>
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
        var layout = {};
        board.querySelectorAll('.pf-chip').forEach(function (chip) {
            layout[chip.getAttribute('data-flag')] = chip.closest('.pf-col').getAttribute('data-tier');
        });
        document.getElementById('pf-layout').value = JSON.stringify(layout);
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
        try { e.dataTransfer.setData('text/plain', chip.getAttribute('data-flag')); } catch (err) {}
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
