<?php
declare(strict_types=1);

/**
 * "How the factory works" — a short 1-2-3 shown on an EMPTY factory screen.
 * A new factory has no orders, so Incoming orders, the Pipeline and the Scan
 * log were blank pages that meant nothing until the first order arrived
 * (Tyler's critique, 2026-10-13: "I basically won't understand anything that's
 * happening until I make an order"). Once there's work, the card goes away.
 *
 *   factory_howto_card('incoming' | 'pipeline' | 'scanlog')
 */

if (!function_exists('factory_howto_card')) {
    function factory_howto_card(string $here): string
    {
        $office = !function_exists('factory_user_is_office') || factory_user_is_office();
        $steps = [
            ['An order comes in',
             'A trade customer places an order with your products in it &mdash; or your own shop accepts a quote for blinds you make. '
             . 'It lands on <a href="/factory/incoming-orders.php">Incoming orders</a> and the <a href="/factory/pipeline.php">Pipeline</a> by itself: nothing to re-key.'],
            ['The office processes it',
             'On the Pipeline, <strong>Process order</strong> sends the blinds you make to the <a href="/factory/floor.php">Floor</a> '
             . '(split into your <a href="/factory/production-areas.php">production areas</a>) and orders anything you buy in from its supplier.'],
            ['The floor makes it',
             'Each area prints its <a href="/factory/worksheets.php">worksheets</a> and labels. A WiFi scanner at each bench scans the barcode as a blind '
             . 'moves on, and every scan shows in the <a href="/factory/scan-log.php">Scan log</a>. When everything is made it\'s Ready to dispatch.'],
        ];
        $intro = [
            'incoming' => 'No orders yet. Here&rsquo;s how they get here and what happens next:',
            'pipeline' => 'Nothing on the board yet. Every open order shows here as a card, from New to done:',
            'scanlog'  => 'No scans yet. Scans come from the WiFi scanners at your benches:',
        ][$here] ?? 'How the factory works:';
        $html = '<div class="fx-howto"><p class="fx-howto-intro">' . $intro . '</p><ol class="fx-howto-steps">';
        foreach ($steps as $i => [$title, $body]) {
            $html .= '<li><span class="n">' . ($i + 1) . '</span><div><strong>' . $title . '</strong><br>' . $body . '</div></li>';
        }
        $html .= '</ol>';
        if ($office) {
            $html .= '<p class="fx-howto-foot">Setting up? Start with <a href="/factory/production-areas.php">Production areas</a> '
                   . '(your benches and their scanners), then try it with a test quote from the <strong>Shop</strong> side. '
                   . 'More in <a href="/help/index.php">Help &amp; guide</a>.</p>';
        }
        $html .= '</div>';
        return $html . '<style>
.fx-howto { background: var(--bg-subtle, #f8fafc); border: 1px dashed var(--border, #e5e7eb); border-radius: 12px; padding: 1.1rem 1.25rem; color: var(--text-body, #374151); }
.fx-howto-intro { margin: 0 0 .75rem; font-weight: 600; }
.fx-howto-steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .9rem; }
.fx-howto-steps li { display: flex; gap: .6rem; font-size: .875rem; line-height: 1.45; min-width: 0; }
.fx-howto-steps .n { flex: none; width: 1.6rem; height: 1.6rem; border-radius: 50%; background: #f59e0b; color: #1c2530; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
.fx-howto-foot { margin: .9rem 0 0; font-size: .8125rem; color: var(--text-muted, #6b7280); }
</style>';
    }
}
