<?php
declare(strict_types=1);

/**
 * QR codes for shop-floor labels.
 *
 * Renders an inline SVG (not an image file) so it prints crisply at any size
 * and needs no writable directory, no GD, and no JavaScript timing games with
 * window.print().
 *
 * THE PAYLOAD IS DELIBERATELY TINY. A blind's code is 8 digits — see
 * qr_blind_code() — which fits a **version 1** symbol (21x21 modules, 29 with
 * the mandatory quiet zone). That matters because the vertical work ticket is
 * only 21mm tall, so the symbol can never be wider than ~19mm however much we
 * want it to be: the only way to get fatter, more forgiving modules is to carry
 * less data. A URL would push it to version 2+ and shrink every module by ~13%.
 * The bench scanners are USB wedges (they type the code straight into the
 * station page), so the payload never needed to be a web address.
 *
 * Sizing on the two label stocks (both print black on WHITE face stock — the
 * "kraft" in the die drawing is the backing liner, not what you print on):
 *   - roller  102x76mm thermal  — masses of room, ~20mm, easy.
 *   - vertical 90x21mm inkjet   — ~17mm ceiling (the label height caps it),
 *     giving ~0.59mm modules at ECC level Q. Inkjet on uncoated stock still
 *     spreads ink, so modules fatten — hence ECC Q and as few as possible.
 * The real floor is whatever /factory/qr-test-sheet.php proves scannable; if a
 * smaller size reads cleanly, take it and give the width back to the text.
 */

require_once __DIR__ . '/../_lib/qrcode/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Mode;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * The encoded matrix for a payload.
 *
 * BEWARE: QRCode::getQRMatrix() takes NO ARGUMENTS — it encodes the segments
 * you have already added. Passing the payload to it does nothing (PHP silently
 * drops the extra argument) and you get a valid-looking QR of an EMPTY string,
 * identical for every input. That shipped once and cost a day of blaming the
 * scanner. Data must be added first, exactly as QRCode::render() does it.
 */
function qr_matrix(string $payload, string $ecc = 'Q')
{
    static $cache = [];
    $key = $payload . '|' . $ecc;
    if (isset($cache[$key])) return $cache[$key];

    $levels = ['L' => EccLevel::L, 'M' => EccLevel::M, 'Q' => EccLevel::Q, 'H' => EccLevel::H];
    $qr = new QRCode(new QROptions([
        'version'       => QRCode::VERSION_AUTO,
        'eccLevel'      => $levels[strtoupper($ecc)] ?? EccLevel::Q,
        'outputType'    => QROutputInterface::CUSTOM,
        'quietzoneSize' => 4,        // mandated by the spec — scanners need it
    ]));

    // Same mode detection render() uses: numeric for our digits, byte for a URL.
    $added = false;
    foreach (Mode::INTERFACES as $iface) {
        if ($iface::validateString($payload)) { $qr->addSegment(new $iface($payload)); $added = true; break; }
    }
    if (!$added) $qr->addByteSegment($payload);

    return $cache[$key] = $qr->getQRMatrix();
}

/**
 * A blind's scannable code: 8 digits, zero-padded — 6 for the order line and
 * 2 for the unit ("2 of 3"). Fixed length keeps every label on a version 1
 * symbol, so module size never changes under us. Stable from the moment the
 * order is placed, which is what lets labels print before the blind is
 * released to the floor.
 */
function qr_blind_code(int $quoteItemId, int $unitNo): string
{
    return str_pad((string) $quoteItemId, 6, '0', STR_PAD_LEFT)
         . str_pad((string) min(99, max(1, $unitNo)), 2, '0', STR_PAD_LEFT);
}

/** Split a scanned code back into [quote_item_id, unit_no], or null if it isn't one. */
function qr_parse_code(string $scanned): ?array
{
    $s = trim($scanned);
    // Tolerate a URL wrapper in case a code ever ships as one (phone camera).
    if (preg_match('~(\d{8})\s*$~', $s, $m)) $s = $m[1];
    if (!preg_match('/^\d{8}$/', $s)) return null;
    $itemId = (int) substr($s, 0, 6);
    $unitNo = (int) substr($s, 6, 2);
    if ($itemId <= 0 || $unitNo <= 0) return null;
    return [$itemId, $unitNo];
}

/**
 * Inline SVG for a QR code, sized in millimetres.
 *
 * $ecc: 'L'|'M'|'Q'|'H' — Q (25% recovery) is the default because these labels
 * live in a workshop and get dust, grease and creases on them.
 */
function qr_svg(string $payload, float $mm, string $ecc = 'Q'): string
{
    $matrix = qr_matrix($payload, $ecc);
    $n      = $matrix->getSize();          // includes the quiet zone

    // One <rect> per dark module, on a viewBox of n units — the SVG scales to
    // whatever physical size we ask for, so print DPI does the rasterising.
    $rects = '';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($matrix->check($x, $y)) {
                $rects .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1"/>';
            }
        }
    }
    $size = rtrim(rtrim(number_format($mm, 2, '.', ''), '0'), '.');

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '"'
         . ' width="' . $size . 'mm" height="' . $size . 'mm"'
         . ' shape-rendering="crispEdges" role="img" aria-label="code ' . htmlspecialchars($payload, ENT_QUOTES) . '">'
         . '<rect width="' . $n . '" height="' . $n . '" fill="#fff"/>'
         . '<g fill="#000">' . $rects . '</g></svg>';
}

/** Module count of the symbol (incl. quiet zone) — for reporting mm-per-module. */
function qr_module_count(string $payload, string $ecc = 'Q'): int
{
    return qr_matrix($payload, $ecc)->getSize();
}
