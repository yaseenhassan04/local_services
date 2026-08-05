<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
// admin/financial_settings.php — إعدادات عمولة الموقع
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
check_login('admin');
$current_page = 'financial_report';

global $pdo;

$success_msg = '';
$error_msg   = '';

// ── إنشاء الجدول لو غير موجود (أمان إضافي) ───────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL
        )
    ");
} catch (PDOException $e) { /* تجاهل لو الجدول موجود مسبقاً */ }

// ── حفظ النسبة الجديدة ────────────────────────────────────
// ── حفظ النسبة الجديدة ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_rate'])) {
    $raw = trim($_POST['new_rate']);
    
    // دالة تحويل الأرقام العربية/الشرقية والهندية إلى أرقام إنجليزية لإصلاح مشكلة الـ المدخلات
    $arabic_eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $arabic_western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $raw = str_replace($arabic_eastern, $arabic_western, $raw);
    
    // استبدال الفاصلة العشرية لو كتبت بشكل خاطئ
    $raw = str_replace(',', '.', $raw);

    if (!is_numeric($raw)) {
        $error_msg = 'القيمة المدخلة غير صالحة، يرجى إدخال رقم بالصيغة الصحيحة.';
    } else {
        $pct = (float)$raw;
        if ($pct < 0 || $pct > 100) {
            $error_msg = 'نسبة العمولة يجب أن تكون بين 0 و100%.';
        } else {
            $new_rate = round($pct / 100, 4); // نحفظ كنسبة عشرية 0.15 مثلاً
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value)
                    VALUES ('admin_commission_rate', ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([(string)$new_rate, (string)$new_rate]);
                $success_msg = 'تم تحديث نسبة العمولة بنجاح إلى ' . number_format($pct, 2) . '%.';
                
                // تحديث المتغير الحالي ليعرض النسبة الجديدة مباشرة بعد الحفظ
                $ADMIN_RATE = $new_rate;
                $admin_rate_pct = round($ADMIN_RATE * 100, 2);
                $provider_rate  = round((1 - $ADMIN_RATE) * 100, 2);
                
            } catch (PDOException $e) {
                // نصيحة: يمكنك طباعة $e->getMessage() هنا مؤقتاً في بيئة التطوير لو استمر الخطأ للتأكد من إعدادات قاعدة البيانات.
                $error_msg = 'حدث خطأ أثناء حفظ الإعدادات، حاول مرة أخرى.';
            }
        }
    }
}

// ── قراءة النسبة الحالية ──────────────────────────────────
try {
    $rate_row   = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='admin_commission_rate' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $ADMIN_RATE = isset($rate_row['setting_value']) ? (float)$rate_row['setting_value'] : 0.15;
} catch (PDOException $e) { $ADMIN_RATE = 0.15; }

$admin_rate_pct = round($ADMIN_RATE * 100, 2);
$provider_rate  = round((1 - $ADMIN_RATE) * 100, 2);

// ── أمثلة تطبيقية على النسبة الحالية ──────────────────────
$examples = [100, 500, 1000, 5000];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>إعدادات العمولة | لوحة المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
<?php include __DIR__ . '/financial_shared_styles.php'; ?>

/* Sub-nav */
.fin-tabs { display:flex;gap:6px;margin-bottom:26px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:6px; }
.fin-tab  { flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;color:var(--muted);text-decoration:none;transition:all .2s; }
.fin-tab i { font-size:17px; }
.fin-tab:hover { background:var(--pri-lt);color:var(--primary); }
.fin-tab.active { background:var(--purple);color:#fff;box-shadow:0 4px 14px rgba(128,93,202,.3); }

/* Settings card */
.settings-grid { display:grid;grid-template-columns:1.1fr 1fr;gap:20px;align-items:start; }
@media(max-width:900px){ .settings-grid{grid-template-columns:1fr;} }

.set-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:26px; }
.set-card h5 { font-size:15px;font-weight:800;margin-bottom:18px;display:flex;align-items:center;gap:8px; }
.set-card h5 i { font-size:19px;color:var(--purple); }

.field-label { font-size:12px;font-weight:700;color:var(--muted);margin-bottom:8px;display:block; }
.rate-input-wrap { position:relative;display:flex;align-items:center;gap:12px;margin-bottom:18px; }
.rate-input { width:140px;background:var(--dark);border:1px solid var(--border);border-radius:8px;color:var(--txt);font-family:'Tajawal',sans-serif;font-size:22px;font-weight:800;padding:12px 16px;outline:none;text-align:center; }
.rate-input:focus { border-color:var(--purple); }
.rate-suffix { font-size:18px;font-weight:800;color:var(--muted); }

.rate-slider { width:100%;margin-bottom:6px;-webkit-appearance:none;height:6px;border-radius:4px;background:var(--border);outline:none; }
.rate-slider::-webkit-slider-thumb { -webkit-appearance:none;width:20px;height:20px;border-radius:50%;background:var(--purple);cursor:pointer;border:3px solid var(--card);box-shadow:0 0 0 1px var(--purple); }
.rate-slider::-moz-range-thumb { width:20px;height:20px;border-radius:50%;background:var(--purple);cursor:pointer;border:3px solid var(--card); }

.split-preview { display:flex;border-radius:8px;overflow:hidden;height:34px;margin:18px 0;border:1px solid var(--border); }
.split-admin   { background:var(--warning);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#1a1300;transition:width .2s; }
.split-prov    { background:var(--info);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#04223f;transition:width .2s; }

.btn-save { width:100%;padding:13px;background:var(--purple);color:#fff;border:none;border-radius:8px;font-family:'Tajawal',sans-serif;font-size:14px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s; }
.btn-save:hover { opacity:.9; }

.alert-box { padding:13px 18px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:9px; }
.alert-ok   { background:var(--suc-lt);color:var(--success);border:1px solid rgba(0,171,85,.3); }
.alert-err  { background:var(--dan-lt);color:var(--danger);border:1px solid rgba(231,81,90,.3); }

.warn-note { background:var(--war-lt);border:1px solid rgba(226,160,63,.3);color:var(--warning);border-radius:8px;padding:12px 16px;font-size:12px;font-weight:600;display:flex;gap:9px;align-items:flex-start;margin-top:16px;line-height:1.7; }
.warn-note i { font-size:16px;margin-top:2px;flex-shrink:0; }

.example-row { display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid rgba(27,46,75,.4);font-size:13px; }
.example-row:last-child { border-bottom:none; }
.ex-amount { color:var(--dark2);font-weight:700; }
.ex-split { display:flex;gap:14px;font-size:12px; }
.ex-split b.a { color:var(--warning); } .ex-split b.p { color:var(--info); }

.current-rate-hero { text-align:center;padding:10px 0 22px; }
.crh-val { font-size:42px;font-weight:900;color:var(--purple);line-height:1; }
.crh-lbl { font-size:12px;color:var(--muted);margin-top:6px;font-weight:700; }
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
        <a href="financial_charts.php" class="fin-tab"><i class="las la-chart-bar"></i> الرسوم البيانية</a>
        <a href="financial_settings.php" class="fin-tab active"><i class="las la-sliders-h"></i> إعدادات العمولة</a>
    </div>

    <div class="page-hdr">
        <div>
            <h1><i class="las la-sliders-h" style="color:var(--purple);margin-left:8px;font-size:24px;"></i>
                إعدادات العمولة
            </h1>
            <div class="breadcrumb">
                <a href="/local_services/admin/dashboard.php">الرئيسية</a>
                <sep>/</sep><a href="financial_report.php">التقارير المالية</a>
                <sep>/</sep><span>إعدادات العمولة</span>
            </div>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert-box alert-ok"><i class="las la-check-circle" style="font-size:17px;"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
    <div class="alert-box alert-err"><i class="las la-exclamation-circle" style="font-size:17px;"></i> <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="settings-grid">

        <!-- ── نموذج التعديل ── -->
        <div class="set-card">
            <h5><i class="las la-percentage"></i> تعديل نسبة عمولة الأدمن</h5>

            <div class="current-rate-hero">
                <div class="crh-val" id="liveRateVal"><?= $admin_rate_pct ?>%</div>
                <div class="crh-lbl">نسبة العمولة الحالية لكل طلب مكتمل</div>
            </div>

            <div class="split-preview">
                <div class="split-admin" id="splitAdmin" style="width:<?= $admin_rate_pct ?>%;">عمولة <?= $admin_rate_pct ?>%</div>
                <div class="split-prov" id="splitProv" style="width:<?= $provider_rate ?>%;">مزود <?= $provider_rate ?>%</div>
            </div>

            <form method="POST">
                <label class="field-label">حدّد النسبة الجديدة (%)</label>
                <div class="rate-input-wrap">
                    <input type="number" name="new_rate" id="rateInput" class="rate-input"
                           value="<?= $admin_rate_pct ?>" min="0" max="100" step="0.5" required>
                    <span class="rate-suffix">%</span>
                </div>
                <input type="range" id="rateSlider" class="rate-slider" min="0" max="100" step="0.5" value="<?= $admin_rate_pct ?>">

                <button type="submit" class="btn-save">
                    <i class="las la-save"></i> حفظ نسبة العمولة
                </button>
            </form>

            <div class="warn-note">
                <i class="las la-exclamation-triangle"></i>
                <span>تنبيه: تعديل النسبة يطبَّق فوراً على كل الطلبات المكتملة الجديدة وعلى حسابات التقارير والرسوم البيانية القادمة. الطلبات السابقة المحفوظة في السجلات لا تتأثر بالتعديل.</span>
            </div>
        </div>

        <!-- ── أمثلة تطبيقية ── -->
        <div class="set-card">
            <h5><i class="las la-calculator"></i> أمثلة على التوزيع بالنسبة الحالية</h5>
            <?php foreach ($examples as $amt):
                $a = round($amt * $ADMIN_RATE, 2);
                $p = round($amt - $a, 2);
            ?>
            <div class="example-row">
                <span class="ex-amount"><?= number_format($amt,0) ?> ₪</span>
                <div class="ex-split">
                    <span><b class="a">أدمن:</b> <?= number_format($a,2) ?> ₪</span>
                    <span><b class="p">مزود:</b> <?= number_format($p,2) ?> ₪</span>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="warn-note" style="margin-top:22px;">
                <i class="las la-info-circle"></i>
                <span>هذه النسبة تُستخدم في حساب عمولة الأدمن وصافي أرباح المزودين في كل من صفحة <a href="financial_report.php" style="color:var(--warning);text-decoration:underline;">التقارير</a> وصفحة <a href="financial_charts.php" style="color:var(--warning);text-decoration:underline;">الرسوم البيانية</a>.</span>
            </div>
        </div>

    </div>

</main>

<script>
const rateInput  = document.getElementById('rateInput');
const rateSlider = document.getElementById('rateSlider');
const liveVal    = document.getElementById('liveRateVal');
const splitAdmin = document.getElementById('splitAdmin');
const splitProv  = document.getElementById('splitProv');

function clamp(n){ return Math.min(100, Math.max(0, n)); }

function updatePreview(val){
    val = clamp(parseFloat(val) || 0);
    const provVal = (100 - val);
    liveVal.textContent = val.toFixed(2).replace(/\.00$/,'') + '%';
    splitAdmin.style.width = val + '%';
    splitProv.style.width  = provVal + '%';
    splitAdmin.textContent = 'عمولة ' + val.toFixed(2).replace(/\.00$/,'') + '%';
    splitProv.textContent  = 'مزود ' + provVal.toFixed(2).replace(/\.00$/,'') + '%';
}

rateInput.addEventListener('input', () => {
    rateSlider.value = clamp(parseFloat(rateInput.value) || 0);
    updatePreview(rateInput.value);
});
rateSlider.addEventListener('input', () => {
    rateInput.value = rateSlider.value;
    updatePreview(rateSlider.value);
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