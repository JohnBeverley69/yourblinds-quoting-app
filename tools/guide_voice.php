<?php
declare(strict_types=1);

/**
 * Record a help guide's voice-over with a natural ElevenLabs voice — one
 * MP3 per script line into /media/guides/<slug>/ — so help/guide.php plays
 * it instead of the browser's robotic text-to-speech.
 *
 *   php tools/guide_voice.php --guide=calendar-booking --voice="Alice"
 *   php tools/guide_voice.php --guide=… --voice=… --force    re-record all
 *
 * Skips lines already recorded and removes clips no line uses any more.
 * CLI only; key from ELEVENLABS_API_KEY / .env (never commit it).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__);
require __DIR__ . '/_elevenlabs.php';
require $root . '/_partials/guide_clips.php';

$opts  = getopt('', ['guide:', 'voice:', 'force', 'model:']);
$slug  = (string) ($opts['guide'] ?? '');
$voice = (string) ($opts['voice'] ?? '');
$model = (string) ($opts['model'] ?? 'eleven_multilingual_v2');

if (!preg_match('/^[a-z0-9-]+$/', $slug) || !is_file("$root/help/guides/$slug.php") || $voice === '') {
    fwrite(STDERR, "Usage: php tools/guide_voice.php --guide=<slug> --voice=\"Alice\" [--force]\n");
    exit(1);
}

$g = require "$root/help/guides/$slug.php";
$key     = el_key($root);
$voiceId = el_voice_id($key, $voice);
$out     = "$root/media/guides/$slug";

$keep = [];
foreach ($g['script'] as $n => $row) {
    $vo     = trim((string) ($row[2] ?? ''));
    if ($vo === '') continue;
    $name   = guide_clip_name($vo);
    $keep[] = $name;
    echo 'Line ' . ($n + 1) . ': ' . ($row[1] ?? '') . "\n";
    if (is_file("$out/$name") && !isset($opts['force'])) { echo "  (already there)\n"; continue; }
    el_tts($key, $voiceId, $model, $vo, "$out/$name");
}

foreach (glob("$out/*.mp3") ?: [] as $old) {
    if (!in_array(basename($old), $keep, true)) {
        unlink($old);
        echo 'Removed stale ' . basename($old) . "\n";
    }
}
echo "\nDone. Commit media/guides/$slug/*.mp3 to publish.\n";
