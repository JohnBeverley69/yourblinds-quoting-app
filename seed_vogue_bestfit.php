<?php
declare(strict_types=1);

/**
 * Seed: Louvolite "Best Fit" truck tables for Vogue vertical blinds.
 *
 * Digitises Louvolite's Best Fit charts (89mm louvres, CD Rom code S280201) so
 * the build engine can pick the truck count + carrier size by best fit instead
 * of a fitter reading the "horrendous table" by hand. Five tables, one per
 * operation:
 *   vogue_ow_cord      One Way Cord Operated
 *   vogue_ow_wand      One Way Mono Command (wand)
 *   vogue_split_cord   Split Draw Cord Operated
 *   vogue_split_1wand  Split Draw Mono Command, 1 Wand
 *   vogue_split_2wand  Split Draw Mono Command, 2 Wands
 *
 * Each row's key is "count|size" (truck count | carrier size mm) and its value
 * is the maximum blind width that combination covers. The engine's BESTFIT()
 * returns the row with the smallest value >= the blind width (least oversail):
 *   Trucks     = BESTFIT("vogue_ow_cord", Width, 1)
 *   Truck_Size = BESTFIT("vogue_ow_cord", Width, 2)
 * e.g. a 1800mm blind → 24 x 87mm, exactly as Louvolite's own example.
 *
 * NB: values are transcribed from the Best Fit PDF and should be spot-checked
 * against the chart before production use; the Allowances screen edits them.
 * A few extraction-damaged high-count rows (very wide blinds) are omitted —
 * an uncovered width makes BESTFIT flag rather than guess.
 *
 * Idempotent (upsert into allowance_rows). Run via web: /seed_vogue_bestfit.php.
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

$SIZES = [83, 85, 87, 89];

// count => [w83, w85, w87, w89] (max width covered, 89mm louvres).
$T = [];

$T['vogue_ow_cord'] = [
    2=>[178,180,182,184], 3=>[248,252,256,260], 4=>[317,323,330,336], 5=>[387,395,404,412],
    6=>[456,467,478,488], 7=>[526,539,553,564], 8=>[595,610,627,640], 9=>[665,682,701,716],
    10=>[734,754,775,792], 11=>[804,825,849,868], 12=>[873,897,923,944], 13=>[943,969,997,1020],
    14=>[1012,1040,1071,1096], 15=>[1082,1112,1145,1172], 16=>[1151,1184,1219,1248], 17=>[1221,1256,1294,1324],
    18=>[1290,1327,1368,1400], 19=>[1360,1399,1442,1476], 20=>[1429,1471,1516,1552], 21=>[1499,1542,1590,1628],
    22=>[1568,1614,1664,1704], 23=>[1638,1686,1738,1780], 24=>[1707,1757,1812,1856], 25=>[1777,1829,1886,1932],
    26=>[1846,1901,1960,2008], 27=>[1916,1973,2035,2084], 28=>[1985,2044,2109,2160], 29=>[2055,2116,2183,2236],
    30=>[2124,2188,2257,2312], 31=>[2194,2259,2331,2388], 32=>[2263,2331,2405,2464], 33=>[2333,2403,2479,2540],
    34=>[2402,2474,2553,2616], 35=>[2472,2546,2627,2692], 36=>[2541,2618,2701,2768],
];

$T['vogue_ow_wand'] = [
    2=>[148,150,152,154], 3=>[218,222,226,230], 4=>[287,293,300,306], 5=>[357,365,374,382],
    6=>[426,437,448,458], 7=>[496,509,523,534], 8=>[565,580,597,610], 9=>[635,652,671,686],
    10=>[704,724,745,762], 11=>[774,795,819,838], 12=>[843,867,893,914], 13=>[913,939,967,990],
    14=>[982,1010,1041,1066], 15=>[1052,1082,1115,1142], 16=>[1121,1154,1189,1218], 17=>[1191,1226,1264,1294],
    18=>[1260,1297,1338,1370], 19=>[1330,1369,1412,1446], 20=>[1399,1441,1486,1522], 21=>[1469,1512,1560,1598],
    22=>[1538,1584,1634,1674], 23=>[1608,1656,1708,1750], 24=>[1677,1727,1782,1826], 25=>[1747,1799,1856,1902],
    26=>[1816,1871,1930,1978], 27=>[1886,1943,2005,2054], 28=>[1955,2014,2079,2130], 29=>[2025,2086,2153,2206],
    30=>[2094,2158,2227,2282], 31=>[2164,2229,2301,2358], 32=>[2233,2301,2375,2434], 33=>[2303,2373,2449,2510],
    34=>[2372,2444,2523,2586],
];

$T['vogue_split_cord'] = [
    4=>[304,308,312,316], 6=>[445,453,461,469], 8=>[585,597,609,621], 10=>[726,742,758,774],
    12=>[867,887,907,927], 14=>[1007,1031,1055,1079], 16=>[1148,1176,1204,1232], 18=>[1288,1320,1352,1384],
    20=>[1429,1465,1501,1537], 22=>[1570,1610,1650,1690], 24=>[1710,1754,1798,1842], 26=>[1851,1899,1947,1995],
    28=>[1991,2043,2095,2147], 30=>[2132,2188,2244,2300], 32=>[2273,2333,2393,2453], 34=>[2413,2477,2541,2605],
    36=>[2554,2622,2690,2758], 38=>[2694,2766,2838,2910], 40=>[2835,2911,2987,3063], 42=>[2976,3056,3136,3216],
    44=>[3116,3200,3284,3368], 46=>[3257,3345,3433,3521], 50=>[3538,3634,3730,3826], 52=>[3679,3779,3879,3979],
    54=>[3819,3923,4027,4131], 56=>[3960,4068,4176,4284], 58=>[4100,4212,4324,4436], 60=>[4241,4357,4473,4589],
    62=>[4382,4502,4622,4742], 64=>[4522,4646,4770,4894], 66=>[4663,4791,4919,5047], 68=>[4803,4935,5067,5199],
    70=>[4944,5080,5216,5352],
];

$T['vogue_split_1wand'] = [
    2=>[158,160,162,164], 4=>[297,304,310,316], 6=>[437,447,458,469], 8=>[576,591,607,621],
    10=>[715,735,755,774], 12=>[855,878,903,927], 14=>[994,1022,1051,1079], 16=>[1133,1166,1199,1232],
    18=>[1272,1309,1348,1384], 20=>[1412,1453,1496,1537], 22=>[1551,1597,1644,1690], 24=>[1690,1740,1792,1842],
    26=>[1830,1884,1940,1995], 28=>[1969,2028,2089,2147], 30=>[2108,2172,2237,2300], 32=>[2248,2315,2385,2453],
    34=>[2387,2459,2533,2605], 36=>[2526,2603,2681,2758], 38=>[2665,2746,2830,2910], 40=>[2805,2890,2978,3063],
    42=>[2944,3034,3126,3216], 44=>[3083,3177,3274,3368], 46=>[3223,3321,3422,3521], 48=>[3362,3465,3571,3673],
];

$T['vogue_split_2wand'] = [
    2=>[128,130,132,134], 4=>[267,274,280,286], 6=>[407,417,428,439], 8=>[546,561,577,591],
    10=>[685,705,725,744], 12=>[825,848,873,897], 14=>[964,992,1021,1049], 16=>[1103,1136,1169,1202],
    18=>[1242,1279,1318,1354], 20=>[1382,1423,1466,1507], 22=>[1521,1567,1614,1660], 24=>[1660,1710,1762,1812],
    26=>[1800,1854,1910,1965], 28=>[1939,1998,2059,2117], 30=>[2078,2142,2207,2270], 32=>[2218,2285,2355,2423],
    34=>[2357,2429,2503,2575], 36=>[2496,2573,2651,2728], 38=>[2635,2716,2800,2880], 40=>[2775,2860,2948,3033],
    42=>[2914,3004,3096,3186], 44=>[3053,3147,3244,3338], 46=>[3193,3291,3392,3491], 48=>[3332,3435,3541,3643],
    50=>[3471,3578,3689,3796], 52=>[3611,3722,3837,3949], 54=>[3750,3866,3985,4104], 56=>[3889,4009,4133,4254],
    58=>[4028,4153,4282,4406], 60=>[4168,4297,4430,4559], 62=>[4307,4440,4578,4712], 64=>[4446,4584,4726,4864],
    66=>[4586,4728,4874,5017], 68=>[4725,4871,5023,5169], 70=>[4864,5015,5171,5322], 72=>[5004,5159,5319,5475],
];

// Self-check: within each row the four sizes must strictly increase.
$warnings = [];
foreach ($T as $name => $rows) {
    foreach ($rows as $count => $vals) {
        for ($i = 1; $i < 4; $i++) {
            if ($vals[$i] <= $vals[$i - 1]) {
                $warnings[] = "{$name} count {$count}: not increasing (" . implode(',', $vals) . ")";
            }
        }
    }
}

$upsert = $pdo->prepare(
    "INSERT INTO allowance_rows (table_name, key_norm, keys_display, value, seq)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE keys_display = VALUES(keys_display), value = VALUES(value), seq = VALUES(seq)"
);

$total = 0;
foreach ($T as $name => $rows) {
    $seq = 0;
    foreach ($rows as $count => $vals) {
        foreach ($SIZES as $si => $size) {
            $key = $count . '|' . $size;               // "count|size"
            $disp = $count . ' · ' . $size;            // "24 · 87"
            $upsert->execute([$name, $key, $disp, (float) $vals[$si], $seq++]);
            $total++;
        }
    }
    echo sprintf("  %-20s %d rows\n", $name, count($rows) * count($SIZES));
}

echo "\nSeeded {$total} best-fit rows across " . count($T) . " tables.\n";
if ($warnings) {
    echo "\n⚠ MONOTONICITY WARNINGS (check these against the chart):\n";
    foreach ($warnings as $w) echo "  - {$w}\n";
} else {
    echo "Self-check: all rows increase across truck sizes. \n";
}
echo "\nReference in a build rule with BESTFIT(\"<table>\", Width, 1) for the count,\n";
echo "or 2 for the carrier size. Verify a few widths against the Best Fit chart.\n";
