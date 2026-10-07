<?php
declare(strict_types=1);

/**
 * The "How it works" tour scenes — shared by the public page
 * (how-it-works.php) and the narration generator (tools/tour_voice.php).
 *
 * Narration audio: each scene's spoken text (hw_say) is hashed, and a clip
 * named after that hash is looked for in /media/tour/. Change a scene's
 * title or body and its old clip simply stops matching — the page falls
 * back to the browser voice until tools/tour_voice.php is re-run.
 */

if (!function_exists('hw_say')) {
    /** Narration: the title and body as one plain string. */
    function hw_say(array $s): string
    {
        return trim($s['title'] . ' ' . $s['body']);
    }

    /** File name of a scene's narration clip (in /media/tour/). */
    function hw_clip_name(array $s): string
    {
        return substr(sha1(hw_say($s)), 0, 16) . '.mp3';
    }

    /** Public URL of a scene's clip, or '' when it hasn't been generated. */
    function hw_clip_url(array $s): string
    {
        $name = hw_clip_name($s);
        return is_file(__DIR__ . '/../media/tour/' . $name) ? '/media/tour/' . $name : '';
    }
}

// ── The scenes ────────────────────────────────────────────────────────
// nav   — chapter label;  icon — chapter emoji;  secs — minimum on-screen
// time (the narration can hold a scene longer);  tier — optional plan tag;
// mock  — the animated screen. Animation classes (a-fade, a-type, …) take
// their start time from --d, so each mock reads like a little timeline.
return [
    [
        'nav'   => 'Book it', 'icon' => '📞', 'secs' => 9,
        'title' => 'The phone rings. Booked in seconds.',
        'body'  => 'One quick form does the lot. Type the customer\'s name, pop in the postcode and, on our Silver and Gold packages, the address fills itself in. Then book it your way: give the customer an exact time, or a window like morning, afternoon or evening, set up just the way you like them. Your choice. Make YourBlinds work the way you want to work. Choose who\'s going, click Book appointment, and the customer record is created as you book. Booking in windows? Your customer gets a confirmation email with their slot. And the double-booking guard makes sure nobody is ever sent to two places at once.',
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
    <div class="hw-row"><label></label><div class="hw-btn a-press" style="--d:5.2s">Book appointment</div></div>
    <div class="hw-toast a-pop" style="--d:5.8s">&#10003; Booked &mdash; confirmation emailed to Mrs Patel</div>
  </div>
</div>
HTML,
    ],
    [
        'nav'   => 'Calendar', 'icon' => '🗓️', 'secs' => 9,
        'title' => 'Straight onto your team\'s calendars.',
        'body'  => 'Set your team up as users and every job lands on the calendar of whoever\'s going, the moment it\'s booked. Each of them simply signs in to YourBlinds on their own phone or tablet, with nothing to download, and their day is right there, always up to date. No texts, no whiteboard, no "did you get my message?". Fitters can be set to see just their fittings, and the office sees the whole team side by side, a column per person. Plans changed? Just drag the job to a new slot.',
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
      <div class="hw-ph-note a-fade" style="--d:3.6s">On Sam&rsquo;s calendar the moment it was booked</div>
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
