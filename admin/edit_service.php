<?php
// admin/edit_service.php - تعديل بيانات خدمة موجودة

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

// 🛑 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

global $pdo;
$errors = [];
$service_id = 0;
$current_service = []; // بيانات الخدمة التي سيتم تعديلها

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

// ==========================================================
// 3. جلب بيانات الخدمة الحالية
// ==========================================================
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

// تهيئة بيانات النموذج بقيم الخدمة الحالية
$form_data = $current_service;


// ==========================================================
// 4. جلب القوائم المنسدلة: التصنيفات والمزودين
// ==========================================================

// جلب جميع التصنيفات
try {
    $stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_message("فشل في جلب التصنيفات.", "danger");
    $categories = [];
}

// جلب جميع المستخدمين ذوي دور "provider" أو "admin"
try {
    $stmt_providers = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('provider', 'admin') ORDER BY full_name ASC");
    $providers = $stmt_providers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_message("فشل في جلب قائمة المزودين.", "danger");
    $providers = [];
}

// ==========================================================
// 5. معالجة تحديث الخدمة (POST Request)
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 5.1. تنظيف البيانات من النموذج
    $form_data['title']       = sanitize_input($_POST['title'] ?? '');
    $form_data['description'] = sanitize_input($_POST['description'] ?? '');
    $form_data['price']       = sanitize_input($_POST['price'] ?? '');
    $form_data['category_id'] = (int) sanitize_input($_POST['category_id'] ?? 0);
    $form_data['provider_id'] = (int) sanitize_input($_POST['provider_id'] ?? 0);
    $form_data['is_active']   = (int) sanitize_input($_POST['is_active'] ?? 0);
    
    // 5.2. التحقق من الصحة (نفس التحقق في صفحة الإضافة)
    if (empty($form_data['title'])) {
        $errors[] = "عنوان الخدمة مطلوب.";
    }
    if (empty($form_data['description'])) {
        $errors[] = "وصف الخدمة مطلوب.";
    }
    if (!is_numeric($form_data['price']) || $form_data['price'] <= 0) {
        $errors[] = "السعر يجب أن يكون رقماً موجباً.";
    }
    if ($form_data['provider_id'] <= 0) {
        $errors[] = "يجب اختيار مزود خدمة.";
    }
    
    $image_filename = $current_service['image']; // الاحتفاظ بالصورة القديمة افتراضياً

    // 5.3. معالجة رفع صورة جديدة أو حذف الصورة القديمة
    $upload_dir = __DIR__ . "/../assets/uploads/services/";

    // هل تم رفع ملف جديد؟
    if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
        // حذف الصورة القديمة إن وجدت
        if (!empty($current_service['image'])) {
            $old_file_path = $upload_dir . $current_service['image'];
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }
        }

        // حفظ الملف الجديد (نفس منطق add_service.php)
        $file_tmp = $_FILES['service_image']['tmp_name'];
        $file_name = $_FILES['service_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "الرجاء رفع صورة بصيغة JPG, JPEG, PNG, أو GIF.";
        } else {
            $image_filename = uniqid('service_', true) . '.' . $file_ext;
            $destination = $upload_dir . $image_filename;

            if (!move_uploaded_file($file_tmp, $destination)) {
                $errors[] = "فشل في حفظ ملف الصورة الجديدة على الخادم.";
                $image_filename = $current_service['image']; // العودة للاحتفاظ بالقديمة في حال فشل النقل
            }
        }
    } 
    
    // 5.4. إدخال التحديثات إلى قاعدة البيانات
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

include_once '../includes/header.php'; 
?>

<div class="container admin-content">
    
    <h2>✏️ تعديل الخدمة: <?= htmlspecialchars($current_service['title']) ?></h2>
    <?php display_message(); ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="color: red; margin-bottom: 20px;">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div style="max-width: 700px; margin-top: 20px; border: 1px solid #ddd; padding: 30px; border-radius: 8px;">
        <form method="POST" action="edit_service.php?id=<?= $service_id ?>" enctype="multipart/form-data">
            
            <label for="title" style="display: block; margin-bottom: 5px; font-weight: bold;">عنوان الخدمة:</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($form_data['title']) ?>" required 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <label for="description" style="display: block; margin-bottom: 5px; font-weight: bold;">وصف الخدمة:</label>
            <textarea id="description" name="description" rows="5" required
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;"><?= htmlspecialchars($form_data['description']) ?></textarea>
            
            <label for="price" style="display: block; margin-bottom: 5px; font-weight: bold;">السعر (ر.س):</label>
            <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($form_data['price']) ?>" required 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">

            <label for="provider_id" style="display: block; margin-bottom: 5px; font-weight: bold;">مزود الخدمة:</label>
            <select id="provider_id" name="provider_id" required 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">-- اختر مزود خدمة --</option>
                <?php foreach ($providers as $provider): ?>
                    <option value="<?= $provider['id'] ?>" <?= (int)$form_data['provider_id'] === (int)$provider['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($provider['full_name']) ?> (<?= htmlspecialchars($provider['email']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="category_id" style="display: block; margin-bottom: 5px; font-weight: bold;">التصنيف (اختياري):</label>
            <select id="category_id" name="category_id" 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">-- لا يوجد تصنيف --</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= (int)$form_data['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="is_active" style="display: block; margin-bottom: 5px; font-weight: bold;">حالة الخدمة:</label>
            <select id="is_active" name="is_active" required 
                    style="padding: 10px; margin-bottom: 20px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
                <option value="1" <?= (int)$form_data['is_active'] === 1 ? 'selected' : '' ?>>1 (نشط)</option>
                <option value="0" <?= (int)$form_data['is_active'] === 0 ? 'selected' : '' ?>>0 (موقف)</option>
            </select>
            
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">الصورة الحالية:</label>
            <?php if (!empty($form_data['image'])): ?>
                <div style="margin-bottom: 15px;">
                    <img src="../assets/uploads/services/<?= htmlspecialchars($form_data['image']) ?>" alt="صورة الخدمة" 
                         style="max-width: 150px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">
                </div>
            <?php else: ?>
                <p style="margin-bottom: 15px; color: #555;">لا توجد صورة مرفوعة حالياً.</p>
            <?php endif; ?>

            <label for="service_image" style="display: block; margin-bottom: 5px; font-weight: bold;">رفع صورة جديدة (اختياري):</label>
            <input type="file" id="service_image" name="service_image" accept="image/*"
                    style="padding: 10px; margin-bottom: 25px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <button type="submit" style="padding: 12px 25px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                حفظ التعديلات
            </button>
            <a href="manage_services.php" style="margin-right: 15px; color: #555; text-decoration: none;">إلغاء والعودة</a>
        </form>
    </div>
    
</div>

<?php 
include_once '../includes/footer.php';
?>