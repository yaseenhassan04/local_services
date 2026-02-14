<?php
// edit_service.php - صفحة تعديل خدمة قائمة (خاصة بالمزودين)

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// حماية الصفحة: يجب أن يكون المستخدم مسجلاً ولديه دور 'provider'
check_login('provider'); 

global $pdo;
$user = current_user(); // بيانات المزود الحالي
$user_id = $user['id'];
$errors = [];
$service = null;

// 🛑 1. التحقق من معرف الخدمة وتصحيح المشكلة المحتملة
$service_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$service_id) {
    // إذا لم يتم تمرير المعرف، يتم إيقاف التنفيذ وعرض رسالة واضحة
    set_message("خطأ: لم يتم تحديد معرف الخدمة المطلوب لتعديله.", "danger");
    header("Location: /local_services/dashboard.php");
    exit();
}

// 2. جلب التصنيفات
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_message("فشل في جلب التصنيفات الأساسية للنظام.", "danger"); 
    $categories = []; 
}

// ==========================================================
// 3. جلب بيانات الخدمة الحالية والتحقق من المالكية
// ==========================================================
try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🛑 تحقق الأمان: هل هذه الخدمة مملوكة للمستخدم الحالي؟
    if (!$service || $service['provider_id'] !== $user_id) {
        set_message("لا تملك صلاحية تعديل هذه الخدمة أو أنها غير موجودة.", "danger");
        header("Location: /local_services/dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    set_message("خطأ في الاتصال بقاعدة البيانات.", "danger");
    header("Location: /local_services/dashboard.php");
    exit();
}

// 4. معالجة نموذج التعديل (POST Request) - (كود الـ POST يبقى كما هو)
// ... (هنا يتم وضع كود معالجة الـ POST الذي أرسلته سابقاً) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تنظيف المدخلات
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = sanitize_input($_POST['category_id'] ?? '');
    $price = sanitize_input($_POST['price'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // التحقق من CSRF
    if (!verifyCsrfToken($csrf_token)) {
        $errors[] = "خطأ في أمان النموذج (CSRF). حاول مرة أخرى.";
    }

    // التحقق من صحة المدخلات (Validation)
    if (empty($title)) $errors[] = "عنوان الخدمة مطلوب.";
    if (empty($description)) $errors[] = "وصف الخدمة مطلوب.";
    if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id <= 0) $errors[] = "التصنيف المحدد غير صالح.";
    if (!is_numeric($price) || $price <= 0) $errors[] = "السعر يجب أن يكون رقماً موجباً.";
    if (empty($city)) $errors[] = "المدينة مطلوبة.";

    // معالجة ملف الصورة الجديد
    $image_file_name = $service['image']; // الحفاظ على الصورة القديمة افتراضياً
    $delete_current_image = isset($_POST['delete_image']) && $_POST['delete_image'] === 'on';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        try {
            $image_file_name = upload_file($_FILES['image'], 'uploads');
        } catch (Exception $e) {
            $errors[] = "فشل في رفع الصورة الجديدة: " . $e->getMessage();
        }
    } elseif ($delete_current_image) {
        $image_file_name = null;
    }


    // 5. حفظ التعديلات في قاعدة البيانات
    if (empty($errors)) {
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE services SET 
                                   category_id = ?, title = ?, description = ?, price = ?, city = ?, image = ?
                                   WHERE id = ? AND provider_id = ?"); 
            
            $stmt->execute([
                $category_id, 
                $title, 
                $description, 
                $price, 
                $city,
                $image_file_name,
                $service_id, 
                $user_id     
            ]);
            
            $pdo->commit(); 
            
            set_message("تم تحديث الخدمة '{$title}' بنجاح.", "success");
            
            // تحديث بيانات المتغير $service ليعكس التغييرات في النموذج
            $service['title'] = $title;
            $service['description'] = $description;
            $service['category_id'] = $category_id;
            $service['price'] = $price;
            $service['city'] = $city;
            $service['image'] = $image_file_name;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            set_message("فشل في حفظ التعديلات: يرجى المحاولة لاحقاً.", "danger");
        }
    }
}
// ------------------ نهاية معالجة الـ POST -------------------

// 6. إعداد واجهة المستخدم (HTML)
$csrf_token = generateCsrfToken();
require_once __DIR__ . '/includes/header.php';
?>



<section class="container auth-page">
    <div class="auth-card" style="max-width: 650px; margin: 50px auto; padding: 30px;">
        <h2 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 25px;">
            تعديل الخدمة: <?php echo htmlspecialchars($service['title']); ?>
        </h2>
        
        <?php display_message(); ?>
        
        <?php if (!empty($errors)): ?>
            <div class="errors alert alert-danger" style="background:#f8d7da; color:#721c24; padding:15px; border-radius:4px; margin-bottom:15px;">
                <p>⚠️ **خطأ في الإدخال:**</p>
                <ul>
                    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" action="edit_service.php?id=<?php echo $service_id; ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="title">عنوان الخدمة *</label>
                <input type="text" id="title" name="title" required 
                       value="<?php echo htmlspecialchars($service['title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="description">وصف مفصل للخدمة *</label>
                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="row" style="display:flex; gap: 20px; margin-bottom: 15px;">
                <div class="form-group" style="flex: 1;">
                    <label for="category_id">التصنيف *</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- اختر تصنيف --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo ($service['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="price">السعر (ريال سعودي) *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required 
                           value="<?php echo htmlspecialchars($service['price'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="city">المدينة *</label>
                <input type="text" id="city" name="city" required 
                       value="<?php echo htmlspecialchars($service['city'] ?? ''); ?>">
            </div>

            <div class="form-group image-controls">
                <label style="font-size: 1.1em; font-weight: bold;">إدارة صورة الخدمة</label>
                
                <?php if ($service['image']): 
                    $image_path = '/local_services/assets/uploads/' . htmlspecialchars($service['image']);
                ?>
                    <div class="current-image-preview">
                        <img src="<?php echo $image_path; ?>" alt="صورة الخدمة الحالية" style="max-width: 120px; height: auto;">
                        <div style="flex-grow: 1;">
                             <p style="margin: 0; font-size: 0.9em; color: #555;">الصورة الحالية: **<?php echo htmlspecialchars($service['image']); ?>**</p>
                             <div style="margin-top: 10px;">
                                <input type="checkbox" id="delete_image" name="delete_image">
                                <label for="delete_image" style="display: inline; font-weight: normal; color: #dc3545;">حذف الصورة الحالية</label>
                             </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="muted-small">لا توجد صورة حالياً للخدمة.</p>
                <?php endif; ?>
                
                <hr style="border-top: 1px dashed #ccc; margin: 15px 0;">
                
                <label for="image_new" style="margin-bottom: 8px; display: block;">تحميل صورة جديدة (اختياري)</label>
                <input type="file" id="image_new" name="image" accept="image/jpeg, image/png, image/gif">
                <p class="muted-small" style="font-size: 0.8em; color: #777; margin-top: 5px;">* عند تحميل صورة جديدة، ستحل محل الصورة السابقة.</p>
            </div>

            <div class="form-group" style="margin-top: 30px; display: flex; justify-content: center;">
    <button type="submit" class="btn btn-primary" style="background:#ffb74d; border-color:#ffb74d; margin-left: 10px;">
        تحديث الخدمة
    </button>
    <a href="/local_services/dashboard.php" class="btn btn-primary" style="text-align: center; display: inline-block;">إلغاء</a>
</div>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>