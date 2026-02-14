<?php
// /local_services/actions/submit_review.php - معالجة إرسال التقييم

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/functions.php'; 

check_login('client'); 
global $pdo;
$user = getCurrentUser();
$client_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_message("طريقة وصول غير صحيحة.", "danger");
    header("Location: /local_services/client_dashboard.php");
    exit;
}

// 1. جلب وتصفية البيانات
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$provider_id = filter_input(INPUT_POST, 'provider_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment = sanitize_input($_POST['comment'] ?? '');
$token = sanitize_input($_POST['csrf_token'] ?? '');

// التحقق من CSRF
if (!verifyCsrfToken($token)) {
    set_message("خطأ أمني: انتهت صلاحية الجلسة.", "danger");
    header("Location: /local_services/review_service.php?order_id=" . ($order_id ?? 0));
    exit;
}

// التحقق من صلاحية البيانات الأساسية
if (!$order_id || !$service_id || !$provider_id || $rating === false || $rating < 1 || $rating > 5) {
    set_message("بيانات التقييم غير صالحة. الرجاء التأكد من إدخال التقييم (من 1-5).", "danger");
    header("Location: /local_services/review_service.php?order_id=" . ($order_id ?? 0));
    exit;
}

try {
    // 2. التحقق النهائي من حالة الطلب وعدم التقييم المسبق (لأمان إضافي)
    // هذا يضمن أن المستخدم لم يقم بتغيير بيانات الطلب في النموذج
    $stmt_check = $pdo->prepare("
        SELECT id FROM orders 
        WHERE id = ? AND client_id = ? AND status = 'completed'
    ");
    $stmt_check->execute([$order_id, $client_id]);
    if (!$stmt_check->fetchColumn()) {
        set_message("الطلب غير مكتمل أو غير تابع لك، لا يمكن تقييمه.", "danger");
        header("Location: /local_services/client_dashboard.php");
        exit;
    }

    $stmt_review_exists = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ?");
    $stmt_review_exists->execute([$order_id]);
    if ($stmt_review_exists->fetch()) {
        set_message("لقد قمت بتقييم هذا الطلب مسبقاً.", "info");
        header("Location: /local_services/view_order.php?id=" . $order_id);
        exit;
    }
    
    // 3. إدخال التقييم في جدول reviews
    $stmt_insert_review = $pdo->prepare("
        INSERT INTO reviews (order_id, client_id, provider_id, service_id, rating, comment, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt_insert_review->execute([$order_id, $client_id, $provider_id, $service_id, $rating, $comment]);
    
    // 4. تحديث متوسط تقييم المزود في جدول providers
    // (يجب أن يكون لديك عمود 'user_id' و 'rating' في جدول providers)
    $stmt_update_rating = $pdo->prepare("
        UPDATE providers 
        SET rating = (
            SELECT AVG(rating) 
            FROM reviews 
            WHERE provider_id = ?
        )
        WHERE user_id = ?
    ");
    $stmt_update_rating->execute([$provider_id, $provider_id]);
    
    // 5. رسالة النجاح
    set_message("✅ تم إرسال تقييمك بنجاح! شكراً لك.", "success");
    header("Location: /local_services/view_order.php?id=" . $order_id);
    
} catch (PDOException $e) {
    error_log("Submit Review Error: " . $e->getMessage());
    set_message("❌ حدث خطأ في قاعدة البيانات أثناء إرسال التقييم.", "danger");
    header("Location: /local_services/review_service.php?order_id=" . $order_id);
}

exit;