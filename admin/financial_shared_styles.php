
:root {
    --dark:    #060818; --card:    #0e1726; --border:  #1b2e4b;
    --txt:     #e0e6ed; --muted:   #888ea8; --dark2:   #bfc9d4;
    --primary: #4361ee; --pri-lt:  rgba(67,97,238,.15);
    --success: #00ab55; --suc-lt:  rgba(0,171,85,.15);
    --warning: #e2a03f; --war-lt:  rgba(226,160,63,.15);
    --danger:  #e7515a; --dan-lt:  rgba(231,81,90,.15);
    --info:    #2196f3; --inf-lt:  rgba(33,150,243,.15);
    --purple:  #805dca; --pur-lt:  rgba(128,93,202,.15);
    --nav-h:   68px; --side-w: 255px; --radius: 10px;
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Tajawal',sans-serif; background:var(--dark); color:var(--txt); direction:rtl; font-size:14px; }
a    { text-decoration:none; color:inherit; }
::-webkit-scrollbar { width:4px; } ::-webkit-scrollbar-track { background:var(--card); } ::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }

/* NAV */
.top-nav { position:fixed;top:0;right:0;left:0;height:var(--nav-h);background:var(--card);border-bottom:1px solid var(--border);z-index:200;display:flex;align-items:center;padding:0 24px;gap:14px; }
.nav-brand { display:flex;align-items:center;gap:10px;font-size:19px;font-weight:900; }
.nav-brand .logo-icon { width:34px;height:34px;background:var(--danger);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px; }
.nav-spacer { flex:1; }
.nav-icon { width:36px;height:36px;background:var(--dark);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:17px;transition:all .2s; }
.nav-icon:hover { border-color:var(--primary);color:var(--primary); }
.nav-user { display:flex;align-items:center;gap:9px;padding:5px 10px;border-radius:8px; }
.nav-avatar { width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--danger),var(--purple));display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:14px; }
.nav-uname { font-size:13px;font-weight:700; } .nav-urole { font-size:11px;color:var(--danger); }

/* MAIN */
.main { margin-right:var(--side-w);margin-top:var(--nav-h);padding:28px;min-height:calc(100vh - var(--nav-h)); }
.page-hdr { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:26px;flex-wrap:wrap;gap:12px; }
.page-hdr h1 { font-size:22px;font-weight:900; }
.breadcrumb { display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);margin-top:4px; }
.breadcrumb a { color:var(--primary); } .breadcrumb sep { color:var(--border); }

/* TABLE */
.tcard { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:22px; }
.tcard-hdr { padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px; }
.tcard-hdr h5 { font-size:14px;font-weight:700;margin:0; }
.xato-table { width:100%;border-collapse:collapse; }
.xato-table thead th { background:rgba(27,46,75,.5);color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;border-bottom:1px solid var(--border);white-space:nowrap; }
.xato-table tbody tr { border-bottom:1px solid rgba(27,46,75,.4);transition:background .15s; }
.xato-table tbody tr:hover { background:rgba(27,46,75,.35); }
.xato-table tbody tr:last-child { border-bottom:none; }
.xato-table tbody td { padding:11px 14px;font-size:13px;color:var(--dark2);vertical-align:middle; }

/* STAT CARDS */
.stats-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:16px;margin-bottom:24px; }
.scard { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;position:relative;overflow:hidden;transition:transform .2s; }
.scard:hover { transform:translateY(-2px); }
.scard::after { content:'';position:absolute;top:0;right:0;width:3px;height:100%; }
.scard.cp::after{background:var(--primary)} .scard.cs::after{background:var(--success)}
.scard.cw::after{background:var(--warning)} .scard.cd::after{background:var(--danger)}
.scard.ci::after{background:var(--info)}    .scard.cx::after{background:var(--purple)}
.sc-icon { width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:10px; }
.sc-val { font-size:24px;font-weight:900;line-height:1; }
.sc-lbl { font-size:11px;color:var(--muted);margin-top:3px; }
.sc-sub { font-size:11px;margin-top:5px;padding:3px 8px;border-radius:5px;display:inline-block;font-weight:700; }

/* LEGEND */
.comm-legend { display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px; }
.comm-leg-item { display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px 18px;flex:1;min-width:180px; }
.comm-dot { width:12px;height:12px;border-radius:50%;flex-shrink:0; }
.cl-val { font-size:18px;font-weight:900; }
.cl-lbl { font-size:11px;color:var(--muted); }

/* SEC TITLE */
.sec-title { font-size:16px;font-weight:800;margin:28px 0 14px;display:flex;align-items:center;gap:8px; }
.sec-title i { font-size:20px; }

/* RESPONSIVE */
@media(max-width:900px){ .main{margin-right:0;padding:16px;} }
@media(max-width:600px){ .stats-grid{grid-template-columns:1fr 1fr;} }

/* LIGHT MODE */
body.light-mode {
    --dark:#f0f2f5; --card:#ffffff; --border:#e5e7eb;
    --txt:#1a2332; --muted:#6b7280; --dark2:#374151;
    --pri-lt:rgba(67,97,238,.1); --suc-lt:rgba(0,171,85,.1);
    --war-lt:rgba(226,160,63,.1); --dan-lt:rgba(231,81,90,.1);
    --inf-lt:rgba(33,150,243,.1); --pur-lt:rgba(128,93,202,.1);
}
body.light-mode { background:#f0f2f5!important; color:#1a2332!important; }
body.light-mode .top-nav { background:#fff!important; border-color:#e5e7eb!important; }
body.light-mode .xato-table thead th { background:#f1f5f9!important;color:#374151!important; }
body.light-mode .xato-table tbody td { color:#374151!important; }
body.light-mode .xato-table tbody tr:hover { background:#f8fafc!important; }
body,body.light-mode,.top-nav,[class*="card"],.scard,.tcard { transition:background .3s,border-color .3s,color .2s!important; }

