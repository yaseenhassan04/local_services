<?php
// service_detail.php — تفاصيل الخدمة
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/functions.php';

global $pdo;
$user      = getCurrentUser(); 
$is_logged = ($user !== false && $user !== null);
$user_id   = $is_logged ? $user['id'] : null;

// ── التحقق من معرّف الخدمة ──────────────────────────────────
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$service_id) {
    set_message("معرف الخدمة غير صالح.", "danger");
    header("Location: services.php"); exit();
}

// ── جلب بيانات الخدمة ───────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.description, s.price,
               s.image AS image_url, s.city,
               s.category_id, s.provider_id,
               u.full_name AS provider_name,
               u.phone     AS provider_phone,
               u.email     AS provider_email,
               c.name      AS category_name
        FROM services s
        LEFT JOIN users u ON s.provider_id = u.id
        LEFT JOIN categories c ON s.category_id = c.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        set_message("الخدمة المطلوبة غير موجودة أو تم إيقافها.", "danger");
        header("Location: services.php"); exit();
    }
} catch (PDOException $e) {
    error_log("Service detail error: " . $e->getMessage());
    set_message("حدث خطأ في جلب بيانات الخدمة.", "danger");
    header("Location: services.php"); exit();
}

$is_owner  = $is_logged && ($user_id == $service['provider_id']);
$can_order = $is_logged && isset($user['role']) && $user['role'] === 'client' && !$is_owner;

// ── التقييمات — يستخدم جدول reviews الصحيح ─────────────────
// ✅ إصلاح الخطأ: كان يستخدم جدول 'ratings' الغير موجود
$reviews = []; $review_count = 0; $average_rating = 0;
try {
    $stmt_rev = $pdo->prepare("
        SELECT r.rating, r.comment AS review_text,
               r.created_at, u.full_name AS client_name
        FROM reviews r
        LEFT JOIN orders o ON r.order_id = o.id
        LEFT JOIN users  u ON o.client_id = u.id
        WHERE o.service_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt_rev->execute([$service_id]);
    $reviews      = $stmt_rev->fetchAll(PDO::FETCH_ASSOC);
    $review_count = count($reviews);
    $average_rating = $review_count > 0
        ? round(array_sum(array_column($reviews, 'rating')) / $review_count, 1)
        : 0;
} catch (PDOException $e) {
    error_log("Reviews error: " . $e->getMessage());
}

// ── خدمات مشابهة ────────────────────────────────────────────
$related = [];
try {
    $stmt_rel = $pdo->prepare("
        SELECT s.id, s.title, s.price, s.image, u.full_name AS provider_name
        FROM services s JOIN users u ON s.provider_id = u.id
        WHERE s.category_id = ? AND s.id != ? AND s.is_active = 1
        ORDER BY RAND() LIMIT 4
    ");
    $stmt_rel->execute([$service['category_id'], $service_id]);
    $related = $stmt_rel->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $related = []; }

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo htmlspecialchars($service['title']); ?> | خدماتي</title>

<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

<style>
/* ============================================================
   XATO DARK THEME  —  خدماتي  —  Service Detail
============================================================ */
:root {
    --dark-bg:      #060818;
    --sidebar-bg:   #0e1726;
    --card-bg:      #0e1726;
    --card-border:  #1b2e4b;
    --header-bg:    #0e1726;
    --txt:          #e0e6ed;
    --txt-muted:    #888ea8;
    --txt-dark:     #bfc9d4;
    --primary:      #4361ee;
    --primary-lt:   rgba(67,97,238,.15);
    --success:      #00ab55;
    --success-lt:   rgba(0,171,85,.15);
    --warning:      #e2a03f;
    --warning-lt:   rgba(226,160,63,.15);
    --danger:       #e7515a;
    --danger-lt:    rgba(231,81,90,.15);
    --info:         #2196f3;
    --info-lt:      rgba(33,150,243,.15);
    --purple:       #805dca;
    --purple-lt:    rgba(128,93,202,.15);
    --radius:       10px;
    --radius-sm:    6px;
    --nav-h:        68px;
    --shadow:       0 4px 24px rgba(0,0,0,.45);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Tajawal', sans-serif;
    background: var(--dark-bg);
    color: var(--txt);
    font-size: 14px;
    direction: rtl;
    min-height: 100vh;
}

a { text-decoration: none; color: inherit; }

::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--sidebar-bg); }
::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 10px; }

/* ─── NAVBAR ─────────────────────────────────────────────── */
.kh-nav {
    position: fixed; top: 0; right: 0; left: 0; height: var(--nav-h);
    background: var(--header-bg);
    border-bottom: 1px solid var(--card-border);
    z-index: 1000;
    display: flex; align-items: center;
    padding: 0 24px; gap: 12px;
}

.nav-brand {
    display: flex; align-items: center; gap: 10px;
    color: var(--txt); font-size: 20px; font-weight: 900;
}

.nav-brand .dot {
    width: 9px; height: 9px; border-radius: 50%;
    background: var(--danger);
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%,100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(1.5); opacity: .6; }
}

.nav-brand span { color: var(--danger); }

.nav-spacer { flex: 1; }

.nav-icon-btn {
    width: 38px; height: 38px;
    background: var(--dark-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    color: var(--txt-muted); font-size: 18px;
    transition: all .2s; cursor: pointer;
}

.nav-icon-btn:hover { border-color: var(--primary); color: var(--primary); }

.nav-user {
    display: flex; align-items: center; gap: 10px;
    padding: 5px 10px; border-radius: var(--radius-sm);
    transition: background .2s; cursor: pointer;
}

.nav-user:hover { background: var(--primary-lt); }

.nav-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--purple));
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
}

.nav-user-name  { font-size: 13px; font-weight: 700; color: var(--txt);      display: block; }
.nav-user-role  { font-size: 11px; color: var(--txt-muted); display: block; }

.btn-nav-login {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: var(--radius-sm);
    background: var(--primary); color: #fff;
    font-family: 'Tajawal', sans-serif;
    font-size: 13px; font-weight: 700;
    border: none; cursor: pointer; transition: all .2s;
}

.btn-nav-login:hover { background: #3a56d4; transform: translateY(-1px); }

/* ─── BREADCRUMB ─────────────────────────────────────────── */
.breadcrumb-bar {
    background: var(--sidebar-bg);
    border-bottom: 1px solid var(--card-border);
    padding: 12px 0;
    margin-top: var(--nav-h);
}

.breadcrumb-inner {
    max-width: 1180px; margin: 0 auto; padding: 0 24px;
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--txt-muted);
}

.breadcrumb-inner a { color: var(--primary); font-weight: 500; }
.breadcrumb-inner a:hover { text-decoration: underline; }
.breadcrumb-inner i { font-size: 10px; }

/* ─── LAYOUT ─────────────────────────────────────────────── */
.page-body {
    max-width: 1180px; margin: 28px auto 70px;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1fr 330px;
    gap: 24px;
    align-items: start;
}

/* ─── CARD ───────────────────────────────────────────────── */
.kh-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 22px;
}
.kh-card:last-child { margin-bottom: 0; }

.kh-card-header {
    padding: 15px 22px;
    border-bottom: 1px solid var(--card-border);
    display: flex; align-items: center; gap: 10px;
}
.kh-card-header h5 {
    font-size: 15px; font-weight: 700;
    color: var(--txt); margin: 0;
}

.kh-card-body { padding: 24px; }

/* ─── SERVICE HERO ───────────────────────────────────────── */
.service-hero {
    position: relative;
    height: 360px;
    background: var(--sidebar-bg);
    overflow: hidden;
}

.service-hero img {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
}

.service-hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(6,8,24,.85) 0%, rgba(6,8,24,.15) 60%, transparent 100%);
}

.service-hero-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    font-size: 80px; color: var(--primary); opacity: .12;
}

/* Image category badge */
.img-badge {
    position: absolute; top: 18px; right: 18px;
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(6,8,24,.7); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.1);
    color: var(--txt); font-size: 12px; font-weight: 700;
    padding: 5px 14px; border-radius: 50px;
}

/* Rating overlay */
.img-rating {
    position: absolute; bottom: 18px; right: 18px;
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(6,8,24,.7); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.1);
    color: var(--warning); font-size: 13px; font-weight: 700;
    padding: 5px 14px; border-radius: 50px;
}

/* ─── META PILLS ─────────────────────────────────────────── */
.meta-pills {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap; margin-bottom: 18px;
}

.pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 50px;
    font-size: 12px; font-weight: 700;
    border-width: 1px; border-style: solid;
}

.pill-primary { background: var(--primary-lt); color: var(--primary); border-color: rgba(67,97,238,.3); }
.pill-warning { background: var(--warning-lt); color: var(--warning); border-color: rgba(226,160,63,.3); }
.pill-info    { background: var(--info-lt);    color: var(--info);    border-color: rgba(33,150,243,.3); }
.pill-muted   { background: rgba(136,142,168,.1); color: var(--txt-muted); border-color: var(--card-border); }

/* ─── DETAIL CONTENT ─────────────────────────────────────── */
.detail-title {
    font-size: clamp(20px, 3vw, 30px);
    font-weight: 900; color: var(--txt);
    line-height: 1.3; margin-bottom: 22px;
}

.section-lbl {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 700; color: var(--txt);
    padding-bottom: 10px; margin-bottom: 14px;
    border-bottom: 1px solid var(--card-border);
    text-transform: uppercase; letter-spacing: .5px;
}
.section-lbl i { color: var(--primary); font-size: 16px; }

.detail-desc {
    font-size: 14px; line-height: 1.9;
    color: var(--txt-dark); white-space: pre-wrap;
}

/* ─── STATS ROW ──────────────────────────────────────────── */
.stats-row {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 14px; margin-bottom: 22px;
}

.stat-box {
    background: var(--dark-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius-sm);
    padding: 16px; text-align: center;
}

.stat-box .sval {
    font-size: 22px; font-weight: 800;
    color: var(--txt); display: block;
    margin-bottom: 4px;
}
.stat-box .slbl {
    font-size: 11px; color: var(--txt-muted);
    text-transform: uppercase; letter-spacing: .5px;
}

/* ─── RATING SUMMARY ─────────────────────────────────────── */
.rating-summary {
    display: flex; align-items: center; gap: 24px;
    padding: 20px 0; margin-bottom: 4px;
}

.rating-big {
    text-align: center; flex-shrink: 0;
}

.rating-big .num {
    font-size: 52px; font-weight: 900;
    color: var(--txt); line-height: 1;
}

.rating-big .stars-row {
    display: flex; gap: 3px;
    justify-content: center; margin: 6px 0 4px;
    color: var(--warning); font-size: 16px;
}

.rating-big .stars-row .empty { color: rgba(226,160,63,.25); }
.rating-big .count { font-size: 12px; color: var(--txt-muted); }

.rating-bars { flex: 1; }

.bar-row {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 6px;
}

.bar-row .bar-lbl {
    font-size: 11px; color: var(--txt-muted);
    width: 40px; text-align: left; flex-shrink: 0;
}

.bar-row .bar-track {
    flex: 1; height: 5px;
    background: var(--card-border); border-radius: 3px; overflow: hidden;
}

.bar-row .bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--warning), #f5c518);
    border-radius: 3px;
    transition: width .6s ease;
}

/* ─── REVIEWS ────────────────────────────────────────────── */
.review-item {
    padding: 18px 0;
    border-bottom: 1px solid var(--card-border);
}
.review-item:first-child { padding-top: 0; }
.review-item:last-child  { border-bottom: none; padding-bottom: 0; }

.review-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; margin-bottom: 10px; gap: 10px;
}

.reviewer-info { display: flex; align-items: center; gap: 12px; }

.rev-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--purple));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 16px; font-weight: 700; flex-shrink: 0;
}

.rev-name { font-size: 14px; font-weight: 700; color: var(--txt); }
.rev-date { font-size: 12px; color: var(--txt-muted); margin-top: 2px; }

.rev-stars {
    display: flex; gap: 2px;
    color: var(--warning); font-size: 14px; flex-shrink: 0;
}
.rev-stars .empty { color: rgba(226,160,63,.2); }

.rev-text {
    font-size: 13px; color: var(--txt-dark);
    line-height: 1.7;
}

.no-reviews {
    text-align: center; padding: 48px 20px;
}
.no-reviews .nr-icon {
    font-size: 48px; color: var(--primary); opacity: .2;
    display: block; margin-bottom: 14px;
}
.no-reviews p { font-size: 14px; color: var(--txt-muted); }

/* ─── SIDEBAR ────────────────────────────────────────────── */
.sidebar-sticky {
    position: sticky; top: calc(var(--nav-h) + 20px);
    display: flex; flex-direction: column; gap: 20px;
}

/* Pricing Card */
.price-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    overflow: hidden;
}

.price-card-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
    padding: 26px 22px; text-align: center; position: relative;
    overflow: hidden;
}

.price-card-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
    background-size: 22px 22px;
}

.price-val {
    position: relative;
    font-size: 44px; font-weight: 900;
    color: #fff; line-height: 1;
}

.price-cur {
    font-size: 16px; font-weight: 500;
    color: rgba(255,255,255,.7);
}

.price-lbl {
    font-size: 12px; color: rgba(255,255,255,.55);
    margin-top: 6px;
}

.price-card-body {
    padding: 20px;
    display: flex; flex-direction: column; gap: 10px;
}

/* Features list */
.price-features {
    list-style: none;
    margin-bottom: 6px;
}

.price-features li {
    display: flex; align-items: center; gap: 9px;
    font-size: 13px; color: var(--txt-dark);
    padding: 7px 0;
    border-bottom: 1px solid var(--card-border);
}

.price-features li:last-child { border-bottom: none; }

.price-features li i {
    color: var(--success); font-size: 16px; flex-shrink: 0;
}

/* Action Buttons */
.btn-order {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 14px;
    background: var(--success); color: #fff;
    border: none; border-radius: var(--radius-sm);
    font-family: 'Tajawal', sans-serif;
    font-size: 15px; font-weight: 800;
    cursor: pointer; transition: all .25s;
    box-shadow: 0 4px 20px rgba(0,171,85,.3);
}
.btn-order:hover { background: #009b4e; transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,171,85,.4); color: #fff; }

.btn-login-cta {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 13px;
    background: transparent; border: 1.5px solid var(--primary);
    color: var(--primary); border-radius: var(--radius-sm);
    font-family: 'Tajawal', sans-serif; font-size: 14px; font-weight: 700;
    transition: all .2s; cursor: pointer;
}
.btn-login-cta:hover { background: var(--primary); color: #fff; }

.owner-notice {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 14px;
    background: var(--warning-lt);
    border: 1px solid rgba(226,160,63,.3);
    border-radius: var(--radius-sm);
    color: var(--warning); font-size: 13px; font-weight: 600;
}

.secure-note {
    display: flex; align-items: center; justify-content: center; gap: 5px;
    font-size: 12px; color: var(--txt-muted);
}

/* Provider Card */
.provider-avatar {
    width: 50px; height: 50px; border-radius: 12px;
    background: linear-gradient(135deg, var(--purple), var(--primary));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 22px; font-weight: 800; flex-shrink: 0;
}

.provider-header { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
.provider-name { font-size: 15px; font-weight: 700; color: var(--txt); }
.provider-role { font-size: 12px; color: var(--success); font-weight: 600; }

.provider-row {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 0; border-bottom: 1px solid var(--card-border);
    font-size: 13px; color: var(--txt-dark);
}
.provider-row:last-child { border-bottom: none; padding-bottom: 0; }

.p-ico {
    width: 30px; height: 30px; border-radius: var(--radius-sm);
    background: var(--primary-lt);
    display: flex; align-items: center; justify-content: center;
    color: var(--primary); font-size: 14px; flex-shrink: 0;
}

/* Related */
.related-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid var(--card-border);
    transition: padding .2s;
}
.related-item:last-child { border-bottom: none; padding-bottom: 0; }
.related-item:hover { padding-right: 6px; }

.related-thumb {
    width: 54px; height: 54px; border-radius: var(--radius-sm);
    background: var(--primary-lt); flex-shrink: 0;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    color: var(--primary); font-size: 24px; opacity: .6;
}
.related-thumb img { width: 100%; height: 100%; object-fit: cover; }

.related-title {
    font-size: 13px; font-weight: 600; color: var(--txt);
    margin-bottom: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.related-price { font-size: 12px; color: var(--success); font-weight: 700; }

/* ─── PAYMENT MODAL ──────────────────────────────────────── */
.overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(6,8,24,.85);
    backdrop-filter: blur(6px);
    z-index: 2000;
    align-items: center; justify-content: center;
}
.overlay.open { display: flex; }

.modal-box {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    width: 92%; max-width: 500px;
    max-height: 90vh; overflow-y: auto;
    padding: 30px;
    position: relative;
    direction: rtl;
    animation: modal-in .25s cubic-bezier(.4,0,.2,1);
}

@keyframes modal-in {
    from { opacity: 0; transform: translateY(24px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-close-btn {
    position: absolute; top: 14px; left: 14px;
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--dark-bg); border: 1px solid var(--card-border);
    cursor: pointer; font-size: 14px; color: var(--txt-muted);
    display: flex; align-items: center; justify-content: center;
    transition: all .2s;
}
.modal-close-btn:hover { border-color: var(--danger); color: var(--danger); }

.modal-title { font-size: 20px; font-weight: 800; color: var(--txt); margin-bottom: 4px; }
.modal-sub   { font-size: 13px; color: var(--txt-muted); margin-bottom: 24px; }

/* Payment methods grid */
.pay-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 10px; margin-bottom: 22px;
}

.pay-btn {
    border: 1px solid var(--card-border);
    border-radius: var(--radius-sm);
    padding: 14px 8px;
    background: var(--dark-bg);
    cursor: pointer; transition: all .2s;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    font-family: 'Tajawal', sans-serif; font-size: 12px; font-weight: 700;
    color: var(--txt-muted);
}

.pay-btn .pay-icon {
    width: 40px; height: 40px; border-radius: var(--radius-sm);
    background: var(--primary-lt); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; transition: all .2s;
}

.pay-btn:hover, .pay-btn.active {
    border-color: var(--primary);
    background: var(--primary-lt);
    color: var(--primary);
}
.pay-btn.active .pay-icon { background: var(--primary); color: #fff; }

/* Form fields */
.pay-form { display: none; }
.pay-form.show { display: block; }

.f-group { margin-bottom: 16px; }

.f-label {
    display: block; font-size: 12px; font-weight: 700;
    color: var(--txt-muted); margin-bottom: 7px;
    text-transform: uppercase; letter-spacing: .5px;
}

.f-input {
    width: 100%; padding: 11px 14px;
    background: var(--dark-bg); border: 1px solid var(--card-border);
    border-radius: var(--radius-sm);
    font-family: 'Tajawal', sans-serif; font-size: 14px;
    color: var(--txt); outline: none; transition: border-color .2s;
}
.f-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67,97,238,.12); }
.f-input::placeholder { color: #3a4a6b; }

.btn-pay {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, var(--primary), var(--purple));
    color: #fff; border: none; border-radius: var(--radius-sm);
    font-family: 'Tajawal', sans-serif; font-size: 15px; font-weight: 800;
    cursor: pointer; transition: all .2s;
    box-shadow: 0 4px 20px rgba(67,97,238,.35);
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-pay:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(67,97,238,.5); }
.btn-pay:disabled { opacity: .5; cursor: not-allowed; transform: none; }

/* Success modal */
.success-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(6,8,24,.88); backdrop-filter: blur(8px);
    z-index: 3000;
    align-items: center; justify-content: center;
}
.success-overlay.open { display: flex; }

.success-box {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px; padding: 50px 40px; text-align: center;
    max-width: 380px; width: 90%;
    animation: modal-in .3s ease;
}

.success-circle {
    width: 80px; height: 80px; border-radius: 50%;
    background: var(--success-lt); border: 2px solid rgba(0,171,85,.3);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px; font-size: 36px; color: var(--success);
    animation: pop .4s cubic-bezier(.34,1.56,.64,1) .1s both;
}

@keyframes pop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

.success-box h3 { font-size: 22px; font-weight: 800; color: var(--txt); margin-bottom: 10px; }
.success-box p  { font-size: 14px; color: var(--txt-muted); line-height: 1.6; }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 920px) {
    .page-body { grid-template-columns: 1fr; }
    .sidebar-sticky { position: static; }
    .service-hero { height: 280px; }
}

@media (max-width: 600px) {
    .page-body { padding: 0 14px; margin-top: 20px; }
    .kh-card-body { padding: 16px; }
    .stats-row { grid-template-columns: 1fr 1fr; }
    .pay-grid { grid-template-columns: repeat(2, 1fr); }
    .rating-summary { flex-direction: column; gap: 16px; }
}
</style>
</head>
<body>

<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="kh-nav">
    <a href="/local_services/index.php" class="nav-brand">
        <div class="dot"></div>
        خدماتي<span>.</span>
    </a>

    <div class="nav-spacer"></div>

    <a href="/local_services/services.php" class="nav-icon-btn" title="الخدمات">
        <i class="las la-th-large"></i>
    </a>

    <?php if ($is_logged): ?>
        <a href="/local_services/dashboard.php" class="nav-icon-btn" title="لوحة التحكم">
            <i class="las la-clipboard-list"></i>
        </a>
        <a href="/local_services/profile.php" class="nav-user">
            <div class="nav-avatar"><?php echo mb_substr($user['full_name'], 0, 1); ?></div>
            <div>
                <span class="nav-user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                <span class="nav-user-role"><?php echo $user['role'] === 'client' ? 'عميل' : 'مزود خدمة'; ?></span>
            </div>
        </a>
    <?php else: ?>
        <a href="/local_services/login.php" class="btn-nav-login">
            <i class="las la-sign-in-alt"></i> دخول
        </a>
    <?php endif; ?>
</nav>

<!-- ═══════════════ BREADCRUMB ═══════════════ -->
<div class="breadcrumb-bar">
    <div class="breadcrumb-inner">
        <a href="/local_services/index.php"><i class="las la-home"></i> الرئيسية</a>
        <i class="las la-angle-left"></i>
        <a href="/local_services/services.php">الخدمات</a>
        <i class="las la-angle-left"></i>
        <span><?php echo htmlspecialchars(mb_substr($service['title'], 0, 50)); ?><?php echo mb_strlen($service['title']) > 50 ? '...' : ''; ?></span>
    </div>
</div>

<!-- ═══════════════ MAIN BODY ═══════════════ -->
<div class="page-body">

    <!-- ══ MAIN COLUMN ══ -->
    <div>

        <!-- Service Image + Info -->
        <div class="kh-card">
            <div class="service-hero">
                <?php if (!empty($service['image_url'])): ?>
                    <img src="/local_services/assets/uploads/<?php echo htmlspecialchars($service['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($service['title']); ?>">
                <?php else: ?>
                    <div class="service-hero-placeholder"><i class="las la-tools"></i></div>
                <?php endif; ?>
                <div class="service-hero-overlay"></div>

                <!-- Category badge over image -->
                <span class="img-badge">
                    <i class="las la-tag"></i>
                    <?php echo htmlspecialchars($service['category_name']); ?>
                </span>

                <!-- Rating badge over image -->
                <?php if ($review_count > 0): ?>
                <span class="img-rating">
                    <i class="las la-star"></i>
                    <?php echo $average_rating; ?> (<?php echo $review_count; ?>)
                </span>
                <?php endif; ?>
            </div>

            <div class="kh-card-body">

                <!-- Meta pills -->
                <div class="meta-pills">
                    <?php if (!empty($service['city'])): ?>
                    <span class="pill pill-info">
                        <i class="las la-map-marker-alt"></i>
                        <?php echo htmlspecialchars($service['city']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($review_count > 0): ?>
                    <span class="pill pill-warning">
                        <i class="las la-star"></i>
                        <?php echo $average_rating; ?> / 5 — <?php echo $review_count; ?> تقييم
                    </span>
                    <?php else: ?>
                    <span class="pill pill-muted">
                        <i class="las la-star"></i>
                        لا توجد تقييمات بعد
                    </span>
                    <?php endif; ?>
                    <span class="pill pill-muted">
                        <i class="las la-check-circle"></i> خدمة موثّقة
                    </span>
                </div>

                <!-- Title -->
                <h1 class="detail-title"><?php echo htmlspecialchars($service['title']); ?></h1>

                <!-- Quick stats -->
                <div class="stats-row">
                    <div class="stat-box">
                        <span class="sval" style="color:var(--warning);"><?php echo $average_rating > 0 ? $average_rating : '—'; ?></span>
                        <span class="slbl">التقييم</span>
                    </div>
                    <div class="stat-box">
                        <span class="sval" style="color:var(--primary);"><?php echo $review_count; ?></span>
                        <span class="slbl">تقييم</span>
                    </div>
                    <div class="stat-box">
                        <span class="sval" style="color:var(--success);"><?php echo number_format($service['price'], 0); ?></span>
                        <span class="slbl">ر.س</span>
                    </div>
                </div>

                <!-- Description -->
                <div class="section-lbl">
                    <i class="las la-align-right"></i>
                    وصف الخدمة
                </div>
                <p class="detail-desc"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>

            </div>
        </div>

        <!-- Reviews -->
        <div class="kh-card">
            <div class="kh-card-header">
                <i class="las la-comments" style="color:var(--primary);font-size:18px;"></i>
                <h5>آراء العملاء</h5>
                <?php if ($review_count > 0): ?>
                <span class="pill pill-warning" style="margin-right:auto;font-size:11px;">
                    <?php echo $review_count; ?> تقييم
                </span>
                <?php endif; ?>
            </div>
            <div class="kh-card-body">

                <?php if ($review_count > 0): ?>

                <!-- Rating Summary Bar -->
                <div class="rating-summary">
                    <div class="rating-big">
                        <div class="num"><?php echo $average_rating; ?></div>
                        <div class="stars-row">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="las la-star<?php echo $i > $average_rating ? ' empty' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="count">من 5</div>
                    </div>
                    <div class="rating-bars">
                        <?php
                        $dist = array_fill(1, 5, 0);
                        foreach ($reviews as $r) {
                            $k = max(1, min(5, (int)$r['rating']));
                            $dist[$k]++;
                        }
                        for ($s = 5; $s >= 1; $s--):
                            $pct = $review_count > 0 ? round($dist[$s] / $review_count * 100) : 0;
                        ?>
                        <div class="bar-row">
                            <span class="bar-lbl"><?php echo $s; ?> ★</span>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div>
                            </div>
                            <span style="font-size:11px;color:var(--txt-muted);width:28px;text-align:right;"><?php echo $dist[$s]; ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div style="border-top:1px solid var(--card-border);margin-bottom:4px;"></div>

                <?php foreach ($reviews as $rev):
                    $initial = mb_substr($rev['client_name'] ?? '؟', 0, 1);
                    $stars   = max(1, min(5, (int)$rev['rating']));
                ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="rev-avatar"><?php echo $initial; ?></div>
                            <div>
                                <div class="rev-name"><?php echo htmlspecialchars($rev['client_name'] ?? 'مستخدم'); ?></div>
                                <div class="rev-date"><?php echo date('Y/m/d', strtotime($rev['created_at'])); ?></div>
                            </div>
                        </div>
                        <div class="rev-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="las la-star<?php echo $i > $stars ? ' empty' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if (!empty($rev['review_text'])): ?>
                    <p class="rev-text"><?php echo nl2br(htmlspecialchars($rev['review_text'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php else: ?>
                <div class="no-reviews">
                    <i class="las la-comment-slash nr-icon"></i>
                    <p>لا توجد تقييمات بعد — كن أول من يجرّب هذه الخدمة!</p>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ══ SIDEBAR ══ -->
    <div>
        <div class="sidebar-sticky">

            <!-- Pricing -->
            <div class="price-card">
                <div class="price-card-header">
                    <div class="price-val">
                        <span class="price-cur">ر.س</span>
                        <?php echo number_format($service['price'], 2); ?>
                    </div>
                    <div class="price-lbl">سعر الخدمة الأساسي</div>
                </div>
                <div class="price-card-body">
                    <ul class="price-features">
                        <li><i class="las la-check-circle"></i> دفع آمن ومضمون</li>
                        <li><i class="las la-check-circle"></i> تواصل مباشر مع المزود</li>
                        <li><i class="las la-check-circle"></i> حماية كاملة لحقوقك</li>
                        <li><i class="las la-headset"></i> دعم على مدار الساعة</li>
                    </ul>

                    <?php if ($can_order): ?>
                        <button id="openPayModal" class="btn-order">
                            <i class="las la-bolt"></i>
                            اطلب الخدمة الآن
                        </button>
                    <?php elseif ($is_owner): ?>
                        <div class="owner-notice">
                            <i class="las la-info-circle"></i>
                            لا يمكنك طلب خدمتك الخاصة
                        </div>
                    <?php else: ?>
                        <a href="/local_services/login.php" class="btn-login-cta">
                            <i class="las la-sign-in-alt"></i>
                            سجّل دخولك للطلب
                        </a>
                    <?php endif; ?>

                    <div class="secure-note">
                        <i class="las la-shield-alt" style="color:var(--success);"></i>
                        الدفع محمي ومشفّر بالكامل
                    </div>
                </div>
            </div>

            <!-- Provider -->
            <div class="kh-card" style="margin-bottom:0;">
                <div class="kh-card-header">
                    <i class="las la-user-tie" style="color:var(--purple);font-size:18px;"></i>
                    <h5>معلومات المزود</h5>
                </div>
                <div class="kh-card-body">
                    <div class="provider-header">
                        <div class="provider-avatar">
                            <?php echo mb_substr($service['provider_name'] ?? '؟', 0, 1); ?>
                        </div>
                        <div>
                            <div class="provider-name"><?php echo htmlspecialchars($service['provider_name'] ?? 'غير معروف'); ?></div>
                            <div class="provider-role">
                                <i class="las la-check-circle"></i> مزود معتمد
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($service['provider_phone'])): ?>
                    <div class="provider-row">
                        <div class="p-ico"><i class="las la-phone"></i></div>
                        <span><?php echo htmlspecialchars($service['provider_phone']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="provider-row">
                        <div class="p-ico"><i class="las la-star"></i></div>
                        <span>
                            <?php echo $average_rating > 0
                                ? $average_rating . ' / 5 (' . $review_count . ' تقييم)'
                                : 'لم يُقيَّم بعد'; ?>
                        </span>
                    </div>

                    <?php if (!empty($service['city'])): ?>
                    <div class="provider-row">
                        <div class="p-ico"><i class="las la-map-marker"></i></div>
                        <span><?php echo htmlspecialchars($service['city']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="provider-row">
                        <div class="p-ico"><i class="las la-tag"></i></div>
                        <span><?php echo htmlspecialchars($service['category_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Related -->
            <?php if (!empty($related)): ?>
            <div class="kh-card" style="margin-bottom:0;">
                <div class="kh-card-header">
                    <i class="las la-layer-group" style="color:var(--info);font-size:18px;"></i>
                    <h5>خدمات مشابهة</h5>
                </div>
                <div class="kh-card-body">
                    <?php foreach ($related as $rel):
                        $rel_img = !empty($rel['image'])
                            ? '/local_services/assets/uploads/' . htmlspecialchars($rel['image'])
                            : null;
                    ?>
                    <a href="/local_services/service_detail.php?id=<?php echo $rel['id']; ?>" class="related-item">
                        <div class="related-thumb">
                            <?php if ($rel_img): ?>
                                <img src="<?php echo $rel_img; ?>" alt="">
                            <?php else: ?>
                                <i class="las la-tools"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="related-title"><?php echo htmlspecialchars($rel['title']); ?></div>
                            <div class="related-price"><?php echo number_format($rel['price'], 2); ?> ر.س</div>
                        </div>
                        <i class="las la-angle-left" style="color:var(--txt-muted);font-size:12px;flex-shrink:0;"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div><!-- /page-body -->

<!-- ═══════════════ PAYMENT MODAL ═══════════════ -->
<div class="overlay" id="payOverlay">
    <div class="modal-box">
        <button class="modal-close-btn" id="closePayModal"><i class="las la-times"></i></button>

        <h3 class="modal-title">اختر طريقة الدفع</h3>
        <p class="modal-sub">
            <?php echo htmlspecialchars(mb_substr($service['title'], 0, 45)); ?> —
            <strong style="color:var(--success);">
                <?php echo number_format($service['price'], 2); ?> ر.س
            </strong>
        </p>

        <!-- Payment method selector -->
        <div class="pay-grid">
            <button class="pay-btn" data-method="jawwal_pay">
                <div class="pay-icon"><i class="las la-mobile"></i></div>
                جوال باي
            </button>
            <button class="pay-btn" data-method="paypal">
                <div class="pay-icon"><i class="lab la-paypal"></i></div>
                باي بال
            </button>
            <button class="pay-btn" data-method="bank_transfer">
                <div class="pay-icon"><i class="las la-university"></i></div>
                تحويل بنكي
            </button>
        </div>

        <!-- Form (shown after selecting method) -->
        <form id="paymentForm" class="pay-form"
              action="/local_services/actions/place_order.php" method="POST">
            <input type="hidden" name="service_id"      value="<?php echo $service_id; ?>">
            <input type="hidden" name="provider_id"     value="<?php echo $service['provider_id']; ?>">
            <input type="hidden" name="csrf_token"      value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="amount"          value="<?php echo htmlspecialchars($service['price']); ?>">
            <input type="hidden" name="payment_method"  id="payMethodInput" value="">

            <div class="f-group">
                <label class="f-label">رقم الحساب / الهاتف</label>
                <input type="text" name="account_number" class="f-input"
                       placeholder="أدخل رقم الحساب أو الهاتف" required>
            </div>

            <div class="f-group">
                <label class="f-label">متطلبات الطلب وملاحظاتك</label>
                <textarea name="details" rows="3" class="f-input"
                          placeholder="الموقع، وقت التنفيذ، أي تفاصيل مهمة..." required></textarea>
            </div>

            <button class="btn-pay" id="submitPayBtn" type="submit" disabled>
                <i class="las la-lock"></i>
                ادفع الآن — <?php echo number_format($service['price'], 2); ?> ر.س
            </button>
        </form>
    </div>
</div>

<!-- ═══════════════ SUCCESS MODAL ═══════════════ -->
<div class="success-overlay" id="successOverlay">
    <div class="success-box">
        <div class="success-circle"><i class="las la-check"></i></div>
        <h3>تم الطلب بنجاح! 🎉</h3>
        <p>سيتواصل معك المزود قريباً لتحديد موعد التنفيذ.<br>يمكنك متابعة طلبك من لوحة التحكم.</p>
    </div>
</div>

<!-- ═══════════════ SCRIPTS ═══════════════ -->
<script>
(function () {
    'use strict';

    const payOverlay    = document.getElementById('payOverlay');
    const successOvrly  = document.getElementById('successOverlay');
    const openBtn       = document.getElementById('openPayModal');
    const closeBtn      = document.getElementById('closePayModal');
    const payBtns       = document.querySelectorAll('.pay-btn');
    const payForm       = document.getElementById('paymentForm');
    const submitBtn     = document.getElementById('submitPayBtn');
    const methodInput   = document.getElementById('payMethodInput');

    // Open / Close modal
    openBtn?.addEventListener('click', () => payOverlay.classList.add('open'));
    closeBtn?.addEventListener('click', () => payOverlay.classList.remove('open'));
    payOverlay?.addEventListener('click', e => {
        if (e.target === payOverlay) payOverlay.classList.remove('open');
    });

    // Select payment method
    payBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            payBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            methodInput.value = this.dataset.method;
            payForm.classList.add('show');
            submitBtn.disabled = false;
            submitBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    // Submit via fetch
    payForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="las la-spinner" style="animation:spin 1s linear infinite"></i> جاري المعالجة...';

        fetch(this.action, { method: 'POST', body: new FormData(this) })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    payOverlay.classList.remove('open');
                    successOvrly.classList.add('open');
                    setTimeout(() => {
                        successOvrly.classList.remove('open');
                        window.location.href = '/local_services/client_dashboard.php';
                    }, 3200);
                } else {
                    alert('خطأ: ' + (data.message || 'حدث خطأ غير متوقع'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="las la-lock"></i> ادفع الآن — <?php echo number_format($service['price'], 2); ?> ر.س';
                }
            })
            .catch(() => {
                alert('خطأ في الاتصال، يرجى المحاولة مجدداً.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="las la-lock"></i> ادفع الآن — <?php echo number_format($service['price'], 2); ?> ر.س';
            });
    });

    // CSS spinner keyframe via JS (avoids style tag)
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
})();
</script>

</body>
</html>