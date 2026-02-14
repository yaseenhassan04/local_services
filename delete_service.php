<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 

// 1. الحماية: يجب أن يكون المزود مسجلاً دخوله
check_login('provider'); 

global $pdo;
$user = current_user();
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // 2. التحقق من CSRF
    if (!verifyCsrfToken($csrf_token)) {
        set_message("خطأ في أمان النموذج. يرجى المحاولة مرة أخرى.", "danger");
        header("Location: /local_services/dashboard.php");
        exit();
    }
    
    if (!$service_id) {
        set_message("معرف الخدمة غير صالح.", "danger");
        header("Location: /local_services/dashboard.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 🛑 3. التحقق من المالكية والحذف الآمن
        // يتم حذف الخدمة فقط إذا كان provider_id يطابق id المستخدم الحالي
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ? AND provider_id = ?");
        $stmt->execute([$service_id, $user_id]);

        $rows_affected = $stmt->rowCount();

        if ($rows_affected > 0) {
            // منطق حذف الصورة من الخادم (اختياري، يتطلب استعلام SELECT قبل DELETE لجلب اسم الصورة)
            
            $pdo->commit();
            set_message("تم حذف الخدمة بنجاح.", "success");
        } else {
            $pdo->rollBack();
            set_message("فشل في حذف الخدمة. (قد تكون غير موجودة أو لا تملك صلاحية حذفها).", "danger");
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        // يمكنك تسجيل $e->getMessage() في ملف Log
        set_message("حدث خطأ في قاعدة البيانات أثناء محاولة الحذف.", "danger");
    }
} else {
    // منع الوصول المباشر
    set_message("هذا الملف مخصص لمعالجة الطلبات فقط.", "danger");
}

// إعادة التوجيه إلى لوحة التحكم
header("Location: /local_services/dashboard.php");
exit();
?>