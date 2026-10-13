<?php
/**
 * Settings → Texts panel (included by admin/settings.php; posts to
 * admin/texts_save.php). Expects $clientId and $client from the page.
 */
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/quote_follow_up.php';

$smsOn       = sms_feature_enabled((int) $clientId);
$qfu         = qfu_settings((int) $clientId);
$smsSettings = sms_settings((int) $clientId);
$smsUsed     = sms_used_this_month((int) $clientId);
$smsLimit    = sms_monthly_limit((int) $clientId);
$smsPct      = $smsLimit > 0 ? min(100, (int) round($smsUsed * 100 / $smsLimit)) : 100;
$smsSender   = (string) ($smsSettings['sms_sender_name'] ?? '');
$smsSuggest  = sms_suggest_sender((string) ($client['company_name'] ?? ''));
$smsSample   = sms_sample_vars($client) + [
    'quote_number' => 'Q1042',
    'quote_link'   => qfu_quote_link(str_repeat('a', 64)),   // same length as a real link
];
$smsHint     = 'color:#6b7280;font-size:0.875rem;margin:0 0 1rem;max-width:44rem';
?>
<div class="settings-panel" data-panel="texts" role="tabpanel" aria-labelledby="tab-texts">

<?php if (!$smsOn): ?>
    <section class="section">
        <div class="section-header"><h2 class="section-title">Customer texts</h2></div>
        <p style="<?= $smsHint ?>">
            Send your customers a text when an appointment is booked, a reminder the day before,
            and a heads-up when the fitter is on the way. Texts go out under your company name.
        </p>
        <p style="<?= $smsHint ?>">
            Customer texts are included in the <strong>Silver</strong> and <strong>Gold</strong> plans.
            <a href="/billing/index.php">See plans</a>
        </p>
    </section>
<?php else: ?>

    <section class="section">
        <div class="section-header"><h2 class="section-title">Texts this month</h2></div>
        <?php $smsBundle = sms_bundle_balance((int) $clientId); ?>
        <p style="margin:0 0 0.5rem"><strong><?= number_format($smsUsed) ?></strong> of your <?= number_format($smsLimit) ?> monthly texts used in <?= e(date('F')) ?><?=
            $smsBundle > 0 ? ' &middot; <strong>' . number_format($smsBundle) . '</strong> bundle texts left' : '' ?>.</p>
        <div style="height:8px;max-width:28rem;background:var(--border);border-radius:999px;overflow:hidden;margin-bottom:0.75rem">
            <div style="height:100%;width:<?= $smsPct ?>%;background:<?= $smsPct >= 100 ? '#dc2626' : ($smsPct >= SMS_WARN_PERCENT ? '#d97706' : '#16a34a') ?>"></div>
        </div>
        <?php if ($smsPct >= 100 && $smsBundle > 0): ?>
            <div class="alert alert-info" role="status">This month's texts are used up, so texts are coming out of your bundle (<?= number_format($smsBundle) ?> left). <a href="/billing/texts.php">Buy more texts &rarr;</a></div>
        <?php elseif ($smsPct >= 100): ?>
            <div class="alert alert-error" role="alert">This month's texts are used up and you have no bundle texts, so no more texts will go out until next month. <a href="/billing/texts.php">Buy more texts &rarr;</a></div>
        <?php elseif ($smsPct >= SMS_WARN_PERCENT): ?>
            <div class="alert alert-info" role="status">You've used <?= $smsPct ?>% of this month's texts. When they run out, texts come from bundles you buy up front. <a href="/billing/texts.php">Buy more texts &rarr;</a></div>
        <?php endif; ?>
        <?php if (!sms_provider_configured()): ?>
            <p style="<?= $smsHint ?>"><strong>Texting isn't switched on for the platform yet</strong> — you can set everything up now and it will start working once it is.</p>
        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section-header"><h2 class="section-title">Customer texts</h2></div>
        <form method="post" action="/admin/texts_save.php" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="save">

            <p style="<?= $smsHint ?>">
                Texts go out under your company name. Customers can't reply to them, so always
                finish with your phone number. To stop texts to one customer, tick
                &ldquo;Don't text this customer&rdquo; on their record.
            </p>

            <div class="form-group" style="max-width:20rem">
                <label for="sms_sender_name">Sender name <span style="color:#9ca3af;font-weight:400">(what the customer sees, up to 11 characters)</span></label>
                <input id="sms_sender_name" name="sms_sender_name" type="text" maxlength="11"
                       value="<?= e($smsSender) ?>" placeholder="<?= e($smsSuggest !== '' ? $smsSuggest : 'e.g. BevBlinds') ?>">
            </div>

            <p style="<?= $smsHint ?>">
                Tick the texts you want sent. You can use:
                <?php $first = true; foreach (sms_placeholders() as $ph => $desc): ?><?= $first ? '' : ', ' ?><code title="<?= e($desc) ?>"><?= e($ph) ?></code><?php $first = false; endforeach; ?>.
                One text is up to 160 characters; longer wording counts as two or more.
            </p>

            <?php foreach (sms_kinds() as $kind => $k):
                $tpl = sms_template((int) $clientId, $kind);
            ?>
                <div style="border:1px solid var(--border);border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.75rem;background:var(--bg-card);max-width:44rem">
                    <label style="display:flex;align-items:center;gap:0.5rem;font-weight:600;cursor:pointer">
                        <input type="checkbox" name="<?= e($k['column']) ?>" value="1" <?= !empty($smsSettings[$k['column']]) ? 'checked' : '' ?>>
                        <?= e($k['label']) ?>
                    </label>
                    <div style="color:#6b7280;font-size:0.8125rem;margin:0.25rem 0 0.5rem 1.6rem"><?= e($k['hint']) ?></div>
                    <textarea name="tpl_<?= e($kind) ?>" rows="3" maxlength="480" class="sms-tpl"
                              data-default="<?= e($k['default']) ?>"
                              style="width:100%;font-family:inherit"><?= e($tpl) ?></textarea>
                    <div style="display:flex;justify-content:space-between;gap:1rem;font-size:0.8125rem;color:#6b7280;margin-top:0.25rem">
                        <span class="sms-preview" style="font-style:italic"></span>
                        <span style="white-space:nowrap">
                            <span class="sms-count"></span>
                            &middot; <a href="#" class="sms-reset">Use the standard wording</a>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save text settings</button>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="section-header"><h2 class="section-title">Quote follow-up</h2></div>
        <form method="post" action="/admin/texts_save.php" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="follow_up">

            <p style="<?= $smsHint ?>">
                A friendly nudge to a customer whose quote is still waiting for an answer. It goes once,
                a set number of days after the quote was sent, between 10am and 8pm. Only quotes to your
                own (retail) customers that haven't been accepted or declined. Re-sending a quote starts the count again.
            </p>

            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:600;cursor:pointer;margin-bottom:0.75rem">
                <input type="checkbox" name="qfu_on" value="1" <?= $qfu['on'] ? 'checked' : '' ?>>
                Send a quote follow-up
            </label>

            <div class="form-row" style="max-width:44rem">
                <div class="form-group">
                    <label for="qfu_days">Days after the quote is sent</label>
                    <input id="qfu_days" name="qfu_days" type="number" min="1" max="25" step="1" value="<?= (int) $qfu['days'] ?>">
                </div>
                <div class="form-group">
                    <span style="display:block;font-weight:600;margin-bottom:0.35rem">Send it by</span>
                    <?php foreach (['sms' => 'Text', 'email' => 'Email', 'both' => 'Text and email'] as $mv => $ml): ?>
                        <label style="display:inline-flex;align-items:center;gap:0.35rem;margin-right:1rem;font-weight:400;cursor:pointer">
                            <input type="radio" name="qfu_method" value="<?= e($mv) ?>" <?= $qfu['method'] === $mv ? 'checked' : '' ?>> <?= e($ml) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <p style="<?= $smsHint ?>">
                You can use:
                <?php $first = true; foreach (qfu_placeholders() as $ph => $desc): ?><?= $first ? '' : ', ' ?><code title="<?= e($desc) ?>"><?= e($ph) ?></code><?php $first = false; endforeach; ?>.
                The link is best kept to the email &mdash; in a text it makes it two texts long.
            </p>

            <div style="border:1px solid var(--border);border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.75rem;background:var(--bg-card);max-width:44rem">
                <div style="font-weight:600;margin-bottom:0.5rem">Text wording</div>
                <textarea name="qfu_text" rows="3" maxlength="480" class="sms-tpl"
                          data-default="<?= e(qfu_defaults()['text']) ?>"
                          style="width:100%;font-family:inherit"><?= e($qfu['text']) ?></textarea>
                <div style="display:flex;justify-content:space-between;gap:1rem;font-size:0.8125rem;color:#6b7280;margin-top:0.25rem">
                    <span class="sms-preview" style="font-style:italic"></span>
                    <span style="white-space:nowrap">
                        <span class="sms-count"></span>
                        &middot; <a href="#" class="sms-reset">Use the standard wording</a>
                    </span>
                </div>
            </div>

            <div style="border:1px solid var(--border);border-radius:8px;padding:0.75rem 1rem;margin-bottom:0.75rem;background:var(--bg-card);max-width:44rem">
                <div style="font-weight:600;margin-bottom:0.5rem">Email wording</div>
                <div class="form-group">
                    <label for="qfu_subject">Subject</label>
                    <input id="qfu_subject" name="qfu_subject" type="text" maxlength="150" value="<?= e($qfu['subject']) ?>">
                </div>
                <div class="form-group">
                    <label for="qfu_email">Message</label>
                    <textarea id="qfu_email" name="qfu_email" rows="9" maxlength="3000" style="width:100%;font-family:inherit"><?= e($qfu['email']) ?></textarea>
                </div>
                <p style="color:#6b7280;font-size:0.8125rem;margin:0">
                    Sent from your company name; replies go to your reply-to address (Settings &rarr; Company).
                    Leave a box empty to use the standard wording.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save quote follow-up</button>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="section-header"><h2 class="section-title">Send a test text</h2></div>
        <form method="post" action="/admin/texts_save.php" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="test">
            <p style="<?= $smsHint ?>">Sends one of your texts, with example details, to your own mobile. Save your settings first.</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="test_to">Your mobile</label>
                    <input id="test_to" name="test_to" type="tel" maxlength="20" placeholder="07…" required>
                </div>
                <div class="form-group">
                    <label for="test_kind">Which text</label>
                    <select id="test_kind" name="test_kind">
                        <?php foreach (sms_kinds() as $kind => $k): ?>
                            <option value="<?= e($kind) ?>"><?= e($k['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Send test text</button>
            </div>
        </form>
    </section>

    <script>
    (function () {
        var sample = <?= json_encode(array_combine(
            array_map(static fn ($k) => '{{' . $k . '}}', array_keys($smsSample)),
            array_values($smsSample)
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        var gsm = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        var ext = "^{}\\[~]|€\f";
        function fill(t) {
            Object.keys(sample).forEach(function (k) { t = t.split(k).join(sample[k]); });
            return t.replace(/ {2,}/g, ' ').trim();
        }
        function parts(t) {
            var len = 0, chars = Array.from(t);
            for (var i = 0; i < chars.length; i++) {
                if (gsm.indexOf(chars[i]) !== -1) len += 1;
                else if (ext.indexOf(chars[i]) !== -1) len += 2;
                else { len = chars.length; return { len: len, n: len <= 70 ? 1 : Math.ceil(len / 67) }; }
            }
            return { len: len, n: len <= 160 ? 1 : Math.ceil(len / 153) };
        }
        document.querySelectorAll('.sms-tpl').forEach(function (ta) {
            var box = ta.parentNode;
            function update() {
                var text = fill(ta.value), p = parts(text);
                box.querySelector('.sms-preview').textContent = text;
                var c = box.querySelector('.sms-count');
                c.textContent = 'About ' + p.len + ' characters = ' + p.n + (p.n === 1 ? ' text' : ' texts');
                c.style.color = p.n > 1 ? '#b45309' : '';
            }
            ta.addEventListener('input', update);
            box.querySelector('.sms-reset').addEventListener('click', function (e) {
                e.preventDefault();
                ta.value = ta.getAttribute('data-default');
                update();
            });
            update();
        });
    })();
    </script>

<?php endif; ?>
</div><!-- /tab: texts -->
