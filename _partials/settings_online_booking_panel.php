<?php
/**
 * Settings → Online booking panel (included by admin/settings.php; posts to
 * admin/online_booking_save.php). Expects $clientId and $client from the page.
 * See _partials/online_booking.php.
 */
require_once __DIR__ . '/online_booking.php';
require_once __DIR__ . '/qr.php';

$obOn       = ob_feature_enabled((int) $clientId);
$obS        = ob_settings((int) $clientId);
$obSlug     = (string) ($obS['slug'] ?? '');
if ($obSlug === '') $obSlug = ob_suggest_slug((string) ($client['company_name'] ?? ''), (int) $clientId);
[$obLive, $obWhy] = ob_status((int) $clientId);
$obAmpmOn   = ampm_settings(db(), (int) $clientId)['on'];
$obDays     = ob_weekdays($obS);
$obPending  = ob_pending_count((int) $clientId);
$obHint     = 'color:#6b7280;font-size:0.875rem;margin:0 0 1rem;max-width:44rem';
?>
<div class="settings-panel" data-panel="online-booking" role="tabpanel" aria-labelledby="tab-online-booking">

<?php if (!$obOn): ?>
    <section class="section">
        <div class="section-header"><h2 class="section-title">Online booking</h2></div>
        <p style="<?= $obHint ?>">
            Let customers book a measure-and-quote visit themselves, any time of day, from a link on your
            website, Facebook page or Google profile. They pick one of your free time slots and it goes
            straight into your calendar.
        </p>
        <p style="<?= $obHint ?>">
            Online booking is included in the <strong>Silver</strong> and <strong>Gold</strong> plans.
            <a href="/billing/index.php">See plans</a>
        </p>
    </section>
<?php else: ?>

    <section class="section">
        <div class="section-header"><h2 class="section-title">Your booking link</h2></div>
        <?php if ($obLive): ?>
            <div class="alert alert-success" role="status">Online booking is <strong>on</strong>. Customers can book at the link below.</div>
        <?php else: ?>
            <div class="alert alert-info" role="status"><?= e($obWhy) ?></div>
        <?php endif; ?>
        <?php if ($obPending > 0): ?>
            <p><strong><?= $obPending ?></strong> online booking<?= $obPending === 1 ? ' is' : 's are' ?> waiting for you to confirm.
               <a href="/calendar/index.php">Open the calendar</a></p>
        <?php endif; ?>

        <?php if ((string) ($obS['slug'] ?? '') !== ''): $obLink = ob_link((string) $obS['slug']); ?>
            <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start">
                <div style="flex:1 1 20rem">
                    <p style="<?= $obHint ?>">Put this link on your website (a "Book a free quote" button), your Facebook page
                       (Edit → Add a button → Book now) and your Google Business profile (Bookings / Website link).
                       You can also send it in emails and texts.</p>
                    <div style="display:flex;gap:.5rem;max-width:36rem">
                        <input type="text" id="ob-link" value="<?= e($obLink) ?>" readonly style="flex:1" onfocus="this.select()">
                        <button type="button" class="btn btn-secondary btn-sm" id="ob-copy">Copy</button>
                    </div>
                    <p style="margin:.5rem 0 0"><a href="<?= e($obLink) ?>" target="_blank" rel="noopener">Open the booking page &rarr;</a></p>
                </div>
                <div style="text-align:center">
                    <div style="background:#fff;padding:6px;border-radius:6px;display:inline-block;line-height:0"><?= qr_svg($obLink, 30.0, 'M') ?></div>
                    <p style="margin:.4rem 0 0;font-size:.85rem"><a href="/admin/online_booking_save.php?qr=1">Download QR code</a></p>
                    <p style="margin:0;font-size:.8rem;color:#6b7280">For vans, leaflets and the shop window</p>
                </div>
            </div>
            <script>
            document.getElementById('ob-copy').addEventListener('click', function () {
                var i = document.getElementById('ob-link'); i.select();
                try { navigator.clipboard.writeText(i.value); } catch (e) { document.execCommand('copy'); }
                this.textContent = 'Copied';
            });
            </script>
        <?php else: ?>
            <p style="<?= $obHint ?>">Save the settings below to get your link.</p>
        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section-header"><h2 class="section-title">Online booking settings</h2></div>
        <form method="post" action="/admin/online_booking_save.php" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="save">

            <?php if (!$obAmpmOn): ?>
                <p style="<?= $obHint ?>"><strong>Online booking uses your time slots.</strong> Switch on
                   &ldquo;Booking time slots&rdquo; on the <a href="/admin/settings.php#company">Company</a> tab
                   (under Calendar) and set up your slots first. Customers are offered the same slots, with the same
                   number of visits per slot, as your booking form.</p>
            <?php endif; ?>

            <div class="form-row full">
                <label style="display:flex;gap:.5rem;align-items:center;font-weight:600">
                    <input type="checkbox" name="enabled" value="1" <?= (int) $obS['enabled'] === 1 ? 'checked' : '' ?>>
                    Let customers book online
                </label>
            </div>

            <div class="form-group" style="max-width:28rem">
                <label for="ob_slug">Link name <span style="color:#9ca3af;font-weight:400">(letters, numbers and dashes)</span></label>
                <input id="ob_slug" name="slug" type="text" maxlength="50" value="<?= e($obSlug) ?>" pattern="[a-z0-9-]{3,50}">
                <p style="<?= $obHint ?>;margin-top:.25rem">Your link will be <?= e(ob_link('')) ?><strong>link-name</strong>. Changing it stops the old link working.</p>
            </div>

            <fieldset class="form-group" style="border:0;padding:0;margin:0 0 1rem">
                <legend style="font-weight:600;margin-bottom:.25rem">When a customer books</legend>
                <label style="display:flex;gap:.5rem;align-items:flex-start;font-weight:400">
                    <input type="radio" name="mode" value="request" <?= (string) $obS['mode'] !== 'instant' ? 'checked' : '' ?>>
                    <span><strong>I'll confirm it</strong> — the time slot is held in your calendar and the customer is told
                    you'll confirm. You confirm or decline it from the appointment, and they get an email either way.</span>
                </label>
                <label style="display:flex;gap:.5rem;align-items:flex-start;font-weight:400;margin-top:.4rem">
                    <input type="radio" name="mode" value="instant" <?= (string) $obS['mode'] === 'instant' ? 'checked' : '' ?>>
                    <span><strong>Book it straight away</strong> — the customer gets their confirmation (and booking text, if you
                    have texts ticked) there and then.</span>
                </label>
            </fieldset>

            <div style="display:flex;flex-wrap:wrap;gap:1rem 1.5rem">
                <div class="form-group" style="max-width:14rem">
                    <label for="ob_notice">Earliest booking</label>
                    <select id="ob_notice" name="min_notice_days">
                        <?php foreach ([0 => 'Same day', 1 => 'Next day', 2 => '2 days ahead', 3 => '3 days ahead', 5 => '5 days ahead', 7 => 'A week ahead'] as $n => $lab): ?>
                            <option value="<?= $n ?>" <?= (int) $obS['min_notice_days'] === $n ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="max-width:14rem">
                    <label for="ob_ahead">Up to</label>
                    <select id="ob_ahead" name="max_days_ahead">
                        <?php foreach ([7 => '1 week ahead', 14 => '2 weeks ahead', 21 => '3 weeks ahead', 28 => '4 weeks ahead', 42 => '6 weeks ahead', 56 => '8 weeks ahead'] as $n => $lab): ?>
                            <option value="<?= $n ?>" <?= (int) $obS['max_days_ahead'] === $n ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <fieldset class="form-group" style="border:0;padding:0;margin:0 0 1rem">
                <legend style="font-weight:600;margin-bottom:.25rem">Days customers can book</legend>
                <div style="display:flex;flex-wrap:wrap;gap:.25rem 1rem">
                <?php foreach (OB_WEEKDAY_NAMES as $n => $lab): ?>
                    <label style="display:flex;gap:.35rem;align-items:center;font-weight:400">
                        <input type="checkbox" name="weekdays[]" value="<?= $n ?>" <?= in_array($n, $obDays, true) ? 'checked' : '' ?>> <?= e($lab) ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-group" style="max-width:28rem">
                <label for="ob_closed">Closed days <span style="color:#9ca3af;font-weight:400">(holidays, days off)</span></label>
                <textarea id="ob_closed" name="closed_dates" rows="4" placeholder="24/12/2026 - 2/1/2027&#10;31/8/2026"><?= e((string) ($obS['closed_dates'] ?? '')) ?></textarea>
                <p style="<?= $obHint ?>;margin-top:.25rem">One date or range per line. Customers can't book online on these days; your own booking form isn't affected.</p>
            </div>

            <h3 style="font-size:1rem;margin:1.25rem 0 .25rem">Area you cover</h3>
            <p style="<?= $obHint ?>">Set a distance, a list of postcodes, or both — a customer only has to match one.
               Leave both empty to take bookings from anywhere. Customers outside your area are given your phone number and email instead.</p>
            <div style="display:flex;flex-wrap:wrap;gap:1rem 1.5rem">
                <div class="form-group" style="max-width:12rem">
                    <label for="ob_base">Base postcode</label>
                    <input id="ob_base" name="base_postcode" type="text" maxlength="10" value="<?= e((string) ($obS['base_postcode'] ?? '')) ?>" placeholder="HU17 0LD" style="text-transform:uppercase">
                </div>
                <div class="form-group" style="max-width:10rem">
                    <label for="ob_radius">Within (miles)</label>
                    <input id="ob_radius" name="radius_miles" type="number" min="1" max="300" value="<?= e((string) ($obS['radius_miles'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-group" style="max-width:36rem">
                <label for="ob_districts">Postcodes covered <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                <input id="ob_districts" name="districts" type="text" value="<?= e((string) ($obS['districts'] ?? '')) ?>" placeholder="HU, YO25, YO42, DN14 6" style="text-transform:uppercase">
                <p style="<?= $obHint ?>;margin-top:.25rem">Separate with commas. <strong>HU</strong> covers every HU postcode,
                   <strong>YO25</strong> just that district, <strong>DN14 6</strong> just that part of it.
                   Distance is measured in a straight line, so allow a little extra for roads.</p>
            </div>

            <h3 style="font-size:1rem;margin:1.25rem 0 .25rem">Booking page</h3>
            <div class="form-group" style="max-width:36rem">
                <label for="ob_intro">Welcome message <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                <textarea id="ob_intro" name="intro_text" rows="3" maxlength="600" placeholder="We'll come to you, measure up and give you a quote. Start with your postcode so we can check we cover your area."><?= e((string) ($obS['intro_text'] ?? '')) ?></textarea>
            </div>
            <div class="form-group" style="max-width:28rem">
                <label for="ob_notify">Email new bookings to</label>
                <input id="ob_notify" name="notify_email" type="email" maxlength="190" value="<?= e((string) ($obS['notify_email'] ?? '')) ?>"
                       placeholder="<?= e((string) ($client['email'] ?? 'office@example.co.uk')) ?>">
                <p style="<?= $obHint ?>;margin-top:.25rem">Leave empty to use your company email.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save online booking</button>
            </div>
        </form>
    </section>
<?php endif; ?>
</div>
