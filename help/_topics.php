<?php
declare(strict_types=1);

/**
 * Help topics — the searchable written answers on help/index.php, separate from
 * the animated walkthroughs in help/guides/.
 *
 * Each row: [aud, cat, title, keys, body]
 *   aud   all | admin | super  — who may see it
 *   cat   the section it groups under (order here is the page order)
 *   title the question, as the user would ask it
 *   keys  extra search words (the box matches title + keys + body)
 *   body  HTML answer
 */

return [
    // ---- Getting started -----------------------------------------------
    ['all', 'Getting started', 'How does a job flow through the app?', 'overview start begin first time new user journey lifecycle funnel stages what next quote order invoice payment',
     '<p>Every job runs along one line: <strong>quote &rarr; order &rarr; invoice &rarr; payment</strong>. Everything else in the menu hangs off that.</p>
      <ul>
      <li><strong>Start it.</strong> The <strong>+ New</strong> button at the very top of the menu opens a new quote. (If you are the platform owner it first asks whether the job is trade or retail.) If you just want a figure on the phone with nobody on the system yet, use <strong>InstaPrice</strong> in the box underneath.</li>
      <li><strong>Price it.</strong> In the quote builder you add one blind at a time &mdash; product, system, band, fabric, width &times; drop, options &mdash; and the total updates as you go.</li>
      <li><strong>Send it.</strong> The <em>Send to customer</em> section emails the PDF with an accept link, sends it on WhatsApp, or gives you a public link to copy.</li>
      <li><strong>Win it.</strong> Either the customer accepts online &mdash; which records their typed name as a signature, books the installation appointment and emails you &mdash; or you press <strong>&check; Customer accepted</strong> yourself. If the job is already sold and no quote needs sending, press <strong>&#128230; Save as order</strong> in <em>Quote actions</em>: it accepts the quote and goes straight to the place-order screen.</li>
      <li><strong>Make and fit it.</strong> The status ladder is <em>draft &rarr; sent &rarr; accepted &rarr; ordered &rarr; fitted &rarr; invoiced &rarr; paid</em>, with <em>declined</em> as the dead end. Orders made in your own factory also carry a separate <strong>fulfilment stage</strong> (Confirmed, In Production, Ready, Dispatched).</li>
      <li><strong>Get paid.</strong> Record money on the order\'s own Payments panel, or on <strong>Retail &rarr; Payments</strong> if you have the Accounts add-on.</li>
      </ul>
      <p>To see where everything is at a glance, open <strong>Work &rarr; Pipeline</strong>.</p>'],

    ['all', 'Getting started', 'Why does the menu have Retail and Trade?', 'menu sidebar navigation retail trade two quotes orders twice duplicate same page wholesale missing section cant see trade where is',
     '<p>Because the app does two different jobs, and the menu keeps them apart. It is grouped <strong>Work</strong> / <strong>Retail</strong> / <strong>Trade</strong> / <strong>Setup</strong> / <strong>Platform</strong>.</p>
      <ul>
      <li><strong>Work</strong> &mdash; the everyday screens that belong to both: Dashboard, Calendar, Pipeline and Factory.</li>
      <li><strong>Retail</strong> &mdash; your own end customers: Customers, Quotes, Orders and Payments.</li>
      <li><strong>Trade</strong> &mdash; other businesses you supply as the factory: Trade accounts, Quotes, Orders, Invoices, Statements and Commissions.</li>
      <li><strong>Setup</strong> &mdash; Products, Users, Settings, Trade terms and Billing.</li>
      <li><strong>Platform</strong> &mdash; running the whole system (catalogue, clients, billing plans, system health).</li>
      </ul>
      <p><strong>Retail &rarr; Quotes and Trade &rarr; Quotes are the same screen</strong>, simply filtered &mdash; the heading tells you which you are on (<em>Retail Quotes</em>, <em>Trade Orders</em> and so on). So a job only ever appears in one of the two lists, depending on whether it was raised for a trade account or for a retail customer. Money is the one thing that really is in two places: retail payments on <strong>Retail &rarr; Payments</strong>, trade invoicing and payments under <strong>Trade</strong>.</p>
      <p><strong>No Trade section?</strong> That is normal. The whole Trade block is only shown to the platform owner, so an ordinary admin or salesperson will not see it at all.</p>'],

    ['all', 'Getting started', 'What is the Pipeline for?', 'pipeline board kanban columns drag drop move cards cannot move funnel column totals value mine only all time',
     '<p><strong>Work &rarr; Pipeline</strong> is a board that shows where every job has got to, in columns, so you can see the whole funnel in one screen instead of scrolling a list.</p>
      <p>The columns are <strong>Quote</strong> (drafts and sent quotes together &mdash; an unsent one carries a <em>Not sent</em> tag), <strong>Declined</strong>, <strong>Accepted</strong>, <strong>Ordered</strong>, <strong>Fitted</strong>, <strong>Invoiced</strong> and <strong>Paid</strong>. Each column head shows how many jobs are in it and what they are worth, and the bar at the top gives the overall job count and &pound; total. Cards show the customer, quote number, postcode, how long since the job was last touched, a <em>Paid</em> or <em>Part paid</em> tag, and the outstanding balance on anything invoiced. The colours are your own traffic lights from <strong>Setup &rarr; Settings &rarr; Status colours</strong>.</p>
      <p><strong>The board is read-only &mdash; you cannot drag a card from one column to the next.</strong> That is deliberate: moving a job really does things (timestamps, supplier orders, invoices), so you change the status on the job itself. Click any card to open it.</p>
      <ul>
      <li>Filters: a search box (customer, quote number or postcode), a <em>Window</em> of the last 30 to 365 days, an <strong>All time</strong> chip and a <strong>Mine only</strong> chip.</li>
      <li>A column shows at most 50 cards; if there are more it says <em>Showing 50 of&hellip;</em> &mdash; narrow the window or search.</li>
      <li>The board refreshes itself when something changes, so leave it open on a screen.</li>
      <li>If you cannot see everyone\'s jobs (the <em>View all customer jobs</em> tick on your user), the board only shows jobs you are on. Prices are hidden unless you have <em>View costs</em>.</li>
      </ul>'],

    ['super', 'Getting started', 'Where has an order got to? (Confirmed, In Production, Ready, Dispatched)', 'fulfilment stage progress where is my order confirmed in production ready dispatched pill chip factory made bought in',
     '<p>Every placed order that contains something you make carries one <strong>fulfilment stage</strong>, and it is the single honest answer to "where is this order?". You will see it as a coloured pill on <strong>Work &rarr; Factory</strong> (the incoming orders list) and in the <em>Stage</em> column on a trade account\'s page (<strong>Trade &rarr; Trade accounts</strong>, then open the account).</p>
      <ul>
      <li><strong>Confirmed</strong> &mdash; the order is in, but nothing has started: not on the production floor and no bought-in items ordered yet.</li>
      <li><strong>In Production</strong> &mdash; work has begun. Either blinds have been released to the floor, or the bought-in items have been ordered from the supplier.</li>
      <li><strong>Ready</strong> &mdash; every blind you make has been made <em>and</em> every bought-in item has been received. This is the stage that lets you dispatch.</li>
      <li><strong>Dispatched</strong> &mdash; it has gone out, either marked dispatched on the floor or sent out on a delivery note.</li>
      </ul>
      <p><strong>You do not set the stage by hand</strong> &mdash; the app works it out from the floor, the bought-in log and the delivery notes, and recalculates it whenever you change an order\'s status, place it with suppliers, or act on it under Trade &rarr; Invoices. If a stage looks wrong, fix the thing underneath it: scan the last blind off the floor, or tick the bought-in items as received.</p>
      <p>A dash instead of a stage means the order has nothing of yours in it, or it is still only a quote &mdash; stages start once an order is placed. On the Factory list the pill sits alongside a <em>made</em> count (click it to open the floor) and the bought-in tag: <em>n to order</em>, <em>ordered</em> or <em>received</em>.</p>'],

    ['super', 'Getting started', 'It won\'t let me dispatch — why?', 'cant dispatch wont let me dispatch not ready greyed out disabled button blocked mark dispatched delivery note print dn bought in awaiting',
     '<p>Because the order is not <strong>Ready</strong> yet. The app refuses with <em>"Can\'t dispatch &mdash; the order isn\'t ready yet (every blind made and every bought-in item received)."</em> on <strong>Trade &rarr; Invoices</strong> (both <em>Mark dispatched</em> and <em>Print DN &amp; invoice</em>), and with <em>"Can\'t dispatch yet &mdash; the order isn\'t ready. Every blind must be made and every bought-in item received first."</em> on the factory side.</p>
      <p>Ready has two halves, and <strong>both</strong> must be true:</p>
      <ul>
      <li><strong>Everything you make is made.</strong> All of the order\'s in-house blinds have been finished on the production floor (or the order has been marked <em>made</em>).</li>
      <li><strong>Everything bought in has arrived.</strong> No bought-in line is still waiting on its supplier &mdash; each one has to be marked received.</li>
      </ul>
      <p>There is a third catch that surprises people: <strong>work has to have actually started</strong>. A brand-new Confirmed order that nobody has touched is never Ready, even if there is little to do on it, so send it to the floor (or order the bought-in items) first.</p>
      <p>To find out which half is holding it up, open <strong>Work &rarr; Factory</strong> and look at the order\'s row: the <em>made</em> count shows how many blinds are still to finish (click it to go to the floor), and the bought-in tag reads <em>n to order</em>, <em>ordered</em> or <em>received</em>. Clear whichever is outstanding and the stage flips to Ready on its own &mdash; then the dispatch buttons work.</p>'],

    // ---- Quoting -------------------------------------------------------
    ['all', 'Quoting', 'Building a quote', 'quote builder add blind product system band fabric width drop room duplicate dup new quote line item',
     '<p>You build a quote one blind at a time, in the <strong>Add blind</strong> panel of the quote builder.
      Start one from <strong>+ New</strong> at the top of the sidebar, fill in the customer details, then work
      down the Add blind form &mdash; the fields are in the order you must choose them, because each one decides
      what the next offers.</p>
      <ul><li><strong>Product</strong> &mdash; choose this first; nothing else unlocks until you do.</li>
      <li><strong>System</strong> &mdash; the variant of that product.</li>
      <li><strong>Band</strong> &mdash; the price tier. Leave it on <em>All bands</em> or pick one to shorten
      the fabric list.</li>
      <li><strong>Fabric</strong> &mdash; start typing and pick from the results.</li>
      <li><strong>Room name</strong> &mdash; just a label (Lounge, Bay 1), so the fitter knows which is which.</li>
      <li><strong>Width</strong>, <strong>Drop</strong>, <strong>Quantity</strong>, an optional internal
      <strong>Notes</strong> line, then any <strong>Options</strong> that product offers.</li></ul>
      <p>The price box underneath reads <em>&ldquo;Pick a product, fabric and dimensions to see the price&rdquo;</em>
      until it has enough to work with. Then press <strong>Save</strong>, or <strong>Save and add another
      blind</strong> to stay on the form for the next window.</p>
      <p>Saved blinds are listed under <strong>Blinds</strong>. Each row has <strong>Edit</strong>,
      <strong>Dup</strong> (copies the fabric, system and options, ready for you to change the size &mdash; the
      quick way to do six similar windows) and a <strong>&times;</strong> to remove it.</p>'],

    ['all', 'Quoting', 'InstaPrice (quick price)', 'instaprice quick price no customer estimate ballpark phone turn into full quote',
     '<p><strong>InstaPrice</strong> gives you a price with no customer attached &mdash; for when someone rings up
      or you are stood in their lounge and they just want a figure. It is the coloured button near the top of the
      sidebar, right under <strong>+ New</strong>.</p>
      <p>The screen is headed <em>&ldquo;Quick price &mdash; no customer details needed&rdquo;</em> and asks for the
      same things as the quote builder: <strong>Product</strong>, <strong>System</strong>, <strong>Band</strong>,
      <strong>Fabric</strong>, <strong>Measurement unit</strong>, then <strong>Width</strong>,
      <strong>Drop</strong> and <strong>Qty</strong>. It uses exactly the same pricing engine as a real quote, so
      the number you read out is the number the quote will show.</p>
      <p>Nothing is saved while you play &mdash; <strong>Reset</strong> clears it and starts again. If the customer
      says yes, press <strong>Turn into full quote &rarr;</strong>. That creates a real draft quote with this blind
      already on it, gives it a quote number and drops you into the quote builder to add the customer details and
      any more windows.</p>
      <p>Two things that catch people: the <em>Turn into full quote</em> button only appears if you are allowed to
      create quotes, and it stays greyed out until you have entered enough for a price. And a quote made this way
      starts with a placeholder customer &mdash; fill in the real name and email before you send it.</p>'],

    ['all', 'Quoting', 'How do I send the quote to the customer?', 'send email quote customer pdf link accept whatsapp share copy public link token',
     '<p>Scroll to the <strong>Send to customer</strong> panel near the bottom of the quote. There are three ways
      out of it, and they all share <em>one</em> public link &mdash; a web page the customer can open with no
      login, where they can read the quote and accept it.</p>
      <ul><li><strong>&#128231; Email PDF + accept link</strong> &mdash; the usual one. Check the
      <strong>Recipient email</strong> box, add anything you like in <strong>Message (optional)</strong>, and send.
      The customer gets the quote as a PDF attachment plus the link in the body.</li>
      <li><strong>&#128172; Send via WhatsApp</strong> &mdash; opens WhatsApp with the link ready to send.</li>
      <li><strong>&#128279; Copy public link</strong> &mdash; copies the address to your clipboard so you can paste
      it anywhere. The full link is printed underneath the buttons too.</li></ul>
      <p><strong>No WhatsApp button?</strong> It only appears when the customer has a phone or mobile number
      <em>and</em> you have ticked <strong>&ldquo;Mobile is on WhatsApp&rdquo;</strong> in their details higher up
      the page. The grey line above the buttons tells you which of the two is missing.</p>
      <p><strong>The trap:</strong> only the email button moves the quote from <strong>draft</strong> to
      <strong>sent</strong>. A quote that is still a draft shows on the public page with no Accept button at all,
      so if you are sharing the link by hand, use <strong>Mark as sent</strong> in Quote actions first.</p>'],

    ['all', 'Quoting', 'The customer accepted online — what happens next?', 'accepted online accept link signature terms appointment notification email ordered factory decline',
     '<p>Quite a lot happens on its own, which is the point &mdash; you should not have to re-key anything.</p>
      <p>On the public quote page the customer types their full name under <strong>Accept this quote</strong> and
      presses <strong>Accept quote</strong>. If you have Terms &amp; Conditions set up they must also tick
      <em>&ldquo;I agree to the Terms &amp; Conditions&rdquo;</em> &mdash; they cannot get past it. The typed name
      and their IP address are stored as the digital sign-off.</p>
      <p>Then, automatically:</p>
      <ul><li>The quote moves to <strong>accepted</strong> and the date is stamped.</li>
      <li>The <strong>installation appointment is created on your Calendar</strong> &mdash; you do not book it
      yourself.</li>
      <li>The customer is emailed a thank-you.</li>
      <li>You are emailed <em>&ldquo;New order &mdash; &lt;quote number&gt; accepted&rdquo;</em>, with the total and
      a link, at the address set in Settings.</li>
      <li>If <em>every</em> blind on the quote is one made in-house, it skips the place-order step and goes
      <strong>straight to <em>ordered</em></strong> and into the workshop queue.</li></ul>
      <p>They can also press <strong>Decline</strong>, which marks the quote declined and takes the pending fitting
      back off the Calendar.</p>
      <p>Acceptance only works once. Open the link again and it just says <em>&ldquo;Quote accepted&rdquo;</em> &mdash;
      a refresh can never accept twice.</p>'],

    ['all', 'Quoting', 'What do the buttons in Quote actions do?', 'quote actions buttons mark as accepted declined reopen draft save as order status ladder send to suppliers invoice pdf',
     '<p><strong>Quote actions</strong> is the panel at the top of every quote. The two you will use most are
      also in the slim bar that follows you down the page: <strong>&check; Customer accepted</strong> and
      <strong>&times; Customer declined</strong>. The rest:</p>
      <ul><li><strong>View PDF</strong> / <strong>Download PDF</strong> &mdash; the customer&rsquo;s quote as a
      document.</li>
      <li><strong>&#128230; Save as order</strong> &mdash; accepts the job and takes you straight to the place-order
      screen, <em>without ever sending the customer a quote</em>. It only shows on a draft or sent quote that has
      at least one blind on it. Use it when the order is already agreed.</li>
      <li><strong>Mark as &hellip;</strong> &mdash; steps the job along the ladder:
      <code>draft &rarr; sent &rarr; accepted &rarr; ordered &rarr; fitted &rarr; invoiced &rarr; paid</code>.
      You only ever see the steps that are legal from where the job is now.</li>
      <li><strong>Reopen as draft</strong> &mdash; puts it back to draft so it can be edited again.</li>
      <li><strong>&#128230; Send to suppliers</strong> &mdash; once the job is an order.</li>
      <li><strong>&#129534; Send invoice</strong> &mdash; from <em>ordered</em> onward. Once sent it becomes
      <strong>Resend invoice</strong> and asks you to confirm, so a stray click cannot send two.</li></ul>
      <p><strong>Paid is never a button.</strong> A job turns <em>paid</em> by itself once the deposit and payments
      cover the total, so &ldquo;paid&rdquo; always means the money is genuinely accounted for.</p>'],

    ['all', 'Quoting', 'Archiving jobs you’ve finished with', 'archive archived restore hide old quotes tidy list delete selected bulk clear out',
     '<p><strong>Archiving hides a job. Deleting destroys it.</strong> If you just want an old job out of the way,
      archive it.</p>
      <p>On the Quotes or Orders list (under <strong>Retail</strong> or <strong>Trade</strong> in the menu), tick
      the boxes beside the jobs you want, then press <strong>&#128451; Archive selected</strong>. They vanish from
      the list &mdash; nothing is lost. A <strong>&#128451; Archived (n)</strong> link appears on the right of the
      filter chips; click it to see them, tick what you want back and press <strong>Restore selected</strong>. Then
      <strong>&larr; Back to active</strong> returns you to the normal list.</p>
      <p>Archiving keeps your filters, so archiving from the Trade view drops you back into the Trade view.</p>
      <p>Next to it sits <strong>Delete selected</strong>, and that one is permanent. It warns you first:
      <em>&ldquo;Delete the selected quotes? This is permanent &mdash; all blinds, items and appointments go
      too.&rdquo;</em> Read it and mean it &mdash; the blinds, the line items and the calendar appointments all go
      with the job. There is no undo.</p>
      <p><strong>Cannot see the Archive button?</strong> It only appears once your database has the archive column,
      so on an older setup you will see <em>Delete selected</em> on its own. Ask whoever looks after the system to
      turn archiving on rather than deleting instead.</p>'],

    ['admin', 'Quoting', 'Hide prices on the customer quote', 'hide price per blind line total settings show price of each blind sizes retail',
     '<p>Go to <strong>Setup &rarr; Settings</strong>, open the <strong>Quoting</strong> tab and find the
      <strong>Quote defaults</strong> section. Two ticks there decide how much detail the customer sees.</p>
      <ul><li><strong>Show the price of each blind</strong> &mdash; ticked, the quote PDF and the customer&rsquo;s
      online quote list a unit price and a line total for every blind. Unticked, those per-blind prices are hidden
      and the customer only sees the quote total.</li>
      <li><strong>Show the size of each blind</strong> &mdash; ticked, each blind&rsquo;s width &times; drop is
      shown, which is what a trade customer expects. Unticked, sizes are hidden and only the description shows,
      which is the usual retail look.</li></ul>
      <p>Press <strong>Save quote defaults</strong>. It applies to every quote from then on, including ones already
      raised, because the PDF is built fresh each time it is opened.</p>
      <p>Worth knowing: these settings are company-wide, not per quote. And if you use the Wally tax (WT charge),
      turning the per-blind prices <em>on</em> makes that surcharge spread proportionally across the blind prices
      so the figures still add up; turning them <em>off</em> means it simply lifts the total.</p>'],

    ['admin', 'Quoting', 'Measurement units (mm, cm, metres, inches)', 'mm cm metres inches measurement unit imperial metric convert size default',
     '<p>Set your company default in <strong>Setup &rarr; Settings &rarr; Quoting</strong>, in the
      <strong>Measurements</strong> section. Pick <strong>Default measurement unit</strong> &mdash; Millimetres (mm),
      Centimetres (cm), Metres (m) or Inches (in) &mdash; and press <strong>Save unit</strong>. That is the unit
      your team types and reads sizes in.</p>
      <p>Sizes are always stored the same way underneath, so you can change this whenever you like and
      <strong>nothing already saved is altered or re-priced</strong> &mdash; it only changes how the numbers are
      displayed and typed.</p>
      <p>You can also override it for one job. In the quote builder, above the size boxes, the
      <strong>Measurement unit (this quote)</strong> dropdown re-displays that quote&rsquo;s sizes in whatever unit
      you choose, and the <strong>Width</strong> and <strong>Drop</strong> labels change to match &mdash; so you
      always know what you are typing in.</p>
      <p>For a one-off you can type the unit straight into the box, such as <code>60in</code> or <code>1.5m</code>,
      and it will be converted for you. Handy for the one customer who works in feet and inches when everything
      else you do is in millimetres.</p>'],

    ['admin', 'Quoting', 'Paid-in-full receipt', 'receipt paid full balance zero thank you settled automatic email customer',
     '<p>This one is on by default and looks after itself. When a payment settles an order&rsquo;s
      <strong>balance to zero</strong>, the customer is automatically emailed a <strong>thank-you receipt</strong>
      &mdash; their order as a PDF, headed <em>&ldquo;Receipt&rdquo;</em>, showing it is paid in full.</p>
      <p>Recording that final payment is all that triggers it. Open the order, use the
      <strong>Payments</strong> panel, enter the <strong>Amount &pound;</strong>, the <strong>Date received</strong>,
      the <strong>Method</strong> and an optional <strong>Reference</strong>, and save. If that clears the balance
      the receipt goes out on its own &mdash; and the job also flips to <em>paid</em> at the same moment.</p>
      <p>Two limits worth knowing:</p>
      <ul><li>It is sent <strong>once</strong> per order. It can never go twice, no matter how many times you open
      the order afterwards.</li>
      <li>The customer must have a valid email address on their record. No email, no receipt, and nothing tells you
      &mdash; so put the email in when you take the job.</li></ul>
      <p>To turn it off, go to <strong>Setup &rarr; Settings &rarr; Quoting</strong>, find
      <strong>Paid-in-full receipt</strong> in the Quote defaults section, untick <em>&ldquo;Email a receipt when an
      order is paid in full&rdquo;</em> and press <strong>Save quote defaults</strong>.</p>'],

    ['admin', 'Quoting', 'WT charge (internal surcharge)', 'wt charge surcharge wally tax internal hidden markup awkward customer fee difficult job',
     '<p>The <strong>Wally tax (WT charge)</strong> is a discretionary amount you can quietly add to a quote for a
      job that is more hassle than it is worth &mdash; an awkward customer, a fiddly fit, three flights of stairs.
      It is <strong>internal only</strong>.</p>
      <p>Turn it on in <strong>Setup &rarr; Settings &rarr; Quoting</strong>, in the Quote defaults section: tick
      <strong>&ldquo;Enable the Wally tax (WT charge)&rdquo;</strong> and press <strong>Save quote defaults</strong>.
      A small <strong>WT</strong> box then appears on the quote builder, and you type an amount into it on the jobs
      that deserve one.</p>
      <ul><li>The customer <strong>never</strong> sees the letters &ldquo;WT&rdquo;, the words &ldquo;Wally tax&rdquo;,
      or a separate line anywhere on their quote or invoice.</li>
      <li>It is added <strong>before VAT</strong> &mdash; so a &pound;30 WT adds &pound;36 to a total at 20% VAT.</li>
      <li>If <em>&ldquo;Show the price of each blind&rdquo;</em> is on, the WT is
      <strong>spread across the blind prices</strong> proportionally, so the figures still add up. If it is off, it
      simply lifts the total.</li>
      <li>On your own screen the totals show a WT line so you know it is there. That line is stripped from anything
      the customer sees.</li></ul>
      <p>Because it hides inside the blind prices, do not also discount the job to &ldquo;make up for it&rdquo;
      &mdash; you will be working against yourself.</p>'],

    ['admin', 'Quoting', 'Bank details on quotes (how customers pay)', 'bank transfer sort code account number payment details pay invoice reference how to pay',
     '<p>Enter them once and they print on every customer quote and invoice. Go to
      <strong>Setup &rarr; Settings &rarr; Quoting</strong> and scroll to
      <strong>Bank details for customer payments</strong>.</p>
      <p>Fill in <strong>Account name</strong>, <strong>Sort code</strong> and <strong>Account number</strong>, plus
      an optional <strong>Payment note</strong> if you want to add something like
      <em>&ldquo;Please use your quote number as the reference&rdquo;</em>. Press
      <strong>Save bank details</strong>.</p>
      <p>They then show as a <strong>&ldquo;How to pay &mdash; bank transfer&rdquo;</strong> block on the quote and
      invoice PDF and on the customer&rsquo;s online quote page, with the quote number printed underneath as the
      suggested payment reference &mdash; which is what saves you guessing whose money has landed in the bank.</p>
      <p>Leave all three boxes blank and the whole block is hidden, so you can switch it off simply by clearing
      them. Type them carefully: nobody checks these numbers for you, and a wrong digit sends your customer&rsquo;s
      money to a stranger.</p>'],

    ['admin', 'Quoting', 'Adjust the price of one blind on a quote', 'override markup margin discount per line quote builder tune adjust price for this blind match a price deal',
     '<p>Sometimes one window needs a different price &mdash; you are matching a rival&rsquo;s quote, or throwing in
      a deal on the last blind. You can do that without touching the product&rsquo;s normal pricing.</p>
      <p>In the quote builder, in the <strong>Add blind</strong> form under the size boxes, open the small
      <strong>Adjust price for this blind</strong> panel. Two boxes:</p>
      <ul><li><strong>Discount % (this blind)</strong></li>
      <li><strong>Markup %</strong> (or <strong>Margin %</strong>) <strong>(this blind)</strong> &mdash; whichever
      basis your company uses; a small blue hint underneath shows the equivalent in the other so you can
      sense-check it.</li></ul>
      <p>Both boxes show <em>&ldquo;product default&rdquo;</em> as their placeholder. Leave one blank and that
      blind uses the product&rsquo;s normal rate &mdash; you do not have to fill both in. The price preview updates
      as you type, so you can nudge the figure until it lands where you want it.</p>
      <p>It changes <strong>this blind on this quote only</strong>. Nothing about the product itself changes, and
      no other quote is touched. If you come back to edit that blind later the panel opens already expanded, so you
      can see at a glance that a special price is in play.</p>
      <p>The panel only appears for people allowed to see costs &mdash; a fitter will not get these boxes.</p>'],

    ['super', 'Quoting', 'What does the + New button give me?', 'new button trade retail sale type account one off master admin start quote default sale type',
     '<p>For you, <strong>+ New</strong> at the top of the sidebar does <em>not</em> go straight into the quote
      builder as it does for everyone else. It opens a short <strong>New</strong> screen that first asks what kind
      of sale this is, because trade and retail price differently. The <strong>Trade / Retail</strong> toggle at
      the top opens on whichever you set as <strong>Setup &rarr; Settings &rarr; Quoting &rarr; Default sale
      type</strong>.</p>
      <ul><li><strong>Retail</strong> &mdash; press <strong>Start retail quote &rarr;</strong> and you are in the
      ordinary quote builder at your standard pricing, adding the customer&rsquo;s details as you go.</li>
      <li><strong>Trade</strong> &mdash; start typing in the <strong>Trade account</strong> box and pick the
      account. Their details fill in as the customer and their trade discount is applied. The
      <strong>Start quote for this account &rarr;</strong> button stays greyed out until you have picked a real
      account, which stops a half-typed name creating a quote for nobody.</li>
      <li><strong>Customer not listed? Enter them manually</strong> &mdash; opens a form for a one-off. Tick
      <strong>&ldquo;Save as a new trade account&rdquo;</strong> to keep their details and pricing for next time;
      left unticked it is a one-off at your standard pricing with no trade discount.</li></ul>
      <p>If that name already exists you get a warning rather than a duplicate, offering <strong>Use the existing
      account</strong> or <strong>Create a separate account anyway</strong>. Take the existing one unless they
      really are two different businesses.</p>'],

    // ---- Calendar & customers ------------------------------------------
    ['all', 'Calendar & customers', 'Booking jobs on the calendar', 'calendar appointment booking book fit fitting measure survey visit month week day view maps waze directions everyone just me mine my schedule my diary pending fitting drag drop tray issue flag status no show today run legend colours',
     '<p>The <strong>Calendar</strong> (Work &rarr; Calendar) is where every visit lives &mdash; and it carries
      <strong>both</strong> kinds: <strong>measure</strong> (quote) visits <em>and</em> <strong>fittings</strong>.
      It&rsquo;s the page you land on when you log in. Use <strong>&ldquo;+ Book Appointment&rdquo;</strong> (top right)
      to add one, or <strong>&ldquo;+ New quote&rdquo;</strong> beside it to start a job instead.</p>
      <p><strong>Reading the grid.</strong> A card&rsquo;s <strong>colour</strong> is the job&rsquo;s stage &mdash; Quote
      sent, Accepted, Ordered, Fitting booked, Fitted, Paid and so on; the key sits along the top. A
      <strong>fitting carries a dark outline</strong>, a measure doesn&rsquo;t, and the
      <strong>&#9888;&#65039; Issues</strong> link in that key filters to jobs you&rsquo;ve flagged. The
      <strong>&#128198; Week</strong> / <strong>&#128197; Day</strong> links under the title give you the other views.</p>
      <p><strong>Everyone / Just me</strong> sits just under the page title &mdash; that pill is what replaced the old
      &ldquo;My Schedule&rdquo; menu entry. Someone without <em>View all customer jobs</em> only ever sees their own, and
      someone with <strong>Fittings only</strong> ticked on their user record (Setup &rarr; Users) sees fittings and no
      measures at all.</p>
      <p><strong>Pending Fitting</strong> is the tray above the grid. When a customer accepts a quote online the fitting
      appointment is created for them with <strong>no date</strong>, and it waits there &mdash; <em>&ldquo;Drag a card onto
      a date to schedule it.&rdquo;</em> Drag a booked one back onto the tray to unschedule it again.</p>
      <p><strong>Click a card</strong> to open the appointment: <strong>Edit</strong>, <strong>Open order &rarr;</strong>
      (or <strong>Start quote</strong>), and <strong>Update status</strong> &mdash; Booked, Completed, Cancelled or No-show.
      On the Maps add-on (Silver plan and up) you also get <strong>Google Maps &rarr;</strong> and <strong>Waze &rarr;</strong>
      buttons and a <strong>Today&rsquo;s run &rarr;</strong> button in the header.</p>'],

    ['all', 'Calendar & customers', 'How do I add, find and tidy up customers?', 'customer customers manager add new edit search find duplicate duplicates merge delete address postcode lookup find by postcode',
     '<p>Customers live under <strong>Retail &rarr; Customers</strong>. Press <strong>&ldquo;+ Add customer&rdquo;</strong>
      for a new one. Only the <strong>Name</strong> is required; the rest &mdash; Email, Phone (landline), Mobile, Address
      line 1 / 2, Town, County, Postcode and Notes &mdash; you can fill in as you learn it.</p>
      <p><strong>Finding someone.</strong> The search box takes a name, email, phone, town <em>or</em> postcode, and the
      list shows Name, Email, Phone, Town, Postcode and how many <strong>Quotes</strong> they have. Click
      <strong>Edit</strong> on a row to open the record &mdash; underneath the details you get a
      <strong>Recent quotes</strong> table (quote number, status, total, created) with an <strong>Open</strong> link
      straight into each one.</p>
      <p><strong>Where the postcode lookup is.</strong> There is <strong>no</strong> postcode lookup on this customer
      form. The <strong>&ldquo;Find by postcode&rdquo;</strong> box and its <strong>&ldquo;Find address&rdquo;</strong>
      button appear on the screens where an address actually matters &mdash; booking an appointment and the quote builder
      &mdash; and only when your plan includes Postcode lookup (Silver and up). Type the postcode, press
      <strong>Find address</strong>, then pick the right one from the list and the address fields fill themselves in.</p>
      <p><strong>Tidying up.</strong> Admins get a <strong>&ldquo;Find duplicates&rdquo;</strong> button next to
      &ldquo;+ Add customer&rdquo;. It groups customers with the same name and lets you <strong>Merge this group</strong>
      (or merge the lot); the <em>oldest</em> record is kept, every quote and appointment is re-pointed at it, and the
      spares are deleted. <strong>It cannot be undone.</strong> <strong>Delete customer</strong> (in the red
      <em>Danger zone</em> at the bottom of a customer) is also permanent &mdash; their quotes stay, they just stop being
      linked to anybody.</p>'],

    ['admin', 'Calendar & customers', 'Can I book a Morning, Afternoon or Evening slot instead of a time?', 'am pm morning afternoon evening add time slot window half day slot slots time capacity bookings per day measure quote visit full fully booked appointment window email customer',
     '<p>Yes &mdash; switch on <strong>&ldquo;&#128344; Booking time slots&rdquo;</strong>. It&rsquo;s in
      <strong>Setup &rarr; Settings &rarr; Company</strong> tab, in the <strong>Calendar</strong> section. There is no
      &ldquo;Calendar&rdquo; tab &mdash; the tabs are Company, Quoting, Legal, Status colours, Suppliers, Accounting and
      Back up data, and this lives on the first one.</p>
      <p>Tick the box, then give each slot a <strong>Name</strong>, a <strong>From</strong> and <strong>To</strong> time and
      its <strong>Bookings / day</strong> limit, and press <strong>Save</strong>. Out of the box that&rsquo;s
      <strong>Morning 9am&ndash;1pm</strong> and <strong>Afternoon 1pm&ndash;5pm</strong>, four bookings each. Want an
      evening? Press <strong>+ Add a time slot</strong>, call it <em>Evening</em>, set say 6pm&ndash;8pm, and Save. Gaps
      between slots are fine (Morning 10&ndash;1, Afternoon 2&ndash;4), you can have up to six, and
      <strong>&#10005; Remove</strong> takes one away (bookings already in it are kept). You should see
      <em>&ldquo;Booking time slots saved.&rdquo;</em></p>
      <p><strong>What changes.</strong> On <strong>+ Book Appointment</strong> the Time and Duration boxes are replaced by
      a <strong>Time slot</strong> choice: one card per slot, each showing its hours and how many are left
      (e.g. <em>&ldquo;2 of 4 left&rdquo;</em>). Once a window is full that card reads <strong>Full</strong> and
      can&rsquo;t be chosen &mdash; the message is <em>&ldquo;Morning is fully booked on 3 Oct 2026. Please choose
      another window or another day.&rdquo;</em> Leave one unpicked and you get
      <em>&ldquo;Please choose a time slot.&rdquo;</em> There&rsquo;s also a tick to
      <strong>email the customer their appointment window</strong>, which needs an email address on the booking.</p>
      <p><strong>The two things people trip over.</strong> It applies to <strong>quote (measure) visits only</strong>
      &mdash; fittings still take an exact time, as they must. And the customer is given a <strong>window, never an exact
      hour</strong>, which is rather the point. If saving fails with <em>&ldquo;Could not save: &hellip; have you run
      migrate_ampm_windows_list.php?&rdquo;</em>, the database update hasn&rsquo;t been run yet &mdash; ask whoever runs
      the platform for you.</p>'],

    ['admin', 'Calendar & customers', 'Can the calendar show what a job is worth?', 'calendar money value balance outstanding paid deposit show figures costs fitters navigation app google maps waze',
     '<p>It can, but think before you switch it on. The tick is
      <strong>&ldquo;&#128178; Show order value + balance on the calendar&rdquo;</strong>, in
      <strong>Setup &rarr; Settings &rarr; Company</strong> tab, <strong>Calendar</strong> section.</p>
      <p>With it on, every appointment that&rsquo;s linked to a quote grows a small money line on the month, week and day
      calendars: the <strong>order value</strong>, what&rsquo;s been <strong>received</strong> (deposit plus payments) and
      the <strong>outstanding balance</strong> &mdash; something like
      <code>&pound;1,200.00 &middot; paid &pound;300.00 &middot; bal &pound;900.00</code>. Once it&rsquo;s settled the
      line turns into <code>&#10003; PAID &pound;1,200.00 &middot; bal &pound;0.00</code>.</p>
      <p><strong>The warning on the setting is worth repeating.</strong> This shows the figures to
      <strong>everyone who can open the calendar</strong> &mdash; including staff whose logins normally hide costs, like
      fitters. It deliberately <strong>ignores</strong> each person&rsquo;s <em>&ldquo;View costs&rdquo;</em> permission
      on the <strong>Setup &rarr; Users</strong> page, just for the calendar. So if anybody who sees the calendar
      shouldn&rsquo;t see the money, leave it unticked &mdash; there is no half-way setting.</p>
      <p>While you&rsquo;re in that same Calendar section you&rsquo;ll find <strong>&ldquo;&#129517; Navigation
      app&rdquo;</strong>: choose <strong>Google Maps</strong> (the default) or <strong>Waze</strong>. That decides which
      app opens when someone taps an address on the day calendar or the schedule view &mdash; pick Waze if your fitters
      prefer it for live traffic.</p>'],

    // ---- Products & pricing --------------------------------------------
    ['admin', 'Products & pricing', 'How is pricing put together?', 'product system band price table grid model structure how does pricing work fabric band code explain',
     '<p>Four things stacked up: <strong>Product → System → Band → Price table</strong>. Get those straight and the rest of the catalogue makes sense.</p>
      <ul>
      <li><strong>Product</strong> — one type of blind, e.g. <em>Roller Blind</em>, <em>Vertical</em>, <em>Metal Venetian</em>. Add them under <strong>Setup &rsaquo; Products</strong>.</li>
      <li><strong>System</strong> — a variant of that product: a slat size, <em>Standard</em> vs <em>Motorised</em>, and so on. Price tables hang off the system, so every product needs at least one.</li>
      <li><strong>Band</strong> — a price tier. Each fabric/slat you add carries a band code, and all the fabrics in a band cost the same at a given size, so they share one grid instead of needing one each.</li>
      <li><strong>Price table</strong> — a width &times; drop grid of prices for one system + band. Open one and type or paste the prices straight from the supplier sheet.</li>
      </ul>
      <p>The band code is the join. A fabric with band <em>B</em> is priced from the table with band <em>B</em> <em>on that same system</em> — the codes must match exactly, character for character. If they do not, the quote builder tells you: <em>“No price table for … band … ”</em>.</p>
      <p>A quoted size that falls between grid lines takes the <strong>next size up</strong>. A size bigger than the biggest cell in the grid will not price at all — extend the table.</p>'],

    ['admin', 'Products & pricing', 'How do I set up a new product from scratch?', 'wizard new product setup steps systems fabrics price tables first blind add product start',
     '<p>Use the <strong>Setup wizard</strong>. Go to <strong>Setup &rsaquo; Products</strong> and press <strong>✨ Setup wizard</strong> (or <em>+ New product</em> if you already know the ropes). It walks you through four steps: <strong>Name → Systems → Fabrics → Price tables</strong>.</p>
      <ul>
      <li><strong>Name</strong> — the product name, plus “What do you call the material this product is made of?” (<em>Fabric</em> for rollers and romans, <em>Colour</em> for metal venetians, <em>Finish</em> for wood). Tick <strong>This product has no fabrics</strong> for a headrail, track or spares line and the fabric step is skipped.</li>
      <li><strong>Systems</strong> — the variants (15mm, 25mm, Motorised…). At least one is required, because price tables live on a system.</li>
      <li><strong>Fabrics</strong> — type or paste them in, or use <strong>📚 Import from Fabric Library</strong> / <strong>📄 Import from spreadsheet</strong>. Either one drops you back into the wizard to carry on.</li>
      <li><strong>Price tables</strong> — one grid per band per system.</li>
      </ul>
      <p><strong>The time-saver:</strong> on the Fabrics step there is a <em>Price tables first →</em> button. Do the pricing first, and when you come back the Band box <strong>suggests the bands you just imported</strong>, so you pick instead of retyping and the codes cannot drift apart.</p>
      <p>You can stop at any point. The wizard offers a <strong>Resume →</strong> link for any product still mid-setup, and works out which step you were on.</p>'],

    ['admin', 'Products & pricing', 'How do I price a headrail, a shutter or vertical fabric only?', 'pricing modes width only per slat per square metre sqm shutter venetian headrail track spares no drop rate',
     '<p>Not every blind is priced on a width &times; drop grid, so a product can be switched to one of three other modes. The ticks are on the product’s edit page (<strong>Setup &rsaquo; Products</strong>, click the product), and the first two are also offered on step 1 of the setup wizard.</p>
      <ul>
      <li><strong>Sized by width only — no drop (e.g. a headrail cut to length).</strong> The Drop box is hidden when quoting, and each price table becomes a single width → price list. Load it with <em>Import width prices</em>.</li>
      <li><strong>Priced per slat (by drop) — e.g. vertical fabric only.</strong> The table is a drop → price-per-slat list. At quote time you pick a band, then enter the <strong>drop</strong> and the <strong>number of slats</strong> (that is the quantity) — no width.</li>
      <li><strong>Priced per square metre — e.g. shutters.</strong> One &pound;/m&sup2; rate per system and band, multiplied by width &times; height. Both sizes are required. There is an optional <strong>Minimum billable area (m&sup2;)</strong> box — leave it blank or 0 for no minimum.</li>
      </ul>
      <p><strong>Traps:</strong> never combine two of these on one product — “per slat” and “per m&sup2;” both say so on screen. And “no fabrics” is a <em>separate</em> tick from “width only”: a product can be width-only and still have fabrics, or have none and still be sized width &times; drop.</p>'],

    ['admin', 'Products & pricing', 'Do band codes have to be A, B, C?', 'band code length name tape herringbone bamboo 60 characters descriptive naming price tier',
     '<p>No. A band code is just text, up to <strong>60 characters</strong>, so it can be a proper name rather than a letter — for example <em>50mm Bamboo &amp; Gloss Herringbone Tape</em> instead of a meaningless <em>D</em>. Use whatever your supplier’s price list calls the tier; it is far easier to check later.</p>
      <p>You set the band in two places and they <strong>must match exactly</strong>:</p>
      <ul>
      <li>On each fabric/slat — the <strong>Band</strong> column on the product’s Fabrics page (or the <em>Set band on selected</em> box for a whole run at once).</li>
      <li>On the price grid — the <strong>Band</strong> field when you add a price table to a system.</li>
      </ul>
      <p>Spelling, spacing and punctuation all count. <em>Band A</em> and <em>A</em> are two different bands as far as the pricing engine is concerned, and a fabric whose band has no matching table simply refuses to price.</p>
      <p><strong>The safe way round it:</strong> import or create your price tables first, then add the fabrics — the Band box suggests the bands that already exist, so you pick one from the list instead of typing it a second time.</p>'],

    ['admin', 'Products & pricing', 'How do I load a supplier’s price list in one go?', 'bulk import excel multi band worksheet sheet picker spreadsheet price tables upload xlsx prices',
     '<p>On a system’s price-tables page (<strong>Setup &rsaquo; Products</strong> → the product → Systems → the system) use <strong>Bulk import (multiple bands)</strong>, top right. Upload one Excel file and it creates every band and fills every grid in a single pass. <em>Single-band import</em> next to it does one band at a time.</p>
      <p><strong>What the file has to look like.</strong> Each band block starts with a row containing <code>Band X</code> in column A (<code>Price Band X</code> and even the common typo <code>Bnad X</code> are accepted). Under it comes a widths row — in <strong>mm</strong> (<code>610mm</code>) or <strong>metres</strong> (<code>0.800</code>), detected per cell — then the data rows, drop in column A and prices across. Currency symbols and commas are stripped for you, and label rows like <code>DROP</code>, <code>WIDTH</code> or <code>Metric</code> are skipped. Several band blocks can sit stacked in one sheet.</p>
      <p>If the workbook has <strong>several worksheets</strong> with bands on them — one per slat size, typically — you get a <strong>“Which worksheet?”</strong> step and choose the one that belongs to this system. Repeat for the next system.</p>
      <p><strong>Watch out:</strong> re-importing <strong>replaces</strong> the existing rows for each band it finds, within this system only. That is how you apply a price rise — but it does mean a half-finished file wipes what was there.</p>'],

    ['admin', 'Products & pricing', 'How do I change the band on lots of fabrics at once?', 'shift click range select set band on selected bulk fabrics delete selected filter search supplier',
     '<p>On a product’s <strong>Fabrics</strong> page, tick the rows you want and use the buttons in the grey bar above the table: <strong>Set band on selected</strong> (type the band in the little box first), <strong>Set supplier on selected</strong>, or <strong>Delete selected</strong>.</p>
      <p><strong>Ticking a long run quickly:</strong> tick the first row, then hold <strong>Shift</strong> and click the last one — everything in between takes the same state. The tick box in the table header selects the lot. The counter next to the buttons tells you how many are selected, so you can sanity-check before you press anything.</p>
      <p><strong>Narrow the list first.</strong> The <em>Filter</em> box above the table is instant and matches on several words at once, so typing <code>polaris cream</code> leaves only Polaris in Cream. It searches the fabric name, colour, band and code — <strong>not</strong> the supplier column, which catches people out. Clear it with the <em>Clear</em> link.</p>
      <p>Bands are case-and-spacing sensitive, so set them here in bulk rather than typing each one: it is the commonest cause of a fabric that will not price.</p>'],

    ['admin', 'Products & pricing', 'Can I import one supplier workbook into lots of products?', 'bulk import fabrics colours one file per product sheet match supplier workbook distribute all at once shared range needs fabric',
     '<p>Yes — that is what <strong>Bulk import fabrics</strong> is for. It takes one workbook with <strong>one sheet per product</strong> (columns <code>Name</code>, <code>Colour</code>, <code>Band</code>) and loads each sheet into the matching product, instead of you uploading the same file twenty-seven times.</p>
      <p><strong>Where it lives.</strong> It moved off the Products page — the link is now on <strong>Platform &rsaquo; Catalogue &rsaquo; Fabric Library</strong>, in the blue panel headed <em>Bulk import fabrics across products</em>. (Do not confuse it with <em>Import fabrics</em> on the same page: that one fills the reusable library, this one writes straight into your products.)</p>
      <p><strong>How it goes.</strong> Upload &amp; match → check the suggested matches → import. Every worksheet is matched to a product by name and you can change or clear any of them before anything is written.</p>
      <p>Each sheet can feed <strong>more than one product</strong> — <strong>Ctrl</strong> (or <strong>Cmd</strong>) click in its list to pick several. That is the answer to a shared fabric range: one softshade range feeding all your softshade blinds, one roller range feeding every roller product. Pick them all and they import together, which is usually what clears products still showing <em>Needs fabric</em>.</p>
      <p>A sheet with nothing selected is skipped, and duplicate rows (same band + name + colour) are skipped automatically. For a single product, the per-product <em>Import from Excel</em> on its Fabrics page is simpler.</p>'],

    ['admin', 'Products & pricing', 'How do I turn several products into one with sizes?', 'combine merge master product systems sizes slat 15mm 25mm fold together one product variants',
     '<p>If a family came in as separate products — <em>15mm / 25mm / 35mm / 50mm Venetian</em> — you can fold them into one product whose <strong>systems</strong> are the sizes. Tick them in the <strong>Setup &rsaquo; Products</strong> list (two or more) and press <strong>Combine into product…</strong> in the bulk bar.</p>
      <p>On the next screen give the <strong>Master product name</strong> (e.g. <em>Metal Venetian</em>) and a system name for each one — the table shows what becomes what, with a blue <strong>Master</strong> pill on the first.</p>
      <p><strong>Order matters.</strong> The <strong>first</strong> product you ticked is reused as the master and keeps its group and settings. The others fold in as systems: their fabrics, price tables, margins and extras all move across, and each size’s colours stay scoped to its own system, so the quote builder only ever offers the colours that size actually comes in. The emptied products are then <strong>deactivated</strong>, not deleted — check the result, then delete the husks.</p>
      <p><strong>Adding one later:</strong> tick the existing master <em>first</em>, then the new single-size product, and Combine again — it is appended as an extra system and the master keeps everything it already had.</p>
      <p>Two refusals to expect: products <strong>priced differently</strong> (per-slat versus a normal grid) cannot be combined, and only the first product may already have more than one system.</p>'],

    ['admin', 'Products & pricing', 'How do I reorder, group or delete several products?', 'reorder drag drop sort order products list multi select bulk delete move group folder checkbox tick all at once category',
     '<p>All of it happens on <strong>Setup &rsaquo; Products</strong>.</p>
      <p><strong>Reordering:</strong> drag a row by its <strong>⋮⋮</strong> handle. The order sticks and is the order products appear in when you build a quote, so put your best sellers at the top. Drag a row <em>onto a group heading</em> to file it there, and drag the headings themselves to reorder the groups.</p>
      <p><strong>Groups:</strong> type a name in the <em>New group name</em> box and press <strong>+ Add group</strong> (e.g. “Woods”). Then either use the <strong>Group</strong> dropdown on a row, or tick several rows and pick from <strong>Move selected to…</strong> in the bulk bar — choose <em>— Ungrouped —</em> to pull them back out. Each heading has <em>Expand all</em> / <em>Collapse all</em>, and remembers whether you left it open.</p>
      <p><strong>Ticking a run:</strong> tick one row, then <strong>Shift</strong>-click another to grab everything between; the header tick box takes the whole table.</p>
      <p><strong>Deleting:</strong> <strong>Delete selected</strong> asks you to confirm and says how many will go. Read it — it also removes every option, extra and price table linked to those products, and it cannot be undone. If you only want a product out of the way, use <em>Deactivate</em> on its row instead: it stops appearing on quotes but keeps all its setup.</p>'],

    ['admin', 'Products & pricing', 'My product says “Needs fabric” — what do I do?', 'needs fabric needs price table needs system not ready to quote status amber yellow pill missing product not showing quote builder',
     '<p>That amber pill in the <strong>Status</strong> column is the product telling you it cannot be quoted yet. <strong>Click the pill</strong> and it takes you straight to whatever is missing.</p>
      <p>A product only shows <strong>✓ Ready</strong> when it is active <em>and</em> has at least one price table <em>and</em> (unless it is a no-fabric product) at least one fabric. The wording lists everything outstanding, in the order you should fix it:</p>
      <ul>
      <li><strong>Needs system</strong> — price tables are set up per system, so with none there is nothing to price against. Add one on the Systems page first.</li>
      <li><strong>Needs fabric</strong> — no fabrics/slats/colours yet. Add them, import them from a spreadsheet, or pull them from the Fabric Library. If this product genuinely has no fabric (a headrail, track or spares line), tick the no-fabrics option instead and the requirement goes away.</li>
      <li><strong>Needs price table</strong> — no grids. Bulk-import the supplier sheet, or add the bands and fill the grids by hand.</li>
      </ul>
      <p>A grey <strong>Inactive</strong> pill is different: the product is switched off and will not appear on quotes however complete it is. Use <em>Activate</em> on the row.</p>
      <p>The counts along the row — Systems, Fabrics, Price tables, Options — are the quickest way to see at a glance which product has a hole in it.</p>'],

    ['admin', 'Products & pricing', 'The quote says “No price table for … band …” — why?', 'no price table for band error wont price cannot price blind quote builder exceeds largest cell size mismatch band code',
     '<p>The pricing engine could not find a grid for the combination you picked. It is nearly always a <strong>band that does not match</strong>, and the message names the culprit — for example <em>“No price table for Roller Blind band B on system Standard.”</em></p>
      <p>Work through it in this order:</p>
      <ul>
      <li><strong>The band on the fabric</strong> (product → Fabrics) must be identical to the band on the price table (product → Systems → that system → Price tables). Spacing, capitals and punctuation all count — <em>Band A</em> is not <em>A</em>.</li>
      <li><strong>Same system.</strong> Tables belong to one system. A fabric quoted on <em>Motorised</em> needs a Band B table on <em>Motorised</em>, not just on <em>Standard</em>.</li>
      <li><strong>The table must have prices in it.</strong> Empty grids get created in batches and are easy to leave blank — the price-tables list shows how many are filled.</li>
      </ul>
      <p>Two other messages mean something different. <em>“Size … exceeds the largest cell in this price table”</em> means the blind is bigger than your grid goes — extend the table or re-import a fuller sheet. <em>“No price table set up for … ”</em> on a no-fabric product means that system has no grid at all.</p>
      <p>Whatever you change, re-pick the fabric on the quote line so it prices again.</p>'],

    ['admin', 'Products & pricing', 'How do I add choices like control side or lining?', 'options extras control side lining bottom weight bracket colour choices required multiple per metre percent surcharge quantity',
     '<p>Those are <strong>Options</strong>, and they live on the product: <strong>Setup &rsaquo; Products</strong> → the product → <strong>Options</strong>. An option is the question (e.g. <em>Control side</em>); its <strong>choices</strong> are the answers the salesperson picks from (<em>Left</em>, <em>Right</em>). Add the option first, then click into it to add the choices.</p>
      <p>On <strong>Add option</strong> you can set:</p>
      <ul>
      <li><strong>Required</strong> — the quote will not save without an answer.</li>
      <li><strong>Allow multiple choices</strong> — renders as tick boxes so any combination can be picked.</li>
      <li><strong>Appears when</strong> — tick one or more choices from other options and this one only shows when one of them is selected. Tick none and it is always visible.</li>
      <li><strong>Also show a number input</strong> — a box beside the choice for a wand or cable length; you name the field yourself and it is recorded on the line for the supplier paperwork.</li>
      </ul>
      <p>Each choice can carry a price: <strong>Flat (£)</strong>, <strong>Percent (%)</strong>, <strong>Per metre (£/m)</strong> or <strong>Price per unit (£)</strong> (typing a quantity multiplies it — brackets, fixings). For per-metre there is <em>Per-metre length is measured along</em>: width usually, or <strong>Perimeter</strong> for a trim that runs all the way round (2 &times; width + 2 &times; drop).</p>
      <p>Setting up a second product that needs the same list? Use <strong>Copy from another product</strong> at the top right rather than retyping it.</p>'],

    ['admin', 'Products & pricing', 'Which “supplier” field is which?', 'order supplier library supplier purchase po catalogue prefix confusion settings suppliers fabric supplier column delivery address',
     '<p>There are two different things called a supplier, and one of them is not yours to worry about.</p>
      <ul>
      <li><strong>Order supplier</strong> — who you <em>buy this product from</em>. It is a box on the product’s edit page (type a new name or pick an existing one) and it is what splits a job up when you send it to your suppliers. Use <em>In House</em> for things you make yourself.</li>
      <li><strong>Library supplier</strong> — a platform-side grouping on the master catalogue, worked out from the product’s <strong>name prefix</strong>, not from the Order supplier box. Only the platform owner sees or sets it. If you are wondering why a catalogue product is filed under a name you never typed, that is why.</li>
      </ul>
      <p><strong>Setup &rsaquo; Settings &rsaquo; Suppliers</strong> is the master list behind the Order supplier box. Each row holds the supplier’s <strong>Order email</strong> and your <strong>Account no.</strong>, and above the table sits the <strong>Delivery address</strong> that goes on every supplier order — fill that in or your orders will not say where to ship.</p>
      <p>Names typed on a product are added to that list automatically, which is how strays appear. Tick <strong>Remove</strong> on the row and press <em>Save suppliers</em> to clear one out.</p>
      <p>There is also a <strong>Supplier</strong> column on individual fabrics. That is a note for your own reference — ordering is split by the product’s Order supplier, not by the fabric’s.</p>'],

    ['admin', 'Products & pricing', 'How do I order the materials from my suppliers?', 'send order suppliers purchase order po email materials ordered pipeline status place order auto in house factory spec pdf',
     '<p>Open the job and press <strong>📦 Send to suppliers</strong> in the Quote actions. The screen is headed <strong>Send order to suppliers</strong> and it is available from <strong>accepted onward</strong> — try it on a draft and you get “Accept the quote before ordering from suppliers.”</p>
      <p>The lines are grouped by each product’s <strong>Order supplier</strong>, with the email shown against each group. Tick the groups to send, then press <strong>📦 Send selected orders</strong>. Each supplier gets an email with <strong>only their own lines</strong> and a spec PDF — sizes and options, no customer prices — shipped to the delivery address from <strong>Setup &rsaquo; Settings &rsaquo; Suppliers</strong>.</p>
      <p><strong>What the screen will tell you:</strong> a group already sent carries an <em>Already sent</em> badge and is left unticked so you cannot double-order, and a group with no supplier set, or no valid order email, cannot be sent until you fix it. Blinds you make yourself go straight to the workshop, so the button may read <strong>📦 Place order</strong> or <strong>📦 Send &amp; place order</strong>.</p>
      <p><strong>You may never need to press it.</strong> If every blind on the order is one you make in-house, the job goes to the workshop by itself the moment the customer accepts — that is the <em>Send in-house orders straight to the workshop when accepted</em> tick under <strong>Settings &rsaquo; Quoting &rsaquo; Quote defaults</strong>. An order with any bought-in line still needs the manual send so the supplier gets emailed.</p>
      <p>Sending moves the job on to <strong>Ordered</strong>, so it steps along the Pipeline and recolours on the Calendar. It only ever steps Accepted → Ordered; a job already fitted, invoiced or paid is never pulled back.</p>'],

    ['admin', 'Products & pricing', 'How do I invoice the customer?', 'invoice send invoice email balance due pipeline invoiced bill customer resend bank details paid',
     '<p>Open the order and press <strong>🧾 Send invoice</strong> in the Quote actions. It emails the customer the order PDF headed “Invoice”, showing the total, anything already paid and the <strong>balance due</strong>, with your payment details on it. The email repeats the balance and, where a public link exists, offers to view it online.</p>
      <p><strong>When it is available:</strong> once the job is an order — <em>Ordered</em>, <em>Fitted</em>, <em>Invoiced</em> or <em>Paid</em>. Earlier than that you get “You can invoice once the job is ordered — move it to Ordered first.” You need to be an admin or have the <em>Create orders</em> permission.</p>
      <p>You are asked to confirm first: <em>“Email this invoice to the customer now? This also marks the job as Invoiced.”</em> Sending steps the job Ordered/Fitted → <strong>Invoiced</strong>, so it advances in the Pipeline and recolours on the Calendar. It never steps backwards.</p>
      <p><strong>Traps.</strong> The customer needs a valid email on their record or it refuses. Once a job is Invoiced or Paid the button becomes <strong>Resend invoice</strong> and warns you it has already gone, so a stray click cannot send two. And the bank details printed on it come from <strong>Setup &rsaquo; Settings &rsaquo; Quoting &rsaquo; Bank details for customer payments</strong> — fill those in before your first invoice goes out.</p>'],

    ['admin', 'Products & pricing', 'Markup or margin — which do I type in?', 'markup margin profit basis pricing settings convert default percent uplift difference explain',
     '<p>Both get you to the same selling price; they are two ways of writing the same uplift. Choose which you type under <strong>Setup &rsaquo; Settings &rsaquo; Quoting &rsaquo; Default margins</strong>, on the <strong>Enter your margins as</strong> radio buttons (<em>Markup %</em> or <em>Margin %</em>).</p>
      <ul>
      <li><strong>Markup</strong> is added on top of your cost — cost + 50% = sell.</li>
      <li><strong>Margin</strong> is the profit slice of the <em>sell</em> price — a 50% margin means your cost is half the sell.</li>
      </ul>
      <p>So 50% markup and 50% margin are <strong>not</strong> the same number. As you type, a small blue line underneath shows the equivalent in the other basis, so you can sense-check before saving.</p>
      <p>Whatever you pick is used everywhere you enter a rate: the two boxes here (<em>Default price-table</em> and <em>Default options &amp; extras</em>), per-product overrides, InstaPrice, and the per-blind adjustment in the quote builder. Set it once and the engine applies it to every product that has no explicit value of its own.</p>
      <p><strong>Reassurance:</strong> switching basis re-prices <strong>nothing</strong>. Saved quotes, orders and entered prices are untouched — only the way you type new numbers changes.</p>'],

    // ---- Fabric Library ------------------------------------------------
    ['super', 'Fabric Library', 'What is the Fabric Library?', 'fabric library cloth master list supplier range manufacturer group structure where is it platform catalogue material',
     '<p>The <strong>Fabric Library</strong> is the master list of cloth — every fabric, colour and code a manufacturer
      offers — held in one place so that products can <em>pull from it</em> instead of somebody typing the same range in
      over and over. Find it at <strong>Platform → Catalogue → Fabric Library</strong>. As the page itself puts it: the
      Master Catalogue holds the price tables, this holds the cloth.</p>
      <p>It is built in three layers:</p>
      <ul>
        <li><strong>Supplier group</strong> — the real supplier (Decora, Eclipse…). Create one with
            <em>“+ Add supplier group”</em>.</li>
        <li><strong>Range</strong> — which this page labels a <em>manufacturer</em>. Create one with
            <em>“+ Add manufacturer”</em>, then drag its ⠿ grip into a supplier group, or open the range and use the
            <strong>Supplier group</strong> dropdown. Ranges that belong to nobody sit under <strong>Ungrouped</strong>.</li>
        <li><strong>Fabric</strong> — the row itself, with <strong>Fabric name</strong>, <strong>Colour</strong>,
            <strong>Code</strong>, <strong>Band</strong> and <strong>Blind type</strong>. Inside a range you can file
            fabrics under headings of your own (<em>“+ Add group”</em>, e.g. Blackout) by dragging the ⋮⋮ handle or
            picking from the <strong>Group</strong> dropdown.</li>
      </ul>
      <p>Two things people trip over. <em>“Sugg. band”</em> is only the supplier’s <strong>suggestion</strong> — each
      client sets their own band when the fabric is added to a product. And untick <strong>Active</strong> on a range and
      it simply greys out and is marked <em>retired</em>; nothing is lost.</p>'],

    ['super', 'Fabric Library', 'How do I import a fabric range from a spreadsheet?', 'import fabrics excel spreadsheet xlsx csv ods preview review by sheet worksheet type skip duplicates manufacturer upload',
     '<p>Open <strong>Platform → Catalogue → Fabric Library</strong> and press <strong>Import fabrics</strong> (top
      right). On the <strong>Fabric import</strong> page choose the <strong>Manufacturer</strong> the fabrics belong to
      (the range must already exist), choose the <strong>Fabric list (spreadsheet)</strong> — .xlsx, .xlsm, .xls, .csv or
      .ods, up to <strong>12 MB</strong> — then press either <strong>Preview only</strong> (reads the file, changes
      nothing) or <strong>Import into library</strong>.</p>
      <p>It finds the header row for you and maps the columns by their wording — <strong>name / colour / code / band /
      type</strong> — and it reads <strong>every worksheet</strong>, so a supplier workbook with one sheet per blind type
      comes in on one pass. Price-grid sheets are skipped automatically.</p>
      <p>Always preview first, because the preview gives you a <strong>Review by sheet</strong> table: each sheet imports
      under a <strong>type</strong> taken from the sheet name, which you can edit to tidy up (e.g. “Decora Roller” →
      “Roller”), and you can untick a sheet in the <strong>Use</strong> column to skip it. Then press
      <strong>Import into … →</strong> — you do <em>not</em> need to choose the file again.</p>
      <p><strong>If it won’t read the file:</strong> “Couldn’t find a fabric list in that file” means the sheet needs a
      header row with a <strong>name</strong> column plus a <strong>colour</strong> or <strong>code</strong> column.
      Leave the preview sitting too long and you get “That preview has expired” — just upload and preview again.
      Fabrics already in that range (matched on <strong>name + colour</strong>) are skipped, so re-running an import is
      safe.</p>'],

    ['super', 'Fabric Library', 'Which fabric import do I want?', 'import fabrics difference three imports bulk across products library excel spreadsheet confused which one product options',
     '<p>There are three, and they fill different things. Pick by <strong>where you want the fabrics to end up</strong>.</p>
      <ul>
        <li><strong>Import fabrics</strong> — the button at the top of <strong>Platform → Catalogue → Fabric
            Library</strong>. Fills <strong>the library only</strong>. Nothing reaches anybody’s product until a product
            pulls the fabrics in. Use it for a manufacturer’s range you will reuse.</li>
        <li><strong>Bulk import fabrics across products</strong> — the blue panel on the same Fabric Library page. Takes
            one workbook with <strong>one sheet per product</strong> (columns <code>Name</code>, <code>Colour</code>,
            <code>Band</code>) and loads them <strong>straight into the matching products</strong>, skipping the library
            altogether. Press <strong>Upload &amp; match →</strong>, check the mapping, then <strong>Import all matched
            →</strong>. One sheet can feed several products — Ctrl/Cmd-click to pick more than one; leave a sheet with
            nothing selected and it is skipped.</li>
        <li><strong>Import from Excel</strong> — on a single product’s Fabrics page. Simplest when it is just the one
            product.</li>
      </ul>
      <p>All three skip duplicates rather than doubling rows up, so a second run is not a disaster. If you are not sure,
      import into the <strong>library</strong>: it is the only route you can reuse for the next client.</p>'],

    ['admin', 'Fabric Library', 'How do I put library fabrics onto one of my products?', 'add from fabric library pull fabrics product options band apply to system ticked choose manufacturer wizard',
     '<p>Go to <strong>Setup → Products</strong>, open the product, open its <strong>Fabrics</strong> page and press
      <strong>Add from fabric library</strong>. (In the setup wizard the same button sits on the Fabrics step, labelled
      <strong>📚 Import from Fabric Library</strong>, and brings you straight back to the wizard afterwards.)</p>
      <p>It is two steps. First <strong>Pick a fabric manufacturer</strong> — the table lists each one with how many
      fabrics it holds; press <strong>Choose →</strong>. Then you get the fabric list with <strong>every row already
      ticked</strong> (the tick box in the header turns them all on or off). Each row’s <strong>Band</strong> is
      pre-filled from the library’s suggested band and is yours to edit. Set <strong>Apply to system</strong> — either
      <em>All systems</em> or one particular system — then press <strong>Add ticked fabrics</strong>.</p>
      <p>The message that comes back tells you exactly what happened: how many were added, how many were
      <em>“skipped … already on this product”</em>, and how many <em>“had no band — set their band so they price
      correctly”</em>. That last one is the trap: a fabric whose band does not match a band on the product’s price table
      will not price. If the library range has groups, the group name rides along and shows as a <strong>Group</strong>
      column on the product’s Fabrics page.</p>
      <p>For the other ways in — paste a list, Excel, or copy from another product — see the walkthrough
      <em>“Adding fabrics — paste, Excel or library”</em> under Products.</p>'],

    ['super', 'Fabric Library', 'If I delete a range or a group, do I lose the fabrics?', 'delete range group remove fabric library undo destroy retired active products keep ungrouped warning',
     '<p>It depends entirely on which <em>Delete</em> you press, and only one of them destroys anything.</p>
      <ul>
        <li><strong>“remove group”</strong> beside a group heading inside a range — the fabrics are <strong>not</strong>
            deleted, they simply become <strong>Ungrouped</strong>. The confirmation box says so.</li>
        <li><strong>“remove group”</strong> beside a supplier group (Decora, Eclipse…) — same again: the ranges are not
            deleted, they just become ungrouped.</li>
        <li><strong>Delete range</strong>, inside a range’s own settings row — this one <strong>does</strong> destroy.
            It removes the range <em>and every library fabric in it</em>, and the confirmation spells out how many:
            “Delete … and ALL n of its library fabrics? Products that already pulled fabrics in keep them. No undo.”</li>
        <li><strong>Delete</strong> at the end of a single fabric row — removes just that one fabric from the library.</li>
      </ul>
      <p>The rule underneath all of it: the library is a <strong>master list to copy from</strong>. Once a product has
      pulled fabrics in, that product owns its own copies. Deleting from the library never reaches back into a client’s
      product — and, just as importantly, tidying the library does <strong>not</strong> fix a product that already has
      the wrong fabrics on it. Put that right on the product’s own Fabrics page.</p>
      <p>If a range is merely finished with rather than wrong, untick <strong>Active</strong> instead of deleting: it is
      marked <em>retired</em> and greyed out, and nothing is lost.</p>'],

    // ---- Master catalogue ----------------------------------------------
    ['super', 'Master catalogue', 'What is the Master Catalogue?', 'master catalogue source prefix supplier products price cells unassigned bev library platform',
     '<p>The <strong>Master Catalogue</strong> is the one set of price lists every client account is built from. Open it at
      <strong>Platform → Catalogue → Master Catalogue</strong>.</p>
      <p>All of it lives on a single account — the <em>master</em> account. The top of the page tells you which one
      (“The master catalogue lives on …”), and whether you are logged in as it. If you are not, the page is read-only:
      product names stop being links and the editing tools are hidden. Log in as that account to change anything.</p>
      <p>Products are filed under a supplier by the <strong>prefix on the product name</strong> — a product called
      “Bev Pleated” belongs to the supplier whose prefix is “Bev”. Each supplier is a collapsible section
      (<em>Expand all</em> / <em>Collapse all</em> at the top) showing its products with <strong>Systems</strong>,
      <strong>Fabrics</strong>, <strong>Options</strong> and <strong>Price cells</strong> counts. Anything that doesn’t
      start with a known prefix drops into <strong>Unassigned</strong> — and that is the trap: unassigned products are
      never copied out to clients. Rename them with a prefix if they should be. Products marked
      <strong>Inactive</strong> are shown greyed with a label.</p>
      <p>The buttons across the top take you to the rest of the set: <strong>Library suppliers</strong>,
      <strong>Supplier import</strong> and <strong>Push updates</strong>.</p>'],

    ['super', 'Master catalogue', 'How do I send catalogue changes out to clients?', 'push updates tenants clients sync copy prices catalogue distribute send',
     '<p>Use <strong>Platform → Catalogue → Push updates</strong>. Nothing you change on the master reaches a client
      until you push it — the two are separate jobs.</p>
      <p>The page is two lists. <strong>1. Suppliers to push</strong> — tick each supplier you want to send (its prefix and
      product count are shown; one with no products is greyed out and can’t be ticked). <strong>2. Tenants to push to</strong>
      — tick the client accounts; there’s a <em>Select all</em> on both lists. Then the red
      <strong>“Push to selected tenants »”</strong> button. It works through one client at a time with a progress bar
      (“Pushing… 2 of 7”) and drops a result card per client, so a big push won’t time out. Miss a tick and you get
      “Pick at least one supplier to push.” or “Pick at least one tenant to push to.”</p>
      <ul>
        <li><strong>What goes across:</strong> products, systems, fabrics, options and their choices, and price tables.
            New products are filed into a group named after the supplier.</li>
        <li><strong>What is never touched:</strong> a client’s own markup or discount percentages, and any product of
            theirs that doesn’t carry one of your prefixes.</li>
        <li><strong>How it merges:</strong> matched items are updated in place, missing ones added, and price cells
            overwritten where they overlap — sizes the client has that you don’t cover are kept.</li>
      </ul>
      <p>One thing that catches people: deleting a whole <em>product</em> on the master does <strong>not</strong> delete
      the client’s copy. Use <strong>Platform → Catalogue → Wipe products</strong> for that.</p>'],

    ['super', 'Master catalogue', 'How do I put catalogue prices up by a percentage?', 'price rise increase percent percentage bump uplift apply all supplier price change history',
     '<p>On <strong>Platform → Catalogue → Master Catalogue</strong>, logged in <em>as the master account</em>. The tools
      only appear when you are on that account.</p>
      <ul>
        <li><strong>One product:</strong> open the supplier’s section and type the figure in the small <em>%</em> box on
            that product’s row, then <strong>Apply %</strong>.</li>
        <li><strong>A whole supplier:</strong> at the top of its product table use <strong>Price change</strong> → type
            the <em>%</em> → <strong>Apply to all</strong>. That hits every product carrying the supplier’s prefix.</li>
      </ul>
      <p>Type it as a plain number: <code>4</code> for a 4% rise, <code>-2</code> for a 2% cut. Leave it empty and you get
      “Enter a percentage (e.g. 4 or -2).” Every price cell in the affected grids is multiplied and rounded to the nearest
      penny, and you’ll get a confirmation box first, because <strong>there is no undo</strong> — a 4% rise followed by a
      4% cut does not land back on the old figures.</p>
      <p>When it finishes you’ll see something like “Adjusted 12,480 prices by +4%.” Every change is logged under
      <strong>Price change history</strong> at the bottom of the page (last 60), showing when, who, what was changed,
      the percentage and how many prices moved.</p>
      <p>Remember the change stops on the master. Clients keep their old figures until you run
      <strong>Push updates</strong>.</p>'],

    ['super', 'Master catalogue', 'How do I add a new supplier to the library?', 'library suppliers add prefix free paid blurb subscribers retire delete supplier key',
     '<p><strong>Platform → Catalogue → Library suppliers</strong> is the register of suppliers the price-list library
      offers. Adding one here is step one; the products come afterwards.</p>
      <p>Under <strong>Add a supplier</strong> fill in:</p>
      <ul>
        <li><strong>Supplier name</strong> — what clients see, e.g. Decora.</li>
        <li><strong>Product prefix</strong> — the word every one of that supplier’s master products must start with.
            Miss this and you get “A supplier needs both a name and a prefix.”</li>
        <li><strong>Blurb</strong> (optional) — a line of description for the client subscribe page.</li>
        <li><strong>Free to every account</strong> — tick it and anyone gets it; leave it clear and it sits behind the
            price-library add-on.</li>
      </ul>
      <p>Press <strong>Add supplier</strong>. Each supplier then gets its own panel showing its <code>key</code> and how
      many subscribers it has, where you can edit the same fields, untick <strong>Active (shown in the library)</strong>
      to retire it quietly, or <strong>Delete supplier</strong>. Deleting doesn’t take anything off a client — they keep
      products already copied to them, they just can’t subscribe or re-import. If you only want it out of sight, untick
      Active instead.</p>
      <p>Two warnings. The prefix is the whole boundary: nothing gets pushed to clients unless the master product’s name
      starts with it. And these are <em>library</em> suppliers — not the per-tenant <em>order suppliers</em> in
      <strong>Setup → Settings → Suppliers</strong>, which are only about where a client’s orders are emailed.</p>'],

    ['super', 'Master catalogue', 'How do I load a supplier’s price list into the catalogue?', 'supplier import upload spreadsheet excel price list preview grids requests csv xlsx',
     '<p>Use <strong>Platform → Catalogue → Supplier import</strong>. It reads a supplier’s price-list workbook and writes
      the grids into the master account.</p>
      <p>Pick the <strong>Supplier (import target)</strong> from the dropdown — it lists each library supplier with its
      prefix — choose the <strong>Price-list file</strong> (.xlsx, .xlsm, .xls, .csv or .ods), then press one of the two
      buttons. <strong>Preview only</strong> reads the file and shows a <em>Products it read</em> table, changing nothing;
      always do this first. <strong>Import into catalogue</strong> writes it.</p>
      <ul>
        <li>Each worksheet becomes one product, named “&lt;prefix&gt; &lt;sheet name&gt;”, so the sheet names matter.</li>
        <li>It brings in the <strong>price grids only</strong>, on an auto-created system called <strong>Standard</strong>.</li>
        <li><strong>Fabrics are not imported here</strong> — add them to each product afterwards in
            <strong>Setup → Products</strong>, and rename or split the Standard system if the range needs more than one.</li>
        <li>Re-importing an updated file refreshes the prices instead of creating duplicates, so next year’s list is just
            another import.</li>
      </ul>
      <p>If a client asks for a supplier you don’t carry, their request lands on
      <strong>Platform → Catalogue → Supplier requests</strong> — a tally of the most-asked-for suppliers, who asked, any
      price list they attached (click to download), and <em>Mark handled</em> / <em>Reopen</em> per row.</p>'],

    ['super', 'Master catalogue', 'What is the Fabric Library for?', 'fabric library cloth manufacturer range colour code band blind type supplier group add from library',
     '<p>The <strong>Fabric Library</strong> (<strong>Platform → Catalogue → Fabric Library</strong>) is the reusable list
      of cloth. The Master Catalogue holds the <em>price tables</em>; this holds the <em>fabrics</em>, so a range is typed
      in once and then pulled into as many products as you like.</p>
      <p>Add a range with <strong>+ Add manufacturer</strong> (e.g. Louvolite), then expand it and add fabrics one at a
      time: <strong>Fabric name</strong>, <strong>Colour</strong>, <strong>Code</strong>, <strong>Band</strong> (the
      suggested price band) and <strong>Blind type</strong>. Ranges can be filed under a parent with
      <strong>+ Add supplier group</strong> and dragging the ⠿ grip; deleting a group never deletes the ranges inside it,
      they just become <em>Ungrouped</em>.</p>
      <p>To use it, open a product’s fabrics page and press <strong>Add from fabric library</strong>: choose the
      manufacturer, tick the fabrics you want, set <strong>Apply to system</strong> (All systems, or just one) and press
      <strong>Add ticked fabrics</strong>. The band comes across as a suggestion and can be changed on the product.</p>
      <p>Don’t confuse the two imports on this page. <strong>Import fabrics</strong> (top right) fills <em>this library</em>.
      <strong>Bulk import fabrics across products</strong> takes a workbook with one sheet per product and loads the
      fabrics <em>straight into the products</em>, skipping the library altogether — that’s the one for a supplier’s full
      fabric file.</p>'],

    // ---- Accounts ------------------------------------------------------
    ['admin', 'Accounts', 'Recording payments & deposits', 'payment deposit record ledger received outstanding balance money in paid cash cheque bank transfer reference edit delete retail',
     '<p>Retail payments are logged on <strong>Retail → Payments</strong> in the left-hand menu. It is part of the
      paid <strong>Accounts</strong> add-on, so if you can’t see it, it isn’t switched on for you (the page answers
      <em>“Accounts module not enabled”</em>).</p>
      <p>Three cards across the top tell you where you stand: <strong>Outstanding</strong>,
      <strong>Received this month</strong> and <strong>All-time received</strong>.</p>
      <ul>
        <li>Press <strong>+ Record payment</strong>. Fill in <strong>Order (optional)</strong> — pick the order the
        money is for, so it counts against that balance — then <strong>Amount £</strong>, <strong>Received on</strong>,
        <strong>Method</strong> (Cash, Card, Bank transfer, Cheque, PayPal, Stripe, GoCardless, Other),
        <strong>Received from (optional)</strong> and <strong>Reference (optional)</strong>. Press
        <strong>Save payment</strong>.</li>
        <li>Under <strong>Payment history</strong> each order has its own block. <strong>Edit</strong> reopens the same
        panel with the figures filled in; the red <strong>×</strong> deletes the payment.</li>
      </ul>
      <p><strong>The deposit is not entered here.</strong> Open the order itself and use the <strong>Deposit</strong>
      panel — <em>Deposit paid £</em> then <strong>Record deposit paid</strong> (a suggested figure from your settings
      is offered as a link). The deposit then appears in this list, shaded, marked
      <em>managed on the order</em> instead of Edit and ×. If you pick an order that already has a paid deposit, the
      panel warns you the deposit is counted in the balance — don’t key it in twice.</p>
      <p><strong>The trap:</strong> this page is your own retail customers only. Money from a <strong>trade
      account</strong> is recorded on a different screen altogether, so it can be allocated across their invoices.</p>'],

    ['admin', 'Accounts', 'Getting my figures to the bookkeeper (CSV export)', 'accounts csv export xero quickbooks sage invoices payments accounting accountant bookkeeper download spreadsheet vat',
     '<p>On <strong>Retail → Payments</strong>, admins get three export buttons at the top right:
      <strong>Export invoices (CSV)</strong>, <strong>Export for QuickBooks (CSV)</strong> and
      <strong>Export payments (CSV)</strong>. They download spreadsheet files you hand to your bookkeeper or import
      into <strong>Xero, QuickBooks or Sage</strong>. The Help guide <em>Get your figures into Xero, QuickBooks or
      Sage</em> walks through each package step by step.</p>
      <ul>
        <li><strong>Export invoices (CSV)</strong> — one row per order line, priced <em>net of VAT</em> so the
        accounting package works the tax out itself. It covers every order from <strong>accepted</strong> onward
        (accepted, ordered, fitted, invoiced and paid) — not just the ones you have invoiced. Each order is dated by
        when it was accepted, falling back to when it was created, with a due date <strong>14 days</strong> later.</li>
        <li><strong>Export for QuickBooks (CSV)</strong> — the same invoices in QuickBooks Online’s own import
        layout (Settings ⚙ → Import data → Invoices): no minus lines (an agreed-price discount is spread across that
        invoice’s lines so the total still matches), QuickBooks’ VAT codes (20.0% S / 5.0% R / No VAT), a line amount
        and rate, and the item “Blinds” on every line. More than 100 invoices comes as a .zip of numbered parts,
        because QuickBooks only takes 100 invoices per file.</li>
        <li><strong>Export payments (CSV)</strong> — one row per payment received: date, order number, customer,
        amount, method, reference, and whether it was a Deposit or a Payment.</li>
      </ul>
      <p>Both files respect the <strong>date filter</strong> further down the page, so set From / To (or press
      <strong>This month</strong> or <strong>Last month</strong>) <em>before</em> you export. The files come out named
      <code>&lt;your-company&gt;-invoices-&lt;date&gt;.csv</code> and
      <code>&lt;your-company&gt;-payments-&lt;date&gt;.csv</code>.</p>
      <p>The invoice rows default to account code <strong>200 (Sales)</strong> and <strong>20% VAT</strong>. Those are
      Xero’s usual UK defaults — if your chart of accounts differs, remap them as you import, not here.</p>
      <p>There is also a live <strong>QuickBooks Online</strong> link under Settings → Accounting, but it only sets up
      the connection at the moment; the CSV is still how the figures actually reach your accounts.</p>'],

    ['admin', 'Accounts', 'Can I link QuickBooks so I don\'t re-key everything?', 'quickbooks qbo intuit connect link accounting integration oauth mapping sales receipt xero sage api',
     '<p>You can link your <strong>QuickBooks Online</strong> company today, but be clear what that does and doesn’t
      do yet. Go to <strong>Setup → Settings</strong> and pick the <strong>Accounting</strong> tab.</p>
      <p>There you’ll see a <strong>QuickBooks Online</strong> card with a badge reading
      <strong>Sandbox (test)</strong> or <strong>Live</strong>, and one of three states: <em>Not set up</em>,
      <em>Not connected</em> or <em>● Connected</em>.</p>
      <ul>
        <li><strong>Connect to QuickBooks</strong> sends you to QuickBooks’ own site to sign in and approve access —
        YourBlinds never sees your accounting password.</li>
        <li>Once connected, <strong>Set up mapping</strong> (later <strong>Edit mapping</strong>) asks four things:
        <em>How to record a paid sale</em> (Sales Receipt, or Invoice + Payment), the <em>Sales item</em> (required —
        QuickBooks insists on an item on every line, so one “Blinds” item is plenty), the <em>Default VAT / tax
        code</em>, and the <em>Bank account paid sales land in</em>.</li>
        <li><strong>Disconnect</strong> unlinks it again.</li>
      </ul>
      <p><strong>It is cash accounting.</strong> Nothing is ever sent to QuickBooks until a sale is <em>paid</em> — you
      will never see an open debtor created from here.</p>
      <p><strong>Be warned:</strong> at the moment this sets up the connection and the mapping only — no sales are
      pushed across automatically yet. Until that arrives, keep using <strong>Export invoices (CSV)</strong> and
      <strong>Export payments (CSV)</strong> on the Payments page. <em>Xero &amp; Sage</em> are shown on the same tab
      as “Coming soon”.</p>'],

    ['super', 'Accounts', 'How do I raise a delivery note and invoice for a trade account?', 'delivery note dn invoice trade wholesale raise dispatch print billing account order paperwork',
     '<p>Go to <strong>Trade → Invoices</strong> in the left-hand menu (the page itself is headed
      <strong>Wholesale</strong>). Every placed trade-account order that contains your products is one row, with a
      status pill and one button for whatever comes next.</p>
      <p>What the button says depends on the flow mode set at the top of the page:</p>
      <ul>
        <li><strong>One-step</strong> — a single <strong>Print DN &amp; invoice</strong> button: it raises and
        dispatches the delivery note and creates and sends the invoice in one go.</li>
        <li><strong>Two-step</strong> — you walk it through yourself: <strong>Raise delivery note</strong> →
        <strong>Mark dispatched</strong> → <strong>Raise invoice</strong> → <strong>Mark sent</strong>.</li>
      </ul>
      <p>Click the little arrow (▸) at the start of a row to open the documents underneath. Each one has
      <strong>View / print</strong> for its PDF, plus its own actions (Mark dispatched, Mark sent, Void, Credit note,
      Cancel). <strong>+ Duplicate delivery note</strong> gives you a second DN for the same order and does
      <em>not</em> raise a second invoice.</p>
      <p>Numbers are per order, not a running sequence: a delivery note is <code>DN-</code> plus the order number and an
      invoice is <code>INV-</code> plus the order number. The status pill is worked out from the documents —
      <em>Ordered, DN draft, Delivered, Invoiced (draft), Invoiced, Paid, Credited</em> — and the column filter above
      it matches.</p>
      <p><strong>Two things that stop you:</strong> an order that isn’t finished refuses with
      <em>“Can\'t dispatch — the order isn\'t ready yet (every blind made and every bought-in item received).”</em>, and
      an order already covered by a live invoice refuses with <em>“Order already invoiced on … (void it first to
      re-invoice).”</em></p>'],

    ['super', 'Accounts', 'One-step or two-step invoicing — which should I use?', 'one step two step auto invoice dispatch delivery note flow wholesale setting mode automatic manual',
     '<p>There are <strong>two different switches</strong> at the top of <strong>Trade → Invoices</strong> and people
      mix them up constantly. They do not do the same job.</p>
      <ul>
        <li><strong>Delivery-note flow: One-step · print &amp; invoice / Two-step · manual.</strong> This only changes
        the button on <em>this page</em>. On <strong>One-step</strong> (the normal setting), printing a delivery note
        dispatches it and creates and sends the invoice automatically, once per order. On <strong>Two-step</strong> you
        raise the delivery note, then raise and send the invoice yourself — four presses instead of one.</li>
        <li><strong>Auto-invoice on dispatch: On / Off.</strong> This is much wider. With it <strong>On</strong>,
        dispatching an order by <em>any</em> route — including someone finishing it on the factory floor — raises and
        sends its invoice, once per order. With it <strong>Off</strong> (the default), dispatching simply marks the
        order ready to invoice and you raise the invoice when you’re ready.</li>
      </ul>
      <p>Both are site-wide settings, not per account, and each one takes effect the moment you press it — the grey
      line beside the buttons always spells out what is happening now, so read it after you switch.</p>
      <p><strong>Our advice:</strong> leave <em>Auto-invoice on dispatch</em> <strong>Off</strong> until you have
      watched a few orders go through and you’re happy they price correctly — the screen says exactly that. Once you
      trust it, turning it on means nothing ever sits un-invoiced because the office forgot.</p>'],

    ['super', 'Accounts', 'A trade invoice is wrong — credit it or void it?', 'void credit note cn cancel invoice wrong mistake wholesale reissue re-invoice trade refund adjust',
     '<p>Both live on <strong>Trade → Invoices</strong>: open the order’s row with the ▸ arrow and you’ll see
      <strong>Void</strong> and <strong>Credit note</strong> beside each invoice. The difference matters.</p>
      <ul>
        <li><strong>Void</strong> — use it when the invoice should never have gone out as it is (wrong figure, wrong
        account, raised too early). The number is kept so your numbering has no gaps, and you can then raise a fresh
        invoice for that order. The confirm box says it plainly: <em>“Its number is kept; raise a fresh invoice to
        replace it.”</em> You can’t void an invoice that is already void, or one marked paid.</li>
        <li><strong>Credit note</strong> — use it when the invoice was correct at the time but the customer is owed
        money back (goods returned, an allowance given). It copies every line of the invoice into a credit note
        numbered against the same order, and it shows on the row as a minus. Print it with <strong>View / print</strong>.
        A credit note raised in error can itself be voided.</li>
      </ul>
      <p>You can’t do both to the same invoice: raising a credit note against a voided invoice is refused with
      <em>“That invoice is void — nothing to credit.”</em> — there is nothing left to credit, because voiding already
      cancelled it.</p>
      <p><strong>Rule of thumb:</strong> nothing sent yet, or plainly wrong → <strong>Void</strong> and re-raise. The
      customer has the invoice and it needs to stay in the books → <strong>Credit note</strong>.</p>'],

    ['super', 'Accounts', 'How do I record a payment from a trade account?', 'trade payment received allocate allocation invoice outstanding wholesale account money in bank transfer cheque void payment',
     '<p>Trade payments are <em>not</em> recorded on the retail Payments page. Go to <strong>Trade → Trade
      accounts</strong> and click <strong>Payments</strong> on the account’s row (or <strong>Record payment</strong> on
      the account’s own page). The screen is headed <strong>Payments — &lt;the account’s name&gt;</strong>.</p>
      <p>Four cards show where the account stands: <strong>Invoiced</strong>, <strong>Credited</strong>,
      <strong>Paid</strong> and <strong>Outstanding</strong>.</p>
      <ul>
        <li>Under <strong>Record a payment</strong>, enter <strong>Amount</strong>, <strong>Date</strong>,
        <strong>Method</strong> (Bank transfer, Cash, Card, Cheque, Other) and a <strong>Reference</strong> such as the
        bank reference or cheque number.</li>
        <li>As you type the amount, the <strong>Allocate</strong> column against the open invoices fills itself in
        <strong>oldest first</strong>. That is only a suggestion — if the customer is paying one particular invoice,
        clear the boxes and put the money where it belongs. You can’t over-pay an invoice, and if you allocate more
        than the payment you get <em>“Allocated (…) is more than the payment (…). Adjust the allocation.”</em></li>
        <li>Add <strong>Notes</strong> if you want, then press <strong>Record payment</strong>.</li>
      </ul>
      <p>Anything you don’t allocate isn’t lost — it sits as <strong>credit on account</strong> and the confirmation
      message tells you how much. If there are no open invoices at all, the whole payment sits as credit.</p>
      <p>Below, <strong>Payment history</strong> lists every payment with its number and a <strong>Void</strong> button.
      Voiding a payment puts the invoices it covered back to open.</p>'],

    ['super', 'Accounts', 'Month-end: chasing what you\'re owed', 'statement run aged debt debtors overdue chase credit control month end email statements pdf as at date',
     '<p><strong>Trade → Statements</strong> is your chase list. It shows every trade account that owes you money,
      aged into <strong>Current</strong>, <strong>1–30</strong>, <strong>31–60</strong>, <strong>61–90</strong> and
      <strong>90+ days</strong>, with a grand total across the bottom so you can see the whole debt at a glance.</p>
      <p>Set the <strong>As at date</strong> and press <strong>Apply</strong> — for month-end, put in the last day of
      the month rather than today, so the figures match your books. Clicking an account name (or
      <strong>Statement PDF</strong> on its row) opens just that account’s statement.</p>
      <ul>
        <li><strong>Download combined PDF</strong> gives you every statement in one file, one account per page, to
        print or file.</li>
        <li><strong>Email statements (n)</strong> sends each account its own statement PDF. The number in brackets is
        how many have an email on file — accounts without one are badged <em>no email</em> and belong in the printed
        batch. Every send is logged, and an account already emailed for that same <em>as at</em> date is skipped and
        badged <em>emailed</em>, so pressing it twice won’t double-mail anybody.</li>
      </ul>
      <p><strong>If the button says “Email statements (paused)” and won’t click</strong>, that is the site-wide testing
      switch, not a fault. Outgoing email is paused for the whole system — turn off <em>Pause all outgoing emails
      (testing mode)</em> under <strong>Platform → Clients → Overview</strong> and come back. Printing and previewing
      still work while it’s paused.</p>'],

    ['super', 'Accounts', 'Where do I set up a trade account and its login?', 'trade account new customer business portal login password discount commission address vat deactivate no portal',
     '<p><strong>Trade → Trade accounts</strong> is the list of every business you supply. The search box filters on
      name, contact, email or phone as you type, and each row shows whether they have a <strong>portal</strong> login or
      are <strong>No portal</strong>, their plan, how many discounts are set, when they last logged in, and quick links
      to <strong>Payments</strong> and <strong>Statement</strong>. <strong>+ New account</strong> creates one.</p>
      <p>Click the account name (or <strong>Manage →</strong>) to open it. That page holds:</p>
      <ul>
        <li><strong>Account details</strong> — company name, contact, email, phone, mobile, VAT number and the full
        address. This address is what gets snapshotted onto their delivery notes and invoices, so get it right. Press
        <strong>Save details</strong>.</li>
        <li><strong>Logins</strong> — <strong>+ Add a login</strong> takes a full name, email and an initial password
        (8 characters or more) and creates an <em>admin</em> login, already verified. Best practice is then to use
        <strong>Send reset link</strong> so they choose their own. Existing logins can have their password set, their
        email or username changed, or be deactivated. An account with no login at all is a perfectly normal
        <strong>no-portal</strong> account — a one-off you invoice and ship to.</li>
        <li>Their standing <strong>discounts</strong> and their sales consultant’s <strong>Commission</strong>.</li>
      </ul>
      <p><strong>Deactivate</strong> at the top right stops their logins signing in without deleting anything. For the
      whole money picture — balance, aged debt, orders with their production stage, open invoices and payment history —
      open the account overview instead.</p>'],

    ['super', 'Accounts', 'How do I work out a consultant\'s commission?', 'commission consultant sales rep statement percentage turnover rate pdf agent introducer',
     '<p><strong>Trade → Commissions</strong> works out what a sales consultant has earned. Pick the
      <strong>Consultant</strong>, set <strong>From</strong> (leave blank for everything from the start) and
      <strong>To</strong>, then press <strong>Apply</strong>.</p>
      <p>The table lists a line per account and product with the <strong>Turnover (net)</strong>, the
      <strong>Rate</strong> and the <strong>Commission</strong>, and totals at the bottom.
      <strong>Download PDF</strong> gives you a copy to send or file.</p>
      <p>Two things decide the figures:</p>
      <ul>
        <li><strong>Turnover</strong> is <em>invoiced</em> net turnover, ex VAT — what you actually billed the account
        in that period. An order that hasn’t been invoiced yet earns nobody anything, and a voided invoice is left
        out.</li>
        <li><strong>The rate</strong> comes from each account, not from this page. Open <strong>Trade → Trade
        accounts → the account → Commission</strong> and add a row: <strong>Commission %</strong>, the
        <strong>Sales Consultant</strong>, and the <strong>Product</strong> it applies to (<em>All</em> = every
        product). One consultant can have different percentages on different products for the same account.</li>
      </ul>
      <p>Consultants themselves are added once, under <strong>+ Add a sales consultant</strong> on that same section,
      and are then shared across every account. There is also a tick there to <strong>email the consultant when the
      account places orders through the trade portal</strong>.</p>
      <p>So if a consultant shows nothing here, check the account has a commission row <em>and</em> that its orders have
      been invoiced.</p>'],

    // ---- Settings ------------------------------------------------------
    ['admin', 'Settings', 'Where do I set my quote prefix, VAT and deposit?', 'settings vat deposit prefix quote number tabs company quoting legal suppliers colours accounting measurement unit mm cm inches margins bank details save',
     '<p>Open <strong>Setup → Settings</strong> in the left-hand menu. The page is split into tabs along the top —
      <strong>Company</strong>, <strong>Quoting</strong>, <strong>Legal</strong>, <strong>Status colours</strong>,
      <strong>Suppliers</strong>, <strong>Accounting</strong> and <strong>Back up data</strong> — and nearly everything
      about how a quote is priced and printed sits on the <strong>Quoting</strong> tab.</p>
      <p>On the Quoting tab, under <em>Quote defaults</em>:</p>
      <ul>
      <li><strong>Quote prefix</strong> — the letters at the front of every quote number. Numbers come out as
      <code>BRI-2026-0042</code>: your prefix, the year, then a number that counts up on its own each year. Leave it
      empty and the app uses the first three letters of your company name.</li>
      <li><strong>VAT %</strong> — normally 20.</li>
      <li><strong>Default deposit</strong> — choose <em>Percentage of total</em> or <em>Flat amount</em> and type the
      figure. It only seeds the deposit on a quote the moment it moves into Accepted, and you can still change it on
      the job itself.</li>
      </ul>
      <p>The same tab also holds <strong>Default margins</strong>, <strong>Measurements</strong> (mm, cm, m or inches
      — you can switch any time) and <strong>Bank details for customer payments</strong>.</p>
      <p><strong>The trap:</strong> every section has its own <strong>Save</strong> button. Saving under <em>Quote
      defaults</em> does <em>not</em> save boxes you changed in another section, so press Save in each one you touch
      before you leave the page.</p>'],

    ['admin', 'Settings', 'Where do I add a supplier and its order email?', 'supplier suppliers order email purchase order delivery address account number remove add stock cannot send blocked',
     '<p><strong>Setup → Settings → Suppliers</strong> tab. This is the list of who you <strong>order stock from</strong>
      — it fills a product’s <em>Order supplier</em> field and it is what supplier orders get emailed to.</p>
      <p>The tab has two parts:</p>
      <ul>
      <li><strong>Delivery address</strong> — your business or warehouse address, where suppliers ship to. It is
      printed on every supplier order, so fill it in once.</li>
      <li>The supplier table — <strong>Supplier</strong>, <strong>Order email</strong>, <strong>Account no.</strong>
      (your account number with them) and a <strong>Remove</strong> tick. Type into the bottom
      <em>“+ Add a supplier”</em> row to add a new one, then press <strong>Save suppliers</strong>.</li>
      </ul>
      <p>You rarely need to add one by hand: a supplier you set on a product turns up here automatically when you save
      that product. That is also why strays appear — tick <strong>Remove</strong> on the row and Save to clear one out.</p>
      <p><strong>The trap:</strong> a supplier with no order email, or a junk one, is flagged on the <em>Send order to
      suppliers</em> screen and <strong>cannot be sent</strong> — the order simply sits there. If a supplier order
      won’t go out, an empty Order email box here is the first thing to check.</p>'],

    ['admin', 'Settings', 'Where do I edit my terms and privacy policy?', 'terms conditions t&c tcs privacy policy legal trade retail thank you email placeholders tokens preview link',
     '<p><strong>Setup → Settings → Legal</strong> tab. There are <strong>three</strong> documents there, not one:</p>
      <ul>
      <li><strong>Terms &amp; Conditions (retail)</strong> — used on quotes for your own end customers.</li>
      <li><strong>Terms &amp; Conditions (trade)</strong> — used on a quote raised for a <strong>trade account</strong>.
      A trade quote never uses the retail wording, so this box needs filling in separately.</li>
      <li><strong>Privacy Policy</strong>.</li>
      </ul>
      <p>Underneath sits the <strong>Thank-you email</strong> that goes out when a customer accepts a quote online.
      One button, <strong>Save terms, privacy &amp; email</strong>, saves all four.</p>
      <p>Each box has a live <strong>Preview</strong> below it showing the finished text with your real company details
      and an example customer, so the <code>{{placeholders}}</code> explain themselves as you type.</p>
      <p><strong>Worth knowing:</strong> quotes no longer print pages of small print. The PDF carries a single line —
      <em>“This quotation is subject to our Terms &amp; Conditions of sale”</em> — linking to the live copy at
      <code>/legal/view.php</code>, so editing the text here updates what every customer sees straight away.
      <strong>Leave a box empty to show nothing</strong>: blank it and the link for that document disappears from the
      quote altogether. This is a starting point, not legal advice — have it checked before you rely on it.</p>'],

    ['admin', 'Settings', 'What do Bronze, Silver and Gold get me?', 'billing tier bronze silver gold plan plans subscription add-on cancel paypal upgrade downgrade price vat trial comp',
     '<p><strong>Setup → Billing</strong>. There are three tiers and each one <strong>includes everything below it</strong>,
      so you are only ever on one at a time:</p>
      <ul>
      <li><strong>Bronze</strong> — free, forever. Quotes, calendar, customers, orders and products.</li>
      <li><strong>Silver</strong> — everything in Bronze plus <strong>Maps</strong> (run optimiser, customer-pin map,
      the “Let’s go” links) and <strong>Postcode lookup</strong>.</li>
      <li><strong>Gold</strong> — everything in Silver plus <strong>Accounts</strong>: payment tracking, outstanding
      balances and the account summary.</li>
      </ul>
      <p>The live monthly price is printed on each plan card. Billing is monthly in GBP through <strong>PayPal</strong>,
      plus VAT, and the top of the page shows your plan and your monthly total. A card carries a badge when it needs
      one — <em>Active</em>, <em>Activating</em>, <em>Past due</em>, <em>Cancelled</em>, <em>🎁 Free trial</em> or
      <em>Comp’d</em> (free, arranged by your account manager).</p>
      <p><strong>If you cancel</strong> (the <em>Cancel subscription</em> button on the card), PayPal stops billing you
      immediately — but you have already paid to the end of the current period, so the features stay switched on until
      that date and then drop back to Bronze. Nothing is deleted: your quotes, orders and customers are all still there
      if you come back.</p>'],

    ['all', 'Settings', 'The grey help lines have vanished / everything looks cramped', 'compact mode density hints tips missing gone disappeared grey text cramped tight small spacing comfortable subtitle explanations',
     '<p>Nothing is broken — <strong>Compact mode</strong> is switched on. Someone has pressed it on this device
      (it is easily done by accident, as the button sits right next to Dark mode).</p>
      <p>To put it back: scroll to the <strong>bottom of the left-hand menu</strong>, below <em>Sign out</em>. Next to
      the <strong>Dark mode</strong> button there is a second one. When compact is on it reads
      <strong>“Comfortable mode”</strong> — press that and the page goes back to normal at once. When it is off, the
      same button reads <strong>“Compact mode”</strong>.</p>
      <p>Compact mode is for people who know the system and want more on screen. It:</p>
      <ul>
      <li>hides the <strong>grey guidance lines</strong> under fields and the grey subtitle under each page heading;</li>
      <li>tightens the spacing — smaller gaps between sections, shallower boxes, tighter table rows.</li>
      </ul>
      <p>It changes nothing about your data, your prices or what a customer sees — only how this screen is laid out.</p>
      <p><strong>Worth knowing:</strong> the setting is <strong>per device</strong>, remembered in this browser for a
      year. Turning it off on the office PC will not turn it off on the tablet or the phone, and a colleague signing in
      on their own machine is unaffected.</p>'],

    ['admin', 'Settings', 'What do the ticks on the Users page actually do?', 'users permissions roles create quotes orders view all customer jobs view costs fittings only dashboard panels leaderboard gross profit hidden missing menu active sign in',
     '<p><strong>Setup → Users</strong>, then click a person to edit them. Two blocks of ticks decide what they see.</p>
      <p><strong>Permissions:</strong></p>
      <ul>
      <li><strong>Create quotes</strong> / <strong>Create orders</strong> — whether they can raise one at all.</li>
      <li><strong>View all customer jobs</strong> — untick it and the <strong>Orders</strong> list and the
      <strong>Pipeline</strong> only show jobs that are assigned to them. This is the usual reason someone says
      “my orders have disappeared”.</li>
      <li><strong>View costs</strong> — lets them see what a job costs you, not just what it sells for.</li>
      <li><strong>Fittings only</strong> — their calendar shows fitting jobs and hides measure and sales visits.
      Ideal for a fitter.</li>
      </ul>
      <p><strong>Dashboard</strong> (the boxed section below): <em>Revenue &amp; KPIs</em>, <em>Sales-team
      leaderboard</em>, <em>Product mix</em>, <em>Gross profit</em> and <em>Recent wins</em> — tick the panels this
      person may see. <strong>Gross profit also needs <em>View costs</em></strong> ticked above, and
      <strong>ticking none of them hides the Dashboard menu entry</strong> for that user entirely.</p>
      <p>These Dashboard ticks are ignored for anyone with the <strong>Admin</strong> role — admins always see
      everything. Above the permissions sit <strong>Roles</strong> (tick every role the person fills) and below them
      <strong>Active (can sign in)</strong>, which is how you switch a leaver off without deleting them. You cannot
      untick your own Admin role, or make yourself inactive.</p>'],

    ['admin', 'Settings', 'How do I take my own copy of my quotes and orders?', 'backup back up export excel xlsx spreadsheet pdf download data copy off-site changes since last accountant',
     '<p><strong>Setup → Settings → Back up data</strong> tab. It downloads a copy of <strong>your quotes and
      orders</strong> — with their line items, totals and payments — straight to your own computer.</p>
      <p>Set a <strong>From</strong> and <strong>To</strong> date, or use the <strong>All time</strong> /
      <strong>Last 30 days</strong> / <strong>This year</strong> buttons, then pick a format:</p>
      <ul>
      <li><strong>⬇ Download Excel (.xlsx)</strong> — the one to keep. Two sheets: a <em>Quotes &amp; Orders</em>
      summary and a full <em>Line items</em> list, both sortable and filterable.</li>
      <li><strong>⬇ Download PDF summary</strong> — a printable one-look list, handy to hand to someone.</li>
      </ul>
      <p>Dates filter by <strong>order date</strong> — the date it was accepted, or the date it was created if it has
      not been accepted yet.</p>
      <p>Once you have taken a full Excel backup with <strong>no dates set</strong>, the panel underneath remembers when
      that was and offers <strong>“⬇ Changes since last backup (Excel)”</strong> — just the quotes and orders created
      or changed since. Only a full, dateless Excel backup resets that marker.</p>
      <p><strong>The trap:</strong> this is a copy of your figures for your own records or your bookkeeper. It is
      <strong>not a restore file</strong> — there is no button that reads a spreadsheet back in. Take one regularly and
      keep it somewhere off the machine.</p>'],

    ['admin', 'Settings', 'What is the Trade terms page telling me?', 'trade terms discount discounts promotion promotions buying price cost supplier band system option standing where do i see it',
     '<p><strong>Setup → Trade terms</strong>. It shows the discounts <strong>you</strong> have been given on what you
      <strong>buy</strong> — nothing to do with what you charge your own customers.</p>
      <p><strong>Your standing discounts</strong> lists them as <em>Discount</em>, <em>Product</em> and
      <em>Applies to</em>. That last column reads either a system and price band (for example
      <code>Vogue · Band A</code>, or <code>All systems · All bands</code> where it covers the lot), or
      <code>Option: …</code> when the discount is on a single option or one of its choices.</p>
      <p><strong>Current promotions</strong> appears when there are time-limited offers running, with an
      <strong>Until</strong> date. Where a promotion and a standing discount both apply to the same thing,
      <strong>you get the larger of the two</strong> — you never have to work it out.</p>
      <p><strong>The bit people get wrong:</strong> these come off the <strong>trade price you pay</strong>, and they
      are applied automatically the moment you price that product — so your <em>cost</em> is already reduced before
      your own markup and any discount you give your customer go on top. They are not a discount to your customer;
      that is set per product under <strong>Setup → Products</strong>.</p>
      <p>Nothing on this page is editable — it is a statement of your account. If it says <em>“Your trade terms
      aren\'t set up yet”</em> or <em>“You\'re on standard terms”</em>, there are no special rates on your account and
      you pay the normal trade price.</p>'],

    // ---- Backups & safety ----------------------------------------------
    ['super', 'Backups & safety', 'How do I back up the database — and put it back?', 'backup restore recover data loss snapshot sql dump database export tenant takeout disaster oops deleted everything undo',
     '<p>Go to <strong>Platform → System health → Backup</strong>. The page is titled <strong>Backup &amp; Restore</strong> and does three jobs.</p>
      <ul>
      <li><strong>Download backup</strong> — <em>“Download backup now”</em> streams the whole database out as one <code>.sql</code> file.</li>
      <li><strong>Per-tenant export</strong> — pick a company from the <em>“— Choose a tenant —”</em> list and press <em>“Download tenant export”</em> for just that client’s users, customers, quotes, appointments, products, fabrics and price tables.</li>
      <li><strong>Restore from backup</strong> — in the red <strong>Danger zone</strong>: choose a <code>.sql</code> file, tick <em>“I understand this overwrites the current database.”</em> and press <strong>Restore database</strong>. It drops every table and rebuilds them from the file, so anything added since that backup was taken is gone.</li>
      </ul>
      <p>Before a restore runs, the app takes its own copy first, listed underneath as an <strong>Auto-snapshot</strong> (<code>auto-prerestore-…</code>). If the restore goes wrong, restore that snapshot to get back to where you were. Delete the snapshots once you are happy.</p>
      <p><strong>Two traps.</strong> The backup holds <em>data only</em> — uploaded logos and fabric images live in <code>/uploads</code> and are <strong>not</strong> in it. And a tenant export is built to load into a <strong>fresh empty database</strong>; dropping it into the live one would wipe the other tenants.</p>
      <p>The host also takes an automatic nightly backup. An ordinary client sees none of this — their own copy of their figures comes from <strong>Setup → Settings → Back up data</strong>.</p>'],

    ['super', 'Backups & safety', 'Is this account ready to go live?', 'go live launch golive readiness paypal sandbox live test data checklist pre launch switch on ready open for business',
     '<p><strong>Platform → Clients → Go-live checklist</strong> tells you what is still standing between this account and taking real money. It is read-only — nothing on the page changes any data.</p>
      <p><strong>Automatic checks</strong> gives a green tick, an amber <em>!</em> or a red ✕ against: <strong>PayPal mode</strong> (sandbox or live, and whether the credentials are there), <strong>App environment</strong> (should be <code>production</code> so errors never show to a customer), <strong>Google Maps key</strong>, <strong>Postcode lookup key</strong>, <strong>Terms &amp; Conditions</strong>, <strong>VAT number</strong>, and <strong>Manual backup</strong> — which goes amber once your last full export is more than seven days old.</p>
      <p><strong>Left-over test data (this account)</strong> lists any product, supplier or customer whose name contains <em>test</em>, <em>demo</em> or <em>dummy</em>, plus a count of the quotes and orders on file by status. It is a name match only, so eyeball the lists yourself too — a test job called “Mrs Smith” won’t show up here.</p>
      <p><strong>Switch PayPal to live</strong> spells out the five steps (live credentials → <code>PAYPAL_ENV=live</code> in the server’s <code>.env</code> → recreate the plans under <strong>Platform → Billing &amp; plans → Pricing</strong> with <em>“Create on PayPal”</em> → register the live webhook → reload the page). Only needed if you’ll charge subscriptions or take payments.</p>
      <p><strong>Do these by hand</strong> is the human list: send yourself a real quote to prove email works, do a full dry run, finish the catalogue, create the real staff logins, and take a fresh backup right before you flip the switch. Note it only ever scans the account you are logged in as, not every tenant.</p>'],

    ['super', 'Backups & safety', 'How do I bulk-delete products across tenants without breaking everything?', 'wipe products delete bulk master catalogue protect cascade remove clear out tidy up mass delete',
     '<p><strong>Platform → Catalogue → Wipe products</strong> deletes matching products from several tenants at once. It is a testing-phase tool — once a tenant has real quotes coming in, delete products on that tenant’s own admin screens instead, so their admin can see what happened.</p>
      <p>It works in three steps:</p>
      <ul>
      <li><strong>1. Filter</strong> — type part of a product name and press <strong>Preview</strong>. The match ignores capitals and looks anywhere in the name, so <code>Beverley</code> finds every product with “Beverley” in it.</li>
      <li><strong>2. Tenants with matches</strong> — every tenant holding a match, with the matched product names underneath. All are pre-ticked; untick any you want left alone, or use <em>toggle all</em>.</li>
      <li><strong>3. Confirm</strong> — type <strong>WIPE</strong> in the box and press <strong>Wipe selected</strong>.</li>
      </ul>
      <p>Deleting a product cascades: its systems, fabrics, options, choices, price tables and price rows all go with it. Quotes raised earlier keep working — they snapshot their own figures — so old PDFs and order history don’t break.</p>
      <p><strong>The master catalogue is protected.</strong> It is never pre-ticked, it is flagged in red as <em>“★ Master catalogue — leave unticked”</em>, and it is stripped out of the delete unless you separately tick <em>“Include the master catalogue”</em>. Wiping it takes down the source library every other tenant is built from.</p>
      <p>The screen also refuses an empty filter (<em>“Name filter required — refuse to wipe every product.”</em>) and a missing confirmation (<em>“Type the word WIPE in the confirmation field.”</em>). There is no undo short of a database restore — take a backup first.</p>'],

    ['super', 'Backups & safety', 'What happens if I delete a client?', 'delete client tenant remove company permanent cascade wipe account protected your client cannot delete',
     '<p>It removes them completely, and there is no undo. The <strong>Delete</strong> button sits at the end of each row on <strong>Platform → Clients → Overview</strong>, and the confirmation box spells it out: <em>all</em> of that client’s data goes — users, customers, quotes, products, fabrics, systems, options, choices, price tables and price rows.</p>
      <p>Two guards stop the worst accidents:</p>
      <ul>
      <li><strong>Your own client can’t be deleted.</strong> Its row shows <em>“(your client)”</em> instead of a button.</li>
      <li><strong>A client holding a master admin user is refused.</strong> The row shows <em>“(protected)”</em>, and if the delete is attempted anyway you get <em>“Cannot delete: client has a master admin user. Clear the super_admin flag in client_users first.”</em></li>
      </ul>
      <p>Before you delete anything real, take a copy: <strong>Platform → System health → Backup</strong> has a <strong>Per-tenant export</strong> that hands you that one client’s data as a <code>.sql</code> file, and <em>“Download backup now”</em> takes the whole database. Do it in that order — export first, delete second.</p>
      <p>The Overview row also carries an <strong>Inactive</strong> tag and a <strong>Master</strong> tag, so check which row you are on before you press anything — the tick boxes across that row are the paid feature flags and are saved separately with <strong>Save flag changes</strong>; they have nothing to do with the Delete button beside them.</p>'],

];
