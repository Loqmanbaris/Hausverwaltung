<style>
.pl-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(17rem,1fr));gap:1rem}
.pl-card{display:block;background:var(--surface);border:1px solid var(--line);border-radius:8px;overflow:hidden;color:inherit;text-decoration:none}
.pl-card:hover{border-color:var(--primary)}
.pl-card img,.pl-noimg{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;background:#dfe5ec}
.pl-noimg{display:flex;align-items:center;justify-content:center;color:var(--muted)}
.pl-body{padding:.8rem 1rem 1rem}
.pl-body h2{font-size:1.05rem;margin:0 0 .3rem;color:var(--primary)}
.pl-meta{color:var(--muted);font-size:.93rem;margin:0 0 .4rem}
.pl-price{font-weight:700;font-size:1.05rem}
.pl-free{display:inline-block;background:var(--ok-bg);color:var(--ok);border-radius:1rem;padding:.05rem .55rem;font-size:.85rem;font-weight:600;margin-top:.3rem}
.pl-form .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:.7rem}
.pl-form label{display:block;font-weight:600;margin:.6rem 0 .2rem}
.pl-form input[type=text],.pl-form input[type=email],.pl-form input[type=tel],.pl-form input[type=date],.pl-form input[type=number],.pl-form textarea{width:100%;padding:.6rem;border:1px solid var(--line);border-radius:6px;font:inherit;background:var(--surface)}
.pl-form textarea{min-height:8rem}
.pl-form .check{display:flex;gap:.5rem;align-items:flex-start;font-weight:400;margin:.8rem 0}
.pl-form .check input{margin-top:.25rem}
.pl-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
</style>
