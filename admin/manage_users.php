<?php
// admin/manage_users.php
// الموقع: C:\xampp\htdocs\local_services\admin\manage_users.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// حماية: المدير فقط
check_login('admin');
$current_page = 'manage_users';


global $pdo;
$users  = [];
$errors = [];

// ── معالجة POST (تغيير الدور / الحالة / الحذف) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // التحقق من CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        set_message("خطأ أمني — أعد المحاولة.", "danger");
        header("Location: manage_users.php"); exit();
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $action  = trim($_POST['action'] ?? '');

    if ($user_id > 0) {
        try {
            // لا تسمح للمدير بتعديل نفسه عبر هذه الصفحة
            $is_self = ($user_id === (int)($_SESSION['user_id'] ?? 0));

            if ($action === 'change_role' && !$is_self) {
                $new_role = $_POST['new_role'] ?? '';
                if (in_array($new_role, ['client', 'provider', 'admin'])) {
                    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
                        ->execute([$new_role, $user_id]);
                    set_message("✅ تم تغيير الدور إلى «{$new_role}» بنجاح.", "success");
                }

            } elseif ($action === 'toggle_active') {
                $current = (int)($_POST['current_status'] ?? 1);
                $new_val = $current === 1 ? 0 : 1;
                $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")
                    ->execute([$new_val, $user_id]);
                set_message($new_val ? "✅ تم تفعيل الحساب." : "⛔ تم حظر الحساب.", $new_val ? "success" : "warning");

            } elseif ($action === 'delete' && !$is_self) {
                $pdo->prepare("DELETE FROM users WHERE id = ?")
                    ->execute([$user_id]);
                set_message("🗑️ تم حذف المستخدم بنجاح.", "success");
            }
        } catch (PDOException $e) {
            error_log("manage_users error: " . $e->getMessage());
            set_message("حدث خطأ في قاعدة البيانات.", "danger");
        }
    }
    header("Location: manage_users.php"); exit();
}

// ── جلب المستخدمين ──────────────────────────────────────────
try {
    $stmt  = $pdo->query("SELECT id, full_name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

// ── إحصائيات سريعة ──────────────────────────────────────────
$total     = count($users);
$clients   = count(array_filter($users, fn($u) => $u['role'] === 'client'));
$providers = count(array_filter($users, fn($u) => $u['role'] === 'provider'));
$admins    = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$active    = count(array_filter($users, fn($u) => $u['is_active']));
$banned    = $total - $active;

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة المستخدمين | لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
/* ============================================================
   XATO DARK — ADMIN PANEL — manage_users.php
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
.users-table { width: 100%; border-collapse: collapse; }
.users-table thead th {
    background: rgba(27,46,75,.5);
    color: var(--muted); font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    padding: 11px 16px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap; text-align: right;
}
.users-table tbody tr {
    border-bottom: 1px solid rgba(27,46,75,.4);
    transition: background .15s;
}
.users-table tbody tr:hover { background: rgba(27,46,75,.35); }
.users-table tbody tr:last-child { border-bottom: none; }
.users-table tbody td {
    padding: 13px 16px;
    font-size: 13px; color: var(--dark2);
    vertical-align: middle; text-align: right;
}
.users-table tbody tr.banned-row td { opacity: .55; }

/* User cell */
.user-cell { display: flex; align-items: center; gap: 10px; }
.u-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: #fff;
}
.u-name  { font-size: 13px; font-weight: 700; color: var(--txt); }
.u-email { font-size: 11px; color: var(--muted); }

/* Role badge */
.role-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700; border: 1px solid;
}
.role-client   { background: var(--inf-lt);  color: var(--info);    border-color: rgba(33,150,243,.3); }
.role-provider { background: var(--pri-lt);  color: var(--primary); border-color: rgba(67,97,238,.3); }
.role-admin    { background: var(--dan-lt);  color: var(--danger);  border-color: rgba(231,81,90,.3); }

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
.status-banned { background: var(--dan-lt); color: var(--danger); }

/* Role inline select */
.role-form-wrap { display: inline-flex; align-items: center; }
.role-select {
    background: var(--dark);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--txt);
    font-family: 'Tajawal', sans-serif;
    font-size: 12px; font-weight: 700;
    padding: 4px 8px;
    outline: none; cursor: pointer;
    transition: border-color .2s;
}
.role-select:focus { border-color: var(--primary); }
.role-select option { background: var(--card); }

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
.btn-xs:disabled { opacity: .4; cursor: not-allowed; }
.btn-edit    { background: var(--pri-lt);  color: var(--primary); }
.btn-edit:hover  { background: var(--primary); color: #fff; }
.btn-ban     { background: var(--war-lt);  color: var(--warning); }
.btn-ban:hover   { background: var(--warning); color: #fff; }
.btn-unban   { background: var(--suc-lt);  color: var(--success); }
.btn-unban:hover { background: var(--success); color: #fff; }
.btn-delete  { background: var(--dan-lt);  color: var(--danger); }
.btn-delete:hover{ background: var(--danger);  color: #fff; }

/* ═══ EMPTY STATE ════════════════════════════════════════════ */
.empty-row td {
    text-align: center; padding: 60px 20px !important;
}
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
    .users-table thead th:nth-child(5),
    .users-table tbody td:nth-child(5) { display: none; }
}
@media (max-width: 600px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .toolbar   { flex-direction: column; }
    .search-wrap { min-width: 100%; }
    .users-table thead th:nth-child(4),
    .users-table tbody td:nth-child(4) { display: none; }
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
            <h1><i class="las la-users" style="color:var(--primary);margin-left:8px;font-size:24px;"></i> إدارة المستخدمين</h1>
            <div class="breadcrumb">
                <a href="/local_services/index.php">الرئيسية</a>
                <sep>/</sep>
                <span>المستخدمون</span>
            </div>
        </div>
        <a href="/local_services/admin/add_user.php"
           style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:var(--primary);color:#fff;border-radius:8px;font-size:13px;font-weight:700;transition:opacity .2s;"
           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <i class="las la-plus"></i> إضافة مستخدم
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
            <div class="stat-icon" style="background:var(--pri-lt);color:var(--primary);"><i class="las la-users"></i></div>
            <div class="stat-val" style="color:var(--primary);"><?php echo $total; ?></div>
            <div class="stat-lbl">إجمالي المستخدمين</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--inf-lt);color:var(--info);"><i class="las la-user"></i></div>
            <div class="stat-val" style="color:var(--info);"><?php echo $clients; ?></div>
            <div class="stat-lbl">العملاء</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--pri-lt);color:var(--purple);"><i class="las la-user-tie"></i></div>
            <div class="stat-val" style="color:var(--purple);"><?php echo $providers; ?></div>
            <div class="stat-lbl">مزودو الخدمة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--dan-lt);color:var(--danger);"><i class="las la-user-shield"></i></div>
            <div class="stat-val" style="color:var(--danger);"><?php echo $admins; ?></div>
            <div class="stat-lbl">المديرون</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--suc-lt);color:var(--success);"><i class="las la-user-check"></i></div>
            <div class="stat-val" style="color:var(--success);"><?php echo $active; ?></div>
            <div class="stat-lbl">حسابات نشطة</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--war-lt);color:var(--warning);"><i class="las la-user-slash"></i></div>
            <div class="stat-val" style="color:var(--warning);"><?php echo $banned; ?></div>
            <div class="stat-lbl">محظورون</div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="search-wrap">
            <input type="text" id="searchInput" placeholder="ابحث بالاسم أو البريد الإلكتروني...">
            <i class="las la-search"></i>
        </div>
        <select class="filter-select" id="roleFilter">
            <option value="">جميع الأدوار</option>
            <option value="client">عميل</option>
            <option value="provider">مزود خدمة</option>
            <option value="admin">مدير</option>
        </select>
        <select class="filter-select" id="statusFilter">
            <option value="">جميع الحالات</option>
            <option value="active">نشط</option>
            <option value="banned">محظور</option>
        </select>
        <div class="results-count" id="resultsCount">
            إجمالي: <strong><?php echo $total; ?></strong> مستخدم
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-card-header">
            <i class="las la-table"></i>
            <h5>قائمة المستخدمين</h5>
            <span style="margin-right:auto;font-size:12px;color:var(--muted);">آخر تحديث: <?php echo date('Y/m/d H:i'); ?></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المستخدم</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>تاريخ الانضمام</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="usersBody">
                <?php if (empty($users)): ?>
                    <tr class="empty-row">
                        <td colspan="6">
                            <div class="empty-icon"><i class="las la-user-slash"></i></div>
                            <div style="font-size:16px;font-weight:700;margin-bottom:6px;">لا يوجد مستخدمون</div>
                            <div style="font-size:13px;color:var(--muted);">لم يتم العثور على أي مستخدمين في النظام</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $avatarColors = ['#4361ee','#805dca','#00ab55','#e2a03f','#e7515a','#2196f3'];
                    foreach ($users as $i => $u):
                        $is_self   = ($u['id'] == ($me['id'] ?? 0));
                        $av_color  = $avatarColors[$i % count($avatarColors)];
                        $initial   = mb_substr($u['full_name'], 0, 1);
                        $role_cls  = 'role-' . $u['role'];
                        $role_lbl  = ['client'=>'عميل','provider'=>'مزود خدمة','admin'=>'مدير'][$u['role']] ?? $u['role'];
                        $role_ico  = ['client'=>'la-user','provider'=>'la-user-tie','admin'=>'la-user-shield'][$u['role']] ?? 'la-user';
                    ?>
                    <tr data-name="<?php echo htmlspecialchars(strtolower($u['full_name'] . ' ' . $u['email'])); ?>"
                        data-role="<?php echo $u['role']; ?>"
                        data-status="<?php echo $u['is_active'] ? 'active' : 'banned'; ?>"
                        class="<?php echo !$u['is_active'] ? 'banned-row' : ''; ?>">

                        <td style="color:var(--muted);font-size:12px;"><?php echo $u['id']; ?></td>

                        <td>
                            <div class="user-cell">
                                <div class="u-avatar" style="background:<?php echo $av_color; ?>;">
                                    <?php echo $initial; ?>
                                </div>
                                <div>
                                    <div class="u-name">
                                        <?php echo htmlspecialchars($u['full_name']); ?>
                                        <?php if ($is_self): ?>
                                        <span style="font-size:10px;background:var(--suc-lt);color:var(--success);padding:1px 6px;border-radius:8px;margin-right:5px;">أنت</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="u-email"><?php echo htmlspecialchars($u['email']); ?></div>
                                </div>
                            </div>
                        </td>

                        <td>
                            <?php if (!$is_self): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token"  value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="user_id"     value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="action"      value="change_role">
                                <select name="new_role" class="role-select" onchange="this.form.submit()"
                                        title="تغيير الدور">
                                    <?php foreach (['client'=>'عميل','provider'=>'مزود خدمة','admin'=>'مدير'] as $rv => $rl): ?>
                                        <option value="<?php echo $rv; ?>" <?php echo $u['role'] === $rv ? 'selected' : ''; ?>>
                                            <?php echo $rl; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <?php else: ?>
                                <span class="role-badge <?php echo $role_cls; ?>">
                                    <i class="las <?php echo $role_ico; ?>"></i>
                                    <?php echo $role_lbl; ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="status-badge <?php echo $u['is_active'] ? 'status-active' : 'status-banned'; ?>">
                                <?php echo $u['is_active'] ? 'نشط' : 'محظور'; ?>
                            </span>
                        </td>

                        <td style="font-size:12px;color:var(--muted);">
                            <?php echo date('Y/m/d', strtotime($u['created_at'])); ?>
                        </td>

                        <td>
                            <div class="actions">
                                <!-- Edit -->
                                <a href="/local_services/admin/edit_user.php?id=<?php echo $u['id']; ?>"
                                   class="btn-xs btn-edit">
                                    <i class="las la-edit"></i> تعديل
                                </a>

                                <!-- Ban / Unban -->
                                <?php if (!$is_self): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token"      value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="user_id"         value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action"          value="toggle_active">
                                    <input type="hidden" name="current_status"  value="<?php echo $u['is_active']; ?>">
                                    <button type="submit" class="btn-xs <?php echo $u['is_active'] ? 'btn-ban' : 'btn-unban'; ?>">
                                        <i class="las <?php echo $u['is_active'] ? 'la-user-slash' : 'la-user-check'; ?>"></i>
                                        <?php echo $u['is_active'] ? 'حظر' : 'تفعيل'; ?>
                                    </button>
                                </form>

                                <!-- Delete -->
                                <button type="button" class="btn-xs btn-delete"
                                        onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['full_name'])); ?>')">
                                    <i class="las la-trash-alt"></i> حذف
                                </button>
                                <?php endif; ?>
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
            هل أنت متأكد من حذف المستخدم
            <strong id="deleteUserName" style="color:var(--danger);"></strong>؟
            <br>هذا الإجراء لا يمكن التراجع عنه.
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="user_id"    id="deleteUserId">
            <input type="hidden" name="action"     value="delete">
            <div class="modal-actions">
                <button type="button" class="btn-modal btn-modal-cancel" onclick="closeModal()">
                    إلغاء
                </button>
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

    // ── Live Search + Filters ────────────────────────────────
    const searchInput  = document.getElementById('searchInput');
    const roleFilter   = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const tbody        = document.getElementById('usersBody');
    const countEl      = document.getElementById('resultsCount');

    function filterTable() {
        const q      = searchInput.value.toLowerCase().trim();
        const role   = roleFilter.value;
        const status = statusFilter.value;
        let visible  = 0;

        tbody.querySelectorAll('tr[data-name]').forEach(row => {
            const matchQ      = !q      || row.dataset.name.includes(q);
            const matchRole   = !role   || row.dataset.role   === role;
            const matchStatus = !status || row.dataset.status === status;
            const show = matchQ && matchRole && matchStatus;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        countEl.innerHTML = `يُعرض: <strong>${visible}</strong> من ${<?php echo $total; ?>} مستخدم`;
    }

    searchInput?.addEventListener('input',  filterTable);
    roleFilter?.addEventListener('change',  filterTable);
    statusFilter?.addEventListener('change', filterTable);

    // ── Delete Modal ─────────────────────────────────────────
    window.confirmDelete = function (id, name) {
        document.getElementById('deleteUserId').value = id;
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteModal').classList.add('open');
    };

    window.closeModal = function () {
        document.getElementById('deleteModal').classList.remove('open');
    };

    document.getElementById('deleteModal')?.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    // ── URL filter params on load ─────────────────────────────
    const urlParams = new URLSearchParams(window.location.search);
    const roleParam = urlParams.get('role');
    const statusParam = urlParams.get('status');
    if (roleParam)   { roleFilter.value   = roleParam;   filterTable(); }
    if (statusParam) { statusFilter.value = statusParam; filterTable(); }
})();
</script>

</body>
</html>