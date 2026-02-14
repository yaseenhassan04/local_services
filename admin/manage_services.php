<?php

require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php'; 

//  1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 

global $pdo;
$services = [];

// ==========================================================
// 2. معالجة طلبات تغيير الحالة أو الحذف
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // (يفضل إضافة تحقق CSRF Token هنا لأمان أفضل)
    
    $action = sanitize_input($_POST['action'] ?? '');
    $service_id = (int) sanitize_input($_POST['service_id'] ?? 0);
    
    if ($service_id > 0) {
        try {
            if ($action === 'toggle_status') {
                $current_status = (int) sanitize_input($_POST['current_status'] ?? 0);
                $new_status = $current_status === 1 ? 0 : 1; // عكس الحالة الحالية
                
                $stmt = $pdo->prepare("UPDATE services SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $service_id]);
                
                set_message("تم تغيير حالة الخدمة بنجاح.", "success");
                
            } elseif ($action === 'delete_service') {
                
                //  حذف ملف الصورة المرتبط بالخدمة قبل حذف الصف
                $stmt_get_image = $pdo->prepare("SELECT image FROM services WHERE id = ?");
                $stmt_get_image->execute([$service_id]);
                $service_data = $stmt_get_image->fetch(PDO::FETCH_ASSOC);
                
                if ($service_data && !empty($service_data['image'])) {
                    // نفترض أن الصور محفوظة في assets/uploads/services/
                    $file_path = __DIR__ . "/../assets/uploads/services/" . $service_data['image'];
                    if (file_exists($file_path)) {
                        unlink($file_path); 
                    }
                }
                
                // حذف الخدمة
                $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                $stmt->execute([$service_id]);
                
                set_message("تم حذف الخدمة بنجاح.", "success");
            }

            header("Location: manage_services.php");
            exit();
            
        } catch (PDOException $e) {
            set_message("خطأ في قاعدة البيانات أثناء معالجة الطلب: " . $e->getMessage(), "danger");
        }
    } else {
        set_message("معرف الخدمة غير صالح.", "danger");
    }
}

// ==========================================================
// 3. جلب جميع الخدمات للعرض (باستخدام LEFT JOIN المصحح)
// ==========================================================
try {
    // جلب الخدمات مع اسم المزود (u.full_name) واسم التصنيف (c.name)
    $sql = "SELECT s.id, s.title, s.price, s.description, s.is_active, s.created_at, 
                   u.full_name AS provider_name, c.name AS category_name
            FROM services s
            JOIN users u ON s.provider_id = u.id -- نفترض أن user_id هو provider_id لديك
            LEFT JOIN categories c ON s.category_id = c.id --  استخدام LEFT JOIN
            ORDER BY s.created_at DESC";
            
    $stmt = $pdo->query($sql);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    set_message("فشل في جلب قائمة الخدمات: " . $e->getMessage(), "danger");
}

include_once '../includes/header.php'; 
?>

<div class="container admin-content">
    
    <h2>📝 إدارة الخدمات (<?= count($services) ?>)</h2>
    <?php display_message(); ?>
    
    <div style="margin-bottom: 20px;">
        <a href="add_service.php" style="background-color: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;">+ إضافة خدمة جديدة</a>
    </div>
    
    <table border="1" style="width: 100%; border-collapse: collapse; margin-top: 20px; text-align: center;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">عنوان الخدمة</th>
                <th style="padding: 10px;">المزود</th>
                <th style="padding: 10px;">التصنيف</th>
                <th style="padding: 10px;">السعر (ر.س)</th>
                <th style="padding: 10px;">الحالة</th>
                <th style="padding: 10px;">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($services)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 15px;">لا توجد خدمات مضافة حالياً في المنصة.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td style="padding: 10px;"><?= $service['id'] ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($service['title']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($service['provider_name']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($service['category_name'] ?? 'لا يوجد') ?></td>
                        <td style="padding: 10px;"><?= number_format($service['price'], 2) ?></td>
                        
                        <td style="padding: 10px;">
                            <span style="color: <?= $service['is_active'] ? 'green' : 'red' ?>; font-weight: bold;">
                                <?= $service['is_active'] ? 'نشط' : 'موقف' ?>
                            </span>
                        </td>
                        
                        <td style="padding: 10px; white-space: nowrap;">
                            <a href="edit_service.php?id=<?= $service['id'] ?>" style="margin-right: 10px; color: #007bff; text-decoration: none;">تعديل</a>
                            
                            <form method="POST" action="manage_services.php" style="display: inline-block;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $service['is_active'] ?>">
                                <button type="submit" 
                                        style="background-color: <?= $service['is_active'] ? 'orange' : 'green' ?>; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px;">
                                    <?= $service['is_active'] ? 'إيقاف' : 'تفعيل' ?>
                                </button>
                            </form>
                            
                            <form method="POST" action="manage_services.php" style="display: inline-block; margin-right: 10px;">
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                <button type="submit" onclick="return confirm('تحذير: هل أنت متأكد من حذف هذه الخدمة نهائياً؟')"
                                        style="background-color: red; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 4px;">
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