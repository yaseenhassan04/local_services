<?php
require_once '../includes/auth.php'; 
require_once '../includes/db.php';
require_once '../includes/functions.php';

//  1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

//  2. بدء الجلسة إذا لم تكن قد بدأت في مكان آخر
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

global $pdo;
$categories = [];

// ==========================================================
// 2. معالجة طلبات الإضافة والحذف والتعديل (مع التحقق من CSRF TOKEN)
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // **********************************
    // يجب تفعيل التحقق من CSRF TOKEN هنا! (تترك معطلة مؤقتاً للاختبار)
    // **********************************
    
    $action = sanitize_input($_POST['action'] ?? '');
    
    // ------------------------------------
    // معالجة إضافة تصنيف جديد
    // ------------------------------------
    if ($action === 'add_category') {
        $name = sanitize_input($_POST['name'] ?? '');
        //  تم إضافة جلب نوع التصنيف
        $type = sanitize_input($_POST['type'] ?? 'local'); // القيمة الافتراضية local
        $image_name = '';

        if (empty($name)) {
            set_message("يجب إدخال اسم التصنيف.", "danger");
        } else {
            try {
                //  معالجة رفع الأيقونة 
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    // نفترض أن وظيفة upload_file تقوم بالتحقق اللازم وتحفظ الملف
                    // يجب أن تقوم هذه الوظيفة بحفظ الأيقونات في assets/uploads/ أو assets/icons/ بناءً على إعداداتك
                    $image_name = upload_file($_FILES['image'], 'icons'); 
                }

                //  تم تعديل الـ SQL ليشمل الـ type
                $stmt = $pdo->prepare("INSERT INTO categories (name, image, type) VALUES (?, ?, ?)"); 
                $stmt->execute([$name, $image_name, $type]);
                
                set_message("تم إضافة التصنيف '{$name}' بنجاح!", "success");
                header("Location: manage_categories.php");
                exit();
                
            } catch (PDOException $e) {
                // إذا كان الخطأ بسبب أن التصنيف موجود مسبقاً، يمكنك التعامل معه هنا
                if ($e->getCode() == '23000') {
                     set_message("التصنيف '{$name}' موجود بالفعل.", "danger");
                } else {
                    set_message("خطأ في قاعدة البيانات أثناء الإضافة: " . $e->getMessage(), "danger");
                }
            } catch (Exception $e) {
                set_message("خطأ في رفع الملف: " . $e->getMessage(), "danger");
            }
        }
    }

    // ------------------------------------
    // معالجة حذف تصنيف
    // ------------------------------------
    if ($action === 'delete_category') {
        $id = (int) sanitize_input($_POST['category_id'] ?? 0);
        
        if ($id > 0) {
            try {
                // جلب اسم الصورة المرتبط قبل حذف الصف
                $stmt_get_image = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
                $stmt_get_image->execute([$id]);
                $category_data = $stmt_get_image->fetch(PDO::FETCH_ASSOC);
                
                if ($category_data && !empty($category_data['image'])) {
                    // 🛑 ملاحظة: تم تعديل المسار هنا ليكون assets/uploads/ أو assets/icons/ بناءً على مكان حفظ الأيقونات
                    // نفترض أنه يتم حفظ الأيقونات المرفوعة بواسطة المدير في مجلد assets/uploads/
                    $file_path = __DIR__ . "/../assets/uploads/" . $category_data['image'];
                    if (file_exists($file_path)) {
                        unlink($file_path); // حذف الملف من الخادم
                    }
                }

                // حذف التصنيف نفسه (قد تفشل هذه العملية إذا كان هناك خدمات مرتبطة)
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                
                set_message("تم حذف التصنيف بنجاح.", "success");
                header("Location: manage_categories.php");
                exit();
                
            } catch (PDOException $e) {
                // 23000 هو رمز خطأ القيود الخارجية (Foreign Key Constraint)
                if ($e->getCode() == '23000') { 
                     set_message("خطأ: لا يمكن حذف التصنيف، يجب حذف الخدمات المرتبطة به أولاً.", "danger");
                } else {
                     set_message("خطأ في قاعدة البيانات أثناء الحذف: " . $e->getMessage(), "danger");
                }
            }
        }
    }
}


// ==========================================================
// 3. جلب التصنيفات الحالية للعرض
// ==========================================================
try {
    $stmt = $pdo->query("SELECT id, name, image, type FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    set_message("فشل في جلب التصنيفات: " . $e->getMessage(), "danger");
}

include_once '../includes/header.php'; 
?>

<div class="container admin-content">
    
    <h2>📝 إدارة التصنيفات (<?= count($categories) ?>)</h2>
    <?php display_message(); ?>
    
    
    <div style="border: 1px solid #ccc; padding: 20px; margin-bottom: 30px; border-radius: 8px;">
        <h3>إضافة تصنيف جديد</h3>
        <form method="POST" action="manage_categories.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_category">
            
            <label for="name">اسم التصنيف:</label>
            <input type="text" id="name" name="name" required style="padding: 8px; margin-bottom: 10px; width: 300px;">
            
            <label for="type" style="margin-left: 20px;">النوع:</label>
            <select id="type" name="type" required style="padding: 8px; margin-bottom: 10px;">
                <option value="local">خدمات محلية</option>
                <option value="freelance">أعمال حرة</option>
            </select>
            
            <label for="image" style="margin-left: 20px;">أيقونة (صورة):</label>
            <input type="file" id="image" name="image" accept="image/*" style="padding: 8px; margin-bottom: 10px;">
            
            <button type="submit" style="padding: 10px 20px; background-color: blue; color: white; border: none; border-radius: 4px; cursor: pointer;">
                إضافة
            </button>
        </form>
    </div>
    
    
    <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr>
                <th>ID</th>
                <th>الأيقونة</th>
                <th>اسم التصنيف</th>
                <th>نوع التصنيف (Type)</th> <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="5" style="text-align: center;">لا توجد تصنيفات حالياً.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                     <td>
                            <?php 
                                // 🛑 تحديث المسار: استخدام assets/uploads/ لجميع الصور المرفوعة عبر المدير
                                $image_name = trim($cat['image'] ?? ''); 
                                $is_uploaded = !empty($image_name) && file_exists(__DIR__ . "/../assets/uploads/" . $image_name);

                                if ($is_uploaded):
                                    $icon_path = "/local_services/assets/uploads/" . htmlspecialchars($image_name); 
                            ?>
                            <img src="<?= $icon_path ?>" alt="<?= htmlspecialchars($cat['name']) ?>" 
                                 style="width: 40px; height: 40px; border-radius: 5px; object-fit: contain;">
                            <?php else: ?>
                                (لا يوجد)
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        
                        <td><?= htmlspecialchars($cat['type'] ?? 'غير محدد') ?></td>
                        
                        <td>
                            <a href="edit_category.php?id=<?= $cat['id'] ?>" style="margin-left: 10px;">تعديل</a>
                            
                            <form method="POST" action="manage_categories.php" style="display: inline-block;">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                
                                <button type="submit" onclick="return confirm('هل أنت متأكد من حذف هذا التصنيف؟ سيتم حذف جميع الخدمات المرتبطة به.')"
                                         style="background-color: red; color: white; border: none; padding: 5px 10px; cursor: pointer;">
                                    حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
</div>

<?php 
include_once '../includes/footer.php'; 
?>