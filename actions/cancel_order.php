<?php
// /local_services/actions/cancel_order.php - إلغاء طلب من قبل العميل

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/functions.php'; 

global $pdo; 

// 1. التحقق من الأمان والصلاحيات
check_login('client'); 

$user = getCurrentUser();
$user_id = $user['id'];
$order_id = sanitize_input($_GET['id'] ?? null);
$token = sanitize_input($_GET['token'] ?? null);

// التأكد من البيانات ورمز CSRF
if (!$order_id || !is_numeric($order_id) || !verifyCsrfToken($token)) {
    set_message("طلب غير صالح أو انتهت صلاحية الجلسة.", "danger");
    header("Location: /local_services/client_dashboard.php");
    exit;
}

try {
    // 2. التحقق من حالة الطلب قبل الإلغاء
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND client_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        set_message("الطلب غير موجود أو لا تملك صلاحية الوصول إليه.", "danger");
        header("Location: /local_services/client_dashboard.php");
        exit;
    }

    // 3. السماح بالإلغاء فقط إذا كانت الحالة "قيد الانتظار" (pending)
    if ($order['status'] !== 'pending') {
        set_message("لا يمكن إلغاء هذا الطلب. حالته الحالية هي: " . get_status_display($order['status']), "warning");
        header("Location: /local_services/view_order.php?id=" . $order_id);
        exit;
    }

    // 4. تنفيذ التحديث (الإلغاء)
    $update_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND client_id = ?");
    $update_stmt->execute([$order_id, $user_id]);

    if ($update_stmt->rowCount()) {
        set_message("تم إلغاء الطلب رقم #{$order_id} بنجاح.", "success");
    } else {
        set_message("فشل في إلغاء الطلب. قد تكون الحالة قد تغيرت بالفعل.", "warning");
    }

} catch (PDOException $e) {
    // خطأ قاعدة بيانات
    error_log("Order Cancellation Error: " . $e->getMessage());
    set_message("حدث خطأ في قاعدة البيانات أثناء الإلغاء.", "danger");
}

// التوجيه إلى صفحة عرض الطلب بعد الإجراء
header("Location: /local_services/view_order.php?id=" . $order_id);
exit;