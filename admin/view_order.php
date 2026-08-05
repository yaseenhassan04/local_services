<?php
// admin/view_order.php - عرض مطور للطلب بالـ Dark Theme مع نسبة الأدمن 15%
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

check_login('admin');
$current_page = 'view_order';


if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    set_message("رقم طلب غير صالح.", "danger");
    header("Location: manage_orders.php");
    exit;
}

$order_id = $_GET['id'];

try {
    $sql = "SELECT
                o.*,
                s.title AS service_title,
                s.description AS service_description,
                s.price AS service_price,
                c.name AS category_name,
                u_client.full_name AS client_name,
                u_client.email AS client_email,
                u_client.phone AS client_phone,
                u_provider.full_name AS provider_name,
                u_provider.email AS provider_email,
                u_provider.phone AS provider_phone
            FROM orders o
            JOIN services s ON o.service_id = s.id
            JOIN users u_client ON o.client_id = u_client.id
            JOIN users u_provider ON o.provider_id = u_provider.id
            LEFT JOIN categories c ON s.category_id = c.id
            WHERE o.id = :order_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        set_message("الطلب غير موجود.", "danger");
        header("Location: manage_orders.php");
        exit;
    }
} catch (PDOException $e) {
    set_message("خطأ فني: " . $e->getMessage(), "danger");
    header("Location: manage_orders.php");
    exit;
}

// ── حسابات العمولة ─────────────────────────────────
$ADMIN_COMMISSION_RATE = 0.15; // 15%
$order_amount          = (float)($order['amount'] ?? 0);
$admin_commission      = $order_amount * $ADMIN_COMMISSION_RATE;
$provider_earning      = $order_amount - $admin_commission;

// ── إحصائيات سريعة من قاعدة البيانات ───────────────
try {
    $fin = $pdo->query("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status='completed' THEN COALESCE(amount,0) ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_count
        FROM orders
    ")->fetch(PDO::FETCH_ASSOC);

    $total_revenue    = (float)($fin['total_revenue'] ?? 0);
    $admin_total_cut  = $total_revenue * $ADMIN_COMMISSION_RATE;
    $provider_total   = $total_revenue - $admin_total_cut;
} catch (PDOException $e) {
    $total_revenue = $admin_total_cut = $provider_total = 0;
    $fin = ['total_orders'=>0,'pending_count'=>0,'completed_count'=>0];
}

if (!function_exists('get_status_display')) {
    function get_status_display($s) {
        return ['pending'=>'معلق','processing'=>'قيد المعالجة','in_progress'=>'قيد التنفيذ',
                'completed'=>'مكتمل','cancelled'=>'ملغي'][$s] ?? $s;
    }
}
function status_css($s) {
    return ['pending'=>'warning','processing'=>'purple','in_progress'=>'info',
            'completed'=>'success','cancelled'=>'danger'][$s] ?? 'muted';
}

$admin_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تفاصيل الطلب #<?= $order['id'] ?> | لوحة المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
/* ============================================================
   XATO DARK — view_order.php
============================================================ */
:root {
    --dark:    #060818;
    --card:    #0e1726;
    --border:  #1b2e4b;
    --txt:     #e0e6ed;
    --muted:   #888ea8;
    --dark2:   #bfc9d4;
    --primary: #4361ee;
    --pri-lt:  rgba(67,97,238,.15);
    --success: #00ab55;
    --suc-lt:  rgba(0,171,85,.15);
    --warning: #e2a03f;
    --war-lt:  rgba(226,160,63,.15);
    --danger:  #e7515a;
    --dan-lt:  rgba(231,81,90,.15);
    --info:    #2196f3;
    --inf-lt:  rgba(33,150,243,.15);
    --purple:  #805dca;
    --pur-lt:  rgba(128,93,202,.15);
    --nav-h:   68px;
    --side-w:  255px;
    --radius:  10px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Tajawal', sans-serif; background: var(--dark); color: var(--txt); direction: rtl; min-height: 100vh; font-size: 14px; }
a    { text-decoration: none; color: inherit; }
::-webkit-scrollbar           { width: 4px; }
::-webkit-scrollbar-track     { background: var(--card); }
::-webkit-scrollbar-thumb     { background: var(--border); border-radius: 4px; }

/* ── TOP NAV ──────────────────────────────────────── */
.top-nav {
    position: fixed; top: 0; right: 0; left: 0;
    height: var(--nav-h); background: var(--card);
    border-bottom: 1px solid var(--border);
    z-index: 200; display: flex; align-items: center;
    padding: 0 24px; gap: 14px;
}
.nav-brand { display: flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 900; }
.nav-brand .logo-icon { width: 34px; height: 34px; background: var(--danger); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 17px; color: #fff; }
.nav-spacer { flex: 1; }
.nav-icon { width: 36px; height: 36px; background: var(--dark); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 17px; transition: all .2s; cursor: pointer; }
.nav-icon:hover { border-color: var(--primary); color: var(--primary); }
.nav-user { display: flex; align-items: center; gap: 9px; padding: 5px 10px; border-radius: 8px; transition: background .2s; cursor: pointer; }
.nav-user:hover { background: var(--pri-lt); }
.nav-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg,var(--danger),var(--purple)); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: #fff; }
.nav-uname { font-size: 13px; font-weight: 700; }
.nav-urole { font-size: 11px; color: var(--danger); }

/* ── SIDEBAR ──────────────────────────────────────── */
.sidebar {
    position: fixed; top: var(--nav-h); right: 0;
    width: var(--side-w); height: calc(100vh - var(--nav-h));
    background: var(--card); border-left: 1px solid var(--border);
    overflow-y: auto; z-index: 100; padding: 14px 10px;
    transition: transform .3s;
}
.sidebar.collapsed { transform: translateX(var(--side-w)); }
.profile-mini { padding: 16px 12px; margin-bottom: 6px; }
.profile-mini .av { width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg,var(--danger),var(--purple)); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: #fff; margin: 0 auto 9px; }
.profile-mini .pname { font-size: 14px; font-weight: 700; text-align: center; }
.profile-mini .pemail { font-size: 11px; color: var(--muted); text-align: center; }
.side-sep { font-size: 10px; font-weight: 800; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; padding: 14px 12px 5px; }
.side-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: var(--muted); font-size: 13px; font-weight: 600; transition: all .2s; margin-bottom: 2px; }
.side-link i { font-size: 17px; min-width: 20px; }
.side-link:hover { background: var(--pri-lt); color: var(--primary); }
.side-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 14px rgba(67,97,238,.35); }
.side-link.fin-link:hover { background: var(--suc-lt); color: var(--success); }
.side-link.fin-link.active { background: var(--success); color: #fff; box-shadow: 0 4px 14px rgba(0,171,85,.35); }
.side-link.danger-link { color: var(--danger); }
.side-link.danger-link:hover { background: var(--dan-lt); }
.side-badge { margin-right: auto; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 10px; }

/* ── MAIN ─────────────────────────────────────────── */
.main { margin-right: var(--side-w); margin-top: var(--nav-h); padding: 28px; min-height: calc(100vh - var(--nav-h)); transition: margin-right .3s; }
.main.expanded { margin-right: 0; }

/* ── PAGE HEADER ──────────────────────────────────── */
.page-hdr { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 26px; flex-wrap: wrap; gap: 12px; }
.page-hdr h1 { font-size: 21px; font-weight: 900; }
.page-hdr h1 span { color: var(--primary); }
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); margin-top: 4px; }
.breadcrumb a { color: var(--primary); }
.breadcrumb sep { color: var(--border); }
.btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; color: var(--dark2); font-size: 13px; font-weight: 700; transition: all .2s; }
.btn-back:hover { border-color: var(--primary); color: var(--primary); }

/* ── FINANCIAL QUICK STATS ────────────────────────── */
.fin-strip { display: grid; grid-template-columns: repeat(auto-fill,minmax(170px,1fr)); gap: 14px; margin-bottom: 26px; }
.fin-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; position: relative; overflow: hidden; transition: transform .2s, box-shadow .2s; }
.fin-card:hover { transform: translateY(-2px); box-shadow: 0 6px 22px rgba(0,0,0,.4); }
.fin-card::before { content: ''; position: absolute; top: 0; right: 0; width: 3px; height: 100%; border-radius: 0 var(--radius) var(--radius) 0; }
.fin-card.c-primary::before { background: var(--primary); }
.fin-card.c-success::before { background: var(--success); }
.fin-card.c-warning::before { background: var(--warning); }
.fin-card.c-purple::before  { background: var(--purple); }
.fin-card.c-info::before    { background: var(--info); }
.fin-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 10px; }
.fin-val  { font-size: 22px; font-weight: 900; line-height: 1; }
.fin-lbl  { font-size: 11px; color: var(--muted); margin-top: 3px; }
.fin-sub  { font-size: 11px; margin-top: 5px; font-weight: 700; }

/* ── TWO COLUMN ───────────────────────────────────── */
.two-col { display: grid; grid-template-columns: 1fr 340px; gap: 22px; align-items: start; }

/* ── CARD ─────────────────────────────────────────── */
.xcard { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 20px; }
.xcard:last-child { margin-bottom: 0; }
.xcard-hdr { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 9px; }
.xcard-hdr h5 { font-size: 14px; font-weight: 700; margin: 0; }
.xcard-hdr i  { font-size: 18px; }
.xcard-body { padding: 20px; }

/* ── STATUS BADGE ─────────────────────────────────── */
.status-big { display: inline-flex; align-items: center; gap: 8px; padding: 7px 16px; border-radius: 30px; font-size: 13px; font-weight: 800; }
.s-warning { background: var(--war-lt); color: var(--warning); }
.s-success { background: var(--suc-lt); color: var(--success); }
.s-danger  { background: var(--dan-lt); color: var(--danger); }
.s-info    { background: var(--inf-lt); color: var(--info); }
.s-purple  { background: var(--pur-lt); color: var(--purple); }
.s-muted   { background: rgba(136,142,168,.1); color: var(--muted); }

/* ── ORDER META GRID ──────────────────────────────── */
.meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 16px; }
.meta-item label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 4px; }
.meta-item span  { font-size: 13px; color: var(--dark2); font-weight: 600; }

/* ── COMMISSION BOX ───────────────────────────────── */
.commission-box { background: linear-gradient(135deg, rgba(67,97,238,.08), rgba(0,171,85,.08)); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; margin-top: 16px; }
.commission-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid rgba(27,46,75,.5); font-size: 13px; }
.commission-row:last-child { border-bottom: none; padding-bottom: 0; }
.commission-row .lbl { color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }
.commission-row .val { font-weight: 800; }
.comm-total { background: var(--pri-lt); border-radius: 8px; padding: 12px 14px; margin-top: 12px; display: flex; justify-content: space-between; align-items: center; }
.comm-total .lbl { font-size: 12px; color: var(--primary); font-weight: 700; }
.comm-total .val { font-size: 18px; font-weight: 900; color: var(--primary); }

/* ── PAYMENT PROOF ────────────────────────────────── */
.proof-empty { text-align: center; padding: 30px; color: var(--muted); }
.proof-empty i { font-size: 36px; margin-bottom: 8px; display: block; }
.proof-box { background: var(--dark); border: 1px solid var(--border); border-radius: 8px; padding: 14px; }

/* ── PEOPLE CARD ──────────────────────────────────── */
.person-card { text-align: center; padding: 20px 16px; }
.person-av { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: #fff; margin: 0 auto 10px; }
.person-name { font-size: 14px; font-weight: 800; }
.person-role { font-size: 11px; margin-bottom: 12px; font-weight: 700; }
.person-info { font-size: 12px; color: var(--dark2); }
.person-info div { padding: 5px 0; display: flex; align-items: center; gap: 7px; justify-content: center; }
.person-info i { color: var(--muted); }

/* ── ACTION PANEL ─────────────────────────────────── */
.select-status { width: 100%; padding: 10px 13px; background: var(--dark); border: 1px solid var(--border); border-radius: 8px; color: var(--txt); font-family: 'Tajawal',sans-serif; font-size: 13px; outline: none; transition: border-color .2s; }
.select-status:focus { border-color: var(--primary); }
.select-status option { background: var(--card); }
.btn-update { width: 100%; padding: 11px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-family: 'Tajawal',sans-serif; font-size: 14px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 7px; transition: all .2s; margin-top: 12px; }
.btn-update:hover { background: #3a56d4; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(67,97,238,.4); }
.btn-print { width: 100%; padding: 10px; background: transparent; border: 1px solid var(--border); color: var(--dark2); border-radius: 8px; font-family: 'Tajawal',sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 7px; transition: all .2s; margin-top: 8px; }
.btn-print:hover { border-color: var(--muted); color: var(--txt); }

/* ── ALERTS ───────────────────────────────────────── */
.xalert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; }
.xalert-success { background: var(--suc-lt); color: var(--success); border: 1px solid rgba(0,171,85,.25); }
.xalert-danger  { background: var(--dan-lt); color: var(--danger);  border: 1px solid rgba(231,81,90,.25); }
.xalert-warning { background: var(--war-lt); color: var(--warning); border: 1px solid rgba(226,160,63,.25); }

/* ── FINANCIAL REPORT SECTION ─────────────────────── */
.report-section { margin-top: 30px; }
.report-section-title { font-size: 16px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.report-section-title i { color: var(--success); font-size: 20px; }
.report-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(200px,1fr)); gap: 16px; }
.report-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
.report-card .r-icon { font-size: 26px; margin-bottom: 10px; }
.report-card .r-val  { font-size: 26px; font-weight: 900; }
.report-card .r-lbl  { font-size: 12px; color: var(--muted); margin-top: 3px; }
.report-card .r-note { font-size: 11px; margin-top: 8px; padding: 4px 9px; border-radius: 6px; display: inline-block; font-weight: 700; }

/* ── RESPONSIVE ───────────────────────────────────── */
@media (max-width: 1100px) { .two-col { grid-template-columns: 1fr; } }
@media (max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-right: 0; padding: 16px; }
}
@media (max-width: 600px) {
    .fin-strip { grid-template-columns: 1fr 1fr; }
    .meta-grid { grid-template-columns: 1fr; }
    .report-grid { grid-template-columns: 1fr 1fr; }
}

/* ══ LIGHT MODE ══════════════════════════════════════ */
body.light-mode {
    --dark:   #f0f2f5;
    --card:   #ffffff;
    --border: #e5e7eb;
    --txt:    #1a2332;
    --muted:  #6b7280;
    --dark2:  #374151;
    --pri-lt: rgba(67,97,238,.1);
    --suc-lt: rgba(0,171,85,.1);
    --war-lt: rgba(226,160,63,.1);
    --dan-lt: rgba(231,81,90,.1);
    --inf-lt: rgba(33,150,243,.1);
    --pur-lt: rgba(128,93,202,.1);
}
body.light-mode,
body.light-mode .top-nav,
body.light-mode .sidebar,
body.light-mode nav.top-nav { background-color: #ffffff; }
body.light-mode .top-nav,
body.light-mode nav.top-nav { border-bottom-color: #e5e7eb; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
body.light-mode .sidebar     { border-left-color: #e5e7eb; }
body.light-mode .xcard,
body.light-mode .fin-card,
body.light-mode .report-card,
body.light-mode .card,
body.light-mode [class*="-card"] { background:#ffffff; border-color:#e5e7eb; }
body.light-mode .nav-icon    { background:#f3f4f6; border-color:#e5e7eb; color:#6b7280; }
body.light-mode .side-link   { color:#6b7280; }
body.light-mode .side-link:hover { background:rgba(67,97,238,.08); color:var(--primary); }
body.light-mode .side-link.active { background:var(--primary); color:#fff; }
body.light-mode .select-status,
body.light-mode select,
body.light-mode input,
body.light-mode textarea     { background:#f9fafb; border-color:#e5e7eb; color:#1a2332; }
body.light-mode table thead th { background:#1a2332 !important; color:#fff !important; }
body.light-mode .commission-box { background:linear-gradient(135deg,rgba(67,97,238,.05),rgba(0,171,85,.05)); }
body.light-mode .commission-row { border-bottom-color:#e5e7eb; }
body.light-mode .proof-box   { background:#f9fafb; border-color:#e5e7eb; }
body, .top-nav, nav.top-nav, .sidebar, .xcard, .fin-card, .report-card,
.nav-icon, .commission-box, .card { transition: background .3s, border-color .3s, color .2s !important; }
</style>
<style>
/* ── Sidebar bridge vars ── */
:root {
  --sidebar-bg:    var(--card-bg, var(--card, #0e1726));
  --card-border:   var(--border, #1b2e4b);
  --sidebar-width: var(--side-w, var(--sidebar-width, 255px));
  --header-height: var(--nav-h, var(--header-height, 68px));
}

/* ══ LIGHT MODE — Sidebar Fix ══ */
body.light-mode {
    --dark-bg:    #f0f4f8;
    --sidebar-bg: #ffffff;
    --card-bg:    #ffffff;
    --card-border:#e5e7eb;
    --header-bg:  #ffffff;
    --text-primary:#1a2332;
    --text-muted: #6b7280;
    --text-dark:  #374151;
    --primary-light: rgba(67,97,238,.1);
    --success-light: rgba(0,171,85,.1);
    --warning-light: rgba(226,160,63,.1);
    --danger-light:  rgba(231,81,90,.1);
    --info-light:    rgba(33,150,243,.1);
    --purple-light:  rgba(128,93,202,.1);
}
body.light-mode                   { background: #f0f4f8 !important; color: #1a2332 !important; }
body.light-mode .app-sidebar      { background: #ffffff !important; border-color: #e5e7eb !important; box-shadow: -2px 0 12px rgba(0,0,0,.06) !important; }
body.light-mode .app-header,
body.light-mode header.app-header { background: #ffffff !important; border-bottom-color: #e5e7eb !important; box-shadow: 0 2px 10px rgba(0,0,0,.07) !important; }
body.light-mode .sidebar-section-title { color: #9ca3af !important; }
body.light-mode .sidebar-menu a   { color: #6b7280 !important; }
body.light-mode .sidebar-menu a:hover { background: rgba(67,97,238,.08) !important; color: #4361ee !important; }
body.light-mode .sidebar-menu a.active { background: #4361ee !important; color: #fff !important; }
body.light-mode .sidebar-menu a.logout-link { color: #e7515a !important; }
body.light-mode .sidebar-menu a.logout-link:hover { background: rgba(231,81,90,.08) !important; }
body.light-mode .user-avatar-wrap,
body.light-mode .profile-mini     { border-color: #e5e7eb !important; }
body.light-mode .profile-mini .pname  { color: #1a2332 !important; }
body.light-mode .profile-mini .pemail { color: #6b7280 !important; }

/* Cards & Content */
body.light-mode .xato-card,
body.light-mode .stat-card,
body.light-mode .nx-card,
body.light-mode [class*="card"]   { background: #ffffff !important; border-color: #e5e7eb !important; }
body.light-mode .header-logo span,
body.light-mode .logo-text        { color: #1a2332 !important; }
body.light-mode .header-toggle,
body.light-mode .hdr-toggle       { color: #6b7280 !important; }
body.light-mode .header-user .user-name { color: #1a2332 !important; }
body.light-mode .header-user .user-role { color: #4361ee !important; }
body.light-mode ::-webkit-scrollbar-track { background: #f1f5f9 !important; }
body.light-mode ::-webkit-scrollbar-thumb { background: #d1d5db !important; }

/* Tables */
body.light-mode table thead th    { background: #f1f5f9 !important; color: #374151 !important; border-color: #e5e7eb !important; }
body.light-mode table tbody td    { color: #374151 !important; border-color: #f1f5f9 !important; }
body.light-mode table tbody tr:hover { background: #f8fafc !important; }

/* Smooth transition */
.app-sidebar, .app-header, header.app-header,
.sidebar-menu a, [class*="card"], body {
    transition: background .25s ease, border-color .25s ease, color .2s ease, box-shadow .25s ease !important;
}
</style>
</head>
<body>

<!-- ══ TOP NAV ══════════════════════════════════════ -->
<nav class="top-nav">
    <div class="nav-brand">
        <div class="logo-icon"><i class="las la-shield-alt"></i></div>
        خدماتي
    </div>
    <div class="nav-spacer"></div>
    <button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
            class="nav-icon" style="cursor:pointer;">
        <i class="las la-sun" id="themeIcon"></i>
    </button>

    <a href="/local_services/index.php" class="nav-icon" title="الموقع الرئيسي"><i class="las la-external-link-alt"></i></a>
    <a href="/local_services/logout.php" class="nav-icon" style="color:var(--danger);" title="خروج"><i class="las la-sign-out-alt"></i></a>
    <?php $me = getCurrentUser(); if ($me): ?>
    <div class="nav-user">
        <div class="nav-avatar"><?= mb_substr($me['full_name'],0,1) ?></div>
        <div>
            <div class="nav-uname"><?= htmlspecialchars($me['full_name']) ?></div>
            <div class="nav-urole"><i class="las la-crown"></i> مدير النظام</div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<!-- ══ SIDEBAR ══════════════════════════════════════ -->
<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<!-- ══ MAIN ══════════════════════════════════════════ -->
<main class="main" id="appMain">

    <!-- Page Header -->
    <div class="page-hdr">
        <div>
            <h1><i class="las la-file-invoice-dollar" style="color:var(--primary);margin-left:8px;font-size:24px;"></i>
                تفاصيل الطلب <span>#<?= $order['id'] ?></span>
            </h1>
            <div class="breadcrumb">
                <a href="/local_services/admin/dashboard.php">الرئيسية</a>
                <sep>/</sep>
                <a href="/local_services/admin/manage_orders.php">الطلبات</a>
                <sep>/</sep>
                <span>طلب #<?= $order['id'] ?></span>
            </div>
        </div>
        <a href="manage_orders.php" class="btn-back">
            <i class="las la-arrow-right"></i> العودة للقائمة
        </a>
    </div>

    <!-- Alerts -->
    <?php
    $flash = $_SESSION['flash_message'] ?? null;
    if ($flash): unset($_SESSION['flash_message']);
        $ftype = $_SESSION['flash_type'] ?? 'success'; unset($_SESSION['flash_type']);
        $ficons = ['success'=>'la-check-circle','danger'=>'la-times-circle','warning'=>'la-exclamation-triangle'];
    ?>
    <div class="xalert xalert-<?= htmlspecialchars($ftype) ?>">
        <i class="las <?= $ficons[$ftype] ?? 'la-info-circle' ?>" style="font-size:18px;flex-shrink:0;"></i>
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>
    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- Financial Quick Strip -->
    <div class="fin-strip">
        <!-- مبلغ هذا الطلب -->
        <div class="fin-card c-primary">
            <div class="fin-icon" style="background:var(--pri-lt);color:var(--primary);"><i class="las la-file-invoice-dollar"></i></div>
            <div class="fin-val" style="color:var(--primary);"><?= number_format($order_amount,2) ?> ₪</div>
            <div class="fin-lbl">قيمة هذا الطلب</div>
        </div>
        <!-- عمولة الأدمن لهذا الطلب -->
        <div class="fin-card c-success">
            <div class="fin-icon" style="background:var(--suc-lt);color:var(--success);"><i class="las la-percentage"></i></div>
            <div class="fin-val" style="color:var(--success);"><?= number_format($admin_commission,2) ?> ₪</div>
            <div class="fin-lbl">عمولة الأدمن (15%)</div>
        </div>
        <!-- صافي المزود -->
        <div class="fin-card c-warning">
            <div class="fin-icon" style="background:var(--war-lt);color:var(--warning);"><i class="las la-user-tie"></i></div>
            <div class="fin-val" style="color:var(--warning);"><?= number_format($provider_earning,2) ?> ₪</div>
            <div class="fin-lbl">صافي المزود (85%)</div>
        </div>
        <!-- إجمالي إيرادات المنصة -->
        <div class="fin-card c-purple">
            <div class="fin-icon" style="background:var(--pur-lt);color:var(--purple);"><i class="las la-chart-line"></i></div>
            <div class="fin-val" style="color:var(--purple);"><?= number_format($total_revenue,2) ?></div>
            <div class="fin-lbl">إجمالي إيرادات المنصة</div>
        </div>
        <!-- إجمالي عمولة الأدمن -->
        <div class="fin-card c-info">
            <div class="fin-icon" style="background:var(--inf-lt);color:var(--info);"><i class="las la-hand-holding-usd"></i></div>
            <div class="fin-val" style="color:var(--info);"><?= number_format($admin_total_cut,2) ?></div>
            <div class="fin-lbl">مجموع عمولات الأدمن</div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="two-col">

        <!-- ══ عمود اليمين: تفاصيل الطلب ══ -->
        <div>

            <!-- حالة الطلب والدفع -->
            <div class="xcard">
                <div class="xcard-hdr">
                    <i class="las la-info-circle" style="color:var(--primary);"></i>
                    <h5>حالة الطلب والدفع</h5>
                </div>
                <div class="xcard-body">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <div>
                            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px;">حالة الطلب</div>
                            <?php $sc = status_css($order['status']); ?>
                            <span class="status-big s-<?= $sc ?>">
                                <i class="las la-circle" style="font-size:9px;"></i>
                                <?= get_status_display($order['status']) ?>
                            </span>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px;">حالة الدفع</div>
                            <span class="status-big s-warning">
                                <i class="las la-credit-card" style="font-size:14px;"></i>
                                <?= htmlspecialchars($order['payment_status'] ?? 'غير محدد') ?>
                            </span>
                        </div>
                        <div style="text-align:left;">
                            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px;">رقم الطلب</div>
                            <span style="font-size:20px;font-weight:900;color:var(--primary);">#<?= $order['id'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تفاصيل الخدمة -->
            <div class="xcard">
                <div class="xcard-hdr">
                    <i class="las la-concierge-bell" style="color:var(--warning);"></i>
                    <h5>تفاصيل الخدمة المطلوبة</h5>
                </div>
                <div class="xcard-body">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
                        <div>
                            <div style="font-size:17px;font-weight:800;margin-bottom:5px;"><?= htmlspecialchars($order['service_title']) ?></div>
                            <span style="font-size:11px;background:var(--pri-lt);color:var(--primary);padding:3px 10px;border-radius:20px;font-weight:700;">
                                <?= htmlspecialchars($order['category_name'] ?: 'غير مصنف') ?>
                            </span>
                        </div>
                        <div style="font-size:24px;font-weight:900;color:var(--success);white-space:nowrap;"><?= number_format($order_amount,2) ?> ₪</div>
                    </div>
                    <p style="font-size:13px;color:var(--muted);line-height:1.7;margin-bottom:16px;"><?= nl2br(htmlspecialchars($order['service_description'])) ?></p>
                    <div class="meta-grid">
                        <div class="meta-item">
                            <label>تاريخ الطلب</label>
                            <span><i class="las la-calendar" style="color:var(--muted);margin-left:4px;"></i><?= $order['order_date'] ?></span>
                        </div>
                        <div class="meta-item">
                            <label>سعر الخدمة الأصلي</label>
                            <span><?= number_format((float)$order['service_price'],2) ?> ₪</span>
                        </div>
                        <div class="meta-item">
                            <label>التصنيف</label>
                            <span><?= htmlspecialchars($order['category_name'] ?: '—') ?></span>
                        </div>
                        <div class="meta-item">
                            <label>المبلغ المدفوع</label>
                            <span style="color:var(--success);font-weight:800;"><?= number_format($order_amount,2) ?> ₪</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تفصيل العمولة والأرباح -->
            <div class="xcard">
                <div class="xcard-hdr">
                    <i class="las la-calculator" style="color:var(--success);"></i>
                    <h5>تفصيل العمولة وتوزيع الأرباح</h5>
                    <span style="margin-right:auto;font-size:11px;background:var(--suc-lt);color:var(--success);padding:3px 9px;border-radius:6px;font-weight:800;">نسبة الأدمن: 15%</span>
                </div>
                <div class="xcard-body">
                    <div class="commission-box">
                        <div class="commission-row">
                            <span class="lbl"><i class="las la-money-bill-wave"></i> المبلغ الإجمالي للطلب</span>
                            <span class="val"><?= number_format($order_amount,2) ?> ₪</span>
                        </div>
                        <div class="commission-row">
                            <span class="lbl"><i class="las la-user-shield" style="color:var(--success);"></i> عمولة الأدمن <span style="background:var(--suc-lt);color:var(--success);padding:1px 7px;border-radius:5px;font-size:10px;">15%</span></span>
                            <span class="val" style="color:var(--success);">+ <?= number_format($admin_commission,2) ?> ₪</span>
                        </div>
                        <div class="commission-row">
                            <span class="lbl"><i class="las la-user-tie" style="color:var(--warning);"></i> صافي المزود <span style="background:var(--war-lt);color:var(--warning);padding:1px 7px;border-radius:5px;font-size:10px;">85%</span></span>
                            <span class="val" style="color:var(--warning);"><?= number_format($provider_earning,2) ?> ₪</span>
                        </div>
                    </div>
                    <div class="comm-total">
                        <span class="lbl"><i class="las la-coins"></i> إجمالي عمولات الأدمن من جميع الطلبات</span>
                        <span class="val"><?= number_format($admin_total_cut,2) ?> ₪</span>
                    </div>
                </div>
            </div>

            <!-- إثبات الدفع -->
            <div class="xcard">
                <div class="xcard-hdr">
                    <i class="las la-camera" style="color:var(--info);"></i>
                    <h5>إثبات الدفع والمرفقات</h5>
                </div>
                <div class="xcard-body">
                    <?php if (!empty($order['payment_proof'])): ?>
                        <div class="proof-box">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <i class="las la-check-circle" style="color:var(--success);font-size:18px;"></i>
                                <span style="font-size:13px;font-weight:700;color:var(--success);">تم إرفاق إثبات الدفع</span>
                            </div>
                            <p style="font-size:13px;color:var(--dark2);line-height:1.7;"><?= htmlspecialchars($order['payment_proof']) ?></p>
                            <p style="font-size:11px;color:var(--muted);margin-top:8px;"><i class="las la-info-circle"></i> يمكنك مراجعة التحويل عبر المحفظة بناءً على البيانات أعلاه.</p>
                        </div>
                    <?php else: ?>
                        <div class="proof-empty">
                            <i class="las la-times-circle" style="color:var(--danger);"></i>
                            <p style="font-size:14px;font-weight:700;margin-bottom:4px;">لا يوجد إثبات دفع</p>
                            <p style="font-size:12px;">لم يرفع العميل إثبات دفع لهذا الطلب بعد.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ══ عمود اليسار: أطراف الطلب والإجراءات ══ -->
        <div>

            <!-- العميل -->
            <div class="xcard">
                <div class="xcard-hdr">
                    <i class="las la-user" style="color:var(--info);"></i>
                    <h5>صاحب الطلب (العميل)</h5>
                </div>
                <div class="person-card">
                    <div class="person-av" style="background:linear-gradient(135deg,var(--info),var(--primary));">
                        <?= mb_substr($order['client_name'],0,1) ?>
                    </div>
                    <div class="person-name"><?= htmlspecialchars($order['client_name']) ?></div>
                    <div class="person-role" style="color:var(--info);">عميل</div>
                    <div class="person-info">
                        <div><i class="las la-envelope"></i><?= htmlspecialchars($order['client_email']) ?></div>
                        <div><i class="las la-phone"></i><?= htmlspecialchars($order['client_phone'] ?? '—') ?></div>
                    </div>
                </div>
            </div>

            <!-- المزود -->
            <div class="xcard">
                <div class="xcard-hdr">
                    <i class="las la-user-tie" style="color:var(--warning);"></i>
                    <h5>مزود الخدمة</h5>
                </div>
                <div class="person-card">
                    <div class="person-av" style="background:linear-gradient(135deg,var(--warning),var(--danger));">
                        <?= mb_substr($order['provider_name'],0,1) ?>
                    </div>
                    <div class="person-name"><?= htmlspecialchars($order['provider_name']) ?></div>
                    <div class="person-role" style="color:var(--warning);">مزود خدمة</div>
                    <div class="person-info">
                        <div><i class="las la-envelope"></i><?= htmlspecialchars($order['provider_email']) ?></div>
                        <div><i class="las la-phone"></i><?= htmlspecialchars($order['provider_phone'] ?? '—') ?></div>
                    </div>
                    <!-- أرباح المزود من هذا الطلب -->
                    <div style="background:var(--war-lt);border-radius:8px;padding:10px;margin-top:12px;">
                        <div style="font-size:11px;color:var(--warning);font-weight:700;margin-bottom:3px;">أرباحه من هذا الطلب</div>
                        <div style="font-size:20px;font-weight:900;color:var(--warning);"><?= number_format($provider_earning,2) ?> ₪</div>
                        <div style="font-size:11px;color:var(--muted);">بعد خصم عمولة الأدمن 15%</div>
                    </div>
                </div>
            </div>

            <!-- لوحة تحكم الطلب -->
            <div class="xcard">
                <div class="xcard-hdr" style="background:rgba(27,46,75,.4);">
                    <i class="las la-user-shield" style="color:var(--danger);"></i>
                    <h5>إجراءات الإدارة</h5>
                </div>
                <div class="xcard-body">
                    <form action="actions/update_order_status.php" method="POST">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <div style="margin-bottom:12px;">
                            <label style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">تعديل حالة الطلب</label>
                            <select name="new_status" class="select-status">
                                <option value="pending"     <?= $order['status']=='pending'    ?'selected':''?>>⏳ قيد الانتظار</option>
                                <option value="processing"  <?= $order['status']=='processing' ?'selected':''?>>🔄 قيد المعالجة</option>
                                <option value="in_progress" <?= $order['status']=='in_progress'?'selected':''?>>⚙️ قيد التنفيذ</option>
                                <option value="completed"   <?= $order['status']=='completed'  ?'selected':''?>>✅ مكتمل</option>
                                <option value="cancelled"   <?= $order['status']=='cancelled'  ?'selected':''?>>❌ ملغى</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-update">
                            <i class="las la-save"></i> تحديث الحالة
                        </button>
                    </form>
                    <hr style="border:none;border-top:1px solid var(--border);margin:14px 0;">
                   <a href="print_invoice.php?id=<?= $order['id'] ?>" 
   target="_blank" 
   class="btn-print" 
   style="display:flex;">
    <i class="las la-file-invoice"></i> طباعة الفاتورة الرسمية
</a>
                    <a href="manage_orders.php" class="btn-print" style="display:flex;margin-top:8px;">
                        <i class="las la-list"></i> كل الطلبات
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- ══ قسم التقارير المالية الموسعة ══ -->
    <div class="report-section">
        <div class="report-section-title">
            <i class="las la-chart-bar"></i>
            نظرة مالية على المنصة
        </div>
        <div class="report-grid">
            <div class="report-card" style="border-right:3px solid var(--primary);">
                <div class="r-icon" style="color:var(--primary);">📋</div>
                <div class="r-val" style="color:var(--primary);"><?= number_format($fin['total_orders'] ?? 0) ?></div>
                <div class="r-lbl">إجمالي الطلبات</div>
                <span class="r-note" style="background:var(--pri-lt);color:var(--primary);">
                    <?= $fin['pending_count'] ?> معلق · <?= $fin['completed_count'] ?> مكتمل
                </span>
            </div>
            <div class="report-card" style="border-right:3px solid var(--success);">
                <div class="r-icon" style="color:var(--success);">💰</div>
                <div class="r-val" style="color:var(--success);"><?= number_format($total_revenue,2) ?></div>
                <div class="r-lbl">إجمالي الإيرادات (₪)</div>
                <span class="r-note" style="background:var(--suc-lt);color:var(--success);">من الطلبات المكتملة</span>
            </div>
            <div class="report-card" style="border-right:3px solid var(--warning);">
                <div class="r-icon" style="color:var(--warning);">🏦</div>
                <div class="r-val" style="color:var(--warning);"><?= number_format($admin_total_cut,2) ?></div>
                <div class="r-lbl">عمولات الأدمن الإجمالية (₪)</div>
                <span class="r-note" style="background:var(--war-lt);color:var(--warning);">نسبة 15% من كل طلب</span>
            </div>
            <div class="report-card" style="border-right:3px solid var(--info);">
                <div class="r-icon" style="color:var(--info);">👷</div>
                <div class="r-val" style="color:var(--info);"><?= number_format($provider_total,2) ?></div>
                <div class="r-lbl">مجموع أرباح المزودين (₪)</div>
                <span class="r-note" style="background:var(--inf-lt);color:var(--info);">85% من إجمالي المبيعات</span>
            </div>
        </div>
    </div>

</main>

<script>
(function(){
    const sidebar  = document.getElementById('appSidebar');
    const mainArea = document.getElementById('appMain');
    // Toggle للموبايل لو أضفت زر
    let isMobile = window.innerWidth <= 900;
    window.addEventListener('resize', () => { isMobile = window.innerWidth <= 900; });
})();
</script>
</body>
</html>