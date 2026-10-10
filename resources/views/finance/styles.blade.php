<style>
.fi-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:.8rem;margin:0 0 1rem}
.fi-kpi{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:.9rem 1rem}
.fi-kpi .k{font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600}
.fi-kpi .v{display:block;font-size:1.45rem;font-weight:750;margin:.25rem 0 .1rem}
.fi-kpi .s{font-size:.85rem;color:var(--muted)}
.fi-pos{color:#067647}.fi-neg{color:#b42318}
.fi-table{width:100%;border-collapse:collapse}
.fi-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:left;padding:.5rem .5rem;border-bottom:1px solid var(--line-strong);white-space:nowrap}
.fi-table td{padding:.55rem .5rem;border-bottom:1px solid var(--line);vertical-align:top}
.fi-table td:first-child{min-width:11rem}
.fi-table .n{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.fi-table tr.sum td{font-weight:700;border-top:2px solid var(--line-strong);border-bottom:0}
.fi-table tr.hl td{background:#fff7ed}
.fi-tag{font-size:.74rem;font-weight:700;border-radius:1rem;padding:.08rem .5rem;white-space:nowrap;display:inline-block}
.fi-tag.warn{background:#fff4e5;color:#b54708}
.fi-tag.red{background:#fde8e7;color:#b42318}
.fi-tag.ok{background:#e7f6ec;color:#067647}
.fi-tag.grey{background:#f3f4f6;color:#6b7280}
.fi-dl{display:grid;grid-template-columns:max-content 1fr;gap:.35rem 1.2rem;margin:0}
.fi-dl dt{color:var(--muted)}
.fi-dl dd{margin:0}
.fi-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1rem}
.fi-grid > .sd-card{margin:0}
.fi-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.7rem 1rem}
.fi-form label{display:block;font-weight:600;font-size:.9rem;margin-bottom:.2rem}
.fi-form input,.fi-form select,.fi-form textarea{width:100%;font:inherit;padding:.5rem .65rem;border:1px solid var(--line-strong);border-radius:8px;background:#fff}
.fi-form small{display:block;color:var(--muted);font-size:.8rem;margin-top:.2rem}
.fi-hide{display:none}
@media (max-width:900px){.fi-grid{grid-template-columns:minmax(0,1fr)}}
</style>
