<?php
// 1. تضمين الملفات الأساسية
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php'; 

global $pdo;
if (!$pdo) { die('خطأ في الاتصال بقاعدة البيانات.'); }

$user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);

$latest_services = [];
$categories = [];
$icons_mapping = [
    'كهرباء'              => 'electricity.svg',
    'سباكة'               => 'plumbing.svg',
    'تنظيف وتعقيم'        => 'cleaning.svg',
    'نجارة'               => 'carpentry.svg',
    'تكييف وتبريد'         => 'ac.svg',
    'تصميم جرافيك'         => 'graphic_design.svg',
    'برمجة وتطوير مواقع'   => 'programming.svg',
    'مونتاج فيديو'         => 'video_editing.svg',
    'كتابة وترجمة'         => 'writing.svg',
    'تسويق إلكتروني'       => 'marketing.svg',
];

try {
    $sql = "SELECT s.id, s.title, s.description, s.price, s.city, s.image, u.full_name
            FROM services s JOIN users u ON s.provider_id = u.id
            WHERE s.is_active = 1 ORDER BY s.created_at DESC LIMIT 6";
    $stmt = $pdo->query($sql);
    $latest_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
}

try {
    $categories_stmt = $pdo->query("SELECT id, name, image FROM categories LIMIT 8");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>خدماتي | منصة الخدمات المحلية والمستقلة</title>

    <!-- Google Fonts - Tajawal for Arabic -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Line Awesome Icons -->
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <style>
        /* ========== CSS Variables ========== */
        :root {
            --primary: #1B55E2;
            --primary-dark: #0d3a9e;
            --accent: #E7515A;
            --success: #1abc9c;
            --warning: #e2a03f;
            --dark-bg: #0e1726;
            --card-bg: #ffffff;
            --text-main: #1e2a3a;
            --text-muted: #6c7a8d;
            --border: #e8ecf0;
            --shadow-soft: 0 4px 24px rgba(27, 85, 226, .08);
            --shadow-card: 0 8px 32px rgba(27, 85, 226, .12);
            --radius: 14px;
            --radius-sm: 8px;
        }

        /* ========== Reset & Base ========== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: #f4f7fb;
            color: var(--text-main);
            direction: rtl;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ========== NAVBAR ========== */
        .kh-navbar {
            background: var(--dark-bg);
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 16px rgba(0, 0, 0, .3);
        }

        .kh-navbar .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .kh-navbar .brand span {
            color: var(--accent);
        }

        .kh-navbar .brand .brand-dot {
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            display: inline-block;
            margin-right: 2px;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.4);
                opacity: .7;
            }
        }

        .kh-nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
            list-style: none;
        }

        .kh-nav-links a {
            color: rgba(255, 255, 255, .75);
            font-size: 14px;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            transition: all .2s;
        }

        .kh-nav-links a:hover,
        .kh-nav-links a.active {
            background: rgba(255, 255, 255, .1);
            color: #fff;
        }

        .kh-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-nav-login {
            border: 1.5px solid rgba(255, 255, 255, .3);
            color: #fff;
            background: transparent;
            padding: 7px 18px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            transition: all .2s;
            cursor: pointer;
        }

        .btn-nav-login:hover {
            background: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .6);
        }

        .btn-nav-register {
            background: var(--primary);
            color: #fff;
            padding: 7px 18px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-nav-register:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(27, 85, 226, .4);
        }

        /* ========== HERO ========== */
        .kh-hero {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #162033 50%, #0d2545 100%);
            min-height: 580px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 60px 0;
        }

        /* Geometric shapes background */
        .kh-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(27, 85, 226, .2) 0%, transparent 70%);
            top: -200px;
            left: -150px;
            pointer-events: none;
        }

        .kh-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(231, 81, 90, .15) 0%, transparent 70%);
            bottom: -100px;
            right: 10%;
            pointer-events: none;
        }

        .hero-grid-dots {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
        }

        .kh-hero .container {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(27, 85, 226, .2);
            border: 1px solid rgba(27, 85, 226, .4);
            color: #7eb3ff;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 50px;
            margin-bottom: 24px;
        }

        .hero-badge i {
            color: var(--accent);
        }

        .kh-hero h1 {
            font-size: clamp(32px, 5vw, 54px);
            font-weight: 900;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 20px;
        }

        .kh-hero h1 .highlight {
            color: var(--primary);
            position: relative;
        }

        .kh-hero h1 .highlight::after {
            content: '';
            position: absolute;
            bottom: -4px;
            right: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .kh-hero p.lead {
            color: rgba(255, 255, 255, .65);
            font-size: 17px;
            line-height: 1.8;
            margin-bottom: 36px;
            max-width: 520px;
        }

        /* Search Box */
        .hero-search-box {
            background: rgba(255, 255, 255, .08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: var(--radius);
            padding: 8px;
            display: flex;
            gap: 8px;
            max-width: 620px;
            margin-bottom: 28px;
        }

        .hero-search-box input,
        .hero-search-box select {
            background: transparent;
            border: none;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
            font-size: 15px;
            padding: 10px 14px;
            outline: none;
            flex: 1;
        }

        .hero-search-box input::placeholder {
            color: rgba(255, 255, 255, .45);
        }

        .hero-search-box select {
            color: rgba(255, 255, 255, .7);
            border-right: 1px solid rgba(255, 255, 255, .15);
            flex: 0 0 160px;
            cursor: pointer;
        }

        .hero-search-box select option {
            background: #1e2a3a;
        }

        .btn-search {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: 10px 24px;
            font-family: 'Tajawal', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all .2s;
        }

        .btn-search:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }

        /* Hero CTA buttons */
        .hero-cta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-cta-primary {
            background: var(--accent);
            color: #fff;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            transition: all .25s;
            border: 2px solid var(--accent);
        }

        .btn-cta-primary:hover {
            background: transparent;
            color: var(--accent);
            transform: translateY(-2px);
        }

        .btn-cta-ghost {
            background: transparent;
            color: rgba(255, 255, 255, .8);
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 15px;
            border: 2px solid rgba(255, 255, 255, .2);
            transition: all .25s;
        }

        .btn-cta-ghost:hover {
            border-color: rgba(255, 255, 255, .5);
            color: #fff;
            background: rgba(255, 255, 255, .05);
        }

        /* Hero Stats */
        .hero-stats {
            display: flex;
            gap: 32px;
            margin-top: 40px;
        }

        .hero-stat-item {
            text-align: center;
        }

        .hero-stat-item .num {
            display: block;
            font-size: 26px;
            font-weight: 900;
            color: #fff;
        }

        .hero-stat-item .lbl {
            font-size: 12px;
            color: rgba(255, 255, 255, .5);
            font-weight: 500;
        }

        .hero-stat-item .num span {
            color: var(--accent);
        }

        /* Hero Image Side */
        .hero-visual {
            position: relative;
        }

        .hero-card-float {
            background: rgba(255, 255, 255, .06);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: var(--radius);
            padding: 20px 24px;
            color: #fff;
            margin-bottom: 16px;
        }

        .hero-card-float .card-title-sm {
            font-size: 12px;
            color: rgba(255, 255, 255, .5);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .hero-card-float .card-val {
            font-size: 28px;
            font-weight: 800;
        }

        .hero-card-float .card-val span {
            color: var(--success);
            font-size: 14px;
            font-weight: 600;
        }

        .hero-card-float .mini-bar {
            height: 4px;
            background: rgba(255, 255, 255, .1);
            border-radius: 2px;
            margin-top: 12px;
            overflow: hidden;
        }

        .hero-card-float .mini-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--success));
            border-radius: 2px;
            width: 72%;
            animation: bar-grow 1.5s ease-out forwards;
        }

        @keyframes bar-grow {
            from {
                width: 0;
            }

            to {
                width: 72%;
            }
        }

        .provider-avatars {
            display: flex;
            align-items: center;
        }

        .provider-avatars .av {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, .2);
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            margin-left: -8px;
        }

        .provider-avatars .av:first-child {
            margin-left: 0;
        }

        .av-more {
            background: rgba(255, 255, 255, .1) !important;
            font-size: 11px !important;
        }

        /* ========== SECTION TITLE ========== */
        .section-title {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-title .tag {
            display: inline-block;
            background: rgba(27, 85, 226, .1);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }

        .section-title h2 {
            font-size: clamp(24px, 3.5vw, 38px);
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 12px;
        }

        .section-title p {
            color: var(--text-muted);
            font-size: 16px;
            max-width: 520px;
            margin: 0 auto;
        }

        /* ========== CATEGORIES ========== */
        .kh-categories {
            padding: 80px 0;
            background: #fff;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 16px;
        }

        .category-card {
            background: #f8faff;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all .25s cubic-bezier(.4, 0, .2, 1);
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .category-card:hover {
            border-color: var(--primary);
            background: rgba(27, 85, 226, .04);
            transform: translateY(-5px);
            box-shadow: var(--shadow-card);
            color: var(--primary);
        }

        .category-icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #e8f0fe, #c8d8fa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            transition: all .25s;
        }

        .category-card:hover .category-icon-wrap {
            background: linear-gradient(135deg, var(--primary), #4a82f0);
        }

        .category-card:hover .category-icon-wrap img {
            filter: brightness(10);
        }

        .category-icon-wrap img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .category-card h4 {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
        }

        .category-card .cat-count {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ========== FEATURES / WHY US ========== */
        .kh-features {
            padding: 80px 0;
            background: linear-gradient(180deg, #f4f7fb 0%, #eef2f9 100%);
        }

        .feature-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 32px 28px;
            height: 100%;
            border: 1.5px solid var(--border);
            transition: all .3s;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(27, 85, 226, .06), transparent);
            border-radius: 0 var(--radius) 0 80px;
        }

        .feature-card:hover {
            border-color: var(--primary);
            transform: translateY(-6px);
            box-shadow: var(--shadow-card);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), #4a82f0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(27, 85, 226, .3);
        }

        .feature-card h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        /* ========== SERVICES GRID ========== */
        .kh-services {
            padding: 80px 0;
            background: #fff;
        }

        .service-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            overflow: hidden;
            transition: all .3s cubic-bezier(.4, 0, .2, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 48px rgba(27, 85, 226, .15);
            border-color: var(--primary);
            color: inherit;
        }

        .service-img {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden;
        }

        .service-img::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(14, 23, 38, .6), transparent 50%);
        }

        .service-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(14, 23, 38, .75);
            backdrop-filter: blur(8px);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            z-index: 2;
        }

        .service-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .service-provider {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .provider-av {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #4a82f0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
        }

        .provider-name {
            font-size: 13px;
            color: var(--text-muted);
        }

        .service-body h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .service-location {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .service-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .service-price {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
        }

        .service-price small {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .btn-view-service {
            background: var(--primary);
            color: #fff;
            padding: 7px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-view-service:hover {
            background: var(--primary-dark);
            color: #fff;
        }

        .btn-view-all {
            background: var(--dark-bg);
            color: #fff;
            padding: 13px 36px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all .25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-view-all:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(27, 85, 226, .3);
            color: #fff;
        }

        /* ========== CTA BANNER ========== */
        .kh-cta {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            position: relative;
            overflow: hidden;
        }

        .kh-cta::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, .08) 0%, transparent 60%);
            top: -200px;
            right: -100px;
            pointer-events: none;
        }

        .kh-cta .container {
            position: relative;
            z-index: 2;
        }

        .kh-cta h2 {
            font-size: clamp(24px, 4vw, 42px);
            font-weight: 900;
            color: #fff;
            margin-bottom: 14px;
        }

        .kh-cta p {
            color: rgba(255, 255, 255, .75);
            font-size: 16px;
            max-width: 500px;
        }

        .btn-cta-white {
            background: #fff;
            color: var(--primary);
            padding: 13px 32px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: all .25s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cta-white:hover {
            background: var(--dark-bg);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .3);
        }

        .btn-cta-outline-white {
            background: transparent;
            color: #fff;
            padding: 13px 32px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, .4);
            cursor: pointer;
            transition: all .25s;
        }

        .btn-cta-outline-white:hover {
            border-color: #fff;
            background: rgba(255, 255, 255, .1);
        }

        /* ========== FOOTER ========== */
        .kh-footer {
            background: var(--dark-bg);
            padding: 56px 0 28px;
            color: rgba(255, 255, 255, .65);
        }

        .footer-brand h3 {
            color: #fff;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .footer-brand h3 span {
            color: var(--accent);
        }

        .footer-brand p {
            font-size: 14px;
            line-height: 1.7;
            max-width: 280px;
        }

        .footer-links h5 {
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, .55);
            font-size: 14px;
            transition: color .2s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-divider {
            border-color: rgba(255, 255, 255, .1);
            margin: 32px 0 20px;
        }

        .footer-bottom {
            font-size: 13px;
        }

        .social-links {
            display: flex;
            gap: 10px;
        }

        .social-links a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .6);
            font-size: 16px;
            transition: all .2s;
        }

        .social-links a:hover {
            background: var(--primary);
            color: #fff;
        }

        /* ========== Empty State ========== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--border);
            margin-bottom: 16px;
            display: block;
        }

        .empty-state h3 {
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* ========== Responsive ========== */
        @media (max-width: 768px) {
            .kh-nav-links {
                display: none;
            }

            .hero-search-box {
                flex-wrap: wrap;
            }

            .hero-search-box select {
                flex: 1 0 100%;
                border-right: none;
                border-top: 1px solid rgba(255, 255, 255, .15);
            }

            .hero-stats {
                gap: 20px;
            }

            .hero-visual {
                display: none;
            }

            .category-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 480px) {
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .hero-cta {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    

    <!-- ===== HERO ===== -->
    <section class="kh-hero">
        <div class="hero-grid-dots"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7" data-aos="fade-up" data-aos-duration="700">

                    <div class="hero-badge">
                        <i class="las la-fire"></i>
                        أكثر من ٥٠٠ خبير موثّق على المنصة
                    </div>

                    <h1>
                        ابحث عن
                        <span class="highlight">أفضل الخبراء</span>
                        <br>في منطقتك بثقة
                    </h1>

                    <p class="lead">
                        منصة خدماتي تربطك بأفضل مقدمي الخدمات المحلية والمستقلين الموثّقين،
                        من الكهرباء والسباكة إلى التصميم والبرمجة — كل ما تحتاجه في مكان واحد.
                    </p>

                    <form class="hero-search-box" action="/local_services/services.php" method="get">
                        <input type="text" name="q" placeholder="مثال: سباكة، تصميم شعار، كهرباء...">
                        <select name="city">
                            <option value="">كل المدن</option>
                            <option>غزة</option>
                            <option>خانيونس</option>
                            <option>دير البلح</option>
                            <option>رفح</option>
                            <option>شمال غزة</option>
                        </select>
                        <button type="submit" class="btn-search">
                            <i class="las la-search"></i> ابحث
                        </button>
                    </form>

                    <div class="hero-cta">
                        <a href="/local_services/register.php?role=provider" class="btn-cta-primary">
                            <i class="las la-briefcase"></i> انضم كمزود خدمة
                        </a>
                        <a href="/local_services/services.php" class="btn-cta-ghost">
                            تصفح الخدمات <i class="las la-arrow-left"></i>
                        </a>
                    </div>

                    <div class="hero-stats">
                        <div class="hero-stat-item">
                            <span class="num">+<span>٥٠٠</span></span>
                            <span class="lbl">مزود خدمة</span>
                        </div>
                        <div class="hero-stat-item">
                            <span class="num">+<span>١٢٠٠</span></span>
                            <span class="lbl">طلب منجز</span>
                        </div>
                        <div class="hero-stat-item">
                            <span class="num"><span>٤.٩</span>★</span>
                            <span class="lbl">متوسط التقييم</span>
                        </div>
                    </div>
                </div>

                <!-- Floating visual cards -->
                <div class="col-lg-5 d-none d-lg-block" data-aos="fade-right" data-aos-delay="200">
                    <div class="hero-visual">
                        <div class="hero-card-float">
                            <div class="card-title-sm">طلبات اليوم</div>
                            <div class="card-val">٢٤ <span>↑ ١٨٪</span></div>
                            <div class="mini-bar">
                                <div class="mini-bar-fill"></div>
                            </div>
                        </div>

                        <div class="hero-card-float">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="card-title-sm mb-0">أحدث المزودين</div>
                                <span style="font-size:11px;color:#1abc9c;font-weight:700;">● متاح</span>
                            </div>
                            <div class="provider-avatars mb-2">
                                <div class="av">أح</div>
                                <div class="av" style="background:#e2a03f">مح</div>
                                <div class="av" style="background:#1abc9c">سا</div>
                                <div class="av" style="background:#E7515A">عم</div>
                                <div class="av av-more">+١٢</div>
                            </div>
                            <div style="font-size:13px;color:rgba(255,255,255,.55);">
                                مزودون جدد انضموا هذا الأسبوع
                            </div>
                        </div>

                        <div class="hero-card-float" style="background:rgba(27,85,226,.15);border-color:rgba(27,85,226,.3);">
                            <div class="d-flex align-items-center gap-3">
                                <div style="font-size:36px;">⭐</div>
                                <div>
                                    <div style="font-size:22px;font-weight:800;color:#fff;">٤.٩ / ٥</div>
                                    <div style="font-size:12px;color:rgba(255,255,255,.5);">من +٨٠٠ تقييم موثّق</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== CATEGORIES ===== -->
    <?php if (!empty($categories)): ?>
        <section class="kh-categories">
            <div class="container">
                <div class="section-title" data-aos="fade-up">
                    <span class="tag">التصنيفات</span>
                    <h2>تصفح حسب المجال</h2>
                    <p>آلاف الخدمات الموثوقة في كل التخصصات — ابحث بسهولة واطلب بثقة.</p>
                </div>

                <div class="category-grid" data-aos="fade-up" data-aos-delay="100">
                    <?php foreach ($categories as $cat):
                        $icon_path = '/local_services/assets/icons/' . ($icons_mapping[$cat['name']] ?? 'default-icon.svg');
                    ?>
                        <a href="/local_services/services.php?cat=<?php echo $cat['id']; ?>" class="category-card">
                            <div class="category-icon-wrap">
                                <img src="<?php echo $icon_path; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                            </div>
                            <h4><?php echo htmlspecialchars($cat['name']); ?></h4>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ===== WHY US ===== -->
    <section class="kh-features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <span class="tag">لماذا خدماتي؟</span>
                <h2>منصة صُمِّمت لثقتك</h2>
                <p>نضع الجودة والأمان في مقدمة أولوياتنا لكل طرفي التعامل.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="las la-shield-alt"></i>
                        </div>
                        <h3>مزودون موثّقون</h3>
                        <p>جميع مقدمي الخدمات يمرون بعملية تحقق دقيقة من الهوية والخبرة قبل الانضمام.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--success), #16a085);">
                            <i class="las la-bolt"></i>
                        </div>
                        <h3>استجابة فورية</h3>
                        <p>احصل على ردود سريعة من أقرب المتخصصين إليك في غضون دقائق لا ساعات.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--accent), #c0392b);">
                            <i class="las la-lock"></i>
                        </div>
                        <h3>دفع آمن ومحمي</h3>
                        <p>نظام دفع مشفر بالكامل يحمي بياناتك المالية ويضمن حقوقك في كل معاملة.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--warning), #d68910);">
                            <i class="las la-star"></i>
                        </div>
                        <h3>تقييمات حقيقية</h3>
                        <p>نظام تقييم شفاف يمكّنك من اختيار الأفضل بناءً على تجارب حقيقية لعملاء سابقين.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #9b59b6, #6c3483);">
                            <i class="las la-headset"></i>
                        </div>
                        <h3>دعم ٢٤/٧</h3>
                        <p>فريق دعم متخصص جاهز لمساعدتك في حل أي مشكلة على مدار الساعة طوال الأسبوع.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon" style="background: linear-gradient(135deg, #27ae60, #1e8449);">
                            <i class="las la-map-marker-alt"></i>
                        </div>
                        <h3>قُرب جغرافي</h3>
                        <p>ابحث عن المتخصصين في مدينتك تحديداً للحصول على خدمة أسرع وبتكلفة أقل.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== LATEST SERVICES ===== -->
    <section class="kh-services">
        <div class="container">
            <div class="d-flex align-items-end justify-content-between mb-5" data-aos="fade-up">
                <div>
                    <span class="section-title tag" style="text-align:right;margin-bottom:10px;display:inline-block;">أحدث الخدمات</span>
                    <h2 style="font-size:clamp(22px,3vw,34px);font-weight:800;margin:0;">خدمات أضيفت مؤخراً</h2>
                </div>
                <a href="/local_services/services.php" class="btn-view-all">
                    عرض الكل <i class="las la-arrow-left"></i>
                </a>
            </div>

            <?php if (!empty($latest_services)): ?>
                <div class="row g-4">
                    <?php $loop_i = 0; ?>
                    <?php foreach ($latest_services as $row):
                        $img = $row['image']
                            ? '/local_services/assets/uploads/' . htmlspecialchars($row['image'])
                            : '/local_services/assets/images/default-service.jpg';
                        $initials = mb_substr($row['full_name'], 0, 2);
                    ?>
                        <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?php echo ($loop_i++ ?? 0) * 80; ?>">
                            <a href="/local_services/service_detail.php?id=<?php echo $row['id']; ?>" class="service-card">
                                <div class="service-img" style="background-image:url('<?php echo $img; ?>')">
                                    <span class="service-badge"><?php echo htmlspecialchars($row['city']); ?></span>
                                </div>
                                <div class="service-body">
                                    <div class="service-provider">
                                        <div class="provider-av"><?php echo $initials; ?></div>
                                        <span class="provider-name"><?php echo htmlspecialchars($row['full_name']); ?></span>
                                    </div>
                                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                                    <div class="service-location">
                                        <i class="las la-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($row['city']); ?>
                                    </div>
                                    <div class="service-footer">
                                        <div class="service-price">
                                            <?php echo number_format($row['price'], 2); ?>
                                            <small> ر.س</small>
                                        </div>
                                        <span class="btn-view-service">عرض الخدمة</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-5" data-aos="fade-up">
                    <a href="/local_services/services.php" class="btn-view-all">
                        <i class="las la-th-large"></i> تصفح جميع الخدمات
                    </a>
                </div>

            <?php else: ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="las la-store-slash"></i>
                    <h3>لا توجد خدمات حالياً</h3>
                    <p style="color:var(--text-muted);margin-bottom:20px;">كن أول من يضيف خدمته إلى المنصة!</p>
                    <a href="/local_services/register.php?role=provider" class="btn-view-all">أضف خدمتك الآن</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===== CTA BANNER ===== -->
    <section class="kh-cta">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7" data-aos="fade-up">
                    <h2>هل أنت خبير محترف؟<br>ابدأ بكسب دخل إضافي اليوم</h2>
                    <p>انضم إلى مئات المستقلين على خدماتي، أنشئ ملفك الشخصي واستقبل طلبات العملاء مباشرة.</p>
                </div>
                <div class="col-lg-5 text-lg-start text-center mt-4 mt-lg-0" data-aos="fade-up" data-aos-delay="150">
                    <div class="d-flex gap-3 flex-wrap justify-content-center justify-content-lg-start">
                        <a href="/local_services/register.php?role=provider" class="btn-cta-white">
                            <i class="las la-briefcase"></i> سجّل كمزود خدمة
                        </a>
                        <a href="/local_services/about.php" class="btn-cta-outline-white">اعرف أكثر</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="kh-footer">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <h3>خدماتي<span>.</span></h3>
                        <p>منصة الخدمات المحلية والمستقلة الرائدة — نربط أصحاب الخبرات بمن يحتاجونها.</p>
                        <div class="social-links mt-4">
                            <a href="#"><i class="lab la-facebook-f"></i></a>
                            <a href="#"><i class="lab la-twitter"></i></a>
                            <a href="#"><i class="lab la-instagram"></i></a>
                            <a href="#"><i class="lab la-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <div class="footer-links">
                        <h5>الخدمات</h5>
                        <ul>
                            <li><a href="/local_services/services.php">تصفح الخدمات</a></li>
                            <li><a href="/local_services/register.php?role=provider">أضف خدمتك</a></li>
                            <li><a href="/local_services/services.php?cat=1">كهرباء</a></li>
                            <li><a href="/local_services/services.php?cat=2">سباكة</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <div class="footer-links">
                        <h5>المنصة</h5>
                        <ul>
                            <li><a href="/local_services/about.php">من نحن</a></li>
                            <li><a href="/local_services/faq.php">الأسئلة الشائعة</a></li>
                            <li><a href="/local_services/contact.php">تواصل معنا</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-4 col-lg-4">
                    <div class="footer-links">
                        <h5>تواصل معنا</h5>
                        <ul>
                            <li><i class="las la-envelope" style="color:var(--primary)"></i> info@khadamati.com</li>
                            <li><i class="las la-phone" style="color:var(--primary)"></i> +972 59X XXX XXX</li>
                            <li><i class="las la-map-marker" style="color:var(--primary)"></i> غزة - مجمع العمال</li>
                        </ul>
                    </div>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="footer-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>© <?php echo date('Y'); ?> خدماتي — جميع الحقوق محفوظة.</span>
                <span>صُنع بـ ❤ لدعم المجتمع المحلي</span>
            </div>
        </div>
    </footer>

    <!-- ===== SCRIPTS ===== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            offset: 60,
            easing: 'ease-out-cubic',
        });
    </script>

</body>

</html>