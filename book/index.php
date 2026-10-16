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
                        'slot'      => ob_window_label($clientId, $in['slot']),
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
        $days = ob_available_days($clientId);
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
<style>
    :root {
        --bg: #f4f5f7; --card: #ffffff; --text: #1f2937; --muted: #6b7280; --line: #e5e7eb;
        --accent: #1d4ed8; --accent-soft: #e8efff; --error-bg: #fef2f2; --error: #b91c1c; --ok: #047857; --ok-bg: #ecfdf5;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #111318; --card: #1b1e25; --text: #e5e7eb; --muted: #9ca3af; --line: #2d323c;
            --accent: #7aa2ff; --accent-soft: #1f2a44; --error-bg: #3a1717; --error: #fca5a5; --ok: #6ee7b7; --ok-bg: #11302a;
        }
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .wrap { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .brand img { max-height: 56px; max-width: 180px; object-fit: contain; }
    .brand h1 { font-size: 1.25rem; margin: 0; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
    h2 { font-size: 1.15rem; margin: 0 0 4px; }
    .muted { color: var(--muted); font-size: .925rem; }
    label { display: block; font-weight: 600; font-size: .925rem; margin: 14px 0 4px; }
    input[type=text], input[type=email], input[type=tel], textarea {
        width: 100%; padding: 10px 12px; font: inherit; color: var(--text); background: var(--card);
        border: 1px solid var(--line); border-radius: 8px;
    }
    textarea { min-height: 80px; resize: vertical; }
    .btn { display: inline-block; margin-top: 18px; padding: 11px 20px; font: inherit; font-weight: 600; color: #fff;
           background: var(--accent); border: 0; border-radius: 8px; cursor: pointer; text-decoration: none; }
    @media (prefers-color-scheme: dark) { .btn { color: #0b1020; } }
    .alert { padding: 10px 12px; border-radius: 8px; margin: 0 0 14px; background: var(--error-bg); color: var(--error); }
    .done { background: var(--ok-bg); color: var(--ok); padding: 12px; border-radius: 8px; font-weight: 600; }
    .days { margin-top: 10px; border-top: 1px solid var(--line); }
    .day { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 0; border-bottom: 1px solid var(--line); }
    .day-name { width: 92px; font-weight: 600; font-size: .925rem; }
    .slot { position: relative; }
    .slot input { position: absolute; opacity: 0; inset: 0; }
    .slot span { display: inline-block; padding: 7px 12px; border: 1px solid var(--line); border-radius: 999px; font-size: .9rem; cursor: pointer; }
    .slot input:checked + span { background: var(--accent); border-color: var(--accent); color: #fff; }
    @media (prefers-color-scheme: dark) { .slot input:checked + span { color: #0b1020; } }
    .slot input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }
    .trap { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
    @media (max-width: 480px) {
        .row2 { grid-template-columns: 1fr; } .day-name { width: 100%; }
        .card { padding: 16px 14px; } .day { gap: 6px; } .slot span { padding: 6px 9px; font-size: .84rem; }
    }
    .foot { margin-top: 18px; text-align: center; font-size: .8rem; color: var(--muted); }
    .pc { font-weight: 600; }
    a { color: var(--accent); }
</style>
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
                <legend class="muted">We'll arrive at some point within the time you choose.</legend>
                <div class="days">
                <?php $picked = ($form['date'] ?? '') . '|' . ($form['slot'] ?? ''); ?>
                <?php foreach ($days as $d): ?>
                    <div class="day">
                        <div class="day-name"><?= e($d['label']) ?></div>
                        <?php foreach ($d['slots'] as $k => $label): $val = $d['date'] . '|' . $k; ?>
                            <label class="slot" style="margin:0;font-weight:400">
                                <input type="radio" name="pick" value="<?= e($val) ?>" required <?= $val === $picked ? 'checked' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </fieldset>

            <h2 style="margin-top:22px">Your details</h2>
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
