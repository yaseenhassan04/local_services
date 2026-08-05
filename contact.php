<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل معنا - خدماتي</title>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #00d4ff;
            --primary-dark: #0099cc;
            --secondary: #ff006e;
            --accent: #00f5ff;
            --success: #00ff88;
            --warning: #ffa500;
            --dark-bg: #0a0e27;
            --dark-card: #1a1f3a;
            --dark-border: #2d3561;
            --text-primary: #ffffff;
            --text-secondary: #b0b8d4;
            --text-muted: #7a8199;
            --gradient-primary: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            --gradient-secondary: linear-gradient(135deg, #ff006e 0%, #ff4d94 100%);
            --gradient-accent: linear-gradient(135deg, #00d4ff 0%, #ff006e 100%);
            --shadow: 0 8px 32px rgba(0, 212, 255, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #0f1f35 100%);
            color: var(--text-primary);
            direction: rtl;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(circle at 20% 50%, rgba(0, 212, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(255, 0, 110, 0.1) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(50px, -50px);
            }
        }

        body>* {
            position: relative;
            z-index: 1;
        }

        /* ==================== NAVBAR ==================== */
        .navbar {
            background: rgba(10, 14, 39, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--dark-border);
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0, 212, 255, 0.1);
            animation: slideDown 0.6s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-100%);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-size: 24px;
            font-weight: 900;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-brand i {
            font-size: 28px;
            color: var(--primary);
        }

        .nav-links {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            position: relative;
            padding: 8px 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            right: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-accent);
            transition: var(--transition);
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary);
        }

        .nav-links a:hover::after,
        .nav-links a.active::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            gap: 15px;
            list-style: none;
        }

        .btn-nav {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-login {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--dark-border);
        }

        .btn-login:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-register {
            background: var(--gradient-accent);
            color: var(--dark-bg);
            border: none;
        }

        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0, 212, 255, 0.3);
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .navbar {
                padding: 0 20px;
            }
        }

        /* ==================== HERO ==================== */
        .hero {
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 30px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            width: 800px;
            height: 800px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 212, 255, 0.1) 0%, transparent 70%);
            top: -400px;
            right: -200px;
            animation: pulse 8s ease-in-out infinite;
        }

        .hero-content {
            max-width: 800px;
            text-align: center;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: clamp(36px, 6vw, 54px);
            font-weight: 900;
            margin-bottom: 20px;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        .hero p {
            font-size: 18px;
            color: var(--text-secondary);
            line-height: 1.8;
        }

        /* ==================== CONTACT GRID ==================== */
        .contact-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.8fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        /* Info Cards Panel */
        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: rgba(26, 31, 58, 0.8);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-accent);
        }

        .info-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateX(-5px);
        }

        .info-card h3 {
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card h3 i {
            font-size: 24px;
        }

        .info-card p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
        }

        /* Form Panel */
        .contact-form-wrapper {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.03) 0%, rgba(255, 0, 110, 0.03) 100%);
            border: 1px solid var(--dark-border);
            border-radius: 20px;
            padding: 45px;
            backdrop-filter: blur(10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .contact-form-wrapper h2 {
            font-size: 28px;
            margin-bottom: 30px;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-control {
            margin-bottom: 20px;
        }

        .form-control input,
        .form-control textarea {
            width: 100%;
            padding: 14px 20px;
            background: rgba(10, 14, 39, 0.7);
            border: 1px solid var(--dark-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-family: 'Tajawal', sans-serif;
            font-size: 15px;
            transition: var(--transition);
        }

        .form-control input:focus,
        .form-control textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
            background: rgba(26, 31, 58, 0.9);
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            background: var(--gradient-accent);
            color: var(--dark-bg);
            border: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0, 212, 255, 0.3);
        }

        /* Status Alert Styles */
        .status-alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(255, 0, 110, 0.1);
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        /* ==================== MAP SECTION ==================== */
        .map-section {
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .map-section h2 {
            font-size: 26px;
            text-align: center;
            margin-bottom: 30px;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .map-embed {
            height: 400px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--dark-border);
            box-shadow: var(--shadow);
        }

        .map-embed iframe {
            width: 100%;
            height: 100%;
            border: 0;
            filter: grayscale(100%) invert(92%) contrast(83%);
            /* Dark theme effect for map */
        }

        /* ==================== FOOTER ==================== */
        footer {
            background: linear-gradient(135deg, rgba(10, 14, 39, 0.95) 0%, rgba(26, 31, 58, 0.95) 100%);
            border-top: 1px solid var(--dark-border);
            padding: 60px 30px 30px;
            margin-top: 100px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h4 {
            font-size: 16px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .footer-section p,
        .footer-section a {
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.9;
            text-decoration: none;
            transition: var(--transition);
            display: block;
            margin-bottom: 8px;
        }

        .footer-section a:hover {
            color: var(--primary);
        }

        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--dark-border);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            margin: 0;
            color: var(--text-secondary);
        }

        .social-links a:hover {
            background: var(--gradient-accent);
            color: var(--dark-bg);
        }

        .footer-bottom {
            border-top: 1px solid var(--dark-border);
            padding-top: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 992px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .info-card:hover {
                transform: translateY(-5px);
            }
        }

        @media (max-width: 768px) {
            .form-group-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .contact-form-wrapper {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>

    <!-- ==================== NAVBAR ==================== -->
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <i class="las la-code"></i>
            خدماتي
        </a>
        <ul class="nav-links">
            <li><a href="index.php">الرئيسية</a></li>
            <li><a href="services.php">الخدمات</a></li>
            <li><a href="about.php">من نحن</a></li>
            <li><a href="contact.php" class="active">تواصل معنا</a></li>
        </ul>
        <ul class="nav-actions">
            <li><a href="login.php" class="btn-nav btn-login"><i class="las la-sign-in-alt"></i> دخول</a></li>
            <li><a href="register.php" class="btn-nav btn-register"><i class="las la-user-plus"></i> إنشاء حساب</a></li>
        </ul>
    </nav>

    <!-- ==================== HERO ==================== -->
    <section class="hero">
        <div class="hero-content">
            <h1>تواصل معنا 🌐</h1>
            <p>نحن هنا للإجابة على جميع استفساراتك وتقديم الدعم. لا تتردد في مراسلتنا.</p>
        </div>
    </section>

    <!-- ==================== CONTACT CONTENT ==================== -->
    <div class="contact-container">

        <?php
        if (isset($_GET['status'])) {
            $status = $_GET['status'];
            $default_msg = ($status == 'success')
                ? 'تم استلاف رسالتك بنجاح. سنرد عليك في أقرب وقت ممكن!'
                : 'عذراً، حدث خطأ أثناء إرسال رسالتك. يرجى التأكد من البيانات والمحاولة مجدداً.';

            $message = $_GET['msg'] ?? $default_msg;
            $alert_class = ($status == 'success') ? 'alert-success' : 'alert-error';
            $icon = ($status == 'success') ? '<i class="las la-check-circle" style="font-size:20px;"></i>' : '<i class="las la-exclamation-triangle" style="font-size:20px;"></i>';

            echo '<div class="status-alert ' . $alert_class . '">';
            echo $icon . htmlspecialchars(urldecode($message));
            echo '</div>';
        }
        ?>

        <section class="contact-grid">

            <!-- Info Cards Left Column -->
            <div class="contact-info" data-aos="fade-left">

                <div class="info-card">
                    <h3><i class="las la-envelope"></i> البريد الإلكتروني</h3>
                    <p>info@khadamati.com</p>
                </div>

                <div class="info-card">
                    <h3><i class="las la-phone"></i> الهاتف</h3>
                    <p>+972 599 999 999</p>
                </div>

                <div class="info-card">
                    <h3><i class="las la-map-marker"></i> العنوان</h3>
                    <p>غزة - مجمع العمال، الطابق الثالث</p>
                </div>

                <div class="info-card">
                    <h3><i class="las la-clock"></i> أوقات العمل</h3>
                    <p>السبت - الخميس: 9:00 صباحاً - 5:00 مساءً</p>
                </div>

            </div>

            <!-- Form Right Column -->
            <div class="contact-form-wrapper" data-aos="fade-right" data-aos-delay="200">
                <h2>أرسل لنا رسالة ✨</h2>

                <form action="/local_services/process_contact.php" method="POST">
                    <div class="form-group-row">
                        <div class="form-control">
                            <input type="text" name="name" placeholder="الاسم الكامل *" required>
                        </div>
                        <div class="form-control">
                            <input type="email" name="email" placeholder="البريد الإلكتروني *" required>
                        </div>
                    </div>

                    <div class="form-control">
                        <input type="text" name="subject" placeholder="الموضوع">
                    </div>

                    <div class="form-control">
                        <textarea name="message" placeholder="رسالتك... *" required rows="6"></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="las la-paper-plane"></i> إرسال الرسالة
                    </button>
                </form>
            </div>

        </section>

        <!-- Map Section -->
        <section class="map-section" data-aos="zoom-in">
            <h2>موقعنا على الخريطة 🗺️</h2>
            <div class="map-embed">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3404.707736637497!2d34.46328368484964!3d31.50029965313988!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14030635f7957973%3A0x673c6838a37f594!2sGaza%20City!5e0!3m2!1sen!2sps!4v1628173456789!5m2!1sen!2sps" loading="lazy"></iframe>
            </div>
        </section>

    </div>

    <!-- ==================== FOOTER ==================== -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>خدماتي</h4>
                <p>منصة الخدمات المحلية والمستقلة الرائدة — نربط أصحاب الخبرات بمن يحتاجونها.</p>
                <div class="social-links">
                    <a href="#" title="Facebook"><i class="lab la-facebook-f"></i></a>
                    <a href="#" title="Twitter"><i class="lab la-twitter"></i></a>
                    <a href="#" title="Instagram"><i class="lab la-instagram"></i></a>
                    <a href="#" title="WhatsApp"><i class="lab la-whatsapp"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h4>الخدمات</h4>
                <a href="services.php">تصفح الخدمات</a>
                <a href="register.php?role=provider">أضف خدمتك</a>
                <a href="services.php">كل التخصصات</a>
            </div>

            <div class="footer-section">
                <h4>المنصة</h4>
                <a href="about.php">من نحن</a>
                <a href="faq.php">الأسئلة الشائعة</a>
                <a href="contact.php">تواصل معنا</a>
            </div>

            <div class="footer-section">
                <h4>تواصل معنا</h4>
                <a href="mailto:info@khadamati.com">info@khadamati.com</a>
                <a href="tel:+972599999999">+972 599 999 999</a>
                <p>غزة - مجمع العمال</p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© 2024 خدماتي — جميع الحقوق محفوظة | صُنع بـ ❤️ لدعم المجتمع المحلي</p>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            offset: 100,
            easing: 'ease-out-cubic'
        });
    </script>

</body>

</html>