<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php'; 

requireLogin();
global $pdo;

$client_id = getCurrentUser()['id'];

// التحقق من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// 1. التحقق من CSRF (ضروري جداً للأمان)
if (!verifyCsrfToken(filter_input(INPUT_POST, 'csrf_token'))) {
    display_message("خطأ في التحقق الأمني. حاول مرة أخرى.", "danger");
    header("Location: services.php");
    exit();
}

// 2. جلب وتصفية بيانات الإدخال
$service_id  = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$provider_id = filter_input(INPUT_POST, 'provider_id', FILTER_VALIDATE_INT);
// يتم تجاهل تفاصيل البطاقة لأنها محاكاة، ولكن يجب التحقق من صحة المعرفات الرئيسية
if (!$service_id || !$provider_id) {
    display_message("بيانات الطلب غير مكتملة أو غير صالحة.", "danger");
    header("Location: services.php");
    exit();
}

// 3. محاكاة عملية الدفع (Simulation)
// في النظام الحقيقي، هنا تتم المعالجة عبر بوابة الدفع.
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

        $price = $service['price']; // جلب السعر لتوثيق الطلب
        $status = 'pending'; // تعيين الحالة الأولية للطلب

        $sql = "INSERT INTO orders (service_id, client_id, provider_id, order_date, total_amount, status) 
                VALUES (?, ?, ?, NOW(), ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$service_id, $client_id, $provider_id, $price, $status]);
        
        $order_id = $pdo->lastInsertId();

        // 5. توجيه العميل إلى صفحة النجاح/لوحة التحكم
        display_message("✅ تم إتمام طلبك رقم **#{$order_id}** بنجاح! سيتم التواصل معك من قبل المزود.", "success");
        header("Location: dashboard.php");
        exit();

    } catch (PDOException $e) {
        error_log("Database Error on place_order: " . $e->getMessage());
        display_message("حدث خطأ في تسجيل الطلب. يرجى المحاولة لاحقاً.", "danger");
        header("Location: checkout.php?service_id={$service_id}");
        exit();
    }
} else {
    // في حال فشل الدفع (نظرياً)
    display_message("فشلت عملية الدفع. يرجى التحقق من بيانات البطاقة والمحاولة مجدداً.", "warning");
    header("Location: checkout.php?service_id={$service_id}");
    exit();
}
?>