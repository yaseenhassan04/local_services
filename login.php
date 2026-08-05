<?php
// login.php - صفحة تسجيل الدخول

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// إذا كان المستخدم مسجلاً بالفعل
if (getCurrentUser()) {
    header("Location: /local_services/dashboard.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "الرجاء ملء جميع الحقول.";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, password_hash, role, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $res = $stmt->fetch();

        if ($res && password_verify($password, $res['password_hash'])) {
            if (!$res['is_active']) {
                $errors[] = "الحساب غير مفعّل. تواصل مع الدعم.";
            } else {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['user_id'] = $res['id'];
                header("Location: /local_services/dashboard.php");
                exit;
            }
        } else {
            $errors[] = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>تسجيل الدخول | خدماتي</title>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

    <style>
        /* ===== Variables ===== */
        :root {
            --primary:      #1B55E2;
            --primary-dark: #0d3a9e;
            --accent:       #E7515A;
            --success:      #1abc9c;
            --dark-bg:      #0e1726;
            --text-main:    #1e2a3a;
            --text-muted:   #6c7a8d;
            --border:       #e0e6f0;
            --radius:       14px;
            --radius-sm:    8px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Tajawal', sans-serif;
            direction: rtl;
            min-height: 100vh;
            display: flex;
            background: #f4f7fb;
        }

        /* ===== LEFT PANEL (Visual) ===== */
        .auth-visual {
            flex: 0 0 48%;
            background: linear-gradient(145deg, var(--dark-bg) 0%, #162033 45%, #0d2545 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px 48px;
        }

        /* Grid dots */
        .auth-visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 30px 30px;
            pointer-events: none;
        }

        /* Glow orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .orb-1 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, rgba(27,85,226,.25) 0%, transparent 65%);
            top: -120px; right: -120px;
        }

        .orb-2 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(231,81,90,.18) 0%, transparent 65%);
            bottom: -80px; left: -80px;
        }

        .visual-brand {
            position: relative;
            z-index: 2;
        }

        .visual-brand .logo {
            color: #fff;
            font-size: 26px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-dot {
            width: 10px; height: 10px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50%       { transform: scale(1.5); opacity: .6; }
        }

        .logo span { color: var(--accent); }

        /* Floating feature cards */
        .visual-cards {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .vis-card {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            backdrop-filter: blur(12px);
            border-radius: var(--radius);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform .3s;
        }

        .vis-card:hover { transform: translateX(-6px); }

        .vis-card-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .vis-card-icon.blue   { background: rgba(27,85,226,.3);  color: #7eb3ff; }
        .vis-card-icon.green  { background: rgba(26,188,156,.3);  color: #5ee8cb; }
        .vis-card-icon.red    { background: rgba(231,81,90,.3);   color: #f88a92; }

        .vis-card-text h4 {
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .vis-card-text p {
            color: rgba(255,255,255,.5);
            font-size: 12px;
        }

        .visual-footer {
            position: relative;
            z-index: 2;
            color: rgba(255,255,255,.35);
            font-size: 13px;
        }

        /* ===== RIGHT PANEL (Form) ===== */
        .auth-form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 48px 40px;
            background: #fff;
        }

        .auth-form-wrap {
            width: 100%;
            max-width: 420px;
        }

        .auth-form-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .auth-form-header .badge-welcome {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(27,85,226,.08);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 50px;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .auth-form-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .auth-form-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Error box */
        .error-box {
            background: #fff5f5;
            border: 1.5px solid #fca5a5;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .error-box i {
            color: var(--accent);
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .error-box ul {
            list-style: none;
            padding: 0;
        }

        .error-box li {
            font-size: 13px;
            color: #b91c1c;
            font-weight: 500;
        }

        /* Form fields */
        .field-group {
            margin-bottom: 18px;
        }

        .field-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .field-wrap {
            position: relative;
        }

        .field-wrap i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 18px;
            pointer-events: none;
            transition: color .2s;
        }

        .field-wrap input {
            width: 100%;
            padding: 12px 44px 12px 44px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            color: var(--text-main);
            background: #f8faff;
            outline: none;
            transition: all .2s;
        }

        .field-wrap input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(27,85,226,.1);
        }

        .field-wrap input:focus + i,
        .field-wrap:focus-within i { color: var(--primary); }

        /* Password toggle */
        .toggle-pass {
            position: absolute !important;
            left: 14px !important;
            right: auto !important;
            pointer-events: all !important;
            cursor: pointer !important;
        }

        /* Remember row */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .custom-check {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .custom-check input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .custom-check span {
            font-size: 13px;
            color: var(--text-muted);
        }

        .forgot-link {
            font-size: 13px;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: color .2s;
        }

        .forgot-link:hover { color: var(--primary-dark); text-decoration: underline; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'Tajawal', sans-serif;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all .25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(27,85,226,.3);
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(27,85,226,.45);
        }

        .btn-login:active { transform: translateY(0); }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: 12px;
            color: var(--text-muted);
            white-space: nowrap;
        }

        /* Register link */
        .auth-footer-link {
            text-align: center;
            font-size: 14px;
            color: var(--text-muted);
            padding-top: 20px;
            border-top: 1px solid var(--border);
            margin-top: 8px;
        }

        .auth-footer-link a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .auth-footer-link a:hover { text-decoration: underline; }

        /* ===== Responsive ===== */
        @media (max-width: 900px) {
            .auth-visual { display: none; }
            .auth-form-panel { padding: 40px 24px; }
        }

        @media (max-width: 480px) {
            .auth-form-panel { padding: 32px 20px; }
        }

        /* ===== Page load animation ===== */
        .auth-form-wrap {
            animation: slide-up .5s cubic-bezier(.4,0,.2,1) both;
        }

        @keyframes slide-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<!-- ===== VISUAL PANEL ===== -->
<div class="auth-visual">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="visual-brand">
        <a href="/local_services/index.php" class="logo">
            <div class="logo-dot"></div>
            خدماتي<span>.</span>
        </a>
    </div>

    <div class="visual-cards">
        <div class="vis-card">
            <div class="vis-card-icon blue">
                <i class="las la-shield-alt"></i>
            </div>
            <div class="vis-card-text">
                <h4>مزودون موثّقون</h4>
                <p>جميع مقدمي الخدمة يخضعون للتحقق</p>
            </div>
        </div>
        <div class="vis-card">
            <div class="vis-card-icon green">
                <i class="las la-lock"></i>
            </div>
            <div class="vis-card-text">
                <h4>بيانات آمنة ومشفّرة</h4>
                <p>حساباتك محمية بأحدث تقنيات التشفير</p>
            </div>
        </div>
        <div class="vis-card">
            <div class="vis-card-icon red">
                <i class="las la-bolt"></i>
            </div>
            <div class="vis-card-text">
                <h4>وصول فوري للخدمات</h4>
                <p>سجّل دخولك وابدأ في ثوانٍ</p>
            </div>
        </div>
    </div>

    <div class="visual-footer">
        © <?php echo date('Y'); ?> خدماتي — جميع الحقوق محفوظة
    </div>
</div>

<!-- ===== FORM PANEL ===== -->
<div class="auth-form-panel">
    <div class="auth-form-wrap">

        <div class="auth-form-header">
            <div class="badge-welcome">
                <i class="las la-user-circle"></i>
                مرحباً بعودتك
            </div>
            <h2>تسجيل الدخول</h2>
            <p>أدخل بياناتك للوصول إلى حسابك</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <i class="las la-exclamation-circle"></i>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">

            <div class="field-group">
                <label for="email">البريد الإلكتروني</label>
                <div class="field-wrap">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="example@mail.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                        autocomplete="email"
                    >
                    <i class="las la-envelope"></i>
                </div>
            </div>

            <div class="field-group">
                <label for="password">كلمة المرور</label>
                <div class="field-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <i class="las la-lock"></i>
                    <i class="las la-eye toggle-pass" id="togglePass" title="إظهار / إخفاء"></i>
                </div>
            </div>

            <div class="remember-row">
                <label class="custom-check">
                    <input type="checkbox" name="remember">
                    <span>تذكّرني</span>
                </label>
                <a href="#" class="forgot-link">نسيت كلمة المرور؟</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="las la-sign-in-alt"></i>
                دخول
            </button>

        </form>

        <div class="divider"><span>أو</span></div>

        <div class="auth-footer-link">
            ليس لديك حساب؟
            <a href="/local_services/register.php">سجّل الآن مجاناً</a>
        </div>

        <div class="auth-footer-link" style="margin-top:12px;">
            <a href="/local_services/index.php" style="color:var(--text-muted);font-weight:500;">
                <i class="las la-arrow-right"></i>
                العودة للرئيسية
            </a>
        </div>

    </div>
</div>

<script>
    // Toggle password visibility
    const togglePass = document.getElementById('togglePass');
    const passInput  = document.getElementById('password');
    togglePass.addEventListener('click', () => {
        const isHidden = passInput.type === 'password';
        passInput.type = isHidden ? 'text' : 'password';
        togglePass.className = isHidden
            ? 'las la-eye-slash toggle-pass'
            : 'las la-eye toggle-pass';
    });
</script>

</body>
</html>