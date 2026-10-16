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
$obStyle    = ob_style($obS);
$obHours    = ob_hours($obS);
$obSales    = count(ob_sales_ids((int) $clientId));
$obStaff    = ob_staff((int) $clientId, true);
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
                    <?php
                        $obBtn = '<a href="' . e($obLink) . '" target="_blank" rel="noopener" style="display:inline-block;'
                               . 'padding:12px 22px;background:#1d4ed8;color:#ffffff;border-radius:8px;'
                               . 'font:600 16px/1.2 Arial,sans-serif;text-decoration:none">Book a free measure &amp; quote</a>';
                    ?>
                    <details style="margin-top:.75rem;max-width:36rem">
                        <summary style="cursor:pointer;font-weight:600">A ready-made button for your website</summary>
                        <p style="<?= $obHint ?>;margin:.5rem 0">Copy this and paste it into your website where you want the
                           button &mdash; or send it to whoever looks after your website. It looks like this:</p>
                        <p><?= $obBtn ?></p>
                        <div style="display:flex;gap:.5rem;align-items:flex-start">
                            <textarea id="ob-btn" readonly rows="4" style="flex:1;font-family:monospace;font-size:.8rem" onfocus="this.select()"><?= e($obBtn) ?></textarea>
                            <button type="button" class="btn btn-secondary btn-sm" id="ob-btn-copy">Copy</button>
                        </div>
                    </details>
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
            document.getElementById('ob-btn-copy').addEventListener('click', function () {
                var i = document.getElementById('ob-btn'); i.select();
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

            <div class="form-group" style="max-width:28rem">
                <label for="ob_display">Name customers see <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                <input id="ob_display" name="display_name" type="text" maxlength="150" value="<?= e((string) ($obS['display_name'] ?? '')) ?>"
                       placeholder="<?= e((string) ($client['company_name'] ?? '')) ?>">
                <p style="<?= $obHint ?>;margin-top:.25rem">On the booking page and its emails. Leave empty to use your company name.</p>
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

            <fieldset class="form-group" style="border:0;padding:0;margin:0 0 1rem">
                <legend style="font-weight:600;margin-bottom:.25rem">How customers pick a time</legend>
                <label style="display:flex;gap:.5rem;align-items:flex-start;font-weight:400">
                    <input type="radio" name="booking_style" value="slots" <?= $obStyle === 'slots' ? 'checked' : '' ?>>
                    <span><strong>Time slots</strong> — they choose one of your Booking time slots, such as Morning or
                    Afternoon, with the same number of visits per slot as your own booking form.
                    <?php if (!$obAmpmOn): ?><br><em>Your Booking time slots are switched off — switch them on on the
                    <a href="/admin/settings.php#company">Company</a> tab (under Calendar) to use this.</em><?php endif; ?></span>
                </label>
                <label style="display:flex;gap:.5rem;align-items:flex-start;font-weight:400;margin-top:.4rem">
                    <input type="radio" name="booking_style" value="times" <?= $obStyle === 'times' ? 'checked' : '' ?>>
                    <span><strong>Exact times</strong> — they choose a start time, such as 10:30am. Set your hours
                    beside each day below, how long a visit takes and the gap you need between visits.</span>
                </label>
            </fieldset>

            <div class="ob-times-only" style="display:flex;flex-wrap:wrap;gap:1rem 1.5rem">
                <div class="form-group" style="max-width:14rem">
                    <label for="ob_visit">A visit takes</label>
                    <select id="ob_visit" name="visit_minutes">
                        <?php foreach ([30, 45, 60, 90, 120, 150, 180] as $n): ?>
                            <option value="<?= $n ?>" <?= (int) $obS['visit_minutes'] === $n ? 'selected' : '' ?>><?= e(ob_duration_words($n)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="max-width:16rem">
                    <label for="ob_gap">Gap after each visit <span style="color:#9ca3af;font-weight:400">(travel)</span></label>
                    <select id="ob_gap" name="gap_minutes">
                        <?php foreach ([0 => 'No gap', 15 => '15 minutes', 30 => '30 minutes', 45 => '45 minutes', 60 => '1 hour'] as $n => $lab): ?>
                            <option value="<?= $n ?>" <?= (int) $obS['gap_minutes'] === $n ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p style="<?= $obHint ?>;flex-basis:100%;margin-top:-.5rem">
                    <?php $obMon = ob_day_starts($obS, 1); ?>
                    <?php if ($obStyle === 'times' && $obMon): ?>Start times on Monday: <?= e(implode(', ', array_map('ob_time_label', $obMon))) ?>.<?php endif; ?>
                    Up to <strong><?= max(1, $obSales) ?></strong> visit<?= max(1, $obSales) === 1 ? '' : 's' ?> can happen at the same time
                    &mdash; one for each person with the Sales role<?= $obSales === 0 ? " (you have none, so it's one at a time)" : '' ?>.
                    A time someone is already booked for isn't offered.
                </p>
            </div>

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
                <div id="ob-days" style="display:flex;flex-wrap:wrap;gap:.25rem 1rem">
                <?php foreach (OB_WEEKDAY_NAMES as $n => $lab): ?>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <label style="display:flex;gap:.35rem;align-items:center;font-weight:400;min-width:3.5rem;margin:0">
                            <input type="checkbox" name="weekdays[]" value="<?= $n ?>" <?= in_array($n, $obDays, true) ? 'checked' : '' ?>> <?= e($lab) ?>
                        </label>
                        <span class="ob-times-only" style="display:flex;gap:.35rem;align-items:center">
                            <input type="time" name="hours_from[<?= $n ?>]" value="<?= e($obHours[$n]['from']) ?>" aria-label="<?= e($lab) ?> from" style="width:8rem">
                            to
                            <input type="time" name="hours_to[<?= $n ?>]" value="<?= e($obHours[$n]['to']) ?>" aria-label="<?= e($lab) ?> to" style="width:8rem">
                        </span>
                    </div>
                <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    var form = document.getElementById('ob-days').closest('form');
                    function sync() {
                        var times = (form.querySelector('input[name=booking_style]:checked') || {}).value === 'times';
                        form.querySelectorAll('.ob-times-only').forEach(function (el) { el.style.display = times ? 'flex' : 'none'; });
                        document.getElementById('ob-days').style.flexDirection = times ? 'column' : 'row';
                    }
                    form.querySelectorAll('input[name=booking_style]').forEach(function (r) { r.addEventListener('change', sync); });
                    sync();
                })();
                </script>
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

            <h3 style="font-size:1rem;margin:1.25rem 0 .25rem">Who goes</h3>
            <?php if (!$obStaff): ?>
                <p style="<?= $obHint ?>">Nobody has the <strong>Sales</strong> role, so online bookings come in unassigned
                   for you to give out. Give your salespeople the Sales role on <a href="/admin/users.php">Users</a> to
                   share bookings out automatically and give each one their own area.</p>
            <?php else: ?>
                <div class="form-row full">
                    <label style="display:flex;gap:.5rem;align-items:center;font-weight:400">
                        <input type="checkbox" name="auto_assign" value="1" <?= (int) ($obS['auto_assign'] ?? 1) === 1 ? 'checked' : '' ?>>
                        Give each online booking to a salesperson automatically
                    </label>
                    <p style="<?= $obHint ?>;margin:.25rem 0 0 1.6rem">It goes to someone whose area covers the customer and who is
                       free, sharing the work out (whoever has fewest visits that day). Untick to leave them unassigned.</p>
                </div>
                <div style="overflow-x:auto;max-width:48rem;margin:.75rem 0">
                <table class="table" style="width:100%;font-size:.9rem">
                    <thead><tr><th style="text-align:left">Takes online bookings</th><th style="text-align:left">Home postcode</th>
                        <th style="text-align:left">Within (miles)</th><th style="text-align:left">Postcodes covered</th></tr></thead>
                    <tbody>
                    <?php foreach ($obStaff as $st): ?>
                        <tr>
                            <td><label style="display:flex;gap:.4rem;align-items:center;font-weight:400;margin:0">
                                <input type="hidden" name="staff[<?= (int) $st['id'] ?>][seen]" value="1">
                                <input type="checkbox" name="staff[<?= (int) $st['id'] ?>][takes]" value="1" <?= $st['takes'] ? 'checked' : '' ?>>
                                <?= e($st['name']) ?></label></td>
                            <td><?= $st['home'] !== null ? e(ob_postcode_pretty($st['home'])) : '<a href="/admin/users_edit.php?id=' . (int) $st['id'] . '">Add</a>' ?></td>
                            <td><input type="number" name="staff[<?= (int) $st['id'] ?>][radius]" min="1" max="300" style="width:6rem"
                                       value="<?= $st['radius'] !== null ? (int) $st['radius'] : '' ?>" aria-label="<?= e($st['name']) ?> miles"></td>
                            <td><input type="text" name="staff[<?= (int) $st['id'] ?>][districts]" style="width:100%;min-width:10rem"
                                       value="<?= e(implode(', ', $st['districts'])) ?>" placeholder="anywhere" aria-label="<?= e($st['name']) ?> postcodes"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p style="<?= $obHint ?>">Leave someone's miles and postcodes empty and they cover your whole area. Miles are measured
                   from their home postcode (set on their user record). If nobody's area covers a customer, anyone free can take it.</p>
                <div class="form-group" style="max-width:20rem">
                    <label for="ob_group">Group visits by area</label>
                    <select id="ob_group" name="group_miles">
                        <?php foreach ([0 => 'Off', 5 => 'Within 5 miles', 10 => 'Within 10 miles', 15 => 'Within 15 miles', 20 => 'Within 20 miles', 30 => 'Within 30 miles'] as $n => $lab): ?>
                            <option value="<?= $n ?>" <?= (int) ($obS['group_miles'] ?? 0) === $n ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="<?= $obHint ?>;margin-top:.25rem">On a day a salesperson already has a visit, only offer their time to
                       customers within this distance of it &mdash; so nobody criss-crosses the map.</p>
                </div>
            <?php endif; ?>

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
