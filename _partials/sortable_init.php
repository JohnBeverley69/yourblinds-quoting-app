<?php
// Auto-wires drag-and-drop reordering on every <table class="sortable-list">.
//
// Markup contract:
//   <table class="table sortable-list" data-reorder-type="products|systems|extras|choices">
//     <tbody>
//       <tr data-id="1"> <td class="drag-col">...grip...</td> ... </tr>
//
// On drop, posts ids[] in the new order to /admin/products/reorder.php?type=...
// which updates sort_order = position. Tenant-scoped server-side.
//
// Optional status element on the page:
//   <span class="reorder-status" [data-for="systems"]>...</span>
// Toggles 'Saving…' / 'Saved' (green) / 'Save failed' (red).
?>
<style>
    .drag-col { width: 2rem; text-align: center; cursor: grab; color: var(--text-faint); user-select: none; }
    .drag-col:hover { color: #1f3b5b; }
    .drag-col:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.4; }
    .sortable-chosen { background: #eff6ff; }
    .reorder-status {
        display: inline-block; margin-left: 0.5rem; font-size: 0.8125rem;
        color: var(--text-faint); opacity: 0; transition: opacity 0.2s;
    }
    .reorder-status.show { opacity: 1; }
    .reorder-status.ok { color: #16a34a; }
    .reorder-status.fail { color: #b91c1c; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"
        integrity="sha384-BSxuMLxX+FCbTdYec3TbXlnMGEEM2QXTFdtDaveen71o+jswm2J36+xFqp8k4VHM"
        crossorigin="anonymous"></script>
<script>
(function () {
    if (typeof Sortable === 'undefined') return;
    var csrf = <?= json_encode(csrf_token()) ?>;

    document.querySelectorAll('table.sortable-list').forEach(function (table) {
        var tbody = table.querySelector('tbody');
        var type  = table.dataset.reorderType;
        if (!tbody || !type) return;

        var status = document.querySelector(
            '.reorder-status[data-for="' + type + '"]'
        ) || document.querySelector('.reorder-status');

        function show(msg, kind) {
            if (!status) return;
            status.textContent = msg;
            status.classList.remove('ok', 'fail');
            if (kind) status.classList.add(kind);
            status.classList.add('show');
            if (kind === 'ok') {
                setTimeout(function () { status.classList.remove('show'); }, 1200);
            }
        }

        new Sortable(tbody, {
            handle: '.drag-col',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onStart: function () { show('Saving…'); },
            onEnd:   function () {
                var ids = Array.prototype.map.call(tbody.children, function (tr) {
                    return tr.getAttribute('data-id');
                }).filter(Boolean);
                var body = '_csrf=' + encodeURIComponent(csrf);
                ids.forEach(function (id) { body += '&ids[]=' + encodeURIComponent(id); });
                fetch('/admin/products/reorder.php?type=' + encodeURIComponent(type), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                }).then(function (r) {
                    return r.json().then(function (j) { return { ok: r.ok && j.ok, data: j }; });
                }).then(function (r) {
                    show(r.ok ? 'Saved' : 'Save failed', r.ok ? 'ok' : 'fail');
                }).catch(function () {
                    show('Save failed', 'fail');
                });
            }
        });
    });
})();
</script>
