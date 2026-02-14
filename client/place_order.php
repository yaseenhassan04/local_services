<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; 

requireLogin();
global $pdo;

$client_id = getCurrentUser()['id'];

// التحقق من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php"); // توجيه لصفحة البداية
    exit();
}

// 1. التحقق من CSRF
$csrf_token = filter_input(INPUT_POST, 'csrf_token');
if (!verifyCsrfToken($csrf_token)) {
    display_message("خطأ في التحقق الأمني. حاول مرة أخرى.", "danger");
    header("Location: services.php");
    exit();
}

// 2. جلب وتصفية بيانات الإدخال
$service_id  = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$provider_id = filter_input(INPUT_POST, 'provider_id', FILTER_VALIDATE_INT);
$payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING); // طريقة الدفع

if (!$service_id || !$provider_id || empty($payment_method)) {
    display_message("بيانات الطلب غير مكتملة أو غير صالحة.", "danger");
    header("Location: service_detail.php?id={$service_id}"); 
    exit();
}

// 3. محاكاة عملية الدفع (Simulation)
// نفترض النجاح دائماً في هذا المشروع التعليمي
$payment_successful = true; 

if ($payment_successful) {
    // 4. تسجيل الطلب في قاعدة البيانات (جدول orders)
    try {
        $stmt_check = $pdo->prepare("SELECT price FROM services WHERE id = ?");
        $stmt_check->execute([$service_id]);
        $service = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$service) {
            display_message("الخدمة غير موجودة.", "danger");
            header("Location: services.php");
            exit();
        }

        $price = $service['price']; 
        $status = 'pending'; // الحالة الأولية

        // الاستعلام يتضمن حقل payment_method
        $sql = "INSERT INTO orders (service_id, client_id, provider_id, order_date, total_amount, status, payment_method) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$service_id, $client_id, $provider_id, $price, $status, $payment_method]);
        
        $order_id = $pdo->lastInsertId();

        // 5. توجيه العميل إلى لوحة التحكم
        display_message("✅ تم إتمام طلبك رقم **#{$order_id}** بنجاح! طريقة الدفع: **{$payment_method}**.", "success");
        header("Location: ../dashboard.php"); // العودة إلى لوحة التحكم الرئيسية في الجذر
        exit();

    } catch (PDOException $e) {
        error_log("Database Error on place_order: " . $e->getMessage());
        display_message("حدث خطأ في تسجيل الطلب. يرجى المحاولة لاحقاً.", "danger");
        header("Location: service_detail.php?id={$service_id}");
        exit();
    }
} else {
    display_message("فشلت عملية الدفع. يرجى التحقق من البيانات والمحاولة مجدداً.", "warning");
    header("Location: service_detail.php?id={$service_id}");
    exit();
}
?>