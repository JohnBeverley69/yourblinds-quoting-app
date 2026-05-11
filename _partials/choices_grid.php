<?php
/**
 * Inline-editable choices grid for one product_extras row.
 *
 * Required vars in the calling scope:
 *   $gridExtraId   (int)      — the extra whose choices we render
 *   $gridChoices   (array)    — choice rows (id, label, system_id, prices, etc.)
 *   $productId     (int)      — for the "+ Sub" deep links
 *   $systems       (array)    — product's systems (for new-row dropdown)
 *   $renderSystemMultiSelect  (Closure) — emits existing-row system widget
 *
 * The page-level JS initialises every .choices-grid-wrap it finds, so
 * this partial can be required multiple times on one page (once for the
 * main option, once for each sub-option). All selectors inside use
 * classes (not IDs) so they're safe to repeat.
 *
 * The save indicator is a SHARED page-level element (id="save-indicator")
 * — only one grid is being interacted with at a time, so a single
 * indicator covers all of them.
 */
?>
<div class="choices-grid-wrap" data-extra-id="<?= (int) $gridExtraId ?>">
    <div class="table-wrap">
        <table class="grid-table sortable-list" data-reorder-type="choices">
            <thead>
                <tr>
                    <th class="col-drag"></th>
                    <th class="col-label">Label</th>
                    <th class="col-system">Available on</th>
                    <th class="col-price">Flat £</th>
                    <th class="col-price">%</th>
                    <th class="col-price">£/m</th>
                    <th class="col-toggle" title="Default = pre-selected for the customer">Default</th>
                    <th class="col-toggle" title="Inactive = hidden from quote builder">Active</th>
                    <th class="col-actions"></th>
                </tr>
            </thead>
            <tbody class="grid-body">
                <?php foreach ($gridChoices as $c):
                    $cid       = (int) $c['id'];
                    $sysId     = $c['system_id'] !== null ? (int) $c['system_id'] : null;
                    $isActive  = (int) $c['active']     === 1;
                    $isDefault = (int) $c['is_default'] === 1;
                ?>
                    <tr data-id="<?= $cid ?>" class="<?= $isActive ? '' : 'is-inactive' ?>">
                        <td class="col-drag drag-col" title="Drag to reorder">⋮⋮</td>
                        <td class="col-label">
                            <input class="cell-input" data-field="label"
                                   value="<?= e((string) $c['label']) ?>"
                                   maxlength="150"
                                   autocomplete="off"
                                   data-form-type="other"
                                   data-lpignore="true"
                                   data-1p-ignore="true">
                        </td>
                        <td class="col-system">
                            <?= $renderSystemMultiSelect($sysId) ?>
                        </td>
                        <td class="col-price">
                            <input class="cell-input num" data-field="price_delta"
                                   type="number" step="0.01"
                                   value="<?= number_format((float) $c['price_delta'], 2, '.', '') ?>">
                        </td>
                        <td class="col-price">
                            <input class="cell-input num" data-field="price_percent"
                                   type="number" step="0.01"
                                   value="<?= number_format((float) $c['price_percent'], 2, '.', '') ?>">
                        </td>
                        <td class="col-price">
                            <input class="cell-input num" data-field="price_per_metre"
                                   type="number" step="0.01"
                                   value="<?= number_format((float) $c['price_per_metre'], 2, '.', '') ?>">
                        </td>
                        <td class="col-toggle">
                            <input type="checkbox" data-field="is_default"
                                   <?= $isDefault ? 'checked' : '' ?>>
                        </td>
                        <td class="col-toggle">
                            <input type="checkbox" data-field="active"
                                   <?= $isActive ? 'checked' : '' ?>>
                        </td>
                        <td class="col-actions row-actions">
                            <a href="/admin/products/extra-choice-edit.php?id=<?= $cid ?>"
                               class="btn-more"
                               title="Full edit page — width-table pricing, thumbnail image upload">Edit</a>
                            <a href="/admin/products/extras.php?product_id=<?= (int) $productId ?>&parent_choice=<?= $cid ?>#add-option"
                               class="btn-sub"
                               title="Add a follow-up option that only appears when this choice is selected">
                                + Sub
                            </a>
                            <button type="button" class="btn-duplicate"
                                    title="Clone this choice (same label, another system at a different price)">
                                Dup
                            </button>
                            <button type="button" class="btn-delete"
                                    title="Delete this choice">×</button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- "Type to add" row. Always present, never has data-id.
                     Classes (not ids) so multiple grids on a page don't collide. -->
                <tr class="new-row">
                    <td class="col-drag">+</td>
                    <td class="col-label">
                        <input class="cell-input new-label-input"
                               placeholder="Type new label and press Enter…"
                               maxlength="150"
                               autocomplete="off"
                               data-form-type="other"
                               data-lpignore="true"
                               data-1p-ignore="true">
                    </td>
                    <td class="col-system">
                        <details class="multi-select new-system-details">
                            <summary class="new-system-summary">All systems</summary>
                            <div class="multi-opts">
                                <label>
                                    <input type="checkbox" class="new-system-all-cb" checked>
                                    <span><strong>All systems</strong></span>
                                </label>
                                <?php if ($systems): ?>
                                    <hr>
                                    <?php foreach ($systems as $s): ?>
                                        <label>
                                            <input type="checkbox"
                                                   class="new-system-one"
                                                   value="<?= (int) $s['id'] ?>">
                                            <span><?= e((string) $s['name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                    </td>
                    <td colspan="6" style="color:#9ca3af;font-size:0.8125rem">
                        Tick one or more systems, then press <strong>Enter</strong> on the label.
                        One row per ticked system; prices and toggles become editable on each new row.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
