<?php
// /client/rate_service.php - معالجة نموذج التقييم

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; 

requireLogin();
global $pdo;
$client_id = getCurrentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../client_dashboard.php");
    exit();
}

// 1. التحقق من CSRF
$csrf_token = filter_input(INPUT_POST, 'csrf_token');
if (!verifyCsrfToken($csrf_token)) {
    set_message("خطأ في التحقق الأمني.", "danger");
    header("Location: ../client_dashboard.php");
    exit();
}

// 2. جلب بيانات التقييم
$order_id   = filter_input(INPUT_POST, 'order_id',   FILTER_VALIDATE_INT);
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$rating     = filter_input(INPUT_POST, 'rating',     FILTER_VALIDATE_INT);
$comment    = trim(filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING));

// تحقق من صحة البيانات
if (!$order_id || !$service_id || $rating === false || $rating < 1 || $rating > 5) {
    set_message("بيانات التقييم غير صالحة أو مفقودة.", "danger");
    header("Location: ../client_dashboard.php");
    exit();
}

try {
    // 3. التحقق من أن العميل هو صاحب الطلب وأن الطلب مكتمل
    $stmt_check = $pdo->prepare("SELECT client_id, status, service_id FROM orders WHERE id = ?");
    $stmt_check->execute([$order_id]);
    $order = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['client_id'] != $client_id) {
        set_message("ليس لديك صلاحية لتقييم هذا الطلب.", "danger");
        header("Location: ../client_dashboard.php");
        exit();
    }

    if ($order['status'] !== 'completed') {
        set_message("لا يمكن تقييم طلب غير مكتمل.", "warning");
        header("Location: ../client_dashboard.php");
        exit();
    }

    // 4. إضافة التقييم
    $sql_insert = "INSERT INTO ratings (service_id, client_id, order_id, rating, comment, created_at) 
                   VALUES (?, ?, ?, ?, ?, NOW())";
    $pdo->prepare($sql_insert)->execute([$service_id, $client_id, $order_id, $rating, $comment]);

    set_message("🌟 شكراً لتقييمك! تم تسجيله بنجاح.", "success");
    header("Location: ../client_dashboard.php"); 
    exit();

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { 
        set_message("لقد قمت بتقييم هذه الخدمة بالفعل.", "warning");
    } else {
        error_log("Rating Submission Error: " . $e->getMessage());
        set_message("حدث خطأ في تسجيل التقييم. حاول مرة أخرى.", "danger");
    }
    header("Location: ../client_dashboard.php");
    exit();
}
?>