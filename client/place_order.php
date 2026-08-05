<?php
// ============================================================
// الموقع: C:\xampp\htdocs\local_services\client\place_order.php
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
global $pdo;
$client_id = getCurrentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

// ── 1. التحقق من CSRF ────────────────────────────────────────
$csrf_token = filter_input(INPUT_POST, 'csrf_token');
if (!verifyCsrfToken($csrf_token)) {
    set_message("خطأ في التحقق الأمني. حاول مرة أخرى.", "danger");
    header("Location: ../services.php");
    exit();
}

// ── 2. جلب وتصفية بيانات الإدخال ────────────────────────────
$service_id     = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$payment_method = trim(filter_input(INPUT_POST, 'payment_method') ?? '');
// [FIX] جلب رقم الحساب من الحقل المخصص في الفورم
$account_num    = trim(filter_input(INPUT_POST, 'account_number') ?? ''); 
$notes          = trim(filter_input(INPUT_POST, 'notes') ?? '');
$notes          = mb_substr($notes, 0, 500); // حد أقصى 500 حرف

// التحقق من البيانات الأساسية
if (!$service_id || empty($payment_method)) {
    set_message("بيانات الطلب غير مكتملة أو غير صالحة.", "danger");
    $safe_id = $service_id ? (int)$service_id : 0;
    header("Location: ../service_detail.php?id={$safe_id}");
    exit();
}

// قيم payment_method المسموحة (تأكد من مطابقتها لما يرسله الفورم)
$allowed_methods = ['بطاقة ائتمان', 'دفع عند التسليم', 'محفظة إلكترونية', 'باي بال', 'جوال باي', 'تحويل بنكي'];
if (!in_array($payment_method, $allowed_methods, true)) {
    set_message("طريقة الدفع غير صالحة.", "danger");
    header("Location: ../service_detail.php?id={$service_id}");
    exit();
}

// ── 3. جلب بيانات الخدمة من DB (أمان: لا نثق بـ POST) ───────
$stmt_svc = $pdo->prepare(
    "SELECT id, provider_id, price FROM services WHERE id = ? AND is_active = 1"
);
$stmt_svc->execute([$service_id]);
$service = $stmt_svc->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    set_message("الخدمة غير موجودة أو غير متاحة.", "danger");
    header("Location: ../services.php");
    exit();
}

$provider_id = $service['provider_id']; 
$price       = $service['price'];

// ── 4. لا يمكن للمزود طلب خدمته الخاصة ─────────────────────
if ($provider_id == $client_id) {
    set_message("لا يمكنك طلب خدمتك الخاصة.", "warning");
    header("Location: ../service_detail.php?id={$service_id}");
    exit();
}

// ── 5. منع الطلب المكرر ──────────────────────────────────────
try {
    $stmt_dup = $pdo->prepare(
        "SELECT id FROM orders WHERE service_id = ? AND client_id = ? AND status = 'pending' LIMIT 1"
    );
    $stmt_dup->execute([$service_id, $client_id]);
    if ($stmt_dup->fetchColumn()) {
        set_message("لديك طلب قيد الانتظار لهذه الخدمة مسبقاً.", "warning");
        header("Location: ../service_detail.php?id={$service_id}");
        exit();
    }
} catch (PDOException $e) { }

// ── 6. تسجيل الطلب في قاعدة البيانات ───────────────────────
try {
    // [FIX] إضافة payment_status و account_number لتجنب أخطاء SQL
    $sql = "INSERT INTO orders 
                (service_id, client_id, provider_id, amount, status, payment_method, payment_status, account_number, notes, order_date)
            VALUES 
                (?, ?, ?, ?, 'pending', ?, 'pending', ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $service_id,
        $client_id,
        $provider_id,
        $price,
        $payment_method,
        $account_num, // سيتم تخزينه في عمود account_number
        $notes ?: null,
    ]);

    $order_id = $pdo->lastInsertId();

    set_message(
        "✅ تم إتمام طلبك رقم #{$order_id} بنجاح! طريقة الدفع: {$payment_method}.",
        "success"
    );
    header("Location: ../client_dashboard.php");
    exit();

} catch (PDOException $e) {
    error_log("place_order DB Error: " . $e->getMessage());
    $debug_msg = (ini_get('display_errors')) ? " [DEBUG: " . $e->getMessage() . "]" : "";
    set_message("حدث خطأ أثناء معالجة الطلب." . $debug_msg, "danger");
    header("Location: ../service_detail.php?id={$service_id}");
    exit();
}
?>