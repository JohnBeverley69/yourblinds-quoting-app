<?php
/**
 * Shared CSS for the inline-editable choices grid.
 *
 * Used by:
 *   - admin/products/extra.php   — the dedicated choices editor page
 *   - admin/products/edit.php    — inline grids per option in the
 *                                  Options section (Phase 2B)
 *
 * Includes the spreadsheet-style table styles, the multi-system
 * picker, the persistent save-status pill, and the sub-options card
 * layout. Sub-option styles are kept here even though edit.php doesn't
 * currently render sub-options inline — keeping all the grid CSS in
 * one place is simpler than splitting "core" vs "sub-options" rules.
 */
?>
<style>
    /* Spreadsheet-style choices grid. Each cell looks plain until
       focused, then shows a clear edit affordance. Aim is to make
       the page feel like a tight data grid, not a form-and-list. */
    .grid-table { width: 100%; border-collapse: collapse; }
    .grid-table thead th {
        text-align: left; font-size: 0.75rem; font-weight: 700;
        color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;
        padding: 0.5rem 0.5rem; border-bottom: 2px solid #e5e7eb;
        background: #f9fafb;
    }
    .grid-table tbody td {
        padding: 0.25rem 0.25rem; border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .grid-table tbody tr:hover td { background: #fafbfd; }
    .grid-table tbody tr.is-saving td { background: #fefce8; }
    .grid-table tbody tr.just-saved td {
        background: #ecfdf5; transition: background 600ms ease-out;
    }
    .grid-table tbody tr.is-error td { background: #fef2f2; }
    .grid-table tbody tr.is-inactive td .cell-input,
    .grid-table tbody tr.is-inactive td .cell-select {
        opacity: 0.55; text-decoration: line-through;
    }

    /* Editable cells — invisible until interaction. */
    .cell-input, .cell-select {
        font: inherit; width: 100%; box-sizing: border-box;
        padding: 0.4375rem 0.5rem; background: transparent;
        border: 1px solid transparent; border-radius: 6px;
        color: #111827;
    }
    .cell-input.num { text-align: right; font-variant-numeric: tabular-nums; }
    /* Suppress the browser's number-input spinners — the right-aligned
       digits read better without them, and the user can edit freely. */
    .cell-input.num::-webkit-outer-spin-button,
    .cell-input.num::-webkit-inner-spin-button {
        -webkit-appearance: none; margin: 0;
    }
    .cell-input.num { -moz-appearance: textfield; }
    .cell-input:hover, .cell-select:hover {
        border-color: #d1d5db; background: #fff;
    }
    .cell-input:focus, .cell-select:focus {
        outline: none; border-color: #1f3b5b; background: #fff;
        box-shadow: 0 0 0 3px rgba(31, 59, 91, 0.12);
    }

    .grid-table th.col-drag,    .grid-table td.col-drag    { width: 28px; padding-left: 0.25rem; padding-right: 0; color: #9ca3af; cursor: grab; text-align: center; }
    .grid-table th.col-label,   .grid-table td.col-label   { min-width: 180px; }
    .grid-table th.col-system,  .grid-table td.col-system  { width: 200px; }
    .grid-table th.col-price,   .grid-table td.col-price   { width: 96px; }
    .grid-table th.col-toggle,  .grid-table td.col-toggle  { width: 72px; text-align: center; }
    .grid-table th.col-actions, .grid-table td.col-actions { width: 130px; text-align: right; white-space: nowrap; }

    .col-toggle input[type="checkbox"] {
        width: 18px; height: 18px; cursor: pointer; margin: 0;
    }

    .row-actions a, .row-actions button {
        font-size: 0.8125rem; padding: 0.25rem 0.5rem; margin: 0 0 0 0.125rem;
        border: 0; background: transparent; cursor: pointer; border-radius: 6px;
        color: #1f3b5b; text-decoration: none;
    }
    .row-actions a:hover, .row-actions button:hover {
        background: #eef2f7;
    }
    .row-actions .btn-more { color: #4b5563; }
    .row-actions .btn-sub  { color: #15803d; }
    .row-actions .btn-sub:hover { background: #dcfce7; }
    .row-actions .btn-delete { color: #b91c1c; }
    .row-actions .btn-delete:hover { background: #fee2e2; }

    /* Bottom blank row gets a softer background so it reads as a
       "type to add" affordance rather than a real row. */
    .grid-table tr.new-row td { background: #f9fafb; }
    .grid-table tr.new-row td:first-child { color: #d1d5db; }
    .grid-table tr.new-row .cell-input::placeholder { color: #9ca3af; font-style: italic; }

    /* Multi-system selector on the new-row. Built on <details> so
       we get show/hide for free; the styling makes the closed
       summary look like a normal cell-select, and the open panel
       floats above the table with a checkbox per system. */
    .multi-select { position: relative; }
    .multi-select > summary {
        list-style: none; cursor: pointer;
        font: inherit; padding: 0.4375rem 1.75rem 0.4375rem 0.5rem;
        border: 1px solid transparent; border-radius: 6px;
        background: transparent; color: #111827;
        position: relative;
    }
    .multi-select > summary::-webkit-details-marker { display: none; }
    .multi-select > summary::after {
        content: '▾'; position: absolute; right: 0.5rem; top: 50%;
        transform: translateY(-50%); color: #6b7280; font-size: 0.75rem;
    }
    .multi-select > summary:hover {
        border-color: #d1d5db; background: #fff;
    }
    .multi-select[open] > summary {
        border-color: #1f3b5b; background: #fff;
        box-shadow: 0 0 0 3px rgba(31, 59, 91, 0.12);
    }
    .multi-opts {
        position: absolute; top: 100%; left: 0; right: 0;
        margin-top: 4px; padding: 0.375rem;
        background: #fff; border: 1px solid #d1d5db;
        border-radius: 8px; box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        z-index: 30; min-width: 200px;
    }
    /* When any system dropdown is open, allow the popup to overflow
       the table wrapper. Without this, app.css's .table-wrap
       overflow:auto (for horizontal scrolling on mobile) clips the
       absolute-positioned popup vertically — visible result is the
       dropdown appearing to contain only "All systems" because the
       rest is hidden below the scroll boundary. The :has() selector
       keeps horizontal scrolling working when nothing's open. */
    .choices-grid-wrap:has(details.multi-select[open]) .table-wrap,
    .choices-grid-wrap:has(details.multi-select[open]) {
        overflow: visible;
    }
    /* Same row-level fix — the tbody/tr can stack contexts too. */
    .grid-table:has(details.multi-select[open]) tbody,
    .grid-table:has(details.multi-select[open]) tr {
        overflow: visible;
    }
    .multi-opts label {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.375rem 0.5rem; cursor: pointer; border-radius: 6px;
        font-size: 0.9375rem; color: #111827;
    }
    .multi-opts label:hover { background: #eef2f7; }
    .multi-opts input[type="checkbox"] {
        width: 16px; height: 16px; margin: 0;
    }
    .multi-opts hr {
        margin: 0.25rem 0; border: 0; border-top: 1px solid #f3f4f6;
    }

    .row-error {
        color: #b91c1c; font-size: 0.8125rem; padding: 0.25rem 0.5rem 0;
    }

    /* Save-status pill — always visible to reassure the user
       their inline edits are persisting. Three states:
         idle    "All changes saved" (green)
         saving  "Saving…"           (amber)
         error   "Save failed"       (red)
       Sits in the section header next to the title. */
    .save-indicator {
        display: inline-flex; align-items: center; gap: 0.3125rem;
        font-size: 0.8125rem; font-weight: 600;
        padding: 0.1875rem 0.625rem; border-radius: 999px;
        margin-left: 0.625rem;
        background: #d1fae5; color: #065f46;
    }
    .save-indicator::before {
        content: '✓';
        display: inline-block; font-weight: 700;
    }
    .save-indicator.is-saving {
        background: #fef3c7; color: #92400e;
    }
    .save-indicator.is-saving::before {
        content: '⟳';
        animation: si-spin 1s linear infinite;
    }
    .save-indicator.is-error {
        background: #fee2e2; color: #991b1b;
    }
    .save-indicator.is-error::before { content: '!'; }
    @keyframes si-spin {
        to { transform: rotate(360deg); }
    }

    /* ===========================================================
       Sub-options section — cards for each follow-up option
       gated by THIS option's choices, plus a collapsible
       "+ Add sub-option" form below. Lets the trade user manage
       the whole tree without round-tripping through the Add
       Option page.
       =========================================================== */
    .sub-card {
        background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 0.75rem 0.875rem;
        margin-bottom: 0.875rem;
    }
    .sub-card.is-inactive { opacity: 0.7; }
    .sub-card-head {
        display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
        margin-bottom: 0.625rem;
    }
    .sub-card-name { font-size: 1rem; color: #111827; }
    .sub-card-gates {
        font-size: 0.8125rem; color: #6b7280;
        display: inline-flex; align-items: center; gap: 0.3125rem; flex-wrap: wrap;
    }
    .gate-pill {
        display: inline-block; padding: 0.0625rem 0.5rem;
        background: #eef2f7; color: #1f3b5b;
        border-radius: 999px; font-size: 0.75rem; font-weight: 600;
    }
    .sub-card-actions {
        display: inline-flex; flex-wrap: wrap; gap: 0.25rem 0.625rem;
        font-size: 0.8125rem;
        margin-left: auto;
    }
    .sub-card-actions a, .sub-card-actions button {
        background: transparent; border: 0; padding: 0; cursor: pointer;
        font: inherit; text-decoration: none;
    }
    .sub-card-actions .btn-primary-link   { color: #1f3b5b; font-weight: 600; }
    .sub-card-actions .btn-secondary-link { color: #4b5563; }
    .sub-card-actions .btn-danger-link    { color: #b91c1c; }
    .sub-card-actions a:hover, .sub-card-actions button:hover { text-decoration: underline; }
    /* Sub-option's inline grid keeps the same look but a subtle
       background so it's visually grouped with its card header. */
    .sub-card > .choices-grid-wrap {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    }

    details.add-sub-form > summary {
        cursor: pointer; padding: 0.5rem 0.75rem;
        background: #eef2f7; border: 1px dashed #93c5fd;
        border-radius: 8px; color: #1f3b5b; font-weight: 600;
        font-size: 0.9375rem; list-style: none;
        width: max-content;
    }
    details.add-sub-form > summary::-webkit-details-marker { display: none; }
    details.add-sub-form > summary:hover { background: #dbeafe; }
    details.add-sub-form[open] > summary { background: #dbeafe; }
    details.add-sub-form > .form {
        margin-top: 0.75rem;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: 1rem;
    }
    .req-pill {
        display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
        font-weight: 700; color: #fff; background: #1f3b5b;
        border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em;
    }
    .opt-pill {
        display: inline-block; padding: 0.0625rem 0.5rem; font-size: 0.6875rem;
        font-weight: 700; color: #6b7280; background: #f3f4f6;
        border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em;
    }
</style>
