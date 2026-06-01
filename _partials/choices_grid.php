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
                                + Sub-option
                            </a>
                            <button type="button" class="btn-duplicate"
                                    title="Clone this choice (e.g. same label on a different system at a different price)">
                                Duplicate
                            </button>
                            <button type="button" class="btn-delete"
                                    title="Delete this choice">×</button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- "Type to add" row. Always present, never has data-id.
                     Classes (not ids) so multiple grids on a page don't collide.
                     The price / toggle cells stay visually empty here — the
                     helper hint lives in a caption BELOW the table so it
                     doesn't visually clash with the column headers. -->
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
                    <td class="col-price"></td>
                    <td class="col-price"></td>
                    <td class="col-price"></td>
                    <td class="col-toggle"></td>
                    <td class="col-toggle"></td>
                    <td class="col-actions"></td>
                </tr>
            </tbody>
        </table>
    </div>
    <!-- Helper caption sits under the table, not inside it, so it
         doesn't visually overlap the Flat / % / £/m / Default / Active
         columns of the new-row. -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.75rem;margin:0.5rem 0.25rem 0;flex-wrap:wrap">
        <p style="color:#6b7280;font-size:0.8125rem;margin:0;line-height:1.45;flex:1 1 18rem">
            <strong>Tip:</strong> type a label and press
            <strong>Enter</strong> to add one row at a time. For multiple
            (e.g. <em>Left</em> + <em>Right</em>, or a list of slat sizes), use
            <strong>Bulk add</strong> &rarr;
        </p>
        <button type="button" class="bulk-add-choices"
                data-extra-id="<?= (int) $gridExtraId ?>"
                style="background:transparent;border:1px solid var(--border-strong);color:var(--text-primary);border-radius:6px;padding:0.3125rem 0.75rem;font:inherit;font-size:0.8125rem;cursor:pointer;white-space:nowrap">
            + Bulk add
        </button>
    </div>
</div>
