<?php
// /local_services/actions/create_order.php - معالجة إضافة طلب جديد

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/functions.php'; 

global $pdo; 

// 1. التحقق من صلاحية المستخدم
check_login('client'); 

// 2. التحقق من طريقة الإرسال
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /local_services/client_dashboard.php");
    exit;
}

$user = getCurrentUser();
$client_id = $user['id'];

// 3. جلب وتطهير البيانات
$token = sanitize_input($_POST['csrf_token'] ?? '');
$service_id = sanitize_input($_POST['service_id'] ?? '');
$provider_id = sanitize_input($_POST['provider_id'] ?? '');
$quantity = sanitize_input($_POST['quantity'] ?? 1);
$client_notes = sanitize_input($_POST['client_notes'] ?? '');
$final_amount = sanitize_input($_POST['final_amount'] ?? 0); 
$price_per_unit = sanitize_input($_POST['price'] ?? 0); // السعر الأصلي للوحدة

// 4. التحقق من CSRF
if (!verifyCsrfToken($token)) {
    set_message("خطأ أمني: انتهت صلاحية الجلسة.", "danger");
    header("Location: /local_services/order.php?service_id=" . $service_id);
    exit;
}

// 5. التحقق من صحة المدخلات الأساسية
if (empty($service_id) || empty($provider_id) || empty($client_notes) || $final_amount <= 0 || $quantity < 1) {
    set_message("يرجى ملء جميع الحقول المطلوبة بشكل صحيح.", "danger");
    header("Location: /local_services/order.php?service_id=" . $service_id);
    exit;
}


// 6. التحقق النهائي لحساب المبلغ (تأكيد من جانب الخادم)
$expected_amount = (float)$price_per_unit * (int)$quantity;
if (abs((float)$final_amount - $expected_amount) > 0.01) {
    // إذا كان هناك تلاعب بسيط في السعر، استخدم القيمة المحسوبة في الخادم
    $final_amount = $expected_amount;
}


try {
    // 7. إدراج الطلب في قاعدة البيانات
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            client_id, 
            provider_id, 
            service_id, 
            amount, 
            quantity, 
            client_notes, 
            status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'pending'
        )
    ");

    $stmt->execute([
        $client_id, 
        $provider_id, 
        $service_id, 
        $final_amount, 
        $quantity, 
        $client_notes
    ]);

    $new_order_id = $pdo->lastInsertId();

    set_message("تم إنشاء طلبك رقم #{$new_order_id} بنجاح. سنقوم بإبلاغ المزود.", "success");
    // التوجيه إلى صفحة عرض الطلب الجديدة
    header("Location: /local_services/view_order.php?id=" . $new_order_id);
    exit;

} catch (PDOException $e) {
    // خطأ قاعدة بيانات
    error_log("Order Creation Error: " . $e->getMessage());
    set_message("حدث خطأ في قاعدة البيانات أثناء إنشاء الطلب.", "danger");
    header("Location: /local_services/order.php?service_id=" . $service_id);
    exit;
}