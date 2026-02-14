<?php


require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

global $pdo;
$user = getCurrentUser();
$user_id = $user['id'];
$user_role = $user['role'];

// 1. الحصول على البيانات المرسلة
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING); // الحالة الجديدة من قائمة الاختيار
$csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

// 2. التحقق الأساسي
if (!$order_id || empty($new_status)) {
    set_message("بيانات الطلب أو الحالة الجديدة غير صالحة.", "danger");
    header("Location: /local_services/dashboard.php");
    exit;
}

// 3. التحقق من CSRF Token
if (!verifyCsrfToken($csrf_token)) {
    set_message("فشل التحقق الأمني.", "danger");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;
}

try {
    // 4. جلب بيانات الطلب الحالية والتحقق من الملكية
    // *** التعديل هنا: إضافة client_id لجلب بيانات العميل ***
    $stmt = $pdo->prepare("
        SELECT id, provider_id, client_id 
        FROM orders 
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        set_message("الطلب غير موجود.", "danger");
        header("Location: /local_services/dashboard.php");
        exit;
    }

    // 5. التحقق من الصلاحيات
    $is_admin = ($user_role === 'admin');
    $is_order_provider = ($user_id == $order['provider_id']);
    $is_order_client = ($user_id == $order['client_id']); // جلب ID العميل
    
    // الشرط الأصلي (المدير والمزود) - يمكنهم تحديث أي حالة
    $can_update_provider_actions = $is_admin || ($user_role === 'provider' && $is_order_provider);

    // الشرط الجديد للعميل: يسمح للعميل صاحب الطلب فقط إذا كانت الحالة الجديدة هي 'cancelled'
    $can_client_cancel = $is_order_client && ($user_role === 'client') && ($new_status === 'cancelled');

    // *** التعديل الرئيسي: إضافة شرط العميل للإلغاء إلى منطق التنفيذ ***
    // الشرط النهائي: مسموح إذا تحقق شرط المزود/المدير OR تحقق شرط العميل للإلغاء
    if (!$can_update_provider_actions && !$can_client_cancel) {
        set_message("لا توجد صلاحيات كافية لتحديث الحالة يدوياً.", "danger");
        header("Location: /local_services/view_order.php?id=" . $order_id);
        exit;
    }

    // 6. التحقق من قيمة الحالة الجديدة (اختياري لكن موصى به)
    $allowed_statuses = ['pending', 'processing', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        set_message("قيمة الحالة غير مسموح بها.", "danger");
        header("Location: /local_services/view_order.php?id=" . $order_id);
        exit;
    }

    // 7. تنفيذ التحديث اليدوي
    $update_stmt = $pdo->prepare("
        UPDATE orders 
        SET status = ? 
        WHERE id = ?
    ");
    $update_stmt->execute([$new_status, $order_id]);

    // تأكيد النجاح
    set_message("✅ تم تحديث حالة الطلب يدوياً إلى: " . $new_status, "success");

    // 8. إعادة التوجيه
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;

} catch (PDOException $e) {
    error_log("Manual status update error: " . $e->getMessage());
    set_message("❌ حدث خطأ في قاعدة البيانات أثناء التحديث.", "danger");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    exit;
}
?>