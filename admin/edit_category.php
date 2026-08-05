<?php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

// 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin');
$current_page = 'edit_category';


global $pdo;
$category_id = (int) sanitize_input($_GET['id'] ?? 0);

// ==========================================================
// 2. جلب بيانات التصنيف الحالي
// ==========================================================
if ($category_id === 0) {
    set_message("معرف التصنيف غير صالح.", "danger");
    header("Location: manage_categories.php");
    exit();
}

try {
    //  تم إضافة جلب عمود type
    $stmt = $pdo->prepare("SELECT id, name, image, type FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        set_message("التصنيف المطلوب غير موجود.", "danger");
        header("Location: manage_categories.php");
        exit();
    }
} catch (PDOException $e) {
    // لا يجب استخدام die() في ملفات العمل (توقف غير محمي)
    error_log("DB Error: " . $e->getMessage()); 
    set_message("فشل في جلب بيانات التصنيف.", "danger");
    header("Location: manage_categories.php");
    exit();
}

// ==========================================================
// 3. معالجة تحديث التصنيف (POST Request)
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $name = sanitize_input($_POST['name'] ?? '');
    //  جلب قيمة type
    $type = sanitize_input($_POST['type'] ?? 'local'); 

    $current_image = $category['image'];
    $new_image_name = $current_image;
    
    // التحقق من الاسم
    if (empty($name)) {
        set_message("يجب إدخال اسم التصنيف.", "danger");
    } else {
        try {
            //  معالجة رفع الأيقونة الجديدة 
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                
                // 1. رفع الملف الجديد
                // نفترض أن دالة upload_file تحفظ الملف في assets/uploads/
                $new_image_name = upload_file($_FILES['image'], 'uploads'); // تم تغيير المسار الافتراضي إلى 'uploads'
                
                // 2. حذف الأيقونة القديمة إن وجدت
                if (!empty($current_image) && $current_image !== $new_image_name) {
                    //  المسار المصحح لحذف الملف القديم هو assets/uploads/
                    $old_file_path = __DIR__ . "/../assets/uploads/" . $current_image;
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            }

            // 3. تحديث قاعدة البيانات
            //  تم إضافة type إلى التحديث
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $type, $new_image_name, $category_id]);
            
            set_message("تم تحديث التصنيف '{$name}' بنجاح!", "success");
            header("Location: manage_categories.php");
            exit();
            
        } catch (PDOException $e) {
            set_message("خطأ في قاعدة البيانات أثناء التحديث: " . $e->getMessage(), "danger");
        } catch (Exception $e) {
             // قد يحدث هذا الخطأ من دالة upload_file
            set_message("خطأ في معالجة الملف: " . $e->getMessage(), "danger");
        }
    }
}

// إذا كان الطلب GET أو فشل الـ POST، نظهر النموذج:
include_once '../includes/header.php';
?>

<button onclick="toggleTheme()" id="themeToggle" title="تبديل المظهر"
        style="position:fixed;top:15px;left:20px;z-index:9999;
               width:38px;height:38px;background:#0e1726;
               border:1px solid #1b2e4b;border-radius:50%;
               display:flex;align-items:center;justify-content:center;
               color:#888ea8;font-size:18px;cursor:pointer;
               box-shadow:0 2px 10px rgba(0,0,0,.3);transition:all .2s;">
    <i class="las la-sun" id="themeIcon"></i>
</button>

<?php
include_once '../includes/admin_sidebar.php';
?>
<style>
:root {
  --sidebar-bg: #0e1726; --card-border: #1b2e4b;
  --sidebar-width: 255px; --header-height: 70px;
}
.container.admin-content { margin-right: 255px; transition: margin-right .3s; }
@media(max-width:900px){ .container.admin-content { margin-right: 0; } }

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
<?php 
?>

<div class="container admin-content">
    
    <h2>✏️ تعديل التصنيف: <?= htmlspecialchars($category['name']) ?></h2>
    <?php display_message(); ?>
    
    
    <div style="max-width: 600px; margin-top: 20px; border: 1px solid #ddd; padding: 30px; border-radius: 8px;">
        <form method="POST" action="edit_category.php?id=<?= $category['id'] ?>" enctype="multipart/form-data">
            
            <label for="name" style="display: block; margin-bottom: 5px; font-weight: bold;">اسم التصنيف:</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($category['name']) ?>" required 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <label for="type" style="display: block; margin-bottom: 5px; font-weight: bold;">نوع التصنيف:</label>
            <select id="type" name="type" required 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
                <option value="local" <?= ($category['type'] === 'local' ? 'selected' : '') ?>>خدمات محلية</option>
                <option value="freelance" <?= ($category['type'] === 'freelance' ? 'selected' : '') ?>>أعمال حرة</option>
            </select>
            
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">الأيقونة الحالية:</label>
            <?php 
                // 🛑 المسار المصحح لعرض الملف المرفوع هو assets/uploads/
                if (!empty($category['image'])): 
                    $icon_path = "../assets/uploads/" . htmlspecialchars($category['image']);
            ?>
                <img src="<?= $icon_path ?>" alt="الأيقونة الحالية" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 15px; border: 1px solid #eee; padding: 5px; border-radius: 5px;">
                <p class="muted" style="color: #666; font-size: 0.9em;">(مسار الملف: assets/uploads/<?= htmlspecialchars($category['image']) ?>)</p>
            <?php else: ?>
                <p style="margin-bottom: 15px;">لا توجد أيقونة حالياً.</p>
            <?php endif; ?>
            
            
            
            <label for="image" style="display: block; margin-top: 20px; margin-bottom: 5px; font-weight: bold;">استبدال الأيقونة (اختياري):</label>
            <input type="file" id="image" name="image" accept="image/*" 
                    style="margin-bottom: 25px;">
            
            <button type="submit" style="padding: 12px 25px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                حفظ التعديلات
            </button>
            <a href="manage_categories.php" style="margin-right: 15px; color: #555; text-decoration: none;">إلغاء والعودة</a>
        </form>
    </div>
    
</div>

<?php 
include_once '../includes/footer.php';
?>