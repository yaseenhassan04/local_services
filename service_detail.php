<?php
// ============================================================
// service_detail.php — تفاصيل الخدمة
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
global $pdo;

$user    = getCurrentUser();
$user_id = $user['id'];

$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$service_id) {
    header("Location: /local_services/services.php");
    exit;
}

$csrf_token = generateCsrfToken();

try {
    $pdo->query("SELECT 1 FROM categories LIMIT 1");
    $has_categories = true;
} catch (PDOException $e) {
    $has_categories = false;
}

$sql = $has_categories
    ? "SELECT s.id, s.title, s.description, s.price, s.category_id,
              s.provider_id, s.created_at, s.image,
              u.full_name AS provider_name, u.email AS provider_email,
              u.phone AS provider_phone, u.city AS provider_city,
              c.name AS category_name
       FROM services s
       JOIN users u ON s.provider_id = u.id
       LEFT JOIN categories c ON s.category_id = c.id
       WHERE s.id = ? AND s.is_active = 1 LIMIT 1"
    : "SELECT s.id, s.title, s.description, s.price, s.category_id,
              s.provider_id, s.created_at, s.image,
              u.full_name AS provider_name, u.email AS provider_email,
              u.phone AS provider_phone, u.city AS provider_city,
              NULL AS category_name
       FROM services s
       JOIN users u ON s.provider_id = u.id
       WHERE s.id = ? AND s.is_active = 1 LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header("Location: /local_services/services.php");
    exit;
}

$ratings    = [];
$avg_rating = 0;
$total_rev  = 0;
try {
    $stmt_r = $pdo->prepare("
        SELECT r.rating, r.comment, r.created_at, u.full_name
        FROM reviews r JOIN users u ON r.client_id = u.id
        WHERE r.service_id = ? ORDER BY r.created_at DESC LIMIT 20
    ");
    $stmt_r->execute([$service_id]);
    $ratings   = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
    $total_rev = count($ratings);
    if ($total_rev > 0)
        $avg_rating = round(array_sum(array_column($ratings, 'rating')) / $total_rev, 1);
} catch (PDOException $e) {}

$already_ordered    = false;
$completed_order_id = null;
try {
    $stmt_chk = $pdo->prepare("SELECT id, status FROM orders WHERE service_id = ? AND client_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_chk->execute([$service_id, $user_id]);
    $prev = $stmt_chk->fetch(PDO::FETCH_ASSOC);
    if ($prev) {
        $already_ordered = true;
        if ($prev['status'] === 'completed') $completed_order_id = $prev['id'];
    }
} catch (PDOException $e) {}

$already_reviewed = false;
if ($completed_order_id) {
    try {
        $stmt_rv = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? LIMIT 1");
        $stmt_rv->execute([$completed_order_id]);
        $already_reviewed = (bool) $stmt_rv->fetchColumn();
    } catch (PDOException $e) {}
}

$is_own_service = ($service['provider_id'] == $user_id);
$service_img    = !empty($service['image'])
    ? '/local_services/assets/uploads/services/' . htmlspecialchars($service['image'])
    : null;
$user_initial   = mb_substr($user['full_name'], 0, 1, 'UTF-8');
$prov_initial   = mb_substr($service['provider_name'], 0, 1, 'UTF-8');
$bc_title       = mb_strlen($service['title']) > 45
    ? mb_substr($service['title'], 0, 42) . '…'
    : $service['title'];

function render_stars(float $rating, int $max = 5): string {
    $html = '<div class="stars-row">';
    for ($i = 1; $i <= $max; $i++) {
        $cls = $i <= round($rating) ? 'on' : 'off';
        $html .= "<i class=\"las la-star star-{$cls}\"></i>";
    }
    return $html . '</div>';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['title']); ?> | خدماتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <style>
        /* ── إخفاء أي هيدر خارجي ── */
        body > nav:not(.app-header),
        body > header:not(.app-header),
        .navbar, .navbar-default, .top-header,
        .site-header, nav.navbar, #header, #top-bar, .main-header { display:none !important; }

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

        ::-webkit-scrollbar { width:4px; height:4px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:var(--border2); border-radius:99px; }

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
            border:1px solid transparent;
            transition:all .2s; white-space:nowrap;
        }
        .hdr-btn:hover { background:var(--surface); color:var(--text); border-color:var(--border2); }
        .hdr-btn i { font-size:16px; }
        .hdr-btn.active { color:var(--blue); background:var(--blue-dim); border-color:rgba(59,130,246,.2); }
        .hdr-btn.danger { color:var(--red); }
        .hdr-btn.danger:hover { background:var(--red-dim); border-color:rgba(239,68,68,.2); }

        .hdr-avatar {
            display:flex; align-items:center; gap:9px;
            padding:5px 10px 5px 14px; border-radius:99px;
            background:var(--surface); border:1px solid var(--border2);
            text-decoration:none; transition:border-color .2s; margin-right:4px;
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

        /* ══ BREADCRUMB ══ */
        .bc-bar {
            position:fixed; top:var(--header-h); right:0; left:0; z-index:100;
            background:rgba(13,17,23,.75);
            backdrop-filter:blur(12px);
            border-bottom:1px solid var(--border);
            padding:9px 24px;
        }
        .bc { display:flex; gap:6px; list-style:none; flex-wrap:wrap; }
        .bc li { font-size:12px; color:var(--text3); }
        .bc li a { color:var(--blue); }
        .bc li a:hover { text-decoration:underline; }
        .bc li:not(:last-child)::after { content:'/'; margin-right:6px; color:var(--text3); }

        /* ══ PAGE WRAP ══ */
        .page-wrap {
            max-width:1060px;
            margin:0 auto;
            padding:calc(var(--header-h) + 44px + 24px) 24px 60px;
        }

        /* ══ LAYOUT GRID ══ */
        .detail-grid {
            display:grid;
            grid-template-columns:1fr 320px;
            gap:20px;
            align-items:start;
        }

        /* ══ CARD ══ */
        .nx-card {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r2);
            overflow:hidden;
            margin-bottom:18px;
            transition:border-color .2s;
        }
        .nx-card:hover { border-color:var(--border2); }
        .nx-card:last-child { margin-bottom:0; }

        .nx-card-hdr {
            display:flex; align-items:center; gap:9px;
            padding:14px 18px; border-bottom:1px solid var(--border);
        }
        .nx-card-hdr h5 {
            font-size:14px; font-weight:800; color:var(--text);
            margin:0; display:flex; align-items:center; gap:7px;
        }
        .nx-card-hdr h5 i { font-size:17px; }
        .nx-card-hdr .hdr-badge {
            margin-right:auto; font-size:11px; font-weight:700;
            padding:2px 9px; border-radius:99px;
            background:var(--amber-dim); color:var(--amber);
        }
        .nx-card-body { padding:20px; }

        /* ══ SERVICE IMAGE ══ */
        .svc-img {
            width:100%; height:260px; object-fit:cover; display:block;
        }
        .svc-placeholder {
            width:100%; height:260px;
            background:var(--surface2);
            display:flex; align-items:center; justify-content:center;
            font-size:60px; color:var(--text3);
        }

        /* ══ TITLE AREA ══ */
        .title-area { padding:18px 20px 20px; }
        .svc-title {
            font-size:20px; font-weight:900; line-height:1.5;
            color:var(--text); margin-bottom:14px;
        }
        .meta-row { display:flex; gap:10px; flex-wrap:wrap; }
        .meta-pill {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 11px; border-radius:99px;
            font-size:11px; font-weight:700;
            background:var(--surface2); border:1px solid var(--border2);
            color:var(--text2);
        }
        .meta-pill i { font-size:13px; color:var(--blue); }
        .meta-pill.warn i { color:var(--amber); }
        .meta-pill.success i { color:var(--green); }

        /* ══ DESC ══ */
        .desc-text {
            line-height:1.95; color:var(--text2); font-size:14px;
        }

        /* ══ PROVIDER ══ */
        .prov-head {
            display:flex; align-items:center; gap:14px; margin-bottom:16px;
        }
        .prov-avatar {
            width:50px; height:50px; border-radius:13px; flex-shrink:0;
            background:linear-gradient(135deg, var(--blue), var(--purple));
            display:flex; align-items:center; justify-content:center;
            font-size:20px; font-weight:900; color:#fff;
            box-shadow:0 4px 16px var(--blue-glow);
        }
        .prov-name { font-size:15px; font-weight:800; color:var(--text); margin-bottom:4px; }
        .prov-verified {
            display:flex; align-items:center; gap:5px;
            font-size:12px; font-weight:700; color:var(--green);
        }
        .prov-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:8px;
        }
        .prov-item {
            display:flex; align-items:center; gap:8px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:9px; padding:10px 12px;
            font-size:12px; color:var(--text3); overflow:hidden;
        }
        .prov-item i { color:var(--blue); font-size:15px; flex-shrink:0; }
        .prov-item span {
            color:var(--text2); font-weight:600;
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }

        /* ══ RATINGS ══ */
        .stars-row { display:flex; gap:3px; }
        .star-on  { color:var(--amber); font-size:13px; }
        .star-off { color:var(--border2); font-size:13px; }

        .avg-block {
            display:flex; align-items:center; gap:16px;
            padding:16px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--r);
            margin-bottom:16px;
        }
        .avg-num {
            font-size:44px; font-weight:900; color:var(--amber);
            line-height:1; letter-spacing:-2px;
        }
        .avg-meta { display:flex; flex-direction:column; gap:5px; }
        .avg-count { font-size:12px; color:var(--text3); margin-top:3px; }

        .rev-item {
            padding:14px 0;
            border-bottom:1px solid var(--border);
        }
        .rev-item:last-child { border-bottom:none; padding-bottom:0; }
        .rev-top {
            display:flex; align-items:center;
            justify-content:space-between; margin-bottom:7px;
        }
        .rev-author {
            display:flex; align-items:center; gap:8px;
            font-size:13px; font-weight:700; color:var(--text);
        }
        .rev-chip {
            width:26px; height:26px; border-radius:7px;
            background:var(--blue-dim); color:var(--blue);
            display:flex; align-items:center; justify-content:center;
            font-size:11px; font-weight:800; flex-shrink:0;
        }
        .rev-text { font-size:13px; color:var(--text2); line-height:1.75; }
        .rev-date { font-size:11px; color:var(--text3); margin-top:5px; }

        /* ══ ORDER CARD (sticky) ══ */
        .order-card {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r2);
            overflow:hidden;
            position:sticky;
            top:calc(var(--header-h) + 44px + 14px);
        }

        /* السعر */
        .order-hero {
            position:relative; padding:24px; text-align:center;
            background:var(--surface2);
            border-bottom:1px solid var(--border);
            overflow:hidden;
        }
        .order-hero::before {
            content:''; position:absolute; inset:0;
            background:radial-gradient(ellipse at 50% 0%, var(--blue-dim), transparent 70%);
        }
        .price-big {
            position:relative;
            font-size:36px; font-weight:900; color:var(--text);
            line-height:1; letter-spacing:-1px;
        }
        .price-big .curr {
            font-size:16px; font-weight:600; color:var(--green);
            vertical-align:super;
        }
        .price-unit { font-size:12px; color:var(--text3); margin-top:6px; }

        /* body */
        .order-body { padding:18px; }

        /* notices */
        .nx-notice {
            display:flex; align-items:flex-start; gap:10px;
            padding:12px 14px; border-radius:var(--r);
            font-size:13px; font-weight:600; border:1px solid;
            margin-bottom:14px; line-height:1.5;
        }
        .nx-notice i { font-size:18px; flex-shrink:0; margin-top:1px; }
        .nx-notice.success { background:var(--green-dim); color:var(--green); border-color:rgba(16,185,129,.25); }
        .nx-notice.warning { background:var(--amber-dim); color:var(--amber); border-color:rgba(245,158,11,.25); }
        .nx-notice.info    { background:var(--blue-dim);  color:var(--blue);  border-color:rgba(59,130,246,.25); }

        /* payment options */
        .section-lbl {
            font-size:11px; font-weight:700; color:var(--text3);
            text-transform:uppercase; letter-spacing:.7px;
            margin-bottom:9px; display:flex; align-items:center; gap:5px;
        }
        .section-lbl i { font-size:13px; color:var(--blue); }
        .section-lbl .req { color:var(--red); font-size:14px; }

        .pay-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
        .pay-opt {
            display:flex; align-items:center; gap:10px;
            padding:10px 13px; border-radius:9px;
            border:1px solid var(--border2);
            cursor:pointer; transition:all .2s;
            background:var(--surface2);
        }
        .pay-opt:has(input:checked) {
            border-color:var(--blue); background:var(--blue-dim);
        }
        .pay-opt input { display:none; }
        .pay-radio {
            width:16px; height:16px; border-radius:50%;
            border:2px solid var(--border2); flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            transition:all .2s;
        }
        .pay-opt:has(input:checked) .pay-radio {
            border-color:var(--blue);
            background:var(--blue);
            box-shadow:0 0 8px var(--blue-glow);
        }
        .pay-opt:has(input:checked) .pay-radio::after {
            content:''; width:6px; height:6px;
            border-radius:50%; background:#fff;
        }
        .pay-opt i { font-size:17px; color:var(--text3); transition:color .2s; }
        .pay-opt:has(input:checked) i { color:var(--blue); }
        .pay-opt-label { font-size:13px; font-weight:600; color:var(--text2); transition:color .2s; }
        .pay-opt:has(input:checked) .pay-opt-label { color:var(--text); }

        /* notes */
        .notes-area {
            width:100%; padding:10px 13px;
            background:var(--surface2); border:1px solid var(--border2);
            border-radius:9px; color:var(--text);
            font-family:'Tajawal', sans-serif; font-size:13px;
            resize:vertical; min-height:72px; line-height:1.65;
            transition:border-color .2s, box-shadow .2s;
            margin-bottom:14px;
        }
        .notes-area:focus { outline:none; border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-dim); }
        .notes-area::placeholder { color:var(--text3); }

        /* buttons */
        .btn {
            display:flex; align-items:center; justify-content:center; gap:7px;
            width:100%; padding:12px; border-radius:9px; border:none;
            font-family:'Tajawal', sans-serif; font-size:14px; font-weight:800;
            cursor:pointer; transition:all .2s; text-decoration:none;
        }
        .btn + .btn { margin-top:8px; }

        .btn-primary {
            background:var(--blue); color:#fff;
            box-shadow:0 4px 16px var(--blue-glow);
        }
        .btn-primary:hover:not(:disabled) {
            opacity:.88; box-shadow:0 6px 22px var(--blue-glow);
            transform:translateY(-1px);
        }
        .btn-primary:disabled { opacity:.4; cursor:not-allowed; transform:none; }

        .btn-ghost {
            background:var(--surface2); color:var(--text2);
            border:1px solid var(--border2);
        }
        .btn-ghost:hover { color:var(--text); border-color:var(--blue); background:var(--blue-dim); }

        .btn-warning {
            background:var(--amber-dim); color:var(--amber);
            border:1px solid rgba(245,158,11,.25);
        }
        .btn-warning:hover { background:var(--amber); color:#000; }

        .btn-success {
            background:var(--green-dim); color:var(--green);
            border:1px solid rgba(16,185,129,.25);
        }
        .btn-success:hover { background:var(--green); color:#fff; }

        .btn-danger-ghost {
            background:transparent; color:var(--red);
            border:1px solid rgba(239,68,68,.25);
        }

        /* perks */
        .perks { margin-top:16px; padding-top:14px; border-top:1px solid var(--border); }
        .perk {
            display:flex; align-items:center; gap:8px;
            font-size:12px; color:var(--text3); padding:4px 0;
        }
        .perk i { color:var(--green); font-size:14px; }

        /* reviewed badge */
        .reviewed-badge {
            display:flex; align-items:center; justify-content:center; gap:6px;
            padding:10px; border-radius:9px;
            background:var(--green-dim); border:1px solid rgba(16,185,129,.25);
            font-size:12px; font-weight:700; color:var(--green);
            margin-top:8px;
        }

        /* alert for flash messages */
        .nx-alert {
            display:flex; align-items:center; gap:10px;
            padding:12px 16px; border-radius:var(--r);
            margin-bottom:18px; font-size:13px; font-weight:600; border:1px solid;
        }
        .nx-alert i { font-size:18px; flex-shrink:0; }
        .nx-alert.success { background:var(--green-dim); color:var(--green); border-color:rgba(16,185,129,.25); }
        .nx-alert.danger  { background:var(--red-dim);   color:var(--red);   border-color:rgba(239,68,68,.25); }
        .nx-alert.warning { background:var(--amber-dim); color:var(--amber); border-color:rgba(245,158,11,.25); }

        /* ══ ANIMATIONS ══ */
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(14px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .animate { animation:fadeUp .35s ease both; }
        .d1 { animation-delay:.05s; }
        .d2 { animation-delay:.10s; }
        .d3 { animation-delay:.15s; }
        .d4 { animation-delay:.20s; }

        /* ══ RESPONSIVE ══ */
        @media (max-width:820px) {
            .detail-grid { grid-template-columns:1fr; }
            .order-card { position:static; }
            .prov-grid { grid-template-columns:1fr; }
        }
        @media (max-width:600px) {
            .page-wrap { padding-left:14px; padding-right:14px; }
            .bc-bar { padding:8px 14px; }
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

    <a href="/local_services/services.php" class="hdr-btn">
        <i class="las la-th-large"></i><span>الخدمات</span>
    </a>
    <a href="/local_services/client_dashboard.php" class="hdr-btn">
        <i class="las la-clipboard-list"></i><span>طلباتي</span>
    </a>
    <a href="/local_services/logout.php" class="hdr-btn danger">
        <i class="las la-sign-out-alt"></i><span>خروج</span>
    </a>
    <a href="/local_services/profile.php" class="hdr-avatar">
        <div>
            <div class="avatar-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="avatar-role">عميل</div>
        </div>
        <div class="avatar-ring"><?php echo $user_initial; ?></div>
    </a>
</header>

<!-- ══ BREADCRUMB ══ -->
<div class="bc-bar">
    <ul class="bc">
        <li><a href="/local_services/index.php">الرئيسية</a></li>
        <li><a href="/local_services/services.php">الخدمات</a></li>
        <li><?php echo htmlspecialchars($bc_title); ?></li>
    </ul>
</div>

<!-- ══ CONTENT ══ -->
<div class="page-wrap">

    <?php if (function_exists('display_message')) display_message(); ?>

    <div class="detail-grid">

        <!-- ── العمود الرئيسي ── -->
        <div>

            <!-- صورة + عنوان -->
            <div class="nx-card animate d1">
                <?php if ($service_img): ?>
                    <img src="<?php echo $service_img; ?>" class="svc-img"
                         alt="<?php echo htmlspecialchars($service['title']); ?>"
                         onerror="this.style.display='none';document.getElementById('imgPlaceholder').style.display='flex';">
                    <div class="svc-placeholder" id="imgPlaceholder" style="display:none;">
                        <i class="las la-concierge-bell"></i>
                    </div>
                <?php else: ?>
                    <div class="svc-placeholder"><i class="las la-concierge-bell"></i></div>
                <?php endif; ?>

                <div class="title-area">
                    <h1 class="svc-title"><?php echo htmlspecialchars($service['title']); ?></h1>
                    <div class="meta-row">
                        <span class="meta-pill">
                            <i class="las la-tag"></i>
                            <?php echo htmlspecialchars($service['category_name'] ?? 'عام'); ?>
                        </span>
                        <span class="meta-pill">
                            <i class="las la-map-marker-alt"></i>
                            <?php echo htmlspecialchars($service['provider_city'] ?? 'غير محددة'); ?>
                        </span>
                        <span class="meta-pill">
                            <i class="las la-calendar-alt"></i>
                            <?php echo date('Y/m/d', strtotime($service['created_at'])); ?>
                        </span>
                        <?php if ($avg_rating > 0): ?>
                            <span class="meta-pill warn">
                                <i class="las la-star"></i>
                                <?php echo $avg_rating; ?> / 5 &nbsp;(<?php echo $total_rev; ?> تقييم)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- وصف الخدمة -->
            <div class="nx-card animate d2">
                <div class="nx-card-hdr">
                    <h5><i class="las la-align-right" style="color:var(--blue);"></i> وصف الخدمة</h5>
                </div>
                <div class="nx-card-body">
                    <div class="desc-text">
                        <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                    </div>
                </div>
            </div>

            <!-- مزود الخدمة -->
            <div class="nx-card animate d3">
                <div class="nx-card-hdr">
                    <h5><i class="las la-user-tie" style="color:var(--cyan);"></i> مزود الخدمة</h5>
                </div>
                <div class="nx-card-body">
                    <div class="prov-head">
                        <div class="prov-avatar"><?php echo $prov_initial; ?></div>
                        <div>
                            <div class="prov-name"><?php echo htmlspecialchars($service['provider_name']); ?></div>
                            <div class="prov-verified">
                                <i class="las la-check-circle"></i> مزود خدمة معتمد
                            </div>
                        </div>
                    </div>
                    <div class="prov-grid">
                        <div class="prov-item">
                            <i class="las la-envelope"></i>
                            <span><?php echo htmlspecialchars($service['provider_email']); ?></span>
                        </div>
                        <div class="prov-item">
                            <i class="las la-phone"></i>
                            <span><?php echo htmlspecialchars($service['provider_phone'] ?? 'غير متوفر'); ?></span>
                        </div>
                        <div class="prov-item">
                            <i class="las la-city"></i>
                            <span><?php echo htmlspecialchars($service['provider_city'] ?? 'غير محددة'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تقييمات العملاء -->
            <?php if (!empty($ratings)): ?>
                <div class="nx-card animate d4">
                    <div class="nx-card-hdr">
                        <h5><i class="las la-star" style="color:var(--amber);"></i> تقييمات العملاء</h5>
                        <span class="hdr-badge"><?php echo $total_rev; ?> تقييم</span>
                    </div>
                    <div class="nx-card-body">
                        <div class="avg-block">
                            <div class="avg-num"><?php echo $avg_rating; ?></div>
                            <div class="avg-meta">
                                <?php echo render_stars($avg_rating); ?>
                                <div class="avg-count">متوسط التقييم من <?php echo $total_rev; ?> مراجعة</div>
                            </div>
                        </div>
                        <?php foreach ($ratings as $rev): ?>
                            <div class="rev-item">
                                <div class="rev-top">
                                    <div class="rev-author">
                                        <div class="rev-chip">
                                            <?php echo mb_substr($rev['full_name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <?php echo htmlspecialchars($rev['full_name']); ?>
                                    </div>
                                    <?php echo render_stars((float)$rev['rating']); ?>
                                </div>
                                <?php if (!empty($rev['comment'])): ?>
                                    <div class="rev-text"><?php echo htmlspecialchars($rev['comment']); ?></div>
                                <?php endif; ?>
                                <div class="rev-date">
                                    <i class="las la-clock" style="margin-left:3px;"></i>
                                    <?php echo date('Y/m/d', strtotime($rev['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- /main col -->

        <!-- ── العمود الجانبي ── -->
        <div>
            <div class="order-card animate d1">

                <!-- بطاقة السعر -->
                <div class="order-hero">
                    <div class="price-big">
                        <span class="curr">ر.س</span>
                        <?php echo number_format($service['price'], 2); ?>
                    </div>
                    <div class="price-unit">سعر الخدمة الإجمالي</div>
                </div>

                <div class="order-body">

                    <?php if ($is_own_service): ?>
                        <!-- ─── صاحب الخدمة ─── -->
                        <div class="nx-notice info" style="margin-bottom:14px;">
                            <i class="las la-info-circle"></i>
                            هذه خدمتك — يمكنك تعديلها أو إدارتها من لوحة التحكم.
                        </div>
                        <a href="/local_services/edit_service.php?id=<?php echo $service['id']; ?>" class="btn btn-warning">
                            <i class="las la-edit"></i> تعديل الخدمة
                        </a>
                        <a href="/local_services/dashboard.php" class="btn btn-ghost">
                            <i class="las la-tachometer-alt"></i> لوحة التحكم
                        </a>

                    <?php elseif ($already_ordered): ?>
                        <!-- ─── سبق وطلبها ─── -->
                        <div class="nx-notice success">
                            <i class="las la-check-circle"></i>
                            <div>
                                طلبت هذه الخدمة مسبقاً.
                                <?php if ($completed_order_id): ?>
                                    <br><span style="font-weight:400;font-size:12px;">يمكنك تقييمها الآن.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="/local_services/client_dashboard.php" class="btn btn-ghost">
                            <i class="las la-list-alt"></i> عرض طلباتي
                        </a>
                        <?php if ($completed_order_id && !$already_reviewed): ?>
                            <a href="/local_services/leave_review.php?order_id=<?php echo $completed_order_id; ?>" class="btn btn-success">
                                <i class="las la-star"></i> قيّم هذه الخدمة
                            </a>
                        <?php elseif ($already_reviewed): ?>
                            <div class="reviewed-badge">
                                <i class="las la-check-circle"></i> قيّمت هذه الخدمة بالفعل
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- ─── نموذج الطلب ─── -->
                        <form method="POST" action="/local_services/actions/place_order.php"
                              onsubmit="return validateOrder(this)">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">

                            <div class="section-lbl">
                                <i class="las la-credit-card"></i>
                                طريقة الدفع
                                <span class="req">*</span>
                            </div>
                            <div class="pay-group">
                                <label class="pay-opt">
                                    <input type="radio" name="payment_method" value="بطاقة ائتمان">
                                    <div class="pay-radio"></div>
                                    <i class="las la-credit-card"></i>
                                    <span class="pay-opt-label">بطاقة ائتمان / مدى</span>
                                </label>
                                <label class="pay-opt">
                                    <input type="radio" name="payment_method" value="دفع عند التسليم">
                                    <div class="pay-radio"></div>
                                    <i class="las la-money-bill-wave"></i>
                                    <span class="pay-opt-label">دفع عند التسليم</span>
                                </label>
                                <label class="pay-opt">
                                    <input type="radio" name="payment_method" value="محفظة إلكترونية">
                                    <div class="pay-radio"></div>
                                    <i class="las la-wallet"></i>
                                    <span class="pay-opt-label">محفظة إلكترونية</span>
                                </label>
                            </div>

                            <div class="section-lbl" style="margin-bottom:8px;">
                                <i class="las la-comment-alt"></i>
                                ملاحظات
                                <span style="font-weight:400;font-size:11px;color:var(--text3);text-transform:none;letter-spacing:0;">(اختياري)</span>
                            </div>
                            <textarea name="notes" class="notes-area"
                                      placeholder="تعليمات خاصة بطلبك…"
                                      maxlength="500"></textarea>

                            <button type="submit" class="btn btn-primary" id="orderBtn">
                                <i class="las la-shopping-cart"></i> اطلب الآن
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- مميزات -->
                    <div class="perks">
                        <div class="perk"><i class="las la-shield-alt"></i> ضمان جودة الخدمة</div>
                        <div class="perk"><i class="las la-headset"></i> دعم على مدار الساعة</div>
                        <div class="perk"><i class="las la-undo"></i> إمكانية الإلغاء</div>
                    </div>
                </div>
            </div>

            <!-- زر العودة -->
            <a href="/local_services/services.php" class="btn btn-ghost animate d2"
               style="margin-top:12px; width:100%; justify-content:center;">
                <i class="las la-arrow-right"></i> العودة إلى الخدمات
            </a>
        </div><!-- /sidebar -->

    </div><!-- /detail-grid -->
</div><!-- /page-wrap -->

<script>
function validateOrder(form) {
    var pay = form.querySelector('input[name="payment_method"]:checked');
    if (!pay) {
        alert('الرجاء اختيار طريقة الدفع قبل المتابعة');
        return false;
    }
    var btn = document.getElementById('orderBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="las la-spinner la-spin"></i> جارٍ الإرسال…';
    }
    return true;
}
</script>
</body>
</html>