<?php
// edit_service.php - صفحة تعديل خدمة قائمة (خاصة بالمزودين)

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

check_login('provider');

global $pdo;
$user = current_user();
$user_id = $user['id'];
$errors = [];
$service = null;

$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$service_id) {
    set_message("خطأ: لم يتم تحديد معرف الخدمة المطلوب لتعديله.", "danger");
    header("Location: /local_services/dashboard.php");
    exit();
}

try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_message("فشل في جلب التصنيفات الأساسية للنظام.", "danger");
    $categories = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service || $service['provider_id'] !== $user_id) {
        set_message("لا تملك صلاحية تعديل هذه الخدمة أو أنها غير موجودة.", "danger");
        header("Location: /local_services/dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    set_message("خطأ في الاتصال بقاعدة البيانات.", "danger");
    header("Location: /local_services/dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = sanitize_input($_POST['category_id'] ?? '');
    $price       = sanitize_input($_POST['price'] ?? '');
    $city        = sanitize_input($_POST['city'] ?? '');
    $csrf_token  = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf_token)) {
        $errors[] = "خطأ في أمان النموذج (CSRF). حاول مرة أخرى.";
    }

    if (empty($title))       $errors[] = "عنوان الخدمة مطلوب.";
    if (empty($description)) $errors[] = "وصف الخدمة مطلوب.";
    if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id <= 0) $errors[] = "التصنيف المحدد غير صالح.";
    if (!is_numeric($price) || $price <= 0) $errors[] = "السعر يجب أن يكون رقماً موجباً.";
    if (empty($city))        $errors[] = "المدينة مطلوبة.";

    $image_file_name   = $service['image'];
    $delete_current_image = isset($_POST['delete_image']) && $_POST['delete_image'] === 'on';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        try {
            $image_file_name = upload_file($_FILES['image'], 'uploads');
        } catch (Exception $e) {
            $errors[] = "فشل في رفع الصورة الجديدة: " . $e->getMessage();
        }
    } elseif ($delete_current_image) {
        $image_file_name = null;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE services SET 
                                   category_id = ?, title = ?, description = ?, price = ?, city = ?, image = ?
                                   WHERE id = ? AND provider_id = ?");

            $stmt->execute([
                $category_id, $title, $description, $price, $city,
                $image_file_name, $service_id, $user_id
            ]);

            $pdo->commit();
            set_message("تم تحديث الخدمة '{$title}' بنجاح.", "success");

            $service['title']       = $title;
            $service['description'] = $description;
            $service['category_id'] = $category_id;
            $service['price']       = $price;
            $service['city']        = $city;
            $service['image']       = $image_file_name;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "فشل في حفظ التعديلات: يرجى المحاولة لاحقاً.";
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الخدمة | خدماتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <style>
        /* ── إخفاء أي هيدر خارجي ── */
        body > nav, body > header:not(.app-header),
        .navbar, .navbar-default, .top-header, .site-header,
        nav.navbar, #header, #top-bar, .main-header { display: none !important; }

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
            --rose:       #f43f5e;

            --text:  #f1f5f9;
            --text2: #94a3b8;
            --text3: #475569;

            --sidebar:  260px;
            --header-h: 64px;
            --r:  10px;
            --r2: 14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            direction: rtl;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 99px; }

        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: .5;
        }

        /* ══ HEADER ══ */
        .app-header {
            position: fixed; top: 0; right: 0; left: 0; z-index: 200;
            height: var(--header-h);
            background: rgba(13,17,23,.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            padding: 0 20px; gap: 12px;
        }
        .logo-wrap { display:flex; align-items:center; gap:10px; text-decoration:none; min-width:220px; }
        .logo-mark {
            width:34px; height:34px; border-radius:9px;
            background: linear-gradient(135deg, var(--blue), var(--cyan));
            display:flex; align-items:center; justify-content:center;
            font-size:16px; color:#fff; font-weight:900;
            box-shadow: 0 0 18px var(--blue-glow);
        }
        .logo-text { font-size:17px; font-weight:900; color:var(--text); letter-spacing:-.3px; }
        .logo-text span { color:var(--blue); }
        .hdr-toggle {
            background:none; border:none; color:var(--text2); font-size:20px;
            cursor:pointer; padding:6px 8px; border-radius:7px;
            transition:background .2s,color .2s; line-height:1;
        }
        .hdr-toggle:hover { background:var(--surface); color:var(--text); }
        .hdr-spacer { flex:1; }
        .hdr-pill {
            display:flex; align-items:center; gap:8px;
            background:var(--surface); border:1px solid var(--border2);
            border-radius:99px; padding:5px 14px;
            font-size:12px; color:var(--text2); white-space:nowrap;
        }
        .hdr-pill i { color:var(--green); font-size:8px; }
        .hdr-btn {
            width:36px; height:36px; border-radius:9px;
            background:var(--surface); border:1px solid var(--border);
            display:flex; align-items:center; justify-content:center;
            color:var(--text2); font-size:17px; text-decoration:none;
            transition:all .2s; cursor:pointer;
        }
        .hdr-btn:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-dim); }
        .hdr-avatar {
            display:flex; align-items:center; gap:9px;
            padding:5px 10px 5px 14px; border-radius:99px;
            background:var(--surface); border:1px solid var(--border2);
            text-decoration:none; transition:border-color .2s;
        }
        .hdr-avatar:hover { border-color:var(--blue); }
        .avatar-ring {
            width:30px; height:30px; border-radius:50%;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display:flex; align-items:center; justify-content:center;
            font-size:13px; font-weight:800; color:#fff; flex-shrink:0;
        }
        .avatar-name { font-size:13px; font-weight:700; color:var(--text); line-height:1.1; }
        .avatar-role { font-size:11px; color:var(--text2); }

        /* ══ SIDEBAR ══ */
        .app-sidebar {
            position:fixed; top:var(--header-h); right:0;
            width:var(--sidebar); height:calc(100vh - var(--header-h));
            background:var(--bg2); border-left:1px solid var(--border);
            overflow-y:auto; z-index:150;
            transition:transform .3s cubic-bezier(.4,0,.2,1);
            display:flex; flex-direction:column;
        }
        .app-sidebar.collapsed { transform:translateX(var(--sidebar)); }
        .app-sidebar.mobile-open { transform:translateX(0) !important; }

        .sb-profile { padding:20px 16px 16px; border-bottom:1px solid var(--border); }
        .sb-av {
            width:54px; height:54px; border-radius:14px;
            background:linear-gradient(135deg,var(--blue),var(--purple));
            display:flex; align-items:center; justify-content:center;
            font-size:22px; font-weight:900; color:#fff; margin-bottom:10px;
            box-shadow:0 4px 20px rgba(59,130,246,.3);
        }
        .sb-name { font-size:14px; font-weight:800; color:var(--text); }
        .sb-email { font-size:11px; color:var(--text3); margin-top:2px; word-break:break-all; }
        .sb-badge {
            display:inline-flex; align-items:center; gap:4px; margin-top:8px;
            padding:3px 9px; border-radius:99px; font-size:11px; font-weight:700;
            background:var(--blue-dim); color:var(--blue); border:1px solid rgba(59,130,246,.2);
        }
        .sb-edit {
            display:flex; align-items:center; gap:6px; margin-top:12px;
            padding:7px 12px; border-radius:8px; background:var(--surface);
            border:1px solid var(--border); text-decoration:none;
            font-size:12px; font-weight:600; color:var(--text2); transition:all .2s;
        }
        .sb-edit:hover { background:var(--blue-dim); color:var(--blue); border-color:rgba(59,130,246,.3); }

        .sb-section { padding:16px 16px 4px; font-size:10px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:1px; }
        .sb-nav { list-style:none; padding:0 10px; }
        .sb-nav li { margin-bottom:2px; }
        .sb-nav a {
            display:flex; align-items:center; gap:9px; padding:9px 10px;
            border-radius:8px; color:var(--text2); text-decoration:none;
            font-size:13px; font-weight:600; transition:all .18s; position:relative;
        }
        .sb-nav a i { font-size:17px; min-width:20px; transition:transform .2s; }
        .sb-nav a:hover { background:var(--surface); color:var(--text); }
        .sb-nav a:hover i { transform:scale(1.1); }
        .sb-nav a.active { background:var(--blue-dim); color:var(--blue); border:1px solid rgba(59,130,246,.2); }
        .sb-nav a.active::before {
            content:''; position:absolute; right:-10px; top:50%; transform:translateY(-50%);
            width:3px; height:20px; background:var(--blue); border-radius:3px;
        }
        .sb-nav a.danger:hover { background:var(--red-dim); color:var(--red); }
        .sb-footer { margin-top:auto; padding:14px 16px; border-top:1px solid var(--border); font-size:11px; color:var(--text3); }

        /* ══ MAIN ══ */
        .app-main {
            margin-right:var(--sidebar); margin-top:var(--header-h);
            padding:28px 26px; min-height:calc(100vh - var(--header-h));
            transition:margin-right .3s cubic-bezier(.4,0,.2,1);
            position:relative; z-index:1;
        }
        .app-main.wide { margin-right:0; }

        /* ══ PAGE HEADER ══ */
        .pg-head {
            display:flex; align-items:flex-start; justify-content:space-between;
            margin-bottom:28px; gap:12px; flex-wrap:wrap;
        }
        .pg-title { font-size:24px; font-weight:900; color:var(--text); letter-spacing:-.5px; line-height:1.1; }
        .pg-sub { font-size:13px; color:var(--text2); margin-top:4px; }
        .pg-sub a { color:var(--blue); text-decoration:none; }
        .pg-sub a:hover { text-decoration:underline; }
        .pg-sub span::before { content:' / '; color:var(--text3); }

        /* ══ ALERTS ══ */
        .nx-alert {
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 18px; border-radius:var(--r);
            margin-bottom:22px; font-size:13px; font-weight:600; border:1px solid;
        }
        .nx-alert i { font-size:18px; flex-shrink:0; margin-top:1px; }
        .nx-alert.success { background:var(--green-dim); color:var(--green); border-color:rgba(16,185,129,.25); }
        .nx-alert.danger  { background:var(--red-dim);   color:var(--red);   border-color:rgba(239,68,68,.25); }
        .nx-alert ul { margin:6px 0 0 0; padding-right:18px; font-weight:400; }
        .nx-alert ul li { margin-bottom:3px; }

        /* ══ CARD ══ */
        .nx-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--r2); overflow:hidden; margin-bottom:20px; }
        .nx-card-hdr {
            display:flex; align-items:center; justify-content:space-between;
            gap:10px; padding:16px 20px; border-bottom:1px solid var(--border);
        }
        .nx-card-hdr h5 {
            font-size:15px; font-weight:800; color:var(--text);
            display:flex; align-items:center; gap:8px; margin:0;
        }
        .nx-card-hdr h5 i { font-size:18px; }
        .nx-card-body { padding:20px; }

        /* ══ FORM ELEMENTS ══ */
        .form-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:18px;
        }
        .form-grid.full { grid-template-columns:1fr; }

        .form-group { display:flex; flex-direction:column; gap:7px; }
        .form-group label {
            font-size:12px; font-weight:700; color:var(--text2);
            text-transform:uppercase; letter-spacing:.5px;
            display:flex; align-items:center; gap:6px;
        }
        .form-group label i { font-size:14px; color:var(--blue); }
        .form-group label .req { color:var(--red); font-size:14px; line-height:1; }

        .form-control {
            background:var(--surface2); border:1px solid var(--border2);
            border-radius:9px; color:var(--text); font-family:'Tajawal',sans-serif;
            font-size:14px; padding:11px 14px; transition:border-color .2s, box-shadow .2s;
            outline:none; width:100%;
        }
        .form-control::placeholder { color:var(--text3); }
        .form-control:focus {
            border-color:var(--blue);
            box-shadow:0 0 0 3px var(--blue-dim);
        }
        select.form-control { cursor:pointer; }
        textarea.form-control { resize:vertical; min-height:110px; line-height:1.6; }

        /* price input with currency */
        .input-wrap { position:relative; }
        .input-wrap .form-control { padding-left:60px; }
        .input-suffix {
            position:absolute; left:0; top:0; bottom:0;
            width:52px; display:flex; align-items:center; justify-content:center;
            font-size:11px; font-weight:800; color:var(--green);
            background:var(--green-dim); border-radius:0 9px 9px 0;
            border-right:1px solid rgba(16,185,129,.2);
        }

        /* ══ IMAGE SECTION ══ */
        .img-section {
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--r2); padding:18px;
        }
        .img-section-title {
            font-size:12px; font-weight:700; color:var(--text2);
            text-transform:uppercase; letter-spacing:.5px;
            display:flex; align-items:center; gap:6px; margin-bottom:14px;
        }
        .img-section-title i { color:var(--amber); font-size:15px; }

        .current-img-wrap {
            display:flex; align-items:center; gap:16px;
            background:var(--bg2); border:1px solid var(--border);
            border-radius:var(--r); padding:14px; margin-bottom:14px;
        }
        .current-img-thumb {
            width:80px; height:72px; border-radius:8px; object-fit:cover;
            border:2px solid var(--border2); flex-shrink:0;
        }
        .current-img-info { flex:1; min-width:0; }
        .current-img-name {
            font-size:12px; font-weight:700; color:var(--text);
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:8px;
        }
        .delete-check-wrap {
            display:flex; align-items:center; gap:8px; cursor:pointer;
        }
        .delete-check-wrap input[type="checkbox"] { display:none; }
        .custom-check {
            width:16px; height:16px; border-radius:4px;
            border:2px solid var(--border2); background:var(--surface2);
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0; transition:all .2s;
        }
        .delete-check-wrap input:checked + .custom-check {
            background:var(--red-dim); border-color:var(--red);
        }
        .delete-check-wrap input:checked + .custom-check::after {
            content:'✓'; font-size:10px; color:var(--red); font-weight:900;
        }
        .delete-check-label { font-size:12px; font-weight:600; color:var(--text3); transition:color .2s; }
        .delete-check-wrap:hover .delete-check-label { color:var(--red); }

        .file-upload-zone {
            border:2px dashed var(--border2); border-radius:var(--r);
            padding:22px; text-align:center; cursor:pointer;
            transition:all .2s; position:relative; overflow:hidden;
        }
        .file-upload-zone:hover, .file-upload-zone.drag { border-color:var(--blue); background:var(--blue-dim); }
        .file-upload-zone input[type="file"] {
            position:absolute; inset:0; opacity:0; cursor:pointer; font-size:0;
        }
        .file-upload-icon { font-size:30px; color:var(--text3); margin-bottom:8px; transition:color .2s; }
        .file-upload-zone:hover .file-upload-icon { color:var(--blue); }
        .file-upload-text { font-size:13px; color:var(--text2); font-weight:600; }
        .file-upload-sub { font-size:11px; color:var(--text3); margin-top:4px; }
        .file-preview-name { font-size:12px; color:var(--green); margin-top:8px; font-weight:700; display:none; }

        /* ══ DIVIDER ══ */
        .nx-divider { border:none; border-top:1px solid var(--border); margin:20px 0; }

        /* ══ BUTTONS ══ */
        .btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:10px 18px; border-radius:9px; border:none;
            font-size:13px; font-weight:700; font-family:'Tajawal',sans-serif;
            cursor:pointer; text-decoration:none; transition:all .18s;
            white-space:nowrap; line-height:1;
        }
        .btn-primary {
            background:var(--blue-dim); color:var(--blue);
            border:1px solid rgba(59,130,246,.25);
        }
        .btn-primary:hover {
            background:var(--blue); color:#fff;
            box-shadow:0 0 18px var(--blue-glow); text-decoration:none;
        }
        .btn-success {
            background:var(--green-dim); color:var(--green);
            border:1px solid rgba(16,185,129,.25);
        }
        .btn-success:hover { background:var(--green); color:#fff; text-decoration:none; }
        .btn-ghost {
            background:transparent; color:var(--text2);
            border:1px solid var(--border2);
        }
        .btn-ghost:hover { background:var(--surface2); color:var(--text); text-decoration:none; }
        .btn-lg { padding:12px 24px; font-size:14px; border-radius:10px; }

        /* ══ FORM ACTIONS ══ */
        .form-actions {
            display:flex; align-items:center; justify-content:flex-end;
            gap:10px; padding-top:20px; border-top:1px solid var(--border);
        }

        /* ══ ANIMATE ══ */
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(14px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .animate { animation:fadeUp .35s ease both; }
        .d1 { animation-delay:.05s; }
        .d2 { animation-delay:.10s; }
        .d3 { animation-delay:.15s; }

        /* ══ RESPONSIVE ══ */
        @media (max-width:900px) {
            .app-sidebar { transform:translateX(var(--sidebar)); }
            .app-main { margin-right:0 !important; padding:16px; }
            .form-grid { grid-template-columns:1fr; }
        }
        @media (max-width:600px) {
            .hdr-pill, .avatar-name, .avatar-role { display:none; }
            .form-actions { flex-direction:column-reverse; }
            .form-actions .btn { width:100%; justify-content:center; }
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
    <button class="hdr-toggle" id="sidebarToggle" aria-label="تبديل القائمة">
        <i class="las la-bars"></i>
    </button>
    <div class="hdr-spacer"></div>

    <div class="hdr-pill">
        <i class="las la-circle" style="font-size:8px;"></i>
        وضع المزود
    </div>

    <a href="/local_services/provider/add_service.php" class="hdr-btn" title="إضافة خدمة">
        <i class="las la-plus"></i>
    </a>
    <a href="/local_services/notifications.php" class="hdr-btn" title="الإشعارات">
        <i class="las la-bell"></i>
    </a>
    <a href="/local_services/profile.php" class="hdr-avatar">
        <div>
            <div class="avatar-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="avatar-role">مزود خدمة</div>
        </div>
        <div class="avatar-ring"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
    </a>
</header>

<!-- ══ SIDEBAR ══ -->
<aside class="app-sidebar" id="appSidebar">
    <div class="sb-profile">
        <div class="sb-av"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
        <div class="sb-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
        <div class="sb-email"><?php echo htmlspecialchars($user['email']); ?></div>
        <div class="sb-badge"><i class="las la-user-tie"></i> مزود خدمة</div>
        <a href="/local_services/profile.php" class="sb-edit">
            <i class="las la-pen"></i> تعديل الملف الشخصي
        </a>
    </div>

    <div class="sb-section">القائمة الرئيسية</div>
    <ul class="sb-nav">
        <li><a href="/local_services/index.php"><i class="las la-home"></i> الرئيسية</a></li>
        <li><a href="/local_services/services.php"><i class="las la-concierge-bell"></i> تصفح الخدمات</a></li>
        <li><a href="/local_services/dashboard.php"><i class="las la-tachometer-alt"></i> لوحة التحكم</a></li>
        <li>
            <a href="/local_services/provider/add_service.php">
                <i class="las la-plus-circle"></i> إضافة خدمة
            </a>
        </li>
        <li><a href="/local_services/profile.php"><i class="las la-user-circle"></i> الملف الشخصي</a></li>
    </ul>

    <div class="sb-section">الإعدادات</div>
    <ul class="sb-nav">
        <li><a href="/local_services/profile.php#password"><i class="las la-lock"></i> تغيير كلمة المرور</a></li>
        <li><a href="/local_services/logout.php" class="danger"><i class="las la-sign-out-alt"></i> خروج آمن</a></li>
    </ul>

    <div class="sb-footer">
        خدماتي &copy; <?php echo date('Y'); ?> — جميع الحقوق محفوظة
    </div>
</aside>

<!-- ══ MAIN ══ -->
<main class="app-main" id="appMain">

    <!-- Page Header -->
    <div class="pg-head animate">
        <div>
            <div class="pg-title">
                <i class="las la-edit" style="color:var(--amber);"></i>
                تعديل الخدمة
            </div>
            <div class="pg-sub">
                <a href="/local_services/index.php">الرئيسية</a>
                <span><a href="/local_services/dashboard.php">لوحة التحكم</a></span>
                <span><?php echo htmlspecialchars($service['title']); ?></span>
            </div>
        </div>
        <a href="/local_services/dashboard.php" class="btn btn-ghost">
            <i class="las la-arrow-right"></i> العودة للوحة التحكم
        </a>
    </div>

    <!-- Alerts -->
    <?php if (!empty($errors)): ?>
        <div class="nx-alert danger animate d1">
            <i class="las la-exclamation-circle"></i>
            <div>
                <div>يوجد أخطاء في البيانات المدخلة، يرجى مراجعتها:</div>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- Form Card -->
    <form method="post" action="edit_service.php?id=<?php echo $service_id; ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <!-- Section 1: Basic Info -->
        <div class="nx-card animate d1">
            <div class="nx-card-hdr">
                <h5><i class="las la-info-circle" style="color:var(--blue);"></i> المعلومات الأساسية</h5>
            </div>
            <div class="nx-card-body">
                <div class="form-grid full" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label for="title">
                            <i class="las la-heading"></i> عنوان الخدمة <span class="req">*</span>
                        </label>
                        <input type="text" id="title" name="title" class="form-control"
                               placeholder="أدخل عنواناً واضحاً ومميزاً لخدمتك…"
                               value="<?php echo htmlspecialchars($service['title'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-grid full">
                    <div class="form-group">
                        <label for="description">
                            <i class="las la-align-right"></i> وصف مفصّل للخدمة <span class="req">*</span>
                        </label>
                        <textarea id="description" name="description" class="form-control"
                                  placeholder="اشرح خدمتك بالتفصيل: ما الذي تقدمه؟ ما المميزات؟ ما الشروط؟…"
                                  required><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Details -->
        <div class="nx-card animate d2">
            <div class="nx-card-hdr">
                <h5><i class="las la-sliders-h" style="color:var(--cyan);"></i> تفاصيل الخدمة</h5>
            </div>
            <div class="nx-card-body">
                <div class="form-grid" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label for="category_id">
                            <i class="las la-tag"></i> التصنيف <span class="req">*</span>
                        </label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">— اختر تصنيفاً —</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($service['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="city">
                            <i class="las la-map-marker-alt"></i> المدينة <span class="req">*</span>
                        </label>
                        <input type="text" id="city" name="city" class="form-control"
                               placeholder="مثال: الرياض، جدة، الدمام…"
                               value="<?php echo htmlspecialchars($service['city'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-grid" style="grid-template-columns:1fr 2fr;">
                    <div class="form-group">
                        <label for="price">
                            <i class="las la-dollar-sign"></i> السعر <span class="req">*</span>
                        </label>
                        <div class="input-wrap">
                            <input type="number" id="price" name="price" class="form-control"
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?php echo htmlspecialchars($service['price'] ?? ''); ?>" required>
                            <span class="input-suffix">ر.س</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Image -->
        <div class="nx-card animate d3">
            <div class="nx-card-hdr">
                <h5><i class="las la-image" style="color:var(--amber);"></i> صورة الخدمة</h5>
            </div>
            <div class="nx-card-body">
                <div class="img-section">
                    <div class="img-section-title">
                        <i class="las la-camera"></i> إدارة الصورة الحالية
                    </div>

                    <?php if (!empty($service['image'])): 
                        $image_path = '/local_services/assets/uploads/' . htmlspecialchars($service['image']);
                    ?>
                        <div class="current-img-wrap">
                            <img src="<?php echo $image_path; ?>"
                                 alt="الصورة الحالية"
                                 class="current-img-thumb"
                                 onerror="this.style.display='none'">
                            <div class="current-img-info">
                                <div class="current-img-name">
                                    <i class="las la-file-image" style="color:var(--blue);margin-left:5px;"></i>
                                    <?php echo htmlspecialchars($service['image']); ?>
                                </div>
                                <label class="delete-check-wrap" for="delete_image">
                                    <input type="checkbox" id="delete_image" name="delete_image">
                                    <span class="custom-check"></span>
                                    <span class="delete-check-label">
                                        <i class="las la-trash-alt"></i> حذف الصورة الحالية
                                    </span>
                                </label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;
                                    background:var(--bg2);border:1px dashed var(--border2);
                                    border-radius:var(--r);margin-bottom:14px;">
                            <i class="las la-image" style="font-size:20px;color:var(--text3);"></i>
                            <span style="font-size:13px;color:var(--text3);">لا توجد صورة مرفقة حالياً</span>
                        </div>
                    <?php endif; ?>

                    <hr class="nx-divider">

                    <label style="font-size:12px;font-weight:700;color:var(--text2);text-transform:uppercase;
                                  letter-spacing:.5px;display:flex;align-items:center;gap:6px;margin-bottom:10px;">
                        <i class="las la-upload" style="color:var(--green);font-size:15px;"></i>
                        رفع صورة جديدة
                        <span style="font-size:11px;font-weight:400;color:var(--text3);text-transform:none;letter-spacing:0;">(اختياري)</span>
                    </label>

                    <div class="file-upload-zone" id="dropZone">
                        <input type="file" id="image_new" name="image" accept="image/jpeg,image/png,image/gif"
                               onchange="handleFileSelect(this)">
                        <div class="file-upload-icon"><i class="las la-cloud-upload-alt"></i></div>
                        <div class="file-upload-text">اسحب الصورة هنا أو اضغط للاختيار</div>
                        <div class="file-upload-sub">PNG, JPG, GIF — حجم أقصى 5MB</div>
                        <div class="file-preview-name" id="filePreviewName"></div>
                    </div>

                    <?php if (!empty($service['image'])): ?>
                        <p style="font-size:11px;color:var(--text3);margin-top:8px;">
                            <i class="las la-info-circle"></i>
                            عند رفع صورة جديدة ستحل محل الصورة الحالية تلقائياً.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions animate d3">
            <a href="/local_services/dashboard.php" class="btn btn-ghost">
                <i class="las la-times"></i> إلغاء
            </a>
            <a href="/local_services/service_detail.php?id=<?php echo $service_id; ?>"
               class="btn btn-ghost" target="_blank">
                <i class="las la-eye"></i> معاينة الخدمة
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="las la-save"></i> حفظ التعديلات
            </button>
        </div>

    </form>

</main>

<script>
(function () {
    /* sidebar toggle */
    const sidebar = document.getElementById('appSidebar');
    const main    = document.getElementById('appMain');
    const btn     = document.getElementById('sidebarToggle');
    let mobile    = window.innerWidth <= 900;

    btn.addEventListener('click', () => {
        if (mobile) {
            sidebar.classList.toggle('mobile-open');
        } else {
            const collapsed = sidebar.classList.toggle('collapsed');
            main.classList.toggle('wide', collapsed);
        }
    });

    document.addEventListener('click', (e) => {
        if (mobile && sidebar.classList.contains('mobile-open') &&
            !sidebar.contains(e.target) && !btn.contains(e.target)) {
            sidebar.classList.remove('mobile-open');
        }
    });

    window.addEventListener('resize', () => {
        mobile = window.innerWidth <= 900;
        if (!mobile) sidebar.classList.remove('mobile-open');
    });

    /* drag-and-drop styling */
    const zone = document.getElementById('dropZone');
    if (zone) {
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag');
            const input = zone.querySelector('input[type="file"]');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                handleFileSelect(input);
            }
        });
    }
})();

function handleFileSelect(input) {
    const preview = document.getElementById('filePreviewName');
    if (input.files && input.files[0]) {
        preview.textContent = '✓ تم اختيار: ' + input.files[0].name;
        preview.style.display = 'block';
    }
}
</script>
</body>
</html>