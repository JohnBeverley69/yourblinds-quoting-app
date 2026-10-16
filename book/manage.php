<?php
declare(strict_types=1);

/**
 * The customer's "change or cancel" page — /book/manage.php?t=<token>, linked
 * from their online-booking emails. No login: the 64-character token IS the
 * key (online_bookings.manage_token). Up to the day before the visit they can
 * move it to another free time or cancel it. Rules: _partials/online_booking.php.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../auth/middleware.php';   // e(), csrf
require_once __DIR__ . '/../_partials/online_booking.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');

$token = strtolower(trim((string) ($_GET['t'] ?? '')));
$b     = ob_manage_load($token);
$self  = '/book/manage.php?t=' . rawurlencode($token);
$msg   = null;
$err   = null;

if ($b !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['_action'] ?? '');
    if ($action === 'cancel') {
        [$ok, $text] = ob_customer_cancel($token);
        $_SESSION['ob_manage_flash'] = [$ok, $text];
        header('Location: ' . $self);
        exit;
    }
    if ($action === 'move') {
        $pick = explode('|', (string) ($_POST['pick'] ?? ''), 2);
        $r = ob_customer_move($token, (string) ($pick[0] ?? ''), (string) ($pick[1] ?? ''));
        $_SESSION['ob_manage_flash'] = [$r['ok'], $r['message']];
        header('Location: ' . $self);
        exit;
    }
}
if (isset($_SESSION['ob_manage_flash'])) {
    [$ok, $text] = $_SESSION['ob_manage_flash'];
    unset($_SESSION['ob_manage_flash']);
    if ($ok) $msg = $text; else $err = $text;
    $b = ob_manage_load($token);   // fresh, after the change
}

if ($b === null) {
    http_response_code(404);
} else {
    $cid     = (int) $b['client_id'];
    $s       = ob_settings($cid);
    $co      = ob_company_contact($cid);
    $st      = db()->prepare('SELECT logo_path FROM clients WHERE id = ? LIMIT 1');
    $st->execute([$cid]);
    $logo    = (string) $st->fetchColumn();
    [$canChange, $whyNot] = ob_manage_can_change($b);
    $whenDay = date('l j F', strtotime((string) $b['appointment_date']));
    $whenAt  = ob_appt_time_label($cid, $b['slot_window'] !== null ? (string) $b['slot_window'] : null,
                   (string) $b['appointment_time'], (int) $b['duration_minutes']);
    $waiting = (int) $b['online_request'] === 1 && (string) $b['appt_status'] === 'booked';
    $moveLeft = OB_MAX_RESCHEDULES - (int) $b['reschedules'];
    $days = $canChange && $moveLeft > 0 && isset($_GET['move'])
        ? ob_available_days($cid, null, ob_postcode((string) $b['installation_postcode']), (int) $b['appointment_id'])
        : null;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $b === null ? 'Booking not found' : 'Your appointment — ' . e($co['name']) ?></title>
<?php require __DIR__ . '/../_partials/online_booking_style.php'; ?>
</head>
<body>
<div class="wrap">
<?php if ($b === null): ?>
    <div class="card">
        <h2>Booking not found</h2>
        <p class="muted">This link isn't working. Please contact the company directly about your appointment.</p>
    </div>
<?php else: ?>
    <div class="brand">
        <?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <h1><?= e($co['name']) ?></h1>
    </div>
    <div class="card">
        <?php if ($msg !== null): ?><div class="done" role="status" style="margin-bottom:14px"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($err !== null): ?><div class="alert" role="alert"><?= e($err) ?></div><?php endif; ?>

        <h2>Your appointment</h2>
        <?php if ((string) $b['appt_status'] === 'booked'): ?>
            <p><strong><?= e($whenDay) ?>, <?= e($whenAt) ?></strong><br>
               <span class="muted"><?= e(ob_postcode_pretty((string) ob_postcode((string) $b['installation_postcode']))) ?></span></p>
            <?php if ($waiting): ?><p class="muted">We're checking our diary and will confirm this by email shortly.</p><?php endif; ?>
        <?php else: ?>
            <p>Your appointment on <?= e($whenDay) ?> is <strong>cancelled</strong>.</p>
            <?php if ((string) ($s['slug'] ?? '') !== ''): ?><p><a href="<?= e(ob_link((string) $s['slug'])) ?>">Book a new appointment</a></p><?php endif; ?>
        <?php endif; ?>

        <?php if ((string) $b['appt_status'] === 'booked' && !$canChange): ?>
            <p class="muted"><?= e($whyNot) ?><?= $co['phone'] !== '' ? ' ' . e($co['phone']) : '' ?></p>
        <?php elseif ($canChange && $days === null): ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:6px">
                <?php if ($moveLeft > 0): ?>
                    <a class="btn" href="<?= e($self) ?>&amp;move=1" style="margin-top:0">Choose a different time</a>
                <?php endif; ?>
                <form method="post" action="<?= e($self) ?>" style="margin:0"
                      onsubmit="return confirm('Cancel your appointment on <?= e($whenDay) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="cancel">
                    <button class="btn" type="submit" style="margin-top:0;background:transparent;color:var(--error);border:1px solid var(--line)">Cancel my appointment</button>
                </form>
            </div>
        <?php elseif ($canChange): ?>
            <h2 style="margin-top:18px">Choose a new time</h2>
            <?php if (!$days): ?>
                <p class="muted">Sorry, there are no other times free online just now.
                   <?php if ($co['phone'] !== ''): ?>Please call us on <?= e($co['phone']) ?>.<?php endif; ?></p>
            <?php else: ?>
            <form method="post" action="<?= e($self) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="move">
                <div class="days">
                <?php foreach ($days as $d): ?>
                    <div class="day">
                        <div class="day-name"><?= e($d['label']) ?></div>
                        <?php foreach ($d['slots'] as $k => $label): ?>
                            <label class="slot" style="margin:0;font-weight:400">
                                <input type="radio" name="pick" value="<?= e($d['date'] . '|' . $k) ?>" required>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <button class="btn" type="submit">Move my appointment</button>
                <a href="<?= e($self) ?>" style="margin-left:12px">Keep my current time</a>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="foot">Booking powered by YourBlinds</div>
<?php endif; ?>
</div>
</body>
</html>
