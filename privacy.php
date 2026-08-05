<?php
// privacy.php — سياسة الخصوصية
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user         = null;
$user_role    = null;
$user_initial = '؟';

if (function_exists('getCurrentUser')) {
    $user = getCurrentUser();
    if ($user) {
        $user_role    = $user['role'] ?? 'client';
        $user_initial = mb_substr($user['full_name'], 0, 1, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سياسة الخصوصية | خدماتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <style>
        /* ── إخفاء أي هيدر خارجي ── */
        body > nav:not(.app-header),
        body > header:not(.app-header),
        .navbar, .navbar-default, .top-header,
        .site-header, nav.navbar, #header,
        #top-bar, .main-header { display:none !important; }

        :root {
            --bg:       #07090f;
            --bg2:      #0d1117;
            --surface:  #111827;
            --surface2: #161f2e;
            --border:   rgba(255,255,255,.07);
            --border2:  rgba(255,255,255,.12);

            --blue:       #3b82f6;
            --blue-dim:   rgba(59,130,246,.12);
            --blue-glow:  rgba(59,130,246,.25);
            --cyan:       #06b6d4;
            --cyan-dim:   rgba(6,182,212,.12);
            --green:      #10b981;
            --green-dim:  rgba(16,185,129,.12);
            --amber:      #f59e0b;
            --amber-dim:  rgba(245,158,11,.12);
            --red:        #ef4444;
            --red-dim:    rgba(239,68,68,.12);
            --purple:     #8b5cf6;
            --purple-dim: rgba(139,92,246,.12);

            --text:  #f1f5f9;
            --text2: #94a3b8;
            --text3: #475569;

            --header-h: 64px;
            --r:  10px;
            --r2: 14px;
        }

        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        html { scroll-behavior:smooth; }

        body {
            font-family:'Tajawal', sans-serif;
            background:var(--bg);
            color:var(--text);
            font-size:14px;
            direction:rtl;
            min-height:100vh;
        }

        a { text-decoration:none; color:inherit; }

        ::-webkit-scrollbar { width:4px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:var(--border2); border-radius:99px; }

        /* noise */
        body::before {
            content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            opacity:.5;
        }

        /* ══ HEADER ══ */
        .app-header {
            position:fixed; top:0; right:0; left:0; z-index:200;
            height:var(--header-h);
            background:rgba(13,17,23,.88);
            backdrop-filter:blur(20px);
            border-bottom:1px solid var(--border);
            display:flex; align-items:center;
            padding:0 20px; gap:12px;
        }
        .logo-wrap { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .logo-mark {
            width:34px; height:34px; border-radius:9px;
            background:linear-gradient(135deg, var(--blue), var(--cyan));
            display:flex; align-items:center; justify-content:center;
            font-size:16px; color:#fff; font-weight:900;
            box-shadow:0 0 18px var(--blue-glow);
        }
        .logo-text { font-size:17px; font-weight:900; color:var(--text); letter-spacing:-.3px; }
        .logo-text span { color:var(--blue); }
        .hdr-spacer { flex:1; }
        .hdr-btn {
            display:flex; align-items:center; gap:6px;
            padding:7px 12px; border-radius:8px;
            font-size:12px; font-weight:600; color:var(--text2);
            border:1px solid transparent; transition:all .2s; white-space:nowrap;
        }
        .hdr-btn:hover { background:var(--surface); color:var(--text); border-color:var(--border2); }
        .hdr-btn i { font-size:15px; }
        .hdr-avatar {
            display:flex; align-items:center; gap:9px;
            padding:5px 10px 5px 14px; border-radius:99px;
            background:var(--surface); border:1px solid var(--border2);
            text-decoration:none; transition:border-color .2s;
        }
        .hdr-avatar:hover { border-color:var(--blue); }
        .avatar-ring {
            width:30px; height:30px; border-radius:50%;
            background:linear-gradient(135deg, var(--blue), var(--purple));
            display:flex; align-items:center; justify-content:center;
            font-size:13px; font-weight:800; color:#fff;
        }
        .avatar-name { font-size:13px; font-weight:700; color:var(--text); line-height:1.1; }
        .avatar-role { font-size:11px; color:var(--text2); }

        /* ══ HERO ══ */
        .page-hero {
            position:relative; overflow:hidden;
            padding: calc(var(--header-h) + 52px) 24px 52px;
            text-align:center;
            border-bottom:1px solid var(--border);
        }
        .page-hero::before {
            content:''; position:absolute; inset:0;
            background:radial-gradient(ellipse 70% 60% at 50% 0%, rgba(59,130,246,.1), transparent 70%);
            pointer-events:none;
        }
        /* grid lines decoration */
        .page-hero::after {
            content:''; position:absolute; inset:0; pointer-events:none;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size:40px 40px;
            mask-image:radial-gradient(ellipse 80% 100% at 50% 0%, black 40%, transparent 80%);
            opacity:.4;
        }
        .hero-icon {
            position:relative; z-index:1;
            width:60px; height:60px; border-radius:16px; margin:0 auto 18px;
            background:linear-gradient(135deg, var(--blue-dim), var(--cyan-dim));
            border:1px solid rgba(59,130,246,.25);
            display:flex; align-items:center; justify-content:center;
            font-size:26px; color:var(--blue);
            box-shadow:0 0 30px var(--blue-glow);
        }
        .hero-title {
            position:relative; z-index:1;
            font-size:30px; font-weight:900; color:var(--text);
            letter-spacing:-.5px; margin-bottom:10px;
        }
        .hero-sub {
            position:relative; z-index:1;
            font-size:14px; color:var(--text2); max-width:480px; margin:0 auto;
            line-height:1.7;
        }
        .hero-date {
            position:relative; z-index:1;
            display:inline-flex; align-items:center; gap:6px;
            margin-top:16px; padding:5px 14px; border-radius:99px;
            background:var(--surface2); border:1px solid var(--border2);
            font-size:12px; color:var(--text3);
        }
        .hero-date i { color:var(--amber); font-size:13px; }

        /* ══ CONTENT ══ */
        .page-wrap {
            max-width:820px; margin:0 auto;
            padding:40px 24px 80px;
            position:relative; z-index:1;
        }

        /* table of contents */
        .toc-card {
            background:var(--surface2);
            border:1px solid var(--border);
            border-radius:var(--r2);
            padding:18px 20px;
            margin-bottom:28px;
        }
        .toc-title {
            font-size:12px; font-weight:700; color:var(--text3);
            text-transform:uppercase; letter-spacing:.7px;
            display:flex; align-items:center; gap:6px; margin-bottom:12px;
        }
        .toc-title i { color:var(--blue); font-size:14px; }
        .toc-list { list-style:none; display:flex; flex-direction:column; gap:3px; }
        .toc-list li a {
            display:flex; align-items:center; gap:8px;
            padding:6px 10px; border-radius:7px;
            font-size:13px; font-weight:600; color:var(--text2);
            transition:all .18s;
        }
        .toc-list li a:hover { background:var(--blue-dim); color:var(--blue); }
        .toc-list li a i { font-size:14px; color:var(--text3); transition:color .18s; }
        .toc-list li a:hover i { color:var(--blue); }
        .toc-num {
            font-size:10px; font-weight:800; color:var(--text3);
            background:var(--surface); border:1px solid var(--border2);
            border-radius:5px; padding:1px 6px; min-width:22px;
            text-align:center; flex-shrink:0;
        }

        /* sections */
        .policy-section {
            margin-bottom:24px;
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r2);
            overflow:hidden;
            transition:border-color .2s;
        }
        .policy-section:hover { border-color:var(--border2); }

        .section-hdr {
            display:flex; align-items:center; gap:12px;
            padding:16px 20px; border-bottom:1px solid var(--border);
        }
        .section-num {
            width:30px; height:30px; border-radius:8px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            font-size:12px; font-weight:900; color:#fff;
        }
        .section-hdr h2 {
            font-size:15px; font-weight:800; color:var(--text); margin:0;
        }

        .section-body {
            padding:18px 20px;
            font-size:14px; color:var(--text2); line-height:1.9;
        }
        .section-body p { margin-bottom:12px; }
        .section-body p:last-child { margin-bottom:0; }

        /* list items */
        .policy-list { list-style:none; display:flex; flex-direction:column; gap:8px; margin-top:12px; }
        .policy-list li {
            display:flex; align-items:flex-start; gap:10px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:9px; padding:11px 14px;
            font-size:13px; color:var(--text2); line-height:1.65;
        }
        .policy-list li i {
            font-size:15px; flex-shrink:0; margin-top:2px;
        }
        .policy-list li strong { color:var(--text); display:block; margin-bottom:2px; }

        /* color variants per section */
        .c-blue   { background:var(--blue-dim); color:var(--blue); }
        .c-cyan   { background:var(--cyan-dim); color:var(--cyan); }
        .c-green  { background:var(--green-dim); color:var(--green); }
        .c-amber  { background:var(--amber-dim); color:var(--amber); }
        .c-purple { background:var(--purple-dim); color:var(--purple); }
        .c-red    { background:var(--red-dim); color:var(--red); }
        .c-blue2  { background:linear-gradient(135deg,#1e40af,#1e3a8a); }

        .icon-li-blue   { color:var(--blue); }
        .icon-li-cyan   { color:var(--cyan); }
        .icon-li-green  { color:var(--green); }
        .icon-li-amber  { color:var(--amber); }
        .icon-li-purple { color:var(--purple); }
        .icon-li-red    { color:var(--red); }

        /* contact link */
        .inline-link {
            color:var(--blue); font-weight:700;
            border-bottom:1px solid rgba(59,130,246,.3);
            transition:border-color .2s;
        }
        .inline-link:hover { border-color:var(--blue); }

        /* ══ FOOTER ══ */
        .page-footer {
            text-align:center; padding:28px 24px;
            border-top:1px solid var(--border);
            font-size:12px; color:var(--text3);
            position:relative; z-index:1;
        }
        .page-footer a { color:var(--blue); }

        /* ══ ANIMATE ══ */
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(14px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .animate { animation:fadeUp .4s ease both; }
        .d1 { animation-delay:.05s; }
        .d2 { animation-delay:.10s; }
        .d3 { animation-delay:.15s; }
        .d4 { animation-delay:.20s; }
        .d5 { animation-delay:.25s; }
        .d6 { animation-delay:.30s; }
        .d7 { animation-delay:.35s; }
        .d8 { animation-delay:.40s; }

        /* ══ RESPONSIVE ══ */
        @media (max-width:600px) {
            .hero-title { font-size:22px; }
            .page-wrap { padding:28px 14px 60px; }
            .section-hdr { padding:13px 15px; }
            .section-body { padding:14px 15px; }
            .avatar-name, .avatar-role { display:none; }
            .hdr-btn span { display:none; }
        }
    </style>
</head>
<body>

<!-- ══ HEADER ══ -->
<header class="app-header">
    <a href="/local_services/index.php" class="logo-wrap">
        <div class="logo-mark"><i class="las la-map-marker"></i></div>
        <span class="logo-text">خدمات<span>ي</span></span>
    </a>
    <div class="hdr-spacer"></div>

    <a href="/local_services/index.php" class="hdr-btn">
        <i class="las la-home"></i><span>الرئيسية</span>
    </a>
    <a href="/local_services/services.php" class="hdr-btn">
        <i class="las la-concierge-bell"></i><span>الخدمات</span>
    </a>
    <a href="/local_services/contact.php" class="hdr-btn">
        <i class="las la-envelope"></i><span>تواصل معنا</span>
    </a>

    <?php if ($user): ?>
        <a href="<?php echo $user_role === 'admin' || $user_role === 'provider' ? '/local_services/dashboard.php' : '/local_services/client_dashboard.php'; ?>"
           class="hdr-avatar">
            <div>
                <div class="avatar-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="avatar-role">
                    <?php echo ['admin'=>'مدير','provider'=>'مزود','client'=>'عميل'][$user_role] ?? $user_role; ?>
                </div>
            </div>
            <div class="avatar-ring"><?php echo $user_initial; ?></div>
        </a>
    <?php else: ?>
        <a href="/local_services/login.php" class="hdr-btn">
            <i class="las la-sign-in-alt"></i><span>دخول</span>
        </a>
    <?php endif; ?>
</header>

<!-- ══ HERO ══ -->
<div class="page-hero">
    <div class="hero-icon"><i class="las la-shield-alt"></i></div>
    <h1 class="hero-title">سياسة الخصوصية</h1>
    <p class="hero-sub">
        نلتزم بحماية بياناتك الشخصية والحفاظ على سريتها التامة.
        اقرأ هذه السياسة لتعرف كيف نتعامل مع معلوماتك.
    </p>
    <div class="hero-date">
        <i class="las la-calendar-check"></i>
        آخر تحديث: 25 نوفمبر 2025
    </div>
</div>

<!-- ══ CONTENT ══ -->
<div class="page-wrap">

    <!-- جدول المحتويات -->
    <div class="toc-card animate d1">
        <div class="toc-title"><i class="las la-list-ul"></i> محتويات السياسة</div>
        <ul class="toc-list">
            <li><a href="#s1"><span class="toc-num">01</span><i class="las la-handshake"></i> الالتزام بالخصوصية</a></li>
            <li><a href="#s2"><span class="toc-num">02</span><i class="las la-database"></i> المعلومات التي نجمعها</a></li>
            <li><a href="#s3"><span class="toc-num">03</span><i class="las la-cogs"></i> استخدام المعلومات</a></li>
            <li><a href="#s4"><span class="toc-num">04</span><i class="las la-share-alt"></i> مشاركة المعلومات مع الأطراف الثالثة</a></li>
            <li><a href="#s5"><span class="toc-num">05</span><i class="las la-cookie-bite"></i> ملفات تعريف الارتباط</a></li>
            <li><a href="#s6"><span class="toc-num">06</span><i class="las la-user-shield"></i> حقوق المستخدم</a></li>
            <li><a href="#s7"><span class="toc-num">07</span><i class="las la-envelope-open-text"></i> التواصل والاستفسارات</a></li>
        </ul>
    </div>

    <!-- 1. الالتزام بالخصوصية -->
    <div class="policy-section animate d2" id="s1">
        <div class="section-hdr">
            <div class="section-num c-blue"><i class="las la-handshake" style="font-size:15px;"></i></div>
            <h2>الالتزام بالخصوصية</h2>
        </div>
        <div class="section-body">
            <p>
                منصة <strong style="color:var(--text);">خدماتي</strong> (يُشار إليها باسم "المنصة"، "نحن"، أو "لنا") تولي أهمية قصوى لخصوصية مستخدميها. توضح هذه السياسة كيفية جمع واستخدام وحماية معلوماتك الشخصية عند استخدامك لخدماتنا.
            </p>
        </div>
    </div>

    <!-- 2. المعلومات التي نجمعها -->
    <div class="policy-section animate d3" id="s2">
        <div class="section-hdr">
            <div class="section-num c-cyan"><i class="las la-database" style="font-size:15px;"></i></div>
            <h2>المعلومات التي نجمعها</h2>
        </div>
        <div class="section-body">
            <p>نجمع نوعين رئيسيين من المعلومات:</p>
            <ul class="policy-list">
                <li>
                    <i class="las la-user icon-li-cyan"></i>
                    <div>
                        <strong>المعلومات التعريفية (عند التسجيل)</strong>
                        الاسم الكامل، البريد الإلكتروني، رقم الهاتف، الموقع الجغرافي (المدينة/المنطقة).
                    </div>
                </li>
                <li>
                    <i class="las la-briefcase icon-li-cyan"></i>
                    <div>
                        <strong>المعلومات الخاصة بمزودي الخدمة</strong>
                        معلومات الخبرة، وثائق التحقق، بيانات الدفع، ووصف الخدمات.
                    </div>
                </li>
                <li>
                    <i class="las la-chart-bar icon-li-cyan"></i>
                    <div>
                        <strong>بيانات الاستخدام</strong>
                        عناوين IP، نوع المتصفح، نظام التشغيل، الصفحات التي زرتها، وأوقات الزيارة.
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <!-- 3. استخدام المعلومات -->
    <div class="policy-section animate d4" id="s3">
        <div class="section-hdr">
            <div class="section-num c-green"><i class="las la-cogs" style="font-size:15px;"></i></div>
            <h2>استخدام المعلومات</h2>
        </div>
        <div class="section-body">
            <p>تُستخدم معلوماتك للأغراض التالية:</p>
            <ul class="policy-list">
                <li>
                    <i class="las la-link icon-li-green"></i>
                    <div>
                        <strong>تشغيل وتوفير الخدمات</strong>
                        ربط العملاء بمزودي الخدمات المحليين وضمان سير العمليات بسلاسة.
                    </div>
                </li>
                <li>
                    <i class="las la-shield-alt icon-li-green"></i>
                    <div>
                        <strong>الأمان والتحقق</strong>
                        التأكد من هوية مزودي الخدمات ومنع الاحتيال والأنشطة المشبوهة.
                    </div>
                </li>
                <li>
                    <i class="las la-bell icon-li-green"></i>
                    <div>
                        <strong>التواصل والتسويق</strong>
                        إرسال تحديثات وإشعارات تتعلق بالخدمة والعروض المتاحة.
                    </div>
                </li>
                <li>
                    <i class="las la-chart-line icon-li-green"></i>
                    <div>
                        <strong>تحسين المنصة</strong>
                        تحليل بيانات الاستخدام لتحسين تجربة المستخدم وأداء الموقع.
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <!-- 4. مشاركة المعلومات -->
    <div class="policy-section animate d5" id="s4">
        <div class="section-hdr">
            <div class="section-num c-amber"><i class="las la-share-alt" style="font-size:15px;"></i></div>
            <h2>مشاركة المعلومات مع الأطراف الثالثة</h2>
        </div>
        <div class="section-body">
            <p>
                نحن لا نبيع معلوماتك الشخصية أو نتاجر بها. يتم مشاركة معلوماتك فقط في الحالات التالية:
            </p>
            <ul class="policy-list">
                <li>
                    <i class="las la-handshake icon-li-amber"></i>
                    <div>
                        <strong>بين المستخدم ومزود الخدمة</strong>
                        عند حجز أو طلب خدمة، يتم مشاركة معلومات الاتصال اللازمة (الاسم ورقم الهاتف) فقط.
                    </div>
                </li>
                <li>
                    <i class="las la-server icon-li-amber"></i>
                    <div>
                        <strong>مقدمو الخدمات</strong>
                        مع الشركاء الذين يساعدوننا في تشغيل المنصة، كخدمات الدفع واستضافة المواقع.
                    </div>
                </li>
                <li>
                    <i class="las la-balance-scale icon-li-amber"></i>
                    <div>
                        <strong>المتطلبات القانونية</strong>
                        إذا كان القانون يتطلب ذلك أو لحماية حقوق المنصة ومستخدميها.
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <!-- 5. الكوكيز -->
    <div class="policy-section animate d6" id="s5">
        <div class="section-hdr">
            <div class="section-num c-purple"><i class="las la-cookie-bite" style="font-size:15px;"></i></div>
            <h2>ملفات تعريف الارتباط (Cookies)</h2>
        </div>
        <div class="section-body">
            <p>
                نستخدم ملفات تعريف الارتباط لتحسين تجربتك وتذكر تفضيلاتك مثل تسجيل الدخول وإعدادات اللغة. يمكنك تعطيل ملفات تعريف الارتباط من خلال إعدادات متصفحك، ولكن قد يؤثر ذلك على بعض وظائف المنصة.
            </p>
        </div>
    </div>

    <!-- 6. حقوق المستخدم -->
    <div class="policy-section animate d7" id="s6">
        <div class="section-hdr">
            <div class="section-num c-blue"><i class="las la-user-shield" style="font-size:15px;"></i></div>
            <h2>حقوق المستخدم</h2>
        </div>
        <div class="section-body">
            <p>لديك الحق في ممارسة الحقوق التالية في أي وقت:</p>
            <ul class="policy-list">
                <li>
                    <i class="las la-eye icon-li-blue"></i>
                    <div>طلب الوصول إلى بياناتك الشخصية أو تعديلها وتصحيحها.</div>
                </li>
                <li>
                    <i class="las la-trash-alt icon-li-blue"></i>
                    <div>طلب حذف بياناتك من أنظمتنا، مع مراعاة الالتزامات القانونية النافذة.</div>
                </li>
                <li>
                    <i class="las la-ban icon-li-blue"></i>
                    <div>الاعتراض على معالجة بياناتك لأغراض تسويقية أو إلغاء الاشتراك في الإشعارات.</div>
                </li>
            </ul>
        </div>
    </div>

    <!-- 7. التواصل -->
    <div class="policy-section animate d8" id="s7">
        <div class="section-hdr">
            <div class="section-num c-green"><i class="las la-envelope-open-text" style="font-size:15px;"></i></div>
            <h2>التواصل والاستفسارات</h2>
        </div>
        <div class="section-body">
            <p>
                إذا كانت لديك أي أسئلة أو مخاوف حول سياسة الخصوصية هذه أو طريقة تعاملنا مع بياناتك، يرجى التواصل معنا عبر صفحة
                <a href="/local_services/contact.php" class="inline-link">اتصل بنا</a>.
                سنرد على استفساراتك في أقرب وقت ممكن.
            </p>
        </div>
    </div>

</div>

<!-- ══ FOOTER ══ -->
<div class="page-footer">
    خدماتي &copy; <?php echo date('Y'); ?> — جميع الحقوق محفوظة &nbsp;·&nbsp;
    <a href="/local_services/privacy.php">سياسة الخصوصية</a> &nbsp;·&nbsp;
    <a href="/local_services/contact.php">تواصل معنا</a>
</div>

</body>
</html>