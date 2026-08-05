<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
check_login('admin');
$current_page = 'manage_categories';

global $pdo;

$errors = []; $success = '';

// إضافة تصنيف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') { $errors[] = "اسم التصنيف مطلوب."; }
    else {
        try {
            $pdo->prepare("INSERT INTO categories (name, description) VALUES (?,?)")->execute([$name, $desc]);
            set_message("تم إضافة التصنيف بنجاح.", "success");
            header("Location: manage_categories.php"); exit;
        } catch (PDOException $e) { $errors[] = "خطأ: " . $e->getMessage(); }
    }
}

// تعديل تصنيف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id   = (int)$_POST['cat_id'];
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') { $errors[] = "اسم التصنيف مطلوب."; }
    else {
        $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")->execute([$name, $desc, $id]);
        set_message("تم تعديل التصنيف.", "success");
        header("Location: manage_categories.php"); exit;
    }
}

// حذف تصنيف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $id = (int)$_POST['cat_id'];
    try {
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        set_message("تم حذف التصنيف.", "danger");
        header("Location: manage_categories.php"); exit;
    } catch (PDOException $e) { $errors[] = "لا يمكن حذف التصنيف (قد يكون مرتبطاً بخدمات)."; }
}

try {
    $categories = $pdo->query("
        SELECT c.id, c.name, s.description,
               COUNT(s.id) AS services_count
        FROM categories c
        LEFT JOIN services s ON s.category_id = c.id
        GROUP BY c.id ORDER BY c.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; $errors[] = $e->getMessage(); }

$edit_cat = null;
if (isset($_GET['edit'])) {
    foreach ($categories as $c) { if ($c['id'] == $_GET['edit']) { $edit_cat = $c; break; } }
}

$admin_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>إدارة التصنيفات | المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
body>nav,body>header:not(.app-header),body>footer,.navbar,.navbar-default,.top-header,.site-header,nav.navbar,#header,#top-bar,.main-header,footer,.footer,.site-footer,#footer{display:none!important}
body{padding-top:0!important;margin-top:0!important}
:root{--dark-bg:#060818;--sidebar-bg:#0e1726;--card-bg:#0e1726;--card-border:#1b2e4b;--header-bg:#0e1726;--text-primary:#e0e6ed;--text-muted:#888ea8;--text-dark:#bfc9d4;--primary:#4361ee;--primary-light:rgba(67,97,238,.15);--success:#00ab55;--success-light:rgba(0,171,85,.15);--warning:#e2a03f;--warning-light:rgba(226,160,63,.15);--danger:#e7515a;--danger-light:rgba(231,81,90,.15);--purple:#805dca;--purple-light:rgba(128,93,202,.15);--sidebar-width:255px;--header-height:70px;--radius:8px;--shadow:0 6px 10px rgba(0,0,0,.4)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Tajawal',sans-serif;background:var(--dark-bg);color:var(--text-primary);font-size:14px;direction:rtl}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--sidebar-bg)}::-webkit-scrollbar-thumb{background:#1b2e4b;border-radius:10px}
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
.app-main{margin-right:var(--sidebar-width);margin-top:var(--header-height);padding:25px;min-height:calc(100vh - var(--header-height));transition:margin-right .3s ease}
.app-main.expanded{margin-right:0}
.page-title-area{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;flex-wrap:wrap;gap:10px}
.page-title h2{font-size:22px;font-weight:800;color:var(--text-primary);margin:0}
.breadcrumb-custom{display:flex;align-items:center;gap:6px;list-style:none;padding:0;margin:5px 0 0}
.breadcrumb-custom li{font-size:12px;color:var(--text-muted)}
.breadcrumb-custom li a{color:var(--primary);text-decoration:none}
.breadcrumb-custom li:not(:last-child)::after{content:'/';margin-right:6px;color:var(--card-border)}
/* 2-column layout */
.two-col{display:grid;grid-template-columns:340px 1fr;gap:22px;align-items:start}
.xato-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden;margin-bottom:22px}
.xato-card:last-child{margin-bottom:0}
.xato-card-header{padding:15px 20px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:10px}
.xato-card-header h5{font-size:15px;font-weight:700;color:var(--text-primary);margin:0}
.xato-card-body{padding:20px}
/* Form */
.f-group{margin-bottom:16px}
.f-label{display:block;font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.f-input{width:100%;background:var(--dark-bg);border:1px solid var(--card-border);color:var(--text-primary);border-radius:6px;padding:10px 13px;font-family:'Tajawal',sans-serif;font-size:14px;outline:none;transition:border-color .2s}
.f-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
.f-input::placeholder{color:#3a4a6b}
.btn-submit{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:11px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius);font-family:'Tajawal',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all .2s}
.btn-submit:hover{background:#3a56d4;transform:translateY(-1px)}
.btn-submit.edit-mode{background:var(--warning)}
.btn-submit.edit-mode:hover{background:#c88a2a}
.btn-cancel{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:10px;background:transparent;border:1px solid var(--card-border);color:var(--text-muted);border-radius:var(--radius);font-family:'Tajawal',sans-serif;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:8px;transition:all .2s}
.btn-cancel:hover{border-color:var(--danger);color:var(--danger);text-decoration:none}
/* Table */
.xato-table{width:100%;border-collapse:collapse}
.xato-table thead th{background:rgba(27,46,75,.5);color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:11px 14px;border-bottom:1px solid var(--card-border);white-space:nowrap}
.xato-table tbody tr{border-bottom:1px solid rgba(27,46,75,.5);transition:background .15s}
.xato-table tbody tr:hover{background:rgba(27,46,75,.4)}
.xato-table tbody td{padding:12px 14px;color:var(--text-dark);font-size:13px;vertical-align:middle}
.xato-table tbody tr:last-child{border-bottom:none}
.cat-id{font-weight:700;color:var(--primary)}
.count-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:var(--primary-light);color:var(--primary)}
.btn-xato{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:700;font-family:'Tajawal',sans-serif;text-decoration:none;border:none;cursor:pointer;transition:all .2s;white-space:nowrap}
.btn-xato-warning{background:var(--warning-light);color:var(--warning)}
.btn-xato-warning:hover{background:var(--warning);color:#fff;text-decoration:none}
.btn-xato-danger{background:var(--danger-light);color:var(--danger)}
.btn-xato-danger:hover{background:var(--danger);color:#fff;text-decoration:none}
.xato-alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:500}
.xato-alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid rgba(231,81,90,.25)}
@media(max-width:900px){.two-col{grid-template-columns:1fr}}
@media(max-width:768px){.app-sidebar{transform:translateX(var(--sidebar-width))}.app-sidebar.mobile-open{transform:translateX(0)}.app-main{margin-right:0;padding:14px}.user-info{display:none}}

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

<header class="app-header">
    <a href="/local_services/dashboard.php" class="header-logo">
        <div class="logo-icon"><i class="las la-shield-alt"></i></div>
        <span>لوحة المدير</span>
    </a>
    <button class="header-toggle" id="sidebarToggle"><i class="las la-bars"></i></button>
    <div style="flex:1;"></div>
    <a href="/local_services/profile.php" class="header-user">
        <div class="user-avatar"><?php echo mb_substr($admin_user['full_name']??'A',0,1); ?></div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($admin_user['full_name']??'مدير'); ?></span>
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

<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="app-main" id="appMain">
    <div class="page-title-area">
        <div class="page-title">
            <h2><i class="las la-tags" style="color:var(--success);margin-left:8px;"></i>إدارة التصنيفات</h2>
            <ul class="breadcrumb-custom">
                <li><a href="/local_services/dashboard.php">لوحة التحكم</a></li>
                <li class="active">التصنيفات</li>
            </ul>
        </div>
    </div>

    <?php if (function_exists('display_message')) display_message(); ?>

    <div class="two-col">

        <!-- FORM -->
        <div>
            <div class="xato-card">
                <div class="xato-card-header">
                    <i class="las la-<?php echo $edit_cat?'edit':'plus-circle'; ?>" style="color:var(--<?php echo $edit_cat?'warning':'success'; ?>);font-size:18px;"></i>
                    <h5><?php echo $edit_cat ? 'تعديل التصنيف' : 'إضافة تصنيف جديد'; ?></h5>
                </div>
                <div class="xato-card-body">
                    <?php foreach ($errors as $e): ?>
                    <div class="xato-alert xato-alert-danger"><i class="las la-exclamation-circle"></i> <?php echo htmlspecialchars($e); ?></div>
                    <?php endforeach; ?>

                    <form method="POST">
                        <?php if ($edit_cat): ?>
                        <input type="hidden" name="cat_id" value="<?php echo $edit_cat['id']; ?>">
                        <?php endif; ?>
                        <div class="f-group">
                            <label class="f-label">اسم التصنيف *</label>
                            <input type="text" name="name" class="f-input"
                                   placeholder="مثال: سباكة، كهرباء..."
                                   value="<?php echo htmlspecialchars($edit_cat['name']??$_POST['name']??''); ?>" required>
                        </div>
                        <div class="f-group">
                            <label class="f-label">وصف التصنيف</label>
                            <textarea name="description" rows="3" class="f-input"
                                      placeholder="وصف مختصر للتصنيف..."><?php echo htmlspecialchars($edit_cat['description']??$_POST['description']??''); ?></textarea>
                        </div>
                        <button type="submit" name="<?php echo $edit_cat?'edit_category':'add_category'; ?>"
                                class="btn-submit <?php echo $edit_cat?'edit-mode':''; ?>">
                            <i class="las la-<?php echo $edit_cat?'save':'plus'; ?>"></i>
                            <?php echo $edit_cat?'حفظ التعديلات':'إضافة التصنيف'; ?>
                        </button>
                        <?php if ($edit_cat): ?>
                        <a href="manage_categories.php" class="btn-cancel"><i class="las la-times"></i> إلغاء</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- LIST -->
        <div>
            <div class="xato-card">
                <div class="xato-card-header">
                    <i class="las la-list" style="color:var(--primary);font-size:18px;"></i>
                    <h5>التصنيفات الحالية <span style="font-size:12px;color:var(--text-muted);font-weight:400;">(<?php echo count($categories); ?>)</span></h5>
                </div>
                <div style="overflow-x:auto;">
                    <table class="xato-table">
                        <thead><tr><th>#</th><th>التصنيف</th><th>الوصف</th><th>الخدمات</th><th>إجراءات</th></tr></thead>
                        <tbody>
                        <?php if (empty($categories)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">لا توجد تصنيفات بعد</td></tr>
                        <?php else: ?>
                        <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><span class="cat-id">#<?php echo $c['id']; ?></span></td>
                            <td style="font-weight:700;color:var(--text-primary);"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td style="font-size:12px;color:var(--text-muted);max-width:160px;"><?php echo htmlspecialchars(mb_substr($c['description']??'',0,50)); ?><?php echo mb_strlen($c['description']??'')>50?'...':''; ?></td>
                            <td><span class="count-badge"><?php echo $c['services_count']; ?> خدمة</span></td>
                            <td>
                                <div style="display:flex;gap:5px;">
                                    <a href="?edit=<?php echo $c['id']; ?>" class="btn-xato btn-xato-warning"><i class="las la-edit"></i> تعديل</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('حذف التصنيف؟ سيؤثر على الخدمات المرتبطة به.')">
                                        <input type="hidden" name="cat_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" name="delete_category" class="btn-xato btn-xato-danger"><i class="las la-trash"></i> حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
const sidebar=document.getElementById('appSidebar'),mainArea=document.getElementById('appMain'),toggle=document.getElementById('sidebarToggle');
let isMobile=window.innerWidth<=768;
toggle.addEventListener('click',()=>{if(isMobile)sidebar.classList.toggle('mobile-open');else{sidebar.classList.toggle('collapsed');mainArea.classList.toggle('expanded');}});
window.addEventListener('resize',()=>{isMobile=window.innerWidth<=768;});
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
</body></html>