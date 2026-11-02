<style>
.wo-st{font-size:.75rem;font-weight:700;border-radius:1rem;padding:.1rem .55rem;white-space:nowrap;display:inline-block}
.wo-st.gemeldet{background:var(--danger-bg);color:var(--danger)}
.wo-st.beauftragt{background:var(--brass-bg);color:var(--brass)}
.wo-st.termin{background:var(--info-bg);color:var(--info)}
.wo-st.erledigt{background:var(--ok-bg);color:var(--ok)}
.wo-st.abgerechnet{background:var(--surface-2);color:var(--muted)}
.wo-st.storniert{background:var(--surface-2);color:var(--muted);text-decoration:line-through}
.wo-pr{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--danger)}
.wo-list{list-style:none;margin:0;padding:0}
.wo-list li{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.3rem 1rem;padding:.75rem .2rem;border-top:1px solid var(--line)}
.wo-list li:first-child{border-top:0}
.wo-list a.t{font-weight:650;color:var(--ink)}
.wo-sub{font-size:.85rem;color:var(--muted)}
.wo-steps{display:flex;flex-wrap:wrap;gap:.3rem;margin:.2rem 0 1rem;padding:0;list-style:none}
.wo-steps li{flex:1 1 7rem;text-align:center;font-size:.8rem;font-weight:600;padding:.45rem .3rem;border-radius:8px;background:var(--surface-2);color:var(--muted)}
.wo-steps li.done{background:color-mix(in srgb,var(--brand) 14%,#fff);color:var(--ink)}
.wo-steps li.now{background:var(--brand);color:#fff}
.wo-grid{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:1rem;align-items:start}
.wo-grid > div > .sd-card{margin:0 0 1rem}
.wo-photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(8rem,1fr));gap:.5rem}
.wo-photos figure{margin:0;position:relative}
.wo-photos img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;border:1px solid var(--line);display:block}
.wo-photos figcaption{font-size:.72rem;color:var(--muted)}
.wo-photos form{position:absolute;top:.25rem;right:.25rem}
.wo-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:.6rem .9rem}
.wo-form label{display:block;font-weight:600;font-size:.9rem;margin-bottom:.2rem}
.wo-form input,.wo-form select,.wo-form textarea{width:100%;font:inherit;padding:.5rem .65rem;border:1px solid var(--line-strong);border-radius:8px;background:var(--surface)}
.wo-form .full{grid-column:1/-1}
.wo-notes{list-style:none;margin:0;padding:0}
.wo-notes li{padding:.55rem 0;border-top:1px solid var(--line);font-size:.92rem}
.wo-notes li:first-child{border-top:0}
.wo-notes .h{font-size:.8rem;color:var(--muted)}
.wo-notes .b{white-space:pre-line}
.wo-link{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;background:var(--ok-bg);border:1px solid #bbf7d0;border-radius:8px;padding:.6rem .7rem;margin:.6rem 0}
.wo-link input{flex:1 1 18rem;font:inherit;font-size:.85rem;padding:.4rem .5rem;border:1px solid var(--line-strong);border-radius:6px;background:var(--surface)}
.wo-due{font-size:.75rem;font-weight:700;border-radius:1rem;padding:.08rem .5rem;white-space:nowrap}
.wo-due.ueberfaellig{background:var(--danger-bg);color:var(--danger)}
.wo-due.bald{background:var(--brass-bg);color:var(--brass)}
.wo-due.ok{background:var(--ok-bg);color:var(--ok)}
@media (max-width:900px){.wo-grid{grid-template-columns:minmax(0,1fr)}}
</style>
