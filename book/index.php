<?php
declare(strict_types=1);

/**
 * Public online booking page — /book/?c=<link name>. No login.
 * See _partials/online_booking.php for the rules and the safety notes.
 *
 * Three steps, each a normal form post then a redirect (so a refresh never
 * books twice):
 *   1. postcode   — checked against the company's area;
 *   2. time       — pick a day + time slot, add your details, book;
 *   3. done       — "requested" or "booked" message.
 * The postcode is carried in the session, never trusted from the form, and is
 * checked again when booking.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';   // e(), csrf, client_ip()
require_once __DIR__ . '/../_partials/online_booking.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex');

$slug    = strtolower(trim((string) ($_GET['c'] ?? '')));
$company = ob_company_by_slug($slug);

if ($company === null) {
    http_response_code(404);
    $pageTitle = 'Booking not available';
    $step = 'missing';
} else {
    $clientId = (int) $company['id'];
    $settings = ob_settings($clientId);
    $self     = '/book/?c=' . rawurlencode($slug);
    $bag      = &$_SESSION['online_booking'][$slug];
    if (!is_array($bag)) $bag = [];
    $step     = (string) ($_GET['step'] ?? 'postcode');
    $error    = null;
    $company['company_name'] = ob_display_name($clientId, (string) $company['company_name']);
    $pageTitle = 'Book an appointment — ' . $company['company_name'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = (string) ($_POST['_action'] ?? '');

        if ($action === 'postcode') {
            $pc = ob_postcode((string) ($_POST['postcode'] ?? ''));
            if ($pc === null) {
                $error = 'Please enter your full postcode, like HU17 9AB.';
                $step  = 'postcode';
            } else {
                $area = ob_area_check($settings, $pc);
                $bag['pc'] = $pc;
                if (!$area['ok']) {
                    $step = 'outside';
                } else {
                    // Address list for "Pick your address" — one paid lookup per
                    // new postcode, kept in the session (ob_lookup_addresses).
                    if (($bag['addr_pc'] ?? '') !== $pc) {
                        $bag['addr']    = ob_lookup_addresses($clientId, $pc, client_ip());
                        $bag['addr_pc'] = $pc;
                    }
                    header('Location: ' . $self . '&step=time');
                    exit;
                }
            }
        } elseif ($action === 'book') {
            $step = 'time';
            $pick = explode('|', (string) ($_POST['pick'] ?? ''), 2);
            $in = [
                'name'     => trim((string) ($_POST['name'] ?? '')),
                'email'    => trim((string) ($_POST['email'] ?? '')),
                'phone'    => trim((string) ($_POST['phone'] ?? '')),
                'address1' => trim((string) ($_POST['address1'] ?? '')),
                'address2' => trim((string) ($_POST['address2'] ?? '')),
                'town'     => trim((string) ($_POST['town'] ?? '')),
                'postcode' => (string) ($bag['pc'] ?? ''),
                'notes'    => trim(str_replace("\r\n", "\n", (string) ($_POST['notes'] ?? ''))),
                'date'     => (string) ($pick[0] ?? ''),
                'slot'     => (string) ($pick[1] ?? ''),
            ];
            $bag['form'] = $in;
            $shownAt = (int) ($bag['shown_at'] ?? 0);

            if (empty($bag['pc'])) {
                header('Location: ' . $self);
                exit;
            } elseif (trim((string) ($_POST['website'] ?? '')) !== '' || $shownAt === 0 || time() - $shownAt < 4) {
                // The hidden trap field was filled in, or the form came back
                // faster than a person can type: a bot. Say nothing useful.
                $error = 'Sorry, something went wrong. Please try again.';
            } elseif (ob_rate_limited($clientId, client_ip())) {
                $error = 'Sorry, we can\'t take any more online bookings from you just now. Please give us a call.';
            } else {
                $res = ob_create_booking($clientId, $in, [
                    'ip'         => client_ip(),
                    'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ]);
                if ($res['ok']) {
                    $bag = ['done' => [
                        'requested' => $res['requested'],
                        'date'      => $in['date'],
                        'slot'      => ob_slot_label($clientId, $settings, $in['slot']),
                        'email'     => $in['email'],
                    ]];
                    header('Location: ' . $self . '&step=done');
                    exit;
                }
                $error = $res['error'];
            }
        }
    }

    if ($step === 'time' && empty($bag['pc'])) $step = 'postcode';
    if ($step === 'done' && empty($bag['done'])) $step = 'postcode';
    if ($step === 'time') {
        $days = ob_available_days($clientId, null, (string) $bag['pc']);
        $bag['shown_at'] = time();
        $form = (array) ($bag['form'] ?? []);
    }
}

$form = $form ?? [];
$v = static fn (string $k): string => e((string) ($form[$k] ?? ''));
$co = $company !== null ? ob_company_contact((int) $company['id']) : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($pageTitle) ?></title>
<?php require __DIR__ . '/../_partials/online_booking_style.php'; ?>
</head>
<body>
<div class="wrap">
<?php if ($step === 'missing'): ?>
    <div class="card">
        <h2>Online booking isn't available</h2>
        <p class="muted">This booking link isn't working at the moment. Please contact the company directly to book.</p>
    </div>
<?php else: ?>
    <div class="brand">
        <?php if (!empty($company['logo_path'])): ?>
            <img src="<?= e((string) $company['logo_path']) ?>" alt="">
        <?php endif; ?>
        <h1><?= e((string) $company['company_name']) ?></h1>
    </div>
    <div class="card">
    <?php if ($error !== null): ?>
        <div class="alert" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'postcode'): ?>
        <h2>Book a free measure and quote</h2>
        <?php if (trim((string) ($settings['intro_text'] ?? '')) !== ''): ?>
            <p class="muted"><?= nl2br(e((string) $settings['intro_text'])) ?></p>
        <?php else: ?>
            <p class="muted">We'll come to you, measure up and give you a quote. Start with your postcode so we can check we cover your area.</p>
        <?php endif; ?>
        <form method="post" action="<?= e($self) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="postcode">
            <label for="postcode">Your postcode</label>
            <input type="text" id="postcode" name="postcode" autocomplete="postal-code" maxlength="10" required
                   value="<?= e(isset($bag['pc']) ? ob_postcode_pretty((string) $bag['pc']) : '') ?>" style="max-width:200px;text-transform:uppercase">
            <div><button class="btn" type="submit">Find a time</button></div>
        </form>

    <?php elseif ($step === 'outside'): ?>
        <h2>Sorry, we can't book <?= e(ob_postcode_pretty((string) $bag['pc'])) ?> online</h2>
        <p class="muted">That postcode is outside the area we take online bookings for. Please get in touch and we'll see what we can do:</p>
        <p>
            <?php if ($co['phone'] !== ''): ?>Call <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $co['phone']) ?? '') ?>"><?= e($co['phone']) ?></a><br><?php endif; ?>
            <?php $mail = $co['reply_to'] !== '' ? $co['reply_to'] : $co['email']; if ($mail !== ''): ?>Email <a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a><?php endif; ?>
        </p>
        <a href="<?= e($self) ?>">Try a different postcode</a>

    <?php elseif ($step === 'time'): ?>
        <h2>Choose a time</h2>
        <p class="muted">For <span class="pc"><?= e(ob_postcode_pretty((string) $bag['pc'])) ?></span> · <a href="<?= e($self) ?>">change</a></p>
        <?php if (!$days): ?>
            <p>Sorry, there are no times free online in the next few weeks.
               <?php if ($co['phone'] !== ''): ?>Please call us on <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $co['phone']) ?? '') ?>"><?= e($co['phone']) ?></a>.<?php endif; ?></p>
        <?php else: ?>
        <form method="post" action="<?= e($self) ?>&amp;step=time">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="book">
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="muted"><?= ob_style($settings) === 'times'
                    ? 'Choose a start time. A visit takes about ' . e(ob_duration_words((int) $settings['visit_minutes'])) . '.'
                    : "We'll arrive at some point within the time you choose." ?></legend>
                <div class="days">
                <?php $picked = ($form['date'] ?? '') . '|' . ($form['slot'] ?? ''); ?>
                <?php foreach ($days as $d): ?>
                    <div class="day">
                        <div class="day-name"><?= e($d['label']) ?></div>
                        <?php foreach ($d['slots'] as $k => $label): $val = $d['date'] . '|' . $k; ?>
                            <label class="slot" style="margin:0;font-weight:400">
                                <input type="radio" name="pick" value="<?= e($val) ?>" required <?= $val === $picked ? 'checked' : '' ?>
                                       data-when="<?= e($d['label'] . ', ' . $label) ?>">
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </fieldset>

            <h2 id="details" style="margin-top:22px;scroll-margin-top:12px">Your details</h2>
            <p id="chosen" class="muted" hidden style="margin:0 0 4px">Your time: <strong id="chosen-when"></strong> ·
                <a href="#" id="chosen-change">change</a></p>
            <label for="name">Name</label>
            <input type="text" id="name" name="name" autocomplete="name" maxlength="120" required value="<?= $v('name') ?>">
            <div class="row2">
                <div>
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" autocomplete="tel" maxlength="20" required value="<?= $v('phone') ?>">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" maxlength="150" required value="<?= $v('email') ?>">
                </div>
            </div>
            <?php $addrList = ($bag['addr_pc'] ?? '') === ($bag['pc'] ?? '') ? (array) ($bag['addr'] ?? []) : []; ?>
            <?php if ($addrList): ?>
                <label for="addr-pick">Pick your address</label>
                <select id="addr-pick">
                    <option value="">Choose from <?= count($addrList) ?> addresses at <?= e(ob_postcode_pretty((string) $bag['pc'])) ?>…</option>
                    <?php foreach ($addrList as $i => $ad): ?>
                        <option value="<?= (int) $i ?>" data-l1="<?= e($ad['line1']) ?>" data-l2="<?= e($ad['line2']) ?>" data-town="<?= e($ad['town']) ?>"><?= e(implode(', ', array_filter([$ad['line1'], $ad['line2']]))) ?></option>
                    <?php endforeach; ?>
                    <option value="none">My address isn't listed — I'll type it</option>
                </select>
            <?php endif; ?>
            <label for="address1">Address</label>
            <input type="text" id="address1" name="address1" autocomplete="address-line1" maxlength="150" required value="<?= $v('address1') ?>">
            <input type="text" id="address2" name="address2" autocomplete="address-line2" maxlength="150" value="<?= $v('address2') ?>" style="margin-top:8px" aria-label="Address line 2">
            <div class="row2">
                <div>
                    <label for="town">Town</label>
                    <input type="text" id="town" name="town" autocomplete="address-level2" maxlength="100" required value="<?= $v('town') ?>">
                </div>
                <div>
                    <label>Postcode</label>
                    <input type="text" value="<?= e(ob_postcode_pretty((string) $bag['pc'])) ?>" disabled>
                </div>
            </div>
            <label for="notes">What would you like a quote for? <span class="muted">(optional)</span></label>
            <textarea id="notes" name="notes" maxlength="1000" placeholder="e.g. 3 roller blinds for the kitchen, shutters in the bay window"><?= $v('notes') ?></textarea>
            <div class="trap" aria-hidden="true">
                <label for="website">Leave this empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <p class="muted" style="margin-top:14px">We'll only use your details to arrange this appointment and your quote.</p>
            <button class="btn" type="submit"><?= (string) $settings['mode'] === 'instant' ? 'Book appointment' : 'Request appointment' ?></button>
        </form>
        <script>
        // Picking a time takes the customer straight down to "Your details",
        // with the chosen time repeated there and a link back up to change it.
        (function () {
            var details = document.getElementById('details'), name = document.getElementById('name');
            var chosen = document.getElementById('chosen'), when = document.getElementById('chosen-when');
            var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            function show(r) { when.textContent = r.dataset.when || ''; chosen.hidden = false; }
            document.querySelectorAll('input[name=pick]').forEach(function (r) {
                if (r.checked) show(r);
                r.addEventListener('change', function () {
                    show(r);
                    details.scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'start' });
                    if (name.value === '') setTimeout(function () { name.focus({ preventScroll: true }); }, calm ? 0 : 450);
                });
            });
            document.getElementById('chosen-change').addEventListener('click', function (e) {
                e.preventDefault();
                var r = document.querySelector('input[name=pick]:checked');
                (r ? r.closest('.day') : document.querySelector('.days')).scrollIntoView({ behavior: calm ? 'auto' : 'smooth', block: 'center' });
            });
        })();
        </script>
        <?php if ($addrList): ?>
        <script>
        document.getElementById('addr-pick').addEventListener('change', function () {
            var o = this.options[this.selectedIndex];
            if (!o.dataset.l1) { if (this.value === 'none') document.getElementById('address1').focus(); return; }
            document.getElementById('address1').value = o.dataset.l1;
            document.getElementById('address2').value = o.dataset.l2 || '';
            document.getElementById('town').value = o.dataset.town || '';
        });
        </script>
        <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($step === 'done'): $done = $bag['done']; $dd = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $done['date']); ?>
        <?php if ($done['requested']): ?>
            <div class="done">Thank you — we've got your request.</div>
            <p>You asked for <strong><?= e($dd ? $dd->format('l j F') : (string) $done['date']) ?>, <?= e((string) $done['slot']) ?></strong>.
               We'll check our diary and confirm by email to <?= e((string) $done['email']) ?> shortly.</p>
        <?php else: ?>
            <div class="done">You're booked in.</div>
            <p>We'll see you on <strong><?= e($dd ? $dd->format('l j F') : (string) $done['date']) ?>, <?= e((string) $done['slot']) ?></strong>.
               A confirmation is on its way to <?= e((string) $done['email']) ?>.</p>
        <?php endif; ?>
        <p class="muted">Need to change anything?
            <?php if ($co['phone'] !== ''): ?>Call us on <?= e($co['phone']) ?> or reply<?php else: ?>Reply<?php endif; ?> to the email.</p>
    <?php endif; ?>
    </div>
    <div class="foot">Booking powered by YourBlinds</div>
<?php endif; ?>
</div>
</body>
</html>
