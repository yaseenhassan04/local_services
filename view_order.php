<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

global $pdo;
$user = getCurrentUser();
$user_id = $user['id'];
$user_role = $user['role'];

// ============================================================
// دوال مساعدة
// ============================================================

if (!function_exists('translate_status')) {
    function translate_status($status) {
        $status = $status ?: 'pending';
        $translations = [
            'pending'     => 'قيد الانتظار',
            'processing'  => 'قيد المعالجة',
            'in_progress' => 'قيد التنفيذ',
            'completed'   => 'مكتمل',
            'cancelled'   => 'ملغاة'
        ];
        return $translations[$status] ?? 'غير محدد';
    }
}

if (!function_exists('display_payment_status')) {
    function display_payment_status($status) {
        $status = $status ?: 'pending_upload';
        $statuses = [
            'paid'                 => ['display' => 'مدفوع', 'type' => 'success'],
            'pending_upload'       => ['display' => 'بانتظار التحقق', 'type' => 'warning'],
            'pending_verification' => ['display' => 'قيد التحقق', 'type' => 'warning'],
        ];
        return $statuses[$status] ?? ['display' => 'غير محدد', 'type' => 'muted'];
    }
}

function get_status_css_class($status) {
    $map = [
        'pending'     => 'warning',
        'processing'  => 'purple',
        'in_progress' => 'info',
        'completed'   => 'success',
        'cancelled'   => 'danger',
    ];
    return $map[$status] ?? 'muted';
}

function get_status_icon($status) {
    $icons = [
        'pending'     => 'la-clock',
        'processing'  => 'la-cog',
        'in_progress' => 'la-play-circle',
        'completed'   => 'la-check-circle',
        'cancelled'   => 'la-times-circle',
    ];
    return $icons[$status] ?? 'la-question-circle';
}

// ============================================================
// التحقق من رقم الطلب وجلب البيانات
// ============================================================

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    set_message("رقم الطلب غير صالح.", "danger");
    header("Location: /local_services/{$user_role}_dashboard.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            s.title AS service_title, 
            p.full_name AS provider_name, 
            p.phone AS provider_phone,
            c.full_name AS client_name, 
            c.phone AS client_phone,
            (SELECT COUNT(r.id) FROM reviews r WHERE r.order_id = o.id) AS review_count,
            COALESCE(o.status, 'pending') AS actual_status,
            COALESCE(o.payment_status, 'pending_upload') AS actual_payment_status
        FROM orders o
        JOIN services s ON o.service_id = s.id
        JOIN users p ON o.provider_id = p.id
        JOIN users c ON o.client_id = c.id
        WHERE o.id = ? AND (o.client_id = ? OR o.provider_id = ?)
    ");
    $stmt->execute([$order_id, $user_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Order fetching error: " . $e->getMessage());
    set_message("حدث خطأ في جلب بيانات الطلب", "danger");
    header("Location: /local_services/dashboard.php");
    exit;
}

if (!$order) {
    $redirect_url = ($user_role === 'client') ? 'client_dashboard.php' : 'provider_dashboard.php';
    set_message("الطلب غير موجود أو ليس لديك صلاحية لعرضه.", "danger");
    header("Location: /local_services/{$redirect_url}");
    exit;
}

$current_status         = $order['actual_status'];
$current_payment_status = $order['actual_payment_status'];
$is_client   = ($user_id == $order['client_id']);
$is_provider = ($user_id == $order['provider_id']);
$csrf_token  = generateCsrfToken();

$can_cancel       = $is_client   && in_array($current_status, ['pending', 'processing']);
$can_review       = $is_client   && ($current_status === 'completed' && $order['review_count'] == 0);
$can_start_work   = $is_provider && $current_payment_status === 'paid' && $current_status === 'processing';
$can_complete_work= $is_provider && $current_status === 'in_progress';
$already_reviewed = $is_client   && $order['review_count'] > 0;

require_once __DIR__ . '/includes/header.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب #<?php echo $order_id; ?> | منصة الخدمات المحلية</title>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        /* ============================================
           إلغاء أي styles تجي من header.php الخارجي
        ============================================ */
        /* يخفي أي navbar/header قديم من includes/header.php */
        body > nav,
        body > header:not(.app-header),
        .navbar,
        .navbar-default,
        .top-header,
        .site-header,
        nav.navbar,
        #header,
        #top-bar,
        .main-header {
            display: none !important;
        }
        /* يلغي أي padding-top حطّه header قديم */
        body { padding-top: 0 !important; margin-top: 0 !important; }

        /* ============================================
           XATO DARK THEME — VIEW ORDER PAGE
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

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        ::-webkit-scrollbar-thumb { background: #1b2e4b; border-radius: 10px; }

        /* ============ HEADER ============ */
        .app-header {
            position: fixed; top: 0; right: 0; left: 0;
            height: var(--header-height);
            background: var(--header-bg);
            border-bottom: 1px solid var(--card-border);
            z-index: 1030;
            display: flex; align-items: center;
            padding: 0 20px; gap: 15px;
        }
        .header-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; min-width: 200px;
        }
        .header-logo .logo-icon {
            width: 36px; height: 36px; background: var(--primary);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 18px; color: #fff;
        }
        .header-logo span { font-size: 18px; font-weight: 800; color: var(--text-primary); }
        .header-toggle {
            background: none; border: none; color: var(--text-muted);
            font-size: 22px; cursor: pointer; padding: 5px 8px;
            border-radius: 6px; transition: all .2s;
        }
        .header-toggle:hover { background: var(--primary-light); color: var(--primary); }
        .header-icon-btn {
            width: 38px; height: 38px; background: var(--dark-bg);
            border: 1px solid var(--card-border); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); font-size: 18px;
            transition: all .2s; text-decoration: none;
        }
        .header-icon-btn:hover { border-color: var(--primary); color: var(--primary); }
        .header-user {
            display: flex; align-items: center; gap: 10px;
            padding: 5px 10px; border-radius: 8px;
            text-decoration: none; transition: background .2s;
        }
        .header-user:hover { background: var(--primary-light); }
        .user-avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 15px; font-weight: 700; color: #fff;
        }
        .user-info .user-name { font-size: 13px; font-weight: 700; color: var(--text-primary); display: block; line-height: 1.2; }
        .user-info .user-role { font-size: 11px; color: var(--text-muted); display: block; }

        /* ============ SIDEBAR ============ */
        .app-sidebar {
            position: fixed; top: var(--header-height); right: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: var(--sidebar-bg);
            border-left: 1px solid var(--card-border);
            overflow-y: auto; z-index: 1020;
            transition: transform .3s ease;
        }
        .app-sidebar.collapsed { transform: translateX(var(--sidebar-width)); }
        .sidebar-section-title {
            padding: 20px 20px 8px; font-size: 11px; font-weight: 700;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
        }
        .sidebar-menu { list-style: none; padding: 5px 10px; }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: var(--radius);
            color: var(--text-muted); text-decoration: none;
            font-size: 14px; font-weight: 500; transition: all .2s;
        }
        .sidebar-menu a i { font-size: 18px; min-width: 22px; }
        .sidebar-menu a:hover { background: var(--primary-light); color: var(--primary); text-decoration: none; }
        .sidebar-menu a.active { background: var(--primary); color: #fff; box-shadow: 0 4px 15px rgba(67,97,238,.4); }
        .sidebar-menu a.logout-link { color: var(--danger); }
        .sidebar-menu a.logout-link:hover { background: var(--danger-light); }

        .user-profile-card {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: var(--radius); padding: 20px;
            margin: 15px 10px; text-align: center;
        }
        .profile-avatar-lg {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 26px; font-weight: 800; color: #fff;
            margin: 0 auto 12px;
        }
        .profile-name { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .profile-email { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .profile-divider { border: none; border-top: 1px solid var(--card-border); margin: 12px 0; }
        .btn-edit-profile {
            display: block; width: 100%; padding: 8px;
            background: var(--primary-light); color: var(--primary);
            border-radius: 6px; text-decoration: none;
            font-size: 13px; font-weight: 600; margin-top: 12px; transition: all .2s;
        }
        .btn-edit-profile:hover { background: var(--primary); color: #fff; text-decoration: none; }

        /* ============ MAIN ============ */
        .app-main {
            margin-right: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px 25px;
            min-height: calc(100vh - var(--header-height));
            transition: margin-right .3s ease;
        }
        .app-main.expanded { margin-right: 0; }

        /* ============ PAGE HEADER ============ */
        .page-title-area {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 25px; flex-wrap: wrap; gap: 10px;
        }
        .page-title h2 { font-size: 22px; font-weight: 800; color: var(--text-primary); margin: 0; }
        .breadcrumb-custom {
            display: flex; align-items: center; gap: 6px;
            list-style: none; padding: 0; margin: 5px 0 0;
        }
        .breadcrumb-custom li { font-size: 12px; color: var(--text-muted); }
        .breadcrumb-custom li a { color: var(--primary); text-decoration: none; }
        .breadcrumb-custom li:not(:last-child)::after { content: '/'; margin-right: 6px; color: var(--card-border); }

        /* ============ LAYOUT GRID ============ */
        .order-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 22px;
            align-items: start;
        }

        /* ============ CARDS ============ */
        .xato-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .xato-card:last-child { margin-bottom: 0; }
        .xato-card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--card-border);
            display: flex; align-items: center; gap: 10px;
        }
        .xato-card-header h5 { font-size: 15px; font-weight: 700; color: var(--text-primary); margin: 0; }
        .xato-card-body { padding: 20px; }

        /* ============ DETAILS TABLE ============ */
        .detail-rows { width: 100%; }
        .detail-row {
            display: flex; align-items: flex-start;
            padding: 13px 0;
            border-bottom: 1px solid rgba(27,46,75,.5);
            gap: 12px;
        }
        .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
        .detail-row:first-child { padding-top: 0; }
        .detail-label {
            font-size: 12px; font-weight: 700;
            color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.6px; min-width: 130px; flex-shrink: 0;
            padding-top: 2px;
        }
        .detail-value { font-size: 14px; color: var(--text-primary); font-weight: 500; flex: 1; }
        .detail-value.amount { color: var(--success); font-size: 18px; font-weight: 800; }
        .detail-value a { color: var(--primary); text-decoration: none; }
        .detail-value a:hover { text-decoration: underline; }

        /* ============ STATUS PILLS ============ */
        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 30px;
            font-size: 12px; font-weight: 700;
        }
        .status-pill i { font-size: 14px; }
        .pill-warning  { background: var(--warning-light);  color: var(--warning);  }
        .pill-success  { background: var(--success-light);  color: var(--success);  }
        .pill-danger   { background: var(--danger-light);   color: var(--danger);   }
        .pill-info     { background: var(--info-light);     color: var(--info);     }
        .pill-purple   { background: var(--purple-light);   color: var(--purple);   }
        .pill-muted    { background: rgba(136,142,168,.15); color: var(--text-muted); }

        /* ============ TEXT BLOCK ============ */
        .text-block {
            background: var(--dark-bg);
            border: 1px solid var(--card-border);
            border-right: 3px solid var(--primary);
            border-radius: var(--radius);
            padding: 14px 16px;
            font-size: 14px; color: var(--text-dark);
            line-height: 1.7; white-space: pre-wrap; word-wrap: break-word;
        }
        .text-block.empty { color: #3a4a6b; font-style: italic; }

        /* ============ CONTACT CARD ============ */
        .contact-xato {
            background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
        }
        .contact-xato .c-title {
            font-size: 14px; font-weight: 800; color: #fff;
            margin-bottom: 16px; opacity: .95;
        }
        .contact-item { margin-bottom: 12px; }
        .contact-item:last-child { margin-bottom: 0; }
        .contact-item .c-label { font-size: 11px; color: rgba(255,255,255,.65); font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; display: block; margin-bottom: 3px; }
        .contact-item .c-value { font-size: 14px; color: #fff; font-weight: 600; }
        .contact-item a { color: rgba(255,255,255,.9); text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .contact-item a:hover { color: #fff; }

        /* ============ ACTIONS CARD ============ */
        .actions-xato {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .actions-xato .a-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--card-border);
            font-size: 14px; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 8px;
        }
        .actions-xato .a-body { padding: 16px; display: flex; flex-direction: column; gap: 10px; }

        /* ============ ACTION BUTTONS ============ */
        .btn-action {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 11px 16px;
            border: none; border-radius: var(--radius);
            font-family: 'Tajawal', sans-serif;
            font-size: 14px; font-weight: 700;
            cursor: pointer; text-decoration: none;
            transition: all .2s; text-align: center;
        }
        .btn-action i { font-size: 16px; }
        .btn-action-info    { background: var(--info-light);    color: var(--info);    border: 1px solid rgba(33,150,243,.3); }
        .btn-action-info:hover    { background: var(--info);    color: #fff; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(33,150,243,.35); }
        .btn-action-success { background: var(--success-light); color: var(--success); border: 1px solid rgba(0,171,85,.3); }
        .btn-action-success:hover { background: var(--success); color: #fff; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,171,85,.35); }
        .btn-action-warning { background: var(--warning-light); color: var(--warning); border: 1px solid rgba(226,160,63,.3); }
        .btn-action-warning:hover { background: var(--warning); color: #fff; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(226,160,63,.35); }
        .btn-action-danger  { background: var(--danger-light);  color: var(--danger);  border: 1px solid rgba(231,81,90,.3); }
        .btn-action-danger:hover  { background: var(--danger);  color: #fff; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(231,81,90,.35); }
        .btn-action-ghost   { background: transparent; color: var(--text-muted); border: 1px solid var(--card-border); }
        .btn-action-ghost:hover   { border-color: var(--primary); color: var(--primary); background: var(--primary-light); text-decoration: none; }

        /* ============ INLINE ALERTS ============ */
        .xato-alert {
            padding: 12px 14px; border-radius: var(--radius);
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; font-weight: 500;
        }
        .xato-alert i { font-size: 17px; flex-shrink: 0; }
        .xato-alert-success { background: var(--success-light); color: var(--success); border: 1px solid rgba(0,171,85,.25); }
        .xato-alert-info    { background: var(--info-light);    color: var(--info);    border: 1px solid rgba(33,150,243,.25); }
        .xato-alert-warning { background: var(--warning-light); color: var(--warning); border: 1px solid rgba(226,160,63,.25); }
        .xato-alert-danger  { background: var(--danger-light);  color: var(--danger);  border: 1px solid rgba(231,81,90,.25); }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 900px) {
            .order-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .app-sidebar { transform: translateX(var(--sidebar-width)); }
            .app-sidebar.mobile-open { transform: translateX(0); }
            .app-main { margin-right: 0; padding: 15px; }
            .user-info { display: none; }
            .detail-label { min-width: 100px; }
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

    <button class="header-toggle" id="sidebarToggle">
        <i class="las la-bars"></i>
    </button>

    <div style="flex:1;"></div>

    <div style="margin-left:10px;">
        <a href="/local_services/<?php echo $user_role; ?>_dashboard.php" class="header-icon-btn" title="لوحة التحكم">
            <i class="las la-clipboard-list"></i>
        </a>
    </div>

    <a href="/local_services/profile.php" class="header-user">
        <div class="user-avatar"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
            <span class="user-role"><?php echo $user_role === 'client' ? 'عميل' : 'مزود خدمة'; ?></span>
        </div>
    </a>
</header>

<!-- ============ SIDEBAR ============ -->
<aside class="app-sidebar" id="appSidebar">
    <div class="user-profile-card">
        <div class="profile-avatar-lg"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
        <div class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
        <hr class="profile-divider">
        <a href="/local_services/profile.php" class="btn-edit-profile">
            <i class="las la-user-edit"></i> تعديل الملف الشخصي
        </a>
    </div>

    <div class="sidebar-section-title">القائمة الرئيسية</div>
    <ul class="sidebar-menu">
        <li><a href="/local_services/index.php"><i class="las la-home"></i> الرئيسية</a></li>
        <?php if ($user_role === 'client'): ?>
        <li><a href="/local_services/services.php"><i class="las la-concierge-bell"></i> تصفح الخدمات</a></li>
        <?php endif; ?>
        <li>
            <a href="/local_services/<?php echo $user_role; ?>_dashboard.php" class="active">
                <i class="las la-clipboard-list"></i>
                <?php echo $user_role === 'client' ? 'طلباتي' : 'طلبات العملاء'; ?>
            </a>
        </li>
        <li><a href="/local_services/profile.php"><i class="las la-user-circle"></i> الملف الشخصي</a></li>
    </ul>

    <div class="sidebar-section-title">الإعدادات</div>
    <ul class="sidebar-menu">
        <li><a href="/local_services/profile.php#password"><i class="las la-lock"></i> تغيير كلمة المرور</a></li>
        <li><a href="/local_services/logout.php" class="logout-link"><i class="las la-sign-out-alt"></i> خروج آمن</a></li>
    </ul>
</aside>

<!-- ============ MAIN ============ -->
<main class="app-main" id="appMain">

    <!-- Page Title -->
    <div class="page-title-area">
        <div class="page-title">
            <h2>
                <i class="las la-file-alt" style="color:var(--primary);margin-left:8px;"></i>
                تفاصيل الطلب #<?php echo $order_id; ?>
            </h2>
            <ul class="breadcrumb-custom">
                <li><a href="/local_services/index.php">الرئيسية</a></li>
                <li><a href="/local_services/<?php echo $user_role; ?>_dashboard.php">لوحة التحكم</a></li>
                <li class="active">طلب #<?php echo $order_id; ?></li>
            </ul>
        </div>
        <a href="/local_services/<?php echo $user_role; ?>_dashboard.php" class="btn-action btn-action-ghost" style="width:auto;padding:9px 16px;">
            <i class="las la-arrow-right"></i> العودة
        </a>
    </div>

    <!-- Flash Messages -->
    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- ============ LAYOUT ============ -->
    <div class="order-layout">

        <!-- ====== RIGHT COLUMN (main) ====== -->
        <div>

            <!-- ── بطاقة ملخص الطلب ── -->
            <div class="xato-card">
                <div class="xato-card-header">
                    <i class="las la-clipboard-check" style="color:var(--primary);font-size:18px;"></i>
                    <h5>ملخص الطلب</h5>
                    <?php
                        $sc = get_status_css_class($current_status);
                    ?>
                    <span class="status-pill pill-<?php echo $sc; ?>" style="margin-right:auto;">
                        <i class="las <?php echo get_status_icon($current_status); ?>"></i>
                        <?php echo translate_status($current_status); ?>
                    </span>
                </div>
                <div class="xato-card-body">
                    <div class="detail-rows">
                        <div class="detail-row">
                            <span class="detail-label">الخدمة</span>
                            <span class="detail-value">
                                <a href="/local_services/service_detail.php?id=<?php echo $order['service_id']; ?>">
                                    <?php echo htmlspecialchars($order['service_title']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">المبلغ الإجمالي</span>
                            <span class="detail-value amount">
                                <?php
                                    $amount = $order['total_amount'] ?? $order['amount'] ?? 0;
                                    echo number_format((float)$amount, 2);
                                ?> ر.س
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">تاريخ الطلب</span>
                            <span class="detail-value">
                                <i class="las la-calendar" style="color:var(--text-muted);margin-left:4px;"></i>
                                <?php echo date('d/m/Y — H:i', strtotime($order['order_date'])); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">حالة الطلب</span>
                            <span class="detail-value">
                                <span class="status-pill pill-<?php echo $sc; ?>">
                                    <i class="las <?php echo get_status_icon($current_status); ?>"></i>
                                    <?php echo translate_status($current_status); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">حالة الدفع</span>
                            <span class="detail-value">
                                <?php
                                    $pi = display_payment_status($current_payment_status);
                                    $ptype = $pi['type'];
                                ?>
                                <span class="status-pill pill-<?php echo $ptype; ?>">
                                    <i class="las la-<?php echo $ptype === 'success' ? 'check-circle' : 'clock'; ?>"></i>
                                    <?php echo $pi['display']; ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── تفاصيل الطلب ── -->
            <div class="xato-card">
                <div class="xato-card-header">
                    <i class="las la-comment-dots" style="color:var(--primary);font-size:18px;"></i>
                    <h5>تفاصيل طلب العميل</h5>
                </div>
                <div class="xato-card-body">
                    <div class="text-block <?php echo empty($order['details']) ? 'empty' : ''; ?>">
                        <?php echo nl2br(htmlspecialchars($order['details'] ?: 'لم يضف العميل تفاصيل إضافية.')); ?>
                    </div>
                </div>
            </div>

            <!-- ── معلومات الدفع ── -->
            <div class="xato-card">
                <div class="xato-card-header">
                    <i class="las la-wallet" style="color:var(--warning);font-size:18px;"></i>
                    <h5>معلومات الدفع</h5>
                </div>
                <div class="xato-card-body">
                    <div class="text-block <?php echo empty($order['payment_proof']) ? 'empty' : ''; ?>">
                        <?php echo nl2br(htmlspecialchars($order['payment_proof'] ?: 'لا توجد معلومات دفع متاحة.')); ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- ====== LEFT COLUMN (sidebar) ====== -->
        <div>

            <!-- ── بطاقة التواصل ── -->
            <div class="contact-xato">
                <div class="c-title">
                    <i class="las la-user-friends" style="margin-left:6px;"></i>
                    معلومات التواصل
                </div>
                <?php if ($is_client): ?>
                    <div class="contact-item">
                        <span class="c-label">مزود الخدمة</span>
                        <span class="c-value"><?php echo htmlspecialchars($order['provider_name']); ?></span>
                    </div>
                    <div class="contact-item">
                        <span class="c-label">رقم الهاتف</span>
                        <a href="tel:<?php echo htmlspecialchars($order['provider_phone']); ?>" class="c-value">
                            <i class="las la-phone"></i>
                            <?php echo htmlspecialchars($order['provider_phone']); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="contact-item">
                        <span class="c-label">العميل</span>
                        <span class="c-value"><?php echo htmlspecialchars($order['client_name']); ?></span>
                    </div>
                    <div class="contact-item">
                        <span class="c-label">رقم الهاتف</span>
                        <a href="tel:<?php echo htmlspecialchars($order['client_phone']); ?>" class="c-value">
                            <i class="las la-phone"></i>
                            <?php echo htmlspecialchars($order['client_phone']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── الإجراءات ── -->
            <div class="actions-xato">
                <div class="a-header">
                    <i class="las la-sliders-h" style="color:var(--primary);"></i>
                    الإجراءات المتاحة
                </div>
                <div class="a-body">

                    <?php if ($can_start_work): ?>
                        <form action="/local_services/actions/update_order.php" method="POST">
                            <input type="hidden" name="order_id"   value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="new_status" value="in_progress">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-action btn-action-info">
                                <i class="las la-play-circle"></i> بدء التنفيذ
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($can_complete_work): ?>
                        <form action="/local_services/actions/update_order.php" method="POST">
                            <input type="hidden" name="order_id"   value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="new_status" value="completed">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-action btn-action-success">
                                <i class="las la-check-circle"></i> إنهاء الخدمة
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($can_review): ?>
                        <a href="/local_services/review_service.php?order_id=<?php echo $order['id']; ?>"
                           class="btn-action btn-action-warning">
                            <i class="las la-star"></i> تقييم الخدمة
                        </a>
                    <?php endif; ?>

                    <?php if ($already_reviewed): ?>
                        <div class="xato-alert xato-alert-success">
                            <i class="las la-check-circle"></i>
                            تم تقييم هذه الخدمة بنجاح
                        </div>
                    <?php endif; ?>

                    <?php if ($can_cancel): ?>
                        <form action="/local_services/actions/update_order.php" method="POST"
                              onsubmit="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟');">
                            <input type="hidden" name="order_id"   value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="new_status" value="cancelled">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <button type="submit" class="btn-action btn-action-danger">
                                <i class="las la-times-circle"></i> إلغاء الطلب
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$can_cancel && !$can_review && !$can_start_work && !$can_complete_work && !$already_reviewed): ?>
                        <div class="xato-alert xato-alert-info">
                            <i class="las la-info-circle"></i>
                            لا توجد إجراءات متاحة حالياً
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

</main>

<!-- ============ SCRIPTS ============ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
    window.addEventListener('resize', () => { isMobile = window.innerWidth <= 768; });
</script>

</body>
</html>

<?php require_once __DIR__ . '/includes/footer.php'; ?>