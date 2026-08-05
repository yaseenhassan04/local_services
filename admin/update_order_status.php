<?php
//  - معالجة تحديث حالة الطلب

session_start();
//  تأكد من أن المسار هنا صحيح (خطوتان للخلف)
require_once '../../includes/db.php'; 
require_once '../../includes/functions.php'; // 🛑 هذا الملف يحتوي على get_status_display() و set_message()
require_once '../../includes/auth.php'; 

// 1. حماية الصفحة: يجب أن يكون المدير فقط
check_login('admin'); 
// ...
//  1. حماية الصفحة: يجب أن يكون المدير فقط

// التحقق من أن الطلب تم إرساله بطريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../manage_orders.php");
    exit;
}

// 2. التحقق من البيانات المرسلة
if (!isset($_POST['order_id']) || !isset($_POST['new_status'])) {
    set_message("بيانات الطلب غير مكتملة.", "danger");
    header("Location: ../manage_orders.php");
    exit;
}

$order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
$new_status = trim($_POST['new_status']);

// 3. التحقق من صلاحية القيمة المرسلة للحالة
$valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    set_message("حالة الطلب المرسلة غير صالحة.", "danger");
    header("Location: ../view_order.php?id=" . $order_id);
    exit;
}

// 4. تحديث حالة الطلب في قاعدة البيانات
try {
    $sql = "UPDATE orders 
            SET status = :new_status 
            WHERE id = :order_id"; 

    global $pdo; // تأكد من وجود هذا السطر إذا كنت تستخدم الاتصال العام
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':new_status', $new_status);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // يجب أن نستخدم set_message التي يفترض وجودها في includes/functions.php
        set_message("تم تحديث حالة الطلب رقم {$order_id} بنجاح إلى " . get_status_display($new_status) . ".", "success");
    } else {
        set_message("فشل في تحديث حالة الطلب.", "danger");
    }

} catch (PDOException $e) {
    // تم تغيير رسالة الخطأ لتكون أوضح للإدارة، ولكن الاستعلام الآن مصحح.
    set_message("خطأ قاعدة بيانات أثناء التحديث: " . $e->getMessage(), "danger");
}

// 5. إعادة التوجيه إلى صفحة عرض التفاصيل
header("Location: ../view_order.php?id=" . $order_id);
exit;
?>
