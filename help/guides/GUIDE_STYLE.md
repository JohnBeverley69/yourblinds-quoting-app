# Help guides — how to write one (v2 player)

The pattern every guide follows. Reference implementation:
`help/guides/products-import-price-tables.php` ("Building price tables").

## What John asked for (Oct 2026)
- **Much more animation** — the picture should *do* what the voice says, in time with it.
- **Slower and more informative** — people absorb little of what they see, so one
  idea per chapter, short sentences, explain *why* as well as *what*, and
  breathing space between points.
- Narrated by **Alice** (ElevenLabs). Recorded clips live in
  `media/guides/<slug>/` (made with `tools/guide_voice.php`).

## The file
`help/guides/<slug>.php` returns an array. v2 adds `'v' => 2`.

| key | notes |
|---|---|
| `aud`, `section`, `title`, `eyebrow`, `blurb`, `open` | as before (`eyebrow` drives help index grouping — keep it) |
| `lede` | friendly 3–5 sentence intro: what this is, why it matters, how to get there |
| `v` | `2` |
| `css` | the guide's own styles, every selector starting `.gd ` |
| `demo` | the walkthrough: `demo-shell` → `app` (mock sidebar) → `.stage#gdStage` containing **one scene per chapter** |
| `body` | the written steps (HTML). Keep it complete and accurate — this is the reference people print. |
| `script` | `[ [chapterNo, 'Caption title', 'Voice-over', stepIndex], … ]` |

PHP above the `return` may build repeated markup (grids, lists) with closures — see the reference.

## Scenes
- `<div class="sc" data-scene="N" data-len="SECS">` — shown only while the line with step `N` plays.
  Scene `0` is the **poster** shown before Play (no `data-len`, no animation classes needed).
- `data-len` = the seconds the line is expected to last: **characters of the voice-over ÷ 13.6**
  (Alice at guide speed). The player stretches all timings to the real clip length.
- Give `.gd .sc{ position:relative; min-height:360px; }` (420px on phones) so the stage doesn't jump.
- Inside a scene, tag elements with an animation class and a start time `style="--d:4.5s"`:

| class | effect |
|---|---|
| `a-fade` / `a-rise` | fade in (small / larger lift) |
| `a-pop` | pop in (scale) — chips, toasts, badges |
| `a-drop` | drop in from above — cards landing |
| `a-fly` | slide in from the left |
| `a-type` | typed in left→right (`--ts` steps, `--tt` duration) — text in boxes |
| `a-sel` | turns into the selected/accent state — chosen radio/pill/header |
| `a-press` | a button press bump |
| `a-out` | fades away (`a-mid`: in at `--d`, out at `--d2`) — swap old→new text by stacking an `a-out` and an `a-fade` in one grid cell |
| `a-grow` / `a-wide` | bar grows up / sideways |
| `a-draw` | SVG stroke draws (`pathLength="100"`) |
| `a-stamp` | stamped on (PAID-style) |
| `a-ring` | pulsing focus ring ×3 — "look here" |
| `a-move` | moves from `--fx/--fy` to `--tx/--ty` (left/top, % or rem of the scene) over `--md` — wrap the shared pointer (`.gd-ptr` svg, see reference) to show a click |

- **One animation class per element** (two classes fight; the later CSS rule wins). Need two
  effects? Nest elements.
- Spread the beats across the whole line: something should change every 2–4 seconds,
  timed to when Alice says it (≈ 13.6 characters per second).
- End states are what show before Play and after Stop — make the finished scene a good still.

## Writing the voice-over
- 10–16 chapters. Each line 220–420 characters, ~20–30 seconds.
- Short sentences. Plain words. Say numbers as words ("twenty five percent").
- One idea per chapter; say what it is, why it matters, then what to click.
- Use the real labels exactly as on screen (copy them from the PHP), but speak naturally around them.
- Never "diary" — it's the **calendar**. Don't promise features that don't exist; check the code.
- Caption titles (2nd column): short, no full stop.

## Accuracy
Every label, button and message must match the real screen. Open the screen's PHP (the `open`
link points at it) and copy wording from it. If the old guide says something the code no longer
does, follow the code. Keep `body` (written steps) complete — that's where the detail lives.

## Checking your work (local)
- `php -l help/guides/<slug>.php`
- Preview: `php -S 127.0.0.1:<port> -t <worktree>` then
  `/tools/guide_preview.php?g=<slug>` (no login; loopback only; needs a `.env`, copy one from `W:\YourBlinds-qa\.env`).
- Screenshot a scene finished: `…&scene=N`, or mid-timeline: `…&scene=N&t=6.5`
  (headless Chrome `--screenshot`). Check every scene for overlaps and overflow at 1000px and ~420px wide.

## Recording
`php tools/guide_voice.php --guide=<slug> --voice="Alice"` (speed 0.92 by default). Clips are named
by a hash of each line, so an edited line simply needs re-running; stale clips are removed.
