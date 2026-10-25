.status-tabs { display:flex; gap:8px; margin:0 0 20px; overflow-x:auto; padding:2px; scrollbar-width:thin; }
.status-tabs__link { display:inline-flex; align-items:center; justify-content:center; min-height:44px; box-sizing:border-box; flex:0 0 auto; border:1px solid #d7dce2; border-radius:10px; padding:0 20px; background:#fff; color:#344054; font-size:14px; font-weight:700; line-height:1; text-decoration:none; white-space:nowrap; }
.status-tabs__link:hover { border-color:#450d07; color:#8f1b12; }
.status-tabs__link:focus-visible { outline:3px solid #2459d3; outline-offset:3px; }
.status-tabs__link[aria-current="page"] { border-color:#000; background:#000; color:#fff; font-weight:900; }
.status-tabs__link[aria-current="page"]::before { content:""; width:6px; height:6px; margin-right:7px; border-radius:999px; background:currentColor; }
