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

  /* Board: horizontally-scrolling station columns. */
  .fl-board { display:flex; gap:.9rem; overflow-x:auto; padding-bottom:.6rem; align-items:flex-start; }
  .fl-col { flex:0 0 15rem; background:var(--bg-subtle,#f8fafc); border:1px solid var(--border,#e5e7eb); border-radius:12px; }
  .fl-col.out { background:#fffbeb; border-color:#fde68a; }
  .fl-col.done { background:#f0fdf4; border-color:#bbf7d0; }
  .fl-col-head { position:sticky; top:0; display:flex; align-items:center; gap:.5rem; padding:.6rem .75rem; border-bottom:1px solid var(--border,#e5e7eb); }
  .fl-col-head h2 { font-size:.9rem; margin:0; flex:1; }
  .fl-col-head a { color:inherit; text-decoration:none; }
  .fl-col-head a:hover h2 { text-decoration:underline; }
  .fl-count { font-size:.72rem; font-weight:700; background:#1f2a37; color:#fff; border-radius:999px; padding:.05rem .5rem; min-width:1.2rem; text-align:center; }
  .fl-col.out .fl-count{ background:#b45309; } .fl-col.done .fl-count{ background:#166534; }
  .fl-col-body { padding:.6rem; display:flex; flex-direction:column; gap:.55rem; min-height:2.5rem; max-height:70vh; overflow-y:auto; }
  .fl-col-empty { color:var(--text-faint,#94a3b8); font-size:.8rem; text-align:center; padding:.6rem 0; }
  .pill.out{ background:#fef3c7; color:#92400e; font-size:.6rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:2px 7px; border-radius:999px; }

  /* Single-station queue: a roomier one-column list. */
  .fl-queue { display:grid; grid-template-columns:repeat(auto-fill,minmax(16rem,1fr)); gap:.7rem; }

  /* Blind card. */
  .bcard { background:var(--bg-card,#fff); border:1px solid var(--border,#e5e7eb); border-radius:10px; padding:.55rem .65rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
  .bcard.working { border-color:#f59e0b; box-shadow:0 0 0 1px #f59e0b33; }
  .bcard-top { display:flex; align-items:center; gap:.4rem; }
  .bcard-ref { font-weight:700; font-variant-numeric:tabular-nums; }
  .bcard-qty { font-size:.72rem; font-weight:700; background:#e2e8f0; color:#334155; border-radius:6px; padding:.02rem .35rem; }
  .bcard-live { margin-left:auto; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#b45309; background:#fef3c7; border-radius:999px; padding:.05rem .45rem; }
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
