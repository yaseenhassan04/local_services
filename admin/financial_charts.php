<?php
// admin/financial_charts.php — الرسوم البيانية المالية
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
check_login('admin');
$current_page = 'financial_report';

global $pdo;

// ── نسبة العمولة ──────────────────────────────────────────
try {
    $rate_row = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='admin_commission_rate' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $ADMIN_RATE = isset($rate_row['setting_value']) ? (float)$rate_row['setting_value'] : 0.15;
} catch (PDOException $e) { $ADMIN_RATE = 0.15; }

// ── آخر 12 شهر ────────────────────────────────────────────
$chart_months = [];
for ($i = 11; $i >= 0; $i--) {
    $ts  = mktime(0, 0, 0, date('n') - $i, 1, date('Y'));
    $y2  = date('Y', $ts);
    $m2  = date('n', $ts);
    $lbl = date('M Y', $ts);
    try {
        $row = $pdo->prepare("SELECT
            SUM(CASE WHEN status='completed' THEN COALESCE(amount,0) ELSE 0 END) AS rev,
            COUNT(CASE WHEN status='completed' THEN 1 END) AS completed,
            COUNT(CASE WHEN status='cancelled' THEN 1 END) AS cancelled,
            COUNT(*) AS total
            FROM orders WHERE YEAR(order_date)=? AND MONTH(order_date)=?");
        $row->execute([$y2, $m2]);
        $d = $row->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $d = ['rev'=>0,'completed'=>0,'cancelled'=>0,'total'=>0]; }
    $rev = (float)($d['rev'] ?? 0);
    $chart_months[] = [
        'label'     => $lbl,
        'revenue'   => $rev,
        'admin'     => round($rev * $ADMIN_RATE, 2),
        'provider'  => round($rev * (1 - $ADMIN_RATE), 2),
        'completed' => (int)($d['completed'] ?? 0),
        'cancelled' => (int)($d['cancelled'] ?? 0),
        'total'     => (int)($d['total'] ?? 0),
    ];
}

// ── أفضل 6 خدمات ──────────────────────────────────────────
try {
    $svc_stmt = $pdo->query("
        SELECT s.title, COUNT(o.id) AS cnt,
               SUM(CASE WHEN o.status='completed' THEN COALESCE(o.amount,0) ELSE 0 END) AS rev
        FROM orders o JOIN services s ON o.service_id=s.id
        WHERE o.status='completed'
        GROUP BY s.id ORDER BY rev DESC LIMIT 6
    ");
    $svc_data = $svc_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $svc_data = []; }

// ── إجماليات للدونات ──────────────────────────────────────
try {
    $totals = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
    FROM orders")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $totals = ['total'=>0,'completed'=>0,'pending'=>0,'cancelled'=>0]; }

$all_rev  = array_sum(array_column($chart_months,'revenue'));
$all_adm  = round($all_rev * $ADMIN_RATE, 2);
$all_prov = round($all_rev - $all_adm, 2);
$best_month = array_reduce($chart_months, fn($c,$i) => ($i['revenue'] > ($c['revenue']??0)) ? $i : $c, []);

$admin_rate_pct = round($ADMIN_RATE * 100, 2);
$provider_rate  = round((1 - $ADMIN_RATE) * 100, 2);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>الرسوم البيانية | لوحة المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
<?php include __DIR__ . '/financial_shared_styles.php'; ?>

/* Sub-nav */
.fin-tabs { display:flex;gap:6px;margin-bottom:26px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:6px; }
.fin-tab  { flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;transition:all .2s; }
.fin-tab i { font-size:17px; }
.fin-tab:hover { background:var(--pri-lt);color:var(--primary); }
.fin-tab.active { background:var(--primary);color:#fff;box-shadow:0 4px 14px rgba(67,97,238,.3); }

/* Charts grid */
.charts-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px; }
@media(max-width:860px){ .charts-grid{grid-template-columns:1fr;} }
.chart-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:22px; }
.chart-card h5 { font-size:14px;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:7px; }
.chart-card h5 i { font-size:18px; }

/* Period buttons */
.period-btns { display:flex;gap:6px;margin-bottom:0;margin-right:auto; }
.period-btn { padding:5px 14px;border-radius:6px;font-size:12px;font-weight:700;border:1px solid var(--border);background:var(--dark);color:var(--muted);cursor:pointer;font-family:'Tajawal',sans-serif;transition:all .15s; }
.period-btn.active,.period-btn:hover { background:var(--primary);color:#fff;border-color:var(--primary); }

/* Insight boxes */
.insights-row { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px; }
.insight-box { background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center; }
.insight-box .ib-val { font-size:22px;font-weight:900;margin-bottom:4px; }
.insight-box .ib-lbl { font-size:11px;color:var(--muted); }
.insight-box .ib-trend { font-size:11px;margin-top:5px;font-weight:700; }
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
        <a href="financial_report.php" class="fin-tab"><i class="las la-file-invoice-dollar"></i> التقارير</a>
        <a href="financial_charts.php" class="fin-tab active"><i class="las la-chart-bar"></i> الرسوم البيانية</a>
        <a href="financial_settings.php" class="fin-tab"><i class="las la-sliders-h"></i> إعدادات العمولة</a>
    </div>

    <div class="page-hdr">
        <div>
            <h1><i class="las la-chart-bar" style="color:var(--primary);margin-left:8px;font-size:24px;"></i>الرسوم البيانية المالية</h1>
            <div class="breadcrumb">
                <a href="/local_services/admin/dashboard.php">الرئيسية</a>
                <sep>/</sep><a href="financial_report.php">التقارير المالية</a>
                <sep>/</sep><span>الرسوم البيانية</span>
            </div>
        </div>
    </div>

    <!-- ── أرقام سريعة ── -->
    <div class="insights-row">
        <div class="insight-box">
            <div class="ib-val" style="color:var(--success);"><?= number_format($all_rev,0) ?> ₪</div>
            <div class="ib-lbl">إجمالي الإيرادات (12 شهر)</div>
        </div>
        <div class="insight-box">
            <div class="ib-val" style="color:var(--warning);"><?= number_format($all_adm,0) ?> ₪</div>
            <div class="ib-lbl">عمولات الأدمن (<?= $admin_rate_pct ?>%)</div>
        </div>
        <div class="insight-box">
            <div class="ib-val" style="color:var(--info);"><?= number_format($all_prov,0) ?> ₪</div>
            <div class="ib-lbl">أرباح المزودين (<?= $provider_rate ?>%)</div>
        </div>
        <div class="insight-box">
            <div class="ib-val" style="color:var(--purple);"><?= $best_month['label'] ?? '—' ?></div>
            <div class="ib-lbl">أفضل شهر</div>
            <div class="ib-trend" style="color:var(--success);"><?= number_format($best_month['revenue']??0,0) ?> ₪</div>
        </div>
    </div>

    <!-- ── Bar chart شهري ── -->
    <div class="chart-card" style="margin-bottom:20px;">
        <h5>
            <i class="las la-chart-bar" style="color:var(--primary);"></i>
            الإيرادات الشهرية — آخر 12 شهراً
            <div class="period-btns">
                <button class="period-btn active" onclick="showPeriod(6,this)">6 أشهر</button>
                <button class="period-btn" onclick="showPeriod(12,this)">12 شهر</button>
            </div>
        </h5>
        <canvas id="revenueBarChart" height="120"></canvas>
    </div>

    <!-- ── Line chart ── -->
    <div class="chart-card" style="margin-bottom:20px;">
        <h5><i class="las la-chart-line" style="color:var(--success);"></i> منحنى نمو الإيرادات</h5>
        <canvas id="revenueLineChart" height="100"></canvas>
    </div>

    <!-- ── Donut + Services bar ── -->
    <div class="charts-grid">
        <div class="chart-card">
            <h5><i class="las la-chart-pie" style="color:var(--warning);"></i> توزيع الطلبات</h5>
            <div style="max-width:280px;margin:0 auto;">
                <canvas id="statusDonut"></canvas>
            </div>
            <div style="display:flex;gap:12px;justify-content:center;margin-top:14px;flex-wrap:wrap;">
                <span style="font-size:12px;font-weight:700;color:var(--success);"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--success);margin-left:5px;"></span>مكتمل (<?= $totals['completed'] ?>)</span>
                <span style="font-size:12px;font-weight:700;color:var(--warning);"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--warning);margin-left:5px;"></span>معلق (<?= $totals['pending'] ?>)</span>
                <span style="font-size:12px;font-weight:700;color:var(--danger);"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--danger);margin-left:5px;"></span>ملغي (<?= $totals['cancelled'] ?>)</span>
            </div>
        </div>
        <div class="chart-card">
            <h5><i class="las la-trophy" style="color:var(--warning);"></i> أفضل الخدمات إيراداً</h5>
            <canvas id="servicesBar" height="200"></canvas>
        </div>
    </div>

    <!-- ── Commission donut ── -->
    <div class="chart-card">
        <h5><i class="las la-percentage" style="color:var(--warning);"></i> توزيع الإيرادات — عمولة الأدمن مقابل المزودين</h5>
        <div style="max-width:260px;margin:0 auto;">
            <canvas id="commissionDonut"></canvas>
        </div>
        <div style="display:flex;gap:16px;justify-content:center;margin-top:14px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:800;color:var(--warning);">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:var(--warning);margin-left:5px;"></span>
                الأدمن <?= $admin_rate_pct ?>% — <?= number_format($all_adm,2) ?> ₪
            </span>
            <span style="font-size:13px;font-weight:800;color:var(--info);">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:var(--info);margin-left:5px;"></span>
                المزودون <?= $provider_rate ?>% — <?= number_format($all_prov,2) ?> ₪
            </span>
        </div>
    </div>

</main>

<script>
const ALL_DATA = <?= json_encode($chart_months) ?>;
const tooltip = { backgroundColor:'#0e1726',borderColor:'#1b2e4b',borderWidth:1,titleColor:'#e0e6ed',bodyColor:'#bfc9d4' };
const tick = '#888ea8', grid = 'rgba(27,46,75,.5)', ff = 'Tajawal';

function getSlice(n){ return ALL_DATA.slice(-n); }

// Bar chart
const barCtx = document.getElementById('revenueBarChart').getContext('2d');
let barChart = new Chart(barCtx, {
    type:'bar',
    data: buildBarData(6),
    options:{
        responsive:true,
        plugins:{
            legend:{ position:'top',labels:{color:tick,font:{family:ff,size:12},padding:18} },
            tooltip:{ ...tooltip, callbacks:{ label:c=>` ${c.dataset.label}: ${c.parsed.y.toLocaleString('ar')} ₪` } }
        },
        scales:{
            x:{ ticks:{color:tick,font:{family:ff}},grid:{color:grid} },
            y:{ ticks:{color:tick,font:{family:ff},callback:v=>v.toLocaleString('ar')+' ₪'},grid:{color:grid} }
        }
    }
});
function buildBarData(n){
    const d = getSlice(n);
    return { labels:d.map(x=>x.label), datasets:[
        { label:'إجمالي الإيرادات',data:d.map(x=>x.revenue),backgroundColor:'rgba(67,97,238,.7)',borderColor:'#4361ee',borderWidth:1,borderRadius:6 },
        { label:'عمولة الأدمن',data:d.map(x=>x.admin),backgroundColor:'rgba(226,160,63,.7)',borderColor:'#e2a03f',borderWidth:1,borderRadius:6 },
        { label:'أرباح المزودين',data:d.map(x=>x.provider),backgroundColor:'rgba(33,150,243,.6)',borderColor:'#2196f3',borderWidth:1,borderRadius:6 },
    ]};
}
function showPeriod(n,btn){
    document.querySelectorAll('.period-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    barChart.data = buildBarData(n);
    barChart.update();
}

// Line chart
new Chart(document.getElementById('revenueLineChart').getContext('2d'),{
    type:'line',
    data:{ labels:ALL_DATA.map(x=>x.label), datasets:[
        { label:'الإيرادات الشهرية',data:ALL_DATA.map(x=>x.revenue),borderColor:'#00ab55',backgroundColor:'rgba(0,171,85,.1)',fill:true,tension:.4,pointBackgroundColor:'#00ab55',pointRadius:4 },
        { label:'عمولة الأدمن',data:ALL_DATA.map(x=>x.admin),borderColor:'#e2a03f',backgroundColor:'rgba(226,160,63,.08)',fill:true,tension:.4,pointBackgroundColor:'#e2a03f',pointRadius:4 }
    ]},
    options:{ responsive:true,
        plugins:{ legend:{position:'top',labels:{color:tick,font:{family:ff,size:12},padding:18}}, tooltip:{...tooltip,callbacks:{label:c=>` ${c.dataset.label}: ${c.parsed.y.toLocaleString('ar')} ₪`}} },
        scales:{ x:{ticks:{color:tick,font:{family:ff}},grid:{color:grid}}, y:{ticks:{color:tick,font:{family:ff},callback:v=>v.toLocaleString('ar')+' ₪'},grid:{color:grid}} }
    }
});

// Status donut
new Chart(document.getElementById('statusDonut').getContext('2d'),{
    type:'doughnut',
    data:{ labels:['مكتملة','معلقة','ملغية'], datasets:[{ data:[<?= (int)$totals['completed'] ?>,<?= (int)$totals['pending'] ?>,<?= (int)$totals['cancelled'] ?>],backgroundColor:['#00ab55','#e2a03f','#e7515a'],borderColor:'#0e1726',borderWidth:3,hoverOffset:6 }] },
    options:{ cutout:'70%',responsive:true, plugins:{ legend:{display:false},tooltip:{...tooltip} } }
});

// Services bar
const svcData = <?= json_encode($svc_data) ?>;
new Chart(document.getElementById('servicesBar').getContext('2d'),{
    type:'bar',
    data:{ labels:svcData.map(x=>x.title.length>18?x.title.substring(0,18)+'…':x.title),
        datasets:[{ label:'الإيرادات (₪)',data:svcData.map(x=>x.rev),backgroundColor:['#4361ee','#00ab55','#e2a03f','#2196f3','#e7515a','#805dca'],borderRadius:6 }] },
    options:{ indexAxis:'y',responsive:true,
        plugins:{ legend:{display:false},tooltip:{...tooltip,callbacks:{label:c=>` ${c.parsed.x.toLocaleString('ar')} ₪`}} },
        scales:{ x:{ticks:{color:tick,font:{family:ff},callback:v=>v.toLocaleString('ar')},grid:{color:grid}}, y:{ticks:{color:tick,font:{family:ff,size:11}},grid:{display:false}} }
    }
});

// Commission donut
new Chart(document.getElementById('commissionDonut').getContext('2d'),{
    type:'doughnut',
    data:{ labels:['عمولة الأدمن','أرباح المزودين'], datasets:[{ data:[<?= $all_adm ?>,<?= $all_prov ?>],backgroundColor:['#e2a03f','#2196f3'],borderColor:'#0e1726',borderWidth:3,hoverOffset:6 }] },
    options:{ cutout:'72%',responsive:true,plugins:{ legend:{display:false},tooltip:{...tooltip,callbacks:{label:c=>` ${c.label}: ${c.parsed.toLocaleString('ar')} ₪`}} } }
});

function toggleTheme(){
    document.body.classList.toggle('light-mode');
    document.getElementById('themeIcon').className = document.body.classList.contains('light-mode') ? 'las la-moon' : 'las la-sun';
    localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
}
if(localStorage.getItem('theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='las la-moon'; }
</script>
</body>
</html>