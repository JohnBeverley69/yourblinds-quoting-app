<?php
declare(strict_types=1);

/**
 * Public: YourBlinds' end-user licence agreement / terms of service (the
 * software company's own terms — NOT a tenant's; those are legal/view.php).
 * Company details come from _partials/platform_legal.php. No login.
 */

require_once __DIR__ . '/../_partials/platform_legal.php';

$co    = htmlspecialchars(PL_COMPANY, ENT_QUOTES, 'UTF-8');
$brand = htmlspecialchars(PL_BRAND, ENT_QUOTES, 'UTF-8');
$mail  = htmlspecialchars(PL_EMAIL, ENT_QUOTES, 'UTF-8');

pl_head(
    'Licence agreement',
    'The terms on which businesses use the YourBlinds quoting, ordering and business-management software.'
);
?>

<h2>1. Who we are and who this is for</h2>
<p><?= $brand ?> is provided by <b><?= $co ?></b>, a company registered in England and Wales under number
   <?= htmlspecialchars(PL_REG_NO, ENT_QUOTES, 'UTF-8') ?>, whose registered office is at
   <?= htmlspecialchars(PL_ADDRESS, ENT_QUOTES, 'UTF-8') ?> (&ldquo;we&rdquo;, &ldquo;us&rdquo;). You can contact us at
   <a href="mailto:<?= $mail ?>"><?= $mail ?></a>.</p>
<p>These terms are an agreement between us and the business that opens a <?= $brand ?> account (&ldquo;you&rdquo;). <?= $brand ?>
   is for <b>businesses only</b>. By creating an account, or by using <?= $brand ?> on behalf of a business, you confirm you are
   acting in the course of that business and have authority to accept these terms for it. The people you give logins to (your
   &ldquo;users&rdquo;) must follow these terms too, and you are responsible for what they do in your account.</p>

<h2>2. The service and your licence to use it</h2>
<p><?= $brand ?> is online software for blinds businesses: pricing and quoting, customers, calendar and appointments, orders,
   invoices and payments, and related tools (together, the &ldquo;service&rdquo;). While your account is active and you keep to
   these terms, we give you a non-exclusive, non-transferable right for your users to use the service for your own business.</p>
<p>You must not:</p>
<ul>
  <li>resell, sub-license or rent the service to anyone else, or let people outside your business use your logins;</li>
  <li>copy, change, reverse-engineer or try to get at the service&rsquo;s source code, except where the law allows it and cannot
      be excluded;</li>
  <li>try to get into other customers&rsquo; accounts or data, test or break the service&rsquo;s security, or overload it (for
      example with automated scraping);</li>
  <li>use the service for anything unlawful, or to store or send content that is unlawful, harmful or infringes someone
      else&rsquo;s rights;</li>
  <li>share your login details. Each user should have their own login.</li>
</ul>
<p>We own the service, its software, design and content, including the price tables and product set-ups we supply. Nothing in
   these terms transfers ownership of them to you.</p>

<h2>3. Your account</h2>
<p>Keep your account details accurate and your passwords safe. Tell us straight away at <a href="mailto:<?= $mail ?>"><?= $mail ?></a>
   if you think someone has got into your account. We may suspend a login we reasonably believe has been compromised.</p>

<h2>4. Plans, fees and payment</h2>
<ul>
  <li>The plans on offer, what each includes and their prices are shown on our <a href="/welcome.php">website</a> and on your
      account&rsquo;s Billing page. Prices are shown excluding VAT unless we say otherwise. VAT is added at the current rate.</li>
  <li>A free plan or free trial may be available. When a trial ends, the account moves to the free plan unless you choose a paid
      one. We may change or withdraw free plans and trials for new sign-ups.</li>
  <li>Paid plans are billed in advance each month through PayPal, and renew automatically until cancelled. We never see or store
      your card details.</li>
  <li>You can cancel at any time from your Billing page or in PayPal. Cancelling stops future payments. You keep the paid features
      until the end of the period already paid for. We don&rsquo;t refund part-periods unless the law requires it.</li>
  <li>We may change our prices. We&rsquo;ll give you at least 30 days&rsquo; notice by email or in the app, and the change applies
      from your next billing period after that. If you don&rsquo;t agree, you can cancel before it takes effect.</li>
  <li>If a payment fails, we may reduce your account to the free plan or suspend paid features until it&rsquo;s paid.</li>
</ul>

<h2>5. Your data</h2>
<p><b>Your data stays yours.</b> Everything you and your users put into the service (your customers, quotes, orders, prices,
   settings and files) belongs to you. You give us permission to store and process it only so we can run the service for you,
   support you, keep it secure and back it up.</p>
<p>You are responsible for having the right to put that data into the service, including telling your own customers how you use
   their personal information. Your own privacy notice to them should cover this. For your customers&rsquo; personal data, you are
   the <b>controller</b> and we are your <b>processor</b>. Section 6 sets out our commitments as your processor.</p>
<p>You can export your data (for example from Settings &rarr; Back up data, and the CSV exports on the Payments page) at any
   time while your account is open.</p>

<h2>6. Data processing terms (UK GDPR)</h2>
<p>When we process personal data on your behalf, we will:</p>
<ul>
  <li>process it only to provide the service and on your documented instructions (these terms, and how you set up and use the
      service), unless the law requires otherwise, in which case we&rsquo;ll tell you unless the law forbids it;</li>
  <li>make sure our staff and contractors who can access it are bound by confidentiality;</li>
  <li>keep appropriate technical and organisational security measures in place, including encrypted connections (HTTPS), hashed
      passwords, encryption of stored accounting-link tokens, access controls that keep each business&rsquo;s data separate, and
      regular backups;</li>
  <li>use only the sub-processors listed in our <a href="/legal/privacy.php">privacy policy</a>, under written terms that protect
      the data at least as well as these. We&rsquo;ll update that list before adding or replacing one, and you may object on
      reasonable grounds. If we can&rsquo;t resolve it, you can end these terms;</li>
  <li>where data is transferred outside the UK, make sure it&rsquo;s covered by UK adequacy regulations or appropriate safeguards
      (such as the ICO&rsquo;s International Data Transfer Agreement or Addendum);</li>
  <li>help you, as far as reasonably possible, to respond to people exercising their data-protection rights, and with security,
      breach notification and data-protection impact assessments;</li>
  <li>tell you without undue delay after becoming aware of a personal-data breach affecting your data;</li>
  <li>when your account ends, delete your data as set out in section 11, unless the law requires us to keep it;</li>
  <li>make available the information reasonably needed to show we meet these obligations, and allow for reasonable audits on
      reasonable notice, at your cost.</li>
</ul>
<p>The personal data involved is mainly your customers&rsquo; and staff&rsquo;s names, contact details and addresses,
   appointment details, and details of the goods quoted, ordered and paid for. It&rsquo;s processed for as long as your account
   is open, to provide the service.</p>

<h2>7. Connections to other services</h2>
<p>The service can link to other providers you choose to use, such as your accounting package (for example QuickBooks Online),
   payment providers, mapping and address look-up. You connect these on the other provider&rsquo;s own site, and your use of them
   is governed by <b>their</b> terms. When you connect one, you tell us to send to it, and receive from it, the data needed for
   that link. For example, once sending is switched on, paid sales go to your accounting package using the settings you chose. You
   can disconnect at any time. We&rsquo;re not responsible for other providers&rsquo; services, availability or changes, though
   we&rsquo;ll try to keep our links working.</p>

<h2>8. Prices, quotes and figures &mdash; please check them</h2>
<p>The service calculates prices, quotes, measurements, cut sizes, VAT and totals from the price tables, rules and settings you
   and your suppliers provide. We work hard to get the calculations right, but <b>you are responsible for checking quotes,
   orders, invoices and figures before you rely on them or send them to anyone</b>, and for the prices and terms you give your
   own customers. Supplier price tables we supply are provided for convenience. Your supplier&rsquo;s own current price list
   always takes priority.</p>

<h2>9. Availability, support and changes</h2>
<p>We aim to keep the service running all the time, but we don&rsquo;t promise it will be uninterrupted or error-free. We may take
   it offline for maintenance, usually out of hours. Support is available through the <b>? Help</b> button in the app and at
   <a href="mailto:<?= $mail ?>"><?= $mail ?></a>. We improve the service regularly and may add, change or remove features. We
   won&rsquo;t make a change that substantially reduces what a paid plan includes without telling you first.</p>
<p>We take daily backups and keep them for a limited time. They exist so we can recover the service, not as an archive for you. Keep
   your own copies of anything important using the export tools.</p>

<h2>10. Our responsibility to you</h2>
<ul>
  <li>Nothing in these terms limits liability that can&rsquo;t legally be limited, such as for death or personal injury caused by
      negligence, or for fraud.</li>
  <li>We are not liable for loss of profit, revenue, business, goodwill or anticipated savings, or for any indirect or
      consequential loss.</li>
  <li>We are not liable for losses caused by prices, quotes or figures that you did not check (see section 8), by data you or
      your users entered, or by other providers&rsquo; services (see section 7).</li>
  <li>Otherwise, our total liability to you in any 12-month period, however it arises, is limited to the fees you paid us in
      that period. On a free plan, that is &pound;100.</li>
  <li>Apart from what these terms say, the service is provided without other warranties or conditions, to the extent the law
      allows.</li>
</ul>

<h2>11. Ending the agreement</h2>
<ul>
  <li>You can close your account at any time by contacting us. Export anything you want to keep first.</li>
  <li>We may suspend or close an account if you seriously or repeatedly break these terms, don&rsquo;t pay, or use the service
      in a way that puts other customers or the service at risk. Where it&rsquo;s reasonable, we&rsquo;ll warn you first and give
      you a chance to put it right.</li>
  <li>We may stop providing the service altogether on at least 90 days&rsquo; notice. In that case we&rsquo;ll give you the chance
      to export your data.</li>
  <li>After an account closes, we delete its data within 90 days, and from backups as they roll over, unless you ask us to delete
      it sooner or the law requires us to keep something (for example our own billing records).</li>
</ul>

<h2>12. Changes to these terms</h2>
<p>We may update these terms. The date at the top shows when they last changed. If a change is significant, we&rsquo;ll tell you by
   email or in the app at least 30 days before it applies. If you keep using the service after that, the new terms apply. If you
   don&rsquo;t agree, you can close your account before then.</p>

<h2>13. General</h2>
<ul>
  <li>These terms, with our <a href="/legal/privacy.php">privacy policy</a>, are the whole agreement between us about the service.</li>
  <li>If any part is found unenforceable, the rest still applies.</li>
  <li>You may not transfer your account or these terms without our agreement. We may transfer them to a business that takes over
      the service, and will tell you if we do.</li>
  <li>Neither of us is liable for delays or failures caused by events outside our reasonable control.</li>
  <li>Only you and we have rights under these terms. No one else can enforce them.</li>
  <li>These terms are governed by the law of England and Wales, and the courts of England and Wales have exclusive jurisdiction.</li>
</ul>

<?php pl_foot();
