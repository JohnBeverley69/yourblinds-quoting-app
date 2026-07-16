<?php /* Shared styles for the floor board + station queue + blind cards. Include once per page. */ ?>
<style>
  .fl-head { display:flex; align-items:baseline; gap:.9rem; flex-wrap:wrap; margin:0 0 .3rem; }
  .fl-h1 { font-size:1.5rem; font-weight:700; margin:0; letter-spacing:-.01em; }
  .fl-sub { color:var(--text-muted,#667); margin:.15rem 0 1.1rem; font-size:.92rem; }
  .fl-stat { font-size:.8rem; font-weight:600; color:var(--text-muted,#667); }
  .fl-stat b { color:inherit; }
  .fl-flash { padding:.6rem 1rem; border-radius:10px; margin:0 0 1rem; font-size:.9rem; }
  .fl-flash.ok{ background:#dcfce7; color:#166534; } .fl-flash.err{ background:#fee2e2; color:#991b1b; }
  .fl-empty { background:var(--bg-subtle,#f8fafc); border:1px dashed var(--border,#e5e7eb); border-radius:12px; padding:1.75rem; color:var(--text-faint,#94a3b8); text-align:center; }

  .fl-count { font-size:.72rem; font-weight:700; background:#1f2a37; color:#fff; border-radius:999px; padding:.05rem .5rem; min-width:1.2rem; text-align:center; }
  .pill.out{ background:#fef3c7; color:#92400e; font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:2px 7px; border-radius:999px; }

  /* Controls above the table. */
  .fl-bar { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin:0 0 .9rem; }
  .fl-bar input[type=search], .fl-bar select { font:inherit; font-size:.9rem; padding:.4rem .6rem; border:1px solid var(--border,#e5e7eb); border-radius:8px; background:var(--bg-card,#fff); color:inherit; }
  .fl-bar input[type=search] { min-width:15rem; }
  .fl-bar label { display:inline-flex; align-items:center; gap:.35rem; font-size:.85rem; color:var(--text-muted,#667); }

  /* One row per blind. */
  .fl-tw { border:1px solid var(--border,#e5e7eb); border-radius:12px; background:var(--bg-card,#fff); overflow-x:auto; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .fl-tbl { width:100%; border-collapse:collapse; }
  .fl-tbl th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint,#94a3b8); font-weight:700; padding:.5rem .7rem; background:var(--bg-subtle,#f8fafc); border-bottom:1px solid var(--border,#e5e7eb); white-space:nowrap; position:sticky; top:0; z-index:2; }
  .fl-tbl td { padding:.45rem .7rem; border-bottom:1px solid var(--border,#eef1f5); font-size:.875rem; vertical-align:middle; }
  .fl-tbl tr:last-child td { border-bottom:none; }
  .fl-tbl tbody tr:hover { background:var(--bg-subtle,#f8fafc); }
  .fl-tbl tr.is-made td { opacity:.6; }
  .fl-ref { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; text-decoration:none; color:#b91c1c; }
  .fl-ref:hover { text-decoration:underline; }
  .fl-tenant { display:block; font-size:.72rem; font-weight:400; color:var(--text-faint,#94a3b8); }
  .fl-blind { font-weight:600; white-space:nowrap; }
  .fl-blind span { font-weight:400; color:var(--text-muted,#667); }
  .fl-fab { display:block; font-size:.78rem; font-weight:400; color:var(--text-muted,#667); }
  .fl-size { font-variant-numeric:tabular-nums; white-space:nowrap; }
  .fl-date { color:var(--text-muted,#667); white-space:nowrap; font-size:.82rem; }
  .fl-due.late  { color:#b91c1c; font-weight:700; }
  .fl-due.today { color:#b45309; font-weight:700; }
  .fl-due.soon  { color:#92600a; font-weight:600; }

  /* Progress bar. */
  .fl-prog { display:flex; align-items:center; gap:.4rem; min-width:6.5rem; }
  .fl-prog-track { flex:1; height:.85rem; background:#e9edf2; border-radius:999px; overflow:hidden; }
  .fl-prog-fill { height:100%; background:#4ade80; border-radius:999px; transition:width .2s; }
  .fl-prog-fill.full { background:#16a34a; }
  .fl-prog-pct { font-size:.7rem; font-weight:700; color:#166534; min-width:2.4rem; text-align:right; font-variant-numeric:tabular-nums; }

  /* The route as a chevron strip — click a stage to move the blind there. */
  .fl-strip { display:flex; align-items:stretch; white-space:nowrap; margin:0; }
  .stg { font:inherit; font-size:.72rem; font-weight:600; cursor:pointer; border:none; color:#64748b; background:#eef1f5;
         padding:.3rem .75rem .3rem 1rem; margin-right:-7px; position:relative;
         clip-path:polygon(0 0, calc(100% - 8px) 0, 100% 50%, calc(100% - 8px) 100%, 0 100%, 8px 50%); }
  .stg:first-child { clip-path:polygon(0 0, calc(100% - 8px) 0, 100% 50%, calc(100% - 8px) 100%, 0 100%); padding-left:.75rem; border-radius:4px 0 0 4px; }
  .stg:hover { filter:brightness(.94); }
  .stg.done    { background:#dcfce7; color:#15803d; }
  .stg.current { background:#fed7aa; color:#9a3412; z-index:1; }
  .stg.working { background:#fdba74; color:#7c2d12; z-index:1; }
  .stg.made    { background:#eef1f5; color:#64748b; clip-path:none; margin-right:0; border-radius:0 4px 4px 0; padding-right:.75rem; }
  .stg.made.done { background:#16a34a; color:#fff; }

  /* Single-station queue: a roomier one-column list. */
  .fl-queue { display:grid; grid-template-columns:repeat(auto-fill,minmax(16rem,1fr)); gap:.7rem; }

  /* Blind card. */
  .bcard { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:.55rem .65rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .bcard.working { border-color:#f59e0b; box-shadow:0 0 0 1px #f59e0b33; }
  /* Nothing in the header may wrap mid-word — a squeezed card must push the
     badges onto a second line, not shred the order ref down the card. */
  .bcard-top { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
  .bcard-ref { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .bcard-qty { font-size:.72rem; font-weight:700; background:#e2e8f0; color:#334155; border-radius:6px; padding:.02rem .35rem; white-space:nowrap; }
  .bcard-live { margin-left:auto; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#b45309; background:#fef3c7; border-radius:999px; padding:.05rem .45rem; white-space:nowrap; }
  .bcard-prod { font-weight:600; font-size:.9rem; margin-top:.15rem; }
  .bcard-sys { font-weight:400; color:var(--text-muted,#667); font-size:.8rem; }
  .bcard-fab { color:var(--text-muted,#556); font-size:.82rem; }
  .bcard-meta { display:flex; gap:.6rem; font-size:.82rem; color:var(--text-muted,#667); margin-top:.1rem; }
  .bcard-size { font-variant-numeric:tabular-nums; font-weight:600; color:inherit; }
  .bcard-sub { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.25rem; font-size:.72rem; }
  .bcard-op { background:#eef2ff; color:#3730a3; border-radius:6px; padding:.03rem .4rem; }
  .bcard-tenant { color:var(--text-faint,#94a3b8); }
  .bcard-actions { display:flex; gap:.35rem; margin-top:.5rem; }
  .bc-inline { display:inline; margin:0; }
  .bc-btn { font:inherit; font-size:.78rem; font-weight:600; cursor:pointer; border:none; border-radius:7px; padding:.28rem .6rem; }
  .bc-btn.go { background:#166534; color:#fff; flex:0 0 auto; }
  .bc-btn.go:hover { background:#14532d; }
  .bc-btn.ghost { background:#eef2f6; color:#334155; }
  .bc-btn.ghost:hover { background:#e2e8f0; }
  .bc-btn.back { background:transparent; color:var(--text-faint,#94a3b8); padding:.28rem .5rem; margin-left:auto; }
  .bc-btn.back:hover { background:#f1f5f9; color:#334155; }
</style>
