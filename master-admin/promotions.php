<?php
declare(strict_types=1);

/**
 * Master Admin: Promotions — time-boxed buying discounts (trade_promotions).
 *
 * Finer + time-limited vs the standing per-account discounts on the Trade
 * Account page: a promotion is a % off a product (optionally one system + one
 * material-group/band), for a date window, for ALL accounts (global) or one.
 * The pricing engine reads these live alongside the standing discounts,
 * best-discount-wins. Products are chosen from OUR master catalogue so a global
 * promotion reaches every account's mirrored copy.
 *
 * Includes a conflict detector: overlapping active promotions are flagged (the
 * price still resolves best-wins, but you're warned). Super-admin only.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/band_sort.php';

requireSuperAdmin();

$user     = current_user();
$pdo      = db();
$myClient = (int) $user['client_id'];   // the master catalogue owner (Beverley)

$ready = false;
try { $pdo->query('SELECT 1 FROM trade_promotions LIMIT 0'); $ready = true; }
catch (Throwable $e) { $ready = false; }

// ── POST ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'promo_add') {
            $name   = trim((string) ($_POST['name'] ?? ''));
            $pid    = (int) ($_POST['product_id'] ?? 0);
            $sid    = (int) ($_POST['system_id'] ?? 0);      // 0 = All
            $band   = trim((string) ($_POST['band_code'] ?? ''));
            $target = (string) ($_POST['target'] ?? 'product');   // 'product' | 'extra'
            $eid    = (int) ($_POST['extra_id'] ?? 0);
            $chid   = (int) ($_POST['choice_id'] ?? 0);      // 0 = all choices
            $pct    = max(0.0, min(100.0, (float) ($_POST['discount_percent'] ?? 0)));
            $start  = trim((string) ($_POST['starts_on'] ?? ''));
            $end    = trim((string) ($_POST['ends_on'] ?? ''));
            $global = !empty($_POST['acct_global']);
            $selIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($_POST['client_ids'] ?? [])),
                static fn ($n) => $n > 0
            )));

            $hasExtraCol = false;
            try { $cec = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trade_promotions' AND COLUMN_NAME = 'extra_id' LIMIT 1"); $cec->execute(); $hasExtraCol = $cec->fetchColumn() !== false; } catch (Throwable $e) {}
            $isExtra = ($target === 'extra' && $hasExtraCol);

            // Product must be one of OUR master products.
            $pc = $pdo->prepare('SELECT 1 FROM products WHERE id = ? AND client_id = ? LIMIT 1');
            $pc->execute([$pid, $myClient]);
            $productOk = (bool) $pc->fetchColumn();

            // Validate the option/choice for a Components promotion.
            $extraId = null; $choiceId = null; $extraErr = null;
            if ($isExtra) {
                $ec = $pdo->prepare('SELECT 1 FROM product_extras WHERE id = ? AND product_id = ? AND client_id = ? LIMIT 1');
                $ec->execute([$eid, $pid, $myClient]);
                if ($eid <= 0 || !$ec->fetchColumn()) {
                    $extraErr = 'Pick an option that belongs to this product.';
                } else {
                    $extraId = $eid;
                    if ($chid > 0) {
                        $cc = $pdo->prepare('SELECT 1 FROM product_extra_choices WHERE id = ? AND product_extra_id = ? LIMIT 1');
                        $cc->execute([$chid, $eid]);
                        if ($cc->fetchColumn()) $choiceId = $chid;
                    }
                }
            }

            if (!$productOk) {
                $_SESSION['flash_error'] = 'Pick a product from the master catalogue.';
            } elseif ($start !== '' && $end !== '' && $end < $start) {
                $_SESSION['flash_error'] = 'The end date is before the start date.';
            } elseif (!$global && !$selIds) {
                $_SESSION['flash_error'] = 'Tick "All accounts", or choose at least one account.';
            } elseif ($extraErr !== null) {
                $_SESSION['flash_error'] = $extraErr;
            } else {
                // System/band apply to the base price only, never to a Components
                // promotion (an extra is priced on its own axis).
                $systemId = null; $bandCode = null;
                if (!$isExtra) {
                    if ($sid > 0) {
                        $sc = $pdo->prepare('SELECT 1 FROM product_systems WHERE id = ? AND product_id = ? AND client_id = ? LIMIT 1');
                        $sc->execute([$sid, $pid, $myClient]);
                        if ($sc->fetchColumn()) $systemId = $sid;
                    }
                    $bandCode = ($band === '' || strcasecmp($band, 'all') === 0) ? null : $band;
                }

                // Targets: one promotion row per account (NULL = global). "All
                // accounts" ticked wins and collapses to a single global row.
                $targets = [];
                if ($global) {
                    $targets = [null];
                } else {
                    $ph2 = implode(',', array_fill(0, count($selIds), '?'));
                    $vc  = $pdo->prepare("SELECT id FROM clients WHERE id IN ($ph2)");
                    $vc->execute($selIds);
                    $targets = array_map('intval', $vc->fetchAll(PDO::FETCH_COLUMN));
                }

                if (!$targets) {
                    $_SESSION['flash_error'] = 'None of the chosen accounts were found.';
                } else {
                    $ins = $hasExtraCol
                        ? $pdo->prepare('INSERT INTO trade_promotions (name, client_id, product_id, system_id, band_code, extra_id, choice_id, discount_percent, starts_on, ends_on, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
                        : $pdo->prepare('INSERT INTO trade_promotions (name, client_id, product_id, system_id, band_code, discount_percent, starts_on, ends_on, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
                    $nameVal = $name !== '' ? mb_substr($name, 0, 150) : null;
                    $sVal    = $start !== '' ? $start : null;
                    $eVal    = $end   !== '' ? $end   : null;
                    $by      = (int) ($user['user_id'] ?? 0) ?: null;
                    foreach ($targets as $t) {
                        if ($hasExtraCol) {
                            $ins->execute([$nameVal, $t, $pid, $systemId, $bandCode, $extraId, $choiceId, $pct, $sVal, $eVal, $by]);
                        } else {
                            $ins->execute([$nameVal, $t, $pid, $systemId, $bandCode, $pct, $sVal, $eVal, $by]);
                        }
                    }
                    $n = count($targets);
                    $_SESSION['flash_success'] = $global
                        ? 'Promotion added (all accounts).'
                        : 'Promotion added to ' . $n . ' account' . ($n === 1 ? '' : 's') . '.';
                }
            }
        } elseif ($action === 'promo_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM trade_promotions WHERE id = ?')->execute([$id]);
            $_SESSION['flash_success'] = 'Promotion removed.';
        } elseif ($action === 'promo_toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $to = (int) ($_POST['to'] ?? 0) === 1 ? 1 : 0;
            $pdo->prepare('UPDATE trade_promotions SET active = ? WHERE id = ?')->execute([$to, $id]);
            $_SESSION['flash_success'] = $to ? 'Promotion activated.' : 'Promotion paused.';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not save: ' . $e->getMessage();
    }
    header('Location: /master-admin/promotions.php');
    exit;
}

$flashMsg = $_SESSION['flash_success'] ?? null;
$flashErr = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Components (extras) promotions available? (extra_id column present)
$tpHasExtra = false;
if ($ready) {
    try {
        $ce = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trade_promotions' AND COLUMN_NAME = 'extra_id' LIMIT 1");
        $ce->execute();
        $tpHasExtra = $ce->fetchColumn() !== false;
    } catch (Throwable $e) { /* base-only */ }
}

// ── Data ─────────────────────────────────────────────────────────────────────
$products = [];      // master products: id => name
$prodBands = [];     // id => [bands]
$prodSystems = [];   // id => [{id,name}]
$prodExtras = [];    // product_id => [{id,name}]  (options)
$extraChoices = [];  // extra_id  => [{id,label}]
$accounts = [];      // clients for the scope dropdown
$promos = [];
if ($ready) {
    try {
        $ps = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? ORDER BY sort_order, name');
        $ps->execute([$myClient]);
    } catch (Throwable $e) {
        $ps = $pdo->prepare('SELECT id, name FROM products WHERE client_id = ? ORDER BY name');
        $ps->execute([$myClient]);
    }
    foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $p) $products[(int) $p['id']] = (string) $p['name'];

    if ($products) {
        $pids = array_keys($products);
        $ph   = implode(',', array_fill(0, count($pids), '?'));
        try {
            $bs = $pdo->prepare(
                "SELECT product_id, band_code FROM (
                    SELECT product_id, band_code FROM product_options WHERE client_id = ? AND product_id IN ($ph)
                    UNION
                    SELECT product_id, band_code FROM price_tables   WHERE client_id = ? AND product_id IN ($ph)
                 ) x WHERE band_code IS NOT NULL AND band_code <> ''"
            );
            $bs->execute(array_merge([$myClient], $pids, [$myClient], $pids));
            $tmp = [];
            foreach ($bs->fetchAll(PDO::FETCH_ASSOC) as $r) $tmp[(int) $r['product_id']][(string) $r['band_code']] = true;
            $bandCmp = static function (string $a, string $b): int {
                $aA = (bool) preg_match('/^A+$/i', $a); $bA = (bool) preg_match('/^A+$/i', $b);
                if ($aA && $bA) return strlen($b) <=> strlen($a);
                if ($aA !== $bA) return $aA ? -1 : 1;
                return strcmp($a, $b);
            };
            foreach ($tmp as $pid => $set) { $list = array_keys($set); usort($list, $bandCmp); $prodBands[$pid] = $list; }
        } catch (Throwable $e) { /* leave empty */ }
        try {
            $ss = $pdo->prepare("SELECT id, product_id, name FROM product_systems WHERE client_id = ? AND product_id IN ($ph) AND active = 1 ORDER BY sort_order, name");
            $ss->execute(array_merge([$myClient], $pids));
            foreach ($ss->fetchAll(PDO::FETCH_ASSOC) as $r) $prodSystems[(int) $r['product_id']][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
        } catch (Throwable $e) { /* leave empty */ }

        // Options (extras) + their choices — for Components promotions.
        if ($tpHasExtra) {
            try {
                $es = $pdo->prepare("SELECT id, product_id, name FROM product_extras WHERE client_id = ? AND product_id IN ($ph) AND active = 1 ORDER BY sort_order, name");
                $es->execute(array_merge([$myClient], $pids));
                $extraIds = [];
                foreach ($es->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $prodExtras[(int) $r['product_id']][] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
                    $extraIds[] = (int) $r['id'];
                }
                if ($extraIds) {
                    $eph = implode(',', array_fill(0, count($extraIds), '?'));
                    $cs = $pdo->prepare("SELECT id, product_extra_id, label FROM product_extra_choices WHERE product_extra_id IN ($eph) AND active = 1 ORDER BY sort_order, label");
                    $cs->execute($extraIds);
                    foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $r) $extraChoices[(int) $r['product_extra_id']][] = ['id' => (int) $r['id'], 'label' => (string) $r['label']];
                }
            } catch (Throwable $e) { /* leave empty */ }
        }
    }

    $accounts = $pdo->query('SELECT id, company_name FROM clients ORDER BY company_name')->fetchAll(PDO::FETCH_ASSOC);
    $accName  = [];
    foreach ($accounts as $a) $accName[(int) $a['id']] = (string) $a['company_name'];

    $extraSel  = $tpHasExtra ? ', pe.name AS extra_name, pec.label AS choice_label' : '';
    $extraJoin = $tpHasExtra
        ? ' LEFT JOIN product_extras pe ON pe.id = tp.extra_id LEFT JOIN product_extra_choices pec ON pec.id = tp.choice_id'
        : '';
    $promos = $pdo->query(
        "SELECT tp.*, p.name AS product_name, s.name AS system_name$extraSel
           FROM trade_promotions tp
      LEFT JOIN products p        ON p.id = tp.product_id
      LEFT JOIN product_systems s ON s.id = tp.system_id$extraJoin
       ORDER BY tp.active DESC, tp.ends_on IS NULL, tp.ends_on, tp.product_id"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ── Conflict detection (overlapping active promotions) ───────────────────────
$today = date('Y-m-d');
$overlap = static function (?string $as, ?string $ae, ?string $bs, ?string $be): bool {
    $as = $as ?: '0000-01-01'; $ae = $ae ?: '9999-12-31';
    $bs = $bs ?: '0000-01-01'; $be = $be ?: '9999-12-31';
    return $as <= $be && $bs <= $ae;
};
$conflicts = [];   // id => [ids it conflicts with]
$active = array_values(array_filter($promos, static fn ($p) => (int) $p['active'] === 1));
for ($i = 0; $i < count($active); $i++) {
    for ($j = $i + 1; $j < count($active); $j++) {
        $a = $active[$i]; $b = $active[$j];
        if ((int) $a['product_id'] !== (int) $b['product_id']) continue;
        // A Components (extra) promotion and a base-price promotion never
        // collide — they discount different things.
        $aExtra = $tpHasExtra && !empty($a['extra_id']);
        $bExtra = $tpHasExtra && !empty($b['extra_id']);
        if ($aExtra !== $bExtra) continue;
        if ($aExtra) {
            if ((int) $a['extra_id'] !== (int) $b['extra_id']) continue;   // different options
            $targetOk = (empty($a['choice_id']) || empty($b['choice_id']) || (int) $a['choice_id'] === (int) $b['choice_id']);
        } else {
            $sysOk    = ($a['system_id'] === null || $b['system_id'] === null || (int) $a['system_id'] === (int) $b['system_id']);
            $bandOk   = (($a['band_code'] ?? '') === '' || ($b['band_code'] ?? '') === '' || (string) $a['band_code'] === (string) $b['band_code']);
            $targetOk = $sysOk && $bandOk;
        }
        $scopeOk = ($a['client_id'] === null || $b['client_id'] === null || (int) $a['client_id'] === (int) $b['client_id']);
        $dateOk  = $overlap($a['starts_on'], $a['ends_on'], $b['starts_on'], $b['ends_on']);
        if ($targetOk && $scopeOk && $dateOk) {
            $conflicts[(int) $a['id']][] = (int) $b['id'];
            $conflicts[(int) $b['id']][] = (int) $a['id'];
        }
    }
}

$activeNav = 'promotions';
$fmtDate = static fn (?string $d): string => $d ? date('j M Y', strtotime($d)) : '—';
$statusOf = static function (array $p) use ($today): array {
    if ((int) $p['active'] !== 1) return ['Paused', 'var(--text-faint)'];
    if ($p['starts_on'] && $p['starts_on'] > $today) return ['Upcoming', '#1e40af'];
    if ($p['ends_on'] && $p['ends_on'] < $today)     return ['Expired', 'var(--text-faint)'];
    return ['Live', '#065f46'];
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promotions &middot; Master admin</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .action-row { display:flex; gap:0.4rem; align-items:flex-end; flex-wrap:wrap; }
        .action-row .lbl { font-size:0.72rem; color:var(--text-faint); font-weight:700; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.2rem; }
        .action-row input, .action-row select { padding:0.4rem 0.55rem; border:1px solid var(--border-strong); border-radius:7px; font:inherit; background:var(--bg-input); }
        .btn-sm { font-size:0.8125rem; padding:0.35rem 0.75rem; }
        .pill { display:inline-block; padding:0.05rem 0.5rem; font-size:0.7rem; font-weight:700; border-radius:999px; }
        .conflict { background:#fffbeb; }
        .conflict-tag { display:inline-block; margin-left:0.4rem; padding:0.05rem 0.45rem; font-size:0.68rem; font-weight:700; border-radius:999px; background:#fef3c7; color:#92400e; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/../_partials/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">Promotions</h1>
                <p class="page-subtitle">
                    <a href="/master-admin/index.php">&larr; Master Admin</a>
                    &middot; time-boxed buying discounts across accounts, on top of standing account discounts (best-wins).
                </p>
            </div>
        </div>

        <?php if ($flashMsg !== null): ?><div class="alert alert-success" role="status"><?= e((string) $flashMsg) ?></div><?php endif; ?>
        <?php if ($flashErr !== null): ?><div class="alert alert-error" role="alert"><?= e((string) $flashErr) ?></div><?php endif; ?>

        <?php if (!$ready): ?>
            <section class="section">
                <div class="alert alert-error" role="alert">
                    The promotions table isn't set up yet — run
                    <a href="/migrate_trade_promotions.php"><code>/migrate_trade_promotions.php</code></a> (super-admin), then reload.
                </div>
            </section>
        <?php else: ?>

        <?php if ($conflicts): ?>
            <div class="alert" role="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#78350f">
                <strong>&#9888; Overlapping promotions.</strong>
                <?= count($conflicts) ?> promotion<?= count($conflicts) === 1 ? '' : 's' ?> overlap another that could apply to the
                same line (same product + scope, within the same dates). The price still resolves to the <strong>largest</strong> applicable % (best-wins),
                but you may want to tidy these up. Overlapping rows are highlighted below.
            </div>
        <?php endif; ?>

        <!-- Add a promotion -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.75rem">New promotion</h2>
            <form method="post" action="/master-admin/promotions.php">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="promo_add">

                <div style="margin-bottom:0.85rem">
                    <div class="lbl" style="margin-bottom:0.3rem">Accounts</div>
                    <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:600;margin-bottom:0.45rem;cursor:pointer">
                        <input type="checkbox" id="acct-global" name="acct_global" value="1"> All accounts (global)
                    </label>
                    <div id="acct-picker">
                        <div style="display:flex;gap:0.6rem;align-items:center;margin-bottom:0.4rem;flex-wrap:wrap">
                            <input type="search" id="acct-search" placeholder="Filter accounts&hellip;" autocomplete="off"
                                   style="flex:1 1 16rem;max-width:22rem;padding:0.35rem 0.55rem;border:1px solid var(--border-strong);border-radius:7px;font:inherit;background:var(--bg-input)">
                            <span id="acct-count" style="font-size:0.8125rem;color:var(--text-faint)">0 selected</span>
                            <button type="button" id="acct-clear" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0" hidden>Clear</button>
                        </div>
                        <div id="acct-list" style="display:flex;flex-wrap:wrap;gap:0.4rem 1.25rem;max-height:10rem;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:0.55rem 0.75rem;background:var(--bg-card)">
                            <?php foreach ($accounts as $a): if ((int) $a['id'] === $myClient) continue; ?>
                                <label class="acct-item" style="display:inline-flex;align-items:center;gap:0.35rem;font-size:0.875rem;cursor:pointer">
                                    <input type="checkbox" class="acct-cb" name="client_ids[]" value="<?= (int) $a['id'] ?>">
                                    <?= e((string) $a['company_name']) ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (count($accounts) <= 1): ?><span style="color:var(--text-faint);font-size:0.8125rem">No other accounts yet.</span><?php endif; ?>
                        </div>
                    </div>
                    <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.35rem 0 0">Tick <strong>All accounts</strong> for a global promotion, or filter and pick one or more.</p>
                </div>

                <div class="action-row">
                    <div><div class="lbl">Name (optional)</div><input type="text" name="name" maxlength="150" placeholder="Autumn Verticals" style="min-width:12rem"></div>
                    <div><div class="lbl">Discount %</div><input type="number" name="discount_percent" step="0.01" min="0" max="100" required placeholder="20" style="width:6rem"></div>
                    <div>
                        <div class="lbl">Product</div>
                        <select id="pr-product" name="product_id" required style="min-width:14rem">
                            <option value="">— choose —</option>
                            <?php foreach ($products as $pid => $pname): ?>
                                <option value="<?= (int) $pid ?>"><?= e((string) $pname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($tpHasExtra): ?>
                    <div>
                        <div class="lbl">Applies to</div>
                        <select id="tp-target" name="target" style="min-width:11rem">
                            <option value="product">Product price</option>
                            <option value="extra">An option (extra)</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div id="tp-sys-wrap"><div class="lbl">System</div><select id="pr-system" name="system_id" style="min-width:9rem"><option value="">All</option></select></div>
                    <div id="tp-band-wrap"><div class="lbl">Material Group</div><select id="pr-band" name="band_code" style="min-width:8rem"><option value="">All</option></select></div>
                    <?php if ($tpHasExtra): ?>
                    <div id="tp-extra-wrap" hidden><div class="lbl">Option</div><select id="pr-extra" name="extra_id" style="min-width:12rem"><option value="">— choose option —</option></select></div>
                    <div id="tp-choice-wrap" hidden><div class="lbl">Choice</div><select id="pr-choice" name="choice_id" style="min-width:11rem"><option value="">All choices</option></select></div>
                    <?php endif; ?>
                    <div><div class="lbl">Start</div><input type="date" name="starts_on"></div>
                    <div><div class="lbl">End</div><input type="date" name="ends_on"></div>
                    <button type="submit" class="btn btn-primary btn-sm">Add promotion</button>
                </div>
                <p style="color:var(--text-faint);font-size:0.8125rem;margin:0.5rem 0 0">
                    Leave dates blank for open-ended. Products come from your master catalogue; a global promotion reaches every
                    account's copy automatically.
                </p>
            </form>
        </section>

        <!-- Promotions list -->
        <section class="section">
            <h2 class="section-title" style="margin:0 0 0.5rem">Promotions (<?= count($promos) ?>)</h2>
            <?php if (!$promos): ?>
                <p style="color:var(--text-faint);margin:0">No promotions yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Status</th><th style="text-align:right">%</th><th>Product</th><th>Applies to</th><th>Accounts</th><th>Runs</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promos as $p):
                                [$slabel, $scolour] = $statusOf($p);
                                $hasConflict = isset($conflicts[(int) $p['id']]);
                                if ($tpHasExtra && !empty($p['extra_id'])) {
                                    $appliesTo = 'Option: ' . (string) ($p['extra_name'] ?? ('#' . (int) $p['extra_id']));
                                    if (!empty($p['choice_id'])) $appliesTo .= ' → ' . (string) ($p['choice_label'] ?? ('#' . (int) $p['choice_id']));
                                } else {
                                    $sy = ($p['system_name'] ?? '') !== '' ? (string) $p['system_name'] : 'All';
                                    $bd = ($p['band_code']  ?? '') !== '' ? (string) $p['band_code']  : 'All';
                                    $appliesTo = 'Price · ' . $sy . ' · ' . $bd;
                                }
                            ?>
                                <tr class="<?= $hasConflict ? 'conflict' : '' ?>">
                                    <td>
                                        <span class="pill" style="background:<?= $scolour === 'var(--text-faint)' ? 'var(--bg-subtle-2)' : ($scolour === '#065f46' ? '#d1fae5' : '#dbeafe') ?>;color:<?= $scolour ?>"><?= e($slabel) ?></span>
                                        <?php if ($hasConflict): ?><span class="conflict-tag" title="Overlaps another promotion">overlap</span><?php endif; ?>
                                    </td>
                                    <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((float) $p['discount_percent'], 2) ?></td>
                                    <td><?= e((string) ($p['product_name'] ?? ('#' . (int) $p['product_id']))) ?><?php if (($p['name'] ?? '') !== ''): ?><br><span style="color:var(--text-faint);font-size:0.8125rem"><?= e((string) $p['name']) ?></span><?php endif; ?></td>
                                    <td><?= e($appliesTo) ?></td>
                                    <td><?= $p['client_id'] === null ? 'All (global)' : e($accName[(int) $p['client_id']] ?? ('#' . (int) $p['client_id'])) ?></td>
                                    <td style="white-space:nowrap"><?= e($fmtDate($p['starts_on'])) ?> &ndash; <?= e($fmtDate($p['ends_on'])) ?></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <form method="post" action="/master-admin/promotions.php" style="display:inline;margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="promo_toggle">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="to" value="<?= (int) $p['active'] === 1 ? 0 : 1 ?>">
                                            <button type="submit" style="background:none;border:0;color:var(--link);cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0"><?= (int) $p['active'] === 1 ? 'Pause' : 'Activate' ?></button>
                                        </form>
                                        <form method="post" action="/master-admin/promotions.php" style="display:inline;margin:0 0 0 0.6rem"
                                              data-confirm="Delete this promotion (<?= e(number_format((float) $p['discount_percent'], 2)) ?>% on <?= e((string) ($p['product_name'] ?? '')) ?>)?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_action" value="promo_delete">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" style="background:none;border:0;color:#b91c1c;cursor:pointer;font-size:0.8125rem;text-decoration:underline;padding:0">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>
<?php if ($ready): ?>
<script>
(function () {
    var BANDS   = <?= json_encode($prodBands, JSON_UNESCAPED_UNICODE) ?>;
    var SYSTEMS = <?= json_encode($prodSystems, JSON_UNESCAPED_UNICODE) ?>;
    // "All accounts" vs a filterable multi-select of individual accounts.
    var acctGlobal = document.getElementById('acct-global');
    var acctPicker = document.getElementById('acct-picker');
    var acctList   = document.getElementById('acct-list');
    var acctSearch = document.getElementById('acct-search');
    var acctCount  = document.getElementById('acct-count');
    var acctClear  = document.getElementById('acct-clear');
    if (acctGlobal && acctList) {
        var cbs   = Array.prototype.slice.call(acctList.querySelectorAll('.acct-cb'));
        var items = Array.prototype.slice.call(acctList.querySelectorAll('.acct-item'));
        function updateCount() {
            var n = cbs.filter(function (c) { return c.checked; }).length;
            if (acctCount) acctCount.textContent = n + ' selected';
            if (acctClear) acctClear.hidden = (n === 0);
        }
        acctGlobal.addEventListener('change', function () {
            if (acctGlobal.checked) { cbs.forEach(function (c) { c.checked = false; }); updateCount(); }
            if (acctPicker) acctPicker.hidden = acctGlobal.checked;   // hide the list when global
        });
        cbs.forEach(function (c) { c.addEventListener('change', function () {
            if (c.checked) acctGlobal.checked = false;
            updateCount();
        }); });
        if (acctSearch) acctSearch.addEventListener('input', function () {
            var q = (acctSearch.value || '').trim().toLowerCase();
            items.forEach(function (it) {
                it.style.display = (q === '' || it.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
        if (acctClear) acctClear.addEventListener('click', function () {
            cbs.forEach(function (c) { c.checked = false; }); updateCount();
        });
        updateCount();
    }

    var EXTRAS  = <?= json_encode($prodExtras, JSON_UNESCAPED_UNICODE) ?>;
    var CHOICES = <?= json_encode($extraChoices, JSON_UNESCAPED_UNICODE) ?>;

    var prod       = document.getElementById('pr-product');
    var band       = document.getElementById('pr-band');
    var sys        = document.getElementById('pr-system');
    var extra      = document.getElementById('pr-extra');
    var choice     = document.getElementById('pr-choice');
    var target     = document.getElementById('tp-target');
    var sysWrap    = document.getElementById('tp-sys-wrap');
    var bandWrap   = document.getElementById('tp-band-wrap');
    var extraWrap  = document.getElementById('tp-extra-wrap');
    var choiceWrap = document.getElementById('tp-choice-wrap');

    function fillChoices() {
        if (!choice) return;
        choice.innerHTML = '<option value="">All choices</option>';
        ((extra && CHOICES[extra.value]) ? CHOICES[extra.value] : []).forEach(function (c) {
            var o = document.createElement('option'); o.value = c.id; o.textContent = c.label; choice.appendChild(o);
        });
    }
    function fillExtras() {
        if (!extra) return;
        extra.innerHTML = '<option value="">— choose option —</option>';
        ((prod && EXTRAS[prod.value]) ? EXTRAS[prod.value] : []).forEach(function (x) {
            var o = document.createElement('option'); o.value = x.id; o.textContent = x.name; extra.appendChild(o);
        });
        fillChoices();
    }
    function applyTarget() {
        var isExtra = target && target.value === 'extra';
        if (sysWrap)    sysWrap.hidden    = isExtra;
        if (bandWrap)   bandWrap.hidden   = isExtra;
        if (extraWrap)  extraWrap.hidden  = !isExtra;
        if (choiceWrap) choiceWrap.hidden = !isExtra;
    }

    if (prod) prod.addEventListener('change', function () {
        if (band) { band.innerHTML = '<option value="">All</option>'; (BANDS[prod.value] || []).forEach(function (b) { var o = document.createElement('option'); o.value = b; o.textContent = b; band.appendChild(o); }); }
        if (sys)  { sys.innerHTML  = '<option value="">All</option>'; (SYSTEMS[prod.value] || []).forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sys.appendChild(o); }); }
        fillExtras();
    });
    if (extra)  extra.addEventListener('change', fillChoices);
    if (target) target.addEventListener('change', applyTarget);
    applyTarget();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../_partials/confirm_modal.php'; ?>
</body>
</html>
