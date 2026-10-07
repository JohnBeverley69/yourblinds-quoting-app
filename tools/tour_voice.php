<?php
declare(strict_types=1);

/**
 * Generate the "How it works" tour narration with a natural AI voice
 * (ElevenLabs), one MP3 per scene, into /media/tour/.
 *
 * CLI only (the /tools folder is blocked from the web). Needs
 * ELEVENLABS_API_KEY in the environment or in the project's .env — never
 * commit the key, this repo is public.
 *
 *   php tools/tour_voice.php --samples           step 1 read by each British voice
 *                                                 on the account → media/tour/samples/
 *   php tools/tour_voice.php --voice="Alice"     every scene's clip in that voice
 *                                                 (name or voice id); skips clips that
 *                                                 already exist, removes stale ones
 *   php tools/tour_voice.php --voice=… --force   regenerate every clip
 *
 * Clips are named by a hash of the scene's spoken text (hw_clip_name), so
 * editing a scene's title/body just needs this re-running for that scene.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__);
$SCENES = require $root . '/_partials/how_it_works_scenes.php';

$opts  = getopt('', ['samples', 'voice:', 'force', 'model:']);
$model = (string) ($opts['model'] ?? 'eleven_multilingual_v2');
$out   = $root . '/media/tour';

require __DIR__ . '/_elevenlabs.php';
$key = el_key($root);

// ── --samples: step 1 in every British voice ──────────────────────────
if (isset($opts['samples'])) {
    $british = array_filter(el_voices($key), static function ($v) {
        $labels = strtolower(json_encode($v['labels'] ?? []));
        return str_contains($labels, 'british') || str_contains($labels, 'english (uk)');
    });
    if (!$british) { fwrite(STDERR, "No British voices found on this account.\n"); exit(1); }
    $text = hw_say($SCENES[0]);
    foreach (array_slice($british, 0, 8) as $v) {
        $desc = trim(implode(', ', array_filter([
            $v['labels']['gender'] ?? '', $v['labels']['age'] ?? '', $v['labels']['description'] ?? $v['labels']['descriptive'] ?? '',
        ])));
        echo "{$v['name']} ({$v['voice_id']}) $desc\n";
        $safe = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $v['name']);
        el_tts($key, (string) $v['voice_id'], $model, $text, "$out/samples/$safe.mp3");
    }
    echo "\nListen in media/tour/samples/, then run with --voice=\"Name\".\n";
    exit(0);
}

// ── --voice: the whole tour ───────────────────────────────────────────
$voice = (string) ($opts['voice'] ?? '');
if ($voice === '') { fwrite(STDERR, "Pass --samples or --voice=\"Name\".\n"); exit(1); }
$voiceId = el_voice_id($key, $voice);

$keep = [];
foreach ($SCENES as $n => $s) {
    $name   = hw_clip_name($s);
    $keep[] = $name;
    $file   = "$out/$name";
    echo 'Step ' . ($n + 1) . ': ' . $s['title'] . "\n";
    if (is_file($file) && !isset($opts['force'])) { echo "  (already there)\n"; continue; }
    el_tts($key, $voiceId, $model, hw_say($s), $file);
}

// Remove clips no scene uses any more (an edited scene's old narration).
foreach (glob("$out/*.mp3") ?: [] as $old) {
    if (!in_array(basename($old), $keep, true)) {
        unlink($old);
        echo 'Removed stale ' . basename($old) . "\n";
    }
}
echo "\nDone. Commit media/tour/*.mp3 (not the samples) to publish.\n";
