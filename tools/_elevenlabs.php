<?php
declare(strict_types=1);

/**
 * Shared ElevenLabs text-to-speech helpers for the narration generators
 * (tools/tour_voice.php, tools/guide_voice.php). CLI only.
 *
 * Needs ELEVENLABS_API_KEY in the environment or the project's .env —
 * never commit the key, this repo is public.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

/** The API key from the environment, else the project .env; exits if missing. */
function el_key(string $root): string
{
    $key = (string) getenv('ELEVENLABS_API_KEY');
    if ($key === '' && is_file($root . '/.env')) {
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*ELEVENLABS_API_KEY\s*=\s*"?([^"\s]+)"?\s*$/', $line, $m)) { $key = $m[1]; }
        }
    }
    if ($key === '') { fwrite(STDERR, "ELEVENLABS_API_KEY is not set (environment or .env).\n"); exit(1); }
    return $key;
}

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
        CURLOPT_TIMEOUT        => 180,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) { $body = curl_error($ch); }
    curl_close($ch);
    return [$code, (string) $body];
}

/** Speak $text in voice $voiceId and save the MP3 to $file; exits on failure. */
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

/** The account's voices (premade + any added), as the API returns them. */
function el_voices(string $key): array
{
    [$code, $body] = el_call($key, 'GET', '/v1/voices');
    if ($code !== 200) { fwrite(STDERR, "Couldn't list voices (HTTP $code).\n"); exit(1); }
    return json_decode($body, true)['voices'] ?? [];
}

/** A voice id from a name ("Alice") or an id; exits if there's no such voice. */
function el_voice_id(string $key, string $voice): string
{
    if (preg_match('/^[A-Za-z0-9]{20}$/', $voice)) return $voice;
    foreach (el_voices($key) as $v) {
        if (strcasecmp((string) $v['name'], $voice) === 0 || stripos((string) $v['name'], $voice . ' ') === 0) {
            return (string) $v['voice_id'];
        }
    }
    fwrite(STDERR, "No voice called \"$voice\" on this account.\n");
    exit(1);
}
