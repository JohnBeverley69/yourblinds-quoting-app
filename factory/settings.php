<?php
declare(strict_types=1);

/**
 * Factory · Settings.
 *
 * First section: order colour-coding — a colour per order state that tints the
 * rows on Incoming Orders (Blind-Matrix style). Per-factory, editable here.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require __DIR__ . '/../_partials/factory_order_colours.php';

requireFactory();

$pdo    = db();
$MASTER = current_factory_id();

$flashOk = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['reset'])) {
        foc_reset($pdo, $MASTER);
        $_SESSION['flash_success'] = 'Order colours reset to the defaults.';
    } else {
        $in = (array) ($_POST['colour'] ?? []);
        foc_save($pdo, $MASTER, $in);
        $_SESSION['flash_success'] = 'Order colours saved.';
    }
    header('Location: /factory/settings.php');
    exit;
}

$flashOk = (string) ($_SESSION['flash_success'] ?? '');
unset($_SESSION['flash_success']);

$colours = foc_colours($pdo, $MASTER);

// A tiny "when does an order get this colour?" note per state, so it's not a
// mystery which is which.
$FOC_WHEN = [
    'new'           => 'Just come in — not started yet',
    'in_production' => 'On the production floor',
    'ready'         => 'Every blind made — ready to send',
    'dispatched'    => 'Sent out',
    'invoiced'      => 'Invoiced to the customer',
    'paid'          => 'Paid / closed',
];

$factoryTitle = 'Settings';
$factoryNav   = 'settings';
require __DIR__ . '/../_partials/factory_head.php';
?>
<style>
    .fs-h1 { font-size:1.6rem; font-weight:700; margin:0 0 .25rem; letter-spacing:-.01em; }
    .fs-sub { color:var(--text-muted,#667); margin:0 0 1.25rem; }
    .fs-flash { background:#dcfce7; color:#166534; border:1px solid #86efac; padding:.6rem .9rem; border-radius:10px; margin:0 0 1rem; font-size:.9375rem; }
    .fs-card { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); padding:1.1rem 1.25rem; max-width:44rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .fs-card h2 { font-size:1.05rem; margin:0 0 .25rem; }
    .fs-note { color:var(--text-muted,#667); font-size:.875rem; margin:0 0 1rem; }
    .foc-row { display:flex; align-items:center; gap:.9rem; padding:.5rem .6rem; border-radius:10px; margin-bottom:.5rem; }
    .foc-row .foc-when { flex:1; min-width:0; }
    .foc-row .foc-label { font-weight:700; }
    .foc-row .foc-desc { font-size:.8125rem; color:var(--text-muted,#667); }
    .foc-row input[type=color] { width:2.75rem; height:2.25rem; padding:0; border:1px solid var(--border,#cbd5e1); border-radius:8px; background:none; cursor:pointer; }
    .foc-row .foc-hex { font-family:ui-monospace,monospace; font-size:.8125rem; color:var(--text-muted,#667); width:5rem; }
    .fs-actions { display:flex; gap:.6rem; margin-top:1rem; flex-wrap:wrap; }
    .fs-btn { font:inherit; font-size:.9rem; font-weight:600; cursor:pointer; border-radius:8px; padding:.5rem 1rem; border:1px solid var(--border,#d7dee7); }
    .fs-btn.primary { background:#1f2a37; color:#fff; border-color:#1f2a37; }
    .fs-btn.primary:hover { background:#111a24; }
    .fs-btn.secondary { background:var(--bg-card,#fff); color:var(--text-muted,#4b5563); }
    .fs-btn.secondary:hover { background:var(--bg-subtle,#f1f5f9); }
</style>

<h1 class="fs-h1">Settings</h1>
<p class="fs-sub ui-hint">Factory back-office settings.</p>

<?php if ($flashOk !== ''): ?><div class="fs-flash"><?= e($flashOk) ?></div><?php endif; ?>

<div class="fs-card">
    <h2>Order colours</h2>
    <p class="fs-note">Colour-code the orders on <strong>Incoming Orders</strong> by where they are, so the queue reads at a glance. Each order gets a coloured left bar and a faint tint. Pick any colour you like for each stage.</p>

    <form method="post" action="/factory/settings.php">
        <?= csrf_field() ?>
        <?php foreach ($colours as $key => $c): $hex = $c['color']; ?>
            <div class="foc-row" style="<?= e(foc_row_style($hex)) ?>">
                <div class="foc-when">
                    <div class="foc-label"><?= e($c['label']) ?></div>
                    <div class="foc-desc"><?= e($FOC_WHEN[$key] ?? '') ?></div>
                </div>
                <input type="color" name="colour[<?= e($key) ?>]" value="<?= e($hex) ?>"
                       oninput="var s=this.closest('.foc-row'); s.style.borderLeft='4px solid '+this.value; var m=this.value.match(/#(..)(..)(..)/); if(m){s.style.background='rgba('+parseInt(m[1],16)+','+parseInt(m[2],16)+','+parseInt(m[3],16)+',0.16)';} this.nextElementSibling.textContent=this.value;"
                       aria-label="Colour for <?= e($c['label']) ?>">
                <span class="foc-hex"><?= e($hex) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="fs-actions">
            <button type="submit" class="fs-btn primary">Save colours</button>
            <button type="submit" name="reset" value="1" class="fs-btn secondary"
                    onclick="return confirm('Reset all order colours to the defaults?');">Reset to defaults</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../_partials/factory_foot.php'; ?>
