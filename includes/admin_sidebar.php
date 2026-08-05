<?php
// includes/admin_sidebar.php
// ============================================================
//  الاستخدام: include_once '../includes/admin_sidebar.php';
//  يجب أن يكون المتغير $current_page معرفاً قبل الـ include:
//  $current_page = 'manage_orders'; // أو 'dashboard' أو ...
// ============================================================

// جلب بيانات المدير إذا لم تكن موجودة مسبقاً
if (!isset($admin_user) && isset($pdo)) {
    try {
        $sid = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;
        if ($sid) {
            $s_stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1");
            $s_stmt->execute([$sid]);
            $admin_user = $s_stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $admin_user = ['full_name' => 'مدير', 'email' => ''];
    }
}

$admin_name  = htmlspecialchars($admin_user['full_name'] ?? 'مدير');
$admin_email = htmlspecialchars($admin_user['email'] ?? '');
$admin_init  = mb_substr($admin_user['full_name'] ?? 'م', 0, 1);

// تعريف مجموعات القائمة
$sidebar_groups = [
    'main' => [
        'label' => 'الرئيسية',
        'icon'  => 'las la-home',
        'items' => [
            ['key' => 'dashboard',   'href' => '/local_services/dashboard.php',       'icon' => 'las la-tachometer-alt', 'label' => 'لوحة التحكم'],
            ['key' => 'site_home',   'href' => '/local_services/index.php',            'icon' => 'las la-globe',          'label' => 'الموقع الرئيسي'],
        ],
    ],
    'orders' => [
        'label' => 'الطلبات',
        'icon'  => 'las la-clipboard-list',
        'items' => [
            ['key' => 'manage_orders', 'href' => '/local_services/admin/manage_orders.php', 'icon' => 'las la-list-alt',    'label' => 'جميع الطلبات'],
            ['key' => 'view_order',    'href' => '/local_services/admin/manage_orders.php', 'icon' => 'las la-eye',         'label' => 'عرض طلب'],
        ],
    ],
    'services' => [
        'label' => 'الخدمات',
        'icon'  => 'las la-tools',
        'items' => [
            ['key' => 'manage_services', 'href' => '/local_services/admin/manage_services.php',        'icon' => 'las la-concierge-bell', 'label' => 'إدارة الخدمات'],
            ['key' => 'add_service',     'href' => '/local_services/admin/add_service.php',            'icon' => 'las la-plus-circle',    'label' => 'إضافة خدمة'],
            ['key' => 'edit_service',    'href' => '/local_services/admin/manage_services.php',        'icon' => 'las la-edit',           'label' => 'تعديل خدمة'],
        ],
    ],
    'users' => [
        'label' => 'المستخدمون',
        'icon'  => 'las la-users-cog',
        'items' => [
            ['key' => 'manage_users', 'href' => '/local_services/admin/manage_users.php',   'icon' => 'las la-users',     'label' => 'إدارة المستخدمين'],
            ['key' => 'add_user',     'href' => '/local_services/admin/add_user.php',       'icon' => 'las la-user-plus', 'label' => 'إضافة مستخدم'],
            ['key' => 'edit_user',    'href' => '/local_services/admin/manage_users.php',   'icon' => 'las la-user-edit', 'label' => 'تعديل مستخدم'],
        ],
    ],
    'categories' => [
        'label' => 'التصنيفات',
        'icon'  => 'las la-tags',
        'items' => [
            ['key' => 'manage_categories', 'href' => '/local_services/admin/manage_categories.php', 'icon' => 'las la-th-large', 'label' => 'إدارة التصنيفات'],
            ['key' => 'edit_category',     'href' => '/local_services/admin/manage_categories.php', 'icon' => 'las la-tag',      'label' => 'تعديل تصنيف'],
        ],
    ],
    'reports' => [
        'label' => 'التقارير',
        'icon'  => 'las la-chart-bar',
        'items' => [
            ['key' => 'financial_report', 'href' => '/local_services/admin/financial_report.php', 'icon' => 'las la-file-invoice-dollar', 'label' => 'التقرير المالي'],
        ],
    ],
    'system' => [
        'label' => 'النظام',
        'icon'  => 'las la-cog',
        'items' => [
            ['key' => 'settings', 'href' => '/local_services/admin/settings.php',    'icon' => 'las la-sliders-h',  'label' => 'الإعدادات'],
            ['key' => 'profile',  'href' => '/local_services/profile.php',           'icon' => 'las la-user-circle','label' => 'الملف الشخصي'],
            ['key' => 'logout',   'href' => '/local_services/logout.php',            'icon' => 'las la-sign-out-alt','label' => 'خروج آمن', 'danger' => true],
        ],
    ],
];

// تحديد أي مجموعة تحتوي على الصفحة الحالية → تفتح تلقائياً
$active_group = null;
foreach ($sidebar_groups as $group_key => $group) {
    foreach ($group['items'] as $item) {
        if ($item['key'] === ($current_page ?? '')) {
            $active_group = $group_key;
            break 2;
        }
    }
}
// إذا ما عُرِّف $current_page، افتح "main" افتراضياً
if ($active_group === null) $active_group = 'main';
?>

<!-- ══════════════════════════════════════
     ADMIN SIDEBAR  –  admin_sidebar.php
══════════════════════════════════════ -->
<style>
/* ─── Sidebar Shell ─── */
.adm-sidebar {
    position: fixed;
    top: var(--header-height, 64px);
    right: 0;
    width: var(--sidebar-width, 255px);
    height: calc(100vh - var(--header-height, 64px));
    background: var(--sidebar-bg, #0e1726);
    border-left: 1px solid var(--card-border, #1b2e4b);
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1020;
    transition: transform .3s ease;
    display: flex;
    flex-direction: column;
}
.adm-sidebar.collapsed { transform: translateX(var(--sidebar-width, 255px)); }
.adm-sidebar.mobile-open { transform: translateX(0) !important; }

/* ─── Profile Block ─── */
.adm-profile {
    padding: 22px 16px 16px;
    border-bottom: 1px solid var(--card-border, #1b2e4b);
    flex-shrink: 0;
}
.adm-av {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 900; color: #fff;
    margin-bottom: 10px;
    box-shadow: 0 4px 18px rgba(59,130,246,.3);
}
.adm-name  { font-size: 14px; font-weight: 800; color: #f1f5f9; }
.adm-email { font-size: 11px; color: #475569; margin-top: 2px; word-break: break-all; }
.adm-badge {
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 8px; padding: 3px 9px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
    background: rgba(244,63,94,.12); color: #f43f5e;
    border: 1px solid rgba(244,63,94,.2);
}
.adm-edit-link {
    display: flex; align-items: center; gap: 6px;
    margin-top: 12px; padding: 7px 12px; border-radius: 8px;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
    text-decoration: none; font-size: 12px; font-weight: 600; color: #94a3b8;
    transition: all .2s;
}
.adm-edit-link:hover { background: rgba(59,130,246,.12); color: #3b82f6; text-decoration: none; }

/* ─── Accordion Groups ─── */
.adm-nav { padding: 10px 8px; flex: 1; }

.adm-group { margin-bottom: 4px; }

/* Group Header (clickable) */
.adm-group-hdr {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 10px; border-radius: 8px;
    cursor: pointer; user-select: none;
    color: #94a3b8; font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    transition: background .18s, color .18s;
    position: relative;
}
.adm-group-hdr:hover { background: rgba(255,255,255,.05); color: #f1f5f9; }
.adm-group-hdr.open  { color: #f1f5f9; }

.adm-group-hdr > i:first-child { font-size: 16px; min-width: 20px; }

.adm-group-label { flex: 1; }

/* Arrow icon */
.adm-arrow {
    font-size: 14px;
    transition: transform .25s cubic-bezier(.4,0,.2,1);
    color: #475569;
}
.adm-group-hdr.open .adm-arrow { transform: rotate(-90deg); color: #94a3b8; }

/* Active-group indicator dot */
.adm-group-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #3b82f6; flex-shrink: 0;
    display: none;
}
.adm-group-hdr.has-active .adm-group-dot { display: block; }

/* Items list */
.adm-items {
    overflow: hidden;
    max-height: 0;
    transition: max-height .3s cubic-bezier(.4,0,.2,1);
}
.adm-items.open { max-height: 500px; }

.adm-items ul { list-style: none; padding: 2px 0 4px 0; margin: 0; }

.adm-items ul li { margin-bottom: 1px; }

.adm-items ul a {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px 8px 24px; /* indent right */
    border-radius: 7px;
    color: #64748b; font-size: 13px; font-weight: 500;
    text-decoration: none;
    transition: all .18s;
    position: relative;
}
.adm-items ul a i { font-size: 15px; min-width: 18px; }

.adm-items ul a:hover { background: rgba(255,255,255,.05); color: #f1f5f9; text-decoration: none; }

.adm-items ul a.active {
    background: rgba(59,130,246,.12); color: #3b82f6;
    border: 1px solid rgba(59,130,246,.2); font-weight: 700;
}
.adm-items ul a.active::before {
    content: '';
    position: absolute; right: -8px; top: 50%; transform: translateY(-50%);
    width: 3px; height: 16px;
    background: #3b82f6; border-radius: 3px;
}

/* Danger link */
.adm-items ul a.danger { color: rgba(239,68,68,.7); }
.adm-items ul a.danger:hover { background: rgba(239,68,68,.1); color: #ef4444; }

/* Connector line between items */
.adm-items ul { border-right: 1px solid rgba(255,255,255,.06); margin-right: 18px; }

/* ─── Footer ─── */
.adm-footer {
    padding: 14px 16px; border-top: 1px solid rgba(255,255,255,.06);
    font-size: 11px; color: #334155; flex-shrink: 0;
}
</style>
<!-- زر الثيم في أسفل السايدبار -->
<div style="padding: 14px 10px; border-top: 1px solid var(--border); margin-top: auto;">
    <button onclick="toggleTheme()" 
            style="width:100%; padding:10px; background:var(--dark); border:1px solid var(--border); 
                   border-radius:8px; color:var(--muted); font-family:'Tajawal',sans-serif; 
                   font-size:13px; font-weight:700; cursor:pointer; display:flex; 
                   align-items:center; justify-content:center; gap:8px; transition:all .2s;"
            id="themeToggle">
        <i class="las la-sun" id="themeIcon"></i>
        <span id="themeLabel">الوضع المضيء</span>
    </button>
</div>

<aside class="adm-sidebar" id="appSidebar">

    <!-- Profile -->
    <div class="adm-profile">
        <div class="adm-av"><?= $admin_init ?></div>
        <div class="adm-name"><?= $admin_name ?></div>
        <div class="adm-email"><?= $admin_email ?></div>
        <div class="adm-badge"><i class="las la-shield-alt"></i> مدير النظام</div>
        <a href="/local_services/profile.php" class="adm-edit-link">
            <i class="las la-pen"></i> تعديل الملف الشخصي
        </a>
    </div>

    <!-- Accordion Navigation -->
    <nav class="adm-nav" id="admNav">
        <?php foreach ($sidebar_groups as $group_key => $group):
            $is_active_group = ($group_key === $active_group);
            $has_active_item = false;
            foreach ($group['items'] as $item) {
                if ($item['key'] === ($current_page ?? '')) { $has_active_item = true; break; }
            }
        ?>
        <div class="adm-group">
            <!-- Group Header -->
            <div class="adm-group-hdr <?= $is_active_group ? 'open' : '' ?> <?= $has_active_item ? 'has-active' : '' ?>"
                 data-group="<?= $group_key ?>">
                <i class="<?= $group['icon'] ?>"></i>
                <span class="adm-group-label"><?= $group['label'] ?></span>
                <span class="adm-group-dot"></span>
                <i class="las la-angle-left adm-arrow"></i>
            </div>

            <!-- Items -->
            <div class="adm-items <?= $is_active_group ? 'open' : '' ?>" id="grp-<?= $group_key ?>">
                <ul>
                    <?php foreach ($group['items'] as $item):
                        $is_active_item = ($item['key'] === ($current_page ?? ''));
                        $danger_class   = ($item['danger'] ?? false) ? ' danger' : '';
                        $active_class   = $is_active_item ? ' active' : '';
                    ?>
                    <li>
                        <a href="<?= $item['href'] ?>"
                           class="<?= trim($active_class . $danger_class) ?>">
                            <i class="<?= $item['icon'] ?>"></i>
                            <?= $item['label'] ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="adm-footer">
        خدماتي &copy; <?= date('Y') ?> — جميع الحقوق محفوظة
    </div>
</aside>

<script>
(function () {
    // Accordion logic
    document.querySelectorAll('.adm-group-hdr').forEach(function (hdr) {
        hdr.addEventListener('click', function () {
            var key   = this.dataset.group;
            var items = document.getElementById('grp-' + key);
            var isOpen = items.classList.contains('open');

            // Close all
            document.querySelectorAll('.adm-items').forEach(function (el) { el.classList.remove('open'); });
            document.querySelectorAll('.adm-group-hdr').forEach(function (el) { el.classList.remove('open'); });

            // Open clicked (toggle)
            if (!isOpen) {
                items.classList.add('open');
                this.classList.add('open');
            }
        });
    });

    // Sidebar collapse toggle (desktop / mobile)
    var sidebar = document.getElementById('appSidebar');
    var mainEl  = document.getElementById('appMain');
    var toggleBtn = document.getElementById('sidebarToggle');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            var isMobile = window.innerWidth <= 900;
            if (isMobile) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
                if (mainEl) mainEl.classList.toggle('expanded');
            }
        });

        // Close on outside click (mobile)
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 900 &&
                sidebar.classList.contains('mobile-open') &&
                !sidebar.contains(e.target) &&
                !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }
})();
</script>
<!-- Theme Toggle - يُضاف مرة وحدة هون -->
<link rel="stylesheet" href="/local_services/assets/css/theme.css">
<script src="/local_services/assets/js/theme.js"></script>