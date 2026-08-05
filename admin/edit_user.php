<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

check_login('admin'); 
$current_page = 'edit_user';

global $pdo;

$errors = [];

$user_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$user_id = (int)$user_id;

if ($user_id <= 0) {
    set_message("معرف المستخدم غير صالح أو مفقود في الرابط.", "danger");
    header("Location: manage_users.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC); 

    if (!$target_user) {
        set_message("المستخدم المطلوب غير موجود.", "danger");
        header("Location: manage_users.php");
        exit();
    }
} catch (PDOException $e) {
    set_message("فشل في جلب بيانات المستخدم: " . $e->getMessage(), "danger");
    header("Location: manage_users.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email     = sanitize_input($_POST['email'] ?? '');
    $role      = sanitize_input($_POST['role'] ?? $target_user['role']);
    $is_active = (int) sanitize_input($_POST['is_active'] ?? $target_user['is_active']);
    
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $password_update_required = false;

    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = "كلمة المرور الجديدة وتأكيدها غير متطابقين.";
        } elseif (strlen($new_password) < 6) { 
            $errors[] = "يجب أن لا تقل كلمة المرور عن 6 أحرف.";
        } else {
            $password_update_required = true;
        }
    }
    
    if (empty($full_name) || empty($email)) {
        $errors[] = "يجب ملء حقل الاسم والبريد الإلكتروني.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "صيغة البريد الإلكتروني غير صحيحة.";
    }

    if (empty($errors)) {
        try {
            if ($password_update_required) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_pass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt_pass->execute([$hashed_password, $user_id]);
            }

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $role, $is_active, $user_id]);
            
            if ($user_id === (int)($_SESSION['user_id'] ?? 0)) {
                $_SESSION['user_role'] = $role;
                $_SESSION['is_active'] = $is_active;
            }
            
            set_message("تم تحديث بيانات المستخدم '{$full_name}' بنجاح!" . ($password_update_required ? " وتم تحديث كلمة المرور." : ""), "success");
            header("Location: manage_users.php");
            exit();
            
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                 $errors[] = "البريد الإلكتروني '{$email}' مستخدم بالفعل.";
            } else {
                 $errors[] = "خطأ في قاعدة البيانات أثناء التحديث.";
            }
        }
    }
    
    $target_user['full_name'] = $full_name;
    $target_user['email']     = $email;
    $target_user['role']      = $role;
    $target_user['is_active'] = $is_active;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>تعديل المستخدم | لوحة التحكم</title>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

    <style>
        :root {
            --primary:      #1B55E2;
            --primary-dark: #0d3a9e;
            --accent:       #E7515A;
            --success:      #1abc9c;
            --warning:      #e2a03f;
            --dark-bg:      #0e1726;
            --dark-card:    #131f30;
            --dark-surface: #1a2941;
            --dark-border:  rgba(255,255,255,.08);
            --text-main:    #e0e6f0;
            --text-muted:   rgba(255,255,255,.45);
            --radius:       14px;
            --radius-sm:    8px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--dark-bg);
            color: var(--text-main);
            direction: rtl;
            min-height: 100vh;
        }

        a { text-decoration: none; color: inherit; }

        /* ===== NAVBAR ===== */
        .kh-navbar {
            background: var(--dark-card);
            border-bottom: 1px solid var(--dark-border);
            padding: 0 28px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }
        .brand span { color: var(--accent); }

        .nav-breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .nav-breadcrumb a { color: var(--text-muted); transition: color .2s; }
        .nav-breadcrumb a:hover { color: var(--primary); }
        .nav-breadcrumb i { font-size: 10px; }

        /* ===== PAGE WRAPPER ===== */
        .page-wrapper {
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 48px 20px;
            background:
                radial-gradient(ellipse at 10% 20%, rgba(27,85,226,.12) 0%, transparent 50%),
                radial-gradient(ellipse at 90% 80%, rgba(231,81,90,.08) 0%, transparent 50%),
                var(--dark-bg);
        }

        .edit-container {
            width: 100%;
            max-width: 720px;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header .user-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 8px;
        }

        .user-avatar-lg {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), #4a82f0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(27,85,226,.35);
        }

        .page-header h1 {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin: 0 0 4px;
        }

        .page-header .user-id-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(27,85,226,.15);
            border: 1px solid rgba(27,85,226,.3);
            color: #7eb3ff;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 50px;
        }

        /* ===== ALERTS ===== */
        .kh-alert {
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            line-height: 1.6;
            animation: slideDown .3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .kh-alert-danger {
            background: rgba(231,81,90,.12);
            border: 1px solid rgba(231,81,90,.3);
            color: #ff8a91;
        }

        .kh-alert-danger i {
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .kh-alert ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .kh-alert ul li::before {
            content: '• ';
            opacity: .6;
        }

        /* ===== CARD ===== */
        .edit-card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,.4);
        }

        .card-section {
            padding: 28px 32px;
            border-bottom: 1px solid var(--dark-border);
        }

        .card-section:last-child {
            border-bottom: none;
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--dark-border);
        }

        .section-label i {
            font-size: 16px;
            color: var(--primary);
        }

        /* ===== FORM FIELDS ===== */
        .field-group {
            margin-bottom: 20px;
        }

        .field-group:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: rgba(255,255,255,.7);
            margin-bottom: 8px;
            letter-spacing: .3px;
        }

        .kh-input,
        .kh-select {
            width: 100%;
            background: var(--dark-surface);
            border: 1.5px solid var(--dark-border);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            color: var(--text-main);
            font-family: 'Tajawal', sans-serif;
            font-size: 15px;
            font-weight: 500;
            outline: none;
            transition: all .2s;
            -webkit-appearance: none;
        }

        .kh-input::placeholder { color: var(--text-muted); }

        .kh-input:focus,
        .kh-select:focus {
            border-color: var(--primary);
            background: rgba(27,85,226,.08);
            box-shadow: 0 0 0 3px rgba(27,85,226,.15);
        }

        .kh-select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='7'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23ffffff50' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 14px center;
            padding-left: 36px;
        }

        .kh-select option {
            background: var(--dark-card);
            color: var(--text-main);
        }

        /* Input with icon */
        .input-wrap {
            position: relative;
        }

        .input-wrap i.field-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .input-wrap .kh-input,
        .input-wrap .kh-select {
            padding-right: 44px;
        }

        .input-wrap .toggle-pw {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 18px;
            padding: 0;
            transition: color .2s;
        }

        .input-wrap .toggle-pw:hover { color: var(--primary); }

        /* ===== ROLE SELECTOR (radio cards) ===== */
        .role-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .role-card input[type="radio"] { display: none; }

        .role-card label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 12px;
            border: 1.5px solid var(--dark-border);
            border-radius: var(--radius-sm);
            background: var(--dark-surface);
            cursor: pointer;
            text-align: center;
            transition: all .2s;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0;
            text-transform: none;
            margin: 0;
        }

        .role-card label i {
            font-size: 22px;
            color: var(--text-muted);
            transition: color .2s;
        }

        .role-card input[type="radio"]:checked + label {
            border-color: var(--primary);
            background: rgba(27,85,226,.12);
            color: #7eb3ff;
        }

        .role-card input[type="radio"]:checked + label i {
            color: var(--primary);
        }

        /* ===== STATUS TOGGLE ===== */
        .status-toggle-wrap {
            display: flex;
            gap: 12px;
        }

        .status-opt input[type="radio"] { display: none; }

        .status-opt label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border: 1.5px solid var(--dark-border);
            border-radius: 50px;
            background: var(--dark-surface);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0;
            text-transform: none;
            margin: 0;
            transition: all .2s;
        }

        .status-opt label .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            opacity: .4;
            transition: opacity .2s;
        }

        .status-opt.active input[type="radio"]:checked + label,
        .status-opt input[type="radio"]:checked + label {
            border-color: transparent;
        }

        .status-opt.is-active input[type="radio"]:checked + label {
            background: rgba(26,188,156,.15);
            border-color: rgba(26,188,156,.4);
            color: var(--success);
        }

        .status-opt.is-active input[type="radio"]:checked + label .dot {
            opacity: 1;
            background: var(--success);
        }

        .status-opt.is-banned input[type="radio"]:checked + label {
            background: rgba(231,81,90,.12);
            border-color: rgba(231,81,90,.3);
            color: var(--accent);
        }

        .status-opt.is-banned input[type="radio"]:checked + label .dot {
            opacity: 1;
            background: var(--accent);
        }

        /* ===== PASSWORD HINT ===== */
        .pw-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pw-strength {
            display: none;
            gap: 4px;
            margin-top: 8px;
        }

        .pw-strength.visible { display: flex; }

        .pw-strength-bar {
            height: 3px;
            flex: 1;
            border-radius: 2px;
            background: var(--dark-border);
            transition: background .3s;
        }

        /* ===== CARD FOOTER (actions) ===== */
        .card-footer-actions {
            padding: 24px 32px;
            background: rgba(255,255,255,.02);
            border-top: 1px solid var(--dark-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-save {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 12px 32px;
            font-family: 'Tajawal', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all .25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(27,85,226,.4);
        }

        .btn-save i { font-size: 18px; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            background: none;
            border: 1.5px solid var(--dark-border);
            border-radius: 50px;
            padding: 10px 20px;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            transition: all .2s;
            text-decoration: none;
        }

        .btn-back:hover {
            color: var(--text-main);
            border-color: rgba(255,255,255,.2);
            background: rgba(255,255,255,.05);
        }

        /* ===== ROW COLS ===== */
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 560px) {
            .field-row { grid-template-columns: 1fr; }
            .role-cards { grid-template-columns: 1fr 1fr 1fr; }
            .card-section { padding: 20px; }
            .card-footer-actions { padding: 20px; }
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

<!-- ===== NAVBAR ===== -->
<nav class="kh-navbar">
    <div class="brand">
        خدماتي<span>.</span>
    </div>
    <div class="nav-breadcrumb">
        <a href="/local_services/admin/dashboard.php">لوحة التحكم</a>
        <i class="las la-angle-left"></i>
        <a href="manage_users.php">إدارة المستخدمين</a>
        <i class="las la-angle-left"></i>
        <span>تعديل المستخدم</span>
    </div>
    <button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
            style="margin-right:auto;width:36px;height:36px;background:rgba(255,255,255,.1);
                   border:1px solid rgba(255,255,255,.2);border-radius:8px;
                   display:flex;align-items:center;justify-content:center;
                   color:#fff;font-size:17px;cursor:pointer;transition:all .2s;flex-shrink:0;">
        <i class="las la-sun" id="themeIcon"></i>
    </button>
</nav>
<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<!-- ===== PAGE ===== -->
<div class="page-wrapper">
    <div class="edit-container">

        <!-- Page Header -->
        <div class="page-header">
            <div class="user-meta">
                <div class="user-avatar-lg">
                    <?= mb_substr(htmlspecialchars($target_user['full_name']), 0, 2) ?>
                </div>
                <div>
                    <h1>تعديل: <?= htmlspecialchars($target_user['full_name']) ?></h1>
                    <span class="user-id-badge">
                        <i class="las la-hashtag"></i> ID: <?= $target_user['id'] ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
        <div class="kh-alert kh-alert-danger">
            <i class="las la-exclamation-circle"></i>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Flash message from set_message() -->
        <?php if (function_exists('display_message')) display_message(); ?>

        <!-- Form Card -->
        <div class="edit-card">
            <form method="POST" action="edit_user.php?id=<?= $target_user['id'] ?>">

                <!-- Section 1: Basic Info -->
                <div class="card-section">
                    <div class="section-label">
                        <i class="las la-user"></i>
                        المعلومات الأساسية
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="full_name">الاسم الكامل</label>
                            <div class="input-wrap">
                                <i class="las la-user field-icon"></i>
                                <input type="text" id="full_name" name="full_name" class="kh-input"
                                    value="<?= htmlspecialchars($target_user['full_name']) ?>"
                                    placeholder="أدخل الاسم الكامل" required>
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="email">البريد الإلكتروني</label>
                            <div class="input-wrap">
                                <i class="las la-envelope field-icon"></i>
                                <input type="email" id="email" name="email" class="kh-input"
                                    value="<?= htmlspecialchars($target_user['email']) ?>"
                                    placeholder="example@mail.com" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Password -->
                <div class="card-section">
                    <div class="section-label">
                        <i class="las la-lock"></i>
                        تغيير كلمة المرور
                        <span style="font-size:11px;font-weight:500;color:var(--text-muted);text-transform:none;letter-spacing:0;">(اتركها فارغة إذا لم ترد التغيير)</span>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="new_password">كلمة المرور الجديدة</label>
                            <div class="input-wrap">
                                <i class="las la-key field-icon"></i>
                                <input type="password" id="new_password" name="new_password" class="kh-input"
                                    placeholder="••••••••" oninput="checkStrength(this.value)">
                                <button type="button" class="toggle-pw" onclick="togglePw('new_password', this)">
                                    <i class="las la-eye"></i>
                                </button>
                            </div>
                            <div class="pw-strength" id="pw-strength">
                                <div class="pw-strength-bar" id="s1"></div>
                                <div class="pw-strength-bar" id="s2"></div>
                                <div class="pw-strength-bar" id="s3"></div>
                                <div class="pw-strength-bar" id="s4"></div>
                            </div>
                            <div class="pw-hint"><i class="las la-info-circle"></i> ٦ أحرف على الأقل</div>
                        </div>

                        <div class="field-group">
                            <label for="confirm_password">تأكيد كلمة المرور</label>
                            <div class="input-wrap">
                                <i class="las la-key field-icon"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="kh-input"
                                    placeholder="••••••••">
                                <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)">
                                    <i class="las la-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Role -->
                <div class="card-section">
                    <div class="section-label">
                        <i class="las la-user-tag"></i>
                        دور المستخدم
                    </div>

                    <div class="role-cards">
                        <?php
                        $roles = [
                            'client'   => ['label' => 'عميل',    'icon' => 'la-user'],
                            'provider' => ['label' => 'مزود',    'icon' => 'la-briefcase'],
                            'admin'    => ['label' => 'مدير',    'icon' => 'la-user-shield'],
                        ];
                        foreach ($roles as $val => $info):
                        ?>
                        <div class="role-card">
                            <input type="radio" id="role_<?= $val ?>" name="role" value="<?= $val ?>"
                                <?= $target_user['role'] === $val ? 'checked' : '' ?>>
                            <label for="role_<?= $val ?>">
                                <i class="las <?= $info['icon'] ?>"></i>
                                <?= $info['label'] ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section 4: Status -->
                <div class="card-section">
                    <div class="section-label">
                        <i class="las la-toggle-on"></i>
                        حالة الحساب
                    </div>

                    <div class="status-toggle-wrap">
                        <div class="status-opt is-active">
                            <input type="radio" id="status_active" name="is_active" value="1"
                                <?= (int)$target_user['is_active'] === 1 ? 'checked' : '' ?>>
                            <label for="status_active">
                                <span class="dot"></span>
                                مفعّل
                            </label>
                        </div>

                        <div class="status-opt is-banned">
                            <input type="radio" id="status_banned" name="is_active" value="0"
                                <?= (int)$target_user['is_active'] === 0 ? 'checked' : '' ?>>
                            <label for="status_banned">
                                <span class="dot"></span>
                                محظور
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card-footer-actions">
                    <a href="manage_users.php" class="btn-back">
                        <i class="las la-arrow-right"></i>
                        إلغاء والعودة
                    </a>
                    <button type="submit" class="btn-save">
                        <i class="las la-save"></i>
                        حفظ التعديلات
                    </button>
                </div>

            </form>
        </div>
        <!-- /edit-card -->

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    function togglePw(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const icon  = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'las la-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'las la-eye';
        }
    }

    // Password strength indicator
    function checkStrength(val) {
        const wrap = document.getElementById('pw-strength');
        const bars = ['s1','s2','s3','s4'].map(id => document.getElementById(id));

        if (!val) {
            wrap.classList.remove('visible');
            bars.forEach(b => b.style.background = '');
            return;
        }

        wrap.classList.add('visible');

        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) || /[a-z]/.test(val)) score++;
        if (/\d/.test(val) && /[^a-zA-Z0-9]/.test(val)) score++;

        const colors = ['#E7515A', '#e2a03f', '#1abc9c', '#1B55E2'];
        bars.forEach((b, i) => {
            b.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,.08)';
        });
    }
</script>

<script>
/* ══ Theme Toggle ══ */
function toggleTheme(){
    var isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('xato_theme', isLight ? 'light' : 'dark');
    _updateThemeIcon(isLight);
}
function _updateThemeIcon(isLight){
    var ic = document.getElementById('themeIcon');
    if(ic) ic.className = isLight ? 'las la-moon' : 'las la-sun';
}
(function(){
    var saved = localStorage.getItem('xato_theme');
    if(saved === 'light'){
        document.body.classList.add('light-mode');
        document.addEventListener('DOMContentLoaded', function(){ _updateThemeIcon(true); });
    }
})();
</script>
</body>
</html>