<?php
// admin/add_service.php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

check_login('admin');
$current_page = 'add_service';

global $pdo;

$errors    = [];
$form_data = [
    'title'       => '',
    'description' => '',
    'price'       => '',
    'city'        => '',
    'category_id' => '',
    'provider_id' => '',
    'is_active'   => 1,
];

// ── جلب التصنيفات ──────────────────────────────────────────
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; }

// ── جلب المزودين ───────────────────────────────────────────
try {
    $providers = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('provider','admin') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $providers = []; }

// ── معالجة POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_data['title']       = trim($_POST['title']       ?? '');
    $form_data['description'] = trim($_POST['description'] ?? '');
    $form_data['price']       = trim($_POST['price']       ?? '');
    $form_data['city']        = trim($_POST['city']        ?? '');
    $form_data['category_id'] = (int)($_POST['category_id'] ?? 0);
    $form_data['provider_id'] = (int)($_POST['provider_id'] ?? 0);
    $form_data['is_active']   = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (mb_strlen($form_data['title']) < 3)           $errors[] = "عنوان الخدمة يجب أن يكون 3 أحرف على الأقل.";
    if (mb_strlen($form_data['description']) < 10)    $errors[] = "الوصف يجب أن يكون 10 أحرف على الأقل.";
    if (!is_numeric($form_data['price']) || $form_data['price'] <= 0) $errors[] = "السعر يجب أن يكون رقماً موجباً.";
    if ($form_data['provider_id'] <= 0)               $errors[] = "يجب اختيار مزود خدمة.";

    // رفع الصورة
    $image_filename = null;
    if (empty($errors) && isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . "/../assets/uploads/services/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext     = strtolower(pathinfo($_FILES['service_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $max_size = 3 * 1024 * 1024; // 3MB

        if (!in_array($ext, $allowed)) {
            $errors[] = "صيغة الصورة غير مدعومة. المسموح: JPG, PNG, GIF, WEBP.";
        } elseif ($_FILES['service_image']['size'] > $max_size) {
            $errors[] = "حجم الصورة يتجاوز الحد الأقصى (3 ميجابايت).";
        } else {
            $image_filename = uniqid('srv_', true) . '.' . $ext;
            if (!move_uploaded_file($_FILES['service_image']['tmp_name'], $upload_dir . $image_filename)) {
                $errors[] = "فشل رفع الصورة.";
                $image_filename = null;
            }
        }
    }

    // الإدخال في قاعدة البيانات
    if (empty($errors)) {
        try {
            $pdo->prepare("INSERT INTO services (title, description, price, city, category_id, provider_id, image, is_active, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                ->execute([
                    $form_data['title'],
                    $form_data['description'],
                    $form_data['price'],
                    $form_data['city'] ?: null,
                    $form_data['category_id'] ?: null,
                    $form_data['provider_id'],
                    $image_filename,
                    $form_data['is_active'],
                ]);
            set_message("✅ تمت إضافة الخدمة «{$form_data['title']}» بنجاح!", "success");
            header("Location: manage_services.php"); exit();
        } catch (PDOException $e) {
            error_log("add_service error: " . $e->getMessage());
            // حذف الصورة المرفوعة إن وُجدت
            if ($image_filename && isset($upload_dir)) @unlink($upload_dir . $image_filename);
            $errors[] = "حدث خطأ في قاعدة البيانات.";
        }
    }
}

$admin_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إضافة خدمة جديدة | المدير</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
/* ============================================================
   XATO DARK — add_service.php — matches manage_services.php
============================================================ */
:root {
    --dark-bg:      #060818;
    --sidebar-bg:   #0e1726;
    --card-bg:      #0e1726;
    --card-border:  #1b2e4b;
    --header-bg:    #0e1726;
    --text-primary: #e0e6ed;
    --text-muted:   #888ea8;
    --text-dark:    #bfc9d4;
    --primary:      #4361ee;
    --primary-light:rgba(67,97,238,.15);
    --success:      #00ab55;
    --success-light:rgba(0,171,85,.15);
    --warning:      #e2a03f;
    --warning-light:rgba(226,160,63,.15);
    --danger:       #e7515a;
    --danger-light: rgba(231,81,90,.15);
    --info:         #2196f3;
    --info-light:   rgba(33,150,243,.15);
    --purple:       #805dca;
    --purple-light: rgba(128,93,202,.15);
    --sidebar-width:255px;
    --header-height:70px;
    --radius:       8px;
    --shadow:       0 6px 10px rgba(0,0,0,.4);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Tajawal',sans-serif;background:var(--dark-bg);color:var(--text-primary);font-size:14px;direction:rtl}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:var(--sidebar-bg)}
::-webkit-scrollbar-thumb{background:#1b2e4b;border-radius:10px}

/* ── HEADER ──────────────────────────────────────────────── */
.app-header{
    position:fixed;top:0;right:0;left:0;
    height:var(--header-height);
    background:var(--header-bg);
    border-bottom:1px solid var(--card-border);
    z-index:1030;
    display:flex;align-items:center;
    padding:0 20px;gap:15px;
}
.header-logo{display:flex;align-items:center;gap:10px;min-width:200px}
.header-logo .logo-icon{
    width:36px;height:36px;background:var(--danger);
    border-radius:8px;display:flex;align-items:center;justify-content:center;
    font-size:18px;color:#fff;
}
.header-logo span{font-size:18px;font-weight:800;color:var(--text-primary)}
.header-toggle{background:none;border:none;color:var(--text-muted);font-size:22px;cursor:pointer;padding:5px 8px;border-radius:6px;transition:all .2s}
.header-toggle:hover{background:var(--primary-light);color:var(--primary)}
.header-icon-btn{
    width:38px;height:38px;background:var(--dark-bg);
    border:1px solid var(--card-border);border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    color:var(--text-muted);font-size:18px;transition:all .2s;
}
.header-icon-btn:hover{border-color:var(--primary);color:var(--primary)}
.header-user{display:flex;align-items:center;gap:10px;padding:5px 10px;border-radius:8px;transition:background .2s}
.header-user:hover{background:var(--primary-light)}
.user-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--danger),var(--purple));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff}
.user-info .user-name{font-size:13px;font-weight:700;color:var(--text-primary);display:block;line-height:1.2}
.user-info .user-role{font-size:11px;color:var(--text-muted);display:block}

/* ── SIDEBAR ─────────────────────────────────────────────── */
.app-sidebar{
    position:fixed;top:var(--header-height);right:0;
    width:var(--sidebar-width);
    height:calc(100vh - var(--header-height));
    background:var(--sidebar-bg);border-left:1px solid var(--card-border);
    overflow-y:auto;z-index:1020;transition:transform .3s ease;
}
.app-sidebar.collapsed{transform:translateX(var(--sidebar-width))}
.sidebar-section-title{padding:20px 20px 8px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
.sidebar-menu{list-style:none;padding:5px 10px}
.sidebar-menu li{margin-bottom:2px}
.sidebar-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:var(--radius);color:var(--text-muted);font-size:14px;font-weight:500;transition:all .2s}
.sidebar-menu a i{font-size:18px;min-width:22px}
.sidebar-menu a:hover{background:var(--primary-light);color:var(--primary)}
.sidebar-menu a.active{background:var(--primary);color:#fff;box-shadow:0 4px 15px rgba(67,97,238,.4)}
.sidebar-menu a.logout-link{color:var(--danger)}
.sidebar-menu a.logout-link:hover{background:var(--danger-light)}
.user-profile-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);padding:20px;margin:15px 10px;text-align:center}
.profile-avatar-lg{width:64px;height:64px;background:linear-gradient(135deg,var(--danger),var(--purple));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#fff;margin:0 auto 12px}
.profile-name{font-size:15px;font-weight:700;color:var(--text-primary)}
.profile-email{font-size:12px;color:var(--text-muted);margin-top:2px}
.profile-divider{border:none;border-top:1px solid var(--card-border);margin:12px 0}
.btn-edit-profile{display:block;width:100%;padding:8px;background:var(--danger-light);color:var(--danger);border-radius:6px;font-size:13px;font-weight:600;margin-top:12px;transition:all .2s}
.btn-edit-profile:hover{background:var(--danger);color:#fff}

/* ── MAIN ────────────────────────────────────────────────── */
.app-main{
    margin-right:var(--sidebar-width);
    margin-top:var(--header-height);
    padding:25px;
    min-height:calc(100vh - var(--header-height));
    transition:margin-right .3s ease;
}
.app-main.expanded{margin-right:0}

/* ── PAGE TITLE ──────────────────────────────────────────── */
.page-title-area{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px;flex-wrap:wrap;gap:10px}
.page-title h2{font-size:22px;font-weight:800;color:var(--text-primary);margin:0}
.breadcrumb-custom{display:flex;align-items:center;gap:6px;list-style:none;padding:0;margin:5px 0 0}
.breadcrumb-custom li{font-size:12px;color:var(--text-muted)}
.breadcrumb-custom li a{color:var(--primary)}
.breadcrumb-custom li:not(:last-child)::after{content:'/';margin-right:6px;color:var(--card-border)}

/* ── ALERT ───────────────────────────────────────────────── */
.xato-alert{padding:13px 16px;border-radius:var(--radius);margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;font-size:13px;font-weight:500}
.xato-alert i{font-size:19px;flex-shrink:0;margin-top:1px}
.xato-alert ul{list-style:none;display:flex;flex-direction:column;gap:4px}
.xato-alert ul li::before{content:'• '}
.xato-alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid rgba(231,81,90,.25)}
.xato-alert-success{background:var(--success-light);color:var(--success);border:1px solid rgba(0,171,85,.25)}

/* ── FORM LAYOUT ─────────────────────────────────────────── */
.form-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start}

/* ── CARD ────────────────────────────────────────────────── */
.xato-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);overflow:hidden;margin-bottom:20px}
.xato-card-header{padding:15px 20px;border-bottom:1px solid var(--card-border);display:flex;align-items:center;gap:10px}
.xato-card-header h5{font-size:15px;font-weight:700;color:var(--text-primary);margin:0}
.xato-card-header i{font-size:19px}
.xato-card-body{padding:20px}

/* ── FORM FIELDS ─────────────────────────────────────────── */
.form-group{margin-bottom:18px}
.form-group:last-child{margin-bottom:0}
.form-label{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--text-dark);margin-bottom:7px}
.form-label i{font-size:15px;color:var(--text-muted)}
.req{color:var(--danger);font-size:15px;line-height:1}

.input-wrap{position:relative}
.input-icon{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;pointer-events:none;transition:color .2s}
.form-control{
    width:100%;
    padding:10px 36px 10px 12px;
    background:var(--dark-bg);
    border:1px solid var(--card-border);
    border-radius:6px;
    color:var(--text-primary);
    font-family:'Tajawal',sans-serif;
    font-size:14px;
    outline:none;
    transition:border-color .2s,box-shadow .2s;
}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
.form-control:focus + .input-icon{color:var(--primary)}
.form-control::placeholder{color:var(--text-muted);font-weight:400}
.form-control.no-icon{padding-right:12px}
select.form-control{cursor:pointer;appearance:none}
select.form-control option{background:var(--card-bg)}
textarea.form-control{padding:10px 12px;resize:vertical;min-height:110px;line-height:1.7}

.form-hint{font-size:11px;color:var(--text-muted);margin-top:5px}

/* Char counter */
.char-counter{font-size:11px;color:var(--text-muted);margin-top:5px;text-align:left}
.char-counter.warn{color:var(--warning)}
.char-counter.over{color:var(--danger)}

/* Price row */
.price-input-wrap{position:relative}
.price-input-wrap .currency-badge{
    position:absolute;left:0;top:0;bottom:0;
    display:flex;align-items:center;padding:0 12px;
    background:rgba(27,46,75,.6);border-right:1px solid var(--card-border);
    border-radius:0 6px 6px 0;
    font-size:12px;font-weight:700;color:var(--text-muted);
    pointer-events:none;
}
.price-input-wrap .form-control{padding-left:54px}

/* Toggle switch */
.toggle-group{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;background:var(--dark-bg);border:1px solid var(--card-border);border-radius:7px}
.toggle-info .tg-title{font-size:13px;font-weight:700;color:var(--text-primary)}
.toggle-info .tg-desc{font-size:11px;color:var(--text-muted);margin-top:2px}
.switch{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;inset:0;background:var(--card-border);border-radius:24px;transition:background .25s}
.slider::before{content:'';position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:var(--text-muted);transition:transform .25s,background .25s}
.switch input:checked + .slider{background:var(--success)}
.switch input:checked + .slider::before{transform:translateX(20px);background:#fff}

/* Image upload */
.upload-zone{
    border:2px dashed var(--card-border);
    border-radius:var(--radius);
    padding:28px 16px;
    text-align:center;
    cursor:pointer;
    transition:all .25s;
    background:var(--dark-bg);
    position:relative;
}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--primary);background:var(--primary-light)}
.upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-icon{font-size:32px;color:var(--primary);margin-bottom:10px}
.upload-title{font-size:14px;font-weight:700;color:var(--text-primary)}
.upload-sub{font-size:11px;color:var(--text-muted);margin-top:4px}
.upload-preview{display:none;margin-top:14px;position:relative;text-align:center}
.upload-preview img{max-height:140px;border-radius:6px;border:1px solid var(--card-border)}
.remove-img{position:absolute;top:-8px;left:50%;transform:translateX(-50%) translateX(50px);background:var(--danger);color:#fff;border:none;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center}

/* Provider card select */
.provider-option{display:flex;flex-direction:column}
.provider-option .p-name{font-size:13px;font-weight:700}
.provider-option .p-email{font-size:11px;color:var(--text-muted)}

/* Submit bar */
.submit-bar{
    display:flex;align-items:center;gap:12px;
    padding:16px 20px;
    border-top:1px solid var(--card-border);
    flex-wrap:wrap;
}
.btn-primary{
    display:inline-flex;align-items:center;gap:7px;
    padding:10px 22px;
    background:var(--primary);color:#fff;border:none;
    border-radius:6px;font-family:'Tajawal',sans-serif;
    font-size:14px;font-weight:700;cursor:pointer;
    transition:all .2s;
    box-shadow:0 4px 14px rgba(67,97,238,.35);
}
.btn-primary:hover{opacity:.88;transform:translateY(-1px)}
.btn-ghost{
    display:inline-flex;align-items:center;gap:6px;
    padding:10px 18px;
    background:transparent;border:1px solid var(--card-border);
    border-radius:6px;color:var(--text-muted);
    font-family:'Tajawal',sans-serif;font-size:14px;font-weight:600;
    cursor:pointer;transition:all .2s;
}
.btn-ghost:hover{border-color:var(--danger);color:var(--danger);background:var(--danger-light)}
.submit-note{font-size:12px;color:var(--text-muted);margin-right:auto}

/* ── SIDEBAR COLUMN ──────────────────────────────────────── */
.side-col{display:flex;flex-direction:column;gap:18px}

/* Tips */
.tip-item{display:flex;gap:10px;align-items:flex-start;margin-bottom:14px}
.tip-item:last-child{margin-bottom:0}
.tip-icon{width:30px;height:30px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px}
.tip-text{font-size:12px;color:var(--text-muted);line-height:1.6;margin-top:5px}
.tip-text strong{color:var(--text-dark);display:block;font-size:12px;font-weight:700;margin-bottom:2px}

/* Quick preview */
.service-preview-card{
    background:var(--dark-bg);border:1px solid var(--card-border);
    border-radius:var(--radius);overflow:hidden;
}
.preview-image-area{
    height:110px;background:linear-gradient(135deg,rgba(67,97,238,.2),rgba(128,93,202,.2));
    display:flex;align-items:center;justify-content:center;
    font-size:38px;color:rgba(67,97,238,.5);
    position:relative;overflow:hidden;
}
.preview-image-area img{width:100%;height:100%;object-fit:cover;display:none}
.preview-body{padding:14px}
.preview-title{font-size:14px;font-weight:800;color:var(--text-primary);margin-bottom:5px;min-height:18px}
.preview-badge{
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 9px;border-radius:20px;
    font-size:11px;font-weight:700;
    background:var(--success-light);color:var(--success);
    margin-bottom:8px;
}
.preview-price{font-size:16px;font-weight:900;color:var(--primary)}
.preview-label{font-size:11px;color:var(--text-muted)}
.preview-provider{display:flex;align-items:center;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid var(--card-border)}
.preview-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--purple));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}
.preview-pname{font-size:12px;font-weight:700;color:var(--text-primary)}

/* divider */
.form-divider{border:none;border-top:1px solid var(--card-border);margin:18px 0}

/* ── RESPONSIVE ──────────────────────────────────────────── */
@media(max-width:900px){
    .app-sidebar{transform:translateX(var(--sidebar-width))}
    .app-sidebar.mobile-open{transform:translateX(0)}
    .app-main{margin-right:0;padding:16px}
    .form-layout{grid-template-columns:1fr}
    .user-info{display:none}
}
@media(max-width:600px){
    .submit-bar{flex-direction:column;align-items:stretch}
    .submit-note{margin-right:0;text-align:center}
}

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

<!-- ══════════ HEADER ══════════ -->
<header class="app-header">
    <a href="/local_services/dashboard.php" class="header-logo">
        <div class="logo-icon"><i class="las la-shield-alt"></i></div>
        <span>لوحة المدير</span>
    </a>
    <button class="header-toggle" id="sidebarToggle"><i class="las la-bars"></i></button>
    <div style="flex:1;"></div>
    <a href="/local_services/services.php" class="header-icon-btn" title="الموقع"><i class="las la-external-link-alt"></i></a>
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

<!-- ══════════ SIDEBAR ══════════ -->
<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
<!-- ══════════ MAIN ══════════ -->
<main class="app-main" id="appMain">

    <!-- Page Header -->
    <div class="page-title-area">
        <div class="page-title">
            <h2><i class="las la-plus-circle" style="color:var(--primary);margin-left:8px;"></i>إضافة خدمة جديدة</h2>
            <ul class="breadcrumb-custom">
                <li><a href="/local_services/dashboard.php">لوحة التحكم</a></li>
                <li><a href="manage_services.php">الخدمات</a></li>
                <li class="active">إضافة خدمة</li>
            </ul>
        </div>
        <a href="manage_services.php" class="btn-ghost">
            <i class="las la-arrow-right"></i> العودة للقائمة
        </a>
    </div>

    <!-- Alerts -->
    <?php if (!empty($errors)): ?>
    <div class="xato-alert xato-alert-danger">
        <i class="las la-exclamation-triangle"></i>
        <div>
            <strong style="display:block;margin-bottom:5px;">يرجى تصحيح الأخطاء التالية:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- Form -->
    <form method="POST" enctype="multipart/form-data" id="addServiceForm" novalidate>

        <div class="form-layout">

            <!-- ══ LEFT: Main Form ══ -->
            <div>

                <!-- Section 1: Basic Info -->
                <div class="xato-card">
                    <div class="xato-card-header">
                        <i class="las la-concierge-bell" style="color:var(--primary);"></i>
                        <h5>معلومات الخدمة</h5>
                    </div>
                    <div class="xato-card-body">

                        <!-- Title -->
                        <div class="form-group">
                            <label class="form-label" for="title">
                                <i class="las la-heading"></i>
                                عنوان الخدمة <span class="req">*</span>
                            </label>
                            <div class="input-wrap">
                                <input type="text" id="title" name="title"
                                       class="form-control"
                                       placeholder="مثال: تصليح أجهزة التبريد والتكييف"
                                       value="<?php echo htmlspecialchars($form_data['title']); ?>"
                                       maxlength="120" autocomplete="off">
                                <i class="las la-heading input-icon"></i>
                            </div>
                            <div class="char-counter" id="titleCounter">0 / 120</div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label" for="description">
                                <i class="las la-align-left"></i>
                                وصف الخدمة <span class="req">*</span>
                            </label>
                            <textarea id="description" name="description"
                                      class="form-control no-icon"
                                      placeholder="اشرح الخدمة بالتفصيل: ماذا تشمل، ما المميزات، ما الضمانات..."
                                      maxlength="1000"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                            <div class="char-counter" id="descCounter">0 / 1000</div>
                        </div>

                        <!-- Price + City -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                            <div class="form-group">
                                <label class="form-label" for="price">
                                    <i class="las la-dollar-sign"></i>
                                    السعر <span class="req">*</span>
                                </label>
                                <div class="price-input-wrap">
                                    <input type="number" id="price" name="price" step="0.01" min="0"
                                           class="form-control"
                                           placeholder="0.00"
                                           value="<?php echo htmlspecialchars($form_data['price']); ?>">
                                    <span class="currency-badge">₪</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="city">
                                    <i class="las la-map-marker-alt"></i>
                                    المدينة
                                </label>
                                <div class="input-wrap">
                                    <input type="text" id="city" name="city"
                                           class="form-control"
                                           placeholder="مثال: رام الله"
                                           value="<?php echo htmlspecialchars($form_data['city']); ?>"
                                           list="cityList">
                                    <i class="las la-map-marker-alt input-icon"></i>
                                    <datalist id="cityList">
                                        <option value="رام الله"><option value="نابلس"><option value="الخليل">
                                        <option value="جنين"><option value="طولكرم"><option value="أريحا">
                                        <option value="بيت لحم"><option value="قلقيلية"><option value="سلفيت">
                                        <option value="طوباس"><option value="أريحا والأغوار">
                                    </datalist>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Section 2: Provider + Category -->
                <div class="xato-card">
                    <div class="xato-card-header">
                        <i class="las la-user-tie" style="color:var(--purple);"></i>
                        <h5>المزود والتصنيف</h5>
                    </div>
                    <div class="xato-card-body">

                        <!-- Provider -->
                        <div class="form-group">
                            <label class="form-label" for="provider_id">
                                <i class="las la-user-tie"></i>
                                مزود الخدمة <span class="req">*</span>
                            </label>
                            <div class="input-wrap">
                                <select id="provider_id" name="provider_id" class="form-control">
                                    <option value="">— اختر مزود الخدمة —</option>
                                    <?php foreach ($providers as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($p['full_name']); ?>"
                                            <?php echo $form_data['provider_id'] == $p['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['full_name']); ?>
                                        (<?php echo htmlspecialchars($p['email']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="las la-user-tie input-icon"></i>
                            </div>
                            <?php if (empty($providers)): ?>
                            <div class="form-hint" style="color:var(--warning);">
                                <i class="las la-exclamation-triangle"></i>
                                لا يوجد مزودو خدمة مسجّلون. أضف مستخدماً بدور "مزود خدمة" أولاً.
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label class="form-label" for="category_id">
                                <i class="las la-tags"></i>
                                التصنيف
                                <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(اختياري)</span>
                            </label>
                            <div class="input-wrap">
                                <select id="category_id" name="category_id" class="form-control">
                                    <option value="">— بدون تصنيف —</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                            <?php echo $form_data['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="las la-tags input-icon"></i>
                            </div>
                        </div>

                        <hr class="form-divider">

                        <!-- Active toggle -->
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="tg-title"><i class="las la-toggle-on" style="color:var(--success);margin-left:5px;"></i> حالة الخدمة</div>
                                <div class="tg-desc">هل تُعرض الخدمة للزوار فور الإضافة؟</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="is_active" id="is_active"
                                       <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                    </div>
                </div>

                <!-- Section 3: Image -->
                <div class="xato-card">
                    <div class="xato-card-header">
                        <i class="las la-image" style="color:var(--warning);"></i>
                        <h5>صورة الخدمة <span style="font-size:12px;color:var(--text-muted);font-weight:400;">(اختياري)</span></h5>
                    </div>
                    <div class="xato-card-body">
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="service_image" id="serviceImage"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <div id="uploadPlaceholder">
                                <div class="upload-icon"><i class="las la-cloud-upload-alt"></i></div>
                                <div class="upload-title">اسحب الصورة هنا أو انقر للاختيار</div>
                                <div class="upload-sub">JPG · PNG · GIF · WEBP — الحد الأقصى 3 ميجابايت</div>
                            </div>
                            <div class="upload-preview" id="uploadPreview">
                                <img id="previewImg" src="" alt="معاينة">
                                <button type="button" class="remove-img" id="removeImg" title="إزالة الصورة">
                                    <i class="las la-times"></i>
                                </button>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:6px;" id="imgName"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="submit-bar">
                        <button type="submit" class="btn-primary">
                            <i class="las la-plus-circle"></i> إضافة الخدمة
                        </button>
                        <a href="manage_services.php" class="btn-ghost">
                            <i class="las la-times"></i> إلغاء
                        </a>
                        <span class="submit-note">
                            الحقول المميزة بـ <span style="color:var(--danger);">*</span> إلزامية
                        </span>
                    </div>
                </div>

            </div><!-- end left col -->

            <!-- ══ RIGHT: Preview & Tips ══ -->
            <div class="side-col">

                <!-- Live Preview -->
                <div class="xato-card">
                    <div class="xato-card-header">
                        <i class="las la-eye" style="color:var(--success);"></i>
                        <h5>معاينة البطاقة</h5>
                    </div>
                    <div class="xato-card-body" style="padding:14px;">
                        <div class="service-preview-card">
                            <div class="preview-image-area" id="previewImageArea">
                                <i class="las la-concierge-bell"></i>
                                <img id="previewCardImg" src="" alt="">
                            </div>
                            <div class="preview-body">
                                <div class="preview-title" id="previewTitle">عنوان الخدمة</div>
                                <div class="preview-badge" id="previewStatus">
                                    <span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                                    نشطة
                                </div>
                                <div style="display:flex;align-items:baseline;gap:5px;margin-top:4px;">
                                    <span class="preview-price" id="previewPrice">—</span>
                                    <span class="preview-label">₪</span>
                                </div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;" id="previewCity"></div>
                                <div class="preview-provider">
                                    <div class="preview-avatar" id="previewAvatar">؟</div>
                                    <div class="preview-pname" id="previewProvider">— اختر المزود —</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips -->
                <div class="xato-card">
                    <div class="xato-card-header">
                        <i class="las la-lightbulb" style="color:var(--warning);"></i>
                        <h5>إرشادات</h5>
                    </div>
                    <div class="xato-card-body">
                        <div class="tip-item">
                            <div class="tip-icon" style="background:var(--primary-light);color:var(--primary);"><i class="las la-heading"></i></div>
                            <div class="tip-text">
                                <strong>العنوان</strong>
                                اجعله واضحاً ومحدداً، يصف الخدمة بدقة في أقل من 10 كلمات.
                            </div>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon" style="background:var(--success-light);color:var(--success);"><i class="las la-align-left"></i></div>
                            <div class="tip-text">
                                <strong>الوصف</strong>
                                اذكر ما تشمله الخدمة، المدة الزمنية، وأي ضمانات تُقدم.
                            </div>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon" style="background:var(--warning-light);color:var(--warning);"><i class="las la-dollar-sign"></i></div>
                            <div class="tip-text">
                                <strong>السعر</strong>
                                حدد سعراً تنافسياً. يمكن أن يكون سعراً ثابتاً أو ابتداءً من.
                            </div>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon" style="background:var(--info-light);color:var(--info);"><i class="las la-image"></i></div>
                            <div class="tip-text">
                                <strong>الصورة</strong>
                                صورة عالية الجودة تزيد التفاعل بنسبة 60٪. الأبعاد المثالية: 800×600.
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- end right col -->

        </div><!-- end form-layout -->
    </form>

</main>

<!-- ══════════ SCRIPTS ══════════ -->
<script>
(function(){
    'use strict';

    // ── Sidebar Toggle ───────────────────────────────────────
    const sidebar  = document.getElementById('appSidebar');
    const mainArea = document.getElementById('appMain');
    const toggle   = document.getElementById('sidebarToggle');
    let isMobile = window.innerWidth <= 900;
    toggle.addEventListener('click', () => {
        if (isMobile) sidebar.classList.toggle('mobile-open');
        else { sidebar.classList.toggle('collapsed'); mainArea.classList.toggle('expanded'); }
    });
    window.addEventListener('resize', () => { isMobile = window.innerWidth <= 900; });

    // ── Char Counters ────────────────────────────────────────
    function makeCounter(inputId, counterId, max) {
        const el  = document.getElementById(inputId);
        const cnt = document.getElementById(counterId);
        if (!el || !cnt) return;
        function update() {
            const len = el.value.length;
            cnt.textContent = `${len} / ${max}`;
            cnt.className = 'char-counter' + (len > max * .9 ? (len >= max ? ' over' : ' warn') : '');
        }
        el.addEventListener('input', update); update();
    }
    makeCounter('title', 'titleCounter', 120);
    makeCounter('description', 'descCounter', 1000);

    // ── Live Preview ─────────────────────────────────────────
    const titleEl    = document.getElementById('title');
    const priceEl    = document.getElementById('price');
    const cityEl     = document.getElementById('city');
    const providerEl = document.getElementById('provider_id');
    const activeEl   = document.getElementById('is_active');

    const previewTitle    = document.getElementById('previewTitle');
    const previewPrice    = document.getElementById('previewPrice');
    const previewCity     = document.getElementById('previewCity');
    const previewProvider = document.getElementById('previewProvider');
    const previewAvatar   = document.getElementById('previewAvatar');
    const previewStatus   = document.getElementById('previewStatus');

    function updatePreview() {
        previewTitle.textContent = titleEl.value.trim() || 'عنوان الخدمة';
        previewPrice.textContent = priceEl.value ? parseFloat(priceEl.value).toLocaleString('ar') : '—';
        previewCity.textContent  = cityEl.value.trim() ? '📍 ' + cityEl.value.trim() : '';

        const selOpt = providerEl.options[providerEl.selectedIndex];
        if (providerEl.value && selOpt) {
            const name = selOpt.dataset.name || selOpt.text.split('(')[0].trim();
            previewProvider.textContent = name;
            previewAvatar.textContent   = name ? name[0] : '؟';
        } else {
            previewProvider.textContent = '— اختر المزود —';
            previewAvatar.textContent   = '؟';
        }

        if (activeEl.checked) {
            previewStatus.style.background = 'var(--success-light)';
            previewStatus.style.color      = 'var(--success)';
            previewStatus.innerHTML        = '<span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block;margin-left:4px;"></span> نشطة';
        } else {
            previewStatus.style.background = 'var(--danger-light)';
            previewStatus.style.color      = 'var(--danger)';
            previewStatus.innerHTML        = '<span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block;margin-left:4px;"></span> موقوفة';
        }
    }

    [titleEl, priceEl, cityEl, providerEl, activeEl].forEach(el => {
        if (!el) return;
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    updatePreview();

    // ── Image Upload Preview ──────────────────────────────────
    const fileInput   = document.getElementById('serviceImage');
    const uploadZone  = document.getElementById('uploadZone');
    const placeholder = document.getElementById('uploadPlaceholder');
    const preview     = document.getElementById('uploadPreview');
    const previewImg  = document.getElementById('previewImg');
    const previewCardImg = document.getElementById('previewCardImg');
    const imgName     = document.getElementById('imgName');
    const removeBtn   = document.getElementById('removeImg');

    fileInput?.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        // 3MB check client-side
        if (file.size > 3 * 1024 * 1024) {
            alert('حجم الصورة يتجاوز 3 ميجابايت.');
            this.value = ''; return;
        }
        const url = URL.createObjectURL(file);
        previewImg.src = url;
        previewCardImg.src = url;
        previewCardImg.style.display = 'block';
        placeholder.style.display = 'none';
        preview.style.display     = 'block';
        imgName.textContent = file.name;
    });

    removeBtn?.addEventListener('click', function (e) {
        e.stopPropagation();
        fileInput.value = '';
        previewImg.src  = '';
        previewCardImg.src = '';
        previewCardImg.style.display = 'none';
        placeholder.style.display = 'block';
        preview.style.display     = 'none';
    });

    // Drag & drop
    uploadZone?.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
    uploadZone?.addEventListener('drop', e => {
        e.preventDefault(); uploadZone.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            const dt = new DataTransfer(); dt.items.add(file);
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

})();
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
</body>
</html>