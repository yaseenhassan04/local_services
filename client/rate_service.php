<?php
// /client/rate_service.php - معالجة نموذج التقييم

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; 

requireLogin();
global $pdo;

$client_id = getCurrentUser()['id'];

// التحقق من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit();
}

// 1. جلب بيانات التقييم
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT); // التقييم من 1 إلى 5
$comment = trim(filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING));

// تحقق من صحة البيانات
if (!$order_id || !$service_id || $rating === false || $rating < 1 || $rating > 5) {
    display_message("بيانات التقييم غير صالحة أو مفقودة.", "danger");
    // قد تحتاج للتوجيه إلى صفحة عرض الطلب (view_order.php) إذا كانت موجودة
    header("Location: ../dashboard.php");
    exit();
}

try {
    // 2. التحقق من أن العميل هو من قام بالطلب وأن الطلب مكتمل ولم يتم تقييمه بعد
    $stmt_check = $pdo->prepare("SELECT client_id, status FROM orders WHERE id = ? AND service_id = ?");
    $stmt_check->execute([$order_id, $service_id]);
    $order = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['client_id'] != $client_id) {
        display_message("ليس لديك صلاحية لتقييم هذا الطلب.", "danger");
        header("Location: ../dashboard.php");
        exit();
    }
    


    $sql_insert_rating = "INSERT INTO ratings (service_id, client_id, order_id, rating, comment) 
                          VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $pdo->prepare($sql_insert_rating);
    $stmt_insert->execute([$service_id, $client_id, $order_id, $rating, $comment]);
    
    // 4. تحديث حالة الطلب في جدول orders (اختياري لتسجيل أنه تم تقييمه)
    // $sql_update_order = "UPDATE orders SET is_rated = 1 WHERE id = ?";
    // $pdo->prepare($sql_update_order)->execute([$order_id]);

    display_message("🌟 شكراً لتقييمك! تم تسجيل التقييم بنجاح.", "success");
    
    // التوجيه إلى لوحة التحكم أو صفحة تفاصيل الطلب
    header("Location: ../dashboard.php"); 
    exit();

} catch (PDOException $e) {
    // تحقق إذا كان الخطأ هو محاولة إدراج تقييم موجود مسبقاً (Unique Constraint)
    if ($e->getCode() == 23000) { 
         display_message("لقد قمت بتقييم هذه الخدمة بالفعل.", "warning");
    } else {
        error_log("Rating Submission Error: " . $e->getMessage());
        display_message("حدث خطأ في تسجيل التقييم. حاول مرة أخرى.", "danger");
    }
    header("Location: ../dashboard.php");
    exit();
}
?>