<?php
// /local_services/actions/place_order.php - معالجة إرسال نموذج الطلب والدفع (بصيغة AJAX)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php'; 
require_once __DIR__ . '/../includes/functions.php'; 

global $pdo; 
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);

// تعيين نوع الاستجابة لـ JSON في جميع الأحوال
header('Content-Type: application/json');

// دالة موحدة لإرجاع الخطأ
function return_error($message, $service_id = null) {
    // يمكنك إضافة set_message هنا إذا كنت تريد تخزين الرسالة في الجلسة، لكن AJAX عادة يعالجها
    // set_message($message, "danger"); 
    
    // إرجاع JSON
    echo json_encode([
        'success' => false, 
        'message' => $message, 
        'redirect_url' => '/local_services/service_detail.php?id=' . ($service_id ?? '')
    ]);
    exit;
}

// 1. التحقق من تسجيل الدخول كعميل
$user = getCurrentUser();
if ($user === false || $user['role'] !== 'client') {
    return_error("يجب تسجيل الدخول كعميل لتقديم الطلب.", $service_id);
}
$client_id = $user['id'];


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return_error("طريقة وصول غير صحيحة.", $service_id);
}


// 2. جلب البيانات من النموذج
$token = sanitize_input($_POST['csrf_token'] ?? '');
// $service_id تم تعريفه في البداية
$provider_id = filter_input(INPUT_POST, 'provider_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$payment_method = sanitize_input($_POST['payment_method'] ?? '');
$account_number = sanitize_input($_POST['account_number'] ?? ''); // رقم الحساب/الهاتف للدفع
$details = sanitize_input($_POST['details'] ?? ''); // تفاصيل الطلب من العميل

// 3. التحقق من CSRF والصحة الأساسية
if (!verifyCsrfToken($token)) {
    return_error("خطأ أمني: انتهت صلاحية الجلسة. يرجى تحديث الصفحة.", $service_id);
}

if (!$service_id || !$provider_id || $amount <= 0 || empty($payment_method) || empty($details) || empty($account_number)) {
    return_error("يجب ملء جميع البيانات المطلوبة لتقديم الطلب والدفع.", $service_id);
}

// 4. محاكاة عملية الدفع وتحديد حالات الطلب
// 💡 ملاحظة: يجب أن تكون هذه الحالات متطابقة مع الأعمدة في قاعدة البيانات (orders table)
$order_status = 'pending';  // حالة الطلب الأولية (في انتظار المراجعة)
$payment_status = 'pending_upload'; // حالة الدفع (في انتظار تأكيد الدفع من المزود)
$proof = "الطريقة: " . $payment_method . " - رقم تأكيد العميل: " . $account_number; 

// إذا كان الدفع كاملاً (في بيئة حقيقية يتم التحقق عبر API)
// في هذه البيئة الافتراضية، سنفترض أن الطلب ينتقل مباشرة إلى 'processing' إذا كان الدفع قد تم فعلاً.
// سأستخدم 'pending' حالياً للسماح للمزود بتأكيد إثبات الدفع.

try {
    $pdo->beginTransaction();

    // 5. إدراج الطلب في جدول orders
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            client_id, provider_id, service_id, amount, details, status, payment_status, payment_proof, order_date
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )
    ");

    $stmt->execute([
        $client_id,
        $provider_id,
        $service_id,
        $amount,
        $details,
        $order_status, 
        $payment_status,
        $proof 
    ]);

    $new_order_id = $pdo->lastInsertId();
    $pdo->commit();

    // 6. استجابة النجاح (JSON)
    // نستخدم set_message هنا لأننا نطلب إعادة تحميل الصفحة في الـ JS بعد ظهور الـ Success Modal
    set_message("✅ تم دفع وتقديم طلبك بنجاح! رقم الطلب هو #{$new_order_id}. يرجى انتظار تواصل المزود.", "success");
    
    echo json_encode([
        'success' => true, 
        'message' => "تم تقديم الطلب بنجاح. رقم الطلب #{$new_order_id}.",
        // يمكن توجيه العميل إلى صفحة الطلب مباشرة بعد النجاح بدلاً من إعادة تحميل الصفحة الرئيسية للخدمة
        'redirect_url' => '/local_services/view_order.php?id=' . $new_order_id 
    ]);
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Order Submission Error: " . $e->getMessage());
    return_error("حدث خطأ في قاعدة البيانات أثناء معالجة الطلب والدفع: " . $e->getMessage(), $service_id);
}

// لضمان عدم وجود مخرجات غير مرغوبة
exit; 
?>