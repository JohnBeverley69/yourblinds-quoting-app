<?php
declare(strict_types=1);

/**
 * Recorded narration for the help guides (help/guide.php).
 *
 * Each script line's voice-over text is hashed; a clip with that name in
 * /media/guides/<slug>/ is the natural-voice recording of it, made by
 * tools/guide_voice.php. Edit a line and its old clip stops matching — that
 * line falls back to the browser voice until the generator is re-run.
 */

/** File name of a voice-over line's clip. */
function guide_clip_name(string $voiceOver): string
{
    return substr(sha1(trim($voiceOver)), 0, 16) . '.mp3';
}

/** Public URL of a line's clip in guide $slug, or '' if not recorded. */
function guide_clip_url(string $slug, string $voiceOver): string
{
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return '';
    $name = guide_clip_name($voiceOver);
    return is_file(__DIR__ . '/../media/guides/' . $slug . '/' . $name)
        ? '/media/guides/' . $slug . '/' . $name : '';
}
