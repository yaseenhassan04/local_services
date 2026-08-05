<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php'; // الحفاظ على منطق الحماية والجلسات الخاص بك

$user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);

// فحص الكاش لملف الستايل
$css_path = __DIR__ . '/../assets/style.css';
$css_v = file_exists($css_path) ? filemtime($css_path) : time();

// الحرف الأول لأفاتار المستخدم
$user_initials = '';
if ($user) {
    $name_parts = explode(' ', $user['full_name'] ?? $user['name'] ?? 'م');
    $user_initials = mb_substr($name_parts[0], 0, 1, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'خدماتي - منصة الخدمات المحلية'); ?></title>

    <!-- الخطوط العريضة والمكتبات المتوافقة تماماً مع موقعك -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="/local_services/assets/style.css?v=<?php echo $css_v; ?>">

    <style>
        /* نظام الألوان الدقيق المطابق للقطات الشاشة والموقع الفعلي */
        :root {
            --nav-height: 80px;
            --brand-gradient: linear-gradient(135deg, #00d4ff 0%, #ff006e 100%);
            --bg-dark-core: #091324;
            /* الأزرق الداكن العميق الظاهر في لقطة الشاشة */
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --font-main: 'Tajawal', sans-serif;
        }

        body {
            background-color: var(--bg-dark-core) !important;
            color: var(--text-main) !important;
            margin: 0;
            padding: 0;
            font-family: var(--font-main);
        }

        /* ---------- تصميم نافبار خدماتي الشفاف بالكامل ---------- */
        .kh-main-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--nav-height);
            background: rgba(9, 19, 36, 0.85);
            /* شفافية مدمجة مع الخلفية الأصلية */
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 4%;
            z-index: 9999;
            transition: all 0.3s ease;
        }

        /* الشعار والأيقونة المربعة المتدرجة تماماً كالصورة */
        .kh-main-nav .logo-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .kh-main-nav .logo-text {
            font-size: 24px;
            font-weight: 800;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kh-main-nav .logo-box {
            width: 36px;
            height: 36px;
            background: var(--brand-gradient);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 212, 255, 0.2);
        }

        .kh-main-nav .logo-box i {
            color: #091324;
            font-size: 20px;
            font-weight: 900;
        }

        /* ---------- الروابط الوسطى بتأثير التحديد الإشعاعي ---------- */
        .kh-nav-menu {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 1.8rem;
            margin: 0;
            padding: 0;
        }

        .kh-nav-menu li a {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
            transition: all 0.25s ease;
            position: relative;
            padding: 6px 0;
        }

        .kh-nav-menu li a:hover {
            color: #00d4ff;
        }

        /* الرابط النشط يظهر بلون أزرق نيون مشع مطابق للموقع الفعلي */
        .kh-nav-menu li a.active {
            color: #00d4ff;
            font-weight: 700;
        }

        .kh-nav-menu li a.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #00d4ff;
            box-shadow: 0 0 8px #00d4ff;
            border-radius: 2px;
        }

        /* ---------- أزرار الحساب الجانبية ---------- */
        .kh-nav-right-side {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .kh-btn-login {
            text-decoration: none;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.2s ease;
        }

        .kh-btn-login:hover {
            border-color: #00d4ff;
            color: #00d4ff;
            background: rgba(0, 212, 255, 0.05);
        }

        /* زر انضم الآن المتدرج المنسجم مع الهوية البصرية */
        .kh-btn-join {
            text-decoration: none;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            padding: 9px 24px;
            border-radius: 20px;
            background: linear-gradient(135deg, #0072ff 0%, #00d4ff 100%);
            box-shadow: 0 4px 15px rgba(0, 114, 255, 0.3);
            transition: all 0.2s ease;
        }

        .kh-btn-join:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 212, 255, 0.4);
        }

        /* زر الأفاتار والتحكم */
        .kh-avatar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
        }

        .kh-circle-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #091324;
            border: 2px solid #00d4ff;
            color: #00d4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }

        /* زر القائمة المدمج للموبايل */
        .kh-mobile-trigger {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 28px;
            cursor: pointer;
        }

        /* كود متجاوب بالكامل */
        @media (max-width: 992px) {

            .kh-nav-menu,
            .kh-nav-right-side {
                display: none;
            }

            .kh-mobile-trigger {
                display: block;
            }
        }

        /* تنسيق قائمة الجوال المنسدلة */
        .kh-mobile-dropdown {
            display: none;
            position: fixed;
            top: var(--nav-height);
            left: 0;
            right: 0;
            background: #091324;
            border-bottom: 1px solid var(--border-color);
            padding: 20px;
            flex-direction: column;
            gap: 15px;
            z-index: 9998;
        }

        .kh-mobile-dropdown.open {
            display: flex;
        }

        .kh-mobile-dropdown a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 5px 0;
        }
    </style>
</head>

<body>

    <?php
    function is_page_active($link_name, $current)
    {
        return $current === $link_name ? 'class="active"' : '';
    }
    ?>

    <!-- شريط التنقل الرئيسي الثابت -->
    <nav class="kh-main-nav">
        <!-- الشعار والأيقونة المتدرجة المطابقة لموقعك -->
        <a href="/local_services/index.php" class="logo-wrapper">
            <div class="logo-text">خدماتي</div>
            <div class="logo-box">
                <i class="las la-code"></i> <!-- أو أيقونة المربع الرمزية الظاهرة لديك -->
            </div>
        </a>

        <!-- روابط الموقع المطابقة لخياراتك بالترتيب -->
        <ul class="kh-nav-menu">
            <li><a href="/local_services/index.php" <?php echo is_page_active('index.php', $current_page); ?>>الرئيسية</a></li>
            <li><a href="/local_services/services.php" <?php echo is_page_active('services.php', $current_page); ?>>الخدمات</a></li>
            <li><a href="/local_services/about.php" <?php echo is_page_active('about.php', $current_page); ?>>من نحن</a></li>
            <li><a href="/local_services/faq.php" <?php echo is_page_active('faq.php', $current_page); ?>>الأسئلة الشائعة</a></li>
            <li><a href="/local_services/contact.php" <?php echo is_page_active('contact.php', $current_page); ?>>تواصل معنا</a></li>
        </ul>

        <!-- أزرار الدخول والتسجيل الجانبية -->
        <div class="kh-nav-right-side">
            <?php if ($user): ?>
                <a href="/local_services/dashboard.php" class="kh-avatar-btn">
                    <div class="kh-circle-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
                    لوحة التحكم
                </a>
            <?php else: ?>
                <a href="/local_services/login.php" class="kh-btn-login">دخول</a>
                <a href="/local_services/register.php" class="kh-btn-join">انضم الآن</a>
            <?php endif; ?>
        </div>

        <!-- زر الموبايل عند صغر الشاشة -->
        <button class="kh-mobile-trigger" onclick="toggleMobileNav()">
            <i class="las la-bars"></i>
        </button>
    </nav>

    <!-- القائمة المنسدلة للهواتف الذكية -->
    <div class="kh-mobile-dropdown" id="mobile-menu">
        <a href="/local_services/index.php">الرئيسية</a>
        <a href="/local_services/services.php">الخدمات</a>
        <a href="/local_services/about.php">من نحن</a>
        <a href="/local_services/faq.php">الأسئلة الشائعة</a>
        <a href="/local_services/contact.php">تواصل معنا</a>
        <hr style="border-color: rgba(255,255,255,0.05); margin: 5px 0;">
        <?php if ($user): ?>
            <a href="/local_services/dashboard.php">لوحة التحكم</a>
        <?php else: ?>
            <a href="/local_services/login.php">دخول</a>
            <a href="/local_services/register.php" style="color: #00d4ff;">انضم الآن</a>
        <?php endif; ?>
    </div>

    <!-- مسافة دفع علوية لمنع تداخل المحتوى مع الهيدر الثابت الموحد -->
    <div style="margin-top: var(--nav-height);"></div>

    <main>