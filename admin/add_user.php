<?php
// admin/add_user.php
// الموقع: C:\xampp\htdocs\local_services\admin\add_user.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// حماية: المدير فقط
check_login('admin');
$current_page = 'add_user';


global $pdo;
$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        set_message("خطأ أمني — أعد المحاولة.", "danger");
        header("Location: add_user.php"); exit();
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $role      = $_POST['role']           ?? 'client';
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $old = ['full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role' => $role, 'is_active' => $is_active];

    // التحققات والقيود
    if (empty($full_name)) $errors[] = "الاسم الكامل مطلوب.";
    if (empty($email)) {
        $errors[] = "البريد الإلكتروني مطلوب.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "صيغة البريد الإلكتروني غير صحيحة.";
    }
    if (empty($password)) $errors[] = "كلمة المرور مطلوبة للحساب الجديد.";
    if ($password !== $password2) $errors[] = "كلمتا المرور غير متطابقتين.";

    // فحص تكرار الإيميل
    if (empty($errors)) {
        try {
            $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $st->execute([$email]);
            if ($st->fetch()) {
                $errors[] = "البريد الإلكتروني مُسجّل مسبقاً بمستخدم آخر.";
            }
        } catch (PDOException $e) {
            $errors[] = "خطأ بالنظام: " . $e->getMessage();
        }
    }

    // إدخال البيانات في حال خلوها من الأخطاء
    if (empty($errors)) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$full_name, $email, $phone, $hashed, $role, $is_active]);

            set_message("✅ تم إنشاء الحساب الجديد بنجاح للمستخدم: $full_name", "success");
            header("Location: manage_users.php"); exit();
        } catch (PDOException $e) {
            $errors[] = "فشل حفظ البيانات: " . $e->getMessage();
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إضافة مستخدم جديد | لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
/* ============================================================
   XATO DARK — ADMIN PANEL — add_user.php
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
body   { font-family: 'Tajawal', sans-serif; background: var(--dark); color: var(--txt); direction: rtl; min-height: 100vh; font-size: 14px; overflow-x: hidden; }
a      { text-decoration: none; color: inherit; }

/* الهيكل الإنشائي الموحد للوحة التحكم */
.top-nav { position: fixed; top: 0; right: 0; left: 0; height: var(--nav-h); background: var(--card); border-bottom: 1px solid var(--border); z-index: 200; display: flex; align-items: center; padding: 0 24px; }
.sidebar { position: fixed; top: var(--nav-h); right: 0; width: var(--side-w); height: calc(100vh - var(--nav-h)); background: var(--card); border-left: 1px solid var(--border); overflow-y: auto; z-index: 100; padding: 16px 10px; transition: all 0.3s ease; }
.sidebar.collapsed { right: calc(-1 * var(--side-w)); }

.main-container { display: flex; margin-top: var(--nav-h); min-height: calc(100vh - var(--nav-h)); }
.main-content { flex: 1; margin-right: var(--side-w); padding: 28px; transition: all 0.3s ease; width: calc(100% - var(--side-w)); }
.sidebar.collapsed + .main-content { margin-right: 0; width: 100%; }

/* ═══ PAGE HEADER ════════════════════════════════════════════ */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 26px; flex-wrap: wrap; gap: 12px; }
.page-title h1 { font-size: 22px; font-weight: 900; }
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); margin-top: 5px; }
.breadcrumb a { color: var(--primary); }
.breadcrumb sep { color: var(--border); }

/* ═══ FORMS & CARDS ══════════════════════════════════════════ */
.form-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,.1); width: 100%; max-width: 850px; margin: 0 auto; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

@media (max-width: 768px) {
    .form-row { grid-template-columns: 1fr; gap: 0; }
    .main-content { margin-right: 0; padding: 16px; width: 100%; }
    .sidebar { right: calc(-1 * var(--side-w)); }
    .sidebar.open { right: 0; }
}

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 13px; font-weight: 700; color: var(--dark2); margin-bottom: 8px; text-align: right; }
.form-control {
    width: 100%; padding: 11px 14px; background: var(--dark); border: 1px solid var(--border);
    border-radius: 7px; color: var(--txt); font-family: 'Tajawal', sans-serif; font-size: 13px;
    outline: none; transition: border-color .2s, box-shadow .2s; text-align: right;
}
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67,97,238,.1); }

/* Input Pwd Wrapper */
.pwd-wrapper { position: relative; display: flex; align-items: center; }
.pwd-wrapper .form-control { padding-left: 45px; }
.pwd-toggle-btn { position: absolute; left: 12px; background: transparent; border: none; color: var(--muted); cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; height: 100%; }
.pwd-toggle-btn:hover { color: var(--txt); }

/* Generate Button */
.btn-gen { background: var(--pri-lt); color: var(--primary); border: 1px dashed var(--primary); padding: 4px 10px; font-family: 'Tajawal', sans-serif; font-size: 11px; font-weight: 700; border-radius: 4px; cursor: pointer; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; }
.btn-gen:hover { background: var(--primary); color: #fff; }

/* Switch Style */
.switch-label { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; margin-top: 5px; }
.switch-input { display: none; }
.switch-slider { width: 42px; height: 22px; background: var(--border); border-radius: 20px; position: relative; transition: background .2s; }
.switch-slider::before { content: ""; position: absolute; width: 16px; height: 16px; border-radius: 50%; background: #fff; top: 3px; right: 3px; transition: transform .2s; }
.switch-input:checked + .switch-slider { background: var(--success); }
.switch-input:checked + .switch-slider::before { transform: translateX(-20px); }

/* Footer Buttons */
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px; border-top: 1px solid var(--border); padding-top: 20px; }
.btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; background: var(--primary); color: #fff; border: none; border-radius: 7px; font-family: 'Tajawal', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity .2s; box-shadow: 0 4px 12px rgba(67,97,238,.2); }
.btn-submit:hover { opacity: .9; }
.btn-cancel { display: inline-flex; align-items: center; padding: 11px 20px; background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 7px; font-family: 'Tajawal', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer; }
.btn-cancel:hover { background: rgba(27,46,75,.3); color: var(--txt); }

/* Alerts */
.xato-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; }
.xato-alert-danger  { background: var(--dan-lt); color: var(--danger);  border: 1px solid rgba(231,81,90,.25); }

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

<!-- Theme Toggle Floating -->
<button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
        style="position:fixed;top:15px;left:20px;z-index:9999;
               width:38px;height:38px;
               background:var(--card,#0e1726);border:1px solid var(--border,#1b2e4b);
               border-radius:50%;display:flex;align-items:center;justify-content:center;
               color:var(--muted,#888ea8);font-size:18px;cursor:pointer;
               box-shadow:0 2px 10px rgba(0,0,0,.3);transition:all .2s;">
    <i class="las la-sun" id="themeIcon"></i>
</button>



<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main-container">
    <main class="main-content" id="appMain">
        <div class="page-header">
            <div class="page-title">
                <h1>إضافة مستخدم جديد</h1>
                <div class="breadcrumb">
                    <a href="/local_services/dashboard.php">الرئيسية</a>
                    <sep>/</sep>
                    <a href="manage_users.php">إدارة المستخدمين</a>
                    <sep>/</sep>
                    <span>حساب جديد</span>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="form-card" style="margin-bottom:20px; padding:14px 20px;">
                <?php foreach ($errors as $err): ?>
                    <div class="xato-alert xato-alert-danger" style="margin-bottom:8px;"><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="add_user.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="مثال: أحمد محمد" value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" placeholder="مثال: 059XXXXXXX" value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني <span style="color:var(--danger)">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="username@example.com" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">نوع وصلاحية الحساب <span style="color:var(--danger)">*</span></label>
                        <select name="role" class="form-control">
                            <option value="client" <?php echo ($old['role']??'') === 'client' ? 'selected':''; ?>>عميل (Client)</option>
                            <option value="provider" <?php echo ($old['role']??'') === 'provider' ? 'selected':''; ?>>مزود خدمة (Provider)</option>
                            <option value="admin" <?php echo ($old['role']??'') === 'admin' ? 'selected':''; ?>>مدير نظام (Admin)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">كلمة المرور <span style="color:var(--danger)">*</span></label>
                        <div class="pwd-wrapper">
                            <input type="password" name="password" id="targetPwd" class="form-control" placeholder="••••••••" required autocomplete="new-password">
                            <button type="button" class="pwd-toggle-btn" onclick="togglePwd('targetPwd', this)"><i class="las la-eye"></i></button>
                        </div>
                        <button type="button" class="btn-gen" onclick="generatePassword()"><i class="las la-key"></i> توليد كلمة مرور عشوائية آمنة</button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور <span style="color:var(--danger)">*</span></label>
                        <div class="pwd-wrapper">
                            <input type="password" name="password2" id="targetPwd2" class="form-control" placeholder="••••••••" required>
                            <button type="button" class="pwd-toggle-btn" onclick="togglePwd('targetPwd2', this)"><i class="las la-eye"></i></button>
                        </div>
                        <div id="matchOutput" style="margin-top:6px; font-size:12px; font-weight:700;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">حالة الحساب عند الإنشاء</label>
                    <label class="switch-label">
                        <input type="checkbox" name="is_active" class="switch-input" value="1" <?php echo (!isset($old['is_active']) || $old['is_active'] == 1) ? 'checked' : ''; ?>>
                        <span class="switch-slider"></span>
                        <span style="font-weight:700; font-size:13px; color: var(--txt);">تفعيل الحساب فوراً بالسيرفر</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="location.href='manage_users.php'">إلغاء</button>
                    <button type="submit" class="btn-submit"><i class="las la-user-plus"></i> إنـشـاء الـحـسـاب</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const pwdInput   = document.getElementById('targetPwd');
    const pwd2Input  = document.getElementById('targetPwd2');
    const output     = document.getElementById('matchOutput');

    function checkMatch() {
        const v1 = pwdInput.value;
        const v2 = pwd2Input.value;
        if(!v1 || !v2) { output.innerHTML = ''; return; }
        if(v1 === v2) {
            output.innerHTML = '<span style="color:var(--success)"><i class="las la-check-circle"></i> كلمتا المرور متطابقتان</span>';
        } else {
            output.innerHTML = '<span style="color:var(--danger)"><i class="las la-times-circle"></i> غير متطابقتين</span>';
        }
    }
    pwd2Input?.addEventListener('input', checkMatch);

    // Toggle Password Visibility
    window.togglePwd = function (fieldId, btn) {
        const input = document.getElementById(fieldId);
        const ico   = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            ico.className = 'las la-eye-slash';
        } else {
            input.type = 'password';
            ico.className = 'las la-eye';
        }
    };

    // Generate Password
    window.generatePassword = function () {
        const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        let pwd = '';
        for (let i = 0; i < 14; i++) {
            pwd += chars[Math.floor(Math.random() * chars.length)];
        }
        pwdInput.value  = pwd;
        pwd2Input.value = pwd;
        pwdInput.type   = 'text';
        pwd2Input.type  = 'text';
        pwdInput.dispatchEvent(new Event('input'));
        pwd2Input.dispatchEvent(new Event('input'));

        [pwdInput, pwd2Input].forEach(el => {
            el.style.borderColor = 'var(--success)';
            setTimeout(() => { el.style.borderColor = 'var(--border)'; }, 1200);
        });
    };
});
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