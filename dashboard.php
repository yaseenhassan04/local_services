<?php
// /local_services/dashboard.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

global $pdo;
if (!$pdo) {
    die('خطأ في الاتصال بقاعدة البيانات.');
}

$user = getCurrentUser();
if (!$user) {
    header("Location: /local_services/login.php");
    exit;
}

$user_id   = $user['id'];
$user_role = $user['role'];
$current_page = 'dashboard';

if ($user_role === 'client') {
    header("Location: /local_services/client_dashboard.php");
    exit;
}

// ── CSRF يُولَّد دائماً بغض النظر عن الدور ─────────────────────
$csrf_token = generateCsrfToken();

// ── إحصائيات المزود ─────────────────────────────────────────────
if ($user_role === 'provider') {
    $stmt_stats = $pdo->prepare("
        SELECT
            COUNT(o.id) AS total_orders,
            SUM(CASE WHEN o.status IN ('pending','processing','in_progress') THEN 1 ELSE 0 END) AS active_orders,
            SUM(CASE WHEN o.status = 'completed'  THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN o.status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled_orders,
            SUM(CASE WHEN o.status = 'completed'  THEN COALESCE(o.amount,0) ELSE 0 END) AS total_earned,
            (SELECT COUNT(*) FROM services WHERE provider_id = ?) AS services_count
        FROM orders o WHERE o.provider_id = ?
    ");
    $stmt_stats->execute([$user_id, $user_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // الطلبات النشطة أولاً ثم الأحدث
    $stmt_orders = $pdo->prepare("
        SELECT o.id, o.order_date, o.status, s.title,
               c.full_name AS client_name, o.amount
        FROM orders o
        JOIN services s ON o.service_id = s.id
        JOIN users    c ON o.client_id  = c.id
        WHERE o.provider_id = ?
        ORDER BY
            CASE WHEN o.status IN ('pending','processing','in_progress') THEN 0 ELSE 1 END,
            o.order_date DESC
        LIMIT 20
    ");
    $stmt_orders->execute([$user_id]);
    $incoming_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    $stmt_services = $pdo->prepare("
        SELECT id, title, price, city, image, is_active, created_at
        FROM services WHERE provider_id = ? ORDER BY created_at DESC
    ");
    $stmt_services->execute([$user_id]);
    $services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);
}

// ── إحصائيات المدير ─────────────────────────────────────────────
if ($user_role === 'admin') {
    $admin_stats = [];
    try {
        $admin_stats['users']     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $admin_stats['providers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='provider'")->fetchColumn();
        $admin_stats['clients']   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
        $admin_stats['services_active']   = $pdo->query("SELECT COUNT(*) FROM services WHERE is_active=1")->fetchColumn();
        $admin_stats['services_inactive'] = $pdo->query("SELECT COUNT(*) FROM services WHERE is_active=0")->fetchColumn();
        $admin_stats['orders']    = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $admin_stats['orders_pending'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing','in_progress')")->fetchColumn();
        $admin_stats['revenue']   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='completed'")->fetchColumn();

        // آخر 10 طلبات للأدمن
        $stmt_recent = $pdo->query("
            SELECT o.id, o.order_date, o.status, s.title,
                   c.full_name AS client_name, p.full_name AS provider_name, o.amount
            FROM orders o
            JOIN services s ON o.service_id = s.id
            JOIN users c ON o.client_id  = c.id
            JOIN users p ON o.provider_id = p.id
            ORDER BY o.order_date DESC LIMIT 10
        ");
        $admin_recent_orders = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

        // آخر 5 مستخدمين
        $stmt_users = $pdo->query("
            SELECT id, full_name, email, role, created_at
            FROM users ORDER BY created_at DESC LIMIT 5
        ");
        $admin_recent_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

        // الخدمات المعلقة (غير نشطة)
        $stmt_pending_svc = $pdo->query("
            SELECT s.id, s.title, s.price, s.city, u.full_name AS provider_name, s.created_at
            FROM services s JOIN users u ON s.provider_id = u.id
            WHERE s.is_active = 0 ORDER BY s.created_at DESC LIMIT 5
        ");
        $admin_pending_services = $stmt_pending_svc->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $admin_stats = ['users' => 0, 'providers' => 0, 'clients' => 0, 'services_active' => 0, 'services_inactive' => 0, 'orders' => 0, 'orders_pending' => 0, 'revenue' => 0];
        $admin_recent_orders = [];
        $admin_recent_users = [];
        $admin_pending_services = [];
    }
}

// ── دوال مساعدة ─────────────────────────────────────────────────
if (!function_exists('translate_status')) {
    function translate_status($s)
    {
        return [
            'pending'    => 'معلق',
            'processing' => 'قيد المعالجة',
            'in_progress' => 'قيد التنفيذ',
            'completed'  => 'مكتمل',
            'cancelled'  => 'ملغي'
        ][$s] ?? $s;
    }
}
if (!function_exists('get_status_css')) {
    function get_status_css($s)
    {
        return [
            'pending'    => 'warning',
            'processing' => 'purple',
            'in_progress' => 'info',
            'completed'  => 'success',
            'cancelled'  => 'danger'
        ][$s] ?? 'muted';
    }
}
if (!function_exists('translate_role')) {
    function translate_role($r)
    {
        return ['admin' => 'مدير', 'provider' => 'مزود', 'client' => 'عميل'][$r] ?? $r;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم | خدماتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <style>
        /* ── إخفاء أي هيدر خارجي ── */
        body>nav,
        body>header:not(.app-header),
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

        /* ══════════════════════════════════════════════
   NEXUS — DARK DASHBOARD  (RTL Arabic)
══════════════════════════════════════════════ */
        :root {
            --bg: #07090f;
            --bg2: #0d1117;
            --surface: #111827;
            --surface2: #161f2e;
            --border: rgba(255, 255, 255, .07);
            --border2: rgba(255, 255, 255, .12);

            --blue: #3b82f6;
            --blue-dim: rgba(59, 130, 246, .12);
            --blue-glow: rgba(59, 130, 246, .25);
            --cyan: #06b6d4;
            --cyan-dim: rgba(6, 182, 212, .12);
            --green: #10b981;
            --green-dim: rgba(16, 185, 129, .12);
            --amber: #f59e0b;
            --amber-dim: rgba(245, 158, 11, .12);
            --red: #ef4444;
            --red-dim: rgba(239, 68, 68, .12);
            --purple: #8b5cf6;
            --purple-dim: rgba(139, 92, 246, .12);
            --rose: #f43f5e;
            --rose-dim: rgba(244, 63, 94, .12);

            --text: #f1f5f9;
            --text2: #94a3b8;
            --text3: #475569;

            --sidebar: 260px;
            --header-h: 64px;
            --r: 10px;
            --r2: 14px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            direction: rtl;
            overflow-x: hidden;
        }

        /* scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 99px;
        }

        /* ══ NOISE TEXTURE overlay ══ */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: .5;
        }

        /* ══ HEADER ══ */
        .app-header {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 200;
            height: var(--header-h);
            background: rgba(13, 17, 23, .85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 12px;
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            min-width: 220px;
        }

        .logo-mark {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--blue), var(--cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            font-weight: 900;
            box-shadow: 0 0 18px var(--blue-glow);
        }

        .logo-text {
            font-size: 17px;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -.3px;
        }

        .logo-text span {
            color: var(--blue);
        }

        .hdr-toggle {
            background: none;
            border: none;
            color: var(--text2);
            font-size: 20px;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 7px;
            transition: background .2s, color .2s;
            line-height: 1;
        }

        .hdr-toggle:hover {
            background: var(--surface);
            color: var(--text);
        }

        .hdr-spacer {
            flex: 1;
        }

        .hdr-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 12px;
            color: var(--text2);
            white-space: nowrap;
        }

        .hdr-pill i {
            color: var(--green);
            font-size: 8px;
        }

        .hdr-btn {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text2);
            font-size: 17px;
            text-decoration: none;
            transition: all .2s;
            cursor: pointer;
            position: relative;
        }

        .hdr-btn:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-dim);
        }

        .hdr-notif::after {
            content: '';
            position: absolute;
            top: 7px;
            left: 8px;
            width: 7px;
            height: 7px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid var(--bg2);
        }

        .hdr-avatar {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 5px 10px 5px 14px;
            border-radius: 99px;
            background: var(--surface);
            border: 1px solid var(--border2);
            text-decoration: none;
            transition: border-color .2s;
        }

        .hdr-avatar:hover {
            border-color: var(--blue);
        }

        .avatar-ring {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .avatar-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.1;
        }

        .avatar-role {
            font-size: 11px;
            color: var(--text2);
        }

        /* ══ SIDEBAR ══ */
        .app-sidebar {
            position: fixed;
            top: var(--header-h);
            right: 0;
            width: var(--sidebar);
            height: calc(100vh - var(--header-h));
            background: var(--bg2);
            border-left: 1px solid var(--border);
            overflow-y: auto;
            z-index: 150;
            transition: transform .3s cubic-bezier(.4, 0, .2, 1);
            display: flex;
            flex-direction: column;
        }

        .app-sidebar.collapsed {
            transform: translateX(var(--sidebar));
        }

        .app-sidebar.mobile-open {
            transform: translateX(0) !important;
        }

        /* profile block */
        .sb-profile {
            padding: 20px 16px 16px;
            border-bottom: 1px solid var(--border);
        }

        .sb-av {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(59, 130, 246, .3);
        }

        .sb-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--text);
        }

        .sb-email {
            font-size: 11px;
            color: var(--text3);
            margin-top: 2px;
            word-break: break-all;
        }

        .sb-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            background: var(--blue-dim);
            color: var(--blue);
            border: 1px solid rgba(59, 130, 246, .2);
        }

        .sb-badge.admin {
            background: var(--rose-dim);
            color: var(--rose);
            border-color: rgba(244, 63, 94, .2);
        }

        .sb-edit {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            padding: 7px 12px;
            border-radius: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            color: var(--text2);
            transition: all .2s;
        }

        .sb-edit:hover {
            background: var(--blue-dim);
            color: var(--blue);
            border-color: rgba(59, 130, 246, .3);
        }

        /* nav */
        .sb-section {
            padding: 16px 16px 4px;
            font-size: 10px;
            font-weight: 700;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sb-nav {
            list-style: none;
            padding: 0 10px;
        }

        .sb-nav li {
            margin-bottom: 2px;
        }

        .sb-nav a {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 10px;
            border-radius: 8px;
            color: var(--text2);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all .18s;
            position: relative;
        }

        .sb-nav a i {
            font-size: 17px;
            min-width: 20px;
            transition: transform .2s;
        }

        .sb-nav a:hover {
            background: var(--surface);
            color: var(--text);
        }

        .sb-nav a:hover i {
            transform: scale(1.1);
        }

        .sb-nav a.active {
            background: var(--blue-dim);
            color: var(--blue);
            border: 1px solid rgba(59, 130, 246, .2);
        }

        .sb-nav a.active::before {
            content: '';
            position: absolute;
            right: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--blue);
            border-radius: 3px;
        }

        .sb-nav a.danger {
            color: var(--red-dim);
        }

        .sb-nav a.danger:hover {
            background: var(--red-dim);
            color: var(--red);
        }

        .sb-nav .chip {
            margin-right: auto;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 99px;
            background: var(--amber-dim);
            color: var(--amber);
        }

        .sb-footer {
            margin-top: auto;
            padding: 14px 16px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--text3);
        }

        /* ══ MAIN ══ */
        .app-main {
            margin-right: var(--sidebar);
            margin-top: var(--header-h);
            padding: 28px 26px;
            min-height: calc(100vh - var(--header-h));
            transition: margin-right .3s cubic-bezier(.4, 0, .2, 1);
            position: relative;
            z-index: 1;
        }

        .app-main.wide {
            margin-right: 0;
        }

        /* ══ PAGE HEADER ══ */
        .pg-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pg-title {
            font-size: 24px;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -.5px;
            line-height: 1.1;
        }

        .pg-sub {
            font-size: 13px;
            color: var(--text2);
            margin-top: 4px;
        }

        .pg-sub a {
            color: var(--blue);
            text-decoration: none;
        }

        .pg-sub a:hover {
            text-decoration: underline;
        }

        .pg-sub span::before {
            content: ' / ';
            color: var(--text3);
        }

        /* ══ ALERT ══ */
        .nx-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--r);
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid;
        }

        .nx-alert.success {
            background: var(--green-dim);
            color: var(--green);
            border-color: rgba(16, 185, 129, .25);
        }

        .nx-alert.danger {
            background: var(--red-dim);
            color: var(--red);
            border-color: rgba(239, 68, 68, .25);
        }

        .nx-alert.warning {
            background: var(--amber-dim);
            color: var(--amber);
            border-color: rgba(245, 158, 11, .25);
        }

        /* ══ STATS GRID ══ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(168px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 18px 16px;
            position: relative;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
            cursor: default;
        }

        .stat-card:hover {
            border-color: var(--border2);
            transform: translateY(-2px);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--r2);
            opacity: 0;
            transition: opacity .3s;
            background: radial-gradient(circle at 30% 30%, var(--glow-color, transparent), transparent 70%);
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-card[data-color="blue"] {
            --glow-color: var(--blue-dim);
        }

        .stat-card[data-color="cyan"] {
            --glow-color: var(--cyan-dim);
        }

        .stat-card[data-color="green"] {
            --glow-color: var(--green-dim);
        }

        .stat-card[data-color="amber"] {
            --glow-color: var(--amber-dim);
        }

        .stat-card[data-color="red"] {
            --glow-color: var(--red-dim);
        }

        .stat-card[data-color="purple"] {
            --glow-color: var(--purple-dim);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .stat-icon.blue {
            background: var(--blue-dim);
            color: var(--blue);
        }

        .stat-icon.cyan {
            background: var(--cyan-dim);
            color: var(--cyan);
        }

        .stat-icon.green {
            background: var(--green-dim);
            color: var(--green);
        }

        .stat-icon.amber {
            background: var(--amber-dim);
            color: var(--amber);
        }

        .stat-icon.red {
            background: var(--red-dim);
            color: var(--red);
        }

        .stat-icon.purple {
            background: var(--purple-dim);
            color: var(--purple);
        }

        .stat-icon.rose {
            background: var(--rose-dim);
            color: var(--rose);
        }

        .stat-val {
            font-size: 28px;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-lbl {
            font-size: 12px;
            color: var(--text2);
            margin-top: 4px;
            font-weight: 500;
        }

        .stat-sub {
            font-size: 11px;
            color: var(--text3);
            margin-top: 2px;
        }

        /* ══ CARD ══ */
        .nx-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .nx-card:last-child {
            margin-bottom: 0;
        }

        .nx-card-hdr {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
        }

        .nx-card-hdr h5 {
            font-size: 14px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0;
        }

        .nx-card-hdr h5 i {
            font-size: 17px;
        }

        .nx-card-body {
            padding: 18px;
        }

        /* ══ TABLE ══ */
        .nx-table {
            width: 100%;
            border-collapse: collapse;
        }

        .nx-table thead th {
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: .6px;
            background: rgba(255, 255, 255, .02);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .nx-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        .nx-table tbody tr:last-child {
            border-bottom: none;
        }

        .nx-table tbody tr:hover {
            background: rgba(255, 255, 255, .025);
        }

        .nx-table tbody td {
            padding: 12px 14px;
            font-size: 13px;
            color: var(--text2);
            vertical-align: middle;
        }

        .t-id {
            font-weight: 800;
            color: var(--blue);
            font-size: 12px;
        }

        .t-main {
            font-weight: 700;
            color: var(--text);
        }

        .t-mono {
            font-family: monospace;
            font-size: 12px;
        }

        .t-green {
            font-weight: 800;
            color: var(--green);
        }

        /* ══ PILL ══ */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pill::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        .pill-warning {
            background: var(--amber-dim);
            color: var(--amber);
        }

        .pill-success {
            background: var(--green-dim);
            color: var(--green);
        }

        .pill-danger {
            background: var(--red-dim);
            color: var(--red);
        }

        .pill-info {
            background: var(--cyan-dim);
            color: var(--cyan);
        }

        .pill-purple {
            background: var(--purple-dim);
            color: var(--purple);
        }

        .pill-muted {
            background: rgba(71, 85, 105, .2);
            color: var(--text3);
        }

        .pill-blue {
            background: var(--blue-dim);
            color: var(--blue);
        }

        /* role pills */
        .role-admin {
            background: var(--rose-dim);
            color: var(--rose);
            border-radius: 99px;
            padding: 2px 9px;
            font-size: 11px;
            font-weight: 700;
        }

        .role-provider {
            background: var(--blue-dim);
            color: var(--blue);
            border-radius: 99px;
            padding: 2px 9px;
            font-size: 11px;
            font-weight: 700;
        }

        .role-client {
            background: var(--green-dim);
            color: var(--green);
            border-radius: 99px;
            padding: 2px 9px;
            font-size: 11px;
            font-weight: 700;
        }

        /* ══ BUTTONS ══ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            font-weight: 700;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all .18s;
            white-space: nowrap;
            line-height: 1;
        }

        .btn-primary {
            background: var(--blue-dim);
            color: var(--blue);
            border: 1px solid rgba(59, 130, 246, .25);
        }

        .btn-primary:hover {
            background: var(--blue);
            color: #fff;
            box-shadow: 0 0 16px var(--blue-glow);
            text-decoration: none;
        }

        .btn-success {
            background: var(--green-dim);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, .25);
        }

        .btn-success:hover {
            background: var(--green);
            color: #fff;
            text-decoration: none;
        }

        .btn-warning {
            background: var(--amber-dim);
            color: var(--amber);
            border: 1px solid rgba(245, 158, 11, .25);
        }

        .btn-warning:hover {
            background: var(--amber);
            color: #000;
            text-decoration: none;
        }

        .btn-danger {
            background: var(--red-dim);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, .25);
        }

        .btn-danger:hover {
            background: var(--red);
            color: #fff;
            text-decoration: none;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 11px;
            border-radius: 6px;
        }

        /* ══ PROGRESS ══ */
        .prog-wrap {
            margin-bottom: 0;
        }

        .prog-head {
            display: flex;
            justify-content: space-between;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
        }

        .prog-head span:last-child {
            color: var(--green);
        }

        .prog-track {
            height: 5px;
            background: var(--surface2);
            border-radius: 99px;
            overflow: hidden;
        }

        .prog-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--blue), var(--cyan));
            border-radius: 99px;
            transition: width .8s cubic-bezier(.4, 0, .2, 1);
        }

        /* ══ ADMIN QUICK ACTIONS ══ */
        .qa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }

        .qa-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            padding: 22px 18px;
            text-decoration: none;
            color: var(--text);
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all .2s;
            position: relative;
            overflow: hidden;
        }

        .qa-card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity .3s;
            background: radial-gradient(ellipse at 50% 0%, var(--qa-glow, transparent), transparent 70%);
        }

        .qa-card:hover {
            transform: translateY(-3px);
            border-color: var(--border2);
            text-decoration: none;
            color: var(--text);
        }

        .qa-card:hover::before {
            opacity: 1;
        }

        .qa-card[data-glow="blue"] {
            --qa-glow: var(--blue-dim);
        }

        .qa-card[data-glow="cyan"] {
            --qa-glow: var(--cyan-dim);
        }

        .qa-card[data-glow="green"] {
            --qa-glow: var(--green-dim);
        }

        .qa-card[data-glow="red"] {
            --qa-glow: var(--red-dim);
        }

        .qa-card[data-glow="purple"] {
            --qa-glow: var(--purple-dim);
        }

        .qa-card[data-glow="amber"] {
            --qa-glow: var(--amber-dim);
        }

        .qa-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            transition: transform .2s;
        }

        .qa-card:hover .qa-icon {
            transform: scale(1.08) rotate(-4deg);
        }

        .qa-title {
            font-size: 14px;
            font-weight: 800;
        }

        .qa-sub {
            font-size: 12px;
            color: var(--text2);
            margin-top: 2px;
        }

        .qa-arr {
            position: absolute;
            bottom: 14px;
            left: 14px;
            font-size: 18px;
            color: var(--text3);
            transition: all .2s;
        }

        .qa-card:hover .qa-arr {
            color: var(--text);
            transform: translateX(-4px);
        }

        /* ══ SERVICE CARDS (Provider) ══ */
        .svc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }

        .svc-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--r2);
            overflow: hidden;
            transition: border-color .2s, transform .2s;
        }

        .svc-card:hover {
            border-color: var(--border2);
            transform: translateY(-2px);
        }

        .svc-thumb {
            height: 140px;
            background: var(--bg);
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .svc-thumb-placeholder {
            height: 140px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--text3);
            position: relative;
        }

        .svc-status {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 99px;
        }

        .svc-status.active {
            background: var(--green-dim);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, .3);
        }

        .svc-status.inactive {
            background: var(--red-dim);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, .3);
        }

        .svc-body {
            padding: 14px 15px;
        }

        .svc-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 5px;
        }

        .svc-meta {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--text3);
            margin-bottom: 3px;
        }

        .svc-meta i {
            color: var(--blue);
            font-size: 13px;
        }

        .svc-price {
            font-size: 18px;
            font-weight: 900;
            color: var(--green);
            margin: 10px 0 12px;
            letter-spacing: -.5px;
        }

        .svc-price span {
            font-size: 11px;
            font-weight: 500;
            color: var(--text3);
        }

        .svc-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* ══ EMPTY STATE ══ */
        .empty {
            text-align: center;
            padding: 48px 20px;
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: var(--surface2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--text3);
            margin: 0 auto 14px;
        }

        .empty h4 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }

        .empty p {
            font-size: 13px;
            color: var(--text2);
        }

        /* ══ DUAL COLUMN ══ */
        .dual-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .dual-col {
                grid-template-columns: 1fr;
            }
        }

        /* ══ AVATAR MINI ══ */
        .av-mini {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--blue), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            color: #fff;
        }

        /* ══ DIVIDER ══ */
        .nx-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 18px 0;
        }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 900px) {
            .app-sidebar {
                transform: translateX(var(--sidebar));
            }

            .app-main {
                margin-right: 0 !important;
                padding: 16px;
            }

            .dual-col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }

            .qa-grid {
                grid-template-columns: 1fr 1fr;
            }

            .hdr-pill,
            .avatar-name,
            .avatar-role {
                display: none;
            }
        }

        @media (max-width: 380px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }

        /* ══ ANIMATIONS ══ */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate {
            animation: fadeUp .35s ease both;
        }

        .d1 {
            animation-delay: .05s;
        }

        .d2 {
            animation-delay: .10s;
        }

        .d3 {
            animation-delay: .15s;
        }

        .d4 {
            animation-delay: .20s;
        }

        .d5 {
            animation-delay: .25s;
        }
    

/* ══ LIGHT MODE — Dashboard ══════════════════════════════ */
html.light-mode, body.light-mode {
    --bg:       #f0f4f8;
    --bg2:      #e8edf3;
    --surface:  #ffffff;
    --surface2: #f1f5f9;
    --border:   rgba(0,0,0,.09);
    --border2:  rgba(0,0,0,.14);
    --text:     #0f172a;
    --text2:    #334155;
    --text3:    #64748b;
}
body.light-mode { background: var(--bg) !important; color: #0f172a !important; }

/* ── Header ── */
body.light-mode .app-header {
    background: #ffffff !important;
    border-bottom: 1px solid rgba(0,0,0,.1) !important;
    box-shadow: 0 2px 12px rgba(0,0,0,.07) !important;
}
body.light-mode .logo-text         { color: #0f172a !important; }
body.light-mode .hdr-toggle        { color: #334155 !important; }
body.light-mode .hdr-pill          { background: rgba(0,0,0,.06) !important; color: #334155 !important; border-color: rgba(0,0,0,.1) !important; }
body.light-mode #themeToggle       { border-color: rgba(0,0,0,.15) !important; color: #334155 !important; }
body.light-mode .avatar-name       { color: #0f172a !important; }
body.light-mode .avatar-role       { color: #3b82f6 !important; }
body.light-mode .hdr-avatar        { background: #fff !important; border-color: rgba(0,0,0,.12) !important; }
body.light-mode .hdr-btn           { background: #fff !important; border-color: rgba(0,0,0,.12) !important; color: #334155 !important; }

/* ── Sidebar (adm-sidebar) ── */
body.light-mode .adm-sidebar,
body.light-mode aside              { background: #ffffff !important; border-color: rgba(0,0,0,.09) !important; box-shadow: -3px 0 12px rgba(0,0,0,.06) !important; }
body.light-mode .adm-name          { color: #0f172a !important; }
body.light-mode .adm-email         { color: #64748b !important; }
body.light-mode .adm-group-hdr     { color: #64748b !important; }
body.light-mode .adm-group-hdr:hover { background: rgba(59,130,246,.07) !important; color: #0f172a !important; }
body.light-mode .adm-group-hdr.open { color: #0f172a !important; }
body.light-mode .adm-items ul      { border-color: rgba(0,0,0,.08) !important; }
body.light-mode .adm-items ul a    { color: #475569 !important; }
body.light-mode .adm-items ul a:hover { background: rgba(59,130,246,.08) !important; color: #1d4ed8 !important; }
body.light-mode .adm-items ul a.active { background: rgba(59,130,246,.12) !important; color: #1d4ed8 !important; border-color: rgba(59,130,246,.25) !important; }
body.light-mode .adm-edit-link     { background: #f1f5f9 !important; border-color: rgba(0,0,0,.1) !important; color: #475569 !important; }
body.light-mode .adm-footer        { color: #94a3b8 !important; border-color: rgba(0,0,0,.08) !important; }

/* ── Page titles & text ── */
body.light-mode .pg-title          { color: #0f172a !important; }
body.light-mode .pg-sub            { color: #475569 !important; }
body.light-mode .pg-sub a          { color: #2563eb !important; }

/* ── Stat cards ── */
body.light-mode .stat-card         { background: #ffffff !important; border-color: rgba(0,0,0,.08) !important; box-shadow: 0 2px 8px rgba(0,0,0,.06) !important; }
body.light-mode .stat-val          { color: #0f172a !important; }
body.light-mode .stat-lbl          { color: #475569 !important; }
body.light-mode .stat-sub          { color: #64748b !important; }

/* ── Cards ── */
body.light-mode .nx-card,
body.light-mode .svc-card          { background: #ffffff !important; border-color: rgba(0,0,0,.08) !important; box-shadow: 0 2px 8px rgba(0,0,0,.05) !important; }
body.light-mode .nx-card-hdr       { border-color: rgba(0,0,0,.07) !important; }
body.light-mode .nx-card-hdr h5    { color: #0f172a !important; }
body.light-mode .nx-card-body      { color: #334155 !important; }

/* ── Quick action cards ── */
body.light-mode .qa-card           { background: #f8fafc !important; border-color: rgba(0,0,0,.08) !important; }
body.light-mode .qa-title          { color: #0f172a !important; }
body.light-mode .qa-sub            { color: #475569 !important; }
body.light-mode .qa-arr            { color: #94a3b8 !important; }

/* ── Tables ── */
body.light-mode .nx-table thead th { color: #374151 !important; background: #f1f5f9 !important; border-color: rgba(0,0,0,.08) !important; }
body.light-mode .nx-table tbody tr { border-color: rgba(0,0,0,.06) !important; }
body.light-mode .nx-table tbody tr:hover { background: #f8fafc !important; }
body.light-mode .nx-table tbody td { color: #374151 !important; }
body.light-mode .t-main            { color: #0f172a !important; }
body.light-mode .t-id              { color: #2563eb !important; }
body.light-mode .t-green           { color: #059669 !important; }

/* ── Service cards ── */
body.light-mode .svc-name          { color: #0f172a !important; }
body.light-mode .svc-meta          { color: #64748b !important; }
body.light-mode .svc-price         { color: #059669 !important; }
body.light-mode .svc-thumb-placeholder { background: #f1f5f9 !important; }

/* ── User items ── */
body.light-mode .adm-profile       { border-color: rgba(0,0,0,.08) !important; }
body.light-mode [style*="color:var(--text)"]   { color: #0f172a !important; }
body.light-mode [style*="color:var(--text2)"]  { color: #334155 !important; }
body.light-mode [style*="color:var(--text3)"]  { color: #64748b !important; }

/* ── Progress bar ── */
body.light-mode .prog-track        { background: #e2e8f0 !important; }
body.light-mode .prog-head         { color: #0f172a !important; }

/* ── Empty state ── */
body.light-mode .empty-icon        { background: #f1f5f9 !important; border-color: rgba(0,0,0,.08) !important; color: #94a3b8 !important; }
body.light-mode .empty h4          { color: #0f172a !important; }
body.light-mode .empty p           { color: #64748b !important; }

/* ── Smooth transition ── */
body, .app-header, .adm-sidebar, aside,
.stat-card, .nx-card, .svc-card, .qa-card,
.nx-table thead th, .nx-table tbody td,
.adm-group-hdr, .adm-items ul a, .adm-name,
.pg-title, .stat-val, .nx-card-hdr h5 {
    transition: background .25s ease, border-color .25s ease,
                color .2s ease, box-shadow .25s ease !important;
}

</style>
<script>
/* تطبيق الثيم قبل رسم الصفحة لمنع الوميض */
if (localStorage.getItem('khadamati_theme') === 'light') {
    document.documentElement.classList.add('light-mode');
}
/* placeholder حتى يتحمل admin_sidebar ويعرّف الدالة الحقيقية */
window.toggleTheme = window.toggleTheme || function () {
    var isLight = document.body.classList.toggle('light-mode');
    document.documentElement.classList.toggle('light-mode', isLight);
    localStorage.setItem('khadamati_theme', isLight ? 'light' : 'dark');
    var icon  = document.getElementById('themeIcon');
    var label = document.getElementById('themeLabel');
    if (icon)  icon.className    = isLight ? 'las la-moon' : 'las la-sun';
    if (label) label.textContent = isLight ? 'داكن' : 'فاتح';
};
</script>
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
            <?php echo $user_role === 'admin' ? 'وضع المدير' : 'وضع المزود'; ?>
        </div>
        <!-- جنب زر "وضع المدير" -->
<button onclick="toggleTheme()" 
        id="themeToggle"
        style="display:inline-flex; align-items:center; gap:6px; padding:6px 14px;
               background:transparent; border:1px solid var(--border); border-radius:20px;
               color:var(--muted); font-family:'Tajawal',sans-serif; font-size:13px; 
               font-weight:700; cursor:pointer; transition:all .2s;">
    <i class="las la-sun" id="themeIcon"></i>
    <span id="themeLabel">فاتح</span>
</button>

        <?php if ($user_role === 'provider'): ?>
            <a href="/local_services/add_service.php" class="hdr-btn" title="إضافة خدمة">
                <i class="las la-plus"></i>
            </a>
        <?php endif; ?>

        <a href="/local_services/notifications.php" class="hdr-btn hdr-notif" title="الإشعارات">
            <i class="las la-bell"></i>
        </a>

        <a href="/local_services/profile.php" class="hdr-avatar">
            <div>
                <div class="avatar-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="avatar-role"><?php echo $user_role === 'admin' ? 'مدير النظام' : 'مزود خدمة'; ?></div>
            </div>
            <div class="avatar-ring"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
        </a>
    </header>

    <!-- ══ SIDEBAR ══ -->
    <?php include_once __DIR__ . '/includes/admin_sidebar.php'; ?>

    <!-- ══ MAIN ══ -->
    <main class="app-main" id="appMain">

        <?php if (function_exists('display_message')) display_message(); ?>

        <!-- Page Header -->
        <div class="pg-head animate">
            <div>
                <div class="pg-title">
                    <?php if ($user_role === 'admin'): ?>
                        <i class="las la-shield-alt" style="color:var(--rose);"></i> لوحة تحكم المدير
                    <?php else: ?>
                        <i class="las la-tachometer-alt" style="color:var(--blue);"></i> لوحة تحكم المزود
                    <?php endif; ?>
                </div>
                <div class="pg-sub">
                    <a href="/local_services/index.php">الرئيسية</a>
                    <span>لوحة التحكم</span>
                </div>
            </div>
            <?php if ($user_role === 'provider'): ?>
                <a href="/local_services/add_service.php" class="btn btn-success" style="padding:10px 18px;font-size:13px;">
                    <i class="las la-plus"></i> إضافة خدمة جديدة
                </a>
            <?php elseif ($user_role === 'admin'): ?>
                <a href="/local_services/admin/manage_orders.php" class="btn btn-primary" style="padding:10px 18px;font-size:13px;">
                    <i class="las la-clipboard-list"></i> إدارة الطلبات
                </a>
            <?php endif; ?>
        </div>

        <!-- ══════ ADMIN ══════ -->
        <?php if ($user_role === 'admin'): ?>

            <!-- Admin Stats Row 1 -->
            <div class="stats-row animate d1">
                <div class="stat-card" data-color="blue">
                    <div class="stat-icon blue"><i class="las la-users"></i></div>
                    <div class="stat-val"><?php echo number_format($admin_stats['users']); ?></div>
                    <div class="stat-lbl">إجمالي المستخدمين</div>
                    <div class="stat-sub">
                        <?php echo $admin_stats['providers']; ?> مزود &nbsp;·&nbsp; <?php echo $admin_stats['clients']; ?> عميل
                    </div>
                </div>
                <div class="stat-card" data-color="cyan">
                    <div class="stat-icon cyan"><i class="las la-concierge-bell"></i></div>
                    <div class="stat-val"><?php echo number_format($admin_stats['services_active']); ?></div>
                    <div class="stat-lbl">خدمات نشطة</div>
                    <div class="stat-sub"><?php echo $admin_stats['services_inactive']; ?> معطّلة</div>
                </div>
                <div class="stat-card" data-color="amber">
                    <div class="stat-icon amber"><i class="las la-clipboard-list"></i></div>
                    <div class="stat-val"><?php echo number_format($admin_stats['orders']); ?></div>
                    <div class="stat-lbl">إجمالي الطلبات</div>
                    <div class="stat-sub"><?php echo $admin_stats['orders_pending']; ?> طلب نشط</div>
                </div>
                <div class="stat-card" data-color="green">
                    <div class="stat-icon green"><i class="las la-dollar-sign"></i></div>
                    <div class="stat-val" style="font-size:22px;"><?php echo number_format((float)$admin_stats['revenue'], 0); ?></div>
                    <div class="stat-lbl">إجمالي الإيرادات</div>
                    <div class="stat-sub">ريال سعودي — طلبات مكتملة</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="nx-card animate d2">
                <div class="nx-card-hdr">
                    <h5><i class="las la-bolt" style="color:var(--amber);"></i> الإجراءات السريعة</h5>
                </div>
                <div class="nx-card-body">
                    <div class="qa-grid">
                        <a href="/local_services/admin/manage_orders.php" class="qa-card" data-glow="cyan">
                            <div class="qa-icon" style="background:var(--cyan-dim);color:var(--cyan);">
                                <i class="las la-clipboard-list"></i>
                            </div>
                            <div>
                                <div class="qa-title">إدارة الطلبات</div>
                                <div class="qa-sub">عرض وتحديث جميع الطلبات</div>
                            </div>
                            <i class="las la-arrow-left qa-arr"></i>
                        </a>
                        <a href="/local_services/admin/manage_services.php" class="qa-card" data-glow="blue">
                            <div class="qa-icon" style="background:var(--blue-dim);color:var(--blue);">
                                <i class="las la-tools"></i>
                            </div>
                            <div>
                                <div class="qa-title">إدارة الخدمات</div>
                                <div class="qa-sub">تفعيل، إيقاف، مراجعة</div>
                            </div>
                            <i class="las la-arrow-left qa-arr"></i>
                        </a>
                        <a href="/local_services/admin/manage_categories.php" class="qa-card" data-glow="green">
                            <div class="qa-icon" style="background:var(--green-dim);color:var(--green);">
                                <i class="las la-tags"></i>
                            </div>
                            <div>
                                <div class="qa-title">إدارة التصنيفات</div>
                                <div class="qa-sub">إضافة وتعديل التصنيفات</div>
                            </div>
                            <i class="las la-arrow-left qa-arr"></i>
                        </a>
                        <a href="/local_services/admin/manage_users.php" class="qa-card" data-glow="red">
                            <div class="qa-icon" style="background:var(--red-dim);color:var(--red);">
                                <i class="las la-users-cog"></i>
                            </div>
                            <div>
                                <div class="qa-title">إدارة المستخدمين</div>
                                <div class="qa-sub">صلاحيات وبيانات الحسابات</div>
                            </div>
                            <i class="las la-arrow-left qa-arr"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dual column: recent orders + recent users -->
            <div class="dual-col animate d3">

                <!-- Recent Orders -->
                <div class="nx-card" style="margin-bottom:0;">
                    <div class="nx-card-hdr">
                        <h5><i class="las la-history" style="color:var(--cyan);"></i> آخر الطلبات</h5>
                        <a href="/local_services/admin/manage_orders.php" class="btn btn-primary btn-sm">عرض الكل</a>
                    </div>
                    <?php if (empty($admin_recent_orders)): ?>
                        <div class="empty">
                            <div class="empty-icon"><i class="las la-inbox"></i></div>
                            <h4>لا توجد طلبات</h4>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="nx-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الخدمة</th>
                                        <th>العميل</th>
                                        <th>المبلغ</th>
                                        <th>الحالة</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admin_recent_orders as $o):
                                        $sc = get_status_css($o['status']);
                                    ?>
                                        <tr>
                                            <td><span class="t-id">#<?php echo $o['id']; ?></span></td>
                                            <td>
                                                <div class="t-main" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                    <?php echo htmlspecialchars($o['title']); ?>
                                                </div>
                                                <div style="font-size:11px;color:var(--text3);">
                                                    <?php echo htmlspecialchars($o['provider_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:6px;">
                                                    <div class="av-mini"><?php echo mb_substr($o['client_name'], 0, 1); ?></div>
                                                    <span style="font-size:12px;"><?php echo htmlspecialchars($o['client_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="t-green"><?php echo number_format((float)$o['amount'], 0); ?> ر.س</td>
                                            <td><span class="pill pill-<?php echo $sc; ?>"><?php echo translate_status($o['status']); ?></span></td>
                                            <td>
                                                <a href="/local_services/view_order.php?id=<?php echo $o['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="las la-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Users + Pending Services -->
                <div style="display:flex;flex-direction:column;gap:20px;">

                    <!-- Recent Users -->
                    <div class="nx-card" style="margin-bottom:0;">
                        <div class="nx-card-hdr">
                            <h5><i class="las la-user-plus" style="color:var(--blue);"></i> أحدث المستخدمين</h5>
                            <a href="/local_services/admin/manage_users.php" class="btn btn-primary btn-sm">عرض الكل</a>
                        </div>
                        <?php if (empty($admin_recent_users)): ?>
                            <div class="empty" style="padding:24px;">
                                <p>لا توجد بيانات</p>
                            </div>
                        <?php else: ?>
                            <div style="padding: 4px 0;">
                                <?php foreach ($admin_recent_users as $u): ?>
                                    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);">
                                        <div class="av-mini" style="width:32px;height:32px;border-radius:8px;font-size:13px;">
                                            <?php echo mb_substr($u['full_name'], 0, 1); ?>
                                        </div>
                                        <div style="flex:1;min-width:0;">
                                            <div style="font-size:13px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?php echo htmlspecialchars($u['full_name']); ?>
                                            </div>
                                            <div style="font-size:11px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?php echo htmlspecialchars($u['email']); ?>
                                            </div>
                                        </div>
                                        <span class="role-<?php echo $u['role']; ?>"><?php echo translate_role($u['role']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pending / Inactive Services -->
                    <?php if (!empty($admin_pending_services)): ?>
                        <div class="nx-card" style="margin-bottom:0;">
                            <div class="nx-card-hdr">
                                <h5><i class="las la-exclamation-circle" style="color:var(--amber);"></i> خدمات معطّلة</h5>
                                <a href="/local_services/admin/manage_services.php?filter=inactive" class="btn btn-warning btn-sm">عرض الكل</a>
                            </div>
                            <div style="padding:4px 0;">
                                <?php foreach ($admin_pending_services as $ps): ?>
                                    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);">
                                        <div style="width:32px;height:32px;border-radius:8px;background:var(--amber-dim);display:flex;align-items:center;justify-content:center;color:var(--amber);font-size:14px;flex-shrink:0;">
                                            <i class="las la-tools"></i>
                                        </div>
                                        <div style="flex:1;min-width:0;">
                                            <div style="font-size:13px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?php echo htmlspecialchars($ps['title']); ?>
                                            </div>
                                            <div style="font-size:11px;color:var(--text3);">
                                                <?php echo htmlspecialchars($ps['provider_name']); ?>
                                                <?php if (!empty($ps['city'])): ?> — <?php echo htmlspecialchars($ps['city']); ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="/local_services/admin/manage_services.php?action=activate&id=<?php echo $ps['id']; ?>&csrf=<?php echo $csrf_token; ?>"
                                            class="btn btn-success btn-sm">تفعيل</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div><!-- /right col -->
            </div><!-- /dual-col -->

        <?php endif; /* end admin */ ?>

        <!-- ══════ PROVIDER ══════ -->
        <?php if ($user_role === 'provider'): ?>

            <!-- Provider Stats -->
            <div class="stats-row animate d1">
                <div class="stat-card" data-color="blue">
                    <div class="stat-icon blue"><i class="las la-clipboard-list"></i></div>
                    <div class="stat-val"><?php echo (int)($stats['total_orders'] ?? 0); ?></div>
                    <div class="stat-lbl">إجمالي الطلبات</div>
                </div>
                <div class="stat-card" data-color="amber">
                    <div class="stat-icon amber"><i class="las la-spinner"></i></div>
                    <div class="stat-val"><?php echo (int)($stats['active_orders'] ?? 0); ?></div>
                    <div class="stat-lbl">طلبات نشطة</div>
                </div>
                <div class="stat-card" data-color="green">
                    <div class="stat-icon green"><i class="las la-check-circle"></i></div>
                    <div class="stat-val"><?php echo (int)($stats['completed_orders'] ?? 0); ?></div>
                    <div class="stat-lbl">طلبات مكتملة</div>
                </div>
                <div class="stat-card" data-color="red">
                    <div class="stat-icon red"><i class="las la-times-circle"></i></div>
                    <div class="stat-val"><?php echo (int)($stats['cancelled_orders'] ?? 0); ?></div>
                    <div class="stat-lbl">طلبات ملغية</div>
                </div>
                <div class="stat-card" data-color="purple">
                    <div class="stat-icon purple"><i class="las la-wallet"></i></div>
                    <div class="stat-val" style="font-size:22px;"><?php echo number_format((float)($stats['total_earned'] ?? 0), 0); ?></div>
                    <div class="stat-lbl">إجمالي الأرباح (ر.س)</div>
                </div>
                <div class="stat-card" data-color="cyan">
                    <div class="stat-icon cyan"><i class="las la-concierge-bell"></i></div>
                    <div class="stat-val"><?php echo (int)($stats['services_count'] ?? 0); ?></div>
                    <div class="stat-lbl">خدماتي المنشورة</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <?php
            $total = (int)($stats['total_orders'] ?? 0);
            $comp  = (int)($stats['completed_orders'] ?? 0);
            if ($total > 0):
                $pct = round($comp / $total * 100);
            ?>
                <div class="nx-card animate d2" style="margin-bottom:20px;">
                    <div class="nx-card-body">
                        <div class="prog-wrap">
                            <div class="prog-head">
                                <span><i class="las la-chart-line" style="color:var(--blue);margin-left:5px;"></i> نسبة إنجاز الطلبات</span>
                                <span><?php echo $pct; ?>%</span>
                            </div>
                            <div class="prog-track">
                                <div class="prog-fill" style="width:<?php echo $pct; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Incoming Orders -->
            <div class="nx-card animate d3">
                <div class="nx-card-hdr">
                    <h5><i class="las la-inbox" style="color:var(--blue);"></i> طلبات العملاء الواردة</h5>
                    <span style="font-size:11px;color:var(--text3);">الطلبات النشطة أولاً · آخر 20</span>
                </div>
                <?php if (empty($incoming_orders)): ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="las la-inbox"></i></div>
                        <h4>لا توجد طلبات بعد</h4>
                        <p>ستظهر هنا طلبات العملاء عند ورودها</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="nx-table">
                            <thead>
                                <tr>
                                    <th>رقم</th>
                                    <th>الخدمة</th>
                                    <th>العميل</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                    <th>إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incoming_orders as $o):
                                    $sc = get_status_css($o['status']);
                                    $is_active = in_array($o['status'], ['pending', 'processing', 'in_progress']);
                                ?>
                                    <tr <?php if ($is_active) echo 'style="background:rgba(59,130,246,.04);"'; ?>>
                                        <td><span class="t-id">#<?php echo htmlspecialchars($o['id']); ?></span></td>
                                        <td>
                                            <span class="t-main" style="display:block;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?php echo htmlspecialchars($o['title']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <div class="av-mini"><?php echo mb_substr($o['client_name'], 0, 1); ?></div>
                                                <span style="font-size:12px;color:var(--text2);"><?php echo htmlspecialchars($o['client_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="t-green"><?php echo number_format((float)$o['amount'], 2); ?></td>
                                        <td style="font-size:12px;color:var(--text3);"><?php echo date('Y/m/d', strtotime($o['order_date'])); ?></td>
                                        <td><span class="pill pill-<?php echo $sc; ?>"><?php echo translate_status($o['status']); ?></span></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                <a href="/local_services/view_order.php?id=<?php echo $o['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="las la-eye"></i> عرض
                                                </a>
                                                <form action="/local_services/admin/actions/update_order_status.php" method="POST"
                                                      style="display:flex;align-items:center;gap:5px;">
                                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <select name="new_status"
                                                            style="background:var(--card2,#0e1726);color:var(--text2,#e0e6ed);border:1px solid var(--border,#1b2e4b);border-radius:6px;padding:4px 6px;font-family:'Tajawal',sans-serif;font-size:11px;">
                                                        <option value="pending"     <?php echo $o['status']==='pending'     ? 'selected' : ''; ?>>قيد الانتظار</option>
                                                        <option value="processing"  <?php echo $o['status']==='processing'  ? 'selected' : ''; ?>>قيد المعالجة</option>
                                                        <option value="in_progress" <?php echo $o['status']==='in_progress' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                                                        <option value="completed"   <?php echo $o['status']==='completed'   ? 'selected' : ''; ?>>مكتمل</option>
                                                        <option value="cancelled"   <?php echo $o['status']==='cancelled'   ? 'selected' : ''; ?>>ملغي</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-success btn-sm" title="تحديث الحالة">
                                                        <i class="las la-save"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Services -->
            <div class="nx-card animate d4">
                <div class="nx-card-hdr">
                    <h5><i class="las la-concierge-bell" style="color:var(--cyan);"></i> خدماتي المنشورة</h5>
                    <a href="/local_services/add_service.php" class="btn btn-success btn-sm">
                        <i class="las la-plus"></i> إضافة جديدة
                    </a>
                </div>
                <?php if (empty($services)): ?>
                    <div class="empty">
                        <div class="empty-icon"><i class="las la-box-open"></i></div>
                        <h4>لا توجد خدمات منشورة</h4>
                        <p>أضف خدمتك الأولى لتبدأ باستقبال الطلبات</p>
                        <a href="/local_services/add_service.php" class="btn btn-primary" style="margin-top:14px;padding:9px 18px;">
                            <i class="las la-plus-circle"></i> أضف خدمتك الأولى
                        </a>
                    </div>
                <?php else: ?>
                    <div class="nx-card-body">
                        <div class="svc-grid">
                            <?php foreach ($services as $s):
                                $has_img = !empty($s['image']);
                                $img_url = $has_img ? '/local_services/assets/uploads/' . htmlspecialchars($s['image']) : null;
                            ?>
                                <div class="svc-card">
                                    <?php if ($has_img): ?>
                                        <div class="svc-thumb" style="background-image:url('<?php echo $img_url; ?>');">
                                        <?php else: ?>
                                            <div class="svc-thumb-placeholder">
                                                <i class="las la-tools"></i>
                                            </div>
                                            <!-- Placeholder doesn't have closing here, handled below -->
                                            <?php if (false): ?><?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($has_img): ?>
                                            <span class="svc-status <?php echo $s['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $s['is_active'] ? 'نشطة' : 'موقوفة'; ?>
                                            </span>
                                        </div><!-- close svc-thumb -->
                                    <?php else: ?>
                                        <!-- placeholder already closed above via the div tag -->
                                    <?php endif; ?>

                                    <div class="svc-body">
                                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:6px;">
                                            <div class="svc-name"><?php echo htmlspecialchars($s['title']); ?></div>
                                            <?php if (!$has_img): ?>
                                                <span class="svc-status <?php echo $s['is_active'] ? 'active' : 'inactive'; ?>" style="position:static;flex-shrink:0;">
                                                    <?php echo $s['is_active'] ? 'نشطة' : 'موقوفة'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($s['city'])): ?>
                                            <div class="svc-meta">
                                                <i class="las la-map-marker"></i> <?php echo htmlspecialchars($s['city']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="svc-price">
                                            <?php echo number_format((float)$s['price'], 2); ?>
                                            <span>ريال سعودي</span>
                                        </div>
                                        <div class="svc-actions">
                                            <a href="/local_services/service_detail.php?id=<?php echo $s['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="las la-eye"></i> عرض
                                            </a>
                                            <a href="/local_services/edit_service.php?id=<?php echo $s['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="las la-edit"></i> تعديل
                                            </a>
                                            <form method="post" action="/local_services/delete_service.php" style="display:inline;">
                                                <input type="hidden" name="service_id" value="<?php echo $s['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('هل أنت متأكد من حذف هذه الخدمة؟');">
                                                    <i class="las la-trash"></i> حذف
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; /* end provider */ ?>

    </main>

    <script>
        // ── Progress bar animation ─────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.prog-fill').forEach(function (el) {
                var w = el.style.width;
                el.style.width = '0';
                requestAnimationFrame(function () {
                    setTimeout(function () { el.style.width = w; }, 200);
                });
            });
        });
    </script>
</body>

</html>