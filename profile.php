<?php
// /profile.php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
global $pdo;

$user       = getCurrentUser();
$user_id    = $user['id'];
$csrf_token = generateCsrfToken();

$errors = [];
$full_name = htmlspecialchars($user['full_name'] ?? '');
$email     = htmlspecialchars($user['email'] ?? '');
$city      = htmlspecialchars($user['city'] ?? '');
$phone     = htmlspecialchars($user['phone'] ?? '');

// ==========================================================
// 1. تحديث البيانات الشخصية
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "خطأ في أمان النموذج. حاول مرة أخرى.";
    }
    $new_full_name = trim($_POST['full_name'] ?? '');
    $new_city      = trim($_POST['city'] ?? '');
    $new_phone     = trim($_POST['phone'] ?? '');

    if (empty($new_full_name)) $errors[] = "يجب إدخال الاسم الكامل.";
    if (empty($new_city))      $errors[] = "يجب تحديد المدينة.";
    if (empty($new_phone))     $errors[] = "يجب إدخال رقم الهاتف.";

    if (empty($errors)) {
        try {
            $pdo->prepare("UPDATE users SET full_name=?, city=?, phone=? WHERE id=?")
                ->execute([$new_full_name, $new_city, $new_phone, $user_id]);
            $_SESSION['user']['full_name'] = $new_full_name;
            $_SESSION['user']['city']      = $new_city;
            $_SESSION['user']['phone']     = $new_phone;
            set_message("✅ تم تحديث الملف الشخصي بنجاح.", 'success');
            header("Location: profile.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ في قاعدة البيانات.";
        }
    }
    $full_name = htmlspecialchars($new_full_name);
    $city      = htmlspecialchars($new_city);
    $phone     = htmlspecialchars($new_phone);
}

// ==========================================================
// 2. تحديث كلمة المرور
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "خطأ في أمان النموذج. حاول مرة أخرى.";
    }
    $current_password  = $_POST['current_password'] ?? '';
    $new_password      = $_POST['new_password'] ?? '';
    $confirm_password  = $_POST['confirm_password'] ?? '';

    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "كلمة المرور الحالية غير صحيحة.";
    }
    if (strlen($new_password) < 6) {
        $errors[] = "يجب أن تكون كلمة المرور الجديدة 6 أحرف على الأقل.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "كلمتا المرور غير متطابقتين.";
    }
    if (empty($errors)) {
        try {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $user_id]);
            set_message("✅ تم تحديث كلمة المرور. يرجى تسجيل الدخول مجدداً.", 'success');
            session_destroy();
            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "حدث خطأ في قاعدة البيانات.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>الملف الشخصي | خدماتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --dark-bg:  #060818;
            --card-bg:  #0e1726;
            --border:   #1b2e4b;
            --text:     #e0e6ed;
            --muted:    #888ea8;
            --primary:  #4361ee;
            --success:  #00ab55;
            --warning:  #e2a03f;
            --danger:   #e7515a;
            --purple:   #805dca;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: var(--dark-bg); color: var(--text); direction: rtl; margin: 0; }

        .top-nav {
            background: var(--card-bg); border-bottom: 1px solid var(--border);
            padding: 0 24px; height: 64px;
            display: flex; align-items: center; gap: 15px;
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .nav-brand .ico { width:34px;height:34px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px; }
        .nav-brand span { font-size: 17px; font-weight: 800; color: var(--text); }
        .nav-spacer { flex: 1; }
        .nav-link-item { color:var(--muted);text-decoration:none;padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600;transition:all .2s; }
        .nav-link-item:hover { background:rgba(67,97,238,.15);color:var(--primary);text-decoration:none; }

        .page-container { max-width: 900px; margin: 30px auto; padding: 0 24px; }

        /* PROFILE HEADER */
        .profile-header-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .profile-cover {
            height: 120px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
            position: relative;
        }
        .profile-cover-body {
            padding: 0 24px 20px;
            display: flex; align-items: flex-end; gap: 16px;
        }
        .avatar-wrap {
            margin-top: -40px; position: relative;
        }
        .avatar-circle {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border: 4px solid var(--card-bg);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 800; color: #fff;
        }
        .profile-header-info { padding-top: 10px; }
        .profile-header-info h2 { font-size: 20px; font-weight: 800; margin: 0 0 4px; }
        .profile-header-info p { font-size: 13px; color: var(--muted); margin: 0; }
        .profile-stats {
            display: flex; gap: 24px; margin-right: auto; padding-top: 10px;
        }
        .profile-stat { text-align: center; }
        .profile-stat .val { font-size: 20px; font-weight: 800; color: var(--primary); }
        .profile-stat .lbl { font-size: 11px; color: var(--muted); }

        /* TABS */
        .profile-tabs {
            display: flex; gap: 0;
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            margin-bottom: 24px;
        }
        .tab-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 12px 16px;
            border: none; background: none;
            color: var(--muted); font-family: 'Tajawal', sans-serif;
            font-size: 14px; font-weight: 600; cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all .2s;
            margin-bottom: -1px;
        }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-btn:hover { color: var(--text); }

        /* TAB PANELS */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* CARD */
        .xato-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .xato-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
        }
        .xato-card-header h5 { margin: 0; font-size: 15px; font-weight: 700; }
        .xato-card-header i { color: var(--primary); font-size: 18px; }
        .xato-card-body { padding: 24px; }

        /* FORM */
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group-x { margin-bottom: 18px; }
        .form-group-x label {
            display: block; font-size: 13px; font-weight: 700;
            color: var(--text); margin-bottom: 7px;
        }
        .form-group-x label i { color: var(--primary); margin-left: 5px; }
        .form-input {
            width: 100%;
            background: var(--dark-bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: 11px 14px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            transition: border-color .2s;
        }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67,97,238,.15); }
        .form-input:disabled { opacity: .5; cursor: not-allowed; }
        .form-input.error { border-color: var(--danger); }

        .btn-save {
            padding: 11px 24px;
            background: var(--primary);
            color: #fff; border: none; border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: opacity .2s;
            box-shadow: 0 4px 15px rgba(67,97,238,.3);
        }
        .btn-save:hover { opacity: .85; }
        .btn-danger-save {
            padding: 11px 24px;
            background: var(--danger);
            color: #fff; border: none; border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: opacity .2s;
            box-shadow: 0 4px 15px rgba(231,81,90,.3);
        }
        .btn-danger-save:hover { opacity: .85; }

        /* ALERTS */
        .xato-alert {
            padding: 13px 16px; border-radius: 8px;
            margin-bottom: 20px; font-size: 13px;
            display: flex; align-items: center; gap: 10px;
        }
        .xato-alert-success { background: rgba(0,171,85,.12); color: var(--success); border: 1px solid rgba(0,171,85,.3); }
        .xato-alert-danger  { background: rgba(231,81,90,.12); color: var(--danger);  border: 1px solid rgba(231,81,90,.3); }

        /* PASSWORD STRENGTH */
        .strength-bar { height: 4px; border-radius: 4px; margin-top: 6px; background: var(--border); overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }
        .strength-label { font-size: 11px; color: var(--muted); margin-top: 4px; }

        @media (max-width: 600px) {
            .form-row-2 { grid-template-columns: 1fr; }
            .profile-stats { display: none; }
            .profile-cover-body { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<nav class="top-nav">
    <a href="/local_services/index.php" class="nav-brand">
        <div class="ico"><i class="las la-map-marker"></i></div>
        <span>خدماتي</span>
    </a>
    <div class="nav-spacer"></div>
    <a href="/local_services/client_dashboard.php" class="nav-link-item"><i class="las la-tachometer-alt"></i> لوحة التحكم</a>
    <a href="/local_services/client/services.php" class="nav-link-item"><i class="las la-th-large"></i> الخدمات</a>
    <a href="/local_services/logout.php" class="nav-link-item" style="color:#e7515a;"><i class="las la-sign-out-alt"></i> خروج</a>
</nav>

<div class="page-container">

    <!-- ALERTS -->
    <?php if (!empty($errors)): ?>
        <div class="xato-alert xato-alert-danger">
            <i class="las la-exclamation-triangle" style="font-size:18px;flex-shrink:0;"></i>
            <div><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        </div>
    <?php endif; ?>
    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- PROFILE HEADER -->
    <div class="profile-header-card">
        <div class="profile-cover"></div>
        <div class="profile-cover-body">
            <div class="avatar-wrap">
                <div class="avatar-circle"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
            </div>
            <div class="profile-header-info">
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p><i class="las la-envelope" style="color:var(--primary);"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="val"><?php echo htmlspecialchars($user['city'] ?? '-'); ?></div>
                    <div class="lbl">المدينة</div>
                </div>
                <div class="profile-stat">
                    <div class="val">عميل</div>
                    <div class="lbl">نوع الحساب</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="profile-tabs">
        <button class="tab-btn active" onclick="switchTab('personal', this)">
            <i class="las la-user"></i> البيانات الشخصية
        </button>
        <button class="tab-btn" onclick="switchTab('password', this)" id="tab-password-btn">
            <i class="las la-lock"></i> كلمة المرور
        </button>
    </div>

    <!-- TAB: Personal Info -->
    <div class="tab-panel active" id="panel-personal">
        <div class="xato-card">
            <div class="xato-card-header">
                <i class="las la-user-edit"></i>
                <h5>تعديل البيانات الشخصية</h5>
            </div>
            <div class="xato-card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-row-2">
                        <div class="form-group-x">
                            <label><i class="las la-user"></i> الاسم الكامل</label>
                            <input class="form-input" type="text" name="full_name"
                                   value="<?php echo $full_name; ?>" required placeholder="أدخل اسمك الكامل">
                        </div>
                        <div class="form-group-x">
                            <label><i class="las la-envelope"></i> البريد الإلكتروني</label>
                            <input class="form-input" type="email" value="<?php echo $email; ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group-x">
                            <label><i class="las la-phone"></i> رقم الهاتف</label>
                            <input class="form-input" type="text" name="phone"
                                   value="<?php echo $phone; ?>" required placeholder="05xxxxxxxx">
                        </div>
                        <div class="form-group-x">
                            <label><i class="las la-city"></i> المدينة</label>
                            <input class="form-input" type="text" name="city"
                                   value="<?php echo $city; ?>" required placeholder="اسم المدينة">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; align-items:center; margin-top:8px;">
                        <button type="submit" class="btn-save">
                            <i class="las la-save"></i> حفظ التغييرات
                        </button>
                        <a href="client_dashboard.php" style="color:var(--muted);font-size:13px;text-decoration:none;">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: Password -->
    <div class="tab-panel" id="panel-password">
        <div class="xato-card">
            <div class="xato-card-header">
                <i class="las la-lock"></i>
                <h5>تغيير كلمة المرور</h5>
            </div>
            <div class="xato-card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="update_password" value="1">

                    <div class="form-group-x">
                        <label><i class="las la-lock"></i> كلمة المرور الحالية</label>
                        <input class="form-input" type="password" name="current_password" required placeholder="أدخل كلمة المرور الحالية">
                    </div>

                    <div class="form-row-2">
                        <div class="form-group-x">
                            <label><i class="las la-key"></i> كلمة المرور الجديدة</label>
                            <input class="form-input" type="password" name="new_password"
                                   id="newPassInput" required minlength="6"
                                   placeholder="6 أحرف على الأقل"
                                   oninput="checkStrength(this.value)">
                            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                            <div class="strength-label" id="strengthLabel">أدخل كلمة المرور</div>
                        </div>
                        <div class="form-group-x">
                            <label><i class="las la-check-double"></i> تأكيد كلمة المرور</label>
                            <input class="form-input" type="password" name="confirm_password"
                                   required placeholder="أعد إدخال كلمة المرور">
                        </div>
                    </div>

                    <div style="background:rgba(231,81,90,.08);border:1px solid rgba(231,81,90,.2);border-radius:8px;padding:12px 14px;margin-bottom:18px;">
                        <p style="font-size:12px;color:var(--muted);margin:0;">
                            <i class="las la-exclamation-circle" style="color:var(--warning);"></i>
                            بعد تغيير كلمة المرور سيتم تسجيل خروجك تلقائياً لأسباب أمنية.
                        </p>
                    </div>

                    <button type="submit" class="btn-danger-save">
                        <i class="las la-shield-alt"></i> تحديث كلمة المرور
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('panel-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // Auto-open password tab if anchor
    if (window.location.hash === '#password') {
        switchTab('password', document.getElementById('tab-password-btn'));
    }

    function checkStrength(val) {
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { w:'20%', c:'#e7515a', t:'ضعيف جداً' },
            { w:'40%', c:'#e7515a', t:'ضعيف' },
            { w:'60%', c:'#e2a03f', t:'متوسط' },
            { w:'80%', c:'#00ab55', t:'جيد' },
            { w:'100%',c:'#00ab55', t:'قوي جداً' },
        ];
        const level = levels[Math.max(0, score - 1)] || levels[0];
        fill.style.width      = val.length ? level.w : '0';
        fill.style.background = level.c;
        label.textContent     = val.length ? level.t : 'أدخل كلمة المرور';
        label.style.color     = val.length ? level.c : 'var(--muted)';
    }
</script>
</body>
</html>