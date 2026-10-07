<?php
declare(strict_types=1);

/**
 * Public "How it works" tour — the retail journey, played out.
 *
 * A play-through walkthrough (Play / Pause / Prev / Next, chapter dots and
 * optional spoken narration) that follows one job through YourBlinds from
 * the first phone call to getting paid. Each scene pairs an animated mock of
 * the real screen with a short, upbeat explanation.
 *
 * Linked from welcome.php. No login required, purely presentational — the
 * mocks are static HTML/CSS, nothing here reads tenant data.
 *
 * Keep the claims TRUE to the app: every scene describes something the app
 * really does today (checked 2026-10-07). If a feature changes, change its
 * scene. Tier tags (Silver/Gold) must match _partials/billing_plans.php.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth/middleware.php';
require_once __DIR__ . '/_partials/app_settings.php';

$loggedIn      = is_logged_in();
$signupsClosed = app_setting_on('signups_paused');

$SCENES     = require __DIR__ . '/_partials/how_it_works_scenes.php';
$sceneCount = count($SCENES);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How it works &middot; YourBlinds</title>
    <meta name="description" content="See how YourBlinds runs a blinds business from the first phone call to getting paid — booking, calendar, route, measuring, quotes customers sign on their phone, supplier orders, fitting and invoices.">
    <link rel="stylesheet" href="<?= asset('/app.css') ?>">
    <style>
        .hw-wrap { max-width: 1120px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
        .hw-top { display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .hw-brand { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.01em;
            color: var(--brand, #1f3b5b); text-decoration: none; }
        .hw-brand .accent { color: var(--brand-accent, #2563eb); }
        [data-theme="dark"] .hw-brand { color: var(--text-primary); }
        .hw-top-links { display: flex; gap: 0.9rem; align-items: center; flex-wrap: wrap; }
        .hw-top-links a { font-size: 0.95rem; text-decoration: none; color: var(--text-secondary); }
        .hw-top-links a:hover { color: var(--link); }

        .hw-hero { text-align: center; padding: 0.5rem 0 1.5rem; }
        .hw-hero h1 { font-size: clamp(1.8rem, 5vw, 2.6rem); line-height: 1.1; margin: 0 0 0.6rem;
            text-wrap: balance; color: var(--text-primary); }
        .hw-hero p { font-size: 1.1rem; color: var(--text-secondary); max-width: 60ch; margin: 0 auto; }

        .hw-cta { display: inline-block; border-radius: 10px; padding: 0.7rem 1.4rem; font-weight: 700;
            font-size: 1rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
        .hw-cta.primary { background: var(--link, #2563eb); color: #fff; }
        .hw-cta.primary:hover { filter: brightness(1.06); }
        .hw-cta.ghost { background: transparent; color: var(--text-primary); border-color: var(--border-strong); }
        .hw-cta.ghost:hover { background: var(--bg-subtle); }

        /* ── Player ─────────────────────────────────────────── */
        .hw-player { --hw-accent: var(--link, #2563eb); --hw-ok: #16a34a; --hw-fit: #0f9f6e;
            --hw-card: var(--bg-card, #fff); --hw-sub: var(--bg-subtle, #f6f8fb); --hw-line: var(--border, #e2e8ef);
            --hw-ink: var(--text-primary, #1c2733); --hw-soft: var(--text-secondary, #5b6b7b); --hw-faint: var(--text-faint, #8a9aa8);
            background: var(--hw-card); border: 1px solid var(--hw-line); border-radius: 18px;
            box-shadow: var(--shadow-sm, 0 2px 10px rgba(0,0,0,.06)); overflow: hidden; position: relative; }
        .hw-stage { position: relative; }
        .hw-scene[hidden] { display: none; }
        .hw-scene { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); gap: 1.75rem;
            align-items: center; padding: 1.75rem; }
        @media (max-width: 860px) { .hw-scene { grid-template-columns: minmax(0, 1fr); gap: 1.1rem; padding: 1rem; } }

        .hw-text .hw-step { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--hw-accent); }
        .hw-text h2 { font-size: clamp(1.35rem, 3vw, 1.85rem); line-height: 1.15; margin: 0.35rem 0 0.7rem;
            color: var(--hw-ink); text-wrap: balance; }
        .hw-text p { margin: 0; color: var(--hw-soft); font-size: 1.02rem; line-height: 1.55; }
        .hw-tier { display: inline-block; margin-top: 0.85rem; font-size: 0.75rem; font-weight: 700;
            border-radius: 999px; padding: 0.15rem 0.6rem; background: var(--hw-sub); color: var(--hw-soft);
            border: 1px solid var(--hw-line); }

        /* Controls */
        .hw-controls { display: flex; align-items: center; gap: 0.6rem; padding: 0.8rem 1.25rem;
            border-top: 1px solid var(--hw-line); background: var(--hw-sub); flex-wrap: wrap; }
        .hw-ctl { border: 1px solid var(--hw-line); background: var(--hw-card); color: var(--hw-ink);
            border-radius: 8px; padding: 0.4rem 0.75rem; font-weight: 700; font-size: 0.9rem; cursor: pointer; }
        .hw-ctl:hover { border-color: var(--hw-accent); }
        .hw-ctl.main { background: var(--hw-accent); color: #fff; border-color: var(--hw-accent); min-width: 6.5rem; }
        .hw-dots { display: flex; gap: 0.3rem; flex: 1 1 auto; overflow-x: auto; scrollbar-width: none; padding: 2px; }
        .hw-dots::-webkit-scrollbar { display: none; }
        .hw-dot { flex: 0 0 auto; border: 1px solid var(--hw-line); background: var(--hw-card); color: var(--hw-soft);
            border-radius: 999px; padding: 0.25rem 0.6rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .hw-dot.seen { color: var(--hw-ink); }
        .hw-dot.on { background: var(--hw-accent); border-color: var(--hw-accent); color: #fff; }
        .hw-progress { height: 3px; background: var(--hw-line); }
        .hw-progress i { display: block; height: 100%; width: 0; background: var(--hw-accent); transition: width .4s ease; }

        /* Start overlay */
        .hw-start { position: absolute; inset: 0; z-index: 20; display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 0.8rem; background: color-mix(in srgb, var(--hw-card) 72%, transparent);
            backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); border: 0; width: 100%; cursor: pointer; color: var(--hw-ink); }
        .hw-start[hidden] { display: none; }
        .hw-start .big { width: 84px; height: 84px; border-radius: 50%; background: var(--hw-accent); color: #fff;
            display: grid; place-items: center; font-size: 2rem; padding-left: 6px; box-shadow: 0 10px 30px rgba(37,99,235,.35);
            animation: hwBeat 1.8s ease-in-out infinite; }
        .hw-start b { font-size: 1.2rem; }
        .hw-start small { color: var(--hw-soft); font-size: 0.9rem; }
        @keyframes hwBeat { 0%,100% { transform: scale(1) } 50% { transform: scale(1.07) } }

        /* ── Mock screens (shared) ─────────────────────────── */
        .hw-screen { position: relative; min-height: 330px; aspect-ratio: 16 / 10.5; font-size: 13px; color: var(--hw-ink);
            display: flex; align-items: stretch; justify-content: center; }
        @media (max-width: 560px) { .hw-screen { font-size: 11px; min-height: 290px; aspect-ratio: auto; } }
        .hw-win { flex: 1 1 auto; display: flex; flex-direction: column; border: 1px solid var(--hw-line); border-radius: 12px;
            background: var(--hw-card); overflow: hidden; box-shadow: 0 12px 30px rgba(15,23,42,.10); min-width: 0; }
        .hw-bar { display: flex; align-items: center; gap: 5px; padding: 7px 10px; background: var(--hw-sub);
            border-bottom: 1px solid var(--hw-line); font-size: 0.85em; color: var(--hw-soft); }
        .hw-bar i { width: 9px; height: 9px; border-radius: 50%; background: var(--hw-line); }
        .hw-bar span { margin-left: 8px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hw-body { position: relative; flex: 1 1 auto; padding: 12px; min-height: 0; }
        .hw-btn { display: inline-block; background: var(--hw-accent); color: #fff; border-radius: 7px; padding: 6px 12px;
            font-weight: 700; }
        .hw-btn.ok { background: var(--hw-ok); display: block; text-align: center; margin-top: 6px; }
        .hw-toast { position: absolute; left: 50%; bottom: 12px; transform: translateX(-50%); white-space: nowrap;
            background: #0f172a; color: #fff; border-radius: 999px; padding: 6px 14px; font-weight: 600; font-size: .92em; z-index: 3; }
        .hw-in { flex: 1 1 auto; border: 1px solid var(--hw-line); border-radius: 6px; padding: 4px 8px; min-height: 2em;
            display: flex; align-items: center; gap: 8px; background: var(--hw-card); min-width: 0; }
        .hw-in.sm { margin: 6px 0 4px; }
        .hw-mini { font-style: normal; font-size: .8em; font-weight: 700; color: var(--hw-accent); margin-left: auto;
            border: 1px solid var(--hw-accent); border-radius: 5px; padding: 1px 6px; white-space: nowrap; }
        .hw-tick { color: var(--hw-soft); }
        .hw-appt { position: absolute; left: 4px; right: 4px; border-radius: 6px; padding: 4px 6px; font-size: .85em; line-height: 1.25;
            background: color-mix(in srgb, var(--hw-accent) 12%, var(--hw-card)); border-left: 3px solid var(--hw-accent);
            color: var(--hw-ink); overflow: hidden; display: block; font-style: normal; }
        .hw-appt b { display: block; font-size: .85em; color: var(--hw-accent); }
        .hw-appt small { display: block; color: var(--hw-soft); }
        .hw-appt.fit { background: color-mix(in srgb, var(--hw-fit) 13%, var(--hw-card)); border-left-color: var(--hw-fit); }
        .hw-appt.fit b { color: var(--hw-fit); }
        .hw-phone { position: relative; width: 190px; max-width: 46%; border: 6px solid #1e293b; border-radius: 24px;
            background: var(--hw-card); padding: 18px 9px 10px; display: flex; flex-direction: column; gap: 6px;
            box-shadow: 0 14px 34px rgba(15,23,42,.22); flex: 0 0 auto; }
        .hw-notch { position: absolute; top: 5px; left: 50%; transform: translateX(-50%); width: 46px; height: 6px;
            border-radius: 6px; background: #1e293b; }
        .hw-ph-title { font-weight: 800; font-size: .95em; }
        .hw-ph-note { font-size: .82em; color: var(--hw-soft); }

        /* 1 · Booking form */
        .hw-form { display: flex; flex-direction: column; gap: 7px; }
        .hw-row { display: flex; align-items: center; gap: 8px; }
        .hw-row > label { flex: 0 0 82px; color: var(--hw-soft); font-size: .88em; }
        .hw-chips { display: flex; gap: 5px; flex-wrap: wrap; }
        .hw-chip { border: 1px solid var(--hw-line); border-radius: 999px; padding: 2px 8px; font-size: .88em; }
        .hw-seg { display: inline-flex; border: 1px solid var(--hw-line); border-radius: 999px; overflow: hidden; font-size: .82em; margin-right: 4px; }
        .hw-seg i { font-style: normal; padding: 2px 8px; color: var(--hw-soft); }
        .hw-seg i.on { background: var(--hw-sub); color: var(--hw-ink); font-weight: 700; }
        .hw-call { align-self: flex-start; background: color-mix(in srgb, var(--hw-ok) 14%, var(--hw-card)); color: var(--hw-ok);
            border-radius: 999px; padding: 3px 10px; font-weight: 700; }
        .hw-call span { display: inline-block; }

        /* 2 · Day view */
        .hw-day { display: grid; grid-template-columns: 40px repeat(3, minmax(0, 1fr)); gap: 6px; }
        .hw-times { display: flex; flex-direction: column; justify-content: space-between; padding-top: 22px;
            font-size: .78em; color: var(--hw-faint); }
        .hw-col { position: relative; background: var(--hw-sub); border-radius: 8px; min-height: 230px; }
        .hw-col h5 { margin: 0; padding: 5px 6px; font-size: .82em; color: var(--hw-soft); border-bottom: 1px solid var(--hw-line); }
        .hw-new { box-shadow: 0 0 0 2px color-mix(in srgb, var(--hw-accent) 40%, transparent); }
        .hw-float { position: absolute; right: 10px; bottom: 10px; width: 150px; z-index: 2; }
        .hw-float .hw-appt { left: 0; right: 0; }
        @media (max-width: 560px) { .hw-float { width: 118px; } .hw-day { grid-template-columns: 30px repeat(3, minmax(0,1fr)); } }

        /* 3 · Map */
        .hw-map { padding: 0; overflow: hidden; background: color-mix(in srgb, #86efac 14%, var(--hw-card)); }
        .hw-map svg { position: absolute; inset: 0; width: 100%; height: 100%; }
        .hw-roads path { stroke: color-mix(in srgb, var(--hw-ink) 12%, transparent); stroke-width: 7; fill: none; stroke-linecap: round; }
        .hw-route { stroke: var(--hw-accent); stroke-width: 4.5; fill: none; stroke-linejoin: round; stroke-linecap: round; }
        .hw-map circle { fill: var(--hw-accent); stroke: #fff; stroke-width: 3; }
        .hw-map text { fill: #fff; font-size: 12px; font-weight: 800; text-anchor: middle; }
        .hw-map g { transform-box: fill-box; transform-origin: center; }
        .hw-map .hw-home { font-size: 20px; }
        .hw-stops { position: absolute; left: 10px; top: 10px; background: var(--hw-card); border: 1px solid var(--hw-line);
            border-radius: 9px; padding: 7px 10px; display: flex; flex-direction: column; gap: 3px; font-size: .88em;
            box-shadow: 0 6px 16px rgba(15,23,42,.10); }
        .hw-stops i { display: inline-grid; place-items: center; width: 15px; height: 15px; border-radius: 50%;
            background: var(--hw-accent); color: #fff; font-style: normal; font-size: .75em; font-weight: 800; margin-right: 3px; }
        .hw-go { position: absolute; right: 12px; top: 12px; }
        .hw-go span { display: inline-block; background: var(--hw-ok); color: #fff; font-weight: 800; border-radius: 8px; padding: 7px 13px; }

        /* 4 · Tablet quote */
        .hw-tab { flex: 1 1 auto; border: 10px solid #1e293b; border-radius: 22px; background: var(--hw-card); display: flex;
            box-shadow: 0 14px 34px rgba(15,23,42,.22); min-width: 0; }
        .hw-quote { display: flex; flex-direction: column; gap: 7px; }
        .hw-q-head { display: flex; justify-content: space-between; gap: 8px; align-items: center; flex-wrap: wrap; }
        .hw-off { font-size: .8em; font-weight: 700; color: #b45309; background: #fef3c7; border-radius: 999px; padding: 2px 8px; }
        .hw-line { display: grid; grid-template-columns: 62px minmax(0,1fr) auto; gap: 8px; align-items: start;
            border: 1px solid var(--hw-line); border-radius: 8px; padding: 6px 8px; }
        .hw-room { font-weight: 800; }
        .hw-what em { display: block; font-style: normal; color: var(--hw-soft); font-size: .9em; margin-top: 1px; }
        .hw-sw { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }
        .hw-opts { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 3px; }
        .hw-opt { font-size: .78em; background: var(--hw-sub); border: 1px solid var(--hw-line); border-radius: 5px; padding: 0 5px; }
        .hw-price { font-size: 1.05em; }
        .hw-total { margin-top: auto; text-align: right; font-size: 1.15em; color: var(--hw-soft); }
        .hw-total b { font-size: 1.5em; color: var(--hw-ink); margin-left: 6px; }

        /* 4b · Offline */
        .hw-offline { display: flex; flex-direction: column; gap: 7px; }
        .hw-net { position: relative; height: 26px; }
        .hw-net span { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            border-radius: 7px; font-weight: 800; font-size: .9em; }
        .hw-net-off { background: #f97316; color: #fff; }
        .hw-net-on { background: var(--hw-ok); color: #fff; }
        .hw-queue { border: 1px dashed #f59e0b; border-radius: 8px; padding: 6px 9px; color: var(--hw-soft); }
        .hw-queue b { color: #b45309; }
        .hw-sync { display: flex; flex-direction: column; gap: 3px; color: var(--hw-ok); font-weight: 700; }

        /* 5 · Send + sign */
        .hw-split { flex: 1 1 auto; display: flex; gap: 14px; align-items: center; justify-content: center; min-width: 0; }
        .hw-send { flex: 1 1 auto; max-width: 250px; align-self: center; }
        .hw-send .hw-body { display: flex; flex-direction: column; gap: 7px; }
        .hw-sbtn { border: 1px solid var(--hw-line); border-radius: 7px; padding: 6px 10px; font-weight: 700; }
        .hw-sbtn.wa { background: #25d366; border-color: #25d366; color: #fff; }
        .hw-fly { color: var(--hw-ok); font-weight: 700; font-size: .9em; }
        .hw-ph-card { display: flex; flex-direction: column; gap: 2px; border: 1px solid var(--hw-line); border-radius: 9px; padding: 7px; font-size: .92em; }
        .hw-dep { color: #b45309; font-size: .88em; margin-top: 2px; }
        .hw-yes { background: var(--hw-ok); color: #fff; border-radius: 8px; padding: 6px; text-align: center; font-weight: 800; font-size: .9em; }
        @media (max-width: 560px) { .hw-send { max-width: 46%; } .hw-phone { width: 52%; max-width: 52%; } }

        /* 6 · Order */
        .hw-order { display: flex; flex-direction: column; gap: 12px; }
        .hw-job { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid var(--hw-line);
            border-radius: 9px; padding: 9px 11px; flex-wrap: wrap; }
        .hw-pills { position: relative; display: inline-block; width: 92px; height: 24px; }
        .hw-pill { position: absolute; inset: 0; display: grid; place-items: center; border-radius: 999px; font-weight: 800; font-size: .85em; }
        .hw-pill.q { background: var(--hw-sub); color: var(--hw-soft); border: 1px solid var(--hw-line); }
        .hw-pill.acc { background: #dcfce7; color: #15803d; }
        .hw-pill.ord { background: color-mix(in srgb, var(--hw-accent) 16%, var(--hw-card)); color: var(--hw-accent); }
        .hw-auto { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; color: var(--hw-ok); font-weight: 700; }
        .hw-tray { color: var(--hw-ink); background: color-mix(in srgb, var(--hw-fit) 14%, var(--hw-card)); border-left: 3px solid var(--hw-fit);
            border-radius: 6px; padding: 4px 9px; font-weight: 700; }
        .hw-order > .hw-btn { align-self: flex-start; }
        .hw-sups { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .hw-sup { border: 1px dashed var(--hw-line); border-radius: 9px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; }
        .hw-sup span { color: var(--hw-accent); font-weight: 700; }

        /* 7 · Pipeline */
        .hw-pipe { display: flex; flex-direction: column; gap: 10px; }
        .hw-track { position: relative; height: 26px; }
        .hw-chipjob { position: absolute; top: 0; left: 0; width: 16.66%; text-align: center; white-space: nowrap;
            background: var(--hw-accent); color: #fff; font-weight: 800; border-radius: 999px; padding: 3px 0; font-size: .8em; }
        .hw-stages { flex: 1 1 auto; display: grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: 6px; }
        .hw-st { position: relative; background: var(--hw-sub); border-radius: 8px; padding: 7px 6px; display: flex; flex-direction: column;
            gap: 2px; overflow: hidden; min-height: 170px; }
        .hw-st b { font-size: .9em; }
        .hw-st span { font-size: .78em; color: var(--hw-soft); }
        .hw-st em { font-style: normal; font-weight: 800; font-size: .95em; position: relative; z-index: 1; }
        .hw-st i { position: absolute; left: 6px; right: 6px; bottom: 6px; height: var(--h); border-radius: 5px;
            background: color-mix(in srgb, var(--hw-accent) 22%, transparent); }
        .hw-st.paid i { background: color-mix(in srgb, var(--hw-ok) 30%, transparent); }
        @media (max-width: 560px) { .hw-st b { font-size: .78em; } .hw-st em { font-size: .8em; } }

        /* 8 · Fitting drag */
        .hw-fit { display: grid; grid-template-columns: 30% minmax(0,1fr); gap: 10px; }
        .hw-trayp { background: var(--hw-sub); border-radius: 9px; padding: 8px; display: flex; flex-direction: column; gap: 6px; }
        .hw-trayp b { font-size: .88em; color: var(--hw-soft); }
        .hw-ghost { border: 2px dashed var(--hw-line); border-radius: 6px; padding: 9px 6px; color: transparent; font-size: .85em; }
        .hw-week { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); grid-auto-rows: minmax(70px, 1fr); gap: 5px; }
        .hw-week > span { position: relative; background: var(--hw-sub); border-radius: 7px; }
        .hw-week > span.hd { background: none; font-size: .82em; font-weight: 700; color: var(--hw-soft); text-align: center; min-height: 0; }
        .hw-week { grid-template-rows: auto; }
        .hw-week .hw-appt { top: 6px; }
        .hw-target { outline: 2px dashed color-mix(in srgb, var(--hw-fit) 55%, transparent); outline-offset: -2px; }
        .hw-drag { position: absolute; z-index: 3; border-radius: 6px; padding: 5px 7px; font-size: .85em; line-height: 1.25;
            background: color-mix(in srgb, var(--hw-fit) 16%, var(--hw-card)); border-left: 3px solid var(--hw-fit);
            box-shadow: 0 8px 18px rgba(15,23,42,.18); left: calc(12px + 8px); top: 72px; width: calc(30% - 28px); }
        .hw-drag b { display: block; font-size: .85em; color: var(--hw-fit); }
        .hw-ok { display: inline-block; margin-top: 3px; background: var(--hw-fit); color: #fff; border-radius: 5px; padding: 0 6px; font-weight: 800; font-size: .85em; }
        .hw-cur { position: absolute; right: -8px; bottom: -14px; width: 15px; fill: #0f172a; stroke: #fff; stroke-width: 1.2; }

        /* 9 · Paid */
        .hw-paid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 12px; align-items: start; }
        .hw-inv { position: relative; display: flex; flex-direction: column; gap: 7px; border: 1px solid var(--hw-line); border-radius: 10px; padding: 10px; }
        .hw-invrow { display: flex; justify-content: space-between; gap: 8px; }
        .hw-invrow.due { font-weight: 800; border-top: 1px solid var(--hw-line); padding-top: 6px; }
        .hw-invrow.pay { color: var(--hw-ok); font-weight: 700; }
        .hw-inv > .hw-btn { align-self: flex-start; }
        .hw-stamp { position: absolute; right: 16px; top: 53%; border: 3px solid var(--hw-ok); color: var(--hw-ok); font-weight: 900;
            font-size: 1.45em; letter-spacing: .08em; padding: 0 10px; border-radius: 8px; transform: rotate(-14deg); background: var(--hw-card); }
        .hw-acc { border-radius: 10px; padding: 10px; background: var(--hw-sub); display: flex; flex-direction: column; gap: 2px; }
        .hw-acc span { font-weight: 800; margin-bottom: 4px; }
        .hw-acc small { color: var(--hw-soft); }
        .hw-acc b { font-size: 1.35em; margin-bottom: 6px; }

        /* 10 · Finale */
        .hw-fin { flex: 1 1 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px;
            text-align: center; border-radius: 14px; padding: 16px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--hw-accent) 14%, var(--hw-card)), var(--hw-card)); }
        .hw-fin .logo { font-size: 1.9em; font-weight: 900; color: var(--hw-ink); }
        .hw-fin .logo span { color: var(--hw-accent); }
        .hw-fin-icons { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; font-size: 1.6em; }
        .hw-fin-icons span { display: inline-grid; place-items: center; width: 1.8em; height: 1.8em; border-radius: 50%;
            background: var(--hw-card); border: 1px solid var(--hw-line); }
        .hw-fin-cta { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; font-size: 13px; }

        /* ── Animation timeline classes ─────────────────────────
           Hidden/initial until the scene gets .play; .done = jump to the
           end state (first paint, and when stepping without playing). */
        .a-fade, .a-pop, .a-drop, .a-fly, .a-stamp, .a-mid { opacity: 0; }
        .a-type { display: inline-block; clip-path: inset(0 100% 0 0); white-space: nowrap; }
        .a-grow { transform: scaleY(0); transform-origin: bottom; }
        .a-draw { stroke-dasharray: 100; stroke-dashoffset: 100; }

        .play .a-fade  { animation: hwFade .5s ease var(--d, 0s) forwards; }
        .play .a-pop   { animation: hwPop .5s cubic-bezier(.3,1.4,.6,1) var(--d, 0s) forwards; }
        .play .a-drop  { animation: hwDrop .7s cubic-bezier(.3,1.3,.6,1) var(--d, 0s) forwards; }
        .play .a-fly   { animation: hwFly .6s ease-out var(--d, 0s) forwards; }
        .play .a-type  { animation: hwType .7s steps(14) var(--d, 0s) forwards; }
        .play .a-sel   { animation: hwSel .3s ease var(--d, 0s) forwards; }
        .play .a-press { animation: hwPress .45s ease var(--d, 0s); }
        .play .a-out   { animation: hwOut .3s ease var(--d, 0s) forwards; }
        .play .a-mid   { animation: hwFade .3s ease var(--d, 0s) forwards, hwOut .3s ease var(--d2, 9s) forwards; }
        .play .a-ring  { animation: hwShake .45s ease var(--d, 0s) 3; }
        .play .a-draw  { animation: hwDraw 2.6s ease-in-out var(--d, 0s) forwards; }
        .play .a-grow  { animation: hwGrow .8s ease-out var(--d, 0s) forwards; }
        .play .a-stamp { animation: hwStamp .45s cubic-bezier(.3,1.3,.6,1) var(--d, 0s) forwards; }
        .play .a-drop.a-glow { animation: hwDrop .7s cubic-bezier(.3,1.3,.6,1) var(--d, 0s) forwards, hwGlow 1.1s ease 1.3s 2; }
        .play .a-travel { animation: hwTravel 6.5s ease-in-out .8s forwards; }
        .play .a-drag  { animation: hwDragTo 2.4s cubic-bezier(.5,0,.3,1) 1s forwards; }

        .done .a-fade, .done .a-pop, .done .a-drop, .done .a-fly, .done .a-mid { opacity: 1; }
        .done .a-type { clip-path: none; }
        .done .a-grow { transform: none; }
        .done .a-draw { stroke-dashoffset: 0; }
        .done .a-stamp { opacity: 1; }
        .done .a-sel { background: var(--hw-accent); color: #fff; border-color: var(--hw-accent); }
        .done .a-out, .done .a-mid { opacity: 0; }
        .done .a-travel { left: 83.34%; }
        .done .a-drag, .play .a-drag { }
        .done .a-drag { left: var(--drag-x); top: var(--drag-y); width: var(--drag-w); }
        .hw-fit { --drag-x: calc(12px + 30% + 10px + (70% - 34px) * .6 + 4px); --drag-y: 120px; --drag-w: calc((70% - 34px) * .2 - 6px); }

        .paused .play *, .paused .play { animation-play-state: paused !important; }

        @keyframes hwFade  { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        @keyframes hwPop   { 0% { opacity: 0; transform: scale(.6); } 100% { opacity: 1; transform: none; } }
        @keyframes hwDrop  { 0% { opacity: 0; transform: translateY(-40px) scale(.96); } 100% { opacity: 1; transform: none; } }
        @keyframes hwFly   { 0% { opacity: 0; transform: translateX(-28px); } 100% { opacity: 1; transform: none; } }
        @keyframes hwType  { from { clip-path: inset(0 100% 0 0); } to { clip-path: inset(0 0 0 0); } }
        @keyframes hwSel   { to { background: var(--hw-accent); color: #fff; border-color: var(--hw-accent); } }
        @keyframes hwPress { 50% { transform: scale(.92); filter: brightness(1.2); } }
        @keyframes hwOut   { to { opacity: 0; } }
        @keyframes hwShake { 0%,100% { transform: rotate(0); } 25% { transform: rotate(-16deg); } 75% { transform: rotate(16deg); } }
        @keyframes hwDraw  { to { stroke-dashoffset: 0; } }
        @keyframes hwGrow  { to { transform: none; } }
        @keyframes hwStamp { 0% { opacity: 0; transform: rotate(-14deg) scale(2.4); } 100% { opacity: 1; transform: rotate(-14deg) scale(1); } }
        @keyframes hwGlow  { 50% { box-shadow: 0 0 0 7px color-mix(in srgb, var(--hw-accent) 25%, transparent); } }
        @keyframes hwTravel { 0%,8% { left: 0; } 18%,26% { left: 16.66%; } 36%,44% { left: 33.33%; }
                              54%,62% { left: 50%; } 72%,80% { left: 66.66%; } 90%,100% { left: 83.34%; } }
        @keyframes hwDragTo { 0% { transform: none; }
                              15% { transform: scale(1.06) rotate(-2deg); }
                              100% { left: var(--drag-x); top: var(--drag-y); width: var(--drag-w); transform: none; } }

        @media (prefers-reduced-motion: reduce) {
            .play *, .play { animation-duration: .01s !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
            .hw-start .big { animation: none; }
        }

        .hw-after { text-align: center; margin-top: 2.5rem; }
        .hw-after h2 { font-size: 1.5rem; margin: 0 0 .4rem; color: var(--text-primary); }
        .hw-after p { color: var(--text-secondary); margin: 0 0 1.2rem; }
        .hw-after .row { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }

        .hw-read { margin-top: 2.5rem; border-top: 1px solid var(--border); padding-top: 1.25rem; }
        .hw-read summary { cursor: pointer; font-weight: 700; color: var(--text-primary); }
        .hw-read ol { color: var(--text-secondary); line-height: 1.55; padding-left: 1.3rem; }
        .hw-read li { margin: .8rem 0; }
        .hw-read li b { color: var(--text-primary); }

        .hw-foot { text-align: center; color: var(--text-faint); font-size: 0.9rem; margin-top: 2.5rem; }
        .hw-foot a { color: var(--link); }
    </style>
</head>
<body>
<div class="hw-wrap">
    <div class="hw-top">
        <a class="hw-brand" href="/welcome.php">Your<span class="accent">Blinds</span></a>
        <div class="hw-top-links">
            <a href="/welcome.php">Plans &amp; pricing</a>
            <a href="/instaprice/index.php">Try InstaPrice</a>
            <?php if ($loggedIn): ?>
                <a href="/calendar/index.php">Go to my dashboard</a>
            <?php else: ?>
                <a href="/auth/login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="hw-hero">
        <h1>See how it works</h1>
        <p>Follow one job from the first phone call to money in the bank &mdash; exactly the way you&rsquo;d run it in YourBlinds.</p>
    </section>

    <div class="hw-player" id="hw-player">
        <div class="hw-stage" id="hw-stage">
            <?php foreach ($SCENES as $n => $s): ?>
                <section class="hw-scene<?= $n === 0 ? ' done' : '' ?>" data-secs="<?= (int) $s['secs'] ?>"
                         data-say="<?= e(hw_say($s)) ?>" data-audio="<?= e(hw_clip_url($s)) ?>" <?= $n === 0 ? '' : 'hidden' ?>
                         aria-label="Step <?= $n + 1 ?> of <?= $sceneCount ?>: <?= e($s['title']) ?>">
                    <div class="hw-screen" aria-hidden="true">
                        <?php if ($s['mock'] === '__FINALE__'): ?>
                            <div class="hw-fin">
                                <div class="logo a-pop" style="--d:.2s">Your<span>Blinds</span></div>
                                <div class="hw-fin-icons">
                                    <?php foreach (array_slice($SCENES, 0, -1) as $k => $x): ?>
                                        <span class="a-pop" style="--d:<?= number_format(0.6 + $k * 0.18, 2) ?>s"><?= $x['icon'] ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="hw-fin-cta a-fade" style="--d:2.4s">
                                    <?php if (!$loggedIn && !$signupsClosed): ?>
                                        <a class="hw-cta primary" href="/auth/signup.php">Start free &rarr;</a>
                                    <?php endif; ?>
                                    <a class="hw-cta ghost" href="/instaprice/index.php">Get a price now</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?= $s['mock'] ?>
                        <?php endif; ?>
                    </div>
                    <div class="hw-text" aria-live="polite">
                        <div class="hw-step"><?= $s['icon'] ?> Step <?= $n + 1 ?> of <?= $sceneCount ?></div>
                        <h2><?= e($s['title']) ?></h2>
                        <p><?= e($s['body']) ?></p>
                        <?php if ($s['tier'] !== ''): ?><span class="hw-tier"><?= e($s['tier']) ?></span><?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <button type="button" class="hw-start" id="hw-start">
                <span class="big" aria-hidden="true">&#9654;</span>
                <b>Play the tour</b>
                <small>About 3 minutes &middot; with voiceover</small>
            </button>
        </div>
        <div class="hw-progress"><i id="hw-bar"></i></div>
        <div class="hw-controls">
            <button type="button" class="hw-ctl" id="hw-prev" aria-label="Previous step">&#9664;</button>
            <button type="button" class="hw-ctl main" id="hw-play">&#9654; Play</button>
            <button type="button" class="hw-ctl" id="hw-next" aria-label="Next step">&#9654;&#9654;</button>
            <nav class="hw-dots" aria-label="Tour steps">
                <?php foreach ($SCENES as $n => $s): ?>
                    <button type="button" class="hw-dot<?= $n === 0 ? ' on' : '' ?>" data-go="<?= $n ?>"><?= $s['icon'] ?> <?= e($s['nav']) ?></button>
                <?php endforeach; ?>
            </nav>
            <button type="button" class="hw-ctl" id="hw-voice" aria-pressed="true">&#128266; Voice on</button>
        </div>
    </div>

    <section class="hw-after">
        <h2>Ready to run your business this way?</h2>
        <p>Start free. Add Maps and Accounts whenever you&rsquo;re ready.</p>
        <div class="row">
            <?php if ($signupsClosed): ?>
                <span class="hw-cta ghost">Sign-ups are paused just now</span>
            <?php elseif (!$loggedIn): ?>
                <a class="hw-cta primary" href="/auth/signup.php">Start free &rarr;</a>
            <?php endif; ?>
            <a class="hw-cta ghost" href="/welcome.php">See plans &amp; pricing</a>
        </div>
    </section>

    <details class="hw-read">
        <summary>Prefer to read it? The whole journey, step by step</summary>
        <ol>
            <?php foreach ($SCENES as $s): ?>
                <li><b><?= e($s['title']) ?></b> <?= e($s['body']) ?><?= $s['tier'] !== '' ? ' (' . e($s['tier']) . ')' : '' ?></li>
            <?php endforeach; ?>
        </ol>
    </details>

    <p class="hw-foot">
        <a href="/welcome.php">What&rsquo;s it all about?</a> &middot; <a href="/instaprice/index.php">Try the price calculator</a><br>
        <a href="/legal/licence.php">Licence agreement</a> &middot; <a href="/legal/privacy.php">Privacy policy</a>
    </p>
</div>

<script>
(function () {
    'use strict';
    var stage   = document.getElementById('hw-stage');
    var scenes  = Array.prototype.slice.call(stage.querySelectorAll('.hw-scene'));
    var dots    = Array.prototype.slice.call(document.querySelectorAll('.hw-dot'));
    var start   = document.getElementById('hw-start');
    var btnPlay = document.getElementById('hw-play');
    var btnPrev = document.getElementById('hw-prev');
    var btnNext = document.getElementById('hw-next');
    var btnVoice= document.getElementById('hw-voice');
    var bar     = document.getElementById('hw-bar');
    var reduce  = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var synth   = ('speechSynthesis' in window) ? window.speechSynthesis : null;
    var voice   = null, cur = 0, playing = false, finished = false, token = 0, timers = [];
    var clip    = new Audio(), cutTalk = null;
    var hasClips = scenes.some(function (s) { return !!s.dataset.audio; });
    var voiceOn = !!synth || hasClips;

    try { if (localStorage.getItem('yb_tour_voice') === '0') voiceOn = false; } catch (e) {}

    // Most natural British voice the browser offers: Edge's neural
    // "(Natural)" voices (Sonia, Libby), Apple's enhanced/premium ones, then
    // Chrome's Google UK English Female, then any en-GB.
    function pickVoice() {
        if (!synth) return;
        var vs = synth.getVoices() || [];
        var gb = vs.filter(function (v) { return /^en[-_]GB/i.test(v.lang); });
        voice = gb.find(function (v) { return /Sonia.*Natural/i.test(v.name); })
             || gb.find(function (v) { return /Libby.*Natural/i.test(v.name); })
             || gb.find(function (v) { return /Natural/i.test(v.name); })
             || gb.find(function (v) { return /(Premium|Enhanced)/i.test(v.name); })
             || vs.find(function (v) { return v.name === 'Google UK English Female'; })
             || gb[0]
             || vs.find(function (v) { return /^en/i.test(v.lang); }) || null;
    }
    if (synth) { pickVoice(); synth.onvoiceschanged = pickVoice; }
    if (!synth && !hasClips) btnVoice.hidden = true;

    // ── Anonymous tour stats (Master admin → Tour stats) ─────────────
    // A random id per page load — no cookies, nothing stored on the device.
    var started = false;
    var visitId = (function () {
        var b = new Uint8Array(8);
        try { crypto.getRandomValues(b); } catch (e) { for (var i = 0; i < 8; i++) b[i] = Math.random() * 256; }
        return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    })();
    var srcTag = '';
    try { srcTag = new URLSearchParams(location.search).get('src') || ''; } catch (e) {}
    function track(event, step) {
        try {
            var body = JSON.stringify({ v: visitId, e: event, s: step || 0, r: document.referrer || '', src: srcTag });
            if (navigator.sendBeacon) navigator.sendBeacon('/api/tour-event.php', new Blob([body], { type: 'application/json' }));
            else fetch('/api/tour-event.php', { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'application/json' } });
        } catch (e) {}
    }
    track('view');
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        var h = a.getAttribute('href');
        if (h.indexOf('/auth/signup.php') === 0) track('cta_signup');
        else if (h.indexOf('/instaprice/') === 0) track('cta_price');
    });

    function later(fn, ms) { timers.push(setTimeout(fn, ms)); }
    function clearAll() {
        timers.forEach(clearTimeout); timers = []; cutTalk = null;
        if (synth) synth.cancel();
        clip.onended = clip.onerror = null; clip.pause();
    }

    // Browser text-to-speech, sentence by sentence (Chrome cuts long
    // utterances off mid-way). Used when a scene has no recorded clip.
    function speakTts(text, done) {
        if (!synth) { done(); return; }
        var parts = (text.match(/[^.!?]+[.!?]+["”’]?|[^.!?]+$/g) || [text])
            .map(function (x) { return x.trim(); }).filter(Boolean);
        parts.forEach(function (txt, k) {
            var u = new SpeechSynthesisUtterance(txt);
            if (voice) { u.voice = voice; u.lang = voice.lang; } else { u.lang = 'en-GB'; }
            u.rate = 1.03;
            if (k === parts.length - 1) u.onend = done;
            u.onerror = done;
            synth.speak(u);
        });
    }

    function money(n, prefix) { return prefix + Math.round(n).toLocaleString('en-GB'); }
    function counters(s, animate) {
        s.querySelectorAll('[data-count]').forEach(function (el) {
            var to = +el.dataset.count, from = +(el.dataset.from || 0), p = el.dataset.prefix || '';
            if (!animate || reduce) { el.textContent = money(to, p); return; }
            el.textContent = money(from, p);
            var my = token, dur = (+el.dataset.dur || 1.5) * 1000;
            later(function () {
                var t0 = performance.now();
                (function step(t) {
                    if (my !== token) return;
                    var k = Math.min(1, (t - t0) / dur);
                    el.textContent = money(from + (to - from) * (1 - Math.pow(1 - k, 3)), p);
                    if (k < 1) requestAnimationFrame(step);
                })(t0);
            }, (+el.dataset.d || 0) * 1000);
        });
    }

    function paintUi() {
        dots.forEach(function (d, k) {
            d.classList.toggle('on', k === cur);
            d.classList.toggle('seen', k < cur);
            if (k === cur) { d.setAttribute('aria-current', 'step'); d.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
            else d.removeAttribute('aria-current');
        });
        bar.style.width = ((cur + 1) / scenes.length * 100) + '%';
        btnPlay.innerHTML = playing ? '&#10073;&#10073; Pause' : (finished ? '&#8635; Replay' : '&#9654; Play');
        btnVoice.innerHTML = voiceOn ? '&#128266; Voice on' : '&#128263; Voice off';
        btnVoice.setAttribute('aria-pressed', voiceOn ? 'true' : 'false');
    }

    // Show scene n. animate=true replays its timeline from the start.
    function show(n, animate) {
        token++; clearAll();
        stage.classList.remove('paused');
        cur = Math.max(0, Math.min(scenes.length - 1, n));
        scenes.forEach(function (s, k) { s.classList.remove('play', 'done'); s.hidden = k !== cur; });
        var s = scenes[cur];
        void s.offsetWidth;                       // restart CSS animations
        s.classList.add(animate ? 'play' : 'done');
        counters(s, animate);
        paintUi();
        if (started) {
            track('step', cur + 1);
            if (cur === scenes.length - 1) track('finish');
        }
    }

    // Play scene n, then move on once both the minimum time and the
    // narration have finished.
    function run(n) {
        finished = false;
        show(n, true);
        if (!playing) return;
        var my = token, s = scenes[cur];
        var minDone = false, talkDone = !(voiceOn && (synth || s.dataset.audio));
        function next() {
            if (my !== token || !minDone || !talkDone) return;
            later(function () {
                if (my !== token) return;
                if (cur < scenes.length - 1) run(cur + 1);
                else { playing = false; finished = true; paintUi(); }
            }, 900);
        }
        later(function () { minDone = true; next(); }, (+s.dataset.secs || 8) * 1000);
        if (!talkDone) {
            var said = false;
            var done = function () { if (said || my !== token) return; said = true; talkDone = true; next(); };
            cutTalk = done;
            if (s.dataset.audio) {
                // Recorded narration (natural voice); browser voice if it fails.
                var fellBack = false;
                var fallback = function () {
                    if (fellBack || said || my !== token) return;
                    fellBack = true; clip.onerror = null; speakTts(s.dataset.say, done);
                };
                clip.onended = done;
                clip.onerror = fallback;
                clip.src = s.dataset.audio;
                var p = clip.play();
                if (p && p.catch) p.catch(fallback);
            } else {
                speakTts(s.dataset.say, done);
            }
            // Safety net if the browser never reports the end of the narration.
            later(done, (+s.dataset.secs || 8) * 1000 + s.dataset.say.split(/\s+/).length * 450);
        }
    }

    function play() {
        start.hidden = true;
        started = true; track('play');
        playing = true;
        run(finished ? 0 : cur);
    }
    function pause() {
        playing = false; token++; clearAll();
        stage.classList.add('paused');
        paintUi();
    }
    function go(n) {
        start.hidden = true;
        started = true;
        if (playing) run(n); else { finished = false; show(n, true); }
    }

    start.addEventListener('click', play);
    btnPlay.addEventListener('click', function () { playing ? pause() : play(); });
    btnPrev.addEventListener('click', function () { go(cur - 1); });
    btnNext.addEventListener('click', function () { go(cur + 1); });
    dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.go); }); });
    btnVoice.addEventListener('click', function () {
        voiceOn = !voiceOn;
        try { localStorage.setItem('yb_tour_voice', voiceOn ? '1' : '0'); } catch (e) {}
        if (!voiceOn) {                            // stop talking; the scene moves on
            var cut = cutTalk;
            if (synth) synth.cancel();
            clip.pause();
            if (cut) cut();
        }
        paintUi();
    });
    document.addEventListener('keydown', function (e) {
        if (e.target.closest && e.target.closest('input, textarea, select')) return;
        if (e.key === 'ArrowRight') go(cur + 1);
        else if (e.key === 'ArrowLeft') go(cur - 1);
    });
    window.addEventListener('pagehide', function () { if (synth) synth.cancel(); clip.pause(); });

    paintUi();
})();
</script>
</body>
</html>
