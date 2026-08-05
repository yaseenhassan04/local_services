<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// admin/financial_report.php — التقارير المالية (إحصائيات + جداول)
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
check_login('admin');
$current_page = 'financial_report';

global $pdo;

// ── نسبة العمولة من قاعدة البيانات ──────────────────────
try {
    $rate_row = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='admin_commission_rate' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $ADMIN_RATE = isset($rate_row['setting_value']) ? (float)$rate_row['setting_value'] : 0.15;
} catch (PDOException $e) { $ADMIN_RATE = 0.15; }

// ── فلاتر ────────────────────────────────────────────────
$filter_month = trim($_GET['month'] ?? date('Y-m'));
$parts  = explode('-', $filter_month);
$year   = (int)($parts[0] ?? date('Y'));
$month  = (int)($parts[1] ?? date('n'));

// ── إحصائيات الشهر المختار ──────────────────────────────
try {
    $monthly = $pdo->prepare("
        SELECT COUNT(*) AS total_orders,
               SUM(CASE WHEN status='completed' THEN COALESCE(amount,0) ELSE 0 END) AS revenue,
               SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending,
               SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM orders WHERE YEAR(order_date)=? AND MONTH(order_date)=?
    ");
    $monthly->execute([$year, $month]);
    $ms = $monthly->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $ms = []; }

$m_revenue   = (float)($ms['revenue'] ?? 0);
$m_admin_cut = $m_revenue * $ADMIN_RATE;
$m_providers = $m_revenue - $m_admin_cut;

// ── إجماليات المنصة ──────────────────────────────────────
try {
    $overall = $pdo->query("
        SELECT COUNT(*) AS total_orders,
               SUM(CASE WHEN status='completed' THEN COALESCE(amount,0) ELSE 0 END) AS revenue
        FROM orders
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $overall = []; }

$all_revenue   = (float)($overall['revenue'] ?? 0);
$all_admin_cut = $all_revenue * $ADMIN_RATE;
$all_providers = $all_revenue - $all_admin_cut;

// ── أفضل المزودين للشهر ──────────────────────────────────
try {
    $top_stmt = $pdo->prepare("
        SELECT u.full_name, u.email,
               COUNT(o.id) AS order_count,
               SUM(CASE WHEN o.status='completed' THEN COALESCE(o.amount,0) ELSE 0 END) AS gross
        FROM users u
        JOIN orders o ON o.provider_id=u.id
        WHERE u.role='provider' AND YEAR(o.order_date)=? AND MONTH(o.order_date)=?
        GROUP BY u.id ORDER BY gross DESC LIMIT 10
    ");
    $top_stmt->execute([$year, $month]);
    $top_providers = $top_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $top_providers = []; }

// ── الطلبات المكتملة للشهر ───────────────────────────────
try {
    $orders_stmt = $pdo->prepare("
        SELECT o.id, o.status, o.order_date,
               COALESCE(o.amount,0) AS amount,
               COALESCE(o.amount,0)*? AS admin_cut,
               COALESCE(o.amount,0)*? AS provider_net,
               s.title AS service_title,
               u_c.full_name AS client_name,
               u_p.full_name AS provider_name
        FROM orders o
        JOIN services s ON o.service_id=s.id
        JOIN users u_c ON o.client_id=u_c.id
        JOIN users u_p ON o.provider_id=u_p.id
        WHERE o.status='completed' AND YEAR(o.order_date)=? AND MONTH(o.order_date)=?
        ORDER BY o.order_date DESC
    ");
    $orders_stmt->execute([$ADMIN_RATE, (1 - $ADMIN_RATE), $year, $month]);
    $completed_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $completed_orders = []; }

$months_ar = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل','05'=>'مايو','06'=>'يونيو',
              '07'=>'يوليو','08'=>'أغسطس','09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];
$current_month_name = $months_ar[str_pad($month,2,'0',STR_PAD_LEFT)] . ' ' . $year;
$admin_rate_pct = round($ADMIN_RATE * 100, 2);
$provider_rate  = round((1 - $ADMIN_RATE) * 100, 2);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>التقارير المالية | لوحة المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
<?php include __DIR__ . '/financial_shared_styles.php'; ?>

/* Sub-nav */
.fin-tabs { display:flex;gap:6px;margin-bottom:26px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:6px; }
.fin-tab  { flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;transition:all .2s; }
.fin-tab i { font-size:17px; }
.fin-tab:hover { background:var(--pri-lt);color:var(--primary); }
.fin-tab.active { background:var(--success);color:#fff;box-shadow:0 4px 14px rgba(0,171,85,.3); }

/* Month filter */
.month-filter { display:flex;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:10px 16px; }
.month-filter label { font-size:12px;font-weight:700;color:var(--muted); }
.month-input { background:var(--dark);border:1px solid var(--border);border-radius:6px;color:var(--txt);font-family:'Tajawal',sans-serif;font-size:13px;padding:7px 10px;outline:none; }
.month-input:focus { border-color:var(--success); }
.btn-go { padding:7px 16px;background:var(--success);color:#fff;border:none;border-radius:6px;font-family:'Tajawal',sans-serif;font-size:13px;font-weight:700;cursor:pointer; }

/* Rate badge */
.rate-badge { display:inline-flex;align-items:center;gap:5px;background:rgba(226,160,63,.15);color:var(--warning);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;border:1px solid rgba(226,160,63,.3); }
.rate-badge a { color:var(--warning);text-decoration:underline;margin-right:4px; }
</style>
</head>
<body>

<!-- NAV -->
<nav class="top-nav">
    <div class="nav-brand">
        <div class="logo-icon"><i class="las la-shield-alt"></i></div>
        خدماتي
    </div>
    <div class="nav-spacer"></div>
    <button onclick="toggleTheme()" id="themeToggle" class="nav-icon" style="cursor:pointer;border:none;background:var(--dark);">
        <i class="las la-sun" id="themeIcon"></i>
    </button>
    <a href="/local_services/index.php" class="nav-icon"><i class="las la-external-link-alt"></i></a>
    <a href="/local_services/logout.php" class="nav-icon" style="color:var(--danger);"><i class="las la-sign-out-alt"></i></a>
    <?php $me = getCurrentUser(); if ($me): ?>
    <div class="nav-user">
        <div class="nav-avatar"><?= mb_substr($me['full_name'],0,1) ?></div>
        <div>
            <div class="nav-uname"><?= htmlspecialchars($me['full_name']) ?></div>
            <div class="nav-urole"><i class="las la-crown"></i> مدير النظام</div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main">

    <!-- Sub-tabs -->
    <div class="fin-tabs">
        <a href="financial_report.php" class="fin-tab active"><i class="las la-file-invoice-dollar"></i> التقارير</a>
        <a href="financial_charts.php" class="fin-tab"><i class="las la-chart-bar"></i> الرسوم البيانية</a>
        <a href="financial_settings.php" class="fin-tab"><i class="las la-sliders-h"></i> إعدادات العمولة</a>
    </div>

    <div class="page-hdr">
        <div>
            <h1><i class="las la-file-invoice-dollar" style="color:var(--success);margin-left:8px;font-size:24px;"></i>
                التقارير المالية — <span style="color:var(--success);"><?= $current_month_name ?></span>
            </h1>
            <div class="breadcrumb">
                <a href="/local_services/admin/dashboard.php">الرئيسية</a>
                <sep>/</sep><span>التقارير المالية</span>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            <form method="GET" class="month-filter">
                <label><i class="las la-calendar-alt"></i> الشهر:</label>
                <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>" class="month-input">
                <button type="submit" class="btn-go"><i class="las la-filter"></i> عرض</button>
            </form>
            <div class="rate-badge">
                <i class="las la-percentage"></i>
                نسبة العمولة الحالية: <strong><?= $admin_rate_pct ?>%</strong>
                <a href="financial_settings.php">تعديل</a>
            </div>
        </div>
    </div>

    <!-- ── إحصائيات الشهر ── -->
    <div style="font-size:13px;color:var(--muted);margin-bottom:14px;font-weight:600;">
        📅 البيانات الخاصة بشهر: <strong style="color:var(--success);"><?= $current_month_name ?></strong>
    </div>
    <div class="stats-grid">
        <div class="scard cp">
            <div class="sc-icon" style="background:var(--pri-lt);color:var(--primary);"><i class="las la-clipboard-list"></i></div>
            <div class="sc-val" style="color:var(--primary);"><?= $ms['total_orders'] ?? 0 ?></div>
            <div class="sc-lbl">طلبات الشهر</div>
            <span class="sc-sub" style="background:var(--pri-lt);color:var(--primary);"><?= $ms['completed'] ?? 0 ?> مكتمل</span>
        </div>
        <div class="scard cs">
            <div class="sc-icon" style="background:var(--suc-lt);color:var(--success);"><i class="las la-money-bill-wave"></i></div>
            <div class="sc-val" style="color:var(--success);"><?= number_format($m_revenue,2) ?></div>
            <div class="sc-lbl">إيرادات الشهر (₪)</div>
        </div>
        <div class="scard cw">
            <div class="sc-icon" style="background:var(--war-lt);color:var(--warning);"><i class="las la-percentage"></i></div>
            <div class="sc-val" style="color:var(--warning);"><?= number_format($m_admin_cut,2) ?></div>
            <div class="sc-lbl">عمولة الأدمن <?= $admin_rate_pct ?>% (₪)</div>
        </div>
        <div class="scard ci">
            <div class="sc-icon" style="background:var(--inf-lt);color:var(--info);"><i class="las la-user-tie"></i></div>
            <div class="sc-val" style="color:var(--info);"><?= number_format($m_providers,2) ?></div>
            <div class="sc-lbl">أرباح المزودين <?= $provider_rate ?>% (₪)</div>
        </div>
        <div class="scard cd">
            <div class="sc-icon" style="background:var(--dan-lt);color:var(--danger);"><i class="las la-times-circle"></i></div>
            <div class="sc-val" style="color:var(--danger);"><?= $ms['cancelled'] ?? 0 ?></div>
            <div class="sc-lbl">طلبات ملغية</div>
        </div>
        <div class="scard cx">
            <div class="sc-icon" style="background:var(--pur-lt);color:var(--purple);"><i class="las la-clock"></i></div>
            <div class="sc-val" style="color:var(--purple);"><?= $ms['pending'] ?? 0 ?></div>
            <div class="sc-lbl">طلبات معلقة</div>
        </div>
    </div>

    <!-- ── إجماليات المنصة كل الوقت ── -->
    <div class="comm-legend">
        <div class="comm-leg-item">
            <div class="comm-dot" style="background:var(--success);"></div>
            <div>
                <div class="cl-val" style="color:var(--success);"><?= number_format($all_revenue,2) ?> ₪</div>
                <div class="cl-lbl">إجمالي إيرادات المنصة (كل الوقت)</div>
            </div>
        </div>
        <div class="comm-leg-item">
            <div class="comm-dot" style="background:var(--warning);"></div>
            <div>
                <div class="cl-val" style="color:var(--warning);"><?= number_format($all_admin_cut,2) ?> ₪</div>
                <div class="cl-lbl">مجموع عمولات الأدمن (<?= $admin_rate_pct ?>%)</div>
            </div>
        </div>
        <div class="comm-leg-item">
            <div class="comm-dot" style="background:var(--info);"></div>
            <div>
                <div class="cl-val" style="color:var(--info);"><?= number_format($all_providers,2) ?> ₪</div>
                <div class="cl-lbl">مجموع أرباح المزودين (<?= $provider_rate ?>%)</div>
            </div>
        </div>
    </div>

    <!-- ── أفضل المزودين ── -->
    <?php if (!empty($top_providers)): ?>
    <div class="sec-title"><i class="las la-trophy" style="color:var(--warning);"></i> أفضل المزودين — <?= $current_month_name ?></div>
    <div class="tcard">
        <div style="overflow-x:auto;">
            <table class="xato-table">
                <thead>
                    <tr>
                        <th>#</th><th>المزود</th><th>عدد الطلبات</th>
                        <th>إجمالي المبيعات</th><th>عمولة الأدمن (<?= $admin_rate_pct ?>%)</th><th>صافي المزود (<?= $provider_rate ?>%)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top_providers as $i => $p):
                    $gross = (float)$p['gross'];
                    $adm   = $gross * $ADMIN_RATE;
                    $net   = $gross - $adm;
                ?>
                <tr>
                    <td style="font-weight:700;color:var(--warning);"><?= $i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:30px;height:30px;border-radius:50%;background:var(--pri-lt);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;"><?= mb_substr($p['full_name'],0,1) ?></div>
                            <div>
                                <div style="font-weight:700;color:var(--txt);"><?= htmlspecialchars($p['full_name']) ?></div>
                                <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($p['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:700;"><?= $p['order_count'] ?></td>
                    <td style="font-weight:700;color:var(--success);"><?= number_format($gross,2) ?> ₪</td>
                    <td style="font-weight:700;color:var(--warning);"><?= number_format($adm,2) ?> ₪</td>
                    <td><span style="font-size:15px;font-weight:900;color:var(--info);"><?= number_format($net,2) ?> ₪</span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── الطلبات المكتملة ── -->
    <div class="sec-title"><i class="las la-check-circle" style="color:var(--success);"></i> الطلبات المكتملة — <?= $current_month_name ?></div>
    <div class="tcard">
        <div class="tcard-hdr">
            <i class="las la-list" style="color:var(--success);font-size:18px;"></i>
            <h5>تفصيل العمولات لكل طلب</h5>
            <span style="margin-right:auto;font-size:12px;color:var(--muted);"><?= count($completed_orders) ?> طلب</span>
        </div>
        <?php if (empty($completed_orders)): ?>
        <div style="text-align:center;padding:50px;color:var(--muted);">
            <i class="las la-receipt" style="font-size:40px;display:block;margin-bottom:10px;"></i>
            لا توجد طلبات مكتملة هذا الشهر
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="xato-table">
                <thead>
                    <tr>
                        <th>#</th><th>الخدمة</th><th>العميل</th><th>المزود</th>
                        <th>المبلغ الكلي</th><th>عمولة الأدمن</th><th>صافي المزود</th>
                        <th>التاريخ</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($completed_orders as $o): ?>
                <tr>
                    <td style="color:var(--primary);font-weight:700;">#<?= $o['id'] ?></td>
                    <td style="font-weight:600;color:var(--txt);max-width:130px;"><?= htmlspecialchars($o['service_title']) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($o['client_name']) ?></td>
                    <td style="color:var(--primary);font-size:12px;"><?= htmlspecialchars($o['provider_name']) ?></td>
                    <td style="font-weight:800;color:var(--success);"><?= number_format((float)$o['amount'],2) ?> ₪</td>
                    <td style="font-weight:700;color:var(--warning);"><?= number_format((float)$o['admin_cut'],2) ?> ₪</td>
                    <td style="font-weight:700;color:var(--info);"><?= number_format((float)$o['provider_net'],2) ?> ₪</td>
                    <td style="font-size:12px;color:var(--muted);"><?= date('Y/m/d',strtotime($o['order_date'])) ?></td>
                    <td>
                        <a href="view_order.php?id=<?= $o['id'] ?>" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:var(--pri-lt);color:var(--primary);border-radius:6px;font-size:11px;font-weight:700;">
                            <i class="las la-eye"></i> عرض
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:rgba(27,46,75,.6);">
                        <td colspan="4" style="font-weight:800;color:var(--txt);padding:12px 14px;">إجمالي الشهر</td>
                        <td style="font-weight:900;color:var(--success);font-size:15px;"><?= number_format($m_revenue,2) ?> ₪</td>
                        <td style="font-weight:900;color:var(--warning);font-size:15px;"><?= number_format($m_admin_cut,2) ?> ₪</td>
                        <td style="font-weight:900;color:var(--info);font-size:15px;"><?= number_format($m_providers,2) ?> ₪</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
function toggleTheme(){
    document.body.classList.toggle('light-mode');
    document.getElementById('themeIcon').className = document.body.classList.contains('light-mode') ? 'las la-moon' : 'las la-sun';
    localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
}
if(localStorage.getItem('theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='las la-moon'; }
</script>
</body>
</html>