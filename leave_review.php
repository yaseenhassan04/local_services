<?php
// ============================================================
// leave_review.php — صفحة تقييم الخدمة (العميل)
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

global $pdo;

if (!$pdo) {
    die("خطأ: لم يتم الاتصال بقاعدة البيانات بشكل صحيح.");
}

// ── 1. التحقق من تسجيل الدخول والدور ──────────────────────
check_login('client');

$user    = getCurrentUser();
$user_id = $user['id'];

// ── 2. التحقق من صحة order_id ──────────────────────────────
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    set_message("رقم الطلب غير صالح.", "danger");
    header("Location: /local_services/client_dashboard.php");
    exit;
}

$csrf_token = generateCsrfToken();

// ── 3. جلب الطلب والتحقق من الصلاحية ─────────────────────
$stmt = $pdo->prepare("
    SELECT o.id, o.status, o.service_id, o.provider_id,
           s.title AS service_title,
           u.full_name AS provider_name
    FROM orders o
    JOIN services s ON o.service_id  = s.id
    JOIN users   u ON o.provider_id  = u.id
    WHERE o.id = ? AND o.client_id = ?
    LIMIT 1
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_message("الطلب غير موجود أو لا تملك صلاحية الوصول إليه.", "danger");
    header("Location: /local_services/client_dashboard.php");
    exit;
}

// ── 4. الطلب يجب أن يكون مكتملاً ──────────────────────────
if ($order['status'] !== 'completed') {
    set_message("يمكن تقييم الطلبات المكتملة فقط.", "warning");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;
}

// ── 5. منع التقييم المزدوج ─────────────────────────────────
$stmt_chk = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ? LIMIT 1");
$stmt_chk->execute([$order_id]);
if ($stmt_chk->fetch()) {
    set_message("لقد قيّمت هذا الطلب مسبقاً.", "info");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;
}

// ── 6. استخراج الحرف الأول من الاسم ───────────────────────
$user_initial     = mb_substr($user['full_name'] ?? $user['name'] ?? 'م',          0, 1, 'UTF-8');
$provider_initial = mb_substr($order['provider_name'] ?? 'م',     0, 1, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييم الخدمة | خدماتي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">

    <style>
        /* ══════════════════════════════════════════
           VARIABLES & RESET
        ══════════════════════════════════════════ */
        :root {
            --bg: #060818;
            --surface: #0e1726;
            --border: #1b2e4b;
            --text: #e0e6ed;
            --muted: #888ea8;
            --primary: #4361ee;
            --pri-lt: rgba(67, 97, 238, .14);
            --success: #00ab55;
            --suc-lt: rgba(0, 171, 85, .13);
            --warning: #e2a03f;
            --war-lt: rgba(226, 160, 63, .13);
            --danger: #e7515a;
            --dan-lt: rgba(231, 81, 90, .13);
            --info: #2196f3;
            --inf-lt: rgba(33, 150, 243, .13);
            --purple: #805dca;
            --nav-h: 64px;
            --radius: 10px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg);
            color: var(--text);
            direction: rtl;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: var(--surface);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        /* ══ NAV ══════════════════════════════════ */
        .top-nav {
            position: sticky;
            top: 0;
            z-index: 200;
            height: var(--nav-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 12px;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 17px;
            font-weight: 800;
        }

        .nav-brand .ico {
            width: 34px;
            height: 34px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
            flex-shrink: 0;
        }

        .nav-spacer {
            flex: 1;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            transition: all .2s;
        }

        .nav-btn:hover {
            background: var(--pri-lt);
            color: var(--primary);
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background .2s;
            cursor: pointer;
        }

        .nav-user:hover {
            background: var(--pri-lt);
        }

        .nav-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .nav-uname {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .nav-urole {
            font-size: 11px;
            color: var(--muted);
        }

        /* ══ PAGE WRAP ════════════════════════════ */
        .page-wrap {
            max-width: 760px;
            margin: 32px auto 60px;
            padding: 0 20px;
        }

        /* ══ BREADCRUMB ═══════════════════════════ */
        .bc {
            display: flex;
            align-items: center;
            gap: 6px;
            list-style: none;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .bc li {
            font-size: 12px;
            color: var(--muted);
        }

        .bc li a {
            color: var(--primary);
        }

        .bc li a:hover {
            text-decoration: underline;
        }

        .bc li:not(:last-child)::after {
            content: '/';
            margin-right: 6px;
            color: var(--border);
        }

        /* ══ PAGE TITLE ═══════════════════════════ */
        .page-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title i {
            color: var(--warning);
        }

        /* ══ CARD ═════════════════════════════════ */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-hdr {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .card-hdr h5 {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }

        .card-hdr i {
            font-size: 17px;
            color: var(--primary);
        }

        .card-body {
            padding: 22px 20px;
        }

        /* ══ ORDER INFO GRID ══════════════════════ */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
        }

        .info-value.accent {
            color: var(--primary);
        }

        /* ── Provider mini avatar ── */
        .prov-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prov-chip {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--pri-lt);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            flex-shrink: 0;
        }

        /* ── Status badge ── */
        .badge-completed {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--suc-lt);
            color: var(--success);
            font-size: 12px;
            font-weight: 700;
        }

        .badge-completed::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* ══ STAR RATING ══════════════════════════ */
        .rating-wrap {
            text-align: center;
            padding: 8px 0 20px;
        }

        .rating-title {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
        }

        /*
         * النجوم: row-reverse → النجمة 5 في اليسار، 1 في اليمين.
         * hover على label يلوّن هذه النجمة وكل ما يليها (الأكبر رقماً).
         */
        .stars-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 6px;
        }

        .stars-input input {
            display: none;
        }

        .stars-input label {
            font-size: 46px;
            line-height: 1;
            color: var(--border);
            cursor: pointer;
            transition: color .15s, transform .15s;
            user-select: none;
        }

        /* hover: هذه النجمة + كل الأصغر (= siblings بعدها في DOM بسبب row-reverse) */
        .stars-input label:hover,
        .stars-input label:hover~label {
            color: var(--warning);
            transform: scale(1.18);
        }

        /* checked: كل النجوم من المحددة فأقل */
        .stars-input input:checked~label {
            color: var(--warning);
        }

        /* إعادة تلوين النجوم الأعلى من المحددة */
        .stars-input input:checked+label~label {
            color: var(--border);
        }

        .rating-hint {
            margin-top: 16px;
            min-height: 28px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hint-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            background: var(--war-lt);
            color: var(--warning);
            transition: all .2s;
        }

        .hint-pill.danger {
            background: var(--dan-lt);
            color: var(--danger);
        }

        /* ══ DIVIDER ══════════════════════════════ */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 22px 0;
        }

        /* ══ TEXTAREA ═════════════════════════════ */
        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .field-label i {
            color: var(--primary);
            font-size: 16px;
        }

        .field-label .optional {
            font-weight: 400;
            font-size: 12px;
            color: var(--muted);
        }

        .review-textarea {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: var(--radius);
            padding: 13px 15px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            line-height: 1.7;
            resize: vertical;
            min-height: 130px;
            transition: border-color .2s, box-shadow .2s;
        }

        .review-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, .1);
        }

        .review-textarea::placeholder {
            color: #3a4a6b;
        }

        .char-row {
            display: flex;
            justify-content: space-between;
            margin-top: 7px;
            font-size: 12px;
            color: var(--muted);
        }

        .char-row span {
            color: var(--primary);
            font-weight: 700;
        }

        /* ══ ACTIONS ══════════════════════════════ */
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 22px;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 700;
            font-family: 'Tajawal', sans-serif;
            border: none;
            cursor: pointer;
            transition: all .22s;
            white-space: nowrap;
        }

        .btn-submit {
            flex: 1;
            justify-content: center;
            background: var(--success);
            color: #fff;
            box-shadow: 0 4px 14px rgba(0, 171, 85, .3);
        }

        .btn-submit:hover:not(:disabled) {
            background: #009b4e;
            box-shadow: 0 6px 20px rgba(0, 171, 85, .45);
            transform: translateY(-2px);
        }

        .btn-submit:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
        }

        .btn-ghost:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--pri-lt);
        }

        /* ══ FLASH ALERTS ═════════════════════════ */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 18px;
        }

        .alert i {
            font-size: 18px;
        }

        .alert-danger {
            background: var(--dan-lt);
            color: var(--danger);
            border: 1px solid rgba(231, 81, 90, .25);
        }

        .alert-success {
            background: var(--suc-lt);
            color: var(--success);
            border: 1px solid rgba(0, 171, 85, .25);
        }

        .alert-info {
            background: var(--inf-lt);
            color: var(--info);
            border: 1px solid rgba(33, 150, 243, .25);
        }

        .alert-warning {
            background: var(--war-lt);
            color: var(--warning);
            border: 1px solid rgba(226, 160, 63, .25);
        }

        /* ══ RESPONSIVE ═══════════════════════════ */
        @media (max-width: 600px) {
            .stars-input label {
                font-size: 36px;
            }

            .actions {
                flex-direction: column;
            }

            .btn-submit {
                flex: none;
            }

            .nav-uname,
            .nav-urole {
                display: none;
            }
        }
    </style>
</head>

<body>

    <!-- ══ NAV ══════════════════════════════════════════════════ -->
    <nav class="top-nav">
        <a href="/local_services/index.php" class="nav-brand">
            <div class="ico"><i class="las la-layer-group"></i></div>
            خدماتي
        </a>

        <div class="nav-spacer"></div>

        <a href="/local_services/client_dashboard.php" class="nav-btn">
            <i class="las la-clipboard-list"></i> طلباتي
        </a>
        <a href="/local_services/services.php" class="nav-btn">
            <i class="las la-th-large"></i> الخدمات
        </a>

        <a href="/local_services/profile.php" class="nav-user">
            <div class="nav-avatar"><?php echo $user_initial; ?></div>
            <div>
                <div class="nav-uname"><?php echo htmlspecialchars($user['full_name'] ?? $user['name'] ?? 'مستخدم'); ?></div>
                <div class="nav-urole">عميل</div>
            </div>
        </a>
    </nav>

    <!-- ══ CONTENT ═══════════════════════════════════════════════ -->
    <div class="page-wrap">

        <!-- Breadcrumb -->
        <ul class="bc">
            <li><a href="/local_services/index.php">الرئيسية</a></li>
            <li><a href="/local_services/client_dashboard.php">طلباتي</a></li>
            <li><a href="/local_services/view_order.php?id=<?php echo $order_id; ?>">طلب #<?php echo $order_id; ?></a></li>
            <li>تقييم</li>
        </ul>

        <!-- Page Title -->
        <div class="page-title">
            <i class="las la-star"></i>
            تقييم الخدمة
        </div>

        <!-- Flash messages -->
        <?php if (function_exists('display_message')) display_message(); ?>

        <!-- ═══ بطاقة معلومات الطلب ═══════════════════════════ -->
        <div class="card">
            <div class="card-hdr">
                <i class="las la-clipboard-check"></i>
                <h5>معلومات الطلب</h5>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">رقم الطلب</span>
                        <span class="info-value accent">#<?php echo $order_id; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">الخدمة</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['service_title']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">مزود الخدمة</span>
                        <span class="info-value">
                            <div class="prov-row">
                                <div class="prov-chip"><?php echo $provider_initial; ?></div>
                                <?php echo htmlspecialchars($order['provider_name']); ?>
                            </div>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">الحالة</span>
                        <span class="info-value">
                            <span class="badge-completed">مكتمل</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ بطاقة نموذج التقييم ════════════════════════════ -->
        <div class="card">
            <div class="card-hdr">
                <i class="las la-star-half-alt" style="color:var(--warning)"></i>
                <h5>أخبرنا عن تجربتك</h5>
            </div>
            <div class="card-body">

                <form method="POST"
                    action="/local_services/actions/submit_review.php"
                    id="reviewForm"
                    novalidate>

                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($order['service_id']); ?>">
                    <input type="hidden" name="provider_id" value="<?php echo htmlspecialchars($order['provider_id']); ?>">

                    <!-- ─── النجوم ─────────────────────────── -->
                    <div class="rating-wrap">
                        <span class="rating-title">كيف تقيّم هذه الخدمة؟</span>

                        <div class="stars-input" id="starsInput" role="radiogroup" aria-label="التقييم">
                            <!--
                            row-reverse: النجمة 5 تُعرض أولاً (يسار)، 1 أخيراً (يمين).
                            CSS ~ selector يلوّن input:checked ~ label (كل النجوم الأقل رقماً).
                        -->
                            <input type="radio" id="s5" name="rating" value="5" required>
                            <label for="s5" title="ممتاز - 5 نجوم">★</label>

                            <input type="radio" id="s4" name="rating" value="4">
                            <label for="s4" title="جيد جداً - 4 نجوم">★</label>

                            <input type="radio" id="s3" name="rating" value="3">
                            <label for="s3" title="جيد - 3 نجوم">★</label>

                            <input type="radio" id="s2" name="rating" value="2">
                            <label for="s2" title="مقبول - نجمتان">★</label>

                            <input type="radio" id="s1" name="rating" value="1">
                            <label for="s1" title="ضعيف - نجمة واحدة">★</label>
                        </div>

                        <div class="rating-hint" id="ratingHint" aria-live="polite">
                            <span class="hint-pill" id="hintPill" style="display:none"></span>
                            <span id="hintDefault" style="font-size:13px;color:var(--muted);">
                                اختر عدد النجوم
                            </span>
                        </div>
                    </div>

                    <hr class="divider">

                    <!-- ─── التعليق ─────────────────────────── -->
                    <label class="field-label" for="comment">
                        <i class="las la-comment-dots"></i>
                        تعليقك على الخدمة
                        <span class="optional">(اختياري)</span>
                    </label>
                    <textarea
                        id="comment"
                        name="comment"
                        class="review-textarea"
                        placeholder="شارك تجربتك بالتفصيل… تعليقاتك تساعد العملاء الآخرين على اتخاذ قرارهم"
                        maxlength="1000"
                        rows="5"></textarea>
                    <div class="char-row">
                        <span style="color:var(--muted)">الحد الأقصى 1000 حرف</span>
                        <span id="charDisplay">0</span> / 1000
                    </div>

                    <hr class="divider">

                    <!-- ─── أزرار ────────────────────────────── -->
                    <div class="actions">
                        <button type="submit" class="btn btn-submit" id="submitBtn" disabled>
                            <i class="las la-paper-plane"></i>
                            إرسال التقييم
                        </button>
                        <a href="/local_services/view_order.php?id=<?php echo $order_id; ?>"
                            class="btn btn-ghost">
                            <i class="las la-arrow-right"></i>
                            العودة للطلب
                        </a>
                    </div>

                </form>
            </div>
        </div>

    </div><!-- /page-wrap -->

    <script>
        (function() {
            /* ── النجوم ────────────────────────────────────────── */
            const labels = {
                1: 'ضعيف جداً 😞',
                2: 'مقبول 😐',
                3: 'جيد 🙂',
                4: 'جيد جداً 😊',
                5: 'ممتاز جداً ⭐'
            };

            const inputs = document.querySelectorAll('input[name="rating"]');
            const hintPill = document.getElementById('hintPill');
            const hintDef = document.getElementById('hintDefault');
            const submitBtn = document.getElementById('submitBtn');

            inputs.forEach(function(inp) {
                inp.addEventListener('change', function() {
                    hintDef.style.display = 'none';
                    hintPill.style.display = 'inline-flex';
                    hintPill.className = 'hint-pill';
                    hintPill.textContent = labels[this.value];
                    submitBtn.disabled = false;
                });
            });

            /* ── عداد الأحرف ───────────────────────────────────── */
            var textarea = document.getElementById('comment');
            var counter = document.getElementById('charDisplay');

            textarea.addEventListener('input', function() {
                counter.textContent = this.value.length;
            });

            /* ── التحقق قبل الإرسال ────────────────────────────── */
            document.getElementById('reviewForm').addEventListener('submit', function(e) {
                var checked = document.querySelector('input[name="rating"]:checked');
                if (!checked) {
                    e.preventDefault();
                    hintDef.style.display = 'none';
                    hintPill.style.display = 'inline-flex';
                    hintPill.className = 'hint-pill danger';
                    hintPill.textContent = '⚠️ يرجى اختيار تقييم أولاً';
                    document.getElementById('starsInput').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });
        })();
    </script>

</body>

</html>