<?php
// admin/manage_services.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// حماية: المدير فقط
check_login('admin');
$current_page = 'manage_services';


global $pdo;
$services = [];
$errors   = [];

// ── معالجة POST (تعديل الحالة / الحذف) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // التحقق من CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        set_message("خطأ أمني — أعد المحاولة.", "danger");
        header("Location: manage_services.php"); exit();
    }

    $service_id = (int)($_POST['service_id'] ?? 0);
    $action     = trim($_POST['action'] ?? '');

    if ($service_id > 0) {
        try {
            if ($action === 'toggle_active') {
                $current = (int)($_POST['current_status'] ?? 1);
                $new_val = $current === 1 ? 0 : 1;
                $pdo->prepare("UPDATE services SET is_active = ? WHERE id = ?")
                    ->execute([$new_val, $service_id]);
                set_message($new_val ? "✅ تم تفعيل الخدمة بنجاح." : "⛔ تم إيقاف الخدمة.", $new_val ? "success" : "warning");

            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM services WHERE id = ?")
                    ->execute([$service_id]);
                set_message("🗑️ تم حذف الخدمة بنجاح.", "success");
            }
        } catch (PDOException $e) {
            error_log("manage_services error: " . $e->getMessage());
            set_message("حدث خطأ في قاعدة البيانات.", "danger");
        }
    }
    header("Location: manage_services.php"); exit();
}

// ── جلب البيانات والفلترة التلقائية عبر قاعدة البيانات ──────
$filter_q      = trim($_GET['q'] ?? '');
$filter_active = $_GET['active'] ?? '';
$filter_cat    = (int)($_GET['cat'] ?? 0);

$where = []; $params = [];
if ($filter_q !== '') { 
    $where[] = "(s.title LIKE ? OR u.full_name LIKE ?)"; 
    $params[] = "%$filter_q%"; $params[] = "%$filter_q%"; 
}
if ($filter_active !== '') { $where[] = "s.is_active = ?"; $params[] = (int)$filter_active; }
if ($filter_cat > 0)       { $where[] = "s.category_id = ?"; $params[] = $filter_cat; }
$ws = $where ? "WHERE " . implode(' AND ', $where) : "";

try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.price, s.city, s.is_active, s.created_at, s.image,
               u.full_name AS provider_name, c.name AS category_name
        FROM services s
        JOIN users u ON s.provider_id = u.id
        LEFT JOIN categories c ON s.category_id = c.id
        $ws ORDER BY s.created_at DESC
    ");
    $stmt->execute($params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات سريعة
    $total_all      = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $total_active   = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active=1")->fetchColumn();
    $total_inactive = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active=0")->fetchColumn();
    $categories_all = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
    $total_all = $total_active = $total_inactive = 0; $categories_all = [];
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة الخدمات | لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
/* ============================================================
   XATO DARK — ADMIN PANEL — manage_services.php
============================================================ */
:root {
    --dark:     #060818;
    --card:     #0e1726;
    --border:   #1b2e4b;
    --txt:      #e0e6ed;
    --muted:    #888ea8;
    --dark2:    #bfc9d4;
    --primary:  #4361ee;
    --pri-lt:   rgba(67,97,238,.15);
    --success:  #00ab55;
    --suc-lt:   rgba(0,171,85,.15);
    --warning:  #e2a03f;
    --war-lt:   rgba(226,160,63,.15);
    --danger:   #e7515a;
    --dan-lt:   rgba(231,81,90,.15);
    --info:     #2196f3;
    --inf-lt:   rgba(33,150,243,.15);
    --purple:   #805dca;
    --nav-h:    68px;
    --side-w:   240px;
    --radius:   10px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body   { font-family: 'Tajawal', sans-serif; background: var(--dark); color: var(--txt); direction: rtl; min-height: 100vh; font-size: 14px; }
a      { text-decoration: none; color: inherit; }
::-webkit-scrollbar           { width: 4px; }
::-webkit-scrollbar-track     { background: var(--card); }
::-webkit-scrollbar-thumb     { background: var(--border); border-radius: 4px; }

/* ═══ TOP NAV ═══════════════════════════════════════════════ */
.top-nav {
    position: fixed; top: 0; right: 0; left: 0;
    height: var(--nav-h);
    background: var(--card);
    border-bottom: 1px solid var(--border);
    z-index: 200;
    display: flex; align-items: center;
    padding: 0 24px; gap: 14px;
}
.nav-brand {
    display: flex; align-items: center; gap: 10px;
    font-size: 19px; font-weight: 900; color: var(--txt);
}
.nav-brand .badge {
    background: var(--danger); color: #fff;
    font-size: 9px; font-weight: 800;
    padding: 2px 6px; border-radius: 4px;
    letter-spacing: .5px; text-transform: uppercase;
}
.nav-spacer { flex: 1; }
.nav-icon {
    width: 36px; height: 36px;
    background: var(--dark);
    border: 1px solid var(--border);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); font-size: 17px;
    transition: all .2s; cursor: pointer;
    text-decoration: none;
}
.nav-icon:hover { border-color: var(--primary); color: var(--primary); }
.nav-user {
    display: flex; align-items: center; gap: 9px;
    padding: 5px 10px; border-radius: 8px;
    transition: background .2s; cursor: pointer;
}
.nav-user:hover { background: var(--pri-lt); }
.nav-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, var(--danger), var(--purple));
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800; color: #fff;
}
.nav-uname { font-size: 13px; font-weight: 700; color: var(--txt); }
.nav-urole { font-size: 11px; color: var(--danger); font-weight: 600; }

/* ═══ SIDEBAR ════════════════════════════════════════════════ */
.sidebar {
    position: fixed; top: var(--nav-h); right: 0;
    width: var(--side-w);
    height: calc(100vh - var(--nav-h));
    background: var(--card);
    border-left: 1px solid var(--border);
    overflow-y: auto; z-index: 100;
    padding: 16px 10px;
}
.side-section { font-size: 10px; font-weight: 800; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; padding: 14px 12px 6px; }
.side-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 8px;
    color: var(--muted); font-size: 13px; font-weight: 600;
    transition: all .2s; margin-bottom: 2px;
}
.side-link i   { font-size: 17px; min-width: 20px; }
.side-link:hover { background: var(--pri-lt); color: var(--primary); }
.side-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 14px rgba(67,97,238,.35); }
.side-link.active i { color: #fff; }
.side-badge {
    margin-right: auto; font-size: 10px; font-weight: 800;
    padding: 2px 7px; border-radius: 10px;
}
.side-link.danger-link     { color: var(--danger); }
.side-link.danger-link:hover { background: var(--dan-lt); color: var(--danger); }

/* ═══ MAIN ═══════════════════════════════════════════════════ */
.main {
    margin-right: var(--side-w);
    margin-top: var(--nav-h);
    padding: 28px;
    min-height: calc(100vh - var(--nav-h));
}

/* ═══ PAGE HEADER ════════════════════════════════════════════ */
.page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 26px; flex-wrap: wrap; gap: 12px;
}
.page-title h1 { font-size: 22px; font-weight: 900; }
.page-title .subtitle { font-size: 13px; color: var(--muted); margin-top: 3px; }
.breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--muted);
    margin-top: 5px;
}
.breadcrumb a   { color: var(--primary); }
.breadcrumb sep { color: var(--border); }

/* ═══ STAT CARDS ═════════════════════════════════════════════ */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 26px;
}
.stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    display: flex; flex-direction: column; gap: 4px;
    transition: transform .2s, box-shadow .2s;
    cursor: default;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.35); }
.stat-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; margin-bottom: 8px;
}
.stat-val  { font-size: 28px; font-weight: 900; line-height: 1; }
.stat-lbl  { font-size: 12px; color: var(--muted); margin-top: 2px; }

/* ═══ TOOLBAR ════════════════════════════════════════════════ */
.toolbar {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 18px; flex-wrap: wrap;
}
.search-wrap {
    position: relative; flex: 1; min-width: 200px;
}
.search-wrap input {
    width: 100%; padding: 9px 36px 9px 14px;
    background: var(--dark);
    border: 1px solid var(--border);
    border-radius: 7px;
    color: var(--txt);
    font-family: 'Tajawal', sans-serif; font-size: 13px;
    outline: none; transition: border-color .2s;
}
.search-wrap input:focus { border-color: var(--primary); }
.search-wrap i {
    position: absolute; left: 11px; top: 50%;
    transform: translateY(-50%); color: var(--muted); font-size: 15px;
}
.filter-select {
    padding: 9px 12px;
    background: var(--dark); border: 1px solid var(--border);
    border-radius: 7px; color: var(--txt);
    font-family: 'Tajawal', sans-serif; font-size: 13px;
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.filter-select:focus { border-color: var(--primary); }
.filter-select option { background: var(--card); }
.btn-filter-submit {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 9px 16px; background: var(--primary); color: #fff;
    border: none; border-radius: 7px; font-family: 'Tajawal', sans-serif;
    font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity .2s;
}
.btn-filter-submit:hover { opacity: .85; }
.btn-reset-link {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 8px 12px; background: transparent; border: 1px solid var(--border);
    border-radius: 7px; color: var(--muted); font-size: 13px; transition: all .2s;
}
.btn-reset-link:hover { border-color: var(--danger); color: var(--danger); }
.results-count {
    font-size: 12px; color: var(--muted);
    margin-right: auto; white-space: nowrap;
}
.results-count strong { color: var(--primary); }

/* ═══ TABLE CARD ═════════════════════════════════════════════ */
.table-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}
.table-card-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.table-card-header h5 { font-size: 15px; font-weight: 700; margin: 0; }
.table-card-header i  { color: var(--primary); font-size: 18px; }

/* ═══ TABLE ══════════════════════════════════════════════════ */
.services-table { width: 100%; border-collapse: collapse; }
.services-table thead th {
    background: rgba(27,46,75,.5);
    color: var(--muted); font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    padding: 11px 16px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap; text-align: right;
}
.services-table tbody tr {
    border-bottom: 1px solid rgba(27,46,75,.4);
    transition: background .15s;
}
.services-table tbody tr:hover { background: rgba(27,46,75,.35); }
.services-table tbody tr:last-child { border-bottom: none; }
.services-table tbody td {
    padding: 13px 16px;
    font-size: 13px; color: var(--dark2);
    vertical-align: middle; text-align: right;
}
.services-table tbody tr.paused-row td { opacity: .55; }

/* Service cell */
.srv-cell { display: flex; align-items: center; gap: 10px; }
.srv-thumb {
    width: 38px; height: 38px; border-radius: 6px;
    object-fit: cover; border: 1px solid var(--border); flex-shrink: 0;
}
.srv-ph {
    width: 38px; height: 38px; border-radius: 6px;
    background: var(--dark); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: var(--muted); flex-shrink: 0;
}
.srv-name { font-size: 13px; font-weight: 700; color: var(--txt); }
.srv-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; display: flex; align-items: center; gap: 4px; }

/* Provider cell */
.prov-cell { display: flex; align-items: center; gap: 8px; }
.prov-av {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; color: #fff; flex-shrink: 0;
}
.prov-name { font-size: 12px; font-weight: 700; color: var(--primary); }

/* Price and other typography styles */
.price-val { font-size: 14px; font-weight: 800; color: var(--success); }
.price-cur { font-size: 11px; color: var(--muted); }
.city-cell { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; }

/* Status badge */
.status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
}
.status-badge::before {
    content: ''; width: 6px; height: 6px;
    border-radius: 50%; background: currentColor;
    flex-shrink: 0;
}
.status-active { background: var(--suc-lt); color: var(--success); }
.status-paused { background: var(--dan-lt); color: var(--danger); }

/* Action buttons */
.actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.btn-xs {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 5px 10px; border-radius: 6px;
    font-family: 'Tajawal', sans-serif;
    font-size: 11px; font-weight: 700;
    border: none; cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.btn-view    { background: var(--inf-lt);  color: var(--info); }
.btn-view:hover  { background: var(--info);  color: #060818; }
.btn-edit    { background: var(--pri-lt);  color: var(--primary); }
.btn-edit:hover  { background: var(--primary); color: #fff; }
.btn-ban     { background: var(--war-lt);  color: var(--warning); }
.btn-ban:hover   { background: var(--warning); color: #fff; }
.btn-unban   { background: var(--suc-lt);  color: var(--success); }
.btn-unban:hover { background: var(--success); color: #fff; }
.btn-delete  { background: var(--dan-lt);  color: var(--danger); }
.btn-delete:hover{ background: var(--danger);  color: #fff; }

/* ═══ EMPTY STATE ════════════════════════════════════════════ */
.empty-row td { text-align: center; padding: 60px 20px !important; }
.empty-icon {
    width: 70px; height: 70px; border-radius: 50%;
    background: var(--pri-lt); margin: 0 auto 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: var(--primary);
}

/* ═══ ALERTS ═════════════════════════════════════════════════ */
.xato-alert {
    padding: 12px 16px; border-radius: 8px;
    margin-bottom: 18px;
    display: flex; align-items: center; gap: 10px;
    font-size: 13px; font-weight: 600;
}
.xato-alert-success { background: var(--suc-lt); color: var(--success); border: 1px solid rgba(0,171,85,.25); }
.xato-alert-danger  { background: var(--dan-lt); color: var(--danger);  border: 1px solid rgba(231,81,90,.25); }
.xato-alert-warning { background: var(--war-lt); color: var(--warning); border: 1px solid rgba(226,160,63,.25); }

/* ═══ MODAL ══════════════════════════════════════════════════ */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(6,8,24,.82); backdrop-filter: blur(5px);
    z-index: 500; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 14px; padding: 30px; width: 92%; max-width: 420px;
    animation: modalIn .22s ease;
}
@keyframes modalIn { from { opacity:0; transform:translateY(16px) scale(.97); } to { opacity:1; transform:none; } }
.modal-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--dan-lt); color: var(--danger);
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; margin: 0 auto 16px;
}
.modal-title { font-size: 18px; font-weight: 800; text-align: center; margin-bottom: 8px; }
.modal-desc  { font-size: 13px; color: var(--muted); text-align: center; line-height: 1.7; margin-bottom: 22px; }
.modal-actions { display: flex; gap: 10px; }
.btn-modal {
    flex: 1; padding: 11px;
    border-radius: 8px; border: none; cursor: pointer;
    font-family: 'Tajawal', sans-serif; font-size: 14px; font-weight: 700;
    transition: all .2s;
}
.btn-modal-cancel { background: var(--border); color: var(--txt); }
.btn-modal-cancel:hover { background: #243352; }
.btn-modal-confirm { background: var(--danger); color: #fff; }
.btn-modal-confirm:hover { background: #c73f47; }

/* ═══ RESPONSIVE ═════════════════════════════════════════════ */
@media (max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-right: 0; padding: 16px; }
    .services-table thead th:nth-child(4), .services-table tbody td:nth-child(4),
    .services-table thead th:nth-child(6), .services-table tbody td:nth-child(6) { display: none; }
}
@media (max-width: 600px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .toolbar   { flex-direction: column; align-items: stretch; }
    .search-wrap { min-width: 100%; }
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

<!-- ══════════════ TOP NAV ══════════════ -->
<nav class="top-nav">
    <div class="nav-brand">
        <i class="las la-shield-alt" style="color:var(--danger);font-size:22px;"></i>
        خدماتي
        <span class="badge">ADMIN</span>
    </div>
    <div class="nav-spacer"></div>
    <button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
            class="nav-icon" style="cursor:pointer;">
        <i class="las la-sun" id="themeIcon"></i>
    </button>

    <a href="/local_services/index.php" class="nav-icon" title="الموقع الرئيسي">
        <i class="las la-external-link-alt"></i>
    </a>
    <a href="/local_services/logout.php" class="nav-icon" title="خروج" style="color:var(--danger);">
        <i class="las la-sign-out-alt"></i>
    </a>
    <?php
    $me = getCurrentUser();
    if ($me):
    ?>
    <div class="nav-user">
        <div class="nav-avatar"><?php echo mb_substr($me['full_name'], 0, 1); ?></div>
        <div>
            <div class="nav-uname"><?php echo htmlspecialchars($me['full_name']); ?></div>
            <div class="nav-urole"><i class="las la-crown"></i> مدير النظام</div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<!-- ══════════════ SIDEBAR ══════════════ -->
<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<!-- ══════════════ MAIN ══════════════ -->
<main class="main">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1><i class="las la-concierge-bell" style="color:var(--primary);margin-left:8px;font-size:24px;"></i> إدارة الخدمات</h1>
            <div class="breadcrumb">
                <a href="/local_services/index.php">الرئيسية</a>
                <sep>/</sep>
                <span>الخدمات</span>
            </div>
        </div>
        <a href="/local_services/admin/add_service.php"
           style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:var(--primary);color:#fff;border-radius:8px;font-size:13px;font-weight:700;transition:opacity .2s;"
           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <i class="las la-plus"></i> إضافة خدمة جديدة
        </a>
    </div>

    <!-- Alerts -->
    <?php
    $flash = $_SESSION['flash_message'] ?? null;
    if ($flash):
        unset($_SESSION['flash_message']);
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_type']);
        $icons = ['success'=>'la-check-circle','danger'=>'la-times-circle','warning'=>'la-exclamation-triangle'];
        $ico = $icons[$type] ?? 'la-info-circle';
    ?>
    <div class="xato-alert xato-alert-<?php echo htmlspecialchars($type); ?>">
        <i class="las <?php echo $ico; ?>" style="font-size:18px;flex-shrink:0;"></i>
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- Error -->
    <?php if (!empty($errors)): ?>
    <div class="xato-alert xato-alert-danger">
        <i class="las la-exclamation-triangle" style="font-size:18px;flex-shrink:0;"></i>
        <?php echo htmlspecialchars($errors[0]); ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--pri-lt);color:var(--primary);"><i class="las la-concierge-bell"></i></div>
            <div class="stat-val" style="color:var(--primary);"><?php echo $total_all; ?></div>
            <div class="stat-lbl">إجمالي الخدمات</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--suc-lt);color:var(--success);"><i class="las la-check-circle"></i></div>
            <div class="stat-val" style="color:var(--success);"><?php echo $total_active; ?></div>
            <div class="stat-lbl">خدمات نشطة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--dan-lt);color:var(--danger);"><i class="las la-ban"></i></div>
            <div class="stat-val" style="color:var(--danger);"><?php echo $total_inactive; ?></div>
            <div class="stat-lbl">خدمات موقوفة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--inf-lt);color:var(--info);"><i class="las la-search"></i></div>
            <div class="stat-val" style="color:var(--info);"><?php echo count($services); ?></div>
            <div class="stat-lbl">نتائج التصفية</div>
        </div>
    </div>

    <!-- Toolbar -->
    <form method="GET" id="filterForm">
        <div class="toolbar">
            <div class="search-wrap">
                <input type="text" name="q" id="searchInput" placeholder="ابحث بعنوان الخدمة أو اسم المزود..." value="<?php echo htmlspecialchars($filter_q); ?>">
                <i class="las la-search"></i>
            </div>
            <select name="active" class="filter-select" id="statusFilter">
                <option value="">جميع الحالات</option>
                <option value="1" <?php echo $filter_active==='1'?'selected':''; ?>>نشطة فقط</option>
                <option value="0" <?php echo $filter_active==='0'?'selected':''; ?>>موقوفة فقط</option>
            </select>
            <select name="cat" class="filter-select" id="categoryFilter">
                <option value="">جميع التصنيفات</option>
                <?php foreach($categories_all as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $filter_cat==$cat['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter-submit"><i class="las la-filter"></i> تصفية</button>
            <?php if($filter_q || $filter_active!=='' || $filter_cat > 0): ?>
            <a href="manage_services.php" class="btn-reset-link"><i class="las la-times"></i> مسح</a>
            <?php endif; ?>
            <div class="results-count" id="resultsCount">
                يُعرض: <strong><?php echo count($services); ?></strong> من <?php echo $total_all; ?> خدمة
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="table-card">
        <div class="table-card-header">
            <i class="las la-table"></i>
            <h5>قائمة الخدمات</h5>
            <span style="margin-right:auto;font-size:12px;color:var(--muted);">آخر تحديث: <?php echo date('Y/m/d H:i'); ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="services-table" id="servicesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الخدمة</th>
                        <th>المزوّد</th>
                        <th>التصنيف</th>
                        <th>السعر</th>
                        <th>المدينة</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="servicesBody">
                <?php if (empty($services)): ?>
                    <tr class="empty-row">
                        <td colspan="9">
                            <div class="empty-icon"><i class="las la-concierge-bell"></i></div>
                            <div style="font-size:16px;font-weight:700;margin-bottom:6px;">لا توجد خدمات مطابقة</div>
                            <div style="font-size:13px;color:var(--muted);">جرّب تغيير معايير البحث أو أضف خدمة جديدة</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $avatarColors = ['#4361ee','#805dca','#00ab55','#e2a03f','#e7515a','#2196f3'];
                    foreach ($services as $i => $s):
                        $av_color  = $avatarColors[$i % count($avatarColors)];
                        $initial   = mb_substr($s['provider_name'], 0, 1);
                    ?>
                    <tr class="<?php echo !$s['is_active'] ? 'paused-row' : ''; ?>">

                        <td style="color:var(--muted);font-size:12px;"><?php echo $s['id']; ?></td>

                        <td>
                            <div class="srv-cell">
                                <?php if(!empty($s['image'])): ?>
                                <img src="/local_services/assets/uploads/services/<?php echo htmlspecialchars($s['image']); ?>" class="srv-thumb" alt="">
                                <?php else: ?>
                                <div class="srv-ph"><i class="las la-image"></i></div>
                                <?php endif; ?>
                                <div>
                                    <div class="srv-name" title="<?php echo htmlspecialchars($s['title']); ?>"><?php echo htmlspecialchars($s['title']); ?></div>
                                    <?php if($s['category_name']): ?>
                                    <div class="srv-sub"><i class="las la-tag"></i><?php echo htmlspecialchars($s['category_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="prov-cell">
                                <div class="prov-av" style="background:<?php echo $av_color; ?>;">
                                    <?php echo $initial; ?>
                                </div>
                                <span class="prov-name"><?php echo htmlspecialchars($s['provider_name']); ?></span>
                            </div>
                        </td>

                        <td style="font-size:12px;color:var(--muted);">
                            <?php echo $s['category_name'] ? htmlspecialchars($s['category_name']) : '—'; ?>
                        </td>

                        <td>
                            <span class="price-val"><?php echo number_format((float)$s['price'], 0); ?></span>
                            <span class="price-cur"> ₪</span>
                        </td>

                        <td>
                            <?php if($s['city']): ?>
                            <span class="city-cell">
                                <i class="las la-map-marker-alt" style="color:var(--danger);font-size:13px;"></i>
                                <?php echo htmlspecialchars($s['city']); ?>
                            </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>

                        <td>
                            <span class="status-badge <?php echo $s['is_active'] ? 'status-active' : 'status-paused'; ?>">
                                <?php echo $s['is_active'] ? 'نشطة' : 'موقوفة'; ?>
                            </span>
                        </td>

                        <td style="font-size:12px;color:var(--muted);">
                            <?php echo date('Y/m/d', strtotime($s['created_at'])); ?>
                        </td>

                        <td>
                            <div class="actions">
                                <a href="/local_services/service_detail.php?id=<?php echo $s['id']; ?>" class="btn-xs btn-view" target="_blank">
                                    <i class="las la-eye"></i> معاينة
                                </a>
                                <a href="edit_service.php?id=<?php echo $s['id']; ?>" class="btn-xs btn-edit">
                                    <i class="las la-edit"></i> تعديل
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="service_id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="current_status" value="<?php echo $s['is_active']; ?>">
                                    <button type="submit" class="btn-xs <?php echo $s['is_active'] ? 'btn-ban' : 'btn-unban'; ?>">
                                        <i class="las <?php echo $s['is_active'] ? 'la-ban' : 'la-check-circle'; ?>"></i>
                                        <?php echo $s['is_active'] ? 'إيقاف' : 'تفعيل'; ?>
                                    </button>
                                </form>
                                <button type="button" class="btn-xs btn-delete" onclick="confirmDelete(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['title'])); ?>')">
                                    <i class="las la-trash-alt"></i> حذف
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ══════════════ DELETE MODAL ══════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon"><i class="las la-trash-alt"></i></div>
        <div class="modal-title">تأكيد الحذف</div>
        <p class="modal-desc">
            هل أنت متأكد من حذف خدمة 
            <strong id="deleteServiceName" style="color:var(--danger);"></strong>؟
            <br>هذا الإجراء لا يمكن التراجع عنه.
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="service_id" id="deleteServiceId">
            <input type="hidden" name="action" value="delete">
            <div class="modal-actions">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeModal()">إلغاء</button>
                <button type="submit" class="btn-modal btn-modal-confirm">
                    <i class="las la-trash-alt"></i> حذف نهائي
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════ SCRIPTS ══════════════ -->
<script>
(function () {
    'use strict';

    // ── Delete Modal ─────────────────────────────────────────
    window.confirmDelete = function (id, name) {
        document.getElementById('deleteServiceId').value = id;
        document.getElementById('deleteServiceName').textContent = name;
        document.getElementById('deleteModal').classList.add('open');
    };

    window.closeModal = function () {
        document.getElementById('deleteModal').classList.remove('open');
    };

    document.getElementById('deleteModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
})();
</script>

</body>
</html>