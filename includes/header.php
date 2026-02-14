<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth.php';
$user = getCurrentUser();

$css_path = __DIR__ . '/../assets/style.css'; 
$css_v = file_exists($css_path) ? filemtime($css_path) : time(); 
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>منصة الخدمات المحلية</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/local_services/assets/style.css?v=<?php echo $css_v; ?>">
    
    <script src="/local_services/assets/script.js"></script> 
</head>
<body>

<div class="top-progress-bar"></div>

<header class="site-header">
    <div class="container nav-wrap">
        <span class="logo logo-static">خدماتي</span>
        
        <nav class="main-nav">
            <a href="/local_services/index.php">الرئيسية</a> 
            <a href="/local_services/services.php">الخدمات</a>
            <a href="/local_services/add_service.php">أضف خدمة</a>
            <?php if ($user): ?>
                <a href="/local_services/dashboard.php">لوحة التحكم</a>
                <a href="/local_services/logout.php" class="btn-logout">خروج</a>
            <?php else: ?>
                <a href="/local_services/login.php" class="btn-login">دخول</a>
                <a href="/local_services/register.php" class="btn-register">تسجيل</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="site-main">