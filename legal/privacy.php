<?php
declare(strict_types=1);

/**
 * Public: YourBlinds' own privacy policy (the software company's — NOT a
 * tenant's; those are legal/view.php?doc=privacy). Company details come from
 * _partials/platform_legal.php. No login.
 *
 * KEEP THE SUB-PROCESSOR TABLE TRUE: the licence (section 6) promises this
 * list is updated before a new one is added. Add a row whenever the app
 * starts sending data to a new outside service.
 */

require_once __DIR__ . '/../_partials/platform_legal.php';

$co    = htmlspecialchars(PL_COMPANY, ENT_QUOTES, 'UTF-8');
$brand = htmlspecialchars(PL_BRAND, ENT_QUOTES, 'UTF-8');
$mail  = htmlspecialchars(PL_EMAIL, ENT_QUOTES, 'UTF-8');

pl_head(
    'Privacy policy',
    'How YourBlinds collects, uses and protects personal information, and the choices and rights you have.'
);
?>

<h2>1. Who we are</h2>
<p><?= $brand ?> is provided by <b><?= $co ?></b>, a company registered in England and Wales under number
   <?= htmlspecialchars(PL_REG_NO, ENT_QUOTES, 'UTF-8') ?>, registered office
   <?= htmlspecialchars(PL_ADDRESS, ENT_QUOTES, 'UTF-8') ?>. For anything about your personal information, email
   <a href="mailto:<?= $mail ?>"><?= $mail ?></a>.</p>

<h2>2. Two different roles</h2>
<ul>
  <li><b>Our own customers and visitors.</b> For information about the businesses that use <?= $brand ?>, their staff who log
      in, and visitors to our website, <b>we</b> decide how it&rsquo;s used. We are the <b>controller</b>, and this policy explains
      what we do.</li>
  <li><b>Our customers&rsquo; customers.</b> Blinds businesses use <?= $brand ?> to keep details of <em>their</em> customers
      (names, addresses, measurements, quotes, orders and payments). For that information the <b>blinds business is the
      controller</b>, and we only process it for them, as their <b>processor</b>, under our <a href="/legal/licence.php">licence
      agreement</a>. If you are one of their customers, their own privacy notice applies. Please contact them first, and we&rsquo;ll
      help them respond.</li>
</ul>

<h2>3. What we collect</h2>
<ul>
  <li><b>Account details</b>: business name, address, phone, email, VAT number and similar details you give us when you sign up
      or fill in your settings. Also the names, email addresses and roles of the users you add.</li>
  <li><b>Login and security information</b>: passwords (stored only as a one-way hash, never readable), login times, IP
      addresses and similar technical records, used to keep accounts secure.</li>
  <li><b>Billing information</b>: your plan, subscription status and payment history. Payments are taken by PayPal. We never see
      or store your card or bank details.</li>
  <li><b>Support messages</b>: what you send through the <b>? Help</b> button or by email, including any screenshots or voice
      notes you choose to attach. If you use the Help chat assistant, your messages are processed by our AI provider to produce
      answers (see section 6).</li>
  <li><b>Accounting link details</b>: if you connect an accounting package such as QuickBooks Online, the company name and
      ID it gives us and the access tokens that let us talk to it (stored encrypted). We never see your accounting password.</li>
  <li><b>Usage information</b>: server logs of pages requested, errors and performance, used to run and improve the service.</li>
</ul>

<h2>4. Why we use it, and our lawful basis</h2>
<div class="tablewrap"><table>
  <tr><th>What for</th><th>Lawful basis (UK GDPR)</th></tr>
  <tr><td>Providing the service, logins, support and your account</td><td>Contract, to provide what you signed up for</td></tr>
  <tr><td>Billing and keeping financial records</td><td>Contract, and legal obligation (tax and accounting records)</td></tr>
  <tr><td>Security, preventing misuse, backups, fixing faults</td><td>Legitimate interests, keeping the service safe and working</td></tr>
  <tr><td>Improving the service from usage and support patterns</td><td>Legitimate interests</td></tr>
  <tr><td>Service emails (receipts, security alerts, important changes)</td><td>Contract / legitimate interests</td></tr>
  <tr><td>Occasional news about <?= $brand ?> to account holders</td><td>Legitimate interests. You can opt out at any time</td></tr>
</table></div>
<p>We don&rsquo;t sell personal information. We don&rsquo;t use it for advertising. We don&rsquo;t make decisions about people by
   automated means that have legal or similarly significant effects.</p>

<h2>5. Cookies and storage on your device</h2>
<p>We use only what the service needs to work:</p>
<ul>
  <li>a <b>session cookie</b> that keeps you logged in. It is essential and is removed when you log out or close the browser;</li>
  <li>a small <b>preference cookie</b> that remembers display choices such as compact mode;</li>
  <li><b>storage in your browser</b> for things like unsaved work and, where you use it, working offline, so you don&rsquo;t lose
      what you&rsquo;re doing if the connection drops.</li>
</ul>
<p>We don&rsquo;t use advertising or tracking cookies. Some screens load maps from Google, and Google may set its own cookies
   when they do.</p>

<h2>6. Who we share it with (sub-processors)</h2>
<p>We use a small number of trusted providers to run the service. They may only use the information to provide their service to
   us:</p>
<div class="tablewrap"><table>
  <tr><th>Provider</th><th>What they do</th><th>What they receive</th></tr>
  <tr><td>Cloudways (DigitalOcean Holdings)</td><td>Hosts the servers the service and its database run on</td><td>All data held in the service</td></tr>
  <tr><td>AuthSMTP</td><td>Sends the service&rsquo;s emails (quotes, notifications, password resets)</td><td>Email addresses and message content</td></tr>
  <tr><td>PayPal</td><td>Takes subscription payments</td><td>Billing contact and payment details (entered directly with PayPal)</td></tr>
  <tr><td>Google (Maps Platform)</td><td>Maps, directions and address search</td><td>Addresses and locations being looked up</td></tr>
  <tr><td>Postcoder (Allies Computing)</td><td>UK postcode-to-address look-up</td><td>Postcodes being looked up</td></tr>
  <tr><td>Anthropic</td><td>AI assistant in the ? Help chat</td><td>Messages you type or speak into the chat, and page context</td></tr>
  <tr><td>GitHub (Microsoft)</td><td>Where we store and manage the service&rsquo;s code, including fixes prompted by support reports</td><td>A technical description of a reported problem, with names, contact details and addresses removed first. Never your stored business data</td></tr>
  <tr><td>Intuit (QuickBooks Online)</td><td>Only if <em>you</em> connect it: your accounting package</td><td>The sales and settings you choose to send it</td></tr>
</table></div>
<p>We may also share information if the law requires it, to protect our rights or others&rsquo; safety, or with a business that
   takes over the service (which would have to protect it in the same way).</p>

<h2>7. Information leaving the UK</h2>
<p>Some of these providers are based in, or use servers in, other countries, including the United States. Where personal
   information goes outside the UK, we make sure it&rsquo;s protected: either the country is covered by UK adequacy regulations
   (for the US, the UK&ndash;US &ldquo;data bridge&rdquo; for certified companies), or the transfer is covered by the ICO&rsquo;s
   International Data Transfer Agreement or Addendum, or equivalent safeguards.</p>

<h2>8. How long we keep it</h2>
<ul>
  <li><b>Account and business data</b>: for as long as the account is open. After it closes, we delete it within 90 days.</li>
  <li><b>Backups</b>: daily backups are kept for about two weeks and then overwritten, so deleted data drops out of them within
      that time.</li>
  <li><b>Billing and financial records</b>: six years, as UK tax law requires.</li>
  <li><b>Support messages</b>: for as long as they&rsquo;re useful for helping you, and no more than two years after the issue
      is closed.</li>
  <li><b>Server logs</b>: kept only for a short period for security and fault-finding.</li>
</ul>

<h2>9. How we keep it safe</h2>
<p>Everything travels over encrypted connections (HTTPS). Passwords are stored as one-way hashes. Accounting-link tokens are
   encrypted when stored. Each business&rsquo;s data is kept separate from every other&rsquo;s, and access inside each account
   follows the roles you give your users. We keep regular backups and restrict who can get into the servers. No system is
   completely risk-free. If a breach affects your information and puts you at risk, we&rsquo;ll tell you and, where required, the
   ICO.</p>

<h2>10. Your rights</h2>
<p>Under UK data-protection law you can ask us to:</p>
<ul>
  <li>give you a copy of the personal information we hold about you;</li>
  <li>correct anything that&rsquo;s wrong;</li>
  <li>delete it, or limit how we use it, in some circumstances;</li>
  <li>give it to you, or another provider, in a portable format;</li>
  <li>stop using it where we rely on legitimate interests (including for news emails).</li>
</ul>
<p>Email <a href="mailto:<?= $mail ?>"><?= $mail ?></a>. We&rsquo;ll reply within one month. It&rsquo;s free unless a request is
   clearly unfounded or excessive. If your information is held by a blinds business that uses <?= $brand ?> (section 2), ask them
   and we&rsquo;ll help them.</p>
<p>If you&rsquo;re unhappy with how we&rsquo;ve handled your information, please tell us first. You also have the right to
   complain to the <b>Information Commissioner&rsquo;s Office</b>, <a href="https://ico.org.uk" rel="noopener">ico.org.uk</a>,
   0303 123 1113.</p>

<h2>11. Children</h2>
<p><?= $brand ?> is a business service and isn&rsquo;t intended for anyone under 18.</p>

<h2>12. Changes to this policy</h2>
<p>We&rsquo;ll update this policy when what we do changes. The date at the top shows the latest version. If a change is
   significant, we&rsquo;ll tell account holders by email or in the app.</p>

<?php pl_foot();
