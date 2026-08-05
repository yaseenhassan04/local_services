<?php
// /local_services/client_dashboard.php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 

requireLogin();

global $pdo;
$user = getCurrentUser();
$user_id = $user['id'];
$user_role = $user['role'];

// توجيه الأدوار الأخرى إلى لوحة التحكم الرئيسية
if ($user_role !== 'client') {
    header("Location: /local_services/dashboard.php");
    exit;
}

// ==========================================================
// 1. جلب الإحصائيات الموجزة للعميل
// ==========================================================
$stmt_stats = $pdo->prepare("SELECT
    COUNT(id) AS total_orders,
    SUM(CASE WHEN status IN ('pending', 'processing', 'in_progress') THEN 1 ELSE 0 END) AS active_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) AS total_spent
    FROM orders WHERE client_id = ?");
$stmt_stats->execute([$user_id]);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// ==========================================================
// 2. جلب الطلبات السابقة للعميل
// ==========================================================
$stmt = $pdo->prepare("SELECT o.id, o.order_date, o.status, o.payment_method, s.title, u.full_name AS provider_name, o.amount
    FROM orders o
    JOIN services s ON o.service_id = s.id
    JOIN users u ON o.provider_id = u.id
    WHERE o.client_id = ?
    ORDER BY o.order_date DESC
    LIMIT 20");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    <title>لوحة تحكم العميل | منصة الخدمات المحلية</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        /* ============================================
           XATO DARK THEME - ARABIC/RTL ADAPTATION
        ============================================ */
        :root {
            --dark-bg:       #060818;
            --sidebar-bg:    #0e1726;
            --card-bg:       #0e1726;
            --card-border:   #1b2e4b;
            --header-bg:     #0e1726;
            --text-primary:  #e0e6ed;
            --text-muted:    #888ea8;
            --text-dark:     #bfc9d4;
            --primary:       #4361ee;
            --primary-light: rgba(67,97,238,.15);
            --success:       #00ab55;
            --success-light: rgba(0,171,85,.15);
            --warning:       #e2a03f;
            --warning-light: rgba(226,160,63,.15);
            --danger:        #e7515a;
            --danger-light:  rgba(231,81,90,.15);
            --info:          #2196f3;
            --info-light:    rgba(33,150,243,.15);
            --purple:        #805dca;
            --purple-light:  rgba(128,93,202,.15);
            --sidebar-width: 255px;
            --header-height: 70px;
            --radius:        8px;
            --shadow:        0 6px 10px rgba(0,0,0,.4);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-size: 14px;
            direction: rtl;
        }

        /* ---- Scrollbar ---- */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        ::-webkit-scrollbar-thumb { background: #1b2e4b; border-radius: 10px; }

        /* ============ HEADER ============ */
        .app-header {
            position: fixed;
            top: 0; right: 0; left: 0;
            height: var(--header-height);
            background: var(--header-bg);
            border-bottom: 1px solid var(--card-border);
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 15px;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            min-width: 200px;
        }
        .header-logo .logo-icon {
            width: 36px; height: 36px;
            background: var(--primary);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #fff;
        }
        .header-logo span {
            font-size: 18px; font-weight: 800;
            color: var(--text-primary);
            letter-spacing: 0.5px;
        }
        .header-toggle {
            background: none; border: none;
            color: var(--text-muted);
            font-size: 22px; cursor: pointer;
            padding: 5px 8px; border-radius: 6px;
            transition: all .2s;
        }
        .header-toggle:hover { background: var(--primary-light); color: var(--primary); }

        .header-spacer { flex: 1; }

        .header-search {
            position: relative;
        }
        .header-search input {
            background: var(--dark-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            border-radius: 6px;
            padding: 7px 35px 7px 14px;
            width: 220px;
            font-family: 'Tajawal', sans-serif;
            font-size: 13px;
            transition: border-color .2s;
        }
        .header-search input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .header-search .search-icon {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .header-nav-item {
            position: relative;
        }
        .header-icon-btn {
            width: 38px; height: 38px;
            background: var(--dark-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 18px;
            transition: all .2s;
            text-decoration: none;
        }
        .header-icon-btn:hover { border-color: var(--primary); color: var(--primary); text-decoration: none; }
        .badge-dot {
            position: absolute;
            top: -3px; left: -3px;
            width: 10px; height: 10px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--header-bg);
        }
        .header-user {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; padding: 5px 10px;
            border-radius: 8px; transition: background .2s;
            text-decoration: none;
        }
        .header-user:hover { background: var(--primary-light); }
        .user-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: #fff;
        }
        .user-info { text-align: right; }
        .user-info .user-name {
            font-size: 13px; font-weight: 700;
            color: var(--text-primary); display: block; line-height: 1.2;
        }
        .user-info .user-role {
            font-size: 11px; color: var(--text-muted); display: block;
        }

        /* ============ SIDEBAR ============ */
        .app-sidebar {
            position: fixed;
            top: var(--header-height);
            right: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: var(--sidebar-bg);
            border-left: 1px solid var(--card-border);
            overflow-y: auto;
            z-index: 1020;
            transition: transform .3s ease;
        }
        .app-sidebar.collapsed {
            transform: translateX(var(--sidebar-width));
        }

        .sidebar-section-title {
            padding: 20px 20px 8px;
            font-size: 11px; font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-menu { list-style: none; padding: 5px 10px; }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius);
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px; font-weight: 500;
            transition: all .2s;
        }
        .sidebar-menu a i { font-size: 18px; min-width: 22px; }
        .sidebar-menu a:hover {
            background: var(--primary-light);
            color: var(--primary);
            text-decoration: none;
        }
        .sidebar-menu a.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 15px rgba(67,97,238,.4);
        }
        .sidebar-menu a.active i { color: #fff; }
        .sidebar-badge {
            margin-right: auto;
            font-size: 11px; font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
        }
        .sidebar-menu a.logout-link { color: var(--danger); }
        .sidebar-menu a.logout-link:hover { background: var(--danger-light); color: var(--danger); }

        /* ============ MAIN CONTENT ============ */
        .app-main {
            margin-right: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 25px;
            min-height: calc(100vh - var(--header-height));
            transition: margin-right .3s ease;
        }
        .app-main.expanded { margin-right: 0; }

        /* ============ PAGE HEADER ============ */
        .page-title-area {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 25px;
            flex-wrap: wrap; gap: 10px;
        }
        .page-title h2 {
            font-size: 22px; font-weight: 800;
            color: var(--text-primary); margin: 0;
        }
        .breadcrumb-custom {
            display: flex; align-items: center; gap: 6px;
            list-style: none; padding: 0; margin: 5px 0 0;
        }
        .breadcrumb-custom li { font-size: 12px; color: var(--text-muted); }
        .breadcrumb-custom li a { color: var(--primary); text-decoration: none; }
        .breadcrumb-custom li.active { color: var(--text-muted); }
        .breadcrumb-custom li:not(:last-child)::after {
            content: '/';
            margin-right: 6px;
            color: var(--card-border);
        }

        /* ============ STAT CARDS ============ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 20px;
            display: flex; align-items: center; gap: 16px;
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .stat-icon.primary  { background: var(--primary-light);  color: var(--primary);  }
        .stat-icon.success  { background: var(--success-light);  color: var(--success);  }
        .stat-icon.warning  { background: var(--warning-light);  color: var(--warning);  }
        .stat-icon.danger   { background: var(--danger-light);   color: var(--danger);   }
        .stat-icon.purple   { background: var(--purple-light);   color: var(--purple);   }

        .stat-info { flex: 1; }
        .stat-value {
            font-size: 26px; font-weight: 800;
            color: var(--text-primary); line-height: 1;
        }
        .stat-label {
            font-size: 13px; color: var(--text-muted);
            margin-top: 4px;
        }

        /* ============ CARD ============ */
        .xato-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .xato-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--card-border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .xato-card-header h5 {
            font-size: 15px; font-weight: 700;
            color: var(--text-primary); margin: 0;
        }
        .xato-card-body { padding: 0; }

        /* Search & filter bar */
        .table-toolbar {
            padding: 14px 20px;
            display: flex; align-items: center; gap: 12px;
            flex-wrap: wrap;
        }
        .table-search {
            position: relative; flex: 1; min-width: 200px;
        }
        .table-search input {
            width: 100%;
            background: var(--dark-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            border-radius: 6px;
            padding: 8px 36px 8px 14px;
            font-family: 'Tajawal', sans-serif;
            font-size: 13px;
        }
        .table-search input:focus { outline: none; border-color: var(--primary); }
        .table-search i {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .filter-select {
            background: var(--dark-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            border-radius: 6px;
            padding: 8px 12px;
            font-family: 'Tajawal', sans-serif;
            font-size: 13px;
        }
        .filter-select:focus { outline: none; border-color: var(--primary); }

        /* ============ TABLE ============ */
        .xato-table { width: 100%; border-collapse: collapse; }
        .xato-table thead th {
            background: rgba(27,46,75,.5);
            color: var(--text-muted);
            font-size: 12px; font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--card-border);
            white-space: nowrap;
        }
        .xato-table tbody tr {
            border-bottom: 1px solid rgba(27,46,75,.5);
            transition: background .15s;
        }
        .xato-table tbody tr:hover { background: rgba(27,46,75,.4); }
        .xato-table tbody td {
            padding: 13px 16px;
            color: var(--text-dark);
            font-size: 13px;
            vertical-align: middle;
        }
        .xato-table tbody tr:last-child { border-bottom: none; }

        .order-id {
            font-weight: 700;
            color: var(--primary);
        }
        .service-title {
            font-weight: 600;
            color: var(--text-primary);
            max-width: 180px;
        }
        .provider-name { color: var(--text-muted); font-size: 12px; }
        .amount-cell { font-weight: 700; color: var(--success); }

        /* ============ STATUS BADGES ============ */
        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 30px;
            font-size: 12px; font-weight: 600;
        }
        .status-pill::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; background: currentColor;
        }
        .status-pending   { background: var(--warning-light);  color: var(--warning);  }
        .status-completed { background: var(--success-light);  color: var(--success);  }
        .status-cancelled { background: var(--danger-light);   color: var(--danger);   }
        .status-in_progress { background: var(--info-light);   color: var(--info);     }
        .status-processing  { background: var(--purple-light); color: var(--purple);   }

        /* Payment method pill */
        .payment-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 8px; border-radius: 5px;
            font-size: 12px; background: rgba(27,46,75,.8);
            color: var(--text-muted);
        }

        /* ============ ACTION BUTTONS ============ */
        .btn-xato {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 6px;
            font-size: 12px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer;
            transition: all .2s; white-space: nowrap;
            font-family: 'Tajawal', sans-serif;
        }
        .btn-xato-primary { background: var(--primary-light); color: var(--primary); }
        .btn-xato-primary:hover { background: var(--primary); color: #fff; text-decoration: none; }
        .btn-xato-warning { background: var(--warning-light); color: var(--warning); }
        .btn-xato-warning:hover { background: var(--warning); color: #fff; text-decoration: none; }

        /* ============ EMPTY STATE ============ */
        .empty-state {
            text-align: center; padding: 60px 20px;
        }
        .empty-state-icon {
            width: 80px; height: 80px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; color: var(--primary);
            margin: 0 auto 20px;
        }
        .empty-state h4 { color: var(--text-primary); font-size: 17px; margin-bottom: 8px; }
        .empty-state p { color: var(--text-muted); font-size: 14px; }

        /* ============ ALERT ============ */
        .xato-alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
            font-size: 13px;
        }
        .xato-alert-success { background: var(--success-light); color: var(--success); border: 1px solid rgba(0,171,85,.3); }
        .xato-alert-danger  { background: var(--danger-light);  color: var(--danger);  border: 1px solid rgba(231,81,90,.3); }
        .xato-alert-warning { background: var(--warning-light); color: var(--warning); border: 1px solid rgba(226,160,63,.3); }

        /* ============ USER PROFILE SIDEBAR CARD ============ */
        .user-profile-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 20px;
            margin: 15px 10px;
            text-align: center;
        }
        .profile-avatar-lg {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 800; color: #fff;
            margin: 0 auto 12px;
        }
        .profile-name { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .profile-email { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .profile-divider {
            border: none;
            border-top: 1px solid var(--card-border);
            margin: 12px 0;
        }
        .profile-meta { text-align: right; }
        .profile-meta-item {
            display: flex; align-items: center; gap: 8px;
            padding: 5px 0;
            font-size: 13px; color: var(--text-muted);
        }
        .profile-meta-item i { color: var(--primary); font-size: 15px; min-width: 18px; }
        .btn-edit-profile {
            display: block; width: 100%;
            padding: 8px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px; font-weight: 600;
            margin-top: 12px;
            transition: all .2s;
        }
        .btn-edit-profile:hover { background: var(--primary); color: #fff; text-decoration: none; }

        /* ============ PROGRESS BAR ============ */
        .progress-custom {
            height: 6px; background: var(--dark-bg);
            border-radius: 10px; overflow: hidden;
            margin-top: 6px;
        }
        .progress-bar-custom { height: 100%; border-radius: 10px; transition: width .5s; }

        /* ============ QUICK ACTIONS ============ */
        .quick-actions {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 10px; padding: 15px;
        }
        .quick-action-btn {
            display: flex; flex-direction: column; align-items: center;
            gap: 8px; padding: 14px 10px;
            border-radius: var(--radius);
            border: 1px solid var(--card-border);
            text-decoration: none;
            transition: all .2s;
            background: var(--dark-bg);
        }
        .quick-action-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            text-decoration: none;
        }
        .quick-action-btn i { font-size: 22px; }
        .quick-action-btn span { font-size: 12px; font-weight: 600; }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 768px) {
            .app-sidebar { transform: translateX(var(--sidebar-width)); }
            .app-sidebar.mobile-open { transform: translateX(0); }
            .app-main { margin-right: 0; padding: 15px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .user-info { display: none; }
            .header-search { display: none; }
            .xato-table { font-size: 12px; }
            .xato-table thead th, .xato-table tbody td { padding: 10px 8px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ============ HEADER ============ -->
<header class="app-header">
    <a href="/local_services/index.php" class="header-logo">
        <div class="logo-icon"><i class="las la-map-marker"></i></div>
        <span>خدماتي</span>
    </a>

    <button class="header-toggle" id="sidebarToggle" title="فتح/إغلاق القائمة">
        <i class="las la-bars"></i>
    </button>

    <div class="header-spacer"></div>

    <div class="header-search d-none d-md-block">
        <input type="text" placeholder="ابحث عن خدمة..." id="globalSearch">
        <i class="las la-search search-icon"></i>
    </div>

    <div class="header-nav-item">
        <a href="/local_services/services.php" class="header-icon-btn" title="تصفح الخدمات">
            <i class="las la-th-large"></i>
        </a>
    </div>

    <div class="header-nav-item">
        <a href="#" class="header-icon-btn" title="الإشعارات">
            <i class="las la-bell"></i>
            <span class="badge-dot"></span>
        </a>
    </div>

    <a href="/local_services/profile.php" class="header-user">
        <div class="user-avatar">
            <?php echo mb_substr($user['full_name'], 0, 1); ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
            <span class="user-role">عميل</span>
        </div>
    </a>
</header>

<!-- ============ SIDEBAR ============ -->
<aside class="app-sidebar" id="appSidebar">

    <!-- User Profile Mini Card -->
    <div class="user-profile-card">
        <div class="profile-avatar-lg">
            <?php echo mb_substr($user['full_name'], 0, 1); ?>
        </div>
        <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
        <hr class="profile-divider">
        <div class="profile-meta">
            <?php if (!empty($user['phone'])): ?>
            <div class="profile-meta-item">
                <i class="las la-phone"></i>
                <?php echo htmlspecialchars($user['phone']); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($user['city'])): ?>
            <div class="profile-meta-item">
                <i class="las la-map-marker"></i>
                <?php echo htmlspecialchars($user['city']); ?>
            </div>
            <?php endif; ?>
        </div>
        <a href="/local_services/profile.php" class="btn-edit-profile">
            <i class="las la-user-edit"></i> تعديل الملف الشخصي
        </a>
    </div>

    <!-- Navigation -->
    <div class="sidebar-section-title">القائمة الرئيسية</div>
    <ul class="sidebar-menu">
        <li>
            <a href="/local_services/index.php">
                <i class="las la-home"></i>
                الرئيسية
            </a>
        </li>
        <li>
            <a href="/local_services/services.php">
                <i class="las la-concierge-bell"></i>
                تصفح الخدمات
                <span class="sidebar-badge" style="background:var(--primary-light);color:var(--primary);">جديد</span>
            </a>
        </li>
        <li>
            <a href="/local_services/client_dashboard.php" class="active">
                <i class="las la-clipboard-list"></i>
                طلباتي
                <?php if ($stats['active_orders'] > 0): ?>
                <span class="sidebar-badge" style="background:var(--warning-light);color:var(--warning);">
                    <?php echo $stats['active_orders']; ?>
                </span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="/local_services/profile.php">
                <i class="las la-user-circle"></i>
                الملف الشخصي
            </a>
        </li>
    </ul>

    <div class="sidebar-section-title">الإعدادات</div>
    <ul class="sidebar-menu">
        <li>
            <a href="/local_services/profile.php#password">
                <i class="las la-lock"></i>
                تغيير كلمة المرور
            </a>
        </li>
        <li>
            <a href="/local_services/logout.php" class="logout-link">
                <i class="las la-sign-out-alt"></i>
                خروج آمن
            </a>
        </li>
    </ul>

    <!-- Quick Actions -->
    <div class="sidebar-section-title">إجراءات سريعة</div>
    <div class="quick-actions">
        <a href="/local_services/services.php" class="quick-action-btn">
            <i class="las la-search" style="color:var(--primary)"></i>
            <span style="color:var(--text-muted)">بحث</span>
        </a>
        <a href="/local_services/profile.php" class="quick-action-btn">
            <i class="las la-user" style="color:var(--success)"></i>
            <span style="color:var(--text-muted)">الحساب</span>
        </a>
        <a href="/local_services/services.php?new=1" class="quick-action-btn">
            <i class="las la-plus-circle" style="color:var(--warning)"></i>
            <span style="color:var(--text-muted)">طلب جديد</span>
        </a>
        <a href="/local_services/index.php" class="quick-action-btn">
            <i class="las la-home" style="color:var(--purple)"></i>
            <span style="color:var(--text-muted)">الرئيسية</span>
        </a>
    </div>

</aside>

<!-- ============ MAIN CONTENT ============ -->
<main class="app-main" id="appMain">

    <!-- Page Header -->
    <div class="page-title-area">
        <div class="page-title">
            <h2>لوحة تحكم العميل</h2>
            <ul class="breadcrumb-custom">
                <li><a href="/local_services/index.php">الرئيسية</a></li>
                <li class="active">لوحة التحكم</li>
            </ul>
        </div>
        <a href="/local_services/services.php" class="btn-xato btn-xato-primary" style="padding: 9px 16px; font-size: 13px;">
            <i class="las la-plus"></i>
            طلب خدمة جديدة
        </a>
    </div>

    <!-- Flash Message -->
    <?php
    if (function_exists('display_message')) {
        display_message();
    }
    ?>

    <!-- ============ STATS GRID ============ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="las la-clipboard-list"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo (int)$stats['total_orders']; ?></div>
                <div class="stat-label">إجمالي الطلبات</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning"><i class="las la-spinner"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo (int)$stats['active_orders']; ?></div>
                <div class="stat-label">قيد التنفيذ</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success"><i class="las la-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo (int)$stats['completed_orders']; ?></div>
                <div class="stat-label">طلبات مكتملة</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger"><i class="las la-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo (int)$stats['cancelled_orders']; ?></div>
                <div class="stat-label">طلبات ملغاة</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple"><i class="las la-wallet"></i></div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:20px;"><?php echo number_format((float)$stats['total_spent'], 0); ?></div>
                <div class="stat-label">إجمالي المدفوعات (ر.س)</div>
            </div>
        </div>
    </div>

    <!-- Completion Progress -->
    <?php if ($stats['total_orders'] > 0):
        $completion_pct = round(($stats['completed_orders'] / $stats['total_orders']) * 100);
    ?>
    <div class="xato-card" style="margin-bottom: 25px; padding: 18px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-size:13px; font-weight:600; color:var(--text-primary);">
                <i class="las la-chart-line" style="color:var(--primary);"></i>
                نسبة إنجاز الطلبات
            </span>
            <span style="font-size:13px; font-weight:700; color:var(--success);"><?php echo $completion_pct; ?>%</span>
        </div>
        <div class="progress-custom">
            <div class="progress-bar-custom" style="width:<?php echo $completion_pct; ?>%; background: linear-gradient(90deg, var(--primary), var(--success));"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ ORDERS TABLE ============ -->
    <div class="xato-card">
        <div class="xato-card-header">
            <h5><i class="las la-list" style="color:var(--primary);margin-left:6px;"></i> الطلبات التفصيلية</h5>
            <span style="font-size:12px; color:var(--text-muted);">آخر 20 طلب</span>
        </div>

        <!-- Toolbar -->
        <div class="table-toolbar">
            <div class="table-search">
                <input type="text" id="orderSearch" placeholder="ابحث في طلباتك...">
                <i class="las la-search"></i>
            </div>
            <select class="filter-select" id="statusFilter">
                <option value="">جميع الحالات</option>
                <option value="pending">معلق</option>
                <option value="in_progress">قيد التنفيذ</option>
                <option value="completed">مكتمل</option>
                <option value="cancelled">ملغي</option>
            </select>
        </div>

        <div class="xato-card-body">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="las la-box-open"></i></div>
                    <h4>لا توجد طلبات بعد</h4>
                    <p>ابدأ بتصفح الخدمات المتاحة وأنشئ طلبك الأول</p>
                    <a href="/local_services/services.php" class="btn-xato btn-xato-primary" style="margin-top:15px; padding:10px 20px;">
                        <i class="las la-search"></i> تصفح الخدمات
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="xato-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>الخدمة</th>
                                <th>مزود الخدمة</th>
                                <th>المبلغ</th>
                                <th>طريقة الدفع</th>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="ordersBody">
                        <?php foreach ($orders as $o):
                            $order_status = $o['status'] ?? 'pending';

                            // ترجمة الحالة
                            $status_labels = [
                                'pending'     => 'معلق',
                                'processing'  => 'قيد المعالجة',
                                'in_progress' => 'قيد التنفيذ',
                                'completed'   => 'مكتمل',
                                'cancelled'   => 'ملغي',
                            ];
                            $status_label = $status_labels[$order_status] ?? $order_status;
                            $payment_method = $o['payment_method'] ?? 'غير محدد';
                        ?>
                            <tr data-status="<?php echo htmlspecialchars($order_status); ?>">
                                <td>
                                    <span class="order-id">#<?php echo htmlspecialchars($o['id']); ?></span>
                                </td>
                                <td>
                                    <div class="service-title"><?php echo htmlspecialchars($o['title']); ?></div>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:28px; height:28px; background:var(--primary-light); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--primary); font-weight:700; flex-shrink:0;">
                                            <?php echo mb_substr($o['provider_name'], 0, 1); ?>
                                        </div>
                                        <span class="provider-name"><?php echo htmlspecialchars($o['provider_name']); ?></span>
                                    </div>
                                </td>
                                <td class="amount-cell">
                                    <?php echo number_format((float)$o['amount'], 2); ?> ر.س
                                </td>
                                <td>
                                    <span class="payment-pill">
                                        <i class="las la-credit-card"></i>
                                        <?php echo htmlspecialchars($payment_method); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color:var(--text-muted); font-size:12px;">
                                        <?php echo date('Y/m/d', strtotime($o['order_date'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill status-<?php echo htmlspecialchars($order_status); ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                                        <a href="/local_services/view_order.php?id=<?php echo $o['id']; ?>"
                                           class="btn-xato btn-xato-primary">
                                            <i class="las la-eye"></i> عرض
                                        </a>
                                        <?php if ($order_status === 'completed'): ?>
                                            <a href="/local_services/review_service.php?order_id=<?php echo $o['id']; ?>"
                                               class="btn-xato btn-xato-warning">
                                                <i class="las la-star"></i> تقييم
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- ============ SCRIPTS ============ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- Sidebar Toggle ---
    const sidebar   = document.getElementById('appSidebar');
    const mainArea  = document.getElementById('appMain');
    const toggleBtn = document.getElementById('sidebarToggle');
    let isMobile = window.innerWidth <= 768;

    toggleBtn.addEventListener('click', () => {
        if (isMobile) {
            sidebar.classList.toggle('mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
            mainArea.classList.toggle('expanded');
        }
    });

    window.addEventListener('resize', () => {
        isMobile = window.innerWidth <= 768;
    });

    // --- Live Order Search ---
    const searchInput   = document.getElementById('orderSearch');
    const statusFilter  = document.getElementById('statusFilter');
    const ordersBody    = document.getElementById('ordersBody');

    function filterTable() {
        if (!ordersBody) return;
        const query  = (searchInput ? searchInput.value : '').toLowerCase();
        const status = statusFilter ? statusFilter.value : '';
        const rows   = ordersBody.querySelectorAll('tr');
        rows.forEach(row => {
            const text       = row.innerText.toLowerCase();
            const rowStatus  = row.dataset.status || '';
            const matchText  = !query  || text.includes(query);
            const matchStatus= !status || rowStatus === status;
            row.style.display = (matchText && matchStatus) ? '' : 'none';
        });
    }

    if (searchInput)  searchInput.addEventListener('input', filterTable);
    if (statusFilter) statusFilter.addEventListener('change', filterTable);

    // --- Global search forward ---
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        globalSearch.addEventListener('keydown', e => {
            if (e.key === 'Enter' && globalSearch.value.trim()) {
                window.location.href = '/local_services/services.php?q=' + encodeURIComponent(globalSearch.value.trim());
            }
        });
    }
</script>

</body>
</html>