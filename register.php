<?php
// register.php - نسخة معدلة بتصميم احترافي محدث

require_once __DIR__ . '/includes/db.php';

$errors = [];
$full_name = $email = $phone = $city = '';
$role = 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $phone     = trim($_POST['phone'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $role      = in_array($_POST['role'] ?? 'client', ['client','provider','admin']) ? $_POST['role'] : 'client';

    if ($full_name === '' || $email === '' || $password === '' || $phone === '') {
        $errors[] = "الرجاء ملء جميع الحقول الأساسية.";
    }
    if ($password !== $password2) {
        $errors[] = "كلمتا المرور غير متطابقتين.";
    }
    $pwd_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    if (!preg_match($pwd_pattern, $password)) {
        $errors[] = "كلمة المرور ضعيفة! يجب أن تحتوي على 8 خانات على الأقل، تشمل (أحرف كبيرة، أحرف صغيرة، أرقام، ورموز خاصة).";
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "رقم الهاتف غير صحيح، يجب أن يتكون من 10 أرقام فقط.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "صيغة البريد الإلكتروني غير صالحة.";
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "هذا البريد الإلكتروني مسجل بالفعل.";
        }
    }
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (full_name, email, password_hash, phone, city, role) 
                VALUES (:fn, :em, :pass, :ph, :ct, :rl)";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':fn'   => $full_name,
            ':em'   => $email,
            ':pass' => $hash,
            ':ph'   => $phone,
            ':ct'   => $city,
            ':rl'   => $role
        ];
        if ($stmt->execute($params)) {
            $user_id = $pdo->lastInsertId();
            if ($role === 'provider') {
                $pstmt = $pdo->prepare("INSERT INTO providers (user_id, bio) VALUES (:uid, :bio)");
                $pstmt->execute([':uid' => $user_id, ':bio' => '']);
            }
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION['user_id'] = $user_id;
            header("Location: /local_services/dashboard.php");
            exit;
        } else {
            $errors[] = "حدث خطأ فني أثناء التسجيل، يرجى المحاولة لاحقاً.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>إنشاء حساب جديد | خدماتي</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --accent: #f72585;
            --success: #06d6a0;
            --warning: #ffd166;
            --danger: #ef233c;
            --text: #1a1a2e;
            --text-muted: #8892a4;
            --border: #e8ecf4;
            --bg: #f5f7ff;
            --white: #ffffff;
            --card-shadow: 0 20px 60px rgba(67, 97, 238, 0.12);
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            direction: rtl;
        }

        /* ===== LAYOUT ===== */
        .register-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ===== LEFT DECORATIVE PANEL ===== */
        .panel-left {
            flex: 0 0 42%;
            background: linear-gradient(145deg, #4361ee 0%, #7209b7 60%, #f72585 100%);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 52px;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
        }

        .panel-left::after {
            content: '';
            position: absolute;
            top: -120px;
            left: -120px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }

        .panel-blob {
            position: absolute;
            bottom: -80px;
            right: -80px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }

        .panel-logo {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .panel-logo .logo-icon {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .panel-logo .logo-text {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
        }

        .panel-content {
            position: relative;
            z-index: 2;
        }

        .panel-title {
            font-size: 38px;
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
            margin-bottom: 20px;
        }

        .panel-title span {
            display: block;
            color: rgba(255,255,255,0.65);
            font-weight: 400;
            font-size: 18px;
            margin-top: 8px;
        }

        .panel-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 36px;
        }

        .panel-features li {
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255,255,255,0.9);
            font-size: 15px;
        }

        .panel-features li .feat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .panel-bottom {
            position: relative;
            z-index: 2;
        }

        .panel-bottom p {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }

        .panel-bottom a {
            color: #fff;
            font-weight: 600;
            text-decoration: underline;
            margin-right: 6px;
        }

        /* ===== RIGHT FORM PANEL ===== */
        .panel-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            overflow-y: auto;
        }

        .form-box {
            width: 100%;
            max-width: 540px;
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-header p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* ===== ERRORS ===== */
        .alert-errors {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
        }

        .alert-errors p {
            color: #c53030;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 4px;
        }

        .alert-errors p:last-child { margin-bottom: 0; }

        .alert-errors p i { margin-top: 2px; flex-shrink: 0; }

        /* ===== FORM GROUPS ===== */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 7px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .icon {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrap .toggle-pw {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 15px;
            cursor: pointer;
            pointer-events: all;
            transition: color 0.2s;
        }

        .form-control {
            width: 100%;
            padding: 11px 42px 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14.5px;
            color: var(--text);
            background: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-control.has-toggle {
            padding-left: 42px;
        }

        .form-control::placeholder { color: #c0c7d8; }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .form-control:focus ~ .icon,
        .input-wrap:focus-within .icon { color: var(--primary); }

        /* ===== PASSWORD STRENGTH ===== */
        .strength-bar {
            display: flex;
            gap: 5px;
            margin-top: 8px;
        }

        .strength-bar .seg {
            height: 4px;
            flex: 1;
            border-radius: 4px;
            background: var(--border);
            transition: background 0.3s;
        }

        .strength-label {
            font-size: 12px;
            margin-top: 5px;
            color: var(--text-muted);
            height: 16px;
        }

        /* ===== ROLE SELECTOR ===== */
        .role-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .role-card {
            position: relative;
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
        }

        .role-card label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 14px;
            color: var(--text-muted);
            background: var(--white);
        }

        .role-card label .role-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: all 0.2s;
        }

        .role-card input[type="radio"]:checked + label {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.06);
            color: var(--primary);
        }

        .role-card input[type="radio"]:checked + label .role-icon {
            background: var(--primary);
            color: #fff;
        }

        /* ===== DIVIDER ===== */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 4px 0 20px;
            color: var(--text-muted);
            font-size: 12px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ===== SUBMIT BUTTON ===== */
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--primary) 0%, #7209b7 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Tajawal', sans-serif;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.35);
            letter-spacing: 0.3px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 28px rgba(67, 97, 238, 0.4);
        }

        .btn-submit:active { transform: translateY(0); }

        /* ===== FOOTER LINK ===== */
        .form-footer {
            text-align: center;
            margin-top: 22px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-footer a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .panel-left { display: none; }
            .panel-right { padding: 32px 20px; }
        }

        @media (max-width: 500px) {
            .form-row { grid-template-columns: 1fr; }
            .role-group { grid-template-columns: 1fr; }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-box { animation: slideUp 0.45s ease both; }
    </style>
</head>
<body>

<div class="register-wrapper">

    <!-- ===== DECORATIVE LEFT PANEL ===== -->
    <div class="panel-left">
        <div class="panel-blob"></div>

        <div class="panel-logo">
            <div class="logo-icon"><i class="fas fa-handshake-simple"></i></div>
            <span class="logo-text">خدماتي</span>
        </div>

        <div class="panel-content">
            <h1 class="panel-title">
                ابدأ رحلتك<br>مع خدماتي
                <span>منصة محلية تربطك بأفضل المحترفين</span>
            </h1>

            <ul class="panel-features">
                <li>
                    <div class="feat-icon"><i class="fas fa-shield-halved"></i></div>
                    <span>خدمات موثّقة وآمنة 100%</span>
                </li>
                <li>
                    <div class="feat-icon"><i class="fas fa-bolt-lightning"></i></div>
                    <span>طلب خدمة في دقيقة واحدة</span>
                </li>
                <li>
                    <div class="feat-icon"><i class="fas fa-star"></i></div>
                    <span>تقييمات حقيقية من عملاء فعليين</span>
                </li>
                <li>
                    <div class="feat-icon"><i class="fas fa-headset"></i></div>
                    <span>دعم مستمر على مدار الساعة</span>
                </li>
            </ul>
        </div>

        <div class="panel-bottom">
            <p>لديك حساب بالفعل؟ <a href="/local_services/login.php">تسجيل الدخول</a></p>
        </div>
    </div>

    <!-- ===== FORM RIGHT PANEL ===== -->
    <div class="panel-right">
        <div class="form-box">

            <div class="form-header">
                <h2>إنشاء حساب جديد 🚀</h2>
                <p>أنشئ حسابك الآن وانضم إلى آلاف المستخدمين</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert-errors">
                <?php foreach ($errors as $e): ?>
                <p><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($e); ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" novalidate>

                <!-- Name & Email -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">الاسم الكامل</label>
                        <div class="input-wrap">
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                   placeholder="محمد أحمد" required
                                   value="<?php echo htmlspecialchars($full_name); ?>">
                            <i class="fas fa-user icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <div class="input-wrap">
                            <input type="email" id="email" name="email" class="form-control"
                                   placeholder="example@mail.com" required
                                   value="<?php echo htmlspecialchars($email); ?>">
                            <i class="fas fa-envelope icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Phone & City -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">رقم الهاتف</label>
                        <div class="input-wrap">
                            <input type="text" id="phone" name="phone" class="form-control"
                                   placeholder="05XXXXXXXX" maxlength="10" required
                                   value="<?php echo htmlspecialchars($phone); ?>">
                            <i class="fas fa-mobile-screen-button icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="city">المدينة <span style="color:var(--text-muted);font-weight:400">(اختياري)</span></label>
                        <div class="input-wrap">
                            <input type="text" id="city" name="city" class="form-control"
                                   placeholder="الرياض"
                                   value="<?php echo htmlspecialchars($city); ?>">
                            <i class="fas fa-location-dot icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" class="form-control has-toggle"
                               placeholder="8 خانات على الأقل" required>
                        <i class="fas fa-lock icon"></i>
                        <i class="fas fa-eye toggle-pw" id="togglePw1" title="إظهار/إخفاء"></i>
                    </div>
                    <div class="strength-bar" id="strengthBar">
                        <div class="seg" id="s1"></div>
                        <div class="seg" id="s2"></div>
                        <div class="seg" id="s3"></div>
                        <div class="seg" id="s4"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="password2">تأكيد كلمة المرور</label>
                    <div class="input-wrap">
                        <input type="password" id="password2" name="password2" class="form-control has-toggle"
                               placeholder="أعد إدخال كلمة المرور" required>
                        <i class="fas fa-lock icon"></i>
                        <i class="fas fa-eye toggle-pw" id="togglePw2" title="إظهار/إخفاء"></i>
                    </div>
                </div>

                <div class="divider">نوع الحساب</div>

                <!-- Role Selector -->
                <div class="form-group">
                    <div class="role-group">
                        <div class="role-card">
                            <input type="radio" id="role_client" name="role" value="client"
                                   <?php echo ($role === 'client') ? 'checked' : ''; ?>>
                            <label for="role_client">
                                <div class="role-icon"><i class="fas fa-user"></i></div>
                                طالب خدمة
                            </label>
                        </div>
                        <div class="role-card">
                            <input type="radio" id="role_provider" name="role" value="provider"
                                   <?php echo ($role === 'provider') ? 'checked' : ''; ?>>
                            <label for="role_provider">
                                <div class="role-icon"><i class="fas fa-briefcase"></i></div>
                                مقدّم خدمة
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus" style="margin-left:8px"></i>
                    إنشاء الحساب الآن
                </button>

            </form>

            <div class="form-footer">
                لديك حساب بالفعل؟ <a href="/local_services/login.php">تسجيل الدخول</a>
            </div>

        </div>
    </div>

</div>

<script>
// ===== Toggle Password =====
function setupToggle(toggleId, inputId) {
    const toggle = document.getElementById(toggleId);
    const input  = document.getElementById(inputId);
    if (!toggle || !input) return;
    toggle.addEventListener('click', function() {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.className = show ? 'fas fa-eye-slash toggle-pw' : 'fas fa-eye toggle-pw';
    });
}
setupToggle('togglePw1', 'password');
setupToggle('togglePw2', 'password2');

// ===== Password Strength =====
const pwInput    = document.getElementById('password');
const segs       = [document.getElementById('s1'), document.getElementById('s2'),
                    document.getElementById('s3'), document.getElementById('s4')];
const label      = document.getElementById('strengthLabel');
const colors     = ['#ef233c', '#f4a261', '#ffd166', '#06d6a0'];
const labels     = ['ضعيفة جداً', 'ضعيفة', 'متوسطة', 'قوية ✓'];

function calcStrength(pw) {
    let score = 0;
    if (pw.length >= 8)              score++;
    if (/[A-Z]/.test(pw))            score++;
    if (/[0-9]/.test(pw))            score++;
    if (/[@$!%*?&]/.test(pw))        score++;
    return score;
}

pwInput.addEventListener('input', function() {
    const val = this.value;
    if (!val) {
        segs.forEach(s => s.style.background = '');
        label.textContent = '';
        return;
    }
    const score = calcStrength(val);
    segs.forEach((s, i) => {
        s.style.background = i < score ? colors[score - 1] : '';
    });
    label.textContent  = labels[score - 1] || '';
    label.style.color  = colors[score - 1] || '';
});

// ===== Phone: numbers only =====
document.getElementById('phone').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
});
</script>

</body>
</html>