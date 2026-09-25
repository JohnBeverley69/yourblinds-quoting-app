<?php
declare(strict_types=1);

/**
 * Shared helpers for the per-client Terms & Conditions / Privacy Policy.
 *
 *  - legal_default_terms()  / legal_default_privacy()  — suggested starting
 *    templates (plain text; rendered with white-space: pre-line). Used to
 *    pre-fill the Settings textareas for a tenant who hasn't configured them.
 *  - legal_render_tokens()  — replaces {{tokens}} with values from a quote
 *    row (which carries trade_* company fields + end_customer_* + quote_number).
 *  - legal_token_list()     — the tokens to advertise in the Settings help.
 *
 * Tokens supported: {{company_name}} {{company_address}} {{company_email}}
 * {{company_phone}} {{customer_name}} {{quote_number}} {{date}}.
 *
 * NB: these are SUGGESTED templates, not legal advice — tenants should have
 * them reviewed before use.
 */

if (!function_exists('legal_token_list')) {
    function legal_token_list(): array
    {
        return [
            '{{company_name}}'    => "your company name",
            '{{company_address}}' => "your company address",
            '{{company_email}}'   => "your company email",
            '{{company_phone}}'   => "your company phone",
            '{{customer_name}}'   => "the customer's name on the quote",
            '{{quote_number}}'    => "the quote reference",
            '{{date}}'            => "today's date",
        ];
    }
}

if (!function_exists('legal_render_tokens')) {
    /**
     * Replace {{tokens}} in $text using fields from a quote row. Unknown
     * tokens are left untouched. $quote uses the trade_* aliases that
     * pdf.php / public.php already select.
     */
    function legal_render_tokens(string $text, array $quote): string
    {
        $addrParts = array_filter([
            (string) ($quote['trade_addr1']    ?? ''),
            (string) ($quote['trade_addr2']    ?? ''),
            (string) ($quote['trade_town']     ?? ''),
            (string) ($quote['trade_county']   ?? ''),
            (string) ($quote['trade_postcode'] ?? ''),
        ], static fn ($p) => trim($p) !== '');

        return strtr($text, [
            '{{company_name}}'    => (string) ($quote['trade_company_name'] ?? ''),
            '{{company_address}}' => implode(', ', $addrParts),
            '{{company_email}}'   => (string) ($quote['trade_email'] ?? ''),
            '{{company_phone}}'   => (string) ($quote['trade_phone'] ?? ''),
            '{{customer_name}}'   => (string) ($quote['end_customer_name'] ?? ''),
            '{{quote_number}}'    => (string) ($quote['quote_number'] ?? ''),
            '{{quote_link}}'      => (string) ($quote['quote_link'] ?? ''),
            '{{date}}'            => date('j F Y'),
        ]);
    }
}

if (!function_exists('legal_default_terms')) {
    function legal_default_terms(): string
    {
        return <<<'TXT'
TERMS & CONDITIONS OF SALE — {{company_name}}

These terms apply to your order with {{company_name}} ("we", "us", "our"). "You" means {{customer_name}}, the customer named on the quotation. Please read them alongside your quotation {{quote_number}} dated {{date}}.

1. OUR QUOTATION
Quotations are valid for 30 days and include supply and fitting unless stated otherwise. Prices are based on the survey carried out and the specification agreed at the time.

2. SURVEY & MEASUREMENTS
We measure your windows, doors or openings and assess access, obstructions (such as sockets, handles, pipework and tiling) and any power requirements at the time of survey. Where you ask us to work from sizes you have supplied yourself, you are responsible for their accuracy.

Changes to the opening after measuring. Measurements are taken on the basis of the opening as it exists on the date of survey, and your made-to-measure products are manufactured to those measurements. If the opening is later altered in any way that affects its size — including tiling, plastering or rendering, fitting or replacing windows, frames, sills or window boards, or other building or decorating work — the original measurements may no longer be accurate and your products may no longer fit. We cannot be held responsible for products that do not fit as a result. Please tell us before any such work is carried out — ideally have it completed before we measure. A re-measure may be required and may be chargeable, and any remake or alteration needed because the opening changed after measuring will be charged at the applicable rate.

3. ORDERS, DEPOSIT & PAYMENT
A 50% deposit is required to commence your order, with the balance due on completion of installation. Risk in the goods passes on delivery/fitting, but the goods remain our property until paid for in full. Where you decline or delay fitting after manufacture, the full balance becomes payable immediately and storage charges may apply.

4. MADE-TO-MEASURE GOODS & CANCELLATION
Your products are made to measure (bespoke) and are therefore exempt from the cancellation rights in the Consumer Contracts Regulations 2013. You may amend or cancel your order within 48 hours of paying your deposit, provided manufacture has not begun. After that, as the goods are made specifically to your specification, the deposit is non-refundable and orders cannot be cancelled. This does not affect your statutory rights in respect of faulty or misdescribed goods (clause 11).

5. COLOUR, FINISH & BATCH VARIATION
Natural variation in colour, shade, grain and texture can occur between sample swatches, on-screen or printed images, and the finished product — and between separate production batches. We match colours and finishes as closely as possible, but an exact match cannot be guaranteed, particularly for items ordered or re-ordered at different times. Such minor variation is normal, is not a fault, and is not grounds for rejection.

6. LEAD TIMES & DELIVERY
Any delivery, installation or completion date is a best estimate only and time is not of the essence. Made-to-measure goods are exempt from standard 30-day delivery requirements. We are not liable for delays outside our reasonable control.

7. INSTALLATION & ACCESS
You agree to provide safe and clear access to the working area, removing or protecting furniture, breakable items, plants and pets beforehand, and to provide any necessary parking. We take care during installation but are not liable for incidental damage where reasonable care has been taken, nor for redecoration or making good unless agreed in writing. Missed or cancelled appointments require 24 hours' notice; return visits may incur a charge.

8. CHILD SAFETY
By law, all internal blinds with cords or chains must be supplied and fitted with appropriate child-safety devices in accordance with BS EN 13120. We fit these as standard. If you ask us not to fit a required safety device we may be unable to complete installation, and the full cost of the order remains payable.

9. MOTORISED PRODUCTS
Most motorised blinds we supply are battery-powered. Where a mains-powered motor is specified, the power supply requirements will be identified at survey; any necessary electrical installation must be carried out by a qualified electrician and, unless agreed otherwise in writing, is arranged and paid for by you.

10. CONDENSATION, DAMP & VENTILATION
Timber and timber-based products — particularly shutters — can be affected by moisture, condensation and damp. You are responsible for ensuring rooms are adequately ventilated and that walls and reveals are sound and dry. Movement, warping, swelling or finish deterioration caused by condensation, damp or inadequate ventilation — and any effect of these conditions on fixings — is not covered by the guarantee. For bathrooms, kitchens and other high-moisture areas we will recommend a suitable moisture-resistant material; fitting a timber product in such areas against our advice is at your own risk.

11. GUARANTEE
We guarantee your products against failure of materials, stitching, track parts and headrail/operating mechanisms for 12 months from the date of fitting. The guarantee excludes fair wear and tear, accidental, malicious or third-party damage, misuse or modification, fabric fading (fabrics are tested to BS EN ISO 105 B02 but fading is inevitable over time and is not a fault), and the conditions in clause 10. Defects must be reported to us in writing within a reasonable time; we will repair or replace at no charge where the guarantee applies.

12. FIT-ONLY / CUSTOMER-SUPPLIED GOODS
Where you engage us to fit blinds, shutters or components supplied by you or a third party ("fit-only"), our responsibility is limited to the standard of the fitting workmanship only. We accept no liability for the products themselves — their measurement, manufacture, quality, suitability, operation or fit — including any item that proves to be the wrong size, faulty or unsuitable. If supplied items cannot be fitted safely or correctly, the fitting charge remains payable.

13. FAULTY GOODS & YOUR STATUTORY RIGHTS
Nothing in these terms affects your rights under the Consumer Rights Act 2015 in respect of goods that are faulty, not as described or not fit for purpose.

14. OUR LIABILITY
We do not exclude or limit liability for death or personal injury caused by our negligence, for fraud, or for anything that cannot lawfully be excluded. Otherwise we are not liable for losses that were not foreseeable, that were not caused by our breach, or for business or trade losses.

15. COMPLAINTS
If something isn't right, please contact us first and we'll do our best to put it right. Please report any concern to us in writing as soon as possible so we can look into it and resolve it promptly.

16. DATA PROTECTION
We process your personal data only to fulfil your order and as required by law, in accordance with UK GDPR. Please see our Privacy Policy for details.

17. GENERAL
These terms are governed by the law of England and Wales and subject to the exclusive jurisdiction of its courts.

Contact: {{company_name}}, {{company_address}} — {{company_email}} — {{company_phone}}.
TXT;
    }
}

if (!function_exists('legal_default_trade_terms')) {
    /** B2B / trade terms — used on trade quotes (raised for an account). */
    function legal_default_trade_terms(): string
    {
        return <<<'TXT'
TERMS & CONDITIONS OF SALE (TRADE) — {{company_name}}

These terms apply to the sale of goods by {{company_name}} ("we", "us", "our") to a business customer ("you"). This is a business-to-business contract; you confirm you are not dealing as a consumer, and the Consumer Rights Act 2015 and Consumer Contracts Regulations 2013 do not apply.

1. APPLICATION & FORMATION OF CONTRACT
A quotation is not an offer capable of acceptance and does not create a contract. A binding contract is formed only when you place an ORDER and we accept it (by our written acknowledgement or by beginning to fulfil it); your order is an offer to buy on these terms. The "Contract" means these terms together with our quotation and your order as accepted by us, and is the entire agreement between us, to the exclusion of any other terms you seek to impose. By ordering you confirm you have not relied on any statement not set out in the Contract.

2. PRICE
The price ("Price") is that in our quotation current at the date of your order, or as otherwise agreed in writing. If our costs increase before delivery due to factors beyond our control (materials, labour, exchange rates, duties, delivery) we may increase the Price after notifying you. Any discounts are at our discretion. The Price excludes packaging, delivery, VAT and other taxes, which you also pay.

3. ORDERS, CANCELLATION & ALTERATION
The quotation is valid for 30 days unless withdrawn earlier. Once we have accepted your order it may be cancelled or altered only with our written agreement. Where goods are made to your specification, once manufacture has begun the order cannot be cancelled and any deposit is non-refundable, save for your rights in respect of faulty or misdescribed goods.

4. PAYMENT
Unless otherwise agreed in writing, you must pay each invoice in full within 20 days of the end of the month of the invoice date (or per your agreed credit terms). Time for payment is of the essence. Payment is due even if delivery has not taken place or title has not passed. All amounts are payable in full without set-off, deduction or counterclaim except as required by law. If you pay late we may suspend further deliveries and charge interest and compensation under the Late Payment of Commercial Debts (Interest) Act 1998 (statutory interest at 8% above the Bank of England base rate, plus the fixed statutory compensation and our reasonable costs of recovering the debt).

5. DIRECTOR'S PERSONAL GUARANTEE
In consideration of us supplying on credit, each director of the customer who accepts these terms on the customer's behalf personally guarantees, as principal obligor, payment of all sums and performance of all liabilities owed by the customer to {{company_name}} on any account — whether under this Contract, under any order (current or future), or otherwise however arising. This guarantee is unconditional and irrevocable and continues until all such sums and liabilities are discharged in full; on the customer's default the director(s) shall on demand indemnify us against all losses, costs and expenses arising. (A separately signed guarantee may be required when a credit account is opened.)

6. DELIVERY
We deliver to the address in your order or as agreed; if none is specified, you collect from our premises. Delivery may take place between 8am and 8pm and must be accepted during those hours. If you do not take delivery we may store the goods and charge the associated costs, arrange redelivery at your expense, or after 10 business days resell or dispose of them and charge any shortfall. Delivery dates are approximate and not of the essence; we are not liable for delays beyond our reasonable control or caused by your failure to give instructions. Goods may be delivered in instalments, invoiced separately.

7. INSPECTION & ACCEPTANCE
You must inspect the goods on delivery or collection; damage or shortage must be reported in writing within 14 days. Returned goods are accepted only if defective and subject to our inspection; defective goods may be repaired, replaced or refunded at our option. We are not liable where you fail to notify us, continue using the goods after notice, or where the defect arises from improper use, storage or maintenance, or from fair wear and tear, misuse or negligence. You bear the risk and cost of returns. Acceptance is deemed on inspection or 14 days after delivery, whichever is earlier.

8. RISK & TITLE
Risk passes to you on delivery. Title passes only when we have received payment in full and cleared funds for the goods and all other amounts you owe. Until title passes you must hold the goods as our bailee, store them separately and in good condition, and keep them insured for their full price; if not resold or incorporated into other products we may require their return and enter your premises to recover them.

9. TERMINATION
We may terminate the Contract with immediate effect if you commit a material breach, become subject to insolvency proceedings, or enter voluntary liquidation or a similar arrangement.

10. LIABILITY
Nothing limits our liability for death or personal injury caused by our negligence, for fraud, or for anything that cannot lawfully be limited. Otherwise all terms implied by law are excluded to the fullest extent permitted, except the implied terms as to title under section 12 of the Sale of Goods Act 1979. Our liability for non-delivery is limited to the cost of replacement goods, and our total liability does not exceed the Price payable. We are not liable for indirect or consequential loss, loss of profit, business interruption, or failures beyond our reasonable control.

11. DATA PROTECTION
Where we process personal data of your staff on your behalf, you are the controller and we are the processor within the meaning of the UK GDPR and the Data Protection Act 2018. We process it only as reasonably required to supply the goods or as you agree, keep it no longer than necessary, do not use it for our own purposes, and disclose it only to our people or advisers on a need-to-know basis under equivalent obligations, or as required by law. See our Privacy Policy for details.

12. EVENTS BEYOND REASONABLE CONTROL
Neither party is liable for failure or delay caused by events beyond its reasonable control, including industrial action, civil unrest, fire, flood, storm, earthquake, terrorism, war, epidemic or government action.

13. GENERAL
No waiver of a breach is a waiver of any later breach. If any provision is unlawful or unenforceable it is severed and the remainder continues. A person who is not a party has no rights under the Contracts (Rights of Third Parties) Act 1999 to enforce these terms (this does not affect our rights under the Director's Personal Guarantee). The Contract is governed by the law of England and Wales, whose courts have exclusive jurisdiction over any dispute (including non-contractual disputes) arising under it.

Contact: {{company_name}}, {{company_address}} — {{company_email}} — {{company_phone}}.
TXT;
    }
}

if (!function_exists('legal_default_privacy')) {
    function legal_default_privacy(): string
    {
        return <<<'TXT'
PRIVACY POLICY — {{company_name}}

{{company_name}} ("we", "us", "our") is committed to protecting your privacy. This notice explains what personal information we collect when you ask us to quote for, supply or fit blinds, shutters or related products, how we use it, and your rights. We are the data controller for this information.

1. THE LAW WE FOLLOW
We handle your personal data in line with UK GDPR and the Data Protection Act 2018 (as amended by the Data (Use and Access) Act 2025), regulated by the Information Commissioner's Office (ICO).

2. THE INFORMATION WE COLLECT
- Contact & identity: your name, address, email and phone number.
- Order details: your window/door measurements, property details, the products and options you choose, and appointment dates.
- Payment information: the details needed to take your deposit and balance (card payments are handled securely by our payment provider).
- Correspondence: notes of your enquiries, our quotations, and messages between us.
- Marketing preferences, where you have given them.

3. HOW WE COLLECT IT
Directly from you — when you enquire, accept a quotation, book a survey, or contact us — and occasionally from someone who refers you to us, or via our website.

4. WHY WE USE IT, AND OUR LAWFUL BASIS
- Preparing your quotation, ordering, supplying and fitting your products — performance of our contract with you.
- Taking payment and keeping accounting/tax records — legal obligation / contract.
- Contacting you about your order, survey or installation — contract / legitimate interests.
- Handling guarantees, aftercare and complaints — contract / legitimate interests.
- Sending you marketing (only if you have opted in) — your consent.

5. WHO WE SHARE IT WITH
We share your information only as needed to fulfil your order — with our manufacturers and suppliers, fitters, payment and delivery providers, our accountants and professional advisers, and the IT/software providers who run our quoting and business systems. We may disclose information where required by law or to a regulator. We never sell your personal data.

6. MARKETING
We will only send you marketing if you have asked us to, and you can opt out at any time — just tell us, or use the unsubscribe link in our emails.

7. KEEPING YOUR INFORMATION
We keep your information for as long as needed to provide and support your order, and to meet our legal and tax obligations — financial records are typically kept for 6 years. After that we securely delete or anonymise it.

8. WHERE IT IS HELD
Your information is stored within the UK or, where a provider operates elsewhere, with appropriate safeguards in place to protect it to UK standards.

9. KEEPING IT SECURE
We use appropriate technical and organisational measures to protect your information against loss, misuse or unauthorised access.

10. YOUR RIGHTS
You have the right to access the information we hold about you, to have it corrected or erased, to restrict or object to its use, to data portability, and to withdraw consent at any time. To exercise any of these, contact us using the details below.

11. HOW TO COMPLAIN
If you are unhappy with how we have handled your information, please contact us first — we will look into it and respond. You also have the right to complain to the Information Commissioner's Office (ICO), Wycliffe House, Water Lane, Wilmslow, Cheshire SK9 5AF — ico.org.uk — 0303 123 1113.

12. CHANGES TO THIS NOTICE
We may update this notice from time to time; the current version is always available on request.

13. CONTACT US
{{company_name}}, {{company_address}} — {{company_email}} — {{company_phone}}.
TXT;
    }
}

if (!function_exists('legal_default_accept_email')) {
    /**
     * Default thank-you email body sent to the customer when they accept a
     * quote. Plain text. Tokens: {{customer_name}} {{company_name}}
     * {{quote_number}} {{quote_link}}.
     */
    function legal_default_accept_email(): string
    {
        return <<<'TXT'
Hello {{customer_name}},

Thank you for accepting your quote {{quote_number}} — we really appreciate your business and are delighted to have you as a customer.

We'll be in touch shortly to arrange the next steps. If you have any questions in the meantime, just reply to this email.

You can view your quote any time here:
{{quote_link}}

With thanks,
{{company_name}}
TXT;
    }

    /**
     * Effective legal text for a client: their saved value, or the standard
     * template when they have NEVER configured it (NULL). An explicitly-saved
     * empty string means "disabled" and is returned as-is. This makes the
     * default T&Cs / Privacy Policy / acceptance email apply to every client
     * out of the box, without anyone having to open Settings and Save first.
     */
    function legal_effective_terms(?string $stored): string
    {
        return $stored === null ? legal_default_terms() : $stored;
    }
    function legal_effective_trade_terms(?string $stored): string
    {
        return $stored === null ? legal_default_trade_terms() : $stored;
    }
    function legal_effective_privacy(?string $stored): string
    {
        return $stored === null ? legal_default_privacy() : $stored;
    }
    function legal_effective_accept_email(?string $stored): string
    {
        return $stored === null ? legal_default_accept_email() : $stored;
    }
}

/*
 * Signed public links to /legal/view.php. The page is public (no login), so a
 * bare ?c=<id> let anyone step through ids and harvest every account's address,
 * email and phone. The link now carries k = HMAC(client id); without a valid k
 * the page still renders the terms but only with the company NAME (so links on
 * quotes sent before this change keep working).
 */
if (!function_exists('legal_link_key')) {
    function legal_link_key(int $clientId): string
    {
        require_once __DIR__ . '/app_settings.php';
        static $secret = null;
        if ($secret === null) {
            $secret = (string) app_setting_get('legal_link_secret', '');
            if ($secret === '') {
                $secret = bin2hex(random_bytes(32));
                if (!app_setting_set('legal_link_secret', $secret)) {
                    // No app_settings table — fall back to a server secret so
                    // links are at least stable; APP_KEY-less installs get a
                    // key that changes per request (links then show name only).
                    $secret = (string) (env('APP_ENCRYPTION_KEY') ?: $secret);
                }
            }
        }
        return substr(hash_hmac('sha256', 'legal:' . $clientId, $secret), 0, 20);
    }

    function legal_view_url(string $base, int $clientId, string $doc): string
    {
        return rtrim($base, '/') . '/legal/view.php?c=' . $clientId
             . '&doc=' . rawurlencode($doc) . '&k=' . legal_link_key($clientId);
    }
}
