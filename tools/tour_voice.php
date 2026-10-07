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

// ── API key: environment first, then .env ─────────────────────────────
$key = (string) getenv('ELEVENLABS_API_KEY');
if ($key === '' && is_file($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^\s*ELEVENLABS_API_KEY\s*=\s*"?([^"\s]+)"?\s*$/', $line, $m)) { $key = $m[1]; }
    }
}
if ($key === '') { fwrite(STDERR, "ELEVENLABS_API_KEY is not set (environment or .env).\n"); exit(1); }

/** Call the ElevenLabs API. Returns [httpCode, body]. */
function el_call(string $key, string $method, string $path, ?array $json = null): array
{
    $ch = curl_init('https://api.elevenlabs.io' . $path);
    $headers = ['xi-api-key: ' . $key, 'Accept: */*'];
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) { $body = curl_error($ch); }
    curl_close($ch);
    return [$code, (string) $body];
}

/** Speak $text in voice $voiceId and save the MP3 to $file. */
function el_tts(string $key, string $voiceId, string $model, string $text, string $file): void
{
    [$code, $body] = el_call($key, 'POST',
        '/v1/text-to-speech/' . rawurlencode($voiceId) . '?output_format=mp3_44100_128', [
            'text'           => $text,
            'model_id'       => $model,
            'voice_settings' => ['stability' => 0.45, 'similarity_boost' => 0.8, 'style' => 0.3, 'use_speaker_boost' => true],
        ]);
    if ($code !== 200) {
        fwrite(STDERR, "  ! HTTP $code: " . substr($body, 0, 300) . "\n");
        exit(1);
    }
    if (!is_dir(dirname($file))) { mkdir(dirname($file), 0775, true); }
    file_put_contents($file, $body);
    echo '  saved ' . basename($file) . ' (' . round(strlen($body) / 1024) . " KB)\n";
}

/** The account's voices as [name => id] (premade + any added). */
function el_voices(string $key): array
{
    [$code, $body] = el_call($key, 'GET', '/v1/voices');
    if ($code !== 200) { fwrite(STDERR, "Couldn't list voices (HTTP $code).\n"); exit(1); }
    $all = [];
    foreach ((json_decode($body, true)['voices'] ?? []) as $v) {
        $all[] = $v;
    }
    return $all;
}

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
$voiceId = $voice;
if (!preg_match('/^[A-Za-z0-9]{20}$/', $voice)) {          // a name, not an id
    $voiceId = '';
    foreach (el_voices($key) as $v) {
        if (strcasecmp((string) $v['name'], $voice) === 0 || stripos((string) $v['name'], $voice . ' ') === 0) {
            $voiceId = (string) $v['voice_id'];
            break;
        }
    }
    if ($voiceId === '') { fwrite(STDERR, "No voice called \"$voice\" on this account.\n"); exit(1); }
}

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
