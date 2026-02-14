<?php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

// 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

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