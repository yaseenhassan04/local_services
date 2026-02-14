<?php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

// 🛑 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

global $pdo;
$errors = [];
$form_data = [
    'title' => '',
    'description' => '',
    'price' => '',
    'category_id' => '',
    'provider_id' => '' // ID المستخدم الذي سيقدم الخدمة
];

// ==========================================================
// 2. جلب القوائم المنسدلة: التصنيفات والمزودين
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
// 3. معالجة إضافة الخدمة (POST Request)
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // تنظيف البيانات
    $form_data['title']       = sanitize_input($_POST['title'] ?? '');
    $form_data['description'] = sanitize_input($_POST['description'] ?? '');
    $form_data['price']       = sanitize_input($_POST['price'] ?? '');
    $form_data['category_id'] = (int) sanitize_input($_POST['category_id'] ?? 0);
    $form_data['provider_id'] = (int) sanitize_input($_POST['provider_id'] ?? 0);

    // التحقق من الصحة
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
    
    // متغير لتخزين اسم ملف الصورة في قاعدة البيانات
    $image_filename = NULL;

    // 4. معالجة رفع الصورة
    if (empty($errors) && isset($_FILES['service_image']) && $_FILES['service_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . "/../assets/uploads/services/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp = $_FILES['service_image']['tmp_name'];
        $file_name = $_FILES['service_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "الرجاء رفع صورة بصيغة JPG, JPEG, PNG, أو GIF.";
        } else {
            // توليد اسم فريد للملف
            $image_filename = uniqid('service_', true) . '.' . $file_ext;
            $destination = $upload_dir . $image_filename;

            if (!move_uploaded_file($file_tmp, $destination)) {
                $errors[] = "فشل في حفظ ملف الصورة على الخادم.";
                $image_filename = NULL;
            }
        }
    } elseif (empty($errors) && !empty($_FILES['service_image']['name']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "حدث خطأ غير متوقع أثناء رفع الصورة.";
    }

    // 5. إدخال الخدمة إلى قاعدة البيانات
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO services (title, description, price, category_id, provider_id, image, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $form_data['title'],
                $form_data['description'],
                $form_data['price'],
                $form_data['category_id'] > 0 ? $form_data['category_id'] : NULL, // إدخال NULL إذا لم يتم اختيار تصنيف
                $form_data['provider_id'],
                $image_filename
            ]);
            
            set_message("تم إضافة الخدمة '{$form_data['title']}' بنجاح!", "success");
            header("Location: manage_services.php");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "خطأ في قاعدة البيانات أثناء الإضافة: " . $e->getMessage();
            // في حالة فشل الإضافة، يجب حذف الصورة المرفوعة
            if ($image_filename && file_exists($upload_dir . $image_filename)) {
                 unlink($upload_dir . $image_filename);
            }
        }
    }
}

include_once '../includes/header.php'; 
?>

<div class="container admin-content">
    
    <h2>✨ إضافة خدمة جديدة</h2>
    <?php display_message(); ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="color: red; margin-bottom: 20px;">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div style="max-width: 700px; margin-top: 20px; border: 1px solid #ddd; padding: 30px; border-radius: 8px;">
        <form method="POST" action="add_service.php" enctype="multipart/form-data">
            
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
                    <option value="<?= $provider['id'] ?>" <?= $form_data['provider_id'] === $provider['id'] ? 'selected' : '' ?>>
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
            
            <label for="service_image" style="display: block; margin-bottom: 5px; font-weight: bold;">صورة الخدمة (اختياري):</label>
            <input type="file" id="service_image" name="service_image" accept="image/*"
                    style="padding: 10px; margin-bottom: 25px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            
            <button type="submit" style="padding: 12px 25px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                حفظ الخدمة
            </button>
            <a href="manage_services.php" style="margin-right: 15px; color: #555; text-decoration: none;">إلغاء والعودة</a>
        </form>
    </div>
    
</div>

<?php 
include_once '../includes/footer.php';
?>