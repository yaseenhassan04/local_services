<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>من نحن - خدماتي</title>

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
            min-height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 30px;
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

        .hero::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 0, 110, 0.08) 0%, transparent 70%);
            bottom: -300px;
            left: -150px;
            animation: pulse 8s ease-in-out infinite;
            animation-delay: 2s;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
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
            font-size: clamp(36px, 6vw, 62px);
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
            margin-bottom: 40px;
        }

        /* ==================== SECTIONS ==================== */
        section {
            padding: 100px 30px;
            position: relative;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-tag {
            display: inline-block;
            background: rgba(0, 212, 255, 0.1);
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            border: 1px solid var(--dark-border);
        }

        .section-title h2 {
            font-size: clamp(32px, 5vw, 48px);
            margin-bottom: 20px;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            font-size: 16px;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* ==================== STORY SECTION ==================== */
        .story-section {
            max-width: 900px;
            margin: 0 auto;
        }

        .story-card {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.05) 0%, rgba(255, 0, 110, 0.05) 100%);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 50px;
            margin-bottom: 40px;
            backdrop-filter: blur(10px);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: slideInLeft 0.8s ease;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .story-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-accent);
        }

        .story-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateX(10px);
        }

        .story-card h3 {
            font-size: 28px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .story-card h3 i {
            font-size: 32px;
            color: var(--primary);
        }

        .story-card p {
            color: var(--text-secondary);
            line-height: 1.9;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .story-card strong {
            color: var(--primary);
        }

        /* ==================== VALUES SECTION ==================== */
        .values-section {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.05) 0%, rgba(255, 0, 110, 0.05) 100%);
            border-radius: 24px;
            backdrop-filter: blur(10px);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .value-card {
            background: rgba(26, 31, 58, 0.8);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: scaleIn 0.8s ease;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .value-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gradient-accent);
            opacity: 0;
            transition: var(--transition);
        }

        .value-card:hover {
            border-color: var(--primary);
            transform: translateY(-10px);
            box-shadow: var(--shadow);
        }

        .value-card:hover::before {
            opacity: 1;
        }

        .value-icon {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: var(--gradient-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(0, 212, 255, 0.2);
        }

        .value-card h4 {
            font-size: 20px;
            margin-bottom: 15px;
        }

        .value-card p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.8;
        }

        /* ==================== STATS SECTION ==================== */
        .stats-section {
            max-width: 900px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .stat-card {
            background: rgba(26, 31, 58, 0.8);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-accent);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }

        .stat-number {
            font-size: 48px;
            font-weight: 900;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* ==================== TEAM SECTION ==================== */
        .team-section {
            max-width: 1000px;
            margin: 0 auto;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .team-member {
            background: rgba(26, 31, 58, 0.8);
            border: 1px solid var(--dark-border);
            border-radius: 16px;
            overflow: hidden;
            transition: var(--transition);
        }

        .team-member:hover {
            border-color: var(--primary);
            transform: translateY(-10px);
            box-shadow: var(--shadow);
        }

        .team-avatar {
            width: 100%;
            height: 200px;
            background: var(--gradient-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            position: relative;
            overflow: hidden;
        }

        .team-member:nth-child(2) .team-avatar {
            background: var(--gradient-secondary);
        }

        .team-member:nth-child(3) .team-avatar {
            background: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
        }

        .team-info {
            padding: 30px;
        }

        .team-info h4 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .team-info p {
            color: var(--text-secondary);
            font-size: 13px;
        }

        /* ==================== CTA SECTION ==================== */
        .cta-section {
            background: var(--gradient-accent);
            border-radius: 24px;
            padding: 70px;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            top: -200px;
            right: -100px;
        }

        .cta-section h2 {
            font-size: 40px;
            color: var(--dark-bg);
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .cta-section p {
            color: rgba(10, 14, 39, 0.8);
            font-size: 16px;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }

        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .btn-cta {
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: var(--dark-bg);
            color: var(--primary);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(10, 14, 39, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--dark-bg);
            border: 2px solid var(--dark-bg);
        }

        .btn-secondary:hover {
            background: var(--dark-bg);
            color: var(--primary);
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
        @media (max-width: 768px) {
            section {
                padding: 60px 20px;
            }

            .story-card {
                padding: 30px;
            }

            .cta-section {
                padding: 50px 30px;
            }

            .cta-buttons {
                flex-direction: column;
            }

            .btn-cta {
                width: 100%;
                justify-content: center;
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
            <li><a href="about.php" class="active">من نحن</a></li>
            <li><a href="contact.php">تواصل معنا</a></li>
        </ul>
        <ul class="nav-actions">
            <li><a href="login.php" class="btn-nav btn-login"><i class="las la-sign-in-alt"></i> دخول</a></li>
            <li><a href="register.php" class="btn-nav btn-register"><i class="las la-user-plus"></i> إنشاء حساب</a></li>
        </ul>
    </nav>

    <!-- ==================== HERO ==================== -->
    <section class="hero">
        <div class="hero-content">
            <h1>قصتنا ورؤيتنا 🚀</h1>
            <p>من نحن؟ وما الهدف من تأسيس منصة خدماتي؟</p>
        </div>
    </section>

    <!-- ==================== STORY ==================== -->
    <section class="story-section">
        <div class="story-card" data-aos="fade-up">
            <h3>
                <i class="las la-fire"></i>
                بدايتنا: جسر الثقة في عالم الخدمات
            </h3>
            <p>
                تأسست منصة <strong>خدماتي</strong> انطلاقاً من إيماننا العميق بضرورة إيجاد نقطة التقاء موثوقة وعالية الجودة
                تجمع بين مزودي الخدمات المحليين والعملاء الباحثين عن الاحترافية.
            </p>
            <p>
                لاحظنا وجود فجوة في السوق المحلي، حيث كان من الصعب على الأفراد والشركات العثور على خبراء معتمدين بسرعة وبدون عناء.
            </p>
            <p>
                من هنا، وُلدت منصتنا لتكون الحل الشامل الذي يضمن <strong>شفافية التعامل، جودة الأداء، والأمان</strong> لكلا الطرفين.
            </p>
        </div>

        <div class="story-card" data-aos="fade-up" data-aos-delay="200">
            <h3>
                <i class="las la-eye"></i>
                رؤيتنا للمستقبل 👁️
            </h3>
            <p>
                أن نكون <strong>المنصة الرقمية الرائدة والأكثر تأثيراً</strong> في ربط المجتمع المحلي بالخدمات الاحترافية،
                مما يساهم في نمو الاقتصاد المحلي ودعم المستقلين والعاملين بالقطاع الخاص.
            </p>
            <p>
                نسعى لأن تكون خدماتي المنصة المفضلة لملايين الأشخاص، حيث <strong>الثقة والجودة والأمان</strong> هي الأساس في كل خطوة.
            </p>
        </div>
    </section>

    <!-- ==================== VALUES ==================== -->
    <section class="values-section">
        <div class="section-title">
            <span class="section-tag">قيمنا الأساسية</span>
            <h2>المبادئ التي تحكم عملنا 💎</h2>
            <p>القيم الراسخة التي نبني عليها منصة خدماتي</p>
        </div>

        <div class="values-grid">
            <div class="value-card" data-aos="zoom-in" data-aos-delay="0">
                <div class="value-icon">🤝</div>
                <h4>الثقة والشفافية</h4>
                <p>نلتزم بأعلى معايير الشفافية في عرض الخدمات وتقييم المزودين لضمان بناء الثقة الحقيقية.</p>
            </div>

            <div class="value-card" data-aos="zoom-in" data-aos-delay="100">
                <div class="value-icon">🏅</div>
                <h4>جودة لا تُضاهى</h4>
                <p>نركز على الكفاءة والاحترافية، حيث يتم التحقق من خبرات مقدمي الخدمات قبل الانضمام.</p>
            </div>

            <div class="value-card" data-aos="zoom-in" data-aos-delay="200">
                <div class="value-icon">⚙️</div>
                <h4>الابتكار والكفاءة</h4>
                <p>نعمل باستمرار على تطوير أدوات المنصة لتكون عملية طلب الخدمة سهلة وسريعة للغاية.</p>
            </div>
        </div>
    </section>

    <!-- ==================== STATS ==================== -->
    <section class="stats-section">
        <div class="section-title">
            <span class="section-tag">إحصائياتنا</span>
            <h2>نموّنا في الأرقام 📊</h2>
            <p>ننمو يوماً بعد يوم بفضل ثقة مجتمعنا</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" data-aos="flip-left">
                <div class="stat-number">+12,000</div>
                <div class="stat-label">خدمة مكتملة بنجاح</div>
            </div>

            <div class="stat-card" data-aos="flip-left" data-aos-delay="100">
                <div class="stat-number">+850</div>
                <div class="stat-label">مزود خدمة موثّق</div>
            </div>

            <div class="stat-card" data-aos="flip-left" data-aos-delay="200">
                <div class="stat-number">+5,000</div>
                <div class="stat-label">مستخدم نشط</div>
            </div>
        </div>
    </section>

    <!-- ==================== TEAM ==================== -->
    <section class="team-section">
        <div class="section-title">
            <span class="section-tag">الفريق</span>
            <h2>فريق العمل المتخصص 👥</h2>
            <p>نخبة من المحترفين المكرسين لخدمتك</p>
        </div>

        <div class="team-grid">
            <div class="team-member" data-aos="fade-up">
                <div class="team-avatar">👨‍💼</div>
                <div class="team-info">
                    <h4>محمد علي</h4>
                    <p>المؤسس والرئيس التنفيذي</p>
                </div>
            </div>

            <div class="team-member" data-aos="fade-up" data-aos-delay="100">
                <div class="team-avatar">👩‍💻</div>
                <div class="team-info">
                    <h4>ليلى أحمد</h4>
                    <p>مديرة التطوير والعمليات</p>
                </div>
            </div>

            <div class="team-member" data-aos="fade-up" data-aos-delay="200">
                <div class="team-avatar">👨‍🔧</div>
                <div class="team-info">
                    <h4>أحمد سالم</h4>
                    <p>رئيس فريق الدعم الفني</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== CTA ==================== -->
    <section style="padding: 50px 30px;">
        <div class="cta-section" data-aos="zoom-in">
            <h2>هل أنت خبير محترف؟ 💼</h2>
            <p>ابدأ بكسب دخل إضافي اليوم — انضم إلى مئات المستقلين على خدماتي</p>
            <div class="cta-buttons">
                <a href="register.php?role=provider" class="btn-cta btn-primary">
                    <i class="las la-briefcase"></i> سجّل كمزود خدمة
                </a>
                <a href="services.php" class="btn-cta btn-secondary">
                    <i class="las la-th-large"></i> تصفح الخدمات
                </a>
            </div>
        </div>
    </section>

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