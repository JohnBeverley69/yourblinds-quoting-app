<?php
/** Shared look of the public online booking pages (book/index.php, book/manage.php). */
?>
<style>
    :root {
        --bg: #f4f5f7; --card: #ffffff; --text: #1f2937; --muted: #6b7280; --line: #e5e7eb;
        --accent: #1d4ed8; --accent-soft: #e8efff; --error-bg: #fef2f2; --error: #b91c1c; --ok: #047857; --ok-bg: #ecfdf5;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #111318; --card: #1b1e25; --text: #e5e7eb; --muted: #9ca3af; --line: #2d323c;
            --accent: #7aa2ff; --accent-soft: #1f2a44; --error-bg: #3a1717; --error: #fca5a5; --ok: #6ee7b7; --ok-bg: #11302a;
        }
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .wrap { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .brand img { max-height: 56px; max-width: 180px; object-fit: contain; }
    .brand h1 { font-size: 1.25rem; margin: 0; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
    h2 { font-size: 1.15rem; margin: 0 0 4px; }
    .muted { color: var(--muted); font-size: .925rem; }
    label { display: block; font-weight: 600; font-size: .925rem; margin: 14px 0 4px; }
    input[type=text], input[type=email], input[type=tel], textarea, select {
        width: 100%; padding: 10px 12px; font: inherit; color: var(--text); background: var(--card);
        border: 1px solid var(--line); border-radius: 8px;
    }
    textarea { min-height: 80px; resize: vertical; }
    .btn { display: inline-block; margin-top: 18px; padding: 11px 20px; font: inherit; font-weight: 600; color: #fff;
           background: var(--accent); border: 0; border-radius: 8px; cursor: pointer; text-decoration: none; }
    @media (prefers-color-scheme: dark) { .btn { color: #0b1020; } }
    .alert { padding: 10px 12px; border-radius: 8px; margin: 0 0 14px; background: var(--error-bg); color: var(--error); }
    .done { background: var(--ok-bg); color: var(--ok); padding: 12px; border-radius: 8px; font-weight: 600; }
    .days { margin-top: 10px; border-top: 1px solid var(--line); }
    .day { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 0; border-bottom: 1px solid var(--line); }
    .day-name { width: 92px; font-weight: 600; font-size: .925rem; }
    .slot { position: relative; }
    .slot input { position: absolute; opacity: 0; inset: 0; }
    .slot span { display: inline-block; padding: 7px 12px; border: 1px solid var(--line); border-radius: 999px; font-size: .9rem; cursor: pointer; }
    .slot input:checked + span { background: var(--accent); border-color: var(--accent); color: #fff; }
    @media (prefers-color-scheme: dark) { .slot input:checked + span { color: #0b1020; } }
    .slot input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }
    .trap { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
    @media (max-width: 480px) {
        .row2 { grid-template-columns: 1fr; } .day-name { width: 100%; }
        .card { padding: 16px 14px; } .day { gap: 6px; } .slot span { padding: 6px 9px; font-size: .84rem; }
    }
    .foot { margin-top: 18px; text-align: center; font-size: .8rem; color: var(--muted); }
    .pc { font-weight: 600; }
    a { color: var(--accent); }
</style>
