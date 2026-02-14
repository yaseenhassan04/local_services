<?php

// تضمين الملفات الأساسية
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// 🎯 حماية الصفحة: التأكد من أن المستخدم مسجل دخوله ولديه دور 'provider'
check_login('provider'); 

global $pdo;
$user = current_user(); // جلب بيانات المستخدم الحالي
$errors = [];
$form_data = []; // لتخزين البيانات المعادة في حال وجود خطأ

// ==========================================================
// 1. جلب التصنيفات لملء القائمة المنسدلة
// ==========================================================
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // في حال فشل الاتصال/الاستعلام، نستخدم رسالة عامة لحماية تفاصيل قاعدة البيانات
    set_message("فشل في جلب التصنيفات الأساسية للنظام.", "danger"); 
    $categories = []; 
}

// ==========================================================
// 2. معالجة نموذج الإضافة (POST Request)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تنظيف المدخلات
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = sanitize_input($_POST['category_id'] ?? '');
    $price = sanitize_input($_POST['price'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    $form_data = $_POST; // حفظ البيانات المعادة

    // التحقق من CSRF
    if (!verifyCsrfToken($csrf_token)) {
        $errors[] = "خطأ في أمان النموذج (CSRF). يرجى تحديث الصفحة والمحاولة مرة أخرى.";
    }

    // التحقق من صحة المدخلات (Validation)
    if (empty($title)) $errors[] = "عنوان الخدمة مطلوب.";
    if (empty($description)) $errors[] = "وصف الخدمة مطلوب.";
    if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id <= 0) $errors[] = "التصنيف المحدد غير صالح.";
    if (!is_numeric($price) || $price <= 0) $errors[] = "السعر يجب أن يكون رقماً موجباً.";
    if (empty($city)) $errors[] = "المدينة مطلوبة.";

    // التحقق من ملف الصورة
    $image_file_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        // التحقق من نوع الملف
        if (!in_array($_FILES['image']['type'], $allowed_mime_types)) {
            $errors[] = "صيغة الصورة غير مدعومة. يرجى استخدام JPEG, PNG, أو GIF.";
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // خطأ آخر في الرفع (مثل حجم الملف)
        $errors[] = "حدث خطأ أثناء تحميل ملف الصورة. رمز الخطأ: " . $_FILES['image']['error'];
    }

    // 3. حفظ البيانات في قاعدة البيانات إذا لم تكن هناك أخطاء
    if (empty($errors)) {
        
        try {
            // البدء بالعملية Transaction لضمان إما حفظ الكل أو لا شيء
            $pdo->beginTransaction();
            
            // أ. رفع الصورة إذا كانت موجودة
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && empty($errors)) {
                // اسم المجلد 'uploads' هو المكان الذي يجب أن تحفظ فيه صور الخدمات
                $image_file_name = upload_file($_FILES['image'], 'uploads'); 
            }
            
            // ب. إدخال بيانات الخدمة في جدول services (باستخدام Preppared Statement)
            $stmt = $pdo->prepare("INSERT INTO services (provider_id, category_id, title, description, price, city, image, is_active) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 1)"); // 1 تعني الخدمة نشطة تلقائياً
            
            $stmt->execute([
                $user['id'], // ربط الخدمة بالمزود الحالي
                $category_id, 
                $title, 
                $description, 
                $price, 
                $city,
                $image_file_name
            ]);
            
            $pdo->commit(); // تأكيد الحفظ
            
            set_message("تم إضافة الخدمة '{$title}' بنجاح.", "success");
            header("Location: /local_services/dashboard.php");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack(); // التراجع عن العملية في حال وجود خطأ
            // عرض رسالة خطأ عامة للمستخدم
            set_message("فشل في حفظ الخدمة: يرجى المحاولة لاحقاً.", "danger"); 
            // يمكن تسجيل الخطأ $e->getMessage() في ملف Log
        }
    }
}

// 4. إعداد واجهة المستخدم (Header و CSRF)
$csrf_token = generateCsrfToken();
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-page-container">
    <div class="auth-card" style="max-width: 600px;">
        <h2>إضافة خدمة جديدة</h2>
        
        <?php display_message(); ?>
        
        <?php if (!empty($errors)): ?>
            <div class="errors alert alert-danger" style="background:#f8d7da; color:#721c24; padding:15px; border-radius:4px; margin-bottom:15px;">
                <p>⚠️ خطأ في الإدخال:</p>
                <ul>
                    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="title">عنوان الخدمة </label>
                <input type="text" id="title" name="title" required 
                       value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">وصف مفصل للخدمة </label>
                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row" style="display:flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label for="category_id">التصنيف </label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- اختر تصنيف --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo (isset($form_data['category_id']) && $form_data['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="price">السعر ( شيكل ) </label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required 
                           value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="city">المدينة</label>
                <input type="text" id="city" name="city" required 
                       value="<?php echo htmlspecialchars($form_data['city'] ?? (current_user()['city'] ?? '')); ?>">
            </div>

            <div class="form-group">
                <label for="image">صورة الخدمة</label>
                <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif">
                <p class="muted-small">الحد الأقصى لحجم الملف هو 2 ميجابايت (مثلاً). سيتم استخدام صورة افتراضية إذا لم تقم بالتحميل.</p>
            </div>
            
            <button type="submit" class="btn btn-primary">إضافة الخدمة</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>