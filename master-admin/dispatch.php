<?php
declare(strict_types=1);

/**
 * Master Admin · Dispatch tray — today's Ready orders, ticked and noted out.
 *
 * This is the screen the accounts side asked for, in her own words: "have a tray
 * of Ready orders for that day, get them out and print Del Notes for each order."
 * So it is deliberately NOT a "delivery run": there is no run object, no driver's
 * round sheet and no consolidated note per account. One note per ORDER, because
 * that is what she picked.
 *
 * Printing is separate from invoicing ON PURPOSE. She does not always print every
 * note, and invoice numbers have to come out in sequence, so wiring the invoice to
 * the print would put gaps and surprises in the numbering. Raising the invoice
 * stays its own action over on Wholesale.
 *
 * The note itself carries: account name + delivery address, their order/PO
 * reference, and each blind's room, size and fabric — with NO prices, and a
 * signature line that they do not use today but wanted on there in case they ever
 * deliver differently. All of that is already how ar_render_delivery_note draws it.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../_partials/factory_ar.php';
require_once __DIR__ . '/../_partials/order_stage.php';

requireSuperAdmin();

$user    = current_user();
$pdo     = db();
$factory = ar_factory_id();
$dnReady = ar_table_ready($pdo, 'factory_ar_delivery_notes');

/**
 * Ready orders waiting to go out: placed, carrying factory-owned lines, and at the
 * "ready" stage (every blind made, every bought-in item received — os_is_ready's
 * rule, already reconciled into quotes.fulfilment_stage). Orders that already have
 * a delivery note are kept but flagged, so re-printing one is possible without it
 * cluttering the default view.
 */
function dispatch_ready_orders(PDO $pdo, int $factory): array
{
    $orders = ar_placed_orders($pdo, $factory);
    if (!$orders) return [];

    $ids = array_map(static fn ($o) => (int) $o['id'], $orders);
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    // Stored stage first — one query for the whole list rather than os_is_ready()
    // per row. Falls back to asking outright if the column is not there yet.
    $stage = [];
    try {
        $s = $pdo->prepare("SELECT id, fulfilment_stage FROM quotes WHERE id IN ($ph)");
        $s->execute($ids);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $stage[(int) $r['id']] = (string) $r['fulfilment_stage'];
    } catch (Throwable $e) {
        foreach ($ids as $id) $stage[$id] = os_is_ready($pdo, $id, $factory) ? 'ready' : '';
    }

    $ref = [];
    try {
        $r = $pdo->prepare("SELECT id, customer_reference FROM quotes WHERE id IN ($ph)");
        $r->execute($ids);
        foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $ref[(int) $row['id']] = (string) ($row['customer_reference'] ?? '');
    } catch (Throwable $e) { /* pre-migrate_order_references — no PO column */ }

    $out = [];
    foreach ($orders as $o) {
        $id = (int) $o['id'];
        if (($stage[$id] ?? '') !== 'ready') continue;
        $o['customer_reference'] = $ref[$id] ?? '';
        $o['has_dn']             = ((int) ($o['dn_count'] ?? 0)) > 0;
        $out[] = $o;
    }
    return $out;
}

// ── Print the ticked orders' delivery notes as ONE PDF ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $ids = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['quote_ids'] ?? [])),
        static fn ($n) => $n > 0
    )));

    if (!$dnReady) {
        $_SESSION['flash_error'] = 'Delivery notes need their migration — run /migrate_ar_delivery_notes.php first.';
        header('Location: /master-admin/dispatch.php'); exit;
    }
    if (!$ids) {
        $_SESSION['flash_error'] = 'Tick at least one order first.';
        header('Location: /master-admin/dispatch.php'); exit;
    }

    // Only orders that are genuinely ours and genuinely ready — the tick list came
    // from the browser, so it is not trusted.
    $allowed = [];
    foreach (dispatch_ready_orders($pdo, $factory) as $o) $allowed[(int) $o['id']] = $o;

    $notes = [];
    $fac   = [];
    try {
        $fs = $pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $fs->execute([$factory]);
        $fac = $fs->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { /* letterhead degrades */ }

    try {
        foreach ($ids as $qid) {
            if (!isset($allowed[$qid])) continue;
            $accountId = (int) $allowed[$qid]['account_id'];

            // Reuse the order's existing note if it has one, so re-printing keeps
            // the same DN number rather than minting DN-…-2 every time.
            $dnId = 0;
            $ex = $pdo->prepare(
                "SELECT id FROM factory_ar_delivery_notes
                  WHERE source_quote_id = ? AND factory_client_id = ? AND status <> 'cancelled'
               ORDER BY id LIMIT 1"
            );
            $ex->execute([$qid, $factory]);
            $dnId = (int) $ex->fetchColumn();

            if ($dnId === 0) {
                $dn   = ar_create_delivery_note($pdo, $factory, $qid, $accountId, (int) ($user['user_id'] ?? 0), false);
                $dnId = (int) ($dn['id'] ?? 0);
            }
            if ($dnId === 0) continue;

            $h = $pdo->prepare('SELECT * FROM factory_ar_delivery_notes WHERE id = ? LIMIT 1');
            $h->execute([$dnId]);
            $head = $h->fetch(PDO::FETCH_ASSOC);
            if (!$head) continue;

            $l = $pdo->prepare('SELECT * FROM factory_ar_delivery_note_lines WHERE delivery_note_id = ? ORDER BY sort_order, id');
            $l->execute([$dnId]);

            $items = [];
            foreach ($l->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'product'   => (string) ($r['product_name'] ?? ''),
                    'system'    => (string) ($r['system_name'] ?? ''),
                    'fabric'    => (string) ($r['fabric'] ?? ''),
                    'band'      => (string) ($r['band_code'] ?? ''),
                    'width_mm'  => $r['width_mm'],
                    'drop_mm'   => $r['drop_mm'],
                    'quantity'  => (int) ($r['quantity'] ?? 1),
                    'room'      => (string) ($r['room'] ?? ''),
                    'notes'     => (string) ($r['line_notes'] ?? ''),
                    'options'   => array_values(array_filter(
                        preg_split('/\r?\n/', (string) ($r['options_snapshot'] ?? '')) ?: [],
                        static fn ($s) => trim((string) $s) !== ''
                    )),
                ];
            }

            $notes[] = [
                'ctx' => [
                    'factory'    => $fac,
                    'dn_number'  => (string) $head['dn_number'],
                    'date'       => date('d/m/Y'),
                    'deliver_to' => (string) ($head['delivery_address'] ?? ''),
                    'order_ref'  => trim((string) $allowed[$qid]['quote_number']
                                   . ((string) $allowed[$qid]['customer_reference'] !== ''
                                       ? ' · ' . $allowed[$qid]['customer_reference'] : '')),
                    'notes'      => (string) ($head['notes'] ?? ''),
                ],
                'items' => $items,
            ];
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not build the delivery notes: ' . $e->getMessage();
        header('Location: /master-admin/dispatch.php'); exit;
    }

    if (!$notes) {
        $_SESSION['flash_error'] = 'Nothing to print — those orders are no longer ready.';
        header('Location: /master-admin/dispatch.php'); exit;
    }

    require_once __DIR__ . '/../pdf-generator/ar_pdf.php';
    $pdf = ar_render_delivery_notes_combined($notes);
    if ($pdf === null) {
        $_SESSION['flash_error'] = 'PDF engine unavailable.';
        header('Location: /master-admin/dispatch.php'); exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="delivery-notes-' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$ready     = $dnReady ? dispatch_ready_orders($pdo, $factory) : [];
$activeNav = 'dispatch';
$money     = static fn ($n) => '£' . number_format((float) $n, 2);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispatch tray &middot; YourBlinds</title>
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
      .dt-bar { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin:0 0 .9rem;
                padding:.6rem .7rem; border:1px solid var(--border,#e2e8ef); border-radius:10px;
                background:var(--bg-subtle,#f8fafc); position:sticky; top:0; z-index:5; }
      .dt-count { font-weight:600; }
      .dt-row.is-noted { opacity:.62; }
      .dt-po { color:var(--text-muted,#667); font-size:.85rem; }
      .dt-empty { padding:1.4rem; color:var(--text-muted,#667); }
    </style>
</head>
<body>
<?php require __DIR__ . '/../_partials/sidebar.php'; ?>
<main class="app-main">
  <div class="page-head">
    <h1 class="page-title">Dispatch tray</h1>
    <p class="ui-hint">Orders that are ready to go — every blind made and every bought-in item in.
       Tick the ones going out and print their delivery notes. Invoicing stays a separate step on
       <a href="/master-admin/wholesale.php">Wholesale</a>.</p>
  </div>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-error" role="alert"><?= e((string) $_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success" role="alert"><?= e((string) $_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (!$dnReady): ?>
    <div class="alert alert-error" role="alert">
      Delivery notes need their migration — run <code>/migrate_ar_delivery_notes.php</code> once, then reload.
    </div>
  <?php elseif (!$ready): ?>
    <div class="card"><div class="dt-empty">
      Nothing is ready to go out right now. An order lands here once every blind on it is made and
      every bought-in item has been received.
    </div></div>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="dt-bar">
        <label class="dt-count"><input type="checkbox" id="dtAll"> Tick all</label>
        <span class="ui-hint"><?= count($ready) ?> order<?= count($ready) === 1 ? '' : 's' ?> ready</span>
        <button class="btn btn-primary" style="margin-left:auto">🖨 Print delivery notes</button>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead><tr>
            <th style="width:2.2rem"></th>
            <th>Account</th><th>Order</th><th>Their ref</th>
            <th class="num">Blinds</th><th class="num">Value</th><th>Note</th>
          </tr></thead>
          <tbody>
          <?php foreach ($ready as $o): $qid = (int) $o['id']; ?>
            <tr class="dt-row<?= $o['has_dn'] ? ' is-noted' : '' ?>">
              <td><input type="checkbox" class="dt-tick" name="quote_ids[]" value="<?= $qid ?>"
                         <?= $o['has_dn'] ? '' : 'checked' ?>></td>
              <td><?= e((string) ($o['account_name'] ?? '')) ?></td>
              <td><a href="/quote-builder/edit.php?id=<?= $qid ?>"><?= e((string) $o['quote_number']) ?></a></td>
              <td class="dt-po"><?= e((string) ($o['customer_reference'] ?? '')) ?: '—' ?></td>
              <td class="num"><?= (int) ($o['bev_qty'] ?? 0) ?></td>
              <td class="num"><?= e($money($o['wholesale_total'] ?? 0)) ?></td>
              <td><?= $o['has_dn'] ? '<span class="ui-hint">already noted</span>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>

    <script>
      (function () {
        var all = document.getElementById('dtAll');
        if (!all) return;
        all.addEventListener('change', function () {
          document.querySelectorAll('.dt-tick').forEach(function (t) { t.checked = all.checked; });
        });
      })();
    </script>
  <?php endif; ?>
</main>
</body>
</html>
