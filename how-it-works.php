<?php
declare(strict_types=1);

/**
 * Public "How it works" tour — the retail journey, played out.
 *
 * A play-through walkthrough (Play / Pause / Prev / Next, chapter dots and
 * optional spoken narration) that follows one job through YourBlinds from
 * the first phone call to getting paid. Each scene pairs an animated mock of
 * the real screen with a short, upbeat explanation.
 *
 * Linked from welcome.php. No login required, purely presentational — the
 * mocks are static HTML/CSS, nothing here reads tenant data.
 *
 * Keep the claims TRUE to the app: every scene describes something the app
 * really does today (checked 2026-10-07). If a feature changes, change its
 * scene. Tier tags (Silver/Gold) must match _partials/billing_plans.php.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/app_settings.php';

$loggedIn      = is_logged_in();
$signupsClosed = app_setting_on('signups_paused');

// ── The scenes ────────────────────────────────────────────────────────
// nav   — chapter label;  icon — chapter emoji;  secs — minimum on-screen
// time (the narration can hold a scene longer);  tier — optional plan tag;
// mock  — the animated screen. Animation classes (a-fade, a-type, …) take
// their start time from --d, so each mock reads like a little timeline.
$SCENES = [
    [
        'nav'   => 'Book it', 'icon' => '📞', 'secs' => 9,
        'title' => 'The phone rings. Booked in seconds.',
        'body'  => 'One quick form does the lot. Type the customer\'s name, pop in the postcode and, on our Silver and Gold packages, the address fills itself in. Then book it your way: give the customer an exact time, or a window like morning, afternoon or evening, set up just the way you like them. Your choice. Make YourBlinds work the way you want to work. Choose who\'s going, hit save, and the customer record is created as you book. Booking in windows? Your customer gets a confirmation email with their slot. And the double-booking guard makes sure nobody is ever sent to two places at once.',
        'tier'  => 'Postcode finder on Silver & Gold',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>New appointment</span></div>
  <div class="hw-body hw-form">
    <div class="hw-call a-pop" style="--d:.1s"><span class="a-ring" style="--d:.4s">📞</span> Incoming call&hellip;</div>
    <div class="hw-row"><label>Customer</label><div class="hw-in"><span class="a-type" style="--d:.8s">Mrs Priya Patel</span></div></div>
    <div class="hw-row"><label>Postcode</label><div class="hw-in"><span class="a-type" style="--d:1.6s">LS17 6AB</span><em class="hw-mini a-pop" style="--d:2.3s">Find address</em></div></div>
    <div class="hw-row"><label>Address</label><div class="hw-in"><span class="a-fade" style="--d:2.7s">14 Elm Grove, Leeds</span></div></div>
    <div class="hw-row"><label>When</label><div class="hw-chips"><span class="hw-seg"><i>Exact time</i><i class="on">Windows</i></span><span class="hw-chip a-sel" style="--d:3.4s">Morning</span><span class="hw-chip">Afternoon</span><span class="hw-chip">Evening</span></div></div>
    <div class="hw-row"><label>Who&rsquo;s going</label><div class="hw-in"><span class="a-type" style="--d:4s">Sam &middot; Sales</span></div></div>
    <div class="hw-row"><label></label><div class="hw-tick a-fade" style="--d:4.6s">&#9745; Email the customer their slot</div></div>
    <div class="hw-row"><label></label><div class="hw-btn a-press" style="--d:5.2s">Save appointment</div></div>
    <div class="hw-toast a-pop" style="--d:5.8s">&#10003; Booked &mdash; confirmation emailed to Mrs Patel</div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Calendar', 'icon' => '🗓️', 'secs' => 9,
        'title' => 'On the right calendar. Instantly.',
        'body'  => 'Set your team up as users and every job lands on the right person\'s calendar the moment you hit save. Each of them simply signs in to YourBlinds on their own phone or tablet, with nothing to download, and their day is right there, always up to date. No texts, no whiteboard, no "did you get my message?". Fitters can be set to see just their fittings, and the office sees the whole team side by side, a column per person. Plans changed? Just drag the job to a new slot.',
        'tier'  => 'Every user, on their own phone or tablet',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>Calendar &middot; Day view &middot; Tue 14 Oct</span></div>
  <div class="hw-body hw-day">
    <div class="hw-times"><span>8:00</span><span>10:00</span><span>12:00</span><span>14:00</span><span>16:00</span></div>
    <div class="hw-col"><h5>Sam &middot; Sales</h5>
      <div class="hw-appt hw-new a-drop a-glow" style="--d:.5s;top:12%;height:22%"><b>Measure</b> Mrs Patel<small>Morning &middot; LS17</small></div>
      <div class="hw-appt" style="top:58%;height:20%"><b>Measure</b> Mr Jones</div>
    </div>
    <div class="hw-col"><h5>Dave &middot; Fitter</h5>
      <div class="hw-appt fit" style="top:20%;height:28%"><b>Fitting</b> Ms Green</div>
      <div class="hw-appt fit" style="top:62%;height:22%"><b>Fitting</b> The Hollies</div>
    </div>
    <div class="hw-col"><h5>Jo &middot; Sales</h5>
      <div class="hw-appt" style="top:36%;height:22%"><b>Measure</b> Dr Khan</div>
    </div>
    <div class="hw-phone hw-float a-fade" style="--d:2s">
      <div class="hw-notch"></div>
      <div class="hw-ph-title">Sam&rsquo;s day</div>
      <div class="hw-appt hw-new a-drop" style="--d:2.8s;position:relative"><b>Measure</b> Mrs Patel<small>Morning &middot; 14 Elm Grove</small></div>
      <div class="hw-appt" style="position:relative"><b>Measure</b> Mr Jones<small>Afternoon</small></div>
      <div class="hw-ph-note a-fade" style="--d:3.6s">On Sam&rsquo;s calendar the moment you saved</div>
    </div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Route', 'icon' => '🗺️', 'secs' => 8,
        'title' => 'Hit the road with your day mapped out.',
        'body'  => 'Open your run and the whole day is on one map, with directions from home, through every visit, and back again. One tap hands the address to Google Maps or Waze, so you\'re never fumbling with postcodes at the kerb.',
        'tier'  => 'Silver',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>Today&rsquo;s run</span></div>
  <div class="hw-body hw-map">
    <svg viewBox="0 0 420 260" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <g class="hw-roads">
        <path d="M0 60 L420 40"/><path d="M0 170 L420 200"/><path d="M90 0 L130 260"/><path d="M250 0 L300 260"/><path d="M0 240 L420 120"/><path d="M360 0 L330 260"/>
      </g>
      <path class="hw-route a-draw" style="--d:.6s" pathLength="100" d="M70 215 L115 150 L160 95 L225 92 L290 72 L325 130 L345 190 L210 228 L70 215"/>
      <g class="a-pop" style="--d:1.4s"><circle cx="160" cy="95" r="12"/><text x="160" y="99">1</text></g>
      <g class="a-pop" style="--d:2.1s"><circle cx="290" cy="72" r="12"/><text x="290" y="76">2</text></g>
      <g class="a-pop" style="--d:2.8s"><circle cx="345" cy="190" r="12"/><text x="345" y="194">3</text></g>
      <text class="hw-home" x="70" y="222">🏠</text>
    </svg>
    <div class="hw-stops a-fade" style="--d:.3s">
      <b>Tue 14 Oct &middot; 3 visits</b>
      <span><i>1</i> 9:00 &nbsp;Mrs Patel &middot; LS17</span>
      <span><i>2</i> 11:00 Mr Jones &middot; LS8</span>
      <span><i>3</i> 14:00 Dr Khan &middot; LS6</span>
    </div>
    <div class="hw-go a-pop" style="--d:3.6s"><span class="a-press" style="--d:4.3s">Navigate &#10148;</span></div>
    <div class="hw-toast a-pop" style="--d:4.9s">Opening Google Maps&hellip; &nbsp;or Waze, your choice</div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Measure', 'icon' => '📐', 'secs' => 10,
        'title' => 'Price it in their living room.',
        'body'  => 'Measure room by room on your tablet. Choose the blind, the fabric and the options, and watch the price build right in front of your customer, worked out from your own price lists and product rules, so it\'s right every time. Add as many rooms and blinds as the job needs.',
        'tier'  => '',
        'mock'  => <<<'HTML'
<div class="hw-tab">
  <div class="hw-body hw-quote">
    <div class="hw-q-head"><b>Quote Q-1042 &middot; Mrs Patel</b><span class="hw-mini">Live price</span></div>
    <div class="hw-line a-fade" style="--d:.6s">
      <span class="hw-room">Lounge</span>
      <span class="hw-what">Roller blind &middot; 1200 &times; 1500<em><i class="hw-sw" style="background:#cfc6b8"></i>Linen Dove</em>
        <span class="hw-opts"><span class="hw-opt a-pop" style="--d:1.3s">Chrome chain</span><span class="hw-opt a-pop" style="--d:1.6s">White bottom bar</span></span></span>
      <b class="hw-price">£189</b>
    </div>
    <div class="hw-line a-fade" style="--d:2.4s">
      <span class="hw-room">Kitchen</span>
      <span class="hw-what">Vertical blind &middot; 2100 &times; 1200<em><i class="hw-sw" style="background:#9fb3c2"></i>Harbour Blue</em></span>
      <b class="hw-price">£236</b>
    </div>
    <div class="hw-line a-fade" style="--d:3.6s">
      <span class="hw-room">Bedroom</span>
      <span class="hw-what">Blackout roller &middot; 900 &times; 1300<em><i class="hw-sw" style="background:#4b5563"></i>Slate</em></span>
      <b class="hw-price">£217</b>
    </div>
    <div class="hw-total">Total <b data-count="642" data-prefix="£" data-d=".6" data-dur="3.6">£642</b></div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Offline', 'icon' => '📶', 'secs' => 10,
        'title' => 'No signal? Keep on selling.',
        'body'  => 'Farmhouse in the hills, or a basement flat with no bars? It doesn\'t matter. Set your tablet up for offline once, on WiFi, and it keeps working wherever you are. It prices every blind on the tablet itself, using exactly the same pricing as the server, starts brand-new quotes, and even queues the email to your customer. Nothing is lost. The moment the signal comes back, it all sends itself, and every price is checked again on the way up.',
        'tier'  => 'Tablets, set up once on WiFi',
        'mock'  => <<<'HTML'
<div class="hw-tab">
  <div class="hw-body hw-offline">
    <div class="hw-net">
      <span class="hw-net-off a-out" style="--d:5.2s">&#128245; No signal &mdash; working on this tablet</span>
      <span class="hw-net-on a-fade" style="--d:5.2s">&#128246; Signal back &mdash; sending&hellip;</span>
    </div>
    <div class="hw-q-head"><b>New quote &middot; Mr &amp; Mrs Hughes</b><span class="hw-mini">On this tablet</span></div>
    <div class="hw-line a-fade" style="--d:.7s">
      <span class="hw-room">Study</span>
      <span class="hw-what">Roller blind &middot; 1000 &times; 1400<em><i class="hw-sw" style="background:#d6c9a8"></i>Oat</em></span>
      <b class="hw-price">£164</b>
    </div>
    <div class="hw-line a-fade" style="--d:1.7s">
      <span class="hw-room">Landing</span>
      <span class="hw-what">Vertical blind &middot; 1600 &times; 1800<em><i class="hw-sw" style="background:#a5b4a0"></i>Sage</em></span>
      <b class="hw-price">£198</b>
    </div>
    <div class="hw-queue a-pop" style="--d:2.9s">&#9993; Email to customer &mdash; <b>queued, waiting for signal</b> &#9203;</div>
    <div class="hw-sync">
      <span class="a-fly" style="--d:5.9s">&#10003; Quote saved as Q-1043</span>
      <span class="a-fly" style="--d:6.5s">&#10003; Prices checked by the server</span>
      <span class="a-fly" style="--d:7.1s">&#10003; Email sent to Mr &amp; Mrs Hughes</span>
    </div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Sign', 'icon' => '✍️', 'secs' => 9,
        'title' => 'Send it. They sign it. Done.',
        'body'  => 'Email the quote as a smart PDF, or send it straight to WhatsApp. Your customer opens it on their phone, types their name to accept, and sees exactly what deposit is due and how to pay. They get a thank-you email, and you get a signed-off job without chasing a single bit of paperwork.',
        'tier'  => '',
        'mock'  => <<<'HTML'
<div class="hw-split">
  <div class="hw-win hw-send"><div class="hw-bar"><i></i><i></i><i></i><span>Send to customer</span></div>
    <div class="hw-body">
      <div class="hw-sbtn">&#9993; Email PDF</div>
      <div class="hw-sbtn wa a-press" style="--d:.7s">&#128172; WhatsApp</div>
      <div class="hw-sbtn">&#128279; Copy link</div>
      <div class="hw-fly a-fly" style="--d:1.2s">Quote Q-1042 sent &#10148;</div>
    </div>
  </div>
  <div class="hw-phone">
    <div class="hw-notch"></div>
    <div class="hw-ph-title">Your Blinds Co.</div>
    <div class="hw-ph-card a-fade" style="--d:1.6s">
      <b>Quote Q-1042</b><span>3 blinds &middot; <b>£642.00</b></span>
      <span class="hw-dep">Deposit due £200 &middot; pay by bank transfer</span>
      <div class="hw-in sm"><span class="a-type" style="--d:2.6s">Priya Patel</span></div>
      <div class="hw-tick a-fade" style="--d:3.4s">&#9745; I agree to the terms</div>
      <div class="hw-btn ok a-press" style="--d:4s">Accept quote</div>
    </div>
    <div class="hw-yes a-pop" style="--d:4.6s">&#10003; Accepted &mdash; thank you, Priya!</div>
    <div class="hw-ph-note a-fade" style="--d:5.3s">&#128231; Thank-you email on its way</div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Order', 'icon' => '📦', 'secs' => 9,
        'title' => 'Accepted? The app takes it from there.',
        'body'  => 'The moment they accept, the measure visit closes and a fitting job is already waiting in your Pending Fitting tray. Then one click sends each supplier just their own lines, with a spec sheet attached. No retyping, no copy-and-paste orders, no mistakes.',
        'tier'  => '',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>Quote Q-1042 &middot; Mrs Patel</span></div>
  <div class="hw-body hw-order">
    <div class="hw-job">
      <b>Mrs Patel &middot; 3 blinds &middot; £642</b>
      <span class="hw-pills">
        <span class="hw-pill q a-out" style="--d:.6s">Quote</span>
        <span class="hw-pill acc a-mid" style="--d:.6s;--d2:3.2s">Accepted</span>
        <span class="hw-pill ord a-fade" style="--d:3.3s">Ordered</span>
      </span>
    </div>
    <div class="hw-auto">
      <span class="a-fade" style="--d:1.2s">&#10003; Measure visit closed</span>
      <span class="hw-tray a-pop" style="--d:1.8s">&#128450; Pending fitting &middot; Mrs Patel</span>
    </div>
    <div class="hw-btn a-press" style="--d:2.6s">Send to suppliers</div>
    <div class="hw-sups">
      <div class="hw-sup"><b>Roller Co.</b><span class="a-fly" style="--d:3.2s">&#9993; 2 lines + spec sheet</span></div>
      <div class="hw-sup"><b>Vertical Ltd</b><span class="a-fly" style="--d:3.8s">&#9993; 1 line + spec sheet</span></div>
    </div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Track', 'icon' => '📊', 'secs' => 9,
        'title' => 'Every job. Every pound. One board.',
        'body'  => 'The pipeline shows exactly where every job is, from quote to paid, and how much money is sitting at each stage. Your dashboard keeps score on what\'s selling and what\'s coming up next. You\'ll never have to wonder where a job has got to.',
        'tier'  => '',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>Pipeline</span></div>
  <div class="hw-body hw-pipe">
    <div class="hw-track"><span class="hw-chipjob a-travel">Mrs Patel &middot; £642</span></div>
    <div class="hw-stages">
      <div class="hw-st"><b>Quote</b><span>12 jobs</span><em data-count="8420" data-prefix="£" data-d=".3" data-dur="2">£8,420</em><i class="a-grow" style="--d:.3s;--h:62%"></i></div>
      <div class="hw-st"><b>Accepted</b><span>5 jobs</span><em data-count="3150" data-prefix="£" data-d=".5" data-dur="2">£3,150</em><i class="a-grow" style="--d:.5s;--h:30%"></i></div>
      <div class="hw-st"><b>Ordered</b><span>7 jobs</span><em data-count="4980" data-prefix="£" data-d=".7" data-dur="2">£4,980</em><i class="a-grow" style="--d:.7s;--h:42%"></i></div>
      <div class="hw-st"><b>Fitted</b><span>3 jobs</span><em data-count="1940" data-prefix="£" data-d=".9" data-dur="2">£1,940</em><i class="a-grow" style="--d:.9s;--h:20%"></i></div>
      <div class="hw-st"><b>Invoiced</b><span>4 jobs</span><em data-count="2610" data-prefix="£" data-d="1.1" data-dur="2">£2,610</em><i class="a-grow" style="--d:1.1s;--h:26%"></i></div>
      <div class="hw-st paid"><b>Paid</b><span>18 jobs</span><em data-count="12300" data-prefix="£" data-d="1.3" data-dur="2">£12,300</em><i class="a-grow" style="--d:1.3s;--h:88%"></i></div>
    </div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Fit', 'icon' => '🔧', 'secs' => 8,
        'title' => 'Blinds in? Fitting booked in one drag.',
        'body'  => 'When the order lands, drag the fitting from the tray onto a day, and it\'s on your fitter\'s calendar straight away. Blinds up, customer happy, mark it fitted. Job done.',
        'tier'  => '',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>Calendar &middot; Week of 20 Oct</span></div>
  <div class="hw-body hw-fit">
    <div class="hw-trayp"><b>Pending fitting</b><div class="hw-ghost">Mrs Patel</div><div class="hw-appt fit" style="position:relative">The Hollies</div></div>
    <div class="hw-week">
      <span class="hd">Mon</span><span class="hd">Tue</span><span class="hd">Wed</span><span class="hd">Thu</span><span class="hd">Fri</span>
      <span><i class="hw-appt fit">Ms Green</i></span><span></span><span><i class="hw-appt fit">Mr Ali</i></span><span class="hw-target"></span><span><i class="hw-appt fit">Fox Hall</i></span>
      <span></span><span><i class="hw-appt fit">Dr Khan</i></span><span></span><span></span><span></span>
    </div>
    <div class="hw-drag a-drag"><b>Fitting</b> Mrs Patel<span class="hw-ok a-pop" style="--d:4.4s">&#10003; Fitted</span><svg class="hw-cur" viewBox="0 0 16 22" aria-hidden="true"><path d="M1 1 L1 17 L5 13 L8 20 L11 19 L8 12 L14 12 Z"/></svg></div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Paid', 'icon' => '💷', 'secs' => 9,
        'title' => 'And then you get paid.',
        'body'  => 'Email the invoice, with the balance due, in one tap. Record the payment, and the job settles itself to Paid and emails your customer a receipt. On Gold, Accounts shows everything outstanding at a glance, ready to export for your accountant.',
        'tier'  => 'Accounts on Gold',
        'mock'  => <<<'HTML'
<div class="hw-win"><div class="hw-bar"><i></i><i></i><i></i><span>Mrs Patel &middot; Invoice INV-2088</span></div>
  <div class="hw-body hw-paid">
    <div class="hw-inv">
      <div class="hw-invrow"><span>Total</span><b>£642.00</b></div>
      <div class="hw-invrow"><span>Deposit paid</span><b>&minus;£200.00</b></div>
      <div class="hw-invrow due"><span>Balance due</span><b>£442.00</b></div>
      <div class="hw-btn a-press" style="--d:.6s">&#9993; Email invoice</div>
      <div class="hw-invrow pay a-fade" style="--d:1.8s"><span>Payment &middot; bank transfer</span><b>£442.00</b></div>
      <div class="hw-stamp a-stamp" style="--d:2.6s">PAID</div>
      <div class="hw-ph-note a-fade" style="--d:3.4s">&#128231; Receipt emailed to Mrs Patel</div>
    </div>
    <div class="hw-acc a-fade" style="--d:.3s">
      <span>Accounts</span>
      <small>Outstanding</small>
      <b data-count="5738" data-from="6180" data-prefix="£" data-d="2.4" data-dur="1.6">£5,738</b>
      <small>Received this month</small>
      <b data-count="9842" data-from="9400" data-prefix="£" data-d="2.4" data-dur="1.6">£9,842</b>
    </div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'That\'s it', 'icon' => '🎉', 'secs' => 6,
        'title' => 'From the first ring to money in the bank.',
        'body'  => 'That\'s YourBlinds: one app for the whole journey. Start free today, and add Maps and Accounts whenever you\'re ready.',
        'tier'  => '',
        'mock'  => '__FINALE__',
    ],
];
$sceneCount = count($SCENES);

/** Narration: the title and body as one plain string. */
function hw_say(array $s): string
{
    return trim($s['title'] . ' ' . $s['body']);
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How it works &middot; YourBlinds</title>
    <meta name="description" content="See how YourBlinds runs a blinds business from the first phone call to getting paid — booking, calendar, route, measuring, quotes customers sign on their phone, supplier orders, fitting and invoices.">
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .hw-wrap { max-width: 1120px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
        .hw-top { display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .hw-brand { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.01em;
            color: var(--brand, #1f3b5b); text-decoration: none; }
        .hw-brand .accent { color: var(--brand-accent, #2563eb); }
        [data-theme="dark"] .hw-brand { color: var(--text-primary); }
        .hw-top-links { display: flex; gap: 0.9rem; align-items: center; flex-wrap: wrap; }
        .hw-top-links a { font-size: 0.95rem; text-decoration: none; color: var(--text-secondary); }
        .hw-top-links a:hover { color: var(--link); }

        .hw-hero { text-align: center; padding: 0.5rem 0 1.5rem; }
        .hw-hero h1 { font-size: clamp(1.8rem, 5vw, 2.6rem); line-height: 1.1; margin: 0 0 0.6rem;
            text-wrap: balance; color: var(--text-primary); }
        .hw-hero p { font-size: 1.1rem; color: var(--text-secondary); max-width: 60ch; margin: 0 auto; }

        .hw-cta { display: inline-block; border-radius: 10px; padding: 0.7rem 1.4rem; font-weight: 700;
            font-size: 1rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
        .hw-cta.primary { background: var(--link, #2563eb); color: #fff; }
        .hw-cta.primary:hover { filter: brightness(1.06); }
        .hw-cta.ghost { background: transparent; color: var(--text-primary); border-color: var(--border-strong); }
        .hw-cta.ghost:hover { background: var(--bg-subtle); }

        /* ── Player ─────────────────────────────────────────── */
        .hw-player { --hw-accent: var(--link, #2563eb); --hw-ok: #16a34a; --hw-fit: #0f9f6e;
            --hw-card: var(--bg-card, #fff); --hw-sub: var(--bg-subtle, #f6f8fb); --hw-line: var(--border, #e2e8ef);
            --hw-ink: var(--text-primary, #1c2733); --hw-soft: var(--text-secondary, #5b6b7b); --hw-faint: var(--text-faint, #8a9aa8);
            background: var(--hw-card); border: 1px solid var(--hw-line); border-radius: 18px;
            box-shadow: var(--shadow-sm, 0 2px 10px rgba(0,0,0,.06)); overflow: hidden; position: relative; }
        .hw-stage { position: relative; }
        .hw-scene[hidden] { display: none; }
        .hw-scene { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); gap: 1.75rem;
            align-items: center; padding: 1.75rem; }
        @media (max-width: 860px) { .hw-scene { grid-template-columns: minmax(0, 1fr); gap: 1.1rem; padding: 1rem; } }

        .hw-text .hw-step { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--hw-accent); }
        .hw-text h2 { font-size: clamp(1.35rem, 3vw, 1.85rem); line-height: 1.15; margin: 0.35rem 0 0.7rem;
            color: var(--hw-ink); text-wrap: balance; }
        .hw-text p { margin: 0; color: var(--hw-soft); font-size: 1.02rem; line-height: 1.55; }
        .hw-tier { display: inline-block; margin-top: 0.85rem; font-size: 0.75rem; font-weight: 700;
            border-radius: 999px; padding: 0.15rem 0.6rem; background: var(--hw-sub); color: var(--hw-soft);
            border: 1px solid var(--hw-line); }

        /* Controls */
        .hw-controls { display: flex; align-items: center; gap: 0.6rem; padding: 0.8rem 1.25rem;
            border-top: 1px solid var(--hw-line); background: var(--hw-sub); flex-wrap: wrap; }
        .hw-ctl { border: 1px solid var(--hw-line); background: var(--hw-card); color: var(--hw-ink);
            border-radius: 8px; padding: 0.4rem 0.75rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; }
        .hw-ctl:hover { border-color: var(--hw-accent); }
        .hw-ctl.main { background: var(--hw-accent); color: #fff; border-color: var(--hw-accent); min-width: 6.5rem; }
        .hw-dots { display: flex; gap: 0.3rem; flex: 1 1 auto; overflow-x: auto; scrollbar-width: none; padding: 2px; }
        .hw-dots::-webkit-scrollbar { display: none; }
        .hw-dot { flex: 0 0 auto; border: 1px solid var(--hw-line); background: var(--hw-card); color: var(--hw-soft);
            border-radius: 999px; padding: 0.25rem 0.6rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .hw-dot.seen { color: var(--hw-ink); }
        .hw-dot.on { background: var(--hw-accent); border-color: var(--hw-accent); color: #fff; }
        .hw-progress { height: 3px; background: var(--hw-line); }
        .hw-progress i { display: block; height: 100%; width: 0; background: var(--hw-accent); transition: width .4s ease; }

        /* Start overlay */
        .hw-start { position: absolute; inset: 0; z-index: 20; display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 0.8rem; background: color-mix(in srgb, var(--hw-card) 72%, transparent);
            backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); border: 0; width: 100%; cursor: pointer; color: var(--hw-ink); }
        .hw-start[hidden] { display: none; }
        .hw-start .big { width: 84px; height: 84px; border-radius: 50%; background: var(--hw-accent); color: #fff;
            display: grid; place-items: center; font-size: 2rem; padding-left: 6px; box-shadow: 0 10px 30px rgba(37,99,235,.35);
            animation: hwBeat 1.8s ease-in-out infinite; }
        .hw-start b { font-size: 1.2rem; }
        .hw-start small { color: var(--hw-soft); font-size: 0.9rem; }
        @keyframes hwBeat { 0%,100% { transform: scale(1) } 50% { transform: scale(1.07) } }

        /* ── Mock screens (shared) ─────────────────────────── */
        .hw-screen { position: relative; min-height: 330px; aspect-ratio: 16 / 10.5; font-size: 13px; color: var(--hw-ink);
            display: flex; align-items: stretch; justify-content: center; }
        @media (max-width: 560px) { .hw-screen { font-size: 11px; min-height: 290px; aspect-ratio: auto; } }
        .hw-win { flex: 1 1 auto; display: flex; flex-direction: column; border: 1px solid var(--hw-line); border-radius: 12px;
            background: var(--hw-card); overflow: hidden; box-shadow: 0 12px 30px rgba(15,23,42,.10); min-width: 0; }
        .hw-bar { display: flex; align-items: center; gap: 5px; padding: 7px 10px; background: var(--hw-sub);
            border-bottom: 1px solid var(--hw-line); font-size: 0.85em; color: var(--hw-soft); }
        .hw-bar i { width: 9px; height: 9px; border-radius: 50%; background: var(--hw-line); }
        .hw-bar span { margin-left: 8px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hw-body { position: relative; flex: 1 1 auto; padding: 12px; min-height: 0; }
        .hw-btn { display: inline-block; background: var(--hw-accent); color: #fff; border-radius: 7px; padding: 6px 12px;
            font-weight: 700; }
        .hw-btn.ok { background: var(--hw-ok); display: block; text-align: center; margin-top: 6px; }
        .hw-toast { position: absolute; left: 50%; bottom: 12px; transform: translateX(-50%); white-space: nowrap;
            background: #0f172a; color: #fff; border-radius: 999px; padding: 6px 14px; font-weight: 600; font-size: .92em; z-index: 3; }
        .hw-in { flex: 1 1 auto; border: 1px solid var(--hw-line); border-radius: 6px; padding: 4px 8px; min-height: 2em;
            display: flex; align-items: center; gap: 8px; background: var(--hw-card); min-width: 0; }
        .hw-in.sm { margin: 6px 0 4px; }
        .hw-mini { font-style: normal; font-size: .8em; font-weight: 700; color: var(--hw-accent); margin-left: auto;
            border: 1px solid var(--hw-accent); border-radius: 5px; padding: 1px 6px; white-space: nowrap; }
        .hw-tick { color: var(--hw-soft); }
        .hw-appt { position: absolute; left: 4px; right: 4px; border-radius: 6px; padding: 4px 6px; font-size: .85em; line-height: 1.25;
            background: color-mix(in srgb, var(--hw-accent) 12%, var(--hw-card)); border-left: 3px solid var(--hw-accent);
            color: var(--hw-ink); overflow: hidden; display: block; font-style: normal; }
        .hw-appt b { display: block; font-size: .85em; color: var(--hw-accent); }
        .hw-appt small { display: block; color: var(--hw-soft); }
        .hw-appt.fit { background: color-mix(in srgb, var(--hw-fit) 13%, var(--hw-card)); border-left-color: var(--hw-fit); }
        .hw-appt.fit b { color: var(--hw-fit); }
        .hw-phone { position: relative; width: 190px; max-width: 46%; border: 6px solid #1e293b; border-radius: 24px;
            background: var(--hw-card); padding: 18px 9px 10px; display: flex; flex-direction: column; gap: 6px;
            box-shadow: 0 14px 34px rgba(15,23,42,.22); flex: 0 0 auto; }
        .hw-notch { position: absolute; top: 5px; left: 50%; transform: translateX(-50%); width: 46px; height: 6px;
            border-radius: 6px; background: #1e293b; }
        .hw-ph-title { font-weight: 800; font-size: .95em; }
        .hw-ph-note { font-size: .82em; color: var(--hw-soft); }

        /* 1 · Booking form */
        .hw-form { display: flex; flex-direction: column; gap: 7px; }
        .hw-row { display: flex; align-items: center; gap: 8px; }
        .hw-row > label { flex: 0 0 82px; color: var(--hw-soft); font-size: .88em; }
        .hw-chips { display: flex; gap: 5px; flex-wrap: wrap; }
        .hw-chip { border: 1px solid var(--hw-line); border-radius: 999px; padding: 2px 8px; font-size: .88em; }
        .hw-seg { display: inline-flex; border: 1px solid var(--hw-line); border-radius: 999px; overflow: hidden; font-size: .82em; margin-right: 4px; }
        .hw-seg i { font-style: normal; padding: 2px 8px; color: var(--hw-soft); }
        .hw-seg i.on { background: var(--hw-sub); color: var(--hw-ink); font-weight: 700; }
        .hw-call { align-self: flex-start; background: color-mix(in srgb, var(--hw-ok) 14%, var(--hw-card)); color: var(--hw-ok);
            border-radius: 999px; padding: 3px 10px; font-weight: 700; }
        .hw-call span { display: inline-block; }

        /* 2 · Day view */
        .hw-day { display: grid; grid-template-columns: 40px repeat(3, minmax(0, 1fr)); gap: 6px; }
        .hw-times { display: flex; flex-direction: column; justify-content: space-between; padding-top: 22px;
            font-size: .78em; color: var(--hw-faint); }
        .hw-col { position: relative; background: var(--hw-sub); border-radius: 8px; min-height: 230px; }
        .hw-col h5 { margin: 0; padding: 5px 6px; font-size: .82em; color: var(--hw-soft); border-bottom: 1px solid var(--hw-line); }
        .hw-new { box-shadow: 0 0 0 2px color-mix(in srgb, var(--hw-accent) 40%, transparent); }
        .hw-float { position: absolute; right: 10px; bottom: 10px; width: 150px; z-index: 2; }
        .hw-float .hw-appt { left: 0; right: 0; }
        @media (max-width: 560px) { .hw-float { width: 118px; } .hw-day { grid-template-columns: 30px repeat(3, minmax(0,1fr)); } }

        /* 3 · Map */
        .hw-map { padding: 0; overflow: hidden; background: color-mix(in srgb, #86efac 14%, var(--hw-card)); }
        .hw-map svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .hw-roads path { stroke: color-mix(in srgb, var(--hw-ink) 12%, transparent); stroke-width: 7; fill: none; stroke-linecap: round; }
        .hw-route { stroke: var(--hw-accent); stroke-width: 4.5; fill: none; stroke-linejoin: round; stroke-linecap: round; }
        .hw-map circle { fill: var(--hw-accent); stroke: #fff; stroke-width: 3; }
        .hw-map text { fill: #fff; font-size: 12px; font-weight: 800; text-anchor: middle; }
        .hw-map g { transform-box: fill-box; transform-origin: center; }
        .hw-map .hw-home { font-size: 20px; }
        .hw-stops { position: absolute; left: 10px; top: 10px; background: var(--hw-card); border: 1px solid var(--hw-line);
            border-radius: 9px; padding: 7px 10px; display: flex; flex-direction: column; gap: 3px; font-size: .88em;
            box-shadow: 0 6px 16px rgba(15,23,42,.10); }
        .hw-stops i { display: inline-grid; place-items: center; width: 15px; height: 15px; border-radius: 50%;
            background: var(--hw-accent); color: #fff; font-style: normal; font-size: .75em; font-weight: 800; margin-right: 3px; }
        .hw-go { position: absolute; right: 12px; top: 12px; }
        .hw-go span { display: inline-block; background: var(--hw-ok); color: #fff; font-weight: 800; border-radius: 8px; padding: 7px 13px; }

        /* 4 · Tablet quote */
        .hw-tab { flex: 1 1 auto; border: 10px solid #1e293b; border-radius: 22px; background: var(--hw-card); display: flex;
            box-shadow: 0 14px 34px rgba(15,23,42,.22); min-width: 0; }
        .hw-quote { display: flex; flex-direction: column; gap: 7px; }
        .hw-q-head { display: flex; justify-content: space-between; gap: 8px; align-items: center; flex-wrap: wrap; }
        .hw-off { font-size: .8em; font-weight: 700; color: #b45309; background: #fef3c7; border-radius: 999px; padding: 2px 8px; }
        .hw-line { display: grid; grid-template-columns: 62px minmax(0,1fr) auto; gap: 8px; align-items: start;
            border: 1px solid var(--hw-line); border-radius: 8px; padding: 6px 8px; }
        .hw-room { font-weight: 800; }
        .hw-what em { display: block; font-style: normal; color: var(--hw-soft); font-size: .9em; margin-top: 1px; }
        .hw-sw { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }
        .hw-opts { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 3px; }
        .hw-opt { font-size: .78em; background: var(--hw-sub); border: 1px solid var(--hw-line); border-radius: 5px; padding: 0 5px; }
        .hw-price { font-size: 1.05em; }
        .hw-total { margin-top: auto; text-align: right; font-size: 1.15em; color: var(--hw-soft); }
        .hw-total b { font-size: 1.5em; color: var(--hw-ink); margin-left: 6px; }

        /* 4b · Offline */
        .hw-offline { display: flex; flex-direction: column; gap: 7px; }
        .hw-net { position: relative; height: 26px; }
        .hw-net span { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            border-radius: 7px; font-weight: 800; font-size: .9em; }
        .hw-net-off { background: #f97316; color: #fff; }
        .hw-net-on { background: var(--hw-ok); color: #fff; }
        .hw-queue { border: 1px dashed #f59e0b; border-radius: 8px; padding: 6px 9px; color: var(--hw-soft); }
        .hw-queue b { color: #b45309; }
        .hw-sync { display: flex; flex-direction: column; gap: 3px; color: var(--hw-ok); font-weight: 700; }

        /* 5 · Send + sign */
        .hw-split { flex: 1 1 auto; display: flex; gap: 14px; align-items: center; justify-content: center; min-width: 0; }
        .hw-send { flex: 1 1 auto; max-width: 250px; align-self: center; }
        .hw-send .hw-body { display: flex; flex-direction: column; gap: 7px; }
        .hw-sbtn { border: 1px solid var(--hw-line); border-radius: 7px; padding: 6px 10px; font-weight: 700; }
        .hw-sbtn.wa { background: #25d366; border-color: #25d366; color: #fff; }
        .hw-fly { color: var(--hw-ok); font-weight: 700; font-size: .9em; }
        .hw-ph-card { display: flex; flex-direction: column; gap: 2px; border: 1px solid var(--hw-line); border-radius: 9px; padding: 7px; font-size: .92em; }
        .hw-dep { color: #b45309; font-size: .88em; margin-top: 2px; }
        .hw-yes { background: var(--hw-ok); color: #fff; border-radius: 8px; padding: 6px; text-align: center; font-weight: 800; font-size: .9em; }
        @media (max-width: 560px) { .hw-send { max-width: 46%; } .hw-phone { width: 52%; max-width: 52%; } }

        /* 6 · Order */
        .hw-order { display: flex; flex-direction: column; gap: 12px; }
        .hw-job { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid var(--hw-line);
            border-radius: 9px; padding: 9px 11px; flex-wrap: wrap; }
        .hw-pills { position: relative; display: inline-block; width: 92px; height: 24px; }
        .hw-pill { position: absolute; inset: 0; display: grid; place-items: center; border-radius: 999px; font-weight: 800; font-size: .85em; }
        .hw-pill.q { background: var(--hw-sub); color: var(--hw-soft); border: 1px solid var(--hw-line); }
        .hw-pill.acc { background: #dcfce7; color: #15803d; }
        .hw-pill.ord { background: color-mix(in srgb, var(--hw-accent) 16%, var(--hw-card)); color: var(--hw-accent); }
        .hw-auto { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; color: var(--hw-ok); font-weight: 700; }
        .hw-tray { color: var(--hw-ink); background: color-mix(in srgb, var(--hw-fit) 14%, var(--hw-card)); border-left: 3px solid var(--hw-fit);
            border-radius: 6px; padding: 4px 9px; font-weight: 700; }
        .hw-order > .hw-btn { align-self: flex-start; }
        .hw-sups { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .hw-sup { border: 1px dashed var(--hw-line); border-radius: 9px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; }
        .hw-sup span { color: var(--hw-accent); font-weight: 700; }

        /* 7 · Pipeline */
        .hw-pipe { display: flex; flex-direction: column; gap: 10px; }
        .hw-track { position: relative; height: 26px; }
        .hw-chipjob { position: absolute; top: 0; left: 0; width: 16.66%; text-align: center; white-space: nowrap;
            background: var(--hw-accent); color: #fff; font-weight: 800; border-radius: 999px; padding: 3px 0; font-size: .8em; }
        .hw-stages { flex: 1 1 auto; display: grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: 6px; }
        .hw-st { position: relative; background: var(--hw-sub); border-radius: 8px; padding: 7px 6px; display: flex; flex-direction: column;
            gap: 2px; overflow: hidden; min-height: 170px; }
        .hw-st b { font-size: .9em; }
        .hw-st span { font-size: .78em; color: var(--hw-soft); }
        .hw-st em { font-style: normal; font-weight: 800; font-size: .95em; position: relative; z-index: 1; }
        .hw-st i { position: absolute; left: 6px; right: 6px; bottom: 6px; height: var(--h); border-radius: 5px;
            background: color-mix(in srgb, var(--hw-accent) 22%, transparent); }
        .hw-st.paid i { background: color-mix(in srgb, var(--hw-ok) 30%, transparent); }
        @media (max-width: 560px) { .hw-st b { font-size: .78em; } .hw-st em { font-size: .8em; } }

        /* 8 · Fitting drag */
        .hw-fit { display: grid; grid-template-columns: 30% minmax(0,1fr); gap: 10px; }
        .hw-trayp { background: var(--hw-sub); border-radius: 9px; padding: 8px; display: flex; flex-direction: column; gap: 6px; }
        .hw-trayp b { font-size: .88em; color: var(--hw-soft); }
        .hw-ghost { border: 2px dashed var(--hw-line); border-radius: 6px; padding: 9px 6px; color: transparent; font-size: .85em; }
        .hw-week { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); grid-auto-rows: minmax(70px, 1fr); gap: 5px; }
        .hw-week > span { position: relative; background: var(--hw-sub); border-radius: 7px; }
        .hw-week > span.hd { background: none; font-size: .82em; font-weight: 700; color: var(--hw-soft); text-align: center; min-height: 0; }
        .hw-week { grid-template-rows: auto; }
        .hw-week .hw-appt { top: 6px; }
        .hw-target { outline: 2px dashed color-mix(in srgb, var(--hw-fit) 55%, transparent); outline-offset: -2px; }
        .hw-drag { position: absolute; z-index: 3; border-radius: 6px; padding: 5px 7px; font-size: .85em; line-height: 1.25;
            background: color-mix(in srgb, var(--hw-fit) 16%, var(--hw-card)); border-left: 3px solid var(--hw-fit);
            box-shadow: 0 8px 18px rgba(15,23,42,.18); left: calc(12px + 8px); top: 72px; width: calc(30% - 28px); }
        .hw-drag b { display: block; font-size: .85em; color: var(--hw-fit); }
        .hw-ok { display: inline-block; margin-top: 3px; background: var(--hw-fit); color: #fff; border-radius: 5px; padding: 0 6px; font-weight: 800; font-size: .85em; }
        .hw-cur { position: absolute; right: -8px; bottom: -14px; width: 15px; fill: #0f172a; stroke: #fff; stroke-width: 1.2; }

        /* 9 · Paid */
        .hw-paid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 12px; align-items: start; }
        .hw-inv { position: relative; display: flex; flex-direction: column; gap: 7px; border: 1px solid var(--hw-line); border-radius: 10px; padding: 10px; }
        .hw-invrow { display: flex; justify-content: space-between; gap: 8px; }
        .hw-invrow.due { font-weight: 800; border-top: 1px solid var(--hw-line); padding-top: 6px; }
        .hw-invrow.pay { color: var(--hw-ok); font-weight: 700; }
        .hw-inv > .hw-btn { align-self: flex-start; }
        .hw-stamp { position: absolute; right: 16px; top: 53%; border: 3px solid var(--hw-ok); color: var(--hw-ok); font-weight: 900;
            font-size: 1.45em; letter-spacing: .08em; padding: 0 10px; border-radius: 8px; transform: rotate(-14deg); background: var(--hw-card); }
        .hw-acc { border-radius: 10px; padding: 10px; background: var(--hw-sub); display: flex; flex-direction: column; gap: 2px; }
        .hw-acc span { font-weight: 800; margin-bottom: 4px; }
        .hw-acc small { color: var(--hw-soft); }
        .hw-acc b { font-size: 1.35em; margin-bottom: 6px; }

        /* 10 · Finale */
        .hw-fin { flex: 1 1 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px;
            text-align: center; border-radius: 14px; padding: 16px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--hw-accent) 14%, var(--hw-card)), var(--hw-card)); }
        .hw-fin .logo { font-size: 1.9em; font-weight: 900; color: var(--hw-ink); }
        .hw-fin .logo span { color: var(--hw-accent); }
        .hw-fin-icons { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; font-size: 1.6em; }
        .hw-fin-icons span { display: inline-grid; place-items: center; width: 1.8em; height: 1.8em; border-radius: 50%;
            background: var(--hw-card); border: 1px solid var(--hw-line); }
        .hw-fin-cta { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; font-size: 13px; }

        /* ── Animation timeline classes ─────────────────────────
           Hidden/initial until the scene gets .play; .done = jump to the
           end state (first paint, and when stepping without playing). */
        .a-fade, .a-pop, .a-drop, .a-fly, .a-stamp, .a-mid { opacity: 0; }
        .a-type { display: inline-block; clip-path: inset(0 100% 0 0); white-space: nowrap; }
        .a-grow { transform: scaleY(0); transform-origin: bottom; }
        .a-draw { stroke-dasharray: 100; stroke-dashoffset: 100; }

        .play .a-fade  { animation: hwFade .5s ease var(--d, 0s) forwards; }
        .play .a-pop   { animation: hwPop .5s cubic-bezier(.3,1.4,.6,1) var(--d, 0s) forwards; }
        .play .a-drop  { animation: hwDrop .7s cubic-bezier(.3,1.3,.6,1) var(--d, 0s) forwards; }
        .play .a-fly   { animation: hwFly .6s ease-out var(--d, 0s) forwards; }
        .play .a-type  { animation: hwType .7s steps(14) var(--d, 0s) forwards; }
        .play .a-sel   { animation: hwSel .3s ease var(--d, 0s) forwards; }
        .play .a-press { animation: hwPress .45s ease var(--d, 0s); }
        .play .a-out   { animation: hwOut .3s ease var(--d, 0s) forwards; }
        .play .a-mid   { animation: hwFade .3s ease var(--d, 0s) forwards, hwOut .3s ease var(--d2, 9s) forwards; }
        .play .a-ring  { animation: hwShake .45s ease var(--d, 0s) 3; }
        .play .a-draw  { animation: hwDraw 2.6s ease-in-out var(--d, 0s) forwards; }
        .play .a-grow  { animation: hwGrow .8s ease-out var(--d, 0s) forwards; }
        .play .a-stamp { animation: hwStamp .45s cubic-bezier(.3,1.3,.6,1) var(--d, 0s) forwards; }
        .play .a-drop.a-glow { animation: hwDrop .7s cubic-bezier(.3,1.3,.6,1) var(--d, 0s) forwards, hwGlow 1.1s ease 1.3s 2; }
        .play .a-travel { animation: hwTravel 6.5s ease-in-out .8s forwards; }
        .play .a-drag  { animation: hwDragTo 2.4s cubic-bezier(.5,0,.3,1) 1s forwards; }

        .done .a-fade, .done .a-pop, .done .a-drop, .done .a-fly, .done .a-mid { opacity: 1; }
        .done .a-type { clip-path: none; }
        .done .a-grow { transform: none; }
        .done .a-draw { stroke-dashoffset: 0; }
        .done .a-stamp { opacity: 1; }
        .done .a-sel { background: var(--hw-accent); color: #fff; border-color: var(--hw-accent); }
        .done .a-out, .done .a-mid { opacity: 0; }
        .done .a-travel { left: 83.34%; }
        .done .a-drag, .play .a-drag { }
        .done .a-drag { left: var(--drag-x); top: var(--drag-y); width: var(--drag-w); }
        .hw-fit { --drag-x: calc(12px + 30% + 10px + (70% - 34px) * .6 + 4px); --drag-y: 120px; --drag-w: calc((70% - 34px) * .2 - 6px); }

        .paused .play *, .paused .play { animation-play-state: paused !important; }

        @keyframes hwFade  { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        @keyframes hwPop   { 0% { opacity: 0; transform: scale(.6); } 100% { opacity: 1; transform: none; } }
        @keyframes hwDrop  { 0% { opacity: 0; transform: translateY(-40px) scale(.96); } 100% { opacity: 1; transform: none; } }
        @keyframes hwFly   { 0% { opacity: 0; transform: translateX(-28px); } 100% { opacity: 1; transform: none; } }
        @keyframes hwType  { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
        @keyframes hwSel   { to { background: var(--hw-accent); color: #fff; border-color: var(--hw-accent); } }
        @keyframes hwPress { 50% { transform: scale(.92); filter: brightness(1.2); } }
        @keyframes hwOut   { to { opacity: 0; } }
        @keyframes hwShake { 0%,100% { transform: rotate(0); } 25% { transform: rotate(-16deg); } 75% { transform: rotate(16deg); } }
        @keyframes hwDraw  { to { stroke-dashoffset: 0; } }
        @keyframes hwGrow  { to { transform: none; } }
        @keyframes hwStamp { 0% { opacity: 0; transform: rotate(-14deg) scale(2.4); } 100% { opacity: 1; transform: rotate(-14deg) scale(1); } }
        @keyframes hwGlow  { 50% { box-shadow: 0 0 0 7px color-mix(in srgb, var(--hw-accent) 25%, transparent); } }
        @keyframes hwTravel { 0%,8% { left: 0; } 18%,26% { left: 16.66%; } 36%,44% { left: 33.33%; }
                              54%,62% { left: 50%; } 72%,80% { left: 66.66%; } 90%,100% { left: 83.34%; } }
        @keyframes hwDragTo { 0% { transform: none; }
                              15% { transform: scale(1.06) rotate(-2deg); }
                              100% { left: var(--drag-x); top: var(--drag-y); width: var(--drag-w); transform: none; } }

        @media (prefers-reduced-motion: reduce) {
            .play *, .play { animation-duration: .01s !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
            .hw-start .big { animation: none; }
        }

        .hw-after { text-align: center; margin-top: 2.5rem; }
        .hw-after h2 { font-size: 1.5rem; margin: 0 0 .4rem; color: var(--text-primary); }
        .hw-after p { color: var(--text-secondary); margin: 0 0 1.2rem; }
        .hw-after .row { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }

        .hw-read { margin-top: 2.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem; }
        .hw-read summary { cursor: pointer; font-weight: 700; color: var(--text-primary); }
        .hw-read ol { color: var(--text-secondary); line-height: 1.55; padding-left: 1.3rem; }
        .hw-read li { margin: .8rem 0; }
        .hw-read li b { color: var(--text-primary); }

        .hw-foot { text-align: center; color: var(--text-faint); font-size: 0.9rem; margin-top: 2.5rem; }
        .hw-foot a { color: var(--link); }
    </style>
</head>
<body>
<div class="hw-wrap">
    <div class="hw-top">
        <a class="hw-brand" href="/welcome.php">Your<span class="accent">Blinds</span></a>
        <div class="hw-top-links">
            <a href="/welcome.php">Plans &amp; pricing</a>
            <a href="/instaprice/index.php">Try InstaPrice</a>
            <?php if ($loggedIn): ?>
                <a href="/calendar/index.php">Go to my dashboard</a>
            <?php else: ?>
                <a href="/auth/login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="hw-hero">
        <h1>See how it works</h1>
        <p>Follow one job from the first phone call to money in the bank &mdash; exactly the way you&rsquo;d run it in YourBlinds.</p>
    </section>

    <div class="hw-player" id="hw-player">
        <div class="hw-stage" id="hw-stage">
            <?php foreach ($SCENES as $n => $s): ?>
                <section class="hw-scene<?= $n === 0 ? ' done' : '' ?>" data-secs="<?= (int) $s['secs'] ?>"
                         data-say="<?= e(hw_say($s)) ?>" <?= $n === 0 ? '' : 'hidden' ?>
                         aria-label="Step <?= $n + 1 ?> of <?= $sceneCount ?>: <?= e($s['title']) ?>">
                    <div class="hw-screen" aria-hidden="true">
                        <?php if ($s['mock'] === '__FINALE__'): ?>
                            <div class="hw-fin">
                                <div class="logo a-pop" style="--d:.2s">Your<span>Blinds</span></div>
                                <div class="hw-fin-icons">
                                    <?php foreach (array_slice($SCENES, 0, -1) as $k => $x): ?>
                                        <span class="a-pop" style="--d:<?= number_format(0.6 + $k * 0.18, 2) ?>s"><?= $x['icon'] ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="hw-fin-cta a-fade" style="--d:2.4s">
                                    <?php if (!$loggedIn && !$signupsClosed): ?>
                                        <a class="hw-cta primary" href="/auth/signup.php">Start free &rarr;</a>
                                    <?php endif; ?>
                                    <a class="hw-cta ghost" href="/instaprice/index.php">Get a price now</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?= $s['mock'] ?>
                        <?php endif; ?>
                    </div>
                    <div class="hw-text" aria-live="polite">
                        <div class="hw-step"><?= $s['icon'] ?> Step <?= $n + 1 ?> of <?= $sceneCount ?></div>
                        <h2><?= e($s['title']) ?></h2>
                        <p><?= e($s['body']) ?></p>
                        <?php if ($s['tier'] !== ''): ?><span class="hw-tier"><?= e($s['tier']) ?></span><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <button type="button" class="hw-start" id="hw-start">
                <span class="big" aria-hidden="true">&#9654;</span>
                <b>Play the tour</b>
                <small>About 3 minutes &middot; with voiceover</small>
            </button>
        </div>
        <div class="hw-progress"><i id="hw-bar"></i></div>
        <div class="hw-controls">
            <button type="button" class="hw-ctl" id="hw-prev" aria-label="Previous step">&#9664;</button>
            <button type="button" class="hw-ctl main" id="hw-play">&#9654; Play</button>
            <button type="button" class="hw-ctl" id="hw-next" aria-label="Next step">&#9654;&#9654;</button>
            <nav class="hw-dots" aria-label="Tour steps">
                <?php foreach ($SCENES as $n => $s): ?>
                    <button type="button" class="hw-dot<?= $n === 0 ? ' on' : '' ?>" data-go="<?= $n ?>"><?= $s['icon'] ?> <?= e($s['nav']) ?></button>
                <?php endforeach; ?>
            </nav>
            <button type="button" class="hw-ctl" id="hw-voice" aria-pressed="true">&#128266; Voice on</button>
        </div>
    </div>

    <section class="hw-after">
        <h2>Ready to run your business this way?</h2>
        <p>Start free. Add Maps and Accounts whenever you&rsquo;re ready.</p>
        <div class="row">
            <?php if ($signupsClosed): ?>
                <span class="hw-cta ghost">Sign-ups are paused just now</span>
            <?php elseif (!$loggedIn): ?>
                <a class="hw-cta primary" href="/auth/signup.php">Start free &rarr;</a>
            <?php endif; ?>
            <a class="hw-cta ghost" href="/welcome.php">See plans &amp; pricing</a>
        </div>
    </section>

    <details class="hw-read">
        <summary>Prefer to read it? The whole journey, step by step</summary>
        <ol>
            <?php foreach ($SCENES as $s): ?>
                <li><b><?= e($s['title']) ?></b> <?= e($s['body']) ?><?= $s['tier'] !== '' ? ' (' . e($s['tier']) . ')' : '' ?></li>
            <?php endforeach; ?>
        </ol>
    </details>

    <p class="hw-foot">
        <a href="/welcome.php">What&rsquo;s it all about?</a> &middot; <a href="/instaprice/index.php">Try the price calculator</a><br>
        <a href="/legal/licence.php">Licence agreement</a> &middot; <a href="/legal/privacy.php">Privacy policy</a>
    </p>
</div>

<script>
(function () {
    'use strict';
    var stage   = document.getElementById('hw-stage');
    var scenes  = Array.prototype.slice.call(stage.querySelectorAll('.hw-scene'));
    var dots    = Array.prototype.slice.call(document.querySelectorAll('.hw-dot'));
    var start   = document.getElementById('hw-start');
    var btnPlay = document.getElementById('hw-play');
    var btnPrev = document.getElementById('hw-prev');
    var btnNext = document.getElementById('hw-next');
    var btnVoice= document.getElementById('hw-voice');
    var bar     = document.getElementById('hw-bar');
    var reduce  = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var synth   = ('speechSynthesis' in window) ? window.speechSynthesis : null;
    var voice   = null, cur = 0, playing = false, finished = false, token = 0, timers = [];
    var voiceOn = !!synth;

    try { if (localStorage.getItem('yb_tour_voice') === '0') voiceOn = false; } catch (e) {}

    // Most natural British voice the browser offers: Edge's neural
    // "(Natural)" voices (Sonia, Libby), Apple's enhanced/premium ones, then
    // Chrome's Google UK English Female, then any en-GB.
    function pickVoice() {
        if (!synth) return;
        var vs = synth.getVoices() || [];
        var gb = vs.filter(function (v) { return /^en[-_]GB/i.test(v.lang); });
        voice = gb.find(function (v) { return /Sonia.*Natural/i.test(v.name); })
             || gb.find(function (v) { return /Libby.*Natural/i.test(v.name); })
             || gb.find(function (v) { return /Natural/i.test(v.name); })
             || gb.find(function (v) { return /(Premium|Enhanced)/i.test(v.name); })
             || vs.find(function (v) { return v.name === 'Google UK English Female'; })
             || gb[0]
             || vs.find(function (v) { return /^en/i.test(v.lang); }) || null;
    }
    if (synth) { pickVoice(); synth.onvoiceschanged = pickVoice; } else { btnVoice.hidden = true; }

    function later(fn, ms) { timers.push(setTimeout(fn, ms)); }
    function clearAll() { timers.forEach(clearTimeout); timers = []; if (synth) synth.cancel(); }

    function money(n, prefix) { return prefix + Math.round(n).toLocaleString('en-GB'); }
    function counters(s, animate) {
        s.querySelectorAll('[data-count]').forEach(function (el) {
            var to = +el.dataset.count, from = +(el.dataset.from || 0), p = el.dataset.prefix || '';
            if (!animate || reduce) { el.textContent = money(to, p); return; }
            el.textContent = money(from, p);
            var my = token, dur = (+el.dataset.dur || 1.5) * 1000;
            later(function () {
                var t0 = performance.now();
                (function step(t) {
                    if (my !== token) return;
                    var k = Math.min(1, (t - t0) / dur);
                    el.textContent = money(from + (to - from) * (1 - Math.pow(1 - k, 3)), p);
                    if (k < 1) requestAnimationFrame(step);
                })(t0);
            }, (+el.dataset.d || 0) * 1000);
        });
    }

    function paintUi() {
        dots.forEach(function (d, k) {
            d.classList.toggle('on', k === cur);
            d.classList.toggle('seen', k < cur);
            if (k === cur) { d.setAttribute('aria-current', 'step'); d.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
            else d.removeAttribute('aria-current');
        });
        bar.style.width = ((cur + 1) / scenes.length * 100) + '%';
        btnPlay.innerHTML = playing ? '&#10073;&#10073; Pause' : (finished ? '&#8635; Replay' : '&#9654; Play');
        btnVoice.innerHTML = voiceOn ? '&#128266; Voice on' : '&#128263; Voice off';
        btnVoice.setAttribute('aria-pressed', voiceOn ? 'true' : 'false');
    }

    // Show scene n. animate=true replays its timeline from the start.
    function show(n, animate) {
        token++; clearAll();
        stage.classList.remove('paused');
        cur = Math.max(0, Math.min(scenes.length - 1, n));
        scenes.forEach(function (s, k) { s.classList.remove('play', 'done'); s.hidden = k !== cur; });
        var s = scenes[cur];
        void s.offsetWidth;                       // restart CSS animations
        s.classList.add(animate ? 'play' : 'done');
        counters(s, animate);
        paintUi();
    }

    // Play scene n, then move on once both the minimum time and the
    // narration have finished.
    function run(n) {
        finished = false;
        show(n, true);
        if (!playing) return;
        var my = token, s = scenes[cur];
        var minDone = false, talkDone = !(voiceOn && synth);
        function next() {
            if (my !== token || !minDone || !talkDone) return;
            later(function () {
                if (my !== token) return;
                if (cur < scenes.length - 1) run(cur + 1);
                else { playing = false; finished = true; paintUi(); }
            }, 900);
        }
        later(function () { minDone = true; next(); }, (+s.dataset.secs || 8) * 1000);
        if (!talkDone) {
            // Sentence by sentence: Chrome cuts long utterances off mid-way.
            var parts = (s.dataset.say.match(/[^.!?]+[.!?]+["”’]?|[^.!?]+$/g) || [s.dataset.say])
                .map(function (x) { return x.trim(); }).filter(Boolean);
            parts.forEach(function (txt, k) {
                var u = new SpeechSynthesisUtterance(txt);
                if (voice) { u.voice = voice; u.lang = voice.lang; } else { u.lang = 'en-GB'; }
                u.rate = 1.03;
                if (k === parts.length - 1) u.onend = function () { talkDone = true; next(); };
                u.onerror = function () { talkDone = true; next(); };
                synth.speak(u);
            });
            // Safety net if the browser never reports the end of speech.
            later(function () { talkDone = true; next(); },
                  (+s.dataset.secs || 8) * 1000 + s.dataset.say.split(/\s+/).length * 420);
        }
    }

    function play() {
        start.hidden = true;
        playing = true;
        run(finished ? 0 : cur);
    }
    function pause() {
        playing = false; token++; clearAll();
        stage.classList.add('paused');
        paintUi();
    }
    function go(n) {
        start.hidden = true;
        if (playing) run(n); else { finished = false; show(n, true); }
    }

    start.addEventListener('click', play);
    btnPlay.addEventListener('click', function () { playing ? pause() : play(); });
    btnPrev.addEventListener('click', function () { go(cur - 1); });
    btnNext.addEventListener('click', function () { go(cur + 1); });
    dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.go); }); });
    btnVoice.addEventListener('click', function () {
        voiceOn = !voiceOn;
        try { localStorage.setItem('yb_tour_voice', voiceOn ? '1' : '0'); } catch (e) {}
        if (!voiceOn && synth) synth.cancel();   // the scene's onerror lets it move on
        paintUi();
    });
    document.addEventListener('keydown', function (e) {
        if (e.target.closest && e.target.closest('input, textarea, select')) return;
        if (e.key === 'ArrowRight') go(cur + 1);
        else if (e.key === 'ArrowLeft') go(cur - 1);
    });
    window.addEventListener('pagehide', function () { if (synth) synth.cancel(); });

    paintUi();
})();
</script>
</body>
</html>
