<style>
.sd-field input[type=date],.sd-field input[type=month],.sd-field input[type=number]{width:100%;box-sizing:border-box;padding:.6rem;border:1px solid var(--line-strong,#d1d5db);border-radius:8px;font:inherit;background:#fff}
.rt-num{text-align:right;white-space:nowrap}
.rt-ok{color:#067647;font-weight:600}
.rt-part{color:#7a4b00;font-weight:600}
.rt-late{color:#b42318;font-weight:600}
.rt-nw{white-space:nowrap}
.rt-soon{color:#4b5563}
.rt-sum td{font-weight:700;border-top:2px solid #c5ccd3}
.rt-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 1rem}
.rt-filter select,.rt-filter input{width:auto;max-width:100%;padding:.45rem;border:1px solid var(--line-strong,#d1d5db);border-radius:8px;font:inherit;background:#fff}
.rt-pay{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
.rt-pay input,.rt-pay select{width:auto;padding:.35rem .45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;background:#fff}
.rt-pay input[name=amount]{width:7rem;text-align:right}
.rt-pay input[type=date]{width:9.5rem}
.rt-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:.8rem;margin:0 0 1rem}
.rt-stat{background:#fff;border:1px solid var(--line,#e5e7eb);border-radius:var(--radius,10px);padding:.8rem 1rem}
.rt-stat strong{display:block;font-size:1.4rem;color:var(--ink,#111827);white-space:nowrap;letter-spacing:-.02em}
.rt-stat span{color:var(--muted,#6b7280);font-size:.86rem}
.rt-warn{background:#fffaf0;border:1px solid #f0d9a8;color:#7a4b00;padding:.7rem 1rem;border-radius:.5rem;margin-bottom:1rem}
tr.rt-target{background:#fff8db}
@media(max-width:700px){
.rt-pay input[type=date]{flex:1 1 8rem;min-width:0;width:auto}
.rt-pay input[name=amount]{flex:1 1 5rem;min-width:0;width:auto}
.rt-pay .sd-btn{flex:0 0 auto}
table.rt-stack thead{display:none}
table.rt-stack,table.rt-stack tbody,table.rt-stack tr,table.rt-stack td{display:block;width:auto;min-width:0}
table.rt-stack tr{border-bottom:1px solid #c5ccd3;padding:.4rem 0}
table.rt-stack td{border:0;padding:.2rem .3rem;text-align:left}
table.rt-stack td[data-l]::before{content:attr(data-l)": ";color:#4b5563;font-size:.85rem}
}
</style>
