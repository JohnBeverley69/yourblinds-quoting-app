<?php
declare(strict_types=1);

/**
 * Renders one blind card for the floor board / station queue. Expects a joined
 * row (factory_blind_jobs + quotes + quote_items). $returnTo is where the
 * Start/Done/Back forms bounce back to. Defines the function once.
 */

if (!function_exists('bj_render_card')) {
    function bj_render_card(array $r, string $returnTo): void
    {
        $ref     = trim((string) ($r['quote_number'] ?? ('#' . ($r['quote_id'] ?? ''))));
        $tenant  = trim((string) ($r['tenant'] ?? ''));
        $product = trim((string) ($r['product_name_snapshot'] ?? ''));
        $system  = trim((string) ($r['system_name_snapshot'] ?? ''));
        $fabric  = trim((string) ($r['fabric_name_snapshot'] ?? ''));
        $colour  = trim((string) ($r['fabric_colour_snapshot'] ?? ''));
        $room    = trim((string) ($r['room_name'] ?? ''));
        $label   = trim((string) ($r['step_label'] ?? ''));
        $w       = (int) ($r['width_mm'] ?? 0);
        $d       = (int) ($r['drop_mm'] ?? 0);
        $qty     = (int) ($r['quantity'] ?? 1);
        $status  = (string) ($r['status'] ?? 'queued');
        $jobId   = (int) ($r['id'] ?? 0);
        $working = $status === 'in_progress';
        $fabLine = trim($fabric . ($colour !== '' ? ' / ' . $colour : ''));
        $rt      = e($returnTo);
        $tok     = csrf_field();
        ?>
        <div class="bcard<?= $working ? ' working' : '' ?>">
            <div class="bcard-top">
                <span class="bcard-ref"><?= e($ref) ?></span>
                <?php if ($qty > 1): ?><span class="bcard-qty">&times;<?= $qty ?></span><?php endif; ?>
                <?php if ($working): ?><span class="bcard-live">working</span><?php endif; ?>
            </div>
            <div class="bcard-prod"><?= e($product) ?><?php if ($system !== ''): ?> <span class="bcard-sys"><?= e($system) ?></span><?php endif; ?></div>
            <?php if ($fabLine !== ''): ?><div class="bcard-fab"><?= e($fabLine) ?></div><?php endif; ?>
            <div class="bcard-meta">
                <span class="bcard-size"><?= $w ?> &times; <?= $d ?></span>
                <?php if ($room !== ''): ?><span class="bcard-room"><?= e($room) ?></span><?php endif; ?>
            </div>
            <?php if ($label !== '' || $tenant !== ''): ?>
                <div class="bcard-sub">
                    <?php if ($label !== ''): ?><span class="bcard-op"><?= e($label) ?></span><?php endif; ?>
                    <?php if ($tenant !== ''): ?><span class="bcard-tenant"><?= e($tenant) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="bcard-actions">
                <?php if (!$working): ?>
                    <form method="post" action="/factory/blind-action.php" class="bc-inline">
                        <?= $tok ?><input type="hidden" name="action" value="start">
                        <input type="hidden" name="job_id" value="<?= $jobId ?>">
                        <input type="hidden" name="return_to" value="<?= $rt ?>">
                        <button class="bc-btn ghost">Start</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="/factory/blind-action.php" class="bc-inline">
                    <?= $tok ?><input type="hidden" name="action" value="done">
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                    <input type="hidden" name="return_to" value="<?= $rt ?>">
                    <button class="bc-btn go">Done &rarr;</button>
                </form>
                <form method="post" action="/factory/blind-action.php" class="bc-inline" title="Step back a stage">
                    <?= $tok ?><input type="hidden" name="action" value="back">
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                    <input type="hidden" name="return_to" value="<?= $rt ?>">
                    <button class="bc-btn back" aria-label="Step back">&larr;</button>
                </form>
            </div>
        </div>
        <?php
    }
}
