<?php
// admin/edit_service.php - تعديل بيانات خدمة موجودة

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

// 🛑 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 
$current_page = 'edit_service';


global $pdo;
$errors = [];
$service_id = 0;
$current_service = [];

// ==========================================================
// 2. جلب ID الخدمة والتحقق منها
// ==========================================================
$service_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$service_id = (int)$service_id;

if ($service_id <= 0) {
    set_message("معرف الخدمة غير صالح أو مفقود في الرابط.", "danger");
    header("Location: manage_services.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $current_service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_service) {
        set_message("الخدمة المطلوبة غير موجودة.", "danger");
        header("Location: manage_services.php");
        exit();
    }
} catch (PDOException $e) {
    set_message("فشل في جلب بيانات الخدمة: " . $e->getMessage(), "danger");
    header("Location: manage_services.php");
    exit();
}

$form_data = $current_service;

// ==========================================================
// 4. جلب القوائم المنسدلة
// ==========================================================
try {
    $stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

try {
    $stmt_providers = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('provider', 'admin') ORDER BY full_name ASC");
    $providers = $stmt_providers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $providers = [];
}

// ==========================================================
// 5. معالجة تحديث الخدمة (POST Request)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data['title']       = sanitize_input($_POST['title'] ?? '');
    $form_data['description'] = sanitize_input($_POST['description'] ?? '');
    $form_data['price']       = sanitize_input($_POST['price'] ?? '');
    $form_data['category_id'] = (int) sanitize_input($_POST['category_id'] ?? 0);
    $form_data['provider_id'] = (int) sanitize_input($_POST['provider_id'] ?? 0);
    $form_data['is_active']   = (int) sanitize_input($_POST['is_active'] ?? 0);
    
    if (empty($form_data['title']))       $errors[] = "عنوان الخدمة مطلوب.";
    if (empty($form_data['description'])) $errors[] = "وصف الخدمة مطلوب.";
    if (!is_numeric($form_data['price']) || $form_data['price'] <= 0) $errors[] = "السعر يجب أن يكون رقماً موجباً.";
    if ($form_data['provider_id'] <= 0)  $errors[] = "يجب اختيار مزود خدمة.";
    
    $image_filename = $current_service['image'];
    $upload_dir = __DIR__ . "/../assets/uploads/services/";

    if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
        if (!empty($current_service['image'])) {
            $old_file_path = $upload_dir . $current_service['image'];
            if (file_exists($old_file_path)) unlink($old_file_path);
        }
        $file_tmp  = $_FILES['service_image']['tmp_name'];
        $file_name = $_FILES['service_image']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "الرجاء رفع صورة بصيغة JPG, PNG, GIF, أو WEBP.";
        } else {
            $image_filename = uniqid('service_', true) . '.' . $file_ext;
            $destination = $upload_dir . $image_filename;
            if (!move_uploaded_file($file_tmp, $destination)) {
                $errors[] = "فشل في حفظ ملف الصورة الجديدة على الخادم.";
                $image_filename = $current_service['image'];
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $sql = "UPDATE services SET 
                    title = ?, description = ?, price = ?, category_id = ?, provider_id = ?, 
                    image = ?, is_active = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $form_data['title'],
                $form_data['description'],
                $form_data['price'],
                $form_data['category_id'] > 0 ? $form_data['category_id'] : NULL,
                $form_data['provider_id'],
                $image_filename,
                $form_data['is_active'],
                $service_id
            ]);
            set_message("تم تحديث بيانات الخدمة '{$form_data['title']}' بنجاح!", "success");
            header("Location: manage_services.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "خطأ في قاعدة البيانات أثناء التحديث: " . $e->getMessage();
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
<title>تعديل الخدمة: <?= htmlspecialchars($current_service['title']) ?> | خدماتي</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
<style>
:root {
    --bg:        #07091a;
    --surface:   #0d1130;
    --surface-2: #111638;
    --surface-3: #182045;
    --border:    rgba(120,130,200,.12);
    --border-2:  rgba(120,130,200,.22);
    --txt:       #e2e8f8;
    --txt-2:     #a8b3d4;
    --muted:     #5a6490;
    --accent:    #7c6af5;
    --acc-2:     #9b8cf7;
    --acc-lt:    rgba(124,106,245,.15);
    --acc-md:    rgba(124,106,245,.3);
    --acc-glow:  rgba(124,106,245,.25);
    --success:   #00c9a7;
    --suc-lt:    rgba(0,201,167,.13);
    --suc-md:    rgba(0,201,167,.28);
    --danger:    #ff6b6b;
    --dan-lt:    rgba(255,107,107,.13);
    --dan-md:    rgba(255,107,107,.28);
    --warning:   #ffd166;
    --war-lt:    rgba(255,209,102,.13);
    --war-md:    rgba(255,209,102,.28);
    --nav-h:   64px;
    --side-w:  255px;
    --r:       8px;
    --r-lg:    12px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Tajawal', sans-serif;
    background: var(--bg);
    color: var(--txt);
    direction: rtl;
    min-height: 100vh;
    font-size: 14px;
    background-image:
        radial-gradient(ellipse at 10% 0%,   rgba(124,106,245,.09) 0%, transparent 50%),
        radial-gradient(ellipse at 90% 100%,  rgba(0,201,167,.06)  0%, transparent 50%);
}
a { text-decoration: none; color: inherit; }
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: var(--surface); }
::-webkit-scrollbar-thumb { background: var(--surface-3); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--accent); }

/* NAV */
.top-nav {
    position: fixed; top: 0; right: 0; left: 0;
    height: var(--nav-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border-2);
    z-index: 300;
    display: flex; align-items: center;
    padding: 0 22px; gap: 12px;
    box-shadow: 0 1px 0 rgba(124,106,245,.12), 0 4px 28px rgba(0,0,0,.5);
}
.nav-brand { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 900; }
.brand-icon {
    width: 34px; height: 34px; border-radius: 9px;
    background: linear-gradient(135deg, var(--accent), #b06cf5);
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; color: #fff;
    box-shadow: 0 3px 14px var(--acc-glow);
}
.brand-pill {
    font-size: 9px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase;
    background: var(--acc-lt); color: var(--acc-2);
    border: 1px solid var(--acc-md); padding: 3px 9px; border-radius: 20px;
}
.nav-spacer { flex: 1; }
.nav-sep { width: 1px; height: 26px; background: var(--border-2); }
.nav-btn {
    width: 36px; height: 36px; border-radius: var(--r);
    background: var(--surface-3); border: 1px solid var(--border);
    color: var(--muted); font-size: 17px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .2s; text-decoration: none;
}
.nav-btn:hover           { border-color: var(--accent); color: var(--accent); background: var(--acc-lt); }
.nav-btn.x-danger:hover  { border-color: var(--danger); color: var(--danger); background: var(--dan-lt); }
.nav-user {
    display: flex; align-items: center; gap: 9px;
    padding: 5px 10px; border-radius: var(--r);
    border: 1px solid var(--border); cursor: pointer; transition: all .2s;
}
.nav-user:hover { background: var(--acc-lt); border-color: var(--acc-md); }
.nav-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--danger));
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800; color: #fff;
    box-shadow: 0 2px 8px var(--acc-glow);
}
.nav-uname { font-size: 12px; font-weight: 700; }
.nav-urole { font-size: 10px; color: var(--acc-2); font-weight: 600; }

/* SIDEBAR */
.sidebar {
    position: fixed; top: var(--nav-h); right: 0;
    width: var(--side-w);
    height: calc(100vh - var(--nav-h));
    background: var(--surface);
    border-left: 1px solid var(--border);
    overflow-y: auto; z-index: 200;
    padding: 14px 10px;
    transition: transform .3s cubic-bezier(.4,0,.2,1);
}
.sidebar.collapsed { transform: translateX(var(--side-w)); }
.side-section {
    font-size: 9px; font-weight: 800; letter-spacing: 2.5px; text-transform: uppercase;
    color: var(--muted); padding: 18px 10px 6px;
    display: flex; align-items: center; gap: 8px;
}
.side-section::after { content: ''; flex: 1; height: 1px; background: linear-gradient(to left, transparent, var(--border-2)); }
.side-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: var(--r);
    color: var(--txt-2); font-size: 13px; font-weight: 600;
    transition: all .18s; margin-bottom: 2px;
}
.side-link i { font-size: 18px; min-width: 22px; }
.side-link:hover { background: var(--acc-lt); color: var(--txt); }
.side-link.active {
    background: linear-gradient(90deg, var(--acc-lt), transparent);
    color: var(--accent); box-shadow: inset 3px 0 0 var(--accent);
}
.side-link.x-exit { color: var(--danger); margin-top: 6px; }
.side-link.x-exit:hover { background: var(--dan-lt); }

/* MAIN */
.main {
    margin-right: var(--side-w); margin-top: var(--nav-h);
    padding: 28px 26px;
    min-height: calc(100vh - var(--nav-h));
    transition: margin-right .3s;
}
.main.expanded { margin-right: 0; }

/* PAGE HEADER */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 26px; flex-wrap: wrap; gap: 14px;
}
.page-eyebrow {
    font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
    color: var(--accent); display: flex; align-items: center; gap: 5px; margin-bottom: 4px;
}
.page-title-text { font-size: 22px; font-weight: 900; }
.page-title-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted); margin-top: 3px; }
.breadcrumb a { color: var(--accent); }
.header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
    background: var(--accent); color: #fff; border: none; border-radius: var(--r);
    font-family: 'Tajawal', sans-serif; font-size: 13px; font-weight: 700; cursor: pointer;
    transition: all .2s; box-shadow: 0 4px 18px var(--acc-glow); text-decoration: none;
}
.btn-primary:hover { background: var(--acc-2); transform: translateY(-1px); box-shadow: 0 6px 22px var(--acc-glow); }
.btn-outline {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
    background: transparent; color: var(--txt-2); border: 1px solid var(--border-2);
    border-radius: var(--r); font-family: 'Tajawal', sans-serif; font-size: 13px; font-weight: 700;
    cursor: pointer; transition: all .2s; text-decoration: none;
}
.btn-outline:hover { border-color: var(--txt-2); color: var(--txt); background: var(--surface-2); }

/* ALERTS */
.alert {
    padding: 12px 16px; border-radius: var(--r); margin-bottom: 20px;
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; font-weight: 600; border: 1px solid;
    animation: slideDown .25s ease;
}
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }
.alert-success { background: var(--suc-lt); color: var(--success); border-color: var(--suc-md); }
.alert-danger  { background: var(--dan-lt); color: var(--danger);  border-color: var(--dan-md); }
.alert-warning { background: var(--war-lt); color: var(--warning); border-color: var(--war-md); }
.alert i { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
.alert ul { margin: 6px 0 0 0; padding-right: 18px; }
.alert ul li { margin-bottom: 3px; font-weight: 500; }

/* FORM LAYOUT */
.edit-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    align-items: start;
}
@media(max-width:900px) { .edit-grid { grid-template-columns: 1fr; } }

/* CARD */
.form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    overflow: hidden;
    box-shadow: 0 4px 30px rgba(0,0,0,.35);
}
.card-head {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
    background: linear-gradient(to left, transparent, var(--acc-lt));
}
.card-head-ico {
    width: 34px; height: 34px; border-radius: 9px;
    background: var(--acc-lt); color: var(--accent);
    display: flex; align-items: center; justify-content: center; font-size: 17px;
}
.card-head h5 { font-size: 14px; font-weight: 800; }
.card-head .card-sub { font-size: 11px; color: var(--muted); margin-top: 1px; }
.card-body { padding: 24px; display: flex; flex-direction: column; gap: 20px; }

/* FIELD */
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width:600px) { .field-row { grid-template-columns: 1fr; } }

label.f-label {
    font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase;
    color: var(--muted); display: flex; align-items: center; gap: 6px;
}
label.f-label i { font-size: 14px; color: var(--accent); }
.f-required { color: var(--danger); font-size: 14px; line-height: 1; }

.f-input, .f-select, .f-textarea {
    width: 100%; padding: 11px 14px;
    background: var(--surface-2); border: 1px solid var(--border-2);
    border-radius: var(--r); color: var(--txt);
    font-family: 'Tajawal', sans-serif; font-size: 14px; outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.f-input::placeholder, .f-textarea::placeholder { color: var(--muted); }
.f-input:focus, .f-select:focus, .f-textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--acc-lt);
    background: var(--surface-3);
}
.f-input:hover:not(:focus), .f-select:hover:not(:focus), .f-textarea:hover:not(:focus) {
    border-color: rgba(120,130,200,.35);
}
.f-textarea { resize: vertical; min-height: 110px; line-height: 1.7; }
.f-select { cursor: pointer; appearance: none; -webkit-appearance: none; }
.f-select option { background: var(--surface-2); }
.select-wrap { position: relative; }
.select-wrap::after {
    content: '\f110'; font-family: 'Line Awesome Free'; font-weight: 900;
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--muted); pointer-events: none; font-size: 14px;
}

/* PRICE INPUT */
.price-wrap { position: relative; }
.price-wrap .f-input { padding-left: 40px; }
.price-symbol {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    font-size: 16px; font-weight: 800; color: var(--success); pointer-events: none;
}

/* STATUS TOGGLE */
.toggle-group { display: flex; gap: 10px; }
.toggle-opt { flex: 1; }
.toggle-opt input[type="radio"] { display: none; }
.toggle-lbl {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 10px 16px; border-radius: var(--r);
    border: 1px solid var(--border-2); cursor: pointer;
    transition: all .2s; font-size: 13px; font-weight: 700; color: var(--muted);
    background: var(--surface-2);
}
.toggle-opt input[type="radio"]:checked + .toggle-lbl.lbl-active {
    background: var(--suc-lt); color: var(--success); border-color: var(--suc-md);
    box-shadow: 0 0 0 2px var(--suc-md);
}
.toggle-opt input[type="radio"]:checked + .toggle-lbl.lbl-paused {
    background: var(--dan-lt); color: var(--danger); border-color: var(--dan-md);
    box-shadow: 0 0 0 2px var(--dan-md);
}
.toggle-lbl:hover { border-color: var(--border-2); background: var(--surface-3); color: var(--txt-2); }

/* IMAGE PANEL */
.img-preview-box {
    border-radius: var(--r); overflow: hidden;
    border: 1px solid var(--border);
    position: relative;
    background: var(--surface-2);
    min-height: 160px;
    display: flex; align-items: center; justify-content: center;
}
.img-preview-box img {
    width: 100%; height: 180px; object-fit: cover; display: block;
}
.img-no-img {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 32px 20px; color: var(--muted);
}
.img-no-img i { font-size: 36px; }
.img-no-img span { font-size: 12px; }
.img-overlay {
    position: absolute; inset: 0;
    background: rgba(7,9,26,.7); backdrop-filter: blur(2px);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .2s;
    color: #fff; font-size: 13px; font-weight: 700; gap: 6px;
}
.img-preview-box:hover .img-overlay { opacity: 1; }

.upload-zone {
    border: 1.5px dashed var(--border-2);
    border-radius: var(--r); padding: 20px;
    text-align: center; cursor: pointer;
    transition: all .2s; background: var(--surface-2);
    position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
    border-color: var(--accent); background: var(--acc-lt);
}
.upload-zone input[type="file"] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-ico { font-size: 28px; color: var(--muted); margin-bottom: 8px; }
.upload-zone:hover .upload-ico { color: var(--accent); }
.upload-title { font-size: 13px; font-weight: 700; color: var(--txt-2); margin-bottom: 4px; }
.upload-sub { font-size: 11px; color: var(--muted); }
.upload-new-preview { margin-top: 12px; }
.upload-new-preview img { width: 100%; max-height: 130px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }

/* META INFO */
.meta-block {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--r); padding: 14px 16px;
}
.meta-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 12px;
}
.meta-row:last-child { border-bottom: none; }
.meta-key { color: var(--muted); display: flex; align-items: center; gap: 5px; }
.meta-val { color: var(--txt-2); font-weight: 600; }
.meta-val.id-badge {
    background: var(--acc-lt); color: var(--accent); border: 1px solid var(--acc-md);
    padding: 2px 9px; border-radius: 12px; font-size: 11px; font-weight: 800;
}

/* FORM FOOTER */
.form-footer {
    padding: 18px 24px;
    border-top: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
    background: var(--surface-2);
    flex-wrap: wrap;
}
.btn-save {
    display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px;
    background: linear-gradient(135deg, var(--accent), #b06cf5);
    color: #fff; border: none; border-radius: var(--r);
    font-family: 'Tajawal', sans-serif; font-size: 14px; font-weight: 800;
    cursor: pointer; transition: all .2s;
    box-shadow: 0 4px 20px var(--acc-glow);
}
.btn-save:hover { transform: translateY(-1px); box-shadow: 0 8px 28px var(--acc-glow); }
.btn-save:active { transform: translateY(0); }
.btn-cancel {
    display: inline-flex; align-items: center; gap: 7px; padding: 12px 20px;
    background: transparent; color: var(--muted); border: 1px solid var(--border);
    border-radius: var(--r); font-family: 'Tajawal', sans-serif; font-size: 13px;
    font-weight: 700; cursor: pointer; transition: all .2s; text-decoration: none;
}
.btn-cancel:hover { border-color: var(--danger); color: var(--danger); background: var(--dan-lt); }
.save-note { font-size: 11px; color: var(--muted); margin-right: auto; display: flex; align-items: center; gap: 5px; }

/* CHAR COUNTER */
.char-counter { font-size: 10px; color: var(--muted); text-align: left; margin-top: 3px; transition: color .2s; }
.char-counter.warn { color: var(--warning); }
.char-counter.over { color: var(--danger); }

/* PROVIDER CARD */
.provider-preview {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: var(--r);
    background: var(--acc-lt); border: 1px solid var(--acc-md);
    margin-top: 8px; transition: all .3s;
}
.provider-preview.hidden { display: none; }
.prov-av-lg {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--accent), #b06cf5);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: #fff;
}
.prov-info .prov-name { font-size: 13px; font-weight: 700; color: var(--accent); }
.prov-info .prov-email { font-size: 11px; color: var(--muted); }

/* RESPONSIVE */
@media(max-width:960px) { .sidebar { display: none; } .main { margin-right: 0; padding: 18px 14px; } }
@media(max-width:640px) { .main { padding: 14px 10px; } .field-row { grid-template-columns: 1fr; } }

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
</style>
<style>
/* ── Sidebar bridge vars ── */
:root {
  --sidebar-bg:    var(--card-bg, var(--card, #0e1726));
  --card-border:   var(--border, #1b2e4b);
  --sidebar-width: var(--side-w, var(--sidebar-width, 255px));
  --header-height: var(--nav-h, var(--header-height, 68px));
}

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

<!-- NAV -->
<nav class="top-nav">
    <div class="nav-brand">
        <div class="brand-icon"><i class="las la-concierge-bell"></i></div>
        خدماتي
        <span class="brand-pill">Admin</span>
    </div>
    <button onclick="toggleSidebar()"
            style="background:var(--surface-3);border:1px solid var(--border);color:var(--muted);font-size:18px;cursor:pointer;padding:7px 10px;border-radius:var(--r);transition:all .2s;line-height:1;"
            onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
            onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
        <i class="las la-bars"></i>
    </button>
    <div class="nav-spacer"></div>
    <button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
            class="nav-icon" style="cursor:pointer;">
        <i class="las la-sun" id="themeIcon"></i>
    </button>

    <div class="nav-sep"></div>
    <a href="/local_services/index.php"   class="nav-btn" title="الموقع"><i class="las la-external-link-alt"></i></a>
    <a href="/local_services/logout.php"  class="nav-btn x-danger" title="خروج"><i class="las la-sign-out-alt"></i></a>
    <?php if ($admin_user): ?>
    <div class="nav-sep"></div>
    <div class="nav-user">
        <div class="nav-avatar"><?= mb_substr($admin_user['full_name'], 0, 1) ?></div>
        <div>
            <div class="nav-uname"><?= htmlspecialchars($admin_user['full_name']) ?></div>
            <div class="nav-urole"><i class="las la-crown"></i> مدير النظام</div>
        </div>
    </div>
    <?php endif; ?>
</nav>

<!-- SIDEBAR -->
<?php include_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<!-- MAIN -->
<main class="main" id="appMain">

    <!-- Header -->
    <div class="page-header">
        <div>
            <div class="page-eyebrow"><i class="las la-edit"></i> تعديل خدمة</div>
            <div class="page-title-text">✏️ <?= htmlspecialchars($current_service['title']) ?></div>
            <div class="breadcrumb">
                <a href="/local_services/dashboard.php">الرئيسية</a>
                <sep>/</sep>
                <a href="manage_services.php">الخدمات</a>
                <sep>/</sep>
                <span>تعديل #<?= $service_id ?></span>
            </div>
        </div>
        <div class="header-actions">
            <a href="/local_services/service_detail.php?id=<?= $service_id ?>" target="_blank" class="btn-outline">
                <i class="las la-eye"></i> معاينة
            </a>
            <a href="manage_services.php" class="btn-outline">
                <i class="las la-arrow-right"></i> العودة
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="las la-exclamation-circle"></i>
        <div>
            <strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (function_exists('display_message')) display_message(); ?>

    <!-- FORM -->
    <form method="POST" action="edit_service.php?id=<?= $service_id ?>" enctype="multipart/form-data" id="editForm" novalidate>

        <div class="edit-grid">

            <!-- ══ RIGHT COL: Main Fields ══ -->
            <div>

                <!-- Basic Info -->
                <div class="form-card" style="margin-bottom:18px;">
                    <div class="card-head">
                        <div class="card-head-ico"><i class="las la-info-circle"></i></div>
                        <div>
                            <h5>المعلومات الأساسية</h5>
                            <div class="card-sub">العنوان، الوصف والسعر</div>
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- Title -->
                        <div class="field-group">
                            <label class="f-label" for="title">
                                <i class="las la-heading"></i> عنوان الخدمة <span class="f-required">*</span>
                            </label>
                            <input type="text" id="title" name="title" class="f-input"
                                   value="<?= htmlspecialchars($form_data['title']) ?>"
                                   placeholder="أدخل عنواناً واضحاً وجذاباً للخدمة..."
                                   maxlength="150" required>
                            <div class="char-counter" id="titleCounter">0 / 150</div>
                        </div>

                        <!-- Description -->
                        <div class="field-group">
                            <label class="f-label" for="description">
                                <i class="las la-align-right"></i> وصف الخدمة <span class="f-required">*</span>
                            </label>
                            <textarea id="description" name="description" class="f-textarea"
                                      placeholder="اكتب وصفاً تفصيلياً يوضح ما تقدمه الخدمة، مميزاتها وشروطها..."
                                      maxlength="2000" required><?= htmlspecialchars($form_data['description']) ?></textarea>
                            <div class="char-counter" id="descCounter">0 / 2000</div>
                        </div>

                        <!-- Price + Category -->
                        <div class="field-row">
                            <div class="field-group">
                                <label class="f-label" for="price">
                                    <i class="las la-shekel-sign"></i> السعر <span class="f-required">*</span>
                                </label>
                                <div class="price-wrap">
                                    <input type="number" id="price" name="price" class="f-input"
                                           value="<?= htmlspecialchars($form_data['price']) ?>"
                                           placeholder="0.00" step="0.01" min="0.01" required>
                                    <span class="price-symbol">₪</span>
                                </div>
                            </div>
                            <div class="field-group">
                                <label class="f-label" for="category_id">
                                    <i class="las la-tag"></i> التصنيف
                                </label>
                                <div class="select-wrap">
                                    <select id="category_id" name="category_id" class="f-select">
                                        <option value="">— بدون تصنيف —</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                                <?= (int)$form_data['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Provider + Status -->
                <div class="form-card">
                    <div class="card-head">
                        <div class="card-head-ico"><i class="las la-user-tie"></i></div>
                        <div>
                            <h5>المزود والحالة</h5>
                            <div class="card-sub">اختر المزود وضبط حالة العرض</div>
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- Provider -->
                        <div class="field-group">
                            <label class="f-label" for="provider_id">
                                <i class="las la-user-check"></i> مزود الخدمة <span class="f-required">*</span>
                            </label>
                            <div class="select-wrap">
                                <select id="provider_id" name="provider_id" class="f-select" required onchange="updateProviderPreview(this)">
                                    <option value="">— اختر مزود خدمة —</option>
                                    <?php foreach ($providers as $p): ?>
                                    <option value="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['full_name']) ?>"
                                            data-email="<?= htmlspecialchars($p['email']) ?>"
                                            <?= (int)$form_data['provider_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['full_name']) ?> — <?= htmlspecialchars($p['email']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="provider-preview <?= empty($form_data['provider_id']) ? 'hidden' : '' ?>" id="providerPreview">
                                <div class="prov-av-lg" id="providerInitial">
                                    <?php
                                    $selProv = array_filter($providers, fn($p) => (int)$p['id'] === (int)$form_data['provider_id']);
                                    $selProv = array_values($selProv);
                                    echo !empty($selProv) ? mb_substr($selProv[0]['full_name'], 0, 1) : '?';
                                    ?>
                                </div>
                                <div class="prov-info">
                                    <div class="prov-name" id="providerName">
                                        <?= !empty($selProv) ? htmlspecialchars($selProv[0]['full_name']) : '' ?>
                                    </div>
                                    <div class="prov-email" id="providerEmail">
                                        <?= !empty($selProv) ? htmlspecialchars($selProv[0]['email']) : '' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="field-group">
                            <label class="f-label">
                                <i class="las la-toggle-on"></i> حالة الخدمة <span class="f-required">*</span>
                            </label>
                            <div class="toggle-group">
                                <div class="toggle-opt">
                                    <input type="radio" name="is_active" id="status_active" value="1"
                                           <?= (int)$form_data['is_active'] === 1 ? 'checked' : '' ?>>
                                    <label for="status_active" class="toggle-lbl lbl-active">
                                        <i class="las la-check-circle"></i> نشطة وظاهرة
                                    </label>
                                </div>
                                <div class="toggle-opt">
                                    <input type="radio" name="is_active" id="status_paused" value="0"
                                           <?= (int)$form_data['is_active'] === 0 ? 'checked' : '' ?>>
                                    <label for="status_paused" class="toggle-lbl lbl-paused">
                                        <i class="las la-ban"></i> موقوفة ومخفية
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Form Footer inside card -->
                    <div class="form-footer">
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="las la-save"></i> حفظ التعديلات
                        </button>
                        <a href="manage_services.php" class="btn-cancel">
                            <i class="las la-times"></i> إلغاء
                        </a>
                        <span class="save-note"><i class="las la-lock"></i> البيانات محمية ومشفرة</span>
                    </div>
                </div>

            </div>

            <!-- ══ LEFT COL: Image + Meta ══ -->
            <div>

                <!-- Image Card -->
                <div class="form-card" style="margin-bottom:18px;">
                    <div class="card-head">
                        <div class="card-head-ico"><i class="las la-image"></i></div>
                        <div>
                            <h5>صورة الخدمة</h5>
                            <div class="card-sub">JPG · PNG · GIF · WEBP</div>
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- Current image -->
                        <div>
                            <label class="f-label" style="margin-bottom:8px;">
                                <i class="las la-photo-video"></i> الصورة الحالية
                            </label>
                            <div class="img-preview-box">
                                <?php if (!empty($form_data['image'])): ?>
                                <img src="../assets/uploads/services/<?= htmlspecialchars($form_data['image']) ?>"
                                     alt="صورة الخدمة" id="currentImg">
                                <div class="img-overlay"><i class="las la-upload"></i> استبدال الصورة</div>
                                <?php else: ?>
                                <div class="img-no-img">
                                    <i class="las la-image"></i>
                                    <span>لا توجد صورة مرفوعة</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Upload new -->
                        <div>
                            <label class="f-label" style="margin-bottom:8px;">
                                <i class="las la-cloud-upload-alt"></i> رفع صورة جديدة (اختياري)
                            </label>
                            <div class="upload-zone" id="dropZone">
                                <input type="file" name="service_image" id="service_image"
                                       accept="image/jpeg,image/png,image/gif,image/webp"
                                       onchange="previewNewImage(this)">
                                <div class="upload-ico"><i class="las la-cloud-upload-alt"></i></div>
                                <div class="upload-title">اسحب وأفلت أو انقر للاختيار</div>
                                <div class="upload-sub">الحجم الأقصى: 5 ميغابايت</div>
                            </div>
                            <div class="upload-new-preview" id="newImgPreview" style="display:none;">
                                <img id="newImgThumb" src="" alt="معاينة الصورة الجديدة">
                                <div style="font-size:11px;color:var(--success);margin-top:6px;display:flex;align-items:center;gap:4px;">
                                    <i class="las la-check-circle"></i>
                                    <span id="newImgName"></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Meta Info -->
                <div class="form-card">
                    <div class="card-head">
                        <div class="card-head-ico"><i class="las la-info"></i></div>
                        <div>
                            <h5>معلومات الخدمة</h5>
                            <div class="card-sub">بيانات النظام</div>
                        </div>
                    </div>
                    <div class="card-body" style="padding:16px 20px;">
                        <div class="meta-block">
                            <div class="meta-row">
                                <span class="meta-key"><i class="las la-hashtag"></i> معرف الخدمة</span>
                                <span class="meta-val id-badge">#<?= $service_id ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-key"><i class="las la-calendar-plus"></i> تاريخ الإنشاء</span>
                                <span class="meta-val"><?= date('Y/m/d', strtotime($current_service['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($current_service['updated_at'])): ?>
                            <div class="meta-row">
                                <span class="meta-key"><i class="las la-history"></i> آخر تعديل</span>
                                <span class="meta-val"><?= date('Y/m/d H:i', strtotime($current_service['updated_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="meta-row">
                                <span class="meta-key"><i class="las la-signal"></i> الحالة الحالية</span>
                                <span class="meta-val">
                                    <?php if ($current_service['is_active']): ?>
                                    <span style="color:var(--success);font-weight:700;"><i class="las la-circle" style="font-size:8px;"></i> نشطة</span>
                                    <?php else: ?>
                                    <span style="color:var(--danger);font-weight:700;"><i class="las la-circle" style="font-size:8px;"></i> موقوفة</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($current_service['city'])): ?>
                            <div class="meta-row">
                                <span class="meta-key"><i class="las la-map-marker-alt"></i> المدينة</span>
                                <span class="meta-val"><?= htmlspecialchars($current_service['city']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div><!-- /edit-grid -->

    </form>

</main>

<script>
(function(){
    'use strict';

    // Sidebar toggle
    const sidebar = document.getElementById('appSidebar');
    const mainEl  = document.getElementById('appMain');
    let mob = window.innerWidth <= 960;
    window.addEventListener('resize', () => mob = window.innerWidth <= 960);
    window.toggleSidebar = function(){
        if (mob) { sidebar.classList.toggle('mobile-open'); return; }
        sidebar.classList.toggle('collapsed'); mainEl.classList.toggle('expanded');
    };

    // Char counters
    function initCounter(inputId, counterId, max) {
        const el = document.getElementById(inputId);
        const ct = document.getElementById(counterId);
        if (!el || !ct) return;
        function update() {
            const len = el.value.length;
            ct.textContent = len + ' / ' + max;
            ct.className = 'char-counter' + (len > max * .9 ? (len >= max ? ' over' : ' warn') : '');
        }
        el.addEventListener('input', update);
        update();
    }
    initCounter('title',       'titleCounter', 150);
    initCounter('description', 'descCounter',  2000);

    // Provider preview
    window.updateProviderPreview = function(sel) {
        const preview  = document.getElementById('providerPreview');
        const nameEl   = document.getElementById('providerName');
        const emailEl  = document.getElementById('providerEmail');
        const initEl   = document.getElementById('providerInitial');
        const opt      = sel.options[sel.selectedIndex];
        if (!opt.value) { preview.classList.add('hidden'); return; }
        const name  = opt.dataset.name  || opt.text;
        const email = opt.dataset.email || '';
        nameEl.textContent  = name;
        emailEl.textContent = email;
        initEl.textContent  = name.charAt(0);
        preview.classList.remove('hidden');
    };

    // Image preview
    window.previewNewImage = function(input) {
        const previewBox = document.getElementById('newImgPreview');
        const thumb      = document.getElementById('newImgThumb');
        const nameEl     = document.getElementById('newImgName');
        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                alert('حجم الملف كبير جداً. الحد الأقصى هو 5 ميغابايت.');
                input.value = '';
                previewBox.style.display = 'none';
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                thumb.src = e.target.result;
                nameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                previewBox.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            previewBox.style.display = 'none';
        }
    };

    // Drag & drop
    const dz = document.getElementById('dropZone');
    if (dz) {
        ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
        ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
        dz.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            if (dt.files.length) {
                document.getElementById('service_image').files = dt.files;
                previewNewImage(document.getElementById('service_image'));
            }
        });
    }

    // Save button loading state
    document.getElementById('editForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('saveBtn');
        btn.innerHTML = '<i class="las la-spinner la-spin"></i> جاري الحفظ...';
        btn.disabled = true;
    });

})();
</script>

</body>
</html>