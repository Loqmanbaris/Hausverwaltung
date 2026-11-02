<style>
:root{
  --paper:#f5f6f8; --surface:#ffffff; --ink:#111827; --muted:#6b7280; --line:#e5e7eb; --line-strong:#d1d5db;
  --primary:#111827; --primary-hover:#000000; --on-primary:#ffffff; --brand:#c2410c; --brand-soft:#fff4ed;
  --brass:#7a4b00; --brass-bg:#fffaf0; --ok:#067647; --ok-bg:#ecfdf3; --danger:#b42318; --danger-bg:#fef2f2;
  --focus:#2563eb; --radius:10px;
  --surface-2:#f6f7f9; --info:#3730a3; --info-bg:#eef2ff;
  --brass-line:#f0d9a8; --danger-line:#fecaca; --ok-line:#a7f3d0;
}
/* Dunkler Modus (02.11.2026): nur im CRM aktiv; das Layout setzt html[data-theme=dark] je nach Wahl im Profilmenü */
html[data-theme=dark]{
  color-scheme:dark;
  --paper:#0f141a; --surface:#182029; --surface-2:#1f2832; --ink:#e6e9ee; --muted:#9aa5b4; --line:#2a3440; --line-strong:#3a4654;
  --primary:#e6e9ee; --primary-hover:#ffffff; --on-primary:#111827; --brand-soft:#2b1a10;
  --brass:#f5c26b; --brass-bg:#2a2112; --brass-line:#5c4720; --ok:#4ade80; --ok-bg:#0f2a1d; --ok-line:#1f5c3d;
  --danger:#f87171; --danger-bg:#2d1517; --danger-line:#6b2a2a; --info:#a5b4fc; --info-bg:#1c2140; --focus:#60a5fa;
  --hv-dark:#11171d; --hv-dark2:#1a222b;
}
html[data-theme=dark] img:not([src$=".svg"]){filter:brightness(.92)}
/* Farbige Kacheln der Übersicht: zarte Pastelltöne werden dunkel getönt */
html[data-theme=dark] [style*="--tile-soft"]{--tile-soft:color-mix(in srgb,var(--tile) 18%,var(--surface))!important}
html[data-theme=dark] .ck-block.has-color .ck-num:not(.warn),html[data-theme=dark] .ck-block.has-color .ck-day:not(.warn) b,html[data-theme=dark] .ck-tile[style] .k{color:color-mix(in srgb,var(--tile) 55%,#fff)!important}
html[data-theme=dark] .panel,html[data-theme=dark] .sd-card,html[data-theme=dark] .card{box-shadow:none!important}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--paper);color:var(--ink);font:15.5px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
h1,h2,h3{line-height:1.2;margin:0 0 .6rem;font-weight:700;letter-spacing:-.02em}
h1{font-size:1.65rem} h2{font-size:1.15rem}
p{margin:0 0 1rem}
a{color:var(--ink);text-decoration-color:var(--brand);text-underline-offset:2px}
a:hover{color:var(--brand)}
.muted{color:var(--muted)}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
:focus-visible{outline:3px solid var(--focus);outline-offset:2px}

.test-banner{background:var(--brass-bg);color:var(--brass);text-align:center;padding:.35rem 1rem;font-size:.88rem;border-bottom:1px solid var(--brass-line)}

.container{max-width:1180px;margin:0 auto;padding:1.5rem 1.25rem 3rem}
.narrow{max-width:440px}
.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:1.5rem;box-shadow:0 1px 2px rgba(17,24,39,.04);overflow-x:auto}
.panel + .panel{margin-top:1rem}

.alert{border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;border:1px solid}
.alert.ok{background:var(--ok-bg);border-color:var(--ok-line);color:var(--ok)}
.alert.warn{background:var(--brass-bg);border-color:var(--brass-line);color:var(--brass)}
.alert.error{background:var(--danger-bg);border-color:var(--danger-line);color:var(--danger)}

label{display:block;font-weight:600;margin:0 0 .25rem}
input[type=text],input[type=email],input[type=password],input[type=tel],input[type=number],input[type=date],input[type=month],select,textarea{width:100%;padding:.6rem .75rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;background:var(--surface);color:var(--ink)}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 18%,transparent)}
.field{margin-bottom:1rem}
.hint{color:var(--muted);font-size:.9rem;margin:.25rem 0 0}
.error{color:var(--danger);font-size:.92rem;margin:.3rem 0 0}

.btn{display:inline-block;padding:.6rem 1.1rem;border-radius:8px;border:1px solid var(--line-strong);background:var(--surface);color:var(--ink);font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.btn:hover{border-color:var(--ink);color:var(--ink)}
.btn.primary{background:var(--primary);border-color:var(--ink);color:var(--on-primary)}
.btn.primary:hover{background:var(--primary-hover);color:var(--on-primary)}
.btn.danger{border-color:var(--danger-line);color:var(--danger)}
.btn.danger:hover{background:var(--danger-bg)}
.btn.small{padding:.3rem .65rem;font-size:.88rem}
.btn.block{display:block;width:100%;text-align:center}

.table-wrap{overflow-x:auto;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)}
table{border-collapse:collapse;width:100%;min-width:640px}
th,td{padding:.6rem .8rem;text-align:left;border-bottom:1px solid var(--line);vertical-align:top;font-size:.95rem}
th{background:var(--surface-2);font-weight:600;white-space:nowrap;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
tr:last-child td{border-bottom:0}
td form{display:inline;margin:0 .25rem .25rem 0}
.status-ok{color:var(--ok);font-weight:600}
.status-off{color:var(--danger);font-weight:600}
.status-wait{color:var(--brass);font-weight:600}

.toolbar{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;margin-bottom:1rem}
.search{display:flex;gap:.5rem}
.search input{min-width:220px}
.pager{display:flex;justify-content:space-between;margin-top:1rem}

.codes{display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem;margin:1rem 0;padding:1rem;background:var(--surface-2);border:1px dashed var(--line-strong);border-radius:8px;font-size:1.1rem}
.qr{display:flex;justify-content:center;margin:1rem 0}
.qr svg{max-width:220px;height:auto;border:1px solid var(--line);padding:.5rem;background:var(--surface)}
.secret{font-size:1.15rem;letter-spacing:.06em;background:var(--surface-2);padding:.6rem .8rem;border-radius:8px;word-break:break-all}
.temp-pw{font-size:1.3rem;margin:.4rem 0;letter-spacing:.04em;user-select:all}
.tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:.9rem;margin-top:1.25rem}
.tile{display:block;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:1rem 1.1rem;text-decoration:none;color:var(--muted);font-size:.93rem;transition:border-color .12s,box-shadow .12s}
.tile strong{display:block;margin-bottom:.25rem;color:var(--ink);font-size:1rem}
a.tile:hover{border-color:var(--ink);color:var(--muted);box-shadow:0 4px 14px rgba(17,24,39,.06)}
.tile.soon{background:transparent;border-style:dashed;color:var(--muted)}
.tile.soon strong{color:var(--muted)}
.center{text-align:center}
.mt{margin-top:1rem}

@@media (max-width:560px){.codes{grid-template-columns:1fr}}
@@media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important} }
</style>
