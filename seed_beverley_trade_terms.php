<?php
declare(strict_types=1);

/**
 * Seed Beverley's approved TRADE Terms & Conditions into client_settings for the
 * factory tenant (client 3). This is the reviewed revision (contract re-based on
 * the ORDER, guarantee broadened, UK GDPR/DPA 2018, statutory late-payment
 * interest, third-party-rights clause), with Beverley's own legal entity details.
 * Other tenants keep the tokenised default (legal_default_trade_terms()).
 *
 * Idempotent (overwrites the stored value). Run after migrate_trade_terms.php.
 * Web-runnable: /seed_beverley_trade_terms.php (super-admin).
 */

require_once __DIR__ . '/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/auth/middleware.php';
    requireSuperAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}
ini_set('display_errors', '1');
error_reporting(E_ALL);

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MASTER = function_exists('factory_client_id') ? factory_client_id() : 3;

$terms = <<<'TXT'
TERMS & CONDITIONS OF SALE (TRADE) — Beverley Blinds Ltd

These terms apply to the sale of goods by Beverley Blinds Ltd, a company registered in England and Wales under number 6058421, whose registered office is at Unit 2, Brindley Road, Exhall CV7 9EP ("we", "us", "our"), to a business customer ("you"). This is a business-to-business contract; you confirm you are not dealing as a consumer, and the Consumer Rights Act 2015 and Consumer Contracts Regulations 2013 do not apply.

1. APPLICATION & FORMATION OF CONTRACT
A quotation is not an offer capable of acceptance and does not create a contract. A binding contract is formed only when you place an ORDER and we accept it (by our written acknowledgement or by beginning to fulfil it); your order is an offer to buy on these terms. The "Contract" means these terms together with our quotation and your order as accepted by us, and is the entire agreement between us, to the exclusion of any other terms you seek to impose. By ordering you confirm you have not relied on any statement not set out in the Contract.

2. PRICE
The price ("Price") is that in our quotation current at the date of your order, or as otherwise agreed in writing. If our costs increase before delivery due to factors beyond our control (materials, labour, exchange rates, duties, delivery) we may increase the Price after notifying you. Any discounts are at our discretion. The Price excludes packaging, delivery, VAT and other taxes, which you also pay.

3. ORDERS, CANCELLATION & ALTERATION
The quotation is valid for 30 days unless withdrawn earlier. Once we have accepted your order it may be cancelled or altered only with our written agreement. Where goods are made to your specification, once manufacture has begun the order cannot be cancelled and any deposit is non-refundable, save for your rights in respect of faulty or misdescribed goods.

4. PAYMENT
Unless otherwise agreed in writing, you must pay each invoice in full within 20 days of the end of the month of the invoice date (or per your agreed credit terms). Time for payment is of the essence. Payment is due even if delivery has not taken place or title has not passed. All amounts are payable in full without set-off, deduction or counterclaim except as required by law. If you pay late we may suspend further deliveries and charge interest and compensation under the Late Payment of Commercial Debts (Interest) Act 1998 (statutory interest at 8% above the Bank of England base rate, plus the fixed statutory compensation and our reasonable costs of recovering the debt).

5. DIRECTOR'S PERSONAL GUARANTEE
In consideration of us supplying on credit, each director of the customer who accepts these terms on the customer's behalf personally guarantees, as principal obligor, payment of all sums and performance of all liabilities owed by the customer to Beverley Blinds Ltd on any account — whether under this Contract, under any order (current or future), or otherwise however arising. This guarantee is unconditional and irrevocable and continues until all such sums and liabilities are discharged in full; on the customer's default the director(s) shall on demand indemnify us against all losses, costs and expenses arising.

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
Where we process personal data of your staff on your behalf, you are the controller and we are the processor within the meaning of the UK GDPR and the Data Protection Act 2018. We process it only as reasonably required to supply the goods or as you agree, keep it no longer than necessary, do not use it for our own purposes, and disclose it only to our people or advisers on a need-to-know basis under equivalent obligations, or as required by law. See our Privacy Policy for details. Data queries: support@beverleyblinds.co.uk.

12. EVENTS BEYOND REASONABLE CONTROL
Neither party is liable for failure or delay caused by events beyond its reasonable control, including industrial action, civil unrest, fire, flood, storm, earthquake, terrorism, war, epidemic or government action.

13. GENERAL
No waiver of a breach is a waiver of any later breach. If any provision is unlawful or unenforceable it is severed and the remainder continues. A person who is not a party has no rights under the Contracts (Rights of Third Parties) Act 1999 to enforce these terms (this does not affect our rights under the Director's Personal Guarantee). The Contract is governed by the law of England and Wales, whose courts have exclusive jurisdiction over any dispute (including non-contractual disputes) arising under it.

Contact: Beverley Blinds Ltd, Unit 2, Brindley Road, Exhall CV7 9EP — john@beverleyblinds.co.uk — 07884495673.
TXT;

// Ensure a settings row exists, then store the trade terms.
$pdo->prepare('INSERT INTO client_settings (client_id) VALUES (?) ON DUPLICATE KEY UPDATE client_id = client_id')
    ->execute([$MASTER]);
$pdo->prepare('UPDATE client_settings SET trade_terms_conditions = ? WHERE client_id = ?')
    ->execute([$terms, $MASTER]);

echo "Stored Beverley's trade T&Cs on client {$MASTER} (" . strlen($terms) . " chars).\n";
echo "Trade quotes now link to /legal/view.php?c={$MASTER}&doc=trade.\n";
