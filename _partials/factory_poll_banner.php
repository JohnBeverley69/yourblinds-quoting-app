<?php
declare(strict_types=1);

/**
 * "New orders have come in" for a factory screen that sits open all day.
 *
 * Same behaviour as Incoming orders (factory/incoming-orders.php): poll
 * /factory/poll.php every 20s; when the version changes, reload by itself if
 * nobody has touched the page for 4 seconds (or the tab is hidden), otherwise
 * OFFER a refresh, so a reload never lands a click on the wrong card.
 * The Pipeline, Console Orders and Dashboard had no polling at all, so new
 * orders only showed after a manual refresh (John, 2026-10-12).
 *
 * Usage, just before </body>:  fx_poll_banner($pdo, 'pipeline', current_factory_id());
 */

require_once __DIR__ . '/factory_poll.php';

function fx_poll_banner(PDO $pdo, string $what, int $factoryId): void
{
    $version = fx_poll_version($pdo, $what, $factoryId);
    ?>
<style>
  .fx-news { position: fixed; left: 50%; bottom: 1.2rem; transform: translateX(-50%); z-index: 50;
      display: flex; align-items: center; gap: .9rem; background: #166534; color: #fff;
      padding: .65rem 1.1rem; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.2); }
  .fx-news[hidden] { display: none; }
  .fx-news button { font: inherit; font-weight: 700; cursor: pointer; border: none; border-radius: 8px;
      padding: .3rem .85rem; background: #fff; color: #166534; }
</style>
<div class="fx-news" id="fx-news" hidden role="status">
  <span id="fx-news-text">New orders have come in.</span>
  <button type="button" id="fx-news-btn">Refresh</button>
</div>
<script>
(function () {
    var mine = <?= json_encode($version) ?>, what = <?= json_encode($what) ?>;
    var news = document.getElementById('fx-news'), text = document.getElementById('fx-news-text');
    var btn = document.getElementById('fx-news-btn');
    if (!news || !btn || mine === 'x') return;
    var baseTitle = document.title, lastTouch = 0, IDLE_MS = 4000;
    btn.addEventListener('click', function () { location.reload(); });
    ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, function () { lastTouch = Date.now(); }, true);
    });
    function check() {
        fetch('/factory/poll.php?what=' + encodeURIComponent(what), { cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j || !j.v || j.v === 'x' || j.v === mine) return;
                // Never reload under an open Process order summary (_partials/process_order.php).
                var busy = !!document.querySelector('.po-back:not([hidden])');
                if (!busy && (document.hidden || (Date.now() - lastTouch) > IDLE_MS)) { location.reload(); return; }
                var a = (mine.match(/^i(\d+)/) || [])[1], b = (j.v.match(/^i(\d+)/) || [])[1];
                var n = (a !== undefined && b !== undefined) ? (parseInt(b, 10) - parseInt(a, 10)) : 0;
                text.textContent = n > 0 ? (n === 1 ? '1 new order has come in.' : n + ' new orders have come in.') : 'Orders have changed.';
                news.hidden = false;
                document.title = (n > 0 ? '(' + n + ') ' : '● ') + baseTitle;
            })
            .catch(function () { /* offline / blip — say nothing */ });
    }
    setInterval(check, 20000);
})();
</script>
    <?php
}
