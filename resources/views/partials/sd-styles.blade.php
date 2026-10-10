<style>
.sd-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.75rem;margin-bottom:1rem}
.sd-head h1{margin:0}
.sd-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.sd-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.55rem .95rem;border-radius:8px;border:1px solid var(--ink,#111827);background:var(--ink,#111827);color:#fff;text-decoration:none;font:inherit;font-weight:600;font-size:.95rem;cursor:pointer;line-height:1.2}
.sd-btn:hover{background:#000;color:#fff}
.sd-btn.sec{background:#fff;color:var(--ink,#111827);border-color:var(--line-strong,#d1d5db)}
.sd-btn.sec:hover{border-color:var(--ink,#111827);color:var(--ink,#111827);background:#fff}
.sd-btn.danger{background:#fff;color:#b91c1c;border-color:#fecaca}
.sd-btn.danger:hover{background:#fef2f2}
.sd-btn.small{padding:.32rem .65rem;font-size:.86rem}
.sd-btn[disabled]{opacity:.5;cursor:not-allowed}
.sd-table-wrap{overflow-x:auto}
table.sd{width:100%;border-collapse:collapse}
table.sd th,table.sd td{text-align:left;padding:.6rem .6rem;border-bottom:1px solid var(--line,#e5e7eb);vertical-align:top}
table.sd th{font-size:.76rem;color:var(--muted,#6b7280);text-transform:uppercase;letter-spacing:.05em;font-weight:600;background:transparent}
table.sd tbody tr:hover td{background:#fafafa}
.sd-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:.9rem;margin-bottom:.9rem}
.sd-field label{display:block;font-weight:600;margin-bottom:.25rem;font-size:.93rem}
.sd-field input[type=text],.sd-field input[type=email],.sd-field input[type=tel],.sd-field select,.sd-field textarea{width:100%;box-sizing:border-box;padding:.58rem .7rem;border:1px solid var(--line-strong,#d1d5db);border-radius:8px;font:inherit;background:#fff}
.sd-field textarea{min-height:5.5rem}
.sd-field small{display:block;margin-top:.2rem}
.sd-check{display:flex;gap:.5rem;align-items:center;margin:.4rem 0;font-weight:400}
.sd-card{border:1px solid var(--line,#e5e7eb);border-radius:var(--radius,10px);padding:1.1rem 1.2rem;margin:0 0 1rem;background:#fff;box-shadow:0 1px 2px rgba(17,24,39,.04);overflow-x:auto}
.sd-card h2{margin-top:0;font-size:1.05rem}
.sd-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:.7rem 1rem;border-radius:8px;margin-bottom:1rem}
.sd-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.7rem 1rem;border-radius:8px;margin-bottom:1rem}
.sd-err ul{margin:.3rem 0 0 1.2rem;padding:0}
.sd-muted{color:var(--muted,#6b7280)}
.sd-badge{display:inline-block;padding:.08rem .5rem;border-radius:1rem;background:#f3f4f6;font-size:.8rem;font-weight:600}
dl.sd-dl{display:grid;grid-template-columns:max-content 1fr;gap:.35rem 1rem;margin:0}
dl.sd-dl dt{color:var(--muted,#6b7280)}
dl.sd-dl dd{margin:0}
.sd-inline{display:inline}
@media(max-width:600px){dl.sd-dl{grid-template-columns:1fr}dl.sd-dl dt{margin-top:.5rem}}
</style>
