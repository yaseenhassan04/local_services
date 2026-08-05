<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

check_login('admin');
$current_page = 'manage_orders'; // ← أضف هذا السطر فقط

global $pdo;
$orders = [];

// فلاتر
$filter_status = trim($_GET['status'] ?? '');
$filter_q      = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($filter_status !== '') { $where[] = "o.status = ?"; $params[] = $filter_status; }
if ($filter_q !== '') {
    $where[] = "(u_client.full_name LIKE ? OR u_provider.full_name LIKE ? OR s.title LIKE ?)";
    $params[] = "%$filter_q%"; $params[] = "%$filter_q%"; $params[] = "%$filter_q%";
}

$where_sql = $where ? "WHERE " . implode(' AND ', $where) : "";

try {
    $sql = "
        SELECT o.id, o.status, COALESCE(o.amount,0) AS amount,
               o.order_date AS created_at,
               s.title AS service_title,
               c.name  AS category_name,
               u_client.full_name   AS client_name,
               u_provider.full_name AS provider_name
        FROM orders o
        JOIN services s ON o.service_id = s.id
        JOIN users u_client   ON o.client_id   = u_client.id
        JOIN users u_provider ON s.provider_id = u_provider.id
        LEFT JOIN categories c ON s.category_id = c.id
        $where_sql
        ORDER BY o.order_date DESC
    ";
    $stmt   = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات سريعة
    $stats_row = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status='pending'     THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status='completed'   THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status='cancelled'   THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN status='completed'   THEN COALESCE(amount,0) ELSE 0 END) AS revenue
        FROM orders
    ")->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $orders   = [];
    $stats_row = [];
    $err_msg  = $e->getMessage();
}

if (!function_exists('get_status_display')) {
    function get_status_display($s) {
        return ['pending'=>'معلق','processing'=>'قيد المعالجة','in_progress'=>'قيد التنفيذ','completed'=>'مكتمل','cancelled'=>'ملغي'][$s] ?? $s;
    }
}
function order_status_css($s) {
    return ['pending'=>'warning','processing'=>'purple','in_progress'=>'info','completed'=>'success','cancelled'=>'danger'][$s] ?? 'muted';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة الطلبات | لوحة المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
body>nav,body>header:not(.app-header),body>footer,.navbar,.navbar-default,.top-header,.site-header,nav.navbar,#header,#top-bar,.main-header,footer,.footer,.site-footer,#footer{display:none!important}
body{padding-top:0!important;margin-top:0!important}
:root{
    --dark-bg:#060818;--sidebar-bg:#0e1726;--card-bg:#0e1726;--card-border:#1b2e4b;
    --header-bg:#0e1726;--text-primary:#e0e6ed;--text-muted:#888ea8;--text-dark:#bfc9d4;
    --primary:#4361ee;--primary-light:rgba(67,97,238,.15);
    --success:#00ab55;--success-light:rgba(0,171,85,.15);
    --warning:#e2a03f;--warning-light:rgba(226,160,63,.15);
    --danger:#e7515a;--danger-light:rgba(231,81,90,.15);
    --info:#2196f3;--info-light:rgba(33,150,243,.15);
    --purple:#805dca;--purple-light:rgba(128,93,202,.15);
    --sidebar-width:255px;--header-height:70px;--radius:8px;--shadow:0 6px 10px rgba(0,0,0,.4);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Tajawal',sans-serif;background:var(--dark-bg);color:var(--text-primary);font-size:14px;direction:rtl}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--sidebar-bg)}::-webkit-scrollbar-thumb{background:#1b2e4b;border-radius:10px}

/* HEADER */
.app-header{position:fixed;top:0;right:0;left:0;height:var(--header-height);background:var(--header-bg);border-bottom:1px solid var(--card-border);z-index:1030;display:flex;align-items:center;padding:0 20px;gap:15px}
.header-logo{display:flex;align-items:center;gap:10px;text-decoration:none;min-width:200px}
.header-logo .logo-icon{width:36px;height:36px;background:var(--danger);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff}
.header-logo span{font-size:18px;font-weight:800;color:var(--text-primary)}
.header-toggle{background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer;padding:5px 8px;border-radius:6px;transition:all .2s}
.header-toggle:hover{background:var(--primary-light);color:var(--primary)}
.header-icon-btn{width:38px;height:38px;background:var(--dark-bg);border:1px solid var(--card-border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:18px;transition:all .2s;text-decoration:none}
.header-icon-btn:hover{border-color:var(--primary);color:var(--primary)}
.header-user{display:flex;align-items:center;gap:10px;padding:5px 10px;border-radius:8px;text-decoration:none;transition:background .2s}
.header-user:hover{background:var(--primary-light)}
.user-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--danger),var(--purple));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff}
.user-info .user-name{font-size:13px;font-weight:700;color:var(--text-primary);display:block;line-height:1.2}
.user-info .user-role{font-size:11px;color:var(--text-muted);display:block}

/* SIDEBAR */
.app-sidebar{position:fixed;top:var(--header-height);right:0;width:var(--sidebar-width);height:calc(100vh - var(--header-height));background:var(--sidebar-bg);border-left:1px solid var(--card-border);overflow-y:auto;z-index:1020;transition:transform .3s ease}
.app-sidebar.collapsed{transform:translateX(var(--sidebar-width))}
.sidebar-section-title{padding:20px 20px 8px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
.sidebar-menu{list-style:none;padding:5px 10px}
.sidebar-menu li{margin-bottom:2px}
.sidebar-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);color:var(--text-muted);text-decoration:none;font-size:14px;font-weight:500;transition:all .2s}
.sidebar-menu a i{font-size:18px;min-width:22px}
.sidebar-menu a:hover{background:var(--primary-light);color:var(--primary);text-decoration:none}
.sidebar-menu a.active{background:var(--primary);color:#fff;box-shadow:0 4px 15px rgba(67,97,238,.4)}
.sidebar-menu a.logout-link{color:var(--danger)}
.sidebar-menu a.logout-link:hover{background:var(--danger-light)}
.user-profile-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:20px;margin:15px 10px;text-align:center}
.profile-avatar-lg{width:64px;height:64px;background:linear-gradient(135deg,var(--danger),var(--purple));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#fff;margin:0 auto 12px}
.profile-name{font-size:15px;font-weight:700;color:var(--text-primary)}
.profile-email{font-size:12px;color:var(--text-muted);margin-top:2px}
.profile-divider{border:none;border-top:1px solid var(--card-border);margin:12px 0}
.btn-edit-profile{display:block;width:100%;padding:8px;background:var(--danger-light);color:var(--danger);border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;margin-top:12px;transition:all .2s}
.btn-edit-profile:hover{background:var(--danger);color:#fff;text-decoration:none}

/* MAIN */
.app-main{margin-right:var(--sidebar-width);margin-top:var(--header-height);padding:25px;min-height:calc(100vh - var(--header-height));transition:margin-right .3s ease}
.app-main.expanded{margin-right:0}

/* PAGE TITLE */
.page-title-area{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;flex-wrap:wrap;gap:10px}
.page-title h2{font-size:22px;font-weight:800;color:var(--text-primary);margin:0}
.breadcrumb-custom{display:flex;align-items:center;gap:6px;list-style:none;padding:0;margin:5px 0 0}
.breadcrumb-custom li{font-size:12px;color:var(--text-muted)}
.breadcrumb-custom li a{color:var(--primary);text-decoration:none}
.breadcrumb-custom li:not(:last-child)::after{content:'/';margin-right:6px;color:var(--card-border)}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:22px}
.stat-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:18px;display:flex;align-items:center;gap:14px;transition:transform .2s,box-shadow .2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow)}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.stat-icon.primary{background:var(--primary-light);color:var(--primary)}
.stat-icon.success{background:var(--success-light);color:var(--success)}
.stat-icon.warning{background:var(--warning-light);color:var(--warning)}
.stat-icon.danger{background:var(--danger-light);color:var(--danger)}
.stat-icon.info{background:var(--info-light);color:var(--info)}
.stat-icon.purple{background:var(--purple-light);color:var(--purple)}
.stat-value{font-size:24px;font-weight:800;color:var(--text-primary);line-height:1}
.stat-label{font-size:12px;color:var(--text-muted);margin-top:3px}

/* CARD */
.xato-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden;margin-bottom:22px}
.xato-card-header{padding:15px 20px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.xato-card-header h5{font-size:15px;font-weight:700;color:var(--text-primary);margin:0}

/* FILTER BAR */
.filter-bar{padding:14px 20px;display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--card-border)}
.filter-group{display:flex;flex-direction:column;gap:5px;flex:1;min-width:160px}
.filter-group label{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px}
.filter-input{background:var(--dark-bg);border:1px solid var(--card-border);color:var(--text-primary);border-radius:6px;padding:8px 12px;font-family:'Tajawal',sans-serif;font-size:13px;width:100%}
.filter-input:focus{outline:none;border-color:var(--primary)}
.filter-input option{background:var(--dark-bg)}
.filter-search-wrap{position:relative;flex:2;min-width:200px}
.filter-search-wrap .filter-input{padding-right:34px}
.filter-search-wrap i{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px}
.btn-filter{display:flex;align-items:center;gap:6px;padding:8px 18px;background:var(--primary);color:#fff;border:none;border-radius:6px;font-family:'Tajawal',sans-serif;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .2s}
.btn-filter:hover{background:#3a56d4;transform:translateY(-1px)}
.btn-reset{display:flex;align-items:center;gap:5px;padding:8px 12px;background:transparent;border:1px solid var(--card-border);color:var(--text-muted);border-radius:6px;font-family:'Tajawal',sans-serif;font-size:13px;cursor:pointer;text-decoration:none;transition:all .2s}
.btn-reset:hover{border-color:var(--danger);color:var(--danger);text-decoration:none}

/* TABLE */
.xato-table{width:100%;border-collapse:collapse}
.xato-table thead th{background:rgba(27,46,75,.5);color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:11px 14px;border-bottom:1px solid var(--card-border);white-space:nowrap}
.xato-table tbody tr{border-bottom:1px solid rgba(27,46,75,.5);transition:background .15s}
.xato-table tbody tr:hover{background:rgba(27,46,75,.4)}
.xato-table tbody td{padding:12px 14px;color:var(--text-dark);font-size:13px;vertical-align:middle}
.xato-table tbody tr:last-child{border-bottom:none}
.order-id{font-weight:700;color:var(--primary)}
.amount-cell{font-weight:700;color:var(--success)}

/* STATUS PILLS */
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:30px;font-size:11px;font-weight:700}
.status-pill::before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor}
.pill-warning{background:var(--warning-light);color:var(--warning)}
.pill-success{background:var(--success-light);color:var(--success)}
.pill-danger{background:var(--danger-light);color:var(--danger)}
.pill-info{background:var(--info-light);color:var(--info)}
.pill-purple{background:var(--purple-light);color:var(--purple)}
.pill-muted{background:rgba(136,142,168,.1);color:var(--text-muted)}

/* BUTTONS */
.btn-xato{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:700;font-family:'Tajawal',sans-serif;text-decoration:none;border:none;cursor:pointer;transition:all .2s;white-space:nowrap}
.btn-xato-primary{background:var(--primary-light);color:var(--primary)}
.btn-xato-primary:hover{background:var(--primary);color:#fff;text-decoration:none}
.btn-xato-danger{background:var(--danger-light);color:var(--danger)}
.btn-xato-danger:hover{background:var(--danger);color:#fff;text-decoration:none}

/* EMPTY */
.empty-state{text-align:center;padding:60px 20px}
.empty-icon{width:80px;height:80px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:36px;color:var(--primary);margin:0 auto 16px}
.empty-state h4{font-size:17px;font-weight:700;color:var(--text-primary);margin-bottom:7px}
.empty-state p{font-size:13px;color:var(--text-muted)}

/* ALERT */
.xato-alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:500}
.xato-alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid rgba(231,81,90,.25)}

@media(max-width:768px){
    .app-sidebar{transform:translateX(var(--sidebar-width))}
    .app-sidebar.mobile-open{transform:translateX(0)}
    .app-main{margin-right:0;padding:14px}
    .user-info{display:none}
    .stats-grid{grid-template-columns:1fr 1fr}
}

/* ══ LIGHT MODE ══════════════════════════════════════ */
body.light-mode {
    --dark:   #f0f2f5;
    --card:   #ffffff;
    --border: #e5e7eb;
    --txt:    #1a2332;
    --muted:  #6b7280;
    --dark2:  #374151;
    --pri-lt: rgba(67,97,238,.1);
    --suc-lt: rgba(0,171,85,.1);
    --war-lt: rgba(226,160,63,.1);
    --dan-lt: rgba(231,81,90,.1);
    --inf-lt: rgba(33,150,243,.1);
    --pur-lt: rgba(128,93,202,.1);
}
body.light-mode,
body.light-mode .top-nav,
body.light-mode .sidebar,
body.light-mode nav.top-nav { background-color: #ffffff; }
body.light-mode .top-nav,
body.light-mode nav.top-nav { border-bottom-color: #e5e7eb; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
body.light-mode .sidebar     { border-left-color: #e5e7eb; }
body.light-mode .xcard,
body.light-mode .fin-card,
body.light-mode .report-card,
body.light-mode .card,
body.light-mode [class*="-card"] { background:#ffffff; border-color:#e5e7eb; }
body.light-mode .nav-icon    { background:#f3f4f6; border-color:#e5e7eb; color:#6b7280; }
body.light-mode .side-link   { color:#6b7280; }
body.light-mode .side-link:hover { background:rgba(67,97,238,.08); color:var(--primary); }
body.light-mode .side-link.active { background:var(--primary); color:#fff; }
body.light-mode .select-status,
body.light-mode select,
body.light-mode input,
body.light-mode textarea     { background:#f9fafb; border-color:#e5e7eb; color:#1a2332; }
body.light-mode table thead th { background:#1a2332 !important; color:#fff !important; }
body.light-mode .commission-box { background:linear-gradient(135deg,rgba(67,97,238,.05),rgba(0,171,85,.05)); }
body.light-mode .commission-row { border-bottom-color:#e5e7eb; }
body.light-mode .proof-box   { background:#f9fafb; border-color:#e5e7eb; }
body, .top-nav, nav.top-nav, .sidebar, .xcard, .fin-card, .report-card,
.nav-icon, .commission-box, .card { transition: background .3s, border-color .3s, color .2s !important; }

/* ══ LIGHT MODE — Sidebar Fix ══ */
body.light-mode {
    --dark-bg:    #f0f4f8;
    --sidebar-bg: #ffffff;
    --card-bg:    #ffffff;
    --card-border:#e5e7eb;
    --header-bg:  #ffffff;
    --text-primary:#1a2332;
    --text-muted: #6b7280;
    --text-dark:  #374151;
    --primary-light: rgba(67,97,238,.1);
    --success-light: rgba(0,171,85,.1);
    --warning-light: rgba(226,160,63,.1);
    --danger-light:  rgba(231,81,90,.1);
    --info-light:    rgba(33,150,243,.1);
    --purple-light:  rgba(128,93,202,.1);
}
body.light-mode                   { background: #f0f4f8 !important; color: #1a2332 !important; }
body.light-mode .app-sidebar      { background: #ffffff !important; border-color: #e5e7eb !important; box-shadow: -2px 0 12px rgba(0,0,0,.06) !important; }
body.light-mode .app-header,
body.light-mode header.app-header { background: #ffffff !important; border-bottom-color: #e5e7eb !important; box-shadow: 0 2px 10px rgba(0,0,0,.07) !important; }
body.light-mode .sidebar-section-title { color: #9ca3af !important; }
body.light-mode .sidebar-menu a   { color: #6b7280 !important; }
body.light-mode .sidebar-menu a:hover { background: rgba(67,97,238,.08) !important; color: #4361ee !important; }
body.light-mode .sidebar-menu a.active { background: #4361ee !important; color: #fff !important; }
body.light-mode .sidebar-menu a.logout-link { color: #e7515a !important; }
body.light-mode .sidebar-menu a.logout-link:hover { background: rgba(231,81,90,.08) !important; }
body.light-mode .user-avatar-wrap,
body.light-mode .profile-mini     { border-color: #e5e7eb !important; }
body.light-mode .profile-mini .pname  { color: #1a2332 !important; }
body.light-mode .profile-mini .pemail { color: #6b7280 !important; }

/* Cards & Content */
body.light-mode .xato-card,
body.light-mode .stat-card,
body.light-mode .nx-card,
body.light-mode [class*="card"]   { background: #ffffff !important; border-color: #e5e7eb !important; }
body.light-mode .header-logo span,
body.light-mode .logo-text        { color: #1a2332 !important; }
body.light-mode .header-toggle,
body.light-mode .hdr-toggle       { color: #6b7280 !important; }
body.light-mode .header-user .user-name { color: #1a2332 !important; }
body.light-mode .header-user .user-role { color: #4361ee !important; }
body.light-mode ::-webkit-scrollbar-track { background: #f1f5f9 !important; }
body.light-mode ::-webkit-scrollbar-thumb { background: #d1d5db !important; }

/* Tables */
body.light-mode table thead th    { background: #f1f5f9 !important; color: #374151 !important; border-color: #e5e7eb !important; }
body.light-mode table tbody td    { color: #374151 !important; border-color: #f1f5f9 !important; }
body.light-mode table tbody tr:hover { background: #f8fafc !important; }

/* Smooth transition */
.app-sidebar, .app-header, header.app-header,
.sidebar-menu a, [class*="card"], body {
    transition: background .25s ease, border-color .25s ease, color .2s ease, box-shadow .25s ease !important;
}
</style>
</head>
<body>

<?php
$admin_user = getCurrentUser();
?>

<!-- HEADER -->
<header class="app-header">
    <a href="/local_services/dashboard.php" class="header-logo">
        <div class="logo-icon"><i class="las la-shield-alt"></i></div>
        <span>لوحة المدير</span>
    </a>
    <button class="header-toggle" id="sidebarToggle"><i class="las la-bars"></i></button>
    <div style="flex:1;"></div>
    <a href="/local_services/services.php" class="header-icon-btn" title="الموقع">
        <i class="las la-external-link-alt"></i>
    </a>
    <a href="/local_services/profile.php" class="header-user">
        <div class="user-avatar"><?php echo mb_substr($admin_user['full_name'] ?? 'A', 0, 1); ?></div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($admin_user['full_name'] ?? 'مدير'); ?></span>
            <span class="user-role">مدير النظام</span>
        </div>
    </a>
    <button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
            style="width:36px;height:36px;background:var(--card-bg,var(--card,#0e1726));
                   border:1px solid var(--card-border,#1b2e4b);border-radius:8px;
                   display:flex;align-items:center;justify-content:center;
                   color:var(--text-muted,#888ea8);font-size:17px;cursor:pointer;
                   transition:all .2s;flex-shrink:0;">
        <i class="las la-sun" id="themeIcon"></i>
    </button>
</header>

<!-- SIDEBAR -->
<?php include_once '../includes/admin_sidebar.php'; ?>

<!-- MAIN -->
<main class="app-main" id="appMain">

    <div class="page-title-area">
        <div class="page-title">
            <h2><i class="las la-clipboard-list" style="color:var(--primary);margin-left:8px;"></i>إدارة الطلبات</h2>
            <ul class="breadcrumb-custom">
                <li><a href="/local_services/dashboard.php">لوحة التحكم</a></li>
                <li class="active">الطلبات</li>
            </ul>
        </div>
    </div>

    <?php if (isset($err_msg)): ?>
    <div class="xato-alert xato-alert-danger"><i class="las la-exclamation-circle"></i> خطأ في قاعدة البيانات: <?php echo htmlspecialchars($err_msg); ?></div>
    <?php endif; ?>

    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="las la-clipboard-list"></i></div>
            <div><div class="stat-value"><?php echo number_format($stats_row['total'] ?? 0); ?></div><div class="stat-label">إجمالي الطلبات</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="las la-clock"></i></div>
            <div><div class="stat-value"><?php echo number_format($stats_row['pending'] ?? 0); ?></div><div class="stat-label">معلقة</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="las la-play-circle"></i></div>
            <div><div class="stat-value"><?php echo number_format($stats_row['in_progress'] ?? 0); ?></div><div class="stat-label">قيد التنفيذ</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="las la-check-circle"></i></div>
            <div><div class="stat-value"><?php echo number_format($stats_row['completed'] ?? 0); ?></div><div class="stat-label">مكتملة</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger"><i class="las la-times-circle"></i></div>
            <div><div class="stat-value"><?php echo number_format($stats_row['cancelled'] ?? 0); ?></div><div class="stat-label">ملغية</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="las la-dollar-sign"></i></div>
            <div><div class="stat-value" style="font-size:18px;"><?php echo number_format((float)($stats_row['revenue'] ?? 0), 0); ?></div><div class="stat-label">الإيرادات (ر.س)</div></div>
        </div>
    </div>

    <!-- ORDERS TABLE -->
    <div class="xato-card">
        <div class="xato-card-header">
            <h5><i class="las la-list" style="color:var(--primary);margin-left:6px;"></i> قائمة الطلبات
                <span style="font-size:12px;color:var(--text-muted);font-weight:400;margin-right:8px;">(<?php echo count($orders); ?> طلب)</span>
            </h5>
        </div>

        <!-- FILTER -->
        <form method="GET">
            <div class="filter-bar">
                <div class="filter-group filter-search-wrap" style="flex:2;min-width:200px;">
                    <label>بحث</label>
                    <div style="position:relative;">
                        <input type="text" name="q" class="filter-input"
                               placeholder="اسم العميل أو المزود أو الخدمة..."
                               value="<?php echo htmlspecialchars($filter_q); ?>"
                               style="padding-right:34px;">
                        <i class="las la-search" style="position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    </div>
                </div>
                <div class="filter-group">
                    <label>الحالة</label>
                    <select name="status" class="filter-input">
                        <option value="">جميع الحالات</option>
                        <option value="pending"     <?php echo $filter_status==='pending'     ?'selected':''; ?>>معلق</option>
                        <option value="processing"  <?php echo $filter_status==='processing'  ?'selected':''; ?>>قيد المعالجة</option>
                        <option value="in_progress" <?php echo $filter_status==='in_progress' ?'selected':''; ?>>قيد التنفيذ</option>
                        <option value="completed"   <?php echo $filter_status==='completed'   ?'selected':''; ?>>مكتمل</option>
                        <option value="cancelled"   <?php echo $filter_status==='cancelled'   ?'selected':''; ?>>ملغي</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter"><i class="las la-filter"></i> فلتر</button>
                <?php if ($filter_q || $filter_status): ?>
                <a href="manage_orders.php" class="btn-reset"><i class="las la-times"></i> إلغاء</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="las la-clipboard-list"></i></div>
            <h4>لا توجد طلبات مطابقة</h4>
            <p>جرب تغيير معايير البحث</p>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="xato-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العميل</th>
                        <th>مزود الخدمة</th>
                        <th>الخدمة</th>
                        <th>التصنيف</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $sc = order_status_css($o['status']);
                ?>
                <tr>
                    <td><span class="order-id">#<?php echo $o['id']; ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:7px;">
                            <div style="width:28px;height:28px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--primary);font-weight:700;flex-shrink:0;">
                                <?php echo mb_substr($o['client_name'],0,1); ?>
                            </div>
                            <span><?php echo htmlspecialchars($o['client_name']); ?></span>
                        </div>
                    </td>
                    <td style="color:var(--primary);font-weight:600;"><?php echo htmlspecialchars($o['provider_name']); ?></td>
                    <td style="font-weight:600;color:var(--text-primary);max-width:150px;"><?php echo htmlspecialchars($o['service_title']); ?></td>
                    <td><span style="font-size:12px;color:var(--text-muted);"><?php echo htmlspecialchars($o['category_name'] ?? '—'); ?></span></td>
                    <td class="amount-cell"><?php echo number_format((float)$o['amount'], 2); ?> ر.س</td>
                    <td><span class="status-pill pill-<?php echo $sc; ?>"><?php echo get_status_display($o['status']); ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?php echo date('Y/m/d H:i', strtotime($o['created_at'])); ?></td>
                    <td>
                        <a href="/local_services/admin/view_order.php?id=<?php echo $o['id']; ?>"
                           class="btn-xato btn-xato-primary">
                            <i class="las la-eye"></i> عرض
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
    const sidebar  = document.getElementById('appSidebar');
    const mainArea = document.getElementById('appMain');
    const toggle   = document.getElementById('sidebarToggle');
    let isMobile   = window.innerWidth <= 768;
    toggle.addEventListener('click', () => {
        if (isMobile) sidebar.classList.toggle('mobile-open');
        else { sidebar.classList.toggle('collapsed'); mainArea.classList.toggle('expanded'); }
    });
    window.addEventListener('resize', () => { isMobile = window.innerWidth <= 768; });
</script>

<script>
/* ══ Theme Toggle ══ */
function toggleTheme(){
    var isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('xato_theme', isLight ? 'light' : 'dark');
    _updateThemeIcon(isLight);
}
function _updateThemeIcon(isLight){
    var ic = document.getElementById('themeIcon');
    if(ic) ic.className = isLight ? 'las la-moon' : 'las la-sun';
}
(function(){
    var saved = localStorage.getItem('xato_theme');
    if(saved === 'light'){
        document.body.classList.add('light-mode');
        document.addEventListener('DOMContentLoaded', function(){ _updateThemeIcon(true); });
    }
})();
</script>
</body>
</html>