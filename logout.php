<?php
// logout.php - تسجيل الخروج مع شاشة توديع احترافية

session_start();

// جلب اسم المستخدم قبل مسح الجلسة
$user_name = '';
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/db.php';
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        $user_name = $row['full_name'] ?? '';
    } catch (Exception $e) {
        // تجاهل الخطأ
    }
}

// مسح الجلسة
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الخروج | خدماتي</title>
    <!-- إعادة التوجيه بعد 4 ثوانٍ -->
    <meta http-equiv="refresh" content="4;url=/local_services/index.php">

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

    <style>
        :root {
            --primary:      #1B55E2;
            --primary-dark: #0d3a9e;
            --accent:       #E7515A;
            --dark-bg:      #0e1726;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Tajawal', sans-serif;
            direction: rtl;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, var(--dark-bg) 0%, #162033 50%, #0d2545 100%);
            position: relative;
            overflow: hidden;
        }

        /* Grid dots background */
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        /* Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(27,85,226,.2) 0%, transparent 65%);
            top: -200px; right: -150px;
        }

        .orb-2 {
            width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(231,81,90,.12) 0%, transparent 65%);
            bottom: -100px; left: -100px;
        }

        /* Main card */
        .logout-card {
            position: relative;
            z-index: 10;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 56px 48px;
            text-align: center;
            max-width: 460px;
            width: 90%;
            animation: card-in .6s cubic-bezier(.4,0,.2,1) both;
        }

        @keyframes card-in {
            from { opacity: 0; transform: translateY(30px) scale(.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Wave icon */
        .wave-icon {
            font-size: 64px;
            display: block;
            margin-bottom: 8px;
            animation: wave 1.2s ease-in-out .3s both;
            transform-origin: 70% 70%;
        }

        @keyframes wave {
            0%   { transform: rotate(0deg);   opacity: 0; }
            20%  { transform: rotate(-20deg); opacity: 1; }
            40%  { transform: rotate(20deg); }
            60%  { transform: rotate(-10deg); }
            80%  { transform: rotate(10deg); }
            100% { transform: rotate(0deg); }
        }

        /* Check circle */
        .check-circle {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #4a82f0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            box-shadow: 0 12px 40px rgba(27,85,226,.4);
            animation: pop-in .5s cubic-bezier(.34,1.56,.64,1) .2s both;
        }

        @keyframes pop-in {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        .check-circle i {
            font-size: 36px;
            color: #fff;
        }

        /* Text */
        .goodbye-name {
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 50px;
            padding: 4px 16px;
            display: inline-block;
            margin-bottom: 16px;
            animation: fade-in .5s ease .4s both;
        }

        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 900;
            margin-bottom: 10px;
            animation: fade-in .5s ease .5s both;
        }

        .sub-text {
            color: rgba(255,255,255,.55);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 36px;
            animation: fade-in .5s ease .6s both;
        }

        /* Progress bar */
        .progress-wrap {
            animation: fade-in .5s ease .7s both;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .progress-label span {
            font-size: 12px;
            color: rgba(255,255,255,.5);
        }

        .progress-bar-track {
            height: 4px;
            background: rgba(255,255,255,.1);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #4a82f0);
            border-radius: 2px;
            animation: progress-fill 4s linear forwards;
        }

        @keyframes progress-fill {
            from { width: 0%; }
            to   { width: 100%; }
        }

        /* Buttons */
        .logout-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fade-in .5s ease .8s both;
        }

        .btn-home {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 11px 26px;
            border-radius: 50px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all .25s;
            box-shadow: 0 4px 20px rgba(27,85,226,.35);
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(27,85,226,.5);
            color: #fff;
        }

        .btn-login {
            background: transparent;
            color: rgba(255,255,255,.7);
            padding: 11px 26px;
            border-radius: 50px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1.5px solid rgba(255,255,255,.2);
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all .25s;
        }

        .btn-login:hover {
            border-color: rgba(255,255,255,.5);
            color: #fff;
            background: rgba(255,255,255,.05);
        }

        /* Bottom brand */
        .logout-brand {
            margin-top: 36px;
            color: rgba(255,255,255,.3);
            font-size: 13px;
            animation: fade-in .5s ease .9s both;
        }

        .logout-brand strong {
            color: rgba(255,255,255,.5);
        }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="logout-card">

        <span class="wave-icon">👋</span>

        <div class="check-circle">
            <i class="las la-sign-out-alt"></i>
        </div>

        <?php if (!empty($user_name)): ?>
        <div class="goodbye-name">
            <i class="las la-user"></i>
            <?php echo htmlspecialchars($user_name); ?>
        </div>
        <?php endif; ?>

        <h2>تم تسجيل الخروج بنجاح</h2>
        <p class="sub-text">
            شكراً لاستخدامك منصة خدماتي.<br>
            سيتم تحويلك للصفحة الرئيسية تلقائياً.
        </p>

        <div class="progress-wrap">
            <div class="progress-label">
                <span>جارٍ التحويل...</span>
                <span>٤ ثوانٍ</span>
            </div>
            <div class="progress-bar-track">
                <div class="progress-bar-fill"></div>
            </div>
        </div>

        <div class="logout-actions">
            <a href="/local_services/index.php" class="btn-home">
                <i class="las la-home"></i>
                الصفحة الرئيسية
            </a>
            <a href="/local_services/login.php" class="btn-login">
                <i class="las la-sign-in-alt"></i>
                تسجيل الدخول
            </a>
        </div>

        <div class="logout-brand">
            منصة <strong>خدماتي</strong> — خدمات موثوقة لمجتمع متميّز
        </div>

    </div>

</body>
</html>