<?php
// admin/settings.php
// الموقع: C:\xampp\htdocs\local_services\admin\settings.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// حماية: المدير فقط
check_login('admin');
$current_page = 'settings';


global $pdo;
$errors = [];

// ── 1. التأكد من وجود جدول الإعدادات أو إنشائه تلقائياً ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(50) PRIMARY KEY,
        `value` TEXT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    $errors[] = "خطأ في تهيئة جدول الإعدادات: " . $e->getMessage();
}

// ── 2. معالجة تحديث الإعدادات (POST) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // التحقق من CSRF لحماية البيانات
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        set_message("خطأ أمني — أعد المحاولة.", "danger");
        header("Location: settings.php"); exit();
    }

    // مصفوفة بالإعدادات المتوقع استقبالها من الفورم
    $allowed_settings = [
        'site_name', 'site_email', 'site_status', 'close_message',
        'currency', 'min_order_price', 'tax_percentage',
        'meta_description', 'meta_keywords', 'footer_text'
    ];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE `value` = ?");

        foreach ($allowed_settings as $key) {
            $value = isset($_POST[$key]) ? trim($_POST[$key]) : '';
            $stmt->execute([$key, $value, $value]);
        }

        $pdo->commit();
        set_message("✅ تم حفظ وتحديث جميع الإعدادات بنجاح.", "success");
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Settings Save Error: " . $e->getMessage());
        set_message("حدث خطأ أثناء حفظ البيانات في قاعدة البيانات.", "danger");
    }

    header("Location: settings.php"); exit();
}

// ── 3. جلب الإعدادات الحالية من قاعدة البيانات ──────────────
$settings = [];
try {
    $rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    $errors[] = "خطأ في جلب البيانات: " . $e->getMessage();
}

// القيم الافتراضية في حال كانت أول مرة يتم فيها فتح الصفحة
$get_setting = function($key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

// جلب إحصائيات سريعة للفرونت إند لمنع الأخطاء في السايدبار
$total_services = 0;
try {
    $total_services = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
} catch (Exception $e){}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إعدادات النظام | لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
/* ============================================================
   XATO DARK — ADMIN PANEL — settings.php
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
    position: fixed; top: 0; right: 0; left: 0; height: var(--nav-h);
    background: var(--card); border-bottom: 1px solid var(--border); z-index: 200;
    display: flex; align-items: center; padding: 0 24px; gap: 14px;
}
.nav-brand { display: flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 900; color: var(--txt); }
.nav-brand .badge { background: var(--danger); color: #fff; font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 4px; }
.nav-spacer { flex: 1; }
.nav-icon {
    width: 36px; height: 36px; background: var(--dark); border: 1px solid var(--border);
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    color: var(--muted); font-size: 17px; transition: all .2s; cursor: pointer;
}
.nav-icon:hover { border-color: var(--primary); color: var(--primary); }
.nav-user { display: flex; align-items: center; gap: 9px; padding: 5px 10px; border-radius: 8px; transition: background .2s; cursor: pointer; }
.nav-user:hover { background: var(--pri-lt); }
.nav-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--danger), var(--purple)); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: #fff; }
.nav-uname { font-size: 13px; font-weight: 700; color: var(--txt); }
.nav-urole { font-size: 11px; color: var(--danger); font-weight: 600; }

/* ═══ SIDEBAR ════════════════════════════════════════════════ */
.sidebar {
    position: fixed; top: var(--nav-h); right: 0; width: var(--side-w); height: calc(100vh - var(--nav-h));
    background: var(--card); border-left: 1px solid var(--border); overflow-y: auto; z-index: 100; padding: 16px 10px;
}
.side-section { font-size: 10px; font-weight: 800; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; padding: 14px 12px 6px; }
.side-link {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px;
    color: var(--muted); font-size: 13px; font-weight: 600; transition: all .2s; margin-bottom: 2px;
}
.side-link i { font-size: 17px; min-width: 20px; }
.side-link:hover { background: var(--pri-lt); color: var(--primary); }
.side-link.active { background: var(--primary); color: #fff; box-shadow: 0 4px 14px rgba(67,97,238,.35); }
.side-link.active i { color: #fff; }
.side-badge { margin-right: auto; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 10px; }
.side-link.danger-link { color: var(--danger); }
.side-link.danger-link:hover { background: var(--dan-lt); color: var(--danger); }

/* ═══ MAIN ═══════════════════════════════════════════════════ */
.main { margin-right: var(--side-w); margin-top: var(--nav-h); padding: 28px; min-height: calc(100vh - var(--nav-h)); }

/* ═══ PAGE HEADER ════════════════════════════════════════════ */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 26px; flex-wrap: wrap; gap: 12px; }
.page-title h1 { font-size: 22px; font-weight: 900; }
.page-title .subtitle { font-size: 13px; color: var(--muted); margin-top: 3px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); margin-top: 5px; }
.breadcrumb a { color: var(--primary); }
.breadcrumb sep { color: var(--border); }

/* ═══ SETTINGS LAYOUT (TABS) ═════════════════════════════════ */
.settings-container { display: grid; grid-template-columns: 220px 1fr; gap: 24px; align-items: start; }
.settings-tabs { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px; display: flex; flex-direction: column; gap: 4px; }
.tab-btn {
    display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px;
    color: var(--muted); font-size: 13px; font-weight: 700; border: none; background: transparent;
    cursor: pointer; text-align: right; width: 100%; transition: all .2s; font-family: 'Tajawal', sans-serif;
}
.tab-btn i { font-size: 18px; }
.tab-btn:hover { background: rgba(27,46,75,.4); color: var(--txt); }
.tab-btn.active { background: var(--pri-lt); color: var(--primary); }

.settings-panel { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); display: none; }
.settings-panel.active { display: block; }

.panel-header { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.panel-header h3 { font-size: 16px; font-weight: 800; color: var(--txt); }
.panel-header i { font-size: 20px; color: var(--primary); }

.panel-body { padding: 24px; }

/* ═══ FORM CONTROLS ══════════════════════════════════════════ */
.form-group { margin-bottom: 20px; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: 13px; font-weight: 700; color: var(--dark2); margin-bottom: 8px; }
.form-control {
    width: 100%; padding: 10px 14px; background: var(--dark); border: 1px solid var(--border);
    border-radius: 7px; color: var(--txt); font-family: 'Tajawal', sans-serif; font-size: 13px;
    outline: none; transition: border-color .2s, box-shadow .2s;
}
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67,97,238,.1); }
.form-text { font-size: 11px; color: var(--muted); margin-top: 5px; }

/* Custom Radio / Select Status Group */
.status-toggle-group { display: flex; gap: 12px; margin-top: 5px; }
.radio-tile-label {
    flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 12px; background: var(--dark); border: 1px solid var(--border); border-radius: 8px;
    cursor: pointer; transition: all .2s; text-align: center; gap: 4px;
}
.radio-tile-label input { position: absolute; opacity: 0; pointer-events: none; }
.radio-tile-label i { font-size: 20px; color: var(--muted); }
.radio-tile-label span { font-size: 12px; font-weight: 700; color: var(--muted); }

.radio-tile-label:hover { border-color: var(--border); background: rgba(27,46,75,.3); }
.radio-tile-label.active-success input:checked + i, .radio-tile-label.active-success input:checked ~ span { color: var(--success); }
.radio-tile-label.active-success input:checked + i { font-size: 22px; }
.radio-tile-label.active-success input:checked ~ .tile-wrapper, .radio-tile-label.active-success input:checked { border-color: var(--success); background: var(--suc-lt); }

.radio-tile-label.active-danger input:checked + i, .radio-tile-label.active-danger input:checked ~ span { color: var(--danger); }
.radio-tile-label.active-danger input:checked ~ .tile-wrapper, .radio-tile-label.active-danger input:checked { border-color: var(--danger); background: var(--dan-lt); }

/* Input Group Currency prefix/suffix */
.input-group { display: flex; align-items: center; }
.input-group .form-control { border-radius: 0 7px 7px 0; }
.input-group-addon {
    background: var(--border); color: var(--dark2); padding: 10px 14px;
    font-size: 13px; font-weight: 700; border: 1px solid var(--border); border-radius: 7px 0 0 7px;
    white-space: nowrap;
}

/* Footer Action Bar */
.panel-footer { padding: 16px 24px; background: rgba(27,46,75,.2); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; }
.btn-save {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px;
    background: var(--primary); color: #fff; border: none; border-radius: 7px;
    font-family: 'Tajawal', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer;
    transition: box-shadow .2s, opacity .2s; box-shadow: 0 4px 12px rgba(67,97,238,.2);
}
.btn-save:hover { opacity: .9; }

/* ═══ ALERTS ═════════════════════════════════════════════════ */
.xato-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; }
.xato-alert-success { background: var(--suc-lt); color: var(--success); border: 1px solid rgba(0,171,85,.25); }
.xato-alert-danger  { background: var(--dan-lt); color: var(--danger);  border: 1px solid rgba(231,81,90,.25); }

/* ═══ RESPONSIVE ═════════════════════════════════════════════ */
@media (max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-right: 0; padding: 16px; }
}
@media (max-width: 768px) {
    .settings-container { grid-template-columns: 1fr; }
    .settings-tabs { flex-direction: row; overflow-x: auto; white-space: nowrap; }
    .tab-btn { justify-content: center; padding: 10px 16px; }
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

    <a href="/local_services/index.php" class="nav-icon" title="الموقع الرئيسي" target="_blank"><i class="las la-external-link-alt"></i></a>
    <a href="/local_services/logout.php" class="nav-icon" title="خروج" style="color:var(--danger);"><i class="las la-sign-out-alt"></i></a>
    <?php $me = getCurrentUser(); if ($me): ?>
    <div class="nav-user">
        <div class="nav-avatar"><?php echo mb_substr($me['full_name'], 0, 1); ?></div>
        <div>
            <div class="nav-uname"><?php echo htmlspecialchars($me['full_name']); ?></div>
            <div class="nav-urole"><i class="las la-crown"></i> مدير النظام</div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main">

    <div class="page-header">
        <div class="page-title">
            <h1><i class="las la-cog" style="color:var(--primary);margin-left:8px;font-size:24px;"></i> إعدادات النظام</h1>
            <div class="breadcrumb">
                <a href="/local_services/dashboard.php">الرئيسية</a>
                <sep>/</sep>
                <span>الإعدادات العامة</span>
            </div>
        </div>
    </div>

    <?php if (function_exists('display_message')) display_message(); ?>
    <?php if (!empty($errors)): ?>
        <div class="xato-alert xato-alert-danger">
            <i class="las la-exclamation-triangle" style="font-size:18px;"></i>
            <?php echo htmlspecialchars($errors[0]); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="settings-container">
            
            <div class="settings-tabs">
                <button type="button" class="tab-btn active" data-target="general-panel"><i class="las la-sliders-h"></i> عام للموقع</button>
                <button type="button" class="tab-btn" data-target="financial-panel"><i class="las la-wallet"></i> المالية والأسعار</button>
                <button type="button" class="tab-btn" data-target="seo-panel"><i class="las la-search-plus"></i> محركات البحث SEO</button>
            </div>

            <div class="settings-content">

                <div class="settings-panel active" id="general-panel">
                    <div class="panel-header">
                        <i class="las la-sliders-h"></i>
                        <h3>الإعدادات العامة للموقع</h3>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="form-label">اسم الموقع الإلكتروني</label>
                            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($get_setting('site_name', 'منصة خدماتي المحلية')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني للإدارة العامة</label>
                            <input type="email" name="site_email" class="form-control" value="<?php echo htmlspecialchars($get_setting('site_email', 'admin@services.local')); ?>" required>
                            <div class="form-text">يُستخدم هذا البريد لإرسال الإشعارات واستقبال رسائل الدعم الفني للبرنامج.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">حالة عمل المنصة الحالية</label>
                            <div class="status-toggle-group">
                                <label class="radio-tile-label active-success <?php echo $get_setting('site_status', '1') === '1' ? 'checked' : ''; ?>" style="border-color: <?php echo $get_setting('site_status', '1') === '1' ? 'var(--success)' : 'var(--border)'; ?>; background: <?php echo $get_setting('site_status', '1') === '1' ? 'var(--suc-lt)' : 'transparent'; ?>;">
                                    <input type="radio" name="site_status" value="1" <?php echo $get_setting('site_status', '1') === '1' ? 'checked' : ''; ?>>
                                    <i class="las la-check-circle" style="color: <?php echo $get_setting('site_status', '1') === '1' ? 'var(--success)' : 'var(--muted)'; ?>;"></i>
                                    <span style="color: <?php echo $get_setting('site_status', '1') === '1' ? 'var(--success)' : 'var(--muted)'; ?>;">مفتوح ومتاح للجميع</span>
                                </label>
                                <label class="radio-tile-label active-danger <?php echo $get_setting('site_status', '1') === '0' ? 'checked' : ''; ?>" style="border-color: <?php echo $get_setting('site_status', '1') === '0' ? 'var(--danger)' : 'var(--border)'; ?>; background: <?php echo $get_setting('site_status', '1') === '0' ? 'var(--dan-lt)' : 'transparent'; ?>;">
                                    <input type="radio" name="site_status" value="0" <?php echo $get_setting('site_status', '1') === '0' ? 'checked' : ''; ?>>
                                    <i class="las la-lock" style="color: <?php echo $get_setting('site_status', '1') === '0' ? 'var(--danger)' : 'var(--muted)'; ?>;"></i>
                                    <span style="color: <?php echo $get_setting('site_status', '1') === '0' ? 'var(--danger)' : 'var(--muted)'; ?>;">مغلق للصيانة المؤقتة</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رسالة الإغلاق (في حال تفعيل الصيانة)</label>
                            <textarea name="close_message" class="form-control" rows="3"><?php echo htmlspecialchars($get_setting('close_message', 'المنصة مغلقة حالياً لأعمال الصيانة الدورية، سنعود قريباً!')); ?></textarea>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit" class="btn-save"><i class="las la-save"></i> حفظ التغييرات</button>
                    </div>
                </div>

                <div class="settings-panel" id="financial-panel">
                    <div class="panel-header">
                        <i class="las la-wallet"></i>
                        <h3>الإعدادات المالية وباقات الأسعار</h3>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="form-label">رمز العملة الافتراضية</label>
                            <input type="text" name="currency" class="form-control" value="<?php echo htmlspecialchars($get_setting('currency', '₪')); ?>" placeholder="مثال: ₪ أو $ أو د.أ">
                        </div>
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">الحد الأدنى لطلب خدمة</label>
                                <div class="input-group">
                                    <input type="number" name="min_order_price" class="form-control" value="<?php echo htmlspecialchars($get_setting('min_order_price', '50')); ?>">
                                    <span class="input-group-addon"><?php echo htmlspecialchars($get_setting('currency', '₪')); ?></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">نسبة الرسوم والضرائب (إن وجدت)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" name="tax_percentage" class="form-control" value="<?php echo htmlspecialchars($get_setting('tax_percentage', '0')); ?>">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit" class="btn-save"><i class="las la-save"></i> حفظ التغييرات</button>
                    </div>
                </div>

                <div class="settings-panel" id="seo-panel">
                    <div class="panel-header">
                        <i class="las la-search-plus"></i>
                        <h3>إعدادات الأرشفة والـ SEO</h3>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label class="form-label">الوصف العام للموقع (Meta Description)</label>
                            <textarea name="meta_description" class="form-control" rows="3" placeholder="اكتب وصفاً مختصراً يظهر في نتائج بحث جوجل..."><?php echo htmlspecialchars($get_setting('meta_description', 'منصة إلكترونية لتقديم وحجز الخدمات المحلية والمنزلية بكل سهولة وأمان.')); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكلمات الدلالية المفتاحية (Keywords)</label>
                            <input type="text" name="meta_keywords" class="form-control" value="<?php echo htmlspecialchars($get_setting('meta_keywords', 'خدمات, صيانة, كهرباء, سباكة, حجز خدمات منزلية')); ?>" placeholder="افصل بين الكلمات بفاصلة (,)">
                        </div>
                        <div class="form-group">
                            <label class="form-label">نص الحقوق أسفل الموقع (Footer)</label>
                            <input type="text" name="footer_text" class="form-control" value="<?php echo htmlspecialchars($get_setting('footer_text', 'جميع الحقوق محفوظة © ' . date('Y') . ' لمنصة خدماتي')); ?>">
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit" class="btn-save"><i class="las la-save"></i> حفظ التغييرات</button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const tabButtons    = document.querySelectorAll('.tab-btn');
    const panels        = document.querySelectorAll('.settings-panel');
    const radioLabels   = document.querySelectorAll('.radio-tile-label');

    // 1. التنقل السلس بين تابات الإعدادات
    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');

            // إزالة الكلاس الفعال من الأزرار واللوحات
            tabButtons.forEach(btn => btn.classList.remove('active'));
            panels.forEach(p => p.classList.remove('active'));

            // إضافة الكلاس الفعال للعنصر الحالي
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // 2. تغيير مظهر واجهة راديو الحالة (مفتوح / مغلق) بشكل ديناميكي عند النقر
    radioLabels.forEach(label => {
        const input = label.querySelector('input[type="radio"]');
        if (input) {
            input.addEventListener('change', function () {
                // تصفير جميع التبديلات داخل نفس الجروب
                radioLabels.forEach(lbl => {
                    const icon = lbl.querySelector('i');
                    const span = lbl.querySelector('span');
                    lbl.style.borderColor = 'var(--border)';
                    lbl.style.background = 'transparent';
                    if (icon) icon.style.color = 'var(--muted)';
                    if (span) span.style.color = 'var(--muted)';
                });

                // تلوين العنصر المختار بناءً على الكلاس الخاص به
                if (this.checked) {
                    const icon = label.querySelector('i');
                    const span = label.querySelector('span');
                    
                    if (label.classList.contains('active-success')) {
                        label.style.borderColor = 'var(--success)';
                        label.style.background = 'var(--suc-lt)';
                        if (icon) icon.style.color = 'var(--success)';
                        if (span) span.style.color = 'var(--success)';
                    } else if (label.classList.contains('active-danger')) {
                        label.style.borderColor = 'var(--danger)';
                        label.style.background = 'var(--dan-lt)';
                        if (icon) icon.style.color = 'var(--danger)';
                        if (span) span.style.color = 'var(--danger)';
                    }
                }
            });
        }
    });
});
</script>

</body>
</html>