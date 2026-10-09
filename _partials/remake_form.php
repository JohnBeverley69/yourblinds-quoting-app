<?php
declare(strict_types=1);

/**
 * The shared "which blinds, why" part of a remake form — used by the factory's
 * Raise remake page and the account's Report a problem page, so both ask the same
 * questions the same way. Prints fields only (the caller owns <form>, CSRF and the
 * submit button). Needs _partials/remakes.php loaded.
 *
 * @param array $lines   rm_order_lines()
 * @param array $reasons rm_reasons() [id => label]
 * @param array $old     previous POST (to refill after an error)
 */
function rm_form_fields(array $lines, array $reasons, array $old = []): void
{
    $oldItems = (array) ($old['items'] ?? []);
    ?>
    <style>
      .rmf { display:flex; flex-direction:column; gap:1.1rem; }
      .rmf fieldset { border:0; margin:0; padding:0; display:flex; flex-direction:column; gap:.5rem; }
      .rmf legend { font-weight:700; color:var(--text-primary); margin-bottom:.35rem; }
      .rmf-line { display:grid; grid-template-columns:auto 1fr auto; gap:.6rem; align-items:center;
                  padding:.6rem .75rem; border:1px solid var(--border); border-radius:10px; background:var(--bg-card); }
      .rmf-line label { cursor:pointer; min-width:0; }
      .rmf-line small { color:var(--text-faint); display:block; }
      .rmf-qty { display:flex; align-items:center; gap:.35rem; font-size:.875rem; color:var(--text-muted); white-space:nowrap; }
      .rmf-qty input { width:4.2rem; padding:.35rem .45rem; border:1px solid var(--border-strong); border-radius:8px;
                       background:var(--bg-input); color:var(--text-body); font:inherit; }
      .rmf select, .rmf textarea { width:100%; max-width:36rem; padding:.5rem .7rem; border:1px solid var(--border-strong);
                       border-radius:8px; background:var(--bg-input); color:var(--text-body); font:inherit; }
      .rmf textarea { min-height:5.5rem; }
      .rmf input[type=file] { font:inherit; }
      .rmf .rmf-hint { color:var(--text-faint); font-size:.85rem; }
    </style>
    <div class="rmf">
      <fieldset>
        <legend>Which blinds need remaking?</legend>
        <?php foreach ($lines as $l):
            $id  = (int) $l['id'];
            $on  = isset($oldItems[$id]) && (int) $oldItems[$id] > 0;
            $qty = $on ? (int) $oldItems[$id] : $l['quantity']; ?>
          <div class="rmf-line">
            <input type="checkbox" id="rmfTick<?= $id ?>" class="rmf-tick" data-for="rmfQty<?= $id ?>"<?= $on ? ' checked' : '' ?>>
            <label for="rmfTick<?= $id ?>">
              <?= e(rm_line_label($l)) ?>
              <small>Line <?= (int) $l['line_no'] ?> · <?= (int) $l['quantity'] ?> on the order</small>
            </label>
            <span class="rmf-qty">
              <label for="rmfQty<?= $id ?>">How many</label>
              <input type="number" id="rmfQty<?= $id ?>" name="items[<?= $id ?>]" min="0" max="<?= (int) $l['quantity'] ?>"
                     value="<?= $on ? $qty : 0 ?>" data-max="<?= (int) $l['quantity'] ?>" data-unit="<?= e(number_format((float) $l['unit_trade'], 2, '.', '')) ?>" class="rmf-q">
            </span>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <div>
        <label for="rmfReason" style="font-weight:700;display:block;margin-bottom:.35rem">Reason</label>
        <select id="rmfReason" name="reason_id" required>
          <option value="">Choose a reason…</option>
          <?php foreach ($reasons as $rid => $label): ?>
            <option value="<?= (int) $rid ?>"<?= (int) ($old['reason_id'] ?? 0) === (int) $rid ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="rmfNote" style="font-weight:700;display:block;margin-bottom:.35rem">What’s wrong?</label>
        <textarea id="rmfNote" name="note" maxlength="2000" placeholder="e.g. fabric flaw 300mm from the bottom"><?= e((string) ($old['note'] ?? '')) ?></textarea>
      </div>

      <div>
        <label for="rmfPhoto" style="font-weight:700;display:block;margin-bottom:.35rem">Photo <span class="rmf-hint">(optional)</span></label>
        <input type="file" id="rmfPhoto" name="photo" accept="image/*">
        <div class="rmf-hint">A picture of the fault helps. JPG, PNG or WebP, up to 12 MB.</div>
      </div>
    </div>
    <script>
      document.querySelectorAll('.rmf-tick').forEach(function (t) {
        var q = document.getElementById(t.dataset.for);
        t.addEventListener('change', function () {
          q.value = t.checked ? (parseInt(q.value, 10) > 0 ? q.value : q.dataset.max) : 0;
        });
        q.addEventListener('input', function () { t.checked = parseInt(q.value, 10) > 0; rmfCost(); });
        t.addEventListener('change', rmfCost);
      });
      function rmfCost() {
        var c = 0;
        document.querySelectorAll('.rmf-q').forEach(function (q) { c += (parseInt(q.value, 10) || 0) * parseFloat(q.dataset.unit || 0); });
        document.querySelectorAll('.rmd-cost').forEach(function (el) { el.textContent = c.toFixed(2); });
        document.querySelectorAll('.rmd-amt').forEach(function (el) { el.placeholder = c.toFixed(2); });
      }
      rmfCost();
    </script>
    <?php
}

/**
 * The "who pays" decision — free (our fault), charge the account (any amount,
 * decided on merit) or free as a supplier claim — plus a due date. Used when the
 * office raises a remake and when it approves an account's request. $key keeps
 * ids unique when several approve forms share a page. $cost = the trade price of
 * the blinds, shown as the reference for a charge.
 */
function rm_decision_fields(string $key, float $cost, array $old = []): void
{
    $mode = (string) ($old['charge_mode'] ?? 'free');
    ?>
    <div class="rmd" style="display:flex;flex-direction:column;gap:.6rem">
      <div style="font-weight:700;color:var(--text-primary)">Who pays?</div>
      <div style="display:flex;flex-direction:column;gap:.35rem">
        <?php foreach (rm_charge_modes() as $m => $label): ?>
          <label style="display:flex;gap:.5rem;align-items:center">
            <input type="radio" name="charge_mode" value="<?= e($m) ?>" class="rmd-mode" data-key="<?= e($key) ?>"<?= $mode === $m ? ' checked' : '' ?>>
            <?= e($label) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="rmd-charge" data-key="<?= e($key) ?>"<?= $mode === 'charge' ? '' : ' hidden' ?>>
        <label for="rmdAmt<?= e($key) ?>">Charge the account (£, ex VAT)</label>
        <input type="number" id="rmdAmt<?= e($key) ?>" name="charge_amount" step="0.01" min="0" class="rmd-amt"
               value="<?= e((string) ($old['charge_amount'] ?? '')) ?>" placeholder="<?= e(number_format($cost, 2, '.', '')) ?>"
               style="width:8rem;padding:.4rem .55rem;border:1px solid var(--border-strong);border-radius:8px;background:var(--bg-input);color:var(--text-body);font:inherit">
        <span style="color:var(--text-faint);font-size:.85rem">Full trade price of these blinds: £<span class="rmd-cost"><?= e(number_format($cost, 2, '.', '')) ?></span></span>
      </div>
      <div class="rmd-supplier" data-key="<?= e($key) ?>"<?= $mode === 'supplier' ? '' : ' hidden' ?>>
        <label for="rmdSup<?= e($key) ?>">Supplier the claim is against</label>
        <input type="text" id="rmdSup<?= e($key) ?>" name="supplier_name" maxlength="120" value="<?= e((string) ($old['supplier_name'] ?? '')) ?>"
               placeholder="e.g. Louvolite"
               style="width:100%;max-width:20rem;padding:.4rem .55rem;border:1px solid var(--border-strong);border-radius:8px;background:var(--bg-input);color:var(--text-body);font:inherit">
      </div>
      <div>
        <label for="rmdDue<?= e($key) ?>">Due date <span style="color:var(--text-faint);font-size:.85rem">(optional)</span></label>
        <input type="date" id="rmdDue<?= e($key) ?>" name="due_date" value="<?= e((string) ($old['due_date'] ?? '')) ?>"
               style="padding:.35rem .5rem;border:1px solid var(--border-strong);border-radius:8px;background:var(--bg-input);color:var(--text-body);font:inherit">
      </div>
    </div>
    <script>
      (function () {
        var k = <?= json_encode($key) ?>;
        document.querySelectorAll('.rmd-mode[data-key="' + k + '"]').forEach(function (r) {
          r.addEventListener('change', function () {
            document.querySelector('.rmd-charge[data-key="' + k + '"]').hidden = r.value !== 'charge';
            document.querySelector('.rmd-supplier[data-key="' + k + '"]').hidden = r.value !== 'supplier';
          });
        });
      })();
    </script>
    <?php
}
